<?php

namespace Rsi\Fred;

class Html extends Component{

  public $escape = false;
  public $break = null;

  public function tag($tag,$content = null,$attribs = null){
    if($content && $this->escape) $content = htmlspecialchars($content);
    $this->escape = false;
    $html = '<' . $tag;
    if($attribs) foreach($attribs as $key => $value) if(!\Rsi::nothing($value)){
      $html .= ' ' . $key;
      if($value !== true) $html .= '="' . htmlspecialchars($value) . '"';
    }
    return $html . ($content === false ? '>' : ">$content</$tag>");
  }

  public function a($content,$attribs = null,$href = null){
    return $this->tag('a',$content,array_merge(['href' => $href],$attribs ?: []));
  }

  public function div($content,$attribs = null,$class = null,$id = null){
    return $this->tag('div',$content,array_merge(['class' => $class,'id' => $id],$attribs ?: []));
  }

  public function form($content,$attribs = null,$action = null,$method = 'post',$enctype = 'multipart/form-data'){
    return $this->tag('form',$content,array_merge(['action' => $action,'method' => $method,'enctype' => $enctype],$attribs ?: []));
  }

  public function img($content,$attribs = null,$alt = null){
    return $this->tag('img',false,array_merge(['src' => $content,'alt' => $alt ?: \Rsi\File::basename($content),'title' => $alt],$attribs ?: []));
  }

  public function input($content,$attribs = null,$type = null,$name = null,$options = null){
    if($options){
      $escape = $this->escape;
      $html = '';
      foreach($options as $key => $label){
        if($escape) $label = htmlspecialchars($label);
        $radio = $type == 'radio';
        $html .= $this->label(
          $this->input(
            $key,
            array_merge($attribs,['checked' => is_array($content) ? in_array($key,$content) : $key == $content]),
            $type,
            $name . ($radio ? '' : "[$key]")
          ) .
          $label
        );
      }
      return $html;
    }
    return $this->tag('input',false,array_merge(['type' => $type,'value' => $content,'name' => $name],$attribs ?: []));
  }

  public function meta($content,$attribs = null,$name){
    return $this->tag('meta',false,array_merge(['name' => $name,'content' => $content],$attribs ?: [])) . $this->break;
  }

  public function script($content,$attribs = null,$srcs = null,$type = 'text/javascript'){
    if($content) $content = "\n/* <![CDATA[ */\n$content\n/* ]]> */\n";
    if(!is_array($srcs)) $srcs = [$srcs];
    $html = '';
    foreach($srcs as $src) $html .= $this->tag('script',$content,array_merge(['src' => $src,'type' => $type],$attribs ?: [])) . $this->break;
    return $html;
  }

  public function select($content,$attribs = null,$value = null,$assoc = null,$name = null){
    if(is_array($options = $content)){
      if($assoc === null) $assoc = \Rsi\Record::assoc($options);
      $content = $this->break;
      foreach($options as $key => $descr){
        $option_attribs = [];
        if($assoc) $option_attribs['value'] = $key;
        else $key = $descr;
        if($key == $value) $option_attribs['selected'] = true;
        $content .= $this->_option($descr,$option_attribs) . $this->break;
      }
    }
    return $this->tag('select',$content,array_merge(['name' => $name],$attribs ?: []));
  }

  public function span($content,$attribs = null,$class = null,$id = null){
    return $this->tag('span',$content,array_merge(['class' => $class,'id' => $id],$attribs ?: []));
  }

  public function style($content,$attribs = null,$srcs = null,$type = 'text/css'){
    if(!$srcs) return $this->tag('style',$content,$attribs);
    if(!is_array($srcs)) $srcs = [$srcs];
    $html = '';
    foreach($srcs as $src) $html .= $this->tag('link',false,array_merge(['rel' => 'stylesheet','type' => $type,'href' => $src],$attribs ?: [])) . $this->break;
    return $html;
  }

  public function textarea($content,$attribs = null,$name = null){
    return $this->tag('textarea',$content ?: null,array_merge(['name' => $name],$attribs ?: []));
  }

  public function th($content,$attribs = null,$scope = 'col'){
    return $this->tag('th',$content,array_merge(['scope' => $scope],$attribs ?: []));
  }

  public function __call($func_name,$params){
    if(substr($func_name,0,1) == '_'){
      $this->escape = true;
      $func_name = substr($func_name,1);
    }
    else{
      array_unshift($params,$func_name);
      $func_name = 'tag';
    }
    return call_user_func_array([$this,$func_name],$params);
  }

}