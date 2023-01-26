<?php

/**
 * PDF Thumbnail Plugin
 *
 * @author     nozomu <https://nozomu.dev>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html    GPLv3
 * @link       https://github.com/nozomu-y/pdf_thumbnail.inc.php
 * @version    $Id: pdf_thumbnail.inc.php, v 1.0 2023-01-25 nozomu $
 * @package    plugin
 */

// resolution of the thumbnail
define('PLUGIN_PDF_THUMBNAIL_RESOLUTION', 72); // default: 72

// value of the target attribute of anchor tag
define('PLUGIN_PDF_THUMBNAIL_ANCHOR_TARGET', '_blank');

// style of the thumbnail image
define('PLUGIN_PDF_THUMBNAIL_STYLE', 'width: 300px; max-width: 100%;');

// whether to use cache
define('PLUGIN_PDF_THUMBNAIL_CACHE', TRUE);

// cache directory
define('PLUGIN_PDF_THUMBNAIL_CACHEDIR', CACHE_DIR . 'pdf_thumbnail/');

// whether to disable external file
define('PLUGIN_PDF_THUMBNAIL_DISABLE_EXTERNAL_FILE', TRUE);

// Usage
define('PLUGIN_PDF_THUMBNAIL_USAGE', "([pagename/]attached-file-name|url)");

function url_to_base64($url)
{
    $imagick = new Imagick();
    $imagick->setResolution(PLUGIN_PDF_THUMBNAIL_RESOLUTION, PLUGIN_PDF_THUMBNAIL_RESOLUTION);
    $imagick->readImage($url);
    $imagick->setIteratorIndex(0);
    $imagick->setImageBackgroundColor('#ffffff');
    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
    $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    $imagick->setImageFormat('png');
    $base64 = base64_encode($imagick);
    return $base64;
}

function url_to_png($url, $png)
{
    $imagick = new Imagick();
    $imagick->setResolution(PLUGIN_PDF_THUMBNAIL_RESOLUTION, PLUGIN_PDF_THUMBNAIL_RESOLUTION);
    $imagick->readImage($url);
    $imagick->setIteratorIndex(0);
    $imagick->setImageBackgroundColor('#ffffff');
    $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
    $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    $imagick->setImageFormat('png');
    $imagick->writeImage($png);
}

function create_htaccess()
{
    $htaccess = <<<EOF
<Files ~ "\.png$">
  Require all granted
</Files>
EOF;
    file_put_contents(PLUGIN_PDF_THUMBNAIL_CACHEDIR . '.htaccess', $htaccess);
}

function plugin_pdf_thumbnail_convert()
{
    global $vars;

    // get the arguments given to the plugin
    $args = func_get_args();
    if (count($args) != 1) {
        return htmlsc('<p>#pdf_thumbnail(): Usage:' . PLUGIN_PDF_THUMBNAIL_USAGE . "</p>\n");
    }

    // uri/url of the file
    $uri = $args[0];
    $is_attachment = !is_url($uri);
    if (!$is_attachment && PLUGIN_PDF_THUMBNAIL_DISABLE_EXTERNAL_FILE) {
        return htmlsc('Use of external file is disabled: ' . $uri);
    }

    // current page
    $page_name = isset($vars['page']) ? $vars['page'] : '';

    if ($is_attachment) {
        $attachment_name = $uri;
        if (!is_dir(UPLOAD_DIR)) {
            return 'Upload dir does not exist';
        }
        $matches = array();

        // whether the link contains page name
        if (preg_match('#^(.+)/([^/]+)$#', $attachment_name, $matches)) {
            if ($matches[1] == '.' || $matches[1] == '..') {
                $matches[1] .= '/'; // Restore relative paths
            }
            $attachment_name = $matches[2];
            $page_name = get_fullname(strip_bracket($matches[1]), $page_name); // strip is a compat
            $file_path = UPLOAD_DIR . encode($page_name) . '_' . encode($attachment_name);
            $is_file = is_file($file_path);
        } else {
            // Simple single argument
            $file_path = UPLOAD_DIR . encode($page_name) . '_' . encode($attachment_name);
            $is_file = is_file($file_path);
        }
        if (!$is_file) {
            return htmlsc('File not found: "' . $attachment_name . '" at page "' . $page_name . '"');
        }
        $url = $file_path . '[0]'; // use the first page only
        $anchor_link = get_base_uri() . '?plugin=attach' . '&amp;refer=' . rawurlencode($page_name) . '&amp;openfile=' . rawurlencode($attachment_name);
    } else {
        $url = htmlsc($uri);
        $anchor_link = $url;
    }

    if (!PLUGIN_PDF_THUMBNAIL_CACHE) {
        if (!extension_loaded('imagick')) {
            return 'Imagick not installed';
        }
        try {
            $base64 = url_to_base64($url);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return '<a href="' . $anchor_link . '" target="' . PLUGIN_PDF_THUMBNAIL_ANCHOR_TARGET . '"><img src="data:image/png;base64,' . $base64 . '" style="' . PLUGIN_PDF_THUMBNAIL_STYLE . '"></a>';
    }

    create_htaccess();

    // create cache dir if it does not exist
    if (!file_exists(PLUGIN_PDF_THUMBNAIL_CACHEDIR)) {
        mkdir(PLUGIN_PDF_THUMBNAIL_CACHEDIR);
    }

    $cache_file = PLUGIN_PDF_THUMBNAIL_CACHEDIR . md5($file_path . '_' . PLUGIN_PDF_THUMBNAIL_RESOLUTION) . '.png';
    // check if cache was generated after the attachment was uploaded
    if (file_exists($cache_file) && (filemtime($cache_file) > filemtime($file_path)) && $is_attachment) {
        // $base64 = file_get_contents($cache_file);
        return '<a href="' . $anchor_link . '" target="' . PLUGIN_PDF_THUMBNAIL_ANCHOR_TARGET . '"><img src="' . $cache_file . '" style="' . PLUGIN_PDF_THUMBNAIL_STYLE . '"></a>';
    } else {
        // create base64 and save as cache
        if (!extension_loaded('imagick')) {
            return 'Imagick not installed';
        }
        // create cache
        try {
            url_to_png($url, $cache_file);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    return '<a href="' . $anchor_link . '" target="' . PLUGIN_PDF_THUMBNAIL_ANCHOR_TARGET . '"><img src="' . $cache_file . '" style="' . PLUGIN_PDF_THUMBNAIL_STYLE . '"></a>';
}
