<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Customers extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('IncentivePrograms');
    $this->load->model('CreditListings');
    $this->load->model('BidMarket');
    $this->load->model('Trading');
    $this->load->model('Trades');
    $this->load->model('Taxpayersdata');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Email_Model');
    $this->load->library('filemanagementlib');

  }

  function index() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "customers";
      $data['current_tab_key'] = "all";
      $data['lnav_key'] = "customers";

      $data['object'] = "customer";

      $customers = $this->Members_Model->get_customers($this->cisession->userdata('dmaId'));
      $data['customers'] = $customers['customers'];

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("customers/all", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function add() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "customers";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "customers";

      $data['object'] = "customer";
      $data['objectCamel'] = "customer";

      $data['mode'] = "add";

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("customers/add", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function insert() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $cusDmaId = $this->cisession->userdata('dmaId');

    if($this->input->post('email') != "" && $this->input->post('email') != " ") {

      //Get new user data
      $cRequest['cusDmaId'] = $cusDmaId;
      $cRequest['cusType'] = ($this->cisession->userdata('dmaType') == "advisor") ? "advisor_customer" : $this->input->post('cusType');
      $cRequest['companyName'] = $this->input->post('companyName');
      $cRequest['firstName'] = $this->input->post('firstName');
      $cRequest['lastName'] = $this->input->post('lastName');
      $cRequest['email'] = strtolower($this->input->post('email'));

      //Get currently logged in user
      $existingUserCheck = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      //Get email address of currently logged in user
      $currentLoggedInEmailDomain = strtolower(substr(strrchr($existingUserCheck['email'], "@"), 1));
      $newUserEmailDomain = strtolower(substr(strrchr($cRequest['email'], "@"), 1));

      if($currentLoggedInEmailDomain == $newUserEmailDomain) {

        //Do NOT add a customer
        $this->session->set_flashdata('sameEmailDomainBlock', 1);

      } else {

        //Check if new post email address is already a user
        $existingUserCheck = $this->Members_Model->get_member_by_email($this->input->post('email'));
        $isExistingUser = (sizeof($existingUserCheck) > 0) ? "yes" : "no";

        if($isExistingUser == "yes") {
          //Add a new customer, but use an existing account
          $cRequest['userId'] = $existingUserCheck['userId'];
          $cusId = $this->Members_Model->insert_dma_customer($cRequest);
        } else {
          //Create new account and insert new customer
          $cusUserId = $this->Members_Model->insert_user_customer_level();
          $cRequest['userId'] = $cusUserId;
          $cusId = $this->Members_Model->insert_dma_customer($cRequest);
          //open a DMA account and then assign user as lead admin user of it
          $dRequest['companyName'] = $this->input->post('companyName');
          $dRequest['cusUserId'] = $cusUserId;
          $dRequest['dmaType'] = ($this->cisession->userdata('dmaType') == "broker") ? "customer_broker" : "customer_advisor";
          $newDmaId = $this->DmaAccounts->insert_dma_account_for_customer($dRequest);
          $dmaMemberRequest['dmaMUserId'] = $cusUserId;
          $dmaMemberRequest['dmaMDmaId'] = $newDmaId;
          $dmaMemberRequest['dmaMGroupId'] = 1;
          $dmaMemberRequest['dmaMStatus'] = 1;
          $this->DmaAccounts->create_dmamember($dmaMemberRequest);

        }
        $send_activation_link = $this->input->post('send_activation_link');
        if($send_activation_link == "yes") {
          $this->send_activation_link($cusId, "block_forward");
          $this->session->set_flashdata('activationCodeSentSuccess', 1);
        }

        $this->session->set_flashdata('insertSuccess', 1);

      }

    }

    redirect('customers');

  }

  function edit($cusId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "customers";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "customers";

      $data['object'] = "customer";
      $data['objectCamel'] = "customer";

      $cRequest['cusId'] = $cusId;
      $data['customer'] = $this->Members_Model->get_customer($cRequest);

      if($data['customer']['cusDmaId'] != $this->cisession->userdata('dmaId')) {
        redirect('/dashboard');
      }

      $userData = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      if($userData['agreed'] == 1) { //if activated user account
        $data['customerHasRegistered'] = true;
      } else {
        $data['customerHasRegistered'] = false;
      }

      $data['mode'] = "edit";

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("customers/add", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function save($cusId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      if($this->input->post('email') != "" && $this->input->post('email') != " ") {

        $cRequest['cusId'] = $cusId;
        $data['customer'] = $this->Members_Model->get_customer($cRequest);
        if($data['customer']['cusDmaId'] != $this->cisession->userdata('dmaId')) {
          redirect('/dashboard');
        }

        $existingUserCheck = $this->Members_Model->get_member_by_email($this->input->post('email'));
        if(strtolower($data['customer']['cusEmail']) == strtolower($this->input->post('email')) && sizeof($existingUserCheck) > 0) {

          //Update existing customer information (but don't update user account)
          $this->Members_Model->update_user_customer_level($cusId);

        }

        $this->session->set_flashdata('saveSuccess', 1);

      }

      redirect('customers');

    }

  }

  function send_activation_link($cusId, $blockForward = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      if($cusId > 0) {

        $cRequest['cusId'] = $cusId;
        $data['customer'] = $this->Members_Model->get_customer($cRequest);
        if($data['customer']['cusDmaId'] != $this->cisession->userdata('dmaId')) {
          redirect('/dashboard');
        }

        $userData = $this->Members_Model->get_member_by_id($data['customer']['cusUserId']);
        if($userData['agreed'] == 1) { //if activated user account
          $cusUpdate['cusStatus'] = 1; //then auto activate customer
        } else {
          $cusUpdate['cusStatus'] = 2;
        }
        $this->Members_Model->update_user_customer($cusId, $cusUpdate);

        //Get partner information
        $pRequest['partnerDmaId'] = $this->cisession->userdata('dmaId');
        $partnerData = $this->Members_Model->get_partner($pRequest);

        //Send Email notice to person shared to
        $emailData['updateType'] = "send_customer_activation_email";
        $emailData['firstName'] = $data['customer']['cusFirstName'];
        $emailData['emailaddress'] = $data['customer']['cusEmail'];
        $emailData['brokerName'] = $this->cisession->userdata('dmaTitle');
        $emailData['brokerDmaId'] = $this->cisession->userdata('dmaId');
        $emailData['brokerLeadUser'] = $this->cisession->userdata('firstName') . ' ' . $this->cisession->userdata('lastName');
        $emailData['brokerUsername'] = $partnerData['partnerUsername'];
        $emailData['brokerContactInfo'] = $partnerData['pContacts'];
        $emailData['isExistingUser'] = ($userData['agreed'] == 1) ? true : false;
        $emailData['welcomeNameTemplate'] = 1;
        $emailData['button'] = 0;
        $emailData['subject'] = $emailData['brokerName'] . ' has invited you to open an account on The OIX';
        $emailData['headline'] = 'Gain%20Access';
        $emailData['customerType'] = 'broker';
        $emailData['oixAdvisors'] = true;
        $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $emailData['emailaddress'], $emailData);

        //Insert message
        $msType = "update";
        $msAction = "access_approved_broker";
        $msListingId = "";
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Inventory Access Granted by Broker: " . $this->cisession->userdata('dmaTitle');
        $msTitle2 = $msTitle;
        $msTitleShort = $msTitle;
        $msTitle2Short = $msTitleShort;
        $msTitleShared = $msTitle;
        $msTitleSharedShort = $msTitleShort;
        $msContent = $this->cisession->userdata('dmaId');
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $data['customer']['userId'];
        $secondDmaMainUserId = "";
        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = false;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        if($blockForward != "block_forward") {
          $this->session->set_flashdata('activationCodeSentSuccess', 1);
          redirect('customers');
        }

      }

    }

  }

  function delete_customer($cusId) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      if($cusId > 0) {

        $cRequest['cusId'] = $cusId;
        $data['customer'] = $this->Members_Model->get_customer($cRequest);
        if($data['customer']['cusDmaId'] != $this->cisession->userdata('dmaId')) {
          redirect('/dashboard');
        }

        $cusUpdate['cusStatus'] = -1;
        $this->Members_Model->update_user_customer($cusId, $cusUpdate);

      }

    }

    redirect('customers');

  }

  function revoke_access($cusId) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      if($cusId > 0) {

        $cRequest['cusId'] = $cusId;
        $data['customer'] = $this->Members_Model->get_customer($cRequest);
        if($data['customer']['cusDmaId'] != $this->cisession->userdata('dmaId')) {
          redirect('/dashboard');
        }

        $cusUpdate['cusStatus'] = 0;
        $this->Members_Model->update_user_customer($cusId, $cusUpdate);

      }

    }

    redirect('customers');

  }

  function customer_welcome($partnerUsername, $success = "") {

    $data['showSuccessScreen'] = ($success == "success") ? true : false;

    $request['partnerUsername'] = $partnerUsername;
    $data['brokerData'] = $this->Members_Model->get_partner($request);

    $this->load->view('includes/header_customer_portal', $data);
    $this->load->view("customers/customer_welcome", $data);

  }

}
