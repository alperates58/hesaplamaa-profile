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

// Only profile_core and profile_optional modules — tool_only modules excluded entirely.
$all_modules      = $modules->get_modules( array( 'availability_status' => 'active', 'limit' => 500 ) );
$analysis_modules = array_values( array_filter(
	$all_modules,
	function ( $m ) {
		return in_array( $m['profile_status'], array( 'profile_core', 'profile_optional' ), true );
	}
) );

$analysis_stats      = $user_data->get_analysis_preparation_stats( $user_id, $analysis_modules, $profile );
$module_stats        = $user_data->get_dashboard_module_stats( $analysis_modules, $profile );
$grouped_items       = array();
$missing_frequency   = array();
$ready_result_cards  = array();
$locked_result_cards = array();

foreach ( $module_stats['modules_with_state'] as $item ) {
	$section = sanitize_key( $item['module']['section'] ?: 'overview' );
	if ( ! isset( $grouped_items[ $section ] ) ) {
		$grouped_items[ $section ] = array();
	}
	$grouped_items[ $section ][] = $item;

	foreach ( $item['missing_fields'] as $missing_key ) {
		$missing_frequency[ $missing_key ] = ( $missing_frequency[ $missing_key ] ?? 0 ) + 1;
	}

	if ( in_array( $item['state'], array( 'ready', 'optional_ready' ), true ) ) {
		$ready_result_cards[] = $item;
	} else {
		$locked_result_cards[] = $item;
	}
}

$section_cards   = array();
$open_sections   = array();
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

// Action-oriented suggestions — maps field key to actionable sentence.
$suggestion_map = array(
	'birth_time'     => 'Doğum saatini ekle — astroloji evleri ve ay burcu analizleri açılır.',
	'birth_place'    => 'Doğum yerini ekle — yükselen burç ve ev yerleşimi analizleri açılır.',
	'height'         => 'Boy bilgini ekle — sağlık ve spor analizleri açılır.',
	'weight'         => 'Kilo bilgini ekle — sağlık analizleri kişiselleşir.',
	'first_name'     => 'Ad ve soyadını ekle — numeroloji analizleri açılır.',
	'last_name'      => 'Soyadını ekle — numeroloji profili tamamlanır.',
	'activity_level' => 'Aktivite düzeyini belirt — spor ve aktivite analizleri açılır.',
	'sleep_hours'    => 'Uyku süresini ekle — yaşam kalitesi analizi açılır.',
	'daily_steps'    => 'Günlük adım sayını ekle — aktivite analizi genişler.',
);

$today_suggestions = array();
foreach ( array_keys( $missing_frequency ) as $field_key ) {
	if ( isset( $suggestion_map[ $field_key ] ) && ! in_array( $suggestion_map[ $field_key ], $today_suggestions, true ) ) {
		$today_suggestions[] = $suggestion_map[ $field_key ];
	}
	if ( count( $today_suggestions ) >= 3 ) {
		break;
	}
}
foreach ( $minimum_missing as $field_key ) {
	if ( count( $today_suggestions ) >= 3 ) {
		break;
	}
	if ( isset( $suggestion_map[ $field_key ] ) && ! in_array( $suggestion_map[ $field_key ], $today_suggestions, true ) ) {
		$today_suggestions[] = $suggestion_map[ $field_key ];
	}
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

		<!-- A) Hero -->
		<section class="hap-hero-card hap-dashboard-hero">
			<div class="hap-hero-main">
				<div class="hap-hero-avatar-wrap">
					<?php echo get_avatar( $user_id, 88, '', '', array( 'class' => 'hap-avatar' ) ); ?>
				</div>
				<div class="hap-hero-copy">
					<span class="hap-eyebrow">Kişisel Analiz Panelin</span>
					<h1 class="hap-hero-title">Merhaba, <?php echo esc_html( $nickname ); ?></h1>
					<p class="hap-hero-subtitle">Bilgilerine göre açılan analiz kategorilerini ve tamamlanması gereken alanları buradan takip edebilirsin.</p>
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
								<strong>Temel profil: %<?php echo absint( $minimum_completion ); ?></strong>
								<span>Takma ad, doğum tarihi, cinsiyet ve şehir</span>
							</div>
						</div>
						<div class="hap-progress-bar" aria-hidden="true">
							<div class="hap-progress-fill" style="width: <?php echo absint( $minimum_completion ); ?>%"></div>
						</div>
					</div>
					<div class="hap-progress-card">
						<div class="hap-progress-meta">
							<div>
								<strong>Analiz hazırlığı: %<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
								<span><?php echo esc_html( $analysis_stats['filled'] . '/' . $analysis_stats['total'] . ' gerekli alan hazır' ); ?></span>
							</div>
						</div>
						<div class="hap-progress-bar" aria-hidden="true">
							<div class="hap-progress-fill" style="width: <?php echo absint( $analysis_stats['percentage'] ); ?>%"></div>
						</div>
					</div>
				</div>
				<div class="hap-hero-note">
					<strong><?php echo esc_html( count( $open_sections ) ); ?> kategori açık</strong>
					<span><?php echo esc_html( count( $locked_sections ) ); ?> kategori ek bilgi bekliyor.</span>
				</div>
			</div>
		</section>

		<!-- Stats grid -->
		<section class="hap-stats-grid" aria-label="Profil özet kartları">
			<article class="hap-stat-card hap-tone-accent">
				<span class="hap-stat-label">Temel Profil</span>
				<strong class="hap-stat-value">%<?php echo absint( $minimum_completion ); ?></strong>
			</article>
			<article class="hap-stat-card hap-tone-success">
				<span class="hap-stat-label">Analiz Hazırlığı</span>
				<strong class="hap-stat-value">%<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
			</article>
			<article class="hap-stat-card hap-tone-warning">
				<span class="hap-stat-label">Açık Kategori</span>
				<strong class="hap-stat-value"><?php echo esc_html( count( $open_sections ) ); ?></strong>
			</article>
			<article class="hap-stat-card hap-tone-neutral">
				<span class="hap-stat-label">Kilitli Kategori</span>
				<strong class="hap-stat-value"><?php echo esc_html( count( $locked_sections ) ); ?></strong>
			</article>
		</section>

		<!-- B) Bugün Tamamlanabilecek 3 Öneri -->
		<section class="hap-summary-layout">
			<article class="hap-summary-card">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Öneriler</span>
						<h2 class="hap-section-title">Bugün tamamlanabilecek 3 öneri</h2>
					</div>
					<p class="hap-section-copy">Eksik alanları tamamladıkça yeni analiz kategorileri açılır.</p>
				</div>
				<div class="hap-suggestion-list">
					<?php if ( ! empty( $today_suggestions ) ) : ?>
						<?php foreach ( array_slice( $today_suggestions, 0, 3 ) as $suggestion ) : ?>
							<div class="hap-suggestion-action">
								<span class="hap-suggestion-dot"></span>
								<span class="hap-suggestion-text"><?php echo esc_html( $suggestion ); ?></span>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-suggestion-cta">Bilgiyi Ekle</a>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="hap-suggestion-action is-complete">
							<span class="hap-suggestion-dot"></span>
							<span class="hap-suggestion-text">Profilin güçlü görünüyor. Yeni analizler eklendikçe burada görünecek.</span>
						</div>
					<?php endif; ?>
				</div>
			</article>

			<article class="hap-summary-card">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Eksik Bilgiler</span>
						<h2 class="hap-section-title">Daha fazla analiz açmak için</h2>
					</div>
					<p class="hap-section-copy">Ana dashboard açık; ama aşağıdaki bilgiler yeni kartların kilidini açar.</p>
				</div>
				<div class="hap-suggestion-list">
					<?php foreach ( array_keys( $missing_frequency ) as $field_key ) : ?>
						<span class="hap-missing-chip"><?php echo esc_html( $field_labels[ $field_key ] ?? $field_key ); ?></span>
					<?php endforeach; ?>
					<?php if ( empty( $missing_frequency ) ) : ?>
						<span class="hap-missing-chip is-ready">Şu an aktif modüller için eksik zorunlu alan yok</span>
					<?php endif; ?>
				</div>
			</article>
		</section>

		<!-- C) Analiz Kategori Kartları -->
		<section class="hap-sections-area" aria-labelledby="hap-analysis-title">
			<div class="hap-section-heading">
				<div>
					<span class="hap-eyebrow">Analiz Kategorileri</span>
					<h2 class="hap-section-title" id="hap-analysis-title">Kişisel Analiz Panelin</h2>
				</div>
				<p class="hap-section-copy">Her kart, profil bilgilerine göre hazırlanan kişisel analizleri temsil eder.</p>
			</div>

			<div class="hap-analysis-card-grid">
				<?php foreach ( $section_cards as $card ) : ?>
					<article class="hap-analysis-card <?php echo $card['is_open'] ? 'is-open' : 'is-locked'; ?>">
						<div class="hap-analysis-card-top">
							<div class="hap-section-card-icon"><?php echo esc_html( $card['icon'] ); ?></div>
							<div class="hap-analysis-card-head">
								<h3 class="hap-section-card-title"><?php echo esc_html( $card['label'] ); ?></h3>
								<p class="hap-section-card-copy"><?php echo esc_html( $card['description'] ); ?></p>
							</div>
							<span class="hap-status-pill <?php echo $card['is_open'] ? 'hap-ready' : 'hap-missing'; ?>">
								<?php echo $card['is_open'] ? 'Açık' : 'Kilitli'; ?>
							</span>
						</div>
						<p class="hap-analysis-card-message"><?php echo esc_html( $card['message'] ); ?></p>
						<?php if ( ! empty( $card['missing_labels'] ) ) : ?>
							<div class="hap-suggestion-list">
								<?php foreach ( $card['missing_labels'] as $missing_label ) : ?>
									<span class="hap-missing-chip"><?php echo esc_html( $missing_label ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="hap-analysis-card-actions">
							<?php if ( ! empty( $card['missing_labels'] ) ) : ?>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary">Bilgiyi Tamamla</a>
							<?php else : ?>
								<span class="hap-status-pill hap-ready">Analiz hazır</span>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>

				<?php
				// Placeholder cards for sections with no active modules yet.
				$section_card_keys    = array_column( $section_cards, 'key' );
				$placeholder_sections = array_diff( array_keys( $section_config ), $section_card_keys );
				foreach ( $placeholder_sections as $section_key ) :
					$config = $section_config[ $section_key ];
				?>
					<article class="hap-analysis-card is-upcoming">
						<div class="hap-analysis-card-top">
							<div class="hap-section-card-icon"><?php echo esc_html( $config['icon'] ); ?></div>
							<div class="hap-analysis-card-head">
								<h3 class="hap-section-card-title"><?php echo esc_html( $config['label'] ); ?></h3>
								<p class="hap-section-card-copy"><?php echo esc_html( $config['description'] ); ?></p>
							</div>
							<span class="hap-status-pill hap-upcoming">Yakında</span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- D) Kişisel Analiz Önizlemeleri (araç linkleri yok, sadece durum) -->
		<?php if ( ! empty( $ready_result_cards ) || ! empty( $locked_result_cards ) ) : ?>
			<section class="hap-results-area" id="hap-results">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Analiz Önizlemeleri</span>
						<h2 class="hap-section-title">Hazır ve bekleyen analizler</h2>
					</div>
					<p class="hap-section-copy">Hangi analizlerin hazır, hangilerinin ek bilgi beklediğini buradan görüntüleyebilirsin.</p>
				</div>

				<div class="hap-results-grid">
					<?php foreach ( $ready_result_cards as $item ) :
						$module    = $item['module'];
						$title     = ! empty( $module['title'] ) ? $module['title'] : hap_profile_humanize_slug( $module['slug'] );
						$required  = $user_data->get_effective_required_fields( $module );
						$req_names = array();
						foreach ( $required as $field_key ) {
							$req_names[] = $field_labels[ $field_key ] ?? $field_key;
						}
					?>
						<article class="hap-result-card is-ready">
							<div class="hap-result-card-head">
								<h3><?php echo esc_html( $title ); ?></h3>
								<span class="hap-status-pill hap-ready">Hazırlanmaya hazır</span>
							</div>
							<p class="hap-result-card-copy">Bu analiz, mevcut profil bilgilerine göre hazır duruma geldi.</p>
							<p class="hap-result-card-detail">Kullanılan bilgiler: <?php echo esc_html( ! empty( $req_names ) ? implode( ', ', $req_names ) : 'Temel profil bilgileri' ); ?></p>
						</article>
					<?php endforeach; ?>

					<?php foreach ( $locked_result_cards as $item ) :
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
							<div class="hap-suggestion-list">
								<?php foreach ( $missing_labels as $label ) : ?>
									<span class="hap-missing-chip"><?php echo esc_html( $label ); ?></span>
								<?php endforeach; ?>
							</div>
							<div class="hap-result-card-actions">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary">Bilgiyi Tamamla</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<!-- E) Profil Düzenleme Formu — sadece ?edit=1 ise göster -->
		<?php if ( $edit_mode ) : ?>
			<section class="hap-profile-form-section" id="hap-profile-form-section">
				<div class="hap-section-heading">
					<div>
						<span class="hap-eyebrow">Profil Bilgileri</span>
						<h2 class="hap-section-title">Bilgilerini düzenle</h2>
					</div>
					<p class="hap-section-copy">Eksik bilgileri tamamladıkça yukarıdaki analiz kartları açılır.</p>
				</div>
				<?php include HAP_PLUGIN_DIR . 'templates/form-basic.php'; ?>
			</section>
		<?php endif; ?>

		<!-- Paylaşım Modalı -->
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

					<div class="hap-share-note">
						Doğum saati, doğum yeri ve benzeri hassas alanlar paylaşımlara dahil edilmez.
					</div>

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
