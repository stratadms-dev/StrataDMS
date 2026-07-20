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

// Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Fetch the 20 most recent activity logs for this user
    $stmt = $pdo->prepare("
        SELECT id, action_type, item_name, target_name, created_at
        FROM activity_log
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'activities' => $activities]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error while fetching activity log.']);
}
?>
