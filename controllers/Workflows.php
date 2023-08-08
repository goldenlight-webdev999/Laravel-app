<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Workflows extends CI_Controller {
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
    $this->load->model('BidMarket');
    $this->load->model('Trading');
    $this->load->model('Taxpayersdata');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Workflow');
    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');

  }

  function index($type) {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $data['lnav_key'] = "compliance";

    if($type == "workflow") {
      $data['tabArray'] = "workflows";
      $wWorkflowType = "workflow";
      $data['workflowType'] = "workflow";
    } else {
      if($type == "compliance") {
        $data['tabArray'] = "compliance";
        $wWorkflowType = "compliance";
        $data['workflowType'] = "compliance";
      }
    }

    $data['workflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), "", "", "", $wWorkflowType);

    if(in_array($wWorkflowType . 'Templates', $this->cisession->userdata('accessParentDataConfig'))) {
      $parentWorkflowTemplates = $this->Workflow->get_workflow_templates($this->cisession->userdata('parentDmaId'), "", "", 1, $wWorkflowType);
      $data['workflowTemplates'] = array_merge($data['workflowTemplates'], $parentWorkflowTemplates);

      usort($data['workflowTemplates'], function($a, $b) {
        return strcmp($a["wTemplateName"], $b["wTemplateName"]);
      });

    }

    if($wWorkflowType == "compliance" && !$this->cisession->userdata('planCompliance')) {
      $data['tabArray'] = "compliance_noaccess";
    }

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    if($wWorkflowType == "workflow" && !$this->cisession->userdata('planWorkflowTools')) {
      $this->load->view('includes/widgets/planBlockMessage');
    } else {
      if($wWorkflowType == "compliance" && !$this->cisession->userdata('planCompliance')) {
        $this->load->view('includes/widgets/planBlockMessage');
      } else {
        if(sizeof($data['workflowTemplates']) == 0) {
          $data['featureAccess'] = true;
          $data['noResults'] = true;
          $data['feature'] = $type;
          $data['isSearch'] = false;
          $this->load->view("includes/upgrade_or_noresults_message", $data);
        } else {
          $this->load->view("workflows/all", $data);
        }
      }
    }
    $this->load->view('includes/footer-2', $data);

  }

  function select_template() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if(!$this->cisession->userdata('planWorkflowTools')) {
      redirect('/workflows');
    }

    if($this->cisession->userdata('dmaType') == 'broker') {
      redirect('/workflows/edit_template');
    }

    $data['lnav_key'] = "compliance";

    $data['oixWorkflowTemplates'] = $this->Workflow->get_workflow_templates("", "credit", 1);
    $data['myWorkflowTemplates'] = $this->Workflow->get_workflow_templates($this->cisession->userdata('dmaId'), "credit");

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view("workflows/select_template", $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view("workflows/select_template", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function browse() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    $this->session->set_flashdata('browseDuplicateMessage', 1);

    redirect('workflows');

  }

  function edit_compliance_template($wtId = "", $duplicate = "", $oixTemplate = "", $isParentDmaTemplate = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if(!$this->cisession->userdata('planCompliance')) {
      redirect('/compliance');
    }

    $data['lnav_key'] = "compliance";
    $data['wWorkflowType'] = "compliance";

    $data['current_tab_key'] = "";
    $data['lnav_key'] = "compliance";

    if($wtId > 0) {
      $data['tabArray'] = "complianceUpdate";
      $data['wId'] = $wtId;
      $data['newTemplate'] = false;
      $data['workflowTemplate'] = $this->Workflow->get_workflow($wtId);
      $data['duplicate'] = ($duplicate == 1) ? true : false;
      $data['oixTemplate'] = ($oixTemplate == 1) ? true : false;
      $data['isParentDmaTemplate'] = ($isParentDmaTemplate == 1) ? true : false;
      $data['editMode'] = (($duplicate == "" || $duplicate == 0) && ($oixTemplate == "" || $oixTemplate == 0)) ? true : false;
      //Verify access
      $this->memberpermissions->checkWorkflowTemplateAccess($wtId); //redirect if no access
    } else {
      $data['tabArray'] = "complianceNew";
      $data['wId'] = "";
      $data['newTemplate'] = true;
      $data['workflowTemplate'] = [];
      $data['workflowTemplate']['wTemplateData']['childWorkflows'] = [];
      $data['duplicate'] = false;
      $data['oixTemplate'] = false;
      $data['isParentDmaTemplate'] = false;
      $data['editMode'] = false;

    }

    $wiTempIsTemp = ($this->cisession->userdata('dmaType') == "broker") ? 3 : 2;
    $complianceItemTemplatesOixDefault = $this->Workflow->get_compliance_item_templates("", $wiTempIsTemp);
    $wiTempIsTempDmaIds = [$this->cisession->userdata('dmaId')];
    if(in_array('complianceTemplates', $this->cisession->userdata('accessParentDataConfig'))) {
      array_push($wiTempIsTempDmaIds, $this->cisession->userdata('parentDmaId'));
    }
    $complianceItemTemplatesCustom = $this->Workflow->get_compliance_item_templates($wiTempIsTempDmaIds);
    $data['complianceItemTemplates'] = array_merge($complianceItemTemplatesOixDefault, $complianceItemTemplatesCustom);

    usort($data['complianceItemTemplates'], function($a, $b) {
      return strcmp($a["wiTempName"], $b["wiTempName"]);
    });

    $data['data'] = $data;

    $this->load->view('includes/left_nav', $data);
    $this->load->view('includes/tab_nav', $data);
    $this->load->view("workflows/add_edit_compliance_template", $data);
    $this->load->view('includes/footer-2', $data);

  }

  function edit_template($wtId = "", $duplicate = "", $oixTemplate = "", $isParentDmaTemplate = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');

      return;
    }

    if(!$this->cisession->userdata('planWorkflowTools')) {
      redirect('/workflows');
    }

    $data['lnav_key'] = "compliance";
    $data['wWorkflowType'] = "workflow";

    if($wtId > 0) {
      $data['tabArray'] = "workflowUpdate";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "compliance";
      $data['wId'] = $wtId;
      $data['newTemplate'] = false;
      $data['workflowTemplate'] = $this->Workflow->get_workflow($wtId);
      $data['duplicate'] = ($duplicate == 1) ? true : false;
      $data['oixTemplate'] = ($oixTemplate == 1) ? true : false;
      $data['isParentDmaTemplate'] = ($isParentDmaTemplate == 1) ? true : false;
      $data['editMode'] = (($duplicate == "" || $duplicate == 0) && ($oixTemplate == "" || $oixTemplate == 0)) ? true : false;
      //Verify access
      $this->memberpermissions->checkWorkflowTemplateAccess($wtId); //redirect if no access
    } else {
      $data['tabArray'] = "workflowNew";
      $data['current_tab_key'] = "";
      $data['lnav_key'] = "compliance";
      $data['wId'] = "";
      $data['newTemplate'] = true;
      $data['workflowTemplate'] = [];
      $data['workflowTemplate']['wTemplateData']['childWorkflows'] = [];
      $data['duplicate'] = false;
      $data['oixTemplate'] = false;
      $data['isParentDmaTemplate'] = false;
      $data['editMode'] = false;
    }

    $data['wliTemplates'] = $this->Workflow->get_workflow_list_item_templates();
    if(in_array($this->cisession->userdata('dmaId'), $this->config->item('dmaIdsBlockDocPreview'))) {
      $data['wliTemplates'] = []; // erase it
    }

    $data['data'] = $data;

    //If loading page from Admin Panel...
    if($this->cisession->userdata('isAdminPanel') == true) {

      $this->load->view('layouts/admin/header', $data);
      $this->load->view("workflows/add_edit_template", $data);
      $this->load->view('admin/admin_nav', $data);
      $this->load->view('layouts/admin/footer', $data);

    } else {

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav', $data);
      $this->load->view("workflows/add_edit_template", $data);
      $this->load->view('includes/footer-2', $data);

    }

  }

  function save_template($wtId = "") {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if($this->input->post("workflowOrComplianceFlag") == "workflow") {
        if(!$this->cisession->userdata('planWorkflowTools')) {
          redirect('/workflows');
        }
      }
      if($this->input->post("workflowOrComplianceFlag") == "compliance") {
        if(!$this->cisession->userdata('planCompliance')) {
          redirect('/compliance');
        }
      }

      ////////////
      // UPDATE //
      ////////////

      //Updating existing workflow
      if($wtId > 0 && $this->input->post("isCopy") == 0) {

        $this->memberpermissions->checkWorkflowTemplateAccess($wtId); //redirect if no access

        //Set post arrays as variables
        $wlLoopId = 0;
        $wlNames = $this->input->post("wlName");
        $wlNameTagIds = $this->input->post("wlNameTagId");
        $wiListIds = $this->input->post("wiListId");
        $wiTempId = $this->input->post("wiTempId");
        $wlIsPrivate = $this->input->post("wlIsPrivate");
        $wiTempNames = $this->input->post("wiTempName");
        $wiTempTypes = $this->input->post("wiTempType");
        $wiTempSubType = $this->input->post("wiTempSubType");
        $wiTempDataPointId = $this->input->post("wiTempDataPointId");
        $wiTempIsPrivate = $this->input->post("wiTempIsPrivate");
        $wiValue1Expected = $this->input->post("wiValue1Expected");
        $wiCompletedDate1Expected = $this->input->post("wiCompletedDate1Expected");
        $wiComplianceReminder = $this->input->post("wiComplianceReminder");
        $wiComplianceAlert = $this->input->post("wiComplianceAlert");
        $wStartYearCompliance = $this->input->post("complianceFirstYear");
        $wYearsCompliant = $this->input->post("requiredComplianceTerm");
        $wlToDelete = $this->input->post("wlToDelete");

        $wlIds = $this->input->post("wlId");
        $wlIdsRaw = $wlIds;
        $wiIds = $this->input->post("wiId"); //Note: this is diff from line above, which is wlId (L instead of I)

        //Get template
        $workflow = $this->Workflow->get_workflow($wtId);
        //Get all the workflows attached to this template, and add everything into one array
        /*
        $workflows[$wtId] = $workflow;
        $workflowCopies = $this->Workflow->get_child_workflows($wtId, $this->input->post("dmaId"));
        foreach($workflowCopies as $wcs) {
          $workflows[$wcs['wId']] = $this->Workflow->get_workflow($wcs['wId']);
        }
        */

        //First, we update name of workflow template
        if($this->input->post("workflowOrComplianceFlag") == "workflow") {
          $this->Workflow->update_workflow($wtId, $this->input->post("wTemplateName"));
        } else {
          $compConfigs['wTemplateName'] = $this->input->post("wTemplateName");
          $compConfigs['wStartYearCompliance'] = $wStartYearCompliance;
          $compConfigs['wYearsCompliant'] = $wYearsCompliant;
          $this->Workflow->update_compliance_template_configs($wtId, $compConfigs);
        }

        if($this->input->post("workflowOrComplianceFlag") == "workflow") {
          //Next, we delete checklists as needed
          if(is_array($wlToDelete) && sizeof($wlToDelete) > 0) {
            foreach($wlToDelete as $wlDelete) {

              //Find the ID in the parent template and get which key # that is
              $wlDeleteLoop = 0;
              foreach($workflow['wLists'] as $tWl) {
                if($tWl['wlId'] == $wlDelete) {
                  break;
                }
                $wlDeleteLoop++;
              }

              //loop through all worklows, find the value ID of the nth checklist and then update that
              //foreach($workflows as $w) {

              //Get workflow checklist ID of this workflow
              $thisListIdToDelete = $workflow['wLists'][$wlDeleteLoop]['wlId'];
              //Delete this checklist
              $this->Workflow->delete_workflow_checklist($thisListIdToDelete);

              //}

            }
          }
        }

        //Next, we delete checklist items as needed
        $wliToDelete = $this->input->post("wliToDelete");
        if(is_array($wliToDelete) && sizeof($wliToDelete) > 0) {
          foreach($wliToDelete as $wliDelete) {

            //Find the ID in the parent template and get which key # that is
            $wliDeleteLoop = 0;
            foreach($workflow['allwlItems'] as $tWlitems) {
              if($tWlitems['wiId'] == $wliDelete) {
                break;
              }
              $wliDeleteLoop++;
            }

            //loop through all worklows, find the value ID of the nth checklist and then update that
            //foreach($workflows as $w) {

            //Get workflow checklist item info of this workflow
            $thisWclIdToDelete = $workflow['allwlItems'][$wliDeleteLoop]['wiId'];
            $this->Workflow->delete_workflow_checklist_item($thisWclIdToDelete);

            //}

          }
        }

        //IF ITEMS HAVE BEEN DELETED ABOVE
        if(sizeof($wlToDelete) > 0 || sizeof($wliToDelete) > 0) {

          //NOW THAT YOU HAVE DELETED SOME ITEMS FROM WORKFLOW LISTS, YOU NEED TO RESET YOUR ARRAY WITH LATEST DATA BEFORE PROCEEDING
          $workflow = [];
          $workflows = [];
          $workflowCopies = [];

          //Get template
          $workflow = $this->Workflow->get_workflow($wtId);
          //Get all the workflows attached to this template, and add everything into one array
          /*
          $workflows[$wtId] = $workflow;
          $workflowCopies = $this->Workflow->get_child_workflows($wtId, $this->input->post("dmaId"));
          foreach($workflowCopies as $wcs) {
            $workflows[$wcs['wId']] = $this->Workflow->get_workflow($wcs['wId']);
          }
          */

        }

        //IF THIS IS A WORKFLOW and NOT A COMPLIANCE TEMPLATE
        if($this->input->post("workflowOrComplianceFlag") == "workflow") {

          //Next, we edit the checklists
          $clLoop = 0;
          //Loop through checklists - $this->input->post("wlName");
          foreach($wlIds as $wi) {

            //This new checklist title is...
            $thisNCLtitle = $wlNames[$clLoop];
            $thisNCLIsPrivate = $wlIsPrivate[$clLoop];

            if($wi < 90000000) {

              //Find the ID in the parent template and get which key # that is
              $subCLLoop = 0;
              foreach($workflow['wLists'] as $tWl) {
                if($tWl['wlId'] == $wi) {
                  break;
                }
                $subCLLoop++;
              }

              //loop through all worklows, find the value ID of the nth checklist and then update that
              //foreach($workflows as $w) {

              //Get workflow checklist ID of this workflow
              $thisWCLid = $workflow['wLists'][$subCLLoop]['wlId'];
              //Update the value
              $workflowListData["wlName"] = $thisNCLtitle;
              $workflowListData["wlIsPrivate"] = $thisNCLIsPrivate;
              $workflowListData["wlOrder"] = $clLoop + 1;
              $this->Workflow->update_workflow_checklist($thisWCLid, $workflowListData);

              //}

            } else {

              //Insert new checklist
              $workflowListData["wlName"] = $wlNames[$clLoop];
              $workflowListData["wlIsPrivate"] = $wlIsPrivate[$clLoop];
              $workflowListData["wlOrder"] = $clLoop + 1;

              //Loop through workflows and add a checklist item attaching this new item template to this list
              //foreach($workflows as $w) {

              //Get workflow checklist item info of this workflow
              $workflowId = $workflow['wId'];
              //Insert it
              $workflowListId = $this->Workflow->insert_workflow_list_no_post($workflowListData, $workflowId);
              //If this workflow is the main template, then reset this 0 list ID to the new value so it can be used later when adding new list items to it
              if($workflow['wId'] == $wtId) {
                $wlIds[$clLoop] = $workflowListId;
              }

              //}

            }

            $clLoop++;

          }

        }

        //NOW THAT YOU HAVE UPDATED WORKFLOW LISTS, YOU NEED TO RESET YOUR ARRAY WITH LATEST DATA BEFORE PROCEEDING
        $workflow = [];
        $workflows = [];
        $workflowCopies = [];

        //Get template
        $workflow = $this->Workflow->get_workflow($wtId);
        //Get all the workflows attached to this template, and add everything into one array
        /*
        $workflows[$wtId] = $workflow;
        $workflowCopies = $this->Workflow->get_child_workflows($wtId, $this->input->post("dmaId"));
        foreach($workflowCopies as $wcs) {
          $workflows[$wcs['wId']] = $this->Workflow->get_workflow($wcs['wId']);
        }
        */

        //Rectify new lists to have new list IDs where its child is currently set to 0
        $clUpdateLoop = 0;
        $cliListArrayPos = 0; //Set to -1 because the first loop below will properly set it to the 0 starting position
        $workflowRectLists = $workflow['allwlItems'];

        foreach($wlIdsRaw as $wlne) {

          $wiuCount = 0;
          foreach($wiIds as $wiu) {

            if($wlne == $wiListIds[$wiuCount]) {

              //If this item has an ID of 0, then we need to update the item list id array with the correct list ID for the loop below
              if($wiu == 0) {
                $wiListIds[$clUpdateLoop] = $workflow['wLists'][$cliListArrayPos]['wlId'];
              }

              $clUpdateLoop++;

            }

            $wiuCount++;

          }

          $cliListArrayPos++;

        }

        //NOW, WE EDIT THE CHECKLIST ITEMS
        $cliLoop = 0;
        $cliListIdLast = 0;
        $cliOrderLoop = 0;
        //Loop through checklist items - $this->input->post("wlName");
        foreach($wiIds as $wii) {

          //This new checklist item title and list id are...
          $thisNCLitemtitle = $wiTempNames[$cliLoop];
          $thisNCLitemListId = $wiListIds[$cliLoop];
          if($cliListIdLast != $thisNCLitemListId) {
            $cliOrderLoop = 0;
          } //Reset the loop
          $cliListIdLast = $thisNCLitemListId;

          //If this item has an ID, then find the ID in the parent template and get which key # that is
          $subCLiLoop = 0;
          if($wii > 0) {
            foreach($workflow['allwlItems'] as $tWlitems) {
              if($tWlitems['wiId'] == $wii) {
                break;
              }
              $subCLiLoop++;
            }
          }

          //Find the list ID in the parent template and get which key # that is
          $subCLiGetListIdLoop = 0;
          foreach($workflow['wLists'] as $wd) {
            if($wd['wlId'] == $thisNCLitemListId) {
              break;
            }
            $subCLiGetListIdLoop++;
          }

          if($wii > 0) {

            //loop through all worklows, find the value ID of the nth checklist and then update that
            //foreach($workflows as $w) {

            //Get workflow checklist item info of this workflow
            $thisWCLitemid = $workflow['allwlItems'][$subCLiLoop]['wiId'];
            $thisWCLitemTemplateid = $workflow['allwlItems'][$subCLiLoop]['wiTempId'];
            $workflowListItemData['wiListId'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? $workflow['wLists'][$subCLiGetListIdLoop]['wlId'] : $workflow['wLists'][0]['wlId']; //Get the ID for the new list ID, using loop count above
            $workflowListItemData['wiValue1Expected'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiValue1Expected[$cliLoop];
            $workflowListItemData['wiCompletedDate1Expected'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiCompletedDate1Expected[$cliLoop];
            $workflowListItemData['wiComplianceReminder'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiComplianceReminder[$cliLoop];
            $workflowListItemData['wiComplianceAlert'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiComplianceAlert[$cliLoop];
            $workflowListItemData['wiOrder'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? $cliOrderLoop + 1 : null;
            $wtRequest["wiTempId"] = $thisWCLitemTemplateid;
            $wtRequest["wiTempName"] = $thisNCLitemtitle;
            $wtRequest["wiTempIsPrivate"] = $wiTempIsPrivate[$cliLoop];
            //var_dump($workflowListItemData);
            $this->Workflow->update_workflow_checklist_item_template($wtRequest);
            $this->Workflow->update_workflow_checklist_item($thisWCLitemid, $workflowListItemData);

            //}

          } else {

            //Insert checklist Item template
            $workflowListItemTemplateData["wiTempName"] = $wiTempNames[$cliLoop];
            $workflowListItemTemplateData["wiTempType"] = $wiTempTypes[$cliLoop];
            $workflowListItemTemplateData["wiTempSubType"] = $wiTempSubType[$cliLoop];
            $workflowListItemTemplateData["wiTempIsPrivate"] = $wiTempIsPrivate[$cliLoop];

            //If this is an existing template item
            $workflowListItemTemplateData["wiTempDataPointId"] = ($wiTempDataPointId[$cliLoop] > 0) ? $wiTempDataPointId[$cliLoop] : null;
            //If this is a custom compliance option created by this DMA
            if($wiTempId[$cliLoop] > 90000000) {
              $workflowListItemTemplateData["wiTempIsTemp"] = 100;
            }
            $workflowListItemTemplateId = $this->Workflow->check_workflow_list_item_template_exists($workflowListItemTemplateData["wiTempName"], $workflowListItemTemplateData["wiTempType"], $this->cisession->userdata('dmaId'));
            $workflowListItemTemplateId = ($workflowListItemTemplateId > 0) ? $workflowListItemTemplateId : $this->Workflow->insert_workflow_list_item_template_no_post($workflowListItemTemplateData);

            //Insert checklist item attached to database item template ID and checklist ID
            $workflowListItemData["wiTempId"] = $workflowListItemTemplateId;
            $workflowListItemData['wiOrder'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? $cliOrderLoop + 1 : null;
            //echo $workflowListItemData["wiTempId"]."<br>";

            //Loop through workflows and add a checklist item attaching this new item template to this list
            //foreach($workflows as $w) {

            //Get workflow checklist item info of this workflow
            $thisWCLitemid = $workflow['allwlItems'][$subCLiLoop]['wiId'];
            $thisWCLitemTemplateid = $workflow['allwlItems'][$subCLiLoop]['wiTempId'];
            $workflowListItemData['wiListId'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? $workflow['wLists'][$subCLiGetListIdLoop]['wlId'] : $workflow['wLists'][0]['wlId'];
            $workflowListItemData['wiValue1Expected'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiValue1Expected[$cliLoop];
            $workflowListItemData['wiCompletedDate1Expected'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiCompletedDate1Expected[$cliLoop];
            $workflowListItemData['wiComplianceReminder'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiComplianceReminder[$cliLoop];
            $workflowListItemData['wiComplianceAlert'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiComplianceAlert[$cliLoop];
            $workflowListItemData['wiOrder'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? $cliOrderLoop + 1 : null;

            //Insert it
            $this->Workflow->insert_workflow_list_item_no_post($workflowListItemData, $workflowListItemData['wiListId']);

            //}

          }

          $cliLoop++;
          $cliOrderLoop++;

        }

        $workflowId = $wtId;

        //If this is a COMPLIANCE template and is connected to at least one credit, then check due dates as you may have adjusted deadlines
        if(sizeof($workflow['wTemplateData']['childWorkflows']) > 0) {
          if($this->input->post("workflowOrComplianceFlag") == "compliance") {
            foreach($workflow['wTemplateData']['childWorkflows'] as $cc) {
              $this->CreditListings->check_compliance_items_for_reminder_dates($cc['listingId']);
            }
          }
        }

        /////////////
        // ADD NEW //
        /////////////

        //Creating new workflow
      } else {

        //Set _ array as a variable
        $wlLoopId = 0;
        $wlNames = $this->input->post("wlName");
        $wiTempId = $this->input->post("wiTempId");
        $wlNameTagIds = $this->input->post("wlNameTagId");
        $wiListIds = $this->input->post("wiListId");
        $wlIsPrivate = $this->input->post("wlIsPrivate");
        $wiTempNames = $this->input->post("wiTempName");
        $wiTempTypes = $this->input->post("wiTempType");
        $wiTempSubType = $this->input->post("wiTempSubType");
        $wiTempIsPrivate = $this->input->post("wiTempIsPrivate");
        $wiValue1Expected = $this->input->post("wiValue1Expected");
        $wiCompletedDate1Expected = $this->input->post("wiCompletedDate1Expected");
        $wiTempDataPointId = $this->input->post("wiTempDataPointId");
        $wiComplianceReminder = $this->input->post("wiComplianceReminder");
        $wiComplianceAlert = $this->input->post("wiComplianceAlert");
        $wStartYearCompliance = $this->input->post("complianceFirstYear");
        $wYearsCompliant = $this->input->post("requiredComplianceTerm");
        $wAttachedToType = $this->input->post("wAttachedToType");

        //Insert workflow (template) - $this->input->post("wTemplateName");
        $workflowData["wAttachedToType"] = ($wAttachedToType == "transaction") ? "transaction" : "credit";
        $workflowData["wWorkflowType"] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? "workflow" : "compliance";
        $workflowData["wTemplateName"] = $this->input->post("wTemplateName");
        $workflowId = $this->Workflow->insert_workflow_no_post($workflowData, "");

        if($this->input->post("workflowOrComplianceFlag") == "compliance") {
          $compConfigs['wTemplateName'] = $this->input->post("wTemplateName");
          $compConfigs['wStartYearCompliance'] = $wStartYearCompliance;
          $compConfigs['wYearsCompliant'] = $wYearsCompliant;
          $this->Workflow->update_compliance_template_configs($workflowId, $compConfigs);
        }

        //Loop through checklists - $this->input->post("wlName");
        foreach($wlNames as $wl) {

          //Insert checklist with the workflow database ID
          $workflowListData["wlName"] = $wl;
          $workflowListData["wlIsPrivate"] = $wlIsPrivate[$wlLoopId];
          $workflowListData["wlOrder"] = $wlLoopId + 1;
          $workflowListId = $this->Workflow->insert_workflow_list_no_post($workflowListData, $workflowId);

          //Reset the inner loop counter
          $wliLoopId = 0;

          //Loop through checklist items - $this->input->post("wiListId");
          foreach($wiListIds as $wli) {

            //If this matching looped checklist temp ID - $this->input->post("wlNameTagId") - matches the loop - $this->input->post("wiListId");
            if($wlNameTagIds[$wlLoopId] == $wli) {

              //Insert checklist Item template
              $workflowListItemTemplateData["wiTempName"] = $wiTempNames[$wliLoopId];
              $workflowListItemTemplateData["wiTempType"] = $wiTempTypes[$wliLoopId];
              $workflowListItemTemplateData["wiTempSubType"] = $wiTempSubType[$wliLoopId];
              $workflowListItemTemplateData["wiTempIsPrivate"] = $wiTempIsPrivate[$wliLoopId];
              //If this is an existing template item
              //if($wiTempDataPointId[$wliLoopId]>0) {
              $workflowListItemTemplateData["wiTempDataPointId"] = $wiTempDataPointId[$wliLoopId];

              //}

              //If this is an existing template item
              $workflowListItemTemplateData["wiTempDataPointId"] = ($wiTempDataPointId[$wliLoopId] > 0) ? $wiTempDataPointId[$wliLoopId] : null;
              //If this is a custom compliance option created by this DMA
              if($wiTempId[$wliLoopId] > 90000000) {
                $workflowListItemTemplateData["wiTempIsTemp"] = 100;
              }
              //Check if template exists within this account
              $workflowListItemTemplateId = $this->Workflow->check_workflow_list_item_template_exists($workflowListItemTemplateData["wiTempName"], $workflowListItemTemplateData["wiTempType"], $this->cisession->userdata('dmaId'));
              $workflowListItemTemplateId = ($workflowListItemTemplateId > 0) ? $workflowListItemTemplateId : $this->Workflow->insert_workflow_list_item_template_no_post($workflowListItemTemplateData);

              //Insert checklist item attached to database item template ID and checklist ID
              $workflowListItemData["wiTempId"] = $workflowListItemTemplateId;
              $workflowListItemData["wiOrder"] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? $wliLoopId + 1 : null;
              $workflowListItemData['wiValue1Expected'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiValue1Expected[$wliLoopId];
              $workflowListItemData['wiCompletedDate1Expected'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiCompletedDate1Expected[$wliLoopId];
              $workflowListItemData['wiComplianceReminder'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiComplianceReminder[$wliLoopId];
              $workflowListItemData['wiComplianceAlert'] = ($this->input->post("workflowOrComplianceFlag") == "workflow") ? null : $wiComplianceAlert[$wliLoopId];

              $this->Workflow->insert_workflow_list_item_no_post($workflowListItemData, $workflowListId);

            }

            $wliLoopId++;

          }

          $wlLoopId++;

        }

      }

      //Set a flashdata
      $this->session->set_flashdata('templateSavedMessage', 1);

      //Redirect user to workflow list
      if($this->input->post('redirectToLocationType') != "") {

        if($this->input->post('redirectToLocationType') == "credit") {
          if($this->input->post("workflowOrComplianceFlag") == "workflow") {
            redirect("/dashboard/credit/" . $this->input->post('redirectToCreditWorkflow') . "/workflow");
          } else {
            redirect("/dashboard/credit/" . $this->input->post('redirectToCreditWorkflow') . "/compliance");
          }
        }

        if($this->input->post('redirectToLocationType') == "trade") {
          $tradeId = $this->input->post('redirectToCreditWorkflow');
          $tradeData = $this->Trading->get_trade($tradeId);
          $trade = $tradeData['trade'];
          $listingId = $trade['listingId'];
          redirect("/dashboard/credit/" . $listingId . "/sale/" . $trade['tradeId']);
        }

        if($this->input->post('redirectToLocationType') == "transaction") {
          $transactionId = $this->input->post('redirectToCreditWorkflow');
          $transactionData = $this->Trading->get_transaction_by_id($transactionId);
          $tradeData = $this->Trading->get_trade($transactionData['tradeId']);
          $trade = $tradeData['trade'];
          $listingId = $transactionData['listingId'];
          redirect("/dashboard/credit/" . $listingId . "/multisale/" . $trade['tradeId'] . "/transaction/" . $transactionId);
        }

      } else {
        if($this->input->post("workflowOrComplianceFlag") == "workflow") {
          redirect("/workflows/edit_template/" . $workflowId);
        } else {
          redirect("/workflows/edit_compliance_template/" . $workflowId);
        }
      }

      if($this->input->post('redirectToCreditWorkflow') > 0) {
      }

    }
  }

  function delete_workflow($wId) {

    //Verify access
    $wAccess = $this->memberpermissions->checkWorkflowTemplateAccess($wId);

    if($wAccess['editAccess']) {

      $workflowTemplate = $this->Workflow->get_workflow($wId);

      //Make sure there are no child templates
      if($workflowTemplate['wTemplateData']['childWorkflowsCount'] == 0) {
        $this->Workflow->delete_workflow($wId);
      }

    }

    //Set a flashdata
    $this->session->set_flashdata('templateDeletedMessage', 1);

    //Redirect user to workflow list
    if($workflowTemplate['wTemplateData']['wWorkflowType'] == 'workflow') {
      redirect("/workflows");
    } else {
      redirect("/compliance");
    }

  }

}
