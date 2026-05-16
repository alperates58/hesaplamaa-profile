<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array              $missing_by_module  [{module, missing[]}]
 * @var array              $missing_fields
 * @var HAP_Profile_Fields $fields
 */
?>
<section class="hap-missing-card">
	<div class="hap-missing-card-head">
		<div>
			<span class="hap-eyebrow">Eksik Bilgiler</span>
			<h3>Profilini tamamla</h3>
			<p>Daha fazla analiz acmak icin birkac bilgi eksik.</p>
		</div>
		<span class="hap-missing-summary"><?php echo absint( count( $missing_by_module ) ); ?> analiz bekliyor</span>
	</div>

	<?php if ( ! empty( $missing_fields ) ) : ?>
		<div class="hap-missing-chips">
			<?php foreach ( $missing_fields as $missing_label ) : ?>
				<span class="hap-chip"><?php echo esc_html( $missing_label ); ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $missing_by_module ) ) : ?>
		<div class="hap-missing-module-list">
			<?php foreach ( array_slice( $missing_by_module, 0, 3 ) as $item ) : ?>
				<?php $module_title = ! empty( $item['module']['title'] ) ? $item['module']['title'] : hap_profile_humanize_slug( $item['module']['slug'] ); ?>
				<div class="hap-missing-module-item">
					<strong><?php echo esc_html( $module_title ); ?></strong>
					<span><?php echo esc_html( implode( ', ', array_map( array( $fields, 'get_label' ), $item['missing'] ) ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<a href="#hap-profile-form" class="hap-btn hap-btn-primary hap-scroll-link">Eksik Bilgileri Tamamla</a>
</section>
