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
$page_order = $data['page_order'] ?? [];

if (!$document_id || empty($page_order)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
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

    // 3. Generate new filename
    $modifiedBase = bin2hex(random_bytes(16));
    $modifiedFilename = $modifiedBase . '.pdf';
    $modifiedPath = __DIR__ . '/../../storage/documents/' . $modifiedFilename;
    $thumbModified = $modifiedBase . '.jpg';
    $thumbModifiedPath = __DIR__ . '/../../storage/thumbnails/' . $thumbModified;

    // 4. Extract individual pages
    $tempFiles = [];
    foreach ($page_order as $pageNum) {
        $pageNum = (int)$pageNum;
        $tmp = tempnam(sys_get_temp_dir(), 'page_') . '.pdf';
        $cmdExtract = sprintf(
            "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s",
            $pageNum, $pageNum, escapeshellarg($tmp), escapeshellarg($originalPath)
        );
        exec($cmdExtract, $out, $ret);
        if ($ret !== 0 || !file_exists($tmp)) {
            throw new Exception("Ghostscript failed to extract page " . $pageNum);
        }
        $tempFiles[] = $tmp;
    }

    // 5. Merge pages in new order
    $mergeInputs = array_map('escapeshellarg', $tempFiles);
    $cmdMerge = sprintf(
        "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -sOutputFile=%s %s",
        escapeshellarg($modifiedPath),
        implode(' ', $mergeInputs)
    );
    exec($cmdMerge, $out, $ret);

    // Cleanup temp files
    foreach ($tempFiles as $tmp) {
        @unlink($tmp);
    }

    if ($ret !== 0 || !file_exists($modifiedPath)) {
        throw new Exception("Ghostscript failed to merge pages.");
    }

    // 6. Generate Thumbnail for the new first page
    $cmdThumb = sprintf(
        "gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s",
        escapeshellarg($thumbModifiedPath), escapeshellarg($modifiedPath)
    );
    exec($cmdThumb);

    // 7. Update Original Document
    $stmtUpdate = $pdo->prepare("UPDATE documents SET filename = :filename, file_size = :file_size, thumbnail_filename = :thumbnail_filename WHERE id = :id");
    $stmtUpdate->execute([
        'filename' => $modifiedFilename,
        'file_size' => filesize($modifiedPath),
        'thumbnail_filename' => file_exists($thumbModifiedPath) ? $thumbModified : null,
        'id' => $document_id
    ]);

    // 8. Insert Version Record
    $actionType = "Reordered Pages";
    $stmtVInsert = $pdo->prepare("INSERT INTO document_versions (document_id, version_number, filename, action_type, created_by) VALUES (:doc_id, :v_num, :filename, :action, :user_id)");
    $stmtVInsert->execute([
        'doc_id' => $document_id,
        'v_num' => $newVersion,
        'filename' => $modifiedFilename,
        'action' => $actionType,
        'user_id' => $userId
    ]);

    // 9. Activity Log
    $stmtLog = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Reordered Pages', :item_name, 'Self')");
    $stmtLog->execute([
        'user_id' => $userId,
        'item_name' => $doc['title'] . " (Version $newVersion)"
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Pages reordered successfully.',
        'new_filename' => $modifiedFilename
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Cleanup if error occurred after merge failed
    if (isset($tempFiles) && is_array($tempFiles)) {
        foreach ($tempFiles as $tmp) @unlink($tmp);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
