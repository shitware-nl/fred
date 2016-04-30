<?php

namespace Rsi\Fred;

use Rsi\Fred\Controller\Widget;

class Def extends Component{

  public $databaseStructureFilename = null;
  public $filename = null;

  public $boolTrue = 1;
  public $boolFalse = 0;

  protected $_def = null;

  /**
   *  Generate a definition file from the database structure.
   *  @param string $table_trans  Transformation method to use for the table name.
   *  @see \\Rsi\\Record::transform()
   */
  public function generateDatabaseStructure($table_trans = null){
    if(!$this->databaseStructureFilename) throw new Exception('No database structure filename');
    $structure = [];
    $db = $this->component('db');
    foreach($db->tables() as $table_name){
      $table = [];
      foreach($db->columns($table_name) as $column_name => $column){
        $def = [
          Widget::REQUIRED => (bool)$column['required'],
          Widget::DEFAULT_VALUE => $column['default']
        ];
        if($column['primary']) $def['primary'] = true;
        $type = $column['type'];
        if(substr($type,-3) == 'int'){
          $def['type'] = 'number';
          $def[Widget::MIN] = -($def[Widget::MAX] = pow(10,$column['precision']) - 1);
        }
        elseif(substr($type,-4) == 'text'){
          $def['type'] = 'memo';
          $def[Widget::MAX] = (int)$column['length'];
        }
        else switch($type){
          case 'varchar':
            $def['type'] = $column['length'] > 128 ? 'memo' : 'char';
            $def[Widget::MAX] = (int)$column['length'];
            break;
          case 'decimal':
          case 'float':
          case 'double':
            $def['type'] = 'number';
            if(($scale = $column['scale']) !== null){
              $def['decimals'] = $scale;
              $def[Widget::MIN] = -($def[Widget::MAX] = pow(10,$column['precision'] - $scale) - pow(10,-$scale));
            }
            break;
          default:
            $def['type'] = $type;
        }
        $table[$column_name] = array_filter($def);
      }
      $structure[\Rsi\Str::transform($table_name,$table_trans)] = $table;
    }
    file_put_contents(
      $this->databaseStructureFilename,
      "<?php\n\n" .
      "//this database-structure file was auto-generated on " . date('Y-m-d G:i:s') . "\n" .
      "//it may be overwritten in the future -- changes will NOT be saved\n" .
      "//use '{$this->filename}' instead for adjustments\n\n" .
      preg_replace('/\\s+array \\(/',' array(','return ' . var_export($structure,true) . ';')
    );
  }
  /**
   *  Table definition (all columns).
   *  @param string $table
   *  @return array  Key = column, value = definition.
   */
  public function table($table){
    return \Rsi\Record::iget($this->def,$table);
  }
  /**
   *  Primary key columns.
   *  @param string $table
   *  @return array
   */
  public function key($table){
    $key = [];
    foreach($this->table($table) as $column => $def) if(\Rsi\Record::get($def,'primary')) $key[] = $column;
    return $key;
  }
  /**
   *  Column definition.
   *  @param string $table  Table name.
   *  @param string $column  Column name.
   *  @param array $extra  Extra properties to add to the definition.
   *  @return array  Definition (including referenced definitions).
   */
  public function column($table,$column,$extra = null){
    $def = array_merge(\Rsi\Record::iget($this->table($table),$column,[]),$extra ?: []);
    if(array_key_exists(null,$def)){
      $ref = \Rsi\Record::explode($def[null]);
      $column = array_pop($ref);
      $def = array_merge($this->column($ref ? array_pop($ref) : $table,$column),$def);
      unset($def[null]);
    }
    return $def;
  }
  /**
   *  Get the base (referenced) column.
   *  @param string $table
   *  @param string $column
   *  @return string  Base column name.
   */
  public function ref($table,$column){
    if($ref = \Rsi\Record::iget($this->table($table),[$column,null])){
      $column = array_pop($ref);
      return $this->ref($ref ? array_pop($ref) : $table,$column) ?: $column;
    }
    return null;
  }
  /**
   *  Convert a single value from database format to standard, internal format.
   *  @param string $type  Column type.
   *  @param mixed $value  Value in database format.
   *  @return mixed  Value in standard, internal format.
   */
  public function convert($type,$value){
    switch($type){
      case 'bool': return $value == $this->boolTrue;
      case 'number': return $value + 0;
    }
    return $value;
  }
  /**
   *  Convert a single column value from database format to standard, internal format.
   *  @param string $column  Column name.
   *  @param mixed $value  Value in database format.
   *  @param array $tables  Tables for the column.
   *  @return mixed  Value in standard, internal format.
   */
  public function convertColumn($column,$value,$tables){
    foreach($tables as $table) if($def = $this->column($table,$column))
      return $this->convert(\Rsi\Record::get($def,'type'),$value);
    return $value;
  }
  /**
   *  Convert a record from database format to standard, internal format.
   *  @param array $record  Record in database format.
   *  @param array $tables  Tables for the column.
   *  @return array  Record in standard, internal format.
   */
  public function convertRecord($record,$tables){
    if($record) foreach($record as $column => &$value) $value = $this->convertColumn($column,$value,$tables);
    unset($value);
    return $record;
  }
  /**
   *  Format a value from standard, internal format to database format.
   *  @param string $type  Column type.
   *  @param mixed $value  Value in standard, internal format.
   *  @return mixed  Value in database format.
   */
  public function format($type,$value){
    if($value !== null) switch($type){
      case 'bool': return $value ? $this->boolTrue : $this->boolFalse;
      case 'date': return is_numeric($value) ? $this->component('db')->date($value) : $value;
      case 'datetime': return is_numeric($value) ? $this->component('db')->dateTime($value) : $value;
    }
    return $value;
  }
  /**
   *  Convert a single column value from standard, internal format to database format.
   *  @param string $column  Column name.
   *  @param mixed $value  Value in standard, internal format.
   *  @param array $tables  Tables for the column.
   *  @return mixed  Value in database format.
   */
  public function formatColumn($column,$value,$tables){
    foreach($tables as $table) if($def = $this->column($table,$column))
      return $this->format(\Rsi\Record::get($def,'type'),$value);
    return $value;
  }
  /**
   *  Format a value from standard, internal format to database format.
   *  @param array $record  Record in standard, internal format.
   *  @param array $tables  Tables for the column.
   *  @return array  Record in database format.
   */
  public function formatRecord($record,$tables){
    if($record) foreach($record as $column => &$value) foreach($tables as $table) if($column = $this->column($table,$column)){
      $value = $this->format(\Rsi\Record::get($column,'type'),$value);
      break;
    }
    unset($value);
    return $record;
  }

  protected function getDef(){
    if($this->_def === null){
      $this->_def = [];
      if($this->databaseStructureFilename) $this->_def = array_replace_recursive($this->_def,require($this->databaseStructureFilename));
      if($this->filename) $this->_def = array_replace_recursive($this->_def,require($this->filename));
    }
    return $this->_def;
  }

  public function __call($func_name,$params){
    return $this->column($func_name,array_shift($params),array_shift($params));
  }

}