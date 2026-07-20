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
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../check_permission.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$document_id = $data['document_id'] ?? 0;
$type = $data['type'] ?? 'redact';
$color = $data['color'] ?? '#000000';
$page_nums = $data['page_nums'] ?? [];
$pos_x = $data['pos_x'] ?? 50;
$pos_y = $data['pos_y'] ?? 50;
$width = $data['width'] ?? 20;
$height = $data['height'] ?? 5;
$placement_id = $data['placement_id'] ?? null;
$page_num = $data['page_num'] ?? null;

if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Missing document_id']);
    exit;
}

$stmtFolder = $pdo->prepare("SELECT folder_id FROM documents WHERE id = :id");
$stmtFolder->execute(['id' => $document_id]);
$docFolder = $stmtFolder->fetchColumn();

if (!has_permission($pdo, $_SESSION['user_id'], $docFolder, 'right_modify')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
    exit;
}

try {
    if ($placement_id) {
        $stmt = $pdo->prepare("UPDATE document_annotations SET pos_x = :x, pos_y = :y, width = :w, height = :h WHERE id = :id");
        $stmt->execute(['x' => $pos_x, 'y' => $pos_y, 'w' => $width, 'h' => $height, 'id' => $placement_id]);
        echo json_encode(['success' => true]);
    } else {
        if (empty($page_nums) && $page_num) {
            $page_nums = [$page_num];
        }
        $newIds = [];
        foreach ($page_nums as $p) {
            $stmt = $pdo->prepare("INSERT INTO document_annotations (document_id, type, page_num, pos_x, pos_y, width, height, color) VALUES (:did, :t, :p, :x, :y, :w, :h, :c) RETURNING id");
            $stmt->execute(['did' => $document_id, 't' => $type, 'p' => $p, 'x' => $pos_x, 'y' => $pos_y, 'w' => $width, 'h' => $height, 'c' => $color]);
            $newIds[] = $stmt->fetchColumn();
        }
        echo json_encode(['success' => true, 'placement_ids' => $newIds]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
