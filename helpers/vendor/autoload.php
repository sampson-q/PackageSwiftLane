<?php
// Existing callers keep their entry path; all dependencies now use one patched lockfile.
return require dirname(__DIR__, 2) . '/vendor/autoload.php';
