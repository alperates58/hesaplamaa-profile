<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var int                   $user_id
 * @var HAP_Profile_Fields    $fields
 * @var HAP_Profile_User_Data $user_data
 */

$steps      = HAP_Profile_Fields::get_active_steps();
$saved_ok   = isset( $_GET['hap_saved'] ) && '1' === $_GET['hap_saved'];
$error_key  = isset( $_GET['hap_error'] ) ? sanitize_key( $_GET['hap_error'] ) : '';
?>
<div id="hap-profile-form" class="hap-profile-form-card">
	<div class="hap-profile-form-head">
		<div>
			<span class="hap-eyebrow">Guvenli Profil</span>
			<h3 class="hap-profile-form-title">Profil Bilgilerim</h3>
			<p class="hap-profile-form-copy">Bu bilgiler sadece kisisel analizlerini olusturmak icin kullanilir.</p>
		</div>
		<div class="hap-profile-form-side-note">Kayitlarin sadece hesabina ozeldir.</div>
	</div>

	<?php if ( $saved_ok ) : ?>
		<div class="hap-notice hap-notice-success">Profilin basariyla kaydedildi.</div>
	<?php elseif ( 'nonce' === $error_key ) : ?>
		<div class="hap-notice hap-notice-error">Guvenlik dogrulamasi basarisiz oldu. Lutfen tekrar dene.</div>
	<?php endif; ?>

	<form id="hap-profile-form-el" class="hap-profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'hap_save_profile_form', 'hap_pf_nonce' ); ?>
		<input type="hidden" name="action" value="hap_save_profile">

		<div class="hap-form-tabs" role="tablist" aria-label="Profil adimlari">
			<?php foreach ( $steps as $index => $step ) : ?>
				<button type="button" class="hap-form-tab <?php echo 0 === $index ? 'active' : ''; ?>" data-step="<?php echo esc_attr( $step['step_key'] ); ?>" role="tab" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>">
					<span class="hap-form-tab-step"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<span class="hap-form-tab-copy">
						<strong><?php echo esc_html( $step['title'] ); ?></strong>
						<small><?php echo esc_html( $step['description'] ); ?></small>
					</span>
				</button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $steps as $index => $step ) : ?>
			<?php $step_fields = HAP_Profile_Fields::get_fields_by_step( $step['step_key'] ); ?>
			<?php if ( empty( $step_fields ) ) { continue; } ?>
			<section class="hap-form-step <?php echo 0 === $index ? 'active' : ''; ?>" data-step="<?php echo esc_attr( $step['step_key'] ); ?>">
				<div class="hap-form-step-head">
					<h4><?php echo esc_html( $step['title'] ); ?></h4>
					<p><?php echo esc_html( $step['description'] ); ?></p>
				</div>

				<div class="hap-form-grid">
					<?php foreach ( $step_fields as $field ) : ?>
						<?php
						$key   = $field['field_key'];
						$value = $user_data->get_field_value( $user_id, $key );
						$id    = 'hap-field-' . esc_attr( $key );
						?>
						<div class="hap-form-field <?php echo ! empty( $field['required_for_minimum_profile'] ) ? 'hap-required' : ''; ?>">
							<label for="<?php echo esc_attr( $id ); ?>" class="hap-field-label">
								<span><?php echo esc_html( $field['label'] ); ?><?php if ( ! empty( $field['required_for_minimum_profile'] ) ) : ?> <span class="hap-req-star">*</span><?php endif; ?></span>
								<?php if ( ! empty( $field['sensitive'] ) ) : ?>
									<span class="hap-sensitive-tag">Kilitli</span>
								<?php endif; ?>
							</label>

							<?php if ( 'select' === $field['type'] ) : ?>
								<select id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" class="hap-select">
									<option value="">- Seciniz -</option>
									<?php foreach ( HAP_Profile_Fields::get_field_options( $key ) as $option_value => $option_label ) : ?>
										<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'date' === $field['type'] ) : ?>
								<input type="date" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
							<?php elseif ( 'time' === $field['type'] ) : ?>
								<input type="time" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
							<?php elseif ( 'number' === $field['type'] ) : ?>
								<input type="number" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" step="0.1" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
							<?php elseif ( 'tel' === $field['type'] ) : ?>
								<input type="tel" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
							<?php else : ?>
								<input type="text" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
							<?php endif; ?>

							<small class="hap-field-desc"><?php echo esc_html( $field['help_text'] ?: 'Bu alan analizlerinin daha dogru olmasina yardimci olur.' ); ?></small>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>

		<div class="hap-form-footer">
			<button type="submit" class="hap-btn hap-btn-primary hap-btn-lg" id="hap-save-profile">Profili Kaydet</button>
			<span class="hap-save-msg" id="hap-save-msg" aria-live="polite"></span>
		</div>
	</form>
</div>
