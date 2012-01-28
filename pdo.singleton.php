<?php

/**
* Documents class db
* @package Self
*  
* Requires php5.3.4 for get_called_class
*
*  PDO wrapper.
*
*  SINGLETON.
*  Create object by calling db::getInstance(). This maintains only a single instance of the db class to reduce overhead and allow the passing of config data to happen just once when the object is first created.
*  
* 
*  #Setup requires an array containing db_host, db_name, db_user, db_pass. 
*  Optionally, db_debug can be passed, and will print out html commented debug info if set to true. All other array elements are ignored.
*  Or this can be set as a property after the object has been instantiated - $db->debug = true
* 
*  #Query Method Params: 
*  a string - the database query including ? for parameters, 
*  and an array - the query parameters in the same order as given in the query string.
* 
*   
*  #Usage:
*  $config = array('db_host'=>'database uri','db_name'=>'database name','db_user'=>'database user name','db_pass'=>'database password');
*  $db = db::getInstance();
*  $db->setup($config);
*
*  $result = $db->query_select("SELECT * FROM table WHERE id=? ORDER BY id ASC",array('1'));
*  print_r($result);
*
*  @TODO: return error if ::queryxxx attempted before ::setup.
*/
    class db{
        public $connect_error;
        public $debug=false;
        protected $query_error;
        protected $conn;
        protected $dbh;
        protected $config;


        private static $instance=array();
        
        private function __construct(){}
        
        public static function getInstance(){
            $class_name = get_called_class();
            if(!isset(self::$instance[$class_name]) ){
                self::$instance[$class_name] = new $class_name();
            }
            return self::$instance[$class_name];
        }

        /*
        * setup
        * 
        * @param (config) Array. Sets db connection info and optional debuggin boolean
        * @return (void)
        */
        public function setup(array $config){
            $dbhost = $config['db_host'];
            $dbname = $config['db_name'];
            $dbuser = $config['db_user'];
            $dbpass = $config['db_pass'];
            if(isset($config['db_debug'])){
                $this->debug = $config['db_debug'];
            }

            try {
                $this->dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
                $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
                }
            catch (PDOException $e) {
                if($this->debug === true){
                    echo "\r\n<!-- PDO CONNECTION ERROR: ".$e->getMessage()."-->\r\n";
                }
                $this->connect_error = "Error!: " . $e->getMessage() . "<br/>";
                $this->dbh = null;
                return;
            }
        }


        /*
        * query
        *
        * Generic Query.
        *
        * @param (query) String. Database Query.
        * @param (params) Array. Database Params.
        * @return (mixed)
        */
        public function query($query,$params=array()){
            try{
                $stmt = $this->dbh->prepare($query);
                $stmt->execute($params);
                if($this->debug === true){
                    $this->debugger($params,$query);
                }
                return $stmt;
            }
            catch (PDOException $e){
                if($this->debug === true){
                    $this->debugger($params,$query,array('error'=>$e->getMessage()));
                }
                return false;
            }
        }

        private function debugger($params,$query,$result=array('error'=>'No results passed to debugger')){
            if($this->debug === true){
                $keys = array();
                if(!empty($params)){
                    foreach ($params as $key => $value) {
                        if (is_string($key)) {
                            $keys[] = '/:'.$key.'/';
                        } else {
                            $keys[] = '/[?]/';
                        }
                        $safeParams[] = "'".$value."'";
                    }
                }else $safeParams = '';

                echo "\r\n<!-- SQL QUERY: \r\n". preg_replace($keys, $safeParams, $query, 1, $count) . "\r\n";
                print_r($result);
                echo "-->\r\n";
            }
        }

        /*
        * query_select
        *
        * Retrieves a multidimensional associative array of results.
        *
        * @param (query) String. Database Query.
        * @param (params) Array. Database Params.
        * @return (rows) Array. [0]=>array(column=>record)
        */
        public function query_select($query,$params=array()){
            try{
                $sth = $this->dbh->prepare($query);
                $sth->execute($params);
                $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
                
                if(isset($rows)){
                    if($this->debug === true){
                        $this->debugger($params,$query, $rows);
                    }
                    return $rows;
                }
                else{
                    if($this->debug === true){
                        $this->debugger($params,$query);
                    }
                    return false;
                }
            }
            catch (PDOException $e){
                if($this->debug === true){
                    $this->debugger($params,$query, array('error'=>$e->getMessage()));
                }
                return false;
            }
            $this->dbh = null;
            
        }

        /*
        * query_select_single
        *
        * Retrieves an associative array of the first result.
        *
        * @param (query) String. Database Query.
        * @param (params) Array. Database Params.
        * @return (rows) Array. array(column=>record)
        */        
        public function query_select_single($query,$params=array()){
            try{
                $sth = $this->dbh->prepare($query);
                $sth->execute($params);
                $row = $sth->fetch(PDO::FETCH_ASSOC);
                if(isset($row) && is_array($row)){
                    if($this->debug === true){
                        $this->debugger($params,$query,$row);
                    }
                    return $row;
                }
                else{
                    if($this->debug === true){
                        $this->debugger($params,$query);
                    }
                    return false;
                }
            }
            catch (PDOException $e){
                if($this->debug === true){
                    $this->debugger($params,$query,array('error'=>$e->getMessage()));
                }
                return false;
            }
            $this->dbh = null;
            
        }

        /*
        * query_insert
        *
        * Insert a new record
        *
        * @param (query) String. Database Query.
        * @param (params) Array. Database Params.
        * @return (id) int. ID of inserted record
        */
        public function query_insert($query,$params){
            $sth = $this->dbh->prepare($query);
            if($sth->execute($params)){
                if($this->debug === true){
                    $this->debugger($params,$query,$this->dbh->lastInsertId());
                }
                return $this->dbh->lastInsertId();
            }else {
                if($this->debug === true){
                    $this->debugger($params,$query,array('error'=>'Insert failed'));
                }
                return false;
            }
        }

                /*
        * query_update
        *
        * Update a record.
        *
        * @param (query) String. Database Query.
        * @param (params) Array. Database Params.
        * @return (true/false) Boolean. True on success.
        */
        public function query_update($query,$params){
            try{
                $sth = $this->dbh->prepare($query);
                $sth->execute($params);
            }catch(PDOException $e){
                if($this->debug === true){
                    $this->debugger($params,$query,array('error'=>$e->getMessage()));
                }
                return false;
            }
            
            if($sth->rowCount() > 0){
                if($this->debug === true){
                    $this->debugger($params,$query,array('Update successful'=>'true'));
                }
                return true;
            }else {
                if($this->debug === true){
                    $this->debugger($params,$query,array('Update succesful'=>'false'));
                }
                return false;
            }
        }
    }
?>