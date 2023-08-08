<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Uploading extends CI_Controller {

  protected $logger;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('CreditListings');
    $this->load->model('Uploads');
    $this->load->model('Email_Model');
    $this->load->library('filemanagementlib');
    $this->load->library('memberpermissions');

    $this->logger = Logger::getInstance();

  }


  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////
  ///  NEW UPLOADER FUNCTIONS - these are new functions related to AWS S3 uploader system
  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////

  function upload_file() {

    // Check if authenticated
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      throw new \Exception('General fail');
    }

    // Get the JSON POST request
    $request = $this->input->post();

    // Setup data structure
    $return = [];
    $return['success'] = 0; //set to fail by default
    $return['error'] = 'File did not upload'; //set generic error by default
    $return['data'] = [];
    $error = false;

    // Insert file into database (files_info table)
    $new_file_data = [];
    $new_file_data['file_name'] = $request['file_name'];
    $new_file_data['file_ext'] = $request['file_ext'];
    $new_file_data['uploaded_by'] = $this->cisession->userdata('dmaMemberId');

    // Generate a unique hash
    $hash = md5(uniqid(rand(), true));
    //Add it to request
    $new_file_data['hash'] = $hash;
    $upload_id = $this->Uploads->insert_upload($new_file_data);

    // Build the unique file name to send back to the uploader to be used as the file name to be uploaded to S3
    $return['data']['file_name'] = $upload_id . "-" . $hash . "." . $request['file_ext'];
    $return['data']['file_ext'] = $request['file_ext'];
    $return['data']['upload_id'] = $upload_id;

    // Generate a signed S3 upload URL from AWS
    // AWS doesn't support one time use URL. Set 20 mins as URL expiration temporarily
    $file['awsS3FileKey'] = $return['data']['file_name'];
    $file['awsS3BucketName'] = $this->config->item('OIX_AWS_BUCKET_NAME_CLIENT_FILES_QUEUE');
    $file['expirationMinutes'] = '+20 minutes';
    $upload_url = $this->filemanagementlib->get_aws_s3_file_upload_url($file);

    $return['data']['upload_url'] = $upload_url;

    // Mark success
    if($return['data']['upload_url'] != '') {
      $return['success'] = 1;
      $return['error'] = '';
    }

    // Log it
    $LOG_request = json_encode($return);
    $this->logger->info("File Uploaded > Data: " . $LOG_request);

    echo json_encode($return);
  }


  function update_upload_file_status() {
    $file_id = $this->input->post('file_id');
    $status = $this->input->post('status');

    $this->Uploads->update_file_status($file_id, $status);
  }







  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////
  ///  OLD UPLOADER FUNCTIONS - these are still being used, but one day will be sunset
  ////////////////////////////////////////////////////////////
  ////////////////////////////////////////////////////////////

  function createBoxFolders($issueId, $listingId, $appId) {
    $folderId = $this->filemanagementlib->createBoxFolders($issueId, $listingId, "", "", 1);
    echo $folderId;
  }

  function getFolderItems($folderId, $appId, $filterTag, $listingId = "", $tradeId = "", $transactionId = "") {

    $ret = [];
    $folderItems = $this->filemanagementlib->getFolderItems($folderId, $appId, $filterTag, $listingId, $tradeId, $transactionId);

    if(count($folderItems) > 0) {
      $ret = $folderItems;
    }
    echo json_encode($ret);

  }

  function getFolderItemMostRecent($folderId, $appId, $filterTag, $listingId = "", $tradeId = "", $transactionId = "") {

    $ret = [];
    $folderItems = $this->filemanagementlib->getFolderItemMostRecent($folderId, $appId, $filterTag, $listingId, $tradeId, $transactionId);

    if(count($folderItems) > 0) {
      $ret = $folderItems;
    }

    echo json_encode($ret);

  }

  function deleteBoxFile($fileId, $etag, $appId, $listingId, $psa = 0) {
    $data = $this->filemanagementlib->deleteBoxFile($fileId, $etag, $appId, $listingId, $psa);
    echo json_encode($data);
  }

  function addBoxFileTags($tagName, $fileId, $appId) {
    $permissions = $this->memberpermissions->checkCreditAccess($listingId);
    if(!$permissions['permEdit']) {
      echo "No Permission";
      throw new \Exception('General fail');
    }

    $data = $this->filemanagementlib->addBoxFileTags($tagName, $fileId, $appId);
    echo json_encode($data);
  }

  function updateDocumentShareStatus($fileId, $listingId, $newShareStatus, $filterTag) {

    $newShareStatus = (int)$newShareStatus;
    $fileId = (int)$fileId;
    $listingId = (int)$listingId;

    //Only if you have edit access on this credit
    $permissions = $this->memberpermissions->checkCreditAccess($listingId);
    if(!$permissions['permEdit']) {
      echo json_encode("No Permission");
      throw new \Exception('General fail');
    }

    if($newShareStatus == 1) {
      $docShared = 1;
    } else {
      $docShared = null;
    }
    $data = $this->Docs->updateDocumentShareStatus($fileId, $docShared);
    echo json_encode($data);

  }

  function updateDocumentDiligenceStatus($fileId, $listingId, $newDiligenceStatus, $filterTag) {

    $thisDoc = $this->Docs->get_document("", $fileId);
    $creditData = $this->CreditListings->get_credit_private($listingId);

    if($filterTag == "newcredit") {
      //no permission check as no one owns the credit

      if($thisDoc['docType'] == "credit_doc" || $thisDoc['docType'] == "transaction_doc" || $thisDoc['docType'] == "signature_doc") {

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

    } else {

      //Only if you have edit access on this credit
      $permissions = $this->memberpermissions->checkCreditAccess($listingId);
      if(!$permissions['permEdit']) {
        echo "No Permission";
        throw new \Exception('General fail');
      }

      if($creditData['status'] == 3 && $newDiligenceStatus == 1) { //if this is submitted to or currenlty on the marketplace and the new request is "1" for OIX Admin repository

        //send email
        $emailData['name'] = $this->cisession->userdata('firstName') . " " . $this->cisession->userdata('lastName');
        $emailData['listingId'] = $listingId;
        $emailData['action'] = "add";
        $emailData['fileName'] = $thisDoc['fileName'];
        $emailData['fileId'] = $thisDoc['fileInfoId'];

        $emailData['updateType'] = 'newCreditDocForOIXAdmin';
        $emailData['updateTypeName'] = 'New Credit Doc for OIX Admin Review';
        $this->Email_Model->_send_email('oix_admin_update', 'New Credit Doc for OIX Admin Review', $this->config->item("oix_admin_emails_array"), $emailData);

      }

    }

    $data = $this->Docs->updateDocumentDiligenceStatus($fileId, $newDiligenceStatus);
    echo json_encode($data);
  }

}
