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

if (!$docId) {
    echo json_encode(['success' => false, 'message' => 'Document ID required.']);
    exit;
}

$stmtFolder = $pdo->prepare("SELECT folder_id, checked_out_by, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id");
$stmtFolder->execute(['id' => $docId]);
$docInfo = $stmtFolder->fetch();
$docFolder = $docInfo ? $docInfo['folder_id'] : null;

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($docInfo && $docInfo['checked_out_by'] && $docInfo['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete: Document is checked out by ' . $docInfo['checked_out_by_name']]);
    exit;
}

if (!has_permission($pdo, $_SESSION['user_id'], $docFolder, 'right_delete')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_delete']);
    exit;
}

try {
    $deleteStmt = $pdo->prepare("UPDATE documents SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = :user_id WHERE id = :id");
    $deleteStmt->execute([
        'id' => $docId,
        'user_id' => $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true, 'message' => 'Document moved to Recycle Bin.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error during deletion.']);
}
?>
