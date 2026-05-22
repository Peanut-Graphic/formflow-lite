/**
 * IntelliSource Forms - Inline Validation Module
 *
 * Real-time field validation with visual feedback.
 * Feature-togglable per instance via FeatureManager.
 */

(function($, window) {
    'use strict';

    var FFInlineValidation = {
        // Configuration (set from PHP via localization)
        config: {
            enabled: true,
            showSuccessIcons: true,
            validateOnBlur: true,
            validateOnKeyup: false
        },

        // Debounce timers
        debounceTimers: {},

        // Field validation rules
        rules: {
            email: {
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: 'Please enter a valid email address'
            },
            phone: {
                pattern: /^\(\d{3}\) \d{3}-\d{4}$/,
                rawPattern: /^\d{10}$/,
                message: 'Please enter a valid 10-digit phone number'
            },
            zip: {
                pattern: /^\d{5}(-\d{4})?$/,
                message: 'Please enter a valid ZIP code (5 digits)'
            },
            account: {
                minLength: 5,
                maxLength: 20,
                pattern: /^[0-9A-Za-z-]+$/,
                message: 'Please enter a valid account number'
            }
        },

        /**
         * Initialize inline validation
         */
        init: function(config) {
            // Merge config from PHP
            if (config) {
                this.config = $.extend({}, this.config, config);
            }

            if (!this.config.enabled) {
                return;
            }

            this.bindEvents();
            this.initializeExistingFields();
        },

        /**
         * Bind validation events
         */
        bindEvents: function() {
            var self = this;

            // Blur validation
            if (this.config.validateOnBlur) {
                $(document).on('blur', '.ff-input, .ff-select', function() {
                    self.validateField($(this));
                });
            }

            // Keyup validation (with debounce)
            if (this.config.validateOnKeyup) {
                $(document).on('keyup', '.ff-input', function() {
                    var $field = $(this);
                    var fieldId = $field.attr('id') || $field.attr('name');

                    // Clear existing timer
                    if (self.debounceTimers[fieldId]) {
                        clearTimeout(self.debounceTimers[fieldId]);
                    }

                    // Set new timer (300ms debounce)
                    self.debounceTimers[fieldId] = setTimeout(function() {
                        self.validateField($field);
                    }, 300);
                });
            }

            // Special handling for email confirmation
            $(document).on('blur keyup', '#email_confirm', function() {
                self.validateEmailMatch($(this));
            });

            // Phone formatting and validation
            $(document).on('input', '#phone, #alt_phone', function() {
                self.formatAndValidatePhone($(this));
            });

            // ZIP formatting
            $(document).on('input', '#zip, #zip_confirm', function() {
                self.formatZip($(this));
            });

            // Account number formatting
            $(document).on('input', '#utility_no', function() {
                self.formatAccountNumber($(this));
            });

            // Clear validation on focus (fresh start)
            $(document).on('focus', '.ff-input, .ff-select', function() {
                var $field = $(this);
                // Only clear if user is about to type
                if (!$field.val()) {
                    self.clearFieldState($field);
                }
            });

            // Re-validate on step load
            $(document).on('ff:stepLoaded', function() {
                self.initializeExistingFields();
            });
        },

        /**
         * Initialize validation state for pre-filled fields
         */
        initializeExistingFields: function() {
            var self = this;

            // Only validate visible required fields with values
            $('.ff-step-form:visible .ff-input[required], .ff-step-form:visible .ff-select[required]').each(function() {
                var $field = $(this);
                if ($field.val() && $field.val().trim() !== '') {
                    // Validate silently (no error messages for pre-filled)
                    self.validateField($field, true);
                }
            });
        },

        /**
         * Validate a single field
         */
        validateField: function($field, silent) {
            var value = $field.val();
            var fieldType = this.getFieldType($field);
            var isRequired = $field.prop('required');
            var result = { valid: true, message: '' };

            // Check required first
            if (isRequired && (!value || value.trim() === '')) {
                result.valid = false;
                result.message = this.getMessage('required_field');
            }
            // Type-specific validation
            else if (value && value.trim() !== '') {
                result = this.validateByType($field, value, fieldType);
            }

            // Update field state
            if (!silent) {
                this.updateFieldState($field, result);
            } else if (result.valid && this.config.showSuccessIcons) {
                // Show success for valid pre-filled fields
                this.setFieldSuccess($field);
            }

            return result.valid;
        },

        /**
         * Get field type from attributes/id
         */
        getFieldType: function($field) {
            var id = $field.attr('id') || '';
            var type = $field.attr('type') || 'text';
            var name = $field.attr('name') || '';

            // Email fields
            if (type === 'email' || id.indexOf('email') !== -1) {
                return 'email';
            }

            // Phone fields
            if (type === 'tel' || id.indexOf('phone') !== -1) {
                return 'phone';
            }

            // ZIP fields
            if (id.indexOf('zip') !== -1) {
                return 'zip';
            }

            // Account number
            if (id === 'utility_no' || id.indexOf('account') !== -1) {
                return 'account';
            }

            // Name fields
            if (id === 'first_name' || id === 'last_name' || name.indexOf('name') !== -1) {
                return 'name';
            }

            // Select fields
            if ($field.is('select')) {
                return 'select';
            }

            return 'text';
        },

        /**
         * Validate field by type
         */
        validateByType: function($field, value, type) {
            var result = { valid: true, message: '' };
            var rules = this.rules[type];

            switch (type) {
                case 'email':
                    if (!this.rules.email.pattern.test(value)) {
                        result.valid = false;
                        result.message = this.getMessage('invalid_email');
                    }
                    break;

                case 'phone':
                    var digits = value.replace(/\D/g, '');
                    if (digits.length !== 10) {
                        result.valid = false;
                        result.message = this.getMessage('invalid_phone');
                    }
                    break;

                case 'zip':
                    if (!/^\d{5}(-\d{4})?$/.test(value)) {
                        result.valid = false;
                        result.message = this.getMessage('invalid_zip');
                    }
                    break;

                case 'account':
                    if (value.length < 5) {
                        result.valid = false;
                        result.message = 'Account number is too short';
                    }
                    break;

                case 'name':
                    if (value.trim().length < 2) {
                        result.valid = false;
                        result.message = 'Please enter at least 2 characters';
                    }
                    break;

                case 'select':
                    if (!value || value === '') {
                        result.valid = false;
                        result.message = 'Please select an option';
                    }
                    break;
            }

            return result;
        },

        /**
         * Validate email confirmation matches
         */
        validateEmailMatch: function($field) {
            var email = $('#email').val().trim();
            var confirm = $field.val().trim();

            if (confirm && email !== confirm) {
                this.updateFieldState($field, {
                    valid: false,
                    message: this.getMessage('email_mismatch')
                });
                return false;
            } else if (confirm && email === confirm) {
                this.setFieldSuccess($field);
                return true;
            }

            return true;
        },

        /**
         * Format phone number as user types
         */
        formatAndValidatePhone: function($field) {
            var value = $field.val().replace(/\D/g, '');
            var formatted = '';

            if (value.length > 0) {
                formatted = '(' + value.substring(0, 3);
            }
            if (value.length >= 3) {
                formatted += ') ' + value.substring(3, 6);
            }
            if (value.length >= 6) {
                formatted += '-' + value.substring(6, 10);
            }

            $field.val(formatted);

            // Validate after formatting
            if (value.length === 10) {
                this.setFieldSuccess($field);
            } else if (value.length > 0 && value.length < 10) {
                // Partial - clear validation state
                this.clearFieldState($field);
            }
        },

        /**
         * Format ZIP code
         */
        formatZip: function($field) {
            var value = $field.val().replace(/[^\d-]/g, '');

            // Limit to 5 or 10 characters (ZIP+4)
            if (value.length > 10) {
                value = value.substring(0, 10);
            }

            // Auto-add hyphen for ZIP+4
            if (value.length > 5 && value.indexOf('-') === -1) {
                value = value.substring(0, 5) + '-' + value.substring(5);
            }

            $field.val(value);

            // Validate
            if (/^\d{5}(-\d{4})?$/.test(value)) {
                this.setFieldSuccess($field);
            } else if (value.length === 5) {
                this.setFieldSuccess($field);
            }
        },

        /**
         * Format account number
         */
        formatAccountNumber: function($field) {
            var value = $field.val();

            // Remove any characters that aren't alphanumeric or dashes
            value = value.replace(/[^0-9A-Za-z-]/g, '');
            $field.val(value);
        },

        /**
         * Update field validation state (success/error)
         */
        updateFieldState: function($field, result) {
            // Clear existing state
            this.clearFieldState($field);

            if (result.valid) {
                this.setFieldSuccess($field);
            } else {
                this.setFieldError($field, result.message);
            }
        },

        /**
         * Set field success state
         */
        setFieldSuccess: function($field) {
            // 3.2.13: Success is now indicated by the green border alone
            // (.ff-input-valid). The previous implementation wrapped each
            // <input> in a width:100% inline-flex span to hang a checkmark
            // icon. That dynamic re-parenting caused a cascade of layout and
            // stability bugs: it broke the flex layout of grouped phone fields,
            // knocked paired fields (Email / Confirm Email) out of vertical
            // alignment, and raced the live formatters on phone + ZIP to throw
            // "appendChild: node no longer a child". Dropping the wrap removes
            // the entire class of problems; the border is a sufficient signal.
            $field.addClass('ff-input-valid').removeClass('ff-input-error');

            // Clean up any wrap/icon left over from a previous plugin version
            // still present in the DOM for this field.
            var $existingWrap = $field.closest('.ff-input-wrap');
            if ($existingWrap.length) {
                $existingWrap.find('.ff-validation-icon').remove();
            }
        },

        /**
         * Set field error state
         */
        setFieldError: function($field, message) {
            var $wrapper = $field.closest('.ff-field');
            $field.addClass('ff-input-error').removeClass('ff-input-valid');

            // Remove any success icon from input wrap
            var $inputWrap = $field.closest('.ff-input-wrap');
            if ($inputWrap.length) {
                $inputWrap.find('.ff-validation-icon').remove();
            }
            $wrapper.find('.ff-validation-icon').remove();

            // Add error message if not exists
            if (!$wrapper.find('.ff-field-error').length && message) {
                var errorEl = '<span class="ff-field-error" role="alert">' + this.escapeHtml(message) + '</span>';

                // For input groups, add after the group
                var $inputGroup = $field.closest('.ff-input-group');
                if ($inputGroup.length) {
                    $inputGroup.after(errorEl);
                } else if ($inputWrap.length) {
                    $inputWrap.after(errorEl);
                } else {
                    $field.after(errorEl);
                }
            }
        },

        /**
         * Clear field validation state
         */
        clearFieldState: function($field) {
            var $wrapper = $field.closest('.ff-field');
            var $inputWrap = $field.closest('.ff-input-wrap');
            $field.removeClass('ff-input-valid ff-input-error');
            $wrapper.find('.ff-field-error').remove();
            $wrapper.find('.ff-validation-icon').remove();
            if ($inputWrap.length) {
                $inputWrap.find('.ff-validation-icon').remove();
            }
        },

        /**
         * Validate entire form
         */
        validateForm: function($form) {
            var self = this;
            var isValid = true;
            var $firstError = null;

            $form.find('.ff-input[required], .ff-select[required]').each(function() {
                var $field = $(this);
                var fieldValid = self.validateField($field);

                if (!fieldValid && isValid) {
                    isValid = false;
                    $firstError = $field;
                }
            });

            // Check email confirmation
            var $emailConfirm = $form.find('#email_confirm');
            if ($emailConfirm.length) {
                var emailMatch = this.validateEmailMatch($emailConfirm);
                if (!emailMatch && isValid) {
                    isValid = false;
                    $firstError = $emailConfirm;
                }
            }

            // Focus first error
            if ($firstError) {
                $firstError.focus();
            }

            return isValid;
        },

        /**
         * Get localized message
         */
        getMessage: function(key) {
            if (typeof fffl_frontend !== 'undefined' && fffl_frontend.strings && fffl_frontend.strings[key]) {
                return fffl_frontend.strings[key];
            }

            // Fallback messages
            var fallbacks = {
                required_field: 'This field is required.',
                invalid_email: 'Please enter a valid email address.',
                invalid_phone: 'Please enter a valid phone number.',
                invalid_zip: 'Please enter a valid ZIP code.',
                email_mismatch: 'Email addresses do not match.'
            };

            return fallbacks[key] || 'Invalid input.';
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Export to window
    window.FFInlineValidation = FFInlineValidation;

    // Auto-initialize when DOM ready if config available
    $(document).ready(function() {
        if (typeof fffl_frontend !== 'undefined' && fffl_frontend.features) {
            var validationConfig = fffl_frontend.features.inline_validation || {};
            FFInlineValidation.init({
                enabled: validationConfig.enabled !== false,
                showSuccessIcons: validationConfig.show_success_icons !== false,
                validateOnBlur: validationConfig.validate_on_blur !== false,
                validateOnKeyup: validationConfig.validate_on_keyup === true
            });
        } else {
            // Default initialization
            FFInlineValidation.init();
        }
    });

})(jQuery, window);
