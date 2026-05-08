<!-- ============================================================== -->
<!-- Right Part contents-->
<!-- ============================================================== -->
<div class="right-part mail-list bg-white">
	<!-- Action part -->
	<!-- Button group part -->
	<div class="bg-light">
		<div class="row justify-content-center">
			<div class="col-md-12">
				<div class="row">
					<div class="col-12">
						<div id="loader" style="display:none"></div>
						<div id="resultados_ajax"></div>
					</div>
				</div>
			</div>
		</div>
	</div> 
	<!-- Action part -->

	<div class="row">
		<!-- Column -->
		<div class="col-12">
			<div class="card-body">

				<div class="d-md-flex align-items-center">
                    <div>
                        <h3 class="card-title"><span><?php echo $lang['tools-config57'] ?></span></h3>
                    </div>
                </div>
                <div><hr><br></div>

				<form class="form-horizontal form-material" id="edit_avatar_form" name="edit_avatar_form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
					<section>


						<h4 class="card-title"><b><?php echo $lang['left1131'] ?></b></h4>

						<div class="row">

							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config27'] ?>, <?php echo $lang['left805'] ?> </label>
									<input class="form-control" name="logo" id="logo" type="file" />
									<div class="image-preview">
				                        <img id="logo-preview" src="<?php echo $core->logo; ?>" alt="Logo Preview" />
				                    </div>
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config97'] ?></label>
									<input type="text" class="form-control" name="thumb_w" id="thumb_w" value="<?php echo $core->thumb_w; ?>" placeholder="Thumbnail Width">
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config98'] ?></label>
									<input type="text" data-toggle="tooltip" class="form-control" name="thumb_h" id="thumb_h" value="<?php echo $core->thumb_h; ?>" placeholder="Thumbnail Height">
								</div>
							</div>
						</div>

						<div class="row">

							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config27web'] ?>, <?php echo $lang['left805'] ?> </label>
									<input class="form-control" name="logo_web" id="logo_web" type="file" />
									<div class="image-preview">
				                        <img id="logo-web-preview" src="<?php echo $core->logo_web; ?>" alt="Logo Preview" />
				                    </div>
								</div>
							</div>


							<div class="col-md-3">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config97'] ?></label>
									<input type="text" class="form-control" name="thumb_web" id="thumb_web" value="<?php echo $core->thumb_web; ?>" placeholder="Thumbnail Width">
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config98'] ?></label>
									<input type="text" data-toggle="tooltip" class="form-control" name="thumb_hweb" id="thumb_hweb" value="<?php echo $core->thumb_hweb ; ?>" placeholder="Thumbnail Height">
								</div>
							</div>


						</div>

						<div class="row">

							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config26'] ?>, <?php echo $lang['left805'] ?> 45 x 45</label>
									<input class="form-control" name="favicon" id="favicon" type="file" />
									<div class="image-preview">
				                        <img id="favicon-preview" src="<?php echo $core->favicon; ?>" alt="Logo Preview" />
				                    </div>
								</div>
							</div>

						</div>


						<hr />
					</section>
					<div class="form-group">
						<div class="col-sm-12">
							<button type="submit" class="btn btn-danger btn-confirmation" name="save_data" id="save_data"><?php echo $lang['tools-config56'] ?> <span><i class="icon-ok"></i></span></button>
						</div>
					</div>

					<input name="id" id="id" type="hidden" value="<?php echo $core->id; ?>" />
				</form>
			</div>
		</div>
		<!-- Column -->
	</div>

</div>