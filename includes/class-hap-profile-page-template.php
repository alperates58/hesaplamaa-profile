<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intercepts the WordPress template for the profile dashboard page and serves
 * a plugin-controlled full-width template instead of the theme's content area.
 *
 * Activation: hap_profile_settings['use_full_page_template'] = 1 (default).
 * When disabled, the shortcode fallback continues to work normally.
 */
class HAP_Profile_Page_Template {

	public function register_hooks() {
		add_filter( 'template_include', array( $this, 'maybe_intercept_template' ), 99 );
	}

	public function maybe_intercept_template( $template ) {
		// Never intercept in admin, AJAX, REST, cron, or preview contexts
		if ( is_admin() ) {
			return $template;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return $template;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $template;
		}
		if ( is_preview() ) {
			return $template;
		}

		// Feature flag: defaults to enabled (1)
		$settings = get_option( 'hap_profile_settings', array() );
		if ( isset( $settings['use_full_page_template'] ) && ! $settings['use_full_page_template'] ) {
			return $template;
		}

		// Must be a singular page
		if ( ! is_page() ) {
			return $template;
		}

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return $template;
		}

		// Determine whether this is the profile dashboard page
		$profile_page_id = absint( $settings['profile_page_id'] ?? 0 );

		$is_dashboard_page = ( $profile_page_id && is_page( $profile_page_id ) )
			|| has_shortcode( $post->post_content, 'hap_user_profile_dashboard' );

		if ( ! $is_dashboard_page ) {
			return $template;
		}

		$custom = HAP_PLUGIN_DIR . 'templates/page-profile-dashboard.php';
		if ( file_exists( $custom ) ) {
			return $custom;
		}

		return $template;
	}
}
