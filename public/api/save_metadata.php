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
$template_id = $data['template_id'] ?? null;
$metadata = $data['metadata'] ?? [];

if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

$stmtFolder = $pdo->prepare("SELECT folder_id, checked_out_by, (SELECT username FROM users WHERE id = checked_out_by) as checked_out_by_name FROM documents WHERE id = :id");
$stmtFolder->execute(['id' => $document_id]);
$docInfo = $stmtFolder->fetch();
$docFolder = $docInfo ? $docInfo['folder_id'] : null;

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($docInfo && $docInfo['checked_out_by'] && $docInfo['checked_out_by'] != $_SESSION['user_id'] && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Cannot modify: Document is checked out by ' . $docInfo['checked_out_by_name']]);
    exit;
}

if (!has_permission($pdo, $_SESSION['user_id'], $docFolder, 'right_modify')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: Missing right_modify']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update document's template_id
    $stmtDoc = $pdo->prepare("UPDATE documents SET template_id = :template_id, updated_at = NOW(), updated_by = :user_id WHERE id = :id");
    $stmtDoc->execute([
        'template_id' => $template_id ?: null,
        'user_id' => $_SESSION['user_id'],
        'id' => $document_id
    ]);

    // Clear old metadata
    $stmtClear = $pdo->prepare("DELETE FROM document_metadata WHERE document_id = :doc_id");
    $stmtClear->execute(['doc_id' => $document_id]);

    // Insert new metadata values if template is selected
    if ($template_id && !empty($metadata)) {
        $stmtInsert = $pdo->prepare("INSERT INTO document_metadata (document_id, field_id, field_value) VALUES (:doc_id, :field_id, :val)");
        foreach ($metadata as $field_id => $val) {
            $stmtInsert->execute([
                'doc_id' => $document_id,
                'field_id' => $field_id,
                'val' => $val
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Metadata saved successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
