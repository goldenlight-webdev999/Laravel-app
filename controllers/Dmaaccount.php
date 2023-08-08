<?php
if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

require_once(APPPATH . "libraries/Aspose/Java.inc");
require_once(APPPATH . "libraries/Aspose/lib/aspose.cells.php");

use aspose\cells;

class Dmaaccount extends CI_Controller {

  protected $isAsposeInitialized = false;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url', 'file']);
    $this->load->library(['form_validation']);
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
    $this->load->model('Workflow');
    $this->load->model('AuditTrail');
    $this->load->model('Taxpayersdata');
    $this->load->model('Uploads');
    $this->load->library('Globalsettings');
    $this->load->library('memberpermissions');
    $this->load->library('filemanagementlib');
    $this->load->library('currency');
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

  function account_configuration() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['tabArray'] = "account_configuration";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "administrative";

      $data['dmaTitle'] = $this->cisession->userdata('dmaTitle');
      $data['levelEditAccount'] = $this->cisession->userdata('levelEditAccount');
      $data['levelManageSuperAdmins'] = $this->cisession->userdata('levelManageSuperAdmins');
      $data['isAccountAdmin'] = ($this->cisession->userdata('userId') == $this->cisession->userdata('primUserId')) ? true : false;
      //$data['levelEditAccount'] = false;

      $output = '';

      $twig = \OIX\Util\TemplateProvider::getTwig();
      $template = $twig->load('dmaaccount/account_configuration.twig');
      $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
      $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

      $output .= $this->load->view('includes/left_nav', $data, true);
      $output .= $this->load->view('includes/tab_nav', $data, true);
      $output .= $template->render($data);
      $output .= $this->load->view('includes/footer-2', true);
      echo $output;

    }

  }

  function password_security() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelEditAccount')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['tabArray'] = "password_security";
        $data['current_tab_key'] = "";
        $data['lnav_key'] = "administrative";

        $data['details'] = $this->Members_Model->get_main_dma_user($this->cisession->userdata('primUserId'));
        $data['state'] = $this->IncentivePrograms->get_us_states();
        $data['dmaData'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

        $data['account_types'] = [
            "0" => "",
            "1" => "Seller",
            "2" => "Buyer",
            "3" => "Buyer/Seller",
        ];

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
        $this->load->view('dmaaccount/password_security', $data);
        $this->load->view('includes/footer-2');

      }

    }

  }

  function password_security_save() {
    $allowedMfaTypes = ['new-location', 'always', 'never'];

    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelEditAccount')) {
        redirect('/dmamembers/no_permission');
      } else {

        //If this is a child trying to hack in...
        if($this->cisession->userdata('parentDmaId') > 0) {
          redirect('/dmamembers/no_permission');
        }

        $prevDmaData = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));

        //Save the default password settings
        $request['daysDefaultInactiveLimit'] = ($_POST['daysDefaultInactiveLimit'] > 0) ? $_POST['daysDefaultInactiveLimit'] : null;
        $request['pwDefaultCharacterCount'] = ($_POST['pwDefaultCharacterCount'] > 0) ? $_POST['pwDefaultCharacterCount'] : null;
        $request['pwDefaultResetDays'] = ($_POST['pwDefaultResetDays'] > 0) ? $_POST['pwDefaultResetDays'] : null;
        $request['idle_timeout_minutes'] = ($_POST['minutesIdleLimit'] > 5) ? ($_POST['minutesIdleLimit'] >= 1440 ? 1440 : $_POST['minutesIdleLimit']) : null;
        $request['pwDefaultReuseCount'] = ($_POST['pwDefaultReuseCount'] == "") ? null : $_POST['pwDefaultReuseCount'];
        $request['pwDefaultReuseDays'] = ($_POST['pwDefaultReuseDays'] == "") ? null : $_POST['pwDefaultReuseDays'];
        $request['mfa_type'] = (in_array($_POST['mfaType'], $allowedMfaTypes) === false) ? null : $_POST['mfaType'];

        $this->DmaAccounts->update_dma_account_settings($this->cisession->userdata('dmaId'), $request);

        $dpRequest['dmaWireInstructions'] = $this->input->post('dmaWireInstructions');
        $this->DmaAccounts->update_dma_account_payment_instructions($this->cisession->userdata('dmaId'), $dpRequest);

        if(($prevDmaData['mfa_type'] != $request['mfa_type'] && strlen($request['mfa_type']) > 0) || ($prevDmaData['idle_timeout_minutes'] != $request['idle_timeout_minutes'] && strlen($request['idle_timeout_minutes']) > 0)) {
          $this->Members_Model->erase_member_session_for_dma($this->cisession->userdata('dmaId'));
          if($prevDmaData['idle_timeout_minutes'] != $request['idle_timeout_minutes'] && strlen($request['idle_timeout_minutes']) > 0) {
            $this->cisession->set_userdata('idleTimeoutSeconds', $request['idle_timeout_minutes'] * 60); //update logged in user's idle timeout
          }
        }

        if(!is_null($data = $this->tank_auth->modify_dma_main_user(
            $this->cisession->userdata('primUserId'),
            $_POST['company_address_1'],
            $_POST['company_address_2'],
            $_POST['city'],
            $_POST['account_state'],
            $_POST['zipcode'],
            $_POST['phonenumber'],
            $_POST['cellnumber']
        ))) {

          //$this->cisession->set_userdata('update_success', '1');
          $this->session->set_flashdata('update_success', 1);
          redirect('/dmaaccount/password_security');
        } else {
          redirect('/dmaaccount/password_security');
        }

        $this->session->set_flashdata('saveSuccess', 1);

      }
    }

  }

  /**
   * This is used to determine if the passed dma ID, or current session dma is a child who is locked from over-riding customized selectors
   *
   * @param int|null $dmaId
   * @return bool
   */
  private function isLockedChildDma(int $dmaId = null): bool {
    if(!isset($dmaId)) {
      $dmaId = $this->cisession->userdata('dmaId');
    }
    $dma = $this->DmaAccounts->get_dma_account_by_id($dmaId);
    if(!$dma['parentDmaId']) {
      return false;
    }

    return $dma['can_override_parent_field'] != true;
  }

  /**
   * This is used to determine if the passed dma ID, or current session dma is a child who is unlocked from over-riding, and free to customized selectors
   *
   * @param int|null $dmaId
   * @return bool
   */
  private function isUnlockedChildDma(int $dmaId = null): bool {
    if(!isset($dmaId)) {
      $dmaId = $this->cisession->userdata('dmaId');
    }
    $dma = $this->DmaAccounts->get_dma_account_by_id($dmaId);
    if(!$dma['parentDmaId']) {
      return false;
    }

    return $dma['can_override_parent_field'] == true;
  }

  function customize_selector($type = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelManageSuperAdmins')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['tabArray'] = "customize_selectors";
        $data['current_tab_key'] = "";
        $data['lnav_key'] = "administrative";

        $data['type'] = $type;

        $data['sections'] = [
            [
                'sectionName' => 'Credit Drop Downs',
                'pages'       => [
                    'program_type'                   => ['title' => 'Credit Types', 'icon' => 'icon-doc', 'url' => base_url() . 'dmaaccount/customize_selector/program_type', 'access' => 1, 'text' => 'Customize the Credit Types (ie; Incentive Program Types) available when loading and editing a credit.'],
                    'certification_status'           => ['title' => 'Certification Status', 'icon' => 'icon-award', 'url' => base_url() . 'dmaaccount/customize_selector/certification_status', 'access' => 1, 'text' => 'Customize the Certification Status options available when loading and editing a credit.'],
                    'monetization_status'            => ['title' => 'Monetization Status', 'icon' => 'icon-tag', 'url' => base_url() . 'dmaaccount/customize_selector/monetization_status', 'access' => 1, 'text' => 'Customize the Monetization Status options available when loading and editing a credit.'],
                    'project_status'                 => ['title' => 'Project Status', 'icon' => 'icon-download-2', 'url' => base_url() . 'dmaaccount/customize_selector/project_status', 'access' => 1, 'text' => 'Customize the Project Status options available when loading and editing a credit.'],
                    'audit_status'                   => ['title' => 'Audit Status', 'icon' => 'icon-calc', 'url' => base_url() . 'dmaaccount/customize_selector/audit_status', 'access' => 1, 'text' => 'Customize the Audit Status options available when loading and editing a credit.'],
                    'adjustment_cause_credit_amount' => ['title' => 'Cause for Adjustment - Credit Amount', 'icon' => 'icon-pencil', 'url' => base_url() . 'dmaaccount/customize_selector/adjustment_cause_credit_amount', 'access' => 1, 'text' => 'Customize the Adjustment Cause options available for Credit Amount when loading and editing a credit.'],
                ],
            ], [
                'sectionName' => 'Utilization Drop Downs',
                'pages'       => [
                    'credit_utilization_type' => ['title' => 'Utilization Types', 'icon' => 'icon-layers', 'url' => base_url() . 'dmaaccount/customize_selector/credit_utilization_type', 'access' => 1, 'text' => 'Customize the Utilization Type options available when loading and editing a credit utilization.'],
                ],
            ],
        ];
        $data['isLockedChildDma'] = $this->isLockedChildDma();

        if($this->cisession->userdata('dmaType') == 'broker') {
          $data['section']['pages']['customer_type'] = ['title' => 'Customer Types', 'icon' => 'icon-money', 'url' => base_url() . 'dmaaccount/customize_selector/customer_type', 'access' => 1, 'text' => 'Customize the Customer Type options available when categorizing your customers.'];
        }

        $dmaId = $this->cisession->userdata('dmaId');
        $parentDmaId = $this->cisession->userdata('parentDmaId');
        if($data['isLockedChildDma'] === true) {
          $dmaId = $parentDmaId;
        }

        if(isset($data['type']) && trim($data['type']) !== '') {

          switch($data['type']) {
            case "program_type":
              $data['typeName'] = "Credit Types";
              break;
            case "certification_status":
              $data['typeName'] = "Certification Status";
              break;
            case "monetization_status":
              $data['typeName'] = "Monetization Status";
              break;
            case "project_status":
              $data['typeName'] = "Project Status";
              break;
            case "audit_status":
              $data['typeName'] = "Audit Status";
              break;
            case "customer_type":
              $data['typeName'] = "Customer Types";
              break;
            case "adjustment_cause_credit_amount":
              $data['typeName'] = "Cause for Adjustment - Credit Amount";
              break;
            case "credit_utilization_type":
              $data['typeName'] = "Utilization Types";
              break;
          }

          $data['custom_options'] = $this->IncentivePrograms->get_dma_account_field_options($data['type'], $dmaId);
          $data['default_options'] = $this->IncentivePrograms->get_dma_account_field_options($data['type'], 1);
          if(empty($data['custom_options'])) {
            $data['custom_options'] = $data['default_options'];
            if($this->isUnlockedChildDma()) {
              $parentDmaOptions = $this->IncentivePrograms->get_dma_account_field_options($data['type'], $parentDmaId);
              if(!empty($parentDmaOptions)) {
                $data['custom_options'] = $parentDmaOptions;
              }
            }
          }
        }

        $output = '';

        $twig = \OIX\Util\TemplateProvider::getTwig();
        $template = $twig->load('dmaaccount/customize_selector.twig');
        $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
        $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

        $output .= $this->load->view('includes/left_nav', $data, true);
        $output .= $this->load->view('includes/tab_nav', $data, true);
        $output .= $template->render($data);
        $output .= $this->load->view('includes/footer-2', true);
        echo $output;
      }
    }
  }

  function save_customized_selectors($type = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelManageSuperAdmins')) {
        redirect('/dmamembers/no_permission');
      } else {
        if($this->isLockedChildDma()) { //IF locked child is saving, ignore and re-show available options
          redirect('/dmaaccount/customize_selector/' . $type);
        }

        $active_types = json_decode($_POST['active_types']);
        //add_dma_account_field($type, $name)   get_dma_account_field_id($type, $name)     activate_dma_account_field($type, $id, $order)      deactivate_all_dma_fields($type)

        $this->IncentivePrograms->deactivate_all_dma_fields($type);

        // Save all default/customized dma field and activate them.
        foreach($active_types as $dma_account_field) {
          if(empty($dma_account_field)) {
            continue;
          }

          $dma_account_field_id = $this->IncentivePrograms->get_dma_account_field_id($type, $dma_account_field->type);

          // If dma field is not exist, add it.
          if(!$dma_account_field_id) {
            $dma_account_field_id = $this->IncentivePrograms->add_dma_account_field($type, $dma_account_field->type);
          }

          $this->IncentivePrograms->activate_dma_account_field($type, $dma_account_field_id, $dma_account_field->order);
        }

        redirect('/dmaaccount/customize_selector/' . $type);
      }
    }
  }

  function custom_datapoints($objectType = "") {
    $data['tabArray'] = "custom_datapoints";
    $data['current_tab_key'] = "custom_data_points_all";
    $data['lnav_key'] = "administrative";
    $allowedObjectTypes = ['credit', 'legal-entity', 'utilization'];
    if(in_array($objectType, $allowedObjectTypes) === false) {
      $objectType = null;
    } else {
      $data['current_tab_key'] = "custom_data_points_" . $objectType;
      $data['dpObjectType'] = $objectType;
    }

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
        $dpRequest['includeArchived'] = 2;
        if(isset($objectType)) {
          $dpRequest['dpObjectType'] = $objectType;
        }
        $customDataPointsRaw = $this->CreditListings->getDataPointsGrouped($dpRequest);
        $data['customDataPoints'] = $customDataPointsRaw['dataPoints'];

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
          $data['tabArray'] = "custom_datapoints_noaccess";
        }
        $data['isBlockDocPreview'] = false;
        if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
          $data['isBlockDocPreview'] = true;
        }
        $data['objectTypeLabels'] = ['credit' => 'Credits', 'utilization' => 'Utilizations', 'legal-entity' => 'Legal Entities'];
        $data['sectionLabels'] = [
            'credit_status_settings'           => 'Status Settings',
            'credit_project_details'           => 'Project Details',
            'credit_incentive_program_details' => 'Incentive Program Details',
            'credit_date_settings'             => 'Date Settings',
            'credit_amount_details'            => 'Credit Amount Details',
            'credit_additional_details'        => 'Additional Details',
            'legal_entity_representative_info' => 'Representative Information',
            'legal_entity_basic_info'          => 'Basic Information',
            'legal_entity_additional_details'  => 'Additional Details',
            'utilization_additional_details'   => 'Additional Details',
        ];
        $data['flash']['saveSuccess'] = $this->session->flashdata('saveSuccess');
        $data['flash']['archiveSuccess'] = $this->session->flashdata('archiveSuccess');
        $data['flash']['deleteSuccess'] = $this->session->flashdata('deleteSuccess');
        $data['flash']['unArchiveSuccess'] = $this->session->flashdata('unArchiveSuccess');
        $data['flash']['saveSuccessOG'] = $this->session->flashdata('saveSuccessOG');
        $data['flash']['archiveSuccessOG'] = $this->session->flashdata('archiveSuccessOG');
        $data['flash']['deleteSuccessOG'] = $this->session->flashdata('deleteSuccessOG');
        $data['flash']['unArchiveSuccessOG'] = $this->session->flashdata('unArchiveSuccessOG');

        $output = '';
        $twig = \OIX\Util\TemplateProvider::getTwig();
        $template = $twig->load('dmaaccount/custom_data_points.twig');
        $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
        $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

        $output .= $this->load->view('includes/left_nav', $data, true);
        $output .= $this->load->view('includes/tab_nav', $data, true);
        if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['allFeatures'])) {
          $output .= $this->load->view('includes/widgets/planBlockMessage', $data, true);
        } else {
          $output .= $template->render($data);
          $output .= $this->load->view('dmaaccount/widgets/datapoint_overlays', $data, true);
        }

        $output .= $this->load->view('includes/footer-2', $data, true);
        echo $output;

      }

    }

  }

  private function parseEditDataPointsReq($optionGroupId) {
    $result = [
        'delete' => [],
        'update' => [],
        'insert' => [],
    ];
    $optionValues = $this->input->post('values');
    $optionValueIds = $this->input->post('valueids');
    $optionValuesArchived = $this->input->post('valuearchives');
    $optionValuesUpdate = $this->input->post('valueupdate');

    $dpOptions = $this->CreditListings->get_option_group_options($optionGroupId);
    $existingOptions = [];
    foreach($dpOptions as $dpOption) {
      $existingOptions[$dpOption['id']] = $dpOption;
    }

    //echo '<pre>';
    //var_dump($optionValues, $optionValueIds, $optionValuesArchived, $optionValuesUpdate, $existingOptions);
    //exit;

    if(isset($optionValues) && count($optionValues) > 0) {
      $displayOrder = 0;
      foreach($optionValues as $optionValueKey => $optionValue) {
        $displayOrder++;
        if($optionValueIds[$optionValueKey] > 0) {
          $isArchived = $optionValuesArchived[$optionValueKey];
          $updateValues = $optionValuesUpdate[$optionValueKey];
          $prevOption = $existingOptions[$optionValueIds[$optionValueKey]];

          if($prevOption['numUses'] > 0) {
            if(trim($prevOption['label']) != trim($optionValue)) {
              $this->CreditListings->updateCustomDataPointOption($optionValueIds[$optionValueKey], $prevOption['label'], $prevOption['display_order'], 1);
              $newOptionId = $this->CreditListings->insertCustomDataPointOption($optionGroupId, $optionValue, $displayOrder);

              if($updateValues == '1') {
                $this->CreditListings->changeCustomDataPointOptionValues($optionGroupId, $optionValueIds[$optionValueKey], $newOptionId, $prevOption['label'], $optionValue);
                $this->CreditListings->deleteCustomDataPointOption($optionValueIds[$optionValueKey], $optionGroupId, $prevOption['label']);
              }
            } else {
              $this->CreditListings->updateCustomDataPointOption($optionValueIds[$optionValueKey], $optionValue, $displayOrder, $isArchived);
            }

          } else {
            if(trim($prevOption['label']) != trim($optionValue) || $displayOrder != $prevOption['display_order'] || $isArchived != $prevOption['is_archived']) {
              $this->CreditListings->updateCustomDataPointOption($optionValueIds[$optionValueKey], $optionValue, $displayOrder, $isArchived);
            }
          }
          unset($existingOptions[$optionValueIds[$optionValueKey]]);
        } else {
          $this->CreditListings->insertCustomDataPointOption($optionGroupId, $optionValue, $displayOrder);
        }
      }
      foreach($existingOptions as $existingOptionId => $existingOption) {
        $result['delete'][] = $existingOption;
        $this->CreditListings->deleteCustomDataPointOption($existingOptionId, $optionGroupId, $existingOption['label']);
        /*
        if($existingOption['numUses'] > 0) {
          $this->CreditListings->updateCustomDataPointOption($existingOptionId, $existingOption['label'], $existingOption['display_order'], 1);
        } else {
          $this->CreditListings->deleteCustomDataPointOption($existingOptionId);
        }
        */
      }

    } else { //all options deleted!
      foreach($existingOptions as $existingOptionId => $existingOption) {
        $result['delete'][] = $existingOption;
        if($existingOption['numUses'] > 0) {
          $this->CreditListings->updateCustomDataPointOption($existingOptionId, $existingOption['label'], $existingOption['display_order'], 1);
        } else {
          $this->CreditListings->deleteCustomDataPointOption($existingOptionId);
        }
      }
    }

    return $result;
  }

  function custom_datapoint_edit($id = 0) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/dashboard');

      return false;
    }
    if(!$this->cisession->userdata('levelLoadCredit')) {
      redirect('/dmamembers/no_permission');

      return false;
    }
    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      redirect('/dashboard');

      return false;
    }

    $data['tabArray'] = "custom_datapoints";
    $data['current_tab_key'] = "";
    $data['lnav_key'] = "administrative";

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
      redirect('/dashboard');

      return false;
    }

    $cdp = null;
    if($id > 0) {
      //Check permission
      $this->memberpermissions->checkCustomDataPointAccess($id);
      $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
      $dpRequest['includeArchived'] = 2;
      $customDataPointsRaw = $this->CreditListings->getDataPointsGrouped($dpRequest);

      foreach($customDataPointsRaw['dataPoints'] as $dataPoint) {
        if($dataPoint['dpId'] == $id) {
          $cdp = $dataPoint;
        }
      }
    }
    $data['cdp'] = $cdp;
    $req['dmaId'] = $this->cisession->userdata('dmaId');
    $req['includeArchived'] = true;
    $data['optionGroups'] = $this->CreditListings->get_option_groups($req);

    $flashOptionGroupId = $this->session->flashdata('optionGroupId');
    if($flashOptionGroupId > 0) {
      $data['cdp']['option_group_id'] = $flashOptionGroupId;
    }

    /*
    echo '<pre>';
    var_dump($data['cdp']);
    exit;
    */

    $data['objectTypeLabels'] = ['credit' => 'Credits', 'utilization' => 'Utilizations', 'legal-entity' => 'Legal Entities'];
    $data['sectionLabels'] = [
        'credit_status_settings'           => 'Status Settings',
        'credit_project_details'           => 'Project Details',
        'credit_incentive_program_details' => 'Incentive Program Details',
        'credit_date_settings'             => 'Date Settings',
        'credit_amount_details'            => 'Credit Amount Details',
        'credit_additional_details'        => 'Additional Details',
        'legal_entity_representative_info' => 'Representative Information',
        'legal_entity_basic_info'          => 'Basic Information',
        'legal_entity_additional_details'  => 'Additional Details',
        'utilization_additional_details'   => 'Additional Details',
    ];

    $output = '';
    $twig = \OIX\Util\TemplateProvider::getTwig();
    $template = $twig->load('dmaaccount/custom_data_point_edit.twig');
    $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
    $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

    $output .= $this->load->view('includes/left_nav', $data, true);
    $output .= $this->load->view('includes/tab_nav', $data, true);
    $output .= $template->render($data);
    $output .= $this->load->view('includes/footer-2', $data, true);
    echo $output;
  }

  function custom_datapoint_save() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');

      return false;
    } else {

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
          throw new \Exception('General fail');
        }

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
          throw new \Exception('General fail');
        }

        /*
        echo '<pre>';
        foreach($this->input->post('objectTypeStatus') as $objectType=>$objectTypeStatus) {
          var_dump($objectType, $this->input->post('dpSection-'.$objectType));
        }

        var_dump($_POST);
        exit;
        */

        if($this->input->post('dpName') != "") {

          if($this->input->post('dpId') > 0) {
            //Check permission
            $this->memberpermissions->checkCustomDataPointAccess($this->input->post('dpId'));

            $existingCdp = null;
            $existingCdpChildren = [];

            $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
            $dpRequest['includeArchived'] = 2;
            $customDataPointsRaw = $this->CreditListings->get_data_points($dpRequest);

            foreach($customDataPointsRaw['dataPoints'] as $dataPoint) {
              if($dataPoint['dpId'] == $this->input->post('dpId')) {
                $existingCdp = $dataPoint;
              }
              if($dataPoint['parent_id'] == $this->input->post('dpId')) {
                $existingCdpChildren[$dataPoint['dpObjectType']] = $dataPoint;
              }
            }

            $newObjTypes = array_keys($this->input->post('objectTypeStatus'));
            if($existingCdp['is_parent'] == '1') {
              $request = [
                  'dpId'          => $existingCdp['dpId'],
                  'dpType'        => $this->input->post('dpType'),
                  'dpName'        => $this->input->post('dpName'),
                  'dpDescription' => trim($this->input->post('dpDescription')),
                  'dpDmaId'       => $this->cisession->userdata('dmaId'),
                  'dpIsRequired'  => $this->input->post('dpIsRequired') == 'on' ? 1 : 0,
              ];
              if($this->input->post('dpOptionGroupId') > 0) {
                $request['option_group_id'] = $this->input->post('dpOptionGroupId');
              }
              else if($existingCdp['option_group_id'] > 0) {
                $request['option_group_id'] = $existingCdp['option_group_id'];
              }

              if(strlen($existingCdp['dpArchivedMarker']) > 0) {
                $request['dpArchivedMarker'] = $existingCdp['dpArchivedMarker'];
              }

              if(strlen($this->input->post('dpName')) > 0) {
                $request['dpName'] = $this->input->post('dpName');
              }
              else if(strlen($existingCdp['dpNameFull']) > 0) {
                $request['dpName'] = $existingCdp['dpNameFull'];
              }
              $this->CreditListings->update_custom_data_point($request);

              foreach($this->input->post('objectTypeStatus') as $objectType => $objectTypeStatus) {
                if(isset($existingCdpChildren[$objectType])) {
                  $request = [
                      'dpId'             => $existingCdpChildren[$objectType]['dpId'],
                      'dpArchivedMarker' => null,
                      'dpType'           => '',
                      'dpObjectType'     => $objectType,
                      'dpSection'        => $this->input->post('dpSection-' . $objectType),
                      'dpDmaId'          => $this->cisession->userdata('dmaId'),
                  ];
                  $this->CreditListings->update_custom_data_point($request);
                } else {
                  $request = [
                      'dpId'            => $existingCdpChildren[$objectType]['dpId'],
                      'parent_id'       => $existingCdp['dpId'],
                      'dpSection'       => $this->input->post('dpSection-' . $objectType),
                      'dpObjectType'    => $objectType,
                      'dpName'          => null,
                      'dpDescription'   => null,
                      'dpIsRequired'    => null,
                      'dpType'          => null,
                      'option_group_id' => null,
                      'dpDmaId'         => $this->cisession->userdata('dmaId'),
                  ];
                  $datapointId = $this->CreditListings->insert_custom_data_point($request);
                }
              }

              foreach($existingCdpChildren as $childCdpObjType => $childCdp) {
                if(!in_array($childCdpObjType, $newObjTypes)) {
                  $request = [
                      'dpId'             => $childCdp['dpId'],
                      'dpType'           => '',
                      'dpObjectType'     => $childCdpObjType,
                      'dpSection'        => $childCdp['dpSection'],
                      'dpArchivedMarker' => 1,
                      'dpDmaId'          => $this->cisession->userdata('dmaId'),
                  ];
                  $this->CreditListings->update_custom_data_point($request);
                }
              }
            } else {
              if(count($newObjTypes) === 1 && $newObjTypes[0] == $existingCdp['dpObjectType']) { //single cdp legit update
                $request = [];
                $request['dpDmaId'] = $this->cisession->userdata('dmaId');
                $request['dpType'] = $this->input->post('dpType');
                $request['dpId'] = $existingCdp['dpId'];
                $request['dpName'] = $this->input->post('dpName');
                $request['dpSection'] = $this->input->post('dpSection-' . $existingCdp['dpObjectType']);
                $request['dpObjectType'] = $existingCdp['dpObjectType'];
                $request['dpDescription'] = trim($this->input->post('dpDescription'));
                $request['dpIsRequired'] = $this->input->post('dpIsRequired') == 'on' ? 1 : 0;
                if($this->input->post('dpOptionGroupId') > 0) {
                  $request['option_group_id'] = $this->input->post('dpOptionGroupId');
                } else if($existingCdp['option_group_id'] > 0) {
                  $request['option_group_id'] = $existingCdp['option_group_id'];
                }

                $this->CreditListings->update_custom_data_point($request);
              } else if(count($this->input->post('objectTypeStatus')) > 0) { //time to convert to a parent

                $request = [
                    'dpId'    => $existingCdp['dpId'],
                    'dpName'  => $existingCdp['dpName'] . ' - Child',
                    'dpDmaId' => $this->cisession->userdata('dmaId'),
                ];
                $this->CreditListings->update_custom_data_point($request);

                $request = [];
                $request['dpDmaId'] = $this->cisession->userdata('dmaId');
                $request['dpName'] = $this->input->post('dpName');
                $request['dpType'] = $this->input->post('dpType');
                $request['is_parent'] = 1;
                $request['dpObjectType'] = '';
                $request['dpDescription'] = trim($this->input->post('dpDescription'));
                $request['dpIsRequired'] = $this->input->post('dpIsRequired') == 'on' ? 1 : 0;
                if($this->input->post('dpOptionGroupId') > 0) {
                  $request['option_group_id'] = $this->input->post('dpOptionGroupId');
                } else if($existingCdp['option_group_id'] > 0) {
                  $request['option_group_id'] = $existingCdp['option_group_id'];
                }
                $parentDpId = $this->CreditListings->insert_custom_data_point($request);

                foreach($this->input->post('objectTypeStatus') as $objectType => $objectTypeStatus) {
                  if($existingCdp['dpObjectType'] != $objectType) {
                    if(isset($existingCdpChildren[$objectType])) {
                      $request = [
                          'dpId'             => $existingCdpChildren[$objectType]['dpId'],
                          'dpType'           => '',
                          'dpArchivedMarker' => null,
                          'dpDmaId'          => $this->cisession->userdata('dmaId'),
                          'dpSection'        => $this->input->post('dpSection-' . $objectType),
                      ];
                      $this->CreditListings->update_custom_data_point($request);
                    } else {
                      $request = [
                          'dpId'            => $existingCdpChildren[$objectType]['dpId'],
                          'parent_id'       => $parentDpId,
                          'dpSection'       => $this->input->post('dpSection-' . $objectType),
                          'dpObjectType'    => $objectType,
                          'dpDmaId'         => $this->cisession->userdata('dmaId'),
                          'dpName'          => null,
                          'dpDescription'   => null,
                          'dpIsRequired'    => null,
                          'dpType'          => null,
                          'option_group_id' => null,
                      ];
                      $datapointId = $this->CreditListings->insert_custom_data_point($request);
                    }
                  }
                }

                //update previous single CDP to child of parent
                $request = [
                    'dpId'            => $existingCdp['dpId'],
                    'parent_id'       => $parentDpId,
                    'dpObjectType'    => $existingCdp['dpObjectType'],
                    'dpName'          => null,
                    'dpDescription'   => null,
                    'dpIsRequired'    => null,
                    'dpType'          => '',
                    'option_group_id' => null,
                    'dpDmaId'         => $this->cisession->userdata('dmaId'),
                ];
                if(!in_array($existingCdp['dpObjectType'], $newObjTypes)) {
                  $request['dpArchivedMarker'] = 1;
                }
                $this->CreditListings->update_custom_data_point($request);
              }
            }

            //Check if data point is in use (do not allow updating type if so)
            /*
            $cdpvRequest['dpId'] = $this->input->post('dpId');
            $cdpvRequest['listingId'] = "";
            $cdpvRequest['dpObjectType'] = $this->input->post('dpObjectType');
            $dataPointValues = $this->CreditListings->get_custom_data_point_values($cdpvRequest);
            */

          } else {
            //regular single object type orphan CDP
            if(count($this->input->post('objectTypeStatus')) === 1) {
              $objectType = array_key_first($this->input->post('objectTypeStatus'));
              $request['dpDmaId'] = $this->cisession->userdata('dmaId');
              $request['dpName'] = $this->input->post('dpName');
              $request['dpType'] = $this->input->post('dpType');
              $request['dpSection'] = $this->input->post('dpSection-' . $objectType);
              $request['dpObjectType'] = $objectType;
              $request['dpDescription'] = trim($this->input->post('dpDescription'));
              $request['dpIsRequired'] = $this->input->post('dpIsRequired') == 'on' ? 1 : 0;
              if($this->input->post('dpOptionGroupId') > 0) {
                $request['option_group_id'] = $this->input->post('dpOptionGroupId');
              }
              $datapointId = $this->CreditListings->insert_custom_data_point($request);
            } else if(count($this->input->post('objectTypeStatus')) > 1) {
              $request['dpDmaId'] = $this->cisession->userdata('dmaId');
              $request['dpName'] = $this->input->post('dpName');
              $request['dpType'] = $this->input->post('dpType');
              $request['dpObjectType'] = '';
              $request['is_parent'] = 1;
              $request['dpDescription'] = trim($this->input->post('dpDescription'));
              $request['dpIsRequired'] = $this->input->post('dpIsRequired') == 'on' ? 1 : 0;
              if($this->input->post('dpOptionGroupId') > 0) {
                $request['option_group_id'] = $this->input->post('dpOptionGroupId');
              }
              $parentDpId = $this->CreditListings->insert_custom_data_point($request);

              foreach($this->input->post('objectTypeStatus') as $objectType => $objectTypeStatus) {
                $request = [];
                $request['dpDmaId'] = $this->cisession->userdata('dmaId');
                $request['parent_id'] = $parentDpId;
                $request['dpSection'] = $this->input->post('dpSection-' . $objectType);
                $request['dpObjectType'] = $objectType;
                $datapointId = $this->CreditListings->insert_custom_data_point($request);
              }
            }
          }

        }

        $this->session->set_flashdata('saveSuccess', 1);

        redirect('/dmaaccount/custom_datapoints');

      }

    }

  }

  function custom_datapoint_get_usages($id = 0) {
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');

      return false;
    }

    if(!$this->cisession->userdata('levelLoadCredit')) {
      redirect('/dmamembers/no_permission');

      return false;
    }

    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      throw new \Exception('General fail');
    }

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
      throw new \Exception('General fail');
    }

    $data['objectTypeLabels'] = ['credit' => 'Credit', 'utilization' => 'Utilization', 'legal-entity' => 'Legal Entity'];
    $values = $this->CreditListings->get_all_custom_values_for_data_point($id);

    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($sanitizedPostData);
    $cData['showArchived'] = 2;

    $cdpValues = [];
    foreach($values as $value) {
      $value['objectLabel'] = $data['objectTypeLabels'][$value['cvObjectType']] . ": ";
      switch($value['cvObjectType']) {
        case 'credit':
          $cData['listingId'] = $value['cvObjectId'];
          $obj = $this->CreditListings->get_credits($cData);
          $value['objectViewUrl'] = '/dashboard/credit/' . $value['cvObjectId'];
          $value['objectEditUrl'] = '/dashboard/editcreditdetails/' . $value['cvObjectId'];
          if(isset($obj['credits'][0]['creditIdFull'])) {
            $value['objectLabel'] .= ' ' . $obj['credits'][0]['creditIdFull'] . ' - ' . $obj['credits'][0]['projectNameFull'];
          } else {
            $value['objectLabel'] = 'Credit: (you do not have permission to this credit)';
            $value['objectViewUrl'] = '';
            $value['objectEditUrl'] = '';
          }

          break;
        case 'utilization':
          $tradeData = $this->Trading->get_trade($value['cvObjectId']);
          $cData['listingId'] = $tradeData['trade']['listingId'];
          $obj = $this->CreditListings->get_credits($cData);
          $value['objectViewUrl'] = '/dashboard/credit/' . $tradeData['trade']['listingId'] . '/utilization/' . $value['cvObjectId'];
          $value['objectEditUrl'] = '/dashboard/utilization_new_estimates/' . $tradeData['trade']['listingId'] . '/' . $value['cvObjectId'];

          if(isset($obj['credits'][0]['creditIdFull'])) {
            $value['objectLabel'] .= ' ' . $obj['credits'][0]['creditIdFull'] . '-' . $value['cvObjectId'] . ' - ' . $obj['credits'][0]['projectNameFull'];
          } else {
            $value['objectLabel'] = 'Utilization: (you do not have permission to this utilization)';
            $value['objectViewUrl'] = '';
            $value['objectEditUrl'] = '';
          }
          break;
        case 'legal-entity':
          $obj = $this->Trading->get_taxpayer($value['cvObjectId']);
          if(isset($obj['tpCompanyName'])) {
            $value['objectLabel'] .= ' ' . $obj['tpCompanyName'];
          }
          $value['objectViewUrl'] = '/taxpayers/entity/' . $value['cvObjectId'];
          $value['objectEditUrl'] = '/taxpayers/entity/' . $value['cvObjectId'];
          break;
      }
      $cdpValues[] = $value;

    }

    $data['cdpValues'] = $cdpValues;
    $output = '';
    $twig = \OIX\Util\TemplateProvider::getTwig();
    $template = $twig->load('dmaaccount/custom_data_point_usages_overlay.twig');
    $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
    $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

    $output .= $template->render($data);
    echo $output;
  }

  function custom_datapoint_check_name($id = 0) {
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');

      return false;
    }

    if(!$this->cisession->userdata('levelLoadCredit')) {
      redirect('/dmamembers/no_permission');

      return false;
    }

    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      throw new \Exception('General fail');
    }

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
      throw new \Exception('General fail');
    }

    //check_data_point_name_availability($name ,$dmaId, $id)  check_data_point_option_group_name_availability()
    $isNameAvailable = $this->CreditListings->check_data_point_name_availability($this->input->post('name'), $this->cisession->userdata('dmaId'), $id);

    echo json_encode(['available' => $isNameAvailable]);
  }

  function custom_datapoint_option_group_check_name($id = 0) {
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');

      return false;
    }

    if(!$this->cisession->userdata('levelLoadCredit')) {
      redirect('/dmamembers/no_permission');

      return false;
    }

    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      throw new \Exception('General fail');
    }

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
      throw new \Exception('General fail');
    }

    $isNameAvailable = $this->CreditListings->check_data_point_option_group_name_availability($this->input->post('name'), $this->cisession->userdata('dmaId'), $id);

    echo json_encode(['available' => $isNameAvailable]);
  }

  function custom_datapoint_status_update() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');
    } else {

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
          throw new \Exception('General fail');
        }

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
          throw new \Exception('General fail');
        }

        //Check permission
        $dataPointToValidate = 0;
        if($this->input->post('dpIdArchive') > 0) {
          $dataPointToValidate = $this->input->post('dpIdArchive');
        } else if($this->input->post('dpIdUnArchive') > 0) {
          $dataPointToValidate = $this->input->post('dpIdUnArchive');
        } else if($this->input->post('dpIdDelete') > 0) {
          $dataPointToValidate = $this->input->post('dpIdDelete');
        } else {
          throw new \Exception('General fail');
        }
        $this->memberpermissions->checkCustomDataPointAccess($dataPointToValidate);

        //IF this is an Archive action....
        if($this->input->post('dpIdArchive') > 0) {

          //Check if data point is in use (do not allow updating type if so)
          $cdpvRequest['dpId'] = $this->input->post('dpIdArchive');
          $cdpvRequest['listingId'] = "";
          //$cdpvRequest['dpObjectType'] = 'credit';
          $request['dpId'] = $this->input->post('dpIdArchive');
          $request['field'] = 'dpArchivedMarker';
          $request['value'] = 1;
          $this->CreditListings->update_custom_data_point_status($request);
          //set flash
          $this->session->set_flashdata('archiveSuccess', 1);

        }

        //IF this is an Archive action....
        if($this->input->post('dpIdUnArchive') > 0) {

          //Only allow archiving if this data point has been used
          $request['dpId'] = $this->input->post('dpIdUnArchive');
          $request['field'] = 'dpArchivedMarker';
          $request['value'] = null;
          $this->CreditListings->update_custom_data_point_status($request);
          //set flash
          $this->session->set_flashdata('unArchiveSuccess', 1);

        }

        //If this is a delete action
        if($this->input->post('dpIdDelete') > 0) {
          //Check if data point is in use (do not allow updating type if so)
          $cdpvRequest['dpId'] = $this->input->post('dpIdDelete');
          $cdpvRequest['listingId'] = "";
          //$cdpvRequest['dpObjectType'] = 'credit';
          $dataPointValues = $this->CreditListings->get_custom_data_point_values($cdpvRequest);
          if(sizeof($dataPointValues) > 0) {
            //Do not allow delete if this data point has been used
          } else {
            //Delete it
            $this->CreditListings->delete_custom_data_point($this->cisession->userdata('dmaId'), $this->input->post('dpIdDelete'));
          }

          $this->session->set_flashdata('deleteSuccess', 1);

        }

        redirect('/dmaaccount/custom_datapoints');

      }

    }

  }

  function custom_datapoint_option_group_status_update($id = 0) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');

      return false;
    } else {
      $optionGroupId = null;

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
          throw new \Exception('General fail');
        }

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
          throw new \Exception('General fail');
        }

        $this->memberpermissions->checkOptionGroupAccess($id);

        $newStatus = $this->input->post('status');
        switch($newStatus) {
          case "unArchive":
            $this->CreditListings->update_option_group_status($id, $newStatus);
            $this->session->set_flashdata('unArchiveSuccessOG', 1);
            break;
          case "archive":
            $optionGroup = $this->CreditListings->get_option_group($id);
            if($optionGroup['numUses'] > 0) {
              $this->CreditListings->update_option_group_status($id, $newStatus);
              $this->session->set_flashdata('archiveSuccessOG', 1);
            }

            break;
          case "delete":
            $optionGroup = $this->CreditListings->get_option_group($id);
            if($optionGroup['numUses'] == 0) {
              $this->CreditListings->update_option_group_status($id, $newStatus);
              $this->session->set_flashdata('deleteSuccessOG', 1);
            }
            break;
        }

        redirect('/dmaaccount/custom_datapoints'); //TODO: this should send user to option group list view
      }

    }
  }

  function custom_datapoint_option_group_edit($id = 0) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/dashboard');

      return false;
    }
    if(!$this->cisession->userdata('levelLoadCredit')) {
      redirect('/dmamembers/no_permission');

      return false;
    }
    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      redirect('/dashboard');

      return false;
    }

    $data['tabArray'] = "custom_datapoints";
    $data['current_tab_key'] = "";
    $data['lnav_key'] = "administrative";

    $data['planData'] = $this->cisession->userdata('planData');
    if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
      redirect('/dashboard');

      return false;
    }

    $optionGroup = null;
    if($id > 0) {
      //Check permission
      $this->memberpermissions->checkOptionGroupAccess($id);
      $req['dmaId'] = $this->cisession->userdata('dmaId');
      $req['includeArchived'] = true;
      $optionGroup = $this->CreditListings->get_option_group($id);
    }
    $data['optionGroup'] = $optionGroup;

    $output = '';
    $twig = \OIX\Util\TemplateProvider::getTwig();
    $template = $twig->load('dmaaccount/option_group_edit.twig');
    $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
    $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

    $output .= $this->load->view('includes/left_nav', $data, true);
    $output .= $this->load->view('includes/tab_nav', $data, true);
    $output .= $template->render($data);
    $output .= $this->load->view('includes/footer-2', $data, true);
    echo $output;
  }

  function custom_datapoint_option_group_save() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      redirect('/');

      return false;
    } else {
      $optionGroupId = null;

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
          throw new \Exception('General fail');
        }

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['customDataPoints']) && !isset($data['planData']['api'])) {
          throw new \Exception('General fail');
        }

        if($this->input->post('name') != "") {

          if($this->input->post('id') > 0) {
            //Check permission
            $this->memberpermissions->checkOptionGroupAccess($this->input->post('id'));

            $result = $this->parseEditDataPointsReq($this->input->post('id'));
            $optionGroupId = $this->input->post('id');

            $request['id'] = $optionGroupId;
            $request['name'] = null;
            if(strlen(trim($this->input->post('name'))) > 0) {
              $request['name'] = trim($this->input->post('name'));
            }
            $this->CreditListings->update_option_group($request);

          } else {
            //Insert record
            $request['dma_id'] = $this->cisession->userdata('dmaId');
            $request['name'] = null;
            if(strlen(trim($this->input->post('name'))) > 0) {
              $request['name'] = trim($this->input->post('name'));
            }
            $optionGroupId = $this->CreditListings->insert_option_group($request);
            $optionValues = $this->input->post('values');
            if(isset($optionValues) && count($optionValues) > 0) {
              $displayOrder = 0;
              foreach($optionValues as $optionValueKey => $optionValue) {
                $displayOrder++;
                $this->CreditListings->insertCustomDataPointOption($optionGroupId, $optionValue, $displayOrder);
              }
            }
          }
        }

        $this->session->set_flashdata('saveSuccessOG', 1);

        //previous_location_data  previous_location
        if($this->input->post('previous_location') != '') {
          $this->session->set_flashdata('optionGroupId', $optionGroupId);
          redirect(trim($this->input->post('previous_location')));
        } else {
          redirect('/dmaaccount/custom_datapoints');
        }

      }

    }
  }

  function api_documentation() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['tabArray'] = "api_settings";
        $data['current_tab_key'] = "api_documentation";
        $data['lnav_key'] = "administrative";

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $data['tabArray'] = "api_settings_noaccess";
        }

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $this->load->view('includes/widgets/planBlockMessage');
        } else {
          $this->load->view('dmaaccount/api_documentation', $data);
        }
        $this->load->view('includes/footer-2');

      }
    }

  }

  function compliance_alerts() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelLoadCredit')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['tabArray'] = "compliance_alerts";
        $data['current_tab_key'] = "";
        $data['lnav_key'] = "administrative";

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
        $data['complianceAlerts'] = $this->Workflow->get_compliance_alerts($complianceListingIds);
        $data['complianceAlertCount'] = sizeof($data['complianceAlerts']);

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
        if(!$this->cisession->userdata('planCompliance')) {
          $this->load->view('includes/widgets/planBlockMessage');
        } else {
          $this->load->view('dmaaccount/compliance_alerts', $data);
        }
        $this->load->view('includes/footer-2');

      }

    }

  }

  function usage_tracker($month = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelEditAccount')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['tabArray'] = "dmaaccount";
        $data['current_tab_key'] = "";
        $data['lnav_key'] = "administrative";

        $listedBy = $this->cisession->userdata('primUserId');
        $audTypeId = 4;
        $data['auditReportRaw'] = $this->AuditTrail->get_audit_trail_of_item_for_account($listedBy, $audTypeId, 'amountLocal');

        $data['auditReport'] = [];
        $data['auditReportHistory'] = [];

        if($month > 0) {
          $reportStartDate = mktime(0, 0, 0, $month, 1);
          $reportEndDate = mktime(23, 59, 59, $month, date("t", $reportStartDate));
        }

        foreach($data['auditReportRaw'] as $a1) {

          $arCount = 0;
          $arOriginalValRaw = $a1[0]['amountLocal'];
          $arOriginalVal = str_replace(['.00', '$', ','], "", $arOriginalValRaw);
          $arLastCycle = 0;
          $arLastMonth = 0;

          foreach($a1 as $a2) {

            $arThisVal = str_replace(['.00', '$', ','], "", $a2['amountLocal']);
            $a2['varToOrig'] = ($arThisVal > $arOriginalVal) ? '<span class="greenText">+$' . number_format($arThisVal - $arOriginalVal) . '</span>' : '<span class="redText">-$' . number_format($arOriginalVal - $arThisVal) . '</span>';
            $a2['varToLastCycle'] = ($arThisVal > $arLastCycle) ? '<span class="greenText">+$' . number_format($arThisVal - $arLastCycle) . '</span>' : '<span class="redText">-$' . number_format($arLastCycle - $arThisVal) . '</span>';

            if($month > 0 && $a2['timeStampUnix'] > $reportStartDate && $a2['timeStampUnix'] <= $reportEndDate) {

              if(!array_key_exists($a2['listingId'], $data['auditReport'])) {
                $data['auditReport'][$a2['listingId']] = [];
              }
              array_push($data['auditReport'][$a2['listingId']], $a2);
            }

            if(!array_key_exists($a2['listingId'], $data['auditReportHistory'])) {
              $data['auditReportHistory'][$a2['listingId']] = [];
            }
            array_push($data['auditReportHistory'][$a2['listingId']], $a2);

            $arCount++;
            $arThisMonth = date('m', $a2['timeStampUnix']);
            if($arThisMonth > $arLastMonth) {
              $arLastCycle = $arThisVal;
            }

          }

        }

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
        $this->load->view('dmaaccount/usage_tracker', $data);
        $this->load->view('includes/footer-2');

      }

    }

  }

  function no_permission() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "";
      $data['current_tab_key'] = "";
      $data['tab_nav'] = "";

      $this->load->view('includes/left_nav', $data);
      $this->load->view('dmaaccount/no_permission');
      $this->load->view('includes/footer-2');

    }

  }




  /////////////////////////////////////////////////
  /////    BULK IMPORT DATA - BELOW
  /////////////////////////////////////////////////

  private function centralized_bulk_processor_credits($objectType, $uploadId) {

    //Check if person is logged in
    if(!$this->tank_auth->is_logged_in()) {
      echo "You must login";
      throw new \Exception('General fail');
    }

    // Get uploaded file info
    $file = $this->Uploads->get_file_info($uploadId);

    if(empty($file) || $file->status !== 'SUCCESS') {
      echo "File was not uploaded successfully.";
      throw new \Exception('General fail');
    }
    if($file->uploaded_by !== $this->cisession->userdata('dmaMemberId')) {
      echo "No Permission";
      throw new \Exception('General fail');
    }

    // Download the spreadsheet template from S3
    $fileName = $file->id . "-" . $file->hash . "." . $file->file_ext;

    $fileRequest['awsS3FileKey'] = $fileName;
    $fileRequest['awsS3BucketName'] = $this->config->item('OIX_AWS_BUCKET_NAME_CLIENT_FILES_QUEUE');
    $fileRequest['expirationMinutes'] = '+20 minutes';
    $fileUrl = $this->filemanagementlib->get_aws_s3_file_url($fileRequest);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);

    // Create file in the tmp_files directory for parse
    $filePath = FCPATH . 'tmp_files/' . $fileName;
    write_file($filePath, $output, 'w+');

    // Grab data from the spreadsheet
    $workbook = new cells\Workbook($filePath);
    if($objectType == 'IPF') {
      $sheet = $workbook->getWorksheets()->get(1);          // [International Portfolio Data] sheet
    } else if($objectType == 'UPF') {
      $sheet = $workbook->getWorksheets()->get(2);          // [US Portfolio Data] sheet
    }
    $cells = $sheet->getCells();

    $cols_count = (int)(string)$cells->getMaxDataColumn() + 1;
    $rows_count = (int)(string)$cells->getMaxDataRow() + 1;

    $headerRowCnt = 6;
    $applyRowCnt = 1000;

    //Get GENERAL data points
    $dpRequestGen = [];
    $dpRequestGen['dpObjectType'] = 'credit';
    $dataPointsGenRaw = $this->CreditListings->get_data_points($dpRequestGen);
    $dpRequestGen = $dataPointsGenRaw['dataPoints'];

    //Get CUSTOM data points
    $dpRequest = [];
    $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
    $dpRequest['dpObjectType'] = 'credit';
    $dataPointsRaw = $this->CreditListings->get_data_points($dpRequest);
    $dataPoints = $dataPointsRaw['dataPoints'];
    $sDataKeys = $dataPointsRaw['dataPointsKeysOnly'];

    //Merge General and Custom data points for certain needs
    $mergedDataPoints = array_merge($dpRequestGen, $dataPoints);

    // Get the dpKey for the Date type Data Points
    $customDataPointValsToKeys = [];
    $dateCustomDataPointFlag = [];
    foreach($mergedDataPoints as $j => $dp) {
      // If it's date field, ...
      if($dp['dpType'] == 'date') {
        if($dp['dpDmaId'] > 0) {
          array_push($dateCustomDataPointFlag, $dp['dpValue']);
        } else {
          array_push($dateCustomDataPointFlag, $dp['dpKey']);
        }
      }
      //add all CDPs to key value
      if($dp['dpDmaId'] > 0) {
        $customDataPointValsToKeys[$dp['dpValue']] = $dp['dpKey'];
      }

    }

    // Grab cell data
    $cellData = [[]];
    for($i = 0; $i < $rows_count; $i++) {
      for($j = 0; $j < $cols_count; $j++) {
        $val = (string)$cells->get($i, $j)->getValue();

        // If cell is generic dropdown value, it should be empty value.
        if($val === 'Select Value') {
          $val = null;
        }

        if(in_array($cellData[2][$j], $customDataPointValsToKeys)) {
          $thisCDPnum = array_search($cellData[2][$j], $customDataPointValsToKeys);
          $cellData[2][$j] = $thisCDPnum;
        }

        if($i >= $headerRowCnt && in_array($cellData[2][$j], $dateCustomDataPointFlag) && !empty($val)) {
          // Format date 'mm/dd/yyyy'
          $cellData[$i][$j] = date('m/d/Y', strtotime($val));
        } else {
          $cellData[$i][$j] = $val;
        }
      }
    }

    // delete xlsx file in the tmp_files directory
    unlink($filePath);

    $ret = [];
    $ret['ErrorCode'] = SP_SUCCESS;

    // Get the number of data rows
    $dataRowCnt = $applyRowCnt - $headerRowCnt;

    if($objectType == 'IPF') {
      for($i = $applyRowCnt - 1; $i >= $headerRowCnt; $i--) {
        for($j = 0; $j < $cols_count; $j++) {
          if($cellData[2][$j] !== 'fieldSharedAccount' && $cellData[2][$j] !== 'fieldAmountUSD' && !empty($cellData[$i][$j])) {
            break;
          }
        }

        // If all columns are empty, decrease data row count
        if($j == $cols_count) {
          $dataRowCnt--;
        } else if($j < $cols_count) {
          // If non empty row faced, no need to count down data row count
          break;
        }
      }

      $invalidRows = [];   // Store the rows having empty required cell

      // Check if required columns have cell data
      $customIncentiveProgramSet = false;
      for($i = $headerRowCnt; $i < $headerRowCnt + $dataRowCnt; $i++) {
        if(!empty($cellData[$i][0])) {    // If Credit ID has value, need to be updated. Skip checking required columns
          continue;
        }
        for($j = 0; $j < $cols_count; $j++) {
          if($cellData[2][$j] === 'fieldCountry' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Country');
          }
          if($cellData[2][$j] === 'fieldIncentiveProgramCustom' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Custom Incentive Program Name');
          }
          if($cellData[2][$j] === 'fieldSellerTaxEntity' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Legal Entity Name');
          }
          if($cellData[2][$j] === 'fieldAmountLocal' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Estimated Credit Amount (Face Value in Local Currency)');
          }
          if($cellData[2][$j] === 'fieldLocalCurrency' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Local Currency');
          }
          if($cellData[2][$j] === 'fieldBudgetExchangeRate' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Budgeted Exchange Rate to USD');
          }
        }
      }
    } else if($objectType == 'UPF') {
      for($i = $applyRowCnt - 1; $i >= $headerRowCnt; $i--) {
        for($j = 0; $j < $cols_count; $j++) {
          if($cellData[2][$j] !== 'fieldSharedAccount' && !empty($cellData[$i][$j])) {
            break;
          }
        }

        // If all columns are empty, decrease data row count
        if($j == $cols_count) {
          $dataRowCnt--;
        } else if($j < $cols_count) {
          // If non empty row faced, no need to count down data row count
          break;
        }
      }

      $invalidRows = [];   // Store the rows having empty required cell

      // Check if required columns have cell data
      for($i = $headerRowCnt; $i < $headerRowCnt + $dataRowCnt; $i++) {
        if(!empty($cellData[$i][0])) {    // If Credit ID has value, need to be updated. Skip checking required columns
          continue;
        }
        for($j = 0; $j < $cols_count; $j++) {
          if($cellData[2][$j] === 'fieldJurisdiction' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Jurisdiction / State or Federal');
          }
          /*
          if($cellData[2][$j] === 'fieldIncentiveProgram' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Incentive Program Name');
          }
          */
          if($cellData[2][$j] === 'fieldSellerTaxEntity' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Legal Entity Name');
          }
        }
      }
    }

    //If only the top header rows exist then there is no data - so throw error
    if($rows_count <= $headerRowCnt || $dataRowCnt == 0) {
      $ret['ErrorCode'] = SP_NO_DATA;
    }

    if(!empty($invalidRows)) {
      $ret['ErrorCode'] = SP_INVALID_ROW;
      $ret['InvalidRows'] = $invalidRows;
    }

    // Get the GooglePlaceId and JurisdictionId for the credit rows
    $googlePlaceIdList = [];
    $jurisdictionIdList = [];

    // Get the CDP drop down option's ID
    $customDataPointOptionIdList = [[]];

    $countryColNum = array_search('fieldCountry', $cellData[2]);
    $stateColNum = array_search('fieldProvinceName', $cellData[2]);
    $countyColNum = array_search('fieldCountyName', $cellData[2]);
    $cityColNum = array_search('fieldTownName', $cellData[2]);

    $this->jurisdictionSvc = new \OIX\Services\JurisdictionService($this->config->item('google_maps_key'));

    for($i = $headerRowCnt; $i < $headerRowCnt + $dataRowCnt; $i++) {
      $credit = $cellData[$i];
      $googlePlaceId = '';
      $jurisdictionId = '';

      // If only State is specified, get the google place ID from States table
      if($objectType === 'UPF' && ($countryColNum === false || empty($credit[$countryColNum])) && ($stateColNum !== false && !empty($credit[$stateColNum])) &&
          ($cityColNum === false || empty($credit[$cityColNum])) && ($countyColNum === false || empty($credit[$countyColNum]))) {
        $stateDetail = $this->IncentivePrograms->get_state_detail_by_name($credit[$stateColNum]);
        $googlePlaceId = $stateDetail['googlePlaceId'];
        $jurisdictionId = $this->jurisdictionSvc->getByPlaceId($googlePlaceId);
      } else if($countryColNum !== false || $stateColNum !== false || $countyColNum !== false || $cityColNum !== false) {
        $jurisdictionSearchString = '';
        $jurisdictionSearchString .= ($cityColNum !== false && $credit[$cityColNum] != '') ? $credit[$cityColNum] . ' ' : '';
        $jurisdictionSearchString .= ($countyColNum !== false && $credit[$countyColNum] != '') ? $credit[$countyColNum] . ' ' : '';
        $jurisdictionSearchString .= ($stateColNum !== false && $credit[$stateColNum] != '') ? $credit[$stateColNum] . ' ' : '';
        if($objectType === 'IPF') {
          $jurisdictionSearchString .= ($countryColNum !== false && $credit[$countryColNum] != '') ? $credit[$countryColNum] : '';
        } else if($objectType === 'UPF') {
          $jurisdictionSearchString .= 'United States';
        }

        //Google Places Search
        $jurisdictionId = $this->jurisdictionSvc->getByName($jurisdictionSearchString);
      }

      $googlePlaceIdList[$i - $headerRowCnt] = $googlePlaceId;
      $jurisdictionIdList[$i - $headerRowCnt] = $jurisdictionId;

      // Get the CDP drop down option's ID
      $customDataPointOptionIdList[$i - $headerRowCnt] = [];
      for($j = 0; $j < $cols_count; $j++) {
        if(strpos($cellData[2][$j], 'custom_') === 0 && !empty($cellData[$i][$j])) {
          // If it's Custom Data Point, get the option's Id
          foreach($mergedDataPoints as $dp) {
            if($dp['dpValue'] === $cellData[2][$j] && $dp['dpType'] === 'selectDropDown') {
              $customDataPointOptionIdList[$i - $headerRowCnt][$dp['dpValue']] = null;
              foreach($dp['dpOptions'] as $option) {
                if($option['label'] === $cellData[$i][$j]) {
                  $customDataPointOptionIdList[$i - $headerRowCnt][$dp['dpValue']] = $option['id'];
                }
              }
            }
          }
        }
      }
    }

    $ret['sheetData'] = $cellData;
    $ret['headerRowCnt'] = $headerRowCnt;
    $ret['dataRowCnt'] = $dataRowCnt;
    $ret['googlePlaceIdList'] = $googlePlaceIdList;
    $ret['jurisdictionIdList'] = $jurisdictionIdList;
    $ret['customDataPointOptionIdList'] = $customDataPointOptionIdList;

    return $ret;
  }

  function bulk_process_credits($objectType, $uploadId) {
    // $objectType: Indicate bulk processing object
    //    IPF: International Portfolio Data
    //    UPF: US Portfolio Data

    set_time_limit(180);

    $this->initAspose();

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $objectType = strtoupper($objectType);
      $processedData = $this->centralized_bulk_processor_credits($objectType, $uploadId);

      //Once all the spreadsheet data is converted into an array
      $data['tabArray'] = "dmaaccount";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "administrative";

      $data['objectType'] = $objectType;
      $data['uploadId'] = $uploadId;

      $data['processedData'] = $processedData;

      $data['taxEntityList'] = $this->Taxpayersdata->get_my_taxpayers($this->cisession->userdata('dmaId'), 0, 0, 0, 0, 0, 0);
      $data['programTypeList'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('program_type');
      $data['certStatusList'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('certification_status');
      $data['projectStatusList'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('project_status');
      $data['auditStatusList'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('audit_status');
      $data['monetizationStatusList'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('monetization_status');

      $output = '';

      $twig = \OIX\Util\TemplateProvider::getTwig();
      $template = $twig->load('dmaaccount/bulk_process_credits.twig');
      $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
      $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates
      $data['google_maps_key'] = $this->config->item("google_maps_key"); //TODO: handle this in more generic way, inject certain variables into all templates

      $output .= $this->load->view('includes/left_nav', $data, true);
      $output .= $this->load->view('includes/tab_nav', $data, true);
      $output .= $template->render($data);
      $output .= $this->load->view('includes/footer-2', true);
      echo $output;
    }
  }

  private function centralized_bulk_processor_utilizations($objectType, $uploadId) {

    //Check if person is logged in
    if(!$this->tank_auth->is_logged_in()) {
      echo "You must login";
      throw new \Exception('General fail');
    }

    // Get uploaded file info
    $file = $this->Uploads->get_file_info($uploadId);

    if(empty($file) || $file->status !== 'SUCCESS') {
      echo "File was not uploaded successfully.";
      throw new \Exception('General fail');
    }
    if($file->uploaded_by !== $this->cisession->userdata('dmaMemberId')) {
      echo "No Permission";
      throw new \Exception('General fail');
    }

    // Download the spreadsheet template from S3
    $fileName = $file->id . "-" . $file->hash . "." . $file->file_ext;

    $fileRequest['awsS3FileKey'] = $fileName;
    $fileRequest['awsS3BucketName'] = $this->config->item('OIX_AWS_BUCKET_NAME_CLIENT_FILES_QUEUE');
    $fileRequest['expirationMinutes'] = '+20 minutes';
    $fileUrl = $this->filemanagementlib->get_aws_s3_file_url($fileRequest);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);

    // Create file in the tmp_files directory for parse
    $filePath = FCPATH . 'tmp_files/' . $fileName;
    write_file($filePath, $output, 'w+');

    // Grab data from the spreadsheet
    $workbook = new cells\Workbook($filePath);
    if($objectType == 'IUT') {
      $sheet = $workbook->getWorksheets()->get(3);          // [International Utilization Data] sheet
    } else if($objectType == 'UUT') {
      $sheet = $workbook->getWorksheets()->get(4);          // [US Utilization Data] sheet
    }
    $cells = $sheet->getCells();

    $cols_count = (int)(string)$cells->getMaxDataColumn() + 1;
    $rows_count = (int)(string)$cells->getMaxDataRow() + 1;

    $headerRowCnt = 6;
    $applyRowCnt = 1000;

    // Grab cell data
    $cellData = [[]];
    for($i = 0; $i < $rows_count; $i++) {
      for($j = 0; $j < $cols_count; $j++) {
        $val = (string)$cells->get($i, $j)->getValue();

        // If cell is generic dropdown value, it should be empty value.
        if($val === 'Select Value') {
          $val = null;
        }

        if($i >= $headerRowCnt && $cellData[2][$j] === 'fieldDateTraded' && !empty($val)) {
          // Format date 'mm/dd/yyyy'
          $cellData[$i][$j] = date('m/d/Y', strtotime($val));
        } else {
          $cellData[$i][$j] = $val;
        }
      }
    }

    // delete xlsx file in the tmp_files directory
    unlink($filePath);

    $ret = [];
    $ret['ErrorCode'] = SP_SUCCESS;

    // Get the number of data rows
    $dataRowCnt = $applyRowCnt - $headerRowCnt;

    if($objectType == 'IUT') {
      for($i = $applyRowCnt - 1; $i >= $headerRowCnt; $i--) {
        for($j = 0; $j < $cols_count; $j++) {
          if(!empty($cellData[$i][$j])) {
            break;
          }
        }

        // If all columns are empty, decrease data row count
        if($j == $cols_count) {
          $dataRowCnt--;
        } else if($j < $cols_count) {
          // If non empty row faced, no need to count down data row count
          break;
        }
      }

      $invalidRows = [];   // Store the rows if any required cell is empty

      // Check if required columns have cell data
      for($i = $headerRowCnt; $i < $headerRowCnt + $dataRowCnt; $i++) {
        if(!empty($cellData[$i][0])) {    // If Utilization ID has value, need to be updated. Skip checking required columns
          continue;
        }

        for($j = 0; $j < $cols_count; $j++) {
          if(empty($cellData[$i][1]) && $cellData[2][$j] === 'fieldInternalId' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'OIX Credit ID');
            array_push($invalidRows[$i], 'Internal Credit ID');
          }
          if($cellData[2][$j] === 'fieldDateTraded' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Utilization Date');
          }
          if($cellData[2][$j] === 'fieldUtilizationEstActStatus' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Utilization (est or act)');
          }
          if($cellData[2][$j] === 'fieldUtilizationType' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Utilization Type');
          }
          if($cellData[2][$j] === 'fieldTradeExchangeRate' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Spot Exchange Rate to USD');
          }
          if($cellData[2][$j] === 'fieldTradePrice' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Price per Credit (%)');
          }
        }
      }
    } else if($objectType == 'UUT') {
      for($i = $applyRowCnt - 1; $i >= $headerRowCnt; $i--) {
        for($j = 0; $j < $cols_count; $j++) {
          if(!empty($cellData[$i][$j])) {
            break;
          }
        }

        // If all columns are empty, decrease data row count
        if($j == $cols_count) {
          $dataRowCnt--;
        } else if($j < $cols_count) {
          // If non empty row faced, no need to count down data row count
          break;
        }
      }

      $invalidRows = [];   // Store the rows if any required cell is empty

      // Check if required columns have cell data
      for($i = $headerRowCnt; $i < $headerRowCnt + $dataRowCnt; $i++) {
        if(!empty($cellData[$i][0])) {    // If Utilization ID has value, need to be updated. Skip checking required columns
          continue;
        }

        for($j = 0; $j < $cols_count; $j++) {
          if(empty($cellData[$i][1]) && $cellData[2][$j] === 'fieldInternalId' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'OIX Credit ID');
            array_push($invalidRows[$i], 'Internal Credit ID');
          }
          if($cellData[2][$j] === 'fieldDateTraded' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Utilization Date');
          }
          if($cellData[2][$j] === 'fieldUtilizationEstActStatus' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Utilization (est or act)');
          }
          if($cellData[2][$j] === 'fieldUtilizationType' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Utilization Type');
          }
          if($cellData[2][$j] === 'fieldTradePrice' && empty($cellData[$i][$j])) {
            if(!array_key_exists($i, $invalidRows)) {
              $invalidRows[$i] = [];
            }
            array_push($invalidRows[$i], 'Price per Credit (%)');
          }
        }
      }
    }

    $invalidInternalIdRows = [];   // Store the rows have invalid Internal Id
    $invalidInternalIdRows['not_found'] = [];
    $invalidInternalIdRows['duplicated'] = [];
    $internalIdColNum = array_search('fieldInternalId', $cellData[2]);

    // If all rows are valid, then check the validation of Internal Credit Id
    if(empty($invalidRows) && $internalIdColNum !== -1) {
      for($i = $headerRowCnt; $i < $headerRowCnt + $dataRowCnt; $i++) {
        if(!empty($cellData[$i][0])) {    // If Utilization ID has value, need to be updated. Skip checking required columns
          continue;
        }

        if(!empty($cellData[$i][$internalIdColNum])) {

          $checkInternalId['id'] = $this->cisession->userdata('dmaId');
          $checkInternalId['internalId'] = $cellData[$i][$internalIdColNum];
          $checkInternalIdResult = $this->CreditListings->check_credit_exists_from_internal_id_for_account($checkInternalId);

          if($checkInternalIdResult['status'] === 'not_found') {
            array_push($invalidInternalIdRows['not_found'], $i + 1);
          } else if($checkInternalIdResult['status'] === 'duplicated') {
            array_push($invalidInternalIdRows['duplicated'], $i + 1);
          } else if($checkInternalIdResult['status'] === 'success') {
            $cellData[$i][1] = $checkInternalIdResult['listingId'];
          }
        }
      }
    }

    //If only the top header rows exist then there is no data - so throw error
    if($rows_count <= $headerRowCnt || $dataRowCnt == 0) {
      $ret['ErrorCode'] = SP_NO_DATA;
    }

    if(!empty($invalidRows)) {
      $ret['ErrorCode'] = SP_INVALID_ROW;
      $ret['InvalidRows'] = $invalidRows;
    }

    if(!empty($invalidInternalIdRows['not_found']) || !empty($invalidInternalIdRows['duplicated'])) {
      $ret['ErrorCode'] = SP_INVALID_INTERNAL_ID;
      $ret['InvalidInternalIdRows'] = $invalidInternalIdRows;
    }

    $ret['sheetData'] = $cellData;
    $ret['headerRowCnt'] = $headerRowCnt;
    $ret['dataRowCnt'] = $dataRowCnt;

    return $ret;
  }

  function bulk_process_utilizations($objectType, $uploadId) {
    // $objectType: Indicate bulk processing object
    //    IUT: International Utilization Data
    //    UUT: US Utilization Data

    $this->initAspose();

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $objectType = strtoupper($objectType);
      $processedData = $this->centralized_bulk_processor_utilizations($objectType, $uploadId);

      //Once all the spreadsheet data is converted into an array
      $data['tabArray'] = "dmaaccount";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "administrative";

      $data['objectType'] = $objectType;
      $data['uploadId'] = $uploadId;

      $data['processedData'] = $processedData;

      $data['utilizationTypes'] = $this->IncentivePrograms->get_dma_account_field_options_key_val('credit_utilization_type');

      $output = '';

      $twig = \OIX\Util\TemplateProvider::getTwig();
      $template = $twig->load('dmaaccount/bulk_process_utilizations.twig');
      $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
      $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

      $output .= $this->load->view('includes/left_nav', $data, true);
      $output .= $this->load->view('includes/tab_nav', $data, true);
      $output .= $template->render($data);
      $output .= $this->load->view('includes/footer-2', true);
      echo $output;
    }
  }

  function get_credits_utilization_data($type, $columns) {
    /**
     * $type: Indicate worksheet of bulk credits spreadsheet
     *    IPF: International Portfolio Data
     *    UPF: US Portfolio Data
     *    IUT: International Utilization Data
     *    UUT: US Utilization Data
     */

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);

      //Centralized function build filter query parameters
      $type = strtoupper($type);
      if($type === 'IPF' || $type === 'IUT') {
        $data['domesticOrInternational'] = 'international';
      } else if($type === 'UPF' || $type === 'UUT') {
        $data['domesticOrInternational'] = 'domestic';
      }

      $cData = $this->CreditListings->buildFilterSearchData($data);

      //If credit portfolio tabs
      if($type === 'IPF' || $type === 'UPF') {
        $credits = $this->CreditListings->get_credits($cData);
        $data['records'] = $credits['credits'];
        //If utilization tabs
      } else if($type === 'IUT' || $type === 'UUT') {
        $trades = $this->CreditListings->get_utilizations($cData);
        $data['records'] = $trades['trades'];
      }

      $data['recordsSummary'] = $credits['summary'];
      $data['columns'] = $columns;

      //Now that we have data, send it to get built into the reports array
      //If credit portfolio tabs
      if($type === 'IPF' || $type === 'UPF') {
        $data['dataKey'] = "getMyCreditsView";
        //If utilization tabs
      } else if($type === 'IUT' || $type === 'UUT') {
        $data['dataKey'] = "getReportTrades";
      }
      $reportData = $this->CreditListings->buildReportData($data['dataKey'], $data['columns'], $data['records'], $data['order'], $data['view'], $this->cisession->userdata('dmaId'));
      $data['sDataKeyValue'] = $reportData['sDataKeyValue']; //this returns the columns of data
      $data['sDataHeaderKeyValue'] = $reportData['sDataHeaderKeyValue'];
      $data['sDataKeyFormats'] = $reportData['sDataKeyFormats']; //should give you the formats of each column/field, if you need it

      return $data;
    }
  }

  function download_bulk_credit_spreadsheet($includeData = "") {
    /**
     * $includeData:
     *    inc_data: Download the spreadsheet filled with credits and utilization data
     */

    set_time_limit(60);     // Increase execution time limit to 60 seconds for handling mass result set

    $this->initAspose();
    // Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {
      //Get credits data points
      $dpRequest = [];
      $dpRequest['dpObjectType'] = 'credit';
      if(empty($this->cisession->userdata('dmaId'))) {
        $dpRequest['dpDmaId'] = null;
      } else {
        $dpRequest['dpDmaId'] = $this->cisession->userdata('dmaId');
      }

      $creditData = $this->CreditListings->get_data_points($dpRequest);

      $dmaTitle = $this->cisession->userdata('dmaTitle');
      $dmaChildAccounts = $this->DmaAccounts->get_child_accounts_of_dma_account($this->cisession->userdata('dmaId'));

      // Create spreadsheet
      $workbook = new cells\Workbook();
      $sheets = $workbook->getWorksheets();

      // Instructions sheet
      $insSheet = $sheets->get(0);
      $insSheet->setName("Instructions");
      $insSheet->setGridlinesVisible(false);
      $insCells = $insSheet->getCells();

      // Add International Portfolio sheet
      $sheets->add();
      $ipfSheet = $sheets->get(1);
      $ipfSheet->setName("International Portfolio Data");
      $ipfCells = $ipfSheet->getCells();

      // Add US Portfolio sheet
      $sheets->add();
      $upfSheet = $sheets->get(2);
      $upfSheet->setName("US Portfolio Data");
      $upfCells = $upfSheet->getCells();

      // Add International Utilization Data sheet
      $sheets->add();
      $iutSheet = $sheets->get(3);
      $iutSheet->setName("International Utilization Data");
      $iutCells = $iutSheet->getCells();

      // Add US Utilization Data sheet
      $sheets->add();
      $uutSheet = $sheets->get(4);
      $uutSheet->setName("US Utilization Data");
      $uutCells = $uutSheet->getCells();

      // Add Drop Down Data sheet
      $sheets->add();
      $dropdownSheet = $sheets->get(5);
      $dropdownSheet->setName("Drop Down Data");
      $dropdownSheet->setGridlinesVisible(false);
      $dropdownCells = $dropdownSheet->getCells();

      // Add GS Drop Downs sheet
      $sheets->add();
      $gsSheet = $sheets->get(6);
      $gsSheet->setName("GS Drop Downs");
      $gsSheet->setVisible(false);
      $gsCells = $gsSheet->getCells();

      // Merge cells [Instructions]
      $insCells->merge(0, 0, 1, 11);
      $insCells->merge(3, 1, 1, 9);
      $insCells->merge(13, 2, 1, 8);
      $insCells->merge(14, 2, 1, 8);
      $insCells->merge(15, 2, 1, 8);
      $insCells->merge(16, 2, 1, 8);
      $insCells->merge(17, 2, 1, 8);
      $insCells->merge(18, 2, 1, 8);
      $insCells->merge(19, 2, 1, 8);

      // Stylize [Instructions]
      $title_style1 = new cells\Style();
      $title_style1->getFont()->setName("Arial");
      $title_style1->getFont()->setSize(14);
      $title_style1->getFont()->setColor(cells\Color::getWhite());
      $title_style1->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $title_style1->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $title_style1->setForegroundColor(cells\Color::fromArgb(36, 64, 98));
      $title_style1->setPattern(cells\BackgroundType::SOLID);

      $title_style2 = new cells\Style();
      $title_style2->getFont()->setName("Arial");
      $title_style2->getFont()->setSize(12);
      $title_style2->getFont()->setBold(true);
      $title_style2->setVerticalAlignment(cells\TextAlignmentType::TOP);

      $body_style1 = new cells\Style();
      $body_style1->getFont()->setName("Arial");
      $body_style1->getFont()->setSize(10);

      $body_style2 = new cells\Style();
      $body_style2->getFont()->setName("Arial");
      $body_style2->getFont()->setSize(10);
      $body_style2->getFont()->setBold(true);

      $body_style3 = new cells\Style();
      $body_style3->getFont()->setName("Arial");
      $body_style3->getFont()->setSize(10);
      $body_style3->setTextWrapped(true);
      $body_style3->setVerticalAlignment(cells\TextAlignmentType::TOP);

      $body_style4 = new cells\Style();
      $body_style4->getFont()->setName("Arial");
      $body_style4->getFont()->setSize(10);
      $body_style4->getFont()->setBold(true);
      $body_style4->setVerticalAlignment(cells\TextAlignmentType::TOP);

      $insCells->get(0, 0)->setStyle($title_style1);
      $insCells->get(2, 1)->setStyle($title_style2);
      $insCells->get(4, 1)->setStyle($title_style2);
      $insCells->get(12, 1)->setStyle($title_style2);
      $insCells->get(21, 1)->setStyle($title_style2);

      $insCells->get(3, 1)->setStyle($body_style3);

      $insCells->get(5, 1)->setStyle($body_style2);
      $insCells->get(6, 1)->setStyle($body_style2);
      $insCells->get(7, 1)->setStyle($body_style2);
      $insCells->get(8, 1)->setStyle($body_style2);
      $insCells->get(9, 1)->setStyle($body_style2);
      $insCells->get(10, 1)->setStyle($body_style2);
      $insCells->get(5, 3)->setStyle($body_style1);
      $insCells->get(6, 3)->setStyle($body_style1);
      $insCells->get(7, 3)->setStyle($body_style1);
      $insCells->get(8, 3)->setStyle($body_style1);
      $insCells->get(9, 3)->setStyle($body_style1);
      $insCells->get(10, 3)->setStyle($body_style1);

      $insCells->get(13, 1)->setStyle($body_style4);
      $insCells->get(14, 1)->setStyle($body_style4);
      $insCells->get(15, 1)->setStyle($body_style4);
      $insCells->get(16, 1)->setStyle($body_style4);
      $insCells->get(17, 1)->setStyle($body_style4);
      $insCells->get(18, 1)->setStyle($body_style4);
      $insCells->get(19, 1)->setStyle($body_style4);
      $insCells->get(13, 2)->setStyle($body_style3);
      $insCells->get(14, 2)->setStyle($body_style3);
      $insCells->get(15, 2)->setStyle($body_style3);
      $insCells->get(16, 2)->setStyle($body_style3);
      $insCells->get(17, 2)->setStyle($body_style3);
      $insCells->get(18, 2)->setStyle($body_style3);
      $insCells->get(19, 2)->setStyle($body_style3);

      $insCells->get(22, 1)->setStyle($body_style1);

      // Row height / Col width [Instructions]
      $insCells->setRowHeight(0, 39);
      $insCells->setRowHeight(2, 25);
      $insCells->setRowHeight(3, 45);
      $insCells->setRowHeight(4, 15);
      $insCells->setRowHeight(5, 22);
      $insCells->setRowHeight(6, 22);
      $insCells->setRowHeight(7, 22);
      $insCells->setRowHeight(8, 22);
      $insCells->setRowHeight(9, 22);
      $insCells->setRowHeight(10, 22);
      $insCells->setRowHeight(11, 36);
      $insCells->setRowHeight(12, 30);
      $insCells->setRowHeight(13, 39);
      $insCells->setRowHeight(14, 27);
      $insCells->setRowHeight(15, 54);
      $insCells->setRowHeight(16, 90);
      $insCells->setRowHeight(17, 39);
      $insCells->setRowHeight(18, 39);
      $insCells->setRowHeight(19, 48);
      $insCells->setRowHeight(21, 24);

      $insCells->getColumns()->get(0)->setWidth(2.5);
      $insCells->getColumns()->get(1)->setWidth(2.5);
      $insCells->getColumns()->get(2)->setWidth(32);
      $insCells->getColumns()->get(3)->setWidth(14);
      $insCells->getColumns()->get(4)->setWidth(14);
      $insCells->getColumns()->get(5)->setWidth(14);
      $insCells->getColumns()->get(6)->setWidth(14);
      $insCells->getColumns()->get(7)->setWidth(14);
      $insCells->getColumns()->get(8)->setWidth(14);
      $insCells->getColumns()->get(9)->setWidth(14);
      $insCells->getColumns()->get(10)->setWidth(2.5);

      // Sheet data [Instructions]
      $insCells->get(0, 0)->putValue('Instructions');
      $insCells->get(2, 1)->putValue('Welcome!');
      $insCells->get(3, 1)->putValue('This bulk upload template allows you to enter your portfolio information into one spreadsheet so that the OIX can bulk upload it into your OIX account. This spreadsheet is separated to collect (1) United States (US) based incentives and their utilizations and (2) International (non-US) based incentives and their utilizations.');
      $insCells->get(4, 1)->putValue('There are 6 sheets in this workbook:');

      $insCells->get(5, 1)->putValue('Sheet 1 - Instructions');
      $insCells->get(5, 3)->putValue('This help sheet');
      $insCells->get(6, 1)->putValue('Sheet 2 - International Portfolio Data');
      $insCells->get(6, 3)->putValue('Enter your international (non-US) credits and incentives on this sheet');
      $insCells->get(7, 1)->putValue('Sheet 3 - US Portfolio Data');
      $insCells->get(7, 3)->putValue('Enter your US (domestic) credits and incentives on this sheet');
      $insCells->get(8, 1)->putValue('Sheet 4 - International Utilization Data');
      $insCells->get(8, 3)->putValue('Enter your estimated and actual utilizations of your international credits and incentives from Sheet 2');
      $insCells->get(9, 1)->putValue('Sheet 5 - US Utilization Data');
      $insCells->get(9, 3)->putValue('Enter your estimated and actual utilizations of your US credits and incentives from Sheet 3');
      $insCells->get(10, 1)->putValue('Sheet 6 - Drop Down Data');
      $insCells->get(10, 3)->putValue('Customize the drop downs options available on all Sheets');

      $insCells->get(12, 1)->putValue('Getting Started -- Recommended Steps:');
      $insCells->get(13, 1)->putValue(1);
      $insCells->get(13, 2)->putValue('Browse Sheets 2, 3, 4 and 5 to get familiar with the data sets you are entering. For the incentives you enter on Sheet 2 (international) you will enter the utilizations against those incentives on Sheet 4. Similarly, for the incentives you enter on Sheet 3 (US) you will enter the utilizations against those incentives on Sheet 5.');
      $insCells->get(14, 1)->putValue(2);
      $insCells->get(14, 2)->putValue('Examine Sheet 6 (Drop Down Data) to understand which of the drop downs available on the various Sheets are configurable.');
      $insCells->get(15, 1)->putValue(3);
      $insCells->get(15, 2)->putValue('From Sheet 6 you can configure the options available in each drop down. There are instructions at the top of each column to assist you. While customizing these drop downs, please think about your internal incentive lifecycle process and the ways in which you will want to view and filter your portfolio data. These are important decisions that impact your OIX Account data architecture. Please let the OIX know if you have questions or need support.');
      $insCells->get(16, 1)->putValue(4);
      $insCells->get(16, 2)->putValue('Now that you\'ve completed this step (customizing your core status drop downs on Sheet 6) it is time to think about the data points you want to capture that aren\'t already included in this spreadsheet. We call these "Custom Data Points".  You may want to create them to extend the information you want to capture in your incentive portfolio beyond what is already available on Sheets 2, 3, 4 and 5. To create custom data points, please re-visit Sheets 2, 3, 4 and 5. Scroll to the far right side of each sheet and notice that there are already a few placeholder columns available for you to add your own "Custom Data Points". Feel free to add to columns now or at anytime while entering your data. For example, if you could create a column titled "Advisory Firm" and then enter the name of the firm for each row (if applicable). These Custom Data Points will be added to your OIX account!');
      $insCells->get(17, 1)->putValue(5);
      $insCells->get(17, 2)->putValue('Now that your data points are all customized and prepared to your liking - we recommend you start entering your incentive portfolio data into Sheets 2 and 3. There are instructions at the top of each column to assist you.');
      $insCells->get(18, 1)->putValue(6);
      $insCells->get(18, 2)->putValue('After entering the incentive portfolio data on Sheets 2 and 3, you can now click over to Sheet 3 and 4 to enter in any Utilizations related to those incentives you entered into Sheets 2 and 3. ');
      $insCells->get(19, 1)->putValue(7);
      $insCells->get(19, 2)->putValue('When you have completed entering your incentives and utilizations you can now send the spreadsheet over to your OIX Account Executive for processing into your OIX Account. (Note: soon the OIX will provide you with access directly to the bulk uploader tool within your OIX Account - for now, our staff will handle this for you).');

      $insCells->get(21, 1)->putValue('Support');
      $insCells->get(22, 1)->putValue('Please contact your OIX Account Executive for any questions. Or, email general support at support@theoix.com');

      // Table header [Drop Down Data]
      $dropdownCells->get(0, 0)->putValue('Drop Down Data');
      $dropdownCells->get(1, 0)->putValue('Please do not edit anything within the red area');

      $ddColNum = 0;

      // Get the tax year list
      $taxYearList = $this->IncentivePrograms->get_all_tax_years();
      $taxYearCnt = count($taxYearList);
      if($taxYearCnt) {
        // Create a range for tax years in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
        $dropdownCells->get(3, $ddColNum)->putValue('First Usable Tax Year');
        $range = $dropdownCells->createRange(4, $ddColNum, $taxYearCnt, 1);
        $range->setName("TaxYearRange");

        foreach($taxYearList as $i => $ty) {
          $range->get($i, 0)->setValue((int)$ty->taxYear);
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Create a range for credit amount status
      $creditAmountStatusList = ['Estimate', 'Actual'];
      $creditAmountStatusCnt = count($creditAmountStatusList);
      $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
      $dropdownCells->get(3, $ddColNum)->putValue('Credit Amount Status (est or act)');
      $range = $dropdownCells->createRange(4, $ddColNum, $creditAmountStatusCnt, 1);
      $range->setName("CreditAmountStatusRange");

      foreach($creditAmountStatusList as $i => $val) {
        $range->get($i, 0)->setValue($val);
      }
      $ddColNum++;
      $ddColNum++;

      // Create a range for utilization status
      $utilizationStatusList = ['Estimate', 'Actual'];
      $utilizationStatusCnt = count($utilizationStatusList);
      $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
      $dropdownCells->get(3, $ddColNum)->putValue('Utilization Status (Est or Act)');
      $range = $dropdownCells->createRange(4, $ddColNum, $utilizationStatusCnt, 1);
      $range->setName("UtilizationStatusRange");

      foreach($utilizationStatusList as $i => $val) {
        $range->get($i, 0)->setValue($val);
      }
      $ddColNum++;
      $ddColNum++;

      // Create a range for carry forward years in the data worksheet.
      $carryForwardYearList = range(1, 50);
      $carryForwardYearCnt = count($carryForwardYearList);
      $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
      $dropdownCells->get(3, $ddColNum)->putValue('Carry Forward Years');
      $range = $dropdownCells->createRange(4, $ddColNum, $carryForwardYearCnt, 1);
      $range->setName("CarryForwardYearRange");

      foreach($carryForwardYearList as $i => $val) {
        $range->get($i, 0)->setValue($val);
      }
      $ddColNum++;
      $ddColNum++;

      // Create a range for legislative framework in the data worksheet.
      $legisFrameworkList = ['Statutory', 'Discretionary', 'Negotiated'];
      $legisFrameworkCnt = count($legisFrameworkList);
      $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
      $dropdownCells->get(3, $ddColNum)->putValue('Legislative Framework');
      $range = $dropdownCells->createRange(4, $ddColNum, $legisFrameworkCnt, 1);
      $range->setName("LegisFrameworkRange");

      foreach($legisFrameworkList as $i => $val) {
        $range->get($i, 0)->setValue($val);
      }
      $ddColNum++;
      $ddColNum++;

      // Create a range for Section in Form to Include CDP in the data worksheet.
      $sectionCdpList = ['Additional Details', 'Credit Amount Details', 'Date Settings', 'Incentive Program Details', 'Project Details', 'Status Settings'];
      $sectionCdpListCnt = count($sectionCdpList);
      $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
      $dropdownCells->get(3, $ddColNum)->putValue('Section in Form to Include CDP');
      $range = $dropdownCells->createRange(4, $ddColNum, $sectionCdpListCnt, 1);
      $range->setName("SectionCdpRange");

      foreach($sectionCdpList as $i => $val) {
        $range->get($i, 0)->setValue($val);
      }
      $ddColNum++;
      $ddColNum++;

      // Create a range for Type of CDP in the data worksheet.
      $typeCdpList = ['Text/Number', 'Date', 'Currency', 'Notes', 'Drop-down'];
      $typeCdpListCnt = count($typeCdpList);
      $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
      $dropdownCells->get(3, $ddColNum)->putValue('Type of CDP');
      $range = $dropdownCells->createRange(4, $ddColNum, $typeCdpListCnt, 1);
      $range->setName("TypeCdpRange");

      foreach($typeCdpList as $i => $val) {
        $range->get($i, 0)->setValue($val);
      }
      $ddColNum++;
      $ddColNum++;

      // Create a range for child accounts for Business Unit column in data worksheet
      if(!empty($dmaChildAccounts)) {
        $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
        $dropdownCells->get(3, $ddColNum)->putValue('Business Unit');
        $range = $dropdownCells->createRange(4, $ddColNum, count($dmaChildAccounts) + 1, 1);
        $range->setName("BusinessUnitRange");

        $range->get(0, 0)->setValue($dmaTitle);
        foreach($dmaChildAccounts as $i => $da) {
          $range->get($i + 1, 0)->setValue($da['title']);
        }
        $ddColNum++;
        $ddColNum++;
      }

      $ddEditableColIdx = $ddColNum;
      $dropdownCells->get(1, $ddEditableColIdx)->putValue('You can customize these drop-down menu items/values (Columns O-AA).  Simply change/modify labels and orders as needed, and we can bulk-modify');

      // Get the Certification status
      $certificationStatusList = $this->IncentivePrograms->get_dma_account_field_options_key_val('certification_status');
      $certificationStatusCnt = count($certificationStatusList);

      if($certificationStatusCnt) {
        // Create a range for certification status in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Edit, re-order, remove or add to');
        $dropdownCells->get(3, $ddColNum)->putValue('Certification Status');
        $range = $dropdownCells->createRange(4, $ddColNum, $certificationStatusCnt, 1);
        $range->setName("CertificationStatusRange");

        $i = 0;
        foreach($certificationStatusList as $cs) {
          $range->get($i, 0)->setValue($cs);
          $i++;
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Get the Audit status
      $auditStatusList = $this->IncentivePrograms->get_dma_account_field_options_key_val('audit_status');
      $auditStatusCnt = count($auditStatusList);

      if($auditStatusCnt) {
        // Create a range for audit status in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Edit, re-order, remove or add to');
        $dropdownCells->get(3, $ddColNum)->putValue('Audit Status');
        $range = $dropdownCells->createRange(4, $ddColNum, $auditStatusCnt, 1);
        $range->setName("AuditStatusRange");

        $i = 0;
        foreach($auditStatusList as $as) {
          $range->get($i, 0)->setValue($as);
          $i++;
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Get the Monetization status
      $monetizationStatusList = $this->IncentivePrograms->get_dma_account_field_options_key_val('monetization_status');
      $monetizationStatusCnt = count($monetizationStatusList);

      if($monetizationStatusCnt) {
        // Create a range for monetization status in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Edit, re-order, remove or add to');
        $dropdownCells->get(3, $ddColNum)->putValue('Monetization Status');
        $range = $dropdownCells->createRange(4, $ddColNum, $monetizationStatusCnt, 1);
        $range->setName("MonetizationStatusRange");

        $i = 0;
        foreach($monetizationStatusList as $ms) {
          $range->get($i, 0)->setValue($ms);
          $i++;
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Get the Project status
      $projectStatusList = $this->IncentivePrograms->get_dma_account_field_options_key_val('project_status');
      $projectStatusCnt = count($projectStatusList);

      if($projectStatusCnt) {
        // Create a range for Project Status in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Edit, re-order, remove or add to');
        $dropdownCells->get(3, $ddColNum)->putValue('Project Status');
        $range = $dropdownCells->createRange(4, $ddColNum, $projectStatusCnt, 1);
        $range->setName("ProjectStatusRange");

        $i = 0;
        foreach($projectStatusList as $ps) {
          $range->get($i, 0)->setValue($ps);
          $i++;
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Get the Program types
      $programTypesList = $this->IncentivePrograms->get_dma_account_field_options_key_val('program_type');
      $programTypesCnt = count($programTypesList);

      if($programTypesCnt) {
        // Create a range for program types in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Edit, re-order, remove or add to');
        $dropdownCells->get(3, $ddColNum)->putValue('Credit Types');
        $range = $dropdownCells->createRange(4, $ddColNum, $programTypesCnt, 1);
        $range->setName("ProgramTypesRange");

        $i = 0;
        foreach($programTypesList as $pt) {
          $range->get($i, 0)->setValue($pt);
          $i++;
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Get the currency list
      $currencyList = $this->currency->get_currency_list();
      $currencyCnt = count($currencyList);
      if($currencyCnt) {
        // Create a range for currency list in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Add to (use 3 character code)');
        $dropdownCells->get(3, $ddColNum)->putValue('Currency');
        $range = $dropdownCells->createRange(4, $ddColNum, $currencyCnt, 1);
        $range->setName("CurrencyRange");

        foreach($currencyList as $i => $curr) {
          $range->get($i, 0)->setValue($curr['code'] . ' - ' . $curr['name']);
        }
        $ddColNum++;
        $ddColNum++;
      }

      // Create a range for Utilization types
      $utilizationTypesList = $this->IncentivePrograms->get_dma_account_field_options_key_val('credit_utilization_type');
      $utilizationTypesCnt = count($utilizationTypesList);

      if($utilizationTypesCnt) {
        // Create a range for utilization types in the data worksheet.
        $dropdownCells->get(2, $ddColNum)->putValue('Edit, re-order, remove or add to');
        $dropdownCells->get(3, $ddColNum)->putValue('Utilization Type');
        $range = $dropdownCells->createRange(4, $ddColNum, $utilizationTypesCnt, 1);
        $range->setName("UtilizationTypesRange");

        $i = 0;
        foreach($utilizationTypesList as $ut) {
          $range->get($i, 0)->setValue($ut);
          $i++;
        }
        $ddColNum++;
        $ddColNum++;
      }

      $ddCdpColIdx = $ddColNum;

      // Header contents [International Portfolio Data]
      $ipfCells->get(0, 0)->putValue('International Portfolio Data');

      $ipfCells->get(1, 0)->putValue('NOTES & GUIDANCE (expand for detail)');
      $ipfCells->get(1, 1)->putValue('Required only if you have the "Linked Accounts" feature to designate which OIX Account/Business Unit this credit should be allocated to.  Type in the name of the Business Unit (or Instance) per your account configuration.');
      $ipfCells->get(1, 2)->putValue('If you already have a unique ID for each individual credit,please enter it here.  "Internal Credit IDs" are required if you plan to bulk upload Utilizations, HOWEVER, if you prefer The OIX can first bulk upload your portfolio data, and then provide YOU with the OIX ID # in order to assign "utilizations" to specific projects.  Note: an Internal ID cannot be used more than once if you are recording utilizations on the "Utilization Data" Tab.');
      $ipfCells->get(1, 3)->putValue('Project/location name.');
      $ipfCells->get(1, 4)->putValue('e.g. any additional designation you would like to assign.');
      $ipfCells->get(1, 5)->putValue('Type name of country');
      $ipfCells->get(1, 6)->putValue('Select State or Federal in the drop-down menu.  (For US FEDERAL credits please select "Federal" in the alphabetized menu under "F".)');
      $ipfCells->get(1, 7)->putValue('Please type the name of the incentive program here.');
      $ipfCells->get(1, 8)->putValue('If applicable, please enter the city here.');
      $ipfCells->get(1, 9)->putValue('If applicable, please enter the county here.');
      $ipfCells->get(1, 10)->putValue('"SPV" or "Entity" who is the recipient of the incentive.  Type in the name of the Legal Entity.');
      $ipfCells->get(1, 11)->putValue('Amount in local currency (USD will be calculated in subsequent columns to the right)');
      $ipfCells->get(1, 12)->putValue('Only required if non-US production.');
      $ipfCells->get(1, 13)->putValue('Enter exchange rate from local currency to USD (the next column will multiply this value by your Estimated Credit Amount Local Currency to show the amount in USD). Format is up to 12 decimal places.');
      $ipfCells->get(1, 14)->putValue('For example, if your incentive is estimated to have a face value of one million US Dollars, then enter $1,000,000.00');
      $ipfCells->get(1, 15)->putValue('(Decimal Format) If receiving full value of the incentive, then set this to 1.00 (i.e.; 100%). If estimating the future sale (i.e.; a transferable credit) then set to your best estimated percent return on the dollar (i.e. 0.91). Format is up to 12 decimal places. If you\'ve already monetized this credit, then enter your initial estimate (or average of the actual monetization) and the system will compare that to the actual return on each monetization you enter on the "Utilization Data" Tab).');
      $ipfCells->get(1, 16)->putValue('Year in which credit can first be applied against liabilities (i.e. year of certification).  This is most valuable when you enter the Carry Forward period so that the system can calculate the credit expiration date.');
      $ipfCells->get(1, 17)->putValue('Enter the carry forward period so that the system can calculate and provide the credit expiration date.');
      $ipfCells->get(1, 18)->putValue('Statutory, Discretionary or Negotiated.');
      $ipfCells->get(1, 19)->putValue('Designate "estimated" or "acutal" credit amount.');
      $ipfCells->get(1, 20)->putValue('Type of government assistance. This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $ipfCells->get(1, 21)->putValue('"OPEN"=not yet monetized, "AWAITING"=another option to designate \'not started yet\', "IN PROCESS"=some or all portion is in the monetization process, "CLOSED"=monetization complete (credit exhausted). This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $ipfCells->get(1, 22)->putValue('This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $ipfCells->get(1, 23)->putValue('This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $ipfCells->get(1, 24)->putValue('This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $ipfCells->get(1, 25)->putValue('Free-form notes field');
      $ipfCells->get(1, 26)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 27)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 28)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 29)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 30)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 31)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 32)->putValue('Optional Date fields.  Highly encouraged.');
      $ipfCells->get(1, 33)->putValue('You can enter your poject budget here.');
      $ipfCells->get(1, 34)->putValue('You can enter your estimate on Qualified Expenditures here.');
      $ipfCells->get(1, 35)->putValue('Enter here the percentage incentive based on qualified expenditures (e.g. 20%, 30%, etc.).');
      $ipfCells->get(1, 36)->putValue('This is a "tag name" for the type of work being performed for this project.');
      $ipfCells->get(1, 37)->putValue('Free-from notes field');

      $ipfColumns = ['fieldListingId', 'fieldSharedAccount', 'fieldInternalId', 'fieldProjectName', 'fieldProjectNameExt',
                     'fieldCountry', 'fieldProvinceName', 'fieldCountyName', 'fieldTownName', 'fieldIncentiveProgramCustom',
                     'fieldSellerTaxEntity', 'fieldAmountLocal', 'fieldLocalCurrency', 'fieldBudgetExchangeRate', 'fieldAmountUSD',
                     'fieldEstCreditPrice', 'fieldFirstTaxYear', 'fieldCarryForwardYears', 'fieldLegislativeFramework', 'fieldCreditAmountStatus',
                     'fieldCreditType', 'fieldStatusMonetization', 'fieldCertStatus', 'fieldProjectStatus', 'fieldAuditStatus',
                     'fieldStatusNotes', 'fieldProjectStartDate', 'fieldProjectEndDate', 'fieldAuditStartDate', 'fieldAuditEndDate',
                     'fieldEstInitialCertDate', 'fieldEstFinalCertDate', 'fieldIssueDate', 'fieldProjectBudgetEst', 'fieldQualifiedExpenditures',
                     'fieldIncentiveRate', 'fieldTypeOfWork', 'fieldGeneralNotes'];
      foreach($ipfColumns as $i => $fieldKey) {
        $ipfCells->get(2, $i)->putValue($fieldKey);
      }

      $ipfCells->get(3, 0)->putValue('Please do not edit or delete anything within the blue areas (Rows 1-7 and Columns 1-2)   ------------   Note symbols designating * required fields, or ** possibly required fields, pending on account conifguration (see notes at the top of each column for detail)');

      $ipfCells->get(4, 0)->putValue('CORE CREDIT & INCENTIVE INFORMATION ------>');
      $ipfCells->get(4, 16)->putValue('OPTIONAL AND ADDITONAL CREDIT & INCENTIVE INFORMATION ------>');
      $ipfCells->get(4, 21)->putValue('Status Fields');
      $ipfCells->get(4, 26)->putValue('Enter date fields as (mm/dd/yyyy)');

      $ipfCells->get(5, 0)->putValue('OIX Credit ID');
      $ipfCells->get(5, 1)->putValue('Business Unit');
      $ipfCells->get(5, 2)->putValue('Internal Credit ID # **');
      $ipfCells->get(5, 3)->putValue('Name of Certified Project');
      $ipfCells->get(5, 4)->putValue('Project Extension Name');
      $ipfCells->get(5, 5)->putValue('Country *');
      $ipfCells->get(5, 6)->putValue('State or Region **');
      $ipfCells->get(5, 7)->putValue('County');
      $ipfCells->get(5, 8)->putValue('City');
      $ipfCells->get(5, 9)->putValue('Custom Incentive Program Name *');
      $ipfCells->get(5, 10)->putValue('Legal Entity Name *');
      $ipfCells->get(5, 11)->putValue('Estimated Credit Amount * (Face Value in Local Currency)');
      $ipfCells->get(5, 12)->putValue('Local Currency *');
      $ipfCells->get(5, 13)->putValue('Budgeted Exchange Rate to USD *');
      $ipfCells->get(5, 14)->putValue('Estimated Credit Amount *** (Face Value in $USD)');
      $ipfCells->get(5, 15)->putValue('Estimated Credit Value * (in % terms expressed as decimal)');
      $ipfCells->get(5, 16)->putValue('First Usable Tax Year');
      $ipfCells->get(5, 17)->putValue('Carry Forward (# of Years)');
      $ipfCells->get(5, 18)->putValue('Legislative Framework');
      $ipfCells->get(5, 19)->putValue('Status of Credit Amount');
      $ipfCells->get(5, 20)->putValue('Credit Type');
      $ipfCells->get(5, 21)->putValue('Monetization Status');
      $ipfCells->get(5, 22)->putValue('Certification Status');
      $ipfCells->get(5, 23)->putValue('Project Status');
      $ipfCells->get(5, 24)->putValue('Audit Status');
      $ipfCells->get(5, 25)->putValue('Status Notes');
      $ipfCells->get(5, 26)->putValue('Project Start Date');
      $ipfCells->get(5, 27)->putValue('Project End Date');
      $ipfCells->get(5, 28)->putValue('Audit Start Date');
      $ipfCells->get(5, 29)->putValue('Audit End Date');
      $ipfCells->get(5, 30)->putValue('Initial Certification Date');
      $ipfCells->get(5, 31)->putValue('Final Certification Date (Estimated)');
      $ipfCells->get(5, 32)->putValue('Actual Credit Issue Date');
      $ipfCells->get(5, 33)->putValue('Project Budget (Est.)');
      $ipfCells->get(5, 34)->putValue('Qualified Expenditures');
      $ipfCells->get(5, 35)->putValue('Rate of Incentive (%)');
      $ipfCells->get(5, 36)->putValue('Type of Work');
      $ipfCells->get(5, 37)->putValue('General Notes');

      $headerRows = 6;
      $applyRows = 1000;
      $fixedCols = 38;
      $customCols = 0;

      // Adjust row height/column width [US Portfolio Data]
      $ipfCells->setRowHeight(0, 43);
      $ipfCells->setRowHeight(1, 100);
      $ipfCells->setRowHeight(2, 24);
      $ipfCells->setRowHeight(3, 30);
      $ipfCells->setRowHeight(4, 36);
      $ipfCells->setRowHeight(5, 36);

      $ipfCells->getColumns()->get(0)->setWidth(28);
      $ipfCells->getColumns()->get(1)->setWidth(37);
      $ipfCells->getColumns()->get(2)->setWidth(27);
      $ipfCells->getColumns()->get(3)->setWidth(40);
      $ipfCells->getColumns()->get(4)->setWidth(28);
      $ipfCells->getColumns()->get(5)->setWidth(32);
      $ipfCells->getColumns()->get(6)->setWidth(32);
      $ipfCells->getColumns()->get(7)->setWidth(19);
      $ipfCells->getColumns()->get(8)->setWidth(19);
      $ipfCells->getColumns()->get(9)->setWidth(51);
      $ipfCells->getColumns()->get(10)->setWidth(33);
      $ipfCells->getColumns()->get(11)->setWidth(41);
      $ipfCells->getColumns()->get(12)->setWidth(24);
      $ipfCells->getColumns()->get(13)->setWidth(32);
      $ipfCells->getColumns()->get(14)->setWidth(28);
      $ipfCells->getColumns()->get(15)->setWidth(37);
      $ipfCells->getColumns()->get(16)->setWidth(23);
      $ipfCells->getColumns()->get(17)->setWidth(24);
      $ipfCells->getColumns()->get(18)->setWidth(24);
      $ipfCells->getColumns()->get(19)->setWidth(24);
      $ipfCells->getColumns()->get(20)->setWidth(33);
      $ipfCells->getColumns()->get(21)->setWidth(34);
      $ipfCells->getColumns()->get(22)->setWidth(37);
      $ipfCells->getColumns()->get(23)->setWidth(27);
      $ipfCells->getColumns()->get(24)->setWidth(29);
      $ipfCells->getColumns()->get(25)->setWidth(35);
      $ipfCells->getColumns()->get(26)->setWidth(18);
      $ipfCells->getColumns()->get(27)->setWidth(18);
      $ipfCells->getColumns()->get(28)->setWidth(18);
      $ipfCells->getColumns()->get(29)->setWidth(18);
      $ipfCells->getColumns()->get(30)->setWidth(24);
      $ipfCells->getColumns()->get(31)->setWidth(24);
      $ipfCells->getColumns()->get(32)->setWidth(23);
      $ipfCells->getColumns()->get(33)->setWidth(25);
      $ipfCells->getColumns()->get(34)->setWidth(25);
      $ipfCells->getColumns()->get(35)->setWidth(25);
      $ipfCells->getColumns()->get(36)->setWidth(25);
      $ipfCells->getColumns()->get(37)->setWidth(38);

      // Styling [International Portfolio Data]
      $pf_header_style1 = new cells\Style();
      $pf_header_style1->getFont()->setSize(18);
      $pf_header_style1->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style1->getFont()->setBold(true);
      $pf_header_style1->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_header_style1->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style1->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $pf_header_style1->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());

      $pf_header_style2 = new cells\Style();
      $pf_header_style2->getFont()->setSize(12);
      $pf_header_style2->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style2->getFont()->setBold(true);
      $pf_header_style2->getFont()->setItalic(true);
      $pf_header_style2->setTextWrapped(true);
      $pf_header_style2->setForegroundColor(cells\Color::fromArgb(21, 59, 105));
      $pf_header_style2->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style2->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $pf_header_style2->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $pf_header_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style2->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style2->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $pf_header_style3 = new cells\Style();
      $pf_header_style3->getFont()->setSize(12);
      $pf_header_style3->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style3->getFont()->setItalic(true);
      $pf_header_style3->setTextWrapped(true);
      $pf_header_style3->setForegroundColor(cells\Color::fromArgb(21, 59, 105));
      $pf_header_style3->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style3->setVerticalAlignment(cells\TextAlignmentType::TOP);
      $pf_header_style3->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style3->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style3->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style3->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $pf_header_style4 = new cells\Style();
      $pf_header_style4->getFont()->setSize(10);
      $pf_header_style4->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style4->getFont()->setItalic(true);
      $pf_header_style4->setForegroundColor(cells\Color::fromArgb(21, 59, 105));
      $pf_header_style4->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style4->setVerticalAlignment(cells\TextAlignmentType::CENTER);

      $pf_header_style5 = new cells\Style();
      $pf_header_style5->getFont()->setSize(12);
      $pf_header_style5->getFont()->setColor(cells\Color::getRed());
      $pf_header_style5->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_header_style5->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style5->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $pf_header_style5->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style5->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $pf_header_style6 = new cells\Style();
      $pf_header_style6->getFont()->setSize(14);
      $pf_header_style6->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style6->getFont()->setBold(true);
      $pf_header_style6->getFont()->setItalic(true);
      $pf_header_style6->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_header_style6->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style6->setVerticalAlignment(cells\TextAlignmentType::CENTER);

      $pf_header_style7 = new cells\Style();
      $pf_header_style7->getFont()->setSize(10);
      $pf_header_style7->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style7->getFont()->setBold(true);
      $pf_header_style7->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_header_style7->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style7->setVerticalAlignment(cells\TextAlignmentType::CENTER);

      $pf_header_style8 = new cells\Style();
      $pf_header_style8->getFont()->setSize(11);
      $pf_header_style8->getFont()->setColor(cells\Color::getWhite());
      $pf_header_style8->getFont()->setBold(true);
      $pf_header_style8->setTextWrapped(true);
      $pf_header_style8->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_header_style8->setPattern(cells\BackgroundType::SOLID);
      $pf_header_style8->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $pf_header_style8->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style8->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style8->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $pf_header_style8->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $pf_body_style1 = new cells\Style();
      $pf_body_style1->getFont()->setColor(cells\Color::getWhite());
      $pf_body_style1->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_body_style1->setPattern(cells\BackgroundType::SOLID);

      $pf_body_style2 = new cells\Style();
      $pf_body_style2->getFont()->setItalic(true);
      $pf_body_style2->setForegroundColor(cells\Color::fromArgb(217, 217, 217));
      $pf_body_style2->setPattern(cells\BackgroundType::SOLID);
      $pf_body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
      $pf_body_style2->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
      $pf_body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
      $pf_body_style2->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());

      $pf_body_style3 = new cells\Style();
      $pf_body_style3->getFont()->setColor(cells\Color::getWhite());
      $pf_body_style3->getFont()->setItalic(true);
      $pf_body_style3->setForegroundColor(cells\Color::fromArgb(15, 36, 62));
      $pf_body_style3->setPattern(cells\BackgroundType::SOLID);

      for($i = 0; $i < $applyRows; $i++) {
        for($j = 0; $j < $fixedCols; $j++) {
          if($i == 0) {
            $ipfCells->get($i, $j)->setStyle($pf_header_style1);
          } else if($i == 1) {
            if($j == 0) {
              $ipfCells->get($i, $j)->setStyle($pf_header_style2);
            } else {
              $ipfCells->get($i, $j)->setStyle($pf_header_style3);
            }
          } else if($i == 2) {
            $ipfCells->get($i, $j)->setStyle($pf_header_style4);
          } else if($i == 3) {
            $ipfCells->get($i, $j)->setStyle($pf_header_style5);
          } else if($i == 4) {
            if($j <= 20) {
              $ipfCells->get($i, $j)->setStyle($pf_header_style6);
            } else if($j >= 21 && $j <= 25) {
              $ipfCells->get($i, $j)->setStyle($pf_header_style7);
            } else if($j >= 26 && $j <= 37) {
              $ipfCells->get($i, $j)->setStyle($pf_header_style5);
            }
          } else if($i == 5) {
            $ipfCells->get($i, $j)->setStyle($pf_header_style8);
          } else {
            if($j == 0) {
              $ipfCells->get($i, $j)->setStyle($pf_body_style1);
            } else if($j == 1) {
              if(!empty($dmaChildAccounts)) {
                $ipfCells->get($i, $j)->setStyle($pf_body_style2);
                $ipfCells->get($i, $j)->putValue('Select Value');
              } else {
                $ipfCells->get($i, $j)->setStyle($pf_body_style1);
                $ipfCells->get($i, $j)->putValue($dmaTitle);
              }
            } else if($j == 11) {
              $number_style = $ipfCells->get($i, $j)->getStyle();
              $number_style->setNumber(39);
              $ipfCells->get($i, $j)->setStyle($number_style);
            } else if($j == 12) {
              $ipfCells->get($i, $j)->setStyle($pf_body_style2);
              $ipfCells->get($i, $j)->putValue('Select Value');
            } else if($j == 13) {
              $number_style = $ipfCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $ipfCells->get($i, $j)->setStyle($number_style);
            } else if($j == 14) {
              $ipfCells->get($i, $j)->setStyle($pf_body_style3);
              $accounting_style = $ipfCells->get($i, $j)->getStyle();
              $accounting_style->setNumber(43);
              $ipfCells->get($i, $j)->setStyle($accounting_style);
              $ipfCells->get($i, $j)->setFormula('=INDIRECT(ADDRESS(ROW(), COLUMN()-3))*INDIRECT(ADDRESS(ROW(), COLUMN()-1))');
            } else if($j == 15) {
              $number_style = $ipfCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $ipfCells->get($i, $j)->setStyle($number_style);
            } else if($j >= 16 && $j <= 24) {
              $ipfCells->get($i, $j)->setStyle($pf_body_style2);
              $ipfCells->get($i, $j)->putValue('Select Value');
            } else if($j >= 26 && $j <= 32) {
              $date_style = $ipfCells->get($i, $j)->getStyle();
              $date_style->setCustom('mm/dd/yyyy');
              $ipfCells->get($i, $j)->setStyle($date_style);
            } else if($j == 33 || $j == 34) {
              $number_style = $ipfCells->get($i, $j)->getStyle();
              $number_style->setNumber(37);
              $ipfCells->get($i, $j)->setStyle($number_style);
            } else if($j == 35) {
              $number_style = $ipfCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $ipfCells->get($i, $j)->setStyle($number_style);
            }
          }
        }
      }

      // Create drop downs [International Portfolio Data]
      $ipf_validations = $ipfSheet->getValidations();

      // Add Business Unit drop down if child accounts exist
      if(!empty($dmaChildAccounts)) {
        $cellArea = new cells\CellArea();
        $cellArea->StartRow = $headerRows;
        $cellArea->StartColumn = 1;
        $cellArea->EndRow = $applyRows;
        $cellArea->EndColumn = 1;

        $tmp_idx = $ipf_validations->add($cellArea);
        $validation = $ipf_validations->get($tmp_idx);

        $validation->setType(cells\ValidationType::LIST);
        $validation->setInCellDropDown(true);
        $validation->setFormula1("=BusinessUnitRange");
        $validation->setShowError(true);
        $validation->setAlertStyle(cells\ValidationAlertType::STOP);
        $validation->setErrorTitle("Error");
        $validation->setErrorMessage("Please select account from the list");
      }

      // Currency drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 12;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 12;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CurrencyRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a currency from the list");

      // First Usable Tax Year drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 16;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 16;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=TaxYearRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select the first usable tax year from the list");

      // Carry Forward (# of Years) drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 17;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 17;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CarryForwardYearRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select the number of years from the list");

      // Legislative Framework drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 18;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 18;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=LegisFrameworkRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a legislative framework from the list");

      // Status of Credit Amount drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 19;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 19;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CreditAmountStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a credit amount status from the list");

      // Credit Type drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 20;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 20;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=ProgramTypesRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a credit type from the list");

      // Monetization Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 21;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 21;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=MonetizationStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a monetization status from the list");

      // Certification Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 22;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 22;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CertificationStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a certification status from the list");

      // Project Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 23;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 23;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=ProjectStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a project status from the list");

      // Audit Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 24;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 24;

      $tmp_idx = $ipf_validations->add($cellArea);
      $validation = $ipf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=AuditStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select an audit status from the list");

      // Add columns for custom data points [International Portfolio Data]
      foreach($creditData['dataPoints'] as $j => $dp) {
        // If it's custom field, ...
        if(strpos($dp['dpKey'], 'fieldCustom_') === 0) {
          array_push($ipfColumns, $dp['dpKey']);
          $customCols++;

          // If it's first custom field, ...
          if($customCols === 1) {
            $ipfCells->get(1, $fixedCols)->putValue('If you want to add a custom data point not included by default the OIX System, simply (1) enter the "name" of the CDP, (2) designate the "type" of CDP by selecting from the "CDP  Type" drop down, and (3) and then use the drop-down selector in Row 10 to designate where on the Load/Edit Credit Form you would like to place the CDP.');
            $ipfCells->get(4, $fixedCols)->putValue('CUSTOM DATA POINTS ------>');
          }

          $tmpColIdx = $fixedCols + $customCols - 1;
          $ipfCells->get(2, $tmpColIdx)->putValue($dp['dpKey']);
          $ipfCells->get(5, $tmpColIdx)->putValue($dp['dpNameFull']);

          $ipfCells->getColumns()->get($tmpColIdx)->setWidth(28);

          $ipfCells->get(0, $tmpColIdx)->setStyle($pf_header_style1);
          $ipfCells->get(1, $tmpColIdx)->setStyle($pf_header_style3);
          $ipfCells->get(2, $tmpColIdx)->setStyle($pf_header_style4);
          $ipfCells->get(3, $tmpColIdx)->setStyle($pf_header_style5);
          $ipfCells->get(4, $tmpColIdx)->setStyle($pf_header_style6);
          $ipfCells->get(5, $tmpColIdx)->setStyle($pf_header_style8);

          if($dp['dpType'] === 'date') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $date_style = $ipfCells->get($i, $tmpColIdx)->getStyle();
              $date_style->setCustom('mm/dd/yyyy');
              $ipfCells->get($i, $tmpColIdx)->setStyle($date_style);
            }
          } else if($dp['dpType'] === 'currencyNoDecimal') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $number_style = $ipfCells->get($i, $tmpColIdx)->getStyle();
              $number_style->setNumber(37);
              $ipfCells->get($i, $tmpColIdx)->setStyle($number_style);
            }
          } else if($dp['dpType'] === 'currencyTwoDecimal') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $number_style = $ipfCells->get($i, $tmpColIdx)->getStyle();
              $number_style->setNumber(39);
              $ipfCells->get($i, $tmpColIdx)->setStyle($number_style);
            }
          } else if($dp['dpType'] === 'currencyFourDecimal') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $number_style = $ipfCells->get($i, $tmpColIdx)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $ipfCells->get($i, $tmpColIdx)->setStyle($number_style);
            }
          } // If field is drop down, ...
          else if($dp['dpType'] === 'selectDropDown') {
            $dropdownCells->get(1, $ddCdpColIdx)->putValue('Use this section to create Custom Data Point Drop Down Menus.  Use the CDP "Name" that you used on the Portfoloio Data tab and enter the values below.  If you would like to add more CDP Drop-downs please simply copy and paste the rows to add additional.');

            // Create a range for the drop down for the custom data point in the data worksheet
            $optionCnt = count($dp['dpOptions']);
            $dropdownCells->get(2, $ddColNum)->putValue('FIXED - do not edit');
            $dropdownCells->get(3, $ddColNum)->putValue($dp['dpNameFull']);
            $range = $dropdownCells->createRange(4, $ddColNum, $optionCnt, 1);
            $range->setName($dp['dpKey'] . '_Range');

            foreach($dp['dpOptions'] as $i => $opt) {
              $range->get($i, 0)->setValue(htmlentities(urldecode($opt['label'])));
            }
            $ddColNum++;
            $ddColNum++;

            $cellArea = new cells\CellArea();
            $cellArea->StartRow = $headerRows;
            $cellArea->StartColumn = $tmpColIdx;
            $cellArea->EndRow = $applyRows;
            $cellArea->EndColumn = $tmpColIdx;

            $tmp_idx = $ipf_validations->add($cellArea);
            $validation = $ipf_validations->get($tmp_idx);

            $validation->setType(cells\ValidationType::LIST);
            $validation->setInCellDropDown(true);
            $validation->setFormula1("=" . $dp['dpKey'] . "_Range");
            $validation->setShowError(true);
            $validation->setAlertStyle(cells\ValidationAlertType::STOP);
            $validation->setErrorTitle("Error");
            $validation->setErrorMessage("Please select an option from the list");

            // Stylize drop down
            for($i = $headerRows; $i < $applyRows; $i++) {
              $ipfCells->get($i, $tmpColIdx)->setStyle($pf_body_style2);
              $ipfCells->get($i, $tmpColIdx)->putValue('Select Value');
            }
          }
        }
      }

      if(strtoupper($includeData) === "INC_DATA") {
        // Remove fieldIncentiveProgramCustom for temporarily to grab credits data
        $ipfColumns_tmp = $ipfColumns;
        if(($key = array_search('fieldIncentiveProgramCustom', $ipfColumns_tmp)) !== false) {
          unset($ipfColumns_tmp[$key]);
        }

        // Grab the international credits data and put them into the sheet
        $ipfData = $this->get_credits_utilization_data('IPF', $ipfColumns_tmp);

        $rowIndex = $headerRows;
        foreach($ipfData['sDataKeyValue'] as $row) {
          $colIndex = 0;
          foreach($ipfColumns as $fieldKey) {
            $fieldVal = $row[$fieldKey];

            if($fieldKey === 0) { //skip the first item in each row as that's a raw data dump for reference (not needed here)
              continue;
            } else if($fieldKey === 'fieldAmountUSD') {   // No need to put the value. calculated automatically.
              $colIndex++;
              continue;
            } else if($fieldKey === 'fieldIncentiveProgramCustom') {   // Set the Program Name in first item as Custom Incentive Program Name
              $fieldVal = $row[0]['ProgramName'];
            } else if($fieldKey === 'fieldLocalCurrency') {
              $fieldVal = $fieldVal . ' - ' . $row[0]['localCurrencyName'];
            } else if($fieldKey === 'fieldCarryForwardYears' || $fieldKey === 'fieldLegislativeFramework') {
              $fieldVal == 0 ? $fieldVal = '' : '';
            } else if($ipfData['sDataKeyFormats'][$fieldKey] === 'currencyNoDecimal' ||
                $ipfData['sDataKeyFormats'][$fieldKey] === 'numberNoDecimal') {
              $fieldVal = (int)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT);
            } else if($ipfData['sDataKeyFormats'][$fieldKey] === 'currencyTwoDecimal' || $ipfData['sDataKeyFormats'][$fieldKey] === 'currencyFourDecimal' ||
                $ipfData['sDataKeyFormats'][$fieldKey] === 'numberTwoDecimal' || $ipfData['sDataKeyFormats'][$fieldKey] === 'numberFourDecimal') {
              $fieldVal = (float)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }

            if(!is_null($fieldVal) && $fieldVal !== '') {
              $ipfCells->get($rowIndex, $colIndex)->putValue($fieldVal);
            }
            $colIndex++;
          }
          $rowIndex++;
        }
      }

      // Create a range for the drop down of Internal Credit ID # [International Portfolio Data]
      $range = $ipfCells->createRange($headerRows, 2, $applyRows, 1);
      $range->setName('InternationalInternalCreditIdRange');

      // Header contents [US Portfolio Data]
      $upfCells->get(0, 0)->putValue('US Portfolio Data');

      $upfCells->get(1, 0)->putValue('NOTES & GUIDANCE (expand for detail)');
      $upfCells->get(1, 1)->putValue('Required only if you have the "Linked Accounts" feature to designate which OIX Account/Business Unit this credit should be allocated to.  Type in the name of the Business Unit (or Instance) per your account configuration.');
      $upfCells->get(1, 2)->putValue('If you already have a unique ID for each individual credit,please enter it here.  "Internal Credit IDs" are required if you plan to bulk upload Utilizations, HOWEVER, if you prefer The OIX can first bulk upload your portfolio data, and then provide YOU with the OIX ID # in order to assign "utilizations" to specific projects.  Note: an Internal ID cannot be used more than once if you are recording utilizations on the "Utilization Data" Tab.');
      $upfCells->get(1, 3)->putValue('Project/location name.');
      $upfCells->get(1, 4)->putValue('e.g. any additional designation you would like to assign.');
      $upfCells->get(1, 5)->putValue('Select State or Federal in the drop-down menu.  (For US FEDERAL credits please select "Federal" in the alphabetized menu under "F".)');
      $upfCells->get(1, 6)->putValue('You must select the "State or Federal" first, and then select the "Incentive Program" from the OIX Incenitve Program Database (powered by CCH) which includes 3,500 + federal, state and local US programs.');
      $upfCells->get(1, 7)->putValue('If the Incentive Program is NOT in the OIX Database, please type the name of the program here, and we will add the program name to your own custom database (which after the bulk upload you can select in the future).');
      $upfCells->get(1, 8)->putValue('If applicable, please enter the city here.');
      $upfCells->get(1, 9)->putValue('If applicable, please enter the county here.');
      $upfCells->get(1, 10)->putValue('"SPV" or "Entity" who is the recipient of the incentive.  Type in the name of the Legal Entity.');
      $upfCells->get(1, 11)->putValue('For example, if your incentive is estimated to have a face value of one million US Dollars, then enter "$1,000,000.00".');
      $upfCells->get(1, 12)->putValue('(Decimal Format) If receiving full value of the incentive, then set this to 1.00 (i.e.; 100%). If estimating the future sale (i.e.; a transferable credit) then set to your best estimated percent return on the dollar (i.e. 0.91). Format is up to 12 decimal places. If you\'ve already monetized this credit, then enter your initial estimate (or average of the actual monetization) and the system will compare that to the actual return on each monetization you enter on the "Utilization Data" Tab).');
      $upfCells->get(1, 13)->putValue('Year in which credit can first be applied against liabilities (i.e. year of certification).  This is most valuable when you enter the Carry Forward period so that the system can calculate the credit expiration date.');
      $upfCells->get(1, 14)->putValue('Enter the carry forward period so that the system can calculate and provide the credit expiration date.');
      $upfCells->get(1, 15)->putValue('Statutory, Discretionary or Negotiated.');
      $upfCells->get(1, 16)->putValue('Designate "estimated" or "acutal" credit amount.');
      $upfCells->get(1, 17)->putValue('Type of government assistance. This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $upfCells->get(1, 18)->putValue('"OPEN"=not yet monetized, "AWAITING"=another option to designate \'not started yet\', "IN PROCESS"=some or all portion is in the monetization process, "CLOSED"=monetization complete (credit exhausted). This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $upfCells->get(1, 19)->putValue('This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $upfCells->get(1, 20)->putValue('This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $upfCells->get(1, 21)->putValue('This drop down menu can be customized during the bulk upload by adjusting the Drop Down Data tab, or after the bulk upload.');
      $upfCells->get(1, 22)->putValue('Free-form notes field');
      $upfCells->get(1, 23)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 24)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 25)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 26)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 27)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 28)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 29)->putValue('Optional Date fields.  Highly encouraged.');
      $upfCells->get(1, 30)->putValue('You can enter your poject budget here.');
      $upfCells->get(1, 31)->putValue('You can enter your estimate on Qualified Expenditures here.');
      $upfCells->get(1, 32)->putValue('Enter here the percentage incentive based on qualified expenditures (e.g. 20%, 30%, etc.).');
      $upfCells->get(1, 33)->putValue('This is a "tag name" for the type of work being performed for this project.');
      $upfCells->get(1, 34)->putValue('Free-from notes field');

      $upfColumns = ['fieldListingId', 'fieldSharedAccount', 'fieldInternalId', 'fieldProjectName', 'fieldProjectNameExt',
                     'fieldProvinceName', 'fieldIncentiveProgram', 'fieldIncentiveProgramCustom', 'fieldTownName', 'fieldCountyName',
                     'fieldSellerTaxEntity', 'fieldAmountLocal', 'fieldEstCreditPrice', 'fieldFirstTaxYear', 'fieldCarryForwardYears',
                     'fieldLegislativeFramework', 'fieldCreditAmountStatus', 'fieldCreditType', 'fieldStatusMonetization', 'fieldCertStatus',
                     'fieldProjectStatus', 'fieldAuditStatus', 'fieldStatusNotes', 'fieldProjectStartDate', 'fieldProjectEndDate',
                     'fieldAuditStartDate', 'fieldAuditEndDate', 'fieldEstInitialCertDate', 'fieldEstFinalCertDate', 'fieldIssueDate',
                     'fieldProjectBudgetEst', 'fieldQualifiedExpenditures', 'fieldIncentiveRate', 'fieldTypeOfWork', 'fieldGeneralNotes'];
      foreach($upfColumns as $i => $fieldKey) {
        $upfCells->get(2, $i)->putValue($fieldKey);
      }

      $upfCells->get(3, 0)->putValue('Please do not edit or delete anything within the blue areas (Rows 1-7 and Columns 1-2)   ------------   Note symbols designating * required fields, or ** possibly required fields, pending on account conifguration (see notes at the top of each column for detail)');

      $upfCells->get(4, 0)->putValue('CORE CREDIT & INCENTIVE INFORMATION ------>');
      $upfCells->get(4, 13)->putValue('OPTIONAL AND ADDITONAL CREDIT & INCENTIVE INFORMATION ------>');
      $upfCells->get(4, 18)->putValue('Status Fields');
      $upfCells->get(4, 23)->putValue('Enter date fields as (mm/dd/yyyy)');

      $upfCells->get(5, 0)->putValue('OIX Credit ID');
      $upfCells->get(5, 1)->putValue('Business Unit');
      $upfCells->get(5, 2)->putValue('Internal Credit ID # **');
      $upfCells->get(5, 3)->putValue('Name of Certified Project');
      $upfCells->get(5, 4)->putValue('Project Extension Name');
      $upfCells->get(5, 5)->putValue('Jurisdiction / State or Federal *');
      $upfCells->get(5, 6)->putValue('Incentive Program Name *');
      $upfCells->get(5, 7)->putValue('Custom Incentive Program Name **');
      $upfCells->get(5, 8)->putValue('City');
      $upfCells->get(5, 9)->putValue('County');
      $upfCells->get(5, 10)->putValue('Legal Entity Name *');
      $upfCells->get(5, 11)->putValue('Estimated Credit Amount * (Face Value in $USD)');
      $upfCells->get(5, 12)->putValue('Estimated Credit Value * (in % terms expressed as decimal)');
      $upfCells->get(5, 13)->putValue('First Usable Tax Year');
      $upfCells->get(5, 14)->putValue('Carry Forward (# of Years)');
      $upfCells->get(5, 15)->putValue('Legislative Framework');
      $upfCells->get(5, 16)->putValue('Status of Credit Amount');
      $upfCells->get(5, 17)->putValue('Credit Type');
      $upfCells->get(5, 18)->putValue('Monetization Status');
      $upfCells->get(5, 19)->putValue('Certification Status');
      $upfCells->get(5, 20)->putValue('Project Status');
      $upfCells->get(5, 21)->putValue('Audit Status');
      $upfCells->get(5, 22)->putValue('Status Notes');
      $upfCells->get(5, 23)->putValue('Project Start Date');
      $upfCells->get(5, 24)->putValue('Project End Date');
      $upfCells->get(5, 25)->putValue('Audit Start Date');
      $upfCells->get(5, 26)->putValue('Audit End Date');
      $upfCells->get(5, 27)->putValue('Initial Certification Date');
      $upfCells->get(5, 28)->putValue('Final Certification Date (Estimated)');
      $upfCells->get(5, 29)->putValue('Actual Credit Issue Date');
      $upfCells->get(5, 30)->putValue('Project Budget (Est.)');
      $upfCells->get(5, 31)->putValue('Qualified Expenditures');
      $upfCells->get(5, 32)->putValue('Rate of Incentive (%)');
      $upfCells->get(5, 33)->putValue('Type of Work');
      $upfCells->get(5, 34)->putValue('General Notes');

      $headerRows = 6;
      $applyRows = 1000;
      $fixedCols = 35;
      $customCols = 0;

      // Adjust row height/column width [US Portfolio Data]
      $upfCells->setRowHeight(0, 43);
      $upfCells->setRowHeight(1, 100);
      $upfCells->setRowHeight(2, 24);
      $upfCells->setRowHeight(3, 30);
      $upfCells->setRowHeight(4, 36);
      $upfCells->setRowHeight(5, 36);

      $upfCells->getColumns()->get(0)->setWidth(28);
      $upfCells->getColumns()->get(1)->setWidth(37);
      $upfCells->getColumns()->get(2)->setWidth(27);
      $upfCells->getColumns()->get(3)->setWidth(28);
      $upfCells->getColumns()->get(4)->setWidth(30);
      $upfCells->getColumns()->get(5)->setWidth(32);
      $upfCells->getColumns()->get(6)->setWidth(63);
      $upfCells->getColumns()->get(7)->setWidth(51);
      $upfCells->getColumns()->get(8)->setWidth(19);
      $upfCells->getColumns()->get(9)->setWidth(19);
      $upfCells->getColumns()->get(10)->setWidth(33);
      $upfCells->getColumns()->get(11)->setWidth(29);
      $upfCells->getColumns()->get(12)->setWidth(37);
      $upfCells->getColumns()->get(13)->setWidth(22);
      $upfCells->getColumns()->get(14)->setWidth(27);
      $upfCells->getColumns()->get(15)->setWidth(24);
      $upfCells->getColumns()->get(16)->setWidth(24);
      $upfCells->getColumns()->get(17)->setWidth(33);
      $upfCells->getColumns()->get(18)->setWidth(34);
      $upfCells->getColumns()->get(19)->setWidth(37);
      $upfCells->getColumns()->get(20)->setWidth(27);
      $upfCells->getColumns()->get(21)->setWidth(29);
      $upfCells->getColumns()->get(22)->setWidth(35);
      $upfCells->getColumns()->get(23)->setWidth(18);
      $upfCells->getColumns()->get(24)->setWidth(18);
      $upfCells->getColumns()->get(25)->setWidth(18);
      $upfCells->getColumns()->get(26)->setWidth(18);
      $upfCells->getColumns()->get(27)->setWidth(24);
      $upfCells->getColumns()->get(28)->setWidth(24);
      $upfCells->getColumns()->get(29)->setWidth(23);
      $upfCells->getColumns()->get(30)->setWidth(25);
      $upfCells->getColumns()->get(31)->setWidth(25);
      $upfCells->getColumns()->get(32)->setWidth(25);
      $upfCells->getColumns()->get(33)->setWidth(25);
      $upfCells->getColumns()->get(34)->setWidth(38);

      // Styling [US Portfolio Data]
      for($i = 0; $i < $applyRows; $i++) {
        for($j = 0; $j < $fixedCols; $j++) {
          if($i == 0) {
            $upfCells->get($i, $j)->setStyle($pf_header_style1);
          } else if($i == 1) {
            if($j == 0) {
              $upfCells->get($i, $j)->setStyle($pf_header_style2);
            } else {
              $upfCells->get($i, $j)->setStyle($pf_header_style3);
            }
          } else if($i == 2) {
            $upfCells->get($i, $j)->setStyle($pf_header_style4);
          } else if($i == 3) {
            $upfCells->get($i, $j)->setStyle($pf_header_style5);
          } else if($i == 4) {
            if($j <= 17) {
              $upfCells->get($i, $j)->setStyle($pf_header_style6);
            } else if($j >= 18 && $j <= 22) {
              $upfCells->get($i, $j)->setStyle($pf_header_style7);
            } else if($j >= 23 && $j <= 34) {
              $upfCells->get($i, $j)->setStyle($pf_header_style5);
            }
          } else if($i == 5) {
            $upfCells->get($i, $j)->setStyle($pf_header_style8);
          } else {
            if($j == 0) {
              $upfCells->get($i, $j)->setStyle($pf_body_style1);
            } else if($j == 1) {
              if(!empty($dmaChildAccounts)) {
                $upfCells->get($i, $j)->setStyle($pf_body_style2);
                $upfCells->get($i, $j)->putValue('Select Value');
              } else {
                $upfCells->get($i, $j)->setStyle($pf_body_style1);
                $upfCells->get($i, $j)->putValue($dmaTitle);
              }
            } else if($j == 5 || $j == 6) {
              $upfCells->get($i, $j)->setStyle($pf_body_style2);
              $upfCells->get($i, $j)->putValue('Select Value');
            } else if($j == 11) {
              $number_style = $upfCells->get($i, $j)->getStyle();
              $number_style->setNumber(39);
              $upfCells->get($i, $j)->setStyle($number_style);
            } else if($j == 12) {
              $number_style = $upfCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $upfCells->get($i, $j)->setStyle($number_style);
            } else if($j >= 13 && $j <= 21) {
              $upfCells->get($i, $j)->setStyle($pf_body_style2);
              $upfCells->get($i, $j)->putValue('Select Value');
            } else if($j >= 23 && $j <= 29) {
              $date_style = $upfCells->get($i, $j)->getStyle();
              $date_style->setCustom('mm/dd/yyyy');
              $upfCells->get($i, $j)->setStyle($date_style);
            } else if($j == 30 || $j == 31) {
              $number_style = $upfCells->get($i, $j)->getStyle();
              $number_style->setNumber(37);
              $upfCells->get($i, $j)->setStyle($number_style);
            } else if($j == 32) {
              $number_style = $upfCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $upfCells->get($i, $j)->setStyle($number_style);
            }
          }
        }
      }

      // Create drop downs [US Portfolio Data]
      $upf_validations = $upfSheet->getValidations();

      // Add Business Unit drop down if child accounts exist
      if(!empty($dmaChildAccounts)) {
        $cellArea = new cells\CellArea();
        $cellArea->StartRow = $headerRows;
        $cellArea->StartColumn = 1;
        $cellArea->EndRow = $applyRows;
        $cellArea->EndColumn = 1;

        $tmp_idx = $upf_validations->add($cellArea);
        $validation = $upf_validations->get($tmp_idx);

        $validation->setType(cells\ValidationType::LIST);
        $validation->setInCellDropDown(true);
        $validation->setFormula1("=BusinessUnitRange");
        $validation->setShowError(true);
        $validation->setAlertStyle(cells\ValidationAlertType::STOP);
        $validation->setErrorTitle("Error");
        $validation->setErrorMessage("Please select account from the list");
      }

      // States drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 5;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 5;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=USJx");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a state from the list");

      // Incentive Programs drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 6;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 6;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1('=INDIRECT(SUBSTITUTE(OFFSET(INDIRECT(ADDRESS(ROW(), COLUMN())),0,-1)," ",""))');
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select an incentive program from the list");

      // First Usable Tax Year drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 13;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 13;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=TaxYearRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select the first usable tax year from the list");

      // Carry Forward (# of Years) drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 14;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 14;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CarryForwardYearRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select the number of years from the list");

      // Legislative Framework drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 15;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 15;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=LegisFrameworkRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a legislative framework from the list");

      // Status of Credit Amount drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 16;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 16;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CreditAmountStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a credit amount status from the list");

      // Credit Type drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 17;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 17;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=ProgramTypesRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a credit type from the list");

      // Monetization Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 18;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 18;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=MonetizationStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a monetization status from the list");

      // Certification Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 19;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 19;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=CertificationStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a certification status from the list");

      // Project Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 20;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 20;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=ProjectStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select a project status from the list");

      // Audit Status drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 21;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 21;

      $tmp_idx = $upf_validations->add($cellArea);
      $validation = $upf_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=AuditStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select an audit status from the list");

      // Add columns for custom data points [US Portfolio Data]
      foreach($creditData['dataPoints'] as $j => $dp) {
        // If it's custom field, ...
        if(strpos($dp['dpKey'], 'fieldCustom_') === 0) {
          array_push($upfColumns, $dp['dpKey']);
          $customCols++;

          // If it's first custom field, ...
          if($customCols === 1) {
            $upfCells->get(1, $fixedCols)->putValue('If you want to add a custom data point not included by default the OIX System, simply (1) enter the "name" of the CDP, (2) designate the "type" of CDP by selecting from the "CDP  Type" drop down, and (3) and then use the drop-down selector in Row 10 to designate where on the Load/Edit Credit Form you would like to place the CDP.');
            $upfCells->get(4, $fixedCols)->putValue('CUSTOM DATA POINTS ------>');
          }

          $tmpColIdx = $fixedCols + $customCols - 1;
          $upfCells->get(2, $tmpColIdx)->putValue($dp['dpKey']);
          $upfCells->get(5, $tmpColIdx)->putValue($dp['dpNameFull']);

          $upfCells->getColumns()->get($tmpColIdx)->setWidth(28);

          $upfCells->get(0, $tmpColIdx)->setStyle($pf_header_style1);
          $upfCells->get(1, $tmpColIdx)->setStyle($pf_header_style3);
          $upfCells->get(2, $tmpColIdx)->setStyle($pf_header_style4);
          $upfCells->get(3, $tmpColIdx)->setStyle($pf_header_style5);
          $upfCells->get(4, $tmpColIdx)->setStyle($pf_header_style6);
          $upfCells->get(5, $tmpColIdx)->setStyle($pf_header_style8);

          if($dp['dpType'] === 'date') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $date_style = $upfCells->get($i, $tmpColIdx)->getStyle();
              $date_style->setCustom('mm/dd/yyyy');
              $upfCells->get($i, $tmpColIdx)->setStyle($date_style);
            }
          } else if($dp['dpType'] === 'currencyNoDecimal') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $number_style = $upfCells->get($i, $tmpColIdx)->getStyle();
              $number_style->setNumber(37);
              $upfCells->get($i, $tmpColIdx)->setStyle($number_style);
            }
          } else if($dp['dpType'] === 'currencyTwoDecimal') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $number_style = $upfCells->get($i, $tmpColIdx)->getStyle();
              $number_style->setNumber(39);
              $upfCells->get($i, $tmpColIdx)->setStyle($number_style);
            }
          } else if($dp['dpType'] === 'currencyFourDecimal') {
            for($i = $headerRows; $i < $applyRows; $i++) {
              $number_style = $upfCells->get($i, $tmpColIdx)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $upfCells->get($i, $tmpColIdx)->setStyle($number_style);
            }
          } // If field is drop down, ...
          else if($dp['dpType'] === 'selectDropDown') {
            // Ranges for the drop down for the custom data point already created while International Portfolio Data
            // So no need to create the ranges for the drop downs

            $cellArea = new cells\CellArea();
            $cellArea->StartRow = $headerRows;
            $cellArea->StartColumn = $tmpColIdx;
            $cellArea->EndRow = $applyRows;
            $cellArea->EndColumn = $tmpColIdx;

            $tmp_idx = $upf_validations->add($cellArea);
            $validation = $upf_validations->get($tmp_idx);

            $validation->setType(cells\ValidationType::LIST);
            $validation->setInCellDropDown(true);
            $validation->setFormula1("=" . $dp['dpKey'] . "_Range");
            $validation->setShowError(true);
            $validation->setAlertStyle(cells\ValidationAlertType::STOP);
            $validation->setErrorTitle("Error");
            $validation->setErrorMessage("Please select an option from the list");

            // Stylize drop down
            for($i = $headerRows; $i < $applyRows; $i++) {
              $upfCells->get($i, $tmpColIdx)->setStyle($pf_body_style2);
              $upfCells->get($i, $tmpColIdx)->putValue('Select Value');
            }
          }
        }
      }

      if(strtoupper($includeData) === "INC_DATA") {
        // Remove fieldIncentiveProgramCustom for temporarily to grab credits data
        $upfColumns_tmp = $upfColumns;
        if(($key = array_search('fieldIncentiveProgramCustom', $upfColumns_tmp)) !== false) {
          unset($upfColumns_tmp[$key]);
        }

        // Grab the US credits data and put them into the sheet
        $upfData = $this->get_credits_utilization_data('UPF', $upfColumns_tmp);

        $rowIndex = $headerRows;
        foreach($upfData['sDataKeyValue'] as $row) {
          $colIndex = 0;
          foreach($upfColumns as $fieldKey) {
            $fieldVal = $row[$fieldKey];

            if($fieldKey === 0) { //skip the first item in each row as that's a raw data dump for reference (not needed here)
              continue;
            } else if($fieldKey === 'fieldIncentiveProgramCustom') {  // skip Custom Incentive Program Name in US Portfolio tab
              $colIndex++;
              continue;
            } else if($fieldKey === 'fieldCarryForwardYears' || $fieldKey === 'fieldLegislativeFramework') {
              $fieldVal == 0 ? $fieldVal = '' : '';
            } else if($upfData['sDataKeyFormats'][$fieldKey] === 'currencyNoDecimal' ||
                $upfData['sDataKeyFormats'][$fieldKey] === 'numberNoDecimal') {
              $fieldVal = (int)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT);
            } else if($upfData['sDataKeyFormats'][$fieldKey] === 'currencyTwoDecimal' || $upfData['sDataKeyFormats'][$fieldKey] === 'currencyFourDecimal' ||
                $upfData['sDataKeyFormats'][$fieldKey] === 'numberTwoDecimal' || $upfData['sDataKeyFormats'][$fieldKey] === 'numberFourDecimal') {
              $fieldVal = (float)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }

            if(!is_null($fieldVal) && $fieldVal !== '') {
              $upfCells->get($rowIndex, $colIndex)->putValue($fieldVal);
            }
            $colIndex++;
          }
          $rowIndex++;
        }
      }

      // Create a range for the drop down of Internal Credit ID # [US Portfolio Data]
      $range = $upfCells->createRange($headerRows, 2, $applyRows, 1);
      $range->setName('USInternalCreditIdRange');

      // Table styles [Drop Down Data]
      $header_style1 = new cells\Style();
      $header_style1->getFont()->setName("Arial");
      $header_style1->getFont()->setSize(12);
      $header_style1->getFont()->setColor(cells\Color::getWhite());
      $header_style1->getFont()->setBold(true);
      $header_style1->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $header_style1->setForegroundColor(cells\Color::fromArgb(36, 64, 98));
      $header_style1->setPattern(cells\BackgroundType::SOLID);

      $header_style2 = new cells\Style();
      $header_style2->getFont()->setName("Arial");
      $header_style2->getFont()->setSize(10);
      $header_style2->getFont()->setBold(true);
      $header_style2->getFont()->setColor(cells\Color::getRed());
      $header_style2->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $header_style2->setForegroundColor(cells\Color::fromArgb(242, 220, 219));
      $header_style2->setPattern(cells\BackgroundType::SOLID);

      $header_style3 = new cells\Style();
      $header_style3->getFont()->setName("Arial");
      $header_style3->getFont()->setSize(10);
      $header_style3->getFont()->setBold(true);
      $header_style3->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $header_style3->setForegroundColor(cells\Color::fromArgb(242, 220, 219));
      $header_style3->setPattern(cells\BackgroundType::SOLID);

      $header_style4 = new cells\Style();
      $header_style4->getFont()->setName("Arial");
      $header_style4->getFont()->setSize(10);
      $header_style4->getFont()->setBold(true);
      $header_style4->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $header_style4->setTextWrapped(true);
      $header_style4->setForegroundColor(cells\Color::fromArgb(242, 220, 219));
      $header_style4->setPattern(cells\BackgroundType::SOLID);

      $body_style1 = new cells\Style();
      $body_style1->getFont()->setName("Arial");
      $body_style1->getFont()->setSize(10);
      $body_style1->getFont()->setColor(cells\Color::getRed());
      $body_style1->setTextWrapped(true);
      $body_style1->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $body_style1->setForegroundColor(cells\Color::fromArgb(242, 220, 219));
      $body_style1->setPattern(cells\BackgroundType::SOLID);
      $body_style1->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style1->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style1->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style1->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $body_style2 = new cells\Style();
      $body_style2->getFont()->setName("Arial");
      $body_style2->getFont()->setSize(10);
      $body_style2->getFont()->setColor(cells\Color::getRed());
      $body_style2->getFont()->setBold(true);
      $body_style2->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $body_style2->setForegroundColor(cells\Color::fromArgb(184, 204, 228));
      $body_style2->setPattern(cells\BackgroundType::SOLID);
      $body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style2->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style2->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $body_style3 = new cells\Style();
      $body_style3->getFont()->setName("Arial");
      $body_style3->getFont()->setSize(10);
      $body_style3->setTextWrapped(true);
      $body_style3->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $body_style3->setForegroundColor(cells\Color::fromArgb(242, 220, 219));
      $body_style3->setPattern(cells\BackgroundType::SOLID);
      $body_style3->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style3->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style3->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style3->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $body_style4 = new cells\Style();
      $body_style4->getFont()->setName("Arial");
      $body_style4->getFont()->setSize(10);
      $body_style4->getFont()->setBold(true);
      $body_style4->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $body_style4->setForegroundColor(cells\Color::fromArgb(184, 204, 228));
      $body_style4->setPattern(cells\BackgroundType::SOLID);
      $body_style4->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style4->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style4->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style4->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $body_style5 = new cells\Style();
      $body_style5->getFont()->setName("Arial");
      $body_style5->getFont()->setSize(10);
      $body_style5->getFont()->setColor(cells\Color::getRed());
      $body_style5->setHorizontalAlignment(cells\TextAlignmentType::LEFT);

      $body_style6 = new cells\Style();
      $body_style6->getFont()->setName("Arial");
      $body_style6->getFont()->setSize(10);
      $body_style6->setHorizontalAlignment(cells\TextAlignmentType::LEFT);

      $body_style7 = new cells\Style();
      $body_style7->setForegroundColor(cells\Color::fromArgb(36, 64, 98));
      $body_style7->setPattern(cells\BackgroundType::SOLID);

      $body_style8 = new cells\Style();
      $body_style8->setForegroundColor(cells\Color::fromArgb(0, 0, 0));
      $body_style8->setPattern(cells\BackgroundType::SOLID);
      $body_style8->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style8->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style8->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style8->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $body_style9 = new cells\Style();
      $body_style9->getFont()->setName("Arial");
      $body_style9->getFont()->setSize(10);
      $body_style9->getFont()->setBold(true);
      $body_style9->getFont()->setColor(cells\Color::getWhite());
      $body_style9->setVerticalAlignment(cells\TextAlignmentType::CENTER);
      $body_style9->setForegroundColor(cells\Color::fromArgb(89, 89, 89));
      $body_style9->setPattern(cells\BackgroundType::SOLID);
      $body_style9->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style9->setBorder(cells\BorderType::RIGHT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style9->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());
      $body_style9->setBorder(cells\BorderType::LEFT_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      for($j = 0; $j < $ddColNum; $j++) {
        for($i = 0; $i < 1000; $i++) {
          if($i == 0) {
            $dropdownCells->get($i, $j)->setStyle($header_style1);
          } else if($i == 1) {
            $dropdownCells->get($i, $j)->setStyle($header_style2);
            if($j == $ddEditableColIdx) {
              $dropdownCells->get($i, $j)->setStyle($header_style3);
            } else if($j == $ddCdpColIdx) {
              $dropdownCells->get($i, $j)->setStyle($header_style4);
            }
          } else if($i == 2) {
            if($j < $ddEditableColIdx) {
              $dropdownCells->get($i, $j)->setStyle($body_style1);
            } else {
              $dropdownCells->get($i, $j)->setStyle($body_style3);
            }
          } else if($i == 3) {
            if($j % 2 == 0) {
              if($j < $ddEditableColIdx) {
                $dropdownCells->get($i, $j)->setStyle($body_style2);
              } else if($j < $ddCdpColIdx) {
                $dropdownCells->get($i, $j)->setStyle($body_style4);
              } else {
                $dropdownCells->get($i, $j)->setStyle($body_style9);
              }
            } else {
              $dropdownCells->get($i, $j)->setStyle($body_style7);
            }
          } else {
            if($j % 2 == 0) {
              if($j < $ddEditableColIdx) {
                $dropdownCells->get($i, $j)->setStyle($body_style5);
              } else {
                $dropdownCells->get($i, $j)->setStyle($body_style6);
              }
            } else {
              $dropdownCells->get($i, $j)->setStyle($body_style7);
            }
          }

          if($j == $ddEditableColIdx - 1 || $j == $ddCdpColIdx - 1) {
            $dropdownCells->get($i, $j)->setStyle($body_style8);
          }
        }

        // Set column width
        if($j % 2 == 0) {
          $dropdownCells->getColumns()->get($j)->setWidth(35);
        } else {
          $dropdownCells->getColumns()->get($j)->setWidth(2);
        }
      }

      // Set row height
      $dropdownCells->setRowHeight(0, 30);
      $dropdownCells->setRowHeight(1, 70);
      $dropdownCells->setRowHeight(2, 34);
      $dropdownCells->setRowHeight(3, 22);

      // Styling [GS Drop Downs]
      $gs_header_style1 = new cells\Style();
      $gs_header_style1->getFont()->setName("Arial");
      $gs_header_style1->getFont()->setSize(10);
      $gs_header_style1->getFont()->setBold(true);
      $gs_header_style1->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::getBlack());

      $gs_body_style1 = new cells\Style();
      $gs_body_style1->getFont()->setName("Arial");
      $gs_body_style1->getFont()->setSize(10);

      $gs_body_style2 = new cells\Style();
      $gs_body_style2->getFont()->setName("Arial");
      $gs_body_style2->getFont()->setSize(10);
      $gs_body_style2->setForegroundColor(cells\Color::fromArgb(217, 217, 217));
      $gs_body_style2->setPattern(cells\BackgroundType::SOLID);

      // Get all US states
      $states = $this->IncentivePrograms->get_all_us_states();

      // Get the incentive programs for each of US states
      $programs = [];
      foreach($states as $st) {
        $programs[$st['state']] = $this->IncentivePrograms->get_active_programs_by_state_ordered_name($st['state']);
      }

      $gsColNum = 0;

      // Create a range for US states [GS Drop Downs]
      $statesCnt = count($states);
      $gsCells->get(0, $gsColNum)->putValue('USJx');
      $range = $gsCells->createRange(1, $gsColNum, $statesCnt, 1);
      $range->setName("USJx");
      $gsCells->get(0, $gsColNum)->setStyle($gs_header_style1);

      foreach($states as $i => $st) {
        $range->get($i, 0)->setValue($st['name']);

        if($i % 2) {
          $range->get($i, 0)->setStyle($gs_body_style1);
        } else {
          $range->get($i, 0)->setStyle($gs_body_style2);
        }
      }
      $gsColNum++;
      $gsColNum++;

      // Create ranges for the programs individual US states [GS Drop Downs]
      foreach($states as $st) {
        $programsCnt = count($programs[$st['state']]);
        $gsCells->get(0, $gsColNum)->putValue($st['name']);
        $range = $gsCells->createRange(1, $gsColNum, $programsCnt, 1);
        $range->setName(str_replace(' ', '', $st['name']));
        $gsCells->get(0, $gsColNum)->setStyle($gs_header_style1);

        foreach($programs[$st['state']] as $i => $pg) {
          $range->get($i, 0)->setValue($pg->ProgramName);

          if($i % 2) {
            $range->get($i, 0)->setStyle($gs_body_style1);
          } else {
            $range->get($i, 0)->setStyle($gs_body_style2);
          }
        }
        $gsColNum++;
      }

      // Header contents [International Utilization Data]
      $iutCells->get(0, 0)->putValue('Utilization Data ("Monetization")');

      $iutCells->get(1, 0)->putValue('Utilization ID on OIX Platform (do not change)');
      $iutCells->get(1, 1)->putValue('Credit ID on OIX Platform (do not change)');
      $iutCells->get(1, 2)->putValue('Internal Credit ID  should match the Credit ID  on the Data Import Format tab. If you have multiple utilizations for the same credit then please use the SAME Internal Credit ID . ');
      $iutCells->get(1, 3)->putValue('Date of estimate or actual');
      $iutCells->get(1, 4)->putValue('You can add "estimated" dates of utilization or set to "actual" if you\'ve already utilized (i.e. monetized")');
      $iutCells->get(1, 5)->putValue('Drop down designates how you are "utilizing" the credit.');
      $iutCells->get(1, 6)->putValue('Amount of credit "utilized" as a fixed number.');
      $iutCells->get(1, 7)->putValue('Amount of credit "utilized" as a percentage of overall credit value (ie; if a utilization represents a third of the overall credit, then enter 33.33% here. They system will then auto-calculate for you.)');
      $iutCells->get(1, 8)->putValue('Enter the exchange rate from local currency to USD (the next column will multiply this value by your Utilization Amount Local Currency to show the amount in USD). Format is up to 12 decimal places.');
      $iutCells->get(1, 9)->putValue('(Decimal Format) If receiving full value of the incentive, then set this to 1.00 (i.e.; 100%). If estimating the future sale (i.e.; a transferable credit) then set to your best estimated percent return on the dollar (i.e. 0.91). Format is up to 4 decimal places. Format is up to 12 decimal places.');
      $iutCells->get(1, 10)->putValue('If you plan or have actually sold a credit to a third-party then you can designate the buyer (i.e. "Apple", "Verizon", etc.).  Later, you can add in the "notes" section if you sold through a broker, direct, transaction fees, etc.). ');

      $iutColumns = ['fieldTradeId', 'fieldListingId', 'fieldInternalId', 'fieldDateTraded', 'fieldUtilizationEstActStatus',
                     'fieldUtilizationType', 'fieldUtilizationAmountValueLocal', 'fieldUtilizationPercentageEstimate', 'fieldTradeExchangeRate',
                     'fieldTradePrice', 'fieldUtilizingEntityCustomName'];
      foreach($iutColumns as $i => $fieldKey) {
        $iutCells->get(2, $i)->putValue($fieldKey);
      }

      $iutCells->get(3, 0)->putValue('Please do not edit or delete anything within the blue areas (Rows 1-7 and Columns 1-2)   ------------   Note symbols designating * required fields, or ** possibly required fields, pending on account conifguration (see notes at the top of each column for detail)');

      $iutCells->get(4, 0)->putValue('CORE CREDIT & INCENTIVE INFORMATION ------>');

      $iutCells->get(5, 0)->putValue('OIX Utilization ID');
      $iutCells->get(5, 1)->putValue('OIX Credit ID');
      $iutCells->get(5, 2)->putValue('Internal Credit ID  *');
      $iutCells->get(5, 3)->putValue('Utilization Date *');
      $iutCells->get(5, 4)->putValue('Utilization (est or act) *');
      $iutCells->get(5, 5)->putValue('Utilization Type *');
      $iutCells->get(5, 6)->putValue('Utilization Amount as $ ** (Credit Face Value $)');
      $iutCells->get(5, 7)->putValue('Utilization Amount as % ** (% of Total Credit Face Value)');
      $iutCells->get(5, 8)->putValue('Spot Exchange Rate to USD *');
      $iutCells->get(5, 9)->putValue('Price per Credit (%) *');
      $iutCells->get(5, 10)->putValue('Name of Entity Transferred / Sold To (if applicable) *');

      $headerRows = 6;
      $applyRows = 1000;
      $fixedCols = 11;

      // Adjust row height/column width [International Utilization Data]
      $iutCells->setRowHeight(0, 43);
      $iutCells->setRowHeight(1, 100);
      $iutCells->setRowHeight(2, 24);
      $iutCells->setRowHeight(3, 30);
      $iutCells->setRowHeight(4, 36);
      $iutCells->setRowHeight(5, 36);

      $iutCells->getColumns()->get(0)->setWidth(28);
      $iutCells->getColumns()->get(1)->setWidth(28);
      $iutCells->getColumns()->get(2)->setWidth(27);
      $iutCells->getColumns()->get(3)->setWidth(20);
      $iutCells->getColumns()->get(4)->setWidth(26);
      $iutCells->getColumns()->get(5)->setWidth(28);
      $iutCells->getColumns()->get(6)->setWidth(28);
      $iutCells->getColumns()->get(7)->setWidth(32);
      $iutCells->getColumns()->get(8)->setWidth(30);
      $iutCells->getColumns()->get(9)->setWidth(31);
      $iutCells->getColumns()->get(10)->setWidth(48);

      // Styling [International Utilization Data]
      for($i = 0; $i < $applyRows; $i++) {
        for($j = 0; $j < $fixedCols; $j++) {
          if($i == 0) {
            $iutCells->get($i, $j)->setStyle($pf_header_style1);
          } else if($i == 1) {
            $iutCells->get($i, $j)->setStyle($pf_header_style3);
          } else if($i == 2) {
            $iutCells->get($i, $j)->setStyle($pf_header_style4);
          } else if($i == 3) {
            $iutCells->get($i, $j)->setStyle($pf_header_style5);
          } else if($i == 4) {
            $iutCells->get($i, $j)->setStyle($pf_header_style6);
          } else if($i == 5) {
            $iutCells->get($i, $j)->setStyle($pf_header_style8);
          } else {
            if($j == 0 || $j == 1) {
              $iutCells->get($i, $j)->setStyle($pf_body_style1);
            } else if($j == 2) {
              $iutCells->get($i, $j)->setStyle($pf_body_style2);
              $iutCells->get($i, $j)->putValue('Select Value');
            } else if($j == 3) {
              $date_style = $iutCells->get($i, $j)->getStyle();
              $date_style->setCustom('mm/dd/yyyy');
              $iutCells->get($i, $j)->setStyle($date_style);
            } else if($j == 4) {
              $iutCells->get($i, $j)->setStyle($pf_body_style2);
              $iutCells->get($i, $j)->putValue('Select Value');
            } else if($j == 5) {
              $iutCells->get($i, $j)->setStyle($pf_body_style2);
              $iutCells->get($i, $j)->putValue('Select Value');
            } else if($j == 6 || $j == 7) {
              $number_style = $iutCells->get($i, $j)->getStyle();
              $number_style->setNumber(39);
              $iutCells->get($i, $j)->setStyle($number_style);
            } else if($j == 8 || $j == 9) {
              $number_style = $iutCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $iutCells->get($i, $j)->setStyle($number_style);
            }
          }
        }
      }

      // Create drop downs [International Utilization Data]
      $iut_validations = $iutSheet->getValidations();

      // Internal IDs drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 2;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 2;

      $tmp_idx = $iut_validations->add($cellArea);
      $validation = $iut_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=InternationalInternalCreditIdRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select Internal Credit ID from the list");

      // Utilization drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 4;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 4;

      $tmp_idx = $iut_validations->add($cellArea);
      $validation = $iut_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=UtilizationStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select utilization from the list");

      // Utilization type drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 5;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 5;

      $tmp_idx = $iut_validations->add($cellArea);
      $validation = $iut_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=UtilizationTypesRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select utilization type from the list");

      if(strtoupper($includeData) === "INC_DATA") {
        // Grab the international utilization data and put them into the sheet
        $iutData = $this->get_credits_utilization_data('IUT', $iutColumns);

        $rowIndex = $headerRows;
        foreach($iutData['sDataKeyValue'] as $row) {
          $colIndex = 0;
          foreach($row as $fieldKey => $fieldVal) {
            if($fieldKey === 0) { //skip the first item in each row as that's a raw data dump for reference (not needed here)
              continue;
            }

            if($iutData['sDataKeyFormats'][$fieldKey] === 'currencyNoDecimal' ||
                $iutData['sDataKeyFormats'][$fieldKey] === 'numberNoDecimal') {
              $fieldVal = (int)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT);
            } else if($iutData['sDataKeyFormats'][$fieldKey] === 'currencyTwoDecimal' || $iutData['sDataKeyFormats'][$fieldKey] === 'currencyFourDecimal' ||
                $iutData['sDataKeyFormats'][$fieldKey] === 'numberTwoDecimal' || $iutData['sDataKeyFormats'][$fieldKey] === 'numberFourDecimal') {
              $fieldVal = (float)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }

            if(!is_null($fieldVal) && $fieldVal !== '') {
              $iutCells->get($rowIndex, $colIndex)->putValue($fieldVal);
            }
            $colIndex++;
          }
          $rowIndex++;
        }
      }

      // Header contents [US Utilization Data]
      $uutCells->get(0, 0)->putValue('Utilization Data ("Monetization")');

      $uutCells->get(1, 0)->putValue('Utilization ID on OIX Platform (do not change)');
      $uutCells->get(1, 1)->putValue('Credit ID on OIX Platform (do not change)');
      $uutCells->get(1, 2)->putValue('Internal Credit ID  should match the Credit ID  on the Data Import Format tab. If you have multiple utilizations for the same credit then please use the SAME Internal Credit ID . ');
      $uutCells->get(1, 3)->putValue('Date of estimate or actual');
      $uutCells->get(1, 4)->putValue('You can add "estimated" dates of utilization or set to "actual" if you\'ve already utilized (i.e. monetized")');
      $uutCells->get(1, 5)->putValue('Drop down designates how you are "utilizing" the credit.');
      $uutCells->get(1, 6)->putValue('Amount of credit "utilized" as a fixed number.');
      $uutCells->get(1, 7)->putValue('Amount of credit "utilized" as a percentage of overall credit value (ie; if a utilization represents a third of the overall credit, then enter 33.33% here. They system will then auto-calculate for you.)');
      $uutCells->get(1, 8)->putValue('(Decimal Format) If receiving full value of the incentive, then set this to 1.00 (i.e.; 100%). If estimating the future sale (i.e.; a transferable credit) then set to your best estimated percent return on the dollar (i.e. 0.91). Format is up to 4 decimal places. Format is up to 12 decimal places.');
      $uutCells->get(1, 9)->putValue('If you plan or have actually sold a credit to a third-party then you can designate the buyer (i.e. "Apple", "Verizon", etc.).  Later, you can add in the "notes" section if you sold through a broker, direct, transaction fees, etc.). ');

      $uutColumns = ['fieldTradeId', 'fieldListingId', 'fieldInternalId', 'fieldDateTraded', 'fieldUtilizationEstActStatus',
                     'fieldUtilizationType', 'fieldUtilizationAmountValueLocal', 'fieldUtilizationPercentageEstimate',
                     'fieldTradePrice', 'fieldUtilizingEntityCustomName'];
      foreach($uutColumns as $i => $fieldKey) {
        $uutCells->get(2, $i)->putValue($fieldKey);
      }

      $uutCells->get(3, 0)->putValue('Please do not edit or delete anything within the blue areas (Rows 1-7 and Columns 1-2)   ------------   Note symbols designating * required fields, or ** possibly required fields, pending on account conifguration (see notes at the top of each column for detail)');

      $uutCells->get(4, 0)->putValue('CORE CREDIT & INCENTIVE INFORMATION ------>');

      $uutCells->get(5, 0)->putValue('OIX Utilization ID');
      $uutCells->get(5, 1)->putValue('OIX Credit ID');
      $uutCells->get(5, 2)->putValue('Internal Credit ID  *');
      $uutCells->get(5, 3)->putValue('Utilization Date *');
      $uutCells->get(5, 4)->putValue('Utilization (est or act) *');
      $uutCells->get(5, 5)->putValue('Utilization Type *');
      $uutCells->get(5, 6)->putValue('Utilization Amount as $ ** (Credit Face Value $)');
      $uutCells->get(5, 7)->putValue('Utilization Amount as % ** (% of Total Credit Face Value)');
      $uutCells->get(5, 8)->putValue('Price per Credit (%) *');
      $uutCells->get(5, 9)->putValue('Name of Entity Transferred / Sold To (if applicable) *');

      $headerRows = 6;
      $applyRows = 1000;
      $fixedCols = 10;

      // Adjust row height/column width [US Utilization Data]
      $uutCells->setRowHeight(0, 43);
      $uutCells->setRowHeight(1, 100);
      $uutCells->setRowHeight(2, 24);
      $uutCells->setRowHeight(3, 30);
      $uutCells->setRowHeight(4, 36);
      $uutCells->setRowHeight(5, 36);

      $uutCells->getColumns()->get(0)->setWidth(28);
      $uutCells->getColumns()->get(1)->setWidth(28);
      $uutCells->getColumns()->get(2)->setWidth(27);
      $uutCells->getColumns()->get(3)->setWidth(20);
      $uutCells->getColumns()->get(4)->setWidth(26);
      $uutCells->getColumns()->get(5)->setWidth(28);
      $uutCells->getColumns()->get(6)->setWidth(28);
      $uutCells->getColumns()->get(7)->setWidth(32);
      $uutCells->getColumns()->get(8)->setWidth(31);
      $uutCells->getColumns()->get(9)->setWidth(48);

      // Styling [US Utilization Data]
      for($i = 0; $i < $applyRows; $i++) {
        for($j = 0; $j < $fixedCols; $j++) {
          if($i == 0) {
            $uutCells->get($i, $j)->setStyle($pf_header_style1);
          } else if($i == 1) {
            $uutCells->get($i, $j)->setStyle($pf_header_style3);
          } else if($i == 2) {
            $uutCells->get($i, $j)->setStyle($pf_header_style4);
          } else if($i == 3) {
            $uutCells->get($i, $j)->setStyle($pf_header_style5);
          } else if($i == 4) {
            $uutCells->get($i, $j)->setStyle($pf_header_style6);
          } else if($i == 5) {
            $uutCells->get($i, $j)->setStyle($pf_header_style8);
          } else {
            if($j == 0 || $j == 1) {
              $uutCells->get($i, $j)->setStyle($pf_body_style1);
            } else if($j == 2) {
              $uutCells->get($i, $j)->setStyle($pf_body_style2);
              $uutCells->get($i, $j)->putValue('Select Value');
            } else if($j == 3) {
              $date_style = $uutCells->get($i, $j)->getStyle();
              $date_style->setCustom('mm/dd/yyyy');
              $uutCells->get($i, $j)->setStyle($date_style);
            } else if($j == 4) {
              $uutCells->get($i, $j)->setStyle($pf_body_style2);
              $uutCells->get($i, $j)->putValue('Select Value');
            } else if($j == 5) {
              $uutCells->get($i, $j)->setStyle($pf_body_style2);
              $uutCells->get($i, $j)->putValue('Select Value');
            } else if($j == 6 || $j == 7) {
              $number_style = $uutCells->get($i, $j)->getStyle();
              $number_style->setNumber(39);
              $uutCells->get($i, $j)->setStyle($number_style);
            } else if($j == 8) {
              $number_style = $uutCells->get($i, $j)->getStyle();
              $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
              $uutCells->get($i, $j)->setStyle($number_style);
            }
          }
        }
      }

      // Create drop downs [US Utilization Data]
      $uut_validations = $uutSheet->getValidations();

      // Internal Credit IDs drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 2;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 2;

      $tmp_idx = $uut_validations->add($cellArea);
      $validation = $uut_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=USInternalCreditIdRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select Internal Credit ID from the list");

      // Utilization drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 4;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 4;

      $tmp_idx = $uut_validations->add($cellArea);
      $validation = $uut_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=UtilizationStatusRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select utilization from the list");

      // Utilization type drop down
      $cellArea = new cells\CellArea();
      $cellArea->StartRow = $headerRows;
      $cellArea->StartColumn = 5;
      $cellArea->EndRow = $applyRows;
      $cellArea->EndColumn = 5;

      $tmp_idx = $uut_validations->add($cellArea);
      $validation = $uut_validations->get($tmp_idx);

      $validation->setType(cells\ValidationType::LIST);
      $validation->setInCellDropDown(true);
      $validation->setFormula1("=UtilizationTypesRange");
      $validation->setShowError(true);
      $validation->setAlertStyle(cells\ValidationAlertType::STOP);
      $validation->setErrorTitle("Error");
      $validation->setErrorMessage("Please select utilization type from the list");

      if(strtoupper($includeData) === "INC_DATA") {
        // Grab the US utilization data and put them into the sheet
        $uutData = $this->get_credits_utilization_data('UUT', $uutColumns);

        $rowIndex = $headerRows;
        foreach($uutData['sDataKeyValue'] as $row) {
          $colIndex = 0;
          foreach($row as $fieldKey => $fieldVal) {
            if($fieldKey === 0) { //skip the first item in each row as that's a raw data dump for reference (not needed here)
              continue;
            }

            if($uutData['sDataKeyFormats'][$fieldKey] === 'currencyNoDecimal' ||
                $uutData['sDataKeyFormats'][$fieldKey] === 'numberNoDecimal') {
              $fieldVal = (int)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT);
            } else if($uutData['sDataKeyFormats'][$fieldKey] === 'currencyTwoDecimal' || $uutData['sDataKeyFormats'][$fieldKey] === 'currencyFourDecimal' ||
                $uutData['sDataKeyFormats'][$fieldKey] === 'numberTwoDecimal' || $uutData['sDataKeyFormats'][$fieldKey] === 'numberFourDecimal') {
              $fieldVal = (float)filter_var($fieldVal, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }

            if(!is_null($fieldVal) && $fieldVal !== '') {
              $uutCells->get($rowIndex, $colIndex)->putValue($fieldVal);
            }
            $colIndex++;
          }
          $rowIndex++;
        }
      }

      $fileName = 'OIX-Credits-Bulk-' . date('Y-m-d') . '.xlsx';
      $filePath = APPPATH . '../tmp_files/' . $fileName;
      $workbook->save($filePath);
      header('Pragma: public');
      header("Content-type:application/vnd.ms-excel; charset=utf-8");
      header('Content-Disposition: attachment; filename=' . $fileName);
      header('Content-Length: ' . filesize($filePath));

      readfile($filePath);
    }
  }

  function upload_credits_file() {
    // Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {
      $data['tabArray'] = "account_configuration";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "administrative";
      $data['uploadingMode'] = 'SINGLE';       // SINGLE: Upload one file at a time, MULTIPLE: Upload multiple files at a time

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view('dmaaccount/uploader', $data);
      $this->load->view('includes/footer-2');
    }
  }

  function bulk_process_dashboard() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      //Once all the spreadsheet data is converted into an array
      $data['tabArray'] = "dmaaccount";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "administrative";

      $output = '';

      $twig = \OIX\Util\TemplateProvider::getTwig();
      $template = $twig->load('dmaaccount/bulk_process_dashboard.twig');
      $data['csrf_token_val'] = $_COOKIE['theoix_csrf_cookie']; //TODO: handle this in more generic way, inject certain variables into all templates
      $data['file_cache_ver'] = $this->config->item("file_cache_ver"); //TODO: handle this in more generic way, inject certain variables into all templates

      $output .= $this->load->view('includes/left_nav', $data, true);
      $output .= $this->load->view('includes/tab_nav', $data, true);
      $output .= $template->render();
      $output .= $this->load->view('includes/footer-2', true);
      echo $output;
    }
  }

}
