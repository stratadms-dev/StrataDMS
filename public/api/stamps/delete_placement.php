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
$id = $data['placement_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing placement_id']);
    exit;
}

$stmtFolder = $pdo->prepare("SELECT d.folder_id FROM documents d JOIN document_stamps s ON s.document_id = d.id WHERE s.id = :id");
$stmtFolder->execute(['id' => $id]);
$docFolder = $stmtFolder->fetchColumn();

if (!has_permission($pdo, $_SESSION['user_id'], $docFolder, 'right_modify')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM document_stamps WHERE id = :id");
    $stmt->execute(['id' => $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
