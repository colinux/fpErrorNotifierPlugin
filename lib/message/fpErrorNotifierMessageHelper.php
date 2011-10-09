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
    return array(
      'class' => $e instanceof ErrorException?fpErrorNotifierErrorCode::getName($e->getSeverity()):get_class($e), 
      'code' => $e->getCode(), 'severity' => $e instanceof ErrorException?$e->getSeverity():'null', 
      'message' => $e->getMessage(), 'file' => "File: {$e->getFile()}, Line: {$e->getLine()}", 
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
    $uri = '';
    if (empty($_SERVER['HTTP_HOST'])) {
      if (!empty($_SERVER['argv'])) {
        $uri = implode(' ', $_SERVER['argv']);
      }
    } else {
      $uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    return array('subject' => $title, 'uri' => $uri, 'environment' => sfConfig::get('sf_environment', 'undefined'), 
      'module' => $context->getModuleName(), 'action' => $context->getActionName(), 
      'generated at' => date('H:i:s j F Y'));
  }

  /**
   * 
   * @return array
   */
  public function formatServer()
  {
    return array('server' => $this->dump($_SERVER), 'session' => isset($_SESSION)?$this->dump($_SESSION):null);
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
      $title .= ' ' . ucfirst(strtolower($part));
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
   * Dump variables. Recursion safe and humanized
   * 
   * @param mixed $value
   * 
   * @return string
   */
  public function dump(&$varInput, $var_name = '', $reference = '', $method = '=', $sub = false)
  {
    static $output;
    static $depth;
    if ($sub == false) {
      $output = '';
      $depth = 0;
      $reference = $var_name;
      $var = serialize($varInput);
      $var = unserialize($var);
    } else {
      ++$depth;
      $var = &$varInput;
    }
    // constants
    $nl = "\n";
    $block = 'a_big_recursion_protection_block';
    $c = $depth;
    $indent = '';
    while ($c-- > 0) {
      $indent .= '| ';
    }
    $namePrefix = $var_name?$var_name . ' ' . $method:'';
    // if this has been parsed before
    if (is_array($var) && isset($var[$block])) {
      $real = &$var[$block];
      $name = &$var['name'];
      $type = gettype($real);
      $output .= $indent . $namePrefix . '& ' . ($type == 'array'?'Array':get_class($real)) . ' ' . $name . $nl;
       // havent parsed this before
    } else {
      // insert recursion blocker
      $var = Array($block => $var, 'name' => $reference);
      $theVar = &$var[$block];
      // print it out
      $type = gettype($theVar);
      switch ($type) {
        case 'array':
          $output .= $indent . $namePrefix . ' Array (' . $nl;
          $keys = array_keys($theVar);
          foreach ($keys as $name) {
            $value = &$theVar[$name];
            $this->dump($value, $name, $reference . '["' . $name . '"]', '=', true);
          }
          $output .= $indent . ')' . $nl;
          break;
        case 'object':
          $output .= $indent . $namePrefix . get_class($theVar) . ' {' . $nl;
          foreach ($theVar as $name => $value) {
            $this->dump($value, $name, $reference . '=>' . $name, '=>', true);
          }
          $output .= $indent . '}' . $nl;
          break;
        case 'string':
          $output .= $indent . $namePrefix . ' "' . $theVar . '"' . $nl;
          break;
        default:
          $output .= $indent . $namePrefix . ' (' . $type . ') ' . $theVar . $nl;
          break;
      }
    }
    --$depth;
    return $output;
  }

  /**
   * 
   * @return fpErrorNotifier
   */
  protected function notifier()
  {
    return fpErrorNotifier::getInstance();
  }
}