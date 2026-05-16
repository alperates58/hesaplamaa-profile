<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Fields {

	private static $default_fields = array(
		array(
			'key'         => 'nickname',
			'label'       => 'Takma Ad',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 1,
			'description' => 'Profilinizde görünecek takma adınız.',
		),
		array(
			'key'         => 'first_name',
			'label'       => 'Ad',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 1,
			'description' => '',
		),
		array(
			'key'         => 'last_name',
			'label'       => 'Soyad',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 1,
			'description' => '',
		),
		array(
			'key'         => 'birth_date',
			'label'       => 'Doğum Tarihi',
			'type'        => 'date',
			'active'      => true,
			'required'    => true,
			'sensitive'   => true,
			'step'        => 1,
			'description' => 'Astroloji ve numeroloji hesaplamaları için gereklidir.',
		),
		array(
			'key'         => 'birth_time',
			'label'       => 'Doğum Saati',
			'type'        => 'time',
			'active'      => true,
			'required'    => false,
			'sensitive'   => true,
			'step'        => 2,
			'description' => 'Yükselen burç ve ev hesaplamaları için gereklidir.',
		),
		array(
			'key'         => 'birth_place',
			'label'       => 'Doğum Yeri',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => true,
			'step'        => 2,
			'description' => 'Doğum yeri/şehri. Ev ve gezegen hesaplamaları için gereklidir.',
		),
		array(
			'key'         => 'gender',
			'label'       => 'Cinsiyet',
			'type'        => 'select',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 1,
			'description' => '',
		),
		array(
			'key'         => 'height',
			'label'       => 'Boy (cm)',
			'type'        => 'number',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 2,
			'description' => 'Sağlık ve vücut analizi için gereklidir.',
		),
		array(
			'key'         => 'weight',
			'label'       => 'Kilo (kg)',
			'type'        => 'number',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 2,
			'description' => 'Sağlık ve spor analizi için gereklidir.',
		),
		array(
			'key'         => 'city',
			'label'       => 'Yaşadığı Şehir',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 1,
			'description' => '',
		),
		array(
			'key'         => 'activity_level',
			'label'       => 'Aktivite Seviyesi',
			'type'        => 'select',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 2,
			'description' => 'Günlük fiziksel aktivite düzeyi.',
		),
		array(
			'key'         => 'sleep_hours',
			'label'       => 'Günlük Uyku Süresi (saat)',
			'type'        => 'number',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 2,
			'description' => '',
		),
		array(
			'key'         => 'daily_steps',
			'label'       => 'Günlük Adım Sayısı',
			'type'        => 'number',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 2,
			'description' => '',
		),
		array(
			'key'         => 'relationship_status',
			'label'       => 'İlişki Durumu',
			'type'        => 'select',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 3,
			'description' => '',
		),
		array(
			'key'         => 'partner_birth_date',
			'label'       => 'Partner Doğum Tarihi',
			'type'        => 'date',
			'active'      => true,
			'required'    => false,
			'sensitive'   => true,
			'step'        => 3,
			'description' => 'Aşk uyumu hesaplamaları için gereklidir.',
		),
		array(
			'key'         => 'home_number',
			'label'       => 'Ev Numarası',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => true,
			'step'        => 3,
			'description' => 'Ev numarası numerolojisi için gereklidir.',
		),
		array(
			'key'         => 'phone_number',
			'label'       => 'Telefon Numarası',
			'type'        => 'tel',
			'active'      => true,
			'required'    => false,
			'sensitive'   => true,
			'step'        => 3,
			'description' => 'Telefon numerolojisi için gereklidir.',
		),
		array(
			'key'         => 'plate_number',
			'label'       => 'Plaka Numarası',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => true,
			'step'        => 3,
			'description' => 'Plaka numerolojisi için gereklidir.',
		),
		array(
			'key'         => 'company_name',
			'label'       => 'Şirket Adı',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 3,
			'description' => 'Şirket ismi numerolojisi için gereklidir.',
		),
		array(
			'key'         => 'baby_name',
			'label'       => 'Bebek Adı',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 3,
			'description' => 'Bebek ismi numerolojisi için gereklidir.',
		),
		array(
			'key'         => 'career_goal',
			'label'       => 'Kariyer Hedefi',
			'type'        => 'text',
			'active'      => true,
			'required'    => false,
			'sensitive'   => false,
			'step'        => 3,
			'description' => '',
		),
	);

	public function get_default_fields() {
		return self::$default_fields;
	}

	public function get_fields() {
		$saved = get_option( 'hap_profile_fields', null );
		if ( null === $saved ) {
			return self::$default_fields;
		}
		return is_array( $saved ) ? $saved : self::$default_fields;
	}

	public function get_active_fields() {
		return array_filter( $this->get_fields(), function( $f ) {
			return ! empty( $f['active'] );
		} );
	}

	public function get_field_by_key( $key ) {
		$key = sanitize_key( $key );
		foreach ( $this->get_fields() as $field ) {
			if ( $field['key'] === $key ) {
				return $field;
			}
		}
		return null;
	}

	public function get_sensitive_keys() {
		$keys = array();
		foreach ( $this->get_fields() as $field ) {
			if ( ! empty( $field['sensitive'] ) ) {
				$keys[] = $field['key'];
			}
		}
		return $keys;
	}

	public function save_fields( array $fields ) {
		$sanitized = array();
		foreach ( $fields as $field ) {
			if ( empty( $field['key'] ) ) {
				continue;
			}
			$sanitized[] = array(
				'key'         => sanitize_key( $field['key'] ),
				'label'       => sanitize_text_field( $field['label'] ?? '' ),
				'type'        => sanitize_key( $field['type'] ?? 'text' ),
				'active'      => ! empty( $field['active'] ),
				'required'    => ! empty( $field['required'] ),
				'sensitive'   => ! empty( $field['sensitive'] ),
				'step'        => absint( $field['step'] ?? 1 ),
				'description' => sanitize_text_field( $field['description'] ?? '' ),
			);
		}
		update_option( 'hap_profile_fields', $sanitized );
		return true;
	}

	public function get_label( $key ) {
		$field = $this->get_field_by_key( $key );
		return $field ? $field['label'] : $key;
	}
}
