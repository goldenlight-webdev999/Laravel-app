<?php

Class Smartcharts_Model extends CI_Model {

  function __construct() {
    parent::__construct();

    $ci =& get_instance();
    $this->load->model('DmaAccounts');
    $this->load->library(['session']);
  }

  /* Store Industry value to 'IPSectors' table.
   * Insert as new record only when value is not exist.
   *
   * Return: id on the 'IPSectors' table.
   * */
  public function store_industry($industry) {
    // Check whether Industry value already exist or not.
    $query = $this->db->get_where('IPSectors', ['sector' => $industry]);
    $result = $query->row_array();

    if(count($result)) {
      $industry_id = $result['id'];
    } else {
      $this->db->insert('IPSectors',
                        [
                            'sector' => $industry,
                        ]);
      $industry_id = $this->db->insert_id();
    }

    return $industry_id;
  }

  /* Store Incentive Category value to 'IPCategories' table.
   * If value is exist, only update 'forSearch' flag as '1'.
   * If not exist, insert as new record.
   *
   * Return: id on the 'IPCategories' table.
   * */
  public function store_incentive_category($incentive_category) {
    // Check whether incentive category already exist or not.
    $query = $this->db->get_where('IPCategories', ['category' => $incentive_category]);
    $result = $query->row_array();

    if(count($result)) {
      $category_id = $result['id'];
      $this->db->where('category', $incentive_category);
      $this->db->update('IPCategories', ['forSearch' => 1]);
    } else {
      $this->db->insert('IPCategories',
                        [
                            'category'  => $incentive_category,
                            'forSearch' => 1,
                        ]);
      $category_id = $this->db->insert_id();
    }

    return $category_id;
  }

  /* Store Incentive Tax Type value to 'IPTaxTypes' table.
   * Insert as new tax type only when value is not exist.
   *
   * Return: id on the 'IPTaxTypes' table.
   * */
  public function store_incentive_tax_type($tax_type) {
    // Check whether tax type already exist or not.
    $query = $this->db->get_where('IPTaxTypes', ['tax_type' => (string)$tax_type]);
    $result = $query->row_array();

    if(count($result)) {
      $tax_type_id = $result['id'];
    } else {
      $this->db->insert('IPTaxTypes',
                        [
                            'tax_type' => (string)$tax_type,
                        ]);
      $tax_type_id = $this->db->insert_id();
    }

    return $tax_type_id;
  }

  /* Store Incentive data to 'IncentivePrograms' table.
   * If IncentiveProgram data exist, update it. Check using the combination of ProgramName and Jurisdiction code (State)
   * Track the all changes for field values before update.
   * If not exist, insert as new record.
   *
   * Return: IncentiveID, ProgramName and the array of changes for field values
   * */
  public function store_incentive_data($incentive_data) {
    $ret = [
        'OIXIncentiveId' => 0,      // OIXIncentiveId of Update/Inserted row
        'State'          => $incentive_data['State'],
        'ProgramName'    => $incentive_data['ProgramName'],
        'Updates'        => [],       // Array of all changes (field name, old value, new value)
        'IsNew'          => false            // Indicates whether row is new or not.
    ];

    $query = $this->db->get_where('IncentivePrograms',
                                  [
                                      'ProgramName' => $incentive_data['ProgramName'],
                                      'State'       => $incentive_data['State'],
                                  ]);
    $result = $query->row_array();

    if(count($result)) {
      // Compare new data and current data and get the all changes for  all field values
      foreach($incentive_data as $key => $value) {
        if($key == 'cchRecordKey') {
          continue;
        }       // Don't check 'cchRecordKey' field
        if($value != $result[$key]) {
          $change = [
              'FieldName' => $key,
              'OldValue'  => $result[$key],
              'NewValue'  => $value,
          ];
          array_push($ret['Updates'], $change);
        }
      }

      $ret['OIXIncentiveId'] = $result['OIXIncentiveId'];
      $this->db->where(
          [
              'ProgramName' => $incentive_data['ProgramName'],
              'State'       => $incentive_data['State'],
          ]);
      $this->db->update('IncentivePrograms', $incentive_data);
    } else {
      $this->db->insert('IncentivePrograms', $incentive_data);
      $ret['OIXIncentiveId'] = $this->db->insert_id();
      $ret['IsNew'] = true;
    }

    return $ret;
  }

  /* Store Jurisdiction data to 'States' table.
   * If already exist, update cchID column as 'jurisdiction_cch_id'.
   *
   * Return: Jurisdiction code (state column on the 'States' table)
   * */
  public function store_jurisdiction_data($jurisdiction_cch_id, $jurisdiction_val) {
    // Check whether jurisdiction already exist or not.
    $query = $this->db->get_where('States', ['name' => $jurisdiction_val]);
    $result = $query->row_array();

    if(count($result)) {
      $jurisdiction_code = $result['state'];
      $this->db->where('name', $jurisdiction_val);
      $this->db->update('States', ['cchId' => $jurisdiction_cch_id]);
    } else {
      $jurisdiction_code = null;
    }

    return $jurisdiction_code;
  }

  /* Store CategoryID, IncentiveProgramID value to 'IPCategoriesRel' table.
   * Insert as new record only when the combination of both not exist.
   *
   * Return: id on the 'IPCategoriesRel' table.
   * */
  public function store_category_incentive($category_id, $incentive_id) {
    // Check whether data already exist or not.
    $query = $this->db->get_where('IPCategoriesRel',
                                  [
                                      'ipcrCategoryId'  => $category_id,
                                      'ipcrIncentiveId' => $incentive_id,
                                  ]);
    $result = $query->row_array();

    if(count($result)) {
      $ipcr_id = $result['ipcrId'];
    } else {
      $this->db->insert('IPCategoriesRel',
                        [
                            'ipcrCategoryId'  => $category_id,
                            'ipcrIncentiveId' => $incentive_id,
                        ]);
      $ipcr_id = $this->db->insert_id();
    }

    return $ipcr_id;
  }

  /* Store SectorID, IncentiveProgramID value to 'IPSectorsRel' table.
   * Insert as new record only when the combination of both not exist.
   *
   * Return: id on the 'IPSectorsRel' table.
   * */
  public function store_sector_incentive($sector_id, $incentive_id) {
    // Check whether data already exist or not.
    $query = $this->db->get_where('IPSectorsRel',
                                  [
                                      'ipsrSectorId'    => $sector_id,
                                      'ipsrIncentiveId' => $incentive_id,
                                  ]);
    $result = $query->row_array();

    if(count($result)) {
      $ipsr_id = $result['ipsrId'];
    } else {
      $this->db->insert('IPSectorsRel',
                        [
                            'ipsrSectorId'    => $sector_id,
                            'ipsrIncentiveId' => $incentive_id,
                        ]);
      $ipsr_id = $this->db->insert_id();
    }

    return $ipsr_id;
  }

  /* Store TaxTypeId, IncentiveProgramID value to 'IPTaxTypesRel' table.
   * Insert a new record only when the combination of both not exist.
   *
   * Return: id on the 'IPTaxTypesRel' table.
   * */
  public function store_tax_type_incentive($tax_type_id, $incentive_id) {
    // Check whether data already exist or not.
    $query = $this->db->get_where('IPTaxTypesRel',
                                  [
                                      'iptrTaxTypeId'   => $tax_type_id,
                                      'iptrIncentiveId' => $incentive_id,
                                  ]);
    $result = $query->row_array();

    if(count($result)) {
      $iptr_id = $result['iptrId'];
    } else {
      $this->db->insert('IPTaxTypesRel',
                        [
                            'iptrTaxTypeId'   => $tax_type_id,
                            'iptrIncentiveId' => $incentive_id,
                        ]);
      $iptr_id = $this->db->insert_id();
    }

    return $iptr_id;
  }

  /* Store incentive program data changes for field values to 'IncentiveProgramChanges' table.
   *
   * Return: Number of changes stored successfully
   * */
  public function store_incentive_changes($incentive_data) {
    $date = date("Y-m-d H:i:s");
    $num_changes = 0;

    foreach($incentive_data['Updates'] as $item) {
      $this->db->insert('IncentiveProgramChanges',
                        [
                            'ipcIncentiveId' => $incentive_data['OIXIncentiveId'],
                            'FieldName'      => $item['FieldName'],
                            'OldValue'       => $item['OldValue'],
                            'NewValue'       => $item['NewValue'],
                            'UpdatedDate'    => $date,
                        ]);
      $num_changes++;
    }

    return $num_changes;
  }

  /* Get the sector list from 'IPSectors' table.
   * Return: Sectors for specified IDs as parameter, All sectors if IDs are not specified.
   * */
  public function get_sector_list($sector_id_list = null) {
    if(!empty($sector_id_list)) {
      $this->db->where_in('id', $sector_id_list);
    }
    $this->db->order_by('sector', 'ASC');
    $query = $this->db->get('IPSectors');

    return $query->result();
  }

  /* Get the category list from 'IPCategories' table.
   * Return: Categories for specified IDs as parameter, All categories if IDs are not specified.
   * */
  public function get_category_list($category_id_list = null) {
    if(!empty($category_id_list)) {
      $this->db->where_in('id', $category_id_list);
    }
    $this->db->order_by('category', 'ASC');
    $query = $this->db->get('IPCategories');

    return $query->result();
  }

  /* Get the Geo Restriction list from 'IPGeoRestrictions' table.
   * Return: Restrictions for specified IDs as parameter, All Restrictions if IDs are not specified.
   * */
  public function get_georestriction_list($georestriction_id_list = null) {
    if(!empty($georestriction_id_list)) {
      $this->db->where_in('id', $georestriction_id_list);
    }
    $this->db->order_by('georestriction', 'ASC');
    $query = $this->db->get('IPGeoRestrictions');

    return $query->result();
  }

  /* Get the Geo Restriction list from 'IPGeoRestrictions' table.
   * Return: Restrictions for specified IDs as parameter, All Restrictions if IDs are not specified.
   * */
  public function get_taxtype_list($taxtype_id_list = null) {
    if(!empty($taxtype_id_list)) {
      $this->db->where_in('id', $taxtype_id_list);
    }
    $this->db->order_by('tax_type', 'ASC');
    $query = $this->db->get('IPTaxTypes');

    return $query->result();
  }

  /* Returns the country list from 'countries' table.
   * */
  public function get_region_list() {
    $this->db->order_by('sOrder', 'ASC');
    $this->db->where('sOrder >', 0);
    $this->db->where('cDmaId', null);
    $query = $this->db->get('countries');

    return $query->result();
  }

  /* Returns the country list from 'countries' table.
 * */
  public function get_region_by_code($code) {
    $this->db->order_by('sOrder', 'ASC');
    $this->db->where('code', $code);
    $query = $this->db->get('countries');

    return $query->result();
  }

  /*
   * Returns the country/state/county/town list where a generic incentive program attached or
   * a custom program attached to DmaId/ParentDmaId/ChildDmaId.
   */
  public function get_jurisdiction_list($jx_type) {
    $filterDmaIdList = [];

    $dmaId = $this->cisession->userdata('dmaId');
    array_push($filterDmaIdList, $dmaId);

    $dmaParentId = $this->cisession->userdata('parentDmaId');
    if (!empty($dmaParentId)) {
      array_push($filterDmaIdList, $dmaParentId);
    }

    if ($this->cisession->userdata('isParentDma')) {
      $childAccounts = $this->DmaAccounts->get_child_accounts_of_dma_account(1);
      foreach ($childAccounts as $ca) {
        array_push($filterDmaIdList, $ca['dmaId']);
      }
    }

    // Get the jurisdiction list
    $location_tbl = "";
    if($jx_type === "country") {
      $location_tbl = "location_country";
      $this->db->select("location_country.*");
      $this->db->join("jurisdiction", "jurisdiction.country_id = location_country.id");
    } else if($jx_type === "province") {
      $location_tbl = "location_province";
      $this->db->select("location_province.*");
      $this->db->join("jurisdiction", "jurisdiction.province_id = location_province.id");
    } else if($jx_type === "county") {
      $location_tbl = "location_county";
      $this->db->select("location_county.*");
      $this->db->join("jurisdiction", "jurisdiction.county_id = location_county.id");
    } else if($jx_type === "town") {
      $location_tbl = "location_town";
      $this->db->select("location_town.*");
      $this->db->join("jurisdiction", "jurisdiction.town_id = location_town.id");
    }
    $this->db->join("IncentivePrograms", "IncentivePrograms.jurisdiction_id = jurisdiction.id");
    $this->db->where('IncentivePrograms.pDmaId', null);
    $this->db->or_where_in('IncentivePrograms.pDmaId', $filterDmaIdList);
    $this->db->group_by($location_tbl . '.id');
    $this->db->order_by($location_tbl . '.name');

    $query = $this->db->get($location_tbl);

    return $query->result();
  }

  /*
   * Get the country/state/county/town for the specified id values.
   * All list returned if any id not specified.
   */
  public function get_jurisdiction_by_id($jx_type, $id_list = null) {
    if(!empty($id_list)) {
      $this->db->where_in('id', $id_list);
    }
    $this->db->order_by('name', 'ASC');

    $location_tbl = "";
    if($jx_type === "country") {
      $location_tbl = "location_country";
    } else if($jx_type === "province") {
      $location_tbl = "location_province";
    } else if($jx_type === "county") {
      $location_tbl = "location_county";
    } else if($jx_type === "town") {
      $location_tbl = "location_town";
    }

    $query = $this->db->get($location_tbl);

    return $query->result();
  }

  /* Get the state list from 'States' table.
   * Return: States for specified country, All states if country is not specified.
   * */
  public function get_state_list_by_country($country = null) {
    $this->db->select('States.*');
    if(!empty($country)) {
      $this->db->where('countries.code', $country);
    }
    $this->db->join("countries", "States.countryId = countries.id", 'left');
    $this->db->where('States.jDmaId', null);
    $this->db->order_by('States.sOrder', 'ASC');

    $query = $this->db->get('States');

    return $query->result();
  }

  /* Get the state list from 'States' table.
   * Return: States for specified state code as parameter, All states if state codes are not specified.
   * */
  public function get_state_list($state_code_list = null) {
    if(!empty($state_code_list)) {
      $this->db->where_in('state', $state_code_list);
    }
    $this->db->order_by('sOrder', 'ASC');
    $query = $this->db->get('States');

    return $query->result();
  }

  /* Returns the incentive program list having specified sectors and categories from 'IncentivePrograms' table.
   * */
  public function get_incentive_list($selected_countries = null, $selected_provinces = null, $selected_counties = null, $selected_towns = null, $selected_sectors = null, $selected_categories = null, $selected_georestrictions = null, $selected_taxtypes = null) {
    $this->db->select('OIXIncentiveId, location_province.code State, location_province.name StateName, location_province.id StateId, 
                            jurisdiction.country_id ipdpCountry, Status, ProgramName, AKAProgramName, ProgramShortName,
                            IncentiveSummary, IncentiveDetail, SunsetDate, cchOther, cchLastUpdated, cchApplicationProvisions,
                            cchFilingRequirements, cchIndustrySpecificConsiderations, cchContactInformation, cchBigLinks,
                            cchRecordKey, pDmaId');
    $this->db->from('IncentivePrograms');
    $this->db->join('jurisdiction', 'jurisdiction.id = IncentivePrograms.jurisdiction_id');
    $this->db->join('location_province', 'location_province.id = jurisdiction.province_id', 'LEFT');

    if($selected_sectors != '') {
      $this->db->join('IPSectorsRel', 'IPSectorsRel.ipsrIncentiveId = IncentivePrograms.OIXIncentiveId', 'INNER');
    }
    if($selected_categories != '') {
      $this->db->join('IPCategoriesRel', 'IPCategoriesRel.ipcrIncentiveId = IncentivePrograms.OIXIncentiveId', 'INNER');
    }
    if($selected_taxtypes != '') {
      $this->db->join('IPTaxTypesRel', 'IPTaxTypesRel.iptrIncentiveId = IncentivePrograms.OIXIncentiveId', 'INNER');
    }

    $this->db->where('(IncentivePrograms.status=1 OR IncentivePrograms.pDmaId=' . $this->cisession->userdata('dmaId') . ')');

    if(!empty($selected_countries)) {
      $this->db->where_in('jurisdiction.country_id', $selected_countries);
    }
    if(!empty($selected_provinces)) {
      $this->db->where_in('jurisdiction.province_id', $selected_provinces);
    }
    if(!empty($selected_counties)) {
      $this->db->where_in('jurisdiction.county_id', $selected_counties);
    }
    if(!empty($selected_towns)) {
      $this->db->where_in('jurisdiction.town_id', $selected_towns);
    }
    if($selected_sectors != '') {
      $this->db->where_in('IPSectorsRel.ipsrSectorId', $selected_sectors);
    }
    if($selected_categories != '') {
      $this->db->where_in('IPCategoriesRel.ipcrCategoryId', $selected_categories);
    }
    if($selected_georestrictions != '') {
      $this->db->where_in('IncentivePrograms.cchGeographicConsiderations', $selected_georestrictions);
    }
    if($selected_taxtypes != '') {
      $this->db->where_in('IPTaxTypesRel.iptrTaxTypeId', $selected_taxtypes);
    }

    $this->db->group_by('IncentivePrograms.OIXIncentiveId');
    $this->db->order_by('location_province.code, IncentivePrograms.ProgramName');
    $query = $this->db->get();

    $return = [];
    foreach($query->result_array() as $data) {

      //Only return programs if they are Status 1 or if they are "other" program created by their DMA Account
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

  /*
   * Returns the number of incentive programs for the specified states.
   */
  public function get_incentive_programs_count_by_states($province_list) {
    $this->db->select('location_province.*, count(IncentivePrograms.OIXIncentiveId) programs_count');
    $this->db->from('location_province');
    $this->db->join('jurisdiction', 'jurisdiction.province_id = location_province.id');
    $this->db->join('IncentivePrograms', 'IncentivePrograms.jurisdiction_id = jurisdiction.id');
    $this->db->where_in('location_province.id', $province_list);
    $this->db->where('(IncentivePrograms.status=1 OR IncentivePrograms.pDmaId=' . $this->cisession->userdata('dmaId') . ')');
    $this->db->group_by('location_province.id');

    $query = $this->db->get();

    return $query->result();
  }

  /* Store HTML documents into 'IncentiveProgramLinkData' table.
   *
   * Return: Number of documents stored
   * */
  public function store_link_html_docs($incentive_id, $field_name, $link_docs) {
    $num_rows = 0;

    foreach($link_docs as $link) {
      $row = [
          'iplIncentiveId' => $incentive_id,
          'FieldName'      => $field_name,
          'LinkType'       => $link['link_type'],
          'CVHandle'       => $link['cv_handle'],
          'CVLink'         => $link['cv_link'],
          'DisplayText'    => $link['display_text'],
          'Url'            => $link['link_url'],
      ];

      if(!empty($link['html_doc_list'])) {
        // If HTML docs exist, store all docs by one doc in a row.
        foreach($link['html_doc_list'] as $doc) {
          $row['DocId'] = $doc['doc_id'];
          $row['DocTitle'] = $doc['title'];
          $row['DocContent'] = $doc['content'];
          $row['LastUpdated'] = $doc['last_changed'];

          $this->db->insert('IncentiveProgramLinkData', $row);
          $num_rows++;

          $iplId = $this->db->insert_id();

          // It's called only when store the HTML docs for new links.
          // So all HTML docs are new. Store change logs as new.
          $this->store_link_html_doc_changes($iplId, $doc['doc_id'], null, $doc['content'], 1);
        }
      } else {
        // If HTML doc doesn't exist, ignore it.
        // Such as URL links, CVH links not having HTML docs.
      }
    }

    return $num_rows;
  }

  /* Store a HTML document into 'IncentiveProgramLinkData' table.
   * If document already exists in DB, update the HTML doc with new one.
   * If not, insert a new HTML doc.
   *
   * Return: Inserted/Updated row id. NULL if no row inserted and updated.
   * */
  public function store_link_html_doc($html_doc) {
    // Check whether HTML doc already exist or not.
    $query = $this->db->get_where('IncentiveProgramLinkData', ['DocId' => $html_doc['DocId']]);
    $result = $query->row_array();

    if(count($result)) {
      // If document exist, update html_doc info such as LastChecked and DocContent (regardless updated or not)
      $this->db->where('iplId', $result['iplId']);
      $this->db->update('IncentiveProgramLinkData', $html_doc);

      // Check the changes of DocContent
      $last_checked = empty($result['LastChecked']) ? null : new DateTime($result['LastChecked']);
      $last_updated = empty($html_doc['LastUpdated']) ? null : new DateTime($html_doc['LastUpdated']);

      if($last_updated > $last_checked && $result['DocContent'] != $html_doc['DocContent']) {
        $iplId = $result['iplId'];

        // Store change logs
        $this->store_link_html_doc_changes($iplId, $html_doc['DocId'], $result['DocContent'], $html_doc['DocContent'], 0);
      } else {
        $iplId = null;
      }
    } else {
      $this->db->insert('IncentiveProgramLinkData', $html_doc);
      $iplId = $this->db->insert_id();

      // Store change logs
      $this->store_link_html_doc_changes($iplId, $html_doc['DocId'], null, $html_doc['DocContent'], 1);
    }

    return $iplId;
  }

  /* Get the HTML docs from 'IncentiveProgramLinkData' by filtering optional filter values.
   * Return: Array of HTML docs.
   * */
  public function get_html_docs($link_type = null, $cv_handle = null, $cv_link = null, $url = null) {
    if(!empty($link_type)) {
      $this->db->where('LinkType', $link_type);
    }
    if(!empty($cv_handle)) {
      $this->db->where('CVHandle', $cv_handle);
    }
    if(!empty($cv_link)) {
      $this->db->where('CVLink', $cv_link);
    }
    if(!empty($url)) {
      $this->db->where('Url', $url);
    }
    $this->db->order_by('iplIncentiveId', 'ASC');
    $query = $this->db->get('IncentiveProgramLinkData');

    return $query->result();
  }

  /* Get the link info from 'IncentiveProgramLinkData' by filtering optional values.
   * Return: Array of link info
   * */
  public function get_link_info($link_type = null) {
    $this->db->select('LinkType, CVHandle, CVLink, Url, DisplayText');
    if(!empty($link_type)) {
      $this->db->where('LinkType', $link_type);
    }
    $this->db->group_by(['CVHandle', 'CVLink']);
    $this->db->order_by('iplId', 'ASC');
    $query = $this->db->get('IncentiveProgramLinkData');

    return $query->result();
  }

  /* Get the link info checked earliest from 'IncentiveProgramLinkData' by filtering optional values.
   * Return: Array of link info
   * */
  public function get_link_info_checked_earliest($link_type = null, $link_count = null) {
    $this->db->select('LinkType, CVHandle, CVLink, Url, DisplayText');
    if(!empty($link_type)) {
      $this->db->where('LinkType', $link_type);
    }
    $this->db->order_by('LastChecked', 'ASC');
    if(!empty($link_count)) {
      $this->db->limit($link_count);
    }
    $query = $this->db->get('IncentiveProgramLinkData');

    return $query->result();
  }

  /* Delete the HTML docs from 'IncentiveProgramLinkData' by filtering optional filter values.
   * */
  public function delete_link_html_docs($link_type = null, $cv_handle = null, $cv_link = null, $url = null) {
    if(!empty($link_type)) {
      $this->db->where('LinkType', $link_type);
    }
    if(!empty($cv_handle)) {
      $this->db->where('CVHandle', $cv_handle);
    }
    if(!empty($cv_link)) {
      $this->db->where('CVLink', $cv_link);
    }
    if(!empty($url)) {
      $this->db->where('Url', $url);
    }
    $query = $this->db->delete('IncentiveProgramLinkData');
  }

  /* Update the Last Checked time on a program html doc (to support regular cron check on CCH docs)
   * */

  public function update_html_doc_last_checked($thisDocLastChecked) {
    $thisDateTime = date('Y-m-d H:i:s', time());
    $this->db->where($thisDocLastChecked);
    $this->db->update('IncentiveProgramLinkData', ['LastChecked' => $thisDateTime]);
  }

  /* Store incentive program HTML document changes for the links to 'IncentiveProgramLinkDataChanges' table.
   *
   * Return: Inserted row id
   * */
  public function store_link_html_doc_changes($iplId, $doc_id, $old_doc, $new_doc, $is_new) {
    $date = date("Y-m-d H:i:s");

    $this->db->insert('IncentiveProgramLinkDataChanges',
                      [
                          'iplId'       => $iplId,
                          'DocId'       => $doc_id,
                          'OldDoc'      => $old_doc,
                          'NewDoc'      => $new_doc,
                          'IsNew'       => $is_new,
                          'UpdatedDate' => $date,
                      ]);

    $iplcId = $this->db->insert_id();

    return $iplcId;
  }
}
