<?php
// *************************************************************************
// * "View as" (impersonation) for IT support.                            *
// *                                                                       *
// * Start:  view_as.php?id=<user_id>   — act as that user.               *
// * Stop:   view_as.php?stop=1         — return to the real account.     *
// *                                                                       *
// * Authority is rank-based (helpers/rbac.php::cdp_canViewAs):           *
// *   super admin -> anyone; admin -> anyone but super admins;           *
// *   employee/staff -> clients only; drivers/agencies/clients -> none.  *
// * The real identity is stashed in imp_original_* so the banner's       *
// * "Exit view" always returns to it. Nested impersonation is not        *
// * allowed — the authority is always the stashed original.              *
// *************************************************************************

require_once("loader.php");
require_once("helpers/rbac.php");

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() !== true) {
    header("Location: login.php");
    exit;
}

// Set every identity session key the app reads, from a cdb_users row.
function cdp_viewAsApplyIdentity($row) {
    $_SESSION['userid']    = $row->id;
    $_SESSION['username']  = $row->username;
    $_SESSION['email']     = $row->email;
    $_SESSION['name_off']  = $row->name_off;
    $_SESSION['name']      = $row->fname . ' ' . $row->lname;
    $_SESSION['userlevel'] = $row->userlevel;
    $_SESSION['last']      = $row->lastlogin;
}

// Keep only a safe, same-app relative path (no host, no scheme) so the return
// URL can't be turned into an open redirect.
function cdp_viewAsSafeReturn($url) {
    $url = (string) $url;
    if ($url === '') { return ''; }
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) { return ''; }
    // Reduce to the basename + query so it resolves against this app root.
    $file = basename($path);
    if ($file === '' || strtolower($file) === 'view_as.php' || strtolower($file) === 'login.php') {
        return '';
    }
    $qs = parse_url($url, PHP_URL_QUERY);
    return $file . ($qs ? ('?' . $qs) : '');
}

// --- STOP: restore the real account -------------------------------------
if (isset($_GET['stop'])) {
    $return = cdp_viewAsSafeReturn($_SESSION['imp_return_url'] ?? '');
    if (!empty($_SESSION['imp_original_username'])) {
        $db = new Conexion;
        $db->cdp_query("SELECT * FROM cdb_users WHERE username = :u LIMIT 1");
        $db->bind(':u', $_SESSION['imp_original_username']);
        $db->cdp_execute();
        $orig = $db->cdp_registro();
        if ($orig) {
            cdp_viewAsApplyIdentity($orig);
        }
        unset(
            $_SESSION['imp_original_username'],
            $_SESSION['imp_original_userlevel'],
            $_SESSION['imp_original_name'],
            $_SESSION['imp_original_userid'],
            $_SESSION['imp_return_url']
        );
    }
    // Back to the screen the operator started from (e.g. the customers/users
    // list), not the generic dashboard.
    header("Location: " . ($return !== '' ? $return : 'index.php'));
    exit;
}

// --- START: begin viewing as the target ---------------------------------
$targetId = (int) ($_GET['id'] ?? 0);
if ($targetId <= 0) {
    header("Location: index.php");
    exit;
}

// The AUTHORITY is the stashed original when already impersonating, otherwise
// the current (real) user. This blocks privilege re-escalation via a nested
// view-as from within a lower-privilege impersonated session.
if (!empty($_SESSION['imp_original_username'])) {
    $authUserlevel = (int) $_SESSION['imp_original_userlevel'];
    $authUserid    = (int) $_SESSION['imp_original_userid'];
    $authUsername  = (string) $_SESSION['imp_original_username'];
    $authName      = (string) $_SESSION['imp_original_name'];
} else {
    $authUserlevel = (int) $user->userlevel;
    $authUserid    = (int) $user->uid;
    $authUsername  = (string) $user->username;
    $authName      = (string) $user->name;
}

$db = new Conexion;
$db->cdp_query("SELECT * FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $targetId);
$db->cdp_execute();
$target = $db->cdp_registro();

if (!$target) {
    $_SESSION['view_as_error'] = 'That account could not be found.';
    header("Location: index.php");
    exit;
}

// Never view-as yourself.
if ((int) $target->id === $authUserid) {
    $_SESSION['view_as_error'] = 'You cannot view as your own account.';
    header("Location: index.php");
    exit;
}

// Enforce the role hierarchy against the AUTHORITY, not the acting identity.
if (!cdp_canViewAs($authUserlevel, (int) $target->userlevel)) {
    $_SESSION['view_as_error'] = 'You are not allowed to view as that account.';
    header("Location: index.php");
    exit;
}

// Stash the real identity once (first entry into impersonation), along with
// the screen the operator launched from so "Exit View" returns there.
if (empty($_SESSION['imp_original_username'])) {
    $_SESSION['imp_original_username']  = $authUsername;
    $_SESSION['imp_original_userlevel'] = $authUserlevel;
    $_SESSION['imp_original_name']      = $authName;
    $_SESSION['imp_original_userid']    = $authUserid;
    $_SESSION['imp_return_url']         = cdp_viewAsSafeReturn($_SERVER['HTTP_REFERER'] ?? '');
}

// Swap into the target and land on the dashboard as them.
cdp_viewAsApplyIdentity($target);
header("Location: index.php");
exit;
