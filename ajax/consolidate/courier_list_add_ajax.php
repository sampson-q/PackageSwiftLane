<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************



require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_consolidate_list');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();

$search = cdp_sanitize($_REQUEST['search']);

$sWhere = "";

if ($search != null) {
	$sWhere .= " and  CONCAT(a.order_prefix,a.order_no) LIKE '%" . $search . "%'";
}



// pagination variables
$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
$per_page = 50; // default 50 rows
$adjacents  = 4; //gap between pages after number of adjacents
$offset = ($page - 1) * $per_page;


$sql = "SELECT a.volumetric_percentage, a.is_pickup,  a.total_order, a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.receiver_id, a.order_courier, a.is_consolidate, a.order_pay_mode, a.status_courier, a.driver_id, a.order_service_options,  b.mod_style, b.color FROM
			 cdb_add_order as a
			 INNER JOIN cdb_styles as b ON a.status_courier = b.id
			 $sWhere
			  and a.status_courier = 10 and a.is_consolidate = 0
			 order by a.order_id desc";


$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql . " limit $offset, $per_page");
$data = $db->cdp_registros();

$total_pages = ceil($numrows / $per_page);


if ($numrows > 0) { ?>
	<div class="table-responsive">
		<div id="selection_bar" style="display:none; margin-bottom: 10px;">
			<button type="button" id="add_checked_packages" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> Add Selected</button>
			<span id="selection_count" class="ml-3" style="font-weight: bold; color: #333;"></span>
		</div>

		<table id="zero_config" class="table table-condensed custom-table-checkbox">
			<thead>
				<tr>
					<th style="width: 40px;">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input sl-all" id="select_all_packages">
							<label class="custom-control-label" for="select_all_packages"></label>
						</div>
					</th>
					<th><b><?php echo 'Sender Name' ?></b></th>
					<th><b><?php echo $lang['ltracking'] ?></b></th>
					<th><b><?php echo 'Contents' ?></b></th>
					<th><b><?php echo $lang['left215'] ?></b></th>
					<th><b><?php echo $lang['lstatusshipment'] ?></b></th>
				</tr>
			</thead>
			<tbody id="projects-tbl">


				<?php if (!$data) { ?>
					<tr>
						<td colspan="7">
							<?php echo "
				<i align='center' class='display-3 text-warning d-block'><img src='assets/images/alert/ohh_shipment.png' width='150' /></i>
				", false; ?>
						</td>
					</tr>
				<?php } else { ?>

					<?php

					$count = 0;
					foreach ($data as $row) {
                        // Get package total price.
                        $db->cdp_query("SELECT user_id, sender_id FROM cdb_add_order WHERE order_id='" . $row->order_id . "'");
                        $order_details = $db->cdp_registro();
                        
                        // Get order item description.
                        $db->cdp_query("SELECT order_item_description FROM cdb_add_order_item WHERE order_id = '" . $row->order_id . "'");
                        $description = $db->cdp_registro();
                        
                        $db->cdp_query("SELECT order_item_quantity FROM cdb_add_order_item WHERE order_id = '" . $row->order_id . "'");
                        $quantity = $db->cdp_registro();

                        // Get sender info.
                        $db->cdp_query("SELECT * FROM cdb_users WHERE id='" . $order_details->sender_id . "'");
                        $sender = $db->cdp_registro();

						$db->cdp_query("SELECT IFNULL(sum(order_item_weight), 0) as weight FROM cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_weight = $db->cdp_registro();

						$weight = number_format($order_weight->weight, 2, '.', '');

						$db->cdp_query("SELECT IFNULL(sum(order_item_length), 0) as length from  cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_length = $db->cdp_registro();

						$db->cdp_query("SELECT IFNULL(sum(order_item_height), 0) as height from cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_height = $db->cdp_registro();

						$db->cdp_query("SELECT IFNULL(sum(order_item_width), 0) as width from cdb_add_order_item where order_id= '" . $row->order_id . "'");
						$order_width = $db->cdp_registro();

						$length = $order_length->length;
						$width = $order_width->width;
						$height = $order_height->height;

						$total_metric = $length * $width * $height / $row->volumetric_percentage;

						$db->cdp_query("SELECT * FROM cdb_styles where id= '14'");
						$status_style_pickup = $db->cdp_registro();

						$tracking = $row->order_prefix . $row->order_no;

					?>
						<tr class="card-hover" id="tb_row_id_<?php echo $row->order_id; ?>"
							data-order-id="<?php echo $row->order_id; ?>"
							data-total-metric="<?php echo $total_metric; ?>"
							data-weight="<?php echo $weight; ?>"
							data-length="<?php echo $length; ?>"
							data-width="<?php echo $width; ?>"
							data-height="<?php echo $height; ?>"
							data-tracking="<?php echo $tracking; ?>"
							data-order-no="<?php echo $row->order_no; ?>"
							data-order-prefix="<?php echo $row->order_prefix; ?>"
                            data-sender="<?php echo $sender->fname . ' ' . $sender->lname; ?>"
                            data-description="<?php echo $description->order_item_description; ?>"
                            data-quantity="<?php echo $quantity->order_item_quantity; ?>"
                            data-total-order="<?php echo cdb_money_format($row->total_order); ?>"
                            data-total-order-raw="<?php echo $row->total_order; ?>"
                            >

								<td style="width: 40px;">
									<div class="custom-control custom-checkbox">
										<input type="checkbox" class="custom-control-input pkg-checkbox" value="<?php echo $row->order_id; ?>" id="pkg_<?php echo $row->order_id; ?>">
										<label class="custom-control-label" for="pkg_<?php echo $row->order_id; ?>"></label>
									</div>
								</td>
							<td><?php echo $sender->fname . ' ' . $sender->lname; ?></td>

							<td><?php echo $row->order_prefix . $row->order_no; ?></td>
							
                            <td><?php echo $description->order_item_description; ?></td>

							<td><?php echo $weight; ?></td>

							<input type="hidden" id="total_ship_<?php echo $row->order_id; ?>" value="<?php echo cdb_money_format($row->total_order); ?>">

							<td>

								<span style="background: <?php echo $row->color; ?>;" class="label label-large"><?php echo $row->mod_style; ?></span>
								<br>

								<?php
								if ($row->is_pickup == true) { ?>
									<span style="background: <?php echo $status_style_pickup->color; ?>;" class="label label-large"><?php echo $status_style_pickup->mod_style; ?></span>
								<?php
								}
								?>
							</td>

							<td class="text-right">
								<button type="button" name="add_row" id="add_row"
									onclick="cdp_add_item('<?php echo $row->order_id; ?>','<?php echo $total_metric; ?>', '<?php echo $weight; ?>', '<?php echo $length; ?>', '<?php echo $width; ?>', '<?php echo $height; ?>', '<?php echo $tracking; ?>', '<?php echo $row->order_no; ?>','<?php echo $row->order_prefix; ?>', '<?php echo $sender->fname . ' ' . $sender->lname; ?>', '<?php echo $description->order_item_description; ?>', '<?php echo cdb_money_format(($row->total_order)); ?>', '<?php echo $quantity->order_item_quantity; ?>', '<?php echo $row->total_order; ?>');
									$('#tb_row_id_<?php echo $row->order_id; ?>').remove();"
									class="btn btn-outline-success btn-sm add_row">
									<i class="fa fa-plus"></i>
								</button>
							</td>
						</tr>
					<?php $count++;
					} ?>

				<?php } ?>
			</tbody>

		</table>

		<div class="pull-right">
			<?php echo cdp_paginate($page, $total_pages, $adjacents, $lang, ''); ?>
		</div>
		<div class="clearfix"></div>

		<script>
			var count = 0;

			function updateSelectionBar() {
				var $checkboxes = $('.pkg-checkbox:checked');
				var checkedCount = $checkboxes.length;
				if (checkedCount > 0) {
					$('#selection_bar').show();
					$('#selection_count').html('Select: ' + checkedCount);
				} else {
					$('#selection_bar').hide();
				}
			}

			// Row click to toggle checkbox
			$('#projects-tbl tr').not(':has(img)').on('click', function(e) {
				if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') return; // Don't toggle if clicking the checkbox itself
				var checkbox = $(this).find('.pkg-checkbox');
				checkbox.prop('checked', !checkbox.is(':checked')).trigger('change');
			});

			$(".sl-all").on('click', function() {
				$('.custom-table-checkbox input:checkbox').not(this).prop('checked', this.checked);
				$('.pkg-checkbox').trigger('change');
			});

			$('.pkg-checkbox').on('change', function() {
				if ($(this).is(':checked')) {
					$(this).parents('tr').css('background', '#fff8e1');
				} else {
					$(this).parents('tr').css('background', '');
				}
				updateSelectionBar();
			});

			$(document).ready(function() {
				updateSelectionBar();
			});
		</script>

		<script>
			$("#send_checkbox_status").submit(function(event) {

				$('#guardar_datos').attr("disabled", true);

				var parametros = $(this).serialize();
				var checked_data = new Array();
				$('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function() {
					checked_data.push($(this).val());
				});

				var status = $('#status_courier_modal').val();

				$.ajax({
					type: "GET",
					url: './ajax/courier/courier_update_multiple_ajax.php?status=' + status,

					data: {
						'checked_data': JSON.stringify(checked_data)
					},
					beforeSend: function(objeto) {},
					success: function(datos) {
						$("#resultados_ajax").html(datos);
						$('#guardar_datos').attr("disabled", false);
						$('#modalCheckboxStatus').modal('hide');


						cdp_load(1);

						$('#div-actions-checked').addClass('hide');
						$('#countChecked').addClass('hide');
						$('html, body').animate({
							scrollTop: 0
						}, 600);


					}
				});
				event.preventDefault();

			})
		</script>

		<script>
			//cdp_eliminar
			function cdp_printMultipleLabel() {

				var checked_data = new Array();
				$('.custom-table-checkbox').find('tr > td:first-child').find('input[type=checkbox]:checked').each(function() {
					checked_data.push($(this).val());
				});

				var name = $(this).attr('data-rel');
				new Messi('<b></i>' + message_print_confirm2 + '</b>', {
					title: message_print_confirm1,
					titleClass: '',
					modal: true,
					closeButton: true,
					buttons: [{
						id: 0,
						label: message_print_confirm3,
						class: '',
						val: 'Y'
					}],
					callback: function(val) {

						if (val === 'Y') {

							window.open('print_label_ship_multiple.php?data=' + JSON.stringify(checked_data), "_blank");

						}
					}

				});
			}
		</script>


	</div>
<?php } else { ?>
	<div class="text-center">
		<h4>There's no package ready for consolidation</h4>
	</div>
<?php } ?>