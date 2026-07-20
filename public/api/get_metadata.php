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

$document_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
if (!$document_id) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

try {
    // Get document template_id and path
    $sqlDoc = "
        WITH RECURSIVE folder_path AS (
            SELECT id, name, parent_id, name::text AS full_path
            FROM folders
            WHERE parent_id IS NULL
            
            UNION ALL
            
            SELECT f.id, f.name, f.parent_id, fp.full_path || ' / ' || f.name
            FROM folders f
            JOIN folder_path fp ON f.parent_id = fp.id
        )
        SELECT d.template_id, d.folder_id, COALESCE(fp.full_path, 'Home') as full_path, d.checked_out_by, u.username as checked_out_by_name 
        FROM documents d
        LEFT JOIN folder_path fp ON d.folder_id = fp.id
        LEFT JOIN users u ON d.checked_out_by = u.id
        WHERE d.id = :id
    ";
    $stmtDoc = $pdo->prepare($sqlDoc);
    $stmtDoc->execute(['id' => $document_id]);
    $doc = $stmtDoc->fetch();
    $template_id = $doc ? $doc['template_id'] : null;
    $folder_id = $doc ? $doc['folder_id'] : null;
    $full_path = $doc ? $doc['full_path'] : 'Home';
    $checked_out_by = $doc ? $doc['checked_out_by'] : null;
    $checked_out_by_name = $doc ? $doc['checked_out_by_name'] : null;

    $permissions = [
        'right_view' => false,
        'right_add' => false,
        'right_modify' => false,
        'right_delete' => false,
        'right_view_documents' => false,
        'right_see_through_redactions' => false,
        'right_manage_security' => false
    ];
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    if ($stmtRole->fetchColumn() === 'admin') {
        foreach ($permissions as $k => $v) $permissions[$k] = true;
    } else {
        $checkFolderId = $folder_id ?: 0;
        $stmtP = $pdo->prepare("SELECT * FROM vw_effective_permissions WHERE user_id = ? AND folder_id = ?");
        $stmtP->execute([$_SESSION['user_id'], $checkFolderId]);
        $p = $stmtP->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            foreach ($permissions as $k => $v) if (isset($p[$k])) $permissions[$k] = (bool)$p[$k];
        }
    }

    // Get all available templates for dropdown
    $stmtTpls = $pdo->query("SELECT id, name FROM document_templates ORDER BY name ASC");
    $allTemplates = $stmtTpls->fetchAll();

    $fields = [];
    $values = [];

    if ($template_id) {
        // Get fields for this template
        $stmtFields = $pdo->prepare("SELECT * FROM template_fields WHERE template_id = :id ORDER BY order_index ASC");
        $stmtFields->execute(['id' => $template_id]);
        $fields = $stmtFields->fetchAll();

        // Get saved metadata values
        $stmtMeta = $pdo->prepare("SELECT field_id, field_value FROM document_metadata WHERE document_id = :doc_id");
        $stmtMeta->execute(['doc_id' => $document_id]);
        $metaRows = $stmtMeta->fetchAll();
        foreach ($metaRows as $row) {
            $values[$row['field_id']] = $row['field_value'];
        }

        // Parse dropdown options
        foreach ($fields as &$field) {
            if ($field['options']) {
                $field['options'] = json_decode($field['options'], true);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'template_id' => $template_id,
        'templates' => $allTemplates,
        'fields' => $fields,
        'values' => $values,
        'full_path' => $full_path,
        'checked_out_by' => $checked_out_by,
        'checked_out_by_name' => $checked_out_by_name,
        'permissions' => $permissions
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
