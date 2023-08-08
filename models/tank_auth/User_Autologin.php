<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

/**
 * User_Autologin
 *
 * This model represents user autologin data. It can be used
 * for user verification when user claims his autologin passport.
 *
 * @package  Tank_auth
 * @author  Ilya Konyukhov (http://konyukhov.com/soft/)
 */
class User_Autologin extends CI_Model {
  private $table_name = 'user_autologin';
  private $users_table_name = 'Accounts';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $ci->config->item('db_table_prefix', 'tank_auth') . $this->table_name;
    $this->users_table_name = $ci->config->item('db_table_prefix', 'tank_auth') . $this->users_table_name;
  }

  /**
   * Get user data for auto-logged in user.
   * Return NULL if given key or user ID is invalid.
   *
   * @param int
   * @param string
   * @return  object
   */
  function get($userId, $key) {
    $this->db->select('*');
    //$this->db->select($this->users_table_name.'.username');
    $this->db->from($this->users_table_name);
    $this->db->join($this->table_name, $this->table_name . '.userId = ' . $this->users_table_name . '.userId');
    $this->db->where($this->table_name . '.user_id', $userId);
    $this->db->where($this->table_name . '.key_id', $key);
    $query = $this->db->get();
    if($query->num_rows() == 1) {
      return $query->row();
    }

    return null;
  }

  /**
   * Save data for user's autologin
   *
   * @param int
   * @param string
   * @return  bool
   */
  function set($userId, $key) {
    return $this->db->insert($this->table_name, [
        'user_id'    => $userId,
        'key_id'     => $key,
        'user_agent' => substr($this->input->user_agent(), 0, 149),
        'last_ip'    => $this->input->ip_address(),
    ]);
  }

  /**
   * Delete user's autologin data
   *
   * @param int
   * @param string
   * @return  void
   */
  function delete($userId, $key) {
    $this->db->where('user_id', $userId);
    $this->db->where('key_id', $key);
    $this->db->delete($this->table_name);
  }

  /**
   * Delete all autologin data for given user
   *
   * @param int
   * @return  void
   */
  function clear($userId) {
    $this->db->where('user_id', $userId);
    $this->db->delete($this->table_name);
  }

  /**
   * Purge autologin data for given user and login conditions
   *
   * @param int
   * @return  void
   */
  function purge($userId) {
    $this->db->where('user_id', $userId);
    $this->db->where('user_agent', substr($this->input->user_agent(), 0, 149));
    $this->db->where('last_ip', $this->input->ip_address());
    $this->db->delete($this->table_name);
  }
}

/* End of file user_autologin.php */
/* Location: ./application/models/auth/user_autologin.php */