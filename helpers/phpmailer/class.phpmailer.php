<?php
/** Backward-compatible facade over the maintained Composer PHPMailer release. */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!class_exists('phpmailerException', false)) {
    class_alias(\PHPMailer\PHPMailer\Exception::class, 'phpmailerException');
}
class PHPMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    public $Version = parent::VERSION;
    private $swiftlaneFailureLogged = false;

    public function __construct($exceptions = null)
    {
        parent::__construct($exceptions);
        if (function_exists('cdp_msgMailCallback')) $this->action_function = 'cdp_msgMailCallback';
    }

    private function logFailure()
    {
        if (!$this->swiftlaneFailureLogged && function_exists('cdp_msgMailFailed')) {
            $this->swiftlaneFailureLogged = true;
            cdp_msgMailFailed($this, $this->ErrorInfo);
        }
    }

    public function send()
    {
        if (function_exists('cdp_notifyDeferred') && cdp_notifyDeferred()) {
            $queued = clone $this;
            cdp_afterResponse(function () use ($queued) {
                try { $queued->send(); }
                catch (\Exception $e) { error_log('[after_response] Mail delivery failed.'); }
            });
            return true;
        }
        $this->swiftlaneFailureLogged = false;
        try {
            $result = parent::send();
            if (!$result) $this->logFailure();
            return $result;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $this->logFailure();
            throw $e;
        }
    }
}
