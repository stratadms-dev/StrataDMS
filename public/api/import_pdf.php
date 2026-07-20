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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['pdf']) || !isset($_POST['document_id']) || !isset($_POST['current_page']) || !isset($_POST['total_pages'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$document_id = (int)$_POST['document_id'];
$pageNum = (int)$_POST['current_page'];
$totalPages = (int)$_POST['total_pages'];
$userId = $_SESSION['user_id'];

$stmtFolder = $pdo->prepare("SELECT folder_id, checked_out_by, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id");
$stmtFolder->execute(['id' => $document_id]);
$docInfo = $stmtFolder->fetch();
$docFolder = $docInfo ? $docInfo['folder_id'] : null;

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($docInfo && $docInfo['checked_out_by'] && $docInfo['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Cannot modify: Document is checked out by ' . $docInfo['checked_out_by_name']]);
    exit;
}

if (!has_permission($pdo, $userId, $docFolder, 'right_modify')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
    exit;
}

// Save uploaded file to temp
$uploadedTmpPath = tempnam(sys_get_temp_dir(), 'import_') . '.pdf';
if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $uploadedTmpPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch document
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $document_id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        throw new Exception("Document not found");
    }

    $originalFilename = $doc['filename'];
    $originalPath = __DIR__ . '/../../storage/documents/' . $originalFilename;

    if (!file_exists($originalPath)) {
        throw new Exception("Original file not found on disk");
    }

    // 2. Versioning: Ensure Version 1 exists
    $stmtV1 = $pdo->prepare("SELECT COUNT(*) FROM document_versions WHERE document_id = :doc_id");
    $stmtV1->execute(['doc_id' => $document_id]);
    $vCount = $stmtV1->fetchColumn();

    if ($vCount == 0) {
        $stmtVInsert = $pdo->prepare("INSERT INTO document_versions (document_id, version_number, filename, action_type, created_by) VALUES (:doc_id, 1, :filename, 'Initial Upload', :user_id)");
        $stmtVInsert->execute([
            'doc_id' => $document_id,
            'filename' => $originalFilename,
            'user_id' => $doc['uploaded_by']
        ]);
        $currentVersion = 1;
    } else {
        $stmtMax = $pdo->prepare("SELECT MAX(version_number) FROM document_versions WHERE document_id = :doc_id");
        $stmtMax->execute(['doc_id' => $document_id]);
        $currentVersion = $stmtMax->fetchColumn();
    }

    $newVersion = $currentVersion + 1;

    // 3. Generate new filename
    $modifiedBase = bin2hex(random_bytes(16));
    $modifiedFilename = $modifiedBase . '.pdf';
    $modifiedPath = __DIR__ . '/../../storage/documents/' . $modifiedFilename;
    $thumbModified = $modifiedBase . '.jpg';
    $thumbModifiedPath = __DIR__ . '/../../storage/thumbnails/' . $thumbModified;

    // 4. Ghostscript split and merge
    $mergeInputs = [];
    $tempFiles = [];

    if ($pageNum > 0) {
        $tmp1 = tempnam(sys_get_temp_dir(), 'p1_') . '.pdf';
        $cmd1 = sprintf("gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -dFirstPage=1 -dLastPage=%d -sOutputFile=%s %s", $pageNum, escapeshellarg($tmp1), escapeshellarg($originalPath));
        exec($cmd1, $out, $ret);
        if ($ret !== 0) throw new Exception("Ghostscript failed to extract part 1.");
        $mergeInputs[] = $tmp1;
        $tempFiles[] = $tmp1;
    }

    $mergeInputs[] = $uploadedTmpPath;

    if ($pageNum < $totalPages) {
        $tmp2 = tempnam(sys_get_temp_dir(), 'p2_') . '.pdf';
        $cmd2 = sprintf("gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s", $pageNum + 1, $totalPages, escapeshellarg($tmp2), escapeshellarg($originalPath));
        exec($cmd2, $out, $ret);
        if ($ret !== 0) throw new Exception("Ghostscript failed to extract part 2.");
        $mergeInputs[] = $tmp2;
        $tempFiles[] = $tmp2;
    }

    $mergeArgs = array_map('escapeshellarg', $mergeInputs);
    $cmdMerge = sprintf(
        "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -sOutputFile=%s %s",
        escapeshellarg($modifiedPath),
        implode(' ', $mergeArgs)
    );
    exec($cmdMerge, $out, $ret);

    // Cleanup temp files
    foreach ($tempFiles as $tmp) {
        @unlink($tmp);
    }
    @unlink($uploadedTmpPath);

    if ($ret !== 0 || !file_exists($modifiedPath)) {
        throw new Exception("Ghostscript failed to merge PDFs.");
    }

    // 5. Generate Thumbnail for the new first page
    $cmdThumb = sprintf(
        "gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s",
        escapeshellarg($thumbModifiedPath), escapeshellarg($modifiedPath)
    );
    exec($cmdThumb);

    // 6. Update Original Document
    $stmtUpdate = $pdo->prepare("UPDATE documents SET filename = :filename, file_size = :file_size, thumbnail_filename = :thumbnail_filename WHERE id = :id");
    $stmtUpdate->execute([
        'filename' => $modifiedFilename,
        'file_size' => filesize($modifiedPath),
        'thumbnail_filename' => file_exists($thumbModifiedPath) ? $thumbModified : null,
        'id' => $document_id
    ]);

    // 7. Insert Version Record
    $actionType = "Imported PDF after page " . $pageNum;
    if ($pageNum == 0) $actionType = "Imported PDF at the beginning";
    
    $stmtVInsert = $pdo->prepare("INSERT INTO document_versions (document_id, version_number, filename, action_type, created_by) VALUES (:doc_id, :v_num, :filename, :action, :user_id)");
    $stmtVInsert->execute([
        'doc_id' => $document_id,
        'v_num' => $newVersion,
        'filename' => $modifiedFilename,
        'action' => $actionType,
        'user_id' => $userId
    ]);

    // 8. Activity Log
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Imported PDF', :item_name, 'Self')");
    $stmtLog->execute([
        'user_id' => $userId,
        'item_name' => $doc['title'] . " (Version $newVersion)"
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'PDF imported successfully.',
        'new_filename' => $modifiedFilename
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($tempFiles) && is_array($tempFiles)) {
        foreach ($tempFiles as $tmp) @unlink($tmp);
    }
    @unlink($uploadedTmpPath);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
