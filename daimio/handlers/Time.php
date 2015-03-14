<?php

/**
 * Responsible for spacetime manipulation
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Time 
{
  
  /** 
  * Reveal an instant in another light
  * @param string The time to convert
  * @param string Format to convert to (see http://php.net/date )
  * @return string 
  * @key __world
  */ 
  static function represent($moment=NULL, $as=NULL)
  {
    if(!$as)
      $as = 'U'; // default to time since epoch
    
    if($moment === NULL)
      return date($as);
    
    if(!$moment or strpos($moment, '0000') === 0)
      return false;
    
    if(!ctype_digit((string) $moment))
      $moment = strtotime($moment);
    
    return date($as, $moment);
  }
  
  /** 
  * Return the amount of time between then and now 
  * @param string When did it happen?
  * @param array Takes :about (displays as 'about an hour ago')
  * @return string 
  * @key __world
  */ 
  static function relative($to, $include=array())
  {
    // TODO: take timezones into account
    
    // convert to timestamp
    if(ctype_digit((string) $to))
      $timestamp = $to;
    else
      $timestamp = strtotime($to);

    // sanity check for negative times
    if($timestamp > time())
      return "a little while ago";

    // sanity check for current time
    if($timestamp == time())
      return "this very moment";

    $old_date = date('Y-m-d H:i:s', $timestamp);

    list($date, $time) = explode(" ", $old_date);
    list($year, $month, $day) = explode("-", $date);
    list($hour, $minute, $second) = explode(":", $time);

    $today = getdate();

    $year_delta = $today['year'] - $year;
    $month_delta = $today['mon'] - $month;
    $day_delta = $today['mday'] - $day;
    $hour_delta = $today['hours'] - $hour;
    $minute_delta = $today['minutes'] - $minute;
    $second_delta = $today['seconds'] - $second;


    if($year_delta != 0 && (($year_delta != 1) || ($month_delta >= 0))) {
      if ($year_delta > 1) {
        $relative_time = "$year_delta years";
      }
      else {
        $relative_time = "last year";
      }
    }

    elseif($month_delta != 0  && (($month_delta != 1) || ($day_delta >= 0))) {
      if($month_delta < 0) {
        $month_delta += 12;
      }
      if ($month_delta > 1) {
        $relative_time = "$month_delta months";
      }
      else {
        $relative_time = "last month";
      }
    }

    elseif($day_delta != 0  && (($day_delta != 1) || ($hour_delta >= 0))) {
      if($day_delta < 0) {
        $day_delta += 30;
      }
      if ($day_delta > 1) {
        $relative_time = "$day_delta days";
      }
      else {
        $relative_time = "yesterday";
      }
    }

    elseif($hour_delta != 0  && (($hour_delta != 1) || ($minute_delta >= 0))) {
      if($hour_delta < 0) {
        $hour_delta += 24;
      }
      if ($hour_delta > 1) {
        $relative_time = "$hour_delta hours";
      }
      else {
        $relative_time = "one hour";
      }
    }

    elseif($minute_delta != 0  && (($minute_delta != 1) || ($second_delta >= 0)))
   {
      if($minute_delta < 0) {
        $minute_delta += 60;
      }
      if ($minute_delta > 1) {
        $relative_time = "$minute_delta minutes";
      }
      else {
        $relative_time = "one minute";
      }
    }

    elseif($second_delta != 0 ) {
      if($second_delta < 0) {
        $second_delta += 60;
      }
      if ($second_delta > 1) {
        $relative_time = "$second_delta seconds";
      }
      else {
        $relative_time = "one second";
      }
    }

    if(in_array('about', (array) $include) && substr($relative_time, 0, 4) != 'last' && $relative_time != 'yesterday')
    {
      $relative_time = "about $relative_time ago";
    }

    return $relative_time;
  }
}

// EOT