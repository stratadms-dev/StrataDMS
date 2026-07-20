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
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$document_id = $_GET['document_id'] ?? 0;
if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Missing document_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, type, page_num, pos_x, pos_y, width, height, color FROM document_annotations WHERE document_id = :did");
    $stmt->execute(['did' => $document_id]);
    $placements = $stmt->fetchAll();
    echo json_encode(['success' => true, 'placements' => $placements]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
