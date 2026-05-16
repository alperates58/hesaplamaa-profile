<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Render {

	private $fields;
	private $modules;
	private $user_data;
	private $share;

	public function __construct(
		HAP_Profile_Fields $fields,
		HAP_Profile_Modules $modules,
		HAP_Profile_User_Data $user_data,
		HAP_Profile_Share $share
	) {
		$this->fields    = $fields;
		$this->modules   = $modules;
		$this->user_data = $user_data;
		$this->share     = $share;
	}

	public function register_shortcodes() {
		add_shortcode( 'hap_user_profile_dashboard', array( $this, 'shortcode_dashboard' ) );
		add_shortcode( 'hap_profile_form', array( $this, 'shortcode_form' ) );
		add_shortcode( 'hap_profile_share_button', array( $this, 'shortcode_share_button' ) );
		add_shortcode( 'hap_public_profile', array( $this, 'shortcode_public_profile' ) );
	}

	public function shortcode_dashboard( $atts ) {
		$settings = get_option( 'hap_profile_settings', array() );

		if ( empty( $settings['system_active'] ) ) {
			return '<p class="hap-notice">' . esc_html__( 'Profil sistemi şu an aktif değil.', 'hesaplamaa-profile' ) . '</p>';
		}

		if ( ! is_user_logged_in() ) {
			if ( empty( $settings['allow_guest_profile'] ) ) {
				return $this->render_login_prompt();
			}
			return $this->render_guest_form();
		}

		$user_id = get_current_user_id();

		$share_token = isset( $_GET['share'] ) ? sanitize_text_field( wp_unslash( $_GET['share'] ) ) : '';
		if ( $share_token ) {
			return $this->render_public_profile_by_token( $share_token );
		}

		ob_start();
		$this->load_template( 'dashboard', array(
			'user_id'   => $user_id,
			'fields'    => $this->fields,
			'modules'   => $this->modules,
			'user_data' => $this->user_data,
			'share'     => $this->share,
			'settings'  => $settings,
		) );
		return ob_get_clean();
	}

	public function shortcode_form( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}
		$user_id = get_current_user_id();
		ob_start();
		$this->load_template( 'form-basic', array(
			'user_id'   => $user_id,
			'fields'    => $this->fields,
			'user_data' => $this->user_data,
		) );
		return ob_get_clean();
	}

	public function shortcode_share_button( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user_id = get_current_user_id();
		ob_start();
		$this->load_template( 'share-button', array(
			'user_id' => $user_id,
			'share'   => $this->share,
		) );
		return ob_get_clean();
	}

	public function shortcode_public_profile( $atts ) {
		$token = isset( $_GET['hap_share'] ) ? sanitize_text_field( wp_unslash( $_GET['hap_share'] ) ) : '';
		if ( ! $token && isset( $_GET['share'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['share'] ) );
		}
		if ( ! $token ) {
			return '<p class="hap-notice">' . esc_html__( 'Geçerli bir paylaşım bağlantısı bulunamadı.', 'hesaplamaa-profile' ) . '</p>';
		}
		return $this->render_public_profile_by_token( $token );
	}

	private function render_public_profile_by_token( $token ) {
		$share_data = $this->share->get_share_by_token( $token );
		if ( ! $share_data ) {
			return '<p class="hap-notice">' . esc_html__( 'Bu profil paylaşımı bulunamadı veya süresi dolmuş.', 'hesaplamaa-profile' ) . '</p>';
		}

		$this->share->increment_view_count( $share_data['id'] );

		$settings      = get_option( 'hap_profile_settings', array() );
		$sensitive_keys = $this->fields->get_sensitive_keys();
		$user_data      = $this->user_data->get_user_data( $share_data['user_id'] );

		if ( ! empty( $settings['hide_sensitive_on_share'] ) ) {
			$user_data = $this->share->filter_sensitive_data(
				$user_data,
				$sensitive_keys,
				$share_data['hidden_fields']
			);
		}

		ob_start();
		$this->load_template( 'dashboard-public', array(
			'share_data'       => $share_data,
			'user_data'        => $user_data,
			'fields'           => $this->fields,
			'modules'          => $this->modules,
			'visible_sections' => $share_data['visible_sections'],
		) );
		return ob_get_clean();
	}

	private function render_login_prompt() {
		ob_start();
		?>
		<div class="hap-login-prompt">
			<div class="hap-login-card">
				<div class="hap-login-icon">✨</div>
				<h2><?php esc_html_e( 'Kişisel Profilinizi Oluşturun', 'hesaplamaa-profile' ); ?></h2>
				<p><?php esc_html_e( 'Profilinizi oluşturmak ve kişisel analizlerinize ulaşmak için giriş yapın.', 'hesaplamaa-profile' ); ?></p>
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="hap-btn hap-btn-primary">
					<?php esc_html_e( 'Giriş Yap', 'hesaplamaa-profile' ); ?>
				</a>
				<p class="hap-login-register">
					<?php esc_html_e( 'Hesabınız yok mu?', 'hesaplamaa-profile' ); ?>
					<a href="<?php echo esc_url( wp_registration_url() ); ?>">
						<?php esc_html_e( 'Ücretsiz Kaydol', 'hesaplamaa-profile' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_guest_form() {
		ob_start();
		?>
		<div class="hap-guest-form-wrap">
			<p><?php esc_html_e( 'Misafir profil formu (geliştirme aşamasında).', 'hesaplamaa-profile' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	public function load_template( $template_name, array $vars = array() ) {
		$path = HAP_PLUGIN_DIR . 'templates/' . $template_name . '.php';
		if ( ! file_exists( $path ) ) {
			echo '<!-- HAP template not found: ' . esc_html( $template_name ) . ' -->';
			return;
		}
		extract( $vars, EXTR_SKIP );
		include $path;
	}

	public function enqueue_assets() {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'hap-profile',
			HAP_PLUGIN_URL . 'assets/profile.css',
			array(),
			HAP_VERSION
		);
		wp_enqueue_script(
			'hap-profile',
			HAP_PLUGIN_URL . 'assets/profile.js',
			array( 'jquery' ),
			HAP_VERSION,
			true
		);
		wp_localize_script( 'hap-profile', 'hapProfile', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hap_profile_nonce' ),
			'i18n'    => array(
				'saving'   => __( 'Kaydediliyor...', 'hesaplamaa-profile' ),
				'saved'    => __( 'Kaydedildi!', 'hesaplamaa-profile' ),
				'error'    => __( 'Hata oluştu.', 'hesaplamaa-profile' ),
				'confirm'  => __( 'Emin misiniz?', 'hesaplamaa-profile' ),
			),
		) );
	}

	private function should_load_assets() {
		global $post;

		// Paylaşım token'ı varsa her zaman yükle
		if ( isset( $_GET['hap_share'] ) || isset( $_GET['share'] ) ) {
			return true;
		}

		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}

		// Ayarlarda tanımlı profil ve paylaşım sayfaları
		$settings        = get_option( 'hap_profile_settings', array() );
		$share_settings  = get_option( 'hap_profile_share_settings', array() );
		$profile_page_id = absint( $settings['profile_page_id'] ?? 0 );
		$share_page_id   = absint( $share_settings['share_page_id'] ?? 0 );

		if ( $profile_page_id && is_page( $profile_page_id ) ) {
			return true;
		}
		if ( $share_page_id && is_page( $share_page_id ) ) {
			return true;
		}

		// Sayfada herhangi bir HAP shortcode var mı?
		$hap_shortcodes = array(
			'hap_user_profile_dashboard',
			'hap_profile_form',
			'hap_profile_share_button',
			'hap_public_profile',
			'hap_profile_register',
			'hap_profile_login',
		);
		foreach ( $hap_shortcodes as $sc ) {
			if ( has_shortcode( $post->post_content, $sc ) ) {
				return true;
			}
		}

		return false;
	}

	public function handle_save_profile() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		$user_id = get_current_user_id();
		$data    = isset( $_POST['profile_data'] ) ? (array) $_POST['profile_data'] : array();
		$data    = array_map( 'sanitize_text_field', $data );

		$this->user_data->save_user_data( $user_id, $data );

		wp_send_json_success( array(
			'message'    => 'Profiliniz kaydedildi.',
			'completion' => $this->user_data->get_completion_percentage( $user_id ),
		) );
	}

	public function handle_create_share() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		$user_id = get_current_user_id();

		$sections = isset( $_POST['visible_sections'] ) ? (array) $_POST['visible_sections'] : array();
		$sections = array_map( 'sanitize_key', $sections );

		$hidden = isset( $_POST['hidden_fields'] ) ? (array) $_POST['hidden_fields'] : array();
		$hidden = array_map( 'sanitize_key', $hidden );

		$title = sanitize_text_field( $_POST['share_title'] ?? '' );

		$result = $this->share->create_share( $user_id, array(
			'visible_sections' => $sections,
			'hidden_fields'    => $hidden,
			'share_title'      => $title,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public function handle_revoke_share() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		$user_id  = get_current_user_id();
		$share_id = absint( $_POST['share_id'] ?? 0 );

		if ( ! $share_id ) {
			wp_send_json_error( array( 'message' => 'Geçersiz paylaşım.' ) );
		}

		$this->share->revoke_share( $share_id, $user_id );
		wp_send_json_success( array( 'message' => 'Paylaşım iptal edildi.' ) );
	}

	public function add_noindex( $robots ) {
		$settings = get_option( 'hap_profile_settings', array() );
		if ( empty( $settings['noindex_profiles'] ) ) {
			return $robots;
		}

		global $post;
		if ( ! $post ) {
			return $robots;
		}

		$profile_page_id = absint( $settings['profile_page_id'] ?? 0 );
		$is_share        = isset( $_GET['share'] ) || isset( $_GET['hap_share'] );
		$is_profile_page = $profile_page_id && is_page( $profile_page_id );
		$has_shortcode   = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hap_user_profile_dashboard' );

		if ( $is_profile_page || $has_shortcode || $is_share ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}
}
