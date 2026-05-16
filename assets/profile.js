/* Hesaplamaa Profile - Frontend JS */
(function ($) {
	'use strict';

	const HAP = {
		init: function () {
			this.initFormTabs();
			this.initProfileSave();
			this.initShare();
			this.initScrollLinks();
			this.initCopyButtons();
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
						const completion = res.data && res.data.completion !== undefined ? parseInt(res.data.completion, 10) : null;
						$msg.text(hapProfile.i18n.saved);

						if (completion !== null && !Number.isNaN(completion)) {
							$('.hap-progress-fill').css('width', completion + '%');
							$('.hap-progress-chip').text('%' + completion);
							$('.hap-progress-meta strong').text('Profil tamamlama: %' + completion);
							$('.hap-progress-meta span').first().text(
								completion > 0
									? 'Bilgilerin tamamlandikca daha fazla analiz acilir.'
									: 'Baslamak icin temel bilgilerini ekle.'
							);
						}
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
		}
	};

	$(document).ready(function () {
		HAP.init();
	});
}(jQuery));
