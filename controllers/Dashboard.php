<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\EnvironmentHelper;

class Dashboard extends CI_Controller {
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
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Taxpayersdata');
    $this->load->model('Workflow');
    $this->load->model('Email_Model');
    $this->load->model('Docs');
    $this->load->model('AuditTrail');

    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');
    $this->load->library('Globalsettings');

  }

  //THIS FUNCTION IS USED ON ALL CREDIT PAGES
  private function creditPageRequiredLoginCheck() {

    $data['lnav_key'] = "";
    $data['isAuthenticated'] = false;

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('ipVerified') != 1) || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {
      $data['isAuthenticated'] = true;
    }

    return $data;

  }

  //THIS FUNCTION IS USED ON ALL CREDIT PAGES
  private function creditPageRequiredData($id, $checkCreditAccess = "", $accessGroupOnly = "") {

    $data['itemType'] = "credit";
    $data['itemNameCap'] = "Credit";
    $data['itemNameLow'] = "credit";

    $data['currentPage'] = $this;

    $data['details'] = $this->CreditListings->get_credit_private($id);
    $data['program'] = $data['details'];

    /// TEMPORARY /////////

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $fData = array_merge($data, $sanitizedPostData);

    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($fData);

    $cData['listingId'] = $id;
    $cData['showArchived'] = 2;
    $credits = $this->CreditListings->get_credits($cData);
    $data['credit_temp'] = $credits['credits'][0];
    /*
    echo "<pre>";
    var_dump($data['credit_temp']);
    throw new \Exception('General fail');
    */

    /// TEMPORARY /////////

    //Check access to credit, get permissions, add to $data
    if($checkCreditAccess == 1) {
      //In this case, it's a buyer's purchase view, so just set proper permissions for that since there is another permission cehck
      $permissions['lnav_key'] = "credits";
      $permissions['shareView'] = false;
      $permissions['permEdit'] = false;
      $permissions['permWatch'] = true;
      $data = array_merge($data, $permissions);
    } else {
      if($accessGroupOnly != "") {
        $permissions = $this->memberpermissions->checkCreditAccess($id, "", $accessGroupOnly);
        $data = array_merge($data, $permissions);
      } else {
        $permissions = $this->memberpermissions->checkCreditAccess($id);
        $data = array_merge($data, $permissions);
      }
    }

    //Check & update recent credit array
    $recentCreditsViewed = $this->cisession->userdata('recentCreditsViewed');
    $thisRVListingId = $data['program']['listingId'];
    $thisRVJurisdiction = $data['program']['State'];
    $thisRVProjectName = $data['program']['projectName'];
    $thisRVProjectNameExt = $data['program']['projectNameExt'];
    $thisRVArray = ['listingId' => $thisRVListingId, 'jurisdictionCode' => $thisRVJurisdiction, 'projectName' => $thisRVProjectName, 'projectNameExt' => $thisRVProjectNameExt];
    if(sizeof($recentCreditsViewed) > 0) {
      if($recentCreditsViewed[0]['listingId'] == $thisRVListingId) {
        //do nothing as viewing last credit
      } else {
        $recentCreditsViewedRemoveThisCredit = [];
        foreach($recentCreditsViewed as $rcv) {
          if($rcv['listingId'] == $thisRVListingId) {
          } else {
            array_push($recentCreditsViewedRemoveThisCredit, $rcv);
          }
        }
        $recentCreditsViewed = $recentCreditsViewedRemoveThisCredit;
        if(sizeof($recentCreditsViewed) == 3 || sizeof($recentCreditsViewed) == 2) {
          $recentCreditsViewed[2] = $recentCreditsViewed[1];
          $recentCreditsViewed[1] = $recentCreditsViewed[0];
          $recentCreditsViewed[0] = $thisRVArray;
        } else {
          if(sizeof($recentCreditsViewed) == 1) {
            $recentCreditsViewed[1] = $recentCreditsViewed[0];
            $recentCreditsViewed[0] = $thisRVArray;
          } else {
            $recentCreditsViewed[0] = $thisRVArray;
          }
        }
        $newRVdata['recentCreditsViewed'] = $recentCreditsViewed;
        $this->cisession->set_userdata($newRVdata);
      }
    } else {
      $recentCreditsViewed[0] = $thisRVArray;
      $newRVdata['recentCreditsViewed'] = $recentCreditsViewed;
      $this->cisession->set_userdata($newRVdata);
    }

    //Pack add on?
    if($this->cisession->userdata('dmaType') == 'advisor') {
      $loan = $this->CreditListings->get_loans_on_listing_by_account($id, $this->cisession->userdata('primUserId'), 2);
      $data['loan'] = (count($loan) > 0) ? $loan[0] : [];
      $data['loanType'] = 2;
    } else {
      $data['allLoans'] = [];
      $data['loanType'] = "";
    }

    //Calender Alerts for this Credit
    $calData['userId'] = $this->cisession->userdata('userId');
    $calData['dmaId'] = $this->cisession->userdata('dmaId');
    $calData['accountType'] = $this->cisession->userdata('level');
    $calData['warned'] = 0;
    $calData['read'] = 0;
    $calData['type'] = "calendar";
    $calData['order'] = "mscontent_asc";
    $calData['listingId'] = $id;
    $data['calendarAlerts'] = $this->Members_Model->get_messages_of_user($calData);
    $data['calendarAlertsPastDue'] = [];
    foreach($data['calendarAlerts'] as $calr) {
      if($calr['msContent'] == 'expired') {
        array_push($data['calendarAlertsPastDue'], $calr);
      }
    }

    //Workflow
    $data['creditWorkflow'] = $this->Workflow->get_workflow('', 'credit', $id, 'workflow');
    $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit');

    $data['creditCompliance'] = $this->Workflow->get_workflow($data['program']['cComplianceId'], '', $id);
    if(sizeof($data['creditCompliance']) > 0) {
    } else {
      $data['creditCompliance']['creditComplianceAlerts'] = [];
    }

    //Audit Trail
    $data['audit_trail'] = $this->AuditTrail->get_audit_trail($id, 2);

    $data['active'] = $this->CreditListings->get_active_listing($id);
    if($data['active'] == null || $data['active'] == "") {
      $data['creditForSale'] = false;
    } else {
      $data['creditForSale'] = true;
    }

    $data['isForeign'] = ($data['program']['countryId'] > 1) ? true : false;

    //$data['openBid'] = $this->CreditListings->get_openbid_of_creditoffered($id);

    $data['bidactivity'] = $this->CreditListings->get_market_on_listing_new($id);
    $trades = $this->Trading->get_trades_on_listing($id);
    $data['trades'] = $trades['trades'];
    $data['tradesSummary'] = $trades['summary'];

    //$data['credit_docs'] = $this->Docs->get_documents("credit_doc", $id);

    $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
    $data = $data + $dmamembers_credit_access;

    return $data;

  }

  function index() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      \OIX\Util\Logger::getInstance()->debug("Dashboard requiring MFA, is logged in?: " . json_encode($this->tank_auth->is_logged_in()) . ' ipVerfied: ' . json_encode($this->cisession->userdata('ipVerified')) . ' isDMA: ' . json_encode($this->cisession->userdata('isDMA')) . ' dmaId: ' . json_encode($this->cisession->userdata('dmaId')) . ' Session ID: ' . session_id());
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if($this->cisession->userdata('primUserId') < 1) {
      redirect('/myaccounts');
    }
    if($this->cisession->userdata('level') == 8) {
      redirect('/signer');
    }

    $data['tabArray'] = "dashboard";
    $data['current_tab_key'] = "dashboard";
    $data['lnav_key'] = "dashboard";

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $data = array_merge($data, $sanitizedPostData);

    //Centralized function build filter query parameters
    $data['showArchived'] = 2;
    $cData = $this->CreditListings->buildFilterSearchData($data);

    //Over-ride a few default data points to get 15 credits ordered by last updated
    $cData['order'] = "updated_n_o";

    //$cData['limit'] = 15;
    $recentlyUpdatedCredits = $this->CreditListings->get_credits($cData);
    $data['recentlyUpdatedCredits'] = $recentlyUpdatedCredits['credits'];
    $data['recentlyUpdatedCreditsSummary'] = $recentlyUpdatedCredits['summary'];
    //For various modules
    $activeCredits = [];
    foreach($data['recentlyUpdatedCredits'] as $ruc) {
      if($ruc['cArchived']) {
        //do not include
      } else {
        array_push($activeCredits, $ruc);
      }
    }

    $data['mapArray'] = $activeCredits;
    $data['data']['recentlyUpdatedCredits'] = $activeCredits; //ignores archived credits
    $data['data']['recentlyUpdatedCreditsSummary'] = $data['recentlyUpdatedCreditsSummary']; //Includes archived credits

    //Get calendar dates
    $cData['order'] = "";
    $calendarEvents = $this->CreditListings->get_credits_calendar_dates($cData);
    $data['calendarEvents'] = $calendarEvents['allEvents'];

    usort($data['calendarEvents'], function($a, $b) {
      return $a['dateUnix'] - $b['dateUnix'];
    });

    $data['planData'] = $this->cisession->userdata('planData');

    $this->load->view('includes/left_nav', $data);
    //$this->load->view('includes/tab_nav', $data);
    $this->load->view('dashboard/overview', $data);
    $this->load->view('includes/footer-2');

  }

  function myaccounts($newMainAdmin = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //redirect signers immediately
      if($this->cisession->userdata('level') == 8) {
        redirect('/signer');
      }

      $data['tabArray'] = "myaccounts";
      $data['current_tab_key'] = "";

      $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));

      //First, check if there are any pending shares for this user
      if($thisMember['email'] != "") {
        $myPendingShares = $this->DmaAccounts->get_pending_shares_of_email($thisMember['email']);
      }

      //if more than 0, then re-direct to the accept share page
      if(sizeof($myPendingShares) > 0) {
        redirect('/dashboard/pending_share');
      }

      $data['myDmaAccounts'] = $this->DmaAccounts->get_my_dma_accounts($this->cisession->userdata('userId'));

      if(sizeof($data['myDmaAccounts']) == 0) {
        redirect('/dashboard/dma_no_accounts');
      } else {
        $lCount = 0;
        $parentKey = 0;
        foreach($data['myDmaAccounts'] as $dmaA) {
          if($dmaA['isParentDma'] == 1) {
            $parentKey = $lCount;
          }
          $lCount++;
        }
        if($parentKey > 0) {
          $parentArray = $data['myDmaAccounts'][$parentKey];
          unset($data['myDmaAccounts'][$parentKey]);
          array_unshift($data['myDmaAccounts'], $parentArray);
        }

      }

      if($newMainAdmin == 3) {
        redirect('/auth/load_DMA_account/' . $data['myDmaAccounts'][0]['dmaMDmaId'] . '/3');
      }
      if($newMainAdmin == 1) {
        redirect('/auth/load_DMA_account/' . $data['myDmaAccounts'][0]['dmaMDmaId'] . '/2');
      }

      if(sizeof($data['myDmaAccounts']) == 1) {
        $timeLapsed = 0;
        if($data['myDmaAccounts'][0]['dmaMJoinDate'] > 0) {
          $timeLapsed = time() - $data['myDmaAccounts'][0]['dmaMJoinDate'];
        }
        if($timeLapsed == 0 || ($timeLapsed > 0 && $timeLapsed < 120)) {
          if($this->cisession->userdata('planId') == 0) {
            redirect('/auth/load_DMA_account/' . $data['myDmaAccounts'][0]['dmaMDmaId'] . '/3');
          } else {
            redirect('/auth/load_DMA_account/' . $data['myDmaAccounts'][0]['dmaMDmaId'] . '/1');
          }
        } else {
          redirect('/auth/load_DMA_account/' . $data['myDmaAccounts'][0]['dmaMDmaId'] . '/0');
        }
      }

      $this->load->view('includes/header_myaccounts', $data);
      //$this->load->view('includes/tab_nav', $data);
      $this->load->view('dashboard/myaccounts', $data);
      $this->load->view('includes/footer-2');

    }
  }

  function pending_share() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //get pending shares for this user
      $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $myPendingShares = $this->DmaAccounts->get_pending_shares_of_email($thisMember['email']);

      if(sizeof($myPendingShares) == 0) {
        redirect('/myaccounts');
      }

      //Make it so market nav goes to corp site
      $data['showMarketTease'] = true;

      //just use the most recent
      $data['myPendingShare'] = $myPendingShares[0];

      $data['credit'] = $this->CreditListings->get_credit_private($data['myPendingShare']['sItemId']);

      $data['myDmaAccounts'] = $this->DmaAccounts->get_my_dma_accounts($this->cisession->userdata('userId'));

      $this->load->view('includes/header', $data);
      $this->load->view('dashboard/pending_share', $data);
      $this->load->view('includes/footer');

    }
  }

  function share_accept() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //Make it so market nav goes to corp site
      $data['showMarketTease'] = true;

      //get pending shares for this user
      $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $myPendingShares = $this->DmaAccounts->get_pending_shares_of_email($thisMember['email']);

      $loopCount = 0;
      $loopSelected = false;
      $loopSelect = 0;
      foreach($myPendingShares as $s) {
        if($s['sId'] == $_POST['sId']) {
          $loopSelect = $loopCount;
          $loopSelected = true;
        }
        $loopCount++;
      }

      //validate that there was a match
      if($loopSelected) {
        $thisShare = $myPendingShares[$loopSelect];
      } else {
        redirect('/myaccounts');
      }

      //mark it as auto accepted status
      $sUpdates['sStatus'] = 1; //Auto-accept it
      $sUpdates['sAcceptedDate'] = time();
      $sUpdates['sharedPrimId'] = $_POST['sharedPrimId'];
      $sUpdates['sharedSecId'] = $this->cisession->userdata('userId');
      $this->CreditListings->update_share($thisShare['sId'], $sUpdates);

      //Get the DMA account this share is being connected to
      $sharedDma = $this->DmaAccounts->get_dma_account_by_id($_POST['sharedPrimId']);

      //Validate that this user is a part of this DMA Account
      //TODO

      //Insert audit trail -  Share accepted
      $permissionText = ($thisShare['sharedPermEdit'] == 1) ? "Edit & View" : "View Only";
      $this->AuditTrail->insert_audit_item(160, $thisShare['sItemId'], '-', $permissionText . " (" . $sharedDma['title'] . ")", "");

      //Insert an "open" credit access permission for this shared DMA Account
      //$sharedPermEdit = $thisShare['sharedPermEdit']; //this is whatever the pending share is set to
      //$sharedPermission = ($sharedPermEdit==1) ? "open" : "watch";
      //$this->CreditListings->insert_credit_permission($thisShare['sItemId'], $sharedPermission, $sharedDma['dmaId']);

      //Update credit Permissions
      $sharedPermEdit = ($thisShare['sharedPermEdit'] == 1) ? "edit" : "view"; //this is whatever the pending share is set to
      $dmaMembers = $this->DmaAccounts->get_dma_members($sharedDma['dmaId'], 1);
      $thisDmaMemberId = 0;
      $mainDmaMemberId = 0;
      foreach($dmaMembers as $d) {
        if($d['userId'] == $this->cisession->userdata('userId')) {
          $thisDmaMemberId = $d['dmaMemberId'];
        }
        if($d['userId'] == $_POST['sharedPrimId']) {
          $mainDmaMemberId = $d['dmaMemberId'];
        }
      }
      $pListingId = $thisShare['sItemId'];
      $pDmaId = $sharedDma['dmaId'];
      $pAdminAccessConfig = 1; //This is not an open/watch permission (private)
      //Prepare THIS (shared to) Admin User perm (the main admin of this account will automatically get added)
      $pAdminUserIdsAccess[] = $thisDmaMemberId;
      $pAdminUserAccessLevels[] = $sharedPermEdit;
      if($thisDmaMemberId != $mainDmaMemberId) {
        //if this person is NOT the main admin of that DMA account, then prepare the main Admin User perm
        $pAdminUserIdsAccess[] = $mainDmaMemberId;
        $pAdminUserAccessLevels[] = $sharedPermEdit;
      }
      $this->memberpermissions->updateCreditAccessPermissionsDMA($pListingId, $pDmaId, $pAdminAccessConfig, $pAdminUserIdsAccess, $pAdminUserAccessLevels, "", 1);

      //Insert audit trail - access for entire DMA account
      $sharePermissionId = ($sharedPermEdit == 1) ? 131 : 130;
      $this->AuditTrail->insert_audit_item($sharePermissionId, $thisShare['sItemId'], '-', "All Admin Users of " . $sharedDma['title'], "");

      //If this user is DMA
      if($this->cisession->userdata('level') == 4) {

        $creditData = $this->CreditListings->get_credit_private($thisShare['sItemId']);

        //Insert message
        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
        $msType = "update";
        $msAction = "share_accepted";
        $msListingId = $creditData['listingId'];
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Credit Share accepted by " . $sharedDma['title'] . " - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
        $msTitle2 = $msTitle;
        $msTitleShort = "Credit Shared accepted by " . $sharedDma['title'];
        $msTitle2Short = $msTitleShort;
        $msTitleShared = $msTitle;
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "shared";
        $firstDmaMainUserId = $thisShare['sharerPrimId'];
        $secondDmaMainUserId = $sharedDma['mainAdmin'];
        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = false;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

      }

      redirect('/dashboard/share_accepted_success');

    }
  }

  function share_revoke() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //get pending shares for this user
      $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $myPendingShares = $this->DmaAccounts->get_pending_shares_of_email($thisMember['email']);

      //validate that there is a match
      $loopCount = 0;
      $loopSelected = false;
      $loopSelect = 0;
      foreach($myPendingShares as $s) {
        if($s['sId'] == $_POST['sId']) {
          $loopSelect = $loopCount;
          $loopSelected = true;
        }
        $loopCount++;
      }
      if($loopSelected) {
        $thisShare = $myPendingShares[$loopSelect];
      } else {
        redirect('/myaccounts');
      }

      //mark it as revoked
      $sUpdates['sStatus'] = 9; //Auto-accept it
      $sUpdates['sRevokedDate'] = time();
      $sUpdates['sharedSecId'] = $this->cisession->userdata('userId');
      $this->CreditListings->update_share($thisShare['sId'], $sUpdates);

      //Insert audit trail -  Share revoked
      $this->AuditTrail->insert_audit_item(163, $thisShare['sItemId'], '-', $thisShare['sharedInviteCompanyName'], "");

      //If this user is DMA
      if($this->cisession->userdata('level') == 4) {

        $creditData = $this->CreditListings->get_credit_private($thisShare['sItemId']);

        //Insert message
        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
        $msType = "update";
        $msAction = "share_revoked";
        $msListingId = $creditData['listingId'];
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Credit Share revoked by " . $thisShare['sharedInviteCompanyName'] . " - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
        $msTitle2 = "";
        $msTitleShort = "Credit Shared revoked by " . $thisShare['sharedInviteCompanyName'];
        $msTitle2Short = "";
        $msTitleShared = "";
        $msTitleSharedShort = "";
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $thisShare['sharerPrimId'];
        $secondDmaMainUserId = "";
        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = false;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

      }

      redirect('/dashboard/share_revoked_success');

    }
  }

  function share_accepted_success() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //Make it so market nav goes to corp site
      $data['showMarketTease'] = true;

      //get pending shares for this user
      $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $data['myPendingShares'] = $this->DmaAccounts->get_pending_shares_of_email($thisMember['email']);

      $this->load->view('includes/header', $data);
      $this->load->view('dashboard/share_accepted_success', $data);
      $this->load->view('includes/footer');

    }
  }

  function share_revoked_success() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      //get pending shares for this user
      $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $data['myPendingShares'] = $this->DmaAccounts->get_pending_shares_of_email($thisMember['email']);

      $this->load->view('includes/header', $data);
      $this->load->view('dashboard/share_revoked_success', $data);
      $this->load->view('includes/footer');

    }
  }

  function dma_no_accounts() {

    $data['tabArray'] = "myaccounts";
    $data['current_tab_key'] = "";

    $data['invites'] = $this->Members_Model->get_invites_pending_of_user();

    $this->load->view('includes/header_myaccounts', $data);
    $this->load->view('includes/tab_nav', $data);
    $this->load->view('dashboard/dma_no_accounts', $data);
    $this->load->view('includes/footer-2');

  }

  function welcome() {

    redirect('/dashboard/welcome_member');

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      $data['lnav_key'] = "";
      $data['current_tab_key'] = "";
      $data['tab_nav'] = "tab_nav_welcome";

      $this->load->view('includes/left_nav', $data);
      $this->load->view('dashboard/welcome');
      $this->load->view('includes/footer-2');

    }
  }

  function welcome_new_share() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      $data['lnav_key'] = "";
      $data['current_tab_key'] = "";
      $data['tab_nav'] = "tab_nav_welcome";

      if($this->cisession->userdata('dmaType') == "customer_broker") {
        $request['cusUserId'] = $this->cisession->userdata('userId');
        $data['serviceProviders'] = $this->Members_Model->get_my_service_providers($request);
      }

      $this->load->view('includes/left_nav', $data);
      $this->load->view('dashboard/welcome_new_share');
      $this->load->view('includes/footer-2');

    }

  }

  function welcome_member() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      $data['lnav_key'] = "";
      $data['current_tab_key'] = "";
      $data['tab_nav'] = "tab_nav_welcome";

      $data['myDmaMembership'] = $this->DmaAccounts->get_dma_member_levels_for_member($this->cisession->userdata('dmaId'), $this->cisession->userdata('userId'));

      $data['adminLevels'] = $this->DmaAccounts->get_admin_user_levels();

      if($data['myDmaMembership']['dmaMJoinDate'] == "") {
        $this->DmaAccounts->set_new_member_date($data['myDmaMembership']['dmaMemberId']);
      }

      $this->load->view('includes/left_nav', $data);
      $this->load->view('dashboard/welcome_member');
      $this->load->view('includes/footer-2');

    }
  }

  function bids() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "bids";
      $data['current_tab_key'] = "bids";
      $data['lnav_key'] = "bids";

      if($this->cisession->userdata('dmaType') == "broker") {
        $bRequest['dmaId'] = $this->cisession->userdata('dmaId');
        $bRequest['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
        $bidactivity = $this->CreditListings->get_bids($bRequest);
        $data['bidactivity'] = $bidactivity['bids'];
      } else {
        $bidactivity = $this->BidMarket->get_my_bids_and_buyorders($this->cisession->userdata('primUserId'));
        $data['bidactivity'] = $bidactivity['myBandBO'];
      }
      //$data['bidactivity'] = $this->BidMarket->get_my_buy_orders($this->cisession->userdata('primUserId'));
      $data['currentPage'] = $this;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dashboard/mybuyorders", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function bid($myBidId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "buying";
      $data['itemType'] = "credit";
      $data['itemNameCap'] = "Credit";
      $data['itemNameLow'] = "credit";
      $data['buyOrder'] = false;
      $data['newBid'] = false;
      $data['marketLive'] = true;

      $this->memberpermissions->checkBidAccess($myBidId);

      //Check if this bid has already traded
      $data['bidTraded'] = $this->Trading->checkIfBidIsTraded($myBidId);
      if(sizeof($data['bidTraded']) > 0) {
        $data['bidTraded'] = $data['bidTraded'][0];
        $bidTraded = true;
      } else {
        $bidTraded = false;
      }

      if(!$bidTraded) {
        $data['myBid'] = $this->CreditListings->get_my_bid_on_active_listing($myBidId);
        $id = $data['myBid']['listingId'];
        $data['details'] = $this->CreditListings->get_active_listing($id);
        $data['program'] = $data['details'];
        $data['program']['stateName'] = $data['details']['name'];
        $data['highestBid'] = $this->CreditListings->get_highest_bid($id);
        $data['programName'] = $this->IncentivePrograms->get_current_program($this->input->post('programs'));

        $data['transactions'] = $this->Trades->get_transactions_of_bid($myBidId);
        if(sizeof($data['transactions']) > 1) {
          $data['multibuyer'] = true;
        } else {
          $data['multibuyer'] = false;
        }
      }

      $pRequest['partnerDmaId'] = $data['transactions'][0]['tBrokerDmaId'];
      $data['brokerData'] = $this->Members_Model->get_partner($pRequest);

      $this->load->view('includes/left_nav', $data);
      if($bidTraded) {
        $this->load->view("dashboard/bid_traded", $data);
      } else {
        $this->load->view("dashboard/mybid", $data);
      }
      $this->load->view('includes/footer-2');

    }

  }

  function openbid($myListingId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "buying";
      $data['buyOrder'] = false;
      $data['newBid'] = true;
      $data['marketLive'] = true;

      $data['myBid'] = $this->CreditListings->get_current_program_binding_listing($myListingId);
      $data['program'] = $data['myBid'];
      $data['bid_jurisdiction'] = $this->IncentivePrograms->get_state_by_code($data['myBid']['State']);

      if($this->cisession->userdata('level') == 4) {
        $data['transactions'] = $this->Trades->get_transactions_of_bid($data['myBid']['bidId']);
        if(sizeof($data['transactions']) > 1) {
          $data['multibuyer'] = true;
        } else {
          $data['multibuyer'] = false;
        }
      } else {
        $data['transactions'] = [];
        $data['multibuyer'] = false;
      }

      $this->load->view('includes/left_nav', $data);
      $this->load->view("dashboard/mybid", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function buyorder($myBuyOrderId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "buying";
      $data['itemNameCap'] = "Credit";
      $data['itemNameLow'] = "credit";
      $data['buyOrder'] = true;

      $data['myBid'] = $this->BidMarket->get_buy_order_details($myBuyOrderId);
      $id = $data['myBid']['listingId'];
      $listedIds = $data['myBid']['listedIds'];

      $data['transactions'] = [];
      $data['multibuyer'] = false;

      if(str_replace([' ', ','], '', $listedIds) > 0) {
        $data['newBid'] = false;
      } else {
        $data['newBid'] = true;
      }
      if($id > 0) {
        $data['details'] = $this->CreditListings->get_active_listing($id);
        $data['program'] = $data['details'];
        $data['program']['stateName'] = $data['details']['name'];
        $data['highestBid'] = $this->CreditListings->get_highest_bid($id);
        $data['programName'] = $this->IncentivePrograms->get_current_program($this->input->post('programs'));
      }

      $data['bid_jurisdiction'] = $this->IncentivePrograms->get_state_by_code($data['myBid']['State']);

      if(($data['myBid']['deleteMarker'] == 1 && $data['myBid']['PostBIDIOIonTheOIX'] != 'Yes') || ($data['myBid']['deleteMarker'] != 1 && $data['myBid']['PostBIDIOIonTheOIX'] == 'Yes')) {
        $data['marketLive'] = true;
      } else {
        $data['marketLive'] = false;
      }

      $this->load->view('includes/left_nav', $data);
      $this->load->view("dashboard/mybid", $data);
      $this->load->view('includes/footer-2');

    }

  }


  /////////////////////////////////////////////
  //////// Start Admin Panel Copy Over ////////
  /////////////////////////////////////////////

  function credit($id = '') {

    $data = [];
    $data['current_tab_key'] = "snapshot";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $data = array_merge($data, $sanitizedPostData);
    //Add listing ID
    $data['listingId'] = $id;
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($data);
    //Get data for timeline

    $creditMapTimelineData = $this->CreditListings->get_credits($cData);
    $creditDatesInput['credits'] = $creditMapTimelineData['credits'];
    $myCreditsProcessedDates = $this->CreditListings->groom_credit_dates($creditDatesInput);
    $data['creditMapTimelineData'] = $myCreditsProcessedDates['credits'];

    //Get calendar dates
    //$cData['limit'] = 1;
    $data['calendarEvents'] = $this->CreditListings->get_credits_calendar_dates($cData);

    //Merge calendar events
    $data['calendarArray'] = [];
    $data['mapArray'] = [];

    $data['calendarArray'] = array_merge($data['calendarArray'], $data['calendarEvents']['allEvents']);
    $data['mapArray'] = array_merge($data['mapArray'], $data['calendarEvents']['futureEvents']);

    //Merge my loans activity
    //$data['calendarArray'] = array_merge($data['calendarArray'], $data['loansCalendarEvents']['allEvents']);
    //$data['mapArray'] = array_merge($data['mapArray'], $data['loansCalendarEvents']['futureEvents']);

    usort($data['calendarArray'], function($a, $b) {
      return $a['dateUnix'] - $b['dateUnix'];
    });

    //If advisor/broker, get customer information
    if($this->cisession->userdata('dmaType') == "advisor" || $this->cisession->userdata('dmaType') == "broker") {
      $cRequest['cusUserId'] = $data['program']['listedBy'];
      $cRequest['cusDmaId'] = $this->cisession->userdata('dmaId');
      $data['customerData'] = $this->Members_Model->get_customer($cRequest);

      $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
      $dpRequest['dpObjectType'] = 'loan';
      $dpRequest['listingId'] = $id;
      $customDataPoints = $this->CreditListings->get_data_points($dpRequest);
      $data['advisorCustomDataPoints'] = $customDataPoints['dataPoints'];

    }

    //Get tasks
    $taskRequest['wiStatus'] = "pending";
    $taskRequest['dmaId'] = $this->cisession->userdata('dmaId');
    $taskRequest['listingId'] = $id;
    $data['tasksArray'] = $this->Workflow->get_tasks($taskRequest);

    $data['data'] = $data;

    $this->load->view('includes/left_nav', $data);
    //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
    $this->load->view('dashboard/mycredit_snapshot', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function credit_details($id = '') {

    $data = [];
    $data['current_tab_key'] = "details";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    if($data['program']['cOrigin'] == 'purchased') {

      //Variables for document uploader
      $data['listingId'] = $id;
      $data['tradeId'] = "";
      $data['transactionId'] = "";
      $transactionId = "";
      $data['docaccess'] = "transactioncascade";
      $data['filterTag'] = "none";
      $data['tradeDeleted'] = false;

      $data['itemType'] = "credit";
      $data['itemNameCap'] = "Credit";
      $data['itemNameLow'] = "credit";

      $data['salePerspective'] = "sale";

      /*
		 		$data['active'] = $this->CreditListings->get_active_listing($id);
		 		if($data['active']==NULL || $data['active']=="") { $data['creditForSale'] = FALSE; } else { $data['creditForSale'] = TRUE; }
				*/
      $saleId = $data['trades'][0]['tradeId'];
      $data['sale'] = $this->Trading->get_trade($saleId);
      $data['sale'] = $data['sale']['trade'];
      $data['utilizationSummary'] = $data['sale']['summary'];
      $data['tradeType'] = $data['sale']['tradeType'];
      if(sizeof($data['sale']) > 0) {
        $data['tradeDeleted'] = ($data['sale']['deleteMarker'] == 1) ? true : false;
      } else {
        $data['tradeDeleted'] = true;
      }

      $data['transactions'] = $this->Trading->get_transactions_of_trade($saleId);

      if($data['transactions'][0]['taxpayerId'] > 0) {
        $data['multibuyer'] = true;
      } else {
        $data['multibuyer'] = false;
      };

      if($data['multibuyer'] == true) {
        $data['buyerSignsCount'] = 0;
        $data['buyerPaysCount'] = 0;
        $data['pendingTransactionsNumber'] = 0;
        $i = 0;
        foreach($data['transactions'] as $transaction) {
          if($transaction['buyerSigned'] != 1) {
            $data['buyerSignsCount']++;
          }
          if($transaction['buyerPaid'] != 1) {
            $data['buyerPaysCount']++;
          }
          if($transaction['tStage'] != 4 && $transaction['finalPsaUploaded'] != 1) {
            $data['pendingTransactionsNumber']++;
          }
          if($transaction['transactionId'] == $transactionId) {
            $currTransactionArrayNum = $i;
          }
          $i++;
        }
        $data['buyerActionsCount'] = $data['buyerSignsCount'] + $data['buyerPaysCount'];
      }

      if($transactionId == "") {
        $data['subPage'] = false;
        if($data['transactions'][0]['taxpayerId'] > 0 && sizeof($data['transactions']) > 1) {
          $data['transactionId'] = "";
          $data['buyerPayMethod'] = "";
        } else {
          $data['transactionId'] = $data['transactions'][0]['transactionId'];
          $data['currTransaction'] = $data['transactions'][0];
          $data['buyerPayMethod'] = $data['transactions'][0]['buyerPayMethod'];
          if($data['buyerPayMethod'] == 1) {
            $data['buyerPayMethod'] = 'check';
          }
          if($data['buyerPayMethod'] == 2) {
            $data['buyerPayMethod'] = 'wire';
          }
        }
      } else {
        $data['subPage'] = true;
        $data['transactionId'] = $transactionId;
        $data['currTransaction'] = $data['transactions'][$currTransactionArrayNum];
        $data['buyerPayMethod'] = "";
      }

      if($data['transactionId'] > 0) {
        $transactionFolderId = $this->filemanagementlib->getFolderId($id, urldecode("PSA Fully Executed " . $data['transactionId']));
        $data['transaction_docs'] = $this->Docs->get_documents('', $id, $transactionFolderId, "", "", "", 1);
      }

    }

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      if($data['program']['cOrigin'] == 'purchased') {
        $this->load->view('dashboard/mycredit_sale', $data);
      } else {
        $this->load->view('dashboard/mycredit', $data);
      }
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      if($data['program']['cOrigin'] == 'purchased') {
        $this->load->view('dashboard/mycredit_sale', $data);
      } else {
        $this->load->view('dashboard/mycredit_details', $data);
      }
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_manage($id = '') {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id, "", "creditEditorOnly");
    $data = array_merge($data, $requiredData);
    if(!$data['permEdit']) {
      redirect("/dashboard");
    }

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_manage', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_manage', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_utilize($id = '') {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id, "", "creditEditorOnly");
    $data = array_merge($data, $requiredData);

    //Get the credit holder's accounts for internal transfer
    $data['myAccounts'] = $this->DmaAccounts->get_my_dma_accounts($this->cisession->userdata('userId'));

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_utilize', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_utilize', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_editmarketsettings($id = '') {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id, "", "creditEditorOnly");
    $data = array_merge($data, $requiredData);

    $data['taxOffsets'] = $this->IncentivePrograms->get_tax_types();

    $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), "transaction", "", "", "workflow");
    $data['complianceTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), "transaction", "", "", "compliance");

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    $data['credit_psa_docs'] = $this->Docs->get_documents("credit_psa_doc", $id, "", "", "", "", 1, 1, "new_to_old");

    $data['data'] = $data;

    $this->load->view('includes/left_nav', $data);
    $this->load->view('admin/fileManagement/docs_php_to_js', $data);
    $this->load->view('dashboard/mycredit_editmarketsettings', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function credit_savemarketsettings() {

    $loginCheck = $this->creditPageRequiredLoginCheck();

    $listingId = $this->input->post('listingId');
    $offerSize = preg_replace('/\D/', '', $this->input->post('offerSize'));
    $offerPrice = preg_replace('/[^0-9.]*/', '', $this->input->post('offerPrice'));
    $finalUsableTaxYear = preg_replace('/\D/', '', $this->input->post('finalUsableTaxYear'));
    $listed_status = $this->input->post('listed_status');
    $listingWireInstructions = $this->input->post('listingWireInstructions');
    $cDefaultTransactionWorkflowId = $this->input->post('cDefaultTransactionWorkflowId');
    $cDefaultTransactionComplianceId = $this->input->post('cDefaultTransactionComplianceId');
    $tax_offset = $this->input->post('tax_offset');

    //Check access to credit
    $accessData = $this->memberpermissions->checkCreditAccess($listingId, 0, 'creditEditorOnly');

    //CHECK WORKFLOW TEMPLATE ACCESS
    if($cDefaultTransactionWorkflowId > 0 || $cDefaultTransactionComplianceId > 0) {
      $witToCheck = ($cDefaultTransactionComplianceId > 0) ? $cDefaultTransactionComplianceId : $cDefaultTransactionWorkflowId;
      $this->memberpermissions->checkWorkflowTemplateAccess($witToCheck); //redirect if no access
    }

    $creditData = $this->CreditListings->get_credit_private($listingId);

    if($listingId > 0 && $offerPrice > 0 && $offerPrice <= 1 && strlen($finalUsableTaxYear) == 4 && $finalUsableTaxYear > 2000 && $finalUsableTaxYear < 2100 && $creditData['taxYear'] > 0) {

      //Update active listing
      //offer size
      $fieldName = 'offerSize';
      $fieldValue = $offerSize;
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      //if listing this credit foing from being unlisted to listed, then update it
      if($creditData['listedStatus'] != "listed" && $listed_status == 1) {
        //original offer size
        $fieldName = 'originalOfferSize';
        $fieldValue = $offerSize;
        $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
        //listed date
        $fieldName = 'listingDate';
        $fieldValue = date('Y-m-d h:i:s', time());
        $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      }
      //offer price
      $fieldName = 'offerPrice';
      $fieldValue = $offerPrice;
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      //final usable tax year
      $fieldName = 'finalUsableTaxYear';
      $fieldValue = $finalUsableTaxYear;
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      //listing status (flag 1)
      $fieldName = 'listed';
      $fieldValue = ($listed_status == 1) ? 1 : null;
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      //listing status (flag 2)
      $fieldName = 'status';
      $fieldValue = ($listed_status == 1) ? 3 : 5;
      $this->CreditListings->update_pending_listing_field($listingId, $fieldName, $fieldValue);
      //listing payment settings
      $fieldName = 'listingWireInstructions';
      $fieldValue = ($listingWireInstructions != "") ? $listingWireInstructions : "";
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      //default workflow for trades
      $fieldName = 'cDefaultTransactionWorkflowId';
      $fieldValue = ($cDefaultTransactionWorkflowId > 0) ? $cDefaultTransactionWorkflowId : "";
      $this->CreditListings->update_pending_listing_field($listingId, $fieldName, $fieldValue);
      //default compliance for trades
      $fieldName = 'cDefaultTransactionComplianceId';
      $fieldValue = ($cDefaultTransactionComplianceId > 0) ? $cDefaultTransactionComplianceId : "";
      $this->CreditListings->update_pending_listing_field($listingId, $fieldName, $fieldValue);
      //Tax types
      $fieldName = 'taxTypeIds';
      $fieldValue = ($tax_offset != "") ? implode(',', $tax_offset) : null;
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);
      //Tag active listing with broker DMA ID
      $fieldName = 'brokerDmaId';
      $fieldValue = $this->cisession->userdata('dmaId');
      $this->CreditListings->update_active_listing_field($listingId, $fieldName, $fieldValue);

      //$this->Trading->update_trade_notes($saleId, $this->input->post('tradeNotes'));
      $this->session->set_flashdata('updateSuccess', 1);

    }

    redirect('dashboard/credit/' . $listingId . '/editmarketsettings');

  }

  function credit_shares($id = '') {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    if($data['shareView'] && !$data['advisorFlag'] && !$this->cisession->userdata('isParentDma')) {
      redirect('/dashboard');
    }

    $allowedDomains = $this->DmaAccounts->getValidDomainsForDMA($this->cisession->userdata('dmaId'));
    $domainWhitelist = [];
    foreach($allowedDomains as $allowedDomain) {
      $domainWhitelist[] = strtolower($allowedDomain['hostname']);
    }

    $data['dmaAllowedDomains'] = implode(', ', $domainWhitelist);
    $data['data'] = $data;

    //Get the credit hold
    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_shares', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_shares', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_documents($id = '') {

    $data = [];
    $data['current_tab_key'] = "documents";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    //Get documents
    $data['credit_docs'] = $this->Docs->get_documents("credit_doc", $id, "", "", "", "", 1);
    $data['signature_docs'] = $this->Docs->get_documents("signature_doc", $id, "", "", "", "", 1);
    if($this->cisession->userdata('dmaType') == "broker") {
      $data['credit_psa_docs'] = $this->Docs->get_documents("credit_psa_doc", $id, "", "", "", "", 1, 1, "new_to_old");
    }

    $data['data'] = $data;

    $data['planData'] = $this->cisession->userdata('planData');

    $this->load->view('includes/left_nav', $data);
    $this->load->view('admin/fileManagement/docs_php_to_js', $data);
    $this->load->view('dashboard/mycredit_documents', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function credit_compliance($id = '', $workflowItemId = '', $workflowItemValueId = '') {

    $data = [];
    $data['current_tab_key'] = "compliance";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    if($workflowItemId > 0 || $workflowItemValueId > 0) {
      $data['deeplinkWorkflowValueItem'] = $this->Workflow->get_workflow_item($workflowItemId, $id, $workflowItemValueId);
    } else {
      $data['deeplinkWorkflowValueItem'] = "";
    }

    $data['creditWorkflow'] = $this->Workflow->get_workflow($data['program']['cComplianceId'], '', $id);
    /*
			echo "<br>";
			foreach($data['creditWorkflow']['wLists'] as $fuck) {
				foreach($fuck['wlItems'] as $shit) {
					$status = ($shit['isValueCompliant']) ? "tru bro" : "fuckin false";
					echo $shit['wiId']." - ".$status." - ".$shit['wiValue1ExpectedAsText']." - ".$shit['wiTempName']." - ".$shit['alertDateDifferenceDaysText']." - ".$shit['wiComplianceAlert']."<br>";
				}
			}
			throw new \Exception('General fail');
			*/

    if(sizeof($data['creditWorkflow']) > 0) {

      $workflowPermission = $this->memberpermissions->checkWorkflowTemplateAccess($data['creditWorkflow']['wId'], 1);
      $data['isMyWorkflow'] = $workflowPermission['editAccess'];
      $data['workflowTemplates'] = [];
      //Last, get the workflow item templates
      $data['wliTemplates'] = $this->Workflow->get_workflow_list_item_templates();

    } else {

      $data['isMyWorkflow'] = false;
      $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit', '', '', 'compliance');
      if(in_array('workflowTemplates', $this->cisession->userdata('accessParentDataConfig'))) {
        $parentWorkflowTemplates = $this->Workflow->get_workflow_templates($this->cisession->userdata('parentDmaId'), "credit", "", 1, 'compliance');
        $data['workflowTemplates'] = array_merge($data['workflowTemplates'], $parentWorkflowTemplates);
      }
      $data['wliTemplates'] = [];

    }

    $data['data'] = $data;

    if(!$this->cisession->userdata('planCompliance')) {
      $data['tabArray'] = "compliance_noaccess";
    }

    $this->load->view('includes/left_nav', $data);
    $this->load->view('admin/fileManagement/docs_php_to_js', $data);
    $this->load->view('dashboard/mycredit_compliance', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function credit_workflow($id = '', $workflowItemId = '', $workflowItemValueId = '') {

    $data = [];
    $data['current_tab_key'] = "workflow";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    if($workflowItemId > 0 || $workflowItemValueId > 0) {
      $data['deeplinkWorkflowValueItem'] = $this->Workflow->get_workflow_item($workflowItemId, $id, $workflowItemValueId);
    } else {
      $data['deeplinkWorkflowValueItem'] = "";
    }

    //Workflow
    $data['workflow'] = $this->Workflow->get_workflow($data['program']['cWorkflowId'], '', $id);
    if(sizeof($data['workflow']) > 0) {
      $workflowPermission = $this->memberpermissions->checkWorkflowTemplateAccess($data['workflow']['wId'], 1);
      $data['isMyWorkflow'] = $workflowPermission['editAccess'];
      $data['workflowTemplates'] = [];
      //Last, get the workflow item templates
      $data['wliTemplates'] = $this->Workflow->get_workflow_list_item_templates();

    } else {
      $data['isMyWorkflow'] = false;
      $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit');
      if(in_array('workflowTemplates', $this->cisession->userdata('accessParentDataConfig'))) {
        $parentWorkflowTemplates = $this->Workflow->get_workflow_templates($this->cisession->userdata('parentDmaId'), "credit", "", 1);
        $data['workflowTemplates'] = array_merge($data['workflowTemplates'], $parentWorkflowTemplates);
      }
      $data['wliTemplates'] = [];
    }
    //Original Credit Amount Estimate
    $original_estimated_credit_amount = $this->AuditTrail->get_audit_trail($id, 4, "", 1);
    $data['estimated_credit_amount'] = 0;
    if(sizeof($original_estimated_credit_amount) > 0) {
      $estimated_credit_amount = preg_replace("/[^0-9]/", "", $original_estimated_credit_amount[0]['audValueAfter']);
      if($estimated_credit_amount > 0) {
        $data['original_estimated_credit_amount'] = $estimated_credit_amount;
      }
    } else {
      $data['original_estimated_credit_amount'] = $data['program']['amountLocal'];
    }
    //Original Project Budget Estimate
    $original_projectBudgetEst = $this->AuditTrail->get_audit_trail($id, 27, "", 1);
    $data['original_projectBudgetEst'] = 0;
    if(sizeof($original_projectBudgetEst) > 0) {
      $projectBudgetEstOrig = preg_replace("/[^0-9]/", "", $original_projectBudgetEst[0]['audValueAfter']);
      if($projectBudgetEstOrig > 0) {
        $data['original_projectBudgetEst'] = $projectBudgetEstOrig;
      }
    } else {
      $data['original_projectBudgetEst'] = $data['program']['projectBudgetEst'];
    }
    //Original Project Start Date Estimate
    $original_projectStartDate = $this->AuditTrail->get_audit_trail($id, 21, "", 1);
    $data['original_projectStartDate'] = 0;
    if(sizeof($original_projectStartDate) > 0) {
      $projectStartDateOrig = strtotime($original_projectStartDate[0]['audValueAfter']);
      if($projectStartDateOrig > 0) {
        $data['original_projectStartDate'] = $projectStartDateOrig;
      }
    } else {
      $data['original_projectStartDate'] = $data['program']['projectStartDate'];
    }
    //Original Project End Date Estimate
    $original_projectEndDate = $this->AuditTrail->get_audit_trail($id, 22, "", 1);
    $data['original_projectEndDate'] = 0;
    if(sizeof($original_projectEndDate) > 0) {
      $projectEndDateOrig = strtotime($original_projectEndDate[0]['audValueAfter']);
      if($projectEndDateOrig > 0) {
        $data['original_projectEndDate'] = $projectEndDateOrig;
      }
    } else {
      $data['original_projectEndDate'] = $data['program']['projectEndDate'];
    }

    //Values for certain template workflow drop downs
    $data['certification_status'] = $this->IncentivePrograms->get_cert_status();

    $data['data'] = $data;

    $this->load->view('includes/left_nav', $data);
    $this->load->view('admin/fileManagement/docs_php_to_js', $data);
    $this->load->view('dashboard/mycredit_workflow', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function credit_activity($id) {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    if($data['shareView'] && !$this->cisession->userdata('isParentDma')) {
      //redirect('/dashboard'); -- allow advisor/broker to see audit trail
    }

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    //Audit Trail - OVERRIDE AUDIT TRAIL IN REQUIREDDATA ABOVE
    $data['audit_trail'] = $this->AuditTrail->get_audit_trail($id, 0);

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_activity', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_activity', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_reports($id) {

    $data = [];
    $data['current_tab_key'] = "reports";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_reports', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_reports', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_activity_variance($id, $audTypeId, $subObjId = "") {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    //Audit Trail - OVERRIDE AUDIT TRAIL IN REQUIREDDATA ABOVE
    $data['audit_trail'] = $this->AuditTrail->get_audit_trail($id, $audTypeId, $subObjId);
    $data['subObjId'] = $subObjId;
    $data['audTypeId'] = $audTypeId;

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_variance_' . $audTypeId, $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_variance_' . $audTypeId, $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_calendar($id) {

    $data = [];
    $data['current_tab_key'] = "calendar";
    $data['tabArray'] = "calendar";
    $data['fieldsType'] = "mycredits";
    $data['calendarPage'] = "credit_calendar";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $data = array_merge($data, $sanitizedPostData);
    //Add listing ID
    $data['listingId'] = $id;
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($data);

    $data['calendarEvents'] = $this->CreditListings->get_credits_calendar_dates($cData);
    $data['selectedAccount'] = [];

    //Pack add on?
    if(in_array(1, $this->cisession->userdata('planDataPacks'))) {
      $allLoans = $this->CreditListings->get_loans($this->cisession->userdata('primUserId'), 1);
      $data['allLoans'] = $allLoans["loans"];
      $data['loansCalendarEvents'] = $allLoans['calendarEvents'];
      $data['loanType'] = 1;
    } else {
      if(in_array(2, $this->cisession->userdata('planDataPacks'))) {
        $allLoans = $this->CreditListings->get_loans($this->cisession->userdata('primUserId'), 2);
        $data['allLoans'] = $allLoans["loans"];
        $data['loansCalendarEvents'] = $allLoans['calendarEvents'];
        $data['loanType'] = 2;
      } else {
        $data['allLoans'] = [];
        $data['loansCalendarEvents']['allEvents'] = [];
        $data['loansCalendarEvents']['futureEvents'] = [];
        $data['loanType'] = "";
      }
    }

    //Merge calendar events
    $data['calendarArray'] = [];
    $data['mapArray'] = [];

    $data['calendarArray'] = array_merge($data['calendarArray'], $data['calendarEvents']['allEvents']);
    $data['mapArray'] = array_merge($data['mapArray'], $data['calendarEvents']['futureEvents']);

    //Merge my loans activity
    $data['calendarArray'] = array_merge($data['calendarArray'], $data['loansCalendarEvents']['allEvents']);
    $data['mapArray'] = array_merge($data['mapArray'], $data['loansCalendarEvents']['futureEvents']);

    usort($data['calendarArray'], function($a, $b) {
      return $a['dateUnix'] - $b['dateUnix'];
    });

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('calendar/calendar', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('calendar/calendar', $data);
      //$this->load->view('dashboard/mycredit_calendar', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_tasks($id) {

    $data = [];
    $data['current_tab_key'] = "tasks";
    $data['calendarPage'] = "credit_tasks";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('calendar/calendar', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('tasks/tasks', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_sales($id = '') {
    redirect('/dashboard/credit/' . $id . '/utilization');
  }

  function credit_utilization($id = '') {

    $data = [];
    $data['current_tab_key'] = "utilization";
    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }
    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);
    //OVER RIDE this
    $trades = $this->Trading->get_trades_on_listing($id, '', 1);
    $data['trades'] = $trades['trades'];
    $data['tradesSummary'] = $trades['summary'];
    $data['data'] = $data;
    $this->load->view('includes/left_nav', $data);
    $this->load->view('dashboard/mycredit_sales', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function credit_sale($id = '', $saleId = '', $salePerspective = '', $transactionId = '', $workflowTab = '', $workflowItemId = '', $workflowItemValueId = '') {

    $data = [];
    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }
    $requiredData = $this->creditPageRequiredData($id, 1);
    $data = array_merge($data, $requiredData);
    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "transactioncascade";
    $data['filterTag'] = "none";
    $data['tradeDeleted'] = false;
    if($salePerspective == "utilization") {
      $data['salePerspective'] = "sale";
      $data['isUtilization'] = true;
    } else {
      $data['salePerspective'] = $salePerspective;
      $data['isUtilization'] = true;
    }
    $permissions = [];
    if($data['salePerspective'] == "sale") {
      $data['current_tab_key'] = "sales";
      $data['isSellerSignerAdminUser'] = false;
      //First check if this user has access to this credit (there is a second check further below)
      ////// note - there is another redirect further down!!!!!!!!!!!!!!!!!!
      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($id, 1);
      $data = array_merge($data, $permissions);
      $access = $data['access'];
      $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
      $data = $data + $dmamembers_credit_access;
    } else {
      $data['lnav_key'] = "purchased";
    }
    $data['sale'] = $this->Trading->get_trade($saleId);
    $data['sale'] = $data['sale']['trade'];
    $data['tradeType'] = $data['sale']['tradeType'];
    $data['tradeId'] = $data['sale']['tradeId'];
    if(sizeof($data['sale']) > 0) {
      $data['tradeDeleted'] = ($data['sale']['deleteMarker'] == 1) ? true : false;
    } else {
      $data['tradeDeleted'] = true;
    }
    $data['transactions'] = $this->Trading->get_transactions_of_trade($saleId);
    if(sizeof($data['transactions']) > 1) {
      $data['multibuyer'] = true;
    } else {
      $data['multibuyer'] = false;
    };
    if($data['multibuyer'] == true) {
      $data['buyerSignsCount'] = 0;
      $data['buyerPaysCount'] = 0;
      $data['pendingTransactionsNumber'] = 0;
      $i = 0;
      foreach($data['transactions'] as $transaction) {
        if($transaction['buyerSigned'] != 1) {
          $data['buyerSignsCount']++;
        }
        if($transaction['buyerPaid'] != 1) {
          $data['buyerPaysCount']++;
        }
        if($transaction['tStage'] != 4 && $transaction['finalPsaUploaded'] != 1) {
          $data['pendingTransactionsNumber']++;
        }
        if($transaction['transactionId'] == $transactionId) {
          $currTransactionArrayNum = $i;
        }
        $i++;
      }
      $data['buyerActionsCount'] = $data['buyerSignsCount'] + $data['buyerPaysCount'];
    }
    if($transactionId == "" || $transactionId == 0) {
      $data['subPage'] = false;
      $data['transactionId'] = $data['transactions'][0]['transactionId'];
      $data['currTransaction'] = $data['transactions'][0];
    } else {
      $data['subPage'] = true;
      $data['transactionId'] = $transactionId;
      $data['currTransaction'] = $data['transactions'][$currTransactionArrayNum];
    }
    if($data['transactionId'] > 0) {
      $transactionFolderId = $this->filemanagementlib->getFolderId($id, urldecode("PSA Fully Executed " . $data['transactionId']));
      $data['transaction_docs'] = $this->Docs->get_documents('', $id, $transactionFolderId, "", "", "", 1);
      $workflowId = 0;
      if($data['currTransaction']['transWorkflowId'] > 0) {
        $data['workflow'] = $this->Workflow->get_workflow($data['currTransaction']['transWorkflowId'], 'transaction', '', '', $data['transactionId']);
      } else {
        $data['workflow'] = [];
      }
      if($data['currTransaction']['transComplianceId'] > 0) {
        $data['compliance'] = $this->Workflow->get_workflow($data['currTransaction']['transComplianceId'], 'transaction', '', '', $data['transactionId']);
      } else {
        $data['compliance'] = [];
      }
      if($workflowTab != "") {
        $data['workflowTab'] = $workflowTab;
        $workflowId = ($workflowTab == "compliance") ? $data['currTransaction']['transComplianceId'] : $data['currTransaction']['transWorkflowId'];
        $data['workflow'] = ($workflowTab == "compliance") ? $data['compliance'] : $data['workflow'];
      } else {
        if(sizeof($data['workflow']) > 0 && sizeof($data['compliance']) > 0) {
          $data['workflowTab'] = "workflow";
          $workflowId = $data['currTransaction']['transWorkflowId'];
          $data['workflow'] = $data['workflow'];
        } else {
          if(sizeof($data['workflow']) > 0 && sizeof($data['compliance']) == 0) {
            $data['workflowTab'] = "workflow";
            $workflowId = $data['currTransaction']['transWorkflowId'];
            $data['workflow'] = $data['workflow'];
          } else {
            if(sizeof($data['workflow']) == 0 && sizeof($data['compliance']) > 0) {
              $data['workflowTab'] = "compliance";
              $workflowId = $data['currTransaction']['transComplianceId'];
              $data['workflow'] = $data['compliance'];
            } else {
              $data['workflowTab'] = "workflow";
              $workflowId = 0;
              $data['workflow'] = [];
            }
          }
        }
      }
      if(sizeof($data['workflow']) > 0) {
        if($workflowItemId > 0 || $workflowItemValueId > 0) {
          $data['deeplinkWorkflowValueItem'] = $this->Workflow->get_workflow_item($workflowItemId, $id, $workflowItemValueId);
        } else {
          $data['deeplinkWorkflowValueItem'] = "";
        }
        //Workflow
        if(sizeof($data['workflow']) > 0) {
          $workflowPermission = $this->memberpermissions->checkWorkflowTemplateAccess($data['workflow']['wId'], 1);
          $data['isMyWorkflow'] = $workflowPermission['editAccess'];
          $data['workflowTemplates'] = [];
          //Last, get the workflow item templates
          $data['wliTemplates'] = $this->Workflow->get_workflow_list_item_templates();
        }
      } else {
        $data['isMyWorkflow'] = false;
        $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'transaction', '', '', $workflowTab);
        if(in_array('workflowTemplates', $this->cisession->userdata('accessParentDataConfig'))) {
          $parentWorkflowTemplates = $this->Workflow->get_workflow_templates($this->cisession->userdata('parentDmaId'), "transaction", "", 1, $workflowTab);
          $data['workflowTemplates'] = array_merge($data['workflowTemplates'], $parentWorkflowTemplates);
        }
        $data['wliTemplates'] = [];
      }
    }
    //A different check if this user has access to this sale/purchase
    if($data['salePerspective'] == "sale") {
      if(isset($data['sale']['taxpayerUserId'])) {
        if($data['sale']['taxpayerUserId'] == $this->cisession->userdata('primUserId')) {
          //If the legal entity for this credit
          $access = true;
          $data['lnav_key'] = "signerActivity";
        }
      }
      //If STILL no access, then finally check if this person has access to the trade
      if(!$access) {
        $tradeCheck = $this->memberpermissions->checkTradeAccess($saleId, 1);
        $access = $tradeCheck['access'];
        if($access) {
          $data['isSellerSignerAdminUser'] = true;
        }
      }
    } else {
      if($data['sale']['accountId'] == $this->cisession->userdata('primUserId')) {
        //If the main buyer in this trade
        $access = true;
      } else {
        if($data['currTransaction']['taxpayerUserId'] == $this->cisession->userdata('primUserId')) {
          //If the legal entity for this transaction
          $access = true;
        }
      }
    }
    if(!$access) {
      redirect('/dashboard');
    }

    $dpRequest['listingId'] = $saleId;
    $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
    $dpRequest['dpObjectType'] = 'utilization';
    $customDataPointsRaw = $this->CreditListings->get_data_points($dpRequest);
    $data['sale']['customDataPoints'] = $customDataPointsRaw['dataPoints'];

    //If is a broker situation
    $data['isRecordTradeBroker'] = ($data['program']['brokerDmaId'] > 0) ? true : false;
    $data['actionWordToUse'] = ($data['program']['brokerDmaId'] > 0) ? "trade" : "utilization";
    $data['data'] = $data;
    if($this->cisession->userdata('level') != 8) {
      $this->load->view('includes/left_nav', $data);
    } else {
      $this->load->view('includes/left_nav_signer', $data);
    }
    $this->load->view('admin/fileManagement/docs_php_to_js', $data);
    $this->load->view('dashboard/mycredit_sale', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function save_trade_notes($saleId) {

    $loginCheck = $this->creditPageRequiredLoginCheck();

    //If loading page from Admin Panel...
    if(!$this->cisession->userdata('isAdminPanel')) {
      //Check access to credit
      $this->memberpermissions->checkCreditAccess($sale['listingId'], 1);
    }

    $sale = $this->Trading->get_trade($saleId);
    $sale = $sale['trade'];

    $this->Trading->update_trade_notes($saleId, $this->input->post('tradeNotes'));

    $redirectValue = ($sale['tradeType'] == 'external_purchase') ? "external_purchase" : "sale";
    redirect('/dashboard/credit/' . $sale['listingId'] . '/' . $redirectValue . '/' . $saleId);

  }

  function credit_sale_sign($id = '', $saleId = '', $perspective = '', $transactionId = '') {

    $data = [];

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id);
    $data = array_merge($data, $requiredData);

    if(!($this->cisession->userdata('levelSellCredit') || $this->cisession->userdata('levelBuyCredit'))) {
      redirect('/dmamembers/no_permission');
    } else {

      //First check if this user has access to this credit (there is a second check further below)
      if($perspective == "seller") {
        $data['seller'] = true;
        $data['current_tab_key'] = "sales";

        //Check access to credit, get permissions, add to $data
        $permissions = $this->memberpermissions->checkCreditAccess($id, 1, "", "", 1);
        $data = array_merge($data, $permissions);
        $access = $data['access'];

      } else {
        $data['seller'] = false;
        $data['lnav_key'] = "buying";
      }

      //Get document to download to sign
      $folderName = "PSA " . $perspective . " To Sign";
      $folderName = str_replace('%20', ' ', $folderName);
      if($transactionId != "") {
        $folderName = $folderName . ' ' . $transactionId;
      } else {
        $folderName = $folderName . ' ' . $saleId;
      }
      $folderId = $this->filemanagementlib->getFolderId($id, $folderName, 1);
      $data['documentToDownload'] = $this->filemanagementlib->getFolderItemMostRecent($folderId, 1, "none", $id, $saleId, $transactionId);

      $data['active'] = $this->CreditListings->get_active_listing($id);
      if($data['active'] == null || $data['active'] == "") {
        $data['creditForSale'] = false;
      } else {
        $data['creditForSale'] = true;
      }

      $data['sale'] = $this->Trading->get_trade($saleId);
      $data['sale'] = $data['sale']['trade'];

      if($transactionId != "") {
        $data['transaction'] = $this->Trades->get_transaction_by_id($transactionId);
        if($data['transaction']['buyerSigned'] == 1 && $perspective != "seller") {
          redirect('/dashboard');
        }
        $data['docToSign'] = $this->Trades->get_docToSign_by_transactionId($transactionId);

      } else {
        $data['transaction'] = "";
        $data['docToSign'] = $this->Trades->get_docToSign_by_tradeId($saleId);
      }
      $data['docuSignData'] = $this->filemanagementlib->get_docuSign_doc($data['docToSign']);

      $data['transactions'] = $this->Trading->get_transactions_of_trade($saleId);

      //If this user doesn't have access to this sale/purchase
      if($perspective == "seller") {
        if($data['sale']['taxpayerUserId'] == $this->cisession->userdata('primUserId')) {
          //If the legal entity for this credit
          $access = true; // don't think this line is needed as it is already covered in the trade access check below
          $data['lnav_key'] = "credits";
          $data['shareView'] = false;
        }
        //If STILL no access, then finally check if this person has access to the trade
        if(!$access) {
          $tradeCheck = $this->memberpermissions->checkTradeAccess($saleId, 1);
          $access = $tradeCheck['access'];
          if($access) {
            $data['isSellerSignerAdminUser'] = true;
          }
        }
      } else {
        if($data['sale']['accountId'] == $this->cisession->userdata('primUserId')) {
          //If the main buyer in this trade
          $access = true;
        } else {
          if($data['transaction']['taxpayerUserId'] == $this->cisession->userdata('primUserId')) {
            //If the legal entity for this transaction
            $access = true;
          }
        }
      }

      if(!$access) {
        redirect('/dashboard');
      }

      //If loading page from Admin Panel...
      if($this->cisession->userdata('isAdminPanel') == true) {

        $this->load->view('layouts/admin/header', $data);
        $this->load->view('includes/credit_header', $data);
        $this->load->view('dashboard/mycredit_sale', $data);
        $this->load->view('admin/admin_nav', $data);
        $this->load->view('layouts/admin/footer', $data);

      } else {

        if($this->cisession->userdata('level') != 8) {
          $this->load->view('includes/left_nav', $data);
        } else {
          $this->load->view('includes/left_nav_signer', $data);
        }
        //$this->load->view('includes/credit_header', $data);
        $this->load->view('dashboard/mycredit_sale_sign', $data);
        $this->load->view('includes/footer-2', $data);

      }

    }
  }

  function signandpay_submit() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //Get trade information - to check if seller has signed
      $sale = $this->Trading->get_trade($this->input->post("saleId"));
      $sale = $sale['trade'];
      $transaction = $this->Trades->get_transaction_by_id($this->input->post("transactionId"));
      $listing = $this->CreditListings->get_active_listing($sale['listingId']);
      $mainSeller = $this->Members_Model->get_member_by_id($listing['listedBy']);
      $mainBuyer = $this->Members_Model->get_member_by_id($sale['BaccountId']);

      if($this->input->post("perspective") == "seller") {
        $access = false;
        //Check access to credit, get permissions, add to $data
        $creditCheck = $this->memberpermissions->checkCreditAccess($sale['listingId'], 1, "creditEditorOnly", "", 1);
        $access = $creditCheck['access'];

        if(!$access) {
          $tradeCheck = $this->memberpermissions->checkTradeAccess($this->input->post("saleId"));
          $access = $tradeCheck['access'];
        }
        if(!$access) {
          redirect('/');
        }
      }

      //Get all the transactions of this sale
      $transactions = $this->Trading->get_transactions_of_trade($this->input->post("saleId"));
      $transactionsNumber = sizeof($transactions);
      $buyerStage1Count = 0;
      foreach($transactions as $trans) {
        if($trans['buyerSigned'] != 1) {
          $buyerStage1Count++;
        }
      }

      //Prepare data for "signature received" email
      $emailData['listingId'] = $sale['listingId'];
      $emailData['tradeId'] = $sale['tradeId'];
      $emailData['transactionId'] = "";
      $emailData['taxYear'] = $sale['taxYear'];
      $emailData['State'] = $sale['State'];
      $emailData['stateName'] = $sale['name'];
      $emailData['program'] = $listing['ProgramName'];
      $emailData['project'] = $listing['stateCertNum'];
      $emailData['projectNameExt'] = $listing['projectNameExt'];
      $emailData['saleIdFull'] = $emailData['State'] . $sale['listingId'] . '-' . $sale['tradeId'];
      $emailData['signerPerspective'] = $this->input->post("perspective");
      $emailData['perspective'] = $this->input->post("perspective");
      $emailData['signerName'] = $this->cisession->userdata('firstName') . ' ' . $this->cisession->userdata('lastName');
      $emailData['sellerCompanyName'] = $mainSeller['companyName'];
      $emailData['buyerCompanyName'] = $mainBuyer['companyName'];
      $emailData['sellerName'] = $mainSeller['firstName'] . ' ' . $mainSeller['lastName'];
      $emailData['buyerName'] = $mainBuyer['firstName'] . ' ' . $mainBuyer['lastName'];
      $emailData['updateType'] = 'newSignature';
      $emailData['updateTypeName'] = 'Signature Received';
      $emailData['size'] = "";
      $emailData['price'] = $sale['price'];
      $emailData['tradeIdFull'] = $sale['State'] . $sale['listingId'] . '-' . $sale['tradeId'];

      // IF SELLER SIGNED
      if($this->input->post("perspective") == "seller") {
        //Set the size to the total trade size
        $emailData['size'] = $sale['size'];
        //if seller - update seller action on trade record
        $this->Trades->update_trade_seller_sign($this->input->post("saleId"));
        //check each transaction to see if buyer has signed AND paid, if so, then move that transaction to state 2
        foreach($transactions as $tCheck) {
          if($tCheck['buyerSigned'] == 1 && $tCheck['buyerPaid'] == 1) {
            $this->Trades->update_transaction_stage($tCheck['transactionId'], 2, $this->input->post("saleId"));
          }
        }

        //send singature received email to seller
        $emailData['headline'] = "Signature Received";
        $emailData['signatureSource'] = 'self';
        if($listing['tpEmailSigner'] != "") {
          //Get the user who has been tagged as the signer
          $emailData['email'] = $listing['tpEmailSigner'];
          $emailData['firstName'] = $listing['tpFirstName'];
          $emailData['lastName'] = $listing['tpLastName'];
          $emailData['companyName'] = $listing['tpCompanyName'];
          $emailData['entityName'] = ($emailData['companyName'] != "") ? $emailData['companyName'] : $emailData['firstName'] . " " . $emailData['lastName'];
          $emailData['stateCertNum'] = $listing['stateCertNum'];
          $emailData['projectNameExt'] = ($listing['projectNameExt'] != "") ? " - " . $listing['projectNameExt'] : "";
          $this->Email_Model->_send_email('closing_updates', 'Signature Received - Sale of Tax Credit: "' . $emailData['stateCertNum'] . $emailData['projectNameExt'] . '"', $emailData['email'], $emailData);
          $emailData['signatureSource'] = 'tpEmailSigner'; //Reset this variable so Main Seller will get proper message
          $emailData['signerFirstName'] = $listing['tpFirstName'];
          $emailData['signerLastName'] = $listing['tpLastName'];
        } else {
          if($listing['tpUserIdSigner'] != "") {
            //Get the user who has been tagged as the signer
            $signerAU = $this->Members_Model->get_member_by_id($listing['tpUserIdSigner']);
            $emailData['email'] = $signerAU['email'];
            $emailData['firstName'] = $signerAU['firstName'];
            $emailData['lastName'] = $signerAU['lastName'];
            $emailData['companyName'] = $mainBuyer['companyName'];
            $emailData['entityName'] = ($emailData['companyName'] != "") ? $emailData['companyName'] : $emailData['firstName'] . " " . $emailData['lastName'];
            $emailData['stateCertNum'] = $listing['stateCertNum'];
            $emailData['projectNameExt'] = ($listing['projectNameExt'] != "") ? " - " . $listing['projectNameExt'] : "";
            $this->Email_Model->_send_email('closing_updates', 'Signature Received - Sale of Tax Credit: "' . $emailData['stateCertNum'] . $emailData['projectNameExt'] . '"', $emailData['email'], $emailData);
            $emailData['signatureSource'] = 'tpUserIdSigner';//Reset this variable so Main Seller will get proper message
            $emailData['signerFirstName'] = $signerAU['firstName'];
            $emailData['signerLastName'] = $signerAU['lastName'];
          }
        }
        $emailData['email'] = $mainSeller['email'];
        $emailData['firstName'] = $mainSeller['firstName'];
        $emailData['lastName'] = $mainSeller['lastName'];
        $emailData['companyName'] = $mainSeller['companyName'];
        $emailData['entityName'] = ($emailData['companyName'] != "") ? $emailData['companyName'] : $emailData['firstName'] . " " . $emailData['lastName'];
        $emailData['stateCertNum'] = $listing['stateCertNum'];
        $emailData['projectNameExt'] = ($listing['projectNameExt'] != "") ? " - " . $listing['projectNameExt'] : "";
        $this->Email_Model->_send_email('closing_updates', 'Signature Received - Sale of Tax Credit: "' . $emailData['stateCertNum'] . $emailData['projectNameExt'] . '"', $emailData['email'], $emailData);

        if($buyerStage1Count == 0) {
          $this->session->set_flashdata('messageSuccess', 2);
        } else {
          $this->session->set_flashdata('messageSuccess', 1);
        }
        $urlToUse = "sale";
        $urlToUse2 = "";

        // IF BUYER SIGNED
      } else {
        if($this->input->post("perspective") == "buyer") {
          //Add transaction ID and transaction size to email
          $emailData['transactionId'] = $this->input->post("transactionId");
          $emailData['size'] = $transaction['tCreditAmount'];
          //if buyer - update buyer action on trade record and buyer payment selction on record
          $this->Trades->update_transaction_buyer_sign($this->input->post("transactionId"), $this->input->post("saleId"));
          //- check if seller has fullfilled stage 1 and if buyer has already paid - if yes, then mark stage 1 complete
          if($sale['sellerSigned'] == 1 && $transaction['buyerPaid'] == 1) {
            $this->Trades->update_transaction_stage($this->input->post("transactionId"), 2, $this->input->post("saleId"));
          }

          //send signer email (with payment instructions)
          if($transaction['tpEmailSigner'] != "") {
            $emailData['email'] = $transaction['tpEmailSigner'];
            $emailData['firstName'] = $transaction['tpFirstName'];
            $emailData['lastName'] = $transaction['tpLastName'];
            $emailData['companyName'] = $transaction['tpCompanyName'];
            $emailData['entityName'] = ($emailData['companyName'] != "") ? $emailData['companyName'] : $emailData['firstName'] . " " . $emailData['lastName'];
          } else {
            if($transaction['tpUserIdSigner'] != "") {
              //Get the user who has been tagged as the signer
              $signerAU = $this->Members_Model->get_member_by_id($transaction['tpUserIdSigner']);
              $emailData['email'] = $signerAU['email'];
              $emailData['firstName'] = $signerAU['firstName'];
              $emailData['lastName'] = $signerAU['lastName'];
              $emailData['companyName'] = $mainBuyer['companyName'];
              $emailData['entityName'] = ($emailData['companyName'] != "") ? $emailData['companyName'] : $emailData['firstName'] . " " . $emailData['lastName'];
            } else {
              $emailData['email'] = $mainBuyer['email'];
              $emailData['firstName'] = $mainBuyer['firstName'];
              $emailData['lastName'] = $mainBuyer['lastName'];
              $emailData['companyName'] = $mainBuyer['companyName'];
              $emailData['entityName'] = ($emailData['companyName'] != "") ? $emailData['companyName'] : $emailData['firstName'] . " " . $emailData['lastName'];
            }
          }
          $emailData['headline'] = "Signature Received - Payment Due";
          $this->Email_Model->_send_email('closing_updates', 'Signature Received - Purchase of ' . $emailData['taxYear'] . ' ' . $emailData['stateName'] . ' Tax Credits', $emailData['email'], $emailData);

          //Open the payment method overlay (3)
          $this->session->set_flashdata('messageSuccess', 3);
          //Get first part of redirect URL
          if(sizeof($transactions) > 1 || $this->cisession->userdata('level') == 8) {
            $urlToUse = "multipurchase";
            $urlToUse2 = "/transaction/" . $this->input->post("transactionId");
          } else {
            $urlToUse = "purchase";
            $urlToUse2 = "";
          }
        }
      }

      //send OIX ADMIN email
      $emailData['members'] = $this->BidMarket->get_buyer_seller_in_bid($sale['bidId']);
      $this->Email_Model->_send_email('oix_admin_update', ucfirst($emailData['signerPerspective']) . ' Signature Received - Sale ' . $emailData['saleIdFull'], $this->config->item("oix_admin_emails_array"), $emailData);

      //redirect back to the sale page (now showing the updated status and overlay telling user information)
      redirect('/dashboard/credit/' . $this->input->post("listingId") . '/' . $urlToUse . '/' . $this->input->post("saleId") . $urlToUse2);

    }

  }

  function submit_payment_choice() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if($this->input->post("paymentMethod") == "check") {
      $buyerPayMethod = 1;
    } else {
      if($this->input->post("paymentMethod") == "wire") {
        $buyerPayMethod = 2;
      } else {
        $buyerPayMethod = null;
      }
    }
    $this->Trades->update_transaction_buyer_pay_choice($this->input->post("transactionId"), $buyerPayMethod, $this->input->post("tradeId"));

    $sale = $this->Trading->get_trade($this->input->post("tradeId"));
    $sale = $sale['trade'];

    //Get all the transactions of this sale
    $transactions = $this->Trading->get_transactions_of_trade($this->input->post("tradeId"));

    //Get redirect URL variables
    if(sizeof($transactions) > 1 || $this->cisession->userdata('level') == 8) {
      $urlToUse = "multipurchase";
      $urlToUse2 = "/transaction/" . $this->input->post("transactionId");
    } else {
      $urlToUse = "purchase";
      $urlToUse2 = "";
    }

    if($sale['sellerSigned'] == 1) {
      $this->session->set_flashdata('messageSuccess', 4);
    } else {
      $this->session->set_flashdata('messageSuccess', 5);
    }

    //redirect back to the sale page (now showing the updated status and overlay telling user information)
    redirect('/dashboard/credit/' . $this->input->post("listingId") . '/' . $urlToUse . '/' . $this->input->post("tradeId") . $urlToUse2);

  }

  function utilization_new_estimates($id, $tradeId = "", $convertToActual = "") {

    $data = [];
    $data['current_tab_key'] = "";

    $loginCheck = $this->creditPageRequiredLoginCheck();
    if($loginCheck['isAuthenticated']) {
      $data = array_merge($data, $loginCheck);
    } else {
      return;
    }

    $requiredData = $this->creditPageRequiredData($id, "", "creditEditorOnly");
    $data = array_merge($data, $requiredData);
    if(!$data['permEdit']) {
      redirect("/dashboard");
    }

    $data['utilizationTypes'] = [0 => "Select a Type"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('credit_utilization_type');

    $data['data'] = $data;

    //Conversion for foreign
    $data['isForeign'] = ($data['credit_temp']['countryId'] > 1) ? true : false;

    if($tradeId > 0) {
      $data['tradeId'] = $tradeId;
      $trade = $this->Trading->get_trade($tradeId);
      $data['trade'] = $trade['trade'];
      if($data['trade']['listingId'] != $id) {
        redirect('dashboard');
      }
      $data['convertToActual'] = $convertToActual;
      //Over ride a few variables if you are editing a utilization - as you need to remove/add this utilization from blocking variables
      $tradeSizeLocalActual = ($data['trade']['tradeIsEstimated'] == 1) ? 0 : $data['trade']['tradeSizeLocal'];
      $tradeSizeLocalEstimated = ($data['trade']['tradeIsEstimated'] == 1) ? $data['trade']['tradeSizeLocal'] : 0;
      $data['totalOfEstimates'] = $data['credit_temp']['utilize_estimate_local_value'] - $tradeSizeLocalEstimated;
      $data['totalOfActuals'] = $data['credit_temp']['utilize_actual_value_local'] - $tradeSizeLocalActual;
      $data['totalOfEstimatesAndActuals'] = $data['credit_temp']['utilize_local_value_total'] - $data['trade']['tradeSizeLocal'];
      $dpRequest['listingId'] = $tradeId;
    } else {
      $data['tradeId'] = 0;
      $data['totalOfEstimates'] = $data['credit_temp']['utilize_estimate_local_value'];
      $data['totalOfActuals'] = $data['credit_temp']['utilize_actual_value_local'];
      $data['totalOfEstimatesAndActuals'] = $data['credit_temp']['utilize_local_value_total'];
    }
    $data['amountNotEstimated'] = $data['credit_temp']['amountLocal'] - $data['totalOfEstimatesAndActuals'];
    $data['amountLocalRemaining'] = $data['credit_temp']['amountLocalRemaining'];
    //echo $data['totalOfEstimates'];
    //throw new \Exception('General fail');

    $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
    $dpRequest['dpObjectType'] = 'utilization';
    $customDataPointsRaw = $this->CreditListings->get_data_points($dpRequest);
    $data['customDataPoints'] = $customDataPointsRaw['dataPoints'];

    $this->load->view('includes/left_nav', $data);
    $this->load->view('dashboard/mycredit_utilize_add_edit', $data);
    $this->load->view('includes/footer-2', $data);

  }

  //Currently used for handling multiple utilization estimates within the web interface only
  function process_estimated_utilization($tradeId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      throw new \Exception('General fail');
    }

    //Convert POST into array
    $utilizations = $_POST;
    $thisListingId = $utilizations["listingId"][0];

    $data = [];

    //Check access to credit, get permissions, add to $data
    $permissions = $this->memberpermissions->checkCreditAccess($thisListingId, 0, 'creditEditorOnly', 1);
    $data = array_merge($data, $permissions);
    if(!$permissions['permEdit']) {
      redirect("/dashboard");
    }

    //Get credit based on first harvest listing ID
    $creditData = $this->CreditListings->get_credit_private($thisListingId);

    for($i = 0; $i < sizeof($utilizations["hType"]); $i++) {
      $cleanUtilizationNum = preg_replace('/[^0-9.]+/', '', $utilizations["hAmount"][$i]);
      if($utilizations["hLocalAmount"][$i] > 0) {
        $cleanUtilizationNum = preg_replace('/[^0-9.]+/', '', $utilizations["hLocalAmount"][$i]);
      }
      $formattedDate = date('Y-m-d h:i:s', strtotime($utilizations["hPayDate"][$i]));
      $finalExchangeRate = ($utilizations["hSpotExchangeRate"][$i] > 0) ? $utilizations["hSpotExchangeRate"][$i] : null; //what will be saved to database

      //Process estimated credit amount
      if($utilizations["estimateMetric"][$i] == 1) {
        //Custom percentage
        $finalEstCreditAmount = 0;
        $finalEstCreditPercent = $utilizations["hPercent"][$i];
        $finalPercentCompareTo = $utilizations["hPercentCompareTo"][$i];
      } else {
        if($utilizations["estimateMetric"][$i] == 2) {
          //Fixed Currency Amount
          $finalEstCreditAmount = $cleanUtilizationNum;
          $finalEstCreditPercent = 0;
          $finalPercentCompareTo = null;
        } else {
          if($utilizations["estimateMetric"][$i] > 5) {
            //custom percentage
            $finalEstCreditAmount = 0;
            $finalEstCreditPercent = $utilizations["estimateMetric"][$i] / 100; //divide by 100 because it comes in whole numbers
            $finalPercentCompareTo = $utilizations["hPercentCompareTo"][$i];
          } else {
            redirect('/dashboard');
          }
        }
      }

      //Validate utilizing entity
      if($utilizations["utilizingEntitySwitch"][$i] == "self") {
        //do nothing
      } else {
        if($utilizations["utilizingEntitySwitch"][$i] == "customers") { //If this is a customer name
          if($utilizations["entitySelected"][$i] != "") {
          } else {
            redirect('/dashboard');
          }
        } else {
          if($utilizations["utilizingEntitySwitch"][$i] == "customname") { //If this is a custom company name
            if($utilizations["entitySelected"][$i] != "") {
            } else {
              redirect('/dashboard');
            }
          } else {
            if($utilizations["utilizingEntitySwitch"][$i] == "taxentity" || $utilizations["utilizingEntitySwitch"][$i] == "myaccounts") { //If this is a legal entity or my accounts
              if($utilizations["entitySelected"][$i] > 0) {
              } else {
                redirect('/dashboard');
              }
            } else {
              redirect('/dashboard');
            }
          }
        }
      }

      //Determine the Utilizing Entity
      if($utilizations["utilizingEntitySwitch"][$i] == "self") { //Self
        $utilizerAccountId = $this->cisession->userdata('primUserId');
        $utilizerTaxpayerId = null;
        $utilizingEntityCustomName = null;
      } else {
        if($utilizations["utilizingEntitySwitch"][$i] == "customname") { //If this is a custom company name
          $utilizerAccountId = 0;
          $utilizerTaxpayerId = null;
          $utilizingEntityCustomName = $utilizations["entitySelected"][$i];
        } else {
          if($utilizations["utilizingEntitySwitch"][$i] == "customers") { //customer
            $utilizerAccountId = $utilizations["entitySelected"][$i];
            $utilizerTaxpayerId = null;
            $utilizingEntityCustomName = null;
          } else {
            if($utilizations["utilizingEntitySwitch"][$i] == "taxentity") { //legal entity
              $utilizerAccountId = 0;
              $utilizerTaxpayerId = $utilizations["entitySelected"][$i];
              $utilizingEntityCustomName = null;
            } else {
              if($utilizations["utilizingEntitySwitch"][$i] == "myaccounts") { //my accounts
                $utilizerAccountId = $utilizations["entitySelected"][$i];
                $utilizerTaxpayerId = null;
                $utilizingEntityCustomName = null;
              }
            }
          }
        }
      }

      $thisTradePriceEstimate = ($utilizations["tradePriceEstimate"][$i] > 0) ? $utilizations["tradePriceEstimate"][$i] : 1;

      $thisUtilization = [];
      $thisUtilization['listingId'] = $utilizations["listingId"][$i];
      $thisUtilization['timeStamp'] = $formattedDate;
      $thisUtilization['accountId'] = $utilizerAccountId;
      $thisUtilization['utilizationTypeId'] = $utilizations["utilizationType"][$i];
      $thisUtilization['utilizingEntityType'] = $utilizations["utilizingEntitySwitch"][$i];
      $thisUtilization['tradeSize'] = $finalEstCreditAmount;
      $thisUtilization['tradePrice'] = $thisTradePriceEstimate;
      $thisUtilization['tradeSizeEstimate'] = $finalEstCreditAmount;
      $thisUtilization['tradePriceEstimate'] = $thisTradePriceEstimate;
      $thisUtilization['tradePercentageEstimate'] = $finalEstCreditPercent;
      $thisUtilization['tradePercentageEstimateCompareTo'] = $finalPercentCompareTo;
      $thisUtilization['tExchangeRate'] = $finalExchangeRate;
      $thisUtilization['tradeDateEstimate'] = strtotime($utilizations["hPayDate"][$i]);
      $thisUtilization['tDmaMemberId'] = $this->cisession->userdata('userId');
      $thisUtilization['tradeNotes'] = $utilizations["notes"][$i];

      if($tradeId > 0) { //If editing an existing estimated utilization

        //Get current utilization
        $utilizationCurrRaw = $this->Trading->get_trade($tradeId);
        $utilizationCurr = $utilizationCurrRaw['trade'];

        $thisUtilization['tradeId'] = $tradeId;
        $thisUtilizationId = $this->Trading->update_estimated_utilization($thisUtilization);

        //Update Transaction
        $thisTransactions = $this->Trading->get_transactions_of_trade($tradeId);
        $aTransaction['buyerAccountId'] = $utilizerAccountId;
        $aTransaction['taxpayerId'] = $utilizerTaxpayerId;
        $aTransaction['utilizingEntityCustomName'] = $utilizingEntityCustomName;
        $aTransaction['tCreditAmount'] = $finalEstCreditAmount;
        foreach($thisTransactions as $tt) {
          $aTransaction['transactionId'] = $tt['transactionId'];
          $this->Trading->update_active_transaction($aTransaction);
        }

        //Get updated utilization
        $utilizationUpdatedRaw = $this->Trading->get_trade($tradeId);
        $utilizationUpdated = $utilizationUpdatedRaw['trade'];

        //////////////////////////////////////////
        //  START - AUDIT ALL POSSIBLE UPDATES  //
        //////////////////////////////////////////

        //Utilization type
        if($utilizationCurr['utilizationTypeId'] != $utilizationUpdated['utilizationTypeId']) {
          $value1 = $utilizationCurr['utName'];
          $value2 = $utilizationUpdated['utName'];
          $this->AuditTrail->insert_audit_item(67, $thisListingId, $value1, $value2, "", "", "", "", "", "", $tradeId);
        }
        //Utilization entity change
        if(($utilizationCurr['utilizingEntityType'] != $utilizationUpdated['utilizingEntityType']) || ($utilizationCurr['utilizingEntityName'] != $utilizationUpdated['utilizingEntityName'])) {
          $value1 = $utilizationCurr['utilizingEntityTypeName'] . " (" . $utilizationCurr['utilizingEntityName'] . ")";
          $value2 = $utilizationUpdated['utilizingEntityTypeName'] . " (" . $utilizationUpdated['utilizingEntityName'] . ")";
          $this->AuditTrail->insert_audit_item(66, $thisListingId, $value1, $value2, "", "", "", "", "", "", $tradeId);
        }
        //Estimated Credit Amount
        if($utilizationCurr['tradeSizeEstimate'] != $utilizationUpdated['tradeSizeEstimate']) {
          if($utilizationCurr['tradeSizeEstimate'] > 0) {
            $value1 = "$" . number_format($utilizationCurr['tradeSizeEstimate'] * $utilizationCurr['tExchangeRate'] ?? 1);
          } else {
            $value1 = "";
          }
          if($utilizationUpdated['tradeSizeEstimate'] != "") {
            $value2 = "$" . number_format($utilizationUpdated['tradeSizeEstimate'] * $utilizationUpdated['tExchangeRate'] ?? 1);
          } else {
            $value2 = "";
          }
          $this->AuditTrail->insert_audit_item(61, $thisListingId, $value1, $value2, "", "", "", "", "", "", $tradeId);
        }
        //Estimated payment date
        if($utilizationCurr['tradeDateEstimate'] != $utilizationUpdated['tradeDateEstimate']) {
          if($utilizationCurr['tradeDateEstimate'] > 0) {
            $value1 = date("m/d/Y", $utilizationCurr['tradeDateEstimate']);
          } else {
            $value1 = "";
          }
          if($utilizationUpdated['tradeDateEstimate'] > 0) {
            $value2 = date("m/d/Y", $utilizationUpdated['tradeDateEstimate']);
          } else {
            $value2 = "";
          }
          $this->AuditTrail->insert_audit_item(62, $thisListingId, $value1, $value2, "", "", "", "", "", "", $tradeId);
        }
        //Estimated Value
        if($utilizationCurr['tradePriceEstimate'] != $utilizationUpdated['tradePriceEstimate']) {
          if($utilizationCurr['tradePriceEstimate'] > 0) {
            $value1 = "$" . number_format($utilizationCurr['tradePriceEstimate'], 4);
          } else {
            $value1 = "";
          }
          if($utilizationUpdated['tradePriceEstimate'] != "") {
            $value2 = "$" . number_format($utilizationUpdated['tradePriceEstimate'], 4);
          } else {
            $value2 = "";
          }
          $this->AuditTrail->insert_audit_item(63, $thisListingId, $value1, $value2, "", "", "", "", "", "", $tradeId);
        }
        //Notes
        if($utilizationCurr['tradeNotes'] != $utilizationUpdated['tradeNotes']) {
          $this->AuditTrail->insert_audit_item(68, $thisListingId, $utilizationCurr['tradeNotes'], $utilizationUpdated['tradeNotes'], "", "", "", "", "", "", $tradeId);
        }

        //////////////////////////////////////////
        //  END - AUDIT ALL POSSIBLE UPDATES  //
        //////////////////////////////////////////

        //Check for estimated utilization dates
        $this->CreditListings->check_utilization_for_due_dates($tradeId);

      } else { //If inserting a NEW estimate

        $thisUtilizationId = $this->Trading->insert_estimated_utilization($thisUtilization);

        //Insert Transaction
        $aTransaction['tradeId'] = $thisUtilizationId;
        $aTransaction['buyerAccountId'] = $utilizerAccountId;
        $aTransaction['taxpayerId'] = $utilizerTaxpayerId;
        $aTransaction['utilizingEntityCustomName'] = $utilizingEntityCustomName;
        $aTransaction['tCreditAmount'] = $finalEstCreditAmount;
        $aTransaction['tDmaId'] = $this->cisession->userdata('dmaId');
        $this->Trading->add_active_transaction($aTransaction);

        //Get current utilization
        $utilizationNewRaw = $this->Trading->get_trade($thisUtilizationId);
        $utilizationNew = $utilizationNewRaw['trade'];

        //Insert audit trail record for new utilization estimate
        $this->AuditTrail->insert_audit_item(60, $thisListingId, "", "$" . number_format($utilizationNew['tradeSizeUSD']), "", "", "", "", "", "", $thisUtilizationId);

        //TODO show USD value in message,
        //Insert message for credit estimate being updated
        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
        $msType = "update";
        $msAction = "new_utilization_estimate";
        $msListingId = $thisListingId;
        $msBidId = "";
        $msTradeId = $thisUtilizationId;
        $msTitle = "New Estimated Utilization - " . $creditData['stateCertNum'] . $projectNameExt . " - Credit Estimate Value $" . number_format($utilizationNew['tradeSizeUSD']) . " (" . $creditData['state'] . $creditData['listingId'] . ")";
        $msTitle2 = "New Estimated Utilization by " . $this->cisession->userdata('dmaTitle') . " - '" . $creditData['stateCertNum'] . $projectNameExt . "' - Credit Estimate Value $" . number_format($utilizationNew['tradeSizeUSD']) . " (" . $creditData['state'] . $creditData['listingId'] . ")";
        $msTitleShared = $msTitle2;
        $msTitleShort = "New Estimated Utilization - Credit Estimate Value $" . number_format($utilizationNew['tradeSize']);
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $this->cisession->userdata('primUserId');
        $secondDmaMainUserId = "";
        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        //Check for estimated utilization dates
        $this->CreditListings->check_utilization_for_due_dates($thisUtilizationId);

      }

      //Update credit update time
      $this->CreditListings->updateTime($thisListingId);

    }

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditData['listingId'];
    $this->CreditListings->update_credit_cache($cData);

    if($tradeId > 0) {
      //Forward to saved estimated credit
      redirect('/dashboard/credit/' . $thisListingId . '/utilization/' . $tradeId);
    } else {
      //Forward to list of utilizations as we may have created multiple
      redirect('/dashboard/credit/' . $thisListingId . '/utilization');
    }

  }

  //Currently used for bulk upload of utilizations only (can handle INSERTING estimates and actuals, but not updating them...yet (TO DO))
  function process_utilization() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $nr = $this->input->post();

    ///// IF EXISTING UTILIZATION ID /////
    if(array_key_exists("tradeId", $nr)) {

      /* To Do - trade update
			$listingIdToUpdate = preg_replace("/[^a-zA-Z0-9]+/", "", $nr["listingId"]); //Put the listing ID column value is a variable
			unset($nr['listingId']); //Then unset it from the array as we aren't updating that value, just using it
			//Make sure this person has access to this credit
			//$this->memberpermissions->checkCreditAccess($listingIdToUpdate, "", "creditEditorOnly"); //THIS IS NOT WORKING
			$thisCredit = $this->CreditListings->get_credit_private($listingIdToUpdate);
			if($thisCredit['listedBy']!=$this->cisession->userdata('primUserId')) {throw new \Exception('General fail');}

			foreach($nr as $k => $v) {
				$k = preg_replace("/[^a-zA-Z0-9]+/", "", $k); //sanitize field value
				$this->CreditListings->update_pending_listing_field($listingIdToUpdate, $k, $v);
				echo 'Update - Listing: '.$listingIdToUpdate.' - '.$k.'='.$v.'<br>';
			}
			echo "<br>";
			*/

      ///// IF NEW UTILIZATION /////
    } else {

      //If using internal credit ID
      if(!isset($nr["listingId"]) && !isset($nr["tradeId"])) {
        if(isset($nr["internalId"])) {
          //Get credit by internal ID
          $nr["internalId"] = trim($nr["internalId"]);
          $lRequest["internalId"] = $nr["internalId"];
          $lRequest["dmaId"] = $this->cisession->userdata('dmaId');
          $lRequest["dmaMemberId"] = $this->cisession->userdata('dmaMemberId');
          $nr["listingId"] = $this->CreditListings->get_credit_id_by_internal_id($lRequest);
        }
      }

      $listingId = $nr["listingId"];
      $bid_size = $nr["tradeSize"];
      $bid_price_percentage = $nr["tradePrice"];
      $transfer_date = date('Y-m-d H:i:s', strtotime($nr["timeStamp"]));
      $utilizationType = $nr["utilizationType"]; //utilization type ID
      $utilizingEntitySwitch = $nr["utilizingEntitySwitch"];
      $entitySelected = isset($nr["entitySelected"]) ? $nr["entitySelected"] : null;

      //Validate data and re-direct if fail
      if($listingId > 0) {
      } else {
        echo "listingId - " . $listingId;
        throw new \Exception('General fail');
      }
      if($bid_size > 0) {
      } else {
        echo "bid_size - " . $listingId;
        throw new \Exception('General fail');
      }
      if($transfer_date != "" && $transfer_date != "") {
      } else {
        echo "transfer_date - " . $listingId;
        throw new \Exception('General fail');
      }
      if($utilizationType > 0) {
      } else {
        echo "utilizationType - " . $listingId;
        throw new \Exception('General fail');
      }
      if($utilizingEntitySwitch == "self") {
        //do nothing
      } else {
        if($utilizingEntitySwitch == "customname") { //If this is a legal entity or my accounts
          $entitySelected = $nr["utilizingEntityCustomName"];
          if($entitySelected != "") {
          } else {
            echo "customname - " . $listingId;
            throw new \Exception('General fail');
          }
        } else {
          if($utilizingEntitySwitch == "taxentity" || $utilizingEntitySwitch == "myaccounts") { //If this is a legal entity or my accounts
            if($entitySelected > 0) {
            } else {
              echo "taxentity / myaccounts - " . $listingId;
              throw new \Exception('General fail');
            }
          } else {
            echo "no utilizingEntitySwitch - " . $listingId;
            throw new \Exception('General fail');
          }
        }
      }

      //Check permission on credit
      //$this->memberpermissions->checkCreditAccess($listingId, 1, 'creditEditorOnly');

      //Get Credit info
      $creditData = $this->CreditListings->get_credit_private($listingId);

      //If trade size is bigger than amount of credit remaining, kick out
      if($bid_size > $creditData['availableToList']) {
        echo "Not enough credit left on Credit ID: " . $listingId;
        throw new \Exception('General fail');
      }

      //Determine the Utilizing Entity
      if($utilizingEntitySwitch == "self") { //Self
        $utilizerAccountId = $this->cisession->userdata('primUserId');
        $utilizerTaxpayerId = null;
        $utilizingEntityCustomName = null;
      } else {
        if($utilizingEntitySwitch == "taxentity") { //legal entity
          $utilizerAccountId = 0;
          $utilizerTaxpayerId = $entitySelected;
          $utilizingEntityCustomName = null;
        } else {
          if($utilizingEntitySwitch == "customname") { //third party custom name
            $utilizerAccountId = 0;
            $utilizerTaxpayerId = null;
            $utilizingEntityCustomName = $nr["utilizingEntityCustomName"];
          } else {
            if($utilizingEntitySwitch == "myaccounts") { //my accounts
              $utilizerAccountId = $entitySelected;
              $utilizerTaxpayerId = null;
              $utilizingEntityCustomName = null;
            }
          }
        }
      }

      //IF THIS IS AN ESTIMATED UTILIZATION
      if(isset($nr["tradeIsEstimated"]) && $nr["tradeIsEstimated"] == 1) {

        //Fixed Currency Amount
        $finalEstCreditAmount = $nr["tradeSize"];
        $finalEstCreditPercent = null;
        $finalPercentCompareTo = null;

        $thisUtilization['listingId'] = $listingId;
        $thisUtilization['timeStamp'] = $transfer_date;
        $thisUtilization['accountId'] = $utilizerAccountId;
        $thisUtilization['utilizationTypeId'] = $utilizationType;
        $thisUtilization['utilizingEntityType'] = $utilizingEntitySwitch;
        $thisUtilization['tradeSize'] = $bid_size;
        $thisUtilization['tradePrice'] = $bid_price_percentage;
        $thisUtilization['tradeSizeEstimate'] = $bid_size;
        $thisUtilization['tradePriceEstimate'] = $bid_price_percentage;
        $thisUtilization['tradePercentageEstimate'] = $finalEstCreditPercent;
        $thisUtilization['tradePercentageEstimateCompareTo'] = $finalPercentCompareTo;
        $thisUtilization['tradeDateEstimate'] = strtotime($transfer_date);
        $thisUtilization['tDmaMemberId'] = $this->cisession->userdata('userId');
        $thisUtilization['tradeNotes'] = null;

        $thisUtilizationId = $this->Trading->insert_estimated_utilization($thisUtilization);

        //Insert Transaction
        $aTransaction['tradeId'] = $thisUtilizationId;
        $aTransaction['buyerAccountId'] = $utilizerAccountId;
        $aTransaction['taxpayerId'] = $utilizerTaxpayerId;
        $aTransaction['utilizingEntityCustomName'] = $utilizingEntityCustomName;
        $aTransaction['tCreditAmount'] = $finalEstCreditAmount;
        $aTransaction['tDmaId'] = $this->cisession->userdata('dmaId');
        $this->Trading->add_active_transaction($aTransaction);

        //Get current utilization
        $utilizationNewRaw = $this->Trading->get_trade($thisUtilizationId);
        $utilizationNew = $utilizationNewRaw['trade'];

        //Insert audit trail record for new utilization estimate
        $this->AuditTrail->insert_audit_item(60, $listingId, "", "$" . number_format($utilizationNew['tradeSizeUSD']), "", "", "", "", "", "", $thisUtilizationId);

        //TODO: show USD currency amount here instead of local
        //Insert message for credit estimate being updated
        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
        $msType = "update";
        $msAction = "new_utilization_estimate";
        $msListingId = $listingId;
        $msBidId = "";
        $msTradeId = $thisUtilizationId;
        $msTitle = "New Estimated Utilization - " . $creditData['stateCertNum'] . $projectNameExt . " - Credit Estimate Value $" . number_format($utilizationNew['tradeSizeUSD']) . " (" . $creditData['state'] . $creditData['listingId'] . ")";
        $msTitle2 = "New Estimated Utilization by " . $this->cisession->userdata('dmaTitle') . " - '" . $creditData['stateCertNum'] . $projectNameExt . "' - Credit Estimate Value $" . number_format($utilizationNew['tradeSizeUSD']) . " (" . $creditData['state'] . $creditData['listingId'] . ")";
        $msTitleShared = $msTitle2;
        $msTitleShort = "New Estimated Utilization - Credit Estimate Value $" . number_format($utilizationNew['tradeSize']);
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $this->cisession->userdata('primUserId');
        $secondDmaMainUserId = "";
        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        $tradeId = $thisUtilizationId; //used below

        //IF THIS IS AN ACTUAL UTILIZATION
      } else {

        //Insert Trade
        $tRequest['listingId'] = $listingId;
        $tRequest['timeStamp'] = $transfer_date;
        $tRequest['status'] = 0;
        $tRequest['accountId'] = $utilizerAccountId;
        $tRequest['tDmaMemberId'] = $this->cisession->userdata('userId');
        $tRequest['tradeSize'] = $bid_size;
        $tRequest['tradePrice'] = $bid_price_percentage;
        $tRequest['utilizationTypeId'] = $utilizationType;
        $tRequest['utilizingEntityType'] = $utilizingEntitySwitch;
        $tradeId = $this->Trading->insert_utilization($tRequest);

        //Insert Transaction
        $aTransaction['tradeId'] = $tradeId;
        $aTransaction['buyerAccountId'] = $utilizerAccountId;
        $aTransaction['taxpayerId'] = $utilizerTaxpayerId;
        $aTransaction['tCreditAmount'] = $bid_size;
        $aTransaction['tDmaId'] = $this->cisession->userdata('dmaId');
        $aTransaction['utilizingEntityCustomName'] = $utilizingEntityCustomName;
        $this->Trading->add_active_transaction($aTransaction);

        //Update credit update time
        $this->CreditListings->updateTime($listingId);

        //Get Trade (newly created)
        $tradeData = $this->Trading->get_trade($tradeId);
        $tradeData = $tradeData['trade'];

        //Create BOX Folders
        $issueId = $tradeData['state'] . $listingId;
        $this->createSaleTransactionFolders($issueId, $listingId, $tradeId, 1);

        //Reduce credit amount
        $this->CreditListings->reduce_credit_remaining($listingId, $tradeData['tradeSize']);
        //If trade is for remaining amount, mark it as sold
        $amtAvailToTrade = $creditData['availableToList'];
        if($tradeData['tradeSize'] == $amtAvailToTrade) {
          $this->CreditListings->update_listing_status($listingId);
        }

        //VARIANCE - Estimated Credit Value
        if($creditData['trackingVariance'] == 1) {

          //$this->runVarianceEstimatedCreditValue($listingId, $tradeId, "utilization");

        }

        //Add new trade to audit trail
        if($tradeData['utilizationTypeId'] == 1) {
          $auditTypeId = 180;
        } else {
          if($tradeData['utilizationTypeId'] == 2) {
            $auditTypeId = 181;
          } else {
            if($tradeData['utilizationTypeId'] == 3) {
              $auditTypeId = 182;
            } else {
              if($tradeData['utilizationTypeId'] == 4) {
                $auditTypeId = 183;
              } else {
                if($tradeData['utilizationTypeId'] == 5) {
                  $auditTypeId = 184;
                } else {
                  if($tradeData['utilizationTypeId'] == 6) {
                    $auditTypeId = 185;
                  }
                }
              }
            }
          }
        }
        $this->AuditTrail->insert_audit_item($auditTypeId, $listingId, "", "$" . number_format($tradeData['tradeSizeUSD']) . " (" . $tradeData['utilizingEntityName'] . ")", "", "", "", "", "", "", $tradeId);

        //TODO: show amount in USD not local currency
        //Insert message
        $msType = "update";
        $msAction = "utilization_new";
        $msListingId = $listingId;
        $msBidId = 0;
        $msTradeId = $tradeId;

        $msTitle = "New " . $tradeData['utName'] . " Utilization (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " - $" . number_format($tradeData['tradeSizeUSD']) . " @ $" . number_format($tradeData['tradePrice'], 4);
        $msTitle2 = "";
        $msTitleShared = $msTitle;
        $msTitleShort = "New " . $tradeData['utName'] . " Utilization (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . ") - $" . number_format($tradeData['tradeSizeUSD']) . " @ $" . number_format($tradeData['tradePrice'], 4);
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "buyer";
        $msPerspective2 = "";
        $firstDmaMainUserId = $creditData['listedBy'];
        $secondDmaMainUserId = "";

        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, "");

      }

      $this->CreditListings->check_utilization_for_due_dates($tradeId);

    }

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditData['listingId'];
    $this->CreditListings->update_credit_cache($cData);

    $response['success'] = true;
    $response['errorMessage'] = "";
    $json = json_encode($response);
    echo $json;

  }

  function purchased($dmaUrl = '') {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "purchased";
      $data['tabArray'] = "purchased";
      $data['current_tab_key'] = "purchased";
      $data['pageKey'] = "purchased";

      if($this->cisession->userdata('dmaType') == "broker") {
        $bRequest['dmaId'] = $this->cisession->userdata('dmaId');
        $bRequest['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
        $activity = $this->CreditListings->get_bids($bRequest);
        $data['activity'] = $activity['bids'];
      } else {
        $activity = $this->CreditListings->get_my_sales_purchased($this->cisession->userdata('primUserId'));
        $data['activity'] = $activity['purchases'];
      }

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dashboard/mysales", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function trades() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "sold";
      $data['tabArray'] = "sold";
      $data['current_tab_key'] = "sold";
      $data['pageKey'] = "sold";

      $sRequest['listedBy'] = null;
      $sRequest['dmaId'] = $this->cisession->userdata('dmaId');
      $sRequest['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
      $data['activity'] = $this->CreditListings->get_my_sales_sold($sRequest);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dashboard/mysales", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function sold() {
    redirect('/dashboard/utilized');
  }

  function utilized() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "utilized";
      $data['tabArray'] = "utilized";
      $data['current_tab_key'] = "utilized";
      $data['pageKey'] = "utilized";

      $data['currentPage'] = "";
      $data['account'] = "";
      $data['search'] = false;
      $data['fieldsType'] = "utilizations";

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);

      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($data);

      $trades = $this->CreditListings->get_utilizations($cData);
      $data['records'] = $trades['trades'];
      $data['recordsSummary'] = $trades['summary'];

      if(1 == 2) {
        $output = ''; //Load all data in sub-data for sub-included view

        $twig = \OIX\Util\TemplateProvider::getTwig();
        $template = $twig->load('utilization/utilizations.twig');
        $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
        $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates
        $data['flashData']['delete_utilization_success'] = $this->session->flashdata('delete_utilization_success');
        $data['creditHeaderHTML'] = null;
        $data['session']['creditHeaderConfig'] = $this->cisession->userdata('creditHeaderConfig');
        $data['session']['dmaType'] = $this->cisession->userdata('dmaType');
        $data['baseUrl'] = base_url();
        $data['trades'] = $trades['trades'];
        $data['tradesSummary'] = $trades['summary'];
        $data['data'] = $data;

        $output .= $this->load->view('includes/left_nav', $data, true);
        $output .= $this->load->view('includes/tab_nav', $data, true);
        $output .= $template->render($data);
        $output .= $this->load->view('includes/widgets/listFiltersOverlay', $data, true);
        $output .= $this->load->view('includes/widgets/listFiltersJavascript', $data, true);
        $output .= $this->load->view('includes/widgets/dateRangeJavascript', $data, true);
        //$this->load->view("dashboard/myutilizations", $data);
        $output .= $this->load->view('includes/footer-2', $data, true);

        echo $output;
      } else {
        $data['data'] = $data; //Load all data in sub-data for sub-included view

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
        $this->load->view("dashboard/myutilizations", $data);
        $this->load->view('includes/footer-2', $data);

      }
    }
  }

  function healthcheck() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "compliance";
      $data['tabArray'] = "healthcheck";
      $data['current_tab_key'] = "";

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dashboard/healthcheck", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function callbackDocusignDocReady() {

    $rawData = json_decode(file_get_contents('php://input'), true);

    $dsId = $rawData['response']['request']['form']['pdf']['id'];
    $envelopeId = $rawData['response']['envelopeId'];

    //Validate data format - if Envelope ID contains only numbers, letters and hyphens && dsId is numeric
    if(preg_match("/^[A-Za-z0-9-]+$/", $envelopeId) && is_numeric($dsId)) {

      //If success
      if($data['json']['success'] = $rawData['response']['success'] == 1) {
        $this->Trades->update_docToSign_envelopeId($dsId, $envelopeId);
      } else {
        //Failed, so send all data to Nick
        $data['json'] = $rawData;
        $this->Email_Model->_send_email('test_data_dump', 'DocuSign API Failed #1', 'nick@theoix.com', $data);
      }

    }

  }

  function callbackDocusignDocSigned() {

    //Mark the doc as signed
    if($_GET['event'] == 'signing_complete') {

      //Validate data format - if Envelope ID contains only numbers, letters and hyphens && dsId is numeric
      if(preg_match("/^[A-Za-z0-9-]+$/", $_GET['envelopeId'])) {
        $this->Docs->update_docToSign_signed($_GET['envelopeId']);
        //Other data available, but not used: $_GET['signerId'] = the docToSign ID in the database // event

        $data['docToSignInfo'] = $this->Docs->get_docToSign_by_envelopeId($_GET['envelopeId']);

        //If this is a CUSTOM signature
        if($data['docToSignInfo']['dsType'] == 'custom_signature') {

          $data['invite'] = $this->Members_Model->get_invite_for_custom_signature($data['docToSignInfo']['dsId']);

          //Mark original Doc to Sign: set the download expiration
          $this->Docs->update_docToSign_download_expires($data['docToSignInfo']['dsId']);

          $data['envelopeId'] = $_GET['envelopeId'];
          $this->load->view("ajax/docuSignRedirectCustomSignature_1", $data);

          //If this is an OIX PSA signature
        } else {
          //Load the redirect script, which will autoforward to function docSignedRedirect with the data in the URL (this is to re-load entire page outside iFrame)
          $this->load->view("ajax/docuSignRedirect");
        }

      }

    } else {
      //There was an error - for example, $_GET['event'] could equal 'ttl_expired' if link expires from time or re-opening the same iframe URL
      redirect('/pagenotfound');
    }

  }

  function docSignedRedirect($envelopeId, $signerId) {

    //Validate data format - if Envelope ID contains only numbers, letters and hyphens && dsId is numeric
    if(preg_match("/^[A-Za-z0-9-]+$/", $envelopeId)) {

      //Get the doc info
      $data['docToSignInfo'] = $this->Docs->get_docToSign_by_envelopeId($envelopeId);

      //Load a view which has a form in it which collects the necessary data and then POSTS to the sign submit function (above)
      $this->load->view("ajax/docuSignSuccess", $data);

    }

  }

  function docSignedRedirect_customSignature($envelopeId, $dsId, $dsAccessTime) {

    //Validate data format - if Envelope ID contains only numbers, letters and hyphens && dsId is numeric
    if(preg_match("/^[A-Za-z0-9-]+$/", $envelopeId)) {

      //Get the doc info
      $data['docToSignInfo'] = $this->Docs->get_docToSign_by_envelopeId($envelopeId);
      if($data['docToSignInfo']['dsId'] == $dsId && $data['docToSignInfo']['dsAccessTime'] == $dsAccessTime) {
        $data['invite'] = $this->Members_Model->get_invite_for_custom_signature($data['docToSignInfo']['dsId']);
        //Load a view which has a form in it which collects the necessary data and then POSTS to the sign submit function (above)
        $this->load->view("ajax/docuSignRedirectCustomSignature_2", $data);
      }

    }

  }

  function runVarianceEstimatedCreditValue($listingId, $tradeId, $tradeType) {

    //Get new credit estimate at this moment in time
    $newCreditEstimateData = $this->CreditListings->get_credit_estimated_value($listingId);
    $newCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
    $newCreditEstFaceValue = $newCreditEstimateData['amountLocal'];
    $newCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
    $newCreditEstValue = $newCreditEstimateData['amountValueLocal'];
    //Get most recent Variance audit record for this credit to get the "prior" value
    $allCreditEstimates = $this->AuditTrail->get_audit_trail($listingId, 2, '');
    //If a record exists, use that data...
    if(sizeof($allCreditEstimates) > 0) {
      $priorCreditEstPrice = $allCreditEstimates[0]["audValAfter"];
      $priorCreditEstFaceValue = $allCreditEstimates[0]["audRelVal1After"];
      $priorCreditEstExchange = $allCreditEstimates[0]["audRelVal2After"];
      $priorCreditEstValue = $allCreditEstimates[0]["audRelVal3After"];
    } else {
      //If a record does NOT exist, use new data so no variance exists...
      $priorCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
      $priorCreditEstFaceValue = $newCreditEstimateData['amountLocal'];
      $priorCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
      $priorCreditEstValue = $newCreditEstimateData['amountValueLocal'];
    }

    $this->AuditTrail->insert_audit_item(2, $listingId, $priorCreditEstPrice, $newCreditEstPrice, $priorCreditEstFaceValue, $newCreditEstFaceValue, $priorCreditEstExchange, $newCreditEstExchange, $priorCreditEstValue, $newCreditEstValue, $tradeId, $tradeType);

  }

  function processSellBid($listingId, $bidId, $perspective) {

    if(($perspective == "seller" || $perspective == "internal_transfer" || $perspective == "external_transfer") && !$this->cisession->userdata('levelSellCredit')) {
      redirect('/dmamembers/no_permission');
    } else {

      $id = $listingId;
      $bid = $bidId;

      if($perspective == "seller" || $perspective == "internal_transfer" || $perspective == "external_transfer") {
        //Check access to credit, get permissions, add to $data
        $this->memberpermissions->checkCreditAccess($id, 0, 'creditEditorOnly');
      }

      $this_listing = $this->CreditListings->get_active_listing_trade($id);
      $this_bid = $this->CreditListings->get_my_bid_trade($bid);
      $creditBefore = $this->CreditListings->get_credit_private($id);

      //If bid is deleted, reject
      if($this_bid['bidDeleteMarker'] == 1) {
        redirect('/dashboard');
      }

      $appId = $this->config->item("app_id");
      $issueId = $this_listing['state'] . $id;

      $newdata = [
          'timeStamp'                     => ($perspective == "internal_transfer" || $perspective == "external_transfer") ? $this_bid['timeStamp'] : date("Y-m-d h:i:s", time()),
          'bidId'                         => $bid,
          'listingId'                     => $this_listing['listingId'],
          'currentPct'                    => $this_bid['bidPrice'],
          'currentSize'                   => $this_bid['bidSize'],
          'prevCalculatedAmountRemaining' => $creditBefore['calculatedAmountRemaining'],
          'HighestPct'                    => $this_listing['HighestBid'],
          'HighestSize'                   => $this_listing['HighestBidSize'],
          'offerPcnt'                     => $this_listing['offerPcnt'],
          'offerSize'                     => $this_listing['offerSize'],
          'lots'                          => $this_listing['allOrNone'],
          'listedBy'                      => $this_listing['listedBy'],
          'availableToList'               => $this_listing['availableToList'],
          'buyerAccountId'                => $this_bid['accountId'],
          'utilizationTypeId'             => 5,
          'utilizingEntityType'           => 'customers',
          'buyerDmaMemberAccountId'       => $this_bid['bDmaMemberId'],
          'tradeType'                     => 'utilization',
      ];
      $this->cisession->set_userdata($newdata);

      $retid1 = 0;

      if($perspective == "internal_transfer" || $perspective == "external_transfer") {
        $amtAvailToTrade = $this_listing['availableToList'];
      } else {
        $amtAvailToTrade = $this_listing['offerSize'];
      }

      //If this is not for sale in "increments" and the BID size equals the OFFER size (i.e., the amount of credit available/remaining)
      if(($newdata['lots'] != '1') && ($newdata['currentSize'] == $amtAvailToTrade)) {

        if((!is_null($retid1 = $this->CreditListings->update_listing_status($newdata['listingId'])))
            && !is_null($retid = $this->Trading->initiate_full_trade())
            && (!is_null($retid1 = $this->CreditListings->reduce_credit_remaining($newdata['listingId'], $newdata['currentSize'])))) {
          //$this->RealTime->add_listing_update($id,null,null,$this->cisession->userdata('userId'));
          $this->Trades->activate_transactions_for_a_sale($bid, $retid);
          //$this->createSaleTransactionFolders($issueId,$id,$retid,$appId);
        }

        //$test['test'] = 1;
        //$this->Email_Model->_send_email('test', 'Test Success', 'nnamikas@gmail.com', $test);

        //Email Sale participants
        if($perspective != "internal_transfer" && $perspective != "external_transfer") {
          $this->notify_key_participants_of_sale($retid);
        }

        //Partial Trades with lot size overwrite
      } else {
        if($newdata['lots'] == '1' || $newdata['lots'] != '1') {

          //If this is a partial sale
          if($amtAvailToTrade > $newdata['currentSize']) {
            if((!is_null($retid = $this->Trading->initiate_partial_trade())) && (!is_null($retid1 = $this->CreditListings->reduce_credit_remaining($newdata['listingId'], $newdata['currentSize'])))) {
              //$this->RealTime->add_listing_update($id,null,null,$this->cisession->userdata('userId'));
              $this->Trades->activate_transactions_for_a_sale($bid, $retid);
              //$this->createSaleTransactionFolders($issueId,$id,$retid,$appId);

            }
          } //If this is a sale for the full remaining amount
          else {
            if(($amtAvailToTrade == $newdata['currentSize'])) {

              if((!is_null($retid1 = $this->CreditListings->update_listing_status($newdata['listingId'])))
                  && !is_null($retid = $this->Trading->initiate_full_trade())
                  && (!is_null($retid1 = $this->CreditListings->reduce_credit_remaining($newdata['listingId'], $newdata['currentSize'])))) {
                //$this->RealTime->add_listing_update($id,null,null,$this->cisession->userdata('userId'));
                $this->Trades->activate_transactions_for_a_sale($bid, $retid);
                //$this->createSaleTransactionFolders($issueId,$id,$retid,$appId);

              }
            } //If Offer Size is Less than Bid size
            else {
              if(($amtAvailToTrade < $newdata['currentSize'])) {

                //DO NOT ALLOW A BID THAT IS GREATER THAN THE CURRENT OFFER PRICE TO BE ACCEPTED!!!!
                redirect('/dashboard');

                //this code will not be run due to the redirect above
                if((!is_null($retid1 = $this->CreditListings->reduce_credit_remaining($newdata['listingId'], $newdata['currentSize']))) &&
                    (!is_null($retid1 = $this->CreditListings->update_listing_status($newdata['listingId']))) &&
                    !is_null($retid = $this->Trading->initiate_full_modified_trade())) {

                  // lets reduce Bid Size now
                  $this->CreditListings->reduce_bid_size($bid, ($newdata['currentSize'] - $amtAvailToTrade), $newdata['currentSize'], $newdata['listedBy']);
                  //$this->RealTime->add_listing_update($id,null,null,$this->cisession->userdata('userId'));
                  $this->Trades->activate_transactions_for_a_sale($bid, $retid);
                  //$this->createSaleTransactionFolders($issueId,$id,$retid,$appId);

                }
              }
            }
          }

          //Email Sale participants
          if($perspective != "internal_transfer" && $perspective != "external_transfer") {
            $this->notify_key_participants_of_sale($retid);
          }

          //No Trade -- But highest bid
        } else {
          if(($newdata['lots'] != '1') && ($newdata['currentSize'] != $amtAvailToTrade)) {

            $this->session->set_flashdata('error', "You can't sell as this offer can't be sold in lots");
            redirect('dashboard/credit/' . $retid1 . '/bids');

          }
        }
      }

      $creditData = $this->CreditListings->get_active_listing($newdata['listingId']);
      $tradeData = $this->Trading->get_trade($bid);
      $transactionsData = $this->Trading->get_transactions_of_trade($tradeData['tradeId']);

      //Create sale folders IF there are 5 or less transactions. If more, then don't do it here. A cron job will pick it up and process it (don't want page to timeout on front end processing Box.com folders...)
      if(!$this->config->item('isLocal') && sizeof($transactionsData) < 6) {
        $this->createSaleTransactionFolders($issueId, $id, $retid, 1);
      }

      if($creditData['cDefaultTransactionWorkflowId'] > 0 || $creditData['cDefaultTransactionComplianceId'] > 0) {
        $transUpdateArray = [];
        $transUpdateArray['transWorkflowId'] = ($creditData['cDefaultTransactionWorkflowId'] > 0) ? $creditData['cDefaultTransactionWorkflowId'] : null;
        $transUpdateArray['transComplianceId'] = ($creditData['cDefaultTransactionComplianceId'] > 0) ? $creditData['cDefaultTransactionComplianceId'] : null;
        foreach($transactionsData as $trans) {
          $this->Trading->update_transaction($trans['transactionId'], $transUpdateArray);
          //Add empty workflow item values
          if($creditData['cDefaultTransactionWorkflowId'] > 0) {
            $workflow = $this->Workflow->get_workflow($creditData['cDefaultTransactionWorkflowId']);
            foreach($workflow['allwlItems'] as $wi) {
              $wRequest['wiId'] = $wi['wiId'];
              $wRequest['wvAttachedToType'] = "transaction";
              $wRequest['wvAttachedToId'] = $trans['transactionId'];
              $wRequest['wiStatus'] = 0;
              $wRequest['wiValue'] = null;
              $this->Workflow->insert_workflow_item_value($wRequest);
            }
          }
          if($creditData['cDefaultTransactionComplianceId'] > 0) {
            $workflow = $this->Workflow->get_workflow($creditData['cDefaultTransactionComplianceId']);
            foreach($workflow['allwlItems'] as $wi) {
              $wRequest['wiId'] = $wi['wiId'];
              $wRequest['wvAttachedToType'] = "transaction";
              $wRequest['wvAttachedToId'] = $trans['transactionId'];
              $wRequest['wiStatus'] = 0;
              $wRequest['wiValue'] = null;
              $this->Workflow->insert_workflow_item_value($wRequest);
            }
          }
        }

      }

      if($perspective == "internal_transfer") {
        //Internal Transfer
        //Add new trade to audit trail
        $this->AuditTrail->insert_audit_item(81, $newdata['listingId'], "", "$" . number_format($newdata['currentSize']) . " (" . $tradeData['buyerAccountName'] . ")", "", "", "", "", "", "", $retid);
      } else {
        if($perspective == "external_transfer") {
          //Record a Transfer
          //Add new trade to audit trail
          $tpName = ($transactionsData[0]['tpAccountType'] == 1) ? $transactionsData[0]['tpCompanyName'] : $transactionsData[0]['tpFirstName'] . " " . $transactionsData[0]['tpLastName'];
          $this->AuditTrail->insert_audit_item(82, $newdata['listingId'], "", "$" . number_format($newdata['currentSize']) . " (" . $tpName . ")", "", "", "", "", "", "", $retid);
        } else {
          //Add new Utilization to audit trail
          $this->AuditTrail->insert_audit_item(60, $newdata['listingId'], "", "$" . number_format($newdata['currentSize']) . " (" . $tradeData['buyerAccountName'] . ")", "", "", "", "", "", "", $retid);
        }
      }

      //VARIANCE - Estimated Credit Value
      if($creditData['trackingVariance'] == 1) {

        $this->runVarianceEstimatedCreditValue($id, $retid, "trade");

      }

      /*
			if($perspective != "internal_transfer" && $perspective != "external_transfer") {

				//send email
				$emailData = $newdata;
				$emailData['tradeId'] = $retid;
				$emailData['updateType'] = 'newPurchase';
				$emailData['updateTypeName'] = 'New Purchase';
				$emailData['members'] = $this->BidMarket->get_buyer_seller_in_bid($newdata['bidId']);
				$emailData['credit'] = $creditData;
				$this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - '.$emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);


				//Insert NEW TRADE notification to database
				$nData['nType'] = 'trade';
				$nData['nMembers'] = 'active_all_notifications_on';
				$nData['nGreeting'] = 0;
				$nData['nSignature'] = 0;
				$nData['nButton'] = 1;
				$nData['nActivityId'] = $retid;
				$nData['nSubject'] = 'New Trade on '.$creditData["name"].' '.$creditData["taxYear"].' Credit on the OIX';
				$nData['nHeadline'] = str_replace('%20', ' ', 'New Trade on the OIX');
				$this->Members_Model->insert_notification($nData);

			}
			*/

      //Insert message
      $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
      $msType = "update";
      $msAction = "trade_new";
      $msListingId = $newdata['listingId'];
      $msBidId = $newdata['bidId'];
      $msTradeId = $retid;

      /* VERSION SENT TO JUST SELLER */
      $msTitle = "New Sale (" . $creditData['state'] . $creditData['listingId'] . "-" . $retid . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ") - $" . number_format($this_bid['bidSize']) . " @ $" . number_format($this_bid['bidPrice'], 4);
      $msTitle2 = "";
      $msTitleShared = $msTitle;
      $msTitleShort = "New Sale (" . $creditData['state'] . $creditData['listingId'] . "-" . $retid . ") - $" . number_format($this_bid['bidSize']) . " @ $" . number_format($this_bid['bidPrice'], 4);
      $msTitle2Short = "";
      $msTitleSharedShort = $msTitleShort;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $this->cisession->userdata('primUserId');
      $secondDmaMainUserId = "";

      $msUserIdCreated = $this->cisession->userdata('userId');
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, "");

      $newdata = [
          'bidId'       => '',
          'listingId'   => $this->cisession->userdata('listingId'),
          'currentPct'  => '',
          'currentSize' => '',
          'HighestPct'  => '',
          'HighestSize' => '',
          'offerPcnt'   => '',
          'offerSize'   => '',
          'lots'        => '',
      ];

      $this->cisession->set_userdata($newdata);

      //If this is the seller who is accepting the bid
      if($perspective == "seller" || $perspective == "internal_transfer" || $perspective == "external_transfer") {
        if($perspective == "seller") {
          $alertOverlay = "update_seller_success";
        }
        if($perspective == "internal_transfer") {
          $alertOverlay = "update_internal_transfer_success";
        }
        if($perspective == "external_transfer") {
          $alertOverlay = "update_external_transfer_success";
        }
        $this->cisession->set_userdata($alertOverlay, 1);
        redirect('dashboard/credit/' . $newdata['listingId'] . '/sale/' . $retid);

        //Else if this is the buyer whose bid has matched the credit then we're triggering an auto-sale
      } else {
        $this->cisession->set_userdata('update_buyer_success', 1);
        redirect('dashboard/credit/' . $newdata['listingId'] . '/purchase/' . $retid);
      }

    }
  }

  function deleteUtilization($tradeId) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    //Get Trade xinfo
    $tradeDataRaw = $this->Trading->get_trade($tradeId);
    $tradeData = $tradeDataRaw['trade'];
    $creditId = $tradeData['listingId'];

    //Check edit permission on credit
    $this->memberpermissions->checkCreditAccess($tradeData['listingId'], 0, 'creditEditorOnly');
    //Get Credit info
    $creditData = $this->CreditListings->get_credit_private($tradeData['listingId']);

    //delete transactions
    $transactions = $this->Trades->get_transactions_of_trade($tradeId);
    if(sizeof($transactions) > 0) {
      foreach($transactions as $t) {
        $this->Trades->delete_transaction($t['transactionId']);
      }
    }

    //Delete utilization
    $this->Trading->delete_trade($tradeId);

    $this->CreditListings->softDeleteDataPointValues('utilization', $tradeId);

    if($tradeData['tradeIsEstimated'] == 1) {
    } else {
      //Reduce listing
      $increaseBy = $tradeData['tradeSizeLocal'];
      $increaseBy = -$increaseBy; //make it negative so it ADDs in the next function
      $this->CreditListings->reduce_credit_remaining($creditId, $increaseBy);

    }

    $auditData['audTypeId'] = 94;
    $auditData['audItemId'] = $creditId;
    $auditData['audItemIdSub'] = $tradeId;
    $auditData['audValueBefore'] = $tradeData['utilizationAuditSummary'];
    $auditData['audValueAfter'] = 0;
    $auditData['audUserId'] = $this->cisession->userdata('userId');
    $auditData['audDmaId'] = $this->cisession->userdata('dmaId');
    $this->AuditTrail->insert_audit_item_api($auditData);

    //TODO: change local currency amount to USD
    //Insert message of deleted trade
    $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
    $msType = "update";
    $msAction = "estimated_utilization_deleted";
    $msListingId = $tradeData['listingId'];
    $msBidId = "";
    $msTradeId = $tradeId;
    $msTitle = "Estimated Utilization Deleted - " . $tradeData['utName'] . " for " . $tradeData['utilizingEntityName'] . " worth $" . number_format($tradeData['tradeSizeUSD'] * $tradeData['tradePrice']) . " (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . " - " . $creditData['stateCertNum'] . $projectNameExt . ")";
    $msTitle2 = "";
    $msTitleShared = $msTitle;
    $msTitleShort = $msTitle;
    $msTitle2Short = $msTitleShort;
    $msTitleSharedShort = $msTitleShort;
    $msContent = "";
    $msContent2 = "";
    $msPerspective = "seller";
    $msPerspective2 = "";
    $firstDmaMainUserId = $this->cisession->userdata('primUserId');
    $secondDmaMainUserId = "";
    $msUserIdCreated = $this->cisession->userdata('userId');
    $alertShared = true;
    $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    //Delete any calendar alerts for this trade
    $msAction = ($tradeData['tradeIsEstimated'] == 1) ? "utilization_estimate_update" : "utilization_actual_update";
    $this->Members_Model->search_and_delete_alert_messages($msAction, $tradeData["listingId"], $tradeId);

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditData['listingId'];
    $this->CreditListings->update_credit_cache($cData);

    $this->session->set_flashdata('delete_utilization_success', 1);
    redirect('dashboard/credit/' . $tradeData['listingId'] . '/utilization');

  }

  function processUtilization() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      throw new \Exception('General fail');
    }

    $listingId = $this->input->post('listingId');
    $bid_size = $this->input->post('bid_size');
    $bid_exchange_rate = $this->input->post('bid_exchange_rate');
    $bid_price_percentage = $this->input->post('bid_price_percentage');
    $transfer_date = date('Y-m-d H:i:s', strtotime($this->input->post('transfer_date')));
    $utilizationType = $this->input->post('utilizationType');
    $utilizingEntitySwitch = $this->input->post('utilizingEntitySwitch');
    $entitySelected = $this->input->post('entitySelected');

    //Validate data and re-direct if fail
    if($listingId > 0) {
    } else {
      redirect('/dashboard');
    }
    if($bid_size > 0) {
    } else {
      redirect('/dashboard');
    }
    if($this->input->post('transfer_date') != "" && $transfer_date != "") {
    } else {
      redirect('/dashboard');
    }
    if($utilizationType > 0) {
    } else {
      redirect('/dashboard');
    }
    if($utilizingEntitySwitch == "self") {
      //do nothing
    } else {
      if($utilizingEntitySwitch == "customname") { //If this is a legal entity or my accounts
        $entitySelected = $this->input->post('utilizingEntityCustomName');
        if($entitySelected != "") {
        } else {
          echo $entitySelected;
          throw new \Exception('General fail');
          redirect('/dashboard');
        }
      } else {
        if($utilizingEntitySwitch == "taxentity" || $utilizingEntitySwitch == "myaccounts" || $utilizingEntitySwitch == "customers") { //If this is a legal entity or my accounts
          if($entitySelected > 0) {
          } else {
            redirect('/dashboard');
          }
        } else {
          redirect('/dashboard');
        }
      }
    }

    //Check permission on credit
    $this->memberpermissions->checkCreditAccess($listingId, 1, 'creditEditorOnly');

    //Get Credit info
    $creditData = $this->CreditListings->get_credit_private($listingId);

    //If trade size is bigger than amount of credit remaining, kick out
    if($bid_size > $creditData['availableToList']) {
      redirect('/dashboard');
    }

    //Determine the Utilizing Entity
    if($utilizingEntitySwitch == "self") { //Self
      $utilizerAccountId = $this->cisession->userdata('primUserId');
      $utilizerTaxpayerId = null;
      $utilizingEntityCustomName = null;
    } else {
      if($utilizingEntitySwitch == "taxentity") { //legal entity
        $utilizerAccountId = 0;
        $utilizerTaxpayerId = $entitySelected;
        $utilizingEntityCustomName = null;
      } else {
        if($utilizingEntitySwitch == "customname") { //third party custom name
          $utilizerAccountId = 0;
          $utilizerTaxpayerId = null;
          $utilizingEntityCustomName = $this->input->post('utilizingEntityCustomName');
        } else {
          if($utilizingEntitySwitch == "myaccounts" || $utilizingEntitySwitch == "customers") { //my accounts
            $utilizerAccountId = $entitySelected;
            $utilizerTaxpayerId = null;
            $utilizingEntityCustomName = null;
          }
        }
      }
    }

    //Insert Trade
    $tRequest['listingId'] = $listingId;
    $tRequest['timeStamp'] = $transfer_date;
    $tRequest['status'] = 0;
    $tRequest['accountId'] = $utilizerAccountId;
    $tRequest['tDmaMemberId'] = $this->cisession->userdata('userId');
    $tRequest['tradeSize'] = $bid_size;
    $tRequest['tradePrice'] = $bid_price_percentage;
    if(isset($bid_exchange_rate) && $bid_exchange_rate > 0) {
      $tRequest['tExchangeRate'] = $bid_exchange_rate;
    }
    $tRequest['utilizationTypeId'] = $utilizationType;
    $tRequest['utilizingEntityType'] = $utilizingEntitySwitch;
    $tradeId = $this->Trading->insert_utilization($tRequest);

    //Insert Transaction
    $aTransaction['tradeId'] = $tradeId;
    $aTransaction['buyerAccountId'] = $utilizerAccountId;
    $aTransaction['taxpayerId'] = $utilizerTaxpayerId;
    $aTransaction['tCreditAmount'] = $bid_size;
    $aTransaction['tDmaId'] = $this->cisession->userdata('dmaId');
    $aTransaction['utilizingEntityCustomName'] = $utilizingEntityCustomName;
    $newTransactionId = $this->Trading->add_active_transaction($aTransaction);

    if($creditData['cDefaultTransactionWorkflowId'] > 0) {
      $transUpdateArray = [];
      $transUpdateArray['transWorkflowId'] = $creditData['cDefaultTransactionWorkflowId'];
      $this->Trading->update_transaction($newTransactionId, $transUpdateArray);
      //Add empty workflow item values
      $workflow = $this->Workflow->get_workflow($creditData['cDefaultTransactionWorkflowId']);
      foreach($workflow['allwlItems'] as $wi) {
        $wRequest['wiId'] = $wi['wiId'];
        $wRequest['wvAttachedToType'] = "transaction";
        $wRequest['wvAttachedToId'] = $newTransactionId;
        $wRequest['wiStatus'] = 0;
        $wRequest['wiValue'] = null;
        $this->Workflow->insert_workflow_item_value($wRequest);
      }

    }

    //Update credit update time
    $this->CreditListings->updateTime($listingId);

    //Get Trade (newly created)
    $tradeData = $this->Trading->get_trade($tradeId);
    $tradeData = $tradeData['trade'];

    //Create BOX Folders
    $issueId = $tradeData['state'] . $issueId;
    $this->createSaleTransactionFolders($issueId, $listingId, $tradeId, 1);

    //Reduce credit amount
    $this->CreditListings->reduce_credit_remaining($listingId, $tradeData['tradeSize']);
    //If trade is for remaining amount, mark it as sold
    $amtAvailToTrade = $creditData['availableToList'];
    if($tradeData['tradeSize'] == $amtAvailToTrade) {
      $this->CreditListings->update_listing_status($listingId);
    }

    //VARIANCE - Estimated Credit Value
    if($creditData['trackingVariance'] == 1) {

      $this->runVarianceEstimatedCreditValue($listingId, $tradeId, "utilization");

    }

    //Add new trade to audit trail
    if($tradeData['utilizationTypeId'] == 1) {
      $auditTypeId = 180;
    } else {
      if($tradeData['utilizationTypeId'] == 2) {
        $auditTypeId = 181;
      } else {
        if($tradeData['utilizationTypeId'] == 3) {
          $auditTypeId = 182;
        } else {
          if($tradeData['utilizationTypeId'] == 4) {
            $auditTypeId = 183;
          } else {
            if($tradeData['utilizationTypeId'] == 5) {
              $auditTypeId = 184;
            } else {
              if($tradeData['utilizationTypeId'] == 6) {
                $auditTypeId = 185;
              }
            }
          }
        }
      }
    }
    $this->AuditTrail->insert_audit_item($auditTypeId, $listingId, "", "$" . number_format($tradeData['tradeSizeUSD']) . " (" . $tradeData['utilizingEntityName'] . ")", "", "", "", "", "", "", $tradeId);

    //TODO: show USD instead of local currency
    //Insert message
    $msType = "update";
    $msAction = "utilization_new";
    $msListingId = $listingId;
    $msBidId = 0;
    $msTradeId = $tradeId;

    $msTitle = "New " . $tradeData['utName'] . " Utilization (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " - $" . number_format($tradeData['tradeSizeUSD']) . " @ $" . number_format($tradeData['tradePrice'], 4);
    $msTitle2 = "";
    $msTitleShared = $msTitle;
    $msTitleShort = "New " . $tradeData['utName'] . " Utilization (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . ") - $" . number_format($tradeData['tradeSizeUSD']) . " @ $" . number_format($tradeData['tradePrice'], 4);
    $msTitle2Short = "";
    $msTitleSharedShort = $msTitleShort;
    $msContent = "";
    $msContent2 = "";
    $msPerspective = "buyer";
    $msPerspective2 = "";
    $firstDmaMainUserId = $creditData['listedBy'];
    $secondDmaMainUserId = "";

    $msUserIdCreated = $this->cisession->userdata('userId');
    $alertShared = true;
    $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, "");

    $this->CreditListings->check_utilization_for_due_dates($tradeId);

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditData['listingId'];
    $this->CreditListings->update_credit_cache($cData);

    $this->cisession->set_userdata('new_utilization_success', 1);
    redirect('dashboard/credit/' . $listingId . '/utilization/' . $tradeId);

  }

  function notify_key_participants_of_sale($tradeId) {

    /*

		//Get data for trade and transactions
		$trade = $this->Trading->get_trade($tradeId);
		$trade = $trade['trade'];
		$transactions = $this->Trading->get_transactions_of_trade($tradeId);
		$creditData = $this->CreditListings->get_credit_private($trade['listingId']);

		//if this is NOT a seller and NOT a buyer in this trade, then kick them out
		if($trade['listedBy']!=$this->cisession->userdata("primUserId") && $trade['BaccountId']!=$this->cisession->userdata("primUserId")) {
			redirect('/dashboard');
		}

		//Get DMA info for buyer and seller

		//Get email address for buyer and seller
		//If a DMA seller
		if($trade['cDmaMemberId']>0) {
			$sellerInfo = $this->Members_Model->get_member_by_id($trade['cDmaMemberId']);
		} else {
			$sellerInfo = $this->Members_Model->get_member_by_id($trade['listedBy']);
		}
		//If a DMA buyer
		if($trade['bDmaMemberId']>0) {
			$buyerInfo = $this->Members_Model->get_member_by_id($trade['bDmaMemberId']);
		} else {
			$buyerInfo = $this->Members_Model->get_member_by_id($trade['BaccountId']);
		}

		//Get seller taxpayer info
		if($trade['cTaxpayerId']!="") {
			$sTaxpayer = $this->Taxpayersdata->get_taxpayer($trade['cTaxpayerId'], 0, 0);
		}

		//Basic email data for seller
		$dataEmailSeller['tradeId'] = $tradeId;
		$dataEmailSeller['dmaTitle'] = $dmaSeller['title'];
		$dataEmailSeller['firstName'] = $sellerInfo['firstName'];
		$dataEmailSeller['lastName'] = $sellerInfo['lastName'];
		$dataEmailSeller['email'] = $sellerInfo['email'];
		$dataEmailSeller['listingId'] = $trade['listingId'];
		$dataEmailSeller['State'] = $trade['State'];
		$dataEmailSeller['size'] = $trade['size'];
		$dataEmailSeller['price'] = $trade['price'];
		$dataEmailSeller['transactions'] = $transactions;

		//Basic email data for seller
		$dataEmailBuyer['tradeId'] = $tradeId;
		$dataEmailBuyer['dmaTitle'] = $dmaBuyer['title'];
		$dataEmailBuyer['firstName'] = $buyerInfo['firstName'];
		$dataEmailBuyer['lastName'] = $buyerInfo['lastName'];
		$dataEmailBuyer['email'] = $buyerInfo['email'];
		$dataEmailBuyer['listingId'] = $trade['listingId'];
		$dataEmailBuyer['State'] = $trade['State'];
		$dataEmailBuyer['size'] = $trade['size'];
		$dataEmailBuyer['price'] = $trade['price'];
		$dataEmailBuyer['transactions'] = $transactions;

		///// SELLER /////

		//If there is a taxpayer seller, add that info
		if($sTaxpayer['tpEmailSigner']!="") {

			$dataEmailSeller['taxentitySeller'] = $sTaxpayer;
			$dataEmailSeller['taxentitySellerExists'] = 'yes';
			$dataEmailBuyer['taxentitySeller'] = $sTaxpayer;
			$dataEmailBuyer['taxentitySellerExists'] = 'yes';

		} else {
			$dataEmailSeller['taxentitySellerExists'] = 'no';
			$dataEmailBuyer['taxentitySellerExists'] = 'no';
		}

		//Add seller signer flag
		$dataEmailSeller['blockSellerSignatureAlerts'] = $creditData['blockSellerSignatureAlerts'];
		$dataEmailSeller['stateCertNum'] = $creditData['stateCertNum'];
		$dataEmailSeller['projectNameExt'] = ($creditData['projectNameExt']!="") ? " - ".$creditData['projectNameExt'] : "";

		///// BUYER /////

		//If there are taxpayer buyers
		//loop through the transactions, if there is a TaxPayer Signer assigned, then create an invite and send email
		$taxpayersNotified = array();
		foreach($transactions as $t) {
			if($t['tpEmailSigner']!="") {

				//Add taxpayer info to array for the email to the master seller
				$tpNotified['tpAccountType'] = $t['tpAccountType'];
				$tpNotified['tpCompanyName'] = $t['tpCompanyName'];
				$tpNotified['tpFirstName'] = $t['tpFirstName'];
				$tpNotified['tpLastName'] = $t['tpLastName'];
				$tpNotified['tCreditAmount'] = $t['tCreditAmount'];
				array_push($taxpayersNotified,$tpNotified);

			}
		}

		//Add the compiled data to the email data
		$dataEmailSeller['taxentityBuyer'] = $taxpayersNotified;
		$dataEmailBuyer['taxentityBuyer'] = $taxpayersNotified;
		if(sizeof($taxpayersNotified)>0) {
			$dataEmailSeller['taxentityBuyerExists'] = 'yes';
			$dataEmailBuyer['taxentityBuyerExists'] = 'yes';
		} else {
			$dataEmailSeller['taxentityBuyerExists'] = 'no';
			$dataEmailBuyer['taxentityBuyerExists'] = 'no';
		}

		// SEND EMAILS //
		//Seller
		//If the flag to BLOCK seller signature alerts is OFF, then allow to proceed
		$this->Email_Model->_send_email('sale_confirmation', 'Sale Confirmation of '.$dataEmailSeller['State'].$dataEmailSeller['listingId'].' - '.$dataEmailSeller['stateCertNum'].$dataEmailSeller['projectNameExt'], $dataEmailSeller['email'], $dataEmailSeller);
		//Buyer
		$this->Email_Model->_send_email('purchase_confirmation', 'Purchase Confirmation of '.$dataEmailBuyer['State'].$dataEmailBuyer['listingId'], $dataEmailBuyer['email'], $dataEmailBuyer);

		*/

  }

  function createSaleTransactionFolders($issueId, $id, $saleId, $appId) {
    /* NOTE - for multi-buyer sales,  this has been moved to a Cron function (sale folders now created behind the scenes on a 4 minute interval)*/
    /* NOTE 2 - if you change here, make sure to change matching function in bulk process - "backend" controller */

    $transactionIds = [];
    $transactionIds = $this->Trades->get_transaction_ids_of_trade($saleId);

    $this->filemanagementlib->createBoxFolders($issueId, $id, $saleId, $transactionIds, 2);

    //Mark trade folders as complete
    $this->Trading->mark_trade_folders_status($saleId, 1);

  }

  function loadcredit($creditId = "", $creditOriginType = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user has ability to load/edit credit from a global permisssion
      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      }

      //Setup basic variables
      $data['lnav_key'] = "credits";
      $data['tabArray'] = "";
      $data['current_tab_key'] = "";

      //Flag if this is loading a new credit or editing existing one
      $data['creditId'] = $creditId;
      $data['isNewCredit'] = ($data['creditId'] > 0) ? false : true;
      $data['tabArray'] = ($data['isNewCredit']) ? "loadcredit" : "editcredit";

      $data['successAction'] = "loadCreditPage";
      $data['creditOriginType'] = ($creditOriginType != "") ? $creditOriginType : ($creditId > 0 ? "existingCredit" : "loaded");

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dashboard/loadcredit_page", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function mycredits() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "credits";
      $data['tabArray'] = "managing";
      $data['current_tab_key'] = "managing";
      $data['currentPage'] = "managing";

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);

      $data['creditListViewPref'] = $this->cisession->userdata('creditListViewPref');
      $data['order'] = $this->cisession->userdata('creditListOrder');
      $data['view'] = $this->cisession->userdata('creditListSections');
      $data['viewIncludeFile'] = ($data['creditListViewPref'] == 2) ? "view_scroller" : "view_flat";
      $data['listClass'] = ($data['creditListViewPref'] == 2) ? "cmd" : "credit-managing";
      $data['loanType'] = "";
      $data['fieldsType'] = "mycredits";

      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($data);

      $credits = $this->CreditListings->get_credits($cData);
      $data['records'] = $credits['credits'];
      $data['recordsSummary'] = $credits['summary'];

      //Columns
      //$updatedDmaAccount = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));
      $data['columns'] = $this->cisession->userdata('creditViewConfig');

      if($data['columns'] != "" && $data['creditListViewPref'] == 2) {
        //Do nothing
      } else {
        $defaults = $this->globalsettings->get_global_setting("my_credits_view_default");
        $savedColumns = $defaults['gsValueLong'];
        $data['columns'] = json_decode($savedColumns, true);
      }

      //Now that we have data, send it to get built into the reports array
      $data['dataKey'] = "getMyCreditsView";
      $reportData = $this->CreditListings->buildReportData($data['dataKey'], $data['columns'], $data['records'], $data['order'], $data['view'], $this->cisession->userdata('dmaId'));
      $data['sDataKeyValue'] = $reportData['sDataKeyValue'];
      $data['sDataHeaderKeyValue'] = $reportData['sDataHeaderKeyValue'];
      $data['sDataSummary'] = $reportData['sDataSummary'];

      //If an account is selected for filtering
      $sharedAccounts = explode(',', $data['sharedAccount']);
      $data['isSingleSelectedAccount'] = ($data['sharedAccount'] != 0 && sizeof($sharedAccounts) == 1) ? true : false;
      if($data['isSingleSelectedAccount']) {
        $data['selectedAccount'] = $this->DmaAccounts->get_dma_account_by_id($data['sharedAccount']); //TODO - convert to DMA ID
        //} else if($data['sharedAccount']=='self') {
        //$data['selectedAccount'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));
      } else {
        $data['selectedAccount'] = [];
      }

      //Pack add on?
      if(in_array(1, $this->cisession->userdata('planDataPacks'))) {
        $data['loanType'] = 1;
      } else {
        if(in_array(2, $this->cisession->userdata('planDataPacks'))) {
          $data['loanType'] = 2;
        } else {
          $data['loanType'] = "";
        }
      }

      //Load all data in sub-data for sub-included view
      $data['data'] = $data;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("dashboard/mycredits", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function shared() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['lnav_key'] = "shared";
    if($this->cisession->userdata('dmaType') == "advisor") {
      $data['tabArray'] = "advisor_projects";
    } else {
      if($this->cisession->userdata('dmaType') == 'broker') {
        $data['tabArray'] = "broker_sellers";
      } else {
        $data['tabArray'] = "shared";
      }
    }
    $data['current_tab_key'] = "shared";
    $data['currentPage'] = "shared";

    //Pack add on?
    if($this->cisession->userdata('dmaType') == 'advisor') {
      $allLoans = $this->CreditListings->get_loans($this->cisession->userdata('primUserId'), 2, 1);
      $data['allLoans'] = $allLoans["loans"];
      $data['allLoansAnalytics'] = $allLoans["analytics"];
    } else {
      if(in_array(1, $this->cisession->userdata('planDataPacks'))) {
        $allLoans = $this->CreditListings->get_loans($this->cisession->userdata('primUserId'), 1, 1);
        $data['allLoans'] = $allLoans["loans"];
        $data['allLoansAnalytics'] = $allLoans["analytics"];
      } else {
        $data['allLoans'] = [];
        $data['allLoansAnalytics'] = [];
      }
    }

    //GET PROJECTS
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    $preparedFilterData['order'] = "seller_a_z";
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    $credits = $this->CreditListings->get_credits($cData);
    $data['activity'] = $credits['credits'];
    $data['activitySummary'] = $credits['summary'];

    $data['childAccounts'] = $this->DmaAccounts->get_child_accounts_of_dma_account($this->cisession->userdata('dmaId'));

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    $this->load->view("dashboard/shared", $data);
    $this->load->view('includes/footer-2', $data);

  }

  function analytics() {
    redirect('/dashboard/analytics_report');
  }

  function analytics_report($reportTab = "", $date1 = "", $date2 = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "analytics";
      $data['tabArray'] = "analytics_details_mycredits";
      $data['current_tab_key'] = "";

      $data['sub_environment'] = EnvironmentHelper::getEnvFromHost();

      $reportTab = ($reportTab != "") ? $reportTab : "summary";
      $data['reportTab'] = $reportTab;

      $data['CSS_COLOR_HEX'] = $this->config->item("CSS_COLOR_HEX");
      $data['branding'] = [];
      $data['branding']['colors'] = [];
      $data['branding']['colors']['dark'] = ($this->cisession->userdata('dmaId') == 564) ? '#2c684f' : $data['CSS_COLOR_HEX'] [0];
      $data['branding']['colors']['mid'] = ($this->cisession->userdata('dmaId') == 564) ? '#7ebb45' : $data['CSS_COLOR_HEX'] [1];
      $data['branding']['colors']['light'] = ($this->cisession->userdata('dmaId') == 564) ? '#a2ce79' : $data['CSS_COLOR_HEX'] [2];
      $data['branding']['colors']['veryLight'] = ($this->cisession->userdata('dmaId') == 564) ? '#f8faf1' : $data['CSS_COLOR_HEX'] [3];

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);
      $data['data'] = $data;
      $data['fieldsType'] = "mycredits";

      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($data);

      if($reportTab == "summary") {

        //PDF Download Configs
        $data['pdfDownloadConfigs']['pdfpageonetitle'] = 'Analytics Summary';
        $data['pdfDownloadConfigs']['pdfname'] = 'Analytics_Summary_' . date("Y-m-d", time());
        $data['pdfDownloadConfigs']['pdforientation'] = 'landscape';
        $data['pdfDownloadConfigs']['pdfelement'] = 'printToPdf_analytics_summary';

        //credit data goes here
        $credits = $this->CreditListings->get_credits($cData);

        $creditList = $credits['credits'];
        $data['noData'] = (sizeof($credits['credits']) > 0) ? false : true;
        $data['mapArray'] = $creditList;
        $data['credits'] = $creditList;
        $data['creditsSummary'] = $credits['summary'];

        //Get Credit Type options
        $data['credit_types'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('program_type');

        //Get Certification Status options
        $data['certification_status'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('certification_status');

        //Get Project Status options
        $data['project_status_values'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('project_status');

        //Get Monetization Status options
        $data['monetization_status_values'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('monetization_status');

        //Get Audit Status options
        $data['audit_status_values'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('audit_status');

        //Get custom data points for DATES
        $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
        $dpRequest['dpType'] = 'selectDropDown';
        $dpRequest['dpSection'] = 'credit_status_settings';
        $customDataPointStatusesRaw = $this->CreditListings->get_data_points($dpRequest);
        $data['customDataPointStatuses'] = $customDataPointStatusesRaw['dataPoints'];

      }

      if($reportTab == "estimated_payment_dates") {

        //PDF Download Configs
        $data['pdfDownloadConfigs']['pdfpageonetitle'] = 'Monetization Forecast';
        $data['pdfDownloadConfigs']['pdfname'] = 'Monetization_Forecast_' . date("Y-m-d", time());
        $data['pdfDownloadConfigs']['pdforientation'] = 'landscape';
        $data['pdfDownloadConfigs']['pdfelement'] = 'printToPdf_analytics_summary';

        //Get outstanding payments
        $cData['utitlizationStatus'] = 1; //Get both estimated and actual (as we need actual for math)
        $data['credits'] = $this->CreditListings->get_utilizations_timeline($cData);
        $data['noData'] = (sizeof($data['credits']['creditDetails']) > 0) ? false : true;
      }

      if($reportTab == "actual_payment_dates") {

        //PDF Download Configs
        $data['pdfDownloadConfigs']['pdfpageonetitle'] = 'Monetization Report';
        $data['pdfDownloadConfigs']['pdfname'] = 'Monetization_Report_' . date("Y-m-d", time());
        $data['pdfDownloadConfigs']['pdforientation'] = 'landscape';
        $data['pdfDownloadConfigs']['pdfelement'] = 'printToPdf_analytics_summary';

        //Get outstanding payments
        $cData['utitlizationStatus'] = 0; //Get only actual utilizations
        $data['credits'] = $this->CreditListings->get_utilizations_timeline($cData);
        $data['noData'] = (sizeof($data['credits']['creditDetails']) > 0) ? false : true;
      }

      if($reportTab == "date_range_analyzer") {

        //PDF Download Configs
        $data['pdfDownloadConfigs']['pdfpageonetitle'] = 'Performance Analysis';
        $data['pdfDownloadConfigs']['pdfname'] = 'Performance_Analysis_' . date("Y-m-d", time());
        $data['pdfDownloadConfigs']['pdforientation'] = 'landscape';
        $data['pdfDownloadConfigs']['pdfelement'] = 'printToPdf_analytics_summary';

        //credit data goes here
        $data["date1"] = $date1;
        $data["date2"] = $date2;

        $myCredits = $this->CreditListings->get_credits($cData);

        //Get custom data points for DATES
        $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
        $dpRequest['dpType'] = 'date';
        $dpRequest['dpSection'] = 'credit_date_settings';
        $customDataPointDatesRaw = $this->CreditListings->get_data_points($dpRequest);
        $data['customDataPointDates'] = $customDataPointDatesRaw['dataPoints'];

        $creditDatesInput['credits'] = $myCredits['credits'];
        $creditDatesInput['customDataPointDates'] = $data['customDataPointDates'];
        $myCreditsProcessedDates = $this->CreditListings->groom_credit_dates($creditDatesInput);
        $data['credits'] = $myCreditsProcessedDates['credits'];
        $data['noData'] = (sizeof($myCreditsProcessedDates['credits']) > 0) ? false : true;

      }

      if($reportTab == "utilization_summary") {

        //PDF Download Configs
        $data['pdfDownloadConfigs']['pdfpageonetitle'] = 'Monetization Summary';
        $data['pdfDownloadConfigs']['pdfname'] = 'Utilization_Summary_' . date("Y-m-d", time());
        $data['pdfDownloadConfigs']['pdforientation'] = 'portrait';
        $data['pdfDownloadConfigs']['pdfelement'] = 'printToPdf_analytics_summary';

        //credit data goes here
        $cData['order'] = 'project_a_z';
        $credits = $this->CreditListings->get_credits($cData);

        $creditList = $credits['credits'];
        $data['noData'] = (sizeof($credits['credits']) > 0) ? false : true;
        $data['credits'] = $creditList;
        $data['creditsSummary'] = $credits['summary'];

        //
        $currYear = date('Y', time());
        $data['currYear'] = $currYear;
        $monetizationData = [];
        $monetizationData['projects'] = [];
        $monetizationData['summary'] = [];
        $monetizationData['summary']['utilization']['achievedPriorYears'] = 0;
        $monetizationData['summary']['utilization']['currentYear'] = 0;
        $monetizationData['summary']['utilization']['futureYears'] = 0;
        $monetizationData['summary']['utilization']['after5YearTimeline'] = 0;
        $monetizationData['summary']['utilizationFiveYearTimeline'] = [];
        $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear] = 0;
        $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 1] = 0;
        $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 2] = 0;
        $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 3] = 0;
        $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 4] = 0;

        foreach($creditList as $cl) {

          if(!array_key_exists($cl['projectName'], $monetizationData['projects'])) {
            $monetizationData['projects'][$cl['projectName']] = [];
            $thisProjectName = ($cl['projectName'] != "") ? $cl['projectName'] : "(no project name)";
            $monetizationData['projects'][$cl['projectName']]['title'] = ($this->cisession->userdata('dmaType') == 'advisor' || $this->cisession->userdata('dmaType') == 'shared') ? $cl['companyName'] . " - " . $thisProjectName : $thisProjectName;
            $monetizationData['projects'][$cl['projectName']]['programs'] = [];
            $monetizationData['projects'][$cl['projectName']]['utilization'] = [];
            $monetizationData['projects'][$cl['projectName']]['utilization']['achievedPriorYears'] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilization']['currentYear'] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilization']['futureYears'] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilization']['after5YearTimeline'] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'] = [];
            $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 1] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 2] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 3] = 0;
            $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 4] = 0;
          }

          if(!array_key_exists($cl['ProgramName'], $monetizationData['projects'][$cl['projectName']]['programs'])) {
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']] = [];
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['title'] = $cl['State'] . " - " . $cl['ProgramName'];
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['creditId'] = $cl['listingId'];
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization'] = [];
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['achievedPriorYears'] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['currentYear'] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['futureYears'] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['after5YearTimeline'] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationByYear'] = [];
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'] = [];
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 1] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 2] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 3] = 0;
            $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 4] = 0;

          }

          foreach($cl['trades'] as $tra) {

            $thisTradeDate = ($tra['tradeIsEstimated'] == 1) ? $tra['tradeDateEstimate'] : strtotime($tra['timeStamp']);
            $thisTradeYear = date('Y', $thisTradeDate);

            if(!array_key_exists($thisTradeYear, $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationByYear'])) {
              $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationByYear'][$thisTradeYear] = $tra['tradeSizeUSD'];
            } else {
              $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationByYear'][$thisTradeYear] += $tra['tradeSizeUSD'];
            }

            if($thisTradeYear < $currYear) {
              $monetizationData['summary']['utilization']['achievedPriorYears'] += $tra['tradeSizeUSD'];
              $monetizationData['projects'][$cl['projectName']]['utilization']['achievedPriorYears'] += $tra['tradeSizeUSD'];
              $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['achievedPriorYears'] += $tra['tradeSizeUSD'];
            } else {
              if($thisTradeYear == $currYear) {
                $monetizationData['summary']['utilization']['currentYear'] += $tra['tradeSizeUSD'];
                $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear] += $tra['tradeSizeUSD'];
                $monetizationData['projects'][$cl['projectName']]['utilization']['currentYear'] += $tra['tradeSizeUSD'];
                $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear] += $tra['tradeSizeUSD'];
                $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['currentYear'] += $tra['tradeSizeUSD'];
                $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear] += $tra['tradeSizeUSD'];
              } else {
                if($thisTradeYear > $currYear) {
                  $monetizationData['summary']['utilization']['futureYears'] += $tra['tradeSizeUSD'];
                  $monetizationData['projects'][$cl['projectName']]['utilization']['futureYears'] += $tra['tradeSizeUSD'];
                  $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilization']['futureYears'] += $tra['tradeSizeUSD'];
                  if($thisTradeYear == $currYear + 1) {
                    $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 1] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 1] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 1] += $tra['tradeSizeUSD'];
                  }
                  if($thisTradeYear == $currYear + 2) {
                    $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 2] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 2] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 2] += $tra['tradeSizeUSD'];
                  }
                  if($thisTradeYear == $currYear + 3) {
                    $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 3] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 3] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 3] += $tra['tradeSizeUSD'];
                  }
                  if($thisTradeYear == $currYear + 4) {
                    $monetizationData['summary']['utilizationFiveYearTimeline'][$currYear + 4] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['utilizationFiveYearTimeline'][$currYear + 4] += $tra['tradeSizeUSD'];
                    $monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationFiveYearTimeline'][$currYear + 4] += $tra['tradeSizeUSD'];
                  }
                }
              }
            }

          }

          /*
					usort($monetizationData['projects'][$cl['projectName']]['programs'][$cl['ProgramName']]['utilizationByYear'], function($a, $b) {
						return $a["updatedTime"], $b["updatedTime"];
					});
					*/

        }

        $data['monetizationData'] = $monetizationData;

      }

      if($reportTab == "advisor_fee_forecaster") {

        //PDF Download Configs
        $data['pdfDownloadConfigs']['pdfpageonetitle'] = 'Fee Forecast';
        $data['pdfDownloadConfigs']['pdfname'] = 'Fee_Forecast_' . date("Y-m-d", time());
        $data['pdfDownloadConfigs']['pdforientation'] = 'landscape';
        $data['pdfDownloadConfigs']['pdfelement'] = 'printToPdf_analytics_summary';

        $cRequest['startDate'] = $date1;
        $cRequest['endDate'] = $date2;
        $cRequest['shared'] = $this->cisession->userdata('primUserId');
        $data['credits'] = $this->CreditListings->get_advisor_payments($cRequest);
        $data['noData'] = (sizeof($data['credits']) > 0) ? false : true;

      }

      //Reset this value:
      $data['projects'] = $this->input->post('listProjects');

      $data['planData'] = $this->cisession->userdata('planData');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view('dashboard/analytics_report', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function insert_share() {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/dashboard');
    } else {

      //Check access to credit, get permissions - allow sharing if this party has editing capabilities (even if they are shared access)
      $permissions = $this->memberpermissions->checkCreditAccess($_POST['listingId']);
      //Check above ensures "edit" access to credit... then beyond that...
      //Allow the following to proceed: (1) if you are account owner, (2) you have share level access, (3)
      if($this->cisession->userdata('primUserId') == $this->cisession->userdata('userId') || $this->cisession->userdata('levelShareCredit') || $this->cisession->userdata('isParentDma') || ($permissions['shareView'] && $permissions['advisorFlag'])) {
        //allow to proceed
      } else {
        throw new \Exception('General fail');
      }

      //Get pending listing of this shared credit
      $credit = $this->CreditListings->get_credit_private($_POST['listingId']);

      //First, check if this is shared to OIX Advisors
      if(strtolower($_POST['shareEmail']) == $this->config->item("oix_advisors_mainAdmin_email")) {
        $oixAdvisors = true;
      } else {
        $oixAdvisors = false;
      }

      $shareEmail = strtolower($_POST['shareEmail']);

      //Get currently logged in user
      $existingUserCheck = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $emailIsAcceptedForShare = $this->DmaAccounts->isEmailValidForShare($shareEmail, $this->cisession->userdata('dmaId'));

      if($emailIsAcceptedForShare !== true) {

        //Do NOT create a share
        $this->session->set_flashdata('sameEmailDomainBlock', 1);

      } else {

        //Setup a few variables depending on if this is an existing user or not
        $existingMember = false;
        //Get user account from email
        $member = $this->Members_Model->get_dma_member_by_email($shareEmail);
        //If account exists, check if this member's DMA account has sharing feature
        if(isset($member['userId'])) {
          $existingMember = true;
          $shareEmail = $member['email'];
        }

        //Get basic user info
        $thisFirstName = ($existingMember) ? $member['firstName'] : $_POST["shareFirstName"];
        $thisLastName = ($existingMember) ? $member['lastName'] : $_POST["shareLastName"];
        $thisCompanyName = $_POST["shareCompanyName"];

        //Insert share
        $sType = $_POST["sType"];
        $sItemId = $_POST["listingId"];
        $sharedPrimId = null; //Set to empty until the person accepts the share (and then the DMA will be set, in case the shared user
        $sharedSecId = ($existingMember) ? $member['userId'] : null;
        $sharedPermEdit = $_POST["sharedPermEdit"];
        $sAdditionalInfo['sharedInviteFirstName'] = $thisFirstName;
        $sAdditionalInfo['sharedInviteLastName'] = $thisLastName;
        $sAdditionalInfo['sharedInviteCompanyName'] = $thisCompanyName;
        $sAdditionalInfo['sharedInviteEmail'] = $shareEmail;
        $sharerPrimId = $credit['listedBy']; //Shares always are "owned" by the owner of the credit
        $sId = $this->CreditListings->insert_share($sType, $sItemId, $sharedPrimId, $sharedSecId, $sharerPrimId, "", $sharedPermEdit, "", "", $sAdditionalInfo);
        //Insert audit trail - New Share
        $permissionText = ($this->input->post('sharedPermEdit') == 1) ? "Edit & View" : "View Only";
        $this->AuditTrail->insert_audit_item(161, $this->input->post('listingId'), '-', $permissionText . " (" . $thisFirstName . " " . $thisLastName . " of " . $thisCompanyName . ")", "");

        //If OIX Advisors share
        if($oixAdvisors) {
          //automatically mark it as a pending project
          $this->CreditListings->update_share_status($sId, 1);
          //Now mark it as auto accepted status
          $saUpdates['sStatus'] = 1; //Auto-accept it
          $saUpdates['sAcceptedDate'] = time();
          $this->CreditListings->update_share($sId, $saUpdates);
        }

        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";

        //Send Email notice to person shared to
        $emailData['updateType'] = "newShareCredit";
        $emailData['firstName'] = $thisFirstName;
        $emailData['dmaTitle'] = $thisCompanyName;
        $emailData['credit'] = $credit;
        $emailData['sharerName'] = $this->cisession->userdata("firstName") . ' ' . $this->cisession->userdata("lastName");
        $emailData['sharerDmaTitle'] = $this->cisession->userdata("dmaTitle");
        $emailData['newUser'] = false;
        $emailData['autoShare'] = false;
        $emailData['inviteData'] = [];
        if($oixAdvisors) {
          $emailData['welcomeNameTemplate'] = 0;
          $emailData['button'] = 0;
          $emailData['subject'] = 'New Project Submitted to OIX Advisors - ' . $this->cisession->userdata("dmaTitle") . ' - "' . $credit["stateCertNum"] . $projectNameExt . '"';
          $emailData['headline'] = 'New%20Pending%20Project';
          $emailData['oixAdvisors'] = true;
          $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $this->config->item("oix_admin_emails_array"), $emailData);
        } else {
          $emailData['welcomeNameTemplate'] = 1;
          $emailData['button'] = 0;
          $emailData['subject'] = $this->cisession->userdata("dmaTitle") . ' Shared a Tax Credit with you - "' . $credit["stateCertNum"] . $projectNameExt . '"';
          $emailData['headline'] = 'Tax%20Credit%20Shared%20With%20You';
          $emailData['oixAdvisors'] = false;
          if(!$existingMember) {
            //Mark as a new user
            $emailData['newUser'] = true;
            //Send invite
            $iData['iType'] = 'creditShare';
            $iData['iFirstName'] = $thisFirstName;
            $iData['iLastName'] = $thisLastName;
            $iData['iCompanyName'] = $thisCompanyName;
            $iData['iEmail'] = $shareEmail;
            $iData['sId'] = $sId;
            $newInviteId = $this->Members_Model->insert_invite($iData);
            //Get the invite we just created
            $emailData['inviteData'] = $this->Members_Model->get_invite($newInviteId);
          }
          //Send the share invitation email
          $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $shareEmail, $emailData);
        }

        //If this user is DMA
        if($this->cisession->userdata('level') == 4) {

          $creditData = $credit;
          $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
          //Insert message for just the credit holder (since this is a pending share)
          $msType = "update";
          $msAction = "share_new";
          $msListingId = $_POST['listingId'];
          $msBidId = "";
          $msTradeId = "";
          $msTitle = "Credit Shared with " . $thisFirstName . " " . $thisLastName . " of " . $thisCompanyName . " - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
          $msTitle2 = "";
          $msTitleShort = "Credit Shared with " . $thisFirstName . " " . $thisLastName . " of " . $thisCompanyName;
          $msTitle2Short = "";
          $msTitleShared = $msTitleShort;
          $msTitleSharedShort = $msTitleShort;
          $msContent = "";
          $msContent2 = "";
          $msPerspective = "seller";
          $msPerspective2 = "";
          $firstDmaMainUserId = $this->cisession->userdata('primUserId');
          $secondDmaMainUserId = "";
          $msUserIdCreated = $this->cisession->userdata('userId');
          $alertShared = false;
          $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        }

        //Centralized function to clean/prepare filter data
        $preparedFilterData = $this->CreditListings->prepareFilterData();
        //Centralized function build filter query parameters
        $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
        //Update cache for this credit
        $cData['listingId'] = $creditData['listingId'];
        $this->CreditListings->update_credit_cache($cData);

        if($oixAdvisors) {
          $this->session->set_flashdata('shareCreditInsertMessage', 11);
        } else {
          $this->session->set_flashdata('shareCreditInsertMessage', 1);
        }

      }

      //redirect
      redirect('/dashboard/credit/' . $_POST["listingId"] . "/shares");
    }

  }

  function heatmap($view = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['lnav_key'] = "heatmap";
    $data['tabArray'] = "heatmap";
    $data['current_tab_key'] = "";

    if(in_array('creditSharedHeatMap', $this->cisession->userdata('accessParentDataConfig'))) {

      $data["view"] = "parentcredits";

      //Get parent DMA info
      $parentDmaData = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('parentDmaId'));
      $data['parentDmaData'] = $parentDmaData;
      //Get all shared credits with parent
      $input['parentMainAdmin'] = $parentDmaData['mainAdmin'];
      $input['dmaId'] = $parentDmaData['dmaId'];
      $input['dmaMemberId'] = $parentDmaData['dmaMemberId'];
      $sharedCredits = $this->CreditListings->get_credits($input);
      $mapArray = $sharedCredits['credits'];
      $data['mapArray'] = $mapArray;

    } else {

      $data["view"] = "mycredits";

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);

      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($data);

      $myCredits = $this->CreditListings->get_credits($cData);
      $mapArray = $myCredits['credits'];
      $data['mapArray'] = $mapArray;

    }

    $data['planData'] = $this->cisession->userdata('planData');

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    if((!isset($data['planData']['heatMaps']) && !isset($data['planData']['allFeatures']))) {
      $this->load->view('includes/widgets/planBlockMessage');
    } else {
      $this->load->view("dashboard/heatmap", $data);
    }
    $this->load->view('includes/footer-2', $data);

  }

  private function process_loan_custom_data_points($request) {

    $creditId = $request['creditId'];
    $loanCDPs = $request['loanCDPs'];
    //get loan data points for this DMA
    $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
    $dpRequest['dpObjectType'] = 'loan';
    $cdps = $this->CreditListings->get_data_points($dpRequest);
    $customDataPoints = $cdps['dataPoints'];

    //Loop through and update them, if set
    foreach($customDataPoints as $cdp) {
      if(isset($loanCDPs[$cdp['dpValue']])) {

        $thisCdpv = $loanCDPs[$cdp['dpValue']];
        if($cdp['type'] == 'date') {
          //convert date to unix
          $thisCdpv = ($thisCdpv != "") ? strtotime($thisCdpv) : null;
        }
        if($cdp['type'] == 'currencyNoDecimal') {
          //
          $thisCdpv = (float)preg_replace('/[^0-9.]*/', '', $thisCdpv);
          $thisCdpv = ($thisCdpv == 0) ? null : round($thisCdpv);
        }
        //save it
        $cvRequest['dpId'] = $cdp['dpId'];
        $cvRequest['listingId'] = $creditId;
        $cvRequest['value'] = $thisCdpv;
        $this->CreditListings->update_custom_data_point_value($cvRequest);

      }
    }

  }

  function insert_loan() {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/dashboard');
    } else {

      //Check access to credit, get permissions, add to $data
      $this->memberpermissions->checkCreditAccess($_POST["listingId"], 0, 'sharedOnly');

      //Insert loan
      $loanId = $this->CreditListings->insert_loan();
      //If Share exists, update the advisor status
      if($_POST['sId'] > 0) {
        $this->CreditListings->update_share_advisor_status();
      }

      //update custom data points on loan
      $loanCDPs = [];
      foreach($postData as $k => $v) {
        if(substr($k, 0, 7) == 'custom_') {
          $loanCDPs[$k] = $v;
        }
      }
      if(count($loanCDPs) > 0) {
        $lCdpRrequest['creditId'] = $_POST["listingId"];
        $lCdpRrequest['loanCDPs'] = $loanCDPs;
        $this->process_loan_custom_data_points($lCdpRrequest);
      }

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $this->input->post('listingId');
      $this->CreditListings->update_credit_cache($cData);

      //Setup success message
      if($_POST['loanType'] == 1) {
        $this->session->set_flashdata('loanSuccess', 1);
      } else {
        if($_POST['loanType'] == 2) {
          $this->session->set_flashdata('advisorSuccess', 1);
        }
      }
      //redirect
      redirect('/dashboard/credit/' . $_POST["listingId"]);

    }
  }

  function update_loan() {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/dashboard');
    } else {

      $postData = $_POST;

      //Check access to credit, get permissions, add to $data
      $this->memberpermissions->checkCreditAccess($_POST["listingId"], 0, 'sharedOnly');

      //update loan
      $loanId = $this->CreditListings->update_loan();
      //If Share exists, update the advisor status
      if($_POST['sId'] > 0) {
        $this->CreditListings->update_share_advisor_status();
      }

      //update custom data points on loan
      $loanCDPs = [];
      foreach($postData as $k => $v) {
        if(substr($k, 0, 7) == 'custom_') {
          $loanCDPs[$k] = $v;
        }
      }
      if(count($loanCDPs) > 0) {
        $lCdpRrequest['creditId'] = $_POST["listingId"];
        $lCdpRrequest['loanCDPs'] = $loanCDPs;
        $this->process_loan_custom_data_points($lCdpRrequest);
      }

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $this->input->post('listingId');
      $this->CreditListings->update_credit_cache($cData);

      //Setup success message
      if($_POST['loanType'] == 1) {
        $this->session->set_flashdata('loanSuccess', 2);
      } else {
        if($_POST['loanType'] == 2) {
          $this->session->set_flashdata('advisorSuccess', 2);
        }
      }
      //redirect
      redirect('/dashboard/credit/' . $_POST["listingId"]);
    }
  }

  function repay_loan() {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/dashboard');
    } else {

      //Check access to credit, get permissions, add to $data
      $this->memberpermissions->checkCreditAccess($_POST["listingId"], 0, 'sharedOnly');

      //update loan
      $loanId = $this->CreditListings->repay_loan();

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $this->input->post('listingId');
      $this->CreditListings->update_credit_cache($cData);

      //Setup success message
      $this->session->set_flashdata('loanSuccess', 3);
      //redirect
      redirect('/dashboard/credit/' . $_POST["listingId"]);
    }
  }

  function unpay_loan() {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/dashboard');
    } else {

      //Check access to credit, get permissions, add to $data
      $this->memberpermissions->checkCreditAccess($_POST["listingId"], 0, 'sharedOnly');

      //update loan
      $loanId = $this->CreditListings->unpay_loan();

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $this->input->post('listingId');
      $this->CreditListings->update_credit_cache($cData);

      //Setup success message
      $this->session->set_flashdata('loanSuccess', 4);
      //redirect
      redirect('/dashboard/credit/' . $_POST["listingId"]);
    }
  }

  function delete_loan() {

    if(!$this->tank_auth->is_logged_in()) {
      $url = $_SERVER['REQUEST_URI'];
      redirect('/dashboard');
    } else {

      //Check access to credit, get permissions, add to $data
      $this->memberpermissions->checkCreditAccess($_POST["listingId"], 0, 'sharedOnly');

      //update loan
      $loanId = $this->CreditListings->delete_loan();

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $this->input->post('listingId');
      $this->CreditListings->update_credit_cache($cData);

      //Setup success message
      $this->session->set_flashdata('loanSuccess', 5);
      //redirect
      redirect('/dashboard/credit/' . $_POST["listingId"]);
    }
  }

  /////////////////////////////////////////////
  //////// End Admin Panel Copy Over ////////
  /////////////////////////////////////////////

  function credit_harvests($id, $harvestId = "") {

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "owner";
    $data['filterTag'] = "none";

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['current_tab_key'] = "harvested";
    $data['lnav_key'] = "";

    $data['itemType'] = "credit";
    $data['itemNameCap'] = "Credit";
    $data['itemNameLow'] = "credit";

    $data['currentPage'] = $this;

    $data['details'] = $this->CreditListings->get_credit_private($id);
    $data['program'] = $data['details'];

    //Check access to credit, get permissions, add to $data
    $permissions = $this->memberpermissions->checkCreditAccess($id);
    $data = array_merge($data, $permissions);

    $data['active'] = $this->CreditListings->get_active_listing($id);
    //if($data['active']==NULL || $data['active']=="") { $data['creditForSale'] = FALSE; } else { $data['creditForSale'] = TRUE; }

    //$data['openBid'] = $this->CreditListings->get_openbid_of_creditoffered($id);

    //$data['bidactivity'] = $this->CreditListings->get_market_on_listing_new($id);
    $trades = $this->Trading->get_trades_on_listing($id);
    $data['trades'] = $trades['trades'];

    $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
    $data = $data + $dmamembers_credit_access;

    //Workflow
    $data['creditWorkflow'] = $this->Workflow->get_workflow('', 'credit', $id, 'workflow');
    $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), '');

    $data['totalTradeAmount'] = $this->Trading->get_total_trade_amount_on_listing($id);
    $data['totalAmountAllHarvest'] = 0;
    $data['minCreditEstVal'] = $data['totalTradeAmount']['totalTradeAmount'] + $data['totalAmountAllHarvest'];

    //Hard coding this for now since people will be always submitting NEW credits - not offers on bids
    //$data['workflow'] = "newOffer";

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_harvests', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view('dashboard/mycredit_harvests', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_bids($id = '') {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['current_tab_key'] = "bids";
    $data['lnav_key'] = "";

    $data['itemType'] = "credit";
    $data['itemNameCap'] = "Credit";
    $data['itemNameLow'] = "credit";

    $data['details'] = $this->CreditListings->get_credit_private($id);
    $data['program'] = $data['details'];
    //$data['openBid'] = $this->CreditListings->get_openbid_of_creditoffered($id);

    //Check access to credit, get permissions, add to $data
    $permissions = $this->memberpermissions->checkCreditAccess($id);
    $data = array_merge($data, $permissions);

    $data['active'] = $this->CreditListings->get_active_listing($id);
    if($data['active'] == null || $data['active'] == "") {
      $data['creditForSale'] = false;
    } else {
      $data['creditForSale'] = true;
    }

    $data['bidactivity'] = $this->CreditListings->get_market_on_listing_new($id);
    $trades = $this->Trading->get_trades_on_listing($id);
    $data['trades'] = $trades['trades'];

    $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
    $data = $data + $dmamembers_credit_access;

    //Workflow
    $data['creditWorkflow'] = $this->Workflow->get_workflow('', 'credit', $id, 'workflow');
    $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit');

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('dashboard/mycredit_bids', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('dashboard/mycredit_bids', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_bid($id = '', $bidId = '') {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['current_tab_key'] = "bids";
    $data['lnav_key'] = "";

    $data['itemType'] = "credit";
    $data['itemNameCap'] = "Credit";
    $data['itemNameLow'] = "credit";

    $data['buyOrder'] = false;
    $data['newBid'] = false;
    $data['marketLive'] = true;

    $data['details'] = $this->CreditListings->get_credit_private($id);
    $data['program'] = $data['details'];

    //Check access to credit, get permissions, add to $data
    $permissions = $this->memberpermissions->checkCreditAccess($id);
    $data = array_merge($data, $permissions);

    $data['active'] = $this->CreditListings->get_active_listing($id);
    if($data['active'] == null || $data['active'] == "") {
      $data['creditForSale'] = false;
    } else {
      $data['creditForSale'] = true;
    }

    $data['bid'] = $this->BidMarket->get_bid_by_id($bidId);
    $bidIsTrade = $this->Trading->get_trade_of_bid($bidId);
    if(sizeof($bidIsTrade) > 0) {
      $data['existingTradeId'] = $bidIsTrade[0]['tradeId'];
    } else {
      $data['existingTradeId'] = 0;
    }

    if(sizeof($data['bid']) > 0) {
      $data['bidDeleted'] = ($data['bid']['bidDeleteMarker'] == 1) ? true : false;
    } else {
      $data['bidDeleted'] = true;
    }

    $trades = $this->Trading->get_trades_on_listing($id);
    $data['trades'] = $trades['trades'];

    $data['transactions'] = $this->Trading->get_transactions_of_bid($bidId);
    if(sizeof($data['transactions']) > 1) {
      $data['multibuyer'] = true;
    } else {
      $data['multibuyer'] = false;
    };

    //Workflow
    $data['creditWorkflow'] = $this->Workflow->get_workflow('', 'credit', $id, 'workflow');
    $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit');

    $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
    $data = $data + $dmamembers_credit_access;

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view('includes/credit_header', $data);
      $this->load->view('dashboard/mycredit_bid', $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('dashboard/mycredit_bid', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

}
