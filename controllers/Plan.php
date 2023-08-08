<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Plan extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('DmaAccounts');
    $this->load->model('CreditListings');
    $this->load->model('IncentivePrograms');
  }

  function index() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "";
      $data['tabArray'] = "";
      $data['current_tab_key'] = "";

      $data['planData'] = $this->cisession->userdata('planData');

      $this->load->view('includes/left_nav', $data);
      //$this->load->view('includes/tab_nav', $data);
      $this->load->view("plan/index");
      $this->load->view('includes/footer-2', $data);

    }
  }

}





/* End of file help.php */
/* Location: ./application/controllers/help.php */
