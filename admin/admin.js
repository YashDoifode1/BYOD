/**
 * Admin Panel JavaScript
 */

// Confirm before destructive actions
document.addEventListener('DOMContentLoaded', function() {
    // Confirm deletions
    const deleteForms = document.querySelectorAll('form[data-confirm]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Password visibility toggle
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Auto-format dates
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value && input.hasAttribute('data-default-today')) {
            const today = new Date().toISOString().split('T')[0];
            input.value = today;
        }
    });
});

// Initialize DataTables with common options
function initDataTable(tableId, options = {}) {
    const defaultOptions = {
        responsive: true,
        dom: '<"top"lf>rt<"bottom"ip>',
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search...'
        },
        pageLength: 25,
        order: [[0, 'desc']]
    };
    
    const mergedOptions = {...defaultOptions, ...options};
    return $('#' + tableId).DataTable(mergedOptions);
}