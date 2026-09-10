=== Searchips Search By Image for WooCommerce ===
Contributors: micromax2
Tags: woocommerce, search, image search, search by image, vector search
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 11.1
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable customers to search WooCommerce products using images powered by machine learning.

== Description ==

Searchips Search By Image for WooCommerce brings visual search to your online store. Shoppers can upload an image or snap a photo with their mobile camera to find matching and visually similar products in seconds.

The free version provides full, unrestricted visual search functionality: unlimited catalog indexing, high-accuracy multimodal vector search via OpenRouter, interactive client-side image cropping, real-time auto-indexing on save, customizable camera triggers, scanner glow accent colors, and an interactive live preview stage.

The settings forms in the free version are 100% clean and fully functional, adhering strictly to WordPress.org guidelines with zero disabled form fields, locked checkboxes, or trialware nag traps.

Looking for deeper store insights and higher conversions? The Searchips Pro Addon unlocks enterprise-grade visual search features including Visual Search Analytics with zero-result demand tracking, WooCommerce product variation images indexing, MD5 smart image hashing to slash API costs, direct OpenAI and Google Gemini connections, instant mobile camera capture, and sales priority boost. Free users can explore an interactive preview of all Pro features directly inside the dedicated "Pro" admin settings tab.

[Get Searchips Pro Addon](https://violo.ir/?p=707)

The plugin offers two search strategies:
1. Multimodal Vector Embeddings: Matches visual features using advanced machine learning models (Strategy 1, available across Free and Pro).
2. Vision-to-Text Description Search: Analyzes images to generate descriptive keyword search queries using a vision language model (Strategy 2, unlocked with Pro Addon).

== Free vs Pro Feature Comparison ==

| Feature | Free Version | Pro Addon |
| :--- | :---: | :---: |
| Multimodal Vector Similarity Search | Yes | Yes |
| Unlimited Product Catalog Indexing | Yes | Yes |
| Auto-Index on Product Save and Update | Yes | Yes |
| Interactive Image Cropper (Cropper.js) | Yes | Yes |
| OpenRouter AI Gateway Integration | Yes | Yes |
| Customizable Camera Trigger Styling | Yes | Yes |
| Scanner Glow Accent Color Picker | Yes | Yes |
| Live Scanner Animation Preview Stage | Yes | Yes |
| Dedicated Pro Interactive Preview Tab | Yes | Yes |
| Admin Test Search Sandbox | Yes | Yes |
| System Logs and Diagnostic Event Viewer | Yes | Yes |
| Visual Search Analytics Dashboard | No | Yes |
| Unfulfilled Demand Tracking (0-Results) | No | Yes |
| Click-Through Rate (CTR) Measurement | No | Yes |
| Product Variation Images Indexing | No | Yes |
| Direct Variation URL and Thumbnail Swap | No | Yes |
| Smart Image Hashing (MD5 Cost Saver) | No | Yes |
| Direct OpenAI Gateway (Embeddings and Vision) | No | Yes |
| Direct Google Gemini Gateway | No | Yes |
| Native Mobile Camera Instant Capture | No | Yes |
| Priority Similarity Boost (On-Sale / Featured) | No | Yes |
| Category Exclusion Filters | No | Yes |
| Vision-to-Text Description Search (Strategy 2) | No | Yes |
| Visual Scanning Animations | Laser Sweep | 6 Futuristic FX |
| High-Throughput WP-CLI and Cron Indexing | Full Access | Unlimited High-Speed |

== Why Upgrade to Searchips Pro? ==

* **Visual Search Analytics and Unfulfilled Demand**:
  Store owners get actionable intelligence. Review exact photos uploaded by your shoppers with high-resolution thumbnails. Monitor overall Click-Through Rate (CTR) via non-blocking beacons. Filter zero-result searches to discover unfulfilled customer demand: know exactly what products your shoppers are looking for that you do not yet carry.

* **Product Variation Images Matching**:
  In standard stores, customers searching for a specific color or style variant only see the parent product. With Pro, every variation image is indexed. When a customer uploads a photo, Searchips highlights the specific variation image and sends them directly to that exact variation on the product page.

* **Smart Image Hashing (Slash API Costs)**:
  Pro computes MD5 hashes for all indexed attachments. When you edit product descriptions, update prices, or run automated inventory syncs, Searchips detects that image pixels have not changed and skips remote AI calls completely. Save money on API tokens and keep servers running fast.

* **Direct OpenAI and Google Gemini Gateways**:
  Connect your store directly to OpenAI (text-embedding-3-small, CLIP, GPT-4o-mini) and Google Gemini (gemini-embedding, Gemini Flash) without extra middleware fees or third-party hops.

* **Native Mobile Camera Photo Capture**:
  Mobile shoppers simply tap the camera icon and select "Take Photo" to launch their smartphone camera immediately via HTML5 environment capture. Seamless visual search while shopping in physical stores or on the go.

* **Algorithmic Similarity Score Boost**:
  Drive higher margins and move promotional inventory. Pro allows you to give an algorithmic similarity boost (+1% to +30%) to featured products and on-sale items, surfacing high-margin inventory higher in visual search results while preserving relevance.

* **Category Exclusions**:
  Exclude non-physical or service categories (such as downloadable files, gift cards, or warranties) from visual indexing, ensuring only relevant products enter your vector database.

[Upgrade to Searchips Pro Today](https://violo.ir/?p=707)

== External services ==

This plugin connects to external AI services to perform image-based searches and catalog indexing using artificial intelligence models:

1. OpenRouter (https://openrouter.ai):
- What the service is and what it is used for: OpenRouter is an API gateway used to generate multimodal vector embeddings and vision descriptions from product and query images.
- What data is sent and when: Base64-encoded image data and model configuration parameters are sent to OpenRouter when products are indexed or when customers submit a visual search query.
- Terms of Service: https://openrouter.ai/terms
- Privacy Policy: https://openrouter.ai/privacy

2. OpenAI (https://openai.com):
- What the service is and what it is used for: Optional direct gateway for vector embeddings and vision completions.
- What data is sent and when: Base64-encoded image data and request parameters are sent to OpenAI API endpoints when the direct OpenAI gateway is configured.
- Terms of Service: https://openai.com/policies/terms-of-use/
- Privacy Policy: https://openai.com/policies/privacy-policy/

3. Google Gemini (https://ai.google.dev):
- What the service is and what it is used for: Optional direct gateway for vector embeddings and vision completions.
- What data is sent and when: Base64-encoded image data and request parameters are sent to Google Gemini endpoints when the direct Gemini gateway is configured.
- Terms of Service: https://ai.google.dev/terms
- Privacy Policy: https://policies.google.com/privacy

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
* **Visual Scanning Animations & Live Preview**: Eye-catching scanner effects (Laser Line, Radar Sweep, Grid Pulse, Corner Bracket) with a live preview stage in settings.
* **Dedicated Pro Interactive Preview Tab**: Isolated preview tab showcasing Pro capabilities and interactive mockups without locked form controls, in strict adherence to WordPress.org Guideline 5.
* **Multi-Provider AI Gateway**: Seamless connectivity to OpenRouter, with direct OpenAI and Google Gemini gateways supported.
* **Frontend Camera Trigger**: Automatically placed inside search forms or displayed with the `[tsbifw_search_bar]` shortcode.
* **Full Styling Customization**: Live color picker, custom icon dimensions, and left/right offsets.
* **WordPress Reading Settings Integration**: Frontend search result count automatically adheres to WordPress archive post limits (`posts_per_page`).
* **Dedicated System Logs Tab**: View recorded events, copy logs, clear history, and configure retention and logging preferences.
* **High-Performance Architecture**: Bulk pre-primed post caches (`_prime_post_caches`), cursor-based streaming, and transient query caching.
* **Visual Search Analytics Dashboard (Pro Addon)**: Track customer-uploaded query images, Click-Through Rates (CTR), and unfulfilled demand with zero-result search filtering.
* **Product Variation Images (Pro Addon)**: Automatically index WooCommerce product variation images so customers match specific variation colors or styles directly.
* **Smart Image Hashing (Pro Addon)**: Calculates MD5 attachment hashes to skip expensive remote AI calls on product save or cron when image files are unchanged.
* **Mobile Camera Photo Capture (Pro Addon)**: Mobile shoppers can tap "Take Photo" to launch their smartphone camera directly and search by captured photo.
* **Similarity Score Boost (Pro Addon)**: Grant an algorithmic similarity boost (+1% to +30%) to featured products and on-sale inventory to prioritize them in visual search.
* **Category Exclusion Filters (Pro Addon)**: Blacklist specific product categories from visual indexing to skip digital, virtual, or service products.
* **Vision-to-Text Description Search (Pro Addon)**: Analyzes images to generate descriptive search terms.

== How-To and Setup ==

= 1. Setting Up the AI Connection =
Go to WooCommerce -> Search by Image. Select your AI Provider Gateway (OpenRouter in free version, or direct OpenAI / Google Gemini with Pro) and paste your corresponding API key in the field. You can use the visibility toggle to confirm the key is input correctly without trailing spaces.

= 2. Running the Product Indexer =
Navigate to the "Product Indexer" tab. Choose whether to index featured images, gallery images, or both. Click "Start Indexing". You can pause or stop the process at any time, or enable "Auto-Index on Save" to automatically index products upon saving.

= 3. Adding the Search Bar via Shortcode =
Insert the shortcode `[tsbifw_search_bar]` in any post, page, or widget template to display the camera trigger search bar.

= 4. Customizing Trigger Styles and Scanner Animation =
Use the "Styling" settings tab to configure position properties, size, and background colors using the WordPress color picker UI. You can also select your preferred scanning animation style (Laser Line, Radar Sweep, Grid Pulse, Corner Bracket) and test it with the real-time interactive preview stage.

= 5. Exploring Pro Features =
Navigate to the "Pro" tab to explore upcoming advanced tools including Visual Search Analytics, Variation Indexing, Smart Hashing, and Mobile Camera Capture with an interactive preview.

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
