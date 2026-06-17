/**
 * Frontend Javascript for Telens Search By Image
 */
jQuery(document).ready(function($) {
	// 1. Auto-inject camera icon into WooCommerce search forms
	if (tsbifw_frontend_params.auto_inject) {
		injectCameraTriggers();
		// Re-run occasionally to handle dynamically loaded elements (e.g. AJAX header search, mini-carts)
		setTimeout(injectCameraTriggers, 1000);
		setTimeout(injectCameraTriggers, 3000);
	}

	function injectCameraTriggers() {
		$('form.woocommerce-product-search, form.search-form, form[role="search"]').each(function() {
			var $form = $(this);
			var isProductSearch = $form.find('input[name="post_type"][value="product"]').length > 0 || $form.hasClass('woocommerce-product-search');
			
			if (isProductSearch && !$form.find('.tsbifw-camera-trigger').length) {
				var $input = $form.find('input[name="s"], input[type="search"], input.search-field');
				if ($input.length) {
					$input.wrap('<div class="tsbifw-search-input-wrapper"></div>');
					$input.after(
						'<button type="button" class="tsbifw-camera-trigger" title="' + tsbifw_frontend_params.strings.modal_title + '">' +
						'<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="tsbifw-camera-icon">' +
						'<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>' +
						'<circle cx="12" cy="13" r="4"></circle>' +
						'</svg>' +
						'</button>'
					);
				}
			}
		});
	}

	// 2. Click handler for camera triggers (delegated to support dynamically loaded forms)
	$(document).on('click', '.tsbifw-camera-trigger', function(e) {
		e.preventDefault();
		initModal();
		openModal();
	});

	// Initialize modal DOM if it doesn't exist
	function initModal() {
		if ($('.tsbifw-modal-overlay').length) return;

		var modalHtml = 
			'<div class="tsbifw-modal-overlay">' +
				'<div class="tsbifw-modal-container">' +
					'<div class="tsbifw-modal-header">' +
						'<h3>' + tsbifw_frontend_params.strings.modal_title + '</h3>' +
						'<button type="button" class="tsbifw-modal-close" aria-label="Close">&times;</button>' +
					'</div>' +
					'<div class="tsbifw-modal-body">' +
						'<div class="tsbifw-drag-zone">' +
							'<svg class="tsbifw-drag-icon" viewBox="0 0 24 24" width="48" height="48" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
								'<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>' +
								'<circle cx="8.5" cy="8.5" r="1.5"></circle>' +
								'<polyline points="21 15 16 10 5 21"></polyline>' +
							'</svg>' +
							'<p>' + tsbifw_frontend_params.strings.drag_drop_text + '</p>' +
						'</div>' +
						'<input type="file" class="tsbifw-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;" />' +
						'<div class="tsbifw-preview-wrapper">' +
							'<div class="tsbifw-cropper-container" style="max-height: 320px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; border: 1px solid #cbd5e1;">' +
								'<img class="tsbifw-preview-image" src="" alt="Search Preview" style="max-width: 100%; display: block;" />' +
							'</div>' +
							'<div class="tsbifw-scanner-bar"></div>' +
							'<div class="tsbifw-scanning-overlay"></div>' +
							'<div class="tsbifw-preview-actions" style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; align-items: center;">' +
								'<button type="button" class="tsbifw-btn tsbifw-btn-primary tsbifw-crop-search-btn" style="width: auto; padding: 10px 20px;">' + tsbifw_frontend_params.strings.search_btn_text + '</button>' +
								'<button type="button" class="tsbifw-reselect-btn" style="margin: 0;">' + tsbifw_frontend_params.strings.select_another + '</button>' +
							'</div>' +
						'</div>' +
						'<div class="tsbifw-search-status"></div>' +
					'</div>' +
				'</div>' +
			'</div>';

		$('body').append(modalHtml);

		// Event handlers for the modal
		$('.tsbifw-modal-close, .tsbifw-modal-overlay').on('click', function(e) {
			if (e.target === this) {
				closeModal();
			}
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('.tsbifw-modal-overlay').hasClass('active')) {
				closeModal();
			}
		});

		// Drag & drop handlers
		var $dragZone = $('.tsbifw-drag-zone');
		var $fileInput = $('.tsbifw-file-input');

		$dragZone.on('click', function() {
			$fileInput.click();
		});

		$fileInput.on('click', function(e) {
			e.stopPropagation();
		});

		$fileInput.on('change', function(e) {
			if (this.files && this.files[0]) {
				handleFileSelection(this.files[0]);
			}
		});

		$dragZone.on('dragover dragenter', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$dragZone.addClass('dragover');
		});

		$dragZone.on('dragleave drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$dragZone.removeClass('dragover');
		});

		$dragZone.on('drop', function(e) {
			var files = e.originalEvent.dataTransfer.files;
			if (files && files[0]) {
				handleFileSelection(files[0]);
			}
		});

		// Reselect / clear button
		$('.tsbifw-reselect-btn').on('click', function() {
			resetSearchUI();
		});

		// Crop and Search button
		$('.tsbifw-crop-search-btn').on('click', function() {
			if (!frontendCropper) return;

			// Start scanning animation
			$('.tsbifw-scanner-bar').show();
			$('.tsbifw-scanning-overlay').show();
			$('.tsbifw-search-status').text(tsbifw_frontend_params.strings.scanning).addClass('pulse').show();

			frontendCropper.getCroppedCanvas({
				maxWidth: 512,
				maxHeight: 512
			}).toBlob(function(blob) {
				if (!blob) {
					alert('Failed to process cropped image.');
					return;
				}
				var croppedFile = new File([blob], 'cropped_search.jpg', { type: 'image/jpeg' });
				uploadSearchImage(croppedFile);
			}, 'image/jpeg', 0.9);
		});
	}

	var frontendCropper = null;

	function openModal() {
		resetSearchUI();
		$('.tsbifw-modal-overlay').addClass('active');
		$('html, body').css('overflow', 'hidden');
		if (window.history && window.history.pushState) {
			window.history.pushState({ tsbifw_modal_open: true }, '');
		}
	}

	function closeModal() {
		if ($('.tsbifw-modal-overlay').hasClass('active')) {
			$('.tsbifw-modal-overlay').removeClass('active');
			$('html, body').css('overflow', '');
			if (window.history && window.history.state && window.history.state.tsbifw_modal_open) {
				window.history.back();
			}
		}
	}

	function resetSearchUI() {
		if (frontendCropper) {
			frontendCropper.destroy();
			frontendCropper = null;
		}
		$('.tsbifw-file-input').val('');
		$('.tsbifw-preview-image').attr('src', '');
		$('.tsbifw-preview-wrapper').hide();
		$('.tsbifw-drag-zone').show();
		$('.tsbifw-scanner-bar').hide();
		$('.tsbifw-scanning-overlay').hide();
		$('.tsbifw-search-status').hide().removeClass('pulse').text('');
	}

	// Process selected image file
	function handleFileSelection(file) {
		if (!file.type.match('image.*')) {
			alert('Please select a valid image file (JPEG, PNG, WEBP).');
			return;
		}

		// Show preview using FileReader
		var reader = new FileReader();
		reader.onload = function(e) {
			var image = $('.tsbifw-preview-image')[0];
			image.src = e.target.result;

			$('.tsbifw-drag-zone').hide();
			$('.tsbifw-preview-wrapper').show();
			$('.tsbifw-search-status').hide().text('');

			if (frontendCropper) {
				frontendCropper.destroy();
			}

			frontendCropper = new Cropper(image, {
				aspectRatio: NaN,
				viewMode: 1,
				autoCropArea: 1,
				responsive: true,
				restore: false,
				modal: true,
				guides: true,
				center: true,
				highlight: false,
				cropBoxMovable: true,
				cropBoxResizable: true,
				toggleDragModeOnDblclick: false
			});
		};
		reader.readAsDataURL(file);
	}

	// AJAX search request to REST API
	function uploadSearchImage(file) {
		var formData = new FormData();
		formData.append('image', file);

		$.ajax({
			url: tsbifw_frontend_params.search_endpoint,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				$('.tsbifw-scanner-bar').hide();
				$('.tsbifw-scanning-overlay').hide();
				$('.tsbifw-search-status').hide().removeClass('pulse');

				if (response.redirect_url) {
					closeModal();
					window.location.href = response.redirect_url;
				} else {
					alert('Search failed. Invalid response.');
					resetSearchUI();
				}
			},
			error: function(xhr) {
				$('.tsbifw-scanner-bar').hide();
				$('.tsbifw-scanning-overlay').hide();
				
				var errorMsg = tsbifw_frontend_params.strings.error;
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				}
				$('.tsbifw-search-status').text(errorMsg).removeClass('pulse').show();
			}
		});
	}

	// Close modal if browser back button is clicked
	$(window).on('popstate', function() {
		if ($('.tsbifw-modal-overlay').hasClass('active')) {
			$('.tsbifw-modal-overlay').removeClass('active');
			$('html, body').css('overflow', '');
		}
	});
});
