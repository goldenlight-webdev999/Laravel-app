<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class IncentivePrograms extends CI_Model {
  private $table_name = 'IncentivePrograms';

  private $dmaAccountFieldTypes = [
      'program_type'                   => [
          'tbl_name'         => 'ProgramType',
          'tbl_field_id'     => 'ProgramTypeId',
          'tbl_field_name'   => 'ProgramTypeName',
          'tbl_field_dma_id' => 'ProgramTypeDmaId',
      ],
      'certification_status'           => [
          'tbl_name'         => 'cert_status_type',
          'tbl_field_id'     => 'cert_status_id',
          'tbl_field_name'   => 'cert_status_name',
          'tbl_field_dma_id' => 'cert_status_dmaId',
      ],
      'monetization_status'            => [
          'tbl_name'         => 'monetization_status',
          'tbl_field_id'     => 'mnsId',
          'tbl_field_name'   => 'mnsName',
          'tbl_field_dma_id' => 'mnsDmaId',
      ],
      'project_status'                 => [
          'tbl_name'         => 'project_status',
          'tbl_field_id'     => 'statusId',
          'tbl_field_name'   => 'projectStatus',
          'tbl_field_dma_id' => 'projectStatusDmaId',
      ],
      'audit_status'                   => [
          'tbl_name'         => 'audit_status',
          'tbl_field_id'     => 'statusId',
          'tbl_field_name'   => 'auditStatus',
          'tbl_field_dma_id' => 'auditCustomDmaId',
      ],
      'customer_type'                  => [
          'tbl_name'         => 'customer_type',
          'tbl_field_id'     => 'id',
          'tbl_field_name'   => 'name',
          'tbl_field_dma_id' => 'dma_id',
      ],
      'adjustment_cause_credit_amount' => [
          'tbl_name'         => 'adjustment_cause_credit_amount',
          'tbl_field_id'     => 'id',
          'tbl_field_name'   => 'name',
          'tbl_field_dma_id' => 'dma_id',
      ],
      'credit_utilization_type'        => [
          'tbl_name'         => 'credit_utilization_type',
          'tbl_field_id'     => 'id',
          'tbl_field_name'   => 'name',
          'tbl_field_dma_id' => 'dma_id',
      ],
  ];

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->table_name = $this->table_name;
  }

  function get_programs() {
    $query = $this->db->get('IncentivePrograms');

    return $query->result();
  }

  function get_program_index() {
    $this->db->select('State, OIXIncentiveId,ProgramName,Status');
    $query = $this->db->get('IncentivePrograms');

    return $query->result();
  }

  function get_programs_for_import() {

    $this->db->select('OIXIncentiveId, State, ProgramName, ipdpCountry, creditTypeId');
    $this->db->from('IncentivePrograms');
    $this->db->order_by("OIXIncentiveId", "asc");
    $query = $this->db->get();
    $data['programsKeyValue'] = [];
    $data['programsData'] = [];
    foreach($query->result_array() as $program) {
      $data['programsKeyValue'][$program['OIXIncentiveId']] = $program['State'] . "-" . $program['ProgramName'];
      $data['programsData'][$program['OIXIncentiveId']] = $program;
    }

    return $data;

  }

  function get_jurisdictions_for_import() {

    $this->db->select('state, name');
    $this->db->from('States');
    $this->db->order_by("state", "asc");
    $query = $this->db->get();
    $result = [];
    foreach($query->result_array() as $data) {
      $result[$data['state']] = $data['name'];
    }

    return $result;

  }

  function get_current_program($id) {

    $this->db->select('IncentivePrograms.*, ProgramType.ProgramTypeName, jurisdiction.country_id ipdpCountry, location_country.name as countryName, location_province.name, location_province.code as State, location_county.name countyName, location_town.name townName');
    $this->db->join("ProgramType", "IncentivePrograms.creditTypeId = ProgramType.ProgramTypeId", 'left');
    $this->db->join("jurisdiction", "jurisdiction.id = IncentivePrograms.jurisdiction_id", 'left');
    $this->db->join("location_country", "location_country.id = jurisdiction.country_id", 'left');
    $this->db->join("location_province", "location_province.id = jurisdiction.province_id", 'left');
    $this->db->join("location_county", "location_county.id = jurisdiction.county_id", 'left');
    $this->db->join("location_town", "location_town.id = jurisdiction.town_id", 'left');
    $query = $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);
    foreach($query->result() as $data) {
      $data->ipCategories = $this->get_ip_categories_from_program($data->OIXIncentiveId);
      $data->ipSectors = $this->get_ip_sectors_from_program($data->OIXIncentiveId);
      $data->ipTaxTypes = $this->get_ip_tax_types_from_program($data->OIXIncentiveId);
      //old?
      $data->OffSettingTaxList = $this->get_offsets_from_program($data->OffSettingTaxList);
    }

    return $query->row();

  }

  function get_program_history($id = "") {
    $this->db->select('IncentiveProgramChanges.*, IncentivePrograms.OIXIncentiveId, IncentivePrograms.ProgramName, States.name');
    if($id > 0) {
      $array = ['IncentiveProgramChanges.ipcIncentiveId' => $id];
      $this->db->where($array);
    }

    $this->db->where('IncentiveProgramChanges.FieldName !=', 'cchLastUpdated');

    $this->db->from('IncentiveProgramChanges');
    $this->db->join("IncentivePrograms", "IncentivePrograms.OIXIncentiveId = IncentiveProgramChanges.ipcIncentiveId", 'left');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->order_by('UpdatedDate', 'DESC');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $data['FieldNameText'] = $data["FieldName"];
      if($data["FieldName"] == "IncentiveSummary") {
        $data['FieldNameText'] = "Incentive Description";
      } else {
        if($data["FieldName"] == "IncentiveDetail") {
          $data['FieldNameText'] = "Amount";
        } else {
          if($data["FieldName"] == "cchApplicationProvisions") {
            $data['FieldNameText'] = "Application Provisions";
          } else {
            if($data["FieldName"] == "cchFilingRequirements") {
              $data['FieldNameText'] = "Filing Requirements";
            } else {
              if($data["FieldName"] == "cchIndustrySpecificConsiderations") {
                $data['FieldNameText'] = "Industry-Specific Considerations";
              } else {
                if($data["FieldName"] == "cchOther") {
                  $data['FieldNameText'] = "Other";
                } else {
                  if($data["FieldName"] == "cchBigLinks") {
                    $data['FieldNameText'] = "CCH Discussion";
                  } else {
                    if($data["FieldName"] == "cchContactInformation") {
                      $data['FieldNameText'] = "Contact Information";
                    }
                  }
                }
              }
            }
          }
        }
      }

      array_push($return, $data);
    }

    return $return;

  }

  function get_ip_categories_from_program($OIXIncentiveId) {
    $this->db->select('IPCategories.id, IPCategories.category');
    $this->db->join('IPCategoriesRel', "IPCategories.id = IPCategoriesRel.ipcrCategoryId", 'left');
    $this->db->order_by("category", "ASC");
    $query = $this->db->get_where('IPCategories', ['ipcrIncentiveId' => $OIXIncentiveId]);

    return $query->result();
  }

  function get_ip_sectors_from_program($OIXIncentiveId) {
    $this->db->select('IPSectors.id, IPSectors.sector');
    $this->db->join('IPSectorsRel', "IPSectors.id = IPSectorsRel.ipsrSectorId", 'left');
    $this->db->order_by("sector", "ASC");
    $query = $this->db->get_where('IPSectors', ['ipsrIncentiveId' => $OIXIncentiveId]);

    return $query->result();
  }

  function get_ip_tax_types_from_program($OIXIncentiveId = "") {
    $this->db->select('IPTaxTypes.id, IPTaxTypes.tax_type');
    $this->db->join('IPTaxTypesRel', "IPTaxTypes.id = IPTaxTypesRel.iptrTaxTypeId", 'left');
    $this->db->order_by("tax_type", "ASC");
    if($OIXIncentiveId > 0) {
      $query = $this->db->get_where('IPTaxTypes', ['iptrIncentiveId' => $OIXIncentiveId]);
    }

    return $query->result();
  }

  function get_tax_types() {
    $this->db->select('IPTaxTypes.*');
    $this->db->from('IPTaxTypes');
    $this->db->order_by("tax_type", "ASC");
    $query = $this->db->get();

    return $query->result_array();
  }

  function get_active_programs_by_state($id) {
    $this->db->select('IncentivePrograms.State, OIXIncentiveId,ProgramName, IPSectors.sector, States.name, OffsettingTaxList,(CASE WHEN Transferable=1 THEN "Transferable" ELSE "Refundable" END ) as ProgramType');
    $this->db->where('IncentivePrograms.State', $id);
    $this->db->where('IncentivePrograms.Status', '1');
    $this->db->from('IncentivePrograms');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("IPSectors", "IncentivePrograms.sector = IPSectors.id", 'left');
    $query = $this->db->get();
    //return $query->result();
    foreach($query->result() as $data) {
      $data->OffsettingTaxList = $this->get_offsets_from_program($data->OffsettingTaxList);
    }

    return $query->result();
  }

  function get_active_programs_by_sector($id) {
    $this->db->select('OIXIncentiveId,ProgramName');
    $this->db->where('IncentivePrograms.Sector', $id);
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->from('IncentivePrograms');
    $query = $this->db->get();

    return $query->result();
  }

  function get_programs_by_category($state, $id) {
    $this->db->select('OIXIncentiveId,ProgramName,IncentiveSummary');
    $this->db->where('IncentivePrograms.Category', $id);
    $this->db->where('IncentivePrograms.State', $state);
    $this->db->from('IncentivePrograms');
    $query = $this->db->get();

    return $query->result();
  }

  //This one is being replaced by get_programs_by_jurisdiction_custom_filter() as it now filters out "other" programs your DMA didnt' create
  function get_active_programs_by_state_short($id) {
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer,MandatedPriceFloor');
    if($id != "") {
      $this->db->where('IncentivePrograms.State', $id);
    }
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("OIXIncentiveId", "ascending");
    $query = $this->db->get();

    return $query->result();
  }

  function get_programs_by_jurisdiction_id($id) {

    $dmaIds = [];
    array_push($dmaIds, $this->cisession->userdata('dmaId'));
    //If is a parent, get CHILD DMA Accounts
    if($this->cisession->userdata('isParentDma')) {
      //Get child DMA Accounts
      $this->db->select('dmaAccounts.dmaId');
      $this->db->where('dmaAccounts.parentDmaId', $this->cisession->userdata('dmaId'));
      $this->db->from('dmaAccounts');
      $query = $this->db->get();
      foreach($query->result_array() as $data) {
        array_push($dmaIds, $data['dmaId']);
      }
    }
    //If is a child, get PARENT DMA Accounts
    if($this->cisession->userdata('parentDmaId') > 0) {
      array_push($dmaIds, $this->cisession->userdata('parentDmaId'));
    }
    //Convert to comma list
    $dmaIdsList = implode(',', $dmaIds);

    //Now let's get those programs!
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer,MandatedPriceFloor, Status, pDmaId');
    if($id != "") {
      $this->db->where('IncentivePrograms.jurisdiction_id', $id);
    }
    $this->db->where('(IncentivePrograms.pDmaId IN(' . $dmaIdsList . ') OR IncentivePrograms.pDmaId IS NULL)');

    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      array_push($return, $data);

    }

    return $return;

  }

  function get_programs_by_jurisdiction($id) {
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer,MandatedPriceFloor, Status, pDmaId');
    if($id != "") {
      $this->db->where('IncentivePrograms.State', $id);
    }
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      //Only return jurisdictions if they are open or if they are created by their DMA Account, parent
      if($data['Status'] == 5 && $this->cisession->userdata('level') == 4) {
        if($data['pDmaId'] == $this->cisession->userdata('dmaId')) {
          array_push($return, $data);
        }
      } else {
        if($data['Status'] == 1) {
          array_push($return, $data);
        }
      }

    }

    return $return;

  }

  function get_active_programs_by_state_short_ipdp($id) {
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer,MandatedPriceFloor');
    $this->db->where('IncentivePrograms.State', $id);
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->where('IncentivePrograms.status =', 1);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("OIXIncentiveId", "ascending");
    $query = $this->db->get();

    return $query->result();
  }

  function get_short_programs_by_state_short($id) {
    $this->db->select('OIXIncentiveId,ProgramShortName');
    $this->db->where('IncentivePrograms.State', $id);
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");
    $query = $this->db->get();

    return $query->result();
  }

  function get_credit_types_of_program($programId) {

    $this->db->select('Transferable, Refundable, allocated, ProgramType.ProgramTypeName, ProgramType.ProgramTypeId');
    $this->db->where('OIXIncentiveId', $programId);
    $this->db->from('IncentivePrograms');
    $this->db->join("ProgramType", "IncentivePrograms.creditTypeId = ProgramType.ProgramTypeId", 'left');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      if($data['Transferable'] == 1) {
        $return[1] = 'State Transferable Credit';
      }
      if($data['Refundable'] == 1) {
        $return[2] = 'State Refundable Credit';
      }
      if($data['allocated'] == 1) {
        $return[12] = 'Allocated Credit';
      }
      if($data['Transferable'] != 1 && $data['Refundable'] != 1 && $data['allocated'] != 1) {
        $return[$data['ProgramTypeId']] = $data['ProgramTypeName'];
      }

    }

    return $return;

  }

  function get_credit_types_of_international_program($programId) {

    $this->db->select('IncentivePrograms.creditTypeId,ProgramType.ProgramTypeName');
    $this->db->where('OIXIncentiveId', $programId);
    $this->db->from('IncentivePrograms');
    $this->db->join("ProgramType", "IncentivePrograms.creditTypeId = ProgramType.ProgramTypeId", 'left');
    $this->db->order_by("ProgramTypeOrder", "ASC");
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      $return[$data['creditTypeId']] = $data['ProgramTypeName'];

    }

    return $return;

  }

  function get_incentive_program_type($request) {

    $this->db->select('*');
    $this->db->from('ProgramType');
    $this->db->where('ProgramTypeId', $request['ProgramTypeId']);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {

      array_push($return, $data);

    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_offsets_by_state_short1($id) {
    $data['offsets'] = $this->get_offset_list($id);
    $str = str_replace("'", "", $data['offsets']);
    $str = preg_replace("/<!--.*?-->/", "", $data['offsets']);
    $this->db->select('Offsets.id,Offsets.offsetName');
    $this->db->from('Offsets');
    $this->db->where_in('Offsets.id1', $str);
    $query = $this->db->get();

    return $query->result();
  }

  function get_offsets_by_state_short($id) {
    $data['offsets'] = $this->get_offset_list($id);

    $this->db->select('Offsets.id,Offsets.offsetName');
    $this->db->from('Offsets');

    $where = "FIND_IN_SET(id,'" . implode(",", $data['offsets']) . "' )";
    $this->db->where($where, null, true);

    $query = $this->db->get();

    return $query->result();
  }

  function get_offsets_by_state_short_array($id) {
    $data['offsets'] = $this->get_offset_list($id);

    $this->db->select('Offsets.id,Offsets.offsetName');
    $this->db->from('Offsets');

    $where = "FIND_IN_SET(id,'" . implode(",", $data['offsets']) . "' )";
    $this->db->where($where, null, true);

    $query = $this->db->get();
    $data['offsets'] = "";
    $data['offsets']['0'] = 'Select Tax offset';
    foreach($query->result() as $offsets_key) {
      $data['offsets'][$offsets_key->id] = $offsets_key->offsetName;
    }

    return $data['offsets'];
  }

  function get_offset_list($id) {

    //TO DO - Since this is a non-standard query, we're going to verify that $id is clean for SQL injection (alphanumeric an dless than 5 characters)
    if(ctype_alnum($id) && strlen($id) < 5) {

      $query = "SELECT GROUP_CONCAT(distinct OffsettingTaxList ORDER BY OffsettingTaxList ASC SEPARATOR ',') as offsets FROM IncentivePrograms where state='" . $id . "'";

      $result = mysql_query($query) or die("SQL Error 1: " . mysql_error());

      // get data and store in a json array
      while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $market[] = [
            'offsets' => $row['offsets'],
        ];
      }

      return $market[0];

    }

  }

  function get_active_programs_by_state_temp() {
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer');
    $this->db->where('IncentivePrograms.State', 'LA');
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");
    $query = $this->db->get();

    return $query->result();
  }

  function get_sectors_by_active_program() {
    $this->db->select('IPSectors.sector, IPSectors.id');
    $this->db->distinct();
    $this->db->where('IncentivePrograms.Status !=', 0);
    $this->db->order_by("IPSectors.sector", "asc");
    $this->db->from('IncentivePrograms');
    $this->db->join("IPSectors", "IncentivePrograms.sector = IPSectors.id", 'left');
    $query = $this->db->get();
    //$return[''] = "Select Sector";
    $return['0'] = "ALL";
    foreach($query->result() as $data) {
      $return[$data->id] = $data->sector;
    }

    return $return;
  }

  function get_sectors_used() {
    $this->db->select('IPCategories.category, IPCategories.id');
    $this->db->where('IPCategories.forSearch = 1');
    $this->db->order_by("IPCategories.category", "asc");
    $this->db->from('IPCategories');
    $query = $this->db->get();
    //$return[''] = "Select Sector";
    $return['0'] = "All Industries";
    foreach($query->result() as $data) {
      $return[$data->id] = $data->category;
    }

    return $return;
  }

  function get_categories_by_active_program() {
    $this->db->select('IPCategories.category, IPCategories.id');
    $this->db->where('IncentivePrograms.Status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->join("IPCategories", "IncentivePrograms.Category = IPCategories.id", 'left');
    $this->db->distinct();
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $return[$data->id] = $data->category;
    }

    return $return;
  }

  function get_categories_by_all_program() {
    $this->db->select('IPCategories.category, IPCategories.id');
    $this->db->distinct();
    $this->db->from('IncentivePrograms');
    $this->db->join("IPCategories", "IncentivePrograms.Category = IPCategories.id", 'left');
    $query = $this->db->get();
    $return[''] = "";
    foreach($query->result() as $data) {
      $return[$data->id] = $data->category;
    }

    return $return;
  }

  function get_states_by_active_program() {
    $this->db->select('States.name, States.state');
    $this->db->distinct();
    $this->db->where('IncentivePrograms.Status !=', 0);
    $this->db->from('IncentivePrograms');
    $this->db->join("States", "IncentivePrograms.state = States.state", 'left');
    $this->db->order_by("States.name");
    $query = $this->db->get();
    $return['0'] = "Select Jurisdiction";
    foreach($query->result() as $data) {
      $return[$data->state] = $data->name;
    }

    return $return;
  }

  function get_all_countries() {
    $this->db->select('countries.name, countries.code');
    $this->db->distinct();
    $this->db->from('countries');
    $this->db->order_by('SOrder');

    $query = $this->db->get();

    foreach($query->result() as $row) {
      $data[$row->code] = $row->name;
    }

    return ($data);

  }

  function get_all_countries_id_name() {
    $this->db->select('countries.name, countries.id');
    $this->db->distinct();
    $this->db->from('countries');
    $this->db->order_by('SOrder');

    $query = $this->db->get();

    foreach($query->result() as $row) {
      $data[$row->id] = $row->name;
    }

    return ($data);

  }

  function getCountryById($id) {
    $this->db->select('countries.id, countries.name');
    $this->db->from('countries');
    $this->db->where('countries.id', $id);
    $query = $this->db->get();
    if($query->num_rows() > 0) {
      $row = $query->row_array();

      return $row;
    }
  }

  function get_states_by_all_program() {
    $this->db->select('States.name, States.state');
    $this->db->distinct();
    $this->db->from('IncentivePrograms');
    $this->db->join("States", "IncentivePrograms.state = States.state", 'left');
    $this->db->order_by("States.name");
    $query = $this->db->get();
    //$return['default'] = "";
    foreach($query->result() as $data) {
      $return[$data->state] = $data->name;
    }

    return $return;
  }

  function get_states_empty() {
    $this->db->select('States.name, States.state');
    $this->db->distinct();
    $this->db->from('States');
    $query = $this->db->get();
    //$return['default'] = "";
    $data['0'] = "Select State";

    /*foreach ($query->result() as $row)
    {
      $data[$row->state] = $row->name;
    }*/

    return ($data);

  }

  function get_all_states() {
    $this->db->select('States.name, States.state');
    $this->db->distinct();
    $this->db->from('States');
    $this->db->where('States.CountryId', 1);
    $this->db->where('States.stateTypeCode', 'S');
    $this->db->order_by('CountryId,SOrder');
    $query = $this->db->get();
    //$return['default'] = "";
    $data['0'] = "All Jurisdictions";

    foreach($query->result() as $row) {
      $data[$row->state] = $row->name;
    }

    return ($data);

  }

  function get_tax_years_for_messaging() {
    $this->db->select('id, taxYear');
    $this->db->distinct();
    $this->db->from('TaxYear');
    $this->db->where('TaxYear.taxYear > 2006');
    $query = $this->db->get();
    $return[''] = "Select Year";
    foreach($query->result() as $data) {
      $return[$data->id] = $data->taxYear;
    }

    return $return;
  }

  function get_offsets_from_program($offsets) {
    $offsets_arr = explode(",", $offsets);
    $return_string = "";
    foreach($offsets_arr as $key => $pair) {
      $query = $this->db->get_where('Offsets', ['id' => $pair]);
      $this_o = $query->row();
      if(isset($this_o->shortOffset)) {
        $return_string .= $this_o->shortOffset;
        if($key < count($offsets_arr) - 1) {
          $return_string .= ", ";
        }
      }
    }

    return $return_string;
  }

  function get_offsets_short_from_program($offsets) {
    $offsets_arr = explode(",", $offsets);
    $return_string = "";
    foreach($offsets_arr as $key => $pair) {
      $query = $this->db->get_where('Offsets', ['id' => $pair]);
      $this_o = $query->row();
      $return_string .= $this_o->offsetName;
      if($key < count($offsets_arr) - 1) {
        $return_string .= ", ";
      }
    }

    return $return_string;
  }

  function get_offsets_by_active_program() {

    //$query = $this->db->get('Offsets');
    $this->db->select('*');
    $this->db->distinct();
    $this->db->order_by("Offsets.offsetName", "asc");
    $this->db->from('Offsets');
    $query = $this->db->get();
    $data['offsets'] = "";
    $data['offsets']['0'] = 'All Tax Offsets';
    foreach($query->result() as $offsets_key) {
      $data['offsets'][$offsets_key->id] = $offsets_key->offsetName;
    }

    return $data['offsets'];
  }

  function get_taxyear_by_active_program() {
    $this->db->select('*');
    $this->db->from('TaxYear');
    $this->db->order_by("taxYear", "asc");
    $query = $this->db->get();
    $data['taxyear'] = [];
    $data['taxyear'][0] = 'All Tax Years';
    foreach($query->result() as $taxyear_key) {
      $data['taxyear'][$taxyear_key->id] = $taxyear_key->taxYear;
    }

    return $data['taxyear'];
  }

  function get_cert_status_type() {
    $query = $this->db->get('cert_status_type');
    $data['cert_status'] = "";
    $data['cert_status'][''] = '';
    foreach($query->result() as $cert_status_name_key) {
      $data['cert_status'][$cert_status_name_key->cert_status_id] = $cert_status_name_key->cert_status_name;
    }

    return $data['cert_status'];
  }

  function get_program_type() {
    $query = $this->db->get('ProgramType');
    $data['p_type'] = "";
    $data['p_type'][''] = '';
    foreach($query->result() as $p_type_name_key) {
      $data['p_type'][$p_type_name_key->ProgramTypeId] = $p_type_name_key->ProgramTypeName;
    }

    return $data['p_type'];
  }

  function get_incentive_programs() {
    $query = $this->db->get('IncentivePrograms');
    $data['prev_offset'] = "";
    $data['prev_offset'][''] = '';
    foreach($query->result() as $prev_offset_key) {
      $data['prev_offset'][$prev_offset_key->OIXIncentiveId] = $prev_offset_key->ProgramName;
    }

    return $data['prev_offset'];
  }

  function get_state($id) {
    $this->db->where('State', strtoupper($id));
    $query = $this->db->get('States');
    $data['local'] = [];
    foreach($query->result() as $state_key) {
      $data['local'][$state_key->state] = $state_key->name;
    }

    return $data['local'];
  }

  function get_state_by_code($id) {
    $this->db->where('state', strtoupper($id));
    $query = $this->db->get('States');
    $data['local'] = [];
    foreach($query->result() as $state_key) {
      $data['local'][$state_key->state] = $state_key->name;
    }

    return $data['local'];
  }

  function get_jurisdiction_by_code($stateCode) {

    $this->db->select("*");
    $this->db->from("States");
    $this->db->where("state", $stateCode);
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_jurisdiction_by_name($jurisdictionName) {

    $this->db->select("States.*, parentJurisdiction.state as parentJurisdictionCode, parentJurisdiction.name as s");
    $this->db->from("States");
    $this->db->where("States.name", $jurisdictionName);
    $this->db->join("States as parentJurisdiction", "States.parentStateId = parentJurisdiction.id", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_jurisdiction_by_ids($countryId, $provinceId = null, $countyId = null) {
    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $this->db->select("jurisdiction.id, loc_c.name AS country_name, loc_c.id AS country_id, loc_c.default_currency_code AS country_currency, loc_co.name as county_name, loc_co.id AS county_id, loc_t.name as town_name, loc_t.id AS town_id, $q1 as name, loc_p.code as parentJurisdictionCode, loc_p.name as s, loc_p.id AS province_id", false);
    $this->db->from("jurisdiction");
    $this->db->where("jurisdiction.town_id", null);
    $this->db->where("jurisdiction.county_id", $countyId);
    $this->db->where("jurisdiction.province_id", $provinceId);
    $this->db->where("jurisdiction.country_id", $countryId);
    $this->db->join("location_country as loc_c", "loc_c.id = jurisdiction.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = jurisdiction.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = jurisdiction.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = jurisdiction.town_id", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_jurisdiction_by_id($id) {
    $q1 = \OIX\Services\JurisdictionService::$jurisdiciton_name_query;
    $this->db->select("loc_c.name AS country_name, loc_c.id AS country_id, loc_c.default_currency_code AS country_currency, loc_co.name as county_name, loc_co.id AS county_id, loc_t.name as town_name, loc_t.id AS town_id, $q1 as name, loc_p.code as parentJurisdictionCode, loc_p.name as s, loc_p.id AS province_id", false);
    $this->db->from("jurisdiction");
    $this->db->where("jurisdiction.id", $id);
    $this->db->join("location_country as loc_c", "loc_c.id = jurisdiction.country_id", 'left');
    $this->db->join("location_province as loc_p", "loc_p.id = jurisdiction.province_id", 'left');
    $this->db->join("location_county as loc_co", "loc_co.id = jurisdiction.county_id", 'left');
    $this->db->join("location_town as loc_t", "loc_t.id = jurisdiction.town_id", 'left');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }
  }

  function get_states() {
    $query = $this->db->get('States');
    $data['state'] = [];
    $data['state']['default'] = '';
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->name;
    }

    return $data['state'];
  }

  function get_states_short() {
    $this->db->select("*");
    $this->db->from("States");
    $this->db->order_by("States.state", "asc");
    $query = $this->db->get();
    $data['state'] = [];
    $data['state']['default'] = 'required';
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->state;
    }

    return $data['state'];
  }

  function get_us_states() {
    $this->db->select("*");
    $this->db->where('countryId =', 1);
    $this->db->from("States");
    $this->db->order_by("States.state", "asc");
    $query = $this->db->get();
    $data['state'] = [];
    $data['state']['default'] = 'required';
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->state;
    }

    return $data['state'];
  }

  function get_us_state_names() {
    $this->db->select("*");
    $this->db->where('countryId', 1);
    $this->db->from("States");
    $this->db->order_by("States.name", "asc");
    $query = $this->db->get();
    $data['state'] = [];
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->name;
    }

    return $data['state'];
  }

  function get_user_states() {
    $this->db->where('Status !=', 0);
    $query = $this->db->get('States');
    $data['state'] = "";
    $data['state'][''] = '';
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->name;
    }

    return $data['state'];
  }

  function get_sectors() {
    $this->db->order_by('sector');
    $query = $this->db->get('IPSectors');
    $data['sector'] = [];
    foreach($query->result() as $sector_key) {
      $data['sector'][$sector_key->id] = $sector_key->sector;
    }

    return $data['sector'];
  }

  function get_categories() {
    $this->db->order_by('category');
    $query = $this->db->get('IPCategories');
    $data['category'] = [];
    foreach($query->result() as $category_key) {
      $data['category'][$category_key->id] = $category_key->category;
    }

    return $data['category'];
  }

  function get_years() {
    $data = '';
    for($i = 0; $i <= 5; $i++) {
      $data[$i] = $i;
    }

    return $data;
  }

  function get_previous_years() {
    $years = date('Y') - 10;
    $year[''] = "";
    while($years <= date('Y')) {
      $year[$years] = $years;
      $years++;
    }

    return $year;
  }

  function get_mandated_floor_prices() {
    $data[0] = '';
    for($i = 60; $i <= 99; $i += 1) {
      $data[$i] = $i / 100;
    }

    return $data;
  }

  function get_agency_buyback_prices() {
    $data[0] = '';
    for($i = 70; $i <= 99; $i += 1) {
      $data[$i] = $i / 100;
    }

    return $data;
  }

  function check_if_program_exists($request) {

    $this->db->select("OIXIncentiveId, pDmaId");
    $this->db->from("IncentivePrograms");
    if(isset($request['pDmaId'])) {
      $this->db->where_in("pDmaId", $request['pDmaId']);
    }
    $this->db->where("ProgramName", $request['ProgramName']);
    $this->db->where("jurisdiction_id", $request['jurisdiction_id']);
    //$this->db->limit(1);

    $query = $this->db->get();
    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function insert_custom_incentive_program($request) {

    $this->db->insert('IncentivePrograms', $request);

    return $this->db->insert_id();

  }

  function update_custom_incentive_program($id, $data) {

    $this->db->where('OIXIncentiveId', $id);
    $this->db->update('IncentivePrograms', $data);

    return $id;

  }

  function check_if_program_type_exists($request) {

    $existingCustomProgramTypeId = 0;

    $this->db->select("ProgramTypeId");
    $this->db->from("ProgramType");
    $this->db->where("ProgramTypeName", $request['ProgramTypeName']);
    $this->db->limit(1);

    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      $existingCustomProgramTypeId = $data["ProgramTypeId"];
    }

    return $existingCustomProgramTypeId;

  }

  function insert_custom_program_type($request) {

    $this->db->insert('ProgramType', $request);

    return $this->db->insert_id();

  }

  function add_program_details() {
    $dum_arr = $this->input->post();
    echo array_key_exists('/checkbox-/', $dum_arr);
    $data = [
        'Status'                          => $this->input->post('status'),
        'State'                           => $this->input->post('state'),
        'ProgramName'                     => $this->input->post('program_name'),
        'Category'                        => $this->input->post('category'),
        'Sector'                          => $this->input->post('sector'),
        'AKAProgramName'                  => $this->input->post('aka'),
        'TypicalClaimantBeneficiary'      => $this->input->post('beneficiary'),
        //'Goal'=> $this->input->post('goal'),
        'IncentiveSummary'                => $this->input->post('incentive_summary'),
        'EligibilitySummary'              => $this->input->post('eligibility_summary'),
        'IncentiveDetail'                 => $this->input->post('incentive_detail'),
        'EligibilityDetail'               => $this->input->post('eligibility_detail'),
        'StatutoryIncentiveDescription'   => $this->input->post('statutory_incentive_description'),
        'StatutoryEligibilityDescription' => $this->input->post('statutory_eligibility_description'),
        'Transferable'                    => $this->input->post('transferable'),
        'MultiTransfer'                   => $this->input->post('multitransferable'),
        'NumTimesTransferable'            => $this->input->post('timestransferable'),
        'Refundable'                      => $this->input->post('refundable'),
        'RefundYears'                     => $this->input->post('refundyears'),
    ];
    $this->db->insert('IncentivePrograms', $data);

    return $this->db->insert_id();
  }

  function update_program_details($id) {

    $data = [
        'Status'                          => $this->input->post('status'),
        'State'                           => $this->input->post('state'),
        'ProgramName'                     => $this->input->post('program_name'),
        'Category'                        => $this->input->post('category'),
        'Sector'                          => $this->input->post('sector'),
        'AKAProgramName'                  => $this->input->post('aka'),
        'TypicalClaimantBeneficiary'      => $this->input->post('beneficiary'),
        //'Goal'=> $this->input->post('goal'),
        'IncentiveSummary'                => $this->input->post('incentive_summary'),
        'EligibilitySummary'              => $this->input->post('eligibility_summary'),
        'IncentiveDetail'                 => $this->input->post('incentive_detail'),
        'EligibilityDetail'               => $this->input->post('eligibility_detail'),
        'StatutoryIncentiveDescription'   => $this->input->post('statutory_incentive_description'),
        'StatutoryEligibilityDescription' => $this->input->post('statutory_eligibility_description'),
        'Transferable'                    => $this->input->post('transferable'),
        'MultiTransfer'                   => $this->input->post('multitransferable'),
        'NumTimesTransferable'            => $this->input->post('timestransferable'),
        'Refundable'                      => $this->input->post('refundable'),
        'RefundYears'                     => $this->input->post('refundyears'),
        //'OffSettingTaxList'=>
    ];
    $this->db->where('OIXIncentiveId', $id);
    $this->db->update('IncentivePrograms', $data);

    return $id;

  }

  function update_legal_details($id) {
    $data = [
        'Note'                            => $this->input->post('note'),
        'TransferSummary'                 => $this->input->post('transfer_summary'),
        'LimitationsSummary'              => $this->input->post('limitations_summary'),
        'TransferDetails'                 => $this->input->post('transfer_detail'),
        'StatutoryTransferDescription'    => $this->input->post('statutory_transfer_description'),
        'StatutoryLimitationsDescription' => $this->input->post('statutory_limitations_description'),
        'RefundableSummary'               => $this->input->post('refund_summary'),
        'ClawbackRecapture'               => $this->input->post('clawback'),
        'RefundableDetails'               => $this->input->post('refund_details'),
        'ClawbackRecaptureSummary'        => $this->input->post('clawback_summary'),
        'RefundRules'                     => $this->input->post('statutory_refund_rules'),
        'ClawbackRecaptureDetails'        => $this->input->post('clawback_detail'),
    ];
    $this->db->where('OIXIncentiveId', $id);
    $this->db->update('IncentivePrograms', $data);

    return $id;
  }

  function update_technical_details($id) {

    $de = strtotime($this->input->post('date_enacted'));
    $sd = strtotime($this->input->post('sunset_date'));

    $data = [
        'DateEnacted'                  => date("Y-m-d H:i:s", $de),
        'SunsetDate'                   => date("Y-m-d H:i:s", $sd),
        'AnnualCapPerProject'          => $this->input->post('annual_cap_per_project'),
        'AnnualCapYN'                  => $this->input->post('annual_cap_bool'),
        'AnnualCap'                    => $this->input->post('annual_cap'),
        'ProgramCap'                   => $this->input->post('program_cap'),
        'StatuteNumber'                => $this->input->post('statute_number'),
        'StatuteSection'               => $this->input->post('statute_section'),
        'StatuteAmendments'            => $this->input->post('statute_amendments'),
        'StatuteLink'                  => $this->input->post('statute_link'),
        'LegalReference'               => $this->input->post('legal_reference'),
        'TransferApprovalReq'          => $this->input->post('transfer_approval_required'),
        'TransferProcess'              => $this->input->post('transfer_process'),
        'ApplicationProcess'           => $this->input->post('application_process'),
        'ProgramRegulations'           => $this->input->post('regulations'),
        'ProgramRegulationsLink'       => $this->input->post('regulations_link'),
        'ProgramAmountIssuedtoDate'    => $this->input->post('program_amount_issued_to_date'),
        'AnnualizedEstimates2012'      => $this->input->post('estimate_2012'),
        'AnnualizedForescast2013'      => $this->input->post('forecast_2013'),
        'EstimatedNumberBeneficiaries' => $this->input->post('estimated_number_of_beneficiaries'),
        'AdministeringAgency'          => $this->input->post('administering_agency'),
        'ApprovingAgency'              => $this->input->post('approving_agency'),
        'MandatedPriceFloor'           => $this->input->post('mandated_price_floor'),
        'AgencyBuybackPrice'           => $this->input->post('agency_buyback_price'),
        'AgencyID'                     => $this->input->post('agency_id'),
        'SellerListingFacts'           => $this->input->post('seller_listing_should_know'),
        'BuyerAcquiringFacts'          => $this->input->post('buyer_acquiring_should_know'),
        'PreApplicationRequired'       => $this->input->post('pre_application_required'),
        'ApprovalReq'                  => $this->input->post('pre_approval_required'),
    ];
    $this->db->where('OIXIncentiveId', $id);
    $this->db->update('IncentivePrograms', $data);

    return $id;

  }

  function update_agency_details($id) {
    $data = [
        'AgencyProgramDescription' => $this->input->post('agency_program_description'),
        'AgencyLink'               => $this->input->post('agency_process_link'),
        'ProgramApplication'       => $this->input->post('program_application'),
        'TransferForms'            => $this->input->post('transfer_forms'),
        'RefundForms'              => $this->input->post('refund_forms'),
        'contactName1'             => $this->input->post('primary_agency_contact_name'),
        'contactAddr1'             => $this->input->post('primary_address'),
        'contactCity1'             => $this->input->post('primary_city'),
        'contactState1'            => $this->input->post('primary_state'),
        'contactZip1'              => $this->input->post('primary_zip'),
        'contactEMail1'            => $this->input->post('primary_email'),
        'contactPhone1'            => $this->input->post('primary_phone'),
        'contactName2'             => $this->input->post('secondary_agency_contact_name'),
        'contactAddr2'             => $this->input->post('secondary_address'),
        'contactCity2'             => $this->input->post('secondary_city'),
        'contactState2'            => $this->input->post('secondary_state'),
        'contactZip2'              => $this->input->post('secondary_zip'),
        'contactEMail2'            => $this->input->post('secondary_email'),
        'contactPhone2'            => $this->input->post('secondary_phone'),
        'TaxExpenditureReport'     => $this->input->post('tax_expenditure_report'),
        'AnnualBudget'             => $this->input->post('annual_budget'),
        'DataSourceOther'          => $this->input->post('other'),
    ];
    $this->db->where('OIXIncentiveId', $id);
    $query = $this->db->update('IncentivePrograms', $data);

    return $id;

  }

  function get_program_details($id) {
    $this->db->select('OIXIncentiveId,Status,State,ProgramName,Category,Sector,AKAProgramName,TypicalClaimantBeneficiary,IncentiveSummary,EligibilitySummary,IncentiveDetail,EligibilityDetail,StatutoryIncentiveDescription,StatutoryEligibilityDescription,Transferable,MultiTransfer,NumTimesTransferable,Refundable,RefundYears');
    $data = '';
    $query = $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);
    foreach($query->row() as $key => $value) {
      $data[$key] = $value;

    }

    return $data;
  }

  function get_legal_details($id) {
    $this->db->select('Note,TransferSummary,LimitationsSummary,TransferDetails,StatutoryTransferDescription,StatutoryLimitationsDescription,RefundableSummary,ClawbackRecapture,RefundableDetails,ClawbackRecaptureSummary,RefundRules,ClawbackRecaptureDetails');
    $data = '';
    $query = $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);
    foreach($query->row() as $key => $value) {
      $data[$key] = $value;
    }

    return $data;
  }

  function calculate_percent() {

    for($i = 99; $i > 50; $i--) {
      $data[$i] = $i . "%";
    }

    return $data;
  }

  function get_technical_details($id) {

    $this->db->select('DateEnacted,SunsetDate,AnnualCapPerProject,AnnualCapYN,AnnualCap,ProgramCap,StatuteNumber,StatuteSection,StatuteAmendments,StatuteLink,LegalReference,TransferApprovalReq,TransferProcess,ApplicationProcess,ProgramRegulations,ProgramRegulationsLink,ProgramAmountIssuedtoDate,AnnualizedEstimates2012,AnnualizedForescast2013,EstimatedNumberBeneficiaries,AdministeringAgency,ApprovingAgency,MandatedPriceFloor,AgencyBuybackPrice,AgencyID,SellerListingFacts,BuyerAcquiringFacts,PreApplicationRequired,ApprovalReq');
    $data = '';
    $query = $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);
    foreach($query->row() as $key => $value) {
      $data[$key] = $value;
      if($key == "DateEnacted") {
        $data[$key] = date("m/d/Y", strtotime($value));
      }
      if($key == "SunsetDate") {
        $data[$key] = date("m/d/Y", strtotime($value));
      }
    }

    return $data;
  }

  function get_agency_information($id) {

    $this->db->select('AgencyProgramDescription,AgencyLink,ProgramApplication,TransferForms,RefundForms,contactName1,contactAddr1,contactCity1,contactState1,contactZip1,contactEMail1,contactPhone1,contactName2,contactAddr2,contactCity2,contactState2,contactZip2,contactEMail2,contactPhone2,TaxExpenditureReport,AnnualBudget,DataSourceOther');
    $data = '';
    $query = $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);
    foreach($query->row() as $key => $value) {
      $data[$key] = $value;
    }

    return $data;
  }

  function get_offsets() {
    $query = $this->db->get('Offsets');

    return $query->result_array();
  }

  function get_offset_by_id($id) {
    $this->db->select('offsetName');
    $query = $this->db->get_where('Offsets', ['id' => $id]);

    return $query->row();
  }

  function get_program_by_id($id) {
    $this->db->select('ProgramName');
    $query = $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);

    return $query->row();
  }

  function get_incentive_program_by_id($id) {
    $this->db->select('ProgramName');
    $this->db->get_where('IncentivePrograms', ['OIXIncentiveId' => $id]);

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function get_cert_status_by_id($id) {
    $this->db->select('cert_status_name');
    $query = $this->db->get_where('cert_status_type', ['cert_status_id' => $id]);

    return $query->row();
  }

  function get_ptype_by_id($id) {
    $this->db->select('ProgramTypeName');
    $query = $this->db->get_where('ProgramType', ['ProgramTypeId' => $id]);

    return $query->row();
  }

  function get_taxyears_by_id($id) {
    $this->db->select('taxYear');
    $query = $this->db->get_where('TaxYear', ['id' => $id]);

    return $query->row();
  }

  function get_offsets_for_dropdown() {
    $query = $this->db->get('Offsets');
    $data['offsets'] = "";
    $data['offsets']['default'] = '';
    foreach($query->result() as $offsets_key) {
      $data['offsets'][$offsets_key->id] = $offsets_key->offsetName;
    }

    return $data['offsets'];
  }

  function get_program_by_state($id) {
    $query = $this->db->get_where('IncentivePrograms', ['State' => $id]);
    foreach($query->row() as $key => $value) {
      $data[$key] = $value;
    }
  }

  function list_countries() {
    $this->db->select('countries.id as countryId, countries.name as countryName');
    $this->db->where('cDmaId', null);
    $this->db->or_where('cDmaId', $this->cisession->userdata('dmaId'));
    if($this->cisession->userdata('parentDmaId') > 0) {
      $this->db->or_where('cDmaId', $this->cisession->userdata('parentDmaId'));
    }
    $this->db->from('countries');
    $this->db->order_by("countries.sOrder", "asc");

    $query = $this->db->get();
    $return = [];
    $return[0] = "Select a Region";
    foreach($query->result_array() as $row) {
      $return[$row["countryId"]] = $row["countryName"];
    }

    return $return;
  }

  function get_cert_status_type_by_id($id) {
    $this->db->select('cert_status_name');
    $this->db->where('cert_status_id', $id);
    $this->db->from('cert_status_type');
    $query = $this->db->get();
    $result = $query->row_array();

    return $result['cert_status_name'];
  }

  function get_cert_status() {
    $this->db->select('cert_status_type.cert_status_id as certId, cert_status_type.cert_status_name as certName');

    $this->db->from('cert_status_type');
    $this->db->order_by("cert_status_type.cert_order", "asc");

    $query = $this->db->get();
    $return = [];
    //$return[0] = "Select Country";
    foreach($query->result_array() as $row) {
      $return[$row["certId"]] = $row["certName"];
    }

    return $return;
  }

  function get_program_types() {

    $this->db->select('ProgramType.ProgramTypeId,ProgramType.ProgramTypeName');

    $this->db->join("dma_account_field", "dma_account_field.id = dma_account_field_program_type.dma_account_field_id", 'left');
    $this->db->join("ProgramType", "ProgramType.ProgramTypeId = dma_account_field_program_type.program_type_id", 'left');

    $this->db->where('dma_account_field.dma_account_id', $this->cisession->userdata('dmaId'));
    $this->db->where('dma_account_field_program_type.status', 'active');

    $this->db->from('dma_account_field_program_type');

    $this->db->order_by("ProgramType.ProgramTypeOrder", "ASC");
    $query = $this->db->get();

    $return = [];

    foreach($query->result() as $data) {
      $return[$data->ProgramTypeId] = $data->ProgramTypeName;
    }

    if(sizeof($return) == 0) {
      //get the default values from the database
      $return = $this->get_program_types_default();
    }

    return $return;

  }

  function get_program_types_default() {

    $this->db->select('ProgramTypeId,ProgramTypeName')->from('ProgramType');
    $dmaWhere = "(ProgramTypeDmaId IS NULL)";
    $this->db->where($dmaWhere);
    $this->db->order_by("ProgramTypeOrder", "ASC");
    $query = $this->db->get();

    foreach($query->result() as $data) {
      $return[$data->ProgramTypeId] = $data->ProgramTypeName;
    }

    return $return;

  }

  function deactivate_account_field_all_program_types() {

    $data = [
        'order'  => null,
        'status' => 'archived',
    ];
    $this->db->where('dma_account_field_id IN (SELECT id FROM dma_account_field WHERE dma_account_id = ' . $this->cisession->userdata('dmaId') . ' AND type="program_type")');
    $this->db->update('dma_account_field_program_type', $data);
  }

  function get_dma_account_field_options($type, $dmaId = 1) {
    $this->validate_dma_account_field_type($type);
    $types = $this->dmaAccountFieldTypes;

    $selectStr = "";
    $selectStr .= $types[$type]['tbl_name'] . '.' . $types[$type]['tbl_field_id'] . ' id, ';
    $selectStr .= $types[$type]['tbl_name'] . '.' . $types[$type]['tbl_field_name'] . ' name, ';
    $selectStr .= $types[$type]['tbl_name'] . '.' . $types[$type]['tbl_field_dma_id'] . ' dmaId, ';
    $selectStr .= 'dma_account_field_' . $type . '.order, ';
    $selectStr .= "'active' status";

    $this->db->select($selectStr);
    $this->db->join("dma_account_field", "dma_account_field.id = dma_account_field_" . $type . ".dma_account_field_id", 'left');
    $this->db->join($types[$type]['tbl_name'], $types[$type]['tbl_name'] . "." . $types[$type]['tbl_field_id'] . " = dma_account_field_" . $type . "." . $type . "_id", 'left');
    $this->db->where('dma_account_field.dma_account_id', $dmaId);
    $this->db->where('dma_account_field_' . $type . '.status', 'active');
    $this->db->from('dma_account_field_' . $type . '');
    $this->db->order_by("dma_account_field_" . $type . ".order", "ASC");
    $query = $this->db->get();

    return $query->result();
  }

  public function get_dma_account_field_options_key_val($type) {
    $results = [];
    $dmaId = $this->cisession->userdata('dmaId');
    if($this->isLockedChildDma()) {
      $dmaId = $this->cisession->userdata('parentDmaId');
    }
    $customOptions = $this->get_dma_account_field_options($type, $dmaId);
    if(empty($customOptions)) {
      $customOptions = $this->get_dma_account_field_options($type);
      if($this->isUnlockedChildDma()) {
        $parentDmaOptions = $this->get_dma_account_field_options($type, $this->cisession->userdata('parentDmaId'));
        if(!empty($parentDmaOptions)) {
          $customOptions = $parentDmaOptions;
        }
      }

    }
    foreach($customOptions as $customOption) {
      $results[$customOption->id] = $customOption->name;
    }

    return $results;
  }

  /**
   * This is used to determine if the passed dma ID, or current session dma is a child who is locked from over-riding customized selectors
   *
   * @param int|null $dmaId
   * @return bool
   */
  private function isLockedChildDma(int $dmaId = null): bool {
    if(!isset($dmaId)) {
      $dmaId = $this->cisession->userdata('dmaId');
    }
    $dma = $this->get_dma_account_by_id($dmaId);
    if(!$dma['parentDmaId']) {
      return false;
    }

    return $dma['can_override_parent_field'] != true;
  }

  /**
   * This is used to determine if the passed dma ID, or current session dma is a child who is unlocked from over-riding, and free to customized selectors
   *
   * @param int|null $dmaId
   * @return bool
   */
  private function isUnlockedChildDma(int $dmaId = null): bool {
    if(!isset($dmaId)) {
      $dmaId = $this->cisession->userdata('dmaId');
    }
    $dma = $this->get_dma_account_by_id($dmaId);
    if(!$dma['parentDmaId']) {
      return false;
    }

    return $dma['can_override_parent_field'] == true;
  }

  private function validate_dma_account_field_type($type) {
    if(!array_key_exists($type, $this->dmaAccountFieldTypes)) {
      throw new \Exception('Invalid DMA Account Field Type supplied.');
    }
  }

  private function get_dma_account_by_id($id) {

    $this->db->select('dmaAccounts.*, Accounts.userId, Accounts.firstName, Accounts.lastName, Accounts.email, Accounts.address1, Accounts.address2, Accounts.city, Accounts.state, Accounts.postalCode, Accounts.phone, Accounts.mobilePhone, dmaMembers.dmaMemberId, dmaMembers.dmaMGroupId');
    $this->db->where('dmaId', $id);
    $this->db->where('dmaMDmaId', $id);
    $this->db->join("Accounts", "dmaAccounts.primary_account_id = Accounts.userId", 'left');
    $this->db->join("dmaMembers", "dmaAccounts.primary_account_id = dmaMembers.dmaMUserId", 'left');
    $this->db->from('dmaAccounts');

    $query = $this->db->get();

    $return = $query->result_array();

    if(is_null($return[0]['profileUrl'])) {
      $return[0]['profileUrl'] = 'online-incentives-exchange';
    }

    if(sizeof($return) > 0) {
      return $return[0];
    }

  }

  function deactivate_all_dma_fields($type) {
    $this->validate_dma_account_field_type($type);
    $data = [
        'order'  => null,
        'status' => 'archived',
    ];
    $this->db->where('dma_account_field_id IN (SELECT id FROM dma_account_field WHERE dma_account_id = ' . $this->cisession->userdata('dmaId') . ' AND type="' . $type . '")');
    $this->db->update('dma_account_field_' . $type, $data);
  }

  function activate_dma_account_field($type, $id, $order) {
    $this->validate_dma_account_field_type($type);
    $types = $this->dmaAccountFieldTypes;

    // Check if audit status already exist or not.
    $this->db->select('id');
    $this->db->from('dma_account_field_' . $type);
    $this->db->where("dma_account_field_id IN (SELECT id FROM dma_account_field WHERE dma_account_id=" . $this->cisession->userdata('dmaId') . ")");
    $this->db->where($type . "_id", $id);
    $query = $this->db->get();

    $date = date("Y-m-d H:i:s");
    if(count($query->result())) {
      $account_field_id = $query->first_row()->id;

      $data = [
          'order'        => $order,
          'status'       => 'active',
          'updated_date' => $date,
      ];
      $this->db->where("id", $account_field_id);
      $this->db->update('dma_account_field_' . $type, $data);
    } else {
      // If audit status option does not exist,
      // 1. add row into dma_account_field table,
      // 2. add option into dma_account_field_audit_status table

      $data = [
          'dma_account_id' => $this->cisession->userdata('dmaId'),
          'type'           => $type,
      ];
      $this->db->insert('dma_account_field', $data);

      $account_field_id = $this->db->insert_id();

      $data = [
          'dma_account_field_id' => $account_field_id,
          $type . '_id'          => $id,
          'order'                => $order,
          'status'               => 'active',
          'created_date'         => $date,
          'updated_date'         => $date,
      ];
      $this->db->insert('dma_account_field_' . $type, $data);
    }
  }

  function get_dma_account_field_id($type, $name) {
    $this->validate_dma_account_field_type($type);
    $types = $this->dmaAccountFieldTypes;

    $this->db->select($types[$type]['tbl_field_id'] . ' id');
    $this->db->from($types[$type]['tbl_name']);
    $this->db->where("BINARY(" . $types[$type]['tbl_field_name'] . ") = '" . $this->db->escape_str($name) . "'");
    $query = $this->db->get();

    if(!empty($query->first_row()->id)) {
      return $query->first_row()->id;
    } else {
      return 0;
    }
  }

  function add_dma_account_field($type, $name) {
    $this->validate_dma_account_field_type($type);
    $types = $this->dmaAccountFieldTypes;

    $data = [
        $types[$type]['tbl_field_name']   => $name,
        $types[$type]['tbl_field_dma_id'] => $this->cisession->userdata('dmaId'),
    ];
    $this->db->insert($types[$type]['tbl_name'], $data);

    return $this->db->insert_id();
  }

  /* This is not finished - countryId is wrong and data doesn't connect
    function get_program_types_by_jurisdiction($programId){

        $this->db->select('ProgramTypeId,ProgramTypeName');
        $this->db->from ( 'ProgramType' );
     if(!empty($programId)){
          $this->db->where('countryId', $programId);
        }
        $query = $this->db->get();
    $return[0] = "Select Credit Type";
        foreach($query->result() as $data) {
            $return[$data->ProgramTypeId] = $data->ProgramTypeName;
        }
        return $return;

    }
    */

  function list_taxCredirProduct() {
    $this->db->select('ProgramTypeName');

    $this->db->from('ProgramType');
    //$this->db->order_by("countries.sOrder","asc");

    $query = $this->db->get();
    $return = [];
    $return[0] = "";

    foreach($query->result_array() as $row) {
      $return[] = $row["ProgramTypeName"];
    }

    return $return;
  }

  function get_only_states_by_country($id) {
    $this->db->select('States.name, States.state, States.jDmaId');
    $this->db->distinct();
    if(!empty($id)) {
      $this->db->where('countryId', $id);
    }
    if(!empty($dmaId)) {
      $this->db->where('jDmaId', $dmaId);
    }
    $this->db->from('States');
    $this->db->order_by("States.sOrder", "asc");
    $query = $this->db->get();
    // $return[]="Select Jurisdiction";
    foreach($query->result() as $data) {

      //Only return programs if they are Status 1 or if they are "other" program created by their DMA Account
      if($data->jDmaId == "" || $data->jDmaId == $this->cisession->userdata('dmaId')) {
        $return[$data->state] = $data->name;
      }

    }

    return $return;
  }

  function get_only_states_by_country_style($id) {
    $this->db->select('States.name, States.state');
    $this->db->distinct();
    $this->db->where('countryId', $id);
    $this->db->from('States');
    $this->db->order_by("States.sOrder", "asc");
    $query = $this->db->get();
    foreach($query->result() as $data) {
      $return[$data->state] = $data->name;
    }

    return $return;
  }

  function get_categoryName_by_id($id) {
    $this->db->select('IPCategories.id,IPCategories.category');
    $this->db->where('id', $id);
    $this->db->from('IPCategories');
    $query = $this->db->get();
    $row = $query->row();
    if(sizeof($row) > 0) {
      return $row->category;
    }
  }

  function get_advance_search($jurisdiction, $industrySector, $categories, $taxOffset, $international, $usFederal, $usState, $refundable, $transferable) {

    $this->db->select('IncentivePrograms.State,IncentivePrograms.Category, OIXIncentiveId,ProgramName, IPSectors.sector, States.name, OffsettingTaxList,(CASE WHEN Transferable=1 THEN "Transferable" ELSE "Refundable" END ) as ProgramType');
    //$this->db->select('IncentivePrograms.State,IncentivePrograms.Category, OIXIncentiveId,ProgramName, IPSectors.sector, States.name, OffsettingTaxList,IncentivePrograms.Transferable as ProgramType');

    /** Transferebale and refundable **/
    if($transferable != 1 && $refundable != 2) {

      if(!empty($transferable) && $transferable != "-") {
        $this->db->where('IncentivePrograms.Transferable', strtoupper($transferable));
      }

      if(!empty($refundable) && $refundable != "-") {
        $this->db->where('IncentivePrograms.Transferable', strtoupper($refundable));
      }
    } else {
      if($transferable == 1 && $refundable != 2) {
        if(!empty($transferable) && $transferable != "-") {
          $this->db->where('IncentivePrograms.Transferable', strtoupper($transferable));
        }
      } else {
        if($transferable != 1 && $refundable == 2) {
          if(!empty($refundable) && $refundable != "-") {
            $this->db->where('IncentivePrograms.Transferable', strtoupper($refundable));
          }
        }
      }
    }

    /** US States and US Federal **/
    if($usState != 1 && $usFederal != 1) {

    } else {
      if($usState == 1 && $usFederal != 1) {
        if(!empty($transferable) && $transferable != "-") {
          $this->db->where('IncentivePrograms.State !=', strtoupper("FED"));
        }
      } else {
        if($usState != 1 && $usFederal == 1) {
          if(!empty($usState) && $usFederal != "-") {
            $this->db->where('IncentivePrograms.State', strtoupper("FED"));
          }
        }
      }
    }

    if(!empty($jurisdiction) && $jurisdiction != "-") {
      $this->db->where('IncentivePrograms.State', strtoupper($jurisdiction));
    }
    if(!empty($industrySector) && $industrySector != "-") {
      $this->db->where('IncentivePrograms.Sector', $industrySector);
    }
    if(!empty($taxOffset) && $taxOffset != "-") {
      $this->db->like('OffsettingTaxList', $taxOffset);

    }
    if(!empty($categories) && $categories != "-") {
      $this->db->where('IncentivePrograms.Category =', $categories);

    }

    //$this->db->where('IncentivePrograms.State', $id);
    $this->db->where('IncentivePrograms.Status', '1');
    $this->db->from('IncentivePrograms');
    $this->db->order_by("IncentivePrograms.State", "ascending");
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("IPSectors", "IncentivePrograms.sector = IPSectors.id", 'left');
    $query = $this->db->get();
    //return $query->result();
    foreach($query->result() as $data) {
      $data->OffsettingTaxList = $this->get_offsets_from_program($data->OffsettingTaxList);
      $data->Category = $this->get_categoryName_by_id($data->Category);
    }

    return $query->result();

  }

  function get_active_programs_by_state_short_code($id) {
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer');
    $this->db->where('IncentivePrograms.State', $id);
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->where('IncentivePrograms.status !=', 5);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");
    $query = $this->db->get();
    $return = [];
    $return[0] = "Select Program";
    foreach($query->result_array() as $row) {
      $return[$row["OIXIncentiveId"]] = $row["ProgramName"];
    }
    $return['other'] = "Other";

    return $return;
  }

  function get_active_programs_by_state_short_code_modify($id) {
    $this->db->select('OIXIncentiveId,ProgramName,multitransfer');
    $this->db->where('IncentivePrograms.State', $id);
    $this->db->where('IncentivePrograms.status !=', 0);
    //$this->db->where('IncentivePrograms.status !=', 5);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");
    $query = $this->db->get();
    $return = [];
    $return[0] = "Select Program";
    foreach($query->result_array() as $row) {
      $return[$row["OIXIncentiveId"]] = $row["ProgramName"];
    }
    $return['other'] = "Other";

    return $return;
  }

  function get_active_programs_by_OIXIncentiveId($id) {
    $this->db->select('OIXIncentiveId,ProgramName');
    $this->db->where('IncentivePrograms.OIXIncentiveId', $id);
    $this->db->where('IncentivePrograms.status !=', 0);
    $this->db->where('IncentivePrograms.status !=', 5);
    $this->db->from('IncentivePrograms');
    $this->db->order_by("ProgramName", "ascending");
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $row) {
      $return[$row["OIXIncentiveId"]] = $row["ProgramName"];
    }

    return $return;
  }

  function getTaxOffsetNameById($id) {
    $this->db->select('offsetName');
    $this->db->where('id', $id);
    $this->db->from('Offsets');
    $query = $this->db->get();
    $result = $query->row_array();

    return $result['offsetName'];
  }

  function get_us_state_short() {
    $this->db->select("*");
    $this->db->where('States.stateTypeCode', 'S');
    $this->db->from("States");
    $this->db->order_by("States.state", "asc");
    $query = $this->db->get();
    $data['state'] = "";
    $data['state']['default'] = 'required';
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->state;
    }

    return $data['state'];
  }

  function get_all_state_short() {
    $this->db->select("*");
    $this->db->where("States.stateTypeCode != 'S'");
    $this->db->from("States");
    $this->db->order_by("States.state", "asc");
    $query = $this->db->get();
    $data['state'] = "";
    foreach($query->result() as $state_key) {
      $data['state'][$state_key->state] = $state_key->state;
    }

    return $data['state'];
  }

  /*
  * @desc Get Audit Status
  * @return  array
  */
  function get_audit_status() {
    $this->db->select('statusId,auditStatus');
    if($this->cisession->userdata('parentDmaId') > 0) {
      $where = "(audit_status.auditOIXoption=1 OR audit_status.auditCustomDmaId=" . $this->cisession->userdata('dmaId') . " OR audit_status.auditCustomParentDmaId=" . $this->cisession->userdata('parentDmaId') . ")";
    } else {
      if($this->cisession->userdata('isParentDma')) {
        $where = "(audit_status.auditOIXoption=1 OR audit_status.auditCustomDmaId=" . $this->cisession->userdata('dmaId') . " OR audit_status.auditCustomParentDmaId=" . $this->cisession->userdata('dmaId') . ")";
      } else {
        if($this->cisession->userdata('dmaId') > 0) {
          $where = "(audit_status.auditOIXoption=1 OR audit_status.auditCustomDmaId=" . $this->cisession->userdata('dmaId') . ")";
        } else {
          $where = "(audit_status.auditOIXoption=1)";
        }
      }
    }
    $this->db->where($where);
    $this->db->from('audit_status');
    $this->db->order_by("audit_status.auditOrder", "asc");
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $row) {
      $return[$row["statusId"]] = $row["auditStatus"];
    }

    return $return;
  }

  /*
   * @desc Get Project Status
   * @return  array
   */
  function get_project_status() {
    $this->db->select('statusId,projectStatus');
    $this->db->from('project_status');
    $query = $this->db->get();
    $return = [];
    $return[0] = "Select Project Status";
    foreach($query->result_array() as $row) {
      $return[$row["statusId"]] = $row["projectStatus"];
    }

    return $return;
  }

  function monetization_status() {
    $this->db->select('mnsId, mnsName, mnsOrder');
    $this->db->from('monetization_status');
    $this->db->order_by("mnsOrder", "ascending");
    $query = $this->db->get();
    $return = [];
    foreach($query->result_array() as $row) {
      $return[$row["mnsId"]] = $row["mnsName"];
    }

    return $return;
  }

  function get_categories_for_notifications() {

    $this->db->select('IPCategories.*');
    $this->db->where('IPCategories.forNotifications', 1);
    $this->db->from('IPCategories');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_all_us_states() {

    $this->db->select('States.id, States.name, States.state');
    $this->db->from('States');
    $this->db->where('States.CountryId', 1);
    $this->db->where('States.stateTypeCode', 'S');
    $this->db->order_by('SOrder');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_all_us_states_placeId() {

    $this->db->select('States.id, States.name, States.state, States.googlePlaceId');
    $this->db->from('States');
    $this->db->where('States.CountryId', 1);
    $this->db->where('States.stateTypeCode', 'S');
    $this->db->order_by('SOrder');
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      $return[$data['name']] = $data['googlePlaceId'];
    }

    return $return;
  }

  function get_state_detail_by_name($stateName) {

    $this->db->from('States');
    $this->db->where('States.name', $stateName);
    $query = $this->db->get();

    return $query->row_array();
  }

  function get_insight_docs($category = "") {

    $this->db->select('*');
    $this->db->from('insightDocs');
    if($category != "") {
      $this->db->where('insightDocs.insCategory', $category);
    }
    $this->db->order_by("insightDocs.insOrder DESC");

    $return = [];
    $query = $this->db->get();

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    return $return;

  }

  function get_notifications_settings_for_user($userId) {

    $this->db->select('notifications_settings.*');
    $this->db->from('notifications_settings');
    $this->db->where('notifications_settings.nsUserId', $userId);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) < 1) {
      return "";
    } else {
      return $return[0];
    }

  }

  function get_notifications_id_for_user($userId) {

    $this->db->select('notifications_settings.nsId');
    $this->db->from('notifications_settings');
    $this->db->where('notifications_settings.nsUserId', $userId);
    $query = $this->db->get();

    $return = [];

    foreach($query->result_array() as $data) {
      array_push($return, $data);
    }

    if(sizeof($return) < 1) {
      return "";
    } else {
      return $return[0];
    }

  }

  function insert_notifications_settings_for_user($userId, $nsStatesIds, $nsStatesCodes, $nsIPCategories, $nsActivity, $nsCreditType, $nsFrequency, $nsMinimum, $nsMinimumAmount, $nsDailyAlertSummary) {

    $data = [
        'nsUserId'            => $userId,
        'nsStatesIds'         => $nsStatesIds,
        'nsStatesCodes'       => $nsStatesCodes,
        'nsIPCategories'      => $nsIPCategories,
        'nsActivity'          => $nsActivity,
        'nsCreditType'        => $nsCreditType,
        'nsFrequency'         => $nsFrequency,
        'nsMinimum'           => $nsMinimum,
        'nsMinimumAmount'     => $nsMinimumAmount,
        'nsDailyAlertSummary' => $nsDailyAlertSummary,
        'nsCreated'           => time(),
    ];

    $this->db->insert('notifications_settings', $data);

    return $this->db->insert_id();

  }

  function update_notifications_settings_by_id($nsId, $nsStatesIds, $nsStatesCodes, $nsIPCategories, $nsActivity, $nsCreditType, $nsFrequency, $nsMinimum, $nsMinimumAmount, $nsDailyAlertSummary) {
    $data = [
        'nsStatesIds'         => $nsStatesIds,
        'nsStatesCodes'       => $nsStatesCodes,
        'nsIPCategories'      => $nsIPCategories,
        'nsActivity'          => $nsActivity,
        'nsCreditType'        => $nsCreditType,
        'nsFrequency'         => $nsFrequency,
        'nsMinimum'           => $nsMinimum,
        'nsMinimumAmount'     => $nsMinimumAmount,
        'nsDailyAlertSummary' => $nsDailyAlertSummary,
        'nsUpdated'           => time(),
    ];

    $this->db->where('notifications_settings.nsId', $nsId);
    $this->db->update('notifications_settings', $data);

    return true;
  }

  function search_programs($country = "", $jurisdiction = "", $category = "", $taxOffset = "", $typeSearch = "", $order = "") {

    $this->db->select('IncentivePrograms.State,IncentivePrograms.cchApplicationProvisions,IncentivePrograms.IncentiveSummary,IncentivePrograms.SunsetDate,IncentivePrograms.IncentiveDetail,IncentivePrograms.Category, OIXIncentiveId,ProgramName, IncentivePrograms.Status, IPSectors.sector, States.name, IncentivePrograms.Transferable, IncentivePrograms.Refundable, IncentivePrograms.allocated, OffsettingTaxList,(CASE WHEN Transferable=1 THEN "Transferable" ELSE "Refundable" END ) as ProgramType, ipdpCountry, ProgramType.ProgramTypeName');

    /** Transferable and Refundable **/
    if($typeSearch == 2) {
      $this->db->where('IncentivePrograms.Transferable', 1);
    }
    if($typeSearch == 3) {
      $this->db->where('IncentivePrograms.Refundable', 1);
    }
    if($typeSearch == 4) {
      $this->db->where('IncentivePrograms.Allocated', 1);
    }

    /** JURISDICTIONS **/
    if(!empty($jurisdiction) && $jurisdiction != "all") {
      $this->db->where('IncentivePrograms.State', strtoupper($jurisdiction));
    } else {
      /** COUNTRY **/
      $this->db->where('IncentivePrograms.ipdpCountry', $country);
    }

    /** OFFSETS **/
    if(!empty($taxOffset) && $taxOffset != "all") {
      $this->db->where('FIND_IN_SET(' . $taxOffset . ', OffsettingTaxList)>0');
    }

    /** CATEGORIES **/
    if(!empty($category) && $category != "all") {
      $this->db->where('IncentivePrograms.Category =', $category);

    }

    if($this->cisession->userdata('dmaId') > 0) {
      $this->db->where('(IncentivePrograms.Status', '1');
      $this->db->or_where('IncentivePrograms.pDmaId = ' . $this->cisession->userdata('dmaId') . ')');
    } else {
      $this->db->where('IncentivePrograms.Status', '1');
    }
    $this->db->from('IncentivePrograms');
    $this->db->join("States", "IncentivePrograms.State = States.state", 'left');
    $this->db->join("IPSectors", "IncentivePrograms.sector = IPSectors.id", 'left');
    $this->db->join("ProgramType", "IncentivePrograms.creditTypeId = ProgramType.ProgramTypeId", 'left');

    /** ORDER **/
    $order = "name ASC, ProgramName ASC";
    $this->db->order_by($order);

    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {
      $data['OffsettingTaxList'] = $this->get_offsets_from_program($data['OffsettingTaxList']);
      $data['Category'] = $this->get_categoryName_by_id($data['Category']);

      array_push($return, $data);
    }

    return $return;

  }

  function get_government_agencies($jurisdiction = "", $categorized = "") {

    $this->db->select('govAgencies.*, States.state, States.name');

    /** JURISDICTIONS **/
    if(!empty($jurisdiction) && $jurisdiction != "-") {
      $this->db->where('States.state', strtoupper($jurisdiction));
    }

    $this->db->from('govAgencies');
    $this->db->join("States", "govAgencies.saStateId = States.id", 'left');
    $this->db->order_by("govAgencies.saStateId", "ascending");
    $query = $this->db->get();

    $return = [];

    if($categorized == 'byJurisdiction') {

      foreach($query->result_array() as $data) {
        if(!array_key_exists($data['state'], $return)) {
          $return[$data['state']] = [];
          $return[$data['state']]['state'] = $data['state'];
          $return[$data['state']]['name'] = $data['name'];
          $return[$data['state']]['agencies'] = [];
        }
        $return[$data['state']]['agencies'][$data['id']]['saIcon'] = $data['saIcon'];
        $return[$data['state']]['agencies'][$data['id']]['saTitle'] = $data['saTitle'];
        $return[$data['state']]['agencies'][$data['id']]['saUrl'] = $data['saUrl'];
      }

    } else {

      foreach($query->result_array() as $data) {
        array_push($return, $data);
      }

    }

    return $return;

  }

  // Get the program html document by using CV handle and CV link.
  function get_program_doc_by_cv($cv_handle, $cv_link) {
    $this->db->select('iplId, DisplayText, DocId, DocTitle, DocContent, LastUpdated');
    $this->db->where('IncentiveProgramLinkData.CVHandle', $cv_handle);
    $this->db->where('IncentiveProgramLinkData.CVLink', $cv_link);
    $this->db->from('IncentiveProgramLinkData');
    $this->db->order_by("iplId", "ASC");
    $query = $this->db->get();

    return $query->result();
  }

  // Get the program html document by using document id.
  function get_program_doc_by_doc_id($doc_id) {
    $this->db->select('iplId, DisplayText, DocId, DocTitle, DocContent, LastUpdated');
    $this->db->where('IncentiveProgramLinkData.iplId', $doc_id);
    $this->db->from('IncentiveProgramLinkData');
    $this->db->order_by("iplId", "ASC");
    $query = $this->db->get();

    return $query->result();
  }

  // Get the all tax years
  function get_all_tax_years() {
    $this->db->select('id, taxYear');
    $this->db->from('TaxYear');
    $this->db->order_by("taxYear", "ASC");
    $query = $this->db->get();

    return $query->result();
  }

  // Get active incentive programs ordered by program name using state
  function get_active_programs_by_state_ordered_name($state) {
    $this->db->select('OIXIncentiveId, ProgramName');
    $this->db->from('IncentivePrograms');
    $where = "IncentivePrograms.State = '$state' AND (IncentivePrograms.Status = 1 OR IncentivePrograms.pDmaId = " . $this->cisession->userdata('dmaId') . ")";
    $this->db->where($where);
    $this->db->order_by("ProgramName", "ascending");
    $query = $this->db->get();

    return $query->result();
  }

  // Remove sectors of incentive program
  function remove_ip_sectors_from_program($OIXIncentiveId) {
    $this->db->where('ipsrIncentiveId', $OIXIncentiveId);
    $this->db->delete('IPSectorsRel');
  }

  // Add incentive program sector
  function add_ip_sector($sectorId, $OIXIncentiveId) {
    $this->db->insert('IPSectorsRel',
                      [
                          'ipsrSectorId'    => $sectorId,
                          'ipsrIncentiveId' => $OIXIncentiveId,
                      ]);
  }

  // Add all incentive program sectors
  function add_ip_sectors_all($OIXIncentiveId) {
    $this->db->select('id');
    $query = $this->db->get('IPSectors');
    $sectors = $query->result();

    foreach($sectors as $sector) {
      $this->db->insert('IPSectorsRel',
                        [
                            'ipsrSectorId'    => $sector->id,
                            'ipsrIncentiveId' => $OIXIncentiveId,
                        ]);
    }
  }

  // Remove categories of incentive program
  function remove_ip_categories_from_program($OIXIncentiveId) {
    $this->db->where('ipcrIncentiveId', $OIXIncentiveId);
    $this->db->delete('IPCategoriesRel');
  }

  // Add incentive program category
  function add_ip_category($categoryId, $OIXIncentiveId) {
    $this->db->insert('IPCategoriesRel',
                      [
                          'ipcrCategoryId'  => $categoryId,
                          'ipcrIncentiveId' => $OIXIncentiveId,
                      ]);
  }

  // Add all incentive program categories
  function add_ip_categories_all($OIXIncentiveId) {
    $this->db->select('id');
    $query = $this->db->get('IPCategories');
    $categories = $query->result();

    foreach($categories as $category) {
      $this->db->insert('IPCategoriesRel',
                        [
                            'ipcrCategoryId'  => $category->id,
                            'ipcrIncentiveId' => $OIXIncentiveId,
                        ]);
    }
  }

  // Remove tax types of incentive program
  function remove_ip_tax_types_from_program($OIXIncentiveId) {
    $this->db->where('iptrIncentiveId', $OIXIncentiveId);
    $this->db->delete('IPTaxTypesRel');
  }

  // Add incentive program tax type
  function add_ip_tax_type($taxTypeId, $OIXIncentiveId) {
    $this->db->insert('IPTaxTypesRel',
                      [
                          'iptrTaxTypeId'   => $taxTypeId,
                          'iptrIncentiveId' => $OIXIncentiveId,
                      ]);
  }

  // Add all incentive program tax types
  function add_ip_tax_types_all($OIXIncentiveId) {
    $this->db->select('id');
    $query = $this->db->get('IPTaxTypes');
    $taxtypes = $query->result();

    foreach($taxtypes as $tt) {
      $this->db->insert('IPTaxTypesRel',
                        [
                            'iptrTaxTypeId'   => $tt->id,
                            'iptrIncentiveId' => $OIXIncentiveId,
                        ]);
    }
  }

}

/* End of file IncentivePrograms.php */
/* Location: ./application/models/IncentivePrograms.php */
