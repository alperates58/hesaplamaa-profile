<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Plugin {

	private static $instance = null;

	private $fields;
	private $modules;
	private $user_data;
	private $render;
	private $share;
	private $onboarding;
	private $ai_templates;
	private $health;
	private $admin;
	private $auth;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->load_dependencies();
			self::$instance->run();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function load_dependencies() {
		// Core
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-fields.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-modules.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-user-data.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-onboarding.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-share.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-render.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-ai-templates.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-updater.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-health.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-auth.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-admin.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-suite-inspector.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-module-runner.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-results-store.php';

		// Yeni modüller
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-suite-module-fields.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-consents.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-ai-reports.php';
		require_once HAP_PLUGIN_DIR . 'includes/class-hap-profile-ai-provider.php';

		if ( HAP_Profile_Activator::should_run_migrations() ) {
			HAP_Profile_Activator::run_migrations();
		}

		// Suite tablo uyarısı
		HAP_Suite_Module_Fields::maybe_warn_admin();

		$this->fields       = new HAP_Profile_Fields();
		$this->modules      = new HAP_Profile_Modules();
		$this->user_data    = new HAP_Profile_User_Data( $this->fields );
		$this->onboarding   = new HAP_Profile_Onboarding( $this->fields, $this->user_data );
		$this->share        = new HAP_Profile_Share();
		$this->render       = new HAP_Profile_Render( $this->fields, $this->modules, $this->user_data, $this->share, $this->onboarding );
		$this->ai_templates = new HAP_Profile_AI_Templates();
		$this->health       = new HAP_Profile_Health( $this->modules );
		$this->auth         = new HAP_Profile_Auth();
		$this->admin        = new HAP_Profile_Admin( $this->fields, $this->modules, $this->user_data, $this->ai_templates, $this->health );
	}

	private function run() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		new HAP_Profile_Updater();

		$this->auth->register_hooks();
		add_action( 'template_redirect', array( $this->auth, 'maybe_do_register_redirect' ), 1 );

		add_action( 'admin_menu', array( $this->admin, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );

		// Admin AJAX — modül yönetimi
		add_action( 'wp_ajax_hap_import_modules', array( $this->admin, 'ajax_import_modules' ) );
		add_action( 'wp_ajax_hap_delete_module', array( $this->admin, 'ajax_delete_module' ) );
		add_action( 'wp_ajax_hap_save_single_module', array( $this->admin, 'ajax_save_single_module' ) );
		add_action( 'wp_ajax_hap_sync_from_suite', array( $this->admin, 'ajax_sync_from_suite' ) );
		add_action( 'wp_ajax_hap_bulk_modules', array( $this->admin, 'ajax_bulk_modules' ) );
		add_action( 'wp_ajax_hap_apply_runner_presets', array( $this->admin, 'ajax_apply_runner_presets' ) );
		add_action( 'wp_ajax_hap_inspect_suite_modules', array( $this->admin, 'ajax_inspect_suite_modules' ) );

		// Admin AJAX — Suite & Fields yeni özellikler
		add_action( 'wp_ajax_hap_get_field_suite_modules', array( $this->admin, 'ajax_get_field_suite_modules' ) );
		add_action( 'wp_ajax_hap_apply_suite_mapping', array( $this->admin, 'ajax_apply_suite_mapping' ) );
		add_action( 'wp_ajax_hap_sync_suite_fields', array( $this->admin, 'ajax_sync_suite_fields' ) );
		add_action( 'wp_ajax_hap_update_module_profile_status', array( $this->admin, 'ajax_update_module_profile_status' ) );

		// Shortcode'lar
		$this->render->register_shortcodes();
		add_action( 'wp_enqueue_scripts', array( $this->render, 'enqueue_assets' ) );
		add_filter( 'wp_robots', array( $this->render, 'add_noindex' ) );

		// Frontend AJAX — profil/onboarding
		add_action( 'wp_ajax_hap_save_profile', array( $this->render, 'handle_save_profile' ) );
		add_action( 'wp_ajax_hap_save_onboarding_step', array( $this->onboarding, 'handle_save_step' ) );
		add_action( 'wp_ajax_hap_save_consents', array( $this->onboarding, 'handle_save_consents' ) );
		add_action( 'admin_post_hap_save_profile', array( $this->render, 'handle_save_profile_post' ) );
		add_action( 'wp_ajax_hap_create_share', array( $this->render, 'handle_create_share' ) );
		add_action( 'wp_ajax_hap_revoke_share', array( $this->render, 'handle_revoke_share' ) );

		// Final step — Beni sonuçlarıma götür
		add_action( 'wp_ajax_hap_generate_profile_results', array( $this->onboarding, 'handle_generate_results' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'hesaplamaa-profile', false, dirname( HAP_PLUGIN_BASENAME ) . '/languages' );
	}
}
