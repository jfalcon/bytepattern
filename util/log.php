<?php
    require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'pub' . DIRECTORY_SEPARATOR . 'index.php');

    // this script will deal with system log file, including parsing it for better displpay and purging as well

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // this will empty a file out without actually deleting it
    function wipeFile($file)
    {
        $file = trim($file);
        $result = false;

        // don't use file_exists() since we don't want a directory check as well
        if(is_file($file))
        {
            $f = @fopen($file, 'r+');
            if($f !== false)
            {
                ftruncate($f, 0);
                fclose($f);

                $result = true;
            }
        }

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // do not process anything unless this script was ran over CLI
    if(BP_SYS_CLI)
    {
        $phpLog = BP_PATH_LOG . pathinfo(__FILE__, PATHINFO_EXTENSION);

        $options = getopt('c');

        // if the user wished to clean / purge the log files
        if(($options !== false) && isset($options['c']))
        {
            wipeFile($phpLog);
        }
    }
    else
        echo _l('errcli', true);
?>