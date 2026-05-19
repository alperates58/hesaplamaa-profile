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
			'ai_deepseek'  => 'DeepSeek AI',
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
					case 'ai_deepseek':
						$this->render_ai_deepseek_tab();
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
			case 'save_updater':
				$this->save_updater_settings();
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
			case 'save_ai_deepseek':
				$this->save_ai_deepseek_settings();
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
			'use_full_page_template'  => ! empty( $_POST['use_full_page_template'] ) ? 1 : 0,
		);
		update_option( 'hap_profile_settings', $settings );
		add_settings_error( 'hap_profile', 'saved', 'Genel ayarlar kaydedildi.', 'updated' );
	}

	private function save_updater_settings() {
		$updater = new HAP_Profile_Updater();
		$updater->save_settings(
			array(
				'repo'   => $_POST['hap_updater_repo'] ?? '',
				'branch' => $_POST['hap_updater_branch'] ?? 'main',
			)
		);
		add_settings_error( 'hap_profile', 'saved', 'GitHub güncelleme ayarları kaydedildi.', 'updated' );
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
		wp_send_json_error( array( 'message' => 'Lütfen "Suite Metadata Senkronize Et" butonunu kullanın.' ) );
	}

	public function ajax_sync_suite_metadata() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		if ( ! class_exists( 'HAP_Suite_Module_Fields' ) || ! HAP_Suite_Module_Fields::table_exists() ) {
			wp_send_json_error( array( 'message' => 'Suite tablosu bulunamadı.' ) );
		}

		$dry_run = ! empty( $_POST['dry_run'] );
		$report  = HAP_Profile_Modules::sync_modules_from_suite( array(
			'dry_run'                   => $dry_run,
			'statuses'                  => array( 'profile_core', 'profile_optional' ),
			'update_result_enabled'     => true,
			'preserve_manual_overrides' => true,
		) );

		if ( isset( $report['error'] ) ) {
			wp_send_json_error( array( 'message' => $report['error'] ) );
		}

		wp_send_json_success( $report );
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
		$settings = wp_parse_args(
			get_option( 'hap_profile_settings', array() ),
			array(
				'system_active'          => 1,
				'profile_page_id'        => 0,
				'noindex_profiles'       => 1,
				'premium_dashboard'      => 1,
				'shareable_profile'      => 1,
				'hide_sensitive_on_share'=> 1,
				'use_full_page_template' => 1,
			)
		);
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
				<tr>
					<th>Tam genişlik template</th>
					<td>
						<label><input type="checkbox" name="use_full_page_template" value="1" <?php checked( ! empty( $settings['use_full_page_template'] ) ); ?>> Aktif</label>
						<p class="description">Etkinleştirildiğinde /profilim/ sayfası tema content container yerine tam genişlik plugin template ile render edilir.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Genel ayarları kaydet' ); ?>
		</form>
		<?php
	}

	private function render_fields_tab() {
		$fields       = HAP_Profile_Fields::get_fields();
		$steps        = HAP_Profile_Fields::get_steps();
		$suite_active = class_exists( 'HAP_Suite_Module_Fields' ) && HAP_Suite_Module_Fields::table_exists();
		settings_errors( 'hap_profile' );
		?>
		<div style="display:flex;gap:24px;align-items:flex-start">
		<div style="flex:1;min-width:0">

		<div style="margin-bottom:16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
			<h2 style="margin:0">Profil Alanları</h2>
			<?php if ( $suite_active ) : ?>
			<button type="button" class="button button-secondary" id="hap-sync-suite-fields">
				Suite Alanlarını Senkronize Et
			</button>
			<span id="hap-sync-suite-result" style="font-size:.85rem;color:#555"></span>
			<?php else : ?>
			<span style="color:#b32d2e;font-size:.85rem">Suite tablosu bulunamadı — senkronizasyon devre dışı.</span>
			<?php endif; ?>
		</div>

		<form method="post" id="hap-fields-form">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_fields">
			<table class="widefat striped" style="border-collapse:collapse">
				<thead>
					<tr>
						<th>Alan (field_key)</th>
						<th>Etiket</th>
						<th>Tip</th>
						<th>Adım</th>
						<th>Aktif</th>
						<th>Min. zorunlu</th>
						<th>AI dahil</th>
						<th>Hassas</th>
						<th>Suite modül</th>
						<th>Backend</th>
						<th>İşlem</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<?php
						$fk     = $field['field_key'];
						$impact = HAP_Profile_Fields::get_field_impact( $fk );
						$m_cnt  = $field['suite_module_count'] ?: $impact['module_count'] ?? 0;
						$b_cnt  = $field['suite_backend_supported_count'] ?: $impact['backend_count'] ?? 0;
						?>
						<tr class="hap-field-row" data-field-key="<?php echo esc_attr( $fk ); ?>" style="vertical-align:middle">
							<td>
								<code><?php echo esc_html( $fk ); ?></code>
								<?php if ( ( $field['source'] ?? '' ) === 'suite_discovered' ) : ?>
									<span style="font-size:.75rem;background:#e6f0fb;padding:1px 5px;border-radius:3px;color:#0077aa">Suite</span>
								<?php endif; ?>
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][source]" value="<?php echo esc_attr( $field['source'] ?? 'default' ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][options]" value="<?php echo esc_attr( wp_json_encode( $field['options'] ) ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][user_meta_key]" value="<?php echo esc_attr( $field['user_meta_key'] ); ?>">
							</td>
							<td><input type="text" name="hap_fields[<?php echo esc_attr( $fk ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" style="width:110px"></td>
							<td><input type="text" name="hap_fields[<?php echo esc_attr( $fk ); ?>][type]" value="<?php echo esc_attr( $field['type'] ); ?>" style="width:70px"></td>
							<td>
								<select name="hap_fields[<?php echo esc_attr( $fk ); ?>][step_key]" style="max-width:130px">
									<?php foreach ( $steps as $step ) : ?>
										<option value="<?php echo esc_attr( $step['step_key'] ); ?>" <?php selected( $field['step_key'], $step['step_key'] ); ?>><?php echo esc_html( $step['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td style="text-align:center"><input type="checkbox" name="hap_fields[<?php echo esc_attr( $fk ); ?>][active]" value="1" <?php checked( ! empty( $field['active'] ) ); ?>></td>
							<td style="text-align:center"><input type="checkbox" name="hap_fields[<?php echo esc_attr( $fk ); ?>][required_for_minimum_profile]" value="1" <?php checked( ! empty( $field['required_for_minimum_profile'] ) ); ?>></td>
							<td style="text-align:center"><input type="checkbox" name="hap_fields[<?php echo esc_attr( $fk ); ?>][ai_include]" value="1" <?php checked( ! empty( $field['ai_include'] ) || ! isset( $field['ai_include'] ) ); ?>></td>
							<td style="text-align:center"><input type="checkbox" name="hap_fields[<?php echo esc_attr( $fk ); ?>][sensitive]" value="1" <?php checked( ! empty( $field['sensitive'] ) ); ?>></td>
							<td style="text-align:center">
								<strong><?php echo esc_html( $m_cnt ); ?></strong>
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][suite_module_count]" value="<?php echo absint( $m_cnt ); ?>">
							</td>
							<td style="text-align:center">
								<?php echo esc_html( $b_cnt ); ?>
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][suite_backend_supported_count]" value="<?php echo absint( $b_cnt ); ?>">
							</td>
							<td>
								<?php if ( $suite_active ) : ?>
								<button type="button" class="button button-small hap-show-field-modules" data-field-key="<?php echo esc_attr( $fk ); ?>">
									Modülleri Gör
								</button>
								<?php endif; ?>
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][sort_order]" value="<?php echo absint( $field['sort_order'] ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][help_text]" value="<?php echo esc_attr( $field['help_text'] ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][validation_rule]" value="<?php echo esc_attr( $field['validation_rule'] ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][public_visible_default]" value="<?php echo absint( $field['public_visible_default'] ?? 1 ); ?>">
								<input type="hidden" name="hap_fields[<?php echo esc_attr( $fk ); ?>][required_for_ai_report]" value="<?php echo absint( $field['required_for_ai_report'] ?? 0 ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( 'Alanları Kaydet' ); ?>
		</form>

		</div><!-- .left -->

		<!-- Sağ panel: Suite modülleri -->
		<div id="hap-field-suite-panel" style="width:380px;min-width:300px;display:none;background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;position:sticky;top:32px">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
				<strong id="hap-suite-panel-title">Bu alanla açılabilecek modüller</strong>
				<button type="button" class="button-link" id="hap-close-suite-panel" style="font-size:18px;line-height:1">&times;</button>
			</div>
			<div id="hap-suite-panel-body">
				<p style="color:#888">Bir alan seçin.</p>
			</div>
		</div>

		</div><!-- flex wrapper -->

		<script>
		jQuery(function($){
			var nonce = hapAdmin.nonce;
			var ajaxUrl = hapAdmin.ajaxUrl;
			var $panel = $('#hap-field-suite-panel');
			var $panelBody = $('#hap-suite-panel-body');
			var $panelTitle = $('#hap-suite-panel-title');

			// Alan satırına tıklayınca Suite modülleri yükle
			$(document).on('click', '.hap-show-field-modules', function(){
				var $btn = $(this);
				var fieldKey = $btn.data('field-key');
				$panelTitle.text(fieldKey + ' — Suite modülleri');
				$panel.show();
				$panelBody.html('<p>Yükleniyor...</p>');

				$.post(ajaxUrl, {
					action: 'hap_get_field_suite_modules',
					nonce: nonce,
					field_key: fieldKey
				}, function(res){
					if (!res.success || !res.data || !res.data.modules) {
						$panelBody.html('<p style="color:#b32d2e">Modül bulunamadı veya Suite tablosu yok.</p>');
						return;
					}
					var modules = res.data.modules;
					var fieldKey = res.data.field_key;
					if (!modules.length) {
						$panelBody.html('<p>Bu alanla eşleşen Suite modülü bulunamadı.</p>');
						return;
					}
					var html = '<table class="widefat striped" style="font-size:.82rem"><thead><tr><th>Modül</th><th>Bölüm</th><th>Öneri</th><th>Backend</th><th>İşlem</th></tr></thead><tbody>';
					$.each(modules, function(i, m){
						var statusBadge = {
							profile_core: '<span style="background:#00a32a;color:#fff;padding:1px 5px;border-radius:2px;font-size:.72rem">Core</span>',
							profile_optional: '<span style="background:#2271b1;color:#fff;padding:1px 5px;border-radius:2px;font-size:.72rem">Optional</span>',
							tool_only: '<span style="background:#888;color:#fff;padding:1px 5px;border-radius:2px;font-size:.72rem">Tool</span>',
							disabled: '<span style="background:#ccc;color:#333;padding:1px 5px;border-radius:2px;font-size:.72rem">Disabled</span>'
						}[m.suggested_profile_status] || m.suggested_profile_status;

						var backendBadge = m.backend_supported == 1
							? '<span style="color:#00a32a">✓ Evet</span>'
							: '<span style="color:#b32d2e">JS only</span>';

						html += '<tr>';
						html += '<td>' + $('<span>').text(m.module_title || m.module_slug).html() + '<br><small style="color:#888">' + $('<span>').text(m.module_slug).html() + '</small></td>';
						html += '<td>' + $('<span>').text(m.section || '').html() + '</td>';
						html += '<td>' + statusBadge + '</td>';
						html += '<td>' + backendBadge + '</td>';
						html += '<td style="white-space:nowrap">';
						html += '<button class="button button-small hap-apply-suite-mapping" data-slug="' + $('<span>').text(m.module_slug).html() + '" data-status="profile_core" data-field="' + $('<span>').text(fieldKey).html() + '" style="margin-bottom:2px">Core yap</button><br>';
						html += '<button class="button button-small hap-apply-suite-mapping" data-slug="' + $('<span>').text(m.module_slug).html() + '" data-status="profile_optional" data-field="' + $('<span>').text(fieldKey).html() + '" style="margin-bottom:2px">Optional yap</button><br>';
						html += '<button class="button button-small hap-apply-suite-mapping" data-slug="' + $('<span>').text(m.module_slug).html() + '" data-status="tool_only" data-field="' + $('<span>').text(fieldKey).html() + '">Tool Only</button>';
						html += '</td>';
						html += '</tr>';
					});
					html += '</tbody></table>';
					$panelBody.html(html);
				}).fail(function(){
					$panelBody.html('<p style="color:#b32d2e">AJAX hatası.</p>');
				});
			});

			// Suite mapping uygula
			$(document).on('click', '.hap-apply-suite-mapping', function(){
				var $btn = $(this);
				var slug = $btn.data('slug');
				var status = $btn.data('status');
				var fieldKey = $btn.data('field');
				$btn.prop('disabled', true).text('Uygulanıyor...');

				$.post(ajaxUrl, {
					action: 'hap_apply_suite_mapping',
					nonce: nonce,
					module_slug: slug,
					profile_status: status,
					field_key: fieldKey
				}, function(res){
					$btn.prop('disabled', false).text(status === 'profile_core' ? 'Core yap' : (status === 'profile_optional' ? 'Optional yap' : 'Tool Only'));
					if (res.success) {
						$btn.closest('tr').find('td:nth-child(3)').html('<span style="background:#00a32a;color:#fff;padding:1px 5px;border-radius:2px;font-size:.72rem">' + status + '</span>');
					} else {
						alert('Hata: ' + (res.data && res.data.message ? res.data.message : 'Bilinmeyen hata'));
					}
				}).fail(function(){
					$btn.prop('disabled', false);
					alert('AJAX hatası.');
				});
			});

			// Suite alanları senkronize et
			$('#hap-sync-suite-fields').on('click', function(){
				var $btn = $(this);
				var $res = $('#hap-sync-suite-result');
				$btn.prop('disabled', true).text('Senkronize ediliyor...');
				$res.text('');

				$.post(ajaxUrl, {
					action: 'hap_sync_suite_fields',
					nonce: nonce
				}, function(res){
					$btn.prop('disabled', false).text('Suite Alanlarını Senkronize Et');
					if (res.success) {
						$res.html('<span style="color:#00a32a">' + (res.data.message || 'Tamamlandı.') + '</span>');
						setTimeout(function(){ location.reload(); }, 1200);
					} else {
						$res.html('<span style="color:#b32d2e">' + (res.data && res.data.message ? res.data.message : 'Hata') + '</span>');
					}
				}).fail(function(){
					$btn.prop('disabled', false);
					$res.html('<span style="color:#b32d2e">AJAX hatası.</span>');
				});
			});

			// Panel kapat
			$('#hap-close-suite-panel').on('click', function(){
				$panel.hide();
			});
		});
		</script>
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
		$modules       = $this->modules->get_modules( array( 'limit' => 500 ) );
		$fields        = HAP_Profile_Fields::get_active_fields();
		$field_options = array();
		foreach ( $fields as $field ) {
			$field_options[ $field['field_key'] ] = $field['label'];
		}
		$suite_active = class_exists( 'HAP_Suite_Module_Fields' ) && HAP_Suite_Module_Fields::table_exists();
		settings_errors( 'hap_profile' );
		?>
		<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
			<h2 style="margin:0">Profil Modülleri</h2>
			<?php if ( $suite_active ) : ?>
			<button type="button" class="button button-primary" id="hap-sync-suite-metadata">
				Suite Metadata Senkronize Et
			</button>
			<button type="button" class="button button-secondary" id="hap-sync-suite-metadata-dry">
				Önizleme (Dry Run)
			</button>
			<span id="hap-sync-suite-meta-result" style="font-size:.85rem;color:#555;max-width:600px"></span>
			<?php else : ?>
			<span style="color:#b32d2e;font-size:.85rem">Suite tablosu bulunamadı — senkronizasyon devre dışı.</span>
			<?php endif; ?>
		</div>
		<script>
		(function($){
			function doSync(dryRun) {
				var $btn = dryRun ? $('#hap-sync-suite-metadata-dry') : $('#hap-sync-suite-metadata');
				var $res = $('#hap-sync-suite-meta-result');
				$btn.prop('disabled', true).text(dryRun ? 'Önizleniyor...' : 'Senkronize ediliyor...');
				$res.text('');
				$.post(hapAdmin.ajaxUrl, {
					action: 'hap_sync_suite_metadata',
					nonce: hapAdmin.nonce,
					dry_run: dryRun ? 1 : 0
				}, function(resp) {
					$btn.prop('disabled', false).text(dryRun ? 'Önizleme (Dry Run)' : 'Suite Metadata Senkronize Et');
					if (resp.success) {
						var d = resp.data;
						$res.html(
							(dryRun ? '<strong>[Dry Run]</strong> ' : '<strong>Tamamlandı.</strong> ') +
							'Toplam: ' + d.total +
							' | Güncellendi: ' + d.updated +
							' | Backend destekli: ' + d.backend_enabled +
							' | result_enabled kapandı: ' + d.result_disabled +
							' | pending_adapter: ' + d.pending_adapter +
							' | Atlandı: ' + d.skipped
						);
					} else {
						$res.html('<span style="color:red">' + (resp.data.message || 'Hata oluştu.') + '</span>');
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					$res.html('<span style="color:red">Sunucu hatası.</span>');
				});
			}
			$('#hap-sync-suite-metadata').on('click', function(){ doSync(false); });
			$('#hap-sync-suite-metadata-dry').on('click', function(){ doSync(true); });
		}(jQuery));
		</script>
		<h2 style="margin-top:4px">Modül Listesi</h2>
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
		$updater     = new HAP_Profile_Updater();
		$settings    = $updater->get_settings();
		$notice      = $updater->get_update_notice( true );
		$last_sha    = (string) get_option( 'hap_profile_last_update_sha', '' );
		$last_update = (string) get_option( 'hap_profile_last_update', '' );
		$last_backup = (string) get_option( 'hap_profile_last_backup_path', '' );
		$last_error  = $updater->get_last_update_error();
		$debug       = $updater->get_last_update_debug();
		settings_errors( 'hap_profile' );
		?>
		<h2>GitHub'dan Güncelle</h2>

		<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
			<p>
				<?php if ( 'success' === $notice['type'] ) : ?>&#x2705;<?php else : ?>&#x274C;<?php endif; ?>
				<strong><?php echo esc_html( $notice['message'] ); ?></strong>
				<span style="color:#888;font-size:.85em;margin-left:8px"><?php echo esc_html( $notice['time'] ); ?></span>
			</p>
		</div>
		<?php endif; ?>

		<div class="hap-updater-status-box" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:16px 20px;margin-bottom:20px;max-width:760px">
			<table style="width:100%;border-collapse:collapse">
				<tr>
					<td style="width:200px;padding:5px 0;color:#555;font-weight:600">Repo</td>
					<td>
						<?php if ( ! empty( $settings['repo'] ) ) : ?>
						<a href="https://github.com/<?php echo esc_attr( $settings['repo'] ); ?>" target="_blank">
							<?php echo esc_html( $settings['repo'] ); ?>
						</a>
						<?php else : ?>
						<span style="color:#aaa">Yapılandırılmamış</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:5px 0;color:#555;font-weight:600">Branch</td>
					<td><code><?php echo esc_html( ! empty( $settings['branch'] ) ? $settings['branch'] : 'main' ); ?></code></td>
				</tr>
				<tr>
					<td style="padding:5px 0;color:#555;font-weight:600">Mevcut Eklenti Sürümü</td>
					<td><code><?php echo esc_html( HAP_Profile_Updater::get_version_string() ); ?></code></td>
				</tr>
				<tr>
					<td style="padding:5px 0;color:#555;font-weight:600">Son GitHub SHA</td>
					<td>
						<?php if ( $last_sha ) : ?>
						<code><?php echo esc_html( substr( $last_sha, 0, 7 ) ); ?></code>
						<?php if ( ! empty( $settings['repo'] ) ) : ?>
						<a href="https://github.com/<?php echo esc_attr( $settings['repo'] ); ?>/commit/<?php echo esc_attr( $last_sha ); ?>" target="_blank" style="margin-left:8px;font-size:.82rem">GitHub'da Gör &#x2197;</a>
						<?php endif; ?>
						<?php else : ?>
						<span style="color:#aaa">&#x2014;</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:5px 0;color:#555;font-weight:600">Son Güncelleme Zamanı</td>
					<td><?php echo $last_update ? esc_html( $last_update ) : '<span style="color:#aaa">&#x2014;</span>'; ?></td>
				</tr>
				<tr>
					<td style="padding:5px 0;color:#555;font-weight:600">Son Backup Yolu</td>
					<td>
						<?php if ( $last_backup ) : ?>
						<code style="font-size:.82rem;word-break:break-all"><?php echo esc_html( $last_backup ); ?></code>
						<?php if ( is_dir( $last_backup ) ) : ?>
						<span style="color:green;margin-left:6px">&#x2714; Mevcut</span>
						<?php else : ?>
						<span style="color:#c00;margin-left:6px">&#x26A0; Klasör bulunamadı</span>
						<?php endif; ?>
						<?php else : ?>
						<span style="color:#aaa">&#x2014;</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $last_error ) : ?>
				<tr>
					<td style="padding:5px 0;color:#c00;font-weight:600">Son Hata</td>
					<td style="color:#c00"><?php echo esc_html( $last_error ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<?php if ( ! empty( $settings['repo'] ) ) : ?>
		<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="hap-update-form">
				<?php wp_nonce_field( 'hap_update_from_github', '_wpnonce' ); ?>
				<input type="hidden" name="action" value="hap_update_from_github">
				<button type="submit" class="button button-primary button-hero" id="hap-do-update"
				        onclick="return confirm('GitHub\'tan en son sürüm indirilip mevcut dosyaların üzerine yazılacak ve öncesinde otomatik backup alınacak. Devam edilsin mi?')">
					GitHub'dan Şimdi Güncelle
				</button>
			</form>
			<button type="button" class="button button-secondary" id="hap-check-version">
				Son Commit'i Kontrol Et
			</button>
			<span id="hap-version-result" style="font-size:.88rem;color:#555"></span>
		</div>
		<?php else : ?>
		<div class="notice notice-warning inline"><p>Güncelleme butonu için önce aşağıdan repo ayarlarını kaydedin.</p></div>
		<?php endif; ?>

		<?php if ( $last_backup && is_dir( $last_backup ) ) : ?>
		<div style="margin-bottom:24px">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="hap-rollback-form">
				<?php wp_nonce_field( 'hap_rollback_from_backup', '_wpnonce' ); ?>
				<input type="hidden" name="action" value="hap_rollback_from_backup">
				<button type="submit" class="button button-secondary"
				        onclick="return confirm('Son backup\'tan geri yükleme yapılacak: <?php echo esc_js( basename( $last_backup ) ); ?>. Mevcut sürümün üzerine yazılacak. Devam?')">
					Son Backup'tan Geri Dön
				</button>
				<span style="margin-left:8px;font-size:.82rem;color:#888"><?php echo esc_html( basename( $last_backup ) ); ?></span>
			</form>
		</div>
		<?php elseif ( $last_backup ) : ?>
		<div class="notice notice-warning inline" style="margin-bottom:16px">
			<p>Son kaydedilen backup yolu artık mevcut değil, geri dönüş yapılamaz.</p>
		</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_updater">
			<h3>Güncelleme Ayarları</h3>
			<p class="description" style="margin-bottom:12px">
				Sadece public GitHub repoları desteklenir. Token/private repo desteği yoktur.
			</p>
			<table class="form-table" style="max-width:700px">
				<tr>
					<th>GitHub Repo <span style="color:red">*</span></th>
					<td>
						<input type="text" name="hap_updater_repo"
						       value="<?php echo esc_attr( $settings['repo'] ); ?>"
						       class="regular-text" placeholder="kullanici/hesaplamaa-profile">
						<p class="description">
							Yalnızca <code>kullanici_adi/repo_adi</code> formatı kabul edilir.<br>
							Örn: <code>alperates58/hesaplamaa-profile</code> - URL, token veya query param girilmesin.
						</p>
					</td>
				</tr>
				<tr>
					<th>Branch</th>
					<td>
						<input type="text" name="hap_updater_branch"
						       value="<?php echo esc_attr( ! empty( $settings['branch'] ) ? $settings['branch'] : 'main' ); ?>"
						       class="small-text" placeholder="main">
						<p class="description">
							Yalnızca harf, sayı, <code>-</code>, <code>_</code> ve <code>.</code> içerebilir.<br>
							Genellikle <code>main</code> veya <code>master</code>.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Ayarları Kaydet' ); ?>
		</form>

		<?php if ( ! empty( $debug ) ) : ?>
		<div style="margin-top:16px">
			<h3>Son Debug Bilgileri <small style="font-weight:400;color:#888">(son güncelleme/rollback)</small></h3>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th style="width:200px">Anahtar</th><th>Değer</th></tr></thead>
				<tbody>
				<?php foreach ( $debug as $key => $value ) : ?>
				<tr>
					<td><code><?php echo esc_html( $key ); ?></code></td>
					<td style="word-break:break-all"><?php echo esc_html( $value ); ?></td>
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
					$btn.prop('disabled', false).text("Son Commit'i Kontrol Et");
					if (res.success) {
						$res.html('GitHub son commit: <strong>' + res.data.sha + '</strong>');
					} else {
						$res.html('Hata: ' + (res.data || 'Bilinmeyen hata.'));
					}
				}).fail(function(){
					$btn.prop('disabled', false).text("Son Commit'i Kontrol Et");
					$res.text('Bağlantı hatası.');
				});
			});

			$('#hap-do-update').closest('form').on('submit', function(){
				$('#hap-do-update').prop('disabled', true).text('Güncelleniyor... (lütfen bekleyin)');
			});

			$('#hap-rollback-form').on('submit', function(){
				$(this).find('button[type=submit]').prop('disabled', true).text('Geri yükleniyor...');
			});
		});
		</script>
		<?php
	}

	private function render_health_tab() {
		echo '<h2>Sağlık Kontrolü</h2>';
		$this->health->render();
	}

	// -------------------------------------------------------
	// Yeni AJAX: Suite + Fields entegrasyonu
	// -------------------------------------------------------

	/**
	 * Belirli bir field_key için Suite'ten modül listesi döner.
	 */
	public function ajax_get_field_suite_modules() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		$field_key = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
		if ( ! $field_key ) {
			wp_send_json_error( array( 'message' => 'field_key gerekli.' ) );
		}

		if ( ! class_exists( 'HAP_Suite_Module_Fields' ) || ! HAP_Suite_Module_Fields::table_exists() ) {
			wp_send_json_error( array( 'message' => 'Suite tablosu bulunamadı.' ) );
		}

		$modules = HAP_Suite_Module_Fields::get_modules_for_field( $field_key, array( 'limit' => 60 ) );
		wp_send_json_success( array( 'field_key' => $field_key, 'modules' => $modules ) );
	}

	/**
	 * Suite tablosundan alınan modüle ait mapping'i wp_hap_profile_modules'a uygular.
	 */
	public function ajax_apply_suite_mapping() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		$module_slug    = sanitize_title( wp_unslash( $_POST['module_slug'] ?? '' ) );
		$profile_status = sanitize_key( $_POST['profile_status'] ?? 'profile_optional' );
		$field_key      = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );

		$allowed_statuses = array( 'profile_core', 'profile_optional', 'tool_only', 'disabled' );
		if ( ! in_array( $profile_status, $allowed_statuses, true ) ) {
			$profile_status = 'profile_optional';
		}

		if ( ! $module_slug ) {
			wp_send_json_error( array( 'message' => 'module_slug gerekli.' ) );
		}

		$manifest = class_exists( 'HAP_Suite_Module_Fields' )
			? HAP_Suite_Module_Fields::get_module_manifest( $module_slug )
			: null;

		$existing = $this->modules->get_module_by_slug( $module_slug );

		$backend_supported = $manifest['backend_supported'] ?? false;
		$runner_type       = $backend_supported ? 'php_callback' : 'js_frontend_only';
		$runner_status     = $backend_supported ? 'ok' : 'pending_adapter';

		$data = array(
			'slug'                    => $module_slug,
			'title'                   => $manifest['module_title'] ?? ( $existing['title'] ?? HAP_Profile_Fields::humanize_module_title( $module_slug ) ),
			'section'                 => $manifest['section'] ?? ( $existing['section'] ?? '' ),
			'profile_status'          => $profile_status,
			'required_fields'         => $manifest ? $manifest['required_fields'] : array( $field_key ),
			'input_mapping'           => $manifest ? wp_json_encode( $manifest['input_mapping'] ) : '',
			'runner_type'             => $runner_type,
			'runner_status'           => $runner_status,
			'suite_source'            => 'suite_table',
			'suite_last_synced_at'    => current_time( 'mysql' ),
			'suite_backend_supported' => $backend_supported ? 1 : 0,
			'suite_section'           => $manifest['section'] ?? '',
			'suite_required_fields'   => $manifest ? wp_json_encode( $manifest['required_fields'] ) : '',
			'suite_input_mapping'     => $manifest ? wp_json_encode( $manifest['input_mapping'] ) : '',
			'availability_status'     => 'active',
			'source'                  => 'suite_sync',
		);

		if ( $existing ) {
			// Manuel ayarları koru
			$data['result_enabled']            = $existing['result_enabled'] ?? 1;
			$data['ai_include']                = $existing['ai_include'] ?? 1;
			$data['share_include_default']     = $existing['share_include_default'] ?? 0;
			$data['onboarding_prompt_enabled'] = $existing['onboarding_prompt_enabled'] ?? 1;
			$id = $this->modules->save_module( $data, (int) $existing['id'] );
		} else {
			$data['result_enabled']            = $backend_supported ? 1 : 0;
			$data['ai_include']                = 1;
			$data['share_include_default']     = 0;
			$data['onboarding_prompt_enabled'] = 1;
			$id = $this->modules->save_module( $data );
		}

		wp_send_json_success( array(
			'message'        => 'Mapping uygulandı.',
			'module_slug'    => $module_slug,
			'profile_status' => $profile_status,
			'module_id'      => $id,
		) );
	}

	/**
	 * Suite tablosundaki tüm profil alanlarını option'a senkronize eder.
	 */
	public function ajax_sync_suite_fields() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		if ( ! class_exists( 'HAP_Suite_Module_Fields' ) || ! HAP_Suite_Module_Fields::table_exists() ) {
			wp_send_json_error( array( 'message' => 'Suite tablosu bulunamadı.' ) );
		}

		$result = HAP_Profile_Fields::sync_fields_from_suite();
		wp_send_json_success( array(
			'message' => sprintf( '%d yeni alan eklendi, %d alan güncellendi.', $result['added'], $result['updated'] ),
			'added'   => $result['added'],
			'updated' => $result['updated'],
		) );
	}

	/**
	 * Modülün profile_status'ını AJAX ile günceller.
	 */
	public function ajax_update_module_profile_status() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}

		$module_slug    = sanitize_title( wp_unslash( $_POST['module_slug'] ?? '' ) );
		$profile_status = sanitize_key( $_POST['profile_status'] ?? '' );
		$allowed        = array( 'profile_core', 'profile_optional', 'tool_only', 'disabled' );
		if ( ! in_array( $profile_status, $allowed, true ) || ! $module_slug ) {
			wp_send_json_error( array( 'message' => 'Geçersiz parametre.' ) );
		}

		$existing = $this->modules->get_module_by_slug( $module_slug );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => 'Modül bulunamadı.' ) );
		}

		$this->modules->save_module( array_merge( $existing, array( 'profile_status' => $profile_status ) ), (int) $existing['id'] );
		wp_send_json_success( array( 'message' => 'Durum güncellendi.', 'profile_status' => $profile_status ) );
	}

	private function save_ai_deepseek_settings() {
		$settings = get_option( 'hap_profile_settings', array() );
		
		$settings['ds_ai_active'] = ! empty( $_POST['ds_ai_active'] ) ? 1 : 0;
		if ( ! empty( $_POST['ds_api_key'] ) ) { // Don't overwrite if empty (password field)
			$settings['ds_api_key'] = sanitize_text_field( $_POST['ds_api_key'] );
		}
		$settings['ds_model']          = sanitize_text_field( $_POST['ds_model'] ?? 'deepseek-v4-flash' );
		$settings['ds_temperature']    = (float) ( $_POST['ds_temperature'] ?? 0.7 );
		$settings['ds_max_tokens']     = absint( $_POST['ds_max_tokens'] ?? 6000 );
		$settings['ds_cache_active']   = ! empty( $_POST['ds_cache_active'] ) ? 1 : 0;
		
		$settings['ds_tone']           = sanitize_text_field( $_POST['ds_tone'] ?? 'Dost canlısı' );
		$settings['ds_detail']         = sanitize_text_field( $_POST['ds_detail'] ?? 'Çok detaylı' );
		$settings['ds_length']         = sanitize_text_field( $_POST['ds_length'] ?? '2500-3500 kelime' );
		$settings['ds_min_paragraphs'] = absint( $_POST['ds_min_paragraphs'] ?? 4 );
		
		$settings['ds_use_name']       = ! empty( $_POST['ds_use_name'] ) ? 1 : 0;
		$settings['ds_single_results'] = ! empty( $_POST['ds_single_results'] ) ? 1 : 0;
		$settings['ds_use_headers']    = ! empty( $_POST['ds_use_headers'] ) ? 1 : 0;
		$settings['ds_add_tips']       = ! empty( $_POST['ds_add_tips'] ) ? 1 : 0;
		$settings['ds_custom_prompt']  = sanitize_textarea_field( wp_unslash( $_POST['ds_custom_prompt'] ?? '' ) );
		
		update_option( 'hap_profile_settings', $settings );
		add_settings_error( 'hap_profile', 'saved', 'DeepSeek AI ayarları kaydedildi.', 'updated' );
	}

	private function render_ai_deepseek_tab() {
		$settings = get_option( 'hap_profile_settings', array() );
		$has_api_key = ! empty( $settings['ds_api_key'] );
		settings_errors( 'hap_profile' );
		?>
		<div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
			<h2 style="margin:0">DeepSeek AI Ayarları</h2>
			<button type="button" class="button button-secondary" id="hap-test-deepseek">Test Bağlantısı</button>
			<span id="hap-test-deepseek-result" style="font-size:0.9em;margin-left:8px;"></span>
		</div>
		<form method="post">
			<?php wp_nonce_field( 'hap_admin_action', 'hap_nonce' ); ?>
			<input type="hidden" name="hap_action" value="save_ai_deepseek">
			
			<table class="form-table">
				<tr>
					<th>AI Aktif</th>
					<td><label><input type="checkbox" name="ds_ai_active" value="1" <?php checked( ! empty( $settings['ds_ai_active'] ) ); ?>> Uygulamada AI Analiz özelliğini aç</label></td>
				</tr>
				<tr>
					<th>DeepSeek API Key</th>
					<td>
						<input type="password" name="ds_api_key" class="regular-text" placeholder="<?php echo $has_api_key ? '********' : 'sk-...'; ?>">
						<p class="description">Değiştirmek istemiyorsanız boş bırakın. Güvenlik için arayüzde gösterilmez.</p>
					</td>
				</tr>
				<tr>
					<th>Model</th>
					<td>
						<select name="ds_model">
							<option value="deepseek-v4-flash" <?php selected( ( $settings['ds_model'] ?? '' ), 'deepseek-v4-flash' ); ?>>deepseek-v4-flash</option>
							<option value="deepseek-v4-pro" <?php selected( ( $settings['ds_model'] ?? '' ), 'deepseek-v4-pro' ); ?>>deepseek-v4-pro</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>Temperature</th>
					<td>
						<input type="number" step="0.1" min="0" max="2" name="ds_temperature" value="<?php echo esc_attr( $settings['ds_temperature'] ?? 0.7 ); ?>" style="width:80px">
					</td>
				</tr>
				<tr>
					<th>Max Output Tokens</th>
					<td>
						<input type="number" step="100" min="1000" max="8000" name="ds_max_tokens" value="<?php echo esc_attr( $settings['ds_max_tokens'] ?? 6000 ); ?>" style="width:100px">
					</td>
				</tr>
				<tr>
					<th>Önbellek (Cache)</th>
					<td>
						<label><input type="checkbox" name="ds_cache_active" value="1" <?php checked( ! isset( $settings['ds_cache_active'] ) || ! empty( $settings['ds_cache_active'] ) ); ?>> Sonuçlar değişene kadar aynı raporu cache'den getir (API maliyetini düşürür)</label>
					</td>
				</tr>
			</table>

			<hr>
			<h3>AI Yazım Ayarları (Parametrik Yönergeler)</h3>
			<table class="form-table">
				<tr>
					<th>Yazım Tonu</th>
					<td>
						<select name="ds_tone">
							<?php
							$tones = array( 'Dost canlısı', 'Profesyonel', 'Sade ve net', 'Spiritüel ama yumuşak' );
							foreach ( $tones as $t ) {
								echo '<option value="' . esc_attr( $t ) . '" ' . selected( ( $settings['ds_tone'] ?? 'Dost canlısı' ), $t, false ) . '>' . esc_html( $t ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Detay Seviyesi</th>
					<td>
						<select name="ds_detail">
							<?php
							$details = array( 'Kısa', 'Orta', 'Detaylı', 'Çok detaylı' );
							foreach ( $details as $d ) {
								echo '<option value="' . esc_attr( $d ) . '" ' . selected( ( $settings['ds_detail'] ?? 'Çok detaylı' ), $d, false ) . '>' . esc_html( $d ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Rapor Uzunluğu Hedefi</th>
					<td>
						<select name="ds_length">
							<?php
							$lengths = array( '800-1200 kelime', '1500-2200 kelime', '2500-3500 kelime', '4000+ kelime' );
							foreach ( $lengths as $l ) {
								echo '<option value="' . esc_attr( $l ) . '" ' . selected( ( $settings['ds_length'] ?? '2500-3500 kelime' ), $l, false ) . '>' . esc_html( $l ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Kategori Başına Minimum Paragraf</th>
					<td>
						<input type="number" min="2" max="8" name="ds_min_paragraphs" value="<?php echo esc_attr( $settings['ds_min_paragraphs'] ?? 4 ); ?>" style="width:80px">
					</td>
				</tr>
				<tr>
					<th>Kullanıcıya Adıyla Hitap Et</th>
					<td><label><input type="checkbox" name="ds_use_name" value="1" <?php checked( ! isset( $settings['ds_use_name'] ) || ! empty( $settings['ds_use_name'] ) ); ?>> Açık</label></td>
				</tr>
				<tr>
					<th>Sonuçları Tek Tek Yorumla</th>
					<td><label><input type="checkbox" name="ds_single_results" value="1" <?php checked( ! isset( $settings['ds_single_results'] ) || ! empty( $settings['ds_single_results'] ) ); ?>> Açık</label></td>
				</tr>
				<tr>
					<th>Kategori Başlıkları Kullan</th>
					<td><label><input type="checkbox" name="ds_use_headers" value="1" <?php checked( ! isset( $settings['ds_use_headers'] ) || ! empty( $settings['ds_use_headers'] ) ); ?>> Açık</label></td>
				</tr>
				<tr>
					<th>Sonunda Farkındalık Önerileri Ekle</th>
					<td><label><input type="checkbox" name="ds_add_tips" value="1" <?php checked( ! isset( $settings['ds_add_tips'] ) || ! empty( $settings['ds_add_tips'] ) ); ?>> Açık</label></td>
				</tr>
				<tr>
					<th>Sağlık Uyarısı & Kesin Kader Dili Yasağı</th>
					<td>
						<p class="description"><strong>Sistem Kuralı:</strong> Bu kurallar AI prompt'una her zaman zorunlu olarak eklenir ve kapatılamaz. Tıbbi teşhis veya astrolojik/kader kesinliği içeren dil kullanımı yasaktır.</p>
					</td>
				</tr>
				<tr>
					<th>Ek Özel Sistem Talimatı</th>
					<td>
						<textarea name="ds_custom_prompt" class="large-text" rows="4" placeholder="Örn: Dost canlısı, sıcak, açıklayıcı ve kullanıcıyı yormayan bir dil kullan..."><?php echo esc_textarea( $settings['ds_custom_prompt'] ?? '' ); ?></textarea>
						<p class="description">Admin isterse AI'ye ek stil veya içerik talimatı yazabilir. (Zorunlu güvenlik kurallarını geçersiz kılmaz.)</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'DeepSeek AI Ayarlarını Kaydet' ); ?>
		</form>
		<script>
		jQuery(function($){
			$('#hap-test-deepseek').on('click', function(){
				var $btn = $(this);
				var $res = $('#hap-test-deepseek-result');
				$btn.prop('disabled', true).text('Bağlanıyor...');
				$res.text('');
				$.post(hapAdmin.ajaxUrl, {
					action: 'hap_test_deepseek_connection',
					nonce: hapAdmin.nonce
				}, function(resp){
					$btn.prop('disabled', false).text('Test Bağlantısı');
					if(resp.success) {
						$res.html('<span style="color:#00a32a">Bağlantı başarılı! (Süre: ' + resp.data.time + 's)</span>');
					} else {
						$res.html('<span style="color:#b32d2e">Hata: ' + (resp.data.message || 'Bilinmeyen hata') + '</span>');
					}
				}).fail(function(){
					$btn.prop('disabled', false).text('Test Bağlantısı');
					$res.html('<span style="color:#b32d2e">Sunucu ile iletişim kurulamadı.</span>');
				});
			});
		});
		</script>
		<?php
	}

	public function ajax_test_deepseek_connection() {
		check_ajax_referer( 'hap_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkiniz yok.' ) );
		}
		
		if ( ! class_exists( 'HAP_Profile_AI_Report' ) ) {
			wp_send_json_error( array( 'message' => 'AI modülü yüklenmemiş.' ) );
		}

		$report_engine = new HAP_Profile_AI_Report();
		$settings = $report_engine->get_settings();
		
		if ( empty( $settings['ds_api_key'] ) ) {
			wp_send_json_error( array( 'message' => 'API Key ayarlanmamış. Önce kaydedin.' ) );
		}
		
		$start_time = microtime( true );
		$messages = array(
			array(
				'role' => 'system',
				'content' => 'Sen sadece "OK" diyerek cevap veren bir test asistanısın. Bağlantıyı doğrulamak için "Bağlantı başarılı" yaz.'
			),
			array(
				'role' => 'user',
				'content' => 'Ping'
			)
		);
		
		$response = $report_engine->call_deepseek( $messages );
		$duration = round( microtime( true ) - $start_time, 2 );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		
		wp_send_json_success( array( 'message' => 'Success', 'time' => $duration ) );
	}
}
