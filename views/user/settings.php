<?php
// User Settings View
?>
<div class="max-w-7xl mx-auto mt-10">
    <h2 class="text-2xl font-bold">Account Settings</h2>
    
    <form action="/settings/update" method="POST" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="mb-4">
            <label for="theme" class="form-label">Theme Preference</label>
            <select name="theme" id="theme" class="form-select">
                <option value="light" <?php echo $user['theme_preference'] === 'light' ? 'selected' : ''; ?>>Light</option>
                <option value="dark" <?php echo $user['theme_preference'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
            </select>
        </div>

        <h3 class="text-lg font-semibold">Notification Preferences</h3>
        <div class="mb-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="email_notifications" <?php echo $notificationPreferences['email'] ? 'checked' : ''; ?> class="form-checkbox">
                <span class="ml-2">Email Notifications</span>
            </label>
        </div>
        <div class="mb-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="push_notifications" <?php echo $notificationPreferences['push'] ? 'checked' : ''; ?> class="form-checkbox">
                <span class="ml-2">Push Notifications</span>
            </label>
        </div>
        <div class="mb-4">
            <label class="inline-flex items-center">
                <input type="checkbox" name="in_app_notifications" <?php echo $notificationPreferences['in_app'] ? 'checked' : ''; ?> class="form-checkbox">
                <span class="ml-2">In-App Notifications</span>
            </label>
        </div>

        <button type="submit" class="btn-primary w-full mt-4">Update Settings</button>
    </form>

    <div class="mt-6">
        <h3 class="text-lg font-semibold">Change Password</h3>
        <form action="/settings/change-password" method="POST" class="mt-4 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            
            <div class="mb-4">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" name="current_password" id="current_password" required class="form-input" placeholder="Enter your current password">
            </div>
            
            <div class="mb-4">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" name="new_password" id="new_password" required class="form-input" placeholder="Enter your new password">
            </div>
            
            <div class="mb-4">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required class="form-input" placeholder="Confirm your new password">
            </div>
            
            <button type="submit" class="btn-primary w-full mt-4">Change Password</button>
        </form>
    </div>
</div>
