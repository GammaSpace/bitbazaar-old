<?php

/**
 * Basic methods for authenticated users
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Myself
{
  
  /**
  * Username of currently authenticated user
  * @return string
  * @key __world
  */ 
  static function whoami()
  {
    if($username = $GLOBALS['X']['USER']['username'])
      return $username;
    else
      return "(anon)";
  }

  /** 
  * Put on a different hat 
  * @param string 
  * @return boolean 
  * @key __member
  */ 
  static function wear($hat)
  {
    // check for hat
    $hats = explode(' ', trim(QueryLib::get_single_value('users', 'id', $GLOBALS['X']['USER']['id'], 'hats')));
    if(!in_array($hat, $hats))
      return ErrorLib::set_error("You do not own that hat");
      
    // put it on
    session_start();
    $_SESSION['X']['user']['current_hat'] = $hat;
    $_SESSION['X']['user']['hats'] = $hats;
    session_write_close();
    
    // tweak @MY, in case we want the current_hat right away
    $GLOBALS['X']['VARS']['MY']['current_hat'] = $hat;
    
    return QueryLib::set_single_value('users', 'id', $GLOBALS['X']['USER']['id'], 'current_hat', $hat);
  }
  
  /** 
  * Get an array of my hats  
  * @return string 
  * @key __member
  */ 
  static function get_hats()
  {
    return $GLOBALS['X']['USER']['hats'];
  }
  
  

  /**
  * Authenticate the user and rebuild commands
  * @param string Username
  * @param string Password
  * @param int Length of login (defaults to just this session)
  * @return int 
  * @link http://www.paulsrichards.com/2008/07/29/persistent-sessions-with-php/
  * @key __world
  */ 
  static function authenticate($username, $password, $days=NULL)
  {
    // get_users scrubs the user for us, and splits the keychain
    $user = reset(UserLib::get_users("username = '$username'"));
    
    // THINK: u and p errors provide the same message for security purposes, but it might be nice to be nice to our users...
    if(!$user)
      return ErrorLib::set_error("Invalid authentication credentials");
    
    $passhash = $user['passhash'];
    $salt = $user['salt'];
    if($passhash != sha1($password . $salt))
      return ErrorLib::set_error("Invalid authentication credentials");

    if($user['disabled'])
      return ErrorLib::set_error("Account disabled");
    
    SessionLib::set_user_session($user);
    SessionLib::add_user_to_globals($user);
    
    // THINK: maybe there's a system setting that provides time-of-life limits?
    if($days) {
      if(ctype_digit((string) $days))
        SessionLib::set_login_cookie($user, $days);
      else
        ErrorLib::set_warning("Value '$days' for param days is not an integer");      
    }
    
    __build_commands();
    
    return $username;
  }
  
  
  //
  // Members only:
  //

  
  /** 
  * Change your password 
  * @param string New password
  * @return string 
  * @key __member
  */ 
  static function set_password($to)
  {
    list($salt, $passhash) = UserLib::get_salt_and_hash_from_password($to);
    $params = array('salt'        => $salt,
                    'passhash'    => $passhash);

    return QueryLib::update_row('users', $GLOBALS['X']['USER']['id'], $params);
  }


  /**
  * Log the current user out
  * @return boolean
  * @key __member
  */ 
  static function logout()
  {
    // am i a polymorphed wizard?
    if($user = $_SESSION['X']['old_user'])
    {
      SessionLib::set_session_value('old_user', '');
      SessionLib::set_session_value('user', $user);
      SessionLib::add_user_to_globals($user);
      return true;
    }
    
    setcookie('chocolate_chip', '', time() - 3600);
    unset($GLOBALS['X']['VARS']['MY']);
    unset($GLOBALS['X']['USER']);
    return SessionLib::remove_user_session();
  }
  
  /** 
  * Returns a single value -- does not work for key:value arrays! 
  * @param string Metadata key
  * @return string
  * @key __member
  */ 
  static function get_metadata($key)
  {
    $type = 'usermeta';
    $user_id = $GLOBALS['X']['USER']['id'];
    
    // if(!$key)
    //   return ErrorLib::set_error("A key is required to get_metadata (try using fetch_metadata to retrieve an array of data)");

    return Metadata::get_single_metadata_value($type, $user_id, $key);      
  }
  
  /** 
  * Returns an array of metadata
  * @param string Metadata key (optional) 
  * @return string
  * @key __member
  */ 
  static function fetch_metadata($key=NULL)
  {
    $type = 'usermeta';
    $user_id = $GLOBALS['X']['USER']['id'];
    
    if($key)
      return Metadata::get_single_metadata_value_array($type, $user_id, $key);      
    else
      return Metadata::get_clean_metadata_array($type, $user_id);
  }
  
  /** 
  * Add a value to the key (a key can have many values)
  * @param string Metadata key
  * @param string Metadata value
  * @return string 
  * @key __member
  */ 
  static function add_metadata($key, $value)
  {
    $type = 'usermeta';
    $user_id = $GLOBALS['X']['USER']['id'];
    
    return Metadata::add_metadata($type, $user_id, $key, $value);
  }
  
  /** 
  * Set a key to a value (removes all old values, then adds the new one)
  * @param string Metadata key
  * @param string Metadata value
  * @return string 
  * @key __member
  */ 
  static function set_metadata($key, $value)
  {
    $type = 'usermeta';
    $user_id = $GLOBALS['X']['USER']['id'];
    
    // TODO: use the UserLib 'try' commands here instead (and maybe customize the 'try'ing a bit better... we can check and set errors?)
    return Metadata::set_metadata($type, $user_id, $key, $value);
  }
  
  /** 
  * Without value removes all key-value pairs, with value just the one
  * @param string Metadata key
  * @param string Metadata value
  * @return string 
  * @key __member
  */ 
  static function remove_metadata($key, $value=NULL)
  {
    $type = 'usermeta';
    $user_id = $GLOBALS['X']['USER']['id'];
    
    if($value)
      return Metadata::delete_metadata_by_key_and_value($type, $user_id, $key, $value);
    else
      return Metadata::delete_metadata_by_key($type, $user_id, $key);
  }
  

}

// EOT