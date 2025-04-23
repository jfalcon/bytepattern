<?php

namespace System;

abstract class Controller
{
    protected $uses = null;   // the model to bind to this instance of the class

    private $_data = [];      // stores view variables
    private $_elements = [];  // stores element file matches to include
    private $_output = null;  // the output object to use

    // a default action is required
    abstract public function onIndex();

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function element($key)
    {
        // keys are always stored as lowercase
        $key = strtolower(trim($key));
        if($key != null) array_push($this->_elements, $key);
    }

    public function get($key)
    {
        // keys are always stored as lowercase
        $key = strtolower(trim($key));
        return ($key != null) ? $this->_data[$key] : null;
    }

    public function set($key, $value)
    {
        // keys are always stored as lowercase
        $key = strtolower(trim($key));
        if($key != null) $this->_data[$key] = $value;
    }

    // the difference between this method and the render method is that this method does not load a view
    // or create variables to be accessed, example uses are when want to output something other than
    // a full HTML page, such as a response to an AJAX service call that outputs JSON instead
    public function display($content)
    {
        if($this->_output === null) $this->_output = new Output;
        $this->_output->showContent($content, false);
    }

    // for debugging purposes, we do not prevent the direct printing / echoing of data to STDOUT,
    // however, it is bad practice and should be avoided, and you should use this render method
    // instead as it will call what needs to be called to get its groove thang on, as such when
    // this method is called it will wipe out any existing output buffers
    public function render()
    {
        if($this->_output === null) $this->_output = new Output;
        $files = $this->__getTemplateFiles();

        $this->_output->showFiles($files, $this->_data);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////// PROTECTED ROUTINES ///////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // used to return the instance of the output object the controller class uses
    final protected function &getOutput()
    {
        if($this->_output === null) $this->_output = new Output;
        return $this->_output;
    }

    final protected function setOutput(Output $output)
    {
        if($output !== null) $this->_output = $output;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////// PRIVATE ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // this will get the full path and file name of the action templte associated with the controller method that invoked this
    // and also any element template files matching the wild card searches specified in teh _elements array
    private function __getTemplateFiles()
    {
        $result = [];
        $caller = debug_backtrace();
        $base = BP_PATH_APP_SERVER . BP_DIR_CONTROL . DIRECTORY_SEPARATOR;
        $router = BP_PATH_SYSTEM . 'router' . BP_EXT_PHP;
        $len = strlen($base);
        $count = count($caller);
        $file = null;

        // here we have to find the name in the call stack that holds the calling controller that invoked this *originally*
        // we then use that name as the basis for loading the corresponding template name in the view directory
        for($x = 0; $x < $count; $x++)
        {
            // note: with the way the data is setup, we cannot break out of this loop early
            if($caller[$x]['file'] == $router)
            {
                // get the filename of the template by finding out which method the router called and remove the "on" prefix
                $file = strtolower(substr($caller[$x]['function'], 2) . BP_EXT_TEMPLATE);
            }
            else if(substr($caller[$x]['file'], 0, $len) == $base)
            {
                // extract the base name, but preserve subdirectories that run deeper than the base directory
                $result[0] = substr($caller[$x]['file'], $len);
                $result[0] = strtolower(substr($result[0], 0, strlen(BP_EXT_PHP) * -1));
                $result[0] = BP_PATH_APP_VIEW . BP_DIR_ACTION . DIRECTORY_SEPARATOR . $result[0] . DIRECTORY_SEPARATOR;
            }
        }

        // we always include the action template as the first index
        $result[0] .= $file;

        if(count($this->_elements) > 0)
        {
            // search the elements directory for patterh matches include in the file list to be returned
            try
            {
                $len = strlen(BP_PATH_APP_VIEW . BP_DIR_ELEMENT) + 1;

                $files = new \RecursiveIteratorIterator
                (
                    new \RecursiveDirectoryIterator(BP_PATH_APP_VIEW . BP_DIR_ELEMENT,
                        \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach($files as $file)
                {
                    if($file->isFile())
                    {
                        if(('.' . $file->getExtension()) == BP_EXT_TEMPLATE)
                        {
                            $match = substr($file->getPath(), $len) . DIRECTORY_SEPARATOR . $file->getBasename(BP_EXT_TEMPLATE);

                            foreach($this->_elements as $element)
                            {
                                if(fnmatch($element, $match, FNM_CASEFOLD))
                                {
                                    array_push($result, $file->getPathname());
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            catch (\Exception $e)
            {
                // do this to eat any error without crashing the system, but do log it
                error_log(_l('exceptionphp', true) . ':  ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            }
        }

        return $result;
    }
}

?>