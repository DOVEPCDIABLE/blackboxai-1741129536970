<?php
// 2FA Verification View
?>
<div class="max-w-md mx-auto mt-10">
    <h2 class="text-2xl font-bold text-center">Verify Two-Factor Authentication</h2>
    <form action="/2fa/verify" method="POST" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="mb-4">
            <label for="code" class="form-label">Verification Code</label>
            <input type="text" name="code" id="code" required class="form-input" placeholder="Enter the code from your authenticator app">
        </div>
        <button type="submit" class="btn-primary w-full mt-4">Verify</button>
    </form>
</div>
