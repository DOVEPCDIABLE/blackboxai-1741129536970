<?php
// Registration View
?>
<div class="max-w-md mx-auto mt-10">
    <h2 class="text-2xl font-bold text-center">Register</h2>
    <form action="/register" method="POST" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="mb-4">
            <label for="name" class="form-label">Name</label>
            <input type="text" name="name" id="name" required class="form-input" placeholder="Enter your name">
        </div>
        
        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" required class="form-input" placeholder="Enter your email">
        </div>
        
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" required class="form-input" placeholder="Enter your password">
        </div>
        
        <div class="mb-4">
            <label for="password_confirm" class="form-label">Confirm Password</label>
            <input type="password" name="password_confirm" id="password_confirm" required class="form-input" placeholder="Confirm your password">
        </div>
        
        <div class="mb-4">
            <label for="referral_code" class="form-label">Referral Code (optional)</label>
            <input type="text" name="referral_code" id="referral_code" class="form-input" placeholder="Enter referral code">
        </div>
        
        <button type="submit" class="btn-primary w-full mt-4">Register</button>
    </form>
    
    <div class="mt-4 text-center">
        <p>Already have an account? <a href="/login" class="text-primary-600 hover:underline">Login</a></p>
    </div>
</div>
