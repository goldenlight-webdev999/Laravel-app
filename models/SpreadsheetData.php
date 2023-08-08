<?php

Class SpreadsheetData extends CI_Model {
  public function get_base_data() {
    $this->db->order_by('id', 'ASC');
    $query = $this->db->get('spreadsheet_base_data');

    return $query->result();
  }

  public function get_raw_monetization() {
    $this->db->order_by('id', 'ASC');
    $query = $this->db->get('spreadsheet_raw_monetization');

    return $query->result();
  }
}
