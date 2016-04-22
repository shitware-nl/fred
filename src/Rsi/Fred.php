<?php

namespace Rsi;

ini_set('display_errors',false);
error_reporting(E_ALL);

/**
 *  Framework for Rapid and Easy Development.
 *
 *  The Fred object is at the core of the framework. From this object all components will be initialized. This is done in a
 *  'lazy' manner. That is: only when the component is specificly called (through the component() function or with a magic
 *  __call()).
 *
 *  This object also does autoloading and error handling.
 */
class Fred extends Object{

  const EVENT_HALT = 'fred:halt';
  const EVENT_EXTERNAL_ERROR = 'fred:externalError';
  const EVENT_SHUTDOWN = 'fred:shutdown';

  public $debug = false; //!<  True for debug modus.
  public $autoloadCacheKey = 'autoloadCache'; //!<  Session key for missing classnames cache.
  public $autoloadMissingKey = 'autoloadMissing'; //!<  Session key for missing classnames cache.
  public $defaultComponentNamespace = __CLASS__; //!<  Default namespace for componenten. If there is no class name defined for
    // a component, then the framework will try to load the class in this namespace with the same name (ucfirst-ed).

  public $templatePath = null; //!<  Path for the framework templates.
  public $version = null; //!<  Project version.

  protected $_startTime = null; //!<  Time at which the request started.
  protected $_initialized = false; //!<  True if the framework is initialised.
  protected $_releaseNotesFile = __DIR__ . '/../../doc/pages/notes.php';
  protected $_config = []; //!<  The configuration.
  protected $_internalError = false; //!<  True if an internal error has (already) occured.

  protected $_autoloadNamespaces = [__NAMESPACE__ => [__DIR__]]; //!<  Autoload namespace prefix (key) en paths (value).
  protected $_autoloadClasses = []; //!<  Autoload classes (key) and files (value).
  protected $_autoloadFiles = []; //!<  Autoload files (if not covered by the previous options).
  protected $_autoloadCache = []; //!<  Register direct location of class files.
  protected $_autoloadMissing = []; //!<  Register missing autoload classes (prevent double checking).
  protected $_components = []; //!<  Initialized components (key = component name, value = component).

  /**
   *  Initialize the framework.
   *  @param string|array $config  The configuratie. In case of a string, the file with this name will be included.
   */
  public function __construct($config){
    try{
      $this->_startTime = array_key_exists('REQUEST_TIME_FLOAT',$_SERVER) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
      spl_autoload_register([$this,'autoload'],true,true);
      $this->_config = is_array($config) ? $config : require($config);
      $this->init();
    }
    catch(Exception $e){
      $this->exceptionHandler($e);
    }
  }
  /**
   *  Initialize the framework.
   */
  protected function init(){
    $this->configure($this->_config);
    ini_set('display_errors',$this->debug);
    set_error_handler([$this,'errorHandler']);
    set_exception_handler([$this,'exceptionHandler']);
    register_shutdown_function([$this,'shutdownFunction']);
    $this->publish(['startTime' => self::READABLE,'autoloadNamespaces' => self::READABLE,'autoloadClasses' => self::READABLE]);
    try{
      if(session_status() == PHP_SESSION_NONE) session_start();
      if(array_key_exists($this->autoloadCacheKey,$_SESSION)) $this->_autoloadCache = $_SESSION[$this->autoloadCacheKey];
      if(array_key_exists($this->autoloadMissingKey,$_SESSION)) $this->_autoloadMissing = $_SESSION[$this->autoloadMissingKey];
    }
    catch(Exception $e){
      if($this->debug) throw $e;
    }
    if($this->debug){
      $this->log->debug('FRED framework initialized',__FILE__,__LINE__);
      $this->releaseNotes();
    }
    $this->_initialized = true;
  }
  /**
   *  Autoloader.
   *  The autoloader tries to load a class in 3 steps:
   *  - If the class is specificly mentioned in the $_autoloadClasses, then the corresponding file will be loaded.
   *  - If the class name starts with a prefix from the $_autoloadNamespaces, then the rest of the class name (namespace
   *    separator = directory separator) and the path corresponding path will be used to create the filename.
   *  - If both these options fail, all files in the $_autoloadFiles will be loaded (once) (hoping the sought class will be in
   *    there).
   */
  public function autoload($class_name){
    if($this->debug && $this->_initialized) $this->log->debug(__CLASS__ . "::autoload('$class_name')",__FILE__,__LINE__,['className' => $class_name]);
    if($this->_autoloadCache && array_key_exists($class_name,$this->_autoloadCache)) require($this->_autoloadCache[$class_name]);
    elseif(!$this->_autoloadMissing || !in_array($class_name,$this->_autoloadMissing)){
      $prefix_match = false;
      if(array_key_exists($class_name,$this->_autoloadClasses)) return require($this->_autoloadClasses[$class_name]);
      foreach($this->_autoloadNamespaces as $prefix => $paths) if(substr($class_name,0,strlen($prefix)) == $prefix){
        $prefix_match = true;
        foreach($paths as $path)
          if(is_file($filename = $path . str_replace('\\','/',substr($class_name,strlen(rtrim($prefix,'\\')))) . '.php')){
            $this->_autoloadCache[$class_name] = $filename;
            $_SESSION[$this->autoloadCacheKey] = $this->_autoloadCache;
            return require($filename);
          }
      }
      if(!$prefix_match && $this->_autoloadFiles){
        foreach($this->_autoloadFiles as $filename) require($filename);
        $this->_autoloadFiles = false;
      }
      elseif($this->_autoloadMissing !== null){
        $this->_autoloadMissing[] = $class_name;
        $_SESSION[$this->autoloadMissingKey] = $this->_autoloadMissing;
      }
    }
  }
  /**
   *  Error handler.
   *  Throws an exception to get a unified error handling.
   */
  public function errorHandler($error_no,$message,$filename,$line_no,$context = null){
    throw new \ErrorException($message,$error_no,0,$filename,$line_no);
  }
  /**
   *  End the request.
   *  @param int|string $status  Value to pass to the exit() function.
   */
  public function halt($status = null){
    if($this->event->trigger(self::EVENT_HALT,$this,$status) !== false) exit($status);
  }
  /**
   *  Separate objects.
   *  @param mixed $item  Item to check on (recursive for arrays).
   *  @param array $objects  Array to store objects in. Item will be replaced with name of key.
   *  @param int $level  Current nesting level (stops at 10).
   */
  protected function stripObjects(&$item,&$objects,$level = 0){
    if(is_object($item)){
      $objects[$key = '@@object_' . md5(print_r($item,true)) . '@@'] = $item;
      $item = $key;
    }
    elseif(is_array($item) && ($level < 10)) foreach($item as &$sub) $this->stripObjects($sub,$objects,$level + 1);
    unset($sub);
  }
  /**
   *  Separate objects from a trace.
   *  @param array $trace
   *  @param array $objects  Array to store objects in. Item will be replaced with name of key.
   */
  protected function stripTraceObjects(&$trace,&$objects){
    foreach($trace as &$step){
      if(array_key_exists('object',$step)) $this->stripObjects($step['object'],$objects);
      if(array_key_exists('args',$step) && $step['args']) $this->stripObjects($step['args'],$objects);
      else unset($step['args']);
    }
    unset($step);
  }
  /**
   *  Handle an (deliberately caused) external error.
   */
  public function externalError($message,$context = null){
    if($this->debug){
      print($message . "\n\n");
      if(!\Rsi::commandLine()){
        $trace = debug_backtrace();
        $objects = [];
        $this->stripTraceObjects($trace,$objects);
        print_r($context);
        print_r($trace);
        print_r($objects);
      }
      exit('External error');
    }
    $this->log->notice('External error: ' . $message,$context);
    if($this->event->trigger(self::EVENT_EXTERNAL_ERROR,$this,$message) !== false){
      http_response_code(400); //Bad Request
      $this->halt();
    }
  }
  /**
   *  Handle an internal error.
   *  @param string $message  Error message.
   *  @param string $filename  File in which the error occured.
   *  @param int $line_no  Line at which the error occured.
   *  @param array $trace  Backtrace of the moment the error occured.
   */
  public function internalError($message,$filename = null,$line_no = null,$trace = null){
    try{
      if(!$trace) $trace = debug_backtrace();
      if(ob_get_length()) ob_clean();
      $objects = [];
      $this->stripTraceObjects($trace,$objects);
      if($this->_internalError){
        if($this->debug){
          print($message);
          print_r($trace);
          print_r($objects);
        }
        exit('Internal error');
      }
      $this->_internalError = true;

      if(!$this->debug) try{
        $this->log->emergency($message,array_filter([
          'filename' => $filename,
          'lineNo' => $line_no,
          'trace' => $trace,
          'objects' => $objects,
          'GET' => $_GET,
          'POST' => $_POST,
          'COOKIE' => $_COOKIE,
          'SESSION' => isset($_SESSION) ? $_SESSION : null
        ]));
        usleep(rand(0,10000));
        http_response_code(500);
        if(file_exists($template = $this->templatePath . 'error.php')) require($template);
      }
      catch(Exception $e){
        print('An unexpected error has occurred');
      }
      elseif(!\Rsi::commandLine() && file_exists($template = $this->templatePath . 'debug.php')) require($template);
      else print($message . "\n\n");
      $this->halt();
    }
    catch(Exception $e){ //de default exception handler wordt niet nogmaals aangeroepen bij een unhandled exception
      $this->internalError($e->getMessage(),$e->getFile(),$e->getLine(),$e->getTrace());
    }
  }
  /**
   *  Exception handler.
   *  @param Exception $exception
   */
  public function exceptionHandler($exception){
    $this->internalError($exception->getMessage(),$exception->getFile(),$exception->getLine(),$exception->getTrace());
  }
  /**
   *  Shutdown function.
   *  If a (non catched) error is the reason for the shutdown (eg a timeout), then the shutdown will be processed as an internal
   *  error.
   *  @see internalError()
   */
  public function shutdownFunction(){
    if(!$this->_internalError && ($error = error_get_last()) && (substr($error['message'],0,10) != 'SOAP-ERROR') && ($this->event->trigger(self::EVENT_SHUTDOWN,$this,$error) !== false))
      $this->internalError($error['message'],$error['file'],$error['line']);
  }
  /**
   *  Add new release notes to messages and log.
   */
  public function releaseNotes(){
    if(
      $this->_releaseNotesFile &&
      ($time = \Rsi\File::mtime($this->_releaseNotesFile)) &&
      ($time != \Rsi\Record::get($_SESSION,'releaseNotesTime')) &&
      preg_match_all('/\\n- ([\\d\\.]+): (.*)/',file_get_contents($this->_releaseNotesFile),$matches,PREG_SET_ORDER)
    ){
      $messages = [];
      foreach($matches as $match){
        $version = $match[1] . '-' . substr(md5($match[2]),-4);
        if(\Rsi\Str::startsWith($this->version,$version)) $messages = [];
        else $messages[] = "FRED version $version: " . $match[2];
      }
      if($messages){
        foreach($messages as $message){
          $this->message->warning($message);
          $this->log->warning($message);
        }
        $this->log->notice("Upgraded to FRED version $version.");
      }
      else $_SESSION['releaseNotesTime'] = $time;
    }
  }
  /**
   *  Replace config keys with variables.
   *  @param mixed $config  For arrays, values with keys starting with a '@' the value is replaced by the variable with that
   *    name (and the '@' is removed from the key).
   *  @return mixed
   */
  public function replaceVars($config){
    if(!is_array($config)) return $config;
    $result = [];
    foreach($config as $key => $value) if(substr($key,0,1) == '@')
      $result[substr($key,1)] = (substr($value,0,1) == '{') && (substr($value,-1) == '}')
        ? json_decode($this->vars->value(substr($value,1,-1)),true)
        : $this->vars->value($value);
    else $result[$key] = $this->replaceVars($value);
    return $result;
  }
  /**
   *  Get a value from the configuration.
   *  @param string|array $key  Key to get the value from the configuration (array = nested).
   *  @param mixed $default  Default value if the key does not exist.
   *  @return mixed  Found value, or default value if the key does not exist.
   */
  public function config($key,$default = null){
    return $this->replaceVars(Record::get($this->_config,$key,$default));
  }

  protected function defaultComponentClassName($name){
    return $this->defaultComponentNamespace . '\\' . ucfirst($name);
  }
  /**
   *  Get a component.
   *  @param string $name  Name of the component.
   */
  public function component($name){
    if(!$this->has($name)){
      $config = $this->config($name);
      if($config && !is_array($config)){
        if(is_callable($config)) $config = call_user_func($config,$name);
        if(is_string($config)) $config = require($config);
      }
      $class_name = Record::get($config,'className') ?: $this->defaultComponentClassName($name);
      $this->_components[$name] = new $class_name($this,array_merge(['name' => $name],$config ?: []));
    }
    return $this->_components[$name];
  }
  /**
   *  Get a component if there is a configuration entry for it.
   *  @param string $name  Name of the component.
   */
  public function may($name){
    return array_key_exists($name,$this->_components) || array_key_exists($name,$this->_config) || class_exists($this->defaultComponentClassName($name))
      ? $this->component($name)
      : false;
  }
  /**
   *  Get a component if it already exists.
   *  @param string $name  Name of the component.
   */
  public function has($name){
    return array_key_exists($name,$this->_components) ? $this->_components[$name] : false;
  }
  /**
   *  Public configuration.
   *  @return array  Public configuration for all components (key = component name, value = public component configuration).
   */
  public function clientConfig(){
    $config = [];
    if($this->debug) $config['debug'] = true;
    foreach($this->_components as $name => $component) $config[$name] = $component->clientConfig();
    return array_filter($config);
  }

  protected function setAutoloadNamespaces($namespaces){
    $this->_autoloadNamespaces = array_merge($this->_autoloadNamespaces,$namespaces);
  }

  protected function setAutoloadClasses($classes){
    $this->_autoloadClasses = array_merge($this->_autoloadClasses,$classes);
  }

  protected function setAutoloadFiles($classes){
    $this->_autoloadFiles = array_merge($this->_autoloadFiles,$classes);
  }

  protected function _get($key){
    return $this->component($key);
  }

  public function __call($func_name,$params){
    return call_user_func_array([$this->entity,'item'],array_merge([$func_name],$params));
  }

}