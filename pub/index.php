<?php

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / system-wide constants in the global namespace
/ / --------------------------------------------------------------------------------------------------------------------
/*/

define('BP_TIME_START', microtime(true));

define('BP_DIR_APP', 'app');
define('BP_DIR_ACTION', 'action');
define('BP_DIR_CACHE', 'cache');
define('BP_DIR_CLIENT', 'client');
define('BP_DIR_COMPONENT', 'component');
define('BP_DIR_EXTERNAL', 'external');
define('BP_DIR_FILE', 'file');
define('BP_DIR_INTERFACE', 'interface');
define('BP_DIR_LANG', 'lang');
define('BP_DIR_LOG', 'log');
define('BP_DIR_MODEL', 'model');
define('BP_DIR_PUBLIC', 'pub');
define('BP_DIR_SCRIPT', 'script');
define('BP_DIR_SERVER', 'server');
define('BP_DIR_SERVICE', 'service');
define('BP_DIR_STYLE', 'style');
define('BP_DIR_SYSTEM', 'sys');
define('BP_DIR_UTIL', 'util');

define('BP_EXT_MANIFEST', '.manifest');
define('BP_EXT_JSON', '.json');
define('BP_EXT_PACK', '.pak');
define('BP_EXT_PHP', '.' . pathinfo(__FILE__, PATHINFO_EXTENSION));
define('BP_EXT_SCRIPT', '.js');
define('BP_EXT_STYLE', '.scss');

// when memory caching is turned on (default is off) this is how long in seconds a cache is valid
define('BP_CACHE_TIME', 86400);

// this is used in production mode to seed the error number before converting it to octal and
// showing it to the end user, set it to something unique to this installation
define('BP_ERR_SEED', 100);

define('BP_SYS_CLI', ((php_sapi_name() === 'cli') && empty($_SERVER['REMOTE_ADDR'])));
define('BP_SYS_CHARSET', 'utf-8');
define('BP_SYS_NAME', 'BytePattern');
define('BP_SYS_VER', 0.2);

// always use these when including files to avoid path errors with CLI requests
define('BP_PATH_PUBLIC', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('BP_PATH_ROOT', dirname(BP_PATH_PUBLIC) . DIRECTORY_SEPARATOR);
define('BP_PATH_APP', BP_PATH_ROOT . BP_DIR_APP . DIRECTORY_SEPARATOR);
define('BP_PATH_FILE', BP_PATH_ROOT . BP_DIR_FILE . DIRECTORY_SEPARATOR);
define('BP_PATH_LOG', BP_PATH_ROOT . BP_DIR_LOG . DIRECTORY_SEPARATOR);
define('BP_PATH_SYSTEM', BP_PATH_ROOT . BP_DIR_SYSTEM . DIRECTORY_SEPARATOR);
define('BP_PATH_UTIL', BP_PATH_ROOT . BP_DIR_UTIL . DIRECTORY_SEPARATOR);

define('BP_PATH_APP_CLIENT', BP_PATH_APP . BP_DIR_CLIENT . DIRECTORY_SEPARATOR);
define('BP_PATH_APP_SERVER', BP_PATH_APP . BP_DIR_SERVER . DIRECTORY_SEPARATOR);
define('BP_PATH_APP_STYLE', BP_PATH_APP . BP_DIR_STYLE . DIRECTORY_SEPARATOR);

define('BP_PATH_APP_SERVER_COMPONENT', BP_PATH_APP_SERVER . BP_DIR_COMPONENT . DIRECTORY_SEPARATOR);
define('BP_PATH_APP_SERVER_LANG', BP_PATH_APP_SERVER . BP_DIR_LANG . DIRECTORY_SEPARATOR);
define('BP_PATH_APP_SERVER_MODEL', BP_PATH_APP_SERVER . BP_DIR_MODEL . DIRECTORY_SEPARATOR);
define('BP_PATH_APP_SERVER_SERVICE', BP_PATH_APP_SERVER . BP_DIR_SERVICE . DIRECTORY_SEPARATOR);

define('BP_PATH_SYSTEM_CACHE', BP_PATH_SYSTEM . BP_DIR_CACHE . DIRECTORY_SEPARATOR);
define('BP_PATH_SYSTEM_CACHE_CLIENT', BP_PATH_SYS_CACHE . BP_DIR_CLIENT . DIRECTORY_SEPARATOR);
define('BP_PATH_SYSTEM_CACHE_SERVER', BP_PATH_SYS_CACHE . BP_DIR_SERVER . DIRECTORY_SEPARATOR);

define('BP_PATH_SYSTEM_EXTERNAL', BP_PATH_SYSTEM . BP_DIR_EXTERNAL . DIRECTORY_SEPARATOR);
define('BP_PATH_SYSTEM_INTERFACE', BP_PATH_SYSTEM . BP_DIR_INTERFACE . DIRECTORY_SEPARATOR);
define('BP_PATH_SYSTEM_LANG', BP_PATH_SYSTEM . BP_DIR_LANG . DIRECTORY_SEPARATOR);

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / initial checks before the bootstrap
/ / --------------------------------------------------------------------------------------------------------------------
/*/

// these should be the only non-localized output strings ever sent to STDOUT
(version_compare(PHP_VERSION, '5.5.0') >= 0) or exit('PHP 5.5 or newer needs to be installed.');

// this file is one the few areas in the system we use inline literals to present info to the user
is_dir(BP_PATH_LOG) or exit('The log file directory is missing.');
is_dir(BP_PATH_UTIL) or exit('The utility directory is missing.');

// ensure the required directories are present (for performance, parent directories are assumed)
is_dir(BP_PATH_APP_CLIENT) or exit('The application client directory is missing.');
is_dir(BP_PATH_APP_SERVER_COMPONENT) or exit('The application server component directory is missing.');
is_dir(BP_PATH_APP_SERVER_LANG) or exit('The application server localization directory is missing.');
is_dir(BP_PATH_APP_SERVER_MODEL) or exit('The application server model directory is missing.');
is_dir(BP_PATH_APP_SERVER_SERVICE) or exit('The application server service directory is missing.');
is_dir(BP_PATH_APP_STYLE) or exit('The application style directory is missing.');
is_dir(BP_PATH_SYSTEM_EXTERNAL) or exit('The system external directory is missing.');
is_dir(BP_PATH_SYSTEM_INTERFACE) or exit('The system interface directory is missing.');
is_dir(BP_PATH_SYSTEM_LANG) or exit('The system localization is missing.');

// ensure the cache sub directories are always present in case they get wiped by a script
// note: mkdir should be called as silent here to not conflict with output buffering
is_dir(BP_PATH_SYSTEM_CACHE_CLIENT) or @mkdir(BP_PATH_SYSTEM_CACHE_CLIENT, 0755, true);
is_dir(BP_PATH_SYSTEM_CACHE_SERVER) or @mkdir(BP_PATH_SYSTEM_CACHE_SERVER, 0755, true);

// after the configuration and language files are processed, only localized strings should be displayed
is_file(BP_PATH_ROOT . 'config' . BP_EXT_JSON) or exit('Missing configuration file.');

// include the bare minimum we need to get the system going
require_once BP_PATH_SYSTEM . 'base' . BP_EXT_PHP;
require_once BP_PATH_SYSTEM . 'error' . BP_EXT_PHP;

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / for security, install error handling hooks before the bootstrap
/ / --------------------------------------------------------------------------------------------------------------------
/*/

set_exception_handler(array('\\System\\Error', 'onException'));
set_error_handler(array('\\System\\Error', 'onError'));

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / convenience routines used with retrieving custom config values and localization strings
/ / --------------------------------------------------------------------------------------------------------------------
/*/

function _c($key) { return \System\Base::getInstance()->getCustomValue($key); }
function _l($key, $system = false) { return \System\Base::getInstance()->getLangValue($key, $system); }

/*/
/ / --------------------------------------------------------------------------------------------------------------------
/ / system entry point, let's run this sucker
/ / --------------------------------------------------------------------------------------------------------------------
/*/

\System\Base::getInstance()->initialize();

?>
