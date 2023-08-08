<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Dmamembers extends CI_Controller {
  protected $logger;

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
    $this->load->library('memberpermissions');

    $this->logger = Logger::getInstance();
  }

  function index() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user has access to manage admin users
      $this->memberpermissions->checkAdminUserManageAccess();

      $data['tabArray'] = "dmamembers";
      $data['current_tab_key'] = "alldmamembers";
      $data['lnav_key'] = "administrative";

      $data['page_version'] = "active";

      $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
      $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');
      $data['maxAdminsUtilized'] = sizeof($data['dmamembers']) + sizeof($data['dmaInvites']);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dmamembers/all", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function archived() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user has access to manage admin users
      $this->memberpermissions->checkAdminUserManageAccess();

      $data['tabArray'] = "dmamembers";
      $data['current_tab_key'] = "archived";
      $data['lnav_key'] = "administrative";

      $data['page_version'] = "archived";

      $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 0);

      $data['dmamembersActive'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
      $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');
      $data['maxAdminsUtilized'] = sizeof($data['dmamembersActive']) + sizeof($data['dmaInvites']);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dmamembers/all", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function add() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user has access to manage admin users
      $access = $this->memberpermissions->checkAdminUserManageAccess();

      $data['lnav_key'] = "administrative";
      $data['page_version'] = "add_member";

      if($access['access'] == 0) {

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/widgets/planBlockMessage', $bData);
        $this->load->view('includes/footer-2', $data);

      } else {

        $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
        $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');
        $data['maxAdminsUtilized'] = sizeof($data['dmamembers']) + sizeof($data['dmaInvites']);

        $data['adminLevels'] = $this->DmaAccounts->get_admin_user_levels();
        $data['dmaData'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));
        $data['dmamember'] = [];
        $data['dmamember']['dmaMUserId'] = "";
        $allowedDomains = $this->DmaAccounts->getValidDomainsForDMA($this->cisession->userdata('dmaId'));
        $domainWhitelist = [];
        foreach($allowedDomains as $allowedDomain) {
          $domainWhitelist[] = strtolower($allowedDomain['hostname']);
        }

        $data['dmaAllowedDomains'] = implode(', ', $domainWhitelist);

        $this->load->view('includes/left_nav', $data);
        if($data['maxAdminsUtilized'] >= $this->cisession->userdata('planMaxAdminUsers')) {
          $bData['adminUserBlock'] = true;
          $this->load->view('includes/widgets/planBlockMessage', $bData);
        } else {
          $this->load->view("dmamembers/add", $data);
        }
        $this->load->view('includes/footer-2', $data);

      }

    }
  }

  //Insert new admin user
  function insert() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //If user has access to manage admin users AND if the new member is of level 1 or 2, then it MUST be the Super Admin
    $this->memberpermissions->checkAdminUserManageAccess("", "", $this->input->post('memberLevel'));

    $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
    $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');
    $data['maxAdminsUtilized'] = sizeof($data['dmamembers']) + sizeof($data['dmaInvites']);
    if($data['maxAdminsUtilized'] >= $this->cisession->userdata('planMaxAdminUsers')) {
      throw new \Exception('General fail');
    }

    $data['emailDmaMemberExists'] = $this->Members_Model->check_if_dmamember_email_exists($this->input->post('mEmail'), $this->cisession->userdata('dmaId'));
    $data['emailIsValid'] = $this->DmaAccounts->isEmailValidForDMA($this->input->post('mEmail'), $this->cisession->userdata('dmaId'));

    if(!$data['emailDmaMemberExists'] && $data['emailIsValid']) {

      $dmaDefaultSettings = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

      //Save security settings (CHECK AGAINST BASELINE CONFIGS)
      $daysInactiveLimitReq = ($_POST['daysInactiveLimitReq'] > 0) ? $_POST['daysInactiveLimitReq'] : null;
      $iData['iDaysInactiveLimitReq'] = ($daysInactiveLimitReq > $dmaDefaultSettings['daysDefaultInactiveLimit']) ? $daysInactiveLimitReq : $dmaDefaultSettings['daysDefaultInactiveLimit'];

      $pwCharacterCountReq = ($_POST['pwCharacterCountReq'] > 8) ? $_POST['pwCharacterCountReq'] : 8; //Minimum is 8 by system default
      $iData['iPwCharacterCountReq'] = ($pwCharacterCountReq > $dmaDefaultSettings['pwDefaultCharacterCount']) ? $pwCharacterCountReq : $dmaDefaultSettings['pwDefaultCharacterCount'];

      $pwResetDaysReq = ($_POST['pwResetDaysReq'] > 0) ? $_POST['pwResetDaysReq'] : null;
      $iData['iPwResetDaysReq'] = ($pwResetDaysReq > $dmaDefaultSettings['pwDefaultResetDays']) ? $pwResetDaysReq : $dmaDefaultSettings['pwDefaultResetDays'];

      $pwReuseCountReq = ($_POST['pwReuseCountReq'] == "") ? null : $_POST['pwReuseCountReq'];
      $iData['iPwReuseCountReq'] = ($pwReuseCountReq > $dmaDefaultSettings['pwDefaultReuseCount']) ? $pwReuseCountReq : $dmaDefaultSettings['pwDefaultReuseCount'];

      $pwReuseDaysReq = ($_POST['pwReuseDaysReq'] == "") ? null : $_POST['pwReuseDaysReq'];
      $iData['iPwReuseDaysReq'] = ($pwReuseDaysReq > $dmaDefaultSettings['pwDefaultReuseDays']) ? $pwReuseDaysReq : $dmaDefaultSettings['pwDefaultReuseDays'];

      $iData['iType'] = 'dmaMember';
      $iData['iFirstName'] = $this->input->post('mFirstName');
      $iData['iLastName'] = $this->input->post('mLastName');
      $iData['iEmail'] = $this->input->post('mEmail');
      $iData['iContent1'] = $this->input->post('memberLevel');
      $newInviteId = $this->Members_Model->insert_invite($iData);

      //Get the invite we just created
      $data['invite'] = $this->Members_Model->get_invite($newInviteId);

      //Collect data for email
      $data['iEmail'] = $data['invite']['iEmail'];
      $data['iFirstName'] = $data['invite']['iFirstName'];
      $data['iLastName'] = $data['invite']['iLastName'];
      $data['firstName'] = $data['invite']['firstName'];
      $data['lastName'] = $data['invite']['lastName'];
      $data['iExpires'] = $data['invite']['iExpires'];
      $data['inviteId'] = $data['invite']['inviteId'];
      $data['iHash'] = $data['invite']['iHash'];
      $data['title'] = $data['invite']['title'];
      $data['dmaGTitle'] = $data['invite']['dmaGTitle'];

      //Send email
      $this->Email_Model->_send_email('invite_dmamember', 'Your ' . $data['title'] . ' Admin User Invitation', $data['iEmail'], $data);

      $this->logger->info("New Admin User Invited - By dmamember: " . $this->cisession->userdata('dmaMemberId') . " - Invite ID: " . $newInviteId);

      $this->session->set_flashdata('insertSuccess', 1);
      redirect('dmamembers/invites');

    } else {

      $this->logger->info("New Admin User Invite Failed - By dmamember: " . $this->cisession->userdata('dmaMemberId') . " - Email Exists");

      if(!$data['emailIsValid']) {
        $this->session->set_flashdata('emailAllowedBlock', 1);
      }
      if($data['emailDmaMemberExists']) {
        $this->session->set_flashdata('emailExistsBlock', 1);
      }

      redirect('dmamembers/invites');

    }

  }

  function edit($dmamemberId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "administrative";
      $data['current_tab_key'] = "";

      $data['page_version'] = "edit_member";

      $data['dmamember'] = $this->Members_Model->get_dma_member($dmamemberId, 0, 0);
      $data['adminLevels'] = $this->DmaAccounts->get_admin_user_levels();
      $data['dmaDefaultSettings'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));
      $data['dmaData'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

      $dmaMemberData = $data['dmamember'];
      //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
      $this->memberpermissions->checkAdminUserManageAccess($dmamemberId, "", $dmaMemberData['dmaMGroupId']);

      if($data['dmamember']['dmaMParentAdminFlag'] == 1) {
        echo "You can only edit a parent admin from within the parent account.";
        throw new \Exception('General fail');
      }

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('includes/taxpayer_header', $data);
      $this->load->view("dmamembers/add", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function save($dmamemberId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $dmaMemberData = $this->Members_Model->get_dma_member($dmamemberId, 0, 0);
    if($dmaMemberData['dmaMParentAdminFlag'] == 1) {
      echo "You can only edit a parent admin from within the parent account.";
      throw new \Exception('General fail');
    }

    //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
    $this->memberpermissions->checkAdminUserManageAccess($dmamemberId, "", $this->input->post('memberLevel'));

    //allowed permission groups
    $allowedPermissionGroups = [1, 2, 5, 6];
    if(!in_array($this->input->post('memberLevel'), $allowedPermissionGroups)) {
      echo "Error: 9008736";
      throw new \Exception('General fail');
    } //not allowed permission group

    $this->Members_Model->save_dma_member($dmamemberId);

    $dmaDefaultSettings = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

    //Save security settings (CHECK AGAINST BASELINE CONFIGS)
    $daysInactiveLimitReq = ($_POST['daysInactiveLimitReq'] > 0) ? $_POST['daysInactiveLimitReq'] : null;
    $securityRequest['daysInactiveLimitReq'] = ($daysInactiveLimitReq > $dmaDefaultSettings['daysDefaultInactiveLimit']) ? $daysInactiveLimitReq : $dmaDefaultSettings['daysDefaultInactiveLimit'];

    $pwCharacterCountReq = ($_POST['pwCharacterCountReq'] > 8) ? $_POST['pwCharacterCountReq'] : 8; //Minimum is 8 by system default
    $securityRequest['pwCharacterCountReq'] = ($pwCharacterCountReq > $dmaDefaultSettings['pwDefaultCharacterCount']) ? $pwCharacterCountReq : $dmaDefaultSettings['pwDefaultCharacterCount'];

    $pwResetDaysReq = ($_POST['pwResetDaysReq'] > 0) ? $_POST['pwResetDaysReq'] : null;
    $securityRequest['pwResetDaysReq'] = ($pwResetDaysReq > $dmaDefaultSettings['pwDefaultResetDays']) ? $pwResetDaysReq : $dmaDefaultSettings['pwDefaultResetDays'];

    $pwReuseCountReq = ($_POST['pwReuseCountReq'] == "") ? null : $_POST['pwReuseCountReq'];
    $securityRequest['pwReuseCountReq'] = ($pwReuseCountReq > $dmaDefaultSettings['pwDefaultReuseCount']) ? $pwReuseCountReq : $dmaDefaultSettings['pwDefaultReuseCount'];

    $pwReuseDaysReq = ($_POST['pwReuseDaysReq'] == "") ? null : $_POST['pwReuseDaysReq'];
    $securityRequest['pwReuseDaysReq'] = ($pwReuseDaysReq > $dmaDefaultSettings['pwDefaultReuseDays']) ? $pwReuseDaysReq : $dmaDefaultSettings['pwDefaultReuseDays'];

    if(strlen($dmaMemberData['external_auth_type']) == 0) {
      $this->Members_Model->update_member_security_settings($dmaMemberData['userId'], $securityRequest);
    }

    $secReqLOG = json_encode($securityRequest);
    $this->logger->info("Admin User Updated - dmamember ID: " . $dmamemberId . " - Security Configs: " . $secReqLOG, ['actionType' => 'ACCESS_MOD']);

    if($dmaMemberData['userId'] == $this->cisession->userdata('userId')) {
      //$this->tank_auth->setDMALevels($dmaMemberData);
      redirect('/auth/logout');
    } else {
      $this->Members_Model->erase_member_session($dmaMemberData['userId']);
    }

    $this->session->set_flashdata('saveSuccess', 1);
    redirect('dmamembers');

  }

  function archive_member($dmamemberId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //IF trying to archive the main admin user
    $dmaMemberData = $this->Members_Model->get_dma_member($dmamemberId, 0, 0);
    if($dmaMemberData['userId'] == $this->cisession->userdata('primUserId')) {
      redirect('/dashboard');
    }

    $dmaMemberData = $this->Members_Model->get_dma_member($dmamemberId, 0, 0);

    //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
    $this->memberpermissions->checkAdminUserManageAccess($dmamemberId, "", $dmaMemberData['dmaMGroupId']);

    $this->Members_Model->update_dma_member_status($dmamemberId, 0);

    $this->Members_Model->erase_member_session($dmaMemberData['userId']);

    $this->logger->info("Admin User Archived - By dmamember: " . $this->cisession->userdata('dmaMemberId') . " - archived dmamember ID: " . $dmamemberId, ['actionType' => 'ACCESS_MOD']);

    $this->session->set_flashdata('archiveSuccess', 1);
    redirect('dmamembers/archived');

  }

  function activate_member($dmamemberId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //First check if enough admin users available
    $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
    $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');
    $data['maxAdminsUtilized'] = sizeof($data['dmamembers']) + sizeof($data['dmaInvites']);

    if($data['maxAdminsUtilized'] >= $this->cisession->userdata('planMaxAdminUsers')) {

      $data['lnav_key'] = "administrative";
      $data['page_version'] = "add_member";

      $this->load->view('includes/left_nav', $data);
      $bData['adminUserBlock'] = true;
      $this->load->view('includes/widgets/planBlockMessage', $bData);
      $this->load->view('includes/footer-2', $data);

    } else {

      $dmaMemberData = $this->Members_Model->get_dma_member($dmamemberId, 0, 0);

      //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
      $this->memberpermissions->checkAdminUserManageAccess($dmamemberId, "", $dmaMemberData['dmaMGroupId']);

      $this->Members_Model->update_dma_member_status($dmamemberId, 1);

      $this->logger->info("Admin User Activated - By dmamember: " . $this->cisession->userdata('dmaMemberId') . " - activated dmamember ID: " . $dmamemberId, ['actionType' => 'ACCESS_MOD']);

      $this->session->set_flashdata('activateSuccess', 1);
      redirect('dmamembers');

    }

  }

  function invites() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user has access to manage admin users
      $this->memberpermissions->checkAdminUserManageAccess();

      $data['tabArray'] = "dmamembers";
      $data['current_tab_key'] = "allinvites";
      $data['lnav_key'] = "administrative";

      $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dmamembers/invites", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function edit_invite($inviteId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //Get the invite
      $data['invite'] = $this->Members_Model->get_invite($inviteId);

      $inviteData = $data['invite'];
      //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
      $this->memberpermissions->checkAdminUserManageAccess("", $inviteId, $inviteData['iContent1']);

      $data['dmaDefaultSettings'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));
      $data['dmaData'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

      $data['lnav_key'] = "administrative";
      $data['current_tab_key'] = "";
      $data['page_version'] = "edit_invite";

      $data['inviteId'] = $inviteId;

      $data['dmamember'] = $data['invite'];
      $data['dmamember']['firstName'] = $data['invite']['iFirstName'];
      $data['dmamember']['lastName'] = $data['invite']['iLastName'];
      $data['dmamember']['email'] = $data['invite']['iEmail'];
      $data['dmamember']['dmaMUserId'] = "";

      $data['adminLevels'] = $this->DmaAccounts->get_admin_user_levels();

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('includes/taxpayer_header', $data);
      $this->load->view("dmamembers/add", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function save_invite($inviteId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //Get the invite
      $inviteData = $this->Members_Model->get_invite($inviteId);

      //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
      $this->memberpermissions->checkAdminUserManageAccess("", $inviteId, $this->input->post('memberLevel'));

      $dmaDefaultSettings = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

      //Save security settings (CHECK AGAINST BASELINE CONFIGS)
      $daysInactiveLimitReq = ($_POST['daysInactiveLimitReq'] > 0) ? $_POST['daysInactiveLimitReq'] : null;
      $security['iDaysInactiveLimitReq'] = ($daysInactiveLimitReq > $dmaDefaultSettings['daysDefaultInactiveLimit']) ? $daysInactiveLimitReq : $dmaDefaultSettings['daysDefaultInactiveLimit'];

      $pwCharacterCountReq = ($_POST['pwCharacterCountReq'] > 8) ? $_POST['pwCharacterCountReq'] : 8; //Minimum is 8 by system default
      $security['iPwCharacterCountReq'] = ($pwCharacterCountReq > $dmaDefaultSettings['pwDefaultCharacterCount']) ? $pwCharacterCountReq : $dmaDefaultSettings['pwDefaultCharacterCount'];

      $pwResetDaysReq = ($_POST['pwResetDaysReq'] > 0) ? $_POST['pwResetDaysReq'] : null;
      $security['iPwResetDaysReq'] = ($pwResetDaysReq > $dmaDefaultSettings['pwDefaultResetDays']) ? $pwResetDaysReq : $dmaDefaultSettings['pwDefaultResetDays'];

      $pwReuseCountReq = ($_POST['pwReuseCountReq'] == "") ? null : $_POST['pwReuseCountReq'];
      $security['iPwReuseCountReq'] = ($pwReuseCountReq > $dmaDefaultSettings['pwDefaultReuseCount']) ? $pwReuseCountReq : $dmaDefaultSettings['pwDefaultReuseCount'];

      $pwReuseDaysReq = ($_POST['pwReuseDaysReq'] == "") ? null : $_POST['pwReuseDaysReq'];
      $security['iPwReuseDaysReq'] = ($pwReuseDaysReq > $dmaDefaultSettings['pwDefaultReuseDays']) ? $pwReuseDaysReq : $dmaDefaultSettings['pwDefaultReuseDays'];

      $isEmailChanged = strtolower(trim($this->input->post('mEmail'))) !== strtolower(trim($inviteData['iEmail']));
      $data['emailDmaMemberExists'] = $this->Members_Model->check_if_dmamember_email_exists($this->input->post('mEmail'), $this->cisession->userdata('dmaId'));
      $data['emailIsValid'] = $this->DmaAccounts->isEmailValidForDMA($this->input->post('mEmail'), $this->cisession->userdata('dmaId'));

      if(!$data['emailIsValid'] || ($data['emailDmaMemberExists'] && $isEmailChanged)) {
        if(!$data['emailIsValid']) {
          $this->session->set_flashdata('emailAllowedBlock', 1);
        } else {
          $this->session->set_flashdata('emailExistsBlock', 1);
        }
        redirect('dmamembers/invites');

        return;
      }
      $this->Members_Model->save_invite_full($inviteId, $security);

      //Get the updated invite we just saved
      $data['invite'] = $this->Members_Model->get_invite($inviteId);

      //Collect data for email
      $data['iEmail'] = $data['invite']['iEmail'];
      $data['iFirstName'] = $data['invite']['iFirstName'];
      $data['iLastName'] = $data['invite']['iLastName'];
      $data['firstName'] = $data['invite']['firstName'];
      $data['lastName'] = $data['invite']['lastName'];
      $data['iExpires'] = $data['invite']['iExpires'];
      $data['inviteId'] = $data['invite']['inviteId'];
      $data['iHash'] = $data['invite']['iHash'];
      $data['title'] = $data['invite']['title'];
      $data['dmaGTitle'] = $data['invite']['dmaGTitle'];

      //Send email
      $this->Email_Model->_send_email('invite_dmamember', 'OIX Admin Invitation - ' . $data['title'] . ' ', $data['iEmail'], $data);

      $this->logger->info("Admin User Invite Updated - By dmamember: " . $this->cisession->userdata('dmaMemberId') . " - Invite ID: " . $inviteId, ['actionType' => 'ACCESS_MOD']);

      $this->session->set_flashdata('saveSuccess', 1);
      redirect('dmamembers/invites');

    }

  }

  function delete_invite($inviteId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Get the invite
    $inviteData = $this->Members_Model->get_invite($inviteId);

    //If user has access to manage admin users AND if the member being accessed is of level 1 or 2, then it MUST be the Super Admin
    $this->memberpermissions->checkAdminUserManageAccess("", $inviteId, $inviteData['iContent1']);

    $this->Members_Model->delete_invite($inviteId);

    $this->logger->info("Admin User Invite Deleted - By dmamember: " . $this->cisession->userdata('dmaMemberId') . " - Invite ID: " . $inviteId, ['actionType' => 'ACCESS_MOD']);

    $this->session->set_flashdata('deleteSuccess', 1);
    redirect('dmamembers/invites');

  }

  function no_permission() {

    if($this->tank_auth->is_logged_in() && !$this->tank_auth->is_admin() && $this->cisession->userdata('level') != "9") {
    } else {
      redirect('/auth/login_form');
    }

    $data['lnav_key'] = "";

    $data['myDmaMembership'] = $this->DmaAccounts->get_dma_member_levels_for_member($this->cisession->userdata('dmaId'), $this->cisession->userdata('userId'));

    $this->load->view('includes/left_nav', $data);
    $this->load->view("dmamembers/no_permission", $data);
    $this->load->view('includes/footer-2', $data);

  }

}
