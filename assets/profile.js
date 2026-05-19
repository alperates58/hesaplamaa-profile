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
			this.initAnalysisPanels();
			this.initAI();
		},

		initAI: function() {
			var pollingInterval = null;

			function parseReportHTML(report) {
				report = report.replace(/^### (.*$)/gim, '<h4>$1</h4>');
				report = report.replace(/^## (.*$)/gim, '<h3>$1</h3>');
				report = report.replace(/^# (.*$)/gim, '<h2>$1</h2>');
				report = report.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
				report = report.replace(/\*(.*?)\*/gim, '<em>$1</em>');
				report = report.replace(/\n/gim, '<br>');
				return report;
			}

			function pollReportStatus(job_id, $btn, $content, $loading) {
				pollingInterval = setInterval(function() {
					$.post(hapProfile.ajaxUrl, {
						action: 'hap_get_ai_report_status',
						nonce: hapProfile.nonce,
						job_id: job_id
					}, function(res) {
						if (res.success) {
							if (res.data.status === 'completed') {
								clearInterval(pollingInterval);
								$loading.hide();
								var report = parseReportHTML(res.data.report);
								var html = '<div class="hap-ai-result" style="text-align:left;line-height:1.6;font-size:1.05rem;">' + report + '</div>';
								if (res.data.generated_at) {
									html += '<p style="font-size:0.8rem;color:#888;margin-top:20px;text-align:center;">Oluşturulma Tarihi: ' + res.data.generated_at + '</p>';
								}
								html += '<div style="text-align:center;margin-top:30px;"><button class="hap-btn hap-btn-secondary" id="hap-generate-ai-btn" data-force="1">Yeniden Oluştur</button></div>';
								$content.html(html);
							} else if (res.data.status === 'failed') {
								clearInterval(pollingInterval);
								$loading.hide();
								var msg = res.data.message || 'AI raporu oluşturulamadı.';
								$content.html('<div style="text-align:center;color:#dc3545;padding:20px;">' + msg + '<br><br><button class="hap-btn hap-btn-secondary" id="hap-generate-ai-btn" data-force="1">Tekrar Dene</button></div>');
								$btn.prop('disabled', false);
							} else {
								// queued veya processing
								$content.find('.hap-ai-loading-text').text(res.data.message || 'Raporun hazırlanıyor, lütfen sayfadan ayrılmayın...');
							}
						} else {
							clearInterval(pollingInterval);
							$loading.hide();
							var msg = res.data && res.data.message ? res.data.message : 'Bir hata oluştu.';
							$content.html('<div style="text-align:center;color:#dc3545;padding:20px;">' + msg + '<br><br><button class="hap-btn hap-btn-secondary" id="hap-generate-ai-btn" data-force="1">Tekrar Dene</button></div>');
							$btn.prop('disabled', false);
						}
					}).fail(function() {
						// Tek seferlik AJAX fail durumunda hemen intervali kapatmamak iyi olabilir ama şimdilik kapatalım
						clearInterval(pollingInterval);
						$loading.hide();
						$content.html('<div style="text-align:center;color:#dc3545;padding:20px;">Sunucu ile iletişim kurulamadı.<br><br><button class="hap-btn hap-btn-secondary" id="hap-generate-ai-btn" data-force="1">Tekrar Dene</button></div>');
						$btn.prop('disabled', false);
					});
				}, 4000);
			}

			$(document).on('change', '#hap-ai-consent-checkbox', function() {
				var isChecked = $(this).is(':checked');
				var $btn = $('#hap-generate-ai-btn');
				if (isChecked) {
					$btn.prop('disabled', false).css({'opacity': '1', 'cursor': 'pointer'});
				} else {
					$btn.prop('disabled', true).css({'opacity': '0.6', 'cursor': 'not-allowed'});
				}
			});

			$(document).on('click', '#hap-generate-ai-btn', function() {
				var $btn = $(this);
				var $loading = $('#hap-ai-loading');
				var $content = $('#hap-ai-report-content');
				var force = $btn.data('force') ? 1 : 0;
				var aiConsent = $('#hap-ai-consent-checkbox').length && $('#hap-ai-consent-checkbox').is(':checked') ? 1 : 0;
				
				$btn.prop('disabled', true);
				$loading.show();
				$content.find('.hap-ai-loading-text').text('AI raporun hazırlanıyor. Bu işlem detay seviyesine göre birkaç dakika sürebilir...');

				if (pollingInterval) {
					clearInterval(pollingInterval);
				}

				$.post(hapProfile.ajaxUrl, {
					action: 'hap_start_ai_report_job',
					nonce: hapProfile.nonce,
					force_regenerate: force,
					ai_consent: aiConsent
				}, function(res) {
					if (res.success) {
						if (res.data.status === 'completed') {
							// Cache'den geldiyse direkt yazdır
							$loading.hide();
							var report = parseReportHTML(res.data.report);
							var html = '<div class="hap-ai-result" style="text-align:left;line-height:1.6;font-size:1.05rem;">' + report + '</div>';
							html += '<p style="font-size:0.8rem;color:#888;margin-top:20px;text-align:center;">Önbellekten yüklendi.</p>';
							html += '<div style="text-align:center;margin-top:30px;"><button class="hap-btn hap-btn-secondary" id="hap-generate-ai-btn" data-force="1">Yeniden Oluştur</button></div>';
							$content.html(html);
						} else {
							// Queued/processing: polling başlat
							pollReportStatus(res.data.job_id, $btn, $content, $loading);
						}
					} else {
						$loading.hide();
						var msg = res.data && res.data.message ? res.data.message : 'Bir hata oluştu.';
						alert('Hata: ' + msg);
						$btn.prop('disabled', false);
					}
				}).fail(function() {
					$loading.hide();
					alert('Sunucu hatası.');
					$btn.prop('disabled', false);
				});
			});

			// Sayfa yüklendiğinde aktif bir job varsa polling'i başlat
			var $contentContainer = $('#hap-ai-report-content');
			var activeJobId = $contentContainer.data('active-job');
			if (activeJobId) {
				var $btn = $('#hap-generate-ai-btn');
				var $loading = $('#hap-ai-loading');
				$btn.prop('disabled', true);
				$loading.show();
				$contentContainer.find('.hap-ai-loading-text').text('AI raporun hazırlanıyor. Sayfayı yenilediniz, işleme devam ediliyor...');
				pollReportStatus(activeJobId, $btn, $contentContainer, $loading);
			}
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

			$(document).on('click', '#hap-open-share-sidebar', function () {
				$('#hap-open-share').trigger('click');
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
		},

		initAnalysisPanels: function () {
			const $navItems = $('[data-analysis-target]');
			const $panels = $('[data-analysis-panel]');

			if (!$navItems.length || !$panels.length) {
				return;
			}

			const getPanelKey = function () {
				const hash = (window.location.hash || '').replace(/^#/, '');
				return hash || 'overview';
			};

			const setActivePanel = function (panelKey, shouldUpdateHash) {
				let target = panelKey || 'overview';
				if (!$panels.filter('[data-analysis-panel="' + target + '"]').length) {
					target = 'overview';
				}

				$navItems.removeClass('is-active').attr('aria-pressed', 'false');
				$panels.removeClass('is-active');

				$navItems.filter('[data-analysis-target="' + target + '"]').addClass('is-active').attr('aria-pressed', 'true');
				$panels.filter('[data-analysis-panel="' + target + '"]').addClass('is-active');

				if (shouldUpdateHash && window.history && typeof window.history.replaceState === 'function') {
					window.history.replaceState(null, '', '#' + target);
				}
			};

			setActivePanel(getPanelKey(), false);

			$(document).on('click', '[data-analysis-target]', function () {
				const panelKey = $(this).data('analysisTarget');
				setActivePanel(panelKey, true);
			});

			$(window).on('hashchange', function () {
				setActivePanel(getPanelKey(), false);
			});
		}
	};

	HAP.initSidebar = function () {
		var sections = document.querySelectorAll('#hap-section-overview, #hap-section-featured, #hap-section-results, #hap-section-ai, #hap-section-next, #hap-section-missing, #hap-section-categories');
		if ( ! sections.length ) return;

		function setActive(id) {
			$('.hap-sidebar-link').removeClass('is-active');
			$('.hap-mobile-nav-link').removeClass('is-active');
			$('.hap-sidebar-link[data-section="' + id + '"]').addClass('is-active');
			$('.hap-mobile-nav-link[data-section="' + id + '"]').addClass('is-active');
		}

		if ( 'IntersectionObserver' in window ) {
			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if ( entry.isIntersecting ) {
						setActive(entry.target.id);
					}
				});
			}, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });

			sections.forEach(function (el) { observer.observe(el); });
		}

		$('#hap-open-share-sidebar').on('click', function () {
			$('#hap-open-share').trigger('click');
		});
	};

	$(document).ready(function () {
		$('[data-step-save]').each(function () {
			$(this).attr('data-original-text', $(this).text());
		});
		HAP.init();
		HAP.initSidebar();

		// Mobile profile card toggle
		$(document).on('click', '.hap-analysis-profile-head', function() {
			if ($(window).width() <= 1024) {
				$(this).toggleClass('is-open');
				$(this).siblings('.hap-profile-accordion-content').slideToggle(200);
			}
		});
	});
}(jQuery));
