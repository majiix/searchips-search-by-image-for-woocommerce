/**
 * Frontend Javascript for Searchips Search By Image
 */
jQuery(document).ready(function($) {
	// Check if tsbifw_frontend_params is defined to prevent script breaks
	if (typeof tsbifw_frontend_params === 'undefined') {
		return;
	}

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

	function ensureCropperLoaded(callback) {
		if (typeof window.customElements !== 'undefined' && window.customElements.get('cropper-canvas')) {
			if (typeof callback === 'function') callback();
			return;
		}
		var existingScript = document.getElementById('tsbifw-cropper-script');
		if (existingScript) {
			if (typeof callback === 'function') {
				existingScript.addEventListener('load', callback, { once: true });
			}
			return;
		}
		if (!tsbifw_frontend_params.cropper_src) {
			if (typeof callback === 'function') callback();
			return;
		}
		var script = document.createElement('script');
		script.id = 'tsbifw-cropper-script';
		script.src = tsbifw_frontend_params.cropper_src;
		script.onload = function() {
			if (typeof callback === 'function') callback();
		};
		document.head.appendChild(script);
	}

	function startScanningAnimation() {
		$('.tsbifw-scan-container').show().addClass('tsbifw-scan-active');
	}

	function stopScanningAnimation() {
		$('.tsbifw-scan-container').hide().removeClass('tsbifw-scan-active');
	}

	// 2. Click handler for camera triggers (delegated to support dynamically loaded forms)
	$(document).on('click', '.tsbifw-camera-trigger', function(e) {
		e.preventDefault();
		ensureCropperLoaded();
		initModal();
		openModal();
	});

	// Initialize modal DOM if it doesn't exist
	function initModal() {
		if ($('.tsbifw-modal-overlay').length) return;

		var isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
			(window.matchMedia && window.matchMedia('(pointer: coarse) and (max-width: 1024px)').matches);
		var showCameraBtn = isMobileDevice && (tsbifw_frontend_params.enable_mobile_camera || !tsbifw_frontend_params.is_pro);
		var cameraSectionHtml = '';

		if (showCameraBtn) {
			var isProLocked = !tsbifw_frontend_params.is_pro;
			var cameraDisabledAttr = isProLocked ? ' disabled="disabled"' : '';
			var cameraClass = isProLocked ? 'tsbifw-take-photo-btn tsbifw-btn-pro-locked' : 'tsbifw-take-photo-btn';
			var cameraTooltip = isProLocked ? ' title="' + (tsbifw_frontend_params.strings.camera_pro_tooltip || '') + '"' : '';
			var proBadgeHtml = isProLocked ? '<span class="tsbifw-modal-pro-badge">PRO</span>' : '';

			cameraSectionHtml =
				'<div class="tsbifw-mobile-camera-section">' +
					'<div class="tsbifw-camera-action-wrapper">' +
						'<button type="button" class="' + cameraClass + '"' + cameraDisabledAttr + cameraTooltip + '>' +
							'<svg class="tsbifw-btn-camera-icon" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
								'<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>' +
								'<circle cx="12" cy="13" r="4"></circle>' +
							'</svg>' +
							'<span>' + (tsbifw_frontend_params.strings.take_photo || 'Take Photo') + '</span>' +
							proBadgeHtml +
						'</button>' +
					'</div>' +
					'<div class="tsbifw-modal-divider">' +
						'<span>' + (tsbifw_frontend_params.strings.or_divider || 'or') + '</span>' +
					'</div>' +
				'</div>';
		}

		var scanEffect = tsbifw_frontend_params.scanning_effect || 'laser';
		var scanColor = tsbifw_frontend_params.scanning_color || '#6366f1';

		var modalHtml =
			'<div class="tsbifw-modal-overlay">' +
				'<div class="tsbifw-modal-container">' +
					'<div class="tsbifw-modal-header">' +
						'<h3>' + tsbifw_frontend_params.strings.modal_title + '</h3>' +
						'<button type="button" class="tsbifw-modal-close" aria-label="Close">&times;</button>' +
					'</div>' +
					'<div class="tsbifw-modal-body">' +
						'<div class="tsbifw-modal-actions-area">' +
							cameraSectionHtml +
							'<div class="tsbifw-drag-zone">' +
								'<svg class="tsbifw-drag-icon" viewBox="0 0 24 24" width="48" height="48" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
									'<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>' +
									'<circle cx="8.5" cy="8.5" r="1.5"></circle>' +
									'<polyline points="21 15 16 10 5 21"></polyline>' +
								'</svg>' +
								'<p>' + tsbifw_frontend_params.strings.drag_drop_text + '</p>' +
							'</div>' +
						'</div>' +
						'<input type="file" class="tsbifw-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;" />' +
						'<input type="file" class="tsbifw-camera-input" accept="image/*" capture="environment" style="display:none;" />' +
						'<div class="tsbifw-preview-wrapper" data-effect="' + scanEffect + '" style="--tsbifw-scan-color: ' + scanColor + ';">' +
							'<div class="tsbifw-cropper-container" style="max-height: 320px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; border: 1px solid #cbd5e1; position: relative;">' +
								'<cropper-canvas id="tsbifw-frontend-cropper-canvas" style="height: 280px; display: none;">' +
									'<cropper-image class="tsbifw-cropper-image" src="" rotatable scalable translatable></cropper-image>' +
									'<cropper-shade></cropper-shade>' +
									'<cropper-selection movable resizable initial-coverage="0.9" dynamic outlined>' +
										'<cropper-grid role="grid" covered></cropper-grid>' +
										'<cropper-crosshair centered></cropper-crosshair>' +
										'<cropper-handle action="move" theme-color="rgba(255, 255, 255, 0.35)"></cropper-handle>' +
										'<cropper-handle action="nw-resize" theme-color="#4f46e5"></cropper-handle>' +
										'<cropper-handle action="ne-resize" theme-color="#4f46e5"></cropper-handle>' +
										'<cropper-handle action="se-resize" theme-color="#4f46e5"></cropper-handle>' +
										'<cropper-handle action="sw-resize" theme-color="#4f46e5"></cropper-handle>' +
									'</cropper-selection>' +
								'</cropper-canvas>' +
								'<div class="tsbifw-scan-container" data-effect="' + scanEffect + '" style="display:none; --tsbifw-scan-color: ' + scanColor + ';">' +
									'<div class="tsbifw-effect-layer tsbifw-effect-laser">' +
										'<div class="tsbifw-scanner-bar"></div>' +
										'<div class="tsbifw-scanning-overlay"></div>' +
									'</div>' +
									'<div class="tsbifw-effect-layer tsbifw-effect-reticle">' +
										'<div class="tsbifw-reticle-bracket tsbifw-reticle-tl"></div>' +
										'<div class="tsbifw-reticle-bracket tsbifw-reticle-tr"></div>' +
										'<div class="tsbifw-reticle-bracket tsbifw-reticle-bl"></div>' +
										'<div class="tsbifw-reticle-bracket tsbifw-reticle-br"></div>' +
										'<div class="tsbifw-reticle-crosshair"></div>' +
										'<div class="tsbifw-reticle-tag">AI_TARGET: 0x94F2</div>' +
									'</div>' +
									'<div class="tsbifw-effect-layer tsbifw-effect-radar">' +
										'<div class="tsbifw-radar-ring tsbifw-radar-ring-1"></div>' +
										'<div class="tsbifw-radar-ring tsbifw-radar-ring-2"></div>' +
										'<div class="tsbifw-radar-sweep"></div>' +
										'<div class="tsbifw-radar-grid"></div>' +
									'</div>' +
									'<div class="tsbifw-effect-layer tsbifw-effect-matrix">' +
										'<div class="tsbifw-matrix-grid"></div>' +
									'</div>' +
									'<div class="tsbifw-effect-layer tsbifw-effect-ripple">' +
										'<div class="tsbifw-ripple-wave tsbifw-ripple-1"></div>' +
										'<div class="tsbifw-ripple-wave tsbifw-ripple-2"></div>' +
										'<div class="tsbifw-ripple-wave tsbifw-ripple-3"></div>' +
										'<div class="tsbifw-ripple-core"></div>' +
									'</div>' +
									'<div class="tsbifw-effect-layer tsbifw-effect-hologram">' +
										'<div class="tsbifw-hologram-prism"></div>' +
										'<div class="tsbifw-hologram-shimmer"></div>' +
									'</div>' +
								'</div>' +
							'</div>' +
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

		// Camera button and camera input handlers
		var $cameraInput = $('.tsbifw-camera-input');
		$('.tsbifw-take-photo-btn:not(.tsbifw-btn-pro-locked)').on('click', function(e) {
			e.preventDefault();
			if ($cameraInput.length) {
				$cameraInput[0].click();
			}
		});

		$cameraInput.on('click', function(e) {
			e.stopPropagation();
		});

		$cameraInput.on('change', function(e) {
			if (this.files && this.files[0]) {
				handleFileSelection(this.files[0]);
			}
		});

		// Drag & drop handlers
		var $dragZone = $('.tsbifw-drag-zone');
		var $fileInput = $('.tsbifw-file-input');

		$dragZone.on('click', function() {
			if ($fileInput.length) {
				$fileInput[0].click();
			}
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
			var canvasEl = document.getElementById('tsbifw-frontend-cropper-canvas');
			if (!canvasEl) return;
			var selection = canvasEl.querySelector('cropper-selection');
			if (!selection) return;

			// Start scanning animation
			startScanningAnimation();
			startStatusRotation();

			selection.$toCanvas({
				width: 512,
				height: 512
			}).then(function(canvas) {
				canvas.toBlob(function(blob) {
					if (!blob) {
						stopStatusRotation();
						stopScanningAnimation();
						$('.tsbifw-search-status').hide().removeClass('pulse').text('');
						alert('Failed to process cropped image.');
						return;
					}
					var croppedFile = new File([blob], 'cropped_search.jpg', { type: 'image/jpeg' });
					uploadSearchImage(croppedFile);
				}, 'image/jpeg', 0.9);
			}).catch(function() {
				stopStatusRotation();
				stopScanningAnimation();
				$('.tsbifw-search-status').hide().removeClass('pulse').text('');
				alert('Failed to crop image.');
			});
		});
	}

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

	var statusInterval = null;

	function startStatusRotation() {
		if (statusInterval) {
			clearInterval(statusInterval);
		}

		var phrases = [
			tsbifw_frontend_params.strings.scanning,
			'Analyzing colors and shapes...',
			'Comparing visual textures...',
			'Querying product database...',
			'Finalizing search results...'
		];
		var index = 0;

		$('.tsbifw-search-status').text(phrases[0]).addClass('pulse').show();

		statusInterval = setInterval(function() {
			index = (index + 1) % phrases.length;
			$('.tsbifw-search-status').fadeOut(200, function() {
				$(this).text(phrases[index]).fadeIn(200);
			});
		}, 1800);
	}

	function stopStatusRotation() {
		if (statusInterval) {
			clearInterval(statusInterval);
			statusInterval = null;
		}
	}

	function resetSearchUI() {
		stopStatusRotation();
		$('.tsbifw-file-input').val('');
		$('.tsbifw-camera-input').val('');
		var previewImg = $('.tsbifw-cropper-image')[0];
		if (previewImg) {
			previewImg.setAttribute('src', '');
		}
		$('#tsbifw-frontend-cropper-canvas').hide();
		$('.tsbifw-preview-wrapper').hide();
		$('.tsbifw-modal-actions-area').show();
		$('.tsbifw-drag-zone').show();
		stopScanningAnimation();
		$('.tsbifw-search-status').hide().removeClass('pulse').text('');
	}

	// Process selected image file
	function handleFileSelection(file) {
		if (!file.type.match('image.*')) {
			alert('Please select a valid image file (JPEG, PNG, WEBP).');
			return;
		}

		if (tsbifw_frontend_params.max_upload_size && file.size > tsbifw_frontend_params.max_upload_size) {
			alert(tsbifw_frontend_params.strings.file_too_large);
			return;
		}

		ensureCropperLoaded(function() {
			processSelectedFile(file);
		});
	}

	function processSelectedFile(file) {
		// Show preview using FileReader
		var reader = new FileReader();
		reader.onload = function(e) {
			$('.tsbifw-modal-actions-area').hide();
			$('.tsbifw-drag-zone').hide();
			$('.tsbifw-preview-wrapper').show();
			$('#tsbifw-frontend-cropper-canvas').show();

			var image = $('.tsbifw-cropper-image')[0];
			if (image) {
				image.setAttribute('src', e.target.result);
				if (typeof image.$ready === 'function') {
					image.$ready().then(function() {
						setTimeout(function() {
							var canvas = document.getElementById('tsbifw-frontend-cropper-canvas');
							var selection = canvas ? canvas.querySelector('cropper-selection') : null;
							if (selection && typeof selection.$change === 'function') {
								var imgRect = image.getBoundingClientRect();
								var canvasRect = canvas.getBoundingClientRect();
								if (imgRect.width > 0 && imgRect.height > 0) {
									var imgLeft = imgRect.left - canvasRect.left;
									var imgTop = imgRect.top - canvasRect.top;
									var imgWidth = imgRect.width;
									var imgHeight = imgRect.height;

									var selectionWidth = imgWidth * 0.9;
									var selectionHeight = imgHeight * 0.9;
									var selectionLeft = imgLeft + (imgWidth - selectionWidth) / 2;
									var selectionTop = imgTop + (imgHeight - selectionHeight) / 2;

									selection.$change(selectionLeft, selectionTop, selectionWidth, selectionHeight);
								}
							}
						}, 50);
					});
				}
			}

			$('.tsbifw-search-status').hide().text('');
		};
		reader.readAsDataURL(file);
	}

	// AJAX search request to REST API
	function uploadSearchImage(file) {
		if (tsbifw_frontend_params.max_upload_size && file.size > tsbifw_frontend_params.max_upload_size) {
			alert(tsbifw_frontend_params.strings.file_too_large);
			stopStatusRotation();
			stopScanningAnimation();
			$('.tsbifw-search-status').hide().removeClass('pulse').text('');
			return;
		}

		var formData = new FormData();
		formData.append('image', file);
		formData.append('security', tsbifw_frontend_params.nonce);

		$.ajax({
			url: tsbifw_frontend_params.search_endpoint,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				stopStatusRotation();
				stopScanningAnimation();
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
				stopStatusRotation();
				stopScanningAnimation();

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

	// Visual search Click-Through Rate (CTR) tracking
	if (tsbifw_frontend_params.current_vquery && tsbifw_frontend_params.track_endpoint) {
		var vtoken = tsbifw_frontend_params.current_vquery;
		var trackUrl = tsbifw_frontend_params.track_endpoint;
		var trackedThisPage = false;

		$(document).on('click', '.product a, a.woocommerce-LoopProduct-link, .add_to_cart_button', function() {
			if (trackedThisPage) return;
			var $target = $(this);
			var $product = $target.closest('.product');
			var productId = 0;

			if ($target.data('product_id')) {
				productId = parseInt($target.data('product_id'), 10);
			} else if ($product.length) {
				var classList = $product.attr('class') || '';
				var match = classList.match(/post-(\d+)/);
				if (match && match[1]) {
					productId = parseInt(match[1], 10);
				}
			}

			if (productId > 0) {
				trackedThisPage = true;
				var payload = JSON.stringify({
					token: vtoken,
					product_id: productId,
					security: (tsbifw_frontend_params && tsbifw_frontend_params.nonce) || ''
				});
				if (navigator.sendBeacon) {
					var blob = new Blob([payload], { type: 'application/json' });
					navigator.sendBeacon(trackUrl, blob);
				} else if (window.fetch) {
					fetch(trackUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: payload,
						keepalive: true
					});
				}
			}
		});
	}
});
