<?php
/**
 * HAP_Profile_Results_Store — wp_hap_profile_results tablosu CRUD.
 *
 * Sonuçlar input_hash (md5) ile önbelleklenir.
 * Profil verisi değiştiğinde hash değişir → yeni hesaplama tetiklenir.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Results_Store {

	const TABLE = 'hap_profile_results';

	// -------------------------------------------------------
	// Tablo oluşturma (aktivatör çağırır)
	// -------------------------------------------------------

	public static function create_table() {
		global $wpdb;
		$table      = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			`id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id`     BIGINT UNSIGNED NOT NULL,
			`module_slug` VARCHAR(120)    NOT NULL,
			`input_hash`  CHAR(32)        NOT NULL,
			`result_json` LONGTEXT        NOT NULL,
			`computed_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_user_module_hash` (`user_id`, `module_slug`, `input_hash`),
			KEY `idx_user_module` (`user_id`, `module_slug`)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// -------------------------------------------------------
	// Okuma
	// -------------------------------------------------------

	/**
	 * Önbellekten sonucu getir.
	 *
	 * @param int    $user_id
	 * @param string $module_slug
	 * @param string $input_hash  md5(serialize($payload))
	 * @return array|null  Sonuç dizisi ya da null (bulunamadı)
	 */
	public static function get( $user_id, $module_slug, $input_hash ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `result_json` FROM `{$table}` WHERE `user_id` = %d AND `module_slug` = %s AND `input_hash` = %s LIMIT 1",
				(int) $user_id,
				$module_slug,
				$input_hash
			)
		);

		if ( ! $row ) {
			return null;
		}

		$decoded = json_decode( $row->result_json, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	// -------------------------------------------------------
	// Yazma
	// -------------------------------------------------------

	/**
	 * Sonucu kaydet (upsert — var olana güncelle, yoksa ekle).
	 *
	 * @param int    $user_id
	 * @param string $module_slug
	 * @param string $input_hash
	 * @param array  $result
	 * @return bool
	 */
	public static function put( $user_id, $module_slug, $input_hash, array $result ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$json = wp_json_encode( $result );
		if ( false === $json ) {
			return false;
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM `{$table}` WHERE `user_id` = %d AND `module_slug` = %s AND `input_hash` = %s LIMIT 1",
				(int) $user_id,
				$module_slug,
				$input_hash
			)
		);

		if ( $existing ) {
			$rows = $wpdb->update(
				$table,
				array( 'result_json' => $json, 'computed_at' => current_time( 'mysql', true ) ),
				array( 'id' => (int) $existing ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$rows = $wpdb->insert(
				$table,
				array(
					'user_id'     => (int) $user_id,
					'module_slug' => $module_slug,
					'input_hash'  => $input_hash,
					'result_json' => $json,
					'computed_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}

		return false !== $rows;
	}

	// -------------------------------------------------------
	// Temizleme
	// -------------------------------------------------------

	/**
	 * Kullanıcıya ait tüm sonuçları sil (profil silindiğinde).
	 */
	public static function delete_for_user( $user_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::TABLE, array( 'user_id' => (int) $user_id ), array( '%d' ) );
	}

	/**
	 * Belirli bir modülün tüm önbelleğini temizle.
	 */
	public static function delete_for_module( $module_slug ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::TABLE, array( 'module_slug' => $module_slug ), array( '%s' ) );
	}
}
