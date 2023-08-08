<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Signer extends CI_Controller {
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

  function index() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "signerHome";

      $data['myTrades'] = $this->Trades->get_trades_by_signer($this->cisession->userdata('userId'), 1);
      $data['myTransactions'] = $this->Trades->get_trade_transactions_by_signer($this->cisession->userdata('userId'));

      $data['dmaRep'] = $this->Members_Model->get_dma_accounts_of_signer_by_id($this->cisession->userdata('userId'));
      if(sizeof($data['dmaRep']) > 0) {
        $data['dmaRep'] = $data['dmaRep'][0];
      } else {
        $data['dmaRep'] = "";
      }

      $actionRequired = 0;
      foreach($data['myTrades'] as $mt1) {
        if($mt1['sellerSigned'] != 1) {
          $actionRequired++;
        }
      }
      foreach($data['myTransactions'] as $mt2) {
        if($mt2['buyerSigned'] != 1 || $mt2['buyerPaid'] != 1) {
          $actionRequired++;
        }
      }
      $data['actionRequired'] = $actionRequired;

      $this->load->view('includes/left_nav_signer', $data);
      $this->load->view("signer/index", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function activity() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "signerActivity";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "signerActivity";

      $data['myTrades'] = $this->Trades->get_trades_by_signer($this->cisession->userdata('userId'), 1);
      $data['myTransactions'] = $this->Trades->get_trade_transactions_by_signer($this->cisession->userdata('userId'));

      //$data['activity'] = array_merge($data['myTrades']);
      //$data['activity'] = $data['myTrades'] + $data['myTransactions'];

      $this->load->view('includes/left_nav_signer', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("signer/signer_activity", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function account_settings() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "signerSettings";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "signerSettings";

      $data['page'] = 'signer';

      $data['details'] = $this->Members_Model->get_current_member($this->cisession->userdata('userId'));
      $data['state'] = $this->IncentivePrograms->get_us_states();

      $data['account_types'] = [
          "0" => "",
          "1" => "Seller",
          "2" => "Buyer",
          "3" => "Buyer/Seller",
      ];

      $this->load->view('includes/left_nav_signer', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("myaccount/account_settings", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function password_change_success() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "support";

      $data['activity'] = "";
      //$data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);

      $this->load->view('includes/left_nav_signer', $data);
      $this->load->view('myaccount/password_change_success', $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function support() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "support";

      $data['activity'] = "";
      //$data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);

      $this->load->view('includes/left_nav_signer', $data);
      $this->load->view("help/support", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

}
