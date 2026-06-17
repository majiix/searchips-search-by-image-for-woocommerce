# WordPress Development Rules
Apply these rules to any WordPress plugins, apps, themes, or WordPress-related projects:

- **WordPress Standards and Codex**: Adhere to the best practices in the most recent WordPress Codex, and always use WordPress coding standards when writing PHP, JavaScript, and TypeScript.
- **Minimum PHP Version**: The minimum supported PHP version must be 7.4. All PHP code must be compatible with PHP 7.4+.
- **Unique Prefixing**: All PHP functions, classes, options, constants, and namespaces must use a unique prefix at least 4 characters long derived from the name of the plugin, theme, or app (e.g., `prefix_` or `sthname_`) to prevent naming collisions.
- **Plugin Quality**: Follow the guidelines inside the WordPress [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin to write standard-compliant, secure, and performant code.
- **Translation Text Domain**: Always match the text domain to the plugin or theme slug.
- **Avoid strip_tags()**: The `strip_tags()` function is discouraged. Use the more comprehensive `wp_strip_all_tags()` function instead.
- **Do Not Load Plugin Textdomain**: Calling `load_plugin_textdomain()` manually is discouraged (since WordPress 4.6). For plugins hosted on WordPress.org, WordPress automatically loads translations under the plugin slug, making manual loading unnecessary.
- **Date/Time Handling**: Avoid using the native PHP `date()` function, as it is affected by runtime timezone changes and can result in incorrect date/time displays. Use `gmdate()` instead.
- **Database Caching**: Do not perform direct database calls without caching. Utilize `wp_cache_get()`, `wp_cache_set()`, or `wp_cache_delete()` to cache database queries wherever possible.
- **Translation Placeholders**: If using the `__()` translation function with placeholders, always add a `// translators:` comment on the line immediately preceding the call to clarify the meaning of each placeholder.
- **Version Updates and Changelog**: When updating any WordPress project (plugin, theme, or app), always update/increment the version number in all relevant files (e.g., the main plugin file header, `readme.txt`, or `style.css`). If a changelog exists, always document the new changes in it.
