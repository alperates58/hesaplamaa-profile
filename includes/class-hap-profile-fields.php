<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Fields {

	const FIELDS_OPTION = 'hap_profile_fields';
	const STEPS_OPTION  = 'hap_profile_onboarding_steps';

	private static $default_fields = array(
		array(
			'field_key'                     => 'nickname',
			'label'                         => 'Takma Ad',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Profilinizde görünecek takma adınız.',
			'step_key'                      => 'basic_profile',
			'sort_order'                    => 10,
			'required_for_minimum_profile'  => 1,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_nickname',
		),
		array(
			'field_key'                     => 'first_name',
			'label'                         => 'Ad',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'numerology',
			'sort_order'                    => 20,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_first_name',
		),
		array(
			'field_key'                     => 'last_name',
			'label'                         => 'Soyad',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'numerology',
			'sort_order'                    => 30,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_last_name',
		),
		array(
			'field_key'                     => 'birth_date',
			'label'                         => 'Doğum Tarihi',
			'type'                          => 'date',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Astroloji ve numeroloji hesaplamaları için gereklidir.',
			'step_key'                      => 'basic_profile',
			'sort_order'                    => 40,
			'required_for_minimum_profile'  => 1,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'date',
			'user_meta_key'                 => '_hap_profile_birth_date',
		),
		array(
			'field_key'                     => 'birth_time',
			'label'                         => 'Doğum Saati',
			'type'                          => 'time',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Yükselen burç ve ev hesaplamaları için gereklidir.',
			'step_key'                      => 'astrology_details',
			'sort_order'                    => 50,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'time',
			'user_meta_key'                 => '_hap_profile_birth_time',
		),
		array(
			'field_key'                     => 'birth_place',
			'label'                         => 'Doğum Yeri',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Doğum yeri/şehri. Ev ve gezegen hesaplamaları için gereklidir.',
			'step_key'                      => 'astrology_details',
			'sort_order'                    => 60,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_birth_place',
		),
		array(
			'field_key'                     => 'gender',
			'label'                         => 'Cinsiyet',
			'type'                          => 'select',
			'options'                       => array(
				'male'       => 'Erkek',
				'female'     => 'Kadın',
				'other'      => 'Diğer',
				'prefer_not' => 'Belirtmek istemiyorum',
			),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'basic_profile',
			'sort_order'                    => 70,
			'required_for_minimum_profile'  => 1,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'gender',
			'user_meta_key'                 => '_hap_profile_gender',
		),
		array(
			'field_key'                     => 'height',
			'label'                         => 'Boy (cm)',
			'type'                          => 'number',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Sağlık ve vücut analizi için gereklidir.',
			'step_key'                      => 'health_lifestyle',
			'sort_order'                    => 80,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'height',
			'user_meta_key'                 => '_hap_profile_height',
		),
		array(
			'field_key'                     => 'weight',
			'label'                         => 'Kilo (kg)',
			'type'                          => 'number',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Sağlık ve spor analizi için gereklidir.',
			'step_key'                      => 'health_lifestyle',
			'sort_order'                    => 90,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'weight',
			'user_meta_key'                 => '_hap_profile_weight',
		),
		array(
			'field_key'                     => 'city',
			'label'                         => 'Yaşadığı Şehir',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'basic_profile',
			'sort_order'                    => 100,
			'required_for_minimum_profile'  => 1,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_city',
		),
		array(
			'field_key'                     => 'activity_level',
			'label'                         => 'Aktivite Seviyesi',
			'type'                          => 'select',
			'options'                       => array(
				'sedentary'   => 'Hareketsiz',
				'light'       => 'Hafif aktif',
				'moderate'    => 'Orta aktif',
				'active'      => 'Aktif',
				'very_active' => 'Çok aktif',
			),
			'placeholder'                   => '',
			'help_text'                     => 'Günlük fiziksel aktivite düzeyi.',
			'step_key'                      => 'health_lifestyle',
			'sort_order'                    => 110,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'activity_level',
			'user_meta_key'                 => '_hap_profile_activity_level',
		),
		array(
			'field_key'                     => 'sleep_hours',
			'label'                         => 'Günlük Uyku Süresi (saat)',
			'type'                          => 'number',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'health_lifestyle',
			'sort_order'                    => 120,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'sleep_hours',
			'user_meta_key'                 => '_hap_profile_sleep_hours',
		),
		array(
			'field_key'                     => 'daily_steps',
			'label'                         => 'Günlük Adım Sayısı',
			'type'                          => 'number',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'health_lifestyle',
			'sort_order'                    => 130,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'daily_steps',
			'user_meta_key'                 => '_hap_profile_daily_steps',
		),
		array(
			'field_key'                     => 'relationship_status',
			'label'                         => 'İlişki Durumu',
			'type'                          => 'select',
			'options'                       => array(
				'single'       => 'Bekar',
				'relationship' => 'İlişkide',
				'married'      => 'Evli',
				'complicated'  => 'Karmaşık',
				'prefer_not'   => 'Belirtmek istemiyorum',
			),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 140,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'relationship_status',
			'user_meta_key'                 => '_hap_profile_relationship_status',
		),
		array(
			'field_key'                     => 'partner_birth_date',
			'label'                         => 'Partner Doğum Tarihi',
			'type'                          => 'date',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Aşk uyumu hesaplamaları için gereklidir.',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 150,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'date',
			'user_meta_key'                 => '_hap_profile_partner_birth_date',
		),
		array(
			'field_key'                     => 'home_number',
			'label'                         => 'Ev Numarası',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Ev numarası numerolojisi için gereklidir.',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 160,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_home_number',
		),
		array(
			'field_key'                     => 'phone_number',
			'label'                         => 'Telefon Numarası',
			'type'                          => 'tel',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Telefon numerolojisi için gereklidir.',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 170,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'phone',
			'user_meta_key'                 => '_hap_profile_phone_number',
		),
		array(
			'field_key'                     => 'plate_number',
			'label'                         => 'Plaka Numarası',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Plaka numerolojisi için gereklidir.',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 180,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 1,
			'public_visible_default'        => 0,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_plate_number',
		),
		array(
			'field_key'                     => 'company_name',
			'label'                         => 'Şirket Adı',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Şirket ismi numerolojisi için gereklidir.',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 190,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_company_name',
		),
		array(
			'field_key'                     => 'baby_name',
			'label'                         => 'Bebek Adı',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => 'Bebek ismi numerolojisi için gereklidir.',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 200,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_baby_name',
		),
		array(
			'field_key'                     => 'career_goal',
			'label'                         => 'Kariyer Hedefi',
			'type'                          => 'text',
			'options'                       => array(),
			'placeholder'                   => '',
			'help_text'                     => '',
			'step_key'                      => 'optional_details',
			'sort_order'                    => 210,
			'required_for_minimum_profile'  => 0,
			'sensitive'                     => 0,
			'public_visible_default'        => 1,
			'active'                        => 1,
			'validation_rule'               => 'text',
			'user_meta_key'                 => '_hap_profile_career_goal',
		),
	);

	private static $default_steps = array(
		array(
			'step_key'         => 'basic_profile',
			'title'            => 'Kişisel profilini oluşturalım',
			'description'      => 'Birkaç temel bilgiyle sana özel analiz panelini hazırlayacağız.',
			'icon'             => '👤',
			'sort_order'       => 10,
			'is_required'      => 1,
			'active'           => 1,
			'completion_rule'  => 'all_fields',
		),
		array(
			'step_key'         => 'health_lifestyle',
			'title'            => 'Sağlık ve günlük yaşam analizlerini aç',
			'description'      => 'Boy, kilo ve aktivite bilgilerini ekleyerek sağlık kartlarını açabilirsin.',
			'icon'             => '🍎',
			'sort_order'       => 20,
			'is_required'      => 0,
			'active'           => 1,
			'completion_rule'  => 'any_field',
		),
		array(
			'step_key'         => 'astrology_details',
			'title'            => 'Doğum haritanı detaylandıralım',
			'description'      => 'Doğum saati ve doğum yeri bilgisi astrolojik detayları açar.',
			'icon'             => '♈',
			'sort_order'       => 30,
			'is_required'      => 0,
			'active'           => 1,
			'completion_rule'  => 'all_fields',
		),
		array(
			'step_key'         => 'numerology',
			'title'            => 'Numeroloji profilini oluştur',
			'description'      => 'İsim temelli numeroloji kartları için ad ve soyad bilgilerini ekle.',
			'icon'             => '🔢',
			'sort_order'       => 40,
			'is_required'      => 0,
			'active'           => 1,
			'completion_rule'  => 'all_fields',
		),
		array(
			'step_key'         => 'optional_details',
			'title'            => 'Ek analizleri aç',
			'description'      => 'İleri seviye ve isteğe bağlı analiz kartlarını açan ek alanlar.',
			'icon'             => '✨',
			'sort_order'       => 50,
			'is_required'      => 0,
			'active'           => 1,
			'completion_rule'  => 'any_field',
		),
	);

	public function get_default_fields() {
		return self::get_default_fields_config();
	}

	public static function get_default_fields_config() {
		return self::$default_fields;
	}

	public static function get_default_steps_config() {
		return self::$default_steps;
	}

	public static function get_fields() {
		$saved = get_option( self::FIELDS_OPTION, null );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			$saved = self::get_default_fields_config();
		}

		$normalized = array();
		foreach ( $saved as $field ) {
			$normalized[] = self::normalize_field_config( $field );
		}

		usort(
			$normalized,
			function ( $a, $b ) {
				if ( (int) $a['sort_order'] === (int) $b['sort_order'] ) {
					return strcmp( $a['label'], $b['label'] );
				}
				return (int) $a['sort_order'] <=> (int) $b['sort_order'];
			}
		);

		return $normalized;
	}

	public static function save_fields( array $fields ) {
		$sanitized = array();
		foreach ( $fields as $field ) {
			$sanitized[] = self::normalize_field_config( $field );
		}
		update_option( self::FIELDS_OPTION, $sanitized, false );
		return true;
	}

	public static function get_steps() {
		$saved = get_option( self::STEPS_OPTION, null );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			$saved = self::get_default_steps_config();
		}

		$normalized = array();
		foreach ( $saved as $step ) {
			$normalized[] = self::normalize_step_config( $step );
		}

		usort(
			$normalized,
			function ( $a, $b ) {
				return (int) $a['sort_order'] <=> (int) $b['sort_order'];
			}
		);

		return $normalized;
	}

	public static function save_steps( array $steps ) {
		$sanitized = array();
		foreach ( $steps as $step ) {
			$sanitized[] = self::normalize_step_config( $step );
		}
		update_option( self::STEPS_OPTION, $sanitized, false );
		return true;
	}

	public static function get_active_steps() {
		return array_values(
			array_filter(
				self::get_steps(),
				function ( $step ) {
					return ! empty( $step['active'] );
				}
			)
		);
	}

	public static function get_step_config( $step_key ) {
		$step_key = sanitize_key( $step_key );
		foreach ( self::get_steps() as $step ) {
			if ( $step['step_key'] === $step_key ) {
				return $step;
			}
		}
		return null;
	}

	public static function get_active_fields() {
		return array_values(
			array_filter(
				self::get_fields(),
				function ( $field ) {
					return ! empty( $field['active'] );
				}
			)
		);
	}

	public static function get_fields_by_step( $step_key ) {
		$step_key = sanitize_key( $step_key );
		return array_values(
			array_filter(
				self::get_active_fields(),
				function ( $field ) use ( $step_key ) {
					return $field['step_key'] === $step_key;
				}
			)
		);
	}

	public static function get_field_config( $field_key ) {
		$field_key = sanitize_key( $field_key );
		foreach ( self::get_fields() as $field ) {
			if ( $field['field_key'] === $field_key ) {
				return $field;
			}
		}
		return null;
	}

	public function get_field_by_key( $key ) {
		return self::get_field_config( $key );
	}

	public static function get_field_options( $field_key ) {
		$field = self::get_field_config( $field_key );
		return is_array( $field['options'] ?? null ) ? $field['options'] : array();
	}

	public static function validate_field( $field_key, $value ) {
		$field = self::get_field_config( $field_key );
		if ( ! $field ) {
			return true;
		}
		return '' !== self::sanitize_field( $field_key, $value ) || '' === trim( (string) $value );
	}

	public static function sanitize_field( $field_key, $value ) {
		$field = self::get_field_config( $field_key );
		if ( ! $field ) {
			return '';
		}

		$value = is_array( $value ) ? '' : trim( (string) $value );
		$rule  = $field['validation_rule'] ?: $field['type'];

		switch ( $rule ) {
			case 'height':
				if ( '' === $value || ! is_numeric( $value ) ) {
					return '';
				}
				$num = (float) $value;
				return ( $num >= 50 && $num <= 250 ) ? $num : '';
			case 'weight':
				if ( '' === $value || ! is_numeric( $value ) ) {
					return '';
				}
				$num = (float) $value;
				return ( $num >= 20 && $num <= 300 ) ? $num : '';
			case 'sleep_hours':
				if ( '' === $value || ! is_numeric( $value ) ) {
					return '';
				}
				$num = (float) $value;
				return ( $num >= 0 && $num <= 24 ) ? $num : '';
			case 'daily_steps':
				if ( '' === $value || ! is_numeric( $value ) ) {
					return '';
				}
				$num = (int) round( (float) $value );
				return ( $num >= 0 && $num <= 100000 ) ? $num : '';
			case 'date':
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					return '';
				}
				$parts = explode( '-', $value );
				return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $value : '';
			case 'time':
				return preg_match( '/^\d{2}:\d{2}$/', $value ) ? $value : '';
			case 'phone':
			case 'tel':
				return preg_replace( '/[^0-9+\-\s()]/', '', $value );
			case 'gender':
			case 'activity_level':
			case 'relationship_status':
			case 'select':
				$options = self::get_field_options( $field_key );
				return array_key_exists( $value, $options ) ? $value : '';
			case 'number':
				return ( '' !== $value && is_numeric( $value ) ) ? (float) $value : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	public static function get_modules_for_field( $field_key ) {
		if ( ! class_exists( 'HAP_Profile_Modules' ) ) {
			return array();
		}

		$field_key = sanitize_key( $field_key );
		$modules   = new HAP_Profile_Modules();
		$items     = $modules->get_modules(
			array(
				'availability_status' => 'active',
				'result_enabled'      => 1,
				'limit'               => 500,
			)
		);

		$matches = array();
		foreach ( $items as $module ) {
			if ( ! in_array( $module['profile_status'], array( 'profile_core', 'profile_optional' ), true ) ) {
				continue;
			}

			$required = $modules->decode_fields_json( $module['required_fields'] ?? array() );
			$optional = $modules->decode_fields_json( $module['optional_fields'] ?? array() );
			if ( in_array( $field_key, $required, true ) || in_array( $field_key, $optional, true ) ) {
				$matches[] = $module;
			}
		}

		return $matches;
	}

	public static function get_minimum_required_fields() {
		$keys = array();
		foreach ( self::get_active_fields() as $field ) {
			if ( ! empty( $field['required_for_minimum_profile'] ) ) {
				$keys[] = $field['field_key'];
			}
		}
		return $keys;
	}

	public static function is_minimum_profile_complete( $user_id ) {
		if ( ! class_exists( 'HAP_Profile_User_Data' ) ) {
			return false;
		}
		$fields    = new self();
		$user_data = new HAP_Profile_User_Data( $fields );
		return $user_data->is_minimum_profile_complete( $user_id );
	}

	public static function get_sensitive_keys() {
		$keys = array();
		foreach ( self::get_active_fields() as $field ) {
			if ( ! empty( $field['sensitive'] ) ) {
				$keys[] = $field['field_key'];
			}
		}
		return $keys;
	}

	public function get_label( $key ) {
		$field = self::get_field_config( $key );
		return $field ? $field['label'] : $key;
	}

	private static function normalize_field_config( array $field ) {
		$field_key   = sanitize_key( $field['field_key'] ?? $field['key'] ?? '' );
		$options_raw = $field['options'] ?? array();

		if ( is_string( $options_raw ) ) {
			$decoded = json_decode( $options_raw, true );
			if ( is_array( $decoded ) ) {
				$options_raw = $decoded;
			} else {
				$options_raw = self::parse_options_text( $options_raw );
			}
		}

		$options = array();
		if ( is_array( $options_raw ) ) {
			foreach ( $options_raw as $opt_key => $opt_label ) {
				$options[ sanitize_key( (string) $opt_key ) ] = sanitize_text_field( (string) $opt_label );
			}
		}

		return array(
			'field_key'                    => $field_key,
			'label'                        => sanitize_text_field( $field['label'] ?? '' ),
			'type'                         => sanitize_key( $field['type'] ?? 'text' ),
			'options'                      => $options,
			'placeholder'                  => sanitize_text_field( $field['placeholder'] ?? '' ),
			'help_text'                    => sanitize_textarea_field( $field['help_text'] ?? $field['description'] ?? '' ),
			'step_key'                     => sanitize_key( $field['step_key'] ?? 'basic_profile' ),
			'sort_order'                   => absint( $field['sort_order'] ?? 0 ),
			'required_for_minimum_profile' => ! empty( $field['required_for_minimum_profile'] ) ? 1 : 0,
			'sensitive'                    => ! empty( $field['sensitive'] ) ? 1 : 0,
			'public_visible_default'       => ! empty( $field['public_visible_default'] ) ? 1 : 0,
			'active'                       => ! empty( $field['active'] ) ? 1 : 0,
			'validation_rule'              => sanitize_key( $field['validation_rule'] ?? ( $field['type'] ?? 'text' ) ),
			'user_meta_key'                => sanitize_key( str_replace( '-', '_', $field['user_meta_key'] ?? '_hap_profile_' . $field_key ) ),
		);
	}

	private static function normalize_step_config( array $step ) {
		return array(
			'step_key'        => sanitize_key( $step['step_key'] ?? $step['id'] ?? '' ),
			'title'           => sanitize_text_field( $step['title'] ?? '' ),
			'description'     => sanitize_textarea_field( $step['description'] ?? $step['subtitle'] ?? '' ),
			'icon'            => sanitize_text_field( $step['icon'] ?? '' ),
			'sort_order'      => absint( $step['sort_order'] ?? $step['number'] ?? 0 ),
			'is_required'     => ! empty( $step['is_required'] ) ? 1 : 0,
			'active'          => ! empty( $step['active'] ) ? 1 : 0,
			'completion_rule' => sanitize_key( $step['completion_rule'] ?? 'all_fields' ),
		);
	}

	private static function parse_options_text( $text ) {
		$options = array();
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $text );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( false !== strpos( $line, ':' ) ) {
				list( $key, $label ) = array_map( 'trim', explode( ':', $line, 2 ) );
			} else {
				$key   = $line;
				$label = $line;
			}
			$options[ sanitize_key( $key ) ] = sanitize_text_field( $label );
		}
		return $options;
	}
}
