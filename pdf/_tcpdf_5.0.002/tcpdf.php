<?php
// Preserve the legacy include path without loading the vulnerable TCPDF 5 code or sample key.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
class_exists('TCPDF');
