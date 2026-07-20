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
$documentId = $data['document_id'] ?? null;

if (!$documentId) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

try {
    // Upsert the recent view record
    // PostgreSQL uses ON CONFLICT DO UPDATE
    $stmt = $pdo->prepare("
        INSERT INTO user_recent_documents (user_id, document_id) 
        VALUES (:user_id, :doc_id)
        ON CONFLICT (user_id, document_id) 
        DO UPDATE SET viewed_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'doc_id' => $documentId
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Even if tracking fails, we don't want to break the UI, so we just log or return soft success
    echo json_encode(['success' => false, 'message' => 'Failed to track view']);
}
