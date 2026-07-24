// =================================================================
// Custom Javascript File (main.js)
// Simple, beginner-friendly client-side interactions and validation.
// =================================================================

document.addEventListener('DOMContentLoaded', function() {

    // toggle the navigation menu on mobile view
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const hamburgerIcon = document.getElementById('hamburger');
    hamburgerIcon.addEventListener('click', function() {
        navMenu.classList.toggle('active');
        // Change the icon based on the menu state
        if (navMenu.classList.contains('active')) {
            hamburgerIcon.classList.remove('bx-menu-right');
            hamburgerIcon.classList.add('bx-x');
        } else {
            hamburgerIcon.classList.remove('bx-x');
            hamburgerIcon.classList.add('bx-menu-right');
        }
    });

    
    // 1. Password Match Validation (Client-Side)
    const registerForm = document.querySelector('.auth-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                e.preventDefault(); // Stop form submission
                alert("Error: Passwords do not match! Please verify and re-enter.");
            }
        });
    }

    // 2. Deletion Confirmation Box
    // Adding class 'confirm-delete' to any button will trigger a prompt
    const deleteButtons = document.querySelectorAll('.confirm-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-message') || "Are you absolutely sure you want to delete this item? This action is permanent and cannot be undone.";
            if (!confirm(message)) {
                e.preventDefault(); // Stop the link navigation or form action if user cancels
            }
        });
    });

    // 3. Dynamic Profile/Item Picture Preview
    // Provides feedback when a user selects an image file
    const fileInput = document.querySelector('.file-input-styled');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Check size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert("Warning: This image is larger than 2MB. Please select a smaller file.");
                    this.value = ''; // Reset input
                    return;
                }
                
                // Show selected name in console for confirmation
                console.log("Selected file: " + file.name + " (" + (file.size / 1024).toFixed(1) + " KB)");
            }
        });
    }

    // 4. Smooth Fade-in effects for alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            alert.style.opacity = '1';
        }, 100);
    });
});
