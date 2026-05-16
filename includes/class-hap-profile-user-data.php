<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_User_Data {

	const META_PREFIX = '_hap_profile_';

	private $fields;

	public function __construct( HAP_Profile_Fields $fields ) {
		$this->fields = $fields;
	}

	public function get_user_data( $user_id ) {
		$user_id = absint( $user_id );
		$data    = array();
		foreach ( $this->fields->get_fields() as $field ) {
			$key         = $field['key'];
			$data[ $key ] = get_user_meta( $user_id, self::META_PREFIX . $key, true );
		}
		return $data;
	}

	public function get_field_value( $user_id, $key ) {
		return get_user_meta( absint( $user_id ), self::META_PREFIX . sanitize_key( $key ), true );
	}

	public function save_user_data( $user_id, array $data ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$allowed_keys = wp_list_pluck( $this->fields->get_fields(), 'key' );

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}
			$field = $this->fields->get_field_by_key( $key );
			if ( ! $field ) {
				continue;
			}
			$sanitized = $this->sanitize_field_value( $field['type'], $value );
			update_user_meta( $user_id, self::META_PREFIX . $key, $sanitized );
		}
		return true;
	}

	private function sanitize_field_value( $type, $value ) {
		switch ( $type ) {
			case 'number':
				return is_numeric( $value ) ? (float) $value : '';
			case 'date':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
			case 'time':
				return preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $value ) ? $value : '';
			case 'tel':
				return preg_replace( '/[^0-9+\- ]/', '', (string) $value );
			case 'select':
				return sanitize_text_field( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	public function get_completion_percentage( $user_id ) {
		$user_id     = absint( $user_id );
		$active      = $this->fields->get_active_fields();
		if ( empty( $active ) ) {
			return 0;
		}
		$filled = 0;
		foreach ( $active as $field ) {
			$val = $this->get_field_value( $user_id, $field['key'] );
			if ( $val !== '' && $val !== null && $val !== false ) {
				$filled++;
			}
		}
		return (int) round( ( $filled / count( $active ) ) * 100 );
	}

	public function get_missing_fields( $user_id, array $required_keys ) {
		$user_id = absint( $user_id );
		$missing = array();
		foreach ( $required_keys as $key ) {
			$val = $this->get_field_value( $user_id, $key );
			if ( $val === '' || $val === null || $val === false ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

	public function has_fields( $user_id, array $required_keys ) {
		return empty( $this->get_missing_fields( $user_id, $required_keys ) );
	}

	public function delete_user_data( $user_id ) {
		$user_id = absint( $user_id );
		foreach ( $this->fields->get_fields() as $field ) {
			delete_user_meta( $user_id, self::META_PREFIX . $field['key'] );
		}
	}
}
