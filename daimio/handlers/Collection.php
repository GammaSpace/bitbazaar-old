<?php

/** 
 * An extensible collection of commands for creating and modifying arrays and hashes
 *
 * @package collection
 * @author dann toliver (except where otherwise noted)
 */

class Collection
{

  // This is an auto-generated file! DO NOT EDIT!!
  // Edits will be destroyed by the next {daimio freshen_commands} call.
  // Files in daimio/tools/$name will be copied into here as available methods.
  
  /** 
  * Returns the number of values in an array 
  * @param array Array to count
  * @return int 
  * @key __world
  */
  static function count($value)
  {
    if(!is_array($value))
      return 0;
      
    return count($value);
  }
  
  /** 
  * Get elements from the first array that aren't in at least one of the other arrays 
  * @param string A list of arrays
  * @return array 
  * @key __world
  */ 
  static function diff($values)
  {
    foreach($values as $index => $array)
      $values[$index] = array_map("serialize", $array);
    
    $intersect = call_user_func_array('array_diff', $values);
    return array_map("unserialize", $intersect);
  }

  /** 
  * All the values you want, none of the ones you don't
  * @param array The main array
  * @param array Expression to select over ('this' and 'parent' (and 'parent.parent', etc) are set as local vars)
  * @param array Leave blank to select the root elements, use a path to select branches
  * @return array 
  * @key __world
  */
  static function extract($value, $expression, $path=NULL)
  {
    // parse path
    if(strpos($path, "{") !== false)
      $path = Processor::process_string($path);
  
    if($path)
      $parts = explode('.', $path);
  
    unset($GLOBALS['X']['ETC']['extract_return']);
    self::recursive_extract($value, $expression, $value, $parts);
    return $GLOBALS['X']['ETC']['extract_return'];
  }


  private static function recursive_extract($value, $expression, $parent, $parts)
  {
    // no parts, value is a string?
    if(!$parts && !is_array($value))
        return self::run_extract_expression($value, $expression, $parent);
  
    // we need parents
    $this_parent = $value;
    $this_parent['parent'] = $parent;
      
    // no parts, value is array?
    if(!$parts) {
      foreach($value as $key => $this_value) {
        $valid = self::run_extract_expression($this_value, $expression, $this_parent);
        if($valid !== NULL)
          $GLOBALS['X']['ETC']['extract_return'][] = $this_value;
      }
    }

    // get some parts
    $part = array_shift($parts);
  
    // catchall part
    if($part == '*') {
      foreach($value as $key => $this_value)
        self::recursive_extract($this_value, $expression, $this_parent, $parts);
    }
    // standard part
    else {
      self::recursive_extract($value[$part], $expression, $this_parent, $parts);
    }
  }


  private static function run_extract_expression($value, $expression, $parent)
  {
    $GLOBALS['X']['VARS']['LOCAL']['this'] = $value;
    $GLOBALS['X']['VARS']['LOCAL']['parent'] = $parent;
    
    $valid = Processor::process_string($expression, 'arrayable');
  
    if($valid)
      return $value;
    else
      return NULL;
  }

  /** 
  * Filter an array by various things, returning a subset of elements
  * 
  * This is a weaker, faster version of {collection extract}. You can only filter over the top level; there's no pathing. Use this if you need simple filtering.
  * 
  * @param array Array to filter
  * @param array A key substring (like :fish) or regex (like "/fish/") -- those two are equivalent
  * @return int 
  * @key __world
  */
  static function filter($value, $by_key=NULL)
  {
    if(!is_array($value))
      return array();

    // to only set keys for keyed arrays, use:
    // array_values($value) !== $value
    
    if(isset($by_key)) {
      if(is_array($by_key)) {
        foreach($by_key as $key) {
          if(isset($value[$key])) {
            $new_value[$key] = $value[$key];
          }
        }
      } 
      else {
        $by_key = (string) $by_key;
        foreach($value as $k => $v) {
          if(strpos($by_key, '/') !== 0) { // plain substring
            if(strpos((string) $k, $by_key) === false) {
              continue;
            }
          }
          elseif(!preg_match($by_key, (string) $k)) {        
            continue;
          }
          $new_value[$k] = $v;
        }      
      }
      
      $value = $new_value;        
    }
    
    return $value;
  }
  
  
  /** 
  * Returns the values of a multidimensional array as a 1D array
  * @param array Array to flatten
  * @return array 
  * @key __world
  */
  static function flatten($value)
  {
    return ErrorLib::flatten_array($value);
  }
  
  /**
  * Create an array from a CSV formatted string (requires PHP > 5.3)
  * @param string A CSV formatted string
  * @return array
  * @key __world
  */
  static function from_csv($value)
  {
    $lines = explode("\n", $value);
    
    foreach($lines as $line)
      $array[] = str_getcsv($line);
      
    return $array;
  }

  /**
  * Create array from a JSON formatted string
  * @param string A JSON formatted string 
  * @return array 
  * @key __world
  */
  static function from_json($value)
  {
    $value = "$value";
    
    // THINK: allow escaped braces?
    return json_decode($value, true);
  }
  /** 
  * Intersect some values 
  * @param string A list of arrays
  * @return array 
  * @key __world
  */ 
  static function intersect($values)
  {
    foreach($values as $index => $array)
      $values[$index] = array_map("serialize", $array);
    
    $intersect = call_user_func_array('array_intersect', $values);
    return array_map("unserialize", $intersect);
  }
  
  /** 
  * Get the array keys 
  * @param array An array
  * @return array 
  * @key __world
  */ 
  static function keys($value)
  {
    return array_keys($value);
  }
  
  
  
  //
  // This is really old code, and needs to be more DAMLish before it's truly useful
  //
  
  
  /** 
  * Provide a command string to sort and stack your data (experimental)
  * @param string Array to process
  * @param string The command string is like "sort:cron,asc;stack:cron,M;stack:cron,D;stack:ranks;"
  * @return string 
  * @key __world
  */
  static function organize($value, $command)
  {
    $commands = explode(';', $command);
    return self::organize_data($value, $commands);
  }
  
  
  //
  // Private functions for organize
  //
  
  
  /** 
  * Organize your data
  * @param array
  * @param string 
  * @return array 
  */ 
  private function organize_data($data, $commands)
  {
    while(count($commands))
    {
      $command_string = array_shift($commands);
      list($command, $mixed) = explode(':', $command_string);
      list($column, $arg) = explode(',', $mixed);
      
      if($command == 'sort')
      {
        $sort_array = array(array($column, $arg));
        $data = self::sort_data($data, $sort_array, $commands);
      }
      if($command == 'stack')
      {
        $data = self::stack_data($data, $column, $arg, $commands);
        unset($commands);
      }
    }
    
    return $data;
  }
  
  /**
  * sort an array by a column
  * @param array 
  * @param array 
  * @param array 
  * @return array 
  */ 
  private function sort_data($data, $sort_array, &$commands)
  {
    // peek ahead to see if we need to grab another command
    do {
      $command_string = reset($commands);
      list($command, $mixed) = explode(':', $command_string);
      list($column, $arg) = explode(',', $mixed);
      if($command == 'sort')
      {
        $sort_array[] = array($column, $arg);
        array_shift($commands);
      }
    } while($command == 'sort');
    
    foreach($sort_array as $sortee)
    {
      list($column, $arg) = $sortee;
      foreach($data as $key => $datum)
      {
        if($time = strtotime($datum[$column])) // strtotime for proper date sorting
          $cols[$key] = $time;
        else
          $cols[$key] = strtolower($datum[$column]); // strtolower for case-insensitive
      }
      
      $sort_order = ($arg == 'desc') ? 'SORT_DESC' : 'SORT_ASC';
      $params[] = $cols;
      $params[] = constant($sort_order);
      unset($cols);
    }
    
    if(is_array($params) && count($params))
    {
      $temp = $data; // this is for call_user_func_array's weird reference passing (borked in PHP 5.2+)
      $params[] = $temp;
      $data =& $params[count($params) - 1];
      call_user_func_array('array_multisort', $params);
    }
    
    return $data;
  }

  
  /** 
  * stack the data
  * @param array
  * @param string
  * @param string
  * @param array 
  * @return array 
  */ 
  private function stack_data($data, $column, $arg, $commands)
  {
    foreach($data as $key => $datum)
    {
      $value = $arg ? Time::represent($datum[$column], $arg) : $datum[$column];
      
      // THINK: this really should intersect the arrays so it groups all equal-valued sets, irrespective of order. (maybe serialize it and then... unserialize all of them and compare them? that's horrible.)
      $key_value = is_array($value) ? json_encode($value) : $value;
      $key_value = $key_value ? $key_value : 0;
      $temp[$key_value]['count']++;
      $temp[$key_value]['column'] = $column;
      $temp[$key_value]['value'] = $value;
      $temp[$key_value]['items'][] = $datum;
    }
    
    $data = $temp;
    
    if(count($commands))
    {
      foreach($data as $key => $datum)
      {
        $data[$key]['items'] = self::organize_data($datum['items'], $commands);
      }
    }
    
    return $data;
  }
  
  
  //
  // End organize
  //
    
  /**
  * Convert the values in a list into a hash of key-value pairs
  * @param string The list to convert
  * @return string
  * @key __world
  */
  static function pair($value)
  {
    if(!is_array($value)) {
      ErrorLib::set_warning('The value parameter must be an array');
      return array();
    }
    
    if(count($value) < 2) {
      ErrorLib::set_warning('The value parameter must contain at least two elements');
      return array();
    }
    
    while(count($value) > 1) 
    {
      $k = array_shift($value);
      $v = array_shift($value);
      $hash[$k] = $v;
    }
    
    return $hash;
  }
  

  /** 
  * Filter the values in an array 
  * @param array The main array
  * @param array Expression to filter over ('this' and 'parent' (and 'parent.parent', etc) are set as local vars)
  * @param array Leave blank to filter the root elements, use a path to filter branches
  * @return array 
  * @key __world
  */
  static function prune($value, $expression, $path=NULL)
  {
    // parse path
    if(strpos($path, "{") !== false)
      $path = Processor::process_string($path);
  
    if($path)
      $parts = explode('.', $path);
  
    return self::recursive_prune($value, $expression, $value, $parts);
  }


  private static function recursive_prune($value, $expression, $parent, $parts)
  {
    // no parts, value is a string?
    if(!$parts && !is_array($value))
        return self::run_expression($value, $expression, $parent);
  
    // we need parents
    $this_parent = $value;
    $this_parent['parent'] = $parent;
      
    // no parts, value is array?
    if(!$parts) {
      foreach($value as $key => $this_value) {
        $valid = self::run_expression($this_value, $expression, $this_parent);
        if($valid === NULL)
          unset($value[$key]);
      }
      return $value;
    }

    // get some parts
    $part = array_shift($parts);
  
    // catchall part
    if($part == '*') {
      foreach($value as $key => $this_value) {
        $new_this = self::recursive_prune($this_value, $expression, $this_parent, $parts);
        if($new_this === NULL)
          unset($value[$key]);
        else
          $value[$key] = $new_this;
      }
    }
    // standard part
    else {
      $value = self::recursive_prune($value[$part], $expression, $this_parent, $parts);
    }
  
    return $value;
  }


  private static function run_expression($value, $expression, $parent)
  {
    $GLOBALS['X']['VARS']['LOCAL']['this'] = $value;
    $GLOBALS['X']['VARS']['LOCAL']['parent'] = $parent;
  
    $valid = Processor::process_string($expression, 'arrayable');
  
    if(!$valid)
      return $value;
    else
      return NULL;
  }
  
  /** 
  * Returns a random element 
  * @param array Array to select an element from
  * @return array
  * @key __world
  */
  static function random($value)
  {
    if(!is_array($value))
      return false;
    
    $rand_key = array_rand($value);
    
    return $value[$rand_key];
  }
  
  
  /** 
  * Returns an array of generated values 
  * @param array First value (can be an integer or letter)
  * @param array Limit value
  * @param array Positive integer, defaults to one
  * @return int 
  * @key __world
  */
  static function range($start, $limit, $step=NULL)
  {
    $step = ($step && ctype_digit((string) $step)) ? intval($step) : 1;
      
    return range($start, $limit, $step);
  }
  
  /** 
  * Rekey a bundle 
  * @param string A bundle
  * @param string A hash key
  * @return array 
  * @key __world
  */ 
  static function rekey($value, $by)
  {
    if(!$value || !is_array($value))
      return array();
    
    $temp_value = array();
    foreach($value as $item) {
      $key = (string) Processor::get_nested_param_value($by, $item);
      $temp_value[$key][] = $item;
    }
    
    return $temp_value;
  }
  

  /** 
  * Remove the member of array indexed by key
  * @param array Array to process
  * @param string Path of the removed thing (like :employees or "*.foo.lala"), or a value to remove, or an array of paths and values [dot-paths are highly experimental, and stars don't work at all]
  * @return array 
  * @key __world
  */ 
  static function remove($value, $path)
  {
    // THINK: should we do something else? throw a warning? return false?
    if(!is_array($value))
      return $value;
    
    if(!$path)
      return $value;
    
    $paths = is_array($path) ? $path : array($path);
    
    foreach($paths as $path) {
      if(isset($value[$path])) // remove by key
        unset($value[$path]);
      elseif(($index = array_search($path, $value)) !== false) // remove by value
        unset($value[$index]);
      elseif(strpos($path, '.')) { // remove by path
        $words = explode('.', $path);
        $temp = &$value;
        while($words) {
          $word = array_shift($words);
          if(!count($words)) {
            unset($temp[$word]);
          } 
          elseif($word == '*') {
            // return 
            // foreach()
            // TODO: finish stars 'n dots!!!
          } 
          else {
            $temp = &$value[$word];
          }
        }
      }      
    }
      
    return $value;
  }
  
  
  /** 
  * Returns the array in reverse order
  * @param array Array to reverse
  * @return array
  * @key __world
  */
  static function reverse($value)
  {
    return array_reverse($value);
  }
  

  /**
  * Like a paring knife for arrays
  * @param array An array 
  * @param array Where to start the slice. Negative values count from the end of the array. Defaults to zero.
  * @param array Number of elements in the slice. Negative values count from the end. Defaults to all remaining elements.
  * @return array
  * @key __world
  */ 
  static function slice($value, $offset=NULL, $length=NULL)
  {
    if(!$offset)
      $offset = 0;
      
    return array_slice($value, $offset, $length, true);
  }
  /** 
  * Sort an array 
  * @param array The array to sort
  * @param array A field name, or an array of fields to sort over, in order of precedence, like {* (:price :desc :keyword :asc)}
  * @return array 
  * @key __world
  */ 
  static function sort($value, $by=NULL)
  {
    // some ideas from here: http://richard.gluga.com/2010/08/awesome-php-53-array-multi-sort.html
    
    if(!is_array($by))
      $by = array($by => 'asc');
    
    $GLOBALS['ETC']['sort']['by'] = $by;
    
    usort($value, array('Collection', 'sort_comparison'));
    
    return $value;
  }
  
  
  /** 
  * compare two items 
  * @param string Item 1
  * @param string Item 2
  * @return int
  */ 
  private static function sort_comparison($a, $b)
  {
    $by = $GLOBALS['ETC']['sort']['by'];
    
    foreach($by as $path => $order) {
      $this_a = Processor::get_nested_param_value($path, $a);
      $this_b = Processor::get_nested_param_value($path, $b);
      
      if(is_array($this_a)) $this_a = count($this_a);
      if(is_array($this_b)) $this_b = count($this_b);

      if($comp = strnatcasecmp($this_a, $this_b))
        return $comp * ($order == 'desc' ? -1 : 1);
    }
    
    return 0;
  }
  
  /**
  * Create a CSV formatted string from an array
  * @param array An array
  * @return string 
  * @key __world
  */ 
  static function to_csv($value)
  {
    // borrowed from http://www.php.net/manual/en/function.fputcsv.php
    $delimiter = ',';
    $enclosure = '"';
    $delimiter_esc = preg_quote($delimiter, '/'); 
    $enclosure_esc = preg_quote($enclosure, '/');
    
    $single_line_flag = !is_array(reset($value));
    
    foreach($value as $item)
    {
      $line = array();
      
      if($single_line_flag)
      {
        $output[] = preg_match("/(${delimiter}|${enclosure}|\s)/", $item) 
          ? ($enclosure . str_replace($enclosure, $enclosure_esc . $enclosure, $item) . $enclosure)
          : $item;
        // $line[] = preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field) 
        //   ? ($enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure)
        //   : $field;
      }
      else
      {
        foreach($item as $field) 
        {
          if(is_array($field))
            $field = json_encode($field);
            
          $line[] = preg_match("/(${delimiter}|${enclosure}|\s)/", $field) 
            ? ($enclosure . str_replace($enclosure, $enclosure_esc . $enclosure, $field) . $enclosure)
            : $field; 
        }

        $output[] = join($line, $delimiter);
      }
    } 
    
    if($single_line_flag)
      $output = join($delimiter, $output);
    else
      $output = join("\n", $output);
      
    return $output;
  }

  /**
  * Create a JSON formatted string from an array
  * @param array An array 
  * @return string 
  * @key __world
  */ 
  static function to_json($value)
  {
    // THINK: automatically escape braces?
    if($value == false)
      $value = "";

    return json_encode($value);
  }

  /** 
  * Returns the union of the arrays in 'values'
  * @param array A list of arrays to merge
  * @return array 
  * @key __world
  */
  static function union($values)
  {
    foreach ($values as $array)
      if($array)
        $args[] = (array) $array;

    $union = call_user_func_array('array_merge', $args); // returns NULL, so we have to convert that to false
    
    return $union ? $union : array();
  }
  

  /**
  * Uniquifies an array
  * @param array An array 
  * @return array
  * @key __world
  */ 
  static function unique($value)
  {
    return array_unique($value);
    // return array_unique($on, SORT_REGULAR);
  }
  }

//EOT