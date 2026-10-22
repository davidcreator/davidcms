function getURLVar(key) {
    var value = [];

    var query = String(document.location).split('?');

    if (query[1]) {
        var part = query[1].split('&');

        for (i = 0; i < part.length; i++) {
            var data = part[i].split('=');

            if (data[0] && data[1]) {
                value[data[0]] = data[1];
            }
        }

        if (value[key]) {
            return value[key];
        } else {
            return '';
        }
    }
}

$(document).ready(function() {
    // Tooltip
    var rms_tooltip = function() {
        // Get tooltip instance
        tooltip = bootstrap.Tooltip.getInstance(this);
        if (!tooltip) {
            // Apply to current element
            tooltip = bootstrap.Tooltip.getOrCreateInstance(this);
            tooltip.show();
        }
    }

    $(document).on('mouseenter', '[data-bs-toggle=\'tooltip\']', rms_tooltip);

    $(document).on('click', 'button', function() {
        $('.tooltip').remove();
    });

    // Date
    var rms_datetimepicker = function() {
        $(this).daterangepicker({
            singleDatePicker: true,
            autoApply: true,
            autoUpdateInput: false,
            locale: {
                format: 'YYYY-MM-DD'
            }
        }, function(start, end) {
            $(this.element).val(start.format('YYYY-MM-DD'));
        });
    }

    $(document).on('focus', '.date', rms_datetimepicker);

    // Time
    var rms_datetimepicker = function() {
        $(this).daterangepicker({
            singleDatePicker: true,
            datePicker: false,
            autoApply: true,
            autoUpdateInput: false,
            timePicker: true,
            timePicker24Hour: true,
            locale: {
                format: 'HH:mm'
            }
        }, function(start, end) {
            $(this.element).val(start.format('HH:mm'));
        }).on('show.daterangepicker', function(ev, picker) {
            picker.container.find('.calendar-table').hide();
        });
    }

    $(document).on('focus', '.time', rms_datetimepicker);

    // Date Time
    var rms_datetimepicker = function() {
        $(this).daterangepicker({
            singleDatePicker: true,
            autoApply: true,
            autoUpdateInput: false,
            timePicker: true,
            timePicker24Hour: true,
            locale: {
                format: 'YYYY-MM-DD HH:mm'
            }
        }, function(start, end) {
            $(this.element).val(start.format('YYYY-MM-DD HH:mm'));
        });
    }

    $(document).on('focus', '.datetime', rms_datetimepicker);

    var rms_alert = function() {
        window.setTimeout(function() {
            $('.alert-dismissible').fadeTo(3000, 0, function() {
                $(this).remove();
            });
        }, 3000);
    }

    $(document).on('click', 'button', rms_alert);
    $(document).on('click', 'change', rms_alert);
});

$(document).ready(function() {
    // Currency
    $('#form-currency .dropdown-item').on('click', function(e) {
        e.preventDefault();

        $('#form-currency input[name=\'code\']').val($(this).attr('href'));

        $('#form-currency').submit();
    });

    // Search
    $('#search input[name=\'search\']').parent().find('button').on('click', function() {
        var url = $('base').attr('href') + 'index.php?route=product/search&language=' + $(this).attr('data-lang');

        var value = $('header #search input[name=\'search\']').val();

        if (value) {
            url += '&search=' + encodeURIComponent(value);
        }

        location = url;
    });

    $('#search input[name=\'search\']').on('keydown', function(e) {
        if (e.keyCode == 13) {
            $('header #search input[name=\'search\']').parent().find('button').trigger('click');
        }
    });

    // Menu
    $('#menu .dropdown-menu').each(function() {
        var menu = $('#menu').offset();
        var dropdown = $(this).parent().offset();

        var i = (dropdown.left + $(this).outerWidth()) - (menu.left + $('#menu').outerWidth());

        if (i > 0) {
            $(this).css('margin-left', '-' + (i + 10) + 'px');
        }
    });

    // Product List
    $('#button-list').on('click', function() {
        var element = this;

        $('#product-list').attr('class', 'row row-cols-1 product-list');

        $('#button-grid').removeClass('active');
        $('#button-list').addClass('active');

        localStorage.setItem('display', 'list');
    });

    // Product Grid
    $('#button-grid').on('click', function() {
        var element = this;

        // What a shame bootstrap does not take into account dynamically loaded columns
        $('#product-list').attr('class', 'row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3');

        $('#button-list').removeClass('active');
        $('#button-grid').addClass('active');

        localStorage.setItem('display', 'grid');
    });

    // Local Storage
    if (localStorage.getItem('display') == 'list') {
        $('#product-list').attr('class', 'row row-cols-1 product-list');
        $('#button-list').addClass('active');
    } else {
        $('#product-list').attr('class', 'row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3');
        $('#button-grid').addClass('active');
    }

    /* Agree to Terms */
    $('body').on('click', '.modal-link', function(e) {
        e.preventDefault();

        var element = this;

        $('#modal-information').remove();

        $.ajax({
            url: $(element).attr('href'),
            dataType: 'html',
            success: function(html) {
                $('body').append(html);

                $('#modal-information').modal('show');
            }
        });
    });

    // Cookie Policy
    $('#cookie button').on('click', function() {
        var element = this;

        $.ajax({
            url: $(this).val(),
            type: 'get',
            dataType: 'json',
            beforeSend: function() {
                $(element).button('loading');
            },
            complete: function() {
                $(element).button('reset');
            },
            success: function(json) {
                if (json['success']) {
                    $('#cookie').fadeOut(400, function() {
                        $('#cookie').remove();
                    });
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
            }
        });
    });
});

// Forms
$(document).on('submit', 'form', function (e) {
    var element = this;

    if (e.originalEvent !== undefined && e.originalEvent.submitter !== undefined) {
        var button = e.originalEvent.submitter;
    } else {
        var button = '';
    }

    var status = false;

    var ajax = $(element).attr('data-rms-toggle');

    if (ajax == 'ajax') {
        status = true;
    }

    var ajax = $(button).attr('data-rms-toggle');

    if (ajax == 'ajax') {
        status = true;
    }

    if (status) {
        e.preventDefault();

        // Form attributes
        var form = e.target;

        var action = $(form).attr('action');

        var method = $(form).attr('method');

        if (method === undefined) {
            method = 'post';
        }

        var enctype = $(form).attr('enctype');

        if (enctype === undefined) {
            enctype = 'application/x-www-form-urlencoded';
        }

        // Form button overrides
        var formaction = $(button).attr('formaction');

        if (formaction !== undefined) {
            action = formaction;
        }

        var formmethod = $(button).attr('formmethod');

        if (formmethod !== undefined) {
            method = formmethod;
        }

        var formenctype = $(button).attr('formenctype');

        if (formenctype !== undefined) {
            enctype = formenctype;
        }

        if (button) {
            var formaction = $(button).attr('data-type');
        }

        console.log(e);
        console.log('element ' + element);
        console.log('action ' + action);
        console.log('button ' + button);
        console.log('formaction ' + formaction);
        console.log('method ' + method);
        console.log('enctype ' + enctype);
        console.log($(element).serialize());

        // https://github.com/reamurcms/reamurcms/issues/9690
        if (typeof CKEDITOR != 'undefined') {
            for (instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
        }

        $.ajax({
            url: action.replaceAll('&amp;', '&'),
            type: method,
            data: $(form).serialize(),
            dataType: 'json',
            contentType: enctype,
            beforeSend: function () {
                $(button).button('loading');
            },
            complete: function () {
                $(button).button('reset');
            },
            success: function (json, textStatus) {
                console.log(json);
                console.log(textStatus);

                $('.alert-dismissible').remove();
                $(element).find('.is-invalid').removeClass('is-invalid');
                $(element).find('.invalid-feedback').removeClass('d-block');

                if (json['redirect']) {
                    location = json['redirect'];
                }

                if (typeof json['error'] == 'string') {
                    $('#alert').prepend('<div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> ' + json['error'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }

                if (typeof json['error'] == 'object') {
                    if (json['error']['warning']) {
                        $('#alert').prepend('<dirv class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> ' + json['error']['warning'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></dirv>');
                    }

                    for (key in json['error']) {
                        $('#input-' + key.replaceAll('_', '-')).addClass('is-invalid').find('.form-control, .form-select, .form-check-input, .form-check-label').addClass('is-invalid');
                        $('#error-' + key.replaceAll('_', '-')).html(json['error'][key]).addClass('d-block');
                    }
                }

                if (json['success']) {
                    $('#alert').prepend('<div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> ' + json['success'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');

                    // Refresh
                    var url = $(form).attr('data-rms-load');
                    var target = $(form).attr('data-rms-target');

                    if (url !== undefined && target !== undefined) {
                        $(target).load(url);
                    }
                }

                // Replace any form values that correspond to form names.
                for (key in json) {
                    $(element).find('[name=\'' + key + '\']').val(json[key]);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
            }
        });
    }
});

// Upload
$(document).on('click', '[data-rms-toggle=\'upload\']', function() {
	var element = this;

	$('#form-upload').remove();
	$('.upload-progress-container').remove();
	$('.upload-validation-container').remove();

	// Create the form for file upload
	$('body').prepend('<form enctype="multipart/form-data" id="form-upload" style="display: none;"><input type="file" name="file" /></form>');

	// Add progress bar container
	var progressContainer = $('<div class="upload-progress-container" style="display: none;">' +
		'<div class="progress">' +
		'<div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>' +
		'</div>' +
		'</div>');
	
	// Add validation feedback container
	var validationContainer = $('<div class="upload-validation-container" style="display: none;">' +
		'<div class="validation-messages"></div>' +
		'</div>');
	
	// Append containers after the element
	$(element).after(progressContainer);
	$(element).after(validationContainer);

	// Trigger file selection dialog
	$('#form-upload input[name=\'file\']').trigger('click');

	// Handle file selection
	$('#form-upload input[name=\'file\']').on('change', function() {
		// Reset error message
		$(element).parent().find('.invalid-feedback').remove();
		$(element).parent().find('.form-control').removeClass('is-invalid');
		$('.upload-validation-container .validation-messages').empty();
		$('.upload-validation-container').hide();

		var file = this.files[0];
		if (!file) return;

		// Check file size
		var maxSize = $(element).attr('data-rms-size-max');
		if (file.size > maxSize) {
			showValidationError($(element).attr('data-rms-size-error') || 'File is too large');
			$(this).val('');
			return;
		}

		// Basic file type validation
		var fileName = file.name;
		var fileExt = fileName.split('.').pop().toLowerCase();
		
		// Show file info
		showValidationInfo('Selected file: ' + fileName + ' (' + formatFileSize(file.size) + ')');
	});

	// Helper function to show validation errors
	function showValidationError(message) {
		$('.upload-validation-container').show();
		$('.upload-validation-container .validation-messages').append(
			'<div class="alert alert-danger">' + message + '</div>'
		);
	}

	// Helper function to show validation info
	function showValidationInfo(message) {
		$('.upload-validation-container').show();
		$('.upload-validation-container .validation-messages').append(
			'<div class="alert alert-info">' + message + '</div>'
		);
	}

	// Helper function to format file size
	function formatFileSize(bytes) {
		if (bytes === 0) return '0 Bytes';
		var k = 1024;
		var sizes = ['Bytes', 'KB', 'MB', 'GB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
	}

	// Clear any existing timer
	if (typeof timer != 'undefined') {
    	clearInterval(timer);
	}

	// Set up timer to check for file selection
	var timer = setInterval(function() {
		if ($('#form-upload input[name=\'file\']').val() != '') {
			clearInterval(timer);

			// Show progress bar
			$('.upload-progress-container').show();

			$.ajax({
				url: $(element).attr('data-rms-url'),
				type: 'post',
				dataType: 'json',
				data: new FormData($('#form-upload')[0]),
				cache: false,
				contentType: false,
				processData: false,
				beforeSend: function() {
					$(element).button('loading');
				},
				uploadProgress: function(event, position, total, percentComplete) {
					// Update progress bar
					var percentVal = percentComplete + '%';
					$('.upload-progress-container .progress-bar').width(percentVal);
					$('.upload-progress-container .progress-bar').text(percentVal);
				},
				complete: function() {
					$(element).button('reset');
				},
				success: function(json) {
					console.log(json);

					// Hide progress bar when complete
					$('.upload-progress-container').hide();

					// Clear validation messages
					$('.upload-validation-container .validation-messages').empty();

					if (json['error']) {
						$(element).parent().find('.invalid-feedback').remove();
						$(element).parent().find('.form-control').removeClass('is-invalid');

						$(element).parent().find('.form-control').addClass('is-invalid').after('<div class="invalid-feedback">' + json['error'] + '</div>');
						
						// Show detailed validation errors if available
						if (json['validation_details'] && json['validation_details'].length) {
							json['validation_details'].forEach(function(detail) {
								showValidationError(detail);
							});
						}

						// Show allowed extensions if available
						if (json['allowed_extensions'] && json['allowed_extensions'].length) {
							showValidationInfo('Allowed extensions: ' + json['allowed_extensions'].join(', '));
						}

						// Show allowed MIME types if available
						if (json['allowed_mimes'] && json['allowed_mimes'].length) {
							showValidationInfo('Allowed MIME types: ' + json['allowed_mimes'].join(', '));
						}
					}

					if (json['success']) {
						showValidationInfo(json['success']);

						// Show file info if available
						if (json['file_info']) {
							var fileInfo = json['file_info'];
							showValidationInfo('Uploaded: ' + fileInfo.name + ' (' + formatFileSize(fileInfo.size) + ')');
						}

						$(element).parent().find('input[type=\'hidden\']').val(json['code']);
					}

					// Show upload limits if available
					if (json['upload_limits']) {
						var limits = json['upload_limits'];
						showValidationInfo('Max file size: ' + formatFileSize(limits.max_file_size * 1024));
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					// Hide progress bar on error
					$('.upload-progress-container').hide();
					
					// Show error message
					showValidationError('Upload failed: ' + thrownError);
					console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
				}
			});
		}
	}, 500);
});

// Chain ajax calls.
class Chain {
    constructor() {
        this.start = false;
        this.data = [];
    }

    attach(call) {
        this.data.push(call);

        if (!this.start) {
            this.execute();
        }
    }

    execute() {
        if (this.data.length) {
            this.start = true;

            var call = this.data.shift();

            var jqxhr = call();

            jqxhr.done(function() {
                chain.execute();
            });
        } else {
            this.start = false;
        }
    }
}

var chain = new Chain();

// Autocomplete
+function($) {
    $.fn.autocomplete = function(option) {
        return this.each(function() {
            var element = this;
            var $dropdown = $('#' + $(element).attr('data-rms-target'));

            this.timer = null;
            this.items = [];

            $.extend(this, option);

            // Focus in
            $(element).on('focusin', function() {
                element.request();
            });

            // Focus out
            $(element).on('focusout', function(e) {
                if (!e.relatedTarget || !$(e.relatedTarget).hasClass('dropdown-item')) {
                    $dropdown.removeClass('show');
                }
            });

            // Input
            $(element).on('input', function(e) {
                element.request();
            });

            // Click
            $dropdown.on('click', 'a', function(e) {
                e.preventDefault();

                var value = $(this).attr('href');

                if (element.items[value] !== undefined) {
                    element.select(element.items[value]);

                    $dropdown.removeClass('show');
                }
            });

            // Request
            this.request = function() {
                clearTimeout(this.timer);

                $('#autocomplete-loading').remove();

                $dropdown.prepend('<li id="autocomplete-loading"><span class="dropdown-item text-center disabled"><i class="fa-solid fa-circle-notch fa-spin"></i></span></li>');
                $dropdown.addClass('show');

                this.timer = setTimeout(function(object) {
                    object.source($(object).val(), $.proxy(object.response, object));
                }, 50, this);
            }

            // Response
            this.response = function(json) {
                var html = '';
                var category = {};
                var name;
                var i = 0, j = 0;

                if (json.length) {
                    for (i = 0; i < json.length; i++) {
                        // update element items
                        this.items[json[i]['value']] = json[i];

                        if (!json[i]['category']) {
                            // ungrouped items
                            html += '<li><a href="' + json[i]['value'] + '" class="dropdown-item">' + json[i]['label'] + '</a></li>';
                        } else {
                            // grouped items
                            name = json[i]['category'];

                            if (!category[name]) {
                                category[name] = [];
                            }

                            category[name].push(json[i]);
                        }
                    }

                    for (name in category) {
                        html += '<li><h6 class="dropdown-header">' + name + '</h6></li>';

                        for (j = 0; j < category[name].length; j++) {
                            html += '<li><a href="' + category[name][j]['value'] + '" class="dropdown-item">' + category[name][j]['label'] + '</a></li>';
                        }
                    }
                }

                $dropdown.html(html);
            }
        });
    }
}(jQuery);

// Button
$(document).ready(function() {
    +function($) {
        $.fn.button = function(state) {
            return this.each(function() {
                var element = this;

                if (state == 'loading') {
                    this.html = $(element).html();
                    this.state = $(element).prop('disabled');

                    $(element).prop('disabled', true).width($(element).width()).html('<i class="fa-solid fa-circle-notch fa-spin text-light"></i>');
                }

                if (state == 'reset') {
                    $(element).prop('disabled', this.state).width('').html(this.html);
                }
            });
        }
    }(jQuery);
});