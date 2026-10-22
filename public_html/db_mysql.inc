<?php
/*
 * Session Management for PHP3
 *
 * Copyright (c) 1998,1999 SH Online Dienst GmbH
 *                    Boris Erdmann, Kristian Koehntopp
 *
 * $Id: db_mysql.inc,v 1.7 2007-10-19 11:32:50 sash Exp $
 *
 * Ported from ext/mysql to mysqli (ext/mysql is gone since PHP 7). Behaviour kept from the
 * mysql_* version:
 *  - one link per host/user/password/database, shared by every DB_Sql using them (mysql_connect
 *    without new_link reused the link, so $db2->last_id() after an insert through $db worked);
 *  - latin1 client charset (what the mysql_* connection negotiated; the data is stored that way);
 *  - errors are reported through halt(), never as exceptions (mysqli throws by default on 8.1+);
 *  - "host:port" hosts (VIART_COM_DATABASE_HOST).
 */

mysqli_report(MYSQLI_REPORT_OFF);

class DB_Sql {

  /* public: connection parameters */
  var $Host     = "";
  var $Database = "";
  var $User     = "";
  var $Password = "";

  /* public: configuration parameters */
  var $Auto_Free     = 0;     ## Set to 1 for automatic mysqli_free_result()
  var $Debug         = 0;     ## Set to 1 for debugging messages.
  var $Halt_On_Error = "yes"; ## "yes" (halt with message), "no" (ignore errors quietly), "report" (ignore errror, but spit a warning)
  var $Seq_Table     = "db_sequence";

  /* public: result array and current row number */
  var $Record   = array();
  var $Row;

  /* public: current error number and error text */
  var $Errno    = 0;
  var $Error    = "";

  /* public: this is an api revision, not a CVS revision. */
  var $type     = "mysql";
  var $revision = "1.2";

  /* private: link and query handles */
  var $Link_ID  = 0;
  var $Query_ID = 0;

  var $SqlQuery = "";

  /* private: open links, "host|user|password|database" => mysqli */
  static $links = array();
  /* private: the link opened last (mysql_insert_id() without a link used that one) */
  static $last_link = null;


  /* public: constructor */
  function __construct($query = "") {
      $this->query($query);
  }

  function DB_Sql($query = "") {
      $this->__construct($query);
  }

  /* public: some trivial reporting */
  function link_id() {
    return $this->Link_ID;
  }

  function query_id() {
    return $this->Query_ID;
  }

  function last_id() {
    $link = $this->Link_ID ? $this->Link_ID : self::$last_link;
    return $link ? mysqli_insert_id($link) : 0;
  }

  /* public: connection management */
  function connect($Database = "", $Host = "", $User = "", $Password = "") {
    /* Handle defaults */
    if ("" == $Database)
      $Database = $this->Database;
    if ("" == $Host)
      $Host     = $this->Host;
    if ("" == $User)
      $User     = $this->User;
    if ("" == $Password)
      $Password = $this->Password;

    /* establish connection, select database */
    if ( !$this->Link_ID ) {

      $key = $Host . "|" . $User . "|" . $Password . "|" . $Database;
      if (isset(self::$links[$key])) {
        $this->Link_ID = self::$links[$key];
        return $this->Link_ID;
      }

      $port = null;
      $hostname = $Host;
      if (preg_match('/^(.+):(\d+)$/', $Host, $m)) {
        $hostname = $m[1];
        $port = (int) $m[2];
      }
      $link = @mysqli_connect($hostname, $User, $Password, "", $port);
      if (!$link) {
        $this->Error = mysqli_connect_error();
        $this->Errno = mysqli_connect_errno();
        $this->halt("pconnect($Host, $User, \$Password) failed.");
        return 0;
      }
      $this->Link_ID = $link;
      mysqli_set_charset($link, "latin1");
      # smoke tests run pages with this set (FPM clears the environment, so web requests never do)
      if (getenv("MONITOR_DB_READ_ONLY"))
        mysqli_query($link, "SET SESSION TRANSACTION READ ONLY");

      if (!@mysqli_select_db($link, $Database)) {
        $this->halt("cannot use database ".$this->Database);
        $this->Link_ID = 0;
        return 0;
      }
      self::$links[$key] = $link;
      self::$last_link = $link;
    }

    return $this->Link_ID;
  }

  /* public: discard the query result */
  function free()
  {
	if ($this->Query_ID instanceof mysqli_result)
	{
      mysqli_free_result($this->Query_ID);
	}
	$this->Query_ID = 0;
  }

  /* public: perform a query */
  function query($Query_String) {
    /* No empty queries, please, since PHP4 chokes on them. */
    if ($Query_String == "")
      /* The empty query string is passed on from the constructor,
       * when calling the class without a query, e.g. in situations
       * like these: '$db = new DB_Sql_Subclass;'
       */
      return 0;

    if (!$this->connect()) {
      return 0; /* we already complained in connect() about that. */
    };

    # New query, discard previous result.
    if ($this->Query_ID) {
      $this->free();
    }

    if ($this->Debug)
      printf("Debug: query = %s<br>\n", $Query_String);

    # smoke-test mode: writes are skipped (reported as done) instead of hitting the read-only transaction
    if (getenv("MONITOR_DB_READ_ONLY") && !preg_match('/^[\s(]*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $Query_String)) {
      $this->SqlQuery = $Query_String;
      $this->Row = 0; $this->Errno = 0; $this->Error = "";
      return $this->Query_ID = true;
    }
    $this->Query_ID = @mysqli_query($this->Link_ID, $Query_String);
	$this->SqlQuery = $Query_String;
    $this->Row   = 0;
    $this->Errno = mysqli_errno($this->Link_ID);
    $this->Error = mysqli_error($this->Link_ID);
    if (!$this->Query_ID) {
      $this->halt("Invalid SQL: ".$Query_String);
    }

    # Will return nada if it fails. That's fine.
    return $this->Query_ID;
  }

  /* public: walk result set */
  function next_record() {
    if (!$this->Query_ID) {
      $this->halt("next_record called with no query pending.");
      return 0;
    }

    # a statement without a result set (INSERT/UPDATE...): mysql_fetch_array() returned false
    $row = ($this->Query_ID instanceof mysqli_result) ? mysqli_fetch_array($this->Query_ID) : null;
    $this->Record = ($row === null) ? false : $row;
    $this->Row   += 1;
    $this->Errno  = mysqli_errno($this->Link_ID);
    $this->Error  = mysqli_error($this->Link_ID);

    $stat = is_array($this->Record);
    if (!$stat && $this->Auto_Free) {
      $this->free();
    }
    return $stat;
  }

  /* public: position in result set */
  function seek($pos = 0) {
    $status = ($this->Query_ID instanceof mysqli_result) ? @mysqli_data_seek($this->Query_ID, $pos) : false;
    if ($status)
      $this->Row = $pos;
    else {
      $this->halt("seek($pos) failed: result has ".$this->num_rows()." rows");

      /* half assed attempt to save the day,
       * but do not consider this documented or even
       * desireable behaviour.
       */
      if ($this->Query_ID instanceof mysqli_result)
        @mysqli_data_seek($this->Query_ID, $this->num_rows());
      $this->Row = $this->num_rows();
      return 0;
    }

    return 1;
  }

  /* public: table locking */
  function lock($table, $mode="write") {
    $this->connect();

    $query="lock tables ";
    if (is_array($table)) {
      foreach ($table as $key => $value) {
        if ($key=="read" && $key!=0) {
          $query.="$value read, ";
        } else {
          $query.="$value $mode, ";
        }
      }
      $query=substr($query,0,-2);
    } else {
      $query.="$table $mode";
    }
    $res = @mysqli_query($this->Link_ID, $query);
    if (!$res) {
      $this->halt("lock($table, $mode) failed.");
      return 0;
    }
    return $res;
  }

  function unlock() {
    $this->connect();

    $res = @mysqli_query($this->Link_ID, "unlock tables");
    if (!$res) {
      $this->halt("unlock() failed.");
      return 0;
    }
    return $res;
  }


  /* public: evaluate the result (size, width) */
  function affected_rows() {
    return $this->Link_ID ? mysqli_affected_rows($this->Link_ID) : -1;
  }

  function num_rows() {
    return ($this->Query_ID instanceof mysqli_result) ? mysqli_num_rows($this->Query_ID) : 0;
  }

  function num_fields() {
    return ($this->Query_ID instanceof mysqli_result) ? mysqli_num_fields($this->Query_ID) : 0;
  }

  /* public: shorthand notation */
  function nf() {
    return $this->num_rows();
  }

  function np() {
    print $this->num_rows();
  }

  function f($Name) {
  	if (isset($this->Record[$Name]))
    	return $this->Record[$Name];
    else
    	return "";
  }

  function p($Name) {
    print $this->Record[$Name];
  }

  /* public: sequence numbers */
  function nextid($seq_name) {
    $this->connect();

    if ($this->lock($this->Seq_Table)) {
      /* get sequence number (locked) and increment */
      $q  = sprintf("select nextid from %s where seq_name = '%s'",
                $this->Seq_Table,
                $seq_name);
      $id  = @mysqli_query($this->Link_ID, $q);
      $res = ($id instanceof mysqli_result) ? mysqli_fetch_array($id) : null;

      /* No current value, make one */
      if (!is_array($res)) {
        $currentid = 0;
        $q = sprintf("insert into %s values('%s', %s)",
                 $this->Seq_Table,
                 $seq_name,
                 $currentid);
        $id = @mysqli_query($this->Link_ID, $q);
      } else {
        $currentid = $res["nextid"];
      }
      $nextid = $currentid + 1;
      $q = sprintf("update %s set nextid = '%s' where seq_name = '%s'",
               $this->Seq_Table,
               $nextid,
               $seq_name);
      $id = @mysqli_query($this->Link_ID, $q);
      $this->unlock();
    } else {
      $this->halt("cannot lock ".$this->Seq_Table." - has it been created?");
      return 0;
    }
    return $nextid;
  }

  /* public: return table metadata */
  function metadata($table='',$full=false) {
    $count = 0;
    $id    = 0;
    $res   = array();

    /*
     * Due to compatibility problems with Table we changed the behavior
     * of metadata();
     * depending on $full, metadata returns the following values:
     *
     * - full is false (default):
     * $result[]:
     *   [0]["table"]  table name
     *   [0]["name"]   field name
     *   [0]["type"]   field type
     *   [0]["len"]    field length
     *   [0]["flags"]  field flags
     *
     * - full is true
     * $result[]:
     *   ["num_fields"] number of metadata records
     *   [0]["table"]  table name
     *   [0]["name"]   field name
     *   [0]["type"]   field type
     *   [0]["len"]    field length
     *   [0]["flags"]  field flags
     *   ["meta"][field name]  index of field named "field name"
     *   The last one is used, if you have a field name, but no index.
     *   Test:  if (isset($result['meta']['myfield'])) { ...
     *
     * mysqli reports type/flags as numbers (MYSQLI_TYPE_* / MYSQLI_*_FLAG), not mysql_field_type()'s names.
     */

    // if no $table specified, assume that we are working with a query
    // result
    if ($table) {
      $this->connect();
      $id = @mysqli_query($this->Link_ID, "SELECT * FROM `" . str_replace("`", "``", $table) . "` LIMIT 0");
      if (!$id)
        $this->halt("Metadata query failed.");
    } else {
      $id = $this->Query_ID;
      if (!$id)
        $this->halt("No query specified.");
    }

    $fields = ($id instanceof mysqli_result) ? mysqli_fetch_fields($id) : array();
    $count = count($fields);

    if ($full)
      $res["num_fields"]= $count;

    for ($i=0; $i<$count; $i++) {
      $res[$i]["table"] = $fields[$i]->table;
      $res[$i]["name"]  = $fields[$i]->name;
      $res[$i]["type"]  = $fields[$i]->type;
      $res[$i]["len"]   = $fields[$i]->length;
      $res[$i]["flags"] = $fields[$i]->flags;
      if ($full)
        $res["meta"][$res[$i]["name"]] = $i;
    }

    // free the result only if we were called on a table
    if ($table && ($id instanceof mysqli_result)) mysqli_free_result($id);
    return $res;
  }

  /* public: escape a value for a quoted SQL literal on this connection (was mysql_escape_string) */
  function escape($value) {
    if (!$this->connect()) return addslashes((string) $value);
    return mysqli_real_escape_string($this->Link_ID, (string) $value);
  }

  /* private: error handling */
  function halt($msg) {
    if ($this->Link_ID) {
      $this->Error = mysqli_error($this->Link_ID);
      $this->Errno = mysqli_errno($this->Link_ID);
    }
    if ($this->Halt_On_Error == "no")
      return;

    $this->haltmsg($msg);

    if ($this->Halt_On_Error != "report")
      die("Session halted.");
  }

  function haltmsg($msg) {
	if (isset($argc))
	{
		echo "Database error: " . $msg . "\n";
		echo "MySQL Error: [" . $this->Errno . "] " . $this->Error . "\n";
	}
	else
	{
		printf("</td></tr></table><b>Database error:</b> %s<br>\n", $msg);
		printf("<b>MySQL Error</b>: %s (%s)<br>\n", $this->Errno, $this->Error);
	}
  }

  function table_names() {
    $this->query("SHOW TABLES");
    $i=0;
    $return = array();
    while ($this->Query_ID instanceof mysqli_result && ($info=mysqli_fetch_row($this->Query_ID)))
     {
      $return[$i]["table_name"]= $info[0];
      $return[$i]["tablespace_name"]=$this->Database;
      $return[$i]["database"]=$this->Database;
      $i++;
     }
   return $return;
  }
}

?>
