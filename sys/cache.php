<?php

namespace System;

class Cache
{
    protected $_memCache = false;
    protected $_key = null;

	public function __construct()
	{
        $this->_memCache = extension_loaded('shmop');
        $this->_memTimeoutKey = ftok(__FILE__, 't');
	}

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // does this environment allow for caching data into memory or not
    public function canMemCache()
    {
        return $this->_memCache;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the contents of a matching file for a URL dumped into STDOUT
    public function readFile()
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;
        $match = ltrim(dirname($uri), '/');
        $file = BP_PATH_SYS_CACHE_OUTPUT . md5($uri);

        // if the URI is on the ignore list we do not show a cached file
        if(Base::getInstance()->isCached && isset($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE]))
        {
            // there can be more than one item on the ignore list
            if(is_array($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE]))
            {
                foreach($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE] as $index)
                    if(ltrim(strtolower(trim($index)), '/') == $match) return;
            }
            else
                if(ltrim(strtolower(trim($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE])), '/') == $match) return;
        }

        if(is_file($file))
        {
            @ob_clean();
            readfile($file);
            @ob_end_flush();

            exit;
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the cache manifest file contents dumped into STDOUT
    public function readManifest()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;

        // piggy back on the server cache directory name for the file name to use for this
        if($uri == '/' . BP_DIR_CACHE . BP_EXT_MANIFEST)
        {
            $file = BP_PATH_APP . BP_DIR_CACHE . BP_EXT_MANIFEST;

            if(is_file($file))
            {
                @ob_clean();
                readfile($file);
                @ob_end_flush();

                exit;
            }
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the contents of a matching file for a URL dumped into a buffer
    public function getFile()
    {
        $result = false;

        $uri = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;
        $file = BP_PATH_SYS_CACHE_OUTPUT . md5($uri);

        // anything over 2 MB is simply ignored, since this is not shared memory running the script
        if(is_file($file)) $result = file_get_contents($file, null, null, 0, 2097152);

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // call this when you want the output of a matching URL dumped into a matching file, it's worth noting
    // that disk files have a manual expiration instead of automatic timeouts, unlike memory caching
    public function setFile($content = null)
    {
        $result = false;
        $uri  = isset($_SERVER['REQUEST_URI']) ? strtolower(trim($_SERVER['REQUEST_URI'])) : null;

        // if the URI is on the ignore list we do not store a cached file
        if(Base::getInstance()->isCached && isset($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE]))
        {
            $base = ltrim(dirname($uri), '/');

            // there can be more than one item on the ignore list
            if(is_array($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE]))
            {
                foreach($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE] as $index)
                {
                    $ignore = ltrim(strtolower(trim($index)), '/');
                    $match = substr($base, 0, strlen($ignore));

                    if($ignore == $match) return $result;
                }
            }
            else
            {
                $ignore = ltrim(strtolower(trim($_SESSION[Base::IX_CONFIG][Base::IX_ENV][Base::IX_CACHE][Base::IX_IGNORE])), '/');
                $match = substr($base, 0, strlen($ignore));

                if($ignore == $match) return $result;
            }
        }

        // it's safe to overwrite any existing file with the same name
        return file_put_contents(BP_PATH_SYS_CACHE_OUTPUT . md5($uri), $content) !== false;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // keep in mind, this is shared memory that should be used by more than one process
    public function getMemory($name)
    {
        $result = false;

        if($this->_memCache && !$this->__hasMemTimedout($name))
        {
            $id = shmop_open(crc32($name), 'a', 0, 0);
            if($id)
            {
                $data = unserialize(shmop_read($id, 0, shmop_size($id)));
                if($data)
                {
                    shmop_close();
                    $result = $data;
                }
            }
        }

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // keep in mind, this is shared memory that should be used by more than one process
    // and it should only be called once every BP_CACHE_TIME seconds
    public function setMemory($name, $data)
    {
        $result = false;

        if($this->_memCache)
        {
            $checksum = crc32($name);

            // delete old cache
            $id = @shmop_open($checksum, 'a', 0, 0);
            shmop_delete($id);
            shmop_close($id);

            // get id for name of cache
            $id = @shmop_open($checksum, 'c', 0644, strlen(serialize($data)));

            // return int for data size or boolean false for fail
            if($id)
            {
                $this->__setMemTimeout($name);
                $result = @shmop_write($id, serialize($data), 0);
            }
        }

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////// PRIVATE ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // returns true if the timeout expired or is inavlid, false if timeout is still active
    private function __hasMemTimedout($name)
    {
        $now = new \DateTime(date('Y-m-d H:i:s'));
        $now = $now->format('YmdHis');

        $id = shmop_open($this->_memTimeoutKey, 'a', 0, 0);
        if($id)
        {
            $tl = unserialize(shmop_read($id, 0, shmop_size($id)));
            shmop_close($id);
            $timeout = $tl[$name];

            return (intval($now) > intval($timeout));
        }
        else
            return true;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function __setMemTimeout($name)
    {
        $dt = new \DateTime(date('Y-m-d H:i:s'));
        $dt->add(\DateInterval::createFromDateString(BP_CACHE_TIME . ' seconds'));
        $dt = $dt->format('YmdHis');

        $id = shmop_open($this->_memTimeoutKey, 'a', 0, 0);
        $tl = ($id) ? unserialize(shmop_read($id, 0, shmop_size($id))) : [];

        @shmop_delete($id);
        @shmop_close($id);

        $tl[$name] = $dt;
        $id = shmop_open($this->_memTimeoutKey, 'c', 0644, strlen(serialize($tl)));
        shmop_write($id, serialize($tl), 0);
    }
}

?>