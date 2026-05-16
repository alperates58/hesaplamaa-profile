<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Share {

	private function table() {
		global $wpdb;
		return $wpdb->prefix . HAP_TABLE_SHARES;
	}

	public function generate_token() {
		return bin2hex( random_bytes( 24 ) );
	}

	public function create_share( $user_id, array $data ) {
		global $wpdb;
		$table   = $this->table();
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error( 'invalid_user', 'Geçersiz kullanıcı.' );
		}

		$token = $this->generate_token();

		$visible_sections = isset( $data['visible_sections'] ) && is_array( $data['visible_sections'] )
			? $data['visible_sections'] : array();
		$hidden_fields    = isset( $data['hidden_fields'] ) && is_array( $data['hidden_fields'] )
			? $data['hidden_fields'] : array();

		$expires_at = null;
		if ( ! empty( $data['expires_days'] ) ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( absint( $data['expires_days'] ) * DAY_IN_SECONDS ) );
		}

		$row = array(
			'user_id'          => $user_id,
			'share_token'      => $token,
			'share_title'      => sanitize_text_field( $data['share_title'] ?? '' ),
			'visible_sections' => wp_json_encode( $visible_sections ),
			'hidden_fields'    => wp_json_encode( $hidden_fields ),
			'expires_at'       => $expires_at,
			'is_active'        => 1,
			'view_count'       => 0,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $row, array( '%d','%s','%s','%s','%s','%s','%d','%d','%s','%s' ) );

		if ( ! $result ) {
			return new WP_Error( 'db_error', 'Paylaşım oluşturulamadı.' );
		}

		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
			'url'   => $this->get_share_url( $token ),
		);
	}

	public function get_share_url( $token ) {
		$settings = get_option( 'hap_profile_settings', array() );
		$page_id  = absint( $settings['profile_page_id'] ?? 0 );

		if ( $page_id ) {
			return add_query_arg( 'share', rawurlencode( $token ), get_permalink( $page_id ) );
		}
		return add_query_arg( 'hap_share', rawurlencode( $token ), home_url( '/' ) );
	}

	public function get_share_by_token( $token ) {
		global $wpdb;
		$table = $this->table();
		$token = sanitize_text_field( $token );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE share_token = %s AND is_active = 1", $token ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] ) < time() ) {
			return null;
		}
		$row['visible_sections'] = json_decode( $row['visible_sections'], true ) ?: array();
		$row['hidden_fields']    = json_decode( $row['hidden_fields'], true ) ?: array();
		return $row;
	}

	public function get_user_shares( $user_id ) {
		global $wpdb;
		$table   = $this->table();
		$user_id = absint( $user_id );
		$rows    = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ),
			ARRAY_A
		);
		return $rows ? $rows : array();
	}

	public function revoke_share( $share_id, $user_id ) {
		global $wpdb;
		$table = $this->table();
		return $wpdb->update(
			$table,
			array( 'is_active' => 0, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $share_id ), 'user_id' => absint( $user_id ) ),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);
	}

	public function increment_view_count( $share_id ) {
		global $wpdb;
		$table = $this->table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET view_count = view_count + 1, updated_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				absint( $share_id )
			)
		);
	}

	public function filter_sensitive_data( array $user_data, array $sensitive_keys, array $hidden_fields = array() ) {
		$all_hidden = array_unique( array_merge( $sensitive_keys, $hidden_fields ) );
		foreach ( $all_hidden as $key ) {
			if ( isset( $user_data[ $key ] ) ) {
				unset( $user_data[ $key ] );
			}
		}
		return $user_data;
	}
}
