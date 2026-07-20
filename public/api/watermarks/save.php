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

try {
    session_start();
    require_once __DIR__ . '/../../../src/db.php';
    require_once __DIR__ . '/../check_permission.php';

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $isJson = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    if ($isJson) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }

    $document_id = isset($data['document_id']) && $data['document_id'] !== '' && $data['document_id'] !== 'null' && $data['document_id'] !== 'undefined' ? (int)$data['document_id'] : null;
    $type = $data['type'] ?? 'text';
    $text = $data['text'] ?? '';
    $h_pos = $data['h_pos'] ?? 'center';
    $v_pos = $data['v_pos'] ?? 'center';
    $rotation = isset($data['rotation']) ? (int)$data['rotation'] : 0;
    $size_pct = isset($data['size_pct']) ? (int)$data['size_pct'] : 50;
    $opacity = isset($data['opacity']) ? (int)$data['opacity'] : 50;
    $offset_x = isset($data['offset_x']) ? (int)$data['offset_x'] : 0;
    $offset_y = isset($data['offset_y']) ? (int)$data['offset_y'] : 0;

    if ($document_id) {
        $stmtFolder = $pdo->prepare("SELECT folder_id FROM documents WHERE id = :id");
        $stmtFolder->execute(['id' => $document_id]);
        $docFolder = $stmtFolder->fetchColumn();

        if (!has_permission($pdo, $_SESSION['user_id'], $docFolder, 'right_modify')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
            exit;
        }
    } else {
        $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmtRole->execute(['id' => $_SESSION['user_id']]);
        if ($stmtRole->fetchColumn() !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Only admins can modify global watermarks']);
            exit;
        }
    }

    if ($type === 'text' && empty($text)) {
        $stmt = $pdo->prepare("DELETE FROM watermarks WHERE document_id = :did OR (document_id IS NULL AND :did IS NULL)");
        $stmt->execute(['did' => $document_id]);
        echo json_encode(['success' => true, 'action' => 'deleted']);
        exit;
    }

    // Handle Image Upload
    $image_filename = null;
    if ($type === 'image' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'png') {
            echo json_encode(['success' => false, 'message' => 'Only PNG images are supported.']);
            exit;
        }
        $image_filename = uniqid('wm_') . '.png';
        $targetPath = __DIR__ . '/../../../storage/watermarks/' . $image_filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            throw new Exception("Failed to move uploaded file. Check permissions on storage/watermarks/");
        }
    } elseif ($type === 'image' && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new Exception("Upload error code: " . $_FILES['image']['error']);
    }

    // Check if watermark exists
    $stmt = $pdo->prepare("SELECT id, image_filename FROM watermarks WHERE document_id = :did OR (document_id IS NULL AND :did IS NULL)");
    $stmt->execute(['did' => $document_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($type === 'image' && !$image_filename) {
            $image_filename = $existing['image_filename'];
        }
        if ($type === 'text' && !empty($existing['image_filename'])) {
            @unlink(__DIR__ . '/../../../storage/watermarks/' . $existing['image_filename']);
            $image_filename = null;
        }
        
        $stmt = $pdo->prepare("UPDATE watermarks SET text = :text, h_pos = :h_pos, v_pos = :v_pos, offset_x = :offset_x, offset_y = :offset_y, rotation = :rotation, size_pct = :size_pct, opacity = :opacity, image_filename = :img WHERE id = :id");
        $stmt->execute([
            'text' => $text,
            'h_pos' => $h_pos,
            'v_pos' => $v_pos,
            'offset_x' => $offset_x,
            'offset_y' => $offset_y,
            'rotation' => $rotation,
            'size_pct' => $size_pct,
            'opacity' => $opacity,
            'img' => $image_filename,
            'id' => $existing['id']
        ]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO watermarks (document_id, text, h_pos, v_pos, offset_x, offset_y, rotation, size_pct, opacity, image_filename) VALUES (:did, :text, :h_pos, :v_pos, :offset_x, :offset_y, :rotation, :size_pct, :opacity, :img)");
        $stmt->execute([
            'did' => $document_id,
            'text' => $text,
            'h_pos' => $h_pos,
            'v_pos' => $v_pos,
            'offset_x' => $offset_x,
            'offset_y' => $offset_y,
            'rotation' => $rotation,
            'size_pct' => $size_pct,
            'opacity' => $opacity,
            'img' => $image_filename
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    file_put_contents('/tmp/wm_error.log', $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
