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

$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = :id");
$stmtRole->execute(['id' => $_SESSION['user_id']]);
if ($stmtRole->fetchColumn() !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Only admins can manage global stamps']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$name = trim($data['name'] ?? '');
$text = trim($data['stamp_text'] ?? '');
$font = $data['font'] ?? 'Helvetica-Bold';
$size = (int)($data['font_size'] ?? 36);
$color = $data['color'] ?? '#FF0000';

if (empty($name) || empty($text)) {
    echo json_encode(['success' => false, 'message' => 'Name and Text are required.']);
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE stamps SET name = :name, stamp_text = :text, font = :font, font_size = :size, color = :color WHERE id = :id");
        $stmt->execute([
            'name' => $name, 'text' => $text, 'font' => $font, 'size' => $size, 'color' => $color, 'id' => $id
        ]);
        $message = "Stamp updated successfully.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO stamps (name, stamp_text, font, font_size, color, created_by) VALUES (:name, :text, :font, :size, :color, :user)");
        $stmt->execute([
            'name' => $name, 'text' => $text, 'font' => $font, 'size' => $size, 'color' => $color, 'user' => $_SESSION['user_id']
        ]);
        $message = "Stamp created successfully.";
    }
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
