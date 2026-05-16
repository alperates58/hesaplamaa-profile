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
		$table_exists  = $wpdb->get_var( "SHOW TABLES LIKE '{$modules_table}'" ) === $modules_table;
		$checks['modules_table'] = array(
			'label'  => 'Modül Tablosu (wp_hap_profile_modules)',
			'value'  => $table_exists ? 'Mevcut' : 'Eksik',
			'status' => $table_exists ? 'ok' : 'error',
		);

		$shares_table  = $wpdb->prefix . HAP_TABLE_SHARES;
		$table2_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$shares_table}'" ) === $shares_table;
		$checks['shares_table'] = array(
			'label'  => 'Paylaşım Tablosu (wp_hap_profile_shares)',
			'value'  => $table2_exists ? 'Mevcut' : 'Eksik',
			'status' => $table2_exists ? 'ok' : 'error',
		);

		$total = $this->modules->count_modules();
		$checks['total_modules'] = array(
			'label'  => 'Toplam Modül',
			'value'  => $total,
			'status' => $total > 0 ? 'ok' : 'warning',
		);

		$status_counts = $this->modules->get_status_counts();
		$checks['profile_core'] = array(
			'label'  => 'profile_core Modül',
			'value'  => $status_counts['profile_core'],
			'status' => 'info',
		);
		$checks['profile_optional'] = array(
			'label'  => 'profile_optional Modül',
			'value'  => $status_counts['profile_optional'],
			'status' => 'info',
		);
		$checks['tool_only'] = array(
			'label'  => 'tool_only Modül',
			'value'  => $status_counts['tool_only'],
			'status' => 'info',
		);
		$checks['disabled'] = array(
			'label'  => 'disabled Modül',
			'value'  => $status_counts['disabled'],
			'status' => 'info',
		);

		$planned = $this->modules->count_modules( array( 'availability_status' => 'planned' ) );
		$checks['planned_modules'] = array(
			'label'  => 'Planlanmış (Planned) Modül',
			'value'  => $planned,
			'status' => 'info',
		);

		$active_av = $this->modules->count_modules( array( 'availability_status' => 'active' ) );
		$checks['active_modules'] = array(
			'label'  => 'Aktif (available) Modül',
			'value'  => $active_av,
			'status' => 'info',
		);

		$updater     = new HAP_Profile_Updater();
		$upd_s       = $updater->get_settings();
		$upd_repo    = ! empty( $upd_s['repo'] );
		$last_update = get_option( 'hap_profile_last_update', '' );
		$last_sha    = substr( (string) get_option( 'hap_profile_last_update_sha', '' ), 0, 7 );
		$upd_label   = $upd_repo
			? ( $last_sha ? 'Yapılandırıldı — Son: ' . $last_sha . ( $last_update ? ' (' . $last_update . ')' : '' ) : 'Yapılandırıldı — Henüz güncelleme yapılmadı' )
			: 'Repo ayarlanmamış';
		$checks['updater'] = array(
			'label'  => 'GitHub Güncelleme',
			'value'  => $upd_label,
			'status' => $upd_repo ? 'ok' : 'warning',
		);

		$settings = get_option( 'hap_profile_settings', array() );
		$checks['noindex'] = array(
			'label'  => 'Profil Sayfası noindex',
			'value'  => ! empty( $settings['noindex_profiles'] ) ? 'Aktif' : 'Pasif',
			'status' => ! empty( $settings['noindex_profiles'] ) ? 'ok' : 'warning',
		);

		$shortcode_registered = shortcode_exists( 'hap_user_profile_dashboard' );
		$checks['shortcode'] = array(
			'label'  => 'Ana Shortcode [hap_user_profile_dashboard]',
			'value'  => $shortcode_registered ? 'Kayıtlı' : 'Kayıtlı Değil',
			'status' => $shortcode_registered ? 'ok' : 'error',
		);

		$memory_limit = ini_get( 'memory_limit' );
		$usage_bytes  = memory_get_usage( true );
		$usage_mb     = round( $usage_bytes / 1024 / 1024, 2 );
		$checks['memory'] = array(
			'label'  => 'Bellek Kullanımı',
			'value'  => $usage_mb . ' MB / Limit: ' . $memory_limit,
			'status' => $usage_mb < 64 ? 'ok' : 'warning',
		);

		return $checks;
	}


	public function render() {
		$checks = $this->run_checks();
		$icons  = array(
			'ok'      => '✅',
			'warning' => '⚠️',
			'error'   => '❌',
			'info'    => 'ℹ️',
		);
		echo '<div class="hap-health-checks">';
		echo '<table class="widefat striped"><thead><tr><th>Kontrol</th><th>Değer</th><th>Durum</th></tr></thead><tbody>';
		foreach ( $checks as $check ) {
			$icon = $icons[ $check['status'] ] ?? 'ℹ️';
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $check['label'] ),
				esc_html( $check['value'] ),
				$icon
			);
		}
		echo '</tbody></table></div>';
	}
}
