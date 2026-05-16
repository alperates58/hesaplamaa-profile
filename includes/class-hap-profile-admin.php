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
			'general'      => 'Genel Ayarlar',
			'fields'       => 'Profil Alanları',
			'onboarding'   => 'Onboarding Adımları',
			'modules'      => 'Profil Modülleri',
			'preset'       => 'Gelecek Modül Presetleri',
			'ai_templates' => 'AI Yorum Şablonları',
			'share'        => 'Paylaşım Ayarları',
			'auth'         => 'Üyelik ve Güvenlik',
			'updater'      => 'GitHub Güncelleme',
			'health'       => 'Sağlık Kontrolü',
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
		if ( false === strpos( $hook, 'hesaplamaa-profile' ) ) {
			return;
		}
		wp_enqueue_style( 'hap-admin', HAP_PLUGIN_URL . 'assets/admin.css', array(), HAP_VERSION );
		wp_enqueue_script( 'hap-admin', HAP_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), HAP_VERSION, true );
		wp_localize_script(
			'hap-admin',
			'hapAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hap_admin_nonce' ),
				'i18n'    => array(
					'delete_confirm' => 'Bu modülü silmek istediğinizden emin misiniz?',
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'hesaplamaa-profile' ) );
		}

		$this->handle_form_submissions();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		if ( ! isset( $this->tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap hap-admin-wrap">
			<h1 class="hap-admin-title">Hesaplamaa Profile <span class="hap-version">v<?php echo esc_html( HAP_VERSION ); ?></span></h1>
			<nav class="nav-tab-wrapper hap-nav-tabs">
				<?php foreach ( $this->tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hesaplamaa-profile&tab=' . $key ) ); ?>" class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="hap-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'fields':
						$this->render_fields_tab();
						break;
					case 'onboarding':
						$this->render_onboarding_tab();
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
					default:
						$this->render_general_tab();
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

		switch ( sanitize_key( $_POST['hap_action'] ) ) {
			case 'save_general':
				$this->save_general_settings();
				break;
			case 'save_fields':
				$this->save_fields_settings();
				break;
			case 'save_onboarding':
				$this->save_onboarding_settings();
				break;
			case 'save_module_inline':
				$this->save_module_inline();
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
		);
		update_option( 'hap_profile_settings', $settings );
		add_settings_error( 'hap_profile', 'saved', 'Genel ayarlar kaydedildi.', 'updated' );
	}

	private function save_fields_settings() {
		$fields = array();
		foreach ( (array) ( $_POST['hap_fields'] ?? array() ) as $field_key => $field ) {
			$field['field_key'] = $field_key;
			$field['active']    = ! empty( $field['active'] );
			$field['required_for_minimum_profile'] = ! empty( $field['required_for_minimum_profile'] );
			$field['sensitive'] = ! empty( $field['sensitive'] );
			$field['public_visible_default'] = ! empty( $field['public_visible_default'] );
			$fields[] = $field;
		}
		HAP_Profile_Fields::save_fields( $fields );
		add_settings_error( 'hap_profile', 'saved', 'Profil alanları kaydedildi.', 'updated' );
	}

	private function save_onboarding_settings() {
		$steps = array();
		foreach ( (array) ( $_POST['hap_steps'] ?? array() ) as $step_key => $step ) {
			$step['step_key']    = $step_key;
			$step['is_required'] = ! empty( $step['is_required'] );
			$step['active']      = ! empty( $step['active'] );
			$steps[]             = $step;
		}
		HAP_Profile_Fields::save_steps( $steps );
		add_settings_error( 'hap_profile', 'saved', 'Onboarding adımları kaydedildi.', 'updated' );
	}

	private function save_module_inline() {
		$module_id = absint( $_POST['module_id'] ?? 0 );
		$existing  = $this->modules->get_module_by_id( $module_id );
		if ( ! $existing ) {
			add_settings_error( 'hap_profile', 'error', 'Modül bulunamadı.', 'error' );
			return;
		}

		$data = array_merge(
			$existing,
			array(
				'profile_status'            => sanitize_key( $_POST['profile_status'] ?? $existing['profile_status'] ),
				'section'                   => sanitize_key( $_POST['section'] ?? $existing['section'] ),
				'required_fields'           => array_map( 'sanitize_key', (array) ( $_POST['required_fields'] ?? array() ) ),
				'optional_fields'           => array_map( 'sanitize_key', (array) ( $_POST['optional_fields'] ?? array() ) ),
				'missing_fields_behavior'   => sanitize_key( $_POST['missing_fields_behavior'] ?? $existing['missing_fields_behavior'] ),
				'ai_enabled'                => ! empty( $_POST['ai_enabled'] ) ? 1 : 0,
				'sort_order'                => absint( $_POST['sort_order'] ?? $existing['sort_order'] ),
				'notes'                     => sanitize_textarea_field( $_POST['notes'] ?? $existing['notes'] ),
				'availability_status'       => sanitize_key( $_POST['availability_status'] ?? $existing['availability_status'] ),
				'result_enabled'            => ! empty( $_POST['result_enabled'] ) ? 1 : 0,
				'onboarding_prompt_enabled' => ! empty( $_POST['onboarding_prompt_enabled'] ) ? 1 : 0,
				'ai_include'                => ! empty( $_POST['ai_include'] ) ? 1 : 0,
				'share_include_default'     => ! empty( $_POST['share_include_default'] ) ? 1 : 0,
				'runner_type'               => sanitize_key( $_POST['runner_type'] ?? ( $existing['runner_type'] ?? 'none' ) ),
				'input_mapping'             => sanitize_textarea_field( wp_unslash( $_POST['input_mapping'] ?? ( $existing['input_mapping'] ?? '' ) ) ),
				'output_mapping'            => sanitize_textarea_field( wp_unslash( $_POST['output_mapping'] ?? ( $existing['output_mapping'] ?? '' ) ) ),
				'runner_callback'           => sanitize_text_field( wp_unslash( $_POST['runner_callback'] ?? ( $existing['runner_callback'] ?? '' ) ) ),
				'ajax_action'               => sanitize_key( $_POST['ajax_action'] ?? ( $existing['ajax_action'] ?? '' ) ),
				'result_selector'           => sanitize_text_field( wp_unslash( $_POST['result_selector'] ?? ( $existing['result_selector'] ?? '' ) ) ),
				'tool_url'                  => esc_url_raw( wp_unslash( $_POST['tool_url'] ?? ( $existing['tool_url'] ?? '' ) ) ),
				'runner_status'             => sanitize_key( $_POST['runner_status'] ?? ( $existing['runner_status'] ?? '' ) ),
				'runner_notes'              => sanitize_textarea_field( wp_unslash( $_POST['runner_notes'] ?? ( $existing['runner_notes'] ?? '' ) ) ),
			)
		);

		$this->modules->save_module( $data, $module_id );
		add_settings_error( 'hap_profile', 'saved', 'Modül kaydedildi.', 'updated' );
	}

	public function ajax_import_modules() {
		wp_send_json_error( array( 'message' => 'Bu sürümde toplu import kapalı.' ) );
	}

	public function ajax_delete_module() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		$id = absint( $_POST['module_id'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) || ! $id ) {
			wp_send_json_error( array( 'message' => 'Yetki veya ID hatası.' ) );
		}
		$this->modules->delete_module( $id );
		wp_send_json_success( array( 'message' => 'Modül silindi.' ) );
	}

	public function ajax_save_single_module() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}
		$_POST['hap_action'] = 'save_module_inline';
		$this->save_module_inline();
		wp_send_json_success( array( 'message' => 'Modül kaydedildi.' ) );
	}

	public function ajax_sync_from_suite() {
		wp_send_json_error( array( 'message' => 'Bu görev kapsamında Suite sync değiştirilmedi.' ) );
	}

	public function ajax_bulk_modules() {
		wp_send_json_error( array( 'message' => 'Bu sürümde toplu işlem devre dışı.' ) );
	}

	public function ajax_apply_runner_presets() {
		wp_send_json_error( array( 'message' => 'Preset uygulama bu sürümde kapalı.' ) );
	}

	public function ajax_inspect_suite_modules() {
		wp_send_json_error( array( 'message' => 'Inspector bu sürümde kapalı.' ) );
	}

	private function render_general_tab() {
		$settings = wp_parse_args( get_option( 'hap_profile_settings', array() ), array(
			'system_active' => 1,
			'profile_page_id' => 0,
			'noindex_profiles' => 1,
			'premium_dashboard' => 1,
			'shareable_profile' => 1,
			'hide_sensitive_on_share' => 1,
		) );
		settings_errors( 'hap_profile' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_general">
			<h2>Genel Ayarlar</h2>
			<table class="form-table">
				<tr><th>Sistem Aktif</th><td><label><input type="checkbox" name="system_active" value="1" <?php checked( ! empty( $settings['system_active'] ) ); ?>> Aktif</label></td></tr>
				<tr><th>Profil Sayfası</th><td><?php wp_dropdown_pages( array( 'name' => 'profile_page_id', 'selected' => absint( $settings['profile_page_id'] ), 'show_option_none' => '— Seçiniz —', 'option_none_value' => 0 ) ); ?></td></tr>
				<tr><th>noindex</th><td><label><input type="checkbox" name="noindex_profiles" value="1" <?php checked( ! empty( $settings['noindex_profiles'] ) ); ?>> Aktif</label></td></tr>
				<tr><th>Premium Dashboard</th><td><label><input type="checkbox" name="premium_dashboard" value="1" <?php checked( ! empty( $settings['premium_dashboard'] ) ); ?>> Aktif</label></td></tr>
				<tr><th>Paylaşım</th><td><label><input type="checkbox" name="shareable_profile" value="1" <?php checked( ! empty( $settings['shareable_profile'] ) ); ?>> Aktif</label></td></tr>
				<tr><th>Hassas verileri gizle</th><td><label><input type="checkbox" name="hide_sensitive_on_share" value="1" <?php checked( ! empty( $settings['hide_sensitive_on_share'] ) ); ?>> Aktif</label></td></tr>
			</table>
			<?php submit_button( 'Genel ayarları kaydet' ); ?>
		</form>
		<?php
	}

	private function render_fields_tab() {
		$fields = HAP_Profile_Fields::get_fields();
		$steps  = HAP_Profile_Fields::get_active_steps();
		settings_errors( 'hap_profile' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_fields">
			<h2>Profil Alanları</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Alan</th>
						<th>Etiket</th>
						<th>Tip</th>
						<th>Adım</th>
						<th>Aktif</th>
						<th>Minimum profil zorunlu mu?</th>
						<th>Hassas mı?</th>
						<th>Bu alanla açılan modül sayısı</th>
						<th>Düzenle</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<?php $unlocked = HAP_Profile_Fields::get_modules_for_field( $field['field_key'] ); ?>
						<tr>
							<td><code><?php echo esc_html( $field['field_key'] ); ?></code></td>
							<td><input type="text" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>"></td>
							<td><input type="text" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][type]" value="<?php echo esc_attr( $field['type'] ); ?>"></td>
							<td>
								<select name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][step_key]">
									<?php foreach ( $steps as $step ) : ?>
										<option value="<?php echo esc_attr( $step['step_key'] ); ?>" <?php selected( $field['step_key'], $step['step_key'] ); ?>><?php echo esc_html( $step['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="checkbox" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][active]" value="1" <?php checked( ! empty( $field['active'] ) ); ?>></td>
							<td><input type="checkbox" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][required_for_minimum_profile]" value="1" <?php checked( ! empty( $field['required_for_minimum_profile'] ) ); ?>></td>
							<td><input type="checkbox" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][sensitive]" value="1" <?php checked( ! empty( $field['sensitive'] ) ); ?>></td>
							<td><?php echo esc_html( count( $unlocked ) ); ?></td>
							<td>Detay aşağıda</td>

							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][options]" value="<?php echo esc_attr( wp_json_encode( $field['options'] ) ); ?>">
							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][help_text]" value="<?php echo esc_attr( $field['help_text'] ); ?>">
							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][sort_order]" value="<?php echo esc_attr( $field['sort_order'] ); ?>">
							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][public_visible_default]" value="<?php echo esc_attr( $field['public_visible_default'] ); ?>">
							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][validation_rule]" value="<?php echo esc_attr( $field['validation_rule'] ); ?>">
							<input type="hidden" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][user_meta_key]" value="<?php echo esc_attr( $field['user_meta_key'] ); ?>">
						</tr>
						<tr>
							<td colspan="9">
								<strong>Alan detayı:</strong>
								<div>field_key: <code><?php echo esc_html( $field['field_key'] ); ?></code></div>
								<div>help_text: <input type="text" class="regular-text" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][help_text]" value="<?php echo esc_attr( $field['help_text'] ); ?>"></div>
								<div>placeholder: <input type="text" class="regular-text" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>"></div>
								<div>sort_order: <input type="number" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][sort_order]" value="<?php echo esc_attr( $field['sort_order'] ); ?>"></div>
								<div>public_visible_default: <input type="checkbox" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][public_visible_default]" value="1" <?php checked( ! empty( $field['public_visible_default'] ) ); ?>></div>
								<div>validation_rule: <input type="text" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][validation_rule]" value="<?php echo esc_attr( $field['validation_rule'] ); ?>"></div>
								<div>user_meta_key: <input type="text" class="regular-text" name="hap_fields[<?php echo esc_attr( $field['field_key'] ); ?>][user_meta_key]" value="<?php echo esc_attr( $field['user_meta_key'] ); ?>"></div>
								<div>modules_unlocked:
									<?php if ( empty( $unlocked ) ) : ?>
										<span>Yok</span>
									<?php else : ?>
										<?php foreach ( $unlocked as $module ) : ?>
											<span class="hap-tag"><?php echo esc_html( $module['title'] ); ?> (<?php echo esc_html( $module['section'] ); ?> / <?php echo esc_html( $module['profile_status'] ); ?>)</span>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( 'Alanları Kaydet' ); ?>
		</form>
		<?php
	}

	private function render_onboarding_tab() {
		$steps = HAP_Profile_Fields::get_steps();
		settings_errors( 'hap_profile' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_onboarding">
			<h2>Onboarding Adımları</h2>
			<table class="widefat striped">
				<thead><tr><th>step_key</th><th>title</th><th>description</th><th>icon</th><th>sort_order</th><th>is_required</th><th>active</th><th>completion_rule</th></tr></thead>
				<tbody>
				<?php foreach ( $steps as $step ) : ?>
					<tr>
						<td><code><?php echo esc_html( $step['step_key'] ); ?></code></td>
						<td><input type="text" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][title]" value="<?php echo esc_attr( $step['title'] ); ?>"></td>
						<td><input type="text" class="regular-text" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][description]" value="<?php echo esc_attr( $step['description'] ); ?>"></td>
						<td><input type="text" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][icon]" value="<?php echo esc_attr( $step['icon'] ); ?>"></td>
						<td><input type="number" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][sort_order]" value="<?php echo esc_attr( $step['sort_order'] ); ?>"></td>
						<td><input type="checkbox" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][is_required]" value="1" <?php checked( ! empty( $step['is_required'] ) ); ?>></td>
						<td><input type="checkbox" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][active]" value="1" <?php checked( ! empty( $step['active'] ) ); ?>></td>
						<td><input type="text" name="hap_steps[<?php echo esc_attr( $step['step_key'] ); ?>][completion_rule]" value="<?php echo esc_attr( $step['completion_rule'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( 'Onboarding Adımlarını Kaydet' ); ?>
		</form>
		<?php
	}

	private function render_modules_tab() {
		$modules = $this->modules->get_modules( array( 'limit' => 500 ) );
		$fields  = HAP_Profile_Fields::get_active_fields();
		$field_options = array();
		foreach ( $fields as $field ) {
			$field_options[ $field['field_key'] ] = $field['label'];
		}
		settings_errors( 'hap_profile' );
		?>
		<h2>Profil Modülleri</h2>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th>Başlık</th><th>Slug</th><th>Durum</th><th>required_fields</th><th>optional_fields</th><th>result_enabled</th><th>onboarding_prompt_enabled</th><th>ai_include</th><th>share_include_default</th></tr></thead>
			<tbody>
			<?php foreach ( $modules as $mod ) : ?>
				<tr>
					<td><?php echo absint( $mod['id'] ); ?></td>
					<td><?php echo esc_html( $mod['title'] ); ?></td>
					<td><code><?php echo esc_html( $mod['slug'] ); ?></code></td>
					<td><?php echo esc_html( $mod['profile_status'] ); ?></td>
					<td><?php echo esc_html( implode( ', ', $this->modules->decode_fields_json( $mod['required_fields'] ) ) ); ?></td>
					<td><?php echo esc_html( implode( ', ', $this->modules->decode_fields_json( $mod['optional_fields'] ?? array() ) ) ); ?></td>
					<td><?php echo ! empty( $mod['result_enabled'] ) ? '1' : '0'; ?></td>
					<td><?php echo ! empty( $mod['onboarding_prompt_enabled'] ) ? '1' : '0'; ?></td>
					<td><?php echo ! empty( $mod['ai_include'] ) ? '1' : '0'; ?></td>
					<td><?php echo ! empty( $mod['share_include_default'] ) ? '1' : '0'; ?></td>
				</tr>
				<tr>
					<td colspan="10">
						<form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:12px;align-items:start">
							<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
							<input type="hidden" name="hap_action" value="save_module_inline">
							<input type="hidden" name="module_id" value="<?php echo absint( $mod['id'] ); ?>">
							<div>
								<label>required_fields</label>
								<select name="required_fields[]" multiple size="6" style="width:100%">
									<?php foreach ( $field_options as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, $this->modules->decode_fields_json( $mod['required_fields'] ), true ) ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div>
								<label>optional_fields</label>
								<select name="optional_fields[]" multiple size="6" style="width:100%">
									<?php foreach ( $field_options as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, $this->modules->decode_fields_json( $mod['optional_fields'] ?? array() ), true ) ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div><label><input type="checkbox" name="result_enabled" value="1" <?php checked( ! empty( $mod['result_enabled'] ) ); ?>> result_enabled</label></div>
							<div><label><input type="checkbox" name="onboarding_prompt_enabled" value="1" <?php checked( ! empty( $mod['onboarding_prompt_enabled'] ) ); ?>> onboarding_prompt_enabled</label></div>
							<div><label><input type="checkbox" name="ai_include" value="1" <?php checked( ! empty( $mod['ai_include'] ) ); ?>> ai_include</label></div>
							<div><label><input type="checkbox" name="share_include_default" value="1" <?php checked( ! empty( $mod['share_include_default'] ) ); ?>> share_include_default</label></div>
							<div><?php submit_button( 'Kaydet', 'secondary', '', false ); ?></div>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_preset_tab() {
		echo '<p>Preset modül ekranı bu görevde değiştirilmedi.</p>';
	}

	private function render_ai_templates_tab() {
		echo '<p>AI şablon ekranı bu görevde değiştirilmedi.</p>';
	}

	private function render_share_tab() {
		echo '<p>Paylaşım ayarları ekranı bu görevde değiştirilmedi.</p>';
	}

	private function render_auth_tab() {
		echo '<p>Üyelik ve güvenlik ekranı bu görevde değiştirilmedi.</p>';
	}

	private function render_updater_tab() {
		echo '<p>Updater ekranı bu görevde değiştirilmedi.</p>';
	}

	private function render_health_tab() {
		echo '<h2>Sağlık Kontrolü</h2>';
		$this->health->render();
	}
}
