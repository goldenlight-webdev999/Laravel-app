<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

require_once(APPPATH . "libraries/Aspose/Java.inc");
require_once(APPPATH . "libraries/Aspose/lib/aspose.cells.php");

use aspose\cells;

class Resources extends CI_Controller {

  const DEFAULT_COUNTRY_CODE = 'US';  // United States by default
  const DEFAULT_COUNTRY_ID = 1;  // United States by default

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->load->library('memberpermissions');
    $this->lang->load('tank_auth');
    $this->load->model('IncentivePrograms');
    $this->load->model('CreditListings');
    $this->load->model('BidMarket');
    $this->load->model('Members_Model');

    $this->load->model('Smartcharts_Model');

  }

  /**
   * This function will initialize the license for the Aspose java bridge, if it hasn't done so already.
   */
  protected function initAspose(): void {
    if(!$this->isAsposeInitialized) {
      $license = new cells\License();
      $license->setLicense(__DIR__ . "/../../javabridge/lib/Aspose.Cells.lic");
      $this->isAsposeInitialized = true;
    }
  }

  function terms_of_service() {
    $data['page_title'] = "The OIX Terms Of Service Page";
    $data['headerswitch'] = "corp";
    $data['column_override'] = 'span-4 push-4';
    $this->load->view('includes/header', $data);
    $this->load->view('resources/terms_of_service', $data);
    $this->load->view('includes/footer', $data);
  }

  function credit_programs($url = "", $countrySearch = "", $jurisdictionSearch = "", $categoriesSearch = "", $taxoffsetsSearch = "", $typeSearch = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "resources";
      $data['current_tab_key'] = "credit_programs";
      $data['lnav_key'] = "resources";

      $data['search'] = ($this->input->server('REQUEST_METHOD') == 'POST') ? true : false;

      $country_list = $this->Smartcharts_Model->get_jurisdiction_list("country");
      $province_list = $this->Smartcharts_Model->get_jurisdiction_list("province");
      $county_list = $this->Smartcharts_Model->get_jurisdiction_list("county");
      $town_list = $this->Smartcharts_Model->get_jurisdiction_list("town");
      $sector_list = $this->Smartcharts_Model->get_sector_list();
      $category_list = $this->Smartcharts_Model->get_category_list();
      $georestriction_list = $this->Smartcharts_Model->get_georestriction_list();
      $taxtype_list = $this->Smartcharts_Model->get_taxtype_list();

      $data['country_list'] = $country_list;
      $data['province_list'] = $province_list;
      $data['county_list'] = $county_list;
      $data['town_list'] = $town_list;
      $data['sector_list'] = $sector_list;
      $data['category_list'] = $category_list;
      $data['georestriction_list'] = $georestriction_list;
      $data['taxtype_list'] = $taxtype_list;
      $data['selected_countries'] = null;
      $data['selected_provinces'] = null;
      $data['selected_counties'] = null;
      $data['selected_towns'] = null;
      $data['selected_sectors'] = null;
      $data['selected_categories'] = null;

      $data['incentive_programs'] = [];
      $data['state_incentive_programs_count'] = [];

      if(isset($_POST['selected_sectors']) || isset($_POST['selected_categories'])) {

        $selected_countries = null;
        if(!empty($_POST['selected_countries'])) {
          $selected_countries = explode(',', $_POST['selected_countries']);
          $data['selected_countries'] = $this->Smartcharts_Model->get_jurisdiction_by_id("country", $selected_countries);
        }

        $selected_provinces = null;
        if(!empty($_POST['selected_provinces'])) {
          $selected_provinces = explode(',', $_POST['selected_provinces']);
          $data['selected_provinces'] = $this->Smartcharts_Model->get_jurisdiction_by_id("province", $selected_provinces);
        }

        $selected_counties = null;
        if(!empty($_POST['selected_counties'])) {
          $selected_counties = explode(',', $_POST['selected_counties']);
          $data['selected_counties'] = $this->Smartcharts_Model->get_jurisdiction_by_id("county", $selected_counties);
        }

        $selected_towns = null;
        if(!empty($_POST['selected_towns'])) {
          $selected_towns = explode(',', $_POST['selected_towns']);
          $data['selected_towns'] = $this->Smartcharts_Model->get_jurisdiction_by_id("town", $selected_towns);
        }

        $selected_sectors = null;
        if(!empty($_POST['selected_sectors'])) {
          $selected_sectors = explode(',', $_POST['selected_sectors']);
          $data['selected_sectors'] = $this->Smartcharts_Model->get_sector_list($selected_sectors);
        }

        $selected_categories = null;
        if(!empty($_POST['selected_categories'])) {
          $selected_categories = explode(',', $_POST['selected_categories']);
          $data['selected_categories'] = $this->Smartcharts_Model->get_category_list($selected_categories);
        }

        $selected_georestrictions = null;
        $selected_georestrictions_processed = null;
        if(!empty($_POST['selected_georestrictions'])) {
          $selected_georestrictions_processed = [];
          $selected_georestrictions = explode(',', $_POST['selected_georestrictions']);
          $data['selected_georestrictions'] = $this->Smartcharts_Model->get_georestriction_list($selected_georestrictions);
          foreach($data['selected_georestrictions'] as $sg) {
            array_push($selected_georestrictions_processed, $sg->georestriction);
          }
        }

        $selected_taxtypes = null;
        if(!empty($_POST['selected_taxtypes'])) {
          $selected_taxtypes = explode(',', $_POST['selected_taxtypes']);
          $data['selected_taxtypes'] = $this->Smartcharts_Model->get_taxtype_list($selected_taxtypes);
        }

        // Get the incentive programs according to the search filters
        $incentive_programs = $this->Smartcharts_Model->get_incentive_list($selected_countries, $selected_provinces, $selected_counties, $selected_towns, $selected_sectors, $selected_categories, $selected_georestrictions_processed, $selected_taxtypes);
        $data['incentive_programs'] = $incentive_programs;

        // Get the number of incentive programs returned according to the states
        $state_incentive_programs_count = [];
        foreach($incentive_programs as $ip) {
          if(array_key_exists($ip['StateName'], $state_incentive_programs_count)) {
            $state_incentive_programs_count[$ip['StateName']]++;
          } else {
            $state_incentive_programs_count[$ip['StateName']] = 1;
          }
        }
        $data['state_incentive_programs_count'] = $state_incentive_programs_count;

      }

      $province_id_list = [];
      foreach($province_list as $province) {
        array_push($province_id_list, $province->id);
      }
      $state_programs_count_list = $this->Smartcharts_Model->get_incentive_programs_count_by_states($province_id_list);
      $data['mapData'] = $state_programs_count_list;

      $data['planData'] = $this->cisession->userdata('planData');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/credit_programs", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  public function get_states_by_country() {

    $state_list = $this->Smartcharts_Model->get_state_list_by_country($_POST['country']);
    echo json_encode($state_list);
    //return true;
  }

  function credit_programs_history() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "resources";
      $data['current_tab_key'] = "credit_programs";
      $data['lnav_key'] = "resources";

      $data['programHistory'] = $this->IncentivePrograms->get_program_history();

      $this->load->view('includes/header_include', $data);
      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/credit_programs_history");
      $this->load->view('includes/footer-2', $data);

    }

  }

  function credit_program($id, $is_fresh = false) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "resources";
      $data['current_tab_key'] = "credit_programs";
      $data['lnav_key'] = "resources";

      $data['program'] = $this->IncentivePrograms->get_current_program($id);
      if (strtoupper($is_fresh) === 'NEW') {
        $data['is_fresh'] = true;
      }
      else {
        $data['is_fresh'] = false;
      }

      //Check access to this program
      if($data['program']->Status == 5) {
        $this->memberpermissions->checkCustomProgramAccess($id);
      }

      $data['programHistory'] = $this->IncentivePrograms->get_program_history($id);

      $this->load->view('includes/header_include', $data);
      //$this->load->view('includes/left_nav', $data);
      //$this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/credit_program");
      $this->load->view('includes/footer-2', $data);

    }

  }

  function edit_custom_program($id = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = ($id > 0) ? "edit_custom_program" : "create_custom_program";
      $data['current_tab_key'] = "credit_programs";
      $data['lnav_key'] = "resources";

      $data['programId'] = $id;

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/edit_custom_program");
      $this->load->view('includes/footer-2', $data);

    }

  }

  function load_edit_form($id = 0) {
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
      return;
    }

    $program = null;
    if($id > 0) {
      $program = $this->IncentivePrograms->get_current_program($id);

      if(!empty($program->SunsetDate)) {
        $program->SunsetDate = date('m/d/Y', strtotime($program->SunsetDate));
      }
    }

    $ipSectors = $this->IncentivePrograms->get_sectors();
    $ipCategories = $this->IncentivePrograms->get_categories();
    $ipTaxTypes = $this->IncentivePrograms->get_tax_types();

    $data = [
        'googleMapsKey' => $this->config->item('google_maps_key'),
        'fileCacheVer'  => $this->config->item('file_cache_ver'),
        'program'       => $program,
        'ipSectors'     => $ipSectors,
        'ipCategories'  => $ipCategories,
        'ipTaxTypes'    => $ipTaxTypes,
    ];

    $twig = \OIX\Util\TemplateProvider::getTwig();
    $template = $twig->load('resources/edit_custom_program.twig');
    echo $template->render($data);
  }

  function process_edit_custom_program_post() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $programId = $this->input->post('id');

    //Check this isn't an empty post
    $programName = $this->input->post('programName');
    $jurisdictionGooglePlaceId = $this->input->post('jurisdictionGooglePlaceId');

    if($programName == '') {
      echo json_encode(['success' => 0, 'id' => 0]);
    }

    $approvingAgency = $this->input->post('approvingAgency');
    $statuteNumber = $this->input->post('statuteNumber');
    $sunsetDate = $this->input->post('sunsetDate');
    $sectors = $this->input->post('sectors');
    $categories = $this->input->post('categories');
    $taxOffsets = $this->input->post('taxOffsets');
    $incentiveSummary = $this->input->post('incentiveSummary');
    $dmaNotes = $this->input->post('dmaNotes');

    if(!empty($sunsetDate)) {
      $sunsetDate = date('Y-m-d', strtotime($sunsetDate));
    } else {
      $sunsetDate = null;
    }

    $program = [
        'ProgramName'      => $programName,
        'ApprovingAgency'  => $approvingAgency,
        'StatuteNumber'    => $statuteNumber,
        'SunsetDate'       => $sunsetDate,
        'IncentiveSummary' => $incentiveSummary,
        'dmaNotes'         => $dmaNotes,
        'Status'           => 5,
        'pDmaId'           => $this->cisession->userdata('dmaId'),
        'ipdbtimestamp'    => date('Y-m-d'),
    ];

    if(!empty($jurisdictionGooglePlaceId)) {
      $this->jurisdictionSvc = new \OIX\Services\JurisdictionService($this->config->item('google_maps_key'));
      $jurisdictionId = $this->jurisdictionSvc->getByPlaceId($jurisdictionGooglePlaceId);
      $program['jurisdiction_id'] = $jurisdictionId;
    }

    if($programId > 0) {
      $this->IncentivePrograms->update_custom_incentive_program($programId, $program);

      $this->session->set_flashdata('saveSuccess', 1);
      echo json_encode(['success' => 1, 'id' => $programId]);
    } else {
      $programId = $this->IncentivePrograms->insert_custom_incentive_program($program);

      $this->session->set_flashdata('insertSuccess', 1);
      echo json_encode(['success' => 1, 'id' => $programId]);
    }

    // Update sectors
    $this->IncentivePrograms->remove_ip_sectors_from_program($programId);
    foreach($sectors as $sector) {
      if($sector == 0) {  // Add all sectors
        $this->IncentivePrograms->add_ip_sectors_all($programId);
      } else {
        $this->IncentivePrograms->add_ip_sector($sector, $programId);
      }
    }

    // Update categories
    $this->IncentivePrograms->remove_ip_categories_from_program($programId);
    foreach($categories as $category) {
      if($category == 0) {  // Add all categories
        $this->IncentivePrograms->add_ip_categories_all($programId);
      } else {
        $this->IncentivePrograms->add_ip_category($category, $programId);
      }
    }

    // Update tax offsets
    $this->IncentivePrograms->remove_ip_tax_types_from_program($programId);
    foreach($taxOffsets as $to) {
      if($to == 0) {  // Add all tax offsets
        $this->IncentivePrograms->add_ip_tax_types_all($programId);
      } else {
        $this->IncentivePrograms->add_ip_tax_type($to, $programId);
      }
    }
  }

  function government_agencies($jurisdiction = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "resources";
      $data['current_tab_key'] = "government_agencies";
      $data['lnav_key'] = "resources";

      $jurisdiction = preg_replace("/[^a-zA-Z0-9]+/", "", $jurisdiction);

      if($jurisdiction != "") {
        $data['search'] = true;
      } else {
        $data['search'] = false;
      }
      $data['jurisdiction'] = $jurisdiction;

      $data['agencies'] = $this->IncentivePrograms->get_government_agencies($jurisdiction, 'byJurisdiction');
      $data['states'] = $this->IncentivePrograms->get_us_state_names();

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/government_agencies", $data);
      $this->load->view('includes/footer-2');

    }
  }

  function portals($pTypeId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['pTypeId'] = $pTypeId;

      if($pTypeId == 1 || $pTypeId == "") {
        $tabArray = "portals_service_providers";
        $data['headline'] = "The OIX Network of Service Providers";
        $data['description'] = 'Connect with these qualified and professional organizations for support with buying, selling, structuring and monetizing tax credits. To learn more about Service Provider Portals please contact the OIX at <a href="mailto:support@theoix.com">support@theoix.com</a>.';
      } else {
        if($pTypeId == 8) {

          //if this is a broker_customer, and they only have one, send directly to marketplace
          if($this->cisession->userdata('dmaType') == "customer_broker") {
            $request['cusUserId'] = $this->cisession->userdata('userId');
            $myServiceProviders = $this->Members_Model->get_my_service_providers($request);
            if(sizeof($myServiceProviders) == 1) {
              redirect('/broker/inventory/' . $myServiceProviders[0]['cusDmaId']);
            }
          }

          $tabArray = "portals_market_makers";
          $data['headline'] = "The OIX Network of Market Makers";
          $data['description'] = 'Connect with these qualified and professional organizations for support with buying, selling, structuring and monetizing tax credits. To learn more about Market Maker Portals please contact the OIX at <a href="mailto:support@theoix.com">support@theoix.com</a>.';
        }
      }

      $data['tabArray'] = $tabArray;
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "resources";

      $pRequest['pTypeId'] = $pTypeId;
      $data['partners'] = $this->Members_Model->get_partners($pRequest);

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/portals", $data);
      $this->load->view('includes/footer-2');

    }

  }

  function insights() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "insights";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "compliance";

      $data['fasbDocs'] = $this->IncentivePrograms->get_insight_docs('fasb');
      $data['insightDocs'] = $this->IncentivePrograms->get_insight_docs('whitepaper');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("resources/insights");
      $this->load->view('includes/footer-2', $data);

    }

  }

  function compare_programs() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $programIdList = explode(',', $_GET['programs']);

      $data['programs'] = [];

      foreach($programIdList as $programId) {
        $program = $this->IncentivePrograms->get_current_program($programId);
        array_push($data['programs'], (array)$program);
      }

      $this->load->view('includes/header_include', $data);
      $this->load->view("resources/compare_programs", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function download_compare_programs() {
    set_time_limit(60);     // Increase execution time limit to 60 seconds for handling mass result set

    $this->initAspose();
    // Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $programIdList = explode(',', $_GET['programs']);

      $programs = [];
      foreach($programIdList as $programId) {
        $program = $this->IncentivePrograms->get_current_program($programId);
        array_push($programs, (array)$program);
      }

      $rowsMeta = [
          "ProgramName"                       => "",
          "countryName"                       => "Region",
          "name"                              => "Jurisdiction",
          "cchGeographicConsiderations"       => "Geographic Considerations",
          "cchSunsetDate"                     => "Sunset Date",
          "ipSectors"                         => "Sectors",
          "ipCategories"                      => "Incentive Categories",
          "ipTaxTypes"                        => "Tax Offsets",
          "IncentiveSummary"                  => "Incentive Description",
          "IncentiveDetail"                   => "Amount",
          "cchApplicationProvisions"          => "Application Provisions",
          "cchFilingRequirements"             => "Filing Requirements",
          "cchIndustrySpecificConsiderations" => "Industry-Specific Considerations",
          "cchOther"                          => "Other",
          "cchContactInformation"             => "Contact Information",
          "cchBigLinks"                       => "CCH Discussion",
      ];

      // Create spreadsheet
      $workbook = new cells\Workbook();
      $sheets = $workbook->getWorksheets();

      // Instructions sheet
      $ipcSheet = $sheets->get(0);
      $ipcSheet->setName("Incentive Program Comparison");
      $ipcCells = $ipcSheet->getCells();

      // Sheet data
      $i = 0;
      foreach($rowsMeta as $rowKey => $rowName) {
        $j = 0;
        $ipcCells->get($i, $j)->putValue($rowName);
        $j++;

        foreach($programs as $program) {
          if($rowKey === "ipSectors") {
            $cellData = '';
            foreach($program[$rowKey] as $sector) {
              $cellData .= $sector->sector . '<br>';
            }
            $ipcCells->get($i, $j)->setHtmlString($cellData);
          } else if($rowKey === "ipCategories") {
            $cellData = '';
            foreach($program[$rowKey] as $category) {
              $cellData .= $category->category . '<br>';
            }
            $ipcCells->get($i, $j)->setHtmlString($cellData);
          } else if($rowKey === "ipTaxTypes") {
            $cellData = '';
            foreach($program[$rowKey] as $taxtype) {
              $cellData .= $taxtype->tax_type . '<br>';
            }
            $ipcCells->get($i, $j)->setHtmlString($cellData);
          } else {
            $ipcCells->get($i, $j)->setHtmlString($program[$rowKey]);
          }

          $j++;
        }
        $i++;
      }

      // Adjust height and width
      $ipcCells->setStandardHeight(20);
      $ipcCells->setRowHeight(0, 30);
      $ipcSheet->autoFitRows(1, count($rowsMeta));

      $ipcCells->getColumns()->get(0)->setWidth(35);
      for($j = 1; $j <= count($programs); $j++) {
        $ipcCells->getColumns()->get($j)->setWidth(60);
      }

      // Styling
      $ipc_header_style = new cells\Style();
      $ipc_header_style->getFont()->setSize(11);
      $ipc_header_style->getFont()->setBold(true);
      $ipc_header_style->setTextWrapped(true);
      $ipc_header_style->setVerticalAlignment(cells\TextAlignmentType::CENTER);

      $ipc_body_style1 = new cells\Style();
      $ipc_body_style1->getFont()->setBold(true);
      $ipc_body_style1->setForegroundColor(cells\Color::fromArgb(245, 244, 244));
      $ipc_body_style1->setPattern(cells\BackgroundType::SOLID);
      $ipc_body_style1->setVerticalAlignment(cells\TextAlignmentType::TOP);
      $ipc_body_style1->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(226, 226, 226));
      $ipc_body_style1->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(226, 226, 226));

      $ipc_body_style2 = new cells\Style();
      $ipc_body_style2->setTextWrapped(true);
      $ipc_body_style2->setVerticalAlignment(cells\TextAlignmentType::TOP);

      for($i = 0; $i < count($rowsMeta); $i++) {
        for($j = 0; $j <= count($programs); $j++) {
          if($i == 0) {
            $ipcCells->get($i, $j)->setStyle($ipc_header_style);
          }
          if($j == 0) {
            $ipcCells->get($i, $j)->setStyle($ipc_body_style1);
          }
          if($i > 0 && $j > 0) {
            $ipcCells->get($i, $j)->setStyle($ipc_body_style2);
          }
        }
      }

      $fileName = 'OIX-Incentive-Program-Comparison' . date('Y-m-d') . '.xlsx';
      $filePath = APPPATH . '../tmp_files/' . $fileName;
      $workbook->save($filePath);
      header('Pragma: public');
      header("Content-type:application/vnd.ms-excel; charset=utf-8");
      header('Content-Disposition: attachment; filename=' . $fileName);
      header('Content-Length: ' . filesize($filePath));

      readfile($filePath);

    }
  }

}
