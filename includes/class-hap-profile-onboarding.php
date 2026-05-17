<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Onboarding {

	private $fields;
	private $user_data;

	public function __construct( HAP_Profile_Fields $fields, HAP_Profile_User_Data $user_data ) {
		$this->fields    = $fields;
		$this->user_data = $user_data;
	}

	// -------------------------------------------------------
	// Step yönetimi
	// -------------------------------------------------------

	public function get_steps() {
		$steps = array();
		foreach ( HAP_Profile_Fields::get_active_steps() as $index => $step ) {
			$step_key = $step['step_key'];
			$steps[ $step_key ] = array(
				'id'              => $step_key,
				'number'          => $index + 1,
				'title'           => $step['title'],
				'subtitle'        => $step['description'],
				'description'     => $step['description'],
				'fields'          => wp_list_pluck( HAP_Profile_Fields::get_fields_by_step( $step_key ), 'field_key' ),
				'optional'        => empty( $step['is_required'] ),
				'icon'            => $step['icon'],
				'completion_rule' => $step['completion_rule'],
				'is_required'     => ! empty( $step['is_required'] ),
				'active'          => ! empty( $step['active'] ),
				'skippable'       => empty( $step['is_required'] ),
				'cta'             => 'Kaydet ve Devam Et',
			);
		}

		// account_consents adımı yoksa başa ekle (consent zorunlu)
		if ( ! isset( $steps['account_consents'] ) ) {
			$consent_step = array(
				'id'              => 'account_consents',
				'number'          => 0,
				'title'           => 'Onaylar',
				'subtitle'        => 'Devam etmek için lütfen aşağıdaki onayları verin.',
				'description'     => 'Devam etmek için lütfen aşağıdaki onayları verin.',
				'fields'          => array(),
				'optional'        => false,
				'icon'            => '📋',
				'completion_rule' => 'all_consents',
				'is_required'     => true,
				'active'          => true,
				'skippable'       => false,
				'cta'             => 'Onaylayıp Devam Et',
			);
			$steps = array( 'account_consents' => $consent_step ) + $steps;
			// Numaraları yeniden sırala
			$i = 1;
			foreach ( $steps as &$s ) {
				$s['number'] = $i++;
			}
			unset( $s );
		}

		// review_generate adımı yoksa sona ekle
		if ( ! isset( $steps['review_generate'] ) ) {
			$count = count( $steps );
			$steps['review_generate'] = array(
				'id'              => 'review_generate',
				'number'          => $count + 1,
				'title'           => 'Sonuçlarını Hazırlayalım',
				'subtitle'        => 'Tüm bilgilerin hazır. Analiz sonuçlarını oluşturmak için hazır olduğunda butona bas.',
				'description'     => 'Tüm bilgilerin hazır. Analiz sonuçlarını oluşturmak için hazır olduğunda butona bas.',
				'fields'          => array(),
				'optional'        => false,
				'icon'            => '🚀',
				'completion_rule' => 'generate_results',
				'is_required'     => true,
				'active'          => true,
				'skippable'       => false,
				'cta'             => 'Beni Sonuçlarıma Götür',
			);
		}

		return $steps;
	}

	public function get_step_order() {
		return array_keys( $this->get_steps() );
	}

	public function get_step( $step_id ) {
		$steps = $this->get_steps();
		return $steps[ $step_id ] ?? null;
	}

	public function get_field_options( $key ) {
		return HAP_Profile_Fields::get_field_options( $key );
	}

	public function get_step_fields( $step_id ) {
		return HAP_Profile_Fields::get_fields_by_step( $step_id );
	}

	/**
	 * Onboarding adımı için bu adımda doldurulan alanlar kaç modül açıyor?
	 *
	 * @param string $step_id
	 * @return array  [ 'module_count' => int, 'backend_count' => int, 'benefit_text' => string ]
	 */
	public function get_step_benefit( $step_id ) {
		$step_fields  = $this->get_step_fields( $step_id );
		$module_count  = 0;
		$backend_count = 0;
		$benefit_parts = array();

		foreach ( $step_fields as $field ) {
			$impact         = HAP_Profile_Fields::get_field_impact( $field['field_key'] );
			$module_count  += (int) ( $impact['module_count'] ?? 0 );
			$backend_count += (int) ( $impact['backend_count'] ?? 0 );
		}

		// Deduplicate
		if ( $module_count > 0 ) {
			$benefit_parts[] = $module_count . ' analiz';
		}
		if ( $backend_count > 0 ) {
			$benefit_parts[] = $backend_count . ' hazır sonuç';
		}

		return array(
			'module_count'  => $module_count,
			'backend_count' => $backend_count,
			'benefit_text'  => empty( $benefit_parts ) ? '' : implode( ', ', $benefit_parts ) . ' açılır',
		);
	}

	public function is_step_complete( $user_id, $step_id, array $profile = array() ) {
		$step = $this->get_step( $step_id );
		if ( ! $step ) {
			return false;
		}

		// Consent adımı
		if ( 'account_consents' === $step_id ) {
			if ( ! class_exists( 'HAP_Profile_Consents' ) ) {
				return false;
			}
			return HAP_Profile_Consents::has_required_consents( $user_id );
		}

		// Generate adımı — profil minimum tamamlanmışsa tamamdır
		if ( 'review_generate' === $step_id ) {
			return $this->user_data->is_minimum_profile_complete( $user_id, $profile ?: null );
		}

		if ( empty( $profile ) ) {
			$profile = $this->user_data->get_user_profile_data( $user_id );
		}

		$fields = $this->get_step_fields( $step_id );
		if ( empty( $fields ) ) {
			return true;
		}

		$filled = 0;
		foreach ( $fields as $field ) {
			if ( $this->user_data->is_field_filled( $field['field_key'], $profile ) ) {
				$filled++;
			}
		}

		if ( 'any_field' === $step['completion_rule'] ) {
			return $filled > 0;
		}

		return $filled === count( $fields );
	}

	public function get_initial_step( $user_id ) {
		$user_id = absint( $user_id );
		$profile = $this->user_data->get_user_profile_data( $user_id );

		foreach ( $this->get_step_order() as $step_id ) {
			$step = $this->get_step( $step_id );
			if ( ! $step || empty( $step['active'] ) ) {
				continue;
			}
			if ( 'review_generate' === $step_id ) {
				continue; // Son adım her zaman sondan gelir
			}
			if ( ! $this->is_step_complete( $user_id, $step_id, $profile ) ) {
				return $step_id;
			}
		}

		return 'review_generate';
	}

	public function are_required_steps_complete( $user_id ) {
		$profile = $this->user_data->get_user_profile_data( $user_id );
		foreach ( $this->get_step_order() as $step_id ) {
			if ( 'review_generate' === $step_id ) {
				continue;
			}
			$step = $this->get_step( $step_id );
			if ( ! empty( $step['is_required'] ) && ! $this->is_step_complete( $user_id, $step_id, $profile ) ) {
				return false;
			}
		}
		return true;
	}

	// -------------------------------------------------------
	// AJAX Handler'lar
	// -------------------------------------------------------

	/**
	 * Onboarding step kaydet.
	 */
	public function handle_save_step() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		$step_id = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$step    = $this->get_step( $step_id );

		if ( ! $step ) {
			wp_send_json_error( array( 'message' => 'Geçersiz onboarding adımı.' ) );
		}

		$user_id = get_current_user_id();

		// Consent adımı özel işleme
		if ( 'account_consents' === $step_id ) {
			wp_send_json_error( array( 'message' => 'Onaylar için hap_save_consents action kullanın.' ) );
		}

		// review_generate adımı özel işleme
		if ( 'review_generate' === $step_id ) {
			wp_send_json_error( array( 'message' => 'Sonuç oluşturma için hap_generate_profile_results kullanın.' ) );
		}

		$payload = isset( $_POST['profile_data'] ) ? (array) $_POST['profile_data'] : array();
		$data    = array();
		foreach ( $step['fields'] as $field_key ) {
			if ( array_key_exists( $field_key, $payload ) ) {
				$data[ $field_key ] = wp_unslash( $payload[ $field_key ] );
			}
		}

		$this->user_data->save_user_data( $user_id, $data );

		$profile = $this->user_data->get_user_profile_data( $user_id );
		if ( ! empty( $step['is_required'] ) && ! $this->is_step_complete( $user_id, $step_id, $profile ) ) {
			wp_send_json_error(
				array(
					'message'        => 'Gerekli alanları tamamlaman gerekiyor.',
					'missing_fields' => array_values(
						array_filter(
							$step['fields'],
							function ( $field_key ) use ( $profile ) {
								return empty( $profile[ $field_key ] ) && '0' !== (string) ( $profile[ $field_key ] ?? '' );
							}
						)
					),
				)
			);
		}

		$order         = $this->get_step_order();
		$current_index = array_search( $step_id, $order, true );
		$next_step     = false !== $current_index && isset( $order[ $current_index + 1 ] ) ? $order[ $current_index + 1 ] : $step_id;

		wp_send_json_success(
			array(
				'message'            => 'Onboarding adımı kaydedildi.',
				'next_step'          => $next_step,
				'minimum_complete'   => $this->user_data->is_minimum_profile_complete( $user_id, $profile ),
				'minimum_completion' => $this->user_data->get_minimum_profile_completion( $user_id, $profile ),
				'missing_minimum'    => $this->user_data->get_minimum_profile_missing_fields( $user_id, $profile ),
			)
		);
	}

	/**
	 * Consent kaydet (KVKK / gizlilik / kullanım / AI).
	 */
	public function handle_save_consents() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		if ( ! class_exists( 'HAP_Profile_Consents' ) ) {
			wp_send_json_error( array( 'message' => 'Consent sistemi yüklenemedi.' ) );
		}

		$user_id  = get_current_user_id();
		$consents = isset( $_POST['consents'] ) ? (array) $_POST['consents'] : array();

		$required_types = HAP_Profile_Consents::get_required_consent_types();
		$required_types[] = HAP_Profile_Consents::get_ai_consent_type();

		foreach ( $required_types as $type ) {
			$accepted = ! empty( $consents[ $type ] );
			HAP_Profile_Consents::save_consent( $user_id, $type, $accepted );
		}

		if ( ! HAP_Profile_Consents::has_required_consents( $user_id ) ) {
			wp_send_json_error( array(
				'message' => 'KVKK, gizlilik politikası ve kullanım şartları onayları zorunludur.',
			) );
		}

		$order         = $this->get_step_order();
		$current_index = array_search( 'account_consents', $order, true );
		$next_step     = false !== $current_index && isset( $order[ $current_index + 1 ] ) ? $order[ $current_index + 1 ] : 'basic_profile';

		wp_send_json_success( array(
			'message'    => 'Onaylar kaydedildi.',
			'next_step'  => $next_step,
			'ai_consent' => HAP_Profile_Consents::has_ai_consent( $user_id ),
		) );
	}

	/**
	 * Final step: "Beni sonuçlarıma götür"
	 * Kayıt ekranından çağrılmaz; sadece review_generate stepinden çağrılır.
	 */
	public function handle_generate_results() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		$user_id = get_current_user_id();

		// 1. Consent kontrol
		if ( class_exists( 'HAP_Profile_Consents' ) && ! HAP_Profile_Consents::has_required_consents( $user_id ) ) {
			wp_send_json_error( array(
				'message' => 'KVKK ve gizlilik onayları gerekli.',
				'code'    => 'consent_required',
			) );
		}

		// 2. Minimum profil kontrol
		if ( ! $this->user_data->is_minimum_profile_complete( $user_id ) ) {
			$missing = HAP_Profile_Fields::get_missing_required_fields_for_user( $user_id );
			wp_send_json_error( array(
				'message'        => 'Minimum profil alanları tamamlanmamış.',
				'code'           => 'incomplete_profile',
				'missing_fields' => $missing,
			) );
		}

		// 3. Modül runner — hesaplama sonuçlarını üret ve DB'ye kaydet
		$profile = $this->user_data->get_user_profile_data( $user_id );
		$runner_results = array();
		if ( class_exists( 'HAP_Profile_Module_Runner' ) ) {
			$runner_results = HAP_Profile_Module_Runner::run_modules_for_user( $user_id, null, $profile );
		}

		$ready_count   = 0;
		$pending_count = 0;
		foreach ( $runner_results as $slug => $item ) {
			if ( 'ready_result' === ( $item['state'] ?? '' ) ) {
				$ready_count++;
			} else {
				$pending_count++;
			}
		}

		// 4. AI consent varsa ve AI etkinse job oluştur
		$ai_status     = 'ai_disabled';
		$ai_consent    = class_exists( 'HAP_Profile_Consents' ) && HAP_Profile_Consents::has_ai_consent( $user_id );
		$ai_enabled    = class_exists( 'HAP_Profile_AI_Provider' ) && HAP_Profile_AI_Provider::is_enabled();
		$auto_generate = false;
		if ( class_exists( 'HAP_Profile_AI_Provider' ) ) {
			$ai_settings   = HAP_Profile_AI_Provider::get_settings();
			$auto_generate = ! empty( $ai_settings['ai_auto_generate_after_onboarding'] );
		}

		if ( $ai_consent && $ai_enabled && $auto_generate && class_exists( 'HAP_Profile_AI_Reports' ) ) {
			if ( HAP_Profile_AI_Reports::should_regenerate_report( $user_id ) ) {
				$input_hash = HAP_Profile_AI_Reports::make_input_hash( $user_id );
				$job_id     = HAP_Profile_AI_Reports::create_job( $user_id, $input_hash );
				// Async job — gerçek AI çağrısı WP cron veya background task ile yapılmalı.
				// Bu MVP'de sync çalıştır (küçük site için kabul edilebilir).
				if ( $job_id ) {
					HAP_Profile_AI_Reports::mark_job_processing( $job_id );
					$payload  = HAP_Profile_AI_Reports::build_ai_payload( $user_id );
					$response = HAP_Profile_AI_Provider::generate_report( $payload );
					if ( isset( $response['error'] ) ) {
						HAP_Profile_AI_Reports::mark_job_failed( $job_id, $response['error'] );
						$ai_status = 'failed';
					} else {
						HAP_Profile_AI_Reports::save_report( $user_id, $job_id, $payload, $response );
						$ai_status = 'completed';
					}
				}
			} else {
				$ai_status = 'completed';
			}
		} elseif ( ! $ai_consent ) {
			$ai_status = 'consent_required';
		} elseif ( ! $ai_enabled ) {
			$settings_check = class_exists( 'HAP_Profile_AI_Provider' ) ? HAP_Profile_AI_Provider::get_settings() : array();
			$ai_status = empty( $settings_check['ai_enabled'] ) ? 'ai_disabled' : 'pending_configuration';
		}

		// 5. Dashboard URL
		$settings         = get_option( 'hap_profile_settings', array() );
		$profile_page_id  = absint( $settings['profile_page_id'] ?? 0 );
		$dashboard_url    = $profile_page_id ? get_permalink( $profile_page_id ) : home_url( '/profilim/' );

		$redirect_url = add_query_arg(
			'ai_status',
			rawurlencode( $ai_status ),
			$dashboard_url
		);

		wp_send_json_success( array(
			'message'       => 'Sonuçların hazırlandı!',
			'ready_count'   => $ready_count,
			'pending_count' => $pending_count,
			'ai_status'     => $ai_status,
			'redirect_url'  => $redirect_url,
		) );
	}
}
