<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_User_Data {

	const META_PREFIX = '_hap_profile_';

	private $fields;

	private static $minimum_profile_keys = array( 'nickname', 'birth_date', 'gender', 'city' );

	private static $section_required_fallbacks = array(
		'overview'          => array( 'birth_date' ),
		'astrology'         => array( 'birth_date' ),
		'astrology_houses'  => array( 'birth_date', 'birth_time', 'birth_place' ),
		'moon_sky'          => array( 'birth_date', 'birth_time', 'birth_place' ),
		'health_lifestyle'  => array( 'height', 'weight', 'activity_level' ),
		'sport_activity'    => array( 'height', 'weight', 'activity_level' ),
		'numerology'        => array( 'first_name', 'last_name' ),
		'chinese_astrology' => array( 'birth_date' ),
		'symbolic'          => array( 'birth_date' ),
		'tarot'             => array( 'birth_date' ),
	);

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
		$req = $this->get_effective_required_fields( $module );
		if ( empty( $req ) ) {
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

	public function get_profile_display_name( $user_id, array $profile_data = array() ) {
		$nickname = isset( $profile_data['nickname'] ) ? $profile_data['nickname'] : $this->get_field_value( $user_id, 'nickname' );
		if ( '' !== (string) $nickname ) {
			return $nickname;
		}

		$wp_user = get_userdata( absint( $user_id ) );
		if ( $wp_user && ! empty( $wp_user->display_name ) ) {
			return $wp_user->display_name;
		}

		return '';
	}

	public function get_minimum_profile_keys() {
		return self::$minimum_profile_keys;
	}

	public function get_minimum_profile_missing_fields( $user_id, array $profile_data = array() ) {
		$user_id = absint( $user_id );
		if ( empty( $profile_data ) ) {
			$profile_data = $this->get_user_profile_data( $user_id );
		}

		$missing = array();
		foreach ( self::$minimum_profile_keys as $key ) {
			if ( 'nickname' === $key ) {
				if ( '' === $this->get_profile_display_name( $user_id, $profile_data ) ) {
					$missing[] = $key;
				}
				continue;
			}

			if ( ! $this->is_field_filled( $key, $profile_data ) ) {
				$missing[] = $key;
			}
		}

		return $missing;
	}

	public function is_minimum_profile_complete( $user_id, array $profile_data = array() ) {
		return empty( $this->get_minimum_profile_missing_fields( $user_id, $profile_data ) );
	}

	public function get_minimum_profile_completion( $user_id, array $profile_data = array() ) {
		$user_id = absint( $user_id );
		if ( empty( $profile_data ) ) {
			$profile_data = $this->get_user_profile_data( $user_id );
		}

		$filled = count( self::$minimum_profile_keys ) - count( $this->get_minimum_profile_missing_fields( $user_id, $profile_data ) );
		return (int) round( ( $filled / count( self::$minimum_profile_keys ) ) * 100 );
	}

	public function get_analysis_required_fields( array $modules ) {
		$required = array();
		foreach ( $modules as $module ) {
			if ( ! in_array( $module['profile_status'], array( 'profile_core', 'profile_optional' ), true ) ) {
				continue;
			}

			foreach ( $this->get_effective_required_fields( $module ) as $field_key ) {
				$required[ $field_key ] = true;
			}
		}

		return array_keys( $required );
	}

	public function get_analysis_preparation_stats( $user_id, array $modules, array $profile_data = array() ) {
		$user_id = absint( $user_id );
		if ( empty( $profile_data ) ) {
			$profile_data = $this->get_user_profile_data( $user_id );
		}

		$required = $this->get_analysis_required_fields( $modules );
		$filled   = 0;
		foreach ( $required as $field_key ) {
			if ( $this->is_field_filled( $field_key, $profile_data ) ) {
				$filled++;
			}
		}

		$total = count( $required );
		return array(
			'percentage' => $total > 0 ? (int) round( ( $filled / $total ) * 100 ) : 100,
			'filled'     => $filled,
			'total'      => $total,
			'fields'     => $required,
		);
	}

	public function get_effective_required_fields( array $module ) {
		$req = $module['required_fields'];
		if ( ! is_array( $req ) ) {
			$req = json_decode( $req, true );
		}
		if ( ! is_array( $req ) ) {
			$req = array();
		}

		$req = array_values( array_filter( array_map( 'sanitize_key', $req ) ) );
		if ( ! empty( $req ) ) {
			return $req;
		}

		$section = sanitize_key( $module['section'] ?? '' );
		return self::$section_required_fallbacks[ $section ] ?? array();
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
