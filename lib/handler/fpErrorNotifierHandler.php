<?php

/**
 *
 * @package    fpErrorNotifier
 * @subpackage handler 
 * 
 * @author     Maksim Kotlyar <mkotlar@ukr.net>
 * @author     Ton Sharp <Forma-PRO@66Ton99@gmail.com>
 */
class fpErrorNotifierHandler
{

  /**
   *
   * @var array
   */
  protected $options = array();

  /**
   *
   * @var string
   */
  protected $memoryReserv = '';

  /**
   *
   * @var sfEventDispatcher
   */
  protected $dispatcher;

  /**
   *
   * @var bool
   */
  protected $isInit = false;

  /**
   *
   * @param $options array
   *
   * @return void
   */
  public function __construct(sfEventDispatcher $dispatcher = null, array $options = array())
  {
    $this->dispatcher = $dispatcher;
    $this->options = array_merge($this->options, $options);
  }

  /**
   *
   * @return void
   */
  public function initialize()
  {
    if ($this->isInit || 'fpErrorNotifierDriverNull' == get_class($this->notifier()->driver())) return;
    $configs = sfConfig::get('sf_notify_driver');
    
    $this->memoryReserv = str_repeat('x', 1024 * 500);
    // Registers error handler and it will process the most part of erros (but not all)
    set_error_handler(array($this, 'handleError'));
    // Registers shutdown handler and it will process the rest part of errors
    register_shutdown_function(array($this, 'handleFatalError'));
    // It will do nothing if fpErrorNotifierDriverNull was set as a driver. Correctly error will not display.
    // See first line of method 
    set_exception_handler(array($this, 'handleException'));
    if ($dispather = $this->notifier()->dispather())
    {
      $dispather->connect('application.throw_exception', array($this, 'handleEvent'));
      $dispather->connect('notify.throw_exception', array($this, 'handleEvent'));
      $dispather->connect('notify.send_message', array($this, 'handleEventMessage'));
    }
    $this->isInit = true;
  }

  /**
   *
   * @param sfEvent $event
   *
   * @return void
   */
  public function handleEvent(sfEvent $event)
  {
    return $this->handleException($event->getSubject());
  }

  /**
   * Exception handler method
   * 
   * @todo Implement display exception mechanism
   *
   * @param Exception $e
   *
   * @return void
   */
  public function handleException(Exception $e)
  {
    
    $message = $this->notifier()->decoratedMessage($e->getMessage());
    $message->addSection('Exception', $this->notifier()->helper()->formatException($e));
    
    if (is_callable(array($e, "getPrevious")))
    {
      $count = 1;
      while ($previous = $e->getPrevious())
      {
        $message->addSection("Previous Exception #{$count}", $this->notifier()->helper()->formatException($previous));
        
        $e = $previous;
        if (!is_callable(array($e, "getPrevious"))) break;
        $count++;
      }
    }
    
    $message->addSection('Server', $this->notifier()->helper()->formatServer());

    if ($request = $this->notifier()->helper()->formatRequest()) {
      $message->addSection('Request', $request);
    }

    if (!empty($this->dispatcher)) $this->dispatcher->notify(new sfEvent($message, 'notify.decorate_exception'));
    $this->notifier()->driver()->notify($message);
  }

  /**
   * 
   *
   * @param sfEvent $event
   *
   * @return
   */
  public function handleEventMessage(sfEvent $event)
  {
    $message = $this->notifier() ->decoratedMessage($event->getSubject());
    $message->addSection('Message Details', $event->getParameters());
    $message->addSection('Server', $this->notifier()->helper()->formatServer());

    if ($request = $this->notifier()->helper()->formatRequest()) {
      $message->addSection('Request', $request);
    }

    $this->dispatcher->notify(new sfEvent($message, 'notify.decorate_message'));
    
    $this->notifier()->driver()->notify($message);
  }

  /**
   *
   * @param $errno string
   * @param $errstr string
   * @param $errfile string
   * @param $errline string
   *
   * @return bool
   */
  public function handleError($errno, $errstr, $errfile, $errline)
  {
    $this->handleException(new ErrorException($errstr, 0, $errno, $errfile, $errline));
    return false;
  }

  /**
   *
   * @return bool
   */
  public function handleFatalError()
  {
    $this->freeMemory();
    $error = error_get_last();
    $error = array_merge(array('type' => null, 'message' => null, 'file' => null, 'line' => null), (array)$error);
    if (!in_array($error['type'], fpErrorNotifierErrorCode::getFatals())) return false;
    $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
    return true;
  }

  /**
   *
   * @return void
   */
  protected function freeMemory()
  {
    unset($this->memoryReserv);
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