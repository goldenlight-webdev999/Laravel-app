<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Uploads extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('CreditListings');
    $this->load->library(['session']);

  }

  function insert_upload($request) {

    $data['hash'] = (isset($request['hash']) && $request['hash'] != "") ? $request['hash'] : null;
    $data['file_ext'] = (isset($request['file_ext']) && $request['file_ext'] != "") ? $request['file_ext'] : null;
    $data['file_name'] = (isset($request['file_name']) && $request['file_name'] != "") ? $request['file_name'] : null;
    $data['uploaded_by'] = (isset($request['uploaded_by']) && $request['uploaded_by'] != "") ? $request['uploaded_by'] : null;
    $data['created_at'] = date("Y-m-d H:i:s", time());

    $this->db->insert('upload', $data);

    return $this->db->insert_id();

  }

  function update_upload($upload_id, $request) {

    $data['hash'] = (isset($request['hash']) && $request['hash'] != "") ? $request['hash'] : null;
    $data['file_extension'] = (isset($request['file_extension']) && $request['file_extension'] != "") ? $request['file_extension'] : null;
    $data['file_size'] = (isset($request['file_size']) && $request['file_size'] != "") ? $request['file_size'] : null;
    $data['file_name'] = (isset($request['file_name']) && $request['file_name'] != "") ? $request['file_name'] : null;
    $data['exif'] = (isset($request['exif']) && $request['exif'] != "") ? $request['exif'] : null;
    $data['created_at'] = date("Y-m-d H:i:s", time());
    $data['uploaded_by'] = (isset($request['uploaded_by']) && $request['uploaded_by'] != "") ? $request['uploaded_by'] : null;

    $this->db->where('id', $upload_id);
    $this->db->update('upload', $data);

    return true;
  }

  function update_file_status($upload_id, $status) {

    $data['status'] = $status;

    $this->db->where('id', $upload_id);
    $this->db->update('upload', $data);

    return true;
  }

  function get_file_info($upload_id) {

    $this->db->where('id', $upload_id);
    $query = $this->db->get('upload');
    return $query->row();

  }
}

/* End of file Uploads.php */
/* Location: ./application/models/Uploads.php */
