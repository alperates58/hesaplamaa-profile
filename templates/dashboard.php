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

$profile               = $user_data->get_user_profile_data( $user_id );
$nickname              = $user_data->get_profile_display_name( $user_id, $profile );
$section_config        = $render->get_sections_config();
$current_page_url      = $render->get_current_page_url();
$minimum_completion    = $user_data->get_minimum_profile_completion( $user_id, $profile );
$minimum_missing       = $user_data->get_minimum_profile_missing_fields( $user_id, $profile );
$field_labels          = $user_data->get_field_labels();
$all_modules           = $modules->get_modules(
	array(
		'availability_status' => 'active',
		'limit'               => 500,
	)
);
$analysis_modules      = array_values(
	array_filter(
		$all_modules,
		function( $module ) use ( $section_config ) {
			return in_array( $module['profile_status'], array( 'profile_core', 'profile_optional' ), true ) && isset( $section_config[ sanitize_key( $module['section'] ?: 'overview' ) ] );
		}
	)
);
$tool_only_modules     = array_values(
	array_filter(
		$all_modules,
		function( $module ) use ( $section_config ) {
			return 'tool_only' === $module['profile_status'] && isset( $section_config[ sanitize_key( $module['section'] ?: 'overview' ) ] );
		}
	)
);
$analysis_stats        = $user_data->get_analysis_preparation_stats( $user_id, $analysis_modules, $profile );
$module_stats          = $user_data->get_dashboard_module_stats( $analysis_modules, $profile );
$grouped_items         = array();
$missing_frequency     = array();
$ready_result_cards    = array();
$locked_result_cards   = array();

foreach ( $module_stats['modules_with_state'] as $item ) {
	$section = sanitize_key( $item['module']['section'] ?: 'overview' );
	if ( ! isset( $grouped_items[ $section ] ) ) {
		$grouped_items[ $section ] = array();
	}
	$grouped_items[ $section ][] = $item;

	foreach ( $item['missing_fields'] as $missing_key ) {
		if ( ! isset( $missing_frequency[ $missing_key ] ) ) {
			$missing_frequency[ $missing_key ] = 0;
		}
		$missing_frequency[ $missing_key ]++;
	}

	if ( in_array( $item['state'], array( 'ready', 'optional_ready' ), true ) ) {
		$ready_result_cards[] = $item;
	} else {
		$locked_result_cards[] = $item;
	}
}

$section_cards  = array();
$open_sections  = array();
$locked_sections = array();

foreach ( $section_config as $section_key => $config ) {
	$items = $grouped_items[ $section_key ] ?? array();
	if ( empty( $items ) ) {
		continue;
	}

	$missing_union = array();
	$ready_count   = 0;
	$locked_count  = 0;
	foreach ( $items as $item ) {
		if ( in_array( $item['state'], array( 'ready', 'optional_ready' ), true ) ) {
			$ready_count++;
		} else {
			$locked_count++;
		}
		foreach ( $item['missing_fields'] as $missing_key ) {
			$missing_union[ $missing_key ] = $field_labels[ $missing_key ] ?? $missing_key;
		}
	}

	$missing_labels = array_values( $missing_union );
	$is_open        = 0 === $locked_count || $ready_count > 0;

	$section_cards[] = array(
		'key'            => $section_key,
		'label'          => $config['label'],
		'icon'           => $config['icon'],
		'description'    => $config['description'],
		'items'          => $items,
		'ready_count'    => $ready_count,
		'locked_count'   => $locked_count,
		'missing_labels' => $missing_labels,
		'is_open'        => $is_open,
		'message'        => $render->get_section_message( $section_key, $missing_labels ),
	);

	if ( $is_open ) {
		$open_sections[] = $section_key;
	} else {
		$locked_sections[] = $section_key;
	}
}

arsort( $missing_frequency );
$today_suggestions = array();
foreach ( array_keys( $missing_frequency ) as $field_key ) {
	$today_suggestions[] = $field_labels[ $field_key ] ?? $field_key;
	if ( count( $today_suggestions ) >= 3 ) {
		break;
	}
}
if ( count( $today_suggestions ) < 3 ) {
	foreach ( $minimum_missing as $field_key ) {
		$label = $field_labels[ $field_key ] ?? $field_key;
		if ( ! in_array( $label, $today_suggestions, true ) ) {
			$today_suggestions[] = $label;
		}
		if ( count( $today_suggestions ) >= 3 ) {
			break;
		}
	}
}

$related_tools = array();
foreach ( $tool_only_modules as $module ) {
	$section = sanitize_key( $module['section'] ?: 'overview' );
	if ( ! empty( $open_sections ) && ! in_array( $section, $open_sections, true ) ) {
		continue;
	}
	$related_tools[] = $module;
	if ( count( $related_tools ) >= 6 ) {
		break;
	}
}
if ( empty( $related_tools ) ) {
	$related_tools = array_slice( $tool_only_modules, 0, 6 );
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
					<span class="hap-eyebrow">Kisisel Analiz Panelin</span>
					<h1 class="hap-hero-title">Merhaba, <?php echo esc_html( $nickname ); ?></h1>
					<p class="hap-hero-subtitle">Panelin artik arac katalogu degil; sana ozel hangi analizlerin hazir oldugunu ve yeni kartlari hangi bilgilerle acabilecegini gosteriyor.</p>
					<div class="hap-hero-actions">
						<a href="#hap-profile-form" class="hap-btn hap-btn-primary hap-scroll-link">Bilgilerimi Guncelle</a>
						<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
							<button class="hap-btn hap-btn-secondary" id="hap-open-share" type="button">Profilimi Paylas</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="hap-hero-aside">
				<div class="hap-progress-cluster">
					<div class="hap-progress-card">
						<div class="hap-progress-meta">
							<div>
								<strong>Temel profil: %<?php echo absint( $minimum_completion ); ?></strong>
								<span>Nickname/display name, dogum tarihi, cinsiyet ve sehir</span>
							</div>
						</div>
						<div class="hap-progress-bar" aria-hidden="true">
							<div class="hap-progress-fill" style="width: <?php echo absint( $minimum_completion ); ?>%"></div>
						</div>
					</div>
					<div class="hap-progress-card">
						<div class="hap-progress-meta">
							<div>
								<strong>Analiz hazirligi: %<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
								<span><?php echo esc_html( $analysis_stats['filled'] . '/' . $analysis_stats['total'] . ' gerekli alan hazir' ); ?></span>
							</div>
						</div>
						<div class="hap-progress-bar" aria-hidden="true">
							<div class="hap-progress-fill" style="width: <?php echo absint( $analysis_stats['percentage'] ); ?>%"></div>
						</div>
					</div>
				</div>
				<div class="hap-hero-note">
					<strong><?php echo esc_html( count( $open_sections ) ); ?> kategori acik</strong>
					<span><?php echo esc_html( count( $locked_sections ) ); ?> kategori ek bilgi bekliyor.</span>
				</div>
			</div>
		</section>

		<section class="hap-stats-grid" aria-label="Profil ozet kartlari">
			<article class="hap-stat-card hap-tone-accent">
				<span class="hap-stat-label">Temel Profil</span>
				<strong class="hap-stat-value">%<?php echo absint( $minimum_completion ); ?></strong>
			</article>
			<article class="hap-stat-card hap-tone-success">
				<span class="hap-stat-label">Analiz Hazirligi</span>
				<strong class="hap-stat-value">%<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
			</article>
			<article class="hap-stat-card hap-tone-warning">
				<span class="hap-stat-label">Acik Kategori</span>
				<strong class="hap-stat-value"><?php echo esc_html( count( $open_sections ) ); ?></strong>
			</article>
			<article class="hap-stat-card hap-tone-neutral">
				<span class="hap-stat-label">Kilitli Kategori</span>
				<strong class="hap-stat-value"><?php echo esc_html( count( $locked_sections ) ); ?></strong>
			</article>
		</section>

		<section class="hap-summary-layout">
			<article class="hap-summary-card">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Profil Ozeti</span>
						<h2 class="hap-section-title">Bugun tamamlanabilecek 3 oneri</h2>
					</div>
					<p class="hap-section-copy">Eksik alanlari tamamladikca yeni analiz kategorileri acilir.</p>
				</div>
				<div class="hap-suggestion-list">
					<?php foreach ( array_slice( $today_suggestions, 0, 3 ) as $label ) : ?>
						<span class="hap-missing-chip"><?php echo esc_html( $label ); ?></span>
					<?php endforeach; ?>
					<?php if ( empty( $today_suggestions ) ) : ?>
						<span class="hap-missing-chip is-ready">Tum oncelikli bilgiler tamamlandi</span>
					<?php endif; ?>
				</div>
			</article>

			<article class="hap-summary-card">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Eksik Bilgiler</span>
						<h2 class="hap-section-title">Daha fazla analiz acmak icin</h2>
					</div>
					<p class="hap-section-copy">Ana dashboard acik; ama asagidaki bilgiler yeni kartlarin kilidini acar.</p>
				</div>
				<div class="hap-suggestion-list">
					<?php foreach ( array_keys( $missing_frequency ) as $field_key ) : ?>
						<span class="hap-missing-chip"><?php echo esc_html( $field_labels[ $field_key ] ?? $field_key ); ?></span>
					<?php endforeach; ?>
					<?php if ( empty( $missing_frequency ) ) : ?>
						<span class="hap-missing-chip is-ready">Su an aktif moduller icin eksik zorunlu alan yok</span>
					<?php endif; ?>
				</div>
			</article>
		</section>

		<section class="hap-sections-area" aria-labelledby="hap-analysis-title">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Analiz Kategorileri</span>
					<h2 class="hap-section-title" id="hap-analysis-title">Kisisel Analiz Panelin</h2>
				</div>
				<p class="hap-section-copy">Bu kartlar yalnizca profile_core ve profile_optional modullerini gosterir. Arac linkleri ikincil seviyededir.</p>
			</div>

			<div class="hap-analysis-card-grid">
				<?php foreach ( $section_cards as $card ) : ?>
					<article class="hap-analysis-card">
						<div class="hap-analysis-card-top">
							<div class="hap-section-card-icon"><?php echo esc_html( $card['icon'] ); ?></div>
							<div class="hap-analysis-card-head">
								<h3 class="hap-section-card-title"><?php echo esc_html( $card['label'] ); ?></h3>
								<p class="hap-section-card-copy"><?php echo esc_html( $card['description'] ); ?></p>
							</div>
							<span class="hap-status-pill <?php echo $card['is_open'] ? 'hap-ready' : 'hap-missing'; ?>">
								<?php echo $card['is_open'] ? 'Analiz hazir' : 'Kilitli'; ?>
							</span>
						</div>
						<p class="hap-analysis-card-message"><?php echo esc_html( $card['message'] ); ?></p>
						<div class="hap-analysis-card-meta">
							<span class="hap-status-pill hap-ready">Acik <?php echo esc_html( $card['ready_count'] ); ?></span>
							<span class="hap-status-pill hap-missing">Kilitli <?php echo esc_html( $card['locked_count'] ); ?></span>
						</div>
						<?php if ( ! empty( $card['missing_labels'] ) ) : ?>
							<div class="hap-suggestion-list">
								<?php foreach ( $card['missing_labels'] as $missing_label ) : ?>
									<span class="hap-missing-chip"><?php echo esc_html( $missing_label ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="hap-analysis-card-actions">
							<?php if ( ! empty( $card['missing_labels'] ) ) : ?>
								<a href="#hap-profile-form" class="hap-btn hap-btn-primary hap-scroll-link">Bilgiyi Tamamla</a>
							<?php else : ?>
								<span class="hap-status-pill hap-ready">Analiz hazir</span>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="hap-results-area" id="hap-results">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Kisisel Sonuclar</span>
					<h2 class="hap-section-title">Hazir ve bekleyen analizler</h2>
				</div>
				<p class="hap-section-copy">Gercek hesap motoruna dokunmadan, hangi modullerin kullanima hazir oldugunu ve hangi bilgilerin eksik oldugunu gosteriyoruz.</p>
			</div>

			<div class="hap-results-grid">
				<?php foreach ( $ready_result_cards as $item ) : ?>
					<?php
					$module         = $item['module'];
					$title          = ! empty( $module['title'] ) ? $module['title'] : hap_profile_humanize_slug( $module['slug'] );
					$required       = $user_data->get_effective_required_fields( $module );
					$required_names = array();
					foreach ( $required as $field_key ) {
						$required_names[] = $field_labels[ $field_key ] ?? $field_key;
					}
					?>
					<article class="hap-result-card is-ready">
						<div class="hap-result-card-head">
							<h3><?php echo esc_html( $title ); ?></h3>
							<span class="hap-status-pill hap-ready">Hesaplanmaya hazir</span>
						</div>
						<p class="hap-result-card-copy">Bu analiz, mevcut profil bilgilerine gore hazir duruma geldi.</p>
						<p class="hap-result-card-detail">Kullanilan bilgiler: <?php echo esc_html( ! empty( $required_names ) ? implode( ', ', $required_names ) : 'Temel profil bilgileri' ); ?></p>
						<div class="hap-result-card-actions">
							<?php if ( ! empty( $module['shortcode'] ) ) : ?>
								<a href="<?php echo esc_url( $current_page_url . '?sc=' . rawurlencode( $module['slug'] ) ); ?>" class="hap-result-link">Detayli hesaplama aracini ac</a>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>

				<?php foreach ( $locked_result_cards as $item ) : ?>
					<?php
					$module         = $item['module'];
					$title          = ! empty( $module['title'] ) ? $module['title'] : hap_profile_humanize_slug( $module['slug'] );
					$missing_labels = array();
					foreach ( $item['missing_fields'] as $field_key ) {
						$missing_labels[] = $field_labels[ $field_key ] ?? $field_key;
					}
					?>
					<article class="hap-result-card is-locked">
						<div class="hap-result-card-head">
							<h3><?php echo esc_html( $title ); ?></h3>
							<span class="hap-status-pill hap-missing">Eksik bilgi</span>
						</div>
						<p class="hap-result-card-copy">Bu analiz icin su bilgiler eksik: <?php echo esc_html( implode( ', ', $missing_labels ) ); ?></p>
						<div class="hap-suggestion-list">
							<?php foreach ( $missing_labels as $label ) : ?>
								<span class="hap-missing-chip"><?php echo esc_html( $label ); ?></span>
							<?php endforeach; ?>
						</div>
						<div class="hap-result-card-actions">
							<a href="#hap-profile-form" class="hap-btn hap-btn-primary hap-scroll-link">Bilgiyi Tamamla</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( ! empty( $related_tools ) ) : ?>
			<section class="hap-tools-area">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Ikincil Araclar</span>
						<h2 class="hap-section-title">Ilgili hesaplama araclari</h2>
					</div>
					<p class="hap-section-copy">Tool only moduller burada kucuk ve ikincil olarak listelenir.</p>
				</div>
				<div class="hap-related-tools">
					<?php foreach ( $related_tools as $module ) : ?>
						<?php $title = ! empty( $module['title'] ) ? $module['title'] : hap_profile_humanize_slug( $module['slug'] ); ?>
						<article class="hap-related-tool">
							<div>
								<strong><?php echo esc_html( $title ); ?></strong>
								<span><?php echo esc_html( $section_config[ sanitize_key( $module['section'] ?: 'overview' ) ]['label'] ?? 'Diger' ); ?></span>
							</div>
							<?php if ( ! empty( $module['shortcode'] ) ) : ?>
								<a href="<?php echo esc_url( $current_page_url . '?sc=' . rawurlencode( $module['slug'] ) ); ?>" class="hap-result-link">Detayli araci ac</a>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="hap-profile-form-section">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Profil Bilgileri</span>
					<h2 class="hap-section-title">Bilgilerini duzenle</h2>
				</div>
				<p class="hap-section-copy">Bu form mevcut user_meta yapisini kullanir. Eksik bilgileri tamamladikca yukaridaki kartlar yeniden acilir.</p>
			</div>
			<?php include HAP_PLUGIN_DIR . 'templates/form-basic.php'; ?>
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
