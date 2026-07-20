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
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/check_permission.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$folderId = $data['id'] ?? null;
$newName = $data['name'] ?? null;

if (!$folderId || !$newName || trim($newName) === '') {
    echo json_encode(['success' => false, 'message' => 'Folder ID and new name are required.']);
    exit;
}

try {
    // Fetch old name
    $stmtOld = $pdo->prepare("SELECT name FROM folders WHERE id = :id");
    $stmtOld->execute(['id' => $folderId]);
    $oldFolder = $stmtOld->fetch();
    $oldName = $oldFolder ? $oldFolder['name'] : 'Unknown Folder';
    
    if (!has_permission($pdo, $_SESSION['user_id'], $folderId, 'right_modify')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE folders SET name = :name, updated_at = NOW(), updated_by = :user_id WHERE id = :id");
    $stmt->execute(['name' => trim($newName), 'user_id' => $_SESSION['user_id'], 'id' => $folderId]);

    // Log Activity
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'rename_folder', :item_name, :target_name)");
    $stmtLog->execute([
        'user_id' => $_SESSION['user_id'],
        'item_name' => $oldName,
        'target_name' => trim($newName)
    ]);

    echo json_encode(['success' => true, 'message' => 'Folder renamed.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error during rename.']);
}
?>
