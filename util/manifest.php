<?php
    require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'index.php');

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function getFiles($sourceFolder, $replaceUri, $extension = false)
    {
        $result = [];

        // make sure we normalize the input data
        if(substr($sourceFolder, -1) != DIRECTORY_SEPARATOR) $sourceFolder .= DIRECTORY_SEPARATOR;
        if(substr($replaceUri, -1) != '/') $replaceUri .= '/';

        $dir = new RecursiveDirectoryIterator($sourceFolder);
        $ite = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        $file = null;

        foreach($ite as $name => $object)
        {
            // never, ever do we cache PHP script files
            if(!$object->isDir() && $object->isFile() && (strtolower($object->getExtension()) != strtolower(substr(BP_EXT_PHP, 1))))
            {
                // combine files that are in subdirectories before we minimize them, when done they act as sort
                // of a psuedo phar file, this will save on numerous server requests when accessed this way,
                // we then add the pack extension on top of it to indicate what happened
                if(!$extension)
                {
                    $file = (basename($ite->getSubPathName()) == $ite->getSubPathName())
                        ? pathinfo($ite->getSubPathName(), PATHINFO_FILENAME) : dirname($ite->getSubPathName());
                }
                else
                    $file = substr($object->getPath(), strlen($sourceFolder))  . '/' . $object->getFilename();

                $file = $replaceUri . $file;

                // make sure we normalize and format the data
                $file = str_replace(array('//', ' ', '+'), array('/', '%20', '%2B'), $file);

                if(!in_array($file, $result)) array_push($result, $file);
            }
        }

        if(count($result) > 0)
            return implode(PHP_EOL, $result) . PHP_EOL;
        else
            return null;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // do not process anything unless this script was ran over CLI
    if(BP_SYS_CLI)
    {
        $output = "CACHE MANIFEST" . PHP_EOL . PHP_EOL . "# " . gmdate(DATE_RFC2822) . PHP_EOL . PHP_EOL . "CACHE:" . PHP_EOL;

        $langDir = BP_PATH_APP_CLIENT . BP_DIR_LANG;
        $scriptDir = BP_PATH_APP_CLIENT . BP_DIR_SCRIPT;
        $styleDir = BP_PATH_APP_CLIENT . BP_DIR_STYLE;
        $vmDir = BP_PATH_APP_CLIENT . BP_DIR_VM;

        $output .= getFiles($langDir, '/' . BP_DIR_LANG);
        $output .= getFiles($scriptDir, '/' . BP_DIR_SCRIPT);
        $output .= getFiles($styleDir, '/' . BP_DIR_STYLE);
        $output .= getFiles($vmDir, '/' . BP_DIR_VM);

        // we also cache all public files since they too will be served to the client
        $output .= getFiles(BP_PATH_PUBLIC, '/', true);

        $output .= PHP_EOL . "NETWORK:" . PHP_EOL . "*" . PHP_EOL;
        if(file_put_contents(BP_PATH_APP . BP_DIR_CACHE . BP_EXT_MANIFEST, $output) === false) echo _l('errormessage', true);
    }
    else
        echo _l('errcli', true);
?>