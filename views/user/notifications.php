<?php
// User Notifications View
?>
<div class="max-w-7xl mx-auto mt-10">
    <h2 class="text-2xl font-bold">Notifications</h2>
    
    <div class="mt-6">
        <?php if (empty($notifications)): ?>
            <p class="text-gray-500">No notifications available.</p>
        <?php else: ?>
            <ul class="space-y-4">
                <?php foreach ($notifications as $notification): ?>
                    <li class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                        <h3 class="font-semibold"><?php echo htmlspecialchars($notification['title']); ?></h3>
                        <p class="text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo date('Y-m-d H:i:s', strtotime($notification['created_at'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
