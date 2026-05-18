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
		if ( ! empty( $module['tool_url'] ) ) {
			return esc_url_raw( $module['tool_url'] );
		}

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

	public static function detect_module_capabilities( $module ) {
		if ( ! empty( $module['runner_type'] ) && 'none' !== $module['runner_type'] ) {
			return $module['runner_type'];
		}

		if ( class_exists( 'HAP_Profile_Suite_Inspector' ) ) {
			$cap = HAP_Profile_Suite_Inspector::get_capability_status( $module['slug'] );
			if ( 'php_callable' === $cap ) {
				return 'php_callback';
			}
		}

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
				// Birincil anahtar: modül input adı (Suite'in beklediği)
				$payload[ $module_param ] = $profile_data[ $profile_field ];
				// Geriye uyumluluk: profile_field adıyla da ekle (overwrite etme)
				if ( $module_param !== $profile_field && ! isset( $payload[ $profile_field ] ) ) {
					$payload[ $profile_field ] = $profile_data[ $profile_field ];
				}
			}
		}

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

	private static function persist_result( $user_id, $slug, $hash, array $result, $module = array() ) {
		if ( ! class_exists( 'HAP_Profile_Results_Store' ) ) {
			return true;
		}

		$written = HAP_Profile_Results_Store::put( $user_id, $slug, $hash, $result );
		if ( $written ) {
			return true;
		}

		$note = sprintf( 'Result store write failed for module %s and user %d.', $slug, (int) $user_id );
		error_log( 'HAP_Profile_Module_Runner: ' . $note );

		if ( ! empty( $module['runner_notes'] ) ) {
			$note .= ' ' . $module['runner_notes'];
		}

		return $note;
	}

	// -------------------------------------------------------
	// Ana çalıştırma metodları
	// -------------------------------------------------------

	/**
	 * Tek bir modülü kullanıcı profil verisiyle çalıştırır.
	 *
	 * @param array      $module  DB'den gelen modül satırı.
	 * @param int        $user_id WP kullanıcı ID.
	 * @param array|null $profile Kullanıcı profil verisi.
	 * @return array
	 */
	public static function run_module_for_user( $module, $user_id, $profile = null ) {
		// 1. Finans/alakasız modül filtresi
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

		// 2. result_enabled güvenlik kontrolü
		if ( isset( $module['result_enabled'] ) && ! (int) $module['result_enabled'] ) {
			return array(
				'module'   => $module,
				'state'    => 'filtered_by_profile_policy',
				'message'  => self::get_user_message_for_state( 'filtered_by_profile_policy' ),
				'missing'  => array(),
				'result'   => null,
				'tool_url' => null,
			);
		}

		// 3. Profil verisi yükle
		if ( null === $profile ) {
			$fields_obj = new HAP_Profile_Fields();
			$ud         = new HAP_Profile_User_Data( $fields_obj );
			$profile    = $ud->get_user_profile_data( $user_id );
		}

		// 4. Eksik zorunlu alan kontrolü
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

		// 5. Payload ve hash
		$slug    = $module['slug'];
		$payload = self::build_payload_for_module( $module, $profile );
		$hash    = md5( serialize( $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		// 6. Efektif runner_type belirle
		$runner_type   = trim( (string) ( $module['runner_type'] ?? '' ) );
		$runner_status = trim( (string) ( $module['runner_status'] ?? '' ) );
		$suite_backend = (int) ( $module['suite_backend_supported'] ?? 0 );

		// Boş/none runner_type ama suite_backend=1 ve status=ok → calculate_api olarak dene
		if ( ( '' === $runner_type || 'none' === $runner_type ) && $suite_backend && 'ok' === $runner_status ) {
			$runner_type = 'calculate_api';
		}

		// 7. Saf frontend/bekleyen tipler: filter denemeden hızlı çık
		if ( in_array( $runner_type, array( 'js_frontend_only', 'frontend_js_only', 'pending_adapter' ), true ) ) {
			return array(
				'module'   => $module,
				'state'    => 'frontend_only',
				'message'  => self::get_user_message_for_state( 'frontend_only' ),
				'missing'  => array(),
				'result'   => null,
				'tool_url' => self::get_tool_url( $module ),
			);
		}

		// 8. Cache kontrolü (sadece backend destekli tipler için)
		if ( class_exists( 'HAP_Profile_Results_Store' ) ) {
			$cached = HAP_Profile_Results_Store::get( $user_id, $slug, $hash );
			if ( null !== $cached && ( ! empty( $cached['success'] ) || 'ready_result' === ( $cached['status'] ?? '' ) ) ) {
				return array(
					'module'   => $module,
					'state'    => 'ready_result',
					'message'  => self::get_user_message_for_state( 'ready_result' ),
					'missing'  => array(),
					'result'   => $cached,
					'tool_url' => self::get_tool_url( $module ),
					'cached'   => true,
				);
			}
		}

		// 9. calculate_api → Suite filter (has_filter kontrolü yok: filter kayıtlı değilse null döner)
		if ( 'calculate_api' === $runner_type ) {
			try {
				$api_result = apply_filters( 'hc_calculate_module', null, $slug, $payload );

				if ( is_array( $api_result ) && ! empty( $api_result['success'] ) ) {
					if ( empty( $api_result['status'] ) ) {
						$api_result['status'] = 'ready_result';
					}
					$store_note = self::persist_result( $user_id, $slug, $hash, $api_result, $module );
					if ( is_string( $store_note ) ) {
						$api_result['runner_note'] = $store_note;
					}
					return array(
						'module'   => $module,
						'state'    => 'ready_result',
						'message'  => self::get_user_message_for_state( 'ready_result' ),
						'missing'  => array(),
						'result'   => $api_result,
						'tool_url' => self::get_tool_url( $module ),
						'cached'   => false,
					);
				}

				if ( is_array( $api_result ) && ! empty( $api_result['error'] ) ) {
					return array(
						'module'        => $module,
						'state'         => 'calculation_error',
						'message'       => self::get_user_message_for_state( 'runner_error' ),
						'missing'       => array(),
						'result'        => null,
						'error_message' => (string) $api_result['error'],
						'tool_url'      => self::get_tool_url( $module ),
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

			// Filter null döndürdü veya success değil → frontend_only
			return array(
				'module'   => $module,
				'state'    => 'frontend_only',
				'message'  => self::get_user_message_for_state( 'frontend_only' ),
				'missing'  => array(),
				'result'   => null,
				'tool_url' => self::get_tool_url( $module ),
			);
		}

		// 10. php_callback → doğrudan callable çağır
		if ( 'php_callback' === $runner_type && ! empty( $module['runner_callback'] ) ) {
			$cb = $module['runner_callback'];
			if ( is_callable( $cb ) ) {
				try {
					$raw = call_user_func( $cb, $payload );
					if ( ! empty( $raw ) ) {
						$normalized                      = self::normalize_result( $module, $raw );
						$stored_value                    = $normalized;
						$stored_value['status']          = 'ready_result';
						$stored_value['raw_result']      = $raw;
						$stored_value['normalized_result'] = $normalized;
						$store_note                      = self::persist_result( $user_id, $slug, $hash, $stored_value, $module );
						if ( is_string( $store_note ) ) {
							$normalized['runner_note'] = $store_note;
						}
						return array(
							'module'   => $module,
							'state'    => 'ready_result',
							'message'  => self::get_user_message_for_state( 'ready_result' ),
							'missing'  => array(),
							'result'   => $normalized,
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
			// Callback callable değil → Suite filter fallback
		}

		// 11. Fallback: Suite filter (php_callback/shortcode_render/bilinmeyen tipler için)
		if ( has_filter( 'hc_calculate_module' ) ) {
			try {
				$api_result = apply_filters( 'hc_calculate_module', null, $slug, $payload );
				if ( is_array( $api_result ) && ! empty( $api_result['success'] ) ) {
					if ( empty( $api_result['status'] ) ) {
						$api_result['status'] = 'ready_result';
					}
					$store_note = self::persist_result( $user_id, $slug, $hash, $api_result, $module );
					if ( is_string( $store_note ) ) {
						$api_result['runner_note'] = $store_note;
					}
					return array(
						'module'   => $module,
						'state'    => 'ready_result',
						'message'  => self::get_user_message_for_state( 'ready_result' ),
						'missing'  => array(),
						'result'   => $api_result,
						'tool_url' => self::get_tool_url( $module ),
						'cached'   => false,
					);
				}
				if ( is_array( $api_result ) && isset( $api_result['error_code'] ) && 'unsupported_backend_calculation' === $api_result['error_code'] ) {
					// Desteklenmiyor → frontend_only'e düş
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

		// 12. Son çare: frontend_only
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
	 * @param int        $user_id
	 * @param array|null $modules
	 * @param array|null $profile
	 * @return array
	 */
	public static function run_modules_for_user( $user_id, $modules = null, $profile = null ) {
		if ( null === $modules ) {
			$modules_obj = new HAP_Profile_Modules();
			$modules     = $modules_obj->get_modules(
				array(
					'availability_status' => 'active',
					'result_enabled'      => 1,
					'limit'               => 500,
				)
			);
			$modules     = array_values(
				array_filter(
					$modules,
					function ( $module ) {
						return in_array( $module['profile_status'], array( 'profile_core', 'profile_optional' ), true );
					}
				)
			);
		}

		if ( null === $profile ) {
			$fields_obj = new HAP_Profile_Fields();
			$ud         = new HAP_Profile_User_Data( $fields_obj );
			$profile    = $ud->get_user_profile_data( $user_id );
		}

		$results = array();
		foreach ( $modules as $module ) {
			if ( empty( $module['result_enabled'] ) ) {
				continue;
			}
			$results[ $module['slug'] ] = self::run_module_for_user( $module, $user_id, $profile );
		}
		return $results;
	}

	// -------------------------------------------------------
	// MVP Mapping Presetleri
	// -------------------------------------------------------

	public static function get_preset_mappings() {
		return array(
			'burc-dogum-araligi-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'birth_date' ),
				'input_mapping'   => json_encode( array( 'birth_date' => 'dogum_tarihi' ) ),
				'runner_notes'    => 'Burç doğum aralığı; JS hesaplıyor. birth_date yeterli.',
			),
			'ay-burcu-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'birth_date', 'birth_time', 'birth_place' ),
				'input_mapping'   => json_encode(
					array(
						'birth_date'  => 'dogum_tarihi',
						'birth_time'  => 'dogum_saati',
						'birth_place' => 'dogum_yeri',
					)
				),
				'runner_notes'    => 'Ay burcu hesaplama; JS. Doğum saati ve yeri zorunlu.',
			),
			'burc-ve-ev-yerlesimi-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'birth_date', 'birth_time', 'birth_place' ),
				'input_mapping'   => json_encode(
					array(
						'birth_date'  => 'dogum_tarihi',
						'birth_time'  => 'dogum_saati',
						'birth_place' => 'dogum_yeri',
					)
				),
				'runner_notes'    => 'Ev yerleşimi; JS. Doğum saati ve yeri zorunlu.',
			),
			'ay-fazi-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'birth_date' ),
				'input_mapping'   => json_encode( array( 'birth_date' => 'dogum_tarihi' ) ),
				'runner_notes'    => 'Ay fazı; JS. birth_date yeterli.',
			),
			'adimdan-kaloriye-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'weight', 'daily_steps' ),
				'input_mapping'   => json_encode(
					array(
						'weight'      => 'kilo',
						'daily_steps' => 'adim',
					)
				),
				'runner_notes'    => 'Adımdan kaloriye; JS. Kilo ve günlük adım gerekli.',
			),
			'gunluk-adim-hedefi-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'weight', 'activity_level' ),
				'input_mapping'   => json_encode(
					array(
						'weight'         => 'kilo',
						'activity_level' => 'aktivite',
					)
				),
				'runner_notes'    => 'Günlük adım hedefi; JS. Kilo ve aktivite gerekli.',
			),
			'bel-kalca-orani-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'height', 'weight', 'gender' ),
				'input_mapping'   => json_encode(
					array(
						'height' => 'boy',
						'weight' => 'kilo',
						'gender' => 'cinsiyet',
					)
				),
				'runner_notes'    => 'Bel-kalça oranı; JS. Boy, kilo, cinsiyet gerekli.',
			),
			'vucut-kitle-indeksi-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'height', 'weight' ),
				'input_mapping'   => json_encode(
					array(
						'height' => 'boy',
						'weight' => 'kilo',
					)
				),
				'runner_notes'    => 'VKİ hesaplama; JS. Boy ve kilo yeterli.',
			),
			'90-dakikalik-uyku-dongusu-hesaplama' => array(
				'runner_type'     => 'js_frontend_only',
				'required_fields' => array( 'sleep_hours' ),
				'input_mapping'   => json_encode( array( 'sleep_hours' => 'uyku_saati' ) ),
				'runner_notes'    => '90 dk uyku döngüsü; JS. sleep_hours gerekli.',
			),
		);
	}
}
