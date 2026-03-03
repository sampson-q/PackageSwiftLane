	<!-- Modal -->
	<div class="modal fade" id="myModalAddUser" role="dialog" aria-labelledby="modal_add_user_title">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title"><?php echo $lang['modal-text1'] ?></h4>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" method="post" id="add_user_from_modal_shipments" name="add_user_from_modal_shipments">

						<input type="hidden" id="type_user" name="type_user">

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="emailAddress1"><?php echo $lang['user_manage6'] ?></label>
									<input type="text" class="form-control" name="fname" id="fname" placeholder="<?php echo $lang['user_manage6'] ?>">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNumber1"><?php echo $lang['user_manage7'] ?></label>
									<input type="text" class="form-control" name="lname" id="lname" placeholder="<?php echo $lang['user_manage7'] ?>">
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="emailAddress1"><?php echo $lang['user_manage5'] ?></label>
									<input type="text" class="form-control" id="email" name="email" placeholder="<?php echo $lang['user_manage5'] ?>">
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNumber1"><?php echo $lang['user_manage9'] ?></label>
									<input type="tel" class="form-control" name="phone_custom" id="phone_custom">

									<span id="valid-msg-sender" class="hide"></span>
									<div id="error-msg-sender" class="hide text-danger"></div>
								</div>
							</div>
						</div>

						<?php if ($userData->userlevel == 9 || $userData->userlevel == 2) { ?>

							<br>
							<label class="custom-control custom-checkbox" style="font-size: 18px; padding-left: 0px">
								<input type="checkbox" class="custom-control-input" name="register_customer_to_user" id="register_customer_to_user" value="1">
								<?php echo $lang['recipient_to_user'] ?>

								<span class="custom-control-indicator"></span>
							</label>
							<br>
							<div class="row d-none" id="show_hide_user_inputs">
								<div class="col-md-6">
									<div class="form-group">
										<label><?php echo $lang['user_manage3'] ?></label>
										<input type="text" class="form-control" name="username" id="username" placeholder="<?php echo $lang['user_manage3'] ?>">

									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label><?php echo $lang['user_manage32'] ?></label>
										<input type="password" class="form-control" id="password" name="password" placeholder="<?php echo $lang['user_manage32'] ?>" autocomplete="on">
									</div>
								</div>
								<br>
							</div>

						<?php
						}
						?>

						<hr>
						<h4><?php echo $lang['laddress'] ?> </h4>

						<div class="row">
							<div class="col-md-4">
								<div class="form-group">
									<label><?php echo $lang['leftorder318'] ?></label>
									<select style="width: 100% !important;" class="select2 form-control" name="country_modal_user" id="country_modal_user">
									</select>
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<label class=""><?php echo $lang['leftorder319'] ?></label>
									<select style="width: 100% !important;" disabled class="select2 form-control" id="state_modal_user" name="state_modal_user">
									</select>
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<label class=""><?php echo $lang['leftorder320'] ?></label>
									<select style="width: 100% !important;" disabled class="select2 form-control" id="city_modal_user" name="city_modal_user">
									</select>
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<label for="phoneNumber1"><?php echo $lang['user_manage14'] ?></label>
									<input type="text" class="form-control" name="postal_modal_user" id="postal_modal_user" placeholder="<?php echo $lang['user_manage14'] ?>">
								</div>
							</div>

							<div class="col-md-4">
								<div class="form-group">
									<label for="phoneNumber1"><?php echo $lang['user_manage10'] ?></label>
									<input type="text" class="form-control" name="address_modal_user" id="address_modal_user" placeholder="<?php echo $lang['user_manage10'] ?>">
								</div>
							</div>
						</div>

						<input type="hidden" name="total_address" id="total_address" value="1" />
						<input type="hidden" name="phone" id="phone" />

						<?php
						if ($core->code_number_locker == 1) {
						?>
							<div class="form-group col-md-6" style="display:none;">
								<label for="inputcom" class="control-label col-form-label"><?php echo $lang['add-title24'] ?></label>
								<div class="input-group mb-3">
									<input type="number" class="form-control" name="locker" id="locker" value="<?php echo $lockerauto; ?>" onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>');">
									<!-- <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>"> -->
								</div>
							</div>
						<?php } elseif ($core->code_number_locker == 2) {

						?>
							<div class="form-group col-md-6" style="display:none;">
								<label for="inputcom" class="control-label col-form-label"><?php echo $lang['leftorder14442'] ?></label>
								<div class="input-group mb-3">
									<input type="number" class="form-control" name="locker" id="locker" value="<?php print_r(cdp_generarCodigo('' . $core->digit_random_locker . '')); ?>" onchange="cdp_validateLockerNumber(this.value, '<?php echo $verifylocker; ?>');">
									<!-- <input type="hidden" name="order_no_main" id="order_no_main" value="<?php echo $lockerauto; ?>"> -->
								</div>
							</div>
						<?php } ?>

				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-danger" id="save_data_user"><?php echo $lang['modal-text6'] ?></button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang['modal-text5'] ?></button>
				</div>
				</form>
			</div>
		</div>
	</div>