<?php

/**
 * A user class for all classes of users
 *
 * Member data is typically protected -- this is where non-critical personal data goes, stuff that might be shared with other trusted members. World-viewable data goes in the profile, private data goes in the user.
 *
 * @package mongoplus
 * @author dann toliver
 * @version 1.0
 */

class Member
{

  /**
  * Get some members
  * @param string User ids (the user id and the member id are the same)
  * @param string The member's plan (accepts 1x, 3x, unlimited, cohort and never)
  * @param string The member's status (accepts pending, active, inactive)
  * @param string Supports sort, limit, skip, fields, nofields, count and attrs: {* (:limit 5 :skip "30" :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)}
  * @return array
  * @key __member __lens __exec
  */
  static function find($by_ids=NULL, $by_plan=NULL, $by_status=NULL, $options=NULL)
  {
    if(isset($by_ids))
      $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));

    if(isset($by_plan))
      $query['plan'] = $by_plan;

    if(isset($by_status))
      $query['status'] = $by_status;

    return MongoLib::find_with_perms('members', $query, $options);
  }


  /**
  * Get member history
  *
  * Technically this does a member fetch first, to check perms. It's not fast or pretty, but it gets the job done.
  *
  * @param string User ids (the user id and the member id are the same)
  * @param string Supports sort, limit, skip, fields, nofields, count and attrs: {* (:limit 5 :skip "30" :sort {* (:name "-1")} :nofields (:pcache :scores))} or {* (:fields :name)} or {* (:count :true)} or {* (:tags :nifty)}
  * @return array
  * @key __member __lens __exec
  */
  static function find_history($by_ids=NULL, $options=NULL)
  {
    if(isset($by_ids))
      $query['_id'] = array('$in' => MongoLib::fix_ids($by_ids));

    $members = MongoLib::find_with_perms('members', $query, array('fields' => '_id'));

    foreach($members as $member)
      $member_ids = $member['_id'];

    $cfilter['member']['$in'] = array($member_ids);
    return MongoLib::find('checkins', $cfilter, $options);
  }


  /**
  * Where is this member?
  *
  * Technically this does a member fetch first, to check perms. It's not fast or pretty, but it gets the job done.
  *
  * @param string Member id
  * @return array
  * @key __member __lens __exec
  */
  static function where($is)
  {
    if(isset($is))
      $query['_id'] = MongoLib::fix_id($is);

    $member = reset(MongoLib::find_with_perms('members', $query, array('fields' => '_id')));

    if(!$member)
      return ErrorLib::set_error("Member location unavailable");

    return Admin::where($is);
  }


  /**
  * Register a new member
  * @param string Username
  * @param string Password
  * @return id
  * @key __world
  */
  static function register($username, $password)
  {
    $username = trim($username);
    $password = trim($password);

    if(!$user_id = UserLib::add_user($username, $password))
      return false; // error inside

    // all clear!

    // Members

    // add entry
    $member['_id'] = $user_id;
    $member['status'] = 'pending';
    // $member['plan'] = 'never';
    $member['cron'] = new MongoDate();
    MongoLib::insert('members', $member);

    // add appropriate perms
    PermLib::grant_user_root_perms('members', $user_id, $user_id);
    PermLib::grant_members_view_perms('members', $user_id); // NOTE: are you sure this is right?

    // Profile

    // add entry
    $profile['_id'] = $user_id;
    $profile['type'] = 'member';
    MongoLib::insert('profiles', $profile);

    // add appropriate perms
    PermLib::grant_user_root_perms('profiles', $user_id, $user_id);
    PermLib::grant_permission(array('profiles', $user_id), "world:*", 'view');

    // NOTE: add custom keys here
    // User::add_key($username, 'whatever');

    // add transactions to history
    History::add('profiles', $user_id, array('action' => 'add', 'type' => 'member'));

    // TODO: put email here
    // TODO: move this to a hippo!

    // run the admin template
    $admin_template = ContentLib::get_value_from_handle("template/admin_emailer");
    $admin_template = Processor::process_with_enhancements($admin_template, '__exec', $_POST);

    // mail('concierge@bentobox.net, dann@bentobox.net', 'A new registrant has registered!', $admin_template, "From:thesystem@bentomiso.com");


//     // FIXME: this reliance on POST data is a hacky hacky hack!
//     if($_POST['firstname'] && $_POST['email']) {
//
//       $message = <<<EOT
// Hi {$_POST['firstname']}!
//
// Thanks for registering to try out Miso. We currently have a two-week waiting list for trial seats, but we will let you know just as soon as a spot becomes available. We'd love to have you drop by for games night or a meetup in the meantime, so check out our event calendar for after-hours events: http://bentomiso.com/events
//
// If there is anything we can do to help you or if we can answer any questions, drop us a line at concierge@bentomiso.com or give us a jingle at (416) 848-3702.
//
// See you soon!
//
// --
// Bento Miso
// Queen West collaborative workspace for Web and game workers
// 862 Richmond Street West, Suite 300
// bentomiso.com | @bentomiso
//
// EOT;
//
//       mail($_POST['email'], 'Welcome to Bento Miso!', $message, 'From:concierge@bentomiso.com');
    // }


    return $user_id;
  }

  /**
  * Emails the user matching the given username with a token that can be used to authenticate.
  * @param string Username
  * @return string
  * @key __world
  */
  static function request_reset($username)
  {
    // check user
    if (!$user = UserLib::get_clean_user($username))
      return ErrorLib::set_error("There is no user with that username");

    // get member
    if(!$member = MongoLib::findOne('members', $user['id']))
      return ErrorLib::set_error("No such member exists");

    // set token length
    $length = 20;

    // generate token
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $num_chars = strlen($chars);
    $token = "";

    for ($i=0; $i < $length; $i++) {
      $position = mt_rand(0, $num_chars - 1);
      $token .= $chars[$position];
    }

    // save the token to secrets
    $token_pointer = SecretsLib::add($token);

    // save the token in the member's locker
    $update['locker.token'] = $token_pointer;
    MongoLib::set('members', $member['_id'], $update);

    $basedir = $GLOBALS['X']['VARS']['SITE']['path'];

    $message = sprintf("Hi $username,

We received a password reset request for your account. To reset your password, use the link below:

%s/members/account/change-password?token=%s

If you didn't request a password reset, you can ignore this message and your password will not be changed -- someone probably typed in your username or email address by accident.

- Bit Bazaar", $basedir, $token);

    $subject = "Reset password on DMG.to";

    mail($member['my']['email'], $subject, $message, 'From:info@dmg.to');

    History::add('members', $user['id'], array('action' => 'request_reset'));

    return $username;
  }

  /**
  * Authenticate the user with a temporary token
  * @param string Username
  * @param string Password
  * @param int Length of login (defaults to just this session)
  * @return int
  * @link http://www.paulsrichards.com/2008/07/29/persistent-sessions-with-php/
  * @key __world
  */
  static function authenticate_token($username, $token)
  {
    // get_users scrubs the user for us, and splits the keychain
    $user = reset(UserLib::get_users("username = '$username'"));

    // THINK: u and p errors provide the same message for security purposes, but it might be nice to be nice to our users...
    if(!$user)
      return ErrorLib::set_error("Invalid authentication credentials");

    if($user['disabled'])
      return ErrorLib::set_error("Account disabled");

    // get member
    if(!$member = MongoLib::findOne('members', $user['id']))
      return ErrorLib::set_error("No such member exists");

    // check the token
    //if (!$member_token = $member['locker']['token'])
    //  return ErrorLib::set_error("Could not retrieve token for member");

    if (!$member_token = SecretsLib::get($member['locker']['token']))
      return ErrorLib::set_error("Could not retrieve token for member");

    if($member_token['value'] != $token)
      return ErrorLib::set_error("Token does not match");

    // token checks out
    SessionLib::set_user_session($user);
    SessionLib::add_user_to_globals($user);

    __build_commands();

    // clear the token for that user
    $update['locker.token'] = "";
    MongoLib::set('members', $member['_id'], $update);

    History::add('members', $user['id'], array('action' => 'request_reset completed'));

    return $username;
  }


  /**
  * Check yourself in
  *
  * @return string
  * @key __member
  */
  static function in()
  {
    $for = $GLOBALS['X']['USER']['id'];
    $date = 'now';

    return Admin::in($for, $date);
  }


  /**
  * Check yourself out
  *
  * @return string
  * @key __member
  */
  static function out()
  {
    $for = $GLOBALS['X']['USER']['id'];
    $date = 'now';

    return Admin::out($for, $date);
  }


  /**
  * Pick a plan, any plan
  *
  * @param string The plan (accepts 1x, 3x, unlimited, cohort and never)
  * @return string
  * @key __member
  */
  static function set_plan($to)
  {
    $for = $GLOBALS['X']['USER']['id'];

    return Admin::set_plan($for, $to);
  }


  /**
  * Send an email to myself
  *
  * @param string The message subject
  * @param string The message body
  * @return string
  * @key __exec
  */
  static function sendmail($subject, $body)
  {
    // get myself
    if(!$member = MongoLib::findOne('members', $GLOBALS['X']['USER']['id']))
      return ErrorLib::set_error("No such member exists");
    // all clear!

    mail($member['my']['email'], $subject, $body, 'From:hello@thebitbazaar.com');
  }


  /**
  * Permanently destroy a member (this can *really* mess things up!)
  * @param string Member id
  * @return string
  */
  static function destroy($id)
  {
    // check for production status
    if($GLOBALS['X']['SETTINGS']['production'])
      return ErrorLib::set_error("Destruction on production is strictly verboten!");

    // get member
    if(!$member = MongoLib::findOne('members', $id))
      return ErrorLib::set_error("No such member exists");

    // all clear!

    // add transaction to history
    History::add('members', $id, array('action' => 'destroy', 'was' => $member));

    // destroy the member
    MongoLib::removeOne('members', $id);
    MongoLib::removeOne('profiles', $id); // TODO: make this Profile::destroy, so we get history
    return QueryLib::delete_row_by_id('users', $id); // TODO: make this User::destroy
  }

}

// EOT
