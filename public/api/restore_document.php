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
$docId = $data['id'] ?? null;
$targetFolderId = isset($data['target_folder_id']) && $data['target_folder_id'] !== 'null' && $data['target_folder_id'] !== null ? (int)$data['target_folder_id'] : null;

if (!$docId) {
    echo json_encode(['success' => false, 'message' => 'Document ID required.']);
    exit;
}

try {
    // If a specific target folder is provided, move the document there.
    // Otherwise just restore it to its original location (where it is right now).
    if (isset($data['target_folder_id'])) {
        $stmt = $pdo->prepare("UPDATE documents SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL, folder_id = :folder_id WHERE id = :id");
        $stmt->execute(['id' => $docId, 'folder_id' => $targetFolderId]);
    } else {
        $stmt = $pdo->prepare("UPDATE documents SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE id = :id");
        $stmt->execute(['id' => $docId]);
    }

    // Log Activity
    $stmtDoc = $pdo->prepare("SELECT title, folder_id FROM documents WHERE id = :id");
    $stmtDoc->execute(['id' => $docId]);
    $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
    $docName = $doc ? $doc['title'] : 'Unknown Document';
    
    $finalFolderId = isset($data['target_folder_id']) ? $targetFolderId : ($doc ? $doc['folder_id'] : null);
    $targetName = 'Home';
    if ($finalFolderId) {
        $stmtFolder = $pdo->prepare("SELECT name FROM folders WHERE id = :id");
        $stmtFolder->execute(['id' => $finalFolderId]);
        $folder = $stmtFolder->fetch(PDO::FETCH_ASSOC);
        if ($folder) $targetName = $folder['name'];
    }

    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Restored Document', :item_name, :target_name)");
    $stmtLog->execute([
        'user_id' => $_SESSION['user_id'],
        'item_name' => $docName,
        'target_name' => $targetName
    ]);

    echo json_encode(['success' => true, 'message' => 'Document restored.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error during restore.']);
}
?>
