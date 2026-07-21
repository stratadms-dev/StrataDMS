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
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['document'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$userId = $_SESSION['user_id'];
$folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== 'null' ? (int)$_POST['folder_id'] : null;

if (!has_permission($pdo, $userId, $folderId, 'right_add')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_add']);
    exit;
}

$file = $_FILES['document'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed. Error: ' . $file['error']]);
    exit;
}

// Strict MIME Validation
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    echo json_encode(['success' => false, 'message' => 'Security Error: Only valid PDF files allowed.']);
    exit;
}

// Secure Filenames
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$fileSize = $file['size'];

$secureBaseName = bin2hex(random_bytes(16));
$pdfFilename = $secureBaseName . '.pdf';
$thumbFilename = $secureBaseName . '.jpg'; 

$pdfDestination = __DIR__ . '/../../storage/documents/' . $pdfFilename;
$thumbDestination = __DIR__ . '/../../storage/thumbnails/' . $thumbFilename;

// 1. Move the PDF to the vault
if (move_uploaded_file($file['tmp_name'], $pdfDestination)) {
    
    $thumbnailGenerated = null;

    // 2. DIRECT GHOSTSCRIPT ENGINE (Bypassing ImageMagick)
    // We escape the file paths to prevent shell injection attacks
    $pdfPathEscaped = escapeshellarg($pdfDestination);
    $thumbPathEscaped = escapeshellarg($thumbDestination);

    // Command: Quiet, Safe mode, Batch mode, output JPEG at 75% quality, 100 DPI, first page only
    $gsCommand = "gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile={$thumbPathEscaped} {$pdfPathEscaped} 2>&1";
    
    exec($gsCommand, $output, $returnCode);

    // Check if Ghostscript successfully generated the thumbnail
    if ($returnCode === 0 && file_exists($thumbDestination)) {
        $thumbnailGenerated = $thumbFilename;
    } else {
        // Temporarily halt and output the raw GS error so we can see it
        echo json_encode(['success' => false, 'message' => 'GHOSTSCRIPT ERROR: ' . implode(" ", $output)]);
        exit;
    }

    // 3. Index everything in PostgreSQL
    try {
        $stmt = $pdo->prepare("INSERT INTO documents (title, filename, folder_id, uploaded_by, file_size, thumbnail_filename) VALUES (:title, :filename, :folder_id, :uploaded_by, :file_size, :thumbnail_filename) RETURNING id");
        $stmt->execute([
            'title' => $originalName,
            'filename' => $pdfFilename,
            'folder_id' => $folderId,
            'uploaded_by' => $userId,
            'file_size' => $fileSize,
            'thumbnail_filename' => $thumbnailGenerated
        ]);
        $newDocId = $stmt->fetchColumn();
        echo json_encode([
            'success' => true, 
            'message' => 'Document secured and thumbnail generated.',
            'doc_id' => $newDocId,
            'filename' => $pdfFilename,
            'title' => $originalName
        ]);
    } catch (PDOException $e) {
        unlink($pdfDestination);
        if ($thumbnailGenerated) unlink($thumbDestination);
        echo json_encode(['success' => false, 'message' => 'Database indexing failed.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Server failed to write file to storage vault.']);
}
?>