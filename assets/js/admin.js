// Ecocash Payment Gateway Admin JavaScript

(function($) {
    'use strict';

    // Initialize admin functionality
    $(document).ready(function() {
        
        // Test API Connection
        $('#test-connection').on('click', function() {
            var button = $(this);
            var result = $('#connection-result');
            
            var sandbox_mode = $('input[name="ecocash_sandbox_mode"]').is(':checked');
            var api_key = sandbox_mode ? 
                $('input[name="ecocash_api_key_sandbox"]').val() : 
                $('input[name="ecocash_api_key_live"]').val();
            
            if (!api_key) {
                result.html('Please enter an API key first.')
                      .removeClass('success')
                      .addClass('error');
                return;
            }
            
            button.prop('disabled', true)
                  .html('<span class="ecocash-spinner"></span>' + ecocash_ajax.text.testing_connection);
            
            result.removeClass('success error').text('');
            
            $.ajax({
                url: ecocash_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecocash_test_connection',
                    api_key: api_key,
                    sandbox_mode: sandbox_mode,
                    nonce: ecocash_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        result.html(response.data.message)
                              .removeClass('error')
                              .addClass('success');
                    } else {
                        result.html(response.data.message)
                              .removeClass('success')
                              .addClass('error');
                    }
                },
                error: function() {
                    result.html('Connection test failed. Please try again.')
                          .removeClass('success')
                          .addClass('error');
                },
                complete: function() {
                    button.prop('disabled', false)
                          .text('Test API Connection');
                }
            });
        });
        
        // Auto-save settings when API key changes
        var autoSaveTimeout;
        $('input[name="ecocash_api_key_sandbox"], input[name="ecocash_api_key_live"]').on('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(function() {
                $('#test-connection').trigger('click');
            }, 2000);
        });
        
        // Environment mode indicator
        $('input[name="ecocash_sandbox_mode"]').on('change', function() {
            var isChecked = $(this).is(':checked');
            var modeText = isChecked ? 'Sandbox Mode' : 'Live Mode';
            var modeClass = isChecked ? 'sandbox' : 'live';
            
            $('.mode').text(modeText)
                      .removeClass('sandbox live')
                      .addClass(modeClass);
        });
        
        // Enable/disable gateway status
        $('input[name="ecocash_enabled"]').on('change', function() {
            var isChecked = $(this).is(':checked');
            var statusText = isChecked ? 'Enabled' : 'Disabled';
            var statusClass = isChecked ? 'enabled' : 'disabled';
            
            $('.status').text(statusText)
                        .removeClass('enabled disabled')
                        .addClass(statusClass);
        });
        
        // Form validation
        $('form').on('submit', function(e) {
            var hasErrors = false;
            var sandbox_mode = $('input[name="ecocash_sandbox_mode"]').is(':checked');
            var api_key = sandbox_mode ? 
                $('input[name="ecocash_api_key_sandbox"]').val() : 
                $('input[name="ecocash_api_key_live"]').val();
            
            // Clear previous errors
            $('.form-error').remove();
            
            // Check if gateway is enabled but no API key is provided
            if ($('input[name="ecocash_enabled"]').is(':checked') && !api_key) {
                var errorMsg = sandbox_mode ? 
                    'Please enter a sandbox API key.' : 
                    'Please enter a live API key.';
                
                var targetField = sandbox_mode ? 
                    $('input[name="ecocash_api_key_sandbox"]') : 
                    $('input[name="ecocash_api_key_live"]');
                
                targetField.after('<p class="form-error" style="color: #dc3232; margin-top: 5px;">' + errorMsg + '</p>');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.form-error').first().offset().top - 100
                }, 500);
            }
        });
        
        // Tooltips for help text
        $('[data-tooltip]').each(function() {
            var $this = $(this);
            $this.hover(
                function() {
                    var tooltip = $('<div class="ecocash-tooltip">' + $this.data('tooltip') + '</div>');
                    $('body').append(tooltip);
                    
                    var offset = $this.offset();
                    tooltip.css({
                        position: 'absolute',
                        top: offset.top - tooltip.outerHeight() - 10,
                        left: offset.left + ($this.outerWidth() / 2) - (tooltip.outerWidth() / 2),
                        background: '#333',
                        color: '#fff',
                        padding: '8px 12px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        zIndex: 9999,
                        whiteSpace: 'nowrap'
                    });
                },
                function() {
                    $('.ecocash-tooltip').remove();
                }
            );
        });
        
        // Accordion functionality for settings sections
        $('.ecocash-accordion-header').on('click', function() {
            var $this = $(this);
            var $content = $this.next('.ecocash-accordion-content');
            
            $this.toggleClass('active');
            $content.slideToggle();
            
            // Store state in localStorage
            var sectionId = $this.data('section');
            if (sectionId) {
                localStorage.setItem('ecocash_section_' + sectionId, $this.hasClass('active'));
            }
        });
        
        // Restore accordion states
        $('.ecocash-accordion-header').each(function() {
            var $this = $(this);
            var sectionId = $this.data('section');
            
            if (sectionId) {
                var isActive = localStorage.getItem('ecocash_section_' + sectionId) === 'true';
                if (isActive) {
                    $this.addClass('active');
                    $this.next('.ecocash-accordion-content').show();
                }
            }
        });
        
        // Copy to clipboard functionality
        $('.ecocash-copy-button').on('click', function() {
            var $this = $(this);
            var targetSelector = $this.data('target');
            var $target = $(targetSelector);
            
            if ($target.length) {
                $target.select();
                document.execCommand('copy');
                
                var originalText = $this.text();
                $this.text('Copied!').prop('disabled', true);
                
                setTimeout(function() {
                    $this.text(originalText).prop('disabled', false);
                }, 2000);
            }
        });
        
        // Auto-refresh transaction status
        if ($('.ecocash-auto-refresh').length) {
            setInterval(function() {
                $('.ecocash-refresh-button').trigger('click');
            }, 30000); // Refresh every 30 seconds
        }
        
        // Confirm actions for sensitive operations
        $('.ecocash-confirm-action').on('click', function(e) {
            var message = $(this).data('confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
        
        // Form field dependencies
        $('input[data-depends-on]').each(function() {
            var $this = $(this);
            var dependsOn = $this.data('depends-on');
            var dependsValue = $this.data('depends-value');
            var $dependency = $('input[name="' + dependsOn + '"]');
            
            function toggleVisibility() {
                var currentValue = $dependency.val();
                if ($dependency.attr('type') === 'checkbox') {
                    currentValue = $dependency.is(':checked') ? 'yes' : 'no';
                }
                
                if (currentValue === dependsValue) {
                    $this.closest('tr').show();
                } else {
                    $this.closest('tr').hide();
                }
            }
            
            $dependency.on('change', toggleVisibility);
            toggleVisibility(); // Initial state
        });
    });
    
    // Utility functions
    window.EcocashAdmin = {
        
        // Show notification
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="ecocash-notification ecocash-notification-' + type + '">' + 
                                '<p>' + message + '</p>' +
                                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>' +
                                '</div>');
            
            $('.wrap h1').after($notification);
            
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.fadeOut();
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut();
            }, 5000);
        },
        
        // Format currency
        formatCurrency: function(amount, currency) {
            currency = currency || 'USD';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },
        
        // Format mobile number
        formatMobile: function(mobile) {
            // Remove any non-numeric characters
            mobile = mobile.replace(/[^0-9]/g, '');
            
            // Format as 263 XX XXX XXXX
            if (mobile.length === 12 && mobile.startsWith('263')) {
                return mobile.replace(/(\d{3})(\d{2})(\d{3})(\d{4})/, '$1 $2 $3 $4');
            }
            
            return mobile;
        },
        
        // Validate mobile number
        validateMobile: function(mobile) {
            mobile = mobile.replace(/[^0-9]/g, '');
            return /^263[0-9]{9}$/.test(mobile);
        }
    };

})(jQuery);