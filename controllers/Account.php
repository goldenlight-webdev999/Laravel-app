<?php
if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Account extends CI_Controller {
  protected $logger;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library(['form_validation']);
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('IncentivePrograms');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Email_Model');

    $this->logger = Logger::getInstance();

  }

  function password_regex_match($pwString) {
    return $this->tank_auth->password_regex_match($pwString);
  }

  function index() {

    redirect('get_started');

    $account = [
        [
            'field' => 'firstname',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'lastname',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'email',
            'rules' => 'xss_clean|required|trim|valid_email',
        ],
        [
            'field' => 'company_name',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'job_title',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'phonenumber',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => '00N0W000009LR6L',
            'rules' => 'trim|xss_clean',
        ],

    ];

    $this->form_validation->set_rules($account);

    if($this->form_validation->run($account)) {

      $request = $_POST;

      //Check if email address is already in use
      if($this->tank_auth->check_if_email_exists($_POST['email'])) {

        if(!is_null($data = $this->tank_auth->create_user(
            $_POST['firstname'],
            $_POST['lastname'],
            $_POST['email'],
            '', //Password blank
            4, //$_POST['account_type'] - make this 4 hard coded for now
            $_POST['job_title'],
            $_POST['company_name'],
            '', //company_address_1
            '', //company_address_2
            '', //city
            '', //account_state
            '', //zipcode
            $_POST['phonenumber'],
            '', //cellnumber
            '', //corporateWebsite
            '' //linkedInProfile
        ))) {

          //send email
          $emailData['firstName'] = $this->input->post('firstname');
          $emailData['lastName'] = $this->input->post('lastname');
          $emailData['email'] = $this->input->post('email');
          $emailData['companyName'] = $this->input->post('company_name');
          $emailData['phoneNumber'] = $this->input->post('phonenumber');

          // Send email to new member applying
          $emailData['headline'] = "Request%20Received";
          $emailData['welcomeNameTemplate'] = 1;
          $emailData['button'] = 0;
          $emailData['updateType'] = 'demoRequestReceiptConfirmation';
          $bcc = $this->config->item('oix_admin_emails_array');
          $this->Email_Model->_send_email('member_template_1', 'OIX Information Request Received', $emailData['email'], $emailData, $bcc);

          //send OIX ADMIN email
          $emailData['fullName'] = $_POST['firstname'] . ' ' . $_POST['lastname'];
          $emailData['cellnumber'] = '';
          $emailData['state'] = '';
          $emailData['accountTypeName'] = '';
          $emailData['updateType'] = 'newMembershipApplication';
          $emailData['updateTypeName'] = 'New Demo Request - ' . $emailData['companyName'];
          $this->Email_Model->_send_email('oix_admin_update', 'New Demo Request Submitted', $this->config->item("oix_sales_inbound_emails_array"), $emailData);

          $result['success'] = true;

        } else {

          $result['success'] = false;
          $result['error'] = 'input_error';

        }

      } else {

        $result['success'] = false;
        $result['error'] = 'email_address_exists';

      }

      $json = json_encode($result);
      echo $json;

    } else {

      $data['page_title'] = "OIX GET STARTED";
      $data['column_override'] = "span-12";
      $data['headerswitch'] = "corp";

      $this->load->view('includes/header', $data);
      $this->load->view('account/general', $data);
      $this->load->view('includes/footer');

    }

  }

  function modify() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['details'] = $this->Members_Model->get_current_member($this->cisession->userdata('userId'));

    $data['state'] = $this->IncentivePrograms->get_states_short();

    $data['current_tab_key'] = "settings";
    $data['tab_nav'] = "tab_nav_account";
    $data['page_title'] = "MODIFY YOUR ACCOUNT";
    $data['column_override'] = "span-12";

    $data['account_types'] = [
        "0" => "",
        "1" => "Seller",
        "2" => "Buyer",
        "3" => "Buyer/Seller",
    ];

    $this->load->view('includes/header', $data);
    $this->load->view('account/modify', $data);
    $this->load->view('includes/footer');
  }

  function check_account($thislist) {
    if($thislist == "0" || $thislist == "00") {
      $this->form_validation->set_message('check_account', 'You must select an account type.');

      return false;
    }

    return true;
  }

  function create_user_lite() {

    //Default PW requirements
    if($this->input->post('invite_type') == "signature") {
      $pwMinLength = 6;
    } else {
      $pwMinLength = 8;
    }

    //Set default
    $account_type = 8;

    //if invite is set
    $invite = [];
    if($this->input->post('iHash') != "") {
      $invite = $this->Members_Model->get_invite_by_hash($this->input->post('iHash'));
      if($invite['iType'] != "signature" && $invite['iType'] != "creditShare") {
        $pwMinLength = ($invite['iPwCharacterCountReq'] > 0) ? $invite['iPwCharacterCountReq'] : null;
      }
      if($invite['iType'] == "dmaMember" || $invite['iType'] == "creditShare") {
        $account_type = 4;
      } else {
        $account_type = 8;
      }
    }
    $isSSO = false;
    if(strlen($invite['external_auth_type']) > 0 && $invite['iType'] != "creditShare") {
      $isSSO = true;
    }

    //Check to make sure the password is valid (signers get less strict PW requirements and Admin Users have strict requirements)
    $account = [
        [
            'field' => 'password',
            'rules' => 'required|matches[confirm_password]|min_length[' . $pwMinLength . ']|callback_password_regex_match',
        ],
        [
            'field' => 'confirm_password',
            'rules' => 'trim|required|matches[password]',
        ],
    ];

    if(!$isSSO) {
      $this->form_validation->set_rules($account);
    }
    $formValidationResult = $this->form_validation->run();
    //Disregard form validation for SSO signup
    if(!$formValidationResult && $isSSO) {
      $formValidationResult = true;
    }

    if($formValidationResult) {
      $data = $this->tank_auth->getUserForEmail($this->input->post('email'));
      if($isSSO && !is_null($data)) {
        //DO nothing, we have the user!
      }
      else {
        $data = $this->tank_auth->insert_user_lite(
            $this->input->post('firstname'),
            $this->input->post('lastname'),
            $this->input->post('companyname'),
            $this->input->post('email'),
            $this->input->post('password'),
            $account_type,
            $invite
        );
      }

      if(!is_null($data)) {

        //This is ugly as sin, but at this point I just don't care
        if($isSSO && $this->input->post('invite_type') == 'dmaMember') {
          $dmaInfo = $this->DmaAccounts->get_dma_account_by_id($invite['iDmaId']);

          $this->Members_Model->insert_dmamember($invite['inviteId'], $data['userId']);

          //If this is an invitation to a PARENT DMA Account, then let's automatically create admin users for this person in each child account and flag them as "Parent Admin"
          if($dmaInfo['isParentDma'] == 1) {
            $childAccounts = $this->DmaAccounts->get_child_accounts_of_dma_account($invite['iDmaId']);
            foreach($childAccounts as $ca) {
              $request['dmaMUserId'] = $data['userId'];
              $request['dmaMDmaId'] = $ca['dmaId'];
              $request['dmaMGroupId'] = 1;
              $request['dmaMParentAdminFlag'] = 1;
              $request['dmaMAddedBy'] = $invite['iFromUserId'];
              $request['dmaMJoinDate'] = time();
              $this->Members_Model->create_dmamember($request);
            }
          }

          //$this->tank_auth->set_member_to_dma_status();
          /*
          $myDmaAccounts = $this->DmaAccounts->get_my_dma_accounts($data['userId']);

          if(sizeof($myDmaAccounts) > 1) {
            $this->tank_auth->set_member_to_multi_dma();
          }*/

          $emailData['email'] = $invite['email'];
          $emailData['iEmail'] = $invite['iEmail'];
          $emailData['iFirstName'] = $invite['iFirstName'];
          $emailData['iLastName'] = $invite['iLastName'];
          $emailData['firstName'] = $invite['firstName'];
          $emailData['lastName'] = $invite['lastName'];
          $emailData['inviteId'] = $invite['inviteId'];
          $emailData['title'] = $invite['title'];
          $emailData['dmaGTitle'] = $invite['dmaGTitle'];
          $emailData['dmaInfo'] = $dmaInfo;

          //Send a welcome email to new user
          $this->Email_Model->_send_email('invite_dma_welcome', 'Welcome to the ' . $dmaInfo['title'] . ' OIX System', $invite['iEmail'], $emailData);

          //Send confirmation to inviter that invite has been accepted
          $this->Email_Model->_send_email('invite_dmamember_accepted', 'Your OIX Admin User Invitation Was Accepted', $invite['email'], $emailData);

          $this->session->set_flashdata('insertSuccess', 1);
        }

        //Log success
        $this->logger->info("Invite  > Accepted > Invite ID: " . $invite['inviteId'] . " >  Type: " . $invite['iType']);

        echo 1;

        return true;
      } else {

        //Log success
        $this->logger->info("Invite > Failed DB Insert > Invite ID: " . $invite['inviteId'] . " >  Type: " . $invite['iType']);

        echo 0;

        return false;
      }

    } else {

      //Log success
      $this->logger->info("Invite > Failed Password Req > Invite ID: " . $invite['inviteId'] . " >  Type: " . $invite['iType']);

      echo 0;

      return false;

    }

  }

  function confirm_modify() {

    $data['page_title'] = "Account has been updated";
    $data['column_override'] = "span-12";
    $this->load->view('includes/header', $data);
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
      redirect('/account/modify');
    }

    $this->load->view('includes/footer');

  }

  function welcome() {
    $data['page_title'] = "YOUR OIX MEMBERSHIP APPLICATION HAS BEEN SUCCESSFULLY RECIEVED";
    $data['column_override'] = "span-12";
    $data['headerswitch'] = "corp";
    $this->load->view('includes/header', $data);
    $this->load->view('account/welcome', $data);
    $this->load->view('includes/footer');
  }

  function rules_cms() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "";
      $data['tabArray'] = "";

      $this->load->view('includes/left_nav', $data);
      $this->load->view("account/rules_cms");
      $this->load->view('includes/footer-2', $data);

    }
  }

}
