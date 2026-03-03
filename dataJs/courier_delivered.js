"use strict";

(function () {
  "use strict";

  window.requestAnimFrame = (function () {
    return (
      window.requestAnimationFrame ||
      window.webkitRequestAnimationFrame ||
      window.mozRequestAnimationFrame ||
      window.oRequestAnimationFrame ||
      window.msRequestAnimationFrame ||
      function (callback) {
        window.setTimeout(callback, 1000 / 60);
      }
    );
  })();

  const canvas = document.getElementById("sig-canvas");
  const ctx = canvas.getContext("2d");
  ctx.strokeStyle = "#222222";
  ctx.lineWidth = 4;

  let drawing = false;
  let mousePos = { x: 0, y: 0 };
  let lastPos = mousePos;

  canvas.addEventListener("mousedown", (e) => {
    drawing = true;
    lastPos = getMousePos(canvas, e);
  });

  canvas.addEventListener("mouseup", () => {
    drawing = false;
  });

  canvas.addEventListener("mousemove", (e) => {
    mousePos = getMousePos(canvas, e);
  });

  canvas.addEventListener("touchstart", (e) => {
    mousePos = getTouchPos(canvas, e);
    const touch = e.touches[0];
    const me = new MouseEvent("mousedown", {
      clientX: touch.clientX,
      clientY: touch.clientY,
    });
    canvas.dispatchEvent(me);
  });

  canvas.addEventListener("touchmove", (e) => {
    const touch = e.touches[0];
    const me = new MouseEvent("mousemove", {
      clientX: touch.clientX,
      clientY: touch.clientY,
    });
    canvas.dispatchEvent(me);
  });

  canvas.addEventListener("touchend", () => {
    const me = new MouseEvent("mouseup", {});
    canvas.dispatchEvent(me);
  });

  function getMousePos(canvasDom, mouseEvent) {
    const rect = canvasDom.getBoundingClientRect();
    return {
      x: mouseEvent.clientX - rect.left,
      y: mouseEvent.clientY - rect.top,
    };
  }

  function getTouchPos(canvasDom, touchEvent) {
    const rect = canvasDom.getBoundingClientRect();
    return {
      x: touchEvent.touches[0].clientX - rect.left,
      y: touchEvent.touches[0].clientY - rect.top,
    };
  }

  function renderCanvas() {
    if (drawing) {
      ctx.moveTo(lastPos.x, lastPos.y);
      ctx.lineTo(mousePos.x, mousePos.y);
      ctx.stroke();
      lastPos = mousePos;
    }
  }

  document.body.addEventListener("touchstart", (e) => {
    if (e.target === canvas) {
      e.preventDefault();
    }
  });

  document.body.addEventListener("touchend", (e) => {
    if (e.target === canvas) {
      e.preventDefault();
    }
  });

  document.body.addEventListener("touchmove", (e) => {
    if (e.target === canvas) {
      e.preventDefault();
    }
  });

  (function drawLoop() {
    requestAnimFrame(drawLoop);
    renderCanvas();
  })();

  function clearCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  }

  const sigText = document.getElementById("sig-dataUrl");
  const sigImage = document.getElementById("sig-image");
  const clearBtn = document.getElementById("sig-clearBtn");
  const submitBtn = document.getElementById("sig-submitBtn");

  clearBtn.addEventListener("click", () => {
    clearCanvas();
    sigText.value = "";
    sigImage.setAttribute("src", "");
  });

  submitBtn.addEventListener("click", () => {
    const dataUrl = canvas.toDataURL();
    sigText.value = dataUrl;
    sigImage.setAttribute("src", dataUrl);
  });
})();

function mandarFirma() {
  document.getElementById("invoice_form").submit();
}


$(function () {
  $("#t_date").datepicker({
    format: "yyyy-mm-dd",
    autoclose: true,
  });
});

$("#invoice_form").on("submit", function (event) {
  event.preventDefault(); // Prevent the form from submitting normally

  var formData = new FormData(this);

  var shipment_id = $("#shipment_id").val();
  var deliver_date = $("#deliver_date").val();
  var driver_id = $("#driver_id").val();
  var person_receives = $("#person_receives").val();
  var miarchivo = document.getElementById("miarchivo").files[0];

  var notify_whatsapp_sender = $("input:checkbox[name=notify_whatsapp_sender]:checked").val();
  var notify_whatsapp_receiver = $("input:checkbox[name=notify_whatsapp_receiver]:checked").val();
  var notify_sms_sender = $("input:checkbox[name=notify_sms_sender]:checked").val();
  var notify_sms_receiver = $("input:checkbox[name=notify_sms_receiver]:checked").val();
  var sigDataUrl = $("#sig-dataUrl").val();

  if (shipment_id) {
    formData.append("shipment_id", shipment_id);
  }

  if (deliver_date) {
    formData.append("deliver_date", deliver_date);
  }

  if (driver_id) {
    formData.append("driver_id", driver_id);
  }

  if (person_receives) {
    formData.append("person_receives", person_receives);
  }

  if (notify_whatsapp_sender) {
    formData.append("notify_whatsapp_sender", notify_whatsapp_sender);
  }

  if (notify_whatsapp_receiver) {
    formData.append("notify_whatsapp_receiver", notify_whatsapp_receiver);
  }

  if (notify_sms_sender) {
    formData.append("notify_sms_sender", notify_sms_sender);
  }

  if (notify_sms_receiver) {
    formData.append("notify_sms_receiver", notify_sms_receiver);
  }

  if (miarchivo) {
    formData.append("miarchivo", miarchivo);
  }

  if (sigDataUrl) {
    formData.append("sig-dataUrl", sigDataUrl);
  }

  $.ajax({
    type: "POST",
    url: "ajax/courier/add_courier_delivered_ajax.php",
    data: formData,
    dataType: "json",
    contentType: false,
    processData: false,
    cache: false, // To disable request pages from being cached
    beforeSend: function () {
      $("#create_invoice").attr("disabled", true);
      Swal.fire({
        title: message_loading,
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });
    },
    success: function (data) {
      $("#create_invoice").attr("disabled", false);
      if (data.success) {
        cdp_showSuccess(data.messages, data.shipment_id);
      } else {
        cdp_showError(data.errors);
      }
    }
  });
});

function cdp_showError(errors) {
  var html_code = "<ul class='error'>";
  for (var i = 0; i < errors.length; i++) {
    html_code += '<li class="text-left">';
    html_code += errors[i];
    html_code += "</li>";
  }
  html_code += "</ul>";

  Swal.fire({
    title: message_error,
    html: html_code,
    icon: "error",
    allowOutsideClick: false,
    confirmButtonText: "Ok",
  });
}

function cdp_showSuccess(messages, shipment_id) {
  Swal.fire({
    title: messages,
    icon: 'success',
    allowOutsideClick: false,
    confirmButtonText: 'Ok'
  }).then((result) => {
    if (result.isConfirmed) {
      setTimeout(function () {
        window.location = "courier_view.php?id=" + shipment_id;
      }, 2000);
    }
  });
}


document.getElementById('miarchivo').addEventListener('change', function (event) {
    var input = event.target;
    var label = input.nextElementSibling;
    var fileName = input.files[0] ? input.files[0].name : 'Choose file';
    label.innerHTML = fileName;
  });

  function previewImage(event) {
    var reader = new FileReader();
    reader.onload = function () {
      var output = document.getElementById('image-preview-img');
      output.src = reader.result;
      output.style.display = 'block';
    }
    reader.readAsDataURL(event.target.files[0]);
  }