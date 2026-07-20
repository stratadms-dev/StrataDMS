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

if (!$data || !isset($data['type']) || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$type = $data['type']; // 'document' or 'folder'
$id = intval($data['id']);
$target_folder_id = isset($data['target_folder_id']) ? $data['target_folder_id'] : null;

try {
    if ($type === 'document') {
        $stmt = $pdo->prepare("UPDATE documents SET folder_id = :target, updated_at = NOW(), updated_by = :user_id WHERE id = :id");
        $stmt->execute(['target' => $target_folder_id, 'user_id' => $_SESSION['user_id'], 'id' => $id]);
    } else if ($type === 'folder') {
        // Basic circular reference prevention: don't move into itself
        if ($target_folder_id == $id) {
            echo json_encode(['success' => false, 'message' => 'Cannot move folder into itself']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE folders SET parent_id = :target, updated_at = NOW(), updated_by = :user_id WHERE id = :id");
        $stmt->execute(['target' => $target_folder_id, 'user_id' => $_SESSION['user_id'], 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
