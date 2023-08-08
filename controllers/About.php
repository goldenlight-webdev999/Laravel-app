<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class About extends CI_Controller {

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }

  }

  function index() {
    redirect($this->config->item('oix_website_url'));
  }

  function leadership() {

    redirect($this->config->item('oix_website_url') . "about/leadership");

  }

  function faq() {

    redirect($this->config->item('oix_website_url') . "about/faq");
  }

  function privacy_policy() {
    redirect($this->config->item('oix_website_url') . "about/privacy_policy");
  }

  function terms_of_use() {
    redirect($this->config->item('oix_website_url') . "about/terms_of_use");
  }

  function press() {

    redirect($this->config->item('oix_website_url') . "about/press");

  }

  function tedx_2018() {

    redirect($this->config->item('oix_website_url') . "about/tedx_2018");

  }

  function act_webinar_10_2018() {

    redirect($this->config->item('oix_website_url') . "about/act_webinar_10_2018");

  }

  function fasb() {

    redirect($this->config->item('oix_website_url') . "about/fasb");
  }

  function contact() {

    redirect($this->config->item('oix_website_url') . "about/contact");

  }

  function contact_received() {

    redirect($this->config->item('oix_website_url') . "about/contact_received");

  }

  function support() {
    redirect('/about/contact');

  }

  function webinar($webinarId) {

    redirect('/about/press');

  }

}





/* End of file admin.php */
/* Location: ./application/controllers/admin/admin.php */
