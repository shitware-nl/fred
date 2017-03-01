<?php

namespace Rsi\Fred;

class Router extends Component{

  public $pathInfoKey = 'PATH_INFO'; //!<  Key in the $_SERVER array for the path info.
  public $prefix = '/'; //!<  Prefix to add before each route.
  public $routes = []; //!<  Available routes (key = route, value = controller name). Parameters can be present in a route (eg
    // [id]). These will be added to the $_GET. Parameters can be appended with a reg-ex pattern (eg [id:\\d+] = numeric only).
    // Static parameters can be added to the controller name as query parameters (eg ?key=value), and a specific action as a
    // fragment (eg #action). The fragment must come before the parameters (eg #action?key=value).

  protected $_viewType = null;
  protected $_controllerName = null;

  /**
   *  Determine controller name and type.
   *  If the controller name matches a route, the name will be translated to this route, and parameters present will be added to
   *  the $_GET.
   *  @param string $path  If the path is not given, then the path after the script itself ('foo/bar' in '/index.php/foo/bar')
   *    will be used.
   */
  public function execute($path = null){
    $this->_viewType = null;
    if($path === null) $path = $this->pathInfo;
    if($this->_controllerName = trim(preg_replace('/^' . preg_quote($this->prefix,'/') . '/i','',$path),'/')){
      if($this->_viewType = strtolower(pathinfo($this->_controllerName,PATHINFO_EXTENSION)))
        $this->_controllerName = substr($this->_controllerName,0,-(1 + strlen($this->_viewType)));
      foreach($this->routes as $route => $controller_name){
        $mask = '/^' . str_replace('/','\\/',$route) . '$/i';
        if(preg_match_all('/\\[(.+?)(:.+?)?\\]/',$route,$params,PREG_SET_ORDER)) foreach($params as $param) //parameters in the route
          $mask = str_replace(str_replace('/','\\/',$param[0]),'(' . (count($param) > 2 ? substr($param[2],1) : '.*?') . ')',$mask);
        if(preg_match($mask,$this->_controllerName,$values)){ //path fits this route
          foreach($params as $index => $param){
            $_GET[$key = $param[1]] = $value = $values[$index + 1]; //copy parameter value
            if(preg_match('/^\\w+$/',$value)) $controller_name = str_replace("[$key]",$value,$controller_name);
          }
          if($index = strpos($controller_name,'?')){
            parse_str(substr($controller_name,$index + 1),$extra);
            $_POST = array_merge($_POST,$extra);
            $controller_name = substr($controller_name,0,$index);
          }
          if($index = strpos($controller_name,'#')){
            $_POST['action'] = substr($controller_name,$index + 1);
            $controller_name = substr($controller_name,0,$index);
          }
          $this->_controllerName = $controller_name;
          return;
        }
      }
      $this->_controllerName = ucwords(strtolower($this->_controllerName),'/');
    }
  }
  /**
   *  Determine a route from a controller name and type.
   *  @param string $controller_name (empty = current)
   *  @param string $type
   *  @param array $params  Parameters to use with the route.
   *  @return string  Found route. Parameters that were not used in the route are added in the query.
   */
  public function reverse($controller_name = null,$type = null,$params = null){
    if(!$controller_name) $controller_name = $this->controllerName;
    $link = $controller_name;
    foreach(array_keys($this->routes,$controller_name) as $route){
      if($params) foreach($params as $key => $value){
        $route = preg_replace('/\\[' . preg_quote($key,'/') . '.*?\\]/',$value,$route,1,$count);
        if($count) unset($params[$key]);
      }
      if(strpos($route,'[') === false){
        $link = $route;
        break;
      }
    }
    if($type) $link .= '.' . $type;
    if($params) $link .= (strpos($link,'?') === false ? '?' : '&') . http_build_query($params);
    return $this->prefix . $link;
  }

  protected function getControllerName(){
    if($this->_controllerName === null) $this->execute();
    return $this->_controllerName;
  }

  protected function getPathInfo(){
    return \Rsi\Record::get($_SERVER,$this->pathInfoKey);
  }

  protected function getViewType(){
    if($this->_controllerName === null) $this->execute();
    return $this->_viewType;
  }

}