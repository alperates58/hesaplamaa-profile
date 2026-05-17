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

	public static function get_minimum_required_fields() {
		$keys = array();
		foreach ( self::get_active_fields() as $field ) {
			if ( ! empty( $field['required_for_minimum_profile'] ) ) {
				$keys[] = $field['field_key'];
			}
		}
		return $keys;
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

	public static function normalize_display_title( $title ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return '';
		}

		// "Hesaplama" son ekini kaldır (ör. "Vücut Kitle İndeksi Hesaplama" → "Vücut Kitle İndeksi").
		$title = trim( preg_replace( '/\s+Hesaplama\s*$/ui', '', $title ) );

		$replacements = array(
			'Tarihine Gore' => 'Tarihine Göre',
			'Gunluk'        => 'Günlük',
			'Gun '          => 'Gün ',
			'Dogum'         => 'Doğum',
			'Donemi'        => 'Dönemi',
			'Yerlesimi'     => 'Yerleşimi',
			'Burçu'         => 'Burcu',
			'Cocuk'         => 'Çocuk',
			'Dugumleri'     => 'Düğümleri',
			'Yukselen'      => 'Yükselen',
			'Jupiter'       => 'Jüpiter',
			'Saturn'        => 'Satürn',
			'Ask'           => 'Aşk',
			'Ideal'         => 'İdeal',
			'Indeksi'       => 'İndeksi',
			'Orani'         => 'Oranı',
			'Kalca'         => 'Kalça',
			'Cakra'         => 'Çakra',
			'Sansli'        => 'Şanslı',
			'Tas'           => 'Taş',
			'Cin'           => 'Çin',
			'Esi'           => 'Eşi',
			'Ihtiyaci'      => 'İhtiyacı',
			'Ihtiyacı'      => 'İhtiyacı',
			'Burc'          => 'Burç',
		);

		$title = strtr( $title, $replacements );
		$title = preg_replace( '/\s+/', ' ', $title );

		return trim( $title );
	}

	public static function humanize_module_title( $slug_or_title ) {
		$text = trim( (string) $slug_or_title );
		if ( '' === $text ) {
			return '';
		}

		if ( false !== strpos( $text, '-' ) || false !== strpos( $text, '_' ) ) {
			$text = str_replace( array( '-', '_' ), ' ', sanitize_title( $text ) );
			$text = ucwords( preg_replace( '/\s+/', ' ', $text ) );
		}

		return self::normalize_display_title( $text );
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
			'unit'                         => sanitize_text_field( $field['unit'] ?? '' ),
			'options'                      => $options,
			'placeholder'                  => sanitize_text_field( $field['placeholder'] ?? '' ),
			'help_text'                    => sanitize_textarea_field( $field['help_text'] ?? $field['description'] ?? '' ),
			'step_key'                     => sanitize_key( $field['step_key'] ?? 'basic_profile' ),
			'sort_order'                   => absint( $field['sort_order'] ?? 0 ),
			'required_for_minimum_profile' => ! empty( $field['required_for_minimum_profile'] ) ? 1 : 0,
			'required_for_ai_report'       => ! empty( $field['required_for_ai_report'] ) ? 1 : 0,
			'sensitive'                    => ! empty( $field['sensitive'] ) ? 1 : 0,
			'ai_include'                   => isset( $field['ai_include'] ) ? ( ! empty( $field['ai_include'] ) ? 1 : 0 ) : 1,
			'public_visible_default'       => ! empty( $field['public_visible_default'] ) ? 1 : 0,
			'active'                       => ! empty( $field['active'] ) ? 1 : 0,
			'validation_rule'              => sanitize_key( $field['validation_rule'] ?? ( $field['type'] ?? 'text' ) ),
			'user_meta_key'                => sanitize_key( str_replace( '-', '_', $field['user_meta_key'] ?? '_hap_profile_' . $field_key ) ),
			'source'                       => sanitize_key( $field['source'] ?? 'default' ),
			'suite_module_count'           => absint( $field['suite_module_count'] ?? 0 ),
			'suite_backend_supported_count'=> absint( $field['suite_backend_supported_count'] ?? 0 ),
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

	// -------------------------------------------------------
	// Suite entegrasyon metodları
	// -------------------------------------------------------

	/**
	 * Suite tablosundan bir profile_field için field config oluşturur veya getirir.
	 *
	 * @param string $profile_field  Suite'deki profile_field değeri
	 * @return array  normalize edilmiş field config
	 */
	public static function get_or_create_field_from_suite( $profile_field ) {
		$profile_field = sanitize_key( $profile_field );
		$existing      = self::get_field_config( $profile_field );
		if ( $existing ) {
			return $existing;
		}

		if ( ! class_exists( 'HAP_Suite_Module_Fields' ) || ! HAP_Suite_Module_Fields::table_exists() ) {
			return array();
		}

		$impact  = HAP_Suite_Module_Fields::get_field_impact_summary();
		$suite_info = $impact[ $profile_field ] ?? null;

		$new_field = array(
			'field_key'                    => $profile_field,
			'label'                        => $suite_info ? ucfirst( str_replace( '_', ' ', $profile_field ) ) : ucfirst( str_replace( '_', ' ', $profile_field ) ),
			'type'                         => 'text',
			'unit'                         => '',
			'options'                      => array(),
			'placeholder'                  => '',
			'help_text'                    => '',
			'step_key'                     => 'basic_profile',
			'sort_order'                   => 500,
			'required_for_minimum_profile' => 0,
			'required_for_ai_report'       => 0,
			'sensitive'                    => 0,
			'ai_include'                   => 1,
			'public_visible_default'       => 1,
			'active'                       => 0,
			'validation_rule'              => 'text',
			'user_meta_key'                => '_hap_profile_' . $profile_field,
			'source'                       => 'suite_discovered',
			'suite_module_count'           => $suite_info ? (int) $suite_info['module_count'] : 0,
			'suite_backend_supported_count'=> $suite_info ? (int) $suite_info['backend_count'] : 0,
		);

		return $new_field;
	}

	/**
	 * Suite tablosundaki profil alanlarını mevcut config ile karşılaştırır;
	 * yeni olanları pasif (active=0) olarak option'a ekler.
	 *
	 * @param array $args {
	 *     @type bool $include_tool_only   Varsayılan false.
	 *     @type bool $include_disabled    Varsayılan false.
	 *     @type bool $require_backend     Varsayılan true — yalnızca backend_count > 0 alanları ekler.
	 *     @type bool $dry_run             Varsayılan false — kaydetmeden sayıları döner.
	 * }
	 * @return array  [ 'added' => int, 'updated' => int ]
	 */
	public static function sync_fields_from_suite( $args = array() ) {
		if ( ! class_exists( 'HAP_Suite_Module_Fields' ) || ! HAP_Suite_Module_Fields::table_exists() ) {
			return array( 'added' => 0, 'updated' => 0 );
		}

		$require_backend = isset( $args['require_backend'] ) ? (bool) $args['require_backend'] : true;
		$dry_run         = ! empty( $args['dry_run'] );

		$statuses = array( 'profile_core', 'profile_optional' );
		if ( ! empty( $args['include_tool_only'] ) ) {
			$statuses[] = 'tool_only';
		}
		if ( ! empty( $args['include_disabled'] ) ) {
			$statuses[] = 'disabled';
		}

		$suite_fields = HAP_Suite_Module_Fields::get_available_profile_fields( array( 'statuses' => $statuses ) );

		// Yalnızca backend destekli alanları dahil et (varsayılan).
		if ( $require_backend ) {
			$suite_fields = array_values( array_filter( $suite_fields, function ( $sf ) {
				return (int) $sf['backend_count'] > 0;
			} ) );
		}
		$impact       = HAP_Suite_Module_Fields::get_field_impact_summary();
		$current      = self::get_fields();
		$current_keys = array_column( $current, 'field_key' );

		$added   = 0;
		$updated = 0;

		foreach ( $suite_fields as $sf ) {
			$key = sanitize_key( $sf['profile_field'] );
			if ( '' === $key ) {
				continue;
			}
			$info = $impact[ $key ] ?? array();

			$idx = array_search( $key, $current_keys, true );
			if ( false !== $idx ) {
				// Mevcut — sadece suite istatistiklerini güncelle.
				$current[ $idx ]['suite_module_count']            = (int) ( $info['module_count'] ?? 0 );
				$current[ $idx ]['suite_backend_supported_count'] = (int) ( $info['backend_count'] ?? 0 );
				$updated++;
			} else {
				// Yeni — pasif olarak ekle.
				$current[]      = array(
					'field_key'                     => $key,
					'label'                         => sanitize_text_field( $info['field_label'] ?? ucfirst( str_replace( '_', ' ', $key ) ) ),
					'type'                          => 'text',
					'unit'                          => '',
					'options'                       => array(),
					'placeholder'                   => '',
					'help_text'                     => '',
					'step_key'                      => 'basic_profile',
					'sort_order'                    => 500 + $added,
					'required_for_minimum_profile'  => 0,
					'required_for_ai_report'        => 0,
					'sensitive'                     => 0,
					'ai_include'                    => 1,
					'public_visible_default'        => 1,
					'active'                        => 0,
					'validation_rule'               => 'text',
					'user_meta_key'                 => '_hap_profile_' . $key,
					'source'                        => 'suite_discovered',
					'suite_module_count'            => (int) ( $info['module_count'] ?? 0 ),
					'suite_backend_supported_count' => (int) ( $info['backend_count'] ?? 0 ),
				);
				$current_keys[] = $key;
				$added++;
			}
		}

		if ( ! $dry_run ) {
			self::save_fields( $current );
		}
		return array( 'added' => $added, 'updated' => $updated );
	}

	/**
	 * Bir profil alanıyla açılan Suite modüllerini döner.
	 * Önce Suite tablosuna bakar; yoksa wp_hap_profile_modules'a döner.
	 *
	 * @param string $field_key
	 * @return array
	 */
	public static function get_modules_for_field( $field_key ) {
		$field_key = sanitize_key( $field_key );

		// Suite tablosundan dene.
		if ( class_exists( 'HAP_Suite_Module_Fields' ) && HAP_Suite_Module_Fields::table_exists() ) {
			return HAP_Suite_Module_Fields::get_modules_for_field( $field_key );
		}

		// Fallback: wp_hap_profile_modules
		if ( ! class_exists( 'HAP_Profile_Modules' ) ) {
			return array();
		}
		$modules = new HAP_Profile_Modules();
		$items   = $modules->get_modules( array( 'availability_status' => 'active', 'result_enabled' => 1, 'limit' => 500 ) );
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

	/**
	 * Bir alanın etki özetini döner (kaç modül açıyor, backend destekli kaç).
	 *
	 * @param string $field_key
	 * @return array
	 */
	public static function get_field_impact( $field_key ) {
		$field_key = sanitize_key( $field_key );
		if ( class_exists( 'HAP_Suite_Module_Fields' ) && HAP_Suite_Module_Fields::table_exists() ) {
			$summary = HAP_Suite_Module_Fields::get_field_impact_summary();
			return $summary[ $field_key ] ?? array( 'module_count' => 0, 'backend_count' => 0 );
		}
		return array( 'module_count' => 0, 'backend_count' => 0 );
	}

	/**
	 * Kullanıcının bir field değerini döner.
	 *
	 * @param int    $user_id
	 * @param string $field_key
	 * @return mixed
	 */
	public static function get_user_field_value( $user_id, $field_key ) {
		$field = self::get_field_config( $field_key );
		if ( ! $field ) {
			return '';
		}
		return get_user_meta( absint( $user_id ), $field['user_meta_key'], true );
	}

	/**
	 * Kullanıcının bir field değerini kaydeder.
	 *
	 * @param int    $user_id
	 * @param string $field_key
	 * @param mixed  $value
	 * @return bool
	 */
	public static function save_user_field_value( $user_id, $field_key, $value ) {
		$field = self::get_field_config( $field_key );
		if ( ! $field ) {
			return false;
		}
		$sanitized = self::sanitize_field( $field_key, $value );
		return (bool) update_user_meta( absint( $user_id ), $field['user_meta_key'], $sanitized );
	}

	/**
	 * Bir field config'ini option'a kaydeder.
	 *
	 * @param string $field_key
	 * @param array  $config
	 * @return bool
	 */
	public static function save_field_config( $field_key, $config ) {
		$field_key = sanitize_key( $field_key );
		$fields    = self::get_fields();
		$found     = false;
		foreach ( $fields as &$f ) {
			if ( $f['field_key'] === $field_key ) {
				$f     = self::normalize_field_config( array_merge( $f, $config ) );
				$found = true;
				break;
			}
		}
		unset( $f );
		if ( ! $found ) {
			$config['field_key'] = $field_key;
			$fields[]            = self::normalize_field_config( $config );
		}
		return self::save_fields( $fields );
	}

	/**
	 * Minimum profil tamamlanmış mı? (HAP_Profile_User_Data gerektirmez — basit kontrol)
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_minimum_profile_complete( $user_id ) {
		if ( ! class_exists( 'HAP_Profile_User_Data' ) ) {
			return false;
		}
		$fields_obj = new self();
		$user_data  = new HAP_Profile_User_Data( $fields_obj );
		return $user_data->is_minimum_profile_complete( $user_id );
	}

	/**
	 * Eksik zorunlu alanları döner.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function get_missing_required_fields_for_user( $user_id ) {
		if ( ! class_exists( 'HAP_Profile_User_Data' ) ) {
			return array();
		}
		$fields_obj = new self();
		$user_data  = new HAP_Profile_User_Data( $fields_obj );
		$profile    = $user_data->get_user_profile_data( $user_id );
		return $user_data->get_minimum_profile_missing_fields( $user_id, $profile );
	}
}
