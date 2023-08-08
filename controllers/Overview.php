<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Overview extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
  }

  //THIS IS A DEMO PAGE ONLY FOR DEV
  function welcome_customer($customer) {

    if($this->config->item('environment') == "LIVE") {
      redirect("/");
    }

    if($customer == "cohn-reznick") {
      $data['customer']['url'] = "cohn-reznick";
      $data['customer']['name'] = "Cohn Reznick";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Joseph Sloan";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Research Activities";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "adp") {
      $data['customer']['url'] = "adp";
      $data['customer']['name'] = "ADP";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Joseph Sloan";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Research Activities";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "cbre") {
      $data['customer']['url'] = "cbre";
      $data['customer']['name'] = "CBRE";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Joseph Sloan";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Research Activities";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "bakertilly") {
      $data['customer']['url'] = "baker-tilly";
      $data['customer']['name'] = "Baker Tilly";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Joseph Sloan";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Research Activities";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "jll") {
      $data['customer']['url'] = "jll";
      $data['customer']['name'] = "Jones Lang LaSalle";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Joseph Sloan";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Research Activities";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "kpmg") {
      $data['customer']['url'] = "kpmg";
      $data['customer']['name'] = "KPMG";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Joseph Sloan";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Research Activities";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "ey") {
      $data['customer']['url'] = "ey";
      $data['customer']['name'] = "EY";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['representative'] = "Rebecca Glazer";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Downtown Reconstruction Development";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }
    if($customer == "deloitte") {
      $data['customer']['url'] = "deloitte";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['name'] = "Deloitte Touche Tohmatsu Limited";
      $data['customer']['representative'] = "Jeff Dunham";
      $data['customer']['projectName'] = "iProcessor 3.0";
    }
    if($customer == "global-incentives") {
      $data['customer']['url'] = "global-incentives";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['name'] = "Global Incentives";
      $data['customer']['representative'] = "Len Pendergast";
      $data['customer']['projectName'] = "Yukon Ho!";
    }
    if($customer == "bennett-thrasher") {
      $data['customer']['url'] = "bennett-thrasher";
      $data['customer']['customerName'] = "Poland Spring Water";
      $data['customer']['name'] = "Bennett Thrasher";
      $data['customer']['representative'] = "Michael Holmes";
      $data['customer']['programName'] = "Research & Delevopment";
      $data['customer']['projectName'] = "Yukon Ho!";
      $data['customer']['jurisdiction'] = "Pennsylvania";
    }

    $this->load->view("overview/welcome_customer", $data);

  }

  function index() {

    redirect($this->config->item('oix_website_url'));
  }

  function why_oix() {

    redirect($this->config->item('oix_website_url') . "overview/why_oix");

  }

  function products() {

    redirect($this->config->item('oix_website_url') . "overview/products");

  }

  function platform() {

    redirect($this->config->item('oix_website_url') . "overview/platform");

  }

  function security() {

    redirect($this->config->item('oix_website_url') . "overview/security");

  }

  function get_started() {
    redirect("/about/contact");
  }

}



/* End of file admin.php */
/* Location: ./application/controllers/admin/admin.php */
