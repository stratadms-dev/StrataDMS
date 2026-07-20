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

require_once __DIR__ . '/../../../src/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'user';

if (!$id || !$username) {
    echo json_encode(['success' => false, 'message' => 'ID and username required']);
    exit;
}

try {
    if ($password) {
        $stmt_settings = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'min_password_length'");
        $min_len = (int)$stmt_settings->fetchColumn() ?: 8;

        if (strlen($password) < $min_len) {
            echo json_encode(['success' => false, 'message' => "Password must be at least $min_len characters."]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?");
        $stmt->execute([$username, $hash, $role, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        $stmt->execute([$username, $role, $id]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'unique constraint') !== false) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
