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

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$name = $data['name'] ?? '';
$description = $data['description'] ?? '';
$fields = $data['fields'] ?? [];

if (trim($name) === '') {
    echo json_encode(['success' => false, 'message' => 'Template name is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id) {
        // Update existing template
        $stmt = $pdo->prepare("UPDATE document_templates SET name = :name, description = :description WHERE id = :id");
        $stmt->execute(['name' => $name, 'description' => $description, 'id' => $id]);
        $templateId = $id;

        // Delete existing fields to replace them
        $stmtDel = $pdo->prepare("DELETE FROM template_fields WHERE template_id = :id");
        $stmtDel->execute(['id' => $templateId]);
    } else {
        // Create new template
        $stmt = $pdo->prepare("INSERT INTO document_templates (name, description, created_by) VALUES (:name, :description, :created_by) RETURNING id");
        $stmt->execute([
            'name' => $name, 
            'description' => $description,
            'created_by' => $_SESSION['user_id']
        ]);
        $templateId = $stmt->fetchColumn();
    }

    // Insert new fields
    if (!empty($fields)) {
        $stmtField = $pdo->prepare("INSERT INTO template_fields (template_id, name, type, options, is_required, order_index) VALUES (:template_id, :name, :type, :options, :is_required, :order_index)");
        
        foreach ($fields as $index => $field) {
            $options = null;
            if ($field['type'] === 'dropdown' && !empty($field['options'])) {
                // Ensure options are stored as JSON array string
                $options = is_array($field['options']) ? json_encode($field['options']) : json_encode(array_map('trim', explode(',', $field['options'])));
            }

            $stmtField->execute([
                'template_id' => $templateId,
                'name' => $field['name'],
                'type' => $field['type'],
                'options' => $options,
                'is_required' => isset($field['is_required']) ? ($field['is_required'] ? 1 : 0) : 0,
                'order_index' => $index
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Template saved successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
