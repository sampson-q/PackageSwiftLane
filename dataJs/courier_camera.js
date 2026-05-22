"use strict";

/* =========================================================================
   CAMERA CAPTURE — FIXED VERSION
   Issues fixed:
   - Desktop: video.play() timing/promise issues
   - Mobile: HTTPS requirement check, better error messages
   - Edge cases: cleanup, concurrent access, permissions
   ========================================================================= */

var cameraStream = null;
var capturedImageBlob = null;
var capturedImages = [];
var cameraInitialized = false;

function initializeCameraCapture() {
  if (cameraInitialized) return; // Prevent double-init
  cameraInitialized = true;

  const cameraBtn = document.getElementById("openCameraCapture");
  if (cameraBtn) {
    cameraBtn.addEventListener("click", openCameraModal);
  }

  const capturePhotoBtn = document.getElementById("capturePhotoBtn");
  if (capturePhotoBtn) {
    capturePhotoBtn.addEventListener("click", capturePhoto);
  }

  const clearCapturedBtn = document.getElementById("clearCapturedBtn");
  if (clearCapturedBtn) {
    clearCapturedBtn.addEventListener("click", clearCapturedImage);
  }

  const addCapturedPhotoBtn = document.getElementById("addCapturedPhotoBtn");
  if (addCapturedPhotoBtn) {
    addCapturedPhotoBtn.addEventListener("click", addCapturedPhotoToFiles);
  }

  const cameraModal = document.getElementById("cameraCaptureModal");
  if (cameraModal) {
    $(cameraModal).on("hidden.bs.modal", function() {
      stopCameraStream();
      resetCameraModal();
    });
  }

  // Cleanup on page unload
  window.addEventListener("beforeunload", stopCameraStream);
}

function openCameraModal() {
  // Check HTTPS on mobile (required for camera access)
  if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
    if (location.protocol !== "https:") {
      Swal.fire({
        icon: "warning",
        title: "HTTPS Required",
        text: "Camera access requires a secure connection (HTTPS). Please access this page via HTTPS.",
        confirmButtonText: "OK"
      });
      return;
    }
  }

  // Check getUserMedia support
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    Swal.fire({
      icon: "error",
      title: "Camera Not Supported",
      text: "Your browser does not support camera access. Try Chrome, Firefox, Safari, or Edge.",
      confirmButtonText: "OK"
    });
    return;
  }

  const modal = document.getElementById("cameraCaptureModal");
  if (!modal) return;

  resetCameraModal();
  $(modal).modal("show");

  // Delay init slightly to ensure modal is rendered
  setTimeout(initializeCameraStream, 300);
}

function initializeCameraStream() {
  const video = document.getElementById("cameraStream");
  const container = document.getElementById("cameraContainer");
  const loading = document.getElementById("cameraLoading");
  const error = document.getElementById("cameraError");
  const errorText = document.getElementById("cameraErrorText");
  const controls = document.getElementById("cameraControls");

  if (!video) return;

  // Stop any existing stream
  if (cameraStream) {
    stopCameraStream();
  }

  if (error) error.style.display = "none";

  const constraints = {
    video: {
      width: { ideal: 1280 },
      height: { ideal: 720 },
      facingMode: "environment"
    },
    audio: false
  };

  navigator.mediaDevices
    .getUserMedia(constraints)
    .then(function(stream) {
      cameraStream = stream;
      video.srcObject = stream;

      // Wait for video to be ready before showing
      video.onloadedmetadata = function() {
        if (container) container.style.display = "block";
        if (loading) loading.style.display = "none";
        if (controls) controls.style.display = "block";

        // Play with proper error handling
        var playPromise = video.play();
        if (playPromise !== undefined) {
          playPromise
            .catch(function(err) {
              console.error("Video play error:", err);
              showCameraError("Could not start video playback: " + err.message);
            });
        }
      };

      // Timeout fallback (5 seconds)
      setTimeout(function() {
        if (!video.videoWidth || !video.videoHeight) {
          showCameraError("Camera stream timed out. Try again or refresh the page.");
        }
      }, 5000);
    })
    .catch(function(err) {
      console.error("Camera access error:", err);
      let errorMsg = "Unable to access camera. ";

      if (err.name === "NotAllowedError") {
        errorMsg = "Camera permission denied. Check your browser settings.";
      } else if (err.name === "NotFoundError") {
        errorMsg = "No camera device found on this computer.";
      } else if (err.name === "NotReadableError") {
        errorMsg = "Camera is in use by another application. Close it and try again.";
      } else if (err.name === "SecurityError") {
        errorMsg = "Camera access denied for security reasons. Ensure you're using HTTPS.";
      } else if (err.name === "TypeError") {
        errorMsg = "Invalid camera constraints. Your browser may not support this camera.";
      }

      showCameraError(errorMsg);
    });
}

function showCameraError(msg) {
  const error = document.getElementById("cameraError");
  const errorText = document.getElementById("cameraErrorText");
  const loading = document.getElementById("cameraLoading");
  const container = document.getElementById("cameraContainer");
  const controls = document.getElementById("cameraControls");

  if (error) {
    error.style.display = "block";
    if (errorText) errorText.textContent = msg;
  }
  if (loading) loading.style.display = "none";
  if (container) container.style.display = "none";
  if (controls) controls.style.display = "none";
}

function capturePhoto() {
  const video = document.getElementById("cameraStream");
  const canvas = document.getElementById("captureCanvas");

  if (!video || !video.videoWidth || !video.videoHeight) {
    alert("Camera stream not ready. Please wait.");
    return;
  }

  if (!canvas) return;

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  const ctx = canvas.getContext("2d");
  if (!ctx) {
    alert("Canvas not supported");
    return;
  }

  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  canvas.toBlob(
    function(blob) {
      if (!blob) {
        alert("Failed to capture image");
        return;
      }

      capturedImageBlob = blob;

      // Show preview
      const previewImg = document.getElementById("capturedImage");
      const preview = document.getElementById("capturedPreview");
      const reader = new FileReader();

      reader.onload = function(e) {
        if (previewImg) previewImg.src = e.target.result;
        if (preview) preview.style.display = "block";
      };
      reader.readAsDataURL(blob);

      // Update buttons
      const captureBtn = document.getElementById("capturePhotoBtn");
      const clearBtn = document.getElementById("clearCapturedBtn");
      const addBtn = document.getElementById("addCapturedPhotoBtn");

      if (captureBtn) captureBtn.style.display = "none";
      if (clearBtn) clearBtn.style.display = "inline-block";
      if (addBtn) addBtn.style.display = "block";

      stopCameraStream();
    },
    "image/jpeg",
    0.85
  );
}

function clearCapturedImage() {
  capturedImageBlob = null;

  const preview = document.getElementById("capturedPreview");
  const container = document.getElementById("cameraContainer");
  const captureBtn = document.getElementById("capturePhotoBtn");
  const clearBtn = document.getElementById("clearCapturedBtn");
  const addBtn = document.getElementById("addCapturedPhotoBtn");

  if (preview) preview.style.display = "none";
  if (captureBtn) captureBtn.style.display = "block";
  if (clearBtn) clearBtn.style.display = "none";
  if (addBtn) addBtn.style.display = "none";

  // Restart stream
  if (!cameraStream) {
    initializeCameraStream();
  }
}

function addCapturedPhotoToFiles() {
  if (!capturedImageBlob) {
    alert("No image captured");
    return;
  }

  const timestamp = Date.now();
  const capturedFile = new File(
    [capturedImageBlob],
    `package_${timestamp}.jpg`,
    { type: "image/jpeg" }
  );

  capturedImages.push({
    file: capturedFile,
    blob: capturedImageBlob,
    timestamp: timestamp
  });

  cdp_preview_images();
  updateFileCounter();

  const cleanFilesDiv = document.getElementById("clean_files");
  if (cleanFilesDiv) cleanFilesDiv.classList.remove("hide");

  const modal = document.getElementById("cameraCaptureModal");
  if (modal) $(modal).modal("hide");

  Swal.fire({
    icon: "success",
    title: "Photo Added",
    text: "Photo added to package. Capture more or close camera.",
    timer: 2500,
    showConfirmButton: false
  });
}

function cdp_deleteCapturedImage(index) {
  capturedImages.splice(index, 1);
  cdp_preview_images();
  updateFileCounter();

  const fileInput = document.getElementById("filesMultiple");
  const uploadedCount = fileInput && fileInput.files ? fileInput.files.length : 0;

  if (capturedImages.length === 0 && uploadedCount === 0) {
    const cleanFilesDiv = document.getElementById("clean_files");
    if (cleanFilesDiv) cleanFilesDiv.classList.add("hide");
  }
}

function updateFileCounter() {
  const fileInput = document.getElementById("filesMultiple");
  const uploadedCount = fileInput && fileInput.files ? fileInput.files.length : 0;
  const capturedCount = capturedImages.length;
  const totalCount = uploadedCount + capturedCount;

  const selectItem = document.getElementById("selectItem");
  if (selectItem) {
    selectItem.innerHTML = totalCount > 0 ? `attached files (${totalCount})` : "Attach files";
  }

  const totalInput = document.getElementById("total_item_files");
  if (totalInput) totalInput.value = totalCount;
}

function stopCameraStream() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
  }
}

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

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initializeCameraCapture);
} else {
  initializeCameraCapture();
}