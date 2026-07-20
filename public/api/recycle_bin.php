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

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 500;
$offset = ($page - 1) * $limit;

try {
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    $role = $stmtRole->fetchColumn();

    $folderCondition = "f.is_deleted = TRUE";
    $docCondition = "d.is_deleted = TRUE";
    
    $params = ['limit' => $limit, 'offset' => $offset];
    if ($role !== 'admin') {
        $folderCondition .= " AND f.deleted_by = :user_id";
        $docCondition .= " AND d.deleted_by = :user_id";
        $params['user_id'] = $_SESSION['user_id'];
    }

    $sortCol = isset($_GET['sort_col']) ? $_GET['sort_col'] : 'deleted_at';
    $sortDir = isset($_GET['sort_dir']) && strtolower($_GET['sort_dir']) === 'asc' ? 'ASC' : 'DESC';

    // Whitelist allowed sort columns to prevent SQL injection
    $allowedSortCols = ['name', 'type', 'deleted_by_name', 'deleted_at', 'original_location'];
    if (!in_array($sortCol, $allowedSortCols)) {
        $sortCol = 'deleted_at';
    }

    $sql = "
        WITH combined AS (
            SELECT f.id, f.name, f.deleted_at, u.username as deleted_by_name, p.name as original_location, 'folder' as type, NULL as filename, f.parent_id as parent_folder_id
            FROM folders f
            LEFT JOIN users u ON f.deleted_by = u.id
            LEFT JOIN folders p ON f.parent_id = p.id
            WHERE $folderCondition
              AND NOT EXISTS (
                  SELECT 1 FROM folders pf WHERE pf.id = f.parent_id AND pf.is_deleted = TRUE
              )

            UNION ALL

            SELECT d.id, d.title as name, d.deleted_at, u.username as deleted_by_name, p.name as original_location, 'document' as type, d.filename, d.folder_id as parent_folder_id
            FROM documents d
            LEFT JOIN users u ON d.deleted_by = u.id
            LEFT JOIN folders p ON d.folder_id = p.id
            WHERE $docCondition
              AND NOT EXISTS (
                  SELECT 1 FROM folders pf WHERE pf.id = d.folder_id AND pf.is_deleted = TRUE
              )
        ),
        counted AS (
            SELECT count(*) OVER() as total_count, combined.*
            FROM combined
        )
        SELECT * FROM counted
        ORDER BY $sortCol $sortDir
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items = [];
    $totalCount = 0;
    
    foreach ($results as $row) {
        $totalCount = $row['total_count'];
        unset($row['total_count']);
        $items[] = $row;
    }
    
    $totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

    echo json_encode([
        'success' => true, 
        'items' => $items,
        'total_pages' => $totalPages,
        'current_page' => $page
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
