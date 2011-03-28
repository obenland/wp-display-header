=== WP Display Header ===
Contributors: kobenland
Tags: admin, custom header, header, header image, custom header image, display header, display dynamic header
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MWUA92KA2TL6Q
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 1.0

Select a specific header or random header image for each content item.

== Description ==

This plugin lets you specify a header image for each post individually from your default headers and custom headers.

It adds a meta box in the post edit screens with the header selection.
If no specific header is specified for a post it will fall back to the default selection.
There is no change of template files necessary as this plugin hooks in the existing WordPress API to unfold its magic.


= Translations =
I will be more than happy to update the plugin with new locales, as soon as I receive them!
Currently available in:

* English
* Deutsch

Thanks to Erik T. for the idea to this plugin!

== Installation ==

1. Download WP Display Header.
2. Unzip the folder into the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress


== Frequently Asked Questions ==

None asked yet.

= Plugin Filter Hooks =

**wpdh_show_default_header** (*bool*)
> Whether to show the default header (true) or to look for a specifically selected header for the current request.

**wpdh_get_header_posts** (*array*)
> All attachments with the meta key `_header_image`. An array with the query vars.

**wpdh_get_headers** (*array*)
> The array with all registered headers.

**wpdh_get_active_post_header** (*string*)
> The url to the currently active header image.

== Changelog ==

= 1.0 =
* Initial Release