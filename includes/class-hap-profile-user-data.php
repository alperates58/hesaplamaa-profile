<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_User_Data {

	private $fields;

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

	public function __construct( HAP_Profile_Fields $fields ) {
		$this->fields = $fields;
	}

	public function get_user_data( $user_id ) {
		$user_id = absint( $user_id );
		$data    = array();
		foreach ( HAP_Profile_Fields::get_fields() as $field ) {
			$data[ $field['field_key'] ] = get_user_meta( $user_id, $field['user_meta_key'], true );
		}
		return $data;
	}

	public function get_user_profile_data( $user_id ) {
		return $this->get_user_data( $user_id );
	}

	public function get_field_value( $user_id, $key ) {
		$field = HAP_Profile_Fields::get_field_config( $key );
		if ( ! $field ) {
			return '';
		}
		return get_user_meta( absint( $user_id ), $field['user_meta_key'], true );
	}

	public function save_user_data( $user_id, array $data ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			$field = HAP_Profile_Fields::get_field_config( $key );
			if ( ! $field || empty( $field['active'] ) ) {
				continue;
			}

			$sanitized = HAP_Profile_Fields::sanitize_field( $key, $value );
			update_user_meta( $user_id, $field['user_meta_key'], $sanitized );
		}

		return true;
	}

	public function delete_user_data( $user_id ) {
		$user_id = absint( $user_id );
		foreach ( HAP_Profile_Fields::get_fields() as $field ) {
			delete_user_meta( $user_id, $field['user_meta_key'] );
		}
	}

	public function get_completion_percentage( $user_id ) {
		$user_id = absint( $user_id );
		$active  = HAP_Profile_Fields::get_active_fields();
		if ( empty( $active ) ) {
			return 0;
		}

		$filled = 0;
		foreach ( $active as $field ) {
			$val = $this->get_field_value( $user_id, $field['field_key'] );
			if ( '' !== (string) $val && null !== $val && false !== $val ) {
				$filled++;
			}
		}

		return (int) round( ( $filled / count( $active ) ) * 100 );
	}

	public function get_missing_fields( $user_id, array $required_keys ) {
		$missing = array();
		foreach ( $required_keys as $key ) {
			$val = $this->get_field_value( $user_id, $key );
			if ( '' === (string) $val && '0' !== (string) $val ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

	public function has_fields( $user_id, array $required_keys ) {
		return empty( $this->get_missing_fields( $user_id, $required_keys ) );
	}

	public function get_field_labels() {
		$labels = array();
		foreach ( HAP_Profile_Fields::get_fields() as $field ) {
			$labels[ $field['field_key'] ] = $field['label'];
		}
		return $labels;
	}

	public function is_field_filled( $field_key, array $profile_data ) {
		$val = $profile_data[ $field_key ] ?? '';
		return ( '' !== (string) $val && null !== $val && false !== $val );
	}

	public function get_missing_fields_for_module( array $module, array $profile_data ) {
		$required = $this->get_effective_required_fields( $module );
		$missing  = array();
		foreach ( $required as $key ) {
			if ( ! $this->is_field_filled( $key, $profile_data ) ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

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
		$active     = HAP_Profile_Fields::get_active_fields();
		$filled     = 0;
		foreach ( $active as $field ) {
			if ( $this->is_field_filled( $field['field_key'], $profile ) ) {
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
		return HAP_Profile_Fields::get_minimum_required_fields();
	}

	public function get_minimum_profile_missing_fields( $user_id, array $profile_data = array() ) {
		$user_id = absint( $user_id );
		if ( empty( $profile_data ) ) {
			$profile_data = $this->get_user_profile_data( $user_id );
		}

		$missing = array();
		foreach ( HAP_Profile_Fields::get_minimum_required_fields() as $key ) {
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

		$minimum = HAP_Profile_Fields::get_minimum_required_fields();
		if ( empty( $minimum ) ) {
			return 100;
		}

		$filled = count( $minimum ) - count( $this->get_minimum_profile_missing_fields( $user_id, $profile_data ) );
		return (int) round( ( $filled / count( $minimum ) ) * 100 );
	}

	public function get_analysis_required_fields( array $modules ) {
		$required = array();
		foreach ( $modules as $module ) {
			if ( ! in_array( $module['profile_status'], array( 'profile_core', 'profile_optional' ), true ) ) {
				continue;
			}
			if ( empty( $module['result_enabled'] ) ) {
				continue;
			}

			foreach ( array_merge( $this->get_effective_required_fields( $module ), $this->get_optional_fields( $module ) ) as $field_key ) {
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
		$modules = new HAP_Profile_Modules();
		$req     = $modules->decode_fields_json( $module['required_fields'] ?? array() );
		if ( ! empty( $req ) ) {
			return $req;
		}

		$section = sanitize_key( $module['section'] ?? '' );
		return self::$section_required_fallbacks[ $section ] ?? array();
	}

	public function get_optional_fields( array $module ) {
		$modules = new HAP_Profile_Modules();
		return $modules->decode_fields_json( $module['optional_fields'] ?? array() );
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
			$missing = in_array( $state, array( 'missing_fields', 'optional_missing' ), true ) ? $this->get_missing_fields_for_module( $mod, $profile_data ) : array();
			$stats['modules_with_state'][] = array(
				'module'         => $mod,
				'state'          => $state,
				'missing_fields' => $missing,
			);
			$stats['total']++;

			switch ( $state ) {
				case 'ready':
					$stats['ready']++;
					break;
				case 'missing_fields':
					$stats['missing']++;
					break;
				case 'optional_ready':
					$stats['optional_ready']++;
					break;
				case 'optional_missing':
					$stats['optional_missing']++;
					break;
				case 'tool_only':
					$stats['tool_only']++;
					break;
			}
		}

		return $stats;
	}

	public function group_modules_by_section( array $modules ) {
		$grouped = array();
		foreach ( $modules as $mod ) {
			$sec = sanitize_key( $mod['section'] ?: 'overview' );
			$grouped[ $sec ][] = $mod;
		}
		return $grouped;
	}
}
