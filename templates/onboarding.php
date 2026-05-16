<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var int                    $user_id
 * @var HAP_Profile_Fields     $fields
 * @var HAP_Profile_User_Data  $user_data
 * @var HAP_Profile_Onboarding $onboarding
 * @var string                 $dashboard_url
 */

$profile            = $user_data->get_user_profile_data( $user_id );
$steps              = $onboarding->get_steps();
$step_order         = $onboarding->get_step_order();
$initial_step       = $onboarding->get_initial_step( $user_id );
$initial_index      = array_search( $initial_step, $step_order, true );
$current_index      = false !== $initial_index ? (int) $initial_index : 0;
$progress_percent   = (int) round( ( $current_index / max( count( $step_order ) - 1, 1 ) ) * 100 );
$minimum_completion = $user_data->get_minimum_profile_completion( $user_id, $profile );
$minimum_missing    = $user_data->get_minimum_profile_missing_fields( $user_id, $profile );
$sensitive_keys     = HAP_Profile_Fields::get_sensitive_keys();
?>
<div class="hap-profile-app">
	<div class="hap-dashboard hap-onboarding-shell" data-initial-step="<?php echo esc_attr( $initial_step ); ?>" data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>">
		<section class="hap-onboarding-hero">
			<div class="hap-onboarding-hero-copy">
				<span class="hap-eyebrow">Onboarding</span>
				<h1 class="hap-hero-title">Kisisel profilini olusturalim</h1>
				<p class="hap-hero-subtitle">Birkac temel bilgiyle sana ozel analiz panelini hazirlayacagiz. Minimum profil tamamlanmadan ana dashboard acilmaz.</p>
			</div>
			<div class="hap-onboarding-hero-stats">
				<div class="hap-onboarding-stat">
					<strong>Temel profil %<?php echo absint( $minimum_completion ); ?></strong>
					<span>Dashboard kilidini acan alanlar</span>
				</div>
				<div class="hap-onboarding-stat">
					<strong><?php echo empty( $minimum_missing ) ? 'Hazir' : esc_html( count( $minimum_missing ) . ' alan eksik' ); ?></strong>
					<span>Eksik alanlar tamamlandikca panel acilir</span>
				</div>
			</div>
		</section>

		<section class="hap-onboarding-card">
			<div class="hap-onboarding-progress">
				<div class="hap-progress-meta">
					<div>
						<strong data-onboarding-step-label>Adim <?php echo esc_html( $current_index + 1 ); ?> / <?php echo esc_html( count( $step_order ) ); ?></strong>
						<span>Her adimda neden bu bilgiyi istedigimizi goreceksin.</span>
					</div>
					<span class="hap-progress-chip" data-onboarding-progress-label>%<?php echo absint( $progress_percent ); ?></span>
				</div>
				<div class="hap-progress-bar" aria-hidden="true">
					<div class="hap-progress-fill" data-onboarding-progress-fill style="width: <?php echo absint( $progress_percent ); ?>%"></div>
				</div>
			</div>

			<div class="hap-onboarding-steps" role="tablist" aria-label="Onboarding adimlari">
				<?php foreach ( $step_order as $step_id ) : ?>
					<?php $step = $steps[ $step_id ]; ?>
					<button type="button" class="hap-onboarding-step-pill <?php echo $step_id === $initial_step ? 'is-active' : ''; ?>" data-step-target="<?php echo esc_attr( $step_id ); ?>" role="tab" aria-selected="<?php echo $step_id === $initial_step ? 'true' : 'false'; ?>">
						<span class="hap-onboarding-step-no"><?php echo esc_html( str_pad( (string) $step['number'], 2, '0', STR_PAD_LEFT ) ); ?></span>
						<span class="hap-onboarding-step-text"><?php echo esc_html( $step['title'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="hap-onboarding-panels">
				<?php foreach ( $step_order as $index => $step_id ) : ?>
					<?php
					$step        = $steps[ $step_id ];
					$step_fields = $onboarding->get_step_fields( $step_id );
					?>
					<section class="hap-onboarding-panel <?php echo $step_id === $initial_step ? 'is-active' : ''; ?>" data-step-panel="<?php echo esc_attr( $step_id ); ?>" data-step-index="<?php echo esc_attr( $index ); ?>">
						<div class="hap-onboarding-panel-head">
							<div>
								<span class="hap-eyebrow">Adim <?php echo esc_html( str_pad( (string) $step['number'], 2, '0', STR_PAD_LEFT ) ); ?></span>
								<h2 class="hap-section-title"><?php echo esc_html( $step['title'] ); ?></h2>
							</div>
							<p class="hap-section-copy"><?php echo esc_html( $step['subtitle'] ); ?></p>
						</div>

						<div class="hap-onboarding-why">
							<strong>Neden bu bilgi gerekiyor?</strong>
							<p><?php echo esc_html( $step['why'] ); ?></p>
						</div>

						<form class="hap-onboarding-form" data-step-form="<?php echo esc_attr( $step_id ); ?>">
							<div class="hap-form-grid hap-onboarding-grid">
								<?php foreach ( $step_fields as $field ) : ?>
									<?php
									$key        = $field['field_key'];
									$value      = $profile[ $key ] ?? '';
									$field_id   = 'hap-onboarding-' . $step_id . '-' . $key;
									$options    = HAP_Profile_Fields::get_field_options( $key );
									$is_private = in_array( $key, $sensitive_keys, true );
									?>
									<div class="hap-form-field">
										<label for="<?php echo esc_attr( $field_id ); ?>" class="hap-field-label">
											<span><?php echo esc_html( $field['label'] ); ?></span>
											<?php if ( $is_private ) : ?>
												<span class="hap-sensitive-tag">Kilitli</span>
											<?php endif; ?>
										</label>

										<?php if ( 'select' === $field['type'] ) : ?>
											<select id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" class="hap-select">
												<option value="">- Seciniz -</option>
												<?php foreach ( $options as $option_value => $option_label ) : ?>
													<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
												<?php endforeach; ?>
											</select>
										<?php elseif ( 'date' === $field['type'] ) : ?>
											<input type="date" id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
										<?php elseif ( 'time' === $field['type'] ) : ?>
											<input type="time" id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
										<?php elseif ( 'number' === $field['type'] ) : ?>
											<input type="number" id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" step="0.1">
										<?php elseif ( 'tel' === $field['type'] ) : ?>
											<input type="tel" id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input">
										<?php else : ?>
											<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="hap-input" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
										<?php endif; ?>

										<small class="hap-field-desc"><?php echo esc_html( $field['help_text'] ?: $step['description'] ); ?></small>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="hap-onboarding-feedback" aria-live="polite"></div>

							<div class="hap-onboarding-actions">
								<?php if ( $index > 0 ) : ?>
									<button type="button" class="hap-btn hap-btn-secondary" data-step-back="<?php echo esc_attr( $step_id ); ?>">Geri</button>
								<?php endif; ?>
								<?php if ( ! empty( $step['optional'] ) ) : ?>
									<button type="button" class="hap-btn hap-btn-tertiary" data-step-skip="<?php echo esc_attr( $step_id ); ?>">Bu adimi atla</button>
								<?php endif; ?>
								<button type="button" class="hap-btn hap-btn-primary" data-step-save="<?php echo esc_attr( $step_id ); ?>"><?php echo esc_html( $step['cta'] ); ?></button>
							</div>
						</form>
					</section>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
</div>
