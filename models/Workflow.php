<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Workflow extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('Members_Model');
    $this->load->model('Docs');
    $this->load->library(['session']);

  }

  function get_compliance_values_as_text($data) {

    $valuesAsText = [];
    if($data['wiTempType'] == "number") {
      $valuesAsText['wiValueAsText'] = number_format($data['wiValue']);
      $valuesAsText['wiValue1ExpectedAsText'] = number_format($data['wiValue1Expected']);
    } else {
      if($data['wiTempType'] == "currency") {
        $valuesAsText['wiValueAsText'] = "$" . number_format($data['wiValue']);
        $valuesAsText['wiValue1ExpectedAsText'] = "$" . number_format($data['wiValue1Expected']);
      } else {
        if($data['wiTempType'] == "yesno") {
          $valuesAsText['wiValueAsText'] = ($data['wiValue']) ? "'Yes'" : "'No'";
          $valuesAsText['wiValue1ExpectedAsText'] = "'Yes'";
        } else {
          $valuesAsText['wiValueAsText'] = $data['wiValue'];
          $valuesAsText['wiValue1ExpectedAsText'] = $data['wiValue1Expected'];
        }
      }
    }

    return $valuesAsText;
  }

  function check_compliance_alerts($data) {

    $data["isValueCompliant"] = true;
    $data['alertDateDifferenceDays'] = number_format((time() - $data['wiCompletedDate1Expected']) / 86400);
    $data['alertDateDifferenceDaysText'] = ($data['alertDateDifferenceDays'] >= 0) ? $data['alertDateDifferenceDays'] . " Days Ago" : "In " . abs($data['alertDateDifferenceDays']) . " Days";
    $data['deadlineMissed'] = ($data['alertDateDifferenceDays'] >= 0) ? true : false;
    $data['wiValueAsText'] = ($data['wiValue'] != "") ? $data['wiValue'] : "-";

    //If value is set and it does not meet requirement
    if($data['wiValueAsText'] != '-') {
      if($data['wiTempType'] == 'currency') {
        if($data['wiValue'] < $data['wiValue1Expected']) {
          $data["isValueCompliant"] = false;
        }
      }
      if($data['wiTempType'] == 'date') {
        if($data['wiValue'] < $data['wiValue1Expected']) {
          $data["isValueCompliant"] = false;
        }
        $data['wiValueAsText'] = date('m/d/Y', $data['wiValue1AsText']);
      }
      if($data['wiTempType'] == 'number') {
        if($data['wiValue'] < $data['wiValue1Expected']) {
          $data["isValueCompliant"] = false;
        }
        $data['wiValueAsText'] = number_format($data['wiValueAsText']);
      }
      if($data['wiTempType'] == 'yesno') {
        if($data['wiValueAsText'] != 'Yes') {
          $data["isValueCompliant"] = false;
        }
        $data['wiValueAsText'] = ($data['wiValue'] != "") ? "'" . $data['wiValue'] . "'" : "-";
      }

      //Else, if empty and within alert window
    } else {

      $alertDate = $data['wiCompletedDate1Expected'] - ($data['wiComplianceAlert'] * 86400);
      if(time() > $alertDate) {
        $data["isValueCompliant"] = false;
      }

    }

    return $data;

  }

  function get_workflow_templates($dmaId = "", $wAttachedToType = "", $defaultOixFlag = "", $isParentDmaTemplates = "", $wWorkflowType = "") {

    $this->db->select('workflows.*');
    $this->db->from('workflows');
    if($defaultOixFlag == 1) {
      $this->db->where('workflows.wIsTemplate', 2);
      if($wAttachedToType != "") {
        $this->db->where('workflows.wAttachedToType', $wAttachedToType);
      }
    } else {
      $this->db->where('workflows.wIsTemplate', 1);
      if($dmaId > 0) {
        $this->db->where('workflows.wDmaId', $dmaId);
      }
      if($wAttachedToType != "") {
        $this->db->where('workflows.wAttachedToType', $wAttachedToType);
      }
    }
    if($wWorkflowType == "compliance") {
      $this->db->where('workflows.wWorkflowType', 'compliance');
    } else {
      $this->db->where('workflows.wWorkflowType', 'workflow');
    }
    $this->db->where('workflows.wDeleteMarker', null);
    $this->db->order_by("workflows.wTemplateName ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      //$data['wLists'] = $this->get_workflow_lists($data['wId']);
      $wWorkflowType = ($wWorkflowType != "") ? $wWorkflowType : "workflow";
      $data['childWorkflows'] = $this->get_child_workflows_summary($data['wId'], $data['wAttachedToType'], $wWorkflowType, $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));
      $data['childWorkflowsCount'] = sizeof($data['childWorkflows']);
      $data['isParentDmaTemplate'] = ($isParentDmaTemplates == 1) ? true : false;
      array_push($return, $data);
    }

    return $return;

  }

  function get_child_workflows_summary($wId, $wAttachedToType, $wWorkflowType, $dmaId = "", $dmaMemberId = "") {

    if($wAttachedToType == "credit") {

      $this->db->select('wId, wAttachedToType, IncentivePrograms.State, PendingListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt');

      $array = [];
      if($wWorkflowType == "compliance") {
        $array['PendingListings.cComplianceId'] = $wId;
      } else {
        $array['PendingListings.cWorkflowId'] = $wId;
      }
      $array['workflows.wDeleteMarker'] = null;
      $this->db->where($array);

      if($dmaId > 0 && $dmaMemberId > 0) {
        $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
        $this->db->where($whereAccess);
      }

      $this->db->from('PendingListings');

      if($wWorkflowType == "compliance") {
        $this->db->join("workflows", "workflows.wId = PendingListings.cComplianceId", 'left');
      } else {
        $this->db->join("workflows", "workflows.wId = PendingListings.cWorkflowId", 'left');
      }
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
      if($dmaId > 0 && $dmaMemberId > 0) {
        $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
        $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId", 'left');
      }

      $this->db->distinct();

      $return = [];
      $query = $this->db->get();

      foreach($query->result_array() as $data) {
        array_push($return, $data);
      }

      return $return;

    }

    if($wAttachedToType == "transaction") {

      $this->db->select('wId, wAttachedToType, IncentivePrograms.State, PendingListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, Trades.tradeId, Transactions.transactionId, Transactions.tCreditAmount, buyerAccount.title as buyerAccountName');

      $array = [];
      $array['Transactions.transWorkflowId'] = $wId;
      $array['workflows.wDeleteMarker'] = null;
      $this->db->where($array);

      if($dmaId > 0 && $dmaMemberId > 0) {
        $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
        $this->db->where($whereAccess);
      }

      $this->db->from('Transactions');

      $this->db->join("workflows", "workflows.wId = Transactions.transWorkflowId", 'left');
      $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
      $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
      $this->db->join("dmaAccounts as buyerAccount", "buyerAccount.mainAdmin = Trades.accountId", 'left');

      if($dmaId > 0 && $dmaMemberId > 0) {
        $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
        $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId", 'left');
      }

      $this->db->distinct();

      $return = [];
      $query = $this->db->get();

      foreach($query->result_array() as $data) {
        array_push($return, $data);
      }

      return $return;

    }

  }

  function get_child_workflows($wId, $dmaId = "") {
    $this->db->select('*');
    $this->db->from('workflows');

    $array = [];
    $array['workflows.wParentwId'] = $wId;
    if($dmaId > 0) {
      $array['workflows.wDmaId'] = $dmaId;
    }
    $array['workflows.wDeleteMarker'] = null;
    $array['ActiveListings.deleteMarker'] = null;
    $this->db->where($array);
    $this->db->join("ActiveListings", "workflows.wAttachedToId = ActiveListings.listingId", 'left');

    $this->db->order_by("workflows.wTemplateName ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_workflow($wId = "", $type = "", $id = "", $wWorkflowType = "", $transactionId = "") {

    if($wId > 0) {
    } else {
      if($type != "" && $id > 0) {
      } else {
        return [];
      }
    }

    $this->db->select('*');
    $this->db->from('workflows');

    $array = [];
    if($wId > 0) {
      $array['workflows.wId'] = $wId;
    } else {
      if($type != "" && $id > 0) {
        $array['workflows.wAttachedToType'] = $type;
        $array['PendingListings.listingId'] = $id;
      } else {
        if($transactionId > 0) {
          $array['Transactions.transactionId'] = $transactionId;
        }
      }
    }
    if($wWorkflowType != "") {
      $array['workflows.wWorkflowType'] = $wWorkflowType;
    }
    $array['workflows.wDeleteMarker'] = null;
    $this->db->where($array);
    if($wId > 0) {
    } else {
      if($wWorkflowType == "compliance") {
        $this->db->join("PendingListings", "PendingListings.cComplianceId = workflows.wId", 'left');
      } else {
        if($transactionId > 0) {
          $this->db->join("Transactions", "Transactions.transWorkflowId = workflows.wId", 'left');
        } else {
          $this->db->join("PendingListings", "PendingListings.cWorkflowId = workflows.wId", 'left');
        }
      }
    }

    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      $data['wTemplateData'] = $data;
      $data['wTemplateData']['childWorkflows'] = $this->get_child_workflows_summary($data['wId'], $data['wAttachedToType'], $data['wWorkflowType'], $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));
      $data['wTemplateData']['childWorkflowsCount'] = sizeof($data['wTemplateData']['childWorkflows']);
      $data['wLists'] = $this->get_workflow_lists($data['wId'], $id, $type, $transactionId);

      $data['creditComplianceAlerts'] = [];

      //Copy all checklist items to a flat array on the top level (for editing logic)
      $data['allwlItems'] = [];
      $wListsCount = 0;
      foreach($data['wLists'] as $wLists) {
        $wlItemsCount = 0;
        foreach($wLists['wlItems'] as $wlItems) {
          $wlItems['wWorkflowType'] = $data['wWorkflowType'];

          $wlItems["isValueCompliant"] = true;
          $wlItems["isValueCompliantAsText"] = '';
          //Convert some values to read-ready
          $valuesAsText = $this->get_compliance_values_as_text($wlItems);
          $wlItems['wiValueAsText'] = $valuesAsText['wiValueAsText'];
          $wlItems['wiValue1ExpectedAsText'] = $valuesAsText['wiValue1ExpectedAsText'];
          $wlItems['wiCompletedDate1ExpectedAsText'] = ($wlItems['wiCompletedDate1Expected'] > 0) ? date('m/d/Y', $wlItems['wiCompletedDate1Expected']) : "";
          if($wlItems['wiStatus'] == 2) {
            $wlItems['wiStatusAsText'] = "In Progress";
          } else {
            if($wlItems['wiStatus'] == 1) {
              $wlItems['wiStatusAsText'] = "Completed";
            } else {
              $wlItems['wiStatusAsText'] = "Pending";
            }
          }
          if($id > 0 || $transactionId > 0) {
            $wlItems['assignedToAsText'] = ($wlItems['wiAssignedTo'] > 0) ? $wlItems['wiAssignedToFirstName'] . " " . $wlItems['wiAssignedToLastName'] : "";
            $wlItems['docsCount'] = (sizeof($wlItems['documents']) > 0) ? sizeof($wlItems['documents']) : "";
            $wlItems['notesCount'] = ($wlItems['commentsCount'] > 0) ? $wlItems['commentsCount'] : "";
            $wlItems['completedDateAsText'] = ($wlItems['wiCompletedDate'] > 0) ? date('m/d/Y', $wlItems['wiCompletedDate']) : "";
            $wlItems['completedByAsText'] = ($wlItems['wiCompletedUser'] > 0) ? $wlItems['firstName'] . " " . $wlItems['lastName'] : "";
          }
          //Add read-ready values to all items
          $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['wiValueAsText'] = $wlItems['wiValueAsText'];
          $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['wiValue1ExpectedAsText'] = $wlItems['wiValue1ExpectedAsText'];
          $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['wiStatusAsText'] = $wlItems['wiStatusAsText'];
          if($id > 0 || $transactionId > 0) {
            $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['assignedToAsText'] = $wlItems['assignedToAsText'];
            $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['docsCount'] = $wlItems['docsCount'];
            $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['notesCount'] = $wlItems['notesCount'];
            $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['completedDateAsText'] = $wlItems['completedDateAsText'];
            $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount]['completedByAsText'] = $wlItems['completedByAsText'];
          }

          if($id > 0 && $data['wWorkflowType'] == 'compliance') {
            $wlItems = $this->check_compliance_alerts($wlItems);
            if(!$wlItems["isValueCompliant"]) {
              array_push($data['creditComplianceAlerts'], $wlItems);
              $wlItems["isValueCompliantAsText"] = 'NOT COMPLIANT';
            }
            $data['wLists'][$wListsCount]['wlItems'][$wlItemsCount] = $wlItems;
          }

          $data['allwlItems'][] = $wlItems;
          $wlItemsCount++;
        }
        $wListsCount++;
      }

      if($id > 0 && $data['wWorkflowType'] == 'compliance') {
        //Re-order all items by date
        usort($data['allwlItems'], function($a, $b) {
          return $a['wiValue1Expected'] - $b['wiValue1Expected'];
        });
      }

      //Re-order the list by days
      usort($data['creditComplianceAlerts'], function($a, $b) {
        return $a['alertDateDifferenceDays'] - $b['alertDateDifferenceDays'];
      });

      //Return all data
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {

      if($id > 0) {

        //Let's first check if there are any new workflow items on the parent template we need to add here
        $currWVs = $this->get_current_workflow_item_values_on_credit($id, "credit");
        $missingVWsCount = 0;
        foreach($return[0]['allwlItems'] as $wi) {
          //If a template workflow item does NOT have a matching value on the credit, then add it
          $matchFound = false;
          //compare this workflow item ID to each value
          foreach($currWVs as $wv) {
            if($wi['wiId'] == $wv['wvWorkflowItemId']) {
              $matchFound = true;
            }
          }
          //If no match is found
          if(!$matchFound) {
            $wRequest['wiId'] = $wi['wiId'];
            $wRequest['wvAttachedToType'] = "credit";
            $wRequest['wvAttachedToId'] = $id;
            $wRequest['wiStatus'] = 0;
            $wRequest['wiValue'] = null;
            $this->insert_workflow_item_value($wRequest);
            //Increment for later...
            $missingVWsCount++;
          }
        }
        //Now, check if we found any new workflow items that needed to be added
        if($missingVWsCount > 0) {
          //Re-retrieve the workflow data now that we've added to it
          $return[0] = $this->get_workflow($return[0]['program']['cWorkflowId'], '', $id);
        }

      }

      //IF compliance then re-order into year break down
      if($return[0]['wWorkflowType'] == 'compliance') {

        $complianceYearCount = $return[0]['wYearsCompliant'];
        $complianceYearStart = $return[0]['wStartYearCompliance'];
        $complianceYearEnd = $complianceYearStart + $complianceYearCount - 1;
        $return[0]['complianceYearCount'] = $complianceYearCount;
        $return[0]['complianceYearStart'] = $complianceYearStart;
        $return[0]['complianceYearEnd'] = $complianceYearEnd;
        $complianceByYear = [];
        while($complianceYearStart <= $complianceYearEnd) {
          $complianceByYear[$complianceYearStart] = [];
          $complianceYearStart++;
        }

        foreach($return[0]['allwlItems'] as $wi) {
          $thisCYear = ($wi['wiCompletedDate1Expected'] != '') ? date('Y', $wi['wiCompletedDate1Expected']) : '';
          if($thisCYear != '' && ($thisCYear >= $return[0]['complianceYearStart'] && $thisCYear <= $return[0]['complianceYearEnd'])) {
            array_push($complianceByYear[$thisCYear], $wi);
          }
        }

        $return[0]['complianceByYear'] = $complianceByYear;

      }

      return $return[0];

    } else {
      return [];
    }

  }

  function get_workflow_lists($id, $listingId = "", $type = "", $transactionId = "") {

    $this->db->select('workflow_lists.*');
    $this->db->from('workflow_lists');
    $array['workflow_lists.wlWorkflowId'] = $id;
    $array['workflow_lists.wlDeleteMarker'] = null;
    $this->db->where($array);
    if($this->cisession->userdata('level') == 4) {
      $whereAccess = "(workflow_lists.wlIsPrivate IS NULL OR workflows.wDmaId = " . $this->cisession->userdata('dmaId') . ")";
      $this->db->where($whereAccess);
    }
    $this->db->join("workflows", "workflows.wId = workflow_lists.wlWorkflowId", 'left');
    $this->db->order_by("workflow_lists.wlOrder ASC");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $data['wlItems'] = $this->get_workflow_list_items($data['wlId'], $listingId, $type, $transactionId);
      array_push($return, $data);
    }

    return $return;

  }

  function get_workflow_list_items($id, $listingId = "", $type = "", $transactionId = "") {

    $listingId = (isset($listingId) && is_numeric($listingId)) ? $listingId : 0;
    $type = ($type != "") ? $type : "credit";

    $select = 'workflow_items.*, workflow_item_values.*, workflow_item_templates.wiTempId, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempDataPointId, workflow_item_templates.wiTempIsTemp, workflow_item_templates.wiTempIsPrivate, Accounts.userId, Accounts.firstName, Accounts.lastName, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName, accountLastUpdatedBy.firstName as wiLastUpdatedByFirstName, accountLastUpdatedBy.lastName as wiLastUpdatedByLastName, dataPoints.dpKey, dataPoints.dpValue, dataPoints.dpType, dataPoints.dpNameFull, workflows.wWorkflowType';
    $this->db->select($select);
    $this->db->from('workflow_items');
    $array['workflow_items.wiListId'] = $id;
    $array['workflow_items.wiDeleteMarker'] = null;
    $array['workflow_item_values.wvDeleteMarker'] = null;
    $this->db->where($array);
    if($this->cisession->userdata('level') == 4) {
      $whereAccess = "(workflow_item_templates.wiTempIsPrivate IS NULL OR workflow_item_templates.wiTempDmaId = " . $this->cisession->userdata('dmaId') . ")";
      $this->db->where($whereAccess);
    }
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    if($transactionId > 0) {
      $this->db->join("workflow_item_values", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId AND workflow_item_values.wvAttachedToId='$transactionId' AND workflow_item_values.wvAttachedToType='$type'", 'left');
    } else {
      $this->db->join("workflow_item_values", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId AND workflow_item_values.wvAttachedToId='$listingId' AND workflow_item_values.wvAttachedToType='$type'", 'left');
    }
    $this->db->join("workflow_lists", "workflow_lists.wlId = workflow_items.wiListId", 'left');
    $this->db->join("workflows", "workflows.wId = workflow_lists.wlWorkflowId", 'left');
    $this->db->join("Accounts", "workflow_item_values.wiCompletedUser = Accounts.userId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    $this->db->join("Accounts as accountLastUpdatedBy", "workflow_item_values.wiLastUpdatedUser = accountLastUpdatedBy.userId", 'left');
    $this->db->join("dataPoints", "dataPoints.dpId = workflow_item_templates.wiTempDataPointId", 'left');
    $this->db->order_by("workflow_items.wiOrder ASC");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      if(isset($data['wvId'])) {
      } else {
        $data['wvId'] = 0;
      }
      if($data['wvId'] > 0) {
        $data['commentsCount'] = $this->countCommentsOnWorkflowItem($data['wvId']);
        //$data['docsCount'] = $this->countDocumentsOnWorkflowItem($data['wiId']);
        $data['documents'] = $this->Docs->get_documents("credit_doc", "", "", $data['wWorkflowType'] . "_item", $data['wvId'], "", 1);
      } else {
        $data['commentsCount'] = 0;
        $data['documents'] = [];
      }

      array_push($return, $data);
    }

    return $return;

  }

  function get_workflow_item($wiId = "", $wvAttachedToId = "", $wvId = "") {

    $wvAttachedToId = (isset($wvAttachedToId) && is_numeric($wvAttachedToId)) ? $wvAttachedToId : 0;

    $this->db->select('workflow_items.*, workflow_item_values.*, workflow_item_templates.wiTempId, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempDataPointId, workflow_item_templates.wiTempIsTemp, workflow_item_templates.wiTempIsPrivate, Accounts.userId, Accounts.firstName, Accounts.lastName, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName, accountLastUpdatedBy.firstName as wiLastUpdatedByFirstName, accountLastUpdatedBy.lastName as wiLastUpdatedByLastName, dataPoints.dpKey, dataPoints.dpValue, dataPoints.dpType, dataPoints.dpNameFull');
    $this->db->from('workflow_items');
    if($wiId > 0) {
      $this->db->where('workflow_items.wiId', $wiId);
    } else {
      if($wvId > 0) {
        $this->db->where('workflow_item_values.wvId', $wvId);
      } else {
        throw new \Exception('General fail');
      }
    }
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    $this->db->join("workflow_item_values", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId AND workflow_item_values.wvAttachedToId='$wvAttachedToId'", 'left');
    $this->db->join("Accounts", "workflow_item_values.wiCompletedUser = Accounts.userId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    $this->db->join("Accounts as accountLastUpdatedBy", "workflow_item_values.wiLastUpdatedUser = accountLastUpdatedBy.userId", 'left');
    $this->db->join("dataPoints", "dataPoints.dpId = workflow_item_templates.wiTempDataPointId", 'left');
    $this->db->order_by("workflow_items.wiOrder ASC");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      if(isset($data['wvId'])) {
      } else {
        $data['wvId'] = 0;
      }
      $valuesAsText = $this->get_compliance_values_as_text($data);
      $data['wiValueAsText'] = $valuesAsText['wiValueAsText'];
      $data['wiValue1ExpectedAsText'] = $valuesAsText['wiValue1ExpectedAsText'];
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_workflow_item_by_value($wvId) {

    $this->db->select('workflow_items.*, workflow_item_values.*, workflow_item_templates.wiTempId, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempDataPointId, workflow_item_templates.wiTempIsTemp, workflow_item_templates.wiTempIsPrivate, Accounts.userId, Accounts.firstName, Accounts.lastName, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName, accountLastUpdatedBy.firstName as wiLastUpdatedByFirstName, accountLastUpdatedBy.lastName as wiLastUpdatedByLastName, dataPoints.dpKey, dataPoints.dpValue, dataPoints.dpType, dataPoints.dpNameFull');
    $this->db->from('workflow_item_values');
    $this->db->where('workflow_item_values.wvId', $wvId);

    $this->db->join("workflow_items", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId", 'left');
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    $this->db->join("Accounts", "workflow_item_values.wiCompletedUser = Accounts.userId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    $this->db->join("Accounts as accountLastUpdatedBy", "workflow_item_values.wiLastUpdatedUser = accountLastUpdatedBy.userId", 'left');
    $this->db->join("dataPoints", "dataPoints.dpId = workflow_item_templates.wiTempDataPointId", 'left');
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_workflow_items_assigned($userId = "", $dmaId = "", $listingId = "", $wAttachedToType = "", $wiStatus = "") {

    if($userId == "" && $dmaId == "") {
      throw new \Exception('General fail');
    }

    $select = "workflow_items.*, workflow_item_values.*, workflow_item_templates.wiTempId, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempIsTemp, workflows.wAttachedToId, Accounts.userId, Accounts.firstName, Accounts.lastName, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName";
    if($wAttachedToType == "credit") {
      $select .= ", PendingListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, PendingListings.country_id, States.state, States.name, IncentivePrograms.ProgramName as incentiveProgramName";
    }
    $this->db->select($select);

    $this->db->from('workflow_item_values');
    $this->db->where('workflow_items.wiDeleteMarker', null);
    if($userId > 0) {
      $this->db->where('workflow_item_values.wiAssignedTo', $userId);
    }
    if($dmaId > 0) {
      $whereDmaId = "(workflows.wDmaId=" . $dmaId . " OR workflow_item_values.wiTaskDmaId=" . $dmaId . ")";
      $this->db->where($whereDmaId);
      //$this->db->where('workflows.wDmaId',$dmaId);
    }
    if($listingId > 0) {
      $whereListingId = "(workflow_item_values.wvAttachedToId='" . $listingId . "' OR workflow_item_values.wiTaskListingId='" . $listingId . "')";
      $this->db->where($whereListingId);
      //$this->db->where('workflows.wAttachedToId',$listingId);
    }
    if($wAttachedToType != "") {
      $whereAttachedToType = "(workflows.wAttachedToType='" . $wAttachedToType . "' OR workflow_item_values.wiTaskAttachedToType='" . $wAttachedToType . "')";
      $this->db->where($whereAttachedToType);
      //$this->db->where('workflows.wAttachedToType',$wAttachedToType);
    }
    if($wiStatus != "") {
      if($wiStatus == "pending") {
        $this->db->where('workflow_item_values.wiStatus !=', 1);
      }
      if($wiStatus == "completed") {
        $this->db->where('workflow_item_values.wiStatus', 1);
      }
      if($wiStatus == "inprogress") {
        $this->db->where('workflow_item_values.wiStatus', 2);
      }
      if($wiStatus == "unstarted") {
        $this->db->where('workflow_item_values.wiStatus', 0);
      }
    }
    if($listingId > 0) {
      $this->db->join("workflow_items", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId AND workflow_item_values.wvAttachedToId='$listingId'", 'left');
    } else {
      $this->db->join("workflow_items", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId", 'left');
    }
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    $this->db->join("workflow_lists", "workflow_items.wiListId = workflow_lists.wlId", 'left');
    $this->db->join("workflows", "workflow_lists.wlWorkflowId = workflows.wId", 'left');
    $this->db->join("Accounts", "workflow_item_values.wiCompletedUser = Accounts.userId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    if($listingId > 0) {
      $this->db->join("PendingListings", "workflows.wId = PendingListings.cWorkflowId", 'left');
    } else {
      $this->db->join("PendingListings", "workflow_item_values.wvAttachedToId = PendingListings.listingId", 'left');
    }
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');

    $this->db->order_by("workflow_items.wiId DESC");
    //$this->db->group_by("workflow_item_values.wvId");
    $this->db->distinct("workflow_item_values.wvAttachedToId");
    //$this->db->distinct();

    $return = [];
    $duplicateValueTracker = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      if($data['wiTempType'] == "signature") {
        $creditData = $this->get_min_credit_data($data['wiTaskListingId']);
        $data['wvAttachedToId'] = $creditData['listingId'];
        $data['stateCertNum'] = $creditData['stateCertNum'];
        $data['projectNameExt'] = $creditData['projectNameExt'];
        $data['state'] = $creditData['state'];
        $data['name'] = $creditData['name'];
      }
      //Name of status
      if($data['wiStatus'] == 2) {
        $data['statusName'] = "In Progress";
      } else if($data['wiStatus'] == 1) {
        $data['statusName'] = "Completed";
      } else {
        $data['statusName'] = "Pending";
      }
      //This only exists because the query above is duplicating records on the JOIN TBD
      if($data['wiAssignedTo'] == $userId && !in_array($data['wvId'], $duplicateValueTracker)) {
        array_push($return, $data);
        array_push($duplicateValueTracker, $data['wvId']);
      }
    }

    //var_dump($duplicateValueTracker); throw new \Exception('General fail');

    return $return;

  }

  function get_compliance_alerts($listingIds) {

    if(sizeof($listingIds) > 0) {
    } else {
      throw new \Exception('General fail');
    }

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;

    $select = "workflow_items.*, workflow_item_values.*, workflow_item_templates.wiTempId, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempIsTemp, workflows.wAttachedToId, Accounts.userId, Accounts.firstName, Accounts.lastName, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName, accountAssignedTo.userId as wiAssignedToUserId, creditOwner.title as creditOwnerTitle";
    $select .= ", PendingListings.listingId, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.country_id, States.state, States.name, IncentivePrograms.ProgramName, workflow_item_templates.wiTempDmaId, dmaAccounts.title as dmaTitle";
    $select .= ", $q2 as State, $q1 as name, $q1 as jurisdictionName, $q2 as parentJurisdictionState, $q1 as parentJurisdictionName";

    $this->db->select($select, false);

    $this->db->from('workflow_item_values');
    $this->db->where('workflow_item_values.wvDeleteMarker', null);
    $this->db->where('workflow_items.wiDeleteMarker', null);
    $this->db->where('workflows.wDeleteMarker', null);
    //$this->db->where('workflows.wDmaId',$dmaId);
    $this->db->where_in('workflow_item_values.wvAttachedToId', $listingIds);
    $this->db->where('workflows.wWorkflowType', 'compliance');

    $this->db->join("workflow_items", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId", 'left');
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    $this->db->join("workflow_lists", "workflow_items.wiListId = workflow_lists.wlId", 'left');
    $this->db->join("workflows", "workflow_lists.wlWorkflowId = workflows.wId", 'left');
    $this->db->join("Accounts", "workflow_item_values.wiCompletedUser = Accounts.userId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    $this->db->join("PendingListings", "workflow_item_values.wvAttachedToId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("dmaAccounts", "workflow_item_templates.wiTempDmaId = dmaAccounts.dmaId", 'left');
    $this->db->join("dmaAccounts as creditOwner", "creditOwner.mainAdmin = PendingListings.listedBy", 'left');
    $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
    $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');

    $this->db->order_by("workflow_items.wiCompletedDate1Expected DESC");
    //$this->db->group_by("workflow_item_values.wvId");
    $this->db->distinct("workflow_item_values.wvAttachedToId");
    //$this->db->distinct();

    $return = [];
    $duplicateValueTracker = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      $data['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : $data['projectName'];

      $data["isValueCompliant"] = true;

      $valuesAsText = $this->get_compliance_values_as_text($data);
      $data['wiValueAsText'] = $valuesAsText['wiValueAsText'];
      $data['wiValue1ExpectedAsText'] = $valuesAsText['wiValue1ExpectedAsText'];

      $data = $this->check_compliance_alerts($data);

      if(!$data["isValueCompliant"]) {
        array_push($return, $data);
      }

    }

    //Re-order the list by days
    /*
    usort($return['creditComplianceAlerts'], function($a, $b) {
       return $a['alertDateDifferenceDays'] - $b['alertDateDifferenceDays'];
    });
    */

    return $return;

  }

  function get_min_credit_data($listingId) {
    $this->db->select('PendingListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, States.state, States.name');
    $this->db->from('PendingListings');
    $this->db->where('PendingListings.listingId', $listingId);
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return[0];
  }

  function get_workflow_list_item_templates() {
    $this->db->select('workflow_item_templates.*, dataPoints.dpKey, dataPoints.dpValue, dataPoints.dpType, dataPoints.dpNameFull');
    $this->db->from('workflow_item_templates');
    $array['workflow_item_templates.wiTempIsTemp'] = 1;
    $this->db->where($array);
    $this->db->where('workflow_item_templates.wiTempDataPointId >', 0);
    $this->db->join("dataPoints", "dataPoints.dpId = workflow_item_templates.wiTempDataPointId", 'left');
    $this->db->order_by("workflow_item_templates.wiTempName ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function check_workflow_list_item_template_exists($wiTempName, $wiTempType, $dmaId) {

    $this->db->select('workflow_item_templates.*');
    $this->db->from('workflow_item_templates');
    $this->db->where('workflow_item_templates.wiTempName !=', 100);
    $this->db->where('workflow_item_templates.wiTempName', $wiTempName);
    $this->db->where('workflow_item_templates.wiTempType', $wiTempType);
    $this->db->where('workflow_item_templates.wiTempDmaId', $dmaId);
    $this->db->order_by("workflow_item_templates.wiTempName ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0]["wiTempId"];
    } else {
      return 0;
    }

  }

  function get_compliance_item_templates($wiTempIsTempDmaIds = "", $wiTempIsTemp = "") {
    $this->db->select('workflow_item_templates.*');
    $this->db->from('workflow_item_templates');
    if($wiTempIsTempDmaIds != "") {
      $this->db->where('workflow_item_templates.wiTempIsTemp', 100);
      $this->db->where_in('workflow_item_templates.wiTempDmaId', $wiTempIsTempDmaIds);
    } else {
      if($wiTempIsTemp > 0) {
        $array['workflow_item_templates.wiTempIsTemp'] = $wiTempIsTemp;
        $this->db->where($array);
      } else {
        $array['workflow_item_templates.wiTempIsTemp'] = 2;
        $this->db->where($array);
      }
    }
    $this->db->order_by("workflow_item_templates.wiTempName ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_workflow_list_item_template_by_data_point_id($wiTempDataPointId) {
    $this->db->select('workflow_item_templates.*, dataPoints.dpKey, dataPoints.dpValue, dataPoints.dpType, dataPoints.dpNameFull');
    $this->db->from('workflow_item_templates');
    $array['workflow_item_templates.wiTempDataPointId'] = $wiTempDataPointId;
    $this->db->where($array);
    $this->db->where('workflow_item_templates.wiTempDataPointId >', 0);
    $this->db->join("dataPoints", "dataPoints.dpId = workflow_item_templates.wiTempDataPointId", 'left');
    $this->db->order_by("workflow_item_templates.wiTempName ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_current_workflow_item_values_on_credit($listingId, $type) {
    $this->db->select('workflow_item_values.wvWorkflowItemId');
    $this->db->from('workflow_item_values');
    $array['workflow_item_values.wvAttachedToId'] = $listingId;
    $array['workflow_item_values.wvAttachedToType'] = $type;
    $this->db->where($array);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function check_workflow_items_for_due_dates($listingId, $creditData = "") {

    if($listingId > 0) {
    } else {
      return false;
    }

    $this->load->model('CreditListings');
    if($creditData == "") {
      $creditData = $this->CreditListings->get_credit_private($listingId);
    }

    $workflow = $this->get_workflow('', 'credit', $listingId);

    if(sizeof($workflow) > 0) {

      $deleteArray = [];

      foreach($workflow['wLists'] as $wLists) {
        foreach($wLists['wlItems'] as $wlItems) {

          $datesForThisItem = [];
          if($wlItems['wiDueDate'] > 0) {
            $thisItem = [];
            $thisItem['msAction'] = 'workflow_update';
            $thisItem['date'] = $wlItems['wiDueDate'];
            $thisItem['wiId'] = $wlItems['wiId'];
            $thisItem['wiTempName'] = 'Task Due: ' . $wlItems['wiTempName'] . ' - Assigned To: ' . $wlItems['assignedToAsText'];
            array_push($datesForThisItem, $thisItem);
          }
          if($wlItems['wiTempType'] == "date" && $wlItems['wiValue'] > 0 && $wlItems['wiTempDataPointId'] == "") {
            $thisItem = [];
            $thisItem['msAction'] = 'workflow_update';
            $thisItem['date'] = $wlItems['wiValue'];
            $thisItem['wiId'] = $wlItems['wiId'];
            $thisItem['wiTempName'] = $wlItems['wiTempName'];
            array_push($datesForThisItem, $thisItem);
          }

          //if (1) this workflow item has a due date or (2) this workflow item is connected to a data point, then do NOT double create an event reminder as it is already being created on the credit data point
          foreach($datesForThisItem as $d) {

            $alertDay = $this->CreditListings->calculate_alert_day($d['date']);

            $msAction = $d['msAction'];

            if($alertDay > -1) {

              //get workflow items matching this workflow item ID
              $wiSearchRequest['msWorkflowItemId'] = $d['wiId'];
              //$wiSearchRequest['msContent'] = $alertDay;
              //$wiSearchRequest['startTime'] = time()-86400; //Only return items withing LAST 24 HOURS so if a duplicate record exists in last 24 then don't re-make it (see below)
              $wiSearchRequest['distinctMsId'] = 1;
              $matchingMessages = $this->Members_Model->search_messages($wiSearchRequest);

              //Mark insert flag as TRUE
              $insertFlag = true;
              $thisYear = date('Y', $d['date']);
              $thisMonth = date('n', $d['date']);
              $thisDay = date('j', $d['date']);

              //Loop through existing messages
              foreach($matchingMessages as $mat) {
                if($mat['msYear'] == $thisYear && $mat['msMonth'] == $thisMonth && $mat['msDay'] == $thisDay && $mat['msContent'] == $alertDay) {
                  //If the existing date is the same as new date, then do nothing and insert flag as FALSE (already exists)
                  $insertFlag = false;
                }
              }

              //If insert flag is TRUE still, then insert the new record
              if($insertFlag) {

                //First delete existing records
                $this->Members_Model->search_and_delete_alert_messages($d['msAction'], "", "", $d['wiId']);

                $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
                $alertDayAsText = ($alertDay == 0) ? 'Today' : $alertDay . ' Day';

                //Insert message for calendar
                $msType = "calendar_alert";
                $msListingId = $listingId;
                $msBidId = "";
                $msTradeId = "";
                $msWorkflowItemId = $d['wiId'];
                $msTitle = $alertDayAsText . " Alert: '" . $d['wiTempName'] . "' due on " . date('m/d/Y', $d['date']) . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
                $msTitle2 = $alertDayAsText . " Alert: '" . $d['wiTempName'] . "' due on " . date('m/d/Y', $d['date']) . " - " . $creditData['title'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
                $msTitleShared = $msTitle2;
                $msTitleShort = $alertDayAsText . " Alert: '" . $d['wiTempName'] . "' due on " . date('m/d/Y', $d['date']);
                $msTitle2Short = "";
                $msTitleSharedShort = $msTitleShort;
                $msContent = $alertDay;
                $msContent2 = $alertDay;
                $msPerspective = "seller";
                $msPerspective2 = "";
                $firstDmaMainUserId = $creditData['listedBy'];
                $secondDmaMainUserId = "";
                $msUserIdCreated = (null !== ($this->cisession->userdata('userId'))) ? $this->cisession->userdata('userId') : $creditData['listedBy'];
                $alertShared = true;
                $msMessageId = $d['wiId'];
                $keepUnread = 1;
                $msCalendarDate = $d['date'];
                $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId, $keepUnread, $msWorkflowItemId, $msCalendarDate);

              }

            } else {
              array_push($deleteArray, $d['wiId']);
            }

          }

        }

      }

      //Delete calendar alerts that are no longer within 5 days
      foreach($deleteArray as $da) {
        $this->Members_Model->search_and_delete_alert_messages("", "", "", $da);
      }

    }

  }

  function countCommentsOnWorkflowItem($cmItemId) {
    $this->db->select('cmId');
    $this->db->from('Comments');
    $array = ['Comments.cmDeleteMarker' => null, 'Comments.cmConnectToType' => 'workflow_item', 'Comments.cmItemId' => $cmItemId];
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

  function insert_workflow_no_post($workflowData, $workflowAttachedId) {

    //If NOT a new template
    if($workflowAttachedId > 0) {
      $data = [
          'wAttachedToId'   => $workflowAttachedId,
          'wAttachedToType' => $workflowData['wAttachedToType'],
          'wWorkflowType'   => $workflowData['wWorkflowType'],
          'wDmaId'          => $this->cisession->userdata('dmaId'),
          'wParentwId'      => $workflowData['wId'],
          'wCreated'        => time(),
      ];
    } else {
      //If a new template
      $data = [
          'wAttachedToType' => $workflowData['wAttachedToType'],
          'wTemplateName'   => $workflowData['wTemplateName'],
          'wWorkflowType'   => $workflowData['wWorkflowType'],
          'wDmaId'          => $this->cisession->userdata('dmaId'),
          'wIsTemplate'     => 1,
          'wCreated'        => time(),
      ];
    }

    $this->db->insert('workflows', $data);

    return $this->db->insert_id();

  }

  function insert_workflow_list_no_post($workflowListData, $workflowId) {

    $data = [
        'wlWorkflowId' => $workflowId,
        'wlName'       => $workflowListData['wlName'],
        'wlOrder'      => $workflowListData['wlOrder'],
        'wlCreated'    => time(),
    ];
    if(isset($workflowListData["wlIsPrivate"])) {
      $data['wlIsPrivate'] = ($workflowListData['wlIsPrivate'] == 1) ? 1 : null;
    }
    $this->db->insert('workflow_lists', $data);

    return $this->db->insert_id();

  }

  function insert_workflow_list_item_no_post($workflowListItemData, $workflowListId) {

    $data = [
        'wiListId'             => $workflowListId,
        'wiTempId'             => $workflowListItemData['wiTempId'],
        'wiValue1Expected'     => $workflowListItemData["wiValue1Expected"],
        'wiComplianceReminder' => $workflowListItemData["wiComplianceReminder"],
        'wiComplianceAlert'    => $workflowListItemData["wiComplianceAlert"],
        'wiOrder'              => $workflowListItemData['wiOrder'],
        'wiCreated'            => time(),
    ];

    if(isset($workflowListItemData["wiCompletedDate1Expected"]) && $workflowListItemData["wiCompletedDate1Expected"] != "") {
      list($m, $d, $y) = explode("/", $workflowListItemData["wiCompletedDate1Expected"]);
      if(checkdate($m, $d, $y)) {
        $wiCompletedDate1Expected = strtotime($workflowListItemData["wiCompletedDate1Expected"]);
      } else {
        $wiCompletedDate1Expected = null;
      }
      $data['wiCompletedDate1Expected'] = $wiCompletedDate1Expected;
    }

    $this->db->insert('workflow_items', $data);

    return $this->db->insert_id();

  }

  function insert_task($request) {

    //First insert workflow item
    $wiTempId = isset($request['wiTempId']) ? $request['wiTempId'] : null;

    $data = [
        'wiTempId'  => $wiTempId,
        'wiCreated' => time(),
    ];

    $this->db->insert('workflow_items', $data);
    $wiId = $this->db->insert_id();

    $wiTaskDmaId = isset($request['wiTaskDmaId']) ? $request['wiTaskDmaId'] : null;
    $wiAssignedTo = isset($request['wiAssignedTo']) ? $request['wiAssignedTo'] : null;
    $wiTaskAttachedToType = isset($request['wiTaskAttachedToType']) ? $request['wiTaskAttachedToType'] : null;
    $wiTaskListingId = isset($request['wiTaskListingId']) ? $request['wiTaskListingId'] : null;
    $wiTaskState = isset($request['wiTaskState']) ? $request['wiTaskState'] : null;
    $wiInviteHash = isset($request['wiInviteHash']) ? $request['wiInviteHash'] : null;

    $data2 = [
        'wvWorkflowItemId'     => $wiId,
        'wiTaskDmaId'          => $wiTaskDmaId,
        'wiAssignedTo'         => $wiAssignedTo,
        'wiTaskAttachedToType' => $wiTaskAttachedToType,
        'wiTaskListingId'      => $wiTaskListingId,
        'wiTaskState'          => $wiTaskState,
        'wiInviteHash'         => $wiInviteHash,
        'wiStatus'             => 0,
    ];

    $this->db->insert('workflow_item_values', $data2);
    $this->db->insert_id();

    //Return the workflow item ID
    return $wiId;

  }

  function credit_where_access($input) { //NOTE:this function is also in CREDIT LISTINGS model
    if($input['dmaId'] > 0 && $input['dmaMemberId'] > 0) {
      $whereAccess = "((creditAccess.caAction='open' AND creditAccess.caDmaId=" . $input['dmaId'] . " AND creditAccess.caDmaMemberId IS NULL) OR ((creditAccess.caAction='edit' OR creditAccess.caAction='view') AND creditAccess.caDmaId=" . $input['dmaId'] . " AND creditAccess.caDmaMemberId=" . $input['dmaMemberId'] . "))";
      $this->db->where($whereAccess);
    } else {
      throw new \Exception('General fail');
    }
  }

  function get_tasks($request) {

    $dmaId = isset($request["dmaId"]) ? $request["dmaId"] : null;
    $userId = isset($request["userId"]) ? $request["userId"] : null;
    $listingId = isset($request["listingId"]) ? $request["listingId"] : null;
    $wAttachedToType = isset($request["wAttachedToType"]) ? $request["wAttachedToType"] : null;
    $wiStatus = isset($request["wiStatus"]) ? $request["wiStatus"] : null;

    if($userId == "" && $dmaId == "" && $listingId == "") {
      throw new \Exception('General fail');
    }

    $select = "workflow_items.*, workflow_item_values.*, workflows.*, workflow_item_templates.wiTempId, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempIsTemp, workflows.wAttachedToId, Accounts.userId, Accounts.firstName, Accounts.lastName, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName";
    $select .= ", PendingListings.listingId, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.country_id, States.state, States.name, IncentivePrograms.ProgramName";

    $this->db->select($select);

    $this->db->from('workflow_item_values');
    $this->db->where('workflow_items.wiDeleteMarker', null);
    $this->db->where('workflow_item_values.wiAssignedTo >', 0);
    if($userId > 0) {
      $this->db->where('workflow_item_values.wiAssignedTo', $userId);
    }
    if($dmaId > 0 && $listingId == "") {
      //$whereDmaId = "(workflows.wDmaId=".$dmaId." OR workflow_item_values.wiTaskDmaId=".$dmaId.")";
      //$this->db->where($whereDmaId);
      $this->db->where('workflows.wDmaId', $dmaId);
    }
    if($listingId > 0) {
      $whereListingId = "(workflow_item_values.wvAttachedToId='" . $listingId . "' OR workflow_item_values.wiTaskListingId='" . $listingId . "')";
      $this->db->where($whereListingId);
      //$this->db->where('workflows.wAttachedToId',$listingId);
    }
    if($wAttachedToType != "") {
      $whereAttachedToType = "(workflows.wAttachedToType='" . $wAttachedToType . "' OR workflow_item_values.wiTaskAttachedToType='" . $wAttachedToType . "')";
      $this->db->where($whereAttachedToType);
      //$this->db->where('workflows.wAttachedToType',$wAttachedToType);
    }
    if($wiStatus != "") {
      if($wiStatus == "pending") {
        $this->db->where('workflow_item_values.wiStatus !=', 1);
      }
      if($wiStatus == "completed") {
        $this->db->where('workflow_item_values.wiStatus', 1);
      }
      if($wiStatus == "inprogress") {
        $this->db->where('workflow_item_values.wiStatus', 2);
      }
      if($wiStatus == "unstarted") {
        $this->db->where('workflow_item_values.wiStatus', 0);
      }
    }
    //access
    $pRequest['dmaId'] = $dmaId;
    $pRequest['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
    $this->credit_where_access($pRequest);

    if($listingId > 0) {
      $this->db->join("workflow_items", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId AND workflow_item_values.wvAttachedToId='$listingId'", 'left');
    } else {
      $this->db->join("workflow_items", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId", 'left');
    }
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    $this->db->join("workflow_lists", "workflow_items.wiListId = workflow_lists.wlId", 'left');
    $this->db->join("workflows", "workflow_lists.wlWorkflowId = workflows.wId", 'left');
    $this->db->join("Accounts", "workflow_item_values.wiCompletedUser = Accounts.userId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    if($listingId > 0) {
      $this->db->join("PendingListings", "workflows.wId = PendingListings.cWorkflowId", 'left');
    } else {
      $this->db->join("PendingListings", "workflow_item_values.wvAttachedToId = PendingListings.listingId", 'left');
    }
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');

    $this->db->order_by("workflow_items.wiId ASC");
    //$this->db->group_by("workflow_item_values.wvId");
    $this->db->distinct("workflow_item_values.wvAttachedToId");
    //$this->db->distinct();

    $return = [];
    $duplicateValueTracker = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      if($data['wiTempType'] == "signature") {
        $creditData = $this->get_min_credit_data($data['wiTaskListingId']);
        $data['wvAttachedToId'] = $creditData['listingId'];
        $data['stateCertNum'] = $creditData['stateCertNum'];
        $data['projectNameExt'] = $creditData['projectNameExt'];
        $data['state'] = $creditData['state'];
        $data['name'] = $creditData['name'];
      }
      $data['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : $data['projectName'];
      //This only exists because the query above is duplicating records on the JOIN TBD
      if(!in_array($data['wvId'], $duplicateValueTracker)) {
        array_push($return, $data);
        array_push($duplicateValueTracker, $data['wvId']);
      }
    }

    //var_dump($duplicateValueTracker); throw new \Exception('General fail');

    return $return;

  }

  function get_task($wvId = "", $wiInviteHash = "") {

    $this->db->select('workflow_item_values.*');
    $this->db->from('workflow_item_values');
    $this->db->where('workflow_item_values.wiInviteHash', $wiInviteHash);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function insert_workflow_list_item_template_no_post($workflowListItemTemplateData) {

    $data = [
        'wiTempName'    => $workflowListItemTemplateData['wiTempName'],
        'wiTempDmaId'   => $this->cisession->userdata('dmaId'),
        'wiTempCreated' => time(),
    ];
    if(isset($workflowListItemTemplateData['wiTempIsTemp'])) {
      $data['wiTempIsTemp'] = $workflowListItemTemplateData['wiTempIsTemp'];
    }

    if(strlen($workflowListItemTemplateData['wiTempType']) > 1) {
      $data['wiTempType'] = $workflowListItemTemplateData['wiTempType'];
    }
    if(strlen($workflowListItemTemplateData['wiTempSubType']) > 1) {
      $data['wiTempSubType'] = $workflowListItemTemplateData['wiTempSubType'];
    }
    if(isset($workflowListItemTemplateData["wiTempDataPointId"])) {
      $data['wiTempDataPointId'] = ($workflowListItemTemplateData["wiTempDataPointId"] > 0) ? $workflowListItemTemplateData['wiTempDataPointId'] : null;
    }
    if(isset($workflowListItemTemplateData["wiTempIsPrivate"])) {
      $data['wiTempIsPrivate'] = ($workflowListItemTemplateData['wiTempIsPrivate'] == 1) ? 1 : null;
    }

    $this->db->insert('workflow_item_templates', $data);

    return $this->db->insert_id();

  }

  function insert_workflow_item_value($request) {

    $data = [
        'wvWorkflowItemId' => $request['wiId'],
        'wvAttachedToType' => $request['wvAttachedToType'],
        'wvAttachedToId'   => $request['wvAttachedToId'],
        'wiStatus'         => $request['wiStatus'],
        'wiValue'          => $request['wiValue'],
    ];

    if(isset($request['wiDueDate'])) {
      $data['wiDueDate'] = $request['wiDueDate'];
    }

    if(isset($request['wiLastUpdatedUser'])) {
      $data['wiLastUpdatedUser'] = $request['wiLastUpdatedUser'];
      $data['wiLastUpdatedDate'] = time();
    }

    if($request['wiStatus'] == 1) {
      $data['wiCompletedUser'] = $request['wiCompletedUser'];
      $data['wiCompletedDate'] = time();
    } else {
      $data['wiCompletedUser'] = null;
      $data['wiCompletedDate'] = null;
    }

    $this->db->insert('workflow_item_values', $data);

    return $this->db->insert_id();

  }

  function update_workflow_item_value($request) {

    $data = [
        'wiStatus' => $request['wiStatus'],
        'wiValue'  => $request['wiValue'],
    ];

    if(isset($request['wiDueDate'])) {
      $data['wiDueDate'] = $request['wiDueDate'];
    }

    if(isset($request['wiLastUpdatedUser'])) {
      $data['wiLastUpdatedUser'] = $request['wiLastUpdatedUser'];
      $data['wiLastUpdatedDate'] = time();
    }

    if(isset($request['wiCompletedUser'])) {
      if($request['wiCompletedUser'] > 0) {
        $data['wiCompletedUser'] = $request['wiCompletedUser'];
        $data['wiCompletedDate'] = time();
      } else {
        $data['wiCompletedUser'] = null;
        $data['wiCompletedDate'] = null;
      }
    }

    $this->db->where('workflow_item_values.wvId', $request['wvId']);
    $this->db->update('workflow_item_values', $data);

    //Check if any calendar alerts need to be created
    //$this->check_workflow_items_for_due_dates($_POST['workflowAttachedId']);

    return true;

  }

  function restart_workflow_item_value($request) {

    $data = [
        'wiStatus'          => 0,
        'wiLastUpdatedUser' => $request['wiLastUpdatedUser'],
        'wiLastUpdatedDate' => time(),
        'wiCompletedUser'   => null,
        'wiCompletedDate'   => null,
    ];

    $this->db->where('workflow_item_values.wvId', $request['wvId']);
    $this->db->update('workflow_item_values', $data);

    return true;

  }

  function assign_workflow_item($wvId, $wiAssignedTo) {

    $data = [
        'wiAssignedTo'   => $wiAssignedTo,
        'wiAssignedDate' => time(),
    ];

    $this->db->where('workflow_item_values.wvId', $wvId);
    $this->db->update('workflow_item_values', $data);

    return true;

  }

  function unassign_workflow_item($wvId) {

    $data = [
        'wiAssignedTo'   => null,
        'wiAssignedDate' => null,
    ];

    $this->db->where('workflow_item_values.wvId', $wvId);
    $this->db->update('workflow_item_values', $data);

    return true;

  }

  function update_workflow($wId, $wTemplateName) {

    $data = [
        'wTemplateName' => $wTemplateName,
    ];

    $this->db->where('workflows.wId', $wId);
    $this->db->update('workflows', $data);

    return true;

  }

  function update_compliance_template_configs($wId, $request) {

    $data = [
        'wTemplateName'        => $request['wTemplateName'],
        'wStartYearCompliance' => $request['wStartYearCompliance'],
        'wYearsCompliant'      => $request['wYearsCompliant'],
    ];

    $this->db->where('workflows.wId', $wId);
    $this->db->update('workflows', $data);

    return true;

  }

  function update_workflow_checklist($wlId, $workflowListData) {

    $data = [
        'wlName'  => $workflowListData['wlName'],
        'wlOrder' => $workflowListData['wlOrder'],
    ];
    if(isset($workflowListData["wlIsPrivate"])) {
      $data['wlIsPrivate'] = ($workflowListData['wlIsPrivate'] == 1) ? 1 : null;
    }

    $this->db->where('workflow_lists.wlId', $wlId);
    $this->db->update('workflow_lists', $data);

    return true;

  }

  function update_workflow_checklist_item_template($request) {

    $data = [
        'wiTempId'   => $request["wiTempId"],
        'wiTempName' => $request["wiTempName"],
    ];
    if(isset($request["wiTempIsPrivate"])) {
      $data['wiTempIsPrivate'] = ($request['wiTempIsPrivate'] == 1) ? 1 : null;
    }

    $this->db->where('workflow_item_templates.wiTempId', $request["wiTempId"]);
    $this->db->update('workflow_item_templates', $data);

    return true;

  }

  function update_workflow_checklist_item($wiId, $workflowListItemData) {

    $data = [
        'wiValue1Expected'     => $workflowListItemData["wiValue1Expected"],
        'wiComplianceReminder' => $workflowListItemData["wiComplianceReminder"],
        'wiComplianceAlert'    => $workflowListItemData["wiComplianceAlert"],
        'wiListId'             => $workflowListItemData['wiListId'],
        'wiOrder'              => $workflowListItemData['wiOrder'],
    ];

    if(isset($workflowListItemData["wiCompletedDate1Expected"])) {
      list($m, $d, $y) = explode("/", $workflowListItemData["wiCompletedDate1Expected"]);
      if(checkdate($m, $d, $y)) {
        $wiCompletedDate1Expected = strtotime($workflowListItemData["wiCompletedDate1Expected"]);
      } else {
        $wiCompletedDate1Expected = null;
      }
      $data['wiCompletedDate1Expected'] = $wiCompletedDate1Expected;
    }

    $this->db->where('workflow_items.wiId', $wiId);
    $this->db->update('workflow_items', $data);

    return true;

  }

  function delete_workflow($wId) {

    $data = [
        'wDeleteMarker' => 1,
    ];

    $this->db->where('workflows.wId', $wId);
    $this->db->update('workflows', $data);

    return true;

  }

  function delete_workflow_checklist($wlId) {

    $data = [
        'wlDeleteMarker' => 1,
    ];

    $this->db->where('workflow_lists.wlId', $wlId);
    $this->db->update('workflow_lists', $data);

    return true;

  }

  function delete_workflow_checklist_item($wiId) {

    if($wiId > 0) {

      $data = [
          'wiDeleteMarker' => 1,
      ];

      $this->db->where('workflow_items.wiId', $wiId);
      $this->db->update('workflow_items', $data);

      return true;

    }

  }

  function get_workflow_item_values($wvAttachedToType, $wWorkflowType, $wvAttachedToId) {

    if($wvAttachedToId > 0) {
    } else {
      throw new \Exception('General fail');
    }

    $this->db->select('workflow_item_values.*');

    $this->db->where('workflow_item_values.wvAttachedToType', $wvAttachedToType);
    $this->db->where('workflow_item_values.wvAttachedToId', $wvAttachedToId);
    $this->db->where('workflows.wWorkflowType', $wWorkflowType);

    $this->db->join("workflow_items", "workflow_item_values.wvWorkflowItemId = workflow_items.wiId", 'left');
    $this->db->join("workflow_lists", "workflow_items.wiListId = workflow_lists.wlId", 'left');
    $this->db->join("workflows", "workflow_lists.wlWorkflowId = workflows.wId", 'left');

    $this->db->from('workflow_item_values');

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      array_push($return, $data);

    }

    return $return;

  }

  function delete_workflow_item_values($workflowItemValuesIdsToDelete) {

    if(sizeof($workflowItemValuesIdsToDelete) > 0) {
    } else {
      throw new \Exception('General fail');
    }

    $data = [
        'wvDeleteMarker' => 1,
    ];

    $this->db->where_in('workflow_item_values.wvId', $workflowItemValuesIdsToDelete);

    $this->db->update('workflow_item_values', $data);

    return true;

  }

  function checkWorkflowTemplateExists($dmaId, $workflowTemplateTitle) {

    $this->db->select('workflows.wId');
    $this->db->from('workflows');
    $this->db->where('workflows.wDmaId', $dmaId);
    $this->db->where('workflows.wTemplateName', $workflowTemplateTitle);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0]['wId'];
    } else {
      return 0;
    }

  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
