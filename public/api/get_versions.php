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

$document_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT dv.version_number, dv.action_type, dv.created_at, u.username
        FROM document_versions dv
        LEFT JOIN users u ON dv.created_by = u.id
        WHERE dv.document_id = :doc_id
        ORDER BY dv.version_number DESC
    ");
    $stmt->execute(['doc_id' => $document_id]);
    $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'versions' => $versions
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
