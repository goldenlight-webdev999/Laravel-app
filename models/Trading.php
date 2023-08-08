<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Trading extends CI_Model {
  private $table_name = 'Trading';
  private $pending_table_name = 'PendingListings';
  private $active_table_name = 'ActiveListings';

  private $enable_exp_date = '1';

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $this->table_name;
    //$this->load->model('Docs');
    $this->load->library(['session']);
    $ci->load->model("Members_Model");

    return $ci->Members_Model;
  }






  //////////////////////////////////////
  ////// START FIELD FUNCTIONS //////
  //////////////////////////////////////

  function getUtilizationTypesFields() {

    //Totals
    $return['utilizationTotalCount'] = 0; //utilizationTotalCount
    $return['utilizationTotalCompletedCount'] = 0; //utilizationTotalCompletedCount
    $return['utilizationTotalAmount'] = 0; //utilizationTotalAmountUSD
    $return['utilizationTotalPurchasePrice'] = 0; //utilizationTotalNetAmountUSD
    $return['utilizationTotalFees'] = 0; //utilizationTotalFees
    $return['utilizationTotalAvgPrice'] = 0; //???
    //This holds ALL of the data that is processed in the loop
    $return['utilizationTypes'] = [];

    return $return;

  }




  //////////////////////////////////////
  ////// START LOOP FUNCTIONS //////
  //////////////////////////////////////

  function processUtilizationTypes($t, $data) {

    $data['summary']['utilizationTotalCount']++;
    $data['summary']['utilizationTotalCompletedCount'] += ($t['status'] == 2) ? 1 : 0;
    $data['summary']['utilizationTotalAmount'] += $t['tradeSizeUSD'];
    $data['summary']['utilizationTotalPurchasePrice'] += $t['tradeSizeUSD'] * $t['tradePrice'];

    //If utilization ID is not an existing key in the array, then create first record
    $thisKey = $t['utilizationTypeId'];
    if(!array_key_exists($thisKey, $data['summary']['utilizationTypes'])) {
      //$data['summary']['utilizationTypes'][$thisKey] = [];
      $data['summary']['utilizationTypes'][$thisKey]['textLabel'] = $t['utName'];
      $data['summary']['utilizationTypes'][$thisKey]['totalCount'] = 0;
      $data['summary']['utilizationTypes'][$thisKey]['completedCount'] = 0;
      $data['summary']['utilizationTypes'][$thisKey]['totalAmount'] = 0;
      $data['summary']['utilizationTypes'][$thisKey]['totalPurchasePrice'] = 0;
    }
    $data['summary']['utilizationTypes'][$thisKey]['totalCount']++;
    $data['summary']['utilizationTypes'][$thisKey]['completedCount'] += ($t['status'] == 2) ? 1 : 0;
    $data['summary']['utilizationTypes'][$thisKey]['totalAmount'] += $t['tradeSizeUSD'];
    $data['summary']['utilizationTypes'][$thisKey]['totalPurchasePrice'] += $t['tradeSizeUSD'] * $t['tradePrice'];

    //if it does exist, then add to it

    return $data;

  }

  function summarizeUtilizationTypes($data, $return) {
    $return['summary']['utilizationTotalCount'] += $data['summary']['utilizationTotalCount'];
    $return['summary']['utilizationTotalCompletedCount'] += $data['summary']['utilizationTotalCompletedCount'];
    $return['summary']['utilizationTotalAmount'] += $data['summary']['utilizationTotalAmount'];
    $return['summary']['utilizationTotalPurchasePrice'] += $data['summary']['utilizationTotalPurchasePrice'];

    foreach($data['summary']['utilizationTypes'] as $k => $v) {
      $return['summary']['utilizationTypes'][$k]['textLabel'] = $data['summary']['utilizationTypes'][$k]['textLabel'];
      $return['summary']['utilizationTypes'][$k]['totalCount'] += $data['summary']['utilizationTypes'][$k]['totalCount'];
      $return['summary']['utilizationTypes'][$k]['completedCount'] += $data['summary']['utilizationTypes'][$k]['completedCount'];
      $return['summary']['utilizationTypes'][$k]['totalAmount'] += $data['summary']['utilizationTypes'][$k]['totalAmount'];
      $return['summary']['utilizationTypes'][$k]['totalPurchasePrice'] += $data['summary']['utilizationTypes'][$k]['totalPurchasePrice'];
    }

    return $return;
  }

  function processUtilizationTypeAveragePrices($return) {

    //Get section avergages
    foreach($return['summary']['utilizationTypes'] as $k => $v) {
      $return['summary']['utilizationTypes'][$k]['avgPrice'] = ($v['totalAmount'] > 0) ? number_format($v['totalPurchasePrice'] / $v['totalAmount'], 4) : 0;
    }

    $return['summary']['utilizationTotalAvgPrice'] = ($return['summary']['utilizationTotalAmount'] > 0) ? number_format($return['summary']['utilizationTotalPurchasePrice'] / $return['summary']['utilizationTotalAmount'], 4) : 0;

    $dataProcessed['return'] = $return;

    return $dataProcessed;

  }

  function process_trade_size_data($data) {

    //Figure out exchange rate to use (spot or generic or fall back to 1)
    $thisExchangeRate = ($data['tExchangeRate'] > 0) ? $data['tExchangeRate'] : ($data['budgetExchangeRate'] > 0 ? $data['budgetExchangeRate'] : 1);
    $data['tExchangeRate'] = $thisExchangeRate;

    //If this is utilization is a percentage of a credit (rather than a fixed amount)
    $data['tradePercentageEstimateWhole'] = 0;
    if($data['tradeIsEstimated'] == 1 && $data['tradePercentageEstimate'] > 0) {
      $data['tradePercentageEstimateWhole'] = number_format($data['tradePercentageEstimate'] * 100, 1);
      if($data['tradePercentageEstimate'] == 0.33) {
        $data['tradePercentageEstimate'] = 0.333333333333333;
      } else {
        if($data['tradePercentageEstimate'] == 0.66) {
          $data['tradePercentageEstimate'] = 0.666666666666666;
        }
      }
      $thisCreditAmountCompareTo = ($data['tradePercentageEstimateCompareTo'] == '' || $data['tradePercentageEstimateCompareTo'] == 'facevalue') ? $data['creditFaceValue'] : $data['amountLocalRemaining'];
      //Now override default values with percentage calculated values
      $data['tradeSize'] = $thisCreditAmountCompareTo * $data['tradePercentageEstimate'];
      $data['size'] = $data['tradeSize'];
      $data['tradeSizeEstimate'] = $data['tradeSize'];
    }

    //Establish core trade amount values
    $data['tradeSizeLocal'] = $data['tradeSize'];
    $data['tradeSizeGrossLocal'] = $data['tradeSize'] + $data['interestAmountLocal'];
    $data['tradeSizeUSD'] = $data['tradeSize'] * $thisExchangeRate;
    $data['tradeSizeGrossUSD'] = $data['tradeSizeGrossLocal'] * $thisExchangeRate;

    //Third party fees
    $thirdParty1Fee = ($data['thirdParty1FeeFlat'] > 0) ? $data['thirdParty1FeeFlat'] : ($data['thirdParty1FeePer'] > 0 ? number_format($data['tradeSizeUSD'] * $data['thirdParty1FeePer'], 2) : 0);
    $thirdParty1Fee = str_replace(',', '', $thirdParty1Fee); //replace commas from number_format
    $thirdParty2Fee = ($data['thirdParty2FeeFlat'] > 0) ? $data['thirdParty2FeeFlat'] : ($data['thirdParty2FeePer'] > 0 ? number_format($data['tradeSizeUSD'] * $data['thirdParty2FeePer'], 2) : 0);
    $thirdParty2Fee = str_replace(',', '', $thirdParty2Fee); //replace commas from number_format
    $thisTradePrice = ($data['tradeIsEstimated'] == 1) ? $data['tradePriceEstimate'] : $data['tradePrice'];
    $data['tradeFeesTotal'] = $thirdParty1Fee + $thirdParty2Fee;
    $data['tradeSizeUSD_net'] = ($data['tradeSizeGrossUSD'] * $thisTradePrice) - $thirdParty1Fee - $thirdParty2Fee;

    //Interest Amount Local --> interest amount * exchange rate
    $data['interestAmountUSD'] = ($data['tExchangeRate'] > 0) ? $data['interestAmountLocal'] * $data['tExchangeRate'] : ($data['budgetExchangeRate'] > 0 ? $data['interestAmountLocal'] * $data['budgetExchangeRate'] : 0);
    //Trade Amount Local Net (Gross Local Currency) --> (trade size * exchange rate) + (interest amount * exchange rate)
    $data['tradeSizeUSDGross'] = $data['tradeSizeUSD'] + $data['interestAmountLocal'];

    return $data;

  }

  //////////////////////////////////////
  ////// START COMPLETE FUNCTIONS //////
  //////////////////////////////////////

  function get_utilization_types() {
    $this->db->select('*');
    $this->db->from('utilizationTypes');
    $this->db->where('utilizationTypes.utInDropDown', 1);
    $this->db->order_by("utOrder ASC");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_utilization_type($utId) {
    $this->db->select('*');
    $this->db->from('utilizationTypes');
    $this->db->where('utilizationTypes.utId', $utId);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return[0];

  }

  function get_trades_count($perspective, $userId, $dmaId = "", $dmaMemberId = "") {

    $this->db->select('COUNT(Trades.tradeId) as tradeCount');
    if($perspective == "seller") {
      $this->db->where('PendingListings.listedBy', $userId);
    }
    if($perspective == "buyer") {
      $this->db->where('Trades.accountId', $userId);
    }
    if($dmaId > 0 && $dmaMemberId > 0) {
      $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
      $this->db->where($whereAccess);
    }
    $this->db->where('Trades.deleteMarker', null);
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    if($dmaId > 0 && $dmaMemberId > 0) {
      $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
      $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId AND ('open' = creditAccessDmaLevel.caAction OR 'watch' = creditAccessDmaLevel.caAction)", 'left');
    }
    $query = $this->db->get('Trades');

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0]['tradeCount'];
    }

  }

  function insert_utilization($request) {
    $data = [
        'timeStamp'           => $request['timeStamp'],
        'listingId'           => $request['listingId'],
        'accountId'           => $request['accountId'],
        'tDmaMemberId'        => $request['tDmaMemberId'],
        'status'              => 0,
        'tradeSize'           => $request['tradeSize'],
        'tradePrice'          => ($request['tradePrice'] > 0) ? $request['tradePrice'] : 1,
        'tExchangeRate'       => ($request['tExchangeRate'] > 0) ? $request['tExchangeRate'] : null,
        'brokeDate'           => time(),
        'updatedTime'         => date('Y-m-d H:i:s'),
        'tradeType'           => 'utilization',
        'utilizingEntityType' => $request['utilizingEntityType'],
        'utilizationTypeId'   => $request['utilizationTypeId'],
    ];
    $this->db->insert('Trades', $data);

    return $this->db->insert_id();

  }

  function insert_estimated_utilization($request) {

    $data = [
        'timeStamp'                        => $request['timeStamp'],
        'listingId'                        => $request['listingId'],
        'accountId'                        => $request['accountId'],
        'tDmaMemberId'                     => $request['tDmaMemberId'],
        'tradeIsEstimated'                 => 1,
        'status'                           => 0,
        'tradeSize'                        => $request['tradeSize'],
        'tradePrice'                       => ($request['tradePrice'] > 0) ? $request['tradePrice'] : 1,
        'tradeSizeEstimate'                => $request['tradeSize'],
        'tradePriceEstimate'               => $request['tradePrice'],
        'tradePercentageEstimate'          => $request['tradePercentageEstimate'],
        'tradePercentageEstimateCompareTo' => $request['tradePercentageEstimateCompareTo'],
        'tExchangeRate'                    => $request['tExchangeRate'],
        'tradeDateEstimate'                => $request['tradeDateEstimate'],
        'brokeDate'                        => time(),
        'updatedTime'                      => date('Y-m-d H:i:s'),
        'tradeType'                        => 'utilization',
        'tradeNotes'                       => $request['tradeNotes'],
        'utilizingEntityType'              => $request['utilizingEntityType'],
        'utilizationTypeId'                => $request['utilizationTypeId'],
    ];
    $this->db->insert('Trades', $data);

    return $this->db->insert_id();

  }

  function update_estimated_utilization($request) {

    $data = [
        'timeStamp'                        => $request['timeStamp'],
        'accountId'                        => $request['accountId'],
        'tradeSize'                        => $request['tradeSize'],
        'tradePrice'                       => $request['tradePrice'],
        'tradeSizeEstimate'                => $request['tradeSize'],
        'tradePriceEstimate'               => $request['tradePrice'],
        'tradePercentageEstimate'          => $request['tradePercentageEstimate'],
        'tradePercentageEstimateCompareTo' => $request['tradePercentageEstimateCompareTo'],
        'tExchangeRate'                    => $request['tExchangeRate'],
        'tradeDateEstimate'                => $request['tradeDateEstimate'],
        'updatedTime'                      => date('Y-m-d H:i:s'),
        'tradeNotes'                       => $request['tradeNotes'],
        'utilizingEntityType'              => $request['utilizingEntityType'],
        'utilizationTypeId'                => $request['utilizationTypeId'],
    ];

    $this->db->where('Trades.tradeId', $request['tradeId']);
    $this->db->update('Trades', $data);

    return $request['tradeId'];

  }

  function initiate_full_trade() {
    $data = [
        'timeStamp'           => $this->cisession->userdata('timeStamp'),
        'listingId'           => $this->cisession->userdata('listingId'),
        'bidId'               => $this->cisession->userdata('bidId'),
        'accountId'           => $this->cisession->userdata('buyerAccountId'),
        'tDmaMemberId'        => $this->cisession->userdata('buyerDmaMemberAccountId'),
        'status'              => 0,
        'tradeSize'           => $this->cisession->userdata('currentSize'),
        'tradePrice'          => $this->cisession->userdata('currentPct'),
        'brokeDate'           => time(),
        'updatedTime'         => date('Y-m-d H:i:s'),
        'updatedAdminBy'      => $this->cisession->userdata('auserId'),
        'updatedBy'           => $this->cisession->userdata('userId'),
        'tradeType'           => $this->cisession->userdata('tradeType'),
        'utilizationTypeId'   => $this->cisession->userdata('utilizationTypeId'),
        'utilizingEntityType' => $this->cisession->userdata('utilizingEntityType'),

    ];
    $this->db->insert('Trades', $data);

    return $this->db->insert_id();

  }

  function initiate_full_modified_trade() {
    $data = [
        'timeStamp'           => $this->cisession->userdata('timeStamp'),
        'listingId'           => $this->cisession->userdata('listingId'),
        'bidId'               => $this->cisession->userdata('bidId'),
        'accountId'           => $this->cisession->userdata('buyerAccountId'),
        'tDmaMemberId'        => $this->cisession->userdata('buyerDmaMemberAccountId'),
        'status'              => 0,
        'tradeSize'           => $this->cisession->userdata('offerSize'),
        'tradePrice'          => $this->cisession->userdata('currentPct'),
        'brokeDate'           => time(),
        'updatedTime'         => date('Y-m-d H:i:s'),
        'updatedAdminBy'      => $this->cisession->userdata('auserId'),
        'updatedBy'           => $this->cisession->userdata('userId'),
        'tradeType'           => $this->cisession->userdata('tradeType'),
        'utilizationTypeId'   => $this->cisession->userdata('utilizationTypeId'),
        'utilizingEntityType' => $this->cisession->userdata('utilizingEntityType'),

    ];
    $this->db->insert('Trades', $data);

    return $this->db->insert_id();

  }

  function initiate_partial_trade() {
    $data = [
        'timeStamp'           => $this->cisession->userdata('timeStamp'),
        'listingId'           => $this->cisession->userdata('listingId'),
        'bidId'               => $this->cisession->userdata('bidId'),
        'accountId'           => $this->cisession->userdata('buyerAccountId'),
        'tDmaMemberId'        => $this->cisession->userdata('buyerDmaMemberAccountId'),
        'status'              => 0,
        'tradeSize'           => $this->cisession->userdata('currentSize'),
        'tradePrice'          => $this->cisession->userdata('currentPct'),
        'brokeDate'           => time(),
        'updatedTime'         => date('Y-m-d H:i:s'),
        'updatedAdminBy'      => $this->cisession->userdata('auserId'),
        'updatedBy'           => $this->cisession->userdata('userId'),
        'tradeType'           => $this->cisession->userdata('tradeType'),
        'utilizationTypeId'   => $this->cisession->userdata('utilizationTypeId'),
        'utilizingEntityType' => $this->cisession->userdata('utilizingEntityType'),

    ];
    $this->db->insert('Trades', $data);

    return $this->db->insert_id();
  }

  /*
    Full trade pending = 0;
    Partial trade pending = 1;
    Full trade complete = 2;
    Partial trade complete = 3;
  */
  function checkStatus($id) {

    $this->db->where('listingId', $id);
    $names = ['0'];
    $this->db->where_in('status', $names);
    //$this->db->where('status','0');
    //$this->db->or_where('status','1');   //need to verify
    //$this->db->or_where('status','2');
    $this->db->from('Trades');
    $count = $this->db->count_all_results();

    return ($count > 0) ? true : false;

  }

  function checkListingStatus($id) {

    $this->db->where('listingId', $id);
    if(($this->enable_exp_date) == "1") {
      $expwhere = "(deleteMarker='1' or TIMESTAMPDIFF(DAY,CURDATE(),FROM_UNIXTIME(OfferGoodUntil))>0 or OfferGoodUntil IS NULL or OfferGoodUntil='0')";
      $this->db->where($expwhere, null, false);
      $this->db->where('deleteMarker', '1');

    }

    $this->db->from('ActiveListings');
    $count = $this->db->count_all_results();

    return ($count > 0) ? false : true;

  }

  function checkBidStatus($id) {

    $this->db->where('BidId', $id);
    $this->db->where('deleteMarker', '1');
    $this->db->from('Bids');
    $count = $this->db->count_all_results();

    return ($count > 0) ? true : false;

  }

  function checkIfBidIsTraded($bidId) {

    $this->db->select('Trades.*');
    $this->db->where('bidId', $bidId);
    $this->db->from('Trades');
    $query = $this->db->get();

    return $query->result_array();

  }

  function checkListingTradingStatus($id) {

    $this->db->where('listingId', $id);
    $this->db->from('Trades');
    $count = $this->db->count_all_results();

    return ($count > 0) ? true : false;

  }

  function getTradesInfo($id, $tradeType = "", $utilizationTypeId = "", $includeEstimated = "") {

    $this->db->select('Trades.tradeId, Trades.listingId,Trades.bidId,Trades.accountId,Trades.status, Trades.utilizationTypeId, Trades.tradeIsEstimated, Trades.sellerSigned,Trades.brokeDate, Trades.utilizationTypeId, utilizationTypes.name as utName, Trades.tradeType,Trades.closingProcessStartDate,tradePrice,tradeSize,(tradePrice * tradeSize) as tradeTotal, Trades.timeStamp as timestamp, Trades.timeStamp, Bids.accountId as BaccountId, Accounts.companyName as buyerCompanyName, PendingListings.cTaxpayerId, Taxpayers.taxpayerId, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpAccountType, IncentivePrograms.State');
    $this->db->where('Trades.listingId', $id);
    $this->db->where('Trades.deleteMarker', null);
    if($tradeType == "") {
      $this->db->where('Trades.tradeType', 'oix_marketplace_trade');
    } else {
      if($tradeType == "all") {
        //no filter
      } else {
        $this->db->where('Trades.tradeType', $tradeType);
      }
    }
    if($utilizationTypeId == "") {
      //no filter
    } else {
      $this->db->where('Trades.utilizationTypeId', $utilizationTypeId);
    }
    if($includeEstimated == "") {
      $whereTradeIsEstimated = "(Trades.tradeIsEstimated=0 OR Trades.tradeIsEstimated IS NULL)";
      $this->db->where($whereTradeIsEstimated);
    } else {
      //no filter
    }
    $this->db->from('Trades');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    $this->db->join("PendingListings", "Trades.listingId = PendingListings.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->order_by("Trades.tradeId desc");
    $query = $this->db->get();

    return $query->result_array();

  }

  function get_trades_lite($listingId, $tradeType = "", $utilizationTypeId = "", $includeEstimated = "") {

    $this->db->select('Trades.tradeId, Trades.status, Trades.tradeIsEstimated, Trades.tradePriceEstimate, Trades.tradeDateEstimate, Trades.utilizationTypeId, Trades.interestAmountLocal, Trades.tExchangeRate, tradePrice, tradeSize, tradePercentageEstimate, tradePercentageEstimateCompareTo, thirdParty1FeeFlat, thirdParty1FeePer, thirdParty2FeeFlat, thirdParty2FeePer, utilizationTypes.name as utName, Trades.timeStamp as timestamp, Trades.timeStamp as actualUtilizationDate, Trades.timeStamp, PendingListings.creditAmount as creditFaceValue, PendingListings.availableToList as amountLocalRemaining, PendingListings.budgetExchangeRate');
    $this->db->where('Trades.listingId', $listingId);
    $this->db->where('Trades.deleteMarker', null);
    if($tradeType == "") {
      $this->db->where('Trades.tradeType', 'oix_marketplace_trade');
    } else {
      if($tradeType == "all") {
        //no filter
      } else {
        $this->db->where('Trades.tradeType', $tradeType);
      }
    }
    if($utilizationTypeId == "") {
      //no filter
    } else {
      $this->db->where('Trades.utilizationTypeId', $utilizationTypeId);
    }
    if($includeEstimated == "") {
      $whereTradeIsEstimated = "(Trades.tradeIsEstimated=0 OR Trades.tradeIsEstimated IS NULL)";
      $this->db->where($whereTradeIsEstimated);
    } else {
      //no filter
    }
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->from('Trades');
    $this->db->order_by("Trades.tradeId desc");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data = $this->process_trade_size_data($data);

      array_push($return, $data);

    }

    return $return;

  }

  function get_last_two_trades($id) {
    $this->db->select('*');
    $this->db->where('Trades.listingId', $id);
    $this->db->where('Trades.deleteMarker', null);
    $this->db->where('Trades.tradeType', 'oix_marketplace_trade');
    $this->db->from('Trades');
    $this->db->order_by("tradeId desc");
    $this->db->limit(2);
    $query = $this->db->get();

    return $query->result_array();
  }

  function get_trades_on_listing($id, $tradeType = "", $includeEstimated = "", $utilizationTypeId = "") {
    $this->db->select('Trades.*, Trades.tradeSize as tradeSizeLocal, Trades.accountId as buyerAccountId, Accounts.companyName as buyerAccountName, Bids.accountId as bidAccountId, IncentivePrograms.State, utilizationTypes.name utName, ActiveListings.brokerDmaId, PendingListings.creditAmount as creditFaceValue, PendingListings.availableToList as amountLocalRemaining, PendingListings.budgetExchangeRate');
    $this->db->from('Trades');
    $this->db->where('Trades.listingId', $id);
    $this->db->where('Trades.deleteMarker', null);
    if($tradeType != "") {
      $this->db->where('Trades.tradeType', $tradeType);
    }
    if($includeEstimated == 1) {
      //do nothing
    } else {
      if($includeEstimated == 2) {
        $this->db->where('Trades.tradeIsEstimated', 1); //ONLY include estimated
      } else {
        $whereTradeIsEstimated = "(Trades.tradeIsEstimated=0 OR Trades.tradeIsEstimated IS NULL)";
        $this->db->where($whereTradeIsEstimated);
      }
    }
    if($utilizationTypeId != "") {
      $this->db->where('Trades.utilizationTypeId', $utilizationTypeId);
    }
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->order_by("Trades.timeStamp DESC");
    $this->db->distinct();

    $query = $this->db->get();

    $return = [];
    $return['trades'] = [];
    $return['summary'] = [];
    $return['summary'] = array_merge($return['summary'], $this->getUtilizationTypesFields());
    $return['summary']['totalTradeSizeLocal'] = 0;
    $return['summary']['actual']['count'] = 0;
    $return['summary']['actual']['faceValue'] = 0;
    $return['summary']['actual']['estimatedValue'] = 0;
    $return['summary']['actual']['yearly'] = [];
    $return['summary']['estimated']['count'] = 0;
    $return['summary']['estimated']['faceValue'] = 0;
    $return['summary']['estimated']['estimatedValue'] = 0;
    $return['summary']['estimated']['yearly'] = [];

    foreach($query->result_array() as $data) {

      $data['tExchangeRate'] = ($data['tExchangeRate'] > 0) ? $data['tExchangeRate'] : $data['budgetExchangeRate'];
      $data['tradeSizeUSD'] = $data['tradeSizeLocal'] * $data['tExchangeRate'];

      $data['actualUtilizationDate'] = ($data['tradeIsEstimated'] == 1) ? null : strtotime($data['timeStamp']);

      $data = $this->process_trade_size_data($data);

      $data['transactions'] = $this->get_transactions_of_trade($data['tradeId']);

      //If a broker trade, see how many sigs and payments are due
      if($data['brokerDmaId'] > 0) {
        $data = $this->processBrokerActions($data);
      }

      //$data['transactions'] = $data['transactions'][0];
      $data['buyerName'] = $this->Members_Model->getUserCompanyById($data['buyerAccountId']);

      if($data['tradeType'] == "internal_transfer") {
        $data['tradeTypeName'] = "Internal Trans.";
      } else {
        if($data['tradeType'] == "external_transfer") {
          $data['tradeTypeName'] = "Transfer";
        } else {
          $data['tradeTypeName'] = "Sale on OIX";
        }
      }

      $firstTrans = $data['transactions'][0];
      if($data['utilizingEntityType'] == "myaccounts") {
        $data['utilizingEntityName'] = $data['buyerAccountName']; //Buyer is the account
      } else {
        if($data['utilizingEntityType'] == "customname") {
          $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
        } else {
          if($data['transactions'][0]['utilizingEntityCustomName'] != "") {
            $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
          } else {
            if($firstTrans['taxpayerId'] > 0) {
              $extraText = ($data['tradeType'] == "oix_marketplace_trade") ? "(via " . $data['buyerAccountName'] . ")" : "";
              if(sizeof($data['transactions']) > 1) {
                $data['utilizingEntityName'] = sizeof($data['transactions']) . " Buyers " . $extraText;
              } else {
                $data['utilizingEntityName'] = ($firstTrans['tpAccountType'] == 1) ? $firstTrans['tpCompanyName'] . " " . $extraText : $firstTrans['tpFirstName'] . ' ' . $firstTrans['tpLastName'] . " " . $extraText;
              }
            } else {
              $data['utilizingEntityName'] = $data['buyerAccountName']; //Buyer is this account
            }
          }
        }
      }

      if($data['tradeIsEstimated'] == 1) {
        $return['summary']['estimated']['count']++;
        $return['summary']['estimated']['faceValue'] += $data['tradeSizeUSD'];
        $return['summary']['estimated']['estimatedValue'] += $data['tradeSizeUSD'] * $data['tradePrice'];
      } else {
        $return['summary']['actual']['count']++;
        $return['summary']['actual']['faceValue'] += $data['tradeSizeUSD'];
        $return['summary']['actual']['estimatedValue'] += $data['tradeSizeUSD'] * $data['tradePrice'];
      }
      $return['summary']['totalTradeSizeLocal'] += $data['tradeSizeLocal'];

      if($data['tradeIsEstimated'] != 1) {
        $return = $this->processUtilizationTypes($data, $return);
      }

      if($data['timeStamp'] != "0000-00-00 00:00:00") {
        if($data['tradeIsEstimated'] != 1) {
          $thisUsageYear = date('Y', strtotime($data['timeStamp']));
          if(array_key_exists($thisUsageYear, $return['summary']['actual']['yearly'])) {
            $return['summary']['actual']['yearly'][$thisUsageYear] += $data['tradeSizeUSD'];
          } else {
            $return['summary']['actual']['yearly'][$thisUsageYear] = $data['tradeSizeUSD'];
          }
        } else {
          $thisUsageYear = date('Y', $data['tradeDateEstimate']);
          if(array_key_exists($thisUsageYear, $return['summary']['estimated']['yearly'])) {
            $return['summary']['estimated']['yearly'][$thisUsageYear] += $data['tradeSizeUSD'];
          } else {
            $return['summary']['estimated']['yearly'][$thisUsageYear] = $data['tradeSizeUSD'];
          }
        }
      }

      array_push($return['trades'], $data);

    }

    $totalUtilizationTypesProcessed = $this->processUtilizationTypeAveragePrices($return);
    $return = $totalUtilizationTypesProcessed['return'];

    //var_dump($return['summary']); throw new \Exception('General fail');

    return $return;

  }

  //HEY YOU!!!!!!!!!!!!!!!!!
  ///   A version of this exists in CreditListings model....
  ///////////////
  function processBrokerActions($data) {

    $data['brokerData'] = [];
    $data['brokerData']['signatureDocsNotUploaded'] = 0;
    $data['brokerData']['signatureDocsNotSent'] = 0;
    $data['brokerData']['signaturesOutstanding'] = 0;
    $data['brokerData']['paymentsOutstanding'] = 0;
    $data['brokerData']['actionsOutstandingMessage'] = "";

    foreach($data['transactions'] as $tr) {
      if($tr['buyerSigReady'] != 1) {
        $data['brokerData']['signatureDocsNotUploaded']++;
      }
      if($tr['buyerSigEmailSent'] != 1) {
        $data['brokerData']['signatureDocsNotSent']++;
      }
      if($tr['buyerSigned'] != 1) {
        $data['brokerData']['signaturesOutstanding']++;
      }
      if($tr['buyerPaid'] != 1) {
        $data['brokerData']['paymentsOutstanding']++;
      }
    }
    if($data['brokerData']['signaturesOutstanding'] > 0 || $data['brokerData']['paymentsOutstanding'] > 0) {
      $data['brokerData']['actionsOutstandingMessage'] .= "Due: ";
    }
    if($data['brokerData']['signaturesOutstanding'] > 0) {
      $data['brokerData']['actionsOutstandingMessage'] .= $data['brokerData']['signaturesOutstanding'] . " Sign";
    }
    if($data['brokerData']['signaturesOutstanding'] > 0 && $data['brokerData']['paymentsOutstanding'] > 0) {
      $data['brokerData']['actionsOutstandingMessage'] .= " + ";
    }
    if($data['brokerData']['paymentsOutstanding'] > 0) {
      $data['brokerData']['actionsOutstandingMessage'] .= $data['brokerData']['paymentsOutstanding'] . " Pay";
    }

    return $data;

  }

  function get_trade_of_bid($bidId) {
    $this->db->select('Trades.*');
    $this->db->from('Trades');
    $this->db->where('Trades.bidId', $bidId);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_transactions_of_trade($tradeId) {
    $this->db->select('Transactions.*, Taxpayers.*, Trades.closingProcessStartDate, Trades.tradeType, Trades.utilizationTypeId, Trades.tradePercentageEstimate, Trades.tradePercentageEstimateCompareTo, utilizationTypes.name as utName, Accounts.companyName as buyerCompanyName, PendingListings.creditAmount as creditFaceValue, PendingListings.availableToList as amountLocalRemaining');
    $this->db->where('Transactions.tradeId', $tradeId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("Accounts", "Transactions.buyerAccountId = Accounts.userId", 'left');
    $this->db->join("Trades", "Transactions.tradeId = Trades.tradeId", 'left');
    $this->db->join("PendingListings", "Trades.tradeId = PendingListings.listingId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data['tradePercentageEstimate'] > 0) {
        $thisCreditAmountCompareTo = ($data['tradePercentageEstimateCompareTo'] == '' || $data['tradePercentageEstimateCompareTo'] == 'facevalue') ? $data['creditFaceValue'] : $data['amountLocalRemaining'];
        $data['tCreditAmount'] = $thisCreditAmountCompareTo * $data['tradePercentageEstimate'];
      }
      $data['buyerPayMethodName'] = '';
      if($data['buyerPayMethod'] == 1) {
        $data['buyerPayMethodName'] = 'check';
      }
      if($data['buyerPayMethod'] == 2) {
        $data['buyerPayMethodName'] = 'wire';
      }
      if($data['taxpayerId'] > 0) {
        $data['taxpayerNameToUse'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . ' ' . $data['tpLastName'];
      } else {
        $data['taxpayerNameToUse'] = "";
      }

      //$data['taxpayerInfo'] = $this->get_taxpayer($data['taxpayerId']);
      array_push($return, $data);
    }

    return $return;

  }

  function get_transactions($request) {

    $tBrokerDmaId = (isset($request['tBrokerDmaId'])) ? $request['tBrokerDmaId'] : 0;

    $this->db->select('Transactions.*, Trades.status');
    if($tBrokerDmaId > 0) {
      $this->db->where('Transactions.tBrokerDmaId', $tBrokerDmaId);
    }
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Transactions');
    $this->db->join("Trades", "Transactions.tradeId = Trades.tradeId", 'left');
    $query = $this->db->get();

    $return = [];
    $return['transactions'] = [];
    $return['summary'] = [];
    $return['summary']['psaToSign'] = 0;
    $return['summary']['buyerSignaturesDue'] = 0;
    $return['summary']['buyerPaymentsDue'] = 0;
    $return['summary']['closingDocsDue'] = 0;
    $return['summary']['inClosingProcess'] = 0;

    foreach($query->result_array() as $data) {

      if($data['buyerSigReady'] == 1) {
      } else {
        $return['summary']['psaToSign']++;
      }
      if($data['buyerSigned'] == 1) {
      } else {
        $return['summary']['buyerSignaturesDue']++;
      }
      if($data['buyerPaid'] == 1) {
      } else {
        $return['summary']['buyerPaymentsDue']++;
      }
      if($data['finalPsaUploaded'] == 1) {
      } else {
        $return['summary']['closingDocsDue']++;
      }
      if($data['status'] == 1) {
        $return['summary']['inClosingProcess']++;
      }

      array_push($return['transactions'], $data);

    }

    return $return;

  }

  function get_transactions_of_listing($listingId) {
    $this->db->select('Transactions.*, Taxpayers.*, Trades.tradePrice, Trades.accountId as tradeBuyerId, Trades.tDmaMemberId, Trades.utilizationTypeId, utilizationTypes.name as utName, Accounts.firstName as buyerFirstName, Accounts.lastName as buyerLastName, Accounts.email as buyerEmail');
    $this->db->where('Trades.listingId', $listingId);
    $this->db->where('Transactions.tDeleted is null');
    $this->db->from('Trades');
    $this->db->join("Transactions", "Transactions.tradeId = Trades.tradeId", 'left');
    $this->db->join("Taxpayers", "Transactions.taxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->join("Accounts", "Accounts.userId = Trades.tDmaMemberId", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');

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

  function get_transaction_participants($transactionId) {
    $this->db->select('Trades.accountId as mainBuyerId, Transactions.taxpayerId as buyerTaxpayerId, Transactions.taxpayerUserId as buyerTaxpayerUserId, PendingListings.listedBy as creditOwner');
    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->from('Transactions');
    $this->db->join("Trades", "Transactions.tradeId = Trades.tradeId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_trade_participants($tradeId) {
    $this->db->select('Trades.accountId as mainBuyerId, PendingListings.listedBy as mainSellerId, PendingListings.cTaxpayerId, Taxpayers.tpAccountId, Taxpayers.tpUserIdSigner');
    $this->db->where('Trades.tradeId', $tradeId);
    $this->db->join("PendingListings", "Trades.listingId = PendingListings.listingId", 'left');
    $this->db->join("Taxpayers", "PendingListings.cTaxpayerId = Taxpayers.taxpayerId", 'left');
    $this->db->from('Trades');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $data['sellerTaxpayerUserId'] = "";
      if($data['cTaxpayerId'] > 0) {
        $taxpayer = $this->get_taxpayer($data['cTaxpayerId']);
        if($taxpayer['tpUserIdSigner'] > 0) {
          $data['sellerTaxpayerUserId'] = $taxpayer['tpUserIdSigner'];
        }
      }
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
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

  function delete_all_trades_against_credit($listingId) {

    $data = [
        'deleteMarker' => 1,
    ];

    $this->db->where('Trades.listingId', $listingId);
    $this->db->update('Trades', $data);

    return true;

  }

  function get_trade($id) {

    $this->db->select('tradeId, Trades.timeStamp, Trades.status, Trades.tradeIsEstimated, Trades.closingProcessStartDate, Trades.executionDate, Trades.accountId, Trades.tradeType, Trades.utilizationTypeId, Trades.hasFolders, Trades.tradeNotes, Trades.sellerSigReady, Trades.sellerSigned, Trades.sellerSignedDate, Trades.settlementDate, Trades.deleteMarker, Trades.taxpayerUserId, Bids.accountId as BaccountId, Bids.bDmaMemberId, Trades.listingId, Trades.tDmaMemberId, Trades.brokeDate, tradeSize, tradeSize as size, tradeSize as tradeSizeLocal, tradePrice as price, Trades.tradePercentageEstimate, Trades.tradeDateEstimate, Trades.interestAmountLocal, Trades.tExchangeRate, Trades.thirdParty1FeeFlat, Trades.thirdParty1FeePer, Trades.thirdParty2FeeFlat, Trades.thirdParty2FeePer, Trades.tradeWireInstructions, Trades.paymentInstructionsToUse, Trades.tradePriceEstimate, Trades.tradePercentageEstimateCompareTo, Trades.sellerRecPayment, Trades.sellerRecPaymentMethod, Trades.sellerRecPaymentDate, Trades.paymentNotes, Trades.utilizingEntityType, utilizationTypes.name as utName, IncentivePrograms.State, IncentivePrograms.ProgramName, IncentivePrograms.Sector, IncentivePrograms.OIXIncentiveId, ActiveListings.taxYearId, IPSectors.sector, Bids.bidId, ActiveListings.brokerDmaId, PendingListings.stateCertNum, PendingListings.stateCertNum as projectName, PendingListings.projectNameExt, PendingListings.budgetExchangeRate, IncentivePrograms.OffSettingTaxList,OffsetLocked,offsetName,TaxYear.taxYear, Accounts.companyName as buyerAccountName, PendingListings.cTaxpayerId, PendingListings.listedBy, PendingListings.creditAmount as creditFaceValue, PendingListings.availableToList as amountLocalRemaining, PendingListings.cDmaMemberId, ActiveListings.listingWireInstructions, States.state, States.name, ActiveListings.ownPS, Accounts_buyer.appAccountId AS buyerAppId, Accounts_buyer.companyName as buyerAccountName, Accounts_buyer.userId as buyerid, Accounts_buyer.companyName as buyername, buyerDmaAccount.title as buyerDmaTitle, transactionFeePer, buyerTransactionFeePer, thirdParty1FeePer, thirdParty2FeePer, creditHolderAccount.primary_account_id, creditHolderAccount.dmaId, creditHolderAccount.parentDmaId');

    $this->db->from('Trades');
    $this->db->where('Trades.TradeId', $id);
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("Bids", "Bids.bidId = Trades.bidId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = ActiveListings.OIXIncentiveId", 'left');
    $this->db->join("IPSectors", "IPSectors.id = IncentivePrograms.Sector", 'left');
    $this->db->join("Offsets", "ActiveListings.CreditUsedForOffset = Offsets.id", 'left');
    $this->db->join("TaxYear", "ActiveListings.taxYearId = TaxYear.id", 'left');
    $this->db->join("Accounts", "Trades.accountId = Accounts.userId", 'left');
    $this->db->join("Accounts as Accounts_buyer", "Accounts_buyer.userId = Bids.accountId", 'left');
    $this->db->join("dmaAccounts as buyerDmaAccount", "Trades.accountId = buyerDmaAccount.primary_account_id", 'left');
    $this->db->join("dmaAccounts as creditHolderAccount", "PendingListings.listedBy = creditHolderAccount.dmaId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("credit_utilization_type as utilizationTypes", "Trades.utilizationTypeId = utilizationTypes.id", 'left');
    $this->db->order_by("Trades.tradeId desc");
    $this->db->distinct();

    $query = $this->db->get();
    $records = $query->result_array();

    $return = [];
    $return['trade'] = [];
    $return['summary'] = [];
    $return['summary'] = array_merge($return['summary'], $this->getUtilizationTypesFields());

    /*
    //CUSTOM DATA POINTS - always set to the OWNER of the credit's CDP
    $cdpRequest['dmaId'] = $records[0]['listedBy'];
    $cdpRequest['dpObjectType'] = 'utilization';
    $customDataPoints = $this->get_custom_data_points($cdpRequest);
    //Check the credit owner's shared Parent Data Points
    if($records[0]['parentDmaId'] > 0) {
      $cdpParentRequest['dmaId'] = $records[0]['parentDmaId'];
      $cdpParentRequest['dpObjectType'] = 'utilization';
      $customDataPointsRawParent = $this->get_custom_data_points($cdpParentRequest);
      $customDataPoints = array_merge($customDataPoints, $customDataPointsRawParent);
    }
    */

    foreach($records as $data) {

      $data = $this->process_trade_size_data($data);
      /*
      //Get CDP values - always set to the OWNER of the credit's CDP
      $data = array_merge($data, $customDataPoints);
      $cdpvRequest['dmaId'] = $data['listedBy'];
      $cdpvRequest['listingId'] = $data['listingId'];
      $cdpvRequest['dpObjectType'] = 'credit';
      $customDataPointValues = $this->get_custom_data_point_values($cdpvRequest);
      if(sizeof($customDataPointValues) > 0) {
        $data = array_merge($data, $customDataPointValues);
      }
      //Check the credit owner's shared Parent Data Points
      if($data['parentDmaId'] > 0) {
        $cdpvRequest['dmaId'] = $data['parentDmaId'];
        $cdpvRequest['listingId'] = $data['listingId'];
        $cdpvRequest['dpObjectType'] = 'credit';
        $customDataPointValuesParent = $this->get_custom_data_point_values($cdpvRequest);
        if(sizeof($customDataPointValuesParent) > 0) {
          $data = array_merge($data, $customDataPointValuesParent);
        }
      }
      */

      //START - This is for old marketplace shit
      $data['sellerid'] = 0;
      $data['sellername'] = '';
      $data['sellerAppId'] = 0;
      $data['sellerCompanyName'] = '';
      $data['title'] = '';
      $data['dmaWireInstructions'] = '';
      //END - This is for old marketplace shit

      $data['utilizationDateUnix'] = strtotime($data['timeStamp']);

      $data['transactions'] = $this->get_transactions_of_trade($data['tradeId']);

      //If a broker trade, see how many sigs and payments are due
      //If a broker trade, see how many sigs and payments are due
      if($data['brokerDmaId'] > 0) {
        $data = $this->processBrokerActions($data);
      }

      $data['shortOffSettingTaxList'] = $this->CreditListings->get_short_offsets_from_program($data['OffSettingTaxList']);
      $data['tradePrice'] = $data['price'];
      $data['tradeSize'] = $data['size'];
      if($data['cDmaMemberId'] > 0) {
        $data['sellerInfo'] = $this->Members_Model->get_member_by_id($data['cDmaMemberId']);
      } else {
        $data['sellerInfo'] = $this->Members_Model->get_member_by_id($data['listedBy']);
      }
      if($data['cTaxpayerId'] > 0) {
        $data['taxpayerInfo'] = $this->Taxpayersdata->get_taxpayer($data['cTaxpayerId'], 0, 0);
      }

      if($data['tradeType'] == "oix_marketplace_trade") {
        $data['totalFees'] = $data['tradeSize'] * ($data['transactionFeePer'] + $data['buyerTransactionFeePer'] + $data['thirdParty1FeePer'] + $data['thirdParty2FeePer']);
      } else {
        $data['totalFees'] = 0;
      }

      $data['projectNameFull'] = ($data['projectNameExt'] != '') ? $data['projectName'] . ' - ' . $data['projectNameExt'] : $data['projectName'];

      $data['tradeDate'] = date('m/d/Y', strtotime($data['timeStamp']));

      $data['tExchangeRate'] = (!empty($data['tExchangeRate'])) ? $data['tExchangeRate'] : 1;

      if($data['paymentInstructionsToUse'] == 'account_level') {
        $data['paymentInstructionsToUseName'] = "Account Default";
        $data['paymentInstructionsInfoSelected'] = $data['dmaWireInstructions'];
      } else {
        if($data['paymentInstructionsToUse'] == 'credit_level') {
          $data['paymentInstructionsToUseName'] = "Custom for this Credit";
          $data['paymentInstructionsInfoSelected'] = $data['listingWireInstructions'];
        } else {
          if($data['paymentInstructionsToUse'] == 'trade_level') {
            $data['paymentInstructionsToUseName'] = "Custom for this Trade";
            $data['paymentInstructionsInfoSelected'] = $data['tradeWireInstructions'];
          } else {
            //If nothing set, then try to use default logic
            //First see if a default is on the credit
            if($data['listingWireInstructions'] != "") {
              $data['paymentInstructionsToUse'] = 'credit_level';
              $data['paymentInstructionsToUseName'] = "Custom for this Credit";
              $data['paymentInstructionsInfoSelected'] = $data['listingWireInstructions'];
            } else {
              //If not, then use the account default
              $data['paymentInstructionsToUse'] = 'account_level';
              $data['paymentInstructionsToUseName'] = "Account Default";
              $data['paymentInstructionsInfoSelected'] = $data['dmaWireInstructions'];
            }
          }
        }
      }

      $data['creditIdVisual'] = $data['state'] . $data['listingId'];

      $firstTrans = $data['transactions'][0];
      $data['utilizingEntityCustomName'] = $data['transactions'][0]['utilizingEntityCustomName'];
      $data['utilizingEntityId'] = 0;
      if($data['utilizingEntityType'] == "myaccounts" || $data['utilizingEntityType'] == "customers") {
        $data['utilizingEntityName'] = $data['buyerDmaTitle']; //Buyer is the account
        $data['utilizingEntityId'] = $data['accountId'];
      } else {
        if($data['utilizingEntityType'] == "customname") {
          $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
          $data['utilizingEntityId'] = null;
        } else {
          if($data['transactions'][0]['utilizingEntityCustomName'] != "") {
            $data['utilizingEntityName'] = $data['transactions'][0]['utilizingEntityCustomName']; //Utilizer is the custom name
          } else {
            if($firstTrans['taxpayerId'] > 0) {
              $extraText = ($data['tradeType'] == "oix_marketplace_trade") ? "(via " . $data['buyerAccountName'] . ")" : "";
              if(sizeof($data['transactions']) > 1) {
                $data['utilizingEntityName'] = sizeof($data['transactions']) . " Buyers " . $extraText;
              } else {
                $data['utilizingEntityName'] = ($firstTrans['tpAccountType'] == 1) ? $firstTrans['tpCompanyName'] . " " . $extraText : $firstTrans['tpFirstName'] . ' ' . $firstTrans['tpLastName'] . " " . $extraText;
                $data['utilizingEntityId'] = $firstTrans['taxpayerId'];
              }
            } else {
              $data['utilizingEntityName'] = $data['buyerAccountName']; //Buyer is this account
            }
          }
        }
      }

      if($data['utilizingEntityType'] == "self") {
        $data['utilizingEntityTypeName'] = "Internal/Self";
      } else {
        if($data['utilizingEntityType'] == "taxentity") {
          $data['utilizingEntityTypeName'] = "Legal Entity";
        } else {
          if($data['utilizingEntityType'] == "customname") {
            $data['utilizingEntityTypeName'] = "Third-Party Company";
          } else {
            if($data['utilizingEntityType'] == "customers") {
              $data['utilizingEntityTypeName'] = "Customer";
            } else {
              if($data['utilizingEntityType'] == "myaccounts") {
                $data['utilizingEntityTypeName'] = "Other OIX Account";
              } else {
                $data['utilizingEntityTypeName'] = "NA";
              }
            }
          }
        }
      }

      if($data['tradeIsEstimated'] != 1) {
        $return = $this->processUtilizationTypes($data, $return);
      }

      $data['utilizationAuditSummary'] = '';
      $data['utilizationAuditSummary'] = ($data['tradeIsEstimated'] == 1) ? 'Estimated' : 'Actual';
      $data['utilizationAuditSummary'] .= ' - ' . $data['utName'];
      $data['utilizationAuditSummary'] .= ' for ' . number_format($data['tradeSizeLocal'], 2);
      $exchangeRateAuditText = ($data['tExchangeRate'] != 1) ? ' with exchange rate of ' . $data['tExchangeRate'] : '';
      $data['utilizationAuditSummary'] .= ' (@ ' . number_format($data['tradePrice'], 4) . $exchangeRateAuditText . ')';
      $data['utilizationAuditSummary'] .= ' on ' . date('m/d/Y', $data['utilizationDateUnix']);
      $data['utilizationAuditSummary'] .= ' (' . $data['utilizingEntityTypeName'] . ': ' . $data['utilizingEntityName'] . ')';

      array_push($return['trade'], $data);

    }

    $totalUtilizationTypesProcessed = $this->processUtilizationTypeAveragePrices($return);
    $return = $totalUtilizationTypesProcessed['return'];

    $return['trade'] = $return['trade'][0];

    return $return;

  }

  function get_total_trade_amount_on_listing($id, $tradeType = "", $utilizationTypeId = "", $estOrActualFlag = "", $fixedValueOnlyFlag = "") {
    $this->db->select('SUM(Trades.tradeSize) as totalTradeAmount');
    $this->db->from('Trades');
    $this->db->where('Trades.listingId', $id);
    $this->db->where('Trades.deleteMarker', null);
    if($estOrActualFlag == 1) {
      $this->db->where('Trades.tradeIsEstimated', 1);
    } else {
      if($estOrActualFlag == 2) {
        //include all
      } else {
        $whereTradeIsEstimated = "(Trades.tradeIsEstimated=0 OR Trades.tradeIsEstimated IS NULL)";
        $this->db->where($whereTradeIsEstimated);
      }
    }
    if($tradeType != "") {
      $this->db->where('Trades.tradeType', $tradeType);
    }
    if($utilizationTypeId != "") {
      $this->db->where('Trades.utilizationTypeId', $utilizationTypeId);
    }
    if($fixedValueOnlyFlag != "") {
      $this->db->where('Trades.tradeIsEstimated', 1);
      $whereTradePercentageEstimate = "(Trades.tradePercentageEstimate=0 OR Trades.tradePercentageEstimate IS NULL)";
      $this->db->where($whereTradePercentageEstimate);
    }

    $return = [];
    $query = $this->db->get();

    $return['totalTradeAmount'] = 0;

    foreach($query->result_array() as $data) {
      if($data['totalTradeAmount'] == null) {
        $return['totalTradeAmount'] = 0;
      } else {
        $return['totalTradeAmount'] = $data['totalTradeAmount'];
      }
    }

    return $return;

  }

  function get_bid_transactions_of_taxpayer($taxpayerId, $filter, $getCredit, $dmaId = "", $dmaMemberId = "") {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId', null);
    $this->db->where('Transactions.taxpayerId', $taxpayerId);
    $this->db->where('Transactions.tDeleted', null);
    $this->db->where('ActiveListings.deleteMarker', null);
    $this->db->where('ActiveListings.traded', null);
    $this->db->where('ActiveListings.listed', 1);
    if($dmaId > 0 && $dmaMemberId > 0) {
      $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
      $this->db->where($whereAccess);
    }
    $this->db->from('Transactions');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Bids.listingId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = PendingListings.listingId", 'left');
    if($dmaId > 0 && $dmaMemberId > 0) {
      $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
      $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
      $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId AND ('open' = creditAccessDmaLevel.caAction OR 'watch' = creditAccessDmaLevel.caAction)", 'left');
    }
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

  function get_trade_transactions_of_taxpayer($taxpayerId, $filter, $getCredit, $dmaId = "", $dmaMemberId = "") {
    $this->db->select('Transactions.*');
    $this->db->where('Transactions.tradeId IS NOT NULL');
    $this->db->where('Transactions.tDeleted', null);
    $this->db->where('Transactions.taxpayerId', $taxpayerId);
    if($dmaId > 0 && $dmaMemberId > 0) {
      $whereAccess = "(creditAccessUserLevel.caAction = 'view' OR creditAccessUserLevel.caAction = 'edit' OR creditAccessDmaLevel.caAction = 'open' OR creditAccessDmaLevel.caAction = 'watch')";
      $this->db->where($whereAccess);
    }
    $this->db->from('Transactions');
    if($dmaId > 0 && $dmaMemberId > 0) {
      $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
      $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
      $this->db->join("creditAccess as creditAccessUserLevel", "PendingListings.listingId = creditAccessUserLevel.caListingId AND " . $dmaMemberId . " = creditAccessUserLevel.caDmaMemberId", 'left');
      $this->db->join("creditAccess as creditAccessDmaLevel", "PendingListings.listingId = creditAccessDmaLevel.caListingId AND " . $dmaId . " = creditAccessDmaLevel.caDmaId AND ('open' = creditAccessDmaLevel.caAction OR 'watch' = creditAccessDmaLevel.caAction)", 'left');
    }
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($this->get_transaction_count_for_trade($data['tradeId']) > 1) {
        $data['multiBuyer'] = true;
      } else {
        $data['multiBuyer'] = false;
      }
      /*
        if($data['taxpayerId']>0) {
          $data['tpNameToUse'] = ($data['tpAccountType']==1) ? $data['tpCompanyName'] : $data['tpFirstName']." ".$data['tpLastName'];
        } else {
          $data['taxpayerId']=0;
          $data['tpAccountType']=1;
          $data['tpCompanyName']=$data['companyName'];
          $data['tpFirstName']=$data['companyName'];
          $data['tpLastName']='';
          $data['tpNameToUse']='';
        }
  */
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

  function get_trade_transactions_of_user_pending_action($userId, $type) {
    $this->db->select('Transactions.*, PendingListings.listingId, PendingListings.stateCertNum, PendingListings.projectNameExt, PendingListings.country_id, Trades.tradePrice, Trades.tradeType, Trades.utilizationTypeId, Trades.status, States.State, Bids.bidId, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName');
    $this->db->where('Transactions.tradeId IS NOT NULL');
    if($type == 'dma') {
      $this->db->where('Transactions.tDmaId', $userId);
    } else {
      $this->db->where('Transactions.buyerAccountId', $userId);
    }
    $this->db->where('PendingListings.cOrigin !=', 'loaded_purchase');
    $this->db->where('Transactions.tDeleted is null');
    $this->db->where('Trades.status', '1');
    $this->db->from('Transactions');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
    $this->db->join("PendingListings", "PendingListings.listingId = Bids.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    $this->db->join("States", "States.state = IncentivePrograms.State", 'left');
    $this->db->join("Taxpayers", "Taxpayers.taxpayerId = Transactions.taxpayerId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      if($data['buyerSigned'] != 1 || $data['buyerPaid'] != 1) {

        if($this->get_transaction_count_for_trade($data['tradeId']) > 1) {
          $data['multiBuyer'] = true;
        } else {
          $data['multiBuyer'] = false;
        }

        $data['tpNameToUse'] = ($data['tpAccountType'] == 1) ? $data['tpCompanyName'] : $data['tpFirstName'] . " " . $data['tpLastName'];

        array_push($return, $data);
      }
    }

    return $return;

  }

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

  function get_transaction_by_id($transactionId) {
    $this->db->select('Transactions.*, ActiveListings.listingId, Taxpayers.tpAccountType, Taxpayers.tpCompanyName, Taxpayers.tpFirstName, Taxpayers.tpLastName, Taxpayers.tpEmailSigner');
    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->from('Transactions');
    $this->db->join("Bids", "Bids.bidId = Transactions.tBidId", 'left');
    $this->db->join("Trades", "Trades.tradeId = Transactions.tradeId", 'left');
    $this->db->join("ActiveListings", "ActiveListings.listingId = Trades.listingId", 'left');
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

  function add_pending_transaction($bidId, $buyerAccountId, $taxpayerId, $tCreditAmount, $openBidId, $tBrokerDmaId = "") {
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
        'tBrokerDmaId'   => $tBrokerDmaId,
        'tDmaId'         => $this->cisession->userdata('dmaId'),
    ];

    $this->db->insert('Transactions', $data);

    return $this->db->insert_id();
  }

  function add_active_transaction($request) {

    $data = [
        'tradeId'                   => $request['tradeId'],
        'buyerAccountId'            => $request['buyerAccountId'],
        'taxpayerId'                => $request['taxpayerId'],
        'utilizingEntityCustomName' => $request['utilizingEntityCustomName'],
        'tStage'                    => 0,
        'tCreditAmount'             => $request['tCreditAmount'],
        'tDmaId'                    => $request['tDmaId'],
        'tTimestamp'                => time(),
    ];

    $this->db->insert('Transactions', $data);

    return $this->db->insert_id();
  }

  function update_active_transaction($request) {

    $data = [
        'buyerAccountId'            => $request['buyerAccountId'],
        'taxpayerId'                => $request['taxpayerId'],
        'utilizingEntityCustomName' => $request['utilizingEntityCustomName'],
        'tCreditAmount'             => $request['tCreditAmount'],
    ];

    $this->db->where('Transactions.transactionId', $request['transactionId']);
    $this->db->update('Transactions', $data);

    return true;

  }

  function update_transaction($transactionId, $request) {

    $data = $request;

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;

  }

  function update_pending_transaction($transactionId, $tCreditAmount, $buyerAccountId = "", $taxpayerId = "", $utilizingEntityCustomName = "") {

    $data = [];
    $data['tCreditAmount'] = $tCreditAmount;
    if($buyerAccountId > 0) {
      $data['buyerAccountId'] = $buyerAccountId;
    }
    if(isset($taxpayerId) && $taxpayerId != 0) {
      $data['taxpayerId'] = $taxpayerId;
    }
    if(isset($utilizingEntityCustomName) && $utilizingEntityCustomName != "") {
      $data['utilizingEntityCustomName'] = $utilizingEntityCustomName;
    }

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;
  }

  function delete_transaction($transactionId) {
    $data = [
        'tDeleted' => 1,
    ];

    $this->db->where('Transactions.transactionId', $transactionId);
    $this->db->update('Transactions', $data);

    return true;
  }

  function activate_transactions_for_a_sale($bidId, $tradeId) {
    $data = [
        'tradeId'    => $tradeId,
        'tStage'     => 0,
        'tTimestamp' => time(),
    ];

    $this->db->where('tBidId', $bidId);
    $this->db->update('Transactions', $data);
  }

  function update_trade_status($tradeId, $status) {

    if($status == 2) {
      $data = [
          'status'                  => $status,
          'executionDate'           => time(),
          'settlementDate'          => time(),
          'closingProcessStartDate' => $executionDate,
      ];
    } else {
      if($status == 1) {
        $data = [
            'status'                  => $status,
            'executionDate'           => null,
            'settlementDate'          => null,
            'closingProcessStartDate' => time(),
        ];
      } else {
        $data = [
            'status'                  => $status,
            'executionDate'           => null,
            'settlementDate'          => null,
            'closingProcessStartDate' => null,
        ];
      }
    }

    $this->db->where('Trades.tradeId', $tradeId);
    $this->db->update('Trades', $data);

    return true;
  }

  function update_seller_received_payment($request) {

    $tradeId = isset($request['tradeId']) ? $request['tradeId'] : null;
    $sellerRecPayment = isset($request['sellerRecPayment']) ? $request['sellerRecPayment'] : null;
    $sellerRecPaymentMethod = isset($request['sellerRecPaymentMethod']) ? $request['sellerRecPaymentMethod'] : null;
    $sellerRecPaymentDate = isset($request['sellerRecPaymentDate']) ? $request['sellerRecPaymentDate'] : null;
    $paymentNotes = isset($request['paymentNotes']) ? $request['paymentNotes'] : null;

    $data = [];
    $data['sellerRecPayment'] = $sellerRecPayment;
    $data['sellerRecPaymentMethod'] = $sellerRecPaymentMethod;
    $data['sellerRecPaymentDate'] = $sellerRecPaymentDate;
    $data['paymentNotes'] = $paymentNotes;

    $this->db->where('Trades.tradeId', $tradeId);
    $this->db->update('Trades', $data);

    return true;
  }

  function get_pending_trades_no_folders($request) {

    $this->db->select('Trades.tradeId, Trades.listingId, IncentivePrograms.State');
    //$this->db->where('Trades.status', 0);
    $this->db->where('Trades.hasFolders', null);
    $this->db->from('Trades');
    $this->db->join("PendingListings", "PendingListings.listingId = Trades.listingId", 'left');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = PendingListings.OIXIncentiveId", 'left');
    if($request['limit'] > 0) {
      $this->db->limit($request['limit']);
    }
    $this->db->order_by("Trades.tradeId desc");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function mark_trade_folders_status($tradeId, $folderStatus) {
    $data = [
        'hasFolders' => $folderStatus,
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  function update_trade_notes($tradeId, $tradeNotes) {
    $data = [
        'tradeNotes' => $tradeNotes,
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  function update_trade_field($tradeId, $request) {

    $data = [];
    foreach($request as $k => $v) {
      $data[$k] = $v;
    }

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  function update_trade($request) {

    if($request['tradeSize'] > 0) {
      $data['tradeSize'] = $request['tradeSize'];
    }
    if($request['tradePrice'] > 0) {
      $data['tradePrice'] = $request['tradePrice'];
    }
    if($request['timeStamp'] > 0) {
      $data['timeStamp'] = $request['timeStamp'];
    }
    if($request['accountId'] > 0) {
      $data['accountId'] = $request['accountId'];
    }
    if($request['utilizationTypeId'] > 0) {
      $data['utilizationTypeId'] = $request['utilizationTypeId'];
    }
    if($request['tExchangeRate'] > 0) {
      $data['tExchangeRate'] = $request['tExchangeRate'];
    }
    if($request['utilizingEntityType'] != "") {
      $data['utilizingEntityType'] = $request['utilizingEntityType'];
    }
    if($request['tradeIsEstimated'] == "" || $request['tradeIsEstimated'] == 0 || $request['tradeIsEstimated'] == 1) {
      $data['tradeIsEstimated'] = $request['tradeIsEstimated'];
    }

    $this->db->where('tradeId', $request['tradeId']);
    $this->db->update('Trades', $data);
  }

  function delete_trade($tradeId) {
    $data = [
        'deleteMarker' => 1,
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  function connect_taxpayer_to_trade($tradeId, $userId) {
    $data = [
        'taxpayerUserId' => $userId,
    ];

    $this->db->where('tradeId', $tradeId);
    $this->db->update('Trades', $data);
  }

  function connect_taxpayer_to_transaction($transactionId, $userId) {
    $data = [
        'taxpayerUserId' => $userId,
    ];

    $this->db->where('transactionId', $transactionId);
    $this->db->update('Transactions', $data);
  }

}

/* End of file users.php */
/* Location: ./application/models/programs.php */
