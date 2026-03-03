<?php
// *************************************************************************
// * DEPRIXA PRO - Import Excel courier: preview editable + creación masiva *
// *************************************************************************

$userData = $user->cdp_getUserData();
?>
<!DOCTYPE html>
<html dir="<?php echo $direction_layout; ?>" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($core->meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <title><?php echo $lang['asingmoduleexcell2']; ?> | <?php echo $core->site_name ?></title>
    <?php include 'views/inc/head_scripts.php'; ?>
    <style>
        .card { border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .file-input { padding: 10px; background: #f8f9fa; border-radius: 5px; border: 2px dashed #007bff; }
        .file-input input[type="file"] { display: none; }
        .file-input label { display: block; padding: 15px; text-align: center; cursor: pointer; }
        #preview_section { display: none; }
        .table-preview th, .table-preview td { font-size: 0.85rem; vertical-align: middle !important; }
        .table-preview .form-control { min-width: 4rem; padding: 0.35rem 0.5rem; }
        .table-preview .cell-name { max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .table-preview .cell-select { min-width: 120px; }
        .table-preview .cell-select select { min-width: 100%; max-width: 180px; }
        .cell-error { color: #dc3545; font-size: 0.8rem; max-width: 200px; }
        .cell-tarifa { font-weight: 600; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @media (max-width: 768px) { .table-preview .form-control { min-width: 3rem; } }
    </style>
</head>
<body>
    <?php include 'views/inc/preloader.php'; ?>
    <div id="main-wrapper">
        <?php include 'views/inc/topbar.php'; ?>
        <?php include 'views/inc/left_sidebar.php'; ?>
        <div class="page-wrapper">
            <div class="page-breadcrumb">
                <div class="row">
                    <div class="col-5 align-self-center">
                        <h4 class="page-title"><?php echo $lang['asingmoduleexcell2']; ?></h4>
                    </div>
                </div>
            </div>
            <div class="container-fluid">
                <!-- PASO 1: Subir archivo -->
                <div id="upload_section" class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <form id="upload_form" enctype="multipart/form-data">
                                    <div class="mb-4">
                                        <label for="file" class="form-label"><?php echo isset($lang['asingmoduleexcell2']) ? $lang['asingmoduleexcell2'] : 'Importar archivo Excel o CSV'; ?></label>
                                        <p class="text-muted">
                                            <?php echo isset($lang['courier_import_help']) ? $lang['courier_import_help'] : ''; ?>
                                        </p>
                                        <a href="ajax/courier/import_excel_add_courier_ajax.php?action=template" class="btn btn-sm btn-info mb-3"><?php echo isset($lang['leftorder16']) ? $lang['leftorder16'] : ''; ?></a>
                                        <a href="ajax/courier/import_excel_add_courier_ajax.php?action=reference" class="btn btn-sm btn-outline-secondary mb-3"><?php echo isset($lang['courier_import_download_reference']) ? $lang['courier_import_download_reference'] : 'Descargar referencia de IDs'; ?></a>
                                        <div class="file-input">
                                            <input type="file" name="file" id="file" accept=".xls,.xlsx,.csv">
                                            <label for="file"><?php echo isset($lang['asingmoduleexcell2']) ? $lang['asingmoduleexcell2'] : 'Seleccionar archivo'; ?></label>
                                        </div>
                                        <div id="file-name" class="mt-2 text-muted small"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="btn_upload"><?php echo isset($lang['asingmoduleexcell1']) ? $lang['asingmoduleexcell1'] : 'Importar'; ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- PASO 2: Vista previa editable -->
                <div id="preview_section" class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo isset($lang['courier_import_preview']) ? $lang['courier_import_preview'] : ''; ?></h5>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_back_upload"><?php echo isset($lang['courier_import_btn_back']) ? $lang['courier_import_btn_back'] : $lang['global-buttons-3']; ?></button>
                                    <button type="button" class="btn btn-success" id="btn_create_shipments"><?php echo isset($lang['courier_import_btn_create']) ? $lang['courier_import_btn_create'] : $lang['left1103']; ?></button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="preview_summary" class="alert mb-3"></div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-preview" id="preview_table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th><?php echo isset($lang['courier_import_sender']) ? $lang['courier_import_sender'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_sender_address']) ? $lang['courier_import_sender_address'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_recipient']) ? $lang['courier_import_recipient'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_recipient_address']) ? $lang['courier_import_recipient_address'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_service_mode']) ? $lang['courier_import_service_mode'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_country_origin']) ? $lang['courier_import_country_origin'] : 'País origen'; ?></th>
                                                <th><?php echo isset($lang['courier_import_country_destination']) ? $lang['courier_import_country_destination'] : 'País destino'; ?></th>
                                                <th><?php echo $lang['courier_table_qty']; ?></th>
                                                <th><?php echo $lang['left228']; ?></th>
                                                <th><?php echo $lang['left216']; ?></th>
                                                <th><?php echo $lang['left217']; ?></th>
                                                <th><?php echo $lang['left218']; ?></th>
                                                <th><?php echo $lang['left213']; ?></th>
                                                <th><?php echo isset($lang['courier_import_declared_value']) ? $lang['courier_import_declared_value'] : 'declared_value'; ?></th>
                                                <th><?php echo isset($lang['courier_import_distance_miles']) ? $lang['courier_import_distance_miles'] : 'distance_miles'; ?></th>
                                                <th><?php echo isset($lang['courier_import_manual_tariff']) ? $lang['courier_import_manual_tariff'] : 'manual_tariff'; ?></th>
                                                <th><?php echo isset($lang['courier_import_price_lb']) ? $lang['courier_import_price_lb'] : 'price_lb'; ?></th>
                                                <th><?php echo isset($lang['courier_import_chargeable_weight']) ? $lang['courier_import_chargeable_weight'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_price_base']) ? $lang['courier_import_price_base'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_cargo_miles']) ? $lang['courier_import_cargo_miles'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_total_tariff']) ? $lang['courier_import_total_tariff'] : ''; ?></th>
                                                <th><?php echo isset($lang['courier_import_error_col']) ? $lang['courier_import_error_col'] : ''; ?></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="preview_tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'views/inc/footer.php'; ?>
        </div>
    </div>
    <script>
(function() {
    var L = <?php echo json_encode([
        'rows' => isset($lang['courier_import_rows']) ? $lang['courier_import_rows'] : 'Filas',
        'with_error' => isset($lang['courier_import_with_error']) ? $lang['courier_import_with_error'] : 'Con error',
        'valid' => isset($lang['courier_import_valid']) ? $lang['courier_import_valid'] : 'Válidas',
        'delete_row' => isset($lang['courier_import_delete_row']) ? $lang['courier_import_delete_row'] : 'Quitar',
        'select_file' => isset($lang['courier_import_select_file']) ? $lang['courier_import_select_file'] : 'Seleccione un archivo.',
        'no_data' => isset($lang['courier_import_no_data']) ? $lang['courier_import_no_data'] : 'El archivo no contiene filas válidas o tiene solo encabezados.',
        'cannot_create' => isset($lang['courier_import_cannot_create']) ? $lang['courier_import_cannot_create'] : 'Hay filas con tarifa automática sin tarifa calculada o con error.',
        'no_valid_rows' => isset($lang['courier_import_no_valid_rows']) ? $lang['courier_import_no_valid_rows'] : 'No hay filas sin error para crear.',
        'process_done' => isset($lang['courier_import_process_done']) ? $lang['courier_import_process_done'] : 'Proceso finalizado',
        'created' => isset($lang['courier_import_created']) ? $lang['courier_import_created'] : 'Creados',
        'failed' => isset($lang['courier_import_failed']) ? $lang['courier_import_failed'] : 'Fallidos',
        'conn_error' => isset($lang['courier_import_conn_error']) ? $lang['courier_import_conn_error'] : 'Error de conexión.',
        'server_error' => isset($lang['courier_import_server_error']) ? $lang['courier_import_server_error'] : 'Respuesta no válida del servidor.',
        'error' => isset($lang['aaction']) ? $lang['aaction'] : 'Error',
        'file_label' => isset($lang['courier_import_file_label']) ? $lang['courier_import_file_label'] : 'Archivo',
        'invalid_option' => isset($lang['courier_import_invalid_option']) ? $lang['courier_import_invalid_option'] : ' (invalid)',
        'success_redirect' => isset($lang['courier_import_success_redirect']) ? $lang['courier_import_success_redirect'] : 'Será redirigido a la lista de envíos.',
    ]); ?>;
    var previewRows = [];
    var previewDefaults = {};
    var optionData = { senders: [], valid_categories: [], sender_addresses: {}, recipients_by_sender: {}, recipient_addresses: {} };
    var ajaxBase = 'ajax/';

    function esc(s) { return (s == null || s === '') ? '' : String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function numVal(v) { var n = parseFloat(v); return (v !== '' && v != null && !isNaN(n)) ? n : 0; }
    function intVal(v) { var n = parseInt(v, 10); return (!isNaN(n)) ? n : 0; }

    function optionsHtml(list, selectedId, addInvalid) {
        var invalidLabel = L.invalid_option || ' (invalid)';
        if (!list || !list.length) return addInvalid && selectedId ? '<option value="' + esc(String(selectedId)) + '" selected>ID ' + esc(String(selectedId)) + invalidLabel + '</option>' : '<option value="">—</option>';
        var out = [];
        var found = false;
        list.forEach(function(o) {
            var id = o.id != null ? o.id : o.id_addresses;
            var text = (o.text != null ? o.text : o.name_item) || ('ID ' + id);
            var sel = (selectedId != null && selectedId !== '' && Number(id) === Number(selectedId)) ? ' selected' : '';
            if (sel) found = true;
            out.push('<option value="' + esc(String(id)) + '"' + sel + '>' + esc(text) + '</option>');
        });
        if (addInvalid && selectedId != null && selectedId !== '' && !found) {
            out.unshift('<option value="' + esc(String(selectedId)) + '" selected>ID ' + esc(String(selectedId)) + invalidLabel + '</option>');
        }
        return out.join('') || '<option value="">—</option>';
    }

    function showUpload() {
        document.getElementById('upload_section').style.display = 'block';
        document.getElementById('preview_section').style.display = 'none';
    }
    function showPreview() {
        document.getElementById('upload_section').style.display = 'none';
        document.getElementById('preview_section').style.display = 'block';
    }

    document.getElementById('upload_form').addEventListener('submit', function(e) {
        e.preventDefault();
        var fileInput = document.getElementById('file');
        var file = fileInput.files[0];
        if (!file) {
            Swal.fire({ icon: 'error', title: L.error, text: L.select_file });
            return;
        }
        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'preview');
        document.getElementById('btn_upload').disabled = true;
        fetch('ajax/courier/import_excel_add_courier_ajax.php', { method: 'POST', body: formData })
            .then(function(r) {
                if (!r.ok) throw new Error('Servidor: ' + r.status);
                return r.text().then(function(t) {
                    try { return JSON.parse(t); } catch (e) { throw new Error(L.server_error); }
                });
            })
            .then(function(data) {
                document.getElementById('btn_upload').disabled = false;
                if (data.success && data.rows && data.rows.length) {
                    previewRows = data.rows;
                    previewDefaults = data.defaults || {};
                    optionData.senders = data.senders || [];
                    optionData.valid_categories = data.valid_categories || [];
                    optionData.sender_addresses = data.sender_addresses || {};
                    optionData.recipients_by_sender = data.recipients_by_sender || {};
                    optionData.recipient_addresses = data.recipient_addresses || {};
                    renderPreviewTable();
                    showPreview();
                } else if (data.success && (!data.rows || data.rows.length === 0)) {
                    Swal.fire({ icon: 'warning', title: L.error, text: L.no_data });
                } else {
                    Swal.fire({ icon: 'error', title: L.error, text: data.error || '' });
                }
            })
            .catch(function(err) {
                document.getElementById('btn_upload').disabled = false;
                Swal.fire({ icon: 'error', title: L.error, text: err.message || L.conn_error });
            });
    });

    function renderPreviewTable() {
        var tbody = document.getElementById('preview_tbody');
        tbody.innerHTML = '';
        var ok = 0, err = 0;
        previewRows.forEach(function(r, i) {
            var cats = optionData.valid_categories || [];
            var catIds = cats.map(function(c) { return Number(c.id); });
            var svcId = r.order_service_options != null ? String(r.order_service_options) : '';
            var categoryErrorResolved = r.row_error && r.row_error.indexOf('cdb_category') !== -1 && svcId && catIds.indexOf(Number(svcId)) === -1 && cats.length > 0;
            var hasError = (r.row_error && !categoryErrorResolved) || r.tariff_error;
            if (hasError) err++; else ok++;
            var tr = document.createElement('tr');
            tr.setAttribute('data-row-idx', i);
            if (hasError) tr.classList.add('table-danger');
            var errText = categoryErrorResolved ? (r.tariff_error || '') : [r.row_error, r.tariff_error].filter(Boolean).join(' ');
            var sid = r.sender_id != null ? String(r.sender_id) : '';
            var said = r.sender_address_id != null ? String(r.sender_address_id) : '';
            var rid = r.recipient_id != null ? String(r.recipient_id) : '';
            var raid = r.recipient_address_id != null ? String(r.recipient_address_id) : '';
            var senderOpts = optionsHtml(optionData.senders, sid, false);
            var senderAddrOpts = optionsHtml(optionData.sender_addresses[sid] || [], said, false);
            var recipientOpts = optionsHtml(optionData.recipients_by_sender[sid] || [], rid, false);
            var recipientAddrOpts = optionsHtml(optionData.recipient_addresses[rid] || [], raid, false);
            var svcInvalid = svcId && catIds.indexOf(Number(svcId)) === -1;
            var serviceSelectedId = svcInvalid && cats.length ? String(cats[0].id) : svcId;
            var serviceOpts = optionsHtml(cats.map(function(c) { return { id: c.id, text: (c.name_item || '') + ' (id ' + c.id + ')' }; }), serviceSelectedId, false);
            var qty = numVal(r.quantity); if (qty <= 0) qty = 1;
            tr.innerHTML =
                '<td class="text-center">' + (i + 1) + '</td>' +
                '<td class="cell-select"><select class="form-control form-control-sm row-select-sender" data-idx="' + i + '" data-key="sender_id">' + senderOpts + '</select></td>' +
                '<td class="cell-select"><select class="form-control form-control-sm row-select-sender-addr" data-idx="' + i + '" data-key="sender_address_id">' + senderAddrOpts + '</select></td>' +
                '<td class="cell-select"><select class="form-control form-control-sm row-select-recipient" data-idx="' + i + '" data-key="recipient_id">' + recipientOpts + '</select></td>' +
                '<td class="cell-select"><select class="form-control form-control-sm row-select-recipient-addr" data-idx="' + i + '" data-key="recipient_address_id">' + recipientAddrOpts + '</select></td>' +
                '<td class="cell-select"><select class="form-control form-control-sm row-select-service" data-idx="' + i + '" data-key="order_service_options">' + serviceOpts + '</select></td>' +
                '<td class="cell-name">' + esc(r.country_origin_name || '') + '</td>' +
                '<td class="cell-name">' + esc(r.country_destination_name || '') + '</td>' +
                '<td><input type="number" min="1" step="1" class="form-control form-control-sm" data-key="quantity" value="' + qty + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="1" step="any" class="form-control form-control-sm" data-key="weight" value="' + (numVal(r.weight) >= 1 ? numVal(r.weight) : 1) + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" step="any" class="form-control form-control-sm" data-key="length" value="' + numVal(r.length) + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" step="any" class="form-control form-control-sm" data-key="width" value="' + numVal(r.width) + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" step="any" class="form-control form-control-sm" data-key="height" value="' + numVal(r.height) + '" data-idx="' + i + '"></td>' +
                '<td><input type="text" class="form-control form-control-sm" data-key="description" value="' + esc(r.description || '') + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" step="any" class="form-control form-control-sm" data-key="declared_value" value="' + numVal(r.declared_value) + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" step="any" class="form-control form-control-sm" data-key="distance_miles" value="' + numVal(r.distance_miles) + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" max="1" step="1" class="form-control form-control-sm" data-key="manual_tariff" value="' + (r.row_error ? 1 : intVal(r.manual_tariff)) + '" data-idx="' + i + '"></td>' +
                '<td><input type="number" min="0" step="any" class="form-control form-control-sm" data-key="price_lb" value="' + numVal(r.price_lb) + '" data-idx="' + i + '"></td>' +
                '<td class="cell-tarifa">' + (r.chargeable_weight != null && r.chargeable_weight !== '' ? r.chargeable_weight : '&mdash;') + '</td>' +
                '<td class="cell-tarifa">' + (r.price_base != null && r.price_base !== '' ? r.price_base : '&mdash;') + '</td>' +
                '<td class="cell-tarifa">' + (r.cargo_millas != null && r.cargo_millas !== '' ? r.cargo_millas : '&mdash;') + '</td>' +
                '<td class="cell-tarifa">' + (r.total_tarifa != null && r.total_tarifa !== '' ? r.total_tarifa : '&mdash;') + '</td>' +
                '<td class="cell-error small">' + (errText ? esc(errText) : '&mdash;') + '</td>' +
                '<td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm btn-remove-row" data-idx="' + i + '" title="' + esc(L.delete_row) + '"><i class="fa fa-trash"></i></button></td>';
            tbody.appendChild(tr);
        });
        document.getElementById('preview_summary').innerHTML = '<strong>' + L.rows + ':</strong> ' + previewRows.length + ' &nbsp;|&nbsp; ' + L.with_error + ': ' + err + ' &nbsp;|&nbsp; ' + L.valid + ': ' + ok;
        document.getElementById('preview_summary').className = 'alert mb-3 ' + (err > 0 ? 'alert-warning' : 'alert-info');

        tbody.querySelectorAll('.btn-remove-row').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.getAttribute('data-idx'), 10);
                if (isNaN(idx) || idx < 0 || idx >= previewRows.length) return;
                previewRows.splice(idx, 1);
                renderPreviewTable();
            });
        });

        tbody.querySelectorAll('.row-select-sender').forEach(function(sel) {
            sel.addEventListener('change', function() {
                var idx = parseInt(this.getAttribute('data-idx'), 10);
                var tr = this.closest('tr');
                var senderId = this.value;
                var addrSel = tr.querySelector('.row-select-sender-addr');
                var recSel = tr.querySelector('.row-select-recipient');
                var recAddrSel = tr.querySelector('.row-select-recipient-addr');
                var addrs = optionData.sender_addresses[senderId];
                var recs = optionData.recipients_by_sender[senderId];
                if (addrs || recs) {
                    addrSel.innerHTML = optionsHtml(addrs || [], '', false);
                    recSel.innerHTML = optionsHtml(recs || [], '', false);
                    recAddrSel.innerHTML = '<option value="">—</option>';
                } else if (senderId) {
                    Promise.all([
                        fetch(ajaxBase + 'select2_sender_addresses.php?id=' + encodeURIComponent(senderId)).then(function(res) { return res.json(); }).catch(function() { return []; }),
                        fetch(ajaxBase + 'select2_recipient.php?id=' + encodeURIComponent(senderId)).then(function(res) { return res.json(); }).catch(function() { return []; })
                    ]).then(function(results) {
                        optionData.sender_addresses[senderId] = results[0];
                        optionData.recipients_by_sender[senderId] = results[1];
                        addrSel.innerHTML = optionsHtml(results[0], '', false);
                        recSel.innerHTML = optionsHtml(results[1], '', false);
                        recAddrSel.innerHTML = '<option value="">—</option>';
                    });
                } else {
                    addrSel.innerHTML = '<option value="">—</option>';
                    recSel.innerHTML = '<option value="">—</option>';
                    recAddrSel.innerHTML = '<option value="">—</option>';
                }
            });
        });

        tbody.querySelectorAll('.row-select-recipient').forEach(function(sel) {
            sel.addEventListener('change', function() {
                var tr = this.closest('tr');
                var recipientId = this.value;
                var recAddrSel = tr.querySelector('.row-select-recipient-addr');
                var addrs = optionData.recipient_addresses[recipientId];
                if (addrs) {
                    recAddrSel.innerHTML = optionsHtml(addrs, '', false);
                } else if (recipientId) {
                    fetch(ajaxBase + 'select2_recipient_addresses.php?id=' + encodeURIComponent(recipientId)).then(function(res) { return res.json(); }).catch(function() { return []; }).then(function(list) {
                        optionData.recipient_addresses[recipientId] = list;
                        recAddrSel.innerHTML = optionsHtml(list, '', false);
                    });
                } else {
                    recAddrSel.innerHTML = '<option value="">—</option>';
                }
            });
        });
    }

    document.getElementById('btn_back_upload').addEventListener('click', showUpload);

    document.getElementById('btn_create_shipments').addEventListener('click', function() {
        var rows = [];
        var trs = document.querySelectorAll('#preview_tbody tr');
        for (var i = 0; i < trs.length; i++) {
            if (i >= previewRows.length) break;
            var r = Object.assign({}, previewRows[i]);
            var tr = trs[i];
            var selSender = tr.querySelector('.row-select-sender');
            var selSenderAddr = tr.querySelector('.row-select-sender-addr');
            var selRecipient = tr.querySelector('.row-select-recipient');
            var selRecipientAddr = tr.querySelector('.row-select-recipient-addr');
            var selService = tr.querySelector('.row-select-service');
            if (selSender) r.sender_id = intVal(selSender.value);
            if (selSenderAddr) r.sender_address_id = intVal(selSenderAddr.value);
            if (selRecipient) r.recipient_id = intVal(selRecipient.value);
            if (selRecipientAddr) r.recipient_address_id = intVal(selRecipientAddr.value);
            if (selService) r.order_service_options = intVal(selService.value);
            var inputs = tr.querySelectorAll('input[data-idx]');
            for (var j = 0; j < inputs.length; j++) {
                var inp = inputs[j];
                var key = inp.getAttribute('data-key');
                if (!key || !r.hasOwnProperty(key)) continue;
                if (key === 'quantity' || key === 'weight' || key === 'length' || key === 'width' || key === 'height' || key === 'declared_value' || key === 'distance_miles' || key === 'price_lb') {
                    r[key] = numVal(inp.value);
                } else if (key === 'manual_tariff') {
                    r[key] = intVal(inp.value);
                } else if (key === 'description') {
                    r[key] = inp.value ? String(inp.value).trim() : '';
                }
            }
            if (r.quantity <= 0) r.quantity = 1;
            if (r.weight < 1) r.weight = 1;
            rows.push(r);
        }

        var validCount = rows.filter(function(r) {
            return r.sender_id && r.sender_address_id && r.recipient_id && r.recipient_address_id && r.order_service_options && r.weight > 0;
        }).length;
        if (validCount === 0) {
            Swal.fire({ icon: 'warning', title: L.error, text: L.no_valid_rows });
            return;
        }
        var hasBlocking = rows.filter(function(r) {
            return r.sender_id && r.sender_address_id && r.recipient_id && r.recipient_address_id && r.order_service_options && r.weight > 0;
        }).some(function(r) { return r.manual_tariff === 0 && r.tariff_error; });
        if (hasBlocking) {
            Swal.fire({ icon: 'error', title: L.error, text: L.cannot_create });
            return;
        }

        var formData = new FormData();
        formData.append('action', 'create');
        formData.append('rows', JSON.stringify(rows));
        document.getElementById('btn_create_shipments').disabled = true;
        fetch('ajax/courier/import_excel_add_courier_ajax.php', { method: 'POST', body: formData })
            .then(function(res) { return res.text().then(function(t) { try { return JSON.parse(t); } catch (e) { return {}; } }); })
            .then(function(data) {
                document.getElementById('btn_create_shipments').disabled = false;
                if (data.success) {
                    var msg = L.created + ': ' + data.created;
                    if (data.failed > 0) msg += ' | ' + L.failed + ': ' + data.failed;
                    msg += '. ' + L.success_redirect;
                    if (data.failed > 0 && data.errors && data.errors.length) msg += '\n' + data.errors.slice(0, 3).join('\n');
                    Swal.fire({ icon: 'success', title: L.process_done, text: msg }).then(function() {
                        window.location.href = 'courier_list.php';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: L.error, text: data.error || '' });
                }
            })
            .catch(function() {
                document.getElementById('btn_create_shipments').disabled = false;
                Swal.fire({ icon: 'error', title: L.error, text: L.conn_error });
            });
    });

    document.getElementById('file').addEventListener('change', function() {
        var fn = this.files[0] ? this.files[0].name : '';
        document.getElementById('file-name').textContent = fn ? (L.file_label ? L.file_label + ': ' : '') + fn : '';
    });
})();
    </script>
</body>
</html>
