"use strict";

/* =========================================================================
   CAMERA CAPTURE MODULE — Complete Implementation
   
   File: dataJs/courier_add_camera.js
   
   This module handles all camera capture functionality for package photos.
   Include this file in courier_add.php before the closing body tag.
   
   ========================================================================= */

// Global variables for camera capture
var cameraStream = null;
var capturedImageBlob = null;
var capturedImages = []; // Store captured image blobs
var captureCanvasContext = null;

/**
 * Initialize camera capture functionality
 * Call this from the main init() function in courier_add.js
 */
function initializeCameraCapture() {
  const cameraBtn = document.getElementById("openCameraCapture");
  const capturePhotoBtn = document.getElementById("capturePhotoBtn");
  const clearCapturedBtn = document.getElementById("clearCapturedBtn");
  const addCapturedPhotoBtn = document.getElementById("addCapturedPhotoBtn");
  const cameraModal = document.getElementById("cameraCaptureModal");

  if (cameraBtn) {
    cameraBtn.addEventListener("click", function() {
      openCameraModal();
    });
  }

  if (capturePhotoBtn) {
    capturePhotoBtn.addEventListener("click", capturePhoto);
  }

  if (clearCapturedBtn) {
    clearCapturedBtn.addEventListener("click", clearCapturedImage);
  }

  if (addCapturedPhotoBtn) {
    addCapturedPhotoBtn.addEventListener("click", addCapturedPhotoToFiles);
  }

  // Close camera stream when modal is closed
  if (cameraModal) {
    $(cameraModal).on("hidden.bs.modal", function() {
      stopCameraStream();
      resetCameraModal();
    });
  }

  // Setup canvas context
  const canvas = document.getElementById("captureCanvas");
  if (canvas) {
    captureCanvasContext = canvas.getContext("2d");
  }
}

/**
 * Open the camera modal and initialize the camera stream
 */
function openCameraModal() {
  const modal = document.getElementById("cameraCaptureModal");
  if (!modal) return;

  // Reset the modal state
  resetCameraModal();

  // Show the modal
  $(modal).modal("show");

  // Initialize camera
  initializeCameraStream();
}

/**
 * Initialize the camera stream using getUserMedia API
 */
function initializeCameraStream() {
  const video = document.getElementById("cameraStream");
  const container = document.getElementById("cameraContainer");
  const loading = document.getElementById("cameraLoading");
  const error = document.getElementById("cameraError");
  const controls = document.getElementById("cameraControls");

  if (!video) return;

  // Reset error display
  if (error) error.style.display = "none";

  const constraints = {
    video: {
      width: { ideal: 1280 },
      height: { ideal: 720 },
      facingMode: "environment" // Use rear camera on mobile
    },
    audio: false
  };

  navigator.mediaDevices
    .getUserMedia(constraints)
    .then(function(stream) {
      cameraStream = stream;
      video.srcObject = stream;

      // Show camera container and controls
      if (container) container.style.display = "block";
      if (loading) loading.style.display = "none";
      if (controls) controls.style.display = "block";

      // Play the video
      video.play().catch(function(err) {
        console.error("Error playing video:", err);
      });
    })
    .catch(function(err) {
      console.error("Camera access error:", err);

      let errorMsg = "Unable to access camera. ";
      if (err.name === "NotAllowedError") {
        errorMsg += "Camera permission was denied.";
      } else if (err.name === "NotFoundError") {
        errorMsg += "No camera device found.";
      } else if (err.name === "NotReadableError") {
        errorMsg += "Camera is already in use by another application.";
      } else {
        errorMsg += err.message;
      }

      // Show error
      if (error) {
        error.style.display = "block";
        document.getElementById("cameraErrorText").textContent = errorMsg;
      }
      if (loading) loading.style.display = "none";
      if (container) container.style.display = "none";
      if (controls) controls.style.display = "none";
    });
}

/**
 * Capture the current frame from the camera
 */
function capturePhoto() {
  const video = document.getElementById("cameraStream");
  const canvas = document.getElementById("captureCanvas");
  const previewImg = document.getElementById("capturedImage");
  const preview = document.getElementById("capturedPreview");
  const captureBtn = document.getElementById("capturePhotoBtn");
  const clearBtn = document.getElementById("clearCapturedBtn");
  const addBtn = document.getElementById("addCapturedPhotoBtn");

  if (!video || !canvas || !captureCanvasContext) {
    alert("Error: Camera stream not ready");
    return;
  }

  // Set canvas dimensions to match video
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  // Draw video frame to canvas
  captureCanvasContext.drawImage(video, 0, 0, canvas.width, canvas.height);

  // Convert canvas to blob
  canvas.toBlob(
    function(blob) {
      capturedImageBlob = blob;

      // Display preview
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImg.src = e.target.result;
        if (preview) preview.style.display = "block";
      };
      reader.readAsDataURL(blob);

      // Update button visibility
      if (captureBtn) captureBtn.style.display = "none";
      if (clearBtn) clearBtn.style.display = "inline-block";
      if (addBtn) addBtn.style.display = "block";

      // Stop the camera stream
      stopCameraStream();
    },
    "image/jpeg",
    0.9
  );
}

/**
 * Clear the captured image and show camera again
 */
function clearCapturedImage() {
  const video = document.getElementById("cameraStream");
  const container = document.getElementById("cameraContainer");
  const preview = document.getElementById("capturedPreview");
  const captureBtn = document.getElementById("capturePhotoBtn");
  const clearBtn = document.getElementById("clearCapturedBtn");
  const addBtn = document.getElementById("addCapturedPhotoBtn");

  capturedImageBlob = null;

  // Hide preview
  if (preview) preview.style.display = "none";

  // Show camera again
  if (container) container.style.display = "block";
  if (captureBtn) captureBtn.style.display = "block";
  if (clearBtn) clearBtn.style.display = "none";
  if (addBtn) addBtn.style.display = "none";

  // Resume camera stream
  if (!cameraStream) {
    initializeCameraStream();
  } else {
    if (video) {
      video.srcObject = cameraStream;
      video.play().catch(function(err) {
        console.error("Error resuming camera:", err);
      });
    }
  }
}

/**
 * Add the captured photo to the files collection
 */
function addCapturedPhotoToFiles() {
  if (!capturedImageBlob) {
    alert("No image captured");
    return;
  }

  // Create a File object from the blob
  const timestamp = Date.now();
  const capturedFile = new File(
    [capturedImageBlob],
    `captured_package_${timestamp}.jpg`,
    { type: "image/jpeg" }
  );

  // Store the captured image
  capturedImages.push({
    file: capturedFile,
    blob: capturedImageBlob,
    timestamp: timestamp,
    dataUrl: null
  });

  // Update preview
  cdp_preview_captured_images();

  // Update file counter
  updateFileCounter();

  // Show clean button
  const cleanFilesDiv = document.getElementById("clean_files");
  if (cleanFilesDiv) cleanFilesDiv.classList.remove("hide");

  // Close modal
  const modal = document.getElementById("cameraCaptureModal");
  if (modal) {
    $(modal).modal("hide");
  }

  // Show success message
  showCaptureSuccess();
}

/**
 * Display preview of all captured images alongside uploaded files
 */
function cdp_preview_captured_images() {
  const preview = document.getElementById("image_preview");
  if (!preview) return;

  preview.innerHTML = "";

  // Add uploaded files
  const fileInput = document.getElementById("filesMultiple");
  if (fileInput && fileInput.files) {
    for (let i = 0; i < fileInput.files.length; i++) {
      const f = fileInput.files[i];
      const mimeRoot = (f.type || "").split("/")[0];
      const src = mimeRoot === "image" ? URL.createObjectURL(f) : "assets/images/no-preview.jpeg";

      preview.innerHTML += `
        <div class="col-md-3" id="image_${i}" style="margin-bottom: 1rem;">
          <img class="img-thumbnail" style="width:180px;height:180px;object-fit:cover;border:2px solid #dee2e6;" src="${src}">
          <div class="row"><div class="col-md-12 mt-2 mb-2">
            <span>${f.name}</span>
          </div></div>
          <div class="row"><div class="mb-2">
            <button type="button" class="btn btn-danger btn-sm" onclick="cdp_deletePreviewImage(${i});"><i class="fa fa-trash"></i></button>
          </div></div>
        </div>
      `;
    }
  }

  // Add captured images
  capturedImages.forEach((capturedImg, index) => {
    const reader = new FileReader();
    const previewItem = document.createElement("div");
    previewItem.className = "col-md-3";
    previewItem.id = `captured_${index}`;
    previewItem.style.marginBottom = "1rem";

    preview.appendChild(previewItem);

    reader.onload = function(e) {
      previewItem.innerHTML = `
        <div style="position: relative;">
          <img class="img-thumbnail" style="width:180px;height:180px;object-fit:cover;border:2px solid #007bff;" src="${e.target.result}">
          <span style="position: absolute; top: 5px; right: 5px; background: #007bff; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px;">📷 CAPTURED</span>
        </div>
        <div class="row"><div class="col-md-12 mt-2 mb-2">
          <span>${capturedImg.file.name}</span><br>
          <small class="text-muted">Camera capture</small>
        </div></div>
        <div class="row"><div class="mb-2">
          <button type="button" class="btn btn-danger btn-sm" onclick="cdp_deleteCapturedImage(${index});"><i class="fa fa-trash"></i></button>
        </div></div>
      `;
    };
    reader.readAsDataURL(capturedImg.blob);
  });
}

/**
 * Delete a captured image
 */
function cdp_deleteCapturedImage(index) {
  capturedImages.splice(index, 1);
  cdp_preview_captured_images();
  updateFileCounter();

  // Hide clean button if no files left
  const fileInput = document.getElementById("filesMultiple");
  const uploadedCount = fileInput && fileInput.files ? fileInput.files.length : 0;
  if (capturedImages.length === 0 && uploadedCount === 0) {
    const cleanFilesDiv = document.getElementById("clean_files");
    if (cleanFilesDiv) cleanFilesDiv.classList.add("hide");
  }
}

/**
 * Update the file counter display
 */
function updateFileCounter() {
  const fileInput = document.getElementById("filesMultiple");
  const uploadedCount = fileInput && fileInput.files ? fileInput.files.length : 0;
  const capturedCount = capturedImages.length;
  const totalCount = uploadedCount + capturedCount;

  const selectItem = document.getElementById("selectItem");
  if (selectItem) {
    if (totalCount > 0) {
      const countLabel = typeof translate_attached_files_count !== "undefined" ? translate_attached_files_count : "attached files";
      selectItem.innerHTML = countLabel + " (" + totalCount + ")";
    } else {
      selectItem.textContent = typeof translate_attach_files !== "undefined" ? translate_attach_files : "Attach files";
    }
  }

  const totalInput = document.getElementById("total_item_files");
  if (totalInput) {
    totalInput.value = totalCount;
  }
}

/**
 * Stop the camera stream
 */
function stopCameraStream() {
  if (cameraStream) {
    const tracks = cameraStream.getTracks();
    tracks.forEach(function(track) {
      track.stop();
    });
    cameraStream = null;
  }
}

/**
 * Reset the camera modal to initial state
 */
function resetCameraModal() {
  const container = document.getElementById("cameraContainer");
  const loading = document.getElementById("cameraLoading");
  const error = document.getElementById("cameraError");
  const preview = document.getElementById("capturedPreview");
  const controls = document.getElementById("cameraControls");
  const captureBtn = document.getElementById("capturePhotoBtn");
  const clearBtn = document.getElementById("clearCapturedBtn");
  const addBtn = document.getElementById("addCapturedPhotoBtn");

  capturedImageBlob = null;

  if (container) container.style.display = "none";
  if (loading) loading.style.display = "block";
  if (error) error.style.display = "none";
  if (preview) preview.style.display = "none";
  if (controls) controls.style.display = "none";
  if (captureBtn) captureBtn.style.display = "block";
  if (clearBtn) clearBtn.style.display = "none";
  if (addBtn) addBtn.style.display = "none";
}

/**
 * Show success message after capture
 */
function showCaptureSuccess() {
  try {
    Swal.fire({
      title: "Success!",
      text: "Photo added to package. You can capture more photos or close the camera.",
      icon: "success",
      timer: 3000,
      timerProgressBar: true,
      showConfirmButton: false
    });
  } catch (e) {
    // Fallback if Swal not available
    alert("Photo added successfully!");
  }
}

/* =========================================================================
   AUTO-INITIALIZATION
   
   The camera capture system is initialized when the page loads.
   This happens automatically after jQuery and other dependencies are ready.
   ========================================================================= */

// Wait for DOM to be ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeCameraCapture);
} else {
  // DOM is already ready
  initializeCameraCapture();
}