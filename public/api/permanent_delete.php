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

// Only admins can permanently delete
$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtRole->execute([$_SESSION['user_id']]);
$role = $stmtRole->fetchColumn();

if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Admin only']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id'])   ? (int)$data['id']   : null;
$type = isset($data['type']) ? $data['type']       : null;

if (!$id || !in_array($type, ['document', 'folder'])) {
    echo json_encode(['success' => false, 'message' => 'id and type (document|folder) are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($type === 'document') {
        // Fetch the filename so we can delete the physical file
        $stmtDoc = $pdo->prepare("SELECT filename FROM documents WHERE id = ? AND is_deleted = TRUE");
        $stmtDoc->execute([$id]);
        $doc = $stmtDoc->fetch();

        if (!$doc) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Document not found in Recycle Bin']);
            exit;
        }

        // Hard-delete the DB row (cascades to annotations, metadata, stamps, versions, watermarks)
        $stmtDel = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmtDel->execute([$id]);

        // Remove the physical file
        $filePath = __DIR__ . '/../../storage/' . $doc['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

    } else {
        // Folder — verify it is soft-deleted
        $stmtFolder = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND is_deleted = TRUE");
        $stmtFolder->execute([$id]);
        if (!$stmtFolder->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Folder not found in Recycle Bin']);
            exit;
        }

        // Collect all document filenames inside this folder tree before deleting
        $stmtFiles = $pdo->prepare("
            WITH RECURSIVE sub AS (
                SELECT id FROM folders WHERE id = ?
                UNION ALL
                SELECT f.id FROM folders f JOIN sub s ON f.parent_id = s.id
            )
            SELECT filename FROM documents WHERE folder_id IN (SELECT id FROM sub)
        ");
        $stmtFiles->execute([$id]);
        $filenames = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);

        // Hard-delete the folder (cascades to sub-folders and documents via ON DELETE CASCADE)
        $stmtDel = $pdo->prepare("DELETE FROM folders WHERE id = ?");
        $stmtDel->execute([$id]);

        // Remove physical files
        foreach ($filenames as $filename) {
            $filePath = __DIR__ . '/../../storage/' . $filename;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
