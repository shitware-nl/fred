<?php

namespace Rsi\Fred;

class Mail extends Component{

  public $defaultFrom = null;
  public $restrict = []; //!<  Regular expressions that the e-mail address has to match (at least one; empty = allow all).
  public $strict = []; //!<  Regular expressions for domains that can not be used in the From header.
  public $markup = ['b' => '*','i' => '/','u' => '_'];

  protected $_mailer = null;
  protected $_transport = null;
  protected $_imap = null;

  protected function init(){
    parent::init();
    $this->_fred->log->debug('Initializing Swift Mailer version ' . \Swift::VERSION,__FILE__,__LINE__); //initialize Swift Mailer autoloader
  }

  public function filterRecipients($recipients){
    if(!$this->restrict) return $recipients;
    $allowed = [];
    foreach(\Rsi\Record::explode($recipients) as $key => $value){
      $email = is_numeric($key) ? $value : $key;
      foreach($this->restrict as $filter)
        if(preg_match(substr($filter,0,1) == '/' ? $filter : '/' . preg_quote($filter,'/') . '/',$email)){
          $allowed[$key] = $value;
          break;
        }
    }
    return $allowed;
  }
  /**
   *  Remove all tags from the message body.
   *  Link addresses are placed between parenthesis after the original anchor text.
   *  @param string $body  Message in HTML format.
   *  @return string  Message in plain text.
   */
  public function stripTags($body){
    foreach($this->markup as $tag => $char) $body = preg_replace("/(<$tag.*?>|<\\/$tag>)/",$char,$body);
    if(preg_match_all('/<a.*?href\s*=\s*([^\s>]+).*?>(.*?)<\\/a>/',$body,$matches,PREG_SET_ORDER))
      foreach($matches as list($full,$link,$descr)){
        $link = \Rsi\Str::StripQuotes($link);
        $body = str_replace($full,$descr . ($link == $descr ? '' : " ($link)"),$body);
      }
    return strip_tags($body);
  }
  /**
   *  Create a new Swift Mailer message object.
   *  @param mixed $from  Sender address (defaultFrom or ini sendmail_from when empty).
   *  @param mixed $to  Recipient(s).
   *  @param string $subject  Subject line.
   *  @param string $body  Message body.
   *  @param bool $html  True when the message body is in HTML format.
   */
  public function message($from = null,$to = null,$subject = null,$body = null,$html = false){
    $message = new \Swift_Message();
    if($from) foreach($this->strict as $filter)
      if(preg_match(substr($filter,0,1) == '/' ? $filter : '/' . preg_quote($filter,'/') . '/',$from)){
        $message->setReplyTo($from);
        $from = null;
        break;
      }
    $message->setFrom($from ?: ($this->defaultFrom ?: ini_get('sendmail_from')));
    $message->setTo($to);
    if($subject) $message->setSubject($subject);
    if($body){
      if($html) $message->setBody($body,'text/html')->addPart($this->stripTags($body),'text/plain');
      else $message->setBody($body);
    }
    return $message;
  }
  /**
   *  Send a message.
   *  If the message is not an object, it is created from all the parameters.
   *  @see message()
   *  @param \Swift_Message $message
   */
  public function send($message){
    if(!is_object($message)) $message = call_user_func_array([$this,'message'],func_get_args());
    $message->setTo($this->filterRecipients($message->getTo()));
    $message->setCc($this->filterRecipients($message->getCc()));
    $message->setBcc($this->filterRecipients($message->getBcc()));
    return $this->transport->send($message);
  }
  /**
   *  Open an IMAP mailbox.
   *  @param string $name  Mailbox to connect to (empty = default).
   *  @return \\Rsi\\Imap\\Mailbox
   */
  public function box($name = null){
    return $this->imap->mailbox($name);
  }

  protected function getImap(){
    if(!$this->_imap){
      $imap = $this->config('imap');
      $this->_imap = new \Rsi\Imap(
        \Rsi\Record::get($imap,'host'),
        \Rsi\Record::get($imap,'username'),
        \Rsi\Record::get($imap,'password'),
        \Rsi\Record::get($imap,'options'),
        \Rsi\Record::get($imap,'port')
      );
    }
    return $this->_imap;
  }

  protected function getMailer(){
    if(!$this->_mailer) $this->_mailer = new \Swift_Mailer($this->transport);
    return $this->_mailer;
  }

  protected function getTransport(){
    if(!$this->_transport){
      if($smtp = $this->config('smtp')){
        if($host = \Rsi\Record::get($smtp,'host')) $port = \Rsi\Record::get($smtp,'port',25);
        else{
          $host = ini_get('SMTP');
          $port = ini_get('smtp_port');
        }
        $this->_transport = new \Swift_SmtpTransport($host,$port);
        if($username = \Rsi\Record::get($smtp,'username')) $this->_transport->setUsername($username);
        if($password = \Rsi\Record::get($smtp,'password')) $this->_transport->setPassword($password);
      }
      else $this->_transport = new \Swift_MailTransport();
    }
    return $this->_transport;
  }

  public function __invoke($from = null,$to = null,$subject = null,$body = null,$html = false){
    return $this->send($from,$to,$subject,$body,$html);
  }

}