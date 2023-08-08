<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Errors extends CI_Controller {
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
    $this->load->model('Members_Model');
    $this->load->model('Email_Model');
  }

  function index() {
    $this->output->set_status_header('404');
    $data['content'] = 'error_404'; // View name
    $this->load->view('includes/header', $data);
    $this->load->view("error/404", $data);
    $this->load->view('includes/footer', $data);
  }

  function error_404() {
    $this->output->set_status_header('404');
    $data['content'] = 'error_404'; // View name
    $this->load->view('includes/header', $data);
    $this->load->view("error/404", $data);
    $this->load->view('includes/footer', $data);
  }

  function error_500() {
    $this->output->set_status_header('500');
    $data['content'] = 'error_500'; // View name
    $this->load->view('includes/header', $data);
    $this->load->view("error/500", $data);
    $this->load->view('includes/footer', $data);
  }
}





/* End of file errors.php */
/* Location: ./application/controllers/errors.php */
