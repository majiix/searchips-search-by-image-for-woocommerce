# Project Documentation

## Overview

Searchips Search By Image for WooCommerce is a visual search plugin that enables shoppers to find WooCommerce products using images powered by machine learning models via the OpenRouter API. It provides multimodal vector embedding similarity matching as well as vision-to-text semantic search, complete with an in-admin test search sandbox, batch indexer, and customizable frontend camera triggers.

## Tech Stack

- **PHP**: 7.4+ (Tested up to PHP 8.3)
- **WordPress**: 5.6+ (Tested up to 6.8)
- **WooCommerce**: 5.0+ (Tested up to 11.1)
- **JavaScript**: Vanilla ES6+ and jQuery
- **CSS**: Custom vanilla CSS with responsive layout support
- **Cropper.js**: v2.1.1 (Web Components-based client-side image cropping)
- **Remote APIs**: OpenRouter API for multimodal embeddings and vision models

## Dependencies & Integrations

- **WordPress Core**: Settings API, REST API, WP Cron, WP_Query, Transients API, and Media Library hooks.
- **WooCommerce**:
  - Declares compatibility with High-Performance Order Storage (`custom_order_tables`).
  - Declares compatibility with Cart and Checkout Blocks (`cart_checkout_blocks`).
  - Supports WooCommerce 11.1.0 `WC_Product::is_viewable()` and catalog visibility filters.
- **Cropper.js**: Bundled in `assets/js/cropper.min.js`.

## Architecture

The plugin is structured into modular components:

- `searchips-search-by-image-for-woocommerce.php`: Main plugin entry file, declares constants, WooCommerce feature compatibility, and initializes core modules with contextual isolation (`TSBIFW_Admin` instantiated only for admin/AJAX requests).
- `includes/class-tsbifw-logger.php`: Handles diagnostic logging supporting WooCommerce file logs (`wc_get_logger()`), non-blocking database writes for high-concurrency requests, and autoload-free option storage.
- `includes/class-tsbifw-api.php`: Handles OpenRouter API communication, model fetching, image resizing, and base64 encoding.
- `includes/class-tsbifw-indexer.php`: Handles product image indexing, metadata mutations with early meta key guards, batch queue priming, custom prefixed cron intervals, and transient-cached O(1) attachment lookups.
- `includes/class-tsbifw-admin.php`: Registers admin settings pages, asset enqueueing (`tsbifw-cropperjs`, `tsbifw-admin-js`), consolidated status count queries with transient caching, AJAX endpoints for batch indexing with primed post caches, and test sandbox.
- `includes/class-tsbifw-search.php`: Manages frontend search shortcodes, on-demand lazy loading of Cropper.js, REST API search route (`tsbifw/v1/search`) with rate limiting (HTTP 429), bulk candidate cache pre-priming (`_prime_post_caches`), cursor-based similarity streaming, and query modifications.
- `uninstall.php`: Clean cleanup of options, metadata, transients, and cron hooks upon plugin deletion.

## Current Features

- **High-Traffic Scalability**: Pre-warmed object/post/taxonomy caches (`_prime_post_caches()`) eliminating N+1 queries during search scoring; non-blocking file-based WooCommerce logging under load; consolidated status count queries.
- **On-Demand Asset Loading**: Lightweight CSS and trigger JS loaded globally, with Cropper.js (40+ KB) lazy-loaded on demand only when the camera modal is opened.
- **Dual Search Strategies**: Multimodal Vector Embeddings (fast cosine similarity with pre-normalized query vectors) and Vision-to-Text (Jaccard similarity and semantic query).
- **Scalable Batch Streaming**: Cursor-based chunked streaming (`LIMIT 200`) across product postmeta avoiding PHP memory exhaustion and MySQL `max_allowed_packet` limits on large catalogs (tested up to 50,000+ products).
- **Product Viewability and Visibility**: Fully respects WooCommerce catalog visibility (`exclude-from-search`) and WooCommerce 11.1.0 viewability checks.
- **Dynamic Model Selection**: Real-time retrieval of available embedding and vision models from OpenRouter.
- **Frontend Camera Trigger**: Auto-injected into WooCommerce and theme search forms, or rendered via `[tsbifw_search_bar]` shortcode.
- **Image Cropping**: Client-side interactive image cropping using Cropper.js before submission.
- **Admin Test Search Sandbox**: Integrated backend testing tool with visual scanning indicators and formatted product cards.
- **Batch and Background Indexing**: AJAX progress indexer with pause/stop controls and automated WP Cron background tasks.
- **Diagnostic Logging**: Configurable in-database logs with clipboard copy and automatic delegation to WooCommerce logger files.

## Verification Commands

Run PHP syntax linting across the plugin files:

```powershell
rtk php -l searchips-search-by-image-for-woocommerce.php
rtk php -l uninstall.php
rtk php -l includes/class-tsbifw-admin.php
rtk php -l includes/class-tsbifw-api.php
rtk php -l includes/class-tsbifw-indexer.php
rtk php -l includes/class-tsbifw-logger.php
rtk php -l includes/class-tsbifw-search.php
```
