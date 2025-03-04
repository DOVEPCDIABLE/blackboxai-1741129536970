<?php
// Header Layout
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <header class="bg-gray-800 text-white p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="/" class="text-xl font-bold"><?php echo APP_NAME; ?></a>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="/dashboard" class="hover:underline">Dashboard</a></li>
                    <li><a href="/profile" class="hover:underline">Profile</a></li>
                    <li><a href="/settings" class="hover:underline">Settings</a></li>
                    <li><a href="/logout" class="hover:underline">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="py-6">
