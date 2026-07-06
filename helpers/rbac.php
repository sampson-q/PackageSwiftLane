<?php
/**
 * Account-management rank rules: who may manage/assign which accounts.
 *
 * Rank comes from the cdb_user_roles type flags (is_superadmin/is_admin/...),
 * with a legacy userlevel fallback when the flag columns are absent, so new
 * department roles rank correctly by their flags without touching this file:
 *   superadmin 4 > admin 3 > staff 2 > driver/agency 1 > client/unknown 0
 *
 * Rules:
 *  - a superadmin manages anyone and assigns any role;
 *  - everyone else manages only accounts of STRICTLY lower rank and assigns
 *    only roles of strictly lower rank than their own.
 */

if (!function_exists('cdp_roleFlagsMap')) {
    function cdp_roleFlagsMap()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        try {
            $db = new Conexion();
            $db->cdp_query("SELECT * FROM cdb_user_roles WHERE rol_active = 1");
            $db->cdp_execute();
            $rows = $db->cdp_registros();
            if ($rows) {
                foreach ($rows as $r) {
                    $map[(int)$r->role_id] = $r;
                }
            }
        } catch (Throwable $e) {
            $map = [];
        }
        return $map;
    }
}

if (!function_exists('cdp_roleRankById')) {
    function cdp_roleRankById($roleId)
    {
        $r = cdp_effectiveRoleRow($roleId);
        if ($r && isset($r->is_superadmin)) {
            if (!empty($r->is_superadmin)) return 4;
            if (!empty($r->is_admin))      return 3;
            if (!empty($r->is_staff))      return 2;
            if (!empty($r->is_driver) || !empty($r->is_agency)) return 1;
            return 0;
        }
        // Legacy fallback (flag columns not present)
        $legacy = [9 => 4, 2 => 3, 4 => 2, 3 => 1, 6 => 1, 1 => 0];
        return $legacy[(int)$roleId] ?? 0;
    }
}

if (!function_exists('cdp_effectiveRoleRow')) {
    /**
     * The role row that defines a role's TYPE — its own if it carries any type
     * flag, otherwise the nearest ancestor up parent_role_id that does. This is
     * what makes a new department role (all flags 0) created under Employee
     * behave as staff automatically, and keeps type consistent if the parent
     * is changed later. Cycle-safe. Returns null if the role isn't in the map.
     */
    function cdp_effectiveRoleRow($roleId)
    {
        $map = cdp_roleFlagsMap();
        $seen = [];
        $cur = (int)$roleId;
        $depth = 0;
        $selfRow = $map[$cur] ?? null;
        while ($cur && !isset($seen[$cur]) && $depth < 20) {
            $seen[$cur] = true;
            $r = $map[$cur] ?? null;
            if (!$r) { break; }
            $hasType = !empty($r->is_superadmin) || !empty($r->is_admin) || !empty($r->is_staff)
                    || !empty($r->is_client) || !empty($r->is_driver) || !empty($r->is_agency);
            if ($hasType) { return $r; }
            $cur = (isset($r->parent_role_id) && !empty($r->parent_role_id)) ? (int)$r->parent_role_id : 0;
            $depth++;
        }
        return $selfRow;
    }
}

if (!function_exists('cdp_roleHasFlag')) {
    function cdp_roleHasFlag($roleId, $flag)
    {
        $r = cdp_effectiveRoleRow($roleId);
        if ($r && isset($r->$flag)) {
            return !empty($r->$flag);
        }
        // Legacy fallback (flag columns absent) mirroring the six seed roles.
        $legacy = [
            'is_superadmin' => [9],
            'is_admin'      => [2, 9],
            'is_staff'      => [2, 4, 9],
            'is_client'     => [1],
            'is_driver'     => [3],
            'is_agency'     => [6],
        ];
        return isset($legacy[$flag]) && in_array((int)$roleId, $legacy[$flag], true);
    }
}

if (!function_exists('cdp_dashboardType')) {
    /**
     * Which dashboard a role lands on: 'admin' | 'client' | 'driver' | 'roles'.
     * Uses the type-defining row (self or nearest typed ancestor), legacy
     * fallback otherwise. New roles inherit their parent's dashboard.
     */
    function cdp_dashboardType($roleId)
    {
        $r = cdp_effectiveRoleRow($roleId);
        if ($r && isset($r->dashboard_type) && $r->dashboard_type !== '') {
            return $r->dashboard_type;
        }
        $legacy = [9 => 'admin', 2 => 'admin', 4 => 'admin', 3 => 'driver', 1 => 'client', 6 => 'roles'];
        return $legacy[(int)$roleId] ?? 'roles';
    }
}

if (!function_exists('cdp_canManageUser')) {
    function cdp_canManageUser($viewer, $targetUserlevel)
    {
        if (!($viewer instanceof User) || empty($viewer->logged_in)) {
            return false;
        }
        $viewerRank = cdp_roleRankById((int)$viewer->userlevel);
        if ($viewerRank >= 4) {
            return true;
        }
        return $viewerRank > cdp_roleRankById((int)$targetUserlevel);
    }
}

if (!function_exists('cdp_canAssignRole')) {
    function cdp_canAssignRole($viewer, $roleId)
    {
        if (!($viewer instanceof User) || empty($viewer->logged_in)) {
            return false;
        }
        $viewerRank = cdp_roleRankById((int)$viewer->userlevel);
        if ($viewerRank >= 4) {
            return true;
        }
        return cdp_roleRankById((int)$roleId) < $viewerRank;
    }
}

if (!function_exists('cdp_roleOptionsHtml')) {
    /**
     * <option> list of roles the viewer may assign. When the target's current
     * role is NOT assignable by the viewer (e.g. self-edit), only that role is
     * rendered, so the form cannot silently change it.
     */
    function cdp_roleOptionsHtml($viewer, $selectedRoleId, $lang = [])
    {
        $selectedRoleId = (int)$selectedRoleId;
        $map = cdp_roleFlagsMap();
        $html = '';
        $selectedAssignable = $selectedRoleId === 0 || cdp_canAssignRole($viewer, $selectedRoleId);
        foreach ($map as $rid => $r) {
            if (!$selectedAssignable && $rid !== $selectedRoleId) {
                continue;
            }
            if ($selectedAssignable && !cdp_canAssignRole($viewer, $rid)) {
                continue;
            }
            $name = isset($lang['role_' . $rid]) ? $lang['role_' . $rid] : $r->role_name;
            $sel = ($rid === $selectedRoleId) ? ' selected="selected"' : '';
            $html .= '<option value="' . $rid . '"' . $sel . '>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</option>\n";
        }
        return $html;
    }
}
