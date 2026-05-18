<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp_hc_module_fields tablosunu güvenli şekilde okur.
 * Hesaplama Suite dosyalarına dokunmaz; sadece DB okur.
 */
class HAP_Suite_Module_Fields {

	const SUITE_TABLE = 'hc_module_fields';

	/** @var bool|null */
	private static $table_exists_cache = null;

	// -------------------------------------------------------
	// Tablo kontrol
	// -------------------------------------------------------

	public static function table_exists() {
		if ( null !== self::$table_exists_cache ) {
			return self::$table_exists_cache;
		}
		global $wpdb;
		$table                    = $wpdb->prefix . self::SUITE_TABLE;
		self::$table_exists_cache = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
		return self::$table_exists_cache;
	}

	/** Admin'de uyarı göster; frontend'de sessiz kal. */
	public static function maybe_warn_admin() {
		if ( ! self::table_exists() && is_admin() ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning"><p><strong>Hesaplamaa Profile:</strong> Hesaplama Suite alan tablosu (<code>wp_hc_module_fields</code>) bulunamadı. Suite tablosu gerektiren özellikler devre dışı.</p></div>';
				}
			);
		}
	}

	// -------------------------------------------------------
	// Temel okuma metodları
	// -------------------------------------------------------

	/**
	 * Aktif profil alanlarını döner.
	 *
	 * @param array $args {
	 *     @type string[] $statuses   Varsayılan: ['profile_core','profile_optional']
	 *     @type int      $limit
	 *     @type int      $offset
	 * }
	 * @return array
	 */
	public static function get_available_profile_fields( $args = array() ) {
		if ( ! self::table_exists() ) {
			return array();
		}
		global $wpdb;
		$table    = $wpdb->prefix . self::SUITE_TABLE;
		$statuses = $args['statuses'] ?? array( 'profile_core', 'profile_optional' );
		$limit    = absint( $args['limit'] ?? 200 );
		$offset   = absint( $args['offset'] ?? 0 );

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT DISTINCT profile_field, field_label, field_type, field_group,
			        COUNT(DISTINCT module_slug) AS module_count,
			        SUM(CASE WHEN backend_supported = 1 THEN 1 ELSE 0 END) AS backend_count
			 FROM `{$table}`
			 WHERE suggested_profile_status IN ({$placeholders})
			   AND profile_field != ''
			 GROUP BY profile_field, field_label, field_type, field_group
			 ORDER BY field_group, profile_field
			 LIMIT %d OFFSET %d",
			array_merge( $statuses, array( $limit, $offset ) )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? array_map( array( __CLASS__, 'normalize_suite_row' ), $rows ) : array();
	}

	/**
	 * Belirli bir profil alanıyla çalışan modülleri döner.
	 *
	 * @param string $profile_field
	 * @param array  $args {
	 *     @type bool $include_tool_only  Varsayılan false.
	 *     @type bool $include_disabled   Varsayılan false.
	 *     @type int  $limit
	 *     @type int  $offset
	 * }
	 * @return array
	 */
	public static function get_modules_for_field( $profile_field, $args = array() ) {
		if ( ! self::table_exists() || '' === $profile_field ) {
			return array();
		}
		global $wpdb;
		$table    = $wpdb->prefix . self::SUITE_TABLE;
		$limit    = absint( $args['limit'] ?? 100 );
		$offset   = absint( $args['offset'] ?? 0 );

		$statuses = array( 'profile_core', 'profile_optional' );
		if ( ! empty( $args['include_tool_only'] ) ) {
			$statuses[] = 'tool_only';
		}
		if ( ! empty( $args['include_disabled'] ) ) {
			$statuses[] = 'disabled';
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT DISTINCT module_slug, module_title, section, suggested_profile_status,
			        backend_supported, ai_useful, is_sensitive, is_custom_field
			 FROM `{$table}`
			 WHERE profile_field = %s
			   AND suggested_profile_status IN ({$placeholders})
			 ORDER BY
			   FIELD(suggested_profile_status, 'profile_core', 'profile_optional', 'tool_only', 'disabled'),
			   backend_supported DESC,
			   module_title ASC
			 LIMIT %d OFFSET %d",
			array_merge( array( $profile_field ), $statuses, array( $limit, $offset ) )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Belirli bir modülün tüm alan satırlarını döner.
	 *
	 * @param string $module_slug
	 * @return array
	 */
	public static function get_fields_for_module( $module_slug ) {
		if ( ! self::table_exists() || '' === $module_slug ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . self::SUITE_TABLE;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE module_slug = %s ORDER BY profile_field",
				$module_slug
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Modül manifest özeti döner.
	 * backend_supported, ai_useful, is_sensitive için MAX() aggregate kullanır.
	 *
	 * @param string $module_slug
	 * @return array|null
	 */
	public static function get_module_manifest( $module_slug ) {
		if ( ! self::table_exists() || '' === $module_slug ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . self::SUITE_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT module_slug,
				        MAX(module_title) AS module_title,
				        MAX(section) AS section,
				        MAX(suggested_profile_status) AS suggested_profile_status,
				        MAX(backend_supported) AS backend_supported,
				        MAX(ai_useful) AS ai_useful,
				        MAX(is_sensitive) AS is_sensitive
				 FROM `{$table}`
				 WHERE module_slug = %s
				 GROUP BY module_slug",
				$module_slug
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$fields = self::get_fields_for_module( $module_slug );
		return array(
			'module_slug'              => $row['module_slug'],
			'module_title'             => $row['module_title'],
			'section'                  => $row['section'],
			'suggested_profile_status' => $row['suggested_profile_status'],
			'backend_supported'        => (bool) $row['backend_supported'],
			'ai_useful'                => (bool) $row['ai_useful'],
			'is_sensitive'             => (bool) $row['is_sensitive'],
			'required_fields'          => self::build_required_fields_for_module( $module_slug, $fields ),
			'input_mapping'            => self::build_input_mapping_for_module( $module_slug, $fields ),
			'source'                   => 'suite',
		);
	}

	/**
	 * Admin tarafından önerilen modülleri döner.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_recommended_modules( $args = array() ) {
		if ( ! self::table_exists() ) {
			return array();
		}
		global $wpdb;
		$table    = $wpdb->prefix . self::SUITE_TABLE;
		$statuses = $args['statuses'] ?? array( 'profile_core', 'profile_optional' );
		$limit    = absint( $args['limit'] ?? 200 );

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$sql          = $wpdb->prepare(
			"SELECT DISTINCT module_slug, module_title, section, suggested_profile_status, backend_supported, ai_useful
			 FROM `{$table}`
			 WHERE suggested_profile_status IN ({$placeholders})
			 ORDER BY suggested_profile_status DESC, module_title
			 LIMIT %d",
			array_merge( $statuses, array( $limit ) )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Her profil alanı için kaç modül + backend destekli sayısı döner.
	 * Yalnızca profile_core/profile_optional satırlarını sayar; label için öncelik zinciri uygular.
	 *
	 * @return array  [ profile_field => ['module_count'=>int,'backend_count'=>int,'field_label'=>str] ]
	 */
	public static function get_field_impact_summary() {
		if ( ! self::table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . self::SUITE_TABLE;

		// GROUP BY yalnızca profile_field; label için core/optional satırlarından MAX al.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT profile_field,
			        MAX(CASE WHEN suggested_profile_status IN ('profile_core','profile_optional') THEN field_label ELSE NULL END) AS best_label,
			        COUNT(DISTINCT module_slug) AS module_count,
			        SUM(CASE WHEN backend_supported = 1 THEN 1 ELSE 0 END) AS backend_count
			 FROM `{$table}`
			 WHERE profile_field != ''
			   AND suggested_profile_status IN ('profile_core','profile_optional')
			 GROUP BY profile_field
			 ORDER BY module_count DESC
			 LIMIT 200",
			ARRAY_A
		);

		// Varsayılan Türkçe etiket sözlüğü (B önceliği).
		$default_labels = array(
			'gender'          => 'Cinsiyet',
			'age'             => 'Yaş',
			'weight'          => 'Kilo (kg)',
			'height'          => 'Boy (cm)',
			'birthdate'       => 'Doğum Tarihi',
			'birth_date'      => 'Doğum Tarihi',
			'city'            => 'Şehir',
			'activity_level'  => 'Aktivite Düzeyi',
			'goal'            => 'Hedef',
			'body_fat'        => 'Vücut Yağ Oranı (%)',
			'waist'           => 'Bel Çevresi (cm)',
			'hip'             => 'Kalça Çevresi (cm)',
			'neck'            => 'Boyun Çevresi (cm)',
			'wrist'           => 'Bilek Çevresi (cm)',
			'blood_type'      => 'Kan Grubu',
			'diet_type'       => 'Beslenme Tipi',
			'smoking'         => 'Sigara Kullanımı',
			'alcohol'         => 'Alkol Kullanımı',
			'sleep_hours'     => 'Uyku Süresi',
			'water_intake'    => 'Su Tüketimi (L)',
		);

		$result = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$field_key = sanitize_key( $row['profile_field'] );

				// A) HAP_Profile_Fields konfigürasyon etiketi.
				$label = '';
				if ( class_exists( 'HAP_Profile_Fields' ) ) {
					$cfg = HAP_Profile_Fields::get_field_config( $field_key );
					if ( $cfg && ! empty( $cfg['label'] ) ) {
						$label = $cfg['label'];
					}
				}
				// B) Varsayılan sözlük.
				if ( '' === $label && isset( $default_labels[ $field_key ] ) ) {
					$label = $default_labels[ $field_key ];
				}
				// C) Suite tablosundan gelen en iyi core/optional etiketi.
				if ( '' === $label && ! empty( $row['best_label'] ) ) {
					$label = $row['best_label'];
				}
				// D) Alan anahtarından üretilen etiket.
				if ( '' === $label ) {
					$label = ucwords( str_replace( '_', ' ', $field_key ) );
				}

				$result[ $field_key ] = array(
					'field_label'   => sanitize_text_field( $label ),
					'module_count'  => (int) $row['module_count'],
					'backend_count' => (int) $row['backend_count'],
				);
			}
		}
		return $result;
	}

	/**
	 * Backend destekli modülleri döner.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_backend_supported_modules( $args = array() ) {
		if ( ! self::table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . self::SUITE_TABLE;
		$limit = absint( $args['limit'] ?? 200 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT module_slug, module_title, section, suggested_profile_status
				 FROM `{$table}`
				 WHERE backend_supported = 1
				 ORDER BY module_title
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Profile dashboard'a dahil edilebilecek modülleri döner.
	 *
	 * @param array $args
	 * @return array
	 */
	public static function get_profile_candidate_modules( $args = array() ) {
		return self::get_recommended_modules( $args );
	}

	// -------------------------------------------------------
	// Normalizer + Builder
	// -------------------------------------------------------

	/**
	 * Suite satırını normalize eder. WP_Post/WP_Term gibi nesneler döndürmez.
	 *
	 * @param array $row
	 * @return array
	 */
	public static function normalize_suite_row( $row ) {
		return array(
			'profile_field'            => sanitize_key( $row['profile_field'] ?? '' ),
			'field_label'              => sanitize_text_field( $row['field_label'] ?? '' ),
			'field_type'               => sanitize_key( $row['field_type'] ?? 'text' ),
			'field_group'              => sanitize_text_field( $row['field_group'] ?? '' ),
			'module_count'             => (int) ( $row['module_count'] ?? 0 ),
			'backend_count'            => (int) ( $row['backend_count'] ?? 0 ),
			'suggested_profile_status' => sanitize_key( $row['suggested_profile_status'] ?? '' ),
		);
	}

	/**
	 * Modüle ait required_fields listesini Suite tablosundan oluşturur.
	 * hc_ önekli teknik anahtarları ve HAP_Profile_Fields'te bilinmeyen alanları dışarıda bırakır.
	 *
	 * @param string     $module_slug
	 * @param array|null $fields  Önceden çekilmişse verilir.
	 * @return array
	 */
	public static function build_required_fields_for_module( $module_slug, $fields = null ) {
		if ( null === $fields ) {
			$fields = self::get_fields_for_module( $module_slug );
		}

		$known_fields = array();
		if ( class_exists( 'HAP_Profile_Fields' ) ) {
			foreach ( HAP_Profile_Fields::get_fields() as $f ) {
				$known_fields[] = $f['field_key'];
			}
		}

		$required = array();
		foreach ( $fields as $row ) {
			$pf = sanitize_key( $row['profile_field'] ?? '' );
			if ( '' === $pf ) {
				continue;
			}
			// hc_ önekli teknik alan anahtarlarını dışla
			if ( 0 === strpos( $pf, 'hc_' ) ) {
				continue;
			}
			// Yalnızca HAP_Profile_Fields'te tanımlı alanları dahil et
			if ( ! empty( $known_fields ) && ! in_array( $pf, $known_fields, true ) ) {
				continue;
			}
			$required[] = $pf;
		}
		return array_values( array_unique( $required ) );
	}

	/**
	 * Modüle ait input_mapping'i Suite tablosundan oluşturur.
	 * module_input_name → profile_field şeklinde döner.
	 *
	 * @param string     $module_slug
	 * @param array|null $fields
	 * @return array  ['profile_field' => 'module_input_name']
	 */
	public static function build_input_mapping_for_module( $module_slug, $fields = null ) {
		if ( null === $fields ) {
			$fields = self::get_fields_for_module( $module_slug );
		}
		$mapping = array();
		foreach ( $fields as $row ) {
			$pf = sanitize_key( $row['profile_field'] ?? '' );
			$mn = sanitize_text_field( $row['module_input_name'] ?? '' );
			if ( '' !== $pf && '' !== $mn ) {
				$mapping[ $pf ] = $mn;
			}
		}
		return $mapping;
	}
}
