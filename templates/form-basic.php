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
foreach ( $all_fields as $f ) {
	$step_groups[ $f['step'] ][] = $f;
}
ksort( $step_groups );

$step_labels = array(
	1 => 'Temel Bilgiler',
	2 => 'Sağlık & Astroloji',
	3 => 'Ek Bilgiler',
);

$gender_opts = array(
	''           => '— Seçiniz —',
	'male'       => 'Erkek',
	'female'     => 'Kadın',
	'other'      => 'Diğer',
	'prefer_not' => 'Belirtmek İstemiyorum',
);
$activity_opts = array(
	''           => '— Seçiniz —',
	'sedentary'  => 'Hareketsiz (ofis işi)',
	'light'      => 'Hafif Aktif (haftada 1-3 gün)',
	'moderate'   => 'Orta Aktif (haftada 3-5 gün)',
	'active'     => 'Aktif (haftada 5-6 gün)',
	'very_active'=> 'Çok Aktif (haftada 6-7 gün / ağır iş)',
);
$relationship_opts = array(
	''              => '— Seçiniz —',
	'single'        => 'Bekar',
	'relationship'  => 'İlişkide',
	'married'       => 'Evli',
	'complicated'   => 'Karmaşık',
	'prefer_not'    => 'Belirtmek İstemiyorum',
);

// URL param feedback (PHP POST fallback)
$saved_ok  = isset( $_GET['hap_saved'] ) && $_GET['hap_saved'] === '1';
$error_key = isset( $_GET['hap_error'] ) ? sanitize_key( $_GET['hap_error'] ) : '';
?>
<div class="hap-profile-form-wrap">

	<?php if ( $saved_ok ) : ?>
	<div class="hap-notice hap-notice-success">✅ Profiliniz başarıyla kaydedildi.</div>
	<?php elseif ( $error_key === 'nonce' ) : ?>
	<div class="hap-notice hap-notice-error">❌ Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.</div>
	<?php endif; ?>

	<form id="hap-profile-form-el" class="hap-profile-form"
	      method="post"
	      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

		<?php wp_nonce_field( 'hap_save_profile_form', 'hap_pf_nonce' ); ?>
		<input type="hidden" name="action" value="hap_save_profile">

		<div class="hap-form-tabs">
			<?php foreach ( $step_groups as $step => $step_fields ) : ?>
			<button type="button" class="hap-form-tab <?php echo $step === 1 ? 'active' : ''; ?>"
			        data-step="<?php echo absint( $step ); ?>">
				<?php echo esc_html( $step_labels[ $step ] ?? 'Adım ' . $step ); ?>
			</button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $step_groups as $step => $step_fields ) : ?>
		<div class="hap-form-step <?php echo $step === 1 ? 'active' : ''; ?>" data-step="<?php echo absint( $step ); ?>">
			<div class="hap-form-grid">
			<?php foreach ( $step_fields as $field ) :
				$key   = $field['key'];
				$value = $user_data->get_field_value( $user_id, $key );
				$id    = 'hap-field-' . esc_attr( $key );
			?>
			<div class="hap-form-field <?php echo ! empty( $field['required'] ) ? 'hap-required' : ''; ?>">
				<label for="<?php echo $id; ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( ! empty( $field['required'] ) ) : ?><span class="hap-req-star">*</span><?php endif; ?>
					<?php if ( ! empty( $field['sensitive'] ) ) : ?><span class="hap-sensitive-tag" title="Hassas veri — paylaşımlarda gizlenir">🔒</span><?php endif; ?>
				</label>
				<?php if ( $field['type'] === 'select' ) :
					if ( $key === 'gender' ) $opts = $gender_opts;
					elseif ( $key === 'activity_level' ) $opts = $activity_opts;
					elseif ( $key === 'relationship_status' ) $opts = $relationship_opts;
					else $opts = array( '' => '— Seçiniz —' );
				?>
				<select id="<?php echo $id; ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" class="hap-select">
					<?php foreach ( $opts as $ov => $ol ) : ?>
					<option value="<?php echo esc_attr( $ov ); ?>" <?php selected( (string) $value, (string) $ov ); ?>><?php echo esc_html( $ol ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php elseif ( $field['type'] === 'date' ) : ?>
				<input type="date" id="<?php echo $id; ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]"
				       value="<?php echo esc_attr( $value ); ?>" class="hap-input">
				<?php elseif ( $field['type'] === 'time' ) : ?>
				<input type="time" id="<?php echo $id; ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]"
				       value="<?php echo esc_attr( $value ); ?>" class="hap-input">
				<?php elseif ( $field['type'] === 'number' ) : ?>
				<input type="number" id="<?php echo $id; ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]"
				       value="<?php echo esc_attr( $value ); ?>" class="hap-input" step="0.1">
				<?php elseif ( $field['type'] === 'tel' ) : ?>
				<input type="tel" id="<?php echo $id; ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]"
				       value="<?php echo esc_attr( $value ); ?>" class="hap-input">
				<?php else : ?>
				<input type="text" id="<?php echo $id; ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]"
				       value="<?php echo esc_attr( $value ); ?>" class="hap-input">
				<?php endif; ?>
				<?php if ( ! empty( $field['description'] ) ) : ?>
				<small class="hap-field-desc"><?php echo esc_html( $field['description'] ); ?></small>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>

		<div class="hap-form-footer">
			<button type="submit" class="hap-btn hap-btn-primary" id="hap-save-profile">
				💾 Profili Kaydet
			</button>
			<span class="hap-save-msg" id="hap-save-msg"></span>
		</div>
	</form>
</div>
