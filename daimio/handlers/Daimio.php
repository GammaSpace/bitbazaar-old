<?php

/**
 * Commands that affect the entire site
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Daimio 
{
  
  /**
  * Control what things are written to daimio/log.txt
  * 
  * Some log types can rapidly fill up a drive: traces, queries, commands, and calls are particularly susceptible to this.
  * Those log types can also have a severely deleterious effect on system speed. Best to use them sparingly, if at all.
  * 
  * @param string Accepts one or more of errors, warnings, notices, queries, commands, calls, local, static, and traces
  * @param string Accepts on or off
  * @return boolean
  * @example {daimio logging type :errors turn :on}
  */
  static function logging($type, $turn)
  {
    if(!is_array($type))
      $type = array($type);
    
    $turn = $turn != 'on' ? 0 : 1;
    
    $log_types = array('errors', 'warnings', 'notices', 'queries', 'commands', 'calls', 'local', 'static', 'traces');
    
    foreach($type as $this_type)
    {
      if(!in_array($this_type, $log_types))
        ErrorLib::set_error("Type \"$type\" is not valid");
      else
        Daimio::set_setting("log_{$this_type}", $turn);
    }
  }
  
  
  /**
  * Add a new site setting
  * Setting the type and item params is dangerous, as you might accidentally overwrite something used elsewhere in the system. It's also useless if you don't know exactly what you're doing, as there's no way to see arbitrary metadata.
  * @param string Name of the site setting
  * @param string Value can be any string
  * @param string Type of entry (defaults to 'daimio' -- don't use unless instructed!)
  * @param string Item identifier (defaults to 'daimio' -- don't use unless instructed!)
  * @return string 
  */ 
  static function add_setting($name, $value, $type=NULL, $item=NULL)
  {
    if(!$type)
      $type = 'daimio';
    if(!$item)
      $item = 'daimio';
      
    return Metadata::add_metadata($type, $item, $name, $value);
  }
  
  
  /**
  * Add a new site setting or change an old one  
  * Setting the type and item params is dangerous, as you might accidentally overwrite something used elsewhere in the system. It's also useless if you don't know exactly what you're doing, as there's no way to see arbitrary metadata.
  * @param string Name of the site setting
  * @param string Value can be any string
  * @param string Type of entry (defaults to 'daimio' -- don't use unless instructed!)
  * @param string Item identifier (defaults to 'daimio' -- don't use unless instructed!)
  * @return string 
  */ 
  static function set_setting($name, $value, $type=NULL, $item=NULL)
  {    
    if(!$type)
      $type = 'daimio';
    if(!$item)
      $item = 'daimio';
      
    return Metadata::set_metadata($type, $item, $name, $value);
  }
  
  /**
  * Get the site setting array
  * @return string 
  */ 
  static function get_settings()
  {
    $settings = $GLOBALS['X']['SETTINGS'];
    unset($settings['commands']); // THINK: we're manually stripping the commands from here until we find a better way of dealing with this
    // THINK: we should convert logging settings to "on" and "off" (or take them out of here and give that it's own command (can _finally_ use the metadata key setting!))
    unset($settings['db']); // THINK: we should strip out more of these sensitive items...
    return $settings;
  }
  
  /**
  * Open or lock a command and provide or remove keyholes
  * 
  * Remember the magic keys __world (allows anyone to access the command) and __member (allows any registered user to access the command).
  * 
  * @param string The command's handler
  * @param string The command's method
  * @param string An array or space-delimited string of keys; empty string removes existing keyholes
  * @return string 
  */ 
  static function set_permission($handler, $method, $keyholes)
  {    
    $condition_array = array('handler' => $handler, 'method' => $method);
    
    if(!QueryLib::get_matching_rows('command_perms', $condition_array))
      return ErrorLib::set_error("There is no {" . "$handler $method} command");
    
    if(is_array($keyholes))
      $keyholes = implode(' ', $keyholes);
        
    // set up some vars
    $safe_handler = QueryLib::quote_smart($handler);
    $safe_method = QueryLib::quote_smart($method);
    $safe_holes = QueryLib::quote_smart(' ' . trim($keyholes) . ' ');    
    $keyholes_sql = $keyholes ? "keyholes = $safe_holes" : "keyholes = ''";
    
    $sql = "
            UPDATE command_perms 
            SET
            $keyholes_sql
            WHERE 
            handler = $safe_handler
            AND method = $safe_method
            LIMIT 1
           ";
    
    if(DBLib::query($sql))
      ErrorLib::set_notice("Keyholes updated for {" . "$handler $method}");
    
    // update the command hash in metadata
    $command_tree = Processor::get_commands();
    $command_tree[$handler]['methods'][$method]['keyholes'] = $keyholes;
    Metadata::set_metadata('daimio', 'daimio', 'commands', json_encode($command_tree));
    
    // clear the cache
    QueryLib::delete_from_table_by_string('metadata', "type = 'caches' AND item = 'commands'");

    Processor::log_command('command_perms');
  }
  

  /**
  * Scan all handlers and models to rebuild the command list (sets NOTICES for new commands)
  * @param string Accepts :overwrite, which will overwrite all your custom command permissions with the default ones (useful if you've updated your code and haven't used {daimio set_permission})
  * @return boolean 
  */ 
  static function freshen_commands($and=NULL)
  {    
    FileLib::activate('freshen');
    
    $old_command_tree = Processor::get_commands();
    $overwrite = $and == 'overwrite';
    
    $scan_array = array('daimio/handlers', 'daimio/models');
    $new_command_tree = FileLib::scan_dirs($scan_array);

    // shove new command tree in the db (on overwrite)
    if($overwrite)
      Metadata::set_metadata('daimio', 'daimio', 'commands', json_encode($new_command_tree));

    // get the old command perms, because we need to grandfather keyholes
    if(!$overwrite)
    {
      $old_perms = QueryLib::get_rows('command_perms');
      foreach($old_perms as $perm)
        $old_methods[$perm['handler']][$perm['method']]['keyholes'] = $perm['keyholes'];
    }
    
    // remove existing command perms 
    // NOTE: this breaks the site until after the next step completes, but also refreshes the order of commands and params (which is good)
    QueryLib::delete_from_table_by_string('command_perms', "1=1"); // NOTE: messy syntax
    
    // go through command tree and add each one
    foreach($new_command_tree as $class_name => $class)
    {
      foreach($class['methods'] as $method_name => $method)
      {
        // grandfathering
        if(!$overwrite && $old_methods[$class_name][$method_name])
          $method['keyholes'] = $old_methods[$class_name][$method_name]['keyholes'];
        
        // set default stuff
        $keyholes = $method['keyholes'];
        
        // drop it in command_perms
        $command_params = array('handler' => $class_name, 'method' => $method_name, 'keyholes' => $keyholes);
        QueryLib::add_row('command_perms', $command_params);

        // modify new_command_tree
        $new_command_tree[$class_name]['methods'][$method_name]['keyholes'] = $keyholes;

        // helpful lingo
        $english_keys = $keyholes ? " with keyholes " . implode(',', explode(' ', trim($keyholes))) : '';
        
        // set notices for new commands
        if(!$old_command_tree[$class_name]['methods'][$method_name])
        {
          ErrorLib::set_notice("Added new command \"$class_name $method_name\" $english_keys");
        }
        else
        {
          // set notices for modified commands
          if($method != $old_command_tree[$class_name]['methods'][$method_name])
          {
            // TODO: describe modifications: params? keyholes?
            ErrorLib::set_notice("Modified command \"$class_name $method_name\"");
          }

          // remove the old command, so we know what's left
          unset($old_command_tree[$class_name]['methods'][$method_name]);
        }
      }
    }
    
    // set notices for deleted commands
    foreach($old_command_tree as $class_name => $class)
    {
      foreach($class['methods'] as $method_name => $method)
      {
        ErrorLib::set_notice("Removed command \"$class_name $method_name\"");
      }
    }
    
    // shove new command tree in the db (no overwrite)
    if(!$overwrite)
      Metadata::set_metadata('daimio', 'daimio', 'commands', json_encode($new_command_tree));

    // clear the cache
    QueryLib::delete_from_table_by_string('metadata', "type = 'caches' AND item = 'commands'");
  }  

  /** 
  * Get the last $lines lines of the log 
  * @param string Defaults to 20
  * @return string 
  */ 
  static function tail_log($lines=NULL)
  {
    $lines = ctype_digit((string) $lines) ? $lines : 20;
    $log_file = $GLOBALS['X']['SETTINGS']['site_directory'] . "/daimio/log.txt";
    return shell_exec("tail -$lines $log_file");
  }
  

}


// EOT