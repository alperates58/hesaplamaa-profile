<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Onboarding {

	private $fields;
	private $user_data;

	public function __construct( HAP_Profile_Fields $fields, HAP_Profile_User_Data $user_data ) {
		$this->fields    = $fields;
		$this->user_data = $user_data;
	}

	public function get_steps() {
		$steps = array();
		foreach ( HAP_Profile_Fields::get_active_steps() as $index => $step ) {
			$steps[ $step['step_key'] ] = array(
				'id'             => $step['step_key'],
				'number'         => $index + 1,
				'title'          => $step['title'],
				'subtitle'       => $step['description'],
				'description'    => $step['description'],
				'fields'         => wp_list_pluck( HAP_Profile_Fields::get_fields_by_step( $step['step_key'] ), 'field_key' ),
				'optional'       => empty( $step['is_required'] ),
				'why'            => $step['description'],
				'cta'            => empty( $step['is_required'] ) ? 'Kaydet ve Devam Et' : 'Kaydet ve Devam Et',
				'icon'           => $step['icon'],
				'completion_rule'=> $step['completion_rule'],
				'is_required'    => ! empty( $step['is_required'] ),
				'active'         => ! empty( $step['active'] ),
			);
		}
		return $steps;
	}

	public function get_step_order() {
		return array_keys( $this->get_steps() );
	}

	public function get_step( $step_id ) {
		$steps = $this->get_steps();
		return $steps[ $step_id ] ?? null;
	}

	public function get_field_options( $key ) {
		return HAP_Profile_Fields::get_field_options( $key );
	}

	public function get_step_fields( $step_id ) {
		return HAP_Profile_Fields::get_fields_by_step( $step_id );
	}

	public function is_step_complete( $user_id, $step_id, array $profile = array() ) {
		$step = $this->get_step( $step_id );
		if ( ! $step ) {
			return false;
		}

		if ( empty( $profile ) ) {
			$profile = $this->user_data->get_user_profile_data( $user_id );
		}

		$fields = $this->get_step_fields( $step_id );
		if ( empty( $fields ) ) {
			return true;
		}

		$filled = 0;
		foreach ( $fields as $field ) {
			if ( $this->user_data->is_field_filled( $field['field_key'], $profile ) ) {
				$filled++;
			}
		}

		if ( 'any_field' === $step['completion_rule'] ) {
			return $filled > 0;
		}

		return $filled === count( $fields );
	}

	public function get_initial_step( $user_id ) {
		$user_id = absint( $user_id );
		$profile = $this->user_data->get_user_profile_data( $user_id );

		foreach ( $this->get_step_order() as $step_id ) {
			$step = $this->get_step( $step_id );
			if ( ! $step || empty( $step['active'] ) ) {
				continue;
			}

			if ( ! $this->is_step_complete( $user_id, $step_id, $profile ) ) {
				return $step_id;
			}
		}

		$order = $this->get_step_order();
		return ! empty( $order ) ? end( $order ) : 'basic_profile';
	}

	public function are_required_steps_complete( $user_id ) {
		$profile = $this->user_data->get_user_profile_data( $user_id );
		foreach ( $this->get_step_order() as $step_id ) {
			$step = $this->get_step( $step_id );
			if ( ! empty( $step['is_required'] ) && ! $this->is_step_complete( $user_id, $step_id, $profile ) ) {
				return false;
			}
		}
		return true;
	}

	public function handle_save_step() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giris yapmaniz gerekiyor.' ) );
		}

		$step_id = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$step    = $this->get_step( $step_id );

		if ( ! $step ) {
			wp_send_json_error( array( 'message' => 'Gecersiz onboarding adimi.' ) );
		}

		$payload = isset( $_POST['profile_data'] ) ? (array) $_POST['profile_data'] : array();
		$data    = array();
		foreach ( $step['fields'] as $field_key ) {
			if ( array_key_exists( $field_key, $payload ) ) {
				$data[ $field_key ] = wp_unslash( $payload[ $field_key ] );
			}
		}

		$user_id = get_current_user_id();
		$this->user_data->save_user_data( $user_id, $data );

		$profile = $this->user_data->get_user_profile_data( $user_id );
		if ( ! empty( $step['is_required'] ) && ! $this->is_step_complete( $user_id, $step_id, $profile ) ) {
			wp_send_json_error(
				array(
					'message'        => 'Gerekli alanlari tamamlaman gerekiyor.',
					'missing_fields' => array_values(
						array_filter(
							$step['fields'],
							function ( $field_key ) use ( $profile ) {
								return empty( $profile[ $field_key ] ) && '0' !== (string) ( $profile[ $field_key ] ?? '' );
							}
						)
					),
				)
			);
		}

		$order         = $this->get_step_order();
		$current_index = array_search( $step_id, $order, true );
		$next_step     = false !== $current_index && isset( $order[ $current_index + 1 ] ) ? $order[ $current_index + 1 ] : $step_id;

		wp_send_json_success(
			array(
				'message'            => 'Onboarding adimi kaydedildi.',
				'next_step'          => $next_step,
				'minimum_complete'   => $this->user_data->is_minimum_profile_complete( $user_id, $profile ),
				'minimum_completion' => $this->user_data->get_minimum_profile_completion( $user_id, $profile ),
				'missing_minimum'    => $this->user_data->get_minimum_profile_missing_fields( $user_id, $profile ),
			)
		);
	}
}
