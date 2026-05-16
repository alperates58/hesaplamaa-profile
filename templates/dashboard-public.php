<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array                $share_data
 * @var array                $user_data  (sensitive fields already filtered)
 * @var HAP_Profile_Fields   $fields
 * @var HAP_Profile_Modules  $modules
 * @var array                $visible_sections
 */

$sections_config = array(
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

$display_name = $share_data['share_title'] ?: 'Profil';
$visible      = ! empty( $visible_sections ) ? $visible_sections : array_keys( $sections_config );
?>
<div class="hap-dashboard hap-public-dashboard">

	<div class="hap-public-banner">
		<span class="hap-public-icon">🌐</span>
		<span>Bu paylaşılan bir profil sayfasıdır &mdash; kişisel hassas veriler gösterilmemektedir.</span>
	</div>

	<div class="hap-hero hap-hero-public">
		<div class="hap-hero-info">
			<h1 class="hap-hero-name"><?php echo esc_html( $display_name ); ?></h1>
			<p class="hap-public-subtitle">Kişisel Analiz Profili &mdash; hesaplamaa.com</p>
			<div class="hap-public-stats">
				<span>👁️ <?php echo absint( $share_data['view_count'] ); ?> görüntülenme</span>
			</div>
		</div>
	</div>

	<div class="hap-sections-grid">
		<?php foreach ( $sections_config as $sec_key => $sec ) :
			if ( ! in_array( $sec_key, $visible, true ) ) continue;

			$section_modules = $modules->get_modules( array(
				'section'        => $sec_key,
				'profile_status' => 'profile_core',
				'limit'          => 10,
			) );
			if ( empty( $section_modules ) ) continue;
		?>
		<div class="hap-section-card">
			<div class="hap-section-card-header">
				<span class="hap-section-card-icon"><?php echo esc_html( $sec['icon'] ); ?></span>
				<h3 class="hap-section-card-title"><?php echo esc_html( $sec['label'] ); ?></h3>
			</div>
			<div class="hap-section-card-body">
				<ul class="hap-module-list">
					<?php foreach ( $section_modules as $mod ) : ?>
					<li class="hap-module-item">
						<span class="hap-module-dot">✨</span>
						<span class="hap-module-name"><?php echo esc_html( $mod['title'] ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="hap-public-footer">
		<p>Bu profil <a href="<?php echo esc_url( home_url( '/' ) ); ?>">hesaplamaa.com</a> tarafından oluşturulmuştur.</p>
		<p class="hap-disclaimer">Astroloji, numeroloji ve sembolik analizler eğlence ve kişisel farkındalık amaçlıdır.</p>
	</div>
</div>
