=== Searchips Search By Image for WooCommerce ===
Contributors: micromax2
Tags: woocommerce, search, image search, search by image, vector search
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 11.1
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable customers to search WooCommerce products using images powered by machine learning.

== Description ==

Searchips Search By Image for WooCommerce brings visual search to your online store. Shoppers can upload an image from desktop or mobile devices to find matching and visually similar products in seconds.

The free version provides full, unrestricted visual search functionality: unlimited catalog indexing, high-accuracy multimodal vector search via OpenRouter, interactive client-side image cropping, real-time auto-indexing on save, customizable camera triggers, scanner glow accent colors, and an interactive live preview stage.

The settings forms in the free version are 100% clean and fully functional, adhering strictly to WordPress.org guidelines with zero disabled form fields, locked checkboxes, or trialware nag traps.

The plugin powers visual search using Multimodal Vector Embeddings: matches visual features using advanced machine learning models (Strategy 1).

== External Services ==

This plugin connects to external AI services to perform image-based searches and catalog indexing using artificial intelligence models:

1. OpenRouter (https://openrouter.ai):
- What the service is and what it is used for: OpenRouter is an API gateway used to generate multimodal vector embeddings from product and query images.
- What data is sent and when: Base64-encoded image data and model configuration parameters are sent to OpenRouter when products are indexed or when customers submit a visual search query.
- Terms of Service: https://openrouter.ai/terms
- Privacy Policy: https://openrouter.ai/privacy

== Copyright and Third-Party Assets ==

This plugin bundles and relies on the following third-party software:

* Cropper.js (v2.1.1)
  Source: https://github.com/fengyuanchen/cropperjs
  License: MIT
  License URI: https://github.com/fengyuanchen/cropperjs/blob/main/LICENSE
  Copyright: (c) 2015-present Chen Fengyuan

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/searchips-search-by-image-for-woocommerce` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your settings under WooCommerce -> Search by Image.
4. Run the Product Indexer to process product images.

== Features ==

* **Multimodal Vector Search**: Fast and accurate visual similarity matching powered by AI embeddings.
* **Unlimited Product Indexing**: Asynchronous AJAX indexer processes catalogs of any size with pause and stop controls.
* **Instant Auto-Indexing**: Automatically indexes or re-indexes products immediately upon publishing or updating images.
* **Interactive Image Cropping**: Integrated Cropper.js allows shoppers to crop and focus on specific image details.
* **Visual Scanning Animation & Live Preview**: Classic Laser Line neon scanning effect with customizable accent glow color and real-time live preview stage in settings.
* **OpenRouter AI Gateway**: Seamless connectivity to OpenRouter for multimodal image embeddings.
* **Frontend Camera Trigger**: Automatically placed inside search forms or displayed with the `[tsbifw_search_bar]` shortcode.
* **Full Styling Customization**: Live color picker, custom icon dimensions, and left/right offsets.
* **WordPress Reading Settings Integration**: Frontend search result count automatically adheres to WordPress archive post limits (`posts_per_page`).
* **Dedicated System Logs Tab**: View recorded events, copy logs, clear history, and configure retention and logging preferences.
* **High-Performance Architecture**: Bulk pre-primed post caches (`_prime_post_caches`), cursor-based streaming, and transient query caching.

== Upgrade ==

For stores requiring advanced capabilities, Searchips Pro Addon is available:

* **Visual Search Analytics Dashboard**: Review shopper photo queries with high-resolution 150x150 thumbnails and track unfulfilled demand with zero-result filtering.
* **Click-Through Rate (CTR) Tracking**: Non-blocking telemetry beacons record customer conversions.
* **Product Variation Images Matching**: Index variation images and deep-link shoppers directly to matching color or style variations.
* **Smart Image Hashing (MD5)**: Skips remote AI API calls when saving products if image files have not changed, saving API costs.
* **Direct AI Gateways**: Connect directly to your own OpenAI and Google Gemini API keys.
* **Native Mobile Camera Photo Capture**: Mobile shoppers can snap photos directly from their smartphone camera.
* **Priority Similarity Boost**: Apply a configurable similarity bonus (+1% to +30%) to on-sale and featured products.
* **Category Exclusion Filters**: Exclude non-physical, virtual, or gift card categories from vector indexing.
* **Vision-to-Text Description Search**: Strategy 2 descriptive AI keyword queries for broader catalog matches.
* **5 Additional Futuristic Scanning Animations**: AI Vision Reticle, Sonar Radar Sweep, Digital Mesh Grid, Concentric Ripple, and Hologram Shimmer.

Learn more about [Searchips Pro](https://violo.ir/?p=707).

== How-To and Setup ==

= 1. Setting Up the AI Connection =
Go to WooCommerce -> Search by Image. Select your AI Provider Gateway (OpenRouter) and paste your corresponding API key in the field. You can use the visibility toggle to confirm the key is input correctly without trailing spaces.

= 2. Running the Product Indexer =
Navigate to the "Product Indexer" tab. Choose whether to index featured images, gallery images, or both. Click "Start Indexing". You can pause or stop the process at any time, or enable "Auto-Index on Save" to automatically index products upon saving.

= 3. Adding the Search Bar via Shortcode =
Insert the shortcode `[tsbifw_search_bar]` in any post, page, or widget template to display the camera trigger search bar.

= 4. Customizing Trigger Styles and Scanner Animation =
Use the "Styling" settings tab to configure position properties, size, and background colors using the WordPress color picker UI. You can also customize your preferred scanning accent color and test the Laser Line animation with the real-time interactive preview stage.

= 5. Advanced Capabilities =
For advanced enterprise tools including Visual Search Analytics, Variation Indexing, Smart Hashing, and Mobile Camera Capture, see the Upgrade section.

= 6. Viewing and Managing Logs =
Navigate to the "System Logs" tab to toggle database logging, select log retention periods, copy diagnostic logs to your clipboard, or clear log history.

== Help and Support ==

* **API Authentication Issues**: Ensure your API key is correct and active on your chosen provider.
* **Skipped Products**: Products with missing or zero-byte image files are skipped to avoid database error loops.
* **Cron Indexing**: Enable background cron indexing to process product updates automatically in the background.

== Frequently Asked Questions ==

= What is the difference between Free and Pro? =
The Free version provides a complete, unrestricted visual search engine for your catalog: unlimited vector indexing, OpenRouter multimodal embeddings, auto-indexing on product save, crop tool, and camera trigger styling.

The Pro Addon unlocks advanced enterprise and conversion tools:
1. Visual Search Analytics: See thumbnail photos shoppers upload, CTR metrics, and zero-result queries to discover unfulfilled demand.
2. Product Variation Images: Index variation images and link customers directly to matching color/style variations.
3. Smart Image Hashing (MD5): Skips remote AI API calls when product images have not changed, dramatically cutting API expenses.
4. Direct Gateways: Connect directly to OpenAI and Google Gemini.
5. Mobile Camera Instant Capture: Let mobile shoppers take photos directly with their device camera.
6. Similarity Score Boost: Algorithmic boost (+1% to +30%) for on-sale and featured products.
7. Category Exclusions: Exclude non-physical or service categories from indexing.

= How do I upgrade to the Pro Addon? =
You can get the Pro Addon at [https://violo.ir/?p=707](https://violo.ir/?p=707). After purchasing, upload and activate the Pro Addon plugin alongside the free plugin. All your existing settings and indexed vectors remain intact.

= Does the free version have a product indexing limit? =
No. The free version provides unlimited catalog indexing with no arbitrary product count limits.

= Do I need an API key to use this plugin? =
Yes. You need an API key from OpenRouter (free version), or an OpenAI or Google Gemini API key if using direct connections with the Pro Addon.

= Which search strategy should I choose? =
* Multimodal Vector Embeddings (Strategy 1): Measures visual similarity between product image vectors directly. Fast, robust, and available across all versions.
* Vision-to-Text Description Search (Strategy 2): Converts uploaded images to descriptive keywords and searches your store database using text search (unlocked with Pro Addon).

= How does Visual Search Analytics help my store? =
Visual Search Analytics tracks what your customers are searching for visually. By reviewing customer-uploaded photos and zero-result searches, you gain unique market intelligence on what styles, items, and trends your shoppers desire that are currently missing from your catalog.

= Does visual search work on mobile devices? =
Yes. Shoppers can upload photos from their mobile library in the Free version. With the Pro Addon, mobile shoppers also get direct access to their smartphone camera to snap and search live photos instantly.

= Are empty or zero-byte images processed? =
No, the indexer automatically detects and skips empty, missing, or corrupt image files to prevent API error cycles.

= How do I control the number of search results displayed? =
Search results inherit your site's WordPress Reading Settings ("Blog pages show at most X posts" under Settings -> Reading), ensuring consistent archive page pagination.

== Changelog ==

= 1.5.0 =
* Architecture: Fully decoupled Strategy 2 (Vision-to-Text Description Search) into the Pro Addon via clean action and filter hooks.
* Architecture: Removed all unused Strategy 2 methods and database queries from the free core plugin.
* Settings: Streamlined settings registration and separated Pro Addon settings into an isolated settings group.
* Compliance: Updated documentation and settings hooks in full compliance with WordPress.org guidelines.

= 1.4.0 =
* Architecture: Fully decoupled Free and Pro Addon integration using extensible WordPress action and filter hooks.
* Architecture: Removed all gated settings and trialware checks from Free settings UI and script bundles.
* Feature: Added public procedural helpers (tsbifw_index_product, tsbifw_get_indexing_stats) and action listeners.
* Decoupling: Extracted Strategy 2 and Mobile Camera capture into pure hook-based Pro extensions.
* Refactor: Standardized modal event listeners and modularized frontend scanning initialization.

= 1.3.0 =
* Complete WordPress.org Guideline 5 compliance and modular decoupled architecture.
* Decoupled Pro Addon into a clean extensible architecture via standard WordPress action and filter hooks.
* Removed all conditional Pro checks, hardcoded addon dependencies, and trialware elements from the free plugin.
* Added extension action hooks for add-ons and gateways: tsbifw_loaded, tsbifw_register_settings, tsbifw_admin_settings_tabs, tsbifw_admin_settings_tab_content, tsbifw_admin_gateway_fields, tsbifw_settings_general_after_images, tsbifw_settings_general_after_media_column, tsbifw_settings_general_after_threshold, tsbifw_settings_general_after_auto_inject, tsbifw_settings_general_advanced, tsbifw_settings_styling_effects, and tsbifw_uninstall.
* Cleaned uninstall routine to only manage core options and transients.

= 1.2.5 =
* Media Library Performance: Added a configurable setting ("Media Library Integration") in the Indexer settings tab allowing store owners to toggle the Media Library list view status column and filter dropdown.
* Media Filter Query Safeguards: Hardened Media Library status filtering to safely merge post inclusion and exclusion query parameters with third-party filters, preventing query clashes and adding a defensive limit cap for high-volume catalogs.

= 1.2.4 =
* Documentation: Converted the feature comparison table into structured subheadings and bulleted lists to ensure clean, native typography on the WordPress.org Plugin Directory.

= 1.2.3 =
* Standards & Code Quality: Refined filesystem operations in uninstaller using WP_Filesystem API and resolved PHPCS sniffs for dynamic query placeholders and execution limits.
* Pro Addon Uninstall Integration: Added dedicated uninstaller for the Pro Addon with automated table drops, directory cleanup, and Action Scheduler unscheduling.
* Enhanced Pro Tab Previews: Included interactive live animations for all five futuristic scanner FX styles and a demo visual analytics dashboard for free tier users.

= 1.2.2 =
* WordPress.org Guideline 5 Compliance: Decoupled settings architecture using WordPress action hooks (`searchips_after_provider_settings`, `searchips_after_indexing_settings`). Completely eliminated locked or disabled form controls, upsell badges on active input labels, and intrusive inline upgrade banners from free settings tabs.
* Dedicated Pro Interactive Preview Tab: Introduced an isolated, high-polish "Pro" tab featuring interactive mockups and detailed breakdowns of Pro capabilities (Analytics, Variations, Smart Hashing, Mobile Camera) while keeping free configuration screens 100% operational and uncluttered.
* Visual Scanning Animations & Live Preview: Added four scanner animation options (Laser Line, Radar Sweep, Grid Pulse, Corner Bracket) with a real-time admin preview stage in the Styling tab.
* Visual Search Analytics Dashboard (Pro): Track shopper-uploaded photo queries with 150x150 thumbnails, measure overall Click-Through Rate (CTR) via non-blocking beacons, and spot unfulfilled customer demand with zero-result search filtering.
* Similarity Score Boost (Pro): Allows store owners to apply a configurable similarity bonus (+1% to +30%) to featured products and on-sale inventory to boost their ranking in visual search results.
* Mobile Camera Photo Capture (Pro): Allows mobile users to open their device camera directly via HTML5 environment capture, snap a photo, and search instantly in WooCommerce.
* Category Exclusion Filters (Pro): Exclude blacklisted WooCommerce product categories from AI indexing, automatically marking them as skipped and saving API quota.
* Smart Image Hashing (Pro): Computes MD5 attachment hashes to bypass expensive remote AI API calls when saving products or running cron tasks if image files have not changed.
* Product Variation Images (Pro): Index WooCommerce variable product variation images, automatically swap catalog result thumbnails, and link shoppers directly to matching variations.
* Multi-Provider AI Gateway: Added gateway architecture supporting OpenRouter, OpenAI Direct, and Google Gemini Direct.
* System Logs Tab: Relocated Enable Logging and Log Retention settings to a dedicated System Logs tab with isolated options saving.
* Reading Settings Integration: Seamlessly synchronized visual search result volume with WordPress core Reading Settings (posts_per_page).
* Styling Freedom: Made all camera trigger styling controls (offsets, sizes, colors) completely free and unrestricted.
* Unlimited Free Indexer: Removed product count limitations from the free indexer, allowing complete catalog indexing.
* Auto-Index on Save: Added free instant auto-indexing for products upon publishing or updating catalog images.
* Implemented memory-efficient cursor-based batch streaming for visual vector and description similarity matching, scaling effortlessly to catalogs with thousands of products.
* Optimized fast cosine similarity computation using pre-normalized query vectors.
* Prevented database packet size (max_allowed_packet) and memory limit errors when searching large catalogs.
* Streamlined Media Library indexed status checking to query attachment IDs directly without loading vector blobs.
* Enhanced REST search error handling and diagnostics for admin test search.

= 1.2.1 =
* Verified and confirmed compatibility with WooCommerce 11.1.0.
* Integrated WooCommerce 11.1.0 WC_Product::is_viewable() and catalog visibility filtering for search results.
* Added HTML tag stripping for product names in search sandbox responses.
* Modernized jQuery file input click handlers to native DOM events.

= 1.2.0 =
* Verified and confirmed compatibility with WordPress 7.1.0 and WooCommerce 11.0.0.
* Declared official High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility.

= 1.1.9 =
* Added size checks directly before initiating frontend/sandbox AJAX uploads.

= 1.1.8 =
* Enforced client-side file size validation in frontend and admin Test Search sandboxes.
* Displayed maximum upload file size beneath the drag-and-drop zone.

= 1.1.7 =
* Added a new configuration setting to customize maximum frontend upload file size (defaults to 2MB).
* Optimized image resizing memory footprint to use intermediate image sizes (medium_large/large) and prevent Out of Memory crashes.
* Enhanced REST API callback error mapping, preventing unexpected HTTP 500 status codes.
* Optimized vector and description database fetching queries using an SQL INNER JOIN, removing N+1 performance bottleneck.
* Added transient lock to throttling database log cleaning routines.
* Added defensive JavaScript safeguards preventing script breaks if localized parameter injections are deferred.

= 1.1.6 =
* Updated description terms to match standard guidelines.
* Adjusted External Services section heading casing.

= 1.1.5 =
* Fixed frontend REST API "Forbidden: invalid security token" authorization issue for logged-in users.

= 1.1.4 =
* Adjusted crop selection area to cover exactly 90% of the loaded image instead of 90% of the canvas.

= 1.1.3 =
* Upgraded Cropper.js library to the latest stable v2.1.1 Web Component release.
* Added detailed privacy and terms of service documentation for OpenRouter.
* Fixed settings page sanitization callback for logging and indexing checkboxes.
* Implemented strict permission_callback checks for the frontend search REST endpoint.

= 1.1.2 =
* Hardened input settings sanitization using custom CSS layout, color, and size callbacks.
* Authenticated the REST API sandbox search route using nonces and capability checks.
* Removed obsolete/unused frontend variables and dead callback functions to unbloat code.
* Enhanced query performance using WordPress object caching memory wrappers.
* Hardened uninstall database cleanup by ensuring all option keys and transients are deleted.

= 1.1.1 =
* Added a search cache expiry setting to database transients.
* Added frontend similarity percentage badges on default archive pages.
* Enhanced the frontend search popup uploader with modern UI effects.

= 1.1.0 =
* Suppressed standard SQL keyword clauses when image search tokens are active to prevent search conflicts.

= 1.0.9 =
* Configured visual search results to load using theme default archive page layouts.

= 1.0.8 =
* Added API key show/hide password toggle to prevent key corruption.

= 1.0.7 =
* Added styling option to configure the camera search trigger icon size.

= 1.0.6 =
* Separated styling and general options to resolve validation errors.

= 1.0.5 =
* Implemented standard WordPress wp-color-picker component.

= 1.0.0 =
* Initial release.
