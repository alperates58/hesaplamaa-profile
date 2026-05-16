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

	public function __construct() {
		add_action( 'admin_post_hap_update_from_github', array( $this, 'handle_update' ) );
		add_action( 'wp_ajax_hap_check_github_version',  array( $this, 'ajax_check_version' ) );
	}

	public function get_settings() {
		return wp_parse_args( get_option( $this->option_key, array() ), array(
			'repo'   => '',
			'branch' => 'main',
			'token'  => '',
		) );
	}

	public function save_settings( $data ) {
		update_option( $this->option_key, array(
			'repo'   => $this->sanitize_repo( $data['repo'] ?? '' ),
			'branch' => sanitize_text_field( $data['branch'] ?? 'main' ),
			'token'  => sanitize_text_field( $data['token'] ?? '' ),
		) );
	}

	public function get_remote_version() {
		$s = $this->get_settings();

		if ( empty( $s['repo'] ) ) {
			return new WP_Error( 'missing_repo', 'GitHub repo ayarı eksik.' );
		}

		if ( ! $this->is_valid_repo( $s['repo'] ) ) {
			return new WP_Error( 'invalid_repo', 'Repo formatı geçersiz. Örnek: kullanici/hesaplamaa-profile' );
		}

		$url  = "https://api.github.com/repos/{$s['repo']}/commits/{$s['branch']}";
		$args = array(
			'timeout' => 20,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'hesaplamaa-profile',
			),
		);

		if ( ! empty( $s['token'] ) ) {
			$args['headers']['Authorization'] = 'token ' . $s['token'];
		}

		$resp = wp_remote_get( $url, $args );

		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'github_request_failed', 'GitHub bağlantısı kurulamadı: ' . $resp->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : 'GitHub API yanıtı başarısız.';
			return new WP_Error( 'github_api_error', 'Sürüm bilgisi alınamadı: ' . $msg );
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

		$s      = $this->get_settings();
		$result = $this->download_and_install( $s );

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

	/* -------------------------------------------------------
	   DOWNLOAD & INSTALL
	   ------------------------------------------------------- */

	private function download_and_install( $s ) {
		if ( empty( $s['repo'] ) ) {
			return new WP_Error( 'missing_repo', 'GitHub repo ayarı eksik.' );
		}

		if ( ! $this->is_valid_repo( $s['repo'] ) ) {
			return new WP_Error( 'invalid_repo', 'Repo formatı geçersiz. Örnek: kullanici/hesaplamaa-profile' );
		}

		if ( ! function_exists( 'download_url' ) || ! function_exists( 'unzip_file' ) || ! function_exists( 'copy_dir' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$zip_url = "https://github.com/{$s['repo']}/archive/refs/heads/{$s['branch']}.zip";
		$args    = array(
			'timeout' => 60,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'hesaplamaa-profile',
			),
		);

		if ( ! empty( $s['token'] ) ) {
			$args['headers']['Authorization'] = 'token ' . $s['token'];
		}

		$plugin_base       = dirname( HAP_PLUGIN_DIR );
		$dest              = HAP_PLUGIN_DIR;
		$filesystem_method = function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : 'unknown';

		$this->log_update_debug( array(
			'repo'              => $s['repo'],
			'branch'            => $s['branch'],
			'zip_url'           => $zip_url,
			'filesystem_method' => $filesystem_method,
			'dest_writable'     => is_writable( $dest ) ? 'yes' : 'no',
			'plugin_base'       => $plugin_base,
		) );

		$tmp = $this->download_zip( $zip_url, $args );
		$this->log_update_debug( array(
			'tmp_created'       => ( ! is_wp_error( $tmp ) && ! empty( $tmp ) ) ? 'yes' : 'no',
			'zip_download_http' => (string) $this->last_zip_http_code,
		) );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		global $wp_filesystem;
		WP_Filesystem();

		$this->log_update_debug( array(
			'wp_filesystem_class' => is_object( $wp_filesystem ) ? get_class( $wp_filesystem ) : 'unavailable',
		) );

		$unzip = unzip_file( $tmp, $plugin_base );
		@unlink( $tmp );

		$this->log_update_debug( array( 'unzip_result' => is_wp_error( $unzip ) ? $unzip->get_error_code() : 'success' ) );

		if ( is_wp_error( $unzip ) ) {
			return $this->wrap_error( 'github_unzip_failed', 'ZIP arşivi açılamadı: ' . $unzip->get_error_message() );
		}

		$repo_name     = basename( $s['repo'] );
		$extracted_dir = $plugin_base . '/' . $repo_name . '-' . $s['branch'];
		$package_root  = $this->locate_package_root( $extracted_dir );

		$this->log_update_debug( array(
			'extracted_dir'        => $extracted_dir,
			'extracted_dir_exists' => is_dir( $extracted_dir ) ? 'yes' : 'no',
			'package_root'         => $package_root ? $package_root : '',
		) );

		if ( ! is_dir( $extracted_dir ) ) {
			return $this->wrap_error( 'github_extracted_dir_missing', 'İndirilen paket açılamadı veya beklenen klasör bulunamadı.' );
		}

		if ( ! $package_root ) {
			$this->cleanup_directory( $extracted_dir );
			return $this->wrap_error( 'github_package_root_missing', 'İndirilen pakette eklenti kökü bulunamadı. ZIP içeriğinde hesaplamaa-profile.php dosyası doğrulanamadı.' );
		}

		$copied = copy_dir( $package_root, $dest );
		$this->log_update_debug( array( 'copy_dir_result' => is_wp_error( $copied ) ? $copied->get_error_code() : 'success' ) );

		if ( is_wp_error( $copied ) && $this->should_use_native_copy_fallback( $filesystem_method, $dest ) ) {
			$copied = $this->native_recursive_copy( $package_root, $dest );
			$this->log_update_debug( array( 'native_copy_fallback' => is_wp_error( $copied ) ? $copied->get_error_code() : 'success' ) );
		}

		$this->cleanup_directory( $extracted_dir );

		if ( is_wp_error( $copied ) ) {
			return $this->wrap_error( 'github_copy_failed', 'Yeni eklenti dosyaları kopyalanamadı: ' . $copied->get_error_message() );
		}

		$remote_sha = $this->get_remote_version();

		update_option( 'hap_profile_last_update', current_time( 'mysql' ) );
		update_option( 'hap_profile_last_update_version', (string) time() );

		if ( $remote_sha && ! is_wp_error( $remote_sha ) ) {
			update_option( 'hap_profile_last_update_sha', $remote_sha );
		}

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		return true;
	}

	/* -------------------------------------------------------
	   YARDIMCILAR
	   ------------------------------------------------------- */

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

		$resp = wp_remote_get( $url, $args );

		if ( is_wp_error( $resp ) ) {
			$this->last_zip_http_code = 0;
			@unlink( $tmp );
			return $resp;
		}

		$code                     = wp_remote_retrieve_response_code( $resp );
		$this->last_zip_http_code = (int) $code;

		if ( $code < 200 || $code >= 300 ) {
			@unlink( $tmp );
			return new WP_Error(
				'github_zip_download_failed',
				'GitHub ZIP indirilemedi. HTTP ' . $code . '. Repo, branch veya token ayarlarını kontrol edin.'
			);
		}

		return $tmp;
	}

	private function sanitize_repo( $repo ) {
		$repo = sanitize_text_field( wp_unslash( $repo ) );
		$repo = trim( $repo, " \t\n\r\0\x0B/" );
		return $repo;
	}

	private function is_valid_repo( $repo ) {
		return (bool) preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repo );
	}

	private function should_use_native_copy_fallback( $filesystem_method, $dest ) {
		return 'ftpsockets' === $filesystem_method && is_dir( $dest ) && is_writable( $dest );
	}

	private function native_recursive_copy( $source, $destination ) {
		if ( ! is_dir( $source ) ) {
			return new WP_Error( 'native_copy_source_missing', 'Kopyalama kaynağı bulunamadı.' );
		}

		if ( ! is_dir( $destination ) || ! is_writable( $destination ) ) {
			return new WP_Error( 'native_copy_destination_unwritable', 'Hedef eklenti klasörü yazılabilir değil.' );
		}

		$items = scandir( $source );
		if ( false === $items ) {
			return new WP_Error( 'native_copy_scandir_failed', 'Kaynak klasör okunamadı.' );
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$from = trailingslashit( $source ) . $item;
			$to   = trailingslashit( $destination ) . $item;

			if ( is_dir( $from ) ) {
				if ( ! is_dir( $to ) && ! wp_mkdir_p( $to ) ) {
					return new WP_Error( 'native_copy_mkdir_failed', 'Hedef klasör oluşturulamadı: ' . $item );
				}
				$copied = $this->native_recursive_copy( $from, $to );
				if ( is_wp_error( $copied ) ) {
					return $copied;
				}
				continue;
			}

			if ( ! @copy( $from, $to ) ) {
				return new WP_Error( 'native_copy_file_failed', 'Dosya kopyalanamadı: ' . $item );
			}
		}

		return true;
	}

	private function cleanup_directory( $path ) {
		global $wp_filesystem;

		if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'delete' ) ) {
			$deleted = $wp_filesystem->delete( $path, true );
			if ( $deleted ) {
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

	/* -------------------------------------------------------
	   STATİK YARDIMCILAR (plugin.php'den çağrılır)
	   ------------------------------------------------------- */

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
