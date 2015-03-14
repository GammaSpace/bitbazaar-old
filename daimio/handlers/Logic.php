<?php

/**
 * Handle Logic
 * 
 * Certain method names are illegal in PHP (and hence in Daimio). We preface these with 'daimio_'. The Logic handler is particularly prone to this, but all of the affected commands have shortcuts. The cumbersome 'logic daimio_if' can simply be replaced with 'if', for example. 
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Logic 
{  
    
  /**
  * Return the "then" param if "condition" is true, else "else"
  * @param string True if it has elements or characters (empty strings and empty arrays are false, zero is not)
  * @param string Returned if true (remember to quote commands)
  * @param string Returned if false (remember to quote commands)
  * @return string 
  * @key __world
  */ 
  static function daimio_if($condition, $then=NULL, $else=NULL)
  {
    $value = Processor::is_true($condition) ? $then : $else;

    return $value;
  }
  
  /**
  * If all conditions are true, return true  
  * @param mixed An array (or list) of conditions
  * @return boolean 
  * @key __world
  */ 
  static function daimio_and($conditions)
  {
    if(!is_array($conditions))
      $conditions = array($conditions); // NOTE: can't shorten this with an (array) cast because it turns false into array('0' => false)... which is *not* the empty array.
    
    foreach($conditions as $condition)
      if(!Processor::is_true($condition))
        return false;
      
    return true;
  }
  
  /**
  * If any condition is true, return true
  * @param mixed An array (or list) of conditions
  * @return boolean 
  * @key __world
  */ 
  static function daimio_or($conditions)
  {
    if(!is_array($conditions))
      $conditions = array($conditions);
      
    foreach($conditions as $condition)
      if(Processor::is_true($condition))
        return true;
    
    return false;
  }
  
  /**
  * If value is true, return false
  * @param string Flips it!
  * @return boolean 
  * @key __world
  */ 
  static function daimio_not($value=NULL)
  {
    return !Processor::is_true($value);
  }
  
  /**
  * If value is in in or like like, return true  
  * @param string Value to compare
  * @param string Array of potential matches
  * @param string A regular expression for matching (if no // wrapping defaults to equal)
  * @return boolean 
  * @key __world
  */ 
  static function is($value, $in=NULL, $like=NULL)
  {
    if($value && !is_array($in) && !$like)
      return strpos($in, $value) !== false;
    
    if(is_array($in) && !in_array($value, $in))
      return false;
    
    if($like !== NULL)
    {
      if(strpos((string) $like, '/') !== 0)
      {
        if((string) $like !== (string) $value)
        {          
          return false;
        }
      }
      elseif(!preg_match($like, $value))
      {        
        return false;
      }
    }
    
    return true;
  }
  
  /**
  * If "value" is less than "than", return true
  * @param string First condition
  * @param string Second condition
  * @return boolean 
  * @key __world
  */ 
  static function less($value, $than)
  {
    if(is_array($value))
      $value = count($value);
    
    if(is_array($than))
      $than = count($than);
    
    return $value < $than ? true : false;
  }
  
  
  /** 
  * Process the first action whose condition is true
  * Everything in the list gets processed before delivery, so there's no short-circuiting. Wrap your actions with quotes if you don't want them activating willy-nilly.
  * @param string An array of conditions and actions, like (false "{blog destroy}" :true "{blog publish}")
  * @return string 
  * @key __world
  */ 
  static function ifelse($values)
  {
    do {
      $condition = array_shift($values);
      $action = array_shift($values);
      if($condition)
        return Processor::process_string($action);
    } while($values);
  }  
  
}


// EOT