<?php

namespace System;

class Output
{
    const IX_ASYNC = 'async';
    const IX_CHARSET = 'charset';
    const IX_CONTENT = 'content';
    const IX_DEFER = 'defer';
    const IX_HREF = 'href';
    const IX_EQUIVALENT = 'http-equiv';
    const IX_MEDIA = 'media';
    const IX_NAME = 'name';
    const IX_ORDER = 'order';
    const IX_RELATE = 'rel';
    const IX_SOURCE = 'src';
    const IX_TARGET = 'target';
    const IX_TYPE = 'type';

    private $_base = [];
    private $_links = [];
    private $_metas = [];
    private $_scripts = [];
    private $_scriptlinks = [];
    private $_styles = [];
    private $_title = null;

    private $_sysInclude = true; // allows us to ensure our includes are first and formost
    private $_linkOrder = 0;
    private $_scriptOrder = 0;

    public function __construct()
    {
        $base = Base::getInstance();
        $langscript = null;

        // default the title as well
        if($base->name != null) $this->setTitle($base->name);

        if($base->isDebug)
        {
            // used by the w2ui library
            $this->setStyle('w2ui');

            // these scripts are always output by the system
            $this->setScript('jquery');
            $this->setScript('history'); // includes json2.js as well
            $this->setScript('sprintf');

            // these are SPA specific libraries that will make your life easier
            $this->setScript('knockout');
            $this->setScript('w2ui');

            // modernizr and the localization script include must always appear last
            $this->setScript('modernizr');
            $this->setScript('i18n');
        }
        else
        {
            // for production we present a combined, minified version of the above styles and scripts instead
            $this->setStyle(BP_DIR_SYSTEM);
            $this->setScript(BP_DIR_SYSTEM);
        }

        // even if client-side localization is disabled, output stub routines to avoid script errors
        // for any code that may depend on them being present without checking for it first
        if($base->isLocalized)
        {
            $uri = '/' . BP_DIR_LANG . '/' . $base->language;
            $langscript = <<< EOT
function _l(e,p)
{
    if((typeof e === 'string') || (typeof e === 'undefined')){
        e=(!e)?'*':e.trim();
        \$(e+'[data-i18n]').each(function(i){
        var d=\$(this).data('i18n').split('```');var x=i18n(d[0]);
        if(d.length>1){d.splice(0,1);x=vsprintf(x, d);}if(p)\$(this).prop(p,x); else \$(this).html(x);
    });}
    else if(typeof e === 'object'){e.each(function(i){
        var d=\$(this).data('i18n').split('```');var x=i18n(d[0]);
        if(d.length>1){d.splice(0,1);x=vsprintf(x, d);}if(p)\$(this).prop(p,x); else \$(this).html(x);
    });}
}
\$(document).ready(function(){\$.ajax({url:'$uri',datatype:'json'}).done(function(d){i18n.add(d);_l();$(document).trigger('system-localized');});});
EOT;
        }
        else
            $langscript = 'function _l(e,p){}';

        $this->setInlineScript($langscript);
        $this->_sysInclude = false;
    }

	public function __destruct() {}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	public function getBase() { return $this->_base; }
    public function getInlineScript($index) { return isset($this->_scripts[$index]) ? $this->_scripts[$index] : null; }
    public function getInlineScriptCount() { return count($this->_scripts); }
    public function getInlineStyle($index) { return isset($this->_styles[$index]) ? $this->_styles[$index] : null; }
    public function getInlineStyleCount() { return count($this->_styles); }
    public function getLink($index) { return isset($this->_links[$index]) ? $this->_links[$index] : null; }
    public function getLinkCount() { return count($this->_links); }
    public function getMetadata($index) { return isset($this->_metas[$index]) ? $this->_metas[$index] : null; }
    public function getMetadataCount() { return count($this->_metas); }
    public function getScript($index) { return isset($this->_scriptlinks[$index]) ? $this->_scriptlinks[$index] : null; }
    public function getScriptCount() { return count($this->_scriptlinks); }
	public function getTitle() { return $this->_title; }

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	public function setBase($href, $target = '_self')
	{
        $href = trim($href);
        $target = trim(strtolower($target));

        $this->_base[Output::IX_HREF] = ($href != null) ? $href : null;
        $this->_base[Output::IX_TARGET] = ($target != null) ? $target : null;
	}

    // this is for scripts included in the header only, it's just bad karma to include a script tag
    // in the middle of a document, it's bad, bad, so don't do it, unless you're making a plug-in!
    public function setInlineScript($content, $type = 'text/javascript')
    {
        $content = trim($content);
        $type = trim(strtolower($type));

        if($content != null)
        {
            $index = count($this->_scripts);

            $this->_scripts[$index][Output::IX_CONTENT] = $content;
            $this->_scripts[$index][Output::IX_TYPE] = (($type != null) && ($type != 'text/javascript')) ? $type : null;
        }
    }

    // this is for inline styles in the header only, so we ignore the scoped attribute, also the MIME type
    // is always text/css since A that's the default now as of HTML5 and B it's the only one supported
    public function setInlineStyle($content, $media = 'all')
    {
        $content = trim($content);
        $media = trim(strtolower($media));

        if($content != null)
        {
            $index = count($this->_styles);

            $this->_styles[$index][Output::IX_CONTENT] = $content;
            $this->_styles[$index][Output::IX_MEDIA] = (($media != null) && ($media != 'all')) ? $media : null;
        }
    }

    // note, the sizes and hreflang attributes are ignored, simply because we wish to enforce a
    // more modular, decoupled design, maintenance nightmares are not your friend,
    // also, charset, rev, and target were dropped in HTML5, and so they too are ignored
    public function setLink($href, $relationship = null, $type = null, $media = 'all')
    {
        // simple sanitation
        $href = str_replace('\\', '/', $href);

        $index = count($this->_links);
        $this->_linkOrder = $index;

        $relationship = trim($relationship);
        $href = trim($href);
        $type = trim(strtolower($type));
        $media = trim(strtolower($media));

        // if this is not a valid url, ensure the href starts with a forward slash
        if((filter_var($href, FILTER_VALIDATE_URL) === false) && (substr($href, 0, 1) != '/')) $href = '/' . $href;

        // we use this to determine the exact output order, first and formost it's rel type, then the system
        // takes priority, next in line is individual files, from that point on it's the order the files
        // were input into the system to allow the caller some control over it
        $add = $this->_sysInclude ? 0 : 10000;

        $this->_links[$index][Output::IX_RELATE] = ($relationship != null) ? $relationship : null;
        $this->_links[$index][Output::IX_HREF] = ($href != null) ? $href : null;
        $this->_links[$index][Output::IX_TYPE] = ($type != null) ? $type : null;
        $this->_links[$index][Output::IX_MEDIA] = (($media != null) && ($media != 'all')) ? $media : null;
        $this->_links[$index][Output::IX_ORDER] = $this->_linkOrder + $add;
    }

    // the charset will be set automatically via BP_SYS_CHARSET in index.php, and
    // the scheme attribute is dropped since HTML5 does not support it
    public function setMetadata($name, $content, $httpEquivalent = false)
    {
        $index = count($this->_metas);

        $name = trim(strtolower($name));
        $content = trim($content);

        $this->_metas[$index][Output::IX_NAME] = (($name != null) && ($name != 'content-type')) ? $name : null;
        $this->_metas[$index][Output::IX_CONTENT] = ($content != null) ? $content : null;
        $this->_metas[$index][Output::IX_EQUIVALENT] = $httpEquivalent;
    }

    // this is for scripts included in the header only, it's just bad karma to include a script tag
    // in the middle of a document, it's bad, bad, so don't do it, unless you're making a plug-in!
    // also, charset is ignored for the same reasons as sizes and hreflang in setLink()
    public function setScript($src, $type = 'text/javascript', $async = false, $defer = false)
    {
        // simple sanitation
        $src = str_replace('\\', '/', $src);

        $prefix = '/' . strtolower(BP_DIR_SCRIPT) . '/';
        $plen = strlen($prefix);
        $index = count($this->_scriptlinks);
        $this->_scriptOrder = $index;

        $src = trim($src);
        $type = trim(strtolower($type));

        // if this is not a valid url, ensure we always have the source include the proper path
        if((filter_var($src, FILTER_VALIDATE_URL) === false) && (substr($src, 0, strlen($prefix)) != $prefix)) $src = $prefix . $src;

        // determine if this is a script contained in a subfolder for production mode, if so just output
        // the folder name since it was minimized as a package file when the application was shrunk
        $sub = strpos(substr($src, $plen), '/');

        // we use this to determine the exact output order, first and formost the system takes priority,
        // next in line is individual files, and lastly packed files, from that point on it's the
        // order the files were input into the system to allow the caller some control over it
        $add = $this->_sysInclude ? 0 : 10000;
        $add += ($sub === false) ? 0 : 10000;

        if(!Base::getInstance()->isDebug)
        {
            $found = false;
            $dirsrc = dirname($src);

            // since the file names are "rolled up" into a psuedo package we can have duplicates, so check for that
            for($x=0; $x < $index; $x++) { if($this->_scriptlinks[$x][Output::IX_SOURCE] == $dirsrc) { $found = true; break; } }

            if(!$found)
            {
                $this->_scriptlinks[$index][Output::IX_SOURCE] = ($sub === false) ? $src : $dirsrc;
                $this->_scriptlinks[$index][Output::IX_TYPE] = (($type != null) && ($type != 'text/javascript')) ? $type : null;
                $this->_scriptlinks[$index][Output::IX_ASYNC] = $async;
                $this->_scriptlinks[$index][Output::IX_DEFER] = $defer;
                $this->_scriptlinks[$index][Output::IX_ORDER] = $this->_scriptOrder + $add;
            }
        }
        else
        {
            $this->_scriptlinks[$index][Output::IX_SOURCE] = $src;
            $this->_scriptlinks[$index][Output::IX_TYPE] = (($type != null) && ($type != 'text/javascript')) ? $type : null;
            $this->_scriptlinks[$index][Output::IX_ASYNC] = $async;
            $this->_scriptlinks[$index][Output::IX_DEFER] = $defer;
            $this->_scriptlinks[$index][Output::IX_ORDER] = $this->_scriptOrder + $add;
        }
    }

    public function setStyle($href, $relationship = 'stylesheet', $type = 'text/css', $media = 'all')
    {
        // simple sanitation
        $href = str_replace('\\', '/', $href);

        $prefix = '/' . strtolower(BP_DIR_STYLE) . '/';
        $plen = strlen($prefix);
        $index = count($this->_links);

        // if this is not a valid url, ensure we always have the href include the proper path
        if((filter_var($href, FILTER_VALIDATE_URL) === false) && (substr($href, 0, strlen($prefix)) != $prefix)) $href = $prefix . $href;

        // determine if this is a stylesheet contained in a subfolder for production mode, if so just output
        // the folder name since it was minimized as a package file when the application was shrunk
        $sub = strpos(substr($href, $plen), '/');

        // we use this to determine the exact output order, first and formost it's rel type, then the system
        // takes priority, next in line is individual files, and lastly packed files, from that point on
        // it's the order the files were input into the system to allow the caller some control over it
        $add = $this->_sysInclude ? 0 : 10000;
        $add += ($sub === false) ? 0 : 10000;

        if(!Base::getInstance()->isDebug)
        {
            $found = false;
            $dirhref = dirname($href);

            // since the file names are "rolled up" into a psuedo package we can have duplicates, so check for that
            for($x=0; $x < $index; $x++) { if($this->_links[$x][Output::IX_HREF] == $dirhref) { $found = true; break; } }

            if(!$found)
            {
                // we have to kludge this a bit by dancing around the setLin() routine
                $this->setLink(($sub === false) ? $href : $dirhref, $relationship, $type, $media);
                $this->_links[$index][Output::IX_ORDER] = $this->_linkOrder + $add;
            }
        }
        else
        {
            // we have to kludge this a bit by dancing around the setLin() routine
            $this->setLink($href, $relationship, $type, $media);
            $this->_links[$index][Output::IX_ORDER] = $this->_linkOrder + $add;
        }
    }

    public function setTitle($title)
    {
        $title = trim($title);
        if($title != null) $this->_title = $title;
    }

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function showContent(&$content, $markup = true)
    {
        @ob_clean();

        if(is_array($content))
        {
            // only add extra markup if this is a web request
            if(!BP_SYS_CLI && ($markup == true))
            {
                echo self::__getTopMarkup();
                print_r($content);
                echo self::__getBottomMarkup();
            }
            else
                print_r($content);
        }
        else if(trim($content) != null)
        {
            // only add extra markup if this is a web request
            if(!BP_SYS_CLI && ($markup == true))
            {
                echo self::__getTopMarkup();
                echo trim($content);
                echo self::__getBottomMarkup();
            }
            else
                echo trim($content);
        }

        exit;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function showFile($file, array &$vars = null, $markup = true)
    {
        @ob_clean();

        if(is_file($file))
        {
            // if passed, put $vars into scope before including the file
            if(($vars != null) && is_array($vars) && (count($vars) > 0)) extract($vars, EXTR_REFS);

            // only add extra markup if this is a web request
            if(!BP_SYS_CLI && ($markup == true))
            {
                echo self::__getTopMarkup();
                include($file);
                echo self::__getBottomMarkup();
            }
            else
                include($file);
        }

        exit;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function showFiles(array &$files, array &$vars = null, $markup = true)
    {
        @ob_clean();
        $count = count($files);

        if($count > 0)
        {
            // if passed, put $vars into scope before including the file
            if(($vars != null) && is_array($vars) && (count($vars) > 0)) extract($vars, EXTR_REFS);

            // only add extra markup if this is a web request
            if(!BP_SYS_CLI && ($markup == true)) echo self::__getTopMarkup();

            for($x = 0; $x < $count; $x++)
            {
                if(is_file($files[$x]))
                {
                    echo PHP_EOL;
                    include($files[$x]);
                    echo PHP_EOL;
                }
            }

            // only add extra markup if this is a web request
            if(!BP_SYS_CLI && ($markup == true)) echo self::__getBottomMarkup();
        }

        exit;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the file contents from a client-side language file dumped into STDOUT, the full parameter
    // determinues whether or not the full (debug) or minimized version is returned as the output
    public function showLanguageFile($cached = false)
    {
        $match = strtolower('/' . BP_DIR_LANG . '/');
        $uri = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;

        if($match == substr($uri, 0, strlen($match)))
        {
            @ob_clean();
            $filename = null;
            header('Content-Type: application/json');

            // if no extension was specified in the URL, then default to the predefined script extension
            $ext = (pathinfo($uri, PATHINFO_EXTENSION) == null) ? BP_EXT_JSON : null;

            if($cached)
                $filename = BP_PATH_SYS_CACHE_APP . BP_DIR_LANG . DIRECTORY_SEPARATOR . substr($uri, strlen($match)) . $ext;
            else
                $filename = BP_PATH_APP_CLIENT . BP_DIR_LANG . DIRECTORY_SEPARATOR . substr($uri, strlen($match)) . $ext;

            $filename = realpath($filename);
            if(is_file($filename)) @readfile($filename);

            exit();
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the file contents from a script file dumped into STDOUT, the full parameter
    // determinues whether or not the full (debug) or minimized version is returned as the output
    public function showScriptFile($cached = false)
    {
        $match = strtolower('/' . BP_DIR_SCRIPT . '/');
        $uri = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;

        if($match == substr($uri, 0, strlen($match)))
        {
            @ob_clean();
            $sysfile = null;
            $appfile = null;
            $pakfile = null;
            $filename = null;

            header('Content-Type: application/javascript');

            // if no extension was specified in the URL, then default to the predefined script extension
            $ext = (pathinfo($uri, PATHINFO_EXTENSION) == null) ? BP_EXT_SCRIPT : null;

            // match both system and application scripts, system takes priority however
            if($cached)
            {
                $sysfile = BP_PATH_SYS_CACHE;
                $appfile = BP_PATH_SYS_CACHE_APP . BP_DIR_SCRIPT . DIRECTORY_SEPARATOR;
                $pakfile = $appfile;
            }
            else
            {
                $sysfile = BP_PATH_SYSTEM . BP_DIR_SCRIPT . DIRECTORY_SEPARATOR;
                $appfile = BP_PATH_APP_CLIENT . BP_DIR_SCRIPT . DIRECTORY_SEPARATOR;
            }

            $filename = substr($uri, strlen($match));

            $sysfile .= $filename . $ext;
            $appfile .= $filename . $ext;
            $pakfile .= $filename . BP_EXT_PACK . $ext;

            $sysfile = realpath($sysfile);
            $appfile = realpath($appfile);
            $pakfile = realpath($pakfile);

            // system scripts take priority over application scripts
            if(is_file($sysfile))
                @readfile($sysfile);

            // load the application script if it exists
            else if(is_file($appfile))
                @readfile($appfile);

            // lastly, if we get here then check for a packed file
            else if(is_file($pakfile))
                @readfile($pakfile);

            exit();
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the file contents from an application stylesheet file dumped into STDOUT, the full parameter
    // determinues whether or not the full (debug) or minimized version is returned as the output
    public function showStylesheetFile($cached = false)
    {
        $match = strtolower('/' . BP_DIR_STYLE . '/');
        $uri = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;

        if($match == substr($uri, 0, strlen($match)))
        {
            @ob_clean();
            $sysfile = null;
            $appfile = null;
            $pakfile = null;
            $filename = null;

            header('Content-Type: text/css');

            // if no extension was specified in the URL, then default to the predefined stylesheet extension
            $ext = (pathinfo($uri, PATHINFO_EXTENSION) == null) ? BP_EXT_STYLE : null;

            // match both system and application scripts, system takes priority however
            if($cached)
            {
                $sysfile = BP_PATH_SYS_CACHE;
                $appfile = BP_PATH_SYS_CACHE_APP . BP_DIR_STYLE . DIRECTORY_SEPARATOR;
                $pakfile = $appfile;
            }
            else
            {
                $sysfile = BP_PATH_SYSTEM . BP_DIR_STYLE . DIRECTORY_SEPARATOR;
                $appfile = BP_PATH_APP_CLIENT . BP_DIR_STYLE . DIRECTORY_SEPARATOR;
            }

            $filename = substr($uri, strlen($match));

            $sysfile .= $filename . $ext;
            $appfile .= $filename . $ext;
            $pakfile .= $filename . BP_EXT_PACK . $ext;

            $sysfile = realpath($sysfile);
            $appfile = realpath($appfile);
            $pakfile = realpath($pakfile);

            // system stylesheets take priority over application scripts
            if(is_file($sysfile))
                @readfile($sysfile);

            // load the application stylesheet if it exists
            else if(is_file($appfile))
                @readfile($appfile);

            // lastly, if we get here then check for a packed file
            else if(is_file($pakfile))
                @readfile($pakfile);

            exit();
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////// PRIVATE ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function __getBottomMarkup()
    {
        return PHP_EOL . "</body>" . PHP_EOL . "</html>";
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	private function __getTopMarkup()
	{
        $eol = PHP_EOL;
        $base = Base::getInstance();
        $output = null;
        $charset = BP_SYS_CHARSET;
        $header = null;
        $manifest = null;
        $noscript = _l('noscript', true);

        // create the contents of the header based on what the caller supplyed via get/set operations
        foreach($this->_metas as $meta)
        {
            if($meta[Output::IX_EQUIVALENT])
                $header .= "$eol    <meta http-equiv=\"{$meta[Output::IX_NAME]}\" content=\"{$meta[Output::IX_CONTENT]}\">";
            else
                $header .= "$eol    <meta name=\"{$meta[Output::IX_NAME]}\" content=\"{$meta[Output::IX_CONTENT]}\">";
        }

        if($this->_title != null) $header .= "$eol    <title>{$this->_title}</title>";

        // if this is less than IE 9 then don't bother with outputing scripts or styles to avoid errors
        // all other browsers get an automatic pass, welcome to web development
        $browser = get_browser(null, true);
        if((strtoupper(trim($browser['browser'])) != 'IE') || ($browser['majorver'] >= 9))
        {
            // we sort to determine the exact output order, first and formost it's rel type, then the system
            // takes priority, next in line is individual files, and lastly packed files, from that point on
            // it's the order the files were input into the system to allow the caller some control over it
            usort($this->_links, function($a, $b)
            {
                return ($a[Output::IX_RELATE] > $b[Output::IX_RELATE]) || ($a[Output::IX_ORDER] > $b[Output::IX_ORDER]);
            });

            foreach($this->_links as $link)
            {
                if($link[Output::IX_TYPE] == null)
                    $header .= "$eol    <link rel=\"{$link[Output::IX_RELATE]}\" href=\"{$link[Output::IX_HREF]}\"";
                else
                    $header .= "$eol    <link rel=\"{$link[Output::IX_RELATE]}\" " .
                    "href=\"{$link[Output::IX_HREF]}\" type=\"{$link[Output::IX_TYPE]}\"";

                $header .= ($link[Output::IX_MEDIA] != null) ? ' media="' . $link[Output::IX_MEDIA] . '">' : '>' ;
            }

            // this must appear before the scripts and styles above to allow for local overrides, and we
            // always output in separate tags in case we wish to inline styles for different media types
            foreach($this->_styles as $style)
            {
                $header .= "$eol    <style";
                $header .= ($style[Output::IX_MEDIA] != null) ? " media=\"{$style[Output::IX_MEDIA]}\">$eol" : ">$eol";
                $header .= preg_replace('/^/m', "        ", $style[Output::IX_CONTENT]);
                $header .= "$eol    </style>";
            }

            // scripts must appear after styles since the noscript will most likely override everything,
            // also we sort to determine the exact output order, first and formost the system takes
            // priority, next in line is individual files, and lastly packed files, from that point on
            // it's the order the files were input into the system to allow the caller some control over it
            usort($this->_scriptlinks, function($a, $b){ return $a[Output::IX_ORDER] > $b[Output::IX_ORDER]; });

            foreach($this->_scriptlinks as $script)
            {
                $header .= "$eol    <script src=\"{$script[Output::IX_SOURCE]}\"";
                if($script[Output::IX_TYPE] != null) $header .= " type=\"{$script[Output::IX_TYPE]}\"";
                if($script[Output::IX_ASYNC]) $header .= " async=\"async\"";
                if($script[Output::IX_DEFER]) $header .= " defer=\"defer\"";
                $header .= '></script>' ;
            }

            // inline scripts must appear after styles and scripts in case overrides must occur, also we
            // use separate script tags for every iteration in case the caller wishes to embed several
            // different types of scripts into the same document, you never know what the future holds
            foreach($this->_scripts as $script)
            {
                $header .= "$eol    <script";
                $header .= ($script[Output::IX_TYPE] != null) ? " type=\"{$script[Output::IX_TYPE]}\">$eol" : ">$eol";
                $header .= preg_replace('/^/m', "        ", $script[Output::IX_CONTENT]);
                $header .= "$eol    </script>";
            }
        }

        // never do we cache a thing for debug mode as the quick changes required would make this a maintenance nightmare
        // and we also piggy back on the server cache directory name for the file name to use for this
        if(!$base->isDebug && $base->isCached && is_file(BP_PATH_APP . BP_DIR_CACHE . BP_EXT_MANIFEST))
            $manifest = ' manifest="/' . BP_DIR_CACHE . BP_EXT_MANIFEST . '"';

        // if possible, output the info to identify just the essential application info to the client as well as server messages
        $name = trim($base->name);
        $version = trim($base->version);
        $oldIeVer = str_replace("'", "\\'", _l('oldieversion', true));

        $appdata = $name == null ? null : "<meta name=\"name\" content=\"" . str_replace('"', '&quot;', $name) . "\">\n    ";
        $appdata .= $version == null ? null : "<meta name=\"version\" content=\"" . number_format(floatval($version), 2) . "\">\n    ";

        // the test for IE should appear after css and script includes, this is a special case as
        // normally the setScript() method should be called when wishing to include a script, the
        // below test will wipeout the entire page if an older version of IE is used

        // note, since a hacker can change the user agent string, we always use client checking
        // on top of the server side to prevent any tampering as much as possible
        // note: make sure we keep the extra newline at the top to keep things clean

        // note, system output should always be white-label
        $output = <<< EOT
<!doctype html>
<html lang="{$base->language}" class="no-js"$manifest>
<head>
    <meta charset="$charset">
    $appdata<meta name="viewport" content="initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">$header
    <!--[if lt IE 9]>
        <style>
            *
            {
                background: none    !important;
                direction:  ltr     !important;
                display:    none    !important;
                font-size:  0       !important;
                height:     0       !important;
                line-height:-9999   !important;
                margin:     0       !important;
                padding:    0       !important;
                position:   static  !important;
                text-indent:-9999em !important;
                width:      0       !important;
                white-space:normal  !important;
            }
        </style>
        <script type="text/javascript">
            if(confirm('$oldIeVer'))
                location.href='http://microsoft.com/ie/';
        </script>
    <![endif]-->
    <noscript><span style="position:absolute;display:block;width:100%;text-align:center;top:45%;">$noscript</span></noscript>
</head>
<body>

EOT;

        return $output;
	}
}

?>