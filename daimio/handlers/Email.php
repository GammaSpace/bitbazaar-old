<?php

class Email 
{  
  /** 
  * Sends an email
  * @param string HTML content of the email
  * @param string Text content of the email
  * @param string Subject content of the email
  * @param string Email address of recipient
  * @param string Name of recipient
  * @param string From email address (if different from default)
  * @param string From name (if different from default)
  * @param string Template name
  * @param string Template content
  * @param array Merge variable content (in format key=variable name, value=content)
  * @return string 
  * @key admin
  */ 
  static function send_message($html, $text, $subject, $to_email, $to_name=NULL, $from_email=NULL, $from_name=NULL, $merge=NULL)
  {
    $message['html'] = $html;
    $message['text'] = $text;
    $message['subject'] = $subject;
    $message['to'][] = array('email'=>$to_email, 'name'=>$to_name);
    
    if(isset($from_email))
      $message['from_email'] = $from_email;
    else
      $message['from_email'] = $GLOBALS['X']['SETTINGS']['mandrill']['from_email'];
      
    if(isset($from_name))
      $message['from_name'] = $from_name;
    else
      $message['from_name'] = $GLOBALS['X']['SETTINGS']['mandrill']['from_name'];
    
    $message['important'] = false;
    $message['track_opens'] = true;
    $message['track_clicks'] = true;
    $message['auto_text'] = true;
    $message['auto_html'] = true;
    
    foreach ($merge as $key => $value) {
      $message['global_merge_vars'][] = array('name' => $key, 'content' => value);      
    }
    
    $sender = new Mandrill();
    
    return $sender->send($message);    
  }
  
  /** 
  * Sends an email using a Mandrill template
  * @param string Subject line of the email
  * @param array Merge variable content (in format key=variable name, value=content), for example {* (:EVENTNAME "March social" :EVENTDATE "March 22")}
  * @param string Email address of recipient
  * @param string Name of recipient
  * @param string From email address (if different from default)
  * @param string From name (if different from default)
  * @param string Template name
  * @param string Template content
  * @return string 
  * @key admin
  */ 
  static function send_template($subject, $merge, $to_email, $to_name=NULL, $from_email=NULL, $from_name=NULL, $template=NULL, $template_content=NULL)
  {
    $message['html'] = "";
    $message['text'] = "";
    $message['subject'] = $subject;
    $message['to'][] = array('email'=>$to_email, 'name'=>$to_name);
    
    if(isset($from_email))
      $message['from_email'] = $from_email;
    else
      $message['from_email'] = $GLOBALS['X']['SETTINGS']['mandrill']['from_email'];
      
    if(isset($from_name))
      $message['from_name'] = $from_name;
    else
      $message['from_name'] = $GLOBALS['X']['SETTINGS']['mandrill']['from_name'];
    
    $message['important'] = false;
    $message['track_opens'] = true;
    $message['track_clicks'] = true;
    $message['auto_text'] = true;
    $message['auto_html'] = true;
    $message['inline_css'] = true;
    
    foreach ($merge as $key => $value) {
      $message['global_merge_vars'][] = array('name' => $key, 'content' => $value);      
    }
    
    $sender = new Mandrill();
    
    if (!isset($template))
      $template = $GLOBALS['X']['SETTINGS']['mandrill']['template_name'];
    
    if(!isset($template_content)){
      $template_content = array();
    }
    
    return $sender->send_template($message, $template, $template_content);

  }
  
}