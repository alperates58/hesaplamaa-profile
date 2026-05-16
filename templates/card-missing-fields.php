<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array                $missing_by_module  [{module, missing[]}]
 * @var HAP_Profile_Fields   $fields
 */
?>
<div class="hap-card hap-missing-card">
	<div class="hap-card-header">
		<span class="hap-card-icon">⚠️</span>
		<h3>Eksik Bilgiler</h3>
		<span class="hap-badge hap-badge-warning"><?php echo count( $missing_by_module ); ?> analiz bekliyor</span>
	</div>
	<div class="hap-card-body">
		<p class="hap-missing-intro">Aşağıdaki analizleri tamamlamak için eksik bilgileri girin:</p>
		<div class="hap-missing-list">
			<?php foreach ( array_slice( $missing_by_module, 0, 10 ) as $item ) :
				$mod    = $item['module'];
				$missed = $item['missing'];
			?>
			<div class="hap-missing-item">
				<div class="hap-missing-item-title"><?php echo esc_html( $mod['title'] ); ?></div>
				<div class="hap-missing-item-fields">
					<?php foreach ( $missed as $mk ) : ?>
					<span class="hap-tag hap-tag-missing"><?php echo esc_html( $fields->get_label( $mk ) ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<a href="#hap-profile-form" class="hap-btn hap-btn-primary hap-scroll-link">
			✏️ Bilgileri Tamamla
		</a>
	</div>
</div>
