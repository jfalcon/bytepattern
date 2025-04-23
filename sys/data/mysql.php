<?php

namespace System;

class MySQL extends Database
{
    private $_database = null;
    private $_statement = null;
    private $_server = null;

    private static $_link = null;

    public function __construct($db = null)
    {
        $base = Base::getInstance();

        // this must be set so ping() reconnects us
        ini_set("mysqli.reconnect", 1);
        mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);

        // force a persistant connection (mysqli performs clean-up for us)
        $this->_server = (strtolower(substr($base->dbServer, 0, 2)) != 'p:') ? 'p:' . $base->dbServer : $base->dbServer;
        $this->_database = trim($db);
        $this->__connect();
    }

    public function __destruct()
    {
        if(self::$_link != null) self::$_link->close();
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////// PUBLIC ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // delete data from table, only proccessing ANDs in the where clause
    public function delete($table, array $where = null, $limit = 0)
    {
        $return = false;

        if((self::$_link != null) && (trim($table) != null))
        {
            $table = $this->ticks($table);
            $sql = "DELETE FROM $table ";

            $count = ($where != null) ? count($variables) : 0;
            if($count > 0)
            {
                $first = true;
                $sql .= ' WHERE ';

                // only process AND, anything more complicated should not use this routine
                foreach($where as $field => $value)
                {
                    if(!$first) $sql .= ' AND ';
                    $sql .= $this->ticks($field) . '=' . $this->quotes($value);
                    $first = false;
                }
            }

            if($limit > 0) $sql .= ' LIMIT '. intval($limit);
            $sql .= ';';

            $this->query($sql);
            $return = true;
        }

        return $return;
    }

    // the prepare and execute routines permit for the use of creating and executing prepared statements
    public function execute()
    {
        $return = false;

        if((self::$_link != null) && ($this->_statement !== null) && ($this->_statement !== false))
        {
            try
            {
                if($this->_statement->execute())
                {
                    $meta = $this->_statement->result_metadata();
                    $result = [];
                    $refs = [];
                    $row = [];

                    // first we must find the field names to use for array indexes and
                    // and pass them by reference to get the value from the result set
                    while($field = $meta->fetch_field())
                    {
                        $row[$field->name] = null;
                        $refs[] = &$row[$field->name];
                    }

                    call_user_func_array(array($this->_statement, 'bind_result'), $refs);

                    $i=0;
                    while($this->_statement->fetch())
                    {
                        $result[$i] = array();
                        foreach($row as $k => $v)
                        $result[$i][$k] = $v;
                        $i++;
                    }

                    if(!empty($result)) $return = $result;
                }

                $this->_statement->close();
                $this->_statement = null;
            }
            catch(\mysqli_sql_exception $e)
            {
                $return = false;

                if(stristr($e->getMessage(), 'MySQL server has gone away') !== false)
                {
                    // attempt to try the call again
                    $this->__connect();
                    $this->execute();
                }
                else
                    // we have an unknown problem, log it and return
                    $this->__logIfError();
            }
        }

        return $return;
    }

    // returns true or false depending on if a DB error is present
    public function error()
    {
        $result = false;

        if(self::$_link != null)
        {
            // for connections
            if(self::$_link->connect_errno != 0) $result = true;

            // for everything else
            else if(self::$_link->errno != 0) $result = true;
        }

        return $result;
    }

    // insert data into database table
    // returns the number of affected rows
    public function insert($table, array $variables)
    {
        $return = false;

        if((self::$_link != null)  && (trim($table) != null))
        {
            $table = $this->ticks($table);
            $sql = "INSERT INTO $table ";

            // generate the field list
            $keys = array_keys($variables);
            $keys = array_map(function($x){ return $this->ticks($x); }, $keys);
            $sql .= '(' . implode(',', $keys) . ') VALUES (';

            // generate the value list
            $count = count($variables);
            for($x=0; $x < $count; $x++)
            {
                if($x != 0) $sql .= ',';
                $sql .= $this->quotes($variables[$x]);
            }

            $sql .= ');';
            $this->query($sql);
            $return = true;
        }

        return $return;
    }

    // get last auto-incrementing ID associated with an insertion
    public function lastId()
    {
        return (self::$_link != null) ? intval(self::$_link->insert_id) : 0;
    }

    // the prepare and execute routines permit for the use of creating and executing prepared statements
    // with parameters that are sanitized on the server and executed later
    public function prepare($query, array $params = null)
    {
        $return = false;
        $query = trim($query);

        if((self::$_link != null) && ($this->_statement == null) && ($query != null))
        {
            // here we remove any semicolons since the docs say it should not have
            // them for a single query, which is all mysqli->prepare() supports
            $query = (substr($query, -1, 1) == ';') ? substr($query, 0, -1) : $query;

            try
            {
                if($this->_statement = self::$_link->prepare($query))
                {
                    if(!empty($params))
                    {
                        // automate the bind_param call
                        $types = null;
                        $binIndex = [];
                        $count = count($params);

                        for($x=0; $x < $count; $x++)
                        {
                            if($params[$x] === null)
                            {
                                $types .= 'i'; // assume int
                            }
                            else if(is_int($params[$x]))
                            {
                                $types .= 'i';
                            }
                            else if(is_double($params[$x]))
                            {
                                $types .= 'd';
                            }
                            // if the first character is a control character zero (null), assume it's a binary string
                            else if(substr($params[$x], 0, 1) === "\x00")
                            {
                                $types .= 'b';
                                array_push($binIndex, $x);
                            }
                            else if(is_string($params[$x]))
                            {
                                $types .= 's';
                            }
                        }

                        // PHP 5.3+ expects all params passed to bind_param to be a reference
                        $refs = function($types, $array)
                        {
                            $result[0] = &$types;
                            $count = count($array);

                            for($x=0; $x < $count; $x++) $result[] = &$array[$x];

                            return $result;
                        };

                        // call bind_param this way to allow for N elements via an array
                        call_user_func_array(array($this->_statement, 'bind_param'), $refs($types, $params));

                        $count = count($binIndex);

                        // automate the send_long_data call
                        for($x=0; $x < $count; $x++)
                        {
                            // don't forget to skip the first char which is a control character zero (null)
                            $this->_statement->send_long_data($binIndex[$x], substr($binIndex[$binary[$x]], 1));
                        }
                    }

                    $return = true;
                }
            }
            catch(\mysqli_sql_exception $e)
            {
                $return = false;

                if(stristr($e->getMessage(), 'MySQL server has gone away') !== false)
                {
                    // attempt to try the call again
                    $this->__connect();
                    $this->prepare($query, $params);
                }
                else
                    // we have an unknown problem, log it and return
                    $this->__logIfError();
            }
        }

        return $return;
    }

    // perform queries, and all following functions run through this function
    // all data run through this function should be automatically sanitized
    // returns a 2D associative array of data from the result
    public function queries(array $queries)
    {
        $return = [];

        if((self::$_link != null) && !empty($queries))
        {
            $queries = array_map(function($x)
            {
                // here we remove any semicolons since the below implode will add them
                $x = trim($x);
                return (substr($x, -1, 1) == ';') ? substr($x, 0, -1) : $x;
            },
            $queries);

            $query = 'START TRANSACTION;' . implode(';', $queries) . ';COMMIT;';
            $x = 0;

            try
            {
                if(self::$_link->multi_query($query) !== false)
                {
                    do
                    {
                        $result = self::$_link->store_result();
                        if($result !== false)
                        {
                            while($row = $result->fetch_assoc())
                                array_push($return[$x], $row);

                            $result->free();
                            $x++;
                        }

                        // we have to manually short circuit the loop to exit
                        if(!self::$_link->more_results()) break;

                        if(!self::$_link->next_result())
                        {
                            $this->__logIfError();
                            $return = false;
                            break;
                        }
                    }
                    while(true);

                    // for non-DML queries simply return true instead of an empty array
                    if(($return !== false) && empty($return)) $return = true;
                }
                else
                {
                    $this->__logIfError();
                    $return = false;
                }
            }
            catch(\mysqli_sql_exception $e)
            {
                $return = false;

                if(stristr($e->getMessage(), 'MySQL server has gone away') !== false)
                {
                    // attempt to try the call again
                    $this->__connect();
                    $this->queries($queries);
                }
                else
                    // we have an unknown problem, log it and return
                    $this->__logIfError();
            }
        }

        return $return;
    }

    // perform queries, and all following functions run through this function
    // all data run through this function should be automatically sanitized
    // returns an associative array of data from the result
    public function query($query)
    {
        $query = trim($query);
        $return = [];

        if((self::$_link != null) && ($query != null) && !is_array($query))
        {
            try
            {
                // here we remove any semicolons since the docs say it should not have
                // them for a single query, which is all mysqli->prepare() supports
                $query = (substr($query, -1, 1) == ';') ? substr($query, 0, -1) : $query;

                $result = self::$_link->query($query);

                if($result !== false)
                {
                    while($row = $result->fetch_assoc())
                        array_push($return, $row);

                    $result->free();

                    // for non-DML queries simply return true instead of an empty array
                    if(empty($return)) $return = true;
                }
                else
                {
                    $this->__logIfError();
                    $return = false;
                }
            }
            catch(\mysqli_sql_exception $e)
            {
                $return = false;

                if(stristr($e->getMessage(), 'MySQL server has gone away') !== false)
                {
                    // attempt to try the call again
                    $this->__connect();
                    $this->query($query);
                }
                else
                    // we have an unknown problem, log it and return
                    $this->__logIfError();
            }
        }

        return $return;
    }

    // ensures the string data passed in is always escaped with quotes
    // if there is no data present, it will return '' for string data
    // should handle strings and string arrays passed as a parameter
    public function quotes($input)
    {
        $result = null;

        if(self::$_link != null)
        {
            $func = function($value)
            {
                $value = trim($value);
                if($value == null) $value = "''";
                if(!is_numeric($value)) $value = "'" . self::$_link->real_escape_string($value) . "'";

                return $value;
            };

            if(!is_array($input))
                $result = $func($input);
            else
            {
                $input = array_map($func, $input);
                $result = implode(',', $input);
            }
        }

        return $result;
    }

    // select data from a database table and ANDs for the where clause
    // returns rows of data or false, the same as query()
    public function select($table, array $columns = null, array $where = null, $limit = 0)
    {
        $return = -1;

        if((self::$_link != null)  && (trim($table) != null))
        {
            $table = $this->ticks($table);

            $sql = "SELECT ";
            $sql .= ($columns != null) ? $this->ticks($columns) : '*';
            $sql .= " FROM $table";

            $count = ($where != null) ? count($where) : 0;
            if($count > 0)
            {
                $first = true;
                $sql .= ' WHERE ';

                // only process AND, anything more complicated should not use this routine
                foreach($where as $field => $value)
                {
                    if(!$first) $sql .= ' AND ';
                    $sql .= $this->ticks($field) . '=' . $this->quotes($value);
                    $first = false;
                }
            }

            if($limit > 0) $sql .= ' LIMIT '. intval($limit);
            $sql .= ';';

            $return = $this->query($sql);
        }

        return $return;
    }

    // update data in a database table and ANDs for the where clause, returns
    // true or false depending on if the update was successful or not
    // returns the number of affected rows
    public function update($table, array $variables, array $where = null, $limit = 0)
    {
        $return = -1;

        if((self::$_link != null)  && (trim($table) != null) && ($variables != null))
        {
            $table = $this->ticks($table);
            $sql = "UPDATE $table SET ";

            $first = true;
            foreach($variables as $field => $value)
            {
                if(!$first) $sql .= ',';
                $sql .= $this->ticks($field) . '=' . $this->quotes($value);
                $first = false;
            }

            $count = ($where != null) ? count($where) : 0;
            if($count > 0)
            {
                $first = true;
                $sql .= ' WHERE ';

                // only process AND, anything more complicated should not use this routine
                foreach($where as $field => $value)
                {
                    if(!$first) $sql .= ' AND ';
                    $sql .= $this->ticks($field) . '=' . $this->quotes($value);
                    $first = false;
                }
            }

            if($limit > 0) $sql .= ' LIMIT '. intval($limit);
            $sql .= ';';

            $this->query($sql);
            $return = self::$_link->affected_rows;
        }

        return $return;
    }

    // selects the current database to use
    public function using($db)
    {
        $result = false;
        $db = trim($db);

        if((self::$_link != null) && ($db != null))
        {
            $result = self::$_link->select_db($db);
        }

        return $result;
    }

    // ensures the string data passed in is always escaped with backticks
    // even if there is no data present, it will return ``
    // should handle strings and string arrays passed as a parameter
    public function ticks($input)
    {
        $result = null;

        if(self::$_link != null)
        {
            $func = function($value)
            {
                $value = trim($value);

                if($value == null)
                    $value = "''";
                else
                    // we do not allow even escaped backticks in table and field names
                    $value = '`' . str_replace('`', '', $value) . '`';

                return $value;
            };

            if(!is_array($input))
                $result = $func($input);
            else
            {
                $input = array_map($func, $input);
                $result = implode(',', $input);
            }
        }

        return $result;
    }

    // truncate entire tables, returns the number of affected rows
    // should handle strings and string arrays passed as a parameter
    public function truncate($tables)
    {
        $result = -1;

        if((self::$_link != null) && ($tables != null))
        {
            if(!is_array($tables))
            {
                $sql = 'TRUNCATE TABLE ' . $this->ticks($tables) . ';';
                $this->query($sql);
                $result = self::$_link->affected_rows;
            }
            else
            {
                $tables = array_map(function($value)
                {
                    return 'TRUNCATE TABLE ' . $this->ticks($value) . ';';
                },
                $tables);

                $this->queries($tables);
                $result = self::$_link->affected_rows;
            }
        }

        return $result;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////// PRIVATE ROUTINES ////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // wrap the db connection process to enforce our policies
    private function __connect()
    {
        // only connect once per script execution
        if(self::$_link == null)
        {
            $base = Base::getInstance();
            self::$_link = new \mysqli($this->_server, $base->dbUser, $base->dbPassword, $this->_database);

            if($this->__logIfError())
                self::$_link = null;
            else
                self::$_link->set_charset(BP_SYS_CHARSET);
        }
        // we are potentially connected so just test it and select the current db
        else
        {
            self::$_link->ping();
            if($this->_database != null) self::$_link->select_db($this->_database);
            $this->__logIfError();
        }
    }

    // will write out error info the log file if an error occurs
    // returns true or false depending on if an error was logged
    private function __logIfError()
    {
        $result = false;

        if(self::$_link != null)
        {
            // for connections
            if(self::$_link->connect_errno != 0)
            {
                $error = trim($_link->connect_error);
                if($error != null) $error .= ' ';

                error_log(_l('errordb', true) . ":  $error#" . intval($_link->connect_errno) . " in " . __FILE__ . ' on line ' . __LINE__);
                $result = true;
            }

            // for everything else
            else if(self::$_link->errno != 0)
            {
                $error = trim(self::$_link->error);
                if($error != null) $error .= ' ';

                error_log(_l('errordb', true) . ":  $error#" . intval(self::$_link->errno) . " in " . __FILE__ . ' on line ' . __LINE__);
                $result = true;
            }
        }

        return $result;
    }
}

?>