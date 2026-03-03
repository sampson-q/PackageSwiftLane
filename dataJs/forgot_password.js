"use strict";

$("#forgotPassword").on('submit', function (event) {
    event.preventDefault();

    var parametros = $(this).serialize();
    $.ajax({
        type: "POST",
        url: "./ajax/forgot-password-ajax.php",
        data: parametros,
        dataType: "json",
        beforeSend: function () {
            $("#resultados_ajax").html("<div class='alert alert-info'>Please wait...</div>");
        },
        success: function (data) {
            if (data.success) {
                $("#resultados_ajax").html("<div class='alert alert-success'>" + data.messages + "</div>");
                window.location.href = data.redirect;
            } else {
                $("#resultados_ajax").html("<div class='alert alert-danger'>" + data.errors + "</div>");
            }
        },
        error: function () {
            $("#resultados_ajax").html("<div class='alert alert-danger'>Request failed. Please try again.</div>");
        }
    });
});