<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class Help extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('CreditListings');
    $this->load->model('IncentivePrograms');
    $this->load->model('DmaAccounts');
    $this->load->library('filemanagementlib');

  }

  function getstarted() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "support";
      $data['tabArray'] = "tutorials";
      $data['current_tab_key'] = "";

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("help/getstarted");
      $this->load->view('includes/footer-2', $data);

    }
  }

  function support() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "support";

      $data['planData'] = $this->cisession->userdata('planData');

      $dpRequestCredits['getReportMyCredits'] = 1;
      $dpRequestCredits['dpObjectType'] = 'credit';
      $dpRequestCredits['dpDmaId'] = $this->cisession->userdata('dmaId');
      $data['oix_data_points_credits'] = $this->CreditListings->get_data_points($dpRequestCredits);

      $dpRequestUtilizations['getReportTrades'] = 1;
      $dpRequestUtilizations['dpObjectType'] = 'utilization';
      $dpRequestUtilizations['dpDmaId'] = $this->cisession->userdata('dmaId');
      $data['oix_data_points_utilizations'] = $this->CreditListings->get_data_points($dpRequestUtilizations);

      $dpRequestLegalEntities['dpObjectType'] = 'legal-entity';
      $dpRequestLegalEntities['dpDmaId'] = $this->cisession->userdata('dmaId');
      $data['oix_data_points_legalentities'] = $this->CreditListings->get_data_points($dpRequestLegalEntities);

      $this->load->view('includes/left_nav', $data);
      $this->load->view("help/support");
      $this->load->view('includes/footer-2', $data);

    }
  }

  function product_announcement($id) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "support";

      $fileRequest['awsS3FileKey'] = 'product-announcements.json';
      $fileRequest['awsS3BucketName'] = $this->config->item('OIX_AWS_BUCKET_NAME_SENSITIVE_STATIC_FILES');
      $fileRequest['expirationMinutes'] = '+20 minutes';
      $Url = $this->filemanagementlib->get_aws_s3_file_url($fileRequest);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $Url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $output = curl_exec($ch);
      curl_close($ch);

      $productAnnouncements = json_decode($output, true);
      $data['productAnnouncement'] = $productAnnouncements[$id];

      $this->load->view('includes/left_nav', $data);
      $this->load->view("help/product_announcement");
      $this->load->view('includes/footer-2', $data);

    }
  }

  function download_how_to_guide() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['planData'] = $this->cisession->userdata('planData');

      $fileRequest['awsS3FileKey'] = 'OIX User Manual_V1.6_August2019.pdf';
      $fileRequest['awsS3BucketName'] = $this->config->item('OIX_AWS_BUCKET_NAME_SENSITIVE_STATIC_FILES');
      $fileRequest['expirationMinutes'] = '+20 minutes';
      $oix_how_to_guide_url = $this->filemanagementlib->get_aws_s3_file_url($fileRequest);

      redirect($oix_how_to_guide_url);

    }
  }

}





/* End of file help.php */
/* Location: ./application/controllers/help.php */
