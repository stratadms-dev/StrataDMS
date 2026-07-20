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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = isset($data['name']) ? trim($data['name']) : '';
    $parentId = isset($data['parent_id']) && $data['parent_id'] !== 'null' && $data['parent_id'] !== null ? (int)$data['parent_id'] : 0;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Folder name is required']);
        exit;
    }

    if (!has_permission($pdo, $_SESSION['user_id'], $parentId, 'right_add')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_add']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO folders (name, parent_id, created_by) VALUES (:name, :parent_id, :created_by) RETURNING id");
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parentId,
            'created_by' => $_SESSION['user_id']
        ]);
        $newFolderId = $stmt->fetchColumn();

        // Automatically inherit permissions from parent if scope cascades
        if ($parentId !== null) {
            $stmtInherit = $pdo->prepare("
                INSERT INTO folder_permissions (
                    user_id, folder_id, scope, right_view, right_add, right_modify, right_delete, 
                    right_see_through_redactions, right_manage_security
                )
                SELECT 
                    user_id, :new_id, scope, right_view, right_add, right_modify, right_delete, 
                    right_see_through_redactions, right_manage_security
                FROM folder_permissions
                WHERE folder_id = :parent_id AND scope IN ('this_folder_subfolders_documents', 'this_folder_subfolders')
            ");
            $stmtInherit->execute([
                'new_id' => $newFolderId,
                'parent_id' => $parentId
            ]);

            $stmtInheritGroup = $pdo->prepare("
                INSERT INTO folder_group_permissions (
                    group_id, folder_id, scope, right_view, right_add, right_modify, right_delete, 
                    right_see_through_redactions, right_manage_security
                )
                SELECT 
                    group_id, :new_id, scope, right_view, right_add, right_modify, right_delete, 
                    right_see_through_redactions, right_manage_security
                FROM folder_group_permissions
                WHERE folder_id = :parent_id AND scope IN ('this_folder_subfolders_documents', 'this_folder_subfolders')
            ");
            $stmtInheritGroup->execute([
                'new_id' => $newFolderId,
                'parent_id' => $parentId
            ]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create folder']);
    }
    exit;
}

$parentId = isset($_GET['parent_id']) && $_GET['parent_id'] !== 'null' ? (int)$_GET['parent_id'] : 0;

try {
    $breadcrumb = [];

    // If we are deep inside a folder, fetch its details for the breadcrumb trail
    if ($parentId !== 0) {
        $stmtFolder = $pdo->prepare("
            WITH RECURSIVE folder_path AS (
                SELECT id, name, parent_id, 1 as depth
                FROM folders
                WHERE id = :id
                
                UNION ALL
                
                SELECT f.id, f.name, f.parent_id, fp.depth + 1
                FROM folders f
                JOIN folder_path fp ON f.id = fp.parent_id
            )
            SELECT id, name
            FROM folder_path
            ORDER BY depth DESC
        ");
        $stmtFolder->execute(['id' => $parentId]);
        $breadcrumb = $stmtFolder->fetchAll(PDO::FETCH_ASSOC);
    }

    $userId = $_SESSION['user_id'];
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$userId]);
    $role = $stmtRole->fetchColumn();

    $folderPermFilter = "";
    $docPermFilter = "";
    if ($role !== 'admin') {
        $folderPermFilter = " AND id IN (SELECT folder_id FROM vw_effective_permissions WHERE user_id = :user_id AND right_view = TRUE) ";
        $docPermFilter = " AND folder_id IN (SELECT folder_id FROM vw_effective_permissions WHERE user_id = :user_id AND right_view_documents = TRUE) ";
    }

    $highlightDocId = isset($_GET['highlight_doc_id']) ? (int)$_GET['highlight_doc_id'] : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 500;
    
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';
    $deletedFilter = $showAll ? "" : " AND coalesce(is_deleted, false) = false";

    if ($highlightDocId !== null) {
        $sqlHighlight = "
            WITH combined AS (
                SELECT id, name as title, name, created_at, 'folder' as item_type, NULL::int as file_size, NULL as filename, NULL as thumbnail_filename
                FROM folders 
                WHERE id != 0 AND " . ($parentId === 0 ? "(parent_id = 0 OR parent_id IS NULL)" : "parent_id = :parent_id") . $deletedFilter . $folderPermFilter . "
                
                UNION ALL
                
                SELECT id, title, NULL as name, created_at, 'document' as item_type, file_size, filename, thumbnail_filename
                FROM documents 
                WHERE " . ($parentId === 0 ? "(folder_id = 0 OR folder_id IS NULL)" : "folder_id = :parent_id") . $deletedFilter . $docPermFilter . "
            ),
            ordered AS (
                SELECT id, item_type, ROW_NUMBER() OVER(ORDER BY item_type DESC, title ASC) as rn
                FROM combined
            )
            SELECT rn FROM ordered WHERE id = :target_id AND item_type = 'document'
        ";
        $stmtH = $pdo->prepare($sqlHighlight);
        $paramsH = [];
        if ($role !== 'admin') $paramsH['user_id'] = $userId;
        if ($parentId !== 0) $paramsH['parent_id'] = $parentId;
        $paramsH['target_id'] = $highlightDocId;
        $stmtH->execute($paramsH);
        $rnResult = $stmtH->fetchColumn();
        if ($rnResult) {
            $page = ceil($rnResult / $limit);
        }
    }

    $offset = ($page - 1) * $limit;
    $deletedFilter = $showAll ? "" : " AND coalesce(is_deleted, false) = false";

    $sql = "
        WITH combined AS (
            SELECT f.id, f.name as title, f.name, f.created_at, 'folder' as item_type, NULL::int as file_size, NULL as filename, NULL as thumbnail_filename, NULL::int as checked_out_by, NULL as checked_out_by_name,
                   ua.username as added_by_name, uu.username as updated_by_name, f.updated_at
            FROM folders f
            LEFT JOIN users ua ON f.created_by = ua.id
            LEFT JOIN users uu ON f.updated_by = uu.id
            WHERE f.id != 0 AND " . ($parentId === 0 ? "(f.parent_id = 0 OR f.parent_id IS NULL)" : "f.parent_id = :parent_id") . str_replace('is_deleted', 'f.is_deleted', $deletedFilter) . str_replace('id IN', 'f.id IN', $folderPermFilter) . "
            
            UNION ALL
            
            SELECT d.id, d.title, NULL as name, d.created_at, 'document' as item_type, d.file_size, d.filename, d.thumbnail_filename, d.checked_out_by, u.username as checked_out_by_name,
                   ua.username as added_by_name, uu.username as updated_by_name, d.updated_at
            FROM documents d
            LEFT JOIN users u ON d.checked_out_by = u.id
            LEFT JOIN users ua ON d.uploaded_by = ua.id
            LEFT JOIN users uu ON d.updated_by = uu.id
            WHERE " . ($parentId === 0 ? "(d.folder_id = 0 OR d.folder_id IS NULL)" : "d.folder_id = :parent_id") . str_replace('is_deleted', 'd.is_deleted', $deletedFilter) . str_replace('folder_id IN', 'd.folder_id IN', $docPermFilter) . "
        ),
        counted AS (
            SELECT count(*) OVER() as total_count, combined.*
            FROM combined
        )
        SELECT * FROM counted
        ORDER BY item_type DESC, title ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    $params = ['limit' => $limit, 'offset' => $offset];
    if ($role !== 'admin') $params['user_id'] = $userId;
    if ($parentId !== 0) {
        $params['parent_id'] = $parentId;
    }
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $folders = [];
    $documents = [];
    $totalCount = 0;

    foreach ($results as $row) {
        $totalCount = $row['total_count'];
        if ($row['item_type'] === 'folder') {
            $folders[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'added_by_name' => $row['added_by_name'],
                'updated_by_name' => $row['updated_by_name'],
                'updated_at' => $row['updated_at']
            ];
        } else {
            $documents[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'filename' => $row['filename'],
                'file_size' => $row['file_size'],
                'created_at' => $row['created_at'],
                'thumbnail_filename' => $row['thumbnail_filename'],
                'added_by_name' => $row['added_by_name'],
                'updated_by_name' => $row['updated_by_name'],
                'updated_at' => $row['updated_at']
            ];
        }
    }
    
    $totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

    if (count($documents) > 0) {
        $docIds = array_column($documents, 'id');
        $placeholders = implode(',', array_fill(0, count($docIds), '?'));
        
        $stmtMeta = $pdo->prepare("
            SELECT dm.document_id, tf.template_id, tf.id as field_id, dm.field_value 
            FROM document_metadata dm 
            JOIN template_fields tf ON dm.field_id = tf.id 
            WHERE dm.document_id IN ($placeholders)
        ");
        $stmtMeta->execute($docIds);
        $metadataRows = $stmtMeta->fetchAll(PDO::FETCH_ASSOC);
        
        $metaByDoc = [];
        foreach ($metadataRows as $row) {
            $key = "tpl_" . $row['template_id'] . "_" . $row['field_id'];
            $metaByDoc[$row['document_id']][$key] = $row['field_value'];
        }
        
        foreach ($documents as &$doc) {
            $doc['metadata'] = $metaByDoc[$doc['id']] ?? [];
        }
    }

    $permissions = [
        'right_add' => has_permission($pdo, $userId, $parentId, 'right_add'),
        'right_modify' => has_permission($pdo, $userId, $parentId, 'right_modify'),
        'right_delete' => has_permission($pdo, $userId, $parentId, 'right_delete'),
        'right_see_through_redactions' => has_permission($pdo, $userId, $parentId, 'right_see_through_redactions'),
        'right_manage_security' => has_permission($pdo, $userId, $parentId, 'right_manage_security')
    ];

    echo json_encode([
        'success' => true,
        'breadcrumb' => $breadcrumb,
        'folders' => $folders,
        'documents' => $documents,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'permissions' => $permissions
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>