<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class AuditTrail extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('Docs');
    $this->load->library(['session']);

  }

  function get_audit_trail($audItemId = "", $audTypeId = "", $audItemIdSub = "", $getOriginalValue = "", $audDmaId = "") {
    $audItemId = $audItemId;
    if($audItemId > 0 || $audDmaId > 0) {
    } else {
      throw new \Exception('General fail');
    } //block out an empty request for ALL audit
    $this->db->select('audit_trail.*, audit_trail_type.*, dmaAccounts.title, Accounts.userId, Accounts.firstName, Accounts.lastName');
    $this->db->from('audit_trail');
    if($audItemId > 0) {
      $this->db->where('audit_trail.audItemId', $audItemId);
    }
    if($audDmaId > 0) {
      $this->db->where('audit_trail.audDmaId', $audDmaId);
    }
    if($audTypeId > 0) {
      $this->db->where('audit_trail.audTypeId', $audTypeId);
    } else {
      $this->db->where('audit_trail.audTypeId !=', '2');
      $this->db->where('audit_trail.audTypeId !=', '33');
      $this->db->where('audit_trail.audTypeId !=', '35');
      $this->db->where('audit_trail.audTypeId !=', '70');
    }
    if($audItemIdSub > 0) {
      $this->db->where('audit_trail.audItemIdSub', $audItemIdSub);
    }
    $this->db->join("audit_trail_type", "audit_trail.audTypeId = audit_trail_type.audTId", 'left');
    $this->db->join("dmaAccounts", "audit_trail.audDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "audit_trail.audUserId = Accounts.userId", 'left');
    if($getOriginalValue == 1) {
      $this->db->order_by("audit_trail.audId ASC");
      $this->db->limit(1);
    } else {
      $this->db->order_by("audit_trail.audId DESC");
    }
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $data['commentsCount'] = $this->countCommentsOnAuditItem($data['audId']);
      //$data['docsCount'] = $this->countDocumentsOnWorkflowItem($data['wiId']);
      $docConnectToType = "audit_trail";
      if($audTypeId == 2) {
        $docConnectToType = "audit_trail_variance_2";
      } else {
        if($audTypeId == 33) {
          $docConnectToType = "audit_trail_variance_33";
        } else {
          if($audTypeId == 35) {
            $docConnectToType = "audit_trail_variance_35";
          } else {
            if($audTypeId == 70) {
              $docConnectToType = "audit_trail_variance_70";
            }
          }
        }
      }

      if($data['audTypeId'] == 0) {
        $data['audTValueText'] = $data['audCustomName'];
      }
      $data['documents'] = $this->Docs->get_documents("credit_doc", "", "", $docConnectToType, $data['audId'], "", 1);
      array_push($return, $data);
    }

    return $return;

  }

  function get_audit_item($audId) {
    $this->db->select('audit_trail.*, audit_trail_type.*, dmaAccounts.title, Accounts.userId, Accounts.firstName, Accounts.lastName');
    $this->db->from('audit_trail');
    $this->db->where('audit_trail.audId', $audId);
    $this->db->join("audit_trail_type", "audit_trail.audTypeId = audit_trail_type.audTId", 'left');
    $this->db->join("dmaAccounts", "audit_trail.audDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "audit_trail.audUserId = Accounts.userId", 'left');

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $data['commentsCount'] = $this->countCommentsOnAuditItem($data['audId']);
      //$data['docsCount'] = $this->countDocumentsOnWorkflowItem($data['wiId']);
      $docConnectToType = "audit_trail";
      $data['documents'] = $this->Docs->get_documents("credit_doc", "", "", $docConnectToType, $data['audId'], "", 1);
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function countCommentsOnAuditItem($audId) {
    $this->db->select('cmId');
    $this->db->from('Comments');
    $array = ['Comments.cmDeleteMarker' => null, 'Comments.cmConnectToType' => 'audit_trail', 'Comments.cmItemId' => $audId];
    $this->db->where($array);
    $num_results = $this->db->count_all_results();

    return $num_results;

  }

  function countDocumentsOnWorkflowItem($docItemId) {
    $this->db->select('fileInfoId');
    $this->db->from('files_info');
    $array = ['files_info.deleteMarker' => 0, 'files_info.docConnectToType' => 'workflow_item', 'files_info.docItemId' => $docItemId];
    $this->db->where($array);
    $num_results = $this->db->count_all_results();

    return $num_results;

  }

  function insert_audit_item($audTypeId, $audItemId, $audValueBefore = "", $audValueAfter = "", $audRelVal1Before = "", $audRelVal1After = "", $audRelVal2Before = "", $audRelVal2After = "", $audRelVal3Before = "", $audRelVal3After = "", $audItemIdSub = "", $audActionSub = "", $customDataPointName = "") {

    $data = [
        'audTypeId'        => $audTypeId,
        'audItemId'        => $audItemId,
        'audItemIdSub'     => $audItemIdSub,
        'audUserId'        => $this->cisession->userdata('userId'),
        'audDmaId'         => $this->cisession->userdata('dmaId'),
        'audValueBefore'   => $audValueBefore,
        'audValueAfter'    => $audValueAfter,
        'audRelVal1Before' => $audRelVal1Before,
        'audRelVal1After'  => $audRelVal1After,
        'audRelVal2Before' => $audRelVal2Before,
        'audRelVal2After'  => $audRelVal2After,
        'audRelVal3Before' => $audRelVal3Before,
        'audRelVal3After'  => $audRelVal3After,
        'audActionSub'     => $audActionSub,
        'audCustomName'    => $customDataPointName,
        'audTimestamp'     => time(),
    ];

    $this->db->insert('audit_trail', $data);

    return $this->db->insert_id();

  }

  function backend_insert_audit_item($audTypeId, $audUserId, $audDmaId, $audItemId, $audValueBefore, $audValueAfter, $audRelVal1Before, $audRelVal1After, $audRelVal2Before, $audRelVal2After, $audRelVal3Before, $audRelVal3After, $audActionSub = "") {

    $data = [
        'audTypeId'        => $audTypeId,
        'audItemId'        => $audItemId,
        'audUserId'        => $audUserId,
        'audDmaId'         => $audDmaId,
        'audValueBefore'   => $audValueBefore,
        'audValueAfter'    => $audValueAfter,
        'audRelVal1Before' => $audRelVal1Before,
        'audRelVal1After'  => $audRelVal1After,
        'audRelVal2Before' => $audRelVal2Before,
        'audRelVal2After'  => $audRelVal2After,
        'audRelVal3Before' => $audRelVal3Before,
        'audRelVal3After'  => $audRelVal3After,
        'audActionSub'     => $audActionSub,
        'audCustomName'    => $customDataPointName,
        'audTimestamp'     => time(),
    ];

    $this->db->insert('audit_trail', $data);

    return $this->db->insert_id();

  }

  function insert_audit_item_api($auditData) {

    /* FOR REFERENCE
    $data = array(
      'audTypeId' => $auditData['audTypeId'],
      'audItemId' => $auditData['audItemId'],
      'audUserId' => $auditData['audUserId'],
      'audDmaId' => $auditData['audDmaId'],
      'audValueBefore' => $auditData['audValueBefore'],
      'audValueAfter' => $auditData['audValueAfter'],
      'audRelVal1Before' => $auditData['audRelVal1Before'],
      'audRelVal1After' => $auditData['audRelVal1After'],
      'audRelVal2Before' => $auditData['audRelVal2Before'],
      'audRelVal2After' => $auditData['audRelVal2After'],
      'audRelVal3Before' => $auditData['audRelVal3Before'],
      'audRelVal3After' => $auditData['audRelVal3After'],
      'audActionSub' => $auditData['audActionSub'],
      'audCustomName' => $auditData['customDataPointName'],
      'audTimestamp' => time()
    );
    */

    $auditData['audTimestamp'] = time();

    $this->db->insert('audit_trail', $auditData);

    return $this->db->insert_id();

  }

  function get_audit_trail_of_item_for_account($listedBy, $audTypeId, $fieldName, $dateRange = []) {

    if($listedBy > 0 || $audTypeId > 0) {
    } else {
      throw new \Exception('General fail');
    } //block out an empty request for ALL audit

    $this->db->select('audit_trail.*, PendingListings.listingId, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, States.state as stateCode, States.name as stateName, dmaAccounts.title, Accounts.userId, Accounts.firstName, Accounts.lastName');
    $this->db->from('audit_trail');

    $this->db->where('PendingListings.listedBy', $listedBy);
    $this->db->where('audit_trail.audTypeId', $audTypeId);
    if(sizeof($dateRange) > 0) {
      $this->db->where('audit_trail.audTimestamp >', $dateRange['startDate']);
      $this->db->where('audit_trail.audTimestamp <=', $dateRange['endDate']);
    }
    $this->db->join("PendingListings", "audit_trail.audItemId = PendingListings.listingId", 'left');
    $this->db->join("dmaAccounts", "audit_trail.audDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "audit_trail.audUserId = Accounts.userId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');

    $this->db->order_by("audit_trail.audId ASC");

    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      if(!array_key_exists($data['listingId'], $return)) {
        $return[$data['listingId']] = [];
      }
      $thisItem['listingIdFull'] = $data['stateCode'] . $data['listingId'];
      $thisItem['listingId'] = $data['listingId'];
      $thisItem['stateCode'] = $data['stateCode'];
      $thisItem['stateName'] = $data['stateName'];
      $thisItem['projectName'] = $data['projectName'];
      $thisItem['projectNameExt'] = $data['projectNameExt'];
      $thisItem['timeStampUnix'] = $data['audTimestamp'];
      $thisItem['timeStamp'] = date('m/d/Y h:i', $data['audTimestamp']);
      $thisItem[$fieldName] = $data['audValueAfter'];
      array_push($return[$data['listingId']], $thisItem);

    }

    return $return;

  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
