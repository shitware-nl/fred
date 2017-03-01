<?php

namespace Rsi\Fred;

/**
 *  Component comprising all information regarding an HTTP-request (to transport these along all stepts executed during a
 *  request).
 */
class Request extends Component{

  public $errors = []; //!<  Errors detected during the processing of the request (key = item ID, value = error code).
  public $data = []; //!<  Data to be presented by the view (key-value pairs).
  public $result = null; //!<  Result fo the request (mixed).
  public $redir = null; //!<  URL for redirection (empty = no redirection).
  public $viewControllerName = null; //!<  Name of the view controller (same as the request handler when empty).

  public $jsonDecodingError = 'JSON decoding error: [error]';
  public $postExceededError = 'POST exceeded ([size] / [max])';

  protected $_files = null;

  protected function init(){
    parent::init();
    $this->checkPost();
  }
  /**
   *  Checks the post (by content type).
   */
  protected function checkPost(){
    switch(\Rsi\Record::get($_SERVER,'CONTENT_TYPE')){
      case 'application/json':
        $_POST = json_decode(file_get_contents('php://input'),true);
        if(json_last_error()) $this->_fred->trans->str($this->jsonDecodingError,['error' => json_last_error_msg()]);
        break;
      case 'application/xml':
        $_POST = json_decode(json_encode(simplexml_load_file('php://input',null,LIBXML_NOCDATA)),true);
        break;
      default:
        if(\Rsi\Http::postExceeded($size,$max)){
          $local = $this->_fred->local;
          $this->errors['post'] = $this->_fred->trans->str($this->postExceededError,['size' => $local->formatBytes($size),'max' => $local->formatBytes($max)]);
        }
    }
  }
  /**
   *  Transform an array of uploaded files to a 'normal' structure.
   *  Single file uploads have an entry in the $_FILES array with properties 'tmp_name', 'size', and so on. With an array of
   *  files this is also the case, and each of these property has a multi dimensional array of values. For the processing of
   *  single files though it is more convinient if all files have the same single dimension array structure as the single file
   *  upload at the deepest level. Therefore the property names must move to the end of the structure.
   *  @param array $values  Array with properties (single or multi dimensional).
   *  @param array $prefix  Array to prefix before the keys.
   *  @param array $files  Already determined, correct structure.
   *  @return array  Complemented, correct structure.
   */
  protected function fixFileKey($values,$prefix = null,$files = null){
    if(!$prefix) $prefix = $files = [];
    foreach($values as $key => $value){
      $key = array_merge($prefix,[$key]);
      if(is_array($value)) $files = $this->fixFileKey($value,$key,$files);
      else{
        $item = array_shift($key);
        \Rsi\Record::set($files,array_merge($key,[$item]),$value);
      }
    }
    return $files;
  }
  /**
   *  Retrieve a file upload from the request.
   *  @param string $key  Name of the sought file.
   *  @return mixed  Found value (might be empty or have errors).
   */
  public function file($key){
    return \Rsi\Record::get($this->files,$key);
  }
  /**
   *  Retrieve a complex parameter (simple type or array) from the request.
   *  Parameters are first sought-after in the $_POST, then in the $_FILES, and last in the $_GET. Only the latter will be done
   *  case insensitive.
   *  @param string $key  Name of the sought parameter.
   *  @param mixed $default  Default value if the key does not exist.
   *  @return mixed  Found value, or default value if the key does not exist.
   */
  public function complex($key,$default = null){
    if(($value = \Rsi\Record::get($_POST,$key,false)) !== false) return $value;
    if(($value = $this->file($key)) !== null) return $value;
    return \Rsi\Record::iget($_GET,$key,$default);
  }
  /**
   *  Retrieve a simple parameter (not an array) from the request.
   *  @see complex()
   *  @param string $key  Name of the sought parameter.
   *  @param mixed $default  Default value if the key does not exist.
   *  @return mixed  Found value, or default value if the key does not exist.
   */
  public function simple($key,$default = null){
    return is_array($value = $this->complex($key,$default)) ? $default : $value;
  }

  public function data($key,$default = null){
    return \Rsi\Record::get($this->data,$key,$default);
  }

  protected function getFiles(){
    if($this->_files === null){
      $this->_files = [];
      foreach($_FILES as $key => $value) $this->_files[$key] = $this->fixFileKey($value);
    }
    return $this->_files;
  }

  protected function _get($key){
    return array_key_exists($key,$this->data) ? $this->data[$key] : $this->simple($key);
  }

  protected function _set($key,$value){
    $this->data[$key] = $value;
  }

  public function __invoke($key,$default = null){
    return $this->data($key,$default);
  }

}