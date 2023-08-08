<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Fox extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->load->library('filemanagementlib');
    $this->lang->load('tank_auth');
    $this->load->model('CreditListings');
    $this->load->model('Email_Model');

  }

  function curl_function($url, $method, $apiKey, $feedCredit) {

    $data_string = json_encode($feedCredit);

    $ch = curl_init(base_url() . $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if($method == "POST") {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    }
    if($method == "PUT") {
      curl_setopt($ch, CURLOPT_PUT, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                       'Content-Type: application/json',
                       'Authorization: ' . $apiKey,
                       //'Cookie: XDEBUG_SESSION=PHPSTORM',
                       'Content-Length: ' . strlen($data_string)]
    );
    //curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    //execute post
    $response = curl_exec($ch);
    //close connection
    curl_close($ch);
    $result = json_decode($response, true);

    if($method == "POST" || $method == "PUT") {
      return $result;
    } else {
      echo '<pre id="json"></pre>';
      echo '<script>var data = ' . $response . ';';
      echo 'document.getElementById("json").innerHTML = JSON.stringify(data, undefined, 2);</script>';
    }

  }

  function foxFindKeyFromValue($array, $field, $value) {
    foreach($array as $k => $v) {
      if($v[$field] === $value) {
        return $k;
      }
    }

    return false;
  }

  function foxPrepareKeyValueArray($array, $fieldId, $fieldValue) {
    $thisKVArray = [];
    $keyCount = 0;
    foreach($array as $a) {
      if($fieldValue == "*") {
        $thisKVArray[$a[$fieldId]] = $a;
      } else {
        $thisKVArray[$a[$fieldId]] = $a[$fieldValue];
      }
      $keyCount++;
    }

    return $thisKVArray;
  }

  function feed($uniqueId, $minInternalId = "", $maxInternalId = "", $excludeScript = "") {

    ////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////
    /// NOTE: BEFORE NEXT IMPORT - THIS REPORT DOES NOT CURRENTLY LOOK FOR DELETED TRADES!!!! MAKE SURE YOU GET ALL TRADES AND SEE IF ANY NO LONGER GET RETURNED
    throw new \Exception('General fail');
    ////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////

    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    $minInternalId = ($minInternalId > 0) ? $minInternalId : 0;
    $maxInternalId = ($maxInternalId > 0) ? $maxInternalId : 10000000000000;

    ////////////////////////////////////////////////////////
    /// START - If pulling from JSON from Disney Endpoint
    ////////////////////////////////////////////////////////
    /*
    $foxDataFeed = "https://dis-prod-func-incentives-rbe.azurewebsites.net/api/synch?code=khuUAa5MJn8aFI9yKdTQ3rj54q7ZKjGzagIugoHGSZE7oWE69EQmbw==";
    $doubleDecode = true;
    */
    ////////////////////////////////////////////////////////
    /// END - If pulling from JSON from Disney Endpoint
    ////////////////////////////////////////////////////////

    ////////////////////////////////////////////////////////
    /// START - If pulling from JSON file rather than Disney Endpoint
    ////////////////////////////////////////////////////////
    /* */
    $fileRequest['awsS3FileKey'] = 'fox/fox-2019-Q2.json';
    $fileRequest['awsS3BucketName'] = $this->config->item('OIX_AWS_BUCKET_NAME_SENSITIVE_STATIC_FILES');
    $fileRequest['expirationMinutes'] = '+2 minutes';
    $foxDataFeed = $this->filemanagementlib->get_aws_s3_file_url($fileRequest);
    $doubleDecode = false;
    ////////////////////////////////////////////////////////
    /// END - If pulling from JSON file rather than Disney Endpoint
    ////////////////////////////////////////////////////////

    ini_set('memory_limit', '200M');
    set_time_limit(14400);     // Increase execution time limit to 4 hrss for handling mass result set

    //cURL to grab JSON data/file
    $ch = curl_init();
    // Disable SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Will return the response, if false it print the response
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Set the url
    curl_setopt($ch, CURLOPT_URL, $foxDataFeed);
    // Execute
    $result = curl_exec($ch);
    // Closing
    curl_close($ch);

    //Decode JSON
    $dataFinal = json_decode($result, true);
    if($doubleDecode) {
      $dataFinal = json_decode($dataFinal, true);
    }

    //TEST IF JSON IS COMING THROUGH AND IS PROPERLY FORMATTED AS PHP ARRAY
    /*
    var_dump($dataFinal);
    throw new \Exception('General fail');
    */
    ////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////
    /// START - Processing DATA
    ////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////

    $oix = [];
    $oix['projects'] = [];
    $oix['credits'] = [];
    $oix['utilizations'] = [];
    $oix['apiErrors'] = [];
    //Preview for testing
    $preview = [];
    $preview['rows'] = [];
    $preview['total_gross_local_currency'] = 0;
    $preview['total_gross_usd'] = 0;
    $preview['total_net_usd'] = 0;
    $preview['total_received_usd'] = 0;
    $preview['total_pending_usd'] = 0;

    if(is_array($dataFinal)) {

      //CONVERTS SOME TABLES INTO SIMPLE KEY VALUE ARRAYS FOR RAPID LOOKUP
      $Groups = $this->foxPrepareKeyValueArray($dataFinal['Groups'], "GroupId", "Name");
      $Divisions = $this->foxPrepareKeyValueArray($dataFinal['Divisions'], "DivisionId", "Name");
      $DivisionsAbbreviated = $this->foxPrepareKeyValueArray($dataFinal['Divisions'], "DivisionId", "Abbreviation");
      $LegalEntities = $this->foxPrepareKeyValueArray($dataFinal['LegalEntities'], "LegalEntityId", "Name");
      $Countries = $this->foxPrepareKeyValueArray($dataFinal['Countries'], "CountryId", "Name");
      $Jurisdictions = $this->foxPrepareKeyValueArray($dataFinal['Jurisdictions'], "JurisdictionId", "Name");
      $CountriesByJurisdictions = $this->foxPrepareKeyValueArray($dataFinal['Jurisdictions'], "JurisdictionId", "CountryId");
      $States = $this->foxPrepareKeyValueArray($dataFinal['States'], "StateId", "Name");
      $StatesByJurisdictions = $this->foxPrepareKeyValueArray($dataFinal['Jurisdictions'], "JurisdictionId", "StateId");
      $IncentiveTypes = $this->foxPrepareKeyValueArray($dataFinal['IncentiveTypes'], "IncentiveTypeId", "Name");
      $IncentiveTypesParents = $this->foxPrepareKeyValueArray($dataFinal['IncentiveTypes'], "IncentiveTypeId", "ParentIncentiveTypeId");
      $Currencies = $this->foxPrepareKeyValueArray($dataFinal['Currencies'], "CurrencyId", "Code");
      $PeriodsYear = $this->foxPrepareKeyValueArray($dataFinal['Periods'], "PeriodId", "Year");
      $PeriodsQuarter = $this->foxPrepareKeyValueArray($dataFinal['Periods'], "PeriodId", "Quarter");
      $PeriodsName = $this->foxPrepareKeyValueArray($dataFinal['Periods'], "PeriodId", "Name");
      $PeriodsPeriodEndDate = $this->foxPrepareKeyValueArray($dataFinal['Periods'], "PeriodId", "PeriodEndDate");
      $IncentiveCategories = $this->foxPrepareKeyValueArray($dataFinal['IncentiveCategories'], "IncentiveCategoryId", "Name");
      $TransactionCategories = $this->foxPrepareKeyValueArray($dataFinal['TransactionCategories'], "TransactionCategoryId", "Name");
      $TransactionStatuses = $this->foxPrepareKeyValueArray($dataFinal['TransactionStatuses'], "TransactionStatusId", "Name");
      $rawExchangeRatesArray = [];
      foreach($dataFinal['ExchangeRates'] as $exch) {
        foreach($dataFinal['Currencies'] as $curr) {
          if($curr['CurrencyId'] == $exch['DestinationCurrencyId']) {
            if(isset($rawExchangeRatesArray[$curr['Code']])) {
              if($exch['ActiveAsOf'] > $rawExchangeRatesArray[$curr['Code']]['activeAsOf']) {
                $rawExchangeRatesArray[$curr['Code']]['currencyCode'] = $curr['Code'];
                $rawExchangeRatesArray[$curr['Code']]['exchangeRate'] = $exch['Rate'];
                $rawExchangeRatesArray[$curr['Code']]['activeAsOf'] = $exch['ActiveAsOf'];
              }
            } else {
              $rawExchangeRatesArray[$curr['Code']]['currencyId'] = $curr['CurrencyId'];
              $rawExchangeRatesArray[$curr['Code']]['currencyCode'] = $curr['Code'];
              $rawExchangeRatesArray[$curr['Code']]['exchangeRate'] = $exch['Rate'];
              $rawExchangeRatesArray[$curr['Code']]['activeAsOf'] = $exch['ActiveAsOf'];
            }
          }
        }
      }
      //Re-order IncentiveRevisions so most recent are at top
      usort($dataFinal['IncentiveRevisions'], function($a, $b) {
        return $a['LastModifiedDate'] < $b['LastModifiedDate'];
      });
      //Loop through exchange rates and only capture the first (most recent) of each
      $IncentiveRevisionsIdTracker = [];
      $IncentiveRevisionsMostRecent = [];
      foreach($dataFinal['IncentiveRevisions'] as $ir) {
        if(!in_array($ir['IncentiveId'], $IncentiveRevisionsIdTracker)) {
          array_push($IncentiveRevisionsMostRecent, $ir);
          array_push($IncentiveRevisionsIdTracker, $ir['IncentiveId']);
        }
      }
      $IncentiveRevisions = $this->foxPrepareKeyValueArray($IncentiveRevisionsMostRecent, "IncentiveId", "GrossUsd");
      $IncentiveRevisions_grossLocalCurrency = $this->foxPrepareKeyValueArray($IncentiveRevisionsMostRecent, "IncentiveId", "GrossLocalCurrency");

      //Re-order ExchangeRates so most recent are at top
      usort($dataFinal['ExchangeRates'], function($a, $b) {
        return $a['ActiveAsOf'] < $b['ActiveAsOf'];
      });
      //Loop through exchange rates and only capture the first (most reecent) of each
      $ExchangeRatesIdTracker = [];
      $ExchangeRatesMostRecent = [];
      foreach($dataFinal['ExchangeRates'] as $er) {
        if(!in_array($er['DestinationCurrencyId'], $ExchangeRatesIdTracker)) {
          array_push($ExchangeRatesMostRecent, $er);
          array_push($ExchangeRatesIdTracker, $er['DestinationCurrencyId']);
        }
      }
      $ExchangeRates = $this->foxPrepareKeyValueArray($ExchangeRatesMostRecent, "DestinationCurrencyId", "Rate");

      //$Transferee = $this->foxPrepareKeyValueArray($dataFinal['TransactionCategories'], "TransfereeId", "Name");

      foreach($dataFinal['Productions'] as $Production) {
        $ProductionId = $Production['ProductionId'];
        $oix['projects'][$ProductionId] = [];
        //Project Info
        $oix['projects'][$ProductionId]['projectId'] = strval($Production['ProjectId']);
        $oix['projects'][$ProductionId]['projectName'] = $Production['Title'];
        $oix['projects'][$ProductionId]['workingTitle'] = $Production['WorkingTitle'];

        //Account Info
        $oix['projects'][$ProductionId]['group'] = $Groups[$Production['GroupId']];
        $oix['projects'][$ProductionId]['division'] = $Divisions[$Production['DivisionId']];
        $oix['projects'][$ProductionId]['divisionAbbreviated'] = $DivisionsAbbreviated[$Production['DivisionId']];

        //Key Dates
        $oix['projects'][$ProductionId]['preProductionStartDate'] = $Production['ProductionStartDate'];
        $oix['projects'][$ProductionId]['preProductionEndDate'] = $Production['ProductionStartDate'];
        $oix['projects'][$ProductionId]['principalPhotographyStartDate'] = $Production['PrincipalPhotographyStartDate'];
        $oix['projects'][$ProductionId]['principalPhotographyEndDate'] = $Production['PrincipalPhotographyEndDate'];
        $oix['projects'][$ProductionId]['postProductionStartDate'] = $Production['PostProductionStartDate'];
        $oix['projects'][$ProductionId]['postProductionEndDate'] = $Production['PostProductionEndDate'];
        $oix['projects'][$ProductionId]['productionDeliveryDate'] = $Production['DeliveryDate'];
        $oix['projects'][$ProductionId]['productionReleaseDate'] = $Production['ReleaseDate'];
        $oix['projects'][$ProductionId]['productionReleaseDvdDate'] = $Production['ReleaseDvdDate'];
        //Archive Status
        $oix['projects'][$ProductionId]['isArchived'] = ($Production['Completed']) ? 1 : 0;

        //Contact
        $oix['projects'][$ProductionId]['contactEstimator'] = null;
        $oix['projects'][$ProductionId]['contactFinance'] = null;
        $oix['projects'][$ProductionId]['contactFeaturesProductionAccountant'] = null;
        $oix['projects'][$ProductionId]['contactPostProductionAccountant'] = null;
        $oix['projects'][$ProductionId]['contactProductionExecutive'] = null;
        $oix['projects'][$ProductionId]['contactVFX'] = null;

        foreach($dataFinal['ProductionContacts'] as $pc) {
          if($pc["ProductionId"] == $ProductionId) {
            foreach($dataFinal['Contacts'] as $contact) {
              if($contact['ContactId'] == $pc['ContactId']) {

                //Contact - Estimator
                if($contact["ProductionContactTypeId"] == 1) {
                  $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
                  $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
                  $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
                  $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
                  $oix['projects'][$ProductionId]['contactEstimator'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
                }

                //Contact - Finance
                if($contact["ProductionContactTypeId"] == 2) {
                  $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
                  $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
                  $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
                  $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
                  $oix['projects'][$ProductionId]['contactFinance'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
                }

                //Contact - Features Production Accountant
                if($contact["ProductionContactTypeId"] == 3) {
                  $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
                  $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
                  $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
                  $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
                  $oix['projects'][$ProductionId]['contactFeaturesProductionAccountant'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
                }

                //Contact - Post Production Accounting Contact
                if($contact["ProductionContactTypeId"] == 4) {
                  $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
                  $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
                  $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
                  $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
                  $oix['projects'][$ProductionId]['contactPostProductionAccountant'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
                }

                //Contact - Production Executive Contact
                if($contact["ProductionContactTypeId"] == 5) {
                  $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
                  $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
                  $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
                  $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
                  $oix['projects'][$ProductionId]['contactProductionExecutive'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
                }

                //Contact - VFX
                if($contact["ProductionContactTypeId"] == 6) {
                  $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
                  $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
                  $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
                  $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
                  $oix['projects'][$ProductionId]['contactVFX'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
                }

              }
            }
          }
        }

        //Incentive Owner
        $oix['projects'][$ProductionId]['internalOwner'] = null;
        if($Production['IncentivesTeamOwnerUserId'] > 0) {
          foreach($dataFinal['Contacts'] as $contact) {
            if($contact['ContactId'] == $Production['IncentivesTeamOwnerUserId']) {
              $FirstName = ($contact['FirstName'] != "") ? $contact['FirstName'] : "";
              $LastName = ($contact['LastName'] != "") ? $contact['LastName'] : "";
              $EmailAddress = ($contact['EmailAddress'] != "") ? " - " . $contact['EmailAddress'] : "";
              $PhoneNumber = ($contact['PhoneNumber'] != "") ? " - " . $contact['PhoneNumber'] : "";
              $oix['projects'][$ProductionId]['internalOwner'] = $FirstName . $LastName . $EmailAddress . $PhoneNumber;
            }
          }
        }

      }

      ////////////////////////////////////////////////////////
      /// START - Check for now missing (deleted) incentives
      ////////////////////////////////////////////////////////

      if($this->config->item('isLocal')) {
        $listedBy = [498];
      } else {
        if($this->config->item('environment') == "DEV") {
          $listedBy = [1256];
        } else {
          $listedBy = [11399, 11403, 11404];
        }
      }
      $existingCredits = [];
      foreach($listedBy as $lb) {
        $existingCreditsChunk = $this->CreditListings->get_CreditIds_with_InternalIds_Account($lb);
        $existingCredits += $existingCreditsChunk;
      }
      $newCredits = [];
      foreach($dataFinal['Incentives'] as $nc) {
        $newCredits[$nc['IncentiveId']] = [$nc['IncentiveId']];
      }
      $oldIncentivesNoLongerExist = [];
      foreach($existingCredits as $ck => $cv) {
        if($ck != "" && !array_key_exists($ck, $newCredits)) {
          $oldIncentivesNoLongerExist[$ck] = $cv;
        }
      }
      echo "Delete These Credits:<br>Listing ID (Internal ID)<br>";
      foreach($oldIncentivesNoLongerExist as $ck2 => $cv2) {
        echo $cv2 . " (" . $ck2 . ") <br>";
      }
      echo "<br><br>";

      ////////////////////////////////////////////////////////
      /// END - Check for now missing (deleted) incentives
      ////////////////////////////////////////////////////////

      foreach($dataFinal['Incentives'] as $Incentive) {

        $preview['rows'][$Incentive['IncentiveId']]['gross_local_currency'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['gross_local_currency_utilized'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['gross_local_currency_utilized_actual'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['gross_usd'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['net_usd'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['received_usd'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['pending_usd'] = 0;
        $preview['rows'][$Incentive['IncentiveId']]['project_info'] = "";
        $preview['rows'][$Incentive['IncentiveId']]['isArchived'] = false;

        $IncentiveId = $Incentive['IncentiveId'];
        $ProductionId = $Incentive['ProductionId'];
        $oix['credits'][$IncentiveId] = [];

        //Incentive ID / Internal ID
        $oix['credits'][$IncentiveId]['internalId'] = $Incentive['IncentiveId'];

        //Project Data
        $oix['credits'][$IncentiveId]['isArchived'] = $oix['projects'][$ProductionId]['isArchived'];
        $preview['rows'][$Incentive['IncentiveId']]['isArchived'] = $oix['projects'][$ProductionId]['isArchived'];
        $oix['credits'][$IncentiveId]['projectData'] = $oix['projects'][$ProductionId];
        $oix['credits'][$IncentiveId]['projectName'] = $oix['projects'][$ProductionId]['projectName'];
        $preview['rows'][$Incentive['IncentiveId']]['project_info'] = $oix['projects'][$ProductionId]['projectName'] . " (" . $Incentive['ProductionId'] . ") (" . $Incentive['IncentiveId'] . ")";

        $oix['credits'][$IncentiveId]['projectBudget'] = $Incentive['BudgetUsd'];

        //Jurisdiction
        //$oix['credits'][$IncentiveId]['stateName'] = $Jurisdictions[$Incentive['JurisdictionId']];
        $oix['credits'][$IncentiveId]['jurisdictionName'] = $Jurisdictions[$Incentive['JurisdictionId']];
        //Country
        $CountryId = $CountriesByJurisdictions[$Incentive['JurisdictionId']];
        $oix['credits'][$IncentiveId]['countryName'] = ($CountryId > 0 && $oix['credits'][$IncentiveId]['jurisdictionName'] != "TBD" && $oix['credits'][$IncentiveId]['jurisdictionName'] != "") ? $Countries[$CountryId] : "TBD";
        $localCurrency = $Currencies[$Incentive['CurrencyId']];
        $oix['credits'][$IncentiveId]['isDomestic'] = ($localCurrency == "USD") ? 1 : 0;
        //State - just using Jusridiction for state since it's the same
        $StateId = $StatesByJurisdictions[$Incentive['JurisdictionId']];
        $oix['credits'][$IncentiveId]['stateName'] = ($StateId > 0 && $oix['credits'][$IncentiveId]['jurisdictionName'] != "TBD" && $oix['credits'][$IncentiveId]['jurisdictionName'] != "") ? $States[$StateId] : "TBD";

        //Incentive Type
        if(isset($IncentiveTypesParents[$Incentive['IncentiveTypeId']])) {
          $oix['credits'][$IncentiveId]['incentiveType'] = ($IncentiveTypes[$IncentiveTypesParents[$Incentive['IncentiveTypeId']]] != "TBD") ? $IncentiveTypes[$IncentiveTypesParents[$Incentive['IncentiveTypeId']]] : null;
          $oix['credits'][$IncentiveId]['incentiveDesignation'] = $IncentiveTypes[$Incentive['IncentiveTypeId']];
        } else {
          $oix['credits'][$IncentiveId]['incentiveType'] = $IncentiveTypes[$Incentive['IncentiveTypeId']];
          $oix['credits'][$IncentiveId]['incentiveDesignation'] = null;
        }

        //Incentive Categories
        $oix['credits'][$IncentiveId]['incentiveCategory'] = $IncentiveCategories[$Incentive['IncentiveCategoryId']];
        //Incentive Program Name
        $incentiveProgramName = "";
        if($oix['credits'][$IncentiveId]['jurisdictionName'] != "" && $oix['credits'][$IncentiveId]['jurisdictionName'] != "TBD") {
          $incentiveProgramName .= $oix['credits'][$IncentiveId]['jurisdictionName'];
          $incentiveProgramName .= ($oix['credits'][$IncentiveId]['incentiveCategory'] != "" && $oix['credits'][$IncentiveId]['incentiveCategory'] != "TBD") ? " - " . $oix['credits'][$IncentiveId]['incentiveCategory'] : "";
          $incentiveProgramName .= ($oix['credits'][$IncentiveId]['incentiveDesignation'] != "" && $oix['credits'][$IncentiveId]['incentiveDesignation'] != "TBD") ? " - " . $oix['credits'][$IncentiveId]['incentiveDesignation'] : "";
        }
        $oix['credits'][$IncentiveId]['incentiveProgramName'] = $incentiveProgramName;

        //Currency
        $oix['credits'][$IncentiveId]['currencyId'] = $Incentive['CurrencyId'];
        $oix['credits'][$IncentiveId]['localCurrency'] = $Currencies[$Incentive['CurrencyId']];
        $exchangeRateRaw = (isset($rawExchangeRatesArray[$localCurrency]['exchangeRate']) && $rawExchangeRatesArray[$localCurrency]['exchangeRate'] > 0) ? $rawExchangeRatesArray[$localCurrency]['exchangeRate'] : 1;
        $oix['credits'][$IncentiveId]['exchangeRate'] = 1 / $exchangeRateRaw;

        //Legal entity
        $oix['credits'][$IncentiveId]['taxEntityCompanyName'] = $LegalEntities[$Incentive['LegalEntityId']];
        //Notes
        $oix['credits'][$IncentiveId]['generalNotes'] = $Incentive['Comments'];
        $oix['credits'][$IncentiveId] = array_merge($oix['credits'][$IncentiveId], $oix['projects'][$ProductionId]);
        //Get the credit amount
        $oix['credits'][$IncentiveId]['amountLocal'] = ($IncentiveRevisions_grossLocalCurrency[$IncentiveId] > 0) ? $IncentiveRevisions_grossLocalCurrency[$IncentiveId] : 0; //$IncentiveRevisions[$IncentiveId]
        $preview['rows'][$Incentive['IncentiveId']]['gross_local_currency'] += $oix['credits'][$IncentiveId]['amountLocal'];

        //Prepare for first Transaction to get vintage tax year
        $oix['credits'][$IncentiveId]['Transactions_LastModifiedDate'] = null;
        $oix['credits'][$IncentiveId]['vintageTaxYear'] = null;
        //Prepare for transactions array
        $oix['credits'][$IncentiveId]['Transactions'] = [];
        $oix['credits'][$IncentiveId]['TransactionsTotalUSD'] = 0;

      }

      foreach($dataFinal['Transactions'] as $Transaction) {
        $TransactionId = $Transaction['TransactionId'];
        $IncentiveId = $Transaction['IncentiveId'];

        //FIRST - HANDLE STUFF ON INCENTIVE / CREDIT LEVEL
        //If this tranaction on this incentive is older than the last, then replace the info (we want the first transaction)
        if($Transaction['LastModifiedDate'] < $oix['credits'][$IncentiveId]['Transactions_LastModifiedDate'] || $oix['credits'][$IncentiveId]['Transactions_LastModifiedDate'] == null) {
          $oix['credits'][$IncentiveId]['Transactions_LastModifiedDate'] = $Transaction['LastModifiedDate'];
          $oix['credits'][$IncentiveId]['vintageTaxYear'] = $PeriodsYear[$Transaction['TaxPeriodId']];
        }

        //SECOND - HANDLE STUFF ON UTILIZATIONS LEVEL
        $oix['utilizations'][$TransactionId]['utilizationInternalId'] = $TransactionId;
        $oix['utilizations'][$TransactionId]['internalCreditId'] = $IncentiveId;
        $oix['utilizations'][$TransactionId]['utilizationAmountLocal'] = $Transaction['LocalCurrencyAmount'];
        $oix['utilizations'][$TransactionId]['DiscountRate'] = $Transaction['DiscountRate'];
        $oix['utilizations'][$TransactionId]['InterestAmount'] = $Transaction['InterestAmount'];
        $oix['utilizations'][$TransactionId]['CurrentFxRate'] = ($oix['credits'][$IncentiveId]['isDomestic'] == 1) ? 1 : $ExchangeRates[$oix['credits'][$IncentiveId]['currencyId']]; //82 = is USD, so set to 1
        $oix['utilizations'][$TransactionId]['utilizationExchangeRate'] = ($Transaction['SpotExchangeRate'] > 0) ? $Transaction['SpotExchangeRate'] : $oix['utilizations'][$TransactionId]['CurrentFxRate'];
        $oix['utilizations'][$TransactionId]['utilizationExchangeRateToUSD'] = 1 / $oix['utilizations'][$TransactionId]['utilizationExchangeRate'];
        //Now that we have all the raw data, let's make the final USD values
        //1. Price per credit in USD
        $oix['utilizations'][$TransactionId]['utilizationValuePerCredit'] = $Transaction['DiscountRate'];
        //4. Interest Amount in USD
        $oix['utilizations'][$TransactionId]['interestAmountLocal'] = $Transaction['InterestAmount'];

        $periodStringFirst4 = substr($PeriodsName[$Transaction['FinancePeriodId']], 0, 4);
        $isValidPeriod = (is_numeric($periodStringFirst4)) ? true : false; //if the first 4 characters are NOT numeric, then it is some hack "TBD" or somethign so we will ignore these date fields as they are set to whacky things like the year 2099 or 1900
        $oix['utilizations'][$TransactionId]['utilizationPeriodId'] = $Transaction['FinancePeriodId'];
        $oix['utilizations'][$TransactionId]['utilizationDate'] = ($isValidPeriod && $Transaction['FinancePeriodId'] != "TBD") ? $PeriodsPeriodEndDate[$Transaction['FinancePeriodId']] : null;
        $oix['utilizations'][$TransactionId]['utilizationDateName'] = $PeriodsName[$Transaction['FinancePeriodId']]; //we take whatever the name is - even if it is TBD
        $oix['utilizations'][$TransactionId]['utilizationDateQuarter'] = ($isValidPeriod) ? $PeriodsQuarter[$Transaction['FinancePeriodId']] : null;
        $oix['utilizations'][$TransactionId]['utilizationDateYear'] = ($isValidPeriod) ? $PeriodsYear[$Transaction['FinancePeriodId']] : null;

        $oix['utilizations'][$TransactionId]['statusName'] = $TransactionStatuses[$Transaction['TaxStatusId']];

        $oix['utilizations'][$TransactionId]['utilizationStatus'] = "estimate";
        $oix['utilizations'][$TransactionId]['monetizationStatus'] = null;
        $oix['utilizations'][$TransactionId]['isDeleted'] = 0;
        // 1 = "Received" / 2 = "Pending as Estimated" / 3 = "Pending as Assessed" / 4 = "Pending as Filed" / 5 = "Pending as Claimed" / 6 = "Abandoned"
        if($Transaction['TaxStatusId'] == 1) {
          $oix['utilizations'][$TransactionId]['utilizationStatus'] = "actual"; //only received utilizations are "actual"
          $oix['utilizations'][$TransactionId]['monetizationStatus'] = 1;
        } else {
          if($Transaction['TaxStatusId'] == 6) {
            $oix['utilizations'][$TransactionId]['isDeleted'] = 1;
          }
        }
        $oix['utilizations'][$TransactionId]['statusId'] = 0;
        if(in_array($Transaction['TaxStatusId'], [1])) {
          $oix['utilizations'][$TransactionId]['statusId'] = 2; // = OIX Completed
        }
        if(in_array($Transaction['TaxStatusId'], [3, 4, 5])) {
          $oix['utilizations'][$TransactionId]['statusId'] = 1; // = OIX In Progress
        }

        $notes = $Transaction['MonetizationComments'] . " /// Additional Data Values: / Local Currency Amount = " . $oix['utilizations'][$TransactionId]['utilizationAmountLocal'] . " / Current FX Rate = " . $oix['utilizations'][$TransactionId]['CurrentFxRate'] . " / Exchange Rate = " . $oix['utilizations'][$TransactionId]['utilizationExchangeRate'] . " / Discount Rate = " . $oix['utilizations'][$TransactionId]['DiscountRate'];
        $oix['utilizations'][$TransactionId]['tradeNotes'] = $notes;

        //$TransactionCategories  = (1) Sale, (2) Utilized, (3) Refunded
        $oix['utilizations'][$TransactionId]['utilizationTypeName'] = ($Transaction['TransactionCategoryId'] > 0) ? $TransactionCategories[$Transaction['TransactionCategoryId']] : "";
        $oix['utilizations'][$TransactionId]['utilizationTypeId'] = 2; //set default to refund, as that's what it looks like Fox's default is
        $oix['utilizations'][$TransactionId]['utilizingEntityType'] = "self";
        $oix['utilizations'][$TransactionId]['transactions'] = [];
        $oix['utilizations'][$TransactionId]['utilizingEntityCustomName'] = ($oix['credits'][$IncentiveId]['taxEntityCompanyName'] != "") ? $oix['credits'][$IncentiveId]['taxEntityCompanyName'] : "TBD";
        if($Transaction['TransactionCategoryId'] == 1) { //Trade/Sale
          $oix['utilizations'][$TransactionId]['utilizationTypeId'] = 5;
          $oix['utilizations'][$TransactionId]['utilizingEntityType'] = "customname";
          if($Transaction['Transferees'] != "") {
            $oix['utilizations'][$TransactionId]['utilizingEntityCustomName'] = ($Transferee[$Transaction['Transferees']] != "") ? $Transferee[$Transaction['Transferees']] : "TBD";
          }
        }
        if($Transaction['TransactionCategoryId'] == 2) { //Internal Use - Applied against liability
          $oix['utilizations'][$TransactionId]['utilizationTypeId'] = 1;
          $oix['utilizations'][$TransactionId]['utilizingEntityType'] = "self";
          $oix['utilizations'][$TransactionId]['utilizingEntityCustomName'] = ($oix['credits'][$IncentiveId]['taxEntityCompanyName'] != "") ? $oix['credits'][$IncentiveId]['taxEntityCompanyName'] : "TBD";
          //$oix['utilizations'][$TransactionId]['utilizingEntityCustomName'] = ($Transferee[$Transaction['Transferees']]!="") ? $Transferee[$Transaction['Transferees']] : "TBD";
        }
        if($Transaction['TransactionCategoryId'] == 3) { //Internal Use - Refund
          $oix['utilizations'][$TransactionId]['utilizationTypeId'] = 2;
          $oix['utilizations'][$TransactionId]['utilizingEntityType'] = "self";
          $oix['utilizations'][$TransactionId]['utilizingEntityCustomName'] = ($oix['credits'][$IncentiveId]['taxEntityCompanyName'] != "") ? $oix['credits'][$IncentiveId]['taxEntityCompanyName'] : "TBD";
          //$oix['utilizations'][$TransactionId]['utilizingEntityCustomName'] = ($Transferee[$Transaction['Transferees']]!="") ? $Transferee[$Transaction['Transferees']] : "TBD";
        }

        //Add some project/incentive information
        $oix['utilizations'][$TransactionId]['projectName'] = $oix['credits'][$IncentiveId]['projectName'];

        //Add to total transactions USD tally for this credit
        $oix['credits'][$IncentiveId]['TransactionsTotalUSD'] += $oix['utilizations'][$TransactionId]['utilizationAmountLocal'];
        //Push into the Credits array
        array_push($oix['credits'][$IncentiveId]['Transactions'], $oix['utilizations'][$TransactionId]);

      }

      //HANDLE DATA INTEGRATION

      //Loop through each
      foreach($oix['credits'] as $feedCredit) {

        //Loop through each existing credit to find a match (or not)
        $match = 0;
        foreach($existingCredits as $keyInternalId => $valueListingId) {

          //If matching incentive ID key exists in Fox groomed data, then look for any data field updates
          if($feedCredit['internalId'] == $keyInternalId) {

            $feedCredit['creditId'] = $valueListingId;

            $match++;
          }

        }

        //Setup API Key depending on which account it is
        if($feedCredit['group'] == "Film") {
          $apiKey = "asdfa9sfu98sudfosfhkhih9e3r2rewr2r323"; //Use Film account
        } else {
          if($feedCredit['divisionAbbreviated'] == "FX") {
            if($this->config->item('environment') == "LIVE") {
              $apiKey = "jfu8jwfhfojwe2734ucwdehwoe9d8weh"; //Use FX account
            } else {
              $apiKey = "asdfa9sfu98sudfosfhkhih9e3r2rewr2r323"; //Use generic TV account
            }
          } else {
            if($this->config->item('environment') == "LIVE") {
              $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Use TV account
            } else {
              $apiKey = "asdfa9sfu98sudfosfhkhih9e3r2rewr2r323"; //Use generic TV account
            }
          }
        }

        $feedCreditFinal = [
            'internalId'             => $feedCredit['internalId'],
            'oixIncentiveProgramId'  => null,
            'isArchived'             => $feedCredit['isArchived'],
            'customProgram'          => [
                'incentiveProgramName' => ($feedCredit['incentiveProgramName'] != "") ? $feedCredit['incentiveProgramName'] : "TBD",
                'jurisdictionName'     => ($feedCredit['jurisdictionName'] != "" && $feedCredit['jurisdictionName'] != "TBD") ? $feedCredit['jurisdictionName'] : null,
                'countryName'          => ($feedCredit['countryName'] != "" && $feedCredit['countryName'] != "TBD") ? $feedCredit['countryName'] : null,
                'programNotes'         => ($feedCredit['incentiveDesignation'] != "" && $feedCredit['incentiveDesignation'] != "TBD") ? $feedCredit['incentiveDesignation'] : null,
            ],
            'incentiveProgramTypeId' => null,
            'taxEntityId'            => null,
            'taxEntityCompanyName'   => ($feedCredit['taxEntityCompanyName'] != "") ? $feedCredit['taxEntityCompanyName'] : "TBD", //Default this to TBD
            'vintageTaxYear'         => ($feedCredit['vintageTaxYear'] != "" && $feedCredit['vintageTaxYear'] != "TBD") ? $feedCredit['vintageTaxYear'] : null,
            'projectName'            => ($feedCredit['projectName'] != "" && $feedCredit['projectName'] != "TBD") ? $feedCredit['projectName'] : null,
            'amountLocal'            => ($feedCredit['amountLocal'] != "" && $feedCredit['amountLocal'] != "TBD") ? $feedCredit['amountLocal'] : 0,
            'projectBudget'          => ($feedCredit['projectBudget'] != "" && $feedCredit['projectBudget'] != "TBD") ? $feedCredit['projectBudget'] : null,
            'generalNotes'           => ($feedCredit['generalNotes'] != "" && $feedCredit['generalNotes'] != "TBD") ? $feedCredit['generalNotes'] : null,
            'typeOfWork'             => ($feedCredit['incentiveCategory'] != "" && $feedCredit['incentiveCategory'] != "TBD") ? $feedCredit['incentiveCategory'] : null,
            'trackingVariance'       => 1,
            'localCurrency'          => ($feedCredit['localCurrency'] != "" && $feedCredit['localCurrency'] != "TBD") ? $feedCredit['localCurrency'] : null,
            'exchangeRate'           => ($feedCredit['exchangeRate'] != "" && $feedCredit['exchangeRate'] != "TBD") ? $feedCredit['exchangeRate'] : 1,
        ];

        /*
        if($feedCredit['projectId'] == '032217') {
        if($feedCreditFinal['amountLocal'] == 0) {
        */
        if($feedCredit['internalId'] == 454) {
          echo "<pre>";
          //echo $feedCreditFinal['amountLocal'] . "<br>";
          var_dump($feedCreditFinal);
          echo "<br><br>";
        }

        if($this->config->item('isLocal')) {
          $feedCreditFinal['customDataPoints']['custom_82_1538180108'] = ($feedCredit['group'] != "") ? $feedCredit['group'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1538200014'] = ($feedCredit['division'] != "") ? $feedCredit['division'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539126458'] = ($feedCredit['projectId'] != "") ? $feedCredit['projectId'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1542650955'] = ($feedCredit['incentiveCategory'] != "") ? $feedCredit['incentiveCategory'] : null;
          //$feedCreditFinal['customDataPoints']['custom_82_1542650954'] = ($feedCredit['incentiveDesignation']!="") ? $feedCredit['incentiveDesignation'] : NULL;
          $feedCreditFinal['customDataPoints']['custom_82_1539126552'] = ($feedCredit['contactEstimator'] != "") ? $feedCredit['contactEstimator'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539126613'] = ($feedCredit['contactFinance'] != "") ? $feedCredit['contactFinance'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539126673'] = ($feedCredit['contactFeaturesProductionAccountant'] != "") ? $feedCredit['contactFeaturesProductionAccountant'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1541883825'] = ($feedCredit['contactPostProductionAccountant'] != "") ? $feedCredit['contactPostProductionAccountant'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539126780'] = ($feedCredit['contactProductionExecutive'] != "") ? $feedCredit['contactProductionExecutive'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539126823'] = ($feedCredit['contactVFX'] != "") ? $feedCredit['contactVFX'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539126719'] = ($feedCredit['principalPhotographyStartDate'] != "") ? $feedCredit['principalPhotographyStartDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539302456'] = ($feedCredit['preProductionStartDate'] != "") ? $feedCredit['preProductionStartDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539302565'] = ($feedCredit['preProductionEndDate'] != "") ? $feedCredit['preProductionEndDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539205397'] = ($feedCredit['postProductionStartDate'] != "") ? $feedCredit['postProductionStartDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539205517'] = ($feedCredit['postProductionEndDate'] != "") ? $feedCredit['postProductionEndDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539205664'] = ($feedCredit['productionDeliveryDate'] != "") ? $feedCredit['productionDeliveryDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539206331'] = ($feedCredit['productionReleaseDate'] != "") ? $feedCredit['productionReleaseDate'] : null;
          $feedCreditFinal['customDataPoints']['custom_82_1539206384'] = ($feedCredit['productionReleaseDvdDate'] != "") ? $feedCredit['productionReleaseDvdDate'] : null;
        } else {
          if($this->config->item('environment') == "DEV") {
            $feedCreditFinal['customDataPoints']['custom_130_1538239031'] = ($feedCredit['group'] != "") ? $feedCredit['group'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1538239062'] = ($feedCredit['division'] != "") ? $feedCredit['division'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126468'] = ($feedCredit['projectId'] != "") ? $feedCredit['projectId'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1542420314'] = ($feedCredit['incentiveCategory'] != "") ? $feedCredit['incentiveCategory'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1542420313'] = ($feedCredit['incentiveDesignation'] != "") ? $feedCredit['incentiveDesignation'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126547'] = ($feedCredit['contactEstimator'] != "") ? $feedCredit['contactEstimator'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126621'] = ($feedCredit['contactFinance'] != "") ? $feedCredit['contactFinance'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126662'] = ($feedCredit['contactFeaturesProductionAccountant'] != "") ? $feedCredit['contactFeaturesProductionAccountant'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126724'] = ($feedCredit['contactPostProductionAccountant'] != "") ? $feedCredit['contactPostProductionAccountant'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126773'] = ($feedCredit['contactProductionExecutive'] != "") ? $feedCredit['contactProductionExecutive'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539126830'] = ($feedCredit['contactVFX'] != "") ? $feedCredit['contactVFX'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539204628'] = ($feedCredit['principalPhotographyStartDate'] != "") ? $feedCredit['principalPhotographyStartDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539302540'] = ($feedCredit['preProductionStartDate'] != "") ? $feedCredit['preProductionStartDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539302605'] = ($feedCredit['preProductionEndDate'] != "") ? $feedCredit['preProductionEndDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539205403'] = ($feedCredit['postProductionStartDate'] != "") ? $feedCredit['postProductionStartDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539205523'] = ($feedCredit['postProductionEndDate'] != "") ? $feedCredit['postProductionEndDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539205729'] = ($feedCredit['productionDeliveryDate'] != "") ? $feedCredit['productionDeliveryDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539206338'] = ($feedCredit['productionReleaseDate'] != "") ? $feedCredit['productionReleaseDate'] : null;
            $feedCreditFinal['customDataPoints']['custom_130_1539206392'] = ($feedCredit['productionReleaseDvdDate'] != "") ? $feedCredit['productionReleaseDvdDate'] : null;
          } else {
            if($this->config->item('environment') == "LIVE") {
              $feedCreditFinal['customDataPoints']['custom_562_1541868967'] = ($feedCredit['group'] != "") ? $feedCredit['group'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868942'] = ($feedCredit['division'] != "") ? $feedCredit['division'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869235'] = ($feedCredit['projectId'] != "") ? $feedCredit['projectId'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868717'] = ($feedCredit['contactEstimator'] != "") ? $feedCredit['contactEstimator'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868875'] = ($feedCredit['contactFinance'] != "") ? $feedCredit['contactFinance'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868850'] = ($feedCredit['contactFeaturesProductionAccountant'] != "") ? $feedCredit['contactFeaturesProductionAccountant'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869309'] = ($feedCredit['contactPostProductionAccountant'] != "") ? $feedCredit['contactPostProductionAccountant'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868896'] = ($feedCredit['contactProductionExecutive'] != "") ? $feedCredit['contactProductionExecutive'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868916'] = ($feedCredit['contactVFX'] != "") ? $feedCredit['contactVFX'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869114'] = ($feedCredit['principalPhotographyStartDate'] != "") ? $feedCredit['principalPhotographyStartDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541870115'] = ($feedCredit['principalPhotographyEndDate'] != "" && $feedCredit['principalPhotographyEndDate'] != "TBD") ? $feedCredit['principalPhotographyEndDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869081'] = ($feedCredit['preProductionStartDate'] != "") ? $feedCredit['preProductionStartDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869051'] = ($feedCredit['preProductionEndDate'] != "") ? $feedCredit['preProductionEndDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869024'] = ($feedCredit['postProductionStartDate'] != "") ? $feedCredit['postProductionStartDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541868994'] = ($feedCredit['postProductionEndDate'] != "") ? $feedCredit['postProductionEndDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869152'] = ($feedCredit['productionDeliveryDate'] != "") ? $feedCredit['productionDeliveryDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869176'] = ($feedCredit['productionReleaseDate'] != "") ? $feedCredit['productionReleaseDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_562_1541869204'] = ($feedCredit['productionReleaseDvdDate'] != "") ? $feedCredit['productionReleaseDvdDate'] : null;
              $feedCreditFinal['customDataPoints']['custom_519_1547018639'] = ($feedCredit['workingTitle'] != "") ? $feedCredit['workingTitle'] : null;
              $feedCreditFinal['customDataPoints']['custom_519_1547018756'] = ($feedCredit['divisionAbbreviated'] != "") ? $feedCredit['divisionAbbreviated'] : null;
              $feedCreditFinal['customDataPoints']['custom_519_1541869312'] = ($feedCredit['incentiveCategory'] != "") ? $feedCredit['incentiveCategory'] : null;
              $feedCreditFinal['customDataPoints']['custom_519_1541869311'] = ($feedCredit['incentiveDesignation'] != "") ? $feedCredit['incentiveDesignation'] : null;
              $feedCreditFinal['customDataPoints']['custom_519_1541869444'] = ($feedCredit['incentiveType'] != "") ? $feedCredit['incentiveType'] : null;
              $feedCreditFinal['customDataPoints']['custom_519_1547012239'] = ($feedCredit['internalOwner'] != "" && $feedCredit['internalOwner'] != "TBD") ? $feedCredit['internalOwner'] : null;
            } else {
              throw new \Exception('General fail');
            }
          }
        }

        //If no match is found, then insert it
        if($match == 0) {

          /**/
          if($feedCreditFinal['internalId'] > $minInternalId && $feedCreditFinal['internalId'] < $maxInternalId && $apiKey != "" && $excludeScript != "credits") {
            //echo "Credit = ".$feedCreditFinal['internalId']." - $".number_format($feedCreditFinal['amountUSD'])."<br>";
            $creditInsertResult = $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $feedCreditFinal);
            if($creditInsertResult['success'] == 1) {
              echo 'Success (Insert): Credit = ' . $creditInsertResult['credit']['projectData']['projectName'] . ' - ' . $creditInsertResult['credit']['creditAmountData']['amountUSD'] . ' (' . $creditInsertResult['credit']['creditId'] . ')<br>';
              //$feedCreditFinal['creditId'] = $creditInsertResult['credit']['creditId']; //update feed data with credit ID for later usage (update call down below)
            } else {
              $thisError = [];
              $thisError['type'] = 'credit';
              $thisError['foxInternalId'] = $feedCreditFinal['internalId'];
              $thisError['errors'] = $creditInsertResult['errors'];
              array_push($oix['apiErrors'], $thisError);
            }
          }

        } else {

          if($feedCreditFinal['internalId'] > $minInternalId && $feedCreditFinal['internalId'] < $maxInternalId && $apiKey != "" && $excludeScript != "credits") {
            $feedCreditFinal['creditId'] = $feedCredit['creditId'];
            $creditInsertResult = $this->curl_function('/api_beta/v1/credits/' . $feedCredit['creditId'], 'POST', $apiKey, $feedCreditFinal);
            if($creditInsertResult['success'] == 1) {
              echo 'Success (Update): Credit = ' . $creditInsertResult['credit']['projectData']['projectName'] . ' - ' . $creditInsertResult['credit']['creditAmountData']['amountLocal'] . ' (' . $creditInsertResult['credit']['creditId'] . ')<br>';
            } else {
              $thisError = [];
              $thisError['type'] = 'credit';
              $thisError['foxInternalId'] = $feedCreditFinal['internalId'];
              $thisError['errors'] = $creditInsertResult['errors'];
              array_push($oix['apiErrors'], $thisError);
            }
          }

        }

        //UTILIZATIONS
        $creditId = (isset($feedCredit['creditId'])) ? $feedCredit['creditId'] : $creditInsertResult['credit']['creditId'];

        $existingUtilizations = $this->CreditListings->get_UtilizationIds_with_InternalIds_Account($creditId);

        foreach($feedCredit['Transactions'] as $fc) {

          //Loop through each existing utilization to find a match (or not)
          $tMatch = 0;
          foreach($existingUtilizations as $keyTInternalId => $valueUtilizationId) {

            //If matching utilization internal ID key exists in Fox groomed data, then look for any data field updates
            if($fc['utilizationInternalId'] == $keyTInternalId) {

              $fc['utilizationId'] = $valueUtilizationId;

              $tMatch++;
            }

          }

          $feedTransactionFinal = [
              'creditId'                  => $creditId,
              'utilizationInternalId'     => $fc['utilizationInternalId'],
              'utilizationTypeId'         => $fc['utilizationTypeId'],
              'utilizingEntityType'       => $fc['utilizingEntityType'],
              'utilizingEntityCustomName' => $fc['utilizingEntityCustomName'],
              'utilizationAmountLocal'    => $fc['utilizationAmountLocal'], //tradeSize
              'utilizationValuePerCredit' => $fc['utilizationValuePerCredit'], //tradePrice
              'utilizationInterestAmount' => $fc['interestAmountLocal'], //interestAmountLocal
              'exchangeRate'              => $fc['utilizationExchangeRateToUSD'],
              'utilizationDate'           => $fc['utilizationDate'],
              'statusId'                  => $fc['statusId'],
              'notes'                     => $fc['tradeNotes'],
              'utilizationStatus'         => $fc['utilizationStatus'],
              'monetizationStatus'        => $fc['monetizationStatus'],
              'isDeleted'                 => $fc['isDeleted'],
          ];

          /*
          //if($feedCredit['projectId'] == '032187') {
           */
          if($feedCredit['internalId'] == 454) {
            //1.3443
            echo "Utilization:<br>";
            var_dump($fc);
            echo "<br><br>";
          }

          //If no match is found, then insert it
          if($tMatch == 0) {

            if($feedCreditFinal['internalId'] > $minInternalId && $feedCreditFinal['internalId'] < $maxInternalId && $apiKey != "" && $excludeScript != "utilizations") {
              //echo "Utilization Insert = ".$feedTransactionFinal['utilizationInternalId']." - $".number_format($feedTransactionFinal['utilizationAmountLocal'])." - ".$feedTransactionFinal['creditId']."<br>";
              $utilizationInsertResult = $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $feedTransactionFinal);
              if($utilizationInsertResult['success'] == 1) {
                echo '---> Success (Insert): Utilization ID = ' . $utilizationInsertResult['utilization']['creditId'] . '<br>';
              } else {
                //echo 'Fail: Internal ID = '.$feedCreditFinal['internalId'].'<br>';
                $thisError = [];
                $thisError['type'] = 'utilization';
                $thisError['foxInternalId'] = $feedCreditFinal['internalId'];
                $thisError['errors'] = $utilizationInsertResult['errors'];
                array_push($oix['apiErrors'], $thisError);
              }
            }

          } else {

            if($feedCreditFinal['internalId'] > $minInternalId && $feedCreditFinal['internalId'] < $maxInternalId && $apiKey != "" && $excludeScript != "utilizations") {
              $feedTransactionFinal['utilizationId'] = $fc['utilizationId'];
              if($fc['utilizationId'] == 1496) {
                //$feedTransactionFinal['utilizationAmountLocal'] = 333;
              }
              //echo "Utilization Update = ".$feedTransactionFinal['utilizationInternalId']." - ".$fc['utilizationId']." - $".number_format($feedTransactionFinal['utilizationAmountLocal'])." - ".$feedTransactionFinal['creditId']."<br>";
              $utilizationInsertResult = $this->curl_function('/api_beta/v1/utilizations/' . $fc['utilizationId'], 'POST', $apiKey, $feedTransactionFinal);
              if($utilizationInsertResult['success'] == 1) {
                echo '---> Success (Update): Utilization ID = ' . $utilizationInsertResult['utilization']['creditId'] . '<br>';
                //	echo 'Success: Utilization ID = '.$utilizationInsertResult['utilization']['creditId'].'<br>';
              } else {
                //echo 'Fail: Internal ID = '.$feedCreditFinal['internalId'].'<br>';
                $thisError = [];
                $thisError['type'] = 'utilization';
                $thisError['foxInternalId'] = $feedCreditFinal['internalId'];
                $thisError['errors'] = $utilizationInsertResult['errors'];
                array_push($oix['apiErrors'], $thisError);
              }
            }

          }

          $utilizationAmountGrossLocal = $feedTransactionFinal['utilizationAmountLocal'] + $feedTransactionFinal['utilizationInterestAmount'];
          $utilizationAmountGrossLocalActual = ($feedTransactionFinal['utilizationStatus'] == 'actual') ? $utilizationAmountGrossLocal : 0;
          $utilizationAmountGrossUSD = $utilizationAmountGrossLocal * $feedTransactionFinal['exchangeRate'];
          $utilizationAmountGrossUSDActual = ($feedTransactionFinal['utilizationStatus'] == 'actual') ? $utilizationAmountGrossUSD : 0;
          $preview['rows'][$feedCreditFinal['internalId']]['gross_local_currency_utilized'] += $utilizationAmountGrossLocal;
          $preview['rows'][$feedCreditFinal['internalId']]['gross_local_currency_utilized_actual'] += $utilizationAmountGrossLocalActual;
          $preview['rows'][$feedCreditFinal['internalId']]['gross_usd'] += $utilizationAmountGrossUSD;
          $preview['rows'][$feedCreditFinal['internalId']]['net_usd'] += $utilizationAmountGrossUSD * $feedTransactionFinal['utilizationValuePerCredit'];
          $preview['rows'][$feedCreditFinal['internalId']]['received_usd'] += $utilizationAmountGrossUSDActual * $feedTransactionFinal['utilizationValuePerCredit'];

        }

        $grossLocalNotEstimated = $feedCreditFinal['amountLocal'] - $preview['rows'][$feedCreditFinal['internalId']]['gross_local_currency_utilized'];
        $grossLocalNotUtilizedActual = $feedCreditFinal['amountLocal'] - $preview['rows'][$feedCreditFinal['internalId']]['gross_local_currency_utilized_actual'];
        if($grossLocalNotEstimated > 0) {
          $preview['rows'][$feedCreditFinal['internalId']]['gross_usd'] += $grossLocalNotEstimated * $feedCreditFinal['exchangeRate'];
          $preview['rows'][$feedCreditFinal['internalId']]['net_usd'] += $grossLocalNotEstimated * $feedCreditFinal['exchangeRate'];
        }
        if($grossLocalNotUtilizedActual > 0) {
          $preview['rows'][$feedCreditFinal['internalId']]['pending_usd'] = $preview['rows'][$feedCreditFinal['internalId']]['net_usd'] - $preview['rows'][$feedCreditFinal['internalId']]['received_usd'];
        }

        //FOX SPREADSHEET VALIDATION KEY
        //"GROSS LOCAL CURRENCY" = the raw sum of the local incentive credit amounts
        //"GROSS USD" = Sum of Gross USD of all actual AND estimated utilizations --> which is Utilization Amount Gross Local + Utilization Interest Local * utilization spot exchange rate --> Plus the remaining gross local * budgeted exchange rate on credit --> which is the Gross Local on the credit subtracted by the sum of the gross local of all utilizations
        //"NET USD" = Sum of Net USD of all actual AND estimated utilizations --> which is Utilization Amount Gross Local + Utilization Interest Local * utilization spot exchange rate * the discount per credit percentage --> Plus the remaining gross local * budgeted exchange rate on credit --> which is the Gross Local on the credit subtracted by the sum of the gross local of all utilizations
        //"RECEIVED USD" = Sum of the Gross USD of all ACTUAL utilizations --> which is Utilization Amount Gross Local + Utilization Interest Local * utilization spot exchange rate * the discount per credit percentage
        //"PENDING USD" = "NET USD" - "RECEIVED USD"

      }

      //final preview summary
      echo '<style>table {font-family: arial, sans-serif;border-collapse: collapse;width: 100%;font-size:12px;} td, th {border: 1px solid #dddddd;text-align: left;padding: 4px;} tr:nth-child(even) {background-color: #dddddd;}</style>';
      echo '<table> <tr> <th>Project</th> <th>Gross Local Currency</th> <th>Gross USD</th> <th>Net USD</th> <th>Received USD</th> <th>Pending USD</th> </tr>';

      usort($preview['rows'], function($a, $b) {
        return strcmp(strtolower($a["project_info"]), strtolower($b["project_info"]));
      });

      $activeIncentiveCount = 0;
      foreach($preview['rows'] as $pr) {
        if($pr['isArchived'] !== 1) {
          $activeIncentiveCount++;
          $preview['total_gross_local_currency'] += $pr['gross_local_currency'];
          $preview['total_gross_usd'] += $pr['gross_usd'];
          $preview['total_net_usd'] += $pr['net_usd'];
          $preview['total_received_usd'] += $pr['received_usd'];
          $preview['total_pending_usd'] += $pr['pending_usd'];
          //echo results
          echo '<tr> <td>' . $pr['project_info'] . '</td> <td>' . number_format($pr['gross_local_currency']) . '</td> <td>' . number_format($pr['gross_usd']) . '</td> <td>' . number_format($pr['net_usd']) . '</td> <td>' . number_format($pr['received_usd']) . '</td> <td>' . number_format($pr['pending_usd']) . '</td> </tr>';
        }
      }
      echo '<tr style="font-weight:bold;"> <td>TOTALS</td> <td>' . number_format($preview['total_gross_local_currency']) . '</td> <td>' . number_format($preview['total_gross_usd']) . '</td> <td>' . number_format($preview['total_net_usd']) . '</td> <td>' . number_format($preview['total_received_usd']) . '</td> <td>' . number_format($preview['total_pending_usd']) . '</td> </tr></table>';
      echo $activeIncentiveCount . " Active Incentives and " . count($preview['rows']) . " Total Incentives<br><br>";

      if(sizeof($oix['apiErrors']) > 0) {
        $emailData = [];
        $emailData['updateType'] = 'fox_data_integration_error_report';
        $emailData['apiErrorCount'] = sizeof($oix['apiErrors']);
        $emailData['apiErrors'] = $oix['apiErrors'];
        $emailData['emailSubject'] = 'Fox Integration Errors: ' . date('m/d/Y H:i:s');
        $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_dev_emails_array"), $emailData);
      }

      echo "Error Count = " . sizeof($oix['apiErrors']);
      echo "<br><pre>";
      var_dump($oix['apiErrors']);

    }

  }

}
