<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Documents extends CI_Controller {
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
    $this->load->model('Taxpayersdata');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Email_Model');
    $this->load->library('filemanagementlib');

  }

  function closing_documents($id = '', $saleId = '', $salePerspective = '', $transactionId = '') {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['itemType'] = "credit";
      $data['itemNameCap'] = "Credit";
      $data['itemNameLow'] = "credit";

      $data['salePerspective'] = $salePerspective;

      $data['details'] = $this->CreditListings->get_credit_private($id);
      $data['program'] = $data['details'];

      //First check if this user has access to this credit (there is a second check further below)
      ////// note - there is another redirect further down!!!!!!!!!!!!!!!!!!
      $access = false;
      if($salePerspective == "sale") {
        $data['shareView'] = false;
        $data['current_tab_key'] = "sales";
        foreach($data['program']['shares'] as $s) {
          if($s['sharedPrimId'] == $this->cisession->userdata('primUserId')) {
            $access = true;
            $data['lnav_key'] = "shared";
            $data['shareView'] = true;
          }
        }
        if($data['program']['listedBy'] == $this->cisession->userdata('primUserId')) {
          $access = true;
          $data['lnav_key'] = "managing";
          $data['shareView'] = false;
        }
      } else {
        $data['lnav_key'] = "buying";
      }

      $data['active'] = $this->CreditListings->get_active_listing($id);
      if($data['active'] == null || $data['active'] == "") {
        $data['creditForSale'] = false;
      } else {
        $data['creditForSale'] = true;
      }

      $trades = $this->Trading->get_trades_on_listing($id);
      $data['trades'] = $trades['trades'];
      $data['sale'] = $this->Trading->get_trade($saleId);
      $data['sale'] = $data['sale']['trade'];

      $data['transactions'] = $this->Trading->get_transactions_of_trade($saleId);

      if($data['transactions'][0]['taxpayerId'] > 0) {
        $data['multibuyer'] = true;
      } else {
        $data['multibuyer'] = false;
      };

      $currTransactionArrayNum = 0;

      if($data['multibuyer'] == true) {
        $data['buyerSignsCount'] = 0;
        $data['buyerPaysCount'] = 0;
        $data['pendingTransactionsNumber'] = 0;
        $i = 0;
        foreach($data['transactions'] as $transaction) {
          if($transaction['buyerSigned'] != 1) {
            $data['buyerSignsCount']++;
          }
          if($transaction['buyerPaid'] != 1) {
            $data['buyerPaysCount']++;
          }
          if($transaction['tStage'] != 4 && $transaction['finalPsaUploaded'] != 1) {
            $data['pendingTransactionsNumber']++;
          }
          if($transaction['transactionId'] == $transactionId) {
            $currTransactionArrayNum = $i;
          }
          $i++;
        }
        $data['buyerActionsCount'] = $data['buyerSignsCount'] + $data['buyerPaysCount'];
      }

      if($transactionId == "") {
        $data['subPage'] = false;
        if($data['transactions'][0]['taxpayerId'] > 0 && sizeof($data['transactions']) > 1) {
          $data['transactionId'] = "";
          $data['buyerPayMethod'] = "";
        } else {
          $data['transactionId'] = $data['transactions'][0]['transactionId'];
          $data['currTransaction'] = $data['transactions'][0];
          $data['buyerPayMethod'] = $data['transactions'][0]['buyerPayMethod'];
          if($data['buyerPayMethod'] == 1) {
            $data['buyerPayMethod'] = 'check';
          }
          if($data['buyerPayMethod'] == 2) {
            $data['buyerPayMethod'] = 'wire';
          }
        }
      } else {
        $data['subPage'] = true;
        $data['transactionId'] = $transactionId;
        $data['currTransaction'] = $data['transactions'][$currTransactionArrayNum];
        $data['buyerPayMethod'] = "";
      }

      //If this user doesn't have access to this sale/purchase
      if($salePerspective == "sale") {
        if($data['program']['listedBy'] == $this->cisession->userdata('primUserId')) {
          //If the main seller of this credit
          $access = true;
        } else {
          if(isset($data['sale']['taxpayerUserId'])) {
            if($data['sale']['taxpayerUserId'] == $this->cisession->userdata('primUserId')) {
              //If the legal entity for this credit
              $access = true;
              $data['lnav_key'] = "signerActivity";
            }
          }
        }
      } else {
        if($data['sale']['accountId'] == $this->cisession->userdata('primUserId')) {
          //If the main buyer in this trade
          $access = true;
        } else {
          if($data['currTransaction']['taxpayerUserId'] == $this->cisession->userdata('primUserId')) {
            //If the legal entity for this transaction
            $access = true;
          }
        }
      }

      if(!$access) {
        redirect('/dashboard');
      }

      if($this->cisession->userdata('level') != 8) {
        $this->load->view('includes/left_nav', $data);
      } else {
        $this->load->view('includes/left_nav_signer', $data);
      }
      if($salePerspective == "sale" && $this->cisession->userdata('level') != 8) {
        $this->load->view('includes/credit_header', $data);
      }
      $this->load->view('documents/closing_documents', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

}
