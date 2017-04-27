<?php

namespace Rsi\Fred;

/**
 *  Front controller component.
 */
class Front extends Component{

  const EVENT_RENDER = 'front:render';

  public $defaultType = 'html';
  public $controllerNamespaces = []; //!<  Namespace prefix (key) per controller name prefix (value).
  public $defaultControllerName = 'Home';
  public $unknownControllerName = 'Unknown';
  public $deniedControllerName = 'Denied';

  /**
   *  Name for a controller by class name.
   *  @param string $class_name  Class name for the controller.
   *  @return string  Controller name.
   */
  public function controllerName($class_name){
    foreach($this->controllerNamespaces as $namespace => $prefix)
      if(($class_name == $namespace) || \Rsi\Str::startsWith($class_name,$namespace))
        return $prefix . str_replace('\\','/',substr($class_name,strlen($namespace)));
    return null;
  }
  /**
   *  Class name for a controller.
   *  @param string $name  Controller name.
   *  @return string  Class name for the controller.
   */
  public function controllerClassName($name){
    $class_name = null;
    foreach($this->controllerNamespaces + [null => null] as $namespace => $prefix) if(
      (!$prefix || ($name == $prefix) || \Rsi\Str::startsWith($name,$prefix . '/')) &&
      class_exists($class_name = $namespace . str_replace('/','\\',$prefix ? substr($name,strlen($prefix)) : '\\' . $name))
    ) break;
    return $class_name;
  }
  /**
   *  Get controller by name.
   *  @param string $name  Controller name.
   *  @param bool $redir  Redirect to the denied or login controller if the user is not authorized to execute the requested
   *    controller action. Otherwise return false.
   *  @return \\Rsi\\Fred\\Controller|bool
   */
  public function controller($name,$redir = true){
    $this->component('log')->debug(__CLASS__ . "::controller('$name')",__FILE__,__LINE__);
    if(
      (!$name || !class_exists($class_name = $this->controllerClassName($name))) &&
      !class_exists($class_name = $this->controllerClassName($name ? $this->unknownControllerName : $this->defaultControllerName))
    ) throw new Exception("Default controller '$class_name' does not exists");
    $controller = new $class_name($this->_fred,array_replace_recursive(
      $this->_fred->config('controller',[]),
      $this->_fred->config(array_merge(['controller'],array_map('lcfirst',explode('/',$name))),[])
    ));
    $user = $this->component('user');
    if(!$user->authenticated($controller->authSets) || !$user->authorized($controller->right))
      $controller = $redir ? $this->controller($user->authenticationControllerName ?: $this->deniedControllerName) : false;
    return $controller;
  }
  /**
   *  Execute the request and render the view.
   *  An optionally provided action will be executed, which will also complement the request component. Afterwards the view is
   *  rendered.
   */
  public function execute(){
    $request = $this->_fred->request;
    $this->component('alive');
    $controller = $this->controller($this->component('router')->controllerName);
    $request->viewControllerName = $name = $controller->name;
    $controller->execute();
    if($request->viewControllerName != $name) $controller = $this->controller($request->viewControllerName);
    else $controller->reset();
    foreach($request->errors as $id => $error) $this->component('message')->error($error,false);
    $this->component('log')->debug(__CLASS__ . '::execute: $controller->view->render()',__FILE__,__LINE__);
    $this->component('event')->trigger(self::EVENT_RENDER,$this,$controller);
    $controller->view->render();
  }

}