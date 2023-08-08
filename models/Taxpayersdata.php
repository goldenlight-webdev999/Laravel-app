<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Taxpayersdata extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('Trades');
    $this->load->library(['session']);

  }

  function get_my_taxpayers($dmaId, $customers = "", $getBids = "", $getTrades = "", $getCreditsSelling = "", $getCreditsSold = "", $getCreditsPortfolio = "") {
    $this->db->select('*');
    $this->db->where('Taxpayers.dmaAccountId', $dmaId);
    if($customers == 1) {
      $this->db->where('Taxpayers.tpCustomer', 1);
    }
    $this->db->from('Taxpayers');
    //$this->db->order_by("Taxpayers.tpLastName ASC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data["tpEinSsn"] != "") {
        $data["tpEinSsn"] = "*****" . substr($data["tpEinSsn"], -4);
      }
      if($data["tpStateTaxId"] != "") {
        $data["tpStateTaxId"] = "*****" . substr($data["tpStateTaxId"], -4);
      }

      $data['taxpayerName'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpLastName'] . ", " . $data['tpFirstName'];

      $data['tpFiscalYearEnd'] = ($data['tpFiscalYearEndMonth'] != "" && $data['tpFiscalYearEndDay'] != "") ? $data['tpFiscalYearEndMonth'] . " " . $data['tpFiscalYearEndDay'] : "";

      $data['tpRepresentativeName'] = ($data['tpLastName'] != "" && $data['tpFirstName'] != "") ? $data['tpFirstName'] . " " . $data['tpLastName'] : "";

      if($getBids == 1) {
        //$data['bidTransactions'] = $this->Trading->get_bid_transactions_of_taxpayer($data['taxpayerId'], '', 'no');
        $data['bidTransactions'] = "";
      } else {
        $data['bidTransactions'] = "";
      }

      if($getTrades == 1) {
        $data['tradeTransactions'] = $this->Trading->get_trade_transactions_of_taxpayer($data['taxpayerId'], 'all', 'no');
      } else {
        $data['tradeTransactions'] = "";
      }

      if($getCreditsSelling == 1) {
        //$data['tpSelling'] = $this->CreditListings->get_credits_of_taxpayer($data['taxpayerId'], 3, "");
        $data['tpSelling'] = "";
      } else {
        $data['tpSelling'] = "";
      }

      if($getCreditsSold == 1) {
        //$data['tpSold'] = $this->CreditListings->get_taxpayer_sales_sold($data['taxpayerId']);
        $data['tpSold'] = "";
      } else {
        $data['tpSold'] = "";
      }

      if($getCreditsPortfolio == 1) {
        $data['tpPortfolio'] = $this->CreditListings->get_credits_of_taxpayer($data['taxpayerId'], "", "");
      } else {
        $data['tpPortfolio'] = "";
      }

      array_push($return, $data);
    }

    //Re-Sort the array by the legal entity name (company or taxpayer name)
    usort($return, function($a, $b) {
      //return $b['taxpayerName'] - $a['taxpayerName']; //Numerical
      return strcmp($a["taxpayerName"], $b["taxpayerName"]); //Alphabetical
    });

    return $return;

  }

  function insert_taxpayer() {
    $tpFiscalYearEndMonth = null;
    $tpFiscalYearEndDay = null;
    $fiscalYearEndParts = $this->input->post('fiscalYearEnd');
    $fiscalYearEndParts = explode(' ', $fiscalYearEndParts);
    if(count($fiscalYearEndParts) == 2) {
      $tpFiscalYearEndMonth = $fiscalYearEndParts[0];
      $tpFiscalYearEndDay = $fiscalYearEndParts[1];
    }

    $data = [
        'dmaAccountId'         => $this->cisession->userdata('dmaId'),
        'tpAccountType'        => '1', //legacy value for company, hard coded here
        'addedBy'              => $this->cisession->userdata('userId'),
        'tpTimestamp'          => time(),
        'tpCompanyName'        => $this->input->post('companyName'),
        'tpFirstName'          => $this->input->post('repFirstName'),
        'tpLastName'           => $this->input->post('repLastName'),
        'tpTitle'              => $this->input->post('repTitle'),
        'tpEmail'              => $this->input->post('repEmail'),
        'tpAddressFormatted'   => $this->input->post('companyAddressFormattedAddress'),
        'tpAddressPlaceId'     => $this->input->post('companyAddressPlaceId'),
        'tpFiscalYearEndMonth' => $tpFiscalYearEndMonth,
        'tpFiscalYearEndDay'   => $tpFiscalYearEndDay,
    ];

    $this->db->insert('Taxpayers', $data);

    return $this->db->insert_id();

  }

  function check_if_tax_entity_exists($request) {

    $existingTaxEntityId = 0;

    $this->db->select("taxpayerId");
    $this->db->from("Taxpayers");
    $this->db->where_in("dmaAccountId", $request['dmaAccountIds']);
    $this->db->where("tpCompanyName", $request['tpCompanyName']);

    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $existingTaxEntityId = $data["taxpayerId"];
    }

    return $existingTaxEntityId;

  }

  function insert_taxpayer_api($request) {

    $timestampDOB = (isset($request['tpDOB']) && $request['tpDOB'] != "") ? strtotime($request['tpDOB']) : "";

    if(!isset($request['signerSelector']) || $request['signerSelector'] == "self") {
      $tpEmailSigner = null;
      $tpUserIdSigner = null;
    } else {
      if($request['signerSelector'] == "adminuser") {
        $tpEmailSigner = null;
        $tpUserIdSigner = $this->input->post('signerSelectorAdminUser');
      }
    }
    if($request['signerSelector'] == "email") {
      $tpEmailSigner = $this->input->post('tpEmailSigner');
      $tpUserIdSigner = null;
    }

    //Validate the data TO DO

    //
    $data = $request;

    $this->db->insert('Taxpayers', $data);

    return $this->db->insert_id();

  }

  function insert_taxpayer_lite($request) {

    $dmaAccountId = $request['dmaAccountId'];
    $tpAccountType = $request['tpAccountType'];
    $tpCompanyName = $request['tpCompanyName'];
    $tpFirstName = $request['tpFirstName'];
    $tpLastName = $request['tpLastName'];

    $data = [
        'dmaAccountId'  => $dmaAccountId,
        'tpAccountType' => $tpAccountType,
        'tpCompanyName' => $tpCompanyName,
        'tpFirstName'   => $tpFirstName,
        'tpLastName'    => $tpLastName,
        'tpTimestamp'   => time(),
    ];

    $this->db->insert('Taxpayers', $data);

    return $this->db->insert_id();

  }

  function add_pending_transaction($bidId, $buyerAccountId, $tCreditAmount) {
    $data = [
        'tBidId'         => $bidId,
        'buyerAccountId' => $buyerAccountId,
        'tStage'         => null,
        'tCreditAmount'  => $tCreditAmount,
        'tDmaId'         => $this->cisession->userdata('dmaId'),
    ];

    $this->db->insert('Transactions', $data);

    return $this->db->insert_id();
  }

  function get_taxpayer($taxpayerId, $getBids = "", $getTrades = "") {
    $this->db->select('Taxpayers.*, Accounts.firstName as tpUserSignerFirstName, Accounts.lastName as tpUserSignerLastName');
    $this->db->from('Taxpayers');
    $this->db->where('Taxpayers.taxpayerId', $taxpayerId);
    $this->db->join("Accounts", "Taxpayers.tpUserIdSigner = Accounts.userId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['taxpayerName'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpLastName'] . ", " . $data['tpFirstName'];

      //If loading page from Admin Panel...
      if($this->cisession->userdata('isAdminPanel') == false) {
        if($data["tpEinSsn"] != "") {
          $data["tpEinSsn"] = "*****" . substr($data["tpEinSsn"], -4);
        }
        if($data["tpStateTaxId"] != "") {
          $data["tpStateTaxId"] = "*****" . substr($data["tpStateTaxId"], -4);
        }
      }

      if($getBids == 1) {
        $data['bidTransactions'] = $this->Trading->get_bid_transactions_of_taxpayer($data['taxpayerId'], '', 'no');
      } else {
        $data['bidTransactions'] = "";
      }

      if($getTrades == 1) {
        $data['tradeTransactions'] = $this->Trading->get_trade_transactions_of_taxpayer($data['taxpayerId'], 'all', 'no');
      } else {
        $data['tradeTransactions'] = "";
      }

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function checkTaxypayerExists($dmaId, $tpCompanyName, $tpFirstName, $tpLastName) {

    $this->db->select('Taxpayers.taxpayerId');
    $this->db->from('Taxpayers');
    $this->db->where('Taxpayers.tpCompanyName', $tpCompanyName);
    $this->db->where('Taxpayers.dmaAccountId', $dmaId);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0]['taxpayerId'];
    } else {
      return 0;
    }

  }

  function save_taxpayer($taxpayerId) {
    $tpFiscalYearEndMonth = null;
    $tpFiscalYearEndDay = null;
    $fiscalYearEndParts = $this->input->post('fiscalYearEnd');
    $fiscalYearEndParts = explode(' ', $fiscalYearEndParts);
    if(count($fiscalYearEndParts) == 2) {
      $tpFiscalYearEndMonth = $fiscalYearEndParts[0];
      $tpFiscalYearEndDay = $fiscalYearEndParts[1];
    }

    $data = [
        'dmaAccountId'         => $this->cisession->userdata('dmaId'),
        'tpAccountType'        => '1', //legacy value for company, hard coded here
        'addedBy'              => $this->cisession->userdata('userId'),
        'tpTimestamp'          => time(),
        'tpCompanyName'        => $this->input->post('companyName'),
        'tpFirstName'          => $this->input->post('repFirstName'),
        'tpLastName'           => $this->input->post('repLastName'),
        'tpTitle'              => $this->input->post('repTitle'),
        'tpEmail'              => $this->input->post('repEmail'),
        'tpAddressFormatted'   => $this->input->post('companyAddressFormattedAddress'),
        'tpAddressPlaceId'     => $this->input->post('companyAddressPlaceId'),
        'tpFiscalYearEndMonth' => $tpFiscalYearEndMonth,
        'tpFiscalYearEndDay'   => $tpFiscalYearEndDay,
    ];

    $this->db->where('taxpayerId', $taxpayerId);
    $this->db->update('Taxpayers', $data);
    //return $this->db->insert_id();

  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
