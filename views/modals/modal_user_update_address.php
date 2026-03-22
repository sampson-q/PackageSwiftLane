<div class="modal fade" id="userUpdateAddress" tabindex="-1" role="dialog" aria-labelledby="userUpdateAddressLabel" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h4 class="modal-title" id="userUpdateAddressLabel">Update your Address</h4>
            </div>

            <div class="modal-body">
                <form id="force_profile_address_form" autocomplete="off">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Country <span class="text-danger">*</span></label>
                                <select style="width:100%" class="select2 form-control" id="force_country_address" name="country"></select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>State <span class="text-danger">*</span></label>
                                <select style="width:100%" class="select2 form-control" id="force_state_address" name="state" disabled></select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>City <span class="text-danger">*</span></label>
                                <select style="width:100%" class="select2 form-control" id="force_city_address" name="city" disabled></select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Zip Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="force_zip_address" name="postal" placeholder="Zip Code">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group mb-3">
                                <label>Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="force_full_address" name="address" placeholder="Full Address">
                            </div>
                        </div>
                    </div>

                    <div id="force_profile_address_error" class="text-danger mt-2"></div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="btn_force_save_address">Update Address</button>
            </div>
        </div>
    </div>
</div>