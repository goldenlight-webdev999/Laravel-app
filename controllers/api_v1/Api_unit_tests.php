<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Api_unit_tests extends CI_Controller {
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
    $this->load->model('AuditTrail');
    $this->load->model('Docs');

    $this->load->library('memberpermissions');
    $this->load->library('filemanagementlib');

  }

  function curl_function($url, $method, $apiKey, $feedCredit) {

    $data_string = json_encode($feedCredit);

    $ch = curl_init(base_url() . $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if($method == "POST") {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                       'Content-Type: application/json',
                       'Authorization: ' . $apiKey,
                       'Content-Length: ' . strlen($data_string)]
    );
    //curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    //execute post
    $response = curl_exec($ch);
    //close connection
    curl_close($ch);
    $result = json_decode($response, true);

    /*
    var_dump($result);
    if($result['success']==1) {
      if(isset($result['credit'])) {
        echo 'Success: '.$result['credit']['creditId'].'<br>';
      } else {
        echo 'Success: '.$result['credit']['creditId'].'<br>';
      }
    } else {
      if(isset($result['credit'])) {
        echo 'Fail: '.$result['credit']['creditId'].'<br>';
        var_dump($result['errors']);
      } else {
        echo 'Fail: '.$result['utilization']['utilizationId'].'<br>';
        var_dump($result['errors']);
      }
    }
    */
    if($method == "POST") {
      echo $response;
    } else {
      echo '<pre id="json"></pre>';
      echo '<script>var data = ' . $response . ';';
      echo 'document.getElementById("json").innerHTML = JSON.stringify(data, undefined, 2);</script>';
    }

  }

  function unit_tests() {

    echo '<p>GET Credit Tests:</p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_credit_succeed" target="_blank">Succed - Credit ID</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_credit_fail_noCreditId" target="_blank">Fail - No Credit ID</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_credit_fail_noCreditAccess" target="_blank">Fail - No Credit Access</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_credit_fail_CreditDoesNotExist" target="_blank">Fail - Credit Does Not Exist</a><p>';

    echo '<br>';
    echo '<p>INSERT Credit Tests:</p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_fail_empty" target="_blank">Fail - Empty Request</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_fail_validation" target="_blank">Fail - Validation  Error - Numeric</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_succeed_minimum" target="_blank">Success - Minimal Required Fields - Existing Data (Program ID & Legal Entity ID)</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_fail_validation_custom_program_data" target="_blank">Fail - Custom Program - Missing Data (State and Country)</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_succeed_minimum_custom_program" target="_blank">Success - Custom Program</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_succeed_minimum_custom_tax_entity" target="_blank">Success - Custom Legal Entity</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_credit_succeed_all_data" target="_blank">Success - All Data</a><p>';

    echo '<br>';
    echo '<p>GET Utilization Tests:</p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_utilization_succeed" target="_blank">Succed - Utilization ID</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_utilization_fail_noId" target="_blank">Fail - No Utilization ID</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_utilization_fail_noAccess" target="_blank">Fail - No Utilization Access</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_get_utilization_fail_doesNotExist" target="_blank">Fail - Utilization Does Not Exist</a><p>';

    echo '<br>';
    echo '<p>INSERT Utilization Tests:</p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_fail_empty" target="_blank">Fail - Empty Request</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_fail_validation" target="_blank">Fail - Validation  Error - Numeric</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_fail_validation_too_much_credit" target="_blank">Fail - Validation  Error - Too Much Credit</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_succeed_estimate_minimum" target="_blank">Success - Minimal Required Fields - ESTIMATE</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_succeed_estimate_full" target="_blank">Success - Full Required Fields - ESTIMATE</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_succeed_actual_minimum" target="_blank">Success - Minimal Required Fields - ACTUAL</a><p>';
    echo '<p><a href="/api_beta/v1/unit_tests/test_insert_utilization_succeed_actual_full" target="_blank">Success - Full Required Fields - ACTUAL</a><p>';

  }

  function test_get_credit_succeed() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = 1654;
    $this->curl_function('/api_beta/v1/credits/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_get_credit_fail_noCreditId() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = null;
    $this->curl_function('/api_beta/v1/credits/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_get_credit_fail_noCreditAccess() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = 1588;
    $this->curl_function('/api_beta/v1/credits/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_get_credit_fail_CreditDoesNotExist() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = 9999999;
    $this->curl_function('/api_beta/v1/credits/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_insert_credit_fail_empty() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_credit_fail_validation() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['oixIncentiveProgramId' => 'safd98s9f', 'taxEntityId' => 1];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_credit_succeed_minimum() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['oixIncentiveProgramId' => 11, 'taxEntityId' => 1];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_credit_fail_validation_custom_program_data() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['taxEntityId' => 1, 'customProgram' => ['incentiveProgramName' => 'Fake Film Production Credit']];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_credit_succeed_minimum_custom_program() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['taxEntityId' => 1, 'customProgram' => ['incentiveProgramName' => 'Fake Film Production Credit', 'jurisdictionCode' => 'AK', 'regionId' => 1]];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_credit_succeed_minimum_custom_tax_entity() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['oixIncentiveProgramId' => 11, 'taxEntityCompanyName' => '3 Big Cheese Inc.'];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_credit_succeed_all_data() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [
        'oixIncentiveProgramId'           => 56842,
        'taxEntityId'                     => 1,
        'vintageTaxYear'                  => 2018,
        'projectName'                     => 'Radio Flyer',
        'projectNameExt'                  => 'Eps 2',
        'amountUSD'                       => 1000000,
        'estimatedValueAsPercent'         => 0.925,
        'projectBudget'                   => 3000000,
        'qualifiedExpenditures'           => 1500000,
        'certificationNumber'             => 'RF 222',
        'localCurrency'                   => 'CAD',
        'spotExchangeRate'                => 0.9,
        'typeOfWork'                      => 'Post-Production',
        'internalId'                      => 'Int-900a',
        'finalCreditAmountFlag'           => 1,
        'trackingVariance'                => 1,
        'generalNotes'                    => 'This is a general note here',
        'statusNotes'                     => 'And here are my status notes',
        'statusIdCertification'           => 1,
        'statusIdProject'                 => 1,
        'statusIdAudit'                   => 1,
        'statusIdMonetization'            => 1,
        'dateCertificationInitial'        => '2018-01-26',
        'dateCertificationFinal'          => '2018-06-26',
        'dateProjectStart'                => '2018-01-26',
        'dateProjectEnd'                  => '2018-06-26',
        'dateAuditStart'                  => '2018-01-26',
        'dateAuditEnd'                    => '2018-06-26',
        'dateCreditIssue'                 => '2018-08-26',
        'dateLastDayPrincipalPhotography' => '2018-06-26',
    ];
    $this->curl_function('/api_beta/v1/credits', 'POST', $apiKey, $apiRequest);

  }

  function test_get_utilization_succeed() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = 87;
    $this->curl_function('/api_beta/v1/utilizations/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_get_utilization_fail_noId() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = null;
    $this->curl_function('/api_beta/v1/utilizations/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_get_utilization_fail_noAccess() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = 60;
    $this->curl_function('/api_beta/v1/utilizations/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_get_utilization_fail_doesNotExist() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $id = 9999999;
    $this->curl_function('/api_beta/v1/utilizations/' . $id, 'GET', $apiKey, $apiRequest);

  }

  function test_insert_utilization_fail_empty() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [];
    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_utilization_fail_validation() {
    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['creditId' => 'cow', 'utilizationStatus' => 'estimate', 'utilizationDate' => '2018-10-20'];
    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);
  }

  function test_insert_utilization_fail_validation_too_much_credit() {
    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['creditId' => 4552, 'utilizationStatus' => 'estimate', 'utilizationDate' => '2018-10-20', 'utilizationAmountUSD' => 1000000000];
    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);
  }

  function test_insert_utilization_succeed_estimate_minimum() {
    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['creditId' => 4552, 'utilizationStatus' => 'estimate', 'utilizationDate' => '2018-10-20'];
    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);
  }

  function test_insert_utilization_succeed_estimate_full() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [
        'creditId'                  => 4552,
        'utilizationInternalId'     => 199,
        'exchangeRate'              => '0.9',
        'utilizationType'           => 'refund',
        'utilizationAmountUSD'      => 1000,
        'utilizationValuePerCredit' => '0.925',
        'notes'                     => 'These are fancy trade notes',
        'utilizingEntityType'       => 'customname',
        'utilizingEntityCustomName' => 'Big Tax Liability, Inc.',
        'utilizationStatus'         => 'estimate',
        //'statusId'=>1, //either this or statusCode
        'statusCode'                => 'not_started', //either this or statusId
        'utilizationDate'           => '2018-08-15',
    ];

    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);

  }

  function test_insert_utilization_succeed_actual_minimum() {
    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = ['creditId' => 4552, 'utilizationStatus' => 'actual', 'utilizationDate' => '2018-10-30'];
    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);
  }

  function test_insert_utilization_succeed_actual_full() {

    $apiKey = "fsd7yfs98ysa9sfu98sudfosfhkhih9989sdf"; //Twentieth Century Fox Television
    $apiRequest = [
        'creditId'                  => 4552,
        'utilizationInternalId'     => 199,
        'exchangeRate'              => '0.9',
        'utilizationType'           => 'refund',
        'utilizationAmountUSD'      => 1000,
        'utilizationValuePerCredit' => '0.925',
        'notes'                     => 'These are fancy trade notes',
        'utilizingEntityType'       => 'customname',
        'utilizingEntityCustomName' => 'Big Tax Liability, Inc.',
        'utilizationStatus'         => 'actual',
        //'statusId'=>1, //either this or statusCode
        'statusCode'                => 'completed', //either this or statusId
        'utilizationDate'           => '2018-08-01',
    ];

    $this->curl_function('/api_beta/v1/utilizations', 'POST', $apiKey, $apiRequest);

  }

}
