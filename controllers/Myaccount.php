<?php
if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Myaccount extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library(['form_validation']);
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('CreditListings');
    $this->load->model('IncentivePrograms');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Email_Model');
    $this->load->library('Globalsettings');
  }

  function account_configuration() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "myaccount_configuration";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "";

      $output = '';

      $twig = \OIX\Util\TemplateProvider::getTwig();
      $template = $twig->load('myaccount/account_configuration.twig');
      $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
      $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

      $output .= $this->load->view('includes/left_nav', $data, true);
      $output .= $this->load->view('includes/tab_nav', $data, true);
      $output .= $template->render($data);
      $output .= $this->load->view('includes/footer-2', true);
      echo $output;

    }

  }

  function account_settings() {

    redirect('/myaccount/account_configuration');

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "mysettings";
      $data['current_tab_key'] = "my_settings_basic";
      $data['lnav_key'] = "mysettings";

      $data['page'] = 'general';

      $data['details'] = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $data['userData'] = $data['details'];
      $data['dmaData'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

      $data['pwMinLength'] = ($data['userData']['pwCharacterCountReq'] > 8) ? $data['userData']['pwCharacterCountReq'] : 8;

      $data['account_types'] = [
          "0" => "",
          "1" => "Seller",
          "2" => "Buyer",
          "3" => "Buyer/Seller",
      ];

      $data['state'] = $this->IncentivePrograms->get_us_states();

      if($this->cisession->userdata('level') == 4) {
        $data['myDmaMembership'] = $this->DmaAccounts->get_dma_member_levels_for_member($this->cisession->userdata('dmaId'), $this->cisession->userdata('userId'));
      }

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view('myaccount/account_settings', $data);
      $this->load->view('includes/footer-2');

    }
  }

  function password_regex_match($pwString) {
    $this->tank_auth->password_regex_match($pwString);
  }

  function account_save() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');
    }

    if($_POST['page'] == 'signer') {
      $url = '/signer/account_settings';
    } else {
      $url = '/myaccount/account_settings';
    }

    $data['userData'] = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
    $data['pwMinLength'] = ($data['userData']['pwCharacterCountReq'] > 8) ? $data['userData']['pwCharacterCountReq'] : 8;

    $data['customPWerrors'] = [];

    $account = [
        [
            'field' => 'password',
            'rules' => 'required|xss_clean|matches[confirm_password]|min_length[8]|callback_password_regex_match',
        ],
        [
            'field' => 'confirm_password',
            'rules' => 'required',
        ],
    ];

    $this->form_validation->set_rules($account);

    if($this->form_validation->run($account)) {

      if(!is_null($data = $this->tank_auth->modify_user(
          $this->cisession->userdata('userId'),
          $_POST['password'],
          $_POST['account_type'],
          $_POST['job_title'],
          $_POST['company_address_1'],
          $_POST['company_address_2'],
          $_POST['city'],
          $_POST['account_state'],
          $_POST['zipcode'],
          $_POST['phonenumber'],
          $_POST['cellnumber']
      ))) {
        $this->cisession->set_userdata('update_success', '1');
        redirect($url);
      } else {
        redirect($url);
      }

    } else {
      $this->cisession->set_userdata('update_success', '2');
      redirect('/myaccount/account_settings');
    }

  }

  function password_change_success() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "mysettings";
      $data['current_tab_key'] = "account";
      $data['lnav_key'] = "mysettings";

      $data['page'] = 'general';

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view('myaccount/password_change_success', $data);
      $this->load->view('includes/footer-2');

    }

  }

  function credit_view_settings() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "mysettings";
      $data['current_tab_key'] = "settings_credits_view";
      $data['lnav_key'] = "mysettings";

      $data['columnsOptions'] = [];

      //Get system available data points
      $dpRequest['getMyCreditsView'] = 1;
      $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
      $dpRequest['dpObjectType'] = 'credit';
      $dataPoints = $this->CreditListings->get_data_points($dpRequest);
      $data['dataPoints'] = $dataPoints['dataPoints'];
      $data['dataPointsKeysOnly'] = $dataPoints['dataPointsKeysOnly'];
      $availableColumns = $data['dataPointsKeysOnly'];
      $savedColumns = $this->cisession->userdata('creditViewConfig');

      //Get custom credit columns
      if($savedColumns != "") {
        //Do nothing
        $savedColumns = $savedColumns;
        //$savedColumns = json_decode($savedColumns, true);
        //var_dump($savedColumns); throw new \Exception('General fail');
      } else {
        $defaults = $this->globalsettings->get_global_setting("my_credits_view_default");
        $savedColumns = $defaults['gsValueLong'];
        $savedColumns = json_decode($savedColumns, true);
      }

      $data['columnsOptions'][0] = array_diff($availableColumns, $savedColumns);
      $data['columnsOptions'][1] = $savedColumns;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view('myaccount/settings_credit_view', $data);
      $this->load->view('includes/footer-2');

    }

  }

  function save_credit_view() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      $this->DmaAccounts->update_credit_view_settings($this->cisession->userdata('userId'));

      $columns = explode(',', $this->input->post('columns'));
      $this->cisession->set_userdata('creditViewConfig', $columns);

      $this->session->set_flashdata('saveSuccess', 1);

      redirect('/myaccount/credit_view_settings');

    }

  }

  function notifications_settings($old = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "mynotifications";
      $data['current_tab_key'] = "notifications";
      $data['lnav_key'] = "mysettings";

      $data['states'] = $this->IncentivePrograms->get_all_us_states();
      //$data['ipCategories'] = $this->IncentivePrograms->get_categories_for_notifications();

      $userId = $this->cisession->userdata('userId');

      $ns = $this->IncentivePrograms->get_notifications_id_for_user($userId);
      if($ns != "") {
        $data['ns'] = $this->IncentivePrograms->get_notifications_settings_for_user($userId);
      } else {
        $data['ns'] = "";
      }

      $data['full'] = $old;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("myaccount/notifications_settings", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function notifications_save() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $userId = $this->cisession->userdata('userId');

      //Get Jurisdictions selected
      $states = $this->IncentivePrograms->get_all_us_states();
      $nsStatesIds = "";
      $nsStatesCodes = "";
      foreach($states as $s) {
        if($this->input->post('n_jurisdiction_' . $s['id']) == 1) {
          $nsStatesIds = $nsStatesIds . $s['id'] . ',';
          $nsStatesCodes = $nsStatesCodes . $s['state'] . ',';
        }
      }

      //Get IPCategories selected
      /*
      $IPCategories = $this->IncentivePrograms->get_categories_for_notifications();
      $nsIPCategories = "";
      foreach($IPCategories as $ipc) {
        if($this->input->post('n_category_'.$ipc['id']) == 1) {
          $nsIPCategories = $nsIPCategories.$ipc['id'].',';
        }
      }
      */
      $nsIPCategories = "";

      //Get Activity selected
      $nsActivity = "";
      if($this->input->post('n_activity_new_credit') == 1) {
        $nsActivity = $nsActivity . 'new_credit,';
      }
      if($this->input->post('n_activity_new_bid') == 1) {
        $nsActivity = $nsActivity . 'new_bid,';
      }
      if($this->input->post('n_activity_new_trade') == 1) {
        $nsActivity = $nsActivity . 'new_trade,';
      }
      if($this->input->post('n_activity_credit_cancelled_expired') == 1) {
        $nsActivity = $nsActivity . 'credit_cancelled_expired,';
      }
      if($this->input->post('n_activity_bid_cancelled_expired') == 1) {
        $nsActivity = $nsActivity . 'bid_cancelled_expired,';
      }

      //Get Minimum Credit Amount
      if($this->input->post('n_activity_under') == 1) {
        $nsMinimum = 1;
      }
      $nsMinimumAmount = $this->input->post('n_activity_under_amount');

      //Get Credity Type selected
      $nsCreditType = "";
      if($this->input->post('n_credit_state') == 1) {
        $nsCreditType = $nsCreditType . 'state,';
      }
      if($this->input->post('n_credit_federal') == 1) {
        $nsCreditType = $nsCreditType . 'federal,';
      }
      if($this->input->post('n_credit_certificated_transferable') == 1) {
        $nsCreditType = $nsCreditType . 'certificated_transferable,';
      }
      if($this->input->post('n_credit_allocated') == 1) {
        $nsCreditType = $nsCreditType . 'allocated,';
      }

      //Get Frequency selected
      $nsFrequency = $this->input->post('n_frequency');

      //Get my account frequency selected
      $nsDailyAlertSummary = $this->input->post('nsDailyAlertSummary');

      //Get general data for OIX ADMIN email
      $emailData['user'] = $this->cisession->userdata('firstName') . ' ' . $this->cisession->userdata('lastName');
      $emailData['nsStatesCodes'] = $nsStatesCodes;
      $emailData['nsActivity'] = $nsActivity;
      $emailData['nsCreditType'] = $nsCreditType;
      $emailData['nsMinimum'] = $nsMinimum;
      $emailData['nsMinimumAmount'] = $nsMinimumAmount;
      $emailData['nsFrequency'] = $nsFrequency;
      $emailData['nsDailyAlertSummary'] = $nsDailyAlertSummary;

      if($this->cisession->userdata('level') == 4) {
        $emailData['userType'] = "Admin User";
        $emailData['dmaAccount'] = [];
        $emailData['companyName'] = $emailData['dmaAccount']['title'];
      } else {
        $emailData['userType'] = "General Member";
        $emailData['companyName'] = $this->cisession->userdata('company');
      }

      $ns = $this->IncentivePrograms->get_notifications_id_for_user($userId);
      if($ns != "") {

        //update existing record
        $this->IncentivePrograms->update_notifications_settings_by_id($ns['nsId'], $nsStatesIds, $nsStatesCodes, $nsIPCategories, $nsActivity, $nsCreditType, $nsFrequency, $nsMinimum, $nsMinimumAmount, $nsDailyAlertSummary);

        //send OIX Admin Email
        if($this->input->post('n_frequency') != "never") {
          //updated
          $emailData['updateType'] = 'notificationsSettingsUpdated';
          $emailData['updateTypeName'] = 'User Notifications Settings Updated';
          $emailData['headline'] = "User Notifications Settings Updated";
          $this->Email_Model->_send_email('oix_admin_update', 'Notifications Settings Updated', $this->config->item("oix_admin_emails_array"), $emailData);
        } else {
          //suspended
          $emailData['updateType'] = 'notificationsSettingsSuspended';
          $emailData['updateTypeName'] = 'User Notifications Settings Suspended';
          $emailData['headline'] = "User Notifications Settings Suspended";
          $this->Email_Model->_send_email('oix_admin_update', 'New Notifications Settings Suspended', $this->config->item("oix_admin_emails_array"), $emailData);
        }

      } else {

        //create new record
        $this->IncentivePrograms->insert_notifications_settings_for_user($userId, $nsStatesIds, $nsStatesCodes, $nsIPCategories, $nsActivity, $nsCreditType, $nsFrequency, $nsMinimum, $nsMinimumAmount, $nsDailyAlertSummary);

        //send OIX Admin Email
        if($this->input->post('n_frequency') != "never") {
          //new
          $emailData['updateType'] = 'notificationsSettingsCreated';
          $emailData['updateTypeName'] = 'User Notifications Settings Created';
          $emailData['headline'] = "User Notifications Settings Created";
          $this->Email_Model->_send_email('oix_admin_update', 'New Notifications Settings Created', $this->config->item("oix_admin_emails_array"), $emailData);
        } else {
          //suspended
          $emailData['updateType'] = 'notificationsSettingsSuspended';
          $emailData['updateTypeName'] = 'User Notifications Settings Suspended';
          $emailData['headline'] = "User Notifications Settings Suspended";
          $this->Email_Model->_send_email('oix_admin_update', 'New Notifications Settings Suspended', $this->config->item("oix_admin_emails_array"), $emailData);
        }

      }

      if($this->input->post('n_frequency') != "never") {
        $this->session->set_flashdata('messageSuccess', 1);
      }

      redirect('myaccount/notifications_settings');

    }

  }

  function rules_cms() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['tab_nav'] = "tab_nav_account";

    $this->load->view('includes/header', $data);
    $this->load->view("account/rules_cms");
    $this->load->view('includes/footer', $data);
  }

  function messages() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "messages";
      $data['lnav_key'] = "messages";

      //Get pending messages for this user in this account
      $calData['userId'] = $this->cisession->userdata('userId');
      $calData['dmaId'] = $this->cisession->userdata('dmaId');
      $calData['accountType'] = $this->cisession->userdata('level');
      $calData['warned'] = 2;
      $calData['read'] = 2;
      $calData['limit'] = 500;
      $calData['msTypeExcludeArray'] = ["calendar_alert", "alert"];
      $data['messages'] = $this->Members_Model->get_messages_of_user($calData);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("myaccount/messages", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function message($mmId) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      redirect('/myaccount/messages');

      $data['tabArray'] = "message";
      $data['lnav_key'] = "messages";

      //Get pending messages for this user in this account
      //$data['message'] = $this->Members_Model->get_member_message($mmId);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("myaccount/message", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function mark_message_read($thisMessage) {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/auth/login_form');
    } else {

      $message = $this->Members_Model->get_member_message($thisMessage);
      if($message['mmUserId'] == $this->cisession->userdata('userId')) {
        $this->Members_Model->update_member_message_status($thisMessage, 1, 1);
      }

      redirect('/myaccount/messages');

    }

  }

  function mark_message_read_and_forward($mmId) {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/auth/login_form');
    } else {

      $message = $this->Members_Model->get_member_message($mmId);
      if($message['mmUserId'] == $this->cisession->userdata('userId')) {
        $this->Members_Model->update_member_message_status($mmId, 1, 1);
      }

      redirect('/' . $_GET['fwd_url']);

    }

  }

  function mark_message_unread($mmId) {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/auth/login_form');
    } else {

      $message = $this->Members_Model->get_member_message($mmId);
      if($message['mmUserId'] == $this->cisession->userdata('userId')) {
        $this->Members_Model->update_member_message_status($mmId, 0, 0);
      }

      redirect('/myaccount/messages');

    }

  }

  function mark_all_read($alertCategory = "", $listingId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/auth/login_form');
    } else {

      $msTypeExcludeArray = [];
      $msTypeOnly = [];

      if($alertCategory == "messages") {
        $calData['userId'] = $this->cisession->userdata('userId');
        $calData['dmaId'] = $this->cisession->userdata('dmaId');
        $calData['accountType'] = $this->cisession->userdata('level');
        $calData['listingId'] = $listingId;
        $calData['warned'] = 2;
        $calData['read'] = 2;
        $calData['limit'] = 500;
        $calData['msTypeExcludeArray'] = ["calendar_alert", "alert"];
        $messages = $this->Members_Model->get_messages_of_user($calData);
        if($listingId > 0) {
          $redirect = '/dashboard/credit/' . $listingId;
        } else {
          $redirect = '/myaccount/messages';
        }
      }
      if($alertCategory == "calendar_alerts") {
        $calData['userId'] = $this->cisession->userdata('userId');
        $calData['dmaId'] = $this->cisession->userdata('dmaId');
        $calData['accountType'] = $this->cisession->userdata('level');
        $calData['listingId'] = $listingId;
        $calData['warned'] = 2;
        $calData['read'] = 2;
        $calData['limit'] = 500;
        $calData['msTypeOnly'] = ["calendar_alert"];
        $messages = $this->Members_Model->get_messages_of_user($calData);
        if($listingId > 0) {
          $redirect = '/dashboard/credit/' . $listingId . '/calendar';
        } else {
          $redirect = '/calendar';
        }
      }

      $mArray = [];
      foreach($messages as $m) {
        array_push($mArray, $m['mmId']);
      }

      $this->Members_Model->mark_messages_read_by_array($mArray);

      redirect($redirect);

    }

  }

}
