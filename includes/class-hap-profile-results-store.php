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
		$table             = $wpdb->prefix . self::TABLE;
		$charset_collate   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			`id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id`           BIGINT UNSIGNED NOT NULL,
			`module_slug`       VARCHAR(120)    NOT NULL,
			`input_hash`        CHAR(32)        NOT NULL,
			`status`            VARCHAR(80)     NOT NULL DEFAULT '',
			`result_json`       LONGTEXT        NOT NULL,
			`raw_result`        LONGTEXT NULL,
			`normalized_result` LONGTEXT NULL,
			`computed_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`calculated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_user_module_hash` (`user_id`, `module_slug`, `input_hash`),
			KEY `idx_user_module` (`user_id`, `module_slug`),
			KEY `idx_status` (`status`)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::migrate_schema();
	}

	public static function needs_migration() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $found !== $table ) {
			return true;
		}

		$columns  = self::get_existing_columns();
		$required = array( 'status', 'computed_at', 'calculated_at', 'result_json', 'raw_result', 'normalized_result' );

		foreach ( $required as $column ) {
			if ( ! in_array( $column, $columns, true ) ) {
				return true;
			}
		}

		return false;
	}

	public static function migrate_schema() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $found !== $table ) {
			return;
		}

		$columns = self::get_existing_columns();
		$queries = array();

		if ( ! in_array( 'status', $columns, true ) ) {
			$queries[] = "ALTER TABLE `{$table}` ADD COLUMN `status` VARCHAR(80) NOT NULL DEFAULT '' AFTER `input_hash`";
		}

		if ( ! in_array( 'raw_result', $columns, true ) ) {
			$queries[] = "ALTER TABLE `{$table}` ADD COLUMN `raw_result` LONGTEXT NULL AFTER `result_json`";
		}

		if ( ! in_array( 'normalized_result', $columns, true ) ) {
			$queries[] = "ALTER TABLE `{$table}` ADD COLUMN `normalized_result` LONGTEXT NULL AFTER `raw_result`";
		}

		if ( ! in_array( 'computed_at', $columns, true ) ) {
			$queries[] = "ALTER TABLE `{$table}` ADD COLUMN `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `normalized_result`";
		}

		if ( ! in_array( 'calculated_at', $columns, true ) ) {
			$queries[] = "ALTER TABLE `{$table}` ADD COLUMN `calculated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `computed_at`";
		}

		foreach ( $queries as $query ) {
			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$columns = self::get_existing_columns();

		if ( in_array( 'computed_at', $columns, true ) && in_array( 'calculated_at', $columns, true ) ) {
			$wpdb->query( "UPDATE `{$table}` SET `calculated_at` = `computed_at` WHERE (`calculated_at` IS NULL OR `calculated_at` = '0000-00-00 00:00:00') AND `computed_at` IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "UPDATE `{$table}` SET `computed_at` = `calculated_at` WHERE (`computed_at` IS NULL OR `computed_at` = '0000-00-00 00:00:00') AND `calculated_at` IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( in_array( 'status', $columns, true ) ) {
			$wpdb->query( "UPDATE `{$table}` SET `status` = 'ready_result' WHERE `status` = '' AND `result_json` <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
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
				"SELECT `result_json`, `raw_result`, `normalized_result`, `status`, `computed_at`, `calculated_at` FROM `{$table}` WHERE `user_id` = %d AND `module_slug` = %s AND `input_hash` = %s LIMIT 1",
				(int) $user_id,
				$module_slug,
				$input_hash
			)
		);

		if ( ! $row ) {
			return null;
		}

		$normalized = json_decode( $row->normalized_result, true );
		$decoded    = json_decode( $row->result_json, true );

		if ( ! is_array( $normalized ) ) {
			$normalized = is_array( $decoded ) ? $decoded : null;
		}

		if ( ! is_array( $normalized ) ) {
			return null;
		}

		if ( empty( $normalized['status'] ) && ! empty( $row->status ) ) {
			$normalized['status'] = $row->status;
		}

		if ( empty( $normalized['calculated_at'] ) ) {
			$normalized['calculated_at'] = ! empty( $row->calculated_at ) ? $row->calculated_at : $row->computed_at;
		}

		if ( empty( $normalized['computed_at'] ) && ! empty( $row->computed_at ) ) {
			$normalized['computed_at'] = $row->computed_at;
		}

		if ( ! isset( $normalized['result_json'] ) && is_array( $decoded ) ) {
			$normalized['result_json'] = $decoded;
		}

		return $normalized;
	}

	/**
	 * Kullanıcının tüm hesaplanmış sonuçlarını getir.
	 *
	 * @param int $user_id
	 * @return array  [ 'module_slug' => result_array ]
	 */
	public static function get_all_results( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `module_slug`, `normalized_result`, `result_json`, `status` FROM `{$table}` WHERE `user_id` = %d AND (`status` = 'ready_result' OR `status` = 'completed')",
				(int) $user_id
			),
			ARRAY_A
		);
		
		$results = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$normalized = json_decode( $row['normalized_result'], true );
				if ( ! is_array( $normalized ) ) {
					$normalized = json_decode( $row['result_json'], true );
				}
				if ( is_array( $normalized ) ) {
					$results[ $row['module_slug'] ] = $normalized;
				}
			}
		}
		
		return $results;
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

		$stored_result = self::normalize_stored_result( $result );
		$json          = wp_json_encode( $stored_result['result_json'] );
		$raw_json      = wp_json_encode( $stored_result['raw_result'] );
		$normalized    = wp_json_encode( $stored_result['normalized_result'] );

		if ( false === $json || false === $raw_json || false === $normalized ) {
			return false;
		}

		$timestamp = current_time( 'mysql', true );
		$data      = array(
			'status'            => $stored_result['status'],
			'result_json'       => $json,
			'raw_result'        => $raw_json,
			'normalized_result' => $normalized,
			'computed_at'       => $timestamp,
			'calculated_at'     => $timestamp,
		);

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
				$data,
				array( 'id' => (int) $existing ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$rows = $wpdb->insert(
				$table,
				array_merge(
					array(
						'user_id'     => (int) $user_id,
						'module_slug' => $module_slug,
						'input_hash'  => $input_hash,
					),
					$data
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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

	private static function get_existing_columns() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$rows  = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_column( $rows, 'Field' );
	}

	private static function normalize_stored_result( array $result ) {
		$status = '';

		if ( ! empty( $result['status'] ) && is_scalar( $result['status'] ) ) {
			$status = sanitize_key( (string) $result['status'] );
		} elseif ( ! empty( $result['state'] ) && is_scalar( $result['state'] ) ) {
			$status = sanitize_key( (string) $result['state'] );
		} elseif ( ! empty( $result['success'] ) ) {
			$status = 'ready_result';
		}

		$raw_result = array_key_exists( 'raw_result', $result ) ? $result['raw_result'] : $result;
		$normalized = array_key_exists( 'normalized_result', $result ) ? $result['normalized_result'] : $result;

		if ( is_array( $normalized ) ) {
			if ( '' !== $status && empty( $normalized['status'] ) ) {
				$normalized['status'] = $status;
			}
		} else {
			$normalized = $result;
		}

		return array(
			'status'            => $status,
			'result_json'       => $result,
			'raw_result'        => $raw_result,
			'normalized_result' => $normalized,
		);
	}
}
