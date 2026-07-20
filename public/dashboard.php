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

// Security Check: If there is no active session, kick them back to the login screen
if (!isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

// Grab user info for the UI
$username = htmlspecialchars($_SESSION['username']);
$role = htmlspecialchars($_SESSION['role']);

require_once __DIR__ . '/../src/db.php';

$logoPath = null;
$minPasswordLength = 8;
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        if ($row['setting_key'] === 'logo_path') $logoPath = $row['setting_value'];
        if ($row['setting_key'] === 'min_password_length') $minPasswordLength = (int)$row['setting_value'];
    }
} catch (Exception $e) {}

// Fetch templates and fields for the detail view columns
$stmtTpls = $pdo->query("SELECT id, name FROM document_templates ORDER BY name ASC");
$allTemplates = $stmtTpls->fetchAll();

$stmtFields = $pdo->query("SELECT id, template_id, name FROM template_fields ORDER BY order_index ASC");
$allFields = $stmtFields->fetchAll();

$tplFields = [];
$templateFieldsMap = [];
foreach ($allFields as $f) {
    $tplFields[$f['template_id']][] = $f;
    $key = "tpl_" . $f['template_id'] . "_" . $f['id'];
    $templateFieldsMap[$key] = $f['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | StrataDMS</title>
    <script src="/assets/js/vendor/tailwindcss.js"></script>
    <style>
        :root {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --slate-950: #020617;
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
            --blue-200: #bfdbfe;
            --blue-300: #93c5fd;
            --blue-400: #60a5fa;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --blue-800: #1e40af;
            --blue-900: #1e3a8a;
            --red-50: #fef2f2;
            --red-100: #fee2e2;
            --red-200: #fecaca;
            --red-300: #fca5a5;
            --red-400: #f87171;
            --red-500: #ef4444;
            --red-600: #dc2626;
            --red-700: #b91c1c;
            --red-800: #991b1b;
            --red-900: #7f1d1d;
            --green-50: #f0fdf4;
            --green-100: #dcfce7;
            --green-200: #bbf7d0;
            --green-300: #86efac;
            --green-400: #4ade80;
            --green-500: #22c55e;
            --green-600: #16a34a;
            --green-700: #15803d;
            --green-800: #166534;
            --amber-500: #f59e0b;
            --white: #ffffff;
            --black: #000000;
        }

        html.dark {
            color-scheme: dark;
            --slate-50: #1e293b;
            --slate-100: #334155;
            --slate-200: #475569;
            --slate-300: #64748b;
            --slate-400: #94a3b8;
            --slate-500: #cbd5e1;
            --slate-600: #e2e8f0;
            --slate-700: #f1f5f9;
            --slate-800: #f8fafc;
            --slate-900: #ffffff;
            --slate-950: #ffffff;
            --blue-50: #1e3a8a;
            --blue-100: #1e40af;
            --blue-200: #1d4ed8;
            --blue-300: #2563eb;
            --blue-400: #3b82f6;
            --blue-500: #60a5fa;
            --blue-600: #93c5fd;
            --blue-700: #bfdbfe;
            --blue-800: #dbeafe;
            --blue-900: #eff6ff;
            --red-50: #7f1d1d;
            --red-100: #991b1b;
            --red-200: #b91c1c;
            --red-300: #dc2626;
            --red-400: #ef4444;
            --red-500: #f87171;
            --red-600: #fca5a5;
            --red-700: #fecaca;
            --red-800: #fee2e2;
            --red-900: #fef2f2;
            --green-50: #14532d;
            --green-100: #166534;
            --green-200: #15803d;
            --green-300: #16a34a;
            --green-400: #22c55e;
            --green-500: #4ade80;
            --green-600: #86efac;
            --green-700: #bbf7d0;
            --green-800: #dcfce7;
            --amber-500: #fbbf24;
            --white: #ffffff; /* Keep white as white for text-white */
            --black: #000000;
        }

        /* Specific overrides for utility classes that don't map cleanly via variables */
        html.dark .bg-white { background-color: #0f172a !important; }
        
        /* Force inputs/selects to have light text in dark mode if browser defaults to black */
        html.dark input:not([type="checkbox"]):not([type="radio"]):not([type="color"]), html.dark select, html.dark textarea {
            color: #f8fafc !important;
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
    </style>
    <script>
        // Inline script to prevent flash of light mode
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        function toggleDarkMode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        slate: {
                            50: 'var(--slate-50)',
                            100: 'var(--slate-100)',
                            200: 'var(--slate-200)',
                            300: 'var(--slate-300)',
                            400: 'var(--slate-400)',
                            500: 'var(--slate-500)',
                            600: 'var(--slate-600)',
                            700: 'var(--slate-700)',
                            800: 'var(--slate-800)',
                            900: 'var(--slate-900)',
                            950: 'var(--slate-950)',
                        },
                        blue: {
                            50: 'var(--blue-50)',
                            100: 'var(--blue-100)',
                            200: 'var(--blue-200)',
                            300: 'var(--blue-300)',
                            400: 'var(--blue-400)',
                            500: 'var(--blue-500)',
                            600: 'var(--blue-600)',
                            700: 'var(--blue-700)',
                            800: 'var(--blue-800)',
                            900: 'var(--blue-900)',
                        },
                        red: {
                            50: 'var(--red-50)',
                            100: 'var(--red-100)',
                            200: 'var(--red-200)',
                            300: 'var(--red-300)',
                            400: 'var(--red-400)',
                            500: 'var(--red-500)',
                            600: 'var(--red-600)',
                            700: 'var(--red-700)',
                            800: 'var(--red-800)',
                            900: 'var(--red-900)',
                        },
                        green: {
                            50: 'var(--green-50)',
                            100: 'var(--green-100)',
                            200: 'var(--green-200)',
                            300: 'var(--green-300)',
                            400: 'var(--green-400)',
                            500: 'var(--green-500)',
                            600: 'var(--green-600)',
                            700: 'var(--green-700)',
                            800: 'var(--green-800)',
                        },
                        amber: {
                            500: 'var(--amber-500)',
                        },
                        white: 'var(--white)',
                        black: 'var(--black)',
                    }
                }
            }
        }
    </script>
    <script>
        window.templateFields = <?php echo json_encode($templateFieldsMap); ?>;
        window.templateFieldsByTpl = <?php echo json_encode($tplFields); ?>;
        window.allTemplates = <?php echo json_encode($allTemplates); ?>;
        window.currentUserRole = <?php echo json_encode($role); ?>;
        window.currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    </script>
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden">

    <aside id="leftSidebar" class="w-64 bg-[#0f172a] text-white flex flex-col hidden md:flex transition-all duration-300">
        <div class="h-16 flex items-center px-6 border-b border-slate-800">
            <?php if ($logoPath): ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>?v=<?php echo time(); ?>" alt="StrataDMS Logo" class="h-10 max-w-full object-contain">
            <?php else: ?>
                <h1 class="text-2xl font-extrabold tracking-tight">Strata<span class="text-blue-500">DMS</span></h1>
            <?php endif; ?>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <button onclick="toggleAppView('files')" id="nav-dashboard" class="w-full flex items-center gap-3 px-3 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Home
            </button>
            <?php if($role === 'admin'): ?>
            <button onclick="toggleAppView('templates')" id="nav-templates" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-[#1e293b] hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Templates
            </button>
            <?php endif; ?>
            <button onclick="toggleAppView('search')" id="nav-search" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-[#1e293b] hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                Search
            </button>
            <button onclick="toggleAppView('recyclebin')" id="nav-recyclebin" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-[#1e293b] hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Recycle Bin
            </button>
            <?php if($role === 'admin'): ?>
            <button onclick="toggleAppView('users')" id="nav-users" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-[#1e293b] hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Users & Security
            </button>
            <button onclick="toggleAppView('settings')" id="nav-settings" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-[#1e293b] hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Settings
            </button>
            <?php endif; ?>

            <div id="sidebarNavHeader" class="pt-4 pb-2">
                <p class="px-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Navigation</p>
            </div>
            <div id="sidebarFolderTree" class="space-y-1">
                <!-- Tree will be injected here -->
            </div>
        </nav>

        <div class="p-4 border-t border-slate-800">
            <a href="/logout.php" class="flex items-center gap-3 px-3 py-2 w-full text-left rounded-lg text-sm font-medium text-slate-400 hover:bg-red-900/50 hover:text-red-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Sign Out
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-hidden">

        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 z-10">
            <div class="flex items-center gap-4">
                <button onclick="toggleLeftSidebar()" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors" title="Toggle Sidebar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h2 class="text-xl font-semibold text-slate-800">Home Overview</h2>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="toggleDarkMode()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500 transition-colors" title="Toggle Dark Mode">
                    <!-- Sun icon for dark mode -->
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <!-- Moon icon for light mode -->
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                <div class="text-right">
                    <p class="text-sm font-medium text-slate-900"><?php echo ucfirst($username); ?></p>
                    <p class="text-xs text-slate-500 uppercase tracking-wider"><?php echo $role; ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold border border-blue-200">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
            </div>
        </header>

     <div id="mainFilesView" class="p-8 flex-1 flex flex-col min-h-0 overflow-hidden">
        <div class="flex items-center justify-between bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
    <div class="flex items-center gap-4 flex-1 min-w-0">
        <div class="relative flex items-center flex-1 min-w-0 group">
            <button id="breadcrumbScrollLeft" onclick="scrollBreadcrumb(-200)" class="hidden absolute left-0 z-10 p-1 bg-gradient-to-r from-white via-white/80 to-transparent pr-4 text-slate-500 hover:text-blue-600 transition-colors h-full flex items-center">
                <svg class="w-4 h-4 bg-white rounded-full shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            
            <div class="flex items-center gap-2 text-sm font-medium text-slate-600 overflow-x-auto whitespace-nowrap flex-1 min-w-0 pb-1 px-2" id="breadcrumb" style="scrollbar-width: none; -ms-overflow-style: none;" onscroll="updateBreadcrumbScrollButtons()">
                <button onclick="loadFolder(null)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'null')" class="hover:text-blue-600 transition-colors p-1 rounded shrink-0">Home</button>
            </div>
            
            <button id="breadcrumbScrollRight" onclick="scrollBreadcrumb(200)" class="hidden absolute right-0 z-10 p-1 bg-gradient-to-l from-white via-white/80 to-transparent pl-4 text-slate-500 hover:text-blue-600 transition-colors h-full flex items-center justify-end">
                <svg class="w-4 h-4 bg-white rounded-full shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>
        
        <button onclick="loadFolder(currentFolderId)" class="hidden md:flex items-center justify-center p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors ml-2" title="Refresh">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        </button>

        <div class="hidden md:flex items-center bg-slate-100 p-1 rounded-lg border border-slate-200 ml-4">
            <div class="relative flex items-center">
                <button onclick="toggleColumnMenu(event)" class="flex items-center gap-1 p-1.5 rounded-md text-slate-500 hover:text-slate-700 transition-all text-sm font-medium" title="Columns">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                </button>
                <div id="columnMenu" class="hidden absolute top-8 right-0 mt-2 w-40 bg-white rounded-lg shadow-xl border border-slate-200 py-1 z-50">
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="type" onchange="updateColumns(this)" checked disabled> Type
                    </label>
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="name" onchange="updateColumns(this)" checked disabled> Name
                    </label>
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="col-cb-size" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="size" onchange="updateColumns(this)"> Size
                    </label>
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="col-cb-added" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="added" onchange="updateColumns(this)"> Added
                    </label>
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="col-cb-added-by" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="added_by" onchange="updateColumns(this)"> Added By
                    </label>
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="col-cb-modified-by" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="modified_by" onchange="updateColumns(this)"> Last Modified By
                    </label>
                    <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="col-cb-modified-at" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="modified_at" onchange="updateColumns(this)"> Last Modified At
                    </label>
                    <div class="relative group/tpl border-t border-slate-200 mt-1 pt-1">
                        <div class="px-4 py-2 hover:bg-slate-50 flex justify-between items-center text-sm text-slate-700 cursor-default">
                            Templates 
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                        <div class="absolute left-full top-0 w-48 bg-white rounded-md shadow-xl border border-slate-200 py-1 hidden group-hover/tpl:block">
                            <?php foreach($allTemplates as $tpl): ?>
                            <div class="relative group/item">
                                <div class="px-4 py-2 hover:bg-slate-50 flex justify-between items-center text-sm text-slate-700 cursor-default">
                                    <?php echo htmlspecialchars($tpl['name']); ?>
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </div>
                                <div class="absolute left-full top-0 w-48 bg-white rounded-md shadow-xl border border-slate-200 py-1 hidden group-hover/item:block">
                                    <?php if(empty($tplFields[$tpl['id']])): ?>
                                        <div class="px-4 py-2 text-xs text-slate-400">No fields</div>
                                    <?php else: ?>
                                        <?php foreach($tplFields[$tpl['id']] as $f): 
                                            $colKey = "tpl_" . $tpl['id'] . "_" . $f['id'];
                                        ?>
                                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                                            <input type="checkbox" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500 col-cb-dynamic" value="<?php echo $colKey; ?>" onchange="updateColumns(this)"> 
                                            <?php echo htmlspecialchars($f['name']); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="headerActions" class="flex gap-3">
        <button onclick="toggleActivityPanel()" class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Activity
        </button>
        <button onclick="createNewFolder()" class="flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path></svg>
            New Folder
        </button>
        <button onclick="toggleUploadModal()" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            Upload Document
        </button>
    </div>
</div>

<div id="recentDocumentsWidget" class="hidden mb-6 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Recently Viewed
    </h3>
    <div id="recentDocumentsGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- Cards injected here -->
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col flex-1 min-h-0"
     ondragover="if(typeof isRecycleBinMode !== 'undefined' && !isRecycleBinMode) handleDragOver(event, true)"
     ondragleave="if(typeof isRecycleBinMode !== 'undefined' && !isRecycleBinMode) handleDragLeave(event, true)"
     ondrop="if(typeof isRecycleBinMode !== 'undefined' && !isRecycleBinMode) handleDrop(event, 'current')">
    <div id="folderGrid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 overflow-y-auto flex-1 min-h-0">
        </div>
    <div id="paginationControls" class="mt-6 flex justify-center gap-2 hidden"></div>
    <div id="emptyState" class="hidden text-center py-12">
        <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
        <h3 class="mt-2 text-sm font-medium text-slate-900">No folders here</h3>
        <p class="mt-1 text-sm text-slate-500">Get started by creating a new folder.</p>
    </div>
</div>

</div>
</div>

<div id="mainTemplatesView" class="hidden p-8 flex-1 overflow-y-auto bg-slate-50">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Document Templates</h2>
        <button onclick="openTemplateEditor()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">
            Create Template
        </button>
    </div>
    
    <div id="templatesListContainer" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-200 text-sm font-medium text-slate-500 bg-slate-50">
                        <th class="py-3 px-4">Template Name</th>
                        <th class="py-3 px-4">Description</th>
                        <th class="py-3 px-4 text-center">Fields</th>
                        <th class="py-3 px-4 w-24"></th>
                    </tr>
                </thead>
                <tbody id="templatesListBody" class="text-sm">
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Loading templates...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Template Editor Form -->
    <div id="templateEditorContainer" class="hidden bg-white rounded-xl shadow-sm border border-slate-200 p-6 max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6 border-b border-slate-200 pb-4">
            <h3 id="templateEditorTitle" class="text-lg font-bold text-slate-800">Create New Template</h3>
            <button onclick="closeTemplateEditor()" class="text-slate-400 hover:text-slate-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-8">
            <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">Template Name <span class="text-red-500">*</span></label>
                <input type="text" id="tplName" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. Invoice, Contract">
            </div>
            <div class="col-span-2 md:col-span-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <input type="text" id="tplDesc" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Optional description...">
            </div>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <h4 class="font-bold text-slate-800">Template Fields</h4>
            <div class="flex gap-2">
                <select id="newFieldType" class="border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="text">Text (String)</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="dropdown">Dropdown</option>
                </select>
                <button onclick="addField()" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200 transition-colors border border-slate-200">
                    Add Field
                </button>
            </div>
        </div>

        <div id="fieldsContainer" class="space-y-3 mb-8">
            <!-- Fields will be dynamically injected here -->
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
            <button onclick="closeTemplateEditor()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
            <button onclick="saveTemplate()" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm">Save Template</button>
        </div>
    </div>
</div>

            </div>
        </div>
    <div id="mainSearchView" class="hidden p-8 flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-50">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Advanced Search</h2>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Search By</label>
                    <select id="searchType" onchange="onSearchTypeChange()" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="name">Name (Doc/Folder)</option>
                        <option value="added_by">Added By</option>
                        <option value="modified_by">Last Modified By</option>
                        <option value="deleted_by" class="recycle-bin-only hidden">Deleted By</option>
                        <option value="deleted_at" class="recycle-bin-only hidden">Date Deleted</option>
                        <option value="template">Template Metadata</option>
                    </select>
                </div>
                
                <div id="searchNameContainer" class="col-span-1 md:col-span-2">
                    <label id="searchNameLabel" class="block text-sm font-medium text-slate-700 mb-1">Name (Doc/Folder)</label>
                    <input type="text" id="searchNameInput" onkeypress="if(event.key === 'Enter') executeSearch()" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Enter name to search...">
                </div>

                <div id="searchDateContainer" class="hidden col-span-1 md:col-span-2">
                    <label id="searchDateLabel" class="block text-sm font-medium text-slate-700 mb-1">Select Date</label>
                    <input type="date" id="searchDateInput" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div id="searchTemplateContainer" class="hidden col-span-1 md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Select Template</label>
                    <select id="searchTemplateSelect" onchange="onSearchTemplateChange()" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">-- Choose a Template --</option>
                        <?php foreach($allTemplates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>"><?php echo htmlspecialchars($tpl['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="searchDynamicFieldsContainer" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 pt-4 border-t border-slate-200">
                <!-- Dynamic fields injected here -->
            </div>

            <div class="flex items-start justify-between pt-4 border-t border-slate-200">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="searchRecycleBin" onchange="toggleRecycleBinSearchOptions()" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <label for="searchRecycleBin" class="text-sm font-medium text-slate-700 cursor-pointer">Search in Recycle Bin</label>
                    </div>
                </div>
                <button onclick="executeSearch()" class="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm ml-auto self-end">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Search
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col flex-1 min-h-0">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-800">Search Results</h3>
                <div class="relative flex items-center">
                    <button onclick="toggleSearchColumnMenu(event)" class="flex items-center gap-1 p-1.5 rounded-md text-slate-500 hover:text-slate-700 transition-all text-sm font-medium" title="Columns">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    </button>
                    <div id="searchColumnMenu" class="hidden absolute top-8 right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-slate-200 py-1 z-50">
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="type" onchange="updateSearchColumns(this)" checked disabled> Type
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="name" onchange="updateSearchColumns(this)" checked disabled> Name
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" id="search-col-cb-location" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="location" onchange="updateSearchColumns(this)"> Location (Path)
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" id="search-col-cb-size" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="size" onchange="updateSearchColumns(this)"> Size
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" id="search-col-cb-added" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="added" onchange="updateSearchColumns(this)"> Added
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" id="search-col-cb-added-by" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="added_by" onchange="updateSearchColumns(this)"> Added By
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" id="search-col-cb-modified-by" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="modified_by" onchange="updateSearchColumns(this)"> Last Modified By
                        </label>
                        <label class="flex items-center px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm text-slate-700">
                            <input type="checkbox" id="search-col-cb-modified-at" class="mr-2 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="modified_at" onchange="updateSearchColumns(this)"> Last Modified At
                        </label>
                    </div>
                </div>
            </div>
            <div id="searchResultsGrid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 overflow-y-auto flex-1 min-h-0">
                <p class="text-slate-400 text-sm col-span-full">No search performed yet.</p>
            </div>
            <div id="searchPaginationControls" class="mt-6 flex justify-center gap-2 hidden"></div>
        </div>
    </div>

    <div id="mainUsersView" class="hidden p-8 flex-1 overflow-y-auto bg-slate-50">
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">User Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage system users, roles, and access credentials.</p>
                </div>
                <button onclick="openUserModal()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    New User
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-500">
                            <th class="py-3 px-4">Username</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Created Date</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersGridBody" class="text-sm text-slate-700 divide-y divide-slate-100">
                        <tr><td colspan="4" class="py-4 text-center text-slate-500">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="max-w-6xl mx-auto space-y-6 mt-12">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">Group Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Manage user groups for easier permission assignment.</p>
                </div>
                <button onclick="openGroupModal()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    New Group
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-500">
                            <th class="py-3 px-4">Group Name</th>
                            <th class="py-3 px-4">Members</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="groupsGridBody" class="text-sm text-slate-700 divide-y divide-slate-100">
                        <tr><td colspan="3" class="py-4 text-center text-slate-500">Loading groups...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="mainSettingsView" class="hidden p-8 flex-1 overflow-y-auto bg-slate-50">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-3xl font-bold text-slate-800 mb-2">System Settings</h2>
            <p class="text-slate-500 mb-8">Manage application configuration and branding.</p>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
                <h3 class="text-xl font-bold text-slate-800 mb-4">Security</h3>
                <div class="space-y-4 max-w-md">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Minimum Password Length</label>
                        <input type="number" id="settingMinPasswordLength" value="<?php echo $minPasswordLength; ?>" min="1" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-1">Applies to new passwords and password changes.</p>
                    </div>
                    <button onclick="saveSecuritySettings()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm shadow-sm">
                        Save Security Settings
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-xl font-bold text-slate-800 mb-4">Branding</h3>
                <div class="space-y-4 max-w-md">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Custom Logo</label>
                        <div class="mt-2 flex items-center gap-4">
                            <div class="h-16 w-48 bg-slate-100 rounded border border-slate-200 flex items-center justify-center overflow-hidden p-2">
                                <?php if ($logoPath): ?>
                                    <img src="<?php echo htmlspecialchars($logoPath); ?>?v=<?php echo time(); ?>" alt="Current Logo" class="max-h-full max-w-full object-contain">
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">No Logo</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" id="settingLogoFile" accept="image/*" class="text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        </div>
                    </div>
                    <button onclick="saveBrandingSettings()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm shadow-sm">
                        Upload & Save Logo
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    </main>


    <!-- Begin MODAL -->

<!-- User Modal -->
<div id="userModal" class="hidden fixed inset-0 z-[11000] bg-[#0f172a]/50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 id="userModalTitle" class="text-lg font-bold text-slate-800 mb-4">Create User</h3>
        <input type="hidden" id="userId">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input type="text" id="userUsername" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select id="userRole" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="user">User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" id="userPassword" placeholder="Leave blank to keep unchanged" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 mt-6">
            <button onclick="document.getElementById('userModal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
            <button onclick="saveUser()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">Save User</button>
        </div>
    </div>
</div>

<!-- Group Modal -->
<div id="groupModal" class="hidden fixed inset-0 z-[11000] bg-[#0f172a]/50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl flex overflow-hidden h-[32rem]">
        <!-- Left side: Group Details -->
        <div class="w-1/3 p-6 border-r border-slate-200 bg-slate-50">
            <h3 id="groupModalTitle" class="text-lg font-bold text-slate-800 mb-4">Create Group</h3>
            <input type="hidden" id="groupId">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Group Name</label>
                    <input type="text" id="groupName" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="mt-8 flex flex-col gap-3">
                <button onclick="saveGroup()" class="w-full py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">Save Group</button>
                <button onclick="document.getElementById('groupModal').classList.add('hidden')" class="w-full py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">Close</button>
            </div>
        </div>
        
        <!-- Right side: Users Management (Only visible when editing) -->
        <div id="groupUsersPanel" class="w-2/3 flex flex-col hidden opacity-50 pointer-events-none">
            <div class="p-4 border-b border-slate-200 bg-white">
                <h4 class="font-semibold text-slate-800">Manage Members</h4>
                <p class="text-xs text-slate-500">Click a user to add or remove them from the group.</p>
            </div>
            <div class="flex flex-1 overflow-hidden">
                <div class="w-1/2 flex flex-col border-r border-slate-200 bg-white">
                    <div class="p-2 bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase flex flex-col gap-2">
                        <span>Available Users</span>
                        <input type="text" id="filterAvailable" onkeyup="filterUsers('groupUsersOut', this.value)" placeholder="Search..." class="w-full border border-slate-300 rounded px-2 py-1 text-xs font-normal focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div id="groupUsersOut" class="flex-1 overflow-y-auto p-2 space-y-1"></div>
                </div>
                <div class="w-1/2 flex flex-col bg-white">
                    <div class="p-2 bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase flex flex-col gap-2">
                        <span>In Group</span>
                        <input type="text" id="filterInGroup" onkeyup="filterUsers('groupUsersIn', this.value)" placeholder="Search..." class="w-full border border-slate-300 rounded px-2 py-1 text-xs font-normal focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div id="groupUsersIn" class="flex-1 overflow-y-auto p-2 space-y-1"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="restoreModal" class="hidden fixed inset-0 z-[11000] bg-[#0f172a]/50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Restore Item</h3>
        <p class="text-sm text-slate-600 mb-4">Select the destination folder where you want to restore this item.</p>
        <div class="mb-6">
            <select id="restoreDestination" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="null">Home (Root Directory)</option>
            </select>
        </div>
        <div class="flex items-center justify-end gap-3">
            <button onclick="closeRestoreModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
            <button id="btnConfirmRestore" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors shadow-sm">Confirm Restore</button>
        </div>
    </div>
</div>

<div id="activityPanel" class="fixed inset-y-0 right-0 w-80 bg-white shadow-2xl border-l border-slate-200 transform translate-x-full transition-transform duration-300 ease-in-out z-[9000] flex flex-col">
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-200 bg-slate-50 shrink-0">
        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Recent Activity
        </h3>
        <button onclick="toggleActivityPanel()" class="text-slate-400 hover:text-slate-700 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <div id="activityFeed" class="flex-1 overflow-y-auto p-6 space-y-6">
        <p class="text-sm text-slate-500 text-center">Loading...</p>
    </div>
</div>

    <div id="uploadModal" class="hidden fixed inset-0 bg-[#0f172a]/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Upload Document</h3>
        <form id="uploadForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Select PDF File</label>
                <input type="file" id="fileInput" accept=".pdf" required class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>
            <div id="uploadStatus" class="hidden text-sm font-medium"></div>
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="toggleUploadModal()" class="px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">Upload securely</button>
            </div>
        </form>
    </div>
</div>



<div id="viewerModal" class="hidden fixed inset-0 bg-[#0f172a] flex flex-col z-[100] overflow-hidden">
    
    <div class="h-10 bg-[#0f172a] text-slate-300 flex items-center justify-between px-4 text-xs font-medium tracking-wide shrink-0">
        <div class="flex items-center gap-4">
            <span class="text-white font-bold flex items-center gap-2 pr-4 border-r border-slate-700">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                StrataDMS Workspace
            </span>
            <div class="relative group">
                <button class="hover:text-white px-2 py-1 rounded hover:bg-[#1e293b] transition-colors">File</button>
                <div class="absolute left-0 top-full w-48 bg-white rounded-md shadow-lg py-1 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-opacity z-50">
                    <a href="#" id="menuPrintBtn" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Print</a>
                    <a href="#" id="menuExportBtn" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Export PDF (with markup)</a>
                    <a href="#" id="menuDownloadBtn" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Download Original</a>
                </div>
            </div>
            <div class="relative group">
                <button class="hover:text-white px-2 py-1 rounded hover:bg-[#1e293b] transition-colors">View</button>
                <div class="absolute left-0 top-full w-40 bg-white rounded-md shadow-lg py-1 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-opacity z-50">
                    <a href="#" onclick="zoomIn(event)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Zoom In</a>
                    <a href="#" onclick="zoomOut(event)" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Zoom Out</a>
                </div>
            </div>
            <div class="relative group">
                <button class="hover:text-white px-2 py-1 rounded hover:bg-[#1e293b] transition-colors">Tools</button>
                <div class="absolute left-0 top-full w-40 bg-white rounded-md shadow-lg py-1 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-opacity z-50">
                    <a href="#" onclick="openStampsModal(); event.preventDefault();" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Stamp</a>
                    <a href="#" onclick="openMarkupModal(); event.preventDefault();" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Markup</a>
                    <a href="#" onclick="openWatermarkModal(); event.preventDefault();" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Watermark</a>
                </div>
            </div>
        </div>
        <button onclick="closeViewer()" class="flex items-center gap-1 text-slate-400 hover:text-red-400 px-2 py-1 transition-colors">
            Close Workspace <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <div class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4 shadow-sm shrink-0">
        <div class="flex items-center gap-3 w-1/3 min-w-0">
            <button onclick="toggleThumbnails()" class="p-1.5 shrink-0 text-slate-500 hover:bg-slate-100 rounded transition-colors" title="Toggle Thumbnails">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"></path></svg>
            </button>
            <div class="flex flex-col min-w-0 w-full">
                <div class="flex items-center gap-2 group/title relative">
                    <h3 id="viewerTitle" class="text-sm font-bold text-slate-800 truncate">Document Title</h3>
                    <button id="viewerRenameBtn" onclick="enableInlineRename()" class="opacity-0 group-hover/title:opacity-100 text-slate-400 hover:text-blue-600 transition-opacity p-0.5" title="Rename Document">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </button>
                    
                    <div id="viewerRenameContainer" class="hidden absolute left-0 top-1/2 -translate-y-1/2 items-center gap-1 bg-white z-10 pr-2">
                        <input type="text" id="viewerRenameInput" class="text-sm font-bold text-slate-800 px-1.5 py-0.5 border border-blue-400 rounded outline-none focus:ring-2 focus:ring-blue-100 w-64" onkeydown="if(event.key === 'Enter') saveInlineRename()">
                        <button onclick="saveInlineRename()" class="p-1 text-green-600 hover:bg-green-50 rounded bg-white shadow-sm border border-slate-200" title="Save">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                        <button onclick="cancelInlineRename()" class="p-1 text-slate-400 hover:bg-slate-50 rounded bg-white shadow-sm border border-slate-200" title="Cancel">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div id="viewerCheckoutStatus" class="flex-shrink-0 flex items-center ml-auto"></div>
                    <div id="viewerCheckoutAction" class="flex-shrink-0 ml-1 flex items-center"></div>
                </div>
                <span id="viewerPath" class="text-xs font-medium text-slate-400 truncate"></span>
            </div>
        </div>

        <div class="flex items-center justify-center gap-1 w-1/3">
            <div class="flex items-center bg-slate-100 p-1 rounded-lg border border-slate-200">
                <button onclick="openStampsModal()" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-white hover:shadow-sm rounded transition-all" title="Add Stamp">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                </button>
                <button onclick="openWatermarkModal()" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-white hover:shadow-sm rounded transition-all" title="Watermark">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                </button>
                <button id="ws-btn-markup" onclick="openMarkupModal()" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-white hover:shadow-sm rounded transition-all" title="Markup">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </button>
                
                <div class="w-px h-5 bg-slate-300 mx-2"></div>
                
                <button id="ws-btn-import" onclick="document.getElementById('importPdfInput').click()" class="p-1.5 text-slate-500 hover:text-green-600 hover:bg-white hover:shadow-sm rounded transition-all" title="Import PDF">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </button>
                <input type="file" id="importPdfInput" accept=".pdf" class="hidden" onchange="handleImportPDF(event)">
                <button id="ws-btn-delete-page" onclick="deleteCurrentPage()" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-white hover:shadow-sm rounded transition-all" title="Delete Page">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 w-1/3">
            <a id="printBtn" href="#" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded hover:bg-slate-200 transition-colors border border-slate-200 flex items-center gap-1" title="Print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            </a>
            <a id="exportStampedBtn" href="#" class="px-3 py-1.5 bg-blue-50 text-blue-700 text-xs font-bold rounded hover:bg-blue-100 transition-colors border border-blue-200 flex items-center gap-1 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export PDF
            </a>
            <a id="downloadBtn" href="#" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded hover:bg-slate-200 transition-colors border border-slate-200 flex items-center gap-1" title="Download Original">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </a>
            <button onclick="toggleSidebar()" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-2">
                Metadata <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
            </button>
        </div>
    </div>

    <div class="flex-1 flex overflow-hidden bg-slate-300">
        <div id="thumbnailSidebar" class="w-48 bg-slate-200 border-r border-slate-300 flex flex-col shrink-0 overflow-y-auto p-3 space-y-4 shadow-inner transition-transform duration-300 transform translate-x-0">
            </div>
        <div class="flex-1 h-full shadow-inner relative flex flex-col bg-slate-400 overflow-hidden">
            
            <div class="h-10 bg-slate-700 flex items-center justify-between px-4 text-slate-200 text-sm shadow-md shrink-0 z-10">
                <div class="flex items-center gap-2 w-1/3">
                    <button onclick="zoomOut(event)" class="p-1 hover:bg-slate-600 rounded transition-colors" title="Zoom Out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"></path></svg>
                    </button>
                    <span id="zoomLevel" class="text-xs font-semibold w-10 text-center">150%</span>
                    <button onclick="zoomIn(event)" class="p-1 hover:bg-slate-600 rounded transition-colors" title="Zoom In">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
                    </button>
                </div>
                <div class="flex items-center justify-center gap-4 w-1/3">
                    <button id="prevPage" class="px-2 py-1 hover:bg-slate-600 rounded disabled:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </button>
                    <span>Page <span id="pageNum" class="font-bold">0</span> of <span id="pageCount" class="font-bold">0</span></span>
                    <button id="nextPage" class="px-2 py-1 hover:bg-slate-600 rounded disabled:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
                <div class="w-1/3"></div>
            </div>

            <div class="flex-1 overflow-auto flex justify-center p-4 bg-slate-400 relative" id="canvasContainer">
                <div class="relative shrink-0" id="pdfWrapper">
                    <canvas id="pdfCanvas" class="shadow-xl bg-white block mx-auto max-w-none"></canvas>
                    <div id="stampsLayer" class="absolute top-0 left-0 pointer-events-none overflow-hidden" style="width: 100%; height: 100%;"></div>
                </div>
            </div>
        </div>

        <div id="metaSidebar" class="w-80 bg-white border-l border-slate-200 flex flex-col shrink-0 shadow-2xl transition-transform duration-300 transform translate-x-0">
            <div class="flex border-b border-slate-200 bg-slate-50">
                <button onclick="switchSidebarTab('metadata')" id="tabBtn-metadata" class="flex-1 py-3 text-sm font-bold text-blue-600 border-b-2 border-blue-600 focus:outline-none transition-colors">Properties</button>
                <button onclick="switchSidebarTab('versions')" id="tabBtn-versions" class="flex-1 py-3 text-sm font-bold text-slate-500 border-b-2 border-transparent hover:text-slate-700 focus:outline-none transition-colors">History</button>
            </div>
            
            <!-- Metadata Panel -->
            <div id="panel-metadata" class="flex flex-col flex-1 overflow-hidden">
                <div class="p-4 flex-1 overflow-y-auto">
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Applied Template</label>
                        <select id="docTemplateSelect" onchange="handleTemplateChange(this.value)" class="w-full text-sm border border-slate-300 rounded-md p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white">
                            <option value="">-- No Template Assigned --</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Metadata Fields</label>
                        <div id="docMetadataFields" class="space-y-4">
                            <p class="text-sm text-slate-400 italic">Select a template to view fields.</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 border-t border-slate-200 bg-white">
                    <button id="ws-btn-save-meta" onclick="saveDocumentMetadata()" class="w-full py-2 bg-[#1e293b] text-white text-sm font-medium rounded-lg hover:bg-[#0f172a] transition-colors shadow-sm">
                        Save Metadata
                    </button>
                </div>
            </div>

            <!-- Versions Panel -->
            <div id="panel-versions" class="hidden flex-col flex-1 overflow-hidden bg-slate-50">
                <div class="p-4 flex-1 overflow-y-auto" id="versionTimeline">
                    <p class="text-sm text-slate-400 italic">Loading versions...</p>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Merge Modal -->
<div id="stampsModal" class="hidden fixed inset-0 bg-[#0f172a]/50 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-semibold text-slate-800">Manage Stamps</h3>
            <button onclick="document.getElementById('stampsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="flex-1 flex overflow-hidden max-h-[60vh]">
            <div class="w-1/3 border-r border-slate-200 bg-slate-50 overflow-y-auto" id="stampsList"></div>
            <div class="w-2/3 p-6 overflow-y-auto">
                <h4 id="stampFormTitle" class="text-sm font-bold text-slate-700 mb-4">Create New Stamp</h4>
                <input type="hidden" id="stampId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Stamp Name</label>
                        <input type="text" id="stampName" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Text</label>
                        <input type="text" id="stampText" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Font</label>
                            <select id="stampFont" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                                <option value="Helvetica">Helvetica</option>
                                <option value="Helvetica-Bold">Helvetica Bold</option>
                                <option value="Courier">Courier</option>
                                <option value="Times-Roman">Times Roman</option>
                            </select>
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Size</label>
                            <input type="number" id="stampSize" value="36" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Color</label>
                            <input type="color" id="stampColor" value="#FF0000" class="w-full h-9 rounded border-slate-300 shadow-sm border p-0.5 cursor-pointer">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-4">
                        <button onclick="clearStampForm()" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded hover:bg-slate-200">Clear</button>
                        <button onclick="saveStamp()" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 shadow-sm">Save Stamp</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Markup Modal -->
<div id="markupModal" class="hidden fixed inset-0 bg-[#0f172a]/50 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-semibold text-slate-800">Add Markup</h3>
            <button onclick="document.getElementById('markupModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Type</label>
                <select id="markupType" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    <option value="redact">Redaction (Solid)</option>
                    <option value="highlight">Highlight (Transparent)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Color</label>
                <input type="color" id="markupColor" value="#000000" class="w-full h-9 rounded border-slate-300 shadow-sm border p-0.5 cursor-pointer">
            </div>
            <div class="pt-4">
                <button onclick="applyAnnotation(event)" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 shadow-sm">Apply to Selected Pages</button>
            </div>
        </div>
    </div>
</div>

<!-- Watermark Modal -->
<div id="watermarkModal" class="hidden fixed inset-0 bg-[#0f172a]/50 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-semibold text-slate-800">Watermark Properties</h3>
            <button onclick="document.getElementById('watermarkModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 overflow-y-auto" style="max-height: 80vh;">
            <div class="space-y-4">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Watermark Type</label>
                    <select id="wmType" onchange="toggleWmType()" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                        <option value="text">Text</option>
                        <option value="image">Image (PNG)</option>
                    </select>
                </div>

                <div id="wmTextInput">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Watermark text:</label>
                    <input type="text" id="wmText" placeholder="Laserfiche Reproduction %(Id)" class="w-full rounded border-slate-300 shadow-sm text-sm p-2 border focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Supports %(Id), %(Date), %(User)</p>
                </div>
                
                <div id="wmImageInput" class="hidden">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Watermark Image:</label>
                    
                    <div id="wmImageActive" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded flex items-center justify-between">
                        <div class="flex items-center gap-2 text-green-700 overflow-hidden">
                            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="text-sm font-semibold truncate" id="wmImageFilenameText">image.png</span>
                        </div>
                        <button type="button" onclick="document.getElementById('wmImageUploadGroup').classList.remove('hidden'); this.classList.add('hidden')" class="text-xs text-blue-600 hover:text-blue-800 font-medium ml-2 flex-shrink-0">Change File</button>
                    </div>

                    <div id="wmImageUploadGroup">
                        <input type="file" id="wmImage" accept="image/png" class="w-full text-sm p-1.5 border border-slate-300 rounded shadow-sm bg-white">
                        <p class="text-xs text-slate-500 mt-1">For best results, upload a PNG with a transparent background.</p>
                    </div>
                    
                    <input type="hidden" id="wmExistingImage">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="border rounded p-3 bg-slate-50">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Position</label>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-slate-700">Horizontal:</span>
                            <select id="wmHPos" class="w-32 rounded border-slate-300 shadow-sm text-sm p-1 border">
                                <option value="left">Left</option>
                                <option value="center" selected>Center</option>
                                <option value="right">Right</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-slate-700">Vertical:</span>
                            <select id="wmVPos" class="w-32 rounded border-slate-300 shadow-sm text-sm p-1 border">
                                <option value="top">Top</option>
                                <option value="center" selected>Center</option>
                                <option value="bottom">Bottom</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-slate-700">X Offset:</span>
                            <div class="flex items-center gap-1">
                                <input type="number" id="wmOffsetX" value="0" class="w-20 rounded border-slate-300 shadow-sm text-sm p-1 border">
                                <span class="text-xs text-slate-500">%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700">Y Offset:</span>
                            <div class="flex items-center gap-1">
                                <input type="number" id="wmOffsetY" value="0" class="w-20 rounded border-slate-300 shadow-sm text-sm p-1 border">
                                <span class="text-xs text-slate-500">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border rounded p-3 bg-slate-50">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Rotation</label>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700">Angle:</span>
                            <div class="flex items-center gap-2">
                                <input type="number" id="wmRotation" value="45" class="w-20 rounded border-slate-300 shadow-sm text-sm p-1 border">
                                <span class="text-sm text-slate-500">degrees</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="border rounded p-3 bg-slate-50">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Size</label>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-700">Percent:</span>
                            <input type="number" id="wmSize" value="50" min="1" max="100" class="w-20 rounded border-slate-300 shadow-sm text-sm p-1 border">
                        </div>
                    </div>
                    
                    <div class="border rounded p-3 bg-slate-50">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Opacity</label>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-slate-700">Darkness:</span>
                            <input type="number" id="wmOpacity" value="20" min="1" max="100" class="w-20 rounded border-slate-300 shadow-sm text-sm p-1 border">
                        </div>
                        <span class="text-xs text-slate-500">From 1 (light) to 100 (dark)</span>
                    </div>
                </div>
                
            </div>
            <div class="pt-6 flex justify-end gap-2">
                <button onclick="deleteWatermark()" class="px-4 py-2 bg-red-100 text-red-700 text-sm font-medium rounded hover:bg-red-200">Remove</button>
                <button onclick="document.getElementById('watermarkModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm font-medium rounded hover:bg-slate-200">Cancel</button>
                <button onclick="saveWatermark()" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 shadow-sm">Apply & Save</button>
            </div>
        </div>
    </div>
</div>

<div id="mergeModal" class="hidden fixed inset-0 bg-[#0f172a]/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-2">Merge Documents</h3>
            <p class="text-sm text-slate-600 mb-6">
                Where would you like to place <strong id="mergeSrcName" class="text-slate-800"></strong> within <strong id="mergeTgtName" class="text-slate-800"></strong>?
            </p>
            <div class="flex gap-3">
                <button onclick="executeMerge('beginning')" class="flex-1 py-2 bg-blue-50 text-blue-700 font-semibold rounded-lg hover:bg-blue-100 transition-colors border border-blue-200">
                    Beginning
                </button>
                <button onclick="executeMerge('end')" class="flex-1 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                    End
                </button>
            </div>
            <div class="mt-4 text-center">
                <button onclick="closeMergeModal()" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- END MODAL -->






<script src="/assets/js/vendor/pdf.min.js"></script>
<script>
    // Set the worker path (required by PDF.js)
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/assets/js/vendor/pdf.worker.min.js';
</script>
    <script>
    let currentFolderId = null;
    let currentView = 'detail';
    let isRecycleBinMode = false;
    let rbSortCol = 'deleted_at';
    let rbSortDir = 'desc';
    let currentDocumentId = null;
    let currentDocTemplateId = null;
    let currentPage = 1;

    window.selectedItems = new Set();

    function handleRowClick(event, tr) {
        if (event.target.tagName.toLowerCase() === 'input' && event.target.type === 'checkbox') {
            return;
        }
        

        
        const table = tr.closest('table') || document;
        const allRows = table.querySelectorAll('tr.bg-blue-50');
        allRows.forEach(row => {
            if (row !== tr) {
                const cb = row.querySelector('.row-checkbox');
                if (!cb || !cb.checked) {
                    row.classList.remove('bg-blue-50');
                }
            }
        });
        
        if (tr.classList.contains('bg-blue-50')) {
            const cb = tr.querySelector('.row-checkbox');
            if (!cb || !cb.checked) {
                tr.classList.remove('bg-blue-50');
            }
        } else {
            tr.classList.add('bg-blue-50');
        }
    }

    function toggleSelection(checkbox, type, id) {
        if (checkbox.checked) {
            window.selectedItems.add(`${type}-${id}`);
            checkbox.closest('tr').classList.add('bg-blue-50');
        } else {
            window.selectedItems.delete(`${type}-${id}`);
            checkbox.closest('tr').classList.remove('bg-blue-50');
        }
        updateBulkActionBar();
    }
    function toggleAllSelection(masterCheckbox) {
        const checkboxes = masterCheckbox.closest('table').querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = masterCheckbox.checked;
            if (masterCheckbox.checked) {
                window.selectedItems.add(`${cb.getAttribute('onchange').includes("'folder'") ? 'folder' : 'document'}-${cb.value}`);
                cb.closest('tr').classList.add('bg-blue-50');
            } else {
                window.selectedItems.delete(`${cb.getAttribute('onchange').includes("'folder'") ? 'folder' : 'document'}-${cb.value}`);
                cb.closest('tr').classList.remove('bg-blue-50');
            }
        });
        updateBulkActionBar();
    }

    function renderPagination(totalPages, current, containerId, actionFnStr) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        if (totalPages <= 1) {
            container.innerHTML = '';
            container.classList.add('hidden');
            return;
        }
        
        container.classList.remove('hidden');
        let html = '';
        
        html += `<button onclick="currentPage = ${current > 1 ? current - 1 : 1}; ${actionFnStr}" class="px-3 py-1 border rounded text-sm ${current === 1 ? 'text-slate-300 border-slate-200 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50 border-slate-300'}" ${current === 1 ? 'disabled' : ''}>Prev</button>`;
        
        html += `<span class="px-3 py-1 text-sm text-slate-600 flex items-center">Page ${current} of ${totalPages}</span>`;
        
        html += `<button onclick="currentPage = ${current < totalPages ? current + 1 : totalPages}; ${actionFnStr}" class="px-3 py-1 border rounded text-sm ${current === totalPages ? 'text-slate-300 border-slate-200 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50 border-slate-300'}" ${current === totalPages ? 'disabled' : ''}>Next</button>`;
        
        container.innerHTML = html;
    }

    function clearBulkSelection() {
        window.selectedItems.clear();
        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('tr').classList.remove('bg-blue-50');
        });
        const master = document.querySelector('thead input[type="checkbox"]');
        if (master) master.checked = false;
        updateBulkActionBar();
    }

    function updateBulkActionBar() {
        const bar = document.getElementById('bulkActionBar');
        const countSpan = document.getElementById('bulkActionCount');
        const btnContainer = document.getElementById('bulkActionButtons');
        
        const isRecycleBin = currentAppView === 'recyclebin' || (currentAppView === 'search' && document.getElementById('searchRecycleBin') && document.getElementById('searchRecycleBin').checked);

        if (window.selectedItems.size > 0) {
            countSpan.textContent = `${window.selectedItems.size} item${window.selectedItems.size > 1 ? 's' : ''} selected`;
            
            if (isRecycleBin) {
                const deleteBtn = window.currentUserRole === 'admin'
                    ? `<button onclick="executeBulkPermanentDelete()" class="flex items-center gap-2 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded text-sm font-medium transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>Delete Permanently</button>`
                    : '';
                btnContainer.innerHTML = `<button onclick="executeBulkAction('restore')" class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>Restore</button>${deleteBtn}`;
            } else {
                btnContainer.innerHTML = `
                    <button onclick="promptBulkMove()" class="flex items-center gap-2 px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-sm font-medium transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>Move</button>
                    <button onclick="executeBulkAction('delete')" class="flex items-center gap-2 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded text-sm font-medium transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>Delete</button>
                `;
            }
            
            bar.classList.remove('hidden');
            // small delay to allow display block to apply before transition
            setTimeout(() => {
                bar.classList.remove('translate-y-24', 'opacity-0');
            }, 10);
        } else {
            bar.classList.add('translate-y-24', 'opacity-0');
            setTimeout(() => {
                if (window.selectedItems.size === 0) bar.classList.add('hidden');
            }, 300);
        }
    }

    async function executeBulkAction(action, targetFolderId = null) {
        if (action === 'delete' && !confirm(`Are you sure you want to delete ${window.selectedItems.size} item(s)?`)) return;
        if (action === 'restore' && !confirm(`Restore ${window.selectedItems.size} item(s) to their original locations?`)) return;

        const items = Array.from(window.selectedItems).map(str => {
            const [type, id] = str.split('-');
            return { type, id: parseInt(id) };
        });

        try {
            const res = await fetch('/api/bulk_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, items, target_folder_id: targetFolderId })
            });
            const data = await res.json();
            if (data.success) {
                clearBulkSelection();
                if (action === 'move') closeModal('moveModal');
                
                // Refresh views
                if (currentAppView === 'files') {
                    loadFolder(currentFolderId);
                    loadSidebarTree();
                } else if (currentAppView === 'recyclebin') {
                    loadRecycleBin();
                } else if (currentAppView === 'search') {
                    executeSearch(false);
                }
            } else {
                alert(data.message || `Bulk ${action} failed.`);
            }
        } catch (e) {
            alert(`Network error during bulk ${action}.`);
        }
    }
    
    function promptBulkMove() {
        currentMoveTarget = Array.from(window.selectedItems).map(str => {
            const [type, id] = str.split('-');
            return { type, id: parseInt(id) };
        });
        openMoveModal();
    }

let expandedTreeFolders = new Set(['home']);

let navHistory = [null];
let navIndex = 0;
let isHistoryNav = false;

function goBack() {
    if (navIndex > 0) {
        navIndex--;
        isHistoryNav = true;
        loadFolder(navHistory[navIndex]);
    }
}

function goForward() {
    if (navIndex < navHistory.length - 1) {
        navIndex++;
        isHistoryNav = true;
        loadFolder(navHistory[navIndex]);
    }
}

function scrollBreadcrumb(amount) {
    const el = document.getElementById('breadcrumb');
    if (el) el.scrollBy({ left: amount, behavior: 'smooth' });
}

function updateBreadcrumbScrollButtons() {
    const el = document.getElementById('breadcrumb');
    const btnLeft = document.getElementById('breadcrumbScrollLeft');
    const btnRight = document.getElementById('breadcrumbScrollRight');
    if (!el || !btnLeft || !btnRight) return;
    
    // Check if scrollable
    if (el.scrollWidth > el.clientWidth) {
        if (el.scrollLeft > 0) {
            btnLeft.classList.remove('hidden');
        } else {
            btnLeft.classList.add('hidden');
        }
        
        if (Math.ceil(el.scrollLeft + el.clientWidth) >= el.scrollWidth) {
            btnRight.classList.add('hidden');
        } else {
            btnRight.classList.remove('hidden');
        }
    } else {
        btnLeft.classList.add('hidden');
        btnRight.classList.add('hidden');
    }
}

let tableColumns = JSON.parse(localStorage.getItem('tableColumns')) || ['type', 'name', 'size', 'added'];
let searchTableColumns = JSON.parse(localStorage.getItem('searchTableColumns')) || ['type', 'name', 'location', 'size', 'added'];
let sortCol = localStorage.getItem('sortCol') || 'name';
let sortDir = localStorage.getItem('sortDir') || 'asc';

let searchSortCol = localStorage.getItem('searchSortCol') || 'name';
let searchSortDir = localStorage.getItem('searchSortDir') || 'asc';
let currentSearchData = null;

// --- PDF.js State Variables ---
let pdfDoc = null;
let pageNum = 1;
let pageIsRendering = false;
let pageNumIsPending = null;
let scale = 1.5; // Adjust for zoom level

function zoomIn(e) {
    if (e) e.preventDefault();
    scale += 0.25;
    document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
    queueRenderPage(pageNum);
}

function zoomOut(e) {
    if (e) e.preventDefault();
    if (scale > 0.5) {
        scale -= 0.25;
        document.getElementById('zoomLevel').textContent = Math.round(scale * 100) + '%';
        queueRenderPage(pageNum);
    }
}

const canvas = document.getElementById('pdfCanvas');
const ctx = canvas ? canvas.getContext('2d') : null;

let renderTask = null;

// --- PDF.js Render Functions ---
function renderPage(num) {
    pageIsRendering = true;
    
    // Fetch page
    pdfDoc.getPage(num).then(page => {
        const viewport = page.getViewport({ scale: scale });
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        // Force canvas CSS to match viewport explicitly
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;
        
        const wrapper = document.getElementById('pdfWrapper');
        if (wrapper) {
            wrapper.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
            wrapper.style.width = `${viewport.width}px`;
            wrapper.style.height = `${viewport.height}px`;
            wrapper.style.maxWidth = 'none';
        }
        
        const stampsLayer = document.getElementById('stampsLayer');
        if (stampsLayer) {
            stampsLayer.style.width = `${viewport.width}px`;
            stampsLayer.style.height = `${viewport.height}px`;
        }

        const renderCtx = {
            canvasContext: ctx,
            viewport: viewport
        };
        
        if (renderTask !== null) {
            renderTask.cancel();
        }

        renderTask = page.render(renderCtx);
        renderTask.promise.then(() => {
            pageIsRendering = false;
            renderTask = null;
            if (pageNumIsPending !== null) {
                renderPage(pageNumIsPending);
                pageNumIsPending = null;
            }
        }).catch(err => {
            // Render cancelled
            if (err.name === 'RenderingCancelledException') {
                // Expected, do nothing
            } else {
                pageIsRendering = false;
                renderTask = null;
            }
        });

        // Update UI
        document.getElementById('pageNum').textContent = num;
        
        // NEW: Update the sidebar to highlight the current page
        syncThumbnailHighlight(num);
        
        // Re-render stamps and annotations for the new scale
        if (typeof renderStamps === 'function') {
            renderStamps(num);
        }
    });
}

function queueRenderPage(num) {
    if (pageIsRendering) {
        pageNumIsPending = num;
    } else {
        renderPage(num);
    }
}

// Pagination Listeners
document.getElementById('prevPage')?.addEventListener('click', () => {
    if (pageNum <= 1) return;
    pageNum--;
    queueRenderPage(pageNum);
});

document.getElementById('nextPage')?.addEventListener('click', () => {
    if (pageNum >= pdfDoc.numPages) return;
    pageNum++;
    queueRenderPage(pageNum);
});



// Add thumbnail generation to openDocument
async function openDocument(filename, title, id = null) {
    if (!filename || filename === 'undefined') return alert("Error: Document filename is missing.");

    currentDocumentId = id;
    if (id) {
        // Attempt automatic checkout
        try {
            await fetch('/api/checkout_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document_id: id })
            });
            // Update grid to reflect lock status
            if (typeof currentFolderId !== 'undefined') loadFolder(currentFolderId, true);
        } catch(e) {
            console.error('Failed to checkout on open:', e);
        }

        loadDocumentMetadata(id);
        loadDocumentVersions(id);
        
        // Track document view asynchronously
        fetch('/api/track_view.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ document_id: id })
        }).catch(err => console.error('Failed to track view:', err));
    }

    document.getElementById('viewerTitle').textContent = title;
    document.getElementById('downloadBtn').href = `/api/view.php?file=${filename}&action=original`;
    document.getElementById('menuDownloadBtn').href = `/api/view.php?file=${filename}&action=original`;
    
    // We will toggle action buttons inside loadDocumentMetadata after fetching true permissions for this doc
    document.getElementById('viewerModal').classList.remove('hidden');

    const url = `/api/view.php?file=${filename}&action=view`;

    pdfjsLib.getDocument(url).promise.then(async pdfDoc_ => {
        pdfDoc = pdfDoc_;
        document.getElementById('pageCount').textContent = pdfDoc.numPages;
        pageNum = 1;
        
        await loadDocumentPlacements(id);
        
        renderPage(pageNum);
        
        // NEW: Generate the sidebar thumbnails
        generateThumbnails(pdfDoc);
        
    }).catch(err => {
        console.error("PDF Load Error: ", err);
        alert("Failed to load PDF data. The file may be corrupted.");
    });
}

// Clear the thumbnails when closing
async function closeViewer() {
    if (currentDocumentId) {
        // Attempt automatic checkin
        try {
            await fetch('/api/checkin_document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document_id: currentDocumentId })
            });
            // Update grid to reflect lock status
            if (typeof currentFolderId !== 'undefined') loadFolder(currentFolderId, true);
        } catch(e) {
            console.error('Failed to checkin on close:', e);
        }
        currentDocumentId = null;
    }

    document.getElementById('viewerModal').classList.add('hidden');
    if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
    pdfDoc = null;
    
    // NEW: Wipe out the sidebar so it's clean for the next document
    document.getElementById('thumbnailSidebar').innerHTML = '';
}

function formatDateTimeWrap(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return '';
    return `<div class="flex flex-col leading-tight"><span>${d.toLocaleDateString()}</span><span class="text-xs text-slate-400">${d.toLocaleTimeString()}</span></div>`;
}

// Format bytes to KB/MB
function formatBytes(bytes, decimals = 2) {
    if (!+bytes) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
}


// --- DRAG AND DROP ENGINE ---
let currentDragGhost = null;

function handleDragStart(event) {
    const isDoc = event.currentTarget.dataset.docId !== undefined;
    const itemId = isDoc ? event.currentTarget.dataset.docId : event.currentTarget.dataset.folderId;
    const itemType = isDoc ? 'document' : 'folder';
    
    // Store the type and ID as JSON string in the drag event data
    event.dataTransfer.setData('text/plain', JSON.stringify({type: itemType, id: itemId}));
    // Make the document look slightly faded while dragging
    event.currentTarget.classList.add('opacity-50');

    // Create a compact custom drag image
    const dragGhost = document.createElement('div');
    dragGhost.className = 'px-3 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg shadow-lg flex items-center gap-2 fixed top-[-1000px] left-[-1000px] z-[9999] whitespace-nowrap';
    
    // Find the document title within the dragged element
    const titleElem = event.currentTarget.querySelector('span[title]') || event.currentTarget.querySelector('td:nth-child(2)');
    const title = titleElem ? titleElem.textContent.trim() : (isDoc ? 'Document' : 'Folder');

    const icon = isDoc 
        ? `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"></path></svg>`
        : `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>`;

    dragGhost.innerHTML = `
        ${icon}
        <span class="max-w-[150px] truncate">${title}</span>
    `;

    document.body.appendChild(dragGhost);
    event.dataTransfer.setDragImage(dragGhost, 15, 15);

    // Save it so we can remove it later
    currentDragGhost = dragGhost;
}

function handleDragEnd(event) {
    event.currentTarget.classList.remove('opacity-50');
    if (currentDragGhost && currentDragGhost.parentNode) {
        currentDragGhost.parentNode.removeChild(currentDragGhost);
        currentDragGhost = null;
    }
}

function handleDragOver(event, isMainContainer = false) {
    // Prevent default to allow dropping
    event.preventDefault(); 
    event.stopPropagation();
    if (isMainContainer) {
        event.currentTarget.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50', 'ring-inset');
    } else {
        event.currentTarget.classList.add('ring-2', 'ring-blue-500', 'bg-blue-100');
    }
}

function handleDragLeave(event, isMainContainer = false) {
    event.stopPropagation();
    if (isMainContainer) {
        event.currentTarget.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50', 'ring-inset');
    } else {
        event.currentTarget.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-100');
    }
}

async function handleDrop(event, targetFolderId) {
    event.preventDefault();
    event.stopPropagation();
    event.currentTarget.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-100', 'bg-blue-50', 'ring-inset');

    if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
        const files = Array.from(event.dataTransfer.files);
        let tFolderId = targetFolderId;
        if (targetFolderId === 'current') tFolderId = currentFolderId;
        if (targetFolderId === 'null') tFolderId = null;

        let successCount = 0;
        let failCount = 0;
        
        for (const file of files) {
            const formData = new FormData();
            formData.append('document', file);
            if (tFolderId) formData.append('folder_id', tFolderId);
            
            try {
                const response = await fetch('/api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    successCount++;
                } else {
                    failCount++;
                    console.error("Upload failed for " + file.name + ": " + data.message);
                }
            } catch (e) {
                failCount++;
                console.error("Network error during upload for " + file.name);
            }
        }
        
        if (failCount > 0) {
            alert(`Uploaded ${successCount} files, but ${failCount} failed. Check console for details.`);
        }
        
        if (currentFolderId == tFolderId || targetFolderId === 'current') {
            loadFolder(currentFolderId);
        }
        if (typeof loadSidebarTree === 'function') loadSidebarTree();
        
        const panel = document.getElementById('activityPanel');
        if (panel && !panel.classList.contains('translate-x-full')) {
            fetchActivityLog();
        }
        return;
    }

    const dragDataStr = event.dataTransfer.getData('text/plain');
    if (!dragDataStr) return;
    
    let dragData;
    try {
        dragData = JSON.parse(dragDataStr);
    } catch(e) {
        // Fallback for old dragging
        dragData = { type: 'document', id: dragDataStr };
    }

    let finalFolderId = targetFolderId;
    if (targetFolderId === 'current') finalFolderId = currentFolderId;
    if (targetFolderId === 'null') finalFolderId = null;
    
    if (dragData.type === 'folder' && dragData.id == finalFolderId) {
        // prevent moving to itself
        return;
    }

    try {
        const isDoc = dragData.type === 'document';
        const url = isDoc ? '/api/move_document.php' : '/api/move_folder.php';
        const payload = isDoc 
            ? { document_id: dragData.id, target_folder_id: finalFolderId } 
            : { folder_id: dragData.id, target_folder_id: finalFolderId };

        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            loadFolder(currentFolderId); // Refresh UI to show file vanished
            if (typeof loadSidebarTree === 'function') loadSidebarTree(); // Refresh sidebar tree
            
            const panel = document.getElementById('activityPanel');
            if (panel && !panel.classList.contains('translate-x-full')) {
                fetchActivityLog();
            }
        } else {
            alert(data.message || 'Failed to move item.');
        }
    } catch (error) {
        alert('Network error while moving the item.');
    }
}

async function openFolderLocation(folderId, documentId, event) {
    if (event) event.stopPropagation();
    
    if (folderId === 'null') folderId = null;
    
    // Switch to folder view
    document.getElementById('nav-dashboard').click();
    
    // Set a global variable for highlighting
    window.highlightDocumentId = documentId;
    
    // Load the folder
    await loadFolder(folderId, true, documentId);
}

async function loadFolder(folderId = null, resetPage = true, highlightDocId = null) {
    if (currentAppView !== 'files') toggleAppView('files');
    if (resetPage) currentPage = 1;
    if (folderId === null) isRecycleBinMode = false;
    
    if (!isHistoryNav) {
        navHistory = navHistory.slice(0, navIndex + 1);
        if (navHistory[navHistory.length - 1] !== folderId) {
            navHistory.push(folderId);
            navIndex++;
        }
    }
    isHistoryNav = false;
    
    currentFolderId = folderId;
    const grid = document.getElementById('folderGrid');
    const emptyState = document.getElementById('emptyState');
    const headerActions = document.getElementById('headerActions');
    
    grid.innerHTML = '<p class="text-slate-400 text-sm col-span-full">Loading...</p>';
    emptyState.classList.add('hidden');
    if (headerActions) headerActions.classList.toggle('hidden', isRecycleBinMode);

    try {
        let url = `/api/folders.php?${folderId ? 'parent_id=' + folderId : ''}${isRecycleBinMode ? '&show_all=1' : ''}`;
        if (highlightDocId) {
            url += `&highlight_doc_id=${highlightDocId}`;
        } else {
            url += `&page=${currentPage}`;
        }
        const response = await fetch(url);
        const data = await response.json();

        if (highlightDocId && data.current_page) {
            currentPage = data.current_page;
        }

        grid.innerHTML = ''; 

        if (data.success) {
            if (data.permissions) {
                window.currentPermissions = data.permissions;
                const btnNewFolder = document.getElementById('btn-new-folder');
                if (btnNewFolder) btnNewFolder.classList.toggle('hidden', !data.permissions.right_add);
                const btnUpload = document.getElementById('btn-upload');
                if (btnUpload) btnUpload.classList.toggle('hidden', !data.permissions.right_add);
            } else {
                window.currentPermissions = null;
            }

            renderPagination(data.total_pages || 1, currentPage, 'paginationControls', `loadFolder(${folderId}, false)`);
            
            // --- 1. DYNAMIC BREADCRUMB TRAIL GENERATOR ---
            const breadcrumbZone = document.getElementById('breadcrumb');
            if (breadcrumbZone) {
                const backDisabled = navIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-200 hover:text-slate-800 cursor-pointer';
                const forwardDisabled = navIndex === navHistory.length - 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-200 hover:text-slate-800 cursor-pointer';
                
                let trailHTML = `
                    <div class="flex items-center gap-1 mr-2 border-r border-slate-200 pr-2 shrink-0">
                        <button onclick="if(navIndex > 0) goBack()" class="p-1 rounded text-slate-500 transition-colors ${backDisabled}" title="Back">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button onclick="if(navIndex < navHistory.length - 1) goForward()" class="p-1 rounded text-slate-500 transition-colors ${forwardDisabled}" title="Forward">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    <button onclick="loadFolder(null)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'null')" class="hover:text-blue-600 hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-all px-2 py-1 rounded font-medium text-slate-600 flex items-center gap-1.5 shrink-0">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                    Home
                                </button>`;
                
                if (data.breadcrumb && data.breadcrumb.length > 0) {
                    data.breadcrumb.forEach((crumb, index) => {
                        expandedTreeFolders.add(String(crumb.id));
                        trailHTML += `<svg class="w-4 h-4 text-slate-300 shrink-0 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>`;
                        
                        if (index === data.breadcrumb.length - 1) {
                            // Last item (current folder) is not clickable
                            trailHTML += `
                                <span class="text-slate-800 font-bold bg-slate-100 border border-slate-200 px-2.5 py-1 rounded shadow-inner max-w-[200px] truncate shrink-0" title="${crumb.name}">
                                    ${crumb.name}
                                </span>
                            `;
                        } else {
                            // Ancestor folders are clickable
                            trailHTML += `
                                <button onclick="loadFolder(${crumb.id})" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, ${crumb.id})" class="hover:text-blue-600 hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-all px-2 py-1 rounded font-medium text-slate-600 truncate max-w-[150px] shrink-0" title="${crumb.name}">
                                    ${crumb.name}
                                </button>
                            `;
                        }
                    });
                }
                breadcrumbZone.innerHTML = trailHTML;
                setTimeout(() => {
                    const el = document.getElementById('breadcrumb');
                    if (el) {
                        el.scrollLeft = el.scrollWidth;
                        updateBreadcrumbScrollButtons();
                    }
                }, 50);
            }

            // --- 2. EMPTY STATE CHECK ---
            if (data.folders.length === 0 && (!data.documents || data.documents.length === 0)) {
                emptyState.classList.remove('hidden');
                grid.className = 'grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 overflow-y-auto flex-1 min-h-0'; // reset
                return;
            }

          

                const getFolderActionBtn = (f) => {
                    if (isRecycleBinMode) {
                        return `<button onclick="promptRestoreFolder(${f.id}, event)" class="p-1.5 text-green-600 hover:bg-green-50 rounded transition-all opacity-100" title="Restore Folder"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></button>`;
                    } else {
                        if (window.currentPermissions && !window.currentPermissions.right_delete) return '';
                        return `<button onclick="deleteFolder(${f.id}, '${f.name.replace(/'/g, "\\'")}', event)" class="p-1.5 text-slate-300 hover:text-red-600 hover:bg-red-50 rounded transition-all opacity-100" title="Delete Folder"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>`;
                    }
                };
                const getDocActionBtn = (d) => {
                    if (isRecycleBinMode) {
                        return `<button onclick="promptRestoreDocument(${d.id}, event)" class="p-1.5 text-green-600 hover:bg-green-50 rounded transition-all opacity-100" title="Restore Document"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></button>`;
                    } else {
                        if (window.currentPermissions && !window.currentPermissions.right_delete) return '';
                        return `<button onclick="deleteDocument(${d.id}, '${d.title.replace(/'/g, "\\'")}', event)" class="p-1.5 text-slate-300 hover:text-red-600 hover:bg-red-50 rounded transition-all opacity-100" title="Delete Document"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>`;
                    }
                };
                
            // --- DETAIL VIEW RENDERING ---
            grid.className = 'w-full mb-4 flex flex-col flex-1 min-h-0'; 
                
                const thHTML = (col, label, cls, sortable = true) => {
                    const isSort = sortCol === col;
                    const arrow = isSort ? (sortDir === 'asc' ? '↑' : '↓') : '';
                    const arrowClass = isSort ? 'text-blue-500' : 'text-slate-300';
                    const storedWidth = getColumnWidth(col);
                    const widthStyle = storedWidth ? `width: ${storedWidth}px;` : '';
                    
                    const cursorClass = sortable ? 'cursor-pointer' : 'cursor-default';
                    const onClick = sortable ? `onclick="setSort('${col}')"` : '';
                    const hoverClass = sortable ? 'hover:text-slate-700' : '';

                    return `<th class="relative ${cls} ${hoverClass} transition-colors select-none" style="${widthStyle}">
                                <div class="flex items-center gap-1 justify-${cls.includes('text-right') ? 'end' : 'start'} ${cursorClass}" ${onClick}>
                                    ${label} ${sortable ? `<span class="text-xs ${arrowClass}">${arrow || '↕'}</span>` : ''}
                                </div>
                                <div class="absolute top-0 right-0 w-2 h-full cursor-col-resize bg-transparent hover:bg-blue-300 transition-colors z-10" onmousedown="initResize(event, '${col}', this)"></div>
                            </th>`;
                };

                let tableHTML = `
                    <div class="overflow-auto h-full pb-4">
                        <table class="w-full text-left border-collapse whitespace-nowrap table-fixed min-w-[600px]">
                            <thead>
                                <tr class="border-b border-slate-200 text-sm font-medium text-slate-500">
                                    <th class="pb-3 pl-3 w-10"><input type="checkbox" onchange="toggleAllSelection(this)" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></th>
                                    ${tableColumns.includes('type') ? thHTML('type', 'Type', 'pb-3 pl-2 w-16', false) : ''}
                                    ${tableColumns.includes('name') ? thHTML('name', 'Name', 'pb-3') : ''}
                                    ${tableColumns.includes('size') ? thHTML('size', 'Size', 'pb-3 text-right') : ''}
                                    ${tableColumns.includes('added') ? thHTML('added', 'Added', 'pb-3 text-right pr-4') : ''}
                                    ${tableColumns.includes('added_by') ? thHTML('added_by', 'Added By', 'pb-3 text-right pr-4') : ''}
                                    ${tableColumns.includes('modified_by') ? thHTML('modified_by', 'Last Modified By', 'pb-3 text-right pr-4') : ''}
                                    ${tableColumns.includes('modified_at') ? thHTML('modified_at', 'Last Modified At', 'pb-3 text-right pr-4') : ''}
`;
tableColumns.forEach(col => {
    if (col.startsWith('tpl_')) {
        let label = window.templateFields[col] || col;
        tableHTML += thHTML(col, label, 'pb-3 text-left');
    }
});
tableHTML += `<th class="pb-3 w-10"></th> 
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                `;

                const dateOpts = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };

                const sortFn = (a, b) => {
                    let valA, valB;
                    if (sortCol === 'name') {
                        valA = (a.title || a.name || '').toLowerCase();
                        valB = (b.title || b.name || '').toLowerCase();
                    } else if (sortCol === 'size') {
                        valA = a.file_size ? parseInt(a.file_size) : 0;
                        valB = b.file_size ? parseInt(b.file_size) : 0;
                    } else if (sortCol === 'added') {
                        valA = new Date(a.created_at).getTime();
                        valB = new Date(b.created_at).getTime();
                    } else if (sortCol === 'added_by') {
                        valA = (a.added_by_name || '').toLowerCase();
                        valB = (b.added_by_name || '').toLowerCase();
                    } else if (sortCol === 'modified_by') {
                        valA = (a.updated_by_name || '').toLowerCase();
                        valB = (b.updated_by_name || '').toLowerCase();
                    } else if (sortCol === 'modified_at') {
                        valA = a.updated_at ? new Date(a.updated_at).getTime() : 0;
                        valB = b.updated_at ? new Date(b.updated_at).getTime() : 0;
                    } else {
                        valA = ''; valB = '';
                    }
                    if (valA < valB) return sortDir === 'asc' ? -1 : 1;
                    if (valA > valB) return sortDir === 'asc' ? 1 : -1;
                    return 0;
                };

                if (data.folders) data.folders.sort(sortFn);
                if (data.documents) data.documents.sort(sortFn);

                data.folders.forEach(folder => {
                    const date = formatDateTimeWrap(folder.created_at);
                    const modifiedDate = folder.updated_at ? formatDateTimeWrap(folder.updated_at) : '--';
                    let rowData = `<td class="py-3 pl-3" onclick="event.stopPropagation()"><input type="checkbox" value="${folder.id}" onchange="toggleSelection(this, 'folder', ${folder.id})" class="row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"></td>`;
                    if (tableColumns.includes('type')) rowData += `<td class="py-3 pl-2"><svg class="w-5 h-5 text-slate-400 group-hover:text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg></td>`;
                    if (tableColumns.includes('name')) rowData += `<td class="py-3 font-medium text-slate-700">${folder.name}</td>`;
                    if (tableColumns.includes('size')) rowData += `<td class="py-3 text-right text-slate-400">--</td>`;
                    if (tableColumns.includes('added')) rowData += `<td class="py-3 text-right pr-4 text-slate-400">${date}</td>`;
                    if (tableColumns.includes('added_by')) rowData += `<td class="py-3 text-right pr-4 text-slate-400">${folder.added_by_name || '--'}</td>`;
                    if (tableColumns.includes('modified_by')) rowData += `<td class="py-3 text-right pr-4 text-slate-400">${folder.updated_by_name || '--'}</td>`;
                    if (tableColumns.includes('modified_at')) rowData += `<td class="py-3 text-right pr-4 text-slate-400">${modifiedDate}</td>`;
                    
                    tableColumns.forEach(col => {
                        if (col.startsWith('tpl_')) {
                            rowData += `<td class="py-3 text-left text-slate-400">--</td>`;
                        }
                    });
                    
                    tableHTML += `
                        <tr ${isRecycleBinMode ? '' : `draggable="true" ondragstart="handleDragStart(event)" ondragend="handleDragEnd(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, ${folder.id})"`} onclick="handleRowClick(event, this)" ondblclick="loadFolder(${folder.id})" data-folder-id="${folder.id}" data-folder-name="${folder.name.replace(/"/g, '&quot;')}" class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-colors group">
                            ${rowData}
                            <td class="py-3 pr-2 text-right">
                                ${getFolderActionBtn(folder)}
                            </td>
                        </tr>
                    `;
                });

                if (data.documents) {
                    data.documents.forEach(doc => {
                        const date = formatDateTimeWrap(doc.created_at);
                        const modifiedDate = doc.updated_at ? formatDateTimeWrap(doc.updated_at) : '--';
                        let docData = `<td class="py-3 pl-3" onclick="event.stopPropagation()"><input type="checkbox" value="${doc.id}" onchange="toggleSelection(this, 'document', ${doc.id})" class="row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"></td>`;
                        if (tableColumns.includes('type')) docData += `<td class="py-3 pl-2"><svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"></path></svg></td>`;
                        
                        let lockIcon = doc.checked_out_by ? `<span title="Checked out by ${doc.checked_out_by_name}" class="inline-flex items-center ml-2 text-amber-500"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg></span>` : '';
                        if (tableColumns.includes('name')) docData += `<td class="py-3 font-medium text-slate-700 flex items-center">${doc.title}${lockIcon}</td>`;
                        if (tableColumns.includes('size')) docData += `<td class="py-3 text-right text-slate-500">${formatBytes(doc.file_size)}</td>`;
                        if (tableColumns.includes('added')) docData += `<td class="py-3 text-right pr-4 text-slate-400">${date}</td>`;
                        if (tableColumns.includes('added_by')) docData += `<td class="py-3 text-right pr-4 text-slate-400">${doc.added_by_name || '--'}</td>`;
                        if (tableColumns.includes('modified_by')) docData += `<td class="py-3 text-right pr-4 text-slate-400">${doc.updated_by_name || '--'}</td>`;
                        if (tableColumns.includes('modified_at')) docData += `<td class="py-3 text-right pr-4 text-slate-400">${modifiedDate}</td>`;
                        
                        tableColumns.forEach(col => {
                            if (col.startsWith('tpl_')) {
                                let val = (doc.metadata && doc.metadata[col]) ? doc.metadata[col] : '--';
                                docData += `<td class="py-3 text-left text-slate-500">${val}</td>`;
                            }
                        });

                        tableHTML += `
                            <tr ${isRecycleBinMode ? '' : 'draggable="true"'} data-doc-id="${doc.id}" data-doc-title="${doc.title.replace(/"/g, '&quot;')}" ${isRecycleBinMode ? '' : `ondragstart="handleDragStart(event)" ondragend="handleDragEnd(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDocDrop(event, ${doc.id})"`} onclick="handleRowClick(event, this)" ondblclick="openDocument('${doc.filename}', '${doc.title.replace(/'/g, "\\'")}', ${doc.id})" class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-colors group">
                                ${docData}
                                <td class="py-3 pr-2 text-right">
                                    ${getDocActionBtn(doc)}
                                </td>
                            </tr>
                        `;
                    });
                }
                tableHTML += `</tbody></table></div>`;
                grid.innerHTML = tableHTML;
        } else {
            grid.innerHTML = `<p class="text-red-500 text-sm col-span-full">${data.message || 'Failed to load directory.'}</p>`;
        }
        
        const recentWidget = document.getElementById('recentDocumentsWidget');
        if (folderId === null && !isRecycleBinMode) {
            recentWidget.classList.remove('hidden');
            loadRecentDocuments();
        } else if (recentWidget) {
            recentWidget.classList.add('hidden');
        }
        
        if (window.highlightDocumentId) {
            setTimeout(() => {
                const el = document.getElementById('folderGrid').querySelector(`[data-doc-id="${window.highlightDocumentId}"]`);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('bg-blue-50');
                }
                window.highlightDocumentId = null;
            }, 100);
        }
        
        if (typeof loadSidebarTree === 'function') {
            loadSidebarTree();
        }
    } catch (error) {
        console.error("Load Folder Error: ", error);
        grid.innerHTML = '<p class="text-red-500 text-sm col-span-full">Failed to load directory. Check the console (F12) for details.</p>';
    }
}

async function loadRecentDocuments() {
    const grid = document.getElementById('recentDocumentsGrid');
    if (!grid) return;
    
    try {
        const res = await fetch('/api/recent_documents.php');
        const data = await res.json();
        
        if (data.success && data.items && data.items.length > 0) {
            grid.innerHTML = data.items.map(doc => `
                <div onclick="openDocument('${doc.filename}', '${doc.title.replace(/'/g, "\\'")}', ${doc.id})" class="bg-white border border-slate-200 rounded-lg p-3 hover:border-blue-300 hover:shadow-md transition-all cursor-pointer group flex flex-col h-full">
                    <div class="flex items-start justify-between mb-2">
                        <svg class="w-8 h-8 text-red-500 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"></path></svg>
                        <span class="text-xs font-medium text-slate-400 bg-slate-50 px-2 py-0.5 rounded">${formatBytes(doc.file_size)}</span>
                    </div>
                    <h4 class="text-sm font-semibold text-slate-700 truncate mb-1" title="${doc.title}">${doc.title}</h4>
                    <div class="mt-auto flex items-center justify-between">
                        <span class="text-xs text-slate-500 truncate" title="Location: ${doc.location_name}">In: ${doc.location_name}</span>
                    </div>
                </div>
            `).join('');
            document.getElementById('recentDocumentsWidget').classList.remove('hidden');
        } else {
            // Hide the widget entirely if there are no recent documents
            document.getElementById('recentDocumentsWidget').classList.add('hidden');
        }
    } catch (e) {
        console.error('Failed to load recent documents', e);
        document.getElementById('recentDocumentsWidget').classList.add('hidden');
    }
}

const rbDateOpts = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };

function buildRecycleBinRow(item, depth = 0) {
    const date = new Date(item.deleted_at).toLocaleString(undefined, rbDateOpts);
    const location = item.original_location || '';
    const isFolder = item.type === 'folder';
    const indent = depth * 24;
    const folderIcon = `<svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>`;
    const docIcon = `<svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"></path></svg>`;
    const chevron = isFolder
        ? `<button onclick="expandRecycleBinFolder(event, ${item.id}, this)" class="p-0.5 text-slate-400 hover:text-slate-700 transition-colors" title="Expand folder contents">
               <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
           </button>`
        : `<span class="w-5 inline-block"></span>`;
    const restoreFunc = isFolder ? `promptRestoreFolder(${item.id}, event)` : `promptRestoreDocument(${item.id}, event)`;
    const deleteBtn = window.currentUserRole === 'admin'
        ? `<button onclick="permanentDeleteItem(${item.id}, '${item.type}', event)" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-md shadow-sm transition-colors">Delete</button>`
        : '';
    return `
        <tr onclick="handleRowClick(event, this)" class="border-b border-slate-100 hover:bg-slate-50 transition-colors group" data-rb-id="${item.id}" data-rb-type="${item.type}">
            <td class="py-3 pl-3" onclick="event.stopPropagation()"><input type="checkbox" value="${item.id}" onchange="toggleSelection(this, '${item.type}', ${item.id})" class="row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"></td>
            <td class="py-3 pl-2">
                <div class="flex items-center gap-1" style="padding-left:${indent}px">
                    ${chevron}
                    ${isFolder ? folderIcon : docIcon}
                </div>
            </td>
            <td class="py-3 font-medium text-slate-700">${item.name}</td>
            <td class="py-3 text-right text-slate-500">${item.deleted_by_name || 'System'}</td>
            <td class="py-3 text-right text-slate-400">${date}</td>
            <td class="py-3 text-right pr-4 text-slate-500">${location || (depth > 0 ? '(inside deleted folder)' : 'Home')}</td>
            <td class="py-3 pr-2 text-center">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="${restoreFunc}" class="px-3 py-1.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-md shadow-sm transition-colors">Restore</button>
                    ${deleteBtn}
                </div>
            </td>
        </tr>`;
}

function sortRecycleBin(col) {
    if (rbSortCol === col) {
        rbSortDir = rbSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        rbSortCol = col;
        rbSortDir = col === 'deleted_at' ? 'desc' : 'asc';
    }
    loadRecycleBin(false);
}

async function loadRecycleBin(resetPage = true) {
    if (resetPage) currentPage = 1;
    currentFolderId = 'recycle_bin';
    isRecycleBinMode = true;
    const grid = document.getElementById('folderGrid');
    const emptyState = document.getElementById('emptyState');
    const breadcrumbZone = document.getElementById('breadcrumb');

    if (breadcrumbZone) {
        breadcrumbZone.innerHTML = `
            <div class="flex items-center gap-1.5 text-slate-600 font-medium px-2 py-1 bg-slate-50 border border-slate-200 rounded">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Recycle Bin
            </div>
        `;
    }

    grid.innerHTML = '<p class="text-slate-400 text-sm col-span-full">Loading Recycle Bin...</p>';
    emptyState.classList.add('hidden');
    
    // hide the view toggle buttons
    const btnGrid = document.getElementById('btn-grid');
    if (btnGrid && btnGrid.parentElement) {
        btnGrid.parentElement.classList.remove('md:flex');
        btnGrid.parentElement.classList.add('hidden');
    }

    try {
        const response = await fetch(`/api/recycle_bin.php?page=${currentPage}&sort_col=${rbSortCol}&sort_dir=${rbSortDir}`);
        const data = await response.json();
        
        grid.innerHTML = '';
        
        if (data.success) {
            renderPagination(data.total_pages || 1, currentPage, 'paginationControls', 'loadRecycleBin(false)');
            
            if (data.items.length === 0) {
                emptyState.classList.remove('hidden');
                grid.className = 'grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 overflow-y-auto flex-1 min-h-0';
                return;
            }

            grid.className = 'w-full mb-4 flex flex-col flex-1 min-h-0';
            grid.innerHTML = `
                <div class="overflow-auto h-full pb-4 w-full">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-slate-200 text-sm font-medium text-slate-500">
                                <th class="pb-3 pl-3 w-10"><input type="checkbox" onchange="toggleAllSelection(this)" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></th>
                                <th class="pb-3 pl-2 w-8 cursor-pointer hover:text-slate-800" onclick="sortRecycleBin('type')">Type <span class="text-xs text-slate-400 ml-1">${rbSortCol === 'type' ? (rbSortDir === 'asc' ? '↑' : '↓') : ''}</span></th>
                                <th class="pb-3 cursor-pointer hover:text-slate-800" onclick="sortRecycleBin('name')">Name <span class="text-xs text-slate-400 ml-1">${rbSortCol === 'name' ? (rbSortDir === 'asc' ? '↑' : '↓') : ''}</span></th>
                                <th class="pb-3 text-right cursor-pointer hover:text-slate-800" onclick="sortRecycleBin('deleted_by_name')">Deleted By <span class="text-xs text-slate-400 ml-1">${rbSortCol === 'deleted_by_name' ? (rbSortDir === 'asc' ? '↑' : '↓') : ''}</span></th>
                                <th class="pb-3 text-right cursor-pointer hover:text-slate-800" onclick="sortRecycleBin('deleted_at')">Deleted At <span class="text-xs text-slate-400 ml-1">${rbSortCol === 'deleted_at' ? (rbSortDir === 'asc' ? '↑' : '↓') : ''}</span></th>
                                <th class="pb-3 text-right pr-4 cursor-pointer hover:text-slate-800" onclick="sortRecycleBin('original_location')">Original Location <span class="text-xs text-slate-400 ml-1">${rbSortCol === 'original_location' ? (rbSortDir === 'asc' ? '↑' : '↓') : ''}</span></th>
                                <th class="pb-3 w-10 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm"></tbody>
                    </table>
                </div>
            `;

            document.querySelector('#folderGrid tbody').innerHTML = data.items.map(item => buildRecycleBinRow(item, 0)).join('');
        }
    } catch (error) {
        grid.innerHTML = '<p class="text-red-500 text-sm col-span-full">Failed to load Recycle Bin.</p>';
    }
}

async function expandRecycleBinFolder(event, folderId, btn) {
    event.stopPropagation();
    const chevron = btn.querySelector('svg');
    const tr = btn.closest('tr');
    const expanded = btn.dataset.expanded === '1';

    // Collapse — remove injected child rows
    if (expanded) {
        btn.dataset.expanded = '0';
        chevron.style.transform = '';
        tr.parentElement.querySelectorAll(`[data-rb-child-of="${folderId}"]`).forEach(r => r.remove());
        return;
    }

    btn.dataset.expanded = '1';
    chevron.style.transform = 'rotate(90deg)';

    try {
        const res = await fetch(`/api/recycle_bin_contents.php?folder_id=${folderId}`);
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        const tbody = tr.parentElement;

        // Insert child rows after the parent row
        let insertAfter = tr;
        (data.items || []).forEach(item => {
            const child = document.createElement('tbody');
            child.innerHTML = buildRecycleBinRow(item, 1);
            const childRow = child.firstElementChild;
            childRow.dataset.rbChildOf = folderId;
            insertAfter.insertAdjacentElement('afterend', childRow);
            insertAfter = childRow;
        });

        if (data.items.length === 0) {
            const empty = document.createElement('tr');
            empty.dataset.rbChildOf = folderId;
            empty.innerHTML = `<td colspan="7" class="py-2 pl-16 text-xs text-slate-400 italic">This folder is empty.</td>`;
            tr.insertAdjacentElement('afterend', empty);
        }
    } catch(e) {
        alert('Could not load folder contents.');
    }
}


async function permanentDeleteItem(id, type, event) {
    if (event) event.stopPropagation();
    const label = type === 'folder' ? 'folder and all its contents' : 'document';
    if (!confirm(`Permanently delete this ${label}?\n\nThis CANNOT be undone. The file will be removed from the system entirely.`)) return;
    try {
        const res = await fetch('/api/permanent_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, type })
        });
        const data = await res.json();
        if (data.success) {
            loadRecycleBin();
        } else {
            alert(data.message || 'Failed to permanently delete.');
        }
    } catch (e) {
        alert('Error: could not permanently delete.');
    }
}

async function executeBulkPermanentDelete() {
    const count = window.selectedItems.size;
    if (!count) return;
    if (!confirm(`Permanently delete ${count} item${count > 1 ? 's' : ''}?\n\nThis CANNOT be undone. All selected files will be removed from the system entirely.`)) return;

    const items = Array.from(window.selectedItems).map(str => {
        const [type, id] = str.split('-');
        return { type, id: parseInt(id) };
    });

    let failed = 0;
    for (const item of items) {
        try {
            const res = await fetch('/api/permanent_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: item.id, type: item.type })
            });
            const data = await res.json();
            if (!data.success) failed++;
        } catch (e) {
            failed++;
        }
    }

    clearBulkSelection();
    loadRecycleBin();
    if (failed > 0) alert(`${failed} item(s) could not be permanently deleted.`);
}

async function restoreDocument(id) {
    try {
        const res = await fetch('/api/restore_document.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            loadRecycleBin();
            loadSidebarTree();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Error restoring document'); }
}

async function restoreFolder(id) {
    try {
        const res = await fetch('/api/restore_folder.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            loadRecycleBin();
            loadSidebarTree();
        } else {
            alert(data.message);
        }
    } catch (e) { alert('Error restoring folder'); }
}

async function loadSidebarTree() {
    try {
        const response = await fetch('/api/folder_tree.php');
        const data = await response.json();
        
        if (data.success) {
            const sidebarTree = document.getElementById('sidebarFolderTree');
            const hasChildren = data.tree && data.tree.length > 0;
            const isHomeExpanded = expandedTreeFolders.has('home');
            const homeHiddenClass = isHomeExpanded ? '' : 'hidden';
            const homeRotateClass = isHomeExpanded ? 'rotate-90' : '';
            const isActiveHome = currentFolderId === null && !isRecycleBinMode;
            const activeHomeBg = isActiveHome ? 'bg-[#1e293b] ring-1 ring-[#334155]' : 'hover:bg-[#1e293b]';
            const activeHomeText = isActiveHome ? 'text-blue-400 font-medium' : 'text-slate-300 hover:text-white';
            const homeIconClass = isActiveHome ? 'text-blue-400' : 'text-slate-400';

            let chevronHtml = `<div class="w-4 h-4 shrink-0"></div>`;
            if (hasChildren) {
                chevronHtml = `
                    <button onclick="toggleTreeFolder(event, 'home')" class="p-0.5 hover:bg-slate-700 rounded text-slate-400 hover:text-white transition-colors">
                        <svg id="tree-chevron-home" class="w-3 h-3 transition-transform duration-200 ${homeRotateClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                `;
            }

            const homeHtml = `
            <div>
                <div class="w-full flex items-center py-1 ${activeHomeBg} rounded-md transition-colors" style="padding-left: 0.25rem">
                    ${chevronHtml}
                    <button onclick="loadFolder(null)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, 'null')" class="flex-1 flex items-center gap-2 ${activeHomeText} text-sm text-left px-1.5 py-0.5">
                        <svg class="w-4 h-4 shrink-0 ${homeIconClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span class="truncate">Home</span>
                    </button>
                </div>
                <div id="tree-children-home" class="${homeHiddenClass}">${renderTree(data.tree, 0)}</div>
            </div>`;
            sidebarTree.innerHTML = homeHtml;
        }
    } catch (error) {
        console.error("Failed to load folder tree:", error);
    }
}

function toggleTreeFolder(event, id) {
    event.stopPropagation();
    const childrenDiv = document.getElementById(`tree-children-${id}`);
    const chevron = document.getElementById(`tree-chevron-${id}`);
    const stringId = String(id);
    if (childrenDiv) {
        if (childrenDiv.classList.contains('hidden')) {
            childrenDiv.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-90');
            expandedTreeFolders.add(stringId);
        } else {
            childrenDiv.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
            expandedTreeFolders.delete(stringId);
        }
    }
}

function renderTree(nodes, depth) {
    let html = '';
    nodes.forEach(node => {
        const paddingLeft = (depth * 0.75) + 0.75; // rem
        const hasChildren = node.children && node.children.length > 0;
        const stringId = String(node.id);
        const isExpanded = expandedTreeFolders.has(stringId);
        const hiddenClass = isExpanded ? '' : 'hidden';
        const rotateClass = isExpanded ? 'rotate-90' : '';
        const isActive = currentFolderId == node.id && !isRecycleBinMode;
        const activeBg = isActive ? 'bg-[#1e293b] ring-1 ring-[#334155]' : 'hover:bg-[#1e293b]';
        const activeText = isActive ? 'text-blue-400 font-medium' : 'text-slate-300 hover:text-white';
        const iconClass = isActive ? 'text-blue-400' : 'text-slate-400';
        
        let chevronHtml = `<div class="w-4 h-4 shrink-0"></div>`;
        if (hasChildren) {
            chevronHtml = `
                <button onclick="toggleTreeFolder(event, '${node.id}')" class="p-0.5 hover:bg-slate-700 rounded text-slate-400 hover:text-white transition-colors">
                    <svg id="tree-chevron-${node.id}" class="w-3 h-3 transition-transform duration-200 ${rotateClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            `;
        }

        html += `
            <div>
                <div data-folder-id="${node.id}" data-folder-name="${node.name.replace(/"/g, '&quot;')}" class="w-full flex items-center py-1 ${activeBg} rounded-md transition-colors" style="padding-left: ${paddingLeft}rem">
                    ${chevronHtml}
                    <button onclick="isRecycleBinMode = false; loadFolder(${node.id})" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, ${node.id})" class="flex-1 flex items-center gap-2 ${activeText} text-sm text-left px-1.5 py-0.5">
                        <svg class="w-4 h-4 shrink-0 ${iconClass}" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                        <span class="truncate">${node.name}</span>
                    </button>
                </div>
            `;
        if (hasChildren) {
            html += `<div id="tree-children-${node.id}" class="${hiddenClass}">${renderTree(node.children, depth + 1)}</div>`;
        }
        html += `</div>`;
    });
    return html;
}

// Calls handled in DOMContentLoaded

let currentMoveTarget = null;
let selectedMoveDestination = undefined;

async function openMoveModal() {
    document.getElementById('moveModal').classList.remove('hidden');
    document.getElementById('moveModalTree').innerHTML = '<p class="text-sm text-slate-500 text-center">Loading folders...</p>';
    
    try {
        const res = await fetch('/api/folder_tree.php');
        const data = await res.json();
        document.getElementById('moveModalTree').innerHTML = `
            <div class="mb-2">
                <button onclick="selectMoveDestination(null, this)" class="move-dest-btn w-full text-left px-2 py-1.5 rounded hover:bg-slate-100 text-sm font-medium text-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Home (Root)
                </button>
            </div>
            ${renderMoveTree(data.tree || [], 0)}
        `;
    } catch (e) {
        document.getElementById('moveModalTree').innerHTML = '<p class="text-sm text-red-500">Failed to load folders.</p>';
    }
}

function renderMoveTree(nodes, depth) {
    let html = '';
    nodes.forEach(node => {
        const paddingLeft = (depth * 1) + 0.5; // rem
        const hasChildren = node.children && node.children.length > 0;
        
        let chevronHtml = `<div class="w-4 h-4 shrink-0"></div>`;
        if (hasChildren) {
            chevronHtml = `
                <button onclick="toggleMoveTreeFolder(event, '${node.id}')" class="p-0.5 hover:bg-slate-200 rounded text-slate-400 transition-colors">
                    <svg id="move-tree-chevron-${node.id}" class="w-3 h-3 transition-transform duration-200 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            `;
        }

        html += `
            <div>
                <div class="w-full flex items-center py-1 hover:bg-slate-50 rounded-md transition-colors" style="padding-left: ${paddingLeft}rem">
                    ${chevronHtml}
                    <button onclick="selectMoveDestination(${node.id}, this)" class="move-dest-btn flex-1 flex items-center gap-2 text-slate-700 hover:text-blue-600 text-sm text-left px-1.5 py-0.5 rounded transition-colors">
                        <svg class="w-4 h-4 shrink-0 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                        <span class="truncate">${node.name}</span>
                    </button>
                </div>
            `;
        if (hasChildren) {
            html += `<div id="move-tree-children-${node.id}">${renderMoveTree(node.children, depth + 1)}</div>`;
        }
        html += `</div>`;
    });
    return html;
}

function toggleMoveTreeFolder(e, id) {
    e.stopPropagation();
    const childrenDiv = document.getElementById(`move-tree-children-${id}`);
    const chevron = document.getElementById(`move-tree-chevron-${id}`);
    if (childrenDiv) {
        if (childrenDiv.classList.contains('hidden')) {
            childrenDiv.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-90');
        } else {
            childrenDiv.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
        }
    }
}

function selectMoveDestination(folderId, btn) {
    selectedMoveDestination = folderId;
    document.querySelectorAll('.move-dest-btn').forEach(el => {
        el.classList.remove('bg-blue-50', 'text-blue-700', 'ring-1', 'ring-blue-300');
    });
    if (btn) {
        btn.classList.add('bg-blue-50', 'text-blue-700', 'ring-1', 'ring-blue-300');
    }
}

async function confirmMove() {
    if (selectedMoveDestination === undefined) {
        alert("Please select a destination folder.");
        return;
    }
    
    if (!currentMoveTarget) return;

    if (Array.isArray(currentMoveTarget)) {
        for (const target of currentMoveTarget) {
            if (target.type === 'folder' && target.id == selectedMoveDestination) {
                alert("Cannot move a folder into itself.");
                return;
            }
        }
        try {
            await fetch('/api/bulk_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'move', items: currentMoveTarget, target_folder_id: selectedMoveDestination })
            });
            clearBulkSelection();
            document.getElementById('moveModal').classList.add('hidden');
            
            if (currentAppView === 'files') {
                loadFolder(currentFolderId);
                if (typeof loadSidebarTree === 'function') loadSidebarTree();
            } else if (currentAppView === 'recyclebin') {
                loadRecycleBin();
            } else if (currentAppView === 'search') {
                executeSearch(false);
            }
        } catch (error) {
            alert('Network error while bulk moving.');
        }
    } else {
        if (currentMoveTarget.type === 'folder' && currentMoveTarget.id == selectedMoveDestination) {
            alert("Cannot move a folder into itself.");
            return;
        }

        const isDoc = currentMoveTarget.type === 'document';
        const url = isDoc ? '/api/move_document.php' : '/api/move_folder.php';
        const payload = isDoc 
            ? { document_id: currentMoveTarget.id, target_folder_id: selectedMoveDestination } 
            : { folder_id: currentMoveTarget.id, target_folder_id: selectedMoveDestination };

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('moveModal').classList.add('hidden');
                
                if (currentAppView === 'files') {
                    loadFolder(currentFolderId);
                    if (typeof loadSidebarTree === 'function') loadSidebarTree();
                } else if (currentAppView === 'recyclebin') {
                    loadRecycleBin();
                } else if (currentAppView === 'search') {
                    executeSearch(false);
                }
            } else {
                alert(data.message || 'Failed to move item.');
            }
        } catch (error) {
            alert('Network error while moving.');
        }
    }
}



    // Create a new folder
    async function createNewFolder() {
        const folderName = prompt("Enter new folder name:");
        if (!folderName || folderName.trim() === '') return;

        try {
            const response = await fetch('/api/folders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    name: folderName, 
                    parent_id: currentFolderId 
                })
            });

            const data = await response.json();
            if (data.success) {
                loadFolder(currentFolderId); // Reload the current view
                loadSidebarTree(); // Refresh sidebar tree
            } else {
                alert(data.message);
            }
        } catch (error) {
            alert("Network error while creating folder.");
        }
    }


    function toggleUploadModal() {
    const modal = document.getElementById('uploadModal');
    modal.classList.toggle('hidden');
    document.getElementById('uploadForm').reset();
    document.getElementById('uploadStatus').classList.add('hidden');
}

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('fileInput');
    const status = document.getElementById('uploadStatus');

    if (fileInput.files.length === 0) return;

    const formData = new FormData();
    formData.append('document', fileInput.files[0]);
    formData.append('folder_id', currentFolderId);

    status.textContent = 'Uploading and securing file...';
    status.className = 'text-blue-600 text-sm font-medium mt-2 block';

    try {
        const response = await fetch('/api/upload.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            toggleUploadModal();
            loadFolder(currentFolderId); // Refresh view
            if (data.doc_id && data.filename && data.title) {
                openDocument(data.filename, data.title, data.doc_id);
            }
        } else {
            status.textContent = data.message;
            status.className = 'text-red-600 text-sm font-medium mt-2 block';
        }
    } catch (error) {
        status.textContent = 'Network error during upload.';
        status.className = 'text-red-600 text-sm font-medium mt-2 block';
    }
});






function toggleLeftSidebar() {
    const sidebar = document.getElementById('leftSidebar');
    if (sidebar.dataset.collapsed === "true") {
        sidebar.dataset.collapsed = "false";
        sidebar.style.display = "";
    } else {
        sidebar.dataset.collapsed = "true";
        sidebar.style.display = "none";
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('metaSidebar');
    // Toggle Tailwind translate classes to slide it out of view
    if (sidebar.classList.contains('translate-x-0')) {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('translate-x-full', 'hidden');
    } else {
        sidebar.classList.remove('translate-x-full', 'hidden');
        sidebar.classList.add('translate-x-0');
    }
}


function toggleThumbnails() {
    const sidebar = document.getElementById('thumbnailSidebar');
    if (sidebar.classList.contains('hidden') || sidebar.style.display === 'none') {
        sidebar.style.display = 'flex';
        sidebar.classList.remove('hidden');
    } else {
        sidebar.style.display = 'none';
        sidebar.classList.add('hidden');
    }
}

let draggedPageNum = null;
let selectedPages = [];

function generateThumbnails(pdf) {
    const sidebar = document.getElementById('thumbnailSidebar');
    sidebar.innerHTML = ''; // Clear old thumbnails
    
    // Reset selection when opening new document
    selectedPages = [1];

    for (let i = 1; i <= pdf.numPages; i++) {
        // Create a wrapper for styling and click events
        const wrapper = document.createElement('div');
        wrapper.id = `thumb-wrapper-${i}`;
        wrapper.className = `p-1.5 cursor-pointer border-2 rounded-lg transition-all shadow-sm ${i === 1 ? 'border-blue-500 bg-blue-100' : 'border-transparent bg-slate-100 hover:border-slate-400'}`;
        wrapper.onclick = (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (selectedPages.includes(i)) {
                    selectedPages = selectedPages.filter(p => p !== i);
                    if (selectedPages.length === 0) {
                        pageNum = i;
                        selectedPages = [i];
                    } else if (pageNum === i) {
                        pageNum = selectedPages[selectedPages.length - 1];
                    }
                } else {
                    selectedPages.push(i);
                    pageNum = i;
                }
            } else if (e.shiftKey && selectedPages.length > 0) {
                const lastSelected = selectedPages[selectedPages.length - 1];
                const start = Math.min(lastSelected, i);
                const end = Math.max(lastSelected, i);
                selectedPages = [];
                for (let p = start; p <= end; p++) {
                    selectedPages.push(p);
                }
                pageNum = i;
            } else {
                selectedPages = [i];
                pageNum = i;
            }
            queueRenderPage(pageNum);
            syncThumbnailHighlight(pageNum);
        };

        // Enable Drag-and-Drop
        wrapper.draggable = true;
        wrapper.dataset.pageNum = i;
        
        wrapper.ondragstart = (e) => {
            draggedPageNum = i;
            e.dataTransfer.effectAllowed = 'move';
            wrapper.classList.add('opacity-50');
        };
        
        wrapper.ondragover = (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            wrapper.classList.add('border-t-4', 'border-t-blue-500'); // Visual indicator
        };
        
        wrapper.ondragleave = (e) => {
            wrapper.classList.remove('border-t-4', 'border-t-blue-500');
        };
        
        wrapper.ondrop = async (e) => {
            e.preventDefault();
            e.stopPropagation();
            wrapper.classList.remove('border-t-4', 'border-t-blue-500');
            
            const targetPageNum = i;
            if (!draggedPageNum || draggedPageNum === targetPageNum) return;
            
            // Calculate new order
            let newOrder = [];
            for (let p = 1; p <= pdf.numPages; p++) {
                if (p === draggedPageNum) continue; // Skip dragged page
                if (p === targetPageNum) {
                    newOrder.push(draggedPageNum); // Insert before target
                }
                newOrder.push(p);
            }
            
            sidebar.innerHTML = '<div class="text-xs text-slate-500 text-center py-4 flex flex-col items-center gap-2"><svg class="animate-spin w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Reordering Pages...</div>';
            
            try {
                const res = await fetch('/api/reorder_pages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        document_id: currentDocumentId,
                        page_order: newOrder
                    })
                });
                const data = await res.json();
                if (data.success) {
                    if (currentFolderId) loadFolder(currentFolderId);
                    else if (typeof loadRecycleBin === 'function' && isRecycleBinMode) loadRecycleBin();
                    else loadFolder(null);
                    
                    const viewerTitle = document.getElementById('viewerTitle').textContent;
                    openDocument(data.new_filename, viewerTitle, currentDocumentId);
                } else {
                    alert(data.message);
                    generateThumbnails(pdfDoc); // Restore thumbnails on failure
                }
            } catch(err) {
                alert('Error reordering pages');
                generateThumbnails(pdfDoc); // Restore thumbnails on failure
            }
            
            draggedPageNum = null;
        };
        
        wrapper.ondragend = () => {
            wrapper.classList.remove('opacity-50');
            draggedPageNum = null;
        };

        // Create the miniature canvas
        const thumbCanvas = document.createElement('canvas');
        thumbCanvas.className = 'w-full shadow-sm bg-white rounded border border-slate-200 pointer-events-none';
        wrapper.appendChild(thumbCanvas);

        // Add the page number label
        const label = document.createElement('div');
        label.className = 'text-center text-xs font-medium text-slate-500 mt-1 pointer-events-none';
        label.innerText = i;
        wrapper.appendChild(label);

        sidebar.appendChild(wrapper);

        // Tell PDF.js to draw the page onto this tiny canvas
        pdf.getPage(i).then(page => {
            // Scale it down to roughly fit the 48-unit width sidebar
            const viewport = page.getViewport({ scale: 0.25 });
            thumbCanvas.height = viewport.height;
            thumbCanvas.width = viewport.width;
            
            page.render({
                canvasContext: thumbCanvas.getContext('2d'),
                viewport: viewport
            });
        });
    }
}

function syncThumbnailHighlight(activeNum) {
    if (!pdfDoc) return;
    for (let i = 1; i <= pdfDoc.numPages; i++) {
        const wrapper = document.getElementById(`thumb-wrapper-${i}`);
        if (wrapper) {
            if (selectedPages.includes(i)) {
                wrapper.classList.remove('border-transparent', 'bg-slate-100', 'hover:border-slate-400');
                if (i === activeNum) {
                    wrapper.classList.remove('border-blue-400', 'bg-blue-50');
                    wrapper.classList.add('border-blue-500', 'bg-blue-100'); // active one
                    wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    wrapper.classList.remove('border-blue-500', 'bg-blue-100');
                    wrapper.classList.add('border-blue-400', 'bg-blue-50'); // selected but not active
                }
            } else {
                wrapper.classList.remove('border-blue-500', 'bg-blue-100', 'border-blue-400', 'bg-blue-50');
                wrapper.classList.add('border-transparent', 'bg-slate-100', 'hover:border-slate-400');
            }
        }
    }
}


async function deleteDocument(docId, docTitle, event) {
    // Stop the click from also opening the PDF viewer modal
    event.stopPropagation(); 

    // Enterprise safeguard: Don't let users accidentally delete files
    if (!confirm(`Are you absolutely sure you want to delete "${docTitle}"?\n\nThis will send the file to the Recycle Bin.`)) {
        return;
    }

    try {
        const response = await fetch('/api/delete_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId })
        });

        const data = await response.json();

        if (data.success) {
            if (currentAppView === 'files') {
                loadFolder(currentFolderId);
                if (typeof loadSidebarTree === 'function') loadSidebarTree();
            } else if (currentAppView === 'recyclebin') {
                loadRecycleBin();
            } else if (currentAppView === 'search') {
                executeSearch(false);
            }
        } else {
            alert(data.message || 'Failed to delete document.');
        }
    } catch (error) {
        alert('A network error occurred while trying to delete the file.');
    }
}



async function deleteFolder(folderId, folderName, event) {
    // Prevent the folder from opening when we click the delete button
    event.stopPropagation(); 

    // High-stakes warning
    if (!confirm(`Are you sure you want to delete the folder "${folderName}"?\n\nThis will send the folder and all documents inside it to the Recycle Bin.`)) {
        return;
    }

    try {
        const response = await fetch('/api/delete_folder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: folderId })
        });

        const data = await response.json();

        if (data.success) {
            if (currentAppView === 'files') {
                loadFolder(currentFolderId);
                if (typeof loadSidebarTree === 'function') loadSidebarTree();
            } else if (currentAppView === 'recyclebin') {
                loadRecycleBin();
            } else if (currentAppView === 'search') {
                executeSearch(false);
            }
        } else {
            alert(data.message || 'Failed to delete folder.');
        }
    } catch (error) {
        alert('A network error occurred while trying to delete the folder.');
    }
}



async function renameFolder(id, oldName) {
    const newName = prompt('Enter new folder name:', oldName);
    if (!newName || newName.trim() === '' || newName === oldName) return;
    
    try {
        const res = await fetch('/api/rename_folder.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, name: newName })
        });
        const data = await res.json();
        if (data.success) {
            loadFolder(currentFolderId);
            loadSidebarTree();
            const panel = document.getElementById('activityPanel');
            if (panel && !panel.classList.contains('translate-x-full')) fetchActivityLog();
        } else alert(data.message);
    } catch (e) { alert('Error renaming folder.'); }
}

async function renameDocument(id, oldTitle) {
    const newTitle = prompt('Enter new document name:', oldTitle);
    if (!newTitle || newTitle.trim() === '' || newTitle === oldTitle) return;
    
    try {
        const res = await fetch('/api/rename_document.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, title: newTitle })
        });
        const data = await res.json();
        if (data.success) {
            loadFolder(currentFolderId);
            const panel = document.getElementById('activityPanel');
            if (panel && !panel.classList.contains('translate-x-full')) fetchActivityLog();
        } else alert(data.message);
    } catch (e) { alert('Error renaming document.'); }
}



function toggleColumnMenu(e) {
    e.stopPropagation();
    document.getElementById('columnMenu').classList.toggle('hidden');
}

function updateColumns(cb) {
    if (cb.checked) {
        if (!tableColumns.includes(cb.value)) tableColumns.push(cb.value);
    } else {
        tableColumns = tableColumns.filter(c => c !== cb.value);
    }
    localStorage.setItem('tableColumns', JSON.stringify(tableColumns));
    loadFolder(currentFolderId);
}

function toggleSearchColumnMenu(e) {
    e.stopPropagation();
    document.getElementById('searchColumnMenu').classList.toggle('hidden');
}

function updateSearchColumns(cb) {
    if (cb.checked) {
        if (!searchTableColumns.includes(cb.value)) searchTableColumns.push(cb.value);
    } else {
        searchTableColumns = searchTableColumns.filter(c => c !== cb.value);
    }
    localStorage.setItem('searchTableColumns', JSON.stringify(searchTableColumns));
    if (currentSearchData) renderSearchResults(currentSearchData);
}

function syncColumnCheckboxes() {
    ['size', 'added', 'added_by', 'modified_by', 'modified_at'].forEach(col => {
        const el = document.getElementById(`col-cb-${col.replace('_', '-')}`);
        if(el) el.checked = tableColumns.includes(col);
    });
    
    document.querySelectorAll('.col-cb-dynamic').forEach(cb => {
        cb.checked = tableColumns.includes(cb.value);
    });
}

function syncSearchColumnCheckboxes() {
    ['location', 'size', 'added', 'added_by', 'modified_by', 'modified_at'].forEach(col => {
        const el = document.getElementById(`search-col-cb-${col.replace('_', '-')}`);
        if(el) el.checked = searchTableColumns.includes(col);
    });
}

function setSort(col) {
    if (sortCol === col) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        sortCol = col;
        sortDir = 'asc';
    }
    localStorage.setItem('sortCol', sortCol);
    localStorage.setItem('sortDir', sortDir);
    loadFolder(currentFolderId);
}

// Column Resizing Logic
let colWidths = JSON.parse(localStorage.getItem('colWidths')) || {};

function getColumnWidth(col) {
    return colWidths[col] || null;
}

let startX, startWidth, currentResizerCol, currentTh;

function initResize(e, col, resizerElement) {
    e.stopPropagation();
    e.preventDefault(); // Prevent text selection
    startX = e.clientX;
    currentResizerCol = col;
    currentTh = resizerElement.parentElement;
    startWidth = currentTh.offsetWidth;

    document.addEventListener('mousemove', doResize);
    document.addEventListener('mouseup', stopResize);
    document.body.style.cursor = 'col-resize';
    resizerElement.classList.replace('bg-transparent', 'bg-blue-400');
}

function doResize(e) {
    if (!currentTh) return;
    const newWidth = startWidth + (e.clientX - startX);
    if (newWidth > 50) { // minimum width
        currentTh.style.width = `${newWidth}px`;
    }
}

function stopResize(e) {
    document.removeEventListener('mousemove', doResize);
    document.removeEventListener('mouseup', stopResize);
    document.body.style.cursor = 'default';
    
    if (currentTh && currentResizerCol) {
        // Reset resizer color
        const resizer = currentTh.querySelector('div.cursor-col-resize');
        if (resizer) resizer.classList.replace('bg-blue-400', 'bg-transparent');
        
        colWidths[currentResizerCol] = currentTh.offsetWidth;
        localStorage.setItem('colWidths', JSON.stringify(colWidths));
    }
    
    currentTh = null;
    currentResizerCol = null;
}

let itemToRestore = null;

async function promptRestoreFolder(id, event) {
    if(event) event.stopPropagation();
    itemToRestore = { type: 'folder', id };
    await populateRestoreDropdown();
    document.getElementById('restoreModal').classList.remove('hidden');
}

async function promptRestoreDocument(id, event) {
    if(event) event.stopPropagation();
    itemToRestore = { type: 'document', id };
    await populateRestoreDropdown();
    document.getElementById('restoreModal').classList.remove('hidden');
}

function closeRestoreModal() {
    document.getElementById('restoreModal').classList.add('hidden');
    itemToRestore = null;
}

async function populateRestoreDropdown() {
    const sel = document.getElementById('restoreDestination');
    sel.innerHTML = '<option value="null">Home (Root Directory)</option>';
    try {
        const res = await fetch('/api/folder_tree.php');
        const data = await res.json();
        if (data.success && data.tree) {
            const buildOptions = (nodes, prefix = '') => {
                nodes.forEach(node => {
                    if (itemToRestore.type === 'folder' && node.id == itemToRestore.id) return;
                    sel.innerHTML += `<option value="${node.id}">${prefix}${node.name}</option>`;
                    if (node.children) buildOptions(node.children, prefix + '— ');
                });
            };
            buildOptions(data.tree);
        }
    } catch(e) {}
}

document.getElementById('btnConfirmRestore').addEventListener('click', async () => {
    if(!itemToRestore) return;
    const targetId = document.getElementById('restoreDestination').value;
    const isDoc = itemToRestore.type === 'document';
    const endpoint = isDoc ? '/api/restore_document.php' : '/api/restore_folder.php';
    
    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: itemToRestore.id, target_folder_id: targetId })
        });
        const data = await res.json();
        if (data.success) {
            closeRestoreModal();
            if (currentFolderId === 'recycle_bin') loadRecycleBin();
            else loadFolder(currentFolderId);
            loadSidebarTree();
        } else {
            alert(data.message);
        }
    } catch(e) { alert('Error restoring item.'); }
});

    // Load root folder on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadSidebarTree();
        loadFolder(currentFolderId);
        syncColumnCheckboxes();
        syncSearchColumnCheckboxes();

        // Context Menu Logic
        let currentContextMenuTarget = null;
        let currentContextMenuBulkItems = [];
        const contextMenu = document.getElementById('contextMenu');
        
        document.addEventListener('click', () => {
            if (contextMenu) contextMenu.classList.add('hidden');
        });

        function handleContextMenu(e) {
            // If in recycle bin mode, disable context menu globally
            if (isRecycleBinMode) return;
            
            e.preventDefault();
            currentContextMenuTarget = { type: 'grid' };
            currentContextMenuBulkItems = [];
            
            let targetEl = e.target.closest('[data-doc-id]');
            if (targetEl) {
                currentContextMenuTarget = { type: 'document', id: targetEl.dataset.docId, name: targetEl.dataset.docTitle };
            } else {
                targetEl = e.target.closest('[data-folder-id]');
                if (targetEl) {
                    currentContextMenuTarget = { type: 'folder', id: targetEl.dataset.folderId, name: targetEl.dataset.folderName };
                }
            }
            
            if (targetEl && targetEl.tagName === 'TR') {
                const table = targetEl.closest('table') || document;
                const allRows = table.querySelectorAll('tr.bg-blue-50');
                allRows.forEach(row => {
                    if (row !== targetEl) {
                        const cb = row.querySelector('.row-checkbox');
                        if (!cb || !cb.checked) {
                            row.classList.remove('bg-blue-50');
                        }
                    }
                });
                targetEl.classList.add('bg-blue-50');
            }

            let hideRename = false;
            if (currentContextMenuTarget.type !== 'grid') {
                const selKey = `${currentContextMenuTarget.type}-${currentContextMenuTarget.id}`;
                if (window.selectedItems && window.selectedItems.has(selKey) && window.selectedItems.size > 1) {
                    currentContextMenuBulkItems = Array.from(window.selectedItems).map(item => {
                        const [type, id] = item.split('-');
                        return { type, id: parseInt(id) };
                    });
                    hideRename = true;
                }
            }

            const canModify = window.currentPermissions ? window.currentPermissions.right_modify : true;
            const canDelete = window.currentPermissions ? window.currentPermissions.right_delete : true;

            const canManageSecurity = window.currentPermissions ? window.currentPermissions.right_manage_security : true;

            document.getElementById('cm-new-folder').classList.toggle('hidden', currentContextMenuTarget.type !== 'grid' || !canModify);
            document.getElementById('cm-rename').classList.toggle('hidden', currentContextMenuTarget.type === 'grid' || hideRename || !canModify);
            document.getElementById('cm-move').classList.toggle('hidden', currentContextMenuTarget.type === 'grid' || !canDelete);
            document.getElementById('cm-delete').classList.toggle('hidden', currentContextMenuTarget.type === 'grid' || !canDelete);
            
            if (document.getElementById('cm-security')) {
                const isDoc = currentContextMenuTarget.type === 'document';
                document.getElementById('cm-security').classList.toggle('hidden', isDoc || hideRename || !canManageSecurity);
            }

            contextMenu.style.left = `${e.pageX}px`;
            contextMenu.style.top = `${e.pageY}px`;
            contextMenu.classList.remove('hidden');
        }

        const gridContainer = document.getElementById('folderGrid');
        if (gridContainer && contextMenu) {
            gridContainer.addEventListener('contextmenu', handleContextMenu);
        }
        
        const sidebarTree = document.getElementById('sidebarFolderTree');
        if (sidebarTree && contextMenu) {
            sidebarTree.addEventListener('contextmenu', handleContextMenu);
        }
        
        const searchResultsGrid = document.getElementById('searchResultsGrid');
        if (searchResultsGrid && contextMenu) {
            searchResultsGrid.addEventListener('contextmenu', handleContextMenu);
        }

        if (contextMenu) {
            document.getElementById('cm-new-folder').addEventListener('click', (e) => { e.stopPropagation(); contextMenu.classList.add('hidden'); createNewFolder(); });
            document.getElementById('cm-rename').addEventListener('click', (e) => {
                e.stopPropagation(); contextMenu.classList.add('hidden');
                if (currentContextMenuTarget.type === 'document') renameDocument(currentContextMenuTarget.id, currentContextMenuTarget.name);
                else if (currentContextMenuTarget.type === 'folder') renameFolder(currentContextMenuTarget.id, currentContextMenuTarget.name);
            });
            document.getElementById('cm-move').addEventListener('click', (e) => {
                e.stopPropagation(); contextMenu.classList.add('hidden');
                currentMoveTarget = currentContextMenuBulkItems.length > 1 ? currentContextMenuBulkItems : currentContextMenuTarget;
                openMoveModal();
            });
            if (document.getElementById('cm-security')) {
                document.getElementById('cm-security').addEventListener('click', (e) => {
                    e.stopPropagation(); contextMenu.classList.add('hidden');
                    const targetFolderId = currentContextMenuTarget.type === 'grid' ? currentFolderId : currentContextMenuTarget.id;
                    const folderName = currentContextMenuTarget.type === 'grid' ? 'Current Folder' : currentContextMenuTarget.name;
                    openPermissionsModal(targetFolderId, folderName);
                });
            }
            document.getElementById('cm-delete').addEventListener('click', async (e) => {
                e.stopPropagation(); contextMenu.classList.add('hidden');
                
                if (currentContextMenuBulkItems.length > 1) {
                    if (!confirm(`Are you absolutely sure you want to delete ${currentContextMenuBulkItems.length} items?\n\nThis will send them to the Recycle Bin.`)) {
                        return;
                    }
                    try {
                        const responses = await Promise.all(currentContextMenuBulkItems.map(item => {
                            const endpoint = item.type === 'document' ? '/api/delete_document.php' : '/api/delete_folder.php';
                            return fetch(endpoint, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: item.id })
                            });
                        }));
                        if (window.selectedItems) window.selectedItems.clear();
                        const checkboxes = document.querySelectorAll('.row-checkbox');
                        checkboxes.forEach(cb => { cb.checked = false; cb.closest('tr').classList.remove('bg-blue-50'); });
                        loadFolder(currentFolderId);
                    } catch (error) {
                        alert('A network error occurred while trying to bulk delete.');
                    }
                } else {
                    const fakeEvent = { stopPropagation: () => {} };
                    if (currentContextMenuTarget.type === 'document') deleteDocument(currentContextMenuTarget.id, currentContextMenuTarget.name, fakeEvent);
                    else if (currentContextMenuTarget.type === 'folder') deleteFolder(currentContextMenuTarget.id, currentContextMenuTarget.name, fakeEvent);
                }
            });

        }
    });

    // Initialize Activity Panel on escape press
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activityPanel = document.getElementById('activityPanel');
            if (activityPanel && !activityPanel.classList.contains('translate-x-full')) {
                toggleActivityPanel();
            }
        }
    });

function toggleActivityPanel() {
    const panel = document.getElementById('activityPanel');
    if (panel.classList.contains('translate-x-full')) {
        panel.classList.remove('translate-x-full');
        fetchActivityLog();
    } else {
        panel.classList.add('translate-x-full');
    }
}

async function fetchActivityLog() {
    const feed = document.getElementById('activityFeed');
    feed.innerHTML = '<div class="text-sm text-slate-500 text-center py-4">Loading activity...</div>';
    
    try {
        const response = await fetch('/api/activity_log.php');
        const data = await response.json();
        
        if (data.success) {
            if (!data.activities || data.activities.length === 0) {
                feed.innerHTML = '<div class="text-sm text-slate-400 text-center py-4">No recent activity.</div>';
                return;
            }
            
            let html = '';
            data.activities.forEach(log => {
                const isDoc = log.action_type.includes('document') || log.action_type.includes('Document') || log.action_type.includes('PDF') || log.action_type.includes('Page');
                const isRename = log.action_type.startsWith('rename_');
                
                const icon = isDoc 
                    ? `<svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"></path></svg>`
                    : `<svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>`;

                let actionText = '';
                if (isRename) {
                    actionText = `Renamed <span class="font-semibold text-slate-900">${log.item_name}</span> to <span class="font-semibold text-slate-900">${log.target_name}</span>`;
                } else if (log.action_type.startsWith('Restored')) {
                    actionText = `Restored <span class="font-semibold text-slate-900">${log.item_name}</span> to <span class="font-semibold text-slate-900">${log.target_name}</span>`;
                } else if (log.action_type === 'Imported PDF') {
                    actionText = `Imported <span class="font-semibold text-slate-900">${log.item_name}</span>`;
                } else if (log.action_type === 'Removed Page') {
                    actionText = `Removed page from <span class="font-semibold text-slate-900">${log.item_name}</span> (Sent to Recycle Bin)`;
                } else if (log.action_type === 'Reordered Pages') {
                    actionText = `Reordered pages in <span class="font-semibold text-slate-900">${log.item_name}</span>`;
                } else if (log.action_type === 'Merged Document') {
                    actionText = `Merged into <span class="font-semibold text-slate-900">${log.item_name}</span>`;
                } else if (log.action_type === 'Deleted Document (Absorbed)') {
                    actionText = `Deleted <span class="font-semibold text-slate-900">${log.item_name}</span> (Absorbed by Merge)`;
                } else {
                    actionText = `Moved <span class="font-semibold text-slate-900">${log.item_name}</span> into <span class="font-semibold text-slate-900">${log.target_name}</span>`;
                }
                
                const timeStr = new Date(log.created_at).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});

                html += `
                    <div class="flex gap-3 items-start relative pb-4 border-l-2 border-slate-100 ml-3 last:border-0 last:pb-0">
                        <div class="mt-0.5 bg-blue-50 p-1.5 rounded-full ring-4 ring-white relative z-10 shrink-0 -ml-[13px]">
                            ${icon}
                        </div>
                        <div class="-mt-1.5 pb-2">
                            <p class="text-sm text-slate-700 leading-snug">
                                ${actionText}
                            </p>
                            <span class="text-xs text-slate-400 mt-1 block">${timeStr}</span>
                        </div>
                    </div>
                `;
            });
            
            feed.innerHTML = html;
        }
    } catch (e) {
        feed.innerHTML = '<div class="text-sm text-red-500 text-center py-4">Failed to load activity.</div>';
    }
}
let currentAppView = 'files';
let currentTemplateId = null;
let templateFields = [];

function toggleAppView(view) {
    currentAppView = view;
    document.getElementById('mainFilesView').classList.toggle('hidden', (view !== 'files' && view !== 'recyclebin'));
    document.getElementById('mainTemplatesView').classList.toggle('hidden', view !== 'templates');
    document.getElementById('mainSearchView').classList.toggle('hidden', view !== 'search');
    if (document.getElementById('mainUsersView')) {
        document.getElementById('mainUsersView').classList.toggle('hidden', view !== 'users');
    }
    if (document.getElementById('mainSettingsView')) {
        document.getElementById('mainSettingsView').classList.toggle('hidden', view !== 'settings');
    }
    
    // Update sidebar styles
    const btnFiles = document.getElementById('nav-dashboard');
    const btnTpl = document.getElementById('nav-templates');
    const btnSearch = document.getElementById('nav-search');
    const btnRecycle = document.getElementById('nav-recyclebin');
    const btnUsers = document.getElementById('nav-users');
    const btnSettings = document.getElementById('nav-settings');
    
    const activeClass = 'w-full flex items-center gap-3 px-3 py-2 bg-blue-600 rounded-lg text-sm font-medium text-white transition-colors';
    const inactiveClass = 'w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-[#1e293b] hover:text-white transition-colors';

    if(btnFiles) btnFiles.className = view === 'files' ? activeClass : inactiveClass;
    if(btnTpl) btnTpl.className = view === 'templates' ? activeClass : inactiveClass;
    if(btnSearch) btnSearch.className = view === 'search' ? activeClass : inactiveClass;
    if(btnRecycle) btnRecycle.className = view === 'recyclebin' ? activeClass : inactiveClass;
    if(btnUsers) btnUsers.className = view === 'users' ? activeClass : inactiveClass;
    if(btnSettings) btnSettings.className = view === 'settings' ? activeClass : inactiveClass;
    
    if (document.getElementById('sidebarNavHeader')) {
        document.getElementById('sidebarNavHeader').classList.toggle('hidden', view !== 'files');
    }
    if (document.getElementById('sidebarFolderTree')) {
        document.getElementById('sidebarFolderTree').classList.toggle('hidden', view !== 'files');
    }
    
    if (view === 'templates') {
        loadTemplates();
    } else if (view === 'search') {
        onSearchTypeChange();
    } else if (view === 'recyclebin') {
        loadRecycleBin();
    } else if (view === 'users') {
        loadUsers();
        loadGroups();
    } else if (view === 'files') {
        if (isRecycleBinMode) {
            loadFolder(null); // return to home if we were in recycle bin
        }
    }
}

async function loadTemplates() {
    const tbody = document.getElementById('templatesListBody');
    tbody.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-500">Loading...</td></tr>';
    try {
        const res = await fetch('/api/templates/list.php');
        const data = await res.json();
        if (data.success) {
            if (data.templates.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-500">No templates found.</td></tr>';
                return;
            }
            let html = '';
            data.templates.forEach(t => {
                html += `
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="py-3 px-4 font-medium text-slate-700">${t.name}</td>
                        <td class="py-3 px-4 text-slate-500 truncate max-w-xs">${t.description || '-'}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">${t.field_count} Fields</span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button onclick="openTemplateEditor(${t.id})" class="text-blue-600 hover:text-blue-800 font-medium mr-3">Edit</button>
                            <button onclick="deleteTemplate(${t.id})" class="text-red-500 hover:text-red-700 font-medium">Delete</button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else alert(data.message);
    } catch(e) { tbody.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-red-500">Error loading templates</td></tr>'; }
}

async function openTemplateEditor(id = null) {
    currentTemplateId = id;
    templateFields = [];
    document.getElementById('templatesListContainer').classList.add('hidden');
    document.getElementById('templateEditorContainer').classList.remove('hidden');
    document.getElementById('tplName').value = '';
    document.getElementById('tplDesc').value = '';
    document.getElementById('templateEditorTitle').innerText = id ? 'Edit Template' : 'Create New Template';
    renderFields();

    if (id) {
        try {
            const res = await fetch(`/api/templates/get.php?id=${id}`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('tplName').value = data.template.name;
                document.getElementById('tplDesc').value = data.template.description;
                templateFields = data.fields.map(f => ({
                    id: f.id,
                    name: f.name,
                    type: f.type,
                    options: f.options ? f.options.join(', ') : '',
                    is_required: f.is_required
                }));
                renderFields();
            }
        } catch(e) { alert('Error fetching template details'); }
    }
}

function closeTemplateEditor() {
    document.getElementById('templatesListContainer').classList.remove('hidden');
    document.getElementById('templateEditorContainer').classList.add('hidden');
}

function addField() {
    const type = document.getElementById('newFieldType').value;
    templateFields.push({ id: 'temp_' + Date.now(), name: '', type: type, options: '', is_required: false });
    renderFields();
}

function removeField(index) {
    templateFields.splice(index, 1);
    renderFields();
}

function renderFields() {
    const container = document.getElementById('fieldsContainer');
    if (templateFields.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-500 italic text-center py-4 border border-dashed border-slate-300 rounded-lg bg-slate-50">No fields added yet. Add a field above.</p>';
        return;
    }
    
    let html = '';
    templateFields.forEach((field, i) => {
        const typeLabels = { text: 'Text', number: 'Number', date: 'Date', dropdown: 'Dropdown' };
        
        let optionsHtml = '';
        if (field.type === 'dropdown') {
            optionsHtml = `
                <div class="mt-2 pl-8">
                    <input type="text" class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" 
                        placeholder="Comma separated options (e.g. HR, Sales, IT)" 
                        value="${field.options || ''}" 
                        onchange="templateFields[${i}].options = this.value">
                </div>
            `;
        }

        html += `
            <div class="p-3 border border-slate-200 rounded-lg bg-slate-50 flex flex-col gap-2">
                <div class="flex gap-4 items-center">
                    <div class="text-slate-400 cursor-move">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
                    </div>
                    <div class="flex-1 flex items-center gap-3">
                        <input type="text" class="flex-1 border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" 
                            placeholder="Field Label (e.g. Invoice Amount)" 
                            value="${field.name || ''}" 
                            onchange="templateFields[${i}].name = this.value">
                        
                        <span class="px-2 py-1 bg-white border border-slate-200 rounded text-xs font-medium text-slate-600 shadow-sm">${typeLabels[field.type]}</span>
                        
                        <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer">
                            <input type="checkbox" class="rounded border-slate-300 text-blue-600" ${field.is_required ? 'checked' : ''} onchange="templateFields[${i}].is_required = this.checked">
                            Required
                        </label>
                    </div>
                    <button onclick="removeField(${i})" class="p-2 text-slate-400 hover:text-red-500 transition-colors" title="Remove Field">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
                ${optionsHtml}
            </div>
        `;
    });
    container.innerHTML = html;
}

async function saveTemplate() {
    const name = document.getElementById('tplName').value;
    const desc = document.getElementById('tplDesc').value;
    
    if (!name.trim()) return alert('Template Name is required');
    
    for (let f of templateFields) {
        if (!f.name.trim()) return alert('All fields must have a label');
        if (f.type === 'dropdown' && !f.options.trim()) return alert('Dropdown fields must have at least one option');
    }
    

    try {
        const res = await fetch('/api/templates/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentTemplateId,
                name: name,
                description: desc,
                fields: templateFields
            })
        });
        const data = await res.json();
        if (data.success) {
            closeTemplateEditor();
            loadTemplates();
        } else {
            alert(data.message);
        }
    } catch(e) { alert('Error saving template'); }
}

let pendingMergeSource = null;
let pendingMergeTarget = null;

function handleDocDrop(event, targetDocId) {
    event.preventDefault();
    event.stopPropagation(); // prevent folder drop from triggering
    event.currentTarget.classList.remove('bg-blue-50', 'border-blue-300');

    const dragDataStr = event.dataTransfer.getData('text/plain');
    if (!dragDataStr) return;
    
    let dragData;
    try { dragData = JSON.parse(dragDataStr); } catch(e) { return; }

    if (dragData.type !== 'document' || dragData.id == targetDocId) return;

    pendingMergeSource = dragData.id;
    pendingMergeTarget = targetDocId;

    // Get names from DOM
    const srcEl = document.querySelector(`[data-doc-id="${dragData.id}"]`);
    const tgtEl = document.querySelector(`[data-doc-id="${targetDocId}"]`);
    
    document.getElementById('mergeSrcName').textContent = srcEl ? srcEl.dataset.docTitle : 'the document';
    document.getElementById('mergeTgtName').textContent = tgtEl ? tgtEl.dataset.docTitle : 'the target';

    const modal = document.getElementById('mergeModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.children[0].classList.remove('scale-95');
    }, 10);
}

function closeMergeModal() {
    const modal = document.getElementById('mergeModal');
    modal.classList.add('opacity-0');
    modal.children[0].classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
    pendingMergeSource = null;
    pendingMergeTarget = null;
}

async function executeMerge(position) {
    if (!pendingMergeSource || !pendingMergeTarget) return;

    try {
        const res = await fetch('/api/merge_documents.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                source_id: pendingMergeSource,
                target_id: pendingMergeTarget,
                position: position
            })
        });
        const data = await res.json();
        
        if (data.success) {
            closeMergeModal();
            if (currentFolderId) loadFolder(currentFolderId);
            else loadFolder(null);
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
        alert('An error occurred while merging documents.');
    }
}

async function deleteTemplate(id) {
    if (!confirm('Are you sure you want to delete this template?')) return;
    try {
        const res = await fetch('/api/templates/delete.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) loadTemplates();
        else alert(data.message);
    } catch(e) { alert('Error deleting template'); }
}
let currentDocMetadataFields = [];
let currentDocMetadataValues = {};

async function loadDocumentMetadata(docId) {
    const sel = document.getElementById('docTemplateSelect');
    const container = document.getElementById('docMetadataFields');
    sel.innerHTML = '<option value="">Loading...</option>';
    container.innerHTML = '<p class="text-sm text-slate-400 italic">Loading metadata...</p>';

    try {
        const res = await fetch(`/api/get_metadata.php?document_id=${docId}`);
        const data = await res.json();
        if (data.success) {
            let opts = '<option value="">-- No Template Assigned --</option>';
            data.templates.forEach(t => {
                opts += `<option value="${t.id}" ${data.template_id == t.id ? 'selected' : ''}>${t.name}</option>`;
            });
            sel.innerHTML = opts;

            currentDocTemplateId = data.template_id;
            currentDocMetadataFields = data.fields;
            currentDocMetadataValues = data.values;
            
            if (data.permissions) {
                window.currentPermissions = data.permissions;
            }
            
            // Determine Lock Status first
            const isLocked = data.checked_out_by !== null;
            const lockedByMe = data.checked_out_by == window.currentUserId;
            const isAdmin = window.currentUserRole === 'admin';
            
            if (!isLocked) {
                window.currentDocumentLocked = false;
            } else {
                if (lockedByMe) {
                    window.currentDocumentLocked = false;
                } else {
                    window.currentDocumentLocked = true;
                }
            }
            
            const canModify = window.currentPermissions ? window.currentPermissions.right_modify : true;
            const canEdit = canModify && !window.currentDocumentLocked;
            
            renderDocumentMetadataFields();
            
            // Toggle edit controls based on lock
            document.getElementById('ws-btn-import').classList.toggle('hidden', !canEdit);
            document.getElementById('ws-btn-delete-page').classList.toggle('hidden', !canEdit);
            document.getElementById('ws-btn-save-meta').classList.toggle('hidden', !canEdit);
            document.getElementById('ws-btn-markup').classList.toggle('hidden', !canEdit);
            document.getElementById('docTemplateSelect').disabled = !canEdit;
            
            if (data.full_path) {
                document.getElementById('viewerPath').textContent = data.full_path;
            } else {
                document.getElementById('viewerPath').textContent = '';
            }
            
            // Check-out Status UI
            const coStatus = document.getElementById('viewerCheckoutStatus');
            const coAction = document.getElementById('viewerCheckoutAction');
            if (coStatus && coAction) {
                coStatus.innerHTML = '';
                coAction.innerHTML = '';
                if (isLocked) {
                    if (lockedByMe) {
                        coStatus.innerHTML = `<span class="bg-green-100 text-green-800 px-2 py-0.5 rounded flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg> Locked for editing</span>`;
                    } else {
                        coStatus.innerHTML = `<span class="bg-red-100 text-red-800 px-2 py-0.5 rounded flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg> Locked by ${data.checked_out_by_name}</span>`;
                        if (isAdmin) {
                            coAction.innerHTML = `<button onclick="checkinDocument(${docId})" class="px-2 py-1 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded text-xs font-semibold shadow-sm transition-colors border border-slate-300" title="Admin Override">Force Unlock</button>`;
                        }
                    }
                }
            }
        } else {
            container.innerHTML = '<p class="text-sm text-red-500">Failed to load metadata.</p>';
        }
    } catch (e) {
        container.innerHTML = '<p class="text-sm text-red-500">Error fetching metadata.</p>';
    }
}

async function checkoutDocument(docId) {
    try {
        const res = await fetch('/api/checkout_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ document_id: docId })
        });
        const data = await res.json();
        if (data.success) {
            loadDocumentMetadata(docId); // Reload to update UI
            loadFolder(currentFolderId, true); // Update grid icon silently
        } else {
            alert('Failed to check out: ' + data.message);
        }
    } catch(e) {
        alert('Error communicating with server.');
    }
}

async function checkinDocument(docId) {
    try {
        const res = await fetch('/api/checkin_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ document_id: docId })
        });
        const data = await res.json();
        if (data.success) {
            loadDocumentMetadata(docId); // Reload to update UI
            loadFolder(currentFolderId, true); // Update grid icon silently
        } else {
            alert('Failed to check in: ' + data.message);
        }
    } catch(e) {
        alert('Error communicating with server.');
    }
}

async function handleTemplateChange(templateId) {
    currentDocTemplateId = templateId;
    if (!templateId) {
        currentDocMetadataFields = [];
        renderDocumentMetadataFields();
        return;
    }

    const container = document.getElementById('docMetadataFields');
    container.innerHTML = '<p class="text-sm text-slate-400 italic">Loading fields...</p>';

    try {
        // Fetch fields for this template
        const res = await fetch(`/api/templates/get.php?id=${templateId}`);
        const data = await res.json();
        if (data.success) {
            currentDocMetadataFields = data.fields;
            renderDocumentMetadataFields();
        }
    } catch(e) {
        container.innerHTML = '<p class="text-sm text-red-500">Error fetching template fields.</p>';
    }
}

function renderDocumentMetadataFields() {
    const container = document.getElementById('docMetadataFields');
    if (!currentDocTemplateId) {
        container.innerHTML = '<p class="text-sm text-slate-400 italic">Select a template to view fields.</p>';
        return;
    }
    if (currentDocMetadataFields.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400 italic">This template has no fields.</p>';
        return;
    }

    let html = '';
    currentDocMetadataFields.forEach(f => {
        let val = currentDocMetadataValues[f.id] || '';
        let inputHtml = '';
        
        const reqStr = f.is_required ? 'required' : '';
        const ast = f.is_required ? '<span class="text-red-500">*</span>' : '';
        const baseClass = 'w-full text-sm border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1';
        const canModify = window.currentPermissions ? window.currentPermissions.right_modify : true;
        const canEdit = canModify && !window.currentDocumentLocked;
        const disStr = canEdit ? '' : 'disabled';

        if (f.type === 'text') {
            inputHtml = `<input type="text" data-field-id="${f.id}" value="${val}" class="${baseClass}" ${reqStr} ${disStr}>`;
        } else if (f.type === 'number') {
            inputHtml = `<input type="number" data-field-id="${f.id}" value="${val}" class="${baseClass}" ${reqStr} ${disStr}>`;
        } else if (f.type === 'date') {
            inputHtml = `<input type="date" data-field-id="${f.id}" value="${val}" class="${baseClass}" ${reqStr} ${disStr}>`;
        } else if (f.type === 'dropdown') {
            let opts = '<option value="">-- Select --</option>';
            if (f.options && Array.isArray(f.options)) {
                f.options.forEach(o => {
                    opts += `<option value="${o}" ${val === o ? 'selected' : ''}>${o}</option>`;
                });
            }
            inputHtml = `<select data-field-id="${f.id}" class="${baseClass}" ${reqStr} ${disStr}>${opts}</select>`;
        }

        html += `
            <div>
                <label class="block text-xs text-slate-700 font-medium">${f.name} ${ast}</label>
                ${inputHtml}
            </div>
        `;
    });
    container.innerHTML = html;
}

async function saveDocumentMetadata() {
    if (!currentDocumentId) return;
    
    // Gather values
    let metadata = {};
    const inputs = document.getElementById('docMetadataFields').querySelectorAll('[data-field-id]');
    
    for (let el of inputs) {
        if (el.hasAttribute('required') && !el.value.trim()) {
            return alert('Please fill in all required fields.');
        }
        metadata[el.dataset.fieldId] = el.value.trim();
    }

    try {
        const res = await fetch('/api/save_metadata.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_id: currentDocumentId,
                template_id: currentDocTemplateId,
                metadata: metadata
            })
        });
        const data = await res.json();
        if (data.success) {
            alert('Metadata saved successfully!');
            // Update local values cache
            currentDocMetadataValues = metadata;
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('Error saving metadata.');
    }
}
async function deleteCurrentPage() {
    if (!currentDocumentId || !pdfDoc || selectedPages.length === 0) return;
    
    const total = pdfDoc.numPages;
    const count = selectedPages.length;
    
    if (count >= total) {
        return alert('Cannot delete all pages of a document. Delete the document instead.');
    }
    
    // sort pages for display
    const sortedPages = [...selectedPages].sort((a,b) => a - b);
    const pageText = sortedPages.join(', ');
    
    if (!confirm(`Are you sure you want to delete ${count} page(s): ${pageText}?\nThe extracted page(s) will be sent to the Recycle Bin and this document will be permanently altered (Version history is maintained).`)) {
        return;
    }
    
    try {
        const res = await fetch('/api/delete_page.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                document_id: currentDocumentId,
                page_nums: selectedPages,
                total_pages: total
            })
        });
        
        const data = await res.json();
        if (data.success) {
            alert(`Successfully deleted ${count} page(s).`);
            // Reset selection to page 1 of new document
            selectedPages = [1];
            pageNum = 1;
            
            // Reload the grid
            if (currentFolderId) loadFolder(currentFolderId);
            else if (typeof loadRecycleBin === 'function' && isRecycleBinMode) loadRecycleBin();
            else loadFolder(null);
            
            // Reload the viewer with the new file instead of closing
            const viewerTitle = document.getElementById('viewerTitle').textContent;
            openDocument(data.new_filename, viewerTitle, currentDocumentId);
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('An error occurred while deleting the page(s).');
    }
}

async function handleImportPDF(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (file.type !== 'application/pdf') {
        event.target.value = '';
        return alert('Please select a valid PDF file.');
    }
    
    if (!currentDocumentId || !pdfDoc) {
        event.target.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('pdf', file);
    formData.append('document_id', currentDocumentId);
    formData.append('current_page', pageNum);
    formData.append('total_pages', pdfDoc.numPages);
    
    try {
        const res = await fetch('/api/import_pdf.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        if (data.success) {
            alert('PDF imported successfully!');
            
            // Reload the viewer with the new file instead of closing
            const viewerTitle = document.getElementById('viewerTitle').textContent;
            openDocument(data.new_filename, viewerTitle, currentDocumentId);
            
            // Reload the grid
            if (currentFolderId) loadFolder(currentFolderId);
            else if (typeof loadRecycleBin === 'function' && isRecycleBinMode) loadRecycleBin();
            else loadFolder(null);
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('An error occurred while importing the PDF.');
    }
    
    event.target.value = '';
}

async function loadDocumentVersions(docId) {
    const container = document.getElementById('versionTimeline');
    container.innerHTML = '<p class="text-sm text-slate-400 italic">Loading versions...</p>';

    try {
        const res = await fetch(`/api/get_versions.php?document_id=${docId}`);
        const data = await res.json();
        
        if (data.success) {
            if (data.versions.length === 0) {
                container.innerHTML = '<p class="text-sm text-slate-400 italic">No version history available.</p>';
                return;
            }

            let html = '<div class="space-y-4">';

            data.versions.forEach(v => {
                const date = new Date(v.created_at).toLocaleString();
                const user = v.username || 'System';
                const dlUrl = `/api/view.php?document_id=${docId}&action=download_version&version=${v.version_number}`;

                html += `
                    <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm relative">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mb-1">
                                    Version ${v.version_number}
                                </span>
                                <h5 class="text-sm font-bold text-slate-800">${v.action_type}</h5>
                                <p class="text-xs text-slate-500 mt-1">By ${user} on ${date}</p>
                            </div>
                            <a href="${dlUrl}" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-slate-100 rounded transition-colors" title="Download this version">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            </a>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-sm text-red-500">Failed to load versions.</p>';
        }
    } catch (e) {
        container.innerHTML = '<p class="text-sm text-red-500">Error fetching versions.</p>';
    }
}

function switchSidebarTab(tab) {
    const pMeta = document.getElementById('panel-metadata');
    const pVers = document.getElementById('panel-versions');
    const bMeta = document.getElementById('tabBtn-metadata');
    const bVers = document.getElementById('tabBtn-versions');

    if (tab === 'metadata') {
        pMeta.classList.remove('hidden');
        pMeta.classList.add('flex');
        pVers.classList.remove('flex');
        pVers.classList.add('hidden');
        
        bMeta.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        bMeta.classList.remove('text-slate-500', 'border-transparent');
        bVers.classList.add('text-slate-500', 'border-transparent');
        bVers.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
    } else {
        pVers.classList.remove('hidden');
        pVers.classList.add('flex');
        pMeta.classList.remove('flex');
        pMeta.classList.add('hidden');
        
        bVers.classList.add('text-blue-600', 'border-b-2', 'border-blue-600');
        bVers.classList.remove('text-slate-500', 'border-transparent');
        bMeta.classList.add('text-slate-500', 'border-transparent');
        bMeta.classList.remove('text-blue-600', 'border-b-2', 'border-blue-600');
    }
}
</script>

<!-- Context Menu -->
<div id="contextMenu" class="hidden absolute z-[10000] w-48 bg-white rounded-lg shadow-xl border border-slate-200 py-1">
    <div id="cm-new-folder" class="hidden px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer flex items-center gap-2">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        New Folder
    </div>
    <div id="cm-rename" class="hidden px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer flex items-center gap-2">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
        Rename
    </div>
    <div id="cm-move" class="px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer flex items-center gap-2">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
        Move
    </div>
    <div id="cm-delete" class="hidden px-4 py-2 text-sm text-red-600 hover:bg-red-50 cursor-pointer flex items-center gap-2">
        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        Delete
    </div>
    <div id="cm-security" class="hidden px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer flex items-center gap-2 border-t border-slate-100 mt-1 pt-2">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
        Manage Security
    </div>
</div>

<!-- Move Modal -->
<div id="moveModal" class="hidden fixed inset-0 bg-[#0f172a]/50 backdrop-blur-sm z-[100] flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col max-h-[80vh]">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800" id="moveModalTitle">Move Item</h3>
            <button onclick="document.getElementById('moveModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <p class="text-sm text-slate-600 mb-4">Select destination folder:</p>
            <div id="moveModalTree" class="border border-slate-200 rounded p-2 max-h-64 overflow-y-auto"></div>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3 bg-slate-50">
            <button onclick="document.getElementById('moveModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 font-medium hover:bg-slate-100 rounded transition-colors">Cancel</button>
            <button onclick="confirmMove()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded transition-colors shadow-sm shadow-blue-200">Move Here</button>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div id="permissionsModal" class="hidden fixed inset-0 z-[11000] bg-[#0f172a]/50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 id="permissionsModalTitle" class="text-xl font-bold text-slate-800">Folder Security</h3>
                <p class="text-sm text-slate-500 mt-1">Manage access permissions for this folder.</p>
            </div>
            <button onclick="document.getElementById('permissionsModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <input type="hidden" id="permFolderId">
        
        <div class="mb-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="inheritPermissionsCheckbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-slate-700">Inherit permissions from parent folder</span>
            </label>
        </div>
        
        <div class="flex gap-6 flex-1 min-h-0">
            <!-- Left Side: User List -->
            <div class="w-1/3 border border-slate-200 rounded-lg flex flex-col bg-slate-50 overflow-hidden">
                <!-- Groups Section -->
                <div class="p-2 border-b border-slate-200 bg-white flex justify-between items-center">
                    <h4 class="font-medium text-slate-700 text-sm">Groups</h4>
                    <button onclick="addPermissionGroup()" class="text-xs text-blue-600 hover:text-blue-800 font-medium bg-blue-50 px-2 py-1 rounded">+ Add</button>
                </div>
                <div id="permGroupsList" class="max-h-48 overflow-y-auto p-2 space-y-1 border-b border-slate-200">
                    <!-- Groups injected here -->
                </div>
                
                <!-- Users Section -->
                <div class="p-2 border-b border-slate-200 bg-white flex justify-between items-center">
                    <h4 class="font-medium text-slate-700 text-sm">Users</h4>
                    <button onclick="addPermissionUser()" class="text-xs text-blue-600 hover:text-blue-800 font-medium bg-blue-50 px-2 py-1 rounded">+ Add</button>
                </div>
                <div id="permUsersList" class="flex-1 overflow-y-auto p-2 space-y-1">
                    <!-- Users injected here -->
                </div>
            </div>
            
            <!-- Right Side: Permissions Matrix -->
            <div id="permMatrixContainer" class="w-2/3 hidden flex-col">
                <div class="mb-4 flex items-center gap-3">
                    <label class="font-medium text-slate-700 text-sm whitespace-nowrap">Choose an option:</label>
                    <select id="permScope" onchange="markPermsDirty()" class="flex-1 border border-slate-300 rounded p-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="this_folder">This folder</option>
                        <option value="this_folder_subfolders">This folder, subfolders</option>
                        <option value="this_folder_subfolders_documents">This folder, subfolders and documents</option>
                    </select>
                </div>
                
                <div class="flex-1 border border-slate-200 rounded-lg overflow-y-auto bg-white">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-100 sticky top-0">
                            <tr>
                                <th class="py-2 px-4 text-sm font-semibold text-slate-700 border-b border-slate-200 w-full">Right</th>
                                <th class="py-2 px-4 text-sm font-semibold text-slate-700 border-b border-slate-200 text-center">Allow</th>
                                <th class="py-2 px-4 text-sm font-semibold text-slate-700 border-b border-slate-200 text-center">Deny</th>
                            </tr>
                        </thead>
                        <tbody id="permRightsBody" class="divide-y divide-slate-100 text-sm text-slate-700">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="permEmptyState" class="w-2/3 flex items-center justify-center text-slate-400 text-sm">
                Select a user to view or edit their permissions.
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-200 w-full">
            <button id="removeAccessBtn" onclick="removeSelectedAccess()" class="hidden px-4 py-2 text-red-600 hover:bg-red-50 font-medium text-sm rounded transition-colors mr-auto">Remove Access</button>
            <button onclick="document.getElementById('permissionsModal').classList.add('hidden')" class="px-4 py-2 text-slate-600 font-medium hover:bg-slate-100 text-sm rounded transition-colors">Cancel</button>
            <button onclick="savePermissions()" class="px-6 py-2 bg-blue-600 text-white font-medium hover:bg-blue-700 text-sm rounded shadow-sm transition-colors">Apply Security</button>
        </div>
    </div>
</div>
<!-- Principal Selection Modal -->
<div id="permSelectModal" class="hidden fixed inset-0 z-[12000] bg-[#0f172a]/50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 flex flex-col max-h-[80vh]">
        <div class="flex justify-between items-center mb-4">
            <h3 id="permSelectModalTitle" class="text-xl font-bold text-slate-800">Add Principal</h3>
            <button onclick="document.getElementById('permSelectModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="mb-4">
            <input type="text" id="permSelectFilter" placeholder="Filter..." class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" oninput="filterPermSelect()">
        </div>
        <div id="permSelectList" class="overflow-y-auto flex-1 space-y-1 border border-slate-200 rounded p-2 bg-slate-50">
            <!-- Items injected here -->
        </div>
    </div>
</div>


<!-- Bulk Action Floating Bar -->
<div id="bulkActionBar" class="fixed bottom-8 left-1/2 transform -translate-x-1/2 bg-[#1e293b] text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-4 z-50 transition-all duration-300 translate-y-24 opacity-0 hidden">
    <span id="bulkActionCount" class="font-medium text-sm">0 items selected</span>
    <div class="w-px h-4 bg-slate-600"></div>
    <div id="bulkActionButtons" class="flex items-center gap-2">
        <!-- Buttons injected dynamically -->
    </div>
    <button onclick="clearBulkSelection()" class="ml-2 p-1 text-slate-400 hover:text-white rounded-full transition-colors" title="Clear selection">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
</div>

</body>

<script>
async function openStampsModal() {
    document.getElementById('stampsModal').classList.remove('hidden');
    clearStampForm();
    await loadStamps();
}

async function loadStamps() {
    const res = await fetch('/api/stamps/list.php');
    const data = await res.json();
    const list = document.getElementById('stampsList');
    if (data.success) {
        if (data.stamps.length === 0) {
            list.innerHTML = '<p class="text-sm text-slate-500 p-4 text-center">No stamps found.</p>';
            return;
        }
        list.innerHTML = data.stamps.map(s => {
            const safeName = s.name.replace(/'/g, "\\'");
            const safeText = s.stamp_text.replace(/'/g, "\\'");
            return `
            <div class="p-3 border-b border-slate-200 hover:bg-white group flex flex-col gap-2 transition-colors">
                <div class="flex justify-between items-start">
                    <div class="font-medium text-sm text-slate-800 cursor-pointer flex-1" onclick="editStamp(${s.id}, '${safeName}', '${safeText}', '${s.font}', ${s.font_size}, '${s.color}')">${s.name}</div>
                    <button onclick="deleteStamp(${s.id})" class="text-slate-400 hover:text-red-500 opacity-100 transition-opacity p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
                <button onclick="applyStamp(${s.id}, event)" class="w-full text-xs bg-blue-50 text-blue-700 py-1.5 rounded hover:bg-blue-100 font-medium transition-colors border border-blue-200 shadow-sm">Apply to Selected Pages</button>
            </div>
        `}).join('');
    }
}

function clearStampForm() {
    document.getElementById('stampId').value = '';
    document.getElementById('stampName').value = '';
    document.getElementById('stampText').value = '';
    document.getElementById('stampFont').value = 'Helvetica-Bold';
    document.getElementById('stampSize').value = '36';
    document.getElementById('stampColor').value = '#FF0000';
    document.getElementById('stampFormTitle').innerText = 'Create New Stamp';
}

function editStamp(id, name, text, font, size, color) {
    document.getElementById('stampId').value = id;
    document.getElementById('stampName').value = name;
    document.getElementById('stampText').value = text;
    document.getElementById('stampFont').value = font;
    document.getElementById('stampSize').value = size;
    document.getElementById('stampColor').value = color;
    document.getElementById('stampFormTitle').innerText = 'Edit Stamp';
}

async function saveStamp() {
    const id = document.getElementById('stampId').value;
    const name = document.getElementById('stampName').value;
    const text = document.getElementById('stampText').value;
    const font = document.getElementById('stampFont').value;
    const size = document.getElementById('stampSize').value;
    const color = document.getElementById('stampColor').value;
    
    if (!name || !text) return alert('Name and text are required');
    
    const res = await fetch('/api/stamps/save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id, name, stamp_text: text, font, font_size: size, color})
    });
    const data = await res.json();
    if (data.success) {
        clearStampForm();
        loadStamps();
    } else {
        alert(data.message);
    }
}

async function deleteStamp(id) {
    if (!confirm('Delete this stamp?')) return;
    const res = await fetch('/api/stamps/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.success) {
        loadStamps();
    } else {
        alert(data.message);
    }
}

async function applyStamp(stampId, event) {
    if (selectedPages.length === 0) return alert('No pages selected.');
    
    const btn = event.currentTarget;
    const originalText = btn.innerText;
    btn.innerText = 'Applying...';
    btn.disabled = true;
    
    try {
        const res = await fetch('/api/stamps/save_placement.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                document_id: currentDocumentId,
                stamp_id: stampId,
                page_nums: selectedPages,
                pos_x: 50,
                pos_y: 50
            })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('stampsModal').classList.add('hidden');
            await loadDocumentPlacements(currentDocumentId);
            renderStamps(pageNum);
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('An error occurred applying the stamp.');
    }
    btn.innerText = originalText;
    btn.disabled = false;
}

// === DRAGGABLE STAMPS LOGIC ===
let documentPlacements = [];
let documentAnnotations = [];
let documentWatermark = null;
let activeDragStamp = null;
let dragStartX = 0;
let dragStartY = 0;
let dragStartLeft = 0;
let dragStartTop = 0;

async function loadDocumentPlacements(id) {
    if (!id) return;
    
    // Load watermark
    const wRes = await fetch(`/api/watermarks/get.php?document_id=${id}`);
    const wData = await wRes.json();
    if (wData.success && wData.watermark) {
        documentWatermark = wData.watermark;
    } else {
        documentWatermark = null;
    }
    
    const res = await fetch(`/api/stamps/get_placements.php?document_id=${id}`);
    const data = await res.json();
    if (data.success) {
        documentPlacements = data.placements;
    }
    
    const resA = await fetch(`/api/annotations/get_placements.php?document_id=${id}`);
    const dataA = await resA.json();
    if (dataA.success) {
        documentAnnotations = dataA.placements;
    }
}

function renderStamps(num) {
    const layer = document.getElementById('stampsLayer');
    if (!layer) return;
    layer.innerHTML = '';
    
    layer.className = 'absolute inset-0 pointer-events-none overflow-hidden';
    
    const pagePlacements = documentPlacements.filter(p => parseInt(p.page_num) === parseInt(num));
    
    // Render Watermark
    if (documentWatermark && documentWatermark.is_active) {
        let wmEl;
        if (documentWatermark.image_filename) {
            wmEl = document.createElement('img');
            wmEl.src = '/api/watermarks/view.php?file=' + documentWatermark.image_filename;
            wmEl.className = 'absolute pointer-events-none z-0';
        } else {
            let wmText = documentWatermark.text.replace('%(Id)', currentDocumentId).replace('%(Date)', new Date().toISOString().split('T')[0]).replace('%(User)', 'System');
            wmEl = document.createElement('div');
            wmEl.className = 'absolute pointer-events-none z-0';
            wmEl.innerText = wmText;
            wmEl.style.color = '#000000';
            wmEl.style.fontWeight = 'bold';
            wmEl.style.fontFamily = 'Helvetica, Arial, sans-serif';
            wmEl.style.whiteSpace = 'nowrap';
        }
        
        const hPos = documentWatermark.h_pos;
        const vPos = documentWatermark.v_pos;
        const rot = parseInt(documentWatermark.rotation);
        const opacity = parseInt(documentWatermark.opacity) / 100;
        
        wmEl.style.opacity = opacity;
        
        const sizePct = parseInt(documentWatermark.size_pct);
        
        const offsetX = parseInt(documentWatermark.offset_x) || 0;
        const offsetY = parseInt(documentWatermark.offset_y) || 0;
        
        let x = hPos === 'center' ? 50 : (hPos === 'left' ? Math.max(5, sizePct / 2) : Math.min(95, 100 - sizePct / 2));
        let y = vPos === 'center' ? 50 : (vPos === 'top' ? 10 : 90);
        
        x += offsetX;
        y += offsetY;
        
        wmEl.style.left = `${x}%`;
        wmEl.style.top = `${y}%`;
        
        const containerWidth = document.getElementById('pdfWrapper').offsetWidth;
        const targetWidth = containerWidth * (sizePct / 100);
        
        if (documentWatermark.image_filename) {
            wmEl.style.width = `${targetWidth}px`;
            wmEl.style.transform = `translate(-50%, -50%) rotate(${-rot}deg)`;
            layer.appendChild(wmEl);
        } else {
            wmEl.style.lineHeight = '1';
            wmEl.style.fontSize = '100px';
            wmEl.style.visibility = 'hidden';
            layer.appendChild(wmEl);
            
            const tw = wmEl.offsetWidth;
            const exactFontSize = (targetWidth / Math.max(tw, 1)) * 100;
            
            wmEl.style.fontSize = `${exactFontSize}px`;
            wmEl.style.visibility = 'visible';
            wmEl.style.transform = `translate(-50%, -50%) rotate(${-rot}deg)`;
        }
    }
    
    const canModify = window.currentPermissions ? window.currentPermissions.right_modify : true;
    const canSeeThrough = window.currentPermissions ? window.currentPermissions.right_see_through_redactions : true;

    pagePlacements.forEach(p => {
        const stampEl = document.createElement('div');
        if (canModify) {
            stampEl.className = 'absolute pointer-events-auto cursor-move opacity-80 hover:opacity-100 hover:ring-2 hover:ring-blue-500 rounded p-1 group';
        } else {
            stampEl.className = 'absolute pointer-events-none opacity-80 rounded p-1 group';
        }
        stampEl.style.left = `${p.pos_x}%`;
        stampEl.style.top = `${p.pos_y}%`;
        stampEl.style.transform = 'translate(-50%, -50%)';
        stampEl.style.fontFamily = p.font;
        const scaledSize = parseInt(p.font_size) * scale;
        stampEl.style.fontSize = `${scaledSize}px`;
        stampEl.style.color = p.color;
        stampEl.style.whiteSpace = 'nowrap';
        stampEl.style.fontWeight = p.font.includes('Bold') ? 'bold' : 'normal';
        stampEl.style.userSelect = 'none';
        
        stampEl.innerText = p.stamp_text;
        
        if (canModify) {
            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '×';
            deleteBtn.className = 'absolute -top-3 -right-3 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-sm font-bold opacity-100 transition-opacity z-50';
            deleteBtn.onclick = async (e) => {
                e.stopPropagation();
                if(!confirm('Delete this stamp?')) return;
                const res = await fetch('/api/stamps/delete_placement.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({placement_id: p.id})
                });
                const data = await res.json();
                if(data.success) {
                    documentPlacements = documentPlacements.filter(pl => pl.id !== p.id);
                    renderStamps(num);
                }
            };
            stampEl.appendChild(deleteBtn);
            
            stampEl.onmousedown = (e) => {
                if (e.target === deleteBtn) return;
                activeDragStamp = { el: stampEl, id: p.id, isAnnot: false };
                dragStartX = e.clientX;
                dragStartY = e.clientY;
                dragStartLeft = parseFloat(stampEl.style.left);
                dragStartTop = parseFloat(stampEl.style.top);
                document.addEventListener('mousemove', onDragStamp);
                document.addEventListener('mouseup', onStopDragStamp);
            };
        }
        
        layer.appendChild(stampEl);
    });
    
    const pageAnnotations = documentAnnotations.filter(p => parseInt(p.page_num) === parseInt(num));
    
    pageAnnotations.forEach(a => {
        // Redaction visibility logic: if they can see through it, and can't modify it, hide it entirely!
        if (a.type !== 'highlight' && !canModify && canSeeThrough) {
            return;
        }

        const annotEl = document.createElement('div');
        if (canModify) {
            annotEl.className = 'absolute pointer-events-auto cursor-move hover:ring-2 hover:ring-blue-500 rounded group';
            annotEl.style.resize = 'both';
        } else {
            annotEl.className = 'absolute pointer-events-none rounded group';
        }
        
        annotEl.style.left = `${a.pos_x}%`;
        annotEl.style.top = `${a.pos_y}%`;
        annotEl.style.width = `${a.width}%`;
        annotEl.style.height = `${a.height}%`;
        annotEl.style.backgroundColor = a.color;
        
        if (a.type === 'highlight') {
            annotEl.style.mixBlendMode = 'multiply';
            annotEl.style.opacity = '0.5';
        } else {
            if (canModify) {
                annotEl.style.opacity = '0.8'; // Modifiers see it semi-transparent
            } else {
                annotEl.style.opacity = '1.0'; // Regular users see it solid (if they can't see through)
            }
        }
        
        annotEl.style.overflow = 'hidden';
        
        if (canModify) {
            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '×';
            deleteBtn.className = 'absolute -top-3 -right-3 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-sm font-bold opacity-100 transition-opacity z-50';
            deleteBtn.onclick = async (e) => {
                e.stopPropagation();
                if(!confirm('Delete this annotation?')) return;
                const res = await fetch('/api/annotations/delete_placement.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({placement_id: a.id})
                });
                const data = await res.json();
                if(data.success) {
                    documentAnnotations = documentAnnotations.filter(al => al.id !== a.id);
                    renderStamps(num);
                }
            };
            annotEl.appendChild(deleteBtn);
            
            annotEl.onmousedown = (e) => {
                if (e.target === deleteBtn) return;
                const rect = annotEl.getBoundingClientRect();
                if (e.clientX > rect.right - 20 && e.clientY > rect.bottom - 20) return;
                
                activeDragStamp = { el: annotEl, id: a.id, isAnnot: true };
                dragStartX = e.clientX;
                dragStartY = e.clientY;
                dragStartLeft = parseFloat(annotEl.style.left);
                dragStartTop = parseFloat(annotEl.style.top);
                document.addEventListener('mousemove', onDragStamp);
                document.addEventListener('mouseup', onStopDragStamp);
            };
            
            annotEl.onmouseup = (e) => {
                const rect = layer.getBoundingClientRect();
                const wPx = annotEl.offsetWidth;
                const hPx = annotEl.offsetHeight;
                const wPct = (wPx / rect.width) * 100;
                const hPct = (hPx / rect.height) * 100;
                
                if (Math.abs(wPct - parseFloat(a.width)) > 0.1 || Math.abs(hPct - parseFloat(a.height)) > 0.1) {
                    a.width = wPct;
                    a.height = hPct;
                    
                    fetch('/api/annotations/save_placement.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            document_id: currentDocumentId,
                            placement_id: a.id,
                            pos_x: parseFloat(annotEl.style.left),
                            pos_y: parseFloat(annotEl.style.top),
                            width: wPct,
                            height: hPct
                        })
                    });
                    
                    annotEl.style.width = `${wPct}%`;
                    annotEl.style.height = `${hPct}%`;
                }
            };
        }

        layer.appendChild(annotEl);
    });
}

function onDragStamp(e) {
    if (!activeDragStamp) return;
    e.preventDefault();
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;
    const layer = document.getElementById('stampsLayer');
    const rect = layer.getBoundingClientRect();
    
    let newLeft = dragStartLeft + (dx / rect.width) * 100;
    let newTop = dragStartTop + (dy / rect.height) * 100;
    
    newLeft = Math.max(0, Math.min(100, newLeft));
    newTop = Math.max(0, Math.min(100, newTop));
    
    activeDragStamp.el.style.left = `${newLeft}%`;
    activeDragStamp.el.style.top = `${newTop}%`;
}

async function onStopDragStamp(e) {
    if (!activeDragStamp) return;
    document.removeEventListener('mousemove', onDragStamp);
    document.removeEventListener('mouseup', onStopDragStamp);
    
    const newLeft = parseFloat(activeDragStamp.el.style.left);
    const newTop = parseFloat(activeDragStamp.el.style.top);
    const id = activeDragStamp.id;
    
    const p = activeDragStamp.isAnnot 
        ? documentAnnotations.find(pl => pl.id === id)
        : documentPlacements.find(pl => pl.id === id);
        
    if(p) {
        p.pos_x = newLeft;
        p.pos_y = newTop;
    }
    
    const endpoint = activeDragStamp.isAnnot ? '/api/annotations/save_placement.php' : '/api/stamps/save_placement.php';
    const body = {
        document_id: currentDocumentId,
        placement_id: id,
        pos_x: newLeft,
        pos_y: newTop
    };
    if (activeDragStamp.isAnnot && p) {
        body.width = p.width;
        body.height = p.height;
    }
    
    activeDragStamp = null;
    
    await fetch(endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    });
}

function openMarkupModal() {
    document.getElementById('markupModal').classList.remove('hidden');
}

async function applyAnnotation(event) {
    if (selectedPages.length === 0) return alert('No pages selected.');
    
    const type = document.getElementById('markupType').value;
    const color = document.getElementById('markupColor').value;
    
    const btn = event.currentTarget;
    const originalText = btn.innerText;
    btn.innerText = 'Applying...';
    btn.disabled = true;
    
    try {
        const res = await fetch('/api/annotations/save_placement.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                document_id: currentDocumentId,
                type: type,
                color: color,
                page_nums: selectedPages,
                pos_x: 20,
                pos_y: 20,
                width: 20,
                height: 5
            })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('markupModal').classList.add('hidden');
            await loadDocumentPlacements(currentDocumentId);
            renderStamps(pageNum);
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('Error applying annotation.');
    }
    btn.innerText = originalText;
    btn.disabled = false;
}

document.getElementById('exportStampedBtn').addEventListener('click', (e) => {
    e.preventDefault();
    if (!currentDocumentId) return;
    window.location.href = `/api/export_stamped.php?document_id=${currentDocumentId}`;
});

document.getElementById('menuExportBtn').addEventListener('click', (e) => {
    e.preventDefault();
    if (!currentDocumentId) return;
    window.location.href = `/api/export_stamped.php?document_id=${currentDocumentId}`;
});

function renameWorkspaceDocument(e) {
    if (e) e.preventDefault();
    enableInlineRename();
}

function enableInlineRename() {
    if (!currentDocumentId) return;
    const titleEl = document.getElementById('viewerTitle');
    const inputEl = document.getElementById('viewerRenameInput');
    inputEl.value = titleEl.textContent;
    document.getElementById('viewerRenameContainer').classList.remove('hidden');
    document.getElementById('viewerRenameContainer').classList.add('flex');
    inputEl.focus();
    inputEl.select();
}

function cancelInlineRename() {
    document.getElementById('viewerRenameContainer').classList.add('hidden');
    document.getElementById('viewerRenameContainer').classList.remove('flex');
}

function saveInlineRename() {
    if (!currentDocumentId) return;
    const newName = document.getElementById('viewerRenameInput').value.trim();
    const currentTitle = document.getElementById('viewerTitle').textContent;
    
    if (newName && newName !== currentTitle) {
        fetch('/api/rename_document.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentDocumentId, title: newName })
        }).then(res => res.json()).then(data => {
            if (data.success) {
                document.getElementById('viewerTitle').textContent = newName;
                cancelInlineRename();
                if (typeof loadFolder === 'function') {
                    if (currentFolderId === 'recycle_bin') {
                        loadRecycleBin();
                    } else if (currentFolderId === 'search') {
                        if (typeof executeSearch === 'function') executeSearch(false);
                    } else {
                        loadFolder(currentFolderId, true);
                    }
                }
            } else {
                alert(data.message || 'Error renaming document');
            }
        }).catch(err => {
            alert('Error renaming document');
        });
    } else {
        cancelInlineRename();
    }
}

function printDocument(e) {
    if (e) e.preventDefault();
    if (!currentDocumentId) return;
    
    const printUrl = `/api/export_stamped.php?document_id=${currentDocumentId}&action=inline`;
    
    // In modern Chrome, hidden iframes loading PDFs use the internal PDF extension
    // which triggers a cross-origin SecurityError on .print().
    // The most robust fallback is to open the PDF in a new tab.
    window.open(printUrl, '_blank');
}

document.getElementById('printBtn').addEventListener('click', printDocument);
document.getElementById('menuPrintBtn').addEventListener('click', printDocument);

document.getElementById('markupType')?.addEventListener('change', function() {
    if (this.value === 'highlight') {
        document.getElementById('markupColor').value = '#FFFF00';
    } else {
        document.getElementById('markupColor').value = '#000000';
    }
});

function openWatermarkModal() {
    if (documentWatermark) {
        if (documentWatermark.image_filename) {
            document.getElementById('wmType').value = 'image';
            document.getElementById('wmExistingImage').value = documentWatermark.image_filename;
            document.getElementById('wmImageActive').classList.remove('hidden');
            document.getElementById('wmImageFilenameText').innerText = documentWatermark.image_filename;
            document.getElementById('wmImageUploadGroup').classList.add('hidden');
        } else {
            document.getElementById('wmType').value = 'text';
            document.getElementById('wmExistingImage').value = '';
            document.getElementById('wmImageActive').classList.add('hidden');
            document.getElementById('wmImageUploadGroup').classList.remove('hidden');
            document.getElementById('wmText').value = documentWatermark.text || '';
        }
        document.getElementById('wmHPos').value = documentWatermark.h_pos;
        document.getElementById('wmVPos').value = documentWatermark.v_pos;
        document.getElementById('wmOffsetX').value = documentWatermark.offset_x || 0;
        document.getElementById('wmOffsetY').value = documentWatermark.offset_y || 0;
        document.getElementById('wmRotation').value = documentWatermark.rotation;
        document.getElementById('wmSize').value = documentWatermark.size_pct;
        document.getElementById('wmOpacity').value = documentWatermark.opacity;
    } else {
        document.getElementById('wmType').value = 'text';
        document.getElementById('wmText').value = '';
        document.getElementById('wmExistingImage').value = '';
        document.getElementById('wmImageActive').classList.add('hidden');
        document.getElementById('wmImageUploadGroup').classList.remove('hidden');
        document.getElementById('wmImage').value = '';
        document.getElementById('wmHPos').value = 'center';
        document.getElementById('wmVPos').value = 'center';
        document.getElementById('wmOffsetX').value = '0';
        document.getElementById('wmOffsetY').value = '0';
        document.getElementById('wmRotation').value = '45';
        document.getElementById('wmSize').value = '50';
        document.getElementById('wmOpacity').value = '20';
    }
    toggleWmType();
    document.getElementById('watermarkModal').classList.remove('hidden');
}

function toggleWmType() {
    const type = document.getElementById('wmType').value;
    if (type === 'image') {
        document.getElementById('wmTextInput').classList.add('hidden');
        document.getElementById('wmImageInput').classList.remove('hidden');
    } else {
        document.getElementById('wmTextInput').classList.remove('hidden');
        document.getElementById('wmImageInput').classList.add('hidden');
    }
}

async function saveWatermark() {
    const type = document.getElementById('wmType').value;
    const text = document.getElementById('wmText').value;
    const fileInput = document.getElementById('wmImage');
    const existing = document.getElementById('wmExistingImage').value;
    
    if (type === 'text' && !text) {
        return deleteWatermark();
    }
    if (type === 'image' && !existing && fileInput.files.length === 0) {
        alert('Please upload an image for the watermark');
        return;
    }
    
    const h_pos = document.getElementById('wmHPos').value;
    const v_pos = document.getElementById('wmVPos').value;
    const offset_x = document.getElementById('wmOffsetX').value;
    const offset_y = document.getElementById('wmOffsetY').value;
    const rotation = document.getElementById('wmRotation').value;
    const size_pct = document.getElementById('wmSize').value;
    const opacity = document.getElementById('wmOpacity').value;
    
    const formData = new FormData();
    formData.append('document_id', currentDocumentId || '');
    formData.append('type', type);
    formData.append('text', text);
    formData.append('h_pos', h_pos);
    formData.append('v_pos', v_pos);
    formData.append('offset_x', offset_x);
    formData.append('offset_y', offset_y);
    formData.append('rotation', rotation);
    formData.append('size_pct', size_pct);
    formData.append('opacity', opacity);
    
    if (type === 'image' && fileInput.files.length > 0) {
        formData.append('image', fileInput.files[0]);
    }
    
    const res = await fetch('/api/watermarks/save.php', {
        method: 'POST',
        body: formData
    });
    
    let data;
    try {
        data = await res.json();
    } catch (e) {
        alert('Server returned an invalid response. See console for details.');
        console.error('Failed to parse JSON:', await res.text().catch(() => ''));
        return;
    }
    
    if (res.ok && data.success) {
        document.getElementById('watermarkModal').classList.add('hidden');
        await loadDocumentPlacements(currentDocumentId);
        renderPage(pageNum);
    } else {
        alert('Error saving watermark: ' + (data.message || 'Unknown error'));
    }
}

async function deleteWatermark() {
    const res = await fetch('/api/watermarks/save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            document_id: currentDocumentId,
            text: ''
        })
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('watermarkModal').classList.add('hidden');
        await loadDocumentPlacements(currentDocumentId);
        renderPage(pageNum);
    }
}

function onSearchTypeChange() {
    const type = document.getElementById('searchType').value;
    const nameCont = document.getElementById('searchNameContainer');
    const dateCont = document.getElementById('searchDateContainer');
    const tplCont = document.getElementById('searchTemplateContainer');
    const fieldsCont = document.getElementById('searchDynamicFieldsContainer');
    
    nameCont.classList.add('hidden');
    dateCont.classList.add('hidden');
    tplCont.classList.add('hidden');
    fieldsCont.classList.add('hidden');
    
    if (type === 'name' || type === 'added_by' || type === 'modified_by' || type === 'deleted_by') {
        nameCont.classList.remove('hidden');
        const label = document.getElementById('searchNameLabel');
        const input = document.getElementById('searchNameInput');
        if (type === 'name') {
            label.innerText = 'Name (Doc/Folder)';
            input.placeholder = 'Enter name to search...';
        } else if (type === 'added_by') {
            label.innerText = 'Added By Username';
            input.placeholder = 'Enter username...';
        } else if (type === 'modified_by') {
            label.innerText = 'Last Modified By Username';
            input.placeholder = 'Enter username...';
        } else if (type === 'deleted_by') {
            label.innerText = 'Deleted By Username';
            input.placeholder = 'Enter username...';
        }
    } else if (type === 'deleted_at') {
        dateCont.classList.remove('hidden');
    } else if (type === 'template') {
        tplCont.classList.remove('hidden');
        onSearchTemplateChange();
    }
}

function toggleRecycleBinSearchOptions() {
    const isChecked = document.getElementById('searchRecycleBin').checked;
    document.querySelectorAll('.recycle-bin-only').forEach(el => {
        el.classList.toggle('hidden', !isChecked);
    });
    const type = document.getElementById('searchType').value;
    if (!isChecked && (type === 'deleted_by' || type === 'deleted_at')) {
        document.getElementById('searchType').value = 'name';
        onSearchTypeChange();
    }
}

function onSearchTemplateChange() {
    const tplId = document.getElementById('searchTemplateSelect').value;
    const fieldsCont = document.getElementById('searchDynamicFieldsContainer');
    fieldsCont.innerHTML = '';
    
    if (!tplId) {
        fieldsCont.classList.add('hidden');
        return;
    }
    
    const fields = window.templateFieldsByTpl[tplId] || [];
    if (fields.length === 0) {
        fieldsCont.innerHTML = '<p class="text-sm text-slate-500 col-span-full">No fields exist for this template.</p>';
    } else {
        fields.forEach(f => {
            let inputHtml = '';
            if (f.type === 'dropdown' && f.options) {
                let opts = '';
                try {
                    let parsed = JSON.parse(f.options);
                    opts = Array.isArray(parsed) ? parsed : parsed.split(',');
                } catch(e) {
                    opts = f.options.split(',');
                }
                inputHtml = `<select class="search-field-input w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" data-field-id="${f.id}" onkeypress="if(event.key === 'Enter') executeSearch()">
                                <option value="">-- Any --</option>
                                ${opts.map(o => `<option value="${o.trim()}">${o.trim()}</option>`).join('')}
                             </select>`;
            } else if (f.type === 'date') {
                inputHtml = `<input type="date" class="search-field-input w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" data-field-id="${f.id}" onkeypress="if(event.key === 'Enter') executeSearch()">`;
            } else if (f.type === 'number') {
                inputHtml = `<input type="number" class="search-field-input w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" data-field-id="${f.id}" placeholder="Enter number..." onkeypress="if(event.key === 'Enter') executeSearch()">`;
            } else {
                inputHtml = `<input type="text" class="search-field-input w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" data-field-id="${f.id}" placeholder="Search text..." onkeypress="if(event.key === 'Enter') executeSearch()">`;
            }
            
            fieldsCont.innerHTML += `
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">${f.name}</label>
                    ${inputHtml}
                </div>
            `;
        });
    }
    fieldsCont.classList.remove('hidden');
}

function sortSearch(col) {
    if (searchSortCol === col) {
        searchSortDir = searchSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        searchSortCol = col;
        searchSortDir = 'asc';
    }
    localStorage.setItem('searchSortCol', searchSortCol);
    localStorage.setItem('searchSortDir', searchSortDir);
    if (currentSearchData) {
        renderSearchResults();
    }
}

async function executeSearch(resetPage = true) {
    if (resetPage) currentPage = 1;
    const type = document.getElementById('searchType').value;
    const grid = document.getElementById('searchResultsGrid');
    
    grid.innerHTML = '<p class="text-slate-400 text-sm col-span-full">Searching...</p>';
    
    let payload = { 
        type, 
        page: currentPage, 
        is_deleted: document.getElementById('searchRecycleBin').checked 
    };
    if (type === 'name' || type === 'added_by' || type === 'modified_by' || type === 'deleted_by') {
        payload.query = document.getElementById('searchNameInput').value;
    } else if (type === 'deleted_at') {
        payload.query = document.getElementById('searchDateInput').value;
    } else if (type === 'template') {
        payload.template_id = document.getElementById('searchTemplateSelect').value;
        if (!payload.template_id) {
            grid.innerHTML = '<p class="text-red-500 text-sm col-span-full">Please select a template.</p>';
            return;
        }
        
        let fields = {};
        document.querySelectorAll('.search-field-input').forEach(input => {
            if (input.value.trim() !== '') {
                fields[input.getAttribute('data-field-id')] = input.value;
            }
        });
        payload.fields = fields;
    }
    
    try {
        const res = await fetch('/api/search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            currentSearchData = data;
            renderSearchResults();
        } else {
            grid.innerHTML = `<p class="text-red-500 text-sm col-span-full">${data.message}</p>`;
        }
    } catch(e) {
        console.error(e);
        grid.innerHTML = '<p class="text-red-500 text-sm col-span-full">Network error during search: ' + e.message + '</p>';
    }
}

function renderSearchResults() {
    const data = currentSearchData;
    if (!data) return;
    
    const grid = document.getElementById('searchResultsGrid');
    grid.innerHTML = '';
    
    renderPagination(data.total_pages || 1, currentPage, 'searchPaginationControls', 'executeSearch(false)');
    
    if ((!data.folders || data.folders.length === 0) && (!data.documents || data.documents.length === 0)) {
        grid.innerHTML = '<p class="text-slate-400 text-sm col-span-full">No results found.</p>';
        return;
    }
    
    // sorting
    const sortFnSearch = (a, b) => {
        let valA, valB;
        if (searchSortCol === 'name') {
            valA = (a.title || a.name || '').toLowerCase();
            valB = (b.title || b.name || '').toLowerCase();
        } else if (searchSortCol === 'size') {
            valA = a.file_size || 0;
            valB = b.file_size || 0;
        } else if (searchSortCol === 'added') {
            valA = new Date(a.created_at).getTime();
            valB = new Date(b.created_at).getTime();
        } else if (searchSortCol === 'added_by') {
            valA = (a.added_by_name || '').toLowerCase();
            valB = (b.added_by_name || '').toLowerCase();
        } else if (searchSortCol === 'modified_by') {
            valA = (a.updated_by_name || '').toLowerCase();
            valB = (b.updated_by_name || '').toLowerCase();
        } else if (searchSortCol === 'modified_at') {
            valA = new Date(a.updated_at || a.created_at).getTime();
            valB = new Date(b.updated_at || b.created_at).getTime();
        } else if (searchSortCol === 'location') {
            valA = (a.full_path || '').toLowerCase();
            valB = (b.full_path || '').toLowerCase();
        } else {
            valA = (a.title || a.name || '').toLowerCase();
            valB = (b.title || b.name || '').toLowerCase();
        }
        
        if (valA < valB) return searchSortDir === 'asc' ? -1 : 1;
        if (valA > valB) return searchSortDir === 'asc' ? 1 : -1;
        return 0;
    };
    
    if (data.folders) data.folders.sort(sortFnSearch);
    if (data.documents) data.documents.sort(sortFnSearch);
    
    const isRecycleBinSearch = document.getElementById('searchRecycleBin').checked;
    grid.className = 'w-full mb-4 flex flex-col flex-1 min-h-0';
    
    const thHTML = (col, label, cls, sortable = true) => {
        const isSort = searchSortCol === col;
        const arrow = isSort ? (searchSortDir === 'asc' ? '↑' : '↓') : '';
        const arrowClass = isSort ? 'text-blue-500 font-bold' : 'text-slate-300 group-hover:text-slate-400';
        const cursorClass = sortable ? 'cursor-pointer' : 'cursor-default';
        const onClick = sortable ? `onclick="sortSearch('${col}')"` : '';
        const hoverClass = sortable ? 'hover:text-slate-700' : '';
        
        const storedWidth = getColumnWidth(col);
        const widthStyle = storedWidth ? `width: ${storedWidth}px;` : '';
        
        return `<th class="relative ${cls} ${cursorClass} ${hoverClass} group select-none" style="${widthStyle}">
                    <div class="flex items-center gap-1 justify-${cls.includes('text-right') ? 'end' : 'start'}" ${onClick}>
                        ${label} ${sortable ? `<span class="text-xs ${arrowClass}">${arrow || '↕'}</span>` : ''}
                    </div>
                    <div class="absolute top-0 right-0 w-2 h-full cursor-col-resize bg-transparent hover:bg-blue-300 transition-colors z-10" onmousedown="initResize(event, '${col}', this)"></div>
                </th>`;
    };
    
    let theadHTML = `
        <tr class="border-b border-slate-200 text-sm font-medium text-slate-500">
            <th class="pb-3 pl-3 w-10"><input type="checkbox" onchange="toggleAllSelection(this)" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"></th>
            <th class="pb-3 pl-2 w-16">Type</th>
            ${thHTML('name', 'Name', 'pb-3 w-1/4')}
            ${searchTableColumns.includes('location') ? thHTML('location', isRecycleBinSearch ? 'Original Path' : 'Location (Path)', 'pb-3 w-1/4') : ''}
    `;
    
    if (isRecycleBinSearch) {
        theadHTML += `<th class="pb-3 w-1/5">Date Deleted</th><th class="pb-3 w-1/5">Deleted By</th>`;
    }
    
    if (searchTableColumns.includes('size')) {
        theadHTML += thHTML('size', 'Size', 'pb-3 text-right pr-4');
    }
    if (searchTableColumns.includes('added')) {
        theadHTML += thHTML('added', 'Date Added', 'pb-3 hidden md:table-cell w-32');
    }
    if (searchTableColumns.includes('added_by')) {
        theadHTML += thHTML('added_by', 'Added By', 'pb-3 hidden md:table-cell');
    }
    if (searchTableColumns.includes('modified_by')) {
        theadHTML += thHTML('modified_by', 'Last Modified By', 'pb-3 hidden md:table-cell');
    }
    if (searchTableColumns.includes('modified_at')) {
        theadHTML += thHTML('modified_at', 'Last Modified At', 'pb-3 hidden md:table-cell w-32');
    }
    
    theadHTML += `<th class="pb-3 w-20"></th></tr>`;
    
    const tableMinWidth = 400 + (searchTableColumns.length * 150) + (isRecycleBinSearch ? 300 : 0);
    
    grid.innerHTML = `
        <div class="overflow-auto h-full pb-4 w-full">
            <table class="w-full text-left border-collapse whitespace-nowrap table-fixed" style="min-width: ${tableMinWidth}px;">
                <thead>${theadHTML}</thead>
                <tbody class="text-sm"></tbody>
            </table>
        </div>
    `;

    const tbody = grid.querySelector('tbody');
    let rowsHTML = '';

    if (data.folders) {
        data.folders.forEach(folder => {
            const chevron = isRecycleBinSearch
                ? `<button onclick="expandRecycleBinFolder(event, ${folder.id}, this)" class="p-0.5 text-slate-400 hover:text-slate-700 transition-colors" title="Expand folder contents">
                       <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                   </button>`
                : '';
            const actionButtons = isRecycleBinSearch
                ? `<button onclick="promptRestoreFolder(${folder.id}, event)" class="p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded transition-all mr-1 opacity-100" title="Restore Folder"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></button>
                   ${window.currentUserRole === 'admin' ? `<button onclick="permanentDeleteItem(${folder.id}, 'folder', event)" class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-all opacity-100" title="Delete Permanently"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>` : ''}`
                : `<button onclick="event.stopPropagation(); loadFolder(${folder.id})" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-all opacity-100 mr-1" title="Open Folder"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg></button>
                   <button onclick="deleteFolder(${folder.id}, '${folder.name.replace(/'/g, "\\'")}', event)" class="p-1.5 text-slate-300 hover:text-red-600 hover:bg-red-50 rounded transition-all opacity-100" title="Delete Folder"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>`;

            let tdHTML = `
                <td class="py-3 pl-3"><input type="checkbox" value="${folder.id}" onclick="event.stopPropagation()" onchange="toggleSelection(this, 'folder', ${folder.id})" class="row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"></td>
                <td class="py-3 pl-2">
                    <div class="flex items-center gap-1">
                        ${chevron}
                        <svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                    </div>
                </td>
                <td class="py-3 font-medium text-slate-700 truncate" title="${folder.name}">${folder.name}</td>
                ${searchTableColumns.includes('location') ? `<td class="py-3 text-slate-500 break-all whitespace-normal" title="${folder.full_path || 'Root'}">${folder.full_path || 'Root'}</td>` : ''}
            `;
            
            if (isRecycleBinSearch) {
                tdHTML += `<td class="py-3 text-slate-500">${formatDateTimeWrap(folder.deleted_at)}</td><td class="py-3 text-slate-500 truncate">${folder.deleted_by_name || 'Unknown'}</td>`;
            }
            if (searchTableColumns.includes('size')) {
                tdHTML += `<td class="py-3 text-right pr-4 text-slate-500">--</td>`;
            }
            if (searchTableColumns.includes('added')) {
                tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${formatDateTimeWrap(folder.created_at)}</td>`;
            }
            if (searchTableColumns.includes('added_by')) {
                tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${folder.added_by_name || 'System'}</td>`;
            }
            if (searchTableColumns.includes('modified_by')) {
                tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${folder.updated_by_name || 'System'}</td>`;
            }
            if (searchTableColumns.includes('modified_at')) {
                tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${formatDateTimeWrap(folder.updated_at)}</td>`;
            }
            
            tdHTML += `<td class="py-3 pr-2 text-right">${actionButtons}</td>`;

            rowsHTML += `
                <tr data-folder-id="${folder.id}" data-rb-id="${folder.id}" data-rb-type="folder" onclick="handleRowClick(event, this)" ${isRecycleBinSearch ? '' : `ondblclick="loadFolder(${folder.id})"`} class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-colors group">
                    ${tdHTML}
                </tr>
            `;
        });
    }

    if (data.documents) {
        data.documents.forEach(doc => {
            const actionButtons = isRecycleBinSearch
                ? `<button onclick="promptRestoreDocument(${doc.id}, event)" class="p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded transition-all mr-1 opacity-100" title="Restore Document"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></button>
                   ${window.currentUserRole === 'admin' ? `<button onclick="permanentDeleteItem(${doc.id}, 'document', event)" class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-all opacity-100" title="Delete Permanently"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>` : ''}`
                : `<button onclick="openFolderLocation(${doc.folder_id || 'null'}, ${doc.id}, event)" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-all opacity-100 mr-1" title="Open Folder Location"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg></button>
                   <button onclick="deleteDocument(${doc.id}, '${doc.title.replace(/'/g, "\\'")}', event)" class="p-1.5 text-slate-300 hover:text-red-600 hover:bg-red-50 rounded transition-all opacity-100" title="Delete Document"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>`;

            let lockIcon = doc.checked_out_by ? '<span class="ml-1 text-red-500" title="Locked">🔒</span>' : '';

            let tdHTML = `
                <td class="py-3 pl-3"><input type="checkbox" value="${doc.id}" onclick="event.stopPropagation()" onchange="toggleSelection(this, 'document', ${doc.id})" class="row-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"></td>
                <td class="py-3 pl-2"><svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"></path></svg></td>
                <td class="py-3 font-medium text-slate-700 truncate" title="${doc.title}">${doc.title}${lockIcon}</td>
                ${searchTableColumns.includes('location') ? `<td class="py-3 text-slate-500 break-all whitespace-normal" title="${doc.folder_path || 'Root'}">${doc.folder_path || 'Root'}</td>` : ''}
            `;

            if (isRecycleBinSearch) {
                tdHTML += `<td class="py-3 text-slate-500">${formatDateTimeWrap(doc.deleted_at)}</td><td class="py-3 text-slate-500 truncate">${doc.deleted_by_name || 'Unknown'}</td>`;
            }
            if (searchTableColumns.includes('size')) tdHTML += `<td class="py-3 text-right pr-4 text-slate-500">${formatBytes(doc.file_size)}</td>`;
            if (searchTableColumns.includes('added')) tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${formatDateTimeWrap(doc.created_at)}</td>`;
            if (searchTableColumns.includes('added_by')) tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${doc.added_by_name || 'System'}</td>`;
            if (searchTableColumns.includes('modified_by')) tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${doc.updated_by_name || 'System'}</td>`;
            if (searchTableColumns.includes('modified_at')) tdHTML += `<td class="py-3 hidden md:table-cell text-slate-500">${formatDateTimeWrap(doc.updated_at)}</td>`;
            
            tdHTML += `<td class="py-3 pr-2 text-right">${actionButtons}</td>`;

            rowsHTML += `
                <tr data-doc-id="${doc.id}" onclick="handleRowClick(event, this)" ${isRecycleBinSearch ? '' : `ondblclick="openDocument('${doc.filename}', '${doc.title.replace(/'/g, "\\'")}', ${doc.id})"`} class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-colors group">
                    ${tdHTML}
                </tr>
            `;
        });
    }

    tbody.innerHTML = rowsHTML;
}

async function loadUsers() {
    const tbody = document.getElementById('usersGridBody');
    tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-slate-500">Loading users...</td></tr>';
    
    try {
        const response = await fetch('/api/users/list.php');
        const data = await response.json();
        
        if (data.success) {
            tbody.innerHTML = '';
            if (data.users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-slate-500">No users found.</td></tr>';
                return;
            }
            
            data.users.forEach(user => {
                const date = new Date(user.created_at).toLocaleString();
                tbody.innerHTML += `
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="py-3 px-4 font-medium text-slate-700">${user.username}</td>
                        <td class="py-3 px-4 text-slate-600"><span class="px-2 py-1 bg-slate-100 rounded text-xs font-semibold uppercase tracking-wider">${user.role}</span></td>
                        <td class="py-3 px-4 text-slate-500">${date}</td>
                        <td class="py-3 px-4 text-right">
                            <button onclick='openUserModal(${JSON.stringify(user).replace(/'/g, "&apos;")})' class="text-blue-600 hover:text-blue-800 font-medium text-sm mr-3 opacity-100 transition-opacity">Edit</button>
                            <button onclick="deleteUser(${user.id}, '${user.username}')" class="text-red-600 hover:text-red-800 font-medium text-sm opacity-100 transition-opacity">Delete</button>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="py-4 text-center text-red-500">${data.message}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-red-500">Network error loading users.</td></tr>';
    }
}

function openUserModal(user = null) {
    document.getElementById('userModal').classList.remove('hidden');
    document.getElementById('userId').value = user ? user.id : '';
    document.getElementById('userUsername').value = user ? user.username : '';
    document.getElementById('userRole').value = user ? user.role : 'user';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').placeholder = user ? 'Leave blank to keep unchanged' : 'Required';
    document.getElementById('userModalTitle').innerText = user ? 'Edit User' : 'Create User';
}

async function saveUser() {
    const id = document.getElementById('userId').value;
    const username = document.getElementById('userUsername').value.trim();
    const role = document.getElementById('userRole').value;
    const password = document.getElementById('userPassword').value;
    
    if (!username) {
        alert("Username is required.");
        return;
    }
    if (!id && !password) {
        alert("Password is required for new users.");
        return;
    }
    
    const payload = { username, role, password };
    if (id) payload.id = id;
    
    try {
        const endpoint = id ? '/api/users/update.php' : '/api/users/create.php';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('userModal').classList.add('hidden');
            loadUsers();
        } else {
            alert(data.message || 'Error saving user.');
        }
    } catch (e) {
        alert('Network error while saving user.');
    }
}

async function deleteUser(id, username) {
    if (!confirm(`Are you sure you want to permanently delete user "${username}"?`)) return;
    
    try {
        const response = await fetch('/api/users/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();
        
        if (data.success) {
            loadUsers();
        } else {
            alert(data.message || 'Error deleting user.');
        }
    } catch (e) {
        alert('Network error while deleting user.');
    }
}

// GROUPS LOGIC
async function loadGroups() {
    const tbody = document.getElementById('groupsGridBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="3" class="py-4 text-center text-slate-500">Loading groups...</td></tr>';
    
    try {
        const response = await fetch('/api/groups/list.php');
        const data = await response.json();
        
        if (data.success) {
            tbody.innerHTML = '';
            if (data.groups.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="py-4 text-center text-slate-500">No groups found.</td></tr>';
                return;
            }
            
            data.groups.forEach(group => {
                tbody.innerHTML += `
                    <tr class="hover:bg-slate-50 transition-colors group-row">
                        <td class="py-3 px-4 font-medium text-slate-700">${group.name}</td>
                        <td class="py-3 px-4 text-slate-600">${group.user_count} members</td>
                        <td class="py-3 px-4 text-right">
                            <button onclick='openGroupModal(${JSON.stringify(group).replace(/'/g, "&apos;")})' class="text-blue-600 hover:text-blue-800 font-medium text-sm mr-3 opacity-100 transition-opacity">Edit</button>
                            <button onclick="deleteGroup(${group.id}, '${group.name}')" class="text-red-600 hover:text-red-800 font-medium text-sm opacity-100 transition-opacity">Delete</button>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="py-4 text-center text-red-500">${data.message}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="3" class="py-4 text-center text-red-500">Network error loading groups.</td></tr>';
    }
}

async function openGroupModal(group = null) {
    document.getElementById('groupModal').classList.remove('hidden');
    document.getElementById('groupId').value = group ? group.id : '';
    document.getElementById('groupName').value = group ? group.name : '';
    document.getElementById('groupModalTitle').innerText = group ? 'Edit Group' : 'Create Group';
    
    const usersPanel = document.getElementById('groupUsersPanel');
    if (group) {
        usersPanel.classList.remove('hidden', 'opacity-50', 'pointer-events-none');
        await loadGroupUsers(group.id);
    } else {
        usersPanel.classList.add('opacity-50', 'pointer-events-none');
        usersPanel.classList.remove('hidden');
        document.getElementById('groupUsersIn').innerHTML = '';
        document.getElementById('groupUsersOut').innerHTML = '';
    }
}

async function saveGroup() {
    const id = document.getElementById('groupId').value;
    const name = document.getElementById('groupName').value.trim();
    
    if (!name) {
        alert("Group name is required.");
        return;
    }
    
    try {
        const response = await fetch('/api/groups/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name })
        });
        const data = await response.json();
        
        if (data.success) {
            if (!id) document.getElementById('groupModal').classList.add('hidden');
            loadGroups();
            if (!id) alert("Group created successfully! Re-open it to manage users.");
        } else {
            alert(data.message || 'Error saving group.');
        }
    } catch (e) {
        alert('Network error while saving group.');
    }
}

async function deleteGroup(id, name) {
    if (!confirm(`Are you sure you want to permanently delete group "${name}"? This will also remove any permissions assigned to this group.`)) return;
    
    try {
        const response = await fetch('/api/groups/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();
        
        if (data.success) {
            loadGroups();
        } else {
            alert(data.message || 'Error deleting group.');
        }
    } catch (e) {
        alert('Network error while deleting group.');
    }
}

async function loadGroupUsers(groupId) {
    document.getElementById('groupUsersIn').innerHTML = '<p class="text-xs p-2 text-slate-400">Loading...</p>';
    document.getElementById('groupUsersOut').innerHTML = '<p class="text-xs p-2 text-slate-400">Loading...</p>';
    
    try {
        const response = await fetch(`/api/groups/users.php?group_id=${groupId}`);
        const data = await response.json();
        
        if (data.success) {
            const inDiv = document.getElementById('groupUsersIn');
            inDiv.innerHTML = '';
            if (data.in_group.length === 0) inDiv.innerHTML = '<p class="text-xs p-2 text-slate-400">No users in group.</p>';
            data.in_group.forEach(u => {
                inDiv.innerHTML += `
                    <div onclick="toggleGroupUser(${groupId}, ${u.id}, 'remove')" data-username="${u.username}" class="group-user-item px-3 py-2 text-sm text-slate-700 hover:bg-red-50 hover:text-red-700 cursor-pointer rounded border border-transparent hover:border-red-200 flex justify-between items-center group/btn">
                        ${u.username}
                        <svg class="w-4 h-4 opacity-0 group-hover/btn:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>`;
            });
            
            const outDiv = document.getElementById('groupUsersOut');
            outDiv.innerHTML = '';
            if (data.out_group.length === 0) outDiv.innerHTML = '<p class="text-xs p-2 text-slate-400">All users are in this group.</p>';
            data.out_group.forEach(u => {
                outDiv.innerHTML += `
                    <div onclick="toggleGroupUser(${groupId}, ${u.id}, 'add')" data-username="${u.username}" class="group-user-item px-3 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700 cursor-pointer rounded border border-transparent hover:border-blue-200 flex justify-between items-center group/btn">
                        ${u.username}
                        <svg class="w-4 h-4 opacity-0 group-hover/btn:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </div>`;
            });
            
            // Re-apply filters if they were already typed in
            const filterAv = document.getElementById('filterAvailable').value;
            if (filterAv) filterUsers('groupUsersOut', filterAv);
            const filterIn = document.getElementById('filterInGroup').value;
            if (filterIn) filterUsers('groupUsersIn', filterIn);
        } else {
            document.getElementById('groupUsersIn').innerHTML = `<p class="text-xs p-2 text-red-500">${data.message || 'Error loading users.'}</p>`;
            document.getElementById('groupUsersOut').innerHTML = `<p class="text-xs p-2 text-red-500">${data.message || 'Error loading users.'}</p>`;
        }
    } catch (e) {
        console.error("Error loading group users", e);
        document.getElementById('groupUsersIn').innerHTML = '<p class="text-xs p-2 text-red-500">Network error.</p>';
        document.getElementById('groupUsersOut').innerHTML = '<p class="text-xs p-2 text-red-500">Network error.</p>';
    }
}

function filterUsers(containerId, query) {
    query = query.toLowerCase();
    const container = document.getElementById(containerId);
    if (!container) return;
    const items = container.querySelectorAll('.group-user-item');
    items.forEach(item => {
        const username = item.getAttribute('data-username').toLowerCase();
        if (username.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

async function toggleGroupUser(groupId, userId, action) {
    try {
        const response = await fetch('/api/groups/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ group_id: groupId, user_id: userId, action })
        });
        const data = await response.json();
        
        if (data.success) {
            await loadGroupUsers(groupId);
            loadGroups(); // Update the members count in the background
        } else {
            alert(data.message || `Error ${action}ing user.`);
        }
    } catch (e) {
        alert('Network error.');
    }
}

// PERMISSIONS LOGIC
let permsData = [];
let permsGroupData = [];
let permsUsers = [];
let permsGroups = [];
let activePermUser = null;
let activePermGroup = null;
let permsDirty = false;

async function openPermissionsModal(folderId, folderName) {
    if (folderId === null || folderId === 'null') {
        folderId = 0;
    }
    
    document.getElementById('permissionsModal').classList.remove('hidden');
    document.getElementById('permissionsModalTitle').innerText = `Security: ${folderName}`;
    document.getElementById('permFolderId').value = folderId;
    
    document.getElementById('permUsersList').innerHTML = '<p class="text-xs text-slate-400 p-2 text-center">Loading...</p>';
    document.getElementById('permGroupsList').innerHTML = '<p class="text-xs text-slate-400 p-2 text-center">Loading...</p>';
    document.getElementById('permMatrixContainer').classList.add('hidden');
    document.getElementById('removeAccessBtn').classList.add('hidden');
    document.getElementById('permEmptyState').classList.remove('hidden');
    
    try {
        const res = await fetch(`/api/permissions/get.php?folder_id=${folderId}`);
        const data = await res.json();
        
        if (data.success) {
            permsData = data.permissions;
            permsGroupData = data.group_permissions || [];
            permsUsers = data.users; // all system users
            permsGroups = data.groups || []; // all system groups
            document.getElementById('inheritPermissionsCheckbox').checked = data.inherit_permissions;
            activePermUser = null;
            activePermGroup = null;
            renderPermUsersList();
            renderPermGroupsList();
            permsDirty = false;
        } else {
            alert(data.message || 'Error loading permissions');
        }
    } catch (e) {
        alert('Network error');
    }
}

function renderPermUsersList() {
    const list = document.getElementById('permUsersList');
    list.innerHTML = '';
    
    if (permsData.length === 0) {
        list.innerHTML = '<p class="text-xs text-slate-400 p-2 text-center">No users have specific access.</p>';
        return;
    }
    
    permsData.forEach((p, idx) => {
        const isActive = activePermUser === p.user_id;
        const bg = isActive ? 'bg-blue-50 border-blue-200' : 'bg-white border-transparent hover:bg-slate-50 hover:border-slate-200';
        const txt = isActive ? 'text-blue-700 font-semibold' : 'text-slate-700';
        list.innerHTML += `
            <div onclick="selectPermUser(${p.user_id})" class="p-2 border rounded cursor-pointer transition-colors ${bg}">
                <div class="${txt} text-sm flex justify-between items-center">
                    <span>${p.username}</span>
                    <svg class="w-4 h-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
            </div>
        `;
    });
}

function renderPermGroupsList() {
    const list = document.getElementById('permGroupsList');
    list.innerHTML = '';
    
    if (permsGroupData.length === 0) {
        list.innerHTML = '<p class="text-xs text-slate-400 p-2 text-center">No groups have specific access.</p>';
        return;
    }
    
    permsGroupData.forEach((p, idx) => {
        const isActive = activePermGroup === p.group_id;
        const bg = isActive ? 'bg-blue-50 border-blue-200' : 'bg-white border-transparent hover:bg-slate-50 hover:border-slate-200';
        const txt = isActive ? 'text-blue-700 font-semibold' : 'text-slate-700';
        list.innerHTML += `
            <div onclick="selectPermGroup(${p.group_id})" class="p-2 border rounded cursor-pointer transition-colors ${bg}">
                <div class="${txt} text-sm flex justify-between items-center">
                    <span>${p.group_name}</span>
                    <svg class="w-4 h-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
        `;
    });
}

function selectPermUser(userId) {
    activePermUser = userId;
    activePermGroup = null; // deselect group
    renderPermUsersList();
    renderPermGroupsList();
    
    const p = permsData.find(x => x.user_id == userId);
    if (!p) return;
    
    document.getElementById('permEmptyState').classList.add('hidden');
    document.getElementById('permMatrixContainer').classList.remove('hidden');
    document.getElementById('removeAccessBtn').classList.remove('hidden');
    document.getElementById('permScope').value = p.scope || 'this_folder_subfolders_documents';
    
    renderRightsMatrix(p);
}

function selectPermGroup(groupId) {
    activePermGroup = groupId;
    activePermUser = null; // deselect user
    renderPermUsersList();
    renderPermGroupsList();
    
    const p = permsGroupData.find(x => x.group_id == groupId);
    if (!p) return;
    
    document.getElementById('permEmptyState').classList.add('hidden');
    document.getElementById('permMatrixContainer').classList.remove('hidden');
    document.getElementById('removeAccessBtn').classList.remove('hidden');
    document.getElementById('permScope').value = p.scope || 'this_folder_subfolders_documents';
    
    renderRightsMatrix(p);
}

let permSelectMode = 'user'; // 'user' or 'group'
let permSelectItems = []; // holds {id, name}

function addPermissionUser() {
    const unassigned = permsUsers.filter(u => !permsData.find(p => p.user_id == u.id));
    if (unassigned.length === 0) {
        alert("All users have already been added.");
        return;
    }
    permSelectMode = 'user';
    permSelectItems = unassigned.map(u => ({ id: u.id, name: u.username }));
    openPermSelectModal('Add User');
}

function addPermissionGroup() {
    const unassigned = permsGroups.filter(g => !permsGroupData.find(p => p.group_id == g.id));
    if (unassigned.length === 0) {
        alert("All groups have already been added.");
        return;
    }
    permSelectMode = 'group';
    permSelectItems = unassigned.map(g => ({ id: g.id, name: g.name }));
    openPermSelectModal('Add Group');
}

function openPermSelectModal(title) {
    document.getElementById('permSelectModalTitle').innerText = title;
    document.getElementById('permSelectFilter').value = '';
    document.getElementById('permSelectModal').classList.remove('hidden');
    renderPermSelectList();
}

function renderPermSelectList(filter = '') {
    const list = document.getElementById('permSelectList');
    list.innerHTML = '';
    const filtered = permSelectItems.filter(item => item.name.toLowerCase().includes(filter.toLowerCase()));
    
    if (filtered.length === 0) {
        list.innerHTML = '<p class="text-xs text-slate-500 p-2 text-center">No matches found.</p>';
        return;
    }
    
    filtered.forEach(item => {
        list.innerHTML += `
            <div onclick="selectPermPrincipal(${item.id}, '${item.name.replace(/'/g, "\\'")}')" class="p-2 cursor-pointer hover:bg-blue-50 text-slate-700 hover:text-blue-700 text-sm rounded border border-transparent hover:border-blue-200 transition-colors">
                ${item.name}
            </div>
        `;
    });
}

function filterPermSelect() {
    renderPermSelectList(document.getElementById('permSelectFilter').value);
}

function selectPermPrincipal(id, name) {
    if (permSelectMode === 'user') {
        permsData.push({
            user_id: id,
            username: name,
            scope: 'this_folder_subfolders_documents',
            right_view: null, right_add: null, right_modify: null, right_delete: null,
            right_see_through_redactions: null, right_manage_security: null
        });
        markPermsDirty();
        selectPermUser(id);
    } else {
        permsGroupData.push({
            group_id: id,
            group_name: name,
            scope: 'this_folder_subfolders_documents',
            right_view: null, right_add: null, right_modify: null, right_delete: null,
            right_see_through_redactions: null, right_manage_security: null
        });
        markPermsDirty();
        selectPermGroup(id);
    }
    document.getElementById('permSelectModal').classList.add('hidden');
}

function removeSelectedAccess() {
    if (activePermUser) {
        permsData = permsData.filter(x => x.user_id != activePermUser);
        activePermUser = null;
        renderPermUsersList();
    } else if (activePermGroup) {
        permsGroupData = permsGroupData.filter(x => x.group_id != activePermGroup);
        activePermGroup = null;
        renderPermGroupsList();
    } else {
        return;
    }
    
    markPermsDirty();
    document.getElementById('permMatrixContainer').classList.add('hidden');
    document.getElementById('permEmptyState').classList.remove('hidden');
    document.getElementById('removeAccessBtn').classList.add('hidden');
}

function renderRightsMatrix(p) {
    const tbody = document.getElementById('permRightsBody');
    const rights = [
        { key: 'right_view', label: 'View' },
        { key: 'right_add', label: 'Add' },
        { key: 'right_modify', label: 'Modify' },
        { key: 'right_delete', label: 'Delete' },
        { key: 'right_see_through_redactions', label: '&nbsp;&nbsp;&nbsp;&nbsp;See Through Redactions' },
        { key: 'right_manage_security', label: 'Manage Security' },
    ];
    
    tbody.innerHTML = rights.map(r => {
        const val = p[r.key];
        const allowChecked = val === true ? 'checked' : '';
        const denyChecked = val === false ? 'checked' : '';
        return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-2.5 px-4"><div class="flex items-center gap-2 text-slate-700">${r.key !== 'right_see_through_redactions' ? '<svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>' : ''} ${r.label}</div></td>
                <td class="py-2.5 px-4 text-center"><input type="checkbox" ${allowChecked} onclick="updateRight('${r.key}', true)" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"></td>
                <td class="py-2.5 px-4 text-center"><input type="checkbox" ${denyChecked} onclick="updateRight('${r.key}', false)" class="w-4 h-4 rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer"></td>
            </tr>
        `;
    }).join('');
}

function updateRight(key, isAllow) {
    let p = null;
    if (activePermUser) p = permsData.find(x => x.user_id == activePermUser);
    else if (activePermGroup) p = permsGroupData.find(x => x.group_id == activePermGroup);
    
    if (!p) return;
    
    const currentVal = p[key];
    if (isAllow) {
        p[key] = currentVal === true ? null : true;
    } else {
        p[key] = currentVal === false ? null : false;
    }
    
    markPermsDirty();
    renderRightsMatrix(p);
}

function markPermsDirty() {
    permsDirty = true;
    if (activePermUser) {
        const p = permsData.find(x => x.user_id == activePermUser);
        if (p) p.scope = document.getElementById('permScope').value;
    } else if (activePermGroup) {
        const p = permsGroupData.find(x => x.group_id == activePermGroup);
        if (p) p.scope = document.getElementById('permScope').value;
    }
}

async function savePermissions() {
    const folder_id = document.getElementById('permFolderId').value;
    const inherit_permissions = document.getElementById('inheritPermissionsCheckbox').checked;
    
    // update current active user/group just in case
    markPermsDirty();
    
    try {
        const response = await fetch('/api/permissions/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ folder_id, inherit_permissions, permissions: permsData, group_permissions: permsGroupData })
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('permissionsModal').classList.add('hidden');
        } else {
            alert(data.message || 'Error saving permissions');
        }
    } catch (e) {
        alert('Network error while saving permissions.');
    }
}

window.addEventListener('beforeunload', function (e) {
    if (typeof currentDocumentId !== 'undefined' && currentDocumentId) {
        navigator.sendBeacon('/api/checkin_document.php', JSON.stringify({ document_id: currentDocumentId }));
    }
});
async function saveSecuritySettings() {
    const minLength = document.getElementById('settingMinPasswordLength').value;
    const formData = new FormData();
    formData.append('min_password_length', minLength);
    
    try {
        const response = await fetch('/api/settings.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            alert('Security settings saved successfully.');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Network error while saving settings.');
    }
}

async function saveBrandingSettings() {
    const fileInput = document.getElementById('settingLogoFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Please select a logo file first.');
        return;
    }
    
    const formData = new FormData();
    formData.append('logo', fileInput.files[0]);
    
    try {
        const response = await fetch('/api/settings.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            alert('Branding settings saved successfully.');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Network error while saving settings.');
    }
}
</script>
</body>
</html>
