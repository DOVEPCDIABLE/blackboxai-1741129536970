// App Configuration
const config = {
    apiUrl: window.location.origin,
    maxFileSize: 5 * 1024 * 1024, // 5MB
    allowedFileTypes: ['image/jpeg', 'image/png', 'image/gif'],
    notificationInterval: 60000, // 1 minute
};

// Theme Management
const ThemeManager = {
    init() {
        this.themeToggleBtn = document.getElementById('theme-toggle');
        if (this.themeToggleBtn) {
            this.themeToggleBtn.addEventListener('click', () => this.toggleTheme());
        }
        this.applyTheme();
    },

    toggleTheme() {
        const isDark = document.documentElement.classList.contains('dark');
        this.setTheme(isDark ? 'light' : 'dark');
        this.saveThemePreference(isDark ? 'light' : 'dark');
    },

    setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    },

    async saveThemePreference(theme) {
        try {
            await fetch(`${config.apiUrl}/settings/theme`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ theme })
            });
        } catch (error) {
            console.error('Failed to save theme preference:', error);
        }
    },

    applyTheme() {
        const theme = localStorage.getItem('theme') || 'light';
        this.setTheme(theme);
    }
};

// Notification System
const NotificationManager = {
    init() {
        this.notificationBtn = document.getElementById('notifications-menu-button');
        this.notificationCount = document.getElementById('notification-count');
        
        if (this.notificationBtn) {
            this.initializeNotifications();
            this.setupPushNotifications();
        }
    },

    async initializeNotifications() {
        this.updateNotificationCount();
        setInterval(() => this.updateNotificationCount(), config.notificationInterval);
    },

    async updateNotificationCount() {
        try {
            const response = await fetch(`${config.apiUrl}/notifications/count`);
            const data = await response.json();
            
            if (data.count > 0) {
                this.notificationCount.textContent = data.count;
                this.notificationCount.classList.remove('hidden');
            } else {
                this.notificationCount.classList.add('hidden');
            }
        } catch (error) {
            console.error('Failed to update notification count:', error);
        }
    },

    async setupPushNotifications() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: 'YOUR_PUBLIC_VAPID_KEY'
                    });

                    await this.sendSubscriptionToServer(subscription);
                }
            } catch (error) {
                console.error('Failed to setup push notifications:', error);
            }
        }
    },

    async sendSubscriptionToServer(subscription) {
        try {
            await fetch(`${config.apiUrl}/api/push-token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(subscription)
            });
        } catch (error) {
            console.error('Failed to send push subscription to server:', error);
        }
    }
};

// File Upload Handler
const FileUploadManager = {
    init() {
        this.setupFileUploads();
    },

    setupFileUploads() {
        const fileInputs = document.querySelectorAll('.file-upload input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => this.handleFileSelect(e));
        });
    },

    handleFileSelect(event) {
        const files = Array.from(event.target.files);
        const uploadContainer = event.target.closest('.file-upload');
        const previewContainer = uploadContainer.querySelector('.file-preview');
        
        if (!previewContainer) return;

        // Clear previous previews
        previewContainer.innerHTML = '';

        files.forEach(file => {
            if (!this.validateFile(file)) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = this.createPreviewElement(e.target.result, file);
                previewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    },

    validateFile(file) {
        if (file.size > config.maxFileSize) {
            alert('File size exceeds the limit');
            return false;
        }

        if (!config.allowedFileTypes.includes(file.type)) {
            alert('File type not supported');
            return false;
        }

        return true;
    },

    createPreviewElement(src, file) {
        const div = document.createElement('div');
        div.className = 'relative inline-block mr-2 mb-2';
        
        const img = document.createElement('img');
        img.src = src;
        img.className = 'h-20 w-20 object-cover rounded';
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 text-xs';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = () => div.remove();
        
        div.appendChild(img);
        div.appendChild(removeBtn);
        
        return div;
    }
};

// Form Validation
const FormValidator = {
    init() {
        this.setupFormValidation();
    },

    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.validateForm(e));
        });
    },

    validateForm(event) {
        const form = event.target;
        let isValid = true;

        // Clear previous errors
        form.querySelectorAll('.error-message').forEach(el => el.remove());

        // Validate required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                this.showError(field, 'This field is required');
                isValid = false;
            }
        });

        // Validate email fields
        form.querySelectorAll('[type="email"]').forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                this.showError(field, 'Please enter a valid email address');
                isValid = false;
            }
        });

        if (!isValid) {
            event.preventDefault();
        }
    },

    showError(field, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-red-500 text-sm mt-1';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    },

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
};

// Mobile Menu
const MobileMenu = {
    init() {
        this.mobileMenuBtn = document.getElementById('mobile-menu-button');
        this.mobileMenu = document.getElementById('mobile-menu');
        
        if (this.mobileMenuBtn && this.mobileMenu) {
            this.mobileMenuBtn.addEventListener('click', () => this.toggleMenu());
        }
    },

    toggleMenu() {
        this.mobileMenu.classList.toggle('hidden');
    }
};

// Flash Messages
const FlashMessage = {
    init() {
        this.setupAutoHide();
    },

    setupAutoHide() {
        const flashMessage = document.getElementById('flash-message');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.classList.add('opacity-0');
                setTimeout(() => flashMessage.remove(), 300);
            }, 5000);
        }
    }
};

// Initialize all components when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    NotificationManager.init();
    FileUploadManager.init();
    FormValidator.init();
    MobileMenu.init();
    FlashMessage.init();
});

// Handle AJAX requests
const ajax = {
    async get(url) {
        try {
            const response = await fetch(url);
            return await response.json();
        } catch (error) {
            console.error('GET request failed:', error);
            throw error;
        }
    },

    async post(url, data) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            console.error('POST request failed:', error);
            throw error;
        }
    }
};

// Export modules for use in other scripts
window.app = {
    config,
    ThemeManager,
    NotificationManager,
    FileUploadManager,
    FormValidator,
    MobileMenu,
    FlashMessage,
    ajax
};
