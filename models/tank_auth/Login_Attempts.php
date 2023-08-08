<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

/**
 * Login_Attempts
 *
 * This model serves to watch on all attempts to login on the site
 * (to protect the site from brute-force attack to user database)
 *
 * @package  Tank_auth
 * @author  Ilya Konyukhov (http://konyukhov.com/soft/)
 */
class Login_Attempts extends CI_Model {
  private $table_name = 'login_attempts';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $ci->config->item('db_table_prefix', 'tank_auth') . $this->table_name;
  }

  /**
   * Get number of attempts to login occured from given IP-address or login
   *
   * @param string
   * @param string
   * @return  int
   */
  function get_attempts_num($ip_address, $login) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $timestampCompare = date('Y-m-d H:i:s', time() - 1200);
    $this->dblogin->select('1', false);
    if(strlen($login) > 0) {
      $array = ['login' => $login, 'ip_address' => $ip_address, 'time >' => $timestampCompare];
      $this->dblogin->where($array);
    } else {
      $array = ['ip_address' => $ip_address, 'time >' => $timestampCompare];
      $this->dblogin->where($array);
    }

    $qres = $this->dblogin->get($this->table_name);

    return $qres->num_rows();

  }

  /**
   * Increase number of attempts for given IP-address and login
   *
   * @param string
   * @param string
   * @return  void
   */
  function increase_attempt($ip_address, $login) {
    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $this->dblogin->insert($this->table_name, ['ip_address' => $ip_address, 'login' => $login, 'time' => date('Y-m-d H:i:s')]);
  }

  /**
   * Clear all attempt records for given IP-address and login.
   * Also purge obsolete login attempts (to keep DB clear).
   *
   * @param string
   * @param string
   * @param int
   * @return  void
   */
  function clear_attempts($ip_address, $login, $expire_period = 86400) {
    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $this->dblogin->where(['ip_address' => $ip_address, 'login' => $login]);

    // Purge obsolete login attempts
    //$this->db->or_where('UNIX_TIMESTAMP(time) <', time());

    $this->dblogin->delete($this->table_name);
  }

}

/* End of file login_attempts.php */
/* Location: ./application/models/auth/login_attempts.php */
