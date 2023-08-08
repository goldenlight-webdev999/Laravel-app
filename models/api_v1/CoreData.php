<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class CoreData extends CI_Model {

  protected $logger;

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('Email_Model');
    $this->load->model('CreditListings');

    $this->logger = Logger::getInstance();

  }

  function validate_numeric($number) {
    $validateCheck = [];
    if(is_numeric($number)) {
      $validateCheck['isValid'] = true;
    } else {
      $validateCheck['isValid'] = false;
      $validateCheck['errorMessage'] = "A numeric value is requred.";
    }

    return $validateCheck;
  }

  function validate_date($date) {
    //THIS DOES NOT ACTUALLY VALIDATE YET! JUST CONVERTS TO UNIX TIMESTAMP
    $validateCheck['isValid'] = true;
    $validateCheck['unixtimestamp_format'] = ($date != "") ? strtotime($date) : null;
    $validateCheck['datetime_format'] = ($date != "") ? date('Y-m-d H:i:s', strtotime($date)) : null;

    return $validateCheck;
  }


  /////////////////////////////////////
  // Credit Functions
  /////////////////////////////////////

  function get_credit_amount_process_data($input) {
    $data['budgetExchangeRate'] = (isset($input['budgetExchangeRate']) && $input['budgetExchangeRate'] > 0) ? $input['budgetExchangeRate'] : 1;
    $data['amountUSD'] = (isset($input['amountLocal']) && $input['amountLocal'] > 0) ? $input['amountLocal'] * $input['budgetExchangeRate'] : 0;
    $data['amountUSDRemaining'] = (isset($input['amountLocalRemaining']) && $input['amountLocalRemaining'] > 0) ? $input['amountLocalRemaining'] * $input['budgetExchangeRate'] : 0;

    return $data;
  }

  function validate_and_build_credit_data_request($accessData, $request) {
    $sData['dmaId'] = $accessData['accountId'];
    $sData['dmaMemberId'] = $accessData['dmaMemberId'];
    $projects = $this->CreditListings->get_filter_data_by_account('projects', $sData);

    /////////////////////////////////////
    // Setup Key Arrays
    /////////////////////////////////////

    $validation['creditData'] = []; //The data that passes validation
    $validation['customProgramData'] = []; //The data that passes validation
    $validation['customIncentiveProgramTypeData'] = []; //The data that passes validation
    $validation['taxEntityData'] = []; //The data that passes validation
    $validation['errors'] = []; //The data that fails validation

    /////////////////////////////////////
    // Data Validation and Preparation
    /////////////////////////////////////
    ///
    $isInsertCredit = ($request['apiRequestAction'] == "insert_credit") ? true : false;

    //oixIncentiveProgramId - OIX Incentive Program ID
    //if exists and is greater than 0
    if(isset($request['oixIncentiveProgramId']) && $request['oixIncentiveProgramId'] > 0) {
      $vCheck = $this->validate_numeric($request['oixIncentiveProgramId']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['OIXIncentiveId'] = $request['oixIncentiveProgramId'];
      } else {
        $thisError = ["code" => 1010, "field" => "oixIncentiveProgramId", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }

    //
    if(isset($request['creditId'])) {
      $validation['creditData']['listingId'] = $request['creditId'];
    }

    if(isset($request['customerOwnerId']) && $request['customerOwnerId'] > 0) {
      //Check if customerOwnerId  is a customer of this account (to avoid loading credit for wrong accounts)
      $this->db->select('cusUserId, dmaAccounts.dmaId');
      $this->db->where('Customers.cusDmaId', $accessData['accountId']);
      $this->db->where('Customers.cusStatus IN(1,2,0)');
      $this->db->join("dmaAccounts", "Customers.cusUserId = dmaAccounts.primUserId", 'left');
      $this->db->from('Customers');
      $query = $this->db->get();
      $customerMatch = false;
      $customerDmaId = 0;
      foreach($query->result_array() as $data) {
        if($data['cusUserId'] == $request['customerOwnerId']) {
          $customerMatch = true;
          $customerDmaId = $data['dmaId'];
        }
      }
      if($customerMatch) {
        $validation['creditData']['listedBy'] = $customerDmaId;
        //If set, also set this value to 1
        $validation['creditData']['cCustomerAccess'] = 1;
      } else {
        //Return error
        $thisError = ["code" => 1030, "field" => "customerOwnerId", "message" => "This customer ID is not registered as part of this account. Therefore, you can not load a credit on this customer's behalf."];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['creditOriginType'])) {
      $validation['creditData']['cOrigin'] = $request['creditOriginType'];
    } else {
      $validation['creditData']['cOrigin'] = 'loaded'; //Default to 'loaded' if empty
    }
    if(isset($request['taxEntityId'])) {
      $validation['creditData']['cTaxpayerId'] = $request['taxEntityId'];
    }
    if(isset($request['creditIssuedTo'])) {
      $validation['creditData']['creditIssuedTo'] = $request['creditIssuedTo'];
    }
    if(isset($request['amountLocal'])) {
      $validation['creditData']['creditAmount'] = $request['amountLocal'];
    } else {
      if($request['apiRequestAction'] == "insert_credit") {
        $validation['creditData']['creditAmount'] = 0; //Default to 0 if empty
      }
    }
    if(isset($request['vintageTaxYear'])) {
      $taxYearId = substr($request['vintageTaxYear'], -2);
      $validation['creditData']['taxYearId'] = $taxYearId;
    }
    if(isset($request['projectName'])) {
      if(isset($request['projectNameType']) && $request['projectNameType'] == 'hash') {
        foreach($projects as $project) {
          if(hash('sha1', $project['stateCertNum']) == $request['projectName']) {
            $validation['creditData']['stateCertNum'] = $project['stateCertNum'];
          }
        }
      } else {
        $validation['creditData']['stateCertNum'] = $request['projectName'];
      }
    }
    if(isset($request['projectNameExt'])) {
      $validation['creditData']['projectNameExt'] = $request['projectNameExt'];
    }
    if(isset($request['isArchived'])) {
      $cArchived = ($request['isArchived']) ? 1 : null; //if TRUE set to 1 (otherwise, NULL)
      $validation['creditData']['cArchived'] = $cArchived;
    }
    /*
    if(isset($request['regionId'])) {
      $validation['creditData']['country_id'] = $request['regionId'];
    }
    */
    if(isset($request['estimatedValueAsPercent'])) {
      $validation['creditData']['estCreditPrice'] = $request['estimatedValueAsPercent'];
    } else {
      if($request['apiRequestAction'] == "insert_credit") {
        $validation['creditData']['estCreditPrice'] = 1; //Set to $1 if empty
      }
    }
    if(isset($request['purchasedAtPrice'])) {
      $validation['creditData']['purchasedAtPrice'] = $request['purchasedAtPrice'];
    }

    if(isset($request['projectBudget'])) {
      $validation['creditData']['projectBudgetEst'] = $request['projectBudget'];
    }
    if(isset($request['qualifiedExpenditures'])) {
      $validation['creditData']['qualifiedExpenditures'] = $request['qualifiedExpenditures'];
    }
    if(isset($request['incentiveRate'])) {
      $validation['creditData']['incentiveRate'] = $request['incentiveRate'];
    }
    if(isset($request['certificationNumber'])) {
      $validation['creditData']['certificationNum'] = $request['certificationNumber'];
    }
    if(isset($request['localCurrency'])) {
      $validation['creditData']['localCurrency'] = $request['localCurrency'];
    } else {
      $validation['creditData']['localCurrency'] = 'USD';
    }
    if(isset($request['exchangeRate']) && $request['exchangeRate'] > 0) {
      $validation['creditData']['budgetExchangeRate'] = $request['exchangeRate'];
    } else {
      $validation['creditData']['budgetExchangeRate'] = 1;
    }
    if(isset($request['typeOfWork'])) {
      $validation['creditData']['typeOfWork'] = $request['typeOfWork'];
    }
    if(isset($request['internalId'])) {
      $validation['creditData']['internalId'] = $request['internalId'];
    }
    if(isset($request['finalCreditAmountFlag'])) {
      $validation['creditData']['finalCreditAmountFlag'] = $request['finalCreditAmountFlag'];
    }
    if(isset($request['generalNotes'])) {
      $validation['creditData']['cNotes'] = $request['generalNotes'];
    }
    if(isset($request['statusNotes'])) {
      $validation['creditData']['statusNotes'] = $request['statusNotes'];
    }
    if(isset($request['trackingVariance'])) {
      $validation['creditData']['trackingVariance'] = $request['trackingVariance'];
    }
    if(isset($request['carryForwardYears'])) {
      $validation['creditData']['cCarryForwardYears'] = $request['carryForwardYears'];
    }
    if(isset($request['legislativeFramework'])) {
      $validation['creditData']['legislativeFramework'] = $request['legislativeFramework'];
    }

    if(isset($request['incentiveProgramTypeId'])) {
      $validation['creditData']['credit_type_id'] = $request['incentiveProgramTypeId'];
    }
    /*
    if(isset($request['incentiveProgramType'])) {
      $validation['incentiveProgramType'] = $request['incentiveProgramType'];
    }
    */

    //Status
    if(isset($request['statusIdCertification'])) {
      $validation['creditData']['certificationStatus'] = $request['statusIdCertification'];
    }
    if(isset($request['statusIdProject'])) {
      $validation['creditData']['projectStatus'] = $request['statusIdProject'];
    }
    if(isset($request['statusIdAudit'])) {
      $validation['creditData']['auditStatusId'] = $request['statusIdAudit'];
    }
    if(isset($request['statusIdMonetization'])) {
      $validation['creditData']['statusMonetization'] = $request['statusIdMonetization'];
    }

    //Dates
    if(isset($request['dateCertificationInitial'])) {
      $vCheck = $this->validate_date($request['dateCertificationInitial']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['est_initial_cert_dt'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1410, "field" => "dateCertificationInitial", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateCertificationFinalEst'])) {
      $vCheck = $this->validate_date($request['dateCertificationFinalEst']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['est_final_cert_dt'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1411, "field" => "dateCertificationFinal", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateProjectStart'])) {
      $vCheck = $this->validate_date($request['dateProjectStart']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['projectStartDate'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1412, "field" => "dateProjectStart", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateProjectEnd'])) {
      $vCheck = $this->validate_date($request['dateProjectEnd']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['projectEndDate'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1413, "field" => "dateProjectEnd", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateAuditStart'])) {
      $vCheck = $this->validate_date($request['dateAuditStart']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['auditStartDate'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1414, "field" => "dateAuditStart", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateAuditEnd'])) {
      $vCheck = $this->validate_date($request['dateAuditEnd']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['auditEndDate'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1415, "field" => "dateAuditEnd", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateCreditIssue'])) {
      $vCheck = $this->validate_date($request['dateCreditIssue']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['IssueDate'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1416, "field" => "dateCreditIssue", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['dateLastDayPrincipalPhotography'])) {
      $vCheck = $this->validate_date($request['dateLastDayPrincipalPhotography']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['lastDayPrincipalPhotography'] = $vCheck['unixtimestamp_format'];
      } else {
        $thisError = ["code" => 1417, "field" => "dateLastDayPrincipalPhotography", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    if(isset($request['purchasedAtDate'])) {
      $vCheck = $this->validate_date($request['purchasedAtDate']); //Validate
      if($vCheck['isValid']) {
        $validation['creditData']['purchasedAtDate'] = $vCheck['datetime_format'];
      } else {
        $thisError = ["code" => 1418, "field" => "purchasedAtDate", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }

    //CUSTOM INCENTIVE PROGRAM DATA

    //Process jurisdiction information: 1) first check if google place ID is set, if not 2) run search against Google Maps to find it
    if(isset($request['customProgram']['jurisdictionId']) && $request['customProgram']['jurisdictionId'] > 0) {

      $validation['customProgramData']['jurisdiction_id'] = $request['customProgram']['jurisdictionId'];
      $validation['creditData']['jurisdiction_id'] = $request['customProgram']['jurisdictionId'];

    } else if(isset($request['customProgram']['googlePlaceId'])) {

      //Google Places Search
      $googlePlaceId = $request['customProgram']['googlePlaceId'];
      $this->jurisdictionSvc = new \OIX\Services\JurisdictionService($this->config->item('google_maps_key'));
      $jurisdictionId = $this->jurisdictionSvc->getByPlaceId($googlePlaceId);
      $validation['customProgramData']['jurisdiction_id'] = $jurisdictionId;
      $validation['creditData']['jurisdiction_id'] = $jurisdictionId;

    } else if(isset($request['customProgram']['countryName'])) {

      $jursdictionSearchString = '';
      $jursdictionSearchString .= (isset($request['customProgram']['townName']) && $request['customProgram']['townName'] != '') ? $request['customProgram']['townName'] . ' ' : '';
      $jursdictionSearchString .= (isset($request['customProgram']['countyName']) && $request['customProgram']['countyName'] != '') ? $request['customProgram']['countyName'] . ' ' : '';
      $jursdictionSearchString .= (isset($request['customProgram']['provinceName']) && $request['customProgram']['provinceName'] != '') ? $request['customProgram']['provinceName'] . ' ' : '';
      $jursdictionSearchString .= (isset($request['customProgram']['countryName']) && $request['customProgram']['countryName'] != '') ? $request['customProgram']['countryName'] : '';

      //Google Places Search
      $this->jurisdictionSvc = new \OIX\Services\JurisdictionService($this->config->item('google_maps_key'));
      $jurisdictionId = $this->jurisdictionSvc->getByName($jursdictionSearchString);
      $validation['customProgramData']['jurisdiction_id'] = $jurisdictionId;
      $validation['creditData']['jurisdiction_id'] = $jurisdictionId;
    }

    if(isset($request['customProgram']['incentiveProgramName'])) {
      $validation['customProgramData']['ProgramName'] = $request['customProgram']['incentiveProgramName'];
    }
    if(isset($request['customProgram']['programNotes'])) {
      $validation['customProgramData']['dmaNotes'] = $request['customProgram']['programNotes'];
    }
    if(isset($request['customProgram']['governmentAgencyName'])) {
      $validation['customProgramData']['ApprovingAgency'] = $request['customProgram']['governmentAgencyName'];
    }
    if(isset($request['customProgram']['statuteNumber'])) {
      $validation['customProgramData']['StatuteNumber'] = $request['customProgram']['statuteNumber'];
    }
    if(isset($request['customProgram']['incentiveProgramDetail'])) {
      $validation['customProgramData']['IncentiveDetail'] = $request['customProgram']['incentiveProgramDetail'];
    }
    if(isset($request['customProgram']['categoryId'])) {
      $validation['customProgramData']['Category'] = $request['customProgram']['categoryId'];
    }
    if(isset($request['customProgram']['sectorId'])) {
      $validation['customProgramData']['Sector'] = $request['customProgram']['sectorId'];
    }

    //Custom Program Type Data
    if(isset($request['customIncentiveProgramType']['incentiveProgramTypeName'])) {
      $validation['customIncentiveProgramTypeData']['ProgramTypeName'] = $request['customIncentiveProgramType']['incentiveProgramTypeName'];
    }

    //TAXPAYER DATA
    if(isset($request['taxEntityCompanyName'])) {
      $validation['taxEntityData']['tpCompanyName'] = $request['taxEntityCompanyName'];
    }

    /////////////////////////////////////
    // Run check on minimum required fields
    /////////////////////////////////////

    if($request['apiRequestAction'] == "insert_credit") {

      //Credit Related Data
      if(!isset($request['oixIncentiveProgramId']) && !isset($request['customProgram']['incentiveProgramName'])) {
        $thisError = ["code" => 1510, "field" => "oixIncentiveProgramId, incentiveProgramName", "message" => "One of these fields is required."];
        array_push($validation['errors'], $thisError);
      }
      if(!isset($request['taxEntityId']) && !isset($request['taxEntityCompanyName']) && !isset($request['customerOwnerId'])) {
        $thisError = ["code" => 1530, "field" => "taxEntityId, taxEntityCompanyName", "message" => "One of these fields is required."];
        array_push($validation['errors'], $thisError);
      }
      //Incentive Program Related Data
      /*
      if(isset($request['customProgram']['incentiveProgramName']) && !isset($request['oixIncentiveProgramId'])) {
        if(!isset($request['customProgram']['jurisdictionCode'])) {
          $thisError = array("code"=>1610,"field"=>"jurisdictionCode","message"=>"When creating a custom Incentive Program - this field is required.");
          array_push($validation['errors'], $thisError);
        }
        if(!isset($request['customProgram']['regionId'])) {
          $thisError = array("code"=>1620,"field"=>"regionId","message"=>"When creating a custom Incentive Program - this field is required.");
          array_push($validation['errors'], $thisError);
        }
      }
      */

    }

    if($request['apiRequestAction'] == "get_credit" || $request['apiRequestAction'] == "update_credit") {

      //Credit ID (required)
      if(is_numeric($request["creditId"]) && $request["creditId"] > 0) {
        //OK
      } else {
        $thisError = ["code" => 2000, "field" => "creditId", "message" => "Either you submitted a non-valid ID or you do not have permission to access the data requested."];
        array_push($validation['errors'], $thisError);
      }

    }

    if($request['apiRequestAction'] == "update_credit") {

      //total amount minus the amount traded minus the amount ACTUALLY utilized fixed
      if(isset($validation['creditData']['creditAmount'])) {
        $this->load->model('Trading');
        //Get utilizations already FIXED (ie; actual or estimated with fixed amount (versus percentage based))
        $totalUtilizationAmountActualRaw = $this->Trading->get_total_trade_amount_on_listing($request["creditId"]);
        $totalUtilizationAmountActual = $totalUtilizationAmountActualRaw['totalTradeAmount'];
        $totalUtilizationAmountEstimatedFixedRaw = $this->Trading->get_total_trade_amount_on_listing($request["creditId"], '', '', 1, 1);
        $totalUtilizationAmountEstimatedFixed = $totalUtilizationAmountEstimatedFixedRaw['totalTradeAmount'];
        $totalUtilizationAmount = $totalUtilizationAmountActual + $totalUtilizationAmountEstimatedFixed;
        //Get and check available to list
        $validation['creditData']['availableToList'] = $validation['creditData']['creditAmount'] - $totalUtilizationAmountActual;
        if($validation['creditData']['availableToList'] < 0) {
          $thisError = ["code" => 2400, "field" => "amountLocal", "message" => "Your Credit Amount is too low. It must be greater than the amount already utilized (which includes actual utilizations and estimated utilizations that have a fixed estimated value - rather than a percentage based estimated value)."];
          array_push($validation['errors'], $thisError);
        }

      }

    }

    /////////////////////////////////////
    // Return the Result
    /////////////////////////////////////

    return $validation;

  }

  function process_credit_api($accessData, $request) {

    /////////////////////////////////////
    // Setup Key Arrays
    /////////////////////////////////////

    $response = [];
    $response['code'] = 200;
    $response['success'] = 1;
    $response['credit'] = [];
    $response['errors'] = [];
    $response['meta'] = [];

    /////////////////////////////////////
    // Data Validation and Preparation
    /////////////////////////////////////

    //$request['apiRequestAction'] = "insert_credit";
    $validationResult = $this->validate_and_build_credit_data_request($accessData, $request);
    $creditData = $validationResult['creditData'];
    $customProgramData = $validationResult['customProgramData'];
    $customProgramTypeData = $validationResult['customIncentiveProgramTypeData'];
    $taxEntityData = $validationResult['taxEntityData'];
    $errors = $validationResult['errors'];

    //If errors, return them and stop processing the script
    if(sizeof($errors) > 0) {
      $response['code'] = 422; //422 = "Unprocessable Entity"
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['errors'] = $errors;
      $response['meta'] = [];

      return $response;
    }

    $this->load->model('IncentivePrograms');

    /////////////////////////////////////
    // (Option) Create Custom Incentive Program, If Desired
    /////////////////////////////////////

    if(!isset($creditData['OIXIncentiveId']) && isset($customProgramData) && sizeof($customProgramData) > 0) {

      //Check if this Custom Program has already been added by this account
      $cpData = [];
      //$cpData['pDmaId'] = ($request['parentAccountId'] > 0) ? [$request['parentAccountId'], $accessData['accountId']] : [$accessData['accountId']];
      $cpData['ProgramName'] = $customProgramData['ProgramName'];
      $cpData['jurisdiction_id'] = $customProgramData['jurisdiction_id'];
      $existingCustomProgramData = $this->IncentivePrograms->check_if_program_exists($cpData);

      $incentiveProgramIdToUse = 0;
      $pDmaIdArray = ($request['parentAccountId'] > 0) ? [$request['parentAccountId'], $accessData['accountId']] : [$accessData['accountId']];
      foreach($existingCustomProgramData as $ep) {
        if(in_array($ep['pDmaId'], $pDmaIdArray)) { //Prioritize custom program
          $incentiveProgramIdToUse = $ep['OIXIncentiveId'];
        } else if($incentiveProgramIdToUse == 0 && $ep['pDmaId'] == '') { //Fall back to generic program
          $incentiveProgramIdToUse = $ep['OIXIncentiveId'];
        }
      }

      //Check if a program exists
      if($incentiveProgramIdToUse > 0) {

        //If a program exists and it is either a general one or a custom one created by this account or its parent account
        $creditData['OIXIncentiveId'] = $incentiveProgramIdToUse;

      } else {

        $customProgramLockedData = [
            'Status'        => 5,
            'pDmaId'        => $accessData['accountId'],
            'ipdbtimestamp' => date('Y-m-d', time()),
        ];

        //Prepare final data request
        $newCustomProgramData = array_merge($customProgramLockedData, $customProgramData);

        //Insert and set the program ID for the subsequent credit insert
        $creditData['OIXIncentiveId'] = $this->IncentivePrograms->insert_custom_incentive_program($newCustomProgramData);

      }

    }

    /////////////////////////////////////
    // (Option) Create Custom Program Type, If Desired
    /////////////////////////////////////

    if(!isset($creditData['credit_type_id']) && isset($customProgramTypeData) && sizeof($customProgramTypeData) > 0) {

      //Check if this Custom Program has already been added by this account
      $cptData = [];
      $cptData['ProgramTypeName'] = $customProgramTypeData['ProgramTypeName'];
      $existingCustomProgramTypeId = $this->IncentivePrograms->check_if_program_type_exists($cptData);

      //If it does not exist, then add it
      if($existingCustomProgramTypeId == 0) {

        //Insert and set the program ID for the subsequent credit insert
        $customProgramTypeData['ProgramTypeDmaId'] = $accessData['accountId'];
        $creditData['credit_type_id'] = $this->IncentivePrograms->insert_custom_program_type($customProgramTypeData);

      } else {

        //If this custom program DOES exist for this Account, then re-set the oixIncentiveProgramId to equal this
        $creditData['credit_type_id'] = $existingCustomProgramTypeId;

      }

    }

    /*
    $emailData = array();
    $emailData['updateType'] = 'fox_data_integration_error_report';
    $emailData['apiErrorCount'] = 1;
    $testData = array($creditData);
    $emailData['apiErrors'] = $testData;
    $emailData['emailSubject'] = 'Fox Output:';
    $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_dev_emails_array"), $emailData);
    */

    /////////////////////////////////////
    // (Option) Create Legal Entity, If Desired
    /////////////////////////////////////

    if((!isset($creditData['cTaxpayerId']) || $creditData['cTaxpayerId'] < 1) && isset($taxEntityData['tpCompanyName'])) {

      //Check if this Custom Program has already been added by this account
      $this->load->model('Taxpayersdata');
      $tpData = [];
      $tpData['dmaAccountIds'] = [$accessData['accountId']];
      $tpData['tpCompanyName'] = $taxEntityData['tpCompanyName'];
      $existingTaxEntityId = $this->Taxpayersdata->check_if_tax_entity_exists($tpData);

      //If it does not exist, then add it
      if($existingTaxEntityId == 0) {

        $ntpData = [];
        $ntpData['dmaAccountId'] = $accessData['accountId'];
        $ntpData['tpCompanyName'] = $taxEntityData['tpCompanyName'];
        $ntpData['tpAccountType'] = 1;
        $ntpData['tpTimestamp'] = time();
        $ntpData['addedBy'] = $accessData['userId'];
        $creditData['cTaxpayerId'] = $this->Taxpayersdata->insert_taxpayer_api($ntpData);

      } else {

        $creditData['cTaxpayerId'] = $existingTaxEntityId;

      }

    }

    if($request['apiRequestAction'] == "insert_credit") {

      /////////////////////////////////////
      // Create Credit
      /////////////////////////////////////

      $response['meta']['action'] = 'insert_credit';

      if(isset($creditData['listedBy']) && $creditData['listedBy'] > 0) {
        $thisListedBy = $creditData['listedBy']; //This is validated in validation function above
      } else {
        //Otherwise use this account from accessData (loading for self)
        $thisListedBy = $accessData['accountId'];
      }
      $thiscDmaMemberId = $accessData['userId'];

      //Create Pending Listing
      $pCreditDataLocked = [
        //'listingId' => $creditId,
        'listedBy'        => $thisListedBy,
        'availableToList' => $creditData['creditAmount'],
        'cDmaMemberId'    => $thiscDmaMemberId,
        'status'          => 5,
        //'activeListingId' => $creditId,
        'timeStamp'       => date('Y-m-d H:i:s'),
        'updatedTime'     => date('Y-m-d H:i:s'),
      ];

      //Prepare final data request
      $newPCreditData = array_merge($pCreditDataLocked, $creditData);

      $this->db->insert('PendingListings', $newPCreditData);
      $creditId = $this->db->insert_id();

      $savedPCredit = $this->get_pending_credit_all_detail($creditId);

      //Create Active Listing
      $data3 = [
          'listingId'        => $creditId,
          'OIXIncentiveId'   => $savedPCredit['OIXIncentiveId'],
          'creditAmount'     => $savedPCredit['creditAmount'],
          'pendingListingId' => $savedPCredit['listingId'],
          'listedBy'         => $savedPCredit['listedBy'],
          'taxYearId'        => $savedPCredit['taxYearId'],
          'timeStamp'        => date('Y-m-d H:i:s'),
      ];

      $this->db->insert('ActiveListings', $data3);

    }

    if($request['apiRequestAction'] == "update_credit") {

      /////////////////////////////////////
      // Update Credit
      /////////////////////////////////////

      $response['meta']['action'] = 'update_credit';

      //Get the listing ID
      $creditId = $creditData['listingId'];
      //add updated time stamp
      $creditData['updatedTime'] = date('Y-m-d H:i:s');

      //Take out the listing ID
      unset($creditData['listingId']);

      if(sizeof($creditData) > 0) {

        $this->db->where('PendingListings.listingId', $creditId);
        $this->db->update('PendingListings', $creditData);

        $savedPCredit = $this->get_pending_credit_all_detail($creditId);

        //Update Active Listing
        $data3 = [
            'OIXIncentiveId' => $savedPCredit['OIXIncentiveId'],
            'creditAmount'   => $savedPCredit['creditAmount'],
            'taxYearId'      => $savedPCredit['taxYearId'],
        ];

        $this->db->where('ActiveListings.listingId', $creditId);
        $this->db->update('ActiveListings', $data3);

      }

    }

    $cRequest = [];
    $cRequest['creditId'] = $creditId;
    $newCreditData = $this->get_credit_api($accessData, $cRequest);
    $response['credit'] = $newCreditData['credit'];

    /////////////////////////////////////
    // Return Response
    /////////////////////////////////////

    return $response;

  }

  function buildCreditResponseArray($accessData, $data) {

    $cleaned = [];

    //Process Amount USD
    $amountDataRequest['amountLocal'] = $data['amountLocal'];
    $amountDataRequest['budgetExchangeRate'] = $data['exchangeRate'];
    $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
    $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
    $data = array_merge($data, $creditAmountProcessedData);

    //Cleaned Response Architecture (alphabetical)
    //Credit Amount Data
    $cleaned['account']['accountId'] = ($data['accountId'] > 0) ? $data['accountId'] : null;
    $cleaned['account']['accountName'] = ($data['accountName'] != "") ? $data['accountName'] : null;
    $cleaned['account']['accountOwnerUserId'] = ($data['accountOwnerUserId'] != "") ? $data['accountOwnerUserId'] : null;
    $cleaned['certificationNumber'] = ($data['certificationNumber'] != "") ? $data['certificationNumber'] : null;
    $cleaned['cOwnerReadOnly'] = ($data['cOwnerReadOnly'] != "") ? $data['cOwnerReadOnly'] : null;
    //Compliance Data
    $cleaned['compliance']['templateId'] = ($data['complianceTemplateId'] > 0) ? $data['complianceTemplateId'] : null;
    $cleaned['compliance']['complianceFirstYear'] = ($data['complianceFirstYear'] > 0) ? $data['complianceFirstYear'] : null;
    $cleaned['compliance']['requiredComplianceTerm'] = ($data['requiredComplianceTerm'] > 0) ? $data['requiredComplianceTerm'] : null;
    $cleaned['compliance']['complianceLastYear'] = ($data['requiredComplianceTerm'] > 0 && $data['complianceFirstYear'] > 0) ? $data['complianceFirstYear'] + $data['requiredComplianceTerm'] : null;
    $cleaned['creditId'] = $data['creditId'];
    $cleaned['creditIdVisual'] = ($data['jurisdictionCode'] != "") ? $data['jurisdictionCode'] . $data['creditId'] : $data['creditId'];
    //Credit Amount Data
    $cleaned['creditAmountData']['amountLocal'] = ($data['amountLocal'] > 0) ? $data['amountLocal'] : 0;
    $cleaned['creditAmountData']['amountLocalRemaining'] = ($data['amountLocalRemaining'] > 0) ? $data['amountLocalRemaining'] : 0;
    $cleaned['creditAmountData']['amountUSD'] = ($data['amountUSD'] > 0) ? $data['amountUSD'] : 0;
    $cleaned['creditAmountData']['amountUSDRemaining'] = ($data['amountUSDRemaining'] > 0) ? $data['amountUSDRemaining'] : 0;
    $cleaned['creditAmountData']['estimatedValueAsPercent'] = ($data['estimatedValueAsPercent'] > 0) ? $data['estimatedValueAsPercent'] : 0;
    $cleaned['creditAmountData']['amountUSDEstimatedNet'] = $cleaned['creditAmountData']['amountUSD'] * $cleaned['creditAmountData']['estimatedValueAsPercent'];
    $cleaned['creditAmountData']['amountUSDRemainingEstimatedNet'] = $cleaned['creditAmountData']['amountUSDRemaining'] * $cleaned['creditAmountData']['estimatedValueAsPercent'];
    $cleaned['creditOriginType'] = $data['creditOriginType'];
    //Legal Entity Data
    $cleaned['creditHolderTaxEntity']['taxEntityId'] = ($data['taxEntityId'] > 0) ? $data['taxEntityId'] : null;
    $cleaned['creditHolderTaxEntity']['accountType'] = ($data['taxEntityAccountType'] != "") ? ($data['taxEntityAccountType'] == 1 ? "business" : "individual") : null;
    $cleaned['creditHolderTaxEntity']['companyName'] = ($data['taxEntityCompanyName'] != "") ? $data['taxEntityCompanyName'] : null;
    $cleaned['creditHolderTaxEntity']['firstName'] = ($data['taxEntityFirstName'] != "") ? $data['taxEntityFirstName'] : null;
    $cleaned['creditHolderTaxEntity']['lastName'] = ($data['taxEntityLastName'] != "") ? $data['taxEntityLastName'] : null;
    //Currency Data
    $cleaned['currencyData']['localCurrency'] = ($data['localCurrency'] != "") ? $data['localCurrency'] : 'USD';
    $cleaned['currencyData']['exchangeRate'] = ($data['exchangeRate'] > 0) ? $data['exchangeRate'] : 1; //default to 1 for USD
    //General data
    $cleaned['customerAccessStatusId'] = ($data['customerAccessStatusId'] != "") ? $data['customerAccessStatusId'] : null;
    $cleaned['dateLoaded'] = $data['dateLoaded'];
    $cleaned['encumbered'] = ($data['encumbered'] > 0) ? 1 : 0;
    $cleaned['finalCreditAmountFlag'] = ($data['finalCreditAmountFlag'] > 0) ? 1 : 0;
    //Incentive Program
    $cleaned['incentiveProgram']['oixIncentiveProgramId'] = ($data['oixIncentiveProgramId'] > 0) ? $data['oixIncentiveProgramId'] : null;
    $cleaned['incentiveProgram']['incentiveProgramName'] = ($data['incentiveProgramName'] != "") ? $data['incentiveProgramName'] : null;
    $cleaned['incentiveProgram']['jurisdictionCode'] = ($data['jurisdictionCode'] != "") ? $data['jurisdictionCode'] : null;
    $cleaned['incentiveProgram']['jurisdictionName'] = ($data['jurisdictionName'] != "") ? $data['jurisdictionName'] : null;
    $cleaned['incentiveProgram']['governmentAgencyName'] = ($data['governmentAgencyName'] != "") ? $data['governmentAgencyName'] : null;
    $cleaned['incentiveProgram']['regionId'] = ($data['regionId'] > 0) ? $data['regionId'] : null;
    $cleaned['incentiveProgram']['countryName'] = ($data['countryName'] != "") ? $data['countryName'] : null;
    $cleaned['incentiveProgram']['regionCode'] = ($data['regionCode'] != "") ? $data['regionCode'] : null;
    $cleaned['incentiveProgram']['programTypeId'] = ($data['programTypeId'] > 0) ? $data['programTypeId'] : null;
    $cleaned['incentiveProgram']['programType'] = ($data['programType'] != "") ? $data['programType'] : null;
    $cleaned['incentiveProgram']['carryForward'] = ($data['carryForward'] != "") ? $data['carryForward'] : null;
    $cleaned['incentiveProgram']['carryForwardYears'] = ($data['carryForwardYears'] != "") ? $data['carryForwardYears'] : null;
    $cleaned['internalId'] = ($data['internalId'] > 0) ? $data['internalId'] : null;
    $cleaned['isArchived'] = ($data['statusArchived'] == 1) ? true : false;
    //Format Dates
    $cleaned['keyDates']['dateAuditStart'] = ($data['dateAuditStart'] > 0) ? date('Y-m-d', $data['dateAuditStart']) : null;
    $cleaned['keyDates']['dateAuditEnd'] = ($data['dateAuditEnd'] > 0) ? date('Y-m-d', $data['dateAuditEnd']) : null;
    $cleaned['keyDates']['dateCertificationFinal'] = ($data['dateCertificationFinal'] > 0) ? date('Y-m-d', $data['dateCertificationFinal']) : null;
    $cleaned['keyDates']['dateCertificationInitial'] = ($data['dateCertificationInitial'] > 0) ? date('Y-m-d', $data['dateCertificationInitial']) : null;
    $cleaned['keyDates']['dateCreditIssue'] = ($data['dateCreditIssue'] > 0) ? date('Y-m-d', $data['dateCreditIssue']) : null;
    if($accessData['accountCategory'] == "entertainment") {
      $cleaned['keyDates']['dateLastDayPrincipalPhotography'] = ($data['dateLastDayPrincipalPhotography'] > 0) ? date('Y-m-d', $data['dateLastDayPrincipalPhotography']) : null;
    }
    $cleaned['keyDates']['dateProjectStart'] = ($data['dateProjectStart'] > 0) ? date('Y-m-d', $data['dateProjectStart']) : null;
    $cleaned['keyDates']['dateProjectEnd'] = ($data['dateProjectEnd'] > 0) ? date('Y-m-d', $data['dateProjectEnd']) : null;
    //Status Data
    $cleaned['keyStatus']['statusIdAudit'] = ($data['statusIdAudit'] > 0) ? $data['statusIdAudit'] : null;
    $cleaned['keyStatus']['statusAudit'] = ($data['statusIdAudit'] > 0) ? $data['statusAudit'] : null;
    $cleaned['keyStatus']['statusIdCertification'] = ($data['statusIdCertification'] > 0) ? $data['statusIdCertification'] : null;
    $cleaned['keyStatus']['statusCertification'] = ($data['statusIdCertification'] > 0) ? $data['statusCertification'] : null;
    $cleaned['keyStatus']['statusIdFinance'] = ($data['statusIdFinance'] > 0) ? $data['statusIdFinance'] : null;
    $cleaned['keyStatus']['statusFinance'] = ($data['statusIdFinance'] > 0) ? $data['statusFinance'] : null;
    $cleaned['keyStatus']['statusIdMonetization'] = ($data['statusIdMonetization'] > 0) ? $data['statusIdMonetization'] : null;
    $cleaned['keyStatus']['statusMonetization'] = ($data['statusIdMonetization'] > 0) ? $data['statusMonetization'] : null;
    $cleaned['keyStatus']['statusIdProject'] = ($data['statusIdProject'] > 0) ? $data['statusIdProject'] : null;
    $cleaned['keyStatus']['statusProject'] = ($data['statusIdProject'] > 0) ? $data['statusProject'] : null;
    //Loaded By Data
    $cleaned['loadedBy']['userId'] = ($data['loadedByUserId'] > 0) ? $data['loadedByUserId'] : null;
    $cleaned['loadedBy']['firstName'] = ($data['loadedByFirstName'] != "") ? $data['loadedByFirstName'] : null;
    $cleaned['loadedBy']['lastName'] = ($data['loadedByLastName'] != "") ? $data['loadedByLastName'] : null;
    //Notes Data
    $cleaned['notes']['generalNotes'] = ($data['generalNotes'] != "") ? $data['generalNotes'] : null;
    $cleaned['notes']['statusNotes'] = ($data['statusNotes'] != "") ? $data['statusNotes'] : null;
    //Project Data
    $cleaned['projectData']['projectName'] = ($data['projectName'] != "") ? $data['projectName'] : null;
    $cleaned['projectData']['projectNameExt'] = ($data['projectNameExt'] != "") ? $data['projectNameExt'] : null;
    $cleaned['projectData']['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : ($data['projectName'] != "" ? $data['projectName'] : null);
    //Purchased Data
    $cleaned['purchasedData']['creditWasPurched'] = ($data['creditOriginType'] == "loaded_purchase") ? 1 : 0;
    $cleaned['purchasedData']['purchasedAtPrice'] = ($data['purchasedAtPrice'] != "") ? $data['purchasedAtPrice'] : null;
    $cleaned['purchasedData']['purchasedAtDate'] = ($data['purchasedAtDate'] != "") ? date('m/d/Y', $data['purchasedAtDate']) : null;
    $cleaned['purchasedData']['creditIssuedTo'] = ($data['creditIssuedTo'] != "") ? $data['creditIssuedTo'] : null;

    $cleaned['projectBudget'] = ($data['projectBudget'] != "") ? $data['projectBudget'] : null;
    $cleaned['qualifiedExpenditures'] = ($data['qualifiedExpenditures'] != "") ? $data['qualifiedExpenditures'] : null;
    $cleaned['incentiveRate'] = ($data['incentiveRate'] != "") ? $data['incentiveRate'] : null;

    $cleaned['trackingVariance'] = ($data['trackingVariance'] > 0) ? 1 : 0;
    $cleaned['typeOfWork'] = ($data['typeOfWork'] != "") ? $data['typeOfWork'] : null;
    $cleaned['vintageTaxYear'] = ($data['vintageTaxYear'] > 0) ? $data['vintageTaxYear'] : null;
    //Workflow data
    $cleaned['workflow']['templateId'] = ($data['workflowTemplateId'] > 0) ? $data['workflowTemplateId'] : null;

    //Custom Data Points
    $cleaned['customDataPoints'] = $this->get_custom_data_points($accessData, $data['listedBy'], $data['parentDmaId'], $data['creditId'], 'credit');

    return $cleaned;

  }

  function get_custom_data_points($accessData, $dmaId, $parentDmaId, $objectId, $type) {
    //Custom Data Points
    $result['customDataPoints'] = [];
    $dpRequest['dpDmaIdCustom'] = $dmaId;
    $dpRequest['parentDmaId'] = $parentDmaId;
    $dpRequest['listingId'] = $objectId;
    $dpRequest['dpObjectType'] = $type;
    $customDataPoints = $this->get_data_points($dpRequest, $accessData);
    $customDataPoints = $customDataPoints['dataPoints'];
    foreach($customDataPoints as $cdp) {
      //Create a cleaned response
      $thisCdp = [];
      $thisCdp['id'] = $cdp['dpId'];
      $thisCdp['name'] = $cdp['dpNameFull'];
      $thisCdp['type'] = $cdp['dpType'];
      $thisCdp['typeName'] = $cdp['dpTypeName'];
      $thisCdp['dpValue'] = $cdp['dpValue'];
      if($cdp['dpType'] == "date") {
        $thisCdp['value'] = ($cdp['cvValueFormatted'] != "") ? date('Y-m-d', $cdp['cvValue']) : null;
        $thisCdp['valueFormatted'] = ($cdp['cvValueFormatted'] != "") ? date('Y-m-d', $cdp['cvValue']) : null;
      } else {
        $thisCdp['value'] = ($cdp['cvValue'] != "") ? $cdp['cvValue'] : null;
        $thisCdp['valueFormatted'] = ($cdp['cvValueFormatted'] != "") ? $cdp['cvValueFormatted'] : null;
      }
      $thisCdp['key'] = $cdp['dpKey'];
      $thisCdp['keyValue'] = $cdp['dpValue'];
      array_push($result['customDataPoints'], $thisCdp);
    }

    return $result['customDataPoints'];
  }

  function get_credit_api($accessData, $request) {

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;
    $q3 = \OIX\Services\JurisdictionService::$jurisdiciton_google_place_id_query;
    $q4 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_latitude_query;
    $q5 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_longitude_query;

    /////////////////////////////////////
    // Setup Key Arrays
    /////////////////////////////////////

    $response = [];
    $response['code'] = 200;
    $response['success'] = 1;
    $response['credit'] = [];
    $response['errors'] = [];
    $response['meta'] = [];

    /////////////////////////////////////
    // Validation
    /////////////////////////////////////

    $request['apiRequestAction'] = "get_credit";
    $validationResult = $this->validate_and_build_credit_data_request($accessData, $request);
    $creditData = $validationResult['creditData'];
    $errors = $validationResult['errors'];

    //If errors, return them and stop processing the script
    if(sizeof($errors) > 0) {
      $response['code'] = 422; //422 = "Unprocessable Entity"
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['errors'] = $errors;
      $response['meta'] = [];

      return $response;
    }

    //Credit General Data
    $select = "PendingListings.listingId as creditId, PendingListings.listedBy, PendingListings.timeStamp as dateLoaded, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, TaxYear.taxYear as vintageTaxYear, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining, PendingListings.estCreditPrice as estimatedValueAsPercent, PendingListings.localCurrency, PendingListings.budgetExchangeRate as exchangeRate, PendingListings.projectBudgetEst as projectBudget, PendingListings.qualifiedExpenditures, PendingListings.incentiveRate, PendingListings.certificationNum as certificationNumber, PendingListings.cDmaMemberId as loadedByUserId, PendingListings.cWorkflowId as workflowTemplateId, PendingListings.cComplianceId as complianceTemplateId, PendingListings.requiredComplianceTerm, PendingListings.complianceFirstYear, PendingListings.cTaxpayerId as taxEntityId, PendingListings.internalId, PendingListings.statusNotes, PendingListings.cOrigin as creditOriginType, PendingListings.finalCreditAmountFlag, PendingListings.trackingVariance, PendingListings.cNotes as generalNotes, PendingListings.encumbered, PendingListings.cCustomerAccess as customerAccessStatusId, PendingListings.typeOfWork, PendingListings.cOwnerReadOnly";
    //Program Data
    $select .= ", PendingListings.OIXIncentiveId as oixIncentiveProgramId, IncentivePrograms.ProgramName as incentiveProgramName, PendingListings.credit_type_id as programTypeId, IncentivePrograms.AdministeringAgency as governmentAgencyName, ProgramType.ProgramTypeName as programType, (TaxYear.taxYear+IncentivePrograms.CarryForwardYears) AS carryForward, IncentivePrograms.CarryForwardYears as carryForwardYears";
    $select .= ", loc_c.id as countryId, loc_c.id as regionId, loc_c.name as countryName, loc_c.code as countryCode, loc_c.code as regionCode, loc_p.id as provinceId, loc_p.name as provinceName, $q2 as jurisdictionCode, $q1 as jurisdictionName, $q4 as jurisdiction_lat, $q5 as jurisdiction_lng, loc_co.id as countyId, loc_co.name as countyName, loc_t.id as townId, loc_t.name as townName";
    //Credit Status Data
    $select .= ", PendingListings.projectStatus as statusIdProject, project_status.projectStatus as statusProject, PendingListings.auditStatusId as statusIdAudit, audit_status.auditStatus as statusAudit, PendingListings.statusMonetization as statusIdMonetization, PendingListings.certificationStatus as statusIdCertification, PendingListings.cArchived as statusArchived, cert_status_type.cert_status_name as statusCertification, monetization_status.mnsName as statusMonetization";
    //Legal Entity Data
    $select .= ", Taxpayers.tpCompanyName as taxEntityCompanyName, Taxpayers.tpAccountType as taxEntityAccountType, Taxpayers.tpFirstName as taxEntityFirstName, Taxpayers.tpLastName as taxEntityLastName";
    //Account Data
    $select .= ", dmaAccounts.title as accountName, dmaAccounts.dmaId as accountId, dmaAccounts.mainAdmin as accountOwnerUserId, dmaAccounts.parentDmaId, Accounts.firstName as loadedByFirstName, Accounts.lastName as loadedByLastName";
    //Dates
    $select .= ", PendingListings.IssueDate as dateCreditIssue, PendingListings.projectStartDate as dateProjectStart, PendingListings.projectEndDate as dateProjectEnd, PendingListings.lastDayPrincipalPhotography as dateLastDayPrincipalPhotography, PendingListings.auditStartDate as dateAuditStart, PendingListings.auditEndDate as dateAuditEnd, PendingListings.est_final_cert_dt as dateCertificationFinal, PendingListings.est_initial_cert_dt as dateCertificationInitial";
    //Purchased Credit Specifics
    $select .= ", PendingListings.purchasedAtPrice, PendingListings.purchasedAtDate, PendingListings.creditIssuedTo";

    $this->db->select($select, false);

    $this->db->where('PendingListings.listingId', $creditData['listingId']);
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->from('PendingListings');
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("Accounts", "Accounts.userId = PendingListings.cDmaMemberId", 'left');
    $this->db->join("dmaAccounts", "ActiveListings.listedBy = dmaAccounts.mainAdmin", 'left');
    $this->db->join("TaxYear", "PendingListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
    $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');

    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      $creditArray = $this->buildCreditResponseArray($accessData, $data);
      $response['credit'] = array_merge($response['credit'], $creditArray);
    }

    return $response;

  }

  function get_pending_credit_all_detail($id) {
    $this->db->select('PendingListings.*');
    $this->db->where('PendingListings.listingId', $id);
    $this->db->from('PendingListings');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_data_points($request, $accessData) {

    $dpDmaId = isset($request['dpDmaId']) ? $request['dpDmaId'] : null;
    $dpDmaIdCustom = isset($request['dpDmaIdCustom']) ? $request['dpDmaIdCustom'] : null;
    $dpObjectType = isset($request['dpObjectType']) ? $request['dpObjectType'] : null;
    $listingId = isset($request['listingId']) ? $request['listingId'] : null;
    $dpKey = isset($request['dpKey']) ? $request['dpKey'] : null;
    $dpValue = isset($request['dpValue']) ? $request['dpValue'] : null;
    $dpType = isset($request['dpType']) ? $request['dpType'] : null;
    $dpSection = isset($request['dpSection']) ? $request['dpSection'] : null;
    $getReportMyCredits = isset($request['getReportMyCredits']) ? $request['getReportMyCredits'] : null;
    $getReportSharedCredits = isset($request['getReportSharedCredits']) ? $request['getReportSharedCredits'] : null;
    $getReportPurchases = isset($request['getReportPurchases']) ? $request['getReportPurchases'] : null;
    $getReportTrades = isset($request['getReportTrades']) ? $request['getReportTrades'] : null;
    $getReportHarvests = isset($request['getReportHarvests']) ? $request['getReportHarvests'] : null;
    $getMyCreditsView = isset($request['getMyCreditsView']) ? $request['getMyCreditsView'] : null;
    $getSharedCreditsView = isset($request['getSharedCreditsView']) ? $request['getSharedCreditsView'] : null;
    $getReportCompliance = isset($request['getReportCompliance']) ? $request['getReportCompliance'] : null;
    $includeArchived = isset($request['includeArchived']) ? $request['includeArchived'] : null;
    $parentDmaAccess = isset($request['parentDmaId']) ? $request['parentDmaId'] : null;

    $select = 'dataPoints.*, parentDp.dpNameFull as parentDpNameFull, parentDp.dpNameShort as parentDpNameShort, parentDp.dpType as parentDpType, parentDp.option_group_id as parent_option_group_id, parentDp.dpIsRequired as parentDpIsRequired, parentDp.dpDescription as parentDpDescription, parentDp.dpId as parentDpId, parentDp.dpArchivedMarker as parentDpArchivedMarker';

    $this->db->select($select);
    $this->db->join('dataPoints as parentDp', 'parentDp.dpId = dataPoints.parent_id', 'left');

    if($dpDmaIdCustom != "") {
      if(isset($parentDmaAccess) && is_numeric($parentDmaAccess) && $parentDmaAccess > 0) {
        $this->db->where_in('dataPoints.dpDmaId', [$dpDmaIdCustom, $parentDmaAccess]);
      } else {
        $this->db->where('dataPoints.dpDmaId', $dpDmaIdCustom);
      }
    } else {
      if($dpDmaId != "" && is_numeric($dpDmaId)) {
        if(isset($parentDmaAccess) && is_numeric($parentDmaAccess) && $parentDmaAccess > 0) {
          $this->db->where('(dataPoints.dpDmaId=' . $dpDmaId . ' OR dataPoints.dpDmaId=' . $parentDmaAccess . ' OR  dataPoints.dpDmaId IS NULL)');
        } else {
          $this->db->where('(dataPoints.dpDmaId=' . $dpDmaId . ' OR dataPoints.dpDmaId IS NULL)');
        }
      } else {
        $this->db->where('dataPoints.dpDmaId', null);
      }
    }

    if($dpValue != "") {
      $this->db->where('dataPoints.dpValue', $dpValue);
    }
    if($dpType != "") {
      $this->db->where('dataPoints.dpType', $dpType);
    }
    if($dpSection != "") {
      $this->db->where('dataPoints.dpSection', $dpSection);
    }

    if($dpObjectType != "") {
      $this->db->where('dataPoints.dpObjectType', $dpObjectType);
    }

    if($getReportMyCredits == 1) {
      $this->db->where('dataPoints.dpReportMyCredits >', 0);
      $this->db->order_by("dataPoints.dpReportMyCredits ASC");
    }
    if($getReportTrades == 1) {
      $this->db->where('dataPoints.dpReportTrades >', 0);
      $this->db->order_by("dataPoints.dpReportTrades ASC");
    }
    if($getMyCreditsView == 1) {
      $this->db->where('dataPoints.dpMyCreditsView >', 0);
      $this->db->order_by("dataPoints.dpMyCreditsView ASC");
    }
    if($getReportCompliance == 1) {
      $this->db->where('dataPoints.dpReportCompliance >', 0);
      $this->db->order_by("dataPoints.dpReportCompliance ASC");
    }

    if($includeArchived == 1) {
      $this->db->where('dataPoints.dpArchivedMarker', 1);
    } else {
      if($includeArchived == 2) {
        //include archived and active
      } else {
        $this->db->where('dataPoints.dpArchivedMarker', null);
      }
    }

    $this->db->from('dataPoints');

    $this->db->order_by("dataPoints.dpNameFull ASC");

    //$this->db->join("xxx","xxx.xxx = xxx.xxx", 'left');

    $query = $this->db->get();

    $return = [];
    $return['dataPoints'] = [];
    $return['dataPointsKeysOnly'] = [];

    foreach($query->result_array() as $data) {

      $include = true;

      $data['isParentData'] = ($data['dpDmaId'] != "" && $data['dpDmaId'] != $accessData['accountId']) ? true : false;

      if($data['dpKey'] == "fieldLastDayPrincipalPhotography" || $data['dpKey'] == "fieldNumberSubProjects") {
        if($accessData['accountCategory'] == "entertainment") {
          //do nothing
        } else {
          $include = false;
        }
      }
      if($data['dpKey'] == "fieldAdvisorFeeTotal" || $data['dpKey'] == "fieldAdvisorFeePaid" || $data['dpKey'] == "fieldAdvisorFeeOutstanding" || $data['dpKey'] == "fieldAdvisorProjectStartDate" || $data['dpKey'] == "fieldAdvisorProjectEndDate" || $data['dpKey'] == "fieldAdvisorStatus") {
        if(in_array(2, $accessData['planDataPacks'])) {
          //IF ADVISOR PACK - do nothing
        } else {
          $include = false;
        }
      }

      if(isset($data['parentDpNameFull'])) {
        $data['dpNameFull'] = $data['parentDpNameFull'];
      }
      if(isset($data['parentDpNameShort'])) {
        $data['dpNameShort'] = $data['parentDpNameShort'];
      }
      if(isset($data['parentDpType'])) {
        $data['dpType'] = $data['parentDpType'];
      }
      if(isset($data['parent_option_group_id'])) {
        $data['option_group_id'] = $data['parent_option_group_id'];
      }
      if(isset($data['parentDpIsRequired'])) {
        $data['dpIsRequired'] = $data['parentDpIsRequired'];
      }
      if(isset($data['parentDpDescription'])) {
        $data['dpDescription'] = $data['parentDpDescription'];
      }
      if(isset($data['parentDpArchivedMarker']) && !isset($request['parentCdpId'])) {
        $data['dpArchivedMarker'] = $data['parentDpArchivedMarker'];
      }

      $data['cvValue'] = null;
      $data['cvValueFormatted'] = null;
      if($listingId > 0) {
        //Get value specific to this listing
        $cdpvRequest['dpId'] = $data['dpId'];
        $cdpvRequest['listingId'] = $listingId;
        $cdpvRequest['dpObjectType'] = 'credit';
        $customDataPointValues = $this->get_custom_data_point_values($cdpvRequest);
        if(sizeof($customDataPointValues) > 0) {
          foreach($customDataPointValues as $k => $v) {
            $data['cvValue'] = $v;
          }
        } else {
          $data['cvValue'] = null;
        }

      } else {
        //Get generic values
        $data['dataPointValues'] = $this->get_all_custom_values_for_data_point($data['dpId']);
      }

      $data['dpTypeName'] = "";
      if($data['dpType'] == "date") {
        $data['dpTypeName'] = "Date";
        $data['cvValueFormatted'] = ($data['cvValue'] != "") ? date('m/d/Y', $data['cvValue']) : "";
      }
      if($data['dpType'] == "text") {
        $data['dpTypeName'] = "Text/Number";
        $data['cvValueFormatted'] = $data['cvValue'];
      }
      if($data['dpType'] == "note") {
        $data['dpTypeName'] = "Notes";
        $data['cvValueFormatted'] = $data['cvValue'];
      }
      if($data['dpType'] == "currencyNoDecimal") {
        $data['dpTypeName'] = "Currency";
        $data['cvValueFormatted'] = ($data['cvValue'] != "") ? "$" . number_format($data['cvValue']) : "";
      }

      if($include) {
        array_push($return['dataPoints'], $data);
        array_push($return['dataPointsKeysOnly'], $data['dpKey']);
      }

    }

    return $return;

  }

  function get_custom_data_point_values($request) {

    $dmaId = $request['dmaId'];
    $objectId = $request['listingId'];
    $dpId = $request['dpId'];
    $dpObjectType = $request['dpObjectType'];

    $select = 'dataPoints.dpValue, dataPointCustomValues.cvValue';
    $this->db->select($select);
    if($dpId > 0 && $objectId > 0) {
      $this->db->where('dataPoints.dpId', $dpId);
      $this->db->where('dataPointCustomValues.cvObjectId', $objectId);
    } else {
      if($dmaId > 0 && $objectId > 0) {
        $this->db->where('dataPoints.dpDmaId', $dmaId);
        $this->db->where('dataPointCustomValues.cvObjectId', $objectId);
      } else {
        if($dpId > 0) {
          $this->db->where('dataPoints.dpId', $dpId);
        } else {
          throw new \Exception('General fail');
        }
      }
    }
    if($dpObjectType != "") {
      $this->db->where('dataPoints.dpObjectType', $dpObjectType);
    }
    $this->db->where('dataPoints.dpArchivedMarker', null);
    $this->db->from('dataPoints');
    $this->db->join("dataPointCustomValues", "dataPoints.dpId = dataPointCustomValues.cvDpId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data['cvValue'] != "") {
        $return[$data['dpValue']] = $data['cvValue'];
      }
    }

    return $return;

  }

  function get_all_custom_values_for_data_point($dpId = "") {

    $select = 'dataPointCustomValues.*';
    $this->db->select($select);
    $this->db->where('dataPoints.dpId', $dpId);
    $this->db->where('dataPoints.dpArchivedMarker', null);
    $this->db->from('dataPoints');
    $this->db->join("dataPointCustomValues", "dataPoints.dpId = dataPointCustomValues.cvDpId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($data['cvValue'] != "") {
        array_push($return, $data);
      }
    }

    return $return;

  }








  /////////////////////////////////////
  // Utilization Functions
  /////////////////////////////////////

  function validate_and_build_utilization_data_request($accessData, $request) {

    /////////////////////////////////////
    // Setup Key Arrays
    /////////////////////////////////////

    $validation['utilizationData'] = []; //The data that passes validation
    $validation['transactionsData'] = []; //The data that passes validation for sub-transactions
    $validation['errors'] = []; //The data that fails validation

    //Temp setup until we add multi-buyer
    $validation['transactionsData'][0] = [];

    /////////////////////////////////////
    // Data Validation and Preparation
    /////////////////////////////////////

    //if exists and is greater than 0
    if(isset($request['creditId'])) {
      $vCheck = $this->validate_numeric($request['creditId']); //Validate
      if($vCheck['isValid']) {
        $validation['utilizationData']['listingId'] = $request['creditId'];
      } else {
        $thisError = ["code" => 5010, "field" => "creditId", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }
    //if exists and is greater than 0
    if(isset($request['utilizationId'])) {
      $vCheck = $this->validate_numeric($request['utilizationId']); //Validate
      if($vCheck['isValid']) {
        $validation['utilizationData']['tradeId'] = $request['utilizationId'];
      } else {
        $thisError = ["code" => 5050, "field" => "utilizationId", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    }

    //
    if(isset($request['utilizationInternalId'])) {
      $validation['utilizationData']['tInternalId'] = $request['utilizationInternalId'];
    }
    if(isset($request['exchangeRate'])) {
      $validation['utilizationData']['tExchangeRate'] = $request['exchangeRate'];
    }
    if(isset($request['utilizationTypeId'])) {
      $validation['utilizationData']['utilizationTypeId'] = $request['utilizationTypeId'];
    } else {
      if($request['apiRequestAction'] == "insert_utilization") {
        $validation['utilizationData']['utilizationTypeId'] = 1; //Set "Liability Offset" as default
      }
    }

    //If is estimated or actual
    if(isset($request['utilizationStatus'])) {
      $utilizationStatus = strtolower($request['utilizationStatus']);
      $validation['utilizationData']['tradeIsEstimated'] = ($utilizationStatus == 'estimate' || $utilizationStatus == 'estimated') ? 1 : 0;
    } else {
      $validation['utilizationData']['tradeIsEstimated'] = 0;
    }

    //If is estimate, and trade price estimate is greater than zero, then this is a percentage based estimate
    $utilization_metric = 'act_fixed_amount';
    if($validation['utilizationData']['tradeIsEstimated'] == 1) {
      $utilization_metric = 'est_fixed_amount';
    }
    if($validation['utilizationData']['tradeIsEstimated'] == 1 && isset($request['utilizationPercentageEstimate']) && $request['utilizationPercentageEstimate'] > 0) {
      $utilization_metric = 'est_percentage_based';
    }
    if($utilization_metric == 'est_percentage_based') {
      if(isset($request['utilizationPercentageEstimate'])) {
        $validation['utilizationData']['tradePercentageEstimate'] = $request['utilizationPercentageEstimate'];
      }
      if(isset($request['utilizationPercentageEstimateCompareTo'])) {
        $validation['utilizationData']['tradePercentageEstimateCompareTo'] = $request['utilizationPercentageEstimateCompareTo'];
      }
    }

    //Utilization Amount
    if(isset($request['utilizationAmountLocal']) && $request['utilizationAmountLocal'] >= 0) {

      if($request['apiRequestAction'] == "insert_utilization") {

        $uaQuery = $this->db->get_where('PendingListings', ['listingId' => (string)$request['creditId']]);
        $uaResult = $uaQuery->row_array();
        //If the amount remaining is greater than or equal to the amount being utilized, then it's OK
        if($uaResult['availableToList'] + 1 >= $request['utilizationAmountLocal']) { //we add one in case there is a small decimal fraction overage (which we account for later in credit reduction)
          $validation['utilizationData']['tradeSize'] = $request['utilizationAmountLocal'];
        } else {
          $thisError = ["code" => 5090, "field" => "utilizationAmountLocal", "message" => "The credit amount of this utilization ($" . number_format($request['utilizationAmountLocal']) . ") is greater than the amount remaining on this credit ($" . number_format($uaResult['availableToList']) . "). Please reduce this utilization amount or increase the credit amount beforehand."];
          array_push($validation['errors'], $thisError);
        }

      }

      if($request['apiRequestAction'] == "update_utilization") {

        $validation['utilizationData']['tradeSize'] = $request['utilizationAmountLocal'];

        if($utilization_metric == 'est_fixed_amount') {
          $validation['utilizationData']['tradePercentageEstimate'] = null;
          $validation['utilizationData']['tradePercentageEstimateCompareTo'] = null;
        }

      }

    } else {
      if($request['apiRequestAction'] == "insert_utilization") {
        $validation['utilizationData']['tradeSize'] = 0; //Set to 0 if empty

        if($utilization_metric == 'est_percentage_based') {
          $validation['utilizationData']['tradeSizeEstimate'] = 0;
        }

      }
    }
    if(isset($request['utilizationAmountLocal']) && $request['utilizationAmountLocal'] >= 0) {
      $validation['transactionsData'][0]['tCreditAmount'] = $validation['utilizationData']['tradeSize'];
    }

    if(isset($request['utilizationValuePerCredit'])) {
      $validation['utilizationData']['tradePrice'] = $request['utilizationValuePerCredit'];
    } else {
      if($request['apiRequestAction'] == "insert_utilization") {
        $validation['utilizationData']['tradePrice'] = 1; //set default to $1 per credit if empty
      }
    }
    if(isset($request['utilizationInterestAmount'])) { //permit negative numbers
      $validation['utilizationData']['interestAmountLocal'] = $request['utilizationInterestAmount'];
    }

    if(isset($request['notes'])) {
      $validation['utilizationData']['tradeNotes'] = $request['notes'];
    }

    //Utilization transactions
    if(isset($request['utilizingEntityType']) && $request['utilizingEntityType'] != '') {
      //VALiDATION CHECK = TO DO
      //$request['utilizingEntityType'] should ONLY ever equal - self, customname, taxentity
      $validation['utilizationData']['utilizingEntityType'] = $request['utilizingEntityType'];
    } else {
      if($request['apiRequestAction'] == "insert_utilization") {
        $validation['utilizationData']['utilizingEntityType'] = 'taxentity'; //Set "taxentity" as default if empty as we're going to assign
      }
    }

    //Status
    if(isset($request['statusId'])) {
      $validation['utilizationData']['status'] = $request['statusId'];
    } else {
      if(isset($request['statusCode'])) {
        //VALiDATION CHECK = TO DO
        if($request['statusCode'] == "not_started") {
          $validation['utilizationData']['status'] = 0;
        } else {
          if($request['statusCode'] == "in_progress") {
            $validation['utilizationData']['status'] = 1;
          } else {
            if($request['statusCode'] == "completed") {
              $validation['utilizationData']['status'] = 3;
            }
          }
        }
      } else {
        if($request['apiRequestAction'] == "insert_utilization") {
          $validation['utilizationData']['status'] = 0; //Set to un-started by default
        }
      }
    }

    if(isset($request['monetizationStatus'])) {
      $validation['utilizationData']['sellerRecPayment'] = ($request['monetizationStatus'] == 1) ? 1 : null;
    }

    if(isset($request['isDeleted'])) {
      $validation['utilizationData']['deleteMarker'] = ($request['isDeleted'] == 1) ? 1 : null;
    }

    //Dates
    if(isset($request['utilizationDate'])) {
      $vCheck = $this->validate_date($request['utilizationDate']); //Validate
      if($vCheck['isValid']) {
        $validation['utilizationData']['timeStamp'] = date('Y-m-d H:i:s', $vCheck['unixtimestamp_format']);
      } else {
        $thisError = ["code" => 5100, "field" => "utilizationDate", "message" => $vCheck['errorMessage']];
        array_push($validation['errors'], $thisError);
      }
    } else {
      if($request['apiRequestAction'] == "insert_utilization") {
        $validation['utilizationData']['timeStamp'] = '0000-00-00 00:00:00';
      }
    }

    //Based on cleaned data, make a few configs

    if(isset($request['utilizingAccountId']) && $validation['utilizationData']['utilizingEntityType'] == 'myaccounts') {
      //TO DO - validate that these accounts are either (1) DMA accounts I have access to or (2) customer accounts of my account
      $validation['utilizationData']['accountId'] = $request['utilizingAccountId'];
      $validation['transactionsData'][0]['buyerAccountId'] = $request['utilizingAccountId'];
    } else {
      if($request['apiRequestAction'] == "insert_utilization") {
        $validation['utilizationData']['accountId'] = 0;
        $validation['transactionsData'][0]['buyerAccountId'] = 0;
      }
    }

    if(isset($request['utilizingTaxpayerId']) && $request['utilizingTaxpayerId'] > 0) {
      $validation['transactionsData'][0]['taxpayerId'] = $request['utilizingTaxpayerId'];
      $validation['transactionsData'][0]['utilizingEntityCustomName'] = null;
    } else if(isset($request['utilizingEntityCustomName']) && $request['utilizingEntityCustomName'] != '') {
      $validation['transactionsData'][0]['taxpayerId'] = null;
      $validation['transactionsData'][0]['utilizingEntityCustomName'] = $request['utilizingEntityCustomName'];
    } else {

      if($request['apiRequestAction'] == "insert_utilization") { //if nothing is set on an Insert, then try to insert the credit holder taxpayer

        //get the credit holder taxpayer
        $thisCreditHolderTaxpayerId = 0;
        $this->db->select('cTaxpayerId');
        $this->db->where('listingId', $request['creditId']);
        $this->db->from('PendingListings');
        $query = $this->db->get();
        foreach($query->result_array() as $data) {
          if($data['cTaxpayerId'] > 0) {
            $thisCreditHolderTaxpayerId = $data['cTaxpayerId'];
          }
        }
        if($thisCreditHolderTaxpayerId > 0) {
          $validation['transactionsData'][0]['taxpayerId'] = $thisCreditHolderTaxpayerId;
          $validation['transactionsData'][0]['utilizingEntityCustomName'] = null;
        }

      }

    }

    if(isset($request['utilizationStatus']) && $validation['utilizationData']['tradeIsEstimated'] == 1) { //if estimated
      if($utilization_metric == 'est_fixed_amount' && isset($validation['utilizationData']['tradePrice'])) {
        $validation['utilizationData']['tradeSizeEstimate'] = $validation['utilizationData']['tradeSize'];
      }
      if(isset($validation['utilizationData']['tradePrice'])) {
        $validation['utilizationData']['tradePriceEstimate'] = $validation['utilizationData']['tradePrice'];
      }
      if(isset($validation['utilizationData']['timeStamp'])) {
        $validation['utilizationData']['tradeDateEstimate'] = (isset($validation['utilizationData']['timeStamp']) && $validation['utilizationData']['timeStamp'] != "") ? strtotime($validation['utilizationData']['timeStamp']) : null;
      }
    }

    /////////////////////////////////////
    // Run check on minimum required fields
    /////////////////////////////////////

    if($request['apiRequestAction'] == "insert_utilization") {

      //Utilization Related Data
      if(is_numeric($request["creditId"]) && $request["creditId"] > 0) {
        //OK
      } else {
        $thisError = ["code" => 5200, "field" => "creditId", "message" => "Either you submitted a non-valid Credit ID or you do not have permission to access the data requested."];
        array_push($validation['errors'], $thisError);
      }
      if(!isset($request['utilizationStatus'])) {
        $thisError = ["code" => 5210, "field" => "'utilizationStatus'", "message" => "You must indicate wether this is an estimated or actual utilzation. An actual utilization will reduce the amount of credit remaining while an estimated will not."];
        array_push($validation['errors'], $thisError);
      }
      /*
      if(!isset($request['utilizationDate'])) {
        $thisError = array("code"=>5220,"field"=>"utilizationDate","message"=>"You must indicate a date of utilization (wether that be estimated or actual).");
        array_push($validation['errors'], $thisError);
      }
      */

    }

    if($request['apiRequestAction'] == "get_utilization") {

      //Utilization Related Data
      if(is_numeric($request["utilizationId"]) && $request["utilizationId"] > 0) {
        //OK
      } else {
        $thisError = ["code" => 5200, "field" => "utilizationId", "message" => "Either you submitted a non-valid Utilizaiton ID or you do not have permission to access the data requested."];
        array_push($validation['errors'], $thisError);
      }

    }

    /////////////////////////////////////
    // Return the Result
    /////////////////////////////////////

    return $validation;

  }

  function process_utilization_api($accessData, $request) {

    /////////////////////////////////////
    // Setup Key Arrays
    /////////////////////////////////////

    $response = [];
    $response['code'] = 200;
    $response['success'] = 1;
    $response['utilization'] = [];
    $response['errors'] = [];
    $response['meta'] = [];

    /////////////////////////////////////
    // Data Validation and Preparation
    /////////////////////////////////////

    $validationResult = $this->validate_and_build_utilization_data_request($accessData, $request);
    $utilizationData = $validationResult['utilizationData'];
    $transactionsData = $validationResult['transactionsData'];
    $errors = $validationResult['errors'];

    //If errors, return them and stop processing the script
    if(sizeof($errors) > 0) {
      $response['code'] = 422; //422 = "Unprocessable Entity"
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['errors'] = $errors;
      $response['meta'] = [];

      return $response;
    }

    /*
    $emailData = array();
    $emailData['updateType'] = 'fox_data_integration_error_report';
    $emailData['apiErrorCount'] = 1;
    $testData = array($newTransactionData);
    $emailData['apiErrors'] = $testData;
    $emailData['emailSubject'] = 'Transaction Ready to go to DB:';
    $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_dev_emails_array"), $emailData);
    */

    if($request['apiRequestAction'] == "insert_utilization") {

      /////////////////////////////////////
      // Create Utilization
      /////////////////////////////////////

      $utilizationDataLocked = [
          'tradeType'         => 'utilization',
          'tDmaMemberId'      => $accessData['userId'],
          'brokeDate'         => time(),
          'updatedTime'       => date('Y-m-d H:i:s'),
          'tUpdatedTimestamp' => time(),
      ];

      //Prepare final data request
      $newUtilizationData = array_merge($utilizationDataLocked, $utilizationData);

      $this->db->insert('Trades', $newUtilizationData);
      $finalUtilizationId = $this->db->insert_id();

      foreach($transactionsData as $trans) {

        if(count($trans) > 0) {

          $transactionDataLocked = [
              'tradeId'    => $finalUtilizationId,
              'tDmaId'     => $accessData['accountId'],
              'tTimestamp' => time(),
          ];

          //Prepare final data request
          $newTransactionData = array_merge($trans, $transactionDataLocked);

          $this->db->insert('Transactions', $newTransactionData);
          $newTransactionId = $this->db->insert_id();

        }

      }

    }

    if($request['apiRequestAction'] == "update_utilization") {

      /////////////////////////////////////
      // Update Utilization
      /////////////////////////////////////

      $tradeId = $utilizationData['tradeId'];
      $finalUtilizationId = $utilizationData['tradeId'];

      $utilizationDataLocked = [
          'updatedTime'       => date('Y-m-d H:i:s'),
          'tUpdatedTimestamp' => time(),
      ];

      //Prepare final data request
      $newUtilizationData = array_merge($utilizationDataLocked, $utilizationData);

      $this->db->where('Trades.tradeId', $tradeId);
      $this->db->update('Trades', $newUtilizationData);

      foreach($transactionsData as $trans) {

        if(count($trans) > 0) {

          //Prepare final data request
          $newTransactionData = $trans;

          //TO DO!!!! - this is only setup for single transaction type trades. It's just updating the first transaction.
          $this->db->where('Transactions.tradeId', $tradeId);
          $this->db->update('Transactions', $newTransactionData);

        }

      }

    }

    $cRequest = [];
    $cRequest['utilizationId'] = $finalUtilizationId;
    $newUtilizationData = $this->get_utilization_api($accessData, $cRequest);
    $response['utilization'] = $newUtilizationData['utilization'];

    /////////////////////////////////////
    // Return Response
    /////////////////////////////////////

    return $response;

  }

  function buildUtilizationResponseArray($accessData, $data) {

    $cleaned = [];

    //TBD
    //accountOwnerUserId

    //Cleaned Response Architecture (alphabetical)
    //Created By Data
    $cleaned['createdBy']['userId'] = ($data['tDmaMemberId'] > 0) ? $data['tDmaMemberId'] : 0;
    $cleaned['createdBy']['firstName'] = ($data['createdByFirstName'] != "") ? $data['createdByFirstName'] : null;
    $cleaned['createdBy']['lastName'] = ($data['createdByLastName'] != "") ? $data['createdByLastName'] : null;
    $cleaned['createdBy']['accountId'] = ($data['utilizationManagerAccountId'] > 0) ? $data['utilizationManagerAccountId'] : 0;
    $cleaned['createdBy']['accountName'] = ($data['utilizationManagerAccountTitle'] != "") ? $data['utilizationManagerAccountTitle'] : null;
    //Credit Amount Data
    $cleaned['creditId'] = ($data['creditId'] > 0) ? $data['creditId'] : 0;
    $cleaned['creditIdVisual'] = ($data['jurisdictionCode'] != "") ? $data['jurisdictionCode'] . $data['creditId'] : $data['creditId'];

    $cleaned['closingProcess']['statusId'] = ($data['statusId'] > 0) ? $data['statusId'] : 0;
    if($cleaned['closingProcess']['statusId'] == 0) {
      $cleaned['closingProcess']['statusName'] = "Not Started";
    } else {
      if($cleaned['closingProcess']['statusId'] == 1) {
        $cleaned['closingProcess']['statusName'] = "In Progress";
      } else {
        if($cleaned['closingProcess']['statusId'] == 2) {
          $cleaned['closingProcess']['statusName'] = "Completed";
        }
      }
    }
    $cleaned['closingProcess']['completeDate'] = ($data['utilizationCompleteDate'] > 0) ? date('Y-m-d', $data['utilizationCompleteDate']) : null;
    $cleaned['closingProcess']['utilizationReceived'] = ($data['utilizationReceived'] == 1) ? true : false;
    $cleaned['closingProcess']['utilizationReceivedDate'] = ($data['utilizationReceivedDate'] > 0) ? date('Y-m-d', $data['utilizationReceivedDate']) : null;
    $cleaned['closingProcess']['utilizationReceivedMethodId'] = ($data['utilizationReceivedMethodId'] > 0) ? $data['utilizationReceivedMethodId'] : 0;
    if($cleaned['closingProcess']['utilizationReceivedMethodId'] == 0) {
      $cleaned['closingProcess']['utilizationReceivedMethodName'] = "None";
    } else {
      if($cleaned['closingProcess']['utilizationReceivedMethodId'] == 1) {
        $cleaned['closingProcess']['utilizationReceivedMethodName'] = "Check";
      } else {
        if($cleaned['closingProcess']['utilizationReceivedMethodId'] == 2) {
          $cleaned['closingProcess']['utilizationReceivedMethodName'] = "Wire Transfer";
        } else {
          if($cleaned['closingProcess']['utilizationReceivedMethodId'] == 3) {
            $cleaned['closingProcess']['utilizationReceivedMethodName'] = "Applied Against Liability";
          }
        }
      }
    }

    $cleaned['closingProcess']['utilizationReceivedNotes'] = ($data['utilizationReceivedNotes'] != "") ? $data['utilizationReceivedNotes'] : null;

    //Credit account owner
    $cleaned['creditData']['account']['accountId'] = ($data['accountId'] > 0) ? $data['accountId'] : null;
    $cleaned['creditData']['account']['accountName'] = ($data['accountName'] != "") ? $data['accountName'] : null;
    $cleaned['creditData']['account']['accountOwnerUserId'] = ($data['accountOwnerUserId'] != "") ? $data['accountOwnerUserId'] : null;
    //Credit Amount Data
    $cleaned['creditData']['creditAmountData']['amountUSD'] = ($data['creditAmountUSD'] > 0) ? $data['creditAmountUSD'] : 0;
    $cleaned['creditData']['creditAmountData']['amountUSDRemaining'] = ($data['creditAmountRemainingUSD'] > 0) ? $data['creditAmountRemainingUSD'] : 0;
    $cleaned['creditData']['creditAmountData']['estimatedValueAsPercent'] = ($data['estimatedValueAsPercent'] > 0) ? $data['estimatedValueAsPercent'] : 0;
    $cleaned['creditData']['creditAmountData']['amountUSDEstimatedNet'] = $cleaned['creditData']['creditAmountData']['amountUSD'] * $cleaned['creditData']['creditAmountData']['estimatedValueAsPercent'];
    $cleaned['creditData']['creditAmountData']['amountUSDRemainingEstimatedNet'] = $cleaned['creditData']['creditAmountData']['amountUSDRemaining'] * $cleaned['creditData']['creditAmountData']['estimatedValueAsPercent'];
    //Legal Entity Data
    $cleaned['creditData']['creditHolderTaxEntity']['taxEntityId'] = ($data['creditTaxEntityId'] > 0) ? $data['creditTaxEntityId'] : null;
    $cleaned['creditData']['creditHolderTaxEntity']['accountType'] = ($data['taxEntityAccountType'] != "") ? ($data['taxEntityAccountType'] == 1 ? "business" : "individual") : null;
    $cleaned['creditData']['creditHolderTaxEntity']['companyName'] = ($data['taxEntityCompanyName'] != "") ? $data['taxEntityCompanyName'] : null;
    $cleaned['creditData']['creditHolderTaxEntity']['firstName'] = ($data['taxEntityFirstName'] != "") ? $data['taxEntityFirstName'] : null;
    $cleaned['creditData']['creditHolderTaxEntity']['lastName'] = ($data['taxEntityLastName'] != "") ? $data['taxEntityLastName'] : null;
    //Incentive Program
    $cleaned['creditData']['incentiveProgram']['oixIncentiveProgramId'] = ($data['oixIncentiveProgramId'] > 0) ? $data['oixIncentiveProgramId'] : null;
    $cleaned['creditData']['incentiveProgram']['incentiveProgramName'] = ($data['incentiveProgramName'] != "") ? $data['incentiveProgramName'] : null;
    $cleaned['creditData']['incentiveProgram']['jurisdictionCode'] = ($data['jurisdictionCode'] != "") ? $data['jurisdictionCode'] : null;
    $cleaned['creditData']['incentiveProgram']['jurisdictionName'] = ($data['jurisdictionName'] != "") ? $data['jurisdictionName'] : null;
    $cleaned['creditData']['incentiveProgram']['regionId'] = ($data['regionId'] > 0) ? $data['regionId'] : null;
    $cleaned['creditData']['incentiveProgram']['countryName'] = ($data['countryName'] != "") ? $data['countryName'] : null;
    $cleaned['creditData']['incentiveProgram']['regionCode'] = ($data['regionCode'] != "") ? $data['regionCode'] : null;
    $cleaned['creditData']['incentiveProgram']['programTypeId'] = ($data['programTypeId'] > 0) ? $data['programTypeId'] : null;
    $cleaned['creditData']['incentiveProgram']['programType'] = ($data['programType'] != "") ? $data['programType'] : null;
    //Loaded By Data
    $cleaned['creditData']['loadedBy']['userId'] = ($data['loadedByUserId'] > 0) ? $data['loadedByUserId'] : null;
    $cleaned['creditData']['loadedBy']['firstName'] = ($data['loadedByFirstName'] != "") ? $data['loadedByFirstName'] : null;
    $cleaned['creditData']['loadedBy']['lastName'] = ($data['loadedByLastName'] != "") ? $data['loadedByLastName'] : null;
    //Project Data
    $cleaned['creditData']['projectData']['projectName'] = ($data['projectName'] != "") ? $data['projectName'] : null;
    $cleaned['creditData']['projectData']['projectNameExt'] = ($data['projectNameExt'] != "") ? $data['projectNameExt'] : null;
    $cleaned['creditData']['projectData']['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : ($data['projectName'] != "" ? $data['projectName'] : null);

    $cleaned['utilizationStatus'] = ($data['tradeIsEstimated'] == 1) ? 'estimate' : 'actual';

    $cleaned['notes'] = ($data['notes'] != "") ? $data['notes'] : null;

    $cleaned['estimateData']['utilizationPercentageEstimate'] = ($data['tradePercentageEstimate'] > 0) ? $data['tradePercentageEstimate'] : null;
    $cleaned['estimateData']['utilizationSizeEstimate'] = ($data['tradeSizeEstimate'] > 0) ? $data['tradeSizeEstimate'] : null;
    $cleaned['estimateData']['utilizationPriceEstimate'] = ($data['tradePriceEstimate'] > 0) ? $data['tradePriceEstimate'] : null;
    $cleaned['estimateData']['utilizationPercentageEstimateCompareTo'] = ($data['tradePercentageEstimateCompareTo'] != "") ? $data['tradePercentageEstimateCompareTo'] : null;
    $cleaned['estimateData']['utilizationDateEstimate'] = ($data['tradeDateEstimate'] > 0) ? date('Y-m-d', $data['tradeDateEstimate']) : null;

    $cleaned['utilizationAmountData']['utilizationAmountLocal'] = ($data['utilizationAmountLocal'] > 0) ? $data['utilizationAmountLocal'] : 0;
    $cleaned['utilizationAmountData']['utilizationValuePerCredit'] = ($data['utilizationValuePerCredit'] > 0) ? $data['utilizationValuePerCredit'] : 0;

    $cleaned['utilizationDate'] = ($data['utilizationDate'] != "") ? $data['utilizationDate'] : null;

    $cleaned['utilizationFees']['total'] = ($data['totalFees'] > 0) ? $data['totalFees'] : 0;

    //Utlizing Entity Data
    $cleaned['utilizingEntity']['utilizingEntityId'] = ($data['utilizingEntityId'] > 0) ? $data['utilizingEntityId'] : null;
    $cleaned['utilizingEntity']['utilizingEntityCustomName'] = ($data['utilizingEntityCustomName'] != "") ? $data['utilizingEntityCustomName'] : null;
    $cleaned['utilizingEntity']['utilizingEntityName'] = ($data['utilizingEntityName'] != "") ? $data['utilizingEntityName'] : null;
    $cleaned['utilizingEntity']['utilizingEntityTaxEntityId'] = ($data['utilizingEntityTaxEntityId'] > 0) ? $data['utilizingEntityTaxEntityId'] : 0;
    $cleaned['utilizingEntity']['utilizingEntityType'] = ($data['utilizingEntityType'] != "") ? $data['utilizingEntityType'] : null;
    $cleaned['utilizingEntity']['utilizingEntityTypeName'] = ($data['utilizingEntityTypeName'] != "") ? $data['utilizingEntityTypeName'] : null;

    $cleaned['utilizationId'] = ($data['utilizationId'] > 0) ? $data['utilizationId'] : 0;

    $cleaned['utilizationType']['utilizationTypeId'] = ($data['utilizationTypeId'] > 0) ? $data['utilizationTypeId'] : 0;
    $cleaned['utilizationType']['utilizationTypeName'] = ($data['utName'] != "") ? $data['utName'] : null;

    $cleaned['customDataPoints'] = $this->get_custom_data_points($accessData, $data['creditOwnerAccountId'], $data['parentDmaId'], $data['utilizationId'], 'utilization');

    return $cleaned;

  }

  function get_utilization_api($accessData, $request) {

    /////////////////////////////////////
    // Setup Key Arrays
    /////////////////////////////////////

    $response = [];
    $response['code'] = 200;
    $response['success'] = 1;
    $response['utilization'] = [];
    $response['errors'] = [];
    $response['meta'] = [];

    /////////////////////////////////////
    // Validation
    /////////////////////////////////////

    $request['apiRequestAction'] = "get_utilization";
    $validationResult = $this->validate_and_build_utilization_data_request($accessData, $request);
    $utilizationData = $validationResult['utilizationData'];
    $errors = $validationResult['errors'];

    //If errors, return them and stop processing the script
    if(sizeof($errors) > 0) {
      $response['code'] = 422; //422 = "Unprocessable Entity"
      $response['success'] = 0;
      $response['message'] = "This request did not pass validation";
      $response['errors'] = $errors;
      $response['meta'] = [];

      return $response;
    }

    /////////////////////////////////////
    // Database Query
    /////////////////////////////////////

    //General Trade Info
    $this->db->select('tradeId as utilizationId, Trades.listingId as creditId, Trades.timeStamp as utilizationDate, Trades.taxpayerUserId as utilizingEntityTaxEntityId, Trades.tDmaMemberId, Trades.status as statusId, Trades.tradeIsEstimated, Trades.closingProcessStartDate, Trades.accountId as accountOwnerUserId, Trades.utilizationTypeId, Trades.tradeType as oixMarketplaceOrNot, Trades.utilizingEntityType, utilizationTypes.name as utName, Trades.hasFolders, Trades.tradeNotes as notes, Trades.deleteMarker, Trades.brokeDate, tradeSize as utilizationAmountLocal, tradePrice as utilizationValuePerCredit, Trades.tradePercentageEstimate, Trades.tradeDateEstimate, Trades.tradeSizeEstimate, Trades.tradePriceEstimate, Trades.tradePercentageEstimateCompareTo, Trades.sellerSigReady, Trades.sellerSigned, Trades.sellerSignedDate, Trades.settlementDate as utilizationCompleteDate, Trades.sellerRecPayment as utilizationReceived, Trades.sellerRecPaymentMethod as utilizationReceivedMethodId, Trades.sellerRecPaymentDate as utilizationReceivedDate, Trades.paymentNotes as utilizationReceivedNotes');
    //Incentive Program Info
    $this->db->select("PendingListings.OIXIncentiveId as oixIncentiveProgramId, IncentivePrograms.State as jurisdictionCode, IncentivePrograms.ProgramName as incentiveProgramName, PendingListings.credit_type_id as programTypeId, States.name as jurisdictionName, States.countryId as regionId, countries.name as countryName, countries.code as regionCode, ProgramType.ProgramTypeName as programType");

    //Project Info
    $this->db->select('PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.cTaxpayerId as creditTaxEntityId, PendingListings.listedBy, PendingListings.creditAmount as creditAmountUSD, PendingListings.availableToList as creditAmountRemainingUSD, PendingListings.estCreditPrice as estimatedValueAsPercent, PendingListings.cDmaMemberId as loadedByUserId');
    //Transaction Fees
    $this->db->select('transactionFeePer, buyerTransactionFeePer, thirdParty1FeePer, thirdParty2FeePer');
    //Legal Entity Data
    $this->db->select("Taxpayers.tpCompanyName as taxEntityCompanyName, Taxpayers.tpAccountType as taxEntityAccountType, Taxpayers.tpFirstName as taxEntityFirstName, Taxpayers.tpLastName as taxEntityLastName");
    //Credit Owner Account Data
    $this->db->select("creditOwnerAccount.title as accountName, creditOwnerAccount.dmaId as accountId, creditOwnerAccount.mainAdmin as accountOwnerUserId, Accounts.firstName as loadedByFirstName, Accounts.lastName as loadedByLastName");
    //Account Info
    $this->db->select('creditOwnerAccount.dmaId as creditOwnerAccountId, creditOwnerAccount.parentDmaId, creditOwnerAccount.title as creditOwnerAccountTitle, utilizationManagerAccount.dmaId as utilizationManagerAccountId, utilizationManagerAccount.title as utilizationManagerAccountTitle');
    //Utilization created by
    $this->db->select("createdBy.firstName as createdByFirstName, createdBy.lastName as createdByLastName");

    $this->db->from('Trades');
    $this->db->where('Trades.TradeId', $utilizationData['tradeId']);
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("TaxYear", "PendingListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->join("dmaAccounts as creditOwnerAccount", "PendingListings.listedBy = creditOwnerAccount.mainAdmin", 'left');
    $this->db->join("dmaAccounts as utilizationManagerAccount", "Trades.accountId = utilizationManagerAccount.mainAdmin", 'left');
    $this->db->join("Accounts as createdBy", "createdBy.userId = Trades.tDmaMemberId", 'left');
    $this->db->join("Accounts", "Accounts.userId = PendingListings.cDmaMemberId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');

    $query = $this->db->get();
    $response = [];
    $response['utilization'] = [];

    foreach($query->result_array() as $data) {

      //If estimated and estimation is based on percentage (not fixed amount) then process and over-ride
      if($data['tradeIsEstimated'] == 1 && $data['tradePercentageEstimate'] > 0) {
        $data = $this->processUtilizationEstimateCalculation($data);
      }
      $data['transactions'] = $this->get_utilization_transactions($data['utilizationId']);

      if($data['oixMarketplaceOrNot'] == "oix_marketplace_trade") {
        $data['totalFees'] = $data['utilizationAmountLocal'] * ($data['transactionFeePer'] + $data['buyerTransactionFeePer'] + $data['thirdParty1FeePer'] + $data['thirdParty2FeePer']);
      } else {
        $data['totalFees'] = 0;
      }

      $firstTrans = $data['transactions'][0];
      $data['utilizingEntityCustomName'] = $data['transactions'][0]['utilizingEntityCustomName'];
      $data['utilizingEntityId'] = 0;
      if($data['utilizingEntityType'] == "myaccounts") {
        $data['utilizingEntityName'] = $data['utilizationManagerAccountTitle'];
        $data['utilizingEntityId'] = $data['utilizationManagerAccountId'];
      } else {
        if($data['utilizingEntityType'] == "customname") {
          $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
          $data['utilizingEntityId'] = null;
        } else {
          if($firstTrans['taxpayerId'] > 0) {
            $extraText = ($data['oixMarketplaceOrNot'] == "oix_marketplace_trade") ? "(via " . $data['utilizationManagerAccountTitle'] . ")" : "";
            if(sizeof($data['transactions']) > 1) {
              $data['utilizingEntityName'] = sizeof($data['transactions']) . " Buyers " . $extraText;
            } else {
              $data['utilizingEntityName'] = $firstTrans['taxEntityNameFull'] . " " . $extraText;
              $data['utilizingEntityId'] = $firstTrans['taxpayerId'];
            }
          } else {
            $data['utilizingEntityName'] = $data['utilizationManagerAccountTitle'];
          }
        }
      }

      if($data['utilizingEntityType'] == "self") {
        $data['utilizingEntityTypeName'] = "Internal/Self";
      } else {
        if($data['utilizingEntityType'] == "taxentity") {
          $data['utilizingEntityTypeName'] = "Legal Entity";
        } else {
          if($data['utilizingEntityType'] == "customname") {
            $data['utilizingEntityTypeName'] = "Third-Party Company";
          } else {
            if($data['utilizingEntityType'] == "myaccounts") {
              $data['utilizingEntityTypeName'] = "Other OIX Account";
            } else {
              $data['utilizingEntityTypeName'] = "NA";
            }
          }
        }
      }

      $utilizationArray = $this->buildUtilizationResponseArray($accessData, $data);
      $response['utilization'] = array_merge($response['utilization'], $utilizationArray);

    }

    return $response;

  }

  function processUtilizationEstimateCalculation($data) {

    $data['tradePercentageEstimateWhole'] = number_format($data['tradePercentageEstimate'] * 100, 1);
    if($data['tradePercentageEstimate'] == 0.33) {
      $data['tradePercentageEstimate'] = 0.333333333333333;
    } else {
      if($data['tradePercentageEstimate'] == 0.66) {
        $data['tradePercentageEstimate'] = 0.666666666666666;
      }
    }
    $thisCreditAmountCompareTo = ($data['tradePercentageEstimateCompareTo'] == '' || $data['tradePercentageEstimateCompareTo'] == 'facevalue') ? $data['creditAmountUSD'] : $data['creditAmountRemainingUSD'];
    $data['utilizationAmountLocal'] = $thisCreditAmountCompareTo * $data['tradePercentageEstimate'];
    $data['tradeSizeEstimate'] = $thisCreditAmountCompareTo * $data['tradePercentageEstimate'];

    return $data;

  }

  function get_utilization_transactions($tradeId) {

    $this->db->select('Transactions.*, Taxpayers.*, Trades.closingProcessStartDate, Trades.tradeType, Trades.utilizationTypeId, Trades.tradePercentageEstimate, Trades.tradePercentageEstimateCompareTo, utilizationTypes.name as utName, Accounts.companyName as buyerCompanyName, PendingListings.creditAmount as creditFaceValue, PendingListings.availableToList as amountLocalRemaining');
    $this->db->where('Transactions.tradeId', $tradeId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("Accounts", "Transactions.buyerAccountId = Accounts.userId", 'left');
    $this->db->join("Trades", "Transactions.tradeId = Trades.tradeId", 'left');
    $this->db->join("PendingListings", "Trades.tradeId = PendingListings.listingId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data['tradePercentageEstimate'] > 0) {
        $thisCreditAmountCompareTo = ($data['tradePercentageEstimateCompareTo'] == '' || $data['tradePercentageEstimateCompareTo'] == 'facevalue') ? $data['creditFaceValue'] : $data['amountLocalRemaining'];
        $data['tCreditAmount'] = $thisCreditAmountCompareTo * $data['tradePercentageEstimate'];
      }

      $data['taxEntityNameFull'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . ' ' . $data['tpLastName'];
      array_push($return, $data);
    }

    return $return;

  }

  function insert_adjustment($cfa) {
    $this->db->insert('adjustment', $cfa);
  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
