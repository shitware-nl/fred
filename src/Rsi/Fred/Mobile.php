<?php

namespace Rsi\Fred;

class Mobile extends Component{

  const TABLET = 'tablet';
  const PHONE = 'phone';

  protected $_detection = null;
  protected $_type = null;

  protected function getDetection(){
    if(!$this->_detection) $this->_detection = new \Detection\MobileDetect();
    return $this->_detection;
  }

  protected function getType(){
    if($this->_type === null){
      $this->_type = $this->session->type;
      if($this->_type === null) $this->session->type = $this->_type =
        $this->detection->isMobile() ? ($this->detection->isTablet() ? self::TABLET : self::PHONE) : false;
    }
    return $this->_type;
  }

}