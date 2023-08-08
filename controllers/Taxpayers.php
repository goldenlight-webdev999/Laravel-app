<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Taxpayers extends CI_Controller {
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
    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');

  }

  function index() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "taxpayers";
      $data['current_tab_key'] = "taxentities";
      $data['lnav_key'] = "administrative";;

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayers'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 1, 1, 1, 1, 1);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("taxpayers/all", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function add() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityCreate();

      $data['tabArray'] = "taxpayers";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "administrative";;

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);

      $this->load->view('includes/left_nav', $data);
      $this->load->view("taxpayers/add", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function insert() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->memberpermissions->checkTaxentityCreate();

    //Check this isn't an empty post
    $tpCompanyName = $this->input->post('tpCompanyName');
    $tpFirstName = $this->input->post('tpFirstName');
    $tpLastName = $this->input->post('tpLastName');
    if($tpCompanyName == '' && ($tpFirstName == '' || $tpLastName == '')) {
      redirect('taxpayers');
    }

    $newTaxpayerId = $this->Taxpayersdata->insert_taxpayer();

    $this->session->set_flashdata('insertSuccess', 1);
    redirect('taxpayers/entity/' . $newTaxpayerId);

  }

  function entity($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "details";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['tpSelling'] = $this->CreditListings->get_credits_of_taxpayer($taxpayerId, 3, "", $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));
      $data['tpSold'] = $this->CreditListings->get_taxpayer_sales_sold($taxpayerId, $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));
      $data['tpPortfolio'] = $this->CreditListings->get_credits_of_taxpayer($taxpayerId, "", "", $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));
      $data['pendingPurchases'] = $this->Trading->get_trade_transactions_of_taxpayer($taxpayerId, 'pending', 'yes', $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/entity", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function entity_preview($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $this->load->view("taxpayers/entity_preview", $data);

    }
  }

  function edit($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 0, 0);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/add", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function save($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId);

      //Check this isn't an empty post
      $tpCompanyName = $this->input->post('tpCompanyName');
      $tpFirstName = $this->input->post('tpFirstName');
      $tpLastName = $this->input->post('tpLastName');
      if($tpCompanyName == '' && ($tpFirstName == '' || $tpLastName == '')) {
        redirect('taxpayers');
      }

      //If tax entity name or fiscal year end has changed, then update cache of all credits attached to it
      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);

      //Not update tax entity
      $savedTaxpayerId = $this->Taxpayersdata->save_taxpayer($taxpayerId);

      //If there were changes affecting our core credit object, clear up the cache
      if($data['taxpayer']['tpCompanyName'] != $this->input->post('tpCompanyName') || $data['taxpayer']['tpFirstName'] != $this->input->post('tpFirstName') || $data['taxpayer']['tpLastName'] != $this->input->post('tpLastName') || $data['taxpayer']['tpFiscalYearEndMonth'] != $this->input->post('tpFiscalYearEndMonth') || $data['taxpayer']['tpFiscalYearEndDay'] != $this->input->post('tpFiscalYearEndDay')) {

        //Get all credits attached to this tax entity
        $sanitizedPostData = $this->CreditListings->prepareFilterData();
        $sanitizedPostData['taxEntities'] = $taxpayerId;
        $cData = $this->CreditListings->buildFilterSearchData($sanitizedPostData);

        $creditsData = $this->CreditListings->get_credits($cData);
        $credits = $creditsData['credits'];

        foreach($credits as $c) {

          //Update cache for this credit
          $cData['listingId'] = $c['listingId'];
          $cData['newRecord'] = false;
          $this->CreditListings->update_credit_cache($cData);

        }
      }

      $this->session->set_flashdata('saveSuccess', 1);
      redirect('taxpayers/entity/' . $taxpayerId);

    }

  }

  function buying($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "buying";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['tpBuying'] = $this->Trading->get_bid_transactions_of_taxpayer($taxpayerId, '', 'yes');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/tp_buying", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function purchased($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "purchased";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['tpPurchased'] = $this->Trading->get_trade_transactions_of_taxpayer($taxpayerId, '', 'yes');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/tp_purchased", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function selling($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "selling";
      $data['currentPage'] = "selling";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['activity'] = $this->CreditListings->get_credits_of_taxpayer($taxpayerId, 3, "", $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/tp_selling", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function sold($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "sold";
      $data['pageKey'] = "sold";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['activity'] = $this->CreditListings->get_taxpayer_sales_sold($taxpayerId, $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/tp_sales", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function portfolio($taxpayerId) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $this->memberpermissions->checkTaxentityAccess($taxpayerId, 1);

      $data['lnav_key'] = "administrative";;
      $data['current_tab_key'] = "portfolio";
      $data['currentPage'] = "managing";

      $data['object'] = "taxpayer";
      $data['objectCamel'] = "legal entity";

      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);
      if($data['taxpayer']['tpAccountType'] == 1) {
        $data['isBusiness'] = true;
      } else {
        $data['isBusiness'] = false;
      }

      $data['activity'] = $this->CreditListings->get_credits_of_taxpayer($taxpayerId, "", "", $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/taxpayer_header', $data);
      $this->load->view("taxpayers/tp_selling", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function process_edit_post() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $taxpayerId = $this->input->post('id');

    //Check this isn't an empty post
    $tpCompanyName = $this->input->post('companyName');
    $tpFirstName = $this->input->post('repFirstName');
    $tpLastName = $this->input->post('repLastName');
    if($tpCompanyName == '' && ($tpFirstName == '' || $tpLastName == '')) {
      echo json_encode(['success' => 0, 'id' => 0, 'name' => '']);
    }

    $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
    $dpRequest['orderBySections'] = true;
    $dpRequest['dpObjectType'] = 'legal-entity';
    $customDataPointsRaw = $this->CreditListings->get_data_points($dpRequest);
    $cdpMap = [];
    foreach($customDataPointsRaw['dataPoints'] as $cdp) {
      $cdpMap[$cdp['dpValue']] = ['id' => $cdp['dpId'], 'type' => $cdp['dpType']];
    }

    if($taxpayerId > 0) {
      $this->memberpermissions->checkTaxentityAccess($taxpayerId);

      //If tax entity name or fiscal year end has changed, then update cache of all credits attached to it
      $data['taxpayer'] = $this->Taxpayersdata->get_taxpayer($taxpayerId, 1, 1);

      //Not update tax entity
      $savedTaxpayerId = $this->Taxpayersdata->save_taxpayer($taxpayerId);
      foreach($this->input->post('customDataPoints') as $cdpKey => $cdpVal) {
        if(isset($cdpMap[$cdpKey])) {
          $cdpReq = [
              'dpId'            => $cdpMap[$cdpKey]['id'],
              'listingId'       => $taxpayerId,
              'value'           => null,
              'option_value_id' => null,
          ];
          if($cdpMap[$cdpKey]['type'] == 'selectDropDown') {
            $cdpReq['option_value_id'] = $cdpVal;
          } else if($cdpMap[$cdpKey]['type'] == 'date') {
            $cdpVal = ($cdpVal != "") ? strtotime($cdpVal) : null;
            $cdpReq['value'] = $cdpVal;
          } else if($cdpMap[$cdpKey]['type'] == 'currencyNoDecimal') {
            $cdpVal = (float)preg_replace('/[^0-9.]*/', '', $cdpVal);
            $cdpVal = ($cdpVal == 0) ? null : round($cdpVal);
            $cdpReq['value'] = $cdpVal;
          } else {
            $cdpReq['value'] = $cdpVal;
          }
          $this->CreditListings->update_custom_data_point_value($cdpReq);
        }
      }

      //If there were changes affecting our core credit object, clear up the cache
      if($data['taxpayer']['tpCompanyName'] != $tpCompanyName || $data['taxpayer']['tpFirstName'] != $tpFirstName || $data['taxpayer']['tpLastName'] != $tpLastName || trim($data['taxpayer']['tpFiscalYearEndDay'] . ' ' . $data['taxpayer']['tpFiscalYearEndMonth']) != trim($this->input->post('fiscalYearEnd'))) {

        //Get all credits attached to this tax entity
        $sanitizedPostData = $this->CreditListings->prepareFilterData();
        $sanitizedPostData['taxEntities'] = $taxpayerId;
        $cData = $this->CreditListings->buildFilterSearchData($sanitizedPostData);

        $creditsData = $this->CreditListings->get_credits($cData);
        $credits = $creditsData['credits'];

        foreach($credits as $c) {

          //Update cache for this credit
          $cData['listingId'] = $c['listingId'];
          $cData['newRecord'] = false;
          $this->CreditListings->update_credit_cache($cData);

        }
      }

      $this->session->set_flashdata('saveSuccess', 1);
      echo json_encode(['success' => 1, 'id' => $taxpayerId, 'name' => $tpCompanyName]);
    } else {
      $this->memberpermissions->checkTaxentityCreate();

      //Check this isn't an empty post
      $tpCompanyName = $this->input->post('companyName');
      $tpFirstName = $this->input->post('repFirstName');
      $tpLastName = $this->input->post('repLastName');
      if($tpCompanyName == '' && ($tpFirstName == '' || $tpLastName == '')) {
        redirect('taxpayers');
      }

      $newTaxpayerId = $this->Taxpayersdata->insert_taxpayer();
      foreach($this->input->post('customDataPoints') as $cdpKey => $cdpVal) {
        if(isset($cdpMap[$cdpKey])) {
          $cdpReq = [
              'dpId'            => $cdpMap[$cdpKey]['id'],
              'listingId'       => $newTaxpayerId,
              'value'           => null,
              'option_value_id' => null,
          ];
          if($cdpMap[$cdpKey]['type'] == 'selectDropDown') {
            $cdpReq['option_value_id'] = $cdpVal;
          } else if($cdpMap[$cdpKey]['type'] == 'date') {
            $cdpVal = ($cdpVal != "") ? strtotime($cdpVal) : null;
            $cdpReq['value'] = $cdpVal;
          } else if($cdpMap[$cdpKey]['type'] == 'currencyNoDecimal') {
            $cdpVal = (float)preg_replace('/[^0-9.]*/', '', $cdpVal);
            $cdpVal = ($cdpVal == 0) ? null : round($cdpVal);
            $cdpReq['value'] = $cdpVal;
          } else {
            $cdpReq['value'] = $cdpVal;
          }
          $this->CreditListings->update_custom_data_point_value($cdpReq);
        }
      }

      $this->session->set_flashdata('insertSuccess', 1);

      echo json_encode(['success' => 1, 'id' => $newTaxpayerId, 'name' => $tpCompanyName]);
    }
  }

  function load_edit_form($id = 0) {
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $taxPayer = null;
    if($id > 0) {
      $this->memberpermissions->checkTaxentityAccess($id, 1);
      $taxPayer = $this->Taxpayersdata->get_taxpayer($id, 1, 1);
      $dpRequest['listingId'] = $id;
    } else {
      $this->memberpermissions->checkTaxentityCreate();
    }

    $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
    $dpRequest['orderBySections'] = true;
    $dpRequest['dpObjectType'] = 'legal-entity';
    $customDataPointsRaw = $this->CreditListings->get_data_points($dpRequest);
    $dataPoints = [];
    foreach($customDataPointsRaw['dataPoints'] as $cdp) {
      $dataPoints[$cdp['dpSection']][] = $cdp;
    }

    $data = [
        'googleMapsKey' => $this->config->item('google_maps_key'),
        'fileCacheVer'  => $this->config->item('file_cache_ver'),
        'dataPoints'    => $dataPoints,
        'taxPayer'      => $taxPayer,
        'includeGmaps'  => $this->input->get('includeGmaps') == 'false' ? false : true,
    ];

    $twig = \OIX\Util\TemplateProvider::getTwig();
    $template = $twig->load('taxpayers/edit_form.twig');
    echo $template->render($data);
  }

}
