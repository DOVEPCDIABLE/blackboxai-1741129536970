<?php
// 2FA Setup View
?>
<div class="max-w-md mx-auto mt-10">
    <h2 class="text-2xl font-bold text-center">Setup Two-Factor Authentication</h2>
    <div class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <p class="text-center">Scan the QR code below with your authenticator app:</p>
        <div class="flex justify-center mt-4">
            <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code" class="w-48 h-48">
        </div>
        <p class="text-center mt-4">Or enter this secret key: <strong><?php echo htmlspecialchars($secret); ?></strong></p>
    </div>

    <form action="/2fa/verify" method="POST" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="mb-4">
            <label for="code" class="form-label">Verification Code</label>
            <input type="text" name="code" id="code" required class="form-input" placeholder="Enter the code from your authenticator app">
        </div>
        <button type="submit" class="btn-primary w-full mt-4">Verify</button>
    </form>
</div>
