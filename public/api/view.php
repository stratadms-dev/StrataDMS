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

// 2. Sanitize inputs
$file = basename($_GET['file'] ?? '');
$action = $_GET['action'] ?? 'view'; // 'view', 'download', 'original', 'download_version'

$docId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : null;
$folderId = null;

if (!$docId && $file) {
    // find the document_id and folder_id by the current filename
    // Note: if viewing an old version directly by filename, it might not be in `documents`, 
    // but typically view.php is called with document_id for versions.
    $stmtDoc = $pdo->prepare("SELECT id, folder_id FROM documents WHERE filename = :filename");
    $stmtDoc->execute(['filename' => $file]);
    $doc = $stmtDoc->fetch();
    if ($doc) {
        $docId = $doc['id'];
        $folderId = $doc['folder_id'];
    }
} else if ($docId) {
    $stmtDoc = $pdo->prepare("SELECT folder_id FROM documents WHERE id = :id");
    $stmtDoc->execute(['id' => $docId]);
    $folderId = $stmtDoc->fetchColumn();
}

// Ensure user has right_view for this document's folder
if (!has_permission($pdo, $_SESSION['user_id'], $folderId, 'right_view')) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

if ($action === 'original' || $action === 'download_version') {
    if ($docId) {
        $targetVersion = ($action === 'original') ? 1 : (int)$_GET['version'];
        $stmtV = $pdo->prepare("SELECT filename FROM document_versions WHERE document_id = :doc_id AND version_number = :v_num");
        $stmtV->execute(['doc_id' => $docId, 'v_num' => $targetVersion]);
        $vFile = $stmtV->fetchColumn();
        if ($vFile) {
            $file = basename($vFile);
        }
    }
}

$path = __DIR__ . '/../../storage/documents/' . $file;

if ($file && file_exists($path)) {
    // Look up the original, human-readable title from the database
    $stmt = $pdo->prepare("SELECT title FROM documents WHERE id = :id OR filename = :filename");
    $stmt->execute(['id' => $docId ?? 0, 'filename' => $file]);
    $doc = $stmt->fetch();

    $displayFilename = $doc ? $doc['title'] : 'document.pdf';

    // Set headers for PDF streaming
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($path));

    // Tell the browser to either open it in a tab (inline) or force a download (attachment)
    if ($action === 'download' || $action === 'original' || $action === 'download_version') {
        $downloadName = $displayFilename;
        if ($action === 'original') $downloadName = 'Original_' . $displayFilename;
        if ($action === 'download_version') $downloadName = 'V' . (int)$_GET['version'] . '_' . $displayFilename;
        header('Content-Disposition: attachment; filename="' . addslashes($downloadName) . '"');
    } else {
        header('Content-Disposition: inline; filename="' . addslashes($displayFilename) . '"');
    }

    // Clear any accidental whitespace or hidden errors from the output buffer
    if (ob_get_length()) {
        ob_clean();
    }
    flush();

    // Stream the file out of the hidden vault
    readfile($path);
    exit;
} else {
    header("HTTP/1.1 404 Not Found");
    exit;
}
?>
