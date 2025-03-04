<?php
// Login View
?>
<div class="max-w-md mx-auto mt-10">
    <h2 class="text-2xl font-bold text-center">Login</h2>
    <form action="/login" method="POST" class="mt-6 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        
        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" required class="form-input" placeholder="Enter your email">
        </div>
        
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" required class="form-input" placeholder="Enter your password">
        </div>
        
        <div class="flex items-center justify-between">
            <a href="/forgot-password" class="text-sm text-primary-600 hover:underline">Forgot Password?</a>
        </div>
        
        <button type="submit" class="btn-primary w-full mt-4">Login</button>
    </form>
    
    <div class="mt-4 text-center">
        <p>Don't have an account? <a href="/register" class="text-primary-600 hover:underline">Register</a></p>
    </div>
</div>
