<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var int                  $user_id
 * @var HAP_Profile_Fields   $fields
 * @var HAP_Profile_Modules  $modules
 * @var HAP_Profile_User_Data $user_data
 * @var HAP_Profile_Share    $share
 * @var array                $settings
 */

$wp_user     = get_userdata( $user_id );
$profile     = $user_data->get_user_data( $user_id );
$completion  = $user_data->get_completion_percentage( $user_id );
$nickname    = $user_data->get_field_value( $user_id, 'nickname' )
	?: $wp_user->display_name;

$sections_config = array(
	'health_lifestyle'  => array( 'label' => 'Sağlık & Yaşam', 'icon' => '🏥', 'template' => 'card-health' ),
	'sport_activity'    => array( 'label' => 'Spor & Aktivite', 'icon' => '🏃', 'template' => 'card-sport' ),
	'astrology'         => array( 'label' => 'Astroloji', 'icon' => '⭐', 'template' => 'card-astrology' ),
	'astrology_houses'  => array( 'label' => 'Astroloji Evleri', 'icon' => '🏠', 'template' => 'card-astrology-houses' ),
	'moon_sky'          => array( 'label' => 'Ay & Gökyüzü', 'icon' => '🌙', 'template' => 'card-moon-sky' ),
	'numerology'        => array( 'label' => 'Numeroloji', 'icon' => '🔢', 'template' => 'card-numerology' ),
	'chinese_astrology' => array( 'label' => 'Çin Astrolojisi', 'icon' => '🐉', 'template' => 'card-chinese-astrology' ),
	'symbolic'          => array( 'label' => 'Sembolik', 'icon' => '🔮', 'template' => 'card-symbolic' ),
	'tarot'             => array( 'label' => 'Tarot', 'icon' => '🃏', 'template' => 'card-tarot' ),
);

$sections_summary = $modules->get_sections_summary();
$user_shares      = $share->get_user_shares( $user_id );
$active_share     = null;
foreach ( $user_shares as $s ) {
	if ( $s['is_active'] ) {
		$active_share = $s;
		break;
	}
}

$all_sections = array_keys( $sections_config );
$sensitive_keys = $fields->get_sensitive_keys();

$missing_by_module = array();
$profile_modules   = $modules->get_modules( array(
	'profile_status' => 'profile_core',
	'limit'          => 200,
) );
foreach ( $profile_modules as $mod ) {
	$req     = $modules->decode_required_fields( $mod['required_fields'] );
	$missing = $user_data->get_missing_fields( $user_id, $req );
	if ( ! empty( $missing ) ) {
		$missing_by_module[] = array(
			'module'  => $mod,
			'missing' => $missing,
		);
	}
}
?>
<div class="hap-dashboard" id="hap-dashboard">

	<!-- HERO -->
	<div class="hap-hero">
		<div class="hap-hero-avatar">
			<?php echo get_avatar( $user_id, 72, '', '', array( 'class' => 'hap-avatar' ) ); ?>
		</div>
		<div class="hap-hero-info">
			<h1 class="hap-hero-name"><?php echo esc_html( $nickname ); ?></h1>
			<p class="hap-hero-email"><?php echo esc_html( $wp_user->user_email ); ?></p>
			<div class="hap-completion-row">
				<div class="hap-completion-bar">
					<div class="hap-completion-fill" style="width:<?php echo absint( $completion ); ?>%"></div>
				</div>
				<span class="hap-completion-pct"><?php echo absint( $completion ); ?>% tamamlandı</span>
			</div>
		</div>
		<div class="hap-hero-actions">
			<a href="#hap-profile-form" class="hap-btn hap-btn-outline hap-scroll-link">
				✏️ Bilgileri Güncelle
			</a>
			<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
			<button class="hap-btn hap-btn-primary" id="hap-open-share">
				🔗 Profili Paylaş
			</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- HIZLI ÖZET KARTLAR -->
	<div class="hap-quick-summary">
		<h2 class="hap-section-title">Analiz Özeti</h2>
		<div class="hap-summary-grid">
			<?php foreach ( $sections_config as $sec_key => $sec ) :
				$summary = $sections_summary[ $sec_key ] ?? array( 'total' => 0, 'core' => 0, 'optional' => 0 );
				$active_count = $summary['core'] + $summary['optional'];
			?>
			<div class="hap-summary-card hap-section-<?php echo esc_attr( $sec_key ); ?>">
				<div class="hap-summary-icon"><?php echo esc_html( $sec['icon'] ); ?></div>
				<div class="hap-summary-label"><?php echo esc_html( $sec['label'] ); ?></div>
				<div class="hap-summary-count"><?php echo esc_html( $active_count ); ?> modül</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- EKSİK BİLGİLER -->
	<?php if ( ! empty( $missing_by_module ) ) : ?>
	<div class="hap-missing-section">
		<?php
		include HAP_PLUGIN_DIR . 'templates/card-missing-fields.php';
		?>
	</div>
	<?php endif; ?>

	<!-- MODÜL BÖLÜM KARTLARI -->
	<div class="hap-sections-grid">
		<h2 class="hap-section-title">Analizlerim</h2>
		<?php foreach ( $sections_config as $sec_key => $sec ) :
			$summary = $sections_summary[ $sec_key ] ?? array( 'total' => 0, 'core' => 0, 'optional' => 0, 'planned' => 0 );
			if ( $summary['total'] === 0 ) continue;
			$section_modules = $modules->get_modules( array(
				'section'        => $sec_key,
				'profile_status' => 'profile_core',
				'limit'          => 20,
			) );
		?>
		<div class="hap-section-card" id="hap-sec-<?php echo esc_attr( $sec_key ); ?>">
			<div class="hap-section-card-header">
				<span class="hap-section-card-icon"><?php echo esc_html( $sec['icon'] ); ?></span>
				<h3 class="hap-section-card-title"><?php echo esc_html( $sec['label'] ); ?></h3>
				<div class="hap-section-card-meta">
					<span class="hap-badge-sm"><?php echo esc_html( $summary['total'] ); ?> modül</span>
					<?php if ( $summary['planned'] > 0 ) : ?>
					<span class="hap-badge-sm hap-badge-planned"><?php echo esc_html( $summary['planned'] ); ?> yakında</span>
					<?php endif; ?>
				</div>
			</div>
			<div class="hap-section-card-body">
				<?php if ( ! empty( $section_modules ) ) : ?>
				<ul class="hap-module-list">
					<?php foreach ( array_slice( $section_modules, 0, 5 ) as $mod ) :
						$req     = $modules->decode_required_fields( $mod['required_fields'] );
						$missing = $user_data->get_missing_fields( $user_id, $req );
						$ready   = empty( $missing );
					?>
					<li class="hap-module-item <?php echo $ready ? 'hap-ready' : 'hap-incomplete'; ?>">
						<span class="hap-module-dot"><?php echo $ready ? '✅' : '⚠️'; ?></span>
						<span class="hap-module-name"><?php echo esc_html( $mod['title'] ); ?></span>
						<?php if ( ! $ready ) : ?>
						<span class="hap-module-hint">
							Eksik: <?php echo esc_html( implode( ', ', array_map( array( $fields, 'get_label' ), $missing ) ) ); ?>
						</span>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php else : ?>
				<p class="hap-muted-text">Bu bölüm için aktif analiz modülü yok.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- ARAÇ LİNKLERİ (tool_only) -->
	<?php
	$tool_modules = $modules->get_modules( array(
		'profile_status' => 'tool_only',
		'limit'          => 12,
	) );
	if ( ! empty( $tool_modules ) ) :
	?>
	<div class="hap-tools-section">
		<h2 class="hap-section-title">Önerilen Hesaplama Araçları</h2>
		<div class="hap-tools-grid">
			<?php foreach ( $tool_modules as $tool ) : ?>
			<a class="hap-tool-card" href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( $tool['title'] ); ?>">
				<span class="hap-tool-icon">🔧</span>
				<span class="hap-tool-name"><?php echo esc_html( $tool['title'] ); ?></span>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- PROFİL FORMU -->
	<div class="hap-profile-form-section" id="hap-profile-form">
		<h2 class="hap-section-title">Profil Bilgilerim</h2>
		<?php include HAP_PLUGIN_DIR . 'templates/form-basic.php'; ?>
	</div>

	<!-- PAYLAŞIM PANELİ -->
	<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
	<div class="hap-share-panel" id="hap-share-panel" style="display:none;">
		<div class="hap-share-overlay"></div>
		<div class="hap-share-modal">
			<button class="hap-share-close" id="hap-close-share">&times;</button>
			<h3>🔗 Profilini Paylaş</h3>
			<p class="hap-share-desc">Paylaşmak istediğin bölümleri seç. Hassas bilgiler varsayılan olarak gizlidir.</p>

			<div class="hap-share-sections">
				<h4>Görünür Bölümler</h4>
				<?php foreach ( $sections_config as $sec_key => $sec ) : ?>
				<label class="hap-share-check">
					<input type="checkbox" name="hap_visible_section" value="<?php echo esc_attr( $sec_key ); ?>" checked>
					<?php echo esc_html( $sec['icon'] . ' ' . $sec['label'] ); ?>
				</label>
				<?php endforeach; ?>
			</div>

			<div class="hap-share-note">
				<p>ℹ️ Doğum saati, doğum yeri, telefon, plaka gibi hassas bilgiler hiçbir zaman paylaşılmaz.</p>
			</div>

			<?php if ( $active_share ) : ?>
			<div class="hap-share-existing">
				<p>Mevcut paylaşım bağlantın:</p>
				<div class="hap-share-url-row">
					<input type="text" id="hap-share-url-existing" value="<?php echo esc_attr( $share->get_share_url( $active_share['share_token'] ) ); ?>" readonly>
					<button class="hap-btn hap-btn-sm" onclick="hapCopyShare()">Kopyala</button>
				</div>
				<button class="hap-btn hap-btn-danger" id="hap-revoke-share" data-id="<?php echo absint( $active_share['id'] ); ?>">
					Paylaşımı İptal Et
				</button>
			</div>
			<?php endif; ?>

			<button class="hap-btn hap-btn-primary" id="hap-create-share">
				Yeni Paylaşım Bağlantısı Oluştur
			</button>

			<div id="hap-share-result" class="hap-share-result" style="display:none;">
				<p>Bağlantın hazır:</p>
				<div class="hap-share-url-row">
					<input type="text" id="hap-share-url" readonly>
					<button class="hap-btn hap-btn-sm" onclick="hapCopyShare()">Kopyala</button>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

</div>
