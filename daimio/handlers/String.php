<?php

/** 
 * An extensible set of functions for textual manipulation
 *
 * @package string
 * @author dann toliver (except where otherwise noted)
 */

class String
{

  // This is an auto-generated file! DO NOT EDIT!!
  // Edits will be destroyed by the next {daimio freshen_commands} call.
  // Files in daimio/tools/$name will be copied into here as available methods.

  /** 
  * Perform a diff operation on two strings 
  * @param string
  * @param string 
  * @return string 
  * @key __world
  */ 
  static function diff($old, $new)
  {
    // TODO: replace {content diff} with calls to this
    // TODO: add some flexibility to the diff/escape options
    // TODO: move the diff functions to a different lib

    $esc_old = htmlspecialchars(preg_replace("/\n/", " \n", $old));
    $esc_new = htmlspecialchars(preg_replace("/\n/", " \n", $new));
    return nl2br(ContentLib::diff_master($esc_old, $esc_new, ' '));    
  }
    /** 
  * Scan an array/string for matching items/lines
  *
  * Given an array this returns matching items. 
  * Given a string it returns matching lines. 
  * Use {string split} to chunk a string into an array if linebreaks aren't your teacup. 
  * Enslash the search string for regex action.
  * 
  * @example {(:bach/goldberg_variations/aria :golden_globes/shirley_eaton :elements/au :flysch/kuskokwim_gold_belt) | string grep on :gold}
  * @example {"some examples are very contrived" | string split on " " | string grep on "/[vm]/"}
  * @example {content pick | grep :page reverse :true | grep :layout}
  *
  * @param mixed Value search over; a string or array
  * @param string Search string, slash for regex like "/whatevs/"
  * @param boolean Do not attempt to search for the value. That is impossible. Instead, search only to understand the truth: there is no value.
  * @return array 
  * @key __world
  */ 
  static function grep($value, $on, $reverse=NULL)
  {
    if(substr($on, 0, 1) != '/')
      $on = '/' . preg_quote($on, '/') . '/';
    
    if(is_string($value))
      $value = explode("\n", $value);
    
    if($reverse)
      $flags = PREG_GREP_INVERT;
    
    return array_values(preg_grep($on, $value, $flags));
  }

  /** 
  * Join an array into a string
  *
  * If value is not an array it is returned unaltered.
  * If with is a string, it is inserted between each item in value.
  * If with is an array of two values, each item in value is wrapped between the two.
  * If with is an array of more values, the two arrays are interleaved. The final with element is repeated as needed.
  * 
  * @example {string join value (2 3 4) with "-"}
  * @example {(:pancakes :waffles "french toast") | string join with ("<li>" "</li>")}
  * @example {(:ham :pork :bacon) | string join with (:tempeh :seitan :tofu :tvp)}
  *
  * @param string An array
  * @param string Glue string (or an array, see examples) (defaults to comma)
  * @return array
  * @key __world
  */ 
  static function join($value, $with=NULL)
  {
    if(!is_array($value))
      $value;
    
    if(is_array($with) && count($with) == 1)
      $with = reset($with);

    if(!isset($with))
      $with = ', ';
    
    if(!is_array($with))
      return implode((string) $with, $value);
    
    // with has exactly 2 elements
    if(count($with) == 2) {
      $begin = array_shift($with);
      $end = array_shift($with);
      foreach($value as $item) 
        $output .= $begin . (string) $item . $end;
      return $output;
    } 
    
    // both params are non-trivial arrays
    foreach($value as $item) {
      $next = count($with) ? array_shift($with) : $next;
      $output .= $next . (string) $item;        
    }
    $output .= count($with) ? array_shift($with) : $next;
    return $output;
  }

  /** 
  * Convert a string to lowercase 
  *
  * Note that the characters to be converted are dependent on the current locale settings.
  * 
  * @example {"SOME OLD COMPUTERS ONLY HAD CAPITAL LETTERS" | string lowercase}
  *
  * @param string Value to lowercase
  * @return string 
  * @key __world
  */ 
  static function lowercase($value)
  {
    return strtolower($value);
  }
  
  /** 
  * Sanitize a string for html display 
  * @param string Value to sanitize
  * @return string 
  * @key __world
  */ 
  static function sanitize($value)
  {
    return Processor::sanitize($value);
  }
  

  /** 
  * Get a slice of string 
  * @param string String to slice
  * @param string Start index (can be negative)
  * @param string Length (can be negative)
  * @return string 
  * @key __world
  */ 
  static function slice($value, $start=NULL, $length=NULL)
  {
    $start = $start ? $start : 0;
    $length = $length ? $length : strlen($value);
    return substr($value, $start, $length);
  }
  

  /** 
  * Break a string into an array 
  *
  * If the 'on' param begins and ends with front-slashes the splitting is done using via regular expression.
  * 
  * @example {"tags are fun and stuff" | string split on " "}
  * @example {"I_break _for leading_ under_scores" | string split on "/\b_/"}
  * @example {"extract text: <h3>header</h3> <p>some text</p>" | string transform old "/>\s*</" new "" | string split on "/<.*?>/"}
  *
  * @param string String to split
  * @param string Split on this (use "/regex/" to do regular expression matches)
  * @return array
  * @key __world
  */ 
  static function split($value, $on)
  {
    if(strpos($on, '/') === 0 && strrpos($on, '/') == strlen($on) - 1)
      return preg_split($on, $value);
    else
      return explode($on, $value);
  }

  
  /** 
  * Transform 'old' into 'new' in 'value'. (regex also)
  * @param string Value to transform
  * @param string Old value (wrap with // for regex, like "/^\d+$/")
  * @param string New value (regex is triggered by old)
  * @return string 
  * @key __world
  */
  static function transform($value, $old, $new=NULL)
  {    
    if(strpos($old, '/') === 0 && strrpos($old, '/') == strlen($old) - 1)
    {
      return preg_replace($old, $new, $value);
    }
    
    return str_replace($old, $new, $value);
  }
  

  /** 
  * Awesome Truncator AT2000 series 
  * @param string Cool beans
  * @param string Also cool
  * @return string 
  * @key __world
  */ 
  static function truncate($value, $length=NULL)
  {
    // default length
    if(!ctype_digit((string) $length))
      $length = 255;
  
    // check value against length
    if(!$length || $length >= strlen($value))
      return $value;
  
    $length++; // add one to catch the wordbreak after a word
  
    $truncated = substr($value, 0, $length);
    $last_break = strrpos($truncated, ' ');
    if($last_break > ($length / 2))
      $truncated = substr($truncated, 0, $last_break);
    else
      $truncated = substr($truncated, 0, -1);
  
    return $truncated;
  }

  
  /** 
  * Escape regex characters in a string
  * @param string Value to escape
  * @return string 
  * @key __world
  */ 
  static function unregex($value)
  {
    return preg_quote($value, '/');
  }
  
  
  /** 
  * Convert entities back to themselves
  * @param string Value to unsanitize
  * @return string 
  * @key __world
  */ 
  static function unsanitize($value)
  {
    return html_entity_decode($value, ENT_COMPAT, 'UTF-8');
  }
  

  /** 
  * Encode a string for using in a URL
  * @param string Value to encode
  * @param string If true, replace nonalphanums with dashes; defaults to true
  * @return string 
  * @key __world
  */
  static function url_encode($value, $elide=NULL)
  {
    if($elide || !isset($elide))
      return preg_replace('/[^ a-zA-Z0-9-]/', '', strtr($value, ' ', '-'));
    
    return urlencode($value);
  }
}

//EOT