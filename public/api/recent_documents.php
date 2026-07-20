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

try {
    // Fetch top 6 recently viewed documents
    $stmt = $pdo->prepare("
        SELECT 
            d.id, d.title, d.filename, d.file_size, d.folder_id, 
            COALESCE(f.name, 'Home') AS location_name,
            r.viewed_at
        FROM user_recent_documents r
        JOIN documents d ON r.document_id = d.id
        LEFT JOIN folders f ON d.folder_id = f.id
        WHERE r.user_id = :user_id 
          AND d.is_deleted = FALSE 
          AND (f.is_deleted = FALSE OR f.id IS NULL)
        ORDER BY r.viewed_at DESC
        LIMIT 6
    ");
    
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $recentDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'items' => $recentDocs
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
