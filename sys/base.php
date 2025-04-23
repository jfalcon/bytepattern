<?php

namespace System;

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / system-wide base class (implemented as a singleton)
/ / --------------------------------------------------------------------------------------------------------------------
/*/

final class Base
{
    const IX_ADMIN = 'admin';
    const IX_APP = 'application';
    const IX_CACHE = 'cache';
    const IX_CONFIG = 'configuration';
    const IX_CUSTOM = 'custom';
    const IX_CLIENT = 'client';
    const IX_DB = 'database';
    const IX_DEBUG = 'debug';
    const IX_EMAIL = 'email';
    const IX_ENV = 'environment';
    const IX_HOST = 'host';
    const IX_IGNORE = 'ignore';
    const IX_LANG = 'language';
    const IX_LIB = 'library';
    const IX_LOCALE = 'locale';
    const IX_NAME = 'name';
    const IX_PASS = 'password';
    const IX_USER = 'username';
    const IX_SERVER = 'server';
    const IX_SYS = 'system';
    const IX_VER = 'version';

    public $isDebug = false;     // for debug mode we do not perform any optimizations for speed
    public $isCached = false;    // this flag is used to tell us if output and HTML 5 application caching is enabled
    public $isLocalized = false; // server localization is required but client is optional, this flag tells us if it's set

    public $adminName = null;   // set in the config file, stored as a property for convenience
    public $adminEmail = null;  // set in the config file, stored as a property for convenience
    public $dbUser = null;      // set in the config file, stored as a property for convenience
    public $dbPassword = null;  // set in the config file, stored as a property for convenience
    public $dbServer = null;    // set in the config file, stored as a property for convenience
    public $language = 'en-us'; // set in the config file, stored as a property for convenience
    public $locale = 'en_US';   // set in the config file, stored as a property for convenience
    public $name = null;        // set in the config file, stored as a property for convenience
    public $version = 0.0;      // set in the config file, stored as a property for convenience

	private static $_instance = null;
    private static $_initAlready = false;
	private $_router = null;

	// must be private for the singleton to work, be very selective about exceptions here as the
    // error handling depends on this class and we would like to avoid a circular reference
	private function __construct()
	{
		// required options
        ini_set('output_buffering', 1);
        ini_set('error_log', BP_PATH_LOG . pathinfo(__FILE__, PATHINFO_EXTENSION));
        ini_set('log_errors', 1);
        ini_set('ignore_repeated_errors', 1);

		session_name(BP_SYS_NAME);
		if(!isset($_SESSION)) session_start();

        // ensure consistency across different installations of PHP
        if(get_magic_quotes_runtime()) set_magic_quotes_runtime(false);
		date_default_timezone_set(date_default_timezone_get());

        mb_http_output(BP_SYS_CHARSET);
        mb_internal_encoding(BP_SYS_CHARSET);
        mb_regex_encoding(BP_SYS_CHARSET);

		// convenience options for the system and application files, system includes take priority
        set_include_path(implode(PATH_SEPARATOR, array(get_include_path(), BP_PATH_SYSTEM, BP_PATH_APP)));
        spl_autoload_extensions(BP_EXT_PHP);

        // object factory
        spl_autoload_register(function ($class)
        {
            $class = strtolower(trim($class));
            $file = null;

            // reject any class that does not stem from the system or application namespaces
            if(substr($class, 0, 6) === 'system')
                // if this is in the system namespace load it from the sys directory
                $file = BP_PATH_SYSTEM . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 7)) . BP_EXT_PHP;

            else if(substr($class, 0, 11) === 'application')
            {
                if(substr($class, 12) == 'controller')
                    // this is a special case to allow the developer a custom override to the controller class
                    $file = BP_PATH_APP . DIRECTORY_SEPARATOR . 'controller' . BP_EXT_PHP;
                else
                    // if this is in the application namespace load it from the general app class directory
                    $file = BP_PATH_APP_SERVER . DIRECTORY_SEPARATOR . BP_DIR_GENERAL .
                        str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 12)) . BP_EXT_PHP;
            }

            if(file_exists($file) && is_readable($file)) include_once $file;
        });

        // these only process once per session and must be called in this order
        $this->__setConfigData();
        $this->__setLangData();

        // required options that must be set after configs are read
        setlocale(LC_ALL, $this->locale);

    	// in production environments do not show errors to the user, keep in mind that
        // exceptions will still be picked up and handled by the custom error handling
    	if($this->isDebug)
        {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        else
        {
            error_reporting(E_ALL ^ E_NOTICE);
            ini_set('display_errors', 0);
        }
	}

	private function __clone() {}
	public function __destruct() { if(ob_get_level() > 0) @ob_end_flush(); }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////// PUBLIC ROUTINES //////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// used to return the only instance of this class
	public static function &getInstance()
	{
		if(self::$_instance === null) self::$_instance = new Base;
		return self::$_instance;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// used to return the instance of the router object the base class uses
	public function &getRouter()
	{
		if($this->_router === null) $this->_router = new Router;
		return $this->_router;
	}

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // used to return the values in the custom section from the config file
    public function getCustomValue($key)
    {
        $key = strtolower(trim($key));
        return (@isset($_SESSION[Base::IX_CONFIG][Base::IX_CUSTOM][$key])) ?
            $_SESSION[Base::IX_CONFIG][Base::IX_CUSTOM][$key] : null;
    }

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// used to return the language values in either the system or application language config files
	public function getLangValue($key, $system = false)
	{
		$key = strtolower(trim($key));

        if($system)
            return (@isset($_SESSION[Base::IX_LANG][Base::IX_SYS][$key])) ?
                $_SESSION[Base::IX_LANG][Base::IX_SYS][$key] : null;
        else
            return (@isset($_SESSION[Base::IX_LANG][Base::IX_APP][$key])) ?
                $_SESSION[Base::IX_LANG][Base::IX_APP][$key] : null;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// the base class uses two-step construction for the bootstrap
	public function initialize(Router $router = null, $maintenanceMode = false)
	{
        // we only do this once
        if(!self::$_initAlready)
        {
            // this flag must be set prior to any processing in this routine
            self::$_initAlready = true;

            // if the server is down, abort everything and let the user know
            if($maintenanceMode) throw new Error(_l('maintenance', true));

            // no point in serving up web files if ran over the command line
            if(!BP_SYS_CLI)
            {
                // if we are simply serving a client script, we're done with processing so serve it and scram
                $output = new Output;
                $output->showScriptFile(!$this->isDebug);

                // also, if we are simply serving a stylesheet, we're done with processing so serve it and scram
                $output->showStylesheetFile(!$this->isDebug);

                // and of course, if we are simply serving a client-side language file, we're done with processing so serve it and scram
                if($this->isLocalized) $output->showLanguageFile(!$this->isDebug);

                // note: this MUST be called after the above output calls or else things will break because the
                // output will be optimized twice for included files and all hell breaks loose at that point
                if(!$this->isDebug)
                {
                    // if we are simply serving a cached file, we're done with processing so serve it and exit
                    if($this->isCached)
                    {
                        $cache = new Cache;

                        // if this method determines it should serve the cache manifest file, the script stops here
                        $cache->readManifest();

                        // if this method finds a cached file, the script stops here
                        $cache->readFile();
                    }

                    // if we got here then we do not have a script, stylesheet, or cached file to serve,
                    // so continue on our merry way. also, all output to STDOUT gets minimized
                    ob_start(function($buffer)
                    {
                        $re = '%# Collapse whitespace everywhere but in blacklisted elements.
                            (?>             # Match all whitespans other than single space.
                            [^\S ]\s*     # Either one [\t\r\n\f\v] and zero or more ws,
                            | \s{2,}        # or two or more consecutive-any-whitespace.
                            ) # Note: The remaining regex consumes no text at all...
                            (?=             # Ensure we are not in a blacklist tag.
                            [^<]*+        # Either zero or more non-"<" {normal*}
                            (?:           # Begin {(special normal*)*} construct
                            <           # or a < starting a non-blacklist tag.
                            (?!/?(?:textarea|pre|script)\b)
                            [^<]*+      # more non-"<" {normal*}
                            )*+           # Finish "unrolling-the-loop"
                            (?:           # Begin alternation group.
                            <           # Either a blacklist start tag.
                            (?>textarea|pre|script)\b
                                    | \z          # or end of file.
                            )             # End alternation group.
                            )  # If we made it here, we are not in a blacklist tag.
                            %Six';

                        // minimize the output
                        $buffer = preg_replace($re, ' ', $buffer);

                        // write out the minimized output to a cached file if caching is on
                        if($this->isCached)
                        {
                            $cache = new Cache;
                            $cache->setFile($buffer);
                        }

                        // and finally, for production mode on a web request return the minizmed output
                        return $buffer;
                    });
                }
                else
                    // for debug mode on a web request show everything untouched
                    ob_start();
            }

            // allow for dependency injection so the user can override the router completely
            $this->_router = ($router != null) ? $router : $this->getRouter();

            // execute any code in the application's bootstrap file
            if(is_file(BP_PATH_APP . 'boot' . BP_EXT_PHP)) include_once BP_PATH_APP . 'boot' . BP_EXT_PHP;

            // we're up and runnig, so route the request, but only automatically if over the web
            if(!BP_SYS_CLI) $this->_router->doRoute();
        }
	}

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // wrap module loding to ensure they can never directly output data
    public function loadModule($module)
    {
        $module = strtolower(trim($module));
        if($module != null) @include_once(BP_PATH_SYSTEM . BP_DIR_MODULE . DIRECTORY_SEPARATOR . $module . BP_EXT_PHP);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////// PRIVATE ROUTINES /////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	private function __setConfigData()
	{
		// read config file from disk only once per session
		if(!isset($_SESSION[Base::IX_CONFIG]))
		{
            try
            {
                // config file must be in the root directory
                $xml = @simplexml_load_file(BP_PATH_ROOT . 'config' . BP_EXT_CONFIG);
                if($xml !== false)
                {
                    $config = function($xml, $out = []) use(&$config)
                    {
                        foreach($xml as $index => $node)
                        {
                            $data = (string)$node;
                            $data = trim($data);

                            // we can have several environment nodes, so only process the one
                            // for the current host by excluding the rest that do not match
                            if(($index == Base::IX_ENV) && isset($node['host']) && isset($_SERVER['HTTP_HOST'])
                                && !(bool)preg_match("/{$node['host']}/i", $_SERVER['HTTP_HOST'])) continue;

                            // we can have several custom nodes, so only process the one
                            // for the current host by excluding the rest that do not match
                            else if(($index == Base::IX_CUSTOM) && isset($node['host']) && isset($_SERVER['HTTP_HOST'])
                                && !(bool)preg_match("/{$node['host']}/i", $_SERVER['HTTP_HOST'])) continue;

                            // it's now safe to process any specialized data, so here if caching is not enabled we leave
                            else if(($index == Base::IX_CACHE) && isset($node['enable'])
                                && !(bool)filter_var($node['enable'], FILTER_VALIDATE_BOOLEAN)) continue;

                            // if we made here then it's safe to process the data, and we also account for repeated
                            // tags by placing them into yet another array dimension with numeric indexes
                            if(isset($out[$index]))
                            {
                                $count = count($out[$index]);
                                if($count == 1)
                                    $out[$index] = array($out[$index], (is_object($node) && !empty($node)) ?
                                        $config($node) : $data);
                                else
                                    $out[$index][$count] = (is_object($node) && !empty($node)) ?
                                        $config($node) : $data;
                            }
                            else
                                $out[$index] = (is_object($node) && !empty($node)) ? $config($node) : $data;
                        }

                        return $out;
                    };

                    $_SESSION[Base::IX_CONFIG] = $config($xml);
                }
            }
            catch (Exception $e)
            {
                throw new Error($e->getMessage());
            }
		}

        // make sure we set the convenience properties for easy access
        $this->language = strtolower($_SESSION[Base::IX_CONFIG][Base::IX_APP][Base::IX_LANG]);
        $this->locale = $_SESSION[Base::IX_CONFIG][Base::IX_APP][Base::IX_LOCALE];
        $this->name = $_SESSION[Base::IX_CONFIG][Base::IX_APP][Base::IX_NAME];
        $this->version = doubleval($_SESSION[Base::IX_CONFIG][Base::IX_APP][Base::IX_VER]);

        // direct environment properties are specific
        $this->isDebug = (bool)filter_var($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_DEBUG], FILTER_VALIDATE_BOOLEAN);
        $this->isCached = isset($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE]);

        $this->adminName = $_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_ADMIN][Base::IX_NAME];
        $this->adminEmail = $_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_ADMIN][Base::IX_EMAIL];

        $this->dbUser = $_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_DB][Base::IX_USER];
        $this->dbPassword = $_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_DB][Base::IX_PASS];
        $this->dbServer = $_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_DB][Base::IX_SERVER];
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	private function __setLangData()
	{
		// read server-side language files from disk only once per session
		if(!isset($_SESSION[Base::IX_LANG]))
		{
            // a system language file is required
            $syslang = @simplexml_load_file(BP_PATH_SYSTEM . BP_DIR_LANG . DIRECTORY_SEPARATOR . $this->language . BP_EXT_CONFIG);

    	    if($syslang !== false)
            {
                $str = new String();

                // load the system language string entries specified in the config file and store into the session
                foreach($syslang->entry as $entry)
                {
                    $key = strtolower(trim($entry['key']));
                    $value = trim($entry->__toString());

                    // expand the string before we save it
                    if(($key != null) && ($value != null)) $_SESSION[Base::IX_LANG][Base::IX_SYS][$key] = $str->expand((string)$value);
                }
            }

            // an application language file is optional (but strongly suggested)
            $file = BP_PATH_APP_SERVER . BP_DIR_LANG . DIRECTORY_SEPARATOR . $this->language . BP_EXT_CONFIG;
            if(is_file($file))
            {
                $applang = simplexml_load_file($file);
                if($applang !== false)
                {
                    $str = new String();

                    // load the system language string entries specified in the config file and store into the session
                    foreach($applang->entry as $entry)
                    {
                        $key = strtolower(trim($entry['key']));
                        $value = trim($entry->__toString());

                        // expand the string before we save it
                        if(($key != null) && ($value != null))
                            $_SESSION[Base::IX_LANG][Base::IX_APP][$key] = $str->expand((string)$value);
                    }
                }
            }

            // if the client-side language file is not present then we do not load it
            $langFile = $this->isDebug
                ? BP_PATH_APP_CLIENT . BP_DIR_LANG . DIRECTORY_SEPARATOR . $this->language . BP_EXT_JSON
                : BP_PATH_SYS_CACHE_APP . BP_DIR_LANG . DIRECTORY_SEPARATOR . $this->language . BP_EXT_JSON;

            $_SESSION[Base::IX_LANG][Base::IX_CLIENT] = is_file($langFile) ? true : false;
        }

        // this must always be called
        $this->isLocalized = isset($_SESSION[Base::IX_LANG][Base::IX_CLIENT])
            ? (bool)filter_var($_SESSION[Base::IX_LANG][Base::IX_CLIENT], FILTER_VALIDATE_BOOLEAN) : false;
    }
}

?>