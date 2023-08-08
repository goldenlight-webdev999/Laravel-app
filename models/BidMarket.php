<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class BidMarket extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();

    $this->load->model('DmaAccounts');
    $this->load->library(['session']);

  }

  function get_current_market($type = "") {
    $this->db->select('openBidId,OpenBids.timeStamp,accountId,accountType,portalName,portalurl,OffsetId, OpenBids.State,OpenBids.taxYearId, marketBinding, bidExpirationDate, minimumCreditIncrement, bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, TaxYear.taxYear, forClient, cert_status_type.cert_status_name, ProgramType.ProgramTypeName,States.name, Accounts.email,OpenBids.listingId, PendingListings.listedBy');
    $this->db->from('OpenBids');

    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("Accounts", "Accounts.userId = accountId", 'left');

    $this->db->join("PendingListings", "PendingListings.listingId = OpenBids.openBidId", 'left');
    //$this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("States", "States.state = OpenBids.State");

    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where('OpenBids.marketBinding = 1');
    $this->db->where("OpenBids.PostBIDIOIonTheOIX = 'Yes' ");

    if($type == 'FED') {
      $this->db->where('OpenBids.State', 'FED');
    } else {
      if($type == 'ST') {
        $this->db->where_not_in('OpenBids.State', 'FED');
      } else {
        if($type == 'SYND') {
          $this->db->where('OpenBids.State', 'SYND');

        }
      }
    }

    if($this->input->post('sortType') && $this->input->post('sortType') == "asc") {
      $this->db->order_by("OpenBids.timeStamp asc");
    } else {
      $this->db->order_by("OpenBids.timeStamp desc");
    }

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_consolidated_market($type = "") {
    $this->db->select('openBidId,OpenBids.timeStamp,accountId,accountType,portalName,portalurl,OffsetId, OpenBids.State,OpenBids.taxYearId, marketBinding, bidExpirationDate, minimumCreditIncrement, bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, desiredCreditProgram,TaxYear.taxYear, forClient, cert_status_type.cert_status_name, ProgramType.ProgramTypeName,States.name, Accounts.email,OpenBids.listingId, PendingListings.listedBy, ActiveListings.listedBy as alistedBy,OpenBids.postBidCustomerAssist,OpenBids.postBidAnonymously');
    $this->db->from('OpenBids');

    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("ActiveListings", "OpenBids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("Accounts", "Accounts.userId = accountId", 'left');

    $this->db->join("PendingListings", "PendingListings.listingId = OpenBids.openBidId", 'left');
    //$this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("States", "States.state = OpenBids.State");

    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where("OpenBids.PostBIDIOIonTheOIX = 'Yes' ");

    if($type == 'FED') {
      $this->db->where('OpenBids.State', 'FED');
    } else {
      if($type == 'ST') {
        $this->db->where_not_in('OpenBids.State', 'FED');
      } else {
        if($type == 'SYND') {
          $this->db->where('OpenBids.State', 'SYND');

        }
      }
    }

    if($this->input->post('sortType') && $this->input->post('sortType') == "asc") {
      $this->db->order_by("OpenBids.timeStamp asc");
    } else {
      $this->db->order_by("OpenBids.timeStamp desc");
    }

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_open_bids_listed_all($type = "") {
    $this->db->select('openBidId,OpenBids.timeStamp,accountId,accountType,portalName,portalurl,OffsetId, OpenBids.State,OpenBids.taxYearId, marketBinding, bidExpirationDate, minimumCreditIncrement, bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, desiredCreditProgram,TaxYear.taxYear, forClient, cert_status_type.cert_status_name, ProgramType.ProgramTypeName,States.name, Accounts.email,OpenBids.listingId, PendingListings.listedBy, ActiveListings.listedBy as alistedBy,OpenBids.postBidCustomerAssist,OpenBids.postBidAnonymously');
    $this->db->from('OpenBids');

    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("ActiveListings", "OpenBids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("Accounts", "Accounts.userId = accountId", 'left');

    $this->db->join("PendingListings", "PendingListings.listingId = OpenBids.openBidId", 'left');
    //$this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("States", "States.state = OpenBids.State");

    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where("OpenBids.PostBIDIOIonTheOIX = 'Yes' ");

    $this->db->where("OpenBids.accountId != 338 ");

    if($type == 'FED') {
      $this->db->where('OpenBids.State', 'FED');
    } else {
      if($type == 'ST') {
        $this->db->where_not_in('OpenBids.State', 'FED');
      } else {
        if($type == 'SYND') {
          $this->db->where('OpenBids.State', 'SYND');

        }
      }
    }

    if($this->input->post('sortType') && $this->input->post('sortType') == "asc") {
      $this->db->order_by("OpenBids.timeStamp asc");
    } else {
      $this->db->order_by("OpenBids.timeStamp desc");
    }

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_current_market_only_bids($order = '') {
    $this->db->select('openBidId,OpenBids.timeStamp,accountId,accountType,portalName,portalurl,OffsetId, OpenBids.State,OpenBids.taxYearId, marketBinding, bidExpirationDate, minimumCreditIncrement, bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, TaxYear.taxYear, forClient, cert_status_type.cert_status_name, ProgramType.ProgramTypeName,States.name, Accounts.email,OpenBids.listingId');
    $this->db->from('OpenBids');

    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("Accounts", "Accounts.userId = accountId", 'left');

    $this->db->join("PendingListings", "PendingListings.listingId = OpenBids.openBidId", 'left');
    //$this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("States", "States.state = OpenBids.State");

    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where('(OpenBids.marketBinding IS NULL OR OpenBids.marketBinding = 0)');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where("OpenBids.PostBIDIOIonTheOIX = 'Yes' ");
    if($order == "asc") {
      $this->db->order_by("openBidId asc");
    } else {
      $this->db->order_by("openBidId desc");
    }

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_bid_details($id) {
    $this->db->select('openBidId,timeStamp,accountId,OffsetId, State,taxYearId,bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName,country, TaxYear.taxYear,forClient');
    $this->db->from('OpenBids');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where('OpenBids.openBidId', $id);
    $query = $this->db->get();

    return $query->row_array();

  }

  function get_bid_to_edit($id) {

    $this->db->select('Bids.*');
    $this->db->where('Bids.bidId', $id);
    $this->db->from('Bids');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['bid_price_percentage'] = $data['bidPrice'];

      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_bid_participants($bidId) {

    $this->db->select('Bids.accountId as mainBuyerId, Bids.bDmaMemberId as subBuyerId');
    $this->db->where('Bids.bidId', $bidId);
    $this->db->from('Bids');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_binding_bid_details($id) {
    $this->db->select('openBidId,OpenBids.timeStamp,OpenBids.accountId,OffsetId, OpenBids.State,OpenBids.taxYearId,OpenBids.bidSize, OpenBids.bidPrice, OpenBids.bidTotal, OpenBids.BidGoodUntil,offsetName, TaxYear.taxYear,OpenBids.forClient, country, OpenBids.bidExpirationDate, OpenBids.minimumCreditIncrement, OpenBids.listingId, listedIds, OpenBids.deleteMarker, OpenBids.allOrNone, desiredCreditProgram, PostBIDIOIonTheOIX, postBidCustomerAssist, Bids.bidId');
    $this->db->from('OpenBids');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("Bids", "OpenBids.listingId = Bids.listingId", 'left');
    $this->db->where("(OpenBids.deleteMarker is null OR OpenBids.deleteMarker = 0)");
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where('OpenBids.openBidId', $id);
    $query = $this->db->get();

    return $query->row_array();

  }

  function get_buy_order_details($id) {
    $this->db->select('openBidId,OpenBids.timeStamp,OpenBids.accountId,OpenBids.OffsetId, OpenBids.State, OpenBids.State as state, States.name, OpenBids.taxYearId,bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, TaxYear.taxYear,OpenBids.forClient, country, bidExpirationDate, minimumCreditIncrement, OpenBids.listingId, listedIds, OpenBids.deleteMarker, OpenBids.allOrNone, OpenBids.changedPS, desiredCreditProgram, PostBIDIOIonTheOIX, postBidCustomerAssist, PendingListings.listedBy, PendingListings.cDmaMemberId, (SELECT links FROM remote_links WHERE remote_links.active_status = 1 AND remote_links.listing_id = (SELECT OpenBids.listingId FROM OpenBids WHERE OpenBids.openBidId = ' . $id . ')) AS links,(select eoAccountId from Bids WHERE Bids.listingId=OpenBids.listingId LIMIT 1) AS eoAccountId');
    $this->db->from('OpenBids');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("States", "States.state = OpenBids.State", 'left');
    $this->db->join("PendingListings", "OpenBids.listingId = PendingListings.listingId", 'left');

    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where('OpenBids.openBidId', $id);
    $this->db->join("remote_links", "OpenBids.listingId = remote_links.listing_id", 'left');
    $query = $this->db->get();

    return $query->row_array();

  }

  function get_buy_orders_by_credit($creditId) {
    $this->db->select('openBidId,timeStamp,accountId,OffsetId, State,taxYearId,bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, TaxYear.taxYear,forClient, country, bidExpirationDate, minimumCreditIncrement, listingId, listedIds, deleteMarker, allOrNone, desiredCreditProgram, PostBIDIOIonTheOIX, postBidCustomerAssist, (select eoAccountId from Bids WHERE Bids.listingId=OpenBids.listingId LIMIT 1) AS eoAccountId');
    $this->db->from('OpenBids');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->where("(OpenBids.deleteMarker is null OR OpenBids.deleteMarker = 0)");
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where('OpenBids.listingId', $creditId);
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_bid_by_listing($listingId) {
    $this->db->select('*');
    $this->db->from('Bids');
    $this->db->where('Bids.listingId', $listingId);
    $this->db->where('(Bids.bidExpirationDate >= NOW() OR Bids.bidExpirationDate is null)');
    $query = $this->db->get();

    return $query->row_array();
  }

  function get_bid_by_id($bidId) {
    $this->db->select('Bids.*, Bids.timeStamp as bidTimeStamp, Bids.deleteMarker as bidDeleteMarker, Accounts.companyName, Accounts.accountType, ActiveListings.*, PendingListings.cDmaMemberId, States.name, States.state, TaxYear.taxYear');
    $this->db->from('Bids');
    $this->db->where('Bids.bidId', $bidId);
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $this->db->join("ActiveListings", "Bids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("PendingListings", "Bids.listingId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $query = $this->db->get();

    return $query->row_array();
  }

  function get_openbid_by_id($id) {

    $this->db->select('OpenBids.*, TaxYear.taxYear, States.state, States.name');
    $this->db->from('OpenBids');
    $this->db->where('OpenBids.openBidId', $id);
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("States", "OpenBids.State = States.state", 'left');
    $query = $this->db->get();

    return $query->row_array();

  }

  function get_credit_details($id) {
    $this->db->select('openBidId,OpenBids.timeStamp,OpenBids.accountId,OffsetId,firstName,lastName,companyName,email,phone,OpenBids.State,OpenBids.taxYearId,bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, TaxYear.taxYear, forClient, ActiveListings.OIXIncentiveId');
    $this->db->from('OpenBids');
    $this->db->join("Accounts", "OpenBids.accountId = Accounts.userId", 'left');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("ActiveListings", "OpenBids.listingId = ActiveListings.listingId", 'left');
    $this->db->where('OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where('OpenBids.openBidId', $id);
    $this->db->order_by("openBidId desc");

    $query = $this->db->get();

    return $query->row_array();

  }

  function get_open_bids_listed_filtered($country, $id, $offsets, $taxyears, $type = "") {

    if(!empty($taxyears)) {
      $this->db->where('OpenBids.taxYearId <=', strtoupper($taxyears));
    }

    if(!empty($id)) {
      $this->db->where('OpenBids.State', strtoupper($id));
    }

    if(!empty($offsets)) {
      $this->db->where('OpenBids.OffsetId', strtoupper($offsets));
    }

    $this->db->select('openBidId,OpenBids.timeStamp,accountId,accountType,portalName,portalurl,OffsetId, OpenBids.State,OpenBids.taxYearId, marketBinding, bidExpirationDate, minimumCreditIncrement, bidSize,bidPrice,bidTotal,BidGoodUntil,offsetName, desiredCreditProgram,TaxYear.taxYear, forClient, cert_status_type.cert_status_name, ProgramType.ProgramTypeName,States.name, Accounts.email,OpenBids.listingId,OpenBids.postBidCustomerAssist,,OpenBids.postBidAnonymously');
    $this->db->from('OpenBids');

    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("ActiveListings", "OpenBids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("Accounts", "Accounts.userId = accountId", 'left');

    $this->db->join("PendingListings", "PendingListings.listingId = OpenBids.openBidId", 'left');
    //$this->db->join("PendingListings", "PendingListings.listingId = ActiveListings.listingId", 'left');
    $this->db->join("cert_status_type", "cert_status_type.cert_status_id = PendingListings.certificationStatus", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = PendingListings.credit_type_id", 'left');
    $this->db->join("States", "States.state = OpenBids.State");

    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where('(OpenBids.bidExpirationDate >= NOW() OR OpenBids.bidExpirationDate is null)');
    $this->db->where("OpenBids.PostBIDIOIonTheOIX = 'Yes' ");

    $this->db->where("OpenBids.accountId != 338 ");

    if($type == 'FED') {
      $this->db->where('OpenBids.State', 'FED');
    } else {
      if($type == 'ST') {
        $this->db->where_not_in('OpenBids.State', 'FED');
      } else {
        if($type == 'SYND') {
          $this->db->where('OpenBids.State', 'SYND');

        }
      }
    }

    $this->db->order_by("OpenBids.timeStamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);
    }

    return $return;

  }

  function get_short_offsets_from_program($offsets) {
    $offsets_arr = explode(",", $offsets);
    $return_string = "";
    foreach($offsets_arr as $key => $pair) {
      $query = $this->db->get_where('Offsets', ['id' => $pair]);
      $this_o = $query->row();
      $return_string .= $this_o->shortOffset;
      if($key < count($offsets_arr) - 1) {
        $return_string .= ", ";
      }
    }

    return $return_string;
  }

  function get_my_buy_orders($accountId) {
    $this->db->select('openBidId,OpenBids.timeStamp,OpenBids.accountId,OffsetId,OpenBids.State,OpenBids.taxYearId,bidSize as size,bidPrice as price,bidTotal as total,BidGoodUntil,offsetName, OpenBids.bidExpirationDate, TaxYear.taxYear,forClient,marketBinding,PostBIDIOIonTheOIX, OpenBids.deleteMarker, OpenBids.listingId, listedIds,postBidCustomerAssist,postBidAnonymously, States.state, States.name, States.sLatitude, States.sLongitude, PendingListings.listingDate, PendingListings.listedBy, Accounts.companyName');
    $this->db->where('OpenBids.accountId', $accountId);
    $this->db->where('OpenBids.postBidCustomerAssist', 1);
    //$this->db->where('OpenBids.deleteMarker is null');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("States", "OpenBids.State = States.state", 'left');
    $this->db->join("PendingListings", "OpenBids.listingId = PendingListings.listingId", 'left');
    $this->db->join("Accounts", "PendingListings.listedBy = Accounts.userId", 'left');
    $this->db->from('OpenBids');
    $this->db->order_by("OpenBids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      if(str_replace([' ', ','], '', $data['listedIds']) > 0) {
        $data['attachedToCredit'] = "Yes";
        $data['creditData'] = $this->get_credit_details($data['listingId']);
      } else {
        $data['attachedToCredit'] = "No";
        $data['creditData']['companyName'] = "";
        $data['creditData']['OIXIncentiveId'] = "";
      }

      array_push($return, $data);
    }

    return $return;

  }

  function get_my_openbids_direct($id) {
    $this->db->select('openBidId,OpenBids.timeStamp,OpenBids.accountId,OffsetId,OpenBids.State,taxYearId,OpenBids.bidSize as size,OpenBids.bidPrice as price,OpenBids.bidTotal as total,OpenBids.deleteMarker,BidGoodUntil,OpenBids.bidExpirationDate, offsetName, TaxYear.taxYear,OpenBids.forClient,marketBinding,PostBIDIOIonTheOIX, OpenBids.listingId, listedIds, postBidCustomerAssist,postBidAnonymously,States.name, Bids.bidId');
    $this->db->where('OpenBids.accountId', $id);
    $this->db->where('OpenBids.deleteMarker is null');
    //$this->db->where('OpenBids.postBidCustomerAssist is NULL');
    $where = "(OpenBids.postBidCustomerAssist is NULL OR OpenBids.postBidCustomerAssist = 0)";
    $this->db->where($where);
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("States", "OpenBids.State = States.state", 'left');
    $this->db->join("Bids", "OpenBids.listingId = Bids.listingId", 'left');
    $this->db->from('OpenBids');
    $this->db->order_by("OpenBids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      $data['transactions'] = $this->Trades->get_transactions_of_bid($data['bidId']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_my_bids_and_buyorders($accountId) {

    //Setup final array to merge everything
    $myBandBO = [];

    ////// GET BUY ORDERS //////
    $myBuyOrdersData = $this->get_my_buy_orders($accountId);
    $myBOClean = [];

    //Normalize Buy Orders and add to $myBandBO array
    for($bo = 0; $bo < sizeof($myBuyOrdersData); $bo++) {

      //If buy order is listed AND incentive ID isn't null, then it means it is a listed bid against a credit, which means we don't include it
      if($myBuyOrdersData[$bo]['deleteMarker'] == 1) {

      } else {

        $myBOClean[$bo]['bidType'] = 'buyorder';
        $myBOClean[$bo]['timeStamp'] = $myBuyOrdersData[$bo]['timeStamp'];
        $myBOClean[$bo]['State'] = $myBuyOrdersData[$bo]['State'];
        $myBOClean[$bo]['name'] = $myBuyOrdersData[$bo]['name'];
        $myBOClean[$bo]['taxYear'] = $myBuyOrdersData[$bo]['taxYear'];
        $myBOClean[$bo]['creditId'] = $myBuyOrdersData[$bo]['listingId'];
        $myBOClean[$bo]['bidId'] = $myBuyOrdersData[$bo]['openBidId'];
        $myBOClean[$bo]['creditIdFull'] = $myBuyOrdersData[$bo]['State'] . $myBuyOrdersData[$bo]['listingId'];
        $myBOClean[$bo]['bidIdFull'] = $myBuyOrdersData[$bo]['State'] . $myBuyOrdersData[$bo]['openBidId'];
        $myBOClean[$bo]['attachedToCredit'] = $myBuyOrdersData[$bo]['attachedToCredit'];
        $myBOClean[$bo]['sLatitude'] = $myBuyOrdersData[$bo]['sLatitude'];
        $myBOClean[$bo]['sLongitude'] = $myBuyOrdersData[$bo]['sLongitude'];

        if($myBuyOrdersData[$bo]['listingDate'] != "") {
          $myBOClean[$bo]['listingDate'] = substr($myBuyOrdersData[$bo]['listingDate'], 0, strrpos($myBuyOrdersData[$bo]['listingDate'], ' '));
        } else {
          $myBOClean[$bo]['listingDate'] = "-";
        }

        $myBOClean[$bo]['deleteMarker'] = $myBuyOrdersData[$bo]['deleteMarker'];
        $myBOClean[$bo]['isActive'] = $myBuyOrdersData[$bo]['PostBIDIOIonTheOIX'];
        $myBOClean[$bo]['size'] = $myBuyOrdersData[$bo]['size'];
        $myBOClean[$bo]['price'] = $myBuyOrdersData[$bo]['price'];
        $myBOClean[$bo]['priceTotal'] = $myBuyOrdersData[$bo]['size'] * $myBuyOrdersData[$bo]['price'];
        $myBOClean[$bo]['offsetName'] = $myBuyOrdersData[$bo]['offsetName'];
        $myBOClean[$bo]['taxTypeNames'] = null;
        $myBOClean[$bo]['sellerName'] = $myBuyOrdersData[$bo]['companyName'];
        $myBOClean[$bo]['listingId'] = $myBuyOrdersData[$bo]['listingId'];
        $myBOClean[$bo]['bidExpirationDate'] = $myBuyOrdersData[$bo]['bidExpirationDate'];
        $myBOClean[$bo]['transactions'] = '';

      }

    }

    ////// GET BIDS //////
    $myBidsData = $this->get_my_bids($accountId);
    $myBClean = [];

    //Normalize Bids and add to $myBandBO array
    for($b = 0; $b < sizeof($myBidsData); $b++) {

      $myBClean[$b]['bidType'] = 'bid';
      $myBClean[$b]['timeStamp'] = $myBidsData[$b]['timeStamp'];
      $myBClean[$b]['State'] = $myBidsData[$b]['State'];
      $myBClean[$b]['name'] = $myBidsData[$b]['name'];
      $myBClean[$b]['taxYear'] = $myBidsData[$b]['taxYear'];
      $myBClean[$b]['creditId'] = $myBidsData[$b]['listingId'];
      $myBClean[$b]['bidId'] = $myBidsData[$b]['bidId'];
      $myBClean[$b]['creditIdFull'] = $myBidsData[$b]['State'] . $myBidsData[$b]['listingId'];
      $myBClean[$b]['bidIdFull'] = $myBidsData[$b]['State'] . $myBidsData[$b]['bidId'];
      $myBClean[$b]['attachedToCredit'] = $myBidsData[$b]['attachedToCredit'];
      if($myBidsData[$b]['timeStamp'] != "") {
        $myBClean[$b]['listingDate'] = substr($myBidsData[$b]['timeStamp'], 0, strrpos($myBidsData[$b]['timeStamp'], ' '));
      } else {
        $myBClean[$b]['listingDate'] = "-";
      }
      $myBClean[$b]['deleteMarker'] = $myBidsData[$b]['deleteMarker'];
      $myBClean[$b]['isActive'] = 'Yes';
      $myBClean[$b]['size'] = $myBidsData[$b]['size'];
      $myBClean[$b]['price'] = $myBidsData[$b]['price'];
      $myBClean[$b]['priceTotal'] = $myBidsData[$b]['size'] * $myBidsData[$b]['price'];
      $myBClean[$b]['offsetName'] = $myBidsData[$b]['OffSettingTaxList'];
      $myBClean[$b]['taxTypeNames'] = $myBidsData[$b]['taxTypeNames'];
      $myBClean[$b]['sellerName'] = 'name here';
      $myBClean[$b]['listingId'] = $myBidsData[$b]['listingId'];
      $myBClean[$b]['bidExpirationDate'] = $myBidsData[$b]['bidExpirationDate'];
      $myBClean[$b]['transactions'] = $myBidsData[$b]['transactions'];
      $myBClean[$b]['sLatitude'] = $myBidsData[$b]['sLatitude'];
      $myBClean[$b]['sLongitude'] = $myBidsData[$b]['sLongitude'];

    }

    if($this->cisession->userdata('level') == 4) {

      ////// GET OPEN BIDS, IF DMA //////
      $myOpenBidsData = $this->get_my_openbids_direct($accountId);
      $myOBClean = [];

      //Normalize Open Bids
      for($ob = 0; $ob < sizeof($myOpenBidsData); $ob++) {

        $myOBClean[$ob]['bidType'] = 'openbid';
        $myOBClean[$ob]['timeStamp'] = $myOpenBidsData[$ob]['timeStamp'];
        $myOBClean[$ob]['State'] = $myOpenBidsData[$ob]['State'];
        $myOBClean[$ob]['name'] = $myOpenBidsData[$ob]['name'];
        $myOBClean[$ob]['taxYear'] = $myOpenBidsData[$ob]['taxYear'];
        $myOBClean[$ob]['creditId'] = $myOpenBidsData[$ob]['listingId'];
        $myOBClean[$ob]['bidId'] = $myOpenBidsData[$ob]['listingId'];
        $myOBClean[$ob]['creditIdFull'] = $myOpenBidsData[$ob]['State'] . $myOpenBidsData[$ob]['listingId'];
        $myOBClean[$ob]['bidIdFull'] = $myOpenBidsData[$ob]['State'] . $myOpenBidsData[$ob]['listingId'];
        $myOBClean[$ob]['attachedToCredit'] = 'no';
        $myOBClean[$ob]['listingDate'] = substr($myOpenBidsData[$ob]['timeStamp'], 0, strrpos($myOpenBidsData[$ob]['timeStamp'], ' '));
        $myOBClean[$ob]['deleteMarker'] = $myOpenBidsData[$ob]['deleteMarker'];
        $myOBClean[$ob]['isActive'] = 'Yes';
        $myOBClean[$ob]['size'] = $myOpenBidsData[$ob]['size'];
        $myOBClean[$ob]['price'] = $myOpenBidsData[$ob]['price'];
        $myOBClean[$ob]['priceTotal'] = $myOpenBidsData[$ob]['size'] * $myOpenBidsData[$ob]['price'];
        $myOBClean[$ob]['offsetName'] = $myOpenBidsData[$ob]['offsetName'];
        $myOBClean[$ob]['sellerName'] = '';
        $myOBClean[$ob]['listingId'] = $myOpenBidsData[$ob]['listingId'];
        $myOBClean[$ob]['bidExpirationDate'] = $myOpenBidsData[$ob]['bidExpirationDate'];
        $myOBClean[$ob]['transactions'] = $myOpenBidsData[$ob]['transactions'];

      }

      //Merge all three arrays, if DMA
      $myBandBO = array_merge($myBOClean, $myBClean, $myOBClean);

    } else {

      //Merge only two arrays, if General Member
      $myBandBO = array_merge($myBOClean, $myBClean);

    }

    $myBandBO['myBandBO'] = $myBandBO;

    $myBandBO['calendarEvents']['allEvents'] = [];
    $myBandBO['calendarEvents']['futureEvents'] = [];

    foreach($myBandBO['myBandBO'] as $bb) {
      /**/
      // bids - my bid expirations
      if($bb['bidExpirationDate'] != "") {
        $thisBidExp = strtotime($bb['bidExpirationDate']);
        $thisArray = [];
        $thisArray['type'] = 'bid_expiration_date';
        $thisArray['dateUnix'] = $thisBidExp;
        $thisArray['dateYear'] = date('Y', $thisBidExp);
        $thisArray['dateMonth'] = date('m', $thisBidExp);
        $thisArray['dateDay'] = date('d', $thisBidExp);
        $thisArray['dateQuarter'] = $this->getQuarter(date('n', $thisBidExp));
        $thisArray['dateYearQuarter'] = $thisArray['dateYear'] . "Q" . $thisArray['dateQuarter'];
        $thisArray['ymd'] = date('Y-m-d', $thisBidExp);
        $thisArray['mdy'] = date('m-d-Y', $thisBidExp);
        $thisArray['listingId'] = $bb['listingId'];
        $thisArray['listingFull'] = $bb['State'] . $bb['listingId'];
        $thisArray['bidId'] = $bb['bidId'];
        $thisArray['bidType'] = $bb['bidType'];
        $thisArray['bidSize'] = $bb['size'];
        $thisArray['bidPrice'] = $bb['price'];
        $thisArray['sLatitude'] = $bb['sLatitude'];
        $thisArray['sLongitude'] = $bb['sLongitude'];
        $thisArray['jurisdictionCode'] = $bb['State'];
        $thisArray['jurisdictionName'] = $bb['name'];
        $thisArray['eventTitle'] = "Bid Expiration";
        $thisArray['connectedTo'] = $thisArray['listingFull'];
        $thisArray['actionLink'] = base_url() . "dashboard/" . $bb['bidType'] . "/" . $bb['bidId'];
        array_push($myBandBO['calendarEvents']['allEvents'], $thisArray);
        if($thisBidExp > time()) {
          array_push($myBandBO['calendarEvents']['futureEvents'], $thisArray);
        }
      }

    }

    return $myBandBO;

    // Sort by date descending
    /*
    $merge_buying_by_date = array();
    foreach ($myBandBO as $key => $row)
    {
        $merge_buying_by_date[$key] = $row['timeStamp'];
    }
    array_multisort($merge_buying_by_date, SORT_DESC, $myBandBO);

    return $merge_buying_by_date;
    */

  }

  function getQuarter($month) {
    if($month >= 1 && $month <= 3) {
      $getQuarter = 1;
    }
    if($month >= 4 && $month <= 6) {
      $getQuarter = 2;
    }
    if($month >= 7 && $month <= 9) {
      $getQuarter = 3;
    }
    if($month >= 10 && $month <= 12) {
      $getQuarter = 4;
    }

    return $getQuarter;
  }

  function get_tax_types($taxTypeIds) {
    $offsets_arr = explode(",", $taxTypeIds);
    $return_string = "";
    if(sizeof($offsets_arr) > 0) {
      foreach($offsets_arr as $key => $pair) {
        $query = $this->db->get_where('IPTaxTypes', ['id' => $pair]);
        $this_o = $query->row();
        if(isset($this_o->tax_type)) {
          $return_string .= $this_o->tax_type;
          if($key < count($offsets_arr) - 1) {
            $return_string .= ", ";
          }
        }
      }
    }

    return $return_string;
  }

  function get_my_bids($id) {
    $this->db->select('Bids.bidId, Bids.timeStamp, Bids.accountId, Bids.listingId, Bids.deleteMarker, bidSize as size, bidPrice as price, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IPSectors.sector, IncentivePrograms.OffSettingTaxList,OffsetLocked, Bids.bidExpirationDate, Bids.eoAccountId,TaxYear.taxYear, States.state, States.name, States.sLatitude, States.sLongitude, ActiveListings.offerSize,ActiveListings.offerPrice, ActiveListings.taxTypeIds');
    $this->db->where('(Trades.status is null or originalBidSize is not null)');
    $this->db->where('Bids.accountId', $id);
    $this->db->where('Bids.deleteMarker is null');
    $this->db->where('ActiveListings.traded is null');
    $this->db->where('ActiveListings.deleteMarker is null');
    $this->db->from('Bids');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.TaxYearId = TaxYear.id", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->order_by("Bids.timestamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      if($data['OffSettingTaxList'] != "") {
        $data['OffSettingTaxList'] = $this->get_short_offsets_from_program($data['OffSettingTaxList']);
      } else {
        $data['OffSettingTaxList'] = null;
      }

      $data['taxTypeNames'] = $this->get_tax_types($data['taxTypeIds']);

      $data['bidActiveFlag'] = 'Yes';
      $data['attachedToCredit'] = 'Yes';

      $data['transactions'] = $this->Trades->get_transactions_of_bid($data['bidId']);

      array_push($return, $data);
    }

    return $return;

  }

  function add_open_bid_by_member() {
    if($this->input->post('listingId') > 0) {
      $pendingId = $this->input->post('listingId');
      $OffsetId = $this->input->post('taxOffset');
      $taxYearId = $this->input->post('taxYearHidden');
      $State = $this->input->post('stateHidden');
      $desiredCreditProgram = "";
    } else {
      $pendingId = "";
      $OffsetId = $this->input->post('bid_offset');
      $taxYearId = $this->input->post('bid_tax_year');
      $State = $this->input->post('bid_jurisdiction');
      $desiredCreditProgram = $this->input->post('bid_desired_program');
    }

    //If the user wants to negotiate the PSA, prepare this setting for the DB
    if($this->input->post('agreeToPSA') == 'no') {
      $changedPS = 1;
    } else {
      $changedPS = 0;
    }

    $data = [
      //'openBidId'=> $openBidId,
      'timeStamp'     => date('Y-m-d H:i:s'),
      'accountId'     => $this->cisession->userdata('primUserId'),
      'obDmaMemberId' => $this->cisession->userdata('secUserId'),
      'bidSize'       => $this->input->post('bid_size'),
      'bidPrice'      => $this->input->post('bid_price_percentage'),
      'forClient'     => $this->input->post('client_purchase'),
      'bidTotal'      => $this->input->post('bid_size') * ($this->input->post('bid_price_percentage')),
      'BidGoodUntil'  => strtotime($this->input->post('good_until_date')),
      'OffsetId'      => $OffsetId,
      'taxYearId'     => $taxYearId,
      'State'         => $State,
      'updateId'      => $this->cisession->userdata('userId'),

      'updatedTime'            => date('Y-m-d H:i:s'),
      'updatedBy'              => $this->cisession->userdata('userId'),
      'country'                => 1,
      //'marketBinding' => $this->input->post('binding_bid_agreement'),
      'marketBinding'          => 1,
      'bidExpirationDate'      => ($this->input->post('good_until_date') != "") ? date('Y-m-d H:i:s', strtotime($this->input->post('good_until_date'))) : null,
      'minimumCreditIncrement' => $this->input->post('increments'),
      'desiredCreditProgram'   => $desiredCreditProgram,
      'listingId'              => $pendingId,
      'PostBIDIOIonMyPortal'   => ($this->input->post('PostBIDIOIonMyPortal')) ? $this->input->post('PostBIDIOIonMyPortal') : 'No',
      'PostBIDIOIonTheOIX'     => ($this->input->post('PostBIDIOIonTheOIX')) ? $this->input->post('PostBIDIOIonTheOIX') : 'No',
      //'allOrNone' => $this->input->post('binding_bid_agreement'),
      'allOrNone'              => $this->input->post('bid_allOrNone'),
      'postBidCustomerAssist'  => 1,
      'listedIds'              => $this->input->post('listingId') . ",",
      'changedPS'              => $changedPS,
    ];

    $this->db->insert('OpenBids', $data);
    $insertId = $this->db->insert_id();

    return $insertId;
  }

  function get_cmarket_listed_ids($id) {

    $this->db->select('listedIds');
    $this->db->from('OpenBids');
    $this->db->where('listingId', $id);
    $query = $this->db->get();

    $row = $query->result_array();

    return $row[0]['listedIds'];

  }

  function get_buyer_seller_in_bid($bidId) {

    $bid = $this->get_bid_by_id($bidId);

    //Get buyer info
    //Get DMA info for buyer and seller
    $dmaSeller = [];
    $dmaBuyer = [];

    //Get email address for buyer and seller
    //If a DMA seller
    if(sizeof($dmaSeller) > 0) {
      $sellerInfo = $this->Members_Model->get_member_by_id($bid['cDmaMemberId']);
      $data['accountTitleSeller'] = $dmaSeller['title'];
      $data['userIdSeller'] = $sellerInfo['userId'];
      $data['firstNameSeller'] = $sellerInfo['firstName'];
      $data['lastNameSeller'] = $sellerInfo['lastName'];
      $data['emailSeller'] = $sellerInfo['email'];
      $data['dmaIdSeller'] = $dmaSeller['dmaId'];
      $data['sellerType'] = 'dma';
    } else {
      //If a general member seller
      $sellerInfo = $this->Members_Model->get_member_by_id($bid['listedBy']);
      $data['accountTitleSeller'] = $sellerInfo['companyName'];
      $data['userIdSeller'] = $sellerInfo['userId'];
      $data['firstNameSeller'] = $sellerInfo['firstName'];
      $data['lastNameSeller'] = $sellerInfo['lastName'];
      $data['emailSeller'] = $sellerInfo['email'];
      $data['dmaIdSeller'] = "";
      $data['sellerType'] = 'general';
    }
    //If a DMA buyer
    if(sizeof($dmaBuyer) > 0) {
      $buyerInfo = $this->Members_Model->get_member_by_id($bid['bDmaMemberId']);
      $data['accountTitleBuyer'] = $dmaBuyer['title'];
      $data['userIdBuyer'] = $buyerInfo['userId'];
      $data['firstNameBuyer'] = $buyerInfo['firstName'];
      $data['lastNameBuyer'] = $buyerInfo['lastName'];
      $data['emailBuyer'] = $buyerInfo['email'];
      $data['dmaIdBuyer'] = $dmaBuyer['dmaId'];
      $data['buyerType'] = 'dma';
    } else {
      //If a general member buyer
      $buyerInfo = $this->Members_Model->get_member_by_id($bid['accountId']);
      $data['accountTitleBuyer'] = $buyerInfo['companyName'];
      $data['userIdBuyer'] = $buyerInfo['userId'];
      $data['firstNameBuyer'] = $buyerInfo['firstName'];
      $data['lastNameBuyer'] = $buyerInfo['lastName'];
      $data['emailBuyer'] = $buyerInfo['email'];
      $data['dmaIdBuyer'] = "";
      $data['buyerType'] = 'general';
    }

    return $data;

  }

  function get_buyer_seller_in_buyorder($buyOrderId) {

    $buyOrder = $this->get_buy_order_details($buyOrderId);

    //Get buyer info
    //Get DMA info for buyer and seller
    $dmaSeller = [];
    $dmaBuyer = [];

    //Get email address for buyer and seller
    //If a DMA seller
    if($dmaSeller != "") {
      $sellerInfo = $this->Members_Model->get_member_by_id($buyOrder['cDmaMemberId']);
      $data['accountTitleSeller'] = $dmaSeller['title'];
      $data['firstNameSeller'] = $sellerInfo['firstName'];
      $data['lastNameSeller'] = $sellerInfo['lastName'];
      $data['emailSeller'] = $sellerInfo['email'];
      $data['sellerType'] = 'dma';
    } else {
      //If a general member seller
      $sellerInfo = $this->Members_Model->get_member_by_id($buyOrder['listedBy']);
      $data['accountTitleSeller'] = $sellerInfo['companyName'];
      $data['firstNameSeller'] = $sellerInfo['firstName'];
      $data['lastNameSeller'] = $sellerInfo['lastName'];
      $data['emailSeller'] = $sellerInfo['email'];
      $data['sellerType'] = 'general';
    }
    //If a DMA buyer
    if($dmaBuyer != "") {
      $buyerInfo = $this->Members_Model->get_member_by_id($buyOrder['bDmaMemberId']);
      $data['accountTitleBuyer'] = $dmaBuyer['title'];
      $data['firstNameBuyer'] = $buyerInfo['firstName'];
      $data['lastNameBuyer'] = $buyerInfo['lastName'];
      $data['emailBuyer'] = $buyerInfo['email'];
      $data['buyerType'] = 'dma';
    } else {
      //If a general member buyer
      $buyerInfo = $this->Members_Model->get_member_by_id($buyOrder['accountId']);
      $data['accountTitleBuyer'] = $buyerInfo['companyName'];
      $data['firstNameBuyer'] = $buyerInfo['firstName'];
      $data['lastNameBuyer'] = $buyerInfo['lastName'];
      $data['emailBuyer'] = $buyerInfo['email'];
      $data['buyerType'] = 'general';
    }

    return $data;

  }

  function get_buyer_in_openbid($openBidId) {

    $openBid = $this->get_binding_bid_details($openBidId);

    //Get buyer info
    $dmaBuyer = [];

    //If a DMA buyer
    if($dmaBuyer != "") {
      $buyerInfo = $this->Members_Model->get_member_by_id($openBid['accountId']);
      $data['accountTitleBuyer'] = $dmaBuyer['title'];
      $data['firstNameBuyer'] = $buyerInfo['firstName'];
      $data['lastNameBuyer'] = $buyerInfo['lastName'];
      $data['emailBuyer'] = $buyerInfo['email'];
      $data['buyerType'] = 'dma';
    } else {
      //If a general member buyer
      $buyerInfo = $this->Members_Model->get_member_by_id($openBid['accountId']);
      $data['accountTitleBuyer'] = $buyerInfo['companyName'];
      $data['firstNameBuyer'] = $buyerInfo['firstName'];
      $data['lastNameBuyer'] = $buyerInfo['lastName'];
      $data['emailBuyer'] = $buyerInfo['email'];
      $data['buyerType'] = 'general';
    }

    return $data;

  }

  function delete_bid($bidId = "") {

    if($bidId > 0) {
      $bidId = $bidId;
    } else {
      $bidId = $_POST['bidId'];
    }

    $data = [
        'deleteMarker' => 1,
    ];

    if($bidId > 0) {
      $this->db->where('Bids.bidId', $bidId);
      $this->db->update('Bids', $data);

      return true;
    }

  }

  function delete_all_bids_against_credit($listingId) {

    $data = [
        'deleteMarker' => 1,
    ];

    $this->db->where('Bids.listingId', $listingId);
    $this->db->update('Bids', $data);

    return true;

  }

  function delete_openbid() {

    $data = [
        'deleteMarker' => 1,
    ];

    //Mark the associated BID deleted
    $this->db->where('Bids.bidId', $_POST['bidId']);
    $this->db->update('Bids', $data);

    //Mark the BUY ORDER deleted
    $this->db->where('OpenBids.openBidId', $_POST['openBidId']);
    $this->db->update('OpenBids', $data);

    return true;

  }

  function getBidsSince($seconds, $traded) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    //Convert now time into date format for Expired credits
    $timeNow = date('Y-m-d H:i:s', time());

    $this->db->select('Bids.*, ActiveListings.taxYearId, States.name, States.state, TaxYear.taxYear, ActiveListings.offerSize, ActiveListings.offerPrice,Trades.tradeId');
    $this->db->from('Bids');

    $array = ['Bids.timeStamp >' => $checkSince];
    $this->db->where($array);

    $this->db->join("ActiveListings", "Bids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');
    $this->db->order_by("Bids.timeStamp desc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      if(!in_array($data['accountId'], $this->config->item('oix_tester_ids'))) {
        if($data['offerSize'] > 0) {
          //$traded - 0 means only bids which have NOT traded. In future, 1 could be only those that have traded and 2 could be all bids
          if($traded == 0) {
            if($data['tradeId'] > 0) {
            } else {
              array_push($return, $data);
            }
          } else {
            array_push($return, $data);
          }
        }
      }
    }

    return $return;

  }

  function getOpenBidsSince($seconds) {

    $checkSince = time() - $seconds;
    $checkSince = date('Y-m-d H:i:s', $checkSince);

    $this->db->select('openBidId,OpenBids.timeStamp,OpenBids.accountId,OffsetId,OpenBids.State,OpenBids.taxYearId,bidSize,bidPrice,offsetName, TaxYear.taxYear,forClient,marketBinding,PostBIDIOIonTheOIX, OpenBids.listingId, listedIds, postBidCustomerAssist,postBidAnonymously,States.state,States.name');

    $this->db->where("OpenBids.timeStamp >", $checkSince);
    $this->db->where('(OpenBids.deleteMarker IS NULL OR OpenBids.deleteMarker = 0)');
    $this->db->where("OpenBids.PostBIDIOIonTheOIX", "Yes");

    //$this->db->join("ActiveListings","OpenBids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("TaxYear", "OpenBids.taxYearId = TaxYear.id", 'left');
    $this->db->join("Offsets", "OpenBids.OffsetId = Offsets.id", 'left');
    $this->db->join("States", "OpenBids.State = States.state", 'left');
    $this->db->from('OpenBids');

    $this->db->order_by("OpenBids.timeStamp desc");
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      if(!in_array($data['accountId'], $this->config->item('oix_tester_ids'))) {
        array_push($return, $data);
      }
    }

    return $return;

  }

  function delete_bid_full($bidId) {

    $this->db->delete('Bids', ['bidId' => $bidId]);
    $this->db->delete('Transactions', ['tBidId' => $bidId]);

  }

  function get_bids_between_dates($date1, $date2) {
    $this->db->select('Bids.*, ActiveListings.taxYearId, States.name, States.state, TaxYear.taxYear, ActiveListings.offerSize, ActiveListings.offerPrice,Trades.tradeId');

    $this->db->from('Bids');

    $this->db->join("ActiveListings", "Bids.listingId = ActiveListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("Trades", "Bids.bidId = Trades.bidId", 'left');

    $array = ['Bids.timeStamp >' => $date1, 'Bids.timeStamp <' => $date2];
    $this->db->where($array);

    $this->db->order_by("Bids.timeStamp desc");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function getBidsExpiring($seconds) {

    $checkUntil = time() + $seconds;
    $checkUntil = date('Y-m-d H:i:s', $checkUntil);
    $checkFrom = date('Y-m-d H:i:s', time());

    $this->db->select('Bids.*, Accounts.firstName, Accounts.lastName, Accounts.accountType, Accounts.companyName, States.state');

    $this->db->where('Bids.bidExpirationDate >=', $checkFrom);
    $this->db->where('Bids.bidExpirationDate <=', $checkUntil);
    $where = "(Bids.deleteMarker IS NULL)";
    $this->db->where($where);

    $this->db->join("PendingListings", "PendingListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("Accounts", "Bids.accountId = Accounts.userId", 'left');
    $query = $this->db->get('Bids');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
