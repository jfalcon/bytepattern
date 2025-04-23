<?php

namespace System;

class Router
{
    const DEFAULT_CLASS = 'Index';
    const EVENT_PREFIX = 'on';

    const IX_PART = 'part';
    const IX_FULL = 'full';

	private $_segments = null;

	public function __construct()
	{
		// get and store the uri segments we'll process
		if($this->getURI() != '/')
		{
			$parts = explode('/', $this->getURI());
			$count = count($parts);

			for($n=0, $i=0; $n < $count; $n++)
			{
                // extra check just to ensure we don't route to something that doesn't exist
                $parts[$n] = trim($parts[$n]);
				if($parts[$n] != null)
				{
					$this->_segments[$i][Router::IX_PART] = $parts[$n];

                    // we also store the full URI loading up to this segment point
					$this->_segments[$i][Router::IX_FULL] =
						($i > 0) ? $this->_segments[$i-1][Router::IX_FULL] . '/' . $this->_segments[$i][Router::IX_PART] :
                        '/' . $this->_segments[$i][Router::IX_PART];

					$i++;
				}
			}
 		}
 		else
 		{
			$this->_segments[0][Router::IX_PART] = '/';
			$this->_segments[0][Router::IX_FULL] = '/';
		}
	}

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// gets the full URI (case-insensitive) for the script
	public function getURI ()
	{
		static $return = null;

		// for security and faster routing, make sure the URI gets cleaned up
		if(isset($_SERVER['REQUEST_URI']) && $return == null)
		{
			// avoid as much translation as possible by parsing REQUEST_URI directly
            $parts = parse_url($_SERVER['REQUEST_URI']);

			$return = '/' . strtolower(trim((stripos($parts['path'], $_SERVER['SCRIPT_NAME']) === 0) ?
				substr($parts['path'], strlen($_SERVER['SCRIPT_NAME'])) : $parts['path']));

			// always have a forward slash at the start and remove multiple and trailing slashes
            $return = preg_replace('~/+~', '/', $return);
			if(($return != '/') && (substr($return, -1) == '/')) $return = substr($return, 0, -1);
		}

		return $return;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // returns the URI parts broken down into individual segments
	public function getSegments ()
	{
        return $this->_segments;
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// routing handler, in all likelihood this will only be called once per request
	public function doRoute ()
	{
		// set the controller defaults
		$count = count($this->_segments);
		$path = BP_PATH_APP_SERVER . BP_DIR_CONTROL;
		$class = Router::DEFAULT_CLASS;
		$method = Router::EVENT_PREFIX . Router::DEFAULT_CLASS;
		$node = -1;
        $base = Base::getInstance();

		// first, see if we can match a directory in the app folder from the path in the URI
		for($i=$count-1; $i>=0; $i--)
		{
			if(is_dir($path . $this->_segments[$i][Router::IX_FULL]))
			{
				$node = $i;
                $path = realpath($path . $this->_segments[$i][Router::IX_FULL]);
				break;
			}
		}

        try
        {
            if(($node + 1) < $count)
    		{
                // we have a path, so see if the next segment is a file with a controller class we can load
    			if(is_file($path . DIRECTORY_SEPARATOR . $this->_segments[$node + 1][Router::IX_PART] . BP_EXT_PHP))
    			{
    				// the class name and the actual file name should match
    				$class = ucfirst($this->_segments[++$node][Router::IX_PART]);
    			}
                // before giving up, check to see if there is a default class file we can load in the current directory
                else if(is_file($path . DIRECTORY_SEPARATOR . strtolower($class) . BP_EXT_PHP))
                {
                    // do nothing since $path already points to the proper directory
                }
                else
                    // default back to the root path and look for a default class in it
                    $path = BP_PATH_APP_SERVER . BP_DIR_CONTROL;
    		}

            // attempt to load the class to handle this request
            include strtolower($path . DIRECTORY_SEPARATOR . $class . BP_EXT_PHP);
            $class = 'Application\\' . $class;
            $controller = null;

            // we do not route unless it extends from the controller abstract class
            if(class_exists($class, false) && is_subclass_of($class, '\System\Controller')) $controller = new $class;

            // see if the next segment is a method we can call
            if(($node + 1) < $count)
            {
                // here we allow the use of temp hyphens in the method name to make longer names more readable
                $temp = Router::EVENT_PREFIX . ucfirst(str_replace('-', '', $this->_segments[$node + 1][Router::IX_PART]));
                if(method_exists($controller, $temp)) { $method = $temp; $node++; }
            }

            // attempt to call it or the default method instead
            if(is_callable(array($controller, $method)))
            {
                // anything else on the URI gets sent as params to the method
                $params = ($node < $count) ? array_slice($this->_segments, $node) : null;
                $controller->$method($params);
            }
            else
                throw new Error(_l('notfoundmessage', true), 0, _l('notfoundtitle', true));
        }
        catch(Error $e)
        {
            Error::onException($e);
        }
        catch(\Exception $e)
        {
            Error::onException($e);
        }
	}
}

?>