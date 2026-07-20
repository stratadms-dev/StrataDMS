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
// If the user is already logged in, redirect them to the dashboard (which we will build next)
if (isset($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

require_once __DIR__ . '/../src/db.php';
$logoPath = null;
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'logo_path'");
    $logoPath = $stmt->fetchColumn();
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StrataDMS | Secure Login</title>
    <script src="/assets/js/vendor/tailwindcss.js"></script>
</head>
<body class="bg-gradient-to-br from-[#0f172a] to-slate-900 h-screen flex items-center justify-center font-sans">

    <div class="max-w-md w-full bg-slate-800/80 backdrop-blur-xl rounded-2xl shadow-2xl p-8 border border-slate-700/50">
        <div class="text-center mb-8">
            <?php if ($logoPath): ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>?v=<?php echo time(); ?>" alt="StrataDMS Logo" class="h-20 mx-auto mb-2 object-contain">
            <?php else: ?>
                <h1 class="text-3xl font-extrabold text-white tracking-tight">Strata<span class="text-blue-500">DMS</span></h1>
            <?php endif; ?>
            <p class="text-sm text-slate-400 mt-2">Enterprise Document Management</p>
        </div>

        <div id="errorMessage" class="hidden mb-4 p-3 bg-red-500/10 text-red-400 text-sm rounded-lg border border-red-500/20"></div>

        <form id="loginForm" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-300">Username</label>
                <input type="text" id="username" name="username" required 
                    class="mt-1 block w-full px-4 py-2.5 bg-slate-900/50 border border-slate-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors placeholder-slate-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                <input type="password" id="password" name="password" required 
                    class="mt-1 block w-full px-4 py-2.5 bg-slate-900/50 border border-slate-700 text-white rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors placeholder-slate-500">
            </div>

            <button type="submit" 
                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg shadow-blue-500/25 text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-blue-500 transition-all">
                Sign In
            </button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('errorMessage');

            try {
                const response = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (data.success) {
                    // Reload the page, which will now trigger the PHP redirect to dashboard.php
                    window.location.reload(); 
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorDiv.textContent = 'A network error occurred. Please try again.';
                errorDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>