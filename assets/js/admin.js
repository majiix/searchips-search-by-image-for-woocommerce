/**
 * Admin Panel Javascript for Searchips Search By Image
 */
jQuery(document).ready(function($) {
	// Check if tsbifw_admin_params is defined to prevent script breaks
	if (typeof tsbifw_admin_params === 'undefined') {
		return;
	}

	// Initialize WordPress color picker
	if ($.isFunction($.fn.wpColorPicker)) {
		$('#tsbifw_camera_bg_color').wpColorPicker();
		$('#tsbifw_scanning_color').wpColorPicker({
			change: function(event, ui) {
				var newColor = ui.color.toString();
				$('#tsbifw-preview-stage').css('--tsbifw-scan-color', newColor);
				$('#tsbifw-admin-preview-wrapper .tsbifw-scan-container').css('--tsbifw-scan-color', newColor);
			},
			clear: function() {
				$('#tsbifw-preview-stage').css('--tsbifw-scan-color', '#6366f1');
				$('#tsbifw-admin-preview-wrapper .tsbifw-scan-container').css('--tsbifw-scan-color', '#6366f1');
			}
		});
	}

	// Styling tab: Scanning effect selector cards and live preview mockup
	var $previewStage = $('#tsbifw-preview-stage');
	if ($previewStage.length) {
		var $effectCards = $('.tsbifw-effect-card');

		$effectCards.on('click', function(e) {
			var $card = $(this);
			var effect = $card.data('effect');
			var $radio = $card.find('input[type="radio"]');

			// Update card active class
			$effectCards.removeClass('active');
			$card.addClass('active');

			// Switch animation effect on preview stage and animation container
			$previewStage.attr('data-effect', effect);
			$('#tsbifw-preview-anim-container').attr('data-effect', effect);
			$radio.prop('checked', true);
		});

		// Listen to radio changes for keyboard/accessibility navigation
		$('input[name="tsbifw_scanning_effect"]').on('change', function() {
			var effect = $(this).val();
			$effectCards.removeClass('active');
			$('.tsbifw-effect-card[data-effect="' + effect + '"]').addClass('active');
			$previewStage.attr('data-effect', effect);
			$('#tsbifw-preview-anim-container').attr('data-effect', effect);
		});

		// Toggle play/pause for preview animation
		$('#tsbifw-toggle-preview-anim').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			if ($previewStage.hasClass('tsbifw-preview-paused')) {
				$previewStage.removeClass('tsbifw-preview-paused');
				$btn.text('Pause');
			} else {
				$previewStage.addClass('tsbifw-preview-paused');
				$btn.text('Resume');
			}
		});
	}

	// Strategy selector visibility toggle
	var initialStrategy = $('#tsbifw_strategy').val();
	$('#tsbifw_strategy').on('change', function() {
		var selected = $(this).val();
		$('.tsbifw-strategy-field').hide();
		$('.tsbifw-strategy-field-' + selected).show();
		if (selected === 'embeddings') {
			$('.embeddings-field').show();
		}
		$(document).trigger('tsbifw_strategy_toggled', [selected]);

		// Warn the user to clear index and re-index.
		if (selected !== initialStrategy) {
			if ($('#tsbifw-strategy-warning').length === 0) {
				$('<p id="tsbifw-strategy-warning" class="tsbifw-error-text" style="margin-top: 10px; font-weight: 600;">' +
					tsbifw_admin_params.strings.strategy_warning +
				  '</p>').insertAfter('#tsbifw_strategy');
			}
		} else {
			$('#tsbifw-strategy-warning').remove();
		}
	});

	// Gateway selector visibility toggle
	function updateGatewayGuidance() {
		var gw = $('#tsbifw_api_gateway').val();
		var strategy = $('#tsbifw_strategy').val();

		var gwName = 'OpenRouter';
		if ('openai' === gw) gwName = 'OpenAI Direct';
		if ('gemini' === gw) gwName = 'Google Gemini Direct';

		$('#tsbifw-embeddings-model-desc').text('Select the embedding model ID for ' + gwName + '.');
		$(document).trigger('tsbifw_gateway_guidance_updated', [gw, strategy, gwName]);
	}

	$('#tsbifw_api_gateway').on('change', function() {
		var gw = $(this).val();
		$('.tsbifw-gateway-field').hide();
		$('.gateway-' + gw).show();
		updateGatewayGuidance();
		loadModelsForGateway(gw);
	});

	$('#tsbifw_strategy').on('change', function() {
		updateGatewayGuidance();
	});

	updateGatewayGuidance();

	// Password visibility toggle
	$(document).on('click', '.tsbifw-toggle-pw, #tsbifw-toggle-api-key', function(e) {
		e.preventDefault();
		var targetId = $(this).data('target') || 'tsbifw_api_key';
		var input = $('#' + targetId);
		var icon = $(this).find('.dashicons');
		if (input.attr('type') === 'password') {
			input.attr('type', 'text');
			icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			input.attr('type', 'password');
			icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	});

	// Cron settings toggle visibility
	$('#tsbifw_enable_cron_indexing').on('change', function() {
		if ($(this).is(':checked')) {
			$('.tsbifw-cron-settings').show();
		} else {
			$('.tsbifw-cron-settings').hide();
		}
	});

	var indexing_state = 'stopped'; // 'running', 'paused', 'stopped'

	function updateIndexerUI() {
		if (indexing_state === 'running') {
			$('#tsbifw-start-indexing').hide();
			$('#tsbifw-reset-indexing').hide();
			$('#tsbifw-pause-indexing').show().prop('disabled', false).text('Pause Indexing');
			$('#tsbifw-stop-indexing').show().prop('disabled', false).text('Stop Indexing');
		} else {
			$('#tsbifw-start-indexing').show().prop('disabled', false).text('Start / Resume Indexing');
			$('#tsbifw-reset-indexing').show().prop('disabled', false);
			$('#tsbifw-pause-indexing').hide();
			$('#tsbifw-stop-indexing').hide();
		}
	}

	// Batch indexing trigger
	$('#tsbifw-start-indexing').on('click', function() {
		if (indexing_state === 'running') return;

		indexing_state = 'running';
		updateIndexerUI();
		$('.tsbifw-log-output').show();

		var logConsole = $('#tsbifw-log-console');
		if (logConsole.text().indexOf('Completed') !== -1 || logConsole.text().trim() === '') {
			logConsole.html('Starting indexing process...\n');
		} else {
			logConsole.append('\nResuming indexing process...\n');
		}
		$('.tsbifw-progress-wrapper').show();

		runIndexingBatch();
	});

	// Pause indexing trigger
	$('#tsbifw-pause-indexing').on('click', function() {
		if (indexing_state !== 'running') return;
		indexing_state = 'paused';
		$(this).prop('disabled', true).text('Pausing...');
		$('#tsbifw-log-console').append('Pausing after current batch completes...\n');
	});

	// Stop indexing trigger
	$('#tsbifw-stop-indexing').on('click', function() {
		if (indexing_state !== 'running') return;
		indexing_state = 'stopped';
		$(this).prop('disabled', true).text('Stopping...');
		$('#tsbifw-log-console').append('Stopping after current batch completes...\n');
	});

	// Main batch indexing recursive AJAX loop
	function runIndexingBatch() {
		if (indexing_state !== 'running') {
			handleIndexingHalt();
			return;
		}

		$.ajax({
			url: tsbifw_admin_params.ajax_url,
			type: 'POST',
			data: {
				action: 'tsbifw_batch_index',
				security: tsbifw_admin_params.nonce
			},
			success: function(response) {
				if (response.success) {
					var data = response.data;

					// Output logs to console
					if (data.logs && data.logs.length > 0) {
						var $console = $('#tsbifw-log-console');
						data.logs.forEach(function(log) {
							$console.append(log + '\n');
						});
						$console.scrollTop($console[0].scrollHeight);
					}

					// Update stats counter values
					if (data.stats) {
						var stats = data.stats;
						$('#tsbifw-stat-total').text(stats.total);
						$('#tsbifw-stat-indexed').text(stats.indexed);
						$('#tsbifw-stat-skipped').text(stats.skipped);
						$('#tsbifw-stat-errors').text(stats.errors);

						// Update progress bar
						$('.tsbifw-progress-fill').css('width', stats.percentage + '%');
						$('#tsbifw-progress-percent').text(stats.percentage);
						$('#tsbifw-processed-count').text(stats.processed);
						$('#tsbifw-total-count').text(stats.total);
					}

					if (data.completed) {
						$('#tsbifw-log-console').append('\n--- Indexing Completed Successfully ---\n');
						indexing_state = 'stopped';
						updateIndexerUI();
						$('#tsbifw-start-indexing').prop('disabled', true);
					} else {
						if (indexing_state !== 'running') {
							handleIndexingHalt();
						} else {
							// Process next batch
							runIndexingBatch();
						}
					}
				} else {
					var errMsg = response.data ? response.data : 'Unknown error during batch indexing.';
					$('#tsbifw-log-console').append('\nError: ' + errMsg + '\nIndexing halted.\n');
					indexing_state = 'stopped';
					updateIndexerUI();
				}
			},
			error: function() {
				$('#tsbifw-log-console').append('\nNetwork connection or server execution error.\nIndexing halted.\n');
				indexing_state = 'stopped';
				updateIndexerUI();
			}
		});
	}

	function handleIndexingHalt() {
		var msg = '';
		if (indexing_state === 'paused') {
			msg = '\n--- Indexing Paused by User ---\n';
		} else {
			msg = '\n--- Indexing Stopped by User ---\n';
		}

		var $console = $('#tsbifw-log-console');
		$console.append(msg);
		$console.scrollTop($console[0].scrollHeight);

		indexing_state = 'stopped';
		updateIndexerUI();
	}

	// Index clearing trigger
	$('#tsbifw-reset-indexing').on('click', function() {
		if (!confirm(tsbifw_admin_params.confirm)) return;

		var $btn = $(this);
		$btn.prop('disabled', true).text('Clearing...');
		$('#tsbifw-start-indexing').prop('disabled', true);

		$.ajax({
			url: tsbifw_admin_params.ajax_url,
			type: 'POST',
			data: {
				action: 'tsbifw_clear_index',
				security: tsbifw_admin_params.nonce
			},
			success: function(response) {
				if (response.success) {
					var stats = response.data.stats;

					// Reset counter DOM
					$('#tsbifw-stat-total').text(stats.total);
					$('#tsbifw-stat-indexed').text(stats.indexed);
					$('#tsbifw-stat-skipped').text(stats.skipped);
					$('#tsbifw-stat-errors').text(stats.errors);

					// Reset progress fill
					$('.tsbifw-progress-fill').css('width', '0%');
					$('#tsbifw-progress-percent').text('0');
					$('#tsbifw-processed-count').text('0');
					$('#tsbifw-total-count').text(stats.total);

					// Clean logs console
					$('#tsbifw-log-console').html('');
					$('.tsbifw-log-output').hide();
					$('.tsbifw-progress-wrapper').hide();

					alert(response.data.msg);
				} else {
					alert(response.data ? response.data : 'Failed to clear indexing data.');
				}
				$btn.text('Clear / Reset Index').prop('disabled', false);
				$('#tsbifw-start-indexing').prop('disabled', false);
			},
			error: function() {
				alert('Network error occurred while clearing the indexing data.');
				$btn.text('Clear / Reset Index').prop('disabled', false);
				$('#tsbifw-start-indexing').prop('disabled', false);
			}
		});
	});

	// Load models list dynamically based on active or selected gateway
	function loadModelsForGateway(gateway) {
		var $embSkeleton = $('#tsbifw-embeddings-model-skeleton');
		var $embeddingSelect = $('#tsbifw_embeddings_model');

		if (!$embSkeleton.length) return;

		gateway = gateway || $('#tsbifw_api_gateway').val() || 'openrouter';

		$embSkeleton.show().text('').css('animation', '');
		$embeddingSelect.hide().empty();
		$(document).trigger('tsbifw_before_load_models', [gateway]);

		$.ajax({
			url: tsbifw_admin_params.ajax_url,
			type: 'POST',
			data: {
				action: 'tsbifw_fetch_openrouter_models',
				gateway: gateway,
				security: tsbifw_admin_params.nonce
			},
			success: function(response) {
				if (response.success) {
					var selectedEmbedding = $embeddingSelect.data('selected');

					// Populate embeddings
					if (response.data.embedding_models && response.data.embedding_models.length > 0) {
						response.data.embedding_models.forEach(function(model) {
							var isSelected = (model.id == selectedEmbedding) ? 'selected' : '';
							$embeddingSelect.append('<option value="' + model.id + '" ' + isSelected + '>' + (model.name || model.id) + '</option>');
						});
					}

					// Hide skeleton and show select dropdown
					$embSkeleton.hide();
					$embeddingSelect.show();

					$(document).trigger('tsbifw_models_loaded', [response.data, gateway]);
				} else {
					$('.tsbifw-skeleton-loader').text('Failed to load models.').css('animation', 'none');
				}
			},
			error: function() {
				$('.tsbifw-skeleton-loader').text('Failed to load models.').css('animation', 'none');
			}
		});
	}

	if ($('#tsbifw-embeddings-model-skeleton').length) {
		loadModelsForGateway();
	}

	function copyTextToClipboard(text, $btn, originalLabel) {
		if (!text) return;
		var label = originalLabel || $btn.text();
		function markCopied() {
			$btn.text('Copied!').prop('disabled', true);
			setTimeout(function() {
				$btn.text(label).prop('disabled', false);
			}, 2000);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(markCopied).catch(function() {
				fallbackCopy(text, markCopied);
			});
		} else {
			fallbackCopy(text, markCopied);
		}
	}

	function fallbackCopy(text, callback) {
		var $temp = $('<textarea>');
		$('body').append($temp);
		$temp.val(text).select();
		document.execCommand('copy');
		$temp.remove();
		if (typeof callback === 'function') {
			callback();
		}
	}

	// Copy logs to clipboard
	$('#tsbifw-copy-logs').on('click', function() {
		var logText = $('#tsbifw-log-console').text();
		copyTextToClipboard(logText, $(this), 'Copy Logs');
	});

	function startAdminScanningAnimation() {
		var $wrap = $('#tsbifw-admin-preview-wrapper');
		$wrap.find('.tsbifw-scan-container').show().addClass('tsbifw-scan-active');
	}

	function stopAdminScanningAnimation() {
		var $wrap = $('#tsbifw-admin-preview-wrapper');
		$wrap.find('.tsbifw-scan-container').hide().removeClass('tsbifw-scan-active');
	}

	// 3. Test Search Tab Functionality
	if ($('#tsbifw-admin-drag-zone').length) {
		var $adminDragZone = $('#tsbifw-admin-drag-zone');
		var $adminFileInput = $('#tsbifw-admin-file-input');
		var $adminCameraInput = $('#tsbifw-admin-camera-input');

		$('#tsbifw-admin-camera-btn').on('click', function(e) {
			e.preventDefault();
			if ($adminCameraInput.length) {
				$adminCameraInput[0].click();
			}
		});

		$adminCameraInput.on('click', function(e) {
			e.stopPropagation();
		});

		$adminCameraInput.on('change', function() {
			if (this.files && this.files[0]) {
				handleAdminFileSelection(this.files[0]);
			}
		});

		$adminDragZone.on('click', function() {
			if ($adminFileInput.length) {
				$adminFileInput[0].click();
			}
		});

		$adminFileInput.on('click', function(e) {
			e.stopPropagation();
		});

		$adminFileInput.on('change', function(e) {
			if (this.files && this.files[0]) {
				handleAdminFileSelection(this.files[0]);
			}
		});

		$adminDragZone.on('dragover dragenter', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$adminDragZone.addClass('dragover');
		});

		$adminDragZone.on('dragleave drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$adminDragZone.removeClass('dragover');
		});

		$adminDragZone.on('drop', function(e) {
			var files = e.originalEvent.dataTransfer.files;
			if (files && files[0]) {
				handleAdminFileSelection(files[0]);
			}
		});

		$('#tsbifw-admin-search-btn').on('click', function() {
			var canvasEl = document.getElementById('tsbifw-admin-cropper-canvas');
			if (!canvasEl) return;
			var selection = canvasEl.querySelector('cropper-selection');
			if (!selection) return;

			startAdminScanningAnimation();
			startAdminStatusRotation();
			$('#tsbifw-admin-results-grid').hide().html('');

			selection.$toCanvas({
				width: 512,
				height: 512
			}).then(function(canvas) {
				canvas.toBlob(function(blob) {
					if (!blob) {
						stopAdminStatusRotation();
						stopAdminScanningAnimation();
						$('#tsbifw-admin-search-status').hide().removeClass('pulse').text('');
						alert('Failed to process cropped image.');
						return;
					}
					var croppedFile = new File([blob], 'cropped_search.jpg', { type: 'image/jpeg' });
					uploadAdminSearchImage(croppedFile);
				}, 'image/jpeg', 0.9);
			}).catch(function() {
				stopAdminStatusRotation();
				stopAdminScanningAnimation();
				$('#tsbifw-admin-search-status').hide().removeClass('pulse').text('');
				alert('Failed to crop image.');
			});
		});

		$('#tsbifw-admin-reselect-btn').on('click', function() {
			resetAdminSearchUI();
		});
	}

	var adminStatusInterval = null;

	function startAdminStatusRotation() {
		if (adminStatusInterval) {
			clearInterval(adminStatusInterval);
		}

		var phrases = [
			tsbifw_admin_params.strings.scanning,
			'Analyzing colors and shapes...',
			'Comparing visual textures...',
			'Querying product database...',
			'Finalizing search results...'
		];
		var index = 0;

		$('#tsbifw-admin-search-status').text(phrases[0]).addClass('pulse').show();

		adminStatusInterval = setInterval(function() {
			index = (index + 1) % phrases.length;
			$('#tsbifw-admin-search-status').fadeOut(200, function() {
				$(this).text(phrases[index]).fadeIn(200);
			});
		}, 1800);
	}

	function stopAdminStatusRotation() {
		if (adminStatusInterval) {
			clearInterval(adminStatusInterval);
			adminStatusInterval = null;
		}
	}

	function resetAdminSearchUI() {
		stopAdminStatusRotation();
		$('#tsbifw-admin-file-input').val('');
		$('#tsbifw-admin-camera-input').val('');
		var previewImg = document.getElementById('tsbifw-admin-preview-image');
		if (previewImg) {
			previewImg.setAttribute('src', '');
		}
		$('#tsbifw-admin-cropper-canvas').hide();
		$('#tsbifw-admin-preview-wrapper').hide();
		$('#tsbifw-admin-drag-zone').show();
		stopAdminScanningAnimation();
		$('#tsbifw-admin-search-status').hide().removeClass('pulse').text('');
		$('#tsbifw-admin-results-toolbar').hide();
		$('#tsbifw-admin-results-grid').hide().html('');
	}

	function handleAdminFileSelection(file) {
		if (!file.type.match('image.*')) {
			alert('Please select a valid image file (JPEG, PNG, WEBP).');
			return;
		}

		if (tsbifw_admin_params.max_upload_size && file.size > tsbifw_admin_params.max_upload_size) {
			alert(tsbifw_admin_params.strings.file_too_large);
			return;
		}

		var reader = new FileReader();
		reader.onload = function(e) {
			$('#tsbifw-admin-drag-zone').hide();
			$('#tsbifw-admin-preview-wrapper').show();
			$('#tsbifw-admin-cropper-canvas').show();

			var image = document.getElementById('tsbifw-admin-preview-image');
			if (image) {
				image.setAttribute('src', e.target.result);
				if (typeof image.$ready === 'function') {
					image.$ready().then(function() {
						setTimeout(function() {
							var canvas = document.getElementById('tsbifw-admin-cropper-canvas');
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

			$('#tsbifw-admin-results-toolbar').hide();
			$('#tsbifw-admin-results-grid').hide().html('');
			$('#tsbifw-admin-search-status').hide().text('');
		};
		reader.readAsDataURL(file);
	}

	function uploadAdminSearchImage(file) {
		if (tsbifw_admin_params.max_upload_size && file.size > tsbifw_admin_params.max_upload_size) {
			alert(tsbifw_admin_params.strings.file_too_large);
			stopAdminStatusRotation();
			stopAdminScanningAnimation();
			$('#tsbifw-admin-search-status').hide().removeClass('pulse').text('');
			return;
		}

		var formData = new FormData();
		formData.append('image', file);
		formData.append('sandbox', '1');
		if ($('#tsbifw-admin-include-hidden').is(':checked')) {
			formData.append('include_hidden', '1');
		}
		formData.append('security', tsbifw_admin_params.nonce);

		$.ajax({
			url: tsbifw_admin_params.search_endpoint,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsbifw_admin_params.wp_rest_nonce);
			},
			success: function(response) {
				stopAdminStatusRotation();
				stopAdminScanningAnimation();
				$('#tsbifw-admin-search-status').hide().removeClass('pulse');

				var products = Array.isArray(response) ? response : (response && response.products ? response.products : []);
				var token = response && response.token ? response.token : '';
				var redirectUrl = response && response.redirect_url ? response.redirect_url : '';

				var $grid = $('#tsbifw-admin-results-grid');
				var $toolbar = $('#tsbifw-admin-results-toolbar');
				$grid.html('').show();

				if (!products || products.length === 0) {
					$toolbar.hide();
					$grid.append('<div class="tsbifw-empty-msg">' + tsbifw_admin_params.strings.no_results + '</div>');
					return;
				}

				if (redirectUrl) {
					$('#tsbifw-admin-view-in-store').attr('href', redirectUrl);
					$('#tsbifw-admin-results-count').text(
						products.length === 1 ? '1 product matched' : products.length + ' products matched'
					);
					$toolbar.css('display', 'flex');
				} else {
					$toolbar.hide();
				}

				products.forEach(function(product) {
					var badgeHtml = product.score ? '<span class="tsbifw-similarity-badge">' + tsbifw_admin_params.strings.similarity_label + ' ' + product.score + '</span>' : '';
					var hiddenBadgeHtml = product.is_catalog_visible === false ? '<span class="tsbifw-hidden-badge">' + (tsbifw_admin_params.strings.hidden_from_catalog || 'Hidden from Catalog') + '</span>' : '';
					var cartBtnHtml = product.is_in_stock ? '<a href="' + product.add_to_cart_url + '" target="_blank" class="tsbifw-btn tsbifw-btn-primary tsbifw-trackable-link">' + tsbifw_admin_params.strings.add_to_cart + '</a>' : '';

					var cardHtml =
						'<div class="tsbifw-product-card" data-product-id="' + product.id + '">' +
							badgeHtml +
							hiddenBadgeHtml +
							'<div class="tsbifw-card-image-wrapper">' +
								'<a href="' + product.permalink + '" target="_blank" class="tsbifw-trackable-link">' +
									'<img class="tsbifw-card-image" src="' + product.image + '" alt="' + product.title + '" />' +
								'</a>' +
							'</div>' +
							'<div class="tsbifw-card-body">' +
								'<h4 class="tsbifw-card-title">' +
									'<a href="' + product.permalink + '" target="_blank" class="tsbifw-trackable-link">' + product.title + '</a>' +
								'</h4>' +
								'<div class="tsbifw-card-price">' + product.price_html + '</div>' +
								'<div class="tsbifw-card-actions">' +
									'<a href="' + product.permalink + '" target="_blank" class="tsbifw-btn tsbifw-btn-secondary tsbifw-trackable-link">' + tsbifw_admin_params.strings.view_product + '</a>' +
									cartBtnHtml +
								'</div>' +
							'</div>' +
						'</div>';

					$grid.append(cardHtml);
				});

				// Track clicks on sandbox product cards for CTR parity and testing
				if (token && tsbifw_admin_params.track_endpoint) {
					$grid.find('.tsbifw-trackable-link').on('click', function() {
						var $card = $(this).closest('.tsbifw-product-card');
						var productId = parseInt($card.data('product-id'), 10);
						if (productId > 0) {
							var payload = JSON.stringify({
								token: token,
								product_id: productId,
								security: (tsbifw_admin_params && tsbifw_admin_params.nonce) || ''
							});
							if (navigator.sendBeacon) {
								var blob = new Blob([payload], { type: 'application/json' });
								navigator.sendBeacon(tsbifw_admin_params.track_endpoint, blob);
							} else if (window.fetch) {
								fetch(tsbifw_admin_params.track_endpoint, {
									method: 'POST',
									headers: { 'Content-Type': 'application/json' },
									body: payload,
									keepalive: true
								});
							}
						}
					});
				}
			},
			error: function(xhr) {
				stopAdminStatusRotation();
				stopAdminScanningAnimation();

				var errorMsg = tsbifw_admin_params.strings.error;
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMsg = xhr.responseJSON.message;
				} else if (xhr.statusText && xhr.statusText !== 'error') {
					errorMsg += ' (' + xhr.status + ': ' + xhr.statusText + ')';
				}
				$('#tsbifw-admin-search-status').text(errorMsg).removeClass('pulse').show();
			}
		});
	}

	// Copy admin logs tab logs to clipboard
	$('#tsbifw-admin-copy-logs').on('click', function() {
		var logLines = [];
		$('.tsbifw-logs-table tbody tr').each(function() {
			var timestamp = $(this).find('td:eq(0)').text().trim();
			var message = $(this).find('td:eq(1)').text().trim();
			var context = $(this).find('td:eq(2)').text().trim();
			logLines.push('[' + timestamp + '] ' + message + (context && context !== '-' ? '\nContext: ' + context : ''));
		});
		var logText = logLines.length ? logLines.join('\n\n') : 'No logs recorded.';
		copyTextToClipboard(logText, $(this), 'Copy Logs');
	});

	// Clear admin logs tab logs
	$('#tsbifw-admin-clear-logs').on('click', function() {
		if (!confirm('Are you sure you want to clear all system logs? This cannot be undone.')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text('Clearing...');

		$.ajax({
			url: tsbifw_admin_params.ajax_url,
			type: 'POST',
			data: {
				action: 'tsbifw_clear_logs',
				security: tsbifw_admin_params.nonce
			},
			success: function(response) {
				if (response.success) {
					$('.tsbifw-log-list-wrapper').html('<p class="description">No logs recorded yet.</p>');
					alert(response.data.msg);
				} else {
					alert(response.data ? response.data : 'Failed to clear logs.');
				}
				$btn.text('Clear Logs').prop('disabled', false);
			},
			error: function() {
				alert('Network error occurred while clearing system logs.');
				$btn.text('Clear Logs').prop('disabled', false);
			}
		});
	});
});

