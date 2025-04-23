<?php
    require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'index.php');

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function delTree($dir, $deleteBase = false)
    {
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach($files as $file)
        {
            (is_dir("$dir/$file")) ? delTree("$dir/$file", true) : unlink("$dir/$file");
        }

        return $deleteBase ? rmdir($dir) : true;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // do not process anything unless this script was ran over CLI
    if(BP_SYS_CLI)
    {
        $options = getopt('f');

        // if the user wishes to force a silent invocation, then we get trigger happy
        if(($options === false) || !isset($options['f']))
        {
            // confirm first, in case this script was ran by accident
            if(!isEmpty(BP_PATH_SYS_CACHE_OUTPUT))
            {
                echo _l('promptcache', true);
                echo PHP_EOL;
                echo _l('promptconfirm', true);
                echo ' ';

                $handle = fopen('php://stdin', 'r');
                $line = fgets($handle);

                $confirm = (bool)filter_var($line, FILTER_VALIDATE_BOOLEAN);

                fclose($handle);
                if(!$confirm) exit;
            }
        }

        // nuke everything in the output cache directory
        delTree(BP_PATH_SYS_CACHE_OUTPUT);
    }
    else
        echo _l('errcli', true);
?>