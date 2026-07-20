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

$userId = $_SESSION['user_id'];
$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtRole->execute([$userId]);
$role = $stmtRole->fetchColumn();

try {
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM folders WHERE id != 0 AND coalesce(is_deleted, false) = false ORDER BY name ASC");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT f.id, f.name, f.parent_id 
            FROM folders f
            JOIN vw_effective_permissions p ON f.id = p.folder_id
            WHERE f.id != 0
              AND coalesce(f.is_deleted, false) = false 
              AND p.user_id = ? 
              AND p.right_view = TRUE
            ORDER BY f.name ASC
        ");
        $stmt->execute([$userId]);
    }
    $allFolders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function buildTree(array $elements, $parentId = 0) {
        $branch = array();

        foreach ($elements as $element) {
            // Treat both NULL and 0 parent_id as root level
            $elementParent = ($element['parent_id'] === null || $element['parent_id'] == 0) ? 0 : (int)$element['parent_id'];
            if ($elementParent == $parentId) {
                $children = buildTree($elements, $element['id']);
                $element['children'] = $children;
                $branch[] = $element;
            }
        }

        return $branch;
    }

    $tree = buildTree($allFolders);

    echo json_encode([
        'success' => true,
        'tree' => $tree
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
