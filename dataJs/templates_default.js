$(document).ready(function () {
  select2Templates();
});

function select2Templates() {
  $(".select2")
    .select2({
      ajax: {
        url: "ajax/select2_templates_whatsapp.php",
        dataType: "json",

        delay: 250,
        data: function (params) {
          return {
            q: params.term, // search term
          };
        },
        processResults: function (data) {
          return {
            results: data,
          };
        },
        cache: true,
      },
      placeholder: message_error_form26,
      allowClear: true,
    })
    .on("change", function (e) {
      $(this).valid();
    });
}

// Agregar reglas de validación a los Select2 dinámicos
var rules = {};
var messages = {};

// Generar reglas de validación y mensajes para cada Select2
$(".select2").each(function () {
  var selectId = $(this).attr("id");
  rules[selectId] = {
    required: true,
  };
  messages[selectId] = {
    required: message_error_form27,
  };
});

$("#save_data").validate({
  ignore: "",
  rules: rules,
  messages: messages,
  errorElement: "span",
  errorPlacement: function (error, element) {
    error.addClass("invalid-feedback");
    element.closest(".form-group").append(error);
  },
  highlight: function (element, errorClass, validClass) {
    $(element).addClass("is-invalid");
  },
  unhighlight: function (element, errorClass, validClass) {
    $(element).removeClass("is-invalid");
  },
});

$(document).ready(function() {
    $("#save_data").submit(function(event) {
        event.preventDefault();

        var formData = new FormData(); // Crear objeto FormData


        $(".card-hover").each(function () {
            var id = $(this).find(".checkbox-active").data("id");
            var id_template = $(this).find(".select2").val();
            var active = $(this).find(".checkbox-active").prop("checked");

            formData.append('id[]', id);
            formData.append('id_template[]', id_template);
            formData.append('active[]', active ? 1 : 0);
        });

        $.ajax({
            url: "./ajax/tools/default_templates_ajax.php",
            type: 'POST',
            data: formData, // Enviar los datos como FormData
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function () {
                Swal.fire({
                    title: message_error_form6,
                    text: message_error_form14,
                    type: 'info',
                    showCancelButton: false,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    onBeforeOpen: () => {
                        Swal.showLoading();
                    },
                });
            },
            success: function (response) {
                Swal.close();
                if (response.status === 'success') {
                    Swal.fire({
                        type: 'success',
                        title: message_error_form15,
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true,
                    }).then(() => {
                        // Redirigir al listado de clientes
                        window.location.href = 'templates_default.php';
                    });
                } else {
                    Swal.fire({
                        type: 'error',
                        title: message_error_form18,
                        text: response.message || message_error_form19,
                        confirmButtonColor: '#336aea',
                        showConfirmButton: true,
                    });
                }
            },
            error: function () {
                Swal.close();
                Swal.fire({
                    type: 'error',
                    title: message_error_form18,
                    text: message_error_form19,
                    confirmButtonColor: '#336aea',
                    showConfirmButton: true,
                });
            }
        });
    });
});


