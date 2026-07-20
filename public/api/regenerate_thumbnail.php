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
$docId = $data['id'] ?? null;

if (!$docId) {
    echo json_encode(['success' => false, 'message' => 'Document ID required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT filename, thumbnail_filename FROM documents WHERE id = :id");
    $stmt->execute(['id' => $docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found.']);
        exit;
    }

    $pdfFilename = $doc['filename'];
    $thumbFilename = $doc['thumbnail_filename'];

    if (!$thumbFilename) {
        $secureBaseName = bin2hex(random_bytes(16));
        $thumbFilename = $secureBaseName . '.jpg';
    }

    $pdfDestination = __DIR__ . '/../../storage/documents/' . $pdfFilename;
    $thumbDestination = __DIR__ . '/../../storage/thumbnails/' . $thumbFilename;

    if (!file_exists($pdfDestination)) {
        echo json_encode(['success' => false, 'message' => 'Original PDF file is missing from the server.']);
        exit;
    }

    $pdfPathEscaped = escapeshellarg($pdfDestination);
    $thumbPathEscaped = escapeshellarg($thumbDestination);

    $gsCommand = "gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=jpeg -dJPEGQ=75 -r100 -dFirstPage=1 -dLastPage=1 -sOutputFile={$thumbPathEscaped} {$pdfPathEscaped} 2>&1";
    
    exec($gsCommand, $output, $returnCode);

    if ($returnCode === 0 && file_exists($thumbDestination)) {
        $update = $pdo->prepare("UPDATE documents SET thumbnail_filename = :thumb WHERE id = :id");
        $update->execute(['thumb' => $thumbFilename, 'id' => $docId]);
        echo json_encode(['success' => true, 'message' => 'Thumbnail regenerated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ghostscript error: ' . implode(" ", $output)]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
