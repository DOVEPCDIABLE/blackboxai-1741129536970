<?php
// User Transaction History View
?>
<div class="max-w-7xl mx-auto mt-10">
    <h2 class="text-2xl font-bold">Transaction History</h2>
    
    <div class="mt-6">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800">
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($transaction['type']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">$<?php echo number_format($transaction['amount'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($transaction['status']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"><?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <nav class="flex justify-between">
                <div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
                </div>
                <div>
                    <a href="?page=<?php echo max(1, $currentPage - 1); ?>" class="btn-secondary">Previous</a>
                    <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>" class="btn-secondary">Next</a>
                </div>
            </nav>
        </div>
    </div>
</div>
