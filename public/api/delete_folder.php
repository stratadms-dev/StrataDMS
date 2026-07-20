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

if (!$folderId) {
    echo json_encode(['success' => false, 'message' => 'Folder ID required.']);
    exit;
}

if (!has_permission($pdo, $_SESSION['user_id'], $folderId, 'right_delete')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_delete']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Recursively collect all folder IDs in the subtree (including the root)
    $stmtTree = $pdo->prepare("
        WITH RECURSIVE subtree AS (
            SELECT id FROM folders WHERE id = :id
            UNION ALL
            SELECT f.id FROM folders f JOIN subtree s ON f.parent_id = s.id
        )
        SELECT id FROM subtree
    ");
    $stmtTree->execute(['id' => $folderId]);
    $folderIds = $stmtTree->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($folderIds)) {
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $userId = $_SESSION['user_id'];

        // Soft-delete all folders in the subtree
        $params = array_merge([$userId], $folderIds);
        $stmtFolders = $pdo->prepare(
            "UPDATE folders SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = ? WHERE id IN ($placeholders)"
        );
        $stmtFolders->execute($params);

        // Soft-delete all documents inside any of those folders
        $stmtDocs = $pdo->prepare(
            "UPDATE documents SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = ? WHERE folder_id IN ($placeholders) AND coalesce(is_deleted, false) = false"
        );
        $stmtDocs->execute($params);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Folder moved to Recycle Bin.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Server error during folder deletion.']);
}
?>
