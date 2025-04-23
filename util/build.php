<?php
    require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'index.php');

    \System\Base::getInstance()->loadModule('cssmin');
    \System\Base::getInstance()->loadModule('jsmin');

    $extScript = [BP_EXT_SCRIPT, BP_EXT_JSON]; // if you're using custom extensions, then add them to this array
    $extStyle = [BP_EXT_STYLE];                // if you're using custom extensions, then add them to this array

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function isEmpty($dir)
    {
        if(!is_readable($dir)) return null;
        $handle = opendir($dir);

        while(false !== ($entry = readdir($handle)))
        {
            if(($entry != '.') && ($entry != '..')) return false;
        }

        return true;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function delTree($dir, $deleteBase = false)
    {
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach($files as $file)
        {
            (is_dir("$dir/$file")) ? delTree("$dir/$file", true) : unlink("$dir/$file");
        }

        return $deleteBase ? rmdir($dir) : true;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function minTree($sourceDir, $destDir)
    {
        global $extScript, $extStyle;
        $usePecl = extension_loaded('jsmin');
        $filemap = [];

        // simple sanitation checks
        if(substr($sourceDir, -1) != DIRECTORY_SEPARATOR) $sourceDir .= DIRECTORY_SEPARATOR;
        if(substr($destDir, -1) != DIRECTORY_SEPARATOR) $destDir .= DIRECTORY_SEPARATOR;

        $files = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
        );

        foreach($files as $file)
        {
            if(!$file->isDir() && $file->isReadable())
            {
                // combine files that are in subdirectories before we minimize them, when done they act as sort
                // of a psuedo phar file, this will save on numerous server requests when accessed this way,
                // we then add the pack extension on top of it to indicate what happened
                $outfile = (basename($files->getSubPathName()) == $files->getSubPathName())
                    ? $files->getSubPathName() : dirname($files->getSubPathName()) .
                    BP_EXT_PACK . '.' . $file->getExtension();

                $outfile = $destDir . str_replace(array(DIRECTORY_SEPARATOR, ' '),
                    array('-', '+'), strtolower($outfile));

                // script files, check for both leading dots and none
                if(in_array('.' . $file->getExtension(), $extScript) || in_array($file->getExtension(), $extScript))
                {
                    // if the PECL JavaScript minifier is installed, use that instead of our custom one
                    if($usePecl)
                        $result = jsmin(file_get_contents($file));
                    else
                        $result = JsMin::minify(file_get_contents($file));

                    file_put_contents($outfile, $result, FILE_APPEND|LOCK_EX);
                }

                // stylesheet files, check for both leading dots and none
                else if(in_array('.' . $file->getExtension(), $extStyle) || in_array($file->getExtension(), $extStyle))
                {
                    $result = CssMin::minify(file_get_contents($file));
                    file_put_contents($outfile, $result, FILE_APPEND|LOCK_EX);
                }

                // catch all for safety (since a full version is better than no version)
                else file_put_contents($outfile, file_get_contents($file), FILE_APPEND|LOCK_EX);
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // do not process anything unless this script was ran over CLI
    if(BP_SYS_CLI)
    {
        $appLangDir = BP_PATH_APP_CLIENT . BP_DIR_LANG . DIRECTORY_SEPARATOR;
        $appScriptDir = BP_PATH_APP_CLIENT . BP_DIR_SCRIPT . DIRECTORY_SEPARATOR;
        $appStyleDir = BP_PATH_APP_CLIENT . BP_DIR_STYLE . DIRECTORY_SEPARATOR;
        $appVmDir = BP_PATH_APP_CLIENT . BP_DIR_VM . DIRECTORY_SEPARATOR;

        $sysLangDir = BP_PATH_SYS_CACHE_APP . BP_DIR_LANG . DIRECTORY_SEPARATOR;
        $sysScriptDir = BP_PATH_SYS_CACHE_APP . BP_DIR_SCRIPT . DIRECTORY_SEPARATOR;
        $sysStyleDir = BP_PATH_SYS_CACHE_APP . BP_DIR_STYLE . DIRECTORY_SEPARATOR;
        $sysVmDir = BP_PATH_SYS_CACHE_APP . BP_DIR_VM . DIRECTORY_SEPARATOR;

        $options = getopt('fdp');
        $confirm = true;

        // if the user wishes to force a silent invocation, then we get trigger happy
        if(($options !== false) && isset($options['f'])) $confirm = false;

        if($confirm && (!isEmpty($sysLangDir) || !isEmpty($sysScriptDir) ||
            !isEmpty($sysStyleDir) || !isEmpty($sysVmDir)))
        {
            echo _l('promptmin', true);
            echo PHP_EOL;
            echo _l('promptconfirm', true);
            echo ' ';

            $handle = fopen('php://stdin', 'r');
            $line = fgets($handle);

            $confirm = (bool)filter_var($line, FILTER_VALIDATE_BOOLEAN);

            fclose($handle);
            if(!$confirm) exit;
        }

        // purge the old output / runtime scripts and stylesheets
        delTree($sysLangDir);
        delTree($sysScriptDir);
        delTree($sysStyleDir);
        delTree($sysVmDir);

        // TODO: only if not debug mode from config
        //if( not debug mode )
        {
            // process the full versions into minimized versions
            minTree($appLangDir, $sysLangDir);
            minTree($appScriptDir, $sysScriptDir);
            minTree($appStyleDir, $sysStyleDir);
            minTree($appVmDir, $sysVmDir);
        }
    }
    else
        echo _l('errcli', true);
?>