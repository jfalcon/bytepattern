<?php

namespace System;

class Error extends \Exception
{
    private static $_title = null;

    // in reality E_PARSE, E_CORE_ERROR, and E_COMPILE_ERROR will never make it to this routine
    public static $types = array
    (
        E_ERROR				=> 'Error',
        E_WARNING			=> 'Warning',
        E_PARSE				=> 'Parsing Error',
        E_NOTICE			=> 'Notice',
        E_CORE_ERROR		=> 'Core Error',
        E_CORE_WARNING		=> 'Core Warning',
        E_COMPILE_ERROR		=> 'Compile Error',
        E_COMPILE_WARNING	=> 'Compile Warning',
        E_USER_ERROR		=> 'User Error',
        E_USER_WARNING		=> 'User Warning',
        E_USER_NOTICE		=> 'User Notice',
        E_STRICT			=> 'Strict Notice',
        E_DEPRECATED		=> 'Deprecated',
        E_RECOVERABLE_ERROR	=> 'Catchable Fatal Error'
    );

    private static $_style = <<< 'EOT'
html *
{ color: #444; font: bold 1.25em sans-serif; text-shadow: 1px 1px 0 #FFF; }

body
{
    background: #E2E2E2; /* Old browsers */
    background: -moz-linear-gradient(left, #E2E2E2 0%, #F4F4F4 50%, #E2E2E2 100%); /* FF3.6+ */
    background: -webkit-gradient(linear, left top, right top, color-stop(0%, #E2E2E2),
    color-stop(50%, #F4F4F4), color-stop(100%, #E2E2E2)); /* Chrome, Safari4+ */
    background: -webkit-linear-gradient(left, #E2E2E2 0%, #F4F4F4 50%, #E2E2E2 100%); /* Chrome10+, Safari5.1+ */
    background: -o-linear-gradient(left, #E2E2E2 0%, #F4F4F4 50%, #E2E2E2 100%); /* Opera 11.10+ */
    background: -ms-linear-gradient(left, #E2E2E2 0%, #F4F4F4 50%, #E2E2E2 100%); /* IE10+ */
    background: linear-gradient(to right, #E2E2E2 0%, #F4F4F4 50%, #E2E2E2 100%); /* W3C */
    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#E2E2E2", endColorstr="#E2E2E2", GradientType=1); /* IE6-9 */
}

main
{
    text-align:center;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    -webkit-transform: translate(-50%, -50%);
    position: absolute;
}

a.tooltip {outline:none; text-decoration:none; cursor:default; text-align:left;}
a.tooltip strong {line-height:15px;}
a.tooltip > span
{
    width:200px;
    padding: 10px 20px;
    margin-top: 20px;
    margin-left: -85px;
    opacity: 0;
    visibility: hidden;
    z-index: 10;
    position: absolute;

    font-size: 12px;
    font-style: normal;

    -webkit-border-radius: 3px;
    -moz-border-radius: 3px;
    -o-border-radius: 3px;
    border-radius: 3px;

    -webkit-box-shadow: 2px 2px 2px #999;
    -moz-box-shadow: 2px 2px 2px #999;
    box-shadow: 2px 2px 2px #999;

    -webkit-transition-property:opacity, margin-top, visibility, margin-left;
    -webkit-transition-duration:0.4s, 0.3s, 0.4s, 0.3s;
    -webkit-transition-timing-function: ease-in-out, ease-in-out, ease-in-out, ease-in-out;

    -moz-transition-property:opacity, margin-top, visibility, margin-left;
    -moz-transition-duration:0.4s, 0.3s, 0.4s, 0.3s;
    -moz-transition-timing-function: ease-in-out, ease-in-out, ease-in-out, ease-in-out;

    -o-transition-property:opacity, margin-top, visibility, margin-left;
    -o-transition-duration:0.4s, 0.3s, 0.4s, 0.3s;
    -o-transition-timing-function: ease-in-out, ease-in-out, ease-in-out, ease-in-out;

    transition-property:opacity, margin-top, visibility, margin-left;
    transition-duration:0.4s, 0.3s, 0.4s, 0.3s;
    transition-timing-function: ease-in-out, ease-in-out, ease-in-out, ease-in-out;
}

a.tooltip:hover > span
{
    opacity: 1;
    text-decoration:none;
    visibility: visible;
    overflow: visible;
    margin-top:50px;
    display: inline;
    margin-left: -60px;
}

a.tooltip > span
{
    color: #444;
    background: #FBF5E6;
    background: -moz-linear-gradient(top, #FBF5E6 0%, #FFFFFF 100%);
    background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#FBF5E6), color-stop(100%,#FFFFFF));
    filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#FBF5E6', endColorstr='#FFFFFF',GradientType=0 );
    border: 1px solid #CFB57C;
}
EOT;

    public function __construct($message = null, $code = 0, $title = null)
    {
        parent::__construct($message, $code);
        if(trim($title) != null) self::$_title = trim($title);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    final public function __toString ()
    {
        return $this->getMessage();
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	// main exception event handler
	public static function onException ($e)
    {
        // do this first to ensure we always get a log entry, even if the below code produces more bugs or errors
        error_log(_l('exceptionphp', true) . ':  ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());

        $output = new Output();
        $cotent = self::__getContent($e);

        $output->setTitle((self::$_title != null) ? self::$_title : _l('errortitle', true));
        $output->setInlineStyle(self::$_style);
        $output->showContent($cotent);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // regular error event handler (turn it into an exception)
    public static function onError ($errnum, $error, $file, $line)
    {
        // do this first to ensure we always get a log entry, even if the below code produces more bugs or errors
        error_log(_l('errorphp', true) . ":  $error in $file on line $line");

        $e = new \ErrorException($error, 0, $errnum, $file, $line);

        $base = Base::getInstance();
        $output = new Output();
        $cotent = self::__getContent($e);

        $output->setTitle((self::$_title != null) ? self::$_title : _l('errortitle', true));
        $output->setInlineStyle(self::$_style);
        $output->showContent($cotent);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////// PRIVATE ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // regular error event handler (turn it into an exception)
    private static function __getContent (\Exception $e)
    {
        $tip = null;
        $message = null;
        $base = Base::getInstance();
        $title = (self::$_title != null) ? self::$_title : _l('errortitle', true);

        if(BP_SYS_CLI)
        {
            $tip = _l('errornum', true) . PHP_EOL;
            $tip .= (int)$e->getCode();

            $tip .= PHP_EOL . PHP_EOL . _l('file', true) . PHP_EOL;
            $tip .= str_replace(BP_PATH_ROOT, null, $e->getFile());

            $tip .= PHP_EOL . PHP_EOL . _l('linenum', true) . PHP_EOL;
            $tip .= $e->getLine();

            $tip .= PHP_EOL . PHP_EOL . _l('callstack', true) . PHP_EOL;
            $tip .= $e->getTraceAsString();

            $message = trim($e->getMessage());
        }
        else if($base->isDebug)
        {
            $tip = '<strong>' . _l('errornum', true) . '</strong><br>';
            $tip .= (int)$e->getCode();

            $tip .= '<br><br><strong>' . _l('file', true) . '</strong><br>';
            $tip .= str_replace(BP_PATH_ROOT, null, $e->getFile());

            $tip .= '<br><br><strong>' . _l('linenum', true) . '</strong><br>';
            $tip .= $e->getLine();

            $tip .= '<br><br><strong>' . _l('callstack', true) . '</strong><br>';
            $tip .= $e->getTraceAsString();

            $message = trim($e->getMessage());
        }
        else
        {
            $str = new String();

            // in production the end user never sees error info, log it instead
            $tip = '<strong>' . _l('errornum', true) . '</strong><br>';
            $tip .= decoct($e->getCode() + BP_ERR_SEED);

            $tip .= '<br><br><strong>' . _l('systime', true) . '</strong><br>';
            $tip .= date('l, jS \of F, Y') . '<br>' . date('g:i A T') . ' (' . date('G:i') . ')';

            $tip .= '<br><br><strong>' . _l('sysadmin', true) . '</strong><br>';
            $tip .= $base->adminName . '<br>' . $str->mask($base->adminEmail);

            $message = _l('errormessage', true);
        }

        return BP_SYS_CLI
            ? $title . PHP_EOL . "$message" . PHP_EOL . PHP_EOL . "$tip"
            : "<main><a class=\"tooltip\">$message<span>$tip</span></main>";
    }
}

?>