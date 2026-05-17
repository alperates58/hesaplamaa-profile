<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI job ve rapor tablolarını yönetir.
 * wp_hap_profile_ai_jobs + wp_hap_profile_ai_reports
 */
class HAP_Profile_AI_Reports {

	const TABLE_JOBS    = 'hap_profile_ai_jobs';
	const TABLE_REPORTS = 'hap_profile_ai_reports';

	// -------------------------------------------------------
	// Job yönetimi
	// -------------------------------------------------------

	/**
	 * @param int    $user_id
	 * @param string $input_hash
	 * @return int|false  Job ID
	 */
	public static function create_job( $user_id, $input_hash ) {
		global $wpdb;
		$user_id    = absint( $user_id );
		$input_hash = sanitize_text_field( $input_hash );
		if ( ! $user_id ) {
			return false;
		}
		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_JOBS,
			array(
				'user_id'    => $user_id,
				'status'     => 'pending',
				'input_hash' => $input_hash,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param int $job_id
	 * @return bool
	 */
	public static function mark_job_processing( $job_id ) {
		return self::update_job_status( $job_id, 'processing', array( 'started_at' => current_time( 'mysql' ) ) );
	}

	/**
	 * @param int    $job_id
	 * @param string $message
	 * @return bool
	 */
	public static function mark_job_failed( $job_id, $message ) {
		return self::update_job_status(
			$job_id,
			'failed',
			array(
				'error_message' => substr( sanitize_textarea_field( $message ), 0, 2000 ),
				'finished_at'   => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * @param int $job_id
	 * @return bool
	 */
	public static function mark_job_completed( $job_id ) {
		return self::update_job_status( $job_id, 'completed', array( 'finished_at' => current_time( 'mysql' ) ) );
	}

	/**
	 * @param int $user_id
	 * @return array|null
	 */
	public static function get_latest_job( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_JOBS;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE user_id = %d ORDER BY id DESC LIMIT 1",
				absint( $user_id )
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	// -------------------------------------------------------
	// Rapor yönetimi
	// -------------------------------------------------------

	/**
	 * @param int $user_id
	 * @return array|null
	 */
	public static function get_latest_report( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REPORTS;
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE user_id = %d ORDER BY id DESC LIMIT 1",
				absint( $user_id )
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( ! empty( $row['sections_json'] ) ) {
			$decoded = json_decode( $row['sections_json'], true );
			$row['sections'] = is_array( $decoded ) ? $decoded : array();
		} else {
			$row['sections'] = array();
		}
		return $row;
	}

	/**
	 * AI API yanıtından rapor kaydeder, job'ı completed yapar.
	 *
	 * @param int    $user_id
	 * @param int    $job_id
	 * @param array  $payload   build_ai_payload çıktısı
	 * @param array  $ai_response  AI provider'dan gelen normalize edilmiş yanıt
	 * @return int|false  Rapor ID
	 */
	public static function save_report( $user_id, $job_id, $payload, $ai_response ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$job_id   = absint( $job_id );
		if ( ! $user_id ) {
			return false;
		}

		$now     = current_time( 'mysql' );
		$summary = sanitize_textarea_field( $ai_response['summary'] ?? '' );
		$full    = wp_kses_post( $ai_response['full_report'] ?? '' );

		$sections_raw = $ai_response['sections'] ?? array();
		if ( ! is_array( $sections_raw ) ) {
			$sections_raw = array();
		}
		$sections_sanitized = array();
		foreach ( $sections_raw as $key => $content ) {
			$sections_sanitized[ sanitize_key( $key ) ] = wp_kses_post( (string) $content );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_REPORTS,
			array(
				'user_id'          => $user_id,
				'job_id'           => $job_id ?: null,
				'input_hash'       => sanitize_text_field( $payload['input_hash'] ?? '' ),
				'model'            => sanitize_text_field( $ai_response['model'] ?? '' ),
				'language'         => sanitize_key( $ai_response['language'] ?? 'tr' ),
				'tone'             => sanitize_key( $ai_response['tone'] ?? 'friendly' ),
				'summary'          => $summary,
				'full_report'      => $full,
				'sections_json'    => wp_json_encode( $sections_sanitized ),
				'tokens_prompt'    => absint( $ai_response['tokens_prompt'] ?? 0 ) ?: null,
				'tokens_completion'=> absint( $ai_response['tokens_completion'] ?? 0 ) ?: null,
				'cost_estimate'    => isset( $ai_response['cost_estimate'] ) ? (float) $ai_response['cost_estimate'] : null,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s' )
		);

		if ( false !== $result ) {
			$report_id = (int) $wpdb->insert_id;
			if ( $job_id ) {
				self::mark_job_completed( $job_id );
			}
			return $report_id;
		}
		return false;
	}

	// -------------------------------------------------------
	// Payload builder
	// -------------------------------------------------------

	/**
	 * AI'a gönderilecek payload'ı oluşturur.
	 * Sadece ai_include=1 ve kullanıcı izinli (sensitif değil veya izin verilmiş) alanlar gönderilir.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function build_ai_payload( $user_id ) {
		$user_id = absint( $user_id );

		// Profil verisini al.
		$fields_obj = new HAP_Profile_Fields();
		$ud         = new HAP_Profile_User_Data( $fields_obj );
		$profile    = $ud->get_user_profile_data( $user_id );

		// AI'a dahil edilecek alanlar.
		$ai_fields = array();
		foreach ( HAP_Profile_Fields::get_active_fields() as $field ) {
			if ( empty( $field['ai_include'] ) ) {
				continue;
			}
			$key = $field['field_key'];
			if ( isset( $profile[ $key ] ) && '' !== (string) $profile[ $key ] ) {
				$ai_fields[ $key ] = $profile[ $key ];
			}
		}

		// Hazır hesaplama sonuçları.
		$ready_results = array();
		if ( class_exists( 'HAP_Profile_Results_Store' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'hap_profile_results';
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT module_slug, normalized_result, result_json FROM `{$table}` WHERE user_id = %d AND status = 'ready_result' LIMIT 100",
					$user_id
				),
				ARRAY_A
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$json   = $row['normalized_result'] ?: $row['result_json'];
					$parsed = $json ? json_decode( $json, true ) : null;
					if ( is_array( $parsed ) ) {
						$ready_results[ $row['module_slug'] ] = $parsed;
					}
				}
			}
		}

		// Yakında / frontend-only modüller.
		$pending_summary = array();
		if ( class_exists( 'HAP_Profile_Modules' ) ) {
			$modules_obj = new HAP_Profile_Modules();
			$all         = $modules_obj->get_modules(
				array(
					'availability_status' => 'active',
					'limit'               => 200,
				)
			);
			foreach ( $all as $mod ) {
				if ( in_array( $mod['runner_type'] ?? '', array( 'js_frontend_only', 'frontend_js_only', 'pending_adapter' ), true ) ) {
					$pending_summary[] = $mod['title'] ?: $mod['slug'];
				}
			}
		}

		return array(
			'input_hash'             => self::make_input_hash( $user_id ),
			'generated_at'           => current_time( 'mysql' ),
			'profile_fields'         => $ai_fields,
			'ready_results'          => $ready_results,
			'future_results_summary' => $pending_summary,
			'consent_status'         => array(
				'ai_consent' => class_exists( 'HAP_Profile_Consents' ) ? HAP_Profile_Consents::has_ai_consent( $user_id ) : false,
			),
			'disclaimers'            => array(
				'health'    => 'Bu içerik yalnızca bilgilendirme amaçlıdır; tıbbi tavsiye değildir.',
				'astrology' => 'Astroloji ve numeroloji eğlence ve farkındalık amacıyla sunulur.',
			),
		);
	}

	/**
	 * Kullanıcının profil verisi değişmiş mi kontrolü için hash üretir.
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function make_input_hash( $user_id ) {
		$user_id    = absint( $user_id );
		$fields_obj = new HAP_Profile_Fields();
		$ud         = new HAP_Profile_User_Data( $fields_obj );
		$profile    = $ud->get_user_profile_data( $user_id );
		return md5( $user_id . serialize( $profile ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Mevcut rapor güncel mi yoksa yeniden üretmek gerekir mi?
	 *
	 * @param int $user_id
	 * @return bool  true = yeniden üret
	 */
	public static function should_regenerate_report( $user_id ) {
		$report = self::get_latest_report( $user_id );
		if ( ! $report ) {
			return true;
		}
		$current_hash = self::make_input_hash( $user_id );
		return $report['input_hash'] !== $current_hash;
	}

	// -------------------------------------------------------
	// Yardımcı
	// -------------------------------------------------------

	private static function update_job_status( $job_id, $status, $extra = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_JOBS;
		$data  = array_merge(
			array( 'status' => sanitize_key( $status ), 'updated_at' => current_time( 'mysql' ) ),
			$extra
		);
		$result = $wpdb->update( $table, $data, array( 'id' => absint( $job_id ) ) );
		return false !== $result;
	}
}
