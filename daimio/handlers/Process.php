<?php

/**
 * Handle data merging and substitutions
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Process 
{

  /**
  * Process a copy of "string" for each element of "with" (an array of hashes)  
  * @param string Template to apply each hash to
  * @param array The array over which to process
  * @param string Alias for local variable 
  * @param string String that holds the results together
  * @return string 
  * @key __world
  */ 
  static function merge($string, $with, $as=NULL, $glue=NULL)
  {
    return Processor::merge($string, $with, $as, $glue);
  }
  
  /**
  * Process a copy of "string" for each key ("and") and value ("as") in "with" (a hash)
  * @param string Template to apply each hash to
  * @param array The array over which to process
  * @param string Alias for value
  * @param string Alias for key
  * @param string String that holds the results together
  * @return string
  * @key __world
  */ 
  static function for_each($string, $with, $as=NULL, $and=NULL, $glue=NULL)
  {
    if(!$as) {$as = 'value';}
    if(!$and) {$and = 'key';}
    
    if(!is_array($with) && $GLOBALS['X']['SETTINGS']['log_warnings']) {
      return ErrorLib::set_warning("No with array supplied for for_each");
    }
        
    foreach($with as $key => $value)
    {
      $local_array = array($and => $key, $as => $value, $key => $value);
      $new_string_array[] = Processor::process_with_data($string, $local_array);
    }
    
    return join($new_string_array, $glue);
  }
  
  /**
  * Replaces {with} in :string as :as, possibly copying string
  * Behavior varies based on the nature of the 'with' param:
  * false: return string
  * string: requires 'as' param; replace '{as}' with 'with'
  * hash: given key and value from hash, replace '{key}' with 'value'
  * array: requires 'as' param; replace '{as}' with each array value and glue the resulting strings together
  * array of hashes: process each hash independently, then glue the strings together
  * 
  * @example {:pizza | sub string "I like {food}" as :food}
  * @example {(:jolt :brio :chinotto) | sub string "{soda}" as :soda glue " and " | ("I drink" {__}) | string join with " "}
  * @example {* (:pet :dog :name :Balto :color :brown) | sub string "My {pet} {name} is {color}"}
  * @example {({* (:child :Alice :pet :alpaca)} {* (:child :Bob :pet :barricuda)} {* (:child :Chuck :pet :cougar)} {* (:child :Dave :pet :dinosaur)} {* (:child :Eve :pet :elephant)}) | sub string "{child} has a {pet}" glue ", and "}
  * 
  * @param string Base string
  * @param string Replacement string or array or hash or AoH
  * @param string Search string or alias for with
  * @param string String that holds the results together
  * @return string 
  * @key __world
  */ 
  static function substitute($string, $with, $as=NULL, $glue=NULL)
  {
    if(!$with) // no with, return string
    {
      return $string;
    }
    
    elseif(is_string($with)) // $with is string
    {
      if(!is_string($as))
        return ErrorLib::set_error("Parameter 'as' is not a valid needle");

      return str_replace('{' . $as . '}', $with, $string);
    }
    
    elseif(!is_array($with)) // $with is invalid (int, object, etc)
    {
      return ErrorLib::set_error("Invalid 'with' parameter");
    }
    
    elseif(is_array(reset($with))) // $with is AoH
    {
      foreach($with as $hash)
      {
        $new_string = $string;
        foreach($hash as $key => $value)
        {
          if($as) {$key = "$as.$key";}
          $new_string = str_replace('{' . $key . '}', $value, $new_string);
        }
        $strings[] = $new_string;
      }
      
      return join($strings, $glue);      
    }
    
    elseif(array_values($with) != $with) // $with is hash
    {
      foreach($with as $key => $value)
      {
        if($as) {$key = "$as.$key";}
        $string = str_replace('{' . $key . '}', $value, $string);
      }

      return $string;
    }
    
    else // $with is plain array
    {
      if(!is_string($as))
        return ErrorLib::set_error("Parameter 'as' is not a valid needle");
      
      foreach($with as $value)
        $strings[] = str_replace('{' . $as . '}', $value, $string);
      
      return join($strings, $glue);
    }
  }
  
  /**
  * Entirely process a string
  * @param string String to process
  * @return string 
  * @key __world
  */ 
  static function consume($string)
  {
    return Processor::process_string($string, 'arrayable');
  }
  
  /** 
  * Process a string as a param 
  * @param string 
  * @return mixed 
  * @key __world
  */ 
  static function as_param($string)
  {
    return Processor::param_parser($string);
  }
  
  
  /**
  * Escape curly braces in a string  
  * @param string String to escape
  * @return string 
  * @key __world
  */ 
  static function escape($string)
  {
    return Processor::escape_braces($string);
  }
  
  /**
  * Unescape curly braces in a string  
  * @param string String to unescape
  * @return string 
  * @key __world
  */ 
  static function unescape($string)
  {
    return Processor::unescape_braces($string);
  }
  
  /** 
  * Get an array of all the active shortcuts  
  * @return array 
  * @key __world
  */ 
  static function get_shortcuts()
  {
    return $GLOBALS['X']['SETTINGS']['shortcuts'];
  }
  
  
  /**
  * An array of all commands accessible by the user
  * @return string 
  * @key __world
  */ 
  static function get_commands()
  {
    return Processor::get_commands();
  }
  
}


// EOT