<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Calendar extends CI_Controller {
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
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Taxpayersdata');
    $this->load->model('Email_Model');

    $this->load->library('filemanagementlib');

  }

  function index($test = "") {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $data['lnav_key'] = "calendar";
      $data['tabArray'] = "calendar";
      $data['current_tab_key'] = "calendar";
      $data['currentPage'] = "calendar";
      $data['calendarPage'] = "calendar";

      $data['fieldsType'] = "mycredits";

      //Centralized function to clean/prepare filter data
      $sanitizedPostData = $this->CreditListings->prepareFilterData();
      $data = array_merge($data, $sanitizedPostData);

      //Centralized function build filter query parameters
      $cData = $this->CreditListings->buildFilterSearchData($data);

      $data['calendarEvents'] = $this->CreditListings->get_credits_calendar_dates($cData);

      //If an account is selected for filtering
      if($data['sharedAccount'] > 0) {
        $data['selectedAccount'] = $this->DmaAccounts->get_dma_account_by_id($data['sharedAccount']); //TODO - convert to DMA ID
      } else {
        if($data['sharedAccount'] == 'self') {
          $data['selectedAccount'] = $this->DmaAccounts->get_dma_account_by_id($this->cisession->userdata('dmaId'));
        } else {
          $data['selectedAccount'] = [];
        }
      }

      $calData['userId'] = $this->cisession->userdata('userId');
      $calData['dmaId'] = $this->cisession->userdata('dmaId');
      $calData['accountType'] = $this->cisession->userdata('level');
      $calData['warned'] = 0;
      $calData['read'] = 0;
      $calData['type'] = "calendar";
      $calData['order'] = "mscontent_asc";
      $data['calendarAlerts'] = $this->Members_Model->get_messages_of_user($calData);

      //Merge calendar events
      $data['calendarArray'] = [];
      $data['mapArray'] = [];

      $data['calendarArray'] = array_merge($data['calendarArray'], $data['calendarEvents']['allEvents']);
      $data['mapArray'] = array_merge($data['mapArray'], $data['calendarEvents']['futureEvents']);

      //Merge my loans activity
      //$data['calendarArray'] = array_merge($data['calendarArray'], $data['loansCalendarEvents']['allEvents']);
      //$data['mapArray'] = array_merge($data['mapArray'], $data['loansCalendarEvents']['futureEvents']);

      //Load all data in sub-data for sub-included view
      $data['data'] = $data;

      $data['planData'] = $this->cisession->userdata('planData');

      $this->load->view('includes/left_nav', $data);
      $this->load->view('includes/tab_nav_calendar', $data);
      if($test == "test") {
        $this->load->view("calendar/calendar_test", $data);
      } else {
        $this->load->view("calendar/calendar", $data);
      }
      $this->load->view('includes/footer-2', $data);

    }

  }

  function calendar_event_download() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      $eventTitle = addslashes(strip_tags($this->input->post('eventTitle')));
      $eventDescription = addslashes(strip_tags($this->input->post('eventDescription')));
      $timestamp = date('Ymd', time());
      $startdate = date('Ymd', strtotime($this->input->post('startdate')) + 50000); //add 50000 seconds because the calendar converts the date into GMT which makes it a day short of local ET

      /* emailing the calendar invite isn't working because the email templtae prints the HTML which the header of triggers the download rathre than send an email
            $emailData['subject'] = "OIX Calendar Event - ".$eventTitle;
            $emailData['eventTitle'] = $eventTitle;
            $emailData['eventDescription'] = $eventDescription;
            $emailData['timestamp'] = $timestamp;
            $emailData['startdate'] = $startdate;
            $userData = $this->Members_Model->get_member_by_id();


            $this->Email_Model->_send_email('calendar_event', $emailData['subject'], $userData['email'], $emailData);
      */

      header("Content-Type: text/Calendar");
      header("Content-Disposition: inline; filename=oix_calendar_event.ics");

      echo "BEGIN:VCALENDAR\r\n";
      echo "PRODID:-//Microsoft Corporation//Outlook 12.0 MIMEDIR//EN\r\n";
      echo "VERSION:2.0\r\n";
      echo "METHOD:PUBLISH\r\n";
      echo "X-MS-OLK-FORCEINSPECTOROPEN:TRUE\r\n";

      echo "BEGIN:VEVENT\r\n";
      echo "CLASS:PUBLIC\r\n";
      //echo "CREATED:$createdn";
      echo "DESCRIPTION:" . $eventDescription . "\r\n";
      echo "DTSTAMP:" . $timestamp . "T090000\r\n";
      echo "DTSTART:" . $startdate . "T090000\r\n";
      echo "DTEND:" . $startdate . "T100000\r\n";
      //echo "LAST-MODIFIED:0101T000000\r\n";
      //echo "LOCATION:Location_of_Event\r\n";
      echo "PRIORITY:5\r\n";
      echo "SEQUENCE:0\r\n";
      echo "SUMMARY;LANGUAGE=en-us:" . $eventTitle . "\r\n";
      echo "TRANSP:OPAQUE\r\n";
      echo "UID:" . rand() . "\r\n";  // UID just needs to be some random number.  I used rand() in PHP.
      echo "X-MICROSOFT-CDO-BUSYSTATUS:BUSY\r\n";
      echo "X-MICROSOFT-CDO-IMPORTANCE:1\r\n";
      echo "X-MICROSOFT-DISALLOW-COUNTER:FALSE\r\n";
      echo "X-MS-OLK-ALLOWEXTERNCHECK:TRUE\r\n";
      echo "X-MS-OLK-AUTOFILLLOCATION:FALSE\r\n";
      echo "X-MS-OLK-CONFTYPE:0\r\n";

      echo "END:VEVENT\r\n";
      echo "END:VCALENDAR\r\n";

    }
  }

  function dateToCal($time) {
    return date('Ymd', $time);
  }

}
