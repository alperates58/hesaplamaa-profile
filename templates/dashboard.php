<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var int                    $user_id
 * @var HAP_Profile_Fields     $fields
 * @var HAP_Profile_Modules    $modules
 * @var HAP_Profile_User_Data  $user_data
 * @var HAP_Profile_Share      $share
 * @var HAP_Profile_Render     $render
 * @var array                  $settings
 */

$profile            = $user_data->get_user_profile_data( $user_id );
$nickname           = $user_data->get_profile_display_name( $user_id, $profile );
$section_config     = $render->get_sections_config();
$current_page_url   = $render->get_current_page_url();
$edit_url           = add_query_arg( 'edit', '1', $current_page_url );
$edit_mode          = isset( $_GET['edit'] ) && '1' === sanitize_key( $_GET['edit'] );
$minimum_completion = $user_data->get_minimum_profile_completion( $user_id, $profile );
$minimum_missing    = $user_data->get_minimum_profile_missing_fields( $user_id, $profile );
$field_labels       = $user_data->get_field_labels();

$all_modules      = $modules->get_modules( array( 'availability_status' => 'active', 'result_enabled' => 1, 'limit' => 500 ) );
$analysis_modules = array_values(
	array_filter(
		$all_modules,
		function ( $m ) {
			return in_array( $m['profile_status'], array( 'profile_core', 'profile_optional' ), true );
		}
	)
);

$analysis_stats = $user_data->get_analysis_preparation_stats( $user_id, $analysis_modules, $profile );
$runner_results = class_exists( 'HAP_Profile_Module_Runner' )
	? HAP_Profile_Module_Runner::run_modules_for_user( $user_id, $analysis_modules, $profile )
	: array();

$ready_results      = array();
$frontend_only      = array();
$missing_results    = array();
$grouped_ready      = array();
$missing_frequency  = array();

$display_title = static function ( $module ) {
	$title = ! empty( $module['title'] ) ? $module['title'] : $module['slug'];
	return HAP_Profile_Fields::humanize_module_title( $title );
};

$tab_map = array(
	'astrology'         => 'astrology',
	'astrology_houses'  => 'astrology',
	'moon_sky'          => 'astrology',
	'health_lifestyle'  => 'health',
	'sport_activity'    => 'sport',
	'numerology'        => 'numerology',
	'symbolic'          => 'symbolic',
	'tarot'             => 'symbolic',
	'chinese_astrology' => 'symbolic',
);

foreach ( $runner_results as $slug => $runner_item ) {
	$module = $runner_item['module'];
	$state  = $runner_item['state'];

	if ( 'filtered_by_profile_policy' === $state ) {
		continue;
	}

	$runner_item['display_title'] = $display_title( $module );
	$section                      = sanitize_key( $module['section'] ?: 'overview' );

	if ( 'ready_result' === $state && ! empty( $runner_item['result'] ) ) {
		$ready_results[ $slug ] = $runner_item;
		$grouped_ready[ $section ][] = $runner_item;
	} elseif ( 'missing_fields' === $state ) {
		if ( empty( $module['onboarding_prompt_enabled'] ) ) {
			continue;
		}
		$missing_results[ $slug ] = $runner_item;
		foreach ( $runner_item['missing'] as $missing_key ) {
			$missing_frequency[ $missing_key ] = ( $missing_frequency[ $missing_key ] ?? 0 ) + 1;
		}
	} elseif ( 'frontend_only' === $state ) {
		$frontend_only[ $slug ] = $runner_item;
	}
}

arsort( $missing_frequency );

$featured_priority = array(
	'burc-dogum-araligi-hesaplama',
	'vucut-kitle-indeksi-hesaplama',
	'gunluk-su-ihtiyaci-hesaplama',
	'ideal-kilo-hesaplama',
	'yasam-yolu-sayisi-hesaplama',
	'ay-fazi-hesaplama',
	'adimdan-kaloriye-hesaplama',
	'spor-protein-ihtiyaci-hesaplama',
);

$featured_results = array();
foreach ( $featured_priority as $priority_slug ) {
	if ( isset( $ready_results[ $priority_slug ] ) ) {
		$featured_results[] = $ready_results[ $priority_slug ];
	}
	if ( count( $featured_results ) >= 6 ) {
		break;
	}
}
if ( count( $featured_results ) < 6 ) {
	foreach ( $ready_results as $slug => $runner_item ) {
		if ( in_array( $runner_item, $featured_results, true ) ) {
			continue;
		}
		$featured_results[] = $runner_item;
		if ( count( $featured_results ) >= 6 ) {
			break;
		}
	}
}

$ready_for_tabs = array_values( $ready_results );
$frontend_cards = array_slice( array_values( $frontend_only ), 0, 8 );
$missing_cards  = array_values( $missing_results );

$section_cards = array();
foreach ( $section_config as $section_key => $config ) {
	$ready_count   = ! empty( $grouped_ready[ $section_key ] ) ? count( $grouped_ready[ $section_key ] ) : 0;
	$missing_count = 0;
	foreach ( $missing_results as $item ) {
		if ( sanitize_key( $item['module']['section'] ?: 'overview' ) === $section_key ) {
			$missing_count++;
		}
	}

	$status = 'Yakında';
	$badge  = 'hap-upcoming';
	if ( $ready_count > 0 ) {
		$status = 'Hazır';
		$badge  = 'hap-ready';
	} elseif ( $missing_count > 0 ) {
		$status = 'Eksik';
		$badge  = 'hap-missing';
	}

	$section_cards[] = array(
		'key'          => $section_key,
		'label'        => $config['label'],
		'icon'         => $config['icon'],
		'description'  => wp_trim_words( $config['description'], 8, '...' ),
		'ready_count'  => $ready_count,
		'missing_count'=> $missing_count,
		'status'       => $status,
		'badge'        => $badge,
		'is_upcoming'  => 0 === $ready_count && 0 === $missing_count,
	);
}

$user_shares  = $share->get_user_shares( $user_id );
$active_share = null;
foreach ( $user_shares as $share_item ) {
	if ( ! empty( $share_item['is_active'] ) ) {
		$active_share = $share_item;
		break;
	}
}
?>
<div class="hap-profile-app">
	<div class="hap-dashboard" id="hap-dashboard">
		<section class="hap-hero-card hap-dashboard-hero">
			<div class="hap-hero-main">
				<div class="hap-hero-avatar-wrap">
					<?php echo get_avatar( $user_id, 88, '', '', array( 'class' => 'hap-avatar' ) ); ?>
				</div>
				<div class="hap-hero-copy">
					<span class="hap-eyebrow">Kişisel Analiz Panelin</span>
					<h1 class="hap-hero-title">Merhaba, <?php echo esc_html( $nickname ); ?></h1>
					<p class="hap-hero-subtitle">Profilinden üretilen en önemli sonuçlara kısa yoldan buradan ulaşabilirsin.</p>
					<div class="hap-hero-actions">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary">Bilgilerimi Güncelle</a>
						<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
							<button class="hap-btn hap-btn-secondary" id="hap-open-share" type="button">Profilimi Paylaş</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="hap-hero-aside">
				<div class="hap-progress-cluster">
					<div class="hap-progress-card">
						<div class="hap-progress-meta">
							<div>
								<strong>Profil tamamlama: %<?php echo absint( $minimum_completion ); ?></strong>
								<span>Minimum profil alanları</span>
							</div>
						</div>
						<div class="hap-progress-bar" aria-hidden="true"><div class="hap-progress-fill" style="width: <?php echo absint( $minimum_completion ); ?>%"></div></div>
					</div>
					<div class="hap-progress-card">
						<div class="hap-progress-meta">
							<div>
								<strong>Analiz hazırlığı: %<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
								<span><?php echo esc_html( $analysis_stats['filled'] . '/' . $analysis_stats['total'] . ' gerekli alan hazır' ); ?></span>
							</div>
						</div>
						<div class="hap-progress-bar" aria-hidden="true"><div class="hap-progress-fill" style="width: <?php echo absint( $analysis_stats['percentage'] ); ?>%"></div></div>
					</div>
				</div>
				<div class="hap-hero-note">
					<strong><?php echo esc_html( count( $ready_results ) ); ?> sonuç hazır</strong>
					<span><?php echo esc_html( count( $frontend_only ) ); ?> bağlantı bekleyen, <?php echo esc_html( count( $missing_results ) ); ?> eksik bilgi isteyen analiz var.</span>
				</div>
			</div>
		</section>

		<?php if ( ! empty( $featured_results ) ) : ?>
			<section class="hap-results-area hap-featured-area">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Öne Çıkan Sonuçlar</span>
						<h2 class="hap-section-title">Öne Çıkan Sonuçlar</h2>
					</div>
				</div>
				<div class="hap-featured-results">
					<?php foreach ( $featured_results as $runner_item ) : ?>
						<?php $result = $runner_item['result']; ?>
						<article class="hap-result-card hap-result-card--featured is-ready">
							<div class="hap-result-card-head">
								<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
								<span class="hap-status-pill hap-ready">Hazır</span>
							</div>
							<div class="hap-result-value">
								<strong class="hap-result-value-text">
									<?php echo esc_html( $result['value'] ?? '' ); ?>
									<?php if ( ! empty( $result['unit'] ) ) : ?>
										<span class="hap-result-unit"><?php echo esc_html( $result['unit'] ); ?></span>
									<?php endif; ?>
								</strong>
								<?php if ( ! empty( $result['label'] ) ) : ?>
									<span class="hap-result-value-label"><?php echo esc_html( $result['label'] ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $result['description'] ) ) : ?>
								<p class="hap-result-description"><?php echo esc_html( $result['description'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $result['warnings'] ) ) : ?>
								<p class="hap-result-warning"><?php echo esc_html( reset( $result['warnings'] ) ); ?></p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $ready_for_tabs ) ) : ?>
			<section class="hap-results-area">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Kişisel Sonuçların</span>
						<h2 class="hap-section-title">Kişisel Sonuçların</h2>
					</div>
				</div>
				<div class="hap-result-filters" role="tablist" aria-label="Sonuç filtreleri">
					<button type="button" class="hap-result-filter is-active" data-result-filter="all">Tümü</button>
					<button type="button" class="hap-result-filter" data-result-filter="astrology">Astroloji</button>
					<button type="button" class="hap-result-filter" data-result-filter="health">Sağlık</button>
					<button type="button" class="hap-result-filter" data-result-filter="sport">Spor</button>
					<button type="button" class="hap-result-filter" data-result-filter="numerology">Numeroloji</button>
					<button type="button" class="hap-result-filter" data-result-filter="symbolic">Sembolik</button>
				</div>
				<div class="hap-results-grid hap-results-grid--main">
					<?php foreach ( $ready_for_tabs as $runner_item ) : ?>
						<?php
						$module   = $runner_item['module'];
						$result   = $runner_item['result'] ?? array();
						$tool_url = $runner_item['tool_url'] ?? null;
						$tab_key  = $tab_map[ sanitize_key( $module['section'] ?: 'overview' ) ] ?? 'all';
						?>
						<article class="hap-result-card is-ready" data-result-category="<?php echo esc_attr( $tab_key ); ?>">
							<div class="hap-result-card-head">
								<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
								<span class="hap-status-pill hap-ready">Sonuç hazır</span>
							</div>
							<?php if ( isset( $result['value'] ) && ( '' !== (string) $result['value'] || '0' === (string) $result['value'] ) ) : ?>
								<div class="hap-result-value">
									<strong class="hap-result-value-text">
										<?php echo esc_html( $result['value'] ); ?>
										<?php if ( ! empty( $result['unit'] ) ) : ?>
											<span class="hap-result-unit"><?php echo esc_html( $result['unit'] ); ?></span>
										<?php endif; ?>
									</strong>
									<?php if ( ! empty( $result['label'] ) ) : ?>
										<span class="hap-result-value-label"><?php echo esc_html( $result['label'] ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $result['description'] ) ) : ?>
								<p class="hap-result-description"><?php echo esc_html( $result['description'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $result['warnings'] ) ) : ?>
								<p class="hap-result-warning"><?php echo esc_html( reset( $result['warnings'] ) ); ?></p>
							<?php endif; ?>
							<?php if ( $tool_url ) : ?>
								<p class="hap-result-tool-link"><a href="<?php echo esc_url( $tool_url ); ?>" target="_blank" rel="noopener">Detaylı hesaplama aracı</a></p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $frontend_cards ) ) : ?>
			<section class="hap-results-area">
				<details class="hap-collapsible">
					<summary class="hap-collapsible-summary">
						<div>
							<span class="hap-eyebrow">Sonraki analizler</span>
							<h2 class="hap-section-title">Sonraki analizler</h2>
							<p class="hap-section-copy">Bu analizler için bilgiler hazır; sonuç motoru bağlandığında burada görünecek.</p>
						</div>
					</summary>
					<div class="hap-results-grid hap-results-grid--pending">
						<?php foreach ( $frontend_cards as $runner_item ) : ?>
							<article class="hap-result-card hap-result-card--pending is-ready">
								<div class="hap-result-card-head">
									<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
									<span class="hap-status-pill hap-pending">Beklemede</span>
								</div>
								<p class="hap-result-card-copy">Bilgilerin hazır. Sonuç motoru bağlandığında bu kart burada otomatik görünecek.</p>
							</article>
						<?php endforeach; ?>
					</div>
				</details>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $missing_cards ) ) : ?>
			<section class="hap-results-area">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Eksik Bilgiyle Açılacak Analizler</span>
						<h2 class="hap-section-title">Eksik Bilgiyle Açılacak Analizler</h2>
					</div>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary">Eksik bilgileri tamamla</a>
				</div>
				<div class="hap-results-grid hap-results-grid--missing">
					<?php foreach ( $missing_cards as $runner_item ) : ?>
						<?php
						$missing_labels = array();
						foreach ( $runner_item['missing'] as $field_key ) {
							$missing_labels[] = $field_labels[ $field_key ] ?? $field_key;
						}
						?>
						<article class="hap-result-card is-locked">
							<div class="hap-result-card-head">
								<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
								<span class="hap-status-pill hap-missing">Eksik bilgi</span>
							</div>
							<div class="hap-suggestion-list">
								<?php foreach ( $missing_labels as $label ) : ?>
									<span class="hap-missing-chip"><?php echo esc_html( $label ); ?></span>
								<?php endforeach; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="hap-sections-area" aria-labelledby="hap-analysis-title">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Analiz Kategorileri</span>
					<h2 class="hap-section-title" id="hap-analysis-title">Analiz Kategorileri</h2>
				</div>
			</div>
			<div class="hap-analysis-card-grid hap-analysis-card-grid--compact">
				<?php foreach ( $section_cards as $card ) : ?>
					<article class="hap-analysis-card <?php echo $card['is_upcoming'] ? 'is-upcoming hap-analysis-card--mini' : 'is-open'; ?>">
						<div class="hap-analysis-card-top">
							<div class="hap-section-card-icon"><?php echo esc_html( $card['icon'] ); ?></div>
							<div class="hap-analysis-card-head">
								<h3 class="hap-section-card-title"><?php echo esc_html( $card['label'] ); ?></h3>
								<p class="hap-section-card-copy"><?php echo esc_html( $card['description'] ); ?></p>
							</div>
							<span class="hap-status-pill <?php echo esc_attr( $card['badge'] ); ?>"><?php echo esc_html( $card['status'] ); ?></span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( $edit_mode ) : ?>
			<section class="hap-profile-form-section" id="hap-profile-form-section">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Profil Bilgileri</span>
						<h2 class="hap-section-title">Bilgilerini düzenle</h2>
					</div>
				</div>
				<?php include HAP_PLUGIN_DIR . 'templates/form-basic.php'; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
			<div class="hap-share-panel" id="hap-share-panel" hidden>
				<div class="hap-share-overlay"></div>
				<div class="hap-share-modal" role="dialog" aria-modal="true" aria-labelledby="hap-share-title">
					<button class="hap-share-close" id="hap-close-share" type="button" aria-label="Paylaşım penceresini kapat">&times;</button>
					<span class="hap-eyebrow">Paylaşım Ayarları</span>
					<h3 id="hap-share-title">Profilini paylaş</h3>
					<p class="hap-share-desc">Görünür bölümleri seç. Hassas bilgiler her zaman gizli tutulur.</p>
					<div class="hap-share-sections">
						<?php foreach ( $section_config as $section_key => $config ) : ?>
							<label class="hap-share-check">
								<input type="checkbox" name="hap_visible_section" value="<?php echo esc_attr( $section_key ); ?>" checked>
								<span class="hap-share-check-ui">
									<span class="hap-share-check-icon"><?php echo esc_html( $config['icon'] ); ?></span>
									<span><?php echo esc_html( $config['label'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="hap-share-note">Doğum saati, doğum yeri ve benzeri hassas alanlar paylaşımlara dahil edilmez.</div>
					<?php if ( $active_share ) : ?>
						<div class="hap-share-existing">
							<p>Mevcut paylaşım bağlantın:</p>
							<div class="hap-share-url-row">
								<input type="text" id="hap-share-url-existing" value="<?php echo esc_attr( $share->get_share_url( $active_share['share_token'] ) ); ?>" readonly>
								<button class="hap-btn hap-btn-secondary hap-copy-share" type="button" data-target="#hap-share-url-existing">Linki Kopyala</button>
							</div>
							<button class="hap-btn hap-btn-danger" id="hap-revoke-share" type="button" data-id="<?php echo absint( $active_share['id'] ); ?>">Paylaşımı İptal Et</button>
						</div>
					<?php endif; ?>
					<button class="hap-btn hap-btn-primary hap-btn-full" id="hap-create-share" type="button">Yeni Paylaşım Bağlantısı Oluştur</button>
					<div id="hap-share-result" class="hap-share-result" hidden>
						<p>Bağlantın hazır:</p>
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
