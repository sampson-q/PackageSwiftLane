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

							<div class="col-sm-12 col-md-6">

								<div class="input-group">
									<input type="text" name="search" id="search" class="form-control input-sm float-right" placeholder="<?php echo $lang['left21551'] ?>" onkeyup="cdp_load(1);">
									<div class="input-group-append input-sm">
										<!-- type="button": the search already runs on keyup. As a submit
										     this fired the enclosing form, navigating the user off the
										     consolidation to a raw "Invalid CSRF token" JSON page — and
										     pressing Enter in the box did the same. -->
										<button type="button" class="btn btn-info" onclick="cdp_load(1);"><i class="fa fa-search"></i></button>
									</div>

								</div>
							</div><!-- /.col -->

							<?php if (!empty($cdp_show_dg_filter)) { ?>
							<!-- Dangerous-goods filter (air courier consolidation only). A
							     consolidation may only group ONE kind, so this toggles between
							     normal and dangerous-goods packages. consolidate_*.js hides/locks
							     it to the consolidation's own kind on the edit screen (and after
							     the first package is added). -->
							<div class="col-sm-12 col-md-6" id="dg_filter_wrap">
								<div class="btn-group btn-group-toggle float-md-right" data-toggle="buttons" id="dg_filter_group">
									<label class="btn btn-outline-secondary btn-sm active">
										<input type="radio" name="dg_filter" id="dg_filter_normal" value="0" checked> <i class="fa fa-box"></i> Normal goods
									</label>
									<label class="btn btn-outline-danger btn-sm">
										<input type="radio" name="dg_filter" id="dg_filter_dg" value="1"> <i class="fas fa-exclamation-triangle"></i> Dangerous goods
									</label>
								</div>
								<small id="dg_filter_note" class="d-block text-muted text-md-right mt-1" style="display:none;"></small>
							</div><!-- /.col -->
							<?php } ?>
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