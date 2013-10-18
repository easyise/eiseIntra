<?php 


// #########################################################################
// Database class for mysql functions
// #########################################################################
class sql{
    
    // shorthand functions to minimize coding
    function e($str, $usage="for_ins_upd"){ //escape_string
        return sql::escape_string($str, $usage);
    }
    function q($q){ //do_query
        return $this->do_query($q);
    }
    function n($rs){ //num_rows
        return $this->num_rows($rs);
    }
    function f($rs){ //fetch_array
        return $this->fetch_array($rs);
    }
    function i(){ //insert_id
        return $this->insert_id();
    }
    function a(){ //affected_rows
        return $this->affected_rows();
    }
    function d($variant){ //get_data
        if (is_resource($variant)){
            return $this->get_data($variant);
        } else if (is_string($variant)){
            return $this->get_data($this->do_query($variant));
        } else
            return false;
    }
    
    
        
    function escape_string($str, $usage="for_ins_upd"){
      return "'".($usage!="for_ins_upd" 
		? "%".str_replace("_", "\_", mysql_real_escape_string($str))."%"
		: mysql_real_escape_string($str)
		)."'";
    }
  // #######################################################################
  // Connect to the database
  // #######################################################################
    function connect() {
      
      global $config;  
      global $DBHOST;
      
      
      if ($this->dbh == 0) {
        if ($this->flagPersistent) {
           $this->dbh = @mysql_pconnect($this->$dbhost,$this->dbuser,$this->dbpass);
        }
        else {
           $this->dbh = @mysql_connect($this->dbhost, $this->dbuser, $this->dbpass);
        }
        
      }
      if (!$this->dbh) {
        $this->not_right("Unable to connect to the database!");
        return false;
      }
      
      mysql_set_charset('utf8',$this->dbh); 
      
      if (!mysql_select_db($this->dbname,$this->dbh)){
          return false;
      }
      
      return true;
      
    }

  // #######################################################################
  // Grab the error descriptor
  // #######################################################################
    function graberrordesc() {
      $this->error=mysql_error();
      return $this->error;
    }

  // #######################################################################
  // Grab the error number
  // #######################################################################
    function graberrornum() {
      $this->errornum=mysql_errno();
      return $this->errornum;
    }

  // #######################################################################
  // Do the query
  // #######################################################################
    function do_query($query) {
      
      
      mysql_select_db($this->dbname, $this->dbh);
      if ($this->flagProfiling)
          $timeStart = microtime(true);
          
      $this->sth = mysql_query($query);
      if ($this->flagProfiling) {
         $this->arrQueries[] = $query;
         $this->arrResults[] = ($this->sth 
            ?  Array("affected" => $this->a(), "returned"=>@$this->n($this->sth))
            :  Array("error"=>"Unable to do_query: $query")
            );
         $this->arrMicroseconds[] = number_format(microtime(true)-$timeStart, 6, ".", "");
      }
      
      if (!$this->sth) {
         $this->not_right("Unable to do_query: $query");
      }
      return $this->sth;
    }
    
  // #######################################################################
  // Obtain insert ID
  // #######################################################################
    function insert_id() {
    
      return mysql_insert_id($this->dbh);
    }

  // #######################################################################
  // Fetch the next row in an array
  // #######################################################################
    function fetch_array($sth) {
      
      $this->row = mysql_fetch_assoc($sth);
    
      return $this->row;
    }
    
  // #######################################################################
  // Gets the one field
  // #######################################################################
    function get_data($sth) {
      if ($this->num_rows($sth)>0)
        return mysql_result($sth, 0,0);
      else 
        return null;
    }
    
  // #######################################################################
  // Move internal result pointer
  // #######################################################################
    function data_seek( $result_identifier, $row_number) {
      return mysql_data_seek( $result_identifier, $row_number);
    }

  // #######################################################################
  // Finish the statement handler
  // #######################################################################
    function free_result($sth) {
      return @mysql_free_result($this->sth);
    }
    function finish_sth($sth) {
      return @mysql_free_result($this->sth);
    }

  // #######################################################################
  // Grab the total rows
  // #######################################################################
    function total_rows($sth) {
      return mysql_num_rows($sth);
    }
    function num_rows($sth) {
      return mysql_num_rows($sth);
    }
	function affected_rows(){
		return mysql_affected_rows();
	}
	
	function select_db($dbName){
		if (!mysql_select_db($dbName)){
			return false;
		}
		$this->dbname = $dbName;
		return true;
	}
	
	function get_new_guid(){
		mysql_select_db($this->dbname, $this->dbh);
        $this->sth = mysql_query("SELECT UUID();", $this->dbh); 
		return mysql_result($this->sth, 0,0)	;	
	}
  // #######################################################################
  // Die
  // #######################################################################
    function not_right($error="MySQL error") {
      $this->errordesc = mysql_error();
      $this->errornum  = mysql_errno();  
      if ($this->flagProfiling)
        $this->showProfileInfo();
      throw new Exception("{$this->errornum}: {$this->errordesc},\r\n{$error}\r\n");
    }
    
    function sql ($dbhost, $dbuser, $dbpass, $dbname, $flagPersistent=false) {
       $this->dbhost = $dbhost;
       $this->dbuser = $dbuser;
       $this->dbpass = $dbpass;
       $this->dbname = $dbname;
       $this->flagPersistent = $flagPersistent;
       $this->flagProfiling = false;
       $this->dbh = 0;
       $this->dbtype="MySQL5";
    }
    
    function startProfiling(){
       $this->arrQueries = Array();
       $this->arrResults = Array();
       $this->arrMicroseconds = Array();
       $this->flagProfiling = true;
    }
    
    function showProfileInfo(){
       echo "<pre>";
       echo "Profiling results:";
       for($ii=0;$ii<count($this->arrQueries);$ii++){
          echo "\r\nQuery ##".($ii+1)." (a: {$this->arrResults[$ii]['affected']}, n: {$this->arrResults[$ii]['returned']}), time ".$this->arrMicroseconds[$ii].":\r\n";
          echo $this->arrQueries[$ii];
          echo "\r\n";
       }
       echo "</pre>";
    }

}
?>
