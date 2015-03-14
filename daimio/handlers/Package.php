<?php

/**
 * Handle Packages
 *
 * @package daimio
 * @author dann toliver
 * @version 1.0
 */

class Package 
{
  
  /** 
  * Fetch an array of package information 
  * @param string A url to fetch from (defaults to daimio.org, use 'this' to fetch locally hosted packages)
  * @return array 
  * @key __world
  */ 
  static function fetch($from=NULL)
  {
    // THINK: switch this to 'here'?
    if($from == 'this')
      return json_encode(QueryLib::get_rows('packages'));
    
    if(!$from)
      $from = 'daimio.org';

    if(!preg_match('/^https?:\/\//', $from))
      $from = "http://$from/homunculus.php?handler=package&method=fetch&from=this&content-type=" . urlencode("application/json; charset=UTF-8");
      
    $packages_in_a_string = file_get_contents($from);
    
    return json_decode($packages_in_a_string, true);
  }
  
  /** 
  * Add a new package. Package files live in /packages and have a .zip extension
  * @param string This is also the filename (cum .zip)
  * @param string Description of the new package
  * @return boolean 
  */ 
  static function add($keyword, $description=NULL)
  {
    $file_path = $GLOBALS['X']['SETTINGS']['site_directory'] . "/packages/$keyword.zip";
    if(!file_exists($file_path))
      return ErrorLib::set_error("File packages/$keyword.zip does not exist");
    
    if(QueryLib::get_row('packages', 'keyword', $keyword))
      return ErrorLib::set_error("A package with the keyword :$keyword already exists");
      
    QueryLib::add_row('packages', array('keyword' => $keyword, 'description' => $description));
  }
  
  /** 
  * Remove a package. You'll need to remove the package zip file from /packages manually.
  * @param string This is also the filename (sans .zip)
  * @return boolean 
  */ 
  static function remove($keyword)
  {
    if(!QueryLib::get_row('packages', 'keyword', $keyword))
      return ErrorLib::set_error("Package with keyword :$keyword does not exist in the database");
      
    return QueryLib::delete_from_table('packages', 'keyword', $keyword);
  }
  
  /** 
  * Download a file, save it to daimio/temp/downloads, and unzip it into daimio/temp/packages 
  * @param string Package keyword
  * @param string Defaults to daimio.org
  * @return boolean 
  */ 
  static function download($keyword, $from=NULL)
  {
    if($from)
      ErrorLib::set_warning("Caution: never install packages from untrusted sources!");
    else
      $from = 'daimio.org';
    
    if(!preg_match('/^https?:\/\//', $from))
      $from = "http://$from";  
    $from .= "/packages/$keyword.zip";
    
    // scrub keyword
    $keyword = QueryLib::scrub_string($keyword, '_', '_.-');
    
    $site_dir = $GLOBALS['X']['SETTINGS']['site_directory'];
    $download_dir = FileLib::build_path("$site_dir/daimio/temp/downloads");
    $package_dir = FileLib::build_path("$site_dir/daimio/temp/packages");
    $download_path = "$download_dir/$keyword.zip";
    $file_path = "$package_dir/$keyword";
    
    if(!$download_dir || !$package_dir)
      return ErrorLib::set_error("Could not build download or package directories. Please check your file permissions and try again.");
    
    if(file_exists($download_path))
      return ErrorLib::set_error("A file already exists at $download_path");
    
    if(file_exists($file_path))
      return ErrorLib::set_error("A file already exists at $file_path");
    
    // download the file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $from);
    $fp = fopen($download_path, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    if(!curl_exec($ch))
      return ErrorLib::set_error("Curl error: " . curl_errno($ch).' '.curl_error($ch));
    curl_close($ch);
    fclose($fp);

    // unzip the file
    if($error = shell_exec("unzip -q $download_path -d $package_dir 2>&1"))
      return ErrorLib::set_error("Error unzipping the file: $error");
    
    return $keyword;
  }
  
  /** 
  * Install a new package 
  * @param string Keyword of the package to install
  * @param string Takes :overwrite, which copies over existing files (old files sent to /destroyed)
  * @return boolean 
  */ 
  static function install($keyword, $and=NULL)
  {
    $site_dir = $GLOBALS['X']['SETTINGS']['site_directory'];
    $package_dir = "$site_dir/daimio/temp/packages/$keyword";
    // $site_content_dir = "$site_dir/daimio/temp/content";
    
    $p_content_dir = "$package_dir/content";
    $p_command_dir = "$package_dir/commands";
    $p_file_dir = "$package_dir/files";

    if(!file_exists($package_dir))
      return ErrorLib::set_error("No package found at $package_dir");
        
    if(file_exists($p_content_dir))
    {
      ContentLib::import_content_from_dir($p_content_dir);
      
      // // check for content lock
      // if(file_exists("$site_content_dir/.content_lock"))
      //   return ErrorLib::set_error('Content export already in progress, files are locked. Use the {content import and "remove"} command before package install. (You may also manually remove the /daimio/temp/content/ directory if those files are outdated.)');
      //     
      // ContentLib::remove_temp_content_dir();
      // ContentLib::build_temp_content_dir();
      // 
      // // copy content files
      // FileLib::supercopy($p_content_dir, $site_content_dir, $options);
      // 
      // // NOTE: we import content first, creating all the new pages. You'll need to edit the pages if you need to change their titles, paths, or templates.
      // Processor::process_string("{content import and :remove}");
    }
    
    // copy files dir 
    // NOTE: copy files before running commands, so they can use any new models n' stuff in the commands (after running a manual {daimio freshen_commands})
    $options = array($and => true);
    if(file_exists($p_file_dir))
      FileLib::supercopy($p_file_dir, '', $options);
  
    // run command files
    $p_command_dir_files = scandir($p_command_dir);
    foreach($p_command_dir_files as $filename)
    {
      $commands = file_get_contents("$p_command_dir/$filename");
      Processor::process_string($commands);
    }

    // THINK: how can we handle handler calls in other handlers?
    Processor::process_string("{daimio freshen_commands}"); // THINK: mull this freshen over
  }
}

// EOT