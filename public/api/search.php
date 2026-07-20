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

if (!$data || !isset($data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$type = $data['type'];
$page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
$limit = 500;
$offset = ($page - 1) * $limit;
$is_deleted = isset($data['is_deleted']) && $data['is_deleted'];
$deleted_by = isset($data['deleted_by']) ? trim($data['deleted_by']) : '';
$deleted_at = isset($data['deleted_at']) ? trim($data['deleted_at']) : '';
$userId = $_SESSION['user_id'];
$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtRole->execute([$userId]);
$role = $stmtRole->fetchColumn();

if ($is_deleted && $role !== 'admin') {
    $deletedCondition = "d.is_deleted = true AND d.deleted_by = :user_id";
    $folderDeletedCondition = "f.is_deleted = true AND f.deleted_by = :user_id";
} else if ($is_deleted) {
    $deletedCondition = "d.is_deleted = true";
    $folderDeletedCondition = "f.is_deleted = true";
} else {
    $deletedCondition = "coalesce(d.is_deleted, false) = false";
    $folderDeletedCondition = "coalesce(f.is_deleted, false) = false";
}

if ($is_deleted) {
    if (!empty($deleted_by)) {
        $deletedCondition .= " AND u.username ILIKE :search_deleted_by";
        $folderDeletedCondition .= " AND u.username ILIKE :search_deleted_by";
    }
    if (!empty($deleted_at)) {
        $deletedCondition .= " AND d.deleted_at::date = :search_deleted_at";
        $folderDeletedCondition .= " AND f.deleted_at::date = :search_deleted_at";
    }
}

$docPermFilter = "";
$folderPermFilter = "";
if ($role !== 'admin') {
    $docPermFilter = " AND d.folder_id IN (SELECT folder_id FROM vw_effective_permissions WHERE user_id = :user_id AND right_view_documents = TRUE) ";
    $folderPermFilter = " AND f.id IN (SELECT folder_id FROM vw_effective_permissions WHERE user_id = :user_id AND right_view = TRUE) ";
}

$folders = [];
$documents = [];
$totalCount = 0;

try {
    if (in_array($type, ['name', 'added_by', 'modified_by', 'deleted_by', 'deleted_at'])) {
        $query = isset($data['query']) ? trim($data['query']) : '';
        if (empty($query)) {
            echo json_encode(['success' => true, 'folders' => [], 'documents' => []]);
            exit;
        }

        $docField = "d.title";
        $folderField = "f.name";
        $operator = "ILIKE";
        $paramValue = '%' . $query . '%';

        if ($type === 'added_by') {
            $docField = "ua.username";
            $folderField = "ua.username";
        } else if ($type === 'modified_by') {
            $docField = "uu.username";
            $folderField = "uu.username";
        } else if ($type === 'deleted_by') {
            $docField = "u.username";
            $folderField = "u.username";
        } else if ($type === 'deleted_at') {
            $docField = "d.deleted_at::date";
            $folderField = "f.deleted_at::date";
            $operator = "=";
            $paramValue = $query; // yyyy-mm-dd
        }

        $sql = "
            WITH RECURSIVE folder_path AS (
                SELECT id, name, parent_id, name::text AS full_path
                FROM folders
                WHERE parent_id IS NULL
                
                UNION ALL
                
                SELECT f.id, f.name, f.parent_id, fp.full_path || ' / ' || f.name
                FROM folders f
                JOIN folder_path fp ON f.parent_id = fp.id
            ),
            counted AS (
                SELECT count(*) OVER() as total_count, d.id, d.folder_id, d.title, d.filename, d.file_size, d.created_at, d.deleted_at, u.username as deleted_by_name, d.thumbnail_filename, COALESCE(fp.full_path, 'Root') as folder_name, COALESCE(fp.full_path, 'Root') as full_path, d.checked_out_by, u2.username as checked_out_by_name,
                       ua.username as added_by_name, uu.username as updated_by_name, d.updated_at
                FROM documents d
                LEFT JOIN folder_path fp ON d.folder_id = fp.id
                LEFT JOIN users u ON d.deleted_by = u.id
                LEFT JOIN users u2 ON d.checked_out_by = u2.id
                LEFT JOIN users ua ON d.uploaded_by = ua.id
                LEFT JOIN users uu ON d.updated_by = uu.id
                WHERE $docField $operator :query AND $deletedCondition $docPermFilter
            )
            SELECT * FROM counted
            ORDER BY title ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $params = ['query' => $paramValue, 'limit' => $limit, 'offset' => $offset];
        if ($role !== 'admin') $params['user_id'] = $userId;
        if ($is_deleted && !empty($deleted_by)) $params['search_deleted_by'] = '%' . $deleted_by . '%';
        if ($is_deleted && !empty($deleted_at)) $params['search_deleted_at'] = $deleted_at;
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $totalCount = $row['total_count'];
            unset($row['total_count']);
            $documents[] = $row;
        }
        
        $sqlFolders = "
            WITH RECURSIVE folder_path AS (
                SELECT id, name, parent_id, name::text AS full_path
                FROM folders
                WHERE parent_id IS NULL
                
                UNION ALL
                
                SELECT f.id, f.name, f.parent_id, fp.full_path || ' / ' || f.name
                FROM folders f
                JOIN folder_path fp ON f.parent_id = fp.id
            )
            SELECT f.id, f.name, f.parent_id, f.created_at, f.deleted_at, u.username as deleted_by_name, COALESCE(fp.full_path, 'Root') as full_path, COALESCE(fp.full_path, 'Root') as original_location,
                   ua.username as added_by_name, uu.username as updated_by_name, f.updated_at
            FROM folders f
            LEFT JOIN folder_path fp ON f.parent_id = fp.id
            LEFT JOIN users u ON f.deleted_by = u.id
            LEFT JOIN users ua ON f.created_by = ua.id
            LEFT JOIN users uu ON f.updated_by = uu.id
            WHERE $folderField $operator :query AND " . $folderDeletedCondition . $folderPermFilter . "
            ORDER BY f.name ASC
            LIMIT :limit
        ";
        $stmtFolders = $pdo->prepare($sqlFolders);
        $paramsF = ['query' => $paramValue, 'limit' => $limit];
        if ($role !== 'admin') $paramsF['user_id'] = $userId;
        if ($is_deleted && !empty($deleted_by)) $paramsF['search_deleted_by'] = '%' . $deleted_by . '%';
        if ($is_deleted && !empty($deleted_at)) $paramsF['search_deleted_at'] = $deleted_at;
        $stmtFolders->execute($paramsF);
        $folders = $stmtFolders->fetchAll(PDO::FETCH_ASSOC);

    } else if ($type === 'template') {
        $template_id = isset($data['template_id']) ? intval($data['template_id']) : 0;
        $fields = isset($data['fields']) ? $data['fields'] : [];

        if (!$template_id || empty($fields)) {
            echo json_encode(['success' => true, 'folders' => [], 'documents' => []]);
            exit;
        }

        // We need to build an INTERSECT query for all filled fields.
        // E.g., for two fields:
        // SELECT document_id FROM document_metadata WHERE field_id = ? AND field_value ILIKE ?
        // INTERSECT
        // SELECT document_id FROM document_metadata WHERE field_id = ? AND field_value ILIKE ?

        $intersectParts = [];
        $params = [];
        
        foreach ($fields as $field_id => $value) {
            $value = trim($value);
            if ($value === '') continue; // Skip empty fields

            $intersectParts[] = "SELECT document_id FROM document_metadata WHERE field_id = ? AND field_value ILIKE ?";
            $params[] = $field_id;
            $params[] = '%' . $value . '%';
        }

        if (empty($intersectParts)) {
            echo json_encode(['success' => true, 'folders' => [], 'documents' => []]);
            exit;
        }

        $intersectQuery = implode(" INTERSECT ", $intersectParts);
        
        // Final query to get documents
        $sql = "
            WITH RECURSIVE folder_path AS (
                SELECT id, name, parent_id, name::text AS full_path
                FROM folders
                WHERE parent_id IS NULL
                
                UNION ALL
                
                SELECT f.id, f.name, f.parent_id, fp.full_path || ' / ' || f.name
                FROM folders f
                JOIN folder_path fp ON f.parent_id = fp.id
            ),
            counted AS (
                SELECT count(*) OVER() as total_count, d.id, d.folder_id, d.title, d.filename, d.file_size, d.created_at, d.deleted_at, u.username as deleted_by_name, d.thumbnail_filename, COALESCE(fp.full_path, 'Root') as folder_name, COALESCE(fp.full_path, 'Root') as full_path, d.checked_out_by, u2.username as checked_out_by_name,
                       ua.username as added_by_name, uu.username as updated_by_name, d.updated_at
                FROM documents d
                LEFT JOIN folder_path fp ON d.folder_id = fp.id
                LEFT JOIN users u ON d.deleted_by = u.id
                LEFT JOIN users u2 ON d.checked_out_by = u2.id
                LEFT JOIN users ua ON d.uploaded_by = ua.id
                LEFT JOIN users uu ON d.updated_by = uu.id
                JOIN ($intersectQuery) md ON d.id = md.document_id
                WHERE $deletedCondition $docPermFilter
            )
            SELECT * FROM counted
            ORDER BY title ASC
            LIMIT ? OFFSET ?
        ";

        if ($role !== 'admin') {
            $count = substr_count($sql, ':user_id');
            for ($i = 0; $i < $count; $i++) $params[] = $userId;
            $sql = str_replace(':user_id', '?', $sql);
        }
        if ($is_deleted && !empty($deleted_by)) {
            $count = substr_count($sql, ':search_deleted_by');
            for ($i = 0; $i < $count; $i++) $params[] = '%' . $deleted_by . '%';
            $sql = str_replace(':search_deleted_by', '?', $sql);
        }
        if ($is_deleted && !empty($deleted_at)) {
            $count = substr_count($sql, ':search_deleted_at');
            for ($i = 0; $i < $count; $i++) $params[] = $deleted_at;
            $sql = str_replace(':search_deleted_at', '?', $sql);
        }
        $stmt = $pdo->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $totalCount = $row['total_count'];
            unset($row['total_count']);
            $documents[] = $row;
        }
    }

    // Now fetch metadata for all matching documents
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

    $totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

    echo json_encode([
        'success' => true,
        'folders' => $folders,
        'documents' => $documents,
        'total_pages' => $totalPages,
        'current_page' => $page
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
