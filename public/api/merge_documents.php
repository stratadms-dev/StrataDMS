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
$source_id = $data['source_id'] ?? 0;
$target_id = $data['target_id'] ?? 0;
$position = $data['position'] ?? 'end'; // 'beginning' or 'end'

if (!$source_id || !$target_id || $source_id == $target_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid source or target document']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // 1. Fetch documents
    $stmtSrc = $pdo->prepare("SELECT *, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id FOR UPDATE");
    $stmtSrc->execute(['id' => $source_id]);
    $docSrc = $stmtSrc->fetch();

    $stmtTgt = $pdo->prepare("SELECT *, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id FOR UPDATE");
    $stmtTgt->execute(['id' => $target_id]);
    $docTgt = $stmtTgt->fetch();

    if (!$docSrc || !$docTgt) {
        throw new Exception("Document not found");
    }

    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    if ($docSrc['checked_out_by'] && $docSrc['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
        throw new Exception("Cannot merge: Source document is checked out by " . $docSrc['checked_out_by_name']);
    }
    if ($docTgt['checked_out_by'] && $docTgt['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
        throw new Exception("Cannot merge: Target document is checked out by " . $docTgt['checked_out_by_name']);
    }

    $srcPath = __DIR__ . '/../../storage/documents/' . $docSrc['filename'];
    $tgtPath = __DIR__ . '/../../storage/documents/' . $docTgt['filename'];

    if (!file_exists($srcPath) || !file_exists($tgtPath)) {
        throw new Exception("Original file not found on disk");
    }

    // 2. Versioning: Ensure Version 1 exists for Target
    $stmtV1 = $pdo->prepare("SELECT COUNT(*) FROM document_versions WHERE document_id = :doc_id");
    $stmtV1->execute(['doc_id' => $target_id]);
    $vCount = $stmtV1->fetchColumn();

    if ($vCount == 0) {
        $stmtVInsert = $pdo->prepare("INSERT INTO document_versions (document_id, version_number, filename, action_type, created_by) VALUES (:doc_id, 1, :filename, 'Initial Upload', :user_id)");
        $stmtVInsert->execute([
            'doc_id' => $target_id,
            'filename' => $docTgt['filename'],
            'user_id' => $docTgt['uploaded_by']
        ]);
        $currentVersion = 1;
    } else {
        $stmtMax = $pdo->prepare("SELECT MAX(version_number) FROM document_versions WHERE document_id = :doc_id");
        $stmtMax->execute(['doc_id' => $target_id]);
        $currentVersion = $stmtMax->fetchColumn();
    }

    $newVersion = $currentVersion + 1;

    // 3. Generate new merged filename
    $mergedBase = bin2hex(random_bytes(16));
    $mergedFilename = $mergedBase . '.pdf';
    $mergedPath = __DIR__ . '/../../storage/documents/' . $mergedFilename;
    $thumbMerged = $mergedBase . '.jpg';
    $thumbMergedPath = __DIR__ . '/../../storage/thumbnails/' . $thumbMerged;

    // 4. Merge PDFs using Ghostscript
    if ($position === 'beginning') {
        $cmdMerge = sprintf(
            "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=%s %s %s 2>&1",
            escapeshellarg($mergedPath), escapeshellarg($srcPath), escapeshellarg($tgtPath)
        );
    } else {
        $cmdMerge = sprintf(
            "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=%s %s %s 2>&1",
            escapeshellarg($mergedPath), escapeshellarg($tgtPath), escapeshellarg($srcPath)
        );
    }
    
    exec($cmdMerge, $outMerge, $retMerge);
    if ($retMerge !== 0 || !file_exists($mergedPath)) {
        throw new Exception("Ghostscript failed to merge documents.");
    }

    // 5. Generate Thumbnail for Merged Document
    $cmdThumb = sprintf("gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s", escapeshellarg($thumbMergedPath), escapeshellarg($mergedPath));
    exec($cmdThumb);

    // 6. Update Target Document
    $stmtUpdateTgt = $pdo->prepare("UPDATE documents SET filename = :filename, file_size = :file_size, thumbnail_filename = :thumbnail_filename WHERE id = :id");
    $stmtUpdateTgt->execute([
        'filename' => $mergedFilename,
        'file_size' => filesize($mergedPath),
        'thumbnail_filename' => file_exists($thumbMergedPath) ? $thumbMerged : null,
        'id' => $target_id
    ]);

    // 7. Insert Version Record for Target Document
    $actionType = "Merged Document '" . $docSrc['title'] . "' to the " . $position;
    $stmtVInsert = $pdo->prepare("INSERT INTO document_versions (document_id, version_number, filename, action_type, created_by) VALUES (:doc_id, :v_num, :filename, :action, :user_id)");
    $stmtVInsert->execute([
        'doc_id' => $target_id,
        'v_num' => $newVersion,
        'filename' => $mergedFilename,
        'action' => $actionType,
        'user_id' => $userId
    ]);

    // 8. Delete Source Document (Send to Recycle Bin)
    $stmtDelSrc = $pdo->prepare("UPDATE documents SET is_deleted = true, deleted_at = NOW(), deleted_by = :user_id WHERE id = :id");
    $stmtDelSrc->execute([
        'user_id' => $userId,
        'id' => $source_id
    ]);

    // 9. Activity Logs
    // Log for target
    $stmtLogTgt = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Merged Document', :item_name, 'Workspace')");
    $stmtLogTgt->execute([
        'user_id' => $userId,
        'item_name' => $docTgt['title'] . " (Version $newVersion)"
    ]);

    // Log for source
    $stmtLogSrc = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, item_name, target_name) VALUES (:user_id, 'Deleted Document (Absorbed)', :item_name, 'Recycle Bin')");
    $stmtLogSrc->execute([
        'user_id' => $userId,
        'item_name' => $docSrc['title']
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Documents merged successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
