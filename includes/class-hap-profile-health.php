<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Health {

	private $modules;

	public function __construct( HAP_Profile_Modules $modules ) {
		$this->modules = $modules;
	}

	public function run_checks() {
		global $wpdb;

		$checks = array();
		$checks['plugin_version'] = array(
			'label'  => 'Eklenti Versiyonu',
			'value'  => HAP_VERSION,
			'status' => 'ok',
		);
		$checks['php_version'] = array(
			'label'  => 'PHP Versiyonu',
			'value'  => PHP_VERSION,
			'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'error',
		);
		$checks['wp_version'] = array(
			'label'  => 'WordPress Versiyonu',
			'value'  => get_bloginfo( 'version' ),
			'status' => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? 'ok' : 'warning',
		);

		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		$shares_table  = $wpdb->prefix . HAP_TABLE_SHARES;
		$checks['modules_table'] = array(
			'label'  => 'Modül Tablosu',
			'value'  => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modules_table ) ) === $modules_table ? 'Mevcut' : 'Eksik',
			'status' => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modules_table ) ) === $modules_table ? 'ok' : 'error',
		);
		$checks['shares_table'] = array(
			'label'  => 'Paylaşım Tablosu',
			'value'  => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shares_table ) ) === $shares_table ? 'Mevcut' : 'Eksik',
			'status' => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shares_table ) ) === $shares_table ? 'ok' : 'error',
		);

		$active_fields = HAP_Profile_Fields::get_active_fields();
		$active_steps  = HAP_Profile_Fields::get_active_steps();
		$minimum       = HAP_Profile_Fields::get_minimum_required_fields();

		$checks['active_profile_fields'] = array(
			'label'  => 'Aktif profil alanı sayısı',
			'value'  => count( $active_fields ),
			'status' => count( $active_fields ) > 0 ? 'ok' : 'warning',
		);
		$checks['active_steps'] = array(
			'label'  => 'Aktif onboarding step sayısı',
			'value'  => count( $active_steps ),
			'status' => count( $active_steps ) > 0 ? 'ok' : 'warning',
		);
		$checks['minimum_required_fields'] = array(
			'label'  => 'Minimum required alanlar',
			'value'  => empty( $minimum ) ? 'Yok' : implode( ', ', $minimum ),
			'status' => empty( $minimum ) ? 'warning' : 'ok',
		);
		$checks['result_enabled_modules'] = array(
			'label'  => 'result_enabled modül sayısı',
			'value'  => $this->modules->count_modules( array( 'result_enabled' => 1 ) ),
			'status' => 'info',
		);
		$checks['prompt_enabled_modules'] = array(
			'label'  => 'onboarding_prompt_enabled modül sayısı',
			'value'  => $this->modules->count_modules( array( 'onboarding_prompt_enabled' => 1 ) ),
			'status' => 'info',
		);

		$dependency_ok = true;
		foreach ( $active_fields as $field ) {
			HAP_Profile_Fields::get_modules_for_field( $field['field_key'] );
			if ( empty( $field['step_key'] ) ) {
				$dependency_ok = false;
			}
		}
		$checks['field_dependency_map'] = array(
			'label'  => 'Field dependency map durumu',
			'value'  => $dependency_ok ? 'Hazır' : 'Eksik step bağı var',
			'status' => $dependency_ok ? 'ok' : 'warning',
		);

		$checks['field_configs_option'] = array(
			'label'  => 'Field configs option/table durumu',
			'value'  => get_option( HAP_Profile_Fields::FIELDS_OPTION, false ) ? 'Option mevcut' : 'Option eksik',
			'status' => get_option( HAP_Profile_Fields::FIELDS_OPTION, false ) ? 'ok' : 'error',
		);

		return $checks;
	}

	public function render() {
		$checks = $this->run_checks();
		$icons  = array(
			'ok'      => 'OK',
			'warning' => 'WARN',
			'error'   => 'ERR',
			'info'    => 'INFO',
		);
		echo '<div class="hap-health-checks">';
		echo '<table class="widefat striped"><thead><tr><th>Kontrol</th><th>Değer</th><th>Durum</th></tr></thead><tbody>';
		foreach ( $checks as $check ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $check['label'] ),
				esc_html( (string) $check['value'] ),
				esc_html( $icons[ $check['status'] ] ?? 'INFO' )
			);
		}
		echo '</tbody></table></div>';
	}
}
