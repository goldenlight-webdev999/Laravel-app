<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Docs extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('CreditListings');
    $this->load->model('BidMarket');
    $this->load->model('Members_Model');
    $this->load->library(['session']);

  }

  function get_document($fileInfoId, $fileId = "", $fileName = "", $folderId = "", $listingId = "", $includeInactive = "") {

    $this->db->select('*');
    $this->db->from('files_info');
    if($fileInfoId != "") {
      $this->db->where('files_info.fileInfoId', $fileInfoId);
    }
    if($fileId != "") {
      $this->db->where('files_info.fileId', $fileId);
    }
    if($fileName != "") {
      $this->db->where('files_info.fileName', $fileName);
    }
    if($folderId != "") {
      $this->db->where('files_info.folderId', $folderId);
    }
    if($listingId != "") {
      $this->db->where('files_info.listingId', $listingId);
    }
    if($includeInactive == "") {
      $this->db->where('files_info.passedVirusScan', 1);
    }
    $this->db->where('files_info.deleteMarker', '0');
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $dRequest['fileId'] = $data['fileId'];
      $dRequest['dsId'] = null;
      $data['docToSign'] = $this->get_docToSign($dRequest);
      $data['error'] = null;
      $data['fileTypeName'] = null;
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_documents($docType = "", $listingId = "", $folderId = "", $docConnectToType = "", $docItemId = "", $diligenceFlag = "", $permissionFilter = "", $limit = "", $order = "", $dsIdBeingSigned = "") {

    $this->db->select('files_info.*, Accounts.firstName, Accounts.lastName, dmaAccounts.title');
    $this->db->from('files_info');
    if($docType != "") {
      $this->db->where('files_info.docType', $docType);
    }
    if($listingId != "") {
      $this->db->where('files_info.listingId', $listingId);
    }
    if($folderId != "") {
      $this->db->where('files_info.folderId', $folderId);
    }
    if($diligenceFlag > 0) {
      $this->db->where('files_info.diligenceFlag', $diligenceFlag);
    }
    if($dsIdBeingSigned > 0) {
      $this->db->where('files_info.dsIdBeingSigned', $dsIdBeingSigned);
    }

    if($permissionFilter == 1 && $this->uri->segment(1) != "notifications") { //if URL segment is notifications, then there is no session so can't check permission
      if($this->cisession->userdata('level') == 4) {
        $whereAccess = "(files_info.docShared = 1 OR files_info.fileDmaId = " . $this->cisession->userdata('dmaId') . " OR files_info.fileDmaId IN(" . $this->cisession->userdata('myDmaIds') . "))";
        $this->db->where($whereAccess);
      }
    }

    if($docConnectToType != "") {
      $this->db->where('files_info.docConnectToType', $docConnectToType);
      $this->db->where('files_info.docItemId', $docItemId);
    }
    $this->db->where('files_info.deleteMarker', '0');
    $this->db->where('files_info.passedVirusScan', 1);
    $this->db->join("Accounts", "files_info.fileUserId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "files_info.fileDmaId = dmaAccounts.dmaId", 'left');
    if($order == "new_to_old") {
      $this->db->order_by("files_info.fileId DESC");
    } else {
      $this->db->order_by("files_info.fileName ASC");
    }
    if($limit > 0) {
      $this->db->limit($limit);
    }
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $dRequest['fileId'] = $data['fileId'];
      $dRequest['dsId'] = null;
      $data['docToSign'] = $this->get_docToSign($dRequest);
      $data['error'] = null;
      $data['fileTypeName'] = null;
      $data['docHasVirus'] = null;

      array_push($return, $data);
    }

    return $return;

  }

  function get_documents_backend($docType = "", $listingId = "", $folderId = "", $docConnectToType = "", $docItemId = "", $diligenceFlag = "", $permissionFilter = "") {
    $this->db->select('files_info.*, Accounts.firstName, Accounts.lastName, dmaAccounts.title');
    $this->db->from('files_info');
    if($docType != "") {
      $this->db->where('files_info.docType', $docType);
    }
    if($listingId != "") {
      $this->db->where('files_info.listingId', $listingId);
    }
    if($folderId != "") {
      $this->db->where('files_info.folderId', $folderId);
    }
    if($diligenceFlag > 0) {
      $this->db->where('files_info.diligenceFlag', $diligenceFlag);
    }

    if($permissionFilter == 1 && $this->uri->segment(1) != "notifications") { //if URL segment is notifications, then there is no session so can't check permission
      if($this->cisession->userdata('level') == 4) {
        $whereAccess = "(files_info.docShared = 1 OR files_info.fileDmaId = " . $this->cisession->userdata('dmaId') . " OR files_info.fileDmaId IN(" . $this->cisession->userdata('myDmaIds') . "))";
        $this->db->where($whereAccess);
      }
    }

    if($docConnectToType != "") {
      $this->db->where('files_info.docConnectToType', $docConnectToType);
      $this->db->where('files_info.docItemId', $docItemId);
    }
    $this->db->where('files_info.fileInfoId >', 4212);
    $this->db->where('files_info.deleteMarker', '0');
    $this->db->where('files_info.passedVirusScan', 1);
    $this->db->join("Accounts", "files_info.fileUserId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "files_info.fileDmaId = dmaAccounts.dmaId", 'left');
    $this->db->order_by("files_info.fileInfoId DESC");
    $this->db->limit(1000);
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_document_most_recent($docType = "", $listingId = "", $folderId = "", $docConnectToType = "", $docItemId = "", $orgFileName = "") {
    $this->db->select('*');
    $this->db->from('files_info');
    if($docType != "") {
      $this->db->where('files_info.docType', $docType);
    }
    if($listingId != "") {
      $this->db->where('files_info.listingId', $listingId);
    }
    if($folderId != "") {
      $this->db->where('files_info.folderId', $folderId);
    }
    if($docConnectToType != "") {
      $this->db->where('files_info.docConnectToType', $docConnectToType);
      $this->db->where('files_info.docItemId', $docItemId);
    }
    if($orgFileName != "") {
      $this->db->where('files_info.orgFileName', $orgFileName);
    }
    $this->db->where('files_info.deleteMarker', '0');
    $this->db->where('files_info.passedVirusScan', 1);

    $this->db->limit(1);
    $this->db->order_by("files_info.fileInfoId DESC");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function create_document($fileId, $fileName, $orgFileName, $fileSha1, $folderId, $listingId, $docType, $docConnectToType, $docItemId, $tag, $description, $etag, $newVersionNumOnOIX, $dsIdBeingSigned = "") {

    $dsIdBeingSigned = ($dsIdBeingSigned != "") ? $dsIdBeingSigned : null;

    $data = [
        'fileId'             => $fileId,
        'fileName'           => $fileName,
        'orgFileName'        => $orgFileName,
        'fileSha1'           => $fileSha1,
        'folderId'           => $folderId,
        'fileTime'           => date("Y-m-d H:i:s", time()),
        'sharedLink'         => "",
        'listingId'          => $listingId,
        'fileDmaId'          => $this->cisession->userdata('dmaId'),
        'fileUserId'         => $this->cisession->userdata('userId'),
        'systemTime'         => date("Y-m-d H:i:s", time()),
        'docType'            => $docType,
        'docConnectToType'   => $docConnectToType,
        'docItemId'          => $docItemId,
        'tag'                => $tag,
        'description'        => $description,
        'etag'               => $etag,
        'newVersionNumOnOIX' => $newVersionNumOnOIX,
        'dsIdBeingSigned'    => $dsIdBeingSigned,
        'passedVirusScan'    => null,
    ];

    $this->db->insert('files_info', $data);

    return $this->db->insert_id();

  }

  function get_folder($folderId = "", $folderName = "") {

    $this->db->select('*');
    $this->db->from('folders_info');
    if($folderName != "") {
      $this->db->where('folders_info.folderName', $folderName);
    } else {
      $this->db->where('folders_info.folderId', $folderId);
    }
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_folders($listingId, $folderName = "", $parentId = "") {

    $this->db->select('*');
    $this->db->from('folders_info');
    $this->db->where('folders_info.listingId', $listingId);
    if($folderName != "") {
      $this->db->where('folders_info.folderName', $folderName);
    }
    if($parentId != "") {
      $this->db->where('folders_info.parentId', $parentId);
    }
    $this->db->order_by("folders_info.folderInfoId ASC");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_upload_type_of_group($groupId) {

    $this->db->select('*');
    $this->db->from('upload_type');
    $this->db->where('grouping', $groupId);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_folder_id($listingId, $folderName) {

    $this->db->select('folderId');
    $this->db->from('folders_info');
    $this->db->where('folders_info.listingId', $listingId);
    $this->db->where('folders_info.folderName', $folderName);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0]['folderId'];
    }

  }

  function get_file_id_by_folder_and_tag($folderId, $tag) {

    $this->db->select('fileId');
    $this->db->from('files_info');
    $this->db->where('folderId', $folderId);
    $this->db->where('tag', $tag);
    $this->db->where('description', $tag);
    $this->db->where('deleteMarker', '0');
    $this->db->where('files_info.passedVirusScan', 1);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return;
    }

  }

  function delete_box_file($fileInfoId) {
    $data = [
        'deleteMarker' => '1',
    ];

    $this->db->where('fileInfoId', $fileInfoId);
    $this->db->update('files_info', $data);

    return true;
  }

  function delete_document($fileId) {
    $data = [
        'deleteMarker' => '1',
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function activate_document($fileId) {
    $data = [
        'passedVirusScan' => 1,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function update_etag_on_docs($fileId, $listingId, $etag) {
    $data = [
        'etag' => $etag,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->where('listingId', $listingId);
    $this->db->update('files_info', $data);

    return true;
  }

  function add_file_tag($fileId, $tag) {
    $data = [
        'tag'         => $tag,
        'description' => $tag,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function update_file_id($fileId, $tag) {
    $data = [
        'tag' => $tag,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function updateDocumentShareStatus($fileId, $docShared) {
    $data = [
        'docShared' => $docShared,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function tagDocWithVirusFlag($fileId) {
    $data = [
        'docHasVirus' => 1,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function update_doc_uploadedby($fileId, $dmaId, $userId) {
    $data = [
        'fileDmaId'  => $dmaId,
        'fileUserId' => $userId,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function update_doc_type($fileId, $docType) {
    $data = [
        'docType' => $docType,
    ];

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function updateDocumentDiligenceStatus($fileId, $diligenceFlag) {

    if($diligenceFlag == 2) {
      $data = [
          'diligenceFlag' => $diligenceFlag,
          'tag'           => 'Diligence',
          'description'   => 'Diligence',
      ];
    } else {
      $data = [
          'diligenceFlag' => $diligenceFlag,
          'tag'           => null,
          'description'   => null,
      ];
    }

    $this->db->where('fileId', $fileId);
    $this->db->update('files_info', $data);

    return true;
  }

  function create_folder($folderId, $folderName, $parentFolderId, $listingId) {

    $data = [
        'folderId'   => $folderId,
        'parentId'   => $parentFolderId,
        'folderName' => $folderName,
        'listingId'  => $listingId,
        'createdAt'  => date("Y-m-d H:i:s", time()),
        'systemTime' => date("Y-m-d H:i:s", time()),
        'appId'      => 1,
    ];

    $this->db->insert('folders_info', $data);

    return $this->db->insert_id();

  }

  function change_folder_listing($folderId, $newListingId) {
    $data = [
        'listingId' => $newListingId,
    ];

    $this->db->where('folderId', $folderId);
    $this->db->update('folders_info', $data);

    return true;
  }

  function insert_docToSign($userId, $dsType, $taxpayerId, $userFullname, $userEmail, $role, $state, $listingId, $tradeId, $transactionId, $boxDocId, $signatureData) {

    $data = [
        'dsSigned'        => 0,
        'dsType'          => $dsType,
        'dsUserId'        => $userId,
        'dsTaxpayerId'    => $taxpayerId,
        'dsFullName'      => $userFullname,
        'dsEmail'         => $userEmail,
        'dsRole'          => $role,
        'dsState'         => $state,
        'dsListingId'     => $listingId,
        'dsTradeId'       => $tradeId,
        'dsTransactionId' => $transactionId,
        'dsBoxFileId'     => $boxDocId,
        'dsCreatedDate'   => time(),
    ];

    $this->db->insert('docsToSign', $data);
    $dsId = $this->db->insert_id();

    foreach($signatureData as $sData) {
      $data2 = [
          'sigDocId'      => $dsId,
          'sigPage'       => $sData['page'],
          'sigTemplateId' => $sData['sigTemplate'],
      ];

      $this->db->insert('signatures', $data2);
    }

    return $dsId;

  }

  function get_docToSign_by_id($dsId) {
    $this->db->select('docsToSign.*, files_info.*');
    $this->db->where('docsToSign.dsId', $dsId);
    $this->db->from('docsToSign');
    $this->db->join("files_info", "docsToSign.dsBoxFileId = files_info.fileId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['signatures'] = $this->get_signatures_for_doc($dsId);
      $data['fileInfo'] = $this->get_document_most_recent("signature_doc", $data['dsListingId']);

      array_push($return, $data);
    }

    return $return[0];
  }

  function get_docToSign($request) {

    $request['fileId'] = (isset($request['fileId']) && $request['fileId'] > 0) ? $request['fileId'] : null;
    $request['dsId'] = (isset($request['dsId']) && $request['dsId'] > 0) ? $request['dsId'] : null;
    $request['dsAccessTime'] = (isset($request['dsAccessTime']) && $request['dsAccessTime'] > 0) ? $request['dsAccessTime'] : null;

    $this->db->select('docsToSign.*, files_info.*');
    if($request['dsId'] > 0) {
      $this->db->where('docsToSign.dsId', $request['dsId']);
    }
    if($request['fileId'] > 0) {
      $this->db->where('docsToSign.dsBoxFileId', $request['fileId']);
    }
    if($request['dsAccessTime'] > 0) {
      $this->db->where('docsToSign.dsAccessTime', $request['dsAccessTime']);
    }
    $this->db->where('docsToSign.dsDeleted', null);
    $this->db->from('docsToSign');
    $this->db->join("files_info", "docsToSign.dsBoxFileId = files_info.fileId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($request['dsId'] > 0) {
        $data['signatures'] = $this->get_signatures_for_doc($request['dsId']);
      }
      $data['dsCreatedDateFormatted'] = date('m/d/Y', $data['dsCreatedDate']);

      //$data['fileInfo'] = $this->get_document_most_recent("signature_doc", $data['dsListingId']);

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    } else {
      return [];
    }

  }

  function get_signatures_for_doc($dsId) {
    $this->db->select('signatures.sigPage, sigTemplates.stX, sigTemplates.stY');
    $this->db->where('signatures.sigDocId', $dsId);
    $this->db->from('signatures');
    $this->db->join("sigTemplates", "signatures.sigTemplateId = sigTemplates.stId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function update_docToSign_envelopeId($dsId, $envelopeId) {
    $data = [
        'dsEnvelopeId' => $envelopeId,
    ];

    $this->db->where('docsToSign.dsId', $dsId);
    $this->db->update('docsToSign', $data);

  }

  function update_docToSign_signed($envelopeId) {
    $data = [
        'dsSigned'     => 1,
        'dsSignedDate' => time(),
    ];

    $this->db->where('docsToSign.dsEnvelopeId', $envelopeId);
    $this->db->update('docsToSign', $data);

  }

  function update_docToSign_signed_fileId($request) {
    $data = [
        'dsSignedVersionFileId' => $request['dsSignedVersionFileId'],
        'dsDownloadExpires'     => time() + 600,
    ];

    $this->db->where('docsToSign.dsId', $request['dsId']);
    $this->db->update('docsToSign', $data);

  }

  function update_docToSign_download_expires($dsId) {
    $data = [
        'dsDownloadExpires' => time() + 600,
    ];

    $this->db->where('docsToSign.dsId', $dsId);
    $this->db->update('docsToSign', $data);

  }

  function delete_docToSign($request) {
    $data = [
        'dsDeleted' => 1,
    ];

    if($request['dsId'] > 0) {
      $this->db->where('docsToSign.dsId', $request['dsId']);
    }
    if($request['fileId'] > 0) {
      $this->db->where('docsToSign.dsBoxFileId', $request['fileId']);
    }

    $this->db->update('docsToSign', $data);

  }

  function get_docToSign_by_transactionId($transactionId) {
    $this->db->select('docsToSign.*');
    $this->db->where('docsToSign.dsTransactionId', $transactionId);
    $this->db->from('docsToSign');
    $this->db->order_by("docsToSign.dsCreatedDate desc");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['signatures'] = $this->get_signatures_for_doc($data['dsId']);

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_docToSign_by_tradeId($tradeId) {
    $this->db->select('docsToSign.*');
    $this->db->where('docsToSign.dsTradeId', $tradeId);
    $this->db->from('docsToSign');
    $this->db->order_by("docsToSign.dsCreatedDate desc");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['signatures'] = $this->get_signatures_for_doc($data['dsId']);

      if($data['dsTransactionId'] < 1) {
        array_push($return, $data);
      }
    }

    return $return[0];
  }

  function get_docToSign_by_envelopeId($envelopeId) {
    $this->db->select('docsToSign.*');
    $this->db->where('docsToSign.dsEnvelopeId', $envelopeId);
    $this->db->from('docsToSign');
    $this->db->order_by("docsToSign.dsCreatedDate desc");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['signatures'] = $this->get_signatures_for_doc($data['dsId']);

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_sigTemplates() {
    $this->db->select('sigTemplates.*');
    $this->db->from('sigTemplates');
    $this->db->order_by("sigTemplates.stId ASC");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;
  }

  function countCommentsOnWorkflowItem($cmItemId) {
    $this->db->select('cmId');
    $this->db->from('Comments');

    $array = ['Comments.cmDeleteMarker ' => null, 'Comments.cmConnectToType' => 'workflow_item', 'Comments.cmItemId' => $cmItemId];
    $this->db->where($array);

    $num_results = $this->db->count_all_results();

    return $num_results;

  }

  function insert_workflow_no_post($workflowData, $workflowAttachedId) {

    $data = [
        'wAttachedToId'   => $workflowAttachedId,
        'wAttachedToType' => $workflowData['wAttachedToType'],
        'wCreated'        => time(),
    ];

    $this->db->insert('workflows', $data);

    return $this->db->insert_id();

  }

  function insert_workflow_list_no_post($workflowListData, $workflowId) {

    $data = [
        'wlWorkflowId' => $workflowId,
        'wlName'       => $workflowListData['wlName'],
        'wlOrder'      => $workflowListData['wlOrder'],
        'wlCreated'    => time(),
    ];

    $this->db->insert('workflow_lists', $data);

    return $this->db->insert_id();

  }

  function insert_workflow_list_item_no_post($workflowListItemData, $workflowListId) {

    $data = [
        'wiListId'  => $workflowListId,
        'wiTempId'  => $workflowListItemData['wiTempId'],
        'wiOrder'   => $workflowListItemData['wiOrder'],
        'wiCreated' => time(),
    ];

    $this->db->insert('workflow_items', $data);

    return $this->db->insert_id();

  }

  function update_workflow_item() {

    if($_POST["wiTempType"] == "currency" || $_POST["wiTempType"] == "percentage") {
      $wiValue = (float)preg_replace('/[^0-9.]*/', '', $wiValue);
    } else {
      if($_POST["wiTempType"] == "date") {
        $wiValue = strtotime($_POST['wiValue']);
      } else {
        $wiValue = $_POST['wiValue'];
      }
    }

    $data = [
        'wiStatus'        => 1,
        'wiValue'         => $wiValue,
        'wiCompletedUser' => $this->cisession->userdata('userId'),
        'wiCompletedDate' => time(),
    ];

    $this->db->where('workflow_items.wiId', $_POST['wiId']);
    $this->db->update('workflow_items', $data);

    return true;

  }

  function updateDocSignatureStatus($request) {
    $data = [
        'docSignatureStatus' => $request['docSignatureStatus'],
    ];

    if($request['docSignatureStatus'] == 3) {
      $data['docSignatureDateSigned'] = time();
      $data['docSignedFileId'] = $request['docSignedFileId'];
    }

    $this->db->where('files_info.fileId', $request['fileId']);
    $this->db->update('files_info', $data);

    return true;
  }

  function createDocSignatureLink($request) {
    $dsAccessTime = time() + 3600; //add 3600 seconds (1 hr)
    $data = [
        'dsAccessTime' => $dsAccessTime,
    ];

    $this->db->where('docsToSign.dsId', $request['dsId']);
    $this->db->update('docsToSign', $data);

    return $dsAccessTime;
  }

  function update_sample($transactionId, $tCreditAmount) {
    $data = [
        'tCreditAmount' => $tCreditAmount,
    ];

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;
  }

  function delete_sample($transactionId) {
    $data = [
        'tDeleted' => 1,
    ];

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;
  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
