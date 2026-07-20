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

if (!isset($data['action']) || !isset($data['items']) || !is_array($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$action = $data['action'];
$items = $data['items'];
$targetFolderId = isset($data['target_folder_id']) && $data['target_folder_id'] !== 'null' && $data['target_folder_id'] !== null ? (int)$data['target_folder_id'] : null;
$userId = $_SESSION['user_id'];

if (empty($items)) {
    echo json_encode(['success' => true, 'message' => 'No items provided.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $docIds = [];
    $folderIds = [];
    foreach ($items as $item) {
        if (isset($item['type']) && isset($item['id'])) {
            if ($item['type'] === 'document') {
                $docIds[] = (int)$item['id'];
            } elseif ($item['type'] === 'folder') {
                $folderIds[] = (int)$item['id'];
            }
        }
    }

    if ($action === 'delete') {
        if (!empty($docIds)) {
            $inDocs = implode(',', array_fill(0, count($docIds), '?'));
            $stmt = $pdo->prepare("UPDATE documents SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = ? WHERE id IN ($inDocs)");
            $stmt->execute(array_merge([$userId], $docIds));
        }
        if (!empty($folderIds)) {
            $inFolders = implode(',', array_fill(0, count($folderIds), '?'));
            $stmt = $pdo->prepare("UPDATE folders SET is_deleted = TRUE, deleted_at = NOW(), deleted_by = ? WHERE id IN ($inFolders)");
            $stmt->execute(array_merge([$userId], $folderIds));
        }
    } elseif ($action === 'restore') {
        if (!empty($docIds)) {
            $inDocs = implode(',', array_fill(0, count($docIds), '?'));
            $stmt = $pdo->prepare("UPDATE documents SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE id IN ($inDocs)");
            $stmt->execute($docIds);
            
            $stmtLog = $pdo->prepare("
                INSERT INTO activity_log (user_id, action_type, item_name, target_name)
                SELECT ?, 'Restored Document', d.title, COALESCE(f.name, 'Home')
                FROM documents d
                LEFT JOIN folders f ON d.folder_id = f.id
                WHERE d.id IN ($inDocs)
            ");
            $stmtLog->execute(array_merge([$userId], $docIds));
        }
        if (!empty($folderIds)) {
            $inFolders = implode(',', array_fill(0, count($folderIds), '?'));
            $stmt = $pdo->prepare("UPDATE folders SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE id IN ($inFolders)");
            $stmt->execute($folderIds);
            
            $stmtLog = $pdo->prepare("
                INSERT INTO activity_log (user_id, action_type, item_name, target_name)
                SELECT ?, 'Restored Folder', fld.name, COALESCE(p.name, 'Home')
                FROM folders fld
                LEFT JOIN folders p ON fld.parent_id = p.id
                WHERE fld.id IN ($inFolders)
            ");
            $stmtLog->execute(array_merge([$userId], $folderIds));
        }
    } elseif ($action === 'move') {
        if (!empty($docIds)) {
            $inDocs = implode(',', array_fill(0, count($docIds), '?'));
            $stmt = $pdo->prepare("UPDATE documents SET folder_id = ? WHERE id IN ($inDocs)");
            $stmt->execute(array_merge([$targetFolderId], $docIds));
        }
        if (!empty($folderIds)) {
            if (in_array($targetFolderId, $folderIds)) {
                throw new Exception("Cannot move a folder into itself.");
            }
            $inFolders = implode(',', array_fill(0, count($folderIds), '?'));
            $stmt = $pdo->prepare("UPDATE folders SET parent_id = ? WHERE id IN ($inFolders)");
            $stmt->execute(array_merge([$targetFolderId], $folderIds));
        }
    } else {
        throw new Exception("Unknown action.");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Bulk $action successful."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
