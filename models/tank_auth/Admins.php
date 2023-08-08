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
class Admins extends CI_Model {
  private $table_name = 'AdminUsers';      // user accounts
  private $user_table = 'Accounts';      // user accounts
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
  function get_user_by_id($userId) {
    $this->db->where('userId', $userId);
    $query = $this->db->get($this->table_name);
    if($query->num_rows() == 1) {
      return $query->row();
    }

    return null;
  }

  function check_login($id, $pass) {

    // $this->db->where('userId', $id);
    // 		$this->db->where('password', $pass);
    // 		$query = $this->db->get($this->table_name);
    // 		if ($query->num_rows() == 1) return "TRUE";
    // 		return "FALSE";

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
    $this->db->where('LOWER(email)=', strtolower($email));

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
  function get_runas_user_by_email($email) {
    $this->db->where('LOWER(email)=', strtolower($email));

    $query = $this->db->get($this->user_table);
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

    if(!array_key_exists('role_id', $data)) {
      if($this->admin_not_present()) {
        $data['role_id'] = 5;
      } else {
        $data['role_id'] = $this->default_role();
      }
    }

    if($this->db->insert($this->table_name, $data)) {
      $userId = $this->db->insert_id();

      return ['userId' => $userId];
    }

    return null;
  }

  /**
   * Create new admin record
   *
   * @param array
   * @param bool
   * @return  array
   */
  function create_admin($data, $activated = true) {

    if($this->db->insert($this->table_name, $data)) {
      $userId = $this->db->insert_id();

      return ['userId' => $userId];
    }

    return null;
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
   * Get the admins
   *
   * @return  array
   */
  function get_admins() {

    $this->db->select('userId, firstName, lastName, email, role_id');

    $query = $this->db->get('AdminUsers');

    return $query->result();
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

    return $row['id'];
  }

  /**
   * Get role for role_id
   *
   * @return  string
   */

  function get_role($role_id) {
    $this->db->where('id', $role_id);
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
    $this->db->where('id', $role_id);
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
    $this->db->where('id', $userId);
    if($activate_by_email) {
      $this->db->where('new_email_key', $activation_key);
    } else {
      $this->db->where('new_password_key', $activation_key);
    }
    $this->db->where('activated', 0);
    $query = $this->db->get($this->table_name);

    if($query->num_rows() == 1) {

      $this->db->set('activated', 1);
      $this->db->set('new_email_key', null);
      $this->db->where('id', $userId);
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
    $this->db->where('id', $userId);
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
    $this->db->where('id', $userId);

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
    $this->db->where('id', $userId);
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
  function reset_password($userId, $new_pass, $new_pass_key, $expire_period = 900) {
    $this->db->set('password', $new_pass);
    $this->db->set('new_password_key', null);
    $this->db->set('new_password_requested', null);
    $this->db->where('id', $userId);
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
    $this->db->where('id', $userId);

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
    $this->db->where('id', $userId);
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
    $this->db->where('id', $userId);
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
    // $this->db->set('new_password_key', NULL);
    // 	$this->db->set('new_password_requested', NULL);

    if($record_ip) {
      $this->db->set('last_ip', $this->input->ip_address());
    }
    if($record_time) {
      $this->db->set('last_login', date('Y-m-d H:i:s'));
    }

    $this->db->where('userId', $userId);
    $this->db->update($this->table_name);
  }

  /**
   * Ban user
   *
   * @param int
   * @param string
   * @return  void
   */
  function ban_user($userId, $reason = null) {
    $this->db->where('id', $userId);
    $this->db->update($this->table_name, [
        'banned'     => 1,
        'ban_reason' => $reason,
    ]);
  }

  /*
  * @GET ADMIN NAME
  */
  function get_admin_name() {
    $this->db->select('firstName');
    $this->db->where('userId', $this->cisession->userdata('userId'));
    $query = $this->db->get('AdminUsers');
    $result = $query->row_array();

    return $result['firstName'];
  }

  /*
   @ Save Approveal Process Check By Process TYpes 
   @ NEW Dynamic
   @ @param	int , string
   @ @return	string
   */

  function save_approval_process($data) {
    $appId = $this->config->item("app_id");
    $this->db->select('userId');
    $this->db->where('appAccountId', $appId);
    $this->db->where('default', 1);
    $this->db->from('AdminUsers');
    $adminQuery = $this->db->get();
    $adminData = $adminQuery->result_array();
    $data['modified_by'] = $adminData[0]['userId'];
    // GET ALL VARIABLE OF DATA STORE IN VARIABLE
    $process_type = $data['process_type'];
    $process_type_id = $data['process_type_id'];
    $process_check = $data['process_check'];
    $process_check_id = $data['process_check_id'];
    $process_check_value = $data['process_check_value'];
    $modified_by = $data['modified_by'];

    // HERE CHECKING IF SAME RECORD IS ALREADY EXIST 
    $this->db->select('approval_process_id,process_check_value');
    $this->db->where('process_type', $process_type);
    $this->db->where('process_type_id', $process_type_id);
    $this->db->where('process_check_id', $process_check_id);
    //$this->db->where('modified_by',$modified_by); // EO-63
    $this->db->from('approval_process');
    $query = $this->db->get();
    $total_record = $query->num_rows(); // print total rows
    //return $query->result();

    if($total_record == 0) {
      // total record is zero then it will add in database table	
      //var_dump($data);
      //die;
      $this->db->insert('approval_process', $data);
      //echo $this->db->last_query(); // print the query 
    } else {

      // update in table 	
      $result_id = $query->row()->approval_process_id;  // GET THE PROCESS ID AND STORE IN RESULT ID
      if($process_check_value) {  // HERE WE CHECK IF INPUT VALUE IS EXIST 
        if($process_check_value != $query->row()->process_check_value) {
          $this->db->update('approval_process', $data, ['approval_process_id' => $result_id]);
          //	echo $this->db->last_query(); // print the query 
        }
      }
    }

  }

}

/* End of file users.php */
/* Location: ./application/models/auth/users.php */