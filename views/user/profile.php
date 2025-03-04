<?php
// User Profile View
?>
<div class="max-w-7xl mx-auto mt-10">
    <h2 class="text-2xl font-bold">My Profile</h2>
    
    <form action="/profile/update" method="POST" enctype="multipart/form-data" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="mb-4">
            <label for="name" class="form-label">Name</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="form-input" placeholder="Enter your name">
        </div>
        
        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-input" placeholder="Enter your email" readonly>
        </div>
        
        <div class="mb-4">
            <label for="phone" class="form-label">Phone (optional)</label>
            <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="form-input" placeholder="Enter your phone number">
        </div>
        
        <div class="mb-4">
            <label for="country" class="form-label">Country (optional)</label>
            <input type="text" name="country" id="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" class="form-input" placeholder="Enter your country">
        </div>
        
        <div class="mb-4">
            <label for="profile_picture" class="form-label">Profile Picture (optional)</label>
            <input type="file" name="profile_picture" id="profile_picture" class="form-input">
        </div>
        
        <button type="submit" class="btn-primary w-full mt-4">Update Profile</button>
    </form>

    <div class="mt-6">
        <h3 class="text-lg font-semibold">Bank Accounts</h3>
        <ul class="mt-4">
            <?php foreach ($bankAccounts as $account): ?>
                <li class="mb-2">
                    <span class="font-medium"><?php echo htmlspecialchars($account['bank_name']); ?></span> - 
                    <span><?php echo htmlspecialchars($account['account_number']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
