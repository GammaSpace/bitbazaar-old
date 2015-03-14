<?php

/**
 * Manage string, array, and local variables
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Variable
{
  
  /** 
  * Create a new variable or replace the value of an existing one
  * 
  * Static variables are set exclusively by the user. You'll almost always be setting this type of variable.
  * You can set a static variables like {:betsy | > :cow} 
  * and reference it like {@cow}.
  * 
  * Local variables are set by the user and automatically by various functions. They're temporary: a local variable can change meaning at any time, without warning. You should only set them when there's a good reason.
  * You can set a local variable like {:stoat | > :LOCAL.ermine} 
  * and reference it like {ermine}.
  * 
  * @example {(:cow :duck :ermine) | > :pets}
  * @example {variable set path :pets value (:cow :duck :ermine)}
  * 
  * @example {@pets | array count | > :LOCAL.number}
  * @example {variable set path :LOCAL.number value {@pets | array count}}
  * 
  * @example {"daffy duck" | > :pets.1}
  * @example {var set path :pets value [["dogs","cats","rats","bats"]]}
  * @example {({* (:name "Alpha co" :employees (:Archibald :Amelia))} {* (:name "Beta co" :employees (:Miles :Elliot :Petunia))}) | > :companies}
  * 
  * @param mixed Variable path
  * @param mixed Variable value
  * @return mixed
  * @key __world
  */ 
  static function set($path, $value)
  {
    if(strpos($path, "{") !== false)
      $path = Processor::process_string($path);
    
    $keys = explode('.', $path);
    $head = reset($keys);
    
    if(!in_array($head, array('STATIC', 'LOCAL')))
      $head = 'STATIC';
    else
      $head = array_shift($keys);
    
    if(!count($keys))
      return ErrorLib::set_error("No legal keys were sent");
    
    $GLOBALS['X']['VARS'][$head] = Processor::recursive_insert($GLOBALS['X']['VARS'][$head], $keys, $value);
    
    return $value;
  }
  
  /** 
  * Append a value to a variable
  * 
  * Works like {variable set}, except the input value is modified under certain circumstances. Given a path of old_value and a value parameter called new_value, then:
  * if old_value doesn't exist, new_value becomes the first child of a new array;
  * if old_value and new_value are strings, new_value is prepended with old_value;
  * if old_value is an array, new_value becomes its last child
  * 
  * You can use >> as a shortcut for this command.
  * 
  * @example {:canary | >> :pets}
  * @example {:giraffe | >> :pets}
  * @example {@pets | array count | >> :LOCAL.number}
  * 
  * @example {"How to Train Your " | > :title}
  * @example {"Zebra" | >> :title}
  * 
  * @param mixed Variable path
  * @param mixed Variable value
  * @return mixed 
  * @key __world
  */ 
  static function append($path, $value)
  {
    if(strpos($path, '{') !== false)
      $path = Processor::process_string($path);
    
    $keys = explode('.', $path);
    $head = reset($keys);
    
    if(!in_array($head, array('STATIC', 'LOCAL')))
      $head = 'STATIC';
    else
      $head = array_shift($keys);
    
    if(!count($keys))
      return ErrorLib::set_error("No legal keys were sent");
    
    // appender
    $old_value = Processor::get_nested_param_value(implode('.', $keys), $GLOBALS['X']['VARS'][$head]);
    if($old_value === false || $old_value === NULL)
    {
      $value = array($value);
    }
    elseif(is_string($old_value))
    {
      $old_value .= (string) $value;
      $value = $old_value;      
    }
    elseif(is_array($old_value))
    {
      $old_value[] = $value;
      $value = $old_value;      
    }
        
    $GLOBALS['X']['VARS'][$head] = Processor::recursive_insert($GLOBALS['X']['VARS'][$head], $keys, $value);
    
    return $value;
  }
    
}

// EOT