<!-- ============================================================== -->
<!-- Right Part contents-->
<!-- ============================================================== -->
<div class="right-part mail-list bg-white">

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
                        <h3 class="card-title"><span><?php echo $lang['metaseo2'] ?></span></h3>
                    </div>
                </div>
                <div><hr><br></div>

				<form class="form-horizontal form-material" id="save_seo_config" name="save_seo_config" method="post">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

					<p class="text-muted" style="color: red; font-size: small;">
					    <?php echo $lang['metaseo19'] ?>
					</p>
					<div class="form-group">
						<label for="meta_description"><?php echo $lang['metaseo3'] ?></label>
						<input type="text" class="form-control" id="meta_description" name="meta_description" value="<?php echo $core->meta_description; ?>" placeholder="<?php echo $lang['metaseo4'] ?>" >
					</div>

					<!-- Keywords (palabras clave relacionadas) - Opcional -->
					<div class="form-group">
						<label for="meta_keywords"><?php echo $lang['metaseo5'] ?></label>
						<input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?php echo $core->meta_keywords; ?>" placeholder="<?php echo $lang['metaseo6'] ?>">
					</div>

					<p class="text-muted" style="color: red; font-size: small;">
					    <?php echo $lang['metaseo7'] ?>
					</p>
					<div class="form-group">
						<label for="og_title"><?php echo $lang['metaseo8'] ?></label>
						<input type="text" class="form-control" id="og_title" name="og_title" value="<?php echo $core->og_title; ?>"  placeholder="<?php echo $lang['metaseo9'] ?>" >
					</div>
					<div class="form-group">
						<label for="og_description"><?php echo $lang['metaseo10'] ?></label>
						<input type="text" class="form-control" id="og_description" name="og_description" value="<?php echo $core->og_description; ?>"  placeholder="<?php echo $lang['metaseo11'] ?>" >
					</div>
					<div class="form-group">
						<label for="og_type"><?php echo $lang['metaseo12'] ?></label>
						<input type="text" class="form-control" id="og_type" name="og_type" value="<?php echo $core->og_type; ?>"  placeholder="<?php echo $lang['metaseo13'] ?>" >
					</div>
					<div class="form-group">
						<label for="og_url"><?php echo $lang['metaseo14'] ?></label>
						<input type="url" class="form-control" id="og_url" name="og_url" value="<?php echo $core->og_url; ?>"  placeholder="<?php echo $lang['metaseo15'] ?>">
					</div>
					<div class="form-group">
						<label for="og_image"><?php echo $lang['metaseo16'] ?></label>
						<input type="url" class="form-control" id="og_image" name="og_image" value="<?php echo $core->og_image; ?>"  placeholder="<?php echo $lang['metaseo17'] ?>">
					</div>

					<!-- Submit Button -->
					<div class="form-group">
						<button type="submit" class="btn btn-danger btn-confirmation" name="dosubmit"><?php echo $lang['metaseo18'] ?></button>
					</div>
				</form>

			</div>
		</div>
		<!-- Column -->
	</div>


</div>