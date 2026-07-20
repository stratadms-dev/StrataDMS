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

session_start();
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../check_permission.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$folder_id = $data['folder_id'] ?? null;
if ($folder_id === 'null' || $folder_id === '') $folder_id = 0;
$permissions = $data['permissions'] ?? [];
$group_permissions = $data['group_permissions'] ?? [];
$inherit_permissions = isset($data['inherit_permissions']) ? (bool)$data['inherit_permissions'] : true;

if ($folder_id === null) {
    echo json_encode(['success' => false, 'message' => 'folder_id is required']);
    exit;
}

if (!has_permission($pdo, $_SESSION['user_id'], $folder_id, 'right_manage_security')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_manage_security']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update inherit_permissions flag
    $stmtFolder = $pdo->prepare("UPDATE folders SET inherit_permissions = ? WHERE id = ?");
    $stmtFolder->execute([$inherit_permissions ? 'true' : 'false', $folder_id]);

    // Fetch all subfolders recursively, stopping at folders with inherit_permissions = FALSE
    $stmtSub = $pdo->prepare("
        WITH RECURSIVE subfolders AS (
            SELECT id, inherit_permissions FROM folders WHERE parent_id = :parent_id
            UNION ALL
            SELECT f.id, f.inherit_permissions FROM folders f
            JOIN subfolders s ON f.parent_id = s.id
            WHERE s.inherit_permissions = TRUE
        )
        SELECT id FROM subfolders WHERE inherit_permissions = TRUE
    ");
    $stmtSub->execute(['parent_id' => $folder_id]);
    $subfolder_ids = $stmtSub->fetchAll(PDO::FETCH_COLUMN);

    // Fetch old users and groups to remove them from subfolders
    $stmtOldUsers = $pdo->prepare("SELECT user_id FROM folder_permissions WHERE folder_id = ?");
    $stmtOldUsers->execute([$folder_id]);
    $oldUserIds = $stmtOldUsers->fetchAll(PDO::FETCH_COLUMN);

    $stmtOldGroups = $pdo->prepare("SELECT group_id FROM folder_group_permissions WHERE folder_id = ?");
    $stmtOldGroups->execute([$folder_id]);
    $oldGroupIds = $stmtOldGroups->fetchAll(PDO::FETCH_COLUMN);

    // Delete existing permissions for this folder
    $stmt = $pdo->prepare("DELETE FROM folder_permissions WHERE folder_id = ?");
    $stmt->execute([$folder_id]);
    
    $stmtGroup = $pdo->prepare("DELETE FROM folder_group_permissions WHERE folder_id = ?");
    $stmtGroup->execute([$folder_id]);

    // Wipe old users and groups from all subfolders to handle removals
    foreach ($subfolder_ids as $sub_id) {
        foreach ($oldUserIds as $u_id) {
            $del = $pdo->prepare("DELETE FROM folder_permissions WHERE folder_id = ? AND user_id = ?");
            $del->execute([$sub_id, $u_id]);
        }
        foreach ($oldGroupIds as $g_id) {
            $del = $pdo->prepare("DELETE FROM folder_group_permissions WHERE folder_id = ? AND group_id = ?");
            $del->execute([$sub_id, $g_id]);
        }
    }
    
    // Insert new permissions
    $insertStmt = $pdo->prepare("
        INSERT INTO folder_permissions (
            user_id, folder_id, scope, 
            right_view, right_add, right_modify, right_delete, 
            right_see_through_redactions, right_manage_security
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertGroupStmt = $pdo->prepare("
        INSERT INTO folder_group_permissions (
            group_id, folder_id, scope, 
            right_view, right_add, right_modify, right_delete, 
            right_see_through_redactions, right_manage_security
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    function pg_bool($val) {
        if ($val === null) return null;
        return $val ? 'true' : 'false';
    }

    // If inherit is enabled and no explicit permissions were submitted,
    // walk up the ancestor chain to find cascading permissions and pull them down.
    if ($inherit_permissions && empty($permissions) && empty($group_permissions)) {
        $stmtParent = $pdo->prepare("SELECT parent_id FROM folders WHERE id = ?");
        $stmtParent->execute([$folder_id]);
        $ancestorId = $stmtParent->fetchColumn();

        // Walk up until we find an ancestor with cascading permissions
        while ($ancestorId !== null && $ancestorId !== false) {
            $stmtAncestorPerms = $pdo->prepare("
                SELECT user_id, scope, right_view, right_add, right_modify, right_delete,
                       right_see_through_redactions, right_manage_security
                FROM folder_permissions
                WHERE folder_id = ? AND scope IN ('this_folder_subfolders_documents', 'this_folder_subfolders')
            ");
            $stmtAncestorPerms->execute([$ancestorId]);
            $ancestorPerms = $stmtAncestorPerms->fetchAll(PDO::FETCH_ASSOC);

            $stmtAncestorGroupPerms = $pdo->prepare("
                SELECT group_id, scope, right_view, right_add, right_modify, right_delete,
                       right_see_through_redactions, right_manage_security
                FROM folder_group_permissions
                WHERE folder_id = ? AND scope IN ('this_folder_subfolders_documents', 'this_folder_subfolders')
            ");
            $stmtAncestorGroupPerms->execute([$ancestorId]);
            $ancestorGroupPerms = $stmtAncestorGroupPerms->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($ancestorPerms) || !empty($ancestorGroupPerms)) {
                $permissions = $ancestorPerms;
                $group_permissions = $ancestorGroupPerms;
                break;
            }

            // Go up another level
            $stmtParent->execute([$ancestorId]);
            $ancestorId = $stmtParent->fetchColumn();
        }
    }

    foreach ($permissions as $p) {
        $scope = $p['scope'] ?? 'this_folder_subfolders_documents';
        
        $insertStmt->execute([
            $p['user_id'],
            $folder_id,
            $scope,
            pg_bool($p['right_view'] ?? null),
            pg_bool($p['right_add'] ?? null),
            pg_bool($p['right_modify'] ?? null),
            pg_bool($p['right_delete'] ?? null),
            pg_bool($p['right_see_through_redactions'] ?? null),
            pg_bool($p['right_manage_security'] ?? null)
        ]);

        if ($scope === 'this_folder_subfolders_documents' || $scope === 'this_folder_subfolders') {
            foreach ($subfolder_ids as $sub_id) {
                // Remove any existing permission for this user on the subfolder (if they are a new user not in oldUserIds)
                $delSub = $pdo->prepare("DELETE FROM folder_permissions WHERE folder_id = ? AND user_id = ?");
                $delSub->execute([$sub_id, $p['user_id']]);

                // Insert the permission for the subfolder
                $insertStmt->execute([
                    $p['user_id'],
                    $sub_id,
                    $scope,
                    pg_bool($p['right_view'] ?? null),
                    pg_bool($p['right_add'] ?? null),
                    pg_bool($p['right_modify'] ?? null),
                    pg_bool($p['right_delete'] ?? null),
                    pg_bool($p['right_see_through_redactions'] ?? null),
                    pg_bool($p['right_manage_security'] ?? null)
                ]);
            }
        }
    }
    
    foreach ($group_permissions as $gp) {
        $scope = $gp['scope'] ?? 'this_folder_subfolders_documents';
        
        $insertGroupStmt->execute([
            $gp['group_id'],
            $folder_id,
            $scope,
            pg_bool($gp['right_view'] ?? null),
            pg_bool($gp['right_add'] ?? null),
            pg_bool($gp['right_modify'] ?? null),
            pg_bool($gp['right_delete'] ?? null),
            pg_bool($gp['right_see_through_redactions'] ?? null),
            pg_bool($gp['right_manage_security'] ?? null)
        ]);

        if ($scope === 'this_folder_subfolders_documents' || $scope === 'this_folder_subfolders') {
            foreach ($subfolder_ids as $sub_id) {
                // Remove any existing permission for this group on the subfolder (if they are a new group)
                $delSub = $pdo->prepare("DELETE FROM folder_group_permissions WHERE folder_id = ? AND group_id = ?");
                $delSub->execute([$sub_id, $gp['group_id']]);

                // Insert the permission for the subfolder
                $insertGroupStmt->execute([
                    $gp['group_id'],
                    $sub_id,
                    $scope,
                    pg_bool($gp['right_view'] ?? null),
                    pg_bool($gp['right_add'] ?? null),
                    pg_bool($gp['right_modify'] ?? null),
                    pg_bool($gp['right_delete'] ?? null),
                    pg_bool($gp['right_see_through_redactions'] ?? null),
                    pg_bool($gp['right_manage_security'] ?? null)
                ]);
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
