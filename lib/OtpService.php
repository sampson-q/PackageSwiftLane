<?php
$projectRoot = dirname(__DIR__);
    require_once $projectRoot . '/helpers/querys.php';
    require_once $projectRoot . '/lib/Core.php';
    require_once $projectRoot . '/helpers/functions.php';
    require_once $projectRoot . '/helpers/phpmailer/class.phpmailer.php';
    require_once $projectRoot . '/helpers/phpmailer/class.smtp.php';
    require_once $projectRoot . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php';
    class OtpService {
        private $db;
        private $core;
        private $user;

        public function __construct() {
            $this->db = new Conexion();
            $this->core = new Core();
            $this->user = new User();
            $this->ensureTables();
        }

        private function ensureTables() {
            $this->db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_auth_otp_challenges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                purpose VARCHAR(20) NOT NULL,
                channel VARCHAR(20) NOT NULL DEFAULT 'email',
                code_hash VARCHAR(255) NOT NULL,
                code_salt VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                max_attempts TINYINT NOT NULL DEFAULT 5,
                attempts TINYINT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                metadata TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_user_purpose_status (user_id, purpose, status),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
            $this->db->cdp_execute();

            $this->db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_auth_trusted_devices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                selector VARCHAR(24) NOT NULL UNIQUE,
                verifier_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                last_used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                INDEX idx_user_expires (user_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
            $this->db->cdp_execute();

            $this->db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_password_reset_sessions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_user_expiry (user_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
            $this->db->cdp_execute();
        }

        public function createChallenge($userId, $purpose, array $metadata = [], $ttlSeconds = 300) {
            // Invalidate any existing pending challenge for this user + purpose
            $this->db->cdp_query("UPDATE cdb_auth_otp_challenges
                SET status='replaced', updated_at=:now
                WHERE user_id=:user_id AND purpose=:purpose AND status='pending'");
            $this->db->bind(':now', date('Y-m-d H:i:s'));
            $this->db->bind(':user_id', (int) $userId);
            $this->db->bind(':purpose', $purpose);
            $this->db->cdp_execute();

            $code = (string) random_int(100000, 999999);
            $salt = bin2hex(random_bytes(16));
            $hash = hash('sha256', $code . '|' . $salt . '|' . $this->secret());
            $now  = date('Y-m-d H:i:s');
            $exp  = date('Y-m-d H:i:s', time() + $ttlSeconds);

            $this->db->cdp_query("INSERT INTO cdb_auth_otp_challenges
                (user_id, purpose, channel, code_hash, code_salt, expires_at, metadata, created_at, updated_at)
                VALUES (:user_id, :purpose, 'email', :code_hash, :code_salt, :expires_at, :metadata, :created_at, :updated_at)");
            $this->db->bind(':user_id',    (int) $userId);
            $this->db->bind(':purpose',    $purpose);
            $this->db->bind(':code_hash',  $hash);
            $this->db->bind(':code_salt',  $salt);
            $this->db->bind(':expires_at', $exp);
            $this->db->bind(':metadata',   json_encode($metadata));
            $this->db->bind(':created_at', $now);
            $this->db->bind(':updated_at', $now);
            $this->db->cdp_execute();

            return [
                'id'         => (int) $this->db->dbh->lastInsertId(),
                'code'       => $code,
                'expires_at' => $exp,
            ];
        }

        public function verifyChallenge($challengeId, $code, $purpose) {
            $this->db->cdp_query("SELECT * FROM cdb_auth_otp_challenges WHERE id=:id AND purpose=:purpose");
            $this->db->bind(':id', (int) $challengeId);
            $this->db->bind(':purpose', $purpose);
            $row = $this->db->cdp_registro();

            if (!$row || $row->status !== 'pending') {
                return ['ok' => false, 'error' => 'Invalid or used OTP request.'];
            }
            if (strtotime($row->expires_at) < time()) {
                $this->setChallengeStatus($row->id, 'expired');
                return ['ok' => false, 'error' => 'OTP expired. Request a new code.'];
            }
            if ((int) $row->attempts >= (int) $row->max_attempts) {
                $this->setChallengeStatus($row->id, 'locked');
                return ['ok' => false, 'error' => 'Too many attempts. Request a new code.'];
            }

            $expected = hash('sha256', trim($code) . '|' . $row->code_salt . '|' . $this->secret());
            if (!hash_equals($row->code_hash, $expected)) {
                $this->db->cdp_query("UPDATE cdb_auth_otp_challenges
                    SET attempts=attempts+1, updated_at=:updated_at WHERE id=:id");
                $this->db->bind(':updated_at', date('Y-m-d H:i:s'));
                $this->db->bind(':id', (int) $row->id);
                $this->db->cdp_execute();
                return ['ok' => false, 'error' => 'Incorrect OTP.'];
            }

            $this->setChallengeStatus($row->id, 'verified');
            return [
                'ok'       => true,
                'user_id'  => (int) $row->user_id,
                'metadata' => json_decode($row->metadata, true) ?: [],
            ];
        }

        private function setChallengeStatus($id, $status) {
            $this->db->cdp_query("UPDATE cdb_auth_otp_challenges
                SET status=:status, updated_at=:updated_at WHERE id=:id");
            $this->db->bind(':status',     $status);
            $this->db->bind(':updated_at', date('Y-m-d H:i:s'));
            $this->db->bind(':id',         (int) $id);
            $this->db->cdp_execute();
        }

        public function sendOtpEmail($email, $name, $code, $purpose) {
            $emailTplId = ($purpose === 'password reset') ? 27 : (($purpose === 'login') ? 28 : 30);
            $emailTpl   = cdp_getEmailTemplatesdg1i4($emailTplId);

            if ($emailTpl) {
                $emailBody = str_replace(
                    ['[USERNAME]', '[SITE_NAME]', '[PASSWORD]', '[IP]', '[URL]', '[TTL]'],
                    [
                        ucfirst($name),
                        $this->core->site_name,
                        $code,
                        (new User())->cdp_getUserIP(),
                        $this->core->site_url,
                        '5 minutes',
                    ],
                    $emailTpl->body
                );

                $emailBody = cdp_cleanOutx($emailBody);
                $subject   = mb_convert_encoding($emailTpl->subject, 'UTF-8', 'UTF-8');

                if ($this->core->mailer === 'PHP') {
                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: {$this->core->site_name} <{$this->core->email_address}>\r\n";

                    if (!mail($email, $subject, $emailBody, $headers)) {
                        return ['ok' => false, 'error' => 'PHP Mail() failed.'];
                    }

                } elseif ($this->core->mailer === 'SMTP') {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = $this->core->smtp_host;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $this->core->smtp_user;
                        $mail->Password   = $this->core->smtp_password;
                        $mail->SMTPSecure = $this->core->smtp_secure ?: 'tls';
                        $mail->Port       = $this->core->smtp_port;

                        $mail->setFrom($this->core->email_address, $this->core->smtp_names);
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->CharSet = 'UTF-8';
                        $mail->Subject = $subject;
                        $mail->Body    = "<html><body>{$emailBody}</body></html>";

                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer'       => false,
                                'verify_peer_name'  => false,
                                'allow_self_signed' => true,
                            ],
                        ];

                        $mail->send();
                    } catch (\Exception $e) {
                        return ['ok' => false, 'error' => 'SMTP send error: ' . $mail->ErrorInfo];
                    }
                } else {
                    return ['ok' => false, 'error' => 'Unknown mailer configured.'];
                }
            } else {
                return ['ok' => false, 'error' => "Email template #{$emailTplId} not found."];
            }

            return ['ok' => true];
        }

        public function sendOtpWhatsApp($email, $name, $code, $purpose) {
            $userInfo = $this->user->cdp_getUserInfo($email);

            $whatsappTemplateId = ($purpose === 'password reset') ? 9 : 10;
            $tpl = getTemplateWhatsApp($whatsappTemplateId);

            if ($tpl) {
                $body = str_replace(
                    ['[USERNAME]', '[SITE_NAME]', '[PASSWORD]', '[IP]', '[TTL]'],
                    [
                        ucfirst($name),
                        $this->core->site_name,
                        $code,
                        (new User())->cdp_getUserIP(),
                        '5 minutes',
                    ],
                    $tpl->body
                );
                sendNotificationWhatsApp_v2($userInfo, $body);
            } else {
                return ['ok' => false, 'error' => "WhatsApp template #{$whatsappTemplateId} not found."];
            }

            return ['ok' => true];
        }

        public function isTrustedDevice($userId) {
            if (empty($_COOKIE['trusted_device'])) return false;

            $parts = explode(':', $_COOKIE['trusted_device']);
            if (count($parts) !== 2) return false;
            [$selector, $verifier] = $parts;

            $this->db->cdp_query("SELECT * FROM cdb_auth_trusted_devices
                WHERE selector=:selector AND user_id=:user_id AND revoked_at IS NULL LIMIT 1");
            $this->db->bind(':selector', $selector);
            $this->db->bind(':user_id',  (int) $userId);
            $row = $this->db->cdp_registro();

            if (!$row || strtotime($row->expires_at) < time()) return false;

            $hash = hash('sha256', $verifier . '|' . $this->secret());
            if (!hash_equals($row->verifier_hash, $hash)) return false;

            // Refresh last-used timestamp
            $this->db->cdp_query("UPDATE cdb_auth_trusted_devices SET last_used_at=:last_used_at WHERE id=:id");
            $this->db->bind(':last_used_at', date('Y-m-d H:i:s'));
            $this->db->bind(':id',           (int) $row->id);
            $this->db->cdp_execute();

            return true;
        }

        public function rememberTrustedDevice($userId) {
            $selector = bin2hex(random_bytes(12));   // 24 hex chars
            $verifier = bin2hex(random_bytes(32));   // 64 hex chars
            $hash     = hash('sha256', $verifier . '|' . $this->secret());

            // FIX: was 90 days — changed to 60 days in both the DB record and the cookie
            $ttl     = 60 * 86400;
            $expires = date('Y-m-d H:i:s', time() + $ttl);

            $this->db->cdp_query("INSERT INTO cdb_auth_trusted_devices
                (user_id, selector, verifier_hash, expires_at, created_at)
                VALUES (:user_id, :selector, :verifier_hash, :expires_at, :created_at)");
            $this->db->bind(':user_id',       (int) $userId);
            $this->db->bind(':selector',      $selector);
            $this->db->bind(':verifier_hash', $hash);
            $this->db->bind(':expires_at',    $expires);
            $this->db->bind(':created_at',    date('Y-m-d H:i:s'));
            $this->db->cdp_execute();

            setcookie('trusted_device', $selector . ':' . $verifier, [
                'expires'  => time() + $ttl,
                'path'     => '/',
                'httponly' => true,
                'secure'   => true,   // FIX: was missing — cookie must only travel over HTTPS
                'samesite' => 'Lax',
            ]);
        }

        /**
         * Revoke ALL trusted devices for a user and clear the browser cookie.
         * Call this immediately after a successful password reset.
         *
         * @param int $userId
         */
        public function revokeAllTrustedDevices($userId) {
            $this->db->cdp_query("UPDATE cdb_auth_trusted_devices
                SET revoked_at=:now
                WHERE user_id=:user_id AND revoked_at IS NULL");
            $this->db->bind(':now',     date('Y-m-d H:i:s'));
            $this->db->bind(':user_id', (int) $userId);
            $this->db->cdp_execute();

            // Expire the cookie immediately in the current browser
            setcookie('trusted_device', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'secure'   => true,
                'samesite' => 'Lax',
            ]);
        }

        // ─── Password-reset sessions ──────────────────────────────────────────

        public function createResetSession($userId, $ttl = 900) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token . '|' . $this->secret());

            $this->db->cdp_query("INSERT INTO cdb_password_reset_sessions
                (user_id, token_hash, expires_at, created_at)
                VALUES (:user_id, :token_hash, :expires_at, :created_at)");
            $this->db->bind(':user_id',    (int) $userId);
            $this->db->bind(':token_hash', $hash);
            $this->db->bind(':expires_at', date('Y-m-d H:i:s', time() + $ttl));
            $this->db->bind(':created_at', date('Y-m-d H:i:s'));
            $this->db->cdp_execute();

            return $token;
        }

        public function consumeResetSession($token) {
            $hash = hash('sha256', $token . '|' . $this->secret());

            $this->db->cdp_query("SELECT * FROM cdb_password_reset_sessions
                WHERE token_hash=:token_hash AND consumed_at IS NULL ORDER BY id DESC LIMIT 1");
            $this->db->bind(':token_hash', $hash);
            $row = $this->db->cdp_registro();

            if (!$row || strtotime($row->expires_at) < time()) return false;

            $this->db->cdp_query("UPDATE cdb_password_reset_sessions
                SET consumed_at=:consumed_at WHERE id=:id");
            $this->db->bind(':consumed_at', date('Y-m-d H:i:s'));
            $this->db->bind(':id',          (int) $row->id);
            $this->db->cdp_execute();

            return (int) $row->user_id;
        }

        // ─── Internal helpers ─────────────────────────────────────────────────

        private function secret() {
            return hash('sha256', CDP_DB_NAME . '|' . CDP_DB_USER . '|otp');
        }
    }