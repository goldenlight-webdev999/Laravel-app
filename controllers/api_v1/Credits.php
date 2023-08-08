<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Credits extends CI_Controller {

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
    $this->load->library('filemanagementlib');

    $this->lang->load('tank_auth');

    $this->load->model('IncentivePrograms');
    $this->load->model('CreditListings');
    $this->load->model('api_v1/CoreData');
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
        $request['creditId'] = $id;
        $request['apiRequestAction'] = "update_credit";
      } else {
        $request['apiRequestAction'] = "insert_credit";
      }
    }
    if($method == "PUT") {
      //This isn't working - for some reason CI isn't accepting JSON Post fields in a PUT
    }
    if($method == "GET") {
      $request['apiRequestAction'] = "get_credit";
    }

    //Log request
    $LOG_request = json_encode($request);
    $this->logger->info("API > " . $request['apiRequestAction'] . " > Request > Data: " . $LOG_request);

    //Run Function
    if($request['apiRequestAction'] == "update_credit") {
      $this->update_credit($accessData, $request);
    }
    if($request['apiRequestAction'] == "insert_credit") {
      $this->insert_credit($accessData, $request);
    }
    if($request['apiRequestAction'] == "get_credit") {
      $this->get_credit($accessData, $request);
    }

  }

  private function get_credit($accessData, $request) {

    $permissionCheck = $this->memberpermissions->checkCreditAccess($request['creditId'], 1, "", "", "", "", $accessData);

    //Try to insert
    if($permissionCheck['access']) {
      $response = $this->CoreData->get_credit_api($accessData, $request);
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

  private function insert_credit($accessData, $request) {

    //Try to insert
    $response = $this->CoreData->process_credit_api($accessData, $request);

    //Log it
    $LOG_response = json_encode($response);
    $this->logger->info("API > insert_credit > Response 1 " . $LOG_response);

    //If successful, create access permissions to it and run general functions (audit trail, notifications, etc)
    if($response['success'] == 1) {

      $inpData['adminAccessConfig'] = (isset($request['access']['adminAccessConfig'])) ? $request['access']['adminAccessConfig'] : 0; //If not set, allow access to all
      $inpData['adminUserIdsAccess'] = (isset($request['access']['adminUserIdsAccess'])) ? $request['access']['adminUserIdsAccess'] : [];
      $inpData['adminUserAccessLevels'] = (isset($request['access']['adminUserAccessLevels'])) ? $request['access']['adminUserAccessLevels'] : [];
      $inpData['customerId'] = $inpData['customerId'] = (isset($request['customerOwnerId'])) ? $request['customerOwnerId'] : null;
      $inpData['estCreditPrice'] = $response['credit']['creditAmountData']['estimatedValueAsPercent'];
      $inpData['estimated_payment_date'] = null;
      $inpData['qualifiedExpenditures'] = $response['credit']['qualifiedExpenditures'];

      $creditId = $response['credit']['creditId'];
      $creditData = $response['credit'];

      $finalUpdates = $this->run_shared_scripts_for_new_credit($accessData, $creditId, $inpData, $request, $creditData);

      $response['meta'] = array_merge($response['meta'], $finalUpdates);

    }

    echo json_encode($response);

  }

  private function update_credit($accessData, $request) {

    $permissionCheck = $this->memberpermissions->checkCreditAccess($request['creditId'], 1, "", "", "", "", $accessData);

    //Get credit BEFORE updating it
    if($request['creditId'] > 0) {
      $creditBefore = $this->CreditListings->get_credit_private($request['creditId']);
    } else {
      throw new \Exception('General fail');
    }

    //Try to update
    $response = $this->CoreData->process_credit_api($accessData, $request);

    //If successful, create access permissions to it and run general functions (audit trail, notifications, etc)
    if($response['success'] == 1) {

      $inpData = []; //to do later --> updating permissions
      $inpData['adminAccessConfig'] = (isset($request['access']['adminAccessConfig'])) ? $request['access']['adminAccessConfig'] : 0; //If not set, allow access to all
      $inpData['adminUserIdsAccess'] = (isset($request['access']['adminUserIdsAccess'])) ? $request['access']['adminUserIdsAccess'] : [];
      $inpData['adminUserAccessLevels'] = (isset($request['access']['adminUserAccessLevels'])) ? $request['access']['adminUserAccessLevels'] : [];
      $inpData['customerId'] = (isset($request['access']['customerId'])) ? $request['access']['customerId'] : null;
      $inpData['estCreditPrice'] = $response['credit']['creditAmountData']['estimatedValueAsPercent'];
      $inpData['estimated_payment_date'] = '';
      $inpData['qualifiedExpenditures'] = $response['credit']['qualifiedExpenditures'];

      $creditId = $response['credit']['creditId'];
      $creditData = $response['credit'];

      $finalUpdates = $this->run_shared_scripts_for_update_credit($accessData, $creditId, $inpData, $creditBefore, $request, $creditData);

      $response['meta'] = array_merge($response['meta'], $finalUpdates);

    }

    //Log it
    $LOG_response = json_encode($response);
    $this->logger->info("API > update_credit > Response > ID: " . $request['creditId'] . " > Data: " . $LOG_response);

    echo json_encode($response);

  }

  //Shared by both (i) load a credit and (ii) load a purchased credit (updating credits does not leverage this central function)
  function run_shared_scripts_for_update_credit($accessData, $creditId, $inpData, $creditBefore, $request, $creditData) {

    //Setup response fields
    $meta = [];
    $meta['updateFlags'] = [];

    //SAVE CUSTOM DATA POINTS
    if(isset($request['customDataPoints']) && sizeof($request['customDataPoints']) > 0) {
      foreach($creditData['customDataPoints'] as $cdp) {
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
          $cvRequest['listingId'] = $creditId;
          $cvRequest['value'] = $thisCdpv;
          $cvRequest['option_value_id'] = null;
          if($cdp['type'] == 'selectDropDown') {
            $cvRequest['value'] = null;
            $cvRequest['option_value_id'] = $thisCdpv;
          }
          $this->CreditListings->update_custom_data_point_value($cvRequest);

        }
      }
    }

    //Update credit Permissions
    $this->memberpermissions->updateCreditAccessPermissionsDMA($creditId, $accessData['accountId'], $inpData['adminAccessConfig'], $inpData['adminUserIdsAccess'], $inpData['adminUserAccessLevels']);

    //Check if a Cause for Adjustment was made
    if(isset($request['causesForAdjustment']) && count($request['causesForAdjustment']) > 0) {
      foreach($request['causesForAdjustment'] as $cfa) {
        $cfa['credit_id'] = $creditId;
        $cfa['created_by'] = $accessData['userId'];
        $this->processCauseForAdjustment($cfa);
      }
    }

    //Get new credit
    //WARNING - TO DO - This is the old data call necessary ONLY becuase we need to fedd it into the centralized audit trail function - so all the data point values need to match
    $creditUpdated = $this->CreditListings->get_credit_private($creditId);
    //WARNING - notice variable above has been reset with OLD data!!!!

    //see if the new credit and before credit are a match
    $noChangesOccurred = ($creditBefore == $creditUpdated) ? true : false;

    //IF CHANGES HAVE OCCURRED - THEN CONTINUE PROCESSING
    if(!$noChangesOccurred) {

      //Check credit updates
      $this->CreditListings->check_audit_trail('credit', $accessData, $creditBefore, $creditUpdated); //This runs complete audit trail logic
      //Check credit due dates
      $this->CreditListings->check_credit_for_due_dates($creditId);

    }

    //Prep variance success Message Code(see below)
    $varianceExists = 0;
    $estCreditValue_variance = 1;

    //VARIANCE - Estimated Credit Value
    //Get Variance audit record for this credit to get the "prior" value
    $allCreditEstimates = $this->AuditTrail->get_audit_trail($creditId, 2, '');
    //If variance is on, but there is no priro records, then create a NEW record
    if($creditUpdated['trackingVariance'] == 1 && sizeof($allCreditEstimates) == 0) {

      if($inpData['estCreditPrice'] == "" || $inpData['estCreditPrice'] == 0) {
        $newEstCreditPrice = 1;
      } else {
        $newEstCreditPrice = $inpData['estCreditPrice'];
      }
      $estValueAfter = $newEstCreditPrice * $creditUpdated['amountLocal'];
      $auditData['audTypeId'] = 2;
      $auditData['audItemId'] = $creditUpdated['listingId'];
      $auditData['audValueAfter'] = $newEstCreditPrice;
      $auditData['audRelVal1After'] = $creditUpdated['amountLocal'];
      $auditData['audRelVal2After'] = $creditUpdated['budgetExchangeRate'];
      $auditData['audRelVal3After'] = $estValueAfter;
      $auditData['audUserId'] = $accessData['userId'];
      $auditData['audDmaId'] = $accessData['accountId'];
      $this->AuditTrail->insert_audit_item_api($auditData);
      //add to response
      $meta['updateFlags'][] = 'varianceCreditValue';

      //TODO: change local currency to USD HERE
      //Insert message for credit estimate being updated
      $projectNameExt = ($creditUpdated['projectNameExt'] != "") ? " - " . $creditUpdated['projectNameExt'] : "";
      $msType = "update";
      $msAction = "credit_estimate_updated";
      $msListingId = $creditId;
      $msBidId = "";
      $msTradeId = "";
      $msTitle = "Credit Estimate Created - " . $creditUpdated['projectName'] . $projectNameExt . " -  $" . number_format($estValueAfter * $auditData['audRelVal2After']) . " (" . $creditUpdated['state'] . $creditUpdated['listingId'] . ")";
      $msTitle2 = "Credit Estimate Created by " . $accessData['accountName'] . " - '" . $creditUpdated['projectName'] . $projectNameExt . "' - $" . number_format($estValueAfter * $auditData['audRelVal2After']) . " (" . $creditUpdated['state'] . $creditUpdated['listingId'] . ")";
      $msTitleShared = $msTitle2;
      $msTitleShort = "Credit Estimate Created - $" . number_format($estValueAfter);
      $msTitle2Short = "Credit Estimate Created by " . $accessData['accountName'] . " - $" . number_format($estValueAfter);
      $msTitleSharedShort = $msTitle2Short;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $accessData['accountOwnerUserId'];
      $secondDmaMainUserId = "";
      $msUserIdCreated = $accessData['userId'];
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort);

      //Else, if variance is on, something has changed and there are prior variance records:
    } else {
      if($creditUpdated['trackingVariance'] == 1 && (($creditUpdated['estCreditPrice'] != $creditBefore['estCreditPrice']) || ($creditUpdated['amountLocal'] != $creditBefore['amountLocal']) || ($creditUpdated['budgetExchangeRate'] != $creditBefore['budgetExchangeRate']))) {
        //set variance flags
        $varianceExists = 1;
        $estCreditValue_variance = 2;

        //Get new credit estimate at this moment in time
        $newCreditEstimateData = $this->CreditListings->get_credit_estimated_value($creditId);
        $newCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
        $newCreditEstFaceValue = $newCreditEstimateData['amountLocal'];
        $newCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
        $newCreditEstValue = $newCreditEstimateData['amountValueLocal'];
        //Prepare prior estimates
        $priorCreditEstPrice = $allCreditEstimates[0]["audValAfter"];
        $priorCreditEstFaceValue = $allCreditEstimates[0]["audRelVal1After"];
        $priorCreditEstExchange = $allCreditEstimates[0]["audRelVal2After"];
        $priorCreditEstValue = $allCreditEstimates[0]["audRelVal3After"];

        $auditData['audTypeId'] = 2;
        $auditData['audItemId'] = $creditUpdated['listingId'];
        $auditData['audValueAfter'] = $newCreditEstPrice;
        $auditData['audRelVal1After'] = $newCreditEstFaceValue;
        $auditData['audRelVal2After'] = $newCreditEstExchange;
        $auditData['audRelVal3After'] = $newCreditEstValue;
        $auditData['audRelVal1Before'] = $priorCreditEstFaceValue;
        $auditData['audRelVal2Before'] = $priorCreditEstExchange;
        $auditData['audRelVal3Before'] = $priorCreditEstValue;
        $auditData['audUserId'] = $accessData['userId'];
        $auditData['audDmaId'] = $accessData['accountId'];
        $this->AuditTrail->insert_audit_item_api($auditData);
        //add to response
        $meta['updateFlags'][] = 'varianceCreditValue';

        $estValueBefore = $priorCreditEstValue * $priorCreditEstExchange;
        $estValueAfter = $newCreditEstValue * $newCreditEstExchange;

        //TODO: change local currency to USD HERE
        //Insert message for credit estimate being updated
        $projectNameExt = ($creditUpdated['projectNameExt'] != "") ? " - " . $creditUpdated['projectNameExt'] : "";
        $msType = "update";
        $msAction = "credit_estimate_updated";
        $msListingId = $creditId;
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Credit Estimate Updated - " . $creditUpdated['stateCertNum'] . $projectNameExt . " - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter) . " (" . $creditUpdated['state'] . $creditUpdated['listingId'] . ")";
        $msTitle2 = "Credit Estimate Updated by " . $accessData['accountName'] . " - '" . $creditUpdated['stateCertNum'] . $projectNameExt . "' - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter) . " (" . $creditUpdated['state'] . $creditUpdated['listingId'] . ")";
        $msTitleShared = $msTitle2;
        $msTitleShort = "Credit Estimate Updated - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter);
        $msTitle2Short = "Credit Estimate Updated by " . $accessData['accountName'] . " - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter);
        $msTitleSharedShort = $msTitle2Short;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $accessData['accountOwnerUserId'];
        $secondDmaMainUserId = "";
        $msUserIdCreated = $accessData['userId'];
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort);

      }
    }

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditUpdated['listingId'];
    $cData['newRecord'] = true;
    $this->CreditListings->update_credit_cache($cData);

    return $meta;

  }

  //Shared by both (i) load a credit and (ii) load a purchased credit (updating credits does not leverage this central function)
  function run_shared_scripts_for_new_credit($accessData, $creditId, $inpData, $request, $creditData) {

    //Setup response fields
    $meta = [];
    $meta['updateFlags'] = [];

    //Add new credit listing to audit trail
    $auditData['audTypeId'] = 1;
    $auditData['audItemId'] = $creditId;
    $auditData['audUserId'] = $accessData['userId'];
    $auditData['audDmaId'] = $accessData['accountId'];
    $this->AuditTrail->insert_audit_item_api($auditData);

    //Update credit Permissions
    $this->memberpermissions->updateCreditAccessPermissionsDMA($creditId, $accessData['accountId'], $inpData['adminAccessConfig'], $inpData['adminUserIdsAccess'], $inpData['adminUserAccessLevels'], "", "", 1);

    //SAVE CUSTOM DATA POINTS
    if(isset($request['customDataPoints']) && count($request['customDataPoints']) > 0) {
      foreach($creditData['customDataPoints'] as $cdp) {
        $cvRequest = [];
        if(isset($request['customDataPoints'][$cdp['dpValue']])) {

          $thisCdpv = $request['customDataPoints'][$cdp['dpValue']];
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
          $cvRequest['dpId'] = $cdp['id'];
          $cvRequest['listingId'] = $creditId;
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

    //Check if a Cause for Adjustment was made
    if(isset($request['causesForAdjustment']) && count($request['causesForAdjustment']) > 0) {
      foreach($request['causesForAdjustment'] as $cfa) {
        $cfa['credit_id'] = $creditId;
        $cfa['created_by'] = $accessData['userId'];
        $this->processCauseForAdjustment($cfa);
      }
    }

    //If loading this credit for a CUSTOMER
    if($inpData['customerId'] > 0) {

      $customerData = $this->Members_Model->get_member_by_id($inpData['customerId']);

      //Insert share (REVERSE SHARE)
      $sType = "credit"; //ok
      $sItemId = $creditId; //ok
      $sharedPrimId = $accessData['accountId'];
      $sharedSecId = $accessData['userId'];
      $sharerPrimId = $customerData["dmaAccounts"]["mainAdmin"][0]["dmaId"];
      $sharerSecId = $inpData['customerId'];
      $sId = $this->CreditListings->insert_share($sType, $sItemId, $sharedPrimId, $sharedSecId, $sharerPrimId, $sharerSecId, 1, 1, 1);
      //Now update share to auto accept so this account (advisor) has access
      $sUpdates['sStatus'] = 1; //Auto-accept it
      $sUpdates['sAcceptedDate'] = time();
      $this->CreditListings->update_share($sId, $sUpdates);

      //Insert an "open" permission on this credit for the customer DMA Account (commented out since the customer doesn't have access at this point - the customer must be granted access)
      //$this->CreditListings->insert_credit_permission($creditId, 'open', $customerData["dmaAccounts"]["all"][0]['dmaId'], "");

      //Insert message for new credit/project loaded the advisor (but not customer)
      $msType = "update";
      $msAction = "credit_loaded";
      $msListingId = $creditId;
      $msBidId = "";
      $msTradeId = "";
      $msTitle = "New Credit Loaded - " . $creditData['projectData']['projectNameFull'] . " (" . $creditData['incentiveProgram']['jurisdictionCode'] . $creditData['creditId'] . ")";
      $msTitle2 = "";
      $msTitleShared = $msTitle2;
      $msTitleShort = "Credit Loaded";
      $msTitle2Short = "";
      $msTitleSharedShort = $msTitle2Short;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $accessData['accountOwnerUserId'];
      $secondDmaMainUserId = "";
      $msUserIdCreated = $accessData['userId'];
      $alertShared = false; //this is false since this new credit is not yet shared with anyone
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort);

    } else {

      //Insert message for new credit loaded
      $projectNameExt = ($creditData['projectData']['projectNameExt'] != "") ? " - " . $creditData['projectData']['projectNameExt'] : "";
      $msType = "update";
      $msAction = "credit_loaded";
      $msListingId = $creditId;
      $msBidId = "";
      $msTradeId = "";
      $msTitle = "New Credit Loaded - " . $creditData['projectData']['projectNameFull'] . " (" . $creditData['incentiveProgram']['jurisdictionCode'] . $creditData['creditId'] . ")";
      $msTitle2 = "New Credit Loaded by " . $accessData['accountName'] . " - '" . $creditData['projectData']['projectNameFull'] . " (" . $creditData['incentiveProgram']['jurisdictionCode'] . $creditData['creditId'] . ")";
      $msTitleShared = $msTitle2;
      $msTitleShort = "Credit Loaded";
      $msTitle2Short = "Credit Loaded by " . $accessData['accountName'];
      $msTitleSharedShort = $msTitle2Short;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $accessData['accountOwnerUserId'];
      $secondDmaMainUserId = "";
      $msUserIdCreated = $accessData['userId'];
      $alertShared = false; //this is false since this new credit is not yet shared with anyone
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort);

    }

    //Get accounts this DMA is auto-sharing with
    $autoShareAccounts = $this->Members_Model->get_dma_autoshare_accounts($accessData['accountId'], 'credit');

    if(sizeof($autoShareAccounts) > 0) {

      //////////////////////////////////////////////
      //  START - AUTO-SHARE WITH PARENT ACCOUNT  //
      //////////////////////////////////////////////

      //Loop through the shared accounts
      foreach($autoShareAccounts as $sa) {

        $member = $this->Members_Model->get_member_by_id($sa['saUserIdTo']);
        $dma = $this->DmaAccounts->get_dma_account_by_id($sa['saDmaIdTo']);

        //Insert AUTO share
        $sType = "credit"; //ok
        $sItemId = $creditData['creditId']; //ok
        $sharedPrimId = $dma['mainAdmin'];
        $sharedSecId = $member['userId'];
        $sharerPrimId = $accessData['accountId'];
        $sharerSecId = $accessData['userId'];
        $sId = $this->CreditListings->insert_share($sType, $sItemId, $sharedPrimId, $sharedSecId, $sharerPrimId, $sharerSecId, "", 1);
        //Now update share
        $sUpdates['sStatus'] = 1; //Auto-accept it
        $sUpdates['sAcceptedDate'] = time();
        $this->CreditListings->update_share($sId, $sUpdates);

        //Insert an "open" permission on this credit for this DMA account
        $this->CreditListings->insert_credit_permission($creditId, 'open', $dma['dmaId'], "");

        //Add new credit share to audit trail
        $auditData['audTypeId'] = 160;
        $auditData['audItemId'] = $creditId;
        $auditData['audValueAfter'] = $dma['title'];
        $auditData['audUserId'] = $accessData['userId'];
        $auditData['audDmaId'] = $accessData['accountId'];
        $this->AuditTrail->insert_audit_item_api($auditData);

        //Send Email notice to person shared to
        /*
        $emailData['updateType'] = "newShareCredit";
        $emailData['firstName'] = $member['firstName'];
        $emailData['dmaTitle'] = $dma['title'];
        $emailData['credit'] = $creditData;
        $emailData['sharerName'] = $accessData['firstName'].' '.$accessData['lastName'];
        $emailData['sharerDmaTitle'] = $accessData['accountName'];
        $emailData['autoShare'] = TRUE;
        $emailData['newUser'] = FALSE;
        $emailData['welcomeNameTemplate'] = 1;
        $emailData['button'] = 1;
        $emailData['subject'] = 'Credit Shared With You - '.$accessData['accountName'].' - "'.$creditData["stateCertNum"].$projectNameExt.'"';
        $emailData['headline'] = 'New%20Credit%20Shared';
        $emailData['oixAdvisors'] = FALSE;
        $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $member['email'], $emailData);
        */

        //Insert message
        $msType = "update";
        $msAction = "share_new";
        $msListingId = $creditData['creditId'];
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Credit Shared with " . $dma['title'] . " - " . $creditData['projectData']['projectNameFull'] . " (" . $creditData['incentiveProgram']['jurisdictionCode'] . $creditData['creditId'] . ")";
        $msTitle2 = "Credit Shared by " . $accessData['accountName'] . " - " . $creditData['projectData']['projectNameFull'] . " (" . $creditData['incentiveProgram']['jurisdictionCode'] . $creditData['creditId'] . ")";
        $msTitleShort = "Credit Shared with " . $dma['title'];
        $msTitle2Short = "Credit Shared by " . $accessData['accountName'];
        $msTitleSharedShort = "";
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "shared";
        $firstDmaMainUserId = $accessData['accountOwnerUserId'];
        $secondDmaMainUserId = $dma['mainAdmin'];
        $msUserIdCreated = $accessData['userId'];
        $alertShared = false;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort);

      }

    }
    ////////////////////////////////////////////
    //  END - AUTO-SHARE WITH PARENT ACCOUNT  //
    ////////////////////////////////////////////

    //WARNING - TO DO - This is the old data call necessary ONLY becuase we need to fedd it into the centralized audit trail function - so all the data point values need to match
    $creditData = $this->CreditListings->get_credit_private($creditId);
    //WARNING - notice variable above has been reset with OLD data!!!!

    $priorCredit = [];
    $this->CreditListings->check_audit_trail('credit', $accessData, $priorCredit, $creditData);
    $this->CreditListings->check_credit_for_due_dates($creditId);

    //VARIANCE - Estimated credit value
    if($creditData['trackingVariance'] == 1) {
      if($inpData['estCreditPrice'] == "" || $inpData['estCreditPrice'] == 0) {
        $newEstCreditPrice = 1;
      } else {
        $newEstCreditPrice = $inpData['estCreditPrice'];
      }
      $estValueAfter = $newEstCreditPrice * $creditData['amountLocal'];
      $auditData['audTypeId'] = 2;
      $auditData['audItemId'] = $creditData['listingId'];
      $auditData['audValueAfter'] = $newEstCreditPrice;
      $auditData['audRelVal1After'] = $creditData['creditAmount'];
      $auditData['audRelVal2After'] = $creditData['budgetExchangeRate'];
      $auditData['audRelVal3After'] = $estValueAfter;
      $auditData['audUserId'] = $accessData['userId'];
      $auditData['audDmaId'] = $accessData['accountId'];
      $this->AuditTrail->insert_audit_item_api($auditData);
      //add to response
      $meta['updateFlags'][] = 'varianceCreditValue';

    }

    //VARIANCE - Qualified Expenditures
    if($creditData['trackingVariance'] == 1) {
      if($inpData['qualifiedExpenditures'] > 0) {
        $estValueAfter = $inpData['qualifiedExpenditures'];
        $auditData['audTypeId'] = 35;
        $auditData['audItemId'] = $creditData['listingId'];
        $auditData['audValueAfter'] = $inpData['qualifiedExpenditures'];
        $auditData['audRelVal3After'] = $estValueAfter;
        $auditData['audUserId'] = $accessData['userId'];
        $auditData['audDmaId'] = $accessData['accountId'];
        $this->AuditTrail->insert_audit_item_api($auditData);
        //add to response
        $meta['updateFlags'][] = 'varianceQualifiedExpenditures';
      }
    }

    //Generate Folders
    $fullId = $creditData['State'] . $creditData['listingId'];
    $this->filemanagementlib->createBoxFolders($fullId, $creditData['listingId'], "", "", 1);

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditData['listingId'];
    $cData['newRecord'] = true;
    $this->CreditListings->update_credit_cache($cData);

    return $meta;

  }

  function processCauseForAdjustment($cfa) {
    $cfaErrors = 0;
    $cfaErrors += ($cfa['data_point_key'] != '') ? 0 : 1;
    $cfaErrors += ($cfa['value'] != '') ? 0 : 1;
    $cfaErrors += ($cfa['adjustment_cause_id'] != '') ? 0 : 1;
    if($cfaErrors == 0) {
      $cfa_final = [];
      $cfa_final['credit_id'] = $cfa['credit_id'];
      $cfa_final['created_at'] = date('Y-m-d H:i:s');
      $cfa_final['created_by'] = $cfa['created_by'];
      $data_point_data = $this->CreditListings->get_data_point("", $cfa['data_point_key']);
      $cfa_final['data_point_id'] = $data_point_data['dpId'];
      $cfa_final['value'] = $cfa['value'];
      $cfa_final['initial_value'] = $cfa['initial_value'];
      $cfa_final['notes'] = $cfa['notes'];
      $cfa_final['adjustment_cause_id'] = $cfa['adjustment_cause_id'];
      $this->CoreData->insert_adjustment($cfa_final);
    }
  }

}





/* End of file admin.php */
/* Location: ./application/controllers/admin/admin.php */
