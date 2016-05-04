<?php

namespace Rsi\Fred;

/**
 *  Database access layer (PDO based).
 */
class Db extends Component{

  const EVENT_OPEN = 'db:open';

  public $queryCount = 0;
  public $queryTime = 0;

  public $defTables = null;

  protected $_connection = [];
  protected $_attributes = [];
  protected $_pdo = null;

  protected $_logTimes = [
    Log::CRITICAL => 10.0,
    Log::ERROR => 5.0,
    Log::WARNING => 2.5
  ];
  protected $_startTime = null;

  protected function getPdo(){
    if(!$this->_pdo){
      $this->_pdo = new \PDO(
        \Rsi\Record::get($this->_connection,'dsn'),
        \Rsi\Record::get($this->_connection,'username'),
        \Rsi\Record::get($this->_connection,'password'),
        \Rsi\Record::get($this->_connection,'options',[]) + [
          \PDO::MYSQL_ATTR_FOUND_ROWS => true
        ]
      );
      foreach($this->_attributes + [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
      ] as $attribute => $value)
        $this->_pdo->setAttribute($attribute,$value);
      $this->_fred->event->trigger(self::EVENT_OPEN,$this,$this->_pdo);
    }
    return $this->_pdo;
  }
  /**
   *  Convert a Unix timestamp to database date format.
   *  @param int $time  Timestamp (empty = now).
   *  @return string  Date as a string.
   */
  public function date($time = null){
    return date('Y-m-d',$time ?: time());
  }
  /**
   *  Convert a Unix timestamp to database date+time format.
   *  @param int $time  Timestamp (empty = now).
   *  @return string  Date+time as a string.
   */
  public function dateTime($time = null){
    return date('Y-m-d H:i:s',$time ?: time());
  }
  /**
   *  Begin a transaction.
   */
  public function begin(){
    $this->pdo->beginTransaction();
  }
  /**
   *  Roll a transaction back.
   */
  public function rollBack(){
    $this->pdo->rollBack();
  }
  /**
   *  Commit a transaction.
   */
  public function commit(){
    $this->pdo->commit();
  }
  /**
   *  Wrap a callback function in a transaction.
   *  @param callable $callback  Callback function (first and only parameter is this instance).
   *  @param bool $throw  If true an exception is re-thrown after the rollback.
   *  @return bool  True on success, false on error (and $thrown set to false).
   */
  public function transaction($callback,$throw = true){
    $this->begin();
    try{
      call_user_func($callback,$this);
      $this->commit();
    }
    catch(\Exception $e){
      $this->rollBack();
      if($throw) throw $e;
      return false;
    }
    return true;
  }

  public function lastInsertId(){
    return $this->pdo->lastInsertId();
  }

  protected function startTimer(){
    $this->_startTime = microtime(true);
  }

  protected function checkTimer($sql,$args){
    $this->queryCount++;
    $this->queryTime += ($time = microtime(true) - $this->_startTime);
    if($log = $this->_fred->may('log')) foreach($this->_logTimes as $prio => $edge) if($time >= $edge){
      $log->add($prio,'Slow query',['sql' => $sql,'args' => $args,'time' => $time]);
      break;
    }
  }

  public function prepareArgs(&$sql,&$args){
    if(!$args) $args = [];
    else foreach($args as $key => $value)
      if(!preg_match($pattern = "/:$key\\b/",$sql)) unset($args[$key]); //komt niet voor in sql
      elseif(is_array($value)){
        unset($args[$key]);
        $keys = [];
        foreach($value as $sub) $args[$keys[] = $key . count($keys)] = $sub;
        $sql = preg_replace($pattern,':' . implode(',:',$keys),$sql);
      }
  }
  /**
   *  Create a PDO statement.
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   *  @return PDOStatement (false on failure).
   */
  public function statement($sql,$args = null){
    if($statement = $this->pdo->prepare($sql)){
      if($args) foreach($args as $key => $value){
        $statement->bindParam($key,$value);
        unset($value);
      }
      $statement->execute();
    }
    return $statement;
  }
  /**
   *  Execute an SQL statement
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   *  @return int  The number of affected rows.
   */
  public function execute($sql,$args = null){
    if($log = $this->_fred->has('log'))
      $log->debug(__CLASS__ . "::execute('$sql',args)",__FILE__,__LINE__,['args' => $args]);
    $result = null;
    $this->prepareArgs($sql,$args);
    $this->startTimer();
    if(!$args) $result = $this->pdo->exec($sql);
    elseif($statement = $this->statement($sql,$args)) $result = $statement->rowCount();
    $this->checkTimer($sql,$args);
    return $result;
  }
  /**
   *  Executes an SQL statement.
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   *  @return PDOStatement (false on failure).
   */
  public function query($sql,$args = null){
    if($log = $this->_fred->has('log'))
      $log->debug(__CLASS__ . "::query('$sql',args)",__FILE__,__LINE__,['args' => $args]);
    $this->prepareArgs($sql,$args);
    $this->startTimer();
    $result = $args ? $this->statement($sql,$args) : $this->pdo->query($sql);
    $this->checkTimer($sql,$args);
    return $result;
  }
  /**
   *  Fetch a row from an SQL statement.
   *  @param PDOStatement $statement
   *  @return array
   */
  public function fetch($statement){
    $row = $statement->fetch();
    return $this->defTables ? $this->component('def')->convertRecord($row,$this->defTables) : $row;
  }
  /**
   *  Return al rows from an SQL statement.
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   *  @return array  All rows (false on failure).
   */
  public function all($sql,$args = null){
    if($statement = $this->query($sql,$args)){
      if(!$this->defTables) return $statement->fetchAll();
      $rows = [];
      while($row = $this->fetch($statement)) $rows[] = $row;
      return $rows;
    }
    return false;
  }
  /**
   *  Return a single row from an SQL statement.
   *  Returns a single value if the resulting row has only one column.
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   *  @param bool $auto  Set to false to always return a row.
   *  @return mixed  Row (multi column) or value (single column).
   */
  public function single($sql,$args = null,$auto = true){
    if(($statement = $this->query($sql,$args)) && ($row = $this->fetch($statement)))
      return $auto && (count($row) == 1) ? array_pop($row) : $row;
    return false;
  }
  /**
   *  Returns an array from an SQL statement.
   *  If the query returns only one column, the result is an array of those values. With two columns, the first one becomes the
   *  key, and the second one the value of an assoc.array. With three or more columns the first column will be the key, and the
   *  others the value.
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   *  @return array
   */
  public function record($sql,$args = null){
    $result = [];
    if($statement = $this->query($sql,$args)) while($row = $this->fetch($statement)){
      $key = array_shift($row);
      switch(count($row)){
        case 0: $result[] = $key; break;
        case 1: $result[$key] = array_pop($row); break;
        default: $result[$key] = $row;
      }
    }
    return $result;
  }
  /**
   *  Run a callback function for every row in an SQL resultset.
   *  @param callable $callback  Callback function (first and only parameter is the row). If the function returns explicitly
   *    false the execution of further rows is halted.
   *  @param string $sql  SQL statement to execute.
   *  @param array $args  Variables to bind to the statement.
   */
  public function each($callback,$sql,$args = null){
    if($statement = $this->query($sql,$args)) while($row = $this->fetch($statement))
      if(call_user_func($callback,$row) === false) break;
  }
  /**
   *  Prepare a where statement.
   *  @param string|array $where  If this a string, it is used directly as the where clause. If it is an assoc.array, it is
   *    translated to a where statement. Key => value pairs are translated as follow:
   *    - 'key' => 'value' : "key = 'value'" (or "key is null" when the value is null, or "key in (...)" when the value is an
   *      array)
   *    - 'key<>' => 'value' : "key <> 'value'" (or "key is not null" when the value is null, or "key in (...)" when the value
   *      is an array)
   *    - 'key~' => 'value' : "key like 'value'"
   *  @param string|array $args  If the $where is string, and this is also, this value is added to the where. Otherwise the
   *    arguments resulting from the translation will be added to this array.
   */
  public function prepareWhere(&$where,&$args = null){
    if($extra = $args && is_string($args) ? "\n" . $args : null) $args = null;
    if(is_array($where)){
      if(!is_array($args)) $args = [];
      $def = $this->defTables ? $this->component('def') : null;
      foreach($where as $column => &$value){
        if($raw = substr($column,0,1) == '!') $column = substr($column,1);
        $negation = '';
        if($operator = preg_match('/\\W+$/',$column,$match) ? $match[0] : null){
          $column = substr($column,0,-strlen($operator));
          switch($operator){
            case '<>': $negation = ' not'; break;
            case '~': $operator = 'like'; break;
          }
        }
        else $operator = '=';

        if($value === null) switch($operator){
          case '=':
          case '<>':
            $value = "`$column` is$negation null";
            break;
          default:
            $value = '0=1'; //onbepaald
        }
        elseif(is_array($value)){
          if($value){
            if($def) foreach($value as &$sub) $sub = $def->formatColumn($column,$sub,$this->defTables);
            unset($sub);
            $args[$key = 'a' . count($args)] = $value;
            switch($operator){
              case '=':
              case '<>':
                $value = "`$column`$negation in (:$key)";
                break;
              default:
                throw new Exception("Invalid array operator '$operator' for column '$column'");
            }
          }
          else $value = (int)($operator == '<>') . '=1';
        }
        elseif($raw) $value = "`$column` $operator $value";
        else{
          $args[$key = 'v' . count($args)] = $def ? $def->formatColumn($column,$value,$this->defTables) : $value;
          $value = "`$column` $operator :$key";
        }
      }
      unset($value);
      $where = implode(' and ',$where);
    }
    $where = ($where ?: '1=1') . $extra;
  }
  /**
   *  Limit an SQL statement.
   *  @param string $sql  Original SQL statement.
   *  @param int $limit  Limit (number of rows).
   *  @param int $offset  Offset.
   *  @return string  SQL statement with limit.
   */
  public function limit($sql,$limit,$offset = null){
    $sql .= ' limit ' . (int)$limit;
    if($offset) $sql .= ' offset ' . (int)$offset;
    return $sql;
  }
  /**
   *  Insert a record.
   *  @param string $table  Table name.
   *  @param array $columns  Columns (key = column name, value = value).
   *  @param bool $replace_if_exists  If true, an existing record will be replaced (if alse, an exception will be thrown when a
   *    record with the same key already exists).
   *  @return int  The number of affected rows (0 = failure, 1 = success).
   */
  public function insert($table,$columns,$replace_if_exists = false){
    $def = $this->defTables ? $this->component('def') : null;
    $values = [];
    foreach($columns as $column => &$value){
      if($raw = substr($column,0,1) == '!') $column = substr($column,1);
      elseif($def) $value = $def->formatColumn($column,$value,$this->defTables);
      $values[$column] = $raw ? $value : ':' . $column;
    }
    unset($value);
    $sql = ($replace_if_exists ? 'replace' : 'insert') . " into `$table` (`" . implode('`,`',array_keys($values)) . '`) values (' . implode(',',$values) . ')';
    return $this->execute($sql,$columns);
  }
  /**
   *  Replace a record (insert if not exists).
   *  @param string $table  Table name.
   *  @param array $columns  Columns (key = column name, value = value).
   *  @return int  The number of affected rows (0 = failure, 1 = success).
   */
  public function replace($table,$columns){
    return $this->insert($table,$columns,true);
  }
  /**
   *  Select records.
   *  @param string $table  Table name.
   *  @param array $columns  Columns to select from the table. Prefix the first columns with a '+' to make this the key.
   *  @param string|array $where  Where.
   *  @param string|array $args  Arguments (assoc.array) or extra statement for the where.
   *  @param int|bool $limit  Limit the number of rows (0 = no limit).
   *  @param int $offset  Offset.
   *  @return array  Single value when limit is true, single row when limit is 1, otherwise an array of records.
   *  @see prepareWhere()
   */
  public function select($table,$columns,$where = null,$args = null,$limit = null,$offset = null){
    $this->prepareWhere($where,$args);
    $columns = preg_replace('/^(`)?\\+/','$1',is_array($columns) ? '`' . implode('`,`',$columns) . '`' : $columns,1,$record);
    $sql = "select $columns from `$table` where $where";
    if($limit) $sql = $this->limit($sql,$limit,$offset);
    if($record) return $this->record($sql,$args);
    $rows = $this->all($sql,$args);
    return $limit === true
      ? (($row = array_shift($rows)) ? array_shift($row) : false) //single column
      : ($rows ? ($limit == 1 ? array_shift($rows) : $rows) : false);
  }
  /**
   *  Update one or more record(s).
   *  @param string $table  Table name.
   *  @param array $columns  Columns to update (key = column name, value = new value).
   *  @param string|array $where  Where.
   *  @param string|array $args  Arguments (assoc.array) or extra statement for the where.
   *  @param bool $insert_if_not_exists  If true, a new record will be inserted when there is not yet an existing record.
   *  @return int  The number of affected rows (0 = failure, 1 or more = success).
   */
  public function update($table,$columns,$where = null,$args = null,$insert_if_not_exists = false){
    $this->prepareWhere($where,$args);
    $def = $this->defTables ? $this->component('def') : null;
    $sql = [];
    foreach($columns as $column => $value){
      if($raw = substr($column,0,1) == '!') $column = substr($column,1);
      elseif($def) $value = $def->formatColumn($column,$value,$this->defTables);
      $args[$key = 'c' . count($args)] = $value;
      $sql[] = "`$column` = " . ($raw ? $value : ':' . $key);
    }
    $sql = "update `$table` set " . implode(',',$sql) . " where $where";
    $result = $this->execute($sql,$args);
    if(!$result && $insert_if_not_exists) $result = $this->insert($table,$columns);
    return $result;
  }
  /**
   *  Delete one or more record(s).
   *  @param string $table  Table name.
   *  @param string|array $where  Where.
   *  @param string|array $args  Arguments (assoc.array) or extra statement for the where.
   *  @return int  The number of affected rows (0 = failure, 1 or more = success).
   */
  public function delete($table,$where = null,$args = null){
    $this->prepareWhere($where,$args);
    $sql = "delete from `$table` where $where";
    return $this->execute($sql,$args);
  }
  /**
   *  Check if a record exists that meets the requirement.
   *  @param string $table  Table name.
   *  @param string|array $where  Where.
   *  @param string|array $args  Arguments (assoc.array) or extra statement for the where.
   *  @return bool  True if a record exists.
   */
  public function exists($table,$where = null,$args = null){
    $this->prepareWhere($where,$args);
    $sql = $this->limit("select 1 from `$table` where $where",1);
    return (bool)$this->fetch($this->query($sql,$args));
  }
  /**
   *  The number of records that meet the requirements.
   *  @param string $table  Table name.
   *  @param string|array $where  Where.
   *  @param string|array $args  Arguments (assoc.array) or extra statement for the where.
   *  @return int  Number of rows.
   */
  public function count($table,$where = null,$args = null){
    $this->prepareWhere($where,$args);
    $sql = "select count(*) from `$table` where $where";
    $row = $this->fetch($this->query($sql,$args));
    return array_pop($row);
  }
  /**
   *  Name of the current database.
   *  @return string
   */
  public function database(){
    return $this->single('select database()');
  }
  /**
   *  All tables in current database.
   *  @return array
   */
  public function tables(){
    return $this->record('show tables');
  }
  /**
   *  Column properties for a table.
   *  @param string $table  Name of the table.
   *  @param string $database  Name of the database (current database when empty).
   *  @return array  Key = column name, value = assoc.array with properties.
   */
  public function columns($table,$database = null){
    return $this->record('
      select
        COLUMN_NAME,
        DATA_TYPE as `type`,
        CHARACTER_MAXIMUM_LENGTH as `length`,
        NUMERIC_PRECISION as `precision`,
        NUMERIC_SCALE as `scale`,
        COLUMN_DEFAULT as `default`,
        if(IS_NULLABLE = "NO",1,0) as `required`,
        if(COLUMN_KEY = "PRI",1,0) as `primary`
      from INFORMATION_SCHEMA.COLUMNS
      where TABLE_SCHEMA = :database
        and TABLE_NAME = :table
      order by ORDINAL_POSITION',
      [
        'database' => $database ?: $this->database(),
        'table' => $table
      ]
    );
  }

  public function __call($func_name,$params){
    if(substr($func_name,0,1) != '_') return $this->select($func_name,'*',array_shift($params),array_shift($params),1);
    $func_name = substr($func_name,1);
    $tables = array_shift($params);
    if(in_array($func_name,['insert','replace','select','update','delete','exists','count'])) array_unshift($params,$tables);
    $prev_tables = $this->defTables;
    $this->defTables = is_array($tables) ? $tables : [$tables];
    try{
      $result = call_user_func_array([$this,$func_name],$params);
    }
    finally{
      $this->defTables = $prev_tables;
    }
    return $result;
  }

  public function __invoke($sql,$args = null){
    return $this->execute($sql,$args);
  }

  public function __sleep(){
    return [];
  }

}