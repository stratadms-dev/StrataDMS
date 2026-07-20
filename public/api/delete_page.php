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

$data = json_decode(file_get_contents('php://input'), true);
$document_id = $data['document_id'] ?? 0;
$page_nums = isset($data['page_nums']) ? $data['page_nums'] : (isset($data['page_num']) ? [$data['page_num']] : []);
$total_pages = (int)($data['total_pages'] ?? 0);

if (!$document_id || empty($page_nums) || !$total_pages) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$page_nums = array_map('intval', $page_nums);
// sort descending, just in case, but actually ascending is better for extracting in order
sort($page_nums);

if (count($page_nums) >= $total_pages) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete all pages. Please delete the entire document instead.']);
    exit;
}

foreach ($page_nums as $p) {
    if ($p < 1 || $p > $total_pages) {
        echo json_encode(['success' => false, 'message' => 'Invalid page number']);
        exit;
    }
}

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

    // 3. Generate new filenames
    $modifiedBase = bin2hex(random_bytes(16));
    $extractedBase = bin2hex(random_bytes(16));

    $modifiedFilename = $modifiedBase . '.pdf';
    $extractedFilename = $extractedBase . '.pdf';
    
    $modifiedPath = __DIR__ . '/../../storage/documents/' . $modifiedFilename;
    $extractedPath = __DIR__ . '/../../storage/documents/' . $extractedFilename;

    $thumbModified = $modifiedBase . '.jpg';
    $thumbExtracted = $extractedBase . '.jpg';

    // 4 & 5. Extract and Rebuild PDFs using single page extraction
    $keptTempFiles = [];
    $deletedTempFiles = [];

    for ($i = 1; $i <= $total_pages; $i++) {
        $tmp = tempnam(sys_get_temp_dir(), 'p_') . '.pdf';
        $cmd = sprintf(
            "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s",
            $i, $i, escapeshellarg($tmp), escapeshellarg($originalPath)
        );
        exec($cmd, $out, $ret);
        
        if ($ret !== 0 || !file_exists($tmp)) {
            throw new Exception("Ghostscript failed to extract page $i.");
        }
        
        if (in_array($i, $page_nums)) {
            $deletedTempFiles[] = $tmp;
        } else {
            $keptTempFiles[] = $tmp;
        }
    }

    // Merge Kept Pages
    $keptInputs = array_map('escapeshellarg', $keptTempFiles);
    $cmdMergeKept = sprintf("gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -sOutputFile=%s %s", escapeshellarg($modifiedPath), implode(' ', $keptInputs));
    exec($cmdMergeKept, $outRem, $retRem);
    if ($retRem !== 0 || !file_exists($modifiedPath)) throw new Exception("Ghostscript failed to rebuild PDF.");

    // Merge Deleted Pages
    $delInputs = array_map('escapeshellarg', $deletedTempFiles);
    $cmdMergeDel = sprintf("gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -sOutputFile=%s %s", escapeshellarg($extractedPath), implode(' ', $delInputs));
    exec($cmdMergeDel, $outExt, $retExt);
    if ($retExt !== 0 || !file_exists($extractedPath)) throw new Exception("Ghostscript failed to build extracted PDF.");

    // Cleanup temp files
    foreach ($keptTempFiles as $tmp) @unlink($tmp);
    foreach ($deletedTempFiles as $tmp) @unlink($tmp);

    // 6. Generate Thumbnails
    $thumbModifiedPath = __DIR__ . '/../../storage/thumbnails/' . $thumbModified;
    $cmdThumbMod = sprintf("gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s", escapeshellarg($thumbModifiedPath), escapeshellarg($modifiedPath));
    exec($cmdThumbMod);

    $thumbExtractedPath = __DIR__ . '/../../storage/thumbnails/' . $thumbExtracted;
    $cmdThumbExt = sprintf("gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s", escapeshellarg($thumbExtractedPath), escapeshellarg($extractedPath));
    exec($cmdThumbExt);

    // 7. Update Documents Table (Extracted Page to Recycle Bin)
    $stmtNewDoc = $pdo->prepare("INSERT INTO documents (title, filename, folder_id, uploaded_by, file_size, thumbnail_filename, is_deleted, deleted_at, deleted_by) VALUES (:title, :filename, :folder_id, :uploaded_by, :file_size, :thumbnail_filename, true, NOW(), :deleted_by)");
    $stmtNewDoc->execute([
        'title' => "Deleted Pages (" . implode(', ', $page_nums) . ") from " . $doc['title'],
        'filename' => $extractedFilename,
        'folder_id' => $doc['folder_id'],
        'uploaded_by' => $doc['uploaded_by'],
        'file_size' => filesize($extractedPath),
        'thumbnail_filename' => file_exists($thumbExtractedPath) ? $thumbExtracted : null,
        'deleted_by' => $userId
    ]);

    // 8. Update Original Document
    $stmtUpdate = $pdo->prepare("UPDATE documents SET filename = :filename, file_size = :file_size, thumbnail_filename = :thumbnail_filename WHERE id = :id");
    $stmtUpdate->execute([
        'filename' => $modifiedFilename,
        'file_size' => filesize($modifiedPath),
        'thumbnail_filename' => file_exists($thumbModifiedPath) ? $thumbModified : null,
        'id' => $document_id
    ]);

    // 9. Insert Version Record
    $actionType = "Deleted Pages: " . implode(', ', $page_nums);
    $stmtVInsert = $pdo->prepare("INSERT INTO document_versions (document_id, version_number, filename, action_type, created_by) VALUES (:doc_id, :v_num, :filename, :action, :user_id)");
    $stmtVInsert->execute([
        'doc_id' => $document_id,
        'v_num' => $newVersion,
        'filename' => $modifiedFilename,
        'action' => $actionType,
        'user_id' => $userId
    ]);

    // 10. Activity Log
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Removed Page', :item_name, 'Recycle Bin')");
    $stmtLog->execute([
        'user_id' => $userId,
        'item_name' => $doc['title'] . " (Version $newVersion)"
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Page deleted and original file preserved as new version.',
        'new_filename' => $modifiedFilename
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
