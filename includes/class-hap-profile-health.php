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

		// — Temel sürüm bilgileri —

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

		// — Dosya sistemi —

		$main_file = HAP_PLUGIN_DIR . 'hesaplamaa-profile.php';
		$checks['main_plugin_file'] = array(
			'label'  => 'Ana Plugin Dosyası (hesaplamaa-profile.php)',
			'value'  => is_file( $main_file ) ? 'Mevcut' : 'Eksik',
			'status' => is_file( $main_file ) ? 'ok' : 'error',
		);

		$critical_files = array(
			'includes/class-hap-profile-plugin.php',
			'includes/class-hap-profile-admin.php',
			'includes/class-hap-profile-updater.php',
		);
		$missing = array();
		foreach ( $critical_files as $rel ) {
			if ( ! is_file( HAP_PLUGIN_DIR . $rel ) ) {
				$missing[] = $rel;
			}
		}
		$checks['critical_class_files'] = array(
			'label'  => 'Kritik Class Dosyaları',
			'value'  => empty( $missing ) ? 'Tümü mevcut' : 'Eksik: ' . implode( ', ', $missing ),
			'status' => empty( $missing ) ? 'ok' : 'error',
		);

		// — Updater / Backup —

		$updater  = new HAP_Profile_Updater();
		$upd_s    = $updater->get_settings();
		$upd_repo = ! empty( $upd_s['repo'] );
		$upd_branch = ! empty( $upd_s['branch'] ) ? $upd_s['branch'] : 'main';

		$repo_valid   = $upd_repo && (bool) preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $upd_s['repo'] );
		$branch_valid = (bool) preg_match( '/^[A-Za-z0-9._-]+$/', $upd_branch );

		$checks['updater_repo'] = array(
			'label'  => 'GitHub Updater — Repo',
			'value'  => $upd_repo ? ( $repo_valid ? $upd_s['repo'] . ' (geçerli)' : $upd_s['repo'] . ' (geçersiz format)' ) : 'Ayarlanmamış',
			'status' => $repo_valid ? 'ok' : ( $upd_repo ? 'error' : 'warning' ),
		);

		$checks['updater_branch'] = array(
			'label'  => 'GitHub Updater — Branch',
			'value'  => $upd_branch . ( $branch_valid ? ' (geçerli)' : ' (geçersiz)' ),
			'status' => $branch_valid ? 'ok' : 'error',
		);

		$last_sha    = (string) get_option( 'hap_profile_last_update_sha', '' );
		$last_update = (string) get_option( 'hap_profile_last_update', '' );
		$upd_label   = $upd_repo
			? ( $last_sha
				? 'Son: ' . substr( $last_sha, 0, 7 ) . ( $last_update ? ' (' . $last_update . ')' : '' )
				: 'Henüz güncelleme yapılmadı' )
			: 'Repo ayarlanmamış';
		$checks['updater_last'] = array(
			'label'  => 'GitHub Son Güncelleme',
			'value'  => $upd_label,
			'status' => $upd_repo ? 'ok' : 'warning',
		);

		// Backup klasörü
		$uploads     = wp_upload_dir();
		$backup_base = ! empty( $uploads['basedir'] ) ? trailingslashit( $uploads['basedir'] ) . 'hap-profile-backups' : '';
		$backup_writable = $backup_base && is_dir( $backup_base ) ? is_writable( $backup_base ) : ( $backup_base && is_writable( $uploads['basedir'] ) );
		$checks['backup_dir_writable'] = array(
			'label'  => 'Backup Klasörü Yazılabilir',
			'value'  => $backup_writable ? 'Evet' : 'Hayır',
			'status' => $backup_writable ? 'ok' : 'warning',
		);

		$last_backup = (string) get_option( 'hap_profile_last_backup_path', '' );
		$checks['last_backup'] = array(
			'label'  => 'Son Backup',
			'value'  => $last_backup ? ( is_dir( $last_backup ) ? basename( $last_backup ) . ' (mevcut)' : basename( $last_backup ) . ' (bulunamadı)' ) : 'Backup yok',
			'status' => $last_backup ? ( is_dir( $last_backup ) ? 'ok' : 'warning' ) : 'info',
		);

		// — PHP yetenekleri —

		$shell_exec_available = function_exists( 'shell_exec' ) && is_callable( 'shell_exec' );
		if ( $shell_exec_available ) {
			$disabled_fns     = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
			$shell_exec_available = ! in_array( 'shell_exec', $disabled_fns, true );
		}
		$syntax_skipped = (bool) get_option( 'hap_profile_syntax_check_skipped', 0 );
		$checks['shell_exec'] = array(
			'label'  => 'shell_exec (PHP Syntax Kontrolü)',
			'value'  => $shell_exec_available ? 'Kullanılabilir' : ( $syntax_skipped ? 'Kullanılamıyor — syntax kontrolü atlandı' : 'Kullanılamıyor' ),
			'status' => $shell_exec_available ? 'ok' : 'warning',
		);

		$unzip_available = function_exists( 'unzip_file' );
		if ( ! $unzip_available ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$unzip_available = function_exists( 'unzip_file' );
		}
		$checks['unzip_file'] = array(
			'label'  => 'unzip_file (WP fonksiyonu)',
			'value'  => $unzip_available ? 'Kullanılabilir' : 'Kullanılamıyor',
			'status' => $unzip_available ? 'ok' : 'error',
		);

		// WP_Filesystem
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$fs_method = function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : 'unknown';
		$checks['wp_filesystem'] = array(
			'label'  => 'WP_Filesystem Yöntemi',
			'value'  => $fs_method,
			'status' => in_array( $fs_method, array( 'direct', 'ssh2', 'ftpext' ), true ) ? 'ok' : 'warning',
		);

		// — Veritabanı tabloları —

		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		$table_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $modules_table ) ) === $modules_table;
		$checks['modules_table'] = array(
			'label'  => 'Modül Tablosu (wp_hap_profile_modules)',
			'value'  => $table_exists ? 'Mevcut' : 'Eksik',
			'status' => $table_exists ? 'ok' : 'error',
		);

		$shares_table  = $wpdb->prefix . HAP_TABLE_SHARES;
		$table2_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shares_table ) ) === $shares_table;
		$checks['shares_table'] = array(
			'label'  => 'Paylaşım Tablosu (wp_hap_profile_shares)',
			'value'  => $table2_exists ? 'Mevcut' : 'Eksik',
			'status' => $table2_exists ? 'ok' : 'error',
		);

		// — Shortcode'lar —

		$checks['shortcode_dashboard'] = array(
			'label'  => 'Shortcode [hap_user_profile_dashboard]',
			'value'  => shortcode_exists( 'hap_user_profile_dashboard' ) ? 'Kayıtlı' : 'Kayıtlı Değil',
			'status' => shortcode_exists( 'hap_user_profile_dashboard' ) ? 'ok' : 'error',
		);

		$checks['shortcode_register'] = array(
			'label'  => 'Auth Shortcode [hap_profile_register]',
			'value'  => shortcode_exists( 'hap_profile_register' ) ? 'Kayıtlı' : 'Kayıtlı Değil',
			'status' => shortcode_exists( 'hap_profile_register' ) ? 'ok' : 'warning',
		);

		$checks['shortcode_login'] = array(
			'label'  => 'Auth Shortcode [hap_profile_login]',
			'value'  => shortcode_exists( 'hap_profile_login' ) ? 'Kayıtlı' : 'Kayıtlı Değil',
			'status' => shortcode_exists( 'hap_profile_login' ) ? 'ok' : 'warning',
		);

		// — Modül istatistikleri —

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

		// — Genel ayarlar —

		$settings = get_option( 'hap_profile_settings', array() );
		$checks['noindex'] = array(
			'label'  => 'Profil Sayfası noindex',
			'value'  => ! empty( $settings['noindex_profiles'] ) ? 'Aktif' : 'Pasif',
			'status' => ! empty( $settings['noindex_profiles'] ) ? 'ok' : 'warning',
		);

		$memory_limit = ini_get( 'memory_limit' );
		$usage_mb     = round( memory_get_usage( true ) / 1024 / 1024, 2 );
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
			'ok'      => '&#x2705;',
			'warning' => '&#x26A0;&#xFE0F;',
			'error'   => '&#x274C;',
			'info'    => '&#x2139;&#xFE0F;',
		);
		echo '<div class="hap-health-checks">';
		echo '<table class="widefat striped"><thead><tr><th>Kontrol</th><th>Değer</th><th>Durum</th></tr></thead><tbody>';
		foreach ( $checks as $check ) {
			$icon = $icons[ $check['status'] ] ?? '&#x2139;&#xFE0F;';
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
