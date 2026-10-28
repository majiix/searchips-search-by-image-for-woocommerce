# Project Documentation

## Overview

Searchips Search By Image for WooCommerce (version 1.8.0) is a visual search engine for WooCommerce stores. It enables shoppers to search for matching products using photos and images powered by Multimodal Vector Embeddings via OpenRouter.

The plugin provides vector embedding similarity matching, an in-admin test search sandbox, batch indexing with Action Scheduler async support, 6 futuristic scanning animation effects, comprehensive WP-CLI commands, and a dedicated Pro Upgrade sidebar card linking to the standalone Pro edition.

### Design Decisions & Assumptions:
- Search Strategy: Visual search engine is permanently set to native Multimodal Vector Embeddings. Configurable strategy inputs have been removed from the Free settings UI in favor of an informational overview, and residual option keys (`tsbifw_strategy`) have been purged from `uninstall.php`.
- Backend Processing: Pure Multimodal Vector Search engine. Removed Strategy 2 vision description generation, caching, and text-based scoring logic completely from the Free codebase.
- AI Provider Gateway UI: When only a single gateway is registered (`OpenRouter API`), the settings UI renders a clean, static badge (`.tsbifw-badge-gateway`) along with a hidden input (`tsbifw_api_gateway`), eliminating redundant 1-item dropdown selectors while maintaining seamless option storage compatibility.
- Translation Catalog Hygiene: The translation template (`languages/searchips-search-by-image-for-woocommerce.pot`) is strictly purged of orphaned Pro strings (`Take Photo`, `or drag and drop below`, `Smart Image Hashing (MD5)`, `Visit the %s page.`, `or`). All string references point strictly to active Free codebase line numbers.
- WordPress 6.7 Textdomain Loading & Cron Schedules: Custom cron schedules (`add_custom_cron_schedules`) guard translatable labels with `did_action( 'init' )` so early calls (from Action Scheduler or cron runners during `plugins_loaded`) return fallback English strings without triggering premature `_load_textdomain_just_in_time` notices. The textdomain is formally loaded on the `init` action via `load_plugin_textdomain()`.
- Readme Header Alignment: Maintained `Tested up to: 7.1` per user direction to declare forward compatibility with upcoming WordPress releases.
- Data Handling & Uninstall: The uninstaller operates in pure standalone mode without hardcoded Pro sibling checks. Before performing any cleanup, Free checks the `tsbifw_allow_uninstall_data_deletion` filter hook (defaulting to true). If the filter returns false (e.g. when Pro is active), `uninstall.php` immediately halts to safeguard shared data, options, vectors, and cron jobs. When deletion is allowed and the user has explicitly opted into data deletion (`tsbifw_delete_data_on_uninstall` === 'yes'), Free performs clean removal of active options, transients, and vectors (`_tsbifw_vector`, `_tsbifw_vectors`, `_tsbifw_indexed_status`, `_tsbifw_index_error`). By default, data deletion is disabled ('no') to safeguard store configuration. Scheduled cron jobs and Action Scheduler queues are cleaned up on deletion when data deletion is permitted.
- Threshold & Option Standardization: Similarity thresholding is standardized exclusively around `tsbifw_exclude_below_percent` (0% to 100%, default 40%). Obsolete float keys (`tsbifw_similarity_threshold`) have been completely purged from the codebase.
- UI and Markup Hygiene: Table rows and CSS rules are stripped of Pro-derived classes (`.tsbifw-strategy-field`, `.embeddings-field`, `.tsbifw-gateway-field`, `.gateway-openrouter`). OpenRouter fallback models strictly contain multimodal embedding options (`google/gemini-embedding-2`).
- Post-Type & Image Targets: Free indexing strictly targets parent products (`product` post type). Save hooks (`on_meta_update`, `on_meta_delete`) guard against non-product types without residual `product_variation` branches.
- Test Search & Camera Features: Camera capture is exclusively a Pro capability. The admin Test Search tab provides drag-and-drop and file upload with zero camera input controls. In the frontend search modal, DOM placeholders (`.tsbifw-modal-extra-actions`) and orphaned listeners (`tsbifw_select_file`) have been removed.
- Pro Upsell Card: Displayed at the top of the sidebar across settings tabs, labeled as "PRO EDITION" with an "Upgrade to Pro" action button, presenting genuine Pro-only features (Visual Search Analytics, Variation Images, Smart Image Hashing, Direct OpenAI & Gemini Gateways, Mobile Camera Instant Capture, and Similarity Boost) with a direct link to `https://violo.ir/?p=707`. Any features already available in Free (such as scanning animation effects) are strictly excluded from the card.
- Mutual Exclusion & Fork Safety: Runtime guard `TSBIFW_INITIALIZED` prevents double initialization between Free and Pro editions regardless of load order. All constants are guarded with `defined()` and procedural functions with `function_exists()`.

## Tech Stack

- **PHP**: 7.4+ (Tested up to PHP 8.3)
- **WordPress**: 5.6+ (Tested up to 7.1)
- **WooCommerce**: 5.0+ (Tested up to 11.1)
- **Database**: MySQL / MariaDB (standard WooCommerce and WordPress tables)
- **JavaScript**: Vanilla ES6+ and jQuery
- **CSS**: Custom vanilla CSS with responsive layout support
- **Cropper.js**: v2.1.1 (Web Components-based client-side image cropping)
- **Remote APIs**:
  - OpenRouter API (multimodal embeddings)

## Dependencies & Integrations

- **WordPress Core**: Settings API, REST API, WP Cron, WP_Query, Transients API, and Media Library hooks.
- **WooCommerce**:
  - Declares compatibility with High-Performance Order Storage (`custom_order_tables`).
  - Declares compatibility with Cart and Checkout Blocks (`cart_checkout_blocks`).
  - Supports WooCommerce `WC_Product::is_viewable()` and catalog visibility filters.
  - Action Scheduler support for background indexing bulk actions.
- **Cropper.js**: Bundled in `assets/js/cropper.min.js`.
- **WP-CLI**: Integrated commands under the `wp searchips` namespace.

## Architecture & File Structure

The plugin is structured into modular components:

- `searchips-search-by-image-for-woocommerce.php`: Main plugin entry file, declares constants (`TSBIFW_VERSION`), declares WooCommerce feature compatibility, defines public helper functions (`tsbifw_index_product()`, `tsbifw_get_indexing_stats()`, `tsbifw_get_active_gateway()`, `tsbifw_get_api_key()`, `tsbifw_prepare_image()`), binds action listeners, bootstraps core modules (`TSBIFW_API`, `TSBIFW_Indexer`, `TSBIFW_Admin`, `TSBIFW_Search`), registers WP-CLI commands, and clears scheduled cron jobs on deactivation.
- `includes/class-tsbifw-logger.php`: Handles diagnostic logging supporting WooCommerce file logs (`wc_get_logger()`), non-blocking database writes for high-concurrency requests, and autoload-free option storage with configurable retention.
- `includes/class-tsbifw-api.php`: Handles OpenRouter API communication, model fetching, endpoint resolution, custom headers, response parsing, image resizing, and base64 encoding for embeddings.
- `includes/class-tsbifw-indexer.php`: Handles product image indexing (parent featured and gallery images), batch queue priming, custom cron intervals, WooCommerce product list bulk actions, and Action Scheduler async workers.
- `includes/class-tsbifw-admin.php`: Registers admin settings pages, asset enqueueing, AJAX endpoints for batch indexing, test sandbox, gateway options, preview stages for all 6 scanning effects, and the Pro Upgrade sidebar card (`render_sidebar_pro_upsell_card()`).
- `includes/class-tsbifw-search.php`: Manages frontend search shortcodes, on-demand lazy loading of Cropper.js, REST API search route (`tsbifw/v1/search`), rate limiting, and vector similarity calculations.
- `includes/class-tsbifw-cli.php`: Integrates WP-CLI commands (`wp searchips status`, `wp searchips index`, `wp searchips clear`) with direct procedural indexer execution and class fallback.
- `assets/css/admin.css`: Admin styles for settings dashboard, preview mockup stages, scanning effect keyframe animations, and Pro upsell sidebar card.
- `assets/css/frontend.css`: Frontend styles for search camera trigger button, modal dialog, and 6 visual scanning animation effects.
- `assets/js/admin.js`: Backend settings UI management, dynamic model fetching, and preview triggers.
- `assets/js/frontend.js`: Frontend modal management, Cropper.js integration, and dynamic scanning effect layer injection.
- `uninstall.php`: Comprehensive standalone uninstaller that performs clean removal of active options, transients, and vectors when permitted by the `tsbifw_allow_uninstall_data_deletion` filter and user opts into data deletion (`tsbifw_delete_data_on_uninstall` === 'yes'). Halts immediately without touching data or cron queues when a sibling plugin (such as Pro) filters data deletion. Clears scheduled cron hooks and Action Scheduler actions upon deletion when permitted.

## Current Features

1. **Multimodal Vector Search**: Fast and accurate image matching using high-dimensional AI vector embeddings.
2. **OpenRouter AI Gateway**: Native connection to OpenRouter for multimodal embeddings models.
3. **6 Futuristic Scanning Animations**: Laser Sweep, AI Vision Reticle, Sonar Radar Sweep, Digital Mesh Grid, Concentric Ripple, and Luxury Hologram Shimmer with live interactive preview stage.
4. **WooCommerce Bulk Actions & Action Scheduler**: Index single or multiple products from the standard WooCommerce Products list with async background processing.
5. **WP-CLI Command Integration**: Manage indexing and status directly from the command line (`wp searchips status`, `wp searchips index`, `wp searchips clear`).
6. **Client-Side Image Cropping**: Integrated Cropper.js v2.1.1 Web Component allows shoppers to crop and focus on specific areas of an image.
7. **Instant Auto-Indexing on Save**: Automatically indexes or re-indexes products immediately upon publishing or updating images.
8. **Dedicated System Logs Tab**: View recorded events, copy diagnostic logs, clear history, and configure log retention.
9. **Pro Upgrade Sidebar Card**: Conversion-optimized sidebar card highlighting genuine Pro-only capabilities (Visual Search Analytics, Variation Images, MD5 Hashing, Direct OpenAI & Gemini, Mobile Camera, Similarity Boost) with direct purchase link to `https://violo.ir/?p=707`.

## Verification Commands

Run the following commands to verify code syntax and integrity:

```powershell
# Verify syntax across all PHP files
rtk php -l searchips-search-by-image-for-woocommerce.php
rtk php -l uninstall.php
rtk php -l includes/class-tsbifw-logger.php
rtk php -l includes/class-tsbifw-api.php
rtk php -l includes/class-tsbifw-indexer.php
rtk php -l includes/class-tsbifw-admin.php
rtk php -l includes/class-tsbifw-search.php
rtk php -l includes/class-tsbifw-cli.php

# Verify JavaScript syntax
rtk node -c assets/js/admin.js assets/js/frontend.js

# Verify POT catalog cleanliness (zero residual Pro strings)
rtk git grep -n -E "(Take Photo|Visit the %s page|or drag and drop below)" languages/
```
