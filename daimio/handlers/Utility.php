<?php

/** 
 * An easily extendable collection of tools -- like Batman's utility belt, but better
 *
 * @package utility
 * @author dann toliver (except where otherwise noted)
 */

class Utility
{

  // This is an auto-generated file! DO NOT EDIT!!
  // Edits will be destroyed by the next {daimio freshen_commands} call.
  // Files in daimio/tools/$name will be copied into here as available methods.

  /** 
  * Get a string representing the browser/OS type 
  * @return string 
  * @key __world
  */ 
  static function get_browser_string($ua=NULL)
  {
    /*
    PHP CSS Browser Selector v0.0.1
    Bastian Allgeier (http://bastian-allgeier.de)
    http://bastian-allgeier.de/css_browser_selector
    License: http://creativecommons.org/licenses/by/2.5/
    Credits: This is a php port from Rafael Lima's original Javascript CSS Browser Selector: http://rafael.adm.br/css_browser_selector
    */
    
    $ua = ($ua) ? strtolower($ua) : strtolower($_SERVER['HTTP_USER_AGENT']);		

		$g = 'gecko';
		$w = 'webkit';
		$s = 'safari';
		$b = array();
		
		// browser
		if(strpos($ua, 'chromeframe')) {
		    $b[] = 'chromeframe';
		} else if(!preg_match('/opera|webtv/i', $ua) && preg_match('/msie\s(\d)/', $ua, $array)) {
				$b[] = 'ie ie' . $array[1];
		}	else if(strstr($ua, 'firefox/2')) {
				$b[] = $g . ' ff2';		
		}	else if(strstr($ua, 'firefox/3.5')) {
				$b[] = $g . ' ff3 ff3_5';
    } else if(strstr($ua, 'firefox/3')) {
        $b[] = $g . ' ff3';
    } else if(strstr($ua, 'firefox/4')) {
        $b[] = $g . ' ff4';
		} else if(strstr($ua, 'gecko/')) {
				$b[] = $g;
		} else if(preg_match('/opera(\s|\/)(\d+)/', $ua, $array)) {
				$b[] = 'opera opera' . $array[2];
		} else if(strstr($ua, 'konqueror')) {
				$b[] = 'konqueror';
		} else if(strstr($ua, 'chrome')) {
				$b[] = $w . ' ' . $s . ' chrome';
		} else if(strstr($ua, 'iron')) {
				$b[] = $w . ' ' . $s . ' iron';
		} else if(strstr($ua, 'applewebkit/')) {
				$b[] = (preg_match('/version\/(\d+)/i', $ua, $array)) ? $w . ' ' . $s . ' ' . $s . $array[1] : $w . ' ' . $s;
		} else if(strstr($ua, 'mozilla/')) {
				$b[] = $g;
		}

		// platform				
		if(strstr($ua, 'j2me')) {
				$b[] = 'mobile';
		} else if(strstr($ua, 'iphone')) {
				$b[] = 'iphone';		
		} else if(strstr($ua, 'ipod')) {
				$b[] = 'ipod';		
		} else if(strstr($ua, 'mac')) {
				$b[] = 'mac';		
		} else if(strstr($ua, 'darwin')) {
				$b[] = 'mac';		
		} else if(strstr($ua, 'webtv')) {
				$b[] = 'webtv';		
		} else if(strstr($ua, 'win')) {
				$b[] = 'win';		
		} else if(strstr($ua, 'freebsd')) {
				$b[] = 'freebsd';		
		} else if(strstr($ua, 'x11') || strstr($ua, 'linux')) {
				$b[] = 'linux';		
		}
				
		return join(' ', $b);
  }
  }

//EOT