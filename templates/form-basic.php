<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var int                   $user_id
 * @var HAP_Profile_Fields    $fields
 * @var HAP_Profile_User_Data $user_data
 */

$all_fields  = $fields->get_active_fields();
$step_groups = array();
foreach ( $all_fields as $field ) {
	$step_groups[ $field['step'] ][] = $field;
}
ksort( $step_groups );

$step_labels = array(
	1 => 'Temel Bilgiler',
	2 => 'Saglik & Yasam',
	3 => 'Ek Bilgiler',
);

$step_descriptions = array(
	1 => 'Kimlik, dogum ve temel kisisel bilgileri duzenle.',
	2 => 'Saglik, yasam duzeni ve aktivite verilerini ekle.',
	3 => 'Ek bilgilerle daha fazla analizin acilmasini sagla.',
);

$gender_opts = array(
	''           => '- Seciniz -',
	'male'       => 'Erkek',
	'female'     => 'Kadin',
	'other'      => 'Diger',
	'prefer_not' => 'Belirtmek Istemiyorum',
);
$activity_opts = array(
	''            => '- Seciniz -',
	'sedentary'   => 'Hareketsiz (ofis isi)',
	'light'       => 'Hafif Aktif (haftada 1-3 gun)',
	'moderate'    => 'Orta Aktif (haftada 3-5 gun)',
	'active'      => 'Aktif (haftada 5-6 gun)',
	'very_active' => 'Cok Aktif (haftada 6-7 gun / agir is)',
);
$relationship_opts = array(
	''             => '- Seciniz -',
	'single'       => 'Bekar',
	'relationship' => 'Iliskide',
	'married'      => 'Evli',
	'complicated'  => 'Karmasik',
	'prefer_not'   => 'Belirtmek Istemiyorum',
);

$saved_ok  = isset( $_GET['hap_saved'] ) && '1' === $_GET['hap_saved'];
$error_key = isset( $_GET['hap_error'] ) ? sanitize_key( $_GET['hap_error'] ) : '';
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
			<?php foreach ( $step_groups as $step => $step_fields ) : ?>
				<button type="button" class="hap-form-tab <?php echo 1 === (int) $step ? 'active' : ''; ?>" data-step="<?php echo absint( $step ); ?>" role="tab" aria-selected="<?php echo 1 === (int) $step ? 'true' : 'false'; ?>">
					<span class="hap-form-tab-step">0<?php echo absint( $step ); ?></span>
					<span class="hap-form-tab-copy">
						<strong><?php echo esc_html( $step_labels[ $step ] ?? 'Adim ' . $step ); ?></strong>
						<small><?php echo esc_html( $step_descriptions[ $step ] ?? '' ); ?></small>
					</span>
				</button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $step_groups as $step => $step_fields ) : ?>
			<section class="hap-form-step <?php echo 1 === (int) $step ? 'active' : ''; ?>" data-step="<?php echo absint( $step ); ?>">
				<div class="hap-form-step-head">
					<h4><?php echo esc_html( $step_labels[ $step ] ?? 'Adim ' . $step ); ?></h4>
					<p><?php echo esc_html( $step_descriptions[ $step ] ?? '' ); ?></p>
				</div>

				<div class="hap-form-grid">
					<?php foreach ( $step_fields as $field ) : ?>
						<?php
						$key   = $field['key'];
						$value = $user_data->get_field_value( $user_id, $key );
						$id    = 'hap-field-' . esc_attr( $key );
						?>
						<div class="hap-form-field <?php echo ! empty( $field['required'] ) ? 'hap-required' : ''; ?>">
							<label for="<?php echo esc_attr( $id ); ?>" class="hap-field-label">
								<span><?php echo esc_html( $field['label'] ); ?><?php if ( ! empty( $field['required'] ) ) : ?> <span class="hap-req-star">*</span><?php endif; ?></span>
								<?php if ( ! empty( $field['sensitive'] ) ) : ?>
									<span class="hap-sensitive-tag">Kilitli</span>
								<?php endif; ?>
							</label>

							<?php if ( 'select' === $field['type'] ) : ?>
								<?php
								if ( 'gender' === $key ) {
									$options = $gender_opts;
								} elseif ( 'activity_level' === $key ) {
									$options = $activity_opts;
								} elseif ( 'relationship_status' === $key ) {
									$options = $relationship_opts;
								} else {
									$options = array( '' => '- Seciniz -' );
								}
								?>
								<select id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" class="hap-select">
									<?php foreach ( $options as $option_value => $option_label ) : ?>
										<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'date' === $field['type'] ) : ?>
								<input type="date" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
							<?php elseif ( 'time' === $field['type'] ) : ?>
								<input type="time" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
							<?php elseif ( 'number' === $field['type'] ) : ?>
								<input type="number" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" step="0.1">
							<?php elseif ( 'tel' === $field['type'] ) : ?>
								<input type="tel" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
							<?php else : ?>
								<input type="text" id="<?php echo esc_attr( $id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
							<?php endif; ?>

							<?php if ( ! empty( $field['description'] ) ) : ?>
								<small class="hap-field-desc"><?php echo esc_html( $field['description'] ); ?></small>
							<?php else : ?>
								<small class="hap-field-desc">Bu alan analizlerinin daha dogru olmasina yardimci olur.</small>
							<?php endif; ?>
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
