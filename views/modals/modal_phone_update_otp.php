<div class="modal fade" id="userUpdatePhoneOtp" tabindex="-1" role="dialog" aria-labelledby="userUpdatePhoneOtpLabel" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h4 class="modal-title" id="userUpdatePhoneOtpLabel">Verify WhatsApp code</h4>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label for="force_phone_otp_code" class="form-label">OTP Code <span class="text-danger">*</span></label>
                    <input type="text" id="force_phone_otp_code" maxlength="6" class="form-control" placeholder="Enter 6-digit code">
                </div>

                <div id="force_profile_phone_otp_error" class="text-danger mt-2"></div>

                <div class="mt-3">
                    <button type="button" class="btn btn-link p-0" id="btn_force_resend_phone_otp">Resend code</button>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="btn_force_verify_phone_otp">Verify</button>
            </div>
        </div>
    </div>
</div>