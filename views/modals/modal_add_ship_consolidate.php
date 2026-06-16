	<!-- Modal -->
	<div class="modal fade" id="myModalConsolidate" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width: 92vw; width: 92vw;">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-envelope'></i> <?php echo $lang['leftorder148']; ?>
					</h4>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div id="modal_consolidate"></div>
					<form class="form-horizontal" method="post" id="send_email" name="send_email">

						<div class="row mb-3 ml-2">

							<div class="col-sm-12 col-md-5">

								<div class="input-group">
									<input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
									<div class="input-group-append input-sm">
										<button type="submit" class="btn btn-info"><i class="fa fa-search"></i></button>
									</div>

								</div>
							</div><!-- /.col -->

							<div class="col-sm-6 col-md-4">
								<select id="fs_filter_status" class="form-control form-control-sm" onchange="cdp_load(1);">
									<option value="0"><?php echo 'All package statuses'; ?></option>
									<?php
										$fs_status_list = method_exists($core, 'cdp_getStatus') ? $core->cdp_getStatus() : array();
										foreach ($fs_status_list as $fs_st) {
											// Skip statuses the modal already excludes (pickup/delivered/cancelled).
											if (in_array((int)$fs_st->id, [8, 14, 21])) continue;
											echo '<option value="' . (int)$fs_st->id . '">' . htmlspecialchars($fs_st->mod_style) . '</option>';
										}
									?>
								</select>
							</div><!-- /.col -->

							<div class="col-sm-6 col-md-3">
								<select id="fs_per_page" class="form-control form-control-sm" onchange="cdp_load(1);">
									<option value="50">50 rows</option>
									<option value="100">100 rows</option>
								</select>
							</div><!-- /.col -->
						</div>

						<div class="outer_div"></div><!-- Datos ajax Final -->

				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"> <?php echo $lang['modal-text5']; ?>
					</button>
				</div>
				</form>
			</div>
		</div>
	</div>