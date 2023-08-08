<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Email_Model extends CI_Model {

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }

  }


  // Type of Notifications
  // LI=NEW LISTING, LU=LISITING UPDATE
  // BI=NEW BID, BU=BID UPDATE
  // TI=NEW TRADE, TU=TRADE UPDATE
  // OI=NEW OPEN BID, OU=UPDATE OPEN BID

  function _send_email($type, $subject, $email, &$data, $bcc = "") {

    //FIRST CHECK IF SYSTEM EMAIL FLAG IS OFF
    $this->db->select('globalSettings.*');
    $this->db->where('globalSettings.gsName', 'system_email');
    $this->db->where('globalSettings.gsStatus', 1);
    $this->db->from('globalSettings');
    $emailCheckQuery = $this->db->get();
    $emailCheck = [];
    foreach($emailCheckQuery->result_array() as $emailCheckData) {
      array_push($emailCheck, $emailCheckData);
    }

    //IF EMAIL SEND IS ON
    if(sizeof($emailCheck) > 0) {

      //If a specific email type is shut off, then skip sending that type of email
      if($emailCheck[0]['gsValueLong'] == $type) {
        return;
      }

      $testEmail = false;
      $emailIsEmpty = false;
      if(is_array($email) or ($email instanceof Traversable)) { //If emails are an array
        $to = [];
        foreach($email as $em) {
          $thisArray = ["email" => $em];
          array_push($to, $thisArray);
        }
      } else { //else if it's an individual email address
        $to = [["email" => $email]];
        if(substr(strrchr($email, "@"), 1) == "test.com") {
          $testEmail = true;
        }
        if($email == "") {
          $emailIsEmpty = true;
        }
      }

      if(!$testEmail && !$emailIsEmpty) {

        $fromEmail = $this->config->item('webmaster_email');
        $fromName = $this->config->item('website_name');
        $replyToEmail = $this->config->item('webmaster_email');
        $replyToName = $this->config->item('website_name');
        $subject = $subject;
        $body = $this->load->view('email/' . $type . '-html', $data, true);
        //Comment this out to see a preview in the web browser
        //echo $body; throw new \Exception('General fail');
        $data_string = ["personalizations" => [["to" => $to]], "subject" => $subject, "from" => ["email" => $fromEmail, "name" => $fromName], "reply_to" => ["email" => $replyToEmail, "name" => $replyToName], "content" => [["type" => "text/html", "value" => $body]]];
        $data_string = json_encode($data_string);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.sendgrid.com/v3/mail/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $data_string,
            CURLOPT_HTTPHEADER     => [
                "authorization: Bearer " . $this->config->item("SENDGRID_API_KEY"),
                "content-type: application/json",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if($err) {
          echo "cURL Error #:" . $err;
        } else {
          echo $response;
        }

      }

    }

  }

  function _send_email_new($input) {

    //Check required fields exist
    //	if((!isset($input['templateName']) || $input['templateName']=="") && (!isset($input['templateFileURL']) || $input['templateFileURL']=="")) { throw new \Exception('General fail'); }
    //	if(!isset($input['subject']) || $input['subject']=="") { throw new \Exception('General fail'); }
    //	if(!isset($input['to']) || $input['to']=="") { throw new \Exception('General fail'); }

    //Setup the variables
    $templateName = isset($input['templateName']) ? $input['templateName'] : null; //Name of OIX Email Template file - found in /views/email/
    $templateFileURL = isset($input['templateFileURL']) ? $input['templateFileURL'] : null; //URL to an html file to use as the body of the email (for product announcements, etc)
    $subject = $input['subject'];
    $toEmailAddresses = $input['to']; //could be single email address or array (processed below as $to)
    $emailData = isset($input['emailData']) ? $input['emailData'] : []; //free-form variables used in the selected OIX Email Template
    $bccEmailAddresses = isset($input['bcc']) ? $input['bcc'] : [];

    //CHECK IF SYSTEM EMAIL FLAG IS OFF
    $this->db->select('globalSettings.*');
    $this->db->where('globalSettings.gsName', 'system_email');
    $this->db->where('globalSettings.gsStatus', 1);
    $this->db->from('globalSettings');
    $emailCheckQuery = $this->db->get();
    $emailCheck = [];
    foreach($emailCheckQuery->result_array() as $emailCheckData) {
      array_push($emailCheck, $emailCheckData);
    }

    //IF EMAIL SEND IS ON
    if(sizeof($emailCheck) > 0) {

      //If a specific email type is shut off, then skip sending that type of email
      if($templateFileURL == "" && $emailCheck[0]['gsValueLong'] == $templateName) {
        return;
      }

      $testEmail = false;

      //prepare $to from email address variable and check if it's a test email address, in which case block it
      $toIsArray = false;
      if(is_array($toEmailAddresses) or ($toEmailAddresses instanceof Traversable)) {
        $toIsArray = true;
      } else {
        $toEmailAddressesExplode = explode(',', $toEmailAddresses);
        if(in_array(2, $toEmailAddressesExplode)) {
          $toIsArray = true;
          $toEmailAddresses = $toEmailAddressesExplode;
        }
      }
      if($toIsArray) {
        $to = [];
        foreach($toEmailAddresses as $em) {
          $thisArray = ["email" => $em];
          array_push($to, $thisArray);
        }
      } else {
        $to = [["email" => $toEmailAddresses]];
        if(substr(strrchr($toEmailAddresses, "@"), 1) == "test.com") {
          $testEmail = true;
        }
      }

      //prepare $bcc from bcc variable and check if it's a test email address, in which case block it
      $bccIsArray = false;
      if(is_array($bccEmailAddresses) or ($bccEmailAddresses instanceof Traversable)) {
        $bccIsArray = true;
      } else {
        $bccEmailAddressesExplode = explode(',', $bccEmailAddresses);
        if(sizeof($bccEmailAddressesExplode) > 1) {
          $bccIsArray = true;
          $bccEmailAddresses = $bccEmailAddressesExplode;
        }
      }
      if($bccIsArray) {
        $bcc = [];
        foreach($bccEmailAddresses as $em) {
          $thisArray = ["email" => $em];
          array_push($bcc, $thisArray);
        }
      } else {
        $bcc = [["email" => $bccEmailAddresses]];
      }

      if(!$testEmail) {

        $fromEmail = $this->config->item('webmaster_email');
        $fromName = $this->config->item('website_name');
        $replyToEmail = $this->config->item('webmaster_email');
        $replyToName = $this->config->item('website_name');
        if($templateName != "") {
          $body = $this->load->view('email/' . $templateName . '-html', $emailData, true);
        } else {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $templateFileURL);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          $body = curl_exec($ch);
          curl_close($ch);
          //$body = "testttt";
        }
        if($body == "") {
          throw new \Exception('General fail');
        }
        //Comment this out to see a preview in the web browser
        //echo $body; throw new \Exception('General fail');
        $data_string = ["personalizations" => [["to" => $to, "bcc" => $bcc]], "subject" => $subject, "from" => ["email" => $fromEmail, "name" => $fromName], "reply_to" => ["email" => $replyToEmail, "name" => $replyToName], "content" => [["type" => "text/html", "value" => $body]]];
        $data_string = json_encode($data_string);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.sendgrid.com/v3/mail/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $data_string,
            CURLOPT_HTTPHEADER     => [
                "authorization: Bearer " . $this->config->item("SENDGRID_API_KEY"),
                "content-type: application/json",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if($err) {
          echo "cURL Error #:" . $err;
        } else {
          echo $response;
        }

      }

    }

  }

  function _send_sms($smsTo, $smsText) {

    $this->load->library('twilio');

    $from = '+14243544958';
    $to = $smsTo;
    $message = $smsText;

    $response = $this->twilio->sms($from, $to, $message);

  }

}
