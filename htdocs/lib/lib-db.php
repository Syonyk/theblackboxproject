<?php

/*
  DB ABSTRACTION MARK 2
  June 2011,PS

  Jan 2017 updates by Syonyk:
  - Remove (obsolete) SQLite Support
  - Remove (not quite working) PDO support.
  - Convert from MySQL to MySQLI library as MySQL is dropped in PHP 7.0
  - Removed Table Prefix support (it appears unused).

  Usage:

  //connect
  $db = new Database('MYSQL');
  $db->connect('localhost','myuser','mypass','mydb');

  //read
  $query = "SELECT * FROM bla";
  $result= $db->query($query) or codeerror('DB error',__FILE__,__LINE__);
  $nrows= $db->num_rows($result);
  while ($row=$db->fetch_row($result)){
  //etc
  }

  //write
  $query = "UPDATE bla set bla=':bla'";
  $params=array('bla', $bla);
  $db->query($query,$params) or codeerror('DB error',__FILE__,__LINE__);
  $id= $db->insert_id();
  $nrows= $db->num_rows();
 * 
 * TODO rip: usepdo db_type table_prefix

 */

class Database {

    var $DBH;
    var $link;
    var $result;
    var $error;
    var $nquerys;
    var $last_query;
    var $long_querys = array();

    /*
     * DATABASE Constructor
     * Changed by Syonyk to the new PHP7 __construct type for compatibility with
     * PHP7.
     * @param (string) db type
     * @return nil
     */

    function __construct() {
        $this->nquerys = 0;
        $this->table_prefix = false;
        $this->error = '';
        $this->last_query = '';
        $this->num_querys = 0;
    }

    /*
     * CONNECT 
     * @param (strings) $host,$user,$pass,$db
     * @return (resource) on success / (bool) false on fail
     */

    function connect($host, $user, $pass, $db) {

        //check its not already open
        if ($this->link)
            return $this->link;

        //MYSQL
        $link = @mysqli_connect($host, $user, $pass);
        if (!$link) {
            $this->error = @mysqli_error();
            return false;
        }
        if (@mysqli_select_db($link, $db)) {
            $this->link = $link;
            return $link;
        } else {
            $this->error = @mysqli_error($link);
            return false;
        }
    }

    /*
     * QUERY
     * @param (string) $query
     * @return (resource) result
     */

    function query($query, $params = array()) {
        //store query
        $this->last_query = $query;
        $this->nquerys++;
        $start_time = $this->getmicrotime();


        //insert params
        if ($params) {
            $query = $this->insert_params($query, $params);
            if (!$query)
                return false;
            $this->last_query = $query;
        }

        $result = @mysqli_query($this->link, $query);
        if (!$result) {
            $this->error = @mysqli_error($this->link);
            return false;
        }

        //log long querys
        $query_time = $this->getmicrotime() - $start_time;
        if ($query_time > 0.5) {
            $this->long_querys[$query] = max($query_time, isset($this->long_querys[$query]) ? $this->long_querys[$query] : 0);
        }

        //all good
        return $result;
    }

    /*
     * FETCH_ROW
     * @param (resource) $result
     * @return (bool) success
     */

    function fetch_row($result) {
        return @mysqli_fetch_assoc($result);
    }

    /*
     * NUM_ROWS
     * @param (resource) $result
     * @return (bool) success
     */

    function num_rows($result = true) {
        if ($result === true)
            return @mysqli_affected_rows($this->link);
        else
            return @mysqli_num_rows($result);
    }

    /*
     * INSERT_ID
     * @param nil
     * @return (int) id
     */

    function insert_id() {
        return mysqli_insert_id($this->link);
    }

    /*
     * QUOTE
     * @param (string) arbitrary textual
     * @return (string) sql cleaned
     */

    function quote($str) {
        return mysqli_real_escape_string($this->link, $str);
    }

    /*
     * FREE
     * @param (resource) $result
     * @return nil
     */

    function free($result) {
        @mysqli_free_result($result);
    }

    /*
     * INSERT_PARAMS
     * for sites that dont want to use pdo
     * @param (string) sql
     * @param (array) params
     * @return (string) clean sql
     */

    function insert_params($query, $params) {

        $chars = $perrors = array();
        $ch = range('a', 'z');
        $ch[] = '_';
        foreach ($ch as $c) {
            $chars[$c] = 1;
        }

        $i = $m = 0;
        $out = $p = '';
        while ($i < strlen($query)) {
            $l = substr($query, $i, 1);
            if ($m) {
                if (isset($chars[$l]))
                    $p .= $l;
                else {
                    if ($p and isset($params[$p])) {
                        $sql_p = $this->quote($params[$p]);
                        $len_r = strlen($sql_p);
                        $out .= $sql_p . $l;
                    } else
                        $out .= ":$l$p";
                    $m = 0;
                    $p = '';
                }
            } elseif ($l == ':')
                $m = 1;
            else
                $out .= $l;
            $i++;
        }
        if ($perrors) {
            $this->error = implode(",", $perrors);
            return false;
        }

        return $out;
    }

    /*
     * GETMICROTIME
     * @param nil
     * @return (float) seconds
     */

    function getmicrotime() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float) $usec + (float) $sec);
    }

    //class ends
}

?>
