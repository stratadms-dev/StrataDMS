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
require_once __DIR__ . '/check_permission.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

// 2. Get and sanitize the filename to prevent directory traversal attacks
$file = basename($_GET['file'] ?? '');

if ($file) {
    $stmtDoc = $pdo->prepare("SELECT folder_id FROM documents WHERE thumbnail_filename = :filename");
    $stmtDoc->execute(['filename' => $file]);
    $folderId = $stmtDoc->fetchColumn();

    if ($folderId !== false && !has_permission($pdo, $_SESSION['user_id'], $folderId, 'right_view')) {
        header("HTTP/1.1 403 Forbidden");
        exit;
    }
}

$path = __DIR__ . '/../../storage/thumbnails/' . $file;

// 3. Serve the file if it exists
if ($file && file_exists($path)) {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
} else {
    // Return a 404 if the file is missing
    header("HTTP/1.1 404 Not Found");
    exit;
}
?>
