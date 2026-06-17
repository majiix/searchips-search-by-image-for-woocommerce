# Project Overview

A WooCommerce extension that enables search by image functionality on the shop frontend. Customers can upload or capture images to search for visually similar products in the store.

## Technology Stack

- PHP: 7.4 or higher
- JavaScript: Modern vanilla JS (ES6+)
- CSS: Vanilla CSS for custom interfaces
- Database: WordPress postmeta tables with transient-based caching
- Remote APIs: OpenRouter for vector embedding and image description models

## System Architecture

The plugin is structured into several core modules:
1. **API Client**: Handles image compression, base64 conversion, and OpenRouter API integration.
2. **Metadata & Indexer**: Tracks indexed status of products, manages batch indexing tasks, saves embeddings/descriptions to product meta, and maintains vector caches.
3. **Admin Settings**: Implements a settings dashboard with API credentials, strategy selection, model defaults, and an AJAX-driven batch indexing screen.
4. **REST API Search Handler**: Implements a fast search controller to parse uploaded query images, generate search params, perform search calculations, and return product lists.
5. **Frontend Interface**: Injects search triggers into theme search forms, manages drag-and-drop uploads, and renders a dynamic search overlay.

## Verification Commands

- To check syntax of any PHP file:
  `php -l filename.php`
