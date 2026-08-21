=== Searchips Search By Image for WooCommerce ===
Contributors: micromax2
Tags: woocommerce, search, image search, search by image, vector search
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 11.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enable customers to search WooCommerce products using images powered by machine learning.

== Description ==

Searchips Search By Image for WooCommerce is a visual search tool that allows customers to search for visually similar products by uploading or taking photos directly on your shop pages.

The plugin offers two search strategies:
1. Multimodal Vector Embeddings: Matches visual features using advanced machine learning models via OpenRouter.
2. Vision-to-Text Description Search: Analyzes images to generate descriptive keyword search queries using a vision language model.

== External services ==

This plugin connects to the OpenRouter API to perform image-based searches and catalog indexing using artificial intelligence models.

- What the service is and what it is used for: OpenRouter (https://openrouter.ai) is an external API service used to generate vector embeddings (for visual similarity search) or image descriptions (for vision-to-text search) from product and query images.
- What data is sent and when: Base64-encoded image data and request parameters are sent to OpenRouter's servers when a store administrator manually runs the product indexer or when products are updated (if auto-indexing is triggered), and when a customer uploads or captures an image to perform a visual search on the frontend.
- Terms of service and privacy policy: This service is provided by OpenRouter. You can read their Terms of Service at https://openrouter.ai/terms and their Privacy Policy at https://openrouter.ai/privacy.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/searchips-search-by-image-for-woocommerce` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your settings under WooCommerce -> Search by Image.
4. Run the Product Indexer to process product images.

== Features ==

* **Search Strategies**: Toggle between Vector Embeddings (visual similarity) and Vision-to-Text (semantic descriptions) searches.
* **Camera Search Trigger**: Automatically enqueued inside theme search forms or loaded via shortcode `[tsbifw_search_bar]`.
* **Sleek Frontend UI**: Drag-and-drop modal uploader featuring real-time scanning animations.
* **Client-Side Image Cropping**: Let users crop images before searching.
* **Asynchronous Indexing**: Real-time batch processing indexer with Pause/Stop controls.
* **Custom Thresholds**: Configure similarity percentage requirements to control search strictness.
* **Log Viewer**: View database logs for API requests, settings changes, and queries.
* **Database Logging & Retention**: Automated cleanups of diagnostic logs.
* **Automatic Re-indexing**: Triggers background re-indexing when featured or gallery images change.

== How-To and Setup ==

= 1. Setting Up the API Connection =
Go to WooCommerce -> Search by Image. Paste your OpenRouter API Key in the field. You can use the visibility toggle to confirm the key is input correctly without trailing spaces.

= 2. Running the Product Indexer =
Navigate to the "Product Indexer" tab. Choose whether to index featured images, gallery images, or both. Click "Start Indexing". You can pause or stop the process at any time.

= 3. Adding the Search Bar via Shortcode =
Insert the shortcode `[tsbifw_search_bar]` in any post, page, or widget template to display the camera trigger search bar.

= 4. Customizing Trigger Styles =
Use the "Styling" settings tab to configure position properties, size, and background colors using the WordPress color picker UI.

== Help and Support ==

* **API Authentication Issues**: Ensure your API key is correct and active on OpenRouter.
* **Skipped Products**: Products with missing or zero-byte image files are skipped to avoid database error loops.
* **Cron Indexing**: Enable background cron indexing to process product updates automatically in the background.

== Frequently Asked Questions ==

= Do I need an OpenRouter account? =
Yes, a valid OpenRouter API key is required to connect to embedding and description models.

= Which search strategy should I choose? =
* Multimodal Vector Embeddings (Strategy 1) offers advanced visual feature matching by measuring the similarity of the product image vectors directly.
* Vision-to-Text Description Search (Strategy 2) converts uploaded images to keyword descriptions and searches your store database using regular text search.

= Are empty or zero-byte images processed? =
No, the indexer automatically skips empty or missing image files to prevent API error cycles.

= How do I trigger indexing automatically? =
Ensure that "Background Cron Indexing" is enabled in your General Settings, or simply update a product's featured/gallery image to automatically queue it for re-indexing.

== Changelog ==

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
