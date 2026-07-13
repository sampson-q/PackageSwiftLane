"use strict";

/* =========================================================================
   SHARED VIDEO CAPTURE MODULE  (courier_add / courier_edit / packages)
   -------------------------------------------------------------------------
   Records a short clip straight from the device camera and keeps it small
   (target ~2–5 MB) by capping the bitrate AND the duration — true in-browser
   transcoding isn't available, so size = bitrate × duration is the lever.

   Expected DOM (all optional; the module no-ops if absent):
     #recordVideoButton   button — start/stop recording
     #videoPreview        <video> — live preview + playback of the last clip
     #videoRecStatus      element — shows a live timer / hint
     #filesVideo          <input type="file"> — carries the recorded clip(s)
     #image_preview       container — thumbnails (shared with photo capture)

   Public API:
     window.cdpAppendVideosToFormData(fd)  — append filesVideo[] on submit
   ========================================================================= */
(function () {
  var TARGET_MAX_BYTES = 5 * 1024 * 1024;   // hard ceiling we aim to stay under
  var MAX_DURATION_MS  = 20000;             // ~20 s cap → keeps size bounded
  var VIDEO_BPS        = 2000000;           // ~2 Mbps video
  var AUDIO_BPS        = 64000;             // ~64 kbps audio

  var recordBtn = document.getElementById("recordVideoButton");
  var previewEl = document.getElementById("videoPreview");
  var statusEl  = document.getElementById("videoRecStatus");
  var filesVideoInput = document.getElementById("filesVideo");
  var previewContainer = document.getElementById("image_preview");

  // Nothing to wire up on this page.
  if (!recordBtn || !filesVideoInput) { return; }

  // Fallback store for browsers without a working DataTransfer on file inputs.
  window.__capturedVideosFallback = window.__capturedVideosFallback || [];

  var stream = null;
  var recorder = null;
  var chunks = [];
  var recording = false;
  var startTs = 0;
  var timerId = null;
  var autoStopId = null;

  function setStatus(msg, color) {
    if (statusEl) { statusEl.textContent = msg || ""; if (color) { statusEl.style.color = color; } }
  }

  function pickMimeType() {
    var candidates = [
      "video/webm;codecs=vp9,opus",
      "video/webm;codecs=vp8,opus",
      "video/webm",
      "video/mp4"
    ];
    if (typeof MediaRecorder === "undefined" || !MediaRecorder.isTypeSupported) { return ""; }
    for (var i = 0; i < candidates.length; i++) {
      if (MediaRecorder.isTypeSupported(candidates[i])) { return candidates[i]; }
    }
    return "";
  }

  function extForMime(mime) {
    if (mime && mime.indexOf("mp4") !== -1) { return "mp4"; }
    return "webm";
  }

  function fmtBytes(n) {
    if (n >= 1024 * 1024) { return (n / (1024 * 1024)).toFixed(1) + " MB"; }
    return Math.round(n / 1024) + " KB";
  }

  function appendFileToInput(inputEl, file) {
    try {
      var dt = new DataTransfer();
      if (inputEl.files) {
        Array.prototype.forEach.call(inputEl.files, function (f) { dt.items.add(f); });
      }
      dt.items.add(file);
      inputEl.files = dt.files;
      return true;
    } catch (e) {
      window.__capturedVideosFallback.push(file);
      return false;
    }
  }

  function removeVideoByName(name) {
    // Remove from the input (rebuild via DataTransfer) and the fallback array.
    try {
      var dt = new DataTransfer();
      Array.prototype.forEach.call(filesVideoInput.files || [], function (f) {
        if (f.name !== name) { dt.items.add(f); }
      });
      filesVideoInput.files = dt.files;
    } catch (e) { /* ignore */ }
    window.__capturedVideosFallback = (window.__capturedVideosFallback || [])
      .filter(function (f) { return f.name !== name; });
  }

  function addVideoThumbnail(blob, filename) {
    if (!previewContainer) { return; }
    var url = URL.createObjectURL(blob);

    var wrap = document.createElement("div");
    wrap.className = "file-thumb";
    wrap.setAttribute("data-filename", filename);
    wrap.setAttribute("data-type", "video");
    wrap.style.cssText = "display:inline-block;margin:6px;position:relative;width:130px;vertical-align:top;";

    var inner = document.createElement("div");
    inner.style.cssText = "position:relative;border-radius:10px;overflow:hidden;border:1px solid #ddd;background:#000;";

    var vid = document.createElement("video");
    vid.src = url;
    vid.controls = true;
    vid.playsInline = true;
    vid.style.cssText = "width:130px;height:100px;object-fit:cover;display:block;background:#000;";

    var badge = document.createElement("span");
    badge.textContent = "▶ VIDEO";
    badge.style.cssText = "position:absolute;left:6px;bottom:6px;font-size:9px;font-weight:700;color:#fff;background:rgba(0,0,0,.6);padding:1px 5px;border-radius:8px;pointer-events:none;";

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "remove-preview-btn";
    btn.innerHTML = "&times;";
    btn.style.cssText = "position:absolute;top:6px;right:6px;width:24px;height:24px;border:none;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;font-size:15px;line-height:1;cursor:pointer;";
    btn.addEventListener("click", function () {
      removeVideoByName(filename);
      if (wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });

    inner.appendChild(vid);
    inner.appendChild(badge);
    inner.appendChild(btn);

    var nameEl = document.createElement("div");
    nameEl.style.cssText = "font-size:11px;margin-top:5px;text-align:center;word-break:break-all;";
    nameEl.textContent = filename;

    var sizeEl = document.createElement("div");
    sizeEl.style.cssText = "font-size:10px;color:#666;text-align:center;";
    sizeEl.textContent = fmtBytes(blob.size);

    wrap.appendChild(inner);
    wrap.appendChild(nameEl);
    wrap.appendChild(sizeEl);
    previewContainer.appendChild(wrap);
  }

  function stopStream() {
    if (stream) {
      stream.getTracks().forEach(function (t) { try { t.stop(); } catch (e) {} });
      stream = null;
    }
  }

  function resetButtonIdle() {
    recording = false;
    recordBtn.innerHTML = '<i class="fa fa-video" style="font-size:18px;"></i> Record Video';
    recordBtn.classList.remove("btn-danger");
    recordBtn.classList.add("btn-info");
  }

  function tickTimer() {
    var secs = Math.floor((Date.now() - startTs) / 1000);
    var left = Math.max(0, Math.ceil((MAX_DURATION_MS - (Date.now() - startTs)) / 1000));
    setStatus("● Recording… " + secs + "s (auto-stops in " + left + "s)", "#dc3545");
  }

  async function startRecording() {
    if (typeof MediaRecorder === "undefined") {
      setStatus("Video recording isn't supported on this browser.", "#dc3545");
      return;
    }
    var mime = pickMimeType();
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "environment", width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: true
      });
    } catch (e) {
      setStatus("Could not access the camera/microphone.", "#dc3545");
      return;
    }

    if (previewEl) {
      previewEl.srcObject = stream;
      previewEl.muted = true;          // avoid feedback while recording
      previewEl.controls = false;
      previewEl.style.display = "block";
      try { await previewEl.play(); } catch (e) {}
    }

    chunks = [];
    var opts = { videoBitsPerSecond: VIDEO_BPS, audioBitsPerSecond: AUDIO_BPS };
    if (mime) { opts.mimeType = mime; }
    try {
      recorder = new MediaRecorder(stream, opts);
    } catch (e) {
      try { recorder = new MediaRecorder(stream); } catch (e2) {
        setStatus("Recorder could not start on this device.", "#dc3545");
        stopStream();
        return;
      }
    }

    recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size > 0) { chunks.push(ev.data); } };
    recorder.onstop = function () {
      clearInterval(timerId);
      clearTimeout(autoStopId);
      var outMime = (recorder && recorder.mimeType) ? recorder.mimeType : (mime || "video/webm");
      var blob = new Blob(chunks, { type: outMime.split(";")[0] });
      stopStream();
      if (previewEl) {
        previewEl.srcObject = null;
        previewEl.muted = false;
        previewEl.controls = true;
        previewEl.src = URL.createObjectURL(blob);
      }
      resetButtonIdle();

      if (!blob.size) { setStatus("Nothing was recorded.", "#dc3545"); return; }

      var filename = "clip_" + Date.now() + "." + extForMime(outMime);
      var file;
      try { file = new File([blob], filename, { type: blob.type }); }
      catch (e) { file = blob; file.name = filename; }

      appendFileToInput(filesVideoInput, file);
      addVideoThumbnail(blob, filename);

      if (blob.size > TARGET_MAX_BYTES) {
        setStatus("Saved " + fmtBytes(blob.size) + " — larger than 5 MB; record a shorter clip if you can.", "#e67e22");
      } else {
        setStatus("Saved " + fmtBytes(blob.size) + ".", "#28a745");
      }
    };

    recorder.start(1000); // gather data in 1s slices
    recording = true;
    startTs = Date.now();
    recordBtn.innerHTML = '<i class="fa fa-stop" style="font-size:18px;"></i> Stop Recording';
    recordBtn.classList.remove("btn-info");
    recordBtn.classList.add("btn-danger");
    tickTimer();
    timerId = setInterval(tickTimer, 1000);
    autoStopId = setTimeout(function () { if (recording) { stopRecording(); } }, MAX_DURATION_MS);
  }

  function stopRecording() {
    if (recorder && recorder.state !== "inactive") {
      try { recorder.stop(); } catch (e) { stopStream(); resetButtonIdle(); }
    } else {
      stopStream();
      resetButtonIdle();
    }
  }

  recordBtn.addEventListener("click", function () {
    if (recording) { stopRecording(); } else { startRecording(); }
  });

  window.addEventListener("beforeunload", function () { stopStream(); });

  // Submit hook: append recorded clips to the outgoing FormData.
  window.cdpAppendVideosToFormData = function (fd) {
    if (filesVideoInput && filesVideoInput.files && filesVideoInput.files.length) {
      Array.prototype.forEach.call(filesVideoInput.files, function (f) {
        fd.append("filesVideo[]", f, f.name || ("clip_" + Date.now() + ".webm"));
      });
    }
    (window.__capturedVideosFallback || []).forEach(function (f, i) {
      fd.append("filesVideo[]", f, f.name || ("clip_fallback_" + Date.now() + "_" + i + ".webm"));
    });
  };
})();
