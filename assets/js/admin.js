/**
 * Thrive Mautic Integration Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Dashboard functionality
    const Dashboard = {
        init: function() {
            this.bindEvents();
            this.refreshStats();
            this.startAutoRefresh();
        },
        
        bindEvents: function() {
            $('#refresh-stats').on('click', this.refreshStats.bind(this));
            $('#retry-now').on('click', this.retryFailed.bind(this));
            $('#clear-logs').on('click', this.clearLogs.bind(this));
            $('#test-connection').on('click', this.testConnection.bind(this));
        },
        
        startAutoRefresh: function() {
            setInterval(this.refreshStats.bind(this), 30000); // Every 30 seconds
        },
        
        refreshStats: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_dashboard_stats',
                    nonce: thriveMautic.nonce
                },
                success: function(response) {
                    if (response.success) {
                        Dashboard.updateStats(response.data);
                    }
                },
                error: function() {
                    console.log('Stats refresh failed');
                }
            });
        },
        
        updateStats: function(stats) {
            $('#today-signups').text(stats.today_signups);
            $('#success-rate').text(stats.success_rate + '%');
            $('#pending-count').text(stats.pending_count);
            $('#failed-count').text(stats.failed_count);
            
            // Update change indicator
            const changeEl = $('#today-change');
            if (stats.today_change_num > 0) {
                changeEl.css('color', '#27ae60').text('+' + stats.today_change);
            } else if (stats.today_change_num < 0) {
                changeEl.css('color', '#e74c3c').text(stats.today_change);
            } else {
                changeEl.css('color', '#7f8c8d').text('no change');
            }
        },
        
        retryFailed: function() {
            const button = $('#retry-now');
            button.prop('disabled', true).text('Retrying...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'retry_failed_submissions',
                    nonce: thriveMautic.nonce
                },
                success: function(response) {
                    Dashboard.showMessage(response.data, 'success');
                    Dashboard.refreshStats();
                },
                error: function() {
                    Dashboard.showMessage('Retry failed', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Retry Now');
                }
            });
        },
        
        clearLogs: function() {
            if (!confirm(thriveMautic.strings.clearLogsConfirm)) {
                return;
            }
            
            const button = $('#clear-logs');
            button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_logs',
                    nonce: thriveMautic.nonce
                },
                success: function(response) {
                    Dashboard.showMessage(response.data, 'success');
                    setTimeout(() => location.reload(), 1000);
                },
                error: function() {
                    Dashboard.showMessage('Clear failed', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Clear Old Logs (30+ days)');
                }
            });
        },
        
        testConnection: function() {
            const button = $('#test-connection');
            button.prop('disabled', true).text('Testing...');
            
            const formData = new FormData();
            formData.append('action', 'test_mautic_connection');
            formData.append('nonce', thriveMautic.nonce);
            formData.append('mautic_url', $('input[name="mautic_url"]').val());
            formData.append('mautic_username', $('input[name="mautic_username"]').val());
            formData.append('mautic_password', $('input[name="mautic_password"]').val());
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Dashboard.showMessage(response.data, response.success ? 'success' : 'error');
                },
                error: function() {
                    Dashboard.showMessage('Test failed', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Mautic Connection');
                }
            });
        },
        
        showMessage: function(message, type) {
            const resultDiv = $('#action-result');
            resultDiv.html(`<div class="notice notice-${type}"><p>${message}</p></div>`);
            setTimeout(() => resultDiv.empty(), 5000);
        }
    };
    
    // Meta box functionality
    const MetaBox = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#setup-segments').on('click', this.setupSegments.bind(this));
            $('input[name="setup_type"]').on('change', this.toggleOptions.bind(this));
        },
        
        setupSegments: function() {
            const leadMagnetName = $('input[name="lead_magnet_name"]').val();
            const setupType = $('input[name="setup_type"]:checked').val();
            
            if (!leadMagnetName) {
                alert(thriveMautic.strings.leadMagnetRequired);
                return;
            }
            
            if (!setupType) {
                alert(thriveMautic.strings.setupTypeRequired);
                return;
            }
            
            const button = $('#setup-segments');
            button.prop('disabled', true).text('Setting up segments...');
            
            const formData = new FormData();
            formData.append('action', 'create_segments_flexible');
            formData.append('post_id', thriveMautic.postId);
            formData.append('lead_magnet_name', leadMagnetName);
            formData.append('setup_type', setupType);
            formData.append('nonce', thriveMautic.nonce);
            
            // Add setup-specific data
            if (setupType === 'use_existing') {
                formData.append('existing_optin_segment', $('input[name="existing_optin_segment"]').val());
                formData.append('existing_marketing_segment', $('input[name="existing_marketing_segment"]').val());
            } else if (setupType === 'mixed') {
                formData.append('optin_type', $('select[name="optin_type"]').val());
                formData.append('optin_value', $('input[name="optin_value"]').val());
                formData.append('marketing_type', $('select[name="marketing_type"]').val());
                formData.append('marketing_value', $('input[name="marketing_value"]').val());
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        MetaBox.showMessage(thriveMautic.strings.setupComplete, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        MetaBox.showMessage(thriveMautic.strings.setupError + ': ' + response.data, 'error');
                    }
                },
                error: function() {
                    MetaBox.showMessage(thriveMautic.strings.setupError, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(thriveMautic.strings.setupSegments);
                }
            });
        },
        
        toggleOptions: function() {
            const setupType = $('input[name="setup_type"]:checked').val();
            $('.setup-option').hide();
            
            if (setupType === 'create_new') {
                $('#create-new-options').show();
            } else if (setupType === 'use_existing') {
                $('#use-existing-options').show();
            } else if (setupType === 'mixed') {
                $('#mixed-options').show();
            }
        },
        
        showMessage: function(message, type) {
            const statusDiv = $('#setup-status');
            statusDiv.html(`<div class="notice notice-${type}"><p>${message}</p></div>`);
        }
    };
    
    // Form validation
    const FormValidation = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('form').on('submit', this.validateForm.bind(this));
            $('input[required]').on('blur', this.validateField.bind(this));
        },
        
        validateForm: function(e) {
            let isValid = true;
            
            $('input[required]').each(function() {
                if (!FormValidation.validateField.call(this)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        },
        
        validateField: function() {
            const field = $(this);
            const value = field.val().trim();
            const type = field.attr('type');
            
            field.removeClass('error success');
            field.siblings('.error-message').remove();
            
            if (!value) {
                field.addClass('error');
                field.after('<div class="error-message">' + thriveMautic.strings.fieldRequired + '</div>');
                return false;
            }
            
            if (type === 'email' && !this.isValidEmail(value)) {
                field.addClass('error');
                field.after('<div class="error-message">' + thriveMautic.strings.invalidEmail + '</div>');
                return false;
            }
            
            if (type === 'url' && !this.isValidUrl(value)) {
                field.addClass('error');
                field.after('<div class="error-message">' + thriveMautic.strings.invalidUrl + '</div>');
                return false;
            }
            
            field.addClass('success');
            return true;
        },
        
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        }
    };
    
    // Utility functions
    const Utils = {
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
        
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#thrive-mautic-dashboard').length) {
            Dashboard.init();
        }
        
        if ($('#thrive-mautic-container').length) {
            MetaBox.init();
        }
        
        FormValidation.init();
    });
    
    // Expose to global scope
    window.ThriveMautic = {
        Dashboard: Dashboard,
        MetaBox: MetaBox,
        FormValidation: FormValidation,
        Utils: Utils
    };
    
})(jQuery);
