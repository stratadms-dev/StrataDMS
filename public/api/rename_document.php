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
$docId = $data['id'] ?? null;
$newTitle = $data['title'] ?? null;

if (!$docId || !$newTitle || trim($newTitle) === '') {
    echo json_encode(['success' => false, 'message' => 'Document ID and new title are required.']);
    exit;
}

try {
    // Fetch old title and folder and checkout status
    $stmtOld = $pdo->prepare("SELECT title, folder_id, checked_out_by, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id");
    $stmtOld->execute(['id' => $docId]);
    $oldDoc = $stmtOld->fetch();
    $oldTitle = $oldDoc ? $oldDoc['title'] : 'Unknown Document';
    
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    if ($oldDoc && $oldDoc['checked_out_by'] && $oldDoc['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Cannot rename: Document is checked out by ' . $oldDoc['checked_out_by_name']]);
        exit;
    }
    
    if (!has_permission($pdo, $_SESSION['user_id'], $oldDoc ? $oldDoc['folder_id'] : null, 'right_modify')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE documents SET title = :title, updated_at = NOW(), updated_by = :user_id WHERE id = :id");
    $stmt->execute(['title' => trim($newTitle), 'user_id' => $_SESSION['user_id'], 'id' => $docId]);

    // Log Activity
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'rename_document', :item_name, :target_name)");
    $stmtLog->execute([
        'user_id' => $_SESSION['user_id'],
        'item_name' => $oldTitle,
        'target_name' => trim($newTitle)
    ]);

    echo json_encode(['success' => true, 'message' => 'Document renamed.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error during rename.']);
}
?>
