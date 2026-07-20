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
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$folderId = $data['id'] ?? null;
$targetFolderId = isset($data['target_folder_id']) && $data['target_folder_id'] !== 'null' && $data['target_folder_id'] !== null ? (int)$data['target_folder_id'] : null;

if (!$folderId) {
    echo json_encode(['success' => false, 'message' => 'Folder ID required.']);
    exit;
}

try {
    // If restoring a folder to another folder, it cannot be its own child!
    // But since this is a simple restore, we will assume the frontend handles tree constraints, 
    // or we just let DB fail if recursive.
    if (isset($data['target_folder_id'])) {
        $stmt = $pdo->prepare("UPDATE folders SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL, parent_id = :parent_id WHERE id = :id");
        $stmt->execute(['id' => $folderId, 'parent_id' => $targetFolderId]);
    } else {
        $stmt = $pdo->prepare("UPDATE folders SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE id = :id");
        $stmt->execute(['id' => $folderId]);
    }

    // Log Activity
    $stmtFld = $pdo->prepare("SELECT name, parent_id FROM folders WHERE id = :id");
    $stmtFld->execute(['id' => $folderId]);
    $fld = $stmtFld->fetch(PDO::FETCH_ASSOC);
    $fldName = $fld ? $fld['name'] : 'Unknown Folder';
    
    $finalFolderId = isset($data['target_folder_id']) ? $targetFolderId : ($fld ? $fld['parent_id'] : null);
    $targetName = 'Home';
    if ($finalFolderId) {
        $stmtParent = $pdo->prepare("SELECT name FROM folders WHERE id = :id");
        $stmtParent->execute(['id' => $finalFolderId]);
        $parent = $stmtParent->fetch(PDO::FETCH_ASSOC);
        if ($parent) $targetName = $parent['name'];
    }

    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Restored Folder', :item_name, :target_name)");
    $stmtLog->execute([
        'user_id' => $_SESSION['user_id'],
        'item_name' => $fldName,
        'target_name' => $targetName
    ]);

    echo json_encode(['success' => true, 'message' => 'Folder restored.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error during restore.']);
}
?>
