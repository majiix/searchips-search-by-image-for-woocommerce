# Project Documentation

## Overview

Searchips Search By Image for WooCommerce (version 1.6.0) is an image-based visual search plugin for WooCommerce stores. It enables shoppers to search for matching products using photos and images powered by machine learning models via the OpenRouter API. It provides multimodal vector embedding similarity matching (Strategy 1), an in-admin test search sandbox, a batch indexer, and customizable frontend camera triggers.

The plugin strictly adheres to WordPress.org Plugin Directory guidelines (including Guideline 5 prohibiting trialware, artificial limits on local features, and crippling settings forms). The Free plugin is 100% self-contained and operational on its own, with zero dependencies or hardcoded checks for commercial addons.

## Tech Stack

- **PHP**: 7.4+ (Tested up to PHP 8.3)
- **WordPress**: 5.6+ (Tested up to 6.7)
- **WooCommerce**: 5.0+ (Tested up to 11.1)
- **JavaScript**: Vanilla ES6+ and jQuery
- **CSS**: Custom vanilla CSS with responsive layout support
- **Cropper.js**: v2.1.1 (Web Components-based client-side image cropping)
- **Remote APIs**: OpenRouter API for multimodal vector embeddings

## Dependencies & Integrations

- **WordPress Core**: Settings API, REST API, WP Cron, WP_Query, Transients API, and Media Library hooks.
- **WooCommerce**:
  - Declares compatibility with High-Performance Order Storage (`custom_order_tables`).
  - Declares compatibility with Cart and Checkout Blocks (`cart_checkout_blocks`).
  - Supports WooCommerce 11.1.0 `WC_Product::is_viewable()` and catalog visibility filters.
- **Cropper.js**: Bundled in `assets/js/cropper.min.js`.

## Architecture

The plugin is structured into modular components:

- `searchips-search-by-image-for-woocommerce.php`: Main plugin entry file, declares constants (`TSBIFW_VERSION`), declares WooCommerce feature compatibility, defines public helper functions (`tsbifw_index_product()`, `tsbifw_get_indexing_stats()`, `tsbifw_get_active_gateway()`, `tsbifw_get_api_key()`, `tsbifw_prepare_image()`), binds the `tsbifw_index_product` action listener, initializes core modules, and fires `do_action( 'tsbifw_loaded' )`.
- `includes/class-tsbifw-logger.php`: Handles diagnostic logging supporting WooCommerce file logs (`wc_get_logger()`), non-blocking database writes for high-concurrency requests, and autoload-free option storage with full configurable retention.
- `includes/class-tsbifw-api.php`: Handles OpenRouter API communication, model fetching, image resizing, and base64 encoding. Filters API keys via `tsbifw_gateway_api_key`.
- `includes/class-tsbifw-indexer.php`: Handles product image indexing, metadata mutations, batch queue priming, custom prefixed cron intervals, and transient-cached O(1) attachment lookups. Decoupled via `tsbifw_product_target_images`, `tsbifw_skip_product_indexing`, `tsbifw_index_product_custom_strategy`, `tsbifw_clear_product_indexed_meta`, `tsbifw_clear_all_indexed_data`, and `tsbifw_after_product_indexed`.
- `includes/class-tsbifw-admin.php`: Registers admin settings pages, asset enqueueing (`tsbifw-cropperjs`, `tsbifw-admin-js`), consolidated status count queries with transient caching, AJAX endpoints for batch indexing, test sandbox, and informational upgrade cards. All settings forms are 100% operational with zero disabled or readonly inputs. Exposes `tsbifw_admin_after_settings_fields` for addon settings fields.
- `includes/class-tsbifw-search.php`: Manages frontend search shortcodes, on-demand lazy loading of Cropper.js, REST API search route (`tsbifw/v1/search`) with rate limiting (HTTP 429), bulk candidate cache pre-priming (`_prime_post_caches`), and query modifications. Decoupled via `tsbifw_custom_strategy_search_scores`, `tsbifw_custom_strategy_fallback_posts`, `tsbifw_search_term`, `tsbifw_candidate_scores`, `tsbifw_record_search`, and `tsbifw_record_click`.
- `assets/js/frontend.js`: Frontend modal management, Cropper.js integration, scanning effects, and decoupled events (`tsbifw_modal_initialized`, `tsbifw_modal_reset`, `tsbifw_select_file`).
- `assets/js/admin.js`: Backend settings UI management, dynamic model fetching, and decoupled events (`tsbifw_strategy_toggled`, `tsbifw_gateway_guidance_updated`, `tsbifw_before_load_models`, `tsbifw_models_loaded`).
- `uninstall.php`: Clean cleanup of core options, metadata, transients, and cron hooks upon plugin deletion. Fires `do_action( 'tsbifw_uninstall' )`.

## WordPress.org Guideline 5 Compliance Architecture

To strictly comply with WordPress.org Plugin Review Team requirements (especially Guideline 5 prohibiting trialware, dead code, crippled local settings, and artificial restrictions):

- **Zero Locked Form Fields**: Active settings forms (General, Styling, Indexer, Test Search, System Logs) contain no disabled form fields, lock badges, or nag messages. All rendered inputs are immediately actionable.
- **Zero Pro Hardcoding**: The Free plugin contains no references to Pro classes (`TSBIFW_Pro_Addon`, `TSBIFW_Pro_Analytics`), Pro constants (`TSBIFW_PRO_VERSION`), Pro meta keys (`_tsbifw_images_hash`, `_tsbifw_descriptions`), or internal helper methods (`is_pro_active()`).
- **Hook-Driven Decoupling**: All extension points are exposed through standard WordPress actions and filters. Addons inject their fields, tabs, and filters without modifying core plugin code.
- **Informational Pro Promotion Cards**: Non-disruptive, informational sidebar widgets (`render_pro_card()`) highlight features available in the Pro Addon. Commercial upgrade information is consolidated in the dedicated Upgrade tab (`tab=pro`) rather than inside active settings forms, preventing commercial translation catalog pollution. These are easily suppressed by addons via `tsbifw_show_upgrade_card` and `tsbifw_show_upgrade_tab`.
- **Zero Artificial Restrictions**: Core features have no artificial clamps or gates. Users can freely configure cache expiry, log retention, and cron schedules without restriction.
- **Self-Contained Data Management**: The Free plugin only creates, modifies, and deletes its own core options and metadata in `uninstall.php`. Pro database tables (e.g. analytics) and Pro options are managed exclusively by the Pro Addon.

## Extension Hooks and Filters

The core plugin provides the following hooks for extensions:

- `tsbifw_loaded`: Action fired once the core plugin has completely loaded.
- `tsbifw_register_settings`: Action fired inside `register_settings()` allowing addons to register options with the WordPress Settings API.
- `tsbifw_admin_settings_tabs`: Action fired inside the settings navigation to render custom tab links.
- `tsbifw_admin_settings_tab_content`: Action fired inside the settings dashboard to render custom tab content.
- `tsbifw_admin_gateway_fields`: Action fired inside the AI Provider table to render custom gateway API fields.
- `tsbifw_admin_after_settings_fields`: Action fired inside form headers to output additional settings fields and nonces.
- `tsbifw_settings_general_strategy_fields`: Action fired inside General tab to render strategy-specific fields.
- `tsbifw_admin_after_strategy_select`: Action fired beneath the Strategy dropdown to render strategy guidance notices.
- `tsbifw_admin_sidebar_gateways_guide`: Action fired in the admin sidebar API key guide card to render addon provider guides.
- `tsbifw_admin_sidebar_models_guide`: Action fired in the admin sidebar models help card.
- `tsbifw_admin_sidebar_strategies_guide`: Action fired in the admin sidebar strategies help card.
- `tsbifw_settings_general_after_images`: Action fired inside General tab after images checkboxes.
- `tsbifw_settings_general_after_media_column`: Action fired inside General tab after media column option.
- `tsbifw_settings_general_after_threshold`: Action fired inside General tab after similarity threshold options.
- `tsbifw_settings_general_after_auto_inject`: Action fired inside General tab after auto-inject options.
- `tsbifw_settings_styling_effects`: Action fired inside Styling tab to render custom scanner effect selector cards.
- `tsbifw_preview_stage_effects`: Action fired inside preview scanner markup to render custom scanning animation layers.
- `tsbifw_clear_product_indexed_meta`: Action fired when clearing product indexing metadata (`$product_id`).
- `tsbifw_clear_all_indexed_data`: Action fired when clearing all product indexing metadata.
- `tsbifw_allowed_gateways`: Filter array of allowed gateway slugs (e.g. `openrouter`, `openai`, `gemini`).
- `tsbifw_api_gateways`: Filter map of gateway slugs to human-readable labels.
- `tsbifw_gateway_api_key`: Filter to retrieve the API key for a specified gateway.
- `tsbifw_api_url`: Filter API endpoint URL based on gateway and action.
- `tsbifw_api_headers`: Filter API request headers based on gateway and action.
- `tsbifw_api_model`: Filter API model identifier based on gateway and action.
- `tsbifw_search_strategies`: Filter registered search strategy options.
- `tsbifw_index_product_custom_strategy`: Filter to handle indexing a product under custom search strategies.
- `tsbifw_custom_strategy_search_scores`: Filter to perform custom strategy search scoring on candidate products.
- `tsbifw_custom_strategy_fallback_posts`: Filter to supply fallback posts if custom strategy scoring yields no candidates.
- `tsbifw_search_term`: Filter to customize the search term token generated during visual search.
- `tsbifw_scanning_effects`: Filter registered visual scanning animation options.
- `tsbifw_product_target_images`: Filter image attachment targets for a product during indexing.
- `tsbifw_skip_product_indexing`: Filter boolean to skip indexing a specific product.
- `tsbifw_after_product_indexed`: Action fired after a product has been successfully indexed.
- `tsbifw_candidate_scores`: Filter candidate search similarity scores map.
- `tsbifw_record_search`: Action fired when a visual search is performed.
- `tsbifw_record_click`: Action fired when a search result click is tracked.
- `tsbifw_show_upgrade_card`: Filter boolean to show or hide the upgrade sidebar card.
- `tsbifw_show_upgrade_tab`: Filter boolean to show or hide the upgrade tab link and content.
- `tsbifw_uninstall`: Action fired during core plugin uninstallation.

## Current Features

- **High-Traffic Scalability**: Pre-warmed object/post/taxonomy caches (`_prime_post_caches()`) eliminating N+1 queries during search scoring; non-blocking file-based WooCommerce logging under load; consolidated status count queries.
- **On-Demand Asset Loading**: Lightweight CSS and trigger JS loaded globally, with Cropper.js (40+ KB) lazy-loaded on demand only when the camera modal is opened.
- **OpenRouter AI Gateway**: Built-in support for OpenRouter multimodal embeddings and vision models, with dynamic model fetching and selection.
- **Vector Embedding Search**: Multimodal vector embedding similarity matching with persistent object caching and fallback transients.
- **Frontend Camera Trigger**: Auto-injected into WooCommerce and theme search forms, or rendered via `[tsbifw_search_bar]` shortcode, with customizable position offsets, icon size, and background colors.
- **Interactive Image Cropper**: Client-side interactive image cropping using Cropper.js before submission.
- **Admin Test Search Sandbox**: Integrated backend testing tool featuring parity with the frontend shopper search modal, including diagnostic catalog visibility toggles and real-time scanning animation previews.
- **Batch, Background, and Instant Indexing**: Instant auto-indexing on save/publish, AJAX progress indexer with pause/stop controls, and automated WP Cron background tasks.
- **Diagnostic Logging**: In-database logs with clipboard copy, automatic delegation to WooCommerce logger files, and configurable retention periods.
- **Media Library Integration**: Status column and filter dropdown in the WordPress Media Library list view, with configurable toggle.

## Verification Commands

Run PHP syntax linting across all files:

```powershell
rtk php -l searchips-search-by-image-for-woocommerce.php
rtk php -l uninstall.php
rtk php -l includes/class-tsbifw-admin.php
rtk php -l includes/class-tsbifw-api.php
rtk php -l includes/class-tsbifw-indexer.php
rtk php -l includes/class-tsbifw-logger.php
rtk php -l includes/class-tsbifw-search.php
```
