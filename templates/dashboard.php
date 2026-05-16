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
$dash_stats = $user_data->get_dashboard_stats( $user_id );

$sections_config = array(
	'astrology'         => array(
		'label'       => 'Astroloji',
		'icon'        => 'As',
		'description' => 'Dogum bilgilerine gore temel astrolojik analizlerin.',
	),
	'astrology_houses'  => array(
		'label'       => 'Astroloji Evleri',
		'icon'        => 'Ev',
		'description' => 'Ev yerlesimleri ve yasam alanlarina odaklanan yorumlar.',
	),
	'moon_sky'          => array(
		'label'       => 'Ay & Gokyuzu',
		'icon'        => 'Ay',
		'description' => 'Ay fazlari, gokyuzu ritmi ve duygusal akislara dair modul seti.',
	),
	'health_lifestyle'  => array(
		'label'       => 'Saglik & Yasam',
		'icon'        => 'Sy',
		'description' => 'Gunluk rutin, saglik ve yasam kalitesi odakli analizler.',
	),
	'sport_activity'    => array(
		'label'       => 'Spor & Aktivite',
		'icon'        => 'Sp',
		'description' => 'Aktivite, performans ve hareket aliskanliklarina dair ozetler.',
	),
	'numerology'        => array(
		'label'       => 'Numeroloji',
		'icon'        => 'Nu',
		'description' => 'Sayi temelli karakter ve donemsel enerjileri kesfet.',
	),
	'symbolic'          => array(
		'label'       => 'Sembolik',
		'icon'        => 'Se',
		'description' => 'Simgesel anlamlar ve gunluk yorumlarla desteklenen alanlar.',
	),
	'tarot'             => array(
		'label'       => 'Tarot',
		'icon'        => 'Ta',
		'description' => 'Kart temelli sezgisel rehberlik ve farkindalik modulleri.',
	),
	'chinese_astrology' => array(
		'label'       => 'Cin Astrolojisi',
		'icon'        => 'Ca',
		'description' => 'Cin astrolojisi ekseninde donemsel ve karakter analizleri.',
	),
);

$all_modules = $modules->get_modules(
	array(
		'availability_status' => 'active',
		'limit'               => 500,
	)
);

$active_modules = array_values(
	array_filter(
		$all_modules,
		function( $module ) {
			return $module['profile_status'] !== 'disabled';
		}
	)
);

$module_stats = $user_data->get_dashboard_module_stats( $active_modules, $profile );
$grouped      = $user_data->group_modules_by_section( $active_modules );

$module_items_by_section = array();
foreach ( $module_stats['modules_with_state'] as $item ) {
	$section = sanitize_key( $item['module']['section'] ?: 'overview' );
	if ( ! isset( $module_items_by_section[ $section ] ) ) {
		$module_items_by_section[ $section ] = array();
	}
	$module_items_by_section[ $section ][] = $item;
}

$user_shares  = $share->get_user_shares( $user_id );
$active_share = null;
foreach ( $user_shares as $share_item ) {
	if ( ! empty( $share_item['is_active'] ) ) {
		$active_share = $share_item;
		break;
	}
}

$missing_fields_map = array();
$missing_by_module  = array();
foreach ( $module_stats['modules_with_state'] as $item ) {
	if ( empty( $item['missing_fields'] ) ) {
		continue;
	}
	foreach ( $item['missing_fields'] as $missing_key ) {
		$missing_fields_map[ $missing_key ] = $fields->get_label( $missing_key );
	}
	if ( $item['state'] === 'missing_fields' ) {
		$missing_by_module[] = array(
			'module'  => $item['module'],
			'missing' => $item['missing_fields'],
		);
	}
}
$missing_fields = array_values( $missing_fields_map );

$stats_cards = array(
	array(
		'label' => 'Profil Tamamlama',
		'value' => '%' . absint( $completion ),
		'tone'  => 'accent',
	),
	array(
		'label' => 'Hazir Analiz',
		'value' => absint( $module_stats['ready'] + $module_stats['optional_ready'] ),
		'tone'  => 'success',
	),
	array(
		'label' => 'Eksik Bilgi',
		'value' => absint( $module_stats['missing'] + $module_stats['optional_missing'] ),
		'tone'  => 'warning',
	),
	array(
		'label' => 'Aktif Modul',
		'value' => absint( count( $active_modules ) ),
		'tone'  => 'neutral',
	),
);

$hero_metrics = array(
	array(
		'label' => 'Hazir Analiz',
		'value' => absint( $module_stats['ready'] ),
	),
	array(
		'label' => 'Eksik Bilgi Bekleyen',
		'value' => absint( $module_stats['missing'] ),
	),
	array(
		'label' => 'Opsiyonel Analiz',
		'value' => absint( $module_stats['optional_ready'] + $module_stats['optional_missing'] ),
	),
	array(
		'label' => 'Arac Linki',
		'value' => absint( $module_stats['tool_only'] ),
	),
);
?>
<div class="hap-profile-app">
	<div class="hap-dashboard" id="hap-dashboard">
		<section class="hap-hero-card">
			<div class="hap-hero-main">
				<div class="hap-hero-avatar-wrap">
					<?php echo get_avatar( $user_id, 88, '', '', array( 'class' => 'hap-avatar' ) ); ?>
				</div>
				<div class="hap-hero-copy">
					<span class="hap-eyebrow">Uyelik Paneli</span>
					<h1 class="hap-hero-title">Merhaba, <?php echo esc_html( $nickname ); ?></h1>
					<p class="hap-hero-subtitle">Kisisel analiz panelin burada.</p>
					<div class="hap-progress-meta">
						<div>
							<strong>Profil tamamlama: %<?php echo absint( $completion ); ?></strong>
							<span><?php echo $completion > 0 ? esc_html__( 'Bilgilerin tamamlandikca daha fazla analiz acilir.', 'hesaplamaa-profile' ) : esc_html__( 'Baslamak icin temel bilgilerini ekle.', 'hesaplamaa-profile' ); ?></span>
						</div>
						<span class="hap-progress-chip">%<?php echo absint( $completion ); ?></span>
					</div>
					<div class="hap-progress-bar" aria-hidden="true">
						<div class="hap-progress-fill" style="width: <?php echo absint( $completion ); ?>%"></div>
					</div>
					<div class="hap-hero-actions">
						<a href="#hap-profile-form" class="hap-btn hap-btn-primary hap-scroll-link">Bilgilerimi Guncelle</a>
						<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
							<button class="hap-btn hap-btn-secondary" id="hap-open-share" type="button">Profilimi Paylas</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="hap-hero-aside">
				<div class="hap-hero-metrics">
					<?php foreach ( $hero_metrics as $metric ) : ?>
						<div class="hap-hero-metric">
							<span class="hap-hero-metric-value"><?php echo esc_html( $metric['value'] ); ?></span>
							<span class="hap-hero-metric-label"><?php echo esc_html( $metric['label'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="hap-hero-note">
					<strong><?php echo esc_html( $dash_stats['filled_fields'] ); ?>/<?php echo esc_html( $dash_stats['total_fields'] ); ?></strong>
					<span>alan dolduruldu. Panelin seni yonlendirmeye hazir.</span>
				</div>
			</div>
		</section>

		<section class="hap-stats-grid" aria-label="Profil istatistikleri">
			<?php foreach ( $stats_cards as $card ) : ?>
				<article class="hap-stat-card hap-tone-<?php echo esc_attr( $card['tone'] ); ?>">
					<span class="hap-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
					<strong class="hap-stat-value"><?php echo esc_html( $card['value'] ); ?></strong>
				</article>
			<?php endforeach; ?>
		</section>

		<?php if ( ! empty( $missing_fields ) ) : ?>
			<div class="hap-missing-section">
				<?php include HAP_PLUGIN_DIR . 'templates/card-missing-fields.php'; ?>
			</div>
		<?php endif; ?>

		<section class="hap-sections-area" aria-labelledby="hap-analysis-title">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Analiz Alanlari</span>
					<h2 class="hap-section-title" id="hap-analysis-title">Hazirlanan analizlerin</h2>
				</div>
				<p class="hap-section-copy">Her kategori icin hazir durumunu, eksik alanlarini ve hizli erisim butonlarini tek bakista gorebilirsin.</p>
			</div>

			<div class="hap-sections-grid">
				<?php foreach ( $sections_config as $section_key => $section_config ) : ?>
					<?php
					if ( empty( $grouped[ $section_key ] ) || empty( $module_items_by_section[ $section_key ] ) ) {
						continue;
					}

					$section_items          = $module_items_by_section[ $section_key ];
					$section_total          = count( $section_items );
					$section_ready          = 0;
					$section_missing        = 0;
					$section_optional       = 0;
					$section_tools          = 0;
					$section_cta_label      = 'Analizleri Gor';
					$section_cta_href       = '#hap-sec-' . $section_key;
					$section_completion_pct = 0;

					foreach ( $section_items as $section_item ) {
						switch ( $section_item['state'] ) {
							case 'ready':
								$section_ready++;
								break;
							case 'missing_fields':
								$section_missing++;
								$section_cta_label = 'Bilgiyi Tamamla';
								$section_cta_href  = '#hap-profile-form';
								break;
							case 'optional_ready':
							case 'optional_missing':
								$section_optional++;
								break;
							case 'tool_only':
								$section_tools++;
								break;
						}
					}

					if ( $section_total > 0 ) {
						$section_completion_pct = (int) round( ( ( $section_ready + $section_tools + $section_optional ) / $section_total ) * 100 );
					}
					?>
					<article class="hap-section-card" id="hap-sec-<?php echo esc_attr( $section_key ); ?>">
						<div class="hap-section-card-top">
							<div class="hap-section-card-icon"><?php echo esc_html( $section_config['icon'] ); ?></div>
							<div class="hap-section-card-head">
								<h3 class="hap-section-card-title"><?php echo esc_html( $section_config['label'] ); ?></h3>
								<p class="hap-section-card-copy"><?php echo esc_html( $section_config['description'] ); ?></p>
							</div>
						</div>

						<div class="hap-section-card-stats">
							<span class="hap-status-pill hap-ready">Hazir <?php echo absint( $section_ready ); ?></span>
							<span class="hap-status-pill hap-missing">Eksik <?php echo absint( $section_missing ); ?></span>
							<span class="hap-status-pill hap-optional">Opsiyonel <?php echo absint( $section_optional ); ?></span>
							<span class="hap-status-pill hap-tool">Arac <?php echo absint( $section_tools ); ?></span>
						</div>

						<div class="hap-section-progress">
							<div class="hap-progress-bar" aria-hidden="true">
								<div class="hap-progress-fill" style="width: <?php echo absint( $section_completion_pct ); ?>%"></div>
							</div>
							<span class="hap-section-progress-label">%<?php echo absint( $section_completion_pct ); ?> erisim hazirligi</span>
						</div>

						<ul class="hap-module-list">
							<?php foreach ( array_slice( $section_items, 0, 5 ) as $item ) : ?>
								<?php
								$module        = $item['module'];
								$state         = $item['state'];
								$missing_keys  = $item['missing_fields'];
								$module_title  = ! empty( $module['title'] ) ? $module['title'] : hap_profile_humanize_slug( $module['slug'] );
								$module_action = '';
								$module_href   = '';
								$badge_label   = '';
								$badge_class   = '';

								switch ( $state ) {
									case 'ready':
										$badge_label   = 'Hazir';
										$badge_class   = 'hap-ready';
										$module_action = 'Hazir';
										if ( ! empty( $module['shortcode'] ) ) {
											$module_href = get_permalink() . '?sc=' . rawurlencode( $module['slug'] );
										}
										break;
									case 'missing_fields':
										$badge_label   = 'Eksik bilgi';
										$badge_class   = 'hap-missing';
										$module_action = 'Bilgiyi Tamamla';
										$module_href   = '#hap-profile-form';
										break;
									case 'optional_ready':
										$badge_label   = 'Opsiyonel';
										$badge_class   = 'hap-optional';
										$module_action = 'Hazir';
										if ( ! empty( $module['shortcode'] ) ) {
											$module_href = get_permalink() . '?sc=' . rawurlencode( $module['slug'] );
										}
										break;
									case 'optional_missing':
										$badge_label   = 'Opsiyonel';
										$badge_class   = 'hap-optional';
										$module_action = 'Bilgiyi Tamamla';
										$module_href   = '#hap-profile-form';
										break;
									case 'tool_only':
										$badge_label   = 'Arac';
										$badge_class   = 'hap-tool';
										$module_action = 'Araci Ac';
										if ( ! empty( $module['shortcode'] ) ) {
											$module_href = get_permalink() . '?sc=' . rawurlencode( $module['slug'] );
										}
										break;
								}
								?>
								<li class="hap-module-item">
									<div class="hap-module-main">
										<div class="hap-module-title-row">
											<span class="hap-module-name"><?php echo esc_html( $module_title ); ?></span>
											<span class="hap-status-pill <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
										</div>
										<?php if ( ! empty( $missing_keys ) ) : ?>
											<p class="hap-module-hint">Gerekli: <?php echo esc_html( implode( ', ', array_map( array( $fields, 'get_label' ), $missing_keys ) ) ); ?></p>
										<?php endif; ?>
									</div>
									<?php if ( $module_href ) : ?>
										<a class="hap-module-action <?php echo 0 === strpos( $module_href, '#' ) ? 'hap-scroll-link' : ''; ?>" href="<?php echo esc_url( $module_href ); ?>">
											<?php echo esc_html( $module_action ); ?>
										</a>
									<?php else : ?>
										<span class="hap-module-action is-static"><?php echo esc_html( $module_action ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>

						<a href="<?php echo esc_url( $section_cta_href ); ?>" class="hap-btn hap-btn-tertiary <?php echo '#hap-profile-form' === $section_cta_href ? 'hap-scroll-link' : ''; ?>">
							<?php echo esc_html( $section_cta_label ); ?>
						</a>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="hap-profile-form-section">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Profil Bilgileri</span>
					<h2 class="hap-section-title">Bilgilerini duzenle</h2>
				</div>
				<p class="hap-section-copy"><?php echo esc_html( $dash_stats['filled_fields'] . '/' . $dash_stats['total_fields'] . ' alan dolduruldu. Bu bilgiler sadece kisisel analizlerini olusturmak icin kullanilir.' ); ?></p>
			</div>
			<?php
			$tpl_path = HAP_PLUGIN_DIR . 'templates/form-basic.php';
			if ( file_exists( $tpl_path ) ) {
				include $tpl_path;
			}
			?>
		</section>

		<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
			<div class="hap-share-panel" id="hap-share-panel" hidden>
				<div class="hap-share-overlay"></div>
				<div class="hap-share-modal" role="dialog" aria-modal="true" aria-labelledby="hap-share-title">
					<button class="hap-share-close" id="hap-close-share" type="button" aria-label="Paylasim penceresini kapat">&times;</button>
					<span class="hap-eyebrow">Paylasim Ayarlari</span>
					<h3 id="hap-share-title">Profilini paylas</h3>
					<p class="hap-share-desc">Gorunur bolumleri sec. Hassas bilgiler her zaman gizli tutulur.</p>

					<div class="hap-share-sections">
						<?php foreach ( $sections_config as $section_key => $section_config ) : ?>
							<label class="hap-share-check">
								<input type="checkbox" name="hap_visible_section" value="<?php echo esc_attr( $section_key ); ?>" checked>
								<span class="hap-share-check-ui">
									<span class="hap-share-check-icon"><?php echo esc_html( $section_config['icon'] ); ?></span>
									<span><?php echo esc_html( $section_config['label'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="hap-share-note">
						Dogum saati, dogum yeri, telefon ve benzeri hassas alanlar paylasimlara dahil edilmez.
					</div>

					<?php if ( $active_share ) : ?>
						<div class="hap-share-existing">
							<p>Mevcut paylasim baglantin:</p>
							<div class="hap-share-url-row">
								<input type="text" id="hap-share-url-existing" value="<?php echo esc_attr( $share->get_share_url( $active_share['share_token'] ) ); ?>" readonly>
								<button class="hap-btn hap-btn-secondary hap-copy-share" type="button" data-target="#hap-share-url-existing">Linki Kopyala</button>
							</div>
							<button class="hap-btn hap-btn-danger" id="hap-revoke-share" type="button" data-id="<?php echo absint( $active_share['id'] ); ?>">Paylasimi Iptal Et</button>
						</div>
					<?php endif; ?>

					<button class="hap-btn hap-btn-primary hap-btn-full" id="hap-create-share" type="button">Yeni Paylasim Baglantisi Olustur</button>

					<div id="hap-share-result" class="hap-share-result" hidden>
						<p>Baglantin hazir:</p>
						<div class="hap-share-url-row">
							<input type="text" id="hap-share-url" readonly>
							<button class="hap-btn hap-btn-secondary hap-copy-share" type="button" data-target="#hap-share-url">Linki Kopyala</button>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
