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

$existing_consents = array();
if ( class_exists( 'HAP_Profile_Consents' ) ) {
	$existing_consents = HAP_Profile_Consents::get_user_consents( $user_id );
}
?>
<div class="hap-profile-app">
	<div class="hap-dashboard hap-onboarding-shell" data-initial-step="<?php echo esc_attr( $initial_step ); ?>" data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>">
		<section class="hap-onboarding-hero">
			<div class="hap-onboarding-hero-copy">
				<span class="hap-eyebrow">Profil Kurulumu</span>
				<h1 class="hap-hero-title">Kişisel profilini oluşturalım</h1>
				<p class="hap-hero-subtitle">Birkaç temel bilgiyle sana özel analiz panelini hazırlayacağız.</p>
			</div>
			<div class="hap-onboarding-hero-stats">
				<div class="hap-onboarding-stat">
					<strong>%<?php echo absint( $minimum_completion ); ?></strong>
					<span>Temel profil</span>
				</div>
				<div class="hap-onboarding-stat">
					<strong><?php echo empty( $minimum_missing ) ? 'Hazır' : esc_html( count( $minimum_missing ) . ' alan eksik' ); ?></strong>
					<span>Dashboard kilidi</span>
				</div>
			</div>
		</section>

		<section class="hap-onboarding-card">
			<div class="hap-onboarding-progress">
				<div class="hap-progress-meta">
					<div>
						<strong data-onboarding-step-label>Adım <?php echo esc_html( $current_index + 1 ); ?> / <?php echo esc_html( count( $step_order ) ); ?></strong>
					</div>
					<span class="hap-progress-chip" data-onboarding-progress-label>%<?php echo absint( $progress_percent ); ?></span>
				</div>
				<div class="hap-progress-bar" aria-hidden="true">
					<div class="hap-progress-fill" data-onboarding-progress-fill style="width: <?php echo absint( $progress_percent ); ?>%"></div>
				</div>
			</div>

			<div class="hap-onboarding-steps" role="tablist" aria-label="Onboarding adımları">
				<?php foreach ( $step_order as $step_id ) : ?>
					<?php $step = $steps[ $step_id ]; ?>
					<button type="button"
					        class="hap-onboarding-step-pill <?php echo $step_id === $initial_step ? 'is-active' : ''; ?>"
					        data-step-target="<?php echo esc_attr( $step_id ); ?>"
					        role="tab"
					        aria-selected="<?php echo $step_id === $initial_step ? 'true' : 'false'; ?>">
						<span class="hap-onboarding-step-no"><?php echo esc_html( str_pad( (string) $step['number'], 2, '0', STR_PAD_LEFT ) ); ?></span>
						<span class="hap-onboarding-step-text"><?php echo esc_html( $step['title'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="hap-onboarding-panels">
				<?php foreach ( $step_order as $index => $step_id ) :
					$step        = $steps[ $step_id ];
					$is_consent  = 'account_consents' === $step_id;
					$is_generate = 'review_generate' === $step_id;
					$step_fields = $is_consent || $is_generate ? array() : $onboarding->get_step_fields( $step_id );
					$benefit     = ! $is_consent && ! $is_generate ? $onboarding->get_step_benefit( $step_id ) : array();
				?>
					<section
						class="hap-onboarding-panel <?php echo $step_id === $initial_step ? 'is-active' : ''; ?>"
						data-step-panel="<?php echo esc_attr( $step_id ); ?>"
						data-step-index="<?php echo esc_attr( $index ); ?>">

						<div class="hap-onboarding-panel-head">
							<div>
								<span class="hap-eyebrow">Adım <?php echo esc_html( str_pad( (string) $step['number'], 2, '0', STR_PAD_LEFT ) ); ?></span>
								<h2 class="hap-section-title"><?php echo esc_html( $step['title'] ); ?></h2>
							</div>
							<p class="hap-section-copy"><?php echo esc_html( $step['subtitle'] ); ?></p>
						</div>

						<?php if ( ! empty( $benefit['benefit_text'] ) ) : ?>
						<div class="hap-step-benefit">
							<span class="hap-step-benefit-icon">✨</span>
							<span><?php echo esc_html( $benefit['benefit_text'] ); ?></span>
						</div>
						<?php endif; ?>

						<?php if ( $is_consent ) : ?>
						<!-- CONSENT ADIMI -->
						<form class="hap-onboarding-form hap-consent-form" data-step-form="account_consents">
							<div class="hap-consent-list">
								<?php
								$consent_items = array(
									'kvkk_aydinlatma' => array(
										'label'    => 'KVKK Aydınlatma Metni\'ni okudum ve kabul ediyorum.',
										'required' => true,
									),
									'privacy_policy' => array(
										'label'    => 'Gizlilik Politikası\'nı okudum ve kabul ediyorum.',
										'required' => true,
									),
									'terms_of_use' => array(
										'label'    => 'Kullanım Şartları\'nı okudum ve kabul ediyorum.',
										'required' => true,
									),
									'explicit_ai_processing' => array(
										'label'    => 'Profil verilerimin kişisel analiz raporu oluşturmak amacıyla yapay zeka tarafından işlenmesine izin veriyorum. (İsteğe bağlı)',
										'required' => false,
									),
								);
								foreach ( $consent_items as $type => $info ) :
									$checked = ! empty( $existing_consents[ $type ]['accepted'] );
								?>
								<label class="hap-consent-item <?php echo $info['required'] ? 'hap-consent-required' : ''; ?>">
									<input type="checkbox"
									       name="consents[<?php echo esc_attr( $type ); ?>]"
									       value="1"
									       <?php checked( $checked ); ?>
									       <?php echo $info['required'] ? 'required' : ''; ?>>
									<span><?php echo esc_html( $info['label'] ); ?></span>
									<?php if ( $info['required'] ) : ?>
										<span class="hap-required-star" aria-hidden="true">*</span>
									<?php endif; ?>
								</label>
								<?php endforeach; ?>
							</div>

							<div class="hap-onboarding-feedback" aria-live="polite"></div>

							<div class="hap-onboarding-actions">
								<button type="button" class="hap-btn hap-btn-primary" data-consent-save>
									Onaylayıp Devam Et
								</button>
							</div>
						</form>

						<?php elseif ( $is_generate ) : ?>
						<!-- FINAL STEP: Beni sonuçlarıma götür -->
						<div class="hap-generate-step">
							<div class="hap-generate-summary">
								<p>Profil bilgilerin hazır. Analiz sonuçlarını oluşturmak için aşağıdaki butona bas.</p>
								<ul class="hap-generate-checklist">
									<li>✓ Temel profil bilgilerin kayıt altında</li>
									<li>✓ Analiz modülleri hazır</li>
									<li>✓ Sonuçlar Dashboard'ında görünecek</li>
								</ul>
							</div>
							<div class="hap-onboarding-feedback hap-generate-feedback" aria-live="polite"></div>
							<div class="hap-onboarding-actions">
								<?php if ( $index > 0 ) : ?>
									<button type="button" class="hap-btn hap-btn-secondary" data-step-back="<?php echo esc_attr( $step_id ); ?>">Geri</button>
								<?php endif; ?>
								<button type="button" class="hap-btn hap-btn-primary hap-btn-generate" data-generate-results>
									Beni Sonuçlarıma Götür
								</button>
							</div>
						</div>

						<?php else : ?>
						<!-- NORMAL PROFIL ADIMI -->
						<form class="hap-onboarding-form" data-step-form="<?php echo esc_attr( $step_id ); ?>">
							<div class="hap-form-grid hap-onboarding-grid">
								<?php foreach ( $step_fields as $field ) :
									$key       = $field['field_key'];
									$value     = $profile[ $key ] ?? '';
									$field_id  = 'hap-onboarding-' . $step_id . '-' . $key;
									$options   = HAP_Profile_Fields::get_field_options( $key );
									$is_priv   = in_array( $key, $sensitive_keys, true );
								?>
									<div class="hap-form-field">
										<label for="<?php echo esc_attr( $field_id ); ?>" class="hap-field-label">
											<span><?php echo esc_html( $field['label'] ); ?></span>
											<?php if ( $is_priv ) : ?>
												<span class="hap-sensitive-tag">Kilitli</span>
											<?php endif; ?>
										</label>

										<?php if ( 'select' === $field['type'] ) : ?>
											<select id="<?php echo esc_attr( $field_id ); ?>" name="profile_data[<?php echo esc_attr( $key ); ?>]" class="hap-select">
												<option value="">— Seçiniz —</option>
												<?php foreach ( $options as $ov => $ol ) : ?>
													<option value="<?php echo esc_attr( $ov ); ?>" <?php selected( (string) $value, (string) $ov ); ?>><?php echo esc_html( $ol ); ?></option>
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

										<?php if ( ! empty( $field['help_text'] ) ) : ?>
											<small class="hap-field-desc"><?php echo esc_html( $field['help_text'] ); ?></small>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="hap-onboarding-feedback" aria-live="polite"></div>

							<div class="hap-onboarding-actions">
								<?php if ( $index > 0 ) : ?>
									<button type="button" class="hap-btn hap-btn-secondary" data-step-back="<?php echo esc_attr( $step_id ); ?>">Geri</button>
								<?php endif; ?>
								<?php if ( ! empty( $step['skippable'] ) ) : ?>
									<button type="button" class="hap-btn hap-btn-tertiary" data-step-skip="<?php echo esc_attr( $step_id ); ?>">Bu adımı atla</button>
								<?php endif; ?>
								<button type="button" class="hap-btn hap-btn-primary" data-step-save="<?php echo esc_attr( $step_id ); ?>">
									<?php echo esc_html( $step['cta'] ); ?>
								</button>
							</div>
						</form>
						<?php endif; ?>

					</section>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
</div>

<script>
jQuery(function($){
	var nonce = typeof hapProfile !== 'undefined' ? hapProfile.nonce : '';
	var ajaxUrl = typeof hapProfile !== 'undefined' ? hapProfile.ajaxUrl : '/wp-admin/admin-ajax.php';

	// Consent kaydet
	$(document).on('click', '[data-consent-save]', function(){
		var $btn     = $(this);
		var $form    = $btn.closest('.hap-consent-form');
		var $feedback = $form.find('.hap-onboarding-feedback');
		var consents = {};

		$form.find('input[type=checkbox]').each(function(){
			var name = $(this).attr('name').replace('consents[','').replace(']','');
			consents[name] = $(this).is(':checked') ? 1 : 0;
		});

		// Zorunlu kontrol client-side
		var required = ['kvkk_aydinlatma','privacy_policy','terms_of_use'];
		var missing = required.filter(function(t){ return !consents[t]; });
		if (missing.length) {
			$feedback.html('<div class="hap-error">Devam etmek için KVKK, Gizlilik ve Kullanım Şartları onaylarını vermelisin.</div>');
			return;
		}

		$btn.prop('disabled', true).text('Kaydediliyor...');
		$feedback.html('');

		$.post(ajaxUrl, {
			action: 'hap_save_consents',
			nonce: nonce,
			consents: consents
		}, function(res){
			$btn.prop('disabled', false).text('Onaylayıp Devam Et');
			if (res.success) {
				var nextStep = res.data.next_step;
				$('.hap-onboarding-panel.is-active').removeClass('is-active');
				$('.hap-onboarding-step-pill.is-active').removeClass('is-active').attr('aria-selected','false');
				$('[data-step-panel="' + nextStep + '"]').addClass('is-active');
				$('[data-step-target="' + nextStep + '"]').addClass('is-active').attr('aria-selected','true');
				hapUpdateProgress(nextStep);
			} else {
				$feedback.html('<div class="hap-error">' + (res.data && res.data.message ? res.data.message : 'Hata oluştu.') + '</div>');
			}
		}).fail(function(){
			$btn.prop('disabled', false).text('Onaylayıp Devam Et');
			$feedback.html('<div class="hap-error">Bağlantı hatası.</div>');
		});
	});

	// Generate results — "Beni sonuçlarıma götür"
	$(document).on('click', '[data-generate-results]', function(){
		var $btn      = $(this);
		var $feedback = $btn.closest('.hap-generate-step').find('.hap-generate-feedback');
		$btn.prop('disabled', true).text('Sonuçlar hazırlanıyor...');
		$feedback.html('');

		$.post(ajaxUrl, {
			action: 'hap_generate_profile_results',
			nonce: nonce
		}, function(res){
			if (res.success) {
				$feedback.html('<div class="hap-success">Sonuçların hazır! Yönlendiriliyorsun...</div>');
				setTimeout(function(){
					window.location.href = res.data.redirect_url || window.location.href;
				}, 1000);
			} else {
				$btn.prop('disabled', false).text('Beni Sonuçlarıma Götür');
				var code = res.data && res.data.code ? res.data.code : '';
				var msg  = res.data && res.data.message ? res.data.message : 'Hata oluştu.';
				if (code === 'consent_required') {
					msg = 'Devam etmek için onay adımını tamamlamalısın.';
				} else if (code === 'incomplete_profile') {
					msg = 'Minimum profil alanlarını doldurman gerekiyor.';
				}
				$feedback.html('<div class="hap-error">' + msg + '</div>');
			}
		}).fail(function(){
			$btn.prop('disabled', false).text('Beni Sonuçlarıma Götür');
			$feedback.html('<div class="hap-error">Bağlantı hatası.</div>');
		});
	});

	function hapUpdateProgress(stepId) {
		var $pills  = $('.hap-onboarding-step-pill');
		var total   = $pills.length;
		var current = $pills.index($('[data-step-target="' + stepId + '"]')) + 1;
		var pct     = total > 1 ? Math.round((current / (total - 1)) * 100) : 100;
		$('[data-onboarding-progress-fill]').css('width', pct + '%');
		$('[data-onboarding-progress-label]').text('%' + pct);
		$('[data-onboarding-step-label]').text('Adım ' + current + ' / ' + total);
	}
});
</script>
