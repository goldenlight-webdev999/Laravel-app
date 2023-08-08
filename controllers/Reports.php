<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Exceptions\AccessViolationException;

class Reports extends CI_Controller {
  /***
   * @var ReportsData
   */
  public $ReportsData;

  public $CreditListings;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('ReportsData');
    $this->load->model('IncentivePrograms');
    $this->load->model('CreditListings');
    $this->load->model('BidMarket');
    $this->load->model('Trading');
    $this->load->model('Trades');
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
    } else {

      $data['tabArray'] = "reports";
      $data['current_tab_key'] = "templates";
      $data['lnav_key'] = "reports";

      $data['reports'] = $this->ReportsData->get_report_templates($this->cisession->userdata('dmaId'));

      $data['myCreditsCount'] = $this->CreditListings->get_dma_credits_count($this->cisession->userdata('primUserId'));
      $data['sharedAccounts'] = $this->DmaAccounts->get_shared_accounts_of_dma_account($this->cisession->userdata('primUserId'), "credit");
      $data['myTransfersCount'] = $this->Trading->get_trades_count("seller", $this->cisession->userdata('primUserId'), $this->cisession->userdata('dmaId'), $this->cisession->userdata('dmaMemberId'));

      $data['planData'] = $this->cisession->userdata('planData');
      $data['data'] = $data;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("reports/all", $data);
      $this->load->view('includes/footer-2', $data);

    }
  }

  function library() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "reports";
      $data['current_tab_key'] = "library";
      $data['lnav_key'] = "reports";

      $rRequest = [];
      $data['reportExports'] = $this->ReportsData->get_report_exports($rRequest);

      $data['planData'] = $this->cisession->userdata('planData');
      $data['data'] = $data;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      if(!isset($data['planData']['reports']['library']) && !isset($data['planData']['allFeatures'])) {
        $this->load->view('includes/widgets/planBlockMessage');
      } else {
        $this->load->view("reports/library", $data);
      }
      $this->load->view('includes/footer-2', $data);

    }
  }

  function report($type = "", $rtId = "", $autorun = "", $newView = "") {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      /*
      $data['tabArray'] = "reportNew";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "reports";
      */
      $data['tabArray'] = "reports";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "reports";

      $data['hideMobileWarning'] = 1;
      if($autorun == "run") {
        $data['autorun'] = 1;
      } else {
        if($autorun == "autorun") {
          $data['autorun'] = 2;
        } else {
          $data['autorun'] = 0;
        }
      }

      $data['canEditSchedule'] = true;

      if($rtId > 0) {

        $data['rtId'] = $rtId;
        $data['reportTemplate'] = $this->ReportsData->get_report_template($rtId);

        $data['canEditSchedule'] = ($this->cisession->userdata('dmaMemberId') === $data['reportTemplate']['dmaMemberId']
            || $this->cisession->userdata('dmaMemberId') === $data['reportTemplate']['mainAdminDmaMemberId']);

        //validate access
        if($data['reportTemplate']['rtDmaId'] != $this->cisession->userdata('dmaId')) {
          redirect('/dashboard');
        }
        $data['rtArray'] = json_decode($data['reportTemplate']['rtArray'], true);
        $type = $data['rtArray']['reportType'];
        $data['rtStartDate'] = $data['reportTemplate']['rtStartDate'];
        $data['rtEndDate'] = $data['reportTemplate']['rtEndDate'];
        $data['reportView'] = (isset($data['rtArray']['reportView'])) ? $data['rtArray']['reportView'] : "";
      } else {
        $data['rtId'] = null;
        $data['reportTemplate'] = [];
        $data['rtArray'] = [];
        $data['rtStartDate'] = null;
        $data['rtEndDate'] = null;
        $data['reportView'] = $newView;
      }

      if($data['reportView'] == "group_by_credit") {
        $data['reportViewName'] = "Group by Tax Credit";
      } else {
        if($data['reportView'] == "summary") {
          $data['reportViewName'] = "Summary";
        } else {
          $data['reportViewName'] = "Details";
        }
      }

      if($type == "reports_mycredits" || $type == "reports_utilizations" || $type == "reports_compliance") {
        $data['reportType'] = $type;
        if($type == "reports_mycredits") {
          $data['reportTypeAsText'] = ($this->cisession->userdata('dmaType') == "advisor") ? "Client Projects" : "Tax Credit Portfolio";
          $dataKey = "dpReportMyCredits";
          $dpObjectType = "credit";
        } else {
          if($type == "reports_utilizations") {
            $data['reportTypeAsText'] = "Utilization";
            $dataKey = "dpReportTrades";
            $dpObjectType = "utilization";
          } else {
            if($type == "reports_compliance") {
              $data['reportTypeAsText'] = "Compliance";
              $dataKey = "dpReportCompliance";
            }
          }
        }
        $data['dateRangeText'] = "Date Loaded";
        if($type == "reports_mycredits") {
          $orderOptions = 'credits';
        } else {
          if($type == "reports_utilizations") {
            $orderOptions = ($data['reportView'] == "summary") ? 'utilizations_min' : 'utilizations';
          } else {
            if($type == "reports_compliance") {
              $orderOptions = 'compliance';
            }
          }
        }

        $data['orderOptions'] = $this->CreditListings->get_list_order_options($orderOptions);
        $data['columnsOptions'] = [];
        //Get system available data points
        if($type == "reports_mycredits") {
          $dpRequest['getReportMyCredits'] = 1;
        } else {
          if($type == "reports_utilizations") {
            $dpRequest['getReportTrades'] = 1;
          } else {
            if($type == "reports_compliance") {
              $dpRequest['getReportCompliance'] = 1;
            }
          }
        }
        $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
        if(isset($dpObjectType) && $dpObjectType != "") {
          // $dpRequest['dpObjectType'] = $dpObjectType;
        }
        $dataPoints = $this->CreditListings->get_data_points($dpRequest);
        $data['dataPoints'] = $dataPoints['dataPoints'];

        $data['dataPointsKeysOnly'] = $dataPoints['dataPointsKeysOnly'];
        $availableColumns = $data['dataPointsKeysOnly'];

        if($rtId > 0) {
          $savedColumns = $data['rtArray']['columns'];
          $data['columnsOptions'][0] = array_diff($availableColumns, $savedColumns);
          $data['columnsOptions'][1] = $savedColumns;
        } else {
          $data['columnsOptions'][0] = $availableColumns;
          $data['columnsOptions'][1] = [];
        }
      } else {
        if($type == "reports_purchases") {
          $data['reportType'] = $type;
          $data['reportTypeAsText'] = "Purchased Credits";
          $data['dateRangeText'] = "Date Purchased";
          $data['orderOptions'] = $this->CreditListings->get_list_order_options('purchases');
          //Get system available data points
          $dpRequest['getReportPurchases'] = 1;
          $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
          $dataPoints = $this->CreditListings->get_data_points($dpRequest);
          $data['dataPoints'] = $dataPoints['dataPoints'];
          $data['dataPointsKeysOnly'] = $dataPoints['dataPointsKeysOnly'];
          $availableColumns = $data['dataPointsKeysOnly'];
          //var_dump($availableColumns); throw new \Exception('General fail');
          if($rtId > 0) {
            $savedColumns = $data['rtArray']['columns'];
            $data['columnsOptions'][0] = array_diff($availableColumns, $savedColumns);
            $data['columnsOptions'][1] = $savedColumns;
          } else {
            $data['columnsOptions'][0] = $availableColumns;
            $data['columnsOptions'][1] = [];
          }
        }
      }

      //Auto-select default values
      $data["defaultDataPointsOrdered"] = [];
      $defaultDataPointsOrdered = $data['dataPoints'];
      //sort all this by DATE
      if($type == "reports_mycredits") {
        usort($defaultDataPointsOrdered, function($a, $b) {
          return $a['dpReportMyCredits'] - $b['dpReportMyCredits'];
        });
      } else {
        if($type == "reports_utilizations") {
          usort($defaultDataPointsOrdered, function($a, $b) {
            return $a['dpReportTrades'] - $b['dpReportTrades'];
          });
        } else {
          if($type == "reports_compliance") {
            usort($defaultDataPointsOrdered, function($a, $b) {
              return $a['dpReportCompliance'] - $b['dpReportCompliance'];
            });
          }
        }
      }
      foreach($defaultDataPointsOrdered as $co) {
        if($co[$dataKey] > 1) {
          array_push($data["defaultDataPointsOrdered"], $co);
        }
      }

      $data['planData'] = $this->cisession->userdata('planData');
      $data['dmaMembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
      $data['schedule'] = $this->ReportsData->get_report_schedule($rtId);
      $data['scheduleAccess'] = [];
      if(isset($data['schedule']['id'])) {
        $scheduleAccessMembers = $this->ReportsData->get_report_schedule_access($data['schedule']['id']);
        foreach($scheduleAccessMembers as $scheduleAccessMember) {
          $data['scheduleAccess'][] = $scheduleAccessMember['dma_member_id'];
        }
      }

      $data['data'] = $data;

      if(!$data['autorun']) {
        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
      } else {
        $this->load->view('includes/header_include', $data);
      }
      $this->load->view("reports/report", $data);
      if(!$data['autorun']) {
        $this->load->view('includes/footer-2', $data);
      }

    }
  }

  function view_report($rtId = "", $reId = "") {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['rtId'] = $rtId;
      $data['reId'] = $reId;

      //If this is a SAVE of a new template
      if($this->input->post('reportAction') == 'newReportTemplate') {

        $this->ReportsData->insert_report_template();

        redirect('/reports');

      } else {
        if($this->input->post('reportAction') == 'updateReportTemplate') {
          $data['rtId'] = $rtId;
          $data['reportTemplate'] = $this->ReportsData->get_report_template($rtId);
          //validate access
          if($data['reportTemplate']['rtDmaId'] != $this->cisession->userdata('dmaId')) {
            redirect('/dashboard');
          }

          $this->ReportsData->update_report_template($rtId);

          redirect('/reports');

        } else {

          $reportSvc = new \OIX\Services\ReportGeneratorService();
          try {
            if(isset($reId) && strlen(trim($reId)) > 0 && $reId > 0) {
              $data = $reportSvc->generateReportFromExport((int)$reId);
            } else {
              $data = $reportSvc->generateReportFromTemplate((int)$rtId);
              //$reportSvc->generateReportForTemplate($rtId);

              $data['dmaMembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
              $data['planData'] = $this->cisession->userdata('planData');

            }
          } catch(AccessViolationException $ex) {
            //TODO: we should probably log this
            redirect('/dashboard');
          }

          $this->load->view('includes/header_include', $data);
          //$this->load->view('includes/left_nav', $data);
          //$this->load->view('includes/tab_nav', $data);
          $this->load->view("reports/view_report", $data);
          $this->load->view('includes/footer-2', $data);
        }
      }
    }
  }

  function save_report_to_library() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $rData['reTitle'] = $this->input->post('reTitle');
      $rData['rtId'] = $this->input->post('rtId');
      $rData['reReportType'] = $this->input->post('reportType');
      $rData['reReportView'] = $this->input->post('reportView');
      $rData['reCreatedByUserId'] = $this->cisession->userdata('userId');

      $rData['reDataJSON'] = json_decode(json_decode($this->input->post('reDataJSON'), true), true);
      $rData['reDataHeaderRowsJSON'] = json_decode(json_decode($this->input->post('reDataHeaderRowsJSON'), true), true);
      $rData['reDataSummaryJSON'] = json_decode(json_decode($this->input->post('reDataSummaryJSON'), true), true);
      $rData['reDataOrderSummaryRowsJSON'] = json_decode(json_decode($this->input->post('reDataOrderSummaryRowsJSON'), true), true);
      $rData['reRecordsSummaryJSON'] = json_decode(json_decode($this->input->post('reRecordsSummaryJSON'), true), true);

      $rData['reCDataJSON'] = json_decode(json_decode($this->input->post('reCDataJSON'), true), true);

      $data['reportTemplate'] = $this->ReportsData->get_report_template($rData['rtId']);
      //validate access
      if($data['reportTemplate']['rtDmaId'] != $this->cisession->userdata('dmaId')) {
        redirect('/dashboard');
      }

      $reportExportId = $this->ReportsData->insert_report_export($rData);

      //Now add permission
      $adminAccessConfig = $this->input->post('adminAccessConfig');
      if($adminAccessConfig == 1) {

        $adminUserIdsAccess = $this->input->post('adminUserIdsAccess');
        foreach($adminUserIdsAccess as $dmaMemberId) {
          $reportAccess['raDmaId'] = $this->cisession->userdata('dmaId');
          $reportAccess['raDmaMemberId'] = $dmaMemberId;
          $reportAccess['raReportExportId'] = $reportExportId;
          $reportAccess['raReportTemplateId'] = null;
          $this->ReportsData->add_access_to_report_export($reportAccess);
        }
        //Then add one for myself as it isn't in the above loop
        $reportAccess['raDmaId'] = $this->cisession->userdata('dmaId');
        $reportAccess['raDmaMemberId'] = $this->cisession->userdata('dmaMemberId');
        $reportAccess['raReportExportId'] = $reportExportId;
        $reportAccess['raReportTemplateId'] = null;
        $this->ReportsData->add_access_to_report_export($reportAccess);

      } else {

        $reportAccess['raDmaId'] = $this->cisession->userdata('dmaId');
        $reportAccess['raDmaMemberId'] = 0;
        $reportAccess['raReportExportId'] = $reportExportId;
        $reportAccess['raReportTemplateId'] = null;
        $this->ReportsData->add_access_to_report_export($reportAccess);

      }

      $this->session->set_flashdata('successCreatedMessage', 1);

      redirect('/reports/library');

    }

  }

  function update_report_export() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $rData['reId'] = $this->input->post('reId');
      $rData['reTitle'] = $this->input->post('reTitle');

      //Get this report export
      $reRequest['reId'] = $rData['reId'];
      $data['reportExport'] = $this->ReportsData->get_report_exports($reRequest);

      //validate access
      if(sizeof($data['reportExport']) > 0) {
      } else {
        redirect('/dashboard');
      }

      $this->ReportsData->update_report_export($rData);

      /*
      */
      //Check for permission changes
      $permissionChangeOccurred = false;
      $reportExportId = $rData['reId'];
      $adminAccessConfig = $this->input->post('adminAccessConfig');
      //First, if already set to ALL
      if($adminAccessConfig == 0) {
        if($data['reportExport']['allAdminsHaveAccess']) {
          //Then nothing changed
        } else {
          $permissionChangeOccurred = true;
        }
        //Process change
        if($permissionChangeOccurred) {
          //Delete all existing access records
          $this->ReportsData->delete_all_report_export_access_records($rData['reId']);
          //Insert open access
          $reportAccess['raDmaId'] = $this->cisession->userdata('dmaId');
          $reportAccess['raDmaMemberId'] = 0;
          $reportAccess['raReportExportId'] = $reportExportId;
          $reportAccess['raReportTemplateId'] = null;
          $this->ReportsData->add_access_to_report_export($reportAccess);

        }
      } else {

        //Delete all existing access records
        $this->ReportsData->delete_all_report_export_access_records($rData['reId']);
        //Insert open access
        $adminUserIdsAccess = $this->input->post('adminUserIdsAccess');
        foreach($adminUserIdsAccess as $dmaMemberId) {
          $reportAccess['raDmaId'] = $this->cisession->userdata('dmaId');
          $reportAccess['raDmaMemberId'] = $dmaMemberId;
          $reportAccess['raReportExportId'] = $reportExportId;
          $reportAccess['raReportTemplateId'] = null;
          $this->ReportsData->add_access_to_report_export($reportAccess);
        }
        //Then add one for myself as it isn't in the above loop
        $reportAccess['raDmaId'] = $this->cisession->userdata('dmaId');
        $reportAccess['raDmaMemberId'] = $this->cisession->userdata('dmaMemberId');
        $reportAccess['raReportExportId'] = $reportExportId;
        $reportAccess['raReportTemplateId'] = null;
        $this->ReportsData->add_access_to_report_export($reportAccess);

      }

      $this->session->set_flashdata('successUpdatedMessage', 1);

      redirect('/reports/library');

    }

  }

  function delete_report_export() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $rData['reId'] = $this->input->post('reId');

      //Get this report export
      $reRequest['reId'] = $rData['reId'];
      $data['reportExport'] = $this->ReportsData->get_report_exports($reRequest);

      //validate access
      if(sizeof($data['reportExport']) > 0) {
      } else {
        redirect('/dashboard');
      }

      $this->ReportsData->delete_report_export($rData['reId']);

      //Delete all existing access records
      $this->ReportsData->delete_all_report_export_access_records($rData['reId']);

      $this->session->set_flashdata('successDeletedMessage', 1);

      redirect('/reports/library');

    }

  }

  function delete_report_template() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $rtId = $this->input->post('reportTemplateId');
      $data['rtId'] = $rtId;
      $data['reportTemplate'] = $this->ReportsData->get_report_template($rtId);
      //validate access
      if($data['reportTemplate']['rtDmaId'] != $this->cisession->userdata('dmaId')) {
        redirect('/dashboard');
      }

      $this->ReportsData->delete_report_template($rtId);

      $this->session->set_flashdata('deleteReportMessage', 1);

      redirect('/reports');

    }

  }

}
