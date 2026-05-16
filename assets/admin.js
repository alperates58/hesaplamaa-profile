/* Hesaplamaa Profile — Admin JS */
(function ($) {
	'use strict';

	/* -------------------------------------------------------
	   JSON IMPORT
	   ------------------------------------------------------- */
	$('#hap-import-json').on('click', function () {
		const file = document.getElementById('hap-json-file').files[0];
		if (!file) {
			alert('Lütfen bir JSON dosyası seçin.');
			return;
		}

		const $btn = $(this);
		const $msg = $('#hap-import-result');
		const formData = new FormData();
		formData.append('action', 'hap_import_modules');
		formData.append('nonce', hapAdmin.nonce);
		formData.append('json_file', file);

		$btn.prop('disabled', true).text(hapAdmin.i18n.importing);
		$msg.text('').removeClass('success error');

		$.ajax({
			url: hapAdmin.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (res) {
				$btn.prop('disabled', false).text('İçe Aktar');
				if (res.success) {
					$msg.addClass('success').text(res.data.message);
					setTimeout(function () { location.reload(); }, 1500);
				} else {
					$msg.addClass('error').text(res.data && res.data.message ? res.data.message : hapAdmin.i18n.import_error);
				}
			},
			error: function () {
				$btn.prop('disabled', false).text('İçe Aktar');
				$msg.addClass('error').text(hapAdmin.i18n.import_error);
			}
		});
	});

	/* -------------------------------------------------------
	   SUITE SYNC
	   ------------------------------------------------------- */
	$('#hap-sync-suite').on('click', function () {
		if (!confirm('Hesaplama Suite\'ten modüller içe aktarılacak. Devam edilsin mi?')) return;
		const $btn = $(this);
		$btn.prop('disabled', true).text(hapAdmin.i18n.importing);

		$.post(hapAdmin.ajaxUrl, {
			action: 'hap_sync_from_suite',
			nonce: hapAdmin.nonce
		}, function (res) {
			$btn.prop('disabled', false).text('Hesaplama Suite\'ten İçe Aktar');
			if (res.success) {
				alert(res.data.message);
				location.reload();
			} else {
				alert(res.data && res.data.message ? res.data.message : hapAdmin.i18n.import_error);
			}
		});
	});

	/* -------------------------------------------------------
	   DELETE MODULE
	   ------------------------------------------------------- */
	$(document).on('click', '.hap-delete-module', function () {
		if (!confirm(hapAdmin.i18n.delete_confirm)) return;
		const id  = $(this).data('id');
		const $tr = $(this).closest('tr');
		const $btn = $(this);
		$btn.prop('disabled', true).text(hapAdmin.i18n.deleting);

		$.post(hapAdmin.ajaxUrl, {
			action: 'hap_delete_module',
			nonce: hapAdmin.nonce,
			module_id: id
		}, function (res) {
			if (res.success) {
				$tr.fadeOut(300, function () { $(this).remove(); });
			} else {
				$btn.prop('disabled', false).text('Sil');
				alert(res.data && res.data.message ? res.data.message : 'Hata oluştu.');
			}
		});
	});

	/* -------------------------------------------------------
	   EDIT MODULE MODAL
	   ------------------------------------------------------- */
	$(document).on('click', '.hap-edit-module', function () {
		const id = $(this).data('id');
		const $row = $(this).closest('tr');
		const tmpl = document.getElementById('hap-edit-template');
		if (!tmpl) return;

		const meta    = tmpl.querySelector('[data-sections]');
		const sections  = JSON.parse(meta.getAttribute('data-sections') || '{}');
		const statuses  = JSON.parse(meta.getAttribute('data-statuses') || '{}');
		const mfb       = JSON.parse(meta.getAttribute('data-mfb') || '{}');
		const avail     = JSON.parse(meta.getAttribute('data-avail') || '{}');
		const allFields = JSON.parse(meta.getAttribute('data-fields') || '[]');

		const $select   = $row.find('select.hap-inline-field[data-field="profile_status"]');
		const curStatus = $select.val() || 'disabled';

		function buildSelect(name, opts, selected) {
			let html = '<select name="' + name + '" class="widefat">';
			$.each(opts, function (k, v) {
				html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + v + '</option>';
			});
			return html + '</select>';
		}

		let reqHtml = '<div class="hap-req-fields-edit">';
		allFields.forEach(function (f) {
			reqHtml += '<label style="display:inline-flex;align-items:center;gap:5px;margin:3px 6px 3px 0;font-size:.85rem">'
				+ '<input type="checkbox" name="required_fields[]" value="' + f.key + '"> ' + f.label + '</label>';
		});
		reqHtml += '</div>';

		const form = '<table class="form-table" style="margin:0">'
			+ '<tr><th>Profil Durumu</th><td>' + buildSelect('profile_status', statuses, curStatus) + '</td></tr>'
			+ '<tr><th>Bölüm</th><td>' + buildSelect('section', sections, '') + '</td></tr>'
			+ '<tr><th>Eksik Alan Davranışı</th><td>' + buildSelect('missing_fields_behavior', mfb, 'show_prompt') + '</td></tr>'
			+ '<tr><th>Erişilebilirlik</th><td>' + buildSelect('availability_status', avail, 'active') + '</td></tr>'
			+ '<tr><th>AI Yorumu</th><td><label><input type="checkbox" name="ai_enabled" value="1"> Aktif</label></td></tr>'
			+ '<tr><th>Sıra</th><td><input type="number" name="sort_order" value="0" class="small-text"></td></tr>'
			+ '<tr><th>Gerekli Alanlar</th><td>' + reqHtml + '</td></tr>'
			+ '<tr><th>Notlar</th><td><textarea name="notes" rows="2" class="widefat"></textarea></td></tr>'
			+ '</table>';

		$('#hap-edit-form-content').html(form);
		$('#hap-edit-modal').data('module-id', id).fadeIn(150);
	});

	$('#hap-modal-close').on('click', function () {
		$('#hap-edit-modal').fadeOut(150);
	});

	$('#hap-modal-save').on('click', function () {
		const id = $('#hap-edit-modal').data('module-id');
		const $btn = $(this);
		$btn.prop('disabled', true).text(hapAdmin.i18n.saving);

		const formData = {
			action: 'hap_save_single_module',
			nonce: hapAdmin.nonce,
			module_id: id,
			profile_status: $('#hap-edit-form-content [name=profile_status]').val(),
			section: $('#hap-edit-form-content [name=section]').val(),
			missing_fields_behavior: $('#hap-edit-form-content [name=missing_fields_behavior]').val(),
			availability_status: $('#hap-edit-form-content [name=availability_status]').val(),
			ai_enabled: $('#hap-edit-form-content [name=ai_enabled]').is(':checked') ? 1 : 0,
			sort_order: $('#hap-edit-form-content [name=sort_order]').val(),
			notes: $('#hap-edit-form-content [name=notes]').val(),
			required_fields: []
		};

		$('#hap-edit-form-content [name="required_fields[]"]:checked').each(function () {
			formData.required_fields.push($(this).val());
		});

		$.post(hapAdmin.ajaxUrl, formData, function (res) {
			$btn.prop('disabled', false).text(hapAdmin.i18n.saved);
			if (res.success) {
				setTimeout(function () {
					$('#hap-edit-modal').fadeOut(150);
					location.reload();
				}, 800);
			} else {
				$btn.text('Kaydet');
				alert(res.data && res.data.message ? res.data.message : 'Hata oluştu.');
			}
		});
	});

	/* Inline status change */
	$(document).on('change', 'select.hap-inline-field', function () {
		const id = $(this).data('id');
		const field = $(this).data('field');
		const val = $(this).val();
		$.post(hapAdmin.ajaxUrl, {
			action: 'hap_save_single_module',
			nonce: hapAdmin.nonce,
			module_id: id,
			profile_status: field === 'profile_status' ? val : undefined
		});
	});

}(jQuery));
