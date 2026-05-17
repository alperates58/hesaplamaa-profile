<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kullanıcı onay kayıtlarını yönetir.
 * KVKK / gizlilik / kullanım şartları / AI işleme rızaları.
 */
class HAP_Profile_Consents {

	const TABLE = 'hap_profile_consents';

	// Zorunlu onay türleri — bunlar olmadan onboarding tamamlanamaz.
	private static $required_types = array( 'kvkk_aydinlatma', 'privacy_policy', 'terms_of_use' );

	// AI raporu için ayrıca gerekli onay.
	private static $ai_consent_type = 'explicit_ai_processing';

	// -------------------------------------------------------
	// CRUD
	// -------------------------------------------------------

	/**
	 * @param int    $user_id
	 * @param string $type     Onay türü
	 * @param bool   $accepted
	 * @param string $version
	 * @return bool
	 */
	public static function save_consent( $user_id, $type, $accepted, $version = '1.0' ) {
		global $wpdb;
		$user_id  = absint( $user_id );
		$type     = sanitize_key( $type );
		$accepted = $accepted ? 1 : 0;
		$version  = sanitize_text_field( $version );

		if ( ! $user_id || ! $type ) {
			return false;
		}

		$table    = $wpdb->prefix . self::TABLE;
		$now      = current_time( 'mysql' );
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE user_id = %d AND consent_type = %s LIMIT 1",
				$user_id,
				$type
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$table,
				array(
					'accepted'    => $accepted,
					'accepted_at' => $accepted ? $now : null,
					'version'     => $version,
					'ip_hash'     => self::hash_ip( self::get_current_ip() ),
					'user_agent_hash' => self::hash_user_agent( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
					'updated_at'  => $now,
				),
				array( 'user_id' => $user_id, 'consent_type' => $type ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$result = $wpdb->insert(
				$table,
				array(
					'user_id'         => $user_id,
					'consent_type'    => $type,
					'version'         => $version,
					'accepted'        => $accepted,
					'accepted_at'     => $accepted ? $now : null,
					'ip_hash'         => self::hash_ip( self::get_current_ip() ),
					'user_agent_hash' => self::hash_user_agent( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Zorunlu onayların tamamı verilmiş mi kontrol eder.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function has_required_consents( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		foreach ( self::$required_types as $type ) {
			if ( ! self::has_consent( $user_id, $type ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * AI işleme rızası var mı?
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function has_ai_consent( $user_id ) {
		return self::has_consent( $user_id, self::$ai_consent_type );
	}

	/**
	 * Kullanıcının tüm onaylarını döner.
	 *
	 * @param int $user_id
	 * @return array
	 */
	public static function get_user_consents( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table = $wpdb->prefix . self::TABLE;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT consent_type, accepted, version, accepted_at FROM `{$table}` WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);
		$result = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$result[ $row['consent_type'] ] = array(
					'accepted'    => (bool) $row['accepted'],
					'version'     => $row['version'],
					'accepted_at' => $row['accepted_at'],
				);
			}
		}
		return $result;
	}

	/**
	 * Zorunlu onay türlerini döner.
	 *
	 * @return string[]
	 */
	public static function get_required_consent_types() {
		return self::$required_types;
	}

	/**
	 * AI onay türünü döner.
	 *
	 * @return string
	 */
	public static function get_ai_consent_type() {
		return self::$ai_consent_type;
	}

	// -------------------------------------------------------
	// Hash yardımcıları
	// -------------------------------------------------------

	/**
	 * IP'yi tek yönlü hash'ler; ham IP saklanmaz.
	 */
	public static function hash_ip( $ip ) {
		if ( '' === $ip ) {
			return '';
		}
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'hap_ip_salt';
		return hash( 'sha256', $salt . $ip );
	}

	/**
	 * User agent'ı tek yönlü hash'ler.
	 */
	public static function hash_user_agent( $ua ) {
		if ( '' === $ua ) {
			return '';
		}
		$salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'hap_ua_salt';
		return hash( 'sha256', $salt . $ua );
	}

	// -------------------------------------------------------
	// Yardımcı metodlar
	// -------------------------------------------------------

	private static function has_consent( $user_id, $type ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$val   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT accepted FROM `{$table}` WHERE user_id = %d AND consent_type = %s LIMIT 1",
				$user_id,
				$type
			)
		);
		return '1' === (string) $val;
	}

	private static function get_current_ip() {
		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = strtok( $ip, ',' );
				break;
			}
		}
		return filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ? trim( $ip ) : '';
	}
}
