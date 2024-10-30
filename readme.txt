=== Lazy Embed ===
Contributors: beleaf, josh-stopper
Tags: performance, sustainability, embed, youtube, vimeo, dailymotion, lazy, lazyload
Requires at least: 6.2.0
Tested up to: 6.5
Stable tag: 1.6.3
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Improves the performance and reduces the emissions of your website by only loading embeds (youtube, vimeo, etc) when they are clicked.

== Description ==

Videos are one of the largest assets that can be loaded on a webpage, and as such are one of the largest contributors to slow performance and high carbon emissions.

In fact, adding a Youtube embed to a page using the latest default WordPress theme, increased the page transfer size from 21 kb to 973 kb, and loaded an additional 27 resources. That’s an increase in transfer size of 4533%. Adding a Vimeo video increased the transfer from 21 kb to 276 kb, an increase in transfer size of 1214%, and loaded an additional 7 resources.

The Lazy Embed plugin defers the loading of any resource required for playing the video until the video is requested to be played. It does this by adding a srcdoc attribute to the iframe which shows in place of the normal iframe content.

Currently the following third parties are supported

* Youtube
* Vimeo
* Dailymotion

== Changelog ==

= 1.6.3 - 22/05/2024 =
Fix: Sometimes the maxresdefault thumbnail from youtube doesnt exist. If this is the case, use a smaller image as a fallback

= 1.6.2 - 21/05/2024 =
Fix: iframe lazy loading implementation

= 1.6.1 - 21/05/2024 =
Fix: Load high resolution thumbnail from youtube
Fix: Lazy load iframe and img tag placeholder

= 1.6.0 - 08/04/2024 =
Feat: Replace regular expressions with WP_HTML_Tag_Processor
Fix: A missing src tag on an iframe resulted in a fatal error. This nolonger happens. Thanks Dan

= 1.5.1 - 18/03/2024 =
Fix: Update required wordpress version to ensure WP_HTML_Tag_Processor exists

= 1.5.0 - 18/03/2024 =
Fix: Fix ignoring of gutenberg embed blocks and video blocks
Feat: Support image for facade being provided by data-image
Feat: Support more exit points (not modify iframe) with imageSrc and iframeSrc
Feat: Add filter for replacing whole srcdoc content
QOL: Remove custom polyfill of str_contains as WordPress already polyfills it for us
QOL: Replace DOMDocument with WP_HTML_Tag_Processor
QOL: Optimise CSS positioning properties

= 1.4.0 - 07/02/2024 =
Feat: Pass html of iframe through to filters
QOL: Simplify css transform

= 1.3.0 - 03/03/2023 =
Feat: Add support for native video tags

= 1.2.1 - 16/02/2023 =
Fix: Replace DOMDocument save of the whole dom as it breaks the encoding of the page resulting in different user agent styles

= 1.2.0 - 16/02/2023 =
Fix: Vimeo embeds werent always retrieving their thumbnail due to a malformed url being passed to the Vimeo API. This is now fixed.
Feat: Add caching of the response from the query to the Vimeo API to improve performance and reduce emissions.

= 1.1.1 - 15/02/2023 =
Fix: Change muted parameter to mute for youtube embeds. Thanks @procontentxyz

= 1.1.0 - 02/02/2023 =
Fix: Use template_redirect hook to avoid iframe replacement on gutenberg save action
Feat: Add support for all embeds on a page, not just the Gutenberg and TinyMCE
Feat: Add support for ignoring iframes by adding the lazy-embed-ignore class
Feat: Add filters for the iframe source, image source, svg, and css.
QOL: Add type annotations for quality control
QOL: Remove global constants and use function return value
QOL: House cleaning, formatting, and documentation

= 1.0.0 - 11/01/2023 =
Initial release.

== Frequently Asked Questions ==

= Are there any settings =
Nope, install the plugin and you are good to go. There are some filters available for modifying the behaviour though.

= Can I stop the plugin from modifying an iframe = 
Yes you can, add the "lazy-embed-ignore" class to the iframe itself or using the gutenberg editor

= Why do I have to click a video twice somtimes for it to play? =
Browsers have become more restrictive around autoplaying a video. The first click on a video never reaches the video host, so they then show the default thumbnail. After the first time a Lazy Embed is interacted with, subsequent videos from that provider will not require a second load.

= I have updated the thumbnail for my video in Youtube/Vimeo/Dailymotion. Why am I not seeing the updated thumbnail? =
The thumbnails are cached to improve the performance (uncached frontend pages) and reduce the emissions (less requests to the video platform) of the Lazy Embed plugin.

The cache is known as transients. You can clear the transient cache using a plugin like [WP-Sweep](https://wordpress.org/plugins/wp-sweep/)

= Why does it not work for my videos? =
It could be for one of a few reasons.

1. A thumbnail for the embed could not be retrieved. If this is the case, send us a message with the video and we will take a look
