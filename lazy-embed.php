<?php

namespace BeleafLazyEmbed;

/**
 * Plugin Name: Lazy Embed
 * Plugin URI: https://bitbucket.org/beleaf-au/lazy-embed/
 * Description: Improves the performance and reduces the emissions of your website by only loading embeds (youtube, vimeo, etc) when they are clicked.
 * Version: 1.6.3
 * Requires PHP: 7.1
 * Requires at least: 6.2.0
 * Tested up to: 6.5
 * Author: Beleaf
 * Author URI: https://beleaf.au
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: lazy-embed
 */

if (!class_exists('\BeleafLazyEmbed\LazyEmbed')) {
    class LazyEmbed
    {
        // https://wordpress.org/documentation/article/blocks-list/#embeds
        private const SUPPORTED_PROVIDERS = ['youtube', 'vimeo', 'dailymotion'];
        private const IGNORE_STRING = 'lazy-embed-ignore';

        public static function init(): void
        {
            add_action('template_redirect', [__CLASS__, 'initOutputBuffer'], 10000);
            add_action('render_block', [__CLASS__, 'alterBlockIframeClass'], 10, 2);
        }

        public static function alterBlockIframeClass(string $html, array $block): string
        {
            if ($block['blockName'] === 'core/embed' || $block['blockName'] === 'core/video') {
                if (!empty($block['attrs']['className']) && str_contains($block['attrs']['className'], self::IGNORE_STRING)) {
                    $tag = $block['blockName'] === 'core/embed'
                        ? 'iframe'
                        : 'video';
    
                    $processor = new \WP_HTML_Tag_Processor($html);
                    $processor->next_tag($tag);
                    $processor->add_class(self::IGNORE_STRING);
                    return $processor->get_updated_html();
                }
            }

            return $html;
        }

        public static function initOutputBuffer(): void
        {
            if (
                !is_admin() ||
                (function_exists("wp_doing_ajax") && wp_doing_ajax()) ||
                (defined('DOING_AJAX') && DOING_AJAX)
            ) {
                ob_start([__CLASS__, 'alterHTML']);
            }
        }

        public static function alterHTML(string $content): string
        {
            if (is_admin() || is_feed() || empty($content)) {
                return $content;
            }

            return self::modifyVideos($content);
        }

        private static function modifyVideos(string $html): string
        {
            $processor = new \WP_HTML_Tag_Processor($html);

            while ($processor->next_tag()) {
                if ($processor->get_tag() === 'IFRAME') {
                    $processor = self::iframeReplaceCallback($processor);
                } elseif ($processor->get_tag() === 'VIDEO') {
                    $processor = self::videoReplaceCallback($processor);
                }
            }

            return $processor->get_updated_html();
        }

        private static function iframeReplaceCallback(\WP_HTML_Tag_Processor $processor): \WP_HTML_Tag_Processor
        {
            // Dont modify the iframe if it is ignored
            if (self::ignoredVideo($processor)) {
                return $processor;
            }

            // Extract the video src
            $src = $processor->get_attribute('src') ?? '';

            // Extract the provider
            $provider = self::extractProvider($src);

            // Extract an image
            $imageSrc = self::getImage($processor, $provider);

            // Dont modify the iframe if there is no image
            if ($imageSrc === '') {
                return $processor;
            }

            // This will normally add things like autoplay, mute, and cleanup the embed
            $iframeSrc = self::modifyIframeSrc($src, $provider);

            // An empty string for iframeSrc is another nice way to bail out
            if ($iframeSrc === '') {
                return $processor;
            }

            $srcdoc = self::srcdoc($iframeSrc, $imageSrc, $processor);

            if ($srcdoc === '') {
                return $processor;
            }

            // Update the iframe src with the modified src
            $processor->set_attribute('src', $iframeSrc);

            // Add the srcdoc attribute which is the magic sauce
            $processor->set_attribute('srcdoc', $srcdoc);

            // Add lazy loading
            $processor->set_attribute('loading', 'lazy');

            return $processor;
        }

        private static function videoReplaceCallback(\WP_HTML_Tag_Processor $processor): \WP_HTML_Tag_Processor
        {
            // Dont modify the iframe if it is ignored
            if (self::ignoredVideo($processor)) {
                return $processor;
            }

            $processor->set_attribute('preload', 'none');

            return $processor;
        }

        private static function getImage(\WP_HTML_Tag_Processor $processor, string $provider): string
        {
            $imageURL = $processor->get_attribute('data-image') ?? '';

            if ($imageURL === '') {
                $imageURL = self::imageSrcFromSrc(
                    $processor->get_attribute('src') ?? '',
                    $provider
                );
            }

            // Allow for modification of the image src
            $imageURL = apply_filters('lazy-embed/imagesrc', $imageURL, $provider);

            return $imageURL;
        }

        private static function ignoredVideo(\WP_HTML_Tag_Processor $processor): bool
        {
            $classes = $processor->get_attribute('class');

            if (!is_string($classes)) {
                return false;
            }

            // Check if the element itself has the ignore class
            return str_contains($classes, self::IGNORE_STRING);
        }

        private static function srcdoc(string $iframeSrc, string $imageSrc, \WP_HTML_Tag_Processor $processor): string
        {
            // Pull content for the srcdoc
            $play = apply_filters('lazy-embed/partial/play', self::partialContent('play.svg'), $processor);
            $css = apply_filters('lazy-embed/partial/css', self::partialContent('embed-styles.css'), $processor);

            // Construct the srcdoc
            $srcdoc = "<style>$css</style>";
            $srcdoc .= '<a href="' . esc_url($iframeSrc) . '">';
            $srcdoc .= "<span>$play</span>";
            $srcdoc .= '<img loading="lazy" src="' . esc_url($imageSrc) . '">';
            $srcdoc .= '</a>';

            return apply_filters('lazy-embed/srcdoc', $srcdoc, $iframeSrc, $imageSrc, $processor);
        }

        private static function partialContent(string $partial): string
        {
            return trim(file_get_contents(self::pluginDir() . 'partials/' . $partial));
        }

        private static function extractProvider(string $src): string
        {
            foreach (self::SUPPORTED_PROVIDERS as $provider) {
                if (\str_contains($src, $provider)) {
                    return $provider;
                }
            }

            return '';
        }

        private static function modifyIframeSrc(string $src, string $provider): string
        {
            if ($provider === 'vimeo') {
                // https://vimeo.zendesk.com/hc/en-us/articles/115004485728-Autoplay-and-loop-embedded-videos
                $string = 'autoplay=1&muted=1';

                $src = parse_url($src, PHP_URL_QUERY)
                    ? $src . '&' . $string
                    : $src . '?' . $string;
            } elseif ($provider === 'youtube') {
                // https://developers.google.com/youtube/player_parameters
                $string = 'autoplay=1&mute=1&modestbranding=1&playsinline=1';

                // Make the youtube embed more privacy friendly
                $src = str_replace('youtube.com', 'youtube-nocookie.com', $src);

                $src = parse_url($src, PHP_URL_QUERY)
                    ? $src . '&' . $string
                    : $src . '?' . $string;
            }

            // Allow for modification of the iframesrc
            $src = apply_filters('lazy-embed/iframesrc', $src, $provider);

            return $src;
        }

        private static function imageSrcFromSrc(string $src, string $provider): string
        {
            if ($provider === 'youtube') {
                return self::youtubeThumbnail($src);
            } elseif ($provider === 'vimeo') {
                return self::vimeoThumbnail($src);
            } elseif ($provider === 'dailymotion') {
                return self::dailymotionThumbnail($src);
            }

            return '';
        }

        private static function extractID(string $url): string
        {
            $parts = parse_url($url);
            return basename($parts['path']);
        }

        private static function youtubeThumbnail(string $url): string
        {
            $id = self::extractID($url);

            if(empty($id)) {
                return '';
            }

            // Sometimes the maxresdefault thumbnail at youtube does not get
            // generated. Can be because the video is of low quality to begin
            // with. To ensure we load the highest quality thumbnail, without
            // occuring a performance overhead on each page load, on the first
            // load, we make a query to youtube to find the largest available
            // thumbnail, and then store that response in a transient
            $transientName = 'lazy_embed_youtube_' . $id;

            if ($url = get_transient($transientName)) {
                return $url;
            }

            $maxresdefault = "https://img.youtube.com/vi/$id/maxresdefault.jpg";
            $hqdefault = "https://img.youtube.com/vi/$id/hqdefault.jpg";

            $response = wp_remote_get($maxresdefault);

            if(wp_remote_retrieve_response_code($response) === 200) {
                set_transient($transientName, $maxresdefault);
                return $maxresdefault;
            }

            set_transient($transientName, $hqdefault);
            return $hqdefault;
        }

        private static function vimeoThumbnail(string $url): string
        {
            $id = self::extractID($url);
            $transientName = 'lazy_embed_vimeo_' . $id;

            if ($url = get_transient($transientName)) {
                return $url;
            }

            // https://developer.vimeo.com/api/oembed/videos
            $response = \wp_remote_get('https://vimeo.com/api/oembed.json?' . http_build_query([
                'url' => 'https://vimeo.com' . '/' . $id,
                'width' => self::thumbnailSize(),
            ]));

            $json = \json_decode(\wp_remote_retrieve_body($response), true);

            if (empty($json['thumbnail_url'])) {
                return '';
            }

            set_transient($transientName, $json['thumbnail_url']);

            return $json['thumbnail_url'];
        }

        private static function dailymotionThumbnail(string $url): string
        {
            $id = self::extractID($url);

            return !empty($id)
                ? "https://www.dailymotion.com/thumbnail/video/$id"
                : '';
        }

        private static function thumbnailSize(): int
        {
            return apply_filters('lazy-embed/thumbnail/size', 1280);
        }

        private static function pluginDir()
        {
            return trailingslashit(plugin_dir_path(__FILE__));
        }
    }

    \BeleafLazyEmbed\LazyEmbed::init();
}
