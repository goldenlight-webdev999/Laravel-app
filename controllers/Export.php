<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

require_once(APPPATH . "libraries/Aspose/Java.inc");
require_once(APPPATH . "libraries/Aspose/lib/aspose.cells.php");

use aspose\cells;

class Export extends CI_Controller {
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
    $this->load->model('Trades');
    $this->load->model('ReportsData');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Taxpayersdata');
    $this->load->model('AuditTrail');
    $this->load->model('Workflow');
    $this->load->model('Comments');

    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');
    $this->load->library('phpexcel/PHPExcel');

    $license = new cells\License();
    $license->setLicense(__DIR__ . "/../../javabridge/lib/Aspose.Cells.lic");
  }

  function showErrorMesage() {
    echo "<p style='text-align:center;'><img src='https://oixstatic.s3.amazonaws.com/icons/icon-csv-circle.png'></p><p style='text-align:center;'>We apologize for the inconvenience, but the ability to export to XLSX and CSV is temporarily unavailable.</p><p style='text-align:center;'>The OIX team is addressing as quickly as possible. Service should return within a few hours.</p><p style='text-align:center;'> Contact us (support@theoix.com) if you have questions or would like to know exactly when this service returns. Please click 'back' on your browser to return to your OIX account to perform other tasks.</p>";
    throw new \Exception('General fail');
  }

  function calendar_events_export() {

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

    $data['fieldsType'] = "mycredits";
    $format = $this->input->post('fileFormat');

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    $data = array_merge($data, $sanitizedPostData);

    //Centralized function build filter query parameters
    $cData = $this->CreditListings->buildFilterSearchData($data);

    $data['calendarEvents'] = $this->CreditListings->get_credits_calendar_dates($cData);

    //Merge calendar events
    $data['calendarArray'] = [];
    $data['calendarArray'] = array_merge($data['calendarArray'], $data['calendarEvents']['allEvents']);

    usort($data['calendarArray'], function($a, $b) {
      return $a['dateUnix'] - $b['dateUnix'];
    });

    //
    if($cData['listingId'] > 0) {
      $creditData = $this->CreditListings->get_credit_private($cData['listingId']);
      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($cData['listingId']);
      $fileExt = "-" . $creditData['state'] . $creditData['listingId'] . " (" . $creditData['projectNameFull'] . ")";
    } else {
      $fileExt = "";
    }

    if($format == "excel") {
      $workbook = new cells\Workbook();
      $sheets = $workbook->getWorksheets();
      $datasheet = $sheets->get(0);
      $datasheet->setName("Events");
      $datacells = $datasheet->getCells();

      $tbl_cols = 5;
      $tbl_header_rows = 5;
      $tbl_data_rows = count($data['calendarArray']);

      // Set row height
      $datacells->setRowHeight(0, 0);
      $datacells->setRowHeight(1, 20);

      // Set column width
      $datacells->getColumns()->get(0)->setWidth(15);
      $datacells->getColumns()->get(1)->setWidth(85);
      $datacells->getColumns()->get(2)->setWidth(13);
      $datacells->getColumns()->get(3)->setWidth(40);
      $datacells->getColumns()->get(4)->setWidth(34);

      // Merge cells
      $datacells->merge(1, 0, 1, 5);
      $datacells->merge(3, 0, 1, 5);

      // Table header styles
      $header_style = new cells\Style();
      $header_style->getFont()->setName("Calibri");
      $header_style->getFont()->setSize(11);
      $header_style->getFont()->setColor(cells\Color::getWhite());
      $header_style->setTextWrapped(true);
      $header_style->setForegroundColor(cells\Color::fromArgb(56, 97, 144));
      $header_style->setPattern(cells\BackgroundType::SOLID);

      for($i = 0; $i < $tbl_header_rows; $i++) {
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get($i, $j)->setStyle($header_style);
        }
      }

      $header_style1 = $datacells->get('A2')->getStyle();
      $header_style1->getFont()->setSize(18);
      $header_style1->getFont()->setBold(true);
      $header_style1->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $datacells->get('A2')->setStyle($header_style1);

      $header_style2 = $datacells->get('A3')->getStyle();
      $header_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
      $datacells->get('A3')->setStyle($header_style2);
      $datacells->get('B3')->setStyle($header_style2);
      $datacells->get('C3')->setStyle($header_style2);
      $datacells->get('D3')->setStyle($header_style2);
      $datacells->get('E3')->setStyle($header_style2);

      $header_style3 = $datacells->get('E3')->getStyle();
      $header_style3->getFont()->setSize(14);
      $header_style3->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('E3')->setStyle($header_style3);

      $header_style4 = $datacells->get('A4')->getStyle();
      $header_style4->getFont()->setColor(cells\Color::getBlack());
      $header_style4->getFont()->setBold(true);
      $header_style4->getFont()->setUnderline(cells\FontUnderlineType::SINGLE);
      $header_style4->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $header_style4->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
      $header_style4->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::NONE, cells\Color::getBlack());
      $datacells->get('A4')->setStyle($header_style4);
      $datacells->get('A5')->setStyle($header_style4);
      $datacells->get('B5')->setStyle($header_style4);
      $datacells->get('C5')->setStyle($header_style4);
      $datacells->get('D5')->setStyle($header_style4);
      $datacells->get('E5')->setStyle($header_style4);

      // Table header data
      $datacells->get(1, 0)->putValue('Portfolio Events');
      $datacells->get(2, 4)->putValue('Report Date: ' . date("m/d/Y", time()));
      $datacells->get(4, 0)->putValue('Date');
      $datacells->get(4, 1)->putValue('Event');
      $datacells->get(4, 2)->putValue('Credit ID');
      $datacells->get(4, 3)->putValue('Project');
      $datacells->get(4, 4)->putValue('Account');

      // Table body styles
      $body_style1 = new cells\Style();
      $body_style1->getFont()->setName("Calibri");
      $body_style1->getFont()->setSize(11);
      $body_style1->setTextWrapped(true);

      $body_style2 = new cells\Style();
      $body_style2->getFont()->setName("Calibri");
      $body_style2->getFont()->setSize(11);
      $body_style2->setTextWrapped(true);
      $body_style2->setForegroundColor(cells\Color::fromArgb(220, 230, 240));
      $body_style2->setPattern(cells\BackgroundType::SOLID);
      $body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));
      $body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));

      // Table body data
      foreach($data['calendarArray'] as $i => $cal) {
        for($j = 0; $j < $tbl_cols; $j++) {
          $row_idx = $tbl_header_rows + $i;

          if($i % 2) {
            $datacells->get($row_idx, $j)->setStyle($body_style2);
          } else {
            $datacells->get($row_idx, $j)->setStyle($body_style1);
          }
        }

        $event_date = $cal['dateMonth'] . "/" . $cal['dateDay'] . "/" . $cal['dateYear'];
        $project_name = (isset($cal['projectName'])) ? ($cal['projectName'] != "" ? $cal['projectName'] : "-") : "-";
        $account = (isset($cal['dmaTitle']) && $cal['dmaTitle'] != "") ? $cal['dmaTitle'] : "-";

        $datacells->get($row_idx, 0)->putValue($event_date);
        $datacells->get($row_idx, 1)->putValue((string)$cal['eventTitle']);
        $datacells->get($row_idx, 2)->putValue((string)$cal['listingFull']);
        $datacells->get($row_idx, 3)->putValue((string)$project_name);
        $datacells->get($row_idx, 4)->putValue((string)$account);

        // Date format
        $date_style = $datacells->get($row_idx, 0)->getStyle();
        $date_style->setNumber(14);
        $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
        $datacells->get($row_idx, 0)->setStyle($date_style);
      }

      $titleSectionClean = preg_replace("/[^a-zA-Z0-9]+/", "", $fileExt);
      $fileName = 'OIX-Calendar' . $titleSectionClean . '.xlsx';
      $filePath = APPPATH . '../tmp_files/' . $fileName;
      $workbook->save($filePath);
      header('Pragma: public');
      header("Content-type:application/vnd.ms-excel; charset=utf-8");
      header('Content-Disposition: attachment; filename=' . $fileName);
      header('Content-Length: ' . filesize($filePath));

      readfile($filePath);

    } else {

      // output headers so that the file is downloaded rather than displayed
      $titleSectionClean = preg_replace("/[^a-zA-Z0-9]+/", "", $fileExt);
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=OIX-Calendar' . $titleSectionClean . '.csv');

      // create a file pointer connected to the output stream
      $output = fopen('php://output', 'w');

      fputcsv($output, ['Date', 'Event', 'Credit ID', 'Project', 'Account']);

      // loop over the rows, outputting them
      foreach($data['calendarArray'] as $c) {
        $projectName = (isset($c['projectName'])) ? ($c['projectName'] != "" ? $c['projectName'] : "-") : "-";
        $account = (isset($c['dmaTitle']) && $c['dmaTitle'] != "") ? $c['dmaTitle'] : "-";
        $row = [$c['dateMonth'] . "/" . $c['dateDay'] . "/" . $c['dateYear'], $c['eventTitle'], $c['listingFull'], $projectName, $account];
        fputcsv($output, $row);
      }

    }

  }

  function audit_trail_export($listingId, $format = "") {

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

      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($listingId);

    }

    //First, get the listing information
    $listing = $this->CreditListings->get_credit_private($listingId);

    //Get audit trail on the listing
    $audit_trail = $this->AuditTrail->get_audit_trail($listingId, 0);

    if($format == "excel") {
      $workbook = new cells\Workbook();
      $sheets = $workbook->getWorksheets();
      $datasheet = $sheets->get(0);
      $datasheet->setName("Audit Trail");
      $datacells = $datasheet->getCells();

      $tbl_cols = 8;
      $tbl_header_rows = 8;
      $tbl_data_rows = count($audit_trail);

      // Set row height
      $datacells->setRowHeight(0, 0);
      $datacells->setRowHeight(1, 20);

      // Set column width
      $datacells->getColumns()->get(0)->setWidth(50);
      $datacells->getColumns()->get(1)->setWidth(35);
      $datacells->getColumns()->get(2)->setWidth(30);
      $datacells->getColumns()->get(3)->setWidth(10);
      $datacells->getColumns()->get(4)->setWidth(18);
      $datacells->getColumns()->get(5)->setWidth(15);
      $datacells->getColumns()->get(6)->setWidth(15);
      $datacells->getColumns()->get(7)->setWidth(22);

      // Merge cells
      $datacells->merge(1, 0, 1, 8);
      $datacells->merge(2, 1, 1, 3);
      $datacells->merge(3, 1, 1, 3);
      $datacells->merge(4, 1, 1, 3);
      $datacells->merge(6, 0, 1, 8);

      for($i = $tbl_header_rows; $i < $tbl_header_rows + $tbl_data_rows; $i++) {
        $datacells->merge($i, 2, 1, 2);
      }

      // Table header styles
      $header_style = new cells\Style();
      $header_style->getFont()->setName("Calibri");
      $header_style->getFont()->setSize(11);
      $header_style->getFont()->setColor(cells\Color::getWhite());
      $header_style->setTextWrapped(true);
      $header_style->setForegroundColor(cells\Color::fromArgb(56, 97, 144));
      $header_style->setPattern(cells\BackgroundType::SOLID);

      for($i = 0; $i < $tbl_header_rows; $i++) {
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get($i, $j)->setStyle($header_style);
        }
      }

      $header_style1 = $datacells->get('A2')->getStyle();
      $header_style1->getFont()->setSize(18);
      $header_style1->getFont()->setBold(true);
      $header_style1->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $datacells->get('A2')->setStyle($header_style1);

      $header_style2 = $datacells->get('A3')->getStyle();
      $header_style2->getFont()->setSize(12);
      $datacells->get('A3')->setStyle($header_style2);
      $datacells->get('B3')->setStyle($header_style2);
      $datacells->get('A5')->setStyle($header_style2);
      $datacells->get('B5')->setStyle($header_style2);

      $header_style3 = $datacells->get('A4')->getStyle();
      $header_style3->getFont()->setSize(14);
      $header_style3->getFont()->setBold(true);
      $datacells->get('A4')->setStyle($header_style3);
      $datacells->get('B4')->setStyle($header_style3);
      $datacells->get('G4')->setStyle($header_style3);
      $datacells->get('H4')->setStyle($header_style3);

      $header_style4 = $datacells->get('G3')->getStyle();
      $header_style4->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('G3')->setStyle($header_style4);
      $datacells->get('H3')->setStyle($header_style4);

      $header_style5 = $datacells->get('A6')->getStyle();
      $header_style5->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
      $datacells->get('A6')->setStyle($header_style5);
      $datacells->get('B6')->setStyle($header_style5);
      $datacells->get('C6')->setStyle($header_style5);
      $datacells->get('D6')->setStyle($header_style5);
      $datacells->get('E6')->setStyle($header_style5);
      $datacells->get('F6')->setStyle($header_style5);
      $datacells->get('G6')->setStyle($header_style5);
      $datacells->get('H6')->setStyle($header_style5);

      $header_style6 = $datacells->get('A7')->getStyle();
      $header_style6->getFont()->setColor(cells\Color::getBlack());
      $header_style6->getFont()->setBold(true);
      $header_style6->getFont()->setUnderline(cells\FontUnderlineType::SINGLE);
      $header_style6->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $header_style6->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
      $header_style6->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::NONE, cells\Color::getBlack());
      $datacells->get('A7')->setStyle($header_style6);
      $datacells->get('A8')->setStyle($header_style6);
      $datacells->get('B8')->setStyle($header_style6);
      $datacells->get('C8')->setStyle($header_style6);
      $datacells->get('D8')->setStyle($header_style6);
      $datacells->get('E8')->setStyle($header_style6);
      $datacells->get('F8')->setStyle($header_style6);
      $datacells->get('G8')->setStyle($header_style6);
      $datacells->get('H8')->setStyle($header_style6);

      $date_style = $datacells->get('G4')->getStyle();
      $date_style->setNumber(14);
      $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('G4')->setStyle($date_style);

      $currency_style = $datacells->get('H4')->getStyle();
      $currency_style->setNumber(8);
      $datacells->get('H4')->setStyle($currency_style);

      // Table header data
      $datacells->get(1, 0)->putValue($listing["title"]);
      $datacells->get(2, 0)->putValue('Project Name');
      $datacells->get(2, 1)->putValue('Jurisdiction');
      $datacells->get(2, 6)->putValue('Report Date');
      $datacells->get(2, 7)->putValue('Est. Value');
      $datacells->get(3, 0)->putValue($listing["projectNameFull"]);
      $datacells->get(3, 1)->putValue($listing["name"]);
      $datacells->get(3, 6)->putValue(date("m/d/Y", time()));
      $datacells->get(3, 7)->putValue($listing["amountValueLocal"]);
      $datacells->get(4, 0)->putValue('(ID: ' . $listing["state"] . $listingId . ')');
      $datacells->get(4, 1)->putValue('(Audit Trail Report)');
      $datacells->get(7, 0)->putValue('Action');
      $datacells->get(7, 1)->putValue('Value');
      $datacells->get(7, 2)->putValue('Value Before');
      $datacells->get(7, 4)->putValue('User');
      $datacells->get(7, 5)->putValue('Docs (#)');
      $datacells->get(7, 6)->putValue('Notes (#)');
      $datacells->get(7, 7)->putValue('Date/Time');

      // Table body styles
      $body_style1 = new cells\Style();
      $body_style1->getFont()->setName("Calibri");
      $body_style1->getFont()->setSize(11);
      $body_style1->setTextWrapped(true);

      $body_style2 = new cells\Style();
      $body_style2->getFont()->setName("Calibri");
      $body_style2->getFont()->setSize(11);
      $body_style2->setTextWrapped(true);
      $body_style2->setForegroundColor(cells\Color::fromArgb(220, 230, 240));
      $body_style2->setPattern(cells\BackgroundType::SOLID);
      $body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));
      $body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));

      // Table body data
      foreach($audit_trail as $i => $at) {
        for($j = 0; $j < $tbl_cols; $j++) {
          $row_idx = $tbl_header_rows + $i;

          if($i % 2) {
            $datacells->get($row_idx, $j)->setStyle($body_style2);
          } else {
            $datacells->get($row_idx, $j)->setStyle($body_style1);
          }
        }

        $date = date('m/d/Y h:i', $at['audTimestamp']);
        $notesCount = ($at['commentsCount'] > 0) ? $at['commentsCount'] : "";
        $docsCount = (sizeof($at['documents']) > 0) ? sizeof($at['documents']) : "";;

        $datacells->get($row_idx, 0)->putValue((string)$at['audTValueText']);
        $datacells->get($row_idx, 1)->putValue((string)$at['audValueAfter']);
        $datacells->get($row_idx, 2)->putValue((string)$at['audValueBefore']);
        $datacells->get($row_idx, 4)->putValue($at['lastName'] . ', ' . $at['firstName']);
        $datacells->get($row_idx, 5)->putValue($docsCount);
        $datacells->get($row_idx, 6)->putValue($notesCount);
        $datacells->get($row_idx, 7)->putValue($date);

        // Currency format
        if(strpos($at['audTValueText'], 'Amount') !== false ||
            strpos($at['audTValueText'], 'Budget') !== false ||
            strpos($at['audTValueText'], 'Required Spend') !== false ||
            $at['audTValueText'] == 'Created') {
          $currency_style = $datacells->get($row_idx, 1)->getStyle();
          $currency_style->setNumber(8);
          $datacells->get($row_idx, 1)->setStyle($currency_style);
          $datacells->get($row_idx, 2)->setStyle($currency_style);
        } // Date format
        else {
          if(strpos($at['audTValueText'], 'Day') !== false ||
              strpos($at['audTValueText'], 'Date') !== false) {
            $date_style = $datacells->get($row_idx, 1)->getStyle();
            $date_style->setNumber(14);
            $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
            $datacells->get($row_idx, 1)->setStyle($date_style);
            $datacells->get($row_idx, 2)->setStyle($date_style);
          }
        }

        // Date time format
        $datetime_style = $datacells->get($row_idx, 7)->getStyle();
        $datetime_style->setNumber(22);
        $datetime_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
        $datacells->get($row_idx, 7)->setStyle($datetime_style);
      }

      $fileName = $listing['state'] . $listingId . '-Audit-Trail.xlsx';
      $filePath = APPPATH . '../tmp_files/' . $fileName;
      $workbook->save($filePath);
      header('Pragma: public');
      header("Content-type:application/vnd.ms-excel; charset=utf-8");
      header('Content-Disposition: attachment; filename=' . $fileName);
      header('Content-Length: ' . filesize($filePath));

      readfile($filePath);

    } else {

      // output headers so that the file is downloaded rather than displayed
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=' . $listing['state'] . $listingId . '-Audit-Trail.csv');

      // create a file pointer connected to the output stream
      $output = fopen('php://output', 'w');

      fputcsv($output, ['Date (ET)', 'Action', 'Value', 'Value Before', 'First Name', 'Last Name', 'Notes (#)', 'Docs (#)']);

      // loop over the rows, outputting them
      foreach($audit_trail as $at) {
        $date = date('m/d/Y h:i', $at['audTimestamp']);
        $notesCount = ($at['commentsCount'] > 0) ? $at['commentsCount'] : "";
        $docsCount = (sizeof($at['documents']) > 0) ? sizeof($at['documents']) : "";;
        $row = [$date, $at['audTValueText'], $at['audValueAfter'], $at['audValueBefore'], $at['firstName'], $at['lastName'], $notesCount, $docsCount];
        fputcsv($output, $row);
      }

    }

  }

  function workflow_export($listingId, $format = "") {

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

      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($listingId);

    }

    //First, get the listing information
    $listing = $this->CreditListings->get_credit_private($listingId);

    //Get workflow on the listing
    $workflow = $this->Workflow->get_workflow('', 'credit', $listingId, 'workflow');

    if($format == "excel") {

      $workbook = new cells\Workbook();
      $sheets = $workbook->getWorksheets();
      $datasheet = $sheets->get(0);
      $datasheet->setName("Workflow");
      $datacells = $datasheet->getCells();

      $tbl_cols = 8;
      $tbl_header_rows = 6;

      // Set row height
      $datacells->setRowHeight(0, 0);
      $datacells->setRowHeight(1, 20);

      // Set column width
      $datacells->getColumns()->get(0)->setWidth(60);
      $datacells->getColumns()->get(1)->setWidth(15);
      $datacells->getColumns()->get(2)->setWidth(22);
      $datacells->getColumns()->get(3)->setWidth(24);
      $datacells->getColumns()->get(4)->setWidth(15);
      $datacells->getColumns()->get(5)->setWidth(15);
      $datacells->getColumns()->get(6)->setWidth(22);
      $datacells->getColumns()->get(7)->setWidth(25);

      // Merge cells
      $datacells->merge(1, 0, 1, 8);
      $datacells->merge(2, 1, 1, 3);
      $datacells->merge(3, 1, 1, 3);
      $datacells->merge(4, 1, 1, 3);
      $datacells->merge(6, 0, 1, 8);

      // Table header styles
      $header_style = new cells\Style();
      $header_style->getFont()->setName("Calibri");
      $header_style->getFont()->setSize(11);
      $header_style->getFont()->setColor(cells\Color::getWhite());
      $header_style->setTextWrapped(true);
      $header_style->setForegroundColor(cells\Color::fromArgb(56, 97, 144));
      $header_style->setPattern(cells\BackgroundType::SOLID);

      for($i = 0; $i < $tbl_header_rows; $i++) {
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get($i, $j)->setStyle($header_style);
        }
      }

      $header_style1 = $datacells->get('A2')->getStyle();
      $header_style1->getFont()->setSize(18);
      $header_style1->getFont()->setBold(true);
      $header_style1->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $datacells->get('A2')->setStyle($header_style1);

      $header_style2 = $datacells->get('A3')->getStyle();
      $header_style2->getFont()->setSize(12);
      $datacells->get('A3')->setStyle($header_style2);
      $datacells->get('B3')->setStyle($header_style2);
      $datacells->get('A5')->setStyle($header_style2);
      $datacells->get('B5')->setStyle($header_style2);

      $header_style3 = $datacells->get('A4')->getStyle();
      $header_style3->getFont()->setSize(14);
      $header_style3->getFont()->setBold(true);
      $datacells->get('A4')->setStyle($header_style3);
      $datacells->get('B4')->setStyle($header_style3);
      $datacells->get('G4')->setStyle($header_style3);
      $datacells->get('H4')->setStyle($header_style3);

      $header_style4 = $datacells->get('G3')->getStyle();
      $header_style4->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('G3')->setStyle($header_style4);
      $datacells->get('H3')->setStyle($header_style4);

      $section_style1 = $datacells->get('A7')->getStyle();
      $section_style1->getFont()->setName("Calibri");
      $section_style1->getFont()->setSize(14);
      $section_style1->getFont()->setBold(true);
      $section_style1->getFont()->setColor(cells\Color::getBlack());
      $section_style1->setTextWrapped(true);
      $section_style1->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
      $section_style1->setPattern(cells\BackgroundType::SOLID);
      $section_style1->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());

      $section_style2 = $datacells->get('A8')->getStyle();
      $section_style2->getFont()->setName("Calibri");
      $section_style2->getFont()->setSize(11);
      $section_style2->getFont()->setColor(cells\Color::getBlack());
      $section_style2->getFont()->setUnderline(cells\FontUnderlineType::SINGLE);
      $section_style2->setTextWrapped(true);
      $section_style2->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $section_style2->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
      $section_style2->setPattern(cells\BackgroundType::SOLID);

      $date_style = $datacells->get('G4')->getStyle();
      $date_style->setNumber(14);
      $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('G4')->setStyle($date_style);

      $currency_style = $datacells->get('H4')->getStyle();
      $currency_style->setNumber(8);
      $datacells->get('H4')->setStyle($currency_style);

      // Table header data
      $datacells->get(1, 0)->putValue($listing["title"]);
      $datacells->get(2, 0)->putValue('Project Name');
      $datacells->get(2, 1)->putValue('Jurisdiction');
      $datacells->get(2, 6)->putValue('Report Date');
      $datacells->get(2, 7)->putValue('Est. Value');
      $datacells->get(3, 0)->putValue($listing["projectNameFull"]);
      $datacells->get(3, 1)->putValue($listing["name"]);
      $datacells->get(3, 6)->putValue(date("m/d/Y", time()));
      $datacells->get(3, 7)->putValue($listing["amountValueLocal"]);
      $datacells->get(4, 0)->putValue('(ID: ' . $listing["state"] . $listingId . ')');
      $datacells->get(4, 1)->putValue('(Workflow: ' . $workflow["wTemplateData"]["wTemplateName"] . ')');

      // Table body styles
      $body_style1 = new cells\Style();
      $body_style1->getFont()->setName("Calibri");
      $body_style1->getFont()->setSize(11);
      $body_style1->setTextWrapped(true);

      $body_style2 = new cells\Style();
      $body_style2->getFont()->setName("Calibri");
      $body_style2->getFont()->setSize(11);
      $body_style2->setTextWrapped(true);
      $body_style2->setForegroundColor(cells\Color::fromArgb(220, 230, 240));
      $body_style2->setPattern(cells\BackgroundType::SOLID);
      $body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));
      $body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));

      // Table body data
      $row_idx = $tbl_header_rows;
      foreach($workflow['wLists'] as $wl) {

        // Section header styling
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get($row_idx, $j)->setStyle($section_style1);
          $datacells->get($row_idx + 1, $j)->setStyle($section_style2);
        }

        // Section header data
        $datacells->get($row_idx, 0)->putValue($wl["wlName"]);
        $row_idx++;

        $datacells->get($row_idx, 0)->putValue('Item');
        $datacells->get($row_idx, 1)->putValue('Status');
        $datacells->get($row_idx, 2)->putValue('Value');
        $datacells->get($row_idx, 3)->putValue('Assigned To');
        $datacells->get($row_idx, 4)->putValue('Docs (#)');
        $datacells->get($row_idx, 5)->putValue('Notes (#)');
        $datacells->get($row_idx, 6)->putValue('Completed');
        $datacells->get($row_idx, 7)->putValue('Completed By');
        $row_idx++;

        // Section data
        foreach($wl['wlItems'] as $ii => $wlItems) {
          for($j = 0; $j < $tbl_cols; $j++) {
            if($ii % 2) {
              $datacells->get($row_idx, $j)->setStyle($body_style2);
            } else {
              $datacells->get($row_idx, $j)->setStyle($body_style1);
            }
          }

          if($wlItems['wiStatus'] == 2) {
            $status = "In Progress";
          } else {
            if($wlItems['wiStatus'] == 1) {
              $status = "Completed";
            } else {
              $status = "Pending";
            }
          }
          if($wlItems['wiTempType'] == "date" && $wlItems['wiValue'] > 0) {
            $wiValue = date("m/d/Y", $wlItems['wiValue']);

            // Date format
            $date_style = $datacells->get($row_idx, 2)->getStyle();
            $date_style->setNumber(14);
            $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
            $datacells->get($row_idx, 2)->setStyle($date_style);

          } else {
            if($wlItems['wiTempType'] == "currency") {
              $wiValue = $wlItems['wiValue'];

              // Currency format
              $currency_style = $datacells->get($row_idx, 2)->getStyle();
              $currency_style->setNumber(8);
              $currency_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
              $datacells->get($row_idx, 2)->setStyle($currency_style);

            } else {
              if(($wlItems['wiTempType'] == "date" || $wlItems['wiTempType'] == "currency" || $wlItems['wiTempType'] == "percentage") && ($wlItems['wiValue'] == 0 || $wlItems['wiValue'] == "")) {
                $wiValue = "";
              } else {
                $wiValue = $wlItems['wiValue'];

                // Right format
                $right_style = $datacells->get($row_idx, 3)->getStyle();
                $right_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
                $datacells->get($row_idx, 2)->setStyle($right_style);
              }
            }
          }

          $assignedTo = ($wlItems['wiAssignedTo'] > 0) ? $wlItems['wiAssignedToLastName'] . ". " . $wlItems['wiAssignedToFirstName'] : "";
          $docsCount = (sizeof($wlItems['documents']) > 0) ? sizeof($wlItems['documents']) : "";
          $notesCount = ($wlItems['commentsCount'] > 0) ? $wlItems['commentsCount'] : "";
          $completedDate = ($wlItems['wiCompletedDate'] > 0) ? date('m/d/Y', $wlItems['wiCompletedDate']) : "";
          $completedBy = ($wlItems['wiCompletedUser'] > 0) ? $wlItems['lastName'] . ", " . $wlItems['firstName'] : "";

          $datacells->get($row_idx, 0)->putValue((string)$wlItems['wiTempName']);
          $datacells->get($row_idx, 1)->putValue((string)$status);
          $datacells->get($row_idx, 2)->putValue($wiValue);
          $datacells->get($row_idx, 3)->putValue($assignedTo);
          $datacells->get($row_idx, 4)->putValue($docsCount);
          $datacells->get($row_idx, 5)->putValue($notesCount);
          $datacells->get($row_idx, 6)->putValue($completedDate);
          $datacells->get($row_idx, 7)->putValue($completedBy);

          // Date format
          $date_style = $datacells->get($row_idx, 6)->getStyle();
          $date_style->setNumber(14);
          $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
          $datacells->get($row_idx, 6)->setStyle($date_style);

          $row_idx++;
        }

        // Section separator row
        for($j = 0; $j < $tbl_cols; $j++) {
          if(count($wl['wlItems']) % 2) {
            $datacells->get($row_idx, $j)->setStyle($body_style2);
          } else {
            $datacells->get($row_idx, $j)->setStyle($body_style1);
          }
        }
        $row_idx++;
      }

      $fileNameSectionCleaned = preg_replace("/[^a-zA-Z0-9]+/", "_", $workflow['wTemplateData']['wTemplateName']);
      $fileName = $listing['state'] . $listingId . '-Workflow-' . $fileNameSectionCleaned . '.xlsx';
      $filePath = APPPATH . '../tmp_files/' . $fileName;
      $workbook->save($filePath);
      header('Pragma: public');
      header("Content-type:application/vnd.ms-excel; charset=utf-8");
      header('Content-Disposition: attachment; filename=' . $fileName);
      header('Content-Length: ' . filesize($filePath));

      readfile($filePath);

    } else {

      // output headers so that the file is downloaded rather than displayed
      $fileNameSectionCleaned = preg_replace("/[^a-zA-Z0-9]+/", "_", $workflow['wTemplateData']['wTemplateName']);
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=' . $listing['state'] . $listingId . '-Workflow-' . $fileNameSectionCleaned . '.csv');

      // create a file pointer connected to the output stream
      $output = fopen('php://output', 'w');

      // loop over the rows, outputting them

      foreach($workflow['wLists'] as $wl) {

        fputcsv($output, [$wl['wlName']]);
        fputcsv($output, ['Status', 'Item', 'Value', 'Assigned To', 'Docs (#)', 'Notes (#)', 'Completed', 'Completed By', 'All Notes (Detail)']);

        foreach($wl['wlItems'] as $wlItems) {

          if($wlItems['wiStatus'] == 2) {
            $status = "In Progress";
          } else {
            if($wlItems['wiStatus'] == 1) {
              $status = "Completed";
            } else {
              $status = "Pending";
            }
          }

          if($wlItems['wiTempType'] == "date" && $wlItems['wiValue'] > 0) {
            $wiValue = date("m/d/Y", $wlItems['wiValue']);
          } else {
            if(($wlItems['wiTempType'] == "date" || $wlItems['wiTempType'] == "currency" || $wlItems['wiTempType'] == "percentage") && ($wlItems['wiValue'] == 0 || $wlItems['wiValue'] == "")) {
              $wiValue = "";
            } else {
              $wiValue = $wlItems['wiValue'];
            }
          }

          $assignedTo = ($wlItems['wiAssignedTo'] > 0) ? $wlItems['wiAssignedToLastName'] . ". " . $wlItems['wiAssignedToFirstName'] : "";
          $docsCount = (sizeof($wlItems['documents']) > 0) ? sizeof($wlItems['documents']) : "";
          $notesCount = ($wlItems['commentsCount'] > 0) ? $wlItems['commentsCount'] : "";
          $completedDate = ($wlItems['wiCompletedDate'] > 0) ? date('m/d/Y', $wlItems['wiCompletedDate']) : "";
          $completedBy = ($wlItems['wiCompletedUser'] > 0) ? $wlItems['lastName'] . ", " . $wlItems['firstName'] : "";

          $comments = $this->Comments->get_comments('workflow_item', $wlItems['wvId']);
          $commentsBlob = "";
          $cbCount = 0;
          foreach($comments as $com) {
            if($cbCount > 0) {
              $commentsBlob .= '' . "\n\n" . '';
            }
            $commentsBlob .= '' . $com["firstName"] . ' ' . $com["lastName"] . ' (' . $com["title"] . ') - ' . $com["cmTimestamp"] . '' . "\n" . $com["cmText"];
            $cbCount++;
          }

          $row = [$status, $wlItems['wiTempName'], $wiValue, $assignedTo, $docsCount, $notesCount, $completedDate, $completedBy, $commentsBlob];
          fputcsv($output, $row);

        }

        //Add an empty row
        fputcsv($output, [' ']);

      }

    }

  }

  function compliance_export($listingId, $format = "") {

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

      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($listingId);

    }

    //First, get the listing information
    $listing = $this->CreditListings->get_credit_private($listingId);

    //Get workflow on the listing
    $workflow = $this->Workflow->get_workflow('', 'credit', $listingId, 'compliance');

    if($format == "excel") {

      $workbook = new cells\Workbook();
      $sheets = $workbook->getWorksheets();
      $datasheet = $sheets->get(0);
      $datasheet->setName("Compliance");
      $datacells = $datasheet->getCells();

      $tbl_cols = 9;
      $tbl_header_rows = 6;

      // Set row height
      $datacells->setRowHeight(0, 0);
      $datacells->setRowHeight(1, 20);

      // Set column width
      $datacells->getColumns()->get(0)->setWidth(60);
      $datacells->getColumns()->get(1)->setWidth(22);
      $datacells->getColumns()->get(2)->setWidth(22);
      $datacells->getColumns()->get(3)->setWidth(22);
      $datacells->getColumns()->get(4)->setWidth(24);
      $datacells->getColumns()->get(5)->setWidth(15);
      $datacells->getColumns()->get(6)->setWidth(15);
      $datacells->getColumns()->get(7)->setWidth(22);
      $datacells->getColumns()->get(8)->setWidth(25);

      // Merge cells
      $datacells->merge(1, 0, 1, 9);
      $datacells->merge(2, 1, 1, 3);
      $datacells->merge(3, 1, 1, 3);
      $datacells->merge(4, 1, 1, 3);

      // Table header styles
      $header_style = new cells\Style();
      $header_style->getFont()->setName("Calibri");
      $header_style->getFont()->setSize(11);
      $header_style->getFont()->setColor(cells\Color::getWhite());
      $header_style->setTextWrapped(true);
      $header_style->setForegroundColor(cells\Color::fromArgb(56, 97, 144));
      $header_style->setPattern(cells\BackgroundType::SOLID);

      for($i = 0; $i < $tbl_header_rows; $i++) {
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get($i, $j)->setStyle($header_style);
        }
      }

      $header_style1 = $datacells->get('A2')->getStyle();
      $header_style1->getFont()->setSize(18);
      $header_style1->getFont()->setBold(true);
      $header_style1->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $datacells->get('A2')->setStyle($header_style1);

      $header_style2 = $datacells->get('A3')->getStyle();
      $header_style2->getFont()->setSize(12);
      $datacells->get('A3')->setStyle($header_style2);
      $datacells->get('B3')->setStyle($header_style2);
      $datacells->get('A5')->setStyle($header_style2);
      $datacells->get('B5')->setStyle($header_style2);

      $header_style3 = $datacells->get('A4')->getStyle();
      $header_style3->getFont()->setSize(14);
      $header_style3->getFont()->setBold(true);
      $datacells->get('A4')->setStyle($header_style3);
      $datacells->get('B4')->setStyle($header_style3);
      $datacells->get('E4')->setStyle($header_style3);
      $datacells->get('F4')->setStyle($header_style3);
      $datacells->get('G4')->setStyle($header_style3);
      $datacells->get('H4')->setStyle($header_style3);
      $datacells->get('I4')->setStyle($header_style3);

      $header_style4 = $datacells->get('E3')->getStyle();
      $header_style4->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('E3')->setStyle($header_style4);
      $datacells->get('F3')->setStyle($header_style4);
      $datacells->get('G3')->setStyle($header_style4);
      $datacells->get('H3')->setStyle($header_style4);
      $datacells->get('I3')->setStyle($header_style4);

      $section_style1 = $datacells->get('A7')->getStyle();
      $section_style1->getFont()->setName("Calibri");
      $section_style1->getFont()->setSize(14);
      $section_style1->getFont()->setBold(true);
      $section_style1->getFont()->setColor(cells\Color::getBlack());
      $section_style1->setTextWrapped(true);
      $section_style1->setHorizontalAlignment(cells\TextAlignmentType::LEFT);
      $section_style1->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
      $section_style1->setPattern(cells\BackgroundType::SOLID);
      $section_style1->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());

      $section_style2 = $datacells->get('A8')->getStyle();
      $section_style2->getFont()->setName("Calibri");
      $section_style2->getFont()->setSize(11);
      $section_style2->getFont()->setColor(cells\Color::getBlack());
      $section_style2->getFont()->setUnderline(cells\FontUnderlineType::SINGLE);
      $section_style2->setTextWrapped(true);
      $section_style2->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
      $section_style2->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
      $section_style2->setPattern(cells\BackgroundType::SOLID);

      $date_style = $datacells->get('H4')->getStyle();
      $date_style->setNumber(14);
      $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
      $datacells->get('H4')->setStyle($date_style);

      $currency_style = $datacells->get('I4')->getStyle();
      $currency_style->setNumber(8);
      $datacells->get('I4')->setStyle($currency_style);

      $number_style = $datacells->get('E4')->getStyle();
      $number_style->setNumber(1);
      $datacells->get('E4')->setStyle($number_style);
      $datacells->get('F4')->setStyle($number_style);
      $datacells->get('G4')->setStyle($number_style);

      // Table header data
      $datacells->get(1, 0)->putValue($listing["title"]);
      $datacells->get(2, 0)->putValue('Project Name');
      $datacells->get(2, 1)->putValue('Jurisdiction');
      $datacells->get(2, 4)->putValue('First Year');
      $datacells->get(2, 5)->putValue('Term (yrs)');
      $datacells->get(2, 6)->putValue('Final Year');
      $datacells->get(2, 7)->putValue('Report Date');
      $datacells->get(2, 8)->putValue('Est. Value');
      $datacells->get(3, 0)->putValue($listing["projectNameFull"]);
      $datacells->get(3, 1)->putValue($listing["name"]);
      $datacells->get(3, 4)->putValue((int)$workflow["complianceYearStart"]);
      $datacells->get(3, 5)->putValue((int)$workflow["complianceYearCount"]);
      $datacells->get(3, 6)->putValue((int)$workflow["complianceYearEnd"]);
      $datacells->get(3, 7)->putValue(date("m/d/Y", time()));
      $datacells->get(3, 8)->putValue($listing["amountValueLocal"]);
      $datacells->get(4, 0)->putValue('(ID: ' . $listing["state"] . $listingId . ')');
      $datacells->get(4, 1)->putValue('(Workflow: ' . $workflow["wTemplateData"]["wTemplateName"] . ')');

      // Table body styles
      $body_style1 = new cells\Style();
      $body_style1->getFont()->setName("Calibri");
      $body_style1->getFont()->setSize(11);
      $body_style1->setTextWrapped(true);

      $body_style2 = new cells\Style();
      $body_style2->getFont()->setName("Calibri");
      $body_style2->getFont()->setSize(11);
      $body_style2->setTextWrapped(true);
      $body_style2->setForegroundColor(cells\Color::fromArgb(220, 230, 240));
      $body_style2->setPattern(cells\BackgroundType::SOLID);
      $body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));
      $body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));

      // Table body data
      $row_idx = $tbl_header_rows;
      foreach($workflow['complianceByYear'] as $k => $v) {

        // Section header styling
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get($row_idx, $j)->setStyle($section_style1);
          $datacells->get($row_idx + 1, $j)->setStyle($section_style2);
        }

        // Section header data
        $datacells->get($row_idx, 0)->putValue($k);
        $row_idx++;

        $datacells->get($row_idx, 0)->putValue('Item');
        $datacells->get($row_idx, 1)->putValue('Status (Compliance)');
        $datacells->get($row_idx, 2)->putValue('Required');
        $datacells->get($row_idx, 3)->putValue('Actual');
        $datacells->get($row_idx, 4)->putValue('Assigned To');
        $datacells->get($row_idx, 5)->putValue('Docs (#)');
        $datacells->get($row_idx, 6)->putValue('Notes (#)');
        $datacells->get($row_idx, 7)->putValue('Completed');
        $datacells->get($row_idx, 8)->putValue('Completed By');
        $row_idx++;

        // Section data
        foreach($v as $ii => $wlItems) {

          // Section data styling
          for($j = 0; $j < $tbl_cols; $j++) {
            if($ii % 2) {
              $datacells->get($row_idx, $j)->setStyle($body_style2);
            } else {
              $datacells->get($row_idx, $j)->setStyle($body_style1);
            }
          }

          if($wlItems['wiStatus'] == 2) {
            $status = "In Progress";
          } else {
            if($wlItems['wiStatus'] == 1) {
              $status = "Completed";
            } else {
              $status = "Pending";
            }
          }
          if(!$wlItems['isValueCompliant']) {
            $status = "COMPLIANCE ALERT";

            // Red text styling
            $alert_style = $datacells->get($row_idx, 1)->getStyle();
            $alert_style->getFont()->setColor(cells\Color::getRed());
            $datacells->get($row_idx, 1)->setStyle($alert_style);
            $datacells->get($row_idx, 2)->setStyle($alert_style);
            $datacells->get($row_idx, 3)->setStyle($alert_style);
          }
          if($wlItems['wiTempType'] == "date" && $wlItems['wiValue'] > 0) {
            $wiValue = date("m/d/Y", $wlItems['wiValue']);

            // Date format
            $date_style = $datacells->get($row_idx, 3)->getStyle();
            $date_style->setNumber(14);
            $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
            $datacells->get($row_idx, 3)->setStyle($date_style);

          } else {
            if($wlItems['wiTempType'] == "currency") {
              $wiValue = $wlItems['wiValue'];

              // Currency format
              $currency_style = $datacells->get($row_idx, 3)->getStyle();
              $currency_style->setNumber(8);
              $currency_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
              $datacells->get($row_idx, 2)->setStyle($currency_style);
              $datacells->get($row_idx, 3)->setStyle($currency_style);

            } else {
              if(($wlItems['wiTempType'] == "date" || $wlItems['wiTempType'] == "currency" || $wlItems['wiTempType'] == "percentage") && ($wlItems['wiValue'] == 0 || $wlItems['wiValue'] == "")) {
                $wiValue = "";
              } else { //If number or yes/no
                $wiValue = ($wlItems['wiTempType'] == 'currency') ? "$" . number_format($wlItems['wiValue']) : $wlItems['wiValue'];

                // Right format
                $right_style = $datacells->get($row_idx, 3)->getStyle();
                $right_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
                $datacells->get($row_idx, 2)->setStyle($right_style);
                $datacells->get($row_idx, 3)->setStyle($right_style);
              }
            }
          }

          $assignedTo = ($wlItems['wiAssignedTo'] > 0) ? $wlItems['wiAssignedToLastName'] . ". " . $wlItems['wiAssignedToFirstName'] : "";
          $docsCount = (sizeof($wlItems['documents']) > 0) ? sizeof($wlItems['documents']) : "";
          $notesCount = ($wlItems['commentsCount'] > 0) ? $wlItems['commentsCount'] : "";
          $completedDate = ($wlItems['wiCompletedDate'] > 0) ? date('m/d/Y', $wlItems['wiCompletedDate']) : "";
          $completedBy = ($wlItems['wiCompletedUser'] > 0) ? $wlItems['lastName'] . ", " . $wlItems['firstName'] : "";
          $wiValue1ExpectedAsText = ($wlItems['wiValue1ExpectedAsText'] != "") ? $wlItems['wiValue1ExpectedAsText'] : "";

          $datacells->get($row_idx, 0)->putValue((string)$wlItems['wiTempName']);
          $datacells->get($row_idx, 1)->putValue((string)$status);
          $datacells->get($row_idx, 2)->putValue($wiValue1ExpectedAsText);
          $datacells->get($row_idx, 3)->putValue($wiValue);
          $datacells->get($row_idx, 4)->putValue($assignedTo);
          $datacells->get($row_idx, 5)->putValue($docsCount);
          $datacells->get($row_idx, 6)->putValue($notesCount);
          $datacells->get($row_idx, 7)->putValue($completedDate);
          $datacells->get($row_idx, 8)->putValue($completedBy);

          // Date format
          $date_style = $datacells->get($row_idx, 7)->getStyle();
          $date_style->setNumber(14);
          $date_style->setHorizontalAlignment(cells\TextAlignmentType::RIGHT);
          $datacells->get($row_idx, 7)->setStyle($date_style);

          $row_idx++;
        }

        // Section separator row
        for($j = 0; $j < $tbl_cols; $j++) {
          if(count($v) % 2) {
            $datacells->get($row_idx, $j)->setStyle($body_style2);
          } else {
            $datacells->get($row_idx, $j)->setStyle($body_style1);
          }
        }
        $row_idx++;
      }

      $fileNameSectionCleaned = preg_replace("/[^a-zA-Z0-9]+/", "_", $workflow['wTemplateData']['wTemplateName']);
      $fileName = $listing['state'] . $listingId . '-Compliance-' . $fileNameSectionCleaned . '.xlsx';
      $filePath = APPPATH . '../tmp_files/' . $fileName;
      $workbook->save($filePath);
      header('Pragma: public');
      header("Content-type:application/vnd.ms-excel; charset=utf-8");
      header('Content-Disposition: attachment; filename=' . $fileName);
      header('Content-Length: ' . filesize($filePath));

      readfile($filePath);

    } else {

      // output headers so that the file is downloaded rather than displayed
      $fileNameSectionCleaned = preg_replace("/[^a-zA-Z0-9]+/", "_", $workflow['wTemplateData']['wTemplateName']);
      header('Content-Encoding: UTF-8');
      header('Content-Type: text/csv; charset=UTF-8');
      header('Content-Disposition: attachment; filename=' . $listing['state'] . $listingId . '-Compliance-' . $fileNameSectionCleaned . '.csv');

      // create a file pointer connected to the output stream
      $output = fopen('php://output', 'w');

      // loop over the rows, outputting them
      foreach($workflow['complianceByYear'] as $k => $v) {

        fputcsv($output, [$k]);
        fputcsv($output, ['Item', 'Status', 'Required', 'Actual', 'Assigned To', 'Docs (#)', 'Notes (#)', 'Completed', 'Completed By', 'All Notes (Detail)']);

        foreach($v as $wlItems) {

          if($wlItems['wiStatus'] == 2) {
            $status = "In Progress";
          } else {
            if($wlItems['wiStatus'] == 1) {
              $status = "Completed";
            } else {
              $status = "Pending";
            }
          }
          if(!$wlItems['isValueCompliant']) {
            $status = "COMPLIANCE ALERT";
            $complianceAlertStyle = 'color:red;';
          } else {
            $complianceAlertStyle = '';
          }

          if($wlItems['wiTempType'] == "date" && $wlItems['wiValue'] > 0) {
            $wiValue = date("m/d/Y", $wlItems['wiValue']);
          } else {
            if(($wlItems['wiTempType'] == "date" || $wlItems['wiTempType'] == "currency" || $wlItems['wiTempType'] == "percentage") && ($wlItems['wiValue'] == 0 || $wlItems['wiValue'] == "")) {
              $wiValue = "";
            } else {
              $wiValue = $wlItems['wiValue'];
            }
          }

          $assignedTo = ($wlItems['wiAssignedTo'] > 0) ? $wlItems['wiAssignedToLastName'] . ". " . $wlItems['wiAssignedToFirstName'] : "";
          $docsCount = (sizeof($wlItems['documents']) > 0) ? sizeof($wlItems['documents']) : "";
          $notesCount = ($wlItems['commentsCount'] > 0) ? $wlItems['commentsCount'] : "";
          $completedDate = ($wlItems['wiCompletedDate'] > 0) ? date('m/d/Y', $wlItems['wiCompletedDate']) : "";
          $completedBy = ($wlItems['wiCompletedUser'] > 0) ? $wlItems['lastName'] . ", " . $wlItems['firstName'] : "";
          $wiValue1ExpectedAsText = ($wlItems['wiValue1ExpectedAsText'] != "") ? $wlItems['wiValue1ExpectedAsText'] : "";

          $comments = $this->Comments->get_comments('workflow_item', $wlItems['wvId']);
          $commentsBlob = "";
          $cbCount = 0;
          foreach($comments as $com) {
            if($cbCount > 0) {
              $commentsBlob .= '' . "\n\n" . '';
            }
            $commentsBlob .= '' . $com["firstName"] . ' ' . $com["lastName"] . ' (' . $com["title"] . ') - ' . $com["cmTimestamp"] . '' . "\n" . $com["cmText"];
            $cbCount++;
          }

          $row = [$wlItems['wiTempName'], $status, $wiValue1ExpectedAsText, $wiValue, $wlItems['wiTempName'], $assignedTo, $docsCount, $notesCount, $completedDate, $completedBy, $commentsBlob];
          fputcsv($output, $row);

        }

        //Add an empty row
        fputcsv($output, [' ']);

      }

    }

  }

  function reports_custom_columns_export() {

    if(!$this->tank_auth->is_logged_in()) {
      redirect('/auth/login_form');
    } else {

      /*
      echo $this->input->post('cData');
      echo "<pre>";
      $cData = json_decode($this->input->post('cData'), TRUE);
      echo $cData['reportType'];
      var_dump($cData); throw new \Exception('General fail');
      */

      $cData = json_decode($this->input->post('cData'), true);
      $format = $this->input->post('fileFormat');
      $order = $this->input->post('order');
      $reportTemplateName = $this->input->post('reportTemplateName');
      $reportView = $this->input->post('reportView');
      $reportExportId = $this->input->post('reportExportId');

      //Is EXPORTED report - This means a saved report EXPORT not TEMPLATE
      $isSavedLibraryReport = ($reportExportId > 0) ? true : false;

      if($isSavedLibraryReport) {

        $rRequest['reId'] = $reportExportId;
        $data['reportExport'] = $this->ReportsData->get_report_exports($rRequest);
        $data['reportExport'] = $data['reportExport'][0];

        $csvTitle = $reportTemplateName;
        $tabName = "Report";

        $sData = $data['reportExport']['reDataJSON'];
        $sDataHeaderRows = $data['reportExport']['reDataJSON'][0];
        $sDataOrderSummaryRows = $data['reportExport']['reDataOrderSummaryRowsJSON'];

      }

      //If an ad-hoc report or SAVED TEMPLATE being run ad-hoc
      if(!$isSavedLibraryReport) {

        if($cData['reportType'] == "reports_mycredits" || $cData['reportType'] == "reports_sharedcredits") {

          if($cData['accountId'] != $this->cisession->userdata('primUserId')) {
            echo "You do not have permission to access this data.";
          } else {

            //Centralized function to clean/prepare filter data
            /*
            $data = array();
            $sanitizedPostData = $this->CreditListings->prepareFilterData();
            $data = array_merge($data, $sanitizedPostData);

            //Centralized function build filter query parameters
            $creditData = $this->CreditListings->buildFilterSearchData($data);
            */

            //Get Credit data
            $cData['dmaId'] = $this->cisession->userdata('dmaId');
            $cData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
            $creditsData = $this->CreditListings->get_credits($cData);
            $credits = $creditsData['credits'];

            //Check permission to credit
            //$firstCredit = $credits[0];
            //$this->memberpermissions->checkCreditAccess($firstCredit['listingId']);

            //If no results, then most likely it's either (i) an empty search or (ii) a user trying to hack in
            if(sizeof($credits) == 0) {
              echo "No data is available for this report";
              throw new \Exception('General fail');
            }

            $records = $credits;
            if($cData['reportType'] == "reports_mycredits") {
              $csvTitle = ($reportTemplateName != "") ? $reportTemplateName : "Credits";
              $dataKey = "getReportMyCredits";
              $tabName = "Credits";
            }

          }

        }

        if(($cData['reportType'] == "reports_utilizations" || $cData['reportType'] == "reports_purchases")) {

          if($cData['accountId'] != $this->cisession->userdata('primUserId')) {
            echo "You do not have permission to access this data.";
          } else {

            //Get Trades data
            $cData['dmaId'] = $this->cisession->userdata('dmaId');
            $cData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
            $tradesData = $this->CreditListings->get_utilizations($cData);

            $trades = $tradesData['trades'];

            //Check permission to trade
            //$firstTrade = $trades[0];
            //$this->memberpermissions->checkTradeAccess($firstTrade['tradeId']);

            //If no results, then most likely it's either (i) an empty search or (ii) a user trying to hack in
            if(sizeof($trades) == 0) {
              echo "No data is available for this report";
              throw new \Exception('General fail');
            }

          }

          $records = $trades;
          if($cData['reportType'] == "reports_utilizations") {
            $csvTitle = ($reportTemplateName != "") ? $reportTemplateName : "Utilizations";
            $dataKey = "getReportTrades";
            $tabName = "Utilizations";
          }
          if($cData['reportType'] == "reports_purchases") {
            $csvTitle = "Purchases";
            $dataKey = "getReportPurchases";
            $tabName = "Purchases";
          }

        }

        if($cData['reportType'] == "reports_compliance") {

          if($cData['accountId'] != $this->cisession->userdata('primUserId')) {
            echo "You do not have permission to access this data.";
          } else {

            //Get Credit data
            $cData['dmaId'] = $this->cisession->userdata('dmaId');
            $cData['dmaMemberId'] = $this->cisession->userdata('dmaMemberId');
            $cData['complianceIsConfigured'] = true;
            $creditsData = $this->CreditListings->get_credits($cData);
            $records = []; //we're going to stuff compliance data inside of here below

            //Check permission to credit
            //$firstCredit = $creditsData['credits'][0];
            //$this->memberpermissions->checkCreditAccess($firstCredit['listingId']);

            //If no results, then most likely it's either (i) an empty search or (ii) a user trying to hack in
            if(sizeof($creditsData['credits']) == 0) {
              echo "No data is available for this report";
              throw new \Exception('General fail');
            }

            //Now loop through and
            foreach($creditsData['credits'] as $credit) {
              $complianceData = $this->Workflow->get_workflow($credit['cComplianceId'], '', $credit['listingId']);
              foreach($complianceData['allwlItems'] as $cItem) {
                $thisRow = array_merge($credit, $cItem);
                array_push($records, $thisRow);
              }
            }

            $complianceOrder = $this->input->post('order');
            if($complianceOrder == 'compliance_due_date_l_h') {
              usort($records, function($a, $b) {
                return $a['wiCompletedDate1Expected'] - $b['wiCompletedDate1Expected'];
              });
            } else {
              if($complianceOrder == 'compliance_due_date_h_l') {
                usort($records, function($a, $b) {
                  return $b['wiCompletedDate1Expected'] - $a['wiCompletedDate1Expected'];
                });
              } else {
                if($complianceOrder == 'project_a_z') {
                  usort($records, function($a, $b) {
                    return $a['projectName'] > $b['projectName'];
                  });
                } else {
                  if($complianceOrder == 'project_z_a') {
                    usort($records, function($a, $b) {
                      return $a['projectName'] < $b['projectName'];
                    });
                  } else {
                    if($complianceOrder == 'jurisdiction_a_z') {
                      usort($records, function($a, $b) {
                        return $a['jurisdictionName'] > $b['jurisdictionName'];
                      });
                    } else {
                      if($complianceOrder == 'jurisdiction_z_a') {
                        usort($records, function($a, $b) {
                          return $a['jurisdictionName'] < $b['jurisdictionName'];
                        });
                      } else {
                        if($complianceOrder == 'program_a_z') {
                          usort($records, function($a, $b) {
                            return $a['ProgramName'] > $b['ProgramName'];
                          });
                        } else {
                          if($complianceOrder == 'program_z_a') {
                            usort($records, function($a, $b) {
                              return $a['ProgramName'] < $b['ProgramName'];
                            });
                          }
                        }
                      }
                    }
                  }
                }
              }
            }

            $csvTitle = ($reportTemplateName != "") ? $reportTemplateName : "Compliance";
            $dataKey = "getReportCompliance";
            $tabName = "Compliance";

          }

        }

        if($reportView == "group_by_credit") {

          if($cData['reportType'] == "reports_utilizations") {
            $reportData = $this->CreditListings->buildUtilizationsByCreditData($records);
          }
          if($cData['reportType'] == "reports_compliance") {
            $reportData = $this->CreditListings->buildComplianceByCreditData($records);
          }
          $sData = $reportData['sData'];
          $sDataHeaderRows = [];
          $sDataOrderSummaryRows = [];
          $sDataKeyFormats = [];
          $sDataRowStyles = $reportData['sDataRowStyles'];

        } else {

          //Now that we have data, send it to get built into the reports array
          $extraData['excludeCurrencySymbol'] = true;
          $reportData = $this->CreditListings->buildReportData($dataKey, $cData['columns'], $records, $order, '', $this->cisession->userdata('dmaId'), $extraData);
          $sData = $reportData['sData'];
          $sDataHeaderRows = $reportData['sDataHeaderRows'];
          $sDataOrderSummaryRows = $reportData['sDataOrderSummaryRows'];
          $sDataKeyFormats = $reportData['sDataKeyFormats'];

        }

      }

      if($reportView == "summary") {
        $columnCount = sizeof($sDataOrderSummaryRows[0]);
        $columnData = $sDataOrderSummaryRows;
      } else {
        $columnCount = sizeof($sData[0]);
        $columnData = $sData;
      }

      if($format == "excel") {

        $workbook = new cells\Workbook();
        $sheets = $workbook->getWorksheets();
        $datasheet = $sheets->get(0);
        $datasheet->setName($tabName);
        $datacells = $datasheet->getCells();

        $tbl_cols = $columnCount;
        $tbl_header_rows = 5;

        // Set row height
        $datacells->setRowHeight(0, 0);
        $datacells->setRowHeight(1, 20);

        // Merge cells
        $datacells->merge(1, 0, 1, $tbl_cols);
        $datacells->merge(3, 0, 1, $tbl_cols);

        // Table header styles
        $header_style = new cells\Style();
        $header_style->getFont()->setName("Calibri");
        $header_style->getFont()->setSize(11);
        $header_style->getFont()->setColor(cells\Color::getWhite());
        $header_style->setForegroundColor(cells\Color::fromArgb(56, 97, 144));
        $header_style->setPattern(cells\BackgroundType::SOLID);

        for($i = 0; $i < $tbl_header_rows; $i++) {
          for($j = 0; $j < $tbl_cols; $j++) {
            $datacells->get($i, $j)->setStyle($header_style);
          }
        }

        $header_style1 = $datacells->get('A2')->getStyle();
        $header_style1->getFont()->setSize(18);
        $header_style1->getFont()->setBold(true);
        $datacells->get('A2')->setStyle($header_style1);

        $header_style2 = $datacells->get('A3')->getStyle();
        $header_style2->getFont()->setSize(14);
        $header_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get(2, $j)->setStyle($header_style2);
        }

        $header_style3 = $datacells->get('A4')->getStyle();
        $header_style3->getFont()->setColor(cells\Color::getBlack());
        $header_style3->getFont()->setBold(true);
        $header_style3->getFont()->setUnderline(cells\FontUnderlineType::SINGLE);
        $header_style3->setHorizontalAlignment(cells\TextAlignmentType::CENTER);
        $header_style3->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
        $header_style3->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::NONE, cells\Color::getBlack());
        $datacells->get('A4')->setStyle($header_style3);
        for($j = 0; $j < $tbl_cols; $j++) {
          $datacells->get(4, $j)->setStyle($header_style3);
        }

        // Table header data
        $datacells->get(1, 0)->putValue($csvTitle);
        $datacells->get(2, 0)->putValue('Report Date: ' . date("m/d/Y", time()));

        for($j = 0; $j < $tbl_cols; $j++) {
          $col_name = ($reportView == "summary") ? $sDataOrderSummaryRows[0][$j] : $sData[0][$j];
          $datacells->get(4, $j)->putValue($col_name);

          // Adjust column width according to the field value
          $datacells->getColumns()->get($j)->setWidth(strlen($col_name));
        }

        // Table body styles
        $body_style1 = new cells\Style();
        $body_style1->getFont()->setName("Calibri");
        $body_style1->getFont()->setSize(11);
        $body_style1->setTextWrapped(true);

        $body_style2 = new cells\Style();
        $body_style2->getFont()->setName("Calibri");
        $body_style2->getFont()->setSize(11);
        $body_style2->setTextWrapped(true);
        $body_style2->setForegroundColor(cells\Color::fromArgb(220, 230, 240));
        $body_style2->setPattern(cells\BackgroundType::SOLID);
        $body_style2->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));
        $body_style2->setBorder(cells\BorderType::BOTTOM_BORDER, cells\CellBorderType::THIN, cells\Color::fromArgb(150, 180, 213));

        // Table body data
        foreach($columnData as $i => $row) {
          if($i == 0) {
            continue;   // skip first row
          }

          $row_idx = $tbl_header_rows + $i - 1;

          for($j = 0; $j < $tbl_cols; $j++) {
            if($i % 2) {
              $datacells->get($row_idx, $j)->setStyle($body_style1);
            } else {
              $datacells->get($row_idx, $j)->setStyle($body_style2);
            }

            //If this is a string with a zero at the beginning, then force a zero at beginning
            $val = (substr($row[$j], 0, 1) == 0) ? "&#8203;" . $row[$j] : $row[$j];
            $datacells->get($row_idx, $j)->putValue($row[$j]);

            // Adjust column width according to the field value
            $col_width = $datacells->getColumns()->get($j)->getWidth();
            if((int)(string)$col_width < strlen($row[$j])) {
              $datacells->getColumns()->get($j)->setWidth(min(strlen($row[$j]), 40));
            }

            // Column Cell formatting
            if($reportView == "group_by_credit") {
              $col_name = ($reportView == "summary") ? $sDataOrderSummaryRows[0][$j] : $sData[0][$j];
              $thisCellCustomStyleArray = (isset($sDataRowStyles[$i][$j]) && $sDataRowStyles[$i][$j] != "") ? explode(",", $sDataRowStyles[$i][$j]) : [];
              $custom_field_style = $datacells->get($row_idx, $j)->getStyle();
              if(strpos($col_name, 'Year') !== false ||
                  strpos($col_name, 'Amount') !== false) {
                $custom_field_style->setNumber(4);
                $custom_field_style->sethorizontalAlignment(cells\TextAlignmentType::LEFT);
              }
              if(in_array('bold', $thisCellCustomStyleArray)) {
                $custom_field_style->getFont()->setBold(true);
              }
              if(in_array('borderTopMedium', $thisCellCustomStyleArray)) {
                $custom_field_style->setBorder(cells\BorderType::TOP_BORDER, cells\CellBorderType::MEDIUM, cells\Color::getBlack());
              }
              if(in_array('textAlignCenter', $thisCellCustomStyleArray)) {
                $custom_field_style->sethorizontalAlignment(cells\TextAlignmentType::CENTER);
              }

              $datacells->get($row_idx, $j)->setStyle($custom_field_style);

            } else {
              $val = preg_replace("/[^0-9.]/", "", $row[$j]);
              $col_format = $sDataKeyFormats[$cData['columns'][$j]];

              $number_style = $datacells->get($row_idx, $j)->getStyle();

              if($col_format == "number") {
                $number_style->setNumber(1);
                $datacells->get($row_idx, $j)->putValue((int)$val);
              } else {
                if($col_format == "currencyNoDecimal") {
                  $number_style->setNumber(5);
                  $datacells->get($row_idx, $j)->putValue((int)$val);
                } else {
                  if($col_format == "currencyTwoDecimal") {
                    //$number_style->setNumber(7);
                    $number_style->setCustom('#,##0.00_);(#,##0.00)');
                    $datacells->get($row_idx, $j)->putValue((float)$val);
                  } else {
                    if($col_format == "currencyFourDecimal") {
                      $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
                      $datacells->get($row_idx, $j)->putValue((float)$val);
                    } else {
                      if($col_format == "numberFourDecimal") {
                        $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
                        $datacells->get($row_idx, $j)->putValue((float)$val);
                      }
                    }
                  }
                }
              }

              $datacells->get($row_idx, $j)->setStyle($number_style);
            }
          }
        }

        $row_idx++;

        // Add Total row if the report is not "group_by_credit"
        if($reportView != "group_by_credit") {
          $total_style = new cells\Style();
          $total_style->getFont()->setName("Calibri");
          $total_style->getFont()->setSize(11);
          $total_style->getFont()->setBold(true);
          $total_style->setTextWrapped(true);
          $total_style->setForegroundColor(cells\Color::fromArgb(150, 180, 213));
          $total_style->setPattern(cells\BackgroundType::SOLID);

          for($j = 0; $j < $tbl_cols; $j++) {
            $datacells->get($row_idx, $j)->setStyle($total_style);

            $col_format = $sDataKeyFormats[$cData['columns'][$j]];

            $number_style = $datacells->get($row_idx, $j)->getStyle();

            if($col_format == "currencyNoDecimal") {
              $number_style->setNumber(5);
              $datacells->get($row_idx, $j)->setFormula('=SUM(INDIRECT(ADDRESS(6,COLUMN())&":"&ADDRESS(ROW()-1,COLUMN())))');
            } else {
              if($col_format == "currencyTwoDecimal") {
                //$number_style->setNumber(7);
                $number_style->setCustom('#,##0.00_);(#,##0.00)');
                $datacells->get($row_idx, $j)->setFormula('=SUM(INDIRECT(ADDRESS(6,COLUMN())&":"&ADDRESS(ROW()-1,COLUMN())))');
              } else {
                if($col_format == "currencyFourDecimal") {
                  $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
                  $datacells->get($row_idx, $j)->setFormula('=SUM(INDIRECT(ADDRESS(6,COLUMN())&":"&ADDRESS(ROW()-1,COLUMN())))');
                } else {
                  /*
                  if($col_format == "numberFourDecimal") {
                    $number_style->setCustom('#,##0.0000_);(#,##0.0000)');
                    $datacells->get($row_idx, $j)->setFormula('=SUM(INDIRECT(ADDRESS(6,COLUMN())&":"&ADDRESS(ROW()-1,COLUMN())))');
                  }
                  */
                }
              }
            }

            $datacells->get($row_idx, $j)->setStyle($number_style);
          }

          $bold_style = $datacells->get($row_idx, 0)->getStyle();
          $bold_style->getFont()->setBold(true);
          $datacells->get($row_idx, 0)->setStyle($bold_style);

          $datacells->get($row_idx, 0)->putValue('Total');
        }

        $csvTitleCleaned = preg_replace("/[^0-9a-zA-Z_\s]+/", "", $csvTitle);
        $fileName = 'OIX Report - ' . $csvTitleCleaned . '.xlsx';
        $filePath = APPPATH . '../tmp_files/' . $fileName;
        $workbook->save($filePath);
        header('Pragma: public');
        header("Content-type:application/vnd.ms-excel; charset=utf-8");
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);

      } else {

        // output headers so that the file is downloaded rather than displayed
        $csvTitle = preg_replace("/[^a-zA-Z0-9]+/", "", $csvTitle);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=OIX Report - ' . $csvTitle . '.csv');

        // create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');

        foreach($columnData as $s) {
          fputcsv($output, $s);
        }

      }

    }

  }



  /////////////////////////////////////////////////////////////////////////////////////
  /////// FUNCTIONS BELOW ARE NOT REALLY IN USE (From ye olde marketplace era, safe to disregard) - AND ARE NOT PART OF ASPOSE //////////
  /////////////////////////////////////////////////////////////////////////////////////

  function usage_report_csv() {

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

    $listedBy = $this->cisession->userdata('primUserId');
    $audTypeId = 4;
    $data['auditReport'] = $this->AuditTrail->get_audit_trail_of_item_for_account($listedBy, $audTypeId, 'amountLocal');

    // output headers so that the file is downloaded rather than displayed
    $titleSectionClean = preg_replace("/[^a-zA-Z0-9]+/", "", $this->cisession->userdata('dmaTitle'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $titleSectionClean . '-Credit_Amount_History.csv');

    // create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    fputcsv($output, [$this->cisession->userdata('dmaTitle') . ' Analysis']);
    fputcsv($output, [' ']); //insert blank row first
    fputcsv($output, ['ID', 'Amount', 'Date', 'Time', 'Project']);

    //Loop through credits
    foreach($data['auditReport'] as $ap) {

      //Loop through audit trail
      foreach($ap as $r) {

        $t = [$ap[0]['listingIdFull'], $r['amountLocal'], date('m/d/Y', strtotime($r['timeStamp'])), date('h:i:s', strtotime($r['timeStamp'])), $ap[0]['projectName']];
        fputcsv($output, $t);

      }

    }

  }

  function listing_trades_csv($listingId) {

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

      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($listingId);

    }

    //First, get the listing information
    $listing = $this->CreditListings->get_active_listing($listingId);

    // output headers so that the file is downloaded rather than displayed
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $listing['state'] . $listingId . '-Trades.csv');

    // create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    //Get trades on the listing
    $trades = $this->Trading->get_trades_on_listing($listingId);
    $trades = $trades['trades'];

    fputcsv($output, ['Credit', 'Trade', 'Date', 'Amount', 'Price (%)', 'Price ($)', 'Transactions', 'Buyer']);

    // loop over the rows, outputting them
    foreach($trades as $t) {
      $t = [$t['State'] . $t['listingId'], $t['tradeId'], date('m/d/Y H:i:s', $t['brokeDate']), $t['tradeSize'], $t['tradePrice'], $t['tradeSize'] * $t['tradePrice'], sizeof($t['transactions']), $t['buyerName']['companyName']];
      fputcsv($output, $t);
    }

  }

  function listing_transactions_csv($listingId) {

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

      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($listingId);

    }

    //First, get the listing information
    $listing = $this->CreditListings->get_active_listing($listingId);

    // output headers so that the file is downloaded rather than displayed
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $listing['state'] . $listingId . '-Transactions.csv');

    // create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    //Get trades on the listing
    $transactions = $this->Trading->get_transactions_of_listing($listingId);

    fputcsv($output, ['Last Name', 'First Name', 'Company', 'EIN/SSN', 'State Tax ID', 'Street Address', 'City', 'State', 'Zip', 'Amount', 'Price ($)', 'Price (%)', 'Listing', 'Trade', 'Transaction', 'Date', 'Email', 'Representative Firm', 'Representative', 'Rep. Email']);
    //fputcsv($output, array('Company', 'Last Name', 'First Name', 'Email', 'EIN/SSN', 'State Tax ID', 'Listing', 'Trade', 'Transaction', 'Date', 'Amount', 'Price (%)', 'Price ($)', 'Representative Firm', 'Representative', 'Rep. Email'));

    // loop over the rows, outputting them
    foreach($transactions as $t) {
      $tpAddressFull = $t['tpAddress1'] . " " . $t['tpAddress2'];
      $t = [$t['tpLastName'], $t['tpFirstName'], $t['tpCompanyName'], $t['tpEinSsn'], $t['tpStateTaxId'], $tpAddressFull, $t['tpCity'], $t['tpState'], $t['tpZip'], $t['tCreditAmount'], $t['tCreditAmount'] * $t['tradePrice'], $t['tradePrice'], $listing['State'] . $listing['listingId'], $t['tradeId'], $t['transactionId'], date("m/d/Y", $t['tTimestamp']), $t['tpEmailSigner'], $t['buyerName']['companyName'], $t['buyerFirstName'] . " " . $t['buyerLastName'], $t['buyerEmail']];
      fputcsv($output, $t);
    }

  }

  function trade_transactions_csv($tradeId) {

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

      //First, get the trade information
      $tradeToCheck = $this->Trading->get_trade($tradeId);
      $tradeToCheck = $tradeToCheck['trade'];

      //Check access to credit, get permissions, add to $data
      $permissions = $this->memberpermissions->checkCreditAccess($tradeToCheck['listingId']);

    }

    //First, get the trade information
    $trade = $this->Trading->get_trade($tradeId);
    $trade = $trade['trade'];

    // output headers so that the file is downloaded rather than displayed
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $trade['State'] . $trade['listingId'] . '-' . $tradeId . '-Transactions.csv');

    // create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    //Get trades on the listing
    $transactions = $this->Trading->get_transactions_of_trade($tradeId);

    fputcsv($output, ['Last Name', 'First Name', 'Company', 'EIN/SSN', 'State Tax ID', 'Street Address', 'City', 'State', 'Zip', 'Amount', 'Price ($)', 'Price (%)', 'Listing', 'Trade', 'Transaction', 'Date', 'Email', 'Representative Firm', 'Representative', 'Rep. Email']);
    //fputcsv($output, array('Company', 'Last Name', 'First Name', 'Email', 'EIN/SSN', 'State Tax ID', 'Listing', 'Trade', 'Transaction', 'Date', 'Amount', 'Price (%)', 'Price ($)', 'Representative Firm', 'Representative', 'Rep. Email'));

    // loop over the rows, outputting them
    foreach($transactions as $t) {
      $tpAddressFull = $t['tpAddress1'] . " " . $t['tpAddress2'];
      $t = [$t['tpLastName'], $t['tpFirstName'], $t['tpCompanyName'], $t['tpEinSsn'], $t['tpStateTaxId'], $tpAddressFull, $t['tpCity'], $t['tpState'], $t['tpZip'], $t['tCreditAmount'], $t['tCreditAmount'] * $t['tradePrice'], $t['tradePrice'], $trade['State'] . $trade['listingId'], $t['tradeId'], $t['transactionId'], date("m/d/Y", $t['tTimestamp']), $t['tpEmailSigner'], $t['buyerName']['companyName'], $t['buyerFirstName'] . " " . $t['buyerLastName'], $t['buyerEmail']];
      fputcsv($output, $t);
    }

  }

}
