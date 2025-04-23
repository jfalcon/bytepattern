<?php

namespace System;

class String
{
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // will expand and process constants delmited by {% and %} in the input string
    public function expand($input)
    {
        $input = trim($input);
        if($input != null)
        {
            $lambda = function($matches)
            {
                // remove the delimiters first
                $match = trim(substr($matches[0], 2, -2));

                // only return valid constants (includes class constants), if no match return original string
                return (defined($match)) ? constant($match) : $matches[0];
            };

            return preg_replace_callback('|\{%*[^%\}]*%\}|', $lambda, $input);
        }
        else
            return null;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // encode and decode html entities in string data
    public function html(&$data, $decode = true)
    {
        if(!empty($data))
        {
            $func = null;

            if($decode)
                $func = function($value) { return html_entity_decode(trim($value), ENT_QUOTES|ENT_HTML5, BP_SYS_CHARSET); };
            else
                $func = function($value) { return htmlentities(trim($value), ENT_QUOTES|ENT_HTML5, BP_SYS_CHARSET); };

            if(!is_array($data))
                $data = $func($data);
            else
                $data = array_map($func, $data);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // encodes and decodes json data to and from an array
    public function json($data)
    {
        if(!empty($data))
        {
            if(is_array($data))
            {
                if(Base::getInstance()->isDebug)
                    return trim(json_encode($data, JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT));
                else
                    return trim(json_encode($data, JSON_NUMERIC_CHECK));
            }
            else
                return json_decode(trim($data), true);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // masks a string by converting it to hex-based html entities
    public function mask($input)
    {
        $converted  = null;
        $len = strlen($input);

        for($i = 0; $i < $len; $i++) $converted .= '&#x' . strtoupper(dechex(ord(substr($input, $i, 1)))) . ";";
        return $converted;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // url encode and decode string data
    public function url(&$data, $decode = true)
    {
        if(!empty($data))
        {
            $func = null;

            if($decode)
                $func = function($value) { return urldecode(trim($value)); };
            else
                $func = function($value) { return urlencode(trim($value)); };

            if(!is_array($data))
                $data = $func($data);
            else
                $data = array_map($func, $data);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // escapes both single and double quotes
    public function unquote($data)
    {
        $result = null;

        if(!empty($data))
        {
            $func = function($value) { return str_replace(array("'", '"'), array("\\'", '\\"'), $value); };

            if(!is_array($data))
                $result = $func($data);
            else
                $result = array_map($func, $data);
        }

        return $result;
    }
}

?>