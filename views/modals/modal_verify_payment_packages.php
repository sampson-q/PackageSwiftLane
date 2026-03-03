	<!-- Modal -->
	<div class="modal fade" id="detail_payment_packages" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-envelope'></i><?php echo $lang['modal-text31'] ?></h4>

				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="send_payment" name="send_payment">

						<div class="resultados_ajax_payment_data"></div>
						<input type="hidden" name="order_id_confirm_payment" id="order_id_confirm_payment">
						<input type="hidden" name="customer_id_confirm_payment" id="customer_id_confirm_payment">


						<div class="resultados_ajax_charges_add_results"></div>

				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-danger" id="save_payment"><?php echo $lang['modal-text32'] ?></button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang['modal-text5'] ?></button>

				</div>
				</form>
			</div>
		</div>
	</div>