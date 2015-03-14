<?php

/** 
 * An extensible set of mathematical functions
 *
 * @package math
 * @author dann toliver (except where otherwise noted)
 */

class Math
{

  // This is an auto-generated file! DO NOT EDIT!!
  // Edits will be destroyed by the next {daimio freshen_commands} call.
  // Files in daimio/tools/$name will be copied into here as available methods.

  /**
  * What kind of snake is good at math?
  * 
  * The value and to parameters can be numbers or arrays of numbers.
  * 
  * Both numbers: Add the two numbers.
  * One array, no second parameter: Sum the numbers in the array.
  * One array, one number: Add the number to each item in the array.
  * Both arrays: Add elements of the arrays pairwise by key
  * 
  * You can use '+:' as a shortcut for this command.
  * 
  * @example {+: 4 to 7}
  * @example {7 | +: 4}
  * @example {+: (1 2 3)}
  * @example {(1 2 3) | +: 3}
  * @example {math add value (1 2 3) to (6 5 4)}
  *
  * @param string Augend
  * @param string Addend
  * @return boolean 
  * @key __world
  */ 
  static function add($value, $to=NULL)
  {
    // handle arrays
    if(is_array($value) || is_array($to)) {
      // just one array
      if(!isset($to)) {
        foreach($value as $num)
          $i += $num;
        return $i;
      }
      
      // one array, one number
      if(is_numeric($value)) {
        $temp = $to; $to = $value; $value = $temp; // swap value and to
      }
      if(!is_array($to)) {
        foreach($value as $key => $val)
          $value[$key] = $val + $to;
        return $value;
      }
      
      // both arrays
      foreach($value as $key => $val)
        if($to[$key])
          $value[$key] = $val + $to[$key];
      return $value;
    }
    
    // no arrays
    if(!is_numeric($value))
      return ErrorLib::set_warning("That is not a numeric value");

    if(!is_numeric($to))
      return $value;
    
    return $value + $to;
  }

  // TODO: refactor add, subtract, multiply, divide -- they all use essentially the same code structure.

  /**
  * The anti-successor
  * 
  * Reduce the value of a number by one.
  * You can use '--' as a shortcut for this command.
  *
  * @param string A value to unsucceed
  * @return string 
  * @key __world
  */ 
  static function decrement($value)
  {
    if(!is_numeric($value))
      return ErrorLib::set_warning("That is not a numeric value");
      
    return $value - 1;
  }
  
  /**
  * A method for conquering
  *
  * The value and to parameters can be numbers or arrays of numbers.
  *
  * Both numbers: Divide the two numbers.
  * One array, no second parameter: Divide the first number in the array by each other number.
  * One array, one number: Divide each item in the array by the number.
  * Both arrays: Divide elements of the arrays pairwise by key
  * 
  * You can use '/:' as a shortcut for this command. Read it as 'divides' instead of 'divide by' -- the second example will be confusing otherwise.
  * 
  * @example {/: 4 by 7}
  * @example {7 | /: 4}
  * @example {/: (1 2 3)}
  * @example {(1 2 3) | /: 3}
  * @example {math divide value (1 2 3) by (6 5 4)}
  *
  * @param string Numerator
  * @param string Denominator
  * @return boolean 
  * @key __world
  */ 
  static function divide($value, $by=NULL)
  {
    // handle arrays
    if(is_array($value) || is_array($by)) {
      // just one array
      if(!isset($by)) {
        $i = array_shift($value);
        foreach($value as $num) {
          if($num + 0 == 0)
            return ErrorLib::set_error("Division by zero is a crime against nature");
          $i /= $num;
        }
        return $i;
      }
      
      // one array, one number
      if(is_numeric($value)) {
        $temp = $by; $by = $value; $value = $temp; // swap value and to
      }
      if(!is_array($by)) {
        if($by + 0 == 0)
          return ErrorLib::set_error("Division by zero is a crime against nature");
        foreach($value as $key => $val)
          $value[$key] = $val / $by;
        return $value;
      }
      
      // both arrays
      foreach($value as $key => $val)
        if($by[$key] + 0)
          $value[$key] = $val / $by[$key];
      return $value;
    }
    
    // no arrays
    if(!is_numeric($value))
      return ErrorLib::set_warning("That is not a numeric value");

    if(!is_numeric($by))
      return $value;
    
    if($by + 0 == 0)
      return ErrorLib::set_error("Division by zero is a crime against nature");
    
    return $value / $by;
  }

  /**
  * Make your number display nicely (eg for currency)
  * @param string Numeric value
  * @param string Decimal places
  * @param string Leading symbol
  * @return boolean 
  * @key __world
  */ 
  static function format($value, $places, $symbol)
  {
    if(!is_numeric($value) || ($to && !is_numeric($to)))
      return ErrorLib::set_warning("That is not a numeric value");
    
    return $symbol . number_format($value, $places);
  }
  
  /**
  * Increment an int
  * 
  * Increase the value of a number by one.
  * You can use '++' as a shortcut for this command.
  *
  * @param string Value to succeed
  * @return string 
  * @key __world
  */ 
  static function increment($value)
  {
    if(!is_numeric($value))
      $value = 0;
      
    return $value + 1;
  }

  /**
  * Go fort hand
  *
  * The value and to parameters can be numbers or arrays of numbers.
  *
  * Both numbers: Multiply the two numbers.
  * One array, no second parameter: Multiply the numbers in the array.
  * One array, one number: Multiply the number to each item in the array.
  * Both arrays: Multiply elements of the arrays pairwise by key
  * 
  * You can use '*:' as a shortcut for this command.
  * 
  * @example {*: 4 by 7}
  * @example {7 | *: 4}
  * @example {*: (1 2 3)}
  * @example {(1 2 3) | *: 3}
  * @example {math multiply value (1 2 3) by (6 5 4)}
  *
  * @param string Factor the first
  * @param string Factor the second
  * @return boolean 
  * @key __world
  */ 
  static function multiply($value, $by=NULL)
  {
    // handle arrays
    if(is_array($value) || is_array($by)) {
      // just one array
      if(!isset($by)) {
        $i = 1;
        foreach($value as $num)
          $i *= $num;
        return $i;
      }
      
      // one array, one number
      if(is_numeric($value)) {
        $temp = $by; $by = $value; $value = $temp; // swap value and to
      }
      if(!is_array($by)) {
        foreach($value as $key => $val)
          $value[$key] = $val * $by;
        return $value;
      }
      
      // both arrays
      foreach($value as $key => $val)
        if($by[$key])
          $value[$key] = $val * $by[$key];
      return $value;
    }
    
    // no arrays
    if(!is_numeric($value))
      return ErrorLib::set_warning("That is not a numeric value");

    if(!is_numeric($by))
      return $value;
    
    return $value * $by;
  }
  /**
  * A smack in the face to exponents of exponentiation
  * 
  * This raises value to the exp. Fractional exponents are fine, so the square root of five is {5 | math pow exp :0.5}.
  * 
  * @example {math pow value 2 exp 8}
  * @example {5 | math pow exp :3}
  * @example {5 | math pow exp :0.5}
  * @example {(1 2 3) | +: 3}
  * @example {math add value (1 2 3) to (6 5 4)}
  *
  * @param string Base
  * @param string Exponent
  * @return boolean 
  * @key __world
  */ 
  static function pow($value, $exp)
  {
    return pow($value, $exp);
  }
  /**
  * There's random, and then there's AYN random
  * @param string Maximum value (defaults to 2)
  * @return boolean 
  * @key __world
  */ 
  static function random($max=NULL)
  {
    if(!ctype_digit((string) $max))
      $max = 2;
    
    $min = 1;

    return rand($min, $max);
  }
  /**
  * Round yourself out
  * @param string Augend
  * @param string Addend
  * @return boolean 
  * @key __world
  */ 
  static function round($value, $to=NULL)
  {
    if(!is_numeric($value) || ($to && !is_numeric($to)))
      return ErrorLib::set_warning("That is not a numeric value");
      
    return round($value, $to);
  }

  /**
  * Subtract them one from another
  *
  * The value and to parameters can be numbers or arrays of numbers.
  *
  * Both numbers: Subtract the two numbers.
  * One array, no second parameter: Subtract each subsequent item from the first array element.
  * One array, one number: Subtract the number from each item in the array.
  * Both arrays: Subtract elements of the second array from the first, pairwise by key
  * 
  * You can use '-:' as a shortcut for this command.
  * 
  * @example {-: 4 from 7}
  * @example {7 | -: 4}
  * @example {-: (100 2 3 4 5)}
  * @example {(1 3 5 7) | -: 3}
  * @example {math subtract value (6 5 4) from (1 2 3)}
  *
  * @param string Subtrahend
  * @param string Minuend
  * @return boolean 
  * @key __world
  */ 
  static function subtract($value, $from=NULL)
  {
    // handle arrays
    if(is_array($value) || is_array($from)) {
      // just one array
      if(!isset($from)) {
        $first = array_shift($value);
        foreach($value as $num)
          $first -= $num;
        return $first;
      }
      
      // one array, one number
      if(is_numeric($value)) {
        $temp = $from; $from = $value; $value = $temp; // swap value and from
      }
      if(!is_array($from)) {
        foreach($value as $key => $val)
          $value[$key] = $val - $from;
        return $value;
      }
      
      // both arrays
      foreach($value as $key => $val)
        if($from[$key])
          $value[$key] = $val - $from[$key];
      return $value;
    }
    
    // no arrays
    if(!is_numeric($value))
      return ErrorLib::set_warning("That is not a numeric value");

    if(!is_numeric($from))
      return $value;
    
    return $from - $value;
  }
}

//EOT