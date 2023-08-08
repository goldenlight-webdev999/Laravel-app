<?php
if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Advisor extends CI_Controller {
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
    $this->load->model('Trading');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Taxpayersdata');
    $this->load->model('Email_Model');
    $this->load->model('AuditTrail');

    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');

  }

  function projects($page) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['tabArray'] = "advisor";
    $data['lnav_key'] = "";

    //Page config
    $data['page'] = $page;
    if($page == "pending" || $page == "") {
      $data['current_tab_key'] = "projects_pending";
      $advisorStatus = 1;
    } else {
      if($page == "active") {
        $data['current_tab_key'] = "projects_active";
        $advisorStatus = 2;
      } else {
        if($page == "completed") {
          $data['current_tab_key'] = "projects_completed";
          $advisorStatus = 3;
        } else {
          if($page == "cancelled") {
            $data['current_tab_key'] = "projects_cancelled";
            $advisorStatus = 9;
          }
        }
      }
    }

    //GET PROJECTS
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    $preparedFilterData['advisorStatus'] = $advisorStatus;
    $preparedFilterData['order'] = "seller_a_z";
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    $credits = $this->CreditListings->get_credits($cData);
    $data['projects'] = $credits['credits'];
    $data['projectsSummary'] = $credits['summary'];

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    $this->load->view('advisor/projects', $data);
    $this->load->view('includes/footer-2', $data);

  }

  function project_add() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['tabArray'] = "advisor";
    $data['current_tab_key'] = "project_add";
    $data['lnav_key'] = "";

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    $this->load->view('advisor/project_add', $data);
    $this->load->view('includes/footer-2', $data);

  }

}




/* End of file admin.php */
/* Location: ./application/controllers/admin/incentive_programs.php */
