	<!-- Modal -->
	<div class="modal fade" id="charges_list" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-envelope'></i><?php echo $lang['modal-text12'] ?></h4>
					<div class="pull-right">
						<button class="btn btn-primary" data-toggle="modal" data-target="#charges_add" data-id="1752"><i class="fa fa-plus"></i>
							<div class="hidden-xs" style="display:inline-block"><?php echo $lang['modal-text11'] ?></div>
						</button>
					</div>
				</div>
				<div class="modal-body">

					<div class="resultados_ajax_charges_list"></div>
					<input type="hidden" name="order_id" id="order_id" value="0">


					<div class="resultados_ajax_charges_add_results"></div>

				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang['modal-text5'] ?></button>
				</div>
				</form>
			</div>
		</div>
	</div>