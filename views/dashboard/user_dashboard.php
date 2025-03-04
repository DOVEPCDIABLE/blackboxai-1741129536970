<?php
// User Dashboard View
?>
<div class="max-w-7xl mx-auto mt-10">
    <h2 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
    
    <div class="mt-6">
        <h3 class="text-lg font-semibold">Wallet Balance: $<?php echo number_format($walletBalance, 2); ?></h3>
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-semibold">Recent Transactions</h3>
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
                <?php foreach ($recentTransactions as $transaction): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($transaction['type']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">$<?php echo number_format($transaction['amount'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"><?php echo htmlspecialchars($transaction['status']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"><?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-semibold">Referral Statistics</h3>
        <p>Total Referrals: <?php echo $referralStats['total_referrals']; ?></p>
        <p>Active Referrals: <?php echo $referralStats['active_referrals']; ?></p>
        <p>Total Bonus Earned: $<?php echo number_format($referralStats['total_bonus'], 2); ?></p>
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-semibold">Transaction Statistics</h3>
        <p>Total Transactions: <?php echo $transactionStats['total_transactions']; ?></p>
        <p>Completed Transactions: <?php echo $transactionStats['completed_transactions']; ?></p>
        <p>Pending Transactions: <?php echo $transactionStats['pending_transactions']; ?></p>
        <p>Total Volume: $<?php echo number_format($transactionStats['total_volume'], 2); ?></p>
    </div>
</div>
