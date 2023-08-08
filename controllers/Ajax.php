<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Ajax extends CI_Controller {
  protected $logger;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url', 'datedown', 'auth']);
    $this->load->library('form_validation');
    $this->load->model('IncentivePrograms');
    $this->load->model('Members_Model');
    $this->load->model('CreditListings');
    $this->load->model('Trades');
    $this->load->model('DmaAccounts');
    $this->load->model('Workflow');
    $this->load->model('Comments');
    $this->load->model('Docs');
    $this->load->model('AuditTrail');
    $this->load->model('Email_Model');
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');
    $this->load->library('globalsettings');
    $this->load->library(['currency']);

    $this->logger = Logger::getInstance();

    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }

  }

  function index() {

  }

  function loadFullNavigation() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    } else {

      $data['lnav_key'] = "launchpad";
      $data['tabArray'] = "launchpad";
      $data['current_tab_key'] = "";

      //Page Access (permissions)
      $accessMyAccounts = ($this->cisession->userdata('isDMAmulti')) ? 1 : 0;
      $accessLoadCredit = ($this->cisession->userdata('levelLoadCredit')) ? 1 : 0;
      $accessSellCredit = ($this->cisession->userdata('levelSellCredit')) ? 1 : 0;
      $accessBuyCredit = ($this->cisession->userdata('levelBuyCredit')) ? 1 : 0;
      $accessAdminUsers = ($this->cisession->userdata('levelManageAdmins')) ? 1 : 0;
      $accessEditAccount = ($this->cisession->userdata('levelEditAccount')) ? 1 : 0;

      if($this->cisession->userdata('dmaType') == "advisor") {
        $portfolioDDname = "Advisor Projects";
        $creditName = "Project";
        $linkedAccounts = "Project Summary";
      } else {
        if($this->cisession->userdata('isParentDma')) {
          $portfolioDDname = "Credits";
          $creditName = "Credit";
          $linkedAccounts = "Linked Accounts";
        } else {
          $portfolioDDname = "Credits";
          $creditName = "Credit";
          $linkedAccounts = "";
        }
      }

      $data['sections'] = [
          [
              'sectionName' => 'My Stuff',
              'pages'       => [
                  ['title' => 'My Calendar', 'icon' => 'icon-calendar', 'url' => base_url() . 'calendar', 'access' => 1, 'text' => 'View upcoming dates and deadlines across all of the credits in your account, such as certification dates, estimated payment dates and more.'],
                  ['title' => 'My Alerts', 'icon' => 'icon-bell-alt', 'url' => base_url() . 'myaccount/messages', 'access' => 1, 'text' => 'Discover important updates and upcoming events happening across all of your account activity, such as new credits loaded, documents uploaded, workflow task items created and more.'],
                  ['title' => 'My Tasks', 'icon' => 'icon-clipboard', 'url' => 'javascript:void(0)', 'access' => 1, 'text' => 'Track a “to do” list of tasks assigned to you across all credits, such as workflow items assigned to you, document signatures required and more.', 'onclick' => "openOverlayTemp('pendingActionsOverlay', 'oBoxFull', 'overlayTop0', '0', '', '650');"],
                  ['title' => 'My Compliance Alerts', 'icon' => 'icon-town-hall', 'url' => 'dmaaccount/compliance_alerts', 'access' => 1, 'text' => 'Identify any compliance requirements that have reached the alert stge or are out of compliance to ensure you do not miss a deadline.'],

                  ['title' => 'My Settings', 'icon' => 'icon-cog', 'url' => base_url() . 'myaccount/account_configuration', 'access' => 1, 'text' => 'Modify your personal settings.'],
                  ['title' => 'My Accounts', 'icon' => 'icon-th-large', 'url' => base_url() . 'myaccounts', 'access' => $accessMyAccounts, 'text' => 'Easily and quickly switch between other OIX Linked Accounts that you have access to.', 'hover' => 'You do not have access to other OIX linked accounts at this time.'],
              ],
          ],
          [
              'sectionName' => 'Analytics',
              'pages'       => [
                  ['title' => 'Portfolio Summary', 'icon' => 'icon-chart-pie-1', 'url' => base_url() . 'dashboard/analytics_report', 'access' => 1, 'text' => 'View a high-level overview of your entire ' . $creditName . ' and utilization portfolio, including total amounts, status, incentive programs and more. Robust filtering capabilities let you focus and pinpoint your analysis.'],
                  ['title' => 'Utilization History', 'icon' => 'icon-chart-line', 'url' => base_url() . 'dashboard/analytics_report/actual_payment_dates', 'access' => 1, 'text' => 'Quickly and easily review your credit utilization history over time and gain valuable insights, all with a single click of the mouse. Robust filtering capabilities let you focus and pinpoint your analysis.'],
                  ['title' => 'Utilization Forecast', 'icon' => 'icon-gauge', 'url' => base_url() . 'dashboard/analytics_report/estimated_payment_dates', 'access' => 1, 'text' => 'Quickly and easily visualize your forecasted monetization/utilization over the coming days, weeks, months or years, all with a single click of the mouse. Robust filtering capabilities let you focus and pinpoint your analysis.'],
                  ['title' => 'Performance', 'icon' => 'icon-calendar-empty', 'url' => base_url() . 'dashboard/analytics_report/date_range_analyzer', 'access' => 1, 'text' => 'Analyze the average, minimum, maximum and actual time between critical dates across your portfolio. Robust filtering capabilities let you focus and pinpoint your analysis.'],
                  ['title' => 'Reports', 'icon' => 'icon-calc', 'url' => base_url() . 'reports', 'access' => 1, 'text' => 'Highly configurable reporting system which enables you to organize, filter and customize data points to manage compliance needs. Create a customized library with full export capabilities.'],
              ],
          ],
          [
              'sectionName' => $portfolioDDname,
              'pages'       => [
                  ['title' => 'Dashboard', 'icon' => 'icon-home', 'url' => base_url() . 'dashboard', 'access' => 1, 'text' => 'Your account landing page which summarizes key activity across your entire account and provides quick links to key action items that need to be addressed.'],
                  ['title' => 'All ' . $creditName . 's', 'icon' => 'icon-folder', 'url' => base_url() . 'dashboard/mycredits', 'access' => 1, 'text' => 'Your repository for all credits and incentives loaded into your account to which you have permission to access. Easily filter, order and search your repository.'],
                  ['title' => 'Load a ' . $creditName, 'icon' => 'icon-publish', 'url' => '/dashboard/loadcredit', 'access' => $accessLoadCredit, 'text' => 'Load a new ' . $creditName . ' into your portfolio. Beyond adding incentive information, you can forecast target dates, manage compliance and admin user access, track variance and more.', 'class' => '', 'hover' => 'You do not have access to this feature.'],
                  ['title' => 'Utilizations', 'icon' => 'icon-layers', 'url' => base_url() . 'dashboard/utilized', 'access' => 1, 'text' => 'A list of any credit utilizations in your account to which you have permission to access. '],
                  ['title' => 'Utilize Credit', 'icon' => 'icon-list-add', 'url' => 'javascript:void(0)', 'access' => $accessLoadCredit, 'text' => 'Add an estimate or actual utilization of one or more of your credits. You can easily forecast your monetization and track historic usage.', 'class' => 'navNewUtiliztionBtn', 'hover' => 'You do not have access to this feature.'],
                  ['title' => 'Heat Map', 'icon' => 'icon-globe-alt-outline', 'url' => base_url() . 'dashboard/heatmap', 'access' => 1, 'text' => 'A world map view which displays all permissioned credits and incentives loaded into your account and grouped by jurisdiction.'],
              ],
          ],
          [
              'sectionName' => 'Process',
              'pages'       => [
                  ['title' => 'Workflows', 'icon' => 'icon-list-nested', 'url' => base_url() . 'workflows', 'access' => 1, 'text' => 'Create, manage and track Workflow Templates that can be connected to one or multiple tax credits in order to bring efficiencies to your administrative process.'],
                  ['title' => 'Compliance', 'icon' => 'icon-town-hall', 'url' => base_url() . 'compliance', 'access' => 1, 'text' => 'Create, manage and track Compliance Templates which can be connected to one or multiple tax credits in order to ensure you meet regulatory requirements on time.'],
              ],
          ],
          [
              'sectionName' => 'Administrative',
              'pages'       => [
                  ['title' => 'Admin Users', 'icon' => 'icon-users', 'url' => base_url() . 'dmamembers', 'access' => $accessAdminUsers, 'text' => 'Invite, manage and archive Admin Users (collaborators) within your account. You can configure global permissions on each admin user as well as specific password requirements based on access levels. ', 'hover' => 'You do not have access to this feature.'],
                  ['title' => 'All Tasks', 'icon' => 'icon-clipboard', 'url' => base_url() . 'tasks', 'access' => 1, 'text' => 'Track and manage all workflow and compliance tasks assigned to admin users within this account.'],
                  ['title' => 'Legal Entities', 'icon' => 'icon-bank', 'url' => base_url() . 'taxpayers', 'access' => $accessLoadCredit, 'text' => 'Create and manage “legal entities” in order to easily assign credits, purchases, sales, or other activities to a specific  organization (internal or external).', 'hover' => 'You do not have access to this feature.'],
                  ['title' => 'Custom Data Points', 'icon' => 'icon-list-add', 'url' => base_url() . 'dmaaccount/custom_datapoints', 'access' => $accessLoadCredit, 'text' => 'Create and manage custom data points which are automatically added to all credits within your portfolio in order to capture information beyond the default OIX data points available..', 'hover' => 'You do not have access to this feature.'],
                  ['title' => 'Tax Liabilities', 'icon' => 'icon-clipboard-1', 'url' => base_url() . 'liabilities', 'access' => 1, 'text' => 'Record and track upcoming tax liabilities within your organization which you can then tag to specific legal entities for future reference.'],
                  ['title' => 'Account Configuration', 'icon' => 'icon-cog-alt', 'url' => base_url() . 'dmaaccount/account_configuration', 'access' => $accessEditAccount, 'text' => 'Manage core account configurations, such as customizing drop downs, adding custom data points and managing minimum password/security requirements.', 'hover' => 'You do not have access to this feature.'],
              ],
          ],
          [
              'sectionName' => 'OIX Resources',
              'pages'       => [
                  ['title' => 'Incentive Programs', 'icon' => 'icon-globe', 'url' => base_url() . 'resources/credit_programs', 'access' => 1, 'text' => 'Expansive and expanding database of US and International incentive programs which can be searched with maps and filters.'],
                  ['title' => 'State Agencies', 'icon' => 'icon-map-o', 'url' => base_url() . 'resources/government_agencies', 'access' => 1, 'text' => 'Information about state and federal level US Governmental Agencies responsible for the administration and issuance of tax credits and incentives within their respective jurisdictions.'],
                  ['title' => 'Service Providers', 'icon' => 'icon-data-science', 'url' => base_url() . 'resources/portals/1', 'access' => 1, 'text' => 'An exclusive directory of partners providing core advisory services to our customers and broker/syndicators who facilitate transactional activities.'],
              ],
          ],
      ];

    }

    //ADD ITEMS BASED ON ACCESS PERMISSION

    //LINKED ACCOUNTS - JAMMY JAM
    if($this->cisession->userdata('isParentDma') || $this->cisession->userdata('dmaType') == "advisor") {
      $shared = [['title' => $linkedAccounts, 'icon' => 'icon-group-circled', 'url' => base_url() . 'dashboard/shared', 'access' => 1, 'text' => 'An aggregated dashboard and repository for credits & incentives which have been shared with you by other OIX Accounts.  Easily filter, order and search your shared credits.']];
      array_splice($data['sections'][2]['pages'], 1, 0, $shared);
    }
    //ADVISOR
    if($this->cisession->userdata('dmaType') == "advisor") {
      $pendingProjects = [['title' => 'Pending Projects', 'icon' => 'icon-clock', 'url' => base_url() . 'advisor/projects_pending', 'access' => 1, 'text' => 'Your advisory projects which have not yet started work.']];
      array_splice($data['sections'][2]['pages'], 2, 0, $pendingProjects);
      $activeProjects = [['title' => 'Active Projects', 'icon' => 'icon-chart-line-1', 'url' => base_url() . 'advisor/projects_active', 'access' => 1, 'text' => 'Your advisory projects which are actively being worked on.']];
      array_splice($data['sections'][2]['pages'], 3, 0, $activeProjects);
      $pendingProjects = [['title' => 'Completed Projects', 'icon' => 'icon-ok', 'url' => base_url() . 'advisor/projects_completed', 'access' => 1, 'text' => 'Your advisory projects which have been completed.']];
      array_splice($data['sections'][2]['pages'], 4, 0, $pendingProjects);
    }
    //HEALTH
    if(1 == 2 && $this->config->item('environment') == "DEV") {
      $health = [['title' => 'Health', 'icon' => 'icon-heartbeat', 'url' => base_url() . 'dashboard/healthcheck', 'access' => 1, 'text' => 'Analytics tool that scans your entire portfolio and alerts you to critical data points that are missing (i.e. not entered) relating to status, forecasts, deadlines, compliance, etc., thereby ensuring optimal performance and value.']];
      array_splice($data['sections'][3]['pages'], 2, 0, $health);
    }
    //MARKETPLACE STUFF
    if($this->config->item("environment") == 'DEV' && ($this->cisession->userdata("userId") == 559 || $this->cisession->userdata("userId") == 632)) {
      $marketplace = [['title' => 'Marketplace', 'icon' => 'icon-exchange', 'url' => base_url() . 'marketplace', 'access' => 1, 'text' => 'The OIX Marketplace for buying and selling US state transferable tax credits.']];
      array_splice($data['sections'][3]['pages'], 5, 0, $marketplace);
      $marketplace = [['title' => 'Market Data', 'icon' => 'icon-chart-bar', 'url' => base_url() . 'marketdata/analytics', 'access' => 1, 'text' => 'Analyze historical bid and trade activity on the OIX Tax Credit Marketplace.']];
      array_splice($data['sections'][3]['pages'], 5, 1, $marketplace);
    }

    $this->load->view('ajax/nav_full', $data);

  }

  function contact_form() {

    $account = [
        [
            'field' => 'first_name',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'last_name',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'email',
            'rules' => 'xss_clean|required|trim|valid_email',
        ],
        [
            'field' => 'company',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'title',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'phone',
            'rules' => 'required|trim|xss_clean',
        ],
        [
            'field' => 'need',
            'rules' => 'trim|xss_clean',
        ],

    ];

    $this->form_validation->set_rules($account);

    if($this->form_validation->run($account)) {

      //$response = $this->Members_Model->insertExchangePortal($this->input->post());

      //send email
      $emailData['firstName'] = $this->input->post('first_name');
      $emailData['lastName'] = $this->input->post('last_name');
      $emailData['email'] = $this->input->post('email');
      $emailData['company_name'] = $this->input->post('company');
      $emailData['phoneNumber'] = $this->input->post('phone');
      $emailData['job_title'] = $this->input->post('title');
      $emailData['need'] = $this->input->post('need');

      $emailData['updateType'] = 'newInquiry';
      $emailData['updateTypeName'] = 'New Inquiry Submitted';
      $this->Email_Model->_send_email('oix_admin_update', 'OIX Inquiry: ' . $emailData['need'] . ' - ' . $emailData['company_name'], $this->config->item("oix_sales_inbound_emails_array"), $emailData);

      $result['success'] = true;

    } else {

      $result['success'] = false;
      $result['error'] = 'input_error';

    }

    $json = json_encode($result);
    echo $json;

  }

  function loadcredit($successAction, $creditOriginType, $creditId = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //What to do after ajax success on save (for javascript in the view)
    $data['successAction'] = $successAction;
    $data['creditOriginType'] = $creditOriginType;

    //Flag if this is loading a new credit or editing existing one
    $data['creditId'] = $creditId;
    $data['isNewCredit'] = ($data['creditId'] > 0) ? false : true;

    //shared data between load new and edit credit
    $data['taxpayers'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 0, 0, 0, 0, 0);
    $customers = $this->Members_Model->get_customers($this->cisession->userdata('dmaId'));
    $data['customers'] = $customers['customers'];

    //If loading a credit
    if($data['isNewCredit']) {

      $permissions = [];
      $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares("", $this->cisession->userdata('dmaId'), $permissions);
      $data = $data + $dmamembers_credit_access;
      //custom data points
      $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
      $dpRequest['dpObjectType'] = 'credit';
      $customDataPointsRaw = $this->CreditListings->get_data_points($dpRequest);
      $data['credit']['customDataPoints'] = $customDataPointsRaw['dataPoints'];
    } else {

      $permissions = $this->memberpermissions->checkCreditAccess($creditId, 1, 'creditEditorOnly');
      $data = array_merge($data, $permissions);
      $dmamembers_credit_access = $this->DmaAccounts->get_dmamembers_creditaccess_shares($creditId, $this->cisession->userdata('dmaId'), $permissions);
      $data = $data + $dmamembers_credit_access;

      $data['credit'] = $this->CreditListings->get_credit_private($creditId);
      $totalUtilizationAmountActual = $this->Trading->get_total_trade_amount_on_listing($creditId);
      $data['totalUtilizationAmountActual'] = $totalUtilizationAmountActual['totalTradeAmount'];
      $totalUtilizationAmountEstimatedFixed = $this->Trading->get_total_trade_amount_on_listing($creditId, '', '', 1, 1);
      $data['totalUtilizationAmountEstimatedFixed'] = $totalUtilizationAmountEstimatedFixed['totalTradeAmount'];
      $data['totalUtilizationAmount'] = $data['totalUtilizationAmountActual'] + $data['totalUtilizationAmountEstimatedFixed'];
    }

    $data['price_percentage'] = $this->IncentivePrograms->calculate_percent();
    $data['prev_offset'] = $this->IncentivePrograms->get_offsets_by_active_program();
    $data['ipCategories'] = $this->IncentivePrograms->get_categories();
    $data['ipSectors'] = $this->IncentivePrograms->get_sectors();
    $data['first_tax_years'] = $this->IncentivePrograms->get_taxyear_by_active_program();
    //Reset the first item in taxyears
    $data['first_tax_years'][0] = "Select Year";

    //Get Credit Type options
    $data['credit_types'] = [0 => "Select Credit Type"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('program_type');

    //Get Certification Status options
    $data['certification_status'] = [0 => "Select Certification Status"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('certification_status');

    //Get Project Status options
    $data['project_status_values'] = [0 => "Select Project Status"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('project_status');

    //Get Monetization Status options
    $data['monetization_status_values'] = [0 => "Select Monetization Status"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('monetization_status');

    //Get Audit Status options
    $data['audit_status_values'] = [0 => "Select Audit Status"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('audit_status');

    //Get Cause for Adjustment - Credit Amount
    $data['adjustment_cause_credit_amount'] = [0 => "Select a Cause"] + $this->IncentivePrograms->get_dma_account_field_options_key_val('adjustment_cause_credit_amount');

    $data['currencyData'] = $this->currency->get_currency_key_value();

    //PROJECTS
    //These fields are for some API calls below
    $sData['dmaId'] = $this->cisession->userdata('dmaId');
    $sData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
    $data['projects'] = $this->CreditListings->get_filter_data_by_account('projects', $sData);

    $data['programs'] = ['Select Program'];
    $data['previously_used_years'] = $this->IncentivePrograms->get_previous_years();
    $data['prices'] = $this->IncentivePrograms->get_mandated_floor_prices();

    //If user has ability to load/edit credit from a global permisssion
    if(($this->cisession->userdata('planId') == 0 || $this->cisession->userdata('planId') == "") && $data['isNewCredit']) {
      $this->load->view('includes/widgets/planBlockMessage');
    } else {
      if(isset($permissions['permEdit']) && !$permissions['permEdit']) {
        $permData['permBlock'] = 'editThisCredit';
        $this->load->view("dmamembers/no_permission_static", $permData);
      } else {

        if($creditOriginType == "existingCredit") {
          $creditOriginType = $data['credit']['cOrigin'];
        }

        if($this->cisession->userdata('dmaType') == 'advisor' && count($data['customers']) == 0) {
          $data['featureAccess'] = true;
          $data['noResults'] = true;
          $data['feature'] = "noCustomersLoaded";
          $data['isSearch'] = false;
          $this->load->view("includes/upgrade_or_noresults_message", $data);
        } else {
          if($creditOriginType == "loaded_purchase") {
            $this->load->view("ajax/loadcredit_purchased", $data);
          } else {
            $this->load->view("ajax/loadcredit", $data);
          }
        }
      }
    }

  }

  function get_credit_in_columns($creditId) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $data = array_merge($data, $sanitizedPostData);

    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($data);

    $cData['listingId'] = $creditId;

    $credits = $this->CreditListings->get_credits($cData);
    $data['records'] = $credits['credits'];
    $data['recordsSummary'] = $credits['summary'];

    //Columns
    $data['columns'] = $this->cisession->userdata('creditViewConfig');
    $data['creditListViewPref'] = $this->cisession->userdata('creditListViewPref');

    if($data['columns'] != "" && $data['creditListViewPref'] == 2) {
      //Do nothing
    } else {
      $defaults = $this->globalsettings->get_global_setting("my_credits_view_default");
      $savedColumns = $defaults['gsValueLong'];
      $data['columns'] = json_decode($savedColumns, true);
    }

    //Now that we have data, send it to get built into the reports array
    $data['dataKey'] = "getMyCreditsView";
    $reportData = $this->CreditListings->buildReportData($data['dataKey'], $data['columns'], $data['records'], $data['order'], $data['view'], $this->cisession->userdata('dmaId'));
    $data['sDataKeyValue'] = $reportData['sDataKeyValue'];
    $data['sDataHeaderKeyValue'] = $reportData['sDataHeaderKeyValue'];
    $data['sDataSummary'] = $reportData['sDataSummary'];

    echo json_encode($data);

  }

  //This one is being replaced by get_programs_by_jurisdiction() as it now filters out "other" programs your DMA didnt' create
  function get_program_from_state() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $jurisdiction = $this->input->post('jurisdiction');
    $data['programs'] = $this->IncentivePrograms->get_active_programs_by_state_short($jurisdiction);
    $this->load->view('ajax/programs', $data);
  }

  function get_programs_by_jurisdiction() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $jurisdiction = $this->input->post('jurisdiction');
    $data['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction($jurisdiction);
    $data['jurisdictionData'] = $this->IncentivePrograms->get_jurisdiction_by_code($jurisdiction);
    $this->load->view('ajax/programs', $data);
  }

  function get_programs_by_jurisdiction_name() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //This function accepts jurisdiction names, tries to find a match in our database, and then uses that to try and find Incentive programs that match
    //Note - the front end is powered by Google Maps API, so it could be a city or county that is being searched. While that specific jurisdiction might not be in our database, there could be programs that apply to the state-level above it. THerefore, we try and grab state level programs as well.

    $this->load->model('IncentivePrograms');

    $googlePlaceId = $this->input->post('googlePlaceId');
    $jurisdictionSvc = new \OIX\Services\JurisdictionService($this->config->item('google_maps_key'));
    $jurisdictionId = $jurisdictionSvc->getByPlaceId($googlePlaceId);

    $data = [];
    $data['jurisdictionData'] = [];
    $data['jurisdictionData']['name'] = "";
    $data['jurisdictionData']['programs'] = [];

    //First - try and get incentive programs from this jurisdiction
    $data['jurisdictionData'] = $this->IncentivePrograms->get_jurisdiction_by_id($jurisdictionId);
    $data['jurisdictionData']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($jurisdictionId);
    $currency = new Currency();
    if(trim($data['jurisdictionData']['country_currency']) !== '') {
      $currencyData = $currency->get_currency_data($data['jurisdictionData']['country_currency']);
      if($currencyData['code'] !== $data['jurisdictionData']['country_currency']) {
        $data['jurisdictionData']['country_currency'] = 'USD';
      }
    } else {
      $data['jurisdictionData']['country_currency'] = 'USD';
    }

    if(isset($data['jurisdictionData']['town_id'])) {
      $countyJurisdiction = $this->IncentivePrograms->get_jurisdiction_by_ids($data['jurisdictionData']['country_id'], $data['jurisdictionData']['province_id'], $data['jurisdictionData']['county_id']);

      if(isset($countyJurisdiction['id'])) {
        $data['jurisdictionData']['county']['data'] = $countyJurisdiction;
        $data['jurisdictionData']['county']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($countyJurisdiction['id']);
      }

      $provinceJurisdiction = $this->IncentivePrograms->get_jurisdiction_by_ids($data['jurisdictionData']['country_id'], $data['jurisdictionData']['province_id']);
      if(isset($provinceJurisdiction['id'])) {
        $data['jurisdictionData']['province']['data'] = $provinceJurisdiction;
        $data['jurisdictionData']['province']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($provinceJurisdiction['id']);
      }

      $countryJurisdiction = $this->IncentivePrograms->get_jurisdiction_by_ids($data['jurisdictionData']['country_id']);
      if(isset($countryJurisdiction['id'])) {
        $data['jurisdictionData']['country']['data'] = $countryJurisdiction;
        $data['jurisdictionData']['country']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($countryJurisdiction['id']);
      }
    } else if(isset($data['jurisdictionData']['county_id'])) {
      $provinceJurisdiction = $this->IncentivePrograms->get_jurisdiction_by_ids($data['jurisdictionData']['country_id'], $data['jurisdictionData']['province_id']);
      if(isset($provinceJurisdiction['id'])) {
        $data['jurisdictionData']['province']['data'] = $provinceJurisdiction;
        $data['jurisdictionData']['province']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($provinceJurisdiction['id']);
      }

      $countryJurisdiction = $this->IncentivePrograms->get_jurisdiction_by_ids($data['jurisdictionData']['country_id']);
      if(isset($countryJurisdiction['id'])) {
        $data['jurisdictionData']['country']['data'] = $countryJurisdiction;
        $data['jurisdictionData']['country']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($countryJurisdiction['id']);
      }
    } else if(isset($data['jurisdictionData']['province_id'])) {
      $countryJurisdiction = $this->IncentivePrograms->get_jurisdiction_by_ids($data['jurisdictionData']['country_id']);
      if(isset($countryJurisdiction['id'])) {
        $data['jurisdictionData']['country']['data'] = $countryJurisdiction;
        $data['jurisdictionData']['country']['programs'] = $this->IncentivePrograms->get_programs_by_jurisdiction_id($countryJurisdiction['id']);
      }
    }

    $this->load->view('ajax/programs', $data);

  }

  function get_jurisdiction_id_by_place_id() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $googlePlaceId = $this->input->post('googlePlaceId');

    $jurisdictionSvc = new \OIX\Services\JurisdictionService($this->config->item('google_maps_key'));
    $jurisdictionId = $jurisdictionSvc->getByPlaceId($googlePlaceId);

    echo $jurisdictionId;
  }

  function get_credit_types_of_program() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $programId = $this->input->post('programId');

    $program = $this->IncentivePrograms->get_current_program($programId);

    if($program->ipdpCountry == 1) {
      $data['credit_types'] = $this->IncentivePrograms->get_credit_types_of_program($programId);
    } else {
      $data['credit_types'] = $this->IncentivePrograms->get_credit_types_of_international_program($programId);
    }

    $this->load->view('ajax/credit_types', $data);

  }

  function get_all_credit_types() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $programId = $this->input->post('programId');
    $credit_types = $this->IncentivePrograms->get_dma_account_field_options_key_val('program_type', $this->cisession->userdata('dmaId'));
    foreach($credit_types as $ctK => $ctV) {
      $data['credit_types'][$ctK] = $ctV;
    }
    $data['credit_types'] = [0 => "Select Credit Type"] + $data['credit_types'];
    $this->load->view('ajax/credit_types', $data);

  }

  function get_mandated_price_floor() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $jurisdiction = $this->input->post('jurisdiction');
    $data['programs'] = $this->IncentivePrograms->get_mandated_price_floor($jurisdiction);
    $this->load->view('ajax/pricefloor', $data);
  }

  function get_programo_from_state($withoutSelect = false) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $jurisdiction = $this->input->post('jurisdiction');
    $data['programs'] = $this->IncentivePrograms->get_active_programs_by_state_short($jurisdiction);
    $data['withoutSelect'] = $withoutSelect;
    $this->load->view('ajax/programs_o', $data);
  }

  function get_offsets_from_state($withoutSelect = false) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $jurisdiction = $this->input->post('State');

    $data['offsets'] = $this->IncentivePrograms->get_offsets_by_state_short($jurisdiction);
    $data['withoutSelect'] = $withoutSelect;

    $this->load->view('ajax/offsets', $data);
  }

  function get_offsets_by_state() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');
    $jurisdiction = $this->input->post('State');
    $data['offsets'] = $this->IncentivePrograms->get_offsets_by_state_short($jurisdiction);
    $this->load->view('ajax/tax-offsets', $data);
  }

  function get_offsets($jurisdiction) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');
    $data['offsets'] = $this->IncentivePrograms->get_offsets_by_state_short($jurisdiction);
    $this->load->view('ajax/offsets-selector', $data);
  }

  function get_empty_states() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');
    $data['jurisdiction'] = $this->IncentivePrograms->get_states_empty();
    $this->load->view('ajax/states_empty', $data);
  }

  function get_only_states_from_country() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $country = $this->input->post('country');
    $data['jurisdiction'] = $this->IncentivePrograms->get_only_states_by_country($country);

    if($this->input->post('template') == 'ipdb_search') {
      $this->load->view('ajax/jurisdictionsIPDB', $data);
    } else {
      $this->load->view('ajax/states', $data);
    }
  }

  function get_only_states_from_country2() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $country = $this->input->post('country');
    $data['jurisdiction'] = $this->IncentivePrograms->get_only_states_by_country($country);
    $this->load->view('ajax/states2', $data);
  }

  function get_only_states_from_country_style() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->model('IncentivePrograms');

    $country = $this->input->post('country');
    $data['jurisdiction'] = $this->IncentivePrograms->get_only_states_by_country_style($country);
    $this->load->view('ajax/statesStyle', $data);
  }

  function load_map($mapId) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }
    $data['mapId'] = $mapId;
    $this->load->view('ajax/map', $data);

  }

  function loadEntityDropDown($isMultiForm = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['isMultiForm'] = ($isMultiForm == 'multiform') ? true : false;

    $data['taxEntitiesName'] = "a Legal Entity";
    $data['taxEntities'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 0, 0, 0, 0, 0);
    $data['oixAccountsName'] = ($this->cisession->userdata('dmaType') == 'advisor' || $this->cisession->userdata('dmaType') == 'broker') ? "a Customer" : "an Account";
    $data['oixAccounts'] = ($this->cisession->userdata('dmaType') == 'advisor' || $this->cisession->userdata('dmaType') == 'broker') ? $this->Members_Model->get_customers($this->cisession->userdata('dmaId')) : $this->DmaAccounts->get_my_dma_accounts($this->cisession->userdata('userId'));

    $this->load->view('ajax/entityDropDown', $data);

  }

  function updatePSAstatus($listingId = "", $ownPS = "", $needPS = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //ACCESS - only allow credit owner/sharer
    $this->memberpermissions->checkCreditAccess($listingId, "", "creditEditorOnly");

    $updatePSA = $this->CreditListings->updatePSAstatus($listingId, $ownPS, $needPS);

    //send email
    $emailData['ownPS'] = $ownPS;
    $emailData['needPS'] = $needPS;
    $emailData['listingId'] = $listingId;
    $emailData['updateType'] = 'psaStatusUpdate';
    $emailData['updateTypeName'] = 'PSA Update';
    $this->Email_Model->_send_email('oix_admin_update', 'PSA Status Update on Credit ' . $listingId, $this->config->item("oix_admin_emails_array"), $emailData);

    echo $updatePSA;
  }

  function newPSAnotice($listingId = "", $listed = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //send email
    $emailData['ownPS'] = 1;
    $emailData['listingId'] = $listingId;
    $emailData['listed'] = $listed;
    $emailData['updateType'] = 'newPSAUploaded';
    $emailData['updateTypeName'] = 'New PSA Uploaded';
    $this->Email_Model->_send_email('oix_admin_update', 'New PSA Uploaded on Credit ' . $listingId, $this->config->item("oix_admin_emails_array"), $emailData);

    echo $updatePSA;
  }

  // Refresh OneLogin token after some interval of time
  function refreshToken() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->library('onelogin_lib');
    $this->load->library('filemanagementlib');
    $appId = $this->config->item("app_id");
    $refreshOneLoginToken = $this->onelogin_lib->refreshOneLoginToken($appId);
    $refreshBoxToken = $this->filemanagementlib->accessRefreshToken($appId);
    $refreshData = ["OneLogin" => $refreshOneLoginToken['result'], "Box" => $refreshBoxToken['result']];

    echo json_encode($refreshData);
  }

  //NEW UPLOADER
  function show_doc_uploader($listingId = "", $elementName = "", $folderName = "", $docType = "", $docConnectToType = "", $docItemId = "", $showDiligenceSelector = "", $dsIdBeingSigned = "", $iHash = "", $dsId = "", $dsAccessTime = "", $signedDocumentFileId = "") {

    $thisAccess = false;

    if($iHash != "" && $dsId != "" && $dsAccessTime != "" && $signedDocumentFileId != "") {

      $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

      //Get doc to sign info
      $dRequest['dsId'] = $dsId;
      $dRequest['dsAccessTime'] = $dsAccessTime;
      $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

      //$this->memberpermissions->checkCreditAccess($data['docToSign']['dsListingId'], 0, "creditEditorOnly");

      if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0) {

        //If this has NOT expired...
        if($data['docToSign']['dsAccessTime'] > time() || $data['docToSign']['dsDownloadExpires'] > time()) {
          $thisAccess = true;
        }
      }

    } else {
      if($this->tank_auth->is_logged_in()) {

        $thisAccess = true;

      }
    }

    if($thisAccess) {

      $data['boxUploadLocation'] = "uploadBoxFile2.php"; //previoulsy was https://dev.theoix.com/box/uploadBoxFile2.php for dev
      $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, urldecode($folderName));
      $data["listingId"] = $listingId;
      $data['box_filename'] = "";
      $data['category_text'] = $folderName;
      $data['docType'] = $docType;
      $data['docConnectToType'] = ($docConnectToType != "" || $docConnectToType != "-") ? $docConnectToType : null;
      $data['docItemId'] = ($docItemId > 0) ? $docItemId : null;
      $data['showDiligenceSelector'] = ($showDiligenceSelector != "" || $showDiligenceSelector != "-") ? $showDiligenceSelector : null;
      $data['dsIdBeingSigned'] = $dsIdBeingSigned;

      $data['saleId'] = "";
      $data['transactionId'] = "";
      $data['elementName'] = $elementName;
      $data['docListElementName'] = $elementName . "List";

      if($docType == "credit_psa_doc") {
        $data['multiUploader'] = "no";
      } else {
        $data['multiUploader'] = "yes";
      }

      $this->load->view('admin/fileManagement/doc_uploader', $data);

    }

  }

  function show_diligence_docs_marketplace($listingId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;

    //CHECK PERMISSION
    $this->memberpermissions->checkCreditPublic($data["listingId"]);

    $data['tags'] = "Diligence";

    $data['boxUploadLocation'] = null;

    $data['uploadType'] = null;
    $data['category_text'] = "Supporting Documents";

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data['filterTag'] = "none";
    $data['blockDiligence'] = "";
    $data['autoDiligence'] = "";
    $data["psa"] = "false";
    $data['showShare'] = "true";
    $data['showDelete'] = "false";
    $data['showDownload'] = "false";
    $data['showView'] = "true";
    $data['elementName'] = "-diligenceOnly";
    $data['jsFile'] = 'uploaddocs';
    $data['issueId'] = "";
    $data['saleId'] = "";
    $data['transactionId'] = "";
    $data['box_filename'] = "";
    $data['docType'] = 'credit_doc';
    $data['docConnectToType'] = "";
    $data['docItemId'] = "";
    $data['docaccess'] = "";

    $this->load->view('admin/fileManagement/doc_uploader', $data);
  }

  //EXISTING (OLD) UPLOADER
  function show_file_uploader($listingId = "", $selection = "", $tagConfig = "", $blockDiligence = "", $autoDiligence = "", $filePermissions = "", $uploadType = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;

    //CHECK PERMISSION
    //Only credit owner/sharer should access full credit docs
    //$this->memberpermissions->checkCreditAccess($data["listingId"]);

    //$data['tags'] = ($selection == "true") ? "Diligence" : "Supporting Documents";
    if($tagConfig == 2) {
      $data['tags'] = "Diligence";
    } else {
      $data['tags'] = "";
    }

    $data['boxUploadLocation'] = "uploadBoxFile2.php";

    //$data['uploadType'] = $this->filemanagementlib->getUploadTypeFolderName($appId);
    //$data['category_text'] = $data['uploadType'];
    if($uploadType == "") {
      $data['category_text'] = "Supporting Documents";
      $data['uploadType'] = "Supporting Documents";
    } else {
      $data['category_text'] = $uploadType;
      $data['uploadType'] = $uploadType;
    }

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data['selection'] = $selection;

    $data['uploadButton'] = "";

    // Fetch those ids have Diligence tag
    $data["SelectedfileIds"] = [];
    if($selection == "true") {

      $DiligencefileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
      if($DiligencefileIds != null) {
        $data["SelectedfileIds"] = $DiligencefileIds;
      }
    }

    // Fetch PSA ids to exclude those ids during page load.
    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder("Purchase Sale Agreement", $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["psafileIds"] = $psafileIds;
    } else {
      $data["psafileIds"] = null;
    }

    /*
        $this->cisession->set_userdata(array(
            'folderId'	=> $data['folderId'],
            'listingId' => $listingId
            ));
						*/

    if($filePermissions[0] == 0) {
      $showView = 'false';
    } else {
      if($filePermissions[0] == 1) {
        $showView = 'true';
      } else {
        if($filePermissions[0] == 2) {
          $showView = 'block';
        }
      }
    }
    if($filePermissions[1] == 0) {
      $showDownload = 'false';
    } else {
      if($filePermissions[1] == 1) {
        $showDownload = 'true';
      } else {
        if($filePermissions[1] == 2) {
          $showDownload = 'block';
        }
      }
    }
    if($filePermissions[2] == 0) {
      $showDelete = 'false';
    } else {
      if($filePermissions[2] == 1) {
        $showDelete = 'true';
      } else {
        if($filePermissions[2] == 2) {
          $showDelete = 'block';
        }
      }
    }

    $data['filterTag'] = ($tagConfig == 9) ? "newcredit" : "none";
    $data['blockDiligence'] = $blockDiligence;
    $data['autoDiligence'] = $autoDiligence;
    $data["psa"] = "false";
    $data['showShare'] = "true";
    $data['showDelete'] = $showDelete;
    $data['showDownload'] = $showDownload;
    $data['showView'] = $showView;
    $data['elementName'] = "-docsUpload";
    $data['jsFile'] = 'uploadfile';
    $data['issueId'] = "";
    $data['saleId'] = "";
    $data['transactionId'] = "";
    $data['box_filename'] = "";

    $data['docaccess'] = "owner";

    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  //EXISTING (OLD) UPLOADER (FOR SIGNATURE)
  function show_file_uploader_signature($listingId = "", $selection = "", $tagConfig = "", $blockDiligence = "", $autoDiligence = "", $filePermissions = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;

    //CHECK PERMISSION
    //Only credit owner/sharer should access full credit docs
    //$this->memberpermissions->checkCreditAccess($data["listingId"]);

    //$data['tags'] = ($selection == "true") ? "Diligence" : "Supporting Documents";
    if($tagConfig == 2) {
      $data['tags'] = "Diligence";
    } else {
      $data['tags'] = "";
    }

    $data['boxUploadLocation'] = "uploadBoxFile2.php";

    //$data['uploadType'] = $this->filemanagementlib->getUploadTypeFolderName($appId);
    //$data['category_text'] = $data['uploadType'];
    $data['category_text'] = "Signature Documents";
    $data['uploadType'] = "Signature Documents";

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data['selection'] = $selection;

    $data['uploadButton'] = "";

    // Fetch those ids have Diligence tag
    $data["SelectedfileIds"] = [];
    if($selection == "true") {

      $DiligencefileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
      if($DiligencefileIds != null) {
        $data["SelectedfileIds"] = $DiligencefileIds;
      }
    }

    // Fetch PSA ids to exclude those ids during page load.
    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder("Purchase Sale Agreement", $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["psafileIds"] = $psafileIds;
    } else {
      $data["psafileIds"] = null;
    }

    /*
        $this->cisession->set_userdata(array(
            'folderId'	=> $data['folderId'],
            'listingId' => $listingId
            ));
						*/

    if($filePermissions[0] == 0) {
      $showView = 'false';
    } else {
      if($filePermissions[0] == 1) {
        $showView = 'true';
      } else {
        if($filePermissions[0] == 2) {
          $showView = 'block';
        }
      }
    }
    if($filePermissions[1] == 0) {
      $showDownload = 'false';
    } else {
      if($filePermissions[1] == 1) {
        $showDownload = 'true';
      } else {
        if($filePermissions[1] == 2) {
          $showDownload = 'block';
        }
      }
    }
    if($filePermissions[2] == 0) {
      $showDelete = 'false';
    } else {
      if($filePermissions[2] == 1) {
        $showDelete = 'true';
      } else {
        if($filePermissions[2] == 2) {
          $showDelete = 'block';
        }
      }
    }

    $data['filterTag'] = ($tagConfig == 9) ? "newcredit" : "none";
    $data['blockDiligence'] = $blockDiligence;
    $data['autoDiligence'] = $autoDiligence;
    $data["psa"] = "false";
    $data['showShare'] = "true";
    $data['showDelete'] = $showDelete;
    $data['showDownload'] = $showDownload;
    $data['showView'] = $showView;
    $data['elementName'] = "-docsUploadSignature";
    $data['jsFile'] = 'uploadfile';
    $data['issueId'] = "";
    $data['saleId'] = "";
    $data['transactionId'] = "";
    $data['box_filename'] = "";

    $data['docaccess'] = "owner";

    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  function show_file_uploader_psa($issueId = "", $listingId = "", $selection = "", $category_id = "", $saleId = "", $transactionId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;
    /*
				//CHECK PERMISSION
				if($transactionId>0) {
					//if Transaction ID is set, then check if buyer or taxpayer
					$this->memberpermissions->checkTransactionAccess($transactionId);
				} else if($saleId>0) {
					//else if sale ID is set, then check if buyer, seller and seller legal entity
					$this->memberpermissions->checkTradeAccess($saleId);
				} else if($listingId>0) {
					//else if just credit ID is set, then only allow credit owner/sharer
					$this->memberpermissions->checkCreditAccess($listingId);
				}
*/

    $data['boxUploadLocation'] = "uploadBoxFile2.php";

    //$data['uploadType'] = $this->filemanagementlib->getUploadTypeFolderName($appId);
    //$data['category_text'] = "Supporting Documents";
    //$data['category_text'] = $data['uploadType'];

    $data['tags'] = "Purchase Sale Agreement";
    $data["psa"] = "true";
    $data['uploadButton'] = "";
    $data['showShare'] = "false";
    $data['showDelete'] = "true";
    $data['showDownload'] = "true";
    $data['showView'] = "true";

    if($category_id == 2) {
      $data['uploadType'] = "PSA Listed";
      $data['category_text'] = "PSA Listed";
      $data['box_filename'] = "PSA Listed";
      $data['elementName'] = "-psaListedUpload";
    } else {
      if($category_id == 3) {
        $data['uploadType'] = "PSA Seller Signed " . $saleId;
        $data['category_text'] = "PSA Seller Signed " . $saleId;
        $data['box_filename'] = "PSA Seller Signed";
        $data['elementName'] = "-psaSellerSigned";
      } else {
        if($category_id == 4) {
          $data['uploadType'] = "PSA Buyer Signed " . $transactionId;
          $data['category_text'] = "PSA Buyer Signed " . $transactionId;
          $data['box_filename'] = "PSA Buyer Signed";
          $data['elementName'] = "-psaBuyerSigned";
        } else {
          if($category_id == 5) {
            $data['uploadType'] = "PSA Pending";
            $data['category_text'] = "PSA Pending";
            $data['box_filename'] = "PSA Pending";
            $data['elementName'] = "-psaPending";
          } else {
            if($category_id == 6) {
              $data['uploadType'] = "PSA Fully Executed " . $transactionId;
              $data['category_text'] = "PSA Fully Executed " . $transactionId;
              $data['box_filename'] = "PSA Fully Executed";
              $data['elementName'] = "-psaFullyExecuted";
              $data['tags'] = "";
              $data["psa"] = "false";
            } else {
              if($category_id == 20) {
                $data['uploadType'] = "PSA Seller To Sign " . $saleId;
                $data['category_text'] = "PSA Seller To Sign " . $saleId;
                $data['box_filename'] = "PSA Seller To Sign";
                $data['elementName'] = "-psaSellerToSign";
              } else {
                if($category_id == 21) {
                  $data['uploadType'] = "PSA Buyer To Sign " . $transactionId;
                  $data['category_text'] = "PSA Buyer To Sign " . $transactionId;
                  $data['box_filename'] = "PSA Buyer To Sign";
                  $data['elementName'] = "-psaBuyerToSign";
                }
              }
            }
          }
        }
      }
    }

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);
    $data['selection'] = $selection;
    $data["SelectedfileIds"] = [];
    $data['issueId'] = $issueId;
    $data['saleId'] = $saleId;
    $data['transactionId'] = $transactionId;
    $data['blockDiligence'] = "false";
    $data['autoDiligence'] = "false";

    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["SelectedfileIds"] = $psafileIds;
    }
    $data["psafileIds"] = null;
    $data['jsFile'] = 'uploadfile';
    $data['docaccess'] = "transactioncascade";

    $data['filterTag'] = "none";
    if($listingId > 0) {
      $creditData = $this->CreditListings->get_credit_private($listingId);
      //If this is NOT a credit being loaded (in other words, no data comes back as a loaded credit ID is a temporary ID)
      if(sizeof($creditData) == 0) {
        $data['filterTag'] = "newcredit";
      }
    }

    //$this->load->view('admin/fileManagement/file_uploader_psa',$data);
    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  function show_closing_docs($issueId = "", $listingId = "", $selection = "", $saleId = "", $transactionId = "", $perspective = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;
    /*
				//CHECK PERMISSION
				if($transactionId>0) {
					//if Transaction ID is set, then check if buyer or taxpayer
					$this->memberpermissions->checkTransactionAccess($transactionId);
				} else if($saleId>0) {
					//else if sale ID is set, then check if buyer, seller and seller legal entity
					$this->memberpermissions->checkTradeAccess($saleId);
				} else if($listingId>0) {
					//else if just credit ID is set, then only allow credit owner/sharer
					$this->memberpermissions->checkCreditAccess($listingId);
				}
*/

    $data['boxUploadLocation'] = "uploadBoxFile2.php";

    $data['uploadType'] = "PSA Fully Executed " . $transactionId;
    $data['category_text'] = "PSA Fully Executed " . $transactionId;
    $data['box_filename'] = "PSA Fully Executed";
    $data['elementName'] = "-psaFullyExecuted";
    $data['tags'] = "";
    $data["psa"] = "false";
    $data['uploadButton'] = "hide";
    $data['showShare'] = "false";
    $data['showDelete'] = "false";
    $data['showDownload'] = "true";
    $data['showView'] = "true";

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data['selection'] = $selection;
    $data["SelectedfileIds"] = [];
    $data['issueId'] = $issueId;
    $data['saleId'] = $saleId;
    $data['transactionId'] = $transactionId;
    $data['blockDiligence'] = "false";
    $data['autoDiligence'] = "false";

    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["SelectedfileIds"] = $psafileIds;
    }
    $data["psafileIds"] = null;
    if($perspective == "sale") {
      $data['filterTag'] = "tradeCompletedDocsSale";
    } else {
      $data['filterTag'] = "tradeCompletedDocsPurchase";
    }
    $data['jsFile'] = 'uploadfile';
    $data['docaccess'] = "transactioncascade";

    //$this->load->view('admin/fileManagement/file_uploader_psa',$data);
    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  function trade_docs($issueId = "", $listingId = "", $selection = "", $saleId = "", $transactionId = "", $perspective = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;
    /*
				//CHECK PERMISSION
				if($transactionId>0) {
					//if Transaction ID is set, then check if buyer or taxpayer
					$this->memberpermissions->checkTransactionAccess($transactionId);
				} else if($saleId>0) {
					//else if sale ID is set, then check if buyer, seller and seller legal entity
					$this->memberpermissions->checkTradeAccess($saleId);
				} else if($listingId>0) {
					//else if just credit ID is set, then only allow credit owner/sharer
					$this->memberpermissions->checkCreditAccess($listingId);
				}
*/

    $data['boxUploadLocation'] = "uploadBoxFile2.php";

    $data['uploadType'] = "PSA Fully Executed " . $transactionId;
    $data['category_text'] = "PSA Fully Executed " . $transactionId;
    $data['box_filename'] = "PSA Fully Executed";
    $data['elementName'] = "-psaFullyExecuted";
    $data['tags'] = "";
    $data["psa"] = "false";
    $data['uploadButton'] = "true";
    $data['showShare'] = "true";
    $data['showDelete'] = "true";
    $data['showDownload'] = "true";
    $data['showView'] = "true";

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data['selection'] = $selection;
    $data["SelectedfileIds"] = [];
    $data['issueId'] = $issueId;
    $data['saleId'] = $saleId;
    $data['transactionId'] = $transactionId;
    $data['blockDiligence'] = "false";
    $data['autoDiligence'] = "false";

    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["SelectedfileIds"] = $psafileIds;
    }
    $data["psafileIds"] = null;
    if($perspective == "sale") {
      $data['filterTag'] = "tradeCompletedDocsSale";
    } else {
      $data['filterTag'] = "tradeCompletedDocsPurchase";
    }
    $data['jsFile'] = 'uploadfile';
    $data['docaccess'] = "transactioncascade";

    //$this->load->view('admin/fileManagement/file_uploader_psa',$data);
    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  function show_diligence_docs_only($listingId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;

    //CHECK PERMISSION
    $this->memberpermissions->checkCreditPublic($data["listingId"]);

    $data['tags'] = "Diligence";

    $data['boxUploadLocation'] = null;

    $data['uploadType'] = null;
    $data['category_text'] = "Supporting Documents";

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data["SelectedfileIds"] = null;
    $data["psafileIds"] = null;
    $data['selection'] = "false";
    $data['uploadButton'] = "hide";
    $data['filterTag'] = "Diligence";
    $data['showShare'] = "false";
    $data['showDelete'] = "false";
    $data['showDownload'] = "false";
    $data['showView'] = "true";
    $data['elementName'] = "-diligenceOnly";
    $data['jsFile'] = 'uploadfile';
    $data['issueId'] = "";
    $data['saleId'] = "";
    $data['transactionId'] = "";
    $data['box_filename'] = "";
    $data['blockDiligence'] = "false";
    $data['autoDiligence'] = "false";
    $data['docaccess'] = "public";

    /*
        $this->cisession->set_userdata(array(
            'folderId'	=> $data['folderId'],
            'listingId' => $listingId
            ));
						*/

    $data["psa"] = "";
    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  function show_psa_listed_only($listingId = "", $showView = "", $showDownload = "", $showDelete = "", $category_id = "", $tradeId = "", $transactionId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;

    //CHECK PERMISSION
    $this->memberpermissions->checkCreditPublic($data["listingId"]);

    $data['boxUploadLocation'] = null;

    $data['uploadType'] = "PSA Listed";
    $data['category_text'] = "PSA Listed";
    $data['box_filename'] = "PSA Listed";
    $data['elementName'] = "-psaListedOnly";

    $data['tags'] = "Purchase Sale Agreement";

    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $data['category_text'], $appId);

    $data['selection'] = "false";
    $data["SelectedfileIds"] = [];
    $data['uploadButton'] = "hide";
    $data['showView'] = $showView;
    $data['showDownload'] = $showDownload;
    $data['showShare'] = "false";
    $data['showDelete'] = $showDelete;
    $data['issueId'] = $listingId;
    $data['saleId'] = $tradeId;
    $data['transactionId'] = $transactionId;
    $data['blockDiligence'] = "false";
    $data['autoDiligence'] = "false";
    $data['docaccess'] = "public";

    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["SelectedfileIds"] = $psafileIds;
    }
    $data["psafileIds"] = null;
    $data['filterTag'] = "psaListedPublic";
    $data['jsFile'] = 'uploadfile';

    $data["psa"] = "true";
    /*
        $this->cisession->set_userdata(array(
            'folderId'	=> $data['folderId'],
            'listingId' => $listingId
            ));
						*/

    $this->load->view('admin/fileManagement/file_uploader', $data);
  }

  function show_psa_download_link($listingId = "", $folderName = "", $tradeId = "", $transactionId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data["listingId"] = $listingId;

    //CHECK PERMISSION
    if($transactionId > 0) {
      //if Transaction ID is set, then check if buyer or taxpayer
      $this->memberpermissions->checkTransactionAccess($transactionId);
    } else {
      if($tradeId > 0) {
        //else if sale ID is set, then check if buyer, seller and seller legal entity
        $this->memberpermissions->checkTradeAccess($tradeId);
      } else {
        if($listingId > 0) {
          //else if just credit ID is set, then only allow credit owner/sharer
          $this->memberpermissions->checkCreditAccess($listingId);
        }
      }
    }

    $data['uploadType'] = "PSA Listed";
    $data['category_text'] = "PSA Listed";
    $data['box_filename'] = "PSA Listed";

    $data['tags'] = "Purchase Sale Agreement";

    $data['boxUploadLocation'] = null;
    $data['uploadType'] = null;

    $folderName = str_replace('%20', ' ', $folderName);
    if($transactionId != "") {
      $folderName = $folderName . ' ' . $transactionId;
    } else {
      $folderName = $folderName . ' ' . $tradeId;
    }
    $data['folderId'] = $this->filemanagementlib->getFolderId($listingId, $folderName, $appId);

    $data["SelectedfileIds"] = [];
    $data["psafileIds"] = null;
    $data['selection'] = "false";
    $data['uploadButton'] = "hide";
    $data['filterTag'] = "none";
    $data['showView'] = 'false';
    $data['showDownload'] = 'true';
    $data['showShare'] = "false";
    $data['showDelete'] = 'false';
    $data['elementName'] = "-psaDownloadButton";
    $data['jsFile'] = 'downloadfile';
    $data["psa"] = "";
    $data['issueId'] = "";
    $data['saleId'] = "";
    $data['transactionId'] = "";
    $data['blockDiligence'] = "false";
    $data['autoDiligence'] = "false";
    $data['docaccess'] = "transactioncascade";

    $psafileIds = $this->filemanagementlib->checkBoxFileTagsAgainstFolder($data['tags'], $data['folderId'], $appId);
    if($psafileIds != null) {
      $data["SelectedfileIds"] = $psafileIds;
    }
    /*
        $this->cisession->set_userdata(array(
            'folderId'	=> $data['folderId'],
            'listingId' => $listingId
            ));
						*/

    $this->load->view('admin/fileManagement/file_uploader', $data);
    /*$data['data'] = $data;
        $this->load->view('admin/fileManagement/test',$data);*/
  }

  function checkPendingActions($userId) {

    if(!$this->tank_auth->is_logged_in()) {
      exit;
    }

    if($userId != $this->cisession->userdata('primUserId')) {
      redirect('/dashboard');
    }

    $this->load->model('Trades');
    //If DMA user
    if($this->cisession->userdata('level') == 4) {
      $type = 'dma';
      $transactionsIdToUse = $this->cisession->userdata('dmaId');
    } else {
      $type = 'member';
      $transactionsIdToUse = $userId;
    }

    //Data for pending actions bell
    $data['transactionsAction'] = $this->Trading->get_trade_transactions_of_user_pending_action($transactionsIdToUse, $type);
    $data['myPendingCreditWorkflowItems'] = $this->Workflow->get_workflow_items_assigned($this->cisession->userdata('userId'), $this->cisession->userdata('dmaId'), "", "credit", "pending");

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $data = array_merge($data, $sanitizedPostData);
    //Centralized function build filter query parameters
    $creditData = $this->CreditListings->buildFilterSearchData($data);
    $complianceCredits = $this->CreditListings->get_credits_with_compliance_attached($creditData);
    $complianceCredits = $complianceCredits['credits'];
    $complianceListingIds = [];
    foreach($complianceCredits as $comp) {
      array_push($complianceListingIds, $comp['listingId']);
    }
    $data['complianceAlertCount'] = 0;
    if(count($complianceListingIds) > 0) {
      $complianceAlerts = $this->Workflow->get_compliance_alerts($complianceListingIds);
      $data['complianceAlertCount'] = sizeof($complianceAlerts);
    }

    //Data for alerts/messages bell
    $calData['userId'] = $this->cisession->userdata('userId');
    $calData['dmaId'] = $this->cisession->userdata('dmaId');
    $calData['accountType'] = $this->cisession->userdata('level');
    $calData['warned'] = 0;
    $calData['read'] = 0;
    $calData['count'] = "count";
    $calData['type'] = "general"; //Set TYPE
    $pendingMessagesCount = $this->Members_Model->get_messages_of_user($calData);
    $data['pendingMessagesCount'] = ($pendingMessagesCount > 99) ? "99+" : $pendingMessagesCount;
    $calData['type'] = "calendar"; //Reset TYPE
    $calendarAlertsCount = $this->Members_Model->get_messages_of_user($calData);
    $data['calendarAlertsCount'] = ($calendarAlertsCount > 99) ? "99+" : $calendarAlertsCount;
    $calData['type'] = "alert"; //Reset TYPE
    $pendingMessagesAlertCount = $this->Members_Model->get_messages_of_user($calData);
    $data['pendingMessagesAlertCount'] = ($pendingMessagesAlertCount > 99) ? "99+" : $pendingMessagesAlertCount;

    //Total Actions
    $toDoTasks = [];

    //Loop through SELLER signature tasks
    /*
		foreach($data['tradesAction'] as $ta) {

			$projectNameExt = ($ta['projectNameExt']!="") ? " - ".$ta['projectNameExt'] : "";

			$toDoTasks[] = array(
				"actionName" => "Sign the Purchase Sale Agreement (PSA)",
				"date" => date('m/d/Y', strtotime($ta['timeStamp'])),
				'dateOrder' => strtotime($ta['timeStamp']),
				"actionUrl" => "dashboard/credit/".$ta['listingId']."/sale/".$ta['tradeId'],
				"stateCertNum" => $ta['stateCertNum'].$projectNameExt,
				"listingId" => $ta['listingId'],
				"state" => $ta['State'],
				"country_id" => $ta['country_id'],
				"listingFull" => $ta['State'].$ta['listingId'],
				"additionalInfo" => "Credit Amount: $".number_format($ta['tradeSize']),
			);

		}
		*/

    //Loop through BUYER signature and payment tasks
    foreach($data['transactionsAction'] as $tra) {

      if($tra['tradeType'] == "oix_marketplace_trade") {
        $actionName = ($tra['buyerPaid'] != 1) ? 'Submit purchase price ($' . number_format($tra['tCreditAmount'] * $tra['tradePrice']) . ')' : 'Sign the PSA and submit payment ($' . number_format($tra['tCreditAmount'] * $tra['tradePrice']) . ')';
        $actionUrl = ($tra['multiBuyer']) ? 'dashboard/credit/' . $tra['listingId'] . '/multipurchase/' . $tra['tradeId'] . '/transaction/' . $tra['transactionId'] : 'dashboard/credit/' . $tra['listingId'] . '/purchase/' . $tra['tradeId'];
      } else {
        if($tra['status'] == 0) {
          $actionName = "Initiate Closing Process";
          $actionUrl = 'dashboard/credit/' . $tra['listingId'] . '/sale/' . $tra['tradeId'];
        } else {
          if($tra['status'] == 1) {
            $actionName = "Complete Closing Process";
            $actionUrl = 'dashboard/credit/' . $tra['listingId'] . '/sale/' . $tra['tradeId'];
          } else {
            $actionName = "I dunno";
            $actionUrl = 'dashboard/credit/' . $tra['listingId'] . '/sale/' . $tra['tradeId'];
          }
        }
      }

      /*
      if($tra['tradeType'] == "oix_marketplace_trade") {

        $projectNameExt = ($tra['projectNameExt'] != "") ? " - " . $tra['projectNameExt'] : "";

        $toDoTasks[] = [
            "actionName"     => $actionName,
            "date"           => date('m/d/Y', $tra['tTimestamp']),
            "dueDate"        => '-',
            'dateOrder'      => $tra['tTimestamp'],
            "actionUrl"      => $actionUrl,
            "stateCertNum"   => $tra['stateCertNum'] . $projectNameExt,
            "listingId"      => $tra['listingId'],
            "state"          => $tra['State'],
            "country_id"     => $tra['country_id'],
            "listingFull"    => $tra['State'] . $tra['listingId'],
            "additionalInfo" => $tra['tpNameToUse'] . " (Credit Amount: $" . number_format($tra['tCreditAmount']) . ")",
        ];

      }
      */

    }

    //Loop through WORKFLOW TASKS
    foreach($data['myPendingCreditWorkflowItems'] as $wi) {

      $projectNameExt = ($wi['projectNameExt'] != "") ? " - " . $wi['projectNameExt'] : "";

      $toDoTasks[] = [
          "actionName"           => $wi['wiTempName'],
          "date"                 => ($wi['wiAssignedDate'] > 0) ? date('m/d/Y', $wi['wiAssignedDate']) : '-',
          "dueDate"              => ($wi['wiDueDate'] > 0) ? date('m/d/Y', $wi['wiDueDate']) : '-',
          'dateOrder'            => $wi['wiAssignedDate'],
          "actionUrl"            => ($wi['wiTempType'] == 'signature') ? 'invites/invite/' . $wi['wiInviteHash'] : 'dashboard/credit/' . $wi['wvAttachedToId'] . '/workflow/' . $wi['wiId'],
          "stateCertNum"         => $wi['stateCertNum'] . $projectNameExt,
          "listingId"            => $wi['wvAttachedToId'],
          "state"                => $wi['state'],
          "country_id"           => $wi['country_id'],
          "listingFull"          => $wi['state'] . $wi['wvAttachedToId'],
          "statusName"           => $wi['statusName'],
          "incentiveProgramName" => ($wi['incentiveProgramName'] != '') ? $wi['incentiveProgramName'] : '-',
      ];

    }

    //sort all this by DATE - reorder function
    usort($toDoTasks, function($a, $b) {
      return $b['dateOrder'] - $a['dateOrder'];
    });

    //Total
    $data['toDoTasks'] = $toDoTasks;

    //BROKER ONLY
    $data['broker']['newTradeAlertsCount'] = 0;
    $data['broker']['newBuyOrderAlertsCount'] = 0;
    $data['broker']['newSellOrderAlertsCount'] = 0;
    $data['broker']['newCustomerAlertsCount'] = 0;
    $data['broker']['newBuyerPsaSignedAlertsCount'] = 0;
    if($this->cisession->userdata('dmaType') == 'broker') {

      //Data for alerts/messages bell
      $calData['userId'] = $this->cisession->userdata('userId');
      $calData['dmaId'] = $this->cisession->userdata('dmaId');
      $calData['accountType'] = $this->cisession->userdata('level');
      $calData['warned'] = 0;
      $calData['read'] = 0;
      $calData['count'] = "count";
      $calData['type'] = "general"; //Set TYPE
      $calData['msActions'] = ["trade_new"]; //Set filter
      $data['broker']['newTradeAlertsCount'] = $this->Members_Model->get_messages_of_user($calData);
      $calData['msActions'] = ["bid_new"]; //Set filter
      $data['broker']['newBuyOrderAlertsCount'] = $this->Members_Model->get_messages_of_user($calData);
      $calData['msActions'] = ["credit_loaded"]; //Set filter
      $data['broker']['newSellOrderAlertsCount'] = $this->Members_Model->get_messages_of_user($calData);

    }

    echo json_encode($data);

  }

  function member_message_alert_overlay() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $aRequest['userId'] = $this->cisession->userdata('userId');
    $aRequest['dmaId'] = $this->cisession->userdata('dmaId');
    $aRequest['accountType'] = 4;
    $aRequest['type'] = "alert";
    $aRequest['limit'] = 1;
    $alert = $this->Members_Model->get_messages_of_user($aRequest);
    if(sizeof($alert) > 0) {
      $data['alert'] = $alert[0];
      //Mark the message as read (currently on message ID 1 pertains...)
      $this->Members_Model->update_member_message_alert_warned($this->cisession->userdata('userId'), $data['alert']['mmMessageId']);
      //Open the custom alert overlay
      $this->load->view("ajax/member_messages_overlay", $data);
    }

  }

  function requestDilDocModification($action, $listingId, $fileName, $fileId, $name, $tag = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //send email
    $emailData['name'] = urldecode($name);
    $emailData['listingId'] = $listingId;
    $emailData['action'] = $action;
    $emailData['fileName'] = urldecode($fileName);
    $emailData['fileId'] = $fileId;

    $emailData['updateType'] = 'diligenceModRequest';
    $emailData['updateTypeName'] = 'Diligence Doc Modification Request';
    $this->Email_Model->_send_email('oix_admin_update', 'Diligence Request - ' . $action . ' Document', $this->config->item("oix_admin_emails_array"), $emailData);

    return "Success";

  }

  function share_credit() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Get user account from email
    $member = $this->Members_Model->get_dma_member_by_email($_POST['email']);

    //If account exists, check if this member's DMA account has sharing feature
    if(isset($member['userId'])) {
      //if this user is a member of a DMA account
      if(sizeof($member['dmaAccounts']['all']) > 0) {
        foreach($member['dmaAccounts']['all'] as $dma) {
          //If share capabilty exists on user, then mark the share
          if($dma['planSharedCredits'] == 1) {
            //Return success to the page so the form can be submitted
            $data = ['status' => 'success'];
          } else {
            $data = ['error' => 'noShareAccess'];
          }
        }
      } else {
        $data = ['error' => 'noDmaAccess'];
      }

    } else {
      //Else, this is not a member
      $data = ['error' => 'notMember'];
    }

    $json = json_encode($data);
    echo $json;

  }

  function grant_customer_credit_access() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    //Get pending listing of this shared credit
    $creditData = $this->CreditListings->get_credit_private($this->input->post('listingId'));
    $customerAccount = $this->Members_Model->get_member_by_id($creditData['listedBy']); //TODO: this is fucked now.
    $customerAccountDMA = $customerAccount["dmaAccounts"]["all"][0];
    //Get partner information
    $pRequest['partnerDmaId'] = $this->cisession->userdata('dmaId');
    $partnerData = $this->Members_Model->get_partner($pRequest);

    $this->CreditListings->update_customer_access_to_credit($this->input->post('listingId'), 2, $this->input->post('cOwnerReadOnly'));
    $thisDmaId = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0]['dmaId'];

    //If view only, then enter a "watch" access level for the customer
    if($this->input->post('cOwnerReadOnly') == 1) {
      $this->CreditListings->insert_credit_permission($this->input->post('listingId'), 'watch', $thisDmaId, "");
      //Insert audit trail - View Only access
      $this->AuditTrail->insert_audit_item(130, $this->input->post('listingId'), '-', "View Only (" . $customerAccountDMA['title'] . ")", "");

      //else, give JUST the main admin edit access and everyone else view access
    } else {

      $adminUserIdsAccess = [];
      $adminUserAccessLevels = [];

      $allDmaMembers = $this->DmaAccounts->get_dma_members($thisDmaId, 1);

      foreach($allDmaMembers as $adm) {
        if($adm['userId'] == $customerAccountDMA['primary_account_id']) {
          /* Do not add to permission as permission library will do so automatically*/
        } else {
          if($adm['userId'] == $creditData['cDmaMemberId']) {
            /* Do not add the ma*/
            $adminUserIdsAccess[] = $adm['dmaMemberId'];
            $adminUserAccessLevels[] = "edit";
            $accessId = 131;
            $accessText = "Edit & View";
          } else {
            $adminUserIdsAccess[] = $adm['dmaMemberId'];
            $adminUserAccessLevels[] = "view";
            $accessId = 130;
            $accessText = "View Only";
          }
        }
      }

      $this->memberpermissions->updateCreditAccessPermissionsDMA($this->input->post('listingId'), $thisDmaId, 1, $adminUserIdsAccess, $adminUserAccessLevels);
      //Insert audit trail - Variable access
      $this->AuditTrail->insert_audit_item($accessId, $this->input->post('listingId'), '-', $accessText . " (" . $customerAccountDMA['title'] . ")", "");

    }

    //SEND EMAIL INVITATION TO CUSTOMER

    //Insert message for Advisor
    $msType = "update";
    $msAction = "share_new";
    $msListingId = $this->input->post('listingId');
    $msBidId = "";
    $msTradeId = "";
    $msTitle = "Credit Access Granted to " . $customerAccountDMA['title'] . " - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['projectNameFull'] . ")";
    $msTitle2 = "Credit Access Granted by " . $this->cisession->userdata("dmaTitle") . " - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['projectNameFull'] . ")";
    $msTitleShort = "Credit Access Granted to " . $customerAccountDMA['title'];
    $msTitle2Short = "Credit Access Granted by " . $this->cisession->userdata("dmaTitle");
    $msTitleShared = "";
    $msTitleSharedShort = $msTitleShort;
    $msContent = "";
    $msContent2 = "";
    $msPerspective = "shared";
    $msPerspective2 = "seller";
    $firstDmaMainUserId = $this->cisession->userdata('primUserId');
    $secondDmaMainUserId = $customerAccountDMA['primary_account_id'];
    $msUserIdCreated = $this->cisession->userdata('userId');
    $alertShared = false;
    $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    //Insert alert message for customer
    $msType = "alert";
    $msAction = "advisor_share_new";
    $msListingId = $this->input->post('listingId');
    $msBidId = "";
    $msTradeId = "";
    $msTitle = "Credit Access Granted by " . $this->cisession->userdata("dmaTitle") . " - " . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['projectNameFull'] . ")";
    $msTitle2 = "";
    $msTitleShort = "Credit Access Granted by " . $this->cisession->userdata("dmaTitle");
    $msTitle2Short = "";
    $msTitleShared = "";
    $msTitleSharedShort = $msTitleShort;
    $msContent = "";
    $msContent2 = "";
    $msPerspective = "seller";
    $msPerspective2 = "";
    $firstDmaMainUserId = $customerAccountDMA['primary_account_id'];
    $secondDmaMainUserId = "";
    $msUserIdCreated = $this->cisession->userdata('userId');
    $alertShared = false;
    $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    //Send Email notice to person shared to
    $emailData['updateType'] = "newAdvisorShareCredit";
    $emailData['firstName'] = $customerAccount['firstName'];
    $emailData['dmaTitle'] = $customerAccountDMA['title'];
    $emailData['credit'] = $creditData;
    $emailData['sharerName'] = $this->cisession->userdata("firstName") . ' ' . $this->cisession->userdata("lastName");
    $emailData['sharerDmaTitle'] = $this->cisession->userdata("dmaTitle");
    $emailData['isExistingUser'] = ($customerAccount['agreed'] == 1) ? true : false;
    $emailData['partnerUsername'] = $partnerData['partnerUsername'];
    $emailData['welcomeNameTemplate'] = 1;
    $emailData['button'] = 0;
    $emailData['subject'] = 'Credit Access Granted to You by ' . $this->cisession->userdata("dmaTitle") . ' - "' . $creditData["projectNameFull"] . '"';
    $emailData['headline'] = 'Credit%20Access%20Granted';
    $emailData['oixAdvisors'] = false;
    $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $customerAccount['email'], $emailData);

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $creditData['listingId'];
    $this->CreditListings->update_credit_cache($cData);

    $this->session->set_flashdata('grantAccessToCustomerMessage', 1);

    redirect('/dashboard/credit/' . $this->input->post('listingId'));

  }

  function update_customer_credit_access() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    $creditData = $this->CreditListings->get_credit_private($this->input->post('listingId'));
    $thisDma = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0];
    $thisDmaId = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0]['dmaId'];

    $this->CreditListings->update_customer_access_to_credit($this->input->post('listingId'), "", $this->input->post('cOwnerReadOnly'));

    //If view only, then enter a "watch" access level for the customer
    if($this->input->post('cOwnerReadOnly') == 1) {
      $this->memberpermissions->updateCreditAccessPermissionsDMA($this->input->post('listingId'), $thisDmaId, 1, "", "", 1);
      //Insert audit trail - View Only access
      $this->AuditTrail->insert_audit_item(130, $this->input->post('listingId'), '-', "All Admin Users of " . $thisDma['title'], "");
      //else, give JUST the main admin edit access and everyone else view access
    } else {

      $adminUserIdsAccess = [];
      $adminUserAccessLevels = [];

      $allDmaMembers = $this->DmaAccounts->get_dma_members($thisDmaId, 1);

      foreach($allDmaMembers as $adm) {
        if($adm['userId'] == $creditData['cDmaMemberId']) {
          $adminUserIdsAccess[] = $adm['dmaMemberId'];
          $adminUserAccessLevels[] = "edit";
          $accessId = 131;
        } else {
          $adminUserIdsAccess[] = $adm['dmaMemberId'];
          $adminUserAccessLevels[] = "view";
          $accessId = 130;
        }
      }

      $this->memberpermissions->updateCreditAccessPermissionsDMA($this->input->post('listingId'), $thisDmaId, 1, $adminUserIdsAccess, $adminUserAccessLevels);

    }

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $this->input->post('listingId');
    $this->CreditListings->update_credit_cache($cData);

    //SEND EMAIL INVITATION TO CUSTOMER HERE

    $this->session->set_flashdata('updateAccessToCustomerMessage', 1);

    redirect('/dashboard/credit/' . $this->input->post('listingId'));

  }

  function revoke_customer_credit_access() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    $this->CreditListings->update_customer_access_to_credit($this->input->post('listingId'), 1, 0);

    $creditData = $this->CreditListings->get_credit_private($this->input->post('listingId'));
    $thisDma = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0];
    $thisDmaId = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0]['dmaId'];

    //DELETE all current permission on credit
    $this->CreditListings->delete_all_credit_permissions_for_dma_account($this->input->post('listingId'), $thisDmaId);

    //Insert audit trail - Remove acess
    $this->AuditTrail->insert_audit_item(132, $this->input->post('listingId'), '-', "All Admin Users of " . $thisDma['title'], "");

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $this->input->post('listingId');
    $this->CreditListings->update_credit_cache($cData);

    //SEND EMAIL INVITATION TO CUSTOMER HERE

    $this->session->set_flashdata('revokedAccessToCustomerMessage', 1);

    redirect('/dashboard/credit/' . $this->input->post('listingId'));

  }

  function transfer_customer_credit_access() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $creditData = $this->CreditListings->get_credit_private($this->input->post('listingId'));
    $thisDma = $creditData['listedByInfo']['dmaAccounts']['mainAdmin'][0];

    //TBD - PUT SHARE IN SOME KIND OF DELETE STATE BUT STILL HAVE ACCESS TO DATA SNAPSHOT ???

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    $this->CreditListings->update_customer_access_to_credit($this->input->post('listingId'), 3);

    //SEND EMAIL INVITATION TO CUSTOMER HERE

    //Insert audit trail - Transfer access
    $this->AuditTrail->insert_audit_item(134, $this->input->post('listingId'), '-', "Credit tranfered to " . $thisDma['title'], "");

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $this->input->post('listingId');
    $this->CreditListings->update_credit_cache($cData);

    //$this->session->set_flashdata('grantAccessToCustomerMessage',1);

    redirect('/dashboard/shared');

  }

  function update_share_permission() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    $this->CreditListings->update_share_permission($this->input->post('sIdManage'), $this->input->post('sharedPermEdit'));

    //get all existing permissions for this shared DMA
    $adminUsersPerm = $this->CreditListings->get_credit_permissions($this->input->post('listingId'), $this->input->post('sDmaId'));

    //Get DMA info
    $thisDmaInfo = $this->DmaAccounts->get_dma_account_by_id($this->input->post('sDmaId'));

    //if size of permissions is equal to 1, then there is just a global permission (not individual permissions)
    if(sizeof($adminUsersPerm['creditAccess']) == 1) {
      $this->CreditListings->delete_all_credit_permissions_for_dma_account($this->input->post('listingId'), $this->input->post('sDmaId'));
      $sharePermissionGlobal = ($this->input->post('sharedPermEdit') == 1) ? "open" : "watch";
      $this->CreditListings->insert_credit_permission($this->input->post('listingId'), $sharePermissionGlobal, $this->input->post('sDmaId'), "");
      //Insert audit trail - for entire DMA account
      $sharePermissionGlobalId = ($this->input->post('sharedPermEdit') == 1) ? 131 : 130;
      $this->AuditTrail->insert_audit_item($sharePermissionGlobalId, $this->input->post('listingId'), '-', "All Admin Users of " . $thisDmaInfo['title'], "");
    }

    //If there is more than one permission, then the DMA Account already has configured access to specific admin users in its account, so we need to update those
    if(sizeof($adminUsersPerm['creditAccess']) > 1) {
      $adminUserIdsAccess = [];
      $adminUserAccessLevels = [];
      $thisShare = $this->CreditListings->get_share($this->input->post('sIdManage'));
      foreach($adminUsersPerm['creditAccess'] as $aup) {
        if($aup['dmaMemberId'] > 0) {
          array_push($adminUserIdsAccess, $aup['dmaMemberId']);
          //If this is the person originally shared with or the main DMA of that shard DMA account, then attach edit or view access
          if($aup['userId'] == $thisShare["sharedPrimId"] || $aup['userId'] == $thisShare["sharedSecId"]) {
            $sharePermissionUser = ($this->input->post('sharedPermEdit') == 1) ? "edit" : "view";
            //Else if the new permission is edit, then take whatever the user already has - but if this is view access, then knock this user down to view access
          } else {
            $sharePermissionUser = ($this->input->post('sharedPermEdit') == 1) ? $aup['caAction'] : "view";
          }
          array_push($adminUserAccessLevels, $sharePermissionUser);
        }
      }
      //Update credit Permissions
      $watchFlag = ($this->input->post('sharedPermEdit') == 1) ? 0 : 1;
      $this->memberpermissions->updateCreditAccessPermissionsDMA($this->input->post('listingId'), $this->input->post('sDmaId'), 1, $adminUserIdsAccess, $adminUserAccessLevels, $watchFlag, 1);
    }

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $this->input->post('listingId');
    $this->CreditListings->update_credit_cache($cData);

    $this->session->set_flashdata('shareCreditUpdateMessage', 1);

    redirect('/dashboard/credit/' . $this->input->post('listingId') . '/shares');

  }

  function revoke_share_permission() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    $this->CreditListings->revoke_share($this->input->post('sIdManage'));

    //DELETE all current permission on credit for this DMA account
    $this->CreditListings->delete_all_credit_permissions_for_dma_account($this->input->post('listingId'), $this->input->post('sDmaId'));

    //Check is an invite exists for this share
    $invite = $this->Members_Model->get_invite("", "creditShare", $this->input->post('sIdManage'));
    //If it does, delete it
    if(sizeof($invite) > 0) {
      $this->Members_Model->delete_invite($invite['inviteId']);
    }

    //Insert audit trail - for entire DMA account
    $thisDmaInfo = $this->DmaAccounts->get_dma_account_by_id($this->input->post('sDmaId'));
    $this->AuditTrail->insert_audit_item(132, $this->input->post('listingId'), '-', "All Admin Users of " . $thisDmaInfo['title'], "");

    //Centralized function to clean/prepare filter data
    $preparedFilterData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
    //Update cache for this credit
    $cData['listingId'] = $this->input->post('listingId');
    $this->CreditListings->update_credit_cache($cData);

    $this->session->set_flashdata('deleteCreditUpdateMessage', 1);

    redirect('/dashboard/credit/' . $this->input->post('listingId') . '/shares');

  }

  function update_closing_process_and_payment_min() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    //Insert audit trail - Transfer access
    //$this->AuditTrail->insert_audit_item(134, $this->input->post('listingId'), '-', "Credit tranfered to ".$thisDma['title'], "");

    $trade = $this->Trading->get_trade($this->input->post('tradeId'));
    $trade = $trade['trade'];
    $creditData = $this->CreditListings->get_credit_private($this->input->post('listingId'));

    //If closing status is set and it is NOT different than prior value
    $closingStatus = $this->input->post('closingStatus');
    if(isset($closingStatus) && $trade['status'] != $this->input->post('closingStatus')) {

      $this->Trading->update_trade_status($this->input->post('tradeId'), $this->input->post('closingStatus'));

      //Add new trade to audit trail
      if($this->input->post('closingStatus') == 2) {
        $statusText = "Complete";
      } else {
        if($this->input->post('closingStatus') == 1) {
          $statusText = "In Progress";
        } else {
          $statusText = "Not Started";
        }
      }

      if($this->input->post('tradeType') == 'external_transfer') {
        $auditTypeId = 87;
      }
      if($this->input->post('tradeType') == 'internal_transfer') {
        $auditTypeId = 88;
      }

      $this->AuditTrail->insert_audit_item($auditTypeId, $this->input->post('listingId'), "-", $statusText, "", "", "", "", "", "", $this->input->post('tradeId'));

      //Insert message for closing process change
      $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
      $msType = "update";
      $msAction = "closing_process_update";
      $msListingId = $this->input->post('listingId');
      $msBidId = "";
      $msTradeId = $this->input->post('tradeId');
      $msTitle = "Utilization closing process marked '" . $statusText . "' - " . $creditData['state'] . $creditData['listingId'] . "-" . $this->input->post('tradeId') . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
      $msTitle2 = "";
      $msTitleShort = $msTitle;
      $msTitle2Short = "";
      $msTitleShared = $msTitle;
      $msTitleSharedShort = $msTitleShort;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $this->cisession->userdata('primUserId');
      $secondDmaMainUserId = "";
      $msUserIdCreated = $this->cisession->userdata('userId');
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    }

    //If received payment changes
    if($this->input->post('sellerRecPayment') != $trade['sellerRecPayment']) {
      $sellerRecPaymentText = ($this->input->post('sellerRecPayment') == 1) ? "Payment Received" : "Payment Outstanding";
      $this->AuditTrail->insert_audit_item(95, $this->input->post('listingId'), "-", $sellerRecPaymentText, "", "", "", "", "", "", $this->input->post('tradeId'));

      //Insert message for payment received
      $msType = "update";
      $msAction = "trade_payment_update";
      $msListingId = $this->input->post('listingId');
      $msBidId = "";
      $msTradeId = $this->input->post('tradeId');
      $msTitle = $sellerRecPaymentText . " on Transfer/Trade - " . $creditData['state'] . $creditData['listingId'] . "-" . $this->input->post('tradeId') . " (" . $creditData['stateCertNum'] . $projectNameExt . ")";
      $msTitle2 = "";
      $msTitleShort = $msTitle;
      $msTitle2Short = "";
      $msTitleShared = $msTitle;
      $msTitleSharedShort = $msTitleShort;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $this->cisession->userdata('primUserId');
      $secondDmaMainUserId = "";
      $msUserIdCreated = $this->cisession->userdata('userId');
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    }
    if($this->input->post('sellerRecPaymentMethod') != $trade['sellerRecPaymentMethod']) {
      if($this->input->post('sellerRecPaymentMethod') == 2) {
        $paymentMethodText = "Wire";
      } else {
        if($this->input->post('sellerRecPaymentMethod') == 1) {
          $paymentMethodText = "Check";
        } else {
          $paymentMethodText = "None";
        }
      }
      $this->AuditTrail->insert_audit_item(96, $this->input->post('listingId'), "-", $paymentMethodText, "", "", "", "", "", "", $this->input->post('tradeId'));
    }
    if(strtotime($this->input->post('sellerRecPaymentDate')) != $trade['sellerRecPaymentDate']) {
      $this->AuditTrail->insert_audit_item(97, $this->input->post('listingId'), "-", $this->input->post('sellerRecPaymentDate'), "", "", "", "", "", "", $this->input->post('tradeId'));
    }
    if($this->input->post('paymentNotes') != $trade['paymentNotes']) {
      $this->AuditTrail->insert_audit_item(98, $this->input->post('listingId'), "-", $this->input->post('paymentNotes'), "", "", "", "", "", "", $this->input->post('tradeId'));
    }

    //Save payment on trade
    $spRequest['tradeId'] = $this->input->post('tradeId');
    $spRequest['sellerRecPayment'] = $this->input->post('sellerRecPayment');
    $spRequest['sellerRecPaymentMethod'] = $this->input->post('sellerRecPaymentMethod');
    $spRequest['sellerRecPaymentDate'] = strtotime($this->input->post('sellerRecPaymentDate'));
    $spRequest['paymentNotes'] = $this->input->post('paymentNotes');
    $this->Trading->update_seller_received_payment($spRequest);

    $this->session->set_flashdata('closingProcessUpdatedMessage', 1);

    $redirectValue = ($trade['tradeType'] == 'external_purchase') ? "external_purchase" : "sale";
    redirect('/dashboard/credit/' . $this->input->post('listingId') . '/' . $redirectValue . '/' . $this->input->post('tradeId'));

  }

  function update_broker_closing_process_and_payment_min() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    //Check credit access (this is a shared user )
    $this->memberpermissions->checkCreditAccess($this->input->post('listingId'));

    //Insert audit trail - Transfer access
    //$this->AuditTrail->insert_audit_item(134, $this->input->post('listingId'), '-', "Credit tranfered to ".$thisDma['title'], "");

    $trade = $this->Trading->get_trade($this->input->post('tradeId'));
    $trade = $trade['trade'];
    $creditData = $this->CreditListings->get_credit_private($this->input->post('listingId'));

    //If closing status is set and it is NOT different than prior value
    $closingStatus = $this->input->post('closingStatus');
    if(isset($closingStatus) && $trade['status'] != $this->input->post('closingStatus')) {
      $this->Trading->update_trade_status($this->input->post('tradeId'), $this->input->post('closingStatus'));
    }

    //update trade
    $tradeRequest['paymentInstructionsToUse'] = $this->input->post('paymentInstructionsToUse');
    $tradeRequest['tradeWireInstructions'] = $this->input->post('tradeWireInstructions');
    $this->Trading->update_trade_field($this->input->post('tradeId'), $tradeRequest);

    //update transaction
    $transactionId = $this->input->post('transactionId');
    if($transactionId > 0) {
      $tRequest['buyerPaid'] = $this->input->post('buyerPaid');
      $tRequest['buyerPayMethod'] = $this->input->post('buyerPayMethod');
      $tRequest['buyerPaidDate'] = strtotime($this->input->post('buyerPaidDate'));
      $this->Trading->update_transaction($this->input->post('transactionId'), $tRequest);
    }

    //$statusText = "Complete";
    //$this->AuditTrail->insert_audit_item($auditTypeId, $this->input->post('listingId'), "-", $statusText, "", "", "", "", "", "", $this->input->post('tradeId'));

    //Insert message for closing process change
    /*
		$projectNameExt = ($creditData['projectNameExt']!="") ? " - ".$creditData['projectNameExt'] : "";
		$msType = "update";
		$msAction = "closing_process_update";
		$msListingId = $this->input->post('listingId');
		$msBidId = "";
		$msTradeId = $this->input->post('tradeId');
		$msTitle = "Utilization closing process marked '".$statusText."' - ".$creditData['state'].$creditData['listingId']."-".$this->input->post('tradeId')." (".$creditData['stateCertNum'].$projectNameExt.")";
		$msTitle2 = "";
		$msTitleShort = $msTitle;
		$msTitle2Short = "";
		$msTitleShared = $msTitle;
		$msTitleSharedShort = $msTitleShort;
		$msContent = "";
		$msContent2 = "";
		$msPerspective = "seller";
		$msPerspective2 = "";
		$firstDmaMainUserId = $this->cisession->userdata('primUserId');
		$secondDmaMainUserId = "";
		$msUserIdCreated = $this->cisession->userdata('userId');
		$alertShared = TRUE;
		$mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);
		*/

    $this->session->set_flashdata('paymentStatusUpdated', 1);

    if($transactionId > 0 && sizeof($trade['transactions']) > 1) {
      redirect('/dashboard/credit/' . $this->input->post('listingId') . '/multisale/' . $this->input->post('tradeId') . "/transaction/" . $this->input->post('transactionId'));
    } else {
      redirect('/dashboard/credit/' . $this->input->post('listingId') . '/sale/' . $this->input->post('tradeId'));
    }

  }

  function confirm_invite_email() {

    //Get data to check if email matches
    $data['email'] = $this->input->post('email');
    $iHash = $this->input->post('hash');
    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);
    $data['pwMinLength'] = ($data['invite']['iPwCharacterCountReq'] > 8) ? $data['invite']['iPwCharacterCountReq'] : ($data['invite']['iType'] == "signature" ? 6 : 8);

    //If emails are a match
    $data['email'] = str_replace(' ', '', $data['email']);
    $data['invite']['iEmail'] = str_replace(' ', '', $data['invite']['iEmail']);
    $data['loginPage'] = $data['invite']['iType'] . "Accept";
    $data['isSSO'] = false;

    if(strtolower($data['email']) == strtolower($data['invite']['iEmail'])) {

      if($data['invite']['iType'] == 'signatureCustom') {
        //create a signature link expiration of a few seconds
        $dRequest['dsId'] = $data['invite']['iContent1'];
        $dsAccessTime = $this->Docs->createDocSignatureLink($dRequest);

        $return['success'] = true;
        $return['dsId'] = $dRequest['dsId'];
        $return['dsAccessTime'] = $dsAccessTime;
        $json = json_encode($return);
        echo $json;

        //Setting if email address is connected to an existing user
      } else {
        //TODO: require SSO init here regardless
        if(strlen($data['invite']['external_auth_type']) > 0) {
          $data['isSSO'] = true;
        }

        if($this->input->post('emailExists')) {
          //Show login form
          $this->load->view("ajax/login_form", $data);
        } else {
          //Show register form
          $this->load->view("ajax/invite_register_form", $data);
        }
      }
    } else {
      //if emails do not match, return false
      if($data['invite']['iType'] == 'signatureCustom') {
        $return['success'] = false;
        $json = json_encode($return);
        echo $json;
      } else {
        return false;
      }
    }

  }

  function load_login_form() {

    $data['loginPage'] = $this->input->post('loginPage');
    $this->load->view("ajax/login_form", $data);
  }

  function load_mfa_form() {

    if($this->tank_auth->is_logged_in()) {
      $myEmailAddress = $this->Members_Model->get_member_email($this->cisession->userdata('userId'));
      $memberInfo = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
      $memberIPs = $this->Members_Model->get_member_ip_addresses($this->cisession->userdata('userId'));
      $data['ipAddressStatus'] = "new";
      foreach($memberIPs as $mIp) {
        $thisIpAddress = $this->Members_Model->get_ip();
        if($mIp['ipAddress'] == $thisIpAddress) {
          $data['ipAddressStatus'] = "existing";
          if($mIp['mfaStatus'] == 1) {
            $data['ipAddressStatus'] = "verified";
          }
        }
      }
    } else {
      throw new \Exception('General fail');
    }

    $data['email_address'] = $myEmailAddress['email_address'];
    $data['mobilePhone'] = $memberInfo['mobilePhone'];

    $data['loginPage'] = $this->input->post('loginDeepLink');
    $data['mfaType'] = $this->cisession->userdata('mfaType');
    $this->load->view("includes/widgets/loginMFA", $data);

  }

  function load_credit_selector() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->load->view("ajax/load_credit_selector");

  }

  function load_utilization_selector($loadConfig = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $cData['dmaId'] = $this->cisession->userdata('dmaId');
    $cData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
    //If Advisor
    //$cData['advisorStatus'] = preg_replace("/[^a-zA-Z0-9_]+/", "", $this->input->post('listAdvisorStatus'));

    $credits = $this->CreditListings->get_credits($cData);
    $data['credits'] = $credits['credits'];

    $data['loadConfig'] = $loadConfig;

    $this->load->view("ajax/load_utilization_selector", $data);

  }

  function getDateFields() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['fieldsType'] = $this->input->post('fieldsType');
    //Utilization
    $data['start_date_utilizationDate'] = $this->input->post('start_date_utilizationDate');
    $data['end_date_utilizationDate'] = $this->input->post('end_date_utilizationDate');
    //Credits
    $data['start_date_auditStartDate'] = $this->input->post('start_date_auditStartDate');
    $data['end_date_auditStartDate'] = $this->input->post('end_date_auditStartDate');
    $data['start_date_auditEndDate'] = $this->input->post('start_date_auditEndDate');
    $data['end_date_auditEndDate'] = $this->input->post('end_date_auditEndDate');

    $data['start_date_projectStartDate'] = $this->input->post('start_date_projectStartDate');
    $data['end_date_projectStartDate'] = $this->input->post('end_date_projectStartDate');
    $data['start_date_projectEndDate'] = $this->input->post('start_date_projectEndDate');
    $data['end_date_projectEndDate'] = $this->input->post('end_date_projectEndDate');

    $data['start_date_projectStartDate'] = $this->input->post('start_date_projectStartDate');
    $data['end_date_projectStartDate'] = $this->input->post('end_date_projectStartDate');
    $data['start_date_projectEndDate'] = $this->input->post('start_date_projectEndDate');
    $data['end_date_projectEndDate'] = $this->input->post('end_date_projectEndDate');

    //certInitial
    $data['start_date_certInitialDate'] = $this->input->post('start_date_certInitialDate');
    $data['end_date_certInitialDate'] = $this->input->post('end_date_certInitialDate');
    //certFinal
    $data['start_date_certFinalDate'] = $this->input->post('start_date_certFinalDate');
    $data['end_date_certFinalDate'] = $this->input->post('end_date_certFinalDate');
    //creditIssue
    $data['start_date_creditIssueDate'] = $this->input->post('start_date_creditIssueDate');
    $data['end_date_creditIssueDate'] = $this->input->post('end_date_creditIssueDate');
    //lastDayPrincPhotoDate
    $data['start_date_lastDayPrincPhotoDate'] = $this->input->post('start_date_lastDayPrincPhotoDate');
    $data['end_date_lastDayPrincPhotoDate'] = $this->input->post('end_date_lastDayPrincPhotoDate');
    //start_date_loadedDate
    $data['start_date_loadedDate'] = $this->input->post('start_date_loadedDate');
    $data['end_date_loadedDate'] = $this->input->post('end_date_loadedDate');

    $data['fieldsToShow'] = [];

    if($data['fieldsType'] == "utilizations" || $data['fieldsType'] == "reports_utilizations") {
      $data['fieldsToShow'] = array_merge($data['fieldsToShow'], ['utilizationDate']);
    }
    if($data['fieldsType'] == "mycredits" || $data['fieldsType'] == "reports_mycredits") {
      $data['fieldsToShow'] = array_merge($data['fieldsToShow'], ['auditDate', 'projectDate', 'certInitialDate', 'certFinalDate', 'creditIssueDate', 'loadedDate']);
      if($this->cisession->userdata('dmaCategory') == "entertainment") {
        array_push($data['fieldsToShow'], 'lastDayPrincPhotoDate');
      }
    }

    $data['planData'] = $this->cisession->userdata('planData');

    $this->load->view("ajax/date_ranges", $data);

  }

  function getSearchFields() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $accountId = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('accountId'));
    /*
		$data['fieldsType'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('fieldsType'));
		$data['status'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('status'));
		$data['actions'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('actions'));
		$data['type'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('type'));
		$data['projects'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('projects'));
		$data['jurisdictions'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('jurisdictions'));
		$data['taxyears'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('taxyears'));
		$data['certStatus'] = preg_replace("/[^a-zA-Z0-9]+/", "", $this->input->post('certStatus'));
		*/
    $data['fieldsType'] = $this->input->post('fieldsType');
    $data['fieldsSubType'] = $this->input->post('fieldsSubType');
    $data['taxEntities'] = $this->input->post('taxpayerId');
    $data['sharedAccount'] = $this->input->post('sharedAccount');
    $data['listingId'] = $this->input->post('listingId');
    $data['status'] = $this->input->post('status');
    $data['cOrigin'] = $this->input->post('cOrigin');
    $data['type'] = $this->input->post('type');
    $data['projects'] = $this->input->post('projects');
    $data['jurisdictions'] = $this->input->post('jurisdictions');
    $data['countries'] = $this->input->post('countries');
    $data['provinces'] = $this->input->post('provinces');
    $data['counties'] = $this->input->post('counties');
    $data['towns'] = $this->input->post('towns');
    $data['incentiveprogram'] = $this->input->post('incentiveprogram');
    $data['taxyears'] = $this->input->post('taxyears');
    $data['showArchived'] = $this->input->post('showArchived');
    $data['certStatus'] = $this->input->post('certStatus');
    $data['monetizationStatus'] = $this->input->post('monetizationStatus');
    $data['auditStatus'] = $this->input->post('auditStatus');
    $data['projectStatus'] = $this->input->post('projectStatus');
    $data['advisorStatus'] = $this->input->post('advisorStatus');
    $data['creditUtilizationStatus'] = $this->input->post('creditUtilizationStatus');
    $data['harvestStatus'] = $this->input->post('harvestStatus');
    $data['showSearchButtons'] = $this->input->post('showSearchButtons');
    $data['customerAccessStatus'] = $this->input->post('customerAccessStatus');
    //Advisor
    $data['advisorRepresentative'] = $this->input->post('advisorRepresentative');
    $data['advisorProjectAssignedTo'] = $this->input->post('advisorProjectAssignedTo');
    //Utilization
    $data['utilizationTypes'] = $this->input->post('utilizationTypes');
    $data['utilizationStatus'] = $this->input->post('utilizationStatus');
    //time range
    $data['accountId'] = $accountId;

    $data['planData'] = $this->cisession->userdata('planData');

    //These fields are for some API calls below
    $sData['dmaId'] = $this->cisession->userdata('dmaId');
    $sData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
    $sData['id'] = ($data['sharedAccount'] == "self") ? $this->cisession->userdata('primUserId') : null;
    $sData['sharedAccount'] = $data['sharedAccount'];
    $sData['account'] = $data['sharedAccount'];
    $sData['shared'] = ($data['sharedAccount'] != "") ? ($data['sharedAccount'] != "self" ? $this->cisession->userdata('primUserId') : null) : null;

    //$data['credit_types'] = $this->IncentivePrograms->get_program_types();
    //$data['credit_types'] = array(0 => "Select Credit Type") + $data['credit_types'];

    $data['fieldsToShow'] = ['listStatus', 'listOrigin', 'listTaxEntities', 'listCreditUtilizationStatus', 'listTypes', 'listCountries', 'listProvinces', 'listCounties', 'listTowns', 'listIncentivePrograms', 'listShowArchived', 'listCertStatus', 'listAuditStatus', 'listMonetizationStatus', 'listProjectStatus', 'listProjects', 'listTaxyears', 'listSharedAccounts', 'listListingId'];
    if($data['fieldsType'] == "utilizations" || $data['fieldsType'] == "reports_utilizations") {
      $data['fieldsToShow'] = array_merge($data['fieldsToShow'], ['listUtilizationStatus', 'listUtilizationTypes']);
    }
    if($this->cisession->userdata('dmaType') == "advisor") {
      array_push($data['fieldsToShow'], 'listAdvisorStatus', 'listShowCustomerAccessStatus', 'listAdvisorRepresentative', 'listAdvisorProjectAssignedTo');
    }
    $data['dd_taxentities'] = $this->CreditListings->get_filter_data_by_account('taxentities', $sData);
    //$data['dd_taxentities'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'));
    $data['dd_taxyears'] = $this->CreditListings->get_filter_data_by_account('taxyears', $sData);
    $data['dd_jurisdictions'] = $this->CreditListings->get_filter_data_by_account('jurisdictions', $sData);
    $data['dd_countries'] = $this->CreditListings->get_filter_data_by_account('countries', $sData);
    $data['dd_provinces'] = $this->CreditListings->get_filter_data_by_account('provinces', $sData);
    $data['dd_counties'] = $this->CreditListings->get_filter_data_by_account('counties', $sData);
    $data['dd_towns'] = $this->CreditListings->get_filter_data_by_account('towns', $sData);
    $data['dd_incentiveprogram'] = $this->CreditListings->get_filter_data_by_account('incentiveprogram', $sData);
    $data['dd_projects'] = $this->CreditListings->get_filter_data_by_account('projects', $sData);
    $data['dd_programtypes'] = $this->CreditListings->get_filter_data_by_account('credit_types', $sData);
    $data['dd_certStatus'] = $this->CreditListings->get_filter_data_by_account('certStatus', $sData);
    $data['dd_auditStatus'] = $this->CreditListings->get_filter_data_by_account('auditStatus', $sData);
    $data['dd_monetizationStatus'] = $this->CreditListings->get_filter_data_by_account('monetizationStatus', $sData);
    $data['dd_projectStatus'] = $this->CreditListings->get_filter_data_by_account('projectStatus', $sData);
    $data['dd_sharedAccounts'] = $this->DmaAccounts->get_shared_accounts_of_dma_account($this->cisession->userdata('dmaId'), "credit");
    $data['dd_adminUsers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
    //$data['dd_utilizationTypes'] = $this->Trading->get_utilization_types();
    $data['dd_utilizationTypes'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('credit_utilization_type');

    $data['dd_origins'] = [['origin' => 'loaded', 'originName' => 'Loaded "Earned" Credit'], ['origin' => 'loaded_purchase', 'originName' => 'Purchased Credit']];
    $data['dd_creditUtilizationStatus'] = [['cusId' => 'utilized_none', 'cusName' => 'Has No Utilizations'], ['cusId' => 'utilized_partial', 'cusName' => 'Is Partially Utilized'], ['cusId' => 'utilized_full', 'cusName' => 'Is Fully Utilized']];
    $data['dd_advisorStatus'] = [['advStatusId' => 1, 'advStatusName' => 'Pending'], ['advStatusId' => 2, 'advStatusName' => 'Active'], ['advStatusId' => 3, 'advStatusName' => 'Completed'], ['advStatusId' => 9, 'advStatusName' => 'Cancelled']];
    $data['dd_utilizationStatus'] = [['usId' => 1, 'usName' => 'Estimated'], ['usId' => 2, 'usName' => 'Actual']];

    if($data['fieldsSubType'] == "analytics_summary" || $data['fieldsSubType'] == "analytics_estimated_payment_dates" || $data['fieldsSubType'] == "analytics_date_range_analyzer" || $data['fieldsSubType'] == "analytics_advisor_fee_forecaster") {
      array_push($data['fieldsToShow'], 'dateCreditLoaded');
    }

    //JURISDICTIONS
    $data['selected_jurisdictions'] = [];
    $selected_jurisdictions = [];
    if(!empty($data['jurisdictions'])) {
      $selected_jurisdictions = explode(',', $data['jurisdictions']);
      foreach($data['dd_jurisdictions'] as $ddj) {
        if(in_array($ddj['state'], $selected_jurisdictions)) {
          array_push($data['selected_jurisdictions'], $ddj);
        }
      }
    }
    //COUNTRIES
    $data['selected_countries'] = [];
    $selected_countries = [];
    if(!empty($data['countries'])) {
      $selected_countries = explode(',', $data['countries']);
      foreach($data['dd_countries'] as $ddj) {
        if(in_array($ddj['id'], $selected_countries)) {
          array_push($data['selected_countries'], $ddj);
        }
      }
    }
    //PROVINCES
    $data['selected_provinces'] = [];
    $selected_provinces = [];
    if(!empty($data['provinces'])) {
      $selected_provinces = explode(',', $data['provinces']);
      foreach($data['dd_provinces'] as $ddj) {
        if(in_array($ddj['id'], $selected_provinces)) {
          array_push($data['selected_provinces'], $ddj);
        }
      }
    }
    //COUNTIES
    $data['selected_counties'] = [];
    $selected_counties = [];
    if(!empty($data['counties'])) {
      $selected_counties = explode(',', $data['counties']);
      foreach($data['dd_counties'] as $ddj) {
        if(in_array($ddj['id'], $selected_counties)) {
          array_push($data['selected_counties'], $ddj);
        }
      }
    }
    //TOWNS
    $data['selected_towns'] = [];
    $selected_towns = [];
    if(!empty($data['towns'])) {
      $selected_towns = explode(',', $data['towns']);
      foreach($data['dd_towns'] as $ddj) {
        if(in_array($ddj['id'], $selected_towns)) {
          array_push($data['selected_towns'], $ddj);
        }
      }
    }
    //Incentive Programs
    $data['selected_incentiveprogram'] = [];
    $selected_incentiveprogram = [];
    if(!empty($data['incentiveprogram'])) {
      $selected_incentiveprogram = explode(',', $data['incentiveprogram']);
      foreach($data['dd_incentiveprogram'] as $ddip) {
        if(in_array($ddip['OIXIncentiveId'], $selected_incentiveprogram)) {
          array_push($data['selected_incentiveprogram'], $ddip);
        }
      }
    }
    //TAX YEARS
    $data['selected_taxyears'] = [];
    $selected_taxyears = [];
    if(!empty($data['taxyears'])) {
      $selected_taxyears = explode(',', $data['taxyears']);
      foreach($data['dd_taxyears'] as $ddy) {
        if(in_array($ddy['taxYearId'], $selected_taxyears) && $ddy['taxYear'] > 0) {
          array_push($data['selected_taxyears'], $ddy);
        }
      }
    }
    //CREDIT TYPES
    $data['selected_programtypes'] = [];
    $selected_programtypes = [];
    if(!empty($data['type'])) {
      $selected_programtypes = explode(',', $data['type']);
      foreach($data['dd_programtypes'] as $ddp) {
        if(in_array($ddp['ProgramTypeId'], $selected_programtypes)) {
          array_push($data['selected_programtypes'], $ddp);
        }
      }
    }
    //PROJECTS
    $data['selected_projects'] = [];
    $selected_projects = [];
    if(!empty($data['projects'])) {
      $thisProjects = str_replace("oixapostrophetag", "'", str_replace('oixquotetag', '"', $data['projects']));
      $selected_projects = explode('----', $thisProjects);
      foreach($data['dd_projects'] as $ddp) {
        if(in_array($ddp['stateCertNum'], $selected_projects)) {
          array_push($data['selected_projects'], $ddp);
        }
      }
    }
    //ORIGINS
    $data['selected_origins'] = [];
    $selected_origins = [];
    if(!empty($data['cOrigin'])) {
      $selected_origins = explode(',', $data['cOrigin']);
      foreach($data['dd_origins'] as $ddo) {
        if(in_array($ddo['origin'], $selected_origins)) {
          array_push($data['selected_origins'], $ddo);
        }
      }
    }
    //CREDIT UTILIZATION STATUS
    $data['selected_creditutilizationstatus'] = [];
    $selected_creditutilizationstatus = [];
    if(!empty($data['creditUtilizationStatus'])) {
      $selected_creditutilizationstatus = explode(',', $data['creditUtilizationStatus']);
      foreach($data['dd_creditUtilizationStatus'] as $ddu) {
        if(in_array($ddu['cusId'], $selected_creditutilizationstatus)) {
          array_push($data['selected_creditutilizationstatus'], $ddu);
        }
      }
    }
    //LEGAL ENTITIES
    $data['selected_taxentities'] = [];
    $selected_taxentities = [];
    if(!empty($data['taxEntities'])) {
      $selected_taxentities = explode(',', $data['taxEntities']);
      foreach($data['dd_taxentities'] as $ddt) {
        if(in_array($ddt['taxpayerId'], $selected_taxentities)) {
          array_push($data['selected_taxentities'], $ddt);
        }
      }
    }
    //Accounts
    $data['selected_accounts'] = [];
    $selected_accounts = [];
    if(!empty($data['sharedAccount'])) {
      $selected_accounts = explode(',', $data['sharedAccount']);
      foreach($data['dd_sharedAccounts'] as $dda) {
        if(in_array($dda['dmaId'], $selected_accounts)) {
          array_push($data['selected_accounts'], $dda);
        }
      }
      if(in_array($this->cisession->userdata('dmaId'), $selected_accounts)) {
        $myAccountArray = ["dmaId" => $this->cisession->userdata('dmaId'), "title" => $this->cisession->userdata('dmaTitle')];
        array_push($data['selected_accounts'], $myAccountArray);
      }
    }
    //STATUS - CERTIFICATION
    $data['selected_certstatus'] = [];
    $selected_certstatus = [];
    if(!empty($data['certStatus'])) {
      $selected_certstatus = explode(',', $data['certStatus']);
      foreach($data['dd_certStatus'] as $ddt) {
        if(in_array($ddt['cert_status_id'], $selected_certstatus)) {
          array_push($data['selected_certstatus'], $ddt);
        }
      }
    }
    //STATUS - MONETIZATION
    $data['selected_monetizationstatus'] = [];
    $selected_monetizationstatus = [];
    if(!empty($data['monetizationStatus'])) {
      $selected_monetizationstatus = explode(',', $data['monetizationStatus']);
      foreach($data['dd_monetizationStatus'] as $ddt) {
        if(in_array($ddt['mnsId'], $selected_monetizationstatus)) {
          array_push($data['selected_monetizationstatus'], $ddt);
        }
      }
    }
    //STATUS - AUDIT
    $data['selected_auditstatus'] = [];
    $selected_auditstatus = [];
    if(!empty($data['auditStatus'])) {
      $selected_auditstatus = explode(',', $data['auditStatus']);
      foreach($data['dd_auditStatus'] as $dds) {
        if(in_array($dds['auditStatusId'], $selected_auditstatus)) {
          array_push($data['selected_auditstatus'], $dds);
        }
      }
    }
    //STATUS - PROJECT
    $data['selected_projectstatus'] = [];
    $selected_projectstatus = [];
    if(!empty($data['projectStatus'])) {
      $selected_projectstatus = explode(',', $data['projectStatus']);
      foreach($data['dd_projectStatus'] as $dds) {
        if(in_array($dds['statusId'], $selected_projectstatus)) {
          array_push($data['selected_projectstatus'], $dds);
        }
      }
    }
    //STATUS - PROJECT
    $data['selected_advisorstatus'] = [];
    $selected_advisorstatus = [];
    if(!empty($data['advisorStatus'])) {
      $selected_advisorstatus = explode(',', $data['advisorStatus']);
      foreach($data['dd_advisorStatus'] as $dds) {
        if(in_array($dds['advStatusId'], $selected_advisorstatus)) {
          array_push($data['selected_advisorstatus'], $dds);
        }
      }
    }
    //STATUS - UTIlIZATION STATUS
    $data['selected_utilizationstatus'] = [];
    $selected_utilizationstatus = [];
    if(!empty($data['utilizationStatus'])) {
      $selected_utilizationstatus = explode(',', $data['utilizationStatus']);
      foreach($data['dd_utilizationStatus'] as $dds) {
        if(in_array($dds['usId'], $selected_utilizationstatus)) {
          array_push($data['selected_utilizationstatus'], $dds);
        }
      }
    }
    //STATUS - UTIlIZATION TYPE
    $data['selected_utilizationtypes'] = [];
    $selected_utilizationtypes = [];
    if(!empty($data['utilizationTypes'])) {
      $selected_utilizationtypes = explode(',', $data['utilizationTypes']);
      foreach($data['dd_utilizationTypes'] as $k => $v) {
        if(in_array($k, $selected_utilizationtypes)) {
          $data['selected_utilizationtypes'][$k] = $v;
        }
      }
    }
    //ADVISOR REPRESENTATIVES
    $data['selected_AdvisorRepresentatives'] = [];
    $selected_AdvisorRepresentatives = [];
    if(!empty($data['advisorRepresentative'])) {
      $selected_AdvisorRepresentatives = explode(',', $data['advisorRepresentative']);
      foreach($data['dd_adminUsers'] as $ddau) {
        if(in_array($ddau['userId'], $selected_AdvisorRepresentatives)) {
          array_push($data['selected_AdvisorRepresentatives'], $ddau);
        }
      }
    }
    //ADVISOR Assigned to
    $data['selected_AdvisorProjectAssignedTo'] = [];
    $selected_AdvisorProjectAssignedTo = [];
    if(!empty($data['advisorProjectAssignedTo'])) {
      $selected_AdvisorProjectAssignedTo = explode(',', $data['advisorProjectAssignedTo']);
      foreach($data['dd_adminUsers'] as $ddau) {
        if(in_array($ddau['userId'], $selected_AdvisorProjectAssignedTo)) {
          array_push($data['selected_AdvisorProjectAssignedTo'], $ddau);
        }
      }
    }

    $this->load->view("ajax/filters", $data);

  }

  /*

	function getCreditSearchFields($searchURL, $accountId="", $status="", $actions="", $type="", $projects="", $jurisdictions="", $taxyears="", $certStatus="") {

		$searchURL = preg_replace("/[^a-zA-Z0-9]+/", "", $searchURL);
		$accountId = preg_replace("/[^a-zA-Z0-9]+/", "", $accountId);
		$status = preg_replace("/[^a-zA-Z0-9]+/", "", $status);
		$actions = preg_replace("/[^a-zA-Z0-9]+/", "", $actions);
		$type = preg_replace("/[^a-zA-Z0-9]+/", "", $type);
		$projects = preg_replace("/[^a-zA-Z0-9]+/", "", $projects);
		$jurisdictions = preg_replace("/[^a-zA-Z0-9]+/", "", $jurisdictions);
		$taxyears = preg_replace("/[^a-zA-Z0-9]+/", "", $taxyears);
		$certStatus = preg_replace("/[^a-zA-Z0-9]+/", "", $certStatus);

		if($searchURL=="sharedcredits") {
			$secPart = (is_numeric($accountId)) ? "/".$accountId : "/allaccounts";
			$searchURL = $searchURL.$secPart;
		}
		$data['searchURL'] = $searchURL;
		$data['status'] = $status;
		$data['actions'] = $actions;
		$data['type'] = $type;
		$data['projects'] = $projects;
		$data['jurisdictions'] = $jurisdictions;
		$data['taxyears'] = $taxyears;
		$data['certStatus'] = $certStatus;

		$data['credit_types'] = $this->IncentivePrograms->get_program_types();
		$data['credit_types'] = array(0 => "Select Credit Type") + $data['credit_types'];

		if($accountId>0) {
			$data['dd_projects'] = $this->CreditListings->get_account_credit_projects($accountId);
			$data['dd_jurisdictions'] = $this->CreditListings->get_account_credit_jurisdictions($accountId);
			$data['dd_taxyears'] = $this->CreditListings->get_account_credit_taxyears($accountId);
		} else if($searchURL=="sharedcredits" || $searchURL=="reportssharedcredits") {
			$data['dd_projects'] = $this->CreditListings->get_shared_credit_projects($this->cisession->userdata('mainAdmin'));
			$data['dd_jurisdictions'] = $this->CreditListings->get_shared_credit_jurisdictions($this->cisession->userdata('mainAdmin'));
			$data['dd_taxyears'] = $this->CreditListings->get_shared_credit_taxyears($this->cisession->userdata('mainAdmin'));
		}
		$data['dd_certStatus'] = $this->IncentivePrograms->get_cert_status();

		$data['filterData'] = $data;

		if($searchURL=="reportsmycredits" || $searchURL=="reportssharedcredits") {
			$this->load->view("ajax/creditFilters", $data);
		} else {
			$this->load->view("ajax/creditSearch", $data);
		}


	}
	*/

  function setCreditListPreference($listViewTemplate, $listOrder, $listView) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $listViewTemplate = preg_replace("/[^a-zA-Z0-9_]+/", "", $listViewTemplate);
    $listOrder = preg_replace("/[^a-zA-Z0-9_]+/", "", $listOrder);
    $listView = preg_replace("/[^a-zA-Z0-9_]+/", "", $listView);

    $userId = $this->cisession->userdata('userId');
    //$this->Members_Model->updateMemberPreference($userId, 'creditListViewPref', $listViewTemplate);
    $this->Members_Model->updateMemberPreference($userId, 'creditListOrder', $listOrder);
    $this->Members_Model->updateMemberPreference($userId, 'creditListSections', $listView);
    //$this->cisession->set_userdata('creditListViewPref', $listViewTemplate);
    $this->cisession->set_userdata('creditListOrder', $listOrder);
    $this->cisession->set_userdata('creditListSections', $listView);

    $this->session->set_flashdata('creditListPreferenceChange', $setting);

    echo "success";

  }

  function setCreditHeaderConfig($creditHeaderConfig) {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $creditHeaderConfig = preg_replace("/[^a-zA-Z0-9_]+/", "", $creditHeaderConfig);

    $userId = $this->cisession->userdata('userId');
    $this->Members_Model->updateMemberPreference($userId, 'creditHeaderConfig', $creditHeaderConfig);
    $this->cisession->set_userdata('creditHeaderConfig', $creditHeaderConfig);

    echo "success";

  }

  function attach_workflow() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    if($_POST["workflowAttachedType"] == "credit") {

      //CHECK CREDIT ACCESS
      $this->memberpermissions->checkCreditAccess($_POST['workflowAttachedId']);

      //CHECK WORKFLOW TEMPLATE ACCESS
      $this->memberpermissions->checkWorkflowTemplateAccess($_POST["workflowTemplateId"]); //redirect if no access

      //Add workflow template ID to pending listing
      $dbField = ($_POST["workflowType"] == 'compliance') ? "cComplianceId" : "cWorkflowId";
      $this->CreditListings->update_pending_listing_field($_POST['workflowAttachedId'], $dbField, $_POST["workflowTemplateId"]);

      //Add empty workflow item values
      $workflow = $this->Workflow->get_workflow($_POST["workflowTemplateId"]);
      foreach($workflow['allwlItems'] as $wi) {
        $wRequest['wiId'] = $wi['wiId'];
        $wRequest['wvAttachedToType'] = "credit";
        $wRequest['wvAttachedToId'] = $_POST['workflowAttachedId'];
        $wRequest['wiStatus'] = 0;
        $wRequest['wiValue'] = null;
        $this->Workflow->insert_workflow_item_value($wRequest);
      }

      $listingId = $_POST['workflowAttachedId'];

      //Update credit update time
      $this->CreditListings->updateTime($listingId);

      $creditData = $this->CreditListings->get_credit_private($listingId);

      //Insert alert message
      if($_POST["workflowType"] == 'compliance') {
        $lower = "compliance";
        $upper = "Compliance";
      } else {
        $lower = "workflow";
        $upper = "Workflow";
      }

      $msType = "update";
      $msAction = $lower . "_attached";
      $msListingId = $listingId;
      $msBidId = "";
      $msTradeId = "";
      $msTitle = $upper . " Template '" . $workflow['wTemplateName'] . "' Attached to " . $creditData['projectNameFull'] . " (" . $creditData['state'] . $listingId . ")";
      $msTitle2 = "";
      $msTitleShared = $msTitle;
      $msTitleShort = $upper . " Template '" . $workflow['wTemplateName'] . "' Attached";
      $msTitle2Short = "";
      $msTitleSharedShort = $msTitleShort;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $this->cisession->userdata('primUserId');
      $secondDmaMainUserId = "";
      $msUserIdCreated = $this->cisession->userdata('userId');
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, "");

      //Setup success message
      if($_POST["workflowType"] == 'compliance') {
        $this->session->set_flashdata('complianceAddedSuccess', 1);
        //redirect
        redirect('/dashboard/credit/' . $_POST["workflowAttachedId"] . "/compliance");
      } else {
        $this->session->set_flashdata('workflowAddedSuccess', 1);
        //redirect
        redirect('/dashboard/credit/' . $_POST["workflowAttachedId"] . "/workflow");
      }
    }

    if($_POST["workflowAttachedType"] == "transaction") {

      $transactionId = $_POST['workflowAttachedId'];
      $workflowId = $_POST["workflowTemplateId"];

      $transactionData = $this->Trading->get_transaction_by_id($transactionId);
      $tradeData = $this->Trading->get_trade($transactionData['tradeId']);
      $trade = $tradeData['trade'];

      //CHECK CREDIT ACCESS
      $this->memberpermissions->checkCreditAccess($transactionData['listingId']);

      //CHECK WORKFLOW TEMPLATE ACCESS
      $this->memberpermissions->checkWorkflowTemplateAccess($workflowId); //redirect if no access

      //Add workflow template ID to transaction
      $transUpdateArray = [];
      if($_POST["workflowType"] == "compliance") {
        $transUpdateArray['transComplianceId'] = $workflowId;
      } else {
        $transUpdateArray['transWorkflowId'] = $workflowId;
      }
      $this->Trading->update_transaction($transactionId, $transUpdateArray);
      //Add empty workflow item values
      $workflow = $this->Workflow->get_workflow($workflowId);
      foreach($workflow['allwlItems'] as $wi) {
        $wRequest['wiId'] = $wi['wiId'];
        $wRequest['wvAttachedToType'] = "transaction";
        $wRequest['wvAttachedToId'] = $transactionId;
        $wRequest['wiStatus'] = 0;
        $wRequest['wiValue'] = null;
        $this->Workflow->insert_workflow_item_value($wRequest);
      }

      $listingId = $transactionData['listingId'];

      //Update credit update time
      $this->CreditListings->updateTime($listingId);

      /*
			$creditData = $this->CreditListings->get_credit_private($listingId);

			//Insert alert message
			if($_POST["workflowType"]=='compliance') {
				$lower = "compliance";
				$upper = "Compliance";
			} else {
				$lower = "workflow";
				$upper = "Workflow";
			}

			$msType = "update";
			$msAction = $lower."_attached";
			$msListingId = $listingId;
			$msBidId = "";
			$msTradeId = "";
			$msTitle = $upper." Template '".$workflow['wTemplateName']."' Attached to ".$creditData['projectNameFull']." (".$creditData['state'].$listingId.")";
			$msTitle2 = "";
			$msTitleShared = $msTitle;
			$msTitleShort = $upper." Template '".$workflow['wTemplateName']."' Attached";
			$msTitle2Short = "";
			$msTitleSharedShort = $msTitleShort;
			$msContent = "";
			$msContent2 = "";
			$msPerspective = "seller";
			$msPerspective2 = "";
			$firstDmaMainUserId = $this->cisession->userdata('primUserId');
			$secondDmaMainUserId = "";
			$msUserIdCreated = $this->cisession->userdata('userId');
			$alertShared = TRUE;
			$mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, "");
			*/

      $workflowTab = ($_POST["workflowType"] == "compliance") ? "compliance_tab" : "workflow_tab";

      //Setup success message
      $this->session->set_flashdata('workflowAddedSuccess', 1);
      //redirect
      if(sizeof($trade['transactions']) > 1) {
        redirect('/dashboard/credit/' . $listingId . '/multisale/' . $trade['tradeId'] . "/transaction/" . $transactionId);
      } else {
        redirect('/dashboard/credit/' . $listingId . '/utilization/' . $trade['tradeId'] . '/' . $workflowTab);
      }

    }

  }

  function disconnect_workflow() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    $wWorkflowType = ($_POST["workflowType"] == 'compliance') ? "compliance" : "workflow";

    if($_POST["workflowAttachedType"] == "credit") {

      //CHECK ACCESS
      $this->memberpermissions->checkCreditAccess($_POST['workflowAttachedId']);

      $listingId = $_POST['workflowAttachedId'];
      $creditData = $this->CreditListings->get_credit_private($listingId);
      $workflow = $this->Workflow->get_workflow($creditData["cWorkflowId"]);

      //Get the template data
      $dbField = ($_POST["workflowType"] == 'compliance') ? "cComplianceId" : "cWorkflowId";
      $this->CreditListings->update_pending_listing_field($_POST['workflowAttachedId'], $dbField, null);

      //Delete all the workflow item values attached to this listing (so if you re-add this worklfow template, they dont show again)
      $workflowItemValuesToDelete = $this->Workflow->get_workflow_item_values('credit', $wWorkflowType, $listingId);

      $workflowItemValuesIdsToDelete = [];
      foreach($workflowItemValuesToDelete as $item) {
        array_push($workflowItemValuesIdsToDelete, $item['wvId']);
      }
      $this->Workflow->delete_workflow_item_values($workflowItemValuesIdsToDelete);

      //Update credit update time
      $this->CreditListings->updateTime($listingId);

      //Insert alert message
      if($_POST["workflowType"] == 'compliance') {
        $lower = "compliance";
        $upper = "Compliance";
      } else {
        $lower = "workflow";
        $upper = "Workflow";
      }

      //Insert alert message
      $msType = "update";
      $msAction = $lower . "_dettached";
      $msListingId = $listingId;
      $msBidId = "";
      $msTradeId = "";
      $msTitle = $upper . " Template '" . $workflow['wTemplateName'] . "' Disconnected from " . $creditData['projectNameFull'] . " (" . $creditData['state'] . $listingId . ")";
      $msTitle2 = "";
      $msTitleShared = $msTitle;
      $msTitleShort = $upper . " Template '" . $workflow['wTemplateName'] . "' Disconnected";
      $msTitle2Short = "";
      $msTitleSharedShort = $msTitleShort;
      $msContent = "";
      $msContent2 = "";
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $this->cisession->userdata('primUserId');
      $secondDmaMainUserId = "";
      $msUserIdCreated = $this->cisession->userdata('userId');
      $alertShared = true;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, "");

      //Setup success message
      if($_POST["workflowType"] == 'compliance') {
        $this->session->set_flashdata('complianceDeletedSuccess', 1);
        //redirect
        redirect('/dashboard/credit/' . $_POST["workflowAttachedId"] . "/compliance");
      } else {
        $this->session->set_flashdata('workflowDeletedSuccess', 1);
        //redirect
        redirect('/dashboard/credit/' . $_POST["workflowAttachedId"] . "/workflow");
      }

    }

    if($_POST["workflowAttachedType"] == "transaction") {

      $transactionId = $_POST['workflowAttachedId'];

      $transactionData = $this->Trading->get_transaction_by_id($transactionId);
      $tradeData = $this->Trading->get_trade($transactionData['tradeId']);
      $trade = $tradeData['trade'];

      $listingId = $transactionData['listingId'];

      //CHECK CREDIT ACCESS
      $this->memberpermissions->checkCreditAccess($listingId);

      //Remove workflow template ID from transaction
      $transUpdateArray = [];
      if($wWorkflowType == "compliance") {
        $transUpdateArray['transComplianceId'] = null;
      } else {
        $transUpdateArray['transWorkflowId'] = null;
      }
      $this->Trading->update_transaction($transactionId, $transUpdateArray);

      //Delete all the workflow item values attached to this listing (so if you re-add this worklfow template, they dont show again)
      $workflowItemValuesToDelete = $this->Workflow->get_workflow_item_values('transaction', $wWorkflowType, $transactionId);

      $workflowItemValuesIdsToDelete = [];
      foreach($workflowItemValuesToDelete as $item) {
        array_push($workflowItemValuesIdsToDelete, $item['wvId']);
      }
      $this->Workflow->delete_workflow_item_values($workflowItemValuesIdsToDelete);

      $this->session->set_flashdata('workflowDeletedSuccess', 1);
      $workflowTab = ($wWorkflowType == "compliance") ? "compliance_tab" : "workflow_tab";
      //redirect
      if(sizeof($trade['transactions']) > 1) {
        redirect('/dashboard/credit/' . $listingId . '/multisale/' . $trade['tradeId'] . "/transaction/" . $transactionId);
      } else {
        redirect('/dashboard/credit/' . $listingId . '/utilization/' . $trade['tradeId'] . '/' . $workflowTab);
      }

    }

  }

  function update_workflow_item() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    $owner_of_credit_primary_account_id = 0;

    if($_POST["workflowAttachedType"] == "credit") {
      $listingId = $_POST['workflowAttachedId'];
    }

    if($_POST["workflowAttachedType"] == "transaction") {

      $transactionId = $_POST['workflowAttachedId'];
      $transactionData = $this->Trading->get_transaction_by_id($transactionId);
      $tradeData = $this->Trading->get_trade($transactionData['tradeId']);
      $trade = $tradeData['trade'];
      $listingId = $trade['listingId'];
      $owner_of_credit_primary_account_id = $trade['primary_account_id'];
    }

    $wWorkflowType = $_POST['wWorkflowType'];

    //CHECK ACCESS
    $this->memberpermissions->checkCreditAccess($listingId, "", "creditEditorOnly");

    $wiValue = null;

    //If CONNECTED TO CREDIT DATA (meaning, this is going to modify credit details as well)
    if($_POST['dpValue'] != "") {

      $estCreditValVarianceFlag = false;

      //Get credit data so we can run comparisons against it to see if things have changed
      $creditData = $this->CreditListings->get_credit_private($_POST['workflowAttachedId']);

      $owner_of_credit_primary_account_id = $creditData['primary_account_id'];

      //ANY OTHER CONNECTED DATA POINT
      //Get data point information
      $dataPointType = $_POST['dpValue'];
      $dpRequest['dpValue'] = $dataPointType;
      $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
      $dpRequest['dpObjectType'] = 'credit';
      $dataPointInfo = $this->CreditListings->get_data_points($dpRequest);
      $dataPointInfo = $dataPointInfo['dataPoints'][0]; //Pick the first in the array as that's is the only one returned
      $auditId = $dataPointInfo['dpAuditTrailId'];
      //
      if($dataPointInfo['dpType'] == 'currencyNoDecimal') {
        $wiValue = preg_replace("/[^0-9]/", "", $_POST[$dataPointType]);
        if($creditData[$dataPointType] > 0) {
          $value1 = "$" . number_format($creditData[$dataPointType]);
        } else {
          $value1 = "(none)";
        }
        if($wiValue != "") {
          $value2 = "$" . number_format($wiValue);
        } else {
          $value2 = "(none)";
        }
      } else {
        if($dataPointInfo['dpType'] == 'currencyTwoDecimal') {
          $wiValue = preg_replace("/[^0-9]/", "", $_POST[$dataPointType]);
          if($creditData[$dataPointType] > 0) {
            $value1 = "$" . number_format($creditData[$dataPointType], 2);
          } else {
            $value1 = "(none)";
          }
          if($wiValue != "") {
            $value2 = "$" . number_format($wiValue, 2);
          } else {
            $value2 = "(none)";
          }
        } else {
          if($dataPointInfo['dpType'] == 'currencyFourDecimal') {
            $wiValue = preg_replace("/[^0-9]/", "", $_POST[$dataPointType]);
            if($creditData[$dataPointType] > 0) {
              $value1 = "$" . number_format($creditData[$dataPointType], 4);
            } else {
              $value1 = "(none)";
            }
            if($wiValue != "") {
              $value2 = "$" . number_format($wiValue, 4);
            } else {
              $value2 = "(none)";
            }
          } else {
            if($dataPointInfo['dpType'] == 'date') {
              $wiValue = strtotime($_POST[$dataPointType]);
              if($creditData[$dataPointType] > 0) {
                $value1 = date('m/d/Y', $creditData[$dataPointType]);
              } else {
                $value1 = "(none)";
              }
              if($wiValue != "") {
                $value2 = date('m/d/Y', $wiValue);
              } else {
                $value2 = "(none)";
              }
            } else {
              if($dataPointInfo['dpType'] == 'text') {
                $wiValue = $_POST[$dataPointType];
                if($creditData[$dataPointType] > 0) {
                  $value1 = $creditData[$dataPointType];
                } else {
                  $value1 = "(none)";
                }
                if($wiValue != "") {
                  $value2 = $wiValue;
                } else {
                  $value2 = "(none)";
                }
              } else {
                if($dataPointInfo['dpType'] == "numberFourDecimal") {
                  $wiValue = preg_replace("/[^0-9]/", "", $_POST[$dataPointType]);
                  if($creditData[$dataPointType] > 0) {
                    $value1 = number_format($creditData[$dataPointType], 4);
                  } else {
                    $value1 = "(none)";
                  }
                  if($wiValue != "") {
                    $value2 = number_format($wiValue, 4);
                  } else {
                    $value2 = "(none)";
                  }
                }
              }
            }
          }
        }
      }
      if($creditData[$dataPointType] != $wiValue) {
        //First check if this is credit amount being changed, as this edits more than one value
        if($dataPointType == "creditAmount") {
          //First, check if the new credit amount is above what has already been traded/harvested to modify the amount remaining accordingly:
          //1. get total trades and harvests
          $totalTradeAmount = $this->Trades->get_total_trade_amount_on_listing($_POST['workflowAttachedId']);
          //2. new total amount minus the amount traded minus the amount ACTUALLY harvested
          $availableToList = $wiValue - $totalTradeAmount['totalTradeAmount'];
          if($availableToList < 0) {
            //Set error message that you reduced the credit amount below the amount sold/harvested
            $this->session->set_flashdata('workflowCreditAmountTooLowError', 1);
            redirect('/dashboard/credit/' . $_POST["workflowAttachedId"] . "/" . $wWorkflowType);
          }
          //Change the credit amount value on the credit
          $this->CreditListings->update_active_listing_field($_POST['workflowAttachedId'], $dataPointType, $wiValue);
          //Change the amount remaining data point
          $this->CreditListings->update_pending_listing_field($_POST['workflowAttachedId'], "availableToList", $availableToList);
          //set flag for further down
          $estCreditValVarianceFlag = true;
        }
        $this->CreditListings->update_pending_listing_field($_POST['workflowAttachedId'], $dataPointType, $wiValue);
        //Audit Trail
        $this->AuditTrail->insert_audit_item($auditId, $_POST['workflowAttachedId'], $value1, $value2, "", "", "", "", "", "", "");
        //If this is credit amount, then flag the variance value for later in script
      }

      //IF credit estimate data was updated and tracking is on, then update variance
      if($estCreditValVarianceFlag && $creditData['trackingVariance'] == 1) {

        $allCreditEstimates = $this->AuditTrail->get_audit_trail($_POST['workflowAttachedId'], 2, '');

        //Get new credit estimate at this moment in time
        $newCreditEstimateData = $this->CreditListings->get_credit_estimated_value($_POST['workflowAttachedId']);
        $newCreditEstPrice = $newCreditEstimateData['estCreditPrice'];
        $newCreditEstFaceValue = $newCreditEstimateData['amountLocal'];
        $newCreditEstExchange = $newCreditEstimateData['budgetExchangeRate'];
        $newCreditEstValue = $newCreditEstimateData['amountValueLocal'];
        //Prepare prior estimates
        $priorCreditEstPrice = $allCreditEstimates[0]["audValAfter"];
        $priorCreditEstFaceValue = $allCreditEstimates[0]["audRelVal1After"];
        $priorCreditEstExchange = $allCreditEstimates[0]["audRelVal2After"];
        $priorCreditEstValue = $allCreditEstimates[0]["audRelVal3After"];

        $this->AuditTrail->insert_audit_item(2, $_POST['workflowAttachedId'], $priorCreditEstPrice, $newCreditEstPrice, $priorCreditEstFaceValue, $newCreditEstFaceValue, $priorCreditEstExchange, $newCreditEstExchange, $priorCreditEstValue, $newCreditEstValue, '', '');

        $estValueBefore = $priorCreditEstValue * $priorCreditEstExchange;
        $estValueAfter = $newCreditEstValue * $newCreditEstExchange;

        //If this user is DMA
        if($this->cisession->userdata('level') == 4) {
          //TODO: *TEST THIS* change me to USD!
          //Insert message for credit estimate being updated
          $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
          $msType = "update";
          $msAction = "credit_estimate_updated";
          $msListingId = $creditData['listingId'];
          $msBidId = "";
          $msTradeId = "";
          $msTitle = "Credit Estimate Updated - " . $creditData['stateCertNum'] . $projectNameExt . " - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter) . " (" . $creditData['state'] . $creditData['listingId'] . ")";
          $msTitle2 = "Credit Estimate Updated by " . $this->cisession->userdata('dmaTitle') . " - '" . $creditData['stateCertNum'] . $projectNameExt . "' - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter) . " (" . $creditData['state'] . $creditData['listingId'] . ")";
          $msTitleShared = $msTitle2;
          $msTitleShort = "Credit Estimate Updated - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter);
          $msTitle2Short = "Credit Estimate Updated by " . $this->cisession->userdata('dmaTitle') . " - from $" . number_format($estValueBefore) . " to $" . number_format($estValueAfter);
          $msTitleSharedShort = $msTitle2Short;
          $msContent = "";
          $msContent2 = "";
          $msPerspective = "seller";
          $msPerspective2 = "";
          $firstDmaMainUserId = $this->cisession->userdata('primUserId');
          $secondDmaMainUserId = "";
          $msUserIdCreated = $this->cisession->userdata('userId');
          $alertShared = true;
          $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);

        }

      }

      //Centralized function to clean/prepare filter data
      $preparedFilterData = $this->CreditListings->prepareFilterData();
      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($preparedFilterData);
      //Update cache for this credit
      $cData['listingId'] = $creditData['listingId'];
      $this->CreditListings->update_credit_cache($cData);

      //This is just a normal workfow item
    } else {

      if($_POST["wiTempType"] == "currency" || $_POST["wiTempType"] == "percentage" || $_POST["wiTempType"] == "number") {
        $wiValueCheck = (float)preg_replace('/[^0-9.]*/', '', $_POST['wiValue']);
        if($wiValueCheck > 0) {
          $wiValue = $wiValueCheck;
        }
      } else {
        if($_POST["wiTempType"] == "date") {
          if(strlen($_POST['wiValue']) > 3) {
            $wiValue = strtotime($_POST['wiValue']);
          }
        } else {
          $wiValue = $_POST['wiValue'];
        }
      }

    }

    //See if the workflow item has changed
    //Get pre-existing workflow item
    if($wWorkflowType) {
      $wiExisting = $this->Workflow->get_workflow_item("", $_POST['workflowAttachedId'], $_POST['wvId']);
    } else {
      $wiExisting = $this->Workflow->get_workflow_item($_POST['wiId'], $_POST['workflowAttachedId']);
    }

    $wiDueDate = (isset($_POST['wiDueDate'])) ? strtotime($_POST['wiDueDate']) : null;

    //If this is a new item or anything has actually changed, then mark an alert
    if($wiExisting['wiStatus'] != $_POST['wiStatus'] || $wiExisting['wiValue'] != $wiValue || $wiExisting['wiAssignedTo'] != $_POST['wiAssignedTo'] || (isset($_POST['wiDueDate']) && $_POST['wiDueDate'] != $wiExisting['wiDueDate'])) {

      if($_POST['wvId'] == 0) {
        //Create a new value item
        $wRequest['wiId'] = $_POST['wiId'];
        $wRequest['wvAttachedToType'] = $_POST["workflowAttachedType"];
        $wRequest['wvAttachedToId'] = $_POST['workflowAttachedId'];
        $wRequest['wiStatus'] = $_POST['wiStatus'];
        if($wiDueDate != "") {
          $wRequest['wiDueDate'] = $wiDueDate;
        }
        $wRequest['wiValue'] = $wiValue;
        $wRequest['wiLastUpdatedUser'] = $this->cisession->userdata('userId');
        if($wRequest['wiStatus'] == 1) {
          $wRequest['wiCompletedUser'] = $this->cisession->userdata('userId');
        }
        $this->Workflow->insert_workflow_item_value($wRequest);
        $this->Workflow->get_workflow_item($_POST['wiId'], $_POST['workflowAttachedId']);

        if($_POST['wiAssignedTo'] > 0) {
          $this->Workflow->assign_workflow_item($_POST['wvId'], $_POST['wiAssignedTo']);
        }

      } else {
        //Update workflow item
        $existingWI = $this->Workflow->get_workflow_item($_POST['wiId'], $_POST['workflowAttachedId']);

        $wRequest['wvId'] = $_POST['wvId'];
        $wRequest['wiLastUpdatedUser'] = $this->cisession->userdata('userId');
        $wRequest['wiStatus'] = $_POST['wiStatus'];
        if($wiDueDate != "") {
          $wRequest['wiDueDate'] = $wiDueDate;
        }
        $wRequest['wiValue'] = $wiValue;
        if($wRequest['wiStatus'] == 1 && ($existingWI['wiStatus'] == 0 || $existingWI['wiStatus'] == 2)) {
          $wRequest['wiCompletedUser'] = $this->cisession->userdata('userId');
        } else {
          if(($existingWI['wiStatus'] == 0 || $wRequest['wiStatus'] == 2) && $wRequest['wiStatus'] == 1) {
            $wRequest['wiCompletedUser'] = null;
          }
        }
        $this->Workflow->update_workflow_item_value($wRequest);

        //If this is assigned to someone, but that someone has changed
        if($_POST['wiAssignedTo'] > 0 && $_POST['wiAssignedTo'] != $wiExisting['wiAssignedTo']) {
          $this->Workflow->assign_workflow_item($_POST['wvId'], $_POST['wiAssignedTo']);
        }
        //If this task is NOT assigned to anyone anymore, then unassign it
        if($_POST['wiAssignedTo'] > 0) {
          //do nothing
        } else {
          $this->Workflow->unassign_workflow_item($_POST['wvId']);
        }

      }

      if($wiExisting['wiAssignedTo'] == 0) {
        $wiExisting['wiAssignedTo'] = "";
      }
      if($wiExisting['wiTempType'] == "currency" || $wiExisting["wiTempType"] == "percentage") {
        $wiValueContent = "$" . number_format($wiExisting['wiValue']);
        $wiValueContentNew = "$" . number_format($wiValue);
      } else {
        if($wiExisting['wiTempType'] == "date") {
          $wiValueContent = date('m/d/Y', $wiExisting['wiValue']);
          $wiValueContentNew = date('m/d/Y', $wiValue);
        } else {
          $wiValueContent = $wiExisting['wiValue'];
          $wiValueContentNew = $wiValue;
        }
      }

      //If this user is DMA
      if($this->cisession->userdata('level') == 4) {

        //Insert alert messages (but force the )
        $projectNameExt = ($_POST['projectNameExt'] != "") ? " - " . $_POST['projectNameExt'] : "";
        $msType = "update";
        $msAction = $wWorkflowType . "_update";
        $msListingId = $listingId;
        $msBidId = "";
        $msTradeId = "";
        $msWorkflowItemId = $_POST['wiId'];
        $thisProject = ($_POST['stateCertNum'] != "") ? "(" . $_POST['stateCertNum'] . $projectNameExt . ")" : "";
        $wiTempName = "'" . $_POST['wiTempName'] . "'";
        if($wiExisting['wiValue'] != $wiValue) {
          $thisValue = ($wiValue != "" && $wiValue != "-") ? " - set to " . $wiValueContentNew : " - value erased (" . $wiValueContent . ")";
        } else {
          $thisValue = "";
        }
        if($wiExisting['wiStatus'] != $_POST['wiStatus']) {
          if($_POST['wiStatus'] == 0) {
            $thisStatus = " - Marked Pending";
          } else {
            if($_POST['wiStatus'] == 2) {
              $thisStatus = " - Marked In Progress";
            } else {
              if($_POST['wiStatus'] == 1) {
                $thisStatus = " - Marked Complete";
              }
            }
          }
        } else {
          $thisStatus = "";
        }
        if($wiExisting['wiAssignedTo'] != $_POST['wiAssignedTo']) {
          if($_POST['wiAssignedTo'] > 0) {
            //Get new assigned person
            $newPerson = $this->Members_Model->get_member_by_id($_POST['wiAssignedTo']);
            $wiAssignedTo = " - task assigned to " . $newPerson['lastName'] . ", " . $newPerson['firstName'];
          } else {
            //Get prior assigned person
            $priorPerson = $this->Members_Model->get_member_by_id($wiExisting['wiAssignedTo']);
            $wiAssignedTo = " - task unassigned (" . $priorPerson['lastName'] . ", " . $priorPerson['firstName'] . ")";
          }
        } else {
          $wiAssignedTo = "";
        }

        if($_POST["workflowAttachedType"] == "credit") {
          $msTitle = "Credit " . ucfirst($wWorkflowType) . " Update - " . $thisProject . " (" . $_POST['state'] . $_POST['workflowAttachedId'] . ") - " . $wiTempName . " " . $thisValue . " " . $thisStatus . " " . $wiAssignedTo;
          $msTitle2 = "";
          $msTitleShared = $msTitle;
          $msTitleShort = ucfirst($wWorkflowType) . " Update - " . $wiTempName . " " . $thisValue . " " . $thisStatus . " " . $wiAssignedTo;
          $msTitle2Short = "";
          $msTitleSharedShort = $msTitleShort;
          $msContent = "";
          $msContent2 = "";
          $msPerspective = "seller";
          $msPerspective2 = "";
          $firstDmaMainUserId = $owner_of_credit_primary_account_id;
          $secondDmaMainUserId = "";
          $msUserIdCreated = $this->cisession->userdata('userId');
          $msMessageId = $_POST['wiId'];
          $alertShared = true;
          $keepUnread = null;
          $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId, $keepUnread, $msWorkflowItemId);

        }

        //Also - send the person assigned to a real time email if the user checked the box to do so
        if($this->input->post('emailAssignedTo') == 1 && $wiExisting['wiAssignedTo'] != $_POST['wiAssignedTo'] && $_POST['wiAssignedTo'] > 0 && $this->cisession->userdata('userId') != $_POST['wiAssignedTo']) {
          //Get person being assigned
          $assignedMember = $this->Members_Model->get_member_by_id($_POST['wiAssignedTo']);

          //send email to person assigned
          $emailData['updateType'] = "taskAssignedTo";
          $emailData['welcomeNameTemplate'] = 1;
          $emailData['button'] = 0;
          $emailData['email'] = $assignedMember['email'];
          $emailData['firstName'] = $assignedMember['firstName'];
          $emailData['assignerFirstName'] = $this->cisession->userdata('firstName');
          $emailData['assignerLastName'] = $this->cisession->userdata('lastName');
          $emailData['assignerCompanyName'] = $this->cisession->userdata('dmaTitle');
          $emailData['task'] = $_POST['wiTempName'];
          $emailData['projectName'] = ($_POST['stateCertNum'] != "") ? $_POST['stateCertNum'] . $projectNameExt : "";
          $emailData['msListingId'] = $msListingId;
          $emailData['msState'] = $_POST['state'];
          $emailData['taskDueDate'] = ($_POST['wiDueDate'] != "") ? $_POST['wiDueDate'] : 'TBD';
          $emailData['wWorkflowType'] = $wWorkflowType;
          $emailData['msMessageId'] = $msMessageId;
          $emailData['headline'] = ucfirst($wWorkflowType) . " Task Assigned to You";
          $this->Email_Model->_send_email('member_template_1', 'OIX ' . ucfirst($wWorkflowType) . ' Task Assigned to You - ' . $emailData['task'], $emailData['email'], $emailData);

        }

      }

    }

    //if($_POST['wiTempType'] == 'date') {
    //Check for calendar updates
    $this->CreditListings->check_credit_for_due_dates($_POST['workflowAttachedId']);
    if($_POST['wiValue'] != '' || $_POST['wiDueDate'] > 0) {
      if($wWorkflowType == "compliance") {
        $this->CreditListings->check_compliance_items_for_reminder_dates($_POST['workflowAttachedId']);
      } else {
        $this->Workflow->check_workflow_items_for_due_dates($_POST['workflowAttachedId']);
      }
    } else {
      //Delete any calendar alerts for this workflow item
      $messageType = ($wWorkflowType == "compliance") ? 'compliance_reminder' : 'workflow_update';
      $this->Members_Model->search_and_delete_alert_messages($messageType, "", "", $_POST['wiId']);
    }

    //}

    //Update credit update time
    $this->CreditListings->updateTime($_POST['workflowAttachedId']);

    //Setup success message
    $this->session->set_flashdata('workflowAddedSuccess', 1);

    //redirect
    if($_POST["workflowAttachedType"] == "credit") {
      redirect('/dashboard/credit/' . $_POST["workflowAttachedId"] . "/" . $wWorkflowType);
    }

    if($_POST["workflowAttachedType"] == "transaction") {
      //redirect
      if(sizeof($trade['transactions']) > 1) {
        redirect('/dashboard/credit/' . $listingId . '/multisale/' . $trade['tradeId'] . "/transaction/" . $transactionId);
      } else {
        redirect('/dashboard/credit/' . $listingId . '/sale/' . $trade['tradeId']);
      }
    }

  }

  function restart_workflow_item() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    if($_POST["workflowAttachedType"] == "credit") {
      $listingId = $_POST['workflowAttachedIdRestart'];
    }

    if($_POST["workflowAttachedType"] == "transaction") {

      $transactionId = $_POST['workflowAttachedIdRestart'];
      $transactionData = $this->Trading->get_transaction_by_id($transactionId);
      $tradeData = $this->Trading->get_trade($transactionData['tradeId']);
      $trade = $tradeData['trade'];
      $listingId = $trade['listingId'];

    }

    //CHECK ACCESS
    $this->memberpermissions->checkCreditAccess($listingId, "", "creditEditorOnly");

    $wRequest['wvId'] = $_POST['wvIdRestart'];
    $wRequest['wiLastUpdatedUser'] = $this->cisession->userdata('userId');
    $this->Workflow->restart_workflow_item_value($wRequest);

    if($_POST["wWorkflowType"] == "workflow") {

      if($_POST["workflowAttachedType"] == "transaction") {
        //redirect
        if(sizeof($trade['transactions']) > 1) {
          redirect('/dashboard/credit/' . $listingId . '/multisale/' . $trade['tradeId'] . "/transaction/" . $transactionId);
        } else {
          redirect('/dashboard/credit/' . $listingId . '/sale/' . $trade['tradeId']);
        }
      } else {
        redirect('/dashboard/credit/' . $_POST["workflowAttachedIdRestart"] . "/workflow/" . $_POST['wiIdRestart']);
      }

    }
    if($_POST["wWorkflowType"] == "compliance") {
      redirect('/dashboard/credit/' . $_POST["workflowAttachedIdRestart"] . "/compliance/" . $_POST['wiIdRestart']);
    }

  }

  function load_comments() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    //First, figure out the listing ID
    if($_POST['cmConnectToType'] == "audit_trail") {
      $record = $this->AuditTrail->get_audit_item($_POST['cmItemId']);
      $creditId = $record['audItemId'];
    }
    if($_POST['cmConnectToType'] == "workflow_item") {
      $record = $this->Workflow->get_workflow_item_by_value($_POST['cmItemId']);
      $permissionType = $record['wvAttachedToType'];
      if($permissionType == "credit") {
        $creditId = $record['wvAttachedToId'];
      }
      if($permissionType == "transaction") {
        $transactionId = $record['wvAttachedToId'];
        $transactionData = $this->Trading->get_transaction_by_id($transactionId);
        $tradeData = $this->Trading->get_trade($transactionData['tradeId']);
        $trade = $tradeData['trade'];
        $creditId = $trade['listingId'];
      }
    }

    //Check access to credit, get permissions, add to $data
    $this->memberpermissions->checkCreditAccess($creditId);

    $comments = $this->Comments->get_comments($_POST['cmConnectToType'], $_POST['cmItemId']);

    $json = json_encode($comments);
    echo $json;

  }

  function add_comment() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    //Check access to credit, get permissions, add to $data
    $this->memberpermissions->checkCreditAccess($_POST['listingId'], 0, "creditEditorOnly");

    $comment = $this->Comments->insert_comment();

    $creditData = $this->CreditListings->get_credit_private($_POST['listingId']);

    $owner_of_credit_primary_account_id = $creditData['primary_account_id'];

    //If this user is DMA
    if($this->cisession->userdata('level') == 4) {

      //If this is a workflow comment, add an alert
      if($_POST['cmConnectToType'] == "workflow_item") {

        //Insert alert messages (but force the )
        $msType = "update";
        $msAction = "workflow_update";
        $msListingId = $_POST['listingId'];
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Workflow Comment on Credit " . $_POST['state'] . $_POST['listingId'] . " (" . $_POST['stateCertNum'] . ") - " . $_POST['wiTempName'] . " (''" . $_POST['cmText'] . "'')";
        $msTitle2 = "";
        $msTitleShared = $msTitle;
        $msTitleShort = "Workflow Comment - " . $_POST['wiTempName'] . " (''" . $_POST['cmText'] . "'')";
        $msTitle2Short = "";
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $owner_of_credit_primary_account_id;
        $secondDmaMainUserId = "";
        $msUserIdCreated = $this->cisession->userdata('userId');
        $msMessageId = $_POST['cmItemId'];
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId);

      }

    }

    $json = json_encode($comment);
    echo $json;

  }

  function delete_comment() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    //Check access to credit, get permissions, add to $data
    $this->memberpermissions->checkCreditAccess($_POST['listingId'], 0, "creditEditorOnly");

    //Check if owner of comment
    $comment = $this->Comments->get_comment($_POST['cmId']);
    if($comment['userId'] != $this->cisession->userdata('userId')) {
      echo "<p>Error: Either this comment does not exist, or you do not have permission to access it.</p>";
      throw new \Exception('General fail');
    }

    $this->Comments->delete_comment();

    //Return the number of active comments on this item to see if there still are any
    $remainingCommentCount = $this->Workflow->countCommentsOnWorkflowItem($_POST['cmItemId']);

    $json = json_encode($remainingCommentCount);
    echo $json;

  }

  function getDocumentViewLink($fileId, $filterTag, $docaccess, $listingId, $tradeId = "", $transactionId = "") {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    $docSharedLinkData = $this->filemanagementlib->getBoxFileViewLink($fileId, 1, $filterTag, $docaccess, $listingId, $tradeId, $transactionId);
    $json = json_encode($docSharedLinkData);
    echo $json;

  }

  function getDocumentViewLinkSignature() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

    }

    $iHash = $this->input->post('iHash');
    $dsId = $this->input->post('dsId');
    $dsAccessTime = $this->input->post('dsAccessTime');
    $docVersion = $this->input->post('docVersion');
    $viewFileId = $this->input->post('viewFileId');

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0) {

      //If this has expired... return error
      if($data['docToSign']['dsAccessTime'] < time()) {
        $response['success'] = false;
        $response['errorMessage'] = "Link expired";
        $response['downloadLink'] = null;
        $json = json_encode($response);
        echo $json;

      } else {

        $rDocToSign["dsEnvelopeId"] = $data['docToSign']['dsEnvelopeId'];
        $rDocToSign["dsId"] = $dsId;
        $rDocToSign["dsFullName"] = $data['invite']['iFirstName'] . " " . $data['invite']['iLastName'];
        $rDocToSign["dsEmail"] = $data['invite']['iEmail'];
        $data['docuSignData'] = $this->filemanagementlib->get_docuSign_doc($rDocToSign);

        $listingId = $data['docToSign']['dsListingId'];

        $data['docToDownload'] = $this->Docs->get_document(null, $viewFileId);
        if($data['docToDownload']['listingId'] == $listingId) {
          $fileId = $viewFileId;
        }
        $signerData['fullName'] = $data['docToSign']['dsFullName'];
        $signerData['dmaTitle'] = null;
        $docSharedLinkData = $this->filemanagementlib->getBoxFileViewLink($fileId, 1, "signatureCustom", null, $listingId, null, null, $signerData);
        $response['success'] = true;
        $response['docSharedLinkData'] = $docSharedLinkData;
        $json = json_encode($response);
        echo $json;

      }

    }

  }

  function downloadDocument($fileId, $filterTag, $docaccess, $listingId, $tradeId = "", $transactionId = "", $signedFileId = "", $dsId = "") {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {
      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }
    } else {
      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }
    }

    //Set default
    $downloadType = "box";

    if($filterTag == "signed_doc") {
      $dRequest['dsId'] = $dsId;
      $docToSign = $this->Docs->get_docToSign($dRequest);
      if($docToSign['dsSignedVersionFileId'] == null) {
        $downloadType = "docusign";
      } else {
        $fileId = $signedFileId; //reset this value for box download below
      }
    }

    //IF DOCUSIGN DOC (eSign)
    if($downloadType == "docusign") {

      //$docDownLink = base_url().'ajax/signed_doc_download/0/'.$dsId;
      $docDownLink = $this->filemanagementlib->downloadDocuSignDoc($dsId, $docToSign['dsEnvelopeId']);
      $thisFileName = $docToSign['fileName'];
      exit();

      //IF BOX DOC (not eSign)
    } else {

      $docDownLink = $this->filemanagementlib->downloadBoxFile($fileId, 1, $filterTag, $docaccess, $listingId, $tradeId, $transactionId);
      $thisDoc = $this->Docs->get_document("", $fileId);
      $thisFileName = $thisDoc['orgFileName'];

      $path = $docDownLink; // the file made available for download via this PHP file

      $ch = curl_init($path);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_NOBODY, 1);
      curl_exec($ch);
      $mm_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

      header("Pragma: public");
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: public");
      header("Content-Description: File Transfer");
      header("Content-Type: " . $mm_type);
      header("Content-Length: " . (string)($fileSize));
      header("Content-Transfer-Encoding: binary\n");
      header('Content-Disposition: attachment; filename="' . $thisFileName . '"');

      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $path);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_HEADER, false);
      $filedata = curl_exec($curl);
      curl_close($curl);
      echo $filedata;

      exit();

    }

  }

  function downloadDocument_unauthenticated($fileId, $iHash, $dsId, $dsAccessTime, $docVersion, $downloadFileId) {

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0) {

      //If this has expired... return error
      if($data['docToSign']['dsAccessTime'] > time() || $data['docToSign']['dsDownloadExpires'] > time()) {

        //Set default
        $downloadType = "box";

        $dRequest['dsId'] = $dsId;
        $docToSign = $this->Docs->get_docToSign($dRequest);
        if($docToSign['dsSignedVersionFileId'] == null) {
          $downloadType = "docusign";
        } else {
          $fileId = $signedFileId; //reset this value for box download below
        }

        //IF DOCUSIGN DOC (eSign)
        if($downloadType == "docusign") {

          //$docDownLink = base_url().'ajax/signed_doc_download/0/'.$dsId;
          $documentTitle = ($docVersion == "originalVersion") ? $data['docToSign']['fileName'] : "";
          $docDownLink = $this->filemanagementlib->downloadDocuSignDoc($dsId, $docToSign['dsEnvelopeId'], "", "", "", "", $documentTitle);
          $thisFileName = $docToSign['fileName'];
          exit();

          //IF BOX DOC (not eSign)
        } else {

          $signerData['fullName'] = $data['docToSign']['dsFullName'];
          $signerData['dmaTitle'] = null;
          $docDownLink = $this->filemanagementlib->downloadBoxFile($fileId, 1, "signatureCustom", null, $listingId, null, null, $signerData);
          $thisDoc = $this->Docs->get_document("", $fileId);
          $thisFileName = $thisDoc['orgFileName'];

          $path = $docDownLink; // the file made available for download via this PHP file

          $ch = curl_init($path);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_HEADER, 1);
          curl_setopt($ch, CURLOPT_NOBODY, 1);
          curl_exec($ch);
          $mm_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
          $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

          header('Pragma: public');
          header('Expires: 0');
          header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
          header('Cache-Control: public');
          header('Content-Description: File Transfer');
          header('Content-Type: ' . $mm_type);
          header('Content-Length: ' . (string)($fileSize));
          header('Content-Transfer-Encoding: binary\n');
          header('Content-Disposition: attachment; filename="' . $thisFileName . '";');

          $curl = curl_init();
          curl_setopt($curl, CURLOPT_URL, $path);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_HEADER, false);
          $filedata = curl_exec($curl);
          curl_close($curl);
          echo $filedata;

          exit();

        }

      }

    }

  }

  /*
	function getDocumentDownloadLinkTransaction($fileId, $filterTag, $docaccess, $listingId, $tradeId="", $transactionId="", $signedFileId="", $dsId="") {

		//If loading page from Admin Panel...
		if($this->cisession->userdata('isAdminPanel')==TRUE) {

			//Check if ADMIN
			if (!$this->tank_auth->is_admin()) {
				redirect('/auth/login/');
			}

		} else {

			//Check if MEMBER is logged in
			if (!$this->tank_auth->is_logged_in() ) {
				redirect('/');
			}

		}

		//Set default
		$downloadType = "box";

		if($filterTag=="signed_doc") {
			$dRequest['dsId'] = $dsId;
			$docToSign = $this->Docs->get_docToSign($dRequest);
			if($docToSign['dsSignedVersionFileId']==NULL) {
				$downloadType = "docusign";
			} else {
				$fileId = $signedFileId; //reset this value for box download below
			}
		}

		if($downloadType=="docusign") {
			$docDownLink = base_url().'ajax/signed_doc_download/0/'.$dsId;
		} else {
			$docDownLink = $this->filemanagementlib->downloadBoxFile($fileId,1, $filterTag, $docaccess, $listingId, $tradeId, $transactionId);
		}
		$json = json_encode($docDownLink);
		echo $json;

	}
	*/

  function getDocumentDownloadLinkSignature() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

    }

    $iHash = $this->input->post('iHash');
    $dsId = $this->input->post('dsId');
    $dsAccessTime = $this->input->post('dsAccessTime');
    $docVersion = $this->input->post('docVersion');
    $downloadFileId = $this->input->post('downloadFileId');

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0) {

      //If this has expired... return error
      if($data['docToSign']['dsAccessTime'] > time() || $data['docToSign']['dsDownloadExpires'] > time()) {

        $rDocToSign["dsEnvelopeId"] = $data['docToSign']['dsEnvelopeId'];
        $rDocToSign["dsId"] = $dsId;
        $rDocToSign["dsFullName"] = $data['invite']['iFirstName'] . " " . $data['invite']['iLastName'];
        $rDocToSign["dsEmail"] = $data['invite']['iEmail'];
        $data['docuSignData'] = $this->filemanagementlib->get_docuSign_doc($rDocToSign);

        $listingId = $data['docToSign']['dsListingId'];

        //Audit trail
        $this->AuditTrail->insert_audit_item(150, $listingId, '-', "By: " . $data['docToSign']['dsFullName'] . " (Document: " . $data['docToSign']['fileName'] . ")", "");

        if($docVersion == "originalVersion") {
          $fileId = $data['docToSign']['fileId'];
        } else {
          if($docVersion == "signedVersion") {
            if($downloadFileId > 0) {
              $data['docToDownload'] = $this->Docs->get_document(null, $downloadFileId);
              if($data['docToDownload']['listingId'] == $listingId) {
                $fileId = $downloadFileId;
              }
            } else {

              //$redirectData['redirectUrl'] = '/ajax/signed_doc_download/'.$iHash.'/'.$dsId.'/'.$dsAccessTime;
              $response['success'] = true;
              $response['downloadLink'] = base_url() . 'ajax/signed_doc_download/' . $iHash . '/' . $dsId . '/' . $dsAccessTime;
              $json = json_encode($response);
              echo $json;
              exit();

            }
          }
        }

        $signerData['fullName'] = $data['docToSign']['dsFullName'];
        $signerData['dmaTitle'] = null;
        $response['success'] = true;
        $response['downloadLink'] = base_url() . 'ajax/downloadDocument_unauthenticated/' . $fileId . '/' . $iHash . '/' . $dsId . '/' . $dsAccessTime . '/' . $docVersion . '/' . $downloadFileId;
        //$response['downloadLink'] = $docDownLink;
        $json = json_encode($response);
        echo $json;

      } else {

        $response['success'] = false;
        $response['errorMessage'] = "Link expired";
        $response['downloadLink'] = null;
        $json = json_encode($response);
        echo $json;

      }

    }

  }

  function signed_doc_download($iHash = "", $dsId = "", $dsAccessTime = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['invite'] = ($iHash != 0) ? $this->Members_Model->get_invite_by_hash($iHash) : [];

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    //Scenario of non-logged in download
    if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0 && $dsAccessTime > 0) {

      //If this has expired... return error
      if($data['docToSign']['dsAccessTime'] > time() || $data['docToSign']['dsDownloadExpires'] > time()) {

        $this->filemanagementlib->downloadDocuSignDoc($dsId, $data['docToSign']['dsEnvelopeId']);

      }

    } else {

      $d2Request['dsId'] = $dsId;
      $data['docToSign'] = $this->Docs->get_docToSign($d2Request);

      $this->memberpermissions->checkCreditAccess($data['docToSign']['dsListingId']);

      $docuSignUrl = $this->filemanagementlib->getDocuSignDocDownloadLink($dsId, $data['docToSign']['dsEnvelopeId']);

    }

  }

  function account_people_selector($fileId = "") {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $data['dmamembers'] = $this->DmaAccounts->get_dma_members($this->cisession->userdata('dmaId'), 1);
    $data['taxpayers'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 1, 1, 1, 1, 1);
    $customers = $this->Members_Model->get_customers($this->cisession->userdata('dmaId'));
    $data['customers'] = $customers['customers'];

    $data['fileId'] = $fileId;
    $data['selectInsteadOfSend'] = ($fileId > 0) ? false : true;

    $this->load->view('ajax/account_people_selector', $data);

  }

  function sendDocumentForSignature() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $request['listingId'] = $this->input->post('listingId');
    $request['fileId'] = $this->input->post('fileId');
    $request['signerType'] = $this->input->post('signerType');
    $request['signerIdentifier'] = $this->input->post('signerIdentifier');

    $this->memberpermissions->checkCreditAccess($request['listingId'], 0, "creditEditorOnly");

    $creditData = $this->CreditListings->get_credit_private($request['listingId']);

    if($request['signerType'] == 'adminUser') {
      $signerData = $this->Members_Model->get_member_by_id($request['signerIdentifier']);
      $data['signerTypeText'] = "Admin User";
      $data['signerNameText'] = $signerData['firstName'] . " " . $signerData['lastName'];
      $data['signerEmail'] = $signerData['email'];
      $taxpayerId = null;
    } else {
      if($request['signerType'] == 'taxEntity') {
        $signerData = $this->Taxpayersdata->get_taxpayer($request['signerIdentifier']);
        if($signerData['tpUserIdSigner'] > 0) {
          $signerData = $this->Members_Model->get_member_by_id($signerData['tpUserIdSigner']);
          $data['signerTypeText'] = "Legal Entity";
          $data['signerNameText'] = $signerData['firstName'] . " " . $signerData['lastName'];
          $data['signerEmail'] = $signerData['email'];
          $taxpayerId = null;
        } else {
          if($signerData['tpEmailSigner'] != "") {
            $signerData['userId'] = $signerData['tpAccountId'];
            $signerData['firstName'] = $signerData['tpFirstName'];
            $signerData['lastName'] = $signerData['tpLastName'];
            $signerData['email'] = $signerData['tpEmailSigner'];
            $data['signerTypeText'] = "Legal Entity";
            $data['signerNameText'] = $signerData['tpFirstName'] . " " . $signerData['tpLastName'];
            $data['signerEmail'] = $signerData['tpEmailSigner'];
            $taxpayerId = null;
          } else {
            $signerData = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
            $data['signerTypeText'] = "Legal Entity";
            $data['signerNameText'] = $signerData['firstName'] . " " . $signerData['lastName'];
            $data['signerEmail'] = $signerData['email'];
            $taxpayerId = null;
          }
        }
      } else {
        if($request['signerType'] == 'customer') {
          $signerData = $this->Members_Model->get_member_by_id($request['signerIdentifier']);
          $data['signerTypeText'] = "Customer";
          $data['signerNameText'] = $signerData['firstName'] . " " . $signerData['lastName'];
          $data['signerEmail'] = $signerData['email'];
          $taxpayerId = null;
        } else {
          if($request['signerType'] == 'customEmail') {
            $signerData['firstName'] = $this->input->post('customEmailFirstNameSend');
            $signerData['lastName'] = $this->input->post('customEmailLastNameSend');
            $signerData['userId'] = null;
            $signerData['email'] = $this->input->post('signerIdentifier');
            $data['signerTypeText'] = $this->input->post('signerIdentifier');
            $data['signerNameText'] = $signerData['firstName'] . " " . $signerData['lastName'];
            $data['signerEmail'] = $this->input->post('signerIdentifier');
            $taxpayerId = null;
          }
        }
      }
    }

    //First, mark existing doc unsigned and delete existing invitation
    $request['docSignatureStatus'] = 0;
    $this->Docs->updateDocSignatureStatus($request);

    $dRequest['fileId'] = $request['fileId'];
    $dRequest['dsId'] = null;
    $priorDocToSignInfo = $this->Docs->get_docToSign($dRequest);

    //Delete Doc to Sign
    $this->Docs->delete_docToSign($dRequest);

    if(sizeof($priorDocToSignInfo) > 0 && $priorDocToSignInfo['dsId'] > 0) {
      $priorInvite = $this->Members_Model->get_invite("", "signatureCustom", $priorDocToSignInfo['dsId']);
      //delete invite
      $this->Members_Model->delete_invite($priorInvite['inviteId']);
    }

    //Insert local record of document to sign in DB
    $userId = $this->cisession->userdata('userId');
    $dsType = "custom_signature";
    $userFullname = $data['signerNameText'];
    $userEmail = $data['signerEmail'];
    $role = $request['signerType'];
    $state = $creditData['state'];
    $listingId = $creditData['listingId'];
    $tradeId = null;
    $transactionId = null;
    $boxDocId = $request['fileId'];
    $signatureData = [];
    $docToSignId = $this->Docs->insert_docToSign($userId, $dsType, $taxpayerId, $userFullname, $userEmail, $role, $state, $listingId, $tradeId, $transactionId, $boxDocId, $signatureData);

    //Get all the data about the doc we just inserted
    $docToSignInfo = $this->Docs->get_docToSign_by_id($docToSignId);

    $fullTradeInfo = [];

    //Send that data to our DocuSign integration where an envelope is created in our DocuSign account
    $this->filemanagementlib->submitPSAtoDocusign($docToSignInfo, $fullTradeInfo);

    //build data for invite
    $iData['iType'] = 'signatureCustom';
    $iData['iDmaId'] = $this->cisession->userdata('dmaId');
    $iData['dmaTitle'] = $this->cisession->userdata('dmaTitle');
    $iData['firstName'] = $this->cisession->userdata('firstName');
    $iData['lastName'] = $this->cisession->userdata('lastName');
    $iData['iFirstName'] = $signerData['firstName'];
    $iData['iLastName'] = $signerData['lastName'];
    $iData['iEmail'] = $signerData['email'];
    $iData['iUserId'] = $signerData['userId'];
    $iData['iFromUserId'] = $this->cisession->userdata('userId');
    $iData['iContent1'] = $docToSignId;
    $iData['iContent2'] = '';
    //Insert the invitation
    $newInviteId = $this->Members_Model->insert_invite($iData);

    //Get the invite we just created
    $data['invite'] = $this->Members_Model->get_invite($newInviteId);

    //If an OIX user, then ceate a signature task
    if($signerData['userId'] > 0) {
      $taskRequest['wiTaskDmaId'] = $this->cisession->userdata('dmaId');
      $taskRequest['wiTaskAttachedToType'] = 'credit';
      $taskRequest['wiTaskListingId'] = $creditData['listingId'];
      $taskRequest['wiTaskState'] = $creditData['state'];
      $taskRequest['wiAssignedTo'] = $signerData['userId'];
      $taskRequest['wiInviteHash'] = $data['invite']['iHash'];
      $taskRequest['wiTempId'] = ($this->config->item('environment') == "LIVE") ? 728 : 5016;
      $wiId = $this->Workflow->insert_task($taskRequest, null);
    }

    //Add more email data for the invite
    $iData['updateType'] = 'signatureCustom';
    $iData['iEmail'] = $data['invite']['iEmail'];
    $iData['iExpires'] = $data['invite']['iExpires'];
    $iData['inviteId'] = $data['invite']['inviteId'];
    $iData['iHash'] = $data['invite']['iHash'];
    $iData['title'] = $data['invite']['title'];
    //RE DO SOME VARIABLES
    $iData['firstName'] = $signerData['firstName'];
    $iData['lastName'] = $signerData['lastName'];
    $iData['requesterFirstName'] = $this->cisession->userdata('firstName');
    $iData['requesterLastName'] = $this->cisession->userdata('lastName');
    $iData['docToSign'] = $docToSignInfo;
    $iData['headline'] = "Signature Request";
    $iData['welcomeNameTemplate'] = 1;
    $iData['button'] = null;

    //Send email - invite to sign
    $this->Email_Model->_send_email('member_template_1', 'Signature Request from ' . $iData['requesterFirstName'] . ' ' . $iData['requesterLastName'] . ' of ' . $iData['dmaTitle'], $iData['iEmail'], $iData);

    $request['docSignatureStatus'] = 2;
    $this->Docs->updateDocSignatureStatus($request);

    //Audit trail
    $this->AuditTrail->insert_audit_item(170, $docToSignInfo['dsListingId'], "", "For: " . $docToSignInfo['dsFullName'] . " (Document: " . $docToSignInfo['fileInfo'][0]['fileName'] . ")", "", "", "", "", "", "", "");

    //Create alert of DMA system
    //Insert message for just the credit holder (since this is a pending share)
    $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
    $msType = "update";
    $msAction = "custom_document_for_signature_new";
    $msListingId = $docToSignInfo['dsListingId'];
    $msBidId = "";
    $msTradeId = "";
    $msTitle = "Document for signature sent by " . $this->cisession->userdata('firstName') . " " . $this->cisession->userdata('lastName') . " to " . $docToSignInfo['dsFullName'] . " (Document: " . $docToSignInfo['fileInfo'][0]['fileName'] . ") - (" . $creditData['state'] . $msListingId . " - " . $creditData['stateCertNum'] . $projectNameExt . ")";
    $msTitle2 = $msTitle;
    $msTitleShared = $msTitle;
    $msTitleShort = $msTitle;
    $msTitle2Short = $msTitle;
    $msTitleSharedShort = $msTitleShort;
    $msContent = "";
    $msContent2 = "";
    $msPerspective = "seller";
    $msPerspective2 = "";
    $firstDmaMainUserId = $this->cisession->userdata('primUserId');
    $secondDmaMainUserId = "";
    $msUserIdCreated = $this->cisession->userdata('userId');
    $alertShared = true;
    $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    $this->CreditListings->updateTime($docToSignInfo['dsListingId']);

    $data['signerDateSentText'] = date('m-d-Y', time());
    $data['fileId'] = $this->input->post('fileId');

    $data['dsfullname'] = $data['signerNameText'];
    $data['dscreateddateformatted'] = $data['signerDateSentText'];
    $data['dsemail'] = $data['signerEmail'];
    $data['dsrolename'] = $data['signerTypeText'];

    echo json_encode($data);

  }

  function revokeDocumentForSignature() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $request['fileId'] = $this->input->post('fileId');
    $request['docSignatureStatus'] = 0;
    $this->Docs->updateDocSignatureStatus($request);

    $dRequest['fileId'] = $request['fileId'];
    $dRequest['dsId'] = null;
    $docToSignInfo = $this->Docs->get_docToSign($dRequest);

    $this->memberpermissions->checkCreditAccess($docToSignInfo['dsListingId'], 0, "creditEditorOnly");

    //Delete Doc to Sign
    $this->Docs->delete_docToSign($dRequest);

    $invite = $this->Members_Model->get_invite("", "signatureCustom", $docToSignInfo['dsId']);

    //delete invite
    $this->Members_Model->delete_invite($invite['inviteId']);

    //delete signature task
    $wiInviteHash = $invite['iHash'];

    $task = $this->Workflow->get_task(null, $wiInviteHash);

    $this->Workflow->delete_workflow_checklist_item($task['wvWorkflowItemId']);

    //Audit trail
    $this->AuditTrail->insert_audit_item(172, $docToSignInfo['dsListingId'], "", "For: " . $docToSignInfo['dsFullName'] . " (Document: " . $docToSignInfo['fileInfo'][0]['fileName'] . ")", "", "", "", "", "", "", "");

    $creditData = $this->CreditListings->get_credit_private($docToSignInfo['dsListingId']);

    //Create alert of DMA system
    //Insert message for just the credit holder (since this is a pending share)
    $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
    $msType = "update";
    $msAction = "custom_document_for_signature_revoked";
    $msListingId = $docToSignInfo['dsListingId'];
    $msBidId = "";
    $msTradeId = "";
    $msTitle = "Document for signature revoked by " . $docToSignInfo['dsFullName'] . " (Document: " . $docToSignInfo['fileInfo'][0]['fileName'] . ") - (" . $creditData['state'] . $msListingId . " - " . $creditData['stateCertNum'] . $projectNameExt . ")";
    $msTitle2 = $msTitle;
    $msTitleShared = $msTitle;
    $msTitleShort = $msTitle;
    $msTitle2Short = $msTitle;
    $msTitleSharedShort = $msTitleShort;
    $msContent = "";
    $msContent2 = "";
    $msPerspective = "seller";
    $msPerspective2 = "";
    $firstDmaMainUserId = $this->cisession->userdata('primUserId');
    $secondDmaMainUserId = "";
    $msUserIdCreated = $this->cisession->userdata('userId');
    $alertShared = true;
    $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

    $this->CreditListings->updateTime($docToSignInfo['dsListingId']);

    $data['fileId'] = $this->input->post('fileId');

    echo json_encode($data);

  }

  function resendDocumentForSignature() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $request['fileId'] = $this->input->post('fileId');

    //Get the doc to sign
    $dRequest['fileId'] = $request['fileId'];
    $dRequest['dsId'] = null;
    $docToSignInfo = $this->Docs->get_docToSign($dRequest);

    $this->memberpermissions->checkCreditAccess($docToSignInfo['dsListingId'], 0, "creditEditorOnly");

    //Get the invite that already exists
    $data['invite'] = $this->Members_Model->get_invite("", "signatureCustom", $docToSignInfo['dsId']);

    //Add more email data for the invite
    $iData['updateType'] = 'signatureCustom';
    $iData['iEmail'] = $data['invite']['iEmail'];
    $iData['iExpires'] = $data['invite']['iExpires'];
    $iData['inviteId'] = $data['invite']['inviteId'];
    $iData['iHash'] = $data['invite']['iHash'];
    $iData['title'] = $data['invite']['title'];
    $iData['dmaTitle'] = $data['invite']['title'];
    $iData['headline'] = "Signature Request";
    $iData['welcomeNameTemplate'] = 1;
    $iData['button'] = null;
    //RE DO SOME VARIABLES
    $iData['firstName'] = $data['invite']['iFirstName'];
    $iData['lastName'] = $data['invite']['iLastName'];;
    $iData['requesterFirstName'] = $this->cisession->userdata('firstName');
    $iData['requesterLastName'] = $this->cisession->userdata('lastName');
    $iData['docToSign'] = $docToSignInfo;

    //Send email - invite to sign
    $this->Email_Model->_send_email('member_template_1', 'Reminder: Signature Request from ' . $iData['requesterFirstName'] . ' ' . $iData['requesterLastName'] . ' of ' . $iData['dmaTitle'], $iData['iEmail'], $iData);

    $data['fileId'] = $this->input->post('fileId');

    echo json_encode($data);

  }

  function submitSignedDoc() {

    //No login check since logged out users do this
    //if (!$this->tank_auth->is_logged_in() ) { redirect('/'); }

    $iHash = $this->input->post('iHash');
    $dsId = $this->input->post('dsId');
    $dsAccessTime = $this->input->post('dsAccessTime');
    $signedDocumentFileId = $this->input->post('signedDocumentFileId');

    $data['invite'] = $this->Members_Model->get_invite_by_hash($iHash);

    //Get doc to sign info
    $dRequest['dsId'] = $dsId;
    $dRequest['dsAccessTime'] = $dsAccessTime;
    $data['docToSign'] = $this->Docs->get_docToSign($dRequest);

    //No login check since logged out users do this
    //$this->memberpermissions->checkCreditAccess($data['docToSign']['dsListingId'], 0, "creditEditorOnly");

    if(sizeof($data['invite']) > 0 && sizeof($data['docToSign']) > 0) {

      //If this has NOT expired...
      if($data['docToSign']['dsAccessTime'] > time() || $data['docToSign']['dsDownloadExpires'] > time()) {

        $listingId = $data['docToSign']['dsListingId'];

        if($signedDocumentFileId > 0) {
          $data['signedDoc'] = $this->Docs->get_document(null, $signedDocumentFileId);
          if($data['signedDoc']['listingId'] == $listingId) {
            $signedFileId = $signedDocumentFileId;
          } else {
            echo "Error 1";
            throw new \Exception('General fail');
          }
        } else {
          $signedFileId = null;
        }

        //Mark original File: docSignatureStatus = 3 (complete), docSignatureDateSigned = time now, docSignedFileId = signed doc ID
        $sRequest['docSignatureStatus'] = 3;
        $sRequest['fileId'] = $data['docToSign']['fileId'];
        $sRequest['docSignedFileId'] = $signedFileId;
        $this->Docs->updateDocSignatureStatus($sRequest);

        //Mark original Doc to Sign: dsSigned=1, dsSignedDate = time now
        $envelopeId = $data['docToSign']['dsEnvelopeId'];
        $this->Docs->update_docToSign_signed($envelopeId);

        if($signedDocumentFileId > 0) {
          //Mark original Doc to Sign: dsSignedVersionFileId = signed doc ID and set the download expiration
          $sfRequest["dsId"] = $dsId;
          $sfRequest['dsSignedVersionFileId'] = $signedFileId;
          $this->Docs->update_docToSign_signed_fileId($sfRequest);
        }

        //delete invite
        $this->Members_Model->delete_invite($data['invite']['inviteId']);

        //Audit trail
        $this->AuditTrail->insert_audit_item(173, $listingId, "", "By: " . $data['docToSign']['dsFullName'] . " (Document: " . $data['docToSign']['fileName'] . ")", "", "", "", "", "", "", "");

        $creditData = $this->CreditListings->get_credit_private($listingId);

        $this->CreditListings->updateTime($listingId);

        //Create alert of DMA system
        //Insert message for just the credit holder (since this is a pending share)
        $projectNameExt = ($creditData['projectNameExt'] != "") ? " - " . $creditData['projectNameExt'] : "";
        $msType = "update";
        $msAction = "custom_signature_new";
        $msListingId = $listingId;
        $msBidId = "";
        $msTradeId = "";
        $msTitle = "Signature submitted by " . $data['docToSign']['dsFullName'] . " (Document: " . $data['docToSign']['fileName'] . ") - (" . $creditData['state'] . $msListingId . " - " . $creditData['stateCertNum'] . $projectNameExt . ")";
        $msTitle2 = $msTitle;
        $msTitleShared = $msTitle;
        $msTitleShort = $msTitle;
        $msTitle2Short = $msTitle;
        $msTitleSharedShort = $msTitleShort;
        $msContent = "";
        $msContent2 = "";
        $msPerspective = "seller";
        $msPerspective2 = "";
        $firstDmaMainUserId = $data['invite']['primary_account_id'];
        $secondDmaMainUserId = "";
        $msUserIdCreated = $data['invite']['userId'];
        $alertShared = true;
        $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleSharedShort);

        //Send email to requester
        $emailData['updateType'] = "customSignatureSubmitted_requester";
        $emailData['firstName'] = $data['invite']['firstName'];
        $emailData['dmaTitle'] = $data['invite']['dmaTitle'];
        $emailData['docToSign'] = $data['docToSign'];
        $emailData['invite'] = $data['invite'];
        $emailData['creditData'] = $creditData;
        $emailData['welcomeNameTemplate'] = 1;
        $emailData['button'] = 0;
        $emailData['subject'] = 'Document Signed by ' . $data['invite']['iFirstName'] . ' ' . $data['invite']['iLastName'];
        $emailData['headline'] = 'Document%20Signed';
        $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $data['invite']['email'], $emailData);

        //Send email to signer
        $emailData['updateType'] = "customSignatureSubmitted_signer";
        $emailData['firstName'] = $data['invite']['iFirstName'];
        $emailData['docToSign'] = $data['docToSign'];
        $emailData['invite'] = $data['invite'];
        $emailData['creditData'] = $creditData;
        $emailData['requesterFirstName'] = $data['invite']['firstName'];
        $emailData['requesterLastName'] = $data['invite']['lastName'];
        $emailData['dmaTitle'] = $data['invite']['dmaTitle'];
        $emailData['welcomeNameTemplate'] = 1;
        $emailData['button'] = 0;
        $emailData['subject'] = 'Your Signature Received';
        $emailData['headline'] = 'Signature%20Received';
        $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $data['invite']['iEmail'], $emailData);

        redirect('/invites/signed_document/' . $iHash . '/' . $dsId . '/' . $dsAccessTime);

      } else {

        $response['success'] = false;
        $response['errorMessage'] = "Link expired";
        $response['downloadLink'] = null;
        $json = json_encode($response);
        echo $json;

      }

    }

  }

  function deleteDocument($fileId, $etag, $filterTag, $docaccess, $listingId, $tradeId = "", $transactionId = "") {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    $data = $this->filemanagementlib->deleteBoxFile($fileId, $etag, $filterTag, $docaccess, $listingId, $tradeId, $transactionId);

    echo json_encode($data);

  }

  function deleteDocument2($fileInfoId, $filterTag) {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    //Get file using $fileInfoId
    $fileInfo = $this->Docs->get_document($fileInfoId);

    //Get doc to sign using file id
    $dRequest['fileId'] = $fileInfo['fileId'];
    $docToSign = $this->Docs->get_docToSign($dRequest);

    //if doc to sign is greatr than 0
    if(sizeof($docToSign) > 0) {

      //get invite
      $invite = $this->Members_Model->get_invite("", "signatureCustom", $docToSign['dsId']);

      //delete invite
      $this->Members_Model->delete_invite($invite['inviteId']);

    }

    //Delete file
    $data = $this->filemanagementlib->deleteBoxFile2($fileInfoId, $filterTag);

    echo json_encode($fileInfo);

  }

  function uploadBoxFile($listingId, $category = "", $tags = "", $issueId = "", $saleId = "", $transactionId = "", $blockDiligence = "", $box_filename = "", $docType = "", $docConnectToType = "", $docItemId = "", $dsIdBeingSigned = "") {

    $category = ($category != "-") ? $category : "";
    $tags = ($tags != "-") ? $tags : "";
    $issueId = ($issueId != "-") ? $issueId : "";
    $saleId = ($saleId != "-") ? $saleId : "";
    $transactionId = ($transactionId != "-") ? $transactionId : "";
    $blockDiligence = ($blockDiligence != "-") ? $blockDiligence : "";
    $box_filename = ($box_filename != "-") ? $box_filename : "";
    $docType = ($docType != "-") ? $docType : "";
    $docConnectToType = ($docConnectToType != "-") ? $docConnectToType : "";
    $docItemId = ($docItemId != "-") ? $docItemId : "";
    $dsIdBeingSigned = ($dsIdBeingSigned != "-") ? $dsIdBeingSigned : "";

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      $checkCategory = urldecode($category);

      if($checkCategory != "Signed Documents") {

        //Check if MEMBER is logged in
        if(!$this->tank_auth->is_logged_in()) {
          redirect('/');
        }

        if($docType == "credit_doc" || $docType == "transaction_doc" || $docType == "signature_doc" || $docType == "credit_psa_doc") {

          $creditData = $this->CreditListings->get_credit_private($listingId);
          //If this is NOT a credit being loaded (in other words, no data comes back as a loaded credit ID is a temporary ID)
          if(sizeof($creditData) > 0) {
            //There is also another permission down further if this is a re-upload on an existing document
            $permissions = $this->memberpermissions->checkCreditAccess($listingId);
            if(!$permissions['permEdit']) {
              echo "No Permission";
              throw new \Exception('General fail');
            }
          }

        }

      }

    }

    $data = $this->filemanagementlib->uploadBoxFile($listingId, $category, $tags, $issueId, $saleId, $transactionId, $blockDiligence, $box_filename, $docType, $docConnectToType, $docItemId, $dsIdBeingSigned);

    echo json_encode($data);

  }

  function load_attached_documents() {

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      //Check if ADMIN
      if(!$this->tank_auth->is_admin()) {
        redirect('/auth/login/');
      }

    } else {

      //Check if MEMBER is logged in
      if(!$this->tank_auth->is_logged_in()) {
        redirect('/');
      }

    }

    //CHECK ACCESS
    $permissions = $this->memberpermissions->checkCreditAccess($_POST['listingId']);
    $docShared = ($permissions['shareView']) ? 1 : null;
    $documents = $this->Docs->get_documents("credit_doc", $_POST['listingId'], "", $_POST['docConnectToType'], $_POST['docItemId'], "", $docShared);

    $json = json_encode($documents);
    echo $json;

  }

  function checkLoginStatus() {

    //Check if MEMBER is logged in
    $loginStatus = $this->tank_auth->is_logged_in("lightCheck");

    if(!$loginStatus) {
      $status = "logged_out";
    } else {
      $status = "logged_in";
    }

    $json = json_encode($status);
    echo $json;

  }

  function send_confirmation_code() {

    //Check if MEMBER is logged in
    $loginStatus = $this->tank_auth->is_logged_in("lightCheck");

    if(!$loginStatus) {

      $status = false;

    } else {

      $confirmationCode = $this->Members_Model->update_member_confirmation_code($this->cisession->userdata('userId'));

      $memberInfo = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));

      if($this->input->post('method') == 'sms') {

        $smsTo = "+1" . $memberInfo['mobilePhone'];
        $smsText = 'Your temporary 4-digit OIX confirmation code is: ' . $confirmationCode;
        $this->Email_Model->_send_sms($smsTo, $smsText);

      } else {
        if($this->input->post('method') == 'email') {

          $data['updateType'] = 'send_confirmation_code';
          $subject = 'Your OIX Confirmation Code';
          $data['headline'] = 'OIX%20Confirmation%20Code';
          $data['welcomeNameTemplate'] = 1;
          $data['button'] = null;
          $data['firstName'] = $memberInfo['firstName'];
          $data['confirmationCode'] = $confirmationCode;

          //Send email
          $this->Email_Model->_send_email('member_template_1', $subject, $memberInfo['email'], $data);

        }
      }

      $status = true;

    }

    $json = json_encode($status);
    echo $json;

  }

  function confirm_confirmation_code() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $check = $this->Members_Model->check_member_confirmation_code($this->cisession->userdata('userId'), $this->input->post('code'));

    if($check) {
      $this->cisession->set_userdata('ipVerified', 1);
      $thisIpAddress = $this->Members_Model->get_ip();
      if($this->cisession->userdata('mfaType') == 'new-location') {
        $this->Members_Model->add_mfa_to_ip_address_on_member($this->cisession->userdata('userId'), $thisIpAddress);
        //Log it
        $this->logger->info("IP Verified set to true - for Session " . session_id());
      }
    }

    $this->Members_Model->erase_member_confirmation_code($this->cisession->userdata('userId'));

    $json = json_encode($check);
    echo $json;

  }

  function save_mobile_number_on_member() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $this->Members_Model->update_mobile_number_on_member($this->cisession->userdata('userId'), $this->input->post('mobileNumber'));

    $status = true;

    $json = json_encode($status);
    echo $json;

  }

  function get_program_doc_by_cv() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $cv_handle = $this->input->post('cv_handle');
    $cv_link = $this->input->post('cv_link');

    if($this->cisession->userdata('planProgramDatabaseDocs')) {
      $program_doc = $this->IncentivePrograms->get_program_doc_by_cv($cv_handle, $cv_link);
    } else {
      $program_doc_info['iplId'] = 0;
      $program_doc_info['DisplayText'] = 0;
      $program_doc_info['DocId'] = 0;
      $program_doc_info['DocTitle'] = "No Permission";
      $program_doc_info['DocContent'] = '<div class="w100 center vSpace16bottom"><img src="https://oixstatic.s3.amazonaws.com/icons/icon-document-access-block.png" style="width:150px;"></div><div class="w100 center"><p class="w80p dib f18 redText">Access Block</p><p class="w80p dib f14">Your OIX Account does not have access to program statutes, documents and additional information/resources. Contact support@theoix.com to inquire about obtaining access.</div></p>';
      $program_doc = [$program_doc_info];
    }

    echo json_encode($program_doc);
  }

  function get_program_doc_by_doc_id() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $doc_id = $this->input->post('doc_id');

    $program_doc = $this->IncentivePrograms->get_program_doc_by_doc_id($doc_id);

    echo json_encode($program_doc);
  }

}


/* End of file admin.php */
/* Location: ./application/controllers/admin/admin.php */
