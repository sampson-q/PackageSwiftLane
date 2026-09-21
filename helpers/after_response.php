<?php
// ============================================================================
// Respond first, deliver after.
//
// Email (SMTP) and WhatsApp (UltraMsg cURL) sends cost several network round
// trips each. Done inline they sit between the user's click and the response:
// a login waited for an SMTP conversation plus two API calls before it could
// redirect to the code page. Nothing on the page depends on those sends having
// finished, so they are queued here and run AFTER the response has been handed
// to the browser.
//
//   cdp_afterResponse(callable $task)   queue any work for after the response
//   cdp_notifyDeferred(true|false)      switch the request's email + WhatsApp
//                                       sends to queued mode (read by
//                                       PHPMailer::send() and
//                                       sendNotificationWhatsApp_v2())
//   cdp_notifyDeferred()                read the current mode
//
// Queued sends report success optimistically; the real outcome is recorded by
// the message log (helpers/message_log.php) like any other send. Endpoints
// whose answer IS the send result (test email, bulk notify reports, phone
// verification) must call cdp_notifyDeferred(false) before sending.
//
// The session is written and released before the queue runs, otherwise the
// page the browser is redirected to would block on the session lock for as
// long as the sends take, which is the same wait in a different place.
// ============================================================================

if (!function_exists('cdp_afterResponse')) {

    function cdp_afterResponse(callable $task)
    {
        // CLI (cron, scripts): there is no response to get out of the way.
        if (PHP_SAPI === 'cli') {
            $task();
            return;
        }

        if (!isset($GLOBALS['cdp_after_response_queue'])) {
            $GLOBALS['cdp_after_response_queue'] = [];
            register_shutdown_function('cdp_afterResponseRun');

            // mod_php can only close the connection early if it knows the body
            // length, so whatever the script echoes from here on is buffered.
            if (!headers_sent() && ob_get_level() === 0) {
                ob_start();
            }
        }
        $GLOBALS['cdp_after_response_queue'][] = $task;
    }

    function cdp_notifyDeferred($set = null)
    {
        if ($set !== null) {
            $GLOBALS['cdp_notify_deferred'] = (bool) $set;
        }
        if (PHP_SAPI === 'cli' || !empty($GLOBALS['cdp_after_response_running'])) {
            return false;
        }
        return !empty($GLOBALS['cdp_notify_deferred']);
    }

    /** Hand the finished response to the client while this process keeps going. */
    function cdp_finishResponse()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {          // PHP-FPM
            fastcgi_finish_request();
            return;
        }
        if (function_exists('litespeed_finish_request')) {        // LiteSpeed
            litespeed_finish_request();
            return;
        }

        // Apache mod_php: an exact Content-Length plus Connection: close lets
        // the browser treat the response as complete.
        $body = '';
        while (ob_get_level() > 0) {
            $body = ob_get_clean() . $body;
        }
        if (!headers_sent()) {
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');   // mod_deflate would change the length
            }
            header('Content-Encoding: none');
            header('Content-Length: ' . strlen($body));
            header('Connection: close');
        }
        echo $body;
        flush();
    }

    function cdp_afterResponseRun()
    {
        $queue = isset($GLOBALS['cdp_after_response_queue']) ? $GLOBALS['cdp_after_response_queue'] : [];
        if (!$queue) {
            return;
        }
        $GLOBALS['cdp_after_response_queue'] = [];

        ignore_user_abort(true);
        @set_time_limit(180);
        cdp_finishResponse();

        $GLOBALS['cdp_after_response_running'] = true;   // queued sends now run for real
        foreach ($queue as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                error_log('[after_response] ' . get_class($e) . ': ' . $e->getMessage());
            }
        }
    }
}
