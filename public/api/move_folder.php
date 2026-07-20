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

// Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$folderId = $data['folder_id'] ?? null;
$targetFolderId = isset($data['target_folder_id']) ? $data['target_folder_id'] : null;

if (!$folderId) {
    echo json_encode(['success' => false, 'message' => 'Folder ID required.']);
    exit;
}

// Optional: Prevent moving a folder into itself
if ($folderId == $targetFolderId) {
    echo json_encode(['success' => false, 'message' => 'Cannot move a folder into itself.']);
    exit;
}

try {
    // Fetch folder name
    $stmtFolderFetch = $pdo->prepare("SELECT name FROM folders WHERE id = :id");
    $stmtFolderFetch->execute(['id' => $folderId]);
    $folderData = $stmtFolderFetch->fetch();
    $itemName = $folderData ? $folderData['name'] : 'Unknown Folder';

    // Fetch target folder name
    $targetName = 'Home';
    if ($targetFolderId) {
        $stmtTarget = $pdo->prepare("SELECT name FROM folders WHERE id = :id");
        $stmtTarget->execute(['id' => $targetFolderId]);
        $targetData = $stmtTarget->fetch();
        if ($targetData) $targetName = $targetData['name'];
    }

    // Update the folder's parent location
    $stmt = $pdo->prepare("UPDATE folders SET parent_id = :parent_id WHERE id = :id");
    $stmt->execute([
        'parent_id' => $targetFolderId,
        'id' => $folderId
    ]);

    // Log Activity
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'moved_folder', :item_name, :target_name)");
    $stmtLog->execute([
        'user_id' => $_SESSION['user_id'],
        'item_name' => $itemName,
        'target_name' => $targetName
    ]);

    echo json_encode(['success' => true, 'message' => 'Folder moved successfully.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error while moving folder.']);
}
?>
