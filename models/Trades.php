<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Trades extends CI_Model {
  private $pending_table_name = 'PendingListings';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $this->pending_table_name;
    $this->load->model('CreditListings');
    $this->load->model('BidMarket');
    $this->load->model('Members_Model');
    $this->load->library(['session']);

  }

  function get_trades() {
    $this->db->select('tradeId, Trades.timeStamp, Trades.brokeDate, Trades.status, Trades.accountId, Trades.listingId, tradeSize as size, tradePrice as price, Bids.bidId, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, PendingListings.stateCertNum, PendingListings.projectNameExt, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear, Accounts.userId, Accounts.firstName, Accounts.lastName, Accounts.companyName');
    $this->db->from('Trades');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "PendingListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "PendingListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->CreditListings->get_short_offsets_from_program($data->OffSettingTaxList);

    }

    return $query->result();

  }

  /* MOVED TO TRADING */
  function get_trade($id) {
    $this->db->select('tradeId, Trades.timeStamp, Trades.status, Trades.accountId, Trades.sellerSigned, Trades.sellerSignedDate, Trades.settlementDate, Trades.taxpayerUserId, Bids.accountId as BaccountId, Bids.bDmaMemberId, Trades.listingId, Trades.tDmaMemberId, Trades.brokeDate, tradeSize as size, tradePrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector,IncentivePrograms.OIXIncentiveId,ActiveListings.taxYearId, IPSectors.sector, Bids.bidId, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear, Accounts.companyName as buyerAccountName, PendingListings.cTaxpayerId, PendingListings.listedBy, PendingListings.cDmaMemberId, States.state, States.name');
    $this->db->from('Trades');
    $this->db->where('Trades.TradeId', $id);
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $data['shortOffSettingTaxList'] = $this->CreditListings->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['tradePrice'] = $data['price'];
      $data['tradeSize'] = $data['size'];
      array_push($return, $data);

    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  /* MOVED TO TRADING */
  function get_total_trade_amount_on_listing($id) {
    $this->db->select('SUM(Trades.tradeSize) as totalTradeAmount');
    $this->db->from('Trades');
    $this->db->where('Trades.listingId', $id);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      if($data['totalTradeAmount'] == null) {
        $data['totalTradeAmount'] = 0;
      }
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_filtered_trades($id, $sector, $programs, $taxyears, $records) {
    $this->db->select('tradeId, Trades.timeStamp, Trades.accountId, Trades.listingId, tradeSize as size, tradePrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear');
    $this->db->from('Trades');

    if(!empty($taxyears)) {
      $this->db->where('ActiveListings.taxYearId <=', strtoupper($taxyears));
    }

    if(!empty($id)) {
      $this->db->where('IncentivePrograms.State', strtoupper($id));
    }
    if(!empty($sector)) {
      $this->db->where('IncentivePrograms.Sector', strtoupper($sector));
    }

    if(!empty($programs)) {
      $this->db->where('ActiveListings.OIXIncentiveId', $programs);
      //$where="(ActiveListings.CreditUsedForOffset=".$offsets." or ActiveListings.CreditUsedForOffset=' ' and FIND_IN_SET(".$offsets.", IncentivePrograms.OffSettingTaxList) > 0 )";

      //$this->db->where($where, NULL, FALSE);
    }

    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');

    $this->db->distinct();

    if(!empty($records)) {
      $this->db->limit($records);
    }

    $this->db->order_by("Trades.tradeId desc");
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $data->OffSettingTaxList = $this->CreditListings->get_short_offsets_from_program($data->OffSettingTaxList);

    }

    return $query->result();

  }

  function get_trades_of_user($id, $full) {
    $this->db->select('*');
    $this->db->from('Trades');
    $this->db->where('Trades.accountId', $id);
    if($full == 1) {
      $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
      $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
      $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
      $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
      $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
      $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    }
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      if($full == 1) {
        $data['transactions'] = $this->get_transactions_of_trade($data['tradeId']);
        //$data['transactions'] = $data['transactions'][0];
      }

      array_push($return, $data);

    }

    return $return;

  }

  function get_trades_by_signer($userId, $full) {
    $this->db->select('*, Trades.status as tStatus, PendingListings.stateCertNum, PendingListings.projectNameExt, IncentivePrograms.ProgramName');
    $this->db->from('Trades');
    $this->db->where('Trades.taxpayerUserId', $userId);
    if($full == 1) {
      $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
      $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
      $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
      $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
      $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
      $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
      $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
      $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    }
    if($full == 2) {
      $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
      $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
      $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    }
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {

      if($full == 1 || $full == 2) {
        $data['transactions'] = $this->get_transactions_of_trade($data['tradeId']);
        //$data['transactions'] = $data['transactions'][0];
      }
      $data['itemType'] = 'trade';

      array_push($return, $data);

    }

    return $return;

  }

  //moved to trading
  function get_trade_of_bid($bidId) {
    $this->db->select('Trades*');
    $this->db->from('Trades');
    $this->db->where('Trades.bidId', $bidId);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  //Watch out!!!!!!! This should not use this function - use the same one but moved to Trading!!!
  function get_transactions_of_trade($tradeId) {
    $this->db->select('Transactions.*, Taxpayers.*');
    $this->db->where('Transactions.tradeId', $tradeId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_transactions_of_listing($listingId) {
    $this->db->select('Transactions.*, Taxpayers.*, Trades.tradePrice, Trades.accountId as tradeBuyerId, Trades.tDmaMemberId, Accounts.firstName as buyerFirstName, Accounts.lastName as buyerLastName, Accounts.email as buyerEmail');
    $this->db->where('Trades.listingId', $listingId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Trades');
    $this->db->join("Transactions", "Transactions.tradeId = Trades.tradeId", 'left');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("Accounts", "Accounts.userId = Trades.tDmaMemberId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $data['buyerName'] = $this->Members_Model->getUserCompanyById($data['tradeBuyerId']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_transaction_count_for_trade($tradeId) {
    $this->db->select('Transactions.transactionId');
    $this->db->where('Transactions.tradeId', $tradeId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $query = $this->db->get();

    $return = sizeof($query->result_array());

    return $return;

  }

  /* DUPLICATE is in Trading */
  function get_transactions_of_bid($bidId) {
    $this->db->select('Transactions.*, Taxpayers.*');
    $array = ['Transactions.tBidId' => $bidId, 'Transactions.tradeId' => null, 'Transactions.tDeleted' => null];
    $this->db->where($array);

    $this->db->from('Transactions');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      //$data['taxpayerInfo'] = $this->get_taxpayer($data['taxpayerId']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_transactions_of_openbid($openBidId) {
    $this->db->select('Transactions.*, Taxpayers.*');
    $array = ['Transactions.tOpenBidId' => $openBidId, 'Transactions.tradeId' => null, 'Transactions.tDeleted' => null];
    $this->db->where($array);

    $this->db->from('Transactions');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      //$data['taxpayerInfo'] = $this->get_taxpayer($data['taxpayerId']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_taxpayer($taxpayerId) {
    $this->db->select('*');
    $this->db->from('Taxpayers');
    $this->db->where('Taxpayers.taxpayerId', $taxpayerId);

    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_bid_transactions_of_user($userId) {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId', null);
    $this->db->where('Transactions.buyerAccountId', $userId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_trade_transactions_of_user($userId) {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId IS NOT NULL');
    $this->db->where('Transactions.tDeleted is null');
    $this->db->where('Transactions.buyerAccountId', $userId);
    $this->db->from('Transactions');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  /* DUPLICATE - on trading */
  function get_bid_transactions_of_taxpayer($taxpayerId, $filter, $getCredit) {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId', null);
    $this->db->where('Transactions.taxpayerId', $taxpayerId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($getCredit == 'yes') {
        $data['bidInfo'] = $this->BidMarket->get_bid_by_id($data['tBidId']);
        $data['creditInfo'] = $this->CreditListings->get_active_listing($data['bidInfo']['listingId']);
      }

      array_push($return, $data);
    }

    return $return;

  }

  /* DUPLICATE - on trading */
  function get_trade_transactions_of_taxpayer($taxpayerId, $filter, $getCredit) {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId IS NOT NULL');
    $this->db->where('Transactions.tDeleted is null');
    $this->db->where('Transactions.taxpayerId', $taxpayerId);
    $this->db->from('Transactions');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($this->get_transaction_count_for_trade($data['tradeId']) > 1) {
        $data['multiBuyer'] = true;
      } else {
        $data['multiBuyer'] = false;
      }

      if($getCredit == 'yes') {
        $data['saleInfo'] = $this->get_trade($data['tradeId']);
        $data['saleInfo'] = $data['saleInfo']['trade'];
        $data['creditInfo'] = $this->CreditListings->get_active_listing($data['saleInfo']['listingId']);
      }

      if($filter == 'pending') {
        if($data['buyerSigned'] != 1 || $data['buyerPaid'] != 1) {
          array_push($return, $data);
        }
      } else {
        array_push($return, $data);
      }

    }

    return $return;

  }

  /* DUPLICATE - on trading */
  function get_trade_transactions_of_user_pending_action($userId, $type) {
    $this->db->select('Transactions.*, ActiveListings.listingId, Trades.tradePrice, States.State, Bids.bidId');
    $this->db->where('Transactions.tradeId IS NOT NULL');
    if($type == 'dma') {
      $this->db->where('Transactions.tDmaId', $userId);
    } else {
      $this->db->where('Transactions.buyerAccountId', $userId);
    }
    $this->db->where('Transactions.tDeleted is null');
    $this->db->where('Trades.status', '1');
    $this->db->from('Transactions');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($data['buyerSigned'] != 1 || $data['buyerPaid'] != 1) {

        if($this->get_transaction_count_for_trade($data['tradeId']) > 1) {
          $data['multiBuyer'] = true;
        } else {
          $data['multiBuyer'] = false;
        }

        array_push($return, $data);
      }
    }

    return $return;

  }

  function get_trade_transactions_by_signer($userId) {
    $this->db->select('Transactions.*, ActiveListings.listingId, Trades.*, Trades.status as tStatus, States.State, States.name, Bids.bidId, TaxYear.taxYear, IncentivePrograms.ProgramName, PendingListings.stateCertNum, PendingListings.projectNameExt');
    $this->db->where('Transactions.tradeId IS NOT NULL');
    $this->db->where('Transactions.taxpayerUserId', $userId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($data['buyerSigned'] != 1 || $data['buyerPaid'] != 1) {

        if($this->get_transaction_count_for_trade($data['tradeId']) > 1) {
          $data['multiBuyer'] = true;
        } else {
          $data['multiBuyer'] = false;
        }

      }
      $data['itemType'] = 'transaction';
      array_push($return, $data);
    }

    return $return;

  }

  /*in Trading */
  function get_transaction_ids_of_trade($tradeId) {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId', $tradeId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data['transactionId']);
    }

    return $return;

  }

  /*in Trading */
  function get_transaction_by_id($transactionId) {
    $this->db->select('Transactions.*, ActiveListings.listingId, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpEmailSigner');
    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->from('Transactions');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("Taxpayers", "Taxpayers.taxpayerId = Transactions.taxpayerId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  /*in Trading */
  function get_trade_by_transaction_id($transactionId) {
    $this->db->select('Trades.*, ActiveListings.listingId');
    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->from('Trades');
    $this->db->join("Transactions", "Trades.tradeId = Transactions.tradeId", 'left');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  /*in Trading */
  function add_pending_transaction($bidId, $buyerAccountId, $taxpayerId, $tCreditAmount, $openBidId) {
    if($taxpayerId == 0 || $taxpayerId == "") {
      unset($taxpayerId);
    }
    $data = [
        'tBidId'         => $bidId,
        'tOpenBidId'     => $openBidId,
        'buyerAccountId' => $buyerAccountId,
        'taxpayerId'     => $taxpayerId,
        'tStage'         => null,
        'tCreditAmount'  => $tCreditAmount,
        'tDmaId'         => $this->cisession->userdata('dmaId'),
    ];

    $this->db->insert('Transactions', $data);

    return $this->db->insert_id();
  }

  /*in Trading */
  function update_pending_transaction($transactionId, $tCreditAmount) {
    $data = [
        'tCreditAmount' => $tCreditAmount,
    ];

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;
  }

  /*in Trading */
  function delete_transaction($transactionId) {
    $data = [
        'tDeleted' => 1,
    ];

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;
  }

  /*in Trading */
  function activate_transactions_for_a_sale($bidId, $tradeId) {
    $data = [
        'tradeId'    => $tradeId,
        'tStage'     => 0,
        'tTimestamp' => time(),
    ];

    $this->db->where('tBidId', $bidId);
    $this->db->update('Transactions', $data);
  }

  function update_trade_seller_sign($tradeId) {
    $data = [
        'sellerSigned'      => 1,
        'sellerSignedDate'  => time(),
        'tUpdatedTimestamp' => time(),
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  function update_transaction_buyer_sign($transactionId, $tradeId) {
    $data = [
        'buyerSigned'     => 1,
        'buyerSignedDate' => time(),
    ];

    $this->db->where('transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    $data2 = [
        'tUpdatedTimestamp' => time(),
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data2);

  }

  function update_transaction_buyer_pay_choice($transactionId, $buyerPayMethod, $tradeId) {
    $data = [
        'buyerPayMethod' => $buyerPayMethod,
    ];

    $this->db->where('transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    $data2 = [
        'tUpdatedTimestamp' => time(),
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data2);

  }

  function update_transaction_stage($transactionId, $stage, $tradeId) {

    if($stage == 1) {
      $data = [
          'tStage' => $stage,
      ];
    } else {
      $finishedStage = $stage - 1;
      $stageDateKey = 'stage' . $finishedStage . 'date';
      $data = [
          'tStage'      => $stage,
          $stageDateKey => time(),
      ];
    }

    $this->db->where('transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    $data2 = [
        'tUpdatedTimestamp' => time(),
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data2);

  }

  /* in Trading */
  function connect_taxpayer_to_trade($tradeId, $userId) {
    $data = [
        'taxpayerUserId' => $userId,
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  /* in Trading */
  function connect_taxpayer_to_transaction($transactionId, $userId) {
    $data = [
        'taxpayerUserId' => $userId,
    ];

    $this->db->where('transactionId', $transactionId);
    $this->db->update('Transactions', $data);
  }

  function get_all_recent_updated_sales($sinceTime) {

    $this->db->select('tradeId, Trades.timeStamp, Trades.status, Trades.accountId, Trades.sellerSigned, Trades.sellerSignedDate, Trades.settlementDate, Bids.accountId as BaccountId, Bids.bDmaMemberId, Trades.listingId, Trades.tDmaMemberId, Trades.brokeDate, tradeSize as size, tradePrice as price, IncentivePrograms.State, States.name, IncentivePrograms.ProgramName, IncentivePrograms.Sector,IncentivePrograms.OIXIncentiveId,ActiveListings.taxYearId, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear, Accounts.companyName as buyerAccountName, PendingListings.cTaxpayerId, PendingListings.cDmaMemberId, ActiveListings.listedBy');
    $array = ['Trades.tUpdatedTimestamp >=' => $sinceTime];
    $this->db->where($array);

    $this->db->from('Trades');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $data['OffSettingTaxList'] = $this->CreditListings->get_short_offsets_from_program($data['OffSettingTaxList']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_all_trades_in_closing_process() {

    //24 hours ago
    $time24Hours = time() - 86400;

    $this->db->select('tradeId, Trades.timeStamp, Trades.status, Trades.accountId, Trades.sellerSigned, Trades.sellerSignedDate, Trades.settlementDate, Bids.accountId as BaccountId, Bids.bDmaMemberId, Trades.listingId, Trades.tDmaMemberId, Trades.brokeDate, tradeSize as size, tradePrice as price, IncentivePrograms.State, States.name, IncentivePrograms.ProgramName, IncentivePrograms.Sector,IncentivePrograms.OIXIncentiveId,ActiveListings.taxYearId, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear, Accounts.companyName as buyerAccountName, PendingListings.cTaxpayerId, PendingListings.cDmaMemberId, ActiveListings.listedBy, ActiveListings.originalOfferSize, ActiveListings.offerSize, PendingListings.stateCertNum, PendingListings.projectNameExt');

    $this->db->where("Trades.settlementDate >", $time24Hours);
    $this->db->or_where("Trades.status", 1);

    $this->db->from('Trades');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $data['OffSettingTaxList'] = $this->CreditListings->get_short_offsets_from_program($data['OffSettingTaxList']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_docToSign_by_id($dsId) {
    $this->db->select('docsToSign.*');
    $this->db->where('docsToSign.dsId', $dsId);
    $this->db->from('docsToSign');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['signatures'] = $this->get_signatures_for_doc($dsId);

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
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

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  /* THIS FUNCTION IS MOVED TO DOCS MODEL */
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

  function getTradesSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('Trades.*, ActiveListings.taxYearId, ActiveListings.listedBy, PendingListings.cDmaMemberId, States.name, States.state, TaxYear.taxYear, ActiveListings.offerSize, ActiveListings.offerPrice, IncentivePrograms.ProgramName');

    $this->db->where("Trades.timeStamp >", $checkSince);
    $this->db->where('Trades.tradeType', 'oix_marketplace_trade');
    $this->db->join("ActiveListings", "Trades.listingId = ActiveListings.listingId", 'left');
    $this->db->join("PendingListings", "Trades.listingId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->order_by("Trades.timeStamp desc");
    $query = $this->db->get('Trades');

    $return = [];
    foreach($query->result_array() as $data) {

      if(!in_array($data['accountId'], $this->config->item('oix_tester_ids'))) {
        array_push($return, $data);
      }

    }

    return $return;

  }

  //// BEGIN - functions for Back End admin scripts ////

  function update_accountId_on_trade($tradeId, $accountId) {

    $data = [
        'accountId' => $accountId,
    ];

    $this->db->where('Trades.tradeId', $tradeId);
    $this->db->update('Trades', $data);

  }

  function create_transaction_for_trade($dmaId, $tData) {

    $data = [
        'tradeId'        => $tData['tradeId'],
        'tBidId'         => $tData['bidId'],
        'buyerAccountId' => $tData['bidAccountId'],
        'tStage'         => 5,
        'buyerSigReady'  => 1,
        'buyerSigned'    => 1,
        'buyerPayMethod' => 1,
        'tCreditAmount'  => $tData['tradeSize'],
        'tDmaId'         => $dmaId,
    ];

    $this->db->insert('Transactions', $data);

    return $this->db->insert_id();

  }

  function backend_update_account_id_on_trade($tradeId, $accountId) {

    $data = [
        'accountId' => $accountId,
    ];

    $this->db->where('Trades.tradeId', $tradeId);
    $this->db->update('Trades', $data);

    return true;

  }

  function complete_trade_hack($tradeId, $brokeDate) {
    $data = [
        'status'         => 2,
        'settlementDate' => $brokeDate,
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  //// END - functions for Back End admin scripts ////

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
