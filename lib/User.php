<?php
// *************************************************************************
// *                                                                       *
// * DEPRIXA PRO -  Integrated Web Shipping System                         *
// * Copyright (c) JAOMWEB. All Rights Reserved                            *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: support@jaom.info                                              *
// * Website: http://www.jaom.info                                         *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.                              *
// * If you Purchased from Codecanyon, Please read the full License from   *
// * here- http://codecanyon.net/licenses/standard                         *
// *                                                                       *
// *************************************************************************



class User
{

    public  $logged_in = null;
    public  $uid = 0;
    public  $userid = 0;
    public  $username;
    public  $email;
    public  $name;
    public  $userlevel;
    public  $last;
    public  $locker;
    public  $name_off;
    private $db;
    private $result;
    public  $sWhere;
    public  $sql;
    public  $errors = array();
    public  $permissions   = array();

    function __construct()
    {
        $this->db = new Conexion;
        $this->cdp_startSession();
        $this->cdp_checkInactivity();
        
        // Auto-load permissions if user is logged in and permissions not loaded
        if ($this->logged_in && empty($this->permissions)) {
            $this->cdp_getUserPermissions();
        }
    }

    /**
     * Users::cdp_startSession()
     */
    private function cdp_startSession()
    {
        if (strlen(session_id()) < 1)
            session_start();

        $this->logged_in = $this->cdp_loginCheck();

        if (!$this->logged_in) {
            $this->username = $_SESSION['username'] = "Guest";
            $this->userlevel = 0;
        }
    }

    /**
     * Users::cdp_checkInactivity()
     */
    private function cdp_checkInactivity()
    {
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1440)) {
            if (isset($_SESSION['userlevel']) && $_SESSION['userlevel'] == 1) {
                $this->cdp_logout();
                $this->cdp_clearBrowserCache();
                header("Location: login.php"); // Redirige al usuario a la página de inicio de sesión
                exit();
            }
        }
        $_SESSION['LAST_ACTIVITY'] = time(); // actualiza la hora de la última actividad
    }

    /**
     * Users::cdp_loginCheck()
     */
    public function cdp_loginCheck()
    {
        if (isset($_SESSION['username']) && $_SESSION['username'] != "Guest") {
            $row = $this->cdp_getUserInfo($_SESSION['username']);
            $this->uid = $row->id;
            $this->username = $row->username;
            $this->locker = $row->locker;
            $this->name_off = $row->name_off;
            $this->email = $row->email;
            $this->name = $row->fname . ' ' . $row->lname;
            $this->userlevel = $row->userlevel;
            $this->last = $row->lastlogin;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Users::cdp_is_Admin()
     */
    public function cdp_is_Admin()
    {
        return in_array($this->userlevel, [9,2]);
    }

    /**
     * Users::cdp_login()
     */
    public function cdp_login($username, $pass, $options = array()) {
        $status = 0;

        if ($username === '' && $pass === '') {
            $this->errors[] = 'Enter a valid username and password.';
        } else {
            $status = $this->cdp_checkStatus($username, $pass);
            if ($status == 0) {
                $this->errors[] = 'Incorrect username or password.';
            } elseif ($status == 2) {
                $this->errors[] = 'Your account is not activated.';
            }
        }

        if ($status != 1) {
            return false;
        }

        $user = $this->cdp_getUserInfo($username);

        if (empty($options['otp_service'])) {
            // OTP not configured — finalize directly
            return $this->cdp_finalizeLogin($user);
        }

        $otpService = $options['otp_service'];
        $rememberMe = !empty($options['remember_me']);

        if ($otpService->isTrustedDevice($user->id)) {
            return $this->cdp_finalizeLogin($user);
        }

        // Device is not trusted — issue an OTP challenge
        $challenge = $otpService->createChallenge($user->id, 'login', array(
            'remember_me' => $rememberMe,
            'email'       => $user->email,
        ));

        if (!empty($challenge['blocked'])) {
            // Rate-limited. If a code is still pending, reuse it so the user lands
            // on a working OTP page (with the code already delivered + a live timer)
            // instead of a dead one built around id 0 / a null code. Otherwise the
            // block is terminal (e.g. hard cap reached) — surface the error.
            if (!empty($challenge['existing_id'])) {
                $_SESSION['otp_login_challenge'] = (int) $challenge['existing_id'];
                $_SESSION['otp_login_user_id']   = $user->id;
                $_SESSION['otp_login_remember']  = $rememberMe ? 1 : 0;
                return 'otp_required';
            }
            $this->errors[] = $challenge['error'];
            return false;
        }

        // Fresh challenge — deliver it on both channels
        $otpService->sendOtpEmail($user->email, $user->fname . ' ' . $user->lname, $challenge['code'], 'login');
        $otpService->sendOtpWhatsApp($user->email, $user->fname . ' ' . $user->lname, $challenge['code'], 'login');

        $_SESSION['otp_login_challenge'] = $challenge['id'];
        $_SESSION['otp_login_user_id']   = $user->id;
        $_SESSION['otp_login_remember']  = $rememberMe ? 1 : 0;

        return 'otp_required';
    }

    public function cdp_finalizeLoginById($userId) {
        $this->db->cdp_query('SELECT * FROM cdb_users WHERE id=:id LIMIT 1');
        $this->db->bind(':id', (int)$userId);
        $user = $this->db->cdp_registro();
        if (!$user) {
            return false;
        }
        return $this->cdp_finalizeLogin($user);
    }

    private function cdp_finalizeLogin($user) {
        $_SESSION['userid'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['email'] = $user->email;
        $_SESSION['name_off'] = $user->name_off;
        $_SESSION['name'] = $user->fname . ' ' . $user->lname;
        $_SESSION['userlevel'] = $user->userlevel;
        $_SESSION['last'] = $user->lastlogin;

        $this->uid = $user->id;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->name_off = $user->name_off;
        $this->name = $user->fname . ' ' . $user->lname;
        $this->userlevel = $user->userlevel;
        $this->last = $user->lastlogin;

        $this->db->cdp_query('UPDATE cdb_users SET lastlogin=:lastlogin, lastip=:lastip WHERE username=:user');
        $this->db->bind(':lastlogin', date("Y-m-d H:i:s"));
        $this->db->bind(':lastip', trim($_SERVER['REMOTE_ADDR']));
        $this->db->bind(':user', $user->username);
        $this->db->cdp_execute();

        return true;
    }


    /**
     * Users::cdp_checkStatus()
     */
    public function cdp_checkStatus($username, $password)
    {
        $username = trim($username);
        $password = trim($password);

        $this->db->cdp_query('SELECT * FROM cdb_users WHERE username=:user OR email=:user');
        $this->db->bind(':user', $username);
        $this->db->cdp_execute();
        $user = $this->db->cdp_registro();
        $numrows = $this->db->cdp_rowCount();

        if ($numrows == 1) {
            if (password_verify($password, $user->password)) {
                return $user->active == 1 ? 1 : 2;
            }
        }
        return 0;
    }

    /**
     * Users::cdp_logout()
     */
    public function cdp_logout()
    {
        // Clear all session variables
        $_SESSION = array();

        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy the session
        session_destroy();

        // Clear instance variables
        $this->logged_in = false;
        $this->username = "Guest";
        $this->userlevel = 0;

        // Close database connection
        $this->db->cdp_cerrarConexion();
    }



    /**
     * Users::getUserPermissions()
     */
    public function cdp_getUserPermissions()
    {
        // Superadmin siempre tiene todos los permisos
        if ($this->userlevel == 9) {
            $this->permissions = ['*']; // Wildcard para superadmin
            return $this->permissions;
        }

        // Resolve the role's parent chain [self, parent, grandparent, ...].
        // Cycle-safe (visited set) and depth-capped. parent_role_id may be
        // absent on un-migrated envs → the chain is just [self].
        $chain = [];
        try {
            $visited = [];
            $current = (int)$this->userlevel;
            $depth = 0;
            while ($current && !isset($visited[$current]) && $depth < 20) {
                $visited[$current] = true;
                $chain[] = $current;
                $this->db->cdp_query("SELECT parent_role_id FROM cdb_user_roles WHERE role_id = :rid AND rol_active = 1");
                $this->db->bind(':rid', $current);
                $this->db->cdp_execute();
                $prow = $this->db->cdp_registro();
                $current = ($prow && !empty($prow->parent_role_id)) ? (int)$prow->parent_role_id : 0;
                $depth++;
            }
        } catch (Throwable $e) {
            $chain = [(int)$this->userlevel];
        }
        if (empty($chain)) {
            $chain = [(int)$this->userlevel];
        }

        // Fetch every explicit grant across the chain, then apply NEAREST-WINS:
        // walking child→ancestor, the first role that has an explicit row for
        // an action decides it (permitted=1 grants, permitted=0 blocks a
        // grant a further ancestor would give). Roles at the same distance are
        // the same role, so ordering within a level is irrelevant.
        $in = implode(',', array_map('intval', $chain));
        $sql = "
            SELECT rp.role_id, ma.action_name AS permission_name, rp.permitted
            FROM cdb_user_role_permissions rp
            JOIN cdb_user_roles r ON rp.role_id = r.role_id
            JOIN cdb_user_module_actions ma ON rp.module_action_id = ma.id
            WHERE rp.role_id IN ($in)
              AND r.rol_active = 1
        ";
        $this->db->cdp_query($sql);
        $this->db->cdp_execute();
        $rows = $this->db->cdp_registros();

        $rank = array_flip($chain); // role_id => distance (0 = self)
        $decided = []; // action_name => [distance, permitted]
        if ($rows) {
            foreach ($rows as $row) {
                if (empty($row->permission_name)) {
                    continue;
                }
                $name = $row->permission_name;
                $dist = $rank[(int)$row->role_id] ?? PHP_INT_MAX;
                if (!isset($decided[$name]) || $dist < $decided[$name][0]) {
                    $decided[$name] = [$dist, (int)$row->permitted];
                }
            }
        }
        $perms = [];
        foreach ($decided as $name => $info) {
            if ($info[1] === 1) {
                $perms[] = $name;
            }
        }

        // Per-user overrides (cdb_user_permission_overrides) on top of the role:
        // permitted=1 adds an action for this user, permitted=0 removes one the
        // role grants. try/catch: envs where the table doesn't exist yet must
        // keep working on role permissions alone.
        try {
            $this->db->cdp_query("
                SELECT ma.action_name, o.permitted
                FROM cdb_user_permission_overrides o
                JOIN cdb_user_module_actions ma ON ma.id = o.module_action_id
                WHERE o.user_id = :uid
            ");
            $this->db->bind(':uid', (int)$this->uid);
            $this->db->cdp_execute();
            $overrides = $this->db->cdp_registros();
            if ($overrides) {
                foreach ($overrides as $ov) {
                    if ((int)$ov->permitted === 1) {
                        $perms[] = $ov->action_name;
                    } else {
                        $perms = array_diff($perms, [$ov->action_name]);
                    }
                }
                $perms = array_values(array_unique($perms));
            }
        } catch (Throwable $e) {
            // overrides table absent — role permissions only
        }

        $this->permissions = $perms;
        return $this->permissions;
    }



    public function cdp_hasPermission(...$permissions)
    {
        // Superadmin siempre tiene acceso
        if ($this->userlevel == 9 || in_array('*', $this->permissions)) {
            return true;
        }

        // Auto-load permissions if empty
        if (empty($this->permissions)) {
            $this->cdp_getUserPermissions();
        }

        // Verifica si el primer argumento es un array
        if (count($permissions) === 1 && is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        // Verifica si el usuario tiene al menos uno de los permisos especificados
        foreach ($permissions as $permission) {
            if (in_array($permission, $this->permissions)) {
                return true;
            }
        }

        return false;
    }



    /**
     * Users::cdp_clearBrowserCache()
     */
    private function cdp_clearBrowserCache()
    {
        header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    /**
     * Users::cdp_getUserInfo()
     */
    public function cdp_getUserInfo($username)
    {
        $username = trim($username);

        $this->db->cdp_query('SELECT * FROM cdb_users WHERE username=:user OR email=:user');

        $this->db->bind(':user', $username);

        $this->db->cdp_execute();
        return $user = $this->db->cdp_registro();
    } 


    /**
     * Users::cdp_getUserData()
     */
    public function cdp_getUserData()
    {

        $this->db->cdp_query("SELECT *,
                       DATE_FORMAT(created, '%a. %d, %M %Y') as cdate,
                        DATE_FORMAT(lastlogin, '%a. %d, %M %Y') as ldate
                       FROM cdb_users WHERE id=:uid");

        $this->db->bind(':uid', $this->uid);

        $this->db->cdp_execute();
        return $user = $this->db->cdp_registro();
    }

    /**
     * Users::cdp_usernameExists()
     */
    public function cdp_usernameExists($username)
    {
        $username = trim($username);
        if (strlen($username) < 4)
            return 1;

        $this->db->cdp_query("SELECT username FROM cdb_users where username = :user LIMIT 1");

        $this->db->bind(':user', $username);

        $this->db->cdp_execute();

        return $numrows = $this->db->cdp_rowCount();
    }

    /**
     * User::cdp_emailExists()
     */
    public function cdp_emailExists($email, $id = null)
    {

        $where = '';
        if ($id != null) {

            $where = "and id!='$id'";
        }

        $this->db->cdp_query("SELECT email FROM cdb_users where email = :email $where LIMIT 1");

        $this->db->bind(':email', trim($email));

        $this->db->cdp_execute();


        if ($this->db->cdp_rowCount() == 1) {
            return true;
        } else {

            return false;
        }
    }



        /**
     * User::cdp_ccnumberExists()
     */
    public function cdp_ccnumberExists($document_number, $id = null)
    {

        $where = '';
        if ($id != null) {

            $where = "and id!='$id'";
        }

        $this->db->cdp_query("SELECT document_number FROM cdb_users where document_number = :document_number $where LIMIT 1");

        $this->db->bind(':document_number', trim($document_number));

        $this->db->cdp_execute();


        if ($this->db->cdp_rowCount() == 1) {
            return true;
        } else {

            return false;
        }
    }



    public function cdp_emailExistsRecipients($email, $id = null)
    {

        $where = '';
        if ($id != null) {

            $where = "and id!='$id'";
        }

        $this->db->cdp_query("SELECT email FROM cdb_recipients where email = :email $where LIMIT 1");

        $this->db->bind(':email', trim($email));

        $this->db->cdp_execute();


        if ($this->db->cdp_rowCount() == 1) {
            return true;
        } else {

            return false;
        }
    }


    /**
     * User::cdp_isValidEmail()
     */
    public function cdp_isValidEmail($email)
    {
        if (function_exists('filter_var')) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return true;
            } else
                return false;
        } else
            return preg_match('/^[a-zA-Z0-9._+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/', $email);
    }


    /**
     * Users::cdp_getUserLevels()
     * 
     */
    public function cdp_getUserLevels($langs, $level = false)
    {
        // Conectar a la base de datos
        $db = new Conexion();
        
        // Consultar los roles activos
        $db->cdp_query("SELECT role_id, role_name FROM cdb_user_roles WHERE rol_active = 1");
        $roles = $db->cdp_registros();

        $list = '';
        foreach ($roles as $role) {
            $role_id = $role->role_id;
            $role_name = $role->role_name;

            // Comprobar si el nivel coincide con el seleccionado
            $selected = ($role_id == $level) ? 'selected="selected"' : '';
            $list .= "<option $selected value=\"$role_id\">$role_name</option>\n";
        }

        return $list;
    }


    // used All Drivers
    public function cdp_userAllDriver()
    {

        // query to select all user records
        $sql = "SELECT * FROM cdb_users WHERE userlevel='3' AND active='1'";

        $this->db->cdp_query($sql);
        $this->db->cdp_execute();
        $row = $this->db->cdp_registros();

        return $row;
    }

    public function cdp_getUserIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // In case of multiple IPs, take the first one
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}
