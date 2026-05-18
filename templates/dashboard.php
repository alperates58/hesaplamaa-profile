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

$ready_results     = array();
$frontend_only     = array();
$missing_results   = array();
$grouped_ready     = array();
$missing_frequency = array();

$display_title = static function ( $module ) {
	$title = ! empty( $module['title'] ) ? $module['title'] : $module['slug'];
	return HAP_Profile_Fields::humanize_module_title( $title );
};

foreach ( $runner_results as $slug => $runner_item ) {
	$module = $runner_item['module'];
	$state  = $runner_item['state'];

	if ( 'filtered_by_profile_policy' === $state ) {
		continue;
	}

	$runner_item['display_title'] = $display_title( $module );
	$section                      = sanitize_key( $module['section'] ?: 'overview' );

	if ( 'ready_result' === $state && ! empty( $runner_item['result'] ) ) {
		$ready_results[ $slug ]      = $runner_item;
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
	'gunes-burcu-hesaplama',
	'yasam-yolu-sayisi-hesaplama',
	'vucut-kitle-indeksi-hesaplama',
	'burc-uyumu-hesaplama',
	'cin-burcu-hesaplama',
	'dogum-tarot-karti-hesaplama',
	'kisisel-yil-sayisi-hesaplama',
	'burc-dogum-araligi-hesaplama',
	'ideal-kilo-hesaplama',
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

// ── Category definitions ──────────────────────────────────────────────────────
$hap_categories = array(
	'astrology'     => array(
		'label' => 'Astroloji & Gökyüzü',
		'icon'  => '♈',
		'slugs' => array(
			'gunes-burcu-hesaplama',
			'burc-dogum-araligi-hesaplama',
			'burc-dekani-hesaplama',
			'burc-derecesi-hesaplama',
			'ay-fazi-hesaplama',
		),
	),
	'numerology'    => array(
		'label' => 'Numeroloji',
		'icon'  => '🔢',
		'slugs' => array(
			'yasam-yolu-sayisi-hesaplama',
			'kisisel-yil-sayisi-hesaplama',
			'dogum-gunu-sayisi-hesaplama',
		),
	),
	'symbolic'      => array(
		'label' => 'Sembolik Profil',
		'icon'  => '🌙',
		'slugs' => array(
			'cin-burcu-hesaplama',
			'cin-elementi-hesaplama',
			'aura-rengi-hesaplama',
			'dogum-tarot-karti-hesaplama',
			'ask-tarot-karti-hesaplama',
		),
	),
	'compatibility' => array(
		'label' => 'Uyum & İlişki',
		'icon'  => '💫',
		'slugs' => array(
			'burc-uyumu-hesaplama',
			'cin-burcuna-gore-ask-uyumu-hesaplama',
			'dogum-gunu-hesaplayici',
		),
	),
	'health'        => array(
		'label' => 'Sağlık & Yaşam',
		'icon'  => '💚',
		'slugs' => array(
			'vucut-kitle-indeksi-hesaplama',
			'ideal-kilo-hesaplama',
			'gunluk-su-ihtiyaci-hesaplama',
			'spor-protein-ihtiyaci-hesaplama',
			'adimdan-kaloriye-hesaplama',
		),
	),
	'sport'         => array(
		'label' => 'Spor & Aktivite',
		'icon'  => '🏃',
		'slugs' => array(
			'yuruyus-kalori-yakimi-hesaplama',
			'kosu-kalori-yakimi-hesaplama',
			'bisiklet-kalori-yakimi-hesaplama',
			'yuzme-kalori-yakimi-hesaplama',
			'ip-atlama-kalori-yakimi-hesaplama',
			'yoga-kalori-yakimi-hesaplama',
			'pilates-kalori-yakimi-hesaplama',
			'zumba-kalori-yakimi-hesaplama',
			'basketbol-kalori-yakimi-hesaplama',
			'futbol-kalori-yakimi-hesaplama',
		),
	),
);

// Build per-category ready results
$cat_results = array();
foreach ( $hap_categories as $cat_key => $cat_def ) {
	$cat_ready = array();
	foreach ( $cat_def['slugs'] as $cat_slug ) {
		if ( isset( $ready_results[ $cat_slug ] ) ) {
			$cat_ready[ $cat_slug ] = $ready_results[ $cat_slug ];
		}
	}
	$cat_results[ $cat_key ] = array(
		'label' => $cat_def['label'],
		'icon'  => $cat_def['icon'],
		'slugs' => $cat_def['slugs'],
		'ready' => $cat_ready,
		'count' => count( $cat_ready ),
	);
}

// Deterministic insight text per category (no AI)
$build_cat_insight = function ( $cat_key, $cat_ready ) {
	$rval = function ( $slug ) use ( $cat_ready ) {
		return isset( $cat_ready[ $slug ] ) ? (string) ( $cat_ready[ $slug ]['result']['value'] ?? '' ) : '';
	};
	$runit = function ( $slug ) use ( $cat_ready ) {
		return isset( $cat_ready[ $slug ] ) ? (string) ( $cat_ready[ $slug ]['result']['unit'] ?? '' ) : '';
	};
	$rlabel = function ( $slug ) use ( $cat_ready ) {
		return isset( $cat_ready[ $slug ] ) ? (string) ( $cat_ready[ $slug ]['result']['label'] ?? '' ) : '';
	};

	$parts = array();
	switch ( $cat_key ) {
		case 'astrology':
			$v = $rval( 'gunes-burcu-hesaplama' );
			if ( $v ) {
				$parts[] = 'Güneş burcun ' . $v;
			}
			$v = $rlabel( 'burc-dekani-hesaplama' );
			if ( $v ) {
				$parts[] = $v;
			}
			$v = $rval( 'ay-fazi-hesaplama' );
			if ( $v ) {
				$parts[] = 'Ay fazı: ' . $v;
			}
			break;

		case 'numerology':
			$v = $rval( 'yasam-yolu-sayisi-hesaplama' );
			if ( $v ) {
				$parts[] = 'Yaşam yolu sayın ' . $v;
			}
			$v = $rval( 'kisisel-yil-sayisi-hesaplama' );
			if ( $v ) {
				$parts[] = 'bu yılın kişisel sayısı ' . $v;
			}
			$v = $rval( 'dogum-gunu-sayisi-hesaplama' );
			if ( $v ) {
				$parts[] = 'doğum günü sayın ' . $v;
			}
			break;

		case 'symbolic':
			$v = $rval( 'cin-burcu-hesaplama' );
			if ( $v ) {
				$parts[] = 'Çin burcun ' . $v;
			}
			$v = $rval( 'cin-elementi-hesaplama' );
			if ( $v ) {
				$parts[] = 'elementi ' . $v;
			}
			$v = $rval( 'aura-rengi-hesaplama' );
			if ( $v ) {
				$parts[] = 'aura rengin ' . $v;
			}
			$v = $rval( 'dogum-tarot-karti-hesaplama' );
			if ( $v ) {
				$parts[] = 'doğum tarot kartın ' . $v;
			}
			break;

		case 'compatibility':
			$v = $rval( 'burc-uyumu-hesaplama' );
			$u = $runit( 'burc-uyumu-hesaplama' );
			if ( $v ) {
				$parts[] = 'Burç uyumun ' . $v . ( $u ? ' ' . $u : '' );
			}
			$v = $rlabel( 'cin-burcuna-gore-ask-uyumu-hesaplama' );
			if ( $v ) {
				$parts[] = 'Çin burcu aşk uyumu: ' . $v;
			}
			break;

		case 'health':
			$v = $rval( 'vucut-kitle-indeksi-hesaplama' );
			$l = $rlabel( 'vucut-kitle-indeksi-hesaplama' );
			if ( $v ) {
				$parts[] = 'Vücut kitle indeksin ' . $v . ( $l ? ' (' . $l . ')' : '' );
			}
			$v = $rval( 'gunluk-su-ihtiyaci-hesaplama' );
			$u = $runit( 'gunluk-su-ihtiyaci-hesaplama' );
			if ( $v ) {
				$parts[] = 'günlük ' . $v . ( $u ? ' ' . $u : '' ) . ' su önerilir';
			}
			$v = $rval( 'ideal-kilo-hesaplama' );
			$u = $runit( 'ideal-kilo-hesaplama' );
			if ( $v ) {
				$parts[] = 'ideal kilonu ' . $v . ( $u ? ' ' . $u : '' ) . ' olarak hesaplandı';
			}
			break;

		case 'sport':
			foreach ( array( 'kosu-kalori-yakimi-hesaplama', 'yuzme-kalori-yakimi-hesaplama', 'ip-atlama-kalori-yakimi-hesaplama' ) as $ts ) {
				if ( isset( $cat_ready[ $ts ] ) ) {
					$title = $cat_ready[ $ts ]['display_title'] ?? '';
					$val   = $cat_ready[ $ts ]['result']['value'] ?? '';
					$unit  = $cat_ready[ $ts ]['result']['unit'] ?? '';
					if ( '' !== (string) $val ) {
						$parts[] = $title . ': ' . $val . ( $unit ? ' ' . $unit : '' );
					}
				}
			}
			break;
	}

	if ( empty( $parts ) ) {
		return '';
	}
	return implode( '. ', $parts ) . '.';
};

$frontend_cards = array_slice( array_values( $frontend_only ), 0, 8 );
$missing_cards  = array_values( $missing_results );

$user_shares  = $share->get_user_shares( $user_id );
$active_share = null;
foreach ( $user_shares as $share_item ) {
	if ( ! empty( $share_item['is_active'] ) ) {
		$active_share = $share_item;
		break;
	}
}

// AI prep
$ai_settings         = class_exists( 'HAP_Profile_AI_Provider' ) ? HAP_Profile_AI_Provider::get_settings() : array();
$ai_globally_enabled = ! empty( $settings['ai_enabled'] );
$ai_report           = null;
$ai_job              = null;
$ai_display_status   = 'ai_disabled';

if ( $ai_globally_enabled && class_exists( 'HAP_Profile_AI_Reports' ) ) {
	$ai_report = HAP_Profile_AI_Reports::get_latest_report( $user_id );
	$ai_job    = HAP_Profile_AI_Reports::get_latest_job( $user_id );
	if ( $ai_report ) {
		$ai_display_status = 'completed';
	} elseif ( $ai_job && in_array( $ai_job['status'] ?? '', array( 'pending', 'processing' ), true ) ) {
		$ai_display_status = 'processing';
	} elseif ( class_exists( 'HAP_Profile_Consents' ) && ! HAP_Profile_Consents::has_ai_consent( $user_id ) ) {
		$ai_display_status = 'consent_required';
	} else {
		$ai_display_status = 'pending_configuration';
	}
} elseif ( $ai_globally_enabled ) {
	$ai_display_status = 'pending_configuration';
}

$url_ai_status = isset( $_GET['ai_status'] ) ? sanitize_key( $_GET['ai_status'] ) : '';
if ( $url_ai_status && 'ai_disabled' !== $ai_display_status ) {
	if ( 'processing' === $url_ai_status && 'completed' !== $ai_display_status ) {
		$ai_display_status = 'processing';
	}
}
?>
<div class="hap-profile-app">

	<!-- Section subnav — sticky horizontal tabs -->
	<nav class="hap-mobile-subnav" id="hap-mobile-subnav" aria-label="Bölüm navigasyonu">
		<div class="hap-mobile-subnav-inner">
			<a href="#hap-section-overview" class="hap-mobile-nav-link hap-scroll-link is-active" data-section="hap-section-overview">Genel</a>
			<?php if ( ! empty( $featured_results ) ) : ?>
				<a href="#hap-section-featured" class="hap-mobile-nav-link hap-scroll-link" data-section="hap-section-featured">Öne Çıkan</a>
			<?php endif; ?>
			<a href="#hap-section-categories" class="hap-mobile-nav-link hap-scroll-link" data-section="hap-section-categories">Analizler</a>
			<?php if ( $ai_globally_enabled ) : ?>
				<a href="#hap-section-ai" class="hap-mobile-nav-link hap-scroll-link" data-section="hap-section-ai">AI Analiz</a>
			<?php endif; ?>
			<?php if ( ! empty( $frontend_cards ) ) : ?>
				<a href="#hap-section-next" class="hap-mobile-nav-link hap-scroll-link" data-section="hap-section-next">Yakında</a>
			<?php endif; ?>
			<?php if ( ! empty( $missing_cards ) ) : ?>
				<a href="#hap-section-missing" class="hap-mobile-nav-link hap-scroll-link" data-section="hap-section-missing">Eksik</a>
			<?php endif; ?>
		</div>
	</nav>

	<div class="hap-dashboard" id="hap-dashboard">

		<!-- Sticky sidebar -->
		<aside class="hap-sidebar" id="hap-sidebar">
			<div class="hap-sidebar-inner">

				<div class="hap-sidebar-profile">
					<?php echo get_avatar( $user_id, 56, '', '', array( 'class' => 'hap-sidebar-avatar' ) ); ?>
					<div class="hap-sidebar-user-info">
						<strong class="hap-sidebar-name"><?php echo esc_html( $nickname ); ?></strong>
						<span class="hap-sidebar-completion-label">%<?php echo absint( $minimum_completion ); ?> tamamlandı</span>
					</div>
					<div class="hap-sidebar-progress-bar" aria-hidden="true">
						<div class="hap-sidebar-progress-fill" style="width: <?php echo absint( $minimum_completion ); ?>%"></div>
					</div>
				</div>

				<nav class="hap-sidebar-nav" aria-label="Sayfa bölümleri">
					<a href="#hap-section-overview" class="hap-sidebar-link hap-scroll-link is-active" data-section="hap-section-overview">
						<span class="hap-sidebar-icon">🏠</span>
						<span class="hap-sidebar-link-label">Genel Bakış</span>
					</a>
					<?php if ( ! empty( $featured_results ) ) : ?>
					<a href="#hap-section-featured" class="hap-sidebar-link hap-scroll-link" data-section="hap-section-featured">
						<span class="hap-sidebar-icon">⭐</span>
						<span class="hap-sidebar-link-label">Öne Çıkan</span>
						<span class="hap-sidebar-badge"><?php echo count( $featured_results ); ?></span>
					</a>
					<?php endif; ?>
					<a href="#hap-section-categories" class="hap-sidebar-link hap-scroll-link" data-section="hap-section-categories">
						<span class="hap-sidebar-icon">🗂️</span>
						<span class="hap-sidebar-link-label">Kişisel Analizler</span>
						<span class="hap-sidebar-badge hap-badge-green"><?php echo count( $ready_results ); ?></span>
					</a>
					<?php if ( $ai_globally_enabled ) : ?>
					<a href="#hap-section-ai" class="hap-sidebar-link hap-scroll-link" data-section="hap-section-ai">
						<span class="hap-sidebar-icon">🤖</span>
						<span class="hap-sidebar-link-label">AI Analiz</span>
					</a>
					<?php endif; ?>
					<?php if ( ! empty( $frontend_cards ) ) : ?>
					<a href="#hap-section-next" class="hap-sidebar-link hap-scroll-link" data-section="hap-section-next">
						<span class="hap-sidebar-icon">⏳</span>
						<span class="hap-sidebar-link-label">Yakında</span>
						<span class="hap-sidebar-badge"><?php echo count( $frontend_cards ); ?></span>
					</a>
					<?php endif; ?>
					<?php if ( ! empty( $missing_cards ) ) : ?>
					<a href="#hap-section-missing" class="hap-sidebar-link hap-scroll-link" data-section="hap-section-missing">
						<span class="hap-sidebar-icon">📝</span>
						<span class="hap-sidebar-link-label">Eksik Bilgi</span>
						<span class="hap-sidebar-badge hap-badge-warn"><?php echo count( $missing_cards ); ?></span>
					</a>
					<?php endif; ?>
				</nav>

				<div class="hap-sidebar-footer">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary hap-btn-full">Profili Güncelle</a>
					<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
						<button class="hap-btn hap-btn-secondary hap-btn-full" id="hap-open-share-sidebar" type="button">Paylaş</button>
					<?php endif; ?>
				</div>

			</div>
		</aside>

		<!-- Main content -->
		<div class="hap-main-content">

			<!-- ── Hero ──────────────────────────────────────────────────────── -->
			<section id="hap-section-overview" class="hap-hero-card hap-dashboard-hero">

				<div class="hap-hero-top">

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
							<?php $pending_count = count( $frontend_only ); ?>
							<?php if ( $pending_count > 0 ) : ?>
							<span><?php echo esc_html( $pending_count ); ?> analiz yakında sonuç üretmeye hazır.</span>
							<?php elseif ( count( $missing_results ) > 0 ) : ?>
							<span><?php echo esc_html( count( $missing_results ) ); ?> analiz için ek bilgi gerekiyor.</span>
							<?php endif; ?>
						</div>
					</div>

				</div><!-- .hap-hero-top -->

				<div class="hap-hero-stats">
					<div class="hap-stat-chip hap-stat-chip--green">
						<span class="hap-stat-chip-value"><?php echo count( $ready_results ); ?></span>
						<span class="hap-stat-chip-label">Hazır Sonuç</span>
					</div>
					<div class="hap-stat-chip">
						<span class="hap-stat-chip-value">%<?php echo absint( $minimum_completion ); ?></span>
						<span class="hap-stat-chip-label">Profil</span>
					</div>
					<div class="hap-stat-chip">
						<span class="hap-stat-chip-value">%<?php echo absint( $analysis_stats['percentage'] ); ?></span>
						<span class="hap-stat-chip-label">Analiz Hazırlığı</span>
					</div>
					<?php if ( ! empty( $missing_cards ) ) : ?>
					<div class="hap-stat-chip hap-stat-chip--warn">
						<span class="hap-stat-chip-value"><?php echo count( $missing_cards ); ?></span>
						<span class="hap-stat-chip-label">Eksik Alan</span>
					</div>
					<?php endif; ?>
				</div>

			</section>

			<!-- ── Öne Çıkan Sonuçlar ───────────────────────────────────────── -->
			<?php if ( ! empty( $featured_results ) ) : ?>
				<section id="hap-section-featured" class="hap-results-area hap-featured-area">
					<div class="hap-section-heading">
						<div>
							<span class="hap-eyebrow">Analizleriniz</span>
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

			<!-- ── Kategori Bazlı Analizler ─────────────────────────────────── -->
			<section id="hap-section-categories" class="hap-results-area">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Kişisel Analizler</span>
						<h2 class="hap-section-title">Kategori Bazlı Analizlerin</h2>
					</div>
				</div>

				<!-- 6 category summary cards -->
				<div class="hap-cat-overview-grid">
					<?php foreach ( $cat_results as $cat_key => $cat ) : ?>
					<a href="#hap-cat-<?php echo esc_attr( $cat_key ); ?>" class="hap-cat-summary-card <?php echo $cat['count'] > 0 ? 'has-results' : 'no-results'; ?>">
						<div class="hap-cat-summary-top">
							<span class="hap-cat-summary-icon"><?php echo esc_html( $cat['icon'] ); ?></span>
							<span class="hap-cat-summary-count <?php echo $cat['count'] > 0 ? 'hap-ready' : ''; ?>">
								<?php echo absint( $cat['count'] ); ?>
							</span>
						</div>
						<strong class="hap-cat-summary-label"><?php echo esc_html( $cat['label'] ); ?></strong>
						<?php if ( $cat['count'] > 0 ) : ?>
						<div class="hap-cat-summary-chips">
							<?php foreach ( array_slice( $cat['ready'], 0, 2 ) as $chip_slug => $chip_item ) : ?>
								<span class="hap-cat-chip">
									<?php echo esc_html( $chip_item['result']['value'] ?? '' ); ?>
									<?php if ( ! empty( $chip_item['result']['unit'] ) ) : ?>
										<small><?php echo esc_html( $chip_item['result']['unit'] ); ?></small>
									<?php endif; ?>
								</span>
							<?php endforeach; ?>
						</div>
						<?php else : ?>
						<p class="hap-cat-summary-empty">Sonuç henüz hazır değil</p>
						<?php endif; ?>
					</a>
					<?php endforeach; ?>
				</div>

				<!-- Category detail sections -->
				<?php foreach ( $cat_results as $cat_key => $cat ) : ?>
				<?php if ( empty( $cat['ready'] ) ) : ?>
					<!-- skip empty category -->
				<?php else : ?>
				<div id="hap-cat-<?php echo esc_attr( $cat_key ); ?>" class="hap-cat-detail">
					<div class="hap-cat-detail-head">
						<span class="hap-cat-detail-icon"><?php echo esc_html( $cat['icon'] ); ?></span>
						<div class="hap-cat-detail-title-wrap">
							<h3 class="hap-cat-detail-title"><?php echo esc_html( $cat['label'] ); ?></h3>
							<?php $insight = $build_cat_insight( $cat_key, $cat['ready'] ); ?>
							<?php if ( $insight ) : ?>
								<p class="hap-cat-insight"><?php echo esc_html( $insight ); ?></p>
							<?php endif; ?>
						</div>
						<span class="hap-status-pill hap-ready"><?php echo absint( $cat['count'] ); ?> sonuç</span>
					</div>
					<div class="hap-cat-results-grid">
						<?php foreach ( $cat['ready'] as $r_slug => $runner_item ) : ?>
							<?php $result = $runner_item['result'] ?? array(); ?>
							<article class="hap-result-card is-ready">
								<div class="hap-result-card-head">
									<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
								</div>
								<?php if ( isset( $result['value'] ) && '' !== (string) $result['value'] ) : ?>
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
									<?php $desc = $result['description']; ?>
									<p class="hap-result-description"><?php echo esc_html( strlen( $desc ) > 130 ? substr( $desc, 0, 130 ) . '…' : $desc ); ?></p>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
				<?php endforeach; ?>

			</section>

			<!-- ── AI Kişisel Analiz ────────────────────────────────────────── -->
			<section id="hap-section-ai" class="hap-results-area hap-ai-section">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Yapay Zeka</span>
						<h2 class="hap-section-title">AI Kişisel Analiz</h2>
					</div>
				</div>

				<?php if ( 'completed' === $ai_display_status && $ai_report ) : ?>
					<div class="hap-ai-report-card">
						<?php if ( ! empty( $ai_report['summary'] ) ) : ?>
						<div class="hap-ai-summary">
							<p><?php echo esc_html( $ai_report['summary'] ); ?></p>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $ai_report['sections'] ) ) : ?>
						<div class="hap-ai-tabs">
							<div class="hap-ai-tab-nav" role="tablist">
								<?php $first_tab = true; ?>
								<?php foreach ( $ai_report['sections'] as $sec_key => $sec_content ) : ?>
									<?php if ( '' === $sec_content ) continue; ?>
									<button type="button" class="hap-ai-tab-btn <?php echo $first_tab ? 'is-active' : ''; ?>"
									        role="tab" data-ai-tab="<?php echo esc_attr( $sec_key ); ?>">
										<?php echo esc_html( ucwords( str_replace( '_', ' ', $sec_key ) ) ); ?>
									</button>
									<?php $first_tab = false; ?>
								<?php endforeach; ?>
							</div>
							<?php $first_panel = true; ?>
							<?php foreach ( $ai_report['sections'] as $sec_key => $sec_content ) : ?>
								<?php if ( '' === $sec_content ) continue; ?>
								<div class="hap-ai-tab-panel <?php echo $first_panel ? 'is-active' : ''; ?>"
								     role="tabpanel" data-ai-panel="<?php echo esc_attr( $sec_key ); ?>">
									<div class="hap-ai-panel-body">
										<?php echo wp_kses_post( wpautop( $sec_content ) ); ?>
									</div>
								</div>
								<?php $first_panel = false; ?>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $ai_report['full_report'] ) && empty( $ai_report['sections'] ) ) : ?>
						<div class="hap-ai-full-report">
							<?php echo wp_kses_post( wpautop( $ai_report['full_report'] ) ); ?>
						</div>
						<?php endif; ?>

						<p class="hap-disclaimer" style="font-size:.78rem;color:#888;margin-top:16px">
							Bu analiz yalnızca bilgilendirme amaçlıdır. Tıbbi, finansal veya hukuki tavsiye niteliği taşımaz.
						</p>
					</div>

				<?php elseif ( 'processing' === $ai_display_status ) : ?>
					<div class="hap-ai-report-card hap-ai-report-card--processing">
						<div class="hap-ai-loader"></div>
						<p><strong>Analizin hazırlanıyor...</strong></p>
						<p style="color:#888">Bu işlem birkaç dakika sürebilir. Sayfayı yenileyerek güncel durumu kontrol edebilirsin.</p>
						<button type="button" class="hap-btn hap-btn-secondary" onclick="location.reload()">Sayfayı Yenile</button>
					</div>

				<?php elseif ( 'consent_required' === $ai_display_status ) : ?>
					<div class="hap-ai-report-card hap-ai-report-card--consent">
						<p><strong>AI analizi için açık rızan gerekiyor.</strong></p>
						<p style="color:#888">Yapay zeka destekli kişisel analiz raporunu görmek için AI işleme onayını vermelisin.</p>
						<a href="<?php echo esc_url( add_query_arg( 'step', 'account_consents', $current_page_url ) ); ?>" class="hap-btn hap-btn-primary">Onay Ver</a>
					</div>

				<?php elseif ( 'pending_configuration' === $ai_display_status ) : ?>
					<div class="hap-ai-report-card hap-ai-report-card--pending">
						<p><strong>AI yorum katmanı yakında aktif olacak.</strong></p>
						<p style="color:#888">Kişisel analiz raporu hazırlandığında burada görünecek.</p>
					</div>

				<?php else : ?>
					<div class="hap-ai-report-card hap-ai-report-card--disabled">
						<p><strong>AI yorum katmanı yakında aktif olacak.</strong></p>
					</div>
				<?php endif; ?>
			</section>

			<!-- ── Gelişmiş analizler (frontend_only) ───────────────────────── -->
			<?php if ( ! empty( $frontend_cards ) ) : ?>
				<section id="hap-section-next" class="hap-results-area">
					<details class="hap-collapsible">
						<summary class="hap-collapsible-summary">
							<div>
								<span class="hap-eyebrow">Yakında</span>
								<h2 class="hap-section-title">Gelişmiş Analizler</h2>
								<p class="hap-section-copy">Bu analizler için bilgiler hazır; gelişmiş analiz motoru hazırlandığında burada görünecek.</p>
							</div>
						</summary>
						<div class="hap-results-grid hap-results-grid--pending">
							<?php foreach ( $frontend_cards as $runner_item ) : ?>
								<article class="hap-result-card hap-result-card--pending is-ready">
									<div class="hap-result-card-head">
										<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
										<span class="hap-status-pill hap-pending">Yakında</span>
									</div>
									<p class="hap-result-card-copy">Gelişmiş analiz motoru hazırlanıyor.</p>
								</article>
							<?php endforeach; ?>
						</div>
					</details>
				</section>
			<?php endif; ?>

			<!-- ── Eksik Bilgi ────────────────────────────────────────────────── -->
			<?php if ( ! empty( $missing_cards ) ) : ?>
				<section id="hap-section-missing" class="hap-results-area">
					<div class="hap-section-heading">
						<div>
							<span class="hap-eyebrow">Profil Tamamlama</span>
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

			<!-- ── Profil düzenleme formu ────────────────────────────────────── -->
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

		</div><!-- /.hap-main-content -->

	</div><!-- /.hap-dashboard -->

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

</div><!-- /.hap-profile-app -->
