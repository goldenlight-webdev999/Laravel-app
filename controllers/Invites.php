<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Invites extends CI_Controller {
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
    $this->load->model('Taxpayersdata');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Email_Model');
    $this->load->library('filemanagementlib');

    try {
      $this->redis = new Predis\Client([
                                           'scheme' => $this->config->item('AWS_elasticache_scheme'),
                                           'host'   => $this->config->item('AWS_elasticache_endpoint'),
                                           'port'   => 6379,
                                       ]);
    } catch(Exception $ex) {
      $this->logger->error("Error connection to redis from Invites Controller with message: " . $ex->getMessage());
    }

  }

  function invite($iHash) {

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    $data['iType'] = $data['invite']['iType'];

    $data['dmaInfo'] = $this->DmaAccounts->get_dma_account_by_id($data['invite']['iDmaId']);
    $data['memberInfo']['mobilePhone'] = "";
    $data['isSSO'] = false;

    $data['pwMinLength'] = ($data['invite']['iPwCharacterCountReq'] > 8) ? $data['invite']['iPwCharacterCountReq'] : ($data['invite']['iType'] == "signature" ? 6 : 8);
    $data['loginPage'] = $data['invite']['iType'] . "Accept";

    $ssoSuccess = $this->cisession->userdata('sso_auth_complete');
    //TODO: require SSO init here regardless
    if(strlen($data['invite']['external_auth_type']) > 0 && $data['iType'] !== 'creditShare' && $data['iType'] !== 'signatureCustom') {
      if((!isset($ssoSuccess) || $ssoSuccess != true)) {
        $data['ssoAuthComplete'] = false;
        $ssoRequestInfo = [
            'id'           => '',
            'email'        => $data['invite']['iEmail'],
            'authType'     => $data['invite']['external_auth_type'],
            'authProvider' => $data['invite']['external_auth_provider'],
            'context'      => 'inviteAccept',
            'nextUrl'      => '/invites/invite/' . $iHash,
        ];

        $ssoRequestHash = sha1(microtime(true) . mt_rand(10000, 90000));
        $this->redis->hmset($ssoRequestHash, $ssoRequestInfo);
        $this->redis->expire($ssoRequestHash, 60 * 5); //TODO: agree on request TTL and implement flow when callback after expired

        redirect('sso/init?requestId=' . urlencode($ssoRequestHash));
      } else {
        $data['isSSO'] = true;
        $data['ssoAuthComplete'] = true;
        $this->cisession->set_userdata(['sso_auth_complete' => false]);
      }
    }

    if($this->tank_auth->is_logged_in() && $data['invite']['iType'] != 'signatureCustom') {
      $data['myEmailAddress'] = $this->Members_Model->get_member_email($this->cisession->userdata('userId'));
      $dmaInfo = $data['dmaInfo'];
      if($data['iType'] == "dmaMember" || $data['iType'] == "creditShare") {
        $data['memberInfo'] = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
        $memberIPs = $this->Members_Model->get_member_ip_addresses($this->cisession->userdata('userId'));
        $data['ipAddressStatus'] = "new";
        foreach($memberIPs as $mIp) {
          $thisIpAddress = $this->Members_Model->get_ip();
          if($mIp['ipAddress'] == $thisIpAddress) {
            $data['ipAddressStatus'] = "existing";
            if($mIp['mfaStatus'] == 1) {
              $data['ipAddressStatus'] = "verified";
            }
          }
        }
      }
    } else {
      $data['myEmailAddress']['email_address'] = "";
    }

    $data['emailExists'] = $this->Members_Model->check_if_user_email_exists($data['invite']['iEmail']);

    $data['data'] = $data;

    $this->load->view('includes/header_invite', $data);
    $this->load->view("invites/invite", $data);
    $this->load->view('includes/footer_invite', $data);

  }

  function signature($iHash, $dsId, $dsAccessTime) {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/');
      }

    }

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    $rDocToSign["dsEnvelopeId"] = $data['docToSign']['dsEnvelopeId'];
    $rDocToSign["dsId"] = $dsId;
    $rDocToSign["dsFullName"] = $data['invite']['iFirstName'] . " " . $data['invite']['iLastName'];
    $rDocToSign["dsEmail"] = $data['invite']['iEmail'];
    $data['docuSignData'] = $this->filemanagementlib->get_docuSign_doc($rDocToSign);

    $data['iType'] = 'signatureCustom';

    //Access:
    //if invite exists, doc to sign exists and the access time is set...
    if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0 && $dsAccessTime > 0) {

      //If this invite is deleted, try forwarding to download signed doc page
      if($data['invite']['iDeleteMarker'] == 1) {
        redirect('/invites/signed_document/' . $iHash . '/' . $dsId . '/' . $dsAccessTime);
      }

      //If this link is expired, show error
      $data['linkExpired'] = ($data['docToSign']['dsAccessTime'] < time()) ? true : false;

      $data['dmaInfo'] = $this->DmaAccounts->get_dma_account_by_id($data['invite']['iDmaId']);

      $data['listingId'] = $data['docToSign']['dsListingId'];
      $data['docFirstName'] = $data['invite']['iFirstName'];
      $data['docLastName'] = $data['invite']['iLastName'];
      $data['docDmaTitle'] = null;
      $data['filterTag'] = null;
      $data['docaccess'] = null;
      $data['tradeId'] = null;
      $data['transactionId'] = null;
      $data['permEdit'] = null;

      $data['signingCustomDoc'] = true;
      $data['docVersion'] = 'signedVersion';

      $data['signed_docs'] = $this->Docs->get_documents("signed_doc", $data['listingId'], "", "", "", "", 1, 1, "new_to_old", $dsId);

      $this->load->view('includes/header_invite', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view("invites/signature", $data);
      $this->load->view('includes/footer_invite', $data);

    } else {

      redirect('/');

    }

  }

  function signed_document($iHash, $dsId, $dsAccessTime) {

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);
    $data['iType'] = 'signatureCustom';

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    //Access:
    //xthis page is only accessible for dsDownloadExpires amount of time after signature is submitted
    if($data['docToSign']['dsAccessTime'] > time() || $data['docToSign']['dsDownloadExpires'] > time()) {

      $data['linkExpired'] = false;

      $data['listingId'] = $data['docToSign']['dsListingId'];
      $data['docFirstName'] = $data['invite']['iFirstName'];
      $data['docLastName'] = $data['invite']['iLastName'];
      $data['docDmaTitle'] = null;
      $data['filterTag'] = null;
      $data['docaccess'] = null;
      $data['tradeId'] = null;
      $data['transactionId'] = null;
      $data['permEdit'] = null;

      $data['isDocuSign'] = ($data['docToSign']['dsSignedVersionFileId'] == null) ? true : false;

      $data['signingCustomDoc'] = true;
      $data['docVersion'] = 'signedVersion';

      $data['signed_docs'] = $this->Docs->get_documents("signed_doc", $data['docToSign']['dsListingId'], "", "", "", "", 1, 1, "new_to_old");

      $this->load->view('includes/header_invite', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view("invites/signed_doc", $data);
      $this->load->view('includes/footer_invite', $data);

    } else {

      $data['linkExpired'] = true;
      $this->load->view("invites/signed_doc", $data);

    }

  }

  function invites() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['tabArray'] = "dmamembers";
    $data['current_tab_key'] = "allinvites";
    $data['lnav_key'] = "dmamembers";

    $data['dmaInvites'] = $this->Members_Model->get_dma_invites($this->cisession->userdata('dmaId'), 'dmaMember');

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    $this->load->view("dmamembers/invites", $data);
    $this->load->view('includes/footer-2', $data);
  }

  function edit_invite($inviteId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['lnav_key'] = "dmamembers";
    $data['current_tab_key'] = "";

    $data['page_version'] = "edit_invite";

    $data['inviteId'] = $inviteId;

    $data['invite'] = $this->Members_Model->get_invite($inviteId);
    $data['dmamember'] = $data['invite'];
    $data['dmamember']['firstName'] = $data['invite']['iFirstName'];
    $data['dmamember']['lastName'] = $data['invite']['iLastName'];
    $data['dmamember']['email'] = $data['invite']['iEmail'];

    $this->load->view('includes/left_nav', $data);
    //$this->load->view('includes/taxpayer_header', $data);
    $this->load->view("dmamembers/add", $data);
    $this->load->view('includes/footer-2', $data);
  }

  function save_invite($inviteId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->Members_Model->save_invite($inviteId);

    $this->session->set_flashdata('saveSuccess', 1);
    redirect('dmamembers/invites');

  }

  function reset_invite_link($iHash) {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

      $redirectURL = '/trades/pending_signatures_payments';
      $this->session->set_flashdata('inviteResetSuccess', 1);

    } else {

      //Check if MEMBER is logged in
      if($this->tank_auth->is_logged_in()) {
        redirect('/');
      }

      $redirectURL = '/invites/invite_reset/';

    }

    //Get existing invite
    $inviteData = $this->Members_Model->get_invite_by_hash($iHash);
    //Mark existing invite as deleted
    $this->Members_Model->unset_invite($inviteData['inviteId']);

    //Create new invite
    $newInviteId = $this->Members_Model->insert_reset_invite($inviteData);

    //Get the invite we just created
    $data['invite'] = $this->Members_Model->get_invite($newInviteId);

    //Collect data for email
    $data['iEmail'] = $data['invite']['iEmail'];
    $data['iFirstName'] = $data['invite']['iFirstName'];
    $data['iLastName'] = $data['invite']['iLastName'];
    $data['firstName'] = $data['invite']['iFirstName'];
    $data['lastName'] = $data['invite']['lastName'];
    $data['iExpires'] = $data['invite']['iExpires'];
    $data['inviteId'] = $data['invite']['inviteId'];
    $data['iHash'] = $data['invite']['iHash'];
    $data['title'] = $data['invite']['title'];
    $data['dmaGTitle'] = $data['invite']['dmaGTitle'];

    //Invite type
    if($data['invite']['iType'] == 'signature') {
      $data['updateType'] = 'reset_signature';
      $subject = 'New OIX Signature Link';
      $data['headline'] = 'New%20OIX%20Signature%20Link';
      $data['welcomeNameTemplate'] = 1;
      $data['button'] = null;
    } else {
      if($data['invite']['iType'] == 'creditShare') {
        $data['updateType'] = 'reset_creditShare';
        $subject = 'New OIX Share Link';
        $data['headline'] = 'New%20OIX%20Share%20Link';
        $data['welcomeNameTemplate'] = 1;
        $data['button'] = null;
      } else {
        $data['updateType'] = 'reset_dmaMember';
        $subject = 'New Admin Link - ' . $data['title'];
        $data['headline'] = 'Join%20as%20Admin%20User%20Now';
        $data['welcomeNameTemplate'] = 1;
        $data['button'] = null;
      }
    }

    //Send email
    $this->Email_Model->_send_email('member_template_1', $subject, $data['iEmail'], $data);

    redirect($redirectURL . $iHash);

  }

  function delete_invite($inviteId) {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->Members_Model->delete_invite($inviteId);

    $this->session->set_flashdata('deleteSuccess', 1);
    redirect('dmamembers/invites');

  }

  function accept_invite() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Get the invite we just accepted
    $data['invite'] = $this->Members_Model->get_invite($this->input->post('inviteId'));
    //Get the current logged in user
    $data['loggedInUserData'] = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
    //Make sure logged in user and email address on invitation match
    if(strtolower($data['invite']['iEmail']) != strtolower($data['loggedInUserData']['email'])) {
      echo "<p>Error: Either this invitation does not exist, or you do not have permission to access it.</p>";
      throw new \Exception('General fail');
    }

    //Collect data for email
    $data['email'] = $data['invite']['email'];
    $data['iEmail'] = $data['invite']['iEmail'];
    $data['iFirstName'] = $data['invite']['iFirstName'];
    $data['iLastName'] = $data['invite']['iLastName'];
    $data['firstName'] = $data['invite']['firstName'];
    $data['lastName'] = $data['invite']['lastName'];
    $data['inviteId'] = $data['invite']['inviteId'];
    $data['title'] = $data['invite']['title'];
    $data['dmaGTitle'] = $data['invite']['dmaGTitle'];

    //Take the action based on which iType the invite is
    if($this->input->post('iType') == 'dmaMember') {

      $data['dmaInfo'] = $this->DmaAccounts->get_dma_account_by_id($data['invite']['iDmaId']);
      $dmaInfo = $data['dmaInfo'];

      $memberIPs = $this->Members_Model->get_member_ip_addresses($this->cisession->userdata('userId'));

      $this->Members_Model->insert_dmamember($this->input->post('inviteId'));

      //If this is an invitation to a PARENT DMA Account, then let's automatically create admin users for this person in each child account and flag them as "Parent Admin"
      if($dmaInfo['isParentDma'] == 1) {
        $childAccounts = $this->DmaAccounts->get_child_accounts_of_dma_account($data['invite']['iDmaId']);
        foreach($childAccounts as $ca) {
          $request['dmaMUserId'] = $this->cisession->userdata('userId');
          $request['dmaMDmaId'] = $ca['dmaId'];
          $request['dmaMGroupId'] = $data['invite']['iContent1'];
          $request['dmaMParentAdminFlag'] = 1;
          $request['dmaMAddedBy'] = $data['invite']['iFromUserId'];
          $request['dmaMJoinDate'] = time();
          $this->Members_Model->create_dmamember($request);
        }
      }

      $this->tank_auth->set_member_to_dma_status();

      $myDmaAccounts = $this->DmaAccounts->get_my_dma_accounts($this->cisession->userdata('userId'));
      if(sizeof($myDmaAccounts) > 1) {
        $this->tank_auth->set_member_to_multi_dma();
      }

      //Send a welcome email to new user
      $this->Email_Model->_send_email('invite_dma_welcome', 'Welcome to the ' . $dmaInfo['title'] . ' OIX System', $data['iEmail'], $data);

      //Send confirmation to inviter that invite has been accepted
      $this->Email_Model->_send_email('invite_dmamember_accepted', 'Your OIX Admin User Invitation Was Accepted', $data['email'], $data);

      $this->session->set_flashdata('insertSuccess', 1);
      redirect('dashboard/myaccounts');

    } else {
      if($this->input->post('iType') == 'creditShare') {

        //Get logged in user
        $thisMember = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));

        //Attach this user to the share this invitation is attached to
        $sRequest['sharedSecId'] = $this->cisession->userdata('userId');
        $this->CreditListings->update_share($data['invite']['iContent1'], $sRequest);

        //Create a DMA account for this new user
        $dmaRequest['title'] = $thisMember['companyName'];
        $dmaRequest['mainAdmin'] = $this->cisession->userdata('userId');
        $dmaRequest['listed'] = 0;
        $dmaRequest['status'] = 1;
        $dmaRequest['planId'] = 0;
        $dmaRequest['dmaType'] = 'shared';
        $dmaRequest['planHarvestedCredit'] = 1;
        $dmaRequest['planCalendar'] = 1;
        $dmaRequest['planShareCredit'] = 1;
        $dmaRequest['planWorkflowTools'] = 1;
        $dmaRequest['planCompliance'] = 1;
        $dmaRequest['planSharedCredits'] = 1;
        $dmaRequest['planInternalTransfer'] = 1;
        $dmaRequest['planExternalTransfer'] = 1;
        $dmaId = $this->DmaAccounts->create_dma_account($dmaRequest);

        //Get the new DMA account info
        $dmaData = $this->DmaAccounts->get_dma_account_by_id($dmaId);

        //Then add this user as the main admin (dmamembers) on the account
        $dmaMemberRequest['dmaMUserId'] = $this->cisession->userdata('userId');
        $dmaMemberRequest['dmaMDmaId'] = $dmaId;
        $dmaMemberRequest['dmaMGroupId'] = 1;
        $this->Members_Model->create_dmamember($dmaMemberRequest);

        //Mark user's session as DMA
        $this->tank_auth->set_member_to_dma_status();

        //Mark invite as deleted
        $this->Members_Model->delete_invite($this->input->post('inviteId'));

        //Update to proper DMA status and company name
        $this->Members_Model->update_member_to_dma($this->cisession->userdata('userId'), $dmaData['title']);

        //Then redirect to my accounts (where they will be first met with the share accept screen)
        redirect('dashboard/myaccounts/3');

      } else {
        if($this->input->post('iType') == 'signature') {

          if($this->input->post('sigPerspective') == 'buy') {
            //Get the data about this transaction to build the redirect URL
            $t = $this->Trading->get_transaction_by_id($this->input->post('tId'));
            //Connect this taxpayer to this trade
            $this->Trading->connect_taxpayer_to_transaction($t['transactionId'], $this->cisession->userdata('userId'));
            //Build re-direct URL
            $sigUrl = 'dashboard/credit/' . $t['listingId'] . '/purchase_sign/' . $t['tradeId'] . '/transaction/' . $t['transactionId'];

          } else {

            //Get the data about this trade to build the redirect URL
            $t = $this->Trading->get_trade($this->input->post('tId'));
            $t = $t['trade'];
            //Connect this taxpayer to this transaction
            $this->Trading->connect_taxpayer_to_trade($t['tradeId'], $this->cisession->userdata('userId'));
            //Build re-direct URL
            $sigUrl = 'dashboard/credit/' . $t['listingId'] . '/sale_sign/' . $t['tradeId'];
          }

          //This invite has been accepted, so delete it
          $this->Members_Model->delete_invite($this->input->post('inviteId'));

          //Only send a welcome email - Do no send email to DMA admin, since we'll send it when the signer actually signs the PSA
          $this->Email_Model->_send_email('invite_signer_welcome', 'Welcome to the Online Incentives Exchange (OIX)', $data['iEmail'], $data);

          //send the user to the right signature page
          redirect($sigUrl);

        }
      }
    }

  }

  function extend_invite_post() {

    //This is for OIX Admin to send cURL request
    $json = file_get_contents('php://input');
    $requestArray = json_decode($json, true);

    $this->Members_Model->extend_invite($requestArray['inviteId']);

    return true;

  }

  function invite_reset($iHash) {

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    $data['iType'] = $data['invite']['iType'];

    $data['dmaInfo'] = $this->DmaAccounts->get_dma_account_by_id($data['invite']['iDmaId']);

    $this->load->view('includes/header_invite', $data);
    $this->load->view("invites/invite_reset", $data);
    $this->load->view('includes/footer_invite', $data);

  }

}
