	<!-- Modal -->
	<div class="modal fade" id="charges_edit" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-envelope'></i> <?php echo $lang['modal-text22'] ?></h4>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="edit_charges" name="edit_charges">
						<div class="resultados_ajax_add_modal_edit"></div>

				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang['modal-text5'] ?></button>
					<button type="submit" class="btn btn-danger" id="guardar_datos"><?php echo $lang['modal-text6'] ?></button>
				</div>
				</form>
			</div>
		</div>
	</div>