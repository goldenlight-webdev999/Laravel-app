<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Members_Model extends CI_Model {

  protected $logger;

  private $table_name = 'Accounts';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $this->table_name;
    $this->check_symbol = "<span class=\"check-symbol\">&#x2713;</span>";
    $this->no_symbol = "<span class=\"no-symbol\">&#x20E0;</span>";

    $this->logger = Logger::getInstance();

  }

  //result 1=login success, 2=login incorrect, 3=password incorrect
  function add_login_history($userId, $login, $result, $password) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $thisIpAddress = $this->get_ip();

    $data = [
        'time'       => date('Y-m-d H:i:s'),
        'ip_address' => $thisIpAddress,
        'result'     => $result,
        'password'   => $password,
        'login'      => $login,
        'userId'     => $userId,
    ];

    $this->dblogin->insert('login_history', $data);

    return $this->dblogin->insert_id();

  }

  function activate_user($userId) {

    $data = [
        'approved'     => 1,
        'agreed'       => 1,
        'approve_date' => date('Y-m-d h:i:s', time()),
        'agreed_date'  => date('Y-m-d h:i:s', time()),
        'last_login'   => date('Y-m-d h:i:s', time()),
    ];

    $this->db->where('userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function lock_user_account_temporarily($email) {

    $memberInfo = $this->get_member_by_email($email);

    $data = [
        'tempLock'      => 1,
        'tempLockUntil' => time() + 1200,
    ];

    $this->db->where('userId', $memberInfo['userId']);
    $this->db->update('Accounts', $data);

  }

  function get_user_lock_info($email) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $this->dblogin->select('tempLock, tempLockUntil');
    $this->dblogin->where('email', $email);
    $query = $this->dblogin->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function remove_user_lock($email) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $memberInfo = $this->get_member_by_email($email);

    $data = [
        'tempLock'      => null,
        'tempLockUntil' => null,
    ];

    $this->dblogin->where('userId', $memberInfo['userId']);
    $this->dblogin->update('Accounts', $data);

  }

  function add_reset_password_history($userId, $hashed_password) {
    $data = [
        'pwrUserId' => $userId,
        'pwrPWhash' => $hashed_password,
        'pwrDate'   => time(),
    ];

    $this->db->insert('password_reset_history', $data);

    return $this->db->insert_id();

  }

  function get_reset_password_history($userId) {

    $this->db->select('password_reset_history.*');
    $this->db->where('password_reset_history.pwrUserId', $userId);
    $this->db->from('password_reset_history');
    $this->db->order_by("password_reset_history.pwrDate DESC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_latest_reset_password_history($userId) {

    $this->db->select('password_reset_history.*');
    $this->db->where('password_reset_history.pwrUserId', $userId);
    $this->db->from('password_reset_history');
    $this->db->order_by("password_reset_history.pwrDate DESC");
    $this->db->limit("1");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function erase_member_session_for_dma($dmaId) {
    $this->db->select('Accounts.userId')
             ->from('Accounts')
             ->join('dmaMembers', 'dmaMembers.dmaMUserId = Accounts.userId', 'left')
             ->join('dmaAccounts', 'dmaMembers.dmaMDmaId = dmaAccounts.dmaId', 'left')
             ->where('dmaAccounts.dmaId', $dmaId)
             ->or_where('dmaAccounts.parentDmaId', $dmaId)
             ->group_by('Accounts.userId');

    $query = $this->db->get();
    foreach($query->result_array() as $data) {
      if($data['userId'] != $this->cisession->userdata('userId')) {
        $this->erase_member_session($data['userId']);
      }
    }

  }

  function erase_member_session($userId) {

    $data = [
        'activeSessionId' => null,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function update_member_confirmation_code($userId) {

    $digits = 4;
    $confCode = rand(pow(10, $digits - 1), pow(10, $digits) - 1);

    $confCodeUntil = time() + $this->config->item('confirmationCodeExpiration');

    $data = [
        'confCode'      => $confCode,
        'confCodeUntil' => $confCodeUntil,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

    return $confCode;

  }

  function check_member_confirmation_code($userId, $confCode) {

    $this->db->select('confCode, confCodeUntil');
    $this->db->where('userId', $userId);
    $this->db->from('Accounts');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      //If DEV allow 1199 through for Danny during demos
      if($this->config->item('environment') == "DEV" && $confCode == 1199) {
        return true;
      }
      //If a code exists, and the expiration is not yet
      if($data["confCode"] > 0 && $data["confCodeUntil"] > time()) {
        if($data["confCode"] == $confCode) {
          return true;
        } else {
          return false;
        }
      }

      return false;
    }

  }

  function erase_member_confirmation_code($userId) {

    $data = [
        'confCode'      => null,
        'confCodeUntil' => null,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function update_mobile_number_on_member($userId, $mobileNumber) {

    $data = [
        'mobilePhone' => $mobileNumber,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function add_mfa_to_member($userId, $ipVerifyMethodReq) {

    $data = [
        'ipVerificationReq' => 1,
        'ipVerifyMethodReq' => $ipVerifyMethodReq,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function get_member_ip_addresses($userId) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $this->dblogin->select('*');
    $this->dblogin->where('userId', $userId);
    $this->dblogin->from('login_locations');

    $query = $this->dblogin->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return;
    } else {
      return [];
    }

  }

  function add_ip_address_to_member($userId, $ipAddress, $mfaStatus) {

    // Load LOGIN database settings
    $CI = &get_instance();
    $this->dblogin = $CI->load->database($this->config->item('db_login_user'), true);

    $data = [
        'userId'    => $userId,
        'ipAddress' => $ipAddress,
        'mfaStatus' => $mfaStatus,
    ];

    $this->dblogin->insert('login_locations', $data);

    return $this->dblogin->insert_id();

  }

  function add_mfa_to_ip_address_on_member($userId, $ipAddress) {

    $data = [
        'mfaStatus' => 1,
    ];

    $this->db->where('userId', $userId);
    $this->db->where('ipAddress', $ipAddress);
    $this->db->update('login_locations', $data);

  }

  function update_member_security_settings($userId, $request) {

    $data = [
        'daysInactiveLimitReq' => $request['daysInactiveLimitReq'],
        'pwCharacterCountReq'  => $request['pwCharacterCountReq'],
        'pwResetDaysReq'       => $request['pwResetDaysReq'],
        'pwReuseCountReq'      => $request['pwReuseCountReq'],
        'pwReuseDaysReq'       => $request['pwReuseDaysReq'],
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function get_member_email($id) {
    $this->db->select('GROUP_CONCAT(email) as email_address');
    $this->db->where('userId', $id);

    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);

    }
    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_current_member($id) {
    $this->db->where('userId', $id);
    $query = $this->db->get('Accounts');

    return $query->row_array();
  }

  function get_main_dma_user($userId) {

    $this->db->select('Accounts.*, dmaAccounts.*');
    $this->db->from('Accounts');
    $this->db->where('userId', $userId);
    $this->db->join("dmaAccounts", "Accounts.userId = dmaAccounts.primary_account_id", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function checkIfSeller($id) {

    $this->db->where('userId', $id);
    $this->db->where('accountType in (4,2,3,1)');
    $this->db->from('Accounts');
    $count = $this->db->count_all_results();

    return ($count > 0) ? true : false;

  }

  function getUserByAppId($appId) {
    //echo $appId;
    //SELECT appAccountId FROM accounts WHERE userId = 9
    $this->db->select('userId');
    $this->db->from('Accounts');
    $this->db->where('appAccountId', $appId);
    $this->db->where('default', 1);
    $query = $this->db->get();
    $userId = $query->result_array();

    return $userId[0]['userId'];
  }

  function getAppId($id) {
    $appId['appAccountId'] = $this->config->item("app_id");

    return $appId;
  }

  function getUserCompanyById($id) {
    //echo $appId;
    //SELECT appAccountId FROM accounts WHERE userId = 9
    $this->db->select('companyName');
    $this->db->from('Accounts');
    $this->db->where('userId', $id);
    $query = $this->db->get();
    $userId = $query->result_array();
    if(sizeof($userId) > 0) {
      return $userId[0];
    }
  }

  function get_dma_member($dmamemberId, $getBids = "", $getTrades = "") {
    $this->db->select('userId, firstName, lastName, Accounts.title, email, dmaMembers.*, dmaAccounts.dmaId, dmaAccounts.title as dmaTitle, dmaGroups.*, Accounts.daysInactiveLimitReq, Accounts.pwCharacterCount, Accounts.pwCharacterCountReq, Accounts.pwReuseDaysReq, Accounts.pwReuseCountReq, Accounts.pwResetDaysReq, dmaAccounts.external_auth_type, dmaAccounts.external_auth_provider');
    $this->db->from('dmaMembers');
    $this->db->where('dmaMembers.dmaMemberId', $dmamemberId);
    $this->db->join("Accounts", "Accounts.userId = dmaMembers.dmaMUserId", 'left');
    $this->db->join("dmaGroups", "dmaMembers.dmaMGroupId = dmaGroups.dmaGroupId", 'left');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($getBids == 1) {
        //$data['bidTransactions'] = $this->Trades->get_bid_transactions_of_taxpayer($data['taxpayerId'], '', 'no');
      } else {
        $data['bidTransactions'] = "";
      }

      if($getTrades == 1) {
        //$data['tradeTransactions'] = $this->Trading->get_trade_transactions_of_taxpayer($data['taxpayerId'], 'all', 'no');
      } else {
        $data['tradeTransactions'] = "";
      }

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function save_dma_member($dmamemberId) {

    $data = [
        'dmaMGroupId' => $this->input->post('memberLevel'),
    ];
    $this->db->where('dmaMemberId', $dmamemberId);
    $this->db->update('dmaMembers', $data);

    $getDmaMemberData = $this->get_dma_member($dmamemberId, 0, 0);

    $data = [
        'firstName' => $this->input->post('mFirstName'),
        'lastName'  => $this->input->post('mLastName'),
        'email'     => $this->input->post('mEmail'),
    ];
    $this->db->where('userId', $getDmaMemberData['dmaMUserId']);
    $this->db->update('Accounts', $data);

  }

  function update_dma_member_status($dmamemberId, $status) {

    $data = [
        'dmaMStatus' => $status,
    ];
    $this->db->where('dmaMemberId', $dmamemberId);
    $this->db->update('dmaMembers', $data);

  }

  function insert_invite($request) {

    //We get today's midnight time UTC and then add just a little less than 6 days to it so we can run a morning script which sends a reminders at various intervals
    $todayMidnight = strtotime('today midnight');
    //7 days = 604,800 --> subtract 2 hrs (10pm UTC = 5pm ET) which is 7200 is 597,600 --> subtract 30 min to get just a little short of 5pm ET which is 1800 --> final = 595800
    $iExpires = $todayMidnight + 595800;

    $iHash = hash('sha256', $this->input->post('mEmail') . time() . mt_rand());

    //Setup expected values across all invite types
    $dataFinal = [
        'iDmaId'      => $this->cisession->userdata('dmaId'),
        'iType'       => $request['iType'],
        'iFirstName'  => $request['iFirstName'],
        'iLastName'   => $request['iLastName'],
        'iEmail'      => $request['iEmail'],
        'iUserId'     => $request['iUserId'],
        'iCreated'    => time(),
        'iExpires'    => null,
        'iFromUserId' => $this->cisession->userdata('userId'),
        'iHash'       => $iHash,
        'iContent1'   => '',
    ];

    //Override with specific information based on type of invite
    if($request['iType'] == 'signatureCustom') {
      $dataFinal['iContent1'] = $request['iContent1'];
    }
    if($request['iType'] == 'creditShare') {
      $dataFinal['iExpires'] = $iExpires;
      $dataFinal['iContent1'] = $request['sId'];
    }
    if($request['iType'] == 'dmaMember') {
      $dataFinal['iExpires'] = $iExpires;
      $dataFinal['iFirstName'] = $request['iFirstName'];
      $dataFinal['iLastName'] = $request['iLastName'];
      $dataFinal['iEmail'] = $request['iEmail'];
      $dataFinal['iContent1'] = $request['iContent1'];
      $dataFinal['iDaysInactiveLimitReq'] = $request['iDaysInactiveLimitReq'];
      $dataFinal['iPwCharacterCountReq'] = $request['iPwCharacterCountReq'];
      $dataFinal['iPwResetDaysReq'] = $request['iPwResetDaysReq'];
      $dataFinal['iPwReuseCountReq'] = $request['iPwReuseCountReq'];
      $dataFinal['iPwReuseDaysReq'] = $request['iPwReuseDaysReq'];
    }

    $this->db->insert('invites', $dataFinal);

    return $this->db->insert_id();

  }

  function insert_reset_invite($savedInvite) {

    //We get today's midnight time UTC and then add just a little less than 3 days to it so we can run a morning script which sends a reminder of invites expiring in less than 24 hrs
    $todayMidnight = strtotime('today midnight');
    $iExpires = $todayMidnight + 258800;

    $iHash = hash('sha256', $this->input->post('mEmail') . time() . mt_rand());

    if($savedInvite['iType'] == 'creditShare') {
      $data = [
          'iDmaId'       => $savedInvite['iDmaId'],
          'iType'        => $savedInvite['iType'],
          'iFirstName'   => $savedInvite['iFirstName'],
          'iLastName'    => $savedInvite['iLastName'],
          'iCompanyName' => $savedInvite['iCompanyName'],
          'iEmail'       => $savedInvite['iEmail'],
          'iCreated'     => time(),
          'iExpires'     => $iExpires,
          'iFromUserId'  => $savedInvite['iFromUserId'],
          'iHash'        => $iHash,
          'iContent1'    => $savedInvite['iContent1'],
      ];
    }

    if($savedInvite['iType'] == 'dmaMember') {
      $data = [
          'iDmaId'      => $savedInvite['iDmaId'],
          'iType'       => $savedInvite['iType'],
          'iFirstName'  => $savedInvite['iFirstName'],
          'iLastName'   => $savedInvite['iLastName'],
          'iEmail'      => $savedInvite['iEmail'],
          'iCreated'    => time(),
          'iExpires'    => $iExpires,
          'iFromUserId' => $savedInvite['iFromUserId'],
          'iHash'       => $iHash,
          'iContent1'   => $savedInvite['iContent1'],
      ];
    }

    if($savedInvite['iType'] == 'signature') {
      $data = [
          'iDmaId'      => $savedInvite['iDmaId'],
          'iType'       => $savedInvite['iType'],
          'iFirstName'  => $savedInvite['iFirstName'],
          'iLastName'   => $savedInvite['iLastName'],
          'iEmail'      => $savedInvite['iEmail'],
          'iCreated'    => time(),
          'iFromUserId' => $savedInvite['iFromUserId'],
          'iExpires'    => $iExpires,
          'iFromUserId' => $savedInvite['iFromUserId'],
          'iHash'       => $iHash,
          'iContent1'   => $savedInvite['iContent1'],
          'iContent2'   => $savedInvite['iContent2'],
      ];
    }

    $this->db->insert('invites', $data);

    return $this->db->insert_id();

  }

  function get_invite($inviteId = "", $iType = "", $iContent1 = "") {

    //PROTECT - ACCESS LEVEL CONTROL
    if($inviteId > 0 || ($iType != "" && $iContent1 != "")) {
    } else {
      throw new \Exception('General fail');
    }

    $this->db->select('invites.*, dmaAccounts.*, dmaGroups.dmaGTitle, Accounts.firstName, Accounts.lastName, Accounts.email');
    $this->db->from('invites');
    if($inviteId != "") {
      $this->db->where('invites.inviteId', $inviteId);
    }
    if($iType != "") {
      $this->db->where('invites.iType', $iType);
    }
    if($iContent1 != "") {
      $this->db->where('invites.iContent1', $iContent1);
    }
    $this->db->join("dmaGroups", "invites.iContent1 = dmaGroups.dmaGroupId", 'left');
    $this->db->join("Accounts", "invites.iFromUserId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "invites.iDmaId = dmaAccounts.dmaId", 'left');
    $this->db->order_by('invites.inviteId DESC');
    $this->db->limit(1);

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function save_invite($inviteId) {

    $iExpires = time() + 260000;

    $data = [
        'iFirstName' => $this->input->post('mFirstName'),
        'iLastName'  => $this->input->post('mLastName'),
        'iEmail'     => $this->input->post('mEmail'),
        'iContent1'  => $this->input->post('memberLevel'),
        'iExpires'   => $iExpires,
    ];

    $this->db->where('inviteId', $inviteId);
    $this->db->update('invites', $data);

    //return $this->db->insert_id();

  }

  function save_invite_full($inviteId, $data) {

    $iExpires = time() + 260000;

    $data = [
        'iFirstName'            => $this->input->post('mFirstName'),
        'iLastName'             => $this->input->post('mLastName'),
        'iEmail'                => $this->input->post('mEmail'),
        'iContent1'             => $this->input->post('memberLevel'),
        'iExpires'              => $iExpires,
        'iDaysInactiveLimitReq' => $data['iDaysInactiveLimitReq'],
        'iPwCharacterCountReq'  => $data['iPwCharacterCountReq'],
        'iPwResetDaysReq'       => $data['iPwResetDaysReq'],
        'iPwReuseCountReq'      => $data['iPwReuseCountReq'],
        'iPwReuseDaysReq'       => $data['iPwReuseDaysReq'],
    ];

    $this->db->where('inviteId', $inviteId);
    $this->db->update('invites', $data);

    //return $this->db->insert_id();

  }

  function extend_invite($inviteId) {

    $iExpires = time() + 260000;

    $data = [
        'iExpires' => $iExpires,
    ];

    $this->db->where('inviteId', $inviteId);
    $this->db->update('invites', $data);

  }

  function unset_invite($inviteId) {
    $data = [
        'iReset'        => 1,
        'iDeleteMarker' => 1,
    ];

    $this->db->where('inviteId', $inviteId);
    $this->db->update('invites', $data);

  }

  function delete_invite($inviteId) {
    $data = [
        'iDeleteMarker' => 1,
    ];

    $this->db->where('inviteId', $inviteId);
    $this->db->update('invites', $data);

  }

  function get_dma_invites($dmaId, $iType) {

    if($iType == 'dmaMember') {
      $this->db->select('invites.*, dmaGroups.dmaGTitle, Accounts.firstName, Accounts.lastName, Accounts.email');
    } else {
      $this->db->select('invites.*');
    }
    $this->db->where('invites.iDmaId', $dmaId);
    $this->db->where('invites.iType', $iType);
    $this->db->where('invites.iDeleteMarker', null);
    $this->db->from('invites');
    if($iType == 'dmaMember') {
      $this->db->join("dmaGroups", "invites.iContent1 = dmaGroups.dmaGroupId", 'left');
    }
    $this->db->join("Accounts", "invites.iFromUserId = Accounts.userId", 'left');
    $this->db->order_by("invites.iExpires desc");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_invite_by_hash($iHash) {

    $this->db->select('invites.*, dmaGroups.dmaGTitle, dmaAccounts.dmaId, dmaAccounts.title, dmaAccounts.mainAdmin, dmaAccounts.primary_account_id, dmaAccounts.profileUrl, dmaAccounts.external_auth_type, dmaAccounts.external_auth_provider, Accounts.companyName, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.userId');
    $this->db->from('invites');
    $this->db->where('invites.iHash', $iHash);
    $this->db->join("dmaAccounts", "invites.iDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("dmaGroups", "invites.iContent1 = dmaGroups.dmaGroupId", 'left');
    $this->db->join("Accounts", "invites.iFromUserId = Accounts.userId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_invite_for_custom_signature($dsId) {

    $this->db->select('invites.*');
    $this->db->from('invites');
    $this->db->where('invites.iType', 'signatureCustom');
    $this->db->where('invites.iContent1', $dsId);
    $this->db->order_by("inviteId DESC");
    $this->db->limit(1);

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_invite_signature_by_email($email, $transactionId) {

    $this->db->select('invites.*, dmaGroups.dmaGTitle, dmaAccounts.dmaId, dmaAccounts.title, dmaAccounts.profileUrl, Accounts.companyName, Accounts.firstName, Accounts.lastName, Accounts.email');
    $this->db->from('invites');
    $this->db->where('invites.iEmail', $email);
    $this->db->where('invites.iContent1', 'buy');
    $this->db->where('invites.iContent2', $transactionId);
    $this->db->join("dmaAccounts", "invites.iDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("dmaGroups", "invites.iContent1 = dmaGroups.dmaGroupId", 'left');
    $this->db->join("Accounts", "invites.iFromUserId = Accounts.userId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_invites_expiring($seconds_back, $seconds_forward, $iType) {

    $checkAhead = time() + $seconds_forward;
    $checkBehind = time() - $seconds_back;

    if($iType == 'dmaMember') {
      $this->db->select('invites.*, dmaGroups.dmaGTitle, Accounts.firstName, Accounts.lastName, Accounts.email');
    } else {
      $this->db->select('invites.*, Transactions.tradeId, Transactions.tCreditAmount, PendingListings.listingId, IncentivePrograms.State as stateCode');
    }

    $array = ['invites.iExpires <' => $checkAhead, 'invites.iExpires >' => $checkBehind, 'invites.iType' => $iType, 'invites.iDeleteMarker' => null];
    $this->db->where($array);

    $this->db->from('invites');
    if($iType == 'dmaMember') {
      $this->db->join("dmaGroups", "invites.iContent1 = dmaGroups.dmaGroupId", 'left');
    }
    if($iType == 'signature') {
      $this->db->join("Transactions", "invites.iContent2 = Transactions.transactionId", 'left');
      $this->db->join("Trades", "Transactions.tradeId = Trades.tradeId", 'left');
      $this->db->join("PendingListings", "Trades.listingId = PendingListings.listingId", 'left');
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    }
    $this->db->join("Accounts", "invites.iFromUserId = Accounts.userId", 'left');
    $this->db->order_by("invites.iExpires desc");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_invites_pending_of_user() {

    $userData = $this->get_member_by_id($this->cisession->userdata('userId'));

    $this->db->select('invites.*');

    $array = ['invites.iEmail' => $userData['email'], 'invites.iReset' => 0, 'invites.iDeleteMarker' => null];
    $this->db->where($array);

    $this->db->from('invites');
    $this->db->order_by("invites.iExpires desc");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_dma_autoshare_accounts($dmaId, $saType = "") {

    $this->db->select('SharesAuto.*');
    $this->db->where('SharesAuto.saDmaIdFrom', $dmaId);
    if($saType != "") {
      $this->db->where('SharesAuto.saType', $saType);
    }
    $this->db->from('SharesAuto');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function insert_dmamember($inviteId, $userId = null) {

    //get the invite
    $data['invite'] = $this->get_invite($inviteId);

    //create the member record
    $data = [
        'dmaMUserId'    => $this->cisession->userdata('userId'),
        'dmaMDmaId'     => $data['invite']['iDmaId'],
        'dmaMGroupId'   => $data['invite']['iContent1'],
        'dmaMStatus'    => 1,
        'dmaMAddedBy'   => $data['invite']['iFromUserId'],
        'dmaMDateAdded' => time(),
    ];
    if(isset($userId)) {
      $data['dmaMUserId'] = $userId;
    }

    $this->db->insert('dmaMembers', $data);

    //Mark invite as deleted
    $this->delete_invite($inviteId);

    //Update to proper DMA status and company name
    $this->update_member_to_dma($this->cisession->userdata('userId'), $data['invite']['title']);

    return $this->db->insert_id();

  }

  function create_dmamember($request) {

    $dmaMUserId = isset($request['dmaMUserId']) ? $request['dmaMUserId'] : null;
    $dmaMDmaId = isset($request['dmaMDmaId']) ? $request['dmaMDmaId'] : null;
    $dmaMGroupId = isset($request['dmaMGroupId']) ? $request['dmaMGroupId'] : null;
    $dmaMAddedBy = isset($request['dmaMAddedBy']) ? $request['dmaMAddedBy'] : null;
    $dmaMParentAdminFlag = isset($request['dmaMParentAdminFlag']) ? $request['dmaMParentAdminFlag'] : null;
    $dmaMJoinDate = isset($request['dmaMJoinDate']) ? $request['dmaMJoinDate'] : null;

    if($dmaMUserId > 0 && $dmaMDmaId > 0 && $dmaMGroupId > 0) {
      $existingMember = $this->get_dmamember($dmaMUserId, $dmaMDmaId);
      if(isset($existingMember['dmaMemberId'])) {
        $data = [
            'dmaMGroupId'         => $dmaMGroupId,
            'dmaMStatus'          => 1,
            'dmaMParentAdminFlag' => $dmaMParentAdminFlag,
        ];

        $this->db->where('dmaMembers.dmaMemberId', $existingMember['dmaMemberId']);
        $this->db->update('dmaMembers', $data);

        return $existingMember['dmaMemberId'];
      }
      else {
        //create the member record
        $data = [
            'dmaMUserId'          => $dmaMUserId,
            'dmaMDmaId'           => $dmaMDmaId,
            'dmaMGroupId'         => $dmaMGroupId,
            'dmaMStatus'          => 1,
            'dmaMAddedBy'         => $dmaMAddedBy,
            'dmaMParentAdminFlag' => $dmaMParentAdminFlag,
            'dmaMJoinDate'        => $dmaMJoinDate,
            'dmaMDateAdded'       => time(),
        ];

        $this->db->insert('dmaMembers', $data);

        return $this->db->insert_id();
      }
    }

  }

  function get_dmamember($userId, $dmaId) {
    $this->db->select('dmaMembers.*');
    $this->db->where('dmaMembers.dmaMUserId', $userId);
    $this->db->where('dmaMembers.dmaMDmaId', $dmaId);
    $this->db->from('dmaMembers');

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }


  function check_if_user_email_exists($email) {

    $this->db->select('userId');
    $this->db->where('email', $email);
    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);

    }

    if(sizeof($return) > 0) {
      return 1;
    } else {
      return 0;
    }

  }

  function check_if_dmamember_email_exists($email, $dmaId) {

    $emailCount = 0;

    //Get user ID of email, if it exists
    $memberInfo = $this->get_member_by_email($email);

    //If it exists, then use the user ID to check membership to this DMA account
    if(sizeof($memberInfo) > 0) {

      $userId = $memberInfo['userId'];

      $this->db->select('dmaMemberId');
      $this->db->where('dmaMUserId', $userId);
      $this->db->where('dmaMDmaId', $dmaId);
      $query = $this->db->get('dmaMembers');

      $return = [];
      foreach($query->result_array() as $data) {
        array_push($return, $data);
      }

      //If there is already a member, increment flag
      if(sizeof($return) > 0) {
        $emailCount++;
      }
    }

    //Then, check invites on this DMA for this email address
    $this->db->select('inviteId');
    $this->db->where('iEmail', $email);
    $this->db->where('iDmaId', $dmaId);
    $this->db->where('iType', 'dmaMember');
    $this->db->where('iDeleteMarker', null);
    $query = $this->db->get('invites');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    //If there is already an invite, increment flag
    if(sizeof($return) > 0) {
      $emailCount++;
    }

    //If the email exists, return TRUE
    if($emailCount > 0) {
      return true;
    } else {
      return false;
    }

  }

  function get_member_by_id($userId) {

    $this->db->select('*');
    $this->db->where('userId', $userId);
    $this->db->from('Accounts');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($data["accountType"] == 4) {
        $data["dmaAccounts"] = $this->get_dma_accounts_of_user($data['userId']);
        if(sizeof($data["dmaAccounts"]["all"]) > 0) {
          $data["companyName"] = $data["dmaAccounts"]["all"][0]["title"];
        } else {
          $data["companyName"] = $data["companyName"];
        }
      }
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_member_by_email($email) {

    $this->db->select('*');
    $this->db->where('LOWER(email)', strtolower($email));
    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_dma_member_by_email($email) {
    $this->db->select('userId, firstName, lastName, email, new_password_key');
    $this->db->from('Accounts');
    $this->db->where('Accounts.email', $email);

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['dmaAccounts'] = $this->get_dma_accounts_of_user($data['userId']);
      array_push($return, $data);

    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function update_member_field($userId, $field, $value) {

    $data = [
        $field => $value,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function update_member_to_dma($userId, $dmaTitle) {

    $data = [
        'accountType' => 4,
        'profile'     => 4,
        'companyName' => $dmaTitle,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function getLoginsSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('Accounts.userId, Accounts.firstName, Accounts.email, Accounts.lastName, Accounts.companyName, Accounts.accountType, Accounts.last_login');
    $this->db->where("last_login >", $checkSince);
    $this->db->order_by("last_login desc");
    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {
      if($data['accountType'] == 4) {
        $dmaAccounts = $this->get_dma_accounts_of_user($data['userId']);
        if(sizeof($dmaAccounts['mainAdmin']) > 0) {
          $data['companyName'] = $dmaAccounts['mainAdmin'][0]['title'];
        } else {
          $data['companyName'] = $dmaAccounts['admin'][0]['title'];
        }
      }

      if($data['accountType'] == 8) {
        $dmaAccounts = $this->get_dma_accounts_of_signer($data['email']);
        $data['companyName'] = $dmaAccounts[0]['title'];
      }

      $OIXAdminIds = [1, 463, 480, 651, 666, 823];
      $OIXAdminEmails = ['theoix.com'];

      if(!in_array($data['userId'], $OIXAdminIds)) {
        if(!in_array(substr(strrchr($data['email'], "@"), 1), $OIXAdminEmails)) {
          array_push($return, $data);
        }
      }
    }

    return $return;

  }

  function get_dma_accounts_of_user($userId) {

    $this->db->select('dmaAccounts.*, dmaMembers.*');
    $this->db->where('dmaMembers.dmaMUserId', $userId);
    $this->db->where('dmaMembers.dmaMStatus', 1);
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->distinct();
    $query = $this->db->get();

    $return['parentDmaFlag'] = false;
    $return['parentDmaId'] = null;
    $return['mainAdmin'] = [];
    $return['admin'] = [];
    $return['all'] = [];

    foreach($query->result_array() as $data) {

      if($data['isParentDma'] == 1) {
        $return['parentDmaFlag'] = true;
        $return['parentDmaId'] = $data['dmaId'];
      }

      if($userId == $data['primary_account_id']) {
        array_push($return['mainAdmin'], $data);
      } else {
        array_push($return['admin'], $data);
      }
      array_push($return['all'], $data);

    }

    return $return;

  }

  function get_dma_accounts_of_signer($email) {

    $this->db->select('Taxpayers.dmaAccountId, dmaAccounts.title, dmaAccounts.profileUrl');
    $this->db->where('Taxpayers.tpEmailSigner', $email);
    $this->db->from('Taxpayers');
    $this->db->join("dmaAccounts", "Taxpayers.dmaAccountId = dmaAccounts.dmaId", 'left');

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_dma_accounts_of_signer_by_id($userId) {

    $this->db->select('Taxpayers.dmaAccountId, dmaAccounts.title, dmaAccounts.profileUrl');
    $this->db->where('Taxpayers.tpAccountId', $userId);
    $this->db->from('Taxpayers');
    $this->db->join("dmaAccounts", "Taxpayers.dmaAccountId = dmaAccounts.dmaId", 'left');

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function getMembershipApplicationsSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('Accounts.userId, Accounts.accountType, Accounts.firstName, Accounts.lastName, Accounts.companyName, Accounts.join_date');
    $this->db->where("join_date >", $checkSince);
    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {

      $isMainAdmin = true;
      $isSigner = false;

      if($data['accountType'] == 4) {

        $data['dmaAccounts'] = $this->get_dma_accounts_of_user($data['userId']);

        foreach($data['dmaAccounts']['all'] as $dma) {
          if($dma['mainAdmin'] == $data['userId']) {
            //Is main admin
          } else {
            //is NOT main admin
            $isMainAdmin = false;
          }
        }

      }

      if($data['accountType'] == 8) {
        $isSigner = true;
      }

      if($isMainAdmin && !$isSigner) {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function getAdminUsersJoinedSince($seconds) {

    $checkSince = time() - $seconds;
    $dmaTypes = ['management', 'shared', 'advisor', 'broker'];

    $this->db->select('dmaAccounts.*, dmaMembers.*, Accounts.userId, Accounts.firstName, Accounts.lastName, Accounts.email');
    $this->db->where("dmaMJoinDate >", $checkSince);
    $this->db->where_in("dmaType", $dmaTypes);
    $this->db->where("accountType", "4");
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "dmaMembers.dmaMUserId = Accounts.userId", 'left');
    $this->db->distinct();
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function getSignersJoinedSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('Taxpayers.dmaAccountId, dmaAccounts.dmaId, dmaAccounts.title, Accounts.userId, Accounts.accountType, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.join_date');
    $this->db->where("accountType", "8");
    $this->db->where("join_date >", $checkSince);
    $this->db->from('Accounts');
    $this->db->join("Taxpayers", "Accounts.email = Taxpayers.tpEmailSigner", 'left');
    $this->db->join("dmaAccounts", "Taxpayers.dmaAccountId = dmaAccounts.dmaId", 'left');
    $this->db->distinct();
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function getNewDMAaccountsSince($seconds) {

    $checkSince = time() - $seconds;

    $dmaTypes = ['management', 'shared', 'advisor', 'broker'];

    $this->db->select('dmaAccounts.*');
    $this->db->where("created >", $checkSince);
    $this->db->where_in("dmaType", $dmaTypes);
    $this->db->from('dmaAccounts');
    $this->db->distinct();
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function getMembershipApprovalsSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('Accounts.userId, Accounts.firstName, Accounts.lastName, Accounts.companyName, Accounts.approve_date');
    $this->db->where("approve_date >", $checkSince);
    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function getMembershipActivatedSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('Accounts.userId, Accounts.firstName, Accounts.lastName, Accounts.companyName, Accounts.agreed_date');
    $this->db->where("agreed_date >", $checkSince);
    $query = $this->db->get('Accounts');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_member_messages_by_userId($mmUserId) {

    $this->db->select('*');
    $this->db->where('mmUserId', $mmUserId);
    $this->db->where('mmWarned', 0);
    $this->db->where('messages.msDeleteMarker', null);
    $this->db->join("messages", "member_messages.mmMessageId = messages.msId", 'left');
    $query = $this->db->get('member_messages');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_messages_of_user($input) {

    $userId = isset($input['userId']) ? $input['userId'] : null;
    $dmaId = isset($input['dmaId']) ? $input['dmaId'] : null;
    $accountType = isset($input['accountType']) ? $input['accountType'] : null;
    $listingId = isset($input['listingId']) ? $input['listingId'] : null;
    $warned = isset($input['warned']) ? $input['warned'] : null;
    $read = isset($input['read']) ? $input['read'] : null;
    $count = isset($input['count']) ? $input['count'] : null;
    $type = isset($input['type']) ? $input['type'] : null;
    $limit = isset($input['limit']) ? $input['limit'] : null;
    $order = isset($input['order']) ? $input['order'] : null;
    $msTypeExcludeArray = isset($input['msTypeExcludeArray']) ? (sizeof($input['msTypeExcludeArray']) > 0 ? $input['msTypeExcludeArray'] : []) : [];
    $msTypeOnly = isset($input['msTypeOnly']) ? (sizeof($input['msTypeOnly']) > 0 ? $input['msTypeOnly'] : []) : [];
    $msActions = isset($input['msActions']) ? (sizeof($input['msActions']) > 0 ? $input['msActions'] : []) : [];

    //PROTECT - ACCESS LEVEL CONTROL
    if($userId > 0) {
    } else {
      throw new \Exception('General fail');
    }

    if(is_numeric($userId) && $userId > 0) {

      $userId = preg_replace('/\D/', '', $userId);

      if($count == "count") {
        $this->db->select('count(member_messages.mmId)');
      } else {
        $this->db->select('member_messages.*, messages.*, Accounts.firstName, Accounts.lastName, ActiveListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, Bids.bidId, Bids.bidSize, Bids.bidPrice, States.state, States.name, countries.code, countries.name as countryName');
      }

      $this->db->from('member_messages');

      $where = "(mmUserId=" . $userId . ") AND (mmDmaId IS NULL";
      if($accountType == 4) {
        //Add to the where claus - This is to filter by the user's current DMA account to not criss-cross data
        if(is_numeric($dmaId) && $dmaId > 0) {
          $dmaId = preg_replace('/\D/', '', $dmaId);
          $where .= " OR mmDmaId=" . $dmaId . ")";
        }
      } else {
        $where .= ")";
      }
      if($warned == 0) {
        $where .= " AND (mmWarned!=1)";
      }
      if($warned == 1) {
        $where .= " AND (mmWarned=1)";
      }
      if($read == 0) {
        $where .= " AND (mmRead!=1)";
      }
      if($read == 1) {
        $where .= " AND (mmRead=1)";
      }
      if($type == "alert") {
        //Over ride the where clause
        $where = "(mmUserId=" . $userId . " AND msType='alert' AND (mmWarned IS NULL OR mmWarned=0))";
      } else {
        if($type == "calendar") {
          $where .= " AND (msType='calendar_alert')";
        } else {
          if($type == "general") {
            $where .= " AND (msType!='calendar_alert')";
          }
        }
      }
      $this->db->where($where);
      if($listingId > 0) {
        $this->db->where('messages.msListingId', $listingId);

      }
      $this->db->where('messages.msDeleteMarker', null);

      if(sizeof($msTypeExcludeArray) > 0) {
        foreach($msTypeExcludeArray as $mte) {
          $this->db->where('messages.msType !=', $mte);
        }
      }
      if(sizeof($msTypeOnly) > 0) {
        foreach($msTypeOnly as $mto) {
          $this->db->where('messages.msType', $mto);
        }
      }
      if(sizeof($msActions) > 0) {
        $this->db->where_in('messages.msAction', $msActions);
      }

      //$this->db->where("mmUserId", $userId);
      $this->db->join("messages", "member_messages.mmMessageId = messages.msId", 'left');
      $this->db->join("Accounts", "messages.msUserIdCreated = Accounts.userId", 'left');
      $this->db->join("PendingListings", "messages.msListingId = PendingListings.listingId", 'left');
      $this->db->join("ActiveListings", "messages.msListingId = ActiveListings.listingId", 'left');
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
      $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
      $this->db->join("countries", "countries.id = States.countryId", 'left');
      $this->db->join("Bids", "messages.msBidId = Bids.bidId", 'left');

      if($order == "mscontent_asc") {
        $this->db->order_by("msContent ASC");
      } else {
        $this->db->order_by("mmCreatedDate DESC");
      }
      if($limit > 0) {
        $this->db->limit($limit);
      } else {
        $this->db->limit(100);
      }
      $query = $this->db->get();

      if($count == "count") {

        $cnt = $query->row_array();

        return $cnt['count(member_messages.mmId)'];

      } else {

        $return = [];

        foreach($query->result_array() as $data) {

          $data['thisMessageUrl'] = "";

          //CUSTOM
          if($data['msAction'] == "custom") {
            $data['thisMessageUrl'] = $data['msCustomLink'];
          }
          //BID - New, Updated
          if($data['msAction'] == "bid_new" || $data['msAction'] == "bid_update") {
            if($data['msPerspective'] == "buyer") {
              $data['thisMessageUrl'] = "dashboard/bid/" . $data['msBidId'];
            } else {
              if($data['msPerspective'] == "seller" || $data['msPerspective'] == "shared") {
                $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/bid/" . $data['msBidId'];
              }
            }
          }
          //TRADE - New
          if($data['msAction'] == "trade_new") {
            if($data['msPerspective'] == "buyer") {
              $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/purchase/" . $data['msTradeId'];
            } else {
              if($data['msPerspective'] == "seller" || $data['msPerspective'] == "shared") {
                $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/sale/" . $data['msTradeId'];
              }
            }
          }
          //TRADE - Updated
          if($data['msAction'] == "trade_update") {
            if($data['msPerspective'] == "buyer") {
              $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/purchase/" . $data['msTradeId'];
            } else {
              if($data['msPerspective'] == "seller" || $data['msPerspective'] == "shared") {
                $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/sale/" . $data['msTradeId'];
              }
            }
          }
          //TRADE - Deleted
          if($data['msAction'] == "trade_deleted") {
            $data['thisMessageUrl'] = "";
          }
          //TRADE - External Purchase
          if($data['msAction'] == "external_purchase_new") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/external_purchase/" . $data['msTradeId'];
          }
          //UTILIZATION
          if($data['msAction'] == "utilization_new") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }
          if($data['msAction'] == "utilization_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }
          if($data['msAction'] == "estimated_utilization_deleted") {
            $data['thisMessageUrl'] = "";
          }
          //TRADE - Deleted
          if($data['msAction'] == "utilization_deleted") {
            $data['thisMessageUrl'] = "";
          }

          //SHARE - various methods
          if($data['msAction'] == "share_new" || $data['msAction'] == "share_revoked" || $data['msAction'] == "share_accepted" || $data['msAction'] == "advisor_share_new") {
            if($data['msPerspective'] == "seller" || $data['msPerspective'] == "shared") {
              $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
            }
          }

          //WORKFLOW - Update
          if($data['msAction'] == "workflow_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/workflow/" . $data['msMessageId'];
          }

          //COMPLIANCE - Update
          if($data['msAction'] == "compliance_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/compliance/" . $data['msMessageId'];
          }

          //CREDIT - Loaded
          if($data['msAction'] == "credit_loaded") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Unlisted from OIX Marketplace
          if($data['msAction'] == "credit_unlisted") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Credit Estimate Updated
          if($data['msAction'] == "credit_estimate_updated") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/activity_variance/2";
          }
          //CREDIT - Estimated Payment Date Updated
          if($data['msAction'] == "credit_estimated_payment_date_updated") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/activity_variance/33";
          }
          //CREDIT - Qualified Expenditures Variance Updated
          if($data['msAction'] == "qualified_expenditures_updated") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/activity_variance/35";
          }
          //CREDIT - Initial Cert Date
          if($data['msAction'] == "est_initial_cert_dt") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Final Cert Date
          if($data['msAction'] == "est_final_cert_dt") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Estimated Payment Date
          if($data['msAction'] == "estimated_payment_date") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Issue Date
          if($data['msAction'] == "IssueDate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Project Start Date
          if($data['msAction'] == "projectStartDate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Project End Date
          if($data['msAction'] == "projectEndDate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Audit Start Date
          if($data['msAction'] == "auditStartDate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Audit End Date
          if($data['msAction'] == "auditEndDate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - OIX Offer Good Until Date
          if($data['msAction'] == "OfferGoodUntil") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - OIX Offer Good Until Date
          if($data['msAction'] == "lastDayPrincipalPhotography") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }
          //CREDIT - Custom Data Point Date
          if(substr($data['msAction'], 0, 19) == "customDataPointDate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'];
          }

          //COMPLIANCE - Attached to Credit
          if($data['msAction'] == "compliance_attached") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/compliance";
          }
          //COMPLIANCE - Disconnectd from Credit
          if($data['msAction'] == "compliance_dettached") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/compliance";
          }
          if($data['msAction'] == "compliance_reminder") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/compliance";
          }

          //WORKFLOW - Attached to Credit
          if($data['msAction'] == "workflow_attached") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/workflow";
          }
          //WORKFLOW - Disconnectd from Credit
          if($data['msAction'] == "workflow_dettached") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/workflow";
          }

          //UTILIZATION ESTIMATE - New
          if($data['msAction'] == "new_utilization_estimate") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }
          //UTILIZATION ESTIMATE - Update to Credit Value (estimate or actual)
          if($data['msAction'] == "utilization_estimate_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }
          //UTILIZATION ESTIMATE - Deleted
          if($data['msAction'] == "utilization_estimate_delete") {
            $data['thisMessageUrl'] = "";
          }
          //UTILIZATION ACTUAL - Update to Credit Value (estimate or actual)
          if($data['msAction'] == "utilization_actual_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }

          //UTILIZATION - Update to closing process
          if($data['msAction'] == "closing_process_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }
          //UTILIZATION - Update to payment status
          if($data['msAction'] == "trade_payment_update") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/utilization/" . $data['msTradeId'];
          }

          //CUSTOM Signature - New doc for signature
          if($data['msAction'] == "custom_document_for_signature_new") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/documents";
          }
          //CUSTOM Signature - Doc for sig revoked
          if($data['msAction'] == "custom_document_for_signature_revoked") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/documents";
          }
          //CUSTOM Signature - New signed
          if($data['msAction'] == "custom_signature_new") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/documents";
          }
          //DOCUMENT - New
          if($data['msAction'] == "doc_new") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/documents";
          }
          //DOCUMENT - Deleted
          if($data['msAction'] == "doc_deleted") {
            $data['thisMessageUrl'] = "dashboard/credit/" . $data['msListingId'] . "/documents";
          }

          //CUSOM ACCESS - New
          if($data['msAction'] == "access_approved_broker") {
            $data['thisMessageUrl'] = "broker/inventory/" . $data['msContent'];
          }

          array_push($return, $data);

        }

        return $return;

      }

    }

  }

  function get_messages_of_user_lite($input) {

    $userId = isset($input['userId']) ? $input['userId'] : null;
    $dmaId = isset($input['dmaId']) ? $input['dmaId'] : null;
    $accountType = isset($input['accountType']) ? $input['accountType'] : null;
    $warned = isset($input['warned']) ? $input['warned'] : null;
    $read = isset($input['read']) ? $input['read'] : null;
    $count = isset($input['count']) ? $input['count'] : null;
    $type = isset($input['type']) ? $input['type'] : null;
    $limit = isset($input['limit']) ? $input['limit'] : null;
    $order = isset($input['order']) ? $input['order'] : null;
    $includeUnread = isset($input['includeUnread']) ? $input['includeUnread'] : null;
    $msTypeExcludeArray = isset($input['msTypeExcludeArray']) ? (sizeof($input['msTypeExcludeArray']) > 0 ? $input['msTypeExcludeArray'] : []) : [];
    $msContentExcludeArray = isset($input['msContentExcludeArray']) ? (sizeof($input['msContentExcludeArray']) > 0 ? $input['msContentExcludeArray'] : []) : [];
    $msTypeOnly = isset($input['msTypeOnly']) ? (sizeof($input['msTypeOnly']) > 0 ? $input['msTypeOnly'] : []) : [];

    //PROTECT - ACCESS LEVEL CONTROL
    if($userId > 0) {
    } else {
      throw new \Exception('General fail');
    }

    $oneDayAgo = time() - 86400;

    $this->db->select('member_messages.*, messages.*, Accounts.firstName, Accounts.lastName, dmaAccounts.title, ActiveListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, Bids.bidId, Bids.bidSize, Bids.bidPrice, States.state, States.name, countries.code, countries.name as countryName');

    $this->db->from('member_messages');

    if($includeUnread == 1) {
      $array = ['mmUserId' => $userId, 'mmCreatedDate >' => $oneDayAgo];
    } else {
      $array = ['mmUserId' => $userId, 'mmRead !=' => 1, 'mmCreatedDate >' => $oneDayAgo];
    }
    $this->db->where($array);
    if(sizeof($msTypeExcludeArray) > 0) {
      foreach($msTypeExcludeArray as $mte) {
        $this->db->where('messages.msType !=', $mte);
      }
    }
    if(sizeof($msTypeOnly) > 0) {
      foreach($msTypeOnly as $mto) {
        $this->db->where('messages.msType', $mto);
      }
    }
    if(sizeof($msContentExcludeArray) > 0) {
      foreach($msContentExcludeArray as $mce) {
        $this->db->where('messages.msContent !=', $mce);
      }
    }
    $this->db->where('dmaAccounts.planId >', 0);
    $this->db->where('messages.msDeleteMarker', null);

    $this->db->join("messages", "member_messages.mmMessageId = messages.msId", 'left');
    $this->db->join("Accounts", "messages.msUserIdCreated = Accounts.userId", 'left');
    $this->db->join("PendingListings", "messages.msListingId = PendingListings.listingId", 'left');
    $this->db->join("ActiveListings", "messages.msListingId = ActiveListings.listingId", 'left');
    $this->db->join("dmaAccounts", "dmaAccounts.mainAdmin = PendingListings.listedBy", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("countries", "countries.id = States.countryId", 'left');
    $this->db->join("Bids", "messages.msBidId = Bids.bidId", 'left');
    if($order == "alertAsc") {
      $this->db->order_by("msContent ASC");
    } else {
      $this->db->order_by("mmCreatedDate DESC");
    }
    $this->db->limit(100);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function search_messages($input) {

    $msType = isset($input['msType']) ? $input['msType'] : null;
    $msAction = isset($input['msAction']) ? $input['msAction'] : null;
    $msListingId = isset($input['msListingId']) ? $input['msListingId'] : null;
    $msTradeId = isset($input['msTradeId']) ? $input['msTradeId'] : null;
    $msWorkflowItemId = isset($input['msWorkflowItemId']) ? $input['msWorkflowItemId'] : null;
    $msContent = isset($input['msContent']) ? $input['msContent'] : null;
    $msContentNotEqual = isset($input['msContentNotEqual']) ? $input['msContentNotEqual'] : null;
    $startTime = isset($input['startTime']) ? $input['startTime'] : null;
    $endTime = isset($input['endTime']) ? $input['endTime'] : null;
    $distinctMsId = isset($input['distinctMsId']) ? $input['distinctMsId'] : null;
    $msYear = isset($input['msYear']) ? $input['msYear'] : null;
    $msMonth = isset($input['msMonth']) ? $input['msMonth'] : null;
    $msDay = isset($input['msDay']) ? $input['msDay'] : null;

    $this->db->select('member_messages.*, messages.*, Accounts.firstName, Accounts.lastName, dmaAccounts.title, ActiveListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, Bids.bidId, Bids.bidSize, Bids.bidPrice, States.state, States.name, countries.code, countries.name as countryName');
    $this->db->from('member_messages');

    if($msType != "") {
      $this->db->where('messages.msType', $msType);
    }
    if($msAction != "") {
      $this->db->where('messages.msAction', $msAction);
    }
    if($msListingId > 0) {
      $this->db->where('messages.msListingId', $msListingId);
    }
    if($msTradeId > 0) {
      $this->db->where('messages.msTradeId', $msTradeId);
    }
    if($msWorkflowItemId > 0) {
      $this->db->where('messages.msWorkflowItemId', $msWorkflowItemId);
    }
    if($msContent != "" || $msContent > -1) {
      if($msContent == 0) {
        $this->db->where('messages.msContent', '0');
      } else {
        $this->db->where('messages.msContent', $msContent);
      }
    }
    if($msContentNotEqual != "") {
      $this->db->where('messages.msContent !=', $msContentNotEqual);
    }
    if($msYear > 0) {
      $this->db->where('messages.msYear', $msYear);
    }
    if($msMonth > 0) {
      $this->db->where('messages.msMonth', $msMonth);
    }
    if($msDay > 0) {
      if($msContentNotEqual == "expired") {
        $this->db->where('messages.msDay <', $msDay); //This if statement is here for when checking for expired events (looks for days less)
      } else {
        $this->db->where('messages.msDay', $msDay);
      }
    }
    if($startTime != "") {
      $this->db->where('messages.msCreatedDate >=', $startTime);
    }
    if($endTime != "") {
      $this->db->where('messages.msCreatedDate <', $endTime);
    }
    $this->db->where('messages.msDeleteMarker', null);

    $this->db->join("messages", "member_messages.mmMessageId = messages.msId", 'left');
    $this->db->join("Accounts", "messages.msUserIdCreated = Accounts.userId", 'left');
    $this->db->join("PendingListings", "messages.msListingId = PendingListings.listingId", 'left');
    $this->db->join("ActiveListings", "messages.msListingId = ActiveListings.listingId", 'left');
    $this->db->join("dmaAccounts", "dmaAccounts.mainAdmin = PendingListings.listedBy", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("countries", "countries.id = States.countryId", 'left');
    $this->db->join("Bids", "messages.msBidId = Bids.bidId", 'left');
    $this->db->order_by("mmCreatedDate DESC");
    if($distinctMsId == 1) {
      $this->db->group_by('messages.msId');
    }
    //$this->db->limit(100);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_member_message($mmId) {

    $this->db->select('member_messages.*, messages.*, Accounts.firstName, Accounts.lastName');

    $this->db->from('member_messages');

    $this->db->where('mmId', $mmId);
    $this->db->join("messages", "member_messages.mmMessageId = messages.msId", 'left');
    $this->db->join("Accounts", "messages.msUserIdCreated = Accounts.userId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_message($msId) {

    $this->db->select('messages.*');

    $this->db->from('messages');

    $this->db->where('msId', $msId);

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function update_member_message_status($mmId, $warned, $read) {

    $data = [
        'mmWarned'     => $warned,
        'mmRead'       => $read,
        'mmWarnedDate' => time(),
        'mmReadDate'   => time(),
    ];

    $this->db->where('mmId', $mmId);
    $this->db->where('mmUserId', $this->cisession->userdata('userId'));
    $this->db->update('member_messages', $data);

  }

  function mark_all_read($userId, $dmaId) {

    $data = [
        'mmWarned'     => 1,
        'mmRead'       => 1,
        'mmWarnedDate' => time(),
        'mmReadDate'   => time(),
    ];

    $this->db->where('mmUserId', $userId);
    $this->db->where('mmDmaId', null);
    $this->db->update('member_messages', $data);

    $this->db->where('mmUserId', $userId);
    $this->db->where('mmDmaId', $dmaId);
    $this->db->update('member_messages', $data);

  }

  function mark_messages_read_by_array($mArray) {

    $data = [
        'mmWarned'     => 1,
        'mmRead'       => 1,
        'mmWarnedDate' => time(),
        'mmReadDate'   => time(),
    ];

    $this->db->where_in('mmId', $mArray);
    $this->db->update('member_messages', $data);

  }

  function mark_message_expired($msId) {

    $currMessage = $this->get_message($msId);

    //Strip out all potential
    $newMsTitle = $currMessage['msTitle'];
    $newMsTitle = str_replace('Today Alert: ', '', $newMsTitle);
    $newMsTitle = str_replace('1 Day Alert: ', '', $newMsTitle);
    $newMsTitle = str_replace('2 Day Alert: ', '', $newMsTitle);
    $newMsTitle = str_replace('3 Day Alert: ', '', $newMsTitle);
    $newMsTitle = str_replace('4 Day Alert: ', '', $newMsTitle);
    $newMsTitle = str_replace('5 Day Alert: ', '', $newMsTitle);
    $newMsTitleShort = $currMessage['msTitleShort'];
    $newMsTitleShort = str_replace('Today Alert: ', '', $newMsTitleShort);
    $newMsTitleShort = str_replace('1 Day Alert: ', '', $newMsTitleShort);
    $newMsTitleShort = str_replace('2 Day Alert: ', '', $newMsTitleShort);
    $newMsTitleShort = str_replace('3 Day Alert: ', '', $newMsTitleShort);
    $newMsTitleShort = str_replace('4 Day Alert: ', '', $newMsTitleShort);
    $newMsTitleShort = str_replace('5 Day Alert: ', '', $newMsTitleShort);

    $data = [
        'msTitle'      => $newMsTitle,
        'msTitleShort' => $newMsTitleShort,
        'mscontent'    => 'expired',
    ];

    if($msId > 0) {
      $this->db->where('messages.msId', $msId);
      $this->db->update('messages', $data);
    }

  }

  function search_and_delete_alert_messages($msAction = "", $listingId = "", $tradeId = "", $wiId = "") {

    //compile generic search variables and then get the matching alerts
    $mSearchRequest['msType'] = 'calendar_alert';
    $mSearchRequest['msAction'] = $msAction;
    $mSearchRequest['msListingId'] = $listingId;
    $mSearchRequest['msTradeId'] = $tradeId;
    $mSearchRequest['msWorkflowItemId'] = $wiId;
    $mSearchRequest['msContentNotEqual'] = 'expired';
    $mSearchRequest['distinctMsId'] = 1;
    //var_dump($mSearchRequest);
    $matchingCalendarAlerts = $this->search_messages($mSearchRequest);
    if(sizeof($matchingCalendarAlerts) > 0) {
      $this->delete_message($matchingCalendarAlerts[0]['msId']);
    }

  }

  function delete_message($msId) {

    $data = [
        'msDeleteMarker' => 1,
    ];

    if($msId > 0) {
      $this->db->where('messages.msId', $msId);
      $this->db->update('messages', $data);
    }

  }

  function delete_all_credit_messages($listingId) {

    $data = [
        'msDeleteMarker' => 1,
    ];

    if($listingId > 0) {
      $this->db->where('messages.msListingId', $listingId);
      $this->db->update('messages', $data);
    }

  }

  function delete_all_trade_messages($tradeId) {

    $data = [
        'msDeleteMarker' => 1,
    ];

    if($tradeId > 0) {
      $this->db->where('messages.msTradeId', $tradeId);
      $this->db->update('messages', $data);
    }

  }

  //this
  function update_member_message_alert_warned($userId, $mmMessageId) {

    $data = [
        'mmWarned'     => 1,
        'mmRead'       => 1,
        'mmWarnedDate' => time(),
        'mmReadDate'   => time(),
    ];

    $this->db->where('mmUserId', $userId);
    $this->db->where('mmMessageId', $mmMessageId);
    $this->db->update('member_messages', $data);

  }

  function update_member_message_read($mmId) {

    $data = [
        'mmRead'     => 1,
        'mmReadDate' => time(),
    ];

    $this->db->where('mmId', $mmId);
    $this->db->update('member_messages', $data);

  }

  function insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent = "", $msUserIdCreated = "", $msPerspective = "", $msListingId = "", $msBidId = "", $msTradeId = "", $secondDmaMainUserId = "", $msTitle2 = "", $msTitle2Short = "", $msPerspective2 = "", $alertShared = "", $msTitleShared = "", $msTitleSharedShort = "", $msMessageId = "", $keepUnread = "", $msWorkflowItemId = "", $msCalendarDate = "") {

    //IF CALENDAR ALERT TYPE THEN DELETE PRE-EXISTING RECORDS
    if($msType == "calendar_alert") {

      //compile generic search variables and then get the matching alerts
      $mSearchRequest['msType'] = $msType;
      $mSearchRequest['msAction'] = $msAction;
      $mSearchRequest['msListingId'] = $msListingId;
      $mSearchRequest['msTradeId'] = $msTradeId;
      if($msAction == "workflow_update") {
        $mSearchRequest['msWorkflowItemId'] = $msWorkflowItemId;
      }
      $mSearchRequest['distinctMsId'] = 1;
      //var_dump($mSearchRequest);
      $matchingCalendarAlerts = $this->search_messages($mSearchRequest);
      //echo "Matches: ".sizeof($matchingCalendarAlerts)."<br>";
      //Since a lot of duplicates are returned, let's start an array
      $deletedMessageAIds = [];
      //Now delete them each
      foreach($matchingCalendarAlerts as $mal) {
        if(!in_array($mal['msId'], $deletedMessageAIds)) {
          $this->delete_message($mal['msId']);
          //echo "DM: ".$mal['msId']."<br>";
          //echo "<p>".$mal['msId']."</p>";
          array_push($deletedMessageAIds, $mal['msId']);
        }
      }

    }

    //Create Primary Message
    $data = [
        'msType'             => $msType,
        'msAction'           => $msAction,
        'msUserIdCreated'    => $msUserIdCreated,
        'msPerspective'      => $msPerspective,
        'msListingId'        => $msListingId,
        'msBidId'            => $msBidId,
        'msTradeId'          => $msTradeId,
        'msWorkflowItemId'   => $msWorkflowItemId,
        'msMessageId'        => $msMessageId,
        'msTitle'            => $msTitle,
        'msTitleShort'       => $msTitleShort,
        'msContent'          => $msContent,
        'msCalendarDateUnix' => $msCalendarDate,
        'msYear'             => ($msCalendarDate != "") ? date('Y', $msCalendarDate) : null,
        'msMonth'            => ($msCalendarDate != "") ? date('n', $msCalendarDate) : null, //n = month without leading zeros
        'msDay'              => ($msCalendarDate != "") ? date('j', $msCalendarDate) : null, //j = day without leading zeros
        'msCreatedDate'      => time(),
    ];

    $this->db->insert('messages', $data);
    $mmMessageId = $this->db->insert_id();

    //Load in the DMA model, as we need it below
    $this->load->model('DmaAccounts');

    ////////////////////////////////////////////////

    // 1. First, Add member messages for all Admins in this DMA account performing the action

    //Get DMA account from the Main User Id
    $dmaOne = $this->DmaAccounts->get_dma_account_by_id($firstDmaMainUserId); //TODO - convert to DMA ID

    //Get admin users of this DMA account
    if($msListingId > 0 && ($msPerspective == "seller" || $msPerspective == "shared")) {
      //Get those members with access to this credit
      $dmamembersData = $this->DmaAccounts->get_dmamembers_creditaccess_shares($msListingId, $dmaOne['dmaId'], []);
      $dmamembers = $dmamembersData['membersAllAccess'];
    } else {
      $dmamembers = $this->DmaAccounts->get_dma_members($dmaOne['dmaId'], 1);
    }

    //Loop through Admin Users and insert a member message
    foreach($dmamembers as $dmam) {

      $thisMessage = $this->insert_member_message($mmMessageId, $dmam['userId'], $dmaOne['dmaId']);

      //If the member is THIS member, then automatically mark that update as read
      if($this->cisession->userdata('userId') == $dmam['userId'] && $keepUnread != 1) {

        $this->update_member_message_status($thisMessage, 1, 1);

      }

    }

    // 2. Second, if a second DMA account needs notifications too, then create a new message and send to those DMA admin

    if($secondDmaMainUserId > 0) {

      $data2 = [
          'msType'             => $msType,
          'msAction'           => $msAction,
          'msUserIdCreated'    => null,
          'msPerspective'      => $msPerspective2,
          'msListingId'        => $msListingId,
          'msBidId'            => $msBidId,
          'msTradeId'          => $msTradeId,
          'msWorkflowItemId'   => $msWorkflowItemId,
          'msMessageId'        => $msMessageId,
          'msTitle'            => $msTitle2,
          'msTitleShort'       => $msTitle2Short,
          'msContent'          => $msContent,
          'msCalendarDateUnix' => $msCalendarDate,
          'msYear'             => ($msCalendarDate != "") ? date('Y', $msCalendarDate) : null,
          'msMonth'            => ($msCalendarDate != "") ? date('n', $msCalendarDate) : null, //n = month without leading zeros
          'msDay'              => ($msCalendarDate != "") ? date('j', $msCalendarDate) : null, //j = day without leading zeros
          'msCreatedDate'      => time(),
      ];

      $this->db->insert('messages', $data2);
      $mmMessageId2 = $this->db->insert_id();

      //Get DMA account from the Main User Id
      $dmaTwo = $this->DmaAccounts->get_dma_account_by_id($secondDmaMainUserId); //TODO - convert to DMA ID

      //Get admin users of this DMA account
      if($msListingId > 0 && ($msPerspective2 == "seller" || $msPerspective2 == "shared")) {
        //Get those members with access to this credit
        $dmamembers2Data = $this->DmaAccounts->get_dmamembers_creditaccess_shares($msListingId, $dmaTwo['dmaId'], []);
        $dmamembers2 = $dmamembers2Data['membersAllAccess'];
      } else {
        $dmamembers2 = $this->DmaAccounts->get_dma_members($dmaTwo['dmaId'], 1);
      }

      //Loop through Admin Users and insert a member message
      foreach($dmamembers2 as $dmam2) {

        $thisMessage = $this->insert_member_message($mmMessageId2, $dmam2['userId'], $dmaTwo['dmaId']);

      }

    }

    // 3. Third, if this message should be shared with anyone this credit has been shared with

    if($alertShared == true) {

      $data3 = [
          'msType'             => $msType,
          'msAction'           => $msAction,
          'msUserIdCreated'    => $msUserIdCreated,
          'msPerspective'      => 'shared',
          'msListingId'        => $msListingId,
          'msBidId'            => $msBidId,
          'msTradeId'          => $msTradeId,
          'msWorkflowItemId'   => $msWorkflowItemId,
          'msMessageId'        => $msMessageId,
          'msTitle'            => $msTitleShared,
          'msTitleShort'       => $msTitleSharedShort,
          'msContent'          => $msContent,
          'msCalendarDateUnix' => $msCalendarDate,
          'msYear'             => ($msCalendarDate != "") ? date('Y', $msCalendarDate) : null,
          'msMonth'            => ($msCalendarDate != "") ? date('n', $msCalendarDate) : null, //n = month without leading zeros
          'msDay'              => ($msCalendarDate != "") ? date('j', $msCalendarDate) : null, //j = day without leading zeros
          'msCreatedDate'      => time(),
      ];

      $this->db->insert('messages', $data3);
      $mmMessageId3 = $this->db->insert_id();

      $sharedAccounts = $this->CreditListings->get_shares_of_credit($msListingId);
      foreach($sharedAccounts as $sa) {
        //Get admin users of this DMA account
        if($msListingId > 0) {
          //Get those members with access to this credit
          $dmamembers3Data = $this->DmaAccounts->get_dmamembers_creditaccess_shares($msListingId, $sa['dmaId'], []);
          $dmamembers3 = $dmamembers3Data['membersAllAccess'];
        } else {
          $dmamembers3 = $this->DmaAccounts->get_dma_members($sa['dmaId'], 1);
        }

        //Loop through Admin Users and insert a member message
        foreach($dmamembers3 as $dmam3) {

          $thisMessage = $this->insert_member_message($mmMessageId3, $dmam3['userId'], $sa['dmaId']);

        }

      }

    }

    ////////////////////////////////////////////////

    //Finally, return the ORIGINAL message ID which was inserted
    return $mmMessageId;

  }

  function insert_member_message($mmMessageId, $mmUserId, $mmDmaId = "") {

    $data = [
        'mmMessageId'   => $mmMessageId,
        'mmUserId'      => $mmUserId,
        'mmDmaId'       => $mmDmaId,
        'mmWarned'      => 0,
        'mmRead'        => 0,
        'mmCreatedDate' => time(),
    ];

    $this->db->insert('member_messages', $data);

    return $this->db->insert_id();

  }

  function get_active_members_for_daily_alerts() {
    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > get_active_members_for_daily_alerts > started');

    $this->db->select('userId, accountType, firstName, lastName, email, notifications_settings.*');
    $this->db->where('planId > 0 AND approved = 1 AND agreed = 1 AND accountType = 4 AND (nsDailyAlertSummary IS NULL OR nsDailyAlertSummary <> 0)');
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');
    $this->db->join("dmaMembers", "Accounts.userId = dmaMembers.dmaMUserId", 'left');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->distinct();

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);

      //$this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > get_active_members_for_daily_alerts > User ID: '.$data['userId']);
    }

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > get_active_members_for_daily_alerts > complete');

    return $return;
  }

  function get_active_members_for_notifications($notifications = "", $alertSummary = "", $getDmaAccounts = "") {

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > get_active_members_for_notifications > started');

    $this->db->select('userId, accountType, firstName, lastName, email, notifications_settings.*');
    $this->db->where('approved', '1');
    $this->db->where('agreed', '1');
    $this->db->where('accountType', '4');
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      //If need DMA account info, add it to $data
      $data["dmaAccounts"] = $this->get_dma_accounts_of_user($data['userId']);

      $hasActiveDmaAccount = false;
      foreach($data["dmaAccounts"]['all'] as $da) {
        if($da['planId'] > 0) {
          $hasActiveDmaAccount = true;
        }
      }

      if($hasActiveDmaAccount) {

        if($notifications == "on") {
          if($data['nsFrequency'] == "never") {
            //then do not add this email to response
          } else {
            array_push($return, $data);
          }
        }

        if($alertSummary == "on") {
          if($data['nsDailyAlertSummary'] == 0 && $data['nsDailyAlertSummary'] != "") {
            //if user has configured notifications and set alerts to NO // OR // if user has configured notifications and it is not empty
          } else {
            //If user has alerts on OR if user has no configured notifications at all
            array_push($return, $data);
          }
        }

      }

      $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > get_active_members_for_notifications > User ID: ' . $data['userId']);

    }

    return $return;

  }

  function get_member_by_email_for_notifications($email) {

    $this->db->select('userId, accountType, firstName, lastName, email, notifications_settings.*');
    $this->db->where('email', $email);
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_active_dma_members_for_notifications($notifications = "") {

    $this->db->select('userId, accountType, firstName, lastName, email, notifications_settings.*');
    $this->db->where('approved', '1');
    $this->db->where('agreed', '1');
    $this->db->where("accountType = '4'");
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($notifications == "on" && $data['nsFrequency'] == "never") {
        //then do not add this email to response
      } else {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_active_general_members_for_notifications($notifications = "") {

    $this->db->select('userId, accountType, firstName, lastName, email, companyName, notifications_settings.*');
    $this->db->where('approved', '1');
    $this->db->where('agreed', '1');
    $this->db->where("accountType != '4'");
    $this->db->where("accountType != '8'");
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($notifications == "on" && $data['nsFrequency'] == "never") {
        //then do not add this email to response
      } else {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_approved_members_for_notifications() {

    $this->db->select('userId, accountType, firstName, lastName, email, approve_date, notifications_settings.*');
    $this->db->where('approved', '1');
    $this->db->where("agreed != '1'");
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if(substr($data['approve_date'], 0, 4) == '2016') {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_pending_members_for_notifications() {

    $this->db->select('userId, accountType, firstName, lastName, email, notifications_settings.*');
    $this->db->where("approved != '1'");
    $this->db->from('Accounts');
    $this->db->join("notifications_settings", "Accounts.userId = notifications_settings.nsUserId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_admins_for_notifications() {
    $this->db->select('userId, firstName, lastName, email');
    $this->db->from('AdminUsers');
    $this->db->where("is_delete !='1'");
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;
  }

  function get_admin_by_id_for_notifications($id) {
    $this->db->select('userId, firstName, lastName, email');
    $this->db->where('userId', $id);
    $this->db->from('AdminUsers');
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_members_with_inactivity_limits() {

    $this->db->select('userId, accountType, firstName, lastName, email, pwResetDaysReq, last_login');
    $this->db->where('pwResetDaysReq >', 0);
    //$this->db->where('resetPasswordFlag <',1);
    $this->db->from('Accounts');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_members_with_pw_expiration_limits() {

    $this->db->select('userId, accountType, firstName, lastName, email, pwResetDaysReq');
    $this->db->where('pwResetDaysReq >', 0);
    //$this->db->where('resetPasswordFlag <',1);
    $this->db->from('Accounts');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $data['latestPWchange'] = $this->get_latest_reset_password_history($data['userId']);
      array_push($return, $data);
    }

    return $return;

  }

  function add_password_reset_flag($userId, $flag) {

    $data = [
        'resetPasswordFlag' => $flag,
    ];

    $this->db->where('Accounts.userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function insert_notification($eData) {

    $data = [
        'nAdmin'      => $eData['nAdmin'],
        'nTimestamp'  => time(),
        'nStatus'     => 0,
        'nType'       => $eData['nType'],
        'nMembers'    => $eData['nMembers'],
        'nGreeting'   => $eData['nGreeting'],
        'nSignature'  => $eData['nSignature'],
        'nButton'     => $eData['nButton'],
        'nActivityId' => $eData['nActivityId'],
        'nSubject'    => $eData['nSubject'],
        'nHeadline'   => $eData['nHeadline'],
        'nCustomBody' => $eData['nCustomBody'],
    ];

    $this->db->insert('notifications', $data);

    return $this->db->insert_id();

  }

  function insert_completed_notification($eData) {

    $data = [
        'nAdmin'        => $eData['nAdmin'],
        'nTimestamp'    => time(),
        'nStatus'       => 2,
        'nType'         => $eData['nType'],
        'nMembers'      => $eData['nMembers'],
        'nGreeting'     => $eData['nGreeting'],
        'nSignature'    => $eData['nSignature'],
        'nButton'       => $eData['nButton'],
        'nSubject'      => $eData['nSubject'],
        'nHeadline'     => $eData['nHeadline'],
        'nCustomBody'   => $eData['nCustomBody'],
        'nStartTime'    => $eData['nStartTime'],
        'nFinishTime'   => $eData['nFinishTime'],
        'nSentCount'    => $eData['nSentCount'],
        'nNotSentCount' => $eData['nNotSentCount'],
        'nTotalMembers' => $eData['nTotalMembers'],
    ];

    $this->db->insert('notifications', $data);

    return $this->db->insert_id();

  }

  function get_notification_by_id($nId) {

    $this->db->select('notifications.*, AdminUsers.firstName, AdminUsers.lastName');
    $this->db->from('notifications');
    $this->db->where('notifications.nId', $nId);
    $this->db->join("AdminUsers", "notifications.nAdmin = AdminUsers.userId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_pending_notifications($code) {

    if($code != 'fhs7fyshksdf') {
      throw new \Exception('General fail');
    }

    $this->db->select('notifications.*');
    $this->db->from('notifications');
    $this->db->where('notifications.nStatus', '0');
    $this->db->order_by("notifications.nTimestamp asc");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function update_notification_to_processing($nId) {

    $data = [
        'nStatus'    => 1,
        'nStartTime' => time(),
    ];

    $this->db->where('nId', $nId);
    $this->db->update('notifications', $data);

  }

  function update_notification_to_finished($nId, $nSentCount, $nNotSentCount, $nTotalMembers) {

    $data = [
        'nStatus'       => 2,
        'nFinishTime'   => time(),
        'nSentCount'    => $nSentCount,
        'nNotSentCount' => $nNotSentCount,
        'nTotalMembers' => $nTotalMembers,
    ];

    $this->db->where('nId', $nId);
    $this->db->update('notifications', $data);

  }

  function update_accountType($userId, $accountType) {

    $data = [
        'accountType' => $accountType,
    ];

    $this->db->where('userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function updateMemberPreference($userId, $field, $value) {

    $fieldFinal = "";
    if($field == "creditListViewPref") {
      $fieldFinal = "creditListViewPref";
    }
    if($field == "creditListOrder") {
      $fieldFinal = "creditListOrder";
    }
    if($field == "creditListSections") {
      $fieldFinal = "creditListSections";
    }
    if($field == "creditHeaderConfig") {
      $fieldFinal = "creditHeaderConfig";
    }

    $data = [
        $fieldFinal => $value,
    ];

    if($fieldFinal != "") {
      $this->db->where('userId', $userId);
      $this->db->update('Accounts', $data);
    }

  }

  function salesForceSubmitNewUser($request) {

    $url = "https://webto.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8";
    /*
		$ch = curl_init();
		$headers = array(
			'Content-Type: text/html'
		);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
*/
    $data = [
        'oid'             => '00Dd0000000dBa0',
        'retURL'          => 'https://www.theoix.com',
        'first_name'      => $request['firstname'],
        'last_name'       => $request['lastname'],
        'company'         => $request['company_name'],
        'title'           => $request['job_title'],
        'email'           => $request['email'],
        'phone'           => $request['phonenumber'],
        '00N0W000009LR6L' => $request['00N0W000009LR6L'] //custom data point "how can we help you?"
    ];
    /*
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$output = curl_exec($ch);

		$info = curl_getinfo($ch);
		echo "<pre>";
		var_dump($output);
		var_dump($info);
		curl_close($ch);
*/

    //Initialize the $kv array for later use
    $kv = [];

    foreach($data as $key => $value) {
      $kv[] = stripslashes($key) . "=" . stripslashes($value);
    }

    //Create a query string with join function separted by &
    $query_string = join("&", $kv);

    //Check to see if cURL is installed ...
    if(function_exists('curl_init')) {

      //Open cURL connection
      $ch = curl_init();

      //Set the url, number of POST vars, POST data
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, count($kv));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);

      //Set some settings that make it all work :)
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

      //Execute SalesForce web to lead PHP cURL
      $result = curl_exec($ch);

      $info = curl_getinfo($ch);
      echo "<pre>";
      var_dump($result);
      var_dump($info);

      //close cURL connection
      curl_close($ch);

    }

  }

  function get_my_service_providers($request) {

    $cusUserId = (isset($request['cusUserId'])) ? $request['cusUserId'] : null;

    $this->db->select('Partners.*, Customers.*, dmaAccounts.title, dmaAccounts.profileUrl, Accounts.userId, Accounts.profile, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.companyName, Accounts.address1, Accounts.address2, Accounts.city, Accounts.state, Accounts.postalCode, Accounts.phone, Accounts.mobilePhone');
    if($cusUserId > 0) {
      $this->db->where('Customers.cusUserId', $cusUserId);
    } else {
      throw new \Exception('General fail');
    }
    $this->db->where('Customers.cusStatus', 1);
    $this->db->from('Customers');
    $this->db->join("Partners", "Partners.partnerDmaId = Customers.cusDmaId", 'left');
    $this->db->join("dmaAccounts", "dmaAccounts.dmaId = Customers.cusDmaId", 'left');
    $this->db->join("Accounts", "Customers.cusUserId = Accounts.userId", 'left');
    $this->db->order_by("Accounts.companyName ASC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['cusTypeName'] = "";
      if($data['cusType'] == "buyer") {
        $data['cusTypeName'] = "Buyer";
      } else {
        if($data['cusType'] == "seller") {
          $data['cusTypeName'] = "Seller";
        } else {
          if($data['cusType'] == "buyer_seller") {
            $data['cusTypeName'] = "Buyer & Seller";
          }
        }
      }

      $data['cusStatusName'] = "";
      $data['cusStatusIcon'] = "";
      if($data['cusStatus'] == 2) {
        $data['cusStatusName'] = "Approved";
        $data['cusStatusIcon'] = '<span class="icon-ok mr5"></span>';
      } else {
        if($data['cusStatus'] == 1) {
          $data['cusStatusName'] = "Active";
          $data['cusStatusIcon'] = '<span class="icon-users mr5 greenText"></span>';
        } else {
          $data['cusStatusName'] = "Pending";
          $data['cusStatusIcon'] = '<span class="icon-clock mr5 goldText"></span>';
        }
      }

      array_push($return, $data);

    }

    return $return;

  }

  function get_customers($dmaId, $request = []) {

    $orderBy = isset($request['orderBy']) ? $request['orderBy'] : null;
    $orderDir = isset($request['orderDir']) ? $request['orderDir'] : null;

    $this->db->select('Customers.*, dmaAccounts.title, dmaAccounts.dmaType, Accounts.userId, Accounts.profile, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.companyName, Accounts.address1, Accounts.address2, Accounts.city, Accounts.state, Accounts.postalCode, Accounts.phone, Accounts.mobilePhone');
    $this->db->where('Customers.cusDmaId', $dmaId);
    $this->db->where('Customers.cusStatus IN(1,2,0)');
    $this->db->from('Customers');
    $this->db->join("Accounts", "Customers.cusUserId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "dmaAccounts.primary_account_id = Customers.cusUserId", 'left');
    if($orderBy != "" && $orderDir != "") {
      if($orderBy == 'customerAdded') {
        $or1 = "Accounts.companyName";
      }
      if($orderDir == 'asc') {
        $or2 = "ASC";
      } else {
        $or2 = "DESC";
      }
      $this->db->order_by($or1 . " " . $or2);
    } else {
      $this->db->order_by("Accounts.companyName ASC");
    }

    $query = $this->db->get();

    $return = [];
    $return['customers'] = [];
    $return['summary'] = [];

    $return['summary']['pendingCustomerCount'] = 0;
    $return['summary']['approvedCustomerCount'] = 0;
    $return['summary']['activeCustomerCount'] = 0;

    foreach($query->result_array() as $data) {

      $data['cusTypeName'] = "";
      if($data['cusType'] == "buyer") {
        $data['cusTypeName'] = "Buyer";
      } else {
        if($data['cusType'] == "seller") {
          $data['cusTypeName'] = "Seller";
        } else {
          if($data['cusType'] == "buyer_seller") {
            $data['cusTypeName'] = "Buyer & Seller";
          }
        }
      }

      $data['cusStatusName'] = "";
      $data['cusStatusIcon'] = "";
      if($data['cusStatus'] == 2) {
        $data['cusStatusName'] = ($data['dmaType'] == "customer_broker") ? "Approved" : "Access Invite Sent";
        $data['cusStatusIcon'] = '<span class="icon-ok mr5"></span>';
        $return['summary']['approvedCustomerCount']++;
      } else {
        if($data['cusStatus'] == 1) {
          $data['cusStatusName'] = "Active";
          $data['cusStatusIcon'] = '<span class="icon-users mr5 greenText"></span>';
          $return['summary']['activeCustomerCount']++;
        } else {
          $data['cusStatusName'] = ($data['dmaType'] == "customer_broker") ? "Pending (No Access)" : "No Access";
          $data['cusStatusIcon'] = '<span class="icon-lock mr5 goldText"></span>';
          $return['summary']['pendingCustomerCount']++;
        }
      }

      array_push($return['customers'], $data);

    }

    return $return;

  }

  function get_customer($request) {

    $cusId = (isset($request['cusId'])) ? $request['cusId'] : null;
    $cusUserId = (isset($request['cusUserId'])) ? $request['cusUserId'] : null;
    $cusDmaId = (isset($request['cusDmaId'])) ? $request['cusDmaId'] : null;
    $partnerUsername = (isset($request['partnerUsername'])) ? $request['partnerUsername'] : null;

    //Verify
    if($cusId > 0 || ($cusUserId > 0 && $cusDmaId > 0) || ($cusUserId > 0 && $partnerUsername != "")) {
      //sllow through
    } else {
      throw new \Exception('General fail');
    }

    $this->db->select('Customers.*, Partners.partnerUsername, Accounts.userId, Accounts.profile, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.companyName, Accounts.address1, Accounts.address2, Accounts.city, Accounts.state, Accounts.postalCode, Accounts.phone, Accounts.mobilePhone, Accounts.agreed');

    if($cusId > 0) {
      $this->db->where('Customers.cusId', $cusId);
    }
    if($cusUserId > 0) {
      $this->db->where('Customers.cusUserId', $cusUserId);
    }
    if($cusDmaId > 0) {
      $this->db->where('Customers.cusDmaId', $cusDmaId);
    }
    if($partnerUsername != "") {
      $this->db->where('Partners.partnerUsername', $partnerUsername);
    }

    $this->db->from('Customers');
    $this->db->join("Accounts", "Customers.cusUserId = Accounts.userId", 'left');
    $this->db->join("Partners", "Partners.partnerDmaId = Customers.cusDmaId", 'left');
    $this->db->order_by("Accounts.lastName ASC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['cusTypeName'] = "";
      if($data['cusType'] == "buyer") {
        $data['cusTypeName'] = "Buyer";
      } else {
        if($data['cusType'] == "seller") {
          $data['cusTypeName'] = "Seller";
        } else {
          if($data['cusType'] == "buyer_seller") {
            $data['cusTypeName'] = "Buyer & Seller";
          }
        }
      }

      $data['cusStatusName'] = "";
      $data['cusStatusIcon'] = "";
      if($data['cusStatus'] == 2) {
        $data['cusStatusName'] = "Approved";
        $data['cusStatusIcon'] = '<span class="icon-mail"></span>';
      } else {
        if($data['cusStatus'] == 1) {
          $data['cusStatusName'] = "Active";
          $data['cusStatusIcon'] = '<span class="icon-ok-circled greenText"></span>';
        } else {
          $data['cusStatusName'] = "Pending";
          $data['cusStatusIcon'] = '<span class="icon-clock goldText"></span>';
        }
      }

      array_push($return, $data);

    }

    if(sizeof($return) > 0) {
      return $return[0];
    } else {
      return [];
    }

  }

  function insert_user_customer_level() {

    $data = [
        'accountType' => 4,
        'profile'     => 4,
        'firstName'   => $this->input->post('firstName'),
        'lastName'    => $this->input->post('lastName'),
        'email'       => $this->input->post('email'),
        'companyName' => $this->input->post('companyName'),
    ];

    $this->db->insert('Accounts', $data);

    return $this->db->insert_id();

  }

  function insert_dma_customer($request) {

    $data = [
        'cusUserId'        => $request['userId'],
        'cusDmaId'         => $request['cusDmaId'],
        'cusType'          => $request['cusType'],
        'cusCompanyName'   => $request['companyName'],
        'cusFirstName'     => $request['firstName'],
        'cusLastName'      => $request['lastName'],
        'cusEmail'         => $request['email'],
        'cusAddedDate'     => time(),
        'cusAddedByUserId' => $this->cisession->userdata('userId'),
    ];

    $this->db->insert('Customers', $data);

    return $this->db->insert_id();

  }

  function insert_dma_customer_old($cusUserId, $cusDmaId) {

    $data = [
        'cusUserId'        => $cusUserId,
        'cusDmaId'         => $cusDmaId,
        'cusType'          => $this->input->post('cusType'),
        'cusCompanyName'   => $this->input->post('companyName'),
        'cusFirstName'     => $this->input->post('firstName'),
        'cusLastName'      => $this->input->post('lastName'),
        'cusEmail'         => $this->input->post('email'),
        'cusAddedDate'     => time(),
        'cusAddedByUserId' => $this->cisession->userdata('userId'),
    ];

    $this->db->insert('Customers', $data);

    return $this->db->insert_id();

  }

  function update_user_customer_level($cusId) {

    $data = [
        'cusType'        => $this->input->post('cusType'),
        'cusCompanyName' => $this->input->post('companyName'),
        'cusFirstName'   => $this->input->post('firstName'),
        'cusLastName'    => $this->input->post('lastName'),
        'cusEmail'       => $this->input->post('email'),
    ];

    $this->db->where('Customers.cusId', $cusId);
    $this->db->update('Customers', $data);

  }

  function update_user_customer($cusId, $request) {

    $this->db->where('Customers.cusId', $cusId);
    $this->db->update('Customers', $request);

  }

  function get_partners($input) {

    $this->db->select('companyName, partnerDmaId, portalImage, pContent, partnerId, portalURL, companyURL, portalEmail ');
    $this->db->from('Partners');
    $this->db->where('Partners.pListed', 1);
    if($input['pTypeId'] > 0) {
      $this->db->where_in('pTypeId', $input['pTypeId']);
    }
    $this->db->order_by('companyName asc');
    $query = $this->db->get();
    $result = $query->result_array();

    return $result;
  }

  function get_partner($input) {

    $partnerId = (isset($input['partnerId'])) ? $input['partnerId'] : 0;
    $partnerUsername = (isset($input['partnerUsername'])) ? $input['partnerUsername'] : null;
    $partnerDmaId = (isset($input['partnerDmaId'])) ? $input['partnerDmaId'] : 0;

    $this->db->select('companyName, partnerUsername, partnerDmaId, portalImage, welcomeBannerHeadline, pContacts, pContent, partnerId, address, city, state, phone, portalURL, companyURL, portalEmail, dmaAccounts.*');
    $this->db->from('Partners');
    if($partnerId > 0) {
      $this->db->where('partnerId', $partnerId);
    }
    if($partnerUsername != "") {
      $this->db->where('partnerUsername', $partnerUsername);
    }
    if($partnerDmaId > 0) {
      $this->db->where('partnerDmaId', $partnerDmaId);
    }

    $this->db->join("dmaAccounts", "dmaAccounts.dmaId = Partners.partnerDmaId", 'left');
    $this->db->order_by('companyName asc');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['partnerUsername'] = $data['partnerUsername'];
      $data['partnerCompanyName'] = $data['title'];
      $data['contact']['address'] = $data['address'] . " " . $data['city'] . ", " . $data['state'];
      $data['contact']['contacts'] = $data['pContacts'];
      $data['contact']['phone'] = $data['phone'];
      $data['contact']['email'] = $data['portalEmail'];
      $data['dmaProfileUrl'] = $data['profileUrl'];
      $data['partnerBannerUrl'] = "https://oixstatic.s3.amazonaws.com/portals/banners/" . $data['partnerUsername'] . "-banner.jpg";
      $data['partnerDmaImageUrl'] = "https://oixstatic.s3.amazonaws.com/dma/logos/" . $data['dmaId'] . "-white.png";

      array_push($return, $data);

    }

    if(sizeof($return) > 0) {
      return $return[0];
    } else {
      return [];
    }

  }

  //THIS FUNCTION IS ALSO IN TANK AUTH
  function get_ip() {
    //Just get the headers if we can or else use the SERVER global
    if(function_exists('apache_request_headers')) {
      $headers = apache_request_headers();
    } else {
      $headers = $_SERVER;
    }
    //Get the forwarded IP if it exists
    if(array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      $the_ip = $headers['X-Forwarded-For'];
    } else {
      if(array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
      ) {
        $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
      } else {

        $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
      }
    }

    return $the_ip;
  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
