<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!class_exists('SMTP', false)) {
    class_alias(\PHPMailer\PHPMailer\SMTP::class, 'SMTP');
}
