<?php

/**
 * Handle user management related tasks
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class User 
{
  
  //
  // Private
  //
  
  /**
  * get the array of where params
  * @return array 
  */ 
  private function get_where_params()
  {
    $params[] = 'username';
    $params[] = 'wizard';
    $params[] = 'disabled';
    $params[] = 'keychain';
    $params[] = 'current_hat';
    $params[] = 'hats';
    $params[] = 'cron';
    $params['id'] = 'users.id';
    return $params;
  }
  
  
  //
  // Not private
  //
  
  
  /** 
  * A newfangled finder for users 
  * @param string A list of user ids
  * @param string A list of usernames
  * @return array 
  * @key admin
  */ 
  static function find($by_ids=NULL, $by_usernames=NULL)
  {
    if($by_ids) {
      if(!is_array($by_ids)) 
        $by_ids = array($by_ids);

      if(!count($by_ids))
        return array();
      
      foreach($by_ids as $key=>$id)
        $clean_ids[] = (int) $id;

      $id_string = join($clean_ids, ',');
      $id_sql = "id in ($id_string)";

      return UserLib::get_users($id_sql, '', '');
    }
    
    if($by_usernames)
      return UserLib::get_users_from_usernames($by_usernames);
      
    // make $by_ids a list of ints
    // make $by_usernames a list of names
    
    // compose the sql query?
  }
  
  
  /**
  * Get an array of selected usernames  
  * @param string Limits the results -- see the documentation for information on the where string
  * @return string 
  * @key admin
  */ 
  static function pick($where=NULL)
  {
    // TODO: add some limits here, this isn't scalable
    $params = User::get_where_params();
    $where_sql = HandlerLib::where_parser($where, $params);
    
    $user_array = UserLib::get_users($where_sql, false, 'username');

    $usernames = array();
    foreach($user_array as $user)
      $usernames[] = $user['username'];
    
    return $usernames;
  }

  /**
  * Get an array of selected users  
  * @param string Limits the results -- see the documentation for information on the where string
  * @return string
  * @key admin
  */ 
  static function fetch($where=NULL)
  {
    // TODO: add some limits here, this isn't scalable
    // YAGNI: add order
    $params = User::get_where_params();
    $where_sql = HandlerLib::where_parser($where, $params);
    
    $dirty_users = UserLib::get_users($where_sql);
    $clean_users = UserLib::scrub_dirty_users($dirty_users);
    
    return $clean_users;
  }
   
  
  /**
  * Make a new one!
  * @param string Subject to constraints
  * @param string Subject to constraints
  * @return string 
  */ 
  static function add($username, $password)
  {
    // THINK: constraints? what constraints?
    $username = trim($username);
    $password = trim($password);
    
    // ensure username and password exist (ORLY?)
    if(!$username)
      return ErrorLib::set_error("That is not a valid username");
    if(!$password)
      return ErrorLib::set_error("That is not a valid password");
    
    if(!UserLib::add_user($username, $password))
      return false; // error in UserLib

    return $username;
  }
  
  
  /** 
  * Add a hat to a user (they'll need to relog or wear before this shows up)
  * @param string Set of usernames
  * @param string A hat type
  * @return array 
  * @key milliner admin
  */ 
  static function add_hat($for, $type)
  {
    $usernames = HandlerLib::add_things($for, $type, 'users', 'username', 'hats');
    
    if(!$usernames)
      return array();
    
    // add hat to any hatless users
    $username_string = join($usernames, ' :');
    $users = User::fetch("username %= {(:$username_string)} AND current_hat = ''");
    foreach($users as $user)
      if(!$user['current_hat']) 
        QueryLib::set_single_value('users', 'id', $user['id'], 'current_hat', $type);
    
    return $usernames;
  }
  
  
  /** 
  * Remove a hat from a user (they'll need to relog or wear before this shows up)
  * @param string Set of usernames
  * @param string A hat type
  * @return array 
  * @key milliner admin
  */ 
  static function remove_hat($for, $type)
  {
    return HandlerLib::remove_things($for, $type, 'users', 'username', 'hats');
  }
  
  
  
  /** 
  * Assume the guise of another
  *
  * If the into param is an integer this uses the user's id. If it's a string, it uses username. This allows you to polymorph into users who have integer usernames (though having integer usernames may bring its own set of problems).
  *
  * @example {"by id" | user polymorph into 5}
  * @example {"by username" | user polymorph into "5"}
  *
  * @param string Target's username or id
  * @return boolean 
  * @key admin
  */ 
  static function polymorph($into)
  {
    if(is_array($into))
      $into = reset($into);
    
    if(is_int($into))
    {
      $username = QueryLib::get_single_value('users', 'id', $into, 'username');
      $user = UserLib::get_clean_user($username);
    }
    else
    {      
      $user = UserLib::get_clean_user($into);
    }

    if(!$user)
      return ErrorLib::set_error("No matching user found");
    
    if($user['wizard'])
      return ErrorLib::set_error("The wizard {$user['username']} resists your insolent attempt at mimicry");
    
    // change the session
    SessionLib::set_session_value('old_user', $GLOBALS['X']['USER']);
    SessionLib::set_session_value('user', $user);
    
    // change the globals
    SessionLib::add_user_to_globals($user);
    
    __build_commands();
    
    return $into;
  }
  

  /**
  * Convert plebs into wizards
  * @param mixed A list of of usernames
  * @return array 
  */ 
  static function wizardize($for)
  {
    $usernames = HandlerLib::for_parser($for);    
    $user_array = UserLib::get_users_from_usernames($usernames);
    
    if(!count($user_array))
      return ErrorLib::set_error("No valid usernames were found");

    foreach($user_array as $user)
      if(QueryLib::set_single_value('users', 'id', $user['id'], 'wizard', 1))
        $changed_users[] = $user['username'];
    
    if(!count($changed_users))
      return ErrorLib::set_warning("No action was taken: all listed users are already wizards"); // NOTE: this never triggers, because we changed the foreach loop above. keeping it here for posterity
    
    return $changed_users;
  }
  
  /**
  * Revoke wizarding licenses
  * @param mixed A list of of usernames
  * @return array 
  */ 
  static function unwizardize($for)
  {
    $usernames = HandlerLib::for_parser($for);    
    $user_array = UserLib::get_users_from_usernames($usernames);
    
    if(!count($user_array))
      return ErrorLib::set_error("No valid usernames were found");

    foreach($user_array as $user)
      if(QueryLib::set_single_value('users', 'id', $user['id'], 'wizard', 0))
        $changed_users[] = $user['username'];
    
    if(!count($changed_users))
      return ErrorLib::set_warning("No action was taken: no listed users were wizardly in nature");
    
    return $changed_users;
  }
  
  
  /** 
  * Change your password 
  * @param string A list of usernames
  * @param string New password
  * @return string 
  * @key admin
  */ 
  static function set_password($for, $to)
  {
    $usernames = HandlerLib::for_parser($for);    
    $user_array = UserLib::get_users_from_usernames($usernames);
    
    if(!count($user_array))
      return ErrorLib::set_error("No valid usernames were found");

    // set new password for each username
    foreach($user_array as $user)
    {
      // TODO: if $user is a wizard and not me, fail
      list($salt, $passhash) = UserLib::get_salt_and_hash_from_password($to);
      $params = array('salt'        => $salt,
                      'passhash'    => $passhash);

      QueryLib::update_row('users', $user['id'], $params);
    }
    
    return $usernames;
  }
  
  
  /**
  * Prevent user authentication
  * @param mixed A list of of usernames
  * @return array 
  * @key admin
  */ 
  static function disable($for)
  {
    $usernames = HandlerLib::for_parser($for);    
    $user_array = UserLib::get_users_from_usernames($usernames);
    
    if(!count($user_array))
      return ErrorLib::set_error("No valid usernames were found");

    foreach($user_array as $user)
      if(!$user['wizard'] && !$user['disabled'])
        if(QueryLib::set_single_value('users', 'id', $user['id'], 'disabled', 1))
          $changed_users[] = $user['username'];
    
    if(!count($changed_users))
      return ErrorLib::set_warning("No action was taken: all listed users are already disabled or wizards");
    
    return $changed_users;
  }
  
  /**
  * Re-allow user authentication
  * @param mixed A list of of usernames
  * @return array 
  * @key admin
  */ 
  static function undisable($for)
  {
    $usernames = HandlerLib::for_parser($for);    
    $user_array = UserLib::get_users_from_usernames($usernames);
    
    if(!count($user_array))
      return ErrorLib::set_error("No valid usernames were found");

    foreach($user_array as $user)
      if(QueryLib::set_single_value('users', 'id', $user['id'], 'disabled', 0))
        $changed_users[] = $user['username'];
    
    if(!count($changed_users))
      return ErrorLib::set_warning("No action was taken: no listed users are currently disabled");
    
    return $changed_users;
  }
  
  
  /**
  * Give some users a key
  * @param mixed A list of of usernames or ids
  * @param string Key to add to the keychain (keys are single words)
  * @return array 
  */ 
  static function add_key($for, $key)
  {
    $user_somethings = HandlerLib::for_parser($for);
    
    if(!ctype_digit((string) $user_somethings[0])) {
      $users = UserLib::get_users_from_usernames($user_somethings);
    } 
    else {
      $id_string = join($user_somethings, ',');
      $users = Userlib::get_users("id in ($id_string)");
    }
    
    // don't give keys to wizards: they don't need them, and shouldn't be using them on the front end

    foreach($users as $user)
      if(!$user['wizard'])
        $targets[] = $user['username'];
    
    if($users && !$targets)
      return ErrorLib::set_error("Keys can't be added to wizards");
    
    return HandlerLib::add_things($targets, $key, 'users', 'username', 'keychain');
  }    

  
  /**
  * Take a key away from some users
  * @param mixed A list of of usernames
  * @param string Key name
  * @return array
  */ 
  static function remove_key($for, $key)
  {
    return HandlerLib::remove_things($for, $key, 'users', 'username', 'keychain');
  }
  
  /**
  * Permanently destroy a user  
  * @param mixed A list of of usernames
  * @return boolean 
  */ 
  static function destroy($for)
  {
    $usernames = HandlerLib::for_parser($for);
    $ids = UserLib::get_ids_from_usernames($usernames);
    
    if(!$ids)
      return ErrorLib::set_error("Invalid username \"$username\"");
    
    $id_string = join($ids, ',');
    $user_array = Userlib::get_users("id in ($id_string)");
        
    // TODO: return a notice with all connected stuff for this user (forms, etc)
    // TODO: deposit in temp/destroyed (a user export command?)
    foreach($user_array as $user) {
      if(!$user['wizard'])
        QueryLib::delete_row_by_id('users', $user['id']);
      else
        ErrorLib::set_error("The wizard '{$user['username']}' defies your attempted destruction.");
    }
    
    return true;
  }
  
}

//EOT