<?php

namespace Rsi\Fred\Security;

abstract class Check extends \Rsi\Fred\Component{

  /**
   *  Perform the check.
   *  @return bool  True if everything is fine. False if something is wrong. Null if something is wrong, but it is not
   *    significant enough to add a ban for it (e.g. repeated error).
   */
  abstract public function check();

  public function clientConfig(){
    return [];
  }

}