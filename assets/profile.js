/* Hesaplamaa Profile - Frontend JS */
(function ($) {
	'use strict';

	const HAP = {
		init: function () {
			this.initFormTabs();
			this.initProfileSave();
			this.initOnboarding();
			this.initShare();
			this.initScrollLinks();
			this.initCopyButtons();
			this.initResultFilters();
		},

		initFormTabs: function () {
			$(document).on('click', '.hap-form-tab', function () {
				const step = $(this).data('step');
				$('.hap-form-tab').removeClass('active').attr('aria-selected', 'false');
				$('.hap-form-step').removeClass('active');
				$(this).addClass('active').attr('aria-selected', 'true');
				$('.hap-form-step[data-step="' + step + '"]').addClass('active');
			});
		},

		initProfileSave: function () {
			$(document).on('submit', '#hap-profile-form-el', function (e) {
				e.preventDefault();

				const $form = $(this);
				const $btn = $('#hap-save-profile');
				const $msg = $('#hap-save-msg');

				$btn.prop('disabled', true).text(hapProfile.i18n.saving);
				$msg.text('').removeClass('error');

				$.post(hapProfile.ajaxUrl, {
					action: 'hap_save_profile',
					nonce: hapProfile.nonce,
					profile_data: HAP.extractProfileData($form)
				}, function (res) {
					if (res.success) {
						$msg.text(hapProfile.i18n.saved);
					} else {
						$msg.addClass('error').text(hapProfile.i18n.error);
					}

					$btn.prop('disabled', false).text('Profili Kaydet');
				}).fail(function () {
					$msg.addClass('error').text(hapProfile.i18n.error);
					$btn.prop('disabled', false).text('Profili Kaydet');
				});
			});
		},

		initOnboarding: function () {
			$(document).on('click', '[data-step-target]', function () {
				const stepId = $(this).data('stepTarget');
				HAP.activateOnboardingStep(stepId);
			});

			$(document).on('click', '[data-step-back]', function () {
				const currentStep = $(this).data('stepBack');
				const $panel = $('[data-step-panel="' + currentStep + '"]');
				const currentIndex = parseInt($panel.data('stepIndex'), 10);
				const $target = $('.hap-onboarding-panel[data-step-index="' + (currentIndex - 1) + '"]');
				if ($target.length) {
					HAP.activateOnboardingStep($target.data('stepPanel'));
				}
			});

			$(document).on('click', '[data-step-skip]', function () {
				const currentStep = $(this).data('stepSkip');
				const $panel = $('[data-step-panel="' + currentStep + '"]');
				const currentIndex = parseInt($panel.data('stepIndex'), 10);
				const $target = $('.hap-onboarding-panel[data-step-index="' + (currentIndex + 1) + '"]');
				if ($target.length) {
					HAP.activateOnboardingStep($target.data('stepPanel'));
				}
			});

			$(document).on('click', '[data-step-save]', function () {
				const stepId = $(this).data('stepSave');
				const $panel = $('[data-step-panel="' + stepId + '"]');
				const $form = $panel.find('[data-step-form="' + stepId + '"]');
				const $feedback = $panel.find('.hap-onboarding-feedback');
				const $button = $(this);
				$button.prop('disabled', true).text(hapProfile.i18n.saving);
				$feedback.removeClass('is-error is-success').text('');

				$.post(hapProfile.ajaxUrl, {
					action: 'hap_save_onboarding_step',
					nonce: hapProfile.nonce,
					step: stepId,
					profile_data: HAP.extractProfileData($form)
				}, function (res) {
					if (!res.success) {
						const message = res.data && res.data.message ? res.data.message : hapProfile.i18n.error;
						$feedback.addClass('is-error').text(message);
						$button.prop('disabled', false).text(HAP.getStepButtonText(stepId));
						return;
					}

					const nextStep = res.data && res.data.next_step ? res.data.next_step : 'complete';
					const minimumCompletion = res.data && res.data.minimum_completion !== undefined ? parseInt(res.data.minimum_completion, 10) : null;

					$feedback.addClass('is-success').text('Kaydedildi. Sonraki adima geciliyor.');
					if (minimumCompletion !== null && !Number.isNaN(minimumCompletion)) {
						HAP.updateOnboardingProgress(nextStep, minimumCompletion);
					}
					HAP.activateOnboardingStep(nextStep);

					$button.prop('disabled', false).text(HAP.getStepButtonText(stepId));
				}).fail(function () {
					$feedback.addClass('is-error').text(hapProfile.i18n.error);
					$button.prop('disabled', false).text(HAP.getStepButtonText(stepId));
				});
			});
		},

		activateOnboardingStep: function (stepId) {
			const $shell = $('.hap-onboarding-shell');
			const $panel = $('[data-step-panel="' + stepId + '"]');
			if (!$shell.length || !$panel.length) {
				return;
			}

			$('[data-step-panel]').removeClass('is-active');
			$panel.addClass('is-active');

			$('[data-step-target]').removeClass('is-active').attr('aria-selected', 'false');
			$('[data-step-target="' + stepId + '"]').addClass('is-active').attr('aria-selected', 'true');

			HAP.updateOnboardingProgress(stepId);
		},

		updateOnboardingProgress: function (stepId) {
			const $panel = $('[data-step-panel="' + stepId + '"]');
			if (!$panel.length) {
				return;
			}

			const index = parseInt($panel.data('stepIndex'), 10);
			const total = $('[data-step-panel]').length - 1;
			const percent = total > 0 ? Math.round((index / total) * 100) : 0;

			$('[data-onboarding-progress-fill]').css('width', percent + '%');
			$('[data-onboarding-progress-label]').text('%' + percent);
			$('[data-onboarding-step-label]').text('Adim ' + (index + 1) + ' / ' + $('[data-step-panel]').length);
		},

		getStepButtonText: function (stepId) {
			const $button = $('[data-step-save="' + stepId + '"]');
			return $button.data('originalText') || $button.text() || 'Kaydet';
		},

		extractProfileData: function ($form) {
			const data = {};
			$form.find('[name^="profile_data["]').each(function () {
				const match = $(this).attr('name').match(/profile_data\[([^\]]+)\]/);
				if (match) {
					data[match[1]] = $(this).val();
				}
			});
			return data;
		},

		initShare: function () {
			$(document).on('click', '#hap-open-share', function () {
				$('#hap-share-panel').prop('hidden', false).addClass('is-open');
				$('body').addClass('hap-modal-open');
			});

			$(document).on('click', '#hap-close-share, .hap-share-overlay', function () {
				$('#hap-share-panel').removeClass('is-open').prop('hidden', true);
				$('body').removeClass('hap-modal-open');
			});

			$(document).on('click', '#hap-create-share', function () {
				const $btn = $(this);
				const sections = [];

				$('input[name="hap_visible_section"]:checked').each(function () {
					sections.push($(this).val());
				});

				$btn.prop('disabled', true).text(hapProfile.i18n.saving);

				$.post(hapProfile.ajaxUrl, {
					action: 'hap_create_share',
					nonce: hapProfile.nonce,
					visible_sections: sections,
					hidden_fields: [],
					share_title: ''
				}, function (res) {
					$btn.prop('disabled', false).text('Yeni Paylasim Baglantisi Olustur');

					if (res.success) {
						$('#hap-share-url').val(res.data.url);
						$('#hap-share-result').prop('hidden', false).stop(true, true).hide().slideDown(180);
					} else {
						alert(res.data && res.data.message ? res.data.message : hapProfile.i18n.error);
					}
				}).fail(function () {
					$btn.prop('disabled', false).text('Yeni Paylasim Baglantisi Olustur');
					alert(hapProfile.i18n.error);
				});
			});

			$(document).on('click', '#hap-revoke-share', function () {
				if (!confirm(hapProfile.i18n.confirm)) {
					return;
				}

				const shareId = $(this).data('id');

				$.post(hapProfile.ajaxUrl, {
					action: 'hap_revoke_share',
					nonce: hapProfile.nonce,
					share_id: shareId
				}, function (res) {
					if (res.success) {
						$('.hap-share-existing').fadeOut(180, function () {
							$(this).remove();
						});
					}
				});
			});
		},

		initScrollLinks: function () {
			$(document).on('click', '.hap-scroll-link', function (e) {
				const target = $(this).attr('href');
				if (target && target.charAt(0) === '#') {
					const $target = $(target);
					if ($target.length) {
						e.preventDefault();
						$('html, body').animate({ scrollTop: $target.offset().top - 80 }, 360);
					}
				}
			});
		},

		initCopyButtons: function () {
			$(document).on('click', '.hap-copy-share', function () {
				const $button = $(this);
				const target = $button.data('target');
				const $input = $(target);

				if (!$input.length) {
					return;
				}

				navigator.clipboard.writeText($input.val()).then(function () {
					const original = $button.text();
					$button.text('Link Kopyalandi');
					setTimeout(function () {
						$button.text(original);
					}, 1600);
				}).catch(function () {
					$input.trigger('select');
					document.execCommand('copy');
				});
			});
		},

		initResultFilters: function () {
			$(document).on('click', '.hap-result-filter', function () {
				const filter = $(this).data('resultFilter');
				$('.hap-result-filter').removeClass('is-active');
				$(this).addClass('is-active');

				if (filter === 'all') {
					$('[data-result-category]').show();
					return;
				}

				$('[data-result-category]').hide();
				$('[data-result-category="' + filter + '"]').show();
			});
		}
	};

	$(document).ready(function () {
		$('[data-step-save]').each(function () {
			$(this).attr('data-original-text', $(this).text());
		});
		HAP.init();
	});
}(jQuery));
