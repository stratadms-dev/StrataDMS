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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    if (!isset($settings['min_password_length'])) $settings['min_password_length'] = 8;
    if (!isset($settings['logo_path'])) $settings['logo_path'] = '';
    
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin required']);
        exit;
    }

    $minPassLen = $_POST['min_password_length'] ?? null;
    if ($minPassLen !== null) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('min_password_length', ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
        $stmt->execute([(int)$minPassLen]);
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (strpos($mimeType, 'image/') === 0) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            $assetsDir = __DIR__ . '/../assets';
            if (!is_dir($assetsDir)) {
                mkdir($assetsDir, 0755, true);
            }
            $dest = $assetsDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $logoPath = '/assets/' . $filename;
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('logo_path', ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
                $stmt->execute([$logoPath]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Uploaded file is not a valid image.']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'message' => 'Settings updated']);
    exit;
}
