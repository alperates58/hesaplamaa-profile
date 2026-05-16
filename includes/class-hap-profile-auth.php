<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth altyapısı — Faz 1 iskeleti.
 *
 * Gerçek Google OAuth ve Turnstile entegrasyonu bu sınıfa eklenir;
 * bu fazda yalnızca hook noktaları, davranış ayarları ve shortcode iskeletleri bulunur.
 * Hiçbir secret / API key saklanmaz veya hardcode edilmez.
 */
class HAP_Profile_Auth {

	private function settings() {
		return wp_parse_args(
			get_option( 'hap_profile_auth_settings', array() ),
			self::defaults()
		);
	}

	public static function defaults() {
		return array(
			'enable_profile_registration_page'  => 0,
			'profile_register_page_id'           => 0,
			'profile_login_page_id'              => 0,
			'redirect_after_login_page_id'       => 0,
			'redirect_after_register_page_id'    => 0,
			'enable_google_login_hint'           => 0,
			'enable_turnstile_hint'              => 0,
			'block_wp_admin_for_subscribers'     => 0,
			'hide_admin_bar_for_subscribers'     => 0,
			'require_email_verification'         => 0,
			'rate_limit_registration_enabled'    => 0,
			'rate_limit_login_enabled'           => 0,
		);
	}

	public function register_hooks() {
		add_shortcode( 'hap_profile_register', array( $this, 'shortcode_register' ) );
		add_shortcode( 'hap_profile_login', array( $this, 'shortcode_login' ) );

		add_action( 'init', array( $this, 'maybe_block_admin_for_subscribers' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );
		add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );
		add_action( 'user_register', array( $this, 'redirect_after_register' ), 10, 1 );

		/**
		 * Hook: hap_before_register_form
		 * Ileride Google OAuth / sosyal giriş butonu buraya eklenir.
		 *
		 * Hook: hap_after_register_form
		 * Hook: hap_before_login_form
		 * Hook: hap_after_login_form
		 * Hook: hap_turnstile_field
		 *   Turnstile widget entegrasyonu buraya eklenir.
		 * Hook: hap_google_login_button
		 *   Google ile giriş butonu buraya eklenir.
		 */
	}

	/* -------------------------------------------------------
	   SHORTCODES
	   ------------------------------------------------------- */

	public function shortcode_register( $atts ) {
		$settings = $this->settings();

		if ( is_user_logged_in() ) {
			$page_id = absint( $settings['redirect_after_login_page_id'] );
			$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
			return '<p class="hap-notice">' .
				sprintf(
					'Zaten giriş yapıldı. <a href="%s">Profilime git</a>.',
					esc_url( $url )
				) .
			'</p>';
		}

		ob_start();
		include HAP_PLUGIN_DIR . 'templates/auth-register.php';
		return ob_get_clean();
	}

	public function shortcode_login( $atts ) {
		$settings = $this->settings();

		if ( is_user_logged_in() ) {
			$page_id = absint( $settings['redirect_after_login_page_id'] );
			$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
			return '<p class="hap-notice">' .
				sprintf(
					'Zaten giriş yapıldı. <a href="%s">Profilime git</a>.',
					esc_url( $url )
				) .
			'</p>';
		}

		ob_start();
		include HAP_PLUGIN_DIR . 'templates/auth-login.php';
		return ob_get_clean();
	}

	/* -------------------------------------------------------
	   DAVRANIŞLAR
	   ------------------------------------------------------- */

	public function maybe_block_admin_for_subscribers() {
		if ( ! is_admin() || wp_doing_ajax() || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$settings = $this->settings();
		if ( empty( $settings['block_wp_admin_for_subscribers'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$page_id = absint( $settings['profile_login_page_id'] );
		$redirect = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		/**
		 * Filtre: hap_block_admin_redirect_url
		 * wp-admin'den yönlendirme URL'ini özelleştir.
		 */
		$redirect = apply_filters( 'hap_block_admin_redirect_url', $redirect );

		wp_safe_redirect( $redirect );
		exit;
	}

	public function maybe_hide_admin_bar( $show ) {
		if ( ! is_user_logged_in() || current_user_can( 'edit_posts' ) ) {
			return $show;
		}
		$settings = $this->settings();
		if ( ! empty( $settings['hide_admin_bar_for_subscribers'] ) ) {
			return false;
		}
		return $show;
	}

	public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! is_a( $user, 'WP_User' ) ) {
			return $redirect_to;
		}
		if ( $user->has_cap( 'manage_options' ) ) {
			return $redirect_to;
		}

		$settings = $this->settings();
		$page_id  = absint( $settings['redirect_after_login_page_id'] );
		if ( ! $page_id ) {
			return $redirect_to;
		}

		/**
		 * Filtre: hap_login_redirect_url
		 * Giriş sonrası yönlendirme URL'ini özelleştir.
		 *
		 * @param string  $url     Varsayılan yönlendirme URL'i
		 * @param WP_User $user    Giriş yapan kullanıcı
		 */
		return apply_filters( 'hap_login_redirect_url', get_permalink( $page_id ), $user );
	}

	public function redirect_after_register( $user_id ) {
		$settings = $this->settings();
		$page_id  = absint( $settings['redirect_after_register_page_id'] );
		if ( ! $page_id ) {
			return;
		}

		/**
		 * Filtre: hap_register_redirect_url
		 * Kayıt sonrası yönlendirme URL'ini özelleştir.
		 *
		 * @param string $url     Yönlendirme URL'i
		 * @param int    $user_id Yeni kullanıcı ID
		 */
		$url = apply_filters( 'hap_register_redirect_url', get_permalink( $page_id ), $user_id );

		/*
		 * user_register hook'u sonrası header zaten gönderilmiş olabilir;
		 * yönlendirmeyi kayıt işlemi tamamlandıktan sonraki istekte yapmak
		 * için transient kullanıyoruz.
		 */
		set_transient( 'hap_register_redirect_' . $user_id, $url, 60 );
	}

	/**
	 * Kullanıcı kayıt sonrası ilk init'te transient yönlendirmesini işle.
	 * Bu metod register_hooks() içindeki init hook'una bağlı değil;
	 * plugin çalıştığında ayrıca çağrılır.
	 */
	public function maybe_do_register_redirect() {
		if ( ! is_user_logged_in() || is_admin() ) {
			return;
		}
		$user_id  = get_current_user_id();
		$redirect = get_transient( 'hap_register_redirect_' . $user_id );
		if ( $redirect ) {
			delete_transient( 'hap_register_redirect_' . $user_id );
			wp_safe_redirect( esc_url_raw( $redirect ) );
			exit;
		}
	}

	/* -------------------------------------------------------
	   RATE LIMIT YARDIMCISI (Faz 2 için hazır iskelet)
	   ------------------------------------------------------- */

	/**
	 * IP başına işlem sayısını transient ile izle.
	 * Gerçek uygulama Faz 2'de tamamlanır.
	 *
	 * @param string $action   'register' | 'login'
	 * @param int    $limit    İzin verilen maksimum istek sayısı
	 * @param int    $window   Saniye cinsinden pencere
	 * @return bool            Limit aşıldıysa true
	 */
	public function is_rate_limited( $action, $limit = 5, $window = 300 ) {
		$settings = $this->settings();

		$setting_key = 'rate_limit_' . $action . '_enabled';
		if ( empty( $settings[ $setting_key ] ) ) {
			return false;
		}

		$ip  = $this->get_client_ip();
		$key = 'hap_rl_' . $action . '_' . md5( $ip );

		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return true;
		}
		set_transient( $key, $count + 1, $window );
		return false;
	}

	private function get_client_ip() {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$ip = trim( explode( ',', $_SERVER[ $k ] )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}
