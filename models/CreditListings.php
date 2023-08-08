<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class CreditListings extends CI_Model {
  protected $logger;

  private $pending_table_name = 'PendingListings';
  private $active_table_name = 'ActiveListings';
  private $enable_exp_date = '1';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $this->pending_table_name;
    $this->load->model('Trading');
    $this->load->model('Email_Model');
    $this->load->model('Members_Model');
    $this->load->model('AuditTrail');
    $this->load->library(['session']);

    $this->load->library(['currency']);

    $this->logger = Logger::getInstance();

    require_once __DIR__ . '/../../vendor/autoload.php';
    $this->redisIsAvailable = false;
//    try {
//      $this->redis = new Predis\Client([
//                                           'scheme' => $this->config->item('AWS_elasticache_scheme'),
//                                           'host'   => $this->config->item('AWS_elasticache_endpoint'),
//                                           'port'   => 6379,
//                                       ]);
//      $this->redis->ping();
//      $this->redisIsAvailable = true;
//    } catch(Exception $ex) {
//      $this->logger->error("Error connection to redis from CreditListings model with message: " . $ex->getMessage());
//    }

  }


  //////////////////////////////////////
  ///// SUPPORTING INPUT FUNCTIONS /////
  //////////////////////////////////////

  function credit_input($input) {

    //cache
    $input['cache'] = isset($input['cache']) ? $input['cache'] : false;

    //Check input data
    $input['id'] = isset($input['id']) ? $input['id'] : null;
    $input['shared'] = isset($input['shared']) ? $input['shared'] : null;
    $input['sharedAccount'] = isset($input['sharedAccount']) ? $input['sharedAccount'] : null;
    $input['parentMainAdmin'] = isset($input['parentMainAdmin']) ? $input['parentMainAdmin'] : null;
    $input['taxEntities'] = isset($input['taxEntities']) ? $input['taxEntities'] : null;
    $input['getShareData'] = ($this->cisession->userdata('dmaType') == "advisor" || $this->cisession->userdata('dmaType') == "broker") ? true : false;
    $input['account'] = isset($input['account']) ? $input['account'] : null;
    $input['taxyears'] = isset($input['taxyears']) ? $input['taxyears'] : null;
    $input['jurisdictions'] = isset($input['jurisdictions']) ? $input['jurisdictions'] : null;
    $input['countries'] = isset($input['countries']) ? $input['countries'] : null;
    $input['provinces'] = isset($input['provinces']) ? $input['provinces'] : null;
    $input['counties'] = isset($input['counties']) ? $input['counties'] : null;
    $input['towns'] = isset($input['towns']) ? $input['towns'] : null;
    $input['incentiveprogram'] = isset($input['incentiveprogram']) ? $input['incentiveprogram'] : null;
    $input['projects'] = isset($input['projects']) ? $input['projects'] : null;
    $input['listingId'] = isset($input['listingId']) ? $input['listingId'] : null;
    $input['status'] = isset($input['status']) ? $input['status'] : null;
    $input['type'] = isset($input['type']) ? $input['type'] : null;
    $input['cOrigin'] = isset($input['cOrigin']) ? $input['cOrigin'] : null;
    $input['countryId'] = isset($input['countryId']) ? $input['countryId'] : null;
    $input['order'] = isset($input['order']) ? $input['order'] : null;
    $input['limit'] = isset($input['limit']) ? $input['limit'] : null;
    $input['page'] = isset($input['page']) ? $input['page'] : null;
    $input['loanType'] = isset($input['loanType']) ? $input['loanType'] : null;
    $input['includeWorkflows'] = isset($input['includeWorkflows']) ? $input['includeWorkflows'] : null;
    $input['creditUtilizationStatus'] = isset($input['creditUtilizationStatus']) ? $input['creditUtilizationStatus'] : 0;
    $thisDmaId = isset($input['dmaId']) ? $input['dmaId'] : null;
    $input['dmaId'] = (int)$thisDmaId;
    $thisDmaMemberId = isset($input['dmaMemberId']) ? $input['dmaMemberId'] : 0;
    $input['dmaMemberId'] = (int)$thisDmaMemberId;
    $input['tradeType'] = isset($input['tradeType']) ? $input['tradeType'] : null;
    $input['complianceIsConfigured'] = isset($input['complianceIsConfigured']) ? $input['complianceIsConfigured'] : null;
    $input['advisorRepresentative'] = isset($input['advisorRepresentative']) ? $input['advisorRepresentative'] : null;
    $input['advisorProjectAssignedTo'] = isset($input['advisorProjectAssignedTo']) ? $input['advisorProjectAssignedTo'] : null;

    //Status
    $input['showArchived'] = isset($input['showArchived']) ? $input['showArchived'] : null;
    $input['certStatus'] = isset($input['certStatus']) ? $input['certStatus'] : null;
    $input['auditStatus'] = isset($input['auditStatus']) ? $input['auditStatus'] : null;
    $input['monetizationStatus'] = isset($input['monetizationStatus']) ? $input['monetizationStatus'] : null;
    $input['projectStatus'] = isset($input['projectStatus']) ? $input['projectStatus'] : null;
    $input['advisorStatus'] = isset($input['advisorStatus']) ? $input['advisorStatus'] : null;
    $input['customerAccessStatus'] = isset($input['customerAccessStatus']) ? $input['customerAccessStatus'] : null;
    //Utilization input data
    $input['utilizationStatus'] = isset($input['utilizationStatus']) ? $input['utilizationStatus'] : null;
    $input['utilizationTypes'] = isset($input['utilizationTypes']) ? $input['utilizationTypes'] : null;
    //Calendar input
    $input['start_date_report'] = isset($input['start_date_report']) ? $input['start_date_report'] : null;
    $input['end_date_report'] = isset($input['end_date_report']) ? $input['end_date_report'] : null;
    $input['actions'] = isset($input['actions']) ? $input['actions'] : null;
    //Dates
    $input['start_date_utilizationDate'] = isset($input['start_date_utilizationDate']) ? $input['start_date_utilizationDate'] : null;
    $input['end_date_utilizationDate'] = isset($input['end_date_utilizationDate']) ? $input['end_date_utilizationDate'] : null;

    $input['start_date_auditStartDate'] = isset($input['start_date_auditStartDate']) ? $input['start_date_auditStartDate'] : null;
    $input['end_date_auditStartDate'] = isset($input['end_date_auditStartDate']) ? $input['end_date_auditStartDate'] : null;
    $input['start_date_auditEndDate'] = isset($input['start_date_auditEndDate']) ? $input['start_date_auditEndDate'] : null;
    $input['end_date_auditEndDate'] = isset($input['end_date_auditEndDate']) ? $input['end_date_auditEndDate'] : null;

    $input['start_date_projectStartDate'] = isset($input['start_date_projectStartDate']) ? $input['start_date_projectStartDate'] : null;
    $input['end_date_projectStartDate'] = isset($input['end_date_projectStartDate']) ? $input['end_date_projectStartDate'] : null;
    $input['start_date_projectEndDate'] = isset($input['start_date_projectEndDate']) ? $input['start_date_projectEndDate'] : null;
    $input['end_date_projectEndDate'] = isset($input['end_date_projectEndDate']) ? $input['end_date_projectEndDate'] : null;

    $input['start_date_creditIssueDate'] = isset($input['start_date_creditIssueDate']) ? $input['start_date_creditIssueDate'] : null;
    $input['end_date_creditIssueDate'] = isset($input['end_date_creditIssueDate']) ? $input['end_date_creditIssueDate'] : null;

    $input['start_date_certFinalDate'] = isset($input['start_date_certFinalDate']) ? $input['start_date_certFinalDate'] : null;
    $input['end_date_certFinalDate'] = isset($input['end_date_certFinalDate']) ? $input['end_date_certFinalDate'] : null;

    $input['start_date_certInitialDate'] = isset($input['start_date_certInitialDate']) ? $input['start_date_certInitialDate'] : null;
    $input['end_date_certInitialDate'] = isset($input['end_date_certInitialDate']) ? $input['end_date_certInitialDate'] : null;

    $input['start_date_lastDayPrincPhotoDate'] = isset($input['start_date_lastDayPrincPhotoDate']) ? $input['start_date_lastDayPrincPhotoDate'] : null;
    $input['end_date_lastDayPrincPhotoDate'] = isset($input['end_date_lastDayPrincPhotoDate']) ? $input['end_date_lastDayPrincPhotoDate'] : null;

    $input['start_date_loadedDate'] = isset($input['start_date_loadedDate']) ? $input['start_date_loadedDate'] : null;
    $input['end_date_loadedDate'] = isset($input['end_date_loadedDate']) ? $input['end_date_loadedDate'] : null;

    return $input;

  }



  //////////////////////////////////////
  ///// SUPPORTING WHERE FUNCTIONS /////
  //////////////////////////////////////

  //NOTE - see note in next line
  function credit_where_access($input) { //NOTE:this function is also in WORKFLOW model
    if($input['dmaId'] > 0 && $input['dmaMemberId'] > 0) {
      $whereAccess = "((creditAccess.caAction='open' AND creditAccess.caDmaId=" . $input['dmaId'] . " AND creditAccess.caDmaMemberId IS NULL) OR ((creditAccess.caAction='edit' OR creditAccess.caAction='view') AND creditAccess.caDmaId=" . $input['dmaId'] . " AND creditAccess.caDmaMemberId=" . $input['dmaMemberId'] . "))";
      $this->db->where($whereAccess);
    } else {
      throw new \Exception('no dma ID or dma member ID supplied.');
    }
  }

  function credit_where_owner($input) {

    if($input['id'] != "") {
      if($input['id'] == "" && in_array($this->cisession->userdata('userId'), $this->config->item('oix_backend_id'))) {
        //Return ALL credits from ALL Accounts
      } else {
        if($input['id'] == "") {
          //If try to access all credits, but not a backend admin, then return nothing
          return [];
        } else {
          $values = explode(',', $input['id']);
          $this->db->where_in('ActiveListings.listedBy', $values);
          $whereCustomerAccess = "(PendingListings.cCustomerAccess IS NULL OR PendingListings.cCustomerAccess = 2)";
          $this->db->where($whereCustomerAccess);
        }
      }
    }

  }

  function credit_where_shared_owner($input) {

    if($input['sharedAccount'] != "" && $input['sharedAccount'] != 0) {

      $sharedAccountsAsArray = explode(',', $input['sharedAccount']);
      //If own account is the ONLY ID in this array...
      if(sizeof($sharedAccountsAsArray) == 1 && in_array($this->cisession->userdata('dmaId'), $sharedAccountsAsArray)) {
        $this->db->where('PendingListings.listedBy', $this->cisession->userdata('dmaId'));
        //else if own account is in the array with others...
      } else {
        if(in_array($this->cisession->userdata('dmaId'), $sharedAccountsAsArray)) {
          $whereSharedAccess = "(PendingListings.listedBy=" . $this->cisession->userdata('dmaId') . " OR (PendingListings.listedBy IN (" . $input['sharedAccount'] . ") AND  ((creditAccess.caAction='open' AND creditAccess.caDmaId=" . $this->cisession->userdata('dmaId') . " AND creditAccess.caDmaMemberId IS NULL) OR ((creditAccess.caAction='edit' OR creditAccess.caAction='view') AND creditAccess.caDmaId=" . $this->cisession->userdata('dmaId') . " AND creditAccess.caDmaMemberId=" . $this->cisession->userdata('dmaMemberId') . "))))";
          $this->db->where($whereSharedAccess);

          //otherwise, it's just shared accounts then keep it simple
        } else {
          //echo $input['sharedAccount'] . ' ' . $this->cisession->userdata('dmaId') . ' ' . $this->cisession->userdata('userId');
          $whereSharedAccess = "(PendingListings.listedBy IN (" . $input['sharedAccount'] . ") AND  ((creditAccess.caAction='open' AND creditAccess.caDmaId=" . $this->cisession->userdata('dmaId') . " AND creditAccess.caDmaMemberId IS NULL) OR ((creditAccess.caAction='edit' OR creditAccess.caAction='view') AND creditAccess.caDmaId=" . $this->cisession->userdata('dmaId') . " AND creditAccess.caDmaMemberId=" . $this->cisession->userdata('dmaMemberId') . ")))";
          $this->db->where($whereSharedAccess);

        }
      }

    }

  }

  function credit_where_parent_owner($input) {

    if($input['parentMainAdmin'] != "" && $input['parentMainAdmin'] > 0) {

      //If own account is the ONLY ID in this array...
      $saWhere = "(Shares.sharedPrimId=" . $input['parentMainAdmin'] . " OR PendingListings.listedBy=" . $input['parentMainAdmin'] . ")";
      $this->db->where($saWhere);
      $this->db->where('Shares.sDeleteMarker', null);

    }

  }

  function credit_where_advisor_status($input) {
    if($input['advisorStatus'] != "" && $input['advisorStatus'] != 0) {
      $values = explode(',', $input['advisorStatus']);
      if(sizeof($values) > 0) {
        foreach($values as $v) {
          if($v == 1) {
            array_push($values, 0);
          }
        }
        $values = implode(',', $values);
        if(substr($values, -1) == ',') {
          $values = substr($values, 0, -1);
        }
        $advisorStatus = "(Shares.advisorStatus IN (" . $values . "))";
        $this->db->where($advisorStatus);
        $this->db->where('Shares.sDeleteMarker', null);
      }
    }
  }

  function credit_where_cOrigin($input) {
    if($input['cOrigin'] == "all" || $input['cOrigin'] == "" || $input['cOrigin'] == "0") {
      //don't filter by cOrigin
    } else {
      $values = explode(',', $input['cOrigin']);
      $this->db->where_in('PendingListings.cOrigin', $values);
    }
  }

  function credit_where_taxpayers($input) {
    if($input['taxEntities'] != "0" && $input['taxEntities'] != "" && $input['taxEntities'] != "all") {
      $values = explode(',', $input['taxEntities']);
      $this->db->where_in('PendingListings.cTaxpayerId', $values);
    }
  }

  function credit_where_taxyears($input) {
    if($input['taxyears'] != "0" && $input['taxyears'] != "" && $input['taxyears'] != "all") {
      $values = explode(',', $input['taxyears']);
      $this->db->where_in('PendingListings.taxYearId', $values);
    }
  }

  function credit_where_archived($input) {
    if($input['showArchived'] == 2) {
      //include both active and archived
    } else {
      if($input['showArchived'] == 1) {
        $this->db->where('PendingListings.cArchived', 1);
      } else {
        $this->db->where('PendingListings.cArchived', null);
      }
    }
  }

  function credit_where_certificationStatus($input) {
    if($input['certStatus'] != "0" && $input['certStatus'] != "" && $input['certStatus'] != "all") {
      $values = explode(',', $input['certStatus']);
      $this->db->where_in('PendingListings.certificationStatus', $values);
    }
  }

  function credit_where_monetizationStatus($input) {
    if($input['monetizationStatus'] != "0" && $input['monetizationStatus'] != "") {
      $values = explode(',', $input['monetizationStatus']);
      $this->db->where_in('monetization_status.mnsId', $values);
    }
  }

  function credit_where_auditStatus($input) {
    if($input['auditStatus'] != "0" && $input['auditStatus'] != "") {
      $values = explode(',', $input['auditStatus']);
      $this->db->where_in('audit_status.statusId', $values);
    }
  }

  function credit_where_projectStatus($input) {
    if($input['projectStatus'] != "0" && $input['projectStatus'] != "") {
      $values = explode(',', $input['projectStatus']);
      $this->db->where_in('project_status.statusId', $values);
    }
  }

  function credit_where_jurisdictions($input) {
    if($input['jurisdictions'] != "0" && $input['jurisdictions'] != "" && $input['jurisdictions'] != "all") {
      $values = explode(',', $input['jurisdictions']);
      $this->db->where_in('States.state', $values);
    }
  }

  function credit_where_countries($input) {
    if($input['countries'] != "0" && $input['countries'] != "" && $input['countries'] != "all") {
      $values = explode(',', $input['countries']);
      $this->db->where_in('loc_c.id', $values);
    }
  }

  function credit_where_provinces($input) {
    if($input['provinces'] != "0" && $input['provinces'] != "" && $input['provinces'] != "all") {
      $values = explode(',', $input['provinces']);
      $this->db->where_in('loc_p.id', $values);
    }
  }

  function credit_where_counties($input) {
    if($input['counties'] != "0" && $input['counties'] != "" && $input['counties'] != "all") {
      $values = explode(',', $input['counties']);
      $this->db->where_in('loc_co.id', $values);
    }
  }

  function credit_where_towns($input) {
    if($input['towns'] != "0" && $input['towns'] != "" && $input['towns'] != "all") {
      $values = explode(',', $input['towns']);
      $this->db->where_in('loc_t.id', $values);
    }
  }

  function credit_where_listingId($input) {
    if($input['listingId'] != "0" && $input['listingId'] != "" && $input['listingId'] != "all") {
      $this->db->where('PendingListings.listingId', $input['listingId']);
    }
  }

  function credit_where_customer_access_status($input) {
    if($input['customerAccessStatus'] == 1 || $input['customerAccessStatus'] == 2) {
      $this->db->where('PendingListings.cCustomerAccess', $input['customerAccessStatus']);
    }
  }

  function credit_where_programs($input) {
    if($input['incentiveprogram'] != "0" && $input['incentiveprogram'] != "" && $input['incentiveprogram'] != "all") {
      $values = explode(',', $input['incentiveprogram']);
      $this->db->where_in('PendingListings.OIXIncentiveId', $values);
    }
  }

  function credit_where_projects($input) {
    if(($input['projects'] != "" || $input['projects'] == "NA0") && $input['projects'] != "0" && $input['projects'] != "all") {
      if($input['projects'] == "NA0") {
        $input['projects'] = "";
      }
      $thisProjects = str_replace("oixapostrophetag", "'", str_replace('oixquotetag', '"', $input['projects']));
      $values = explode('----', $thisProjects);
      $this->db->where_in('PendingListings.stateCertNum', $values);
    }
  }

  function credit_where_status($input) {
    if($input['status'] == "managed") {
      $this->db->where('PendingListings.status', 5);
    } else {
      if($input['status'] == "selling_listed") {
        $this->db->where('PendingListings.status', 3);
        $this->db->where('ActiveListings.listed', 1);
      } else {
        if($input['status'] == "selling_pending") {
          $this->db->where('PendingListings.status', 3);
          $wherePending = "(ActiveListings.listed IS NULL OR ActiveListings.listed = 0)";
          $this->db->where($wherePending);
        } else {
          if($input['status'] == "sold") {
            $this->db->where('PendingListings.status', 9);
          } else {
            if($input['status'] != "0" && $input['status'] != "" && $input['status'] != "all") {
              $this->db->where('PendingListings.status', $input['status']);
            } else {
              $this->db->where('PendingListings.status IN (3, 5, 9)');
            }
          }
        }
      }
    }
  }

  function credit_where_compliance_template_configured($input) {
    if(is_bool($input['complianceIsConfigured'])) { //as this is a TRUE / FALSE (or NULL for nothing)
      if($input['complianceIsConfigured']) {
        $this->db->where('PendingListings.cComplianceId >', 0);
      } else {
        if(!$input['complianceIsConfigured']) {
          $this->db->where('PendingListings.cComplianceId', null);
        }
      }
    }
  }

  function credit_where_type($input) {
    if($input['type'] != "0" && $input['type'] != "" && $input['type'] != "all") {
      $values = explode(',', $input['type']);
      $this->db->where_in('ProgramType.ProgramTypeId', $values);
    }
  }

  function credit_where_countryId($input) {
    if($input['countryId'] != "0" && $input['countryId'] != "" && $input['countryId'] != "all") {
      $this->db->where('States.countryId', $input['countryId']);
    }
  }

  function credit_where_utilization_status($input) {

    if($input['creditUtilizationStatus'] != "") {
      $values = explode(',', $input['creditUtilizationStatus']);
      if(in_array('utilized_none', $values) && in_array('utilized_partial', $values)) {
        $where = '(PendingListings.creditAmount >= PendingListings.availableToList AND PendingListings.availableToList!=0)';
        $this->db->where($where);
      } else {
        if(in_array('utilized_none', $values) && in_array('utilized_full', $values)) {
          $where = '(PendingListings.creditAmount = PendingListings.availableToList OR PendingListings.availableToList=0)';
          $this->db->where($where);
        } else {
          if(in_array('utilized_partial', $values) && in_array('utilized_full', $values)) {
            $this->db->where('PendingListings.creditAmount > PendingListings.availableToList');
          } else {
            if(in_array('utilized_none', $values)) {
              $this->db->where('PendingListings.creditAmount = PendingListings.availableToList');
            } else {
              if(in_array('utilized_partial', $values)) {
                $where = '(PendingListings.creditAmount > PendingListings.availableToList AND PendingListings.availableToList!=0)';
                $this->db->where($where);
              } else {
                if(in_array('utilized_full', $values)) {
                  $this->db->where('PendingListings.availableToList', 0);
                }
              }
            }
          }
        }
      }
    }

  }

  function utilization_where_status($input) {
    if($input['utilizationStatus'] != "") {
      $values = explode(',', $input['utilizationStatus']);
      if(in_array(1, $values) && in_array(2, $values)) {
        //do nothing - include all
      } else {
        if(in_array(1, $values)) {
          $this->db->where('Trades.tradeIsEstimated', 1);
        } else {
          if(in_array(2, $values)) {
            $this->db->where('Trades.tradeIsEstimated', 0);
          }
        }
      }
    }
  }

  function utilization_where_type($input) {
    if($input['utilizationTypes'] != "" && $input['utilizationTypes'] != 0) {
      $values = explode(',', $input['utilizationTypes']);
      $this->db->where_in('utilizationTypes.id', $values);
    }
  }

  // DATE FILTERS

  function credit_where_start_loaded_date($input) {
    if($input['start_date_loadedDate'] != "0" && $input['start_date_loadedDate'] != "") {
      $start_date_report = date('Y-m-d', $input['start_date_loadedDate']);
      $this->db->where('PendingListings.timeStamp >=', $start_date_report);
    }
  }

  function credit_where_end_loaded_date($input) {
    if($input['end_date_loadedDate'] != "0" && $input['end_date_loadedDate'] != "") {
      $end_date_report = date('Y-m-d', $input['end_date_loadedDate']);
      $this->db->where('PendingListings.timeStamp <=', $end_date_report);
    }
  }

  function utilization_where_start_date($input) {
    if($input['start_date_utilizationDate'] != "0" && $input['start_date_utilizationDate'] != "") {
      $start_date_report = date('Y-m-d', $input['start_date_utilizationDate']);
      $this->db->where('Trades.timeStamp >=', $start_date_report);
    }
  }

  function utilization_where_end_date($input) {
    if($input['end_date_utilizationDate'] != "0" && $input['end_date_utilizationDate'] != "") {
      $end_date_report = date('Y-m-d', $input['end_date_utilizationDate']);
      $this->db->where('Trades.timeStamp <=', $end_date_report);
    }
  }

  function credit_where_start_audit_start_date($input) {
    if($input['start_date_auditStartDate'] != "0" && $input['start_date_auditStartDate'] != "") {
      $start_date_report = $input['start_date_auditStartDate'];
      $this->db->where('PendingListings.auditStartDate >=', $start_date_report);
    }
  }

  function credit_where_end_audit_start_date($input) {
    if($input['end_date_auditStartDate'] != "0" && $input['end_date_auditStartDate'] != "") {
      $end_date_report = $input['end_date_auditStartDate'];
      $this->db->where('PendingListings.auditStartDate <=', $end_date_report);
    }
  }

  function credit_where_start_audit_end_date($input) {
    if($input['start_date_auditEndDate'] != "0" && $input['start_date_auditEndDate'] != "") {
      $start_date_report = $input['start_date_auditEndDate'];
      $this->db->where('PendingListings.auditEndDate >=', $start_date_report);
    }
  }

  function credit_where_end_audit_end_date($input) {
    if($input['end_date_auditEndDate'] != "0" && $input['end_date_auditEndDate'] != "") {
      $end_date_report = $input['end_date_auditEndDate'];
      $this->db->where('PendingListings.auditEndDate <=', $end_date_report);
    }
  }

  function credit_where_start_project_start_date($input) {
    if($input['start_date_projectStartDate'] != "0" && $input['start_date_projectStartDate'] != "") {
      $start_date_report = $input['start_date_projectStartDate'];
      $this->db->where('PendingListings.projectStartDate >=', $start_date_report);
    }
  }

  function credit_where_end_project_start_date($input) {
    if($input['end_date_projectStartDate'] != "0" && $input['end_date_projectStartDate'] != "") {
      $end_date_report = $input['end_date_projectStartDate'];
      $this->db->where('PendingListings.projectStartDate <=', $end_date_report);
    }
  }

  function credit_where_start_project_end_date($input) {
    if($input['start_date_projectEndDate'] != "0" && $input['start_date_projectEndDate'] != "") {
      $start_date_report = $input['start_date_projectEndDate'];
      $this->db->where('PendingListings.projectEndDate >=', $start_date_report);
    }
  }

  function credit_where_end_project_end_date($input) {
    if($input['end_date_projectEndDate'] != "0" && $input['end_date_projectEndDate'] != "") {
      $end_date_report = $input['end_date_projectEndDate'];
      $this->db->where('PendingListings.projectEndDate <=', $end_date_report);
    }
  }

  function credit_where_start_init_cert_date($input) {
    if($input['start_date_certInitialDate'] != "0" && $input['start_date_certInitialDate'] != "") {
      $start_date_report = $input['start_date_certInitialDate'];
      $this->db->where('PendingListings.est_initial_cert_dt >=', $start_date_report);
    }
  }

  function credit_where_end_init_cert_date($input) {
    if($input['end_date_certInitialDate'] != "0" && $input['end_date_certInitialDate'] != "") {
      $end_date_report = $input['end_date_certInitialDate'];
      $this->db->where('PendingListings.est_initial_cert_dt <=', $end_date_report);
    }
  }

  function credit_where_start_final_cert_date($input) {
    if($input['start_date_certFinalDate'] != "0" && $input['start_date_certFinalDate'] != "") {
      $start_date_report = $input['start_date_certFinalDate'];
      $this->db->where('PendingListings.est_final_cert_dt >=', $start_date_report);
    }
  }

  function credit_where_end_final_cert_date($input) {
    if($input['end_date_certFinalDate'] != "0" && $input['end_date_certFinalDate'] != "") {
      $end_date_report = $input['end_date_certFinalDate'];
      $this->db->where('PendingListings.est_final_cert_dt <=', $end_date_report);
    }
  }

  function credit_where_start_credit_issue_date($input) {
    if($input['start_date_creditIssueDate'] != "0" && $input['start_date_creditIssueDate'] != "") {
      $start_date_report = $input['start_date_creditIssueDate'];
      $this->db->where('PendingListings.IssueDate >=', $start_date_report);
    }
  }

  function credit_where_end_credit_issue_date($input) {
    if($input['end_date_creditIssueDate'] != "0" && $input['end_date_creditIssueDate'] != "") {
      $end_date_report = $input['end_date_creditIssueDate'];
      $this->db->where('PendingListings.IssueDate <=', $end_date_report);
    }
  }

  function credit_where_start_last_day_principal_photo_date($input) {
    if($input['start_date_lastDayPrincPhotoDate'] != "0" && $input['start_date_lastDayPrincPhotoDate'] != "") {
      $start_date_report = $input['start_date_lastDayPrincPhotoDate'];
      $this->db->where('PendingListings.lastDayPrincipalPhotography >=', $start_date_report);
    }
  }

  function credit_where_end_last_day_principal_photo_date($input) {
    if($input['end_date_lastDayPrincPhotoDate'] != "0" && $input['end_date_lastDayPrincPhotoDate'] != "") {
      $end_date_report = $input['end_date_lastDayPrincPhotoDate'];
      $this->db->where('PendingListings.lastDayPrincipalPhotography <=', $end_date_report);
    }
  }

  function credit_where_cache_filters($data, $input) {

    $thisInclude = true;

    //credit_where_owner
    if($thisInclude && $input['id'] != "") {
      $values = explode(',', $input['id']);
      if(!in_array($data['listedBy'], $values)) {
        $thisInclude = false;
      }
    }

    //credit_where_shared_owner
    if($thisInclude && $input['sharedAccount'] != "" && $input['sharedAccount'] != 0) {
      $values = explode(',', $input['sharedAccount']);
      if(count($values) == 1 && in_array($this->cisession->userdata('dmaId'), $values)) {
        if(!in_array($data['listedBy'], $values)) {
          $thisInclude = false;
        }
      } else {
        if(in_array($this->cisession->userdata('dmaId'), $values)) {
          if(isset($data['myShare']['sharerPrimId']) && in_array($data['myShare']['sharerPrimId'], $values) && $data['myShare']['sharedPrimId'] == $this->cisession->userdata('dmaId') && $data['myShare']['sDeleteMarker'] == null) {
          } else {
            $thisInclude = false;
          }
          if($data['listedBy'] == $this->cisession->userdata('dmaId') && $data['myShare']['sDeleteMarker'] == null) {
          } else {
            $thisInclude = false;
          }
        } else {
          if(isset($data['myShare']['sharerPrimId']) && in_array($data['myShare']['sharerPrimId'], $values) && $data['myShare']['sharedPrimId'] == $this->cisession->userdata('dmaId') && $data['myShare']['sDeleteMarker'] == null) {
          } else {
            $thisInclude = false;
          }
        }
      }

    }

    //credit_where_advisor_status
    if($thisInclude && $input['advisorStatus'] != "" && $input['advisorStatus'] != 0) {
      $values = explode(',', $input['advisorStatus']);
      if(sizeof($values) > 0) {
        foreach($values as $v) {
          if($v == 1) {
            array_push($values, 0); //include 0 if 1 exists because 0 and 1 are "pending"
          }
        }
        if(!in_array($data['advisorStatus'], $values)) {
          $thisInclude = false;
        }
      }
    }

    //Legal Entities
    if($thisInclude && $input['taxEntities'] != "" && $input['taxEntities'] != 0) {
      $values = explode(',', $input['taxEntities']);
      if(!in_array($data['taxpayerId'], $values)) {
        $thisInclude = false;
      }
    }
    //Tax Years
    if($thisInclude && $input['taxyears'] != "0" && $input['taxyears'] != "" && $input['taxyears'] != "all") {
      $values = explode(',', $input['taxyears']);
      if(!in_array($data['taxYearId'], $values)) {
        $thisInclude = false;
      }
    }
    //Jurisdictions
    if($thisInclude && $input['jurisdictions'] != "0" && $input['jurisdictions'] != "" && $input['jurisdictions'] != "all") {
      $values = explode(',', $input['jurisdictions']);
      if(!in_array($data['state'], $values)) {
        $thisInclude = false;
      }
    }
    //Countries
    if($thisInclude && $input['countries'] != "0" && $input['countries'] != "" && $input['countries'] != "all") {
      $values = explode(',', $input['countries']);
      if(!in_array($data['countryId'], $values)) {
        $thisInclude = false;
      }
    }
    //Provinces
    if($thisInclude && $input['provinces'] != "0" && $input['provinces'] != "" && $input['provinces'] != "all") {
      $values = explode(',', $input['provinces']);
      if(!in_array($data['provinceId'], $values)) {
        $thisInclude = false;
      }
    }
    //Counties
    if($thisInclude && $input['counties'] != "0" && $input['counties'] != "" && $input['counties'] != "all") {
      $values = explode(',', $input['counties']);
      if(!in_array($data['countyId'], $values)) {
        $thisInclude = false;
      }
    }
    //Towns
    if($thisInclude && $input['towns'] != "0" && $input['towns'] != "" && $input['towns'] != "all") {
      $values = explode(',', $input['towns']);
      if(!in_array($data['townId'], $values)) {
        $thisInclude = false;
      }
    }

    //Listing ID
    if($thisInclude && $input['listingId'] != "0" && $input['listingId'] != "" && $input['listingId'] != "all") {
      if($input['listingId'] == $data['listingId']) {
      } else {
        $thisInclude = false;
      }
    }
    //Incentive Programs
    if($thisInclude && $input['incentiveprogram'] != "0" && $input['incentiveprogram'] != "" && $input['incentiveprogram'] != "all") {
      $values = explode(',', $input['incentiveprogram']);
      if(!in_array($data['OIXIncentiveId'], $values)) {
        $thisInclude = false;
      }
    }
    //Projects
    if($thisInclude && ($input['projects'] != "" || $input['projects'] == "NA0") && $input['projects'] != "0" && $input['projects'] != "all") {
      $thisProjects = str_replace("oixapostrophetag", "'", str_replace('oixquotetag', '"', $input['projects']));
      $values = explode('----', $thisProjects);
      if(!in_array($data['projectName'], $values)) {
        $thisInclude = false;
      }
    }

    //Status
    if($thisInclude) {
      if($input['status'] == "managed") {
        if($input['status'] == 5) {
        } else {
          $thisInclude = false;
        }
      } else {
        if($input['status'] == "selling_listed") {
          if($input['status'] == 3 && $input['listed'] == 1) {
          } else {
            $thisInclude = false;
          }
        } else {
          if($input['status'] == "selling_pending") {
          } else {
            if($input['status'] == "sold") {
              if($input['status'] == 9) {
              } else {
                $thisInclude = false;
              }
            } else {
              if($input['status'] != "0" && $input['status'] != "" && $input['status'] != "all") {
                if($input['status'] == $input['status']) {
                } else {
                  $thisInclude = false;
                }
              } else {
                $values = [3, 5, 9];
                if(!in_array($data['status'], $values)) {
                  $thisInclude = false;
                }
              }
            }
          }
        }
      }
    }
    //Original
    if($thisInclude) {
      if($input['cOrigin'] == "all" || $input['cOrigin'] == "" || $input['cOrigin'] == "0") {
      } else {
        $values = explode(',', $input['cOrigin']);
        if(!in_array($data['cOrigin'], $values)) {
          $thisInclude = false;
        }
      }
    }
    //Program Type
    if($thisInclude && $input['type'] != "0" && $input['type'] != "" && $input['type'] != "all") {
      $values = explode(',', $input['type']);
      if(!in_array($data['ProgramTypeId'], $values)) {
        $thisInclude = false;
      }
    }
    //Country
    if($thisInclude && $input['countryId'] != "0" && $input['countryId'] != "" && $input['countryId'] != "all") {
      if($input['countryId'] == $data['countryId']) {
      } else {
        $thisInclude = false;
      }
    }
    //domesticOrInternational
    if($thisInclude && $input['domesticOrInternational'] != "0" && $input['domesticOrInternational'] != "" && $input['domesticOrInternational'] != "all") {
      if(($input['domesticOrInternational'] == 'domestic' && $data['countryId'] == 1) || ($input['domesticOrInternational'] == 'international' && $data['countryId'] > 1)) {
      } else {
        $thisInclude = false;
      }
    }
    //Credit Utilization Status
    if($thisInclude && $input['creditUtilizationStatus'] != "" && $input['creditUtilizationStatus'] != "0") {
      $values = explode(',', $input['creditUtilizationStatus']);
      if(in_array('utilized_none', $values) && in_array('utilized_partial', $values)) {
        if($data['creditAmount'] >= $data['availableToList'] && $data['availableToList'] != 0) {
        } else {
          $thisInclude = false;
        }
      } else {
        if(in_array('utilized_none', $values) && in_array('utilized_full', $values)) {
          if($data['creditAmount'] == $data['availableToList'] || $data['availableToList'] == 0) {
          } else {
            $thisInclude = false;
          }
        } else {
          if(in_array('utilized_partial', $values) && in_array('utilized_full', $values)) {
            if($data['creditAmount'] > $data['availableToList']) {
            } else {
              $thisInclude = false;
            }
          } else {
            if(in_array('utilized_none', $values)) {
              if($data['creditAmount'] == $data['availableToList']) {
              } else {
                $thisInclude = false;
              }
            } else {
              if(in_array('utilized_partial', $values)) {
                if($data['creditAmount'] > $data['availableToList'] && $data['availableToList'] != 0) {
                } else {
                  $thisInclude = false;
                }
              } else {
                if(in_array('utilized_full', $values)) {
                  if($data['availableToList'] == 0) {
                  } else {
                    $thisInclude = false;
                  }
                }
              }
            }
          }
        }
      }
    }
    //Compliance is configured
    if($thisInclude && is_bool($input['complianceIsConfigured'])) { //as this is a TRUE / FALSE (or NULL for nothing)
      if($input['complianceIsConfigured']) {
        if($data['cComplianceId'] > 0) {
        } else {
          $thisInclude = false;
        }
      } else {
        if(!$input['complianceIsConfigured']) {
          if($data['cComplianceId'] == "") {
          } else {
            $thisInclude = false;
          }
        }
      }
    }
    //Utilization status
    if($thisInclude && $input['utilizationStatus'] != "0" && $input['utilizationStatus'] != "" && $input['utilizationStatus'] != "all") {
      if($input['utilizationStatus'] == "utilized_estimated_partial") {
        if($data['utilize_estimate_count'] > 0 && $data['credit_amount_not_estimated_utilized'] > 0) {
        } else {
          $thisInclude = false;
        }
      } else {
        if($input['utilizationStatus'] == "utilized_estimated_full") {
          if($data['utilize_estimate_count'] > 0 && $data['credit_amount_not_estimated_utilized'] == 0) {
          } else {
            $thisInclude = false;
          }
        } else {
          if($input['utilizationStatus'] == "utilized_partial") {
            if($data['utilize_actual_count'] > 0 && $data['credit_amount_not_actual_utilized'] > 0) {
            } else {
              $thisInclude = false;
            }
          } else {
            if($input['utilizationStatus'] == "utilized_full") {
              if($data['utilize_actual_count'] > 0 && $data['credit_amount_not_actual_utilized'] == 0) {
              } else {
                $thisInclude = false;
              }
            } else {
              if($input['utilizationStatus'] == "utilized_none") {
                if($data['utilize_estimate_count'] == 0 && $data['utilize_actual_count'] == 0 && $data['credit_amount_not_actual_utilized'] > 0) {
                } else {
                  $thisInclude = false;
                }
              }
            }
          }
        }
      }
    }

    //ADVISOR FILTERS

    //if filtered by advisor assigned to
    if($thisInclude && isset($input['advisorRepresentative']) && $input['advisorRepresentative'] != "" && $input['advisorRepresentative'] != 0) {
      //If loan type exists
      if(isset($data['loanType']) && $data['loanType'] != "") {
        $advisorRepresentativeArray = explode(',', $input['advisorRepresentative']);
        if($myLoan['estProvider'] > 0 && in_array($myLoan['estProvider'], $advisorRepresentativeArray)) {
        }
      }
      //if((isset($input['advisorRepresentative']) && $input['advisorRepresentative']!="" && $input['advisorRepresentative']!=0) || (isset($input['advisorProjectAssignedTo']) && $input['advisorProjectAssignedTo']!="" && $input['advisorProjectAssignedTo']!=0)) { continue; }
    }
    //if filtered by advisor assigned to
    if($thisInclude && isset($input['advisorProjectAssignedTo']) && $input['advisorProjectAssignedTo'] != "" && $input['advisorProjectAssignedTo'] != 0) {
      //If loan type exists
      if(isset($data['loanType']) && $data['loanType'] != "") {
        $advisorProjectAssignedToArray = explode(',', $input['advisorProjectAssignedTo']);
        if($myLoan['assignedTo'] > 0 && in_array($myLoan['assignedTo'], $advisorProjectAssignedToArray)) {
        }
      }
    }

    //credit_where_customer_access_status
    if($thisInclude && isset($input['customerAccessStatus']) && $input['customerAccessStatus'] != "" && $input['customerAccessStatus'] != 0) {
      if($input['customerAccessStatus'] == 1 || $input['customerAccessStatus'] == 2) {
        if($data['cCustomerAccess'] != $input['customerAccessStatus']) {
          $thisInclude = false;
        }
      }
    }

    // STATUS

    //Archived
    if($thisInclude) {
      if($input['showArchived'] == 2) {
        //include both active and archived
      } else {
        if($input['showArchived'] == 1) {
          if($data['cArchived'] == 1) {
          } else {
            $thisInclude = false;
          }
        } else {
          if($data['cArchived'] != 1) {
          } else {
            $thisInclude = false;
          }
        }
      }
    }
    //Cert Status
    if($thisInclude && $input['certStatus'] != "0" && $input['certStatus'] != "" && $input['certStatus'] != "all") {
      $values = explode(',', $input['certStatus']);
      if(!in_array($data['certificationStatus'], $values)) {
        $thisInclude = false;
      }
    }
    //Monetization Status
    if($thisInclude && $input['monetizationStatus'] != "0" && $input['monetizationStatus'] != "") {
      $values = explode(',', $input['monetizationStatus']);
      if(!in_array($data['monetizationStatusId'], $values)) {
        $thisInclude = false;
      }
    }
    //Audit status
    if($thisInclude && $input['auditStatus'] != "0" && $input['auditStatus'] != "") {
      $values = explode(',', $input['auditStatus']);
      if(!in_array($data['auditStatusId'], $values)) {
        $thisInclude = false;
      }
    }
    //Project status
    if($thisInclude && $input['projectStatus'] != "0" && $input['projectStatus'] != "") {
      $values = explode(',', $input['projectStatus']);
      if(!in_array($data['projectStatusId'], $values)) {
        $thisInclude = false;
      }
    }

    // DATES

    //Audit Dates
    if($thisInclude && $input['start_date_auditStartDate'] != "0" && $input['start_date_auditStartDate'] != "") {
      $start_date_report = $input['start_date_auditStartDate'];
      if($data['auditStartDate'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_auditStartDate'] != "0" && $input['end_date_auditStartDate'] != "") {
      $end_date_report = $input['end_date_auditStartDate'];
      if($data['auditStartDate'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['start_date_auditEndDate'] != "0" && $input['start_date_auditEndDate'] != "") {
      $start_date_report = $input['start_date_auditEndDate'];
      if($data['auditEndDate'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_auditEndDate'] != "0" && $input['end_date_auditEndDate'] != "") {
      $end_date_report = $input['end_date_auditEndDate'];
      if($data['auditEndDate'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    //Project Dates
    if($thisInclude && $input['start_date_projectStartDate'] != "0" && $input['start_date_projectStartDate'] != "") {
      $start_date_report = $input['start_date_projectStartDate'];
      if($data['projectStartDate'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_projectStartDate'] != "0" && $input['end_date_projectStartDate'] != "") {
      $end_date_report = $input['end_date_projectStartDate'];
      if($data['projectStartDate'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['start_date_projectEndDate'] != "0" && $input['start_date_projectEndDate'] != "") {
      $start_date_report = $input['start_date_projectEndDate'];
      if($data['projectEndDate'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_projectEndDate'] != "0" && $input['end_date_projectEndDate'] != "") {
      $end_date_report = $input['end_date_projectEndDate'];
      if($data['projectEndDate'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    //Credit Issue Date
    if($thisInclude && $input['start_date_creditIssueDate'] != "0" && $input['start_date_creditIssueDate'] != "") {
      $start_date_report = $input['start_date_creditIssueDate'];
      if($data['IssueDate'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_creditIssueDate'] != "0" && $input['end_date_creditIssueDate'] != "") {
      $end_date_report = $input['end_date_creditIssueDate'];
      if($data['IssueDate'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    //Initial Cert Dates
    if($thisInclude && $input['start_date_certInitialDate'] != "0" && $input['start_date_certInitialDate'] != "") {
      $start_date_report = $input['start_date_certInitialDate'];
      if($data['est_initial_cert_dt'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_certInitialDate'] != "0" && $input['end_date_certInitialDate'] != "") {
      $end_date_report = $input['end_date_certInitialDate'];
      if($data['est_initial_cert_dt'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    //Final Cert Dates
    if($thisInclude && $input['start_date_certFinalDate'] != "0" && $input['start_date_certFinalDate'] != "") {
      $start_date_report = $input['start_date_certFinalDate'];
      if($data['est_final_cert_dt'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_certFinalDate'] != "0" && $input['end_date_certFinalDate'] != "") {
      $end_date_report = $input['end_date_certFinalDate'];
      if($data['est_final_cert_dt'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    //Principal Photo
    if($thisInclude && $input['start_date_lastDayPrincPhotoDate'] != "0" && $input['start_date_lastDayPrincPhotoDate'] != "") {
      $start_date_report = $input['start_date_lastDayPrincPhotoDate'];
      if($data['lastDayPrincipalPhotography'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_lastDayPrincPhotoDate'] != "0" && $input['end_date_lastDayPrincPhotoDate'] != "") {
      $end_date_report = $input['end_date_lastDayPrincPhotoDate'];
      if($data['lastDayPrincipalPhotography'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    //Date Loaded
    if($thisInclude && $input['start_date_loadedDate'] != "0" && $input['start_date_loadedDate'] != "") {
      $start_date_report = date('Y-m-d', $input['start_date_loadedDate']);
      if($data['timeStamp'] >= $start_date_report) {
      } else {
        $thisInclude = false;
      }
    }
    if($thisInclude && $input['end_date_loadedDate'] != "0" && $input['end_date_loadedDate'] != "") {
      $end_date_report = date('Y-m-d', $input['end_date_loadedDate']);
      if($data['timeStamp'] <= $end_date_report) {
      } else {
        $thisInclude = false;
      }
    }

    return $thisInclude;

  }



  ///////////////////////////////////
  ////// START ORDER FUNCTIONS //////
  ///////////////////////////////////

  function credit_order($input) {

    if($input['order'] == "updated_n_o") {
      $this->db->order_by("PendingListings.updatedTime DESC");
    } else {
      if($input['order'] == "loaded_n_o") {
        $this->db->order_by("ActiveListings.timestamp DESC");
      } else {
        if($input['order'] == "loaded_o_n") {
          $this->db->order_by("ActiveListings.timestamp ASC");
        } else {
          if($input['order'] == "jurisdiction_a_z") {
            $this->db->order_by("States.name ASC");
          } else {
            if($input['order'] == "jurisdiction_z_a") {
              $this->db->order_by("States.name DESC");
            } else {
              if($input['order'] == "project_a_z") {
                $this->db->order_by("PendingListings.stateCertNum ASC");
              } else {
                if($input['order'] == "project_z_a") {
                  $this->db->order_by("PendingListings.stateCertNum DESC");
                } else {
                  if($input['order'] == "year_h_l") {
                    $this->db->order_by("TaxYear.taxYear DESC");
                  } else {
                    if($input['order'] == "year_l_h") {
                      $this->db->order_by("TaxYear.taxYear ASC");
                    } else {
                      if($input['order'] == "program_a_z") {
                        $this->db->order_by("IncentivePrograms.ProgramName ASC");
                      } else {
                        if($input['order'] == "program_z_a") {
                          $this->db->order_by("IncentivePrograms.ProgramName DESC");
                        } else {
                          if($input['order'] == "type_a_z") {
                            $this->db->order_by("ProgramType.ProgramTypeName ASC");
                          } else {
                            if($input['order'] == "type_z_a") {
                              $this->db->order_by("ProgramType.ProgramTypeName DESC");
                            } else {
                              if($input['order'] == "orig_amt_l_h") {
                                $this->db->order_by("ActiveListings.creditAmount ASC");
                              } else {
                                if($input['order'] == "orig_amt_h_l") {
                                  $this->db->order_by("ActiveListings.creditAmount DESC");
                                } else {
                                  if($input['order'] == "trade_date_o_n") {
                                    $this->db->order_by("Trades.timeStamp ASC");
                                  } else {
                                    if($input['order'] == "trade_date_n_o") {
                                      $this->db->order_by("Trades.timeStamp DESC");
                                    } else {
                                      if($input['order'] == "trade_amt_l_h") {
                                        $this->db->order_by("Trades.tradeSize ASC");
                                      } else {
                                        if($input['order'] == "trade_amt_h_l") {
                                          $this->db->order_by("Trades.tradeSize DESC");
                                        } else {
                                          if($input['order'] == "seller_a_z") {
                                            $this->db->order_by("Accounts.companyName ASC");
                                          } else {
                                            if($input['order'] == "seller_z_a") {
                                              $this->db->order_by("Accounts.companyName DESC");
                                            } else {
                                              if($input['order'] == "shared_n_o") {
                                                $this->db->order_by("Shares.sTimeStamp DESC");
                                              } else {
                                                if($input['order'] == "shared_o_n") {
                                                  $this->db->order_by("Shares.sTimeStamp ASC");
                                                } else {
                                                  $this->db->order_by("States.name ASC");
                                                }
                                              }
                                            }
                                          }
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

  }

  function cache_credit_order($input, $records) {

    if($input['order'] == "updated_n_o") {
      usort($records, function($a, $b) {
        return strcmp($b["updatedTime"], $a["updatedTime"]);
      });
      //$this->db->order_by("PendingListings.updatedTime DESC");
    } else {
      if($input['order'] == "loaded_n_o") {
        usort($records, function($a, $b) {
          return strcmp($b["timestamp"], $a["timestamp"]);
        });
        //$this->db->order_by("ActiveListings.timestamp DESC");
      } else {
        if($input['order'] == "loaded_o_n") {
          usort($records, function($a, $b) {
            return strcmp($a["timestamp"], $b["timestamp"]);
          });
          //$this->db->order_by("ActiveListings.timestamp ASC");
        } else {
          if($input['order'] == "jurisdiction_a_z") {
            usort($records, function($a, $b) {
              return strcmp(strtolower($a["name"]), strtolower($b["name"]));
            });
            //$this->db->order_by("States.name ASC");
          } else {
            if($input['order'] == "jurisdiction_z_a") {
              usort($records, function($a, $b) {
                return strcmp(strtolower($b["name"]), strtolower($a["name"]));
              });
              //$this->db->order_by("States.name DESC");
            } else {
              if($input['order'] == "project_a_z") {
                usort($records, function($a, $b) {
                  return strcmp(strtolower($a["stateCertNum"]), strtolower($b["stateCertNum"]));
                });
                //$this->db->order_by("PendingListings.stateCertNum ASC");
              } else {
                if($input['order'] == "project_z_a") {
                  usort($records, function($a, $b) {
                    return strcmp(strtolower($b["stateCertNum"]), strtolower($a["stateCertNum"]));
                  });
                  //$this->db->order_by("PendingListings.stateCertNum DESC");
                } else {
                  if($input['order'] == "year_l_h") {
                    usort($records, function($a, $b) {
                      return strcmp($a["taxYear"], $b["taxYear"]);
                    });
                  } else {
                    if($input['order'] == "year_h_l") {
                      usort($records, function($a, $b) {
                        return strcmp($b["taxYear"], $a["taxYear"]);
                      });
                    } else {
                      if($input['order'] == "program_a_z") {
                        usort($records, function($a, $b) {
                          return strcmp(strtolower($a["ProgramName"]), strtolower($b["ProgramName"]));
                        });
                        //$this->db->order_by("IncentivePrograms.ProgramName ASC");
                      } else {
                        if($input['order'] == "program_z_a") {
                          usort($records, function($a, $b) {
                            return strcmp(strtolower($b["ProgramName"]), strtolower($a["ProgramName"]));
                          });
                          //$this->db->order_by("IncentivePrograms.ProgramName DESC");
                        } else {
                          if($input['order'] == "type_a_z") {
                            usort($records, function($a, $b) {
                              return strcmp(strtolower($a["ProgramTypeName"]), strtolower($b["ProgramTypeName"]));
                            });
                            //$this->db->order_by("ProgramType.ProgramTypeName ASC");
                          } else {
                            if($input['order'] == "type_z_a") {
                              usort($records, function($a, $b) {
                                return strcmp(strtolower($b["ProgramTypeName"]), strtolower($a["ProgramTypeName"]));
                              });
                              //$this->db->order_by("ProgramType.ProgramTypeName DESC");
                            } else {
                              if($input['order'] == "orig_amt_l_h") {
                                usort($records, function($a, $b) {
                                  return $a['creditAmount'] - $b['creditAmount'];
                                });
                                //$this->db->order_by("ActiveListings.creditAmount ASC");
                              } else {
                                if($input['order'] == "orig_amt_h_l") {
                                  usort($records, function($a, $b) {
                                    return $b['creditAmount'] - $a['creditAmount'];
                                  });
                                  //$this->db->order_by("ActiveListings.creditAmount DESC");
                                } else {
                                  if($input['order'] == "trade_date_o_n") {
                                    usort($records, function($a, $b) {
                                      return strcmp($a["timeStamp"], $b["timeStamp"]);
                                    });
                                    //$this->db->order_by("Trades.timeStamp ASC");
                                  } else {
                                    if($input['order'] == "trade_date_n_o") {
                                      usort($records, function($a, $b) {
                                        return strcmp($b["timeStamp"], $a["timeStamp"]);
                                      });
                                      //$this->db->order_by("Trades.timeStamp DESC");
                                    } else {
                                      if($input['order'] == "trade_amt_l_h") {
                                        usort($records, function($a, $b) {
                                          return $a['tradeSize'] - $b['tradeSize'];
                                        });
                                        //$this->db->order_by("Trades.tradeSize ASC");
                                      } else {
                                        if($input['order'] == "trade_amt_h_l") {
                                          usort($records, function($a, $b) {
                                            return $b['tradeSize'] - $a['tradeSize'];
                                          });
                                          //$this->db->order_by("Trades.tradeSize DESC");
                                        } else {
                                          if($input['order'] == "seller_a_z") {
                                            usort($records, function($a, $b) {
                                              return strcmp(strtolower($a["companyName"]), strtolower($b["companyName"]));
                                            });
                                            //$this->db->order_by("Accounts.companyName ASC");
                                          } else {
                                            if($input['order'] == "seller_z_a") {
                                              usort($records, function($a, $b) {
                                                return strcmp(strtolower($b["companyName"]), strtolower($a["companyName"]));
                                              });
                                              //$this->db->order_by("Accounts.companyName DESC");
                                            } else {
                                              if($input['order'] == "shared_n_o") {
                                                usort($records, function($a, $b) {
                                                  return strcmp($a["sTimeStamp"], $b["sTimeStamp"]);
                                                });
                                                //$this->db->order_by("Shares.sTimeStamp DESC");
                                              } else {
                                                if($input['order'] == "shared_o_n") {
                                                  usort($records, function($a, $b) {
                                                    return strcmp($b["sTimeStamp"], $a["sTimeStamp"]);
                                                  });
                                                  //$this->db->order_by("Shares.sTimeStamp ASC");
                                                } else {
                                                  usort($records, function($a, $b) {
                                                    return strcmp(strtolower($a["name"]), strtolower($b["name"]));
                                                  });
                                                  //$this->db->order_by("States.name ASC");
                                                }
                                              }
                                            }
                                          }
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    return $records;

  }

  function getSummaryFields_auditStartDate() {

    $return['auditStartDate'] = [];
    $return['auditStartDate']['auditStartDateTotalCount'] = 0;
    $return['auditStartDate']['auditStartDateTotalAmount'] = 0;
    $return['auditStartDate']['auditStartDateTotalNotIncludedCount'] = 0;
    $return['auditStartDate']['auditStartDateTotalNotIncludedAmount'] = 0;

    return $return;

  }




  //////////////////////////////////////
  ////// START LOOP FUNCTIONS //////
  //////////////////////////////////////

  function processStatusAnalytics($data, $return, $inputConfigs, $skipSummary = "") {

    $fieldName = $inputConfigs['fieldName'];
    $fieldNameDB = $inputConfigs['fieldNameDB'];

    $fieldNameTotalCount = $fieldNameDB . 'TotalCount';
    $fieldNameTotalAmount = $fieldNameDB . 'TotalAmount';
    $fieldNameTotalNotIncludedCount = $fieldNameDB . 'TotalNotIncludedCount';
    $fieldNameTotalNotIncludedAmount = $fieldNameDB . 'TotalNotIncludedAmount';

    if(!isset($return['summary'][$fieldNameDB])) {
      $return['summary'][$fieldNameDB] = [];
    }
    if(!isset($return['summary'][$fieldNameTotalCount])) {
      $return['summary'][$fieldNameTotalCount] = 0;
    }
    if(!isset($return['summary'][$fieldNameTotalAmount])) {
      $return['summary'][$fieldNameTotalAmount] = 0;
    }
    if(!isset($return['summary'][$fieldNameTotalNotIncludedCount])) {
      $return['summary'][$fieldNameTotalNotIncludedCount] = 0;
    }
    if(!isset($return['summary'][$fieldNameTotalNotIncludedAmount])) {
      $return['summary'][$fieldNameTotalNotIncludedAmount] = 0;
    }

    if(isset($data[$fieldName]) && $data[$fieldName] != "" && (!is_numeric($data[$fieldName]) || $data[$fieldName] != 0)) {
      if($skipSummary == "") {
        if(array_key_exists($data[$fieldName], $return['summary'][$fieldNameDB])) {
          $return['summary'][$fieldNameDB][$data[$fieldName]]['count']++;
          $return['summary'][$fieldNameDB][$data[$fieldName]]['amount'] += $data['amountUSD'];
        } else {
          $return['summary'][$fieldNameDB][$data[$fieldName]]['count'] = 1;
          $return['summary'][$fieldNameDB][$data[$fieldName]]['amount'] = $data['amountUSD'];
        }
        $return['summary'][$fieldNameTotalCount]++;
        $return['summary'][$fieldNameTotalAmount] += $data['amountUSD'];
      }
      $data['flags'][$fieldNameDB] = $data[$fieldName];
    } else {
      if($skipSummary == "") {
        $return['summary'][$fieldNameTotalNotIncludedCount]++;
        $return['summary'][$fieldNameTotalNotIncludedAmount] += $data['amountUSD'];
      }
      $data['flags'][$fieldNameDB] = 0;
    }

    $dataProcessed['data'] = $data;
    $dataProcessed['return'] = $return;

    return $dataProcessed;

  }

  function creditDatesProcessed($data, $return, $skipSummary = "") {

    if($data['auditStartDate'] > 0) {
      if($skipSummary == "") {
        $return['summary']['auditStartDate']['auditStartDateTotalCount']++;
        $return['summary']['auditStartDate']['auditStartDateTotalAmount'] += $data['amountUSD'];
      }
      $data['flags']['auditStartDate'] = $data['auditStartDate'];
    } else {
      if($skipSummary == "") {
        $return['summary']['auditStartDate']['auditStartDateTotalNotIncludedCount']++;
        $return['summary']['auditStartDate']['auditStartDateTotalNotIncludedAmount'] += $data['amountUSD'];
      }
      $data['flags']['auditStartDate'] = 0;
    }

    $dataProcessed['data'] = $data;
    $dataProcessed['return'] = $return;

    return $dataProcessed;

  }





  //////////////////////////////////////
  ////// START COMPLETE FUNCTIONS //////
  //////////////////////////////////////

  function get_credit_amount_process_data($input) {
    $data['budgetExchangeRate'] = (isset($input['budgetExchangeRate']) && $input['budgetExchangeRate'] > 0) ? (float)$input['budgetExchangeRate'] : 1;
    $data['amountUSD'] = (isset($input['amountLocal']) && $input['amountLocal'] > 0) ? round($input['amountLocal'] * $input['budgetExchangeRate'], 2) : 0;
    $data['amountUSDRemaining'] = (isset($input['amountLocalRemaining']) && $input['amountLocalRemaining'] > 0) ? round($input['amountLocalRemaining'] * $input['budgetExchangeRate'], 2) : 0;
    if(isset($input['amountLocal'])) {
      $data['amountLocal'] = round($input['amountLocal'], 2);
    }
    if(isset($input['amountLocalRemaining'])) {
      $data['amountLocalRemaining'] = round($input['amountLocalRemaining'], 2);
    }
    if(isset($input['estCreditPrice'])) {
      $data['amountValueEstimateUSD'] = $data['amountUSD'] * $input['estCreditPrice'];
      $data['amountValueEstimateLocal'] = $input['amountLocal'] * $input['estCreditPrice'];
    }

    return $data;
  }

  function prepareFilterData() {

    //A few default values
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
      $data['search'] = true;
    } else {
      $data['search'] = false;
    }

    //Santize data
    $data['status'] = isset($_POST['listStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listStatus')) : null;
    $data['cOrigin'] = isset($_POST['listOrigin']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listOrigin')) : null;
    $data['actions'] = isset($_POST['listActions']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listActions')) : null;
    $data['type'] = isset($_POST['listTypes']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listTypes')) : null;
    $data['projects'] = isset($_POST['listProjects']) ? $this->input->post('listProjects') : null;
    $data['listingId'] = isset($_POST['listListingId']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listListingId')) : null;
    $data['jurisdictions'] = isset($_POST['listJurisdictions']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listJurisdictions')) : null;
    $data['domesticOrInternational'] = isset($_POST['domesticOrInternational']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('domesticOrInternational')) : null;
    $data['countries'] = isset($_POST['listCountries']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listCountries')) : null;
    $data['provinces'] = isset($_POST['listProvinces']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listProvinces')) : null;
    $data['counties'] = isset($_POST['listCounties']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listCounties')) : null;
    $data['towns'] = isset($_POST['listTowns']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listTowns')) : null;
    $data['incentiveprogram'] = isset($_POST['listIncentivePrograms']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listIncentivePrograms')) : null;
    $data['programId'] = isset($_POST['listPrograms']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listPrograms')) : null;
    $data['taxyears'] = isset($_POST['listTaxyears']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listTaxyears')) : null;
    $data['complianceIsConfigured'] = isset($_POST['complianceIsConfigured']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('complianceIsConfigured')) : null;
    //Status
    $data['showArchived'] = isset($_POST['listShowArchived']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listShowArchived')) : null;
    $data['certStatus'] = isset($_POST['listCertStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listCertStatus')) : null;
    $data['monetizationStatus'] = isset($_POST['listMonetizationStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listMonetizationStatus')) : null;
    $data['auditStatus'] = isset($_POST['listAuditStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listAuditStatus')) : null;
    $data['projectStatus'] = isset($_POST['listProjectStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listProjectStatus')) : null;
    //Shared Account is by default NULL. If a shared or advisor account filters the list to see their own credits, then it is "Self". If filtering by a specific shared account, then it is the shared account ID.
    $data['taxEntities'] = isset($_POST['listTaxEntities']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listTaxEntities')) : null;
    $data['sharedAccount'] = isset($_POST['listSharedAccounts']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listSharedAccounts')) : null;
    $data['advisorStatus'] = isset($_POST['listAdvisorStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listAdvisorStatus')) : null;
    $data['customerAccessStatus'] = isset($_POST['listCustomerAccessStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listCustomerAccessStatus')) : null;
    $data['creditUtilizationStatus'] = isset($_POST['listCreditUtilizationStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listCreditUtilizationStatus')) : null;
    $data['advisorRepresentative'] = isset($_POST['listAdvisorRepresentative']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listAdvisorRepresentative')) : null;
    $data['advisorProjectAssignedTo'] = isset($_POST['listAdvisorProjectAssignedTo']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listAdvisorProjectAssignedTo')) : null;

    //utilizations
    $data['utilizationStatus'] = isset($_POST['listUtilizationStatus']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listUtilizationStatus')) : null;
    $data['utilizationTypes'] = isset($_POST['listUtilizationTypes']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('listUtilizationTypes')) : null;
    //basics
    $data['order'] = isset($_POST['order']) ? preg_replace("/[^a-zA-Z0-9_,+]+/", "", $this->input->post('order')) : null;
    //Dates
    $data['start_date_utilizationDate'] = isset($_POST['start_date_utilizationDate']) ? strtotime($this->input->post('start_date_utilizationDate')) : null;
    $data['end_date_utilizationDate'] = isset($_POST['end_date_utilizationDate']) ? strtotime($this->input->post('end_date_utilizationDate')) : null;

    $data['start_date_auditStartDate'] = isset($_POST['start_date_auditStartDate']) ? strtotime($this->input->post('start_date_auditStartDate')) : null;
    $data['end_date_auditStartDate'] = isset($_POST['end_date_auditStartDate']) ? strtotime($this->input->post('end_date_auditStartDate')) : null;
    $data['start_date_auditEndDate'] = isset($_POST['start_date_auditEndDate']) ? strtotime($this->input->post('start_date_auditEndDate')) : null;
    $data['end_date_auditEndDate'] = isset($_POST['end_date_auditEndDate']) ? strtotime($this->input->post('end_date_auditEndDate')) : null;

    $data['start_date_projectStartDate'] = isset($_POST['start_date_projectStartDate']) ? strtotime($this->input->post('start_date_projectStartDate')) : null;
    $data['end_date_projectStartDate'] = isset($_POST['end_date_projectStartDate']) ? strtotime($this->input->post('end_date_projectStartDate')) : null;
    $data['start_date_projectEndDate'] = isset($_POST['start_date_projectEndDate']) ? strtotime($this->input->post('start_date_projectEndDate')) : null;
    $data['end_date_projectEndDate'] = isset($_POST['end_date_projectEndDate']) ? strtotime($this->input->post('end_date_projectEndDate')) : null;

    $data['start_date_creditIssueDate'] = isset($_POST['start_date_creditIssueDate']) ? strtotime($this->input->post('start_date_creditIssueDate')) : null;
    $data['end_date_creditIssueDate'] = isset($_POST['end_date_creditIssueDate']) ? strtotime($this->input->post('end_date_creditIssueDate')) : null;

    $data['start_date_certFinalDate'] = isset($_POST['start_date_certFinalDate']) ? strtotime($this->input->post('start_date_certFinalDate')) : null;
    $data['end_date_certFinalDate'] = isset($_POST['end_date_certFinalDate']) ? strtotime($this->input->post('end_date_certFinalDate')) : null;

    $data['start_date_certInitialDate'] = isset($_POST['start_date_certInitialDate']) ? strtotime($this->input->post('start_date_certInitialDate')) : null;
    $data['end_date_certInitialDate'] = isset($_POST['end_date_certInitialDate']) ? strtotime($this->input->post('end_date_certInitialDate')) : null;

    $data['start_date_lastDayPrincPhotoDate'] = isset($_POST['start_date_lastDayPrincPhotoDate']) ? strtotime($this->input->post('start_date_lastDayPrincPhotoDate')) : null;
    $data['end_date_lastDayPrincPhotoDate'] = isset($_POST['end_date_lastDayPrincPhotoDate']) ? strtotime($this->input->post('end_date_lastDayPrincPhotoDate')) : null;

    $data['start_date_loadedDate'] = isset($_POST['start_date_loadedDate']) ? strtotime($this->input->post('start_date_loadedDate')) : null;
    $data['end_date_loadedDate'] = isset($_POST['end_date_loadedDate']) ? strtotime($this->input->post('end_date_loadedDate')) : null;

    return $data;

  }

  function buildFilterSearchData($data) {

    $cData['cache'] = false; //REMOVE - this was prior to AWS Redis Elasticache

    $cData['id'] = ($data['sharedAccount'] == "self") ? $this->cisession->userdata('primUserId') : null;
    $cData['dmaId'] = isset($data['dmaId']) ? $data['dmaId'] : $this->cisession->userdata('dmaId');
    $cData['dmaMemberId'] = isset($data['dmaMemberId']) ? $data['dmaMemberId'] : $this->cisession->userdata('dmaMemberId');
    //$cData['account'] = $data['sharedAccount'];
    //$cData['sharedAccount'] = $data['sharedAccount'];
    //$cData['shared'] = ($data['sharedAccount']!="") ? ($data['sharedAccount']!="self" ? $this->cisession->userdata('primUserId') : NULL) : NULL;
    $cData['taxEntities'] = $data['taxEntities'];
    $cData['sharedAccount'] = $data['sharedAccount'];
    $cData['getShareData'] = ($this->cisession->userdata('dmaType') == "advisor") ? true : false;
    $cData['order'] = isset($data['order']) ? $data['order'] : null;
    $cData['status'] = $data['status'];
    $cData['cOrigin'] = $data['cOrigin'];
    $cData['actions'] = $data['actions'];
    $cData['type'] = $data['type'];
    $cData['projects'] = ($data['projects'] != "") ? $data['projects'] : "0";
    //$cData['projects'] = ($data['projects']!="") ? urldecode($data['projects']) : "0";
    $cData['listingId'] = $data['listingId'];
    $cData['jurisdictions'] = $data['jurisdictions'];
    $cData['domesticOrInternational'] = $data['domesticOrInternational'];
    $cData['countries'] = $data['countries'];
    $cData['provinces'] = $data['provinces'];
    $cData['counties'] = $data['counties'];
    $cData['towns'] = $data['towns'];
    $cData['incentiveprogram'] = $data['incentiveprogram'];
    $cData['programId'] = $data['programId'];
    $cData['taxyears'] = $data['taxyears'];
    $cData['showArchived'] = $data['showArchived'];
    $cData['certStatus'] = $data['certStatus'];
    $cData['monetizationStatus'] = $data['monetizationStatus'];
    $cData['auditStatus'] = $data['auditStatus'];
    $cData['projectStatus'] = $data['projectStatus'];
    $cData['creditUtilizationStatus'] = $data['creditUtilizationStatus'];
    $cData['utilizationStatus'] = $data['utilizationStatus'];
    $cData['utilizationTypes'] = $data['utilizationTypes'];
    $cData['view'] = "";
    $cData['start_date_report'] = "";
    $cData['end_date_report'] = "";
    $cData['advisorStatus'] = "";
    $cData['countryId'] = "";
    $cData['loanType'] = "";
    $cData['includeWorkflows'] = "";
    //If Advisor
    $cData['advisorStatus'] = $data['advisorStatus'];
    $cData['customerAccessStatus'] = $data['customerAccessStatus'];
    $cData['advisorRepresentative'] = $data['advisorRepresentative'];
    $cData['advisorProjectAssignedTo'] = $data['advisorProjectAssignedTo'];
    //Dates
    $cData['start_date_utilizationDate'] = $data['start_date_utilizationDate'];
    $cData['end_date_utilizationDate'] = $data['end_date_utilizationDate'];

    $cData['start_date_auditStartDate'] = $data['start_date_auditStartDate'];
    $cData['end_date_auditStartDate'] = $data['end_date_auditStartDate'];
    $cData['start_date_auditEndDate'] = $data['start_date_auditEndDate'];
    $cData['end_date_auditEndDate'] = $data['end_date_auditEndDate'];

    $cData['start_date_projectStartDate'] = $data['start_date_projectStartDate'];
    $cData['end_date_projectStartDate'] = $data['end_date_projectStartDate'];
    $cData['start_date_projectEndDate'] = $data['start_date_projectEndDate'];
    $cData['end_date_projectEndDate'] = $data['end_date_projectEndDate'];

    $cData['start_date_creditIssueDate'] = $data['start_date_creditIssueDate'];
    $cData['end_date_creditIssueDate'] = $data['end_date_creditIssueDate'];

    $cData['start_date_certFinalDate'] = $data['start_date_certFinalDate'];
    $cData['end_date_certFinalDate'] = $data['end_date_certFinalDate'];

    $cData['start_date_certInitialDate'] = $data['start_date_certInitialDate'];
    $cData['end_date_certInitialDate'] = $data['end_date_certInitialDate'];

    $cData['start_date_lastDayPrincPhotoDate'] = $data['start_date_lastDayPrincPhotoDate'];
    $cData['end_date_lastDayPrincPhotoDate'] = $data['end_date_lastDayPrincPhotoDate'];

    $cData['start_date_loadedDate'] = $data['start_date_loadedDate'];
    $cData['end_date_loadedDate'] = $data['end_date_loadedDate'];

    return $cData;

  }

  function reduce_credit_remaining($listingId, $tradeSize) {

    $credit = $this->get_credit_private($listingId);

    $newAvailableToList = $credit['amountLocalRemaining']; // This value already factors in the NEW trade we just did so you don't need to add it
    if($newAvailableToList > 0) {
    } else {
      $newAvailableToList = 0;
    }

    if($newAvailableToList > 0) {
      $data2 = [
          'availableToList' => $newAvailableToList,
          'updatedTime'     => date('Y-m-d H:i:s'),
      ];
    } else {
      $data2 = [
          'availableToList' => $newAvailableToList,
          'status'          => 9,
          'updatedTime'     => date('Y-m-d H:i:s'),
      ];
    }

    //Update Pending Listing
    $this->db->where('listingId', $listingId);
    $this->db->update('PendingListings', $data2);

    return $this->db->affected_rows() > 0;

  }

  function update_credit_amount_from_trade_modification($listingId, $action = "", $creditAmountDelta = "") {

    $creditBefore = $this->get_credit_private($listingId);
    //We already updated the trade ahead of this function, so this number accounts for that already
    $newAvailableToList = $creditBefore['calculatedAmountRemaining'];

    //If credit is on a Deal Room
    if($creditBefore['listedStatus'] == 'listed' || $creditBefore['listedStatus'] == 'expired' || $creditBefore['listedStatus'] == 'pending') {
      if($action == "increase") {
        $offerSize = $creditBefore['offerSize'] + $creditAmountDelta;
        $originalOfferSize = $creditBefore['originalOfferSize'] + $creditAmountDelta;
      } else {
        if($action == "decrease") {
          $offerSize = $creditBefore['offerSize'] - $creditAmountDelta;
          $originalOfferSize = $creditBefore['originalOfferSize'] - $creditAmountDelta;
        }
      }
    } else {
      $offerSize = $creditBefore['offerSize'];
      $originalOfferSize = $creditBefore['originalOfferSize'];
    }

    $data = [
        'offerSize'         => $offerSize,
        'originalOfferSize' => $originalOfferSize,
        'updatedTime'       => date('Y-m-d H:i:s'),
        'updatedAdminBy'    => $this->cisession->userdata('auserId'),
        'updatedBy'         => $this->cisession->userdata('userId'),

    ];

    //Update Active listing
    $this->db->where('listingId', $listingId);
    $this->db->update('ActiveListings', $data);

    if($newAvailableToList > 0) {
      $data2 = [
          'availableToList' => $newAvailableToList,
          'updatedTime'     => date('Y-m-d H:i:s'),
      ];
    } else {
      $data2 = [
          'availableToList' => $newAvailableToList,
          'status'          => 9,
          'updatedTime'     => date('Y-m-d H:i:s'),
      ];
    }

    //Update Pending Listing
    $this->db->where('listingId', $listingId);
    $this->db->update('PendingListings', $data2);

    return $this->db->affected_rows() > 0;

  }

  function get_credit_id_by_internal_id($input) {
    $this->db->select('PendingListings.listingId');
    $this->db->where('PendingListings.internalId', $input['internalId']);
    $this->credit_where_access($input);
    $this->db->from('PendingListings');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');

    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0]['listingId'];
    }

  }

  function get_adjustment($request) {

    $this->db->select('adjustment.*');
    $this->db->from('adjustment');
    $this->db->where('adjustment.credit_id', $request['credit_id']);
    if(isset($request['dpKey'])) {
      $this->db->where('dataPoints.dpKey', $request['dpKey']);
    }
    $this->db->join("dataPoints", "adjustment.data_point_id = dataPoints.dpId", 'left');
    if(isset($request['limit'])) {
      $this->db->limit($request['limit']);
    }
    if(isset($request['order'])) {
      $this->db->order_by('created_at ' . $request['order']);
    }
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }
    if(count($return) > 0) {
      return $return[0];
    }

  }

  /* SHARED FUNCTION */
  function get_credit_private($id) {

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;
    $q3 = \OIX\Services\JurisdictionService::$jurisdiciton_google_place_id_query;
    $q4 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_latitude_query;
    $q5 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_longitude_query;

    $select = "PendingListings.listingId, PendingListings.timeStamp, PendingListings.estCreditPrice, ActiveListings.timeStamp as dateUploaded, ActiveListings.listingDate, ActiveListings.blockSellerSignatureAlerts, PendingListings.OIXIncentiveId, PendingListings.creditAmount, PendingListings.creditAmount as amountLocal,  PendingListings.budgetExchangeRate, ActiveListings.offerSize, ActiveListings.listingSubmittedDate, ActiveListings.offerPrice, PendingListings.allOrNone, PendingListings.certificationNum, PendingListings.initial_estimated_value, PendingListings.cDmaMemberId, PendingListings.IssueDate, PendingListings.cWorkflowId, PendingListings.cComplianceId, PendingListings.cTaxpayerId, PendingListings.OfferGoodUntil, PendingListings.status, PendingListings.projectStatus as projectStatusId, PendingListings.auditStatusId, PendingListings.internalId, PendingListings.CreditUsedForOffset,offsetName, PendingListings.CreditUsedDate, PendingListings.listedDate, PendingListings.listedBy, IncentivePrograms.ProgramName, PendingListings.incrementAmount, PendingListings.requiredSpend, PendingListings.requiredComplianceTerm, PendingListings.complianceFirstYear, PendingListings.requiredJobs, PendingListings.complianceNotes, PendingListings.statusMonetization, PendingListings.statusNotes, PendingListings.cOrigin, PendingListings.finalCreditAmountFlag, PendingListings.qualifiedExpenditures, PendingListings.incentiveRate, PendingListings.trackingVariance, PendingListings.projectStartDate, PendingListings.projectEndDate, PendingListings.credit_type_id, Accounts.companyName, Accounts.firstName, Accounts.lastName, IPSectors.sector, IPCategories.category,AdministeringAgency, previouslyUsedAmount, PendingListings.creditGoodUntil, PendingListings.taxYearId, PendingListings.projectBudgetEst, PendingListings.estimated_payment_date, PendingListings.lastDayPrincipalPhotography, PendingListings.cNotes, PendingListings.estCreditPrice, PendingListings.cDefaultTransactionWorkflowId, PendingListings.cDefaultTransactionComplianceId, PendingListings.projectNameExt, PendingListings.auditStartDate, PendingListings.auditEndDate, PendingListings.est_final_cert_dt, PendingListings.est_initial_cert_dt, IncentivePrograms.OffSettingTaxList, IncentivePrograms.MandatedPriceFloor, IncentivePrograms.AgencyBuybackPrice, availableToList, PendingListings.availableToList as amountLocalRemaining, IncentivePrograms.NumTimesTransferable, TaxYear.taxYear, stateCertNum, ActiveListings.finalUsableTaxYear, ActiveListings.taxTypeIds, ActiveListings.brokerDmaId, PendingListings.stateCertNum as projectName, stateAccountNum, IncentivePrograms.Status as programStatus, copyrightReg, creditAllocation, stateFile,sellerPermit, PendingListings.encumbered, PendingListings.cOwnerReadOnly, PendingListings.cCustomerAccess, PendingListings.localCurrency, PendingListings.budgetExchangeRate, PendingListings.typeOfWork,ownerTaxId, PendingListings.cArchived, PendingListings.needPS, PendingListings.provisions, PendingListings.ownPS,PendingListings.certificationStatus, ActiveListings.listed, ActiveListings.originalOfferSize, ActiveListings.listingCustomerAssist, ActiveListings.listingAnonymously, ProgramType.ProgramTypeName, cert_status_type.cert_status_name, Taxpayers.taxpayerId, project_status.projectStatus, audit_status.auditStatus, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.dmaAccountId, States.name, (TaxYear.taxYear+PendingListings.cCarryForwardYears) AS maxYears, PendingListings.cCarryForwardYears as CarryForwardYears, PendingListings.legislativeFramework, monetization_status.mnsName, dmaAccounts.profileUrl, dmaAccounts.title, dmaAccounts.dmaId, dmaAccounts.parentDmaId, dmaAccounts.mainAdmin, dmaAccounts.primary_account_id, ActiveListings.listingWireInstructions,PendingListings.purchasedAtPrice, PendingListings.purchasedAtDate, PendingListings.creditIssuedTo";
    $select .= ", $q2 as State, $q1 as jurisdictionName, $q1 AS stateName, $q2 AS state, $q3 AS googlePlaceId, $q2 as parentJurisdictionState, $q1 as parentJurisdictionName, $q4 as sLatitude, $q5 as sLongitude, $q4 as jurisdiction_lat, $q5 as jurisdiction_lng";
    //$select .= ", IncentivePrograms.State, States.name as stateName, States.name as jurisdictionName, States.state, States.googlePlaceId as googlePlaceId, States.status as jurisdictionStatus, States.countryId, countries.name as countryName, countries.code as countryCode, parentJurisdiction.state as parentJurisdictionState, parentJurisdiction.name as parentJurisdictionName, ";
    $select .= ", loc_c.id as countryId, loc_c.name as countryName, loc_c.code as countryCode, loc_p.id as provinceId, loc_p.name as provinceName, loc_co.id as countyId, loc_co.name as countyName, loc_t.id as townId, loc_t.name as townName";
    $this->db->select($select, false);

    $this->db->where('PendingListings.listingId', $id);
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->from('PendingListings');
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts", "Accounts.userId = dmaAccounts.primary_account_id", 'left');
    $this->db->join("IPCategories", "IPCategories.id = IncentivePrograms.Category", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("TaxYear", "PendingListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("States as parentJurisdiction", "States.parentStateId = parentJurisdiction.id", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("Offsets", "PendingListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
    $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');

    $query = $this->db->get();
    $return = [];

    foreach($query->result_array() as $data) {

      $dpRequest['dpDmaIdCustom'] = $data['listedBy'];
      $dpRequest['listingId'] = $data['listingId'];
      $dpRequest['dpObjectType'] = 'credit';
      $customDataPoints = $this->get_data_points($dpRequest);
      $data['customDataPoints'] = $customDataPoints['dataPoints'];

      //Credit estimate
      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Get most recent adjustment - credit amount
      $adjRequest['credit_id'] = $data['listingId'];
      $adjRequest['dpKey'] = 'fieldCreditAmountGrossLocal';
      $adjRequest['limit'] = 1;
      $adjRequest['order'] = "DESC";
      $adjustment = $this->get_adjustment($adjRequest);
      $data['last_adjustment'] = $adjustment;

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : $data['projectName'];

      $data['creditAmountStatus'] = ($data['finalCreditAmountFlag'] == 1) ? "Final" : "Estimate";

      $data['submittedDate'] = date("m/d/y", strtotime($data['timeStamp']));
      $data['agency1ConfirmDate'] = (!empty($data['agencyVerificationStatus'])) ? $data['agencyVerificationStatus'] : "N/A";
      $data['agency2ConfirmDate'] = (!empty($data['certificateVerificationStatus'])) ? $data['certificateVerificationStatus'] : "N/A";
      $data['OPSDate'] = (!empty($data['approvedByOpsDate'])) ? $data['approvedByOpsDate'] : "N/A";
      $data['CFODate'] = (!empty($data['approvedByEYDate'])) ? $data['approvedByEYDate'] : "N/A";
      $data['CEODate'] = (!empty($data['listedDate'])) ? $data['listedDate'] : "N/A";

      $data['submittedStatus'] = (($data['submittedDate']) !== "N/A") ? "<span class=\"check-symbol\">&#x2713;</span>" : "<span class=\"no-symbol\">&#x20E0;</span>";
      $data['agency1ConfirmStatus'] = (($data['agency1ConfirmDate']) !== "N/A") ? "<span class=\"check-symbol\">&#x2713;</span>" : "<span class=\"no-symbol\">&#x20E0;</span>";
      $data['agency2ConfirmStatus'] = (($data['agency2ConfirmDate']) !== "N/A") ? "<span class=\"check-symbol\">&#x2713;</span>" : "<span class=\"no-symbol\">&#x20E0;</span>";
      $data['OPSStatus'] = (($data['OPSDate']) !== "N/A") ? "<span class=\"check-symbol\">&#x2713;</span>" : "<span class=\"no-symbol\">&#x20E0;</span>";
      $data['CFOStatus'] = (($data['CFODate']) !== "N/A") ? "<span class=\"check-symbol\">&#x2713;</span>" : "<span class=\"no-symbol\">&#x20E0;</span>";
      $data['CEOStatus'] = (($data['CEODate']) !== "N/A") ? "<span class=\"check-symbol\">&#x2713;</span>" : "<span class=\"no-symbol\">&#x20E0;</span>";

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['taxTypeNames'] = $this->get_tax_types($data['taxTypeIds']);

      $data['purchasedAtDate'] = ($data['purchasedAtDate'] != "") ? date('m/d/Y', strtotime($data['purchasedAtDate'])) : null;

      if($data['cDmaMemberId'] > 0) {
        $data['loadedBy'] = $this->Members_Model->get_member_by_id($data['cDmaMemberId']);
      }
      $data['listedByInfo'] = $this->Members_Model->get_member_by_id($data['primary_account_id']);

      $data['creditExpirationDate'] = ($data['taxYear'] > 0 && $data['CarryForwardYears'] > 0) ? "12/31/" . ($data['taxYear'] + $data['CarryForwardYears']) : ($data['taxYear'] > 0 && $data['CarryForwardYears'] == 0 ? "12/31/" . $data['taxYear'] : null);

      $data['complianceLastYear'] = ($data['requiredComplianceTerm'] > 0 && $data['complianceFirstYear'] > 0) ? $data['complianceFirstYear'] + $data['requiredComplianceTerm'] : null;

      if($data['initial_estimated_value'] > 0) {
        //do nothing
      } else {
        //Original Credit Amount Estimate
        $original_estimated_credit_amount = $this->AuditTrail->get_audit_trail($data['listingId'], 2, null, 1);
        $data['estimated_credit_amount'] = 0;
        if(sizeof($original_estimated_credit_amount) > 0) {
          $estimated_credit_amount = preg_replace("/[^0-9]/", "", $original_estimated_credit_amount[0]['audRelVal3After']);
          if($estimated_credit_amount > 0) {
            $data['initial_estimated_value'] = $estimated_credit_amount;
          }
        }
      }
      $data['initial_estimated_value'] = ($data['initial_estimated_value'] > 0) ? $data['initial_estimated_value'] : $data['creditAmount'];

      $data['currencySymbolData'] = $this->currency->get_currency_data($data['localCurrency']);

      //Get total credit used
      $totalUtilized = $this->Trading->get_total_trade_amount_on_listing($data['listingId']);
      $data['totalUtilized'] = $totalUtilized['totalTradeAmount'];
      $data['totalTradeAmount'] = $data['totalUtilized'];
      $data['totalCreditUsed'] = $data['totalUtilized'];
      //Get total estimated utilizations
      $estimatedUtilizations = $this->Trading->get_trades_on_listing($data['listingId'], "", 2);
      $data['utilizationsEstimated'] = $estimatedUtilizations['trades'];
      $data['totalEstimatedUtilizationUSD'] = $estimatedUtilizations['summary']['estimated']['faceValue'];
      $data['utilizationEstimatedSummary'] = $estimatedUtilizations['summary'];

      ///Actual utilizations
      $trades = $this->Trading->get_trades_on_listing($data['listingId']);
      $data['utilizations'] = $trades['trades'];
      $data['utilizationActualSummary'] = $trades['summary'];

      //Process utilization data
      $data['trades'] = $this->Trading->get_trades_lite($data['listingId'], "all", "", 1);
      $data = $this->process_utilizations_amount_data($data);

      $data['offerPriceDecimal'] = $data['offerPrice'];
      $data['offerPrice'] = ($data['offerPrice']) * $data['offerSize'];
      if($data['offerSize'] != 0) {
        $data['offerPcnt'] = $data['offerPrice'] / $data['offerSize'];
      } else {
        $data['offerPcnt'] = 0;
      }

      if($data['status'] == 9 || $data['availableToList'] == 0) {
        $data['listedStatus'] = 'sold';
      } else {
        if($data['status'] == 5) {
          $data['listedStatus'] = 'managing';
        } else {
          if($data['status'] == 3) {
            if($data['listed'] == null || $data['listed'] == 0) {
              $data['listedStatus'] = 'pending';
            } else {
              if($data['OfferGoodUntil'] > 0 && $data['OfferGoodUntil'] < time()) {
                $data['listedStatus'] = 'expired';
              } else {
                $data['listedStatus'] = 'listed';
              }
            }
          }
        }
      }

      if($data['needPS'] == 0) {
        $data['psaStatus'] = 'agreed';
      }
      if($data['needPS'] == 1 && $data['ownPS'] == 0) {
        $data['psaStatus'] = 'negotiate';
      }
      if($data['needPS'] == 1 && $data['ownPS'] == 1) {
        $data['psaStatus'] = 'uploadNegotiate';
      }
      if($data['needPS'] == 2) {
        $data['psaStatus'] = 'uploadLater';
      }
      if($data['needPS'] == 3) {
        $data['psaStatus'] = 'help';
      }

      $data['shares'] = $this->get_shares_of_credit($data['listingId']);

      if($data['taxpayerId'] > 0) {
        $data['tpNameToUse'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . " " . $data['tpLastName'];
      } else {
        $data['taxpayerId'] = 0;
        $data['tpAccountType'] = 1;
        $data['tpCompanyName'] = $data['companyName'];
        $data['tpFirstName'] = $data['companyName'];
        $data['tpLastName'] = '';
        $data['tpNameToUse'] = '';
      }

      //COMPLIANCE STATUS
      $statusAlertCount = 0;
      if($data['certificationStatus'] > 0) {
      } else {
        $statusAlertCount++;
      }
      if($data['statusMonetization'] > 0) {
      } else {
        $statusAlertCount++;
      }
      if($data['auditStatusId'] > 0) {
      } else {
        $statusAlertCount++;
      }
      if($data['projectStatusId'] > 0) {
      } else {
        $statusAlertCount++;
      }
      $data['compliance']['statusAlertCount'] = $statusAlertCount;

      //COMPLIANCE DATES
      $dateAlertCount = 0;
      if($data['est_initial_cert_dt'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($data['est_final_cert_dt'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($data['projectStartDate'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($data['projectEndDate'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($data['IssueDate'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($data['auditStartDate'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($data['auditEndDate'] > 0) {
      } else {
        $dateAlertCount++;
      }
      if($this->cisession->userdata('dmaCategory') == "entertainment") {
        if($data['lastDayPrincipalPhotography'] > 0) {
        } else {
          $dateAlertCount++;
        }
      }
      $data['compliance']['dateAlertCount'] = $dateAlertCount;

      $totalTradeActualAndEstimate = $data['totalTradeAmount'] + $data['totalEstimatedUtilizationUSD'];
      //COMPLIANCE - Analyze estimation percentage status
      $data['compliance']['percentCreditNotUtilizedOrEstimated'] = 100;
      if($data['amountUSDRemaining'] > 0) {

        if($data['amountUSDRemaining'] - $data['totalEstimatedUtilizationUSD'] < 1) {
          $data['compliance']['percentCreditNotUtilizedOrEstimated'] = 0; //If credit fully estimated (might be partial actual utilization)
        } else {
          if($data['totalEstimatedUtilizationUSD'] == 0) {
            if($data['totalCreditUsed'] == 0) {
              $data['compliance']['percentCreditNotUtilizedOrEstimated'] = 100;
            } else {
              $data['compliance']['percentCreditNotUtilizedOrEstimated'] = ceil(100 * $data['amountUSDRemaining'] / $data['amountUSD']);
            }
          } else {
            $data['compliance']['percentCreditNotUtilizedOrEstimated'] = ceil(100 * ($data['amountUSDRemaining'] - $data['totalEstimatedUtilizationUSD']) / $data['amountUSD']);
          }
        }

      } else {
        if($data['creditAmount'] == 0) {
          $data['compliance']['percentCreditNotUtilizedOrEstimated'] = 100;
        } else {
          $data['compliance']['percentCreditNotUtilizedOrEstimated'] = 0;
        }
      }

      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_active_listing($id) {

    $this->db->select('ActiveListings.timeStamp,portalName, States.name, States.state,States.countryId, countries.name as countryName, IncentivePrograms.OffSettingTaxList, ProgramType.ProgramTypeName,links, ActiveListings.OIXIncentiveId,ActiveListings.taxYearId, ActiveListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, ActiveListings.offerSize, ActiveListings.offerPrice, ActiveListings.allOrNone, PendingListings.IssueDate, ActiveListings.OfferGoodUntil, ActiveListings.listingSubmittedDate, ActiveListings.CreditUsedForOffset,Offsets.id as OffsetsId,offsetName, PendingListings.cOrigin, ActiveListings.CreditUsedDate, ActiveListings.listedBy, ActiveListings.listed, ActiveListings.listingDate, IncentivePrograms.State, IncentivePrograms.ProgramName, Accounts.companyName, IPSectors.sector, PendingListings.certificationNum, PendingListings.encumbered,ActiveListings.traded,ActiveListings.deleteMarker, IPCategories.category,AdministeringAgency, ActiveListings.creditGoodUntil, ActiveListings.incrementAmount, PendingListings.projectNameExt, PendingListings.cWorkflowId, ActiveListings.taxTypeIds, ActiveListings.finalUsableTaxYear, ActiveListings.listingCustomerAssist, ActiveListings.listingAnonymously, ActiveListings.incrementAmount as minAmount,IncentivePrograms.MandatedPriceFloor,IncentivePrograms.AgencyBuybackPrice, IncentivePrograms.NumTimesTransferable, IncentivePrograms.OffSettingTaxList, HighestBid, HighestBidSize, ActiveListings.listingId, TaxYear.taxYear, (TaxYear.taxYear+PendingListings.cCarryForwardYears) AS maxYears, ActiveListings.originalOfferSize,OffsetLocked,ActiveListings.ownPS,MandatedPriceFloor, cert_status_type.cert_status_name, ActiveListings.updatedTime, PendingListings.lastDayPrincipalPhotography, PendingListings.cNotes, PendingListings.trackingVariance, PendingListings.cDmaMemberId, PendingListings.provisions, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.estCreditPrice, PendingListings.budgetExchangeRate, PendingListings.cDefaultTransactionWorkflowId, PendingListings.cDefaultTransactionComplianceId, PendingListings.internalId, PendingListings.requiredSpend, PendingListings.requiredComplianceTerm, PendingListings.complianceFirstYear, PendingListings.requiredJobs, PendingListings.complianceNotes, PendingListings.statusMonetization, PendingListings.statusNotes, PendingListings.cTaxpayerId, Taxpayers.taxpayerId, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpCompanyName, Taxpayers.tpAccountType, Taxpayers.tpEmailSigner, Taxpayers.tpUserIdSigner, project_status.statusId, project_status.projectStatus, audit_status.auditStatus, PendingListings.auditStartDate, PendingListings.auditEndDate, PendingListings.certificationStatus, PendingListings.qualifiedExpenditures, PendingListings.incentiveRate, PendingListings.localCurrency, PendingListings.est_initial_cert_dt, PendingListings.est_final_cert_dt, PendingListings.projectStartDate, PendingListings.projectEndDate, PendingListings.auditStatusId, PendingListings.projectBudgetEst, PendingListings.estimated_payment_date, PendingListings.typeOfWork, PendingListings.finalCreditAmountFlag, monetization_status.mnsName');
    $this->db->where('ActiveListings.listingId', $id);
    $this->db->from('ActiveListings');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("dmaAccounts", "ActiveListings.listedBy = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts", "Accounts.userId = dmaAccounts.primary_account_id", 'left');
    $this->db->join("IPCategories", "IPCategories.id = IncentivePrograms.Category", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("project_status", "project_status.statusId = PendingListings.projectStatus", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("remote_links", "ActiveListings.listingId = remote_links.listing_id", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['taxTypeNames'] = $this->get_tax_types($data['taxTypeIds']);

      if($data['listedBy'] != "") {
        $data['sellerDmaName'] = $this->Members_Model->getUserCompanyById($data['listedBy']);
      } else {
        $data['sellerDmaName'] = "";
      }

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['offerPrice'] = ($data['offerPrice']) * $data['offerSize'];
      if($data['offerSize'] > 0) {
        $data['offerPcnt'] = $data['offerPrice'] / $data['offerSize'];
      } else {
        $data['offerPcnt'] = '0';
      }

      $data['complianceLastYear'] = ($data['requiredComplianceTerm'] > 0 && $data['complianceFirstYear'] > 0) ? $data['complianceFirstYear'] + $data['requiredComplianceTerm'] : null;

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  private function process_utilizations_amount_data($data) {

    $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

    $data['amountValueLocal'] = 0;
    $data['amountValueUSD'] = 0;
    $data['totalTradeAmount'] = 0; //this is LOCAL amount
    $data['totalTradeAmountLocal'] = 0;
    $data['totalTradeAmountUSD'] = 0;
    $data['totalTradeAmountEstActLocal'] = 0;
    $data['totalTradeAmountEstActUSD'] = 0;
    $amountValueUtilizedLocalWithFees = 0;
    $budgetExchangeRate = ($data['budgetExchangeRate'] > 0) ? $data['budgetExchangeRate'] : 1;

    foreach($data['trades'] as $t) {
      $data['totalTradeAmountEstActLocal'] += $t['tradeSizeLocal'];
      $data['totalTradeAmountEstActUSD'] += $t['tradeSizeUSD'];
      $data['amountValueLocal'] += $t['tradeSizeLocal'] * $t['tradePrice'];
      $thisExchangeRate = ($t['tExchangeRate'] > 0) ? $t['tExchangeRate'] : $budgetExchangeRate; //if trade has an FX then use that, otherwise, use what is on the credit
      $amountValueUtilizedLocalWithFees += $t['tradeSize'] + $t['interestAmountLocal'];
      $data['amountValueUSD'] += ($t['tradeSize'] + $t['interestAmountLocal']) * $t['tradePrice'] * $thisExchangeRate;
      $data['totalTradeAmount'] += ($t['tradeIsEstimated'] == 1) ? 0 : $t['tradeSizeLocal'];
      $data['totalTradeAmountLocal'] += ($t['tradeIsEstimated'] == 1) ? 0 : $t['tradeSizeLocal'];
      $data['totalTradeAmountUSD'] += ($t['tradeIsEstimated'] == 1) ? 0 : $t['tradeSizeUSD'];
    }

    $data['calculatedAmountRemaining'] = $data['amountLocal'] - $data['totalTradeAmountLocal'];
    $data['amountLocalRemaining'] = $data['amountLocal'] - $data['totalTradeAmountLocal'];
    $data['amountValueLocal'] += ($data['amountLocal'] - $data['totalTradeAmountEstActLocal']) * $data['estCreditPrice'];

    $data['amountUSDRemaining'] = $data['amountUSD'] - $data['totalTradeAmountUSD'];

    $data['amountValueUSD'] += ($data['amountLocal'] - $amountValueUtilizedLocalWithFees) * $budgetExchangeRate * $data['estCreditPrice'];

    return $data;

  }

  /* SHARED FUNCTION */
  function get_credit_estimated_value($id) {
    $this->db->select('PendingListings.listingId, PendingListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, PendingListings.estCreditPrice, PendingListings.budgetExchangeRate');
    $this->db->where('PendingListings.listingId', $id);
    $this->db->from('PendingListings');
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['trades'] = $this->Trading->get_trades_lite($data['listingId'], "all", "", 1);
      $utilizations_processed_data = $this->process_utilizations_amount_data($data);
      $data = array_merge($data, $utilizations_processed_data);

      //Get credit estimate
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_listed_status_and_owner($id) {

    $this->db->select('ActiveListings.listed, ActiveListings.offerSize, ActiveListings.traded, PendingListings.listedBy, PendingListings.status');
    $this->db->where('ActiveListings.listingId', $id);
    $this->db->from('ActiveListings');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_account_credit_projects($userId) {

    $this->db->select('PendingListings.stateCertNum');
    $this->db->where('listedBy', $userId);
    $this->db->from('PendingListings');
    $this->db->distinct();
    $this->db->order_by("stateCertNum asc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      if($data['stateCertNum'] != "") {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_shared_credit_projects($userId) {

    $this->db->select('PendingListings.stateCertNum');
    $this->db->where('Shares.sharedPrimId', $userId);
    $this->db->where('Shares.sType', 'credit');
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->from('PendingListings');
    $this->db->join("Shares", "Shares.sItemId = PendingListings.listingId", 'left');
    $this->db->distinct();
    $this->db->order_by("stateCertNum asc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      if($data['stateCertNum'] != "") {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_account_trade_projects($userId) {

    $this->db->select('PendingListings.stateCertNum');
    $this->db->where('PendingListings.listedBy', $userId);
    $this->db->from('Trades');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->distinct();
    $this->db->order_by("PendingListings.stateCertNum asc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_account_credit_jurisdictions($userId) {

    $this->db->select('States.name, States.state');
    $this->db->where('listedBy', $userId);
    $this->db->where('PendingListings.cOrigin !=', 'loaded_purchase');
    $this->db->from('PendingListings');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->distinct();
    $this->db->order_by("name asc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_shared_credit_jurisdictions($userId) {

    $this->db->select('States.name, States.state');
    $this->db->where('Shares.sharedPrimId', $userId);
    $this->db->where('Shares.sType', 'credit');
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->where('PendingListings.cOrigin !=', 'loaded_purchase');
    $this->db->from('PendingListings');
    $this->db->join("Shares", "Shares.sItemId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->distinct();
    $this->db->order_by("name asc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      if($data['name'] != "") {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_account_trade_jurisdictions($perspective, $userId) {

    $this->db->select('States.name, States.state');
    if($perspective == "seller") {
      $this->db->where('PendingListings.listedBy', $userId);
    }
    if($perspective == "buyer") {
      $this->db->where('Trades.accountId', $userId);
    }
    $this->db->from('Trades');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->distinct();
    $this->db->order_by("name asc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_filter_data_by_account($filterType, $input) {

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;

    $input = $this->credit_input($input);

    //SELECT
    if($filterType == "taxentities") {
      $select = "Taxpayers.taxpayerId, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.dmaAccountId, dmaAccounts.title as tpAccountOwnerName";
    }
    if($filterType == "taxyears") {
      $select = "PendingListings.taxYearId, TaxYear.taxYear";
    }
    if($filterType == "countries") {
      $select = "loc_c.id, loc_c.name";
    }
    if($filterType == "provinces") {
      $select = "loc_p.id, loc_p.name";
    }
    if($filterType == "counties") {
      $select = "loc_co.id, loc_co.name";
    }
    if($filterType == "towns") {
      $select = "loc_t.id, loc_t.name";
    }
    if($filterType == "jurisdictions") {
      //$select = "States.name, States.state";
      $select = "$q1 AS name, $q2 AS state";
    }
    if($filterType == "incentiveprogram") {
      $select = "IncentivePrograms.OIXIncentiveId, IncentivePrograms.ProgramName";
    }
    if($filterType == "projects") {
      $select = "PendingListings.stateCertNum";
    }
    if($filterType == "credit_types") {
      $select = "ProgramType.ProgramTypeId, ProgramType.ProgramTypeName";
    }
    if($filterType == "certStatus") {
      $select = "cert_status_type.cert_status_id, cert_status_type.cert_status_name";
    }
    if($filterType == "auditStatus") {
      $select = "PendingListings.auditStatusId, audit_status.auditStatus";
    }
    if($filterType == "monetizationStatus") {
      $select = "monetization_status.mnsId, monetization_status.mnsName";
    }
    if($filterType == "projectStatus") {
      $select = "project_status.statusId, project_status.projectStatus";
    }

    $this->db->select($select, false);

    //WHERE
    $this->credit_where_access($input);
    $this->credit_where_owner($input);
    $this->credit_where_shared_owner($input);
    $this->credit_where_taxpayers($input);
    //$this->credit_where_advisor_status($input);
    $this->db->where('ActiveListings.deleteMarker', null);

    $this->db->from('PendingListings');

    //JOIN
    if($filterType == "taxentities") {
      $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
      $this->db->join("dmaAccounts", "dmaAccounts.dmaId = Taxpayers.dmaAccountId", 'left');
    }
    if($filterType == "taxyears") {
      $this->db->join("TaxYear", "PendingListings.taxYearId = TaxYear.id", 'left');
    }
    if($filterType == "jurisdictions" || $filterType == "countries" || $filterType == "provinces" || $filterType == "counties" || $filterType == "towns") {
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
      //$this->db->join("States", "States.state = IncentivePrograms.State", 'left');
      $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
      $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
      $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
      $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
      $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');
    }
    if($filterType == "incentiveprogram") {
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    }
    if($filterType == "credit_types") {
      $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    }
    if($filterType == "certStatus") {
      $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    }
    if($filterType == "auditStatus") {
      $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    }
    if($filterType == "monetizationStatus") {
      $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    }
    if($filterType == "projectStatus") {
      $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    }
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    //if($input['getShareData'] || isset($input['sharedAccount'])) {
    //$this->db->join("Shares","PendingListings.listingId = Shares.sItemId", 'left');
    //}

    $this->db->distinct();

    //ORDER
    if($filterType == "taxentities") {
      $this->db->order_by("tpCompanyName asc");
    }
    if($filterType == "taxyears") {
      $this->db->order_by("taxYear desc");
    }
    if($filterType == "countries") {
      $this->db->order_by("loc_c.name asc");
    }
    if($filterType == "provinces") {
      $this->db->order_by("loc_p.name asc");
    }
    if($filterType == "counties") {
      $this->db->order_by("loc_co.name asc");
    }
    if($filterType == "towns") {
      $this->db->order_by("loc_t.name asc");
    }
    if($filterType == "incentiveprogram") {
      $this->db->order_by("ProgramName asc");
    }
    if($filterType == "projects") {
      $this->db->order_by("stateCertNum asc");
    }
    if($filterType == "credit_types") {
      $this->db->order_by("ProgramTypeName asc");
    }
    if($filterType == "certStatus") {
      $this->db->order_by("cert_status_name asc");
    }
    if($filterType == "auditStatus") {
      $this->db->order_by("auditStatus asc");
    }
    if($filterType == "monetizationStatus") {
      $this->db->order_by("mnsOrder asc");
    }
    if($filterType == "projectStatus") {
      $this->db->order_by("projectStatus asc");
    }

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      if($filterType == "projects") {
        if($data['stateCertNum'] != "") {
          array_push($return, $data);
        }
      } else {
        if($filterType == "jurisdictions" || $filterType == "countries" || $filterType == "province" || $filterType == "county" || $filterType == "town") {
          if($data['name'] != "") {
            array_push($return, $data);
          }
        } else {
          if($filterType == "incentiveprogram") {
            if($data['ProgramName'] != "") {
              array_push($return, $data);
            }
          } else {
            if($filterType == "credit_types") {
              if($data['ProgramTypeName'] != "") {
                array_push($return, $data);
              }
            } else {
              if($filterType == "taxyears") {
                if($data['taxYear'] > 0) {
                  array_push($return, $data);
                }
              } else {
                if($filterType == "certStatus") {
                  if($data['cert_status_name'] != "") {
                    array_push($return, $data);
                  }
                } else {
                  if($filterType == "auditStatus") {
                    if($data['auditStatus'] != "") {
                      array_push($return, $data);
                    }
                  } else {
                    if($filterType == "monetizationStatus") {
                      if($data['mnsName'] != "") {
                        array_push($return, $data);
                      }
                    } else {
                      if($filterType == "projectStatus") {
                        if($data['projectStatus'] != "") {
                          array_push($return, $data);
                        }
                      } else {
                        if($filterType == "taxentities") {
                          if($data['tpAccountType'] != "") {
                            $tpName = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . " " . $data['tpLastName'];
                            $tpName .= ($data['dmaAccountId'] != $this->cisession->userdata('dmaId')) ? " <span class='pGray'>(" . $data['tpAccountOwnerName'] . ")</span>" : "";
                            $tpData = ['taxpayerId' => $data['taxpayerId'], 'taxpayerName' => $tpName];
                            array_push($return, $tpData);
                          }
                        } else {
                          array_push($return, $data);
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }

    }

    return $return;

  }

  function get_custom_data_points($request) {

    $dmaId = $request['dmaId'];
    $dpObjectType = $request['dpObjectType'];
    if($dmaId > 0) {
    } else {
      throw new \Exception('no dma ID supplied.');
    }

    $select = 'dataPoints.dpValue';
    $this->db->select($select);
    $this->db->where('dataPoints.dpDmaId', $dmaId);
    $this->db->where('dataPoints.dpObjectType', $dpObjectType);
    $this->db->where('dataPoints.dpArchivedMarker', null);

    $this->db->from('dataPoints');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $return[$data['dpValue']] = null;
    }

    return $return;

  }

  function get_custom_data_point_values($request) {

    $dmaId = $request['dmaId'];
    $objectId = $request['listingId'];
    $dpId = isset($request['dpId']) ? $request['dpId'] : 0;
    $dpObjectType = $request['dpObjectType'];

    $select = 'dataPoints.dpValue, dataPointCustomValues.cvValue, dataPointCustomValues.option_value_id, data_point_option.label';
    $this->db->select($select);
    if($dpId > 0 && $objectId > 0) {
      $this->db->where('dataPoints.dpId', $dpId);
      $this->db->where('dataPointCustomValues.cvObjectId', $objectId);
    } else if($dmaId > 0 && $objectId > 0) {
      $this->db->where('dataPoints.dpDmaId', $dmaId);
      $this->db->where('dataPointCustomValues.cvObjectId', $objectId);
    } else if($dpId > 0) {
      $this->db->where('dataPoints.dpId', $dpId);
    } else {
      throw new \Exception('no dp id supplied.');
    }
    if($dpObjectType != "") {
      $this->db->where('dataPoints.dpObjectType', $dpObjectType);
    }
    $this->db->where('dataPoints.dpArchivedMarker', null);
    $this->db->from('dataPoints');
    $this->db->join("dataPointCustomValues", "dataPoints.dpId = dataPointCustomValues.cvDpId", 'left');
    $this->db->join('data_point_option', 'data_point_option.id = dataPointCustomValues.option_value_id', 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data['cvValue'] != "") {
        $return[$data['dpValue']] = $data['cvValue'];
      }
      if($data['option_value_id'] != "") {
        $return[$data['dpValue']] = $data['label'];
        $return[$data['dpValue'] . '-id'] = $data['option_value_id'];
        //TODO:  this didn't work
      }
    }

    return $return;

  }

  function get_all_custom_values_for_data_point($dpId = "") {

    $select = 'dataPointCustomValues.*, dataPoints.dpObjectType as cvObjectType';
    $this->db->select($select);
    $this->db->where('dataPoints.dpId', $dpId);
    $this->db->or_where('dataPoints.parent_id', $dpId);
    $this->db->where('dataPoints.dpArchivedMarker', null);
    $this->db->from('dataPoints');
    $this->db->join("dataPointCustomValues", "dataPoints.dpId = dataPointCustomValues.cvDpId AND dataPointCustomValues.is_deleted = 0", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($data['cvValue'] != "" || $data['option_value_id'] != "") {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function prepareCreditSummaryArray() {

    $return['cntCredits'] = 0;
    $return['cntCreditsManaged'] = 0;
    $return['cntCreditsListed'] = 0;
    $return['cntCreditsSold'] = 0;
    $return['cntCreditsOutstanding'] = 0;
    $return['cntCreditsReceived'] = 0;
    $return['totalFaceValue'] = 0;
    $return['totalCreditEstimateValue'] = 0;
    $return['outstandingFaceValue'] = 0;
    $return['outstandingCreditEstimateValue'] = 0;
    $return['receivedFaceValue'] = 0;
    $return['receivedCreditActualValue'] = 0;
    $return['creditUnderConsiderationNumber'] = 0;
    $return['creditUnderConsiderationAmount'] = 0;
    $return['creditInitCertSubmittedNumber'] = 0;
    $return['creditInitCertSubmittedAmount'] = 0;
    $return['creditInitCertNumber'] = 0;
    $return['creditInitCertAmount'] = 0;
    $return['creditFinalCertSubmittedNumber'] = 0;
    $return['creditFinalCertSubmittedAmount'] = 0;
    $return['creditFinalCertNumber'] = 0;
    $return['creditFinalCertAmount'] = 0;
    $return['creditUSTransNumber'] = 0;
    $return['creditUSTransAmount'] = 0;
    $return['creditUSRefundNumber'] = 0;
    $return['creditUSRefundAmount'] = 0;
    $return['creditIntNumber'] = 0;
    $return['creditIntAmount'] = 0;

    $return['projectPerformance']['effectiveRateOfIncentiveCount'] = 0;
    $return['projectPerformance']['effectiveRateOfIncentive'] = 0;
    $return['projectPerformance']['creditEarnedToSpendCount'] = 0;
    $return['projectPerformance']['creditEarnedToSpend'] = 0;
    $return['projectPerformance']['spendThatQualifiedCount'] = 0;
    $return['projectPerformance']['spendThatQualified'] = 0;

    /*
	    $return['monetizationStatus'] = array();
	    $return['monetizationStatusTotalCount'] = 0;
	    $return['monetizationStatusTotalAmount'] = 0;
	    $return['monetizationStatusTotalNotIncludedCount'] = 0;
	    $return['monetizationStatusTotalNotIncludedAmount'] = 0;
	    $return['projectStatus'] = array();
	    $return['projectStatusTotalCount'] = 0;
	    $return['projectStatusTotalAmount'] = 0;
	    $return['projectStatusTotalNotIncludedCount'] = 0;
	    $return['projectStatusTotalNotIncludedAmount'] = 0;
	    $return['auditStatus'] = array();
	    $return['auditStatusTotalCount'] = 0;
	    $return['auditStatusTotalAmount'] = 0;
	    $return['auditStatusTotalNotIncludedCount'] = 0;
	    $return['auditStatusTotalNotIncludedAmount'] = 0;
	    $return['certificationStatus'] = array();
	    $return['certificationStatusTotalCount'] = 0;
	    $return['certificationStatusTotalAmount'] = 0;
	    $return['certificationStatusTotalNotIncludedCount'] = 0;
	    $return['certificationStatusTotalNotIncludedAmount'] = 0;
	    */
    $return['creditTypes'] = [];
    $return['creditUsCount'] = 0;
    $return['creditInternationalCount'] = 0;
    $return['creditJurisdictionsArray'] = [];
    $return['incentiveProgramsUsed'] = [];
    $return['dealroom']['listed'] = 0;
    $return['dealroom']['listedAmount'] = 0;
    $return['dealroom']['sold'] = 0;
    $return['dealroom']['soldAmount'] = 0;
    $return['dealroom']['pending'] = 0;
    $return['dealroom']['pendingAmount'] = 0;
    $return['total_all']['totalCreditsCount'] = 0;
    $return['total_all']['totalSize'] = 0;
    $return['total_all']['monetized']['totalCount'] = 0;
    $return['total_all']['monetized']['totalCreditsCount'] = 0;
    $return['total_all']['monetized']['totalSize'] = 0;
    $return['total_all']['monetized']['totalPrice'] = 0;
    $return['total_all']['unmonetized']['totalCreditsCount'] = 0;
    $return['total_all']['unmonetized']['totalSize'] = 0;
    $return['total_active']['totalCreditsCount'] = 0;
    $return['total_active']['totalSize'] = 0;
    $return['total_active']['monetized']['totalCount'] = 0;
    $return['total_active']['monetized']['totalCreditsCount'] = 0;
    $return['total_active']['monetized']['totalSize'] = 0;
    $return['total_active']['monetized']['totalPrice'] = 0;
    $return['total_active']['unmonetized']['totalCreditsCount'] = 0;
    $return['total_active']['unmonetized']['totalSize'] = 0;
    $return['total_archived']['totalCreditsCount'] = 0;
    $return['total_archived']['totalSize'] = 0;
    $return['total_archived']['monetized']['totalCount'] = 0;
    $return['total_archived']['monetized']['totalCreditsCount'] = 0;
    $return['total_archived']['monetized']['totalSize'] = 0;
    $return['total_archived']['monetized']['totalPrice'] = 0;
    $return['total_archived']['unmonetized']['totalCreditsCount'] = 0;
    $return['total_archived']['unmonetized']['totalSize'] = 0;
    $return['loaded']['totalCreditsCount'] = 0;
    $return['loaded']['totalSize'] = 0;
    $return['purchased']['totalCreditsCount'] = 0;
    $return['purchased']['totalSize'] = 0;
    $return['trades']['tradeCount'] = 0;
    $return['trades']['tradeCreditsCount'] = 0;
    $return['trades']['tradeSizeTotal'] = 0;
    $return['trades']['tradePriceTotal'] = 0;
    $return['trades']['tradePercentageTotal'] = 0;
    $return['trades']['tradePercentageAvg'] = 0;

    $return['advisor']['projectsPendingCount'] = 0;
    $return['advisor']['projectsPendingAmount'] = 0;
    $return['advisor']['projectsActiveCount'] = 0;
    $return['advisor']['projectsActiveAmount'] = 0;
    $return['advisor']['projectsCompletedCount'] = 0;
    $return['advisor']['projectsCompletedAmount'] = 0;
    $return['advisor']['projectsCancelledCount'] = 0;
    $return['advisor']['projectsCancelledAmount'] = 0;

    return $return;

  }

  function get_my_credit_ids($input) {

    $select = 'PendingListings.listingId, PendingListings.updatedTime, PendingListings.cCustomerAccess';
    $this->db->select($select);

    $this->credit_where_access($input);
    $this->credit_where_owner($input);
    $this->credit_where_shared_owner($input);
    //$this->credit_where_advisor_status($input);
    //$this->credit_where_customer_access_status($input);

    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->from('PendingListings');
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "dmaAccounts.primary_account_id = Accounts.userId", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');

    if($input['limit'] > 0) {
      $this->db->limit($input['limit']);
    }
    $this->db->distinct();
    $this->db->group_by('listingId');
    $query = $this->db->get();

    $records = $query->result_array();

    $return = [];

    foreach($records as $data) {

      if($input['listingId'] > 0) {
        if($input['listingId'] == $data['listingId']) {
          array_push($return, $data);
        }
      } else {
        array_push($return, $data);
      }

    }

    return $return;

  }

  function update_credit_cache($input) {

    if($input['listingId'] > 0) {
    } else {
      throw new \Exception('General fail');
    }

    if($this->redisIsAvailable === false) {
      $this->logger->error("Unable to connect to redis, updating cache skipped");

      return true;
    }

    $tbl_name = 'Credits';

    $hkey = $tbl_name . ':' . $input['listingId'];

    //If this is an update to existing cache record
    if(!isset($input['newRecord']) || !$input['newRecord']) {
      /// DELETE EXISTING
      $hfields = $this->redis->hkeys($hkey);
      $this->redis->hdel($hkey, $hfields);
      // Push the row id to the list
      $this->redis->lrem($tbl_name, 0, $input['listingId']);
    }

    // GET CURRENT DATA
    $input = $this->credit_input($input);
    $input['listingId'] = $input['listingId'];
    $creditRefresh = $this->get_credit_data($input);

    // UPDATE CREDIT CACHE
    $creditRefreshed = [];
    $creditRefreshed['credit'] = json_encode($creditRefresh['credit']);
    $this->redis->hmset($hkey, $creditRefreshed);
    // Push the row id to the list
    $this->redis->rpush($tbl_name, $input['listingId']);

  }

  function get_credits_from_cache($input, $creditIds) {

    $tbl_name = 'Credits';
    $credits = [];

    if(sizeof($creditIds) > 0) {
      // Get the all row data from Redis by iterating row keys.
      foreach($creditIds as $creditId) {

        $getFromDatabaseFlag = true;
//        $hkey = $tbl_name . ':' . $creditId['listingId'];
//        if($this->redisIsAvailable !== false) {
//          $row = $this->redis->hgetall($hkey);
//        }
//        if(!empty($row)) {
//          $creditDecoded = json_decode($row['credit'], true);
//          //If cache updated time matches time pulled from database, then use cache
//          if($creditDecoded['updatedTime'] != $creditId['updatedTime']) {
//            $getFromDatabaseFlag = true;
//          } else {
//            $getFromDatabaseFlag = false;
//            array_push($credits, $creditDecoded);
//          }
//        }
        if($getFromDatabaseFlag) {
          $cInput = $input;
          $cInput['listingId'] = $creditId['listingId'];
          $creditRefresh = $this->get_credit_data($cInput);
          array_push($credits, $creditRefresh['credit']);
//          $creditRefreshed = [];
//          $creditRefreshed['credit'] = json_encode($creditRefresh['credit']);
//
//          if($this->redisIsAvailable !== false) {
//            $this->redis->hmset($hkey, $creditRefreshed);
//            // Push the row id to the list
//            $this->redis->rpush($tbl_name, $creditId['listingId']);
//          }
        }

      }
    }

    return $credits;

  }

  function get_credits($input) {

    $input = $this->credit_input($input);

    $creditIds = $this->get_my_credit_ids($input);

    $credits = $this->get_credits_from_cache($input, $creditIds);

    //handle order of that list (filters will be below in loop)
    $records = $this->cache_credit_order($input, $credits);

    if($input['dmaId'] > 0) {
      $dpRequest['dpDmaIdCustom'] = $input['dmaId'];
      $dpRequest['dpObjectType'] = 'credit';
      $customDataPoints = $this->get_data_points($dpRequest);
      $cdp['customDataPointsRawData'] = $customDataPoints['dataPoints'];
    } else {
      $cdp['customDataPointsRawData'] = [];
    }

    $return = [];
    $return['credits'] = [];
    $return['summary'] = [];
    $return['summary'] = array_merge($return['summary'], $this->prepareCreditSummaryArray(), $this->Trading->getUtilizationTypesFields(), $this->getSummaryFields_auditStartDate());

    foreach($records as $data) {

      //First, get some data in realtime before filtering
      //If this is NOT the owner account of the credit, then get MY SHARE - this is NOT part of the cache because "my" is relative (could be several shares on a credit)
      $data['myShare'] = [];
      if($this->cisession->userdata('dmaId') != $data['listedBy']) {
        foreach($data['shares'] as $share) {
          if($share['sharedPrimId'] == $this->cisession->userdata('dmaId')) {
            //Taking this merge out as it causes bugs - not sure why it was here in the first place (maybe advisor system?) - I'm keeping it here in case we need to bring it back...
            //$data = array_merge($data, $share); //merge this share into the main credit data
            //Only inserting the permissions into an array called "My Share"
            $data['myShare']['sharerPrimId'] = $share['sharerPrimId'];
            $data['myShare']['sharedPrimId'] = $share['sharedPrimId'];
            $data['myShare']['sDeleteMarker'] = $share['sDeleteMarker'];
          }
        }
      }

      //Apply filters
      $thisInclude = $this->credit_where_cache_filters($data, $input);
      if(!$thisInclude) {
        continue;
      }

      //If i am an advisor
      if($this->cisession->userdata('dmaType') == "advisor") {
        //Find my loan ("project") data, if it exits
        foreach($data['loans'] as $loan) {
          if($loan['loanAccountId'] == $this->cisession->userdata('primUserId')) {
            $data['loanType'] = $loan['loanType'];
            $data['loanStatus'] = $loan['loanStatus'];
            $data['loanAmount'] = $loan['loanAmount'];
            $data['closingDate'] = $loan['closingDate'];
            $data['maturityDate'] = $loan['maturityDate'];
            $data['advisorRepresentative'] = $loan['advisorRepName'];
            $data['advisorAssignedTo'] = $loan['assignedToName'];
            $data['payment1Amount'] = $loan['payment1Amount'];
            $data['payment1Date'] = $loan['payment1Date'];
            $data['payment1Received'] = $loan['payment1Received'];
            $data['payment2Amount'] = $loan['payment2Amount'];
            $data['payment2Date'] = $loan['payment2Date'];
            $data['payment2Received'] = $loan['payment2Received'];
            $data['payment3Amount'] = $loan['payment3Amount'];
            $data['payment3Date'] = $loan['payment3Date'];
            $data['payment3Received'] = $loan['payment3Received'];
            $data = array_merge($data, $loan);
          }
        }

        //Advisor Status - build primarly off of SHARE and LOAN data
        $data['advisorStatusText'] = '';
        if(isset($data['advisorStatus'])) {
          if($data['advisorStatus'] == 1 || $data['advisorStatus'] == 0) {
            $data['advisorStatus'] = 1; //make 0 be 1
            $data['advisorStatusText'] = 'Pending';
          } else {
            if($data['advisorStatus'] == 2) {
              $data['advisorStatusText'] = 'Active';
            } else {
              if($data['advisorStatus'] == 3) {
                $data['advisorStatusText'] = 'Completed';
              } else {
                if($data['advisorStatus'] == 9) {
                  $data['advisorStatusText'] = 'Cancelled';
                }
              }
            }
          }
        }
        //Advisor Fee Status
        $data['advisorFeeTotal'] = 0;
        $data['advisorFeePaidAmount'] = 0;
        $data['advisorFeeOutstandingAmount'] = 0;
        if(isset($data['payment1Amount'])) {
          if($data['payment1Amount'] > 0) {
            $data['advisorFeeTotal'] += $data['payment1Amount'];
            if($data['payment1Received'] == 1) {
              $data['advisorFeePaidAmount'] += $data['payment1Amount'];
            } else {
              $data['advisorFeeOutstandingAmount'] += $data['payment1Amount'];
            }
          }
        }
        if(isset($data['payment2Amount'])) {
          if($data['payment2Amount'] > 0) {
            $data['advisorFeeTotal'] += $data['payment2Amount'];
            if($data['payment2Received'] == 1) {
              $data['advisorFeePaidAmount'] += $data['payment2Amount'];
            } else {
              $data['advisorFeeOutstandingAmount'] += $data['payment2Amount'];
            }
          }
        }
        if(isset($data['payment3Amount'])) {
          if($data['payment3Amount'] > 0) {
            $data['advisorFeeTotal'] += $data['payment3Amount'];
            if($data['payment3Received'] == 1) {
              $data['advisorFeePaidAmount'] += $data['payment3Amount'];
            } else {
              $data['advisorFeeOutstandingAmount'] += $data['payment3Amount'];
            }
          }
        }
        //Advisor Project Start / End Dates
        if(isset($data['closingDate'])) {
          $data['closingDate'] = ($data['closingDate'] > 0) ? $data['closingDate'] : 0;
        }
        if(isset($data['maturityDate'])) {
          $data['maturityDate'] = ($data['maturityDate'] > 0) ? $data['maturityDate'] : 0;
        }

      }

      if($data['cOrigin'] == "loaded_purchase") {
        $return['summary']['purchased']['totalCreditsCount']++;
        $return['summary']['purchased']['totalSize'] += $data['creditAmount'];
      } else {
        $return['summary']['loaded']['totalCreditsCount']++;
        $return['summary']['loaded']['totalSize'] += $data['creditAmount'];
      }

      //TRADE SUMMARY DATA
      $return = $this->Trading->summarizeUtilizationTypes($data, $return);
      $return['summary']['trades']['tradeCount'] += $data['utilize_total_count'];
      $return['summary']['trades']['tradeSizeTotal'] += $data['utilize_value_total'];
      $return['summary']['trades']['tradePriceTotal'] += $data['utilize_total_net_value'];
      $return['summary']['trades']['tradePercentageTotal'] += $data['utilize_percentage_total'];
      $return['summary']['total_all']['monetized']['totalCount'] += $data['summary']['total_all']['monetized']['totalCount'];
      $return['summary']['total_all']['monetized']['totalSize'] += $data['summary']['total_all']['monetized']['totalSize'];
      $return['summary']['total_all']['monetized']['totalCreditsCount'] += $data['summary']['total_all']['monetized']['totalCreditsCount'];
      $return['summary']['total_active']['monetized']['totalCount'] += $data['summary']['total_active']['monetized']['totalCount'];
      $return['summary']['total_active']['monetized']['totalSize'] += $data['summary']['total_active']['monetized']['totalSize'];
      $return['summary']['total_active']['monetized']['totalCreditsCount'] += $data['summary']['total_active']['monetized']['totalCreditsCount'];
      $return['summary']['total_archived']['monetized']['totalCount'] += $data['summary']['total_archived']['monetized']['totalCount'];
      $return['summary']['total_archived']['monetized']['totalSize'] += $data['summary']['total_archived']['monetized']['totalSize'];
      $return['summary']['total_archived']['monetized']['totalCreditsCount'] += $data['summary']['total_archived']['monetized']['totalCreditsCount'];
      if($data['utilize_actual_count'] > 0) {
        $return['summary']['trades']['tradeCreditsCount']++;
      }

      if($data['availableToList'] > 0) {
        if($data['cArchived'] == 1) {
          $return['summary']['total_all']['unmonetized']['totalCreditsCount']++;
          $return['summary']['total_archived']['unmonetized']['totalCreditsCount']++;
        } else {
          $return['summary']['total_all']['unmonetized']['totalCreditsCount']++;
          $return['summary']['total_active']['unmonetized']['totalCreditsCount']++;
        }
      }
      if($data['cArchived'] == 1) {
        $return['summary']['total_all']['totalCreditsCount']++;
        $return['summary']['total_archived']['totalCreditsCount']++;
        $return['summary']['total_all']['totalSize'] += $data['amountUSD'];
        $return['summary']['total_archived']['totalSize'] += $data['amountUSD'];
      } else {
        $return['summary']['total_all']['totalCreditsCount']++;
        $return['summary']['total_active']['totalCreditsCount']++;
        $return['summary']['total_all']['totalSize'] += $data['amountUSD'];
        $return['summary']['total_active']['totalSize'] += $data['amountUSD'];
      }

      //Start
      //These next functions are in BOTH individual credit call AND summary call because they do different things

      $inputConfigs['fieldName'] = 'statusMonetization';
      $inputConfigs['fieldNameDB'] = 'monetizationStatus';
      $statusMonetizationProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs);
      $data = $statusMonetizationProcessed['data'];
      $return = $statusMonetizationProcessed['return'];

      $inputConfigs['fieldName'] = 'projectStatusId';
      $inputConfigs['fieldNameDB'] = 'projectStatus';
      $statusProjectProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs);
      $data = $statusProjectProcessed['data'];
      $return = $statusProjectProcessed['return'];

      $inputConfigs['fieldName'] = 'auditStatusId';
      $inputConfigs['fieldNameDB'] = 'auditStatus';
      $statusAuditProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs);
      $data = $statusAuditProcessed['data'];
      $return = $statusAuditProcessed['return'];

      $inputConfigs['fieldName'] = 'certificationStatus';
      $inputConfigs['fieldNameDB'] = 'certificationStatus';
      $statusCertificationProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs);
      $data = $statusCertificationProcessed['data'];
      $return = $statusCertificationProcessed['return'];

      foreach($cdp['customDataPointsRawData'] as $cdpr) {
        if($cdpr['dpType'] == 'selectDropDown' && $cdpr['dpSection'] == 'credit_status_settings') {
          $inputConfigs['fieldName'] = $cdpr['dpValue'];
          $inputConfigs['fieldNameDB'] = $cdpr['dpValue'];
          $statusCustomDataPointProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs);
          $data = $statusCustomDataPointProcessed['data'];
          $return = $statusCustomDataPointProcessed['return'];
        }
      }

      $creditDatesProcessed = $this->creditDatesProcessed($data, $return);
      $data = $creditDatesProcessed['data'];
      $return = $creditDatesProcessed['return'];
      //End

      if(array_key_exists($data['ProgramTypeId'], $return['summary']['creditTypes'])) {
        $return['summary']['creditTypes'][$data['ProgramTypeId']]['count'] += 1;
      } else {
        $return['summary']['creditTypes'][$data['ProgramTypeId']]['count'] = 1;
        $return['summary']['creditTypes'][$data['ProgramTypeId']]['ProgramTypeName'] = $data['ProgramTypeName'];
      }
      if(array_key_exists($data['State'], $return['summary']['creditJurisdictionsArray'])) {
        $return['summary']['creditJurisdictionsArray'][$data['State']]['count'] += 1;
      } else {
        $return['summary']['creditJurisdictionsArray'][$data['State']]['count'] = 1;
        $return['summary']['creditJurisdictionsArray'][$data['State']]['State'] = $data['State'];
        $return['summary']['creditJurisdictionsArray'][$data['State']]['name'] = $data['name'];
      }
      if($data['countryId'] == 1) {
        $return['summary']['creditUsCount']++;
      } else {
        $return['summary']['creditInternationalCount']++;
      }
      $incentiveProgramAdd = false;
      if(array_key_exists($data['countryId'], $return['summary']['incentiveProgramsUsed'])) {
        if(array_key_exists($data['State'], $return['summary']['incentiveProgramsUsed'][$data['countryId']])) {
          if(array_key_exists($data['OIXIncentiveId'], $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']])) {
            $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['count']++;
            $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['amount'] += $data['amountUSD'];
          } else {
            $incentiveProgramAdd = true;
          }
        } else {
          $incentiveProgramAdd = true;
        }
      } else {
        $incentiveProgramAdd = true;
      }
      if($incentiveProgramAdd) {
        $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['count'] = 1;
        $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['amount'] = $data['amountUSD'];
        $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['programName'] = $data['ProgramName'];
        $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['jurisdictionName'] = $data['name'];
        $return['summary']['incentiveProgramsUsed'][$data['countryId']][$data['State']][$data['OIXIncentiveId']]['countryName'] = $data['countryName'];
      }

      $return['summary']['totalFaceValue'] += $data['amountUSD'];
      $return['summary']['totalCreditEstimateValue'] += $data['amountUSD'] * $data['estCreditPrice'];
      $return['summary']['outstandingFaceValue'] += $data['amountUSDRemaining'];
      $return['summary']['outstandingCreditEstimateValue'] += $data['estAmtRemaining'];
      $return['summary']['receivedFaceValue'] += $data['totalCreditUsed'];
      $return['summary']['receivedCreditActualValue'] += $data['totalCreditUsed'] * 0.92; //TO DO
      $return['summary']['cntCredits']++; //TO DO
      $return['summary']['cntCreditsOutstanding'] = 0; //TO DO
      $return['summary']['cntCreditsReceived'] = 0; //TO DO
      if($data['certificationStatus'] == 1) { //Final Certification
        $return['summary']['creditFinalCertNumber']++;
        $return['summary']['creditFinalCertAmount'] += $data['amountUSD'];
      } else {
        if($data['certificationStatus'] == 2) { //Initial Certification
          $return['summary']['creditInitCertNumber']++;
          $return['summary']['creditInitCertAmount'] += $data['amountUSD'];
        } else {
          if($data['certificationStatus'] == 3) { //Under Consideration
            $return['summary']['creditUnderConsiderationNumber']++;
            $return['summary']['creditUnderConsiderationAmount'] += $data['amountUSD'];
          } else {
            if($data['certificationStatus'] == 4) { //Initial Application Submitted
              $return['summary']['creditInitCertSubmittedNumber']++;
              $return['summary']['creditInitCertSubmittedAmount'] += $data['amountUSD'];
            } else {
              if($data['certificationStatus'] == 5) { //Final Application Submitted
                $return['summary']['creditFinalCertSubmittedNumber']++;
                $return['summary']['creditFinalCertSubmittedAmount'] += $data['amountUSD'];
              }
            }
          }
        }
      }
      if($data['status'] == 5 && $data['countryId'] == 1 && $data['ProgramTypeId'] == 1) {
        $return['summary']['creditUSTransNumber']++;
        $return['summary']['creditUSTransAmount'] += $data['amountUSD'];
      } else {
        if($data['status'] == 5 && $data['countryId'] == 1 && $data['ProgramTypeId'] == 2) {
          $return['summary']['creditUSRefundNumber']++;
          $return['summary']['creditUSRefundAmount'] += $data['amountUSD'];
        }
      }
      if($data['status'] == 5 && $data['countryId'] != 1) {
        $return['summary']['creditIntNumber']++;
        $return['summary']['creditIntAmount'] += $data['amountUSD'];
      }

      //Expenditure Report Data (another check after loop)
      if($data['qualifiedExpenditures'] > 0) {
        $return['summary']['projectPerformance']['effectiveRateOfIncentiveCount']++;
        $return['summary']['projectPerformance']['effectiveRateOfIncentive'] += $data['amountUSD'] / $data['qualifiedExpenditures'];
      }
      if($data['projectBudgetEst'] > 0) {
        $return['summary']['projectPerformance']['creditEarnedToSpendCount']++;
        $return['summary']['projectPerformance']['creditEarnedToSpend'] += $data['amountUSD'] / $data['projectBudgetEst'];
      }
      if($data['qualifiedExpenditures'] > 0 && $data['projectBudgetEst'] > 0) {
        $return['summary']['projectPerformance']['spendThatQualifiedCount']++;
        $return['summary']['projectPerformance']['spendThatQualified'] += $data['qualifiedExpenditures'] / $data['projectBudgetEst'];
      }

      //ADVISOR PROJECT STATUS
      if(isset($data['advisorStatus'])) {
        //Status
        if($data['advisorStatus'] == 1 || $data['advisorStatus'] == "") {
          $return['summary']['advisor']['projectsPendingCount']++;
          $return['summary']['advisor']['projectsPendingAmount'] += $data['amountUSD'];
        }
        if($data['advisorStatus'] == 2) {
          $return['summary']['advisor']['projectsActiveCount']++;
          $return['summary']['advisor']['projectsActiveAmount'] += $data['amountUSD'];
        }
        if($data['advisorStatus'] == 3) {
          $return['summary']['advisor']['projectsCompletedCount']++;
          $return['summary']['advisor']['projectsCompletedAmount'] += $data['amountUSD'];
        }
        if($data['advisorStatus'] == 9) {
          $return['summary']['advisor']['projectsCancelledCount']++;
          $return['summary']['advisor']['projectsCancelledAmount'] += $data['amountUSD'];
        }

      }

      //BROKER LISTED STATUS
      $data['listedOnDealRoom'] = false;
      if($data['brokerDmaId'] > 0 && $data['status'] == 3 && $data['traded'] != 1 && $data['traded'] == "" && $data['listed'] == 1) {
        $data['listedOnDealRoom'] = true;
        $return['summary']['dealroom']['listed']++;
        $return['summary']['dealroom']['listedAmount'] += $data['size'];
      } else {
        if($data['status'] == 9) {
          $return['summary']['dealroom']['sold']++;
          $return['summary']['dealroom']['soldAmount'] += $data['originalOfferSize'];
        } else {
          $data['listedOnDealRoom'] = false;
          $return['summary']['dealroom']['pending']++;
          $return['summary']['dealroom']['pendingAmount'] += $data['amountUSD'];
        }
      }

      array_push($return['credits'], $data);

    }

    //////////////////////////
    ///// SECONDARY ORDER
    //////////////////////////
    $order_primary = '';
    $order_secondary = '';
    if($input['order'] == "jurisdiction_a_z" || $input['order'] == "jurisdiction_z_a") {
      $order_primary = 'name';
      $order_secondary = 'stateCertNum';
    } else {
      if($input['order'] == "project_a_z" || $input['order'] == "project_z_a") {
        $order_primary = 'stateCertNum';
        $order_secondary = 'name';
      } else {
        if($input['order'] == "year_l_h" || $input['order'] == "year_h_l") {
          $order_primary = 'taxYear';
          $order_secondary = 'name';
        } else {
          if($input['order'] == "program_a_z" || $input['order'] == "program_z_a") {
            $order_primary = 'ProgramName';
            $order_secondary = 'name';
          } else {
            if($input['order'] == "type_a_z" || $input['order'] == "type_z_a") {
              $order_primary = 'ProgramTypeName';
              $order_secondary = 'name';
            } else {
              if($input['order'] == "shared_n_o" || $input['order'] == "shared_o_n") {
                $order_primary = 'sTimeStamp';
                $order_secondary = 'name';
              } else {
                if($input['order'] == "seller_a_z" || $input['order'] == "seller_z_a") {
                  $order_primary = 'title';
                  $order_secondary = 'name';
                }
              }
            }
          }
        }
      }
    }
    if($order_primary != "" && $order_secondary != "") {
      $new_credits_array = [];
      $sub_array_tracker_array = [];
      $credits_in_this_sub_array = [];
      foreach($return['credits'] as $soc) {
        if(in_array($soc[$order_primary], $sub_array_tracker_array)) {
          array_push($credits_in_this_sub_array, $soc);
        } else {
          if(sizeof($credits_in_this_sub_array) > 0) {
            if($order_secondary == "name") {
              usort($credits_in_this_sub_array, function($a, $b) {
                return strcmp(strtolower($a["name"]), strtolower($b["name"]));
              });
            }
            if($order_secondary == "stateCertNum") {
              usort($credits_in_this_sub_array, function($a, $b) {
                return strcmp(strtolower($a["stateCertNum"]), strtolower($b["stateCertNum"]));
              });
            }
            $new_credits_array = array_merge($new_credits_array, $credits_in_this_sub_array);
          }
          $credits_in_this_sub_array = [];
          array_push($sub_array_tracker_array, $soc[$order_primary]);
          array_push($credits_in_this_sub_array, $soc);
        }
      }
      //When the last credit is a single credit (not grouped) then it gets left out of the loop logic above, so we do one final check for a missed item
      if(sizeof($credits_in_this_sub_array) > 0) {
        $new_credits_array = array_merge($new_credits_array, $credits_in_this_sub_array);
      }
      $return['credits'] = $new_credits_array;
    }

    $return['summary']['projectPerformance']['effectiveRateOfIncentive'] = ($return['summary']['projectPerformance']['effectiveRateOfIncentiveCount'] > 0) ? $return['summary']['projectPerformance']['effectiveRateOfIncentive'] / $return['summary']['projectPerformance']['effectiveRateOfIncentiveCount'] : 0;
    $return['summary']['projectPerformance']['creditEarnedToSpend'] = ($return['summary']['projectPerformance']['creditEarnedToSpendCount'] > 0) ? $return['summary']['projectPerformance']['creditEarnedToSpend'] / $return['summary']['projectPerformance']['creditEarnedToSpendCount'] : 0;
    $return['summary']['projectPerformance']['spendThatQualified'] = ($return['summary']['projectPerformance']['spendThatQualifiedCount'] > 0) ? $return['summary']['projectPerformance']['spendThatQualified'] / $return['summary']['projectPerformance']['spendThatQualifiedCount'] : 0;

    if($return['summary']['trades']['tradeCount'] > 0) {
      $return['summary']['trades']['tradePercentageAvg'] = $return['summary']['trades']['tradePercentageTotal'] / $return['summary']['trades']['tradeCount'];
    }

    $sumTotalAllUnMonetized = $return['summary']['total_all']['totalSize'] - $return['summary']['total_all']['monetized']['totalSize'];
    $return['summary']['total_all']['unmonetized']['totalSize'] = ($sumTotalAllUnMonetized > 0) ? $sumTotalAllUnMonetized : 0;
    $sumTotalActiveUnMonetized = $return['summary']['total_active']['totalSize'] - $return['summary']['total_active']['monetized']['totalSize'];
    $return['summary']['total_active']['unmonetized']['totalSize'] = ($sumTotalActiveUnMonetized > 0) ? $sumTotalActiveUnMonetized : 0;
    $sumTotalArchivedUnMonetized = $return['summary']['total_archived']['totalSize'] - $return['summary']['total_archived']['monetized']['totalSize'];
    $return['summary']['total_archived']['unmonetized']['totalSize'] = ($sumTotalArchivedUnMonetized > 0) ? $sumTotalArchivedUnMonetized : 0;
    $totalUtilizationTypesProcessed = $this->Trading->processUtilizationTypeAveragePrices($return);
    $return = $totalUtilizationTypesProcessed['return'];

    return $return;

  }

  function get_credit_data($input) {

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;
    $q3 = \OIX\Services\JurisdictionService::$jurisdiciton_google_place_id_query;
    $q4 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_latitude_query;
    $q5 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_longitude_query;

    $select = 'PendingListings.listingId, ActiveListings.timeStamp, PendingListings.jurisdiction_id, PendingListings.OIXIncentiveId, ActiveListings.listed, ActiveListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, ActiveListings.offerSize as size, ActiveListings.offerPrice as price, ActiveListings.originalOfferSize, ActiveListings.allOrNone, ActiveListings.certificationNum, PendingListings.IssueDate, ActiveListings.OfferGoodUntil, ActiveListings.CreditUsedForOffset, ActiveListings.CreditUsedDate, PendingListings.listedBy, IncentivePrograms.ProgramName, IncentivePrograms.State, IncentivePrograms.Sector, PendingListings.cCarryForwardYears as CarryForwardYears, PendingListings.legislativeFramework, IPSectors.sector,OffsetLocked, IncentivePrograms.OffSettingTaxList, TaxYear.taxYear, ActiveListings.taxYearId, PendingListings.listingId as pendingListingId, PendingListings.status, PendingListings.certificationStatus, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.availableToList, PendingListings.est_initial_cert_dt, PendingListings.est_final_cert_dt, PendingListings.projectStartDate, PendingListings.projectEndDate, PendingListings.estimated_payment_date, PendingListings.cWorkflowId, PendingListings.cComplianceId, PendingListings.cOrigin, PendingListings.internalId, PendingListings.projectBudgetEst, PendingListings.requiredSpend, PendingListings.requiredComplianceTerm, PendingListings.complianceFirstYear, ActiveListings.brokerDmaId, PendingListings.requiredJobs, PendingListings.updatedTime, PendingListings.complianceNotes, PendingListings.statusMonetization, monetization_status.mnsId as monetizationStatusId, PendingListings.statusNotes, PendingListings.qualifiedExpenditures, PendingListings.incentiveRate, PendingListings.estCreditPrice, PendingListings.typeOfWork, PendingListings.projectNameExt, PendingListings.auditStatusId, ActiveListings.traded, PendingListings.auditStartDate, PendingListings.auditEndDate, PendingListings.projectStatus as projectStatusId, PendingListings.encumbered, PendingListings.lastDayPrincipalPhotography, PendingListings.cNotes, PendingListings.cArchived, PendingListings.localCurrency, ActiveListings.listingCustomerAssist, ActiveListings.listingAnonymously, ProgramType.ProgramTypeId, ProgramType.ProgramTypeName, dmaAccounts.profileUrl, dmaAccounts.title, dmaAccounts.title as companyName, dmaAccounts.dmaId, dmaAccounts.mainAdmin, dmaAccounts.parentDmaId, sellerAdminUser.userId as sellerAdminUserId, sellerAdminUser.firstName as sellerAdminUserFirstName, sellerAdminUser.lastName as sellerAdminUserLastName, Taxpayers.taxpayerId, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpFiscalYearEndMonth, Taxpayers.tpFiscalYearEndDay, cert_status_type.cert_status_name, audit_status.auditStatus, project_status.projectStatus, monetization_status.mnsName, PendingListings.finalCreditAmountFlag, PendingListings.cCustomerAccess';
    $select .= ", $q1 AS stateName, $q2 AS state, $q3 AS googlePlaceId";

    //$select .= ', States.googlePlaceId as googlePlaceId, parentJurisdiction.state as parentJurisdictionState, parentJurisdiction.name as parentJurisdictionName, States.name,States.state, States.name as jurisdictionName, States.id as stateId, States.countryId, States.sLatitude, States.sLongitude, countries.name as countryName';
    //$select .= 'States.sLatitude, States.sLongitude';
    $select .= ", $q2 as State, $q1 as name, $q1 as jurisdictionName, $q2 as parentJurisdictionState, $q1 as parentJurisdictionName, $q4 as sLatitude, $q5 as sLongitude, $q4 as jurisdiction_lat, $q5 as jurisdiction_lng";
    $select .= ", loc_c.id as countryId, loc_c.name as countryName, loc_c.code as countryCode, loc_p.id as provinceId, loc_p.name as provinceName, loc_co.id as countyId, loc_co.name as countyName, loc_t.id as townId, loc_t.name as townName";
    $this->db->select($select, false);

    $this->db->where('PendingListings.listingId', $input['listingId']);

    $this->credit_where_access($input);
    $this->credit_where_owner($input);
    $this->credit_where_shared_owner($input);
    //$this->credit_where_customer_access_status($input);

    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->from('PendingListings');
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.pendingListingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("TaxYear", "PendingListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("States as parentJurisdiction", "States.parentStateId = parentJurisdiction.id", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    $this->db->join("Accounts", "PendingListings.listedBy = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts as sellerAdminUser", "PendingListings.cDmaMemberId = sellerAdminUser.userId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
    $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');

    $query = $this->db->get();

    $records = $query->result_array();

    $return = [];
    $return['credit'] = [];

    if(isset($records[0]['listingId']) && $records[0]['listingId'] > 0) {
      //Proceed
    } else {
      return $return;
    }

    //Get external access of this credit
    $shares = $this->get_shares_of_credit($records[0]['listingId']);

    //CUSTOM DATA POINTS - always set to the OWNER of the credit's CDP
    $cdpRequest['dmaId'] = $records[0]['listedBy'];
    $cdpRequest['dpObjectType'] = 'credit';
    $customDataPoints = $this->get_custom_data_points($cdpRequest);
    //Check the credit owner's shared Parent Data Points
    if($records[0]['parentDmaId'] > 0) {
      $cdpParentRequest['dmaId'] = $records[0]['parentDmaId'];
      $cdpParentRequest['dpObjectType'] = 'credit';
      $customDataPointsRawParent = $this->get_custom_data_points($cdpParentRequest);
      $customDataPoints = array_merge($customDataPoints, $customDataPointsRawParent);
    }

    foreach($records as $data) {

      $data['summary'] = $this->Trading->getUtilizationTypesFields();

      $data['shares'] = $shares;

      $data['flags'] = [];

      //Get CDP values - always set to the OWNER of the credit's CDP
      $data = array_merge($data, $customDataPoints);
      $cdpvRequest['dmaId'] = $data['listedBy'];
      $cdpvRequest['listingId'] = $data['listingId'];
      $cdpvRequest['dpObjectType'] = 'credit';
      $customDataPointValues = $this->get_custom_data_point_values($cdpvRequest);
      if(sizeof($customDataPointValues) > 0) {
        $data = array_merge($data, $customDataPointValues);
      }
      //Check the credit owner's shared Parent Data Points
      if($data['parentDmaId'] > 0) {
        $cdpvRequest['dmaId'] = $data['parentDmaId'];
        $cdpvRequest['listingId'] = $data['listingId'];
        $cdpvRequest['dpObjectType'] = 'credit';
        $customDataPointValuesParent = $this->get_custom_data_point_values($cdpvRequest);
        if(sizeof($customDataPointValuesParent) > 0) {
          $data = array_merge($data, $customDataPointValuesParent);
        }
      }

      //If a town or county
      if($data['townId'] > 0 || $data['countyId'] > 0) {
        $data['jurisdictionNameFull'] = $data['stateName'] . ', ' . $data['provinceName'] . ', ' . $data['countryName'];
      } else if($data['provinceId'] > 0) {
        $data['jurisdictionNameFull'] = $data['provinceName'] . ', ' . $data['countryName'];
      } else {
        $data['jurisdictionNameFull'] = $data['countryName'];
      }

      $data['currencySymbolData'] = $this->currency->get_currency_data($data['localCurrency']);
      $data['localCurrencyName'] = $data['currencySymbolData']['name'];

      $data['trades'] = $this->Trading->get_trades_lite($data['listingId'], "all", "", 1);

      //setup few advisor Variables
      $data['advisorRepresentative'] = null;
      $data['advisorAssignedTo'] = null;

      $data['loans'] = $this->get_loans_on_listing_by_account($data['listingId'], '', 1);

      $data['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : $data['projectName'];
      //$data['OffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['creditExpirationDate'] = ($data['taxYear'] > 0 && $data['CarryForwardYears'] > 0) ? "12/31/" . ($data['taxYear'] + $data['CarryForwardYears']) : ($data['taxYear'] > 0 && $data['CarryForwardYears'] == 0 ? "12/31/" . $data['taxYear'] : null);
      $data['action'] = ($data['listed'] == null || $data['listed'] == 0) ? "Pending" : "Active";

      //Get credit estimate
      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['encumberedStatus'] = ($data['encumbered'] == 1) ? "Yes" : "No";
      $data['cOriginName'] = ($data['cOrigin'] == "loaded_purchase") ? "Purchased" : "Loaded";

      //Process utilization data
      $utilizations_processed_data = $this->process_utilizations_amount_data($data);
      $data = array_merge($data, $utilizations_processed_data);

      $tradesCreditArray = [];
      $data['utilize_total_count'] = 0;
      $data['utilize_estimate_count'] = 0;
      $data['utilize_actual_count'] = 0;
      $data['utilize_estimate_value'] = 0;
      $data['utilize_actual_value'] = 0;
      $data['utilize_actual_value_local'] = 0;
      $data['utilizationAmountActEstGrossUSD'] = 0;
      $data['utilizationAmountActEstNetUSD'] = 0;
      $data['creditAmountGrossUSD'] = 0;
      $data['creditAmountGrossLocal'] = 0;

      $data['utilize_value_total'] = 0;

      $data['utilize_local_value_total'] = 0;
      $data['utilize_estimate_local_value'] = 0;
      $data['utilize_actual_local_value'] = 0;

      $data['utilize_percentage_total'] = 0;
      $data['utilize_total_net_value'] = 0;
      $data['utilize_pending_estimate_net_value'] = 0;
      $data['utilize_pending_actual_net_value'] = 0;
      $data['firstEstimatedUtilizationDate'] = 0;
      $data['finalEstimatedUtilizationDate'] = 0;
      $data['firstActualUtilizationDate'] = 0;
      $data['finalActualUtilizationDate'] = 0;
      $data['summary']['total_all']['monetized']['totalCount'] = 0;
      $data['summary']['total_all']['monetized']['totalSize'] = 0;
      $data['summary']['total_all']['monetized']['totalPrice'] = 0;
      $data['summary']['total_all']['monetized']['totalCreditsCount'] = 0;
      $data['summary']['total_active']['monetized']['totalCount'] = 0;
      $data['summary']['total_active']['monetized']['totalSize'] = 0;
      $data['summary']['total_active']['monetized']['totalPrice'] = 0;
      $data['summary']['total_active']['monetized']['totalCreditsCount'] = 0;
      $data['summary']['total_archived']['monetized']['totalCount'] = 0;
      $data['summary']['total_archived']['monetized']['totalSize'] = 0;
      $data['summary']['total_archived']['monetized']['totalPrice'] = 0;
      $data['summary']['total_archived']['monetized']['totalCreditsCount'] = 0;
      foreach($data['trades'] as $t) {
        if($t['tradeIsEstimated'] == 1) {
          $data['utilize_estimate_count']++;
          $data['utilize_estimate_value'] += $t['tradeSizeUSD'];
          $data['utilize_estimate_local_value'] += $t['tradeSizeLocal'];
          //$data['utilize_pending_estimate_net_value'] += $t['tradeSizeUSD_net'];
          $data['firstEstimatedUtilizationDate'] = (strtotime($t['timeStamp']) < $data['firstEstimatedUtilizationDate'] || $data['firstEstimatedUtilizationDate'] == 0) ? strtotime($t['timeStamp']) : $data['firstEstimatedUtilizationDate'];
          $data['finalEstimatedUtilizationDate'] = (strtotime($t['timeStamp']) > $data['finalEstimatedUtilizationDate']) ? strtotime($t['timeStamp']) : $data['finalEstimatedUtilizationDate'];
        } else {
          $data['utilize_actual_count']++;
          $data['utilize_actual_value'] += $t['tradeSizeUSD'];
          $data['utilize_actual_value_local'] += $t['tradeSizeLocal'];
          $data['utilize_actual_local_value'] += $t['tradeSizeLocal'];
          $data['utilize_pending_actual_net_value'] += $t['tradeSizeUSD_net'];
          $data['firstActualUtilizationDate'] = (strtotime($t['timeStamp']) < $data['firstActualUtilizationDate'] || $data['firstActualUtilizationDate'] == 0) ? strtotime($t['timeStamp']) : $data['firstActualUtilizationDate'];
          $data['finalActualUtilizationDate'] = (strtotime($t['timeStamp']) > $data['finalActualUtilizationDate']) ? strtotime($t['timeStamp']) : $data['finalActualUtilizationDate'];
          //Since this is an actual trade, we check this array total
          if(!in_array($data['listingId'], $tradesCreditArray)) {
            $data['summary']['total_all']['monetized']['totalCreditsCount']++;
            if($data['cArchived'] == 1) {
              $data['summary']['total_archived']['monetized']['totalCreditsCount']++;
            } else {
              $data['summary']['total_active']['monetized']['totalCreditsCount']++;
            }
            array_push($tradesCreditArray, $data['listingId']);
          }
          //General summary for utilizations
          $data['summary']['total_all']['monetized']['totalCount']++;
          $data['summary']['total_all']['monetized']['totalSize'] += $t['tradeSizeUSD'];
          $data['summary']['total_all']['monetized']['totalPrice'] += $t['tradeSizeUSD'] * $t['tradePrice'];
          if($data['cArchived'] == 1) {
            $data['summary']['total_archived']['monetized']['totalCount']++;
            $data['summary']['total_archived']['monetized']['totalSize'] += $t['tradeSizeUSD'];
            $data['summary']['total_archived']['monetized']['totalPrice'] += $t['tradeSizeUSD'] * $t['tradePrice'];
          } else {
            $data['summary']['total_active']['monetized']['totalCount']++;
            $data['summary']['total_active']['monetized']['totalSize'] += $t['tradeSizeUSD'];
            $data['summary']['total_active']['monetized']['totalPrice'] += $t['tradeSizeUSD'] * $t['tradePrice'];
          }
        }
        $data['utilize_total_count']++;
        $data['utilize_value_total'] += $t['tradeSizeUSD'];
        $data['utilize_local_value_total'] += $t['tradeSizeLocal'];
        $data['utilize_percentage_total'] += $t['tradePrice'];
        $data['utilize_total_net_value'] += $t['tradeSizeUSD_net'];
        $data['utilizationAmountActEstGrossUSD'] += $t['tradeSizeUSD'];
        $data['utilizationAmountActEstNetUSD'] += $t['tradeSizeUSD_net'];
        $data['creditAmountGrossUSD'] += $t['tradeSizeGrossUSD'];
        $data['creditAmountGrossLocal'] += $t['tradeSizeGrossLocal'];

        if($t['tradeIsEstimated'] != 1) {
          $data = $this->Trading->processUtilizationTypes($t, $data);
        }

      }

      //Get total credit used
      $data['totalTradeAmount'] = $data['utilize_actual_value'];
      $data['totalCreditUsed'] = $data['utilize_actual_value'];

      $data['calculatedAmountRemaining'] = $data['amountLocal'] - $data['totalCreditUsed'];
      $data['estAmtRemaining'] = ($data['amountLocal'] - $data['utilize_value_total']) * $data['estCreditPrice'];

      //Start - This is exists only because 'availableToList' doesn't support decimals... remove this as soon as it does.
      $cWhole = floor($data['amountLocal']);
      $cDecimal = $data['amountLocal'] - $cWhole;
      $availableToListPlusDecimal = ($cDecimal > 0.49) ? $data['amountUSDRemaining'] - 1 + $cDecimal : $data['amountUSDRemaining'];
      //End

      $credit_amount_not_estimated_utilized = $availableToListPlusDecimal - $data['utilize_estimate_value']; //Remaining Gross USD subtracted by the total estimated gross amount USD - the result is the amount of credit that has NOT been estimated yet
      $data['credit_amount_not_estimated_utilized'] = ($credit_amount_not_estimated_utilized > 0) ? $credit_amount_not_estimated_utilized : 0;
      //This is the estimated value of the remaining amount of USD credit that has NOT been actually utilized yet. For example, i've got a 1,000,000 credit --> I've actually utilized 500,000 --> and i've already estimated utlizations of 250,000 gross at .90 value --> SO, this would be (250,000 * 0.90) PLUS (250,000 * budgeted exchange rate)
      //$data['utilize_pending_estimate_net_value'] += ($credit_amount_not_estimated_utilized > 0) ? $credit_amount_not_estimated_utilized * $data['estCreditPrice'] : 0; //this is: (1) total NET value of all utilizations on this credit, PLUS (2) the remainder of this credit that has NOT been actualized and has NOT been estimated which is multiplied by the budgedted exchange rate on the credit.
      $data['utilize_total_net_value'] += ($credit_amount_not_estimated_utilized > 0) ? $credit_amount_not_estimated_utilized * $data['estCreditPrice'] : 0;

      //overrirde previous commented out logif for FOx's sake... to be reviewed later
      $data['utilize_pending_estimate_net_value'] = $data['amountValueUSD'] - $data['utilize_pending_actual_net_value'];

      $amountRemainingLocal = ($data['amountLocal'] > $data['creditAmountGrossLocal']) ? $data['amountLocal'] - $data['creditAmountGrossLocal'] : 0;
      $data['creditAmountGrossUSD'] += ($amountRemainingLocal > 0) ? $amountRemainingLocal * $data['budgetExchangeRate'] : 0;
      $data['creditAmountGrossLocal'] += $amountRemainingLocal;

      $data['credit_amount_not_actual_utilized'] = $data['availableToList'];

      //These next functions are in BOTH individual credit call AND summary call because they do different things
      $inputConfigs['fieldName'] = 'statusMonetization';
      $inputConfigs['fieldNameDB'] = 'monetizationStatus';
      $statusMonetizationProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs, 1);
      $data = $statusMonetizationProcessed['data'];
      $return = $statusMonetizationProcessed['return'];

      $inputConfigs['fieldName'] = 'projectStatusId';
      $inputConfigs['fieldNameDB'] = 'projectStatus';
      $statusProjectProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs, 1);
      $data = $statusProjectProcessed['data'];
      $return = $statusProjectProcessed['return'];

      $inputConfigs['fieldName'] = 'auditStatusId';
      $inputConfigs['fieldNameDB'] = 'auditStatus';
      $statusAuditProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs, 1);
      $data = $statusAuditProcessed['data'];
      $return = $statusAuditProcessed['return'];

      $inputConfigs['fieldName'] = 'certificationStatus';
      $inputConfigs['fieldNameDB'] = 'certificationStatus';
      $statusCertificationProcessed = $this->processStatusAnalytics($data, $return, $inputConfigs, 1);
      $data = $statusCertificationProcessed['data'];
      $return = $statusCertificationProcessed['return'];

      $creditDatesProcessed = $this->creditDatesProcessed($data, $return, 1);
      $data = $creditDatesProcessed['data'];
      $return = $creditDatesProcessed['return'];
      $incentiveProgramAdd = false;

      $data['complianceLastYear'] = ($data['requiredComplianceTerm'] > 0 && $data['complianceFirstYear'] > 0) ? $data['complianceFirstYear'] + $data['requiredComplianceTerm'] : null;
      //Credit ID
      $data['creditIdFull'] = $data['State'] . $data['listingId'];

      if($data['taxpayerId'] > 0) {
        $data['tpNameToUse'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . " " . $data['tpLastName'];
      } else {
        $data['taxpayerId'] = 0;
        $data['tpAccountType'] = 1;
        $data['tpCompanyName'] = $data['companyName'];
        $data['tpFirstName'] = $data['companyName'];
        $data['tpLastName'] = '';
        $data['tpNameToUse'] = '';
      }
      $data['legalEntityFiscalYearEnd'] = ($data['tpFiscalYearEndMonth'] != "" && $data['tpFiscalYearEndDay'] > 0) ? $data['tpFiscalYearEndMonth'] . " " . $data['tpFiscalYearEndDay'] : $data['tpFiscalYearEndMonth'];
      $data['loadedByName'] = $data['sellerAdminUserFirstName'] . " " . $data['sellerAdminUserLastName'];
      $data['creditAmountStatus'] = ($data['finalCreditAmountFlag'] == 1) ? "Final" : "Estimate";
      if($data['status'] == 3) {
        $data['statusText'] = "Selling";
        if($data['listed'] == null || $data['listed'] == 0) {
          $data['statusIconClass'] = "yellowDotSmall";
        } else {
          $data['statusIconClass'] = "greenDotSmall";
        }
      } else {
        if($data['status'] == 9) {
          $data['statusText'] = "Sold";
          $data['statusIconClass'] = "icon-ok f20 greenText";
        } else {
          $data['statusText'] = "Privately Managed";
          $data['statusIconClass'] = "icon-lock listLock f20";
        }
      }

      //BROKER LISTED STATUS
      $data['listedOnDealRoom'] = false;
      if($data['brokerDmaId'] > 0 && $data['status'] == 3 && $data['traded'] != 1 && $data['traded'] == "" && $data['listed'] == 1) {
        $data['listedOnDealRoom'] = true;
      } else {
        if($data['status'] == 9) {
        } else {
          $data['listedOnDealRoom'] = false;
        }
      }

      array_push($return['credit'], $data);

    }

    if(sizeof($return['credit']) > 0) {
      $return['credit'] = $return['credit'][0];
    }

    return $return;

  }

  function check_credit_exists_from_internal_id_for_account($input) {

    $select = 'PendingListings.listingId';

    $this->db->select($select);

    $this->db->where('PendingListings.internalId', $input['internalId']);

    $this->credit_where_owner($input);

    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->from('PendingListings');
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');

    $query = $this->db->get();

    $records = $query->result_array();

    $return = [];
    $return['status'] = 'fail';
    $return['message'] = '';
    $return['listingId'] = '';

    if(count($records) == 1) {
      $return['status'] = 'success';
      $return['listingId'] = $records[0]['listingId'];
    } else if(count($records) > 1) {
      $return['status'] = 'duplicated';
      $return['message'] = 'More than one credit with this internal ID exists. Please ensure your credits have unique Internal IDs.';
    } else {
      $return['status'] = 'not_found';
      $return['message'] = 'No matching credit with an Internal ID of ' . $input['internalId'] . ' is found within this account. Please load or assign an existing credit to this Internal ID and try again.';
    }

    return $return;

  }

  function buildEventHtml($thisArray, $template) {
    if($template == "standard") {
      return "<a class='blueText'  href='" . $thisArray['actionLink'] . "'>" . $thisArray['eventTitle'] . "</a> (" . $thisArray['listingFull'] . " - " . $thisArray['projectName'] . ")";
    }
    if($template == "standard_noproject") {
      return "<a class='blueText'  href='" . $thisArray['actionLink'] . "'>" . $thisArray['eventTitle'] . "</a>";
    }
    if($template == "task") {
      return "<a class='blueText'  href='" . $thisArray['actionLink'] . "'>" . $thisArray['eventTitle'] . "</a> (" . $thisArray['listingFull'] . " - " . $thisArray['projectName'] . $thisArray['taskAssignedTo'] . ")";
    }
    if($template == "task_noproject") {
      return "<a class='blueText'  href='" . $thisArray['actionLink'] . "'>" . $thisArray['eventTitle'] . "</a>";
    }
  }

  function get_credits_calendar_dates($input) {

    $input = $this->credit_input($input);

    $creditIds = $this->get_my_credit_ids($input);

    $tbl_name = 'Credits';
    $credits = [];

    if(sizeof($creditIds) > 0) {
      // Get the all row data from Redis by iterating row keys.
      foreach($creditIds as $creditId) {

        $getFromDatabaseFlag = true;
//        $hkey = $tbl_name . ':' . $creditId['listingId'];
//        $row = $this->redis->hgetall($hkey);
//        if(!empty($row)) {
//          $creditDecoded = json_decode($row['credit'], true);
//          //If cache updated time matches time pulled from database, then use cache
//          if($creditDecoded['updatedTime'] != $creditId['updatedTime']) {
//            $getFromDatabaseFlag = true;
//          } else {
//            $getFromDatabaseFlag = false;
//            array_push($credits, $creditDecoded);
//          }
//        }
        if($getFromDatabaseFlag) {
          $cInput = $input;
          $cInput['listingId'] = $creditId['listingId'];
          $creditRefresh = $this->get_credit_data($cInput);
          array_push($credits, $creditRefresh['credit']);
//          $creditRefreshed = [];
//          $creditRefreshed['credit'] = json_encode($creditRefresh['credit']);
//          $this->redis->hmset($hkey, $creditRefreshed);
//          // Push the row id to the list
//          $this->redis->rpush($tbl_name, $creditId['listingId']);
        }

      }
    }

    //handle order of that list (filters will be below in loop)
    $records = $this->cache_credit_order($input, $credits);

    $return = [];
    $return['credits'] = [];
    $return['allEvents'] = [];
    $return['futureEvents'] = [];

    //Get system available data points
    $dataPoints = [];
    $sDataKeys = [];
    $dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
    $dpRequest['dpObjectType'] = 'credit';
    $dataPointsRaw = $this->get_data_points($dpRequest);
    $dataPoints = $dataPointsRaw['dataPoints'];
    $sDataKeys = $dataPointsRaw['dataPointsKeysOnly'];

    foreach($records as $data) {

      //First, get some data in realtime (non-cached) before filtering
      //If this is NOT the owner account of the credit, then get MY SHARE - this is NOT part of the cache because "my" is relative (could be several shares on a credit)
      $data['myShare'] = [];
      if($this->cisession->userdata('dmaId') != $data['listedBy']) {
        foreach($data['shares'] as $share) {
          if($share['sharedPrimId'] == $this->cisession->userdata('dmaId')) {
            //Only inserting the permissions into an array called "My Share"
            $data['myShare']['sharerPrimId'] = $share['sharerPrimId'];
            $data['myShare']['sharedPrimId'] = $share['sharedPrimId'];
            $data['myShare']['sDeleteMarker'] = $share['sDeleteMarker'];
          }
        }
      }

      $thisInclude = $this->credit_where_cache_filters($data, $input);
      if(!$thisInclude) {
        continue;
      }

      //First, get some generic information

      if($data['taxpayerId'] > 0) {
        $data['tpNameToUse'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . " " . $data['tpLastName'];
      } else {
        $data['taxpayerId'] = 0;
        $data['tpAccountType'] = 1;
        $data['tpCompanyName'] = $data['companyName'];
        $data['tpFirstName'] = $data['companyName'];
        $data['tpLastName'] = '';
        $data['tpNameToUse'] = '';
      }

      if($data['status'] == 3) {
        $data['statusText'] = "Selling";
      } else {
        if($data['status'] == 9) {
          $data['statusText'] = "Sold";
        } else {
          $data['statusText'] = "Privately Managed";
        }
      }

      $data['loadedByName'] = $data['sellerAdminUserFirstName'] . " " . $data['sellerAdminUserLastName'];
      $data['creditAmountStatus'] = ($data['finalCreditAmountFlag'] == 1) ? "Final" : "Estimate";

      $data['creditExpirationDate'] = ($data['taxYear'] > 0 && $data['CarryForwardYears'] > 0) ? "12/31/" . ($data['taxYear'] + $data['CarryForwardYears']) : ($data['taxYear'] > 0 && $data['CarryForwardYears'] == 0 ? "12/31/" . $data['taxYear'] : null);

      //Second, get all sub-data

      $data['bids'] = $this->get_market_on_listing_new($data['listingId']);
      $data['workflow'] = $this->get_workflow_list_items_for_listing($data['listingId'], '', 'workflow');
      $data['compliance'] = $this->get_workflow_list_items_for_listing($data['listingId'], '', 'compliance');

      /*
			$dpRequest['dpDmaIdCustom'] = $this->cisession->userdata('dmaId');
			$dpRequest['listingId'] = $data['listingId'];
			$customDataPoints = $this->get_data_points($dpRequest);
			$data['customDataPoints'] = $customDataPoints['dataPoints'];
			*/

      //Third, Loop through data and sub-data and build calendar events

      //////////////////////////////
      /////////  EVENTS   //////////
      //////////////////////////////
      // listing - offer expiration date
      // listing - credit issue date
      // listing - initial and final cert dates
      // listing - project start and end dates
      // listing - estimated payment date
      // bids - expiration dates on bids on my credits
      // signatures - signatures due
      // harvests - estimated and actual payment dates
      // workflow items - dates on any "date" type workflow items
      //////////////////////////////

      // listing - credit expiration date
      if($data['creditExpirationDate'] != '') {
        $thisDate = strtotime($data['creditExpirationDate']);
        $thisArray = [];
        $thisArray['type'] = 'credit_expiration';
        $thisArray['dateUnix'] = $thisDate;
        $thisArray['dateYear'] = date('Y', $thisDate);
        $thisArray['dateMonth'] = date('m', $thisDate);
        $thisArray['dateDay'] = date('d', $thisDate);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDate));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisDate);
        $thisArray['mdy'] = date('m-d-Y', $thisDate);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Credit Expiration";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($thisDate > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - offer expiration date
      $isListed = ($data['brokerDmaId'] > 0 && $data['listed'] == 1 && $data['traded'] != 1);
      if($isListed && $data['OfferGoodUntil'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_listing_expiration';
        $thisArray['dateUnix'] = $data['OfferGoodUntil'];
        $thisArray['dateYear'] = date('Y', $data['OfferGoodUntil']);
        $thisArray['dateMonth'] = date('m', $data['OfferGoodUntil']);
        $thisArray['dateDay'] = date('d', $data['OfferGoodUntil']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['OfferGoodUntil']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['OfferGoodUntil']);
        $thisArray['mdy'] = date('m-d-Y', $data['OfferGoodUntil']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Listing Expiration on Deal Room";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['OfferGoodUntil'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - credit issue date
      if($data['IssueDate'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_issue_date';
        $thisArray['dateUnix'] = $data['IssueDate'];
        $thisArray['dateYear'] = date('Y', $data['IssueDate']);
        $thisArray['dateMonth'] = date('m', $data['IssueDate']);
        $thisArray['dateDay'] = date('d', $data['IssueDate']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['IssueDate']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['IssueDate']);
        $thisArray['mdy'] = date('m-d-Y', $data['IssueDate']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Credit Issue Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['IssueDate'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - initial cert date
      if($data['est_initial_cert_dt'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_initial_cert_date';
        $thisArray['dateUnix'] = $data['est_initial_cert_dt'];
        $thisArray['dateYear'] = date('Y', $data['est_initial_cert_dt']);
        $thisArray['dateMonth'] = date('m', $data['est_initial_cert_dt']);
        $thisArray['dateDay'] = date('d', $data['est_initial_cert_dt']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['est_initial_cert_dt']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['est_initial_cert_dt']);
        $thisArray['mdy'] = date('m-d-Y', $data['est_initial_cert_dt']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Credit Estimated Initial Certification Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['est_initial_cert_dt'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - final cert date
      if($data['est_final_cert_dt'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_final_cert_date';
        $thisArray['dateUnix'] = $data['est_final_cert_dt'];
        $thisArray['dateYear'] = date('Y', $data['est_final_cert_dt']);
        $thisArray['dateMonth'] = date('m', $data['est_final_cert_dt']);
        $thisArray['dateDay'] = date('d', $data['est_final_cert_dt']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['est_final_cert_dt']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['est_final_cert_dt']);
        $thisArray['mdy'] = date('m-d-Y', $data['est_final_cert_dt']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Credit Estimated Final Certification Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['est_final_cert_dt'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - project start date
      if($data['projectStartDate'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_project_start_date';
        $thisArray['dateUnix'] = $data['projectStartDate'];
        $thisArray['dateYear'] = date('Y', $data['projectStartDate']);
        $thisArray['dateMonth'] = date('m', $data['projectStartDate']);
        $thisArray['dateDay'] = date('d', $data['projectStartDate']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['projectStartDate']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['projectStartDate']);
        $thisArray['mdy'] = date('m-d-Y', $data['projectStartDate']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Project Start Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['projectStartDate'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - project end date
      if($data['projectEndDate'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_project_end_date';
        $thisArray['dateUnix'] = $data['projectEndDate'];
        $thisArray['dateYear'] = date('Y', $data['projectEndDate']);
        $thisArray['dateMonth'] = date('m', $data['projectEndDate']);
        $thisArray['dateDay'] = date('d', $data['projectEndDate']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['projectEndDate']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['projectEndDate']);
        $thisArray['mdy'] = date('m-d-Y', $data['projectEndDate']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Project End Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['projectEndDate'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - audit start date
      if($data['auditStartDate'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_audit_start_date';
        $thisArray['dateUnix'] = $data['auditStartDate'];
        $thisArray['dateYear'] = date('Y', $data['auditStartDate']);
        $thisArray['dateMonth'] = date('m', $data['auditStartDate']);
        $thisArray['dateDay'] = date('d', $data['auditStartDate']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['auditStartDate']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['auditStartDate']);
        $thisArray['mdy'] = date('m-d-Y', $data['auditStartDate']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Audit Start Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['auditStartDate'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - audit end date
      if($data['auditEndDate'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_audit_end_date';
        $thisArray['dateUnix'] = $data['auditEndDate'];
        $thisArray['dateYear'] = date('Y', $data['auditEndDate']);
        $thisArray['dateMonth'] = date('m', $data['auditEndDate']);
        $thisArray['dateDay'] = date('d', $data['auditEndDate']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['auditEndDate']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['auditEndDate']);
        $thisArray['mdy'] = date('m-d-Y', $data['auditEndDate']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Audit End Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['auditEndDate'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - estimated payment date
      if($data['estimated_payment_date'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'credit_estimated_payment_date';
        $thisArray['dateUnix'] = $data['estimated_payment_date'];
        $thisArray['dateYear'] = date('Y', $data['estimated_payment_date']);
        $thisArray['dateMonth'] = date('m', $data['estimated_payment_date']);
        $thisArray['dateDay'] = date('d', $data['estimated_payment_date']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['estimated_payment_date']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['estimated_payment_date']);
        $thisArray['mdy'] = date('m-d-Y', $data['estimated_payment_date']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Estimated Payment Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['estimated_payment_date'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // listing - last day principal photography
      if($data['lastDayPrincipalPhotography'] > 0) {
        $thisArray = [];
        $thisArray['type'] = 'lastDayPrincipalPhotography';
        $thisArray['dateUnix'] = $data['lastDayPrincipalPhotography'];
        $thisArray['dateYear'] = date('Y', $data['lastDayPrincipalPhotography']);
        $thisArray['dateMonth'] = date('m', $data['lastDayPrincipalPhotography']);
        $thisArray['dateDay'] = date('d', $data['lastDayPrincipalPhotography']);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $data['lastDayPrincipalPhotography']));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $data['lastDayPrincipalPhotography']);
        $thisArray['mdy'] = date('m-d-Y', $data['lastDayPrincipalPhotography']);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['State'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['State'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Last Day Principal Photography";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($data['lastDayPrincipalPhotography'] > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // loans - engagement start date
      if($data['closingDate'] > 0) {
        $thisDateValue = $data['closingDate'];
        $thisArray = [];
        $thisArray['type'] = 'loan_closingDate';
        $thisArray['dateUnix'] = $thisDateValue;
        $thisArray['dateYear'] = date('Y', $thisDateValue);
        $thisArray['dateMonth'] = date('m', $thisDateValue);
        $thisArray['dateDay'] = date('d', $thisDateValue);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
        $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Engagement Start Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($thisDateValue > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // loans - engagement end date
      if($data['maturityDate'] > 0) {
        $thisDateValue = $data['maturityDate'];
        $thisArray = [];
        $thisArray['type'] = 'loan_maturityDate';
        $thisArray['dateUnix'] = $thisDateValue;
        $thisArray['dateYear'] = date('Y', $thisDateValue);
        $thisArray['dateMonth'] = date('m', $thisDateValue);
        $thisArray['dateDay'] = date('d', $thisDateValue);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
        $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Engagement End Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($thisDateValue > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      // loans - contractSentDate
      if($data['contractSentDate'] > 0) {
        $thisDateValue = $data['contractSentDate'];
        $thisArray = [];
        $thisArray['type'] = 'loan_contractSentDate';
        $thisArray['dateUnix'] = $thisDateValue;
        $thisArray['dateYear'] = date('Y', $thisDateValue);
        $thisArray['dateMonth'] = date('m', $thisDateValue);
        $thisArray['dateDay'] = date('d', $thisDateValue);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
        $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = "Contract Sent Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($thisDateValue > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      $payments = [];
      if($data['payment1Date'] > 0) {
        $paymentArray = [];
        $paymentArray['thisDate'] = $data['payment1Date'];
        $paymentArray['thisType'] = 'loan_payment1Date';
        $paymentArray['thisTitle'] = "Payment Date ($" . number_format($data['payment1Amount']) . ")";
        array_push($payments, $paymentArray);
      }
      if($data['payment1DateEst'] > 0) {
        $paymentArray = [];
        $paymentArray['thisDate'] = $data['payment1DateEst'];
        $paymentArray['thisType'] = 'loan_payment1DateEst';
        $paymentArray['thisTitle'] = "Estimated Payment Date ($" . number_format($data['payment1Amount']) . ")";
        array_push($payments, $paymentArray);
      }
      if($data['payment2Date'] > 0) {
        $paymentArray = [];
        $paymentArray['thisDate'] = $data['payment2Date'];
        $paymentArray['thisType'] = 'loan_payment2Date';
        $paymentArray['thisTitle'] = "Payment Date ($" . number_format($data['payment2Amount']) . ")";
        array_push($payments, $paymentArray);
      }
      if($data['payment2DateEst'] > 0) {
        $paymentArray = [];
        $paymentArray['thisDate'] = $data['payment2DateEst'];
        $paymentArray['thisType'] = 'loan_payment2DateEst';
        $paymentArray['thisTitle'] = "Estimated Payment Date ($" . number_format($data['payment2Amount']) . ")";
        array_push($payments, $paymentArray);
      }
      if($data['payment3Date'] > 0) {
        $paymentArray = [];
        $paymentArray['thisDate'] = $data['payment3Date'];
        $paymentArray['thisType'] = 'loan_payment3Date';
        $paymentArray['thisTitle'] = "Payment Date ($" . number_format($data['payment3Amount']) . ")";
        array_push($payments, $paymentArray);
      }
      if($data['payment3DateEst'] > 0) {
        $paymentArray = [];
        $paymentArray['thisDate'] = $data['payment3DateEst'];
        $paymentArray['thisType'] = 'loan_payment3DateEst';
        $paymentArray['thisTitle'] = "Estimated Payment Date ($" . number_format($data['payment3Amount']) . ")";
        array_push($payments, $paymentArray);
      }

      // loans - paymentdates
      foreach($payments as $p) {
        $thisDateValue = $p['thisDate'];
        $thisArray = [];
        $thisArray['type'] = $p['thisType'];
        $thisArray['dateUnix'] = $thisDateValue;
        $thisArray['dateYear'] = date('Y', $thisDateValue);
        $thisArray['dateMonth'] = date('m', $thisDateValue);
        $thisArray['dateDay'] = date('d', $thisDateValue);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
        $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        if($data['stateCertNum'] != "") {
          $thisArray['projectName'] = $data['stateCertNum'];
        } else {
          $thisArray['projectName'] = "[no project]";
        }
        $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
        if($input['sharedAccount'] > 0) {
          $thisArray['sharedCredit'] = true;
          $thisArray['dmaTitle'] = $data['title'];
        } else {
          $thisArray['sharedCredit'] = false;
          $thisArray['dmaTitle'] = $data['title'];
        }
        $thisArray['eventTitle'] = $p['thisTitle'];
        $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
        $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
        $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
        array_push($return['allEvents'], $thisArray);
        if($thisDateValue > time()) {
          array_push($return['futureEvents'], $thisArray);
        }
      }

      //CUSTOM DATA POINTS
      //Prepare custom data points
      foreach($dataPoints as $cdp) {
        if($cdp['dpType'] == "date") {

          if(isset($data[$cdp['dpValue']]) && $data[$cdp['dpValue']] != "") {

            $thisDate = $data[$cdp['dpValue']];

            $thisArray = [];
            $thisArray['type'] = 'customDataPointDate';
            $thisArray['dateUnix'] = $thisDate;
            $thisArray['dateYear'] = date('Y', $thisDate);
            $thisArray['dateMonth'] = date('m', $thisDate);
            $thisArray['dateDay'] = date('d', $thisDate);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDate));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisDate);
            $thisArray['mdy'] = date('m-d-Y', $thisDate);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'] . $data['listingId'];
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($data['stateCertNum'] != "") {
              $thisArray['projectName'] = $data['stateCertNum'];
            } else {
              $thisArray['projectName'] = "[no project]";
            }
            $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
            if($input['sharedAccount'] > 0) {
              $thisArray['sharedCredit'] = true;
              $thisArray['dmaTitle'] = $data['title'];
            } else {
              $thisArray['sharedCredit'] = false;
              $thisArray['dmaTitle'] = $data['title'];
            }
            $thisArray['eventTitle'] = $cdp['dpNameFull'];
            $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
            $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
            $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
            $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
            array_push($return['allEvents'], $thisArray);
            if($data['auditStartDate'] > time()) {
              array_push($return['futureEvents'], $thisArray);
            }

          }
        }
      }

      // bids - expiration dates on bids on my credits
      if(sizeof($data['bids']) > 0) {
        foreach($data['bids'] as $bid) {
          $thisArray = [];

          if($bid['bidExpirationDate'] != "") {
            $thisBidExp = strtotime($bid['bidExpirationDate']);
            $thisArray = [];
            $thisArray['type'] = 'credit_bid_expiration_date';
            $thisArray['dateUnix'] = $thisBidExp;
            $thisArray['dateYear'] = date('Y', $thisBidExp);
            $thisArray['dateMonth'] = date('m', $thisBidExp);
            $thisArray['dateDay'] = date('d', $thisBidExp);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisBidExp));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisBidExp);
            $thisArray['mdy'] = date('m-d-Y', $thisBidExp);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'] . $data['listingId'];
            $thisArray['bidSize'] = $bid['bidSize'];
            $thisArray['bidPrice'] = $bid['bidPrice'];
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($data['stateCertNum'] != "") {
              $thisArray['projectName'] = $data['stateCertNum'];
            } else {
              $thisArray['projectName'] = "[no project]";
            }
            $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
            if($input['sharedAccount'] > 0) {
              $thisArray['sharedCredit'] = true;
              $thisArray['dmaTitle'] = $data['title'];
            } else {
              $thisArray['sharedCredit'] = false;
              $thisArray['dmaTitle'] = $data['title'];
            }
            $thisArray['eventTitle'] = "Bid Expiration Date";
            $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
            $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/bids";
            $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
            $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
            array_push($return['allEvents'], $thisArray);
            if($thisBidExp > time()) {
              array_push($return['futureEvents'], $thisArray);
            }
          }

        }
      }

      // signatures - signatures due
      if(sizeof($data['trades']) > 0) {
        foreach($data['trades'] as $trade) {

          $thisArray = [];

          //If an OIX trade and in closing process and seller hasn't signed
          /*
          if($trade['utilizationTypeId']==7 && $trade['status']==1 && $trade['sellerSigned']!=1) {
            $thisSigExp = $trade['brokeDate'] + 432000;
            $thisArray=array();
            $thisArray['type'] = 'credit_seller_sig_date';
            $thisArray['dateUnix'] = $thisSigExp;
            $thisArray['dateYear'] = date('Y', $thisSigExp);
            $thisArray['dateMonth'] = date('m', $thisSigExp);
            $thisArray['dateDay'] = date('d', $thisSigExp);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisSigExp));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear']."Q".$thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisSigExp);
						$thisArray['mdy'] = date('m-d-Y', $thisSigExp);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'].$data['listingId'];
            $thisArray['tradeId'] = $trade['tradeId'];
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($trade['cTaxpayerId']>0) {
              if($trade['tpAccountType']==1) {
                $thisArray['signer'] = $trade['tpCompanyName'];
              } else {
                $thisArray['signer'] = $trade['tpFirstName']." ".$trade['LastName'];
              }
            } else {
              $thisArray['signer'] = $trade['buyerCompanyName'];
            }
						if($data['stateCertNum']!="") {
      $thisArray['projectName'] = $data['stateCertNum'];
    } else {
      $thisArray['projectName'] = "[no project]";
    }
						$thisArray['projectName'] .= ($data['projectNameExt']!="") ? " - ".$data['projectNameExt'] : "";
            if($input['sharedAccount']>0) { $thisArray['sharedCredit']=TRUE; $thisArray['dmaTitle']=$data['title']; } else { $thisArray['sharedCredit']=FALSE; $thisArray['dmaTitle']=$data['title']; }
						$thisArray['eventTitle'] = "Signature Due";
						$thisArray['connectedTo'] = $thisArray['listingFull']." - ".$thisArray['projectName'];
						$thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - ".$thisArray['dmaTitle'] : "";
						$thisArray['actionLink'] = base_url()."dashboard/credit/".$thisArray['listingId']."/sale/".$thisArray['tradeId'];
						$thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
						$thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
            if($thisSigExp > time()) {
              array_push($return['futureEvents'], $thisArray);
            }
            array_push($return['allEvents'], $thisArray);
          }
					*/

          //Estimated utilization date
          if($trade['tradeIsEstimated'] == 1 && $trade['tradeDateEstimate'] > 0 && $trade['tradeDateEstimate'] > 0) {

            $thisArray['type'] = 'utilization_estimated_date';
            $estUtilizationDate = $trade['tradeDateEstimate'];
            $estUtilizationValue = $trade['tradeSize'];
            $thisArray['eventTitle'] = "Estimated Utilization Date (Type: " . $trade['utName'] . " - Est. Amt: $" . number_format($estUtilizationValue * $trade['tExchangeRate'] ?? 1) . ")";
            $thisArray['dateUnix'] = $estUtilizationDate;
            $thisArray['dateYear'] = date('Y', $estUtilizationDate);
            $thisArray['dateMonth'] = date('m', $estUtilizationDate);
            $thisArray['dateDay'] = date('d', $estUtilizationDate);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $estUtilizationDate));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $estUtilizationDate);
            $thisArray['mdy'] = date('m-d-Y', $estUtilizationDate);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'] . $data['listingId'];
            $thisArray['harvestValue'] = $estUtilizationValue;
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($data['stateCertNum'] != "") {
              $thisArray['projectName'] = $data['stateCertNum'];
            } else {
              $thisArray['projectName'] = "[no project]";
            }
            $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
            if($input['sharedAccount'] > 0) {
              $thisArray['sharedCredit'] = true;
              $thisArray['dmaTitle'] = $data['title'];
            } else {
              $thisArray['sharedCredit'] = false;
              $thisArray['dmaTitle'] = $data['title'];
            }
            $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
            $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/utilization/" . $trade['tradeId'];
            $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
            $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
            array_push($return['allEvents'], $thisArray);
            if($estUtilizationDate > time()) {
              array_push($return['futureEvents'], $thisArray);
            }

          }

          //Actual utilization date
          if($trade['tradeIsEstimated'] != 1 && $trade['actualUtilizationDate'] > 0) {

            $thisArray['type'] = 'utilization_actual_date';
            $actualUtilizationDate = strtotime($trade['actualUtilizationDate']);
            $actualUtilizationValue = $trade['tradeSize'];
            $thisArray['eventTitle'] = "Actual Utilization Date (Type: " . $trade['utName'] . " - Act. Amt: $" . number_format($actualUtilizationValue * $trade['tExchangeRate'] ?? 1) . ")";
            $thisArray['dateUnix'] = $actualUtilizationDate;
            $thisArray['dateYear'] = date('Y', $actualUtilizationDate);
            $thisArray['dateMonth'] = date('m', $actualUtilizationDate);
            $thisArray['dateDay'] = date('d', $actualUtilizationDate);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $actualUtilizationDate));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $actualUtilizationDate);
            $thisArray['mdy'] = date('m-d-Y', $actualUtilizationDate);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'] . $data['listingId'];
            $thisArray['harvestValue'] = $actualUtilizationValue;
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($data['stateCertNum'] != "") {
              $thisArray['projectName'] = $data['stateCertNum'];
            } else {
              $thisArray['projectName'] = "[no project]";
            }
            $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
            if($input['sharedAccount'] > 0) {
              $thisArray['sharedCredit'] = true;
              $thisArray['dmaTitle'] = $data['title'];
            } else {
              $thisArray['sharedCredit'] = false;
              $thisArray['dmaTitle'] = $data['title'];
            }
            $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
            $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/utilization/" . $trade['tradeId'];
            $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
            $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
            array_push($return['allEvents'], $thisArray);
            if($actualUtilizationDate > time()) {
              array_push($return['futureEvents'], $thisArray);
            }

          }

        }
      }

      // workflow items - dates on any "date" type workflow items
      if(sizeof($data['workflow']) > 0) {
        foreach($data['workflow'] as $wf) {
          $thisArray = [];

          if($wf['wiTempType'] == "date" && $wf['wiValue'] > 0) {
            $thisDateValue = $wf['wiValue'];
            $thisArray = [];
            $thisArray['type'] = 'workflow_item';
            $thisArray['dateUnix'] = $thisDateValue;
            $thisArray['dateYear'] = date('Y', $thisDateValue);
            $thisArray['dateMonth'] = date('m', $thisDateValue);
            $thisArray['dateDay'] = date('d', $thisDateValue);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
            $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'] . $data['listingId'];
            $thisArray['workflowItemTitle'] = $wf['wiTempName'];
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($wf['wiStatus'] == 0) {
              $thisArray['workflowItemStatus'] = "Estimated Date";
            } else {
              if($wf['wiStatus'] == 2) {
                $thisArray['workflowItemStatus'] = "Estimated Date";
              } else {
                if($wf['wiStatus'] == 1) {
                  $thisArray['workflowItemStatus'] = "Completed";
                }
              }
            }
            if($data['stateCertNum'] != "") {
              $thisArray['projectName'] = $data['stateCertNum'];
            } else {
              $thisArray['projectName'] = "[no project]";
            }
            $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
            if($input['sharedAccount'] > 0) {
              $thisArray['sharedCredit'] = true;
              $thisArray['dmaTitle'] = $data['title'];
            } else {
              $thisArray['sharedCredit'] = false;
              $thisArray['dmaTitle'] = $data['title'];
            }
            $thisArray['eventTitle'] = $thisArray['workflowItemTitle'] . " (" . $thisArray['workflowItemStatus'] . ")";
            $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
            $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/workflow/" . $wf['wiId'];
            $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
            $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
            array_push($return['allEvents'], $thisArray);
            if($thisDateValue > time()) {
              array_push($return['futureEvents'], $thisArray);
            }
          }

          if($wf['wiDueDate'] > 0 && $wf['wiStatus'] != 1) {
            $thisDateValue = $wf['wiDueDate'];
            $thisArray = [];
            $thisArray['type'] = 'workflow_item';
            $thisArray['dateUnix'] = $thisDateValue;
            $thisArray['dateYear'] = date('Y', $thisDateValue);
            $thisArray['dateMonth'] = date('m', $thisDateValue);
            $thisArray['dateDay'] = date('d', $thisDateValue);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
            $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
            $thisArray['listingId'] = $data['listingId'];
            $thisArray['listingFull'] = $data['State'] . $data['listingId'];
            $thisArray['workflowItemTitle'] = $wf['wiTempName'];
            $thisArray['sLatitude'] = $data['sLatitude'];
            $thisArray['sLongitude'] = $data['sLongitude'];
            $thisArray['jurisdictionCode'] = $data['State'];
            $thisArray['jurisdictionName'] = $data['name'];
            if($data['stateCertNum'] != "") {
              $thisArray['projectName'] = $data['stateCertNum'];
            } else {
              $thisArray['projectName'] = "[no project]";
            }
            $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
            if($input['sharedAccount'] > 0) {
              $thisArray['sharedCredit'] = true;
              $thisArray['dmaTitle'] = $data['title'];
            } else {
              $thisArray['sharedCredit'] = false;
              $thisArray['dmaTitle'] = $data['title'];
            }
            $thisArray['eventTitle'] = "Task Due: " . $thisArray['workflowItemTitle'];
            $thisArray['taskAssignedTo'] = ($wf['wiAssignedToFirstName'] != '') ? " - Assigned To: " . $wf['wiAssignedToFirstName'] . " " . $wf['wiAssignedToLastName'] : "";
            $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
            $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/workflow/" . $wf['wiId'];
            $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "task");
            $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "task_noproject");
            array_push($return['allEvents'], $thisArray);
            if($thisDateValue > time()) {
              array_push($return['futureEvents'], $thisArray);
            }
          }

        }
      }

      if(sizeof($data['compliance']) > 0) {
        foreach($data['compliance'] as $wf) {
          $thisArray = [];

          //wiCompletedDate
          $thisDateValue = $wf['wiCompletedDate1Expected'];
          $thisArray = [];
          $thisArray['type'] = 'compliance_item';
          $thisArray['dateUnix'] = $thisDateValue;
          $thisArray['dateYear'] = date('Y', $thisDateValue);
          $thisArray['dateMonth'] = date('m', $thisDateValue);
          $thisArray['dateDay'] = date('d', $thisDateValue);
          $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDateValue));
          $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
          $thisArray['ymd'] = date('Y-m-d', $thisDateValue);
          $thisArray['mdy'] = date('m-d-Y', $thisDateValue);
          $thisArray['listingId'] = $data['listingId'];
          $thisArray['listingFull'] = $data['State'] . $data['listingId'];
          $thisArray['workflowItemTitle'] = $wf['wiTempName'];
          $thisArray['sLatitude'] = $data['sLatitude'];
          $thisArray['sLongitude'] = $data['sLongitude'];
          $thisArray['jurisdictionCode'] = $data['State'];
          $thisArray['jurisdictionName'] = $data['name'];
          if($wf['wiStatus'] == 0) {
            $thisArray['workflowItemStatus'] = "Estimated Date";
          } else {
            if($wf['wiStatus'] == 2) {
              $thisArray['workflowItemStatus'] = "Estimated Date";
            } else {
              if($wf['wiStatus'] == 1) {
                $thisArray['workflowItemStatus'] = "Completed";
              }
            }
          }
          if($data['stateCertNum'] != "") {
            $thisArray['projectName'] = $data['stateCertNum'];
          } else {
            $thisArray['projectName'] = "[no project]";
          }
          $thisArray['projectName'] .= ($data['projectNameExt'] != "") ? " - " . $data['projectNameExt'] : "";
          if($input['sharedAccount'] > 0) {
            $thisArray['sharedCredit'] = true;
            $thisArray['dmaTitle'] = $data['title'];
          } else {
            $thisArray['sharedCredit'] = false;
            $thisArray['dmaTitle'] = $data['title'];
          }
          $thisArray['eventTitle'] = $thisArray['workflowItemTitle'] . " (" . $thisArray['workflowItemStatus'] . ")";
          $thisArray['connectedTo'] = $thisArray['listingFull'] . " - " . $thisArray['projectName'];
          $thisArray['connectedTo'] .= ($thisArray['sharedCredit']) ? " - " . $thisArray['dmaTitle'] : "";
          $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/compliance/" . $wf['wiId'];
          $thisArray['eventHtml'] = $this->buildEventHtml($thisArray, "standard");
          $thisArray['eventHtmlNoProject'] = $this->buildEventHtml($thisArray, "standard_noproject");
          array_push($return['allEvents'], $thisArray);
          if($thisDateValue > time()) {
            array_push($return['futureEvents'], $thisArray);
          }
        }

      }

      array_push($return['credits'], $data);

    }

    return $return;

  }

  function getQuarter($month) {
    if($month >= 1 && $month <= 3) {
      $getQuarter = 1;
    }
    if($month >= 4 && $month <= 6) {
      $getQuarter = 2;
    }
    if($month >= 7 && $month <= 9) {
      $getQuarter = 3;
    }
    if($month >= 10 && $month <= 12) {
      $getQuarter = 4;
    }

    return $getQuarter;
  }

  function groom_credit_dates($input) {

    $return = [];
    $return['credits'] = [];

    foreach($input['credits'] as $data) {

      $data['creditExpirationDate'] = ($data['taxYear'] > 0 && $data['CarryForwardYears'] > 0) ? "12/31/" . ($data['taxYear'] + $data['CarryForwardYears']) : ($data['taxYear'] > 0 && $data['CarryForwardYears'] == 0 ? "12/31/" . $data['taxYear'] : null);

      $data['firstEstimatedUtilizationDate'] = 0;
      $data['finalEstimatedUtilizationDate'] = 0;
      $data['firstActualUtilizationDate'] = 0;
      $data['finalActualUtilizationDate'] = 0;
      foreach($data['trades'] as $t) {
        if($t['tradeIsEstimated'] == 1) {
          $data['firstEstimatedUtilizationDate'] = (strtotime($t['timeStamp']) < $data['firstEstimatedUtilizationDate'] || $data['firstEstimatedUtilizationDate'] == 0) ? strtotime($t['timeStamp']) : $data['firstEstimatedUtilizationDate'];
          $data['finalEstimatedUtilizationDate'] = (strtotime($t['timeStamp']) > $data['finalEstimatedUtilizationDate']) ? strtotime($t['timeStamp']) : $data['finalEstimatedUtilizationDate'];
        } else {
          $data['firstActualUtilizationDate'] = (strtotime($t['timeStamp']) < $data['firstActualUtilizationDate'] || $data['firstActualUtilizationDate'] == 0) ? strtotime($t['timeStamp']) : $data['firstActualUtilizationDate'];
          $data['finalActualUtilizationDate'] = (strtotime($t['timeStamp']) > $data['finalActualUtilizationDate']) ? strtotime($t['timeStamp']) : $data['finalActualUtilizationDate'];
        }
      }

      $data['projectStartDateText'] = ($data['projectStartDate'] > 0) ? date('m/d/Y', $data['projectStartDate']) : "";
      $data['projectEndDateText'] = ($data['projectEndDate'] > 0) ? date('m/d/Y', $data['projectEndDate']) : "";
      $data['auditStartDateText'] = ($data['auditStartDate'] > 0) ? date('m/d/Y', $data['auditStartDate']) : "";
      $data['auditEndDateText'] = ($data['auditEndDate'] > 0) ? date('m/d/Y', $data['auditEndDate']) : "";
      $data['est_initial_cert_dtText'] = ($data['est_initial_cert_dt'] > 0) ? date('m/d/Y', $data['est_initial_cert_dt']) : "";
      $data['est_final_cert_dtText'] = ($data['est_final_cert_dt'] > 0) ? date('m/d/Y', $data['est_final_cert_dt']) : "";
      $data['IssueDate'] = ($data['IssueDate'] > 0) ? date('m/d/Y', $data['IssueDate']) : "";
      $data['lastDayPrincipalPhotographyText'] = ($data['lastDayPrincipalPhotography'] > 0) ? date('m/d/Y', $data['lastDayPrincipalPhotography']) : "";
      $data['firstActualUtilizationDateText'] = ($data['firstActualUtilizationDate'] > 0) ? date('m/d/Y', $data['firstActualUtilizationDate']) : "";
      $data['finalActualUtilizationDateText'] = ($data['finalActualUtilizationDate'] > 0) ? date('m/d/Y', $data['finalActualUtilizationDate']) : "";
      $data['firstEstimatedUtilizationDateText'] = ($data['firstEstimatedUtilizationDate'] > 0) ? date('m/d/Y', $data['firstEstimatedUtilizationDate']) : "";
      $data['finalEstimatedUtilizationDateText'] = ($data['finalEstimatedUtilizationDate'] > 0) ? date('m/d/Y', $data['finalEstimatedUtilizationDate']) : "";

      //Advisor Data
      $data['engagementStartDate'] = "";
      $data['engagementEndDate'] = "";
      if($this->cisession->userdata('dmaType') == 'advisor') {
        $myProjectData = $this->get_loans_on_listing_by_account($data['listingId'], '', 2);
        if(count($myProject) > 0) {
          $myProject = $myProjectData[0];
          $data['engagementStartDate'] = $myProject['closingDate'];
          $data['engagementEndDate'] = $myProject['maturityDate'];
          $data['engagementStartDateText'] = ($myProject['closingDate'] > 0) ? date('m/d/Y', $myProject['closingDate']) : "";
          $data['engagementEndDateText'] = ($myProject['maturityDate'] > 0) ? date('m/d/Y', $myProject['maturityDate']) : "";
          $data = array_merge($data, $myProject);
        }
      }

      //Custom Dates
      if(isset($input['customDataPointDates']) && count($input['customDataPointDates']) > 0) {
        foreach($input['customDataPointDates'] as $cdpDate) {
          $thisDateName = $cdpDate['dpValue'];
          $thisDateNameText = $thisDateName . 'Text';
          $data[$thisDateName] = (isset($data[$cdpDate['dpValue']])) ? $data[$cdpDate['dpValue']] : "";
          $data[$thisDateNameText] = (isset($data[$cdpDate['dpValue']])) ? date('m/d/Y', $data[$cdpDate['dpValue']]) : "";
        }
      }

      array_push($return['credits'], $data);

    }

    return $return;

  }

  function get_credits_with_compliance_attached($input) {

    $input = $this->credit_input($input);

    $select = 'PendingListings.listingId, PendingListings.cComplianceId';

    $this->db->select($select);

    $this->credit_where_access($input);
    $this->credit_where_owner($input);
    $this->credit_where_shared_owner($input);

    $this->credit_where_taxpayers($input);
    $this->credit_where_taxyears($input);
    $this->credit_where_jurisdictions($input);
    $this->credit_where_listingId($input);
    $this->credit_where_programs($input);
    $this->credit_where_projects($input);
    $this->credit_where_status($input);
    $this->credit_where_cOrigin($input);
    $this->credit_where_type($input);
    $this->credit_where_countryId($input);
    $this->credit_where_utilization_status($input);
    $this->credit_where_compliance_template_configured($input);
    //Where status
    $this->credit_where_archived($input);
    $this->credit_where_certificationStatus($input);
    $this->credit_where_monetizationStatus($input);
    $this->credit_where_auditStatus($input);
    $this->credit_where_projectStatus($input);
    //$this->credit_where_advisor_status($input);
    //$this->credit_where_customer_access_status($input);
    //Where dates
    $this->credit_where_start_audit_start_date($input);
    $this->credit_where_end_audit_start_date($input);
    $this->credit_where_start_audit_end_date($input);
    $this->credit_where_end_audit_end_date($input);

    $this->credit_where_start_project_start_date($input);
    $this->credit_where_end_project_start_date($input);
    $this->credit_where_start_project_end_date($input);
    $this->credit_where_end_project_end_date($input);

    $this->credit_where_start_credit_issue_date($input);
    $this->credit_where_end_credit_issue_date($input);

    $this->credit_where_start_init_cert_date($input);
    $this->credit_where_end_init_cert_date($input);

    $this->credit_where_start_final_cert_date($input);
    $this->credit_where_end_final_cert_date($input);

    $this->credit_where_start_credit_issue_date($input);
    $this->credit_where_end_credit_issue_date($input);

    $this->credit_where_start_last_day_principal_photo_date($input);
    $this->credit_where_end_last_day_principal_photo_date($input);

    $this->credit_where_start_loaded_date($input);
    $this->credit_where_end_loaded_date($input);

    $this->db->where('ActiveListings.deleteMarker', null);

    $this->db->from('ActiveListings');

    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.pendingListingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    $this->db->join("Accounts", "ActiveListings.listedBy = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts as sellerAdminUser", "PendingListings.cDmaMemberId = sellerAdminUser.userId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    //if($input['getShareData'] || isset($input['sharedAccount'])) {
    //$this->db->join("Shares","PendingListings.listingId = Shares.sItemId", 'left');
    //}

    $this->credit_order($input);

    if($input['limit'] > 0) {
      $this->db->limit($input['limit']);
    }

    $this->db->distinct();

    $query = $this->db->get();

    $return = [];
    $return['credits'] = [];

    foreach($query->result_array() as $data) {

      array_push($return['credits'], $data);

    }

    return $return;

  }

  function get_utilizations($input) {

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;
    $q3 = \OIX\Services\JurisdictionService::$jurisdiciton_google_place_id_query;
    $q4 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_latitude_query;
    $q5 = \OIX\Services\JurisdictionService::$jurisdiciton_gps_longitude_query;

    $input = $this->credit_input($input);

    $select = 'Trades.*, Trades.tradeSize as tradeSizeLocal, utilizationTypes.name as utName, buyerAdminUser.userId as buyerAccountId, buyerAdminUser.companyName as buyerAccountName, buyerDmaAccount.title as buyerDmaTitle, PendingListings.listingId, (PendingListings.creditAmount * PendingListings.budgetExchangeRate) as creditFaceValue, PendingListings.creditAmount as creditFaceValueLocal, PendingListings.availableToList as amountLocalRemaining, PendingListings.budgetExchangeRate, PendingListings.budgetExchangeRate, PendingListings.localCurrency, PendingListings.listedBy, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList, PendingListings.cCarryForwardYears as CarryForwardYears, PendingListings.legislativeFramework, TaxYear.taxYear, PendingListings.listingId as pendingListingId, PendingListings.cOrigin, PendingListings.status as creditStatus, PendingListings.certificationStatus, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.cOrigin, PendingListings.internalId, PendingListings.IssueDate, PendingListings.projectNameExt, ProgramType.ProgramTypeId, ProgramType.ProgramTypeName, Accounts.companyName, dmaAccounts.title, dmaAccounts.dmaId, dmaAccounts.mainAdmin, sellerAdminUser.userId as sellerAdminUserId, sellerAdminUser.firstName as sellerAdminUserFirstName, sellerAdminUser.lastName as sellerAdminUserLastName, Taxpayers.taxpayerId, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, audit_status.auditStatus, project_status.projectStatus';
    $select .= ", $q1 AS stateName, $q1 AS name, $q2 AS state, $q2 AS State, $q1 as jurisdictionName, $q2 as parentJurisdictionState, $q1 as parentJurisdictionName, $q4 as sLatitude, $q5 as sLongitude, $q4 as jurisdiction_lat, $q5 as jurisdiction_lng";
    $select .= ", loc_c.id as countryId, loc_c.name as countryName, loc_c.code as countryCode, loc_p.id as provinceId, loc_p.name as provinceName, loc_co.id as countyId, loc_co.name as countyName, loc_t.id as townId, loc_t.name as townName";

    $this->db->select($select, false);

    $this->credit_where_access($input);
    $this->credit_where_owner($input);
    $this->credit_where_shared_owner($input);
    $this->credit_where_taxpayers($input);
    $this->credit_where_cOrigin($input);
    $this->credit_where_taxyears($input);
    $this->credit_where_jurisdictions($input);
    $this->credit_where_countries($input);
    $this->credit_where_provinces($input);
    $this->credit_where_counties($input);
    $this->credit_where_towns($input);
    $this->credit_where_listingId($input);
    $this->credit_where_projects($input);
    $this->credit_where_status($input);
    $this->credit_where_type($input);
    $this->credit_where_countryId($input);
    //Where status
    $this->credit_where_archived($input);
    $this->credit_where_certificationStatus($input);
    $this->credit_where_monetizationStatus($input);
    $this->credit_where_auditStatus($input);
    $this->credit_where_projectStatus($input);
    //$this->credit_where_advisor_status($input);
    //$this->credit_where_customer_access_status($input);
    //Where utlization data
    $this->utilization_where_status($input);
    $this->utilization_where_type($input);
    $this->utilization_where_start_date($input);
    $this->utilization_where_end_date($input);
    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->where('Trades.deleteMarker', null);

    $this->db->from('Trades');

    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts", "dmaAccounts.primary_account_id = Accounts.userId", 'left');
    $this->db->join("Accounts as sellerAdminUser", "PendingListings.cDmaMemberId = sellerAdminUser.userId", 'left');
    $this->db->join("Accounts as buyerAdminUser", "Trades.accountId = buyerAdminUser.userId", 'left');
    $this->db->join("dmaAccounts as buyerDmaAccount", "Trades.accountId = buyerDmaAccount.mainAdmin", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
    $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');
    //if($input['getShareData'] || isset($input['sharedAccount'])) {
    //$this->db->join("Shares","PendingListings.listingId = Shares.sItemId", 'left');
    //}

    $this->credit_order($input);

    if($input['limit'] > 0) {
      $this->db->limit($input['limit']);
    }

    $this->db->distinct();

    $query = $this->db->get();
    $records = $query->result_array();

    $return = [];
    $return['trades'] = [];
    $return['summary'] = [];
    $return['summary'] = array_merge($return['summary'], $this->Trading->getUtilizationTypesFields());
    $return['summary']['actual']['count'] = 0;
    $return['summary']['actual']['faceValue'] = 0;
    $return['summary']['actual']['estimatedValue'] = 0;
    $return['summary']['estimated']['count'] = 0;
    $return['summary']['estimated']['faceValue'] = 0;
    $return['summary']['estimated']['estimatedValue'] = 0;

    //CUSTOM DATA POINTS - always set to the OWNER of the credit's CDP
    $cdpRequest['dmaId'] = $this->cisession->userdata('dmaId');
    $cdpRequest['dpObjectType'] = 'utilization';
    $customDataPoints = $this->get_custom_data_points($cdpRequest);
    //Check the credit owner's shared Parent Data Points
    if($records[0]['parentDmaId'] > 0) {
      $cdpParentRequest['dmaId'] = $records[0]['parentDmaId'];
      $cdpParentRequest['dpObjectType'] = 'utilization';
      $customDataPointsRawParent = $this->get_custom_data_points($cdpParentRequest);
      $customDataPoints = array_merge($customDataPoints, $customDataPointsRawParent);
    }

    foreach($records as $data) {

      $data = $this->Trading->process_trade_size_data($data);

      //Get CDP values - always set to the OWNER of the credit's CDP
      $data = array_merge($data, $customDataPoints);
      $cdpvRequest['dmaId'] = $this->cisession->userdata('dmaId');
      $cdpvRequest['listingId'] = $data['tradeId'];
      $cdpvRequest['dpObjectType'] = 'utilization';
      $customDataPointValues = $this->get_custom_data_point_values($cdpvRequest);
      if(sizeof($customDataPointValues) > 0) {
        $data = array_merge($data, $customDataPointValues);
      }
      //Check the credit owner's shared Parent Data Points
      if($data['parentDmaId'] > 0) {
        $cdpvRequest['dmaId'] = $data['parentDmaId'];
        $cdpvRequest['listingId'] = $data['tradeId'];
        $cdpvRequest['dpObjectType'] = 'utilization';
        $customDataPointValuesParent = $this->get_custom_data_point_values($cdpvRequest);
        if(sizeof($customDataPointValuesParent) > 0) {
          $data = array_merge($data, $customDataPointValuesParent);
        }
      }

      $data['actualUtilizationDate'] = ($data['tradeIsEstimated'] == 1) ? null : strtotime($data['timeStamp']);
      $data['estimatedUtilizationDate'] = ($data['tradeIsEstimated'] == 1) ? strtotime($data['timeStamp']) : 0;
      $data['tradeEstActStatus'] = ($data['tradeIsEstimated'] == 1) ? 'Estimate' : 'Actual';

      $data['tradePriceTotal'] = $data['tradeSizeUSD_net'];
      $data['utilizationDateUnix'] = strtotime($data['timeStamp']);

      $data['creditIdFull'] = $data['State'] . $data['listingId'];

      $data['currencySymbolData'] = $this->currency->get_currency_data($data['localCurrency']);

      $data['transactions'] = $this->Trading->get_transactions_of_trade($data['tradeId']);

      //$data['transactions'] = $data['transactions'][0];
      $data['buyerName'] = $this->Members_Model->getUserCompanyById($data['buyerAccountId']);

      if($data['tradeType'] == "internal_transfer") {
        $data['tradeTypeName'] = "Internal Trans.";
      } else {
        if($data['tradeType'] == "external_transfer") {
          $data['tradeTypeName'] = "Transfer";
        } else {
          $data['tradeTypeName'] = "Sale on OIX";
        }
      }

      $data['utilizingEntityCustomName'] = '';
      $firstTrans = $data['transactions'][0];
      if($data['utilizingEntityType'] == "myaccounts") {
        $data['utilizingEntityName'] = $data['buyerDmaTitle']; //Buyer is the account
      } else {
        if($data['utilizingEntityType'] == "customname") {
          $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
          $data['utilizingEntityCustomName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
        } else {
          if($data['transactions'][0]['utilizingEntityCustomName'] != "") {
            $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
            $data['utilizingEntityCustomName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
          } else {
            if($firstTrans['taxpayerId'] > 0) {
              $extraText = ($data['tradeType'] == "oix_marketplace_trade") ? "(via " . $data['buyerAccountName'] . ")" : "";
              if(sizeof($data['transactions']) > 1) {
                $data['utilizingEntityName'] = sizeof($data['transactions']) . " Buyers " . $extraText;
              } else {
                $data['utilizingEntityName'] = ($firstTrans['tpAccountType'] == 1) ? $firstTrans['tpCompanyName'] . " " . $extraText : $firstTrans['tpFirstName'] . ' ' . $firstTrans['tpLastName'] . " " . $extraText;
              }
            } else {
              $data['utilizingEntityName'] = $data['buyerAccountName']; //Buyer is this account
            }
          }
        }
      }

      if($data['tradeIsEstimated'] == 1) {
        $return['summary']['estimated']['count']++;
        $return['summary']['estimated']['faceValue'] += $data['tradeSizeUSD'];
        $return['summary']['estimated']['estimatedValue'] += $data['tradeSizeUSD'] * $data['tradePrice'];
      } else {
        $return['summary']['actual']['count']++;
        $return['summary']['actual']['faceValue'] += $data['tradeSizeUSD'];
        $return['summary']['actual']['estimatedValue'] += $data['tradeSizeUSD'] * $data['tradePrice'];
      }

      if($data['tradeIsEstimated'] != 1) {
        $return = $this->Trading->processUtilizationTypes($data, $return);
      }

      if($data['tradeIsEstimated'] == 1) {
        if($data['tradePercentageEstimate'] > 0) {
          if($data['tradePercentageEstimateCompareTo'] == 'facevalue') {
            $data['estimatedUtilizationMetricAsText'] = $data['tradePercentageEstimateWhole'] . '% Percent of Face Value';
          } else {
            $data['estimatedUtilizationMetricAsText'] = $data['tradePercentageEstimateWhole'] . '% Percent of Amount Remaining';
          }
        } else {
          $data['estimatedUtilizationMetricAsText'] = 'Fixed Dollar Amt.';
        }
      } else {
        $data['estimatedUtilizationMetricAsText'] = null;
      }

      if($data['cOrigin'] == "loaded_purchase") {
        $data['cOriginName'] = "Purchased";
      } else {
        $data['cOriginName'] = "Loaded";
      }

      if($data['taxpayerId'] > 0) {
        $data['tpNameToUse'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . " " . $data['tpLastName'];
      } else {
        $data['taxpayerId'] = 0;
        $data['tpAccountType'] = 1;
        $data['tpCompanyName'] = $data['companyName'];
        $data['tpFirstName'] = $data['companyName'];
        $data['tpLastName'] = '';
        $data['tpNameToUse'] = '';
      }

      $data['projectNameFull'] = ($data['projectNameExt'] != "") ? $data['projectName'] . " - " . $data['projectNameExt'] : $data['projectName'];
      $data['loadedByName'] = $data['sellerAdminUserFirstName'] . " " . $data['sellerAdminUserLastName'];

      $data['creditExpirationDate'] = ($data['taxYear'] > 0 && $data['CarryForwardYears'] > 0) ? "12/31/" . ($data['taxYear'] + $data['CarryForwardYears']) : ($data['taxYear'] > 0 && $data['CarryForwardYears'] == 0 ? "12/31/" . $data['taxYear'] : null);

      array_push($return['trades'], $data);

    }

    $totalUtilizationTypesProcessed = $this->Trading->processUtilizationTypeAveragePrices($return);
    $return = $totalUtilizationTypesProcessed['return'];

    return $return;

  }

  function get_utilizations_timeline($input) {

    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $q2 = \OIX\Services\JurisdictionService::$jurisdiciton_code_query;

    $input = $this->credit_input($input);

    $startDate = (isset($input['startDate'])) ? $input['startDate'] : 0;
    $endDate = (isset($input['endDate'])) ? $input['endDate'] : time();
    $tradeType = (isset($input['tradeType'])) ? $input['tradeType'] : null;
    $utitlizationStatus = (isset($input['utitlizationStatus'])) ? $input['utitlizationStatus'] : 0;

    $return = [];

    $startDate = ($startDate > 0) ? $startDate : 1483301821; //1483301821 is Jan 1, 2017
    $endDate = ($endDate > 0) ? $endDate : time();

    $select = 'PendingListings.listingId, PendingListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.estCreditPrice, States.state';
    $select .= ", $q1 AS stateName, $q1 AS name, $q2 AS state, $q2 AS State";
    $select .= ", loc_c.id as countryId, loc_c.name as countryName, loc_c.code as countryCode, loc_p.id as provinceId, loc_p.name as provinceName, loc_co.id as countyId, loc_co.name as countyName, loc_t.id as townId, loc_t.name as townName";

    $this->db->select($select, false);

    $this->credit_where_access($input);
    $this->credit_where_owner($input);
    $this->credit_where_shared_owner($input);
    $this->credit_where_taxpayers($input);
    $this->credit_where_cOrigin($input);
    $this->credit_where_taxyears($input);
    $this->credit_where_jurisdictions($input);
    $this->credit_where_countries($input);
    $this->credit_where_provinces($input);
    $this->credit_where_counties($input);
    $this->credit_where_towns($input);
    $this->credit_where_listingId($input);
    $this->credit_where_projects($input);
    $this->credit_where_status($input);
    $this->credit_where_type($input);
    $this->credit_where_countryId($input);
    //Where status
    $this->credit_where_archived($input);
    $this->credit_where_certificationStatus($input);
    $this->credit_where_monetizationStatus($input);
    $this->credit_where_auditStatus($input);
    $this->credit_where_projectStatus($input);
    //$this->credit_where_advisor_status($input);
    //$this->credit_where_customer_access_status($input);

    //Where dates
    $this->credit_where_start_audit_start_date($input);
    $this->credit_where_end_audit_start_date($input);
    $this->credit_where_start_audit_end_date($input);
    $this->credit_where_end_audit_end_date($input);

    $this->credit_where_start_project_start_date($input);
    $this->credit_where_end_project_start_date($input);
    $this->credit_where_start_project_end_date($input);
    $this->credit_where_end_project_end_date($input);

    $this->credit_where_start_credit_issue_date($input);
    $this->credit_where_end_credit_issue_date($input);

    $this->credit_where_start_init_cert_date($input);
    $this->credit_where_end_init_cert_date($input);

    $this->credit_where_start_final_cert_date($input);
    $this->credit_where_end_final_cert_date($input);

    $this->credit_where_start_credit_issue_date($input);
    $this->credit_where_end_credit_issue_date($input);

    $this->credit_where_start_last_day_principal_photo_date($input);
    $this->credit_where_end_last_day_principal_photo_date($input);

    $this->credit_where_start_loaded_date($input);
    $this->credit_where_end_loaded_date($input);

    $this->db->where('ActiveListings.deleteMarker', null);

    $this->db->join("ActiveListings", "ActiveListings.listingId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    $this->db->join("cert_status_type", "PendingListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("audit_status", "PendingListings.auditStatusId = audit_status.statusId", 'left');
    $this->db->join("project_status", "PendingListings.projectStatus = project_status.statusId", 'left');
    $this->db->join("Accounts", "ActiveListings.listedBy = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.dmaId", 'left');
    $this->db->join("Accounts as sellerAdminUser", "PendingListings.cDmaMemberId = sellerAdminUser.userId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("monetization_status", "PendingListings.statusMonetization = monetization_status.mnsId", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    $this->db->join("jurisdiction as loc_j", "loc_j.id = PendingListings.jurisdiction_id", 'left');
    $this->db->join("location_country as loc_c", "loc_c.id = loc_j.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = loc_j.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = loc_j.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = loc_j.town_id", 'left');
    //if($input['getShareData'] || isset($input['sharedAccount'])) {
    //$this->db->join("Shares","PendingListings.listingId = Shares.sItemId", 'left');
    //}

    $this->db->order_by('PendingListings.estimated_payment_date', 'DESC');
    $query = $this->db->get('PendingListings');

    $return['creditDetails'] = [];
    $return['creditStatusSummary'] = [];

    $return['creditStatusSummary']['totalCreditCount'] = 0;

    $return['creditStatusSummary']['utilizationCountWithEstimate'] = 0;
    $return['creditStatusSummary']['creditCountWithEstimate'] = 0;
    $return['creditStatusSummary']['creditAmountWithEstimate'] = 0;

    $return['creditStatusSummary']['creditCountNotFullyEstimated'] = 0;
    $return['creditStatusSummary']['creditsNotFullyEstimated'] = [];
    $return['creditStatusSummary']['creditCountOverEstimated'] = 0;
    $return['creditStatusSummary']['creditsOverEstimated'] = [];

    $return['creditStatusSummary']['calculatedAmountRemaining'] = 0;
    $return['creditStatusSummary']['estAmtRemaining'] = 0;
    $return['creditStatusSummary']['amountValueEstimateUSD'] = 0;
    $return['creditStatusSummary']['amountValueEstimateUSDWithEstimate'] = 0;

    //defaults
    $startDate = time() - 3000000;
    $endDate = time() + 3000000;

    $estMissingCreditIDTracker = [];
    $creditIDTracker = [];

    foreach($query->result_array() as $data) {

      $return['creditStatusSummary']['totalCreditCount']++;

      //Get Utilizations
      $tradesData = $this->Trading->get_trades_on_listing($data['listingId'], "", $utitlizationStatus);

      //Process - amount remaining on credit
      //First, face value
      $budgetExchangeRate = ($data['budgetExchangeRate'] > 0) ? $data['budgetExchangeRate'] : 1;
      $data['amountUSD'] = $data['amountLocal'] * $data['budgetExchangeRate'];
      $data['totalActualCreditUsed'] = $tradesData['summary']['actual']['faceValue'];
      $data['totalEstimatedCreditUsed'] = $tradesData['summary']['estimated']['faceValue'];
      $data['amountUSDRemaining'] = $data['amountUSD'] - $data['totalActualCreditUsed'];
      //Second, estimated value
      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];
      $data['estAmtRemaining'] = $data['amountUSDRemaining'] * $data['estCreditPrice'];
      $data['amountValueEstimateUSD'] = $data['amountUSD'] * $data['estCreditPrice'];
      //Add it to the summary
      $return['creditStatusSummary']['estAmtRemaining'] += $data['estAmtRemaining']; //estimated value remaining
      $return['creditStatusSummary']['calculatedAmountRemaining'] += $data['amountUSDRemaining']; //face value remaining
      $return['creditStatusSummary']['amountValueEstimateUSD'] += $data['amountValueEstimateUSD']; //Total estimated value

      //Loop through utilizations to build array
      foreach($tradesData['trades'] as $t) {

        if($t['timeStamp'] != "0000-00-00 00:00:00") {
          $data['tradeDateToUse'] = ($utitlizationStatus == 1) ? $t['tradeDateEstimate'] : strtotime($t['timeStamp']);
        } else {
          $data['tradeDateToUse'] = "0000-00-00 00:00:00";
        }

        if($utitlizationStatus == 1 && $t['tradeIsEstimated'] == 0) {
          //If we're filtering by both estimates and actual, then weed out any actuals in this particular loop
        } else {

          //Find the earliest and latest dates in order to use as the timeline spectrum (see loop below)
          if($data['tradeDateToUse'] != "0000-00-00 00:00:00") {
            $startDate = ($data['tradeDateToUse'] < $startDate) ? $data['tradeDateToUse'] : $startDate;
            $endDate = ($data['tradeDateToUse'] > $endDate) ? $data['tradeDateToUse'] : $endDate;
          }
          //Add it to the summary
          $return['creditStatusSummary']['creditAmountWithEstimate'] += $t['tradeSizeUSD']; //Total credit face value with estimate
          $return['creditStatusSummary']['amountValueEstimateUSDWithEstimate'] += $data['amountValueEstimateUSD']; //Total estimated value with estimate
          $return['creditStatusSummary']['utilizationCountWithEstimate']++;
          //Add trade data to array
          $data['tradeId'] = $t['tradeId'];
          $data['tradeSizeUSD'] = $t['tradeSizeUSD'];
          $data['utName'] = $t['utName'];
          $data['tradePercentageEstimate'] = ($utitlizationStatus == 1) ? $t['tradePercentageEstimate'] : "";
          $data['tradePercentageEstimateCompareTo'] = ($utitlizationStatus == 1) ? $t['tradePercentageEstimateCompareTo'] : "";
          $data['tradePercentageEstimateWhole'] = ($utitlizationStatus == 1) ? $t['tradePercentageEstimateWhole'] : "";

          array_push($return['creditDetails'], $data);

        }

      }

      //FINALLY - if there is some credit left to be estimated  - we do 0.999999 because it is possible there is a conversion % on the credit amount leaving a small remainder
      if(!in_array($data['listingId'], $estMissingCreditIDTracker)) {
        //add to array
        $estMissingCreditIDTracker[] = $data['listingId'];
        if($utitlizationStatus == 1 && ($data['amountUSDRemaining'] - ceil($data['totalEstimatedCreditUsed']) > 0.99999)) {
          $return['creditStatusSummary']['creditCountNotFullyEstimated']++;
          $data['amountNotEstimated'] = $data['amountUSDRemaining'] - $data['totalEstimatedCreditUsed'];
          $data['amountNotEstimatedPercent'] = $data['amountNotEstimated'] / $data['amountUSDRemaining'] * 100;
          array_push($return['creditStatusSummary']['creditsNotFullyEstimated'], $data);
        }
      }

    }

    $return['timelineSnapshots'] = [];
    $creditsCountTotal = 0;
    $creditAmountSum = 0;
    $creditAmountEstSum = 0;

    $startDateYear = date('Y', $startDate);
    $startDateMonth = date('m', $startDate);

    $endDateYear = date('Y', $endDate);
    $endDateMonth = date('m', $endDate);

    $dStart = new DateTime(date('Y-m', $startDate));
    $dEnd = new DateTime(date('Y-m', $endDate));
    $periodMonths = $dStart->diff($dEnd)->m + ($dStart->diff($dEnd)->y * 12);
    $periodMonths = $periodMonths + 1; //Add one because we don't just one diff between two months, we want total months between two months (add one to include last month)

    $currMonth = $startDateMonth;
    $currYear = $startDateYear;

    $x = 0;
    $m = 1;
    while($m <= $periodMonths) {
      $currMonthData = DateTime::createFromFormat('!m', $currMonth);
      $currMonthName = $currMonthData->format('F');
      $return['timelineSnapshots'][$x]['time'] = strtotime($currYear . "/" . $currMonth . "/01 00:00:00");
      $return['timelineSnapshots'][$x]['monthName'] = $currYear . " - " . $currMonthName;
      $return['timelineSnapshots'][$x]['creditsIdArray'] = [];
      $return['timelineSnapshots'][$x]['creditsCount'] = 0;
      $return['timelineSnapshots'][$x]['utilizationsCount'] = 0;
      $return['timelineSnapshots'][$x]['thisCalculatedAmountRemaining'] = 0;
      $return['timelineSnapshots'][$x]['creditAmount'] = 0;
      $return['timelineSnapshots'][$x]['calculatedAmountRemaining'] = 0;
      $return['timelineSnapshots'][$x]['creditAmountTotal'] = $return['creditStatusSummary']['creditAmountWithEstimate'];
      $return['timelineSnapshots'][$x]['thisEstAmtRemaining'] = 0;
      $return['timelineSnapshots'][$x]['creditAmountEst'] = 0;
      $return['timelineSnapshots'][$x]['estAmtRemaining'] = 0;
      $return['timelineSnapshots'][$x]['creditAmountEstTotal'] = $return['creditStatusSummary']['amountValueEstimateUSD'];
      if($currMonth == 12) {
        $currMonth = 1;
        $currYear++;
      } else {
        $currMonth++;
      }
      $x++;
      $m++;
    }

    //Now let's re-sort the utilizations by estimated utilization date

    usort($return['creditDetails'], function($a, $b) {
      return $b['tradeDateToUse'] - $a['tradeDateToUse'];
    });

    foreach($return['creditDetails'] as $l) {

      if($l['tradeDateToUse'] != "0000-00-00 00:00:00") {

        //credits Count
        $dThis = new DateTime(date('Y-m', $l['tradeDateToUse']));
        $thisPeriodMonths = $dStart->diff($dThis)->m + ($dStart->diff($dThis)->y * 12);

        if(!in_array($l['listingId'], $creditIDTracker)) {
          //add to array
          $creditIDTracker[] = $l['listingId'];
          //add to summary
          $return['creditStatusSummary']['creditCountWithEstimate']++;
          //If estimate, process anything missing
          if($utitlizationStatus == 1) {
            //if estimated amount is greater than amount remaining
            if($l['totalEstimatedCreditUsed'] > $l['amountUSDRemaining'] + 0.99999999999) {
              $return['creditStatusSummary']['creditCountOverEstimated']++;
              $l['amountOverEstimated'] = $l['totalEstimatedCreditUsed'] - $l['amountUSDRemaining'];
              $amountUSDRemaining = ($l['amountUSDRemaining'] > 0) ? $l['amountUSDRemaining'] : 1;
              $l['amountOverEstimatedPercent'] = $l['amountOverEstimated'] / $amountUSDRemaining * 100;
              array_push($return['creditStatusSummary']['creditsOverEstimated'], $l);
            }

          }

        }

        //get tally for credits in this month
        if(!in_array($l['listingId'], $return['timelineSnapshots'][$thisPeriodMonths]['creditsIdArray'])) {
          $return['timelineSnapshots'][$thisPeriodMonths]['creditsIdArray'][] = $l['listingId'];
          $return['timelineSnapshots'][$thisPeriodMonths]['creditsCount']++;
        }
        $return['timelineSnapshots'][$thisPeriodMonths]['utilizationsCount']++;
        //credits Size
        $return['timelineSnapshots'][$thisPeriodMonths]['creditAmount'] = round($l['tradeSizeUSD']);
        $return['timelineSnapshots'][$thisPeriodMonths]['thisCalculatedAmountRemaining'] = $l['amountUSDRemaining'];
        $return['timelineSnapshots'][$thisPeriodMonths]['calculatedAmountRemaining'] = $l['amountUSDRemaining'];
        //est credits value
        $return['timelineSnapshots'][$thisPeriodMonths]['creditAmountEst'] = $l['amountValueEstimateUSD'];
        $return['timelineSnapshots'][$thisPeriodMonths]['thisEstAmtRemaining'] += round($l['tradeSizeUSD']);
        $return['timelineSnapshots'][$thisPeriodMonths]['estAmtRemaining'] = $l['estAmtRemaining'];

      }

    }

    /* Re-activate this if you want the credit amount to total each loop, which we don't right now
		$last_calculatedAmountRemaining = 0;
		$last_estAmtRemaining = 0;
		$loopCounter = 0;
		foreach($return['timelineSnapshots'] as $ts) {
			//Scale face value remaining
			if($ts['calculatedAmountRemaining']>0) {
				//set the variable to this new value
				$last_calculatedAmountRemaining += $ts['amountUSDRemaining'];
				//save the prior value on this
				$return['timelineSnapshots'][$loopCounter]['calculatedAmountRemaining'] = $last_calculatedAmountRemaining;
			} else {
				//save the prior value on this
				$return['timelineSnapshots'][$loopCounter]['calculatedAmountRemaining'] = $last_calculatedAmountRemaining;
			}
			//Scale estimated value remaining
			if($ts['estAmtRemaining']>0) {
				//set the variable to this new value
				$last_estAmtRemaining += $ts['estAmtRemaining'];
					//save the prior value on this
				$return['timelineSnapshots'][$loopCounter]['estAmtRemaining'] = $last_estAmtRemaining;
			} else {
				//save the prior value on this
				$return['timelineSnapshots'][$loopCounter]['estAmtRemaining'] = $last_estAmtRemaining;
			}
			$loopCounter++;
		}
		*/

    return $return;

  }

  function get_advisor_payments($request) {

    $listedBy = (isset($request['listedBy'])) ? $request['listedBy'] : 0;
    $shared = (isset($request['shared'])) ? $request['shared'] : 0;
    $account = (isset($request['account'])) ? $request['account'] : 0;
    $startDate = (isset($request['startDate'])) ? $request['startDate'] : 0;
    $endDate = (isset($request['endDate'])) ? $request['endDate'] : time();
    $tradeType = (isset($request['tradeType'])) ? $request['tradeType'] : null;
    $advisorStatus = 0;

    $return = [];

    $startDate = ($startDate > 0) ? $startDate : 1483301821; //1483301821 is Jan 1, 2017
    $endDate = ($endDate > 0) ? $endDate : time() + 3301821;

    $select = "PendingListings.listingId, PendingListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.estimated_payment_date, PendingListings.estCreditPrice, States.state, Loans.payment1Amount, Loans.payment1DateEst, Loans.payment1Date, Loans.payment1Received, Loans.payment2Amount, Loans.payment2DateEst, Loans.payment2Date, Loans.payment2Received, Loans.payment3Amount, Loans.payment3DateEst, Loans.payment3Date, Loans.payment3Received, Shares.*";

    $this->db->select($select);

    //$this->db->where('PendingListings.listedBy', $listedBy);
    //$this->db->where('PendingListings.availableToList >', 0); //Any outstanding credit
    /*
		if($startDate>0) {
			$this->db->where('PendingListings.estimated_payment_date >=', $startDate);
		}
		if($endDate>0) {
			$this->db->where('PendingListings.estimated_payment_date <=', $endDate);
		}
		*/
    $this->db->where('ActiveListings.deleteMarker', null);

    if($account > 0) {
      $this->db->where('Shares.sharedPrimId', $shared);
      $this->db->where('PendingListings.listedBy', $account);
      $this->db->where('Shares.sDeleteMarker', null);
    } else {
      $this->db->where('Shares.sharedPrimId', $shared);
      $this->db->where('Shares.sDeleteMarker', null);
    }
    if($advisorStatus == 1) {
      $this->db->where('(Shares.advisorStatus=1 OR Shares.advisorStatus IS NULL)');
      $this->db->where('Shares.sDeleteMarker', null);
    } else {
      if($advisorStatus != "0" && $advisorStatus != "") {
        $this->db->where('Shares.advisorStatus', $advisorStatus);
        $this->db->where('Shares.sDeleteMarker', null);
      }
    }

    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.pendingListingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("Loans", "PendingListings.listingId = Loans.listingId", 'left');
    $this->db->join("Shares", "PendingListings.listingId = Shares.sItemId", 'left');

    //$this->db->order_by('PendingListings.estimated_payment_date','DESC');
    $query = $this->db->get('PendingListings');

    $return = [];
    $return['paymentDetails'] = [];
    $return['paymentStatusSummary'] = [];
    $return['paymentStatusSummary']['feeTotal'] = 0;
    $return['paymentStatusSummary']['feeOutstanding'] = 0;
    $return['paymentStatusSummary']['feePaid'] = 0;
    $return['paymentStatusSummary']['creditCountWithEstimate'] = 0;
    $return['paymentStatusSummary']['creditCountWithoutEstimate'] = 0;
    //keep credit status array in here
    $return['creditDetails'] = [];
    $return['creditStatusSummary'] = [];
    $return['creditStatusSummary']['creditAmount'] = 0;
    $return['creditStatusSummary']['calculatedAmountRemaining'] = 0;
    $return['creditStatusSummary']['estAmtRemaining'] = 0;
    $return['creditStatusSummary']['amountValueEstimateUSD'] = 0;
    $return['creditStatusSummary']['creditCountWithEstimate'] = 0;
    $return['creditStatusSummary']['creditCountWithoutEstimate'] = 0;

    //defaults
    $startDate = time() - 3000000;
    $endDate = time() + 3000000;

    foreach($query->result_array() as $data) {

      //Set the start and end date ranges based on the highest and lowest dates
      $dateRangeArray = ['payment1DateEst', 'payment1Date', 'payment2DateEst', 'payment2Date', 'payment3DateEst', 'payment3Date'];
      foreach($dateRangeArray as $dra) {
        if($data[$dra] > 0) {
          $startDate = ($data[$dra] < $startDate) ? $data[$dra] : $startDate;
          $endDate = ($data[$dra] > $endDate) ? $data[$dra] : $endDate;
        }
      }

      $data['feeTotal'] = 0;
      $data['feeOutstanding'] = 0;
      $data['feePaid'] = 0;

      $return['paymentStatusSummary']['feeTotal'] += $data['payment1Amount'] + $data['payment2Amount'] + $data['payment3Amount']; //Total payment
      $data['feeTotal'] = $data['payment1Amount'] + $data['payment2Amount'] + $data['payment3Amount'];

      if($data['payment1Received'] == 1) {
        $return['paymentStatusSummary']['feePaid'] += $data['payment1Amount']; //Paid
        $data['feePaid'] += $data['payment1Amount'];
      } else {
        $return['paymentStatusSummary']['feeOutstanding'] += $data['payment1Amount']; //Outstanding
        $data['feeOutstanding'] += $data['payment1Amount'];
      }
      if($data['payment2Received'] == 1) {
        $return['paymentStatusSummary']['feePaid'] += $data['payment2Received']; //Paid
        $data['feePaid'] += $data['payment2Amount'];
      } else {
        $return['paymentStatusSummary']['feeOutstanding'] += $data['payment2Received']; //Outstanding
        $data['feeOutstanding'] += $data['payment2Amount'];
      }
      if($data['payment3Received'] == 1) {
        $return['paymentStatusSummary']['feePaid'] += $data['payment3Received']; //Paid
        $data['feePaid'] += $data['payment3Amount'];
      } else {
        $return['paymentStatusSummary']['feeOutstanding'] += $data['payment3Received']; //Outstanding
        $data['feeOutstanding'] += $data['payment3Amount'];
      }

      if($data['feeTotal'] > 0) {
        $return['paymentStatusSummary']['creditCountWithEstimate']++;
      } else {
        $return['paymentStatusSummary']['creditCountWithoutEstimate']++;
      }

      //Get credit estimate
      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      //and keep in the credit data from query above
      //Get total credit used
      $totalTradeAmount = $this->Trading->get_total_trade_amount_on_listing($data['listingId'], $tradeType);
      $data['totalTradeAmount'] = $totalTradeAmount['totalTradeAmount'];
      $totalCreditUsed = $data['totalTradeAmount'];

      $totalUtilizationActualAndEstimate = 0;
      $data['trades'] = $this->Trading->getTradesInfo($data['listingId'], $tradeType);
      foreach($data['trades'] as $t) {
        $totalUtilizationActualAndEstimate += $t['tradeSize'];
      }
      $data['calculatedAmountRemaining'] = $data['creditAmount'] - $totalCreditUsed;
      $data['estAmtRemaining'] = ($data['creditAmount'] - $totalUtilizationActualAndEstimate) * $data['estCreditPrice'];

      $return['creditStatusSummary']['creditAmount'] += $data['creditAmount']; //Total credit face value
      $return['creditStatusSummary']['calculatedAmountRemaining'] += $data['calculatedAmountRemaining']; //face value remaining
      $return['creditStatusSummary']['amountValueEstimateUSD'] += $data['amountValueEstimateUSD']; //Total estimated value
      $return['creditStatusSummary']['estAmtRemaining'] += $data['estAmtRemaining']; //estimated value remaining

      array_push($return['creditDetails'], $data);
    }

    $return['timelineSnapshots'] = [];
    $creditsCountTotal = 0;
    $creditAmountSum = 0;
    $creditAmountEstSum = 0;

    $startDateYear = date('Y', $startDate);
    $startDateMonth = date('m', $startDate);

    $endDateYear = date('Y', $endDate);
    $endDateMonth = date('m', $endDate);

    $dStart = new DateTime(date('Y-m', $startDate));
    $dEnd = new DateTime(date('Y-m', $endDate));
    $periodMonths = $dStart->diff($dEnd)->m + ($dStart->diff($dEnd)->y * 12);
    $periodMonths = $periodMonths + 1; //Add one because we don't just one diff between two months, we want total months between two months (add one to include last month)

    $currMonth = $startDateMonth;
    $currYear = $startDateYear;

    $x = 0;
    $m = 1;
    while($m <= $periodMonths) {
      $currMonthData = DateTime::createFromFormat('!m', $currMonth);
      $currMonthName = $currMonthData->format('F');
      $return['timelineSnapshots'][$x]['time'] = strtotime($currYear . "/" . $currMonth . "/01 00:00:00");
      $return['timelineSnapshots'][$x]['monthName'] = $currYear . " - " . $currMonthName;
      $return['timelineSnapshots'][$x]['paymentsCount'] = 0;
      $return['timelineSnapshots'][$x]['fee'] = 0;
      $return['timelineSnapshots'][$x]['thisFee'] = 0;
      $return['timelineSnapshots'][$x]['feeOutstanding'] = 0;
      $return['timelineSnapshots'][$x]['thisFeeOutstanding'] = 0;
      $return['timelineSnapshots'][$x]['feePaid'] = 0;
      $return['timelineSnapshots'][$x]['thisFeePaid'] = 0;
      if($currMonth == 12) {
        $currMonth = 1;
        $currYear++;
      } else {
        $currMonth++;
      }
      $x++;
      $m++;
    }

    foreach($return['creditDetails'] as $l) {

      $x = 1;
      while($x <= 3) {

        //payments received
        if($l['payment' . $x . 'Received'] && $l['payment' . $x . 'Date'] > 0) {
          $dThis = new DateTime(date('Y-m', $l['payment' . $x . 'Date']));
          $thisPeriodMonths = $dStart->diff($dThis)->m + ($dStart->diff($dThis)->y * 12);
          //Count
          $return['timelineSnapshots'][$thisPeriodMonths]['paymentsCount']++;
          //fees
          $return['timelineSnapshots'][$thisPeriodMonths]['fee'] += $l['payment' . $x . 'Amount'];
          $return['timelineSnapshots'][$thisPeriodMonths]['thisFee'] += $l['payment' . $x . 'Amount'];
          $return['timelineSnapshots'][$thisPeriodMonths]['feeOutstanding'] += 0;
          $return['timelineSnapshots'][$thisPeriodMonths]['thisFeeOutstanding'] += 0;
          $return['timelineSnapshots'][$thisPeriodMonths]['feePaid'] += $l['payment' . $x . 'Amount'];
          $return['timelineSnapshots'][$thisPeriodMonths]['thisFeePaid'] += $l['payment' . $x . 'Amount'];
        } else {
          if($l['payment' . $x . 'DateEst'] > 0) {
            $dThis = new DateTime(date('Y-m', $l['payment' . $x . 'DateEst']));
            $thisPeriodMonths = $dStart->diff($dThis)->m + ($dStart->diff($dThis)->y * 12);
            //Count
            //$return['timelineSnapshots'][$thisPeriodMonths]['paymentsCount']++;
            //fees
            $return['timelineSnapshots'][$thisPeriodMonths]['fee'] += $l['payment' . $x . 'Amount'];
            $return['timelineSnapshots'][$thisPeriodMonths]['thisFee'] += $l['payment' . $x . 'Amount'];
            $return['timelineSnapshots'][$thisPeriodMonths]['feeOutstanding'] += $l['payment' . $x . 'Amount'];
            $return['timelineSnapshots'][$thisPeriodMonths]['thisFeeOutstanding'] += $l['payment' . $x . 'Amount'];
            $return['timelineSnapshots'][$thisPeriodMonths]['feePaid'] += 0;
            $return['timelineSnapshots'][$thisPeriodMonths]['thisFeePaid'] += 0;
          }
        }

        $x++;

      }

      if($return['timelineSnapshots'][$thisPeriodMonths]['fee']) {
        $return['creditStatusSummary']['creditCountWithEstimate']++;
      } else {
        //Count
        $return['creditStatusSummary']['creditCountWithoutEstimate']++;
      }

    }

    $last_fee = 0;
    $last_feeOutstanding = 0;
    $last_feePaid = 0;
    $loopCounter = 0;
    foreach($return['timelineSnapshots'] as $ts) {
      //Scale total fee
      if($ts['fee'] > 0) {
        //set the variable to this new value
        $last_fee += $ts['fee'];
        //save the prior value on this
        $return['timelineSnapshots'][$loopCounter]['fee'] = $last_fee;
      } else {
        //save the prior value on this
        $return['timelineSnapshots'][$loopCounter]['fee'] = $last_fee;
      }
      //Scale fee outstanding
      if($ts['feeOutstanding'] > 0) {
        //set the variable to this new value
        $last_feeOutstanding += $ts['feeOutstanding'];
        //save the prior value on this
        $return['timelineSnapshots'][$loopCounter]['feeOutstanding'] = $last_feeOutstanding;
      } else {
        //save the prior value on this
        $return['timelineSnapshots'][$loopCounter]['feeOutstanding'] = $last_feeOutstanding;
      }
      if($ts['feePaid'] > 0) {
        //set the variable to this new value
        $last_feePaid += $ts['feePaid'];
        //save the prior value on this
        $return['timelineSnapshots'][$loopCounter]['feePaid'] = $last_feePaid;
      } else {
        //save the prior value on this
        $return['timelineSnapshots'][$loopCounter]['feePaid'] = $last_feePaid;
      }
      $loopCounter++;
    }

    return $return;

  }

  function get_workflow_list_items_for_listing($listingId, $wiTempType = "", $wWorkflowType = "") {
    $this->db->select('workflow_items.*, workflow_item_values.*, workflow_item_templates.wiTempName, workflow_item_templates.wiTempType, workflow_item_templates.wiTempSubType, workflow_item_templates.wiTempIsTemp, accountAssignedTo.firstName as wiAssignedToFirstName, accountAssignedTo.lastName as wiAssignedToLastName');
    $this->db->from('workflow_items');
    $array['PendingListings.listingId'] = $listingId;
    $array['workflows.wAttachedToType'] = 'credit';
    if($wiTempType != "") {
      $array['workflow_item_templates.wiTempType'] = $wiTempType;
    }
    if($wWorkflowType != "") {
      $array['workflows.wWorkflowType'] = $wWorkflowType;
    }
    $this->db->where($array);
    $this->db->join("workflow_item_templates", "workflow_items.wiTempId = workflow_item_templates.wiTempId", 'left');
    $this->db->join("workflow_item_values", "workflow_items.wiId = workflow_item_values.wvWorkflowItemId AND workflow_item_values.wvAttachedToId='$listingId'", 'left');
    $this->db->join("workflow_lists", "workflow_items.wiListId = workflow_lists.wlId", 'left');
    $this->db->join("workflows", "workflow_lists.wlWorkflowId = workflows.wId", 'left');
    $this->db->join("Accounts as accountAssignedTo", "workflow_item_values.wiAssignedTo = accountAssignedTo.userId", 'left');
    if($wWorkflowType == "compliance") {
      $this->db->join("PendingListings", "workflows.wId = PendingListings.cComplianceId", 'left');
    } else {
      $this->db->join("PendingListings", "workflows.wId = PendingListings.cWorkflowId", 'left');
    }
    $this->db->order_by("workflow_items.wiOrder ASC");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_credits_of_taxpayer($taxpayerId, $status, $type, $dmaId = "", $dmaMemberId = "") {
    $this->db->select('ActiveListings.listingId, ActiveListings.timeStamp, ActiveListings.OIXIncentiveId, ActiveListings.listed, PendingListings.creditAmount, PendingListings.estCreditPrice, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, ActiveListings.offerSize as size, ActiveListings.offerPrice as price, ActiveListings.originalOfferSize, ActiveListings.allOrNone, ActiveListings.certificationNum, PendingListings.IssueDate, ActiveListings.OfferGoodUntil, ActiveListings.CreditUsedForOffset, offsetName, ActiveListings.CreditUsedDate, ActiveListings.listedBy, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector,OffsetLocked, IncentivePrograms.OffSettingTaxList, TaxYear.taxYear,PendingListings.listingId as pendingListingId, PendingListings.status, PendingListings.certificationStatus, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.projectStartDate, States.countryId, ActiveListings.listingCustomerAssist, ActiveListings.listingAnonymously,States.name, ProgramType.ProgramTypeId, ProgramType.ProgramTypeName, PendingListings.projectNameExt');
    $this->db->where('PendingListings.cTaxpayerId', $taxpayerId);
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    if($status != "") {
      $this->db->where('PendingListings.status', $status);
    } else {
      $this->db->where('PendingListings.status IN (3, 5, 9)');
    }
    if($type != "") {
      $this->db->where('ProgramType.ProgramTypeId', $type);
    }
    if($dmaId > 0 && $dmaMemberId > 0) {
      $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
      $this->db->where($whereAccess);
    }

    $this->db->from('ActiveListings');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.pendingListingId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("ProgramType", "PendingListings.credit_type_id = ProgramType.ProgramTypeId", 'left');
    if($dmaId > 0 && $dmaMemberId > 0) {
      $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
      $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId AND ('open' = creditAccessDmaLevel.caAction OR 'watch' = creditAccessDmaLevel.caAction)", 'left');
    }

    $this->db->order_by("ActiveListings.timestamp desc");

    $query = $this->db->get();

    foreach($query->result() as $data) {

      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      //$data->action = "Active Listing";
      $data->action = ($data->listed == null || $data->listed == 0) ? "Pending" : "Active";

      /*
      $data->estCreditPrice = ($data->estCreditPrice == "" || $data->estCreditPrice == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);
      */

      if($data->listed == 1) {
        $data->trades = $this->Trading->getTradesInfo($data->listingId);
        $data->bids = $this->get_market_on_listing_new($data->listingId);
      } else {
        $data->trades = null;
        $data->bids = null;
      }
    }

    return $query->result();

  }

  function calculate_alert_day($thisDate) {

    date_default_timezone_set('US/Pacific'); //we temporarily set to PT because it means the only potential time issues could be between 12am-3am
    $thisDayZeroHour = strtotime('00:00:00'); //all dates on system are stored at 00:00:00 on that date, so let's compare to that
    date_default_timezone_set('US/Eastern'); //go back to ET
    $diff = $thisDate - $thisDayZeroHour;
    $days = (abs($diff) == 0) ? 0 : ceil($diff / 86400);
    $alertDay = ($days < 6) ? ($days == -0 ? 0 : $days) : -1;

    return $alertDay;

  }

  function insert_credit_calender_alert($msAction, $messageText, $alertDay, $date, $extraData) {

    $alertDayAsText = ($alertDay == 0) ? 'Today' : $alertDay . ' Day';

    //get messages matching this credit value
    $cSearchRequest['msType'] = "calendar_alert";
    $cSearchRequest['msAction'] = $msAction;
    $cSearchRequest['msListingId'] = $extraData["listingId"];
    $cSearchRequest['msTradeId'] = isset($extraData["tradeId"]) ? $extraData["tradeId"] : null;
    $cSearchRequest['msWorkflowItemId'] = isset($extraData["wiId"]) ? $extraData["wiId"] : null;
    //$cSearchRequest['msContent'] = $alertDay;
    //$cSearchRequest['startTime'] = time()-86400; //Only return items within LAST 24 HOURS so if a duplicate record exists in last 24 then don't re-make it (see below)
    $cSearchRequest['distinctMsId'] = 1;
    $matchingMessages = $this->Members_Model->search_messages($cSearchRequest);

    //Mark insert flag as TRUE
    $insertFlag = true;
    $thisYear = date('Y', $date);
    $thisMonth = date('n', $date);
    $thisDay = date('j', $date);

    //Loop through existing messages
    foreach($matchingMessages as $mat) {
      if($mat['msYear'] == $thisYear && $mat['msMonth'] == $thisMonth && $mat['msDay'] == $thisDay && $mat['msContent'] == $alertDay) {
        //If the existing date is the same as new date, then do nothing and insert flag as FALSE (already exists)
        $insertFlag = false;
      } else {
        //Else if the existing date does not equal new date, then delete it
        $this->Members_Model->search_and_delete_alert_messages($mat['msAction'], $extraData["listingId"]);
      }
    }

    //If insert flag is TRUE still, then insert the new record
    if($insertFlag) {

      $projectNameExt = ($extraData['projectNameExt'] != "") ? " - " . $extraData['projectNameExt'] : "";

      //Insert message for calendar
      $msType = "calendar_alert";
      $msAction = $msAction;
      $msListingId = $extraData["listingId"];
      $msBidId = "";
      $msTradeId = (isset($extraData['tradeId'])) ? $extraData['tradeId'] : null;
      $msWorkflowItemId = "";
      $msTitle = $alertDayAsText . " Alert: '" . $messageText . "' due on " . date('m/d/Y', $date) . " (" . $extraData['stateCertNum'] . $projectNameExt . ")";
      $msTitle2 = $alertDayAsText . " Alert: '" . $messageText . "' due on " . date('m/d/Y', $date) . " - " . $extraData['title'] . " (" . $extraData['stateCertNum'] . $projectNameExt . ")";
      $msTitleShared = $msTitle2;
      $msTitleShort = $alertDayAsText . " Alert: '" . $messageText . "' due on " . date('m/d/Y', $date);
      $msTitle2Short = "";
      $msTitleSharedShort = $msTitleShort;
      $msContent = $alertDay;
      $msContent2 = $alertDay;
      $msPerspective = "seller";
      $msPerspective2 = "";
      $firstDmaMainUserId = $extraData['listedBy'];
      $secondDmaMainUserId = "";
      $msUserIdCreated = (null !== ($this->cisession->userdata('userId'))) ? $this->cisession->userdata('userId') : $extaData['listedBy'];
      $alertShared = true;
      $msMessageId = "";
      $keepUnread = 1;
      $msCalendarDate = $date;
      $mmMessageId = $this->Members_Model->insert_message($msType, $msAction, $msTitle, $msTitleShort, $firstDmaMainUserId, $msContent, $msUserIdCreated, $msPerspective, $msListingId, $msBidId, $msTradeId, $secondDmaMainUserId, $msTitle2, $msTitle2Short, $msPerspective2, $alertShared, $msTitleShared, $msTitleShort, $msMessageId, $keepUnread, $msWorkflowItemId, $msCalendarDate);

    }

  }

  function check_credit_for_due_dates($listingId) {

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 1 > Listing ID: ' . $listingId);

    $creditData = $this->get_credit_private($listingId);

    if(sizeof($creditData) > 0) {

      $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 2 > Listing ID: ' . $listingId);

      $deleteArray = [];

      //ISSUE DATE - IssueDate
      $IssueDate = $creditData["IssueDate"];
      $msAction = "IssueDate";
      if($IssueDate > 0) {
        if(($alertDay = $this->calculate_alert_day($IssueDate)) > -1) {
          $messageText = "Estimated Credit Issue Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $IssueDate, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Estimated Initial Cert Date - est_initial_cert_dt
      $est_initial_cert_dt = $creditData["est_initial_cert_dt"];
      $msAction = "est_initial_cert_dt";
      if($est_initial_cert_dt > 0) {
        if(($alertDay = $this->calculate_alert_day($est_initial_cert_dt)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 3 > Listing ID: ' . $listingId . ' > Val: ' . $est_initial_cert_dt);
          $messageText = "Estimated Initial Certification Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $est_initial_cert_dt, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Estimated Final Cert Date - est_final_cert_dt
      $est_final_cert_dt = $creditData["est_final_cert_dt"];
      $msAction = "est_final_cert_dt";
      if($est_final_cert_dt > 0) {
        if(($alertDay = $this->calculate_alert_day($est_final_cert_dt)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 4 > Listing ID: ' . $listingId . ' > Val: ' . $est_final_cert_dt);
          $messageText = "Estimated Final Certification Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $est_final_cert_dt, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Estimated Payment Date - estimated_payment_date
      $estimated_payment_date = $creditData["estimated_payment_date"];
      $msAction = "estimated_payment_date";
      if($estimated_payment_date > 0) {
        if(($alertDay = $this->calculate_alert_day($estimated_payment_date)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 5 > Listing ID: ' . $listingId . ' > Val: ' . $estimated_payment_date);
          $messageText = "Estimated Payment Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $estimated_payment_date, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Estimated Project Start Date - projectStartDate
      $projectStartDate = $creditData["projectStartDate"];
      $msAction = "projectStartDate";
      if($projectStartDate > 0) {
        if(($alertDay = $this->calculate_alert_day($projectStartDate)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 6 > Listing ID: ' . $listingId . ' > Val: ' . $projectStartDate);
          $messageText = "Project Start Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $projectStartDate, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Estimated Project End Date - projectEndDate
      $projectEndDate = $creditData["projectEndDate"];
      $msAction = "projectEndDate";
      if($projectEndDate > 0) {
        if(($alertDay = $this->calculate_alert_day($projectEndDate)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 7 > Listing ID: ' . $listingId . ' > Val: ' . $projectEndDate);
          $messageText = "Project End Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $projectEndDate, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Audit Start Date - auditStartDate
      $auditStartDate = $creditData["auditStartDate"];
      $msAction = "auditStartDate";
      if($auditStartDate > 0) {
        if(($alertDay = $this->calculate_alert_day($auditStartDate)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 8 > Listing ID: ' . $listingId . ' > Val: ' . $auditStartDate);
          $messageText = "Audit Start Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $auditStartDate, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Audit End Date - auditEndDate
      $auditEndDate = $creditData["auditEndDate"];
      $msAction = "auditEndDate";
      if($auditEndDate > 0) {
        if(($alertDay = $this->calculate_alert_day($auditEndDate)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 9 > Listing ID: ' . $listingId . ' > Val: ' . $auditEndDate);
          $messageText = "Audit End Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $auditEndDate, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //OIX Offer Good Until Date - OfferGoodUntil
      $OfferGoodUntil = $creditData["OfferGoodUntil"];
      $msAction = "OfferGoodUntil";
      if($OfferGoodUntil > 0) {
        if(($alertDay = $this->calculate_alert_day($OfferGoodUntil)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 10 > Listing ID: ' . $listingId . ' > Val: ' . $OfferGoodUntil);
          $messageText = "OIX Marketplace Offer Expiration Date";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $OfferGoodUntil, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //Principal Photography Final Day
      $lastDayPrincipalPhotography = $creditData["lastDayPrincipalPhotography"];
      $msAction = "lastDayPrincipalPhotography";
      if($lastDayPrincipalPhotography > 0) {
        if(($alertDay = $this->calculate_alert_day($lastDayPrincipalPhotography)) > -1) {
          $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 11 > Listing ID: ' . $listingId . ' > Val: ' . $lastDayPrincipalPhotography);
          $messageText = "Last Day Principal Photography";
          $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $lastDayPrincipalPhotography, $creditData);
        } else {
          array_push($deleteArray, $msAction);
        }
      } else {
        array_push($deleteArray, $msAction);
      }

      //CUSTOM DATA POINTS
      //Prepare custom data points
      foreach($creditData['customDataPoints'] as $cdp) {
        if($cdp['dpType'] == "date") {
          $msAction = "customDataPointDate_" . $creditData['listingId'] . "_" . $cdp['dpId'];
          $thisDate = $cdp['cvValue'];
          if($thisDate > 0) {
            if(($alertDay = $this->calculate_alert_day($thisDate)) > -1) {
              $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 12 > Listing ID: ' . $listingId . ' > Val: ' . $msAction . ' - ' . $thisDate);
              $messageText = $cdp['dpNameFull'];
              $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $thisDate, $creditData);
            } else {
              array_push($deleteArray, $msAction);
            }
          } else {
            array_push($deleteArray, $msAction);
          }

        }
      }

      //Delete calendar alerts that are no longer within 5 days
      foreach($deleteArray as $da) {
        $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > check_credit_for_due_dates > Step 13 > Listing ID: ' . $listingId);
        $this->Members_Model->search_and_delete_alert_messages($da, $listingId);
      }

    }

  }

  function check_utilization_for_due_dates($tradeId) {

    $tradeDataRaw = $this->Trading->get_trade($tradeId);
    $tradeData = $tradeDataRaw['trade'];

    $deleteArray = [];

    if(sizeof($tradeData) > 0) {

      if($tradeData['tradeIsEstimated'] == 1) {

        //ESTIMATED UTILIZTION DATE - tradeDateEstimate
        $thisDate = $tradeData["tradeDateEstimate"];
        $msAction = "utilization_estimate_update";
        if($thisDate > 0) {
          if(($alertDay = $this->calculate_alert_day($thisDate)) > -1) {
            $messageText = "Estimated Utilization Date";
            $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $thisDate, $tradeData);
          } else {
            array_push($deleteArray, $msAction);
          }
        } else {
          array_push($deleteArray, $msAction);
        }

      } else {

        //ACTUAL UTILIZTION DATE - timeStamp
        $thisDate = strtotime($tradeData["timeStamp"]);
        $msAction = "utilization_actual_update";
        if($thisDate > 0) {
          if(($alertDay = $this->calculate_alert_day($thisDate)) > -1) {
            $messageText = "Actual Utilization Date";
            $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $thisDate, $tradeData);
          } else {
            array_push($deleteArray, $msAction);
          }
        } else {
          array_push($deleteArray, $msAction);
        }

      }

      //Delete calendar alerts that are no longer within 5 days
      foreach($deleteArray as $da) {
        $this->Members_Model->search_and_delete_alert_messages($da, $tradeData["listingId"], $tradeId);
      }

    }

  }

  function check_compliance_items_for_reminder_dates($listingId) {

    $this->load->model('Workflow');

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > Cal Alert > Compliance Alert > check_compliance_items_for_reminder_dates > Listing ID: ' . $listingId);

    $data['compliance'] = $this->Workflow->get_workflow('', 'credit', $listingId, 'compliance');

    $creditData = $this->get_credit_private($listingId);

    $deleteArray = [];

    if(isset($data['compliance']['allwlItems'])) {

      foreach($data['compliance']['allwlItems'] as $wli) {

        //COMPLIANCE DUE DATE REMINDER
        $thisDate = $wli["wiCompletedDate1Expected"];
        $msAction = "compliance_reminder";
        if($thisDate > 0 && $wli["wiComplianceReminder"] != "") {
          //Date at which
          $wiComplianceReminder = explode(",", $wli["wiComplianceReminder"]);
          foreach($wiComplianceReminder as $cr) {
            $alertDay = $cr;
            $thisReminderDateUnix = $thisDate - ($cr * 86400);
            $thisReminderDate = date('m/d/Y', $thisReminderDateUnix);
            $todayDate = date('m/d/Y', time());
            if($thisReminderDate == $todayDate) {
              $messageText = "Compliance Reminder - " . $wli["wiTempName"];
              $creditData['wiId'] = $wli["wiId"];
              $this->insert_credit_calender_alert($msAction, $messageText, $alertDay, $thisDate, $creditData);
            } else {
              $thisDeleteArray = ['msAction' => $msAction, 'wiId' => $wli['wiId']];
              array_push($deleteArray, $thisDeleteArray);
            }
          }
        } else {
          $thisDeleteArray = ['msAction' => $msAction, 'wiId' => $wli['wiId']];
          array_push($deleteArray, $thisDeleteArray);
        }

      }

      //Delete calendar alerts that are no longer within 5 days
      foreach($deleteArray as $da) {
        $this->Members_Model->search_and_delete_alert_messages($da['msAction'], $creditData["listingId"], '', $da['wiId']);
      }

    }

  }

  function check_audit_trail($objectType, $accessData, $dataBefore, $dataAfter) {

    if($objectType == 'credit' || $objectType == 'trade') {
      $audItemId = $dataAfter['listingId'];
    }

    $isNewInsert = false;
    if(count($dataBefore) == 0) {
      $isNewInsert = true;
    }

    $dpRequest['dpDmaId'] = $accessData['accountId'];
    $dpRequest['dpObjectType'] = $objectType;
    $dataPointsRaw = $this->get_data_points($dpRequest);
    $dataPoints = $dataPointsRaw['dataPoints'];

    //Prep - prior credit
    $priorCdpKeyValue = [];
    //Prep - new credit
    $updatedCdpKeyValue = [];
    $updatedCdpName = [];
    $updatedCdpType = [];
    $updatedAuditTrailTypeId = [];

    //Process - DEFAULT data points - for prior and new credit
    foreach($dataPoints as $dp) {
      //First, clean up the prior value, if it exists
      if(!$isNewInsert) {
        if(isset($dataBefore[$dp['dpValue']])) {
          $priorCdpKeyValue[$dp['dpKey']] = $dataBefore[$dp['dpValue']];
        }
      }
      //Second, clean up new value
      if(isset($dataAfter[$dp['dpValue']])) {
        $updatedCdpKeyValue[$dp['dpKey']] = $dataAfter[$dp['dpValue']];
        $updatedCdpName[$dp['dpKey']] = $dp['dpNameFull'];
        $updatedCdpType[$dp['dpKey']] = $dp['dpType'];
        $updatedAuditTrailTypeId[$dp['dpKey']] = $dp['dpAuditTrailId'];
      }
    }

    //Process - CUSTOM data points - for prior and new credit
    //First, clean up the prior value, if it exists
    if(isset($dataBefore['customDataPoints']) && count($dataBefore['customDataPoints']) > 0) {
      foreach($dataBefore['customDataPoints'] as $priorCdp) {
        $priorCdpKeyValue[$priorCdp['dpKey']] = $priorCdp['cvValue'];
      }
    }
    //Second, clean up new value
    if(isset($dataAfter['customDataPoints']) && count($dataAfter['customDataPoints']) > 0) {
      foreach($dataAfter['customDataPoints'] as $newCdp) {
        $updatedCdpKeyValue[$newCdp['dpKey']] = $newCdp['cvValue'];
        $updatedCdpName[$newCdp['dpKey']] = $newCdp['dpNameFull'];
        $updatedCdpType[$newCdp['dpKey']] = $newCdp['dpType'];
        $updatedAuditTrailTypeId[$newCdp['dpKey']] = 0;
      }
    }

    //NOW PROCESS THE AUDIT TRAIL
    //Cycle through new data points and compare to prior to see if Audit needs to occur
    foreach($updatedCdpKeyValue as $k => $v) {
      $thisOldValue = (isset($priorCdpKeyValue[$k])) ? $priorCdpKeyValue[$k] : null;
      $thisNewValue = $v;
      $thisCdpName = $updatedCdpName[$k];
      $thisCdpType = $updatedCdpType[$k];
      $thisAuditTrailTypeId = $updatedAuditTrailTypeId[$k];
      if(($thisNewValue != $thisOldValue || ($isNewInsert && $thisNewValue != "")) && ($thisAuditTrailTypeId === 0 || $thisAuditTrailTypeId > 0)) {
        if($thisCdpType == "currencyNoDecimal") {
          $value1 = ($thisOldValue != "") ? number_format($thisOldValue) : "";
          $value2 = ($thisNewValue != "") ? number_format($thisNewValue) : "";
        } else if($thisCdpType == "currencyTwoDecimal") {
          $value1 = ($thisOldValue != "") ? number_format($thisOldValue, 2) : "";
          $value2 = ($thisNewValue != "") ? number_format($thisNewValue, 2) : "";
        } else if($thisCdpType == "currencyFourDecimal") {
          $value1 = ($thisOldValue != "") ? number_format($thisOldValue, 4) : "";
          $value2 = ($thisNewValue != "") ? number_format($thisNewValue, 4) : "";
        } else if($thisCdpType == "date") {
          $value1 = ($thisOldValue != "") ? date('m/d/Y', $thisOldValue) : "";
          $value2 = ($thisNewValue != "") ? date('m/d/Y', $thisNewValue) : "";
        } else {
          $value1 = ($thisOldValue != "") ? $thisOldValue : "";
          $value2 = ($thisNewValue != "") ? $thisNewValue : "";
        }
        $auditData = [];
        //Consistent
        $auditData['audUserId'] = $accessData['userId'];
        $auditData['audDmaId'] = $accessData['accountId'];
        $auditData['audItemId'] = $audItemId;
        //Unique
        $auditData['audTypeId'] = $thisAuditTrailTypeId;
        $auditData['audValueBefore'] = $value1;
        $auditData['audValueAfter'] = $value2;
        $auditData['audCustomName'] = ($thisAuditTrailTypeId > 0) ? "" : $thisCdpName; //If a audit trail ID exists, it's default, otherwise use custom name
        $this->AuditTrail->insert_audit_item_api($auditData);

      }
    }

  }

  function get_all_credit_ids() {

    $this->db->select('PendingListings.listingId');
    $this->db->from('PendingListings');
    //$this->db->where('ActiveListings.listingId', 1293);
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $return[] = $data['listingId'];
    }

    return $return;

  }

  function get_all_credits_minimum() {

    $this->db->select('PendingListings.listingId, PendingListings.cWorkflowId, PendingListings.cComplianceId, COUNT(Trades.tradeId) as num_trades');
    $this->db->from('PendingListings');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->where('dmaAccounts.planId >', 0);
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Trades", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->group_by('PendingListings.listingId');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_trade_ids_on_credit($listingId) {

    $this->db->select('Trades.tradeId');
    $this->db->from('Trades');
    $this->db->where('Trades.listingId', $listingId);
    $this->db->where('Trades.deleteMarker', null);
    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->join("ActiveListings", "Trades.listingId = ActiveListings.listingId", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $return[] = $data['tradeId'];
    }

    return $return;

  }

  function get_loans($loanAccountId, $loanType = "", $returnAnalytics = "") {

    $this->db->select('Loans.*, PendingListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.estCreditPrice, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, PendingListings.offerPrice as price, (Loans.loanAmount/PendingListings.creditAmount) as loanToFaceValue, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.listingId, States.state, States.name, dmaAccounts.title, States.sLatitude, States.sLongitude');
    $this->db->from('Loans');
    $this->db->where('Loans.loanAccountId', $loanAccountId);
    if($loanType != "") {
      $this->db->where('Loans.loanType', $loanType);
    }
    $this->db->join("PendingListings", "PendingListings.listingId = Loans.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Accounts", "PendingListings.listedBy = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "Accounts.userId = dmaAccounts.mainAdmin", 'left');
    $query = $this->db->get();

    $return = [];
    $return["loans"] = [];
    $return["analytics"] = [];
    $return['calendarEvents']['allEvents'] = [];
    $return['calendarEvents']['futureEvents'] = [];

    if($returnAnalytics == 1 && $loanType == 2) {
      $return["analytics"]["advisorFeeTotal"] = 0;
      $return["analytics"]["advisorFeeReceived"] = 0;
      $return["analytics"]["advisorFeeDue"] = 0;
      $return["analytics"]["advisorFeeProjectCount"] = 0;
      $return["analytics"]["advisorFeePerProject"] = 0;
      $return["analytics"]["avgProjectDuration"] = 0;
      $allLoanEstMonthsDiff = 0;
      $allLoanEstMonthsDiffCount = 0;
    }

    foreach($query->result_array() as $data) {

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      if($returnAnalytics == 1 && $loanType == 2) {

        if($data["payment1Amount"] > 0) {
          $return["analytics"]["advisorFeeTotal"] += $data["payment1Amount"] + $data["payment2Amount"] + $data["payment3Amount"];
          $return["analytics"]["advisorFeeProjectCount"]++;
        }
        if($data["payment1Received"] == 1) {
          $return["analytics"]["advisorFeeReceived"] += $data["payment1Amount"];
        } else {
          if($data["payment1Amount"] > 0) {
            $return["analytics"]["advisorFeeDue"] += $data["payment1Amount"];
          }
        }
        if($data["payment2Received"] == 1) {
          $return["analytics"]["advisorFeeReceived"] += $data["payment2Amount"];
        } else {
          if($data["payment2Amount"] > 0) {
            $return["analytics"]["advisorFeeDue"] += $data["payment2Amount"];
          }
        }

        if($data["payment3Received"] == 1) {
          $return["analytics"]["advisorFeeReceived"] += $data["payment3Amount"];
        } else {
          if($data["payment3Amount"] > 0) {
            $return["analytics"]["advisorFeeDue"] += $data["payment3Amount"];
          }
        }

        //Calculate months between closed and maturity dates
        if($data['closingDate'] != "" && $data['maturityDate'] != "") {
          $allLoanEstMonthsDiffCount++;
          $year1 = date('Y', $data['closingDate']);
          $year2 = date('Y', $data['maturityDate']);
          $month1 = date('m', $data['closingDate']);
          $month2 = date('m', $data['maturityDate']);
          $monthDiff = ((abs($year2 - $year1)) * 12) + (abs($month2 - $month1));
          $allLoanEstMonthsDiff += $monthDiff;
        }

      }

      array_push($return["loans"], $data);

      // loans - closing date
      if($data['closingDate'] > 0) {
        $thisClosingDate = $data['closingDate'];
        $thisArray = [];
        $thisArray['type'] = 'loan_closingDate';
        $thisArray['dateUnix'] = $thisClosingDate;
        $thisArray['dateYear'] = date('Y', $thisClosingDate);
        $thisArray['dateMonth'] = date('m', $thisClosingDate);
        $thisArray['dateDay'] = date('d', $thisClosingDate);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisClosingDate));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisClosingDate);
        $thisArray['mdy'] = date('m-d-Y', $thisClosingDate);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['loanAmount'] = $data['loanAmount'];
        $thisArray['interestRate'] = $data['interestRate'];
        $thisArray['projectName'] = $data['stateCertNum'];
        $thisArray['projectNameExt'] = $data['projectNameExt'];
        $thisArray['sharedCredit'] = true;
        $thisArray['dmaTitle'] = $data['title'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        $thisArray['eventTitle'] = "Loan Closing Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'];
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        array_push($return['calendarEvents']['allEvents'], $thisArray);
        if($thisClosingDate > time()) {
          array_push($return['calendarEvents']['futureEvents'], $thisArray);
        }
      }

      // loans - maturity date
      if($data['maturityDate'] > 0) {
        $thisMaturityDate = $data['maturityDate'];
        $thisArray = [];
        $thisArray['type'] = 'loan_maturityDate';
        $thisArray['dateUnix'] = $thisMaturityDate;
        $thisArray['dateYear'] = date('Y', $thisMaturityDate);
        $thisArray['dateMonth'] = date('m', $thisMaturityDate);
        $thisArray['dateDay'] = date('d', $thisMaturityDate);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisMaturityDate));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisMaturityDate);
        $thisArray['mdy'] = date('m-d-Y', $thisMaturityDate);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['loanAmount'] = $data['loanAmount'];
        $thisArray['interestRate'] = $data['interestRate'];
        $thisArray['projectName'] = $data['stateCertNum'];
        $thisArray['projectNameExt'] = $data['projectNameExt'];
        $thisArray['sharedCredit'] = true;
        $thisArray['dmaTitle'] = $data['title'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        $thisArray['eventTitle'] = "Loan Maturity Date";
        $thisArray['connectedTo'] = $thisArray['listingFull'];
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        array_push($return['calendarEvents']['allEvents'], $thisArray);
        if($thisMaturityDate > time()) {
          array_push($return['calendarEvents']['futureEvents'], $thisArray);
        }
      }

      // loans - loan repay date
      if($data['loanRepayDate'] > 0) {
        $thisLoanRepayDate = $data['loanRepayDate'];
        $thisArray = [];
        $thisArray['type'] = 'loan_loanRepayDate';
        $thisArray['dateUnix'] = $thisLoanRepayDate;
        $thisArray['dateYear'] = date('Y', $thisLoanRepayDate);
        $thisArray['dateMonth'] = date('m', $thisLoanRepayDate);
        $thisArray['dateDay'] = date('d', $thisLoanRepayDate);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisLoanRepayDate));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisLoanRepayDate);
        $thisArray['mdy'] = date('m-d-Y', $thisLoanRepayDate);
        $thisArray['listingId'] = $data['listingId'];
        $thisArray['listingFull'] = $data['state'] . $data['listingId'];
        $thisArray['loanAmount'] = $data['loanAmount'];
        $thisArray['interestRate'] = $data['interestRate'];
        $thisArray['projectName'] = $data['stateCertNum'];
        $thisArray['projectNameExt'] = $data['projectNameExt'];
        $thisArray['loanAmountRepay'] = $data['loanAmountRepay'];
        $thisArray['sharedCredit'] = true;
        $thisArray['dmaTitle'] = $data['title'];
        $thisArray['sLatitude'] = $data['sLatitude'];
        $thisArray['sLongitude'] = $data['sLongitude'];
        $thisArray['jurisdictionCode'] = $data['state'];
        $thisArray['jurisdictionName'] = $data['name'];
        $thisArray['eventTitle'] = "Loan Repay Date ($" . number_format($thisArray['loanAmountRepay']) . ")";
        $thisArray['connectedTo'] = $thisArray['listingFull'];
        $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'];
        array_push($return['calendarEvents']['allEvents'], $thisArray);
        if($thisLoanRepayDate > time()) {
          array_push($return['calendarEvents']['futureEvents'], $thisArray);
        }
      }

    }

    if($returnAnalytics == 1 && $loanType == 2) {

      if($allLoanEstMonthsDiffCount > 0) {
        $return["analytics"]["avgProjectDuration"] = $allLoanEstMonthsDiff / $allLoanEstMonthsDiffCount;
      } else {
        $return["analytics"]["avgProjectDuration"] = 0;
      }

      if($return["analytics"]["advisorFeeProjectCount"] > 0) {
        $return["analytics"]["advisorFeePerProject"] = $return["analytics"]["advisorFeeTotal"] / $return["analytics"]["advisorFeeProjectCount"];
      } else {
        $return["analytics"]["advisorFeePerProject"] = 0;
      }
    }

    return $return;

  }

  function get_loans_on_listing($id, $account = "", $loanType = "") {

    $this->db->select('Loans.*, PendingListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.estCreditPrice, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, PendingListings.offerPrice as price, (Loans.loanAmount/PendingListings.creditAmount) as loanToFaceValue');
    $this->db->from('Loans');
    $this->db->where('Loans.listingId', $id);
    if($account != "") {
      $this->db->where('Loans.loanAccountId', $account);
    }
    if($loanType != "") {
      $this->db->where('Loans.loanType', $loanType);
    }
    $this->db->join("PendingListings", "PendingListings.listingId = Loans.listingId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      array_push($return, $data);
    }

    return $return;

  }

  function get_loans_on_listing_by_account($id, $accountId = "", $loanType = "") {

    $this->db->select('Loans.*, assignedTo.firstName as assignedToFirstName, assignedTo.lastName as assignedToLastName, advisorRep.firstName as advisorRepFirstName, advisorRep.lastName as advisorRepLastName, PendingListings.creditAmount, PendingListings.estCreditPrice, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, PendingListings.offerPrice as price, (Loans.loanAmount/PendingListings.creditAmount) as loanToFaceValue');
    $this->db->from('Loans');
    $this->db->where('Loans.listingId', $id);
    if($accountId > 0) {
      $this->db->where('Loans.loanAccountId', $accountId);
    }
    $this->db->join("PendingListings", "PendingListings.listingId = Loans.listingId", 'left');
    $this->db->join("Accounts as assignedTo", "assignedTo.userId = Loans.assignedTo", 'left');
    $this->db->join("Accounts as advisorRep", "advisorRep.userId = Loans.estProvider", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['assignedToName'] = ($data['assignedTo'] > 0) ? $data['assignedToFirstName'] . " " . $data['assignedToLastName'] : null;
      $data['advisorRepName'] = ($data['estProvider'] > 0) ? $data['advisorRepFirstName'] . " " . $data['advisorRepLastName'] : null;

      array_push($return, $data);
    }

    return $return;

  }

  function get_liabilities($request) {

    $tlDmaId = $request['tlDmaId'];

    $this->db->select('buyer_tax_liability_profile.*, Offsets.offsetName, TaxYear.taxYear, States.name, Taxpayers.taxpayerId, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpCompanyName, Taxpayers.tpAccountType, dmaAccounts.title');
    $this->db->from('buyer_tax_liability_profile');
    $array['buyer_tax_liability_profile.tlDmaId'] = $tlDmaId;
    $array['buyer_tax_liability_profile.tlDeleteMarker'] = null;
    $this->db->where($array);

    $this->db->join("TaxYear", "buyer_tax_liability_profile.tax_year = TaxYear.id", 'left');
    $this->db->join("States", "buyer_tax_liability_profile.jurisdiction = States.state", 'left');
    $this->db->join("Offsets", "buyer_tax_liability_profile.tax_offset = Offsets.id", 'left');
    $this->db->join("Taxpayers", "buyer_tax_liability_profile.tlTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("dmaAccounts", "buyer_tax_liability_profile.tlDmaId = dmaAccounts.dmaId", 'left');

    $this->db->order_by("TaxYear.taxYear ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $data['taxpayerName'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpLastName'] . ", " . $data['tpFirstName'];
      array_push($return, $data);
    }

    return $return;

  }

  function get_tax_liability($request) {

    $tlId = $request['tlId'];

    $this->db->select('buyer_tax_liability_profile.*, Offsets.offsetName, TaxYear.taxYear, States.name, Taxpayers.taxpayerId, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpCompanyName, Taxpayers.tpAccountType');
    $this->db->from('buyer_tax_liability_profile');
    $array['buyer_tax_liability_profile.id'] = $tlId;
    $array['buyer_tax_liability_profile.tlDeleteMarker'] = null;
    $this->db->where($array);

    $this->db->join("TaxYear", "('buyer_tax_liability_profile.tax_year = TaxYear.id", 'left');
    $this->db->join("States", "('buyer_tax_liability_profile.jurisdiction = States.state", 'left');
    $this->db->join("Offsets", "('buyer_tax_liability_profile.tax_offset = Offsets.id", 'left');
    $this->db->join("Taxpayers", "('buyer_tax_liability_profile.tlTaxpayerId = Taxpayers.taxpayerId", 'left');

    $this->db->order_by("TaxYear.taxYear ASC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $data['taxpayerName'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpLastName'] . ", " . $data['tpFirstName'];
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function insert_tax_liability() {

    //Sanitize data
    $errors = 0;
    if($this->input->post('tlTaxpayerId') > 0) {
      $tlTaxpayerId = $this->input->post('tlTaxpayerId');
    } else {
      $errors++;
    }
    $amount = floatval(preg_replace('/[^\d\.]+/', '', $this->input->post('amount')));
    if($amount > 0) {
      $amount = $amount;
    } else {
      $errors++;
    }
    if($this->input->post('taxOffset') > 0) {
      $taxOffset = $this->input->post('taxOffset');
    } else {
      $errors++;
    }
    if($this->input->post('jurisdiction') != "") {
      $jurisdiction = $this->input->post('jurisdiction');
    } else {
      $errors++;
    }
    if($this->input->post('tax_year') > 0) {
      $tax_year = $this->input->post('tax_year');
    } else {
      $errors++;
    }

    if($errors == 0) {

      $data = [
          'tlDmaId'      => $this->cisession->userdata('dmaId'),
          'account_id'   => null,
          'tlCreatedBy'  => $this->cisession->userdata('userId'),
          'tlTaxpayerId' => $tlTaxpayerId,
          'amount'       => $amount,
          'tax_offset'   => $taxOffset,
          'country'      => 1,
          'jurisdiction' => $jurisdiction,
          'tax_year'     => $tax_year,
      ];

      $this->db->insert('buyer_tax_liability_profile', $data);

      return $this->db->insert_id();

    }

  }

  function save_tax_liability($tlId) {

    //Sanitize data
    $errors = 0;
    if($tlId > 0) {
      $tlId = $tlId;
    } else {
      $errors++;
    }
    if($this->input->post('tlTaxpayerId') > 0) {
      $tlTaxpayerId = $this->input->post('tlTaxpayerId');
    } else {
      $errors++;
    }
    $amount = floatval(preg_replace('/[^\d\.]+/', '', $this->input->post('amount')));
    if($amount > 0) {
      $amount = $amount;
    } else {
      $errors++;
    }
    if($this->input->post('taxOffset') > 0) {
      $taxOffset = $this->input->post('taxOffset');
    } else {
      $errors++;
    }
    if($this->input->post('jurisdiction') != "") {
      $jurisdiction = $this->input->post('jurisdiction');
    } else {
      $errors++;
    }
    if($this->input->post('tax_year') > 0) {
      $tax_year = $this->input->post('tax_year');
    } else {
      $errors++;
    }

    if($errors == 0) {

      $data = [
          'account_id'   => null,
          'tlTaxpayerId' => $tlTaxpayerId,
          'amount'       => $amount,
          'tax_offset'   => $taxOffset,
          'country'      => 1,
          'jurisdiction' => $jurisdiction,
          'tax_year'     => $tax_year,
      ];

      $this->db->where('buyer_tax_liability_profile.id', $tlId);
      $this->db->update('buyer_tax_liability_profile', $data);

      return true;

    }

  }

  function update_pending_listing_field($listingId, $fieldName, $fieldValue) {

    $data = [
        $fieldName => $fieldValue,
    ];

    $this->db->where('PendingListings.listingId', $listingId);
    $this->db->update('PendingListings', $data);

    return true;

  }

  function update_active_listing_field($listingId, $fieldName, $fieldValue) {

    $data = [
        $fieldName => $fieldValue,
    ];

    $this->db->where('ActiveListings.listingId', $listingId);
    $this->db->update('ActiveListings', $data);

    return true;

  }

  function get_credit_permissions($listingId, $caDmaid = "", $caDmaMemberId = "", $caAction = "") {

    $this->db->select('creditAccess.*, dmaMembers.*, Accounts.userId, Accounts.firstName, Accounts.lastName');
    $this->db->from('creditAccess');
    $this->db->where('creditAccess.caListingId', $listingId);
    if($caDmaid != "") {
      $this->db->where('creditAccess.caDmaid', $caDmaid);
    }
    if($caDmaMemberId != "") {
      $this->db->where('creditAccess.caDmaMemberId', $caDmaMemberId);
    }
    if($caAction != "") {
      $this->db->where('creditAccess.caAction', $caAction);
    }
    $this->db->join("dmaMembers", "creditAccess.caDmaMemberId = dmaMembers.dmaMemberId", 'left');
    $this->db->join("Accounts", "dmaMembers.dmaMUserId = Accounts.userId", 'left');
    $query = $this->db->get();

    $return = [];
    $return['creditAccess'] = [];

    foreach($query->result_array() as $data) {
      array_push($return['creditAccess'], $data);
    }

    return $return;

  }

  function insert_credit_permission($caListingId, $caAction, $caDmaid, $caDmaMemberId = "") {

    $data = [
        'caListingId'   => $caListingId,
        'caAction'      => $caAction,
        'caDmaid'       => $caDmaid,
        'caDmaMemberId' => ($caDmaMemberId > 0) ? $caDmaMemberId : null,
        'caDateCreated' => time(),
    ];

    $this->db->insert('creditAccess', $data);

    return $this->db->insert_id();

  }

  function update_credit_permission($caId, $caAction) {

    $data = [
        'caAction' => $caAction,
    ];

    $this->db->where('creditAccess.caId', $caId);
    $this->db->update('creditAccess', $data);

    return true;

  }

  function delete_all_credit_permissions_for_dma_account($caListingId, $caDmaId) {

    $this->db->where('creditAccess.caListingId', $caListingId);
    $this->db->where('creditAccess.caDmaId', $caDmaId);
    $this->db->delete('creditAccess');

    return true;

  }

  function delete_credit_permission($caId, $caListingId) {

    $this->db->where('creditAccess.caId', $caId);
    $this->db->where('creditAccess.caListingId', $caListingId);
    $this->db->delete('creditAccess');

    return true;

  }

  function unlist_credit($listingId) {

    //Update Active Listing
    $data = [
        'listed' => null,
    ];
    $this->db->where('ActiveListings.listingId', $listingId);
    $this->db->update('ActiveListings', $data);

    //Update Pending Listing
    $data = [
        'status' => 5,
    ];
    $this->db->where('PendingListings.listingId', $listingId);
    $this->db->update('PendingListings', $data);

    return true;

  }

  function insert_share($sType, $sItemId, $sharedPrimId, $sharedSecId, $sharerPrimId = "", $sharerSecId = "", $sharedPermEdit = "", $sRemoveLock = "", $advisorFlag = "", $sAdditionalInfo = "") {

    $data = [
        'sType'          => $sType,
        'sItemId'        => $sItemId,
        'sStatus'        => 0,
        'sharerPrimId'   => ($sharerPrimId > 0) ? $sharerPrimId : $this->cisession->userdata('dmaId'),
        'sharerSecId'    => ($sharerSecId > 0) ? $sharerPrimId : $this->cisession->userdata('userId'),
        'sharedPrimId'   => $sharedPrimId,
        'sharedSecId'    => $sharedSecId,
        'sharedPermEdit' => ($sharedPermEdit != "") ? $sharedPermEdit : 1,
        'sRemoveLock'    => $sRemoveLock,
        'advisorFlag'    => $advisorFlag,
        'sTimeStamp'     => time(),
    ];

    if($sAdditionalInfo != "") {
      $data['sharedInviteFirstName'] = $sAdditionalInfo['sharedInviteFirstName'];
      $data['sharedInviteLastName'] = $sAdditionalInfo['sharedInviteLastName'];
      $data['sharedInviteCompanyName'] = $sAdditionalInfo['sharedInviteCompanyName'];
      $data['sharedInviteEmail'] = $sAdditionalInfo['sharedInviteEmail'];
    }

    $this->db->insert('Shares', $data);

    return $this->db->insert_id();

  }

  function update_share($sId, $updates) {

    if($sId > 0) {

      $data = [];

      foreach($updates as $k => $v) {
        $data[$k] = $v;
      }

      $this->db->where('Shares.sId', $sId);
      $this->db->update('Shares', $data);

      return true;

    }

  }

  function update_share_permission($sId, $sharedPermEdit) {

    $data = [
        'sharedPermEdit' => $sharedPermEdit,
    ];

    $this->db->where('Shares.sId', $sId);
    $this->db->update('Shares', $data);

    return true;

  }

  function revoke_share($sId) {

    $data = [
        'sDeleteMarker' => 1,
    ];

    $this->db->where('Shares.sId', $sId);
    $this->db->update('Shares', $data);

    return true;

  }

  function update_share_advisor_status() {

    $data = [
        'advisorStatus' => $_POST['advisorStatus'],
    ];

    $this->db->where('Shares.sId', $_POST['sId']);
    $this->db->update('Shares', $data);

    return true;

  }

  function update_customer_access_to_credit($listingId, $cCustomerAccess, $cOwnerReadOnly) {

    $data = [];

    if($cCustomerAccess != "") {
      $data['cCustomerAccess'] = $cCustomerAccess;
    }

    $data['cOwnerReadOnly'] = $cOwnerReadOnly;

    $this->db->where('PendingListings.listingId', $listingId);
    $this->db->update('PendingListings', $data);

    return true;

  }

  function insert_loan() {

    $loanAmount = preg_replace('/[^0-9.]*/', '', $_POST['loanAmount']);
    $closingDate = strtotime($_POST['closingDate']);
    $maturityDate = strtotime($_POST['maturityDate']);
    $contractSentDate = strtotime($_POST['contractSentDate']);
    $contractSignedDate = strtotime($_POST['contractSignedDate']);
    if($contractSignedDate > 0) {
      $contractSigned = 1;
    }
    $payment1Amount = preg_replace("/[^0-9.]*/", "", $_POST['payment1Amount']);
    $payment1Date = strtotime($_POST['payment1Date']);
    $payment1DateEst = strtotime($_POST['payment1DateEst']);
    if($_POST['payment1Received'] == 'yes') {
      $payment1Received = 1;
    } else {
      $payment1Received = "";
    }
    $payment2Amount = preg_replace("/[^0-9.]*/", "", $_POST['payment2Amount']);
    $payment2Date = strtotime($_POST['payment2Date']);
    $payment2DateEst = strtotime($_POST['payment2DateEst']);
    if($_POST['payment2Received'] == 'yes') {
      $payment2Received = 1;
    } else {
      $payment2Received = "";
    }
    $payment3Amount = preg_replace("/[^0-9.]*/", "", $_POST['payment3Amount']);
    $payment3Date = strtotime($_POST['payment3Date']);
    $payment3DateEst = strtotime($_POST['payment3DateEst']);
    if($_POST['payment3Received'] == 'yes') {
      $payment3Received = 1;
    } else {
      $payment3Received = "";
    }
    //Payment received decision
    $paymentReceived = 0;
    $enitityCut1Amount = preg_replace("/[^0-9.]*/", "", $_POST['enitityCut1Amount']);

    $data = [
        'listingId'          => $_POST['listingId'],
        'loanType'           => $_POST['loanType'],
        'loanAccountId'      => $_POST['loanAccountId'],
        'closingDate'        => $closingDate,
        'maturityDate'       => $maturityDate,
        'loanAmount'         => $loanAmount,
        'interestRate'       => $_POST['interestRate'],
        'estProvider'        => $_POST['estProvider'],
        'loanStatus'         => 1,
        'clientType'         => $_POST['clientType'],
        'assignedTo'         => $_POST['assignedTo'],
        'contractSentDate'   => $contractSentDate,
        'payment1Amount'     => $payment1Amount,
        'payment1Date'       => $payment1Date,
        'payment1DateEst'    => $payment1DateEst,
        'payment1Received'   => $payment1Received,
        'payment2Amount'     => $payment2Amount,
        'payment2Date'       => $payment2Date,
        'payment2DateEst'    => $payment2DateEst,
        'payment2Received'   => $payment2Received,
        'payment3Amount'     => $payment3Amount,
        'payment3Date'       => $payment3Date,
        'payment3DateEst'    => $payment3DateEst,
        'payment3Received'   => $payment3Received,
        'contractSigned'     => $contractSigned,
        'contractSignedDate' => $contractSignedDate,
        'enitityCut1Name'    => $_POST['enitityCut1Name'],
        'enitityCut1Amount'  => $enitityCut1Amount,
        'timestamp'          => time(),
    ];

    $this->db->insert('Loans', $data);

    return $this->db->insert_id();

  }

  function update_loan() {

    $loanAmount = preg_replace('/[^0-9.]*/', '', $_POST['loanAmount']);
    $closingDate = strtotime($_POST['closingDate']);
    $maturityDate = strtotime($_POST['maturityDate']);
    $contractSentDate = strtotime($_POST['contractSentDate']);
    $contractSignedDate = strtotime($_POST['contractSignedDate']);
    if($contractSignedDate > 0) {
      $contractSigned = 1;
    }
    $payment1Amount = preg_replace("/[^0-9.]*/", "", $_POST['payment1Amount']);
    $payment1Date = strtotime($_POST['payment1Date']);
    $payment1DateEst = strtotime($_POST['payment1DateEst']);
    if($_POST['payment1Received'] == 'yes') {
      $payment1Received = 1;
    } else {
      $payment1Received = "";
    }
    $payment2Amount = preg_replace("/[^0-9.]*/", "", $_POST['payment2Amount']);
    $payment2Date = strtotime($_POST['payment2Date']);
    $payment2DateEst = strtotime($_POST['payment2DateEst']);
    if($_POST['payment2Received'] == 'yes') {
      $payment2Received = 1;
    } else {
      $payment2Received = "";
    }
    $payment3Amount = preg_replace("/[^0-9.]*/", "", $_POST['payment3Amount']);
    $payment3Date = strtotime($_POST['payment3Date']);
    $payment3DateEst = strtotime($_POST['payment3DateEst']);
    if($_POST['payment3Received'] == 'yes') {
      $payment3Received = 1;
    } else {
      $payment3Received = "";
    }
    $enitityCut1Amount = preg_replace("/[^0-9.]*/", "", $_POST['enitityCut1Amount']);

    $data = [
        'closingDate'        => $closingDate,
        'maturityDate'       => $maturityDate,
        'loanAmount'         => $loanAmount,
        'interestRate'       => $_POST['interestRate'],
        'estProvider'        => $_POST['estProvider'],
        'clientType'         => $_POST['clientType'],
        'assignedTo'         => $_POST['assignedTo'],
        'contractSentDate'   => $contractSentDate,
        'payment1Amount'     => $payment1Amount,
        'payment1Date'       => $payment1Date,
        'payment1DateEst'    => $payment1DateEst,
        'payment1Received'   => $payment1Received,
        'payment2Amount'     => $payment2Amount,
        'payment2Date'       => $payment2Date,
        'payment2DateEst'    => $payment2DateEst,
        'payment2Received'   => $payment2Received,
        'payment3Amount'     => $payment3Amount,
        'payment3Date'       => $payment3Date,
        'payment3DateEst'    => $payment3DateEst,
        'payment3Received'   => $payment3Received,
        'contractSigned'     => $contractSigned,
        'contractSignedDate' => $contractSignedDate,
        'enitityCut1Name'    => $_POST['enitityCut1Name'],
        'enitityCut1Amount'  => $enitityCut1Amount,
    ];

    $this->db->where('Loans.id', $_POST['id']);
    $this->db->update('Loans', $data);

    return true;

  }

  function repay_loan() {

    $loanAmountRepay = preg_replace("/[^0-9.]*/", "", $_POST['loanAmountRepay']);
    $loanRepayDate = strtotime($_POST['loanRepayDate']);

    $data = [
        'loanAmountRepay' => $loanAmountRepay,
        'loanRepayDate'   => $loanRepayDate,
        'loanStatus'      => 2,
    ];

    $this->db->where('Loans.id', $_POST['id']);
    $this->db->update('Loans', $data);

    return true;

  }

  function unpay_loan() {

    $data = [
        'loanAmountRepay' => 0,
        'loanRepayDate'   => 0,
        'loanStatus'      => 1,
    ];

    $this->db->where('Loans.id', $_POST['id']);
    $this->db->update('Loans', $data);

    return true;

  }

  function delete_loan() {

    $data = [
        'loanDeleted' => 1,
    ];

    $this->db->where('Loans.id', $_POST['id']);
    $this->db->update('Loans', $data);

    return true;

  }

  function get_my_bids($id) {
    $this->db->select('Bids.bidId, Bids.timeStamp, Bids.accountId, Bids.listingId, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName, Bids.eoAccountId,TaxYear.taxYear');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.accountId', $id);
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      $data->action = "Active Buy Order";
    }

    return $query->result();

  }

  function get_my_bid_on_listing($id) {
    $this->db->select('Bids.bidId, Bids.timeStamp,ActiveListings.OIXIncentiveId,IncentivePrograms.State, Bids.accountId, Bids.listingId, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.bidId', $id);
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      $data->action = "Bid";
    }

    return $query->result();

  }

  function get_my_bid_on_active_listing($id) {
    $this->db->select('Bids.bidId, Bids.timeStamp, Bids.bDmaMemberId, ActiveListings.OIXIncentiveId,IncentivePrograms.State, Bids.accountId, Bids.listingId, Bids.bidExpirationDate, Bids.changedPS, bidSize as size, bidPrice as price, Bids.reducable, Bids.minimumCreditIncrement, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,
			Accounts.firstName, Accounts.lastName, Accounts.email, dmaAccounts.title, subBuyer.email as subBuyerEmail, subBuyer.firstName as subBuyerFirstName, subBuyer.lastName subBuyerLastName');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.bidId', $id);
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "Bids.accountId = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts as subBuyer", "Bids.bDmaMemberId = subBuyer.userId", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['OffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['action'] = "Bid";

      array_push($return, $data);
    }
    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_my_bid_on_listing_forNotifications($id) {
    $this->db->select('Bids.bidId, Bids.timeStamp,ActiveListings.OIXIncentiveId,IncentivePrograms.State, Bids.accountId, Bids.listingId, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,taxYear,listedBy,changedPS');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.bidId', $id);
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_deleted_bid_on_listing_forNotifications($id) {
    $this->db->select('Bids.bidId, Bids.timeStamp,ActiveListings.OIXIncentiveId,IncentivePrograms.State, Bids.accountId, Bids.listingId, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,taxYear,listedBy');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.bidId', $id);
    $this->db->where('Bids.deleteMarker is not null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_my_cancelled_bids($id) {
    $this->db->select('bidId, Bids.timeStamp, accountId, Bids.listingId, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear');
    $this->db->where('accountId', $id);
    $this->db->from('Bids');
    $this->db->where('Bids.deleteMarker is not null');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      $data->action = "Cancelled Bid";
    }

    return $query->result();

  }

  function get_my_new_trades($id) {
    $this->db->select('tradeId, Trades.timeStamp, Trades.accountId, ActiveListings.listingId, tradeSize as size, tradePrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName');
    $this->db->where('Trades.accountId', $id);
    $this->db->or_where('listedBy', $id);
    $this->db->or_where('Bids.accountId', $id);
    $this->db->from('ActiveListings');
    $this->db->join("Trades", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');

    $this->db->distinct();
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      $data->action = "Pending Trade";
    }

    return $query->result();

  }

  function get_my_trades($id) {
    $this->db->select('tradeId, Trades.timeStamp,Trades.status,listedBy as saccountId,Bids.accountId as baccountId, Trades.accountId, Trades.listingId, tradeSize as size, tradePrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName, closingDoc, TaxYear.taxYear');
    $this->db->where('Trades.accountId', $id);
    $this->db->or_where('listedBy', $id);
    $this->db->or_where('Bids.accountId', $id);
    $this->db->from('Trades');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->order_by("Trades.timestamp desc");
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      if($data->status != 2) {
        $data->tstatus = "Pending Trade";
      } else {
        $data->tstatus = "Completed Trade";
      }
      if($data->baccountId == $id) {
        $data->action = "Bought";
      } else {
        $data->action = "Sold";
      }
    }

    return $query->result();

  }

  function get_my_sales_purchased($id) {
    $this->db->select('tradeId, Trades.timeStamp, Trades.status,PendingListings.listedBy as saccountId,Bids.accountId as baccountId, Trades.accountId, Trades.tradeType, Trades.listingId, Trades.tradeIsEstimated, Trades.settlementDate, Trades.sellerSigned, Trades.sellerRecPayment, Trades.closingProcessStartDate, tradeSize as size, tradePrice as price, Trades.utilizationTypeId, utilizationTypes.name as utName, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName, SellerAccount.title as sellerAccountName, closingDoc, TaxYear.taxYear, Accounts.companyName, ActiveListings.brokerDmaId, States.state, States.name, States.sLatitude, States.sLongitude');
    $this->db->where('Bids.accountId', $id);
    $this->db->from('Trades');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    $this->db->join("Accounts as Seller", "PendingListings.listedBy = Seller.userId", 'left'); //TODO: how the fuck is this supposed to work?
    $this->db->join("dmaAccounts as SellerAccount", "SellerAccount.mainAdmin = Seller.userId", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->order_by("Trades.timestamp desc");
    $query = $this->db->get();

    $return['purchases'] = [];
    $return['calendarEvents']['allEvents'] = [];
    $return['calendarEvents']['futureEvents'] = [];

    foreach($query->result() as $data) {

      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      if($data->status != 2) {
        $data->tstatus = "Pending Trade";
      } else {
        $data->tstatus = "Completed Trade";
      }
      $data->transactions = $this->Trading->get_transactions_of_trade($data->tradeId);

      if($data->tradeType == "internal_transfer") {
        $data->tradeTypeName = "Internal Trans.";
      } else {
        if($data->tradeType == "external_transfer") {
          $data->tradeTypeName = "Transfer";
        } else {
          if($data->tradeType == "external_purchase") {
            $data->tradeTypeName = "Purchase";
          } else {
            $data->tradeTypeName = "Sale on OIX";
          }
        }
      }

      if($data->brokerDmaId > 0) {
        $data = $this->processBrokerActions($data);
      }

      array_push($return['purchases'], $data);

      // signatures - signatures due
      //If this is in the closing process
      if($data->status == 1 && $data->closingProcessStartDate != "") {
        //For each transaction
        foreach($data->transactions as $pt) {

          //If buyer has not payed
          if($pt['buyerSignedDate'] == "") {
            $thisDate = $data->closingProcessStartDate + 432000;
            $thisArray = [];
            $thisArray['type'] = 'credit_buyer_pay_date';
            $thisArray['dateUnix'] = $thisDate;
            $thisArray['dateYear'] = date('Y', $thisDate);
            $thisArray['dateMonth'] = date('m', $thisDate);
            $thisArray['dateDay'] = date('d', $thisDate);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDate));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisDate);
            $thisArray['mdy'] = date('m-d-Y', $thisDate);
            $thisArray['listingId'] = $data->listingId;
            $thisArray['listingFull'] = $data->State . $data->listingId;
            $thisArray['tradeId'] = $data->tradeId;
            $thisArray['transactionId'] = $pt['transactionId'];
            $thisArray['tCreditAmount'] = $pt['tCreditAmount'];
            $thisArray['tradePrice'] = $data->price;
            $thisArray['sLatitude'] = $data->sLatitude;
            $thisArray['sLongitude'] = $data->sLongitude;
            $thisArray['jurisdictionCode'] = $data->state;
            $thisArray['jurisdictionName'] = $data->name;
            $thisArray['eventTitle'] = "Signature Due";
            $thisArray['connectedTo'] = $thisArray['listingFull'];
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/purchase/" . $thisArray['tradeId'];
            if($pt['taxpayerId'] > 0) {
              if($pt['tpAccountType'] == 1) {
                $thisArray['payer'] = $pt['tpCompanyName'];
              } else {
                $thisArray['payer'] = $pt['tpFirstName'] . " " . $pt['tpLastName'];
              }
            } else {
              $thisArray['payer'] = $pt['buyerCompanyName'];
            }
            array_push($return['calendarEvents']['allEvents'], $thisArray);
            if($thisDate > time()) {
              array_push($return['calendarEvents']['futureEvents'], $thisArray);
            }
          }

          //If buyer has not signed
          if($pt['buyerSigned'] != 1) {
            $thisDate = $data->closingProcessStartDate + 432000;
            $thisArray = [];
            $thisArray['type'] = 'credit_buyer_sig_date';
            $thisArray['dateUnix'] = $thisDate;
            $thisArray['dateYear'] = date('Y', $thisDate);
            $thisArray['dateMonth'] = date('m', $thisDate);
            $thisArray['dateDay'] = date('d', $thisDate);
            $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisDate));
            $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
            $thisArray['ymd'] = date('Y-m-d', $thisDate);
            $thisArray['mdy'] = date('m-d-Y', $thisDate);
            $thisArray['listingId'] = $data->listingId;
            $thisArray['listingFull'] = $data->State . $data->listingId;
            $thisArray['tradeId'] = $data->tradeId;
            $thisArray['transactionId'] = $pt['transactionId'];
            $thisArray['tCreditAmount'] = $pt['tCreditAmount'];
            $thisArray['sLatitude'] = $data->sLatitude;
            $thisArray['sLongitude'] = $data->sLongitude;
            $thisArray['jurisdictionCode'] = $data->state;
            $thisArray['jurisdictionName'] = $data->name;
            $thisArray['eventTitle'] = "Payment Due";
            $thisArray['connectedTo'] = $thisArray['listingFull'];
            $thisArray['actionLink'] = base_url() . "dashboard/credit/" . $thisArray['listingId'] . "/purchase/" . $thisArray['tradeId'];
            if($pt['taxpayerId'] > 0) {
              if($pt['tpAccountType'] == 1) {
                $thisArray['signer'] = $pt['tpCompanyName'];
              } else {
                $thisArray['signer'] = $pt['tpFirstName'] . " " . $pt['tpLastName'];
              }
            } else {
              $thisArray['signer'] = $pt['buyerCompanyName'];
            }
            array_push($return['calendarEvents']['allEvents'], $thisArray);
            if($thisDate > time()) {
              array_push($return['calendarEvents']['futureEvents'], $thisArray);
            }
          }
        }

      }

    }

    return $return;

  }

  function get_my_sales_sold($input) {

    $input['listedBy'] = (isset($input['listedBy']) && $input['listedBy'] > 0) ? $input['listedBy'] : null;
    $input['dmaId'] = (isset($input['dmaId']) && $input['dmaId'] > 0) ? $input['dmaId'] : null;
    $input['dmaMemberId'] = (isset($input['dmaMemberId']) && $input['dmaMemberId'] > 0) ? $input['dmaMemberId'] : null;

    $this->db->select('tradeId, Trades.timeStamp, Trades.tradeIsEstimated, Trades.status, Trades.utilizingEntityType, Trades.tradePercentageEstimate, Trades.tradePercentageEstimateCompareTo, Trades.utilizationTypeId, utilizationTypes.name as utName, PendingListings.creditAmount as creditFaceValue, ActiveListings.brokerDmaId, ActiveListings.listedBy as saccountId,Bids.accountId as baccountId, Trades.tradeType, Trades.accountId, Trades.settlementDate, Trades.sellerSigned, Trades.listingId, tradeSize as size, tradePrice as price, ActiveListings.brokerDmaId, SellerAccount.title as sellerAccountName, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, Accounts.companyName as buyerAccountName, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName, closingDoc, TaxYear.taxYear, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.availableToList as amountLocalRemaining, Accounts.*');
    if($input['listedBy'] > 0) {
      $this->db->where('ActiveListings.listedBy', $input['listedBy']);
    }
    $this->db->where('Trades.deleteMarker', null);
    /*if($dmaId>0 && $dmaMemberId>0) {
        $whereAccess = "(creditAccessUserLevel.caAction = 'view' || creditAccessUserLevel.caAction = 'edit' || creditAccessDmaLevel.caAction = 'open' || creditAccessDmaLevel.caAction = 'watch')";
        $this->db->where($whereAccess);
      }*/
    $this->credit_where_access($input);

    $this->db->from('Trades');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    $this->db->join("Accounts as Seller", "PendingListings.listedBy = Seller.userId", 'left');
    $this->db->join("dmaAccounts as SellerAccount", "SellerAccount.mainAdmin = Seller.userId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    /*if($dmaId>0 && $dmaMemberId>0) {
        $this->db->join("creditAccess as creditAccessUserLevel","PendingListings.listingId = creditAccessUserLevel.caListingId AND ".$dmaMemberId." = creditAccessUserLevel.caDmaMemberId", 'left');
        $this->db->join("creditAccess as creditAccessDmaLevel","PendingListings.listingId = creditAccessDmaLevel.caListingId AND ".$dmaId." = creditAccessDmaLevel.caDmaId AND ('open' = creditAccessDmaLevel.caAction OR 'watch' = creditAccessDmaLevel.caAction)", 'left');
      }*/
    $this->db->order_by("Trades.timestamp DESC");
    $query = $this->db->get();

    foreach($query->result() as $data) {

      if($data->tradePercentageEstimate > 0) {
        $data->tradePercentageEstimateWhole = number_format($data->tradePercentageEstimate * 100, 1);
        if($data->tradePercentageEstimate == 0.33) {
          $data->tradePercentageEstimate = 0.333333333333333;
        } else {
          if($data->tradePercentageEstimate == 0.66) {
            $data->tradePercentageEstimate = 0.666666666666666;
          }
        }
        $thisCreditAmountCompareTo = ($data->tradePercentageEstimateCompareTo == '' || $data->tradePercentageEstimateCompareTo == 'facevalue') ? $data->creditFaceValue : $data->amountLocalRemaining;
        $data->tradeSize = $thisCreditAmountCompareTo * $data->tradePercentageEstimate;
        $data->size = $thisCreditAmountCompareTo * $data->tradePercentageEstimate;
        $data->tradeSizeEstimate = $thisCreditAmountCompareTo * $data->tradePercentageEstimate;
      }

      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      if($data->status != 2) {
        $data->tstatus = "Pending Trade";
      } else {
        $data->tstatus = "Completed Trade";
      }
      $data->transactions = $this->Trading->get_transactions_of_trade($data->tradeId);

      if($data->brokerDmaId > 0) {
        $data = $this->processBrokerActions($data);
      }

      if($data->tradeType == "internal_transfer") {
        $data->tradeTypeName = "Internal Trans.";
      } else {
        if($data->tradeType == "external_transfer") {
          $data->tradeTypeName = "Transfer";
        } else {
          $data->tradeTypeName = "Sale on OIX";
        }
      }

      $firstTrans = $data->transactions[0];
      if($data->utilizingEntityType == "myaccounts") {
        $data->utilizingEntityName = $data->buyerAccountName; //Buyer is the account
      } else {
        if($data->utilizingEntityType == "customname") {
          $data->utilizingEntityName = $firstTrans['utilizingEntityCustomName']; //Utilizer is the custom name
        } else {
          if($firstTrans['utilizingEntityCustomName'] != "") {
            $data->utilizingEntityName = $firstTrans['utilizingEntityCustomName']; //Utilizer is the custom name
          } else {
            if($firstTrans['taxpayerId'] > 0) {
              $extraText = ($data->tradeType == "oix_marketplace_trade") ? "(via " . $data->buyerAccountName . ")" : "";
              if(sizeof($data->transactions) > 1) {
                $data->utilizingEntityName = sizeof($data->transactions) . " Buyers " . $extraText;
              } else {
                $data->utilizingEntityName = ($firstTrans['tpAccountType'] == 1) ? $firstTrans['tpCompanyName'] . " " . $extraText : $firstTrans['tpFirstName'] . ' ' . $firstTrans['tpLastName'] . " " . $extraText;
              }
            } else {
              $data->utilizingEntityName = $data->buyerAccountName; //Buyer is this account
            }
          }
        }
      }

    }

    return $query->result();

  }

  function processBrokerActions($data) {

    $data->brokerData = new stdClass();
    $data->brokerData->signatureDocsNotUploaded = 0;
    $data->brokerData->signatureDocsNotSent = 0;
    $data->brokerData->signaturesOutstanding = 0;
    $data->brokerData->paymentsOutstanding = 0;
    $data->brokerData->actionsOutstandingMessage = "";

    foreach($data->transactions as $tr) {
      if($tr['buyerSigReady'] != 1) {
        $data->brokerData->signatureDocsNotUploaded++;
      }
      if($tr['buyerSigEmailSent'] != 1) {
        $data->brokerData->signatureDocsNotSent++;
      }
      if($tr['buyerSigned'] != 1) {
        $data->brokerData->signaturesOutstanding++;
      }
      if($tr['buyerPaid'] != 1) {
        $data->brokerData->paymentsOutstanding++;
      }
    }
    if($data->brokerData->signaturesOutstanding > 0 || $data->brokerData->paymentsOutstanding > 0) {
      $data->brokerData->actionsOutstandingMessage .= "Due: ";
    }
    if($data->brokerData->signaturesOutstanding > 0) {
      $data->brokerData->actionsOutstandingMessage .= $data->brokerData->signaturesOutstanding . " Sign";
    }
    if($data->brokerData->signaturesOutstanding > 0 && $data->brokerData->paymentsOutstanding > 0) {
      $data->brokerData->actionsOutstandingMessage .= " / ";
    }
    if($data->brokerData->paymentsOutstanding > 0) {
      $data->brokerData->actionsOutstandingMessage .= $data->brokerData->paymentsOutstanding . " Pay";
    }

    return $data;

  }

  function get_taxpayer_sales_sold($taxpayerId, $dmaId = "", $dmaMemberId = "") {
    $this->db->select('tradeId, Trades.timeStamp,Trades.status,ActiveListings.listedBy as saccountId,Bids.accountId as baccountId, mainBuyer.companyName as mainBuyerCompanyName, Trades.accountId, Trades.settlementDate, Trades.sellerSigned, Trades.listingId, tradeSize as size, tradePrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName, closingDoc, TaxYear.taxYear, PendingListings.cTaxpayerId');
    $this->db->where('PendingListings.cTaxpayerId', $taxpayerId);
    if($dmaId > 0 && $dmaMemberId > 0) {
      $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
      $this->db->where($whereAccess);
    }
    $this->db->from('Trades');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts as mainBuyer", "Trades.accountId = mainBuyer.userId", 'left');
    if($dmaId > 0 && $dmaMemberId > 0) {
      $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
      $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId AND ('open' = creditAccessDmaLevel.caAction OR 'watch' = creditAccessDmaLevel.caAction)", 'left');
    }
    $this->db->order_by("Trades.timestamp desc");
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->get_short_offsets_from_program($data->OffSettingTaxList);
      if($data->status != 2) {
        $data->tstatus = "Pending Trade";
      } else {
        $data->tstatus = "Completed Trade";
      }
      $data->transactions = $this->Trading->get_transactions_of_trade($data->tradeId);
    }

    return $query->result();

  }

  function get_market_on_listing_new($id) {
    $currentDate = date("Y-m-d H:i:s");
    $this->db->select('Bids.bidId as bidId,bidPrice,bidSize,bidTotal,Bids.accountId,Bids.bDmaMemberId, Bids.listingId,Trades.bidId as TbidId,changedPS, Bids.timeStamp, Trades.accountId as TaccountId,Trades.status,Accounts.portalName, Accounts.companyName, Accounts.portalurl, dmaAccounts.title, Bids.minimumCreditIncrement, Bids.bidExpirationDate, Accounts.email, subBuyer.email as subBuyerEmail, subBuyer.firstName as subBuyerFirstName, subBuyer.lastName subBuyerLastName, Bids.aggregateBid');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.listingId', $id);
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('(Bids.bidExpirationDate >= NOW() OR Bids.bidExpirationDate is null)');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "Bids.accountId = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts as subBuyer", "Bids.bDmaMemberId = subBuyer.userId", 'left');
    $this->db->order_by("bidPrice desc, Bids.timestamp");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['transactions'] = $this->Trades->get_transactions_of_bid($data['bidId']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_cuurent_market_on_listing($id) {
    $this->db->select('listingId, ActiveListings.OIXIncentiveId, HighestBid, HighestBidSize,  offerSize, offerPrice');
    $this->db->from('ActiveListings');
    $this->db->where('ActiveListings.listingId', $id);
    $this->db->where('ActiveListings.traded is null');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->order_by("listingId desc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['HighestBids'] = $this->get_highest_bid($data['listingId']);
      $data['bidSize'] = isset($data['HighestBids']['bidSize']) ? $data['HighestBids']['bidSize'] : '0';
      $data['bidPrice'] = isset($data['HighestBids']['bidPrice']) ? $data['HighestBids']['bidPrice'] : '0';

      array_push($return, $data);
    }

    return $return;

  }

  function get_bids($input) {

    $input['dmaId'] = isset($input['dmaId']) ? $input['dmaId'] : null;
    $input['dmaMemberId'] = isset($input['dmaMemberId']) ? $input['dmaMemberId'] : null;

    $this->db->select('Bids.bidId, Bids.timeStamp, Bids.accountId, Bids.listingId, Bids.deleteMarker, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked, Bids.bidExpirationDate, Bids.eoAccountId,TaxYear.taxYear, States.state, States.name, States.sLatitude, States.sLongitude, ActiveListings.brokerDmaId, ActiveListings.offerSize,ActiveListings.offerPrice, ActiveListings.taxTypeIds, buyerAccount.title as buyerCompanyName, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt');

    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->where('ActiveListings.deleteMarker is null');

    $this->credit_where_access($input);
    //$this->credit_where_owner($input);
    //$this->credit_where_shared_owner($input);

    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("creditAccess", "PendingListings.listingId = creditAccess.caListingId", 'left');
    $this->db->join("dmaAccounts as buyerAccount", "buyerAccount.mainAdmin = Bids.accountId", 'left');

    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    $return['bids'] = [];

    foreach($query->result_array() as $data) {

      $data['projectNameFull'] = $data['projectName'] . " " . $data['projectNameExt'];

      $data['OffSettingTaxList'] = null;
      $data['taxTypeNames'] = $this->get_tax_types($data['taxTypeIds']);
      $data['bidActiveFlag'] = 'Yes';
      $data['attachedToCredit'] = 'Yes';

      $data['bidType'] = 'bid';
      $data['creditId'] = $data['listingId'];
      $data['bidId'] = $data['bidId'];
      $data['creditIdFull'] = $data['State'] . $data['listingId'];
      $data['bidIdFull'] = $data['State'] . $data['bidId'];
      if($data['timeStamp'] != "") {
        $data['listingDate'] = substr($data['timeStamp'], 0, strrpos($data['timeStamp'], ' '));
      } else {
        $data['listingDate'] = "-";
      }
      $data['isActive'] = 'Yes';
      $data['priceTotal'] = $data['size'] * $data['price'];
      $data['offsetName'] = "";
      $data['sellerName'] = '';

      $data['transactions'] = $this->Trades->get_transactions_of_bid($data['bidId']);

      array_push($return['bids'], $data);
    }

    return $return;

  }

  function get_bids_on_listing($id) {
    $this->db->select('Bids.bidId as bidId,Bids.listingId');
    $this->db->where('Bids.listingId', $id);
    $this->db->where('Trades.status is null');
    $this->db->where('Bids.deleteMarker is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->order_by("bidPrice desc, Bids.timestamp");
    $query = $this->db->get();

    return $query->result_array();
  }

  function get_bids_details_on_listing($id) {
    $this->db->select('Bids.bidId as bidId,Bids.listingId,bidPrice,bidSize,bidTotal');
    $this->db->where('Bids.listingId', $id);
    $this->db->where('Trades.status is null');
    $this->db->where('Bids.deleteMarker is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->order_by("bidPrice desc, Bids.timestamp");
    $query = $this->db->get();

    return $query->result_array();
  }

  function get_credit_valid_date($id, $program) {
    $this->db->select('carryForwardYears');
    $this->db->where('OIXIncentiveId', $program);
    $this->db->from('IncentivePrograms');
    $query = $this->db->get();

    return strtotime('+' . $query->row()->carryForwardYears . ' years', strtotime($id));

  }

  function get_highest_bid($id) {
    $this->db->select('*');
    $this->db->where('Bids.listingId', $id);
    $this->db->where('tradeId is null');
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('(Bids.bidExpirationDate >= NOW() OR Bids.bidExpirationDate is null)');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("Accounts", "Accounts.userId = Bids.accountId", 'left');
    $this->db->order_by("bidPrice desc, Bids.timestamp");
    $this->db->limit(1);
    $query = $this->db->get();

    return $query->row_array();
  }

  function check_bid($id) {
    $this->db->where('accountId', $this->cisession->userdata('userId'));
    $this->db->from('Bids');

    $count = $this->db->count_all_results();

    return ($count > 0) ? true : false;
  }

  function get_current_program_listing($id) {
    $this->db->select('ActiveListings.timeStamp, ActiveListings.updatedTime, ActiveListings.listingId,ActiveListings.listingDate,links, ActiveListings.OIXIncentiveId, ActiveListings.listed, ActiveListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining, PendingListings.estCreditPrice, PendingListings.budgetExchangeRate, ActiveListings.offerSize, ActiveListings.offerPrice, ActiveListings.allOrNone, ActiveListings.certificationNum, PendingListings.IssueDate,ActiveListings.encumbered, ActiveListings.OfferGoodUntil,ActiveListings.originalOfferSize, ActiveListings.CreditUsedForOffset,offsetName, ActiveListings.CreditUsedDate, maturityDate,  ActiveListings.listedBy,  ActiveListings.brokerDmaId, States.name as stateName, States.state, IncentivePrograms.ProgramName, TaxYear.taxYear, Accounts.companyName, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.phone, Accounts.portalName, IPSectors.sector, IPCategories.category,AdministeringAgency, ActiveListings.creditGoodUntil,  IncentivePrograms.MandatedPriceFloor,IncentivePrograms.AgencyBuybackPrice, IncentivePrograms.NumTimesTransferable, IncentivePrograms.OffSettingTaxList, IncentivePrograms.TransferSummary, IncentivePrograms.TransferDetails,IncentivePrograms.TransferRules, IncentivePrograms.LimitationsSummary, IncentivePrograms.LimitationsDetails, IncentivePrograms.ProgramShortName, IncentivePrograms.UniqueConsiderationsSummary, IncentivePrograms.UniqueConsiderationsDetails, IncentivePrograms.StatuteNumber, PendingListings.cCarryForwardYears as CarryForwardYears, PendingListings.legislativeFramework, (TaxYear.taxYear+PendingListings.cCarryForwardYears) as maxYears, IncentivePrograms.CarryForwardDetails, IncentivePrograms.CarryForwardSummary, IncentivePrograms.StatuteLink, ActiveListings.taxTypeIds, ActiveListings.finalUsableTaxYear, ActiveListings.incrementAmount, taxAuthority,OffsetLocked,ActiveListings.ownPS,PendingListings.certificationStatus, MandatedPriceFloor, cert_status_type.cert_status_name, ActiveListings.OfferGoodUntil, portalurl as sportalurl, Bids.aggregateBid, PendingListings.status, PendingListings.cTaxpayerId, PendingListings.provisions, ActiveListings.listingCustomerAssist, PendingListings.creditIssuedTo, ActiveListings.listingAnonymously, Accounts.accountType, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName');
    $this->db->where('ActiveListings.listingId', $id);
    $this->db->from('ActiveListings');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("Accounts", "Accounts.userId = ActiveListings.listedBy", 'left');
    $this->db->join("IPCategories", "IPCategories.id = IncentivePrograms.Category", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Bids", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("remote_links", "ActiveListings.listingId = remote_links.listing_id AND remote_links.upload_type='Purchase Sale Agreement' AND remote_links.active_status=1", 'left');
    //$this->db->where('upload_type', 'agreement');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['taxTypeNames'] = $this->get_tax_types($data['taxTypeIds']);

      $data['latestTwoSales'] = $this->Trading->get_last_two_trades($data['listingId']);
      $data['lastSale'] = isset($data['latestTwoSales'][0]['tradePrice']) ? $data['latestTwoSales'][0]['tradePrice'] : '0';
      $data['priorSale'] = isset($data['latestTwoSales'][1]['tradePrice']) ? $data['latestTwoSales'][1]['tradePrice'] : '0';

      $data['offerPrice'] = ($data['offerPrice']) * $data['offerSize'];
      if($data['offerSize'] > 0) {
        $data['offerPcnt'] = $data['offerPrice'] / $data['offerSize'];
      } else {
        $data['offerPcnt'] = 0;
      }
      $data['HighestBids'] = $this->get_highest_bid($data['listingId']);
      $data['bportalurl'] = isset($data['HighestBids']['portalurl']) ? $data['HighestBids']['portalurl'] : '';

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function reduce_bid_size($bid, $bidSize, $originalBidSize, $updateid) {

    $data_two = [

        'BidSize'         => $bidSize,
        'originalBidSize' => $originalBidSize,
        'updateId'        => $updateid,
        'updatedTime'     => date('Y-m-d H:i:s'),
        'updatedAdminBy'  => $this->cisession->userdata('auserId'),
        'updatedBy'       => $this->cisession->userdata('userId'),

    ];
    $this->db->where('BidId', $bid);
    $this->db->update('Bids', $data_two);

    return $this->db->affected_rows() > 0;
  }

  function update_listing_status($listingId) {
    $data_two = [
        'traded'         => '1',
        'updatedTime'    => date('Y-m-d H:i:s'),
        'updatedAdminBy' => $this->cisession->userdata('auserId'),
        'updatedBy'      => $this->cisession->userdata('userId'),

    ];
    $this->db->where('listingId', $listingId);
    $this->db->update('ActiveListings', $data_two);

    return $this->db->affected_rows() > 0;
  }

  function get_short_offsets_from_program($offsets) {
    $offsets_arr = explode(",", $offsets);
    $return_string = "";
    if($offsets_arr != "") {
      foreach($offsets_arr as $key => $pair) {
        $query = $this->db->get_where('Offsets', ['id' => $pair]);
        $this_o = $query->row();
        if(isset($this_o->shortOffset)) {
          $return_string .= $this_o->shortOffset;
          if($key < count($offsets_arr) - 1) {
            $return_string .= ", ";
          }
        }
      }
    }

    return $return_string;
  }

  function get_tax_types($taxTypeIds) {
    $offsets_arr = explode(",", $taxTypeIds);
    $return_string = "";
    if(sizeof($offsets_arr) > 0) {
      foreach($offsets_arr as $key => $pair) {
        $query = $this->db->get_where('IPTaxTypes', ['id' => $pair]);
        $this_o = $query->row();
        if(isset($this_o->tax_type)) {
          $return_string .= $this_o->tax_type;
          if($key < count($offsets_arr) - 1) {
            $return_string .= ", ";
          }
        }
      }
    }

    return $return_string;
  }

  function add_bid() {

    if($this->input->post('good_until_date') > 0) {
      $timeToAdd = 82800; //add 23 hours (82,800 seconds) to make expiration date at very end of day selected
      $bidExpirationDate = strtotime($this->input->post('good_until_date')) + $timeToAdd;
    }

    if($this->input->post('listedByForm')) {
      $listedBy = $this->input->post('listedByForm');
    } else {
      $listedBy = "";
    }

    //If the user wants to negotiate the PSA, prepare this setting for the DB
    if($this->input->post('agreeToPSA') == 'no') {
      $changedPS = 1;
    } else {
      $changedPS = 0;
    }

    //Check if this is a bid, or a type of transfer
    $thisActionType = "";
    if(isset($_POST['bidActionType'])) {
      if($_POST['bidActionType'] == "internal_transfer" || $_POST['bidActionType'] == "external_transfer") {
        $thisActionType = $_POST['bidActionType'];
      }
    }

    if($thisActionType == "internal_transfer") {
      $thisAccountId = $_POST['transfer_account'];
    } else {
      if($thisActionType == "external_transfer") {
        $thisAccountId = 0;
      } else {
        $thisAccountId = $this->cisession->userdata('primUserId');
      }
    }

    $data = [
      //'bidId' => $bidId,
      'timeStamp'              => ($thisActionType == "internal_transfer" || $thisActionType == "external_transfer") ? date('Y-m-d H:i:s', strtotime($_POST['transfer_date'])) : date('Y-m-d H:i:s'),
      'accountId'              => $thisAccountId,
      'bDmaMemberId'           => ($thisActionType == "internal_transfer" || $thisActionType == "external_transfer") ? "" : $this->cisession->userdata('secUserId'),
      'listingId'              => $this->input->post('listingId'),
      'bidSize'                => $this->input->post('bid_size'),
      'bidPrice'               => $this->input->post('bid_price_percentage'),
      'reducable'              => $this->input->post('reducable'),
      'forClient'              => ($this->input->post('client_purchase')) ? '1' : '0',
      'bidTotal'               => $this->input->post('bid_size') * ($this->input->post('bid_price_percentage')),
      'changedPS'              => $changedPS,
      'updatedTime'            => date('Y-m-d H:i:s'),
      'updatedAdminBy'         => $this->cisession->userdata('auserId'),
      'updatedBy'              => $this->cisession->userdata('userId'),
      'bidExpirationDate'      => ($this->input->post('good_until_date')) ? date('Y-m-d H:i:s', $bidExpirationDate) : null,
      'minimumCreditIncrement' => $this->input->post('increments'),
      'eoAccountId'            => $listedBy,

    ];

    $this->db->insert('Bids', $data);

    return $this->db->insert_id();
  }

  function update_bid($bidId) {

    if($this->input->post('good_until_date') > 0) {
      $timeToAdd = 82800; //add 23 hours (82,800 seconds) to make expiration date at very end of day selected
      $bidExpirationDate = strtotime($this->input->post('good_until_date')) + $timeToAdd;
    }

    //If the user wants to negotiate the PSA, prepare this setting for the DB
    if($this->input->post('agreeToPSA') == 'no') {
      $changedPS = 1;
    } else {
      $changedPS = 0;
    }

    $data = [
        'bidSize'                => $this->input->post('bid_size'),
        'bidPrice'               => $this->input->post('bid_price_percentage'),
        'reducable'              => ($this->input->post('reducable')) ? '1' : '0',
        'bidTotal'               => $this->input->post('bid_size') * ($this->input->post('bid_price_percentage')),
        'changedPS'              => $changedPS,
        'updatedTime'            => date('Y-m-d H:i:s'),
        'updatedBy'              => $this->cisession->userdata('userId'),
        'bidExpirationDate'      => ($this->input->post('good_until_date')) ? date('Y-m-d H:i:s', $bidExpirationDate) : null,
        'minimumCreditIncrement' => $this->input->post('increments'),
    ];

    $this->db->where('BidId', $bidId);
    $this->db->update('Bids', $data);

    return true;
  }

  function delete_credit($listingId) {
    $data = [
        'deleteMarker' => '1',
        'updatedTime'  => date('Y-m-d H:i:s'),
        'updatedBy'    => $this->cisession->userdata('userId'),
    ];

    $this->db->where('listingId', $listingId);
    $this->db->update('ActiveListings', $data);

    return true;
  }

  function update_credit_archive_status($listingId, $status) {
    $status = ($status == 1) ? 1 : null;
    $data = [
        'cArchived'   => $status,
        'updatedTime' => date('Y-m-d H:i:s'),
        'updatedBy'   => $this->cisession->userdata('userId'),
    ];

    $this->db->where('listingId', $listingId);
    $this->db->update('PendingListings', $data);

    return true;
  }

  //Centralized function for updating the PendingListing "last updated" field
  function updateTime($listingId) {
    $data = [
        'updatedTime' => date('Y-m-d H:i:s'),
    ];

    $this->db->where('listingId', $listingId);
    $this->db->update('ActiveListings', $data);

    $this->db->where('listingId', $listingId);
    $this->db->update('PendingListings', $data);
  }

  function get_expired_listings_7days() {

    $this->db->select('ActiveListings.listingId,ActiveListings.OfferGoodUntil');
    $this->db->from('ActiveListings');
    $this->db->where('ActiveListings.deleteMarker is Null');
    $expwhere = "((`ActiveListings`.`OfferGoodUntil` != 0) AND (timestampdiff(DAY,curdate(),from_unixtime(`ActiveListings`.`OfferGoodUntil`)) >0))";
    $this->db->where($expwhere, null, false);
    $expwhere2 = "((`ActiveListings`.`OfferGoodUntil` != 0) AND (timestampdiff(DAY,curdate(),from_unixtime(`ActiveListings`.`OfferGoodUntil`)) <7))";
    $this->db->where($expwhere2, null, false);
    $this->db->where('ActiveListings.traded is null');
    $query = $this->db->get();
    $return = [];
    $bidsinfo = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;
  }

  function get_expired_listings_2days() {

    $this->db->select('ActiveListings.listingId,ActiveListings.OfferGoodUntil');
    $this->db->from('ActiveListings');
    $this->db->where('ActiveListings.deleteMarker is Null');
    $expwhere = "((`ActiveListings`.`OfferGoodUntil` != 0) AND (timestampdiff(DAY,curdate(),from_unixtime(`ActiveListings`.`OfferGoodUntil`)) >0))";
    $this->db->where($expwhere, null, false);
    $expwhere2 = "((`ActiveListings`.`OfferGoodUntil` != 0) AND (timestampdiff(DAY,curdate(),from_unixtime(`ActiveListings`.`OfferGoodUntil`)) <2))";
    $this->db->where($expwhere2, null, false);
    $this->db->where('ActiveListings.traded is null');
    $query = $this->db->get();
    $return = [];
    $bidsinfo = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;
  }

  function get_active_listing_trade($id) {

    $this->db->select('ActiveListings.timeStamp,portalName, States.name,States.state,States.countryId,IncentivePrograms.OffSettingTaxList, ProgramType.ProgramTypeName,links, ActiveListings.OIXIncentiveId,ActiveListings.taxYearId, ActiveListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.estCreditPrice, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, ActiveListings.offerSize, ActiveListings.offerPrice, ActiveListings.allOrNone, ActiveListings.certificationNum, PendingListings.IssueDate, ActiveListings.OfferGoodUntil, ActiveListings.CreditUsedForOffset,Offsets.id as OffsetsId,offsetName, ActiveListings.CreditUsedDate, ActiveListings.listedBy, IncentivePrograms.State, IncentivePrograms.ProgramName, Accounts.companyName, IPSectors.sector, ActiveListings.encumbered,ActiveListings.traded,ActiveListings.deleteMarker, IPCategories.category,AdministeringAgency, ActiveListings.creditGoodUntil, ActiveListings.incrementAmount, ActiveListings.incrementAmount as minAmount,IncentivePrograms.MandatedPriceFloor,IncentivePrograms.AgencyBuybackPrice, IncentivePrograms.NumTimesTransferable, IncentivePrograms.OffSettingTaxList, HighestBid, HighestBidSize, ActiveListings.listingId,TaxYear.taxYear, (TaxYear.taxYear+PendingListings.cCarryForwardYears) AS maxYears, PendingListings.availableToList, PendingListings.availableToList as amountLocalRemaining, ActiveListings.originalOfferSize,OffsetLocked,ActiveListings.ownPS,MandatedPriceFloor, cert_status_type.cert_status_name, ActiveListings.updatedTime');
    $this->db->where('ActiveListings.listingId', $id);
    //$this->db->where('ActiveListings.traded is null');
    $this->db->from('ActiveListings');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("Accounts", "Accounts.userId = listedBy", 'left');
    $this->db->join("IPCategories", "IPCategories.id = IncentivePrograms.Category", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("cert_status_type", "ActiveListings.certificationStatus = cert_status_type.cert_status_id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("remote_links", "ActiveListings.listingId = remote_links.listing_id", 'left');
    //$this->db->where('upload_type', 'agreement');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);

      $data['offerPrice'] = ($data['offerPrice']) * $data['offerSize'];
      if($data['offerSize'] > 0) {
        $data['offerPcnt'] = $data['offerPrice'] / $data['offerSize'];
      } else {
        $data['offerPcnt'] = '0';
      }

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_my_bid_trade($id) {
    $this->db->select('Bids.*, Bids.deleteMarker as bidDeleteMarker, Accounts.*');
    $this->db->where('bidId', $id);
    $this->db->join("Accounts", "Accounts.userId = Bids.accountId", 'left');
    $this->db->from('Bids');
    $query = $this->db->get();

    return $query->row_array();
  }

  function updatePSAstatus($listingId, $ownPS, $needPS) {

    //Update Pending Listing
    $data1 = [
        'ownPS'  => $ownPS,
        'needPS' => $needPS,
    ];
    $this->db->where('listingId', $listingId);
    $this->db->update('PendingListings', $data1);

    //Update Active Listing
    $data2 = [
        'ownPS' => $ownPS,
    ];
    $this->db->where('listingId', $listingId);
    $this->db->update('ActiveListings', $data2);

    return "success";
  }

  function get_seller_of_credit($listingId) {

    $credit = $this->get_credit_private($listingId);

    //Get DMA info for seller
    $dmaSeller = [];

    //Get email address for buyer and seller
    //If a DMA seller
    if($dmaSeller != "") {
      $sellerInfo = $this->Members_Model->get_member_by_id($credit['cDmaMemberId']);
      $data['accountTitleSeller'] = $dmaSeller['title'];
      $data['firstNameSeller'] = $sellerInfo['firstName'];
      $data['lastNameSeller'] = $sellerInfo['lastName'];
      $data['emailSeller'] = $sellerInfo['email'];
      $data['sellerType'] = 'dma';
    } else {
      //If a general member seller
      $sellerInfo = $this->Members_Model->get_member_by_id($credit['listedBy']);
      $data['accountTitleSeller'] = $sellerInfo['companyName'];
      $data['firstNameSeller'] = $sellerInfo['firstName'];
      $data['lastNameSeller'] = $sellerInfo['lastName'];
      $data['emailSeller'] = $sellerInfo['email'];
      $data['sellerType'] = 'general';
    }

    return $data;

  }

  function getRecentlyLoadedCredits($seconds) {

    $checkUntil = time() - $seconds;
    $checkUntil = date('Y-m-d h:i:s', $checkUntil);

    $this->db->select('PendingListings.*, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining, PendingListings.estCreditPrice, PendingListings.budgetExchangeRate, Accounts.companyName, States.state, dmaAccounts.title, dmaAccounts.dmaType');
    $this->db->where('PendingListings.timeStamp >=', $checkUntil);
    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->where('dmaAccounts.dmaId !=', 2); //This is OIX Developers on Production
    $this->db->where('dmaAccounts.dmaId !=', 592); //This is The OIX account on Production
    $this->db->join("ActiveListings", "PendingListings.listingId = ActiveListings.pendingListingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("dmaAccounts", "PendingListings.listedBy = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts", "dmaAccounts.primary_account_id = Accounts.userId", 'left');
    $query = $this->db->get('PendingListings');

    $return = [];
    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      if($data['dmaType'] == 'customer_advisor' || $data['dmaType'] == 'customer_broker') {
        $data['thirdPartyManagerOfCredit'] = $this->Members_Model->get_member_by_id($data['updatedBy']);
      } else {
        $data['thirdPartyManagerOfCredit'] = [];
      }
      array_push($return, $data);
    }

    return $return;

  }

  function getCreditsListedSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('ActiveListings.timeStamp, ActiveListings.listedBy, PendingListings.cDmaMemberId, PendingListings.estCreditPrice, ActiveListings.listingDate, States.name, States.state, IncentivePrograms.OffSettingTaxList, ActiveListings.taxYearId, ActiveListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining, PendingListings.budgetExchangeRate, ActiveListings.offerSize, ActiveListings.offerPrice, ActiveListings.CreditUsedForOffset,Offsets.id as OffsetsId,offsetName, IncentivePrograms.ProgramName, IncentivePrograms.OffSettingTaxList, ActiveListings.listingId,TaxYear.taxYear, (TaxYear.taxYear+PendingListings.cCarryForwardYears) AS maxYears, ActiveListings.originalOfferSize');

    $this->db->where("ActiveListings.listingDate >", $checkSince);
    $this->db->where('ActiveListings.offerSize is not null');
    $this->db->where("ActiveListings.listed", 1);
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->order_by("ActiveListings.listingDate desc");
    $query = $this->db->get('ActiveListings');

    $return = [];
    foreach($query->result_array() as $data) {
      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['trades'] = $this->Trading->getTradesInfo($data['listingId']);
      $data['tradesTotal'] = 0;
      foreach($data['trades'] as $tt) {
        $data['tradesTotal'] = $data['tradesTotal'] + $tt['tradeSize'];
      }
      //$data['bids'] = $this->get_market_on_listing_new($data['listingId']);

      if((!in_array($data['listedBy'], $this->config->item('oix_tester_ids'))) && (!in_array($data['cDmaMemberId'], $this->config->item('oix_tester_ids')))) {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_share_count_of_credit($listing_id, $type = "") {

    $this->db->select('COUNT(sId) shareCount');
    $this->db->where('Shares.sItemId', $listing_id);
    $this->db->where('Shares.sDeleteMarker', null);
    $query = $this->db->get('Shares');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    } else {
      return 0;
    }

  }

  function get_shares_of_credit($listing_id, $type = "") {

    $this->db->select('Shares.*, dmaAccounts.title as sharedCompany, dmaAccounts.profileUrl as sharedProfileUrl, dmaAccounts.profileUrl, dmaAccounts.dmaId, dmaAccounts.mainAdmin, Accounts.firstName as mainAdminFirstName, Accounts.lastName as mainAdminLastName');
    $this->db->where('Shares.sItemId', $listing_id);
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->join("dmaAccounts", "Shares.sharedPrimId = dmaAccounts.mainAdmin", 'left');
    $this->db->join("Accounts", "dmaAccounts.primary_account_id = Accounts.userId", 'left');
    $query = $this->db->get('Shares');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_shares_of_credit_lite($listing_id, $type = "") {

    $this->db->select('dmaAccounts.title as sharedCompany, dmaAccounts.profileUrl, dmaAccounts.dmaId, dmaAccounts.mainAdmin');
    $this->db->where('Shares.sItemId', $listing_id);
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->join("dmaAccounts", "Shares.sharedPrimId = dmaAccounts.mainAdmin", 'left');
    $query = $this->db->get('Shares');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_shares($sharerPrimId = "", $sharerSecId = "", $sharedPrimId = "", $sharedSecId = "", $sItemId = "", $sType = "", $sStatus = "") {

    $this->db->select('Shares.*, dmaAccounts.title as sharedCompany, dmaAccounts.profileUrl');
    $this->db->where('Shares.sharedPrimId', $sharedPrimId);
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->join("dmaAccounts", "Shares.sharedPrimId = dmaAccounts.mainAdmin", 'left');
    $query = $this->db->get('Shares');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_share($sId = "", $listingId = "", $sType = "", $sharedPrimId = "") {

    $this->db->select('Shares.*, dmaAccounts.title as sharedCompany, dmaAccounts.profileUrl as sharedProfileUrl');
    if($sId > 0) {
      $this->db->where('Shares.sId', $sId);
    } else {
      if($sType != "" && $listingId != "" && $sharedPrimId != "") {
        $this->db->where('Shares.sType', $sType);
        $this->db->where('Shares.sItemId', $listingId);
        $this->db->where('Shares.sharedPrimId', $sharedPrimId);
      } else {
        throw new \Exception('General fail');
      }
    }
    $this->db->where('Shares.sDeleteMarker', null);
    $this->db->join("dmaAccounts", "Shares.sharedPrimId = dmaAccounts.mainAdmin", 'left');
    $query = $this->db->get('Shares');

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function update_share_status($sId, $advisorStatus) {

    //Update Pending Listing
    $data = [
        'advisorStatus' => $advisorStatus,
    ];

    $this->db->where('sId', $sId);
    $this->db->update('Shares', $data);

  }

  function get_list_order_options($listType) {

    $return = [];

    if($listType == "credits") {
      $orderArray = ["jurisdiction", "project", "year", "program", "type", "orig_amt", "loaded"];
    } else {
      if($listType == "utilizations") {
        $orderArray = ["trade_date", "trade_amt", "jurisdiction", "project", "year", "program", "type"];
      } else {
        if($listType == "utilizations_min") {
          $orderArray = ["jurisdiction", "project", "year", "program", "type"];
        } else {
          if($listType == "purchases") {
            $orderArray = ["trade_date", "trade_amt", "jurisdiction", "year", "program", "type"];
          } else {
            if($listType == "compliance") {
              $orderArray = ["complianceDueDate", "project", "jurisdiction", "program"];
            }
          }
        }
      }
    }

    if($this->cisession->userdata('dmaType') == "broker" || $this->cisession->userdata('dmaType') == "advisor") {
      array_unshift($orderArray, 'customer');
    }

    foreach($orderArray as $oa) {

      if($oa == "customer") {
        $return["seller_a_z"] = "Customer (A-Z)";
        $return["seller_z_a"] = "Customer (Z-A)";
      }
      if($oa == "jurisdiction") {
        $return["jurisdiction_a_z"] = "Jurisdiction (A-Z)";
        $return["jurisdiction_z_a"] = "Jurisdiction (Z-A)";
      }
      if($oa == "loaded") {
        $return["loaded_n_o"] = "Date Loaded (New-Old)";
        $return["loaded_o_n"] = "Date Loaded (Old-New)";
      }
      if($oa == "project") {
        $return["project_a_z"] = "Project (A-Z)";
        $return["project_z_a"] = "Project (Z-A)";
      }
      if($oa == "year") {
        $return["year_h_l"] = "Tax Year (High-Low)";
        $return["year_l_h"] = "Tax Year (Low-High)";
      }
      if($oa == "program") {
        $return["program_a_z"] = "Incentive Program (A-Z)";
        $return["program_z_a"] = "Incentive Program (Z-A)";
      }
      if($oa == "type") {
        $return["type_a_z"] = "Credit Type (A-Z)";
        $return["type_z_a"] = "Credit Type (Z-A)";
      }
      if($oa == "orig_amt") {
        $return["orig_amt_l_h"] = "Total Credit Amount (Low-High)";
        $return["orig_amt_h_l"] = "Total Credit Amount (High-Low)";
      }

      if($oa == "trade_date") {
        $return["trade_date_n_o"] = "Utilization Date (New-Old)";
        $return["trade_date_o_n"] = "Utilization Date (Old-New)";
      }
      /* Removed this now because this is ordering by tradeSize, which is empty for estimated utilizations (need to expand functionality to get this to work...)
      if($oa=="trade_amt") {
        $return["trade_amt_h_l"] = "Utilization Amount (High-Low)";
        $return["trade_amt_l_h"] = "Utilization Amount (Low-High)";
      }
      */

      if($oa == "complianceDueDate") {
        $return["compliance_due_date_l_h"] = "Compliance Due Date (First-Last)";
        $return["compliance_due_date_h_l"] = "Compliance Due Date (Last-First)";
      }

    }

    return $return;

  }

  function get_dma_credits_count($mainAdmin) {

    $this->db->select('COUNT(PendingListings.listingId) as creditCount');
    $this->db->where('PendingListings.listedBy', $mainAdmin);
    $query = $this->db->get('PendingListings');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0]['creditCount'];
    }

  }

  function check_data_point_key_availability(string $key) {
    $this->db->select('dataPoints.dpId');
    $this->db->where('dataPoints.dpKey', $key);

    $this->db->from('dataPoints');
    $query = $this->db->get();

    $existing = [];

    foreach($query->result_array() as $data) {
      array_push($existing, $data);
    }

    if(count($existing) > 0) {
      return false;
    }

    return true;
  }

  function insert_custom_data_point($request) {

    $dpDmaId = $request['dpDmaId'];
    $dpNameFull = $request['dpName'];
    $dpNameShort = $request['dpName'];
    $isParent = $request['is_parent'];
    $parentId = $request['parent_id'];
    $dpKey = "fieldCustom_" . $request['dpDmaId'] . "_" . time();
    $dpValue = "custom_" . $request['dpDmaId'] . "_" . time();
    $dpType = $request['dpType'];
    $dpSection = $request['dpSection'];
    $value = $request['value'];
    $dpObjectType = isset($request['dpObjectType']) ? $request['dpObjectType'] : 'credit';
    $dpOptionGroupId = isset($request['option_group_id']) ? $request['option_group_id'] : null;
    $dpIsRequired = $request['dpIsRequired'];
    $dpDescription = $request['dpDescription'];

    $keyAvailable = $this->check_data_point_key_availability($dpKey);
    if(!$keyAvailable) {
      $numTries = 0;
      while($numTries < 3 && !$keyAvailable) {
        $randStr = substr(md5(mt_rand()), 0, 6);
        $keyAvailable = $this->check_data_point_key_availability($dpKey . $randStr);
        if($keyAvailable) {
          $dpKey .= $randStr;
          $dpValue .= $randStr;
        }
        $numTries++;
      }
      if(!$keyAvailable) {
        throw new \Exception('Unable to generate random unique key for data point at creation time, noping out.');
      }
    }

    $data = [
        'dpDmaId'         => $dpDmaId,
        'dpObjectType'    => $dpObjectType,
        'dpNameFull'      => $dpNameFull,
        'dpNameShort'     => $dpNameShort,
        'dpKey'           => $dpKey,
        'dpValue'         => $dpValue,
        'dpType'          => $dpType,
        'dpSection'       => $dpSection,
        'option_group_id' => $dpOptionGroupId,
        'dpIsRequired'    => $dpIsRequired == 1 ? 1 : 0,
        'dpDescription'   => strlen(trim($dpDescription)) > 0 ? $dpDescription : null,
    ];

    switch($dpObjectType) {
      case 'credit':
        $data['dpReportMyCredits'] = 1;
        $data['dpMyCreditsView'] = 1;
        break;
      case 'utilization':
        $data['dpReportTrades'] = 1;
        break;
    }

    if(isset($value)) {
      $data['value'] = $value;
    }

    if(isset($isParent)) {
      $data['is_parent'] = $isParent;
    }
    if(isset($parentId)) {
      $data['parent_id'] = $parentId;
    }

    $this->db->insert('dataPoints', $data);

    return $this->db->insert_id();
  }

  function update_custom_data_point($request) {

    $dpId = $request['dpId'];
    $dpNameFull = $request['dpName'];
    $dpNameShort = $request['dpName'];
    $dpType = $request['dpType'];
    $dpSection = $request['dpSection'];
    $dpObjectType = $request['dpObjectType'];
    $dpOptionGroupId = isset($request['option_group_id']) ? $request['option_group_id'] : null;
    $dpIsRequired = $request['dpIsRequired'];
    $dpDescription = $request['dpDescription'];
    $isParent = $request['is_parent'];
    $parentId = $request['parent_id'];
    $archivedMarker = $request['dpArchivedMarker'];

    if($dpType != "") {
      $data = [
          'dpNameFull'      => $dpNameFull,
          'dpNameShort'     => $dpNameShort,
          'dpSection'       => $dpSection,
          'option_group_id' => $dpOptionGroupId,
          'dpType'          => $dpType,
          'dpObjectType'    => $dpObjectType,
          'dpIsRequired'    => $dpIsRequired == 1 ? 1 : 0,
          'dpDescription'   => strlen(trim($dpDescription)) > 0 ? $dpDescription : null,
      ];
    } else {
      $data = [
          'dpNameFull'       => $dpNameFull,
          'dpNameShort'      => $dpNameShort,
          'dpSection'        => $dpSection,
          'dpObjectType'     => $dpObjectType,
          'dpArchivedMarker' => $archivedMarker,
      ];
    }

    if($dpType == 'selectDropDown') {
      $data['value'] = $request['value'];
    }

    if(isset($isParent)) {
      $data['is_parent'] = $isParent;
    }
    if(isset($parentId)) {
      $data['parent_id'] = $parentId;
    }

    $this->db->where('dpId', $dpId);
    $this->db->update('dataPoints', $data);
    //return $this->db->insert_id();

  }

  function update_custom_data_point_status($request) {

    $dpId = $request['dpId'];
    $field = $request['field'];
    $value = $request['value'];

    $data = [
        $field => $value,
    ];

    $this->db->where('dpId', $dpId);
    $this->db->update('dataPoints', $data);

  }

  public function delete_custom_data_point(int $dmaId, int $dpId) {
    //TODO: delete data point values?

    $this->db->where('parent_id', $dpId);
    $this->db->where('dpDmaId', $dmaId);
    $this->db->delete('dataPoints');

    $this->db->where('dpId', $dpId);
    $this->db->where('dpDmaId', $dmaId);
    $this->db->delete('dataPoints');
  }

  public function insertCustomDataPointOption($optionGroupId, $optionText, $displayOrder) {
    $data = [
        'option_group_id' => $optionGroupId,
        'label'           => $optionText,
        'display_order'   => $displayOrder,
    ];
    $this->db->insert('data_point_option', $data);

    return $this->db->insert_id();
  }

  public function updateCustomDataPointOption($optionId, $optionText, $displayOrder, $isArchived = 0) {
    $data = [
        'is_archived'   => $isArchived,
        'label'         => $optionText,
        'display_order' => $displayOrder,
    ];
    $this->db->where('id', $optionId);
    $this->db->update('data_point_option', $data);
  }

  public function changeCustomDataPointOptionValues($optionGroupId, $oldOptionId, $newOptionId, $oldOptionLabel, $newOptionLabel) {
    $this->db->select('dataPointCustomValues.cvObjectId cvListingId, PendingListings.listedBy, Taxpayers.addedBy, dmaAccounts.dmaId, dataPoints.dpNameFull, dataPoints.option_group_id as dpOptionGroupId');
    $this->db->join('dataPoints', 'dataPoints.dpId = dataPointCustomValues.cvDpId', 'left');
    $this->db->join('PendingListings', 'PendingListings.listingId = dataPointCustomValues.cvObjectId', 'left');
    $this->db->join('Taxpayers', 'Taxpayers.taxpayerId = dataPointCustomValues.cvObjectId', 'left');
    $this->db->join('dmaAccounts', 'PendingListings.listedBy = dmaAccounts.mainAdmin', 'left');
    $this->db->join('dmaAccounts DmaAccountsTp', 'Taxpayers.dmaAccountId = DmaAccountsTp.dmaId', 'left');
    $this->db->where('dataPoints.option_group_id', $optionGroupId);
    $this->db->where('option_value_id', $oldOptionId);

    $this->db->from('dataPointCustomValues');
    $query = $this->db->get();
    $updateAudit = [];
    foreach($query->result_array() as $data) {
      array_push($updateAudit, $data);
    }

    $data = [
        'option_value_id' => $newOptionId,
    ];
    //$this->db->where('cvDpId', $dpId);
    $this->db->where('option_value_id', $oldOptionId);
    $this->db->update('dataPointCustomValues', $data);

    foreach($updateAudit as $updateAuditItem) {
      $data = [
          'audCustomName'  => $updateAuditItem['dpNameFull'],
          'audTimestamp'   => time(),
          'audUserId'      => $this->cisession->userdata('userId'),
          'audDmaId'       => $updateAuditItem['dmaId'],
          'audTypeId'      => 0,
          'audItemId'      => $updateAuditItem['cvListingId'],
          'audValueBefore' => $oldOptionLabel,
          'audValueAfter'  => $newOptionLabel,
      ];
      $this->db->insert('audit_trail', $data);
      //Update the time on the credit so cache gets refreshed on its next request
      $this->updateTime($updateAuditItem['cvListingId']);
    }
  }

  public function deleteCustomDataPointOption($optionId, $optionGroupId, $oldLabel) {
    $this->db->select('dataPointCustomValues.cvObjectId cvListingId, PendingListings.listedBy, Taxpayers.addedBy, dmaAccounts.dmaId, dataPoints.dpNameFull, dataPoints.option_group_id as dpOptionGroupId');
    $this->db->join('dataPoints', 'dataPoints.dpId = dataPointCustomValues.cvDpId', 'left');
    $this->db->join('PendingListings', 'PendingListings.listingId = dataPointCustomValues.cvObjectId', 'left');
    $this->db->join('Taxpayers', 'Taxpayers.taxpayerId = dataPointCustomValues.cvObjectId', 'left');
    $this->db->join('dmaAccounts', 'PendingListings.listedBy = dmaAccounts.mainAdmin', 'left');
    $this->db->join('dmaAccounts DmaAccountsTp', 'Taxpayers.dmaAccountId = DmaAccountsTp.dmaId', 'left');
    $this->db->where('dataPoints.option_group_id', $optionGroupId);
    $this->db->where('option_value_id', $optionId);
    $this->db->from('dataPointCustomValues');
    $query = $this->db->get();
    $updateAudit = [];
    foreach($query->result_array() as $data) {
      array_push($updateAudit, $data);
    }
    $this->db->where('option_value_id', $optionId);
    $this->db->delete('dataPointCustomValues');
    $this->db->where('id', $optionId);
    $this->db->delete('data_point_option');
    foreach($updateAudit as $updateAuditItem) {
      $data = [
          'audCustomName'  => $updateAuditItem['dpNameFull'],
          'audTimestamp'   => time(),
          'audUserId'      => $this->cisession->userdata('userId'),
          'audDmaId'       => $updateAuditItem['dmaId'],
          'audTypeId'      => 0,
          'audItemId'      => $updateAuditItem['cvListingId'],
          'audValueBefore' => $oldLabel,
          'audValueAfter'  => '',
      ];
      $this->db->insert('audit_trail', $data);
      //Update the time on the credit so cache gets refreshed on its next request
      $this->updateTime($updateAuditItem['cvListingId']);
    }

    return true;
  }

  public function getCustomDataPointOptions($dpId) {
    $this->db->select('dpo.*, (SELECT COUNT(*) FROM dataPointCustomValues dpv WHERE dpv.option_value_id = dpo.id AND dpv.is_deleted = 0) num_values');
    $this->db->join('data_point_option_group dpog', 'dpo.option_group_id = dpog.id', 'left');
    $this->db->join('dataPoints dp', 'dp.option_group_id = dpog.id', 'left');
    $this->db->where('dp.dpId', $dpId);
    $this->db->order_by("dpo.is_archived, dpo.display_order ASC");

    $this->db->from('data_point_option dpo');
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;
  }

  function update_custom_data_point_value($request) {

    $dpId = $request['dpId'];
    $objectId = $request['listingId'];
    $value = $request['value'];
    $option_value_id = (trim($request['option_value_id']) !== '') ? $request['option_value_id'] : null;

    //First check if a value row exists in the database
    $select = 'dataPointCustomValues.cvId';
    $this->db->select($select);
    $this->db->where('dataPointCustomValues.cvDpId', $dpId);
    $this->db->where('dataPointCustomValues.cvObjectId', $objectId);
    $this->db->from('dataPointCustomValues');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {

      //If EXISTS - then Update it
      $data = [
          'cvValue'         => $value,
          'option_value_id' => $option_value_id,
      ];

      $this->db->where('cvDpId', $dpId);
      $this->db->where('cvObjectId', $objectId);
      $this->db->update('dataPointCustomValues', $data);

    } else {

      //If NOT, then insert it
      $data = [
          'cvDpId'          => $dpId,
          'cvObjectId'      => $objectId,
          'cvValue'         => $value,
          'option_value_id' => $option_value_id,
      ];

      $this->db->insert('dataPointCustomValues', $data);

      return $this->db->insert_id();

    }

  }

  function get_data_point($dpId = "", $dpKey = "") {

    if($dpId == "" && $dpKey == "") {
      throw new \Exception('General fail');
    }

    $select = 'dataPoints.dpId, dataPoints.dpNameFull, dataPoints.dpNameShort, dataPoints.dpKey, dataPoints.dpDmaId, dataPoints.dpValue, dataPoints.dpType, dataPoints.dpAuditTrailId';
    $this->db->select($select);
    if($dpId > 0) {
      $this->db->where('dataPoints.dpId', $dpId);
    }
    if($dpKey != "") {
      $this->db->where('dataPoints.dpKey', $dpKey);
    }
    $this->db->from('dataPoints');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    } else {
      return [];
    }

  }

  function check_data_point_name_availability(string $name, int $dmaId, int $id = 0) {
    $this->db->select('dataPoints.dpId');
    $this->db->where('dataPoints.dpDmaId', $dmaId);
    $this->db->where('dataPoints.dpNameFull', $name);
    if($id > 0) {
      $this->db->where('dataPoints.dpId !=', $id);
    }

    $this->db->from('dataPoints');
    $query = $this->db->get();

    $existing = [];

    foreach($query->result_array() as $data) {
      array_push($existing, $data);
    }

    if(count($existing) > 0) {
      return false;
    }

    return true;
  }

  function check_data_point_option_group_name_availability(string $name, int $dmaId, int $id = 0) {
    $this->db->select('data_point_option_group.id');
    $this->db->where('data_point_option_group.dma_id', $dmaId);
    $this->db->where('data_point_option_group.name', $name);
    if($id > 0) {
      $this->db->where('data_point_option_group.id !=', $id);
    }

    $this->db->from('data_point_option_group');
    $query = $this->db->get();

    $existing = [];

    foreach($query->result_array() as $data) {
      array_push($existing, $data);
    }

    if(count($existing) > 0) {
      return false;
    }

    return true;
  }

  function get_option_group_options($optionGroupId = "") {
    if($optionGroupId == "") {
      throw new \Exception('No Option Group ID Supplied');
    }

    $select = "data_point_option.*, (SELECT COUNT(*) FROM dataPointCustomValues dpv WHERE dpv.option_value_id = data_point_option.id AND dpv.is_deleted = 0) as numUses";
    $this->db->select($select);
    if($optionGroupId > 0) {
      $this->db->where('data_point_option.option_group_id', $optionGroupId);
    } else {
      return [];
    }

    $this->db->from('data_point_option');
    $this->db->order_by('data_point_option.display_order ASC');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;
  }

  function get_option_group($optionGroupId = "") {
    if($optionGroupId == "") {
      throw new \Exception('No Option Group ID Supplied');
    }

    $select = 'data_point_option_group.*';
    $this->db->select($select);
    $this->db->where('data_point_option_group.id', $optionGroupId);
    $this->db->from('data_point_option_group');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $data['options'] = $this->get_option_group_options($data['id']);
      $data['numUses'] = 0;
      foreach($data['options'] as $optionData) {
        $data['numUses'] += $optionData['numUses'];
      }
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    } else {
      return [];
    }
  }

  function get_option_groups($request) {
    $dmaId = $request['dmaId'] ?? null;
    $includeArchived = $request['includeArchived'] ?? false;
    $parentDmaAccess = $this->getParentDpAccess($dmaId);

    $select = 'data_point_option_group.*';

    $this->db->select($select);

    if(isset($parentDmaAccess) && is_numeric($parentDmaAccess) && $parentDmaAccess > 0) {
      $this->db->where_in('data_point_option_group.dma_id', [$dmaId, $parentDmaAccess]);
    } else {
      $this->db->where('data_point_option_group.dma_id', $dmaId);
    }

    if(!$includeArchived) {
      $this->db->where('data_point_option_group.is_archived', 0);
    }

    $this->db->from('data_point_option_group');
    $this->db->order_by('data_point_option_group.name ASC');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $data['options'] = $this->get_option_group_options($data['id']);
      $data['numUses'] = 0;
      foreach($data['options'] as $optionData) {
        $data['numUses'] += $optionData['numUses'];
      }
      $return[] = $data;
    }

    return $return;
  }

  function update_option_group($request) {
    $id = $request['id'];
    $name = $request['name'];
    $now = date('Y-m-d H:i:s');

    $data = [
        'name'       => $name,
        'updated_at' => $now,
    ];

    $this->db->where('id', $id);
    $this->db->update('data_point_option_group', $data);
  }

  function insert_option_group($request) {
    $dmaId = $request['dma_id'];
    $name = $request['name'];
    $now = date('Y-m-d H:i:s');

    $data = [
        'dma_id'     => $dmaId,
        'name'       => $name,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    $this->db->insert('data_point_option_group', $data);

    return $this->db->insert_id();
  }

  function update_option_group_status(int $id, string $status) {

    if($status == 'unArchive' || $status == 'archive') {
      $data = [
          'is_archived' => ($status == 'archive') ? 1 : 0,
      ];

      $this->db->where('id', $id);
      $this->db->update('data_point_option_group', $data);
    }
    if($status == 'delete') {
      $options = $this->get_option_group_options($id);
      $optionIds = [];
      foreach($options as $option) {
        $optionIds[] = $option['id'];
      }

      $this->db->where_in('option_value_id', $optionIds);
      $this->db->delete('dataPointCustomValues');

      $this->db->where_in('id', $optionIds);
      $this->db->delete('data_point_option');

      $this->db->where('option_group_id', $id);
      $this->db->update('dataPoints', ['option_group_id' => null]);

      $this->db->where('id', $id);
      $this->db->delete('data_point_option_group');
    }
  }

  function softDeleteDataPointValues(string $type, int $id) {
    $allowedTypes = [
        'credit',
        'legal-entity',
        'utilization',
    ];
    if(!in_array($type, $allowedTypes)) {
      throw new \Exception('Trying to soft delete custom data point values for disallowed type: ' . $type);
    }

    $this->db->query('UPDATE dataPointCustomValues dpv LEFT JOIN dataPoints dp ON dp.dpId = dpv.cvDpId SET dpv.is_deleted = 1 WHERE dpv.cvObjectId = ' . $id . ' AND dp.dpObjectType = "' . $type . '"');

    return true;
  }

  function getParentDpAccess($dmaId) {
    $result = null;

    $this->db->select('dmaAccounts.accessParentDataConfig, dmaAccounts.parentDmaId');
    $this->db->where('dmaAccounts.dmaId', $dmaId);

    $this->db->from('dmaAccounts');

    //$this->db->join("xxx","xxx.xxx = xxx.xxx", 'left');

    $query = $this->db->get();

    $data = $query->result_array();
    if(isset($data[0]['parentDmaId']) && $data[0]['parentDmaId'] > 0 && stripos($data[0]['accessParentDataConfig'], 'customDataPoints') !== false) {
      $result = $data[0]['parentDmaId'];
    }

    return $result;
  }

  function getDataPointsGrouped($request) {
    $dpDmaIdCustom = isset($request['dpDmaIdCustom']) ? $request['dpDmaIdCustom'] : null;
    $dpObjectType = isset($request['dpObjectType']) ? $request['dpObjectType'] : null;
    $includeArchived = isset($request['includeArchived']) ? $request['includeArchived'] : null;
    $parentDmaAccess = $this->getParentDpAccess($dpDmaIdCustom);

    $select = 'dataPoints.*, ';

    $this->db->select($select);
    $this->db->where('dataPoints.parent_id', null);

    //dpDmaId
    if($dpDmaIdCustom != "") {
      if(isset($parentDmaAccess) && is_numeric($parentDmaAccess) && $parentDmaAccess > 0) {
        $this->db->where_in('dataPoints.dpDmaId', [$dpDmaIdCustom, $parentDmaAccess]);
      } else {
        $this->db->where('dataPoints.dpDmaId', $dpDmaIdCustom);
      }
    }

    if($dpObjectType != "") {
      $this->db->where('dataPoints.dpObjectType', $dpObjectType);
      $this->db->or_where('(SELECT COUNT(*) FROM dataPoints as dp2 WHERE dp2.parent_id = dataPoints.dpId AND dp2.dpObjectType = "' . $dpObjectType . '" AND (dp2.dpArchivedMarker IS NULL OR dp2.dpArchivedMarker != 1)) >', 0);
    }

    if($includeArchived == 1) {
      $this->db->where('dataPoints.dpArchivedMarker', 1);
    } else {
      if($includeArchived == 2) {
        //include archived and active
      } else {
        $this->db->where('dataPoints.dpArchivedMarker', null);
      }
    }

    $this->db->from('dataPoints');

    $this->db->order_by("dataPoints.dpNameFull ASC");

    //$this->db->join("xxx","xxx.xxx = xxx.xxx", 'left');

    $query = $this->db->get();

    $return = [];
    $return['dataPoints'] = [];
    $return['dataPointsKeysOnly'] = [];

    foreach($query->result_array() as $data) {

      $include = true;

      $data['numUses'] = 0;

      $data['isParentData'] = ($data['dpDmaId'] != "" && $data['dpDmaId'] != $this->cisession->userdata('dmaId')) ? true : false;

      if($data['is_parent'] == '1') {
        $childDps = $this->get_data_points(['parentCdpId' => $data['dpId'], 'dpDmaIdCustom' => $dpDmaIdCustom]);
        $data['childCdps'] = [];
        foreach($childDps['dataPoints'] as $childDp) {
          $data['childCdps'][$childDp['dpObjectType']] = $childDp;
          $data['numUses'] += $childDp['numUses'];
        }
      } else {
        //Get generic values
        $data['dataPointValues'] = $this->get_all_custom_values_for_data_point($data['dpId']);
        $data['numUses'] = count($data['dataPointValues']);
      }

      if($data['dpSection'] == "") {
        $data['dpSection'] = "credit_additional_details"; //If it is empty, then set to credit_additional_details
      }

      if($data['dpKey'] == "fieldLastDayPrincipalPhotography" || $data['dpKey'] == "fieldNumberSubProjects") {
        if($this->cisession->userdata('dmaCategory') == "entertainment") {
          //do nothing
        } else {
          $include = false;
        }
      }
      if($data['dpAdvisorsOnly'] == 1) {
        if($this->cisession->userdata('dmaType') == "advisor") {
          //IF ADVISOR PACK - do nothing
        } else {
          $include = false;
        }
      }

      $data['cvValue'] = null;
      $data['cvValueFormatted'] = null;

      $data = $this->getFormattedValueForDataPoint($data);

      /*
      if($data['dpId'] == '311') {
        echo '<pre>';
        var_dump($data);
        exit;
      }
      */

      if($include) {
        array_push($return['dataPoints'], $data);
        array_push($return['dataPointsKeysOnly'], $data['dpKey']);
      }

    }

    return $return;
  }

  function get_data_points($request) {

    $orderBy = isset($request['orderBySections']) ? "dataPoints.dpObjectType ASC, dataPoints.dpSection ASC, dataPoints.dpNameFull ASC" : "dataPoints.dpNameFull ASC";
    $dpDmaId = isset($request['dpDmaId']) ? $request['dpDmaId'] : null;
    $dpDmaIdCustom = isset($request['dpDmaIdCustom']) ? $request['dpDmaIdCustom'] : null;
    $dpObjectType = isset($request['dpObjectType']) ? $request['dpObjectType'] : null;
    $listingId = isset($request['listingId']) ? $request['listingId'] : null;
    $dpKey = isset($request['dpKey']) ? $request['dpKey'] : null;
    $dpValue = isset($request['dpValue']) ? $request['dpValue'] : null;
    $dpType = isset($request['dpType']) ? $request['dpType'] : null;
    $dpSection = isset($request['dpSection']) ? $request['dpSection'] : null;
    $getReportMyCredits = isset($request['getReportMyCredits']) ? $request['getReportMyCredits'] : null;
    $getReportSharedCredits = isset($request['getReportSharedCredits']) ? $request['getReportSharedCredits'] : null;
    $getReportPurchases = isset($request['getReportPurchases']) ? $request['getReportPurchases'] : null;
    $getReportTrades = isset($request['getReportTrades']) ? $request['getReportTrades'] : null;
    $getReportHarvests = isset($request['getReportHarvests']) ? $request['getReportHarvests'] : null;
    $getMyCreditsView = isset($request['getMyCreditsView']) ? $request['getMyCreditsView'] : null;
    $getSharedCreditsView = isset($request['getSharedCreditsView']) ? $request['getSharedCreditsView'] : null;
    $getReportCompliance = isset($request['getReportCompliance']) ? $request['getReportCompliance'] : null;
    $includeArchived = isset($request['includeArchived']) ? $request['includeArchived'] : null;
    $parentDmaAccess = $this->getParentDpAccess($dpDmaIdCustom > 0 ? $dpDmaIdCustom : $dpDmaId);
    $parentCdpId = isset($request['parentCdpId']) ? $request['parentCdpId'] : null;

    $select = 'dataPoints.*, parentDp.dpNameFull as parentDpNameFull, parentDp.dpNameShort as parentDpNameShort, parentDp.dpType as parentDpType, parentDp.option_group_id as parent_option_group_id, parentDp.dpIsRequired as parentDpIsRequired, parentDp.dpDescription as parentDpDescription, parentDp.dpId as parentDpId, parentDp.dpArchivedMarker as parentDpArchivedMarker';

    $this->db->select($select);
    $this->db->join('dataPoints as parentDp', 'parentDp.dpId = dataPoints.parent_id', 'left');
    //$this->db->where('(SELECT COUNT(*) from)', '0');

    //dpDmaId
    if($dpDmaIdCustom != "") {
      if(isset($parentDmaAccess) && is_numeric($parentDmaAccess) && $parentDmaAccess > 0) {
        $this->db->where_in('dataPoints.dpDmaId', [$dpDmaIdCustom, $parentDmaAccess]);
      } else {
        $this->db->where('dataPoints.dpDmaId', $dpDmaIdCustom);
      }
    } else {
      if($dpDmaId != "" && is_numeric($dpDmaId)) {
        if(isset($parentDmaAccess) && is_numeric($parentDmaAccess) && $parentDmaAccess > 0) {
          $this->db->where('(dataPoints.dpDmaId=' . $dpDmaId . ' OR dataPoints.dpDmaId=' . $parentDmaAccess . ' OR  dataPoints.dpDmaId IS NULL)');
        } else {
          $this->db->where('(dataPoints.dpDmaId=' . $dpDmaId . ' OR dataPoints.dpDmaId IS NULL)');
        }
      } else {
        $this->db->where('dataPoints.dpDmaId', null);
      }
    }
    if($dpValue != "") {
      $this->db->where('dataPoints.dpValue', $dpValue);
    }
    if($dpType != "") {
      $this->db->where('dataPoints.dpType', $dpType);
    }
    if($dpSection != "") {
      $this->db->where('dataPoints.dpSection', $dpSection);
    }

    if($dpObjectType != "") {
      $this->db->where('dataPoints.dpObjectType', $dpObjectType);
    }

    if($parentCdpId > 0) {
      $this->db->where('dataPoints.parent_id', $parentCdpId);
    }

    if($getReportMyCredits == 1) {
      $this->db->where('dataPoints.dpReportMyCredits >', 0);
      //$this->db->order_by("dataPoints.dpReportMyCredits ASC");
    }
    if($getReportTrades == 1) {
      $this->db->where('dataPoints.dpReportTrades >', 0);
      //$this->db->order_by("dataPoints.dpReportTrades ASC");
    }
    if($getMyCreditsView == 1) {
      $this->db->where('dataPoints.dpMyCreditsView >', 0);
      //$this->db->order_by("dataPoints.dpMyCreditsView ASC");
    }
    if($getReportCompliance == 1) {
      $this->db->where('dataPoints.dpReportCompliance >', 0);
      //$this->db->order_by("dataPoints.dpReportCompliance ASC");
    }

    if($includeArchived == 1) {
      $this->db->where('dataPoints.dpArchivedMarker', 1);
    } else {
      if($includeArchived == 2) {
        //include archived and active
      } else {
        $this->db->where('dataPoints.dpArchivedMarker', null);
        if(!isset($request['parentCdpId'])) {
          $this->db->where('parentDp.dpArchivedMarker', null);
        }
      }
    }

    $this->db->from('dataPoints');

    $this->db->order_by($orderBy);

    //$this->db->join("xxx","xxx.xxx = xxx.xxx", 'left');

    $query = $this->db->get();

    $return = [];
    $return['dataPoints'] = [];
    $return['dataPointsKeysOnly'] = [];

    foreach($query->result_array() as $data) {

      $include = true;

      $data['numUses'] = 0;

      $data['isParentData'] = ($data['dpDmaId'] != "" && $data['dpDmaId'] != $this->cisession->userdata('dmaId')) ? true : false;

      if($data['dpSection'] == "") {
        $data['dpSection'] = "credit_additional_details"; //If it is empty, then set to credit_additional_details
      }

      if($data['dpKey'] == "fieldLastDayPrincipalPhotography" || $data['dpKey'] == "fieldNumberSubProjects") {
        if($this->cisession->userdata('dmaCategory') == "entertainment") {
          //do nothing
        } else {
          $include = false;
        }
      }
      if($data['dpAdvisorsOnly'] == 1) {
        if($this->cisession->userdata('dmaType') == "advisor") {
          //IF ADVISOR PACK - do nothing
        } else {
          $include = false;
        }
      }

      $data['cvValue'] = null;
      $data['cvValueFormatted'] = null;
      if($listingId > 0) { //TO DO - make this a generic ID thingy ma bob?
        //Get value specific to this listing
        $cdpvRequest['dpId'] = $data['dpId'];
        $cdpvRequest['listingId'] = $listingId;
        $customDataPointValues = $this->get_custom_data_point_values($cdpvRequest);
        if(sizeof($customDataPointValues) > 0) {
          foreach($customDataPointValues as $k => $v) {
            $data['cvValue'] = $v;
          }
        } else {
          $data['cvValue'] = null;
        }

      } else {
        //Get generic values
        $data['dataPointValues'] = $this->get_all_custom_values_for_data_point($data['dpId']);
        $data['numUses'] += count($data['dataPointValues']);
      }

      if(isset($data['parentDpNameFull'])) {
        $data['dpNameFull'] = $data['parentDpNameFull'];
      }
      if(isset($data['parentDpNameShort'])) {
        $data['dpNameShort'] = $data['parentDpNameShort'];
      }
      if(isset($data['parentDpType'])) {
        $data['dpType'] = $data['parentDpType'];
      }
      if(isset($data['parent_option_group_id'])) {
        $data['option_group_id'] = $data['parent_option_group_id'];
      }
      if(isset($data['parentDpIsRequired'])) {
        $data['dpIsRequired'] = $data['parentDpIsRequired'];
      }
      if(isset($data['parentDpDescription'])) {
        $data['dpDescription'] = $data['parentDpDescription'];
      }
      if(isset($data['parentDpArchivedMarker']) && !isset($request['parentCdpId'])) {
        $data['dpArchivedMarker'] = $data['parentDpArchivedMarker'];
      }

      $data = $this->getFormattedValueForDataPoint($data);

      if($include) {
        array_push($return['dataPoints'], $data);
        array_push($return['dataPointsKeysOnly'], $data['dpKey']);
      }

    }

    return $return;

  }

  function getFormattedValueForDataPoint($data) {
    $data['dpTypeName'] = "";
    if($data['dpType'] == "date") {
      $data['dpTypeName'] = "Date";
      $data['cvValueFormatted'] = ($data['cvValue'] != "") ? date('m/d/Y', $data['cvValue']) : "";
    }
    if($data['dpType'] == "selectDropDown") {
      $data['dpTypeName'] = "Select Drop Down";
      $data['cvValueFormatted'] = $data['cvValue'];
      $data['cvValueId'] = $data['cvValue'];

      $data['dpOptions'] = $this->getCustomDataPointOptions($data['parentDpId'] > 0 ? $data['parentDpId'] : $data['dpId']);

      foreach($data['dpOptions'] as $dpOption) {
        if($dpOption['id'] == $data['cvValue']) {
          $data['cvValue'] = $dpOption['label'];
        }
        //$data['numUses'] += $dpOption['num_values'];
      }
    }
    if($data['dpType'] == "text") {
      $data['dpTypeName'] = "Text/Number";
      $data['cvValueFormatted'] = $data['cvValue'];
    }
    if($data['dpType'] == "note") {
      $data['dpTypeName'] = "Notes";
      $data['cvValueFormatted'] = $data['cvValue'];
    }
    if($data['dpType'] == "currencyNoDecimal") {
      $data['dpTypeName'] = "Currency (No Decimal)";
      $data['cvValueFormatted'] = ($data['cvValue'] != "") ? "$" . number_format($data['cvValue']) : "";
    }
    if($data['dpType'] == "currencyTwoDecimal") {
      $data['dpTypeName'] = "Currency (Two Decimal)";
      $data['cvValueFormatted'] = ($data['cvValue'] != "") ? "$" . number_format($data['cvValue'], 2) : "";
    }
    if($data['dpType'] == "currencyFourDecimal") {
      $data['dpTypeName'] = "Currency (Four Decimal)";
      $data['cvValueFormatted'] = ($data['cvValue'] != "") ? "$" . number_format($data['cvValue'], 4) : "";
    }
    if($data['dpType'] == 'numberFourDecimal') {
      $data['dpTypeName'] = "Number (Four Decimal)";
      $data['cvValueFormatted'] = ($data['cvValue'] != "") ? number_format($data['cvValue'], 4) : "";
    }

    return $data;
  }

  function buildUtilizationsByCreditData($records) {

    /* BUILD THE REPORT DATA INTO VARIOUS ARRAYS */
    $sData = []; //stores values ONLY in arrays (first row are column titles)
    $sDataRowStyles = []; //stores an array for each row with a style array against it

    $uOrg = [];
    $uOrgIdTracker = [];
    foreach($records as $u) {

      $thisKey = $u["State"] . $u["listingId"];

      if(!in_array($u["listingId"], $uOrgIdTracker)) {
        array_push($uOrgIdTracker, $u["listingId"]);
        $thisCreditArray = [];
        $thisCreditArray["listingId"] = $u["listingId"];
        $thisCreditArray["jurisdictionCode"] = $u["State"];
        $thisCreditArray["jurisdictionName"] = $u["jurisdictionName"];
        $thisCreditArray["programName"] = $u["ProgramName"];
        $thisCreditArray["projectName"] = $u["projectNameFull"];
        $thisCreditArray["taxYear"] = $u["taxYear"];
        $thisCreditArray["creditFaceValue"] = $u["creditFaceValue"];
        $thisCreditArray["creditRemaining"] = $u["amountUSDRemaining"];
        $thisCreditArray["creditExpirationDate"] = $u["creditExpirationDate"];
        $thisCreditArray["CarryForwardYears"] = $u["CarryForwardYears"];
        $thisCreditArray["creditIssueDate"] = ($u["IssueDate"] > 0) ? date('m/d/Y', $u['IssueDate']) : null;

        $thisCreditArray["utilizations"] = [];
        $uOrg[$thisKey] = $thisCreditArray;
      }

      $thisUtilArray = [];
      $thisUtilArray["tradeSize"] = $u["tradeSize"];
      $thisUtilArray["utName"] = $u["utName"];
      $thisUtilArray["tradeEstActStatus"] = $u["tradeEstActStatus"];
      $thisUtilArray["utilizationDate"] = ($u['tradeIsEstimated'] == 1) ? $u['estimatedUtilizationDate'] : $u['actualUtilizationDate'];
      array_push($uOrg[$thisKey]["utilizations"], $thisUtilArray);

    }

    usort($uOrg, function($a, $b) {
      return $a['projectName'] > $b['projectName'];
    });

    $sData[0] = ['Credit ID', 'Jurisdiction', 'Program', 'Vintage Year', 'Project Name', 'Utilized Date', 'Utilization Type', 'Act./Est.', 'Credit Face Value', 'Amt. Utilized', 'Credit Balance Remaining', 'Carryforward (Yrs)', 'Expiration Date'];
    $sDataRowStyles[0] = [];

    foreach($uOrg as $uo) {

      $thisChipAwayTracker = $uo["creditFaceValue"];
      $thisUtilizationTracker = 0;
      $thisCreditFaceValue = $uo["creditFaceValue"];

      if(sizeof($uo["utilizations"]) > 0) {
        $firstTradeSize = "$" . number_format($uo["utilizations"][0]["tradeSize"]);
        $thisChipAwayTracker = $thisChipAwayTracker - $uo["utilizations"][0]["tradeSize"];
        $thisUtilizationTracker += $uo["utilizations"][0]["tradeSize"];
        $firstCAT = "$" . number_format($thisChipAwayTracker);
        $firstTradeEstActStatus = $uo["utilizations"][0]['tradeEstActStatus'];
        $firstUtName = $uo["utilizations"][0]["utName"];
        $firstUtilizationDate = date("m/d/Y", $uo["utilizations"][0]["utilizationDate"]);
        //Remove first row
        array_slice($uo["utilizations"], 1);
      } else {
        $firstTradeSize = "";
        $firstCAT = "";
        $firstTradeEstActStatus = "";
        $firstUtName = "";
        $firstUtilizationDate = "";
      }

      $creditRow = [$uo["jurisdictionCode"] . $uo["listingId"], $uo["jurisdictionName"], $uo["programName"], $uo["taxYear"], $uo["projectName"], $firstUtilizationDate, $firstUtName, $firstTradeEstActStatus, "$" . number_format($uo["creditFaceValue"]), $firstTradeSize, $firstCAT, $uo['CarryForwardYears'], $uo['creditExpirationDate']];
      array_push($sData, $creditRow);

      $loopCounter = 0;
      foreach($uo["utilizations"] as $uu) {
        if($loopCounter > 0) {
          $thisChipAwayTracker = $thisChipAwayTracker - $uu["tradeSize"];
          $thisUtilizationTracker += $uu["tradeSize"];
          $utilizationRow = ["", "", "", "", "", date("m/d/Y", $uu["utilizationDate"]), $uu['utName'], $uu['tradeEstActStatus'], "$" . number_format($uo["creditFaceValue"]), "$" . number_format($uu["tradeSize"]), "$" . number_format($thisChipAwayTracker), "", "", ""];
          array_push($sData, $utilizationRow);
        }
        $sDataRowStyles[] = ["", "bold", "bold", "bold", "", "", "", "", "", "", "", "textAlignCenter", ""];
        $loopCounter++;
      }
      if(sizeof($uo["utilizations"]) > 0) {
        $utilizationSumRow = ["", "", "", "", "", "", "", "", "$" . number_format($thisCreditFaceValue), "$" . number_format($thisUtilizationTracker), "$" . number_format($thisChipAwayTracker), "", "", ""];
        array_push($sData, $utilizationSumRow);
        $sDataRowStyles[] = ["", "", "", "", "", "", "", "", "bold,borderTopMedium", "bold,borderTopMedium", "bold,borderTopMedium", "", ""];
      }
      $utilizationBlankRow = ["", "", "", "", "", "", "", "", "", "", "", "", "", ""];
      array_push($sData, $utilizationBlankRow);
      $sDataRowStyles[] = [];
    }

    $result = [];
    $result['sData'] = $sData;
    $result['sDataRowStyles'] = $sDataRowStyles;

    return $result;

  }

  function buildComplianceByCreditData($records) {

    /* BUILD THE REPORT DATA INTO VARIOUS ARRAYS */
    $sData = []; //stores values ONLY in arrays (first row are column titles)

    $cOrg = [];
    $cOrgIdTracker = [];
    foreach($records as $c) {

      $thisKey = $c["State"] . $c["listingId"];

      if(!in_array($c["listingId"], $cOrgIdTracker)) {
        array_push($cOrgIdTracker, $c["listingId"]);
        $thisCreditArray = [];
        $thisCreditArray["listingId"] = $c["listingId"];
        $thisCreditArray["jurisdictionCode"] = $c["State"];
        $thisCreditArray["jurisdictionName"] = $c["jurisdictionName"];
        $thisCreditArray["programName"] = $c["ProgramName"];
        $thisCreditArray["projectName"] = $c["projectNameFull"];
        $thisCreditArray["taxYear"] = $c["taxYear"];

        $thisCreditArray["compliance"] = [];
        $cOrg[$thisKey] = $thisCreditArray;
      }

      $thisCompArray = [];
      $thisCompArray["wiTempName"] = $c["wiTempName"];
      $thisCompArray["isValueCompliant"] = $c["isValueCompliant"];
      $thisCompArray["isValueCompliantAsText"] = $c["isValueCompliantAsText"];
      $thisCompArray["wiValueAsText"] = $c["wiValueAsText"];
      $thisCompArray["wiValue1ExpectedAsText"] = $c["wiValue1ExpectedAsText"];
      $thisCompArray["wiCompletedDate1Expected"] = $c["wiCompletedDate1Expected"];
      $thisCompArray["wiCompletedDate1ExpectedAsText"] = $c["wiCompletedDate1ExpectedAsText"];
      $thisCompArray["wiStatusAsText"] = $c["wiStatusAsText"];
      $thisCompArray["assignedToAsText"] = $c["assignedToAsText"];
      $thisCompArray["docsCount"] = $c["docsCount"];
      $thisCompArray["notesCount"] = $c["notesCount"];
      $thisCompArray["completedDateAsText"] = $c["completedDateAsText"];
      $thisCompArray["completedByAsText"] = $c["completedByAsText"];
      array_push($cOrg[$thisKey]["compliance"], $thisCompArray);

    }

    $sData[0] = ['Credit ID', 'Project Name', 'Jurisdiction', 'Program', 'Vintage Year', 'Compliance Item', 'Compliance Status', 'Compliance Required Value', 'Compliance Actual Value', 'Compliance Due Date', 'Compliance Assigned To', 'Compliance Docs (#)', 'Compliance Notes (#)', 'Compliance Completed Date', 'Compliance Completed By', 'Compliance Alert'];

    foreach($cOrg as $co) {

      usort($co["compliance"], function($a, $b) {
        return $a['wiCompletedDate1Expected'] > $b['wiCompletedDate1Expected'];
      });

      $firstComplianceData = $co["compliance"][0];
      $creditRow = [$co["jurisdictionCode"] . $co["listingId"], $co["projectName"], $co["jurisdictionName"], $co["programName"], $co["taxYear"], $firstComplianceData["wiTempName"], $firstComplianceData["wiStatusAsText"], $firstComplianceData["wiValue1ExpectedAsText"], $firstComplianceData["wiValueAsText"], $firstComplianceData["wiCompletedDate1ExpectedAsText"], $firstComplianceData["assignedToAsText"], $firstComplianceData["docsCount"], $firstComplianceData["notesCount"], $firstComplianceData["completedDateAsText"], $firstComplianceData["completedByAsText"], $firstComplianceData["isValueCompliantAsText"]];
      array_push($sData, $creditRow);

      $loopCounter = 0;
      foreach($co["compliance"] as $cc) {
        if($loopCounter > 0) {
          $complianceRow = ["", "", "", "", "", $cc["wiTempName"], $cc["wiStatusAsText"], $cc["wiValue1ExpectedAsText"], $cc["wiValueAsText"], $cc["wiCompletedDate1ExpectedAsText"], $cc["assignedToAsText"], $cc["docsCount"], $cc["notesCount"], $cc["completedDateAsText"], $cc["completedByAsText"], $cc["isValueCompliantAsText"]];
          array_push($sData, $complianceRow);
        }
        $loopCounter++;
      }
      $complianceBlankRow = ["", "", "", "", "", "", "", "", "", "", "", "", "", ""];
      array_push($sData, $complianceBlankRow);
    }

    $result = [];
    $result['sData'] = $sData;

    return $result;

  }

  function buildReportData($dataKey, $columns, $records, $order = "", $view = "", $dpDmaId, $extraData = []) {
    /*
    echo "<pre>";
    echo $dataKey."<br>";
    var_dump($columns)."<br>";
    var_dump($records)."<br>";
    throw new \Exception('General fail');

    */
    /* BUILD THE REPORT DATA INTO VARIOUS ARRAYS */
    $sData = []; //stores values ONLY in arrays (first row are column titles)
    $sDataKeyValue = []; //stores keys (dpKey) and values in arrays (first row are column titles)
    $sDataKeyFormats = []; //stores an array of keys and their formats
    $sDataHeaderRows = []; //stores an array of column titles
    $sDataHeaderKeyValue = []; //stores keys (dpKey) and column titles in an array
    $sDataKeys = [];  //stores keys (dpKey) only in array
    $sDataSummary['creditsCount'] = 0;
    $sDataSummary['totalCreditAmount'] = 0;
    $sDataSummary['totalEstimatedValue'] = 0;
    $sDataSummary['totalAmountRemaining'] = 0;
    $sDataSummary['totalQualifiedExpenditures'] = 0;
    $sDataSummary['totalProjectBudgetEst'] = 0;
    $rCount = 0;

    //Get system available data points
    $dpRequest[$dataKey] = 1;
    $dpRequest['dpDmaId'] = $dpDmaId;
    $dataPointsRaw = $this->get_data_points($dpRequest);
    $dataPoints = $dataPointsRaw['dataPoints'];
    $sDataKeys = $dataPointsRaw['dataPointsKeysOnly'];

    $keysToRemove = [];
    foreach($columns as $column) {

      $key = array_search($column, $sDataKeys); //Figure out which key # it is in the array
      if($key !== false) {
        //allow through
      } else {
        $keyToRemove = array_search($column, $columns); //Figure out which key # it is in the array
        array_push($keysToRemove, $keyToRemove);
      }

    }
    $keysToRemove = array_flip($keysToRemove); //reverse order so we can delete in reverse to not break key order as each is removed
    foreach($keysToRemove as $keyToRemove) {
      unset($columns[$keyToRemove]);
    }

    //Build first row -- the column titles
    $sData[0] = []; //Setup the first as it is the column loop
    foreach($columns as $column) {

      $key = array_search($column, $sDataKeys); //Figure out which key # it is in the array
      if($key !== false) {
        array_push($sData[$rCount], $dataPoints[$key]['dpNameFull']); //use that key to get the value (column name)
        $sDataHeaderKeyValue[$column] = $dataPoints[$key]['dpNameShort'];
      } else {
        $this->logger->error('This column was not found: ' . $column . ' in the report: ' . $dataKey);
      }

    }

    $orderArrayTracker = [];
    $creditIdTrackerForNonCredits = [];

    $rCount++; //Add one as we already added the first row to the arrays above (column titles)
    foreach($records as $c) {

      $sData[$rCount] = [];
      $sDataKeyValue[$rCount] = [];

      if($dataKey == "getMyCreditsView" || $dataKey == "getReportMyCredits") {
        //Summary data
        $sDataSummary['creditsCount']++;
        $sDataSummary['totalCreditAmount'] += $c['amountUSD'];
        $sDataSummary['totalEstimatedValue'] += $c['amountValueEstimateUSD'];
        $sDataSummary['totalAmountRemaining'] += $c['amountUSDRemaining'];
        $sDataSummary['totalQualifiedExpenditures'] += $c['qualifiedExpenditures'];
        $sDataSummary['totalProjectBudgetEst'] += $c['projectBudgetEst'];
      }

      $insertNewSection = false;

      //If view is section, throw a new row in with the title of that section

      ////////////////////////////
      ///// Section Settings /////
      ////////////////////////////

      /////	Jurisdiction
      if($order == "jurisdiction_a_z" || $order == "jurisdiction_z_a" || $order == '') {
        $orderName = "Jurisdiction";
        $orderTag = "jurisdiction";
        $arrayKey = $c['jurisdiction_id'];
        $objectKey = 'jurisdiction';
        $objectName = $c['jurisdictionName'];
      }
      /////	Project
      if($order == "project_a_z" || $order == "project_z_a") {
        $orderName = "Project";
        $orderTag = "project";
        $arrayKey = ($c['stateCertNum'] != "") ? $c['stateCertNum'] : "(No Project)";
        $objectKey = 'project';
        $objectName = $arrayKey;
      }
      /////	Tax Year
      if($order == "year_h_l" || $order == "year_l_h") {
        $orderName = "Tax Year";
        $orderTag = "taxyear";
        $arrayKey = $c['taxYear'];
        $objectKey = 'taxyear';
        $objectName = $c['taxYear'];
      }
      /////	Program Name
      if($order == "program_a_z" || $order == "program_z_a") {
        $orderName = "Incentive Program";
        $orderTag = "program";
        $arrayKey = $c['ProgramName'];
        $objectKey = 'program';
        $objectName = $c['ProgramName'];
      }
      /////	Type
      if($order == "type_a_z" || $order == "type_z_a") {
        $orderName = "Credit Type";
        $orderTag = "programtype";
        $arrayKey = $c['ProgramTypeName'];
        $objectKey = 'programtype';
        $objectName = $c['ProgramTypeName'];
      }
      /////	Credit Amount
      if($order == "orig_amt_l_h" || $order == "orig_amt_h_l") {
        $orderName = "Credit Amount";
        $orderTag = "creditamount";
        $arrayKey = $c['creditAmount'];
        $objectKey = 'creditamount';
        $objectName = $c['creditAmount'];
      }
      /////	Date Loaded
      if($order == "loaded_n_o" || $order == "loaded_o_n") {
        $orderName = "Date Loaded";
        $orderTag = "timestamp";
        $arrayKey = $c['timeStamp'];
        $objectKey = 'timestamp';
        $objectName = $c['timeStamp'];
      }

      /////	Date Utilized
      if($order == "trade_date_n_o" || $order == "trade_date_o_n") {
        $orderName = "Date Utilized";
        $orderTag = "timestamp";
        $arrayKey = $c['timeStamp'];
        $objectKey = 'timestamp';
        $objectName = $c['timeStamp'];
      }

      /////	Utilization Amount
      if($order == "trade_amt_h_l" || $order == "trade_amt_l_h") {
        $orderName = "Utilization Amount";
        $orderTag = "tradesize";
        $arrayKey = $c['tradeSize'];
        $objectKey = 'tradesize';
        $objectName = $c['tradeSize'];
      }

      /////	Utilization Amount
      if($order == "compliance_due_date_l_h" || $order == "compliance_due_date_h_l") {
        $orderName = "Compliance Due Date";
        $orderTag = "complianceduedate";
        $arrayKey = $c['wiCompletedDate1Expected'];
        $objectKey = 'complianceduedate';
        $objectName = $c['wiCompletedDate1Expected'];
      }

      /////	Customer / Acconut
      if($order == "seller_a_z" || $order == "seller_z_a" || $order == '') {
        $orderName = "Account";
        $orderTag = "account";
        $arrayKey = $c['title'];
        $objectKey = 'account';
        $objectName = $c['title'];
      }

      //Do two things:
      // 1. build summary data
      // 2. create order row separators
      if(in_array($arrayKey, $orderArrayTracker)) {
        //add to summary
        $sDataOrderSummary[$arrayKey]['count']++;
        //CREDIT PORTFOLIO
        if($dataKey == "getMyCreditsView" || $dataKey == "getReportMyCredits") {
          $sDataOrderSummary[$arrayKey]['totalCreditAmount'] += $c['creditAmount'];
          $sDataOrderSummary[$arrayKey]['totalEstimatedValue'] += $c['amountValueEstimateUSD'];
          $sDataOrderSummary[$arrayKey]['totalAmountRemaining'] += $c['amountUSDRemaining'];
          $sDataOrderSummary[$arrayKey]['totalQualifiedExpenditures'] += $c['qualifiedExpenditures'];
          $sDataOrderSummary[$arrayKey]['totalProjectBudgetEst'] += $c['projectBudgetEst'];
          $sDataOrderSummary[$arrayKey]['finalTaxYear'] = ($c['taxYear'] > $sDataOrderSummary[$arrayKey]['finalTaxYear']) ? $c['taxYear'] : $sDataOrderSummary[$arrayKey]['finalTaxYear'];
          $sDataOrderSummary[$arrayKey]['finalCreditExpirationDate'] = ($c['creditExpirationDate'] > $sDataOrderSummary[$arrayKey]['finalCreditExpirationDate']) ? $c['creditExpirationDate'] : $sDataOrderSummary[$arrayKey]['finalCreditExpirationDate'];
          $sDataOrderSummary[$arrayKey]['finalAuditEndDate'] = ($c['auditEndDate'] > $sDataOrderSummary[$arrayKey]['finalAuditEndDate']) ? $c['auditEndDate'] : $sDataOrderSummary[$arrayKey]['finalAuditEndDate'];
          $sDataOrderSummary[$arrayKey]['finalProjectEndDate'] = ($c['projectEndDate'] > $sDataOrderSummary[$arrayKey]['finalProjectEndDate']) ? $c['projectEndDate'] : $sDataOrderSummary[$arrayKey]['finalProjectEndDate'];
          $sDataOrderSummary[$arrayKey]['finalCertDate'] = ($c['est_final_cert_dt'] > $sDataOrderSummary[$arrayKey]['finalCertDate']) ? $c['est_final_cert_dt'] : $sDataOrderSummary[$arrayKey]['finalCertDate'];
          $sDataOrderSummary[$arrayKey]['finalIssueDate'] = ($c['IssueDate'] > $sDataOrderSummary[$arrayKey]['finalIssueDate']) ? $c['IssueDate'] : $sDataOrderSummary[$arrayKey]['finalIssueDate'];
          $sDataOrderSummary[$arrayKey]['finalLastDayPrincipalPhotography'] = ($c['lastDayPrincipalPhotography'] > $sDataOrderSummary[$arrayKey]['finalLastDayPrincipalPhotography']) ? $c['lastDayPrincipalPhotography'] : $sDataOrderSummary[$arrayKey]['finalLastDayPrincipalPhotography'];
          $sDataOrderSummary[$arrayKey]['finalFirstEstimatedUtilizationDate'] = ($c['firstEstimatedUtilizationDate'] > $sDataOrderSummary[$arrayKey]['finalFirstEstimatedUtilizationDate']) ? $c['firstEstimatedUtilizationDate'] : $sDataOrderSummary[$arrayKey]['finalFirstEstimatedUtilizationDate'];
          $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'] = ($c['finalEstimatedUtilizationDate'] > $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate']) ? $c['finalEstimatedUtilizationDate'] : $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'];
          $sDataOrderSummary[$arrayKey]['finalFirstActualUtilizationDate'] = ($c['firstActualUtilizationDate'] > $sDataOrderSummary[$arrayKey]['finalFirstActualUtilizationDate']) ? $c['firstActualUtilizationDate'] : $sDataOrderSummary[$arrayKey]['finalFirstActualUtilizationDate'];
          $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'] = ($c['finalActualUtilizationDate'] > $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate']) ? $c['finalActualUtilizationDate'] : $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'];
        }
        //UTILIZATION
        if($dataKey == "getReportTrades") {
          $sDataOrderSummary[$arrayKey]['totalTradeSize'] += $c['tradeSize'];
          $sDataOrderSummary[$arrayKey]['totalPrice'] += $c['tradePrice'];
          $sDataOrderSummary[$arrayKey]['totalTradePriceTotal'] += $c['tradePriceTotal'];
          if($c['tradeIsEstimated'] == 1) {
            $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'] = ($c['estimatedUtilizationDate'] > $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate']) ? $c['estimatedUtilizationDate'] : $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'];
          } else {
            $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'] = ($c['actualUtilizationDate'] > $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate']) ? $c['actualUtilizationDate'] : $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'];
          }
          if(in_array($c['listingId'], $creditIdTrackerForNonCredits)) {
          } else {
            $sDataOrderSummary[$arrayKey]['creditCount']++;
            $sDataOrderSummary[$arrayKey]['creditFaceValueTotal'] += $c['creditFaceValue'];
            array_push($creditIdTrackerForNonCredits, $c['listingId']);
          }
        }
        //COMPLIANCE
        if($dataKey == "getReportCompliance") {
          $sDataOrderSummary[$arrayKey]['complianceAlerts'] += (!$c['isValueCompliant']) ? 1 : 0;
          if(in_array($c['listingId'], $creditIdTrackerForNonCredits)) {
          } else {
            $sDataOrderSummary[$arrayKey]['creditCount']++;
            $sDataOrderSummary[$arrayKey]['creditFaceValueTotal'] += $c['creditAmount'];
            array_push($creditIdTrackerForNonCredits, $c['listingId']);
          }
        }
      } else {
        array_push($orderArrayTracker, $arrayKey);
        if($view == "section") {
          $sData[$rCount] = [$objectName];
          $sDataKeyValue[$rCount] = [$objectKey => $objectName];
          $rCount++;
          $sData[$rCount] = []; //prepare next array
          $sDataKeyValue[$rCount] = []; //prepare next array
        }
        //create a summary
        $sDataOrderSummary[$arrayKey]['name'] = $objectName;
        $sDataOrderSummary[$arrayKey]['count'] = 1;
        //CREDIT PORTFOLIO
        if($dataKey == "getMyCreditsView" || $dataKey == "getReportMyCredits") {
          $sDataOrderSummary[$arrayKey]['totalCreditAmount'] = $c['amountUSD'];
          $sDataOrderSummary[$arrayKey]['totalEstimatedValue'] = $c['amountValueEstimateUSD'];
          $sDataOrderSummary[$arrayKey]['totalAmountRemaining'] = $c['amountUSDRemaining'];
          $sDataOrderSummary[$arrayKey]['totalQualifiedExpenditures'] = $c['qualifiedExpenditures'];
          $sDataOrderSummary[$arrayKey]['totalProjectBudgetEst'] = $c['projectBudgetEst'];
          $sDataOrderSummary[$arrayKey]['finalTaxYear'] = $c['taxYear'];
          $sDataOrderSummary[$arrayKey]['finalCreditExpirationDate'] = $c['creditExpirationDate'];
          $sDataOrderSummary[$arrayKey]['finalAuditEndDate'] = $c['auditEndDate'];
          $sDataOrderSummary[$arrayKey]['finalProjectEndDate'] = $c['projectEndDate'];
          $sDataOrderSummary[$arrayKey]['finalCertDate'] = $c['est_final_cert_dt'];
          $sDataOrderSummary[$arrayKey]['finalIssueDate'] = $c['IssueDate'];
          $sDataOrderSummary[$arrayKey]['finalLastDayPrincipalPhotography'] = $c['lastDayPrincipalPhotography'];
          $sDataOrderSummary[$arrayKey]['finalFirstEstimatedUtilizationDate'] = $c['firstEstimatedUtilizationDate'];
          $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'] = $c['finalEstimatedUtilizationDate'];
          $sDataOrderSummary[$arrayKey]['finalFirstActualUtilizationDate'] = $c['firstActualUtilizationDate'];
          $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'] = $c['finalActualUtilizationDate'];
        }
        //UTILIZATION
        if($dataKey == "getReportTrades") {
          $sDataOrderSummary[$arrayKey]['totalTradeSize'] = $c['tradeSizeUSD'];
          $sDataOrderSummary[$arrayKey]['totalPrice'] = $c['tradePrice'];
          $sDataOrderSummary[$arrayKey]['totalTradePriceTotal'] = $c['tradePriceTotal'];
          $sDataOrderSummary[$arrayKey]['avgPrice'] = 0;
          if($c['tradeIsEstimated'] == 1) {
            $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'] = $c['estimatedUtilizationDate'];
            $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'] = 0;
          } else {
            $sDataOrderSummary[$arrayKey]['finalFinalEstimatedUtilizationDate'] = 0;
            $sDataOrderSummary[$arrayKey]['finalFinalActualUtilizationDate'] = $c['actualUtilizationDate'];
          }
          if(in_array($c['listingId'], $creditIdTrackerForNonCredits)) {
          } else {
            $sDataOrderSummary[$arrayKey]['creditCount'] = (isset($sDataOrderSummary[$arrayKey]['creditCount'])) ? $sDataOrderSummary[$arrayKey]['creditCount']++ : 1;
            $sDataOrderSummary[$arrayKey]['creditFaceValueTotal'] = (isset($sDataOrderSummary[$arrayKey]['creditFaceValueTotal'])) ? $sDataOrderSummary[$arrayKey]['creditFaceValueTotal'] + $c['creditFaceValue'] : $c['creditFaceValue'];
            array_push($creditIdTrackerForNonCredits, $c['listingId']);
          }
        }
        //COMPLIANCE
        if($dataKey == "getReportCompliance") {
          $sDataOrderSummary[$arrayKey]['complianceAlerts'] = (!$c['isValueCompliant']) ? 1 : 0;
          if(in_array($c['listingId'], $creditIdTrackerForNonCredits)) {
          } else {
            $sDataOrderSummary[$arrayKey]['creditCount'] = (isset($sDataOrderSummary[$arrayKey]['creditCount'])) ? $sDataOrderSummary[$arrayKey]['creditCount']++ : 1;
            $sDataOrderSummary[$arrayKey]['creditFaceValueTotal'] = (isset($sDataOrderSummary[$arrayKey]['creditFaceValueTotal'])) ? $sDataOrderSummary[$arrayKey]['creditFaceValueTotal'] + $c['creditAmount'] : $c['creditAmount'];
            array_push($creditIdTrackerForNonCredits, $c['listingId']);
          }
        }
      }

      if($dataKey == "getReportTrades") {
        $i = 0;
        while($i < sizeof($sDataOrderSummary)) {
          $thisKey = array_keys($sDataOrderSummary)[$i];
          $sDataOrderSummary[$thisKey]['avgPrice'] = $sDataOrderSummary[$thisKey]['totalPrice'] / $sDataOrderSummary[$thisKey]['count'];
          $i++;
        }
      }

      foreach($columns as $column) {

        $key = array_search($column, $sDataKeys);
        $dpValue = $dataPoints[$key]['dpValue'];
        $dpTypeConfig = json_decode($dataPoints[$key]['dpTypeConfig'], true);
        $excludeCurrencySymbol = (isset($extraData['excludeCurrencySymbol']) && $extraData['excludeCurrencySymbol']) ? true : false;
        $dollarSign = ($excludeCurrencySymbol) ? "" : (isset($dpTypeConfig['isLocalCurrency']) && $dpTypeConfig['isLocalCurrency'] == 1 ? html_entity_decode($c['currencySymbolData']['html_code'], ENT_COMPAT, 'UTF-8') : "$");

        //FORMAT THE VALUE
        /* TEXT */
        if($dataPoints[$key]['dpType'] == "text" || $dataPoints[$key]['dpType'] == "selectDropDown") {
          $value = $c[$dpValue];
        }
        /* NUMBER */
        if($dataPoints[$key]['dpType'] == "number") {
          $value = ($c[$dpValue] > 0) ? number_format($c[$dpValue]) : 0;
        } /* DATE */
        else {
          if($dataPoints[$key]['dpType'] == "date") {
            $value = ($c[$dpValue] > 0) ? date('m/d/Y', $c[$dpValue]) : null;
          } /* TIMESTAMP */
          else {
            if($dataPoints[$key]['dpType'] == "timestamp") {
              $value = ($c[$dpValue] != "" && $c[$dpValue] != "0000-00-00 00:00:00") ? date('m/d/Y', strtotime($c[$dpValue])) : null;
            } /* CURRENCY NO DECIMALS */
            else {
              if($dataPoints[$key]['dpType'] == "currencyNoDecimal") {
                $value = ($c[$dpValue] > 0) ? $dollarSign . number_format($c[$dpValue]) : 0;
              } /* CURRENCY TWO DECIMALS */
              else {
                if($dataPoints[$key]['dpType'] == "currencyTwoDecimal") {
                  $value = ($c[$dpValue] > 0) ? $dollarSign . number_format($c[$dpValue], 2) : 0;
                } /* CURRENCY FOUR DECIMALS */
                else {
                  if($dataPoints[$key]['dpType'] == "currencyFourDecimal") {
                    $value = ($c[$dpValue] > 0) ? $dollarSign . number_format($c[$dpValue], 4) : 0;
                  } /* NUMBER FOUR DECIMALS */
                  else if($dataPoints[$key]['dpType'] == "numberFourDecimal") {
                    $value = ($c[$dpValue] > 0) ? number_format($c[$dpValue], 4) : 0;
                  } /* NUMBER FOUR DECIMALS */
                  else if($dataPoints[$key]['dpType'] == "numberFlexibleDecimal") {
                    $value = ($c[$dpValue] > 0) ? (float)$c[$dpValue] : 0;
                  } /* NUMBER FOUR DECIMALS */
                  else if($dataPoints[$key]['dpType'] == "numberTwoDecimal") {
                    $value = ($c[$dpValue] > 0) ? number_format($c[$dpValue], 2) : 0;
                  }

                }
              }
            }
          }
        }
        //Put formatted values into the arrays
        array_push($sData[$rCount], $value);
        $sDataKeyValue[$rCount][$column] = $value;

        if($rCount == 1) {
          $sDataKeyFormats[$column] = $dataPoints[$key]['dpType'];
        }

      }

      if($dataKey == "getSharedCreditsView") {
        //Put formatted values into the arrays
        $sDataHeaderKeyValue['fieldSharedAccount'] = 'Account';
        array_push($sData[$rCount], $c['title']);
        $sDataKeyValue[$rCount]['fieldSharedAccount'] = $c['title'];
      }

      //If this is my credits or shared credits, then we need to add an array of credit data to the first data point of each row
      if($dataKey == "getMyCreditsView" || $dataKey == "getSharedCreditsView") {
        array_unshift($sDataKeyValue[$rCount], $c);
      }

      $rCount++;

    }

    $sDataOrderSummaryRows = [];
    if($dataKey == "getMyCreditsView" || $dataKey == "getReportMyCredits") {
      array_push($sDataOrderSummaryRows, [$orderName, "# Credits", "Total Face Value Amt.", "Estimated Value", "Amount Remaining", 'Last Final Estimated Utilization Date', 'Last Actual Utilization Date']);
      //Commenting this version out as for Version 1.0 we are going with less fields
      //array_push($sDataOrderSummaryRows, array($orderName,"# Credits","Credit Amount","Estimated Value","Credit Remaining",'Last Final Estimated Utilization Date','Last Actual Utilization Date',"Last Vintage Tax Year","Last Expiration Date","Last Audit End Date","Last Project End Date","Last Certification Date","Last Issue Date","Qualified Expenditures","Project Budget Estimate"));
      if($this->cisession->userdata('dmaCategory') == "entertainment") {
        //Ignoring this too in v1.0
        //array_splice($sDataOrderSummaryRows[0], 10, 0, 'Last Pricinipal Photography End Date');
      }
    }
    if($dataKey == "getReportTrades") {
      array_push($sDataOrderSummaryRows, [$orderName, "# Credits", "Total Credit Face Value", "# Utilizations", "Total Utilization Amt.", "Average Value/Price", "Total Value", 'Last Estimated Utilization Date', 'Last Actual Utilization Date']);
    }
    if($dataKey == "getReportCompliance") {
      array_push($sDataOrderSummaryRows, [$orderName, "# Credits w/ Compliance Requirements", "Total Credit Face Value", "# of Compliance Requirements", "# Out of Compliance"]);
    }

    foreach($sDataOrderSummary as $os) {

      if($dataKey == "getMyCreditsView" || $dataKey == "getReportMyCredits") {
        $thisRow = [
            $os['name'],
            $os['count'],
            "$" . number_format($os['totalCreditAmount']),
            "$" . number_format($os['totalEstimatedValue']),
            "$" . number_format($os['totalAmountRemaining']),
            ($os['finalFinalEstimatedUtilizationDate'] != "") ? date('m/d/Y', $os['finalFinalEstimatedUtilizationDate']) : "",
            ($os['finalFinalActualUtilizationDate'] != "") ? date('m/d/Y', $os['finalFinalActualUtilizationDate']) : ""
            /* we're leaving all of these out in version 1.0
           $os['finalCreditExpirationDate'],
           ($os['finalAuditEndDate']>0) ? date("m/d/Y",$os['finalAuditEndDate']) : "",
           ($os['finalProjectEndDate']>0) ? date("m/d/Y",$os['finalProjectEndDate']) : "",
           ($os['finalCertDate']>0) ? date("m/d/Y",$os['finalCertDate']) : "",
           ($os['finalIssueDate']>0) ? date("m/d/Y",$os['finalIssueDate']) : "",
           ($os['totalQualifiedExpenditures']>0) ? "$".number_format($os['totalQualifiedExpenditures']) : "",
           ($os['totalProjectBudgetEst']>0) ? "$".number_format($os['totalProjectBudgetEst']) : ""
           */
        ];
        if($this->cisession->userdata('dmaCategory') == "entertainment") {
          $lastDayPrinc = ($os['finalLastDayPrincipalPhotography'] > 0) ? date("m/d/Y", $os['finalLastDayPrincipalPhotography']) : "";
          array_splice($thisRow, 10, 0, $lastDayPrinc);
        }
      }

      if($dataKey == "getReportTrades") {
        $thisRow = [
            $os['name'],
            $os['creditCount'],
            "$" . number_format($os['creditFaceValueTotal']),
            $os['count'],
            "$" . number_format($os['totalTradeSize']),
            "$" . number_format($os['avgPrice'], 4),
            "$" . number_format($os['totalTradePriceTotal']),
            ($os['finalFinalEstimatedUtilizationDate'] > 0) ? date("m/d/Y", $os['finalFinalEstimatedUtilizationDate']) : "",
            ($os['finalFinalActualUtilizationDate'] > 0) ? date("m/d/Y", $os['finalFinalActualUtilizationDate']) : "",
        ];
      }

      if($dataKey == "getReportCompliance") {
        $thisRow = [
            $os['name'],
            $os['creditCount'],
            "$" . number_format($os['creditFaceValueTotal']),
            $os['count'],
            $os['complianceAlerts'],
        ];
      }

      array_push($sDataOrderSummaryRows, $thisRow);
    }

    $result['sData'] = $sData;
    $result['sDataKeyValue'] = $sDataKeyValue;
    $result['sDataKeyFormats'] = $sDataKeyFormats;
    $result['sDataHeaderRows'] = $sData[0];
    $result['sDataHeaderKeyValue'] = $sDataHeaderKeyValue;
    $result['sDataKeys'] = $sDataKeys;
    $result['sDataSummary'] = $sDataSummary;
    $result['sDataOrderSummary'] = $sDataOrderSummary;
    $result['sDataOrderSummaryRows'] = $sDataOrderSummaryRows;

    return $result;

  }

  //USED FOR FOX INTEGRATION
  function get_CreditIds_with_InternalIds_Account($listedBy) {

    $this->db->select('PendingListings.listingId, PendingListings.internalId');
    $this->db->from('PendingListings');
    $this->db->join("ActiveListings", "ActiveListings.listingId = PendingListings.listingId", 'left');
    $this->db->where("PendingListings.listedBy", $listedBy);
    $this->db->where("ActiveListings.deleteMarker", null);
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $return[$data['internalId']] = $data['listingId'];
    }

    return $return;

  }

  //USED FOR FOX INTEGRATION
  function get_UtilizationIds_with_InternalIds_Account($creditId) {

    $this->db->select('Trades.tradeId, Trades.tInternalId');
    $this->db->from('Trades');
    $this->db->where("Trades.listingId", $creditId);
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $return[$data['tInternalId']] = $data['tradeId'];
    }

    return $return;

  }

  /* END - for backend functionality */

  //STILL USED TO GET BROKER INVENTORY??
  function get_current_market($type, $brokerDmaId = "") {
    $this->db->select('ActiveListings.timeStamp,ActiveListings.updatedTime,ActiveListings.listingId, ActiveListings.OIXIncentiveId, HighestBid, HighestBidSize,ActiveListings.allOrNone, ActiveListings.creditAmount, PendingListings.creditAmount as amountLocal, PendingListings.availableToList as amountLocalRemaining,  PendingListings.budgetExchangeRate, ActiveListings.incrementAmount,ActiveListings.offerSize, ActiveListings.originalOfferSize, ActiveListings.offerPrice, ActiveListings.certificationNum, PendingListings.IssueDate, ActiveListings.OfferGoodUntil, ActiveListings.CreditUsedForOffset,offsetName, ActiveListings.CreditUsedDate, ActiveListings.listedBy, ActiveListings.taxTypeIds, ActiveListings.finalUsableTaxYear, IncentivePrograms.ProgramName, IncentivePrograms.State, IPSectors.sector, IPCategories.category, IncentivePrograms.OffSettingTaxList, TaxYear.taxYear, PendingListings.estCreditPrice, PendingListings.cCarryForwardYears as CarryForwardYears, (TaxYear.taxYear+PendingListings.cCarryForwardYears) as maxYears, OffsetLocked, cert_status_type.cert_status_name, ProgramType.ProgramTypeName,States.name, portalName as sportalName, portalurl as sportalurl,accountType, Accounts.email, listingCustomerAssist, listingAnonymously');
    $this->db->from('ActiveListings');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IncentivePrograms.Sector = IPSectors.id", 'left');
    $this->db->join("IPCategories", "IncentivePrograms.Category = IPCategories.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State");
    $this->db->join("countries", "countries.id = States.countryId");
    $this->db->join("Accounts", "Accounts.userId = ActiveListings.listedBy", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $where1 = "(ActiveListings.traded != '1' or ActiveListings.traded is null)";
    $this->db->where($where1, null, false);
    $where2 = "(ActiveListings.deleteMarker != '1' or ActiveListings.deleteMarker is null)";
    $this->db->where($where2, null, false);
    $this->db->where('ActiveListings.listed', '1');
    if($brokerDmaId > 0) {
      $this->db->where('ActiveListings.brokerDmaId', $brokerDmaId);
    }

    $expwhere = "(TIMESTAMPDIFF(DAY,CURDATE(),FROM_UNIXTIME(ActiveListings.OfferGoodUntil))>0 OR ActiveListings.OfferGoodUntil is null or ActiveListings.OfferGoodUntil='0' )";
    $this->db->where($expwhere, null, false);

    if($type == 'FED') {
      $this->db->where('IncentivePrograms.State', 'FED');
    } else {
      if($type == 'ST') {
        $this->db->where_not_in('IncentivePrograms.State', 'FED');
      } else {
        if($type == 'SYND') {
          $this->db->where('IncentivePrograms.State', 'SYND');

        } else {

        }
      }
    }

    if($this->input->post('sortType') && $this->input->post('sortType') == "asc") {
      $this->db->order_by("ActiveListings.updatedTime asc");
    } else {
      $this->db->order_by("ActiveListings.updatedTime desc");
    }
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['estCreditPrice'] = ($data['estCreditPrice'] == "" || $data['estCreditPrice'] == 0) ? 1 : $data['estCreditPrice'];

      //Process Amount USD
      $amountDataRequest['amountLocal'] = $data['amountLocal'];
      $amountDataRequest['budgetExchangeRate'] = $data['budgetExchangeRate'];
      $amountDataRequest['amountLocalRemaining'] = $data['amountLocalRemaining'];
      $amountDataRequest['estCreditPrice'] = $data['estCreditPrice'];
      $creditAmountProcessedData = $this->get_credit_amount_process_data($amountDataRequest);
      $data = array_merge($data, $creditAmountProcessedData);

      $data['shortOffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['taxTypeNames'] = $this->get_tax_types($data['taxTypeIds']);
      $data['tradeStatusPending'] = $this->Trading->checkStatus($data['listingId']);
      $data['HighestBids'] = $this->get_highest_bid($data['listingId']);
      $data['HighestBidSize'] = isset($data['HighestBids']['bidSize']) ? $data['HighestBids']['bidSize'] : '0';
      $data['HighestBid'] = isset($data['HighestBids']['bidPrice']) ? $data['HighestBids']['bidPrice'] : '0';
      $data['bportalName'] = isset($data['HighestBids']['portalName']) ? $data['HighestBids']['portalName'] : '';
      $data['bportalurl'] = isset($data['HighestBids']['portalurl']) ? $data['HighestBids']['portalurl'] : '';
      $data['latestTwoSales'] = $this->Trading->get_last_two_trades($data['listingId']);
      $data['lastSale'] = isset($data['latestTwoSales'][0]['tradePrice']) ? $data['latestTwoSales'][0]['tradePrice'] : '0';
      $data['priorSale'] = isset($data['latestTwoSales'][1]['tradePrice']) ? $data['latestTwoSales'][1]['tradePrice'] : '0';

      array_push($return, $data);
    }

    return $return;

  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
