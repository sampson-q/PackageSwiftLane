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
	<?php $zonerow = $core->cdp_getZone(); ?>

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

				<form class="form-horizontal form-material" id="save_config" name="save_config" method="post">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
					
					<section>
						<div class="row">
							<div class="col-md-4">
								<div class="form-group">
									<label for="firstName1"><?php echo $lang['tools-config1'] ?></label>
									<input type="text" class="form-control required" name="site_name" id="site_name" title="<?php echo $lang['tools-config2'] ?>" data-toggle="tooltip" value="<?php echo $core->site_name; ?>" placeholder="<?php echo $lang['tools-config1'] ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config3'] ?></label>
									<input type="text" class="form-control required" name="site_url" id="site_url" title="<?php echo $lang['tools-config4'] ?>" data-toggle="tooltip" value="<?php echo $core->site_url; ?>" placeholder="<?php echo $lang['tools-config3'] ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config5'] ?></label>
									<input type="text" class="form-control required" name="site_email" id="site_email" title="<?php echo $lang['tools-config6'] ?>" data-toggle="tooltip" value="<?php echo $core->site_email; ?>" placeholder="<?php echo $lang['tools-config5'] ?>">
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-4">
								<div class="form-group">
									<label for="firstName1"><?php echo $lang['tools-config7'] ?></label>
									<input type="text" class="form-control required" name="c_nit" id="c_nit" value="<?php echo $core->c_nit; ?>" placeholder="<?php echo $lang['tools-config7'] ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config8'] ?></label>
									<input type="number" class="form-control required" name="c_phone" id="c_phone" value="<?php echo $core->c_phone; ?>" placeholder="<?php echo $lang['tools-config8'] ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['user_manage9'] ?></label>
									<input type="number" class="form-control required" name="cell_phone" id="cell_phone" value="<?php echo $core->cell_phone; ?>" placeholder="<?php echo $lang['tools-config9'] ?>">
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['left801'] ?></label>
									<textarea class="form-control required" name="c_address" id="c_address" placeholder="<?php echo $lang['tools-config9'] ?>"><?php echo $core->c_address; ?></textarea>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['left802'] ?></label>
									<textarea class="form-control required" name="locker_address" id="locker_address" placeholder="Addres virtual locker"><?php echo $core->locker_address; ?></textarea>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-4">
								<div class="form-group">
									<label for="firstName1"><?php echo $lang['tools-config11'] ?></label>
									<input type="text" class="form-control required" name="c_country" id="c_country" value="<?php echo $core->c_country; ?>" placeholder="<?php echo $lang['tools-config11'] ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config12'] ?></label>
									<input type="text" class="form-control required" name="c_city" id="c_city" value="<?php echo $core->c_city; ?>" placeholder="<?php echo $lang['tools-config12'] ?>">
								</div>
							</div>
							<div class="col-md-4">
								<div class="form-group">
									<label for="lastName1"><?php echo $lang['tools-config13'] ?></label>
									<input type="text" class="form-control required" name="c_postal" id="c_postal" value="<?php echo $core->c_postal; ?>" placeholder="<?php echo $lang['tools-config13'] ?>">
								</div>
							</div>
						</div>
						<hr />
						<h4 class="card-title"><b><?php echo $lang['left803'] ?></b></h4>

						<div class="row">
							<div class="col-md-3" style="display:none">
								<div class="form-group">
									<div class="btn-group" data-toggle="buttons">
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio4" name="reg_verify" value="1" <?php cdp_getChecked($core->reg_verify, 1); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio4"> <?php echo $lang['tools-config14'] ?></label>
											</div>
										</label>
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio5" name="reg_verify" value="0" <?php cdp_getChecked($core->reg_verify, 0); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio5"> <?php echo $lang['tools-config15'] ?></label>
											</div>
										</label>
									</div>
									<div class="note"><?php echo $lang['tools-config16'] ?> <i class="icon-exclamation-sign  tooltip" data-title="<?php echo $lang['tools-config17'] ?>"></i> </div>
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<div class="btn-group" data-toggle="buttons">
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio4" name="auto_verify" value="1" <?php cdp_getChecked($core->auto_verify, 1); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio4"> <?php echo $lang['tools-config14'] ?></label>
											</div>
										</label>
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio5" name="auto_verify" value="0" <?php cdp_getChecked($core->auto_verify, 0); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio5"> <?php echo $lang['tools-config15'] ?></label>
											</div>
										</label>
									</div>
									<div class="note"><?php echo $lang['tools-config18'] ?> <i class="icon-exclamation-sign  tooltip" data-title="<?php echo $lang['tools-config19'] ?>"></i></div>
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<div class="btn-group" data-toggle="buttons">
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio4" name="reg_allowed" value="1" <?php cdp_getChecked($core->reg_allowed, 1); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio4"> <?php echo $lang['tools-config14'] ?></label>
											</div>
										</label>
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio5" name="reg_allowed" value="0" <?php cdp_getChecked($core->reg_allowed, 0); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio5"> <?php echo $lang['tools-config15'] ?></label>
											</div>
										</label>
									</div>
									<div class="note"><?php echo $lang['tools-config20'] ?> <i class="icon-exclamation-sign  tooltip" data-title="<?php echo $lang['tools-config21'] ?>"></i></div>
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<div class="btn-group" data-toggle="buttons">
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio4" name="notify_admin" value="1" <?php cdp_getChecked($core->notify_admin, 1); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio4"> <?php echo $lang['tools-config14'] ?></label>
											</div>
										</label>
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio5" name="notify_admin" value="0" <?php cdp_getChecked($core->notify_admin, 0); ?> class="custom-control-input">
												<label class="custom-control-label" for="customRadio5"> <?php echo $lang['tools-config15'] ?></label>
											</div>
										</label>
									</div>
									<div class="note"><?php echo $lang['tools-config22'] ?> <i class="icon-exclamation-sign  tooltip" data-title="<?php echo $lang['tools-config23'] ?>"></i></div>
								</div>
							</div>
						</div>
						<hr />

						<h4 class="card-title"><b><?php echo $lang['left1129'] ?></b></h4>

						<div class="row">
							<div class="col-md-12">
								
								<div class="form-group">

									<div class="btn-group" data-toggle="buttons">
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio4" name="code_number_locker" value="1" <?php cdp_getCheckedlocker($core->code_number_locker, 1); ?> class="custom-control-input required">
												<label class="custom-control-label" for="customRadio4"> <?php echo $lang['tools-config90'] ?></label>
											</div>
										</label>
										<label class="btn">
											<div class="custom-control custom-radio">
												<input type="radio" id="customRadio5" name="code_number_locker" value="2" <?php cdp_getCheckedlocker($core->code_number_locker, 2); ?> class="custom-control-input required">
												<label class="custom-control-label" for="customRadio5"> <?php echo $lang['leftorder14442'] ?></label>
											</div>
										</label>
									</div>

								</div>
							</div> 

							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><i class="mdi mdi-alert-box"></i> <?php echo $lang['tools-config88'] ?></label>
									<input type="text" data-toggle="tooltip" class="form-control required" name="digit_random_locker" id="digit_random_locker" value="<?php echo $core->digit_random_locker; ?>" placeholder="Digits random locker">
								</div> 
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="lastName1"><i class="mdi mdi-alert-circle"></i> <?php echo $lang['left1130'] ?></label>
									<input type="text" data-toggle="tooltip" class="form-control required" name="prefix_locker" id="prefix_locker" value="<?php echo $core->prefix_locker; ?>" placeholder="Prefix locker">
								</div>
							</div>
						</div>


						<div class="row">
							<div class="col-md-4" style="display:none">
								<div class="form-group">
									<label for="firstName1"><?php echo $lang['tools-config99'] ?></label>
									<input type="text" class="form-control" name="user_perpage" id="user_perpage" value="<?php echo $core->user_perpage; ?>" placeholder="Items Per Page">
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-4" style="display:none">
								<label class="input"><i class="icon-append icon-exclamation-sign  tooltip" data-title="<?php echo $lang['tools-config25'] ?>"></i>
									<input type="text" name="user_limit" value="<?php echo $core->user_limit; ?>" placeholder="<?php echo $lang['tools-config24'] ?>">
								</label>
								<div class="note"><?php echo $lang['tools-config24'] ?></div>
							</div>

						</div>


						<hr />
					</section>
					<div class="form-group">
						<div class="col-sm-12">
							<button type="submit" class="btn btn-danger btn-confirmation" name="save_data" id="save_data"><?php echo $lang['tools-config56'] ?> <span><i class="icon-ok"></i></span></button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<!-- Column -->
	</div>

</div>