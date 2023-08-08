<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Utilizations extends CI_Controller {

  protected $logger;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->load->library('memberpermissions');

    $this->lang->load('tank_auth');

    $this->load->model('IncentivePrograms');
    $this->load->model('CreditListings');
    $this->load->model('api_v1/CoreData');
    $this->load->model('Trading');
    $this->load->model('Members_Model');
    $this->load->model('AuditTrail');
    $this->load->model('Email_Model');

    $this->logger = Logger::getInstance();

  }

  function index($id = "") {

    $apiAccess = [];

    //Try and grab JSON request (IF IT EXISTS - as this could be a standard post from our website)
    $request = json_decode(file_get_contents('php://input'), true);

    //If this is an API request with a JSON payload (rather than a direct request from a CI Controller) then get API key access info
    if($request != "") {

      header("Content-Type:application/json");
      //Check if API exists
      $headers = apache_request_headers();
      if(isset($headers['Authorization'])) {
        $apiAccess['apiKey'] = $headers['Authorization'];
        $apiAccess['requestHost'] = $headers['Host'];
      } else {
        //No API key set
        throw new \Exception('General fail');
      }

      //ELSE this is a POST from our website (not API - there is a session)
    } else {

      //put the POST as the request
      $request = $this->input->post();

    }

    //Array to hold access and user data
    $accessData = $this->memberpermissions->getUserAccessData($apiAccess);

    //Determine function based on request method
    $method = $this->input->server('REQUEST_METHOD');
    $request['apiRequestAction'] = '';
    if($method == "POST") {
      if($id > 0) {
        $request['utilizationId'] = $id;
        $request['apiRequestAction'] = "update_utilization";
      } else {
        $request['apiRequestAction'] = "insert_utilization";
      }
    }
    if($method == "PUT") {
      //This isn't working - for some reason CI isn't accepting JSON Post fields in a PUT
    }
    if($method == "GET") {
      $request['apiRequestAction'] = 'get_utilization';
    }

    //Log request
    $LOG_request = json_encode($request);
    $this->logger->info("API > " . $request['apiRequestAction'] . " > Request > Data: " . $LOG_request);

    //Run Function
    if($request['apiRequestAction'] == "update_utilization") {
      $this->update_utilization($accessData, $request, $id);
    }
    if($request['apiRequestAction'] == "insert_utilization") {
      $this->insert_utilization($accessData, $request);
    }
    if($request['apiRequestAction'] == "get_utilization") {
      $this->get_utilization($accessData, $request, $id);
    }

  }

  private function get_utilization($accessData, $request, $id) {

    $request['utilizationId'] = $id;

    $permissionCheck = $this->memberpermissions->checkTradeAccess($id, 1, "", $accessData);

    //Try to insert
    if($permissionCheck['access']) {
      $response = $this->CoreData->get_utilization_api($accessData, $request);
    } else {
      $response = [];
      $response['code'] = 422;
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['credit'] = [];
      $response['errors'] = [
          ["code" => 9000, "field" => "utilizationId", "message" => "Either you submitted a non-valid Utilization ID or you do not have permission to access the data requested."],
      ];
    }

    echo json_encode($response);

  }

  private function insert_utilization($accessData, $request) {

    //If internal credit ID is set, but the OIX credit ID is NOT set, then let's find the matching credit

    //Check permission on credit
    $permissionCheck = $this->memberpermissions->checkCreditAccess($request['creditId'], 1, "creditEditorOnly", "", "", "", $accessData);

    if($permissionCheck['access']) {

      //Try to insert
      $request['apiRequestAction'] = "insert_utilization";
      $response = $this->CoreData->process_utilization_api($accessData, $request);
      //If successful, create access permissions to it and run general functions (audit trail, notifications, etc)
      if($response['success'] == 1) {

        $utilizationId = $response['utilization']['utilizationId'];
        $priorUtilizationData = [];
        $this->run_shared_scripts_for_utilization($accessData, $utilizationId, $priorUtilizationData, $request, $response['utilization']);

      }

    } else {

      $response = [];
      $response['code'] = 422;
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['credit'] = [];
      $response['errors'] = [
          ["code" => 9000, "field" => "creditId", "message" => "Either you submitted a non-valid ID or you do not have permission to access the data requested."],
      ];

    }

    echo json_encode($response);

  }

  private function update_utilization($accessData, $request, $id) {

    //Get utilization to check credit ID
    if($id > 0) {
      $priorUtilizationDataRaw = $this->Trading->get_trade($id);
      $priorUtilizationData = $priorUtilizationDataRaw['trade'];
    }

    //Check permission on credit
    $permissionCheck = $this->memberpermissions->checkCreditAccess($priorUtilizationData['listingId'], 1, "creditEditorOnly", "", "", "", $accessData);

    if($permissionCheck['access']) {

      //Try to update
      $request['apiRequestAction'] = "update_utilization";
      if(isset($request['utilizationStatus'])) {
        //allow it to pass through
      } else {
        //If not set, then we need to grab the current status as it's important for sub-sequent calculations
        $request['utilizationStatus'] = ($priorUtilizationData['tradeIsEstimated'] == 1) ? 'estimated' : 'actual';
      }
      $response = $this->CoreData->process_utilization_api($accessData, $request);

      //If successful, create access permissions to it and run general functions (audit trail, notifications, etc)
      if($response['success'] == 1) {

        $utilizationId = $response['utilization']['utilizationId'];
        $utilizationData = $response['utilization'];
        $this->run_shared_scripts_for_utilization($accessData, $utilizationId, $priorUtilizationData, $request, $utilizationData);

      }

    } else {

      $response = [];
      $response['code'] = 422;
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['credit'] = [];
      $response['errors'] = [
          ["code" => 10500, "field" => "access", "message" => "Either you submitted a non-valid ID or you do not have permission to access the data requested."],
      ];

    }

    echo json_encode($response);

  }

  function run_shared_scripts_for_utilization($accessData, $utilizationId, $priorUtilizationData, $request, $utilizationData) {

    //if new utilization
    $isNewUtilization = (count($priorUtilizationData) > 0) ? false : true;

    //Get new trade data
    $newUtilizationRaw = $this->Trading->get_trade($utilizationId);
    $newUtilization = $newUtilizationRaw['trade'];
    $creditId = $newUtilization['listingId'];

    //Update credit update time
    $this->CreditListings->updateTime($creditId);

    //SAVE CUSTOM DATA POINTS
    if(isset($request['customDataPoints']) && count($request['customDataPoints']) > 0) {
      foreach($utilizationData['customDataPoints'] as $cdp) {
        $cvRequest = [];
        if(isset($request['customDataPoints'][$cdp['dpValue']])) {

          $thisCdpv = $request['customDataPoints'][$cdp['dpValue']];
          if($cdp['type'] == 'date') {
            //convert date to unix
            $thisCdpv = ($thisCdpv != "") ? strtotime($thisCdpv) : null;
          }
          if($cdp['type'] == 'currencyNoDecimal') {
            if($thisCdpv == "") {
              $thisCdpv = null;
            } else {
              $thisCdpv = (float)preg_replace('/[^0-9.]*/', '', $thisCdpv);
              $thisCdpv = round($thisCdpv);
            }
          }
          //save it
          $cvRequest['dpId'] = $cdp['id'];
          $cvRequest['listingId'] = $utilizationData['utilizationId'];
          $cvRequest['value'] = $thisCdpv;
          $cvRequest['option_value_id'] = null;
          if($cdp['type'] == 'selectDropDown') {
            $cvRequest['value'] = null;
            $cvRequest['option_value_id'] = $thisCdpv;
          }

          $this->CreditListings->update_custom_data_point_value($cvRequest);

        }
      }

      //Now that custom data points are updated, get credit data again... Get pending listing of this shared credit
      $cRequest['creditId'] = $creditId;
      $newCreditData = $this->CoreData->get_credit_api($accessData, $cRequest);
      $creditData = $newCreditData['credit'];
      //$creditData = $this->CreditListings->get_credit_private($creditId);

    }

    //Saved for when we want to start auditing each part of the utilization independently
    //$priorUtilization = [];
    //$this->CreditListings->check_audit_trail('trade', $accessData, $priorUtilization, $newUtilization);

    //Insert audit trail record for utilization insert or update
    $auditData['audTypeId'] = ($isNewUtilization) ? 60 : 99;
    $auditData['audItemId'] = $creditId;
    $auditData['audItemIdSub'] = $utilizationId;
    $auditData['audValueBefore'] = ($isNewUtilization) ? '' : $priorUtilizationData['utilizationAuditSummary'];
    $auditData['audValueAfter'] = $newUtilization['utilizationAuditSummary'];
    $auditData['audUserId'] = $accessData['userId'];
    $auditData['audDmaId'] = $accessData['accountId'];
    $this->AuditTrail->insert_audit_item_api($auditData);

    //If this is a new utilization
    if($isNewUtilization) {

      //BUILD NOTIFICATION

      //If Estimated utilization
      if($newUtilization['tradeIsEstimated'] == 1) {

        $msAction = "new_utilization_estimate";
        $msTitle = "New Estimated Utilization - " . $newUtilization['projectNameFull'] . " - Credit Estimate Value (Local) " . number_format($newUtilization['tradeSizeLocal']) . " (" . $utilizationData['creditIdVisual'] . ")";
        $msTitle2 = "New Estimated Utilization by " . $accessData['accountName'] . " - '" . $newUtilization['projectNameFull'] . "' - Credit Estimate Value (Local) " . number_format($newUtilization['tradeSizeLocal']) . " (" . $utilizationData['creditIdVisual'] . ")";
        $msTitleShort = "New Estimated Utilization - Credit Estimate Value (Local) " . number_format($newUtilization['tradeSizeLocal']);
        $msTitleShared = $msTitle2;
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;

        //If actual utilization
      } else {

        //Insert message
        $msAction = "utilization_new";
        $msTitle = "New " . $newUtilization['utName'] . " Utilization (" . $newUtilization['creditIdVisual'] . "-" . $utilizationId . ") on Credit " . $newUtilization['creditIdVisual'] . " - (Local) " . number_format($newUtilization['tradeSizeLocal']) . " @ " . number_format($newUtilization['tradePrice'], 4);
        $msTitle2 = "";
        $msTitleShared = $msTitle;
        $msTitleShort = "New " . $newUtilization['utName'] . " Utilization (" . $newUtilization['creditIdVisual'] . "-" . $utilizationId . ") - (Local) " . number_format($newUtilization['tradeSizeLocal']) . " @ " . number_format($newUtilization['tradePrice'], 4);
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;

      }

      //Insert message
      $msType = "update";
      $msListingId = $creditId;
      $msBidId = "";
      $msTradeId = $utilizationId;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $accessData['accountOwnerUserId'];
      $secondDmaMainUserId = "";
      $msUserIdCreated = $accessData['userId'];
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    }

    //If actual utilization...
    if($newUtilization['tradeIsEstimated'] != 1) {

      //Reduce credit amount
      if($isNewUtilization) {

        //If new, use full amount
        $this->CreditListings->reduce_credit_remaining($creditId, $newUtilization['tradeSizeLocal']);

      } else {

        //Reduce credit amount by an equation
        $reduceBy = 0;
        $increaseBy = 0;
        //If previously was an estimate, and is now an actual, use full amount
        if($priorUtilizationData['tradeIsEstimated'] == 1) {
          $reduceBy = $newUtilization['tradeSizeLocal'];
        } else {
          if($priorUtilizationData['tradeSizeLocal'] > $newUtilization['tradeSizeLocal']) {
            $increaseBy = $priorUtilizationData['tradeSizeLocal'] - $newUtilization['tradeSizeLocal'];
          }
          if($priorUtilizationData['tradeSizeLocal'] < $newUtilization['tradeSizeLocal']) {
            $reduceBy = $newUtilization['tradeSizeLocal'] - $priorUtilizationData['tradeSizeLocal'];
          }
        }
        if($reduceBy > 0) {
          $this->CreditListings->reduce_credit_remaining($creditId, $reduceBy);
        }
        if($increaseBy > 0) {
          $increaseBy = -$increaseBy; //make it negative so it ADDs in the next function
          $this->CreditListings->reduce_credit_remaining($creditId, $increaseBy);
        }

        //see if a change has occurred...
        $changeHasOccurred = false;
        $changeMessage = '';

        if($priorUtilizationData['tradeSizeLocal'] != $newUtilization['tradeSizeLocal']) {
          $changeHasOccurred = true;
          $changeMessage .= 'Amount (' . number_format($priorUtilizationData['tradeSizeLocal']) . ' to ' . number_format($newUtilization['tradeSizeLocal']) . ') ';
        }
        if($priorUtilizationData['tradePrice'] != $newUtilization['tradePrice']) {
          $changeHasOccurred = true;
          $changeMessage .= 'Discount (' . number_format($priorUtilizationData['tradePrice'], 4) . ' to ' . number_format($newUtilization['tradePrice'], 4) . ') ';

        }
        if($priorUtilizationData['tExchangeRate'] != $newUtilization['tExchangeRate']) {
          $changeHasOccurred = true;
          $changeMessage .= 'FX Rate (' . number_format($priorUtilizationData['tExchangeRate'], 4) . ' to ' . number_format($newUtilization['tExchangeRate'], 4) . ') ';
        }
        if($priorUtilizationData['utName'] != $newUtilization['utName']) {
          $changeHasOccurred = true;
          $changeMessage .= 'Type (' . $priorUtilizationData['utName'] . ' to ' . $newUtilization['utName'] . ') ';

        }
        if($priorUtilizationData['utilizationDateUnix'] != $newUtilization['utilizationDateUnix']) {
          $changeHasOccurred = true;
          $changeMessage .= 'Date (' . date("m/d/Y", $priorUtilizationData['utilizationDateUnix']) . ' to ' . date("m/d/Y", $newUtilization['utilizationDateUnix']) . ') ';
        }
        if($priorUtilizationData['utilizingEntityName'] != $newUtilization['utilizingEntityName']) {
          $changeHasOccurred = true;
          $changeMessage .= 'Entity (' . $priorUtilizationData['utilizingEntityName'] . ' to ' . $newUtilization['utilizingEntityName'] . ') ';
        }

        //Insert message
        if($changeHasOccurred) {
          $changeMessage = ($changeMessage != '') ? ' (Change: ' . $changeMessage . ')' : '';
          $msType = "update";
          $msAction = "utilization_update";
          $msListingId = $creditId;
          $msBidId = 0;
          $msTradeId = $utilizationId;
          $msTitle = "Utilization Updated (" . $newUtilization['creditIdVisual'] . "-" . $utilizationId . ") on Credit " . $newUtilization['creditIdVisual'] . " - " . number_format($newUtilization['tradeSizeLocal']) . " @ " . number_format($newUtilization['tradePrice'], 4) . $changeMessage;
          $msTitle2 = "";
          $msTitleShared = $msTitle;
          $msTitleShort = $msTitle;
          $msTitle2Short = "";
          $msTitleSharedShort = $msTitleShort;
          $msContent = "";
          $msContent2 = "";
          $msPerspective = "buyer";
          $msPerspective2 = "";
          $firstDmaMainUserId = $accessData['accountOwnerUserId'];
          $secondDmaMainUserId = "";
          $msUserIdCreated = $accessData['userId'];
          $alertShared = true;
          $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        }

      }

      //VARIANCE - Estimated Credit Value
      if($isNewUtilization || ($priorUtilizationData['tradeSizeLocal'] != $newUtilization['tradeSizeLocal'] || $priorUtilizationData['tradePrice'] != $newUtilization['tradePrice'] || $priorUtilizationData['tExchangeRate'] != $newUtilization['tExchangeRate'])) {

        //Get new credit estimate at this moment in time
        $newCreditEstimateData = $this->CreditListings->get_credit_estimated_value($creditId);
        $newCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
        $newCreditEstFaceValue = $newCreditEstimateData['creditAmount'];
        $newCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
        $newCreditEstValue = $newCreditEstimateData['amountValueLocal'];
        //Get most recent Variance audit record for this credit to get the "prior" value
        $allCreditEstimates = $this->AuditTrail->get_audit_trail($creditId, 2, '');
        //If a record exists, use that data...
        if(sizeof($allCreditEstimates) > 0) {
          $priorCreditEstPrice = $allCreditEstimates[0]["audValAfter"];
          $priorCreditEstFaceValue = $allCreditEstimates[0]["audRelVal1After"];
          $priorCreditEstExchange = $allCreditEstimates[0]["audRelVal2After"];
          $priorCreditEstValue = $allCreditEstimates[0]["audRelVal3After"];
        } else {
          //If a record does NOT exist, use new data so no variance exists...
          $priorCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
          $priorCreditEstFaceValue = $newCreditEstimateData['creditAmount'];
          $priorCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
          $priorCreditEstValue = $newCreditEstimateData['amountValueLocal'];
        }

        $this->AuditTrail->insert_audit_item(2, $creditId, $priorCreditEstPrice, $newCreditEstPrice, $priorCreditEstFaceValue, $newCreditEstFaceValue, $priorCreditEstExchange, $newCreditEstExchange, $priorCreditEstValue, $newCreditEstValue, $utilizationId, 'utilization_edit');

      }

    }

    $this->CreditListings->check_utilization_for_due_dates($utilizationId);

  }

}






/* End of file admin.php */
/* Location: ./application/controllers/admin/admin.php */
