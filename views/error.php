<?php
// Error Handler View
?>
<div class="max-w-md mx-auto mt-10">
    <h2 class="text-2xl font-bold text-center text-red-600">Error</h2>
    <p class="mt-4 text-center text-gray-600 dark:text-gray-300">
        <?php echo htmlspecialchars($errorMessage); ?>
    </p>
    <div class="mt-6 text-center">
        <a href="/" class="btn-primary">Go to Home</a>
    </div>
</div>
