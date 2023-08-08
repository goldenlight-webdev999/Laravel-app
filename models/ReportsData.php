<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class ReportsData extends CI_Model {
  private $logger;

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->library(['session']);

    $this->logger = \OIX\Util\Logger::getInstance();
  }

  function get_report_templates($dmaId) {
    $this->db->select('report_templates.*, Accounts.firstName, Accounts.lastName');
    $this->db->where('report_templates.rtDmaId', $dmaId);
    $this->db->where('report_templates.rtDeleteMarker', null);
    $this->db->from('report_templates');
    $this->db->join("Accounts", "report_templates.rtUserId = Accounts.userId", 'left');
    $this->db->order_by("report_templates.rtName ASC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $data['rtArray'] = json_decode($data['rtArray'], true);
      $reportType = $data['rtArray']['reportType'];
      if($reportType == "reports_mycredits") {
        $data['reportType'] = "Credit Portfolio";
      } else {
        if($reportType == "reports_sharedcredits") {
          $data['reportType'] = "Portfolio";
        } else {
          if($reportType == "reports_utilizations") {
            $data['reportType'] = "Utilization";
          } else {
            if($reportType == "reports_purchases") {
              $data['reportType'] = "Purchases";
            } else {
              if($reportType == "reports_harvests") {
                $data['reportType'] = "Utilization";
              } else {
                if($reportType == "reports_compliance") {
                  $data['reportType'] = "Compliance";
                }
              }
            }
          }
        }
      }

      if(isset($data['rtArray']['reportView'])) {
        if($data['rtArray']['reportView'] == "group_by_credit") {
          $data['reportViewName'] = "Group by Tax Credit";
        } else {
          if($data['rtArray']['reportView'] == "summary") {
            $data['reportViewName'] = "Summary";
          } else {
            $data['reportViewName'] = "Details";
          }
        }
      } else {
        $data['reportViewName'] = "Details";
      }

      array_push($return, $data);
    }

    return $return;

  }

  function get_report_template_schedule($rtId) {
    $this->db->select('*');
    $this->db->from('report_templates');
  }

  function get_report_template($rtId) {

    $this->db->select('report_templates.*, CONCAT(Accounts.firstName, " ", Accounts.lastName) reportOwnerName, Accounts.email reportOwnerEmail, dmaMembers.dmaMemberId, mainAdminMember.dmaMemberId mainAdminDmaMemberId');
    $this->db->from('report_templates');
    $this->db->where('report_templates.rtId', $rtId);
    $this->db->where('report_templates.rtDeleteMarker', null);
    $this->db->join("dmaMembers", "dmaMembers.dmaMUserId = report_templates.rtUserId AND dmaMembers.dmaMDmaId = report_templates.rtDmaId", 'left');
    $this->db->join("Accounts", "Accounts.userId = dmaMembers.dmaMUserId", 'left');
    $this->db->join("dmaAccounts", "dmaAccounts.dmaId = report_templates.rtDmaId", 'left');
    $this->db->join("dmaMembers mainAdminMember", "mainAdminMember.dmaMUserId = dmaAccounts.primary_account_id AND mainAdminMember.dmaMDmaId = report_templates.rtDmaId", 'left');

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_dma_member_by_dmaid_and_userid($dmaId, $userId) {
    $this->db->select('userId, firstName, lastName, Accounts.title, email, dmaMembers.*, dmaAccounts.dmaId, dmaAccounts.title as dmaTitle, dmaGroups.*');
    $this->db->from('dmaMembers');
    $this->db->where('dmaMembers.dmaMDmaId', $dmaId);
    $this->db->where('dmaMembers.dmaMUserId', $userId);
    $this->db->join("Accounts", "Accounts.userId = dmaMembers.dmaMUserId", 'left');
    $this->db->join("dmaGroups", "dmaMembers.dmaMGroupId = dmaGroups.dmaGroupId", 'left');
    $this->db->join("dmaAccounts", "dmaMembers.dmaMDmaId = dmaAccounts.dmaId", 'left');

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_report_exports($request) {

    $reId = isset($request['reId']) ? $request['reId'] : null;

    $this->db->select('report_exports.*, Accounts.firstName, Accounts.lastName');
    $this->db->where_in('report_access.raDmaId', $this->cisession->userdata('dmaId'));
    $userAccessArray = [0, $this->cisession->userdata('dmaMemberId')]; //We add 0 because that's the "all access" flag
    $this->db->where_in('report_access.raDmaMemberId', $userAccessArray);
    if($reId > 0) {
      $this->db->where('report_exports.reId', $reId);
    }
    $this->db->where('report_exports.reDeleteMarker', null);
    $this->db->where('report_access.raDeleteMarker', null);
    $this->db->from('report_exports');
    $this->db->join("report_access", "report_access.raReportExportId = report_exports.reId", 'left');
    $this->db->join("Accounts", "report_exports.reCreatedByUserId = Accounts.userId", 'left');
    $this->db->order_by("report_exports.reDate DESC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data['reReportTemplateId'] > 0) {
        $data['reportTemplateData'] = $this->get_report_template($data['reReportTemplateId']);
        $data['rtArray'] = json_decode($data['reportTemplateData']['rtArray'], true);
        $reportType = $data['rtArray']['reportType'];
        $data['reportTypeCode'] = $reportType;
        if(isset($data['rtArray']['reportView'])) {
          $reportView = $data['rtArray']['reportView'];
        } else {
          $reportView = "details";
        }
      } else {
        $reportType = $data['reReportType'];
        $data['reportTypeCode'] = $reportType;
        $reportView = $data['reReportView'];
      }

      if($reportType == "reports_mycredits") {
        $data['reportType'] = "Credit Portfolio";
      } else {
        if($reportType == "reports_sharedcredits") {
          $data['reportType'] = "Portfolio";
        } else {
          if($reportType == "reports_utilizations") {
            $data['reportType'] = "Utilization";
          } else {
            if($reportType == "reports_purchases") {
              $data['reportType'] = "Purchases";
            } else {
              if($reportType == "reports_harvests") {
                $data['reportType'] = "Utilization";
              } else {
                if($reportType == "reports_compliance") {
                  $data['reportType'] = "Compliance";
                }
              }
            }
          }
        }
      }

      if($reportView == "group_by_credit") {
        $data['reportViewName'] = "Group by Tax Credit";
      } else {
        if($reportView == "summary") {
          $data['reportViewName'] = "Summary";
        } else {
          $data['reportViewName'] = "Details";
        }
      }

      $data['reDataJSON'] = json_decode($data['reDataJSON'], true);
      $data['reDataSummaryJSON'] = json_decode($data['reDataSummaryJSON'], true);
      $data['reRecordsSummaryJSON'] = json_decode($data['reRecordsSummaryJSON'], true);
      $data['reDataOrderSummaryRowsJSON'] = json_decode($data['reDataOrderSummaryRowsJSON'], true);
      $data['reCDataJSON'] = json_decode($data['reCDataJSON'], true);

      $raRequest['raReportExportId'] = $data['reId'];
      $data['access'] = $this->get_report_access($raRequest);

      if($data['access'][0]['raDmaMemberId'] == 0) {
        $data['allAdminsHaveAccess'] = true;
      } else {
        $data['allAdminsHaveAccess'] = false;
      }

      array_push($return, $data);
    }

    return $return;

  }

  function get_report_access($request) {

    $raReportExportId = isset($request['raReportExportId']) ? $request['raReportExportId'] : null;

    $this->db->select('report_access.*, Accounts.firstName, Accounts.lastName, Accounts.userId');
    if($raReportExportId > 0) {
      $this->db->where('report_access.raReportExportId', $raReportExportId);
    }
    $this->db->where('report_access.raDeleteMarker', null);
    $this->db->from('report_access');
    $this->db->join("dmaMembers", "report_access.raDmaMemberId = dmaMembers.dmaMemberId", 'left');
    $this->db->join("Accounts", "dmaMembers.dmaMUserId = Accounts.userId", 'left');
    $this->db->order_by("Accounts.lastName DESC");

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function insert_report_template() {

    $post = $this->input->post();
    $post['columns'] = explode(',', $this->input->post('columns'));
    $json = json_encode($post);
    $start_date_report = strtotime($this->input->post('start_date_report'));
    $end_date_report = strtotime($this->input->post('end_date_report'));

    $data = [
        'rtName'        => $this->input->post('rtName'),
        'rtArray'       => $json,
        'rtDmaId'       => $this->cisession->userdata('dmaId'),
        'rtUserId'      => $this->cisession->userdata('userId'),
        'rtStartDate'   => $start_date_report,
        'rtEndDate'     => $end_date_report,
        'rtCreatedDate' => time(),
    ];

    $this->db->insert('report_templates', $data);
    $rtId = $this->db->insert_id();

    if($this->input->post('reportRecurrence') != "none" && $this->input->post('reportRecurrence') != "") {
      $this->upsert_report_schedule($rtId);
    }

    return $rtId;
  }

  function update_report_export($request) {

    $data = [
        'reTitle' => $request['reTitle'],
    ];

    $this->db->where('reId', $request['reId']);
    $this->db->update('report_exports', $data);

  }

  function update_report_template($rtId) {

    $post = $this->input->post();
    $post['columns'] = explode(',', $this->input->post('columns'));
    $json = json_encode($post);
    $start_date_report = strtotime($this->input->post('start_date_report'));
    $end_date_report = strtotime($this->input->post('end_date_report'));

    $data = [
        'rtName'      => $this->input->post('rtName'),
        'rtArray'     => $json,
        'rtStartDate' => $start_date_report,
        'rtEndDate'   => $end_date_report,
    ];

    $this->db->where('rtId', $rtId);
    $this->db->update('report_templates', $data);

    $this->upsert_report_schedule($rtId);
  }

  function upsert_report_schedule($rtId) {
    $canEditSchedule = true;
    $reportTemplate = $this->get_report_template($rtId);

    if(isset($reportTemplate['dmaMemberId'])) {
      $canEditSchedule = ($this->cisession->userdata('dmaMemberId') === $reportTemplate['dmaMemberId']
          || $this->cisession->userdata('dmaMemberId') === $reportTemplate['mainAdminDmaMemberId']);
    }

    if(!$canEditSchedule) {
      return true;
    }

    $now = date('Y-m-d H:i:s');
    $schedule = [
        'report_template_id' => $rtId,
        'frequency'          => $this->input->post('reportRecurrence'),
        'updated_at'         => $now,
    ];
    $existingSchedule = $this->get_report_schedule($rtId, true);
    if(!isset($existingSchedule['id'])) {
      $schedule['created_at'] = $now;

      if($this->input->post('reportRecurrence') == "none") {
        return true;
      }
    } else {
      if($this->input->post('reportRecurrence') == "none" && $existingSchedule['is_deleted'] == false) {
        $this->db->where('id', $existingSchedule['id']);
        $this->db->update('report_schedule', ['is_deleted' => true]);

        return true;
      }
      if($this->input->post('reportRecurrence') != "none" && $existingSchedule['is_deleted'] == true) {
        $schedule['is_deleted'] = false;
      }
    }

    switch($schedule['frequency']) {
      case 'weekly':
        $schedule['frequency_specification'] = $this->input->post('recurrenceDayOfWeek');
        break;
      case 'monthly':
        $schedule['frequency_specification'] = $this->input->post('recurrenceDayOfMonth');
        break;
      case 'quarterly':
        $schedule['frequency_specification'] = $this->input->post('recurrenceDayOfQuarter');
        break;
      case 'yearly':
        $schedule['frequency_specification'] = $this->input->post('recurrenceDayOfYearMonth') . ':' . $this->input->post('recurrenceDayOfYearDay');
        break;
    }

    $scheduleId = $existingSchedule['id'];
    if(!isset($scheduleId)) {
      $this->db->insert('report_schedule', $schedule);
      $scheduleId = $this->db->insert_id();

      foreach($this->input->post('adminUserIdsAccess') as $dmaMemberId) {
        $this->db->insert('report_schedule_access', ['report_schedule_id' => $scheduleId, 'dma_member_id' => $dmaMemberId, 'created_at' => $now, 'updated_at' => $now]);
      }
    } else {
      if($existingSchedule['frequency'] != $schedule['frequency'] || $existingSchedule['frequency_specification'] != $schedule['frequency_specification']) {
        $this->db->where('id', $existingSchedule['id']);
        $this->db->update('report_schedule', $schedule);
      }

      $existingMembers = [];
      $existingAccess = $this->get_report_schedule_access($scheduleId);
      foreach($existingAccess as $existingAccessItem) {
        if(!in_array($existingAccessItem['dma_member_id'], $this->input->post('adminUserIdsAccess')) && $existingAccessItem['dma_member_id'] != $this->cisession->userdata('dmaMemberId')) {
          $this->db->where('dma_member_id', $existingAccessItem['dma_member_id']);
          $this->db->where('report_schedule_id', $scheduleId);
          $this->db->delete('report_schedule_access');
        } else {
          $existingMembers[] = $existingAccessItem['dma_member_id'];
        }
      }
      foreach($this->input->post('adminUserIdsAccess') as $dmaMemberId) {
        if(!in_array($dmaMemberId, $existingMembers)) {
          $this->db->insert('report_schedule_access', ['report_schedule_id' => $scheduleId, 'dma_member_id' => $dmaMemberId, 'created_at' => $now, 'updated_at' => $now]);
        }
      }
    }
  }

  function get_report_schedules_for_date(\DateTime $dateTime) {
    $dayOfWeek = strtolower($dateTime->format('D'));
    $dayOfMonth = $dateTime->format('t') == $dateTime->format('j') ? 'last' : $dateTime->format('j');
    $monthOfYear = strtolower($dateTime->format('M'));
    $dayOfQuarter = '';
    if($dayOfMonth == '1' && ($monthOfYear == 'oct' || $monthOfYear == 'jan' || $monthOfYear == 'apr' || $monthOfYear == 'jul')) {
      $dayOfQuarter = 'first';
    }
    if($dayOfMonth == 'last' && ($monthOfYear == 'dec' || $monthOfYear == 'mar' || $monthOfYear == 'jun' || $monthOfYear == 'sep')) {
      $dayOfQuarter = 'first';
    }

    $sql = "SELECT rs.*, rt.rtDmaId, rt.rtName, TIMESTAMPDIFF(HOUR, rs.last_run_at,?) as hours_since_last_run
              FROM report_schedule rs
              LEFT JOIN report_templates rt ON rt.rtId = rs.report_template_id
              WHERE rs.is_deleted = FALSE AND ((rs.`frequency` = 'daily') OR (rs.`frequency` = 'weekly' AND rs.`frequency_specification`=?)
                OR (rs.`frequency` = 'monthly' AND rs.`frequency_specification`=?) OR (rs.`frequency` = 'quarterly' AND rs.`frequency_specification`=?)
                OR (rs.`frequency` = 'yearly' AND rs.`frequency_specification`=?))
              HAVING (hours_since_last_run IS NULL OR hours_since_last_run >= 16)";
    $query = $this->db->query($sql, [$dateTime->format('Y-m-d H:i:s'), $dayOfWeek, $dayOfMonth, $dayOfQuarter, $monthOfYear . ':' . $dayOfMonth]);

    $this->logger->addInfo('Scheduled Reports Lookup, with data: ' . json_encode(['at' => $dateTime->format('Y-m-d H:i:s'), 'dayofweek' => $dayOfWeek, 'dayOfMonth' => $dayOfMonth, 'dayOfQuarter' => $dayOfQuarter, 'yearSpec' => $monthOfYear . ':' . $dayOfMonth]));

    $scheduledReports = [];
    foreach($query->result_array() as $row) {
      $scheduledReports[] = $row;
    }

    return $scheduledReports;
  }

  function get_report_schedule($rtId, $includeDeleted = false) {
    $this->db->select('*');
    $this->db->from('report_schedule');
    $this->db->where('report_schedule.report_template_id', $rtId);
    if(!$includeDeleted) {
      $this->db->where('report_schedule.is_deleted', false);
    }

    $query = $this->db->get();

    $data = $query->row_array();

    return $data;
  }

  function get_report_schedule_access($reportScheduleId) {
    $this->db->select('*');
    $this->db->from('report_schedule_access');
    $this->db->where('report_schedule_access.report_schedule_id', $reportScheduleId);

    $query = $this->db->get();

    $members = [];
    foreach($query->result_array() as $row) {
      $members[] = $row;
    }

    return $members;
  }

  function insert_scheduled_report_execution($schedule, $exportId) {
    $now = new \DateTime();
    $this->db->where('id', $schedule['id']);
    $this->db->update('report_schedule', ['updated_at' => $now->format('Y-m-d H:i:s'), 'last_run_at' => $now->format('Y-m-d H:i:s')]);

    $row = [
        'report_schedule_id'      => $schedule['id'],
        'report_export_id'        => $exportId,
        'frequency'               => $schedule['frequency'],
        'frequency_specification' => $schedule['frequency_specification'],
        'created_at'              => $now->format('Y-m-d H:i:s'),
    ];

    $this->db->insert('report_schedule_execution', $row);
  }

  function insert_report_export($request) {

    $rData['reTitle'] = $request['reTitle'];
    $rData['reReportTemplateId'] = $request['rtId'];
    $rData['reReportType'] = $request['reReportType'];
    $rData['reReportView'] = $request['reReportView'];
    $rData['reDate'] = date('Y-m-d H:i:s');
    $rData['reCreatedByUserId'] = $this->cisession->userdata('userId') ?? $request['reCreatedByUserId'];

    //$reDataJSON = explode(',', $request['reDataJSON']);
    //$rData['reDataJSON'] = json_encode($reDataJSON);
    $rData['reDataJSON'] = json_encode($request['reDataJSON']);

    //$reDataHeaderRowsJSON = explode(',', $request['reDataHeaderRowsJSON']);
    //$rData['reDataHeaderRowsJSON'] = json_encode($reDataHeaderRowsJSON);
    $rData['reDataHeaderRowsJSON'] = json_encode($request['reDataHeaderRowsJSON']);

    //	$reDataSummaryJSON = explode(',', $request['reDataSummaryJSON']);
    //	$rData['reDataSummaryJSON'] = json_encode($reDataSummaryJSON);
    $rData['reDataSummaryJSON'] = json_encode($request['reDataSummaryJSON']);

    $rData['reRecordsSummaryJSON'] = json_encode($request['reRecordsSummaryJSON']);
    $rData['reDataOrderSummaryRowsJSON'] = json_encode($request['reDataOrderSummaryRowsJSON']);
    $rData['reCDataJSON'] = json_encode($request['reCDataJSON']);

    $this->db->insert('report_exports', $rData);

    return $this->db->insert_id();

  }

  function add_access_to_report_export($request) {

    $data = [
        'raDmaId'            => $request['raDmaId'],
        'raDmaMemberId'      => $request['raDmaMemberId'],
        'raReportExportId'   => $request['raReportExportId'],
        'raReportTemplateId' => $request['raReportTemplateId'],
    ];

    $this->db->insert('report_access', $data);

    return $this->db->insert_id();

  }

  function delete_all_report_export_access_records($raReportExportId) {

    $data = [
        'raDeleteMarker' => 1,
    ];

    $this->db->where('raReportExportId', $raReportExportId);
    $this->db->update('report_access', $data);

  }

  function delete_report_export($reId) {

    $data = [
        'reDeleteMarker' => 1,
    ];

    $this->db->where('reId', $reId);
    $this->db->update('report_exports', $data);

  }

  function delete_report_template($rtId) {

    $data = [
        'rtDeleteMarker' => 1,
    ];

    $this->db->where('rtId', $rtId);
    $this->db->update('report_templates', $data);

  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
