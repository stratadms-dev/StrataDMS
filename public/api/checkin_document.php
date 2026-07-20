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
    $stmt = $pdo->prepare("SELECT checked_out_by FROM documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    if ($doc['checked_out_by'] && $doc['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'You cannot check in a document locked by someone else unless you are an admin.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE documents SET checked_out_by = NULL, checked_out_at = NULL WHERE id = ?");
    $stmt->execute([$documentId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
