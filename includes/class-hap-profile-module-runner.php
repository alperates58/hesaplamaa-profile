<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kullanıcının profil verilerini alır, aktif modüllerle eşleştirir,
 * her modül için bir runner sonucu üretir.
 *
 * Hesaplama formülleri HARDCODE edilmez.
 * Mevcut Hesaplama Suite modülleri sadece frontend_js_only olduğu için
 * runner, backend API hazır olana kadar 'frontend_only' durumu döner.
 */
class HAP_Profile_Module_Runner {

	// Finans/alakasız içerik filtresi — slug içinde geçenler dashboard'da gösterilmez.
	private static $finance_keywords = array(
		'portfoy', 'beta', 'kredi', 'faiz', 'yatirim',
		'borsa', 'vergi', 'gelir', 'gider', 'maliyet',
		'butce', 'muhasebe', 'kur',
	);

	// -------------------------------------------------------
	// Yardımcı metodlar
	// -------------------------------------------------------

	public static function is_profile_relevant( $module ) {
		$slug = strtolower( sanitize_title( $module['slug'] ) );
		foreach ( self::$finance_keywords as $kw ) {
			if ( false !== strpos( $slug, $kw ) ) {
				return false;
			}
		}
		return true;
	}

	public static function get_tool_url( $module ) {
		// Admin'in girdiği URL önceliklidir.
		if ( ! empty( $module['tool_url'] ) ) {
			return esc_url_raw( $module['tool_url'] );
		}
		// Slug ile WordPress yazısı ara.
		$post = get_page_by_path( sanitize_title( $module['slug'] ), OBJECT, 'post' );
		if ( $post ) {
			return get_permalink( $post );
		}
		return null;
	}

	public static function get_missing_fields( $module, $profile_data ) {
		$required = array();
		if ( ! empty( $module['required_fields'] ) ) {
			if ( is_string( $module['required_fields'] ) ) {
				$decoded  = json_decode( $module['required_fields'], true );
				$required = is_array( $decoded )
					? $decoded
					: array_map( 'trim', explode( ',', $module['required_fields'] ) );
			} elseif ( is_array( $module['required_fields'] ) ) {
				$required = $module['required_fields'];
			}
		}

		$missing = array();
		foreach ( $required as $field ) {
			$field = trim( $field );
			if ( '' === $field ) {
				continue;
			}
			if ( empty( $profile_data[ $field ] ) ) {
				$missing[] = $field;
			}
		}
		return $missing;
	}

	/**
	 * Modülün çalıştırma yeteneğini tespit eder.
	 * Önce DB'deki runner_type'a bakar, yoksa Suite Inspector'ı kullanır.
	 */
	public static function detect_module_capabilities( $module ) {
		// Admin'in yapılandırdığı runner_type önceliğe sahip.
		if ( ! empty( $module['runner_type'] ) && 'none' !== $module['runner_type'] ) {
			return $module['runner_type'];
		}

		if ( class_exists( 'HAP_Profile_Suite_Inspector' ) ) {
			$cap = HAP_Profile_Suite_Inspector::get_capability_status( $module['slug'] );
			if ( 'php_callable' === $cap ) {
				return 'php_callback';
			}
		}

		// Hesaplama Suite tüm modülleri şu an frontend-only.
		return 'js_frontend_only';
	}

	public static function build_payload_for_module( $module, $profile_data ) {
		$input_mapping = array();
		if ( ! empty( $module['input_mapping'] ) ) {
			$decoded = json_decode( $module['input_mapping'], true );
			if ( is_array( $decoded ) ) {
				$input_mapping = $decoded;
			}
		}

		$payload = array();
		foreach ( $input_mapping as $profile_field => $module_param ) {
			if ( isset( $profile_data[ $profile_field ] ) ) {
				$payload[ $module_param ] = $profile_data[ $profile_field ];
			}
		}

		// Mapping tanımlı değilse required_fields'ı doğrudan aktar.
		if ( empty( $payload ) ) {
			$required = json_decode( $module['required_fields'] ?? '[]', true ) ?: array();
			foreach ( $required as $field ) {
				if ( isset( $profile_data[ $field ] ) ) {
					$payload[ $field ] = $profile_data[ $field ];
				}
			}
		}

		return $payload;
	}

	public static function normalize_result( $module, $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			return array( 'value' => $raw, 'label' => $module['title'] ?? '' );
		}
		return array();
	}

	public static function get_user_message_for_state( $state ) {
		$map = array(
			'ready_result'               => 'Analiz sonucu hazır.',
			'missing_fields'             => 'Bu analiz için bazı bilgilerin eksik.',
			'frontend_only'              => 'Bilgilerin hazır. Sonuç bağlantısı hazırlanıyor.',
			'needs_backend_api'          => 'Bilgilerin hazır. Sonuç bağlantısı hazırlanıyor.',
			'needs_runner_mapping'       => 'Bilgilerin hazır; modül yapılandırması tamamlanıyor.',
			'runner_error'               => 'Sonuç yüklenemedi.',
			'filtered_by_profile_policy' => 'Bu analiz profil panelinde gösterilmiyor.',
		);
		return $map[ $state ] ?? '';
	}

	// -------------------------------------------------------
	// Ana çalıştırma metodları
	// -------------------------------------------------------

	/**
	 * Tek bir modülü kullanıcı profil verisiyle çalıştırır.
	 *
	 * @param array $module     DB'den gelen modül satırı.
	 * @param int   $user_id    WP kullanıcı ID.
	 * @param array $profile    Kullanıcı profil verisi (opsiyonel — verilmezse DB'den çekilir).
	 * @return array            runner result array.
	 */
	public static function run_module_for_user( $module, $user_id, $profile = null ) {
		// Finans/alakasız filtrele.
		if ( ! self::is_profile_relevant( $module ) ) {
			return array(
				'module'   => $module,
				'state'    => 'filtered_by_profile_policy',
				'message'  => self::get_user_message_for_state( 'filtered_by_profile_policy' ),
				'missing'  => array(),
				'result'   => null,
				'tool_url' => null,
			);
		}

		// Profil verisini al.
		if ( null === $profile ) {
			$fields_obj  = new HAP_Profile_Fields();
			$ud          = new HAP_Profile_User_Data( $fields_obj );
			$profile     = $ud->get_user_profile_data( $user_id );
		}

		// Eksik alan kontrolü.
		$missing = self::get_missing_fields( $module, $profile );
		if ( ! empty( $missing ) ) {
			return array(
				'module'   => $module,
				'state'    => 'missing_fields',
				'message'  => self::get_user_message_for_state( 'missing_fields' ),
				'missing'  => $missing,
				'result'   => null,
				'tool_url' => self::get_tool_url( $module ),
			);
		}

		// Kapasite tespiti.
		$capability = self::detect_module_capabilities( $module );

		// PHP callback varsa çağırmayı dene.
		if ( 'php_callback' === $capability && ! empty( $module['runner_callback'] ) ) {
			$cb = $module['runner_callback'];
			if ( is_callable( $cb ) ) {
				try {
					$payload = self::build_payload_for_module( $module, $profile );
					$raw     = call_user_func( $cb, $payload );
					if ( ! empty( $raw ) ) {
						return array(
							'module'   => $module,
							'state'    => 'ready_result',
							'message'  => self::get_user_message_for_state( 'ready_result' ),
							'missing'  => array(),
							'result'   => self::normalize_result( $module, $raw ),
							'tool_url' => self::get_tool_url( $module ),
						);
					}
				} catch ( Exception $e ) {
					return array(
						'module'   => $module,
						'state'    => 'runner_error',
						'message'  => self::get_user_message_for_state( 'runner_error' ),
						'missing'  => array(),
						'result'   => null,
						'tool_url' => self::get_tool_url( $module ),
					);
				}
			}
		}

		// Hesaplama Suite şu an tamamen frontend-only (JS hesaplıyor).
		// Kullanıcıya teknik mesaj değil, bağlantı hazırlanıyor mesajı gösterilir.
		return array(
			'module'   => $module,
			'state'    => 'frontend_only',
			'message'  => self::get_user_message_for_state( 'frontend_only' ),
			'missing'  => array(),
			'result'   => null,
			'tool_url' => self::get_tool_url( $module ),
		);
	}

	/**
	 * Belirtilen modül listesini kullanıcı için toplu çalıştırır.
	 *
	 * @param int   $user_id
	 * @param array $modules  profile_core/profile_optional modüller (zaten filtrelenmiş).
	 * @param array $profile  Kullanıcı profil verisi.
	 * @return array          runner results — keyed by module slug.
	 */
	public static function run_modules_for_user( $user_id, array $modules, array $profile ) {
		$results = array();
		foreach ( $modules as $module ) {
			$results[ $module['slug'] ] = self::run_module_for_user( $module, $user_id, $profile );
		}
		return $results;
	}

	// -------------------------------------------------------
	// MVP Mapping Presetleri
	// -------------------------------------------------------

	/**
	 * İlk geliştirme aşamasında bilinen modüller için input_mapping ve
	 * required_fields preset verisi döner.
	 * Bu metod DB'ye kayıt yapmaz — admin AJAX çağrısında kullanılır.
	 */
	public static function get_preset_mappings() {
		return array(
			'burc-dogum-araligi-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'birth_date' ),
				'input_mapping' => json_encode( array( 'birth_date' => 'dogum_tarihi' ) ),
				'runner_notes'  => 'Burç doğum aralığı; JS hesaplıyor. birth_date yeterli.',
			),
			'ay-burcu-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'birth_date', 'birth_time', 'birth_place' ),
				'input_mapping' => json_encode( array(
					'birth_date'  => 'dogum_tarihi',
					'birth_time'  => 'dogum_saati',
					'birth_place' => 'dogum_yeri',
				) ),
				'runner_notes'  => 'Ay burcu hesaplama; JS. Doğum saati ve yeri zorunlu.',
			),
			'burc-ve-ev-yerlesimi-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'birth_date', 'birth_time', 'birth_place' ),
				'input_mapping' => json_encode( array(
					'birth_date'  => 'dogum_tarihi',
					'birth_time'  => 'dogum_saati',
					'birth_place' => 'dogum_yeri',
				) ),
				'runner_notes'  => 'Ev yerleşimi; JS. Doğum saati ve yeri zorunlu.',
			),
			'ay-fazi-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'birth_date' ),
				'input_mapping' => json_encode( array( 'birth_date' => 'dogum_tarihi' ) ),
				'runner_notes'  => 'Ay fazı; JS. birth_date yeterli.',
			),
			'adimdan-kaloriye-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'weight', 'daily_steps' ),
				'input_mapping' => json_encode( array(
					'weight'      => 'kilo',
					'daily_steps' => 'adim',
				) ),
				'runner_notes'  => 'Adımdan kaloriye; JS. Kilo ve günlük adım gerekli.',
			),
			'gunluk-adim-hedefi-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'weight', 'activity_level' ),
				'input_mapping' => json_encode( array(
					'weight'         => 'kilo',
					'activity_level' => 'aktivite',
				) ),
				'runner_notes'  => 'Günlük adım hedefi; JS. Kilo ve aktivite gerekli.',
			),
			'bel-kalca-orani-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'height', 'weight', 'gender' ),
				'input_mapping' => json_encode( array(
					'height' => 'boy',
					'weight' => 'kilo',
					'gender' => 'cinsiyet',
				) ),
				'runner_notes'  => 'Bel-kalça oranı; JS. Boy, kilo, cinsiyet gerekli.',
			),
			'vucut-kitle-indeksi-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'height', 'weight' ),
				'input_mapping' => json_encode( array(
					'height' => 'boy',
					'weight' => 'kilo',
				) ),
				'runner_notes'  => 'VKİ hesaplama; JS. Boy ve kilo yeterli.',
			),
			'90-dakikalik-uyku-dongusu-hesaplama' => array(
				'runner_type'   => 'js_frontend_only',
				'required_fields' => array( 'sleep_hours' ),
				'input_mapping' => json_encode( array( 'sleep_hours' => 'uyku_saati' ) ),
				'runner_notes'  => '90 dk uyku döngüsü; JS. sleep_hours gerekli.',
			),
		);
	}
}
