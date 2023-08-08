<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Services\ReportGeneratorService;
use OIX\Util\Logger;

class Notifications extends CI_Controller {
  protected $logger;
  protected $redis;

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
    $this->load->model('Workflow');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Taxpayersdata');
    $this->load->model('ReportsData');
    $this->load->model('Email_Model');
    $this->load->model('AuditTrail');

    $this->load->library('filemanagementlib');
    $this->logger = Logger::getInstance();

    require_once __DIR__ . '/../../vendor/autoload.php';
    $this->redisIsAvailable = false;
    try {
      $this->redis = new Predis\Client([
                                           'scheme' => $this->config->item('AWS_elasticache_scheme'),
                                           'host'   => $this->config->item('AWS_elasticache_endpoint'),
                                           'port'   => 6379,
                                       ]);
      $this->redis->ping();
      $this->redisIsAvailable = true;
    } catch(Exception $ex) {
      echo $ex->getMessage();
    }

  }

  //   /notifications/run_scheduled_reports/98f97s9a8f8f
  function run_scheduled_reports($uniqueId = "") {
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    $reportsSvc = new ReportGeneratorService();
    $results = $reportsSvc->runScheduledReports();

    foreach($results as $result) {
      foreach($result['dmaMembers'] as $dmaMember) {
        $emailData['accounts'][] = $this->Members_Model->get_dma_member($dmaMember);
      }
      foreach($result['dmaMembers'] as $dmaMember) {
        $emailData['accountInfo'] = $this->Members_Model->get_dma_member($dmaMember);
        $emailData['reportUrl'] = $this->config->item("oix_url") . $result['uri'];
        $emailData['reportName'] = $result['name'];
        $emailData['reportTemplateName'] = $result['report_template_name'];
        $emailData['emailSubject'] = 'Your Scheduled Report is Ready: ' . $emailData['reportTemplateName'];
        $this->Email_Model->_send_email('scheduled-report-complete', $emailData['emailSubject'], $emailData['accountInfo']['email'], $emailData);
      }
    }
  }

  function cch_smart_chart_update_check($uniqueId = "", $sinceDays = "") {
    $this->logger->info('SCHEDULED TASK: CCH SMART CHART UPDATE CHECK Started with uniqueId: ' . $uniqueId);
    //If message ID exists...
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    //This function runs every 7 days and looks for any changes within that period

    require APPPATH . '/libraries/SmartChartsCCH.php';
    //$this->load->library('SmartChartsCCH'); - DOES NOT WORK with the "new SmartChartsCCH()" method below

    $auth_code = '4651FBE2-26B3-4076-A759-EA8757105D34';
    $product_cfg_code = 'BINStateXML_RAW';

    $smartcharts = new SmartChartsCCH($auth_code, $product_cfg_code);
    $cchChanges = [];      //Stores a cleaned up summary of the changes for an email to be sent to OIX Admins at the end of this function

    $sinceDays = ($sinceDays > 0) ? $sinceDays : 7;
    $sinceString = '-' . $sinceDays . ' days';

    $date = new DateTime($sinceString);
    $min_last_updated_date = $date->format('Y-m-d');

    $data = $smartcharts->get_smartcharts_data($min_last_updated_date);
    $cchChanges = $data['cch_changes'];

    // If program data changed, send an email to OIX admins detailing what changed: $cchChanges
    $thisDate = date('m/d/Y', time());
    $emailData['cchChanges'] = $cchChanges;
    $emailData['programsUpdated'] = sizeof($cchChanges);
    $emailData['updateType'] = 'cch_smart_chart_updates';
    $emailData['sinceDays'] = $sinceDays;
    $emailData['emailSubject'] = $emailData['programsUpdated'] . ' CCH Updates: ' . $thisDate;
    $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_cch_program_updates"), $emailData);

    $this->logger->info('SCHEDULED TASK: CCH SMART CHART UPDATE CHECK completed for # of changes: ' . count($cchChanges));

    echo "# of CCH Changes: " . sizeof($cchChanges);
  }

  /**
   * Check the updates of all HTML documents between the optional date range and store the updates if there is any changes.
   * You can specify the recordStart and recordEnd value if you want to set document offset to check.
   *
   * @param string $uniqueId
   * @param string $sinceDays
   * @param string $recordStart
   * @param string $recordEnd
   * @throws Exception
   */
  function cch_smart_chart_html_doc_update_check_by_time_period($uniqueId = "", $sinceDays = "", $recordStart = "", $recordEnd = "") {
    ///notifications/cch_smart_chart_html_doc_update_check_by_time_period/98f97s9a8f8f/0/10/20

    //If message ID exists...
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    ini_set('memory_limit', '512M');     // Extend memory for large data
    set_time_limit(604800);     // Increase execution time limit to 7 days for handling mass result set

    $smartcharts = new SmartChartsCCH();

    $sinceDays = ($sinceDays > 0) ? $sinceDays : 7;
    $sinceString = '-' . $sinceDays . ' days';

    $date = new DateTime($sinceString);
    $min_last_updated_date = $date->format('Y-m-d');

    $docChanges = $smartcharts->update_smartcharts_html_docs_by_time_period($min_last_updated_date, "", $recordStart, $recordEnd);

    // Send an notification email to OIX admins detailing what changed.
    $thisDate = date('m/d/Y', time());
    $emailData['docChanges'] = $docChanges;
    $emailData['docsUpdated'] = sizeof($docChanges);
    $emailData['updateType'] = 'cch_html_docs_updates';
    $emailData['sinceDays'] = $sinceDays;
    if($recordStart > 0 && $recordEnd > 0) {
      $range = " (from " . $recordStart . " to " . $recordEnd . ")";
    } else {
      if($recordStart > 0 && $recordEnd == "") {
        $range = " (>=" . $recordStart . ")";
      } else {
        if($recordStart = "" && $recordEnd > 0) {
          $range = " (<=" . $recordEnd . ")";
        } else {
          $range = "";
        }
      }
    }
    $emailData['emailSubject'] = $emailData['docsUpdated'] . ' CCH HTML Document Updates: ' . $thisDate . $range;
    $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_cch_program_updates"), $emailData);

    echo "# of CCH HTML Document Changes: " . sizeof($docChanges);
  }

  /**
   * Check the updates of the limited number of HTML documents checked earliest and store the updates if there is any changes.
   *
   * @param string $uniqueId
   * @param integer $nDocs :  Limited number of document to check updates. Default is 25
   * @throws Exception
   */
  function cch_smart_chart_html_doc_update_check_by_limited_docs($uniqueId = "", $nDocs = 25) {
    ///notifications/cch_smart_chart_html_doc_update_check_by_limited_docs/98f97s9a8f8f/25

    //If message ID exists...
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    ini_set('memory_limit', '512M');     // Extend memory for large data
    set_time_limit(604800);     // Increase execution time limit to 7 days for handling mass result set

    $smartcharts = new SmartChartsCCH();

    $docChanges = $smartcharts->update_smartcharts_html_docs_by_limited_docs($nDocs);

    /*
    if (count($docChanges)) {
      // Send an notification email to OIX admins detailing what changed.
      $thisDate = date('m/d/Y', time());
      $emailData['docChanges'] = $docChanges;
      $emailData['docsUpdated'] = count($docChanges);
      $emailData['updateType'] = 'cch_html_docs_updates';
      $emailData['sinceDays'] = '';
      $emailData['emailSubject'] = $emailData['docsUpdated'] . ' CCH HTML Document Updates: ' . $thisDate;
      $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_cch_program_updates"), $emailData);
    }
    */

    echo "# of CCH HTML Document Changes: " . count($docChanges);
  }

  function oix_admin_report($schedule) {
    $this->logger->info('SCHEDULED TASK: OIX ADMIN REPORT Started for type: ' . $schedule);
    //Schedule #1 = 12pm ET every day (17 hrs since 7pm ET so 61,200)
    if($schedule == 1) {
      $time_1 = 61200;
      $time_2 = 345000;
      $time_sig = 35000;
      $sinceDisplay = 'Yesterday';
      $sinceDisplayTime = '7pm ET';
      $timeDisplay = '12pm ET';
    }
    //Schedule #2 = 7pm ET every day (7 hrs since 12pm ET so 25,200)
    if($schedule == 2) {
      $time_1 = 25200;
      $time_2 = 345000;
      $time_sig = 0;
      $sinceDisplay = 'Today';
      $sinceDisplayTime = '12pm ET';
      $timeDisplay = '7pm ET';
    }
    //Schedule #3 = 4:10pm ET every Friday (7 days since last Friday so 604,800)
    if($schedule == 10 || $schedule == 100) {
      $time_1 = 604800;
      $time_2 = 345000;
      $time_sig = 7200;
      $sinceDisplay = 'Last Friday';
      $timeDisplay = '7pm ET';
    }

    //Logins by members
    $emailData['memberLogins'] = $this->Members_Model->getLoginsSince($time_1);

    //New Admin Users
    $emailData['newAdminUsers'] = $this->Members_Model->getAdminUsersJoinedSince($time_1);

    //New DMA Accounts
    $emailData['newDMA'] = $this->Members_Model->getNewDMAaccountsSince($time_1);

    //Recent credits loaded
    $emailData['newCredits'] = $this->CreditListings->getRecentlyLoadedCredits($time_1);

    $emailData['updateType'] = 'oix_admin_report';

    if($schedule == 10 || $schedule == 100) {
      $timestampLastWeek = time() - 604800;
      $dateThenTimestamp = new DateTime("@" . $timestampLastWeek);
      $dateThenTimestamp->setTimezone(new DateTimeZone('US/Eastern'));
      $dateThen = $dateThenTimestamp->format('h:i A');

      $timeText = date('M j', $timestampLastWeek) . ' - ' . date('M j, Y');
      $emailData['emailSubject'] = 'OIX Admin Report - Week of ' . $timeText;
    } else {
      $timeText = date('D, M j, Y');
      $emailData['emailSubject'] = 'OIX Admin Report - ' . $timeText . ' at ' . $timeDisplay;

      //Get time now formated in ET
      $date = new DateTime("@" . time());
      $date->setTimezone(new DateTimeZone('US/Eastern'));
      $dateNow = $date->format('h:i A');

      //Get time then (beginning of data time) formated in ET
      $dateThenTimestamp = time() - $time_1;
      $dateThenTimestamp = new DateTime("@" . $dateThenTimestamp);
      $dateThenTimestamp->setTimezone(new DateTimeZone('US/Eastern'));
      $dateThen = $dateThenTimestamp->format('h:i A');

    }

    //Build the timerange
    $emailData['reportTimeRangeText'] = "Activity Since " . $sinceDisplay . " at " . $dateThen . " ET";

    if($schedule == 100) {
      $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], 'nick@theoix.com', $emailData);
    } else {
      $this->Email_Model->_send_email('oix_admin_update', $emailData['emailSubject'], $this->config->item("oix_admin_emails_array"), $emailData);
    }

    $this->logger->info('SCHEDULED TASK: OIX ADMIN REPORT completed for report type: ' . $schedule);
    echo json_encode(['success' => true]);
  }

  function member_notifications($schedule, $adminId = "") {

    throw new \Exception('General fail');

    $emailData = [];
    $adminEmailData['memberSentCount'] = 0;
    $adminEmailData['memberNotSentCount'] = 0;
    $adminEmailData['timeStart'] = time();

    //Schedule #1 = 8am ET every day (24 hrs since = 86,400)
    if($schedule == 1 || $schedule == 101) {
      $frequency = 'daily';
      $time_1 = 86400;
      $sinceDisplay = 'Yesterday';
      $sinceDisplayTime = '8am ET';
      $timeDisplay = '8am ET';
      $emailData['dateDay'] = date('D, j M, Y', time() - 86400);
      $emailData['nType'] = $frequency;
      $emailData['emailSubject'] = 'OIX Daily Activity Report (' . $emailData['dateDay'] . ')';
      $emailData['headline'] = 'OIX%20Daily%20Activity%20Report';
      $emailData['updateDescription'] = '';
      $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Daily Notifications Sent';
      $adminEmailData['type'] = 'daily';
    }
    //Schedule #10 = 8am ET every Monday (7 days since = 604,800)
    if($schedule == 10 || $schedule == 110) {
      $frequency = 'weekly';
      $time_1 = 604800;
      $sinceDisplay = 'Last Monday';
      $timeDisplay = '8am ET';
      $emailData['dateStart'] = date('M j', time() - $time_1 - 86400);
      $emailData['dateEnd'] = date('j, Y', time() - 86400);
      $emailData['nType'] = $frequency;
      $emailData['emailSubject'] = 'OIX Weekly Activity Report (' . $emailData['dateStart'] . '-' . $emailData['dateEnd'] . ')';
      $emailData['headline'] = 'OIX%20Weekly%20Activity%20Report';
      //$emailData['updateDescription'] = 'In the past week the following activity has occurred on the OIX Marketplace:';
      $emailData['updateDescription'] = '';
      $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Weekly Notifications Sent';
      $adminEmailData['type'] = 'weekly';
    }
    //Schedule #20 = 8am ET on the first of every Month (7 days since = 604,800)
    if($schedule == 20 || $schedule == 120) {
      $frequency = 'monthly';
      //Get the number of days in the prior month

      $days = cal_days_in_month(CAL_GREGORIAN, date('m', time() - 100000), date('Y', time() - 100000));
      $time_1 = $days * 24 * 60 * 60;
      $sinceDisplay = 'Last Month';
      $timeDisplay = '8am ET';
      $emailData['dateRange'] = date('F, Y', time() - 100000);
      $emailData['nType'] = $frequency;
      $emailData['emailSubject'] = 'OIX Monthly Activity Report (' . $emailData['dateRange'] . ')';
      $emailData['headline'] = 'OIX%20Monthly%20Activity%20Report';
      $emailData['updateDescription'] = '';
      $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Monthly Notifications Sent';
      $adminEmailData['type'] = 'monthly';
    }

    //GET MARKET TOTALS
    $bidIndication = $this->CreditListings->get_overall_bids();
    $offerIndication = $this->CreditListings->get_overall_offers();
    $emailData['bidIndication'] = $bidIndication['data']['bvolume'] + $bidIndication['data']['fbvolume'];
    $emailData['offerIndication'] = $offerIndication['data']['ovolume'] + $offerIndication['data']['fovolume'];

    //New Credits Listed
    $data['new_credit'] = $this->CreditListings->getCreditsListedSince($time_1);
    $emailData['new_credit_total_amount'] = 0;
    $credit_avg_price = 0;

    //Drop in a custom credit record
    /*
        $customCredit['state'] = "CT";
        $customCredit['listingId'] = 2314;
        $customCredit['offerSize'] = 339000;
        $customCredit['name'] = "Connecticut";
        $customCredit['ProgramName'] = "Digital Media & Motion Picture Tax Credit";
        $customCredit['taxYear'] = "2014";
        $customCredit['maxYears'] = "2017";
        $customCredit['offerPrice'] = 0.9000;
        $customCredit['listingDate'] = "10/27/2016";

        array_push($data['new_credit'], $customCredit);
    */

    foreach($data['new_credit'] as $ncr) {
      $emailData['new_credit_total_amount'] = $emailData['new_credit_total_amount'] + $ncr['offerSize'] + $ncr['tradesTotal'];
      $credit_avg_price = $credit_avg_price + $ncr['offerPrice'];
    }
    if($credit_avg_price != 0) {
      $emailData['new_credit_avg_price'] = $credit_avg_price / sizeof($data['new_credit']);
    } else {
      $emailData['new_credit_avg_price'] = 0;
    }

    //New Trades Listed
    $data['new_trade'] = $this->Trades->getTradesSince($time_1);
    $emailData['new_trade_total_amount'] = 0;
    $trade_avg_price = 0;
    $tradesByListingArray = [];
    foreach($data['new_trade'] as $ntr) {

      $emailData['new_trade_total_amount'] = $emailData['new_trade_total_amount'] + $ntr['tradeSize'];
      $trade_avg_price = $trade_avg_price + $ntr['tradePrice'];

      //If trade's credit listing is not already in array, insert it as new sub-array
      if(!array_key_exists($ntr['listingId'], $tradesByListingArray)) {
        $tradesByListingArray[$ntr['listingId']] = [];
      }
      //Add this trade data to the sub-array for this listing
      $tradesByListingArray[$ntr['listingId']][sizeof($tradesByListingArray[$ntr['listingId']])] = $ntr;

    }
    if($trade_avg_price != 0) {
      $emailData['new_trade_avg_price'] = $trade_avg_price / sizeof($data['new_trade']);
    } else {
      $emailData['new_trade_avg_price'] = 0;
    }

    //New Bids Listed (but NOT bids that have already traded during the period, since that will be covered by the trades data)
    $data['bids'] = $this->BidMarket->getBidsSince($time_1, 0);
    $data['openbids'] = $this->BidMarket->getOpenBidsSince($time_1);
    $data['new_bid'] = array_merge($data['bids'], $data['openbids']);
    $emailData['new_bid_total_amount'] = 0;
    $bid_avg_price = 0;
    $bidsByListingArray = [];
    foreach($data['new_bid'] as $ntr) {
      $emailData['new_bid_total_amount'] = $emailData['new_bid_total_amount'] + $ntr['bidSize'];
      $bid_avg_price = $bid_avg_price + $ntr['bidPrice'];

      //If bid's credit listing is not already in array, insert it as new sub-array
      if(!array_key_exists($ntr['listingId'], $bidsByListingArray)) {
        $bidsByListingArray[$ntr['listingId']] = [];
      }
      //Add this trade data to the sub-array for this listing
      $bidsByListingArray[$ntr['listingId']][sizeof($bidsByListingArray[$ntr['listingId']])] = $ntr;

    }
    if($bid_avg_price != 0) {
      $emailData['new_bid_avg_price'] = $bid_avg_price / sizeof($data['new_bid']);
    } else {
      $emailData['new_bid_avg_price'] = 0;
    }

    $emailData['updateType'] = 'member_notifications_report';
    $emailData['welcomeNameTemplate'] = 0;
    $emailData['button'] = 1;

    //Get active members
    //If this is a test scenario, then just get OIX Admin emails
    if($schedule == 101 || $schedule == 110 || $schedule == 120) {
      $activeMembers = [];
      $selfData = $this->Members_Model->get_admin_by_id_for_notifications($adminId);
      array_push($activeMembers, $selfData);
      /*
      $adminEmails = $this->config->item('oix_admin_emails_array');
      foreach($adminEmails as $key => $value) {
        $memberData = $this->Members_Model->get_member_by_email_for_notifications($value);
        array_push($activeMembers, $memberData);
      }
      */
    } else {
      $activeMembers = $this->Members_Model->get_active_members_for_notifications("on");
    }

    //Total member "pool" being considered in this notification
    $emailData['membersSize'] = sizeof($activeMembers);
    $adminEmailData['membersSize'] = sizeof($activeMembers);

    //Loop through each
    foreach($activeMembers as $am) {

      $emailData['firstName'] = $am['firstName'];
      $emailData['userId'] = $am['userId'];
      $emailData['notifications']['new_credit'] = [];
      $emailData['notifications']['new_trade'] = [];
      $emailData['notifications']['new_bid'] = [];
      $emailData['notificationCount'] = 0;

      //If there is an admin ID present (sending to self)
      if($adminId != "") {
        $nsActivity = [];
        $nsStatesCodes = [];
        $nsFrequency = "realtime";
        $am['nsMinimum'] = 0;
        $am['nsMinimumAmount'] = 0;
        //This variable let's admins through all activity
        $adminPass = 'yes';
        $nsPass = 'no';
        //If the user had notification settings
      } else {
        if($am['nsUserId'] != "") {
          $nsActivity = explode(',', $am['nsActivity']);
          $nsStatesCodes = explode(',', $am['nsStatesCodes']);
          $nsFrequency = $am['nsFrequency'];
          $adminPass = 'no';
          $nsPass = 'no';
          //If the user does NOT have notification settings
        } else {
          $nsActivity = [];
          $nsStatesCodes = [];
          $nsFrequency = "weekly";
          $am['nsMinimum'] = 1;
          $am['nsMinimumAmount'] = 100000;
          $adminPass = 'no';
          $nsPass = 'yes';
        }
      }

      //If this cron is running a frequency that matches the user's frequency setting, then run it (or if user is "realtime" or "daily" and this is a weekly report)
      if($nsFrequency == $frequency || ($frequency == 'weekly' && ($nsFrequency == 'realtime' || $nsFrequency == 'daily')) || $adminPass == 'yes') {

        ///////
        ///////   NEW CREDITS
        ///////
        //If credits activity is in NS... or if NS is NOT set (include in both situations)
        if(in_array('new_credit', $nsActivity) || $adminPass == 'yes' || $nsPass == 'yes') {

          //Loop through new credits to check if they should be added to notifications array
          foreach($data['new_credit'] as $nc) {

            //check minimum amount threshold set by user
            if($nc['offerSize'] >= $am['nsMinimumAmount'] || $am['nsMinimum'] == 0) {
              $checkSize = true;
            } else {
              $checkSize = false;
            }

            //check jurisdiction set by user
            if(in_array($nc['state'], $nsStatesCodes) || $adminPass == 'yes' || $nsPass == 'yes') {
              $checkJurisdiction = true;
            } else {
              $checkJurisdiction = false;
            }

            //If all is TRUE, then add it to the temp array
            if($checkSize && $checkJurisdiction) {
              //Push new credits into temp array
              array_push($emailData['notifications']['new_credit'], $nc);
              $emailData['notificationCount']++;
            }

          }

        }

        ///////
        ///////   NEW TRADES
        ///////
        //If credits activity is in NS... or if NS is NOT set (include in both situations)
        /*
        if (in_array('new_trade', $nsActivity) || $adminPass=='yes' || $nsPass=='yes') {

          //Loop through new credits to check if they should be added to notifications array
          foreach ($data['new_trade'] as $nt) {

            //check minimum amount threshold set by user
            if($nt['tradeSize']>=$am['nsMinimumAmount'] || $am['nsMinimum']==0) {
              $checkSize = TRUE;
            } else {
              $checkSize = FALSE;
            }

            //check jurisdiction set by user
            if (in_array($nt['state'], $nsStatesCodes) || $adminPass=='yes' || $nsPass=='yes') {
              $checkJurisdiction = TRUE;
            } else {
              $checkJurisdiction = FALSE;
            }

            //If all is TRUE, then add it to the temp array
            if($checkSize && $checkJurisdiction) {
              //Push new credits into temp array
              array_push($emailData['notifications']['new_trade'], $nt);
              $emailData['notificationCount']++;
            }

          }

        }*/

        if(in_array('new_trade', $nsActivity) || $adminPass == 'yes' || $nsPass == 'yes') {

          //Loop through new credits to check if they should be added to notifications array
          foreach($tradesByListingArray as $tradeListing) {

            $newTradeCount = 0;
            $newTradeAmount = 0;
            $newTradePriceTotal = 0;
            $newTradePriceAvg = 0;
            foreach($tradeListing as $subTrade) {

              $newTradeAmount = $newTradeAmount + $subTrade['tradeSize'];
              $newTradePriceTotal = $newTradePriceTotal + $subTrade['tradePrice'];
              $newTradeCount++;

            }
            $newTradePriceAvg = $newTradePriceTotal / $newTradeCount;

            //check minimum amount threshold set by user
            if($newTradeAmount >= $am['nsMinimumAmount'] || $am['nsMinimum'] == 0) {
              $checkSize = true;
            } else {
              $checkSize = false;
            }

            //check jurisdiction set by user
            if(in_array($tradeListing[0]['state'], $nsStatesCodes) || $adminPass == 'yes' || $nsPass == 'yes') {
              $checkJurisdiction = true;
            } else {
              $checkJurisdiction = false;
            }

            //If all is TRUE, then add it to the temp array
            if($checkSize && $checkJurisdiction) {

              //Add sub-trade data to main data
              $tradeListing['tradeCount'] = $newTradeCount;
              $tradeListing['tradePrice'] = $newTradePriceAvg;
              $tradeListing['tradeSize'] = $newTradeAmount;
              $tradeListing['state'] = $tradeListing[0]['state'];
              $tradeListing['name'] = $tradeListing[0]['name'];
              $tradeListing['listingId'] = $tradeListing[0]['listingId'];
              $tradeListing['taxYear'] = $tradeListing[0]['taxYear'];
              $tradeListing['ProgramName'] = $tradeListing[0]['ProgramName'];

              //Push new credits into temp array
              array_push($emailData['notifications']['new_trade'], $tradeListing);
              $emailData['notificationCount']++;
            }

          }

        }

        ///////
        ///////   NEW BIDS
        ///////
        //If credits activity is in NS... or if NS is NOT set (include in both situations)
        /*
        if (in_array('new_bid', $nsActivity) || $adminPass=='yes' || $nsPass=='yes') {

          //Loop through new credits to check if they should be added to notifications array
          foreach ($data['new_bid'] as $nb) {

            //check minimum amount threshold set by user
            if($nb['bidSize']>=$am['nsMinimumAmount'] || $am['nsMinimum']==0) {
              $checkSize = TRUE;
            } else {
              $checkSize = FALSE;
            }

            //check jurisdiction set by user
            if (in_array($nb['state'], $nsStatesCodes) || $adminPass=='yes' || $nsPass=='yes') {
              $checkJurisdiction = TRUE;
            } else {
              $checkJurisdiction = FALSE;
            }

            //If all is TRUE, then add it to the temp array
            if($checkSize && $checkJurisdiction) {
              //Push new credits into temp array
              array_push($emailData['notifications']['new_bid'], $nb);
              $emailData['notificationCount']++;
            }

          }

        }*/

        if(in_array('new_bid', $nsActivity) || $adminPass == 'yes' || $nsPass == 'yes') {

          //Loop through new credits to check if they should be added to notifications array
          foreach($bidsByListingArray as $bidListing) {

            $newBidCount = 0;
            $newBidAmount = 0;
            $newBidPriceTotal = 0;
            $newBidPriceAvg = 0;
            foreach($bidListing as $subBid) {

              $newBidAmount = $newBidAmount + $subBid['bidSize'];
              $newBidPriceTotal = $newBidPriceTotal + $subBid['bidPrice'];
              $newBidCount++;

            }
            $newBidPriceAvg = $newBidPriceTotal / $newBidCount;

            //check minimum amount threshold set by user
            if($newBidAmount >= $am['nsMinimumAmount'] || $am['nsMinimum'] == 0) {
              $checkSize = true;
            } else {
              $checkSize = false;
            }

            //check jurisdiction set by user
            if(in_array($bidListing[0]['state'], $nsStatesCodes) || $adminPass == 'yes' || $nsPass == 'yes') {
              $checkJurisdiction = true;
            } else {
              $checkJurisdiction = false;
            }

            //If all is TRUE, then add it to the temp array
            if($checkSize && $checkJurisdiction) {

              //Add sub-trade data to main data
              $bidListing['bidCount'] = $newBidCount;
              $bidListing['bidPrice'] = $newBidPriceAvg;
              $bidListing['bidSize'] = $newBidAmount;
              $bidListing['state'] = $bidListing[0]['state'];
              $bidListing['name'] = $bidListing[0]['name'];
              $bidListing['listingId'] = $bidListing[0]['listingId'];
              $bidListing['taxYear'] = $bidListing[0]['taxYear'];

              //Push new credits into temp array
              array_push($emailData['notifications']['new_bid'], $bidListing);
              $emailData['notificationCount']++;
            }

          }

        }

        //Send the email to the member
        if($emailData['notificationCount'] > 0) {
          $this->Email_Model->_send_email('member_template_1', $emailData['emailSubject'], $am['email'], $emailData);
          //Increment the member count that is sent an email
          $adminEmailData['memberSentCount']++;
          echo $am['email'] . ' / ' . $emailData['notificationCount'] . '<br>';
        } else {
          //Increment the member count that is NOT sent an email
          $adminEmailData['memberNotSentCount']++;
          echo '<span style="color:#ff0000">' . $am['email'] . '</span><br>';
        }

      } else {
        //Increment the member count that is NOT sent an email
        $adminEmailData['memberNotSentCount']++;
      }

    }

    //Collect some more admin report data regarding start/finish time of script
    $adminEmailData['timeFinish'] = time();
    echo '<br><br>Started - ' . date('h:i:s', $adminEmailData['timeStart']) . '<br>';
    echo 'Finished - ' . date('h:i:s', $adminEmailData['timeFinish']) . '<br>';

    if($adminId == "") {

      //Insert FINISHED notification to database
      $nData['nStatus'] = 2;
      $nData['nType'] = $frequency;
      $nData['nMembers'] = 'active_all_notifications_on';
      $nData['nGreeting'] = 0;
      $nData['nSignature'] = 1;
      $nData['nButton'] = 1;
      $nData['nJurisdiction'] = '';
      $nData['nAmount'] = '';
      $nData['nSubject'] = $emailData['emailSubject'];
      $nData['nHeadline'] = str_replace('%20', ' ', $emailData['headline']);
      $nData['nStartTime'] = $adminEmailData['timeStart'];
      $nData['nFinishTime'] = $adminEmailData['timeFinish'];
      $nData['nSentCount'] = $adminEmailData['memberSentCount'];
      $nData['nNotSentCount'] = $adminEmailData['memberNotSentCount'];
      $nData['nTotalMembers'] = $adminEmailData['membersSize'];
      $this->Members_Model->insert_completed_notification($nData);

    }

    //Send report to OIX Admin
    $adminEmailData['updateType'] = 'oix_admin_report_notifications';
    $this->Email_Model->_send_email('oix_admin_update', $adminEmailData['emailSubject'], $this->config->item("oix_dev_emails_array"), $adminEmailData);

  }

  function member_realtime_notifications($messageId = "", $adminId = "", $testBlock = "") {

    throw new \Exception('General fail');

    //If message ID exists...
    if($messageId != "") {

      //Get Pending Notifications
      $messages = [];
      $message = $this->Members_Model->get_notification_by_id($messageId);
      array_push($messages, $message);

    } else {

      //Get Pending Notifications
      $messages = $this->Members_Model->get_pending_notifications('fhs7fyshksdf');

    }

    //Loop through each message
    foreach($messages as $m) {

      //Make sure this isn't an empty notification
      if($m['nType'] != "" || $m['nType'] != "") {

        if($adminId == "") {
          //Update start time and status to 1
          $this->Members_Model->update_notification_to_processing($m['nId']);
        }

        //Reset email tallies
        $emailData = [];
        $adminEmailData['memberSentCount'] = 0;
        $adminEmailData['memberNotSentCount'] = 0;
        $adminEmailData['timeStart'] = time();

        //check members for this notification, and then get those users' info
        $members = [];
        if($messageId != "" && $adminId != "" && $adminId != 0) {
          $selfData = $this->Members_Model->get_admin_by_id_for_notifications($adminId);
          array_push($members, $selfData);
        } else {
          if($m['nMembers'] == 'self') {
            $selfData = $this->Members_Model->get_admin_by_id_for_notifications($m['nAdmin']);
            array_push($members, $selfData);
          } else {
            if($m['nMembers'] == 'admin') {
              $adminEmails = $this->config->item('oix_admin_emails_array');
              foreach($adminEmails as $key => $value) {
                $memberData = $this->Members_Model->get_member_by_email_for_notifications($value);
                array_push($members, $memberData);
              }
            } else {
              if($m['nMembers'] == 'active_all') {
                $members = $this->Members_Model->get_active_members_for_notifications();
              } else {
                if($m['nMembers'] == 'active_all_notifications_on') {
                  $members = $this->Members_Model->get_active_members_for_notifications("on");
                } else {
                  if($m['nMembers'] == 'active_dma') {
                    $members = $this->Members_Model->get_active_dma_members_for_notifications();
                  } else {
                    if($m['nMembers'] == 'active_dma_notifications_on') {
                      $members = $this->Members_Model->get_active_dma_members_for_notifications("on");
                    } else {
                      if($m['nMembers'] == 'active_general') {
                        $members = $this->Members_Model->get_active_general_members_for_notifications();
                      } else {
                        if($m['nMembers'] == 'active_general_notifications_on') {
                          $members = $this->Members_Model->get_active_general_members_for_notifications("on");
                        } else {
                          if($m['nMembers'] == 'approved') {
                            $members = $this->Members_Model->get_approved_members_for_notifications();
                          } else {
                            if($m['nMembers'] == 'pending') {
                              $members = $this->Members_Model->get_pending_members_for_notifications();
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

        //Total member "pool" being considered in this notification
        $emailData['membersSize'] = sizeof($members);
        $adminEmailData['membersSize'] = sizeof($members);

        ///////// TO DO - Add other member groups to this ///////////

        //Put the general email info used by all emails into the email data
        $emailData['greeting'] = $m['nGreeting'];
        $emailData['signature'] = $m['nSignature'];
        $emailData['button'] = $m['nButton'];
        $emailData['emailSubject'] = $m['nSubject'];
        $emailData['banner'] = $m['nBanner'];
        $emailData['headline'] = str_replace(' ', '%20', $m['nHeadline']);

        /// CHECK NOTIFICATION TYPE
        $emailData['updateType'] = $m['nType'];

        ///////   If type = ADMIN CUSTOM
        if($m['nType'] == 'admin_custom') {

          //don't need to get any market activity data, since this is a custom email and we have the data already
          $emailData['customBody'] = $m['nCustomBody'];
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Custom Notification Sent';
          $adminEmailData['type'] = 'Custom Admin Email';

        }
        /*
        ///////   If type = NEW CREDIT LISTED
        if($m['nType']=='credit_listed') {

          //get credit info
          $emailData['credit'] = $this->CreditListings->get_active_listing($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - New Credit Notification Sent';
          $adminEmailData['type'] = 'New Credit Notification';

        }
        ///////   If type = CREDIT MODIFIED
        if($m['nType']=='credit_modified') {

          //get credit info
          $emailData['credit'] = $this->CreditListings->get_active_listing($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Credit Modified Notification Sent';
          $adminEmailData['type'] = 'Credit Modified Notification';

        }
        ///////   If type = CREDIT UNLISTED
        if($m['nType']=='credit_unlisted') {

          //get credit info
          $emailData['credit'] = $this->CreditListings->get_credit_private($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Credit Removed Notification Sent';
          $adminEmailData['type'] = 'Credit Unlisted Notification';

        }
        ///////   If type = CREDIT CANCELED
        if($m['nType']=='credit_cancelled') {

          //get credit info
          $emailData['credit'] = $this->CreditListings->get_credit_private($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Credit Cancelled Notification Sent';
          $adminEmailData['type'] = 'Credit Cancelled Notification';

        }
        ///////   If type = BID ON CREDIT
        if($m['nType']=='bid_listed') {

          //get credit info
          $emailData['bid'] = $this->BidMarket->get_bid_by_id($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - New Bid Notification Sent';
          $adminEmailData['type'] = 'New Bid Notification';

        }
        ///////   If type = OPEN BID
        if($m['nType']=='openbid_listed') {

          //get open bid info
          $emailData['bid'] = $this->BidMarket->get_openbid_by_id($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - New Bid Notification Sent';
          $adminEmailData['type'] = 'New Bid Notification';

        }
        ///////   If type = MODIFIED BID ON CREDIT
        if($m['nType']=='bid_modified') {

          //get credit info
          $emailData['bid'] = $this->BidMarket->get_bid_by_id($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Bid Modified Notification Sent';
          $adminEmailData['type'] = 'Bid Modified Notification';

        }
        ///////   If type = DELETED BID ON CREDIT
        if($m['nType']=='bid_deleted') {

          //get credit info
          $emailData['bid'] = $this->BidMarket->get_bid_by_id($m['nActivityId']);
          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Bid Deleted Notification Sent';
          $adminEmailData['type'] = 'Bid Deleted Notification';

        }
        ///////   If type = TRADE
        if($m['nType']=='trade') {

          //get open bid info
          $emailData['trade'] = $this->Trading->get_trade($m['nActivityId']);
          $emailData['trade'] = $emailData['trade']['trade'];

          //Admin email
          $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Trade Notification Sent';
          $adminEmailData['type'] = 'Trade Notification';

        }
        */

        //loop through members selected
        foreach($members as $mem) {

          //Add specific details
          $emailData['firstName'] = $mem['firstName'];
          $emailData['userId'] = $mem['userId'];

          //Ignore notification settings if on a member depending on (1) the Message Type and (2) if this is an admin preview
          if($m['nType'] == 'admin_custom' || ($adminId != "" && $adminId != 0)) {

            if($testBlock == "") {
              $this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $mem['email'], $emailData);
            }

            //Increment the member count
            $adminEmailData['memberSentCount']++;
            echo '(' . $adminEmailData['memberSentCount'] . ') ' . $mem['email'] . '<br>';

          } else {

            ///////
            ///////   CHECK MEMBER NOTIFICATION SETTINGS
            ///////

            //Prepare member's notification settings to be processed
            if($mem['nsUserId'] != "") {
              $nsActivity = explode(',', $mem['nsActivity']);
              $nsStatesCodes = explode(',', $mem['nsStatesCodes']);
              $nsFrequency = $mem['nsFrequency'];
              $nsPass = 'no';
            } else {

              //////////////  PLEASE NOTE - the default is currenlty "Weekly", so we put $nsPass as "no". If default goes back to realtime, switch to "yes" and frequency back to "realtime"
              $nsPass = 'no';
              $nsActivity = [];
              $nsStatesCodes = [];
              $nsFrequency = "weekly";
              $mem['nsMinimum'] = 1;
              $mem['nsMinimumAmount'] = 100000;
            }

            ///////
            ///////   IF CREDIT ACTIVITY
            ///////
            if($m['nType'] == 'credit_listed' || $m['nType'] == 'credit_modified' || $m['nType'] == 'credit_unlisted' || $m['nType'] == 'credit_cancelled') {

              //If credits activity is in NS... or if NS is NOT set (include in both situations)
              if($nsPass == 'yes' || (in_array('new_credit', $nsActivity) && $nsFrequency == 'realtime')) {

                $creditData = $emailData['credit'];

                //check minimum amount threshold set by user
                if($creditData['offerSize'] >= $mem['nsMinimumAmount'] || $mem['nsMinimum'] == 0) {
                  $checkSize = true;
                } else {
                  $checkSize = false;
                }

                //check jurisdiction set by user
                if($nsPass == 'yes' || in_array($creditData['state'], $nsStatesCodes)) {
                  $checkJurisdiction = true;
                } else {
                  $checkJurisdiction = false;
                }

                //If all is TRUE, then send notification
                if($checkSize && $checkJurisdiction) {
                  //send email
                  //if($mem['email']=='nnamikas@gmail.com') {
                  if($testBlock == "") {
                    //BLOCK THIS TYPE FOR NOW (ALL MARKETPLACE STUFF)
                    //$this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $mem['email'], $emailData);
                  }
                  //}

                  //Increment the member count
                  $adminEmailData['memberSentCount']++;
                  echo '(' . $adminEmailData['memberSentCount'] . ') ' . $mem['email'] . '<br>';

                }

              }

            }

            ///////
            ///////   IF BID ACTIVITY
            ///////
            if($m['nType'] == 'bid_listed' || $m['nType'] == 'openbid_listed' || $m['nType'] == 'bid_modified' || $m['nType'] == 'bid_deleted') {

              //If bid activity is in NS... or if NS is NOT set (include in both situations)
              if($nsPass == 'yes' || (in_array('new_bid', $nsActivity) && $nsFrequency == 'realtime')) {

                $bidData = $emailData['bid'];

                //check minimum amount threshold set by user
                if($bidData['bidSize'] >= $mem['nsMinimumAmount'] || $mem['nsMinimum'] == 0) {
                  $checkSize = true;
                } else {
                  $checkSize = false;
                }

                //check jurisdiction set by user
                if($nsPass == 'yes' || in_array($bidData['state'], $nsStatesCodes)) {
                  $checkJurisdiction = true;
                } else {
                  $checkJurisdiction = false;
                }

                //If all is TRUE, then send notification
                if($checkSize && $checkJurisdiction) {
                  //send email
                  //if($mem['email']=='nnamikas@gmail.com') {
                  if($testBlock == "") {
                    //BLOCK THIS TYPE FOR NOW (ALL MARKETPLACE STUFF)
                    //$this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $mem['email'], $emailData);
                  }
                  //}

                  //Increment the member count
                  $adminEmailData['memberSentCount']++;
                  echo '(' . $adminEmailData['memberSentCount'] . ') ' . $mem['email'] . '<br>';

                }

              }

            }

            ///////
            ///////   IF TRADE ACTIVITY
            ///////
            if($m['nType'] == 'trade') {

              //If trade activity is in NS... or if NS is NOT set (include in both situations)
              if($nsPass == 'yes' || (in_array('new_trade', $nsActivity) && $nsFrequency == 'realtime')) {

                $tradeData = $emailData['trade'];

                //check minimum amount threshold set by user
                if($tradeData['tradeSize'] >= $mem['nsMinimumAmount'] || $mem['nsMinimum'] == 0) {
                  $checkSize = true;
                } else {
                  $checkSize = false;
                }

                //check jurisdiction set by user
                if($nsPass == 'yes' || in_array($tradeData['state'], $nsStatesCodes)) {
                  $checkJurisdiction = true;
                } else {
                  $checkJurisdiction = false;
                }

                //If all is TRUE, then send notification
                if($checkSize && $checkJurisdiction) {
                  //send email
                  //if($mem['email']=='nnamikas@gmail.com') {
                  if($testBlock == "") {
                    //BLOCK THIS TYPE FOR NOW (ALL MARKETPLACE STUFF)
                    //$this->Email_Model->_send_email('member_realtime_template_1', $emailData['emailSubject'], $mem['email'], $emailData);
                  }
                  //}

                  //Increment the member count
                  $adminEmailData['memberSentCount']++;
                  echo '(' . $adminEmailData['memberSentCount'] . ') ' . $mem['email'] . '<br>';

                }

              }

            }

          }

        }

        if($adminId == "") {
          //Update finish time and status to 2
          $this->Members_Model->update_notification_to_finished($m['nId'], $adminEmailData['memberSentCount'], $adminEmailData['memberNotSentCount'], $emailData['membersSize']);
        }

        //Collect some more admin report data regarding start/finish time of script
        $adminEmailData['timeFinish'] = time();
        echo '<br><br>Started - ' . date('h:i:s', $adminEmailData['timeStart']) . '<br>';
        echo 'Finished - ' . date('h:i:s', $adminEmailData['timeFinish']) . '<br><br><br><br><br>';

        //Send report to OIX Admin
        $adminEmailData['updateType'] = 'oix_admin_report_realtime_notifications';
        $adminEmailData['banner'] = "";
        if($testBlock == "") {
          $this->Email_Model->_send_email('oix_admin_update', $adminEmailData['emailSubject'], $this->config->item("oix_dev_emails_array"), $adminEmailData);
        }

      }

      echo '<pre>';
      //var_dump($messages);
      echo '</pre>';
    }

    //After everything is done, trigger the sale folder function (we do this so we only have one cron looping every 4 mintues and to these frequent organize tasks so they happen sequentially)
    //$this->createSaleTransactionFolders("98f97s9a8f8f");

  }

  function generate_folders($uniqueId = "", $limit = "") {
    $this->logger->info('SCHEDULED TASK: GENERATE BOX FOLDERS Started with uniqueId: ' . $uniqueId);
    //throw new \Exception('General fail');

    //If message ID exists...
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    //Get trades where folders have not yet been created
    $tRequest['limit'] = $limit;
    $tradesNoFolders = $this->Trading->get_pending_trades_no_folders($tRequest);

    $this->logger->info('SCHEDULED TASK: GENERATE BOX FOLDERS looping over # of folders: ' . count($tradesNoFolders));
    //Loop through those Trades
    foreach($tradesNoFolders as $tnf) {

      //get transactions of trade
      $transactionIds = [];
      $transactionIds = $this->Trades->get_transaction_ids_of_trade($tnf['tradeId']);

      $issueId = $tnf['State'] . $tnf['listingId'];

      //Mark trade folders as in process of being created
      $this->Trading->mark_trade_folders_status($tnf['tradeId'], 2);

      //Create the folders
      $this->filemanagementlib->createBoxFolders($issueId, $tnf['listingId'], $tnf['tradeId'], $transactionIds, 2);

      //Mark trade folders as complete
      $this->Trading->mark_trade_folders_status($tnf['tradeId'], 1);

    }

    $this->logger->info('SCHEDULED TASK: GENERATE BOX FOLDERS has completed');
    echo json_encode(['success' => true]);
  }

  function update_all_calendar_alerts($uniqueId = "") {

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > Cal Alert > Started');

    //If message ID exists... this is also used in the second function call at the bottom of this function
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    //STEP 1 - EXPIRE ANY "TODAY" WARNINGS FROM YESTERDAY

    //Update all expired calendar alerts
    echo "<br>Expire Past Alerts Check:<br><br>";

    // get all alert messages that have gone over 24 hours since created and have not been marked reviewed
    $mSearchRequest['msType'] = "calendar_alert";
    $mSearchRequest['msContentNotEqual'] = 'expired';
    $today = strtotime('00:00:00');
    $mSearchRequest['msYear'] = date('Y', $today);
    $mSearchRequest['msMonth'] = date('n', $today);
    $mSearchRequest['msDay'] = date('j', $today);
    //$mSearchRequest['endTime'] = time()-115000; //Only return items created BEFORE the LAST 32 HOURS (these are the ones that have expired)
    $mSearchRequest['distinctMsId'] = 1;
    $messages = $this->Members_Model->search_messages($mSearchRequest);

    // loop through them and modify them (remove "1 Day Alert:" and then update msContent to 'expired')
    foreach($messages as $m) {
      $this->Members_Model->mark_message_expired($m['msId']);
    }

    //STEP 2 - UPDATE/CREATE ALERTS WITHIN NEXT 0-5 DAYS

    //Get all credits
    $allListingIds = $this->CreditListings->get_all_credits_minimum();

    foreach($allListingIds as $listing) {
      if($listing['num_trades'] > 0) {
        echo $listing['listingId'] . " - " . $listing['num_trades'] . "<br>";
      }
    }

    echo "Calendar Alerts Check:<br>";

    //Loop through credits
    foreach($allListingIds as $listing) {

      $listingId = $listing['listingId'];

      //Run the function to update the calendar alerts for this credit's details
      $this->CreditListings->check_credit_for_due_dates($listingId);

      //Run the function to update the calender alerts for this credit's workflow items
      if($listing['cWorkflowId'] > 0) {
        $this->Workflow->check_workflow_items_for_due_dates($listingId);
      }

      //Get trade IDs on this credit
      if($listing['num_trades'] > 0) {
        $thisTradeIds = $this->CreditListings->get_trade_ids_on_credit($listingId);
        //Loop through trades
        foreach($thisTradeIds as $tradeId) {
          //Check for estimated utilization dates
          $this->CreditListings->check_utilization_for_due_dates($tradeId);
        }
      }

      //Check for Compliance Reminder Alerts
      if($listing['cComplianceId'] > 0) {
        $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > Cal Alert > Compliance Alert > Listing ID: ' . $listing['listingId']);
        $this->CreditListings->check_compliance_items_for_reminder_dates($listingId);
      }

      $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > Cal Alert > Listing ID: ' . $listing['listingId']);

      echo $listingId . "<br>";

    }

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > Compliance Alert > Done');

  }

  function member_daily_alerts_summary($uniqueId = "", $emailBlock = "", $eventAlertBlock = "") {
    $now = new \DateTime();
    if($this->redisIsAvailable === true) {
      $lockResult = $this->redis->setnx('member_daily_alerts_summary:LOCK', $now->format('Y-m-d H:i:s'));
      if($lockResult === 0) {
        echo 'Process is already started, please try again later or release the lock `member_daily_alerts_summary:LOCK`';
        exit;
      }
      $this->redis->expire('member_daily_alerts_summary:LOCK', 60 * 60 * 3); //set lock expire for +3 hours
    }
    ini_set('max_execution_time', 0);
    set_time_limit(0); //¯\_(ツ)_/¯ This is UGLY, but this currently takes 30 minutes or more to run in production
    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY Started with uniqueId: ' . $uniqueId);
    //If message ID exists... this is also used in the second function call at the bottom of this function
    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    $adminEmailData['timeStart'] = time();
    $adminEmailData['memberSentCount'] = 0;
    $adminEmailData['memberNotSentCount'] = 0;
    $adminEmailData['membersSize'] = sizeof($members);
    echo $adminEmailData['timeStart'];

    if($eventAlertBlock != 1) {
      $this->update_all_calendar_alerts($uniqueId);
    }

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY > got to get_active_members_for_notifications');

    //Get members who have alert summary notification turned ON or if no notifications set at all (default)
    $members = $this->Members_Model->get_active_members_for_daily_alerts();
    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY now considering # members: ' . count($members));

    echo "<br>Daily Alerts:<br>";
    echo sizeof($members) . " Users Considered<br>";

    //loop through members selected
    foreach($members as $mem) {
      //Add specific details
      $emailData['firstName'] = $mem['firstName'];
      $emailData['userId'] = $mem['userId'];

      $alertsArray = [];
      $dateAlertsArray = [];

      $activityData['userId'] = $mem['userId'];
      $activityData['msTypeExcludeArray'] = ["calendar_alert", "alert"];
      $nonDateAlerts = $this->Members_Model->get_messages_of_user_lite($activityData);

      $calData['userId'] = $mem['userId'];
      $calData['msTypeOnly'] = ["calendar_alert"];
      $calData['order'] = "alertAsc";
      $calData['msContentExcludeArray'] = ["expired"];
      $dateAlerts = $this->Members_Model->get_messages_of_user_lite($calData);

      echo sizeof($nonDateAlerts) . "<br>";
      echo sizeof($dateAlerts) . "<br>";

      foreach($nonDateAlerts as $al) {

        //If this member is NOT a member of a parent DMA -- or if he is, and the message DMA ID matches his parent DMA Id, then allow through (the purpose is to block a parent from considering children messages to avoid multiple messages on same thing)
        if(!$mem['dmaAccounts']['parentDmaFlag'] || ($mem['dmaAccounts']['parentDmaFlag'] && $mem['dmaAccounts']['parentDmaId'] == $al['mmDmaId'])) {

          if(array_key_exists($al['msListingId'], $alertsArray)) {
            array_push($alertsArray[$al['msListingId']], $al);
          } else {
            $alertsArray[$al['msListingId']] = [];
            array_push($alertsArray[$al['msListingId']], $al);
          }

        }

      }

      foreach($dateAlerts as $al) {
        //If this member is NOT a member of a parent DMA -- or if he is, and the message DMA ID matches his parent DMA Id, then allow through (the purpose is to block a parent from considering children messages to avoid multiple messages on same thing)
        if(!$mem['dmaAccounts']['parentDmaFlag'] || ($mem['dmaAccounts']['parentDmaFlag'] && $mem['dmaAccounts']['parentDmaId'] == $al['mmDmaId'])) {
          array_push($dateAlertsArray, $al);
        }
      }

      $dateWarningText = (sizeof($dateAlertsArray) > 0) ? (sizeof($dateAlertsArray) == 1 ? " - 1 Date Alert" : " - " . sizeof($dateAlertsArray) . " Date Alerts") : "";
      $emailData['emailSubject'] = "Activity Updates" . $dateWarningText;
      $emailData['headline'] = 'Activity%20Updates';
      $emailData['welcomeNameTemplate'] = 1;
      $emailData['button'] = 1;
      $emailData['updateType'] = "myActivitySummary";

      $emailData['alertsArray'] = $alertsArray;
      $emailData['dateAlerts'] = $dateAlertsArray;
      $totalAlertSize = sizeof($emailData['alertsArray']) + sizeof($emailData['dateAlerts']);

      //If test block is OFF and there are alerts to share
      if($emailBlock != 1 && $totalAlertSize > 0) {
        echo $mem['email'] . " - " . $totalAlertSize . "<br>";
        $adminEmailData['memberSentCount']++;
        $this->Email_Model->_send_email('member_template_1', $emailData['emailSubject'], $mem['email'], $emailData);
        if($this->config->item('environment') != "DEV") {
          $this->Email_Model->_send_email('member_template_1', $emailData['emailSubject'], "nnamikas+oix@gmail.com", $emailData);
        }
        //If test block is ON and there are alerts to share
      } else {
        if($emailBlock == 1 && $totalAlertSize > 0) {
          echo "Block - " . $mem['email'] . " - " . $totalAlertSize . "<br>";
          $this->Email_Model->_send_email('member_template_1', $emailData['emailSubject'], "nnamikas+oix@gmail.com", $emailData);
        } else {
          $adminEmailData['memberNotSentCount']++;
        }
      }

    }
    //End of member loop

    //Send report to OIX Admin
    //Collect some more admin report data regarding start/finish time of script
    $adminEmailData['timeFinish'] = time();
    echo '<br><br>Started - ' . date('h:i:s', $adminEmailData['timeStart']) . '<br>';
    echo 'Finished - ' . date('h:i:s', $adminEmailData['timeFinish']) . '<br><br><br><br><br>';
    $adminEmailData['updateType'] = 'oix_admin_report_member_daily_alerts_summary';
    $adminEmailData['type'] = "Member Daily Alerts Summary";
    $adminEmailData['emailSubject'] = 'OIX Admin Confirmation - Daily Member Alerts Summaries Sent';
    //Send admin email
    $this->Email_Model->_send_email('oix_admin_update', $adminEmailData['emailSubject'], $this->config->item("oix_dev_emails_array"), $adminEmailData);

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY now running daily password check: ');
    /* THEN RUN DAILY PASSWORD CHECK
    - we're doing this within this function since this is already on a 24 hour cron
    */
    $this->dailyPasswordSecurityChecks($uniqueId);

    if($this->redisIsAvailable === true) {
      $this->redis->del(['member_daily_alerts_summary:LOCK']);
    }

    $this->logger->info('SCHEDULED TASK: MEMBER DAILY ALERTS SUMMARY daily password check now complete: ');
  }

  function dailyPasswordSecurityChecks($uniqueId) {
    $this->logger->info('SCHEDULED TASK: DAILY PASSWORD SECURITY CHECKS started for uniqueId: ' . $uniqueId);

    if($uniqueId != "98f97s9a8f8f") {
      echo "no permission";
      throw new \Exception('General fail');
    }

    //FIRST - check password expirations
    $membersWithPWResetDays = $this->Members_Model->get_members_with_pw_expiration_limits();
    $this->logger->info('SCHEDULED TASK: DAILY PASSWORD SECURITY CHECKS looping # password expirations: ' . count($membersWithPWResetDays));
    foreach($membersWithPWResetDays as $mrd) {
      //If last password change is greater than the num of days allowed (86400 seconds per day)
      $secondsAllowed = $mrd['pwResetDaysReq'] * 86400;
      $latestPWchangeDate = ($mrd['latestPWchange']['pwrDate'] > 0) ? $mrd['latestPWchange']['pwrDate'] : 0;
      if(time() - $latestPWchangeDate > $secondsAllowed) {
        //Give the user a flag to reset password
        $this->Members_Model->add_password_reset_flag($mrd['userId'], 4);
      }
    }

    //SECOND - check account inactivity
    $membersWithInactivityLimits = $this->Members_Model->get_members_with_inactivity_limits();
    $this->logger->info('SCHEDULED TASK: DAILY PASSWORD SECURITY CHECKS looping # inactive: ' . count($membersWithInactivityLimits));
    foreach($membersWithInactivityLimits as $mil) {
      //If last password change is greater than the num of days allowed (86400 seconds per day)
      $lastLogin = strtotime($mil['last_login']);
      $sinceLastLogin = time() - $lastLogin;
      $daysAllowed = ($mil['pwResetDaysReq'] > 0) ? $mil['pwResetDaysReq'] : 0;
      $secondsAllowed = $daysAllowed * 86400;

      if($sinceLastLogin > $secondsAllowed) {
        //Get user info with all Admin Userships
        $userData = $this->Members_Model->get_member_by_id($mil['userId']);
        //Loop through DMA accounts and mark each as archived
        foreach($userData['dmaAccounts']['all'] as $dma) {
          $this->Members_Model->update_dma_member_status($dma['dmaMemberId'], 0);
        }
      }
    }

    $this->logger->info('SCHEDULED TASK: DAILY PASSWORD SECURITY CHECKS completed');
  }

  function closingPendingActionsEmail($transactionEmailBuyer, $closingProcessStartDate, $tpReminderSignature, $tpReminderPayment, $emailBlock, $sendNick, $nickEmail) {

    throw new \Exception('General fail');

    $tpReminderEmail = $transactionEmailBuyer;
    $tpReminderEmail['tpReminderSignature'] = $tpReminderSignature;
    $tpReminderEmail['tpReminderPayment'] = $tpReminderPayment;

    if($tpReminderEmail['tpReminderSignature'] == 1) {
      //Get invite
      $signatureInvite = $this->Members_Model->get_invite_signature_by_email($tpReminderEmail['email'], $tpReminderEmail['transactionId']);
      $tpReminderEmail['iHash'] = $signatureInvite['iHash'];
    }

    //Prepare time frames
    $time_now = time();
    $time_since = $time_now - $closingProcessStartDate;
    $daysSinceClosing = ceil($time_since / (60 * 60 * 24));

    $tpReminderEmail['updateType'] = "";
    $emailSubjectEnd = "";

    //////////  8th Day  - expired  //////////
    if($daysSinceClosing == 8) {
      $tpReminderEmail['updateType'] = 'signature_fourth_reminder';
      $emailSubjectEnd = 'Past Due';
    }

    //////////  7th Day  - expires today  //////////
    if($daysSinceClosing == 7) {
      $tpReminderEmail['updateType'] = 'signature_third_reminder';
      $emailSubjectEnd = 'Due Today';
    }

    //////////  6th Day - expired tomorrow  //////////
    if($daysSinceClosing == 6) {
      $tpReminderEmail['updateType'] = 'signature_second_reminder';
      $emailSubjectEnd = 'Due Tomorrow';
    }

    //////////  3rd Day  - expires soon  //////////
    if($daysSinceClosing == 3) {
      $tpReminderEmail['updateType'] = 'signature_first_reminder';
      $emailSubjectEnd = 'Required';
    }

    //Send email
    if($tpReminderEmail['updateType'] != "") {

      //Generic email settings
      $tpReminderEmail['updateDescription'] = '';
      $tpReminderEmail['welcomeNameTemplate'] = 1;
      $tpReminderEmail['button'] = 0;

      $taxpayerReminders = $tpReminderSignature + $tpReminderPayment;
      $itemsDueText = "";
      if($taxpayerReminders == 2) {
        $itemsDueText = "Signature and Payment are";
      } else {
        if($tpReminderSignature == 1) {
          $itemsDueText = "Signature is";
        }
        if($tpReminderPayment == 1) {
          $itemsDueText = "Payment is";
        }
      }
      $tpReminderEmail['headline'] = "Your " . $itemsDueText . " " . $emailSubjectEnd;
      $tpReminderEmail['taxpayerReminders'] = $taxpayerReminders;

      if(!$emailBlock) {
        $this->Email_Model->_send_email('closing_updates', $tpReminderEmail['headline'] . ' - Purchase of ' . $tpReminderEmail['taxYear'] . ' ' . $tpReminderEmail['stateName'] . ' Tax Credits', $tpReminderEmail['email'], $tpReminderEmail);
        $this->Email_Model->_send_email('closing_updates', 'N - ' . $tpReminderEmail['headline'] . ' - Purchase of ' . $tpReminderEmail['taxYear'] . ' ' . $tpReminderEmail['stateName'] . ' Tax Credits', 'nnamikas+oix@gmail.com', $tpReminderEmail);
      } else {
        if($sendNick) {
          $this->Email_Model->_send_email('closing_updates', 'N - ' . $tpReminderEmail['headline'] . ' - Purchase of ' . $tpReminderEmail['taxYear'] . ' ' . $tpReminderEmail['stateName'] . ' Tax Credits', $nickEmail, $tpReminderEmail);
        }
      }

    }

  }

  function closing_updates($updateFlag) {

    throw new \Exception('General fail');

    //Actionable = this email notification runs at 8am EST each day

    //SUMMARY OF THIS SCRIPT
    //This script sends the following updates during the closing process: (i) seller has signed, sent to buyer, (ii) buyer has paid, sent to seller, buyer and taxpayer and (iii) closing process has completed on a transaction, sent to seller, buyer and taxpayer

    //STEP BY STEP OF THIS SCRIPT
    //This script first checks to make sure the $updateFlag is 1, or kills the script (or if it's 2, then it runs an admin print on the page without sending emails)
    //Gets ALL trades currently in the closing process (status=1)
    //LOOP - through each trade
    //generate empty array each loop for updates on buyer and seller
    //get more data: (i) transactions, (ii) listing data, (iii) main seller (gen member or DMA of legal entities) and (iv) main seller (gen member or DMA of legal entities)
    //get DMA account info for the main buyer/seller, and if non-existent, then use the general member info as the account info
    //assign core email variables for buyer and seller
    //if a SELLER legal entity exists, then get that person's information
    //***IF seller signed, then add to the buyer update array
    //LOOP - through each transaction of the sale
    //generate two empty update arrays each loop for buyer taxpayer: (i) payment received and (ii) closing process complete
    //setup transaction data for: (i) main buyer/seller and (ii) buyer legal entity
    //***IF buyer has PAID in the transaction: (i) add to main seller's update array, (ii) add to main buyer's update array, (iii) IF legal entity email exists, send an email notification to the legal entity
    //***IF transaction has COMPLETED: (i) add to main seller's update array, (ii) add to main buyer's update array, (iii) IF legal entity email exists, send an email notification to the legal entity
    //End of loop, IF the legal entity has an email address then increment the legal entity count variable
    //SEND MAIN SELLER EMAILS
    //IF there are seller updates, then send main seller an email aggregation of this data
    //IF there are any transactions which have completed, then send main seller an email aggregation of this data
    //SEND MAIN BUYER EMAILS
    //IF there are buyer updates, then send main buyer an email aggregation of this data
    //IF there are any transactions which have completed, then send main buyer an email aggregation of this data

    //TO BE DONE
    //Schedule
    //once every morning as a 24 hour check
    //Main Buyer Email
    //If legal entity email address increment is not equal to the total transaction count, then show text describing emails being sent to those people and closing process
    //Rework status updates into a better format?
    //add grid showing status of every transaction
    //include payment info at bottom of email
    //Main Seller Email
    //Rework status updates into a better format?
    //add grid showing status of every transaction

    //////////////////////////
    //////  SCRIPT PREP  /////
    //////////////////////////

    //Match the time to the crontab schedule
    $currTimestamp = time();
    //Set the "since" date to something insanely high as a backup to the backup  (so if not reset below, no updates are sent)
    $sinceTimestamp = 99999999999999999;
    //Get the time since based on the updateFlag
    //1 = 8am ET every day (24 hours so 86400)
    if($updateFlag == 1) {
      $sinceTimestamp = $currTimestamp - 86400;
      $emailBlock = false;
      $sendNick = false;
      $nickEmail = "";
    } else {
      if($updateFlag == 2) {
        $sinceTimestamp = $currTimestamp - 86400;
        $emailBlock = true;
        $sendNick = false;
        $nickEmail = '';
      } else {
        if($updateFlag == 4242) {
          $sinceTimestamp = $currTimestamp - 86400;
          $emailBlock = true;
          $sendNick = true;
          $nickEmail = 'nnamikas@gmail.com';
        } else {
          echo "blocked";
          $emailBlock = true;
          throw new \Exception('General fail');
        }
      }
    }

    //////////////////////////
    //////   GET DATA   //////
    //////////////////////////

    //Get any sales that have been updated since last crontab check
    //$salesUpdated = $this->Trades->get_all_recent_updated_sales($sinceTimestamp);
    $salesUpdated = $this->Trades->get_all_trades_in_closing_process();

    //Create array for main seller and buyer
    $mainSellersEmails = [];
    $mainBuyersEmails = [];

    //Default set multibuyer to false
    $multibuyer = false;

    ///////////////////////////
    //////   LOOP SALES  //////
    ///////////////////////////

    //Loop through these sales
    foreach($salesUpdated as $su) {

      //Going to group updates into one email
      $dataEmailSeller['updates'] = [];
      $dataEmailBuyer['updates'] = [];
      //Going to send the completed step in a separate email
      //$dataEmailSeller['completed'] = array();
      //$dataEmailBuyer['completed'] = array();

      //get data on this sale
      $transactions = $this->Trading->get_transactions_of_trade($su['tradeId']);
      $listing = $this->CreditListings->get_active_listing($su['listingId']);
      $mainSeller = $this->Members_Model->get_member_by_id($listing['listedBy']);
      $mainBuyer = $this->Members_Model->get_member_by_id($su['BaccountId']);

      //Get buyer info
      //Get DMA info for buyer and seller
      $dmaSeller = [];
      $dmaBuyer = [];

      //Get email address and user ID for buyer and seller
      //If a DMA seller
      if($dmaSeller != "") {
        $sellerInfo = $this->Members_Model->get_member_by_id($su['cDmaMemberId']);
        $dataEmailSeller['dmaTitle'] = $dmaSeller['title'];
        $dataEmailSeller['sellerType'] = 'dma';
      } else {
        //If a general member seller
        $sellerInfo = $this->Members_Model->get_member_by_id($su['listedBy']);
        $dataEmailSeller['dmaTitle'] = '';
        $dataEmailSeller['sellerType'] = 'general';
      }

      //If a DMA buyer
      if(sizeof($dmaBuyer) > 0) {
        $buyerInfo = $this->Members_Model->get_member_by_id($su['bDmaMemberId']);
        $dataEmailBuyer['dmaTitle'] = $dmaBuyer['title'];
        $dataEmailBuyer['sellerType'] = 'dma';
      } else {
        //If a general member buyer
        $buyerInfo = $this->Members_Model->get_member_by_id($su['BaccountId']);
        $dataEmailBuyer['dmaTitle'] = '';
        $dataEmailBuyer['sellerType'] = 'general';
      }

      ///////////////////////////////////////////
      ///// SELLER - start building data  ///////
      ///////////////////////////////////////////

      //IF MAIN SELLER is not already in the main seller email array... set up the data
      if(!array_key_exists($sellerInfo['userId'], $mainSellersEmails)) {

        $mainSellersEmails[$sellerInfo['userId']]['perspective'] = "seller";
        $mainSellersEmails[$sellerInfo['userId']]['userId'] = $sellerInfo['userId'];
        $mainSellersEmails[$sellerInfo['userId']]['firstName'] = $sellerInfo['firstName'];
        $mainSellersEmails[$sellerInfo['userId']]['lastName'] = $sellerInfo['lastName'];
        $mainSellersEmails[$sellerInfo['userId']]['email'] = $sellerInfo['email'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'] = [];
        $mainSellersEmails[$sellerInfo['userId']]['updates']['signed'] = [];
        $mainSellersEmails[$sellerInfo['userId']]['updates']['paid'] = [];
        $mainSellersEmails[$sellerInfo['userId']]['updates']['completed'] = [];
        $mainSellersEmails[$sellerInfo['userId']]['updates']['totalCount'] = 0;

      }

      $thisCreditArray = $mainSellersEmails[$sellerInfo['userId']]['credits'];
      if(!array_key_exists($su['listingId'], $thisCreditArray)) {

        //Create a new array for this listing where this and other sales on this credit can be stored
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'] = [];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['listingId'] = $su['listingId'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['State'] = $su['State'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['stateCertNum'] = $su['stateCertNum'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['projectNameExt'] = $su['projectNameExt'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['ProgramName'] = $su['ProgramName'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['taxYear'] = $su['taxYear'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['name'] = $su['name'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['offerSize'] = $su['offerSize'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['pendingTransactionsInCreditCount'] = 0;

      }

      $thisSaleArray = $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'];
      if(!array_key_exists($su['tradeId'], $thisSaleArray)) {

        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['State'] = $su['State'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['listingId'] = $su['listingId'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['tradeId'] = $su['tradeId'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['size'] = $su['size'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['price'] = $su['price'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['tradeIdFull'] = $su['State'] . $su['listingId'] . '-' . $su['tradeId'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['stateCertNum'] = $su['stateCertNum'];
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['projectNameExt'] = $su['projectNameExt'];
        //$mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'] = $transactions;
        $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'] = [];

      }

      ///////////////////////////////////////////
      ///// BUYER - start building data  ///////
      ///////////////////////////////////////////

      //IF MAIN BUYER is not already in the main buyer email array... set up the data
      if(!array_key_exists($buyerInfo['userId'], $mainBuyersEmails)) {

        $mainBuyersEmails[$buyerInfo['userId']]['perspective'] = "buyer";
        $mainBuyersEmails[$buyerInfo['userId']]['userId'] = $buyerInfo['userId'];
        $mainBuyersEmails[$buyerInfo['userId']]['firstName'] = $buyerInfo['firstName'];
        $mainBuyersEmails[$buyerInfo['userId']]['lastName'] = $buyerInfo['lastName'];
        $mainBuyersEmails[$buyerInfo['userId']]['email'] = $buyerInfo['email'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'] = [];
        $mainBuyersEmails[$buyerInfo['userId']]['updates']['signed'] = [];
        $mainBuyersEmails[$buyerInfo['userId']]['updates']['paid'] = [];
        $mainBuyersEmails[$buyerInfo['userId']]['updates']['completed'] = [];
        $mainBuyersEmails[$buyerInfo['userId']]['updates']['totalCount'] = 0;
        //Unique to buyer
        $mainBuyersEmails[$buyerInfo['userId']]['updates']['signaturesPending'] = 0;
        $mainBuyersEmails[$buyerInfo['userId']]['updates']['paymentsPending'] = 0;
        $mainBuyersEmails[$buyerInfo['userId']]['pendingTransactionsCount'] = 0;

      }

      $thisCreditArray = $mainBuyersEmails[$buyerInfo['userId']]['credits'];
      if(!array_key_exists($su['listingId'], $thisCreditArray)) {

        //Create a new array for this listing where this and other sales on this credit can be stored
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'] = [];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['listingId'] = $su['listingId'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['State'] = $su['State'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['stateCertNum'] = $su['stateCertNum'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['projectNameExt'] = $su['projectNameExt'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['ProgramName'] = $su['ProgramName'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['taxYear'] = $su['taxYear'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['name'] = $su['name'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['offerSize'] = $su['offerSize'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['pendingTransactionsInCreditCount'] = 0;

      }

      $thisSaleArray = $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'];
      if(!array_key_exists($su['tradeId'], $thisSaleArray)) {

        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['State'] = $su['State'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['listingId'] = $su['listingId'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['tradeId'] = $su['tradeId'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['size'] = $su['size'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['price'] = $su['price'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['tradeIdFull'] = $su['State'] . $su['listingId'] . '-' . $su['tradeId'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['stateCertNum'] = $su['stateCertNum'];
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['projectNameExt'] = $su['projectNameExt'];
        //$mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'] = $transactions;
        $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'] = [];

      }

      //Get seller taxpayer info
      if($su['cTaxpayerId'] != "") {
        $sTaxpayer = $this->Taxpayersdata->get_taxpayer($su['cTaxpayerId'], 0, 0);
        //Add data for confirmation email to seller
        $dataEmailSeller['seller-tpAccountType'] = $sTaxpayer['tpAccountType'];
        $dataEmailSeller['seller-tpCompanyName'] = $sTaxpayer['tpCompanyName'];
        $dataEmailSeller['seller-tpFirstName'] = $sTaxpayer['tpFirstName'];
        $dataEmailSeller['seller-tpLastName'] = $sTaxpayer['tpLastName'];
        $dataEmailSeller['seller-tpEmailSigner'] = $sTaxpayer['tpEmailSigner'];
        $dataEmailSeller['seller-tpUserIdSigner'] = $sTaxpayer['tpUserIdSigner'];
        //Also put it into the buyer email data
        $dataEmailBuyer['seller-tpAccountType'] = $sTaxpayer['tpAccountType'];
        $dataEmailBuyer['seller-tpCompanyName'] = $sTaxpayer['tpCompanyName'];
        $dataEmailBuyer['seller-tpFirstName'] = $sTaxpayer['tpFirstName'];
        $dataEmailBuyer['seller-tpLastName'] = $sTaxpayer['tpLastName'];
        $dataEmailBuyer['seller-tpEmailSigner'] = $sTaxpayer['tpEmailSigner'];
        $dataEmailBuyer['seller-tpUserIdSigner'] = $sTaxpayer['tpUserIdSigner'];
      }

      /////  UPDATE 1 - Seller Signed - Update Buyer(s) /////

      //If seller signed
      if($su['sellerSignedDate'] >= $sinceTimestamp) {

        //Add data for confirmation email
        $dataEmailSeller['seller-tpAccountType'] = $sTaxpayer['tpAccountType'];
        $dataEmailSeller['seller-tpCompanyName'] = $sTaxpayer['tpCompanyName'];
        $dataEmailSeller['seller-tpFirstName'] = $sTaxpayer['tpFirstName'];
        $dataEmailSeller['seller-tpLastName'] = $sTaxpayer['tpLastName'];

        $dataUpdate['updateType'] = 'sellerSigned';
        $dataUpdate['sellerName'] = $dataEmailSeller['dmaTitle'];

        ///// BUYER /////
        //Add an update for the seller signed
        array_push($dataEmailBuyer['updates'], $dataUpdate);

      }

      //Copy the data for buyer legal entities into a new variable so we can use it in every loop without impacting original data for later
      $transactionEmailBuyer = $dataEmailBuyer;

      ///////////////////////////////////////
      ///// CHECK TRANSACTIONS OF TRADE ///////
      ///////////////////////////////////////

      if(sizeof($transactions) > 1) {
        $multibuyer = true;
      } else {
        $multibuyer = false;
      }

      foreach($transactions as $t) {

        //Reset on each loop
        $tpReminderSignature = 0;
        $tpReminderPayment = 0;

        //Transaction info for the Main Buyer/Seller
        $dataUpdate['transactionId'] = $t['transactionId'];
        $dataUpdate['transactionIdFull'] = $su['State'] . $su['listingId'] . '-' . $su['tradeId'] . '-' . $t['transactionId'];
        $dataUpdate['tpAccountType'] = $t['tpAccountType'];
        $dataUpdate['tpEmailSigner'] = $t['tpEmailSigner'];
        $dataUpdate['tpCompanyName'] = $t['tpCompanyName'];
        $dataUpdate['tpFirstName'] = $t['tpFirstName'];
        $dataUpdate['tpLastName'] = $t['tpLastName'];
        $dataUpdate['tCreditAmount'] = $t['tCreditAmount'];
        $dataUpdate['purchasePrice'] = $t['tCreditAmount'] * $su['price'];

        //Transaction info for the Legal Entity
        $transactionEmailBuyer['perspective'] = "buyer";
        $transactionEmailBuyer['email'] = $t['tpEmailSigner'];
        $transactionEmailBuyer['tpAccountType'] = $t['tpAccountType'];
        $transactionEmailBuyer['firstName'] = $t['tpFirstName'];
        $transactionEmailBuyer['lastName'] = $t['tpLastName'];
        $transactionEmailBuyer['companyName'] = $t['tpCompanyName'];
        $transactionEmailBuyer['entityName'] = ($transactionEmailBuyer['companyName'] != "") ? $transactionEmailBuyer['companyName'] : $transactionEmailBuyer['firstName'] . " " . $transactionEmailBuyer['lastName'];
        $transactionEmailBuyer['listingId'] = $su['listingId'];
        $transactionEmailBuyer['tradeId'] = $su['tradeId'];
        $transactionEmailBuyer['transactionId'] = $t['transactionId'];
        $transactionEmailBuyer['closingProcessStartDate'] = $t['closingProcessStartDate'];
        $transactionEmailBuyer['taxYear'] = $su['taxYear'];
        $transactionEmailBuyer['State'] = $listing['State'];
        $transactionEmailBuyer['stateName'] = $su['name'];
        $transactionEmailBuyer['program'] = $listing['ProgramName'];
        $transactionEmailBuyer['project'] = $listing['stateCertNum'];
        $transactionEmailBuyer['projectNameExt'] = $listing['projectNameExt'];
        $transactionEmailBuyer['saleIdFull'] = $su['State'] . $su['listingId'] . '-' . $su['tradeId'];
        $transactionEmailBuyer['sellerCompanyName'] = $mainSeller['companyName'];
        $transactionEmailBuyer['buyerCompanyName'] = $mainBuyer['companyName'];
        $transactionEmailBuyer['sellerName'] = $mainSeller['firstName'] . ' ' . $mainSeller['lastName'];
        $transactionEmailBuyer['buyerName'] = $mainBuyer['firstName'] . ' ' . $mainBuyer['lastName'];
        $transactionEmailBuyer['size'] = $t['tCreditAmount'];
        $transactionEmailBuyer['price'] = $su['price'];
        $transactionEmailBuyer['tradeIdFull'] = $su['State'] . $su['listingId'] . '-' . $su['tradeId'];
        $transactionEmailBuyer['transactionIdFull'] = $su['State'] . $su['listingId'] . '-' . $su['tradeId'] . '-' . $t['transactionId'];
        $transactionEmailBuyer['tCreditAmount'] = $t['tCreditAmount'];
        $transactionEmailBuyer['purchasePrice'] = $t['tCreditAmount'] * $su['price'];

        ///////////////////////////////////
        /////      Buyer Signed     ///////
        ///////////////////////////////////

        //HAS signed IN time window
        if($t['buyerSignedDate'] >= $sinceTimestamp) {

          ///// MAIN SELLER /////
          array_push($mainSellersEmails[$sellerInfo['userId']]['updates']['signed'], $transactionEmailBuyer);

          ///// MAIN BUYER /////
          array_push($mainBuyersEmails[$buyerInfo['userId']]['updates']['signed'], $transactionEmailBuyer);

        }
        //has NOT signed at ALL
        if($t['buyerSignedDate'] == "" || $t['buyerSignedDate'] == 0) {

          //If TP email exists
          if($dataUpdate['tpEmailSigner'] != "") {
            //Set signature reminder to 1
            $tpReminderSignature = 1;
          }

          //increment signatures pending
          $mainBuyersEmails[$buyerInfo['userId']]['updates']['signaturesPending']++;

        }

        ///////////////////////////////////
        /////       Buyer Paid      ///////
        ///////////////////////////////////

        //NOTE!!!!!!!! - while this is still here, the email functions are commented out as we moved this update into real-time on Admin codebase when a transaction is updated as payment received

        //HAS paid IN time window
        if($t['buyerPaidDateCreated'] >= $sinceTimestamp) {

          ///// MAIN SELLER /////
          array_push($mainSellersEmails[$sellerInfo['userId']]['updates']['paid'], $transactionEmailBuyer);

          ///// MAIN BUYER /////
          array_push($mainBuyersEmails[$buyerInfo['userId']]['updates']['paid'], $transactionEmailBuyer);
          //If this is NOT multibuyer and the legal entity does NOT have an email address, then send the main buyer the email
          if(!$multibuyer && $dataUpdate['tpEmailSigner'] == "") {
            $transactionEmailBuyer['updateType'] = 'mainBuyerPaid';
            $transactionEmailBuyer['updateTypeName'] = 'Payment Received';
            $transactionEmailBuyer['headline'] = "Payment Received";
            $transactionEmailBuyer['firstName'] = $mainBuyer['firstName'];
            if(!$emailBlock) {
              //$this->Email_Model->_send_email('closing_updates', 'Payment Received - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $mainBuyersEmails[$buyerInfo['userId']]['email'], $transactionEmailBuyer);
              //$this->Email_Model->_send_email('closing_updates', 'N - Payment Received - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', 'nnamikas+oix@gmail.com', $transactionEmailBuyer);
            } else {
              if($sendNick) {
                //$this->Email_Model->_send_email('closing_updates', 'N - Payment Received - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $nickEmail, $transactionEmailBuyer);
              }
            }

          }

          ///// TAX PAYER BUYER /////
          if($dataUpdate['tpEmailSigner'] != "") {
            $transactionEmailBuyer['updateType'] = 'buyerPaid';
            $transactionEmailBuyer['updateTypeName'] = 'Payment Received';
            $transactionEmailBuyer['headline'] = "Payment Received";
            if(!$emailBlock) {
              //$this->Email_Model->_send_email('closing_updates', 'Payment Received - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $transactionEmailBuyer['email'], $transactionEmailBuyer);
              //$this->Email_Model->_send_email('closing_updates', 'N - Payment Received - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', 'nnamikas+oix@gmail.com', $transactionEmailBuyer);
            } else {
              if($sendNick) {
                //$this->Email_Model->_send_email('closing_updates', 'N - Payment Received - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $nickEmail, $transactionEmailBuyer);
              }
            }

          }

        }
        //has NOT paid at ALL
        if($t['buyerPaid'] != 1) {

          //If TP email exists
          if($dataUpdate['tpEmailSigner'] != "") {
            //Set payment reminder to 1
            $tpReminderPayment = 1;
          }

          //increment payments pending
          $mainBuyersEmails[$buyerInfo['userId']]['updates']['paymentsPending']++;
        }

        ///////////////////////////////////
        ///// Transaction Complete  ///////
        ///////////////////////////////////

        //If closing doc pack has been uploaded
        if($t['finalPsaUploadedDate'] >= $sinceTimestamp && $t['finalPsaUploaded'] == 1) {

          ///// SELLER /////
          array_push($mainSellersEmails[$sellerInfo['userId']]['updates']['completed'], $transactionEmailBuyer);
          //Add this to the transaction chart overview, where it will never be included again since it is now complete
          array_push($mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'], $t);
          $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['pendingTransactionsInCreditCount']++;

          ///// BUYER /////
          array_push($mainBuyersEmails[$buyerInfo['userId']]['updates']['completed'], $transactionEmailBuyer);
          //Add this to the transaction chart overview, where it will never be included again since it is now complete
          array_push($mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'], $t);
          $mainBuyersEmails[$buyerInfo['userId']]['pendingTransactionsCount']++;
          $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['pendingTransactionsInCreditCount']++;
          //If this is NOT multibuyer and the legal entity does NOT have an email address, then send the main buyer the email
          if(!$multibuyer && $dataUpdate['tpEmailSigner'] == "") {
            $transactionEmailBuyer['updateType'] = 'mainClosingProcessComplete';
            $transactionEmailBuyer['updateTypeName'] = 'Closing Process Complete';
            $transactionEmailBuyer['headline'] = "Transaction Completed - Download Trade Docs";
            $transactionEmailBuyer['firstName'] = $mainBuyer['firstName'];
            if(!$emailBlock) {
              //$this->Email_Model->_send_email('closing_updates', 'Transaction Completed - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $mainBuyersEmails[$buyerInfo['userId']]['email'], $transactionEmailBuyer);
              //$this->Email_Model->_send_email('closing_updates', 'N - Transaction Completed - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', 'nnamikas+oix@gmail.com', $transactionEmailBuyer);
            } else {
              if($sendNick) {
                //$this->Email_Model->_send_email('closing_updates', 'N - Transaction Completed - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $nickEmail, $transactionEmailBuyer);
              }
            }

          }

          ///// TAX PAYER BUYER /////
          if($dataUpdate['tpEmailSigner'] != "") {
            $transactionEmailBuyer['updateType'] = 'closingProcessComplete';
            $transactionEmailBuyer['updateTypeName'] = 'Closing Process Complete';
            $transactionEmailBuyer['headline'] = "Transaction Completed - Download Trade Docs";
            if(!$emailBlock) {
              //$this->Email_Model->_send_email('closing_updates', 'Transaction Completed - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $transactionEmailBuyer['email'], $transactionEmailBuyer);
              //$this->Email_Model->_send_email('closing_updates', 'N - Transaction Completed - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', 'nnamikas+oix@gmail.com', $transactionEmailBuyer);
            } else {
              if($sendNick) {
                //$this->Email_Model->_send_email('closing_updates', 'N - Transaction Completed - Purchase of '.$transactionEmailBuyer['taxYear'].' '.$transactionEmailBuyer['stateName'].' Tax Credits', $nickEmail, $transactionEmailBuyer);
              }
            }

          }

        }

        //////////////////////////////////////
        ///// Taxpayer Reminder Email  ///////
        //////////////////////////////////////

        //If there is at least one taxpayer reminder...
        $taxpayerReminders = $tpReminderSignature + $tpReminderPayment;
        if($taxpayerReminders > 0) {

          $this->closingPendingActionsEmail($transactionEmailBuyer, $transactionEmailBuyer['closingProcessStartDate'], $tpReminderSignature, $tpReminderPayment, $emailBlock, $sendNick, $nickEmail);

        }

        //If this transaction is below stage 4 and psa is not uploaded, increment as this will decide if we send the DMA an email or not
        if($t['finalPsaUploaded'] != 1) {
          array_push($mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'], $t);
          array_push($mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['sales'][$su['tradeId']]['transactions'], $t);
          $mainBuyersEmails[$buyerInfo['userId']]['pendingTransactionsCount']++;
          $mainBuyersEmails[$buyerInfo['userId']]['credits'][$su['listingId']]['pendingTransactionsInCreditCount']++;
          $mainSellersEmails[$sellerInfo['userId']]['credits'][$su['listingId']]['pendingTransactionsInCreditCount']++;
        }

      }

      //////////////////////////////////////////
      //// Finish Loop Through Transactions ////
      //////////////////////////////////////////

    }

    ///////////////////////////////////
    //// Finish Loop Through sales ////
    ///////////////////////////////////

    ////////////////////////////////////
    ///// SEND SELLER REPORT EMAIL /////
    ////////////////////////////////////

    foreach($mainSellersEmails as $mse) {

      //Tally the total updates for this email
      $mse['updates']['totalCount'] = sizeof($mse['updates']['signed']) + sizeof($mse['updates']['paid']) + sizeof($mse['updates']['completed']);

      //Send Seller a Summary Email if there are updates to share
      if($mse['updates']['totalCount'] > 0) {

        $mse['updateType'] = 'closingProcessSummary';
        $mse['updateTypeName'] = 'Status Updates on My Sales';
        $mse['headline'] = "My Closing Process Status Report";

        //Send seller the email
        if(!$emailBlock) {
          $this->Email_Model->_send_email('closing_updates', 'My OIX Sales - Closing Process Report', $mse['email'], $mse);
          $this->Email_Model->_send_email('closing_updates', 'N - My OIX Sales - Closing Process Report', 'nnamikas+oix@gmail.com', $mse);
        } else {
          if($sendNick) {
            $this->Email_Model->_send_email('closing_updates', 'N - My OIX Sales - Closing Process Report', $nickEmail, $mse);
          }
        }
        echo $mse['userId'] . "<br>";

      }

    }
    /*
    echo "<pre>";
    var_dump($mainSellersEmails);
    echo "</pre>";
    */

    ///////////////////////////////////
    ///// SEND BUYER REPORT EMAIL /////
    ///////////////////////////////////

    foreach($mainBuyersEmails as $mbe) {

      //If there are still pending transactions, regardless of the status of
      if($mbe['pendingTransactionsCount'] > 0) {

        //Tally the total updates for this email
        $mbe['updates']['totalCount'] = sizeof($mbe['updates']['signed']) + sizeof($mbe['updates']['paid']) + sizeof($mbe['updates']['completed']);

        $mbe['updateType'] = 'closingProcessSummary';
        $mbe['updateTypeName'] = 'Status Updates on My Purchases';
        $mbe['headline'] = "My Closing Process Status Report";
        //Unique data point for buyer
        $mbe['pendingActionsCount'] = $mbe['updates']['signaturesPending'] + $mbe['updates']['paymentsPending'];

        //Send seller the email
        if(!$emailBlock) {
          $this->Email_Model->_send_email('closing_updates', 'My OIX Purchases - Closing Process Report', $mbe['email'], $mbe);
          $this->Email_Model->_send_email('closing_updates', 'N - My OIX Purchases - Closing Process Report', 'nnamikas+oix@gmail.com', $mbe);
        } else {
          if($sendNick) {
            $this->Email_Model->_send_email('closing_updates', 'N - My OIX Purchases - Closing Process Report', $nickEmail, $mbe);
          }
        }
        echo $mbe['userId'] . "<br>";

      }

    }
    /*
    echo "<pre>";
    var_dump($mainBuyersEmails);
    echo "</pre>";
    */

    /////////////////////////////
    ///// SEND BUYER EMAIL //////
    /////////////////////////////

    //If this is NOT multibuyer and the only transaction has a legal entity with an email address
    /*
    if(!$multibuyer && $transactions[0]['tpEmailSigner']!="") {
      $this->Email_Model->_send_email('closing_updates', 'Update on Sale '.$dataEmailBuyer['tradeIdFull'], $dataEmailBuyer['email'], $dataEmailBuyer);
      $this->Email_Model->_send_email('closing_updates', 'N - Update on Sale '.$dataEmailBuyer['tradeIdFull'], 'nnamikas@gmail.com', $dataEmailBuyer);
    }
    */

    //Send Buyer Email if there are updates to share
    //if(($transactions[0]['tpEmailSigner']!="" || $multibuyer) && sizeof($dataEmailBuyer['updates'])>0) {
    //$this->Email_Model->_send_email('closing_update', 'Update on Sale '.$dataEmailBuyer['tradeIdFull'], $dataEmailBuyer['email'], $dataEmailBuyer);
    //$this->Email_Model->_send_email('closing_update', 'N - Update on Sale '.$dataEmailBuyer['tradeIdFull'], 'nnamikas@gmail.com', $dataEmailBuyer);
    //}
    //Completed transaction updates for the buyer
    //if(($transactions[0]['tpEmailSigner']!="" || $multibuyer) && sizeof($dataEmailBuyer['completed'])>0) {
    //$this->Email_Model->_send_email('closing_complete', 'Sale Completed', $dataEmailBuyer['email'], $dataEmailBuyer);
    //$this->Email_Model->_send_email('closing_complete', 'N - Sale Completed', 'nnamikas@gmail.com', $dataEmailBuyer);
    //	}

    $testData['updateType'] = 'test';
    if(!$emailBlock || $sendNick) {
      $this->Email_Model->_send_email('oix_admin_update', 'Closing Process Updater - ' . $updateFlag, 'nnamikas@gmail.com', $testData);
    }

    echo time();

  }

}
