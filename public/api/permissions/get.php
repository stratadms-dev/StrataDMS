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
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$folder_id = $_GET['folder_id'] ?? null;
if ($folder_id === 'null' || $folder_id === '') $folder_id = 0;
if ($folder_id === null) {
    echo json_encode(['success' => false, 'message' => 'folder_id is required']);
    exit;
}


if (!has_permission($pdo, $_SESSION['user_id'], $folder_id, 'right_manage_security')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_manage_security']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, u.username FROM folder_permissions p JOIN users u ON p.user_id = u.id WHERE p.folder_id = ?");
    $stmt->execute([$folder_id]);
    $permissions = $stmt->fetchAll();
    
    // Also fetch users to populate dropdown
    $usersStmt = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
    $users = $usersStmt->fetchAll();
    
    // Fetch group permissions
    $stmtGroup = $pdo->prepare("SELECT p.*, g.name as group_name FROM folder_group_permissions p JOIN groups g ON p.group_id = g.id WHERE p.folder_id = ?");
    $stmtGroup->execute([$folder_id]);
    $group_permissions = $stmtGroup->fetchAll();
    
    // Fetch groups
    $groupsStmt = $pdo->query("SELECT id, name FROM groups ORDER BY name ASC");
    $groups = $groupsStmt->fetchAll();

    $stmtFolder = $pdo->prepare("SELECT inherit_permissions FROM folders WHERE id = ?");
    $stmtFolder->execute([$folder_id]);
    $inherit_permissions = $stmtFolder->fetchColumn();

    echo json_encode([
        'success' => true, 
        'permissions' => $permissions,
        'group_permissions' => $group_permissions,
        'users' => $users,
        'groups' => $groups,
        'inherit_permissions' => (bool)$inherit_permissions
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
