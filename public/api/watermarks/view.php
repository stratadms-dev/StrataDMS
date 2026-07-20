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
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
$filename = $_GET['file'] ?? '';
$filename = basename($filename);
$path = __DIR__ . '/../../../storage/watermarks/' . $filename;
if ($filename && file_exists($path)) {
    header('Content-Type: image/png');
    readfile($path);
} else {
    http_response_code(404);
}
