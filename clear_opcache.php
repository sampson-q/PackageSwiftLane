<?php
// One-time OPcache clear — delete this file after use
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo json_encode(['ok' => true, 'msg' => 'OPcache cleared']);
} else {
    echo json_encode(['ok' => true, 'msg' => 'OPcache not active']);
}
