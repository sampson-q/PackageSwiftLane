<div class="modal fade" id="userUpdatePhone" tabindex="-1" role="dialog" aria-labelledby="userUpdatePhoneLabel" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h4 class="modal-title" id="userUpdatePhoneLabel">Update your WhatsApp number</h4>
            </div>

            <div class="modal-body">
                <form id="force_profile_phone_form" autocomplete="off">
                    <div class="mb-3">
                        <label for="force_phone_custom" class="form-label">WhatsApp Number <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="tel" class="form-control iti__tel-input" name="phone_custom" id="force_phone_custom" autocomplete="off" placeholder="Enter WhatsApp number">
                        </div>
                        <span id="force_phone_valid_msg" class="hide text-success"></span>
                        <div id="force_phone_error_msg" class="hide text-danger"></div>
                    </div>

                    <input type="hidden" name="phone" id="force_phone" />
                    <div id="force_profile_phone_error" class="text-danger mt-2"></div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="btn_force_save_phone">Continue</button>
            </div>
        </div>
    </div>
</div>