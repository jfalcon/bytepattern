<?php

namespace System;

interface IDatabase
{
    // delete data from table
    public function delete($table, array $where = null, $limit = 0);

    // the prepare and execute routines permit for the use of creating and executing prepared statements
    public function execute();

    // returns true or false depending on if a DB error is present
    public function error();

    // insert data into database table
    public function insert($table, array $variables);

    // get last auto-incrementing ID associated with an insertion
    public function lastId();

    // the prepare and execute routines permit for the use of creating and executing prepared statements
    public function prepare($query, array $params = null);

    // perform queries, and all following functions run through this function
    // all data run through this function should be automatically sanitized
    public function query($query);

    // ensures the string data passed in is always escaped with quotes
    // should handle strings and string arrays passed as a parameter
    public function quotes($input);

    // select data from a database table
    public function select($table, array $columns = null, array $where = null, $limit = 0);

    // update data in a database table
    public function update($table, array $variables, array $where = null, $limit = 0);

    // selects the current database to use
    public function using($db);

    // ensures the string data passed in is always escaped with backticks
    // should handle strings and string arrays passed as a parameter
    public function ticks($input);

    // truncate entire tables
    // should handle strings and string arrays passed as a parameter
    public function truncate($tables);
}

?>