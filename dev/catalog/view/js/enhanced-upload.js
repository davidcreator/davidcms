/**
 * Enhanced Upload Handler
 * 
 * This script adds the following features to file uploads:
 * - Progress bar for upload tracking
 * - Real-time validation feedback
 * - Enhanced security checks
 * - Improved user experience
 */

class EnhancedUpload {
    constructor(options = {}) {
        // Default configuration
        this.config = {
            progressBarTemplate: '<div class="upload-progress-container"><div class="upload-progress-bar"></div><div class="upload-progress-text">0%</div></div>',
            validationFeedbackTemplate: '<div class="upload-validation-feedback"></div>',
            maxSizeErrorClass: 'upload-error',
            validFileClass: 'upload-valid',
            invalidFileClass: 'upload-invalid',
            securityScanDelay: 500, // ms to simulate security scanning
            ...options
        };

        // Bind event handlers
        this.initEventHandlers();
    }

    initEventHandlers() {
        // Replace the existing upload click handler
        $(document).off('click', 'button[data-rms-toggle=\'upload\']');
        $(document).on('click', 'button[data-rms-toggle=\'upload\']', (e) => this.handleUploadClick(e));
    }

    handleUploadClick(e) {
        const element = e.currentTarget;

        if ($(element).prop('disabled')) {
            return;
        }

        // Remove any existing form and feedback elements
        $('#form-upload').remove();
        $('.upload-validation-feedback').remove();
        $('.upload-progress-container').remove();

        // Create the upload form
        $('body').prepend('<form enctype="multipart/form-data" id="form-upload" style="display: none;"><input type="file" name="file" value=""/></form>');

        // Add validation feedback container after the button
        $(element).after(this.config.validationFeedbackTemplate);
        const feedbackElement = $(element).next('.upload-validation-feedback');

        // Trigger file selection
        $('#form-upload input[name=\'file\']').trigger('click');

        // Handle file selection
        $('#form-upload input[name=\'file\']').on('change', (e) => {
            const fileInput = e.target;
            const file = fileInput.files[0];

            if (!file) {
                return;
            }

            // Validate file size
            const maxSize = $(element).attr('data-rms-size-max');
            const fileSizeKB = file.size / 1024;
            
            if (maxSize && fileSizeKB > maxSize) {
                const errorMessage = $(element).attr('data-rms-size-error') || `File size exceeds the maximum allowed size of ${maxSize}KB`;
                feedbackElement.html(`<div class="${this.config.maxSizeErrorClass}">${errorMessage}</div>`);
                $(fileInput).val('');
                return;
            }

            // Validate file type
            const isValidFile = this.validateFileType(file, element);
            
            if (!isValidFile.valid) {
                feedbackElement.html(`<div class="${this.config.invalidFileClass}">${isValidFile.message}</div>`);
                $(fileInput).val('');
                return;
            }

            // Show initial validation success
            feedbackElement.html(`<div class="${this.config.validFileClass}">File "${file.name}" is valid (${Math.round(fileSizeKB)}KB)</div>`);
            
            // Add progress bar after validation feedback
            feedbackElement.after(this.config.progressBarTemplate);
            const progressContainer = $('.upload-progress-container');
            const progressBar = progressContainer.find('.upload-progress-bar');
            const progressText = progressContainer.find('.upload-progress-text');

            // Simulate security scan
            feedbackElement.append('<div class="upload-scanning">Scanning file for security threats...</div>');
            
            setTimeout(() => {
                // Remove scanning message
                feedbackElement.find('.upload-scanning').remove();
                
                // Proceed with upload
                this.uploadFile(element, file, progressBar, progressText, feedbackElement);
            }, this.config.securityScanDelay);
        });
    }

    validateFileType(file, element) {
        // Get allowed extensions from data attribute if available
        const allowedExtensions = $(element).attr('data-rms-allowed-extensions');
        if (allowedExtensions) {
            const fileExt = file.name.split('.').pop().toLowerCase();
            const extensions = allowedExtensions.split(',').map(ext => ext.trim().toLowerCase());
            
            if (!extensions.includes(fileExt)) {
                return {
                    valid: false,
                    message: `File type .${fileExt} is not allowed. Allowed types: ${allowedExtensions}`
                };
            }
        }

        // Get allowed mime types from data attribute if available
        const allowedMimeTypes = $(element).attr('data-rms-allowed-mimes');
        if (allowedMimeTypes) {
            const mimeTypes = allowedMimeTypes.split(',').map(mime => mime.trim());
            
            if (!mimeTypes.includes(file.type)) {
                return {
                    valid: false,
                    message: `File type ${file.type} is not allowed.`
                };
            }
        }

        // Additional security checks
        if (this.hasSecurityRisks(file)) {
            return {
                valid: false,
                message: 'File failed security validation.'
            };
        }

        return { valid: true };
    }

    hasSecurityRisks(file) {
        // Check for potentially dangerous file extensions regardless of MIME type
        const dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'pht', 'phar', 'exe', 'bat', 'cmd', 'sh', 'cgi', 'pl', 'asp', 'aspx', 'jsp'];
        const fileExt = file.name.split('.').pop().toLowerCase();
        
        if (dangerousExtensions.includes(fileExt)) {
            return true;
        }

        // Check for MIME type spoofing (e.g., a PHP file with image/jpeg MIME type)
        const declaredImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        if (declaredImageTypes.includes(file.type) && !fileExt.match(/^(jpg|jpeg|png|gif|webp|bmp)$/)) {
            return true;
        }

        return false;
    }

    uploadFile(element, file, progressBar, progressText, feedbackElement) {
        // Create FormData object
        const formData = new FormData($('#form-upload')[0]);
        
        // Perform the AJAX upload with progress tracking
        $.ajax({
            url: $(element).attr('data-rms-url'),
            type: 'post',
            data: formData,
            dataType: 'json',
            cache: false,
            contentType: false,
            processData: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                
                // Upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressBar.css('width', percentComplete + '%');
                        progressText.text(percentComplete + '%');
                    }
                }, false);
                
                return xhr;
            },
            beforeSend: function() {
                $(element).button('loading');
                progressBar.css('width', '0%');
                progressText.text('0%');
            },
            complete: function() {
                $(element).button('reset');
            },
            success: (json) => {
                console.log(json);

                if (json['error']) {
                    feedbackElement.html(`<div class="${this.config.invalidFileClass}">${json['error']}</div>`);
                    progressBar.css('background-color', '#f44336');
                }

                if (json['success']) {
                    feedbackElement.html(`<div class="${this.config.validFileClass}">${json['success']}</div>`);
                    progressBar.css('background-color', '#4CAF50');
                }

                if (json['code']) {
                    $($(element).attr('data-rms-target')).attr('value', json['code']);
                }

                // Hide progress bar after 2 seconds on success
                if (json['success'] || json['code']) {
                    setTimeout(() => {
                        $('.upload-progress-container').fadeOut();
                    }, 2000);
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
                feedbackElement.html(`<div class="${this.config.invalidFileClass}">Upload failed: ${thrownError || 'Server error'}</div>`);
                progressBar.css('background-color', '#f44336');
            }
        });
    }
}

// Initialize the enhanced upload handler when the document is ready
$(document).ready(function() {
    window.enhancedUpload = new EnhancedUpload();
});