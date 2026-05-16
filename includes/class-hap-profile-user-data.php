<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_User_Data {

	const META_PREFIX = '_hap_profile_';

	private $fields;

	private static $sensitive_keys = array(
		'birth_time', 'birth_place', 'phone_number', 'plate_number',
		'home_number', 'company_name', 'baby_name', 'partner_birth_date',
	);

	private static $gender_whitelist = array( 'female', 'male', 'other', 'prefer_not' );

	private static $activity_whitelist = array(
		'sedentary', 'light', 'moderate', 'active', 'very_active',
	);

	private static $relationship_whitelist = array(
		'single', 'relationship', 'married', 'complicated', 'prefer_not',
	);

	public function __construct( HAP_Profile_Fields $fields ) {
		$this->fields = $fields;
	}

	/* -------------------------------------------------------
	   OKUMA
	   ------------------------------------------------------- */

	public function get_user_data( $user_id ) {
		$user_id = absint( $user_id );
		$data    = array();
		foreach ( $this->fields->get_fields() as $field ) {
			$key          = $field['key'];
			$data[ $key ] = get_user_meta( $user_id, self::META_PREFIX . $key, true );
		}
		return $data;
	}

	public function get_user_profile_data( $user_id ) {
		return $this->get_user_data( $user_id );
	}

	public function get_field_value( $user_id, $key ) {
		return get_user_meta( absint( $user_id ), self::META_PREFIX . sanitize_key( $key ), true );
	}

	/* -------------------------------------------------------
	   KAYDETME
	   ------------------------------------------------------- */

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
			$sanitized = $this->sanitize_field_value( $field['type'], $value, $key );
			update_user_meta( $user_id, self::META_PREFIX . $key, $sanitized );
		}
		return true;
	}

	public function delete_user_data( $user_id ) {
		$user_id = absint( $user_id );
		foreach ( $this->fields->get_fields() as $field ) {
			delete_user_meta( $user_id, self::META_PREFIX . $field['key'] );
		}
	}

	/* -------------------------------------------------------
	   TAMAMLANMA VE EKSİK ALAN
	   ------------------------------------------------------- */

	public function get_completion_percentage( $user_id ) {
		$user_id = absint( $user_id );
		$active  = $this->fields->get_active_fields();
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

	/* -------------------------------------------------------
	   MODUL DURUM MOTORU
	   ------------------------------------------------------- */

	public function get_field_labels() {
		$labels = array();
		foreach ( $this->fields->get_fields() as $field ) {
			$labels[ $field['key'] ] = $field['label'];
		}
		return $labels;
	}

	public function is_field_filled( $field_key, array $profile_data ) {
		$val = $profile_data[ $field_key ] ?? '';
		return ( $val !== '' && $val !== null && $val !== false );
	}

	public function get_missing_fields_for_module( array $module, array $profile_data ) {
		$req = $module['required_fields'];
		if ( ! is_array( $req ) ) {
			$req = json_decode( $req, true );
		}
		if ( ! is_array( $req ) || empty( $req ) ) {
			return array();
		}
		$missing = array();
		foreach ( $req as $key ) {
			if ( ! $this->is_field_filled( $key, $profile_data ) ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

	/**
	 * Modül durumu döner.
	 * @return string ready|missing_fields|optional_ready|optional_missing|tool_only|disabled
	 */
	public function get_module_state( array $module, array $profile_data ) {
		$status = $module['profile_status'];
		if ( $status === 'disabled' ) {
			return 'disabled';
		}
		if ( $status === 'tool_only' ) {
			return 'tool_only';
		}

		$missing = $this->get_missing_fields_for_module( $module, $profile_data );

		if ( $status === 'profile_optional' ) {
			return empty( $missing ) ? 'optional_ready' : 'optional_missing';
		}

		return empty( $missing ) ? 'ready' : 'missing_fields';
	}

	public function get_dashboard_stats( $user_id ) {
		$user_id    = absint( $user_id );
		$completion = $this->get_completion_percentage( $user_id );
		$profile    = $this->get_user_data( $user_id );
		$active     = $this->fields->get_active_fields();
		$filled     = 0;
		foreach ( $active as $field ) {
			if ( $this->is_field_filled( $field['key'], $profile ) ) {
				$filled++;
			}
		}
		return array(
			'completion'    => $completion,
			'filled_fields' => $filled,
			'total_fields'  => count( $active ),
		);
	}

	public function get_dashboard_module_stats( array $modules, array $profile_data ) {
		$stats = array(
			'ready'              => 0,
			'missing'            => 0,
			'optional_ready'     => 0,
			'optional_missing'   => 0,
			'tool_only'          => 0,
			'total'              => 0,
			'modules_with_state' => array(),
		);

		foreach ( $modules as $mod ) {
			$state   = $this->get_module_state( $mod, $profile_data );
			$missing = in_array( $state, array( 'missing_fields', 'optional_missing' ), true )
				? $this->get_missing_fields_for_module( $mod, $profile_data )
				: array();

			$stats['modules_with_state'][] = array(
				'module'         => $mod,
				'state'          => $state,
				'missing_fields' => $missing,
			);
			$stats['total']++;

			switch ( $state ) {
				case 'ready':            $stats['ready']++; break;
				case 'missing_fields':   $stats['missing']++; break;
				case 'optional_ready':   $stats['optional_ready']++; break;
				case 'optional_missing': $stats['optional_missing']++; break;
				case 'tool_only':        $stats['tool_only']++; break;
			}
		}

		return $stats;
	}

	public function group_modules_by_section( array $modules ) {
		$grouped = array();
		foreach ( $modules as $mod ) {
			$sec              = sanitize_key( $mod['section'] ?: 'overview' );
			$grouped[ $sec ][] = $mod;
		}
		return $grouped;
	}

	/* -------------------------------------------------------
	   SANİTİZASYON + VALİDASYON
	   ------------------------------------------------------- */

	private function sanitize_field_value( $type, $value, $key = '' ) {
		$value = is_array( $value ) ? '' : (string) $value;
		$value = trim( $value );

		switch ( $type ) {
			case 'number':
				if ( $value === '' || ! is_numeric( $value ) ) {
					return '';
				}
				$num = (float) $value;
				if ( $key === 'height' && ( $num < 50 || $num > 250 ) ) {
					return '';
				}
				if ( $key === 'weight' && ( $num < 20 || $num > 300 ) ) {
					return '';
				}
				if ( $key === 'sleep_hours' && ( $num < 0 || $num > 24 ) ) {
					return '';
				}
				if ( $key === 'daily_steps' ) {
					$int = (int) round( $num );
					return ( $int >= 0 && $int <= 100000 ) ? $int : '';
				}
				return $num;

			case 'date':
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
					return '';
				}
				$parts = explode( '-', $value );
				return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $value : '';

			case 'time':
				return preg_match( '/^\d{2}:\d{2}$/', $value ) ? $value : '';

			case 'tel':
				return preg_replace( '/[^0-9+\-\s()]/', '', $value );

			case 'select':
				if ( $key === 'gender' ) {
					return in_array( $value, self::$gender_whitelist, true ) ? $value : '';
				}
				if ( $key === 'activity_level' ) {
					return in_array( $value, self::$activity_whitelist, true ) ? $value : '';
				}
				if ( $key === 'relationship_status' ) {
					return in_array( $value, self::$relationship_whitelist, true ) ? $value : '';
				}
				return sanitize_text_field( $value );

			default:
				return sanitize_text_field( $value );
		}
	}
}
