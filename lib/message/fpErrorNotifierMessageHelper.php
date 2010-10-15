<?php

/**
 *
 * @package    fpErrorNotifier
 * @subpackage message 
 * 
 * @author     Maksim Kotlyar <mkotlar@ukr.net>
 */
class fpErrorNotifierMessageHelper
{
  /**
   * 
   * @param Exception $e
   * 
   * @return array
   */
  public function formatException(Exception $e)
  {
    $code = $e->getCode();
    if (empty($code) && $e instanceof ErrorException) {
      $code = $e->getSeverity();
    }
    return array(
      'class' => get_class($e),
      'code' =>  $this->getNameOfErrorByCode($code),
      'message' => $e->getMessage(),
      'file' => "{$e->getFile()}, Line: {$e->getLine()}",
      'trace' => $e->getTraceAsString());
  }
  
  /**
   * 
   * @param string $title
   * 
   * @return array
   */
  public function formatSummary($title)
  {
    $context = $this->notifier()->context();
    if (empty($_SERVER['HTTP_HOST'])) {
      $uri = implode(' ', $_SERVER['argv']);
    } else {
      $uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    return array(
      'subject' => $title,
      'uri' => $uri,
      'environment' => sfConfig::get('sf_environment', 'undefined'),
      'module' => $context->getModuleName(),
      'action' => $context->getActionName(),
      'generated at' => date('H:i:s j F Y')
    );
  }
  
  /**
   * 
   * @return array
   */
  public function formatServer()
  {
    return array(
      'server' => $this->dump($_SERVER),
      'session' => $this->dump(isset($_SESSION) ? $_SESSION : null));
  }
  
  /**
   * 
   * @return string
   */
  public function formatSubject($title)
  {
    $env = sfConfig::get('sf_environment', 'undefined');
    
    return "Notification: {$env} - {$title}";
  }
  
  /**
   * 
   * @param string $title
   * 
   * @return string
   */
  public function formatTitle($title)
  {
    $titleArr = trim(str_replace(array('_', '-'), ' ', $title));
    $titleArr = array_filter(explode(' ', $titleArr));

    $title = '';
    foreach ($titleArr as $part) {
      $title .= ' '.ucfirst(strtolower($part));
    }
    
    return trim($title);
  }
  
  /**
   * 
   * @param mixed $value
   * 
   * @return string
   */
  public function formatValue($value)
  {
    is_string($value) || $value = $this->dump($value);
    
    return nl2br(htmlspecialchars($value));
  }
  
  /**
   * 
   * @param mixed $value
   * 
   * @return string
   */
  public function dump($value)
  {
    return var_export($value, true);
  }
  
  /**
   * 
   * @return fpErrorNotifier
   */
  protected function notifier()
  {
    return fpErrorNotifier::getInstance();
  }
  
  protected function getNameOfErrorByCode($code)
  {
    switch ($code) {
      case 1:
        return 'E_ERROR';
      case 4096:
        return 'E_RECOVERABLE_ERROR';
      case 2:
        return 'E_WARNING';
      case 4:
        return 'E_PARSE';
      case 8:
        return 'E_NOTICE';
      case 2048:
        return 'E_STRICT';
      case 16:
        return 'E_CORE_ERROR';
      case 32:
        return 'E_CORE_WARNING';
      case 64:
        return 'E_COMPILE_ERROR';
      case 128:
        return 'E_COMPILE_WARNING';
      case 256:
        return 'E_USER_ERROR';
      case 512:
        return 'E_USER_WARNING';
      case 1024:
        return 'E_USER_NOTICE';
      case 6143:
        return 'E_ALL';
    }
    return empty($code)?'NONE':$code;
  }
  
}