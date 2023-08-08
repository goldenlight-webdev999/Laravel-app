<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

require_once(APPPATH . "libraries/Aspose/Java.inc");
require_once(APPPATH . "libraries/Aspose/lib/aspose.cells.php");

use aspose\cells;

class Export_custom extends CI_Controller {
  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url']);
    $this->load->library('form_validation');
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }

    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');

    $this->load->model('CreditListings');
    $this->load->model('SpreadsheetData');

    $this->load->library('memberpermissions');

    $license = new cells\License();
    $license->setLicense(APPPATH . "javabridge/lib/Aspose.Cells.lic");
  }

  function index() {

    redirect('/');
  }

  function fox_portfolio_summary_sheet() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    }

    //1 - Get portfolio data

    //Centralized function to clean/prepare filter data
    $sanitizedPostData = $this->CreditListings->prepareFilterData();
    //Centralized function build filter query parameters
    $creditData = $this->CreditListings->buildFilterSearchData($sanitizedPostData);
    $credits = $this->CreditListings->get_credits($creditData);
    $data['records'] = $credits['credits'];
    $data['recordsSummary'] = $credits['summary'];

    $numCredits = sizeof($data['records']) - 1; //subtract first row as it is headers

    //2 - Get base data

    //$raw_monetization = $this->SpreadsheetData->get_raw_monetization();
    $raw_monetization = [];

    $workbook = new cells\Workbook();
    $sheets = $workbook->getWorksheets();
    $sheets->add();
    $sheets->add();
    $sheets->add();
    $sheets->get(0)->setName("Receivable Tracking Report");
    $sheets->get(1)->setName("DATA");
    $sheets->get(2)->setName("RawMonetization");
    $sheets->get(3)->setName("Monetization");
    $sheets->get(2)->setVisible(false);

    $basedatasheet = $sheets->get(1);
    $basedatasheet->freezePanes("E2", 1, 4);
    $tableStyles = $sheets->getTableStyles();
    $tableStyleName = "BaseData";
    $index1 = $tableStyles->addTableStyle($tableStyleName);
    $tableStyle = $tableStyles->get($index1);

    $rawsheet = $sheets->get(2);
    $rawsheet->freezePanes("A2", 1, 0);
    $rawtableStyles = $sheets->getTableStyles();
    $rawtableStyleName = "RawMonetization";
    $index2 = $rawtableStyles->addTableStyle($rawtableStyleName);
    $rawtableStyle = $rawtableStyles->get($index2);

    $basedatacells = $basedatasheet->getCells();
    $rawcells = $rawsheet->getCells();

    //////// Base Data Table ///////////////////
    $basedatacells->get(0, 0)->putValue('GROUP'); //fieldCustom_130_1538239031 - fieldCustom_562_1541868967
    $basedatacells->get(0, 1)->putValue('PROJECT ID'); //fieldCustom_130_1539126468 - fieldCustom_562_1541869235
    $basedatacells->get(0, 2)->putValue('DIVISION'); //fieldCustom_130_1538239062 - fieldCustom_562_1541868942
    $basedatacells->get(0, 3)->putValue('PRODUCTION'); //projectName
    $basedatacells->get(0, 4)->putValue('WORKING TITLE'); //(none use static) - fieldCustom_519_1547018639
    $basedatacells->get(0, 5)->putValue('LEGAL ENTITY'); //taxEntityCompanyName
    //$basedatacells->get(0, 6) -> putValue('NON-INT ENTITY / FOX NOT IN LEAD');
    //$basedatacells->get(0, 7) -> putValue('GLOBAL JURISDICTION'); //if(countryId = 1) ? Domestic : International
    $basedatacells->get(0, 6)->putValue('COUNTRY'); //countryName
    $basedatacells->get(0, 7)->putValue('LOCATION'); //jurisdictionName
    $basedatacells->get(0, 8)->putValue('INCENTIVE CATEGORY'); //fieldCustom_130_1542420314 - custom_519_1541869312
    $basedatacells->get(0, 9)->putValue('INCENTIVE TYPE'); //ProgramTypeName
    $basedatacells->get(0, 10)->putValue('INCENTIVE DESIGNATION'); //fieldCustom_130_1542420313 - custom_519_1541869311
    $basedatacells->get(0, 11)->putValue('CURRENCY CODE'); //localCurrency
    //$basedatacells->get(0, 14) -> putValue('BUDGET LOCAL CURRENCY');
    //$basedatacells->get(0, 15) -> putValue('BUDGET LOCK RATE');
    $basedatacells->get(0, 12)->putValue('BUDGET USD'); //projectBudgetEst
    $basedatacells->get(0, 13)->putValue('GROSS LOCAL CURRENCY'); // creditAmountLocal
    $basedatacells->get(0, 14)->putValue('GROSS USD'); //creditAmount
    $basedatacells->get(0, 15)->putValue('NET USD'); // utilize_total_net_value
    //$basedatacells->get(0, 16) -> putValue('RECEIVED USD SINCE PRIOR REPORT');
    //$basedatacells->get(0, 17) -> putValue('RECEIVED USD INCREASE');
    $basedatacells->get(0, 16)->putValue('RECEIVED USD'); //utilize_pending_actual_net_value
    $basedatacells->get(0, 17)->putValue('PENDING USD'); //utilize_pending_estimate_net_value
    //$basedatacells->get(0, 24) -> putValue('PRIOR PENDING USD');
    //$basedatacells->get(0, 25) -> putValue('FX PENDING DELTA');
    //$basedatacells->get(0, 26) -> putValue('PROD UPDATE PENDING DELTA');
    //$basedatacells->get(0, 27) -> putValue('RECEIVED PENDING DELTA');
    //$basedatacells->get(0, 28) -> putValue('NEW INCENTIVE PENDING DELTA');
    //$basedatacells->get(0, 29) -> putValue('FISCAL YEAR RETURN TO BE CLAIMED IN');
    //$basedatacells->get(0, 30) -> putValue('PRODUCTION SERVICES FEE');
    //$basedatacells->get(0, 31) -> putValue('FINAL CERTIFICATE RECEIVED');
    //$basedatacells->get(0, 32) -> putValue('JOURNAL ENTRY BOOKED');
    //$basedatacells->get(0, 33) -> putValue('JOURNAL ENTRY NUMBER');
    //$basedatacells->get(0, 34) -> putValue('PLANNED USAGE');
    //$basedatacells->get(0, 35) -> putValue('NOTES');
    //$basedatacells->get(0, 36) -> putValue('CHANGES FROM LAST DISTRIBUTED REPORT');

    $rowCount = 0;

    foreach($data['records'] as $row) {

      if($rowCount == 0) {
        //skip first row
      } else {

        $ifDev = ($this->config->item('environment') == "DEV") ? true : false;
        //get value flags
        $fieldGroup = ($ifDev) ? "custom_130_1538239031" : "custom_562_1541868967"; //GROUP
        $fieldProjectId = ($ifDev) ? "custom_130_1539126468" : "custom_562_1541869235"; //PROJECT ID
        $fieldDivision = ($ifDev) ? "custom_130_1538239062" : "custom_562_1541868942"; //DIVISION
        $fieldProjectName = "projectName"; //PRODUCTION
        $fieldWorkingTitle = ($ifDev) ? "projectName" : "custom_519_1547018639"; //WORKING TITLE
        $fieldTaxEntity = "tpCompanyName"; //LEGAL ENTITY
        $fieldCountry = "countryName"; //COUNTRY
        $fieldJurisdiction = "jurisdictionName"; //LOCATION
        $fieldIncentiveCategory = ($ifDev) ? "custom_130_1542420314" : "custom_519_1541869312"; //INCENTIVE CATEGORY
        $fieldCreditType = "ProgramTypeName"; //INCENTIVE TYPE
        $fieldIncentiveDesignation = ($ifDev) ? "custom_130_1542420313" : "custom_519_1541869311"; //INCENTIVE DESIGNATION
        $fieldLocalCurrencyCode = "localCurrency"; //CURRENCY CODE
        $fieldProjectBudget = "projectBudgetEst"; //BUDGET USD
        $fieldGLC = "amountLocal"; //GROSS LOCAL CURRENCY
        $fieldCreditAmount = "amountUSD"; //GROSS USD
        $fieldAmountNet = "utilize_total_net_value"; //NET USD
        $fieldReceivedUSD = "utilize_pending_actual_net_value"; //RECEIVED USD
        $fieldPendingUSD = "utilize_pending_estimate_net_value"; //PENDING USD

        $basedatacells->get($rowCount, 0)->putValue($row[$fieldGroup]);
        $basedatacells->get($rowCount, 1)->putValue($row[$fieldProjectId]);
        $basedatacells->get($rowCount, 2)->putValue($row[$fieldDivision]);
        $basedatacells->get($rowCount, 3)->putValue($row[$fieldProjectName]);
        $basedatacells->get($rowCount, 4)->putValue($row[$fieldWorkingTitle]);
        $basedatacells->get($rowCount, 5)->putValue($row[$fieldTaxEntity]);
        $basedatacells->get($rowCount, 6)->putValue($row[$fieldCountry]);
        $basedatacells->get($rowCount, 7)->putValue($row[$fieldJurisdiction]);
        $basedatacells->get($rowCount, 8)->putValue($row[$fieldIncentiveCategory]);
        $basedatacells->get($rowCount, 9)->putValue($row[$fieldCreditType]);
        $basedatacells->get($rowCount, 10)->putValue($row[$fieldIncentiveDesignation]);
        $basedatacells->get($rowCount, 11)->putValue($row[$fieldLocalCurrencyCode]);
        $basedatacells->get($rowCount, 12)->putValue($row[$fieldProjectBudget]);
        $basedatacells->get($rowCount, 13)->putValue((float)($row[$fieldGLC]));
        $basedatacells->get($rowCount, 14)->putValue((float)($row[$fieldCreditAmount]));
        $basedatacells->get($rowCount, 15)->putValue((float)($row[$fieldAmountNet]));
        $basedatacells->get($rowCount, 16)->putValue((float)($row[$fieldReceivedUSD]));
        $basedatacells->get($rowCount, 17)->putValue((float)($row[$fieldPendingUSD]));

      }

      $rowCount++;

    }

    //////// Raw Monetization Table  ///////////////////////
    $rawcells->get(0, 0)->putValue("PRODUCTION"); //projectName
    $rawcells->get(0, 1)->putValue("PROJECT ID"); //fieldCustom_130_1539126468 - fieldCustom_562_1541869235
    $rawcells->get(0, 2)->putValue("DIVISION"); //fieldCustom_130_1538239062 - fieldCustom_562_1541868942
    $rawcells->get(0, 3)->putValue("LEGAL ENTITY"); //taxEntityCompanyName
    //$rawcells->get(0, 4) -> putValue("NON-FOX ENTITY / FOX NOT IN LEAD");
    //$rawcells->get(0, 5) -> putValue("US OR FOREIGN");
    $rawcells->get(0, 6)->putValue("COUNTRY"); //countryName
    $rawcells->get(0, 7)->putValue("LOCATION"); //jurisdictionName
    //$rawcells->get(0, 8) -> putValue("STATE");
    $rawcells->get(0, 9)->putValue("INCENTIVE CATEGORY"); //fieldCustom_130_1542420314 - custom_519_1541869312
    $rawcells->get(0, 10)->putValue("INCENTIVE TYPE"); //ProgramTypeName
    $rawcells->get(0, 11)->putValue("INCENTIVE DESIGNATION"); //fieldCustom_130_1542420313 - custom_519_1541869311
    $rawcells->get(0, 12)->putValue("TAX STATUS");
    $rawcells->get(0, 13)->putValue("FINANCE STATUS");
    $rawcells->get(0, 14)->putValue("TAX PERIOD");
    $rawcells->get(0, 15)->putValue("FINANCE PERIOD");
    $rawcells->get(0, 16)->putValue("GROSS BEFORE INTEREST LOCAL CURRENCY");
    $rawcells->get(0, 17)->putValue("INTEREST LOCAL CURRENCY");
    $rawcells->get(0, 18)->putValue("GROSS LOCAL CURRENCY");
    $rawcells->get(0, 19)->putValue("GROSS USD");
    $rawcells->get(0, 20)->putValue("NET USD");
    $rawcells->get(0, 21)->putValue("DISCOUNT RATE");
    $rawcells->get(0, 22)->putValue("SPOT EXCHANGE RATE");
    $rawcells->get(0, 23)->putValue("COMMENTS");
    $rawcells->get(0, 24)->putValue("GROUP");
    $rawcells->get(0, 25)->putValue("PRODUCTION COMPLETED");
    $rawcells->get(0, 26)->putValue("CURRENCY CODE");
    $rawcells->get(0, 27)->putValue("RELEASED");
    $rawcells->get(0, 28)->putValue("CURRENT FX RATE");
    $rawcells->get(0, 29)->putValue("TRANSACTION CATEGORY");
    $rawcells->get(0, 30)->putValue("EXPECTED CERT RECEIVED DATE");
    $rawcells->get(0, 31)->putValue("JOURNAL ENTRY ID");
    $rawcells->get(0, 32)->putValue("FIRST AVAILABLE TAX PERIOD");
    $rawcells->get(0, 33)->putValue("TAX PERIOD YEAR");
    $rawcells->get(0, 34)->putValue("FINAL CERTIFICATE RECEIVED DATE");
    $rawcells->get(0, 35)->putValue("IS NON FOX IND");
    $rawcells->get(0, 36)->putValue("DIVISION FULL NAME");
    $rawcells->get(0, 37)->putValue("WORKING TITLE");

    foreach($raw_monetization as $i => $row) {
      $rawcells->get($i + 1, 0)->putValue($row->production);
      $rawcells->get($i + 1, 1)->putValue($row->project_id);
      $rawcells->get($i + 1, 2)->putValue($row->division);
      $rawcells->get($i + 1, 3)->putValue($row->legal_entity);
      $rawcells->get($i + 1, 4)->putValue($row->non_fox_entity_fox_not_in_lead);
      $rawcells->get($i + 1, 5)->putValue($row->us_or_foreign);
      $rawcells->get($i + 1, 6)->putValue($row->country);
      $rawcells->get($i + 1, 7)->putValue($row->location);
      $rawcells->get($i + 1, 8)->putValue($row->state);
      $rawcells->get($i + 1, 9)->putValue($row->incentive_category);
      $rawcells->get($i + 1, 10)->putValue($row->incentive_type);
      $rawcells->get($i + 1, 11)->putValue($row->incentive_designation);
      $rawcells->get($i + 1, 12)->putValue($row->tax_status);
      $rawcells->get($i + 1, 13)->putValue($row->finance_status);
      $rawcells->get($i + 1, 14)->putValue($row->tax_period);
      $rawcells->get($i + 1, 15)->putValue($row->finance_period);
      $rawcells->get($i + 1, 16)->putValue((float)($row->gross_before_interest_local_currency));
      $rawcells->get($i + 1, 17)->putValue((float)($row->interest_local_currency));
      $rawcells->get($i + 1, 18)->putValue((float)($row->gross_local_currency));
      $rawcells->get($i + 1, 19)->putValue((float)($row->gross_usd));
      $rawcells->get($i + 1, 20)->putValue((float)($row->net_usd));
      $rawcells->get($i + 1, 21)->putValue($row->discount_rate);
      $rawcells->get($i + 1, 22)->putValue($row->spot_exchange_rate);
      $rawcells->get($i + 1, 23)->putValue($row->comments);
      $rawcells->get($i + 1, 24)->putValue($row->group);
      $rawcells->get($i + 1, 25)->putValue($row->production_completed);
      $rawcells->get($i + 1, 26)->putValue($row->currency_code);
      $rawcells->get($i + 1, 27)->putValue($row->released);
      $rawcells->get($i + 1, 28)->putValue($row->current_fx_rate);
      $rawcells->get($i + 1, 29)->putValue($row->transaction_category);
      $rawcells->get($i + 1, 30)->putValue($row->expected_cert_received_date);
      $rawcells->get($i + 1, 31)->putValue($row->journal_entry_id);
      $rawcells->get($i + 1, 32)->putValue($row->first_available_tax_period);
      $rawcells->get($i + 1, 33)->putValue($row->tax_period_year);
      $rawcells->get($i + 1, 34)->putValue($row->final_certificate_received_date);
      $rawcells->get($i + 1, 35)->putValue($row->is_non_fox_ind);
      $rawcells->get($i + 1, 36)->putValue($row->division_full_name);
      $rawcells->get($i + 1, 37)->putValue($row->working_title);
    }

    //Style the RAW UTILIZATION ($rawcells) fields
    for($row = 1; $row < $numCredits; $row++) {
      for($column = 0; $column < 38; $column++) {
        $numstyle = $rawcells->getStyle();
        $rawcells->get($row, $column)->setStyle($numstyle);
      }
    }

    //Style the DATA ($basedatacells) and RAW UTILIZATION ($rawcells) fields
    for($i = 0; $i < 18; $i++) {
      $rawcells->getColumns()->get($i)->setWidth(15);
      $basedatacells->getColumns()->get($i)->setWidth(16);
      $style = $basedatacells->get(0, 0)->getStyle();
      for($j = 0; $j < $numCredits; $j++) {
        $cell = $basedatacells->get($j, $i);
        $style->setTextWrapped(true);
        if($i >= 12 && $i <= 18) {
          $style->setNumber(7);
        }
        $cell->setStyle($style);
      }
    }
    $basedatacells->getColumns()->get(0)->setWidth(11);
    $basedatacells->getColumns()->get(1)->setWidth(10);
    $basedatacells->getColumns()->get(2)->setWidth(10);
    $basedatacells->getColumns()->get(3)->setWidth(35);
    $basedatacells->getColumns()->get(4)->setWidth(35);
    $basedatacells->getColumns()->get(5)->setWidth(35);
    $basedatacells->getColumns()->get(8)->setWidth(11);
    $basedatacells->getColumns()->get(9)->setWidth(20);
    $basedatacells->getColumns()->get(10)->setWidth(20);
    $basedatacells->getColumns()->get(11)->setWidth(12);

    $tables = $basedatasheet->getListObjects();
    $index = $tables->add(0, 0, $numCredits, 17, true);
    $table = $tables->get(0);
    $table->setShowTotals(true);
    $table->getListColumns()->get(12)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(13)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(14)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(15)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(16)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(17)->setTotalsCalculation(cells\TotalsCalculation::SUM);

    $table->setTableStyleType(cells\TableStyleType::TABLE_STYLE_MEDIUM_9);

    $reportsheet = $sheets->get(0);
    $reportsheet->freezePanes("B3", 2, 1);
    $reportTables = $reportsheet->getPivotTables();
    $thisRange = "=DATA!A1:R" . $numCredits;
    $index = $reportTables->add($thisRange, "A1", "PivotTable1");
    $reportTable = $reportTables->get($index);
    $reportTable->setAutoFormat(true);
    // Setting the PivotTable autoformat type.
    $reportTable->setAutoFormatType(Cells\PivotTableAutoFormatType::REPORT_7);
    $reportTable->setPivotTableStyleType(cells\PivotTableStyleType::PIVOT_TABLE_STYLE_MEDIUM_2);
    // Draging fields to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 3);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 4);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 2);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 5);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 6);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 7);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 9);
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 10);

    $reportTable->removeField(Cells\PivotFieldType::ROW, "Values");

    // Draging fields to the data area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 14);
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 15);
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 16);
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 17);

    $reportTable->setAutoFormat(false);
    $reportTable->setRowGrand(false);
    $reportTable->setColumnGrand(true);
    $reportTable->setShowDrill(false);
    $reportTable->setPrintDrill(false);
    $reportTable->setPrintTitles(false);

    $datafield = $reportTable->getDataField();
    $reportTable->addFieldToArea(Cells\PivotFieldType::COLUMN, $datafield);

    $reportsheet->getAutoFilter()->setRange("B2:H2");
    $reportsheet->getAutoFilter()->refresh();

    $fields = $reportTable->getRowFields();

    $reportTable->getDataFields()->get(0)->setNumber(7);
    $reportTable->getDataFields()->get(1)->setNumber(7);
    $reportTable->getDataFields()->get(2)->setNumber(7);
    $reportTable->getDataFields()->get(3)->setNumber(7);
    $reportTable->getDataFields()->get(4)->setNumber(7);
    $reportTable->getDataFields()->get(0)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(1)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(2)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(3)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(4)->setFunction(Cells\ConsolidationFunction::SUM);

    for($i = 0; $i < 11; $i++) {
      if($i) {
        $fields->get($i)->setAutoSubtotals(false);
      }
      $fields->get($i)->setShowInOutlineForm(false);
      $fields->get($i)->setRepeatItemLabels(true);
      $fields->get($i)->setInsertBlankRow(false);
    }
    $reportsheet->getCells()->getColumns()->get(0)->setWidth(35);
    $reportsheet->getCells()->getColumns()->get(1)->setWidth(35);
    $reportsheet->getCells()->getColumns()->get(2)->setWidth(10);
    $reportsheet->getCells()->getColumns()->get(3)->setWidth(35);

    for($i = 4; $i < 15; $i++) {
      $reportsheet->getCells()->getColumns()->get($i)->setWidth(15);
    }

    $reportsheet->getCells()->getColumns()->get(6)->setWidth(20);
    $reportsheet->getCells()->getColumns()->get(7)->setWidth(20);
    $reportsheet->getCells()->getColumns()->get(8)->setWidth(18);
    $reportsheet->getCells()->getColumns()->get(9)->setWidth(18);
    $reportsheet->getCells()->getColumns()->get(10)->setWidth(18);
    $reportsheet->getCells()->getColumns()->get(11)->setWidth(18);

    $reportTable->setRefreshDataFlag(true);
    $reportTable->refreshData();
    $reportTable->setRefreshDataFlag(false);
    $reportTable->calculateRange();
    $reportTable->calculateData();
    $reportTable->setRefreshDataOnOpeningFile(true);

    $columnCount = $reportsheet->getCells()->getColumns()->getCount();
    for($j = 0; $j < 50; $j++) {
      for($i = 0; $i < java_values($columnCount); $i++) {
        $cell = $reportsheet->getCells()->get($j, $i);
        $style = $cell->getStyle();
        $style->setTextWrapped(true);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::LEFT_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::RIGHT_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::TOP_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::BOTTOM_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border->setColor(cells\Color::getRed());
        $reportTable->format($j, $i, $style);
        $cell->setStyle($style);
      }
    }

    $reportTable->setRowHeaderCaption("PRODUCTION");

    $rawtables = $rawsheet->getListObjects();
    $index = $rawtables->add(0, 0, 690, 37, true);
    $table = $rawtables->get(0);

    $monetizationsheet = $sheets->get(3);
    $monetizationsheet->freezePanes("B4", 3, 1);
    $monetizationTables = $monetizationsheet->getPivotTables();
    $index = $monetizationTables->add("=RawMonetization!A1:AL691", "A1", "PivotTable2");
    $monetizationTable = $monetizationTables->get($index);

    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 0);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 37);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 1);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 2);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 3);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 4);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 5);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 6);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 7);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 9);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 10);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 11);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 23);

    $monetizationTable->addFieldToArea(Cells\PivotFieldType::COLUMN, 12);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::COLUMN, 14);

    $monetizationTable->addFieldToArea(Cells\PivotFieldType::DATA, 20);

    $monetizationTable->setAutoFormat(false);
    $monetizationTable->setRowGrand(true);
    $monetizationTable->setColumnGrand(true);
    $monetizationTable->setShowDrill(false);
    $monetizationTable->setPrintDrill(false);
    $monetizationTable->setPrintTitles(false);
    $monetizationTable->setGridDropZones(true);

    $monetizationsheet->getAutoFilter()->setRange("A3:M3");
    $monetizationsheet->getAutoFilter()->refresh();
    $monetizationTable->setRowHeaderCaption("PRODUCTION");

    $monetizationTable->getDataFields()->get(0)->setDisplayName("sdf");

    $fields = $monetizationTable->getRowFields();

    $monetizationTable->getDataFields()->get(0)->setNumber(7);

    for($i = 0; $i < 13; $i++) {
      $fields->get($i)->setAutoSubtotals(false);
      $fields->get($i)->setShowInOutlineForm(false);
      $fields->get($i)->setRepeatItemLabels(true);
      $fields->get($i)->setInsertBlankRow(false);
    }
    for($i = 0; $i < 40; $i++) {
      $monetizationsheet->getCells()->getColumns()->get($i)->setWidth(16);
      $rawsheet->getCells()->getColumns()->get($i)->setWidth(15);
    }
    $monetizationsheet->getCells()->getColumns()->get(0)->setWidth(35);
    $monetizationsheet->getCells()->getColumns()->get(1)->setWidth(35);
    $monetizationsheet->getCells()->getColumns()->get(2)->setWidth(9);
    $monetizationsheet->getCells()->getColumns()->get(3)->setWidth(10);
    $monetizationsheet->getCells()->getColumns()->get(4)->setWidth(35);
    $monetizationsheet->getCells()->getColumns()->get(5)->setWidth(10);
    $monetizationsheet->getCells()->getColumns()->get(6)->setWidth(11);
    $monetizationsheet->getCells()->getColumns()->get(7)->setWidth(14);
    $monetizationsheet->getCells()->getColumns()->get(8)->setWidth(14);
    $monetizationsheet->getCells()->getColumns()->get(9)->setWidth(12);
    $monetizationsheet->getCells()->getColumns()->get(10)->setWidth(20);
    $monetizationsheet->getCells()->getColumns()->get(11)->setWidth(20);
    $monetizationsheet->getCells()->getColumns()->get(12)->setWidth(40);

    $rawsheet->getCells()->getColumns()->get(0)->setWidth(37);
    $rawsheet->getCells()->getColumns()->get(1)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(2)->setWidth(15);
    $rawsheet->getCells()->getColumns()->get(3)->setWidth(36);
    $rawsheet->getCells()->getColumns()->get(4)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(5)->setWidth(18);
    $rawsheet->getCells()->getColumns()->get(6)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(7)->setWidth(22);
    $rawsheet->getCells()->getColumns()->get(8)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(9)->setWidth(24);
    $rawsheet->getCells()->getColumns()->get(10)->setWidth(29);
    $rawsheet->getCells()->getColumns()->get(11)->setWidth(41);
    $rawsheet->getCells()->getColumns()->get(23)->setWidth(214);
    $rawsheet->getCells()->getColumns()->get(36)->setWidth(45);

    $monetizationTable->setRefreshDataFlag(true);
    $monetizationTable->refreshData();
    $monetizationTable->setRefreshDataFlag(false);
    $monetizationTable->calculateRange();
    $monetizationTable->calculateData();
    $monetizationTable->setRefreshDataOnOpeningFile(true);

    $columnCount = $monetizationsheet->getCells()->getColumns()->getCount();
    for($j = 0; $j < 700; $j++) {
      for($i = 0; $i < java_values($columnCount); $i++) {
        $cell = $monetizationsheet->getCells()->get($j, $i);
        $style = $cell->getStyle();
        $style->setTextWrapped(true);
        $monetizationTable->format($j, $i, $style);
      }
    }

    $dirpath = APPPATH . '../tmp_files/';
    $workbook->save($dirpath . "oix_spreadsheet.xlsx");
    $fileurl = $dirpath . "oix_spreadsheet.xlsx";

    header('Pragma: public');
    header("Content-type:application/vnd.ms-excel; charset=utf-8");
    header('Content-Disposition: attachment; filename=' . "oix_spreadsheet.xlsx");
    header('Content-Length: ' . filesize($fileurl));

    readfile($fileurl);

    echo "Document has been saved, please check the output file.";

  }

  function original_fox_spreadsheet_static() {

    //THIS IS HERE ONLY TO REFER TO STYLING AND LAYOUT - IT NO LONGER ACTUALLY FUNCTIONS SINCE INPUT DATA IS NOT THERE ANYMORE

    if($this->config->item('environment') != "DEV") {
      throw new \Exception('General fail');
    }

    $base_data = $this->SpreadsheetData->get_base_data(); //no longer exists
    $raw_monetization = $this->SpreadsheetData->get_raw_monetization(); //no longer exists

    $workbook = new cells\Workbook();
    $sheets = $workbook->getWorksheets();
    $sheets->add();
    $sheets->add();
    $sheets->add();
    $sheets->get(0)->setName("Receivable Tracking Report");
    $sheets->get(1)->setName("DATA");
    $sheets->get(2)->setName("RawMonetization");
    $sheets->get(3)->setName("Monetization");
    $sheets->get(2)->setVisible(false);

    $basedatasheet = $sheets->get(1);
    $basedatasheet->freezePanes("E2", 1, 4);
    $tableStyles = $sheets->getTableStyles();
    $tableStyleName = "BaseData";
    $index1 = $tableStyles->addTableStyle($tableStyleName);
    $tableStyle = $tableStyles->get($index1);

    $rawsheet = $sheets->get(2);
    $rawsheet->freezePanes("A2", 1, 0);
    $rawtableStyles = $sheets->getTableStyles();
    $rawtableStyleName = "RawMonetization";
    $index2 = $rawtableStyles->addTableStyle($rawtableStyleName);
    $rawtableStyle = $rawtableStyles->get($index2);

    $basedatacells = $basedatasheet->getCells();
    $rawcells = $rawsheet->getCells();

    //////// Base Data Table ///////////////////
    $basedatacells->get(0, 0)->putValue('GROUP');
    $basedatacells->get(0, 1)->putValue('PROJECT ID');
    $basedatacells->get(0, 2)->putValue('DIVISION');
    $basedatacells->get(0, 3)->putValue('PRODUCTION');
    $basedatacells->get(0, 4)->putValue('WORKING TITLE');
    $basedatacells->get(0, 5)->putValue('LEGAL ENTITY');
    $basedatacells->get(0, 6)->putValue('NON-INT ENTITY / FOX NOT IN LEAD');
    $basedatacells->get(0, 7)->putValue('GLOBAL JURISDICTION');
    $basedatacells->get(0, 8)->putValue('COUNTRY');
    $basedatacells->get(0, 9)->putValue('LOCATION');
    $basedatacells->get(0, 10)->putValue('INCENTIVE CATEGORY');
    $basedatacells->get(0, 11)->putValue('INCENTIVE TYPE');
    $basedatacells->get(0, 12)->putValue('INCENTIVE DESIGNATION');
    $basedatacells->get(0, 13)->putValue('CURRENCY CODE');
    $basedatacells->get(0, 14)->putValue('BUDGET LOCAL CURRENCY');
    $basedatacells->get(0, 15)->putValue('BUDGET LOCK RATE');
    $basedatacells->get(0, 16)->putValue('BUDGET USD');
    $basedatacells->get(0, 17)->putValue('GROSS LOCAL CURRENCY');
    $basedatacells->get(0, 18)->putValue('GROSS USD');
    $basedatacells->get(0, 19)->putValue('NET USD');
    $basedatacells->get(0, 20)->putValue('RECEIVED USD SINCE PRIOR REPORT');
    $basedatacells->get(0, 21)->putValue('RECEIVED USD INCREASE');
    $basedatacells->get(0, 22)->putValue('RECEIVED USD');
    $basedatacells->get(0, 23)->putValue('PENDING USD');
    $basedatacells->get(0, 24)->putValue('PRIOR PENDING USD');
    $basedatacells->get(0, 25)->putValue('FX PENDING DELTA');
    $basedatacells->get(0, 26)->putValue('PROD UPDATE PENDING DELTA');
    $basedatacells->get(0, 27)->putValue('RECEIVED PENDING DELTA');
    $basedatacells->get(0, 28)->putValue('NEW INCENTIVE PENDING DELTA');
    $basedatacells->get(0, 29)->putValue('FISCAL YEAR RETURN TO BE CLAIMED IN');
    $basedatacells->get(0, 30)->putValue('PRODUCTION SERVICES FEE');
    $basedatacells->get(0, 31)->putValue('FINAL CERTIFICATE RECEIVED');
    $basedatacells->get(0, 32)->putValue('JOURNAL ENTRY BOOKED');
    $basedatacells->get(0, 33)->putValue('JOURNAL ENTRY NUMBER');
    $basedatacells->get(0, 34)->putValue('PLANNED USAGE');
    $basedatacells->get(0, 35)->putValue('NOTES');
    $basedatacells->get(0, 36)->putValue('CHANGES FROM LAST DISTRIBUTED REPORT');

    foreach($base_data as $i => $row) {
      $basedatacells->get($i + 1, 0)->putValue($row->group);
      $basedatacells->get($i + 1, 1)->putValue($row->project_id);
      $basedatacells->get($i + 1, 2)->putValue($row->division);
      $basedatacells->get($i + 1, 3)->putValue($row->production);
      $basedatacells->get($i + 1, 4)->putValue($row->working_title);
      $basedatacells->get($i + 1, 5)->putValue($row->legal_entity);
      $basedatacells->get($i + 1, 6)->putValue($row->non_int_entity_fox_not_lead);
      $basedatacells->get($i + 1, 7)->putValue($row->global_jurisdiction);
      $basedatacells->get($i + 1, 8)->putValue($row->country);
      $basedatacells->get($i + 1, 9)->putValue($row->location);
      $basedatacells->get($i + 1, 10)->putValue($row->incentive_category);
      $basedatacells->get($i + 1, 11)->putValue($row->incentive_type);
      $basedatacells->get($i + 1, 12)->putValue($row->incentive_designation);
      $basedatacells->get($i + 1, 13)->putValue($row->currency_code);
      $basedatacells->get($i + 1, 14)->putValue($row->budget_local_currency);
      $basedatacells->get($i + 1, 15)->putValue($row->budget_lock_rate);
      $basedatacells->get($i + 1, 16)->putValue($row->budget_usd);
      $basedatacells->get($i + 1, 17)->putValue((float)($row->gross_local_currency));
      $basedatacells->get($i + 1, 18)->putValue((float)($row->gross_usd));
      $basedatacells->get($i + 1, 19)->putValue((float)($row->net_usd));
      $basedatacells->get($i + 1, 20)->putValue((float)($row->received_usd_since_prior_report));
      $basedatacells->get($i + 1, 21)->putValue((float)($row->received_usd_increase));
      $basedatacells->get($i + 1, 22)->putValue((float)($row->received_usd));
      $basedatacells->get($i + 1, 23)->putValue((float)($row->pending_usd));
      $basedatacells->get($i + 1, 24)->putValue((float)($row->prior_pending_usd));
      $basedatacells->get($i + 1, 25)->putValue((float)($row->fx_pending_delta));
      $basedatacells->get($i + 1, 26)->putValue((float)($row->prod_update_pending_delta));
      $basedatacells->get($i + 1, 27)->putValue((float)($row->received_pending_delta));
      $basedatacells->get($i + 1, 28)->putValue((float)($row->new_incentive_pending_delta));
      $basedatacells->get($i + 1, 29)->putValue($row->fiscal_year_return_to_be_claimed_in);
      $basedatacells->get($i + 1, 30)->putValue($row->production_services_fee);
      $basedatacells->get($i + 1, 31)->putValue($row->final_certificate_received);
      $basedatacells->get($i + 1, 32)->putValue($row->journal_entry_booked);
      $basedatacells->get($i + 1, 33)->putValue($row->journal_entry_number);
      $basedatacells->get($i + 1, 34)->putValue($row->planned_usage);
      $basedatacells->get($i + 1, 35)->putValue($row->notes);
      $basedatacells->get($i + 1, 36)->putValue($row->changes_from_last_distributed_report);
    }

    //////// Raw Monetization Table  ///////////////////////
    $rawcells->get(0, 0)->putValue("PRODUCTION");
    $rawcells->get(0, 1)->putValue("PROJECT ID");
    $rawcells->get(0, 2)->putValue("DIVISION");
    $rawcells->get(0, 3)->putValue("LEGAL ENTITY");
    $rawcells->get(0, 4)->putValue("NON-FOX ENTITY / FOX NOT IN LEAD");
    $rawcells->get(0, 5)->putValue("US OR FOREIGN");
    $rawcells->get(0, 6)->putValue("COUNTRY");
    $rawcells->get(0, 7)->putValue("LOCATION");
    $rawcells->get(0, 8)->putValue("STATE");
    $rawcells->get(0, 9)->putValue("INCENTIVE CATEGORY");
    $rawcells->get(0, 10)->putValue("INCENTIVE TYPE");
    $rawcells->get(0, 11)->putValue("INCENTIVE DESIGNATION");
    $rawcells->get(0, 12)->putValue("TAX STATUS");
    $rawcells->get(0, 13)->putValue("FINANCE STATUS");
    $rawcells->get(0, 14)->putValue("TAX PERIOD");
    $rawcells->get(0, 15)->putValue("FINANCE PERIOD");
    $rawcells->get(0, 16)->putValue("GROSS BEFORE INTEREST LOCAL CURRENCY");
    $rawcells->get(0, 17)->putValue("INTEREST LOCAL CURRENCY");
    $rawcells->get(0, 18)->putValue("GROSS LOCAL CURRENCY");
    $rawcells->get(0, 19)->putValue("GROSS USD");
    $rawcells->get(0, 20)->putValue("NET USD");
    $rawcells->get(0, 21)->putValue("DISCOUNT RATE");
    $rawcells->get(0, 22)->putValue("SPOT EXCHANGE RATE");
    $rawcells->get(0, 23)->putValue("COMMENTS");
    $rawcells->get(0, 24)->putValue("GROUP");
    $rawcells->get(0, 25)->putValue("PRODUCTION COMPLETED");
    $rawcells->get(0, 26)->putValue("CURRENCY CODE");
    $rawcells->get(0, 27)->putValue("RELEASED");
    $rawcells->get(0, 28)->putValue("CURRENT FX RATE");
    $rawcells->get(0, 29)->putValue("TRANSACTION CATEGORY");
    $rawcells->get(0, 30)->putValue("EXPECTED CERT RECEIVED DATE");
    $rawcells->get(0, 31)->putValue("JOURNAL ENTRY ID");
    $rawcells->get(0, 32)->putValue("FIRST AVAILABLE TAX PERIOD");
    $rawcells->get(0, 33)->putValue("TAX PERIOD YEAR");
    $rawcells->get(0, 34)->putValue("FINAL CERTIFICATE RECEIVED DATE");
    $rawcells->get(0, 35)->putValue("IS NON FOX IND");
    $rawcells->get(0, 36)->putValue("DIVISION FULL NAME");
    $rawcells->get(0, 37)->putValue("WORKING TITLE");

    foreach($raw_monetization as $i => $row) {
      $rawcells->get($i + 1, 0)->putValue($row->production);
      $rawcells->get($i + 1, 1)->putValue($row->project_id);
      $rawcells->get($i + 1, 2)->putValue($row->division);
      $rawcells->get($i + 1, 3)->putValue($row->legal_entity);
      $rawcells->get($i + 1, 4)->putValue($row->non_fox_entity_fox_not_in_lead);
      $rawcells->get($i + 1, 5)->putValue($row->us_or_foreign);
      $rawcells->get($i + 1, 6)->putValue($row->country);
      $rawcells->get($i + 1, 7)->putValue($row->location);
      $rawcells->get($i + 1, 8)->putValue($row->state);
      $rawcells->get($i + 1, 9)->putValue($row->incentive_category);
      $rawcells->get($i + 1, 10)->putValue($row->incentive_type);
      $rawcells->get($i + 1, 11)->putValue($row->incentive_designation);
      $rawcells->get($i + 1, 12)->putValue($row->tax_status);
      $rawcells->get($i + 1, 13)->putValue($row->finance_status);
      $rawcells->get($i + 1, 14)->putValue($row->tax_period);
      $rawcells->get($i + 1, 15)->putValue($row->finance_period);
      $rawcells->get($i + 1, 16)->putValue((float)($row->gross_before_interest_local_currency));
      $rawcells->get($i + 1, 17)->putValue((float)($row->interest_local_currency));
      $rawcells->get($i + 1, 18)->putValue((float)($row->gross_local_currency));
      $rawcells->get($i + 1, 19)->putValue((float)($row->gross_usd));
      $rawcells->get($i + 1, 20)->putValue((float)($row->net_usd));
      $rawcells->get($i + 1, 21)->putValue($row->discount_rate);
      $rawcells->get($i + 1, 22)->putValue($row->spot_exchange_rate);
      $rawcells->get($i + 1, 23)->putValue($row->comments);
      $rawcells->get($i + 1, 24)->putValue($row->group);
      $rawcells->get($i + 1, 25)->putValue($row->production_completed);
      $rawcells->get($i + 1, 26)->putValue($row->currency_code);
      $rawcells->get($i + 1, 27)->putValue($row->released);
      $rawcells->get($i + 1, 28)->putValue($row->current_fx_rate);
      $rawcells->get($i + 1, 29)->putValue($row->transaction_category);
      $rawcells->get($i + 1, 30)->putValue($row->expected_cert_received_date);
      $rawcells->get($i + 1, 31)->putValue($row->journal_entry_id);
      $rawcells->get($i + 1, 32)->putValue($row->first_available_tax_period);
      $rawcells->get($i + 1, 33)->putValue($row->tax_period_year);
      $rawcells->get($i + 1, 34)->putValue($row->final_certificate_received_date);
      $rawcells->get($i + 1, 35)->putValue($row->is_non_fox_ind);
      $rawcells->get($i + 1, 36)->putValue($row->division_full_name);
      $rawcells->get($i + 1, 37)->putValue($row->working_title);
    }

    for($row = 1; $row < 691; $row++) {
      for($column = 0; $column < 38; $column++) {
        $numstyle = $rawcells->getStyle();
        $numstyle->setNumber(7);
        $rawcells->get($row, $column)->setStyle($numstyle);
      }
    }

    for($i = 0; $i < 38; $i++) {
      $rawcells->getColumns()->get($i)->setWidth(15);
      $basedatacells->getColumns()->get($i)->setWidth(16);
      $style = $basedatacells->get(0, 0)->getStyle();
      for($j = 0; $j < 28; $j++) {
        $cell = $basedatacells->get($j, $i);
        $style->setNumber(7);
        $style->setTextWrapped(true);
        $cell->setStyle($style);
      }
    }
    $basedatacells->getColumns()->get(0)->setWidth(10);
    $basedatacells->getColumns()->get(1)->setWidth(10);
    $basedatacells->getColumns()->get(2)->setWidth(10);
    $basedatacells->getColumns()->get(3)->setWidth(35);
    $basedatacells->getColumns()->get(4)->setWidth(35);
    $basedatacells->getColumns()->get(5)->setWidth(35);
    $basedatacells->getColumns()->get(6)->setWidth(10);
    $basedatacells->getColumns()->get(13)->setWidth(0);
    $basedatacells->getColumns()->get(15)->setWidth(0);
    $basedatacells->getColumns()->get(34)->setWidth(25);
    $basedatacells->getColumns()->get(35)->setWidth(45);
    $basedatacells->getColumns()->get(36)->setWidth(0);

    $tables = $basedatasheet->getListObjects();
    $index = $tables->add(0, 0, 26, 36, true);
    $table = $tables->get(0);
    $table->setShowTotals(true);
    $table->getListColumns()->get(15)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(16)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(17)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(18)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(19)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(20)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(21)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(22)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(23)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(24)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(25)->setTotalsCalculation(cells\TotalsCalculation::SUM);
    $table->getListColumns()->get(26)->setTotalsCalculation(cells\TotalsCalculation::SUM);

    $table->setTableStyleType(cells\TableStyleType::TABLE_STYLE_MEDIUM_9);

    $reportsheet = $sheets->get(0);
    $reportsheet->freezePanes("B3", 2, 1);
    $reportTables = $reportsheet->getPivotTables();
    $index = $reportTables->add("=DATA!A1:AK27", "A1", "PivotTable1");
    $reportTable = $reportTables->get($index);
    $reportTable->setAutoFormat(true);
    // Setting the PivotTable autoformat type.
    $reportTable->setAutoFormatType(Cells\PivotTableAutoFormatType::REPORT_7);
    $reportTable->setPivotTableStyleType(cells\PivotTableStyleType::PIVOT_TABLE_STYLE_MEDIUM_2);
    // Draging the first field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 3);
    // Draging the third field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 4);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 2);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 5);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 7);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 8);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 9);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 11);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 12);
    // Draging the second field to the row area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::ROW, 29);

    $reportTable->removeField(Cells\PivotFieldType::ROW, "Values");

    // Draging the fifth field to the data area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 18);
    // Draging the fifth field to the data area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 19);
    // Draging the fifth field to the data area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 22);
    // Draging the fifth field to the data area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 23);
    // Draging the fifth field to the data area.
    $reportTable->addFieldToArea(Cells\PivotFieldType::DATA, 36);
    // Setting the number format of the first data field

    $reportTable->setAutoFormat(false);
    $reportTable->setRowGrand(false);
    $reportTable->setColumnGrand(true);
    $reportTable->setShowDrill(false);
    $reportTable->setPrintDrill(false);
    $reportTable->setPrintTitles(false);

    $datafield = $reportTable->getDataField();
    $reportTable->addFieldToArea(Cells\PivotFieldType::COLUMN, $datafield);

    $reportsheet->getAutoFilter()->setRange("B2:J2");
    $reportsheet->getAutoFilter()->refresh();

    $fields = $reportTable->getRowFields();

    $reportTable->getDataFields()->get(0)->setNumber(7);
    $reportTable->getDataFields()->get(1)->setNumber(7);
    $reportTable->getDataFields()->get(2)->setNumber(7);
    $reportTable->getDataFields()->get(3)->setNumber(7);
    $reportTable->getDataFields()->get(4)->setNumber(7);
    $reportTable->getDataFields()->get(0)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(1)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(2)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(3)->setFunction(Cells\ConsolidationFunction::SUM);
    $reportTable->getDataFields()->get(4)->setFunction(Cells\ConsolidationFunction::SUM);

    for($i = 0; $i < 11; $i++) {
      if($i) {
        $fields->get($i)->setAutoSubtotals(false);
      }
      $fields->get($i)->setShowInOutlineForm(false);
      $fields->get($i)->setRepeatItemLabels(true);
      $fields->get($i)->setInsertBlankRow(false);
    }
    $reportsheet->getCells()->getColumns()->get(0)->setWidth(35);
    $reportsheet->getCells()->getColumns()->get(1)->setWidth(35);
    $reportsheet->getCells()->getColumns()->get(2)->setWidth(10);
    $reportsheet->getCells()->getColumns()->get(3)->setWidth(30);

    for($i = 4; $i < 15; $i++) {
      $reportsheet->getCells()->getColumns()->get($i)->setWidth(15);
    }

    $reportsheet->getCells()->getColumns()->get(7)->setWidth(20);
    $reportsheet->getCells()->getColumns()->get(8)->setWidth(20);
    $reportsheet->getCells()->getColumns()->get(14)->setWidth(20);

    $reportTable->setRefreshDataFlag(true);
    $reportTable->refreshData();
    $reportTable->setRefreshDataFlag(false);
    $reportTable->calculateRange();
    $reportTable->calculateData();
    $reportTable->setRefreshDataOnOpeningFile(true);

    $columnCount = $reportsheet->getCells()->getColumns()->getCount();
    for($j = 0; $j < 50; $j++) {
      for($i = 0; $i < java_values($columnCount); $i++) {
        $cell = $reportsheet->getCells()->get($j, $i);
        $style = $cell->getStyle();
        $style->setTextWrapped(true);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::LEFT_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::RIGHT_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::TOP_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border = $style->getBorders()->getByBorderType(cells\BorderType::BOTTOM_BORDER);
        $border->setLineStyle(cells\CellBorderType::NONE);
        $border->setColor(cells\Color::getRed());
        $reportTable->format($j, $i, $style);
        $cell->setStyle($style);
      }
    }

    $reportTable->setRowHeaderCaption("PRODUCTION");

    $rawtables = $rawsheet->getListObjects();
    $index = $rawtables->add(0, 0, 690, 37, true);
    $table = $rawtables->get(0);

    $monetizationsheet = $sheets->get(3);
    $monetizationsheet->freezePanes("B4", 3, 1);
    $monetizationTables = $monetizationsheet->getPivotTables();
    $index = $monetizationTables->add("=RawMonetization!A1:AL691", "A1", "PivotTable2");
    $monetizationTable = $monetizationTables->get($index);

    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 0);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 37);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 1);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 2);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 3);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 4);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 5);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 6);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 7);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 9);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 10);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 11);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::ROW, 23);

    $monetizationTable->addFieldToArea(Cells\PivotFieldType::COLUMN, 12);
    $monetizationTable->addFieldToArea(Cells\PivotFieldType::COLUMN, 14);

    $monetizationTable->addFieldToArea(Cells\PivotFieldType::DATA, 20);

    $monetizationTable->setAutoFormat(false);
    $monetizationTable->setRowGrand(true);
    $monetizationTable->setColumnGrand(true);
    $monetizationTable->setShowDrill(false);
    $monetizationTable->setPrintDrill(false);
    $monetizationTable->setPrintTitles(false);
    $monetizationTable->setGridDropZones(true);

    $monetizationsheet->getAutoFilter()->setRange("A3:M3");
    $monetizationsheet->getAutoFilter()->refresh();
    $monetizationTable->setRowHeaderCaption("PRODUCTION");

    $monetizationTable->getDataFields()->get(0)->setDisplayName("sdf");

    $fields = $monetizationTable->getRowFields();

    $monetizationTable->getDataFields()->get(0)->setNumber(7);

    for($i = 0; $i < 13; $i++) {
      $fields->get($i)->setAutoSubtotals(false);
      $fields->get($i)->setShowInOutlineForm(false);
      $fields->get($i)->setRepeatItemLabels(true);
      $fields->get($i)->setInsertBlankRow(false);
    }
    for($i = 0; $i < 40; $i++) {
      $monetizationsheet->getCells()->getColumns()->get($i)->setWidth(16);
      $rawsheet->getCells()->getColumns()->get($i)->setWidth(15);
    }
    $monetizationsheet->getCells()->getColumns()->get(0)->setWidth(35);
    $monetizationsheet->getCells()->getColumns()->get(1)->setWidth(35);
    $monetizationsheet->getCells()->getColumns()->get(2)->setWidth(9);
    $monetizationsheet->getCells()->getColumns()->get(3)->setWidth(10);
    $monetizationsheet->getCells()->getColumns()->get(4)->setWidth(35);
    $monetizationsheet->getCells()->getColumns()->get(5)->setWidth(10);
    $monetizationsheet->getCells()->getColumns()->get(6)->setWidth(11);
    $monetizationsheet->getCells()->getColumns()->get(7)->setWidth(14);
    $monetizationsheet->getCells()->getColumns()->get(8)->setWidth(14);
    $monetizationsheet->getCells()->getColumns()->get(9)->setWidth(12);
    $monetizationsheet->getCells()->getColumns()->get(10)->setWidth(20);
    $monetizationsheet->getCells()->getColumns()->get(11)->setWidth(20);
    $monetizationsheet->getCells()->getColumns()->get(12)->setWidth(40);

    $rawsheet->getCells()->getColumns()->get(0)->setWidth(37);
    $rawsheet->getCells()->getColumns()->get(1)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(2)->setWidth(15);
    $rawsheet->getCells()->getColumns()->get(3)->setWidth(36);
    $rawsheet->getCells()->getColumns()->get(4)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(5)->setWidth(18);
    $rawsheet->getCells()->getColumns()->get(6)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(7)->setWidth(22);
    $rawsheet->getCells()->getColumns()->get(8)->setWidth(14);
    $rawsheet->getCells()->getColumns()->get(9)->setWidth(24);
    $rawsheet->getCells()->getColumns()->get(10)->setWidth(29);
    $rawsheet->getCells()->getColumns()->get(11)->setWidth(41);
    $rawsheet->getCells()->getColumns()->get(23)->setWidth(214);
    $rawsheet->getCells()->getColumns()->get(36)->setWidth(45);

    $monetizationTable->setRefreshDataFlag(true);
    $monetizationTable->refreshData();
    $monetizationTable->setRefreshDataFlag(false);
    $monetizationTable->calculateRange();
    $monetizationTable->calculateData();
    $monetizationTable->setRefreshDataOnOpeningFile(true);

    $columnCount = $monetizationsheet->getCells()->getColumns()->getCount();
    for($j = 0; $j < 700; $j++) {
      for($i = 0; $i < java_values($columnCount); $i++) {
        $cell = $monetizationsheet->getCells()->get($j, $i);
        $style = $cell->getStyle();
        $style->setTextWrapped(true);
        $monetizationTable->format($j, $i, $style);
      }
    }

    $dirpath = APPPATH . '../tmp_files/';
    $workbook->save($dirpath . "oix_spreadsheet.xlsx");
    $fileurl = $dirpath . "oix_spreadsheet.xlsx";

    header('Pragma: public');
    header("Content-type:application/vnd.ms-excel; charset=utf-8");
    header('Content-Disposition: attachment; filename=' . "oix_spreadsheet.xlsx");
    header('Content-Length: ' . filesize($fileurl));

    readfile($fileurl);

    echo "Document has been saved, please check the output file.";
  }

}



/* End of file admin.php */
/* Location: ./application/controllers/admin/admin.php */
