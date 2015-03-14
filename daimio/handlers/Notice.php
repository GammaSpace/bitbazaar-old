<?php

/**
 * Stuff notices into the session (they disappear on logout: not for long-term storage)
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Notice
{
  
  /** 
  * Set a notice for the current user 
  * @param string A string or array
  * @return boolean 
  * @key __world
  */ 
  static function set($value)
  {
    $notice['value'] = $value;
    $notice['cron'] = date('Y-m-d H:i:s');
    SessionLib::append_session_value('NOTICES', $notice);
  }
  
  /** 
  * Get all the unseen notices
  * @return array 
  * @key __world
  */ 
  static function get_new()
  {
    $offset = $_SESSION['X']['notice_offset'];
    SessionLib::set_session_value('notice_offset', count($_SESSION['X']['NOTICES']));
    $return = array_slice($_SESSION['X']['NOTICES'], $offset);
    return $return ? $return : array();
  }
  
  
  /** 
  * Get all the notices  
  * @return array
  * @key __world
  */ 
  static function fetch()
  {
    return $_SESSION['X']['NOTICES'];
  }
  
  /** 
  * Throw a Daimio error, warning, or notice
  *
  * These are not the session notices the other commands in this handler throw -- they're internal Daimio values, the kind you get in the terminal in response to commands.
  *
  * @param string The string to throw
  * @param string Accepts (:error :warning :notice :up)
  * @param string A value to return (defaults to message)
  * @return boolean
  * @key __member __exec __lens
  */ 
  static function toss($message, $type='notice', $return=NULL)
  {
    switch ($type)
    {
      case 'time':
        list($micro, $seconds) = explode(' ', microtime());
        $time = $seconds + $micro;
        if($GLOBALS['X']['ETC']['notice_timing'])
          ErrorLib::log_array(array(substr(($time - $GLOBALS['X']['ETC']['notice_timing']), 0, 7), $message));
        $GLOBALS['X']['ETC']['notice_timing'] = $time;
        return false;
      break;
      
      case 'up':
        ErrorLib::log_array($message);
      break;
            
      case 'error':
        ErrorLib::set_error($message);
      break;
      
      case 'warning':
        ErrorLib::set_warning($message);
      break;
      
      case 'notice':
      default:
        ErrorLib::set_notice($message);
      break;
    }
    
    return isset($return) ? $return : $message;
  }

}
