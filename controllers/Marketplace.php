<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Marketplace extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }

    $this->load->library('tank_auth');
    $this->load->library('session');
    $this->lang->load('tank_auth');
    $this->load->helper('date');
    $this->load->model('IncentivePrograms');
    $this->load->model('CreditListings');
    $this->load->model('api_v1/CoreData');
    $this->load->model('Trading');
    $this->load->model('Trades');
    $this->load->model('Taxpayersdata');
    $this->load->model('Members_Model');
    $this->load->model('BidMarket');
    $this->load->model('Email_Model');
    $this->load->model('AuditTrail');
    $this->load->model('Workflow');
    $this->load->model('DmaAccounts');

    /*
		$config['upload_path'] = '/files/';
		$config['allowed_types'] = 'pdf|doc|docx|zip';
		$config['max_size']    = '0';
		$config['encrypt_name'] = TRUE;
		$this->load->library('upload', $config);
		$this->load->library('Multi_upload');
		*/

    $this->load->model('admins');
    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');
    $this->load->library(['currency']);

    $param = [[
                  'app_key'         => "xk13ljz02drf7ow",
                  'app_secret'      => "utnvdkmxkstzue4",
                  'app_full_access' => false,
              ], 'en'];

  }

  function index($credits = '', $state = '', $taxyear = '', $sector = '', $offset = '') {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if($this->cisession->userdata('level') == 8) {
        redirect('/overview/marketplace');
      }

      if($this->config->item("environment") != 'DEV') {
        redirect('/dashboard');
      }

      $data['lnav_key'] = "marketplace";
      /*$data['current_tab_key'] = "search";
			$data['tabArray'] = "marketplace";*/

      //Get data for drop downs
      //$data['countries'] = $this->IncentivePrograms->list_countries();
      $data['state'] = $this->IncentivePrograms->get_all_states();
      $data['offsets'] = $this->IncentivePrograms->get_offsets_by_active_program();
      $data['sectors'] = $this->IncentivePrograms->get_sectors_used();
      //$data['sectors'] = $this->IncentivePrograms->get_sectors_by_active_program();
      $data['taxyears'] = $this->IncentivePrograms->get_taxyear_by_active_program();

      //Groom empty Get parameters
      if($credits == "all") {
        $credits = "";
      }
      if($state == "all") {
        $state = "";
      }
      if($taxyear == "all") {
        $taxyear = "";
      }
      if($sector == "all") {
        $sector = "";
      }
      if($offset == "all") {
        $offset = "";
      }

      //If this is a search
      if(!empty($credits) || !empty($state) || !empty($taxyear) || !empty($sector) || !empty($offset)) {

        $credits = preg_replace("/[^a-zA-Z0-9]+/", "", $credits);
        $state = preg_replace("/[^a-zA-Z0-9]+/", "", $state);
        $taxyear = preg_replace("/[^a-zA-Z0-9]+/", "", $taxyear);
        $sector = preg_replace("/[^a-zA-Z0-9]+/", "", $sector);
        $offset = preg_replace("/[^a-zA-Z0-9]+/", "", $offset);

        /*$data['countryData'] = $this->IncentivePrograms->getCountryById($this->input->post('country'));
		        if($this->input->post('country')){
		        	$data['state'] = $this->IncentivePrograms->get_only_states_by_country($this->input->post('country'));
		        }else{
		            $data['state'] = $this->IncentivePrograms->get_all_states();
		        }*/

        $data['markets'] = $this->CreditListings->get_sorted_local_market_ipdp($state, $sector, $offset, $taxyear, 'AA');
        $data['incentiveProgram'] = $this->getIncentiveProgram($data['markets']);
        //TBD - shouldn't sector be included in Bids since open bids can have a sector?
        $data['bmarkets'] = $this->BidMarket->get_open_bids_listed_filtered(1, $state, $offset, $taxyear);

      } else {

        $data['markets'] = $this->CreditListings->get_current_market('AA');
        $data['incentiveProgram'] = $this->getIncentiveProgram($data['markets']);
        $data['bmarkets'] = $this->BidMarket->get_open_bids_listed_all();

      }

      /*Sort the data with date wise and merge the Active listings + Open Bids + Open Offers*/
      $imarkets = 0;
      foreach($data['markets'] as $key => $value) {

        $data['markets'][$imarkets]['item_name'] = "markets";
        $data['markets'][$imarkets]['IssueDate2'] = strtotime($data['markets'][$imarkets]['updatedTime']);
        $imarkets++;
      }
      $ibmarkets = 0;
      foreach($data['bmarkets'] as $key => $value) {

        $data['bmarkets'][$ibmarkets]['item_name'] = "bidMarket";
        $data['bmarkets'][$ibmarkets]['IssueDate2'] = strtotime($data['bmarkets'][$ibmarkets]['timeStamp']);
        $ibmarkets++;
      }

      $data['merge_markets'] = [];
      if(!empty($credits)) {
        if($credits == 'credits') {
          $data['merge_markets'] = array_merge($data['markets']);
        }
        if($credits == 'bids') {
          $data['merge_markets'] = array_merge($data['bmarkets']);
        }
      } else {
        $data['merge_markets'] = array_merge($data['markets'], $data['bmarkets']);
      }

      // Sort by date descending
      $merge_markets_by_date = [];
      foreach($data['merge_markets'] as $key => $row) {
        $merge_markets_by_date[$key] = $row['IssueDate2'];
      }
      array_multisort($merge_markets_by_date, SORT_DESC, $data['merge_markets']);

      /*END Sort the data with date wise and merge the Active listings + Open Bids + Open Offers*/

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('includes/tab_nav', $data);
      $this->load->view("marketplace/index", $data);
      $this->load->view('includes/footer-2');

    }
  }

  function status() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if($this->cisession->userdata('level') == 8) {
        redirect('/overview/marketplace');
      }

      $data['lnav_key'] = "marketplace";

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('includes/tab_nav', $data);
      $this->load->view("marketplace/coming_soon", $data);
      $this->load->view('includes/footer-2');

    }
  }

  function view($id) {

    //redirect('/marketplace/status');

    //Variables for document uploader
    $data['listingId'] = $id;
    $data['tradeId'] = "";
    $data['transactionId'] = "";
    $data['docaccess'] = "public";
    $data['filterTag'] = "none";

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if($this->config->item("environment") != 'DEV') {
        redirect('/dashboard');
      }

      //CHECK PERMISSION
      $permissions = $this->memberpermissions->checkCreditPublic($id);
      $data = array_merge($data, $permissions);

      $data['lnav_key'] = "marketplace";
      $data['itemType'] = "credit";
      $data['itemNameCap'] = "Credit";
      $data['itemNameLow'] = "credit";

      $data['program'] = $this->CreditListings->get_current_program_listing($id);

      if($data['program']['listedBy'] == $this->cisession->userdata('primUserId')) {
        $data['isOwner'] = true;
      } else {
        $data['isOwner'] = false;
      }

      $data['market'] = $this->CreditListings->get_market_on_listing_new($id);
      $data['hasBid'] = $this->CreditListings->check_bid($id);
      $data['tradeStatusPending'] = $this->Trading->checkStatus($id);
      $data['listingStatus'] = $this->Trading->checkListingStatus($id);
      $data['trades'] = $this->Trading->getTradesInfo($id);
      $data['userinfo'] = $this->Members_Model->get_current_member($this->cisession->userdata('userId'));
      $data['listId'] = $data['program']['state'] . $data['program']['listingId'];
      $data['buyOrders'] = $this->BidMarket->get_buy_orders_by_credit($id);

      $data['diligence_docs'] = $this->Docs->get_documents("", $id, "", "", "", 2);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/listing_header_main', $data);
      $this->load->view('admin/fileManagement/docs_php_to_js', $data);
      $this->load->view("marketplace/view", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function viewbid($id) {

    //redirect('/marketplace/status');

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if($this->config->item("environment") != 'DEV') {
        redirect('/dashboard');
      }

      $data['lnav_key'] = "marketplace";
      $data['itemType'] = "openbid";
      $data['itemNameCap'] = "Bid";
      $data['itemNameLow'] = "bid";

      $data['program'] = $this->CreditListings->get_current_program_binding_listing($id);
      if($data['program']['accountId'] == $this->cisession->userdata('primUserId')) {
        $data['isOwner'] = true;
      } else {
        $data['isOwner'] = false;
      }

      $data['market'] = $this->CreditListings->get_market_on_listing_new($id);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/listing_header_main', $data);
      $this->load->view('marketplace/viewbid', $data);
      $this->load->view('includes/footer-2');

    }

  }

  function buycredit($id = "", $page = "", $bidId = "", $tradeId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelBuyCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        //Key Variables
        $data['page'] = ""; //defines the page we're on: buynow, edit, editopenbid, oix_marketplace_trade, internal_transfer, external_transfer
        $data['workflow'] = ""; //defines if this is bidding on a credit or an open bid
        $data['editMode'] = ($bidId > 0 || $tradeId > 0) ? true : false; //if there's a bid or trade ID, then we're editing it
        $data['bidId'] = ($bidId > 0) ? $bidId : 0;
        $data['tradeId'] = ($tradeId > 0) ? $tradeId : 0;
        $data['convertToActual'] = false;

        if($page == "utilization_convert_actual") {
          $data['convertToActual'] = true;
          $page = "utilization_edit"; //reset page value
        }
        //If buying it now (bidding at full price)
        if($page == "buynow") {
          //Set the page variable based on this
          $data['page'] = "buynow";
        }
        //If editing a bid
        if($page == "edit") {
          //Set the page variable based on this
          $data['page'] = "bid";
        }
        //If editing an open bid
        if($page == "editopenbid") {
          //Set the page variable based on this
          $data['page'] = "openbid";
        }
        //If creating a transfer
        if($page == "utilize") {
          //Set the page variable based on this
          $data['page'] = "utilize";
        }
        //If creating a transfer
        if($page == "external_transfer") {
          //Set the page variable based on this
          $data['page'] = "external_transfer";
        }
        //If editing a trade
        if(($page == "sale_edit" || $page == "utilization_edit") && $tradeId > 0) {
          //Check access to this credit
          $permissions = $this->memberpermissions->checkCreditAccess($id, 0, 'creditEditorOnly');
          //Get Trade
          $tradeData = $this->Trading->get_trade($tradeId);
          $tradeData = $tradeData['trade'];
          $data['tradeData'] = $tradeData;
          //We don't allow editing of OIX trades from DMA system (because don't have access/permission to taxpayers)
          if($tradeData['tradeType'] == "oix_marketplace_trade") {
            redirect('/dashboard');
          }
          //Set the page variable based on this
          $data['page'] = ($page == "utilization_edit") ? "utilize" : $tradeData['tradeType'];
          $data['tradeData']['transfer_date'] = date('m/d/Y', strtotime($tradeData['timeStamp']));
          //Define the bid id variable
          $bidId = $tradeData['bidId'];
        }

        //This is being used for both Post an Open Bid AND Post a Bid on a Credit workflows
        $data['workflow'] = ($id != "" && $data['page'] != "editopenbid") ? "bidOnCredit" : "bidNew";

        if(($data['workflow'] == "bidOnCredit" || $data['page'] == "external_transfer") && $data['page'] != "utilize" && $data['page'] != "oix_marketplace_trade") {

          $data['lnav_key'] = "marketplace";
          $data['tabArray'] = "";

          $data['itemType'] = "credit";
          $data['itemNameCap'] = "Credit";
          $data['itemNameLow'] = "credit";

          $data['details'] = $this->CreditListings->get_active_listing($id); // check condition first
          $data['program'] = $data['details'];

          if($data['page'] != "external_transfer") {
            //CHECK PERMISSION (if credit is public and if owner, then kick out)
            $this->memberpermissions->checkCreditPublic($id, 1);
            //Listing Check -> Kick out if not listed
            if($data['program']['listed'] != 1) {
              redirect('/dashboard');
            }
          }

          $data['program']['stateName'] = $data['details']['name'];
          if($data['details']['State'] != "") {
            $data['mergedOffset'] = $this->IncentivePrograms->get_offsets_by_state_short($data['details']['State']);
          } else {
            $data['mergedOffset'] = [];
          }

          $data['taxOffsetVal'] = ['jani'];
          $data['highestBid'] = $this->CreditListings->get_highest_bid($id);

        }

        if($data['page'] == "utilize" || $data['page'] == "external_transfer" || $data['page'] == "oix_marketplace_trade") {

          $data['current_tab_key'] = "";
          $data['lnav_key'] = "";

          $data['itemType'] = "credit";
          $data['itemNameCap'] = "Credit";
          $data['itemNameLow'] = "credit";

          $data['currentPage'] = $this;

          $data['details'] = $this->CreditListings->get_credit_private($id);

          $data['details']['offerSize'] = $data['details']['availableToList'];
          if($data['editMode'] && $tradeId > 0) {
            if($data['page'] == "utilize") {
              $data['details']['offerSize'] = $data['details']['availableToList'] + $data['tradeData']['tradeSize'];
            }
          }
          $data['program'] = $data['details'];

          //Check access to credit, get permissions, add to $data
          $permissions = $this->memberpermissions->checkCreditAccess($id, 0, 'creditEditorOnly', 1);
          $data = array_merge($data, $permissions);

          /// TEMPORARY /////////

          //Centralized function to clean/prepare filter data
          $sanitizedPostData = $this->CreditListings->prepareFilterData();
          $fData = array_merge($data, $sanitizedPostData);

          //Centralized function build filter query parameters
          $cData = $this->CreditListings->buildFilterSearchData($fData);

          $cData['listingId'] = $id;

          $credits = $this->CreditListings->get_credits($cData);
          $data['credit_temp'] = $credits['credits'][0];

          /// TEMPORARY /////////

          $data['active'] = $this->CreditListings->get_active_listing($id);
          $trades = $this->Trading->get_trades_on_listing($id);
          $data['trades'] = $trades['trades'];

          $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
          $data = $data + $dmamembers_credit_access;

          //If is a broker situation
          $data['isRecordTradeBroker'] = ($data['program']['brokerDmaId'] > 0) ? true : false;
          $data['actionWordToUse'] = ($data['program']['brokerDmaId'] > 0) ? "trade" : "utilization";

          //Workflow
          $data['creditWorkflow'] = $this->Workflow->get_workflow('', 'credit', $id, 'workflow');
          $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit');

          $data['totalTradeAmount'] = $this->Trading->get_total_trade_amount_on_listing($id);
          $data['totalAmountAllHarvest'] = 0;
          $data['minCreditEstVal'] = $data['totalTradeAmount']['totalTradeAmount'] + $data['totalAmountAllHarvest'];

          //Get the credit holder's accounts for internal transfer
          $data['myAccounts'] = $this->DmaAccounts->get_my_dma_accounts($this->cisession->userdata('userId'));

          $data['utilizationTypes'] = $this->Trading->get_utilization_types();

        }

        if($data['workflow'] == "bidNew") {

          $data['lnav_key'] = "buycredit";
          $data['tabArray'] = "buycredit";
          $data['current_tab_key'] = "";

          $data['page'] = "";
          $data['states'] = $this->IncentivePrograms->get_all_states();
          $data['tax_years'] = $this->IncentivePrograms->get_taxyear_by_active_program();
          unset($data['states'][0]);
        }

        if($this->cisession->userdata("level") == 4) {
          $data['taxpayers'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 0, 0, 0, 0, 0);
        }

        //If there is an existing Bid ID and we're on edit page
        if($data['editMode'] && ($bidId > 0 || $tradeId > 0)) {

          if($data['page'] == "utilize") {
            $data['details']['bidId'] = 0;
            $data['details']['bid_price_percentage'] = $tradeData['tradePrice'];
            if($data['tradeData']['tradeSize'] > $data['details']['offerSize']) {
              $data['details']['bid_size_est'] = ceil($data['tradeData']['tradeSize']);
              $data['details']['bid_size'] = ceil($data['details']['offerSize']);
              $data['details']['estTradeLargerThanRemainingFlag'] = true;
            } else {
              $data['details']['bid_size'] = ceil($data['tradeData']['tradeSize']);
              $data['details']['estTradeLargerThanRemainingFlag'] = false;
            }
            $data['details']['tExchangeRate'] = (float)$data['tradeData']['tExchangeRate'];
            $data['details']['minimumCreditIncrement'] = 100000000000;
            $data['details']['good_until_date'] = 3476496257;
            $data['details']['changedPS'] = 0;
            $data['details']['accountId'] = $tradeData['accountId'];
          } else {
            $bidInfo = $this->BidMarket->get_bid_to_edit($bidId);
            $data['details']['bidId'] = $bidInfo['bidId'];
            $data['details']['bid_price_percentage'] = $bidInfo['bidPrice'];
            $data['details']['bid_size'] = $bidInfo['bidSize'];
            $data['details']['tExchangeRate'] = (float)$data['tradeData']['tExchangeRate'];
            $data['details']['minimumCreditIncrement'] = $bidInfo['minimumCreditIncrement'];
            $data['details']['good_until_date'] = $bidInfo['bidExpirationDate'];
            $data['details']['changedPS'] = $bidInfo['changedPS'];
            $data['details']['accountId'] = $bidInfo['accountId'];
          }

          if($tradeId > 0) {
            $data['transactions'] = $this->Trading->get_transactions_of_trade($tradeId);
          } else {
            $data['transactions'] = $this->Trading->get_transactions_of_bid($bidId);
          }

        }

        //If there is an existing Bid ID and we're on open bid edit
        if($bidId != '' && $data['page'] == 'openbid') {
          $openBidInfo = $this->BidMarket->get_binding_bid_details($bidId);
          $bidInfo = $this->BidMarket->get_bid_to_edit($openBidInfo['bidId']);
          $data['details']['openBid'] = $openBidInfo;
          $data['details']['openBidId'] = $bidId;
          $data['details']['bidId'] = $bidInfo['bidId'];
          $data['details']['bid_price_percentage'] = $bidInfo['bidPrice'];
          $data['details']['bid_size'] = $bidInfo['bidSize'];
          $data['details']['minimumCreditIncrement'] = $bidInfo['minimumCreditIncrement'];
          $data['details']['good_until_date'] = $bidInfo['bidExpirationDate'];
          $data['details']['changedPS'] = $bidInfo['changedPS'];

          $data['transactions'] = $this->Trades->get_transactions_of_bid($openBidInfo['bidId']);
        }

        $credit = [
            [
                'field' => 'bid_size',
                'rules' => 'required|trim|xss_clean',
            ],
        ];
        $this->form_validation->set_rules($credit);

        $data['data'] = $data;

        if($this->form_validation->run($credit)) {

          $data['programName'] = $this->IncentivePrograms->get_current_program($this->input->post('programs'));

          $this->load->view('includes/left_nav', $data);
          if($data['workflow'] == "bidOnCredit") {
            //$this->load->view('includes/credit_header', $data);
          } else {
            $this->load->view('includes/tab_nav', $data);
          }
          $this->load->view("marketplace/bid_confirm", $data);
          $this->load->view('includes/footer-2');

        } else {
          $data['getMembers'] = [];
          $data['memberDropdown'] = "no";

          $this->load->view('includes/left_nav', $data);
          $this->load->view('includes/currency_and_accounting_scripts', $data);

          if($data['page'] == "utilize" || $data['page'] == "external_transfer" || $data['page'] == "oix_marketplace_trade") {
            //$this->load->view('includes/credit_header', $data);
          } else {
            if($data['editMode']) {
              //do nothing
            } else {
              if($data['workflow'] == "bidOnCredit") {
                $this->load->view('includes/listing_header_sub', $data);
              } else {
                $this->load->view('includes/tab_nav', $data);
              }
            }
          }

          $this->load->view("marketplace/bid", $data);
          $this->load->view('includes/footer-2');

        }

      }

    }
  }

  function bid_confirm($id = "", $page = "", $bidId = "", $tradeId = "") {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    if(!$this->cisession->userdata('levelBuyCredit')) {
      redirect('/dmamembers/no_permission');
    } else {

      //Key Variables
      $data['page'] = ""; //defines the page we're on: buynow, edit, editopenbid, oix_marketplace_trade, internal_transfer, external_transfer
      $data['workflow'] = ""; //defines if this is bidding on a credit or an open bid
      $data['editMode'] = ($bidId > 0 || $tradeId > 0) ? true : false; //if there's a bid or trade ID, then we're editing it
      $data['bidId'] = ($bidId > 0) ? $bidId : 0;
      $data['tradeId'] = ($tradeId > 0) ? $tradeId : 0;

      //If editing a bid
      if($page == "edit") {
        //Set the page variable based on this
        $data['page'] = "bid";
      }
      //If editing a trade
      if($page == "editopenbid") {
        //Set the page variable based on this
        $data['page'] = "openbid";
      }
      //If creating a transfer
      if($page == "utilize_confirm") {
        //Set the page variable based on this
        $data['page'] = "utilize";
      }
      //If creating a transfer
      if($page == "external_transfer_confirm") {
        //Set the page variable based on this
        $data['page'] = "external_transfer";
      }
      //If editing a trade
      if($tradeId > 0) {
        //Get Trade
        $tradeData = $this->Trading->get_trade($tradeId);
        $tradeData = $tradeData['trade'];
        //We don't allow editing of OIX trades from DMA system (because don't have access/permission to taxpayers)
        if($tradeData['tradeType'] == "oix_marketplace_trade") {
          redirect('/dashboard');
        }
        //Set the page variable based on this
        $data['page'] = ($page == "utilize_confirm") ? "utilize" : $tradeData['tradeType'];
        $data['tradeData']['transfer_date'] = date('m/d/Y', strtotime($tradeData['timeStamp']));
        //Define the bid id variable
        $bidId = $tradeData['bidId'];
      }

      //This is being used for both Post an Open Bid AND Post a Bid on a Credit workflows
      $data['workflow'] = ($id != "" && $data['page'] != "editopenbid") ? "bidOnCredit" : "bidNew";

      if($data['workflow'] == "bidOnCredit") {

        $data['lnav_key'] = "marketplace";
        $data['tabArray'] = "";

        //CHECK PERMISSION (if credit is public and if owner, then kick out)
        if($data['page'] == "utilize") {
          $permissions = $this->memberpermissions->checkCreditAccess($id, "", "", "", "", "planInternalTransfer");
        } else {
          if($data['page'] == "external_transfer") {
            $permissions = $this->memberpermissions->checkCreditAccess($id, "", "", "", "", "planExternalTransfer");
          } else {
            if($data['workflow'] == "bidOnCredit") {
              $this->memberpermissions->checkCreditPublic($id, 1);
            }
          }
        }

        $data['itemType'] = "credit";
        $data['itemNameCap'] = "Credit";
        $data['itemNameLow'] = "credit";

        $data['details'] = $this->CreditListings->get_active_listing($id);
        $data['program'] = $data['details'];

        /// TEMPORARY /////////

        //Centralized function to clean/prepare filter data
        $sanitizedPostData = $this->CreditListings->prepareFilterData();
        $fData = array_merge($data, $sanitizedPostData);

        //Centralized function build filter query parameters
        $cData = $this->CreditListings->buildFilterSearchData($fData);

        $cData['listingId'] = $id;

        $credits = $this->CreditListings->get_credits($cData);
        $data['credit_temp'] = $credits['credits'][0];

        /// TEMPORARY /////////

        $data['program']['stateName'] = $data['details']['name'];
        $data['highestBid'] = $this->CreditListings->get_highest_bid($id);

        if($data['page'] != "utilize") {

          $data['taxOffsetName'] = $this->IncentivePrograms->getTaxOffsetNameById($_POST['taxOffset']);
          $data['countryHidden'] = $_POST['country'];
          $data['stateHidden'] = $_POST['state'];
          $data['taxYearHidden'] = $_POST['taxYear'];
          $data['taxOffsetHidden'] = $_POST['taxOffset'];
          $data['programName'] = $this->IncentivePrograms->get_current_program($this->input->post('programs'));

        }
      }

      if($data['workflow'] == "bidNew") {

        $data['lnav_key'] = "buycredit";
        $data['tabArray'] = "buycredit";
        $data['current_tab_key'] = "";

        $data['details'] = null;
        $data['bid_jurisdiction'] = $this->IncentivePrograms->get_state($_POST['jurisdiction']);
        $data['bid_offset'] = $this->IncentivePrograms->get_offset_by_id($_POST['offsets']);
        $data['bid_tax_year'] = $this->IncentivePrograms->get_taxyears_by_id($_POST['bid_tax_year']);
      }

      //If there is an existing Bid ID and we're on edit page
      if($bidId != '' && ($data['editMode'] || $data['page'] == 'utilize')) {
        $bidInfo = $this->BidMarket->get_bid_to_edit($bidId);
        $data['details']['bidId'] = $bidInfo['bidId'];
        $data['details']['bid_price_percentage'] = $bidInfo['bidPrice'];
        $data['details']['bid_size'] = $bidInfo['bidSize'];
        $data['details']['minimumCreditIncrement'] = $bidInfo['minimumCreditIncrement'];
        $data['details']['good_until_date'] = $bidInfo['bidExpirationDate'];
        $data['details']['changedPS'] = $bidInfo['changedPS'];

        $data['transactions'] = $this->Trades->get_transactions_of_bid($bidId);

      }

      //If there is an existing Bid ID and we're on open bid edit
      if($bidId != '' && $data['editMode'] && $data['page'] == 'openbid') {
        $openBidInfo = $this->BidMarket->get_binding_bid_details($bidId);
        $bidInfo = $this->BidMarket->get_bid_to_edit($openBidInfo['bidId']);
        $data['details']['openBid'] = $openBidInfo;
        $data['details']['openBidId'] = $bidId;
        $data['details']['bidId'] = $bidInfo['bidId'];
        $data['details']['bid_price_percentage'] = $bidInfo['bidPrice'];
        $data['details']['bid_size'] = $bidInfo['bidSize'];
        $data['details']['minimumCreditIncrement'] = $bidInfo['minimumCreditIncrement'];
        $data['details']['good_until_date'] = $bidInfo['bidExpirationDate'];
        $data['details']['changedPS'] = $bidInfo['changedPS'];
        //Set these to dummy data to not throw PHP errors
        $data['details']['listingId'] = null;
        $data['details']['offerSize'] = null;
        $data['details']['offerPcnt'] = null;
        $data['details']['OfferGoodUntil'] = null;
        $data['details']['shortOffSettingTaxList'] = null;
        $data['details']['companyName'] = null;
        $data['details']['State'] = null;
        $data['details']['ProgramName'] = null;
        $data['details']['offsetName'] = null;
        $data['details']['OIXIncentiveId'] = null;
        $data['details']['allOrNone'] = null;
        $data['details']['HighestBid'] = null;
        $data['details']['HighestBidSize'] = null;
        $data['details']['originalOfferSize'] = null;
        $data['details']['incrementAmount'] = null;

        $data['transactions'] = $this->Trades->get_transactions_of_bid($openBidInfo['bidId']);
      }

      if($data['page'] == "utilize" || $data['page'] == "external_transfer") {

        $data['current_tab_key'] = "";
        $data['lnav_key'] = "";

        $data['itemType'] = "credit";
        $data['itemNameCap'] = "Credit";
        $data['itemNameLow'] = "credit";

        $data['currentPage'] = $this;

        $data['details'] = $this->CreditListings->get_credit_private($id);
        $data['details']['offerSize'] = $data['details']['availableToList'];
        $data['program'] = $data['details'];

        //Check access to credit, get permissions, add to $data
        $permissions = $this->memberpermissions->checkCreditAccess($id, 0, 'creditEditorOnly', 1);
        $data = array_merge($data, $permissions);

        $data['active'] = $this->CreditListings->get_active_listing($id);
        $trades = $this->Trading->get_trades_on_listing($id);
        $data['trades'] = $trades['trades'];

        $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($id, $this->cisession->userdata('dmaId'), $permissions);
        $data = $data + $dmamembers_credit_access;

        //If is a broker situation
        $data['isRecordTradeBroker'] = ($data['program']['brokerDmaId'] > 0) ? true : false;
        $data['actionWordToUse'] = ($data['program']['brokerDmaId'] > 0) ? "trade" : "utilization";

        //Workflow
        $data['creditWorkflow'] = $this->Workflow->get_workflow('', 'credit', $id, 'workflow');
        $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), 'credit');

        $data['totalTradeAmount'] = $this->Trading->get_total_trade_amount_on_listing($id);
        $data['totalAmountAllHarvest'] = 0;
        $data['minCreditEstVal'] = $data['totalTradeAmount']['totalTradeAmount'] + $data['totalAmountAllHarvest'];

        //Utilization Type info
        $data['utilizationType_details'] = $this->Trading->get_utilization_type($this->input->post('utilizationType'));

        //Account being transfered to
        if($this->input->post('utilizingEntitySwitch') == 'taxentity') {
          $taxpayerDetails = $this->Taxpayersdata->get_taxpayer($this->input->post('entitySelected'));
          $data['entitySelected_name'] = $taxpayerDetails['taxpayerName'];
          $data['entityType_name'] = "Legal Entity";
        } else {
          if($this->input->post('utilizingEntitySwitch') == 'customname') {
            $data['entitySelected_name'] = $this->input->post('utilizingEntityCustomName');
            $data['entityType_name'] = "Third-Party Account";
          } else {
            if($this->input->post('utilizingEntitySwitch') == 'customers') {
              $accountDetails = [];
              $data['entitySelected_name'] = $accountDetails['title'];
              $data['entityType_name'] = "Customer";
            } else {
              if($this->input->post('utilizingEntitySwitch') == 'myaccounts') {
                $accountDetails = [];
                $data['entitySelected_name'] = $accountDetails['title'];
                $data['entityType_name'] = "Other Account";
              } else {
                $data['entitySelected_name'] = $this->cisession->userdata('dmaTitle');
                $data['entityType_name'] = "Internal/Self";
              }
            }
          }
        }

      }

      $data['memberDropdown'] = "no";

      $data['data'] = $data;

      $this->load->view('includes/left_nav', $data);

      if($data['page'] == "utilize" || $data['page'] == "external_transfer") {
        //$this->load->view('includes/credit_header', $data);
      } else {
        if($data['editMode'] && ($data['page'] == 'bid' || $data['page'] == 'openbid')) {
          //do nothing
        } else {
          if($data['workflow'] == "bidOnCredit") {
            $this->load->view('includes/listing_header_sub', $data);
          } else {
            $this->load->view('includes/tab_nav', $data);
          }
        }
      }

      if($data['page'] == "utilize") {
        $this->load->view("marketplace/utilize_confirm", $data);
      } else {
        $this->load->view("marketplace/bid_confirm", $data);
      }

      $this->load->view('includes/footer-2');

    }

  }

  function bid_submit() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['bid_size'])) {
        redirect('/marketplace');
      }

      //Check if this is an internal transfer, recorded trade or regular bid
      $bidActionType = "";
      if(isset($_POST['bidActionType'])) {
        if($_POST['bidActionType'] == "utilize" || $_POST['bidActionType'] == "external_transfer") {
          $bidActionType = $_POST['bidActionType'];
        }
      }

      if(!$this->cisession->userdata('levelBuyCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        $this->session->set_flashdata('messageSuccess', 1);

        //If this is an internal transfer or external sale
        if(isset($_POST['listingId']) && ($bidActionType == "utilize" || $bidActionType == "external_transfer")) {

          $transferBuyerAccount = ($bidActionType == "internal_transfer") ? $_POST['transfer_account'] : 0;

          //CHECK PERMISSION (if you have access to this credit then they get kicked out)
          $this->memberpermissions->checkCreditAccess($_POST['listingId'], 1);

          if(!is_null($newBid = $this->CreditListings->add_bid())) {

            //If buying on behalf of legal entities (single or multi-buyer) or else on behalf of self
            $taxEntityData = json_decode($this->input->post('taxEntityDataFinal'));
            if(sizeof($taxEntityData) > 0) {
              foreach($taxEntityData as $ted) {
                $this->Trading->add_pending_transaction($newBid, $transferBuyerAccount, $ted->taxpayerId, $ted->creditAmount, '');
              }
            } else {
              $this->Trading->add_pending_transaction($newBid, $transferBuyerAccount, '', $_POST['bid_size'], '');
            }

            $this->CreditListings->updateTime($_POST['listingId']);

            //redirect to trigger a trade
            redirect('dashboard/processSellBid/' . $_POST['listingId'] . '/' . $newBid . '/' . $bidActionType);

          }

        }

        //If this is a bid on a credit (but not an internal transfer or external sale)
        if(isset($_POST['listingId']) && $this->input->post('agreeToPSA') && $bidActionType != "internal_transfer" && $bidActionType != "external_transfer") {

          //CHECK PERMISSION (if credit is NOT public, then kick out)
          $this->memberpermissions->checkCreditPublic($_POST['listingId'], 1);

          $data['details'] = $this->CreditListings->get_active_listing($_POST['listingId']);
          $data['program'] = $data['details'];

          //If Buy Order via Customer Assist
          if($this->cisession->userdata("level") != 4 || ($this->cisession->userdata("level") == 4 && $_POST['listingCustomerAssist'] == 1)) {

            $buyOrderId = $this->BidMarket->add_open_bid_by_member();
            $this->CreditListings->updateTime($_POST['listingId']);

            $emailData['members'] = $this->BidMarket->get_buyer_seller_in_buyorder($buyOrderId);

            $emailData['bid'] = $this->BidMarket->get_buy_order_details($buyOrderId);

            //PREPARE EMAIL DATA
            $emailData['updateType'] = 'newBuyOrderOnCredit';
            $emailData['signature'] = 2;

            //Send Email to Buyer (confirmation)
            $emailData['credit'] = $this->CreditListings->get_credit_private($_POST['listingId']);
            $emailData['greeting'] = 1;

            //Buyer specific email data and SEND
            $emailData['firstName'] = $emailData['members']['firstNameBuyer'];
            $emailData['email'] = $emailData['members']['emailBuyer'];
            $emailData['headline'] = 'Buy%20Order%20Confirmation';
            $emailData['emailSubject'] = 'Buy Order Confirmation';
            $emailData['introHTML'] = "Your Buy Order on credit " . $emailData['credit']['state'] . $emailData['credit']['listingId'] . " has been received by OIX Customer Assist. You will receive an email notification when your Buy Order has been reviewed and listed on the OIX Marketplace.<br><br>";
            $emailData['button'] = 0;
            $this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $emailData['email'], $emailData);

            //send email
            $emailData['buyOrderId'] = $buyOrderId;
            $emailData['listingId'] = $_POST['listingId'];
            $emailData['currentPct'] = $_POST['bid_price_percentage'];
            $emailData['currentSize'] = $_POST['bid_size'];
            $emailData['offerPcnt'] = $_POST['offerPcnt'];
            $emailData['offerSize'] = $_POST['offerSize'];
            $emailData['lots'] = $_POST['allOrNone'];
            $emailData['minAmount'] = $_POST['minAmount'];
            $emailData['updateType'] = 'newBuyOrder';
            $emailData['updateTypeName'] = 'New Buy Order';
            $emailData['credit'] = $this->CreditListings->get_active_listing($_POST['listingId']);
            $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);

            //redirect to the buy order page
            redirect('dashboard/buyorder/' . $buyOrderId);
          }

          //If direct Bid (not a Buy Order)
          if(!is_null($newBid = $this->CreditListings->add_bid())) {

            $newdata = [
                'bidId'       => $newBid,
                'listingId'   => $_POST['listingId'],
                'currentPct'  => $_POST['bid_price_percentage'],
                'currentSize' => $_POST['bid_size'],
                'HighestPct'  => $_POST['HighestBid'],
                'HighestSize' => $_POST['HighestBidSize'],
                'offerPcnt'   => $_POST['offerPcnt'],
                'offerSize'   => $_POST['offerSize'],
                'lots'        => $_POST['allOrNone'],
                'minAmount'   => $_POST['minAmount'],
            ];

            $this->cisession->set_userdata($newdata);

            //array with data: taxpayerID, amount

            //If buying on behalf of legal entities (single or multi-buyer) or else on behalf of self
            $taxEntityData = json_decode($this->input->post('taxEntityDataFinal'));
            if(sizeof($taxEntityData) > 0) {
              foreach($taxEntityData as $ted) {
                $this->Trading->add_pending_transaction($newBid, $this->cisession->userdata('userId'), $ted->taxpayerId, $ted->creditAmount, '');
              }
            } else {
              $this->Trading->add_pending_transaction($newBid, $this->cisession->userdata('userId'), '', $_POST['bid_size'], '');
            }

            //$this->new_bid_email($_POST['listingId']);
            //$this->RealTime->add_listing_update($_POST['listingId'],$newBid,null,$this->cisession->userdata('userId'));
            $this->CreditListings->updateTime($_POST['listingId']);

            //If agree to PSA and the price % match then TRIGGER A TRADE
            if($this->input->post('agreeToPSA') == 'yes' && ($newdata['currentPct'] == $newdata['offerPcnt'])) {

              //redirect to see the new purchase
              redirect('dashboard/processSellBid/' . $newdata['listingId'] . '/' . $newdata['bidId'] . '/buyer');

            } else {

              //Get credit data for emails
              $creditData = $this->CreditListings->get_active_listing($_POST['listingId']);
              $emailData['members'] = $this->BidMarket->get_buyer_seller_in_bid($newdata['bidId']);
              $emailData['bid'] = $this->BidMarket->get_bid_by_id($newdata['bidId']);
              $emailData['bid']['allOrNone'] = $emailData['bid']['reducable'];

              //Add new bid to audit trail
              $this->AuditTrail->insert_audit_item(83, $_POST['listingId'], "", "$" . number_format($_POST['bid_size']) . " at $" . number_format($_POST['bid_price_percentage'], 4) . " (" . $emailData['members']['accountTitleBuyer'] . ")", "", "", "", "", "", "", $newdata['bidId']);

              //PREPARE EMAIL DATA
              $emailData['updateType'] = 'newBid';
              $emailData['signature'] = 1;

              //Send Email to Seller (confirmation)
              $emailData['credit'] = $this->CreditListings->get_credit_private($_POST['listingId']);
              $emailData['greeting'] = 1;

              //Buyer specific email data and SEND
              $emailData['firstName'] = $emailData['members']['firstNameBuyer'];
              $emailData['email'] = $emailData['members']['emailBuyer'];
              $emailData['headline'] = 'Bid%20Listed%20Confirmation';
              $emailData['emailSubject'] = 'Bid Listed Confirmation';
              $emailData['introHTML'] = "Your bid has been listed on the OIX Marketplace against credit " . $creditData['state'] . $creditData['listingId'] . ". The seller of this credit has been notified. You will receive an email notification if your bid is accepted by the seller (thereby triggering a trade).<br><br>";
              $emailData['button'] = 0;
              $this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $emailData['email'], $emailData);

              //Seller specific email data and SEND
              $emailData['firstName'] = $emailData['members']['firstNameSeller'];
              $emailData['email'] = $emailData['members']['emailSeller'];
              $emailData['headline'] = 'Bid%20Submitted%20on%20Your%20Credit';
              $emailData['emailSubject'] = 'Bid Submitted on Your Credit';
              $emailData['introHTML'] = "A bid has been submitted against your credit " . $creditData['state'] . $creditData['listingId'] . ". Please review the details below. You can login to your account to learn more and/or accept this bid.<br><br>";
              $emailData['button'] = 1;
              $this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $emailData['email'], $emailData);

              //send email to OIX Admin
              $emailData = $newdata;
              $emailData['updateTypeName'] = 'New Bid';
              //Reset the credit data
              $emailData['credit'] = $creditData;
              $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);

              //Insert NEW BID ON CREDIT notification to database
              $nData['nType'] = 'bid_listed';
              $nData['nMembers'] = 'active_all_notifications_on';
              $nData['nGreeting'] = 0;
              $nData['nSignature'] = 0;
              $nData['nButton'] = 1;
              $nData['nActivityId'] = $newdata["bidId"];
              $nData['nSubject'] = 'New Bid on ' . $creditData["name"] . ' ' . $creditData["taxYear"] . ' Credit on the OIX';
              $nData['nHeadline'] = str_replace('%20', ' ', 'New Bid on Credit on the OIX');
              $this->Members_Model->insert_notification($nData);

              //If this user is DMA
              if($this->cisession->userdata('level') == 4) {

                //Insert message
                /* VERSION TO BUYER AND SELLER - commented out for now since we don't want buyers getting blasted on other's activity */
                /*
											$msType = "update";
											$msAction = "bid_new";
											$msListingId = $_POST['listingId'];
											$msBidId = $newdata['bidId'];
											$msTradeId = "";
											$msTitle = "New Bid (".$creditData['state'].$creditData['listingId']."-".$newdata['bidId'].") on Credit ".$creditData['state'].$creditData['listingId']." - $".number_format($_POST['bid_size'])." @ $".number_format($_POST['bid_price_percentage'], 4);
											$msTitle2 = "New Bid (".$creditData['state'].$creditData['listingId']."-".$newdata['bidId'].") on Credit ".$creditData['state'].$creditData['listingId']." (".$creditData['stateCertNum'].") - $".number_format($_POST['bid_size'])." @ $".number_format($_POST['bid_price_percentage'], 4);
											$msTitleShared = $msTitle2;
											$msTitleShort = "New Bid (".$creditData['state'].$creditData['listingId']."-".$newdata['bidId'].") - $".number_format($_POST['bid_size'])." @ $".number_format($_POST['bid_price_percentage'], 4);
											$msTitle2Short = "New Bid (".$creditData['state'].$creditData['listingId']."-".$newdata['bidId'].") - $".number_format($_POST['bid_size'])." @ $".number_format($_POST['bid_price_percentage'], 4);
											$msTitleSharedShort = $msTitle2Short;
											$msContent = "";
											$msContent2 = "";
											$msPerspective = "buyer";
											$msPerspective2 = "seller";
											$firstDmaMainUserId = $this->cisession->userdata('primUserId');
											$secondDmaMainUserId = $creditData['listedBy'];
											$msUserIdCreated = $this->cisession->userdata('userId');
											$alertShared = TRUE;
											$mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);
											*/
                /* VERSION TO JUST SELLER */
                $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
                $msType = "update";
                $msAction = "bid_new";
                $msListingId = $_POST['listingId'];
                $msBidId = $newdata['bidId'];
                $msTradeId = "";
                $msTitle = "New Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $newdata['bidId'] . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ") - $" . number_format($_POST['bid_size']) . " @ $" . number_format($_POST['bid_price_percentage'], 4);
                $msTitle2 = "";
                $msTitleShared = $msTitle;
                $msTitleShort = "New Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $newdata['bidId'] . ") - $" . number_format($_POST['bid_size']) . " @ $" . number_format($_POST['bid_price_percentage'], 4);
                $msTitle2Short = "";
                $msTitleSharedShort = $msTitleShort;
                $msContent = "";
                $msContent2 = "";
                $msPerspective = "seller";
                $msPerspective2 = "";
                $firstDmaMainUserId = $creditData['listedBy'];
                $secondDmaMainUserId = "";
                $msUserIdCreated = $this->cisession->userdata('userId');
                $alertShared = true;
                $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);
              }

              //redirect to see the new bid
              redirect('dashboard/bid/' . $newBid);

            }

          }
        }

        //If this is an open bid
        if(empty($_POST['listingId'])) {

          //If Buy Order via Customer Assist
          /*
			        if($this->cisession->userdata("level") != 4 || ($this->cisession->userdata("level") == 4 && $_POST['listingCustomerAssist']==1)){
			            $buyOrderId = $this->BidMarket->add_open_bid_by_member();
			            redirect('dashboard/buyorder/'.$buyOrderId);
			        }
							*/

          //Else...
          $data = $this->cisession->all_userdata();

          if(!is_null($data['bidInfo'] = $this->BidMarket->add_open_bid(
              $_POST['bid_jurisdiction'],
              $_POST['bid_tax_year'],
              $_POST['bid_offset'],
              $_POST['bid_size'],
              $_POST['bid_price_percentage']
          ))) {

            if($this->cisession->userdata('level') == 4) {

              //If buying on behalf of legal entities (single or multi-buyer) or else on behalf of self
              $taxEntityData = json_decode($this->input->post('taxEntityDataFinal'));
              if(sizeof($taxEntityData) > 0) {
                foreach($taxEntityData as $ted) {
                  $this->Trading->add_pending_transaction($data['bidInfo']['bidId'], $this->cisession->userdata('userId'), $ted->taxpayerId, $ted->creditAmount, $data['bidInfo']['openBidId']);
                }
              } else {
                $this->Trading->add_pending_transaction($data['bidInfo']['bidId'], $this->cisession->userdata('userId'), '', $_POST['bid_size'], $data['bidInfo']['openBidId']);
              }

            }

            //$this->RealTime->add_listing_update(null, $data['bidInfo']['pendingListingId'],null,$this->cisession->userdata('userId'));

            //if Open Bid is a Buy Order
            if($_POST['listingCustomerAssist'] == 1) {

              //send email
              $emailData['openBidId'] = $data['bidInfo']['openBidId'];
              $emailData['state'] = $_POST['bid_jurisdiction'];
              $emailData['currentPct'] = $_POST['bid_price_percentage'];
              $emailData['currentSize'] = $_POST['bid_size'];
              $emailData['updateType'] = 'newOpenBidBuyOrder';
              $emailData['updateTypeName'] = 'New Buy Order for an Open Bid';
              $emailData['members'] = $this->BidMarket->get_buyer_seller_in_buyorder($emailData['openBidId']);
              $emailData['credit'] = $this->CreditListings->get_active_listing($_POST['listingId']);
              $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);

              //If this is a Buy Order, redirect to see it
              redirect('dashboard/buyorder/' . $data['bidInfo']['openBidId']);

              //if Open Bid is direct, from DMA
            } else {

              $creditData = $this->CreditListings->get_active_listing($_POST['listingId']);
              /*$openBidData = $this->BidMarket->get_openbid_by_id($data['bidInfo']['openBidId']);*/

              //send email
              $emailData['openBidId'] = $data['bidInfo']['openBidId'];
              $emailData['state'] = $_POST['bid_jurisdiction'];
              $emailData['currentPct'] = $_POST['bid_price_percentage'];
              $emailData['currentSize'] = $_POST['bid_size'];
              $emailData['updateType'] = 'newOpenBid';
              $emailData['updateTypeName'] = 'New Open Bid';
              $emailData['members'] = $this->BidMarket->get_buyer_seller_in_buyorder($emailData['openBidId']);
              $emailData['credit'] = $creditData;
              $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);

              //Insert NEW OPEN BID notification to database
              /*
											$nData['nType'] = 'openbid_listed';
											$nData['nMembers'] = 'active_all_notifications_on';
											$nData['nGreeting'] = 0;
											$nData['nSignature'] = 0;
											$nData['nButton'] = 1;
											$nData['nActivityId'] = $data['bidInfo']['openBidId'];
											$nData['nSubject'] = 'New Open Bid for '.$openBidData['name'].' '.$openBidData['taxYear'].' Credit on the OIX';
											$nData['nHeadline'] = str_replace('%20', ' ', 'New Open Bid for Credit on the OIX');
											$this->Members_Model->insert_notification($nData);
											*/

              //If this is an Open Bid, redirect to see it
              redirect('dashboard/openbid/' . $data['bidInfo']['pendingListingId']);
            }
          }
        }

      }
    }
  }

  function bid_update($bidId, $tradeId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['bid_size'])) {
        redirect('/marketplace');
      }

      if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
        throw new \Exception('General fail');
      }

      //CHECK PERMISSION
      //If we're editing a trade, check if trade owner if this is editing a trade
      if($tradeId > 0) {

        $tradeData = $this->Trading->get_trade($tradeId);
        $tradeData = $tradeData['trade'];
        $creditData = $this->CreditListings->get_credit_private($_POST['listingId']);

        $bidId = $tradeData['bidId'];

      } else {
        //if not Bid owner, then kick out
        $this->memberpermissions->checkBidAccess($bidId, 0, 'bidManagerOnly');
      }

      $this->session->set_flashdata('messageSaveSuccess', 1);

      //If this is a bid on a credit
      if($_POST['listingId'] > 0 && ($this->input->post('agreeToPSA') || $tradeId > 0)) {

        //Get Bid before updating
        $bidBeforeUpdate = $this->BidMarket->get_bid_by_id($bidId);

        if(sizeof($bidBeforeUpdate) > 0) {

          //If direct Bid (not a Buy Order)
          if($this->CreditListings->update_bid($bidId)) {
            $newdata = [
                'bidId'       => $bidId,
                'listingId'   => $_POST['listingId'],
                'currentPct'  => $_POST['bid_price_percentage'],
                'currentSize' => $_POST['bid_size'],
                'HighestPct'  => $_POST['HighestBid'],
                'HighestSize' => $_POST['HighestBidSize'],
                'offerPcnt'   => $_POST['offerPcnt'],
                'offerSize'   => $_POST['offerSize'],
                'lots'        => $_POST['allOrNone'],
                'minAmount'   => $_POST['minAmount'],
            ];

            $this->cisession->set_userdata($newdata);
          }

        }

        //If we are editing a utilization
        if($tradeId > 0 && $tradeData['tradeType'] == 'utilization') {

          $existingTrans = $this->Trading->get_transactions_of_trade($tradeId);
          //Get the first transaction in the loop as that's the existing one we're going to update
          $transactionId = $existingTrans[0]['transactionId'];
          $buyerAccountId = ($_POST['transfer_account'] > 0) ? $_POST['transfer_account'] : 0;
          $firstTransactionAmount = $_POST['bid_size'];
          $utilizingEntitySwitch = $this->input->post('utilizingEntitySwitch');
          $entitySelected = $this->input->post('entitySelected');
          $utilizingEntityCustomName = $this->input->post('utilizingEntityCustomName');
          if($utilizingEntitySwitch == 'taxentity') {
            $taxpayerId = $entitySelected;
          } else {
            $taxpayerId = null;
          }
          $this->Trading->update_pending_transaction($transactionId, $firstTransactionAmount, $buyerAccountId, $taxpayerId, $utilizingEntityCustomName);

        } else {

          //If buying on behalf of legal entities (single or multi-buyer) or else on behalf of self
          $taxEntityData = json_decode($this->input->post('taxEntityDataFinal'));
          if(sizeof($taxEntityData) > 0) { //IF THIS IS: new/edit bid or new/edit external transfer (Internal Transfer skips this)
            //get pre-existing transactions
            if($tradeId > 0) {
              $existingTrans = $this->Trading->get_transactions_of_trade($tradeId);
            } else {
              $existingTrans = $this->Trading->get_transactions_of_bid($bidId);
            }
            $keepTransactions = [];
            $removeTransactions = $existingTrans;
            $newTransactionIds = [];

            //loop through new transactions and edit them or add them
            foreach($taxEntityData as $ted) {
              //If we're editing a trade, then let's update the existing transaction (since it's single buyer)
              if($tradeId > 0) {
                //Get the first transaction in the loop as that's the existing one we're going to update
                $transactionId = $existingTrans[0]['transactionId'];
                //$buyerAccountId = ($ted->buyerAccountId > 0) ? $ted->buyerAccountId : 0;
                $newTaxpayerId = ($ted->taxpayerId > 0) ? $ted->taxpayerId : 0;
                $firstTransactionAmount = $ted->creditAmount;
                $this->Trading->update_pending_transaction($transactionId, $firstTransactionAmount, 0, $newTaxpayerId);
                //If editing an existing transaction
              } else {
                if($ted->transactionId != '') {
                  $this->Trading->update_pending_transaction($ted->transactionId, $ted->creditAmount, 0, 0);
                  //Else if new transaction
                } else {
                  $newTransactionId = $this->Trading->add_pending_transaction($bidId, $this->cisession->userdata('userId'), $ted->taxpayerId, $ted->creditAmount, '');
                  array_push($newTransactionIds, $newTransactionId);
                }
              }

              $x = 0;
              while($x < sizeof($existingTrans)) {
                if($ted->transactionId == $existingTrans[$x]['transactionId']) {
                  //remove $et from $existingTrans
                  unset($removeTransactions[$x]);
                }
                $x++;
              }
            }

            //Delete transactions
            if($tradeId == "") {
              foreach($removeTransactions as $rt) {
                $this->Trades->delete_transaction($rt['transactionId']);
              }
            }

          }

        }

        ////////////////////////////////
        // IF THIS IS EDITING A TRADE //
        ////////////////////////////////
        if($tradeId > 0) {

          //Since we already updated the transaction above, let's take care of the rest

          //First update the trade info
          $tRequest['tradeId'] = $tradeId;
          $tRequest['tradeSize'] = $firstTransactionAmount;
          $tRequest['tradePrice'] = $_POST['bid_price_percentage'];
          $tRequest['timeStamp'] = date('Y-m-d h:i:s', strtotime($_POST['transfer_date']));
          if($tradeData['tradeType'] == 'utilization') {
            $listingId = $this->input->post('listingId');
            $bid_size = $this->input->post('bid_size');
            $bid_exchange_rate = $this->input->post('bid_exchange_rate');
            $bid_price_percentage = $this->input->post('bid_price_percentage');
            $transfer_date = date('Y-m-d H:i:s', strtotime($this->input->post('transfer_date')));
            $utilizationType = $this->input->post('utilizationType');
            $utilizingEntitySwitch = $this->input->post('utilizingEntitySwitch');
            $entitySelected = $this->input->post('entitySelected');
            $convertToActual = $this->input->post('convertToActual');

            //Validate data and re-direct if fail
            if($listingId > 0) {
            } else {
              redirect('/dashboard');
            }
            if($bid_size > 0) {
            } else {
              redirect('/dashboard');
            }
            if($this->input->post('transfer_date') != "" && $transfer_date != "") {
            } else {
              redirect('/dashboard');
            }
            if($utilizationType > 0) {
            } else {
              redirect('/dashboard');
            }
            if($utilizingEntitySwitch == "self") {
              //do nothing
            } else {
              if($utilizingEntitySwitch == "taxentity" || $utilizingEntitySwitch == "myaccounts" || $utilizingEntitySwitch == "customers") { //If this is a legal entity or my accounts
                if($entitySelected > 0) {
                } else {
                  redirect('/dashboard');
                }
              } else {
                if($utilizingEntitySwitch == "customname") { //If this is a custom company name
                  if($utilizingEntityCustomName != "") {
                  } else {
                    redirect('/dashboard');
                  }
                } else {
                  redirect('/dashboard');
                }
              }
            }

            //If trade size is bigger than amount of credit remaining, kick out
            $bid_size_diff = $bid_size - $tradeData['tradeSize']; //Subtract original value from new value to get amount increase
            if($bid_size_diff > $creditData['availableToList']) {
              redirect('/dashboard');
            }

            //Determine the Utilizing Entity
            if($utilizingEntitySwitch == "self") { //Self
              $utilizerAccountId = $this->cisession->userdata('primUserId');
              $utilizerTaxpayerId = null;
            } else {
              if($utilizingEntitySwitch == "taxentity") { //legal entity
                $utilizerAccountId = 0;
                $utilizerTaxpayerId = $entitySelected;
              } else {
                if($utilizingEntitySwitch == "customname") { //custom company name
                  $utilizerAccountId = 0;
                  $utilizerTaxpayerId = null;
                } else {
                  if($utilizingEntitySwitch == "myaccounts") { //my accounts
                    $utilizerAccountId = $entitySelected;
                    $utilizerTaxpayerId = null;
                  } else {
                    if($utilizingEntitySwitch == "customers") { //my accounts
                      $utilizerAccountId = $entitySelected;
                      $utilizerTaxpayerId = null;
                    }
                  }
                }
              }
            }

            //Update Trade
            $tRequest['timeStamp'] = $transfer_date;
            $tRequest['accountId'] = $utilizerAccountId;
            $tRequest['tradeSize'] = $bid_size;
            $tRequest['tradePrice'] = $bid_price_percentage;
            if(isset($bid_exchange_rate) && $bid_exchange_rate > 0) {
              $tRequest['tExchangeRate'] = $bid_exchange_rate;
            }
            $tRequest['utilizationTypeId'] = $utilizationType;
            $tRequest['utilizingEntityType'] = $utilizingEntitySwitch;
            $tRequest['tradeIsEstimated'] = ($tradeData['tradeIsEstimated'] == 1 && $convertToActual == 0) ? 1 : 0;

          }

          $this->Trading->update_trade($tRequest);

          //If this is an ACTUAL trade OR if this is an Estimate being CONVERTED to ACTUAL
          //if($tradeData['tradeIsEstimated']!=1 || $convertToActual==1) {

          //Second, update the credit amount
          //calculate delta (positive or negative)
          //If current size is greater than the new size then we are INCREASING the credit amount
          if($convertToActual == 1) {
            $decreaseAmountBy = $tradeData['tradeSize'];
            $this->CreditListings->update_credit_amount_from_trade_modification($_POST['listingId'], "decrease", $decreaseAmountBy);
          } else {
            if($tradeData['tradeSize'] > $firstTransactionAmount) {
              $increaseAmountBy = $tradeData['tradeSize'] - $firstTransactionAmount;
              $this->CreditListings->update_credit_amount_from_trade_modification($_POST['listingId'], "increase", $increaseAmountBy);
            } else {
              if($tradeData['tradeSize'] < $firstTransactionAmount) {
                $decreaseAmountBy = $firstTransactionAmount - $tradeData['tradeSize'];
                $this->CreditListings->update_credit_amount_from_trade_modification($_POST['listingId'], "decrease", $decreaseAmountBy);
              }
            }
          }

          //Third, check for variances to audit and alert
          //String detailing changes for upcoming alert
          $tradeChangesString = "";
          $nextTradeStart = "";

          //Did credit amount change?
          if($tradeData['tradeSize'] != $firstTransactionAmount) {
            //audit
            $this->AuditTrail->insert_audit_item(90, $_POST['listingId'], "$" . number_format($tradeData['tradeSizeUSD']), "$" . number_format($firstTransactionAmount * $tradeData['tExchangeRate'] ?? 1), "", "", "", "", "", "", $tradeId);
            //alert
            $tradeChangesString .= $nextTradeStart . "Utilization Size (from $" . number_format($tradeData['tradeSizeUSD']) . " to $" . number_format($firstTransactionAmount * $tradeData['tExchangeRate'] ?? 1) . ")";
            $nextTradeStart = " / ";
          }
          //Did purchase price change?
          if($tradeData['tradePrice'] != $_POST['bid_price_percentage']) {
            //audit
            $this->AuditTrail->insert_audit_item(91, $_POST['listingId'], "$" . number_format($tradeData['tradePrice'], 4), "$" . number_format($_POST['bid_price_percentage'], 4), "", "", "", "", "", "", $tradeId);
            //alert
            $tradeChangesString .= $nextTradeStart . "Utilization Price (from $" . number_format($tradeData['tradePrice'], 4) . " to $" . number_format($_POST['bid_price_percentage'], 4) . ")";
            $nextTradeStart = " / ";
          }
          //Did transfer date change?
          $priorTransferDate = date('m-d-Y', strtotime($tradeData['timeStamp']));
          $newTransferDate = date('m-d-Y', strtotime($tRequest['timeStamp']));
          if($priorTransferDate != $newTransferDate) {
            //audit
            $this->AuditTrail->insert_audit_item(92, $_POST['listingId'], $priorTransferDate, $newTransferDate, "", "", "", "", "", "", $tradeId);
            //alert
            $tradeChangesString .= $nextTradeStart . "Utilization Date (from " . $priorTransferDate . " to " . $newTransferDate . ")";
            $nextTradeStart = " / ";
          }
          //Did Buyer change?
          if($tradeData['utilizingEntityType'] == 'myaccounts') {
            if($tradeData['accountId'] != $tRequest['accountId']) {
              $priorAccount = [];
              $newAccount = [];
              //audit
              $this->AuditTrail->insert_audit_item(93, $_POST['listingId'], $priorAccount['title'], $newAccount['title'], "", "", "", "", "", "", $tradeId);
              //alert
              $tradeChangesString .= $nextTradeStart . "Utilizing Entity (from " . $priorAccount['title'] . " to " . $newAccount['title'] . ")";
              $nextTradeStart = " / ";
            }
          } else {
            if($tradeData['utilizingEntityType'] == 'taxentity') {
              //$firstTaxPayer = $taxEntityData[0];
              if($existingTrans[0]['taxpayerId'] != $newTaxpayerId) {

                $priorTaxpayer = $this->Taxpayersdata->get_taxpayer($existingTrans[0]['taxpayerId'], 0, 0);
                $priorTaxpayerName = ($priorTaxpayer['tpAccountType'] == 2) ? $priorTaxpayer['tpFirstName'] . " " . $priorTaxpayer['tpLastName'] : $priorTaxpayer['tpCompanyName'];
                $newTaxpayer = $this->Taxpayersdata->get_taxpayer($newTaxpayerId, 0, 0);
                $newTaxpayerName = ($priorTaxpayer['tpAccountType'] == 2) ? $newTaxpayer['tpFirstName'] . " " . $newTaxpayer['tpLastName'] : $newTaxpayer['tpCompanyName'];

                //audit
                $this->AuditTrail->insert_audit_item(93, $_POST['listingId'], $priorTaxpayerName, $newTaxpayerName, "", "", "", "", "", "", $tradeId);
                //alert
                $tradeChangesString .= $nextTradeStart . "Utilizing Entity (from " . $priorTaxpayerName . " to " . $newTaxpayerName . ")";
                $nextTradeStart = " / ";
              }
            } else {
              if($tradeData['utilizingEntityType'] == 'customname') {
                //$firstTaxPayer = $taxEntityData[0];
                if($existingTrans[0]['utilizingEntityCustomName'] != $utilizingEntityCustomName) {
                  $priorUtilizingEntityCustomName = $existingTrans[0]['utilizingEntityCustomName'];
                  $newUtilizingEntityCustomName = $utilizingEntityCustomName;
                  //audit
                  $this->AuditTrail->insert_audit_item(93, $_POST['listingId'], $priorUtilizingEntityCustomName, $newUtilizingEntityCustomName, "", "", "", "", "", "", $tradeId);
                  //alert
                  $tradeChangesString .= $nextTradeStart . "Utilizing Entity (from " . $priorUtilizingEntityCustomName . " to " . $newUtilizingEntityCustomName . ")";
                  $nextTradeStart = " / ";
                }
              }
            }
          }

          //VARIANCE - Estimated Credit Value
          if($creditData['trackingVariance'] == 1 && ($tradeData['tradeSize'] != $firstTransactionAmount || $tradeData['tradePrice'] != $_POST['bid_price_percentage'])) {
            //Get new credit estimate at this moment in time
            $newCreditEstimateData = $this->CreditListings->get_credit_estimated_value($_POST['listingId']);
            $newCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
            $newCreditEstFaceValue = $newCreditEstimateData['creditAmount'];
            $newCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
            $newCreditEstValue = $newCreditEstimateData['amountValueLocal'];
            //Get most recent Variance audit record for this credit to get the "prior" value
            $allCreditEstimates = $this->AuditTrail->get_audit_trail($_POST['listingId'], 2, '');
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

            $this->AuditTrail->insert_audit_item(2, $_POST['listingId'], $priorCreditEstPrice, $newCreditEstPrice, $priorCreditEstFaceValue, $newCreditEstFaceValue, $priorCreditEstExchange, $newCreditEstExchange, $priorCreditEstValue, $newCreditEstValue, $tradeId, 'utilization_edit');

          }

          $utilizationTypeData = $this->Trading->get_utilization_type($tRequest['utilizationTypeId']);
          //TODO: change $tradeChangesString to USD from local currency
          //Insert message for just the credit holder (since this is a pending share)
          $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
          $msType = "update";
          $msAction = "utilization_update";
          $msListingId = $_POST['listingId'];
          $msBidId = "";
          $msTradeId = $tradeId;
          $msTitle = "Utilization (" . $utilizationTypeData['utName'] . ") Updated (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . ") - " . $tradeChangesString . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
          $msTitle2 = "";
          $msTitleShared = $msTitle;
          $msTitleShort = "Utilization (" . $utilizationTypeData['utName'] . ") Updated (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . ") - " . $tradeChangesString . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
          $msTitle2Short = "";
          $msTitleSharedShort = $msTitleShort;
          $msContent = "";
          $msContent2 = "";
          $msPerspective = "seller";
          $msPerspective2 = "";
          $firstDmaMainUserId = $this->cisession->userdata('primUserId');
          $secondDmaMainUserId = "";
          $msUserIdCreated = $this->cisession->userdata('userId');
          $alertShared = true;
          $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

          if($convertToActual == 1) {
            //Delete any existing calendar alerts (estimated utilization date)
            $this->Members_Model->delete_all_trade_messages($tradeId);
          }

          $this->CreditListings->check_utilization_for_due_dates($tradeId);

          //}

          //Centralized function to clean/prepare filter data
          $preparedFilterData = $this->CreditListings->prepareFilterData();
          //Centralized function build filter query parameters
          $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
          //Update cache for this credit
          $cData['listingId'] = $creditData['listingId'];
          $this->CreditListings->update_credit_cache($cData);

          //redirect to see the trade
          redirect('dashboard/credit/' . $_POST['listingId'] . '/utilization/' . $tradeId);

          //////////////////////////
          // NOT EDITING A TRADE //
          /////////////////////////
        } else {

          //$this->new_bid_email($_POST['listingId']);
          //$this->RealTime->add_listing_update($_POST['listingId'],$bidId,null,$this->cisession->userdata('userId'));
          $this->CreditListings->updateTime($_POST['listingId']);

          //If agree to PSA and the price % match then TRIGGER A TRADE
          if($this->input->post('agreeToPSA') == 'yes' && ($newdata['currentPct'] == $newdata['offerPcnt'])) {

            //redirect to see the new purchase
            redirect('dashboard/processSellBid/' . $newdata['listingId'] . '/' . $newdata['bidId'] . '/buyer');

          } else {

            $creditData = $this->CreditListings->get_active_listing($_POST['listingId']);

            //send email
            $emailData = $newdata;
            $emailData['updateType'] = 'bidUpdate';
            $emailData['updateTypeName'] = 'Bid Updated';
            $emailData['members'] = $this->BidMarket->get_buyer_seller_in_bid($newdata['bidId']);
            $emailData['credit'] = $creditData;
            $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'] . ' - ' . $emailData['credit']['state'] . $newdata['listingId'] . '-' . $newdata['bidId'], $this->config->item("oix_admin_emails_array"), $emailData);

            //Add bid update to credit audit trail
            $this->AuditTrail->insert_audit_item(84, $_POST['listingId'], "$" . number_format($bidBeforeUpdate['bidSize']) . " at $" . number_format($bidBeforeUpdate['bidPrice'], 4) . "", "$" . number_format($_POST['bid_size']) . " at $" . number_format($_POST['bid_price_percentage'], 4) . " (" . $emailData['members']['accountTitleBuyer'] . ")", "", "", "", "", "", "", $newdata['bidId']);

            //COMMENTED OUT -- Insert BID MODIFIED notification to database
            /*
						$nData['nType'] = 'bid_modified';
						$nData['nMembers'] = 'active_all_notifications_on';
						$nData['nGreeting'] = 0;
						$nData['nSignature'] = 0;
						$nData['nButton'] = 1;
						$nData['nActivityId'] = $bidId;
						$nData['nSubject'] = $creditData["name"].' '.$creditData["taxYear"].' Bid Modified on the OIX';
						$nData['nHeadline'] = str_replace('%20', ' ', 'Bid Modified on the OIX');
						$this->Members_Model->insert_notification($nData);
						*/

            //If this user is DMA
            if($this->cisession->userdata('level') == 4) {

              //Insert message
              $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
              $msType = "update";
              $msAction = "bid_update";
              $msListingId = $_POST['listingId'];
              $msBidId = $newdata['bidId'];
              $msTradeId = "";
              $msTitle = "Updated Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $newdata['bidId'] . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " - $" . number_format($_POST['bid_size']) . " @ $" . number_format($_POST['bid_price_percentage'], 4);
              $msTitle2 = "Updated Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $newdata['bidId'] . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ") - $" . number_format($_POST['bid_size']) . " @ $" . number_format($_POST['bid_price_percentage'], 4);
              $msTitleShared = $msTitle2;
              $msTitleShort = "Updated Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $newdata['bidId'] . ") - $" . number_format($_POST['bid_size']) . " @ $" . number_format($_POST['bid_price_percentage'], 4);
              $msTitle2Short = "Updated Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $newdata['bidId'] . ") - $" . number_format($_POST['bid_size']) . " @ $" . number_format($_POST['bid_price_percentage'], 4);
              $msTitleSharedShort = $msTitle2Short;
              $msContent = "";
              $msContent2 = "";
              $msPerspective = "buyer";
              $msPerspective2 = "seller";
              $firstDmaMainUserId = $this->cisession->userdata('primUserId');
              $secondDmaMainUserId = $creditData['listedBy'];
              $msUserIdCreated = $this->cisession->userdata('userId');
              $alertShared = true;
              $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);

            }

            //redirect to see the  bid
            redirect('dashboard/bid/' . $bidId);

          }

        }

      }

      //If this is an open bid
      if($_POST['listingId'] == null || $_POST['listingId'] == "") {

        $openBidId = $bidId;

        //Get the Open Bid first
        $openBidInfo = $this->BidMarket->get_binding_bid_details($openBidId);

        //If direct Open Bid (not a Buy Order)
        if($this->CreditListings->update_openbid($openBidId, $openBidInfo['bidId'], $openBidInfo['listingId'])) {

          $this->cisession->set_userdata($newdata);

          //array with data: taxpayerID, amount

          //If buying on behalf of legal entities (single or multi-buyer) or else on behalf of self
          $taxEntityData = json_decode($this->input->post('taxEntityDataFinal'));
          if(sizeof($taxEntityData) > 0) {
            //get pre-existing transactions
            $existingTrans = $this->Trades->get_transactions_of_bid($openBidInfo['bidId']);
            $keepTransactions = [];
            $removeTransactions = $existingTrans;

            //loop through new transactions and edit them or add them
            foreach($taxEntityData as $ted) {
              //If editing an existing transaction
              if($ted->transactionId != '') {
                $this->Trades->update_pending_transaction($ted->transactionId, $ted->creditAmount, 0);
                //Else if new transaction
              } else {
                $this->Trading->add_pending_transaction($openBidId, $this->cisession->userdata('userId'), $ted->taxpayerId, $ted->creditAmount, '');
              }

              $x = 0;
              while($x < sizeof($existingTrans)) {
                if($ted->transactionId == $existingTrans[$x]['transactionId']) {
                  //remove $et from $existingTrans
                  unset($removeTransactions[$x]);
                }
                $x++;
              }
            }

            //Delete transactions
            foreach($removeTransactions as $rt) {
              $this->Trades->delete_transaction($rt['transactionId']);
            }

          }

          //send email
          $emailData = $openBidInfo;
          $emailData['updateType'] = 'openBidUpdate';
          $emailData['updateTypeName'] = 'Open Bid Updated';
          $emailData['members'] = $this->BidMarket->get_buyer_in_openbid($openBidId);
          $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'] . ' - ' . $openBidInfo["State"] . $openBidInfo["listingId"] . '-' . $openBidId, $this->config->item("oix_admin_emails_array"), $emailData);

          //Insert BID MODIFIED notification to database
          /*
					$nData['nType'] = 'openbid_modified';
					$nData['nMembers'] = 'active_all_notifications_on';
					$nData['nGreeting'] = 0;
					$nData['nSignature'] = 0;
					$nData['nButton'] = 1;
					$nData['nActivityId'] = $openBidId;
					$nData['nSubject'] = $openBidInfo["State"].' '.$openBidInfo["taxYear"].' Open Bid Modified on the OIX';
					$nData['nHeadline'] = str_replace('%20', ' ', 'Open Bid Modified on the OIX');
					$this->Members_Model->insert_notification($nData);
					*/

          //redirect to see the  bid
          redirect('dashboard/openbid/' . $openBidInfo['listingId']);

        }
      }

    }

  }

  function bid_delete() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['bidId']) && empty($_POST['openBidId'])) {
        redirect('/marketplace');
      } else {

        //If deleting a BID (bid id is not empty, but Opend Bid ID is empty)
        if(!empty($_POST['bidId']) && empty($_POST['openBidId'])) {

          //CHECK PERMISSION (if not owner, then kick out)
          $this->memberpermissions->checkBidAccess($_POST['bidId'], 0, 'bidManagerOnly');

          $bidInfo = $this->BidMarket->get_bid_by_id($_POST['bidId']);

          $transactions = $this->Trades->get_transactions_of_bid($_POST['bidId']);

          if(sizeof($transactions) > 0) {
            foreach($transactions as $t) {
              $this->Trades->delete_transaction($t['transactionId']);
            }
          }

          $this->BidMarket->delete_bid();

          $this->session->set_flashdata('messageDeleteBidSuccess', 1);

          //send email
          $newdata = [
              'bidId'       => $_POST['bidId'],
              'listingId'   => $bidInfo['listingId'],
              'currentPct'  => $bidInfo['bidPrice'],
              'currentSize' => $bidInfo['bidSize'],
              'offerPcnt'   => $bidInfo['offerPrice'],
              'offerSize'   => $bidInfo['offerSize'],
              'lots'        => $bidInfo['allOrNone'],
              'minAmount'   => $bidInfo['minAmount'],
          ];

          $creditData = $this->CreditListings->get_active_listing($_POST['listingId']);

          $emailData = $newdata;
          $emailData['updateType'] = 'bidDeleted';
          $emailData['updateTypeName'] = 'Bid Deleted';
          $emailData['members'] = $this->BidMarket->get_buyer_seller_in_bid($newdata['bidId']);
          $emailData['credit'] = $creditData;
          $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'] . ' - ' . $emailData['credit']['state'] . $newdata['listingId'] . '-' . $newdata['bidId'], $this->config->item("oix_admin_emails_array"), $emailData);

          //Add deleted bid to audit trail
          $this->AuditTrail->insert_audit_item(85, $newdata['listingId'], "$" . number_format($bidInfo['bidSize']) . " at $" . number_format($bidInfo['bidPrice'], 4) . " (" . $emailData['members']['accountTitleBuyer'] . ")", "$0", "", "", "", "", "", "", $_POST['bidId']);

          //Insert BID DELETED notification to database
          /*
					$nData['nType'] = 'bid_deleted';
					$nData['nMembers'] = 'active_all_notifications_on';
					$nData['nGreeting'] = 0;
					$nData['nSignature'] = 0;
					$nData['nButton'] = 1;
					$nData['nActivityId'] = $_POST['bidId'];
					$nData['nSubject'] = $creditData["name"].' '.$creditData["taxYear"].' Bid Deleted on the OIX';
					$nData['nHeadline'] = str_replace('%20', ' ', 'Bid Deleted on the OIX');
					$this->Members_Model->insert_notification($nData);
					*/

        } //ELSE IF deleting an OPEN BID (both Open Bid and Bid Ids are NOT empty)
        else {
          if(!empty($_POST['openBidId']) && !empty($_POST['bidId'])) {

            $openBidInfo = $this->BidMarket->get_binding_bid_details($_POST['openBidId']);

            $transactions = $this->Trades->get_transactions_of_bid($openBidInfo['bidId']);

            //send email
            $newdata = [
                'openBid'     => $_POST['openBidId'],
                'currentPct'  => $openBidInfo['bidPrice'],
                'currentSize' => $openBidInfo['bidSize'],
                'lots'        => $openBidInfo['allOrNone'],
                'minAmount'   => $openBidInfo['minimumCreditIncrement'],
            ];

            $emailData = $newdata;
            $emailData['openBidInfo'] = $openBidInfo;
            $emailData['updateType'] = 'openBidDeleted';
            $emailData['updateTypeName'] = 'Open Bid Deleted';
            $emailData['members'] = $this->BidMarket->get_buyer_in_openbid($_POST['openBidId']);
            $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'] . ' - ' . $openBidInfo['State'] . $openBidInfo['listingId'] . '-' . $openBidInfo['openBidId'], $this->config->item("oix_admin_emails_array"), $emailData);

            //Insert BID DELETED notification to database
            /*
					$nData['nType'] = 'bid_deleted';
					$nData['nMembers'] = 'active_all_notifications_on';
					$nData['nGreeting'] = 0;
					$nData['nSignature'] = 0;
					$nData['nButton'] = 1;
					$nData['nActivityId'] = $_POST['bidId'];
					$nData['nSubject'] = $creditData["name"].' '.$creditData["taxYear"].' Bid Deleted on the OIX';
					$nData['nHeadline'] = str_replace('%20', ' ', 'Bid Deleted on the OIX');
					$this->Members_Model->insert_notification($nData);
					*/

            if(sizeof($transactions) > 0) {
              foreach($transactions as $t) {
                $this->Trades->delete_transaction($t['transactionId']);
              }
            }

            $this->BidMarket->delete_openbid();

            $this->session->set_flashdata('messageDeleteBidSuccess', 1);

          }
        }

        redirect('/dashboard/buying');

      }

    }

  }

  function trade_delete($tradeId) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if($tradeId > 0) {

        //get trade
        $tradeData = $this->Trading->get_trade($tradeId);
        $tradeData = $tradeData['trade'];
        //get credit
        $creditData = $this->CreditListings->get_credit_private($tradeData['listingId']);
        //CHECK ACCESS ON CREDIT
        if($tradeData['tradeType'] == "oix_marketplace_trade") {
          if(!$this->cisession->userdata('isAdmin')) {
            redirect('/dashboard');
          }
        }
        $access = $this->memberpermissions->checkCreditAccess($tradeData['listingId']);
        if(!$access['permEdit']) {
          redirect('/dashboard');
        }

        if($tradeData['tradeType'] == "oix_marketplace_trade") {
          //delete bid
          $bidId = $tradeData['bidId'];
          $this->BidMarket->delete_bid($bidId);
        }

        //delete transactions
        $transactions = $this->Trades->get_transactions_of_trade($tradeId);
        if(sizeof($transactions) > 0) {
          foreach($transactions as $t) {
            $this->Trades->delete_transaction($t['transactionId']);
          }
        }

        //delete trade
        $this->Trading->delete_trade($tradeId);

        //increase credit amount
        $this->CreditListings->update_credit_amount_from_trade_modification($tradeData['listingId'], "increase", $tradeData['tradeSize']);

        //VARIANCE - Estimated Credit Value
        if($creditData['trackingVariance'] == 1) {
          //Get new credit estimate at this moment in time
          $newCreditEstimateData = $this->CreditListings->get_credit_estimated_value($tradeData['listingId']);
          $newCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
          $newCreditEstFaceValue = $newCreditEstimateData['amountLocal'];
          $newCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
          $newCreditEstValue = $newCreditEstimateData['amountValueLocal'];
          //Get most recent Variance audit record for this credit to get the "prior" value
          $allCreditEstimates = $this->AuditTrail->get_audit_trail($tradeData['listingId'], 2, '');
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

          $this->AuditTrail->insert_audit_item(2, $tradeData['listingId'], $priorCreditEstPrice, $newCreditEstPrice, $priorCreditEstFaceValue, $newCreditEstFaceValue, $priorCreditEstExchange, $newCreditEstExchange, $priorCreditEstValue, $newCreditEstValue, $tradeId, 'trade_deleted');

        }

        //insert delete audit item
        if($tradeData['tradeType'] == "oix_marketplace_trade" || $tradeData['tradeType'] == "internal_transfer") {
          $buyerName = $tradeData['buyerAccountName'];
        } else {
          if($tradeData['tradeType'] == "external_transfer") {
            $tpAccountType = $tradeData['transactions'][0]['tpAccountType'];
            $buyerName = ($tpAccountType == 2) ? $tradeData['transactions'][0]['tpFirstName'] . " " . $tradeData['transactions'][0]['tpLastName'] : $tradeData['transactions'][0]['tpCompanyName'];
          }
        }
        $this->AuditTrail->insert_audit_item(94, $tradeData['listingId'], "$" . number_format($tradeData['tradeSizeUSD']) . " at $" . number_format($tradeData['tradePrice'], 4) . " (" . $buyerName . ")", "$0", "", "", "", "", "", "", $tradeId);

        //Insert message of deleted trade
        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
        $msType = "update";
        $msAction = "trade_deleted";
        $msListingId = $tradeData['listingId'];
        $msBidId = "";
        $msTradeId = $tradeId;
        $msTitle = "Utilization Deleted (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . " - " . $creditData['stateCertNum'] . $projectNameExt . ")";
        $msTitle2 = "";
        $msTitleShared = $msTitle;
        $msTitleShort = "Utilization Deleted (" . $creditData['state'] . $creditData['listingId'] . "-" . $tradeId . " - " . $creditData['stateCertNum'] . $projectNameExt . ")";
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $this->cisession->userdata('primUserId');
        $secondDmaMainUserId = "";
        $msUserIdCreated = $this->cisession->userdata('userId');
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        if($tradeData['tradeType'] == "oix_marketplace_trade") {
          $emailData['tradeData'] = $tradeData;
          $emailData['creditData'] = $creditData;
          $emailData['updateType'] = 'oixTradeDeleted';
          $emailData['updateTypeName'] = 'OIX Trade Deleted';
          $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'] . ' - ' . $creditData['State'] . $creditData['listingId'] . '-' . $tradeId, $this->config->item("oix_admin_emails_array"), $emailData);
        }

        //Delete any calendar alerts for this trade
        $msAction = ($tradeData['tradeIsEstimated'] == 1) ? "utilization_estimate_update" : "utilization_actual_update";
        $this->Members_Model->search_and_delete_alert_messages($msAction, $tradeData["listingId"], $tradeId);

        //redirect to the credit details page with message overlay
        $this->session->set_flashdata('messageDeleteTradeSuccess', 1);
        redirect("/dashboard/credit/" . $tradeData['listingId']);

      }

    }

  }

  function credit_delete() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['listingId'])) {
        redirect('/dashboard');
      } else {

        $creditData = $this->CreditListings->get_credit_private($_POST['listingId']);
        $permissions = $this->memberpermissions->checkCreditAccess($_POST['listingId']);

        //CHECK PERMISSION (only credit owner's (i) account admin and (ii) loader of the credit can delete // or advisor)
        if(($this->cisession->userdata('userId') == $this->cisession->userdata('primUserId') || $this->cisession->userdata('userId') == $creditData['cDmaMemberId']) || ($permissions['permEdit'] && $this->cisession->userdata('dmaType') == 'advisor')) {

          $this->CreditListings->softDeleteDataPointValues('credit', $_POST['listingId']);

          //Delete Credit
          $this->CreditListings->delete_credit($_POST['listingId']);

          //Delete all bids of listing
          $this->BidMarket->delete_all_bids_against_credit($_POST['listingId']);

          $allTrades = $this->Trading->get_trades_lite($_POST['listingId'], "all", "", "1");
          foreach($allTrades as $currentTrade) {
            $this->CreditListings->softDeleteDataPointValues('utilization', $currentTrade['tradeId']);
          }

          //Delete all trades of listing
          $this->Trading->delete_all_trades_against_credit($_POST['listingId']);

          //Delete all alert messages of listing
          $this->Members_Model->delete_all_credit_messages($_POST['listingId']);

          //Workflow
          $workflow = $this->Workflow->get_workflow('', 'credit', $_POST['listingId'], 'workflow');
          //If workflow...
          if(sizeof($workflow) > 0) {
            //Remove it from the listing
            $this->CreditListings->update_pending_listing_field($_POST['listingId'], 'cWorkflowId', null);
            //Delete all workflows of listing and unassign any that are assigned
            //Delete all the workflow item values attached to this listing (so if you re-add this worklfow template, they dont show again)
            $workflowItemValuesToDelete = $this->Workflow->get_workflow_item_values('credit', 'workflow', $_POST['listingId']);

            $workflowItemValuesIdsToDelete = [];
            foreach($workflowItemValuesToDelete as $item) {
              $this->Workflow->unassign_workflow_item($item['wvId']);
              array_push($workflowItemValuesIdsToDelete, $item['wvId']);
            }
            $this->Workflow->delete_workflow_item_values($workflowItemValuesIdsToDelete);

          }

          //Compliance
          $compliance = $this->Workflow->get_workflow('', 'credit', $_POST['listingId'], 'compliance');
          //If workflow...
          if(sizeof($workflow) > 0) {

            //Get the template data
            $this->CreditListings->update_pending_listing_field($_POST['listingId'], 'cComplianceId', null);

            //Delete all the workflow item values attached to this listing (so if you re-add this worklfow template, they dont show again)
            $workflowItemValuesToDelete = $this->Workflow->get_workflow_item_values('credit', 'compliance', $_POST['listingId']);

            $workflowItemValuesIdsToDelete = [];
            foreach($workflowItemValuesToDelete as $item) {
              $this->Workflow->unassign_workflow_item($item['wvId']);
              array_push($workflowItemValuesIdsToDelete, $item['wvId']);
            }
            $this->Workflow->delete_workflow_item_values($workflowItemValuesIdsToDelete);

          }

          $this->session->set_flashdata('messageDeleteCreditSuccess', 1);

          //Add deleted credit to audit trail (for records)
          $this->AuditTrail->insert_audit_item(86, $creditData['listingId'], "", "", "", "", "", "", "", "", "");

          //Centralized function to clean/prepare filter data
          $preparedFilterData = $this->CreditListings->prepareFilterData();
          //Centralized function build filter query parameters
          $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
          //Update cache for this credit
          $cData['listingId'] = $creditData['listingId'];
          $this->CreditListings->update_credit_cache($cData);

        }

      }

      redirect('/dashboard');

    }

  }

  function credit_archive() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['listingId'])) {
        redirect('/dashboard');
      } else {

        $creditData = $this->CreditListings->get_credit_private($_POST['listingId']);
        $permissions = $this->memberpermissions->checkCreditAccess($_POST['listingId']);

        //CHECK PERMISSION (only credit owner's (i) account admin and (ii) loader of the credit can delete // or advisor)
        if(($this->cisession->userdata('userId') == $this->cisession->userdata('primUserId') || $this->cisession->userdata('userId') == $creditData['cDmaMemberId']) || ($permissions['permEdit'] && $this->cisession->userdata('dmaType') == 'advisor')) {

          //Archive Credit
          $this->CreditListings->update_credit_archive_status($_POST['listingId'], 1);

          //Add archived credit to audit trail (for records)
          $this->AuditTrail->insert_audit_item(108, $creditData['listingId'], "", "", "", "", "", "", "", "", "");

        }

      }

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $creditData['listingId'];
      $this->CreditListings->update_credit_cache($cData);

      $this->session->set_flashdata('successMessage', 'archive_success');

      //Redirect back to manage page and give a success overlay
      redirect('/dashboard/credit/' . $_POST['listingId'] . '/manage');

    }

  }

  function credit_unarchive() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['listingId'])) {
        redirect('/dashboard');
      } else {

        $creditData = $this->CreditListings->get_credit_private($_POST['listingId']);
        $permissions = $this->memberpermissions->checkCreditAccess($_POST['listingId']);

        //CHECK PERMISSION (only credit owner's (i) account admin and (ii) loader of the credit can delete // or advisor)
        if(($this->cisession->userdata('userId') == $this->cisession->userdata('primUserId') || $this->cisession->userdata('userId') == $creditData['cDmaMemberId']) || ($permissions['permEdit'] && $this->cisession->userdata('dmaType') == 'advisor')) {

          //Un-Archive Credit
          $this->CreditListings->update_credit_archive_status($_POST['listingId'], 0);

          //Add archived credit to audit trail (for records)
          $this->AuditTrail->insert_audit_item(109, $creditData['listingId'], "", "", "", "", "", "", "", "", "");

        }

      }

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $creditData['listingId'];
      $this->CreditListings->update_credit_cache($cData);

      $this->session->set_flashdata('successMessage', 'unarchive_success');

      //Redirect back to manage page and give a success overlay
      redirect('/dashboard/credit/' . $_POST['listingId'] . '/manage');

    }

  }

  function credit_unlist() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //If user came direct to the page, kick them out
      if(empty($_POST['listingId'])) {
        redirect('/dashboard');
      } else {

        $creditData = $this->CreditListings->get_credit_private($_POST['listingId']);
        $permissions = $this->memberpermissions->checkCreditAccess($_POST['listingId']);

        //CHECK PERMISSION (only credit owner's (i) account admin and (ii) loader of the credit can delete // or advisor)
        if(($this->cisession->userdata('userId') == $this->cisession->userdata('primUserId') || $this->cisession->userdata('userId') == $creditData['cDmaMemberId']) || ($permissions['permEdit'] && $this->cisession->userdata('dmaType') == 'advisor')) {

          //Unlist Credit
          $this->CreditListings->unlist_credit($_POST['listingId']);

          //Delete all outstanding bids against this credit
          $bidsOutstanding = $this->CreditListings->get_market_on_listing_new($_POST['listingId']);
          foreach($bidsOutstanding as $bo) {

            //Mark Bid deleted
            $this->BidMarket->delete_bid($bo['bidId']);

            //Insert alert for bid deleted
            if($this->cisession->userdata('level') == 4) {

              //Insert message
              $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
              $msType = "update";
              $msAction = "bid_deleted";
              $msListingId = $_POST['listingId'];
              $msBidId = $bo['bidId'];
              $msTradeId = "";
              $msTitle = "Cancelled Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $bo['bidId'] . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " - $" . number_format($bo['bidSize']) . " @ $" . number_format($bo['bidPrice'], 4);
              $msTitle2 = "Cancelled Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $bo['bidId'] . ") on Credit " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ") - $" . number_format($bo['bidSize']) . " @ $" . number_format($bo['bidPrice'], 4);
              $msTitleShared = $msTitle2;
              $msTitleShort = "Cancelled Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $bo['bidId'] . ") - $" . number_format($bo['bidSize']) . " @ $" . number_format($bo['bidPrice'], 4);
              $msTitle2Short = "Cancelled Bid (" . $creditData['state'] . $creditData['listingId'] . "-" . $bo['bidId'] . ") - $" . number_format($bo['bidSize']) . " @ $" . number_format($bo['bidPrice'], 4);
              $msTitleSharedShort = $msTitle2Short;
              $msContent = "";
              $msContent2 = "";
              $msPerspective = "buyer";
              $msPerspective2 = "seller";
              $firstDmaMainUserId = $creditData['listedBy'];
              $secondDmaMainUserId = $bo['accountId'];
              $msUserIdCreated = $this->cisession->userdata('userId');
              $alertShared = true;
              $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);

            }

            //Email main buyer for bid deleted
            $emailData['updateType'] = "bid_deleted_credit_unlisted";
            $emailData['firstName'] = ($bo['bDmaMemberId'] > 0) ? $bo['subBuyerFirstName'] : $bo['firstName'];
            $emailData['email'] = ($bo['bDmaMemberId'] > 0) ? $bo['subBuyerEmail'] : $bo['email'];
            $emailData['headline'] = 'Bid%20Deleted';
            $emailData['emailSubject'] = "Bid Deleted (" . $creditData['state'] . $creditData['listingId'] . "-" . $bo['bidId'] . ")";
            $emailData['introHTML'] = "The tax credit " . $creditData['state'] . $creditData['listingId'] . " has been rmoved removed from the OIX Marketplace. Therefore, your bid against this credit (detailed below) has been automatically cancelled. <br><br>";
            $emailData['bid'] = $bo;
            $emailData['bid']['state'] = $creditData['state'];
            $emailData['bid']['name'] = $creditData['name'];
            $emailData['bid']['taxYear'] = $creditData['taxYear'];
            $emailData['greeting'] = 1;
            $emailData['button'] = 0;
            $emailData['signature'] = 1;
            $this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $emailData['email'], $emailData);

            //Add audit trail for bid deleted
            $this->AuditTrail->insert_audit_item(85, $creditData['listingId'], "$" . number_format($bo['bidSize']) . " at $" . number_format($bo['bidPrice'], 4) . " (" . $bo['title'] . ")", "$0", "", "", "", "", "", "", $bo['bidId']);

          }

          //Insert alert for credit unlisted unlisted
          if($this->cisession->userdata('level') == 4) {

            //Insert message
            $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
            $msType = "update";
            $msAction = "credit_unlisted";
            $msListingId = $creditData['listingId'];
            $msBidId = "";
            $msTradeId = "";
            $msTitle = "Credit Unlisted from OIX Marketplace - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
            $msTitle2 = "";
            $msTitleShared = $msTitle;
            $msTitleShort = "Credit Unlisted from OIX Marketplace - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
            $msTitle2Short = "";
            $msTitleSharedShort = $msTitle2Short;
            $msContent = "";
            $msContent2 = "";
            $msPerspective = "seller";
            $msPerspective2 = "";
            $firstDmaMainUserId = $creditData['listedBy'];
            $secondDmaMainUserId = "";
            $msUserIdCreated = $this->cisession->userdata('userId');
            $alertShared = true;
            $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);

          }

          //Add unlisted credit to audit trail (for records)
          $this->AuditTrail->insert_audit_item(32, $creditData["listingId"], "(none)", "$" . number_format($creditData['offerSize']), "", "", "", "", "", "", "");

          //send OIX Admin email
          $emailData['updateType'] = 'creditUnlisted';
          $emailData['updateTypeName'] = 'Credit Unlisted';
          $emailData['listingId'] = $creditData['listingId'];
          $emailData['state'] = $creditData['state'];
          $emailData['offerPcnt'] = $creditData['offerPcnt'];
          $emailData['offerSize'] = $creditData['offerSize'];
          $emailData['allOrNone'] = $creditData['allOrNone'];
          $emailData['minAmount'] = $creditData['incrementAmount'];
          $emailData['OfferGoodUntil'] = $creditData['OfferGoodUntil'];
          $emailData['modifications'] = "";
          $emailData['members'] = $this->CreditListings->get_seller_of_credit($creditData['listingId']);
          $emailData['credit'] = $creditData;
          $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);
        }

      }

      $this->session->set_flashdata('messageUnlistCreditSuccess', 1);

      //Redirect back to manage page and give a success overlay
      redirect('/dashboard/credit/' . $_POST['listingId'] . '/manage');

    }

  }

  function record_trade() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['tabArray'] = "record_trade";
    $data['current_tab_key'] = "";
    $data['lnav_key'] = "record_trade";

    $cData['id'] = $this->cisession->userdata('primUserId');
    $cData['dmaId'] = $this->cisession->userdata('dmaId');
    $cData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
    //If Advisor
    //$cData['advisorStatus'] = preg_replace("/[^a-zA-Z0-9_]+/", "", $this->input->post('listAdvisorStatus'));

    $credits = $this->CreditListings->get_credits($cData);
    $data['credits'] = $credits['credits'];

    $this->load->view('includes/left_nav', $data);
    $this->load->view('marketplace/record_trade', $data);
    $this->load->view('includes/footer-2');

  }

  function rules($chapter = "") {
    if(!empty($chapter)) {
      $data['chapter'] = $chapter;
    } else {
      $data['chapter'] = "all";
    }
    $this->load->view("marketplace/rules", $data);

  }

  function getIncentiveProgram($dataIpdb) {

    /*****************  Get the Incentive Program In the drop down  (Start)*****************/
    $getipdbProgram = [];
    $getIpdbProUnique = [];
    $getIpdbProgramDropDown = [];
    $incentiveProgram = [];
    foreach($dataIpdb as $getProgram) {
      array_push($getipdbProgram, $getProgram['OIXIncentiveId']);
    }
    $getIpdbProUnique = array_unique($getipdbProgram);

    $incentiveProgram[0] = "All Incentive Programs";
    foreach($getIpdbProUnique as $getIpdbProUni) {
      if(trim($getIpdbProUni) != null) {
        $invProgram = $this->IncentivePrograms->get_active_programs_by_OIXIncentiveId($getIpdbProUni);
        if(isset($invProgram[$getIpdbProUni]) && $invProgram[$getIpdbProUni] != null) {
          $incentiveProgram[$getIpdbProUni] = $invProgram[$getIpdbProUni];
        }
      }
    }

    return $incentiveProgram;
    /*****************  Get the Incentive Program In the drop down  (End)*****************/

  }

  /*
    // Show Uploader by passing state name
    function show_uploader_new($val,$statename,$listingId,$page="") {

        // Get all upload types
        $upload_types = $this->uploadingmodel->get_upload_types();

        // At Load A Credit(LAC) firstly create Directories
        if($page != "" && $page == "LAC"){
            $issueId = $statename.$listingId;
            //$upload_path = $this->config->item('base_dir').$this->config->item('uploads_dir');
            $upload_path = $this->config->item('uploads_dir');
            $this->filemanagementlib->createDirectoryStructure($issueId,$upload_path,$upload_types);

            // Sync Folders in cmsadmin as well
            $foldername = array();
            $inc = 0;
            foreach($upload_types as $folder){
                $foldername[$inc]['folderName'] = $folder;
                $inc++;
            }

            $pendings = array(array('listingId' => $listingId, 'stateCode' => $statename));

            $folderData = array(
                'pendingListings' => $pendings,
                'folders' => $foldername,
            );

            $paths = array(
                0 => array(
                    "filePath"=>$this->config->item("cmsadmin_doc_repo_dir")
                )
            );

            //$appId = $this->members_model->getAppIdAgainstUserId($usersData['listedBy']);
            $sourceAppId = $this->config->item('app_id');
            $targetAppId = $this->config->item('app_id');
            $restResponse = $this->filemanagementlib->createDocs($folderData, $sourceAppId, $targetAppId, $paths);
            //$restResponse = $this->addActiveCreditDoc($remoteData, $sourceAppId, $targetAppId);
            //echo "\r\n" . $restResponse;
        }

        $data['val']=$val;
        $data['statename']=$statename;
        $data['listingId']=$listingId;
        $data['page']=$page;
        $data['categories'] = $upload_types;
        $this->load->view('admin/fileManagement/uploader',$data);
    }

    function show_tree_docs($statename,$listingId,$page="") {
        $data['statename']=$statename;
        $data['listingId']=$listingId;
        $data['page'] = $page;
        $this->load->view('admin/fileManagement/tree_docs',$data);
    }
*/

}




/* End of file marketplace.php */
/* Location: ./application/controllers/marketplace.php */
