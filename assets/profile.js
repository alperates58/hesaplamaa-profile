/* Hesaplamaa Profile — Frontend JS */
(function ($) {
	'use strict';

	const HAP = {
		init: function () {
			this.initFormTabs();
			this.initProfileSave();
			this.initShare();
			this.initScrollLinks();
		},

		initFormTabs: function () {
			$(document).on('click', '.hap-form-tab', function () {
				const step = $(this).data('step');
				$('.hap-form-tab').removeClass('active');
				$('.hap-form-step').removeClass('active');
				$(this).addClass('active');
				$('.hap-form-step[data-step="' + step + '"]').addClass('active');
			});
		},

		initProfileSave: function () {
			$(document).on('submit', '#hap-profile-form-el', function (e) {
				e.preventDefault();
				const $btn = $('#hap-save-profile');
				const $msg = $('#hap-save-msg');

				$btn.prop('disabled', true).text(hapProfile.i18n.saving);
				$msg.text('').removeClass('error');

				const data = $(this).serializeArray().reduce(function (acc, field) {
					acc[field.name] = field.value;
					return acc;
				}, {});

				$.post(hapProfile.ajaxUrl, {
					action: 'hap_save_profile',
					nonce: hapProfile.nonce,
					profile_data: HAP.extractProfileData($(this))
				}, function (res) {
					if (res.success) {
						$msg.text(hapProfile.i18n.saved);
						if (res.data && res.data.completion !== undefined) {
							$('.hap-completion-fill').css('width', res.data.completion + '%');
							$('.hap-completion-pct').text(res.data.completion + '% tamamlandı');
						}
					} else {
						$msg.addClass('error').text(hapProfile.i18n.error);
					}
					$btn.prop('disabled', false).text('💾 Profili Kaydet');
				}).fail(function () {
					$msg.addClass('error').text(hapProfile.i18n.error);
					$btn.prop('disabled', false).text('💾 Profili Kaydet');
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
			// Open modal
			$(document).on('click', '#hap-open-share', function () {
				$('#hap-share-panel').fadeIn(200);
				$('body').addClass('hap-modal-open');
			});

			// Close modal
			$(document).on('click', '#hap-close-share, .hap-share-overlay', function () {
				$('#hap-share-panel').fadeOut(200);
				$('body').removeClass('hap-modal-open');
			});

			// Create share
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
					$btn.prop('disabled', false).text('Yeni Paylaşım Bağlantısı Oluştur');
					if (res.success) {
						$('#hap-share-url').val(res.data.url);
						$('#hap-share-result').slideDown(200);
					} else {
						alert(res.data && res.data.message ? res.data.message : hapProfile.i18n.error);
					}
				}).fail(function () {
					$btn.prop('disabled', false).text('Yeni Paylaşım Bağlantısı Oluştur');
					alert(hapProfile.i18n.error);
				});
			});

			// Revoke share
			$(document).on('click', '#hap-revoke-share', function () {
				if (!confirm(hapProfile.i18n.confirm)) return;
				const shareId = $(this).data('id');
				$.post(hapProfile.ajaxUrl, {
					action: 'hap_revoke_share',
					nonce: hapProfile.nonce,
					share_id: shareId
				}, function (res) {
					if (res.success) {
						$('.hap-share-existing').fadeOut(200, function () { $(this).remove(); });
					}
				});
			});
		},

		initScrollLinks: function () {
			$(document).on('click', '.hap-scroll-link', function (e) {
				const target = $(this).attr('href');
				if (target && target.startsWith('#')) {
					e.preventDefault();
					const $target = $(target);
					if ($target.length) {
						$('html, body').animate({ scrollTop: $target.offset().top - 80 }, 400);
					}
				}
			});
		}
	};

	window.hapCopyShare = function () {
		const $input = $('#hap-share-url, #hap-share-url-existing').first();
		if ($input.length) {
			$input[0].select();
			document.execCommand('copy');
			const orig = $input.siblings('.hap-btn-sm').text();
			$input.siblings('.hap-btn-sm').text('Kopyalandı!');
			setTimeout(function () {
				$input.siblings('.hap-btn-sm').text(orig);
			}, 2000);
		}
	};

	$(document).ready(function () {
		HAP.init();
	});
}(jQuery));
