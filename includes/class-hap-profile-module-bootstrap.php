<?php
/**
 * Hesaplama Suite adaptörü bulunan yeni modülleri wp_hap_profile_modules
 * tablosuna tek seferlik olarak upsert eden bootstrap sınıfı.
 *
 * Tetikleme: admin_init sırasında, version option yoksa çalışır.
 * Mevcut kayıtlara dokunmaz; sadece eksik slug'ları INSERT eder.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Module_Bootstrap {

	const VERSION_OPTION = 'hap_module_bootstrap_v3';

	/**
	 * Bootstrap gerekiyorsa çalıştır.
	 */
	public static function maybe_run() {
		if ( get_option( self::VERSION_OPTION ) ) {
			return;
		}
		self::run();
		update_option( self::VERSION_OPTION, '1', false );
	}

	/**
	 * Yeni modül kayıtlarını upsert et.
	 * Mevcut kayda dokunmaz (INSERT IGNORE mantığı).
	 */
	public static function run() {
		global $wpdb;
		$table = $wpdb->prefix . HAP_TABLE_MODULES;
		$now   = current_time( 'mysql' );

		$modules = self::get_module_definitions();

		foreach ( $modules as $def ) {
			$slug     = sanitize_key( $def['slug'] );
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $existing ) {
				// Sadece runner ayarlarını güncelle; kullanıcı ayarlarına (profile_status, sort_order) dokunma
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'runner_type'            => 'calculate_api',
						'runner_status'          => 'ok',
						'suite_backend_supported' => 1,
						'result_enabled'         => 1,
						'required_fields'        => wp_json_encode( $def['required_fields'] ),
						'updated_at'             => $now,
					),
					array( 'id' => (int) $existing ),
					array( '%s', '%s', '%d', '%d', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'slug'                      => $slug,
						'title'                     => sanitize_text_field( $def['title'] ),
						'shortcode'                 => '',
						'section'                   => sanitize_key( $def['section'] ),
						'profile_status'            => 'profile_optional',
						'result_enabled'            => 1,
						'onboarding_prompt_enabled' => 1,
						'ai_include'                => 1,
						'ai_enabled'                => 0,
						'runner_type'               => 'calculate_api',
						'runner_status'             => 'ok',
						'suite_backend_supported'   => 1,
						'suite_source'              => 'bootstrap',
						'suite_last_synced_at'      => $now,
						'suite_section'             => sanitize_key( $def['section'] ),
						'required_fields'           => wp_json_encode( $def['required_fields'] ),
						'suite_required_fields'     => wp_json_encode( $def['required_fields'] ),
						'input_mapping'             => '{}',
						'suite_input_mapping'       => '{}',
						'source'                    => 'bootstrap',
						'availability_status'       => 'active',
						'missing_fields_behavior'   => 'show_prompt',
						'sort_order'                => 0,
						'share_include_default'     => 0,
						'notes'                     => '',
						'created_at'                => $now,
						'updated_at'                => $now,
					)
				);
			}
		}
	}

	/**
	 * Bootstrap edilecek modül tanımları.
	 *
	 * required_fields: wp_hap_profile_modules.required_fields alanı için
	 * (profil doldurma kontrolünde kullanılır).
	 *
	 * @return array[]
	 */
	private static function get_module_definitions() {
		return array(
			// ── Sağlık & Metabolizma ──────────────────────────────────────
			array(
				'slug'            => 'bazal-metabolizma-hizi-hesaplama',
				'title'           => 'Bazal Metabolizma Hızı',
				'section'         => 'health',
				'required_fields' => array( 'weight', 'height', 'birth_date', 'gender' ),
			),
			array(
				'slug'            => 'dinlenme-metabolizma-hizi',
				'title'           => 'Dinlenme Metabolizma Hızı',
				'section'         => 'health',
				'required_fields' => array( 'weight', 'height', 'birth_date', 'gender' ),
			),
			array(
				'slug'            => 'basit-kalori-ihtiyaci-hesaplama',
				'title'           => 'Basit Kalori İhtiyacı',
				'section'         => 'health',
				'required_fields' => array( 'weight' ),
			),
			// ── Nabız ────────────────────────────────────────────────────
			array(
				'slug'            => 'maksimum-nabiz-hesaplama',
				'title'           => 'Maksimum Nabız',
				'section'         => 'health',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'hedef-nabiz-bolgesi-hesaplama',
				'title'           => 'Hedef Nabız Bölgesi',
				'section'         => 'health',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'hedef-nabiz-hesaplama',
				'title'           => 'Hedef Nabız (Karvonen)',
				'section'         => 'health',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'nabiz-bolgesi-hesaplama',
				'title'           => 'Nabız Bölgesi',
				'section'         => 'health',
				'required_fields' => array( 'birth_date' ),
			),
			// ── Gezegenler ───────────────────────────────────────────────
			array(
				'slug'            => 'venus-burcu-hesaplama',
				'title'           => 'Venüs Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'mars-burcu-hesaplama',
				'title'           => 'Mars Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'jupiter-burcu-hesaplama',
				'title'           => 'Jüpiter Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'saturn-burcu-hesaplama',
				'title'           => 'Satürn Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			// ── Sidereal & Çin Burcu ─────────────────────────────────────
			array(
				'slug'            => 'sidereal-burc-hesaplama',
				'title'           => 'Sidereal Burç',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'cin-burcu-yili-hesaplama',
				'title'           => 'Çin Burcu (Yıl)',
				'section'         => 'symbolic',
				'required_fields' => array( 'birth_date' ),
			),
			// ── Modül Genişletme Paketi 2 ────────────────────────────────
			array(
				'slug'            => 'merkur-burcu-hesaplama',
				'title'           => 'Merkür Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'uranus-burcu-hesaplama',
				'title'           => 'Uranüs Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'neptun-burcu-hesaplama',
				'title'           => 'Neptün Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'pluton-burcu-hesaplama',
				'title'           => 'Plüton Burcu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'vedik-burc-hesaplama',
				'title'           => 'Vedik Burç',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'kuzey-ay-dugumu-hesaplama',
				'title'           => 'Kuzey Ay Düğümü',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'cin-burcu-dongusu-hesaplama',
				'title'           => 'Çin Burcu Döngüsü',
				'section'         => 'symbolic',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'burc-elementi-hesaplama',
				'title'           => 'Burç Elementi',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'burc-grubu-hesaplama',
				'title'           => 'Burç Grubu',
				'section'         => 'astrology',
				'required_fields' => array( 'birth_date' ),
			),
			array(
				'slug'            => 'aktivite-katsayisi',
				'title'           => 'Aktivite Katsayısı (PAL)',
				'section'         => 'health',
				'required_fields' => array( 'activity_level' ),
			),
			array(
				'slug'            => 'gunluk-kalori-ihtiyaci-hesaplama',
				'title'           => 'Günlük Kalori İhtiyacı',
				'section'         => 'health',
				'required_fields' => array( 'weight', 'height', 'birth_date', 'gender', 'activity_level' ),
			),
		);
	}
}
