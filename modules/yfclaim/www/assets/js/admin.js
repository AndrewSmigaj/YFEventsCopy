/**
 * YFClaim Admin JavaScript
 * Common functionality for admin interface
 */

// Utility functions
const YFClaimAdmin = {
    
    // Initialize admin interface
    init: function() {
        this.setupModals();
        this.setupForms();
        this.setupTables();
        this.setupConfirmations();
    },
    
    // Modal functionality
    setupModals: function() {
        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        
        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    },
    
    // Form enhancements
    setupForms: function() {
        // Auto-submit status changes
        document.querySelectorAll('select[onchange*="form.submit"]').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value) {
                    this.form.submit();
                }
            });
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    this.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    },
    
    // Table enhancements
    setupTables: function() {
        // Sortable headers (basic implementation)
        document.querySelectorAll('th[data-sortable]').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                // Basic sort implementation would go here
                console.log('Sort by:', this.textContent);
            });
        });
        
        // Row highlighting
        document.querySelectorAll('table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    },
    
    // Confirmation dialogs
    setupConfirmations: function() {
        document.querySelectorAll('[onclick*="confirm"]').forEach(element => {
            element.addEventListener('click', function(e) {
                const confirmText = this.getAttribute('onclick').match(/confirm\(['"]([^'"]+)['"]\)/);
                if (confirmText && !confirm(confirmText[1])) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    },
    
    // Show modal
    showModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    },
    
    // Close modal
    closeModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    },
    
    // Show message
    showMessage: function(message, type = 'message') {
        const container = document.querySelector('.container');
        if (container) {
            const messageDiv = document.createElement('div');
            messageDiv.className = type;
            messageDiv.textContent = message;
            
            // Insert at the top of container
            container.insertBefore(messageDiv, container.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    },
    
    // AJAX helper
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = Object.assign(defaults, options);
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                this.showMessage('An error occurred. Please try again.', 'error');
                throw error;
            });
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    },
    
    // Format date
    formatDate: function(dateString, options = {}) {
        const defaults = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };
        
        const config = Object.assign(defaults, options);
        
        return new Intl.DateTimeFormat('en-US', config).format(new Date(dateString));
    },
    
    // Debounce function for search inputs
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Setup live search
    setupLiveSearch: function(inputSelector, callback) {
        const input = document.querySelector(inputSelector);
        if (input) {
            const debouncedCallback = this.debounce(callback, 300);
            input.addEventListener('input', debouncedCallback);
        }
    },
    
    // Copy to clipboard
    copyToClipboard: function(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.showMessage('Copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy: ', err);
            this.showMessage('Failed to copy to clipboard', 'error');
        });
    },
    
    // Toggle element visibility
    toggle: function(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = element.style.display === 'none' ? '' : 'none';
        }
    },
    
    // Smooth scroll to element
    scrollTo: function(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }
};

// Common modal functions for backward compatibility
function showCreateModal() {
    YFClaimAdmin.showModal('createModal');
}

function closeModal() {
    document.querySelectorAll('.modal.active').forEach(modal => {
        modal.classList.remove('active');
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    YFClaimAdmin.init();
    
    // Setup auto-refresh for certain pages (optional)
    if (window.location.pathname.includes('/admin/') && 
        (window.location.pathname.includes('offers') || window.location.pathname.includes('dashboard'))) {
        
        // Auto-refresh every 5 minutes for dashboard and offers
        setTimeout(() => {
            if (document.hidden === false) { // Only if page is visible
                window.location.reload();
            }
        }, 300000); // 5 minutes
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = YFClaimAdmin;
}