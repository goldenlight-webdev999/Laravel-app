<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class DmaAccounts extends CI_Model {
  private $pending_table_name = 'PendingListings';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $this->pending_table_name;
    $this->load->library(['session']);

  }

  function get_dma_member_levels_for_member($dmaId, $userId) {

    $this->db->select('dmaMembers.*, dmaGroups.*, dmaAccounts.*');
    $this->db->where('dmaMUserId', $userId);
    $this->db->where('dmaMDmaId', $dmaId);
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("dmaGroups", "dmaMembers.dmaMGroupId = dmaGroups.dmaGroupId", 'left');

    $query = $this->db->get();

    $return = $query->result_array();

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_admin_user_levels() {

    $this->db->select('dmaGroups.*');
    $this->db->from('dmaGroups');
    $this->db->where('dmaGroupDeleteMarker', null);
    $query = $this->db->get();

    $return = $query->result_array();

    return $return;

  }

  function get_my_dma_accounts($userId) {

    $this->db->select('dmaMembers.*, dmaAccounts.*, dmaGroups.*');
    $this->db->where('dmaMUserId', $userId);
    $this->db->where('dmaMembers.dmaMStatus', 1);
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("dmaGroups", "dmaMembers.dmaMGroupId = dmaGroups.dmaGroupId", 'left');
    $this->db->order_by("dmaAccounts.title", "ASC");

    $query = $this->db->get();

    return $query->result_array();

  }

  function get_dma_accounts_historically_active() {

    $this->db->select('dmaAccounts.*');
    $this->db->where('planId', 1);
    $this->db->from('dmaAccounts');
    $this->db->order_by("dmaAccounts.dmaId", "ASC");

    $query = $this->db->get();

    return $query->result_array();

  }

  function get_dma_members($dmaId, $status = "") {

    $this->db->select('dmaMembers.*, dmaAccounts.*, dmaGroups.*, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.userId, Accounts.last_login');
    $this->db->where('dmaMDmaId', $dmaId);
    if($status >= 0) {
      $this->db->where('dmaMStatus', $status);
    }
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "dmaMembers.dmaMUserId = Accounts.userId", 'left');
    $this->db->join("dmaGroups", "dmaGroups.dmaGroupId = dmaMembers.dmaMGroupId", 'left');

    $this->db->order_by("Accounts.lastName", "ASC");

    $query = $this->db->get();

    return $query->result_array();

  }

  function create_dma_account($request) {

    //Make sure minimums are set
    if($request['title'] != "" && $request['mainAdmin'] > 0) {

      $data = [];
      foreach($request as $k => $v) {
        $data[$k] = $v;
      }
      $data['created'] = time();

      $this->db->insert('dmaAccounts', $data); //Inserting with "Main Admin" set as "primary user" (this is legacy)

      $newDmaId = $this->db->insert_id();

      $this->db->where('dmaId', $newDmaId);
      $this->db->update('dmaAccounts', ['mainAdmin' => $newDmaId, 'primary_account_id' => $request['mainAdmin']]); //Then immediately shifting values to correct for new structure

      return $newDmaId;

    }

  }

  function getValidDomainsForDMA($dmaId) {
    $this->db->select('dma_admin_allowed_domain.hostname');
    $this->db->where('dma_admin_allowed_domain.dma_id', $dmaId);
    $this->db->from('dma_admin_allowed_domain');
    $this->db->order_by('dma_admin_allowed_domain.hostname');

    $query = $this->db->get();

    return $query->result_array();
  }

  function isEmailValidForShare($email, $dmaId) {
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return false;
    }

    $emailParts = explode('@', $email);
    $emailDomain = $emailParts[count($emailParts) - 1];

    $this->db->select('dma_admin_allowed_domain.id');
    $this->db->where('dma_admin_allowed_domain.dma_id', $dmaId);
    $this->db->where('dma_admin_allowed_domain.hostname', $emailDomain);
    $this->db->from('dma_admin_allowed_domain');

    $query = $this->db->get();

    $result = $query->row_array();
    if(!isset($result['id'])) {
      return true;
    }

    $this->db->reset_query();
    $this->db->where('dma_admin_allowed_domain.dma_id', $dmaId);
    $this->db->from('dma_admin_allowed_domain');
    $numAllowedDomains = $this->db->count_all_results();

    return $numAllowedDomains === 0 && $this->config->item('environment') === 'DEV';
  }

  function isEmailValidForDMA($email, $dmaId) {
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return false;
    }

    $emailParts = explode('@', $email);
    $emailDomain = $emailParts[count($emailParts) - 1];

    $this->db->select('dma_admin_allowed_domain.id');
    $this->db->where('dma_admin_allowed_domain.dma_id', $dmaId);
    $this->db->where('dma_admin_allowed_domain.hostname', $emailDomain);
    $this->db->from('dma_admin_allowed_domain');

    $query = $this->db->get();

    $result = $query->row_array();
    if(isset($result['id'])) {
      return true;
    }

    $this->db->reset_query();
    $this->db->where('dma_admin_allowed_domain.dma_id', $dmaId);
    $this->db->from('dma_admin_allowed_domain');
    $numAllowedDomains = $this->db->count_all_results();

    return $numAllowedDomains === 0 && $this->config->item('environment') === 'DEV';
  }

  function insert_dma_account_for_customer($request) {

    $data = [
        'title'              => $request['companyName'],
        'primUserId'         => $request['cusUserId'],
        'primary_account_id' => $request['cusUserId'],
        'dmaType'            => $request['dmaType'],
        'listed'             => 0,
        'status'             => 0,
        'created'            => time(),
    ];

    $this->db->insert('dmaAccounts', $data);
    $newDmaId = $this->db->insert_id();

    $this->db->where('dmaId', $newDmaId);
    $this->db->update('dmaAccounts', ['mainAdmin' => $newDmaId]);

    return $newDmaId;

  }

  function update_dma_account_for_customer($cusUserId) {

    $data = [
        'title' => $this->input->post('companyName'),
    ];

    $this->db->where('dmaAccounts.primary_account_id', $cusUserId);
    $this->db->update('dmaAccounts', $data);

  }

  function update_dma_account_payment_instructions($dmaId, $request) {

    $data = [
        'dmaWireInstructions' => $request['dmaWireInstructions'],
    ];

    $this->db->where('dmaAccounts.dmaId', $dmaId);
    $this->db->update('dmaAccounts', $data);

  }

  function update_dma_account_settings($dmaId, $request) {

    $data = [
        'daysDefaultInactiveLimit' => $request['daysDefaultInactiveLimit'],
        'pwDefaultCharacterCount'  => $request['pwDefaultCharacterCount'],
        'pwDefaultResetDays'       => $request['pwDefaultResetDays'],
        'pwDefaultReuseCount'      => $request['pwDefaultReuseCount'],
        'pwDefaultReuseDays'       => $request['pwDefaultReuseDays'],
    ];

    if(strlen($request['idle_timeout_minutes']) > 0) {
      $data['idle_timeout_minutes'] = $request['idle_timeout_minutes'];
    }
    if(strlen($request['mfa_type']) > 0) {
      $data['mfa_type'] = $request['mfa_type'];
    }

    $this->db->where('dmaAccounts.dmaId', $dmaId);
    $this->db->update('dmaAccounts', $data);

    //if updating settings for a parent, update all children
    $this->db->where('dmaAccounts.parentDmaId', $dmaId);
    $this->db->update('dmaAccounts', $data);

    $updatedRequirements = [
        'daysDefaultInactiveLimit' => $request['daysDefaultInactiveLimit'],
        'pwDefaultCharacterCount'  => $request['pwDefaultCharacterCount'],
        'pwDefaultResetDays'       => $request['pwDefaultResetDays'],
        'pwDefaultReuseCount'      => $request['pwDefaultReuseCount'],
        'pwDefaultReuseDays'       => $request['pwDefaultReuseDays'],
    ];
    //get all users to consider for updating
    $this->db->select('Accounts.userId, Accounts.pwCharacterCountReq, Accounts.daysInactiveLimitReq, Accounts.pwReuseDaysReq, Accounts.pwReuseCountReq, Accounts.pwResetDaysReq')
             ->from('Accounts')
             ->join('dmaMembers', 'dmaMembers.dmaMUserId = Accounts.userId', 'left')
             ->join('dmaAccounts', 'dmaMembers.dmaMDmaId = dmaAccounts.dmaId', 'left')
             ->where('dmaAccounts.dmaId', $dmaId)
             ->or_where('dmaAccounts.parentDmaId', $dmaId)
             ->group_by('Accounts.userId');

    $query = $this->db->get();
    foreach($query->result_array() as $data) {
      $updateData = [];
      //If new default is greater than current, then increase current
      if($updatedRequirements['pwDefaultCharacterCount'] > $data['pwCharacterCountReq']) {
        $updateData['pwCharacterCountReq'] = $updatedRequirements['pwDefaultCharacterCount'];
      }
      //If new default is null then do nothing. If current value is not specifically 0 or is NULL, then don't reject it yet... If new default is greater than current (but the current is NOT 0), then update it. If new default is 0 then that trumps all so set it to 0
      if($updatedRequirements['pwDefaultReuseDays'] != "" && ($data['pwReuseDaysReq'] != 0 || $data['pwReuseDaysReq'] == null) && ((int)$updatedRequirements['pwDefaultReuseDays'] > $data['pwReuseDaysReq'] || (int)$updatedRequirements['pwDefaultReuseDays'] == 0)) {
        $updateData['pwReuseDaysReq'] = $updatedRequirements['pwDefaultReuseDays'];
      }
      //If new default is null then do nothing. If current value is not specifically 0 or is NULL, then don't reject it yet... If new default is greater than current (but the current is NOT 0), then update it. If new default is 0 then that trumps all so set it to 0
      if($updatedRequirements['pwDefaultReuseCount'] != "" && ($data['pwReuseCountReq'] != 0 || $data['pwReuseCountReq'] == null) && ((int)$updatedRequirements['pwDefaultReuseCount'] > $data['pwReuseCountReq'] || (int)$updatedRequirements['pwDefaultReuseCount'] == 0)) {
        $updateData['pwReuseCountReq'] = $updatedRequirements['pwDefaultReuseCount'];
      }
      //If new default is null then do nothing. If new default is greater than current, then update it.
      if($updatedRequirements['daysDefaultInactiveLimit'] != "" && $updatedRequirements['daysDefaultInactiveLimit'] < $data['daysInactiveLimitReq']) {
        $updateData['daysInactiveLimitReq'] = $updatedRequirements['daysDefaultInactiveLimit'];
      }
      //If new default is null then do nothing. If new default is greater than current, then update it.
      if($updatedRequirements['pwDefaultResetDays'] != "" && $updatedRequirements['pwDefaultResetDays'] < $data['pwResetDaysReq']) {
        $updateData['pwResetDaysReq'] = $updatedRequirements['pwDefaultResetDays'];
      }

      if(count($updateData) > 0) {
        $this->db->where('Accounts.userId', $data['userId']);
        $this->db->update('Accounts', $updateData);
      }
    }

  }

  function get_dmamembers_creditaccess_shares($listingId, $dmaId, $permissions) {

    $data = [];

    /* FIRST -- GET ACCESS OF THIS DMA ACCOUNT */

    $data['dmamembers'] = $this->get_dma_members($dmaId, 1);

    if($listingId > 0) {

      $data['adminUsersPerm'] = $this->get_dma_members_with_credit_access($listingId, "", $dmaId);

      $data['adminUsersPermUserIds'] = [];
      foreach($data['adminUsersPerm'] as $aup) {
        array_push($data['adminUsersPermUserIds'], $aup['userId']);
      }

      $data['membersAllAccess'] = [];
      $data['membersEditAccess'] = [];
      $data['membersViewAccess'] = [];
      $data['membersNoAccess'] = [];

      $checkAccess = true;
      //If this is not a private access with member permissions assigned to admins
      if(sizeof($data['adminUsersPerm']) == 0) {
        //Then get the general share for the DMA account on this credit
        $dmaAccess = $this->get_dma_credit_access($listingId, $dmaId);
        if(sizeof($dmaAccess) > 0) {
          //If it's an an OPEN type then skip the user level check and just add everyone to view/edit (in loop below)
          if($dmaAccess['caAction'] == 'open' || $dmaAccess['caAction'] == 'watch') {
            $checkAccess = false;
          }
        }
      }

      foreach($data['dmamembers'] as $dmam) {
        if($checkAccess) {
          if(in_array($dmam['userId'], $data['adminUsersPermUserIds'])) {
            $thisKey = array_search($dmam['userId'], $data['adminUsersPermUserIds']);
            if($data['adminUsersPerm'][$thisKey]['caAction'] == "edit") {
              array_push($data['membersEditAccess'], $dmam);
            } else {
              if($data['adminUsersPerm'][$thisKey]['caAction'] == "view") {
                array_push($data['membersViewAccess'], $dmam);
              } else {
                array_push($data['membersViewAccess'], $dmam);
              }
            }
            array_push($data['membersAllAccess'], $dmam);
          } else {
            array_push($data['membersNoAccess'], $dmam);
          }
        } else {
          array_push($data['membersEditAccess'], $dmam);
          array_push($data['membersAllAccess'], $dmam);
        }
      }

      /* SECOND -- GET ACCESS OF MAIN ADMIN OF ALL SHARED ACCOUNTS */

      $data['shares'] = $this->get_dma_shares_of_credit($listingId);
      $data['adminUsersSharedAll'] = [];
      $data['adminUsersSharedEdit'] = [];
      $data['adminUsersSharedView'] = [];
      foreach($data['shares'] as $sh) {
        $thisArray = [];
        $thisShareName = $sh['mainAdminLastName'] . ", " . $sh['mainAdminFirstName'];
        $thisShareUserId = $sh['primary_account_id'];
        $thisShareDmaTitle = $sh['sharedCompany'];
        //If this is NOT logged in user's current account, then add it
        if($sh['primary_account_id'] != $this->cisession->userdata('primUserId')) {
          //IF share is activated yet
          if($sh['sStatus'] == 1) {
            if($sh['sharedPermEdit'] == 1) {
              array_push($data['adminUsersSharedEdit'], ["userId" => $thisShareUserId, "name" => $thisShareName, "title" => $thisShareDmaTitle]);
            } else {
              array_push($data['adminUsersSharedView'], ["userId" => $thisShareUserId, "name" => $thisShareName, "title" => $thisShareDmaTitle]);
            }
            array_push($data['adminUsersSharedAll'], ["userId" => $thisShareUserId, "name" => $thisShareName, "title" => $thisShareDmaTitle]);
          }
        }
      }
      //If this is a shared view, then we need to add the owner of the credit as a "shared" account
      if(sizeof($permissions) > 0) {
        if($permissions['shareView']) {
          $creditData = $this->CreditListings->get_credit_private($listingId);
          $thisArray = [];
          $thisMainName = $creditData['loadedBy']['lastName'] . ", " . $creditData['loadedBy']['firstName'];
          $thisMainUserId = $creditData['cDmaMemberId'];
          $thisMainDmaTitle = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0]['title'];
          array_push($data['adminUsersSharedAll'], ["userId" => $thisMainUserId, "name" => $thisMainName, "title" => $thisMainDmaTitle]);
          if($creditData['cCustomerAccess'] == 2) {
            if($creditData['cOwnerReadOnly'] == 1) {
              array_push($data['adminUsersSharedView'], ["userId" => $thisMainUserId, "name" => $thisMainName, "title" => $thisMainDmaTitle]);
            } else {
              array_push($data['adminUsersSharedEdit'], ["userId" => $thisMainUserId, "name" => $thisMainName, "title" => $thisMainDmaTitle]);
            }
          }
        }
      }

    }

    return $data;

  }

  function get_dma_shares_of_credit($listing_id) {

    $this->db->select('Shares.*, dmaAccounts.title as sharedCompany, dmaAccounts.profileUrl, dmaAccounts.dmaId, dmaAccounts.mainAdmin, dmaAccounts.primary_account_id, Accounts.firstName as mainAdminFirstName, Accounts.lastName as mainAdminLastName');
    $this->db->where('Shares.sItemId', $listing_id);
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->join("dmaAccounts", "Shares.sharedPrimId = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts", "dmaAccounts.primary_account_id = Accounts.userId", 'left');
    $query = $this->db->get('Shares');

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_dma_credit_access($listingId, $dmaId) {

    $this->db->select('creditAccess.*');
    $this->db->where('creditAccess.caListingId', $listingId);
    $this->db->where('creditAccess.caDmaId', $dmaId);
    $this->db->where('creditAccess.caDmaMemberId', null);
    $this->db->from('creditAccess');

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_dma_members_with_credit_access($listingId, $caAction = "", $dmaId) {

    $this->db->select('dmaMembers.*, creditAccess.*, dmaAccounts.*, dmaGroups.*, Accounts.firstName, Accounts.lastName, Accounts.userId');
    $this->db->where('creditAccess.caListingId', $listingId);
    $this->db->where('creditAccess.caDmaId', $dmaId);
    if($caAction != "") {
      $this->db->where('creditAccess.caAction', $caAction);
    }
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "dmaMembers.dmaMUserId = Accounts.userId", 'left');
    $this->db->join("dmaGroups", "dmaGroups.dmaGroupId = dmaMembers.dmaMGroupId", 'left');
    $this->db->join("creditAccess", "creditAccess.caDmaMemberId = dmaMembers.dmaMemberId", 'left');

    $this->db->order_by("Accounts.lastName", "ASC");

    $query = $this->db->get();

    return $query->result_array();

  }

  function add_dma_member_access_to_credit($caAction, $dmaId, $dmaMemberId, $lid) {

    $data = [
        'caAction'      => $caAction,
        'caDmaId'       => $dmaId,
        'caDmaMemberId' => $dmaMemberId,
        'caListingId'   => $lid,
    ];

    $this->db->insert('creditAccess', $data);

    return $this->db->insert_id();

  }

  function remove_dma_member_access_to_credit($caAction, $dmaMemberId, $lid) {

    $this->db->where('creditAccess.caListingId', $lid);
    $this->db->where('creditAccess.caDmaMemberId', $dmaMemberId);
    $this->db->where('creditAccess.caAction', $caAction);
    $this->db->delete('creditAccess');

  }

  function insert_dmamember() {

    $data = [
        'dmaMDmaId'   => $this->input->post('dmaAccountId'),
        'mFirstName'  => $this->input->post('mFirstName'),
        'mLastName'   => $this->input->post('mLastName'),
        'mEmail'      => $this->input->post('mEmail'),
        'tpTimestamp' => time(),
        'dmaMGroupId' => $this->input->post('memberLevel'),
    ];

    $this->db->insert('dmaMembers', $data);

    return $this->db->insert_id();

  }

  function create_dmamember($request) {

    $data = [
        'dmaMUserId'    => $request['dmaMUserId'],
        'dmaMDmaId'     => $request['dmaMDmaId'],
        'dmaMGroupId'   => $request['dmaMGroupId'],
        'dmaMStatus'    => $request['dmaMStatus'],
        'dmaMDateAdded' => time(),
        'dmaMJoinDate'  => time(),
    ];

    $this->db->insert('dmaMembers', $data);

    return $this->db->insert_id();

  }

  function set_new_member_date($dmaMemberId) {

    $data = [
        'dmaMJoinDate' => time(),
    ];

    $this->db->where('dmaMembers.dmaMemberId', $dmaMemberId);
    $this->db->update('dmaMembers', $data);

  }

  function get_dma_account_by_id($id) {

    $this->db->select('dmaAccounts.*, Accounts.userId, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.address1, Accounts.address2, Accounts.city, Accounts.state, Accounts.postalCode, Accounts.phone, Accounts.mobilePhone, dmaMembers.dmaMemberId, dmaMembers.dmaMGroupId');
    $this->db->where('dmaId', $id);
    $this->db->where('dmaMDmaId', $id);
    $this->db->join("Accounts", "dmaAccounts.mainAdmin = Accounts.userId", 'left');
    $this->db->join("dmaMembers", "dmaAccounts.primary_account_id = dmaMembers.dmaMUserId", 'left');
    $this->db->from('dmaAccounts');

    $query = $this->db->get();

    $return = $query->result_array();

    if(is_null($return[0]['profileUrl'])) {
      $return[0]['profileUrl'] = 'online-incentives-exchange';
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_pending_shares_of_email($email) {

    $this->db->select('Shares.*, dmaAccounts.*, Accounts.firstName, Accounts.lastName');
    $this->db->where('Shares.sharedInviteEmail', $email);
    $this->db->where('Shares.sStatus', 0);
    $this->db->join("dmaAccounts", "Shares.sharerPrimId = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts", "Shares.sharerSecId = Accounts.userId", 'left');
    $this->db->from('Shares');
    $this->db->distinct();
    $this->db->group_by('Shares.sId, dmaAccounts.dmaId');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;
  }

  function get_shared_accounts_of_dma_account($dmaId, $sType) {

    $this->db->select('Shares.sId, dmaAccounts.*');
    $this->db->where('Shares.sharedPrimId', $dmaId);
    $this->db->where('Shares.sType', $sType);
    $this->db->join("dmaAccounts", "Shares.sharerPrimId = dmaAccounts.mainAdmin", 'left');
    $this->db->from('Shares');
    $this->db->distinct();
    $this->db->group_by('dmaAccounts.mainAdmin');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;
  }

  function get_child_accounts_of_dma_account($dmaId) {

    $this->db->select('dmaAccounts.*');
    $this->db->where('dmaAccounts.parentDmaId', $dmaId);
    $this->db->from('dmaAccounts');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;
  }

  function update_credit_view_settings($userId) {

    $columns = explode(',', $this->input->post('columns'));
    $json = json_encode($columns);

    $data = [
        'creditViewConfig' => $json,
    ];

    $this->db->where('userId', $userId);
    $this->db->update('Accounts', $data);

  }

  function save_wire_instructions($dmaId, $dmaWireInstructions) {
    $data = [
        'dmaWireInstructions' => $dmaWireInstructions,
    ];
    $this->db->where('dmaAccounts.dmaId', $dmaId);
    $this->db->update('dmaAccounts', $data);
  }

}

/* End of file dmaaccounts.php */
/* Location: ./application/models/programs.php */
