<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Activator {

	public static function activate() {
		self::create_tables();
		self::run_migrations();
		self::set_default_options();
		flush_rewrite_rules();
	}

	public static function run_migrations() {
		global $wpdb;

		self::load_classes();

		// --- wp_hap_profile_modules ---
		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modules_table ) ) !== $modules_table ) {
			self::create_tables();
		}

		$existing_cols = array_column(
			$wpdb->get_results( "SHOW COLUMNS FROM `{$modules_table}`", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'Field'
		);

		$new_columns = array(
			'runner_type'               => "ALTER TABLE `{$modules_table}` ADD COLUMN `runner_type` VARCHAR(40) NOT NULL DEFAULT 'none' AFTER `availability_status`",
			'input_mapping'             => "ALTER TABLE `{$modules_table}` ADD COLUMN `input_mapping` LONGTEXT AFTER `runner_type`",
			'output_mapping'            => "ALTER TABLE `{$modules_table}` ADD COLUMN `output_mapping` LONGTEXT AFTER `input_mapping`",
			'runner_callback'           => "ALTER TABLE `{$modules_table}` ADD COLUMN `runner_callback` VARCHAR(255) NOT NULL DEFAULT '' AFTER `output_mapping`",
			'ajax_action'               => "ALTER TABLE `{$modules_table}` ADD COLUMN `ajax_action` VARCHAR(190) NOT NULL DEFAULT '' AFTER `runner_callback`",
			'result_selector'           => "ALTER TABLE `{$modules_table}` ADD COLUMN `result_selector` TEXT AFTER `ajax_action`",
			'tool_url'                  => "ALTER TABLE `{$modules_table}` ADD COLUMN `tool_url` TEXT AFTER `result_selector`",
			'runner_status'             => "ALTER TABLE `{$modules_table}` ADD COLUMN `runner_status` VARCHAR(80) NOT NULL DEFAULT '' AFTER `tool_url`",
			'runner_notes'              => "ALTER TABLE `{$modules_table}` ADD COLUMN `runner_notes` TEXT AFTER `runner_status`",
			'optional_fields'           => "ALTER TABLE `{$modules_table}` ADD COLUMN `optional_fields` LONGTEXT AFTER `required_fields`",
			'result_enabled'            => "ALTER TABLE `{$modules_table}` ADD COLUMN `result_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `availability_status`",
			'onboarding_prompt_enabled' => "ALTER TABLE `{$modules_table}` ADD COLUMN `onboarding_prompt_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `result_enabled`",
			'ai_include'                => "ALTER TABLE `{$modules_table}` ADD COLUMN `ai_include` TINYINT(1) NOT NULL DEFAULT 1 AFTER `onboarding_prompt_enabled`",
			'share_include_default'     => "ALTER TABLE `{$modules_table}` ADD COLUMN `share_include_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ai_include`",
			// Yeni Suite kolonları
			'suite_source'              => "ALTER TABLE `{$modules_table}` ADD COLUMN `suite_source` VARCHAR(40) NOT NULL DEFAULT '' AFTER `source`",
			'suite_last_synced_at'      => "ALTER TABLE `{$modules_table}` ADD COLUMN `suite_last_synced_at` DATETIME NULL AFTER `suite_source`",
			'suite_backend_supported'   => "ALTER TABLE `{$modules_table}` ADD COLUMN `suite_backend_supported` TINYINT(1) NOT NULL DEFAULT 0 AFTER `suite_last_synced_at`",
			'suite_section'             => "ALTER TABLE `{$modules_table}` ADD COLUMN `suite_section` VARCHAR(80) NOT NULL DEFAULT '' AFTER `suite_backend_supported`",
			'suite_required_fields'     => "ALTER TABLE `{$modules_table}` ADD COLUMN `suite_required_fields` LONGTEXT AFTER `suite_section`",
			'suite_input_mapping'       => "ALTER TABLE `{$modules_table}` ADD COLUMN `suite_input_mapping` LONGTEXT AFTER `suite_required_fields`",
		);

		foreach ( $new_columns as $col => $sql ) {
			if ( ! in_array( $col, $existing_cols, true ) ) {
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		// --- wp_hap_profile_results ---
		if ( class_exists( 'HAP_Profile_Results_Store' ) ) {
			HAP_Profile_Results_Store::create_table();
		}

		// --- Yeni tablolar ---
		self::create_consent_table();
		self::create_ai_jobs_table();
		self::create_ai_reports_table();

		// --- Options ---
		if ( class_exists( 'HAP_Profile_Fields' ) ) {
			if ( ! get_option( HAP_Profile_Fields::FIELDS_OPTION, false ) ) {
				update_option( HAP_Profile_Fields::FIELDS_OPTION, HAP_Profile_Fields::get_default_fields_config(), false );
			}
			if ( ! get_option( HAP_Profile_Fields::STEPS_OPTION, false ) ) {
				update_option( HAP_Profile_Fields::STEPS_OPTION, HAP_Profile_Fields::get_default_steps_config(), false );
			}
		}

		update_option( 'hap_profile_db_version', HAP_VERSION );
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::load_classes();

		// --- wp_hap_profile_modules ---
		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		$sql_modules   = "CREATE TABLE {$modules_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(190) NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			shortcode VARCHAR(190) NOT NULL DEFAULT '',
			section VARCHAR(80) NOT NULL DEFAULT '',
			profile_status VARCHAR(40) NOT NULL DEFAULT 'disabled',
			required_fields LONGTEXT,
			optional_fields LONGTEXT,
			missing_fields_behavior VARCHAR(40) NOT NULL DEFAULT 'show_prompt',
			ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT,
			source VARCHAR(40) NOT NULL DEFAULT 'manual',
			suite_source VARCHAR(40) NOT NULL DEFAULT '',
			suite_last_synced_at DATETIME NULL DEFAULT NULL,
			suite_backend_supported TINYINT(1) NOT NULL DEFAULT 0,
			suite_section VARCHAR(80) NOT NULL DEFAULT '',
			suite_required_fields LONGTEXT,
			suite_input_mapping LONGTEXT,
			availability_status VARCHAR(40) NOT NULL DEFAULT 'active',
			result_enabled TINYINT(1) NOT NULL DEFAULT 1,
			onboarding_prompt_enabled TINYINT(1) NOT NULL DEFAULT 1,
			ai_include TINYINT(1) NOT NULL DEFAULT 1,
			share_include_default TINYINT(1) NOT NULL DEFAULT 0,
			runner_type VARCHAR(40) NOT NULL DEFAULT 'none',
			input_mapping LONGTEXT,
			output_mapping LONGTEXT,
			runner_callback VARCHAR(255) NOT NULL DEFAULT '',
			ajax_action VARCHAR(190) NOT NULL DEFAULT '',
			result_selector TEXT,
			tool_url TEXT,
			runner_status VARCHAR(80) NOT NULL DEFAULT '',
			runner_notes TEXT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY section (section),
			KEY profile_status (profile_status),
			KEY availability_status (availability_status),
			KEY result_enabled (result_enabled)
		) {$charset_collate};";

		// --- wp_hap_profile_shares ---
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

		if ( class_exists( 'HAP_Profile_Results_Store' ) ) {
			HAP_Profile_Results_Store::create_table();
		}

		self::create_consent_table();
		self::create_ai_jobs_table();
		self::create_ai_reports_table();

		update_option( 'hap_profile_db_version', HAP_VERSION );
	}

	private static function create_consent_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'hap_profile_consents';
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			consent_type VARCHAR(80) NOT NULL,
			version VARCHAR(40) NOT NULL DEFAULT '1.0',
			accepted TINYINT(1) NOT NULL DEFAULT 0,
			accepted_at DATETIME NULL DEFAULT NULL,
			ip_hash VARCHAR(128) NULL DEFAULT NULL,
			user_agent_hash VARCHAR(128) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_consent (user_id, consent_type),
			KEY user_id (user_id),
			KEY consent_type (consent_type)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	private static function create_ai_jobs_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'hap_profile_ai_jobs';
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(40) NOT NULL DEFAULT 'pending',
			model VARCHAR(120) NULL DEFAULT NULL,
			input_hash VARCHAR(64) NULL DEFAULT NULL,
			started_at DATETIME NULL DEFAULT NULL,
			finished_at DATETIME NULL DEFAULT NULL,
			error_message TEXT NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	private static function create_ai_reports_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'hap_profile_ai_reports';
		$sql   = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			job_id BIGINT UNSIGNED NULL DEFAULT NULL,
			input_hash VARCHAR(64) NULL DEFAULT NULL,
			model VARCHAR(120) NULL DEFAULT NULL,
			language VARCHAR(20) NOT NULL DEFAULT 'tr',
			tone VARCHAR(80) NOT NULL DEFAULT 'friendly',
			summary LONGTEXT,
			full_report LONGTEXT,
			sections_json LONGTEXT,
			tokens_prompt INT UNSIGNED NULL DEFAULT NULL,
			tokens_completion INT UNSIGNED NULL DEFAULT NULL,
			cost_estimate DECIMAL(12,6) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY job_id (job_id)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	public static function should_run_migrations() {
		global $wpdb;

		self::load_classes();

		$db_version = get_option( 'hap_profile_db_version', '0' );
		if ( version_compare( $db_version, HAP_VERSION, '<' ) ) {
			return true;
		}

		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		$shares_table  = $wpdb->prefix . HAP_TABLE_SHARES;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modules_table ) ) !== $modules_table ) {
			return true;
		}
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shares_table ) ) !== $shares_table ) {
			return true;
		}

		// Yeni tablolar kontrol
		foreach ( array( 'hap_profile_consents', 'hap_profile_ai_jobs', 'hap_profile_ai_reports' ) as $t ) {
			$full = $wpdb->prefix . $t;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full ) ) !== $full ) {
				return true;
			}
		}

		if ( class_exists( 'HAP_Profile_Results_Store' ) && HAP_Profile_Results_Store::needs_migration() ) {
			return true;
		}

		$module_columns = array_column(
			$wpdb->get_results( "SHOW COLUMNS FROM `{$modules_table}`", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'Field'
		);
		$required_cols = array(
			'optional_fields', 'result_enabled', 'onboarding_prompt_enabled', 'ai_include',
			'share_include_default', 'suite_source', 'suite_backend_supported', 'suite_section',
		);
		foreach ( $required_cols as $col ) {
			if ( ! in_array( $col, $module_columns, true ) ) {
				return true;
			}
		}

		if ( class_exists( 'HAP_Profile_Fields' ) ) {
			if ( ! get_option( HAP_Profile_Fields::FIELDS_OPTION, false ) || ! get_option( HAP_Profile_Fields::STEPS_OPTION, false ) ) {
				return true;
			}
		}

		return false;
	}

	private static function load_classes() {
		$map = array(
			'HAP_Profile_Results_Store' => 'class-hap-profile-results-store.php',
			'HAP_Profile_Fields'        => 'class-hap-profile-fields.php',
		);
		foreach ( $map as $class => $file ) {
			if ( ! class_exists( $class ) && is_readable( HAP_PLUGIN_DIR . 'includes/' . $file ) ) {
				require_once HAP_PLUGIN_DIR . 'includes/' . $file;
			}
		}
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
			'share_page_id'   => 0,
			'share_url_param' => 'share',
			'default_expiry'  => 0,
			'allow_public'    => 1,
		);
		add_option( 'hap_profile_share_settings', $default_share_settings );

		$default_updater = array(
			'enable_github_updates'       => 0,
			'github_repository_url'       => '',
			'github_branch'               => 'main',
			'update_source_type'          => 'github_releases',
			'self_hosted_update_json_url' => '',
			'updater_status'              => 'not_configured',
		);
		add_option( 'hap_profile_updater_settings', $default_updater );
		add_option( 'hap_profile_delete_on_uninstall', 0 );

		// AI provider varsayılanı
		add_option(
			'hap_profile_ai_settings',
			array(
				'ai_enabled'                        => 0,
				'ai_provider'                       => 'deepseek',
				'ai_model'                          => 'deepseek-chat',
				'ai_temperature'                    => 0.7,
				'ai_max_tokens'                     => 4000,
				'ai_report_length'                  => 'medium',
				'ai_auto_generate_after_onboarding' => 0,
				'ai_api_key_option_name'            => 'hap_ai_api_key',
			)
		);
	}
}
