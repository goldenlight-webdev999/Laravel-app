<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Comments extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('CreditListings');
    $this->load->model('BidMarket');
    $this->load->model('Members_Model');
    $this->load->library(['session']);

  }

  function get_comments($cmConnectToType, $cmItemId) {
    $this->db->select('*, Accounts.userId, Accounts.firstName, Accounts.lastName, dmaAccounts.title');
    $this->db->from('Comments');
    $array = ['Comments.cmDeleteMarker' => null, 'Comments.cmConnectToType' => $cmConnectToType, 'Comments.cmItemId' => $cmItemId];
    $this->db->where($array);
    $this->db->join("Accounts", "Comments.cmUserId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "Comments.cmDmaId = dmaAccounts.dmaId", 'left');
    $this->db->order_by("Comments.cmTimeStamp DESC");
    $this->db->distinct();
    $return = [];
    $query = $this->db->get();
    foreach($query->result_array() as $data) {
      $data["cmTimestamp"] = date("m/d/Y h:i:s", $data["cmTimestamp"]) . " ET";
      array_push($return, $data);
    }

    return $return;
  }

  function get_comment($cmId) {
    $this->db->select('*, Accounts.userId, Accounts.firstName, Accounts.lastName, dmaAccounts.title');
    $this->db->from('Comments');
    $array = ['Comments.cmDeleteMarker' => null, 'Comments.cmId' => $cmId];
    $this->db->where($array);
    $this->db->join("Accounts", "Comments.cmUserId = Accounts.userId", 'left');
    $this->db->join("dmaAccounts", "Comments.cmDmaId = dmaAccounts.dmaId", 'left');
    $this->db->order_by("Comments.cmTimeStamp DESC");
    $this->db->distinct();
    $return = [];
    $query = $this->db->get();
    foreach($query->result_array() as $data) {
      $data["cmTimestamp"] = date("m/d/Y h:i:s", $data["cmTimestamp"]) . " ET";
      array_push($return, $data);
    }
    if(count($return) > 0) {
      return $return[0];
    }
  }

  function countCommentsOnItem($cmItemId, $cmConnectToType) {
    $this->db->select('cmId');
    $this->db->from('Comments');
    $array = ['Comments.cmDeleteMarker' => null, 'Comments.cmConnectToType' => $cmConnectToType, 'Comments.cmItemId' => $cmItemId];
    $this->db->where($array);
    $num_results = $this->db->count_all_results();

    return $num_results;
  }

  function insert_comment() {
    $data = [
        'cmConnectToType' => $_POST['cmConnectToType'],
        'cmItemId'        => $_POST['cmItemId'],
        'cmUserId'        => $this->cisession->userdata('userId'),
        'cmDmaId'         => $this->cisession->userdata('dmaId'),
        'cmText'          => $_POST['cmText'],
        'cmTimestamp'     => time(),
    ];
    $this->db->insert('Comments', $data);

    return $this->db->insert_id();
  }

  function update_comment() {
    $data = [
        'cmItemId'    => $_POST['cmItemId'],
        'cmUserId'    => $this->cisession->userdata('userId'),
        'cmDmaId'     => $this->cisession->userdata('dmaId'),
        'cmText'      => $_POST['cmText'],
        'cmTimestamp' => time(),
    ];
    $this->db->where('Comments.cmId', $_POST['cmId']);
    $this->db->update('Comments', $data);

    return true;
  }

  function delete_comment() {
    $data = [
        'cmDeleteMarker' => 1,
        'cmDeleteDate'   => time(),
    ];
    $this->db->where('Comments.cmId', $_POST['cmId']);
    $this->db->update('Comments', $data);

    return true;
  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
