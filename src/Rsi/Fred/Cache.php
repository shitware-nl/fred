<?php

namespace Rsi\Fred;

/**
 *  Wrapper for the Doctrine cache.
 */
class Cache extends Component{

  protected $_type = 'array'; //!<  Cache type to use.
  protected $_params = []; //!<  Parameters (arguments) for this type of cache.

  protected $_instance = null;

  protected function init(){
    parent::init();
    $this->publish('type');
  }
  /**
   *  Fetch with callback function in case cache fails.
   *  @param string $id  Cache id.
   *  @param int $ttl  Cache lifetime.
   *  @param function $callback  Function to get data if cache fails (parameters: $id; returns data).
   *  @return mixed  Data (false on fail).
   */
  public function fetch($id,$ttl = 0,$callback = null){
    $data = $this->instance->fetch($id);
    if(($data === false) && $callback) $this->instance->save($id,$data = call_user_func($callback,$id),$ttl);
    return $data;
  }

  protected function getInstance(){
    if(!$this->_instance){
      $reflect = new \ReflectionClass('\\Doctrine\\Common\\Cache\\' . ucfirst($this->_type) . 'Cache');
      $this->_instance = $reflect->newInstanceArgs($this->_params);
    }
    return $this->_instance;
  }
  /**
   *  See vendor/doctrine/cache/lib/Doctrine/Common/Cache/Cache.php for the available functions (save, contains, fetch, delete,
   *  flushAll, getStats) and more explanation.
   */
  public function __call($func_name,$params){
    return call_user_func_array([$this->instance,$func_name],$params);
  }

  public function __invoke($id){
    return $this->instance->fetch($id);
  }

}