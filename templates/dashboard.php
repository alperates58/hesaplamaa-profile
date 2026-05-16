<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var int                   $user_id
 * @var HAP_Profile_Fields    $fields
 * @var HAP_Profile_Modules   $modules
 * @var HAP_Profile_User_Data $user_data
 * @var HAP_Profile_Share     $share
 * @var array                 $settings
 */

$wp_user    = get_userdata( $user_id );
$profile    = $user_data->get_user_profile_data( $user_id );
$completion = $user_data->get_completion_percentage( $user_id );
$nickname   = $user_data->get_field_value( $user_id, 'nickname' ) ?: $wp_user->display_name;

$sections_config = array(
	'overview'          => array( 'label' => 'Genel', 'icon' => '📋' ),
	'health_lifestyle'  => array( 'label' => 'Sağlık & Yaşam', 'icon' => '🏥' ),
	'sport_activity'    => array( 'label' => 'Spor & Aktivite', 'icon' => '🏃' ),
	'astrology'         => array( 'label' => 'Astroloji', 'icon' => '⭐' ),
	'astrology_houses'  => array( 'label' => 'Astroloji Evleri', 'icon' => '🏠' ),
	'moon_sky'          => array( 'label' => 'Ay & Gökyüzü', 'icon' => '🌙' ),
	'numerology'        => array( 'label' => 'Numeroloji', 'icon' => '🔢' ),
	'chinese_astrology' => array( 'label' => 'Çin Astrolojisi', 'icon' => '🐉' ),
	'symbolic'          => array( 'label' => 'Sembolik', 'icon' => '🔮' ),
	'tarot'             => array( 'label' => 'Tarot', 'icon' => '🃏' ),
);

// Tüm profil modüllerini çek (disabled hariç) + durum motoru
$all_modules = $modules->get_modules( array(
	'availability_status' => 'active',
	'limit'               => 500,
) );
$active_modules = array_filter( $all_modules, function( $m ) {
	return $m['profile_status'] !== 'disabled';
} );
$active_modules = array_values( $active_modules );

$dash_stats    = $user_data->get_dashboard_stats( $user_id );
$module_stats  = $user_data->get_dashboard_module_stats( $active_modules, $profile );
$grouped       = $user_data->group_modules_by_section( $active_modules );

// Paylaşım
$user_shares  = $share->get_user_shares( $user_id );
$active_share = null;
foreach ( $user_shares as $s ) {
	if ( $s['is_active'] ) {
		$active_share = $s;
		break;
	}
}

// Eksik alan listesi (profile_core modüller için)
$missing_by_module = array();
foreach ( $module_stats['modules_with_state'] as $item ) {
	if ( $item['state'] === 'missing_fields' && ! empty( $item['missing_fields'] ) ) {
		$missing_by_module[] = array(
			'module'  => $item['module'],
			'missing' => $item['missing'],
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
			<div class="hap-hero-badges">
				<?php if ( $module_stats['ready'] > 0 ) : ?>
				<span class="hap-badge hap-badge-ready">✅ <?php echo absint( $module_stats['ready'] ); ?> hazır analiz</span>
				<?php endif; ?>
				<?php if ( $module_stats['missing'] > 0 ) : ?>
				<span class="hap-badge hap-badge-warning">⚠️ <?php echo absint( $module_stats['missing'] ); ?> eksik</span>
				<?php endif; ?>
				<?php if ( $module_stats['optional_ready'] > 0 ) : ?>
				<span class="hap-badge hap-badge-info">💡 <?php echo absint( $module_stats['optional_ready'] ); ?> ek analiz hazır</span>
				<?php endif; ?>
				<?php if ( $module_stats['tool_only'] > 0 ) : ?>
				<span class="hap-badge hap-badge-neutral">🔧 <?php echo absint( $module_stats['tool_only'] ); ?> araç</span>
				<?php endif; ?>
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

	<!-- EKSİK BİLGİLER KARTI -->
	<?php if ( ! empty( $missing_by_module ) ) : ?>
	<div class="hap-missing-section">
		<?php include HAP_PLUGIN_DIR . 'templates/card-missing-fields.php'; ?>
	</div>
	<?php endif; ?>

	<!-- BÖLÜM KARTLARI -->
	<div class="hap-sections-grid">
		<h2 class="hap-section-title">Analizlerim</h2>
		<?php foreach ( $sections_config as $sec_key => $sec_cfg ) :
			if ( empty( $grouped[ $sec_key ] ) ) continue;
			$sec_modules = $grouped[ $sec_key ];

			// Bu bölümdeki durum sayacı
			$sec_ready = $sec_missing = $sec_opt_ready = $sec_opt_miss = $sec_tool = 0;
			$sec_items = array();
			foreach ( $module_stats['modules_with_state'] as $item ) {
				if ( $item['module']['section'] !== $sec_key ) continue;
				$sec_items[] = $item;
				switch ( $item['state'] ) {
					case 'ready':            $sec_ready++;     break;
					case 'missing_fields':   $sec_missing++;   break;
					case 'optional_ready':   $sec_opt_ready++; break;
					case 'optional_missing': $sec_opt_miss++;  break;
					case 'tool_only':        $sec_tool++;      break;
				}
			}
			if ( empty( $sec_items ) ) continue;
		?>
		<div class="hap-section-card" id="hap-sec-<?php echo esc_attr( $sec_key ); ?>">
			<div class="hap-section-card-header">
				<span class="hap-section-card-icon"><?php echo esc_html( $sec_cfg['icon'] ); ?></span>
				<h3 class="hap-section-card-title"><?php echo esc_html( $sec_cfg['label'] ); ?></h3>
				<div class="hap-section-card-meta">
					<?php if ( $sec_ready > 0 ) : ?>
					<span class="hap-badge-sm hap-badge-ready"><?php echo absint( $sec_ready ); ?> hazır</span>
					<?php endif; ?>
					<?php if ( $sec_missing > 0 ) : ?>
					<span class="hap-badge-sm hap-badge-warning"><?php echo absint( $sec_missing ); ?> eksik</span>
					<?php endif; ?>
					<?php if ( ( $sec_opt_ready + $sec_opt_miss ) > 0 ) : ?>
					<span class="hap-badge-sm hap-badge-info"><?php echo absint( $sec_opt_ready + $sec_opt_miss ); ?> ek</span>
					<?php endif; ?>
				</div>
			</div>
			<div class="hap-section-card-body">
				<ul class="hap-module-list">
				<?php foreach ( array_slice( $sec_items, 0, 6 ) as $item ) :
					$mod   = $item['module'];
					$state = $item['state'];
					$miss  = $item['missing_fields'];

					$dot_class = '';
					$dot_icon  = '';
					switch ( $state ) {
						case 'ready':
							$dot_icon  = '✅'; $dot_class = 'hap-ready'; break;
						case 'missing_fields':
							$dot_icon  = '⚠️'; $dot_class = 'hap-incomplete'; break;
						case 'optional_ready':
							$dot_icon  = '💡'; $dot_class = 'hap-opt-ready'; break;
						case 'optional_missing':
							$dot_icon  = '○'; $dot_class = 'hap-opt-missing'; break;
						case 'tool_only':
							$dot_icon  = '🔧'; $dot_class = 'hap-tool-only'; break;
						default:
							$dot_icon  = '·'; $dot_class = '';
					}
				?>
				<li class="hap-module-item <?php echo esc_attr( $dot_class ); ?>">
					<span class="hap-module-dot"><?php echo $dot_icon; ?></span>
					<span class="hap-module-name"><?php echo esc_html( $mod['title'] ); ?></span>
					<?php if ( ! empty( $miss ) ) : ?>
					<span class="hap-module-hint">
						Eksik: <?php echo esc_html( implode( ', ', array_map( array( $fields, 'get_label' ), $miss ) ) ); ?>
					</span>
					<?php endif; ?>
					<?php if ( ! empty( $mod['shortcode'] ) && in_array( $state, array( 'ready', 'optional_ready', 'tool_only' ), true ) ) : ?>
					<a class="hap-module-link" href="<?php echo esc_url( get_permalink() ); ?>?sc=<?php echo esc_attr( $mod['slug'] ); ?>" title="<?php echo esc_attr( $mod['title'] ); ?>">Aç →</a>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
				</ul>
				<?php if ( $sec_missing > 0 ) : ?>
				<a href="#hap-profile-form" class="hap-btn hap-btn-sm hap-btn-outline hap-scroll-link">
					✏️ Eksikleri Tamamla
				</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- PROFİL FORMU -->
	<div class="hap-profile-form-section" id="hap-profile-form">
		<h2 class="hap-section-title">Profil Bilgilerim</h2>
		<p class="hap-muted-text">
			<?php printf(
				esc_html( '%d alanın %d tanesi dolu.' ),
				absint( $dash_stats['total_fields'] ),
				absint( $dash_stats['filled_fields'] )
			); ?>
		</p>
		<?php
		$tpl_path = HAP_PLUGIN_DIR . 'templates/form-basic.php';
		if ( file_exists( $tpl_path ) ) {
			include $tpl_path;
		}
		?>
	</div>

	<!-- PAYLAŞIM PANELİ -->
	<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
	<div class="hap-share-panel" id="hap-share-panel" style="display:none;">
		<div class="hap-share-overlay"></div>
		<div class="hap-share-modal">
			<button class="hap-share-close" id="hap-close-share">&times;</button>
			<h3>🔗 Profilini Paylaş</h3>
			<p class="hap-share-desc">Paylaşmak istediğin bölümleri seç. Hassas bilgiler her zaman gizlidir.</p>

			<div class="hap-share-sections">
				<h4>Görünür Bölümler</h4>
				<?php foreach ( $sections_config as $sec_key => $sec_cfg ) : ?>
				<label class="hap-share-check">
					<input type="checkbox" name="hap_visible_section" value="<?php echo esc_attr( $sec_key ); ?>" checked>
					<?php echo esc_html( $sec_cfg['icon'] . ' ' . $sec_cfg['label'] ); ?>
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
					<input type="text" id="hap-share-url-existing"
					       value="<?php echo esc_attr( $share->get_share_url( $active_share['share_token'] ) ); ?>" readonly>
					<button class="hap-btn hap-btn-sm" onclick="hapCopyShare()">Kopyala</button>
				</div>
				<button class="hap-btn hap-btn-danger" id="hap-revoke-share"
				        data-id="<?php echo absint( $active_share['id'] ); ?>">
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
