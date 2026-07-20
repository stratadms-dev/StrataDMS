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

$folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
if (!$folderId) {
    echo json_encode(['success' => false, 'message' => 'folder_id is required']);
    exit;
}

try {
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    $role = $stmtRole->fetchColumn();

    // Verify the parent folder itself is deleted (so we're browsing inside a bin item)
    $stmtCheck = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND is_deleted = TRUE");
    $stmtCheck->execute([$folderId]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Folder not found in Recycle Bin']);
        exit;
    }

    $extraCond = ($role !== 'admin') ? " AND deleted_by = " . (int)$_SESSION['user_id'] : "";

    // Direct child sub-folders
    $stmtFolders = $pdo->prepare("
        SELECT f.id, f.name, f.deleted_at, u.username as deleted_by_name, 'folder' as type, NULL as filename
        FROM folders f
        LEFT JOIN users u ON f.deleted_by = u.id
        WHERE f.parent_id = ? AND f.is_deleted = TRUE $extraCond
        ORDER BY f.name ASC
    ");
    $stmtFolders->execute([$folderId]);
    $subfolders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);

    // Direct child documents
    $stmtDocs = $pdo->prepare("
        SELECT d.id, d.title as name, d.deleted_at, u.username as deleted_by_name, 'document' as type, d.filename
        FROM documents d
        LEFT JOIN users u ON d.deleted_by = u.id
        WHERE d.folder_id = ? AND d.is_deleted = TRUE $extraCond
        ORDER BY d.title ASC
    ");
    $stmtDocs->execute([$folderId]);
    $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'items' => array_merge($subfolders, $docs)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
