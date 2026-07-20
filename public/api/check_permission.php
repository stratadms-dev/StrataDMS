<?php
/**
 * StrataDMS
 * Copyright (C) 2026 James Briscoe
 *
 * StrataDMS is free software; You can redistribute it and/or modify it under the terms of:
 *   - the GNU Affero General Public License version 3 as published by the Free Software Foundation.
 *
 * StrataDMS is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

function has_permission($pdo, $user_id, $folder_id, $right) {
    // Admin override
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$user_id]);
    if ($stmtRole->fetchColumn() === 'admin') {
        return true;
    }
    
    // Root folder access
    if ($folder_id === null || $folder_id === 'null' || $folder_id === '') {
        $folder_id = 0; 
    }
    
    // Query effective permissions
    $validRights = ['right_view', 'right_add', 'right_modify', 'right_delete', 'right_see_through_redactions', 'right_manage_security'];
    if (!in_array($right, $validRights)) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT $right FROM vw_effective_permissions WHERE user_id = ? AND folder_id = ?");
    $stmt->execute([$user_id, $folder_id]);
    $result = $stmt->fetchColumn();
    
    return $result === true;
}
?>
