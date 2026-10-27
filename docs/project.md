# Project Documentation

## Overview

Searchips Search By Image for WooCommerce (version 1.8.0) is a visual search engine for WooCommerce stores. It enables shoppers to search for matching products using photos and images powered by Multimodal Vector Embeddings (Strategy 1) via OpenRouter.

The plugin provides vector embedding similarity matching, an in-admin test search sandbox, batch indexing with Action Scheduler async support, 6 futuristic scanning animation effects, comprehensive WP-CLI commands, and a dedicated Pro Upgrade sidebar card linking to the Pro edition.

### Design Decisions & Assumptions:
- Search Strategy Setting: Replaced the dropdown in Free with a read-only indicator for "Strategy 1: Multimodal Vector Embeddings (Recommended)" accompanied by a hidden input defaulting to `embeddings`.
- Backend Processing: Removed Strategy 2 vision description generation, caching, and Jaccard scoring logic completely from Free.
- Existing Database Options: Retained existing Strategy 2 options and metadata untouched in the database to prevent data loss if upgrading to Pro.
- Pro Upsell Card: Displayed at the top of the sidebar across all settings tabs (General, Styling, Indexer, Test Search, Logs), presenting genuine Pro-only features (Visual Search Analytics, Variation Images, Smart Image Hashing, Direct OpenAI & Gemini Gateways, Mobile Camera Instant Capture, and Similarity Boost) with direct link to `https://violo.ir/?p=707`. Any features already available in Free (such as scanning animation effects) are strictly excluded from the card.

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
- `includes/class-tsbifw-admin.php`: Registers admin settings pages with static Strategy 1 indicator, asset enqueueing, AJAX endpoints for batch indexing, test sandbox, gateway options, preview stages for all 6 scanning effects, and the Pro Upgrade sidebar card (`render_sidebar_pro_upsell_card()`).
- `includes/class-tsbifw-search.php`: Manages frontend search shortcodes, on-demand lazy loading of Cropper.js, REST API search route (`tsbifw/v1/search`), rate limiting, and vector similarity calculations.
- `includes/class-tsbifw-cli.php`: Integrates WP-CLI commands (`wp searchips status`, `wp searchips index`, `wp searchips clear`).
- `assets/css/admin.css`: Admin styles for settings dashboard, preview mockup stages, scanning effect keyframe animations, and Pro upsell sidebar card.
- `assets/css/frontend.css`: Frontend styles for search camera trigger button, modal dialog, and 6 visual scanning animation effects.
- `assets/js/admin.js`: Backend settings UI management, dynamic model fetching, and preview triggers.
- `assets/js/frontend.js`: Frontend modal management, Cropper.js integration, and dynamic scanning effect layer injection.
- `uninstall.php`: Comprehensive uninstaller removing active plugin options, transients, post metadata (`_tsbifw_vector`, `_tsbifw_vectors`), cron hooks, and Action Scheduler actions upon plugin deletion (leaving Pro options and descriptions untouched).

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

# Run Pro card unit test
rtk php tests/test-sidebar-upsell.php

# Verify JavaScript syntax
rtk node -c assets/js/admin.js assets/js/frontend.js
```
