<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'hap_profile_humanize_slug' ) ) {
	function hap_profile_humanize_slug( $slug ) {
		return class_exists( 'HAP_Profile_Fields' )
			? HAP_Profile_Fields::humanize_module_title( $slug )
			: ucwords( trim( preg_replace( '/\s+/', ' ', str_replace( array( '-', '_' ), ' ', sanitize_title( (string) $slug ) ) ) ) );
	}
}

class HAP_Profile_Render {

	private $fields;
	private $modules;
	private $user_data;
	private $share;
	private $onboarding;

	public function __construct(
		HAP_Profile_Fields $fields,
		HAP_Profile_Modules $modules,
		HAP_Profile_User_Data $user_data,
		HAP_Profile_Share $share,
		HAP_Profile_Onboarding $onboarding
	) {
		$this->fields    = $fields;
		$this->modules   = $modules;
		$this->user_data = $user_data;
		$this->share     = $share;
		$this->onboarding = $onboarding;
	}

	public function register_shortcodes() {
		add_shortcode( 'hap_user_profile_dashboard', array( $this, 'shortcode_dashboard' ) );
		add_shortcode( 'hap_profile_onboarding', array( $this, 'shortcode_onboarding' ) );
		add_shortcode( 'hap_profile_dashboard_only', array( $this, 'shortcode_dashboard_only' ) );
		add_shortcode( 'hap_profile_form', array( $this, 'shortcode_form' ) );
		add_shortcode( 'hap_profile_share_button', array( $this, 'shortcode_share_button' ) );
		add_shortcode( 'hap_public_profile', array( $this, 'shortcode_public_profile' ) );
	}

	public function shortcode_dashboard( $atts ) {
		$settings = get_option( 'hap_profile_settings', array() );

		if ( empty( $settings['system_active'] ) ) {
			return '<p class="hap-notice">' . esc_html__( 'Profil sistemi su an aktif degil.', 'hesaplamaa-profile' ) . '</p>';
		}

		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		$user_id = get_current_user_id();

		$share_token = isset( $_GET['share'] ) ? sanitize_text_field( wp_unslash( $_GET['share'] ) ) : '';
		if ( $share_token ) {
			return $this->render_public_profile_by_token( $share_token );
		}

		if ( ! HAP_Profile_Fields::is_minimum_profile_complete( $user_id ) || ! $this->onboarding->are_required_steps_complete( $user_id ) ) {
			return $this->render_onboarding_for_user( $user_id );
		}

		return $this->render_dashboard_for_user( $user_id, $settings );
	}

	public function shortcode_onboarding( $atts ) {
		$settings = get_option( 'hap_profile_settings', array() );

		if ( empty( $settings['system_active'] ) ) {
			return '<p class="hap-notice">' . esc_html__( 'Profil sistemi su an aktif degil.', 'hesaplamaa-profile' ) . '</p>';
		}

		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		return $this->render_onboarding_for_user( get_current_user_id() );
	}

	public function shortcode_dashboard_only( $atts ) {
		$settings = get_option( 'hap_profile_settings', array() );

		if ( empty( $settings['system_active'] ) ) {
			return '<p class="hap-notice">' . esc_html__( 'Profil sistemi su an aktif degil.', 'hesaplamaa-profile' ) . '</p>';
		}

		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		return $this->render_dashboard_for_user( get_current_user_id(), $settings );
	}

	public function shortcode_form( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		$user_id = get_current_user_id();

		ob_start();
		?>
		<div class="hap-profile-app">
			<div class="hap-dashboard hap-dashboard-form-only">
				<?php
				$this->load_template(
					'form-basic',
					array(
						'user_id'   => $user_id,
						'fields'    => $this->fields,
						'user_data' => $this->user_data,
					)
				);
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function shortcode_share_button( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user_id = get_current_user_id();
		ob_start();
		$this->load_template(
			'share-button',
			array(
				'user_id' => $user_id,
				'share'   => $this->share,
			)
		);
		return ob_get_clean();
	}

	public function shortcode_public_profile( $atts ) {
		$token = isset( $_GET['hap_share'] ) ? sanitize_text_field( wp_unslash( $_GET['hap_share'] ) ) : '';
		if ( ! $token && isset( $_GET['share'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['share'] ) );
		}
		if ( ! $token ) {
			return '<p class="hap-notice">' . esc_html__( 'Gecerli bir paylasim baglantisi bulunamadi.', 'hesaplamaa-profile' ) . '</p>';
		}
		return $this->render_public_profile_by_token( $token );
	}

	public function get_sections_config() {
		return array(
			'overview'           => array(
				'label'       => 'Temel Profil',
				'icon'        => '👤',
				'description' => 'Doğum tarihi, cinsiyet ve şehir bilgilerine dayanan temel kişilik profili.',
			),
			'astrology'         => array(
				'label'       => 'Astroloji',
				'icon'        => '♈',
				'description' => 'Doğum tarihinle temel astroloji analizlerin hazırlanabilir. Doğum saati ve yeri ekleyerek ay burcu ve ev yerleşimi analizlerini açabilirsin.',
			),
			'astrology_houses'  => array(
				'label'       => 'Astroloji Evleri',
				'icon'        => '🏛',
				'description' => 'Doğum saati ve doğum yeri bilgisiyle yükselen burç, ev yerleşimleri ve detaylı harita analizleri açılır.',
			),
			'moon_sky'          => array(
				'label'       => 'Ay & Gökyüzü',
				'icon'        => '🌙',
				'description' => 'Doğum saati ve yeri eklenince ay burcu ve gökyüzü ritimlerine dayanan analiz kartları hazır olur.',
			),
			'health_lifestyle'  => array(
				'label'       => 'Sağlık & Yaşam',
				'icon'        => '🍎',
				'description' => 'Boy, kilo ve aktivite düzeyi bilgilerini eklediğinde sağlık ve günlük yaşam analizlerin hazırlanır.',
			),
			'sport_activity'    => array(
				'label'       => 'Spor & Aktivite',
				'icon'        => '⚡',
				'description' => 'Aktivite seviyesi, günlük adım ve uyku bilgileriyle hareket ve performans analizleri açılır.',
			),
			'numerology'        => array(
				'label'       => 'Numeroloji',
				'icon'        => '🔢',
				'description' => 'Ad ve soyad bilgilerini ekleyerek isim temelli numeroloji analizlerini açabilirsin.',
			),
			'chinese_astrology' => array(
				'label'       => 'Çin Astrolojisi',
				'icon'        => '🐉',
				'description' => 'Doğum tarihine dayalı Çin takvimi ve astroloji analizleri.',
			),
			'symbolic'          => array(
				'label'       => 'Sembolik Profil',
				'icon'        => '🔮',
				'description' => 'Sembolik yorum ve sezgisel profil kartları.',
			),
			'tarot'             => array(
				'label'       => 'Tarot',
				'icon'        => '🃏',
				'description' => 'Kart tabanlı sezgisel rehberlik ve yorumlar.',
			),
		);
	}

	public function get_section_message( $section_key, array $missing_labels ) {
		switch ( $section_key ) {
			case 'astrology':
				return empty( $missing_labels )
					? 'Dogum tarihinle temel burc analizlerin hazir.'
					: 'Dogum tarihinle temel burc analizlerin hazir. Daha fazla detay icin ek bilgiler tamamlanabilir.';
			case 'astrology_houses':
				return empty( $missing_labels )
					? 'Dogum saati ve yeriyle ev yerlesimi analizlerin hazir.'
					: 'Dogum saati ve dogum yeri bilgilerini ekleyerek ev yerlesimi analizlerini acabilirsin.';
			case 'moon_sky':
				return empty( $missing_labels )
					? 'Ay burcu ve gokyuzu kartlarin hazir.'
					: 'Dogum saati ve dogum yeri eklenince Ay burcu ve gokyuzu kartlari hazir olur.';
			case 'health_lifestyle':
				return empty( $missing_labels )
					? 'Boy, kilo ve aktivite duzeyiyle yasam analizi kartlarin hazir.'
					: 'Boy, kilo ve aktivite duzeyi bilgilerini eklediginde gunluk yasam analizlerin hazirlanir.';
			case 'sport_activity':
				return empty( $missing_labels )
					? 'Aktivite bazli spor kartlarin acildi.'
					: 'Hareket ve performans kartlarini acmak icin boy, kilo ve aktivite seviyeni tamamla.';
			case 'numerology':
				return empty( $missing_labels )
					? 'Ad ve soyadina gore isim temelli numeroloji kartlarin hazir.'
					: 'Ad ve soyad bilgini eklediginde isim temelli numeroloji analizlerin acilir.';
			default:
				return empty( $missing_labels )
					? 'Bu analiz kategorisi icin gerekli bilgiler tamamlandi.'
					: 'Bu analiz kategorisini acmak icin eksik bilgileri tamamlayabilirsin.';
		}
	}

	public function get_current_page_url() {
		$post_id = get_queried_object_id();
		if ( $post_id ) {
			return get_permalink( $post_id );
		}

		return home_url( '/' );
	}

	public function get_dashboard_url() {
		$settings        = get_option( 'hap_profile_settings', array() );
		$profile_page_id = absint( $settings['profile_page_id'] ?? 0 );
		if ( $profile_page_id ) {
			$url = get_permalink( $profile_page_id );
			if ( $url ) {
				return $url;
			}
		}

		return $this->get_current_page_url();
	}

	private function render_dashboard_for_user( $user_id, array $settings ) {
		ob_start();
		$this->load_template(
			'dashboard',
			array(
				'user_id'    => $user_id,
				'fields'     => $this->fields,
				'modules'    => $this->modules,
				'user_data'  => $this->user_data,
				'share'      => $this->share,
				'settings'   => $settings,
				'render'     => $this,
				'onboarding' => $this->onboarding,
			)
		);
		return ob_get_clean();
	}

	private function render_onboarding_for_user( $user_id ) {
		ob_start();
		$this->load_template(
			'onboarding',
			array(
				'user_id'       => $user_id,
				'fields'        => $this->fields,
				'user_data'     => $this->user_data,
				'onboarding'    => $this->onboarding,
				'dashboard_url' => $this->get_dashboard_url(),
			)
		);
		return ob_get_clean();
	}

	private function render_public_profile_by_token( $token ) {
		$share_data = $this->share->get_share_by_token( $token );
		if ( ! $share_data ) {
			return '<p class="hap-notice">' . esc_html__( 'Bu profil paylasimi bulunamadi veya suresi dolmus.', 'hesaplamaa-profile' ) . '</p>';
		}

		$this->share->increment_view_count( $share_data['id'] );

		$settings       = get_option( 'hap_profile_settings', array() );
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
		$this->load_template(
			'dashboard-public',
			array(
				'share_data'       => $share_data,
				'user_data'        => $user_data,
				'fields'           => $this->fields,
				'modules'          => $this->modules,
				'visible_sections' => $share_data['visible_sections'],
			)
		);
		return ob_get_clean();
	}

	private function render_login_prompt() {
		ob_start();
		?>
		<div class="hap-profile-app">
			<div class="hap-login-prompt">
				<div class="hap-login-card">
					<div class="hap-login-icon">Hp</div>
					<div>
						<span class="hap-eyebrow"><?php esc_html_e( 'Kisisel Analiz Merkezi', 'hesaplamaa-profile' ); ?></span>
						<h2><?php esc_html_e( 'Kisisel Profilinizi Olusturun', 'hesaplamaa-profile' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'Saglik, astroloji, numeroloji ve yasam analizlerini tek panelde kesfedin.', 'hesaplamaa-profile' ); ?></p>

					<div class="hap-login-benefits">
						<div class="hap-login-benefit">
							<strong><?php esc_html_e( 'Kisisel analiz paneli', 'hesaplamaa-profile' ); ?></strong>
							<span><?php esc_html_e( 'Tum uyumlu analizlerini premium kartlar halinde gor.', 'hesaplamaa-profile' ); ?></span>
						</div>
						<div class="hap-login-benefit">
							<strong><?php esc_html_e( 'Eksik bilgileri adim adim tamamla', 'hesaplamaa-profile' ); ?></strong>
							<span><?php esc_html_e( 'Hangi bilginin hangi analizleri actigini tek bakista takip et.', 'hesaplamaa-profile' ); ?></span>
						</div>
						<div class="hap-login-benefit">
							<strong><?php esc_html_e( 'Profilini guvenle paylas', 'hesaplamaa-profile' ); ?></strong>
							<span><?php esc_html_e( 'Hassas veriler korunurken secili bolumlerini baglanti ile sun.', 'hesaplamaa-profile' ); ?></span>
						</div>
					</div>

					<div class="hap-login-actions">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="hap-btn hap-btn-primary"><?php esc_html_e( 'Giris Yap', 'hesaplamaa-profile' ); ?></a>
						<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="hap-btn hap-btn-secondary"><?php esc_html_e( 'Ucretsiz Kaydol', 'hesaplamaa-profile' ); ?></a>
					</div>

					<p class="hap-login-register"><?php esc_html_e( 'Kayit akisiniz ayri ilerlese bile bu buton mevcut kayit veya giris sayfasina yonlenir.', 'hesaplamaa-profile' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_guest_form() {
		ob_start();
		?>
		<div class="hap-profile-app">
			<div class="hap-login-prompt">
				<div class="hap-login-card">
					<div class="hap-login-icon">Gs</div>
					<div>
						<span class="hap-eyebrow"><?php esc_html_e( 'Yakinda', 'hesaplamaa-profile' ); ?></span>
						<h2><?php esc_html_e( 'Misafir profil akisi hazirlaniyor', 'hesaplamaa-profile' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'Bu alanda simdilik sadece uyelik paneli tasarimi gosterilir. Misafir profil deneyimi sonraki asamada acilacaktir.', 'hesaplamaa-profile' ); ?></p>
				</div>
			</div>
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

		$css_path = HAP_PLUGIN_DIR . 'assets/profile.css';
		$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : HAP_VERSION;
		wp_enqueue_style(
			'hap-profile',
			HAP_PLUGIN_URL . 'assets/profile.css',
			array(),
			$css_ver
		);

		$js_path = HAP_PLUGIN_DIR . 'assets/profile.js';
		$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : HAP_VERSION;
		wp_enqueue_script(
			'hap-profile',
			HAP_PLUGIN_URL . 'assets/profile.js',
			array( 'jquery' ),
			$js_ver,
			true
		);
		wp_localize_script(
			'hap-profile',
			'hapProfile',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hap_profile_nonce' ),
				'i18n'    => array(
					'saving'  => __( 'Kaydediliyor...', 'hesaplamaa-profile' ),
					'saved'   => __( 'Kaydedildi!', 'hesaplamaa-profile' ),
					'error'   => __( 'Hata olustu.', 'hesaplamaa-profile' ),
					'confirm' => __( 'Emin misiniz?', 'hesaplamaa-profile' ),
				),
			)
		);
	}

	private function should_load_assets() {
		global $post;

		if ( isset( $_GET['hap_share'] ) || isset( $_GET['share'] ) ) {
			return true;
		}

		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}

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

		$hap_shortcodes = array(
			'hap_user_profile_dashboard',
			'hap_profile_onboarding',
			'hap_profile_dashboard_only',
			'hap_profile_form',
			'hap_profile_share_button',
			'hap_public_profile',
			'hap_profile_register',
			'hap_profile_login',
		);
		foreach ( $hap_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	public function handle_save_profile() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giris yapmaniz gerekiyor.' ) );
		}

		$user_id = get_current_user_id();
		$data    = isset( $_POST['profile_data'] ) ? (array) $_POST['profile_data'] : array();

		$this->user_data->save_user_data( $user_id, $data );

		wp_send_json_success(
			array(
				'message'    => 'Profiliniz kaydedildi.',
				'completion' => $this->user_data->get_completion_percentage( $user_id ),
			)
		);
	}

	public function handle_save_profile_post() {
		if ( ! is_user_logged_in() ) {
			wp_redirect( wp_login_url( wp_get_referer() ?: home_url() ) );
			exit;
		}

		$nonce = isset( $_POST['hap_pf_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hap_pf_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hap_save_profile_form' ) ) {
			wp_redirect( add_query_arg( 'hap_error', 'nonce', wp_get_referer() ?: home_url() ) );
			exit;
		}

		$user_id = get_current_user_id();
		$data    = isset( $_POST['profile_data'] ) ? (array) $_POST['profile_data'] : array();

		$this->user_data->save_user_data( $user_id, $data );

		wp_redirect( add_query_arg( 'hap_saved', '1', wp_get_referer() ?: home_url() ) );
		exit;
	}

	public function handle_create_share() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giris yapmaniz gerekiyor.' ) );
		}

		$user_id = get_current_user_id();

		$sections = isset( $_POST['visible_sections'] ) ? (array) $_POST['visible_sections'] : array();
		$sections = array_map( 'sanitize_key', $sections );

		$hidden = isset( $_POST['hidden_fields'] ) ? (array) $_POST['hidden_fields'] : array();
		$hidden = array_map( 'sanitize_key', $hidden );

		$title = sanitize_text_field( $_POST['share_title'] ?? '' );

		$result = $this->share->create_share(
			$user_id,
			array(
				'visible_sections' => $sections,
				'hidden_fields'    => $hidden,
				'share_title'      => $title,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public function handle_revoke_share() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giris yapmaniz gerekiyor.' ) );
		}

		$user_id  = get_current_user_id();
		$share_id = absint( $_POST['share_id'] ?? 0 );

		if ( ! $share_id ) {
			wp_send_json_error( array( 'message' => 'Gecersiz paylasim.' ) );
		}

		$this->share->revoke_share( $share_id, $user_id );
		wp_send_json_success( array( 'message' => 'Paylasim iptal edildi.' ) );
	}

	public function add_noindex( $robots ) {
		$settings = get_option( 'hap_profile_settings', array() );
		if ( empty( $settings['noindex_profiles'] ) ) {
			return $robots;
		}

		global $post;
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			return $robots;
		}

		$profile_page_id  = absint( $settings['profile_page_id'] ?? 0 );
		$register_page_id = absint( $settings['profile_register_page_id'] ?? 0 );
		$login_page_id    = absint( $settings['profile_login_page_id'] ?? 0 );
		$share_settings   = get_option( 'hap_profile_share_settings', array() );
		$share_page_id    = absint( $share_settings['share_page_id'] ?? 0 );

		$is_share        = isset( $_GET['share'] ) || isset( $_GET['hap_share'] );
		$is_profile_page = $profile_page_id && is_page( $profile_page_id );
		$is_register     = $register_page_id && is_page( $register_page_id );
		$is_login        = $login_page_id && is_page( $login_page_id );
		$is_share_page   = $share_page_id && is_page( $share_page_id );

		$hap_shortcodes = array(
			'hap_user_profile_dashboard',
			'hap_profile_onboarding',
			'hap_profile_dashboard_only',
			'hap_profile_form',
			'hap_profile_register',
			'hap_profile_login',
			'hap_public_profile',
			'hap_profile_share_button',
		);
		$has_shortcode = false;
		foreach ( $hap_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$has_shortcode = true;
				break;
			}
		}

		if ( $is_profile_page || $is_register || $is_login || $is_share_page || $has_shortcode || $is_share ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	public function handle_generate_ai_report() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giriş yapmanız gerekiyor.' ) );
		}

		$user_id = get_current_user_id();
		
		if ( ! class_exists( 'HAP_Profile_AI_Report' ) ) {
			wp_send_json_error( array( 'message' => 'AI modülü yüklenmemiş.' ) );
		}

		if ( ! empty( $_POST['ai_consent'] ) && class_exists( 'HAP_Profile_Consents' ) ) {
			HAP_Profile_Consents::save_consent( $user_id, HAP_Profile_Consents::get_ai_consent_type(), true );
		}

		$force = ! empty( $_POST['force_regenerate'] );
		$report_engine = new HAP_Profile_AI_Report();
		$result = $report_engine->generate_report( $user_id, $force );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
		}

		wp_send_json_success( $result );
	}
}
