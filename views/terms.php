<!DOCTYPE html>
<html lang="en" data-theme="system">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Términos y Condiciones | <?php echo $core->site_name ?></title>
    <meta name="keywords" content="Courier DEPRIXA-Integral Web System">
    <meta name="author" content="Jaomweb">
    <meta name="description" content="">
    <!-- favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/<?php echo $core->favicon ?>">
    <!-- Bootstrap -->
    <link href="assets/css_main_deprixa/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Icons -->
    <link href="assets/css_main_deprixa/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v3.0.6/css/line.css">
    <!-- Slider -->
    <link rel="stylesheet" href="assets/css_main_deprixa/css/tiny-slider.css" />
    <!-- Date picker -->
    <link rel="stylesheet" href="assets/css_main_deprixa/css/datepicker.min.css">
    <!-- Main Css -->
    <link href="assets/css_main_deprixa/css/style.css" rel="stylesheet" type="text/css" id="theme-opt" />
    <link href="assets/css_main_deprixa/css/colors/default.css" rel="stylesheet" id="color-opt">

    <style>
        /* Asegura que el header tenga altura fija y esté encima del contenido */
        header#topnav {
            height: 80px;
            z-index: 999;
            position: sticky;
            top: 0;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* Ajusta el espacio del contenido principal */
        .main {
            padding-top: 100px; /* igual o mayor que la altura del header */
        }

        /* Mejora para móviles */
        @media (max-width: 768px) {
            header#topnav {
                height: auto;
            }
            .main {
                padding-top: 120px;
            }
        }

        .centered-page-header {
                margin-top: 50px;
            }

            .site-footer {
                margin-top: 60px; /* separa el footer del contenido anterior */
                padding: 30px 0;
                background-color: #f8f9fa; /* opcional: color suave */
            }

            .footer-bottom {
                display: flex;
                justify-content: center; /* centra horizontalmente */
                align-items: center;
                text-align: center;
            }


    </style>

</head> 

<body class="cover-user">
    <!-- Loader -->
        <div id="preloader">
            <div id="status">
                <div class="spinner">
                    <div class="double-bounce1"></div>
                    <div class="double-bounce2"></div>
                </div>
            </div>
        </div>
        <!-- Loader -->

        <!-- Navbar STart -->
        <header id="topnav" class="defaultscroll sticky">
            <div class="container">
                <!-- Logo container-->
                <a class="logo" href="index.php">
                    <?php echo ($core->logo_web) ? '<img src="assets/' . $core->logo_web . '" alt="' . $core->site_name . '" width="' . $core->thumb_web . '" height="' . $core->thumb_hweb . '"/>' : $core->site_name; ?>


                </a>

                <!-- End Logo container-->
                <div class="menu-extras">
                    <div class="menu-item">
                        <!-- Mobile menu toggle-->
                        <a class="navbar-toggle" id="isToggle" onclick="toggleMenu()">
                            <div class="lines">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </a>
                        <!-- End mobile menu toggle-->
                    </div>
                </div>

                <!--Login button Start-->
                <ul class="buy-button list-inline mb-0">
                    <li class="list-inline-item mb-0">
                        <a href="index.php">
                            <div class="login-btn-primary"><span class="btn btn-icon btn-pills btn-soft-primary"><i data-feather="home" class="fea icon-sm"></i></span></div>
                            <div class="login-btn-light"><span class="btn btn-icon btn-pills btn-light"><i data-feather="home" class="fea icon-sm"></i></span></div>
                        </a>
                    </li>

                </ul>
                <!--Login button End-->

            </div>
            <!--end container-->
        </header>
        <!--end header-->
        <!-- Navbar End -->

        <section>
            <div class="container">
                <div class="centered-page-header text-center">
                    <h2 class="title">Términos y Condiciones</h2>
                    <div class="description">
                        <p>Lea cuidadosamente nuestros términos antes de usar nuestros servicios.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="bg-white shadow rounded p-4">
                            <h4>1. Aceptación de Términos</h4>
                            <p>Al acceder y utilizar nuestro sitio web y servicios, usted acepta estar sujeto a estos Términos y Condiciones. Si no está de acuerdo con alguna parte, no debería utilizar nuestro sitio.</p>

                            <h4>2. Servicios</h4>
                            <p>Brindamos servicios de envío, seguimiento y logística según las condiciones estipuladas en nuestros términos de uso. Nos reservamos el derecho de modificar o descontinuar cualquier servicio sin previo aviso.</p>

                            <h4>3. Responsabilidad del Usuario</h4>
                            <p>El usuario es responsable de proporcionar información precisa en todos los formularios y procesos del sitio. Cualquier intento de fraude, alteración de información o uso indebido puede resultar en la suspensión de la cuenta.</p>

                            <h4>4. Propiedad Intelectual</h4>
                            <p>Todos los contenidos de este sitio, incluyendo textos, gráficos y logotipos, son propiedad de <?php echo $core->site_name ?> o de sus respectivos dueños y están protegidos por leyes de derechos de autor.</p>

                            <h4>5. Modificaciones</h4>
                            <p>Nos reservamos el derecho de modificar estos Términos en cualquier momento. Las modificaciones entrarán en vigencia tan pronto como sean publicadas en el sitio web.</p>

                            <h4>6. Ley Aplicable</h4>
                            <p>Estos Términos se rigen por las leyes vigentes en el país donde se encuentra registrada nuestra empresa.</p>

                            <p>Para cualquier duda o consulta, contáctenos a través de: <strong><?php echo $core->site_email ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer">
            <div class="container">
                <div class="footer-bottom flex justify-space-between">
                    <div class="copyright">
                        &copy; <?php echo date('Y') ?> <a href="index.php"><?php echo $core->site_name; ?></a> - Todos los derechos reservados.
                    </div>
                </div>
            </div>
        </footer>
    </div>

        <!-- javascript -->
    <script src="assets/css_main_deprixa/main_deprixa/js/jquery.min.js"></script>
    <script src="assets/css_main_deprixa/js/bootstrap.bundle.min.js"></script>
    <!-- SLIDER -->
    <script src="assets/css_main_deprixa/js/tiny-slider.js "></script>
    <!-- Datepicker -->
    <script src="assets/css_main_deprixa/js/datepicker.min.js"></script>
    <!-- Icons -->
    <script src="assets/css_main_deprixa/js/feather.min.js"></script>
    <!-- Main Js -->
    <script src="assets/css_main_deprixa/js/plugins.init.js"></script>
    <!--Note: All init js like tiny slider, counter, countdown, maintenance, lightbox, gallery, swiper slider, aos animation etc.-->
    <script src="assets/css_main_deprixa/js/app.js"></script>
    <!--Note: All important javascript like page loader, menu, sticky menu, menu-toggler, one page menu etc. -->
</body>
</html>
