<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************

/**
 * Customer avatar upload (own profile, or a client edited by staff).
 *
 * Why the rewrite: the old handler read a `current_avatar` field the profile
 * page never sent (PHP warning printed into the JSON) and then inserted into a
 * history table that does not exist on every environment — a fatal AFTER the
 * avatar had been saved, so the customer saw "Connection or processing error"
 * while the photo had actually changed.
 */
ini_set('display_errors', 0);

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once("../../helpers/profile.php");
require_once("../../helpers/ajax_guard.php");
require_login();

header('Content-Type: application/json; charset=UTF-8');

$db   = new Conexion;
$user = new User;

if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['success' => false, 'message' => 'This is a demo version, this action is not allowed.']);
    exit;
}

$targetId = (int) ($_POST['id'] ?? $user->uid);
if (!cdp_profileCanEdit($user, $targetId, 'edit_client_avatar')) {
    echo json_encode(['success' => false, 'message' => 'You can only change your own profile photo.']);
    exit;
}

$db->cdp_query("SELECT id, avatar, fname, lname FROM cdb_users WHERE id = :id LIMIT 1");
$db->bind(':id', $targetId);
$row = $db->cdp_registro();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}

if (empty($_FILES['avatar']['name'])) {
    echo json_encode(['success' => false, 'message' => 'No file was selected. Click on the image to choose your photo.']);
    exit;
}

$stored = cdp_profileStoreImage($_FILES['avatar'], '', (string) $targetId);
if (empty($stored['ok'])) {
    echo json_encode(['success' => false, 'message' => $stored['error']]);
    exit;
}

$db->cdp_query('UPDATE cdb_users SET avatar = :avatar WHERE id = :id');
$db->bind(':avatar', $stored['path']);
$db->bind(':id', $targetId);
if (!$db->cdp_execute()) {
    echo json_encode(['success' => false, 'message' => 'Could not save the new photo.']);
    exit;
}

cdp_profileHistoryLog($targetId, (int) $user->uid, (string) $row->avatar, 'Avatar updated');

if (function_exists('cdp_activityLog')) {
    cdp_activityLog([
        'module'       => 'profile',
        'verb'         => 'update',
        'action'       => 'profile.avatar',
        'label'        => 'Profile · Avatar Changed',
        'entity_type'  => 'user',
        'entity_id'    => $targetId,
        'entity_label' => trim($row->fname . ' ' . $row->lname),
        'summary'      => ($targetId === (int) $user->uid)
            ? 'Changed their own profile photo'
            : 'Changed the profile photo of user #' . $targetId,
        'changes'      => ['avatar' => ['from' => (string) $row->avatar, 'to' => $stored['path']]],
    ]);
}

echo json_encode([
    'success'    => true,
    'message'    => 'Profile photo updated.',
    'avatar_url' => cdp_avatarUrl($stored['path']),
]);
