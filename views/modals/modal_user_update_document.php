<div class="modal fade" id="userUpdateDocument" tabindex="-1" role="dialog" aria-labelledby="userUpdateDocumentLabel" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h4 class="modal-title" id="userUpdateDocumentLabel">
                    <?php echo $lang['leftorder164']; ?>
                </h4>
            </div>

            <div class="modal-body">
                <form id="force_profile_document_form" enctype="multipart/form-data" autocomplete="off">

                    <div class="row">

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <?php echo $lang['leftorder164']; ?> <span class="text-danger">*</span>
                                </label>
                                <div class="form-icon position-relative">
                                    <i data-feather="list" class="fea icon-sm icons"></i>
                                    <select class="custom-select form-control ps-5" id="force_document_type" name="document_type">
                                        <option value=""><?php echo $lang['leftorder164']; ?></option>
                                        <option value="DNI"><?php echo $lang['leftorder165']; ?></option>
                                        <option value="RIC"><?php echo $lang['leftorder166']; ?></option>
                                        <option value="CI"><?php echo $lang['leftorder167']; ?></option>
                                        <option value="CIE"><?php echo $lang['leftorder168']; ?></option>
                                        <option value="CIN"><?php echo $lang['leftorder169']; ?></option>
                                        <option value="CC"><?php echo $lang['leftorder171']; ?></option>
                                        <option value="TI"><?php echo $lang['leftorder172']; ?></option>
                                        <option value="CE"><?php echo $lang['leftorder173']; ?></option>
                                        <option value="PSP"><?php echo $lang['leftorder174']; ?></option>
                                        <option value="NIT"><?php echo $lang['leftorder1745']; ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">
                                    <?php echo $lang['leftorder175']; ?> <span class="text-danger">*</span>
                                </label>
                                <div class="form-icon position-relative">
                                    <i data-feather="more-horizontal" class="fea icon-sm icons"></i>
                                    <input type="text"
                                           class="form-control ps-5"
                                           id="force_document_number"
                                           name="document_number"
                                           placeholder="<?php echo $lang['leftorder175']; ?>">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">
                                    Upload Document (Optional)
                                </label>

                                <div class="form-icon position-relative">
                                    <i data-feather="image" class="fea icon-sm icons"></i>
                                    <input type="file"
                                           class="form-control ps-5"
                                           id="force_document_photo"
                                           name="document_photo"
                                           accept="image/*">
                                </div>

                                <small class="text-muted">
                                    Scanned image of your document.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div id="force_profile_document_error" class="text-danger mt-2"></div>

                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="btn_force_save_document">
                    Update Document
                </button>
            </div>

        </div>
    </div>
</div>