<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Updater {

	private $option_key           = 'hap_profile_updater_settings';
	private $notice_transient_key = 'hap_profile_update_notice';
	private $debug_option_key     = 'hap_profile_update_last_debug';
	private $error_option_key     = 'hap_profile_update_last_error';
	private $last_zip_http_code   = 0;

	/** Güncelleme sonrası varlığı zorunlu dosyalar */
	private $critical_files = array(
		'hesaplamaa-profile.php',
		'includes/class-hap-profile-plugin.php',
		'includes/class-hap-profile-admin.php',
		'includes/class-hap-profile-updater.php',
	);

	public function __construct() {
		add_action( 'admin_post_hap_update_from_github', array( $this, 'handle_update' ) );
		add_action( 'admin_post_hap_rollback_from_backup', array( $this, 'handle_rollback' ) );
		add_action( 'wp_ajax_hap_check_github_version', array( $this, 'ajax_check_version' ) );
	}

	public function get_settings() {
		return wp_parse_args(
			get_option( $this->option_key, array() ),
			array(
				'repo'   => '',
				'branch' => 'main',
			)
		);
	}

	public function save_settings( $data ) {
		update_option(
			$this->option_key,
			array(
				'repo'   => $this->sanitize_repo( $data['repo'] ?? '' ),
				'branch' => $this->sanitize_branch( $data['branch'] ?? 'main' ),
			)
		);
	}

	public function get_remote_version() {
		$settings = $this->get_settings();

		if ( empty( $settings['repo'] ) ) {
			return new WP_Error( 'missing_repo', 'GitHub repo ayarı eksik.' );
		}

		if ( ! $this->is_valid_repo( $settings['repo'] ) ) {
			return new WP_Error( 'invalid_repo', 'Repo formatı geçersiz. Beklenen: kullanici/repo-adi' );
		}

		if ( ! $this->is_valid_branch( $settings['branch'] ) ) {
			return new WP_Error( 'invalid_branch', 'Branch adı geçersiz.' );
		}

		$url  = "https://api.github.com/repos/{$settings['repo']}/commits/{$settings['branch']}";
		$args = array(
			'timeout' => 20,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'hesaplamaa-profile',
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'github_request_failed', 'GitHub bağlantısı kurulamadı: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : 'GitHub API yanıtı başarısız.';
			return new WP_Error( 'github_api_error', 'Sürüm bilgisi alınamadı: ' . $message );
		}

		if ( empty( $body['sha'] ) ) {
			return new WP_Error( 'github_bad_response', 'GitHub yanıtı beklenen formatta değil.' );
		}

		return $body['sha'];
	}

	public function handle_update() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'GitHub güncellemesi yapma yetkiniz yok.', 'hesaplamaa-profile' ),
				esc_html__( 'Yetkisiz işlem', 'hesaplamaa-profile' ),
				array( 'response' => 403 )
			);
		}

		if (
			! isset( $_POST['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'hap_update_from_github' )
		) {
			wp_die(
				esc_html__( 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.', 'hesaplamaa-profile' ),
				esc_html__( 'Geçersiz istek', 'hesaplamaa-profile' ),
				array( 'response' => 400 )
			);
		}

		$this->reset_update_state();

		$result = $this->download_and_install( $this->get_settings() );

		if ( true === $result ) {
			$this->set_update_notice( 'success', 'Eklenti GitHub üzerinden başarıyla güncellendi.' );
			wp_safe_redirect( admin_url( 'admin.php?page=hesaplamaa-profile&tab=updater&update=success' ) );
			exit;
		}

		$message = $result instanceof WP_Error ? $result->get_error_message() : (string) $result;
		$this->save_update_error( $message );
		$this->set_update_notice( 'error', $message );

		wp_safe_redirect( admin_url( 'admin.php?page=hesaplamaa-profile&tab=updater&update=error' ) );
		exit;
	}

	public function handle_rollback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Geri dönüş yapma yetkiniz yok.', 'hesaplamaa-profile' ),
				esc_html__( 'Yetkisiz işlem', 'hesaplamaa-profile' ),
				array( 'response' => 403 )
			);
		}

		if (
			! isset( $_POST['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'hap_rollback_from_backup' )
		) {
			wp_die(
				esc_html__( 'Güvenlik doğrulaması başarısız oldu.', 'hesaplamaa-profile' ),
				esc_html__( 'Geçersiz istek', 'hesaplamaa-profile' ),
				array( 'response' => 400 )
			);
		}

		$backup_path = (string) get_option( 'hap_profile_last_backup_path', '' );

		if ( ! $backup_path || ! is_dir( $backup_path ) ) {
			$this->set_update_notice( 'error', 'Geri dönüş için geçerli bir backup bulunamadı.' );
			wp_safe_redirect( admin_url( 'admin.php?page=hesaplamaa-profile&tab=updater&rollback=no_backup' ) );
			exit;
		}

		$result = $this->restore_from_backup( $backup_path, HAP_PLUGIN_DIR );
		$this->log_update_debug(
			array(
				'rollback_result' => is_wp_error( $result ) ? $result->get_error_message() : 'success',
				'rollback_source' => $backup_path,
			)
		);

		if ( true === $result ) {
			$this->flush_caches();
			$this->set_update_notice( 'success', 'Eklenti backup\'tan başarıyla geri yüklendi: ' . basename( $backup_path ) );
			wp_safe_redirect( admin_url( 'admin.php?page=hesaplamaa-profile&tab=updater&rollback=success' ) );
			exit;
		}

		$message = $result instanceof WP_Error ? $result->get_error_message() : 'Geri dönüş sırasında bilinmeyen bir hata oluştu.';
		$this->set_update_notice( 'error', 'Geri dönüş başarısız: ' . $message );
		wp_safe_redirect( admin_url( 'admin.php?page=hesaplamaa-profile&tab=updater&rollback=error' ) );
		exit;
	}

	public function ajax_check_version() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Yetkiniz yok.', 403 );
		}

		if ( ! check_ajax_referer( 'hap_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Güvenlik doğrulaması başarısız.', 400 );
		}

		$sha = $this->get_remote_version();

		if ( is_wp_error( $sha ) ) {
			wp_send_json_error( $sha->get_error_message() );
		}

		wp_send_json_success( array( 'sha' => substr( $sha, 0, 7 ) ) );
	}

	public function get_update_notice( $delete = true ) {
		$notice = get_transient( $this->notice_transient_key );
		if ( $delete ) {
			delete_transient( $this->notice_transient_key );
		}
		return is_array( $notice ) ? $notice : null;
	}

	public function get_last_update_error() {
		return get_option( $this->error_option_key, '' );
	}

	public function get_last_update_debug() {
		return get_option( $this->debug_option_key, array() );
	}

	private function download_and_install( $settings ) {
		if ( empty( $settings['repo'] ) ) {
			return new WP_Error( 'missing_repo', 'GitHub repo ayarı eksik.' );
		}

		if ( ! $this->is_valid_repo( $settings['repo'] ) ) {
			return new WP_Error( 'invalid_repo', 'Repo formatı geçersiz. Beklenen: kullanici/repo-adi' );
		}

		if ( ! $this->is_valid_branch( $settings['branch'] ) ) {
			return new WP_Error( 'invalid_branch', 'Branch adı geçersiz. Sadece harf, sayı, -, _ ve . kullanılabilir.' );
		}

		if ( ! function_exists( 'download_url' ) || ! function_exists( 'unzip_file' ) || ! function_exists( 'copy_dir' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$plugin_dir        = HAP_PLUGIN_DIR;
		$plugin_base       = dirname( $plugin_dir );
		$filesystem_method = function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : 'unknown';

		$this->log_update_debug(
			array(
				'step'              => '1_validate',
				'repo'              => $settings['repo'],
				'branch'            => $settings['branch'],
				'filesystem_method' => $filesystem_method,
				'dest_writable'     => is_writable( $plugin_dir ) ? 'yes' : 'no',
			)
		);

		$backup_path = $this->create_backup( $plugin_dir );
		$this->log_update_debug(
			array(
				'step'        => '2_backup',
				'backup_path' => is_wp_error( $backup_path ) ? $backup_path->get_error_message() : $backup_path,
			)
		);

		if ( is_wp_error( $backup_path ) ) {
			return $this->wrap_error( 'backup_failed', 'Backup oluşturulamadığı için güncelleme iptal edildi: ' . $backup_path->get_error_message() );
		}

		update_option( 'hap_profile_last_backup_path', $backup_path, false );

		$zip_url = "https://github.com/{$settings['repo']}/archive/refs/heads/{$settings['branch']}.zip";
		$args    = array(
			'timeout' => 60,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'hesaplamaa-profile',
			),
		);

		$tmp = $this->download_zip( $zip_url, $args );
		$this->log_update_debug(
			array(
				'step'          => '3_download',
				'zip_url'       => $zip_url,
				'zip_http_code' => (string) $this->last_zip_http_code,
				'tmp_created'   => ( ! is_wp_error( $tmp ) && ! empty( $tmp ) ) ? 'yes' : 'no',
			)
		);

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		global $wp_filesystem;
		WP_Filesystem();

		$this->log_update_debug(
			array(
				'step'                => '4_unzip',
				'wp_filesystem_class' => is_object( $wp_filesystem ) ? get_class( $wp_filesystem ) : 'unavailable',
			)
		);

		$unzip = unzip_file( $tmp, $plugin_base );
		@unlink( $tmp );

		$this->log_update_debug( array( 'unzip_result' => is_wp_error( $unzip ) ? $unzip->get_error_code() : 'success' ) );

		if ( is_wp_error( $unzip ) ) {
			return $this->wrap_error( 'github_unzip_failed', 'ZIP arşivi açılamadı: ' . $unzip->get_error_message() );
		}

		$repo_name     = basename( $settings['repo'] );
		$extracted_dir = $plugin_base . '/' . $repo_name . '-' . $settings['branch'];
		$package_root  = $this->locate_package_root( $extracted_dir );

		$this->log_update_debug(
			array(
				'step'                 => '5_locate',
				'extracted_dir'        => $extracted_dir,
				'extracted_dir_exists' => is_dir( $extracted_dir ) ? 'yes' : 'no',
				'package_root'         => $package_root ?: '',
			)
		);

		if ( ! is_dir( $extracted_dir ) ) {
			return $this->wrap_error( 'extracted_dir_missing', 'İndirilen paket açılamadı veya beklenen klasör bulunamadı.' );
		}

		if ( ! $package_root ) {
			$this->cleanup_directory( $extracted_dir );
			return $this->wrap_error( 'package_root_missing', 'İndirilen pakette hesaplamaa-profile.php bulunamadı.' );
		}

		$header_valid = $this->validate_plugin_header( trailingslashit( $package_root ) . 'hesaplamaa-profile.php' );
		$this->log_update_debug( array( 'step' => '6_header', 'header_valid' => $header_valid ? 'yes' : 'no' ) );

		if ( ! $header_valid ) {
			$this->cleanup_directory( $extracted_dir );
			return $this->wrap_error(
				'invalid_plugin_header',
				'İndirilen paketteki hesaplamaa-profile.php dosyasında geçerli plugin başlığı bulunamadı (Plugin Name veya Text Domain uyumsuz).'
			);
		}

		$copied = copy_dir( $package_root, $plugin_dir );
		$this->log_update_debug(
			array(
				'step'                => '7_copy',
				'copy_dir_result'     => is_wp_error( $copied ) ? $copied->get_error_code() : 'success',
				'copy_dir_message'    => is_wp_error( $copied ) ? $copied->get_error_message() : '',
				'plugin_dir'          => $plugin_dir,
				'plugin_dir_writable' => is_writable( $plugin_dir ) ? 'yes' : 'no',
			)
		);

		if ( is_wp_error( $copied ) && $this->should_use_native_copy_fallback( $filesystem_method, $plugin_dir ) ) {
			$copied = $this->native_recursive_copy( $package_root, $plugin_dir );
			$this->log_update_debug(
				array(
					'native_copy_fallback'         => is_wp_error( $copied ) ? $copied->get_error_code() : 'success',
					'native_copy_fallback_message' => is_wp_error( $copied ) ? $copied->get_error_message() : '',
				)
			);
		}

		$this->cleanup_directory( $extracted_dir );

		if ( is_wp_error( $copied ) ) {
			$rollback = $this->restore_from_backup( $backup_path, $plugin_dir );
			$this->log_update_debug(
				array(
					'step'            => '7_copy_failed_rollback',
					'rollback_result' => is_wp_error( $rollback ) ? $rollback->get_error_message() : 'success',
				)
			);
			return $this->wrap_error( 'copy_failed', 'Yeni dosyalar kopyalanamadı, eski sürüm geri yüklendi: ' . $copied->get_error_message() );
		}

		$syntax = $this->check_syntax( $plugin_dir );
		$this->log_update_debug(
			array(
				'step'          => '8_syntax',
				'syntax_result' => $syntax['ok'] ? 'ok' : 'error',
				'syntax_detail' => $syntax['detail'],
			)
		);

		$files_ok = $this->check_critical_files( $plugin_dir );
		$this->log_update_debug(
			array(
				'step'         => '9_critical_files',
				'files_result' => $files_ok ? 'ok' : 'missing',
			)
		);

		if ( ! $syntax['ok'] || ! $files_ok ) {
			$rollback     = $this->restore_from_backup( $backup_path, $plugin_dir );
			$rollback_msg = is_wp_error( $rollback ) ? $rollback->get_error_message() : 'başarılı';
			$this->log_update_debug( array( 'step' => '10_rollback', 'rollback_result' => $rollback_msg ) );

			if ( ! $syntax['ok'] ) {
				return $this->wrap_error( 'syntax_check_failed', 'PHP syntax hatası tespit edildi, eklenti eski sürüme geri döndürüldü. Detay: ' . $syntax['detail'] );
			}

			return $this->wrap_error( 'critical_files_missing', 'Güncelleme sonrası kritik dosyalar eksik, eklenti eski sürüme geri döndürüldü.' );
		}

		$remote_sha = $this->get_remote_version();
		update_option( 'hap_profile_last_update', current_time( 'mysql' ) );
		update_option( 'hap_profile_last_update_version', (string) time() );

		if ( $remote_sha && ! is_wp_error( $remote_sha ) ) {
			update_option( 'hap_profile_last_update_sha', $remote_sha );
		}

		$this->flush_caches();
		$this->log_update_debug( array( 'step' => '11_done', 'result' => 'success' ) );

		return true;
	}

	private function create_backup( $plugin_dir ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'uploads_dir_error', 'WordPress uploads dizini alınamadı.' );
		}

		$backup_base = trailingslashit( $uploads['basedir'] ) . 'hap-profile-backups';

		if ( ! is_dir( $backup_base ) ) {
			if ( ! wp_mkdir_p( $backup_base ) ) {
				return new WP_Error( 'backup_dir_create_failed', 'Backup ana klasörü oluşturulamadı: ' . $backup_base );
			}
			@file_put_contents( $backup_base . '/.htaccess', "Deny from all\n" );
		}

		if ( ! is_writable( $backup_base ) ) {
			return new WP_Error( 'backup_dir_not_writable', 'Backup klasörü yazılabilir değil: ' . $backup_base );
		}

		$folder_name = 'hesaplamaa-profile-backup-' . gmdate( 'Ymd-His' );
		$backup_path = trailingslashit( $backup_base ) . $folder_name;

		if ( ! wp_mkdir_p( $backup_path ) ) {
			return new WP_Error( 'backup_subdir_failed', 'Backup alt klasörü oluşturulamadı.' );
		}

		$copied = $this->native_recursive_copy( $plugin_dir, $backup_path );
		if ( is_wp_error( $copied ) ) {
			$this->cleanup_directory( $backup_path );
			return new WP_Error( 'backup_copy_failed', 'Backup kopyalanamadı: ' . $copied->get_error_message() );
		}

		return $backup_path;
	}

	private function restore_from_backup( $backup_path, $plugin_dir ) {
		if ( ! is_dir( $backup_path ) ) {
			return new WP_Error( 'backup_not_found', 'Backup klasörü bulunamadı: ' . $backup_path );
		}

		return $this->native_recursive_copy( $backup_path, $plugin_dir );
	}

	private function validate_plugin_header( $plugin_file ) {
		if ( ! is_file( $plugin_file ) ) {
			return false;
		}

		$content = file_get_contents( $plugin_file, false, null, 0, 4096 );
		if ( ! $content ) {
			return false;
		}

		if ( preg_match( '/^[\s\*]*Plugin Name\s*:\s*(.+)$/mi', $content, $matches ) ) {
			if ( false !== stripos( $matches[1], 'hesaplamaa' ) ) {
				return true;
			}
		}

		if ( preg_match( '/^[\s\*]*Text Domain\s*:\s*(.+)$/mi', $content, $matches ) ) {
			if ( 'hesaplamaa-profile' === strtolower( trim( $matches[1] ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function check_syntax( $plugin_dir ) {
		$result = array(
			'ok'     => true,
			'detail' => '',
		);

		$shell_available = function_exists( 'shell_exec' ) && is_callable( 'shell_exec' );
		if ( $shell_available ) {
			$disabled        = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
			$shell_available = ! in_array( 'shell_exec', $disabled, true );
		}

		if ( ! $shell_available ) {
			$result['detail'] = 'shell_exec kullanılamıyor; syntax kontrolü atlandı.';
			update_option( 'hap_profile_syntax_check_skipped', 1, false );
			return $result;
		}

		$files_to_check = array(
			trailingslashit( $plugin_dir ) . 'hesaplamaa-profile.php',
		);

		$includes = glob( trailingslashit( $plugin_dir ) . 'includes/*.php' );
		if ( is_array( $includes ) ) {
			$files_to_check = array_merge( $files_to_check, $includes );
		}

		update_option( 'hap_profile_syntax_check_skipped', 0, false );

		foreach ( $files_to_check as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			$output = @shell_exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1' );
			if ( $output && false === strpos( $output, 'No syntax errors' ) ) {
				$result['ok']     = false;
				$result['detail'] = basename( $file ) . ': ' . trim( $output );
				return $result;
			}
		}

		$result['detail'] = 'Tüm dosyalar syntax kontrolünden geçti.';
		return $result;
	}

	private function check_critical_files( $plugin_dir ) {
		foreach ( $this->critical_files as $relative_path ) {
			if ( ! is_file( trailingslashit( $plugin_dir ) . $relative_path ) ) {
				return false;
			}
		}
		return true;
	}

	private function flush_caches() {
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}
	}

	private function locate_package_root( $extracted_dir ) {
		$required_file = trailingslashit( $extracted_dir ) . 'hesaplamaa-profile.php';

		if ( is_file( $required_file ) ) {
			return $extracted_dir;
		}

		foreach ( glob( trailingslashit( $extracted_dir ) . '*', GLOB_ONLYDIR ) as $candidate ) {
			if ( is_file( trailingslashit( $candidate ) . 'hesaplamaa-profile.php' ) ) {
				return $candidate;
			}
		}

		return '';
	}

	private function download_zip( $url, $args ) {
		$tmp = wp_tempnam( $url );

		if ( ! $tmp ) {
			return new WP_Error( 'tmp_file_failed', 'Geçici indirme dosyası oluşturulamadı.' );
		}

		$args['stream']   = true;
		$args['filename'] = $tmp;

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->last_zip_http_code = 0;
			@unlink( $tmp );
			return $response;
		}

		$code                     = wp_remote_retrieve_response_code( $response );
		$this->last_zip_http_code = (int) $code;

		if ( $code < 200 || $code >= 300 ) {
			@unlink( $tmp );
			return new WP_Error( 'github_zip_download_failed', 'GitHub ZIP indirilemedi. HTTP ' . $code . '. Repo ve branch ayarlarını kontrol edin.' );
		}

		return $tmp;
	}

	private function sanitize_repo( $repo ) {
		$repo = sanitize_text_field( wp_unslash( $repo ) );
		$repo = trim( $repo, " \t\n\r\0\x0B/" );
		$repo = preg_replace( '/[?#].+$/', '', $repo );
		$repo = preg_replace( '#^https?://[^/]+/#i', '', $repo );
		return $repo;
	}

	private function sanitize_branch( $branch ) {
		$branch = sanitize_text_field( wp_unslash( $branch ) );
		$branch = trim( $branch );
		$branch = preg_replace( '/[^A-Za-z0-9._-]/', '', $branch );
		return $branch ?: 'main';
	}

	private function is_valid_repo( $repo ) {
		return (bool) preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repo );
	}

	private function is_valid_branch( $branch ) {
		return (bool) preg_match( '/^[A-Za-z0-9._-]+$/', $branch );
	}

	private function should_use_native_copy_fallback( $filesystem_method, $dest ) {
		unset( $filesystem_method );
		return is_dir( $dest ) && is_writable( $dest );
	}

	private function native_recursive_copy( $source, $destination ) {
		if ( ! is_dir( $source ) ) {
			return new WP_Error( 'native_copy_source_missing', 'Kopyalama kaynağı bulunamadı: ' . $source );
		}

		if ( ! is_dir( $destination ) || ! is_writable( $destination ) ) {
			return new WP_Error( 'native_copy_destination_unwritable', 'Hedef klasör yazılabilir değil: ' . $destination );
		}

		$items = scandir( $source );
		if ( false === $items ) {
			return new WP_Error( 'native_copy_scandir_failed', 'Kaynak klasör okunamadı: ' . $source );
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$from = trailingslashit( $source ) . $item;
			$to   = trailingslashit( $destination ) . $item;

			if ( is_dir( $from ) ) {
				if ( file_exists( $to ) && ! is_dir( $to ) ) {
					if ( ! @unlink( $to ) ) {
						return new WP_Error( 'native_copy_conflict_failed', 'Hedefteki çakışan dosya silinemedi: ' . $to );
					}
				}
				if ( ! is_dir( $to ) && ! wp_mkdir_p( $to ) ) {
					return new WP_Error( 'native_copy_mkdir_failed', 'Hedef klasör oluşturulamadı: ' . $to );
				}
				$copied = $this->native_recursive_copy( $from, $to );
				if ( is_wp_error( $copied ) ) {
					return $copied;
				}
				continue;
			}

			if ( file_exists( $to ) ) {
				@chmod( $to, 0644 );
				if ( ! is_writable( $to ) ) {
					@unlink( $to );
				}
			}

			if ( file_exists( $to ) && ! is_writable( $to ) ) {
				return new WP_Error( 'native_copy_target_locked', 'Hedef dosya yazılabilir değil: ' . $to );
			}

			if ( ! @copy( $from, $to ) ) {
				$error = error_get_last();
				return new WP_Error(
					'native_copy_file_failed',
					'Dosya kopyalanamadı: ' . $from . ' -> ' . $to . ( ! empty( $error['message'] ) ? ' | ' . $error['message'] : '' )
				);
			}
		}

		return true;
	}

	private function cleanup_directory( $path ) {
		global $wp_filesystem;

		if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'delete' ) ) {
			if ( $wp_filesystem->delete( $path, true ) ) {
				return true;
			}
		}

		return $this->native_recursive_delete( $path );
	}

	private function native_recursive_delete( $path ) {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		if ( is_file( $path ) || is_link( $path ) ) {
			return @unlink( $path );
		}

		$items = scandir( $path );
		if ( false === $items ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			if ( ! $this->native_recursive_delete( trailingslashit( $path ) . $item ) ) {
				return false;
			}
		}

		return @rmdir( $path );
	}

	private function reset_update_state() {
		delete_transient( $this->notice_transient_key );
		delete_option( $this->debug_option_key );
		delete_option( $this->error_option_key );
		$this->last_zip_http_code = 0;
	}

	private function set_update_notice( $type, $message ) {
		set_transient(
			$this->notice_transient_key,
			array(
				'type'    => $type,
				'message' => wp_strip_all_tags( (string) $message ),
				'time'    => current_time( 'mysql' ),
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	private function save_update_error( $message ) {
		update_option( $this->error_option_key, wp_strip_all_tags( (string) $message ), false );
	}

	private function log_update_debug( $data ) {
		$debug = get_option( $this->debug_option_key, array() );
		if ( ! is_array( $debug ) ) {
			$debug = array();
		}
		foreach ( (array) $data as $key => $value ) {
			$debug[ $key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		}
		update_option( $this->debug_option_key, $debug, false );
		error_log( 'HAP_Profile_Updater: ' . wp_json_encode( $debug ) );
	}

	private function wrap_error( $code, $message ) {
		$sanitized = wp_strip_all_tags( (string) $message );
		$this->save_update_error( $sanitized );
		return new WP_Error( $code, $sanitized );
	}

	public static function get_version_string() {
		$base_version    = HAP_VERSION;
		$last_sha        = substr( (string) get_option( 'hap_profile_last_update_sha', '' ), 0, 7 );
		$last_update_ver = (string) get_option( 'hap_profile_last_update_version', '' );

		if ( $last_update_ver ) {
			return $base_version . '-' . $last_update_ver . ( $last_sha ? '-' . $last_sha : '' );
		}

		return $base_version;
	}
}
