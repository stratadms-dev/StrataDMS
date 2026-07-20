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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$document_id = isset($_GET['document_id']) ? (int)$_GET['document_id'] : null;

// Try to get document-specific watermark first, then global fallback
$stmt = $pdo->prepare("SELECT * FROM watermarks WHERE document_id = :did OR document_id IS NULL ORDER BY document_id DESC NULLS LAST LIMIT 1");
$stmt->execute(['did' => $document_id]);
$watermark = $stmt->fetch(PDO::FETCH_ASSOC);

if ($watermark) {
    echo json_encode(['success' => true, 'watermark' => $watermark]);
} else {
    echo json_encode(['success' => false, 'message' => 'No watermark found']);
}
