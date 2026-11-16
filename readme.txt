=== Searchips Search By Image for WooCommerce ===
Contributors: micromax2
Tags: woocommerce, search, image search, search by image, vector search
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 11.3
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable customers to search WooCommerce products using images powered by OpenRouter, OpenAI, Google Gemini, Jina AI, Voyage AI, Cohere, Groq, AvalAI, GapGPT, and Custom OpenAI-Compatible AI providers.

== Description ==

Searchips Search By Image for WooCommerce brings intelligent visual search to your online store. Shoppers can upload photos from their device or mobile photo library to find matching and visually similar products in seconds.

Built around a unified 10-provider AI gateway architecture, the plugin supports both **Strategy 1 (Multimodal Vector Embeddings, plus 2-step Vision-to-Vector support)** and **Strategy 2 (Vision-to-Text Description Search)** alongside unlimited featured-image catalog indexing, 6 futuristic scanning animations, inline provider connection testing, interactive image cropping, and full WP-CLI command integration.

The plugin supports two search strategies:
1. **Multimodal Vector Embeddings (Strategy 1):** Generates high-dimensional mathematical vectors for products and query photos (including native `embedContent` support for Google Gemini Direct and AvalAI, multimodal endpoints for Jina AI, Voyage AI, and Cohere, OpenRouter embeddings, and 2-step Vision-to-Vector pipelines for OpenAI Direct, GapGPT, and Custom endpoints), calculating cosine similarity for direct visual matching.
2. **Vision-to-Text Description Search (Strategy 2):** Uses multimodal vision models (such as Gemini 2.5 Flash, GPT-4o Mini, or Llama 4 Vision) to generate descriptive keyword phrases and match products via tokenized Jaccard similarity, with optional sync to WooCommerce product tags.

== External Services ==

Depending on the AI Provider Gateway you choose in WooCommerce -> Search by Image, this plugin sends base64-encoded product images, query photos, and/or model request payloads to one of the following external AI services:

1. **OpenRouter** (`https://openrouter.ai`) - Terms: `https://openrouter.ai/terms`, Privacy: `https://openrouter.ai/privacy`
2. **OpenAI Direct** (`https://api.openai.com`) - Terms: `https://openai.com/policies/terms-of-use`, Privacy: `https://openai.com/policies/privacy-policy`
3. **Google Gemini Direct** (`https://generativelanguage.googleapis.com`) - Terms: `https://ai.google.dev/terms`, Privacy: `https://policies.google.com/privacy`
4. **Jina AI** (`https://api.jina.ai`) - Website & Terms: `https://jina.ai/`
5. **Voyage AI** (`https://api.voyageai.com`) - Website & Terms: `https://www.voyageai.com/`
6. **Cohere** (`https://api.cohere.com`) - Terms: `https://cohere.com/terms-of-use`, Privacy: `https://cohere.com/privacy`
7. **Groq** (`https://api.groq.com`) - Terms: `https://groq.com/terms-of-use/`, Privacy: `https://groq.com/privacy-policy/`
8. **AvalAI** (`https://api.avalai.ir`) - Website: `https://avalai.ir/`
9. **GapGPT** (`https://api.gapgpt.app`) - Website: `https://gapgpt.app/`
10. **Custom OpenAI-Compatible Endpoint** (such as Liara AI, MetisAI, Together AI, or self-hosted Ollama) - Sends requests only to the HTTPS (or localhost) Base URL you explicitly configure.

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
3. Configure your AI provider and strategy under WooCommerce -> Search by Image.
4. Click "Test Connection" to verify your API credentials, then run the Product Indexer to process your product featured images.

== Features ==

* **10 AI Provider Gateways & Custom Endpoints**: Connect to OpenRouter, OpenAI Direct, Google Gemini Direct, Jina AI, Voyage AI, Cohere, Groq, AvalAI, GapGPT, or any Custom OpenAI-Compatible endpoint (with presets for Liara AI, MetisAI, Together AI, and local Ollama).
* **Dual AI Search Strategies**: Choose between Strategy 1 (Multimodal Vector Embeddings, with automatic 2-step Vision-to-Vector support on vision-only gateways) and Strategy 2 (Vision-to-Text Description Search with optional tag syncing).
* **Inline AJAX Connection Tester**: Click the mini "Test Connection" button beside the provider selector to validate API keys, Base URLs, and round-trip latency before saving.
* **Persistent Re-index Safety Notice**: Automatically shows a non-dismissible reminder across settings tabs when Strategy 1 provider, endpoint, or model changes require clearing and re-indexing your catalog.
* **Dedicated Help & Setup Guide Tab**: Step-by-step setup instructions for all 10 gateways plus Model Selection Help inside the settings dashboard.
* **6 Futuristic Scanning Animations**: Laser Sweep, AI Vision Reticle, Sonar Radar Sweep, Digital Mesh Grid, Concentric Ripple, and Luxury Hologram Shimmer with live interactive preview stage.
* **WooCommerce Bulk Actions & Action Scheduler**: Index single or multiple products from the standard WooCommerce Products list with async background processing.
* **WP-CLI Command Integration**: Manage indexing and status directly from the command line (`wp searchips status`, `wp searchips index`, `wp searchips clear`).
* **Interactive Image Cropping**: Integrated Cropper.js allows shoppers to crop and focus on specific areas of an image before searching.
* **Unlimited Featured Image Indexing**: Asynchronous AJAX indexer processes product featured images for catalogs of any size with pause and stop controls.
* **Background Cron & Auto-Index on Save**: Automatically index new or updated products on save or via WordPress Cron (`Every 5 Minutes`, `Every 15 Minutes`, `Hourly`, `Twice Daily`, or `Daily`).
* **Search Result Query Caching**: Configurable transient query caching (`5 Minutes` or `10 Minutes`).
* **Dedicated System Logs Tab**: View recorded events, copy diagnostic logs, clear history, and configure log retention.
* **High-Performance Architecture**: Bulk pre-primed post caches (`_prime_post_caches`), cursor-based streaming, and SSRF-hardened URL validation.

= Looking for Enterprise Features? Upgrade to Pro =
The Pro edition unlocks **Visual Search Analytics** (query thumbnails, CTR, zero-result tracking), **Product Gallery Images** & **Variation Images Indexing**, **Smart Image Hashing (MD5)** to reduce API calls, **Excluded Categories Filter**, **Mobile Camera Instant Capture**, **Algorithmic Similarity Score Boost** for featured/on-sale products, **1-Minute Background Cron**, and **Extended Search Cache (30m to 24h)**.

== How-To and Setup ==

= 1. Setting Up the AI Connection =
Go to WooCommerce -> Search by Image -> General Settings. Choose your AI Provider Gateway, paste your API key (or configure your Custom Base URL), and click the **Test Connection** button next to the dropdown to confirm connectivity. See the **Help & Setup Guide** tab for provider-specific instructions.

= 2. Choosing Strategy & Models =
Select Strategy 1 (Multimodal Vector Embeddings) or Strategy 2 (Vision-to-Text Description Search) and pick your preferred Embeddings and/or Vision model. Save your settings.

= 3. Running the Product Indexer =
Navigate to the "Product Indexer" tab and click "Start / Resume Indexing" to index your product featured images. You can pause or stop at any time, or enable "Auto-Index on Save" and background WordPress Cron in General Settings.

= 4. Adding the Search Bar via Shortcode =
Insert the shortcode `[tsbifw_search_bar]` in any post, page, or widget template to display the camera trigger search bar, or leave "Auto-Inject Camera Icon" enabled for supported theme search forms.

= 5. Customizing Trigger Styles and Scanner Animation =
Use the "Styling" tab to configure icon positioning, size, background color, scanning accent color, and test all 6 scanning animations in the live preview stage.

= 6. Viewing and Managing Logs =
Navigate to the "System Logs" tab to toggle logging, adjust retention days, copy diagnostic logs, or clear log history.

== Help and Support ==

* **API Authentication Issues**: Use the inline **Test Connection** button next to the gateway selector to verify keys and endpoints before indexing.
* **Re-index Warnings**: When changing providers, Custom Base URLs, or models under Strategy 1 while products are indexed, click **Clear / Reset Index** and re-index your catalog so query vectors match stored product vectors.
* **Skipped Products**: Products with missing or zero-byte image files are skipped to avoid database error loops.

== Frequently Asked Questions ==

= What AI providers are supported? =
You can connect to OpenRouter, OpenAI Direct, Google Gemini Direct, Jina AI, Voyage AI, Cohere, Groq, AvalAI, GapGPT, or any Custom OpenAI-Compatible endpoint (including Liara AI, MetisAI, Together AI, and local Ollama).

= How do the two search strategies differ? =
* **Strategy 1 (Multimodal Vector Embeddings)** encodes product and query images into numerical vectors and compares cosine similarity for visual matching (shape, color, layout, pattern). Providers without native image embeddings (OpenAI Direct, GapGPT, Custom) run an automatic 2-step Vision-to-Vector pipeline.
* **Strategy 2 (Vision-to-Text Description Search)** uses a vision LLM to extract rich visual keyword phrases and matches products with Jaccard keyword similarity (optional sync to WooCommerce product tags).

= Do I need to re-index if I change providers or models? =
Yes under Strategy 1: vectors from different providers or models live in incompatible vector spaces. The plugin automatically displays a persistent reminder until you click **Clear / Reset Index** in the Product Indexer tab. Switching providers or vision models while staying on Strategy 2 does not require re-indexing.

= Are empty or zero-byte images processed? =
No, the indexer automatically detects and skips empty, missing, or corrupt image files to prevent API error cycles.

= How do I control the number of search results displayed? =
Search results inherit your site's WordPress Reading Settings ("Blog pages show at most X posts" under Settings -> Reading), ensuring consistent archive page pagination.

== Changelog ==

= 2.0.0 =
* Expanded AI gateway support to 10 providers: OpenRouter, OpenAI Direct, Google Gemini Direct, Jina AI, Voyage AI, Cohere, Groq, AvalAI, GapGPT, and Custom OpenAI-Compatible (with Liara AI, MetisAI, Together AI, and Ollama presets).
* Brought Direct OpenAI, Google Gemini Direct, Strategy 2 (Vision-to-Text Description Search), and 2-step Vision-to-Vector embeddings into the Free edition.
* Fixed Gemini Direct and AvalAI multimodal image embeddings via native `v1beta/models/{model}:embedContent` with context-aware `taskType` (`RETRIEVAL_DOCUMENT` / `RETRIEVAL_QUERY`).
* Added inline AJAX **Test Connection** mini button beside the AI Provider Gateway dropdown.
* Added a dedicated **Help & Setup Guide** tab containing step-by-step instructions for all 10 providers and Model Selection Help.
* Added persistent non-dismissible **Clear & Re-index** warning notice when Strategy 1 provider, endpoint, or model changes affect existing indexed data.
* Focused Free indexing options on Featured Images, 5m/10m Search Cache Expiry, and 5m+ Cron intervals, and updated the Pro edition comparison sidebar.

= 1.8.1 =
* Verified compatibility with WooCommerce 11.3.

= 1.8.0 =
* Complete Free Unification: Cleaned up and streamlined standalone free plugin with zero upgrade notices.
* Added all 6 futuristic scanning animations (Laser Sweep, Reticle, Radar, Matrix, Ripple, Hologram).
* Added WooCommerce Products list bulk action indexing with Action Scheduler async support.
* Added WP-CLI command suite (wp searchips status, wp searchips index, wp searchips clear).
