<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Admin {

	private $fields;
	private $modules;
	private $user_data;
	private $ai_templates;
	private $health;

	private $tabs = array();

	public function __construct(
		HAP_Profile_Fields $fields,
		HAP_Profile_Modules $modules,
		HAP_Profile_User_Data $user_data,
		HAP_Profile_AI_Templates $ai_templates,
		HAP_Profile_Health $health
	) {
		$this->fields       = $fields;
		$this->modules      = $modules;
		$this->user_data    = $user_data;
		$this->ai_templates = $ai_templates;
		$this->health       = $health;

		$this->tabs = array(
			'general'       => 'Genel Ayarlar',
			'fields'        => 'Profil Alanları',
			'modules'       => 'Profil Modülleri',
			'preset'        => 'Gelecek Modül Presetleri',
			'ai_templates'  => 'AI Yorum Şablonları',
			'share'         => 'Paylaşım Ayarları',
			'auth'          => 'Üyelik ve Güvenlik',
			'updater'       => 'GitHub Güncelleme',
			'health'        => 'Sağlık Kontrolü',
		);
	}

	public function add_menu() {
		add_menu_page(
			'Hesaplamaa Profil',
			'Hesaplamaa Profil',
			'manage_options',
			'hesaplamaa-profile',
			array( $this, 'render_page' ),
			'dashicons-id-alt',
			30
		);
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'hesaplamaa-profile' ) === false ) {
			return;
		}
		wp_enqueue_style( 'hap-admin', HAP_PLUGIN_URL . 'assets/admin.css', array(), HAP_VERSION );
		wp_enqueue_script( 'hap-admin', HAP_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), HAP_VERSION, true );
		wp_enqueue_media();
		wp_localize_script( 'hap-admin', 'hapAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hap_admin_nonce' ),
			'i18n'    => array(
				'importing'     => 'İçe aktarılıyor...',
				'import_done'   => 'İçe aktarma tamamlandı.',
				'import_error'  => 'İçe aktarma hatası.',
				'delete_confirm'=> 'Bu modülü silmek istediğinizden emin misiniz?',
				'deleting'      => 'Siliniyor...',
				'saving'        => 'Kaydediliyor...',
				'saved'         => 'Kaydedildi!',
			),
		) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'hesaplamaa-profile' ) );
		}

		$this->handle_form_submissions();

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		if ( ! array_key_exists( $active_tab, $this->tabs ) ) {
			$active_tab = 'general';
		}

		?>
		<div class="wrap hap-admin-wrap">
			<h1 class="hap-admin-title">
				<span class="dashicons dashicons-id-alt"></span>
				Hesaplamaa Profile <span class="hap-version">v<?php echo esc_html( HAP_VERSION ); ?></span>
			</h1>

			<nav class="nav-tab-wrapper hap-nav-tabs">
				<?php foreach ( $this->tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hesaplamaa-profile&tab=' . $key ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="hap-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'general':
						$this->render_general_tab();
						break;
					case 'fields':
						$this->render_fields_tab();
						break;
					case 'modules':
						$this->render_modules_tab();
						break;
					case 'preset':
						$this->render_preset_tab();
						break;
					case 'ai_templates':
						$this->render_ai_templates_tab();
						break;
					case 'share':
						$this->render_share_tab();
						break;
					case 'auth':
						$this->render_auth_tab();
						break;
					case 'updater':
						$this->render_updater_tab();
						break;
					case 'health':
						$this->render_health_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function handle_form_submissions() {
		if ( ! isset( $_POST['hap_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! check_admin_referer( 'hap_admin_action', 'hap_nonce' ) ) {
			return;
		}

		$action = sanitize_key( $_POST['hap_action'] );

		switch ( $action ) {
			case 'save_general':
				$this->save_general_settings();
				break;
			case 'save_share':
				$this->save_share_settings();
				break;
			case 'save_auth':
				$this->save_auth_settings();
				break;
			case 'save_updater':
				$this->save_updater_settings();
				break;
			case 'save_ai_templates':
				$this->save_ai_templates();
				break;
			case 'save_fields':
				$this->save_fields_settings();
				break;
			case 'save_module_inline':
				$this->save_module_inline();
				break;
			case 'add_preset_module':
				$this->save_preset_module();
				break;
		}
	}

	private function save_general_settings() {
		$settings = array(
			'system_active'           => ! empty( $_POST['system_active'] ) ? 1 : 0,
			'ai_enabled'              => ! empty( $_POST['ai_enabled'] ) ? 1 : 0,
			'profile_page_id'         => absint( $_POST['profile_page_id'] ?? 0 ),
			'noindex_profiles'        => ! empty( $_POST['noindex_profiles'] ) ? 1 : 0,
			'allow_guest_profile'     => ! empty( $_POST['allow_guest_profile'] ) ? 1 : 0,
			'premium_dashboard'       => ! empty( $_POST['premium_dashboard'] ) ? 1 : 0,
			'shareable_profile'       => ! empty( $_POST['shareable_profile'] ) ? 1 : 0,
			'hide_sensitive_on_share' => ! empty( $_POST['hide_sensitive_on_share'] ) ? 1 : 0,
			'delete_on_uninstall'     => ! empty( $_POST['delete_on_uninstall'] ) ? 1 : 0,
		);
		update_option( 'hap_profile_settings', $settings );
		update_option( 'hap_profile_delete_on_uninstall', $settings['delete_on_uninstall'] );
		add_settings_error( 'hap_profile', 'saved', 'Genel ayarlar kaydedildi.', 'updated' );
	}

	private function save_share_settings() {
		$settings = array(
			'share_page_id'   => absint( $_POST['share_page_id'] ?? 0 ),
			'share_url_param' => sanitize_key( $_POST['share_url_param'] ?? 'share' ),
			'default_expiry'  => absint( $_POST['default_expiry'] ?? 0 ),
			'allow_public'    => ! empty( $_POST['allow_public'] ) ? 1 : 0,
		);
		update_option( 'hap_profile_share_settings', $settings );
		add_settings_error( 'hap_profile', 'saved', 'Paylaşım ayarları kaydedildi.', 'updated' );
	}

	private function save_updater_settings() {
		$updater = new HAP_Profile_Updater();
		$updater->save_settings( array(
			'repo'   => $_POST['hap_updater_repo']   ?? '',
			'branch' => $_POST['hap_updater_branch'] ?? 'main',
			'token'  => $_POST['hap_updater_token']  ?? '',
		) );
		add_settings_error( 'hap_profile', 'saved', 'GitHub güncelleme ayarları kaydedildi.', 'updated' );
	}

	private function save_ai_templates() {
		$data = isset( $_POST['ai_templates'] ) ? (array) $_POST['ai_templates'] : array();
		$this->ai_templates->save_templates( $data );
		add_settings_error( 'hap_profile', 'saved', 'AI şablonları kaydedildi.', 'updated' );
	}

	private function save_fields_settings() {
		$fields_post = isset( $_POST['hap_fields'] ) ? (array) $_POST['hap_fields'] : array();
		$fields      = $this->fields->get_fields();
		foreach ( $fields as &$field ) {
			$key              = $field['key'];
			$field['active']   = isset( $fields_post[ $key ]['active'] );
			$field['required'] = isset( $fields_post[ $key ]['required'] );
			$field['sensitive']= isset( $fields_post[ $key ]['sensitive'] );
		}
		unset( $field );
		$this->fields->save_fields( $fields );
		add_settings_error( 'hap_profile', 'saved', 'Profil alanları kaydedildi.', 'updated' );
	}

	private function save_module_inline() {
		$module_id = absint( $_POST['module_id'] ?? 0 );
		$data      = array(
			'profile_status'          => sanitize_key( $_POST['profile_status'] ?? 'disabled' ),
			'section'                 => sanitize_key( $_POST['section'] ?? '' ),
			'required_fields'         => array_map( 'sanitize_key', (array) ( $_POST['required_fields'] ?? array() ) ),
			'missing_fields_behavior' => sanitize_key( $_POST['missing_fields_behavior'] ?? 'show_prompt' ),
			'ai_enabled'              => ! empty( $_POST['ai_enabled'] ) ? 1 : 0,
			'sort_order'              => absint( $_POST['sort_order'] ?? 0 ),
			'notes'                   => sanitize_textarea_field( $_POST['notes'] ?? '' ),
		);

		$existing = $this->modules->get_module_by_id( $module_id );
		if ( $existing ) {
			$data = array_merge( $existing, $data );
			$this->modules->save_module( $data, $module_id );
		}
		add_settings_error( 'hap_profile', 'saved', 'Modül kaydedildi.', 'updated' );
	}

	private function save_preset_module() {
		$req_raw = isset( $_POST['preset_required_fields'] ) ? sanitize_text_field( $_POST['preset_required_fields'] ) : '';
		$req     = array_filter( array_map( 'trim', explode( ',', $req_raw ) ) );
		$req     = array_map( 'sanitize_key', $req );

		$data = array(
			'slug'                    => sanitize_key( $_POST['preset_slug'] ?? '' ),
			'title'                   => sanitize_text_field( $_POST['preset_title'] ?? '' ),
			'shortcode'               => sanitize_text_field( $_POST['preset_shortcode'] ?? '' ),
			'section'                 => sanitize_key( $_POST['preset_section'] ?? '' ),
			'profile_status'          => sanitize_key( $_POST['preset_profile_status'] ?? 'disabled' ),
			'required_fields'         => $req,
			'missing_fields_behavior' => sanitize_key( $_POST['preset_missing_fields_behavior'] ?? 'show_prompt' ),
			'ai_enabled'              => ! empty( $_POST['preset_ai_enabled'] ) ? 1 : 0,
			'sort_order'              => absint( $_POST['preset_sort_order'] ?? 0 ),
			'source'                  => 'manual',
			'availability_status'     => sanitize_key( $_POST['preset_availability_status'] ?? 'planned' ),
			'notes'                   => sanitize_textarea_field( $_POST['preset_notes'] ?? '' ),
		);

		if ( empty( $data['slug'] ) || empty( $data['title'] ) ) {
			add_settings_error( 'hap_profile', 'error', 'Slug ve başlık zorunludur.', 'error' );
			return;
		}

		$result = $this->modules->save_module( $data );
		if ( $result ) {
			add_settings_error( 'hap_profile', 'saved', 'Preset modül eklendi: ' . esc_html( $data['title'] ), 'updated' );
		} else {
			add_settings_error( 'hap_profile', 'error', 'Modül kaydedilemedi.', 'error' );
		}
	}

	/* ============================================================
	   AJAX HANDLERS
	   ============================================================ */

	public function ajax_import_modules() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		if ( empty( $_FILES['json_file'] ) ) {
			wp_send_json_error( array( 'message' => 'Dosya bulunamadı.' ) );
		}

		$file     = $_FILES['json_file'];
		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => 'Dosya 5MB limitini aşıyor.' ) );
		}

		$finfo    = new finfo( FILEINFO_MIME_TYPE );
		$mime     = $finfo->file( $file['tmp_name'] );
		$allowed  = array( 'application/json', 'text/plain', 'text/json' );
		if ( ! in_array( $mime, $allowed, true ) && substr( $file['name'], -5 ) !== '.json' ) {
			wp_send_json_error( array( 'message' => 'Sadece JSON dosyası kabul edilir.' ) );
		}

		$content = file_get_contents( $file['tmp_name'] );
		if ( ! $content ) {
			wp_send_json_error( array( 'message' => 'Dosya okunamadı.' ) );
		}

		$result = $this->modules->import_from_json( $content );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => sprintf( '%d modül içe aktarıldı, %d atlandı.', $result['imported'], $result['skipped'] ),
			'imported' => $result['imported'],
			'skipped'  => $result['skipped'],
		) );
	}

	public function ajax_delete_module() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}
		$id = absint( $_POST['module_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Geçersiz ID.' ) );
		}
		$this->modules->delete_module( $id );
		wp_send_json_success( array( 'message' => 'Modül silindi.' ) );
	}

	public function ajax_save_single_module() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		$id = absint( $_POST['module_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Geçersiz ID.' ) );
		}

		$existing = $this->modules->get_module_by_id( $id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => 'Modül bulunamadı.' ) );
		}

		$req    = isset( $_POST['required_fields'] ) ? array_map( 'sanitize_key', (array) $_POST['required_fields'] ) : array();
		$update = array_merge( $existing, array(
			'profile_status'          => sanitize_key( $_POST['profile_status'] ?? $existing['profile_status'] ),
			'section'                 => sanitize_key( $_POST['section'] ?? $existing['section'] ),
			'required_fields'         => $req,
			'missing_fields_behavior' => sanitize_key( $_POST['missing_fields_behavior'] ?? $existing['missing_fields_behavior'] ),
			'ai_enabled'              => ! empty( $_POST['ai_enabled'] ) ? 1 : 0,
			'sort_order'              => absint( $_POST['sort_order'] ?? $existing['sort_order'] ),
			'notes'                   => sanitize_textarea_field( $_POST['notes'] ?? '' ),
			'availability_status'     => sanitize_key( $_POST['availability_status'] ?? $existing['availability_status'] ),
		) );

		$this->modules->save_module( $update, $id );
		wp_send_json_success( array( 'message' => 'Modül kaydedildi.' ) );
	}

	public function ajax_sync_from_suite() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}
		if ( ! class_exists( 'HC_Module_Inventory' ) ) {
			wp_send_json_error( array( 'message' => 'Hesaplama Suite aktif değil veya HC_Module_Inventory sınıfı bulunamadı.' ) );
		}
		$suite_modules = HC_Module_Inventory::get_modules();
		if ( ! is_array( $suite_modules ) ) {
			wp_send_json_error( array( 'message' => 'Modül listesi alınamadı.' ) );
		}
		$result = $this->modules->import_from_json( $suite_modules );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array(
			'message'  => sprintf( 'Suite\'ten %d modül içe aktarıldı.', $result['imported'] ),
			'imported' => $result['imported'],
		) );
	}

	/* ============================================================
	   TAB RENDERERS
	   ============================================================ */

	private function render_general_tab() {
		$settings          = get_option( 'hap_profile_settings', array() );
		$delete_on_uninstall = get_option( 'hap_profile_delete_on_uninstall', 0 );
		settings_errors( 'hap_profile' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_general">

			<h2>Genel Ayarlar</h2>
			<table class="form-table">
				<tr>
					<th>Profil Sistemi</th>
					<td>
						<label><input type="checkbox" name="system_active" value="1" <?php checked( ! empty( $settings['system_active'] ) ); ?>> Aktif</label>
						<p class="description">Profil sistemini açar/kapatır.</p>
					</td>
				</tr>
				<tr>
					<th>AI Yorumları</th>
					<td>
						<label><input type="checkbox" name="ai_enabled" value="1" <?php checked( ! empty( $settings['ai_enabled'] ) ); ?>> Aktif</label>
						<p class="description">Faz 1'de AI çağrısı yapılmaz, ilerisi için altyapı hazırlanır.</p>
					</td>
				</tr>
				<tr>
					<th>Profil Sayfası</th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'profile_page_id',
							'selected'         => absint( $settings['profile_page_id'] ?? 0 ),
							'show_option_none' => '— Seçiniz —',
							'option_none_value'=> 0,
						) );
						?>
						<p class="description">Dashboard shortcode'unun bulunduğu sayfa. (Shortcode: <code>[hap_user_profile_dashboard]</code>)</p>
					</td>
				</tr>
				<tr>
					<th>Profil Sayfası noindex</th>
					<td>
						<label><input type="checkbox" name="noindex_profiles" value="1" <?php checked( ! empty( $settings['noindex_profiles'] ) ); ?>> Aktif (Varsayılan: Aktif)</label>
						<p class="description">Profil sayfaları arama motorlarına kapatılır.</p>
					</td>
				</tr>
				<tr>
					<th>Misafir Profil</th>
					<td>
						<label><input type="checkbox" name="allow_guest_profile" value="1" <?php checked( ! empty( $settings['allow_guest_profile'] ) ); ?>> İzin Ver</label>
						<p class="description">Varsayılan: kapalı. Giriş yapmamış kullanıcılara form gösterilir.</p>
					</td>
				</tr>
				<tr>
					<th>Premium Dashboard</th>
					<td>
						<label><input type="checkbox" name="premium_dashboard" value="1" <?php checked( ! empty( $settings['premium_dashboard'] ) ); ?>> Aktif</label>
					</td>
				</tr>
				<tr>
					<th>Paylaşılabilir Profil</th>
					<td>
						<label><input type="checkbox" name="shareable_profile" value="1" <?php checked( ! empty( $settings['shareable_profile'] ) ); ?>> Aktif</label>
					</td>
				</tr>
				<tr>
					<th>Paylaşımda Hassas Verileri Gizle</th>
					<td>
						<label><input type="checkbox" name="hide_sensitive_on_share" value="1" <?php checked( ! empty( $settings['hide_sensitive_on_share'] ) ); ?>> Aktif (Varsayılan: Aktif)</label>
						<p class="description">Doğum saati, doğum yeri, telefon, plaka gibi hassas veriler public paylaşımda gizlenir.</p>
					</td>
				</tr>
				<tr>
					<th>Kaldırımda Verileri Sil</th>
					<td>
						<label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $delete_on_uninstall ); ?>>
							Eklenti kaldırılırken tüm tabloları ve kullanıcı verilerini sil
						</label>
						<p class="description" style="color:#c00;"><strong>DİKKAT:</strong> Bu seçenek aktifken eklenti kaldırıldığında tüm veriler kalıcı olarak silinir.</p>
					</td>
				</tr>
			</table>

			<h3>Shortcode Referansı</h3>
			<table class="widefat" style="max-width:600px">
				<tr><td><code>[hap_user_profile_dashboard]</code></td><td>Ana profil dashboard'u</td></tr>
				<tr><td><code>[hap_profile_form]</code></td><td>Profil düzenleme formu</td></tr>
				<tr><td><code>[hap_profile_share_button]</code></td><td>Paylaşım butonu</td></tr>
				<tr><td><code>[hap_public_profile]</code></td><td>Public paylaşım görünümü</td></tr>
			</table>

			<?php submit_button( 'Ayarları Kaydet' ); ?>
		</form>
		<?php
	}

	private function render_fields_tab() {
		$fields = $this->fields->get_fields();
		settings_errors( 'hap_profile' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_fields">
			<h2>Profil Alanları</h2>
			<p>Hangi alanların aktif, zorunlu veya hassas olduğunu belirleyin.</p>
			<table class="widefat striped hap-fields-table">
				<thead>
					<tr>
						<th>Key</th>
						<th>Etiket</th>
						<th>Tür</th>
						<th>Adım</th>
						<th>Aktif</th>
						<th>Zorunlu</th>
						<th>Hassas</th>
						<th>Açıklama</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
					<tr>
						<td><code><?php echo esc_html( $field['key'] ); ?></code></td>
						<td><?php echo esc_html( $field['label'] ); ?></td>
						<td><code><?php echo esc_html( $field['type'] ); ?></code></td>
						<td><?php echo absint( $field['step'] ); ?></td>
						<td>
							<input type="checkbox"
							       name="hap_fields[<?php echo esc_attr( $field['key'] ); ?>][active]"
							       value="1"
							       <?php checked( ! empty( $field['active'] ) ); ?>>
						</td>
						<td>
							<input type="checkbox"
							       name="hap_fields[<?php echo esc_attr( $field['key'] ); ?>][required]"
							       value="1"
							       <?php checked( ! empty( $field['required'] ) ); ?>>
						</td>
						<td>
							<input type="checkbox"
							       name="hap_fields[<?php echo esc_attr( $field['key'] ); ?>][sensitive]"
							       value="1"
							       <?php checked( ! empty( $field['sensitive'] ) ); ?>>
						</td>
						<td class="hap-small-text"><?php echo esc_html( $field['description'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( 'Alanları Kaydet' ); ?>
		</form>
		<?php
	}

	private function render_modules_tab() {
		settings_errors( 'hap_profile' );

		$page    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 50;
		$offset  = ( $page - 1 ) * $per_page;

		$search  = sanitize_text_field( $_GET['s'] ?? '' );
		$section = sanitize_key( $_GET['filter_section'] ?? '' );
		$status  = sanitize_key( $_GET['filter_status'] ?? '' );

		$total   = $this->modules->count_modules( array(
			'search'         => $search,
			'section'        => $section,
			'profile_status' => $status,
		) );
		$modules = $this->modules->get_modules( array(
			'search'         => $search,
			'section'        => $section,
			'profile_status' => $status,
			'limit'          => $per_page,
			'offset'         => $offset,
		) );

		$all_fields   = $this->fields->get_fields();
		$field_keys   = wp_list_pluck( $all_fields, 'key' );
		$sections     = $this->get_section_labels();
		$ps_labels    = $this->get_profile_status_labels();
		$mfb_labels   = $this->get_mfb_labels();
		$avail_labels = $this->get_avail_labels();

		$suite_available = class_exists( 'HC_Module_Inventory' );
		?>
		<h2>Profil Modülleri
			<span class="hap-badge"><?php echo esc_html( $total ); ?> modül</span>
		</h2>

		<div class="hap-module-actions">
			<?php if ( $suite_available ) : ?>
			<button id="hap-sync-suite" class="button button-secondary">
				Hesaplama Suite'ten İçe Aktar
			</button>
			<?php endif; ?>

			<div class="hap-import-form">
				<label><strong>JSON Dosyasından İçe Aktar:</strong></label>
				<input type="file" id="hap-json-file" name="json_file" accept=".json">
				<button id="hap-import-json" class="button button-primary">İçe Aktar</button>
				<span id="hap-import-result" class="hap-result-msg"></span>
			</div>
		</div>

		<div class="hap-filter-bar">
			<form method="get">
				<input type="hidden" name="page" value="hesaplamaa-profile">
				<input type="hidden" name="tab" value="modules">
				<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Modül ara...">
				<select name="filter_section">
					<option value="">— Tüm Bölümler —</option>
					<?php foreach ( $sections as $k => $v ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $section, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="filter_status">
					<option value="">— Tüm Durumlar —</option>
					<?php foreach ( $ps_labels as $k => $v ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $v ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( 'Filtrele', 'secondary', '', false ); ?>
			</form>
		</div>

		<?php if ( empty( $modules ) ) : ?>
			<div class="notice notice-warning inline"><p>Henüz modül yok. JSON dosyası ile içe aktarın.</p></div>
		<?php else : ?>
		<table class="widefat hap-modules-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Başlık</th>
					<th>Slug</th>
					<th>Bölüm</th>
					<th>Durum</th>
					<th>Erişilebilirlik</th>
					<th>Gerekli Alanlar</th>
					<th>Eksik Alan Davranışı</th>
					<th>Sıra</th>
					<th>İşlemler</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $modules as $mod ) :
					$req = $this->modules->decode_required_fields( $mod['required_fields'] );
				?>
				<tr data-id="<?php echo absint( $mod['id'] ); ?>">
					<td><?php echo absint( $mod['id'] ); ?></td>
					<td>
						<strong><?php echo esc_html( $mod['title'] ); ?></strong>
						<?php if ( $mod['notes'] ) : ?>
							<br><small class="hap-small-text"><?php echo esc_html( $mod['notes'] ); ?></small>
						<?php endif; ?>
					</td>
					<td><code><?php echo esc_html( $mod['slug'] ); ?></code></td>
					<td><?php echo esc_html( $sections[ $mod['section'] ] ?? $mod['section'] ); ?></td>
					<td>
						<select class="hap-inline-field" data-field="profile_status" data-id="<?php echo absint( $mod['id'] ); ?>">
							<?php foreach ( $ps_labels as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $mod['profile_status'], $k ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<span class="hap-avail-badge hap-avail-<?php echo esc_attr( $mod['availability_status'] ); ?>">
							<?php echo esc_html( $avail_labels[ $mod['availability_status'] ] ?? $mod['availability_status'] ); ?>
						</span>
					</td>
					<td>
						<div class="hap-req-fields">
							<?php if ( ! empty( $req ) ) : ?>
								<?php foreach ( $req as $rk ) : ?>
									<span class="hap-tag"><?php echo esc_html( $this->fields->get_label( $rk ) ); ?></span>
								<?php endforeach; ?>
							<?php else : ?>
								<span class="hap-muted">—</span>
							<?php endif; ?>
						</div>
					</td>
					<td><?php echo esc_html( $mfb_labels[ $mod['missing_fields_behavior'] ] ?? $mod['missing_fields_behavior'] ); ?></td>
					<td><?php echo absint( $mod['sort_order'] ); ?></td>
					<td>
						<button class="button button-small hap-edit-module" data-id="<?php echo absint( $mod['id'] ); ?>">Düzenle</button>
						<button class="button button-small hap-delete-module" data-id="<?php echo absint( $mod['id'] ); ?>">Sil</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$total_pages = ceil( $total / $per_page );
		if ( $total_pages > 1 ) {
			echo '<div class="hap-pagination tablenav-pages">';
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$url = add_query_arg( array( 'paged' => $i, 'tab' => 'modules', 'page' => 'hesaplamaa-profile' ), admin_url( 'admin.php' ) );
				printf(
					'<a href="%s" class="button button-small%s">%d</a> ',
					esc_url( $url ),
					$i === $page ? ' button-primary' : '',
					$i
				);
			}
			echo '</div>';
		}
		?>
		<?php endif; ?>

		<div id="hap-edit-modal" class="hap-modal" style="display:none;">
			<div class="hap-modal-content">
				<h3>Modül Düzenle</h3>
				<div id="hap-edit-form-content"></div>
				<button id="hap-modal-save" class="button button-primary">Kaydet</button>
				<button id="hap-modal-close" class="button">Kapat</button>
			</div>
		</div>

		<script type="text/template" id="hap-edit-template">
			<?php
			$sections_json  = wp_json_encode( $sections );
			$ps_json        = wp_json_encode( $ps_labels );
			$mfb_json       = wp_json_encode( $mfb_labels );
			$avail_json     = wp_json_encode( $avail_labels );
			$all_fields_json = wp_json_encode( array_map( function( $f ) {
				return array( 'key' => $f['key'], 'label' => $f['label'] );
			}, $this->fields->get_fields() ) );
			?>
			<div data-sections="<?php echo esc_attr( $sections_json ); ?>"
			     data-statuses="<?php echo esc_attr( $ps_json ); ?>"
			     data-mfb="<?php echo esc_attr( $mfb_json ); ?>"
			     data-avail="<?php echo esc_attr( $avail_json ); ?>"
			     data-fields="<?php echo esc_attr( $all_fields_json ); ?>"></div>
		</script>
		<?php
	}

	private function render_preset_tab() {
		settings_errors( 'hap_profile' );
		$planned_modules = $this->modules->get_modules( array( 'availability_status' => 'planned', 'limit' => 200 ) );
		$sections        = $this->get_section_labels();
		$ps_labels       = $this->get_profile_status_labels();
		$avail_labels    = $this->get_avail_labels();
		?>
		<h2>Gelecek Modül Presetleri</h2>
		<p>Henüz sitede olmayan modülleri <code>planned</code> olarak ekleyin. Modül hazır olduğunda slug/shortcode eşleşmesiyle <code>active</code> durumuna geçer.</p>

		<div class="hap-two-col">
		<div class="hap-col-main">
		<h3>Planlanmış Modüller (<?php echo count( $planned_modules ); ?>)</h3>
		<?php if ( empty( $planned_modules ) ) : ?>
			<p class="hap-muted">Henüz planlanmış modül yok.</p>
		<?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>Başlık</th><th>Slug</th><th>Bölüm</th><th>Durum</th><th>Kaynak</th></tr></thead>
			<tbody>
			<?php foreach ( $planned_modules as $mod ) : ?>
			<tr>
				<td><?php echo esc_html( $mod['title'] ); ?></td>
				<td><code><?php echo esc_html( $mod['slug'] ); ?></code></td>
				<td><?php echo esc_html( $sections[ $mod['section'] ] ?? $mod['section'] ); ?></td>
				<td><span class="hap-avail-badge hap-avail-<?php echo esc_attr( $mod['availability_status'] ); ?>"><?php echo esc_html( $avail_labels[ $mod['availability_status'] ] ?? $mod['availability_status'] ); ?></span></td>
				<td><?php echo esc_html( $mod['source'] ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
		</div>

		<div class="hap-col-side">
		<h3>Yeni Preset Modül Ekle</h3>
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="add_preset_module">

			<table class="form-table hap-compact-form">
				<tr>
					<th>Başlık *</th>
					<td><input type="text" name="preset_title" class="regular-text" required></td>
				</tr>
				<tr>
					<th>Slug *</th>
					<td><input type="text" name="preset_slug" class="regular-text" required>
					<p class="description">Küçük harf, tire ile. Örn: kisisel-yil-hesaplama</p></td>
				</tr>
				<tr>
					<th>Shortcode</th>
					<td><input type="text" name="preset_shortcode" class="regular-text">
					<p class="description">Örn: [kisisel_yil_hesaplama]</p></td>
				</tr>
				<tr>
					<th>Bölüm</th>
					<td>
						<select name="preset_section">
							<?php foreach ( $sections as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Profil Durumu</th>
					<td>
						<select name="preset_profile_status">
							<?php foreach ( $ps_labels as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Erişilebilirlik</th>
					<td>
						<select name="preset_availability_status">
							<?php foreach ( $avail_labels as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, 'planned' ); ?>><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Gerekli Alanlar</th>
					<td><input type="text" name="preset_required_fields" class="regular-text" placeholder="birth_date, first_name">
					<p class="description">Virgülle ayırın. Örn: birth_date, birth_time, birth_place</p></td>
				</tr>
				<tr>
					<th>Eksik Alan Davranışı</th>
					<td>
						<select name="preset_missing_fields_behavior">
							<?php foreach ( $this->get_mfb_labels() as $k => $v ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Sıra</th>
					<td><input type="number" name="preset_sort_order" value="0" class="small-text"></td>
				</tr>
				<tr>
					<th>Notlar</th>
					<td><textarea name="preset_notes" rows="2" class="regular-text"></textarea></td>
				</tr>
			</table>
			<?php submit_button( 'Preset Modül Ekle', 'primary', 'submit_preset' ); ?>
		</form>
		</div>
		</div>
		<?php
	}

	private function render_ai_templates_tab() {
		$templates = $this->ai_templates->get_templates();
		$keys      = $this->ai_templates->get_template_keys();
		settings_errors( 'hap_profile' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_ai_templates">
			<h2>AI Yorum Şablonları</h2>
			<div class="notice notice-info inline">
				<p><strong>Faz 1:</strong> AI çağrısı şu an aktif değil. Bu şablonlar ileride kullanılmak üzere saklanır. <br>
				Sağlık/spor analizlerinde kesin tavsiye, tanı veya tedavi dili kullanmayın. Astroloji/tarot/numeroloji yorumlarında eğlence ve kişisel farkındalık dili kullanın.</p>
			</div>
			<table class="form-table">
				<?php foreach ( $keys as $key => $label ) : ?>
				<tr>
					<th><?php echo esc_html( $label ); ?></th>
					<td>
						<textarea name="ai_templates[<?php echo esc_attr( $key ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( $templates[ $key ] ?? '' ); ?></textarea>
						<p class="description">Değişkenler: <code>{birth_date}</code>, <code>{birth_place}</code>, <code>{gender}</code>, vb.</p>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( 'Şablonları Kaydet' ); ?>
		</form>
		<?php
	}

	private function render_share_tab() {
		$settings = get_option( 'hap_profile_share_settings', array() );
		settings_errors( 'hap_profile' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_share">
			<h2>Paylaşım Ayarları</h2>
			<table class="form-table">
				<tr>
					<th>Paylaşım Sayfası</th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'             => 'share_page_id',
							'selected'         => absint( $settings['share_page_id'] ?? 0 ),
							'show_option_none' => '— Profil sayfasıyla aynı —',
							'option_none_value'=> 0,
						) );
						?>
					</td>
				</tr>
				<tr>
					<th>URL Parametresi</th>
					<td>
						<input type="text" name="share_url_param" value="<?php echo esc_attr( $settings['share_url_param'] ?? 'share' ); ?>" class="regular-text">
						<p class="description">Örn: <code>share</code> → /profilim/?share=TOKEN</p>
					</td>
				</tr>
				<tr>
					<th>Varsayılan Geçerlilik (Gün)</th>
					<td>
						<input type="number" name="default_expiry" value="<?php echo absint( $settings['default_expiry'] ?? 0 ); ?>" class="small-text" min="0">
						<p class="description">0 = süresiz</p>
					</td>
				</tr>
				<tr>
					<th>Public Paylaşıma İzin Ver</th>
					<td>
						<label><input type="checkbox" name="allow_public" value="1" <?php checked( ! empty( $settings['allow_public'] ) ); ?>> Aktif</label>
					</td>
				</tr>
			</table>

			<h3>Public URL Formatı</h3>
			<p>Şu an <strong>query param</strong> yöntemi kullanılmaktadır:</p>
			<code>/profilim/?share=TOKEN</code>
			<p>Paylaşım tokenları <code>random_bytes(24)</code> ile kriptografik olarak üretilir.</p>

			<?php submit_button( 'Paylaşım Ayarlarını Kaydet' ); ?>
		</form>
		<?php
	}

	private function save_auth_settings() {
		$bool_keys = array(
			'enable_profile_registration_page',
			'enable_google_login_hint',
			'enable_turnstile_hint',
			'block_wp_admin_for_subscribers',
			'hide_admin_bar_for_subscribers',
			'require_email_verification',
			'rate_limit_registration_enabled',
			'rate_limit_login_enabled',
		);
		$page_keys = array(
			'profile_register_page_id',
			'profile_login_page_id',
			'redirect_after_login_page_id',
			'redirect_after_register_page_id',
		);

		$settings = array();
		foreach ( $bool_keys as $k ) {
			$settings[ $k ] = ! empty( $_POST[ $k ] ) ? 1 : 0;
		}
		foreach ( $page_keys as $k ) {
			$settings[ $k ] = absint( $_POST[ $k ] ?? 0 );
		}

		update_option( 'hap_profile_auth_settings', $settings );
		add_settings_error( 'hap_profile', 'saved', 'Üyelik ve güvenlik ayarları kaydedildi.', 'updated' );
	}

	private function render_auth_tab() {
		$s = wp_parse_args(
			get_option( 'hap_profile_auth_settings', array() ),
			HAP_Profile_Auth::defaults()
		);
		settings_errors( 'hap_profile' );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_auth">

			<h2>Üyelik ve Güvenlik</h2>

			<div class="notice notice-info inline" style="margin:0 0 18px">
				<p>
					<strong>Faz 1:</strong> Bu sekme ayar altyapısını ve shortcode iskeletlerini hazırlar.
					Gerçek Google OAuth ve Turnstile entegrasyonu ileri fazda bu hook noktalarına bağlanacaktır.
					Hiçbir secret veya API key bu eklenti içinde saklanmaz.
				</p>
				<p>
					Shortcode'lar: <code>[hap_profile_register]</code> — <code>[hap_profile_login]</code>
				</p>
			</div>

			<!-- SAYFA ATAMALARI -->
			<h3>Sayfa Atamaları</h3>
			<table class="form-table">
				<tr>
					<th>Özel Kayıt Sistemi Aktif</th>
					<td>
						<label>
							<input type="checkbox" name="enable_profile_registration_page" value="1"
							       <?php checked( ! empty( $s['enable_profile_registration_page'] ) ); ?>>
							Aktif — Shortcode ile özel kayıt sayfası kullanılsın
						</label>
						<p class="description">Pasifken WordPress varsayılan kayıt sistemi çalışır.</p>
					</td>
				</tr>
				<tr>
					<th>Kayıt Sayfası</th>
					<td>
						<?php wp_dropdown_pages( array(
							'name'              => 'profile_register_page_id',
							'selected'          => absint( $s['profile_register_page_id'] ),
							'show_option_none'  => '— Seçiniz —',
							'option_none_value' => 0,
						) ); ?>
						<p class="description">
							<code>[hap_profile_register]</code> shortcode'unun bulunduğu sayfa.
						</p>
					</td>
				</tr>
				<tr>
					<th>Giriş Sayfası</th>
					<td>
						<?php wp_dropdown_pages( array(
							'name'              => 'profile_login_page_id',
							'selected'          => absint( $s['profile_login_page_id'] ),
							'show_option_none'  => '— Seçiniz —',
							'option_none_value' => 0,
						) ); ?>
						<p class="description">
							<code>[hap_profile_login]</code> shortcode'unun bulunduğu sayfa.
						</p>
					</td>
				</tr>
				<tr>
					<th>Giriş Sonrası Yönlendirme</th>
					<td>
						<?php wp_dropdown_pages( array(
							'name'              => 'redirect_after_login_page_id',
							'selected'          => absint( $s['redirect_after_login_page_id'] ),
							'show_option_none'  => '— WordPress Varsayılanı —',
							'option_none_value' => 0,
						) ); ?>
						<p class="description">Subscriber giriş yaptığında yönlendirilecek sayfa. Admin/editör etkilenmez.</p>
					</td>
				</tr>
				<tr>
					<th>Kayıt Sonrası Yönlendirme</th>
					<td>
						<?php wp_dropdown_pages( array(
							'name'              => 'redirect_after_register_page_id',
							'selected'          => absint( $s['redirect_after_register_page_id'] ),
							'show_option_none'  => '— WordPress Varsayılanı —',
							'option_none_value' => 0,
						) ); ?>
					</td>
				</tr>
			</table>

			<!-- SHORTCODE ÖNİZLEME -->
			<h3>Shortcode Referansı</h3>
			<table class="widefat" style="max-width:600px;margin-bottom:24px">
				<tr>
					<td><code>[hap_profile_register]</code></td>
					<td>Özel kayıt formu (iskelet)</td>
				</tr>
				<tr>
					<td><code>[hap_profile_login]</code></td>
					<td>Özel giriş formu (iskelet)</td>
				</tr>
			</table>

			<!-- ENTEGRASYON İPUÇLARI -->
			<h3>Entegrasyon Hazırlıkları</h3>
			<table class="form-table">
				<tr>
					<th>Google Giriş Butonu Göster</th>
					<td>
						<label>
							<input type="checkbox" name="enable_google_login_hint" value="1"
							       <?php checked( ! empty( $s['enable_google_login_hint'] ) ); ?>>
							Placeholder buton göster (Faz 1 — gerçek OAuth bağlı değil)
						</label>
						<p class="description">
							Gerçek Google OAuth entegrasyonu için <code>hap_google_login_button</code> hook'una bağlanın.<br>
							<strong>Bu eklenti içinde Google Client Secret saklanmaz.</strong>
						</p>
					</td>
				</tr>
				<tr>
					<th>Turnstile Bot Koruması</th>
					<td>
						<label>
							<input type="checkbox" name="enable_turnstile_hint" value="1"
							       <?php checked( ! empty( $s['enable_turnstile_hint'] ) ); ?>>
							Turnstile alanı göster (Faz 1 — gerçek doğrulama bağlı değil)
						</label>
						<p class="description">
							Gerçek Turnstile entegrasyonu için <code>hap_turnstile_field</code> hook'una bağlanın.<br>
							<strong>Bu eklenti içinde Turnstile Secret saklanmaz.</strong>
						</p>
					</td>
				</tr>
			</table>

			<!-- GÜVENLİK DAVRANIŞLARI -->
			<h3>Güvenlik Davranışları</h3>
			<table class="form-table">
				<tr>
					<th>wp-admin Subscriber Engellemesi</th>
					<td>
						<label>
							<input type="checkbox" name="block_wp_admin_for_subscribers" value="1"
							       <?php checked( ! empty( $s['block_wp_admin_for_subscribers'] ) ); ?>>
							Aktif — Subscriber kullanıcıları wp-admin'e girmeye çalışırsa profil sayfasına yönlendir
						</label>
						<p class="description">
							Yönetici (manage_options) ve içerik yöneticileri etkilenmez.
							Yönlendirme URL'ini özelleştirmek için <code>hap_block_admin_redirect_url</code> filtresini kullanın.
						</p>
					</td>
				</tr>
				<tr>
					<th>Admin Bar Gizleme</th>
					<td>
						<label>
							<input type="checkbox" name="hide_admin_bar_for_subscribers" value="1"
							       <?php checked( ! empty( $s['hide_admin_bar_for_subscribers'] ) ); ?>>
							Aktif — Subscriber kullanıcılarda üst admin çubuğunu gizle
						</label>
					</td>
				</tr>
				<tr>
					<th>E-posta Doğrulama</th>
					<td>
						<label>
							<input type="checkbox" name="require_email_verification" value="1"
							       <?php checked( ! empty( $s['require_email_verification'] ) ); ?>>
							Kayıt sonrası e-posta doğrulama zorunlu
						</label>
						<p class="description">
							Faz 1'de bu ayar form şablonunda bilgilendirme mesajı gösterir.
							Gerçek doğrulama mantığı <code>hap_process_register_form</code> hook'u ile eklenir.
						</p>
					</td>
				</tr>
			</table>

			<!-- RATE LIMITING -->
			<h3>Rate Limiting (Kaba Kuvvet Koruması)</h3>
			<div class="notice notice-warning inline"><p>Faz 1: Rate limiting altyapısı hazır, transient tabanlı sayaç iskelet olarak çalışır. Gerçek blokaj Faz 2'de etkinleştirilecektir.</p></div>
			<table class="form-table">
				<tr>
					<th>Kayıt Hız Sınırı</th>
					<td>
						<label>
							<input type="checkbox" name="rate_limit_registration_enabled" value="1"
							       <?php checked( ! empty( $s['rate_limit_registration_enabled'] ) ); ?>>
							IP başına kayıt denemelerini sınırla (iskelet aktif)
						</label>
					</td>
				</tr>
				<tr>
					<th>Giriş Hız Sınırı</th>
					<td>
						<label>
							<input type="checkbox" name="rate_limit_login_enabled" value="1"
							       <?php checked( ! empty( $s['rate_limit_login_enabled'] ) ); ?>>
							IP başına giriş denemelerini sınırla (iskelet aktif)
						</label>
					</td>
				</tr>
			</table>

			<!-- HOOK REFERANSI -->
			<h3>Faz 2 Hook Referansı</h3>
			<table class="widefat" style="max-width:750px">
				<thead><tr><th>Hook</th><th>Tür</th><th>Açıklama</th></tr></thead>
				<tbody>
					<tr><td><code>hap_google_login_button</code></td><td>action</td><td>Google OAuth butonu — parametre: 'login' | 'register'</td></tr>
					<tr><td><code>hap_turnstile_field</code></td><td>action</td><td>Turnstile widget — parametre: 'login' | 'register'</td></tr>
					<tr><td><code>hap_process_register_form</code></td><td>action_ref_array</td><td>Kayıt form işleme — referans: &$error_msg, &$success_msg</td></tr>
					<tr><td><code>hap_process_login_form</code></td><td>action_ref_array</td><td>Giriş form işleme — referans: &$error_msg, &$success_msg</td></tr>
					<tr><td><code>hap_login_redirect_url</code></td><td>filter</td><td>Giriş sonrası URL — parametre: $url, $user</td></tr>
					<tr><td><code>hap_register_redirect_url</code></td><td>filter</td><td>Kayıt sonrası URL — parametre: $url, $user_id</td></tr>
					<tr><td><code>hap_block_admin_redirect_url</code></td><td>filter</td><td>wp-admin engellemede yönlendirme URL'i</td></tr>
				</tbody>
			</table>

			<?php submit_button( 'Üyelik Ayarlarını Kaydet' ); ?>
		</form>
		<?php
	}

	private function render_updater_tab() {
		$updater  = new HAP_Profile_Updater();
		$s        = $updater->get_settings();
		$notice   = $updater->get_update_notice( true );
		$last_sha = (string) get_option( 'hap_last_update_sha', '' );
		$last_upd = (string) get_option( 'hap_last_update', '' );
		$last_err = $updater->get_last_update_error();
		$debug    = $updater->get_last_update_debug();

		$update_result = isset( $_GET['update'] ) ? sanitize_key( $_GET['update'] ) : '';
		?>
		<h2>GitHub'dan Güncelle</h2>

		<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
			<p>
				<?php if ( $notice['type'] === 'success' ) : ?>✅<?php else : ?>❌<?php endif; ?>
				<strong><?php echo esc_html( $notice['message'] ); ?></strong>
				<span style="color:#888;font-size:.85em;margin-left:8px"><?php echo esc_html( $notice['time'] ); ?></span>
			</p>
		</div>
		<?php endif; ?>

		<!-- SON GÜNCELLEME DURUMU -->
		<div class="hap-updater-status-box" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:16px 20px;margin-bottom:20px;max-width:700px">
			<table style="width:100%;border-collapse:collapse">
				<tr>
					<td style="width:180px;padding:4px 0;color:#555;font-weight:600">Mevcut Sürüm</td>
					<td><code><?php echo esc_html( HAP_Profile_Updater::get_version_string() ); ?></code></td>
				</tr>
				<tr>
					<td style="padding:4px 0;color:#555;font-weight:600">Son Güncelleme SHA</td>
					<td>
						<?php if ( $last_sha ) : ?>
						<code><?php echo esc_html( substr( $last_sha, 0, 7 ) ); ?></code>
						<?php if ( ! empty( $s['repo'] ) ) : ?>
						<a href="https://github.com/<?php echo esc_attr( $s['repo'] ); ?>/commit/<?php echo esc_attr( $last_sha ); ?>"
						   target="_blank" style="margin-left:8px;font-size:.82rem">GitHub'da Gör ↗</a>
						<?php endif; ?>
						<?php else : ?>
						<span style="color:#aaa">—</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:4px 0;color:#555;font-weight:600">Son Güncelleme Tarihi</td>
					<td><?php echo $last_upd ? esc_html( $last_upd ) : '<span style="color:#aaa">—</span>'; ?></td>
				</tr>
				<tr>
					<td style="padding:4px 0;color:#555;font-weight:600">Repo</td>
					<td>
						<?php if ( ! empty( $s['repo'] ) ) : ?>
						<a href="https://github.com/<?php echo esc_attr( $s['repo'] ); ?>" target="_blank">
							<?php echo esc_html( $s['repo'] ); ?>
						</a>
						<?php else : ?>
						<span style="color:#aaa">Yapılandırılmamış</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:4px 0;color:#555;font-weight:600">Branch</td>
					<td><code><?php echo esc_html( $s['branch'] ?: 'main' ); ?></code></td>
				</tr>
			</table>
		</div>

		<!-- GÜNCELLE BUTONU -->
		<?php if ( ! empty( $s['repo'] ) ) : ?>
		<div style="display:flex;align-items:center;gap:12px;margin-bottom:28px;flex-wrap:wrap">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="hap-update-form">
				<?php wp_nonce_field( 'hap_update_from_github', '_wpnonce' ); ?>
				<input type="hidden" name="action" value="hap_update_from_github">
				<button type="submit" class="button button-primary button-hero" id="hap-do-update"
				        onclick="return confirm('GitHub\'tan en son sürüm indirilip mevcut dosyaların üzerine yazılacak. Devam edilsin mi?')">
					⬇️ GitHub\'dan Şimdi Güncelle
				</button>
			</form>
			<button type="button" class="button button-secondary" id="hap-check-version">
				🔍 Son Commit\'i Kontrol Et
			</button>
			<span id="hap-version-result" style="font-size:.88rem;color:#555"></span>
		</div>
		<?php else : ?>
		<div class="notice notice-warning inline"><p>Güncelleme butonu için önce aşağıdan repo ayarlarını kaydedin.</p></div>
		<?php endif; ?>

		<!-- AYARLAR FORMU -->
		<form method="post" action="">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_updater">
			<h3>Güncelleme Ayarları</h3>
			<table class="form-table" style="max-width:700px">
				<tr>
					<th>GitHub Repo <span style="color:red">*</span></th>
					<td>
						<input type="text" name="hap_updater_repo"
						       value="<?php echo esc_attr( $s['repo'] ); ?>"
						       class="regular-text" placeholder="kullanici/hesaplamaa-profile">
						<p class="description">Format: <code>kullanici_adi/repo_adi</code> — Örn: <code>alperates58/hesaplamaa-profile</code></p>
					</td>
				</tr>
				<tr>
					<th>Branch</th>
					<td>
						<input type="text" name="hap_updater_branch"
						       value="<?php echo esc_attr( $s['branch'] ?: 'main' ); ?>"
						       class="small-text" placeholder="main">
						<p class="description">Güncellemelerin çekileceği branch. Genellikle <code>main</code> veya <code>master</code>.</p>
					</td>
				</tr>
				<tr>
					<th>GitHub Token (İsteğe Bağlı)</th>
					<td>
						<input type="password" name="hap_updater_token"
						       value="<?php echo esc_attr( $s['token'] ); ?>"
						       class="regular-text" autocomplete="new-password" placeholder="ghp_...">
						<p class="description">
							Özel (private) repo için gereklidir. Public repo için boş bırakın.<br>
							Token en az <code>repo</code> veya <code>contents:read</code> iznine sahip olmalıdır.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Ayarları Kaydet' ); ?>
		</form>

		<!-- HATA / DEBUG -->
		<?php if ( $last_err ) : ?>
		<div style="margin-top:24px">
			<h3 style="color:#c00">Son Güncelleme Hatası</h3>
			<div class="notice notice-error inline"><p><?php echo esc_html( $last_err ); ?></p></div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $debug ) ) : ?>
		<div style="margin-top:16px">
			<h3>Debug Bilgisi <small style="font-weight:400;color:#888">(son güncelleme)</small></h3>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th>Anahtar</th><th>Değer</th></tr></thead>
				<tbody>
				<?php foreach ( $debug as $k => $v ) : ?>
				<tr>
					<td><code><?php echo esc_html( $k ); ?></code></td>
					<td><?php echo esc_html( $v ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<script>
		jQuery(function($){
			$('#hap-check-version').on('click', function(){
				var $btn = $(this);
				var $res = $('#hap-version-result');
				$btn.prop('disabled', true).text('Kontrol ediliyor...');
				$res.text('');
				$.post(hapAdmin.ajaxUrl, {
					action: 'hap_check_github_version',
					nonce: hapAdmin.nonce
				}, function(res){
					$btn.prop('disabled', false).text('🔍 Son Commit\'i Kontrol Et');
					if(res.success){
						$res.html('✅ GitHub\'daki son commit: <strong>' + res.data.sha + '</strong>');
					} else {
						$res.html('❌ ' + (res.data || 'Hata oluştu.'));
					}
				}).fail(function(){
					$btn.prop('disabled', false).text('🔍 Son Commit\'i Kontrol Et');
					$res.text('❌ Bağlantı hatası.');
				});
			});

			$('#hap-do-update').closest('form').on('submit', function(){
				$('#hap-do-update').prop('disabled', true).text('⬇️ Güncelleniyor... (lütfen bekleyin)');
			});
		});
		</script>
		<?php
	}

	private function render_health_tab() {
		?>
		<h2>Sağlık Kontrolü</h2>
		<?php $this->health->render(); ?>
		<?php
	}

	/* ============================================================
	   HELPERS
	   ============================================================ */

	private function get_section_labels() {
		return array(
			'overview'           => 'Genel Bakış',
			'health_lifestyle'   => 'Sağlık & Yaşam',
			'sport_activity'     => 'Spor & Aktivite',
			'astrology'          => 'Astroloji',
			'astrology_houses'   => 'Astroloji Evleri',
			'moon_sky'           => 'Ay & Gökyüzü',
			'numerology'         => 'Numeroloji',
			'chinese_astrology'  => 'Çin Astrolojisi',
			'symbolic'           => 'Sembolik',
			'tarot'              => 'Tarot',
		);
	}

	private function get_profile_status_labels() {
		return array(
			'profile_core'     => 'Profile Core',
			'profile_optional' => 'Profile Optional',
			'tool_only'        => 'Araç (Tool Only)',
			'disabled'         => 'Devre Dışı',
		);
	}

	private function get_mfb_labels() {
		return array(
			'hide'         => 'Gizle',
			'show_prompt'  => 'Bilgi Eksik Uyarısı Göster',
			'open_form'    => 'Form Aç',
			'link_to_tool' => 'Araca Link',
		);
	}

	private function get_avail_labels() {
		return array(
			'active'  => 'Aktif',
			'planned' => 'Planlandı',
			'missing' => 'Kayıp',
		);
	}
}
