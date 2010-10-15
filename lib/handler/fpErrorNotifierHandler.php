<?php

/**
 * Unwind the error handler stack until we're back at the built-in error handler.
 */
function unset_error_handler() {
  while (set_error_handler(create_function('$errno,$errstr', 'return false;'))) {
    // Unset the error handler we just set.
    restore_error_handler();
    // Unset the previous error handler.
    restore_error_handler();
  }
  // Restore the built-in error handler.
  restore_error_handler();
}

/**
 *
 * @package    fpErrorNotifier
 * @subpackage handler 
 * 
 * @author     Maksim Kotlyar <mkotlar@ukr.net>
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
  
  protected $specificErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR); 
  
  /**
   * 
   * @param array $options
   * 
   * @return void
   */
  public function __construct(array $options = array())
  {
    $this->options = array_merge($this->options, $options);
  }
  
  /**
   * 
   * @return void
   */
  public function initialize()
  {
    $configs = sfConfig::get('sf_notify_driver');
    if (empty($configs['class']) || (isset($configs['class']) && 'fpErrorNotifierDriverNull' == $configs['class'])) {
      unset_error_handler();
      return false;
    }
    // Prevent blocking of error reporting, becuse of @ - error-control operator.
    if (0 == error_reporting()) @error_reporting(-2);
    $this->memoryReserv = str_repeat('x', 1024 * 500);
    
    // Register error handler it will process most of erros but not all
    set_error_handler(array($this, 'errorHandler'), -1);
    set_exception_handler(array($this, 'handleException'));
    restore_exception_handler();
    // Register shutdown handler it will process other not proced errors 
    register_shutdown_function(array($this, 'handleFatalError'));
    
    $dispather = $this->notifier()->dispather();
    $dispather->connect('application.throw_exception', array($this, 'handleEvent'));
    return true;
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
   * 
   * @param Exception $e
   * 
   * @return void
   */
  public function handleException(Exception $e)
  {
    $message = $this->notifier()->decoratedMessage($e->getMessage());    
    $message->addSection('Exception', $this->notifier()->helper()->formatException($e));
    $message->addSection('Server', $this->notifier()->helper()->formatServer());
    
    $this->notifier()->driver()->notify($message);
  }
  
  /**
   * 
   * @param string $errno
   * @param string $errstr
   * @param string $errfile
   * @param string $errline
   * @param array $errcontext
   * 
   * @return ErrorException
   */
  public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
  {
    // Set becvause of @ error-control operator.
    if (0 == error_reporting()) return;
    $this->handleError($errno, $errstr, $errfile, $errline);
  }
  

	/**
	 * 
	 * @param string $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param string $errline
	 * 
	 * @return ErrorException
	 */
	public function handleError($errno, $errstr, $errfile, $errline)
	{
    $error = new ErrorException($errstr, 0, $errno, $errfile, $errline);
    
	  $this->handleException($error);
	  return $error;
	}

  /**
   * 
   * @return void
   */
  public function handleFatalError()
  {
    $error = error_get_last();
    if (empty($error) || 
        empty($error['type']) || 
        !in_array($error['type'], $this->specificErrors)) return;

    $this->freeMemory();
    
    $error = $this->handleError(@$error['type'], @$error['message'], @$error['file'], @$error['line']);
    
    $sfE = new sfException();
    $sfE->setWrappedException($error);
    $sfE->printStackTrace();
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