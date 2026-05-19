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

// Category definitions
$hap_categories = array(
	'astrology'     => array(
		'label' => html_entity_decode( 'Astroloji &amp; G&#246;ky&#252;z&#252;', ENT_QUOTES, 'UTF-8' ),
		'icon'  => html_entity_decode( '&#9800;', ENT_QUOTES, 'UTF-8' ),
		'slugs' => array(
			'gunes-burcu-hesaplama',
			'burc-dogum-araligi-hesaplama',
			'burc-dekani-hesaplama',
			'burc-derecesi-hesaplama',
			'ay-fazi-hesaplama',
			'venus-burcu-hesaplama',
			'mars-burcu-hesaplama',
			'jupiter-burcu-hesaplama',
			'saturn-burcu-hesaplama',
			'sidereal-burc-hesaplama',
			'merkur-burcu-hesaplama',
			'uranus-burcu-hesaplama',
			'neptun-burcu-hesaplama',
			'pluton-burcu-hesaplama',
			'vedik-burc-hesaplama',
			'kuzey-ay-dugumu-hesaplama',
			'burc-elementi-hesaplama',
			'burc-grubu-hesaplama',
		),
	),
	'numerology'    => array(
		'label' => 'Numeroloji',
		'icon'  => html_entity_decode( '&#128290;', ENT_QUOTES, 'UTF-8' ),
		'slugs' => array(
			'yasam-yolu-sayisi-hesaplama',
			'kisisel-yil-sayisi-hesaplama',
			'dogum-gunu-sayisi-hesaplama',
		),
	),
	'symbolic'      => array(
		'label' => 'Sembolik Profil',
		'icon'  => html_entity_decode( '&#127769;', ENT_QUOTES, 'UTF-8' ),
		'slugs' => array(
			'cin-burcu-hesaplama',
			'cin-burcu-yili-hesaplama',
			'cin-elementi-hesaplama',
			'cin-burcu-dongusu-hesaplama',
			'aura-rengi-hesaplama',
			'dogum-tarot-karti-hesaplama',
			'ask-tarot-karti-hesaplama',
		),
	),
	'compatibility' => array(
		'label' => html_entity_decode( 'Uyum &amp; &#304;li&#351;ki', ENT_QUOTES, 'UTF-8' ),
		'icon'  => html_entity_decode( '&#128158;', ENT_QUOTES, 'UTF-8' ),
		'slugs' => array(
			'burc-uyumu-hesaplama',
			'cin-burcuna-gore-ask-uyumu-hesaplama',
			'dogum-gunu-hesaplayici',
		),
	),
	'health'        => array(
		'label' => html_entity_decode( 'Sa&#287;l&#305;k &amp; Ya&#351;am', ENT_QUOTES, 'UTF-8' ),
		'icon'  => html_entity_decode( '&#128154;', ENT_QUOTES, 'UTF-8' ),
		'slugs' => array(
			'vucut-kitle-indeksi-hesaplama',
			'ideal-kilo-hesaplama',
			'gunluk-su-ihtiyaci-hesaplama',
			'spor-protein-ihtiyaci-hesaplama',
			'adimdan-kaloriye-hesaplama',
			'bazal-metabolizma-hizi-hesaplama',
			'dinlenme-metabolizma-hizi',
			'basit-kalori-ihtiyaci-hesaplama',
			'aktivite-katsayisi',
			'gunluk-kalori-ihtiyaci-hesaplama',
			'maksimum-nabiz-hesaplama',
			'hedef-nabiz-bolgesi-hesaplama',
			'hedef-nabiz-hesaplama',
			'nabiz-bolgesi-hesaplama',
		),
	),
	'sport'         => array(
		'label' => 'Spor & Aktivite',
		'icon'  => html_entity_decode( '&#127939;', ENT_QUOTES, 'UTF-8' ),
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
				$parts[] = html_entity_decode( 'G&#252;ne&#351; burcun ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			$v = $rlabel( 'burc-dekani-hesaplama' );
			if ( $v ) {
				$parts[] = $v;
			}
			$v = $rval( 'ay-fazi-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'Ay faz&#305;: ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			break;

		case 'numerology':
			$v = $rval( 'yasam-yolu-sayisi-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'Ya&#351;am yolu say&#305;n ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			$v = $rval( 'kisisel-yil-sayisi-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'bu y&#305;l&#305;n ki&#351;isel say&#305;s&#305; ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			$v = $rval( 'dogum-gunu-sayisi-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'do&#287;um g&#252;n&#252; say&#305;n ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			break;

		case 'symbolic':
			$v = $rval( 'cin-burcu-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( '&#199;in burcun ', ENT_QUOTES, 'UTF-8' ) . $v;
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
				$parts[] = html_entity_decode( 'do&#287;um tarot kart&#305;n ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			break;

		case 'compatibility':
			$v = $rval( 'burc-uyumu-hesaplama' );
			$u = $runit( 'burc-uyumu-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'Bur&#231; uyumun ', ENT_QUOTES, 'UTF-8' ) . $v . ( $u ? ' ' . $u : '' );
			}
			$v = $rlabel( 'cin-burcuna-gore-ask-uyumu-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( '&#199;in burcu a&#351;k uyumu: ', ENT_QUOTES, 'UTF-8' ) . $v;
			}
			break;

		case 'health':
			$v = $rval( 'vucut-kitle-indeksi-hesaplama' );
			$l = $rlabel( 'vucut-kitle-indeksi-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'V&#252;cut kitle indeksin ', ENT_QUOTES, 'UTF-8' ) . $v . ( $l ? ' (' . $l . ')' : '' );
			}
			$v = $rval( 'gunluk-su-ihtiyaci-hesaplama' );
			$u = $runit( 'gunluk-su-ihtiyaci-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'g&#252;nl&#252;k ', ENT_QUOTES, 'UTF-8' ) . $v . ( $u ? ' ' . $u : '' ) . html_entity_decode( ' su &#246;nerilir', ENT_QUOTES, 'UTF-8' );
			}
			$v = $rval( 'ideal-kilo-hesaplama' );
			$u = $runit( 'ideal-kilo-hesaplama' );
			if ( $v ) {
				$parts[] = html_entity_decode( 'ideal kilonu ', ENT_QUOTES, 'UTF-8' ) . $v . ( $u ? ' ' . $u : '' ) . html_entity_decode( ' olarak hesapland&#305;', ENT_QUOTES, 'UTF-8' );
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

// View-layer display helpers - no backend logic, display only

$cat_teasers = array(
	'astrology'     => html_entity_decode( 'Duygusal y&#246;n&#252;n, aidiyet ihtiyac&#305;n ve i&#231; ritmin &#246;ne &#231;&#305;k&#305;yor.', ENT_QUOTES, 'UTF-8' ),
	'numerology'    => html_entity_decode( 'Do&#287;um tarihinden gelen say&#305; temalar&#305;n&#305; &#246;zetledik.', ENT_QUOTES, 'UTF-8' ),
	'symbolic'      => html_entity_decode( 'Tarot, aura ve sembolik g&#246;stergeleri tek yerde toplad&#305;k.', ENT_QUOTES, 'UTF-8' ),
	'compatibility' => html_entity_decode( 'Partner do&#287;um tarihiyle ili&#351;ki dinami&#287;ini yorumlad&#305;k.', ENT_QUOTES, 'UTF-8' ),
	'health'        => html_entity_decode( 'V&#252;cut dengeni, ideal kilo aral&#305;&#287;&#305;n&#305; ve g&#252;nl&#252;k ihtiya&#231;lar&#305;n&#305; birlikte yorumlad&#305;k.', ENT_QUOTES, 'UTF-8' ),
	'sport'         => html_entity_decode( '30 dakikal&#305;k egzersiz varsay&#305;m&#305;yla aktiviteleri kar&#351;&#305;la&#351;t&#305;rd&#305;k.', ENT_QUOTES, 'UTF-8' ),
);

$result_explanations = array(
	'gunes-burcu-hesaplama'         => html_entity_decode( 'G&#252;ne&#351; burcun temel karakter tonunu i&#351;aret eder; sembolik bir tema olarak yorumlanabilir.', ENT_QUOTES, 'UTF-8' ),
	'burc-dogum-araligi-hesaplama'  => html_entity_decode( 'G&#252;ne&#351; burcunun tarih aral&#305;&#287;&#305;n&#305; g&#246;sterir; astrolojik referans bilgisidir.', ENT_QUOTES, 'UTF-8' ),
	'burc-dekani-hesaplama'         => html_entity_decode( 'Burcunun hangi dekan d&#246;nemine denk geldi&#287;ini g&#246;sterir.', ENT_QUOTES, 'UTF-8' ),
	'ay-fazi-hesaplama'             => html_entity_decode( 'Do&#287;um an&#305;ndaki Ay faz&#305;n&#305; g&#246;sterir; sembolik referans olarak yorumlanabilir.', ENT_QUOTES, 'UTF-8' ),
	'yasam-yolu-sayisi-hesaplama'   => html_entity_decode( 'Do&#287;um tarihinden hesaplanan genel numerolojik tema g&#246;stergesidir.', ENT_QUOTES, 'UTF-8' ),
	'cin-burcu-hesaplama'           => html_entity_decode( '&#199;in takvimindeki do&#287;um y&#305;l&#305;na kar&#351;&#305;l&#305;k gelen hayvan sembol&#252;n&#252; g&#246;sterir.', ENT_QUOTES, 'UTF-8' ),
	'dogum-tarot-karti-hesaplama'   => html_entity_decode( 'Do&#287;um tarihine dayal&#305; numerolojik tarot temas&#305;n&#305; g&#246;sterir; kehanet de&#287;ildir.', ENT_QUOTES, 'UTF-8' ),
	'burc-uyumu-hesaplama'          => html_entity_decode( '&#304;ki burcun sembolik uyum skorunu g&#246;sterir; mutlak &#246;ng&#246;r&#252; de&#287;ildir.', ENT_QUOTES, 'UTF-8' ),
	'vucut-kitle-indeksi-hesaplama' => html_entity_decode( 'V&#252;cut a&#287;&#305;rl&#305;&#287;&#305;-boy oran&#305;n&#305; g&#246;sterir. Bilgilendirme ama&#231;l&#305;d&#305;r; t&#305;bbi te&#351;his de&#287;ildir.', ENT_QUOTES, 'UTF-8' ),
	'ideal-kilo-hesaplama'          => html_entity_decode( 'Yakla&#351;&#305;k referans aral&#305;&#287;&#305;d&#305;r; ki&#351;isel sa&#287;l&#305;k durumuna g&#246;re de&#287;i&#351;ebilir.', ENT_QUOTES, 'UTF-8' ),
	'merkur-burcu-hesaplama'        => html_entity_decode( 'Merk&#252;r konumu d&#252;&#351;&#252;nce ve ileti&#351;im temas&#305;n&#305; i&#351;aret eder.', ENT_QUOTES, 'UTF-8' ),
	'uranus-burcu-hesaplama'        => html_entity_decode( 'Uran&#252;s ku&#351;aksal temalar&#305; g&#246;steren sembolik bir referanst&#305;r.', ENT_QUOTES, 'UTF-8' ),
	'neptun-burcu-hesaplama'        => html_entity_decode( 'Nept&#252;n ku&#351;aksal ve sezgisel temalar&#305; g&#246;steren sembolik bir referanst&#305;r.', ENT_QUOTES, 'UTF-8' ),
	'pluton-burcu-hesaplama'        => html_entity_decode( 'Pl&#252;ton d&#246;n&#252;&#351;&#252;m ve ku&#351;ak temas&#305;n&#305; i&#351;aret eden sembolik bir g&#246;stergedir.', ENT_QUOTES, 'UTF-8' ),
	'gunluk-su-ihtiyaci-hesaplama'  => html_entity_decode( 'V&#252;cut a&#287;&#305;rl&#305;&#287;&#305;na g&#246;re tahmini g&#252;nl&#252;k su ihtiyac&#305;n&#305; g&#246;sterir.', ENT_QUOTES, 'UTF-8' ),
	'spor-protein-ihtiyaci-hesaplama' => html_entity_decode( 'Aktivite d&#252;zeyine g&#246;re tahmini protein ihtiyac&#305;n&#305; g&#246;sterir.', ENT_QUOTES, 'UTF-8' ),
	'kosu-kalori-yakimi-hesaplama'  => html_entity_decode( 'Yakla&#351;&#305;k enerji harcamas&#305;d&#305;r; tempo ve kondisyonla de&#287;i&#351;ir.', ENT_QUOTES, 'UTF-8' ),
	'yuzme-kalori-yakimi-hesaplama' => html_entity_decode( 'Yakla&#351;&#305;k enerji harcamas&#305;d&#305;r; stil ve yo&#287;unlukla de&#287;i&#351;ir.', ENT_QUOTES, 'UTF-8' ),
);

$personal_insights  = array();
$_insight_sources   = array(
	'gunes-burcu-hesaplama'         => array( 'label' => html_entity_decode( 'G&#252;ne&#351; Burcu', ENT_QUOTES, 'UTF-8' ), 'suffix' => html_entity_decode( 'karakterinin duygusal ve sosyal tonunu i&#351;aret eder.', ENT_QUOTES, 'UTF-8' ) ),
	'yasam-yolu-sayisi-hesaplama'   => array( 'label' => html_entity_decode( 'Ya&#351;am Yolu Say&#305;s&#305;', ENT_QUOTES, 'UTF-8' ), 'suffix' => html_entity_decode( 'do&#287;um tarihinden t&#252;retilen numerolojik tema g&#246;stergesi.', ENT_QUOTES, 'UTF-8' ) ),
	'vucut-kitle-indeksi-hesaplama' => array( 'label' => html_entity_decode( 'V&#252;cut Kitle &#304;ndeksi', ENT_QUOTES, 'UTF-8' ), 'suffix' => html_entity_decode( 'bilgilendirme ama&#231;l&#305;d&#305;r; t&#305;bbi te&#351;his de&#287;ildir.', ENT_QUOTES, 'UTF-8' ) ),
	'cin-burcu-hesaplama'           => array( 'label' => html_entity_decode( '&#199;in Burcu', ENT_QUOTES, 'UTF-8' ), 'suffix' => html_entity_decode( '&#199;in takvimindeki sembolik hayvan g&#246;stergesi.', ENT_QUOTES, 'UTF-8' ) ),
	'burc-uyumu-hesaplama'          => array( 'label' => html_entity_decode( 'Bur&#231; Uyumu', ENT_QUOTES, 'UTF-8' ), 'suffix' => html_entity_decode( 'sembolik uyum g&#246;stergesidir; mutlak &#246;ng&#246;r&#252; de&#287;ildir.', ENT_QUOTES, 'UTF-8' ) ),
	'dogum-tarot-karti-hesaplama'   => array( 'label' => html_entity_decode( 'Do&#287;um Tarot Kart&#305;', ENT_QUOTES, 'UTF-8' ), 'suffix' => html_entity_decode( 'sembolik tema g&#246;stergesidir; kehanet de&#287;ildir.', ENT_QUOTES, 'UTF-8' ) ),
);
foreach ( $_insight_sources as $_i_slug => $_i_def ) {
	if ( ! isset( $ready_results[ $_i_slug ] ) ) {
		continue;
	}
	$_i_val  = (string) ( $ready_results[ $_i_slug ]['result']['value'] ?? '' );
	$_i_unit = (string) ( $ready_results[ $_i_slug ]['result']['unit'] ?? '' );
	$_i_lbl  = (string) ( $ready_results[ $_i_slug ]['result']['label'] ?? '' );
	if ( '' === $_i_val ) {
		continue;
	}
	$personal_insights[] = array(
		'label'  => $_i_def['label'],
		'value'  => $_i_val . ( $_i_unit ? ' ' . $_i_unit : '' ) . ( $_i_lbl ? ' - ' . $_i_lbl : '' ),
		'suffix' => $_i_def['suffix'],
	);
	if ( count( $personal_insights ) >= 4 ) {
		break;
	}
}
unset( $_insight_sources, $_i_slug, $_i_def, $_i_val, $_i_unit, $_i_lbl );

$truncate_text = static function ( $text, $limit = 130 ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return '';
	}

	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		return mb_strlen( $text ) > $limit ? mb_substr( $text, 0, $limit ) . '...' : $text;
	}

	return strlen( $text ) > $limit ? substr( $text, 0, $limit ) . '...' : $text;
};

$build_result_chips = static function ( array $items, $limit = 4 ) {
	$chips = array();

	foreach ( $items as $runner_item ) {
		$result = $runner_item['result'] ?? array();
		$value  = trim( (string) ( $result['value'] ?? '' ) );
		$unit   = trim( (string) ( $result['unit'] ?? '' ) );
		$label  = trim( (string) ( $result['label'] ?? '' ) );

		$text = $value;
		if ( $value && $unit ) {
			$text .= ' ' . $unit;
		} elseif ( ! $text ) {
			$text = $label;
		}

		if ( ! $text ) {
			continue;
		}

		$chips[] = $text;
		if ( count( $chips ) >= $limit ) {
			break;
		}
	}

	return $chips;
};

$render_ready_result_card = static function ( $slug, $runner_item, $is_featured = false ) use ( $result_explanations, $truncate_text ) {
	$result   = $runner_item['result'] ?? array();
	$card_sev = hc_get_result_severity( $slug, $result );
	$sev_pill = ( 'vucut-kitle-indeksi-hesaplama' === $slug ) ? hc_get_vki_pill( $result ) : null;
	$classes  = 'hap-result-card is-ready ' . $card_sev;

	if ( $is_featured ) {
		$classes .= ' hap-result-card--featured';
	}

	ob_start();
	?>
	<article class="<?php echo esc_attr( $classes ); ?>">
		<div class="hap-result-card-head">
			<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
			<div class="hap-result-card-pills">
				<?php if ( $is_featured ) : ?>
					<span class="hap-status-pill hap-ready">Haz&#305;r</span>
				<?php endif; ?>
				<?php if ( $sev_pill ) : ?>
					<span class="hap-severity-pill <?php echo esc_attr( $sev_pill['cls'] ); ?>"><?php echo esc_html( $sev_pill['label'] ); ?></span>
				<?php endif; ?>
			</div>
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
			<p class="hap-result-description"><?php echo esc_html( $truncate_text( $result['description'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $result['warnings'] ) ) : ?>
			<p class="hap-result-warning"><?php echo esc_html( reset( $result['warnings'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $result_explanations[ $slug ] ) ) : ?>
			<div class="hap-result-meaning">
				<span class="hap-result-meaning-label">Bu ne anlama geliyor?</span>
				<p class="hap-result-meaning-text"><?php echo esc_html( $result_explanations[ $slug ] ); ?></p>
			</div>
		<?php endif; ?>
	</article>
	<?php

	return trim( ob_get_clean() );
};

$analysis_nav_items = array(
	array(
		'target' => 'overview',
		'icon'   => html_entity_decode( '&#127968;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( 'Genel Bak&#305;&#351;', ENT_QUOTES, 'UTF-8' ),
		'count'  => null,
	),
	array(
		'target' => 'featured',
		'icon'   => html_entity_decode( '&#11088;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( '&#214;ne &#199;&#305;kan', ENT_QUOTES, 'UTF-8' ),
		'count'  => count( $featured_results ),
	),
	array(
		'target' => 'astrology',
		'icon'   => html_entity_decode( '&#9800;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( 'Astroloji &amp; G&#246;ky&#252;z&#252;', ENT_QUOTES, 'UTF-8' ),
		'count'  => $cat_results['astrology']['count'] ?? 0,
	),
	array(
		'target' => 'numerology',
		'icon'   => html_entity_decode( '&#128290;', ENT_QUOTES, 'UTF-8' ),
		'label'  => 'Numeroloji',
		'count'  => $cat_results['numerology']['count'] ?? 0,
	),
	array(
		'target' => 'symbolic',
		'icon'   => html_entity_decode( '&#127769;', ENT_QUOTES, 'UTF-8' ),
		'label'  => 'Sembolik Profil',
		'count'  => $cat_results['symbolic']['count'] ?? 0,
	),
	array(
		'target' => 'compatibility',
		'icon'   => html_entity_decode( '&#128158;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( 'Uyum &amp; &#304;li&#351;ki', ENT_QUOTES, 'UTF-8' ),
		'count'  => $cat_results['compatibility']['count'] ?? 0,
	),
	array(
		'target' => 'health',
		'icon'   => html_entity_decode( '&#128154;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( 'Sa&#287;l&#305;k &amp; Ya&#351;am', ENT_QUOTES, 'UTF-8' ),
		'count'  => $cat_results['health']['count'] ?? 0,
	),
	array(
		'target' => 'sport',
		'icon'   => html_entity_decode( '&#127939;', ENT_QUOTES, 'UTF-8' ),
		'label'  => 'Spor & Aktivite',
		'count'  => $cat_results['sport']['count'] ?? 0,
	),
	array(
		'target' => 'ai',
		'icon'   => html_entity_decode( '&#129302;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( 'AI Ki&#351;isel Analiz', ENT_QUOTES, 'UTF-8' ),
		'count'  => null,
	),
	array(
		'target' => 'advanced',
		'icon'   => html_entity_decode( '&#129514;', ENT_QUOTES, 'UTF-8' ),
		'label'  => html_entity_decode( 'Geli&#351;mi&#351; Analizler', ENT_QUOTES, 'UTF-8' ),
		'count'  => count( $frontend_cards ),
	),
);

// Severity helpers - view layer only, no backend logic
if ( ! function_exists( 'hc_get_result_severity' ) ) {
	function hc_get_result_severity( $slug, $result ) {
		if ( 'vucut-kitle-indeksi-hesaplama' === $slug ) {
			$val = (float) ( $result['value'] ?? 0 );
			if ( $val > 0 && $val < 18.5 ) { return 'is-warning'; }
			if ( $val < 25.0 )              { return 'is-success'; }
			if ( $val < 30.0 )              { return 'is-warning'; }
			return 'is-danger';
		}
		$mystic = array(
			'gunes-burcu-hesaplama', 'burc-dogum-araligi-hesaplama', 'burc-dekani-hesaplama',
			'burc-derecesi-hesaplama', 'ay-fazi-hesaplama', 'venus-burcu-hesaplama',
			'mars-burcu-hesaplama', 'jupiter-burcu-hesaplama', 'saturn-burcu-hesaplama',
			'sidereal-burc-hesaplama', 'yasam-yolu-sayisi-hesaplama', 'kisisel-yil-sayisi-hesaplama',
			'dogum-gunu-sayisi-hesaplama', 'cin-burcu-hesaplama', 'cin-burcu-yili-hesaplama',
			'cin-elementi-hesaplama', 'aura-rengi-hesaplama', 'dogum-tarot-karti-hesaplama',
			'ask-tarot-karti-hesaplama', 'burc-uyumu-hesaplama', 'cin-burcuna-gore-ask-uyumu-hesaplama',
			'dogum-gunu-hesaplayici',
			'merkur-burcu-hesaplama', 'uranus-burcu-hesaplama', 'neptun-burcu-hesaplama',
			'pluton-burcu-hesaplama', 'vedik-burc-hesaplama', 'kuzey-ay-dugumu-hesaplama',
			'cin-burcu-dongusu-hesaplama', 'burc-elementi-hesaplama', 'burc-grubu-hesaplama',
		);
		if ( in_array( $slug, $mystic, true ) ) { return 'is-mystic'; }
		$sport = array(
			'yuruyus-kalori-yakimi-hesaplama', 'kosu-kalori-yakimi-hesaplama', 'bisiklet-kalori-yakimi-hesaplama',
			'yuzme-kalori-yakimi-hesaplama', 'ip-atlama-kalori-yakimi-hesaplama', 'yoga-kalori-yakimi-hesaplama',
			'pilates-kalori-yakimi-hesaplama', 'zumba-kalori-yakimi-hesaplama', 'basketbol-kalori-yakimi-hesaplama',
			'futbol-kalori-yakimi-hesaplama', 'adimdan-kaloriye-hesaplama',
		);
		if ( in_array( $slug, $sport, true ) ) { return 'is-neutral'; }
		$info = array(
			'bazal-metabolizma-hizi-hesaplama', 'dinlenme-metabolizma-hizi', 'basit-kalori-ihtiyaci-hesaplama',
			'maksimum-nabiz-hesaplama', 'hedef-nabiz-bolgesi-hesaplama', 'hedef-nabiz-hesaplama',
			'nabiz-bolgesi-hesaplama', 'gunluk-su-ihtiyaci-hesaplama', 'spor-protein-ihtiyaci-hesaplama',
			'ideal-kilo-hesaplama', 'aktivite-katsayisi', 'gunluk-kalori-ihtiyaci-hesaplama',
		);
		if ( in_array( $slug, $info, true ) ) { return 'is-info'; }
		return 'is-success';
	}
}

if ( ! function_exists( 'hc_get_vki_pill' ) ) {
	function hc_get_vki_pill( $result ) {
		$val = (float) ( $result['value'] ?? 0 );
		if ( $val <= 0 ) { return null; }
		if ( $val < 18.5 ) { return array( 'label' => html_entity_decode( 'D&#252;&#351;&#252;k', ENT_QUOTES, 'UTF-8' ),  'cls' => 'warn' ); }
		if ( $val < 25.0 ) { return array( 'label' => 'Normal', 'cls' => 'ok' ); }
		if ( $val < 30.0 ) { return array( 'label' => 'Dikkat', 'cls' => 'warn' ); }
		return array( 'label' => html_entity_decode( 'Y&#252;ksek', ENT_QUOTES, 'UTF-8' ), 'cls' => 'danger' );
	}
}
?>
<div class="hap-profile-app">
	<div class="hap-dashboard" id="hap-dashboard">
		<div class="hap-analysis-layout">
			<aside class="hap-analysis-sidebar">
				<div class="hap-analysis-sidebar-card hap-analysis-sidebar-card--profile">
					<div class="hap-analysis-profile-head">
						<?php echo get_avatar( $user_id, 64, '', '', array( 'class' => 'hap-analysis-avatar' ) ); ?>
						<div class="hap-analysis-profile-meta">
							<strong><?php echo esc_html( $nickname ); ?></strong>
					<span>%<?php echo absint( $minimum_completion ); ?> profil tamamland&#305;</span>
						</div>
					</div>
					<div class="hap-profile-accordion-content">
						<div class="hap-analysis-progress" aria-hidden="true">
							<div class="hap-analysis-progress-fill" style="width: <?php echo absint( $minimum_completion ); ?>%"></div>
						</div>
						<div class="hap-analysis-sidebar-stats">
							<div>
								<strong><?php echo esc_html( count( $ready_results ) ); ?></strong>
						<span>sonu&#231; haz&#305;r</span>
							</div>
							<div>
								<strong>%<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
						<span>analiz haz&#305;rl&#305;&#287;&#305;</span>
							</div>
						</div>
						<div class="hap-analysis-sidebar-actions">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary hap-btn-full">Profilimi g&#252;ncelle</a>
							<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
						<button class="hap-btn hap-btn-secondary hap-btn-full" id="hap-open-share-sidebar" type="button">Payla&#351;</button>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<nav class="hap-analysis-sidebar-card hap-analysis-nav" aria-label="Analiz navigasyonu">
					<?php foreach ( $analysis_nav_items as $nav_item ) : ?>
						<?php
						$has_count = null !== $nav_item['count'];
						$badge_cls = 'hap-analysis-nav-badge';
						if ( in_array( $nav_item['target'], array( 'astrology', 'numerology', 'symbolic', 'compatibility', 'health', 'sport', 'featured' ), true ) ) {
							$badge_cls .= ' is-ready';
						} elseif ( 'advanced' === $nav_item['target'] ) {
							$badge_cls .= ' is-neutral';
						}
						?>
						<button type="button" class="hap-analysis-nav-item<?php echo 'overview' === $nav_item['target'] ? ' is-active' : ''; ?>" data-analysis-target="<?php echo esc_attr( $nav_item['target'] ); ?>" aria-pressed="<?php echo 'overview' === $nav_item['target'] ? 'true' : 'false'; ?>">
							<span class="hap-analysis-nav-icon"><?php echo esc_html( $nav_item['icon'] ); ?></span>
							<span class="hap-analysis-nav-label"><?php echo esc_html( $nav_item['label'] ); ?></span>
							<?php if ( $has_count ) : ?>
								<span class="<?php echo esc_attr( $badge_cls ); ?>"><?php echo absint( $nav_item['count'] ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</nav>
			</aside>

			<div class="hap-analysis-main">
				<section class="hap-analysis-panel is-active" data-analysis-panel="overview" id="hap-analysis-panel-overview">
					<div class="hap-overview-compact-hero">
						<div class="hap-overview-compact-copy">
					<span class="hap-eyebrow">Genel Bak&#305;&#351;</span>
							<h1 class="hap-section-title">Merhaba, <?php echo esc_html( $nickname ); ?></h1>
					<p class="hap-section-copy">Haz&#305;r sonu&#231;lar&#305;n&#305; daha h&#305;zl&#305; gezmek i&#231;in soldan bir analiz kategorisi se&#231;. Bu panel, en &#246;nemli sinyalleri tek bak&#305;&#351;ta toplar.</p>
							<div class="hap-hero-actions">
						<a href="<?php echo esc_url( $edit_url ); ?>" class="hap-btn hap-btn-primary">Profilimi g&#252;ncelle</a>
								<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
									<button class="hap-btn hap-btn-secondary" id="hap-open-share" type="button">Profilimi payla&#351;</button>
								<?php endif; ?>
							</div>
						</div>
						<div class="hap-overview-compact-aside">
							<div class="hap-overview-compact-note">
						<strong><?php echo esc_html( count( $ready_results ) ); ?> sonu&#231; haz&#305;r</strong>
						<span><?php echo esc_html( $analysis_stats['filled'] . '/' . $analysis_stats['total'] ); ?> gerekli alan analiz &#252;retimi i&#231;in haz&#305;r.</span>
							</div>
							<?php if ( ! empty( $frontend_cards ) ) : ?>
								<div class="hap-overview-compact-note is-soft">
						<strong><?php echo esc_html( count( $frontend_cards ) ); ?> geli&#351;mi&#351; analiz</strong>
						<span>Geli&#351;mi&#351; analiz motoru haz&#305;rlan&#305;yor.</span>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="hap-analysis-kpi-grid">
						<div class="hap-analysis-kpi-card is-ready">
					<span class="hap-analysis-kpi-label">Haz&#305;r Sonu&#231;</span>
							<strong class="hap-analysis-kpi-value"><?php echo esc_html( count( $ready_results ) ); ?></strong>
					<span class="hap-analysis-kpi-meta">Profilinden &#252;retilen sonu&#231;</span>
						</div>
						<div class="hap-analysis-kpi-card">
							<span class="hap-analysis-kpi-label">Profil</span>
							<strong class="hap-analysis-kpi-value">%<?php echo absint( $minimum_completion ); ?></strong>
					<span class="hap-analysis-kpi-meta">Minimum alanlar tamamland&#305;</span>
						</div>
						<div class="hap-analysis-kpi-card">
					<span class="hap-analysis-kpi-label">Analiz Haz&#305;rl&#305;&#287;&#305;</span>
							<strong class="hap-analysis-kpi-value">%<?php echo absint( $analysis_stats['percentage'] ); ?></strong>
					<span class="hap-analysis-kpi-meta"><?php echo esc_html( $analysis_stats['filled'] . '/' . $analysis_stats['total'] . ' gerekli alan haz&#305;r' ); ?></span>
						</div>
					</div>

					<?php if ( ! empty( $personal_insights ) ) : ?>
						<div class="hap-analysis-panel-block">
							<div class="hap-analysis-panel-head">
								<div>
					<span class="hap-eyebrow">Senin &#304;&#231;in Haz&#305;rland&#305;</span>
					<h2 class="hap-section-title">Ki&#351;isel &#214;zet</h2>
					<p class="hap-section-copy">Profil bilgilerine g&#246;re &#246;ne &#231;&#305;kan deterministic sonu&#231;lar&#305;n k&#305;sa &#246;zeti.</p>
								</div>
							</div>
							<div class="hap-personal-summary-grid">
								<?php foreach ( $personal_insights as $_pi ) : ?>
									<div class="hap-personal-insight">
										<span class="hap-personal-insight-label"><?php echo esc_html( $_pi['label'] ); ?></span>
										<strong class="hap-personal-insight-value"><?php echo esc_html( $_pi['value'] ); ?></strong>
										<span class="hap-personal-insight-suffix"><?php echo esc_html( $_pi['suffix'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<div class="hap-analysis-panel-block">
						<div class="hap-analysis-panel-head">
							<div>
					<span class="hap-eyebrow">&#214;ne &#199;&#305;kan</span>
					<h2 class="hap-section-title">&#214;ne &#199;&#305;kan Sonu&#231;lar</h2>
					<p class="hap-section-copy">Detayl&#305; sonu&#231;lar&#305; g&#246;rmek i&#231;in soldan bir analiz kategorisi se&#231;.</p>
							</div>
							<?php if ( ! empty( $featured_results ) ) : ?>
					<button type="button" class="hap-btn hap-btn-secondary hap-analysis-head-action" data-analysis-target="featured">T&#252;m&#252;n&#252; a&#231;</button>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $featured_results ) ) : ?>
							<div class="hap-featured-results">
								<?php foreach ( $featured_results as $runner_item ) : ?>
									<?php echo $render_ready_result_card( $runner_item['module']['slug'] ?? '', $runner_item, true ); ?>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="hap-analysis-empty-state">
					<strong>Hen&#252;z &#246;ne &#231;&#305;kan sonu&#231; g&#246;r&#252;nm&#252;yor.</strong>
					<span>Profil bilgilerini g&#252;ncelledik&#231;e burada en g&#252;&#231;l&#252; analizler listelenecek.</span>
							</div>
						<?php endif; ?>
					</div>

					<div class="hap-analysis-panel-block">
						<div class="hap-analysis-panel-head">
							<div>
								<span class="hap-eyebrow">Kategoriler</span>
								<h2 class="hap-section-title">Analiz Kategorileri</h2>
					<p class="hap-section-copy">Her kategori art&#305;k kendi panelinde a&#231;&#305;l&#305;r; t&#252;m detaylar ayn&#305; anda a&#351;a&#287;&#305; do&#287;ru akmaz.</p>
							</div>
						</div>
						<div class="hap-analysis-category-grid">
							<?php foreach ( $cat_results as $cat_key => $cat ) : ?>
								<button type="button" class="hap-analysis-category-card" data-analysis-target="<?php echo esc_attr( $cat_key ); ?>">
									<div class="hap-analysis-category-card-top">
										<span class="hap-analysis-category-icon"><?php echo esc_html( $cat['icon'] ); ?></span>
										<span class="hap-analysis-nav-badge is-ready"><?php echo absint( $cat['count'] ); ?></span>
									</div>
									<strong><?php echo esc_html( $cat['label'] ); ?></strong>
									<p><?php echo esc_html( $cat_teasers[ $cat_key ] ?? '' ); ?></p>
									<div class="hap-analysis-chip-row">
										<?php foreach ( $build_result_chips( array_values( $cat['ready'] ), 3 ) as $chip_text ) : ?>
											<span class="hap-analysis-chip"><?php echo esc_html( $chip_text ); ?></span>
										<?php endforeach; ?>
									</div>
								</button>
							<?php endforeach; ?>
						</div>
					</div>

					<?php if ( ! empty( $missing_cards ) ) : ?>
						<div class="hap-analysis-panel-block">
							<div class="hap-analysis-panel-head">
								<div>
									<span class="hap-eyebrow">Profil Tamamlama</span>
					<h2 class="hap-section-title">Eksik Bilgiyle A&#231;&#305;lacak Analizler</h2>
					<p class="hap-section-copy">Baz&#305; analizler i&#231;in ek profil alanlar&#305;na ihtiyac&#305;m&#305;z var.</p>
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
						</div>
					<?php endif; ?>
				</section>

				<section class="hap-analysis-panel" data-analysis-panel="featured" id="hap-analysis-panel-featured">
					<div class="hap-analysis-panel-head">
						<div>
					<span class="hap-eyebrow">&#214;ne &#199;&#305;kan</span>
					<h2 class="hap-section-title">&#214;ne &#199;&#305;kan Sonu&#231;lar</h2>
					<p class="hap-section-copy">Profilinden &#252;retilen en g&#252;&#231;l&#252; ve h&#305;zl&#305; okunabilir sonu&#231;lar burada toplan&#305;r.</p>
						</div>
				<span class="hap-status-pill hap-ready"><?php echo absint( count( $featured_results ) ); ?> sonu&#231;</span>
					</div>
					<?php if ( ! empty( $featured_results ) ) : ?>
						<div class="hap-featured-results">
							<?php foreach ( $featured_results as $runner_item ) : ?>
								<?php echo $render_ready_result_card( $runner_item['module']['slug'] ?? '', $runner_item, true ); ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="hap-analysis-empty-state">
					<strong>&#214;ne &#231;&#305;kan sonu&#231; listesi hen&#252;z dolmad&#305;.</strong>
					<span>Yeni haz&#305;r sonu&#231;lar geldik&#231;e burada otomatik olarak g&#246;r&#252;n&#252;r.</span>
						</div>
					<?php endif; ?>
				</section>

				<?php foreach ( $cat_results as $cat_key => $cat ) : ?>
					<?php
					$cat_visible = array_slice( $cat['ready'], 0, 6, true );
					$cat_hidden  = array_slice( $cat['ready'], 6, null, true );
					$cat_insight = $build_cat_insight( $cat_key, $cat['ready'] );
					$cat_chips   = $build_result_chips( array_values( $cat['ready'] ), 4 );
					?>
					<section class="hap-analysis-panel" data-analysis-panel="<?php echo esc_attr( $cat_key ); ?>" id="hap-analysis-panel-<?php echo esc_attr( $cat_key ); ?>">
						<div class="hap-analysis-panel-head">
							<div>
								<span class="hap-eyebrow"><?php echo esc_html( $cat['label'] ); ?></span>
								<h2 class="hap-section-title"><?php echo esc_html( $cat['label'] ); ?></h2>
								<p class="hap-section-copy"><?php echo esc_html( $cat_teasers[ $cat_key ] ?? '' ); ?></p>
							</div>
				<span class="hap-status-pill hap-ready"><?php echo absint( $cat['count'] ); ?> sonu&#231;</span>
						</div>

						<?php if ( $cat_insight ) : ?>
							<div class="hap-analysis-insight"><?php echo esc_html( $cat_insight ); ?></div>
						<?php endif; ?>

						<?php if ( ! empty( $cat_chips ) ) : ?>
							<div class="hap-analysis-chip-row">
								<?php foreach ( $cat_chips as $chip_text ) : ?>
									<span class="hap-analysis-chip"><?php echo esc_html( $chip_text ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ( 'sport' === $cat_key ) : ?>
					<p class="hap-cat-note">Kalori de&#287;erleri 30 dakika egzersiz varsay&#305;m&#305; ile hesaplanm&#305;&#351;t&#305;r.</p>
						<?php elseif ( 'health' === $cat_key ) : ?>
					<p class="hap-cat-note">Bilgilendirme ama&#231;l&#305;d&#305;r; t&#305;bbi te&#351;his veya tedavi &#246;nerisi de&#287;ildir.</p>
						<?php endif; ?>

						<?php if ( ! empty( $cat_visible ) ) : ?>
							<div class="hap-cat-results-grid">
								<?php foreach ( $cat_visible as $r_slug => $runner_item ) : ?>
									<?php echo $render_ready_result_card( $r_slug, $runner_item ); ?>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div class="hap-analysis-empty-state">
					<strong>Bu kategoride hen&#252;z haz&#305;r sonu&#231; g&#246;r&#252;nm&#252;yor.</strong>
					<span>&#304;lgili alanlar&#305; g&#252;ncelledi&#287;inde sonu&#231;lar burada listelenecek.</span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $cat_hidden ) ) : ?>
							<details class="hap-results-collapsible">
					<summary class="hap-results-collapsible-summary">+ <?php echo absint( count( $cat_hidden ) ); ?> sonu&#231; daha g&#246;ster</summary>
								<div class="hap-cat-results-grid hap-cat-results-grid--extra">
									<?php foreach ( $cat_hidden as $r_slug => $runner_item ) : ?>
										<?php echo $render_ready_result_card( $r_slug, $runner_item ); ?>
									<?php endforeach; ?>
								</div>
							</details>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>

				<section class="hap-analysis-panel hap-ai-section" data-analysis-panel="ai" id="hap-analysis-panel-ai">
					<div class="hap-analysis-panel-head">
						<div>
							<span class="hap-eyebrow">Yapay Zeka</span>
					<h2 class="hap-section-title">AI Ki&#351;isel Analiz</h2>
					<p class="hap-section-copy">Mevcut AI yorum katman&#305; ve durum ak&#305;&#351;&#305; bu panelde korunur.</p>
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
											<?php if ( '' === $sec_content ) { continue; } ?>
											<button type="button" class="hap-ai-tab-btn <?php echo $first_tab ? 'is-active' : ''; ?>" role="tab" data-ai-tab="<?php echo esc_attr( $sec_key ); ?>">
												<?php echo esc_html( ucwords( str_replace( '_', ' ', $sec_key ) ) ); ?>
											</button>
											<?php $first_tab = false; ?>
										<?php endforeach; ?>
									</div>
									<?php $first_panel = true; ?>
									<?php foreach ( $ai_report['sections'] as $sec_key => $sec_content ) : ?>
										<?php if ( '' === $sec_content ) { continue; } ?>
										<div class="hap-ai-tab-panel <?php echo $first_panel ? 'is-active' : ''; ?>" role="tabpanel" data-ai-panel="<?php echo esc_attr( $sec_key ); ?>">
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

					<p class="hap-disclaimer" style="font-size:.78rem;color:#888;margin-top:16px">Bu analiz yaln&#305;zca bilgilendirme ama&#231;l&#305;d&#305;r. T&#305;bbi, finansal veya hukuki tavsiye niteli&#287;i ta&#351;&#305;maz.</p>
						</div>
					<?php elseif ( 'processing' === $ai_display_status ) : ?>
						<div class="hap-ai-report-card hap-ai-report-card--processing">
							<div class="hap-ai-loader"></div>
					<p><strong>Analizin haz&#305;rlan&#305;yor...</strong></p>
					<p style="color:#888">Bu i&#351;lem birka&#231; dakika s&#252;rebilir. Sayfay&#305; yenileyerek g&#252;ncel durumu kontrol edebilirsin.</p>
					<button type="button" class="hap-btn hap-btn-secondary" onclick="location.reload()">Sayfay&#305; yenile</button>
						</div>
					<?php elseif ( 'consent_required' === $ai_display_status ) : ?>
						<div class="hap-ai-report-card hap-ai-report-card--consent">
					<p><strong>AI analizi i&#231;in a&#231;&#305;k r&#305;zan gerekiyor.</strong></p>
					<p style="color:#888">Yapay zeka destekli ki&#351;isel analiz raporunu g&#246;rmek i&#231;in AI i&#351;leme onay&#305;n&#305; vermelisin.</p>
							<a href="<?php echo esc_url( add_query_arg( 'step', 'account_consents', $current_page_url ) ); ?>" class="hap-btn hap-btn-primary">Onay ver</a>
						</div>
					<?php elseif ( 'pending_configuration' === $ai_display_status ) : ?>
						<div class="hap-ai-report-card hap-ai-report-card--pending">
					<p><strong>AI yorum katman&#305; yak&#305;nda aktif olacak.</strong></p>
					<p style="color:#888">Ki&#351;isel analiz raporu haz&#305;rland&#305;&#287;&#305;nda burada g&#246;r&#252;necek.</p>
						</div>
					<?php else : ?>
						<div class="hap-ai-report-card hap-ai-report-card--disabled">
					<p><strong>AI yorum katman&#305; yak&#305;nda aktif olacak.</strong></p>
						</div>
					<?php endif; ?>
				</section>

				<section class="hap-analysis-panel" data-analysis-panel="ai" id="hap-analysis-panel-ai">
					<div class="hap-section-heading" style="margin-bottom:1.5rem;">
						<div>
							<span class="hap-eyebrow">AI Kişisel Analiz</span>
							<h2 class="hap-section-title">DeepSeek ile Kapsamlı Profil Yorumu</h2>
							<p class="hap-section-copy">Profiline ve hazır olan tüm analiz sonuçlarına dayanarak, yapay zeka destekli detaylı kişisel analizini oluşturabilirsin.</p>
						</div>
					</div>

					<div class="hap-ai-report-container" style="background:#fff;border:1px solid var(--hap-border);border-radius:12px;padding:2rem;">
						<div class="hap-ai-report-content" id="hap-ai-report-content">
							<div style="text-align:center;padding:2rem 0;">
								<div style="font-size:3rem;margin-bottom:1rem;">🤖</div>
								<h3 style="margin-bottom:0.5rem;font-size:1.25rem;">Analizin Hazır Bekliyor</h3>
								<p style="color:var(--hap-text-light);margin-bottom:1.5rem;max-width:500px;margin-left:auto;margin-right:auto;">
									AI asistanımız, tüm verilerini harmanlayarak sana özel, kapsamlı bir okuma hazırlayacak. 
									<br><br>
									<strong style="color:var(--hap-danger);">Önemli Not:</strong> Bu rapor tıbbi teşhis, tedavi veya kesin bir kader öngörüsü değildir. Sadece bilgilendirme amaçlıdır.
								</p>
								
								<?php
								$has_consent = class_exists( 'HAP_Profile_Consents' ) ? HAP_Profile_Consents::has_ai_consent( $user_id ) : false;
								if ( ! $has_consent ) :
								?>
									<div class="hap-ai-consent-box" style="margin-bottom:1.5rem;text-align:left;background:#f9fafb;padding:1rem;border-radius:8px;border:1px solid #e5e7eb;max-width:500px;margin-left:auto;margin-right:auto;">
										<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:0.9rem;color:var(--hap-text);">
											<input type="checkbox" id="hap-ai-consent-checkbox" style="margin-top:3px;">
											<span>Verilerimin (profil bilgilerim ve analiz sonuçlarım) kişisel analiz raporu oluşturulması amacıyla yapay zeka servisine (DeepSeek) aktarılmasını ve işlenmesini açık rızam ile kabul ediyorum.</span>
										</label>
									</div>
									<button type="button" class="hap-btn hap-btn-primary" id="hap-generate-ai-btn" disabled style="opacity:0.6;cursor:not-allowed;">AI Analizimi Oluştur</button>
								<?php else : ?>
									<button type="button" class="hap-btn hap-btn-primary" id="hap-generate-ai-btn">AI Analizimi Oluştur</button>
								<?php endif; ?>
								
								<div class="hap-ai-loading" id="hap-ai-loading" style="display:none;margin-top:1rem;color:var(--hap-text-light);">
									Analizin hazırlanıyor, lütfen bekleyin... <span class="hap-spinner"></span>
								</div>
							</div>
						</div>
					</div>
				</section>

				<section class="hap-analysis-panel" data-analysis-panel="advanced" id="hap-analysis-panel-advanced">
					<div class="hap-section-heading">
						<div>
							<span class="hap-eyebrow">Geli&#351;mi&#351; Analizler</span>
							<h2 class="hap-section-title">Geli&#351;mi&#351; Analizler</h2>
							<p class="hap-section-copy">Bu analizler i&#231;in bilgiler haz&#305;r; geli&#351;mi&#351; analiz motoru haz&#305;rland&#305;&#287;&#305;nda burada g&#246;r&#252;necek.</p>
						</div>
						<span class="hap-status-pill hap-tool"><?php echo absint( count( $frontend_cards ) ); ?> analiz</span>
					</div>

					<?php if ( ! empty( $frontend_cards ) ) : ?>
						<div class="hap-results-grid hap-results-grid--pending">
							<?php foreach ( $frontend_cards as $runner_item ) : ?>
								<article class="hap-result-card hap-result-card--pending is-ready">
									<div class="hap-result-card-head">
										<h3><?php echo esc_html( $runner_item['display_title'] ); ?></h3>
						<span class="hap-status-pill hap-pending">Yak&#305;nda</span>
									</div>
					<p class="hap-result-card-copy">Geli&#351;mi&#351; analiz motoru haz&#305;rlan&#305;yor.</p>
								</article>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="hap-analysis-empty-state">
					<strong>&#350;u anda ek frontend-only analiz g&#246;r&#252;nm&#252;yor.</strong>
					<span>Yeni geli&#351;mi&#351; analizler haz&#305;r oldu&#287;unda burada listelenecek.</span>
						</div>
					<?php endif; ?>
				</section>

				<?php if ( $edit_mode ) : ?>
					<section class="hap-profile-form-section" id="hap-profile-form-section">
						<div class="hap-section-heading">
							<div>
								<span class="hap-eyebrow">Profil Bilgileri</span>
					<h2 class="hap-section-title">Bilgilerini d&#252;zenle</h2>
							</div>
						</div>
						<?php include HAP_PLUGIN_DIR . 'templates/form-basic.php'; ?>
					</section>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $settings['shareable_profile'] ) ) : ?>
		<div class="hap-share-panel" id="hap-share-panel" hidden>
			<div class="hap-share-overlay"></div>
			<div class="hap-share-modal" role="dialog" aria-modal="true" aria-labelledby="hap-share-title">
				<button class="hap-share-close" id="hap-close-share" type="button" aria-label="Payla&#351;&#305;m penceresini kapat">&times;</button>
				<span class="hap-eyebrow">Payla&#351;&#305;m Ayarlar&#305;</span>
				<h3 id="hap-share-title">Profilini payla&#351;</h3>
				<p class="hap-share-desc">G&#246;r&#252;n&#252;r b&#246;l&#252;mleri se&#231;. Hassas bilgiler her zaman gizli tutulur.</p>
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
				<div class="hap-share-note">Do&#287;um saati, do&#287;um yeri ve benzeri hassas alanlar payla&#351;&#305;mlara dahil edilmez.</div>
				<?php if ( $active_share ) : ?>
					<div class="hap-share-existing">
					<p>Mevcut payla&#351;&#305;m ba&#287;lant&#305;n:</p>
						<div class="hap-share-url-row">
							<input type="text" id="hap-share-url-existing" value="<?php echo esc_attr( $share->get_share_url( $active_share['share_token'] ) ); ?>" readonly>
							<button class="hap-btn hap-btn-secondary hap-copy-share" type="button" data-target="#hap-share-url-existing">Linki kopyala</button>
						</div>
					<button class="hap-btn hap-btn-danger" id="hap-revoke-share" type="button" data-id="<?php echo absint( $active_share['id'] ); ?>">Payla&#351;&#305;m&#305; iptal et</button>
					</div>
				<?php endif; ?>
				<button class="hap-btn hap-btn-primary hap-btn-full" id="hap-create-share" type="button">Yeni payla&#351;&#305;m ba&#287;lant&#305;s&#305; olu&#351;tur</button>
				<div id="hap-share-result" class="hap-share-result" hidden>
					<p>Ba&#287;lant&#305;n haz&#305;r:</p>
					<div class="hap-share-url-row">
						<input type="text" id="hap-share-url" readonly>
						<button class="hap-btn hap-btn-secondary hap-copy-share" type="button" data-target="#hap-share-url">Linki kopyala</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div><!-- /.hap-profile-app -->

