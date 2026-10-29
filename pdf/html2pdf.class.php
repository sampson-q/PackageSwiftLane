<?php
// Compatibility entrypoint. The maintained implementation is pinned in composer.lock.
require_once dirname(__DIR__) . '/vendor/autoload.php';
if (!class_exists('HTML2PDF', false)) {
    class_alias(\Spipu\Html2Pdf\Html2Pdf::class, 'HTML2PDF');
}
