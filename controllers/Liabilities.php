<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Liabilities extends CI_Controller {
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
    $this->load->model('Taxpayersdata');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Workflow');
    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');

  }

  function index() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['lnav_key'] = "liabilities";
    $data['tabArray'] = "liabilities";
    $data['current_tab_key'] = "";

    $tlRequest['tlDmaId'] = $this->cisession->userdata('dmaId');
    $data['liabilities'] = $this->CreditListings->get_liabilities($tlRequest);

    if(in_array('taxLiabilities', $this->cisession->userdata('accessParentDataConfig'))) {

      $tlsRequest['tlDmaId'] = $this->cisession->userdata('parentDmaId');
      $parentLiabilities = $this->CreditListings->get_liabilities($tlsRequest);
      $data['liabilities'] = array_merge($data['liabilities'], $parentLiabilities);

      usort($data['liabilities'], function($a, $b) {
        return $a['taxYear'] - $b['taxYear'];
      });

    }

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['liabilityTracker']) && !isset($data['planData']['allFeatures'])) {
      $data['tabArray'] = "liabilities_noaccess";
    }

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    if(!isset($data['planData']['liabilityTracker']) && !isset($data['planData']['allFeatures'])) {
      $this->load->view('includes/widgets/planBlockMessage');
    } else {
      $this->load->view("liabilities/all", $data);
    }
    $this->load->view('includes/footer-2', $data);

  }

  function add_edit_liability($tlId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['lnav_key'] = "liabilities";

    $data['tlId'] = $tlId;

    //if ID exists, were editing an existing liability
    if($tlId > 0) {
      $tlRequest['tlId'] = $tlId;
      $data['taxLiability'] = $this->CreditListings->get_tax_liability($tlRequest);
      if($data['taxLiability']['tlDmaId'] != $this->cisession->userdata('dmaId')) {
        redirect('/dashboard');
      }
    }

    $data['taxpayers'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 1, 1, 1, 1, 1);
    $data['taxYears'] = $this->IncentivePrograms->get_taxyear_by_active_program();
    $data['taxYears'][0] = "-";
    $data['jurisdictions'] = $this->IncentivePrograms->get_only_states_by_country(1);

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['liabilityTracker']) && !isset($data['planData']['allFeatures'])) {
      $data['tabArray'] = "liabilities_noaccess";
    }

    $this->load->view('includes/left_nav', $data);
    if(!isset($data['planData']['liabilityTracker']) && !isset($data['planData']['allFeatures'])) {
      $this->load->view('includes/widgets/planBlockMessage');
    } else {
      $this->load->view("liabilities/add_edit_liability", $data);
    }
    $this->load->view('includes/footer-2', $data);

  }

  function save_liability($tlId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['tlId'] = $tlId;

    //if ID exists, let's save it
    if($tlId > 0) {

      //First, get the existing liability
      $tlRequest['tlId'] = $tlId;
      $taxLiabilityPrior = $this->CreditListings->get_tax_liability($tlRequest);
      if($taxLiabilityPrior['tlDmaId'] != $this->cisession->userdata('dmaId')) {
        redirect('/dashboard');
      }

      //Check if DMA account matches
      if($taxLiabilityPrior['tlDmaId'] == $this->cisession->userdata('dmaId')) {

        //if does, check if change has occured
        $numChanges = 0;
        if($taxLiabilityPrior['tlTaxpayerId'] != $this->input->post('tlTaxpayerId')) {
          $numChanges++;
        }
        if($taxLiabilityPrior['amount'] != $this->input->post('amount')) {
          $numChanges++;
        }
        if($taxLiabilityPrior['taxOffset'] != $this->input->post('taxOffset')) {
          $numChanges++;
        }
        if($taxLiabilityPrior['jurisdiction'] != $this->input->post('jurisdiction')) {
          $numChanges++;
        }
        if($taxLiabilityPrior['tax_year'] != $this->input->post('tax_year')) {
          $numChanges++;
        }

        //if so, then save it
        if($numChanges > 0) {

          //Insert new tax liability
          $this->CreditListings->save_tax_liability($tlId);

          //Add an alert for this and shared accounts

        }

      }

    } else {

      //insert liability
      $tlId = $this->CreditListings->insert_tax_liability();

      //add an alert for this and shared accounts

    }

    //Prepare success message
    $this->session->set_flashdata('saveLiabilitySuccessMessage', 1);

    //Redirect
    redirect('/liabilities');

  }

}
