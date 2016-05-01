<?php

namespace Rsi\Fred;

class Security extends Component{

  public $proxies = []; //!<  Proxy white-list (key = proxy IP-address (optionally with wildcards), value = header to use
    // (string) or headers (array)).
  public $whitelist = []; //  IP-addresses (key; optionally with wildcards) to exclude from certain checks (value = array of
    // check names).
  public $blacklist = []; //  IP-addresses to ban anyhow (array of IP-address, optionally with wildcards).
  public $ext = '.ban'; //!<  Extension for ban registration file.
  public $defaultDelay = 86400; //!<  Default time (seconds) a ban reason stays in the registry.
  public $bruteForceDelay = 10; //!<  Default time (seconds) a brute force check stays in the registry.
  public $banCount = 5; //!<  Number of registration that will get you banned.

  protected $_path = null; //!<  Path to store the ban registration files (temp path if empty).
  protected $_checks = null; //!<  Available checks (key = name, value = \\Rsi\\Fred\\Security\\Check).

  protected $_remoteAddr = null; //!<  True IP-address of the client client (optionally behind a white-listed proxy).
  protected $_filename = null;
  protected $_banned = null;

  public function clientConfig(){
    $config = [];
    foreach($this->checks as $name => $check) $config[$name] = $check->clientConfig();
    return array_filter($config);
  }

  protected function filename($ip){
    return $this->path . str_replace(['.',':'],'-',$ip) . $this->ext;
  }
  /**
   *  Add a ban reason to the registry.
   *  @param string $reason  Name of reason.
   *  @param int $delay  Time (seconds) the reason should stay in the registry (empty = default).
   */
  public function addBan($reason,$delay = null){
    try{
      if(!file_exists($this->filename) || (count(file($this->filename)) < 10 * $this->banCount)){
        file_put_contents($this->filename,(time() + ($delay ?: $this->defaultDelay)) . ":$reason\n",FILE_APPEND);
        $this->session->banned = $this->_banned = null; //determine again on next call
      }
    }
    catch(Exception $e){
      if($this->_fred->debug) throw $e;
    }
  }
  /**
   *  Add a brute force reason to the registry.
   *  @param bool $result  Whether the request was OK or not (only negative results get registered; however, positive results
   *    with a non-empty registry will be delayed too - this prevents attackers from interrupting a request after a small amount
   *    of time).
   *  @param string $reason  Name of reason.
   *  @param int $delay  Time (seconds) the reason should stay in the registry (empty = default).
   */
  public function bruteForceDelay($result,$reason = null,$delay = null){
    if(!$result) $this->addBan($reason ?: 'brute',$delay ?: $this->bruteForceDelay);
    if(file_exists($this->filename)) sleep($this->bruteForceDelay * ($result ? pow(rand(0,100),3) / 1000000 : 1));
  }
  /**
   *  Unban a client's IP address.
   *  @param string $ip
   *  @return bool  True if he ban was successful deleted.
   */
  public function unBan($ip){
    return \Rsi\File::unlink($this->filename($ip));
  }
  /**
   *  Perform check on server config.
   *  https://www.owasp.org/index.php/PHP_Configuration_Cheat_Sheet
   *  @return array  Warnings (empty = everything is fine).
   */
  public function checkServer(){
    $warnings = [];
    if(ini_get('expose_php')) $warnings[] = 'php.ini: expose_php = On';
    if(ini_get('error_reporting') != E_ALL) $warnings[] = 'php.ini: error_reporting != E_ALL';
    if(ini_get('display_errors')) $warnings[] = 'php.ini: display_errors = On';
    if(ini_get('display_startup_errors')) $warnings[] = 'php.ini: display_startup_errors = On';
    if(!ini_get('log_errors')) $warnings[] = 'php.ini: log_errors = Off';
    if(!is_dir(dirname(ini_get('error_log')))) $warnings[] = 'php.ini: dirname(error_log) does not exist';
    if(ini_get('ignore_repeated_errors')) $warnings[] = 'php.ini: ignore_repeated_errors = On';
    if(ini_get('session.name') == 'PHPSESSID') $warnings[] = 'php.ini: session.name = default';
    if(!ini_get('session.hash_function')) $warnings[] = 'php.ini: session.hash_function = undefined';
    if(ini_get('session.hash_bits_per_character') < 5) $warnings[] = 'php.ini: session.hash_bits_per_character < 5';
    if(!ini_get('session.cookie_httponly')) $warnings[] = 'php.ini: session.cookie_httponly = Off';
    if(\Rsi\Http::secure() && !ini_get('session.cookie_secure')) $warnings[] = 'php.ini: session.cookie_secure = Off (HTTPS = On)';
    return $warnings;
  }
  /**
   *  Perform all security checks.
   *  @param array|bool $ignore  Checks to ignore (true = all).
   *  @return bool  True if all checks are fine.
   */
  public function check($ignore = null){
    if(!$this->banned){
      foreach($this->blacklist as $subnet) if(\Rsi\Http::inSubnet($subnet,$this->remoteAddr)) $this->_banned = true;
      if(!$this->_banned && ($ignore !== true)){
        foreach($this->whitelist as $subnet => $checks) if(\Rsi\Http::inSubnet($subnet,$this->remoteAddr))
          $ignore = array_merge($ignore ?: [],$checks);
        foreach($this->checks as $name => $check)
          if((!is_array($ignore) || !in_array($name,$ignore)) && !($result = $check->check())){
            $this->component('log')->notice("Suspicious $name request");
            if($result !== null) $this->addBan($name);
            return false;
          }
      }
      if($this->banned && ($warnings = $this->checkServer()))
        $this->component('log')->warning('Insecure server configuration',__FILE__,__LINE__,['warnings' => $warning]);
    }
    if($this->banned){
      usleep(rand(5000,10000));
      http_response_code(429); //Too Many Requests
      try{
        require($this->_fred->templatePath . 'banned.php');
      }
      catch(Exception $e){
        print('Banned');
      }
      $this->_fred->halt();
    }
    return true;
  }

  protected function getBanned(){
    if(($this->_banned === null) && (($this->_banned = $this->session->banned) === null)){
      if($this->_banned = file_exists($this->filename)) try{ //in case of error: banned (probably file access problems due to brute forcing)
        $reasons = [];
        $purged = false;
        foreach(file($this->filename) as $reason)
          if(substr($reason,0,strpos($reason,':')) >= time()) $reasons[] = $reason;
          else $purged = true;
        if($purged){
          if($reasons) file_put_contents($this->filename,implode($reasons));
          else unlink($this->filename);
        }
        $this->_banned = count($reasons) >= $this->banCount;
      }
      catch(Exception $e){
        if($this->_fred->debug) throw $e;
      }
      $this->session->banned = $this->_banned;
    }
    return $this->_banned;
  }

  protected function getChecks(){
    if($this->_checks === null){
      $this->_checks = [];
      foreach($this->config('checks',[]) as $name => $config){
        $class_name = \Rsi\Record::get($config,'className',__CLASS__ . '\\Check\\' . ucfirst($name));
        $this->_checks[$name] = new $class_name($this->_fred,$config);
      }
    }
    return $this->_checks;
  }

  protected function getFilename(){
    if(!$this->_filename) $this->_filename = $this->filename($this->remoteAddr);
    return $this->_filename;
  }

  protected function getPath(){
    if(!$this->_path) $this->_path = $this->config('path') ?: \Rsi\File::tempDir();
    return $this->_path;
  }

  protected function getRemoteAddr(){
    if($this->_remoteAddr === null){
      $this->_remoteAddr = \Rsi\Record::get($_SERVER,'REMOTE_ADDR');
      foreach($this->proxies as $subnet => $headers)
        if(\Rsi\Http::inSubnet($subnet,$this->_remoteAddr)) //applicable proxy
          foreach((is_array($headers) ? $headers : [$headers]) as $header)
            if(array_key_exists($header,$_SERVER)) foreach(explode(',',$_SERVER[$header]) as $ip)
              if(filter_var($ip = trim($ip),FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
                return $this->_remoteAddr = $ip;
    }
    return $this->_remoteAddr;
  }

}