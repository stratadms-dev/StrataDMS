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
$docId = $data['document_id'] ?? null;
$targetFolderId = isset($data['target_folder_id']) ? $data['target_folder_id'] : null;

if (!$docId) {
    echo json_encode(['success' => false, 'message' => 'Document ID required.']);
    exit;
}

try {
    // Fetch document name and checkout status
    $stmtDoc = $pdo->prepare("SELECT title, checked_out_by, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id");
    $stmtDoc->execute(['id' => $docId]);
    $doc = $stmtDoc->fetch();
    $itemName = $doc ? $doc['title'] : 'Unknown Document';
    
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    if ($doc && $doc['checked_out_by'] && $doc['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Cannot move: Document is checked out by ' . $doc['checked_out_by_name']]);
        exit;
    }

    // Fetch target folder name
    $targetName = 'Home';
    if ($targetFolderId) {
        $stmtFolder = $pdo->prepare("SELECT name FROM folders WHERE id = :id");
        $stmtFolder->execute(['id' => $targetFolderId]);
        $folder = $stmtFolder->fetch();
        if ($folder) $targetName = $folder['name'];
    }

    // Update the document's folder location
    $stmt = $pdo->prepare("UPDATE documents SET folder_id = :folder_id, updated_at = NOW(), updated_by = :user_id WHERE id = :id");
    $stmt->execute([
        'folder_id' => $targetFolderId,
        'user_id' => $_SESSION['user_id'],
        'id' => $docId
    ]);

    // Log Activity
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'moved_document', :item_name, :target_name)");
    $stmtLog->execute([
        'user_id' => $_SESSION['user_id'],
        'item_name' => $itemName,
        'target_name' => $targetName
    ]);

    echo json_encode(['success' => true, 'message' => 'Document moved successfully.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error while moving document.']);
}
?>
