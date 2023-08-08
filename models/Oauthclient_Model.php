<?php

use OIX\Util\Logger;

class Oauthclient_Model extends CI_Model {
  protected $logger;

  private $table_name = 'oauth_clients';

  public function __construct() {
    parent::__construct();

    //$this->table_name = $this->table_name;
    $this->logger = Logger::getInstance();
  }

  public function add_client($fields) {
    $this->db->insert($this->table_name, $fields);

    return $this->db->insert_id();
  }

  public function update_client($fields) {
    $this->db->where('id', $fields['id']);
    $this->db->update($this->table_name, $fields);
  }

  public function get_all_clients($dmaId) {
    $this->db->select('oauth_clients.*, dmaAccounts.title accountTitle, Accounts.email');
    $this->db->from('oauth_clients');
    $this->db->join("dmaAccounts", "dmaAccounts.dmaId = oauth_clients.dma_id", 'LEFT');
    $this->db->join("Accounts", "Accounts.userId = oauth_clients.user_id", 'LEFT');
    $this->db->where('oauth_clients.revoked', 0);
    $this->db->where('oauth_clients.dma_id', $dmaId);

    $this->db->order_by("oauth_clients.updated_at", "ASC");

    $query = $this->db->get();

    return $query->result_array();
  }

  public function get_client($client_id) {
    $this->db->select('oauth_clients.*, dmaAccounts.title accountTitle');
    $this->db->from('oauth_clients');
    $this->db->join("dmaAccounts", "dmaAccounts.dmaId = oauth_clients.dma_id", 'LEFT');
    $this->db->join("Accounts", "Accounts.userId = oauth_clients.user_id", 'LEFT');
    $this->db->where('oauth_clients.id', $client_id);

    $query = $this->db->get();

    return $query->row_array();
  }

  public function get_dma_members_by_userid($user_id) {
    $this->db->select('dmaMembers.*, dmaAccounts.*');
    $this->db->from('dmaMembers');
    $this->db->join("dmaAccounts", "dmaAccounts.dmaId = dmaMembers.dmaMDmaId", 'LEFT');
    $this->db->where('dmaMembers.dmaMUserId', $user_id);
    $this->db->order_by("dmaAccounts.title", "ASC");

    $query = $this->db->get();

    return $query->result_array();
  }

  public function revoke_client($client_id) {
    $data = [
        'revoked'    => 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $this->db->where('oauth_clients.id', $client_id);
    $this->db->update('oauth_clients', $data);
  }
}