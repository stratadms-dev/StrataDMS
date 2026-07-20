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
require_once __DIR__ . '/../../../src/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Template ID required']);
    exit;
}

try {
    // Get template details
    $stmt = $pdo->prepare("SELECT * FROM document_templates WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $template = $stmt->fetch();

    if (!$template) {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
        exit;
    }

    // Get fields
    $stmtFields = $pdo->prepare("SELECT * FROM template_fields WHERE template_id = :id ORDER BY order_index ASC");
    $stmtFields->execute(['id' => $id]);
    $fields = $stmtFields->fetchAll();

    // Parse JSON options for dropdowns
    foreach ($fields as &$field) {
        if ($field['options']) {
            $field['options'] = json_decode($field['options'], true);
        }
    }

    echo json_encode(['success' => true, 'template' => $template, 'fields' => $fields]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
