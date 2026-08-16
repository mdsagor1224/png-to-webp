=== PNG to WebP Converter ===
Contributors: mdsagor1224, alkesh7
Tags: webp, image optimization, png, jpg, media library
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert PNG and JPG images to WebP directly from your WordPress dashboard. Fast, simple, and privacy-friendly.

== Description ==

PNG to WebP Converter lets you convert PNG and JPG/JPEG images already in your Media Library into the modern, smaller WebP format -- entirely on your own server. There is no external API, no cloud service, no account, and no data ever leaves your site.

**Features**

* Convert a single image from the Media Library with one click.
* Bulk-convert many images at once, without freezing your browser.
* Optional automatic conversion of new PNG/JPG uploads.
* Adjustable WebP quality (1-100).
* Choice to keep or replace original images (originals are kept by default).
* Clear before/after size and savings reporting.
* Dashboard with conversion statistics.
* Uses WordPress's native image editor (GD or Imagick) -- no custom image engine.
* No tracking, no analytics, no advertisements, no external requests.

= Privacy =

This plugin does not collect any personal data, does not send images to any external server, and does not communicate with any third-party service. All processing happens locally using your own server's PHP image libraries (GD or Imagick).

== Installation ==

1. Upload the `png-to-webp-converter` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **PNG to WebP → Settings** to review the default options.
4. Go to **PNG to WebP → Convert Images** to run your first conversion, or convert individual images from the Media Library list view.

== Usage ==

**Convert a single image**

1. Go to Media → Library.
2. Find a PNG or JPG image.
3. Click "Convert to WebP" in the row actions.

**Bulk convert**

1. Go to PNG to WebP → Convert Images.
2. Click "Select Images" to pick specific images, or "Convert All Eligible Images" to process your whole library.
3. Watch the progress bar and results table update as each image finishes.

**Automatic conversion**

1. Go to PNG to WebP → Settings.
2. Enable "Automatically convert new uploads".
3. New PNG/JPG uploads will get a WebP version generated automatically. The original upload is never blocked or broken if WebP generation fails.

== Frequently Asked Questions ==

= Does this plugin upload images to an external server? =

No. Image processing happens locally on your WordPress server using PHP's GD or Imagick libraries.

= Does it delete my original images? =

No, not by default. Originals are preserved unless you explicitly disable "Keep Original Images" in Settings.

= What formats are supported? =

PNG, JPG and JPEG as input; WebP as output.

= What happens if my server doesn't support WebP? =

The plugin displays a clear admin notice explaining that WebP is unavailable and does not break your website or the normal media upload process.

= Does this plugin track me or send analytics anywhere? =

No. The plugin has no tracking, telemetry, or external communication of any kind.

== Troubleshooting ==

* **"WebP conversion is not available on this server"** -- Your PHP GD or Imagick library was built without WebP support. Contact your hosting provider, or ask them to enable it.
* **Conversion fails for a specific image** -- Very large images can hit PHP memory limits. Try increasing `memory_limit` in PHP, or convert the image at a smaller size first.
* **A converted WebP image doesn't appear where expected** -- WebP images are added as their own Media Library attachments; find them by searching your Media Library.

== Server Requirements ==

* WordPress 6.4 or later.
* PHP 7.4 or later.
* GD or Imagick with WebP support compiled in.

== Changelog ==

= 1.0.2 =
* Fixed a Stable Tag / plugin version mismatch in readme.txt.
* Updated "Tested up to" for current WordPress compatibility.
* Resolved all PHPCS/WordPress Coding Standards errors and warnings.
* Added maintainer/contributor credit.

= 1.0.1 =
* Improved WebP capability detection.
* Added GD and Imagick fallback conversion when the WordPress image editor reports WebP as unsupported.
* Improved error messages for local XAMPP and hosting environments.

= 1.0.0 =
* Initial release: single-image conversion, bulk conversion, automatic conversion on upload, adjustable quality, Media Library integration, dashboard statistics.

== Upgrade Notice ==

= 1.0.2 =
Coding standards and WordPress.org metadata compliance update. No functional changes; safe to update.

= 1.0.0 =
Initial release.
