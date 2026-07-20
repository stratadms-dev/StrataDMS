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
$name = trim($data['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Group name is required']);
    exit;
}

try {
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    if ($stmtRole->fetchColumn() !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE groups SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO groups (name) VALUES (?)");
        $stmt->execute([$name]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'unique') !== false) {
        echo json_encode(['success' => false, 'message' => 'Group name already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
