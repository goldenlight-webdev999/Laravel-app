<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Tasks extends CI_Controller {
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
    $this->load->model('Workflow');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Taxpayersdata');
    $this->load->model('Email_Model');

    $this->load->library('filemanagementlib');

  }

  function index() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "tasks";
      $data['tabArray'] = "tasks";
      $data['current_tab_key'] = "tasks";
      $data['tasksPage'] = "tasks";
      $data['fieldsType'] = "mycredits";

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);

      $taskRequest['dmaId'] = $this->cisession->userdata('dmaId');
      //$wAttachedToType = $request["wAttachedToType"];
      $taskRequest['wiStatus'] = "pending";
      $data['adminUserIdFilter'] = $this->input->post('adminUserIdFilter');
      $taskRequest['userId'] = ($data['adminUserIdFilter'] > 0) ? $data['adminUserIdFilter'] : null;

      $data['tasksArray'] = $this->Workflow->get_tasks($taskRequest);
      $data['dmaMembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);

      $data['taskPerUserList'] = [];
      foreach($data['dmaMembers'] as $m) {
        $thisUser = ['userId' => $m['userId'], 'firstName' => $m['firstName'], 'lastName' => $m['lastName'], 'taskCount' => 0];
        array_push($data['taskPerUserList'], $thisUser);
      }
      foreach($data['tasksArray'] as $ta) {
        $loopCounter = 0;
        foreach($data['taskPerUserList'] as $tpul) {
          if($ta['wiAssignedTo'] == $tpul['userId']) {
            $data['taskPerUserList'][$loopCounter]['taskCount']++;
          }
          $loopCounter++;
        }
      }

      //Load all data in sub-data for sub-included view
      $data['data'] = $data;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav_tasks', $data);
      $this->load->view("tasks/tasks", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

}
