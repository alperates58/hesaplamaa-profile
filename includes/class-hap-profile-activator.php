<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Activator {

	public static function activate() {
		self::create_tables();
		self::set_default_options();
		flush_rewrite_rules();
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		$sql_modules   = "CREATE TABLE {$modules_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(190) NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			shortcode VARCHAR(190) NOT NULL DEFAULT '',
			section VARCHAR(80) NOT NULL DEFAULT '',
			profile_status VARCHAR(40) NOT NULL DEFAULT 'disabled',
			required_fields LONGTEXT,
			missing_fields_behavior VARCHAR(40) NOT NULL DEFAULT 'show_prompt',
			ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT,
			source VARCHAR(40) NOT NULL DEFAULT 'manual',
			availability_status VARCHAR(40) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY section (section),
			KEY profile_status (profile_status),
			KEY availability_status (availability_status)
		) {$charset_collate};";

		$shares_table = $wpdb->prefix . HAP_TABLE_SHARES;
		$sql_shares   = "CREATE TABLE {$shares_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			share_token VARCHAR(190) NOT NULL,
			share_title VARCHAR(255) NOT NULL DEFAULT '',
			visible_sections LONGTEXT,
			hidden_fields LONGTEXT,
			expires_at DATETIME NULL DEFAULT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY share_token (share_token),
			KEY user_id (user_id),
			KEY is_active (is_active)
		) {$charset_collate};";

		dbDelta( $sql_modules );
		dbDelta( $sql_shares );

		update_option( 'hap_profile_db_version', HAP_VERSION );
	}

	private static function set_default_options() {
		$default_settings = array(
			'system_active'           => 1,
			'ai_enabled'              => 0,
			'profile_page_id'         => 0,
			'noindex_profiles'        => 1,
			'allow_guest_profile'     => 0,
			'premium_dashboard'       => 1,
			'shareable_profile'       => 1,
			'hide_sensitive_on_share' => 1,
		);
		add_option( 'hap_profile_settings', $default_settings );

		$default_share_settings = array(
			'share_page_id'    => 0,
			'share_url_param'  => 'share',
			'default_expiry'   => 0,
			'allow_public'     => 1,
		);
		add_option( 'hap_profile_share_settings', $default_share_settings );

		$default_updater = array(
			'enable_github_updates'    => 0,
			'github_repository_url'    => '',
			'github_branch'            => 'main',
			'update_source_type'       => 'github_releases',
			'self_hosted_update_json_url' => '',
			'updater_status'           => 'not_configured',
		);
		add_option( 'hap_profile_updater_settings', $default_updater );

		add_option( 'hap_profile_delete_on_uninstall', 0 );
	}
}
