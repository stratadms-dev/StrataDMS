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

try {
    $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$_SESSION['user_id']]);
    if ($stmtRole->fetchColumn() !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $group_id = $_GET['group_id'] ?? null;
        if (!$group_id) {
            echo json_encode(['success' => false, 'message' => 'Group ID required']);
            exit;
        }

        // Get users in group
        $stmtIn = $pdo->prepare("
            SELECT u.id, u.username 
            FROM users u
            JOIN user_groups ug ON u.id = ug.user_id
            WHERE ug.group_id = ?
            ORDER BY u.username ASC
        ");
        $stmtIn->execute([$group_id]);
        $inGroup = $stmtIn->fetchAll(PDO::FETCH_ASSOC);

        // Get users NOT in group
        $stmtOut = $pdo->prepare("
            SELECT u.id, u.username 
            FROM users u
            WHERE u.id NOT IN (SELECT user_id FROM user_groups WHERE group_id = ?)
            ORDER BY u.username ASC
        ");
        $stmtOut->execute([$group_id]);
        $outGroup = $stmtOut->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'in_group' => $inGroup, 'out_group' => $outGroup]);
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? null;
        $group_id = $data['group_id'] ?? null;
        $user_id = $data['user_id'] ?? null;

        if (!$group_id || !$user_id || !in_array($action, ['add', 'remove'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
            $stmt->execute([$user_id, $group_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM user_groups WHERE user_id = ? AND group_id = ?");
            $stmt->execute([$user_id, $group_id]);
        }

        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
