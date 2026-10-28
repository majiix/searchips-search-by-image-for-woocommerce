=== Searchips Search By Image for WooCommerce ===
Contributors: micromax2
Tags: woocommerce, search, image search, search by image, vector search
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 11.1
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable customers to search WooCommerce products using images powered by machine learning and OpenRouter.

== Description ==

Searchips Search By Image for WooCommerce brings intelligent, state-of-the-art visual search to your online store. Shoppers can upload photos from their device or mobile image library to find matching and visually similar products in seconds.

The plugin is 100% free and open-source: unlimited catalog indexing, high-accuracy multimodal vector search, 6 futuristic scanning animations, and full WP-CLI command integration.

The plugin powers visual search using Multimodal Vector Embeddings: generates high-dimensional mathematical vectors for products and search images, calculating cosine similarity for direct visual matching.

== External Services ==

This plugin connects to external AI services to perform image-based searches and catalog indexing using artificial intelligence models:

1. OpenRouter (https://openrouter.ai):
- What the service is and what it is used for: API gateway used to generate multimodal vector embeddings.
- What data is sent and when: Base64-encoded image data and model configuration parameters are sent when products are indexed or when customers submit a visual search query.
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

* **Multimodal Vector Search**: Fast and accurate image matching using high-dimensional AI vector embeddings.
* **OpenRouter AI Gateway**: Connect to leading multimodal models via OpenRouter.
* **6 Futuristic Scanning Animations**: Laser Sweep, AI Vision Reticle, Sonar Radar Sweep, Digital Mesh Grid, Concentric Ripple, and Luxury Hologram Shimmer with live interactive preview stage.
* **WooCommerce Bulk Actions & Action Scheduler**: Index single or multiple products from the standard WooCommerce Products list with async background processing.
* **WP-CLI Command Integration**: Manage indexing and status directly from the command line (`wp searchips status`, `wp searchips index`, `wp searchips clear`).
* **Interactive Image Cropping**: Integrated Cropper.js allows shoppers to crop and focus on specific areas of an image before searching.
* **Unlimited Product Indexing**: Asynchronous AJAX indexer processes catalogs of any size with pause and stop controls.
* **Instant Auto-Indexing on Save**: Automatically indexes or re-indexes products immediately upon publishing or updating images.
* **Dedicated System Logs Tab**: View recorded events, copy diagnostic logs, clear history, and configure log retention.
* **High-Performance Architecture**: Bulk pre-primed post caches (`_prime_post_caches`), cursor-based streaming, and transient query caching.

== How-To and Setup ==

= 1. Setting Up the AI Connection =
Go to WooCommerce -> Search by Image. Paste your OpenRouter API key in the field. You can use the visibility toggle to confirm the key is input correctly without trailing spaces.

= 2. Running the Product Indexer =
Navigate to the "Product Indexer" tab. Choose whether to index featured images or gallery images. Click "Start Indexing". You can pause or stop the process at any time, or enable "Auto-Index on Save" to automatically index products upon saving.

= 3. Adding the Search Bar via Shortcode =
Insert the shortcode `[tsbifw_search_bar]` in any post, page, or widget template to display the camera trigger search bar.

= 4. Customizing Trigger Styles and Scanner Animation =
Use the "Styling" settings tab to configure position properties, size, and background colors using the WordPress color picker UI. You can also customize your preferred scanning accent color and test all 6 scanning animations with the real-time interactive preview stage.

= 5. Viewing and Managing Logs =
Navigate to the "System Logs" tab to toggle database logging, select log retention periods, copy diagnostic logs to your clipboard, or clear log history.

== Help and Support ==

* **API Authentication Issues**: Ensure your API key is correct and active on your chosen provider.
* **Skipped Products**: Products with missing or zero-byte image files are skipped to avoid database error loops.
* **Cron Indexing**: Enable background cron indexing to process product updates automatically in the background.

== Frequently Asked Questions ==

= Is this plugin completely free? =
Yes. Searchips Search By Image for WooCommerce is 100% free and open-source. All core features including the OpenRouter gateway, 6 Scanner FX styles, and WP-CLI commands are included with zero upgrade nag screens and zero subscriptions.

= Do I need an API key to use this plugin? =
Yes. You need an API key from OpenRouter.

= How does visual search work? =
Multimodal Vector Embeddings measure visual similarity between product image vectors directly using multimodal embedding models. Fast, robust, and ideal for shape, color, and visual pattern matching.

= Does visual search work on mobile devices? =
Yes. Shoppers can upload photos from their mobile device library to search photos instantly.

= Are empty or zero-byte images processed? =
No, the indexer automatically detects and skips empty, missing, or corrupt image files to prevent API error cycles.

= How do I control the number of search results displayed? =
Search results inherit your site's WordPress Reading Settings ("Blog pages show at most X posts" under Settings -> Reading), ensuring consistent archive page pagination.

== Changelog ==

= 1.8.0 =
* Complete Free Unification: Cleaned up and streamlined standalone free plugin with zero upgrade notices.
* Added all 6 futuristic scanning animations (Laser Sweep, Reticle, Radar, Matrix, Ripple, Hologram).
* Added WooCommerce Products list bulk action indexing with Action Scheduler async support.
* Added WP-CLI command suite (wp searchips status, wp searchips index, wp searchips clear).
