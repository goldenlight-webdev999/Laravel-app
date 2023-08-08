<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

/**
 * Users
 *
 * This model represents user authentication data. It operates the following tables:
 * - user account data,
 * - user profiles
 *
 * @package  Tank_auth
 * @author  Ilya Konyukhov (http://konyukhov.com/soft/)
 */
class Users extends CI_Model {
  private $table_name = 'Accounts';      // user accounts
  private $profile_table_name = 'user_profiles';  // user profiles

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $ci->config->item('db_table_prefix', 'tank_auth') . $this->table_name;
    //$this->profile_table_name	= $ci->config->item('db_table_prefix', 'tank_auth').$this->profile_table_name;
  }

  /**
   * Get user record by Id
   *
   * @param int
   * @param bool
   * @return  object
   */
  function get_user_by_id($userId, $approved, $agreed) {
    $this->db->where('userId', $userId);
    // $this->db->where('approved1', $approved ? 1 : 0);
    // $this->db->where('agreed', $agreed ? 1 : 0);

    $query = $this->db->get($this->table_name);
    if($query->num_rows() == 1) {
      return $query->row();
    }

    return null;
  }

  /**
   * Get user record by login (username or email)
   *
   * @param string
   * @return  object
   */
  function get_user_by_login($login) {
    $query = $this->db->get($this->table_name);
    if($query->num_rows() == 1) {
      return $query->row();
    }

    return null;
  }

  /**
   * Get user record by email
   *
   * @param string
   * @return  object
   */
  function get_user_by_email($email) {
    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $this->dblogin->where('LOWER(email)', strtolower($email));

    $query = $this->dblogin->get($this->table_name);
    if($query->num_rows() == 1) {
      return $query->row();
    }

    return null;
  }

  /**
   * Check if email available for registering
   *
   * @param string
   * @return  bool
   */
  function is_email_available($email) {
    $this->db->select('1', false);
    $this->db->where('LOWER(email)=', strtolower($email));

    $query = $this->db->get($this->table_name);

    return $query->num_rows() == 0;
  }

  /**
   * Create new user record
   *
   * @param array
   * @param bool
   * @return  array
   */
  function create_user($data, $activated = true) {

    // if (! array_key_exists('role_id', $data))
    // {
    // 	if($this->admin_not_present())
    // 	{
    // 		$data['role_id'] = 5;
    // 	}
    // 	else
    // 	{
    // 		$data['role_id'] = $this->default_role();
    // 	}
    // }

    if($this->db->insert($this->table_name, $data)) {
      $userId = $this->db->insert_id();

      return ['userId' => $userId];
    }

    return null;
  }

  /**
   * Create modify user record
   *
   * @param array
   * @param bool
   * @return  array
   */

  function modify_user($data) {
    $this->db->set('password', $data['password']);
    $this->db->set('last_ip', $data['last_ip']);
    $this->db->set('title', $data['title']);
    $this->db->set('address1', $data['address1']);
    $this->db->set('address2', $data['address2']);
    $this->db->set('city', $data['city']);
    $this->db->set('state', $data['state']);
    $this->db->set('postalCode', $data['postalCode']);
    $this->db->set('phone', $data['phone']);
    $this->db->set('mobilePhone', $data['mobilePhone']);
    $this->db->set('new_password_requested', date('Y-m-d H:i:s'));
    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));

    $this->db->where('userId', $data['userId']);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  function modify_main_dma_user($data) {
    $this->db->set('last_ip', $data['last_ip']);
    $this->db->set('address1', $data['address1']);
    $this->db->set('address2', $data['address2']);
    $this->db->set('city', $data['city']);
    $this->db->set('state', $data['state']);
    $this->db->set('postalCode', $data['postalCode']);
    $this->db->set('phone', $data['phone']);
    $this->db->set('mobilePhone', $data['mobilePhone']);
    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));

    $this->db->where('userId', $data['userId']);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  /**
   * Check if a user with admin role exists
   *
   * @return  bool
   */

  function admin_not_present() {
    $this->db->where('role_id', 1);
    if($this->db->count_all_results($this->table_name) == 0) {
      return true;
    }
  }

  /**
   * Get the default role for users
   *
   * @return  int
   */

  function default_role() {
    $this->db->where('default', 1);
    $query = $this->db->get('AdminRoles');

    $row = $query->row_array();

    return $row['userId'];
  }

  /**
   * Get role for role_id
   *
   * @return  string
   */

  function get_role($role_id) {
    $this->db->where('userId', $role_id);
    $query = $this->db->get('AdminRoles');

    $row = $query->row_array();

    return $row['role'];
  }

  /**
   * Get role for role_id
   *
   * @return  string
   */

  function get_roles() {
    $query = $this->db->get('AdminRoles');

    $row = $query->row_array();

    return $row['role'];
  }

  /**
   * Get permissions for role
   *
   * @return  string
   */

  function get_permissions($role_id) {
    $this->db->where('userId', $role_id);
    $query = $this->db->get('AdminRolesRules');
    $row = $query->row_array();
    foreach($row as $rowkey => $rowvalue) {
      if($rowvalue == 0) {
        unset($row[$rowkey]);
      }
    }

    return $row;
  }

  /**
   * Activate user if activation key is valid.
   * Can be called for not activated users only.
   *
   * @param int
   * @param string
   * @param bool
   * @return  bool
   */
  function activate_user($userId, $activation_key, $activate_by_email) {
    $this->db->select('1', false);
    $this->db->where('userId', $userId);
    if($activate_by_email) {
      $this->db->where('new_email_key', $activation_key);
    } else {
      $this->db->where('new_password_key', $activation_key);
    }
    $this->db->where('email_verified', 0);
    $query = $this->db->get($this->table_name);

    if($query->num_rows() == 1) {

      $this->db->set('email_verified', 1);
      $this->db->set('new_email_key', null);

      $this->db->set('updatedTime', date('Y-m-d H:i:s'));
      $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
      $this->db->set('updatedBy', $this->cisession->userdata('userId'));
      $this->db->where('userId', $userId);
      $this->db->update($this->table_name);

      //			$this->create_profile($userId);
      return true;
    }

    return false;
  }

  /**
   * Purge table of non-activated users
   *
   * @param int
   * @return  void
   */
  function purge_na($expire_period = 172800) {
    $this->db->where('activated', 0);
    $this->db->where('UNIX_TIMESTAMP(created) <', time() - $expire_period);
    $this->db->delete($this->table_name);
  }

  /**
   * Delete user record
   *
   * @param int
   * @return  bool
   */
  function delete_user($userId) {
    $this->db->where('userId', $userId);
    $this->db->delete($this->table_name);
    if($this->db->affected_rows() > 0) {
      //	$this->delete_profile($userId);
      return true;
    }

    return false;
  }

  /**
   * Set new password key for user.
   * This key can be used for authentication when resetting user's password.
   *
   * @param int
   * @param string
   * @return  bool
   */
  function set_password_key($userId, $new_pass_key) {
    $this->db->set('new_password_key', $new_pass_key);
    $this->db->set('new_password_requested', date('Y-m-d H:i:s'));
    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));
    $this->db->set('resetPasswordFlag', 0);

    $this->db->where('userId', $userId);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  /**
   * Check if given password key is valid and user is authenticated.
   *
   * @param int
   * @param string
   * @param int
   * @return  void
   */
  function can_reset_password($userId, $new_pass_key, $expire_period = 900) {
    $this->db->select('1', false);
    $this->db->where('userId', $userId);
    $this->db->where('new_password_key', $new_pass_key);
    $this->db->where('UNIX_TIMESTAMP(new_password_requested) >', time() - $expire_period);

    $query = $this->db->get($this->table_name);

    return $query->num_rows() == 1;
  }

  /**
   * Change user password if password key is valid and user is authenticated.
   *
   * @param int
   * @param string
   * @param string
   * @param int
   * @return  bool
   */
  function reset_password($userId, $new_pass, $new_pass_key, $pwLength, $expire_period = 900) {
    $this->db->set('password', $new_pass);
    $this->db->set('new_password_key', null);
    $this->db->set('new_password_requested', null);
    $this->db->set('pwCharacterCount', $pwLength);

    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));

    $this->db->where('userId', $userId);
    $this->db->where('new_password_key', $new_pass_key);
    $this->db->where('UNIX_TIMESTAMP(new_password_requested) >=', time() - $expire_period);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  /**
   * Change user password
   *
   * @param int
   * @param string
   * @return  bool
   */
  function change_password($userId, $new_pass) {
    $this->db->set('password', $new_pass);
    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));

    $this->db->where('userId', $userId);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  /**
   * Set new email for user (may be activated or not).
   * The new email cannot be used for login or notification before it is activated.
   *
   * @param int
   * @param string
   * @param string
   * @param bool
   * @return  bool
   */
  function set_new_email($userId, $new_email, $new_email_key, $activated) {
    $this->db->set($activated ? 'new_email' : 'email', $new_email);
    $this->db->set('new_email_key', $new_email_key);
    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));

    $this->db->where('userId', $userId);
    $this->db->where('activated', $activated ? 1 : 0);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  /**
   * Activate new email (replace old email with new one) if activation key is valid.
   *
   * @param int
   * @param string
   * @return  bool
   */
  function activate_new_email($userId, $new_email_key) {
    $this->db->set('email', 'new_email', false);
    $this->db->set('new_email', null);
    $this->db->set('new_email_key', null);
    $this->db->set('updatedTime', date('Y-m-d H:i:s'));
    $this->db->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->db->set('updatedBy', $this->cisession->userdata('userId'));

    $this->db->where('userId', $userId);
    $this->db->where('new_email_key', $new_email_key);

    $this->db->update($this->table_name);

    return $this->db->affected_rows() > 0;
  }

  /**
   * Update user login info, such as IP-address or login time, and
   * clear previously generated (but not activated) passwords.
   *
   * @param int
   * @param bool
   * @param bool
   * @return  void
   */
  function update_login_info($userId, $record_ip, $record_time) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    if($record_ip) {
      $this->dblogin->set('last_ip', $record_ip);
    }
    if($record_time) {
      $this->dblogin->set('last_login', date('Y-m-d H:i:s'));
    }
    $this->dblogin->set('updatedTime', date('Y-m-d H:i:s'));
    $this->dblogin->set('updatedAdminBy', $this->cisession->userdata('auserId'));
    $this->dblogin->set('updatedBy', $this->cisession->userdata('userId'));

    $this->dblogin->where('userId', $userId);
    $this->dblogin->update($this->table_name);
  }

  /**
   * Ban user
   *
   * @param int
   * @param string
   * @return  void
   */
  function ban_user($userId, $reason = null) {
    $this->db->where('userId', $userId);
    $this->db->update($this->table_name, [
        'banned'     => 1,
        'ban_reason' => "Your account is not yet approved, or has been temporarily disabled.",
    ]);
  }

  /**
   * Agree to rules
   *
   * @param int
   * @return  void
   */
  function make_agree($userId = '0') {
    $this->db->where('userId', $userId);
    $this->db->update($this->table_name, [
        'agreed'         => 1,
        'agreed_date'    => date('Y-m-d H:i:s'),
        'updatedTime'    => date('Y-m-d H:i:s'),
        'updatedAdminBy' => $this->cisession->userdata('auserId'),
        'updatedBy'      => $this->cisession->userdata('userId'),
    ]);
  }

  function get_my_dma_accounts($userId) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $this->dblogin->select('dmaMembers.*, dmaAccounts.*');
    $this->dblogin->where('dmaMembers.dmaMUserId', $userId);
    $this->dblogin->where('dmaMembers.dmaMStatus', 1);
    $this->dblogin->from('dmaMembers');
    $this->dblogin->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');

    $query = $this->dblogin->get();

    return $query->result_array();

  }

  function insert_active_session_in_db($userId, $activeSessionId) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $expires = time() + 900; //900 = 15 minutes

    $data = [
        'activeSessionId'      => $activeSessionId,
        'activeSessionExpires' => $expires,
    ];

    $this->dblogin->where('Accounts.userId', $userId);
    $this->dblogin->update('Accounts', $data);

    return $userId;

  }

  function extend_active_session_in_db($userId, $activeSessionId, $extensionTimeSeconds = null) {

    $this->db->select('activeSessionExpires, activeSessionId');
    $this->db->where('userId', $userId);
    $this->db->from('Accounts');
    $query = $this->db->get();

    $dbActiveSessionId = $query->row()->activeSessionId;
    $activeSessionExpires = $query->row()->activeSessionExpires;

    //echo $dbActiveSessionId." == ".$activeSessionId."  --  ".$activeSessionExpires." < ".time()." //";
    //If a session ID exists, and it's equal to the one being submitted here
    if($dbActiveSessionId != "" && $dbActiveSessionId == $activeSessionId) {

      //If session expiration is before now, then log out the user
      if($activeSessionExpires < time()) {

        return "session_expired";

        //Else, if the query is not empty...
      } else {

        $expires = time() + ($extensionTimeSeconds ?? $this->config->item('sessionExtensionTime') * 60);

        $data = [
            'activeSessionExpires' => $expires,
        ];

        $this->db->where('Accounts.userId', $userId);
        $this->db->where('Accounts.activeSessionId', $activeSessionId);
        $this->db->update('Accounts', $data);

        return "session_ok";

      }

    } else {
      if($dbActiveSessionId != "") {

        //This means there is a session ID, but not this one, which means user has logged in from another location
        return "double_session";

      } else {

        //Else there is no session (which should have already been caught in the prior function "is_logged_in()" in "TankAuth", which calls this function)
        return "no_session";

      }
    }

  }

  function erase_active_session_in_db($userId, $sessionId) {

    $data = [
        'activeSessionID'      => null,
        'activeSessionExpires' => null,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->where('Accounts.activeSessionId', $sessionId);
    $this->db->update('Accounts', $data);

  }

}

/* End of file gs.php */
/* Location: ./application/models/auth/users.php */
