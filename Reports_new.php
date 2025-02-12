<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports_new extends MY_Controller {

    function __construct() {
        parent::__construct();
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 3000);

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }

        $this->lang->load('reports', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('reports_model_new');
        $this->data['pb'] = array('cash' => lang('cash'), 'CC' => lang('CC'), 'Cheque' => lang('Cheque'), 'paypal_pro' => lang('paypal_pro'), 'stripe' => lang('stripe'), 'gift_card' => lang('gift_card'), 'deposit' => lang('deposit'), 'authorize' => lang('authorize'),);
    }

    function index() {
        $this->sma->checkPermissions();
        $data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['monthly_sales'] = $this->reports_model->getChartData();
        $this->data['stock'] = $this->reports_model->getStockValue();
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('reports')));
        $meta = array('page_title' => lang('reports'), 'bc' => $bc);
        $this->page_construct('reports/index', $meta, $this->data);
    }

    /** new Sales Report **/

    public function sales_extended_report() {
        // $this->sma->checkPermissions('sales-extended-report');
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['users'] = $this->reports_model_new->getStaff();
        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['salegstcount'] = $this->getCountSalesGst();
        $this->data['billers'] = $this->site->getAllCompanies('biller');

        $this->data['page_no'] = 1;
        $this->data['per_page_records'] = 20;

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('sales_report')));
        $meta = array('page_title' => lang('sales_Extended_report'), 'bc' => $bc);
        $this->page_construct('new_reports/sales_extended_report', $meta, $this->data);
    }

    public function sales_extended_report_result($xls = '') {

        $this->load->helper('reports_helper');

        $this->data['search_key'] = $search_key = ($xls) ? $this->input->get('search_key') : $this->input->post('search_key');

        $per_page_records   = ($xls) ? $this->input->get('per_page_records')    : $this->input->post('per_page_records');
        $reference_no       = ($xls) ? $this->input->get('reference_no')        : $this->input->post('reference_no');
        $user               = ($xls) ? $this->input->get('user')                : $this->input->post('user');
        $customer           = ($xls) ? $this->input->get('customer')            : $this->input->post('customer');
        $biller             = ($xls) ? $this->input->get('biller')              : $this->input->post('biller');
        $warehouse          = ($xls) ? $this->input->get('warehouse')           : $this->input->post('warehouse');
        $gstn_no            = ($xls) ? $this->input->get('gstn_no')             : $this->input->post('gstn_no');
        $hsn_code           = ($xls) ? $this->input->get('hsn_code')            : $this->input->post('hsn_code');

        if ($xls) {
            $start_date = !empty($this->input->get('start_date'))   ? ddmmyyyyToyyyymmdd($this->input->get('start_date'))   : '';
            $end_date   = !empty($this->input->get('end_date'))     ? ddmmyyyyToyyyymmdd($this->input->get('end_date'))     : '';
        } else {
            $start_date = !empty($this->input->post('start_date'))  ? ddmmyyyyToyyyymmdd($this->input->post('start_date'))  : '';
            $end_date   = !empty($this->input->post('end_date'))    ? ddmmyyyyToyyyymmdd($this->input->post('end_date'))    : '';

            $page_no = $this->input->post('page_no');
            $page_no = $page_no ? $page_no : 1;
            $per_page_records = $per_page_records ? $per_page_records : 20;
        }

        $select  = "s.id, s.invoice_no, s.date, s.reference_no, s.customer, s.biller, s.seller, s.warehouse_id, s.total, s.grand_total, s.total_tax, s.total_discount, s.sale_status, s.payment_status, s.payment_method, s.total_items, s.paid, s.shipping, s.rounding, s.delivery_status, s.total_weight, s.cgst AS sale_cgst, s.sgst AS sale_sgst, s.igst AS sale_igst, ";
        $select .= "si.sale_id, si.id AS item_id, si.product_id, si.product_code, si.article_code, si.product_name, si.option_id, pv.name AS option_name, si.unit_price, si.quantity, si.item_tax, si.tax_method, si.tax, si.item_discount, si.subtotal, si.real_unit_price, si.unit_quantity, si.hsn_code, si.gst_rate, si.cgst, si.sgst, si.igst, si.item_weight,si.mrp, si.product_unit_code, ";
        $select .= "c.address, c.gstn_no, c.city, c.state_code, c.postal_code, c.phone, c.email ";

        for ($i = 1; $i <= 2; $i++) {

            $this->db->select($select);
            $this->db->from('sales AS s');
            $this->db->join('sale_items AS si', "s.id=si.sale_id", 'right');
            $this->db->join('product_variants AS pv', "pv.id=si.option_id", 'left');
            $this->db->join('companies AS c', "c.id=s.customer_id", 'left');

            if (!empty($search_key)) {

                $search_array = array(
                    "s.invoice_no" => "$search_key",
                    "s.reference_no" => "$search_key",
                    "s.customer" => "$search_key",
                    "s.biller" => "$search_key",
                    "s.payment_status" => "$search_key",
                    "s.payment_method" => "$search_key",
                    "si.product_name" => "$search_key",
                    "pv.name" => "$search_key",
                    "c.gstn_no" => "$search_key",
                    "c.city" => "$search_key",
                    "c.state_code" => "$search_key",
                );

                $this->db->or_like($search_array);
            }
            if (!empty($reference_no)) {

                $this->db->where(['s.reference_no' => $reference_no]);
            }
            if (!empty($user)) {

                $this->db->where(['s.created_by' => $user]);
            }
            if (!empty($customer)) {

                $this->db->where(['s.customer_id' => $customer]);
            }
            if (!empty($biller)) {

                $this->db->where(['s.biller_id' => $biller]);
            }
            if (!empty($warehouse)) {

                $this->db->where(['s.warehouse_id' => $warehouse]);
            }

            if (!empty($start_date) && !empty($end_date)) {

                $this->db->where('date >= ', $start_date . ' 00:00:00');
                $this->db->where('date <=', $end_date . ' 23:59:59');
            }
            if (!empty($gstn_no)) {
                $this->db->where(['c.gstn_no' => $gstn_no]);
            }
            if (!empty($hsn_code)) {

                $this->db->where(['si.hsn_code' => $hsn_code]);
            }

            $this->db->order_by('s.date desc');

            if ($i == 2 && !$xls) {
                $offset = ($page_no - 1) * $per_page_records;
                $this->db->limit($per_page_records, $offset);
            }

            if ($i == 1 && !$xls) {
                $this->db->group_by('s.id');
            }

            $q = 'q' . $i;

            $$q = $this->db->get();
        }//End for

        $this->data['page_no'] = $page_no;
        $this->data['per_page_records'] = $per_page_records;
        $this->data['totalRecord'] = $totalRecord = $q1->num_rows();

        $this->data['totalPages'] = $totalRecord >= $per_page_records ? ceil($totalRecord / $per_page_records) : 1;

        if ($q2->num_rows() > 0) {
            foreach ($q2->result() as $row) {
                if(!$row->id) { continue; }
                $data[$row->id]['sale'] = array(
                    "id" => $row->id,
                    "invoice_no" => $row->invoice_no,
                    "date" => $row->date,
                    "reference_no" => $row->reference_no,
                    "customer" => $row->customer,
                    "address" => $row->address,
                    "gstn_no" => $row->gstn_no,
                    "city" => $row->city,
                    "state_code" => $row->state_code,
                    "postal_code" => $row->postal_code,
                    "phone" => $row->phone,
                    "email" => $row->email,
                    "biller" => $row->biller,
                    "seller" => $row->seller,
                    "warehouse_id" => $row->warehouse_id,
                    "total" => $row->total,
                    "grand_total" => $row->grand_total,
                    "total_tax" => $row->total_tax,
                    "total_discount" => $row->total_discount,
                    "shipping" => $row->shipping,
                    "sale_status" => $row->sale_status,
                    "payment_status" => $row->payment_status,
                    "payment_method" => $row->payment_method,
                    "total_items" => $row->total_items,
                    "paid" => $row->paid,
                    "rounding" => $row->rounding,
                    "delivery_status" => $row->delivery_status,
                    "total_weight" => $row->total_weight,
                    "sale_cgst" => $row->cgst,
                    "sale_sgst" => $row->sgst,
                    "sale_igst" => $row->igst,
                );


                $data[$row->id]['items'][$row->item_id] = array(
                    "sale_id" => $row->sale_id,
                    "product_id" => $row->product_id,
                    "product_code" => $row->product_code,
                    "article_code" => $row->article_code,
                    "product_name" => $row->product_name,
                    "option_id" => $row->option_id,
                    "option_name" => $row->option_name,
                    "unit_price" => $row->unit_price,
                    "quantity" => $row->quantity,
                    "unit_quantity" => $row->unit_quantity,
                    "item_tax" => $row->item_tax,
                    "tax_method" => $row->tax_method,
                    "tax_rate" => $row->tax,
                    "item_discount" => $row->item_discount,
                    "subtotal" => $row->subtotal,
                    "real_unit_price" => $row->real_unit_price,
                    "mrp" => $row->mrp,
                    "unit_code" => $row->product_unit_code,
                    "hsn_code" => $row->hsn_code,
                    "gst_rate" => $row->gst_rate,
                    "cgst" => $row->cgst,
                    "sgst" => $row->sgst,
                    "igst" => $row->igst,
                    "item_weight" => $row->item_weight,
                );
            }

            $this->data['data'] = $data;
        }

        if ($xls) {
            return $data;
        } else {
            $this->load->view('/views/new_reports/sales_extended_report_list', $this->data);
        }
    }

    public function xls_export_sales_extended_report() {

        $data = $this->sales_extended_report_result(TRUE);
                            
        $totalRecord = $data['totalRecord'];

        $this->load->library('excel');
        $this->excel->setActiveSheetIndex(0);

        $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

        $this->excel->getActiveSheet()->getStyle("A1:AY1")->applyFromArray($style);
        $this->excel->getActiveSheet()->mergeCells('A1:AY1');
        $this->excel->getActiveSheet()->SetCellValue('A1', 'Sales Extended Reports');

        $this->excel->getActiveSheet()->setTitle(lang('sales_extended_report'));
        $this->excel->getActiveSheet()->SetCellValue('A2', lang('Sr no'));
        $this->excel->getActiveSheet()->SetCellValue('B2', lang('date'));
        $this->excel->getActiveSheet()->SetCellValue('C2', lang('Invoice No'));
        $this->excel->getActiveSheet()->SetCellValue('D2', lang('reference_no'));
        $this->excel->getActiveSheet()->SetCellValue('E2', lang('biller') . 'Name');
        $this->excel->getActiveSheet()->SetCellValue('F2', lang('customer') . 'Name');
        $this->excel->getActiveSheet()->SetCellValue('G2', lang('customer') . ' Address');
        $this->excel->getActiveSheet()->SetCellValue('H2', lang('phone'));
        $this->excel->getActiveSheet()->SetCellValue('I2', lang('email'));
        $this->excel->getActiveSheet()->SetCellValue('J2', lang('gstn'));
        $this->excel->getActiveSheet()->SetCellValue('K2', lang('warehouse_id'));
        $this->excel->getActiveSheet()->SetCellValue('L2', lang('Seller'));
        $this->excel->getActiveSheet()->SetCellValue('M2', lang('Grand Total (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('N2', lang('Taxable Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('O2', lang('Tax Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('P2', lang('Discount Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('Q2', lang('Shipping Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('R2', lang('Paid (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('S2', lang('Balance (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('T2', lang('Payment Method'));
        $this->excel->getActiveSheet()->SetCellValue('U2', lang('Payment Status'));
        $this->excel->getActiveSheet()->SetCellValue('V2', lang('Sale Status'));
        $this->excel->getActiveSheet()->SetCellValue('W2', lang('Delivery Status'));
        $this->excel->getActiveSheet()->SetCellValue('X2', lang('Sale CGST (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('Y2', lang('Sale SGST (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('Z2', lang('Sale IGST (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('AA2', lang('Total Weight') . '(Kg)');
        $this->excel->getActiveSheet()->SetCellValue('AB2', lang('Total Items'));

        //Sales Items Detail
        $this->excel->getActiveSheet()->SetCellValue('AC2', lang('Item Sr'));
        $this->excel->getActiveSheet()->SetCellValue('AD2', lang('product_id'));
        $this->excel->getActiveSheet()->SetCellValue('AE2', lang('product_code'));
        $this->excel->getActiveSheet()->SetCellValue('AF2', lang('product_name'));
        $this->excel->getActiveSheet()->SetCellValue('AG2', lang('Varient Id'));
        $this->excel->getActiveSheet()->SetCellValue('AH2', lang('Varient'));
        $this->excel->getActiveSheet()->SetCellValue('AI2', lang('hsn_code'));
        $this->excel->getActiveSheet()->SetCellValue('AJ2', lang('unit_price') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AK2', lang('quantity'));
        $this->excel->getActiveSheet()->SetCellValue('AL2', lang('unit'));

        $this->excel->getActiveSheet()->SetCellValue('AM2', lang('mrp') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AN2', lang('item_discount') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AO2', lang('tax_method'));
        $this->excel->getActiveSheet()->SetCellValue('AP2', lang('tax_rate') . '(%)');
        $this->excel->getActiveSheet()->SetCellValue('AQ2', lang('item_tax') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AR2', lang('Subtotal (Rs)'));

        $this->excel->getActiveSheet()->SetCellValue('AS2', lang('CGST Rate (%)'));
        $this->excel->getActiveSheet()->SetCellValue('AT2', lang('CGST') . '(Rs)');

        $this->excel->getActiveSheet()->SetCellValue('AU2', lang('SGST Rate (%)'));
        $this->excel->getActiveSheet()->SetCellValue('AV2', lang('SGST') . '(Rs)');

        $this->excel->getActiveSheet()->SetCellValue('AW2', lang('IGST (%)'));
        $this->excel->getActiveSheet()->SetCellValue('AX2', lang('IGST') . '(Rs)');

        $this->excel->getActiveSheet()->SetCellValue('AY2', lang('item_weight') . '(Kg)');

        $row = 3;
        $total_cgst = 0;
        $total_sgst = 0;
        $total_igst = 0;
        $total_weight = 0;
        $total_items = 0;
        $total = 0;
        $paid = 0;
        $total_balance = 0;
        $total_taxable_amt = 0;
        $totalSubtotal = 0;
        $total_discount = 0;
        $total_shipping = 0;
        $sr = 0;

        $this->excel->getActiveSheet()->getStyle("A" . $row . ":AY" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

        foreach ($data as $sale_id => $salesdata) {
            $sale_data = (object) $salesdata['sale'];
            $address = $sale_data->address;
            $city = $sale_data->city;
            $state_code = $sale_data->state_code;
            $postal_code = $sale_data->postal_code;

            $customerAddress = "$address $city $state_code $postal_code";

            $taxable_amt = $sale_data->grand_total - ($sale_data->total_tax + $sale_data->shipping + $sale_data->rounding);
            $balance = $sale_data->grand_total - $sale_data->paid;

            $sr++;
            $this->excel->getActiveSheet()->SetCellValue('A' . $row, ($sr));
            $this->excel->getActiveSheet()->SetCellValue('B' . $row, $this->sma->hrld($sale_data->date));
            $this->excel->getActiveSheet()->SetCellValue('C' . $row, $sale_data->invoice_no);
            $this->excel->getActiveSheet()->SetCellValue('D' . $row, $sale_data->reference_no);
            $this->excel->getActiveSheet()->SetCellValue('E' . $row, $sale_data->biller);
            $this->excel->getActiveSheet()->SetCellValue('F' . $row, $sale_data->customer);
            $this->excel->getActiveSheet()->SetCellValue('G' . $row, $customerAddress);
            $this->excel->getActiveSheet()->SetCellValue('H' . $row, $sale_data->phone);
            $this->excel->getActiveSheet()->SetCellValue('I' . $row, $sale_data->email);
            $this->excel->getActiveSheet()->SetCellValue('J' . $row, $sale_data->gstn_no);
            $this->excel->getActiveSheet()->SetCellValue('K' . $row, $sale_data->warehouse_id);
            $this->excel->getActiveSheet()->SetCellValue('L' . $row, $sale_data->seller);
            $this->excel->getActiveSheet()->SetCellValue('M' . $row, $this->sma->formatMoney($sale_data->grand_total));
            $this->excel->getActiveSheet()->SetCellValue('N' . $row, $this->sma->formatMoney($taxable_amt));
            $this->excel->getActiveSheet()->SetCellValue('O' . $row, $this->sma->formatMoney($sale_data->total_tax));
            $this->excel->getActiveSheet()->SetCellValue('P' . $row, $this->sma->formatMoney($sale_data->total_discount));
            $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $this->sma->formatMoney($sale_data->shipping));
            $this->excel->getActiveSheet()->SetCellValue('R' . $row, $this->sma->formatMoney($sale_data->paid));
            $this->excel->getActiveSheet()->SetCellValue('S' . $row, $this->sma->formatMoney($balance));            
            $this->excel->getActiveSheet()->SetCellValue('T' . $row, $sale_data->payment_method);
            $this->excel->getActiveSheet()->SetCellValue('U' . $row, $sale_data->payment_status);
            $this->excel->getActiveSheet()->SetCellValue('V' . $row, $sale_data->sale_status);
            $this->excel->getActiveSheet()->SetCellValue('W' . $row, $sale_data->delivery_status);
            $this->excel->getActiveSheet()->SetCellValue('X' . $row, $this->sma->formatMoney($sale_data->sale_cgst));
            $this->excel->getActiveSheet()->SetCellValue('Y' . $row, $this->sma->formatMoney($sale_data->sale_sgst));
            $this->excel->getActiveSheet()->SetCellValue('Z' .  $row, $this->sma->formatMoney ($sale_data->sale_igst));
            $this->excel->getActiveSheet()->SetCellValue('AA' . $row, $sale_data->total_weight);
            $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $sale_data->total_items);

            $sale_items = (object) $salesdata['items'];

            $total += $sale_data->grand_total;
            $paid += $sale_data->paid;
            $total_tax += $sale_data->total_tax;
            $total_balance += $balance;
            $total_taxable_amt += $taxable_amt;
            $total_discount += $sale_data->total_discount;
            $total_shipping += $sale_data->shipping;
            $total_cgst += $sale_data->sale_cgst;
            $total_sgst += $sale_data->sale_sgst;
            $total_igst += $sale_data->sale_igst;
            $total_weight += $sale_data->total_weight;
            $total_items += $sale_data->total_items;

            if (!empty($sale_items) && count($sale_items)) {
                $item_sr = 0;
                foreach ($sale_items as $saleitem_id => $salesItemsData) {

                    $row = $item_sr ? $row + 1 : $row;

                    $sales_items_data = (object) $salesItemsData;
                    $item_sr++;
                    // $VAT = $this->reports_model_new->getVatCess($sale_data->sale_id, "VAT");
                    // $CESS = $this->reports_model_new->getVatCess($sale_data->sale_id, "CESS");

                    $this->excel->getActiveSheet()->SetCellValue('AC' . $row, $item_sr);
                    $this->excel->getActiveSheet()->SetCellValue('AD' . $row, $sales_items_data->product_id);
                    $this->excel->getActiveSheet()->SetCellValue('AE' . $row, $sales_items_data->product_code);
                    $this->excel->getActiveSheet()->SetCellValue('AF' . $row, $sales_items_data->product_name);
                    $this->excel->getActiveSheet()->SetCellValue('AG' . $row, $sales_items_data->option_id);
                    $this->excel->getActiveSheet()->SetCellValue('AH' . $row, $sales_items_data->option_name);
                    $this->excel->getActiveSheet()->SetCellValue('AI' . $row, $sales_items_data->hsn_code);
                    $this->excel->getActiveSheet()->SetCellValue('AJ' . $row, $this->sma->formatMoney($sales_items_data->unit_price));
                    $this->excel->getActiveSheet()->SetCellValue('AK' . $row, $sales_items_data->unit_quantity);
                    $this->excel->getActiveSheet()->SetCellValue('AL' . $row, ($sales_items_data->unit_code));
                    $this->excel->getActiveSheet()->SetCellValue('AM' . $row, $this->sma->formatMoney($sales_items_data->mrp));
                    $this->excel->getActiveSheet()->SetCellValue('AN' . $row, $this->sma->formatMoney($sales_items_data->item_discount));
                    $this->excel->getActiveSheet()->SetCellValue('AO' . $row, ($sales_items_data->tax_method));
                    $this->excel->getActiveSheet()->SetCellValue('AP' . $row, ($sales_items_data->tax_rate));
                    $this->excel->getActiveSheet()->SetCellValue('AQ' . $row, ($sales_items_data->item_tax));
                    $this->excel->getActiveSheet()->SetCellValue('AR' . $row, ($sales_items_data->subtotal));

                    $gst_rate = $sales_items_data->gst_rate;
                    if ($sales_items_data->igst) {
                        $igst_rate = $gst_rate;
                    } else {
                        $cgst_rate = $sgst_rate = $gst_rate;
                    }

                    $this->excel->getActiveSheet()->SetCellValue('AS' . $row, $this->sma->formatMoney($cgst_rate));
                    $this->excel->getActiveSheet()->SetCellValue('AT' . $row, $this->sma->formatMoney($sales_items_data->cgst));
                    $this->excel->getActiveSheet()->SetCellValue('AU' . $row, $sgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('AV' . $row, $this->sma->formatMoney($sales_items_data->sgst));
                    $this->excel->getActiveSheet()->SetCellValue('AW' . $row, $igst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('AX' . $row, $this->sma->formatMoney($sales_items_data->igst));
                    $this->excel->getActiveSheet()->SetCellValue('AY' . $row, $sales_items_data->item_weight);

                    $totalSubtotal += $sales_items_data->subtotal;
                }//end foreach
            }//end if.
            $row++;
        }//end outer foreach

        $this->excel->getActiveSheet()->getStyle("A" . $row . ":AY" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
        $this->excel->getActiveSheet()->getStyle("A" . $row . ":AY" . $row)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

        $this->excel->getActiveSheet()->SetCellValue('H' . $row, 'Total Calculated Value:');
        $this->excel->getActiveSheet()->SetCellValue('M' . $row, $total);
        $this->excel->getActiveSheet()->SetCellValue('N' . $row, $total_taxable_amt);
        $this->excel->getActiveSheet()->SetCellValue('O' . $row, $total_tax);
        $this->excel->getActiveSheet()->SetCellValue('P' . $row, $total_discount);
        $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $total_shipping);
        $this->excel->getActiveSheet()->SetCellValue('R' . $row, $paid);
        $this->excel->getActiveSheet()->SetCellValue('S' . $row, $total_balance);
        $this->excel->getActiveSheet()->SetCellValue('X' . $row, $total_cgst);
        $this->excel->getActiveSheet()->SetCellValue('Y' . $row, $total_sgst);
        $this->excel->getActiveSheet()->SetCellValue('Z' . $row, $total_igst);
        $this->excel->getActiveSheet()->SetCellValue('AA' . $row, $total_weight);
        $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $total_items);

        $filename = 'sales_extended_report_' . time();
        $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $this->excel->getActiveSheet()->getStyle('F2:F' . $row + 1)->getAlignment()->setWrapText(TRUE);

        $rowend = $row + 1;
        $this->excel->getActiveSheet()->SetCellValue('A' . $rowend, lang('Sr no'));
        $this->excel->getActiveSheet()->SetCellValue('B' . $rowend, lang('date'));
        $this->excel->getActiveSheet()->SetCellValue('C' . $rowend, lang('Invoice No'));
        $this->excel->getActiveSheet()->SetCellValue('D' . $rowend, lang('reference_no'));
        $this->excel->getActiveSheet()->SetCellValue('E' . $rowend, lang('biller') . 'Name');
        $this->excel->getActiveSheet()->SetCellValue('F' . $rowend, lang('customer') . 'Name');
        $this->excel->getActiveSheet()->SetCellValue('G' . $rowend, lang('customer') . ' Address');
        $this->excel->getActiveSheet()->SetCellValue('H' . $rowend, lang('phone'));
        $this->excel->getActiveSheet()->SetCellValue('I' . $rowend, lang('email'));
        $this->excel->getActiveSheet()->SetCellValue('J' . $rowend, lang('gstn'));
        $this->excel->getActiveSheet()->SetCellValue('K' . $rowend, lang('warehouse_id'));
        $this->excel->getActiveSheet()->SetCellValue('L' . $rowend, lang('Seller'));
        $this->excel->getActiveSheet()->SetCellValue('M' . $rowend, lang('Grand Total (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('N' . $rowend, lang('Taxable Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('O' . $rowend, lang('Tax Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('P' . $rowend, lang('Discount Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('Q' . $rowend, lang('Shipping Amount (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('R' . $rowend, lang('Paid (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('S' . $rowend, lang('Balance (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('T' . $rowend, lang('Payment Method'));
        $this->excel->getActiveSheet()->SetCellValue('U' . $rowend, lang('Payment Status'));
        $this->excel->getActiveSheet()->SetCellValue('V' . $rowend, lang('Sale Status'));
        $this->excel->getActiveSheet()->SetCellValue('W' . $rowend, lang('Delivery Status'));
        $this->excel->getActiveSheet()->SetCellValue('X' . $rowend, lang('Sale CGST (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('Y' . $rowend, lang('Sale SGST (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('Z' . $rowend, lang('Sale IGST (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('AA' . $rowend, lang('Total Weight') . '(Kg)');
        $this->excel->getActiveSheet()->SetCellValue('AB' . $rowend, lang('Total Items'));

        //Sales Items Detail
        $this->excel->getActiveSheet()->SetCellValue('AC' . $rowend, lang('Item Sr'));
        $this->excel->getActiveSheet()->SetCellValue('AD' . $rowend, lang('product_id'));
        $this->excel->getActiveSheet()->SetCellValue('AE' . $rowend, lang('product_code'));
        $this->excel->getActiveSheet()->SetCellValue('AF' . $rowend, lang('product_name'));
        $this->excel->getActiveSheet()->SetCellValue('AG' . $rowend, lang('Varient Id'));
        $this->excel->getActiveSheet()->SetCellValue('AH' . $rowend, lang('Varient'));
        $this->excel->getActiveSheet()->SetCellValue('AI' . $rowend, lang('hsn_code'));
        $this->excel->getActiveSheet()->SetCellValue('AJ' . $rowend, lang('unit_price') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AK' . $rowend, lang('quantity'));
        $this->excel->getActiveSheet()->SetCellValue('AL' . $rowend, lang('unit'));
        $this->excel->getActiveSheet()->SetCellValue('AM' . $rowend, lang('mrp') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AN' . $rowend, lang('item_discount') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AO' . $rowend, lang('tax_method'));
        $this->excel->getActiveSheet()->SetCellValue('AP' . $rowend, lang('tax_rate') . '(%)');
        $this->excel->getActiveSheet()->SetCellValue('AQ' . $rowend, lang('item_tax') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AR' . $rowend, lang('Subtotal (Rs)'));
        $this->excel->getActiveSheet()->SetCellValue('AS' . $rowend, lang('CGST Rate (%)'));
        $this->excel->getActiveSheet()->SetCellValue('AT' . $rowend, lang('CGST') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AU' . $rowend, lang('SGST Rate (%)'));
        $this->excel->getActiveSheet()->SetCellValue('AV' . $rowend, lang('SGST') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AW' . $rowend, lang('IGST (%)'));
        $this->excel->getActiveSheet()->SetCellValue('AX' . $rowend, lang('IGST') . '(Rs)');
        $this->excel->getActiveSheet()->SetCellValue('AY' . $rowend, lang('item_weight') . '(Kg)');

        ob_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        ob_clean();
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
        $objWriter->save('php://output');
        exit();
    }

    public function sales_gst_reportnew() {
        // $this->sma->checkPermissions('sales');
        // $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['users'] = $this->reports_model_new->getStaff();
        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['customer'] = $this->reports_model_new->getCustomerCompanies();
        $this->data['salegstcount'] = $this->getCountSalesGst();
        $this->data['billers'] = $this->site->getAllCompanies('biller');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('sales_report')));
        $meta = array('page_title' => lang('sales_New_report'), 'bc' => $bc);
        $this->page_construct('new_reports/sales_customer_reportnew', $meta, $this->data);
    }

    public function getSalesReportCnew($pdf = NULL, $xls = NULL, $img = NULL) {
        // $this->sma->checkPermissions('sales', TRUE);
        $SalesIds = '';
        $product = $this->input->get('product') ? $this->input->get('product') : NULL;
        $user = $this->input->get('user') ? $this->input->get('user') : NULL;
        $customer = $this->input->get('customer') ? $this->input->get('customer') : NULL;
        $biller = $this->input->get('biller') ? $this->input->get('biller') : NULL;
        $warehouse = $this->input->get('warehouse') ? $this->input->get('warehouse') : NULL;
        $reference_no = $this->input->get('reference_no') ? $this->input->get('reference_no') : NULL;
        $start_date = $this->input->get('start_date') ? $this->input->get('start_date') : NULL;
        $end_date = $this->input->get('end_date') ? $this->input->get('end_date') : NULL;
        $serial = $this->input->get('serial') ? $this->input->get('serial') : NULL;
        $gstn_opt = $this->input->get('gstn_opt') ? $this->input->get('gstn_opt') : NULL;
        $gstn_no = $this->input->get('gstn_no') ? $this->input->get('gstn_no') : NULL;
        $hsn_code = $this->input->get('hsn_code') ? $this->input->get('hsn_code') : NULL;
        $max_export_sales = $this->input->get('max_export_sales') ? $this->input->get('max_export_sales') : '0-200'; //0-500
        if (!empty($hsn_code)) {
            $SalesIds = $this->reports_model_new->getSaleIdByHsn($hsn_code);
        }
        if ($start_date) {
            $start_date = $this->sma->fld($start_date);
            $end_date = $this->sma->fld($end_date);
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user = $this->session->userdata('user_id');
        }

        if ($pdf || $xls || $img) {
            list($start, $limit) = explode('-', $max_export_sales);
            $this->db->select("sales.id as sale_id,sales.date, sales.reference_no, sales.invoice_no,sales.biller, sales.customer,sales.product_tax as total_tax , 
                IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,   comp.address,  comp.city,  comp.phone,  comp.email , 
                sma_sales.grand_total as grand_total, sma_sales.total_discount ,sma_sales.paid as paid,sma_sales.rounding as rounding,GROUP_CONCAT(' ',sma_payments.paid_by), sales.payment_status", FALSE)
                    ->from('sales')
                    ->join('companies comp', 'sales.customer_id=comp.id', 'left')
                    ->join('sma_payments ', 'sales.id=sma_payments.sale_id', 'left')
                    ->limit($limit, $start)
                    ->group_by('sales.id')
                    ->order_by('sales.date desc');
            if ($this->Owner || $this->Admin) {
                if ($user) {
                    $this->db->where('sales.created_by', $user);
                }
            } else {
                if ($this->session->userdata('view_right') == '0') {
                    if ($user) {
                        $this->db->where('sales.created_by', $user);
                    }
                }
            }
            if ($biller) {
                $this->db->where('sales.biller_id', $biller);
            }
            if ($customer) {
                $this->db->where('sales.customer_id', $customer);
            }
            if ($warehouse) {
                $getwarehouse = str_replace("_", ",", $warehouse);
                $this->db->where('sales.warehouse_id IN(' . $getwarehouse . ')');
            }
            if ($reference_no) {
                $this->db->like('sales.reference_no', $reference_no, 'both');
            }
            if ($start_date) {
                $this->db->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');
            }
            if ($gstn_opt) {
                switch ($gstn_opt) {
                    case '-1':
                        $this->db->where("comp.gstn_no IS NULL OR comp.gstn_no = '' ");
                        break;
                    case '1':
                        $this->db->where("comp.gstn_no IS NOT NULL and comp.gstn_no != '' ");
                        break;
                    default:
                        break;
                }
            }
            if ($gstn_no) {
                $this->db->where("comp.gstn_no = '" . $gstn_no . "' ");
            }
            if ($SalesIds) {
                $this->db->where('sales.id in (' . $SalesIds . ')');
            }
            $this->db->group_by('sales.id');
            $q = $this->db->get();
            $data_sales = [];
            $saleCount = 0;
            if ($q->num_rows() > 0) {
                foreach (($q->result()) as $row) {
                    if (!in_array($row->sale_id, $data_sales)) {
                        $data_sales[] = $row->sale_id;
                    }
                    //Sales Details
                    $data[$row->sale_id]['sale_id'] = $row->sale_id;
                    $data[$row->sale_id]['date'] = $row->date;
                    $data[$row->sale_id]['reference_no'] = $row->reference_no;
                    $data[$row->sale_id]['invoice_no'] = $row->invoice_no;
                    $data[$row->sale_id]['biller'] = $row->biller;
                    $data[$row->sale_id]['customer'] = $row->customer;
                    $cantact = ($row->address) ? $row->address : '';
                    $cantact .= ($row->city) ? ' City:' . $row->city : '';
                    $cantact .= ($row->phone) ? ' Phone:' . $row->phone : '';
                    $cantact .= ($row->email) ? ' Email:' . $row->email : '';
                    $data[$row->sale_id]['address'] = $cantact;
                    $data[$row->sale_id]['gstn_no'] = $row->gstn_no;
                    $data[$row->sale_id]['grand_total'] = $row->grand_total + $row->rounding;
                    $data[$row->sale_id]['taxable_amt'] = $row->grand_total - $row->total_tax;
                    $data[$row->sale_id]['total_tax'] = $row->total_tax;
                    $data[$row->sale_id]['paid'] = $row->paid;
                    $data[$row->sale_id]['balance'] = $row->grand_total + $row->rounding - $row->paid;
                    $data[$row->sale_id]['paid_by'] = $row->paid_by;
                    $data[$row->sale_id]['payment_status'] = $row->payment_status;
                    $data[$row->sale_id]['total_discount'] = $row->total_discount;
                }//endforeach

                $uniqueSalesIds = array_unique($data_sales);
                //Get Sale items details  $SalesItems = $this->reports_model->getSalesItemsBySaleIds($uniqueSalesIds, $product);

                $SalesItems = $this->reports_model_new->getSalesItemsBySaleIds($uniqueSalesIds, $product);
                // print_r($SalesItems);exit;
                if (is_array($SalesItems)) {
                    foreach ($SalesItems as $key => $SaleItemsRow) {
                        $id = $SaleItemsRow->items_id;
                        $datacgst = $this->reports_model_new->getSalesItemAsGst($id, 'cgst'); //CGST 
                        //print_r($datacgst);
                        // echo $datacgst[0]->sumgst;
                        $datasgst = $this->reports_model_new->getSalesItemAsGst($id, 'sgst'); //CGST 
                        $dataigst = $this->reports_model_new->getSalesItemAsGst($id, 'igst'); //CGST 
                        //Sales Items Details
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['items_id'] = $SaleItemsRow->items_id;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['code'] = $SaleItemsRow->product_code;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['name'] = $SaleItemsRow->product_name;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['variantname'] = $SaleItemsRow->variant_name;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['gst'] = ($SaleItemsRow->gst) ? substr($SaleItemsRow->gst, 0, -3) : 0;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['hsn_code'] = $SaleItemsRow->hsn_code;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['quantity'] = $SaleItemsRow->quantity;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['unit'] = $SaleItemsRow->unit;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['tax_amt'] = ($SaleItemsRow->item_tax) ? $SaleItemsRow->item_tax : 0;
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['CGST'] = ($datacgst[0]->totalgst) ? 'Rs. ' . $datacgst[0]->totalgst : '0.00';
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['CGST_rate'] = ($datacgst[0]->gstrrate) ? $datacgst[0]->gstrrate . '%' : '0.00';
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['SGST'] = ($datasgst[0]->totalgst) ? 'Rs. ' . $datasgst[0]->totalgst : '0.00';
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['SGST_rate'] = ($datasgst[0]->gstrrate) ? $datasgst[0]->gstrrate . '%' : '0.00';

                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['IGST'] = ($dataigst[0]->totalgst) ? 'Rs. ' . $dataigst[0]->totalgst : '0.00';
                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['IGST_rate'] = ($dataigst[0]->gstrrate) ? $dataigst[0]->gstrrate . '%' : '0.00';

                        $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['subtotal'] = $SaleItemsRow->subtotal;
                    }//end foreach
                }//end if
            } else {
                $data = NULL;
            }

            if (!empty($data)) {
                $this->load->library('excel');
                $this->excel->setActiveSheetIndex(0);

                $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                $this->excel->getActiveSheet()->getStyle("A1:AG1")->applyFromArray($style);
                $this->excel->getActiveSheet()->mergeCells('A1:AG1');
                $this->excel->getActiveSheet()->SetCellValue('A1', 'GST Sales Report');

                $this->excel->getActiveSheet()->setTitle(lang('sales_report'));
                $this->excel->getActiveSheet()->SetCellValue('A2', lang('sr no'));
                $this->excel->getActiveSheet()->SetCellValue('B2', lang('date'));
                $this->excel->getActiveSheet()->SetCellValue('C2', lang('Invoice No'));
                $this->excel->getActiveSheet()->SetCellValue('D2', lang('reference_no'));
                $this->excel->getActiveSheet()->SetCellValue('E2', lang('biller'));
                $this->excel->getActiveSheet()->SetCellValue('F2', lang('customer'));
                $this->excel->getActiveSheet()->SetCellValue('G2', lang('customer') . ' Contacts');
                $this->excel->getActiveSheet()->SetCellValue('H2', lang('gstn'));
                $this->excel->getActiveSheet()->SetCellValue('I2', lang('Grand Total (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('J2', lang('Discount (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('K2', lang('Taxable Amount (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('L2', lang('Tax Amount (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('M2', lang('Paid (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('N2', lang('Balance (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('O2', lang('Payment Method'));
                $this->excel->getActiveSheet()->SetCellValue('P2', lang('Payment Status'));

                //Sales Items Detail
                $this->excel->getActiveSheet()->SetCellValue('Q2', lang('product_code'));
                $this->excel->getActiveSheet()->SetCellValue('R2', lang('product_name'));
                $this->excel->getActiveSheet()->SetCellValue('S2', lang('Varient'));
                $this->excel->getActiveSheet()->SetCellValue('T2', lang('hsn_code'));
                $this->excel->getActiveSheet()->SetCellValue('U2', lang('quantity'));
                $this->excel->getActiveSheet()->SetCellValue('V2', lang('unit'));
                $this->excel->getActiveSheet()->SetCellValue('W2', lang('GST Rate (%)'));
                $this->excel->getActiveSheet()->SetCellValue('X2', lang('CGST'));
                $this->excel->getActiveSheet()->SetCellValue('Y2', lang('CGST Rate (%)'));
                $this->excel->getActiveSheet()->SetCellValue('Z2', lang('SGST'));
                $this->excel->getActiveSheet()->SetCellValue('AA2', lang('SGST Rate (%)'));
                $this->excel->getActiveSheet()->SetCellValue('AB2', lang('IGST'));
                $this->excel->getActiveSheet()->SetCellValue('AC2', lang('IGST (%)'));
                $this->excel->getActiveSheet()->SetCellValue('AB2', lang('Subtotal (Rs)'));
                $this->excel->getActiveSheet()->SetCellValue('AE2', lang('VAT'));
                $this->excel->getActiveSheet()->SetCellValue('AF2', lang('VAT Rate (%)'));
                $this->excel->getActiveSheet()->SetCellValue('AG2', lang('CESS'));
                $this->excel->getActiveSheet()->SetCellValue('AH2', lang('CESS Rate (%)'));


                $row = 3;
                $cgst = 0;
                $sgst = 0;
                $igst = 0;
                $vat = 0;
                $cess = 0;
                $total = 0;
                $total_disocunt = 0;
                $paid = 0;
                $balance = 0;
                $total_taxable_amt = 0;
                $totalSubtotal = 0;
                $sr = ($start) ? ($start - 1) : 0;

                $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

                foreach ($data as $sale_id => $salesdata) {
                    $sale_data = (object) $salesdata;

                    $sr++;
                    $this->excel->getActiveSheet()->SetCellValue('A' . $row, ($sr));
                    $this->excel->getActiveSheet()->SetCellValue('B' . $row, $this->sma->hrld($sale_data->date));
                    $this->excel->getActiveSheet()->SetCellValue('C' . $row, $sale_data->invoice_no);
                    $this->excel->getActiveSheet()->SetCellValue('D' . $row, $sale_data->reference_no);
                    $this->excel->getActiveSheet()->SetCellValue('E' . $row, $sale_data->biller);
                    $this->excel->getActiveSheet()->SetCellValue('F' . $row, $sale_data->customer);
                    $this->excel->getActiveSheet()->SetCellValue('G' . $row, $sale_data->address);
                    $this->excel->getActiveSheet()->SetCellValue('H' . $row, $sale_data->gstn_no);
                    $this->excel->getActiveSheet()->SetCellValue('I' . $row, $sale_data->grand_total);
                    $this->excel->getActiveSheet()->SetCellValue('J' . $row, $sale_data->total_discount); 
                    $this->excel->getActiveSheet()->SetCellValue('K' . $row, $sale_data->taxable_amt);
                    $this->excel->getActiveSheet()->SetCellValue('L' . $row, $sale_data->total_tax);
                    $this->excel->getActiveSheet()->SetCellValue('M' . $row, $sale_data->paid);
                    $this->excel->getActiveSheet()->SetCellValue('N' . $row, $sale_data->balance);
                    $this->excel->getActiveSheet()->SetCellValue('O' . $row, $this->reports_model_new->getpaymentmode($sale_data->sale_id));
                    $this->excel->getActiveSheet()->SetCellValue('P' . $row, $sale_data->payment_status);
                    if (!empty($sale_data->items)) {
                        foreach ($sale_data->items as $saleitem_id => $salesItemsData) {

                            $sales_items_data = (object) $salesItemsData;

                            $VAT = $this->reports_model_new->getVatCess($sale_data->sale_id, "VAT");
                            $CESS = $this->reports_model_new->getVatCess($sale_data->sale_id, "CESS");

                            $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $sales_items_data->code);
                            $this->excel->getActiveSheet()->SetCellValue('R' . $row, $sales_items_data->name);
                            $this->excel->getActiveSheet()->SetCellValue('S' . $row, $sales_items_data->variantname);
                            $this->excel->getActiveSheet()->SetCellValue('T' . $row, $sales_items_data->hsn_code);
                            $this->excel->getActiveSheet()->SetCellValue('U' . $row, $sales_items_data->quantity);
                            $this->excel->getActiveSheet()->SetCellValue('V' . $row, lang($sales_items_data->unit));
                            $this->excel->getActiveSheet()->SetCellValue('W' . $row, $sales_items_data->gst);

                            $this->excel->getActiveSheet()->SetCellValue('X' . $row, $sales_items_data->CGST);
                            $this->excel->getActiveSheet()->SetCellValue('Y' . $row, $sales_items_data->CGST_rate);

                            $this->excel->getActiveSheet()->SetCellValue('Z' . $row, $sales_items_data->SGST);
                            $this->excel->getActiveSheet()->SetCellValue('AA' . $row, $sales_items_data->SGST_rate);

                            $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $sales_items_data->IGST);
                            $this->excel->getActiveSheet()->SetCellValue('AC' . $row, $sales_items_data->IGST_rate);
                            $this->excel->getActiveSheet()->SetCellValue('AD' . $row, $sales_items_data->subtotal);

//                            $this->excel->getActiveSheet()->SetCellValue('Z' . $row, $sales_items_data->SGST);
//                            $this->excel->getActiveSheet()->SetCellValue('AA' . $row, $sales_items_data->IGST != '0.0000' ? $sales_items_data->gst_rate : 0 );
//                            $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $sales_items_data->IGST);
                            // $cgst += $sales_items_data->CGST;
                            //$sgst += $sales_items_data->SGST;
                            //$igst += $sales_items_data->IGST;
                            $totalSubtotal += $sales_items_data->subtotal;
                            $row++;
                        }//end foreach
                    }//end if.
                    //exit;
                    $this->excel->getActiveSheet()->SetCellValue('AE' . $row, ($VAT->taxamount) ? 'Rs. ' . $VAT->taxamount : '0.00' );
                    $this->excel->getActiveSheet()->SetCellValue('AF' . $row, ($VAT->taxrate) ? $VAT->taxrate . '%' : '0.00' );
                    $this->excel->getActiveSheet()->SetCellValue('AG' . $row, ($CESS->taxamount) ? 'Rs. ' . $CESS->taxamount : '0.00' );
                    $this->excel->getActiveSheet()->SetCellValue('AH' . $row, ($CESS->taxrate) ? $CESS->taxrate . '%' : '0.00' );
                    $this->excel->getActiveSheet()->getStyle("A" . $row . ":AH" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);

                    $total += $sale_data->grand_total;
                    $total_disocunt  += $sale_data->total_discount;            
                    $paid += $sale_data->paid;
                    $total_tax += $sale_data->total_tax;
                    $balance += $sale_data->balance;
                    $total_taxable_amt += $sale_data->taxable_amt;
                }//end outer foreach

                $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
                $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

                $this->excel->getActiveSheet()->SetCellValue('H' . $row, 'Total Calculated Value:');
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $total);
                 $this->excel->getActiveSheet()->SetCellValue('J' . $row, $total_disocunt);  
                $this->excel->getActiveSheet()->SetCellValue('K' . $row, $total_taxable_amt);
                $this->excel->getActiveSheet()->SetCellValue('L' . $row, $total_tax);
                $this->excel->getActiveSheet()->SetCellValue('M' . $row, $paid);
                $this->excel->getActiveSheet()->SetCellValue('N' . $row, $balance);
//                $this->excel->getActiveSheet()->SetCellValue('W' . $row, 'Total CGST:');
//                $this->excel->getActiveSheet()->SetCellValue('X' . $row, $cgst);
//                $this->excel->getActiveSheet()->SetCellValue('Y' . $row, 'Total SGST:');
//                $this->excel->getActiveSheet()->SetCellValue('Z' . $row, $sgst);
//                $this->excel->getActiveSheet()->SetCellValue('AA' . $row, 'Total IGST:');
//                $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $igst);
                $this->excel->getActiveSheet()->SetCellValue('AD' . $row, $totalSubtotal);

                $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
                $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
                $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(40);
                $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('O')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('P')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('Q')->setWidth(12);
                $this->excel->getActiveSheet()->getColumnDimension('R')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('S')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('T')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('U')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('V')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('W')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('X')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('Y')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('Z')->setWidth(10);
                $this->excel->getActiveSheet()->getColumnDimension('AA')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AB')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AC')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AD')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AE')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AF')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AG')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('AD')->setWidth(15);

                $filename = 'sales_gst_new_report_' . $max_export_sales . '_' . time();
                $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                if ($pdf) {
                    $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                    $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                    $this->excel->getDefaultStyle()->getAlignment()->applyFromArray(
                            array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,)
                    );
                    $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                    require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                    $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                    $rendererLibrary = 'MPDF';
                    $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                    if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                        die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                    $objWriter->save('php://output');
                    exit();
                }
                if ($xls) {
                    $this->excel->getActiveSheet()->getStyle('F2:F' . $row + 1)->getAlignment()->setWrapText(TRUE);
                    ob_clean();
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                    header('Cache-Control: max-age=0');
                    ob_clean();
                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                    $objWriter->save('php://output');
                    exit();
                }
                if (img) {
                    $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                    $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                    $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                    require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                    $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                    $rendererLibrary = 'MPDF';
                    $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                    if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                        die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                    $objWriter->save(str_replace(__FILE__, 'assets/mdata/'.$this->Customer_assets.'/uploads/pdf/sales_gst_report.pdf', __FILE__));
                    redirect("reports/create_image/sales_gst_report.pdf");
                    exit();
                }
            }
            $this->session->set_flashdata('error', lang('nothing_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $si = "( SELECT {$this->db->dbprefix('sale_items')}.sale_id, {$this->db->dbprefix('sale_items')}.product_id, {$this->db->dbprefix('sale_items')}.serial_no, GROUP_CONCAT(CONCAT(' ',{$this->db->dbprefix('sale_items')}.product_name, IF({$this->db->dbprefix('product_variants')}.name <> 'NULL',CONCAT(' (',{$this->db->dbprefix('product_variants')}.name,')'),''), CONCAT('( Rs',ROUND({$this->db->dbprefix('sale_items')}.subtotal,2),')'), '-', ROUND({$this->db->dbprefix('sale_items')}.quantity,2)) SEPARATOR ',\n') as item_nane from {$this->db->dbprefix('sale_items')} ";
            $si .= "LEFT JOIN {$this->db->dbprefix('product_variants')} ON {$this->db->dbprefix('sale_items')}.option_id = {$this->db->dbprefix('product_variants')}.id";

            $si .= " GROUP BY {$this->db->dbprefix('sale_items')}.sale_id ) FSI";
            $this->load->library('datatables');
            $this->datatables->select("DATE_FORMAT(sma_sales.date, '%Y-%m-%d %T') as date,sma_sales.invoice_no,
            sma_sales.reference_no as reference_no,biller,customer,comp.state,
            IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,
            FSI.item_nane as iname,
            (grand_total + rounding),total_discount, (grand_total - total_tax ) as tax_able_amount,total_tax,paid,
            (grand_total + rounding - paid) as balance,  GROUP_CONCAT(' ',sma_payments.paid_by), payment_status,
            {$this->db->dbprefix('sales')}.id as id", FALSE)
                    ->add_column('HsnCode', '')
                    ->add_column('qty', '')
                    ->add_column('units', '')
                    ->add_column('CGST', '', '')
                    ->add_column('SGST', '')
                    ->add_column('IGST', '')
                    ->add_column('TaxableAmont', '')
                    ->add_column('VAT', '')
                    ->add_column('CESS', '')
                    ->from('sales')
                    ->join('companies comp', 'sales.customer_id=comp.id', 'left')
                    ->join('sma_payments ', 'sales.id=sma_payments.sale_id', 'left')
                    ->join($si, 'FSI.sale_id=sales.id', 'left')
                    ->join('warehouses', 'warehouses.id=sales.warehouse_id', 'left');
            if ($this->Owner || $this->Admin) {
                if ($user) {
                    $this->datatables->where('sales.created_by', $user);
                }
            } else {
                if ($this->session->userdata('view_right') == '0') {
                    if ($user) {
                        $this->datatables->where('sales.created_by', $user);
                    }
                }
            }
            if ($biller) {
                $this->datatables->where('sales.biller_id', $biller);
            }
            if ($customer) {
                $this->datatables->where('sales.customer_id', $customer);
            }
            if ($warehouse) {
                $getwarehouse = str_replace("_", ",", $warehouse);
                $this->datatables->where('sales.warehouse_id IN(' . $getwarehouse . ')');
            }
            if ($reference_no) {
                $this->datatables->like('sales.reference_no', $reference_no, 'both');
            }
            if ($start_date) {
                $this->datatables->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');
            } else {
                $this->datatables->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . (date('Y') - 2) . date('-m') . date('-d ') . '00:00:00' . '" and "' . date('Y-m-d H:i:s') . '"');
            }

            if ($gstn_opt) {
                switch ($gstn_opt) {
                    case '-1':
                        $this->datatables->where("comp.gstn_no IS NULL OR comp.gstn_no = '' ");
                        break;

                    case '1':
                        $this->datatables->where("comp.gstn_no IS NOT NULL and comp.gstn_no != '' ");
                        break;

                    default:

                        break;
                }
            }
            if ($gstn_no) {
                $this->datatables->where("comp.gstn_no = '" . $gstn_no . "' ");
            }
            if ($SalesIds) {
                $this->datatables->where('sales.id in (' . $SalesIds . ')');
            }
            $this->datatables->group_by('sales.id');
            echo $this->datatables->generate();
        }
    }

    public function getCountSalesGst() {
        $SalesIds = '';
        // $this->sma->checkPermissions('sales', TRUE);
        $product = $this->input->get('product') ? $this->input->get('product') : NULL;
        $user = $this->input->get('user') ? $this->input->get('user') : NULL;
        $customer = $this->input->get('customer') ? $this->input->get('customer') : NULL;
        $biller = $this->input->get('biller') ? $this->input->get('biller') : NULL;
        $warehouse = $this->input->get('warehouse') ? $this->input->get('warehouse') : NULL;
        $reference_no = $this->input->get('reference_no') ? $this->input->get('reference_no') : NULL;
        $start_date = $this->input->get('start_date') ? $this->input->get('start_date') : NULL;
        $end_date = $this->input->get('end_date') ? $this->input->get('end_date') : NULL;
        $serial = $this->input->get('serial') ? $this->input->get('serial') : NULL;
        $gstn_opt = $this->input->get('gstn_opt') ? $this->input->get('gstn_opt') : NULL;
        $gstn_no = $this->input->get('gstn_no') ? $this->input->get('gstn_no') : NULL;
        $hsn_code = $this->input->get('hsn_code') ? $this->input->get('hsn_code') : NULL;
        $max_export_sales = $this->input->get('max_export_sales') ? $this->input->get('max_export_sales') : '0-200'; //0-500
        if (!empty($hsn_code)) {
            $SalesIds = $this->reports_model->getSaleIdByHsn($hsn_code);
        }
        if ($start_date) {
            $start_date = $this->sma->fld($start_date);
            $end_date = $this->sma->fld($end_date);
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user = $this->session->userdata('user_id');
        }

        $this->db->select("sales.id as sale_id,sales.date, sales.reference_no, sales.biller, sales.customer,sales.product_tax as total_tax , 
                IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,   comp.address,  comp.city,  comp.phone,  comp.email , 
                sma_sales.grand_total as grand_total, sma_sales.paid as paid,sma_payments.paid_by, sales.payment_status", FALSE)
                ->from('sales')
                ->join('companies comp', 'sales.customer_id=comp.id', 'left')
                ->join('sma_payments ', 'sales.id=sma_payments.sale_id', 'left')
                ->group_by('sales.id')
                ->order_by('sales.date desc');
        if ($this->Owner || $this->Admin) {
            if ($user) {
                $this->db->where('sales.created_by', $user);
            }
        } else {
            if ($this->session->userdata('view_right') == '0') {
                if ($user) {
                    $this->db->where('sales.created_by', $user);
                }
            }
        }
        if ($biller) {
            $this->db->where('sales.biller_id', $biller);
        }
        if ($customer) {
            $this->db->where('sales.customer_id', $customer);
        }
        if ($warehouse) {
            $getwarehouse = str_replace("_", ",", $warehouse);
            $this->db->where('sales.warehouse_id IN(' . $getwarehouse . ')');
        }
        if ($reference_no) {
            $this->db->like('sales.reference_no', $reference_no, 'both');
        }
        if ($start_date) {
            $this->db->where($this->db->dbprefix('sales') . '.date BETWEEN "' . $start_date . '" and "' . $end_date . '"');
        }

        if ($gstn_opt) {
            switch ($gstn_opt) {
                case '-1':
                    $this->db->where("comp.gstn_no IS NULL OR comp.gstn_no = '' ");
                    break;
                case '1':
                    $this->db->where("comp.gstn_no IS NOT NULL and comp.gstn_no != '' ");
                    break;
                default:
                    break;
            }
        }

        if ($gstn_no) {
            $this->db->where("comp.gstn_no = '" . $gstn_no . "' ");
        }
        if ($SalesIds) {
            $this->db->where('sales.id in (' . $SalesIds . ')');
        }

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            $data = $q->num_rows();

            return $data;
        }

        return FALSE;
    }

    /** @return json for sales_item 12-21-2019 */
    public function getSalesItemsGst() {
        $id = $this->input->get('id');
        $return_option = array();
        $return_option['hsn_code'] = $this->reports_model_new->getSalesHsunt($id, 'hsn_code'); //HSN CODE 
        $return_option['qty'] = $this->reports_model_new->getSalesQty($id, 'quantity'); //Quatity 
        $return_option['units'] = $this->reports_model_new->getSalesHsunt($id, 'product_unit_code'); //Units
        $return_option['tax'] = $this->reports_model_new->getSalesTax($id); //Units
        $datacgst = $this->reports_model_new->getSalesAsGst($id, 'cgst'); //CGST 
        foreach ($datacgst as $dataitem1 => $tax1) {//sumgst
            $arr1[] = $tax1->sumgst;
        }
        $return_option['CGST'] = implode(", ", $arr1);

        $datasgst = $this->reports_model_new->getSalesAsGst($id, 'cgst'); //SGST 

        foreach ($datasgst as $dataitem2 => $tax2) {//sumgst
            $arr2[] = $tax2->sumgst;
        }
        $return_option['SGST'] = implode(", ", $arr2);

        $dataigst = $this->reports_model_new->getSalesAsGst($id, 'igst'); //IGST 
        foreach ($dataigst as $dataitem3 => $tax3) {//sumgst
            $arr3[] = $tax3->sumgst;
        }
        $return_option['IGST'] = implode(", ", $arr3);

        $vat = $this->reports_model_new->getVatCess($id, 'VAT'); // Vat 
        $cess = $this->reports_model_new->getVatCess($id, 'CESS'); //CESS
        $return_option['VAT'] = $vat->VAT; // Vat 
        $return_option['CESS'] = $cess->CESS; // Vat

        echo json_encode($return_option);
    }

    /* purchase gst new */

    public function purchases_gst_report() {
        // $this->sma->checkPermissions('purchases');
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['users'] = $this->reports_model_new->getStaff();
        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('purchases_report')));
        $meta = array('page_title' => lang('purchases_report'), 'bc' => $bc);
        $this->page_construct('new_reports/purchases_gst', $meta, $this->data);
    }

    function create_image_new() {
        $this->data['FileName'] = $this->uri->segment(3);
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('payments_report')));
        $meta = array('page_title' => lang('payments_report'), 'bc' => $bc);
        $this->page_construct('reports_new/view_image_format', $meta, $this->data);
    }

    public function getPurchasesReportC_new($pdf = NULL, $xls = NULL, $img = NULL) {
        // $this->sma->checkPermissions('purchases', TRUE);
        $product = $this->input->get('product') ? $this->input->get('product') : NULL;
        $user = $this->input->get('user') ? $this->input->get('user') : NULL;
        $supplier = $this->input->get('supplier') ? $this->input->get('supplier') : NULL;
        $warehouse = $this->input->get('warehouse') ? $this->input->get('warehouse') : NULL;
        $reference_no = $this->input->get('reference_no') ? $this->input->get('reference_no') : NULL;
        $start_date = $this->input->get('start_date') ? $this->input->get('start_date') : NULL;
        $end_date = $this->input->get('end_date') ? $this->input->get('end_date') : NULL;
        $gstn_opt = $this->input->get('gstn_opt') ? $this->input->get('gstn_opt') : NULL;
        $gstn_no = $this->input->get('gstn_no') ? $this->input->get('gstn_no') : NULL;
        $hsn_code = $this->input->get('hsn_code') ? $this->input->get('hsn_code') : NULL;
        $PurchaseIds = '';
        if (!empty($hsn_code)) {
            $PurchaseIds = $this->reports_model->getPurchaseIdByHsn($hsn_code);
        }
        if ($start_date) {
            $start_date = $this->sma->fld($start_date);
            $end_date = $this->sma->fld($end_date);
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user = $this->session->userdata('user_id');
        }

        if ($pdf || $xls || $img) {

           /* $this->db->select("" . $this->db->dbprefix('purchases') . ".date," . $this->db->dbprefix('purchases') . ".id, reference_no, " . $this->db->dbprefix('warehouses') . ".name as wname, supplier,IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,
            (SELECT  IF(sma_purchase_items.hsn_code ='' OR sma_purchase_items.hsn_code is null , '', (GROUP_CONCAT( CONCAT(sma_purchase_items.hsn_code,' (',FORMAT(sma_purchase_items.quantity,2),')')SEPARATOR ',') ) )
              as hsn_code FROM   `sma_purchase_items` WHERE   `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id`) as hsn_code,
            (SELECT IF(cgst > 0,CONCAT('(',format(gst_rate,2), '%)Rs.',format(sum(cgst),2)), 0.00) FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` AND `sma_purchase_items`.`cgst` > 0 ) as CGST,
	    (SELECT IF(sgst > 0,CONCAT('(',format(gst_rate,2), '%)Rs.',format(sum(sgst),2)), 0.00) FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` AND `sma_purchase_items`.`sgst` > 0) as SGST,
	    (SELECT IF(igst > 0, CONCAT('(',format(gst_rate,2), '%)Rs.',format(sum(igst),2)), 0.00)  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id`) as IGST,
            grand_total, (grand_total - total_tax ) as tax_able_amount, 
            (SELECT (GROUP_CONCAT(DISTINCT CONCAT(' ' , format(tax,2),'%'))) as tax_rate  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_rate,(SELECT  CONCAT(format(sum(item_tax),2), ' Rs')  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_amt, paid, (grand_total-paid) as balance, {$this->db->dbprefix('purchases')}.status, {$this->db->dbprefix('purchases')}.id as id", FALSE)->from('purchases')->join('companies comp', 'purchases.supplier_id=comp.id', 'left')->join('warehouses', 'warehouses.id=purchases.warehouse_id', 'left');
            // paid, " . $this->db->dbprefix('purchases') . ".status", FALSE)->from('purchases')->join('companies comp', 'purchases.supplier_id=comp.id', 'left')->join('warehouses', 'warehouses.id=purchases.warehouse_id', 'left')->group_by('purchases.id')->order_by('purchases.date desc');
*/

   $si = "( SELECT {$this->db->dbprefix('purchase_items')}.purchase_id, {$this->db->dbprefix('purchase_items')}.product_id,  "
            . "GROUP_CONCAT(CONCAT(' ',{$this->db->dbprefix('purchase_items')}.product_name,"
            . " IF({$this->db->dbprefix('product_variants')}.name <> 'NULL',CONCAT(' (',{$this->db->dbprefix('product_variants')}.name,')'),''), CONCAT('( Rs',ROUND({$this->db->dbprefix('purchase_items')}.subtotal,2),')'), '-', ROUND({$this->db->dbprefix('purchase_items')}.quantity)) SEPARATOR ',\n') as item_name, IF(sma_purchase_items.hsn_code ='' OR sma_purchase_items.hsn_code is null , '',(GROUP_CONCAT( CONCAT(sma_purchase_items.hsn_code)SEPARATOR '\n') )  ) as hsn_code  from {$this->db->dbprefix('purchase_items')} ";
            $si .= "LEFT JOIN {$this->db->dbprefix('product_variants')} ON {$this->db->dbprefix('purchase_items')}.option_id = {$this->db->dbprefix('product_variants')}.id";

            $si .= " GROUP BY {$this->db->dbprefix('purchase_items')}.purchase_id ) FSI";


        $this->db->select("DATE_FORMAT({$this->db->dbprefix('purchases')}.date, '%Y-%m-%d %T') as date, reference_no, {$this->db->dbprefix('warehouses')}.name as wname, supplier,IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,
             FSI.hsn_code,FSI.item_name, 
            grand_total, (grand_total - total_tax ) as tax_able_amount,
            (SELECT (GROUP_CONCAT(DISTINCT CONCAT(' ' , format(tax,2),'%'))) as tax_rate  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_rate,
           (SELECT  IF(item_tax IS NULL or item_tax = '', 0 , CONCAT(format(sum(ifnull(item_tax,0)),2)) )  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_amt,
            paid, (grand_total-paid) as balance, {$this->db->dbprefix('purchases')}.status, {$this->db->dbprefix('purchases')}.id as id", FALSE)
            
                    ->from('purchases')
                     ->join($si, 'FSI.purchase_id=purchases.id', 'left')
                    ->join('companies comp', 'purchases.supplier_id=comp.id', 'left')->join('warehouses', 'warehouses.id=purchases.warehouse_id', 'left');
      


            if ($this->session->userdata('view_right') == '0') {
                if ($user) {
                    $this->db->where('purchases.created_by', $user);
                }
            }
            if ($supplier) {
                $this->db->where('purchases.supplier_id', $supplier);
            }
            if ($warehouse) {
                $getwarehouse = str_replace("_", ",", $warehouse);
                $this->db->where('purchases.warehouse_id IN(' . $getwarehouse . ')');
            }
            if ($reference_no) {
                $this->db->like('purchases.reference_no', $reference_no, 'both');
            }
            if ($start_date) {
                //$this->db->where(DATE('.$this->db->dbprefix('purchases') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');
                $this->db->where('DATE(' . $this->db->dbprefix('purchases') . '.date) >= "' . $start_date . '"');
                $this->db->where('DATE(' . $this->db->dbprefix('purchases') . '.date) <= "' . $end_date . '"');
            }
            if ($gstn_opt) {
                switch ($gstn_opt) {
                    case '-1':
                        $this->db->where("comp.gstn_no IS NULL OR comp.gstn_no = '' ");
                        break;

                    case '1':
                        $this->db->where("comp.gstn_no IS NOT NULL and comp.gstn_no != '' ");
                        break;

                    default:

                        break;
                }
            }
            if ($gstn_no) {
                $this->db->where("comp.gstn_no = '" . $gstn_no . "' ");
            }

            if ($PurchaseIds) {
                $PurchaseIds = $this->reports_model->getPurchaseIdByHsn($hsn_code);
                $this->db->where('purchases.id in (' . $PurchaseIds . ')');
            }

            $q = $this->db->get();
            if ($q->num_rows() > 0) {
                foreach (($q->result()) as $row) {
                    $data[] = $row;
                }
            } else {
                $data = NULL;
            }

            if (!empty($data)) {

                $this->load->library('excel');
                $this->excel->setActiveSheetIndex(0);

                $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                $this->excel->getActiveSheet()->getStyle("A1:P1")->applyFromArray($style);
                $this->excel->getActiveSheet()->mergeCells('A1:P1');
                $this->excel->getActiveSheet()->SetCellValue('A1', 'GST Purchases Report');
                $this->excel->getActiveSheet()->setTitle(lang('purchase_report'));
                $this->excel->getActiveSheet()->SetCellValue('A2', lang('date'));
                $this->excel->getActiveSheet()->SetCellValue('B2', lang('reference_no'));
                $this->excel->getActiveSheet()->SetCellValue('C2', lang('warehouse'));
                $this->excel->getActiveSheet()->SetCellValue('D2', lang('supplier'));
                $this->excel->getActiveSheet()->SetCellValue('E2', lang('gstn'));
                $this->excel->getActiveSheet()->SetCellValue('F2', lang('hsn_code'));

                $this->excel->getActiveSheet()->SetCellValue('G2', lang('grand_total'));
                $this->excel->getActiveSheet()->SetCellValue('H2', lang('Taxable_Amount'));
                $this->excel->getActiveSheet()->SetCellValue('I2', lang('GST_Rate'));
                $this->excel->getActiveSheet()->SetCellValue('J2', lang('Tax Amount (Rs)'));

                $this->excel->getActiveSheet()->SetCellValue('K2', lang('paid'));
                $this->excel->getActiveSheet()->SetCellValue('L2', lang('balance'));
                $this->excel->getActiveSheet()->SetCellValue('M2', lang('status'));
                $this->excel->getActiveSheet()->SetCellValue('N2', lang('CGST'));
                $this->excel->getActiveSheet()->SetCellValue('O2', lang('CGST Rate (%)'));
                $this->excel->getActiveSheet()->SetCellValue('P2', lang('SGST'));
                $this->excel->getActiveSheet()->SetCellValue('Q2', lang('SGST Rate (%)'));

                $this->excel->getActiveSheet()->SetCellValue('R2', lang('IGST'));
                $this->excel->getActiveSheet()->SetCellValue('S2', lang('IGST Rate (%)'));
                $this->excel->getActiveSheet()->SetCellValue('T2', lang('VAT'));
                $this->excel->getActiveSheet()->SetCellValue('U2', lang('CESS'));
                $this->excel->getActiveSheet()->SetCellValue('U2', lang('Product Name'));

                $row = 3;
                $total = 0;
                $total_tax = 0;
                $paid = 0;
                $tax_amt = 0;
                $balance = 0;
                $cgst = 0;
                $sgst = 0;
                $igst = 0;
                foreach ($data as $data_row) {
                    $id = $data_row->id;
                    //echo $id;
                    $datacgst = $this->reports_model_new->getPurcahseAsGst($id, 'cgst'); //CGST 
                    //print_r($datacgst);
                    if (isset($datacgst)) {
                        unset($arr1);
                        unset($arrrate1);
                        foreach ($datacgst as $dataitem1 => $tax1) {//sumgst
                            $arr1[] = $tax1->totalgst;
                            $arrrate1 [] = $tax1->gstrate;
                        }
                    }
                    $CGST = implode(", ", $arr1);
                    $CGSTRATE = implode(", ", $arrrate1);
                    $datasgst = $this->reports_model_new->getPurcahseAsGst($id, 'cgst'); //SGST 
                    //print_r($datasgst);
                    if (isset($datasgst)) {
                        unset($arr2);
                        unset($arrrate2);

                        foreach ($datasgst as $dataitem2 => $tax2) {//sumgst
                            $arr2[] = $tax2->totalgst;
                            $arrrate2 [] = $tax2->gstrate;
                        }
                    }
                    $SGST = implode(", ", $arr2);
                    $SGSTRATE = implode(", ", $arrrate2);

                    $dataigst = $this->reports_model_new->getPurcahseAsGst($id, 'igst'); //IGST 
                    if (isset($dataigst)) {
                        unset($arr3);
                        unset($arrrate3);
                        foreach ($dataigst as $dataitem3 => $tax3) {//sumgst
                            $arr3[] = $tax3->totalgst;
                            $arrrate3 [] = $tax3->gstrate;
                        }
                    }
                    $IGST = implode(", ", $arr3);
                    $IGSTRATE = implode(", ", $arrrate3);

                    $Vat = $this->reports_model_new->getpurcahseVatCESS($id, 'VAT');
                    $Cess = $this->reports_model_new->getpurcahseVatCESS($id, 'CESS');

                    $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($data_row->date));
                    $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->reference_no);
                    $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->wname);
                    $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->supplier);
                    $this->excel->getActiveSheet()->SetCellValue('E' . $row, $data_row->gstn_no);
                    $this->excel->getActiveSheet()->SetCellValue('F' . $row, $data_row->hsn_code);

                    $this->excel->getActiveSheet()->SetCellValue('G' . $row, $this->sma->formatMoney($data_row->grand_total));
                    $this->excel->getActiveSheet()->SetCellValue('H' . $row, $this->sma->formatMoney($data_row->tax_able_amount));
                    $this->excel->getActiveSheet()->SetCellValue('I' . $row, $data_row->tax_rate);
                    $this->excel->getActiveSheet()->SetCellValue('J' . $row, $this->sma->formatMoney($data_row->tax_amt));
                    $this->excel->getActiveSheet()->SetCellValue('K' . $row, $this->sma->formatMoney($data_row->paid));
                    $this->excel->getActiveSheet()->SetCellValue('L' . $row, $this->sma->formatMoney($data_row->grand_total - $data_row->paid));
                    $this->excel->getActiveSheet()->SetCellValue('M' . $row, $data_row->status);
                    $this->excel->getActiveSheet()->SetCellValue('N' . $row, $this->sma->formatMoney($CGST));
                    $this->excel->getActiveSheet()->SetCellValue('O' . $row, $CGSTRATE);

                    $this->excel->getActiveSheet()->SetCellValue('P' . $row, $this->sma->formatMoney($SGST));
                    $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $SGSTRATE);

                    $this->excel->getActiveSheet()->SetCellValue('R' . $row, $this->sma->formatMoney($IGST));
                    $this->excel->getActiveSheet()->SetCellValue('S' . $row, $IGSTRATE);

                    $this->excel->getActiveSheet()->SetCellValue('T' . $row, $this->sma->formatMoney($Vat->taxamount));
                    $this->excel->getActiveSheet()->SetCellValue('U' . $row, $this->sma->formatMoney($Cess->taxamount));
                    $this->excel->getActiveSheet()->SetCellValue('V' . $row, $data_row->item_name);


                    $total += $data_row->grand_total;
                    $total_tax += $data_row->tax_able_amount;
                    $tax_amt += $data_row->tax_amt;
                    $paid += $data_row->paid;
                    $balance += ($data_row->grand_total - $data_row->paid);
                    $row++;
                }
                $this->excel->getActiveSheet()->getStyle("G" . $row . ":L" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

                //$this->excel->getActiveSheet()->SetCellValue('G' . $row, $cgst);
                //$this->excel->getActiveSheet()->SetCellValue('H' . $row, $sgst);
                //$this->excel->getActiveSheet()->SetCellValue('I' . $row, $igst);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $total);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $total_tax);
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $tax_amt);
                $this->excel->getActiveSheet()->SetCellValue('K' . $row, $paid);
                $this->excel->getActiveSheet()->SetCellValue('L' . $row, $balance);

                $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('K')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('N')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('O')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('P')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('R')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('R')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('S')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('T')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('U')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('V')->setWidth(20);

                $filename = 'GST_purchase_report';
                $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                if ($pdf) {
                    $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                    $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                    $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                    require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                    $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                    $rendererLibrary = 'MPDF';
                    $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                    if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                        die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                    $objWriter->save('php://output');
                    exit();
                }
                if ($xls) {
                    $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
                    ob_clean();
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                    header('Cache-Control: max-age=0');
                    ob_clean();
                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                    $objWriter->save('php://output');
                    exit();
                }
                if ($img) {
                    $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                    $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                    $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                    require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                    $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                    $rendererLibrary = 'MPDF';
                    $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                    if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                        die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                    $objWriter->save(str_replace(__FILE__, 'assets/mdata/'.$this->Customer_assets.'/uploads/pdf/purchase_gst_report.pdf', __FILE__));
                    redirect("reports/create_image/purchase_gst_report.pdf");
                }
            }
            $this->session->set_flashdata('error', lang('nothing_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->load->library('datatables');
         
            /*$this->datatables->select("DATE_FORMAT({$this->db->dbprefix('purchases')}.date, '%Y-%m-%d %T') as date, reference_no, {$this->db->dbprefix('warehouses')}.name as wname, supplier,IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,
            (SELECT  IF(sma_purchase_items.hsn_code ='' OR sma_purchase_items.hsn_code is null , '', (GROUP_CONCAT( CONCAT(sma_purchase_items.hsn_code,' (',FORMAT(sma_purchase_items.quantity,2),')')SEPARATOR '<br>') ) )
            as hsn_code FROM   `sma_purchase_items` WHERE   `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id`) as hsn_code,
            grand_total, (grand_total - total_tax ) as tax_able_amount,
            (SELECT (GROUP_CONCAT(DISTINCT CONCAT(' ' , format(tax,2),'%'))) as tax_rate  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_rate,
           (SELECT  IF(item_tax IS NULL or item_tax = '', 0 , CONCAT(format(sum(ifnull(item_tax,0)),2)) )  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_amt,
            paid, (grand_total-paid) as balance, {$this->db->dbprefix('purchases')}.status, {$this->db->dbprefix('purchases')}.id as id", FALSE)
                    ->add_column('CGST', '', '')
                    ->add_column('SGST', '')
                    ->add_column('IGST', '')
                    ->add_column('VAT', '')
                    ->add_column('CESS', '')
                    ->from('purchases')
                    ->join('companies comp', 'purchases.supplier_id=comp.id', 'left')->join('warehouses', 'warehouses.id=purchases.warehouse_id', 'left'); */

            $si = "( SELECT {$this->db->dbprefix('purchase_items')}.purchase_id, {$this->db->dbprefix('purchase_items')}.product_id,  "
            . "GROUP_CONCAT(CONCAT(' ',{$this->db->dbprefix('purchase_items')}.product_name,"
            . " IF({$this->db->dbprefix('product_variants')}.name <> 'NULL',CONCAT(' (',{$this->db->dbprefix('product_variants')}.name,')'),''), CONCAT('( Rs',ROUND({$this->db->dbprefix('purchase_items')}.subtotal,2),')'), '-', ROUND({$this->db->dbprefix('purchase_items')}.quantity)) SEPARATOR ',\n') as item_name, IF(sma_purchase_items.hsn_code ='' OR sma_purchase_items.hsn_code is null , '',(GROUP_CONCAT( CONCAT(sma_purchase_items.hsn_code)SEPARATOR '\n') )  ) as hsn_code  from {$this->db->dbprefix('purchase_items')} ";
            $si .= "LEFT JOIN {$this->db->dbprefix('product_variants')} ON {$this->db->dbprefix('purchase_items')}.option_id = {$this->db->dbprefix('product_variants')}.id";

            $si .= " GROUP BY {$this->db->dbprefix('purchase_items')}.purchase_id ) FSI";
$this->datatables->select("DATE_FORMAT({$this->db->dbprefix('purchases')}.date, '%Y-%m-%d %T') as date, reference_no, {$this->db->dbprefix('warehouses')}.name as wname, supplier,IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,
             FSI.hsn_code,FSI.item_name, 
            grand_total, (grand_total - total_tax ) as tax_able_amount,
            (SELECT (GROUP_CONCAT(DISTINCT CONCAT(' ' , format(tax,2),'%'))) as tax_rate  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_rate,
           (SELECT  IF(item_tax IS NULL or item_tax = '', 0 , CONCAT(format(sum(ifnull(item_tax,0)),2)) )  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_amt,
            paid, (grand_total-paid) as balance, {$this->db->dbprefix('purchases')}.status, {$this->db->dbprefix('purchases')}.id as id", FALSE)
            
                    ->add_column('CGST', '', '')
                    ->add_column('SGST', '')
                    ->add_column('IGST', '')
                    ->add_column('VAT', '')
                    ->add_column('CESS', '')
                    ->from('purchases')
                     ->join($si, 'FSI.purchase_id=purchases.id', 'left')
                    ->join('companies comp', 'purchases.supplier_id=comp.id', 'left')->join('warehouses', 'warehouses.id=purchases.warehouse_id', 'left');
        

            // ->group_by('purchases.id');
            if ($this->session->userdata('view_right') == '0') {
                if ($user) {
                    $this->datatables->where('purchases.created_by', $user);
                }
            }
            if ($supplier) {
                $this->datatables->where('purchases.supplier_id', $supplier);
            }
            if ($warehouse) {
                $getwarehouse = str_replace("_", ",", $warehouse);
                $this->datatables->where('purchases.warehouse_id IN (' . $getwarehouse . ')');
            }
            if ($reference_no) {
                $this->datatables->like('purchases.reference_no', $reference_no, 'both');
            }
            if ($start_date) {
                $this->datatables->where('DATE(' . $this->db->dbprefix('purchases') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');
            }
            if ($gstn_opt) {
                switch ($gstn_opt) {
                    case '-1':
                        $this->datatables->where("comp.gstn_no IS NULL OR comp.gstn_no = '' ");
                        break;

                    case '1':
                        $this->datatables->where("comp.gstn_no IS NOT NULL and comp.gstn_no != '' ");
                        break;

                    default:

                        break;
                }
            }
            if ($gstn_no) {
                $this->datatables->where("comp.gstn_no = '" . $gstn_no . "' ");
            }

            if ($PurchaseIds) {

                $this->datatables->where('purchases.id in (' . $PurchaseIds . ')');
            }
            echo $this->datatables->generate();
        }
    }

    /** @return json for Purchase_item */
    public function getPurchaseItemsGst() {
        $id = $this->input->get('id');
        $return_option = array();
        /* $return_option['hsn_code'] = $this->reports_model->getSalesHsunt($id, 'hsn_code'); //HSN CODE 
          $return_option['qty'] = $this->reports_model->getSalesQty($id, 'quantity'); //Quatity
          $return_option['units'] = $this->reports_model->getSalesHsunt($id, 'product_unit_code'); //Units
          $return_option['tax'] = $this->reports_model->getSalesTax($id); //Units
         * /
         */
        $datacgst = $this->reports_model_new->getPurcahseAsGst($id, 'cgst'); //CGST 
        // print_r($datacgst);

        foreach ($datacgst as $dataitem1 => $tax1) {//sumgst
            $arr1[] = $tax1->sumgst;
        }

        $return_option['CGST'] = implode(", ", $arr1);

        $datasgst = $this->reports_model_new->getPurcahseAsGst($id, 'cgst'); //SGST 
        //print_r($datasgst);
        foreach ($datasgst as $dataitem2 => $tax2) {//sumgst
            $arr2[] = $tax2->sumgst;
        }
        $return_option['SGST'] = implode(", ", $arr2);

        $dataigst = $this->reports_model_new->getPurcahseAsGst($id, 'igst'); //IGST 
        foreach ($dataigst as $dataitem3 => $tax3) {//sumgst
            $arr3[] = $tax3->sumgst;
        }
        $return_option['IGST'] = implode(", ", $arr3);

        $VAT = $this->reports_model_new->getpurcahseVatCESS($id, 'VAT');
        $CESS = $this->reports_model_new->getpurcahseVatCESS($id, 'CESS');
        $return_option['VAT'] = $VAT->taxamount;
        $return_option['CESS'] = $CESS->taxamount;

        echo json_encode($return_option);
    }

    /* daily sale report */

    function profit($date = NULL, $warehouse_id = NULL) {
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            $this->sma->md();
        }
        if (!$date) {
            $date = date('Y-m-d');
        }
        $this->data['costing'] = $this->reports_model->getCosting($date, $warehouse_id);
        $this->data['discount'] = $this->reports_model->getOrderDiscount($date, $warehouse_id);
        $this->data['expenses'] = $this->reports_model->getExpenses($date, $warehouse_id);
        $this->data['returns'] = $this->reports_model->getReturns($date, $warehouse_id);
        $this->data['date'] = $date;
        $this->load->view($this->theme . 'reports/profit', $this->data);
    }

    function monthly_profit($year, $month, $warehouse_id = NULL) {
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            $this->sma->md();
        }

        $this->data['costing'] = $this->reports_model->getCosting(NULL, $warehouse_id, $year, $month);
        $this->data['discount'] = $this->reports_model->getOrderDiscount(NULL, $warehouse_id, $year, $month);
        $this->data['expenses'] = $this->reports_model->getExpenses(NULL, $warehouse_id, $year, $month);
        $this->data['returns'] = $this->reports_model->getReturns(NULL, $warehouse_id, $year, $month);
        $this->data['date'] = date('F Y', strtotime($year . '-' . $month . '-' . '01'));
        $this->load->view($this->theme . 'new_reports/monthly_profit', $this->data);
    }

    function daily_sales($warehouse_id = NULL, $year = NULL, $month = NULL, $pdf = NULL, $user_id = NULL) {
        // $this->sma->checkPermissions('');
        if ($warehouse_id != NULL) {
            $warehouse_id = $warehouse_id;
        } else if (!$this->Owner && !$this->Admin && $this->session->userdata('warehouse_id')) {
            $warehouse_id = str_replace(",", "_", $this->session->userdata('warehouse_id'));
        }

        $this->data['sel_warehouse'] = $warehouse_id ? (strpos($warehouse_id, '_') !== false) ? NULL : $this->site->getWarehouseByID($warehouse_id) : NULL;


        if (!$year) {
            $year = date('Y');
        }
        if (!$month) {
            $month = date('m');
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $key = $this->data['sel_warehouse'] ? array_keys($this->data['sel_warehouse']) : 0; //Use to get Id on sel_warehouse 
        //$config = array('show_next_prev' => TRUE, 'next_prev_url' => site_url('reports/daily_sales/' . ($this->data['sel_warehouse']? $this->data['sel_warehouse']->id : 0)), 'month_type' => 'long', 'day_type' => 'long');

        $config = array('show_next_prev' => TRUE, 'next_prev_url' => site_url('reports_new/daily_sales/' . ($this->data['sel_warehouse'] ? $key[0] : 0)), 'month_type' => 'long', 'day_type' => 'long');

        $config['template'] = '{table_open}<div class="table-responsive"><table border="0" cellpadding="0" cellspacing="0" class="table table-bordered dfTable">{/table_open}
		{heading_row_start}<tr>{/heading_row_start}
		{heading_previous_cell}<th><a href="{previous_url}">&lt;&lt;</a></th>{/heading_previous_cell}
		{heading_title_cell}<th colspan="{colspan}" id="month_year">{heading}</th>{/heading_title_cell}
		{heading_next_cell}<th><a href="{next_url}">&gt;&gt;</a></th>{/heading_next_cell}
		{heading_row_end}</tr>{/heading_row_end}
		{week_row_start}<tr>{/week_row_start}
		{week_day_cell}<td class="cl_wday">{week_day}</td>{/week_day_cell}
		{week_row_end}</tr>{/week_row_end}
		{cal_row_start}<tr class="days">{/cal_row_start}
		{cal_cell_start}<td class="day">{/cal_cell_start}
		{cal_cell_content}
		<div class="day_num">{day}</div>
		<div class="content">{content}</div>
		{/cal_cell_content}
		{cal_cell_content_today}
		<div class="day_num highlight">{day}</div>
		<div class="content">{content}</div>
		{/cal_cell_content_today}
		{cal_cell_no_content}<div class="day_num">{day}</div>{/cal_cell_no_content}
		{cal_cell_no_content_today}<div class="day_num highlight">{day}</div>{/cal_cell_no_content_today}
		{cal_cell_blank}&nbsp;{/cal_cell_blank}
		{cal_cell_end}</td>{/cal_cell_end}
		{cal_row_end}</tr>{/cal_row_end}
		{table_close}</table></div>{/table_close}';

        $this->load->library('calendar', $config);
        /* $sales = $user_id ? $this->reports_model_new->getStaffDailySales($user_id, $year, $month, $warehouse_id) : $this->reports_model_new->getDailySales($year, $month, $warehouse_id); */
        $sales = $user_id ? $this->reports_model_new->getStaffDailySales_w($user_id, $year, $month, $warehouse_id) : $this->reports_model_new->getDailySales_w($year, $month, $warehouse_id);
        $sales_w = $sales;


        if (!empty($sales)) {
            foreach ($sales as $sale) {
                $deposit =  $this->reports_model_new->getCurrentDeposit($year.'-'.$month.'-'.(($sale->date<10)?'0'.$sale->date :$sale->date ));
                
                
                
                $daily_sale[$sale->date] = "<table class='table table-bordered table-hover table-striped table-condensed data' style='margin:0;'><tr><td>Gross Sale</td><td>" . $this->sma->formatMoney($sale->GrossSale + $sale->return_amt + $sale->total_discount) . "</td></tr><tr><td>Return Sales</td><td>" . $this->sma->formatMoney($sale->return_amt) . "</td></tr><tr><td>" . lang("discount") . "</td><td>" . $this->sma->formatMoney($sale->discount) . "</td></tr><tr><td>" . lang("shipping") . "</td><td>" . $this->sma->formatMoney($sale->shipping) . "</td></tr><tr style='cursor: pointer' onClick='getsaleitemstaxes(" . $year . "," . $month . "," . $sale->date . ")'><td>" . lang("product_tax") . " <i class='fa fa-list-alt' aria-hidden='true'></i></td><td>" . $this->sma->formatMoney($sale->tax1) . "</td></tr><tr><td>" . lang("order_tax") . "</td><td>" . $this->sma->formatMoney($sale->tax2) . "</td></tr><tr><td>" . lang("Total") . "</td><td>" . $this->sma->formatMoney($sale->total) . "</td></tr><tr><td>Items</td><td onClick='getsaleitems(" . $year . "," . $month . "," . $sale->date . ")'><i class='fa fa-list-alt' aria-hidden='true'></i></td></tr><tr style='cursor: pointer' onClick='getsaleitemurbin(" . $year . "," . $month . "," . $sale->date . ")'><td>" . lang("Urban_Piper") . " <i class='fa fa-list-alt' aria-hidden='true'></i></td><td>" . $this->sma->formatMoney($sale->urban_piper) . "</td></tr><tr><td> Deposit Received</td><td>".$this->sma->formatMoney($deposit) ."</td></tr></table>";
            }
        } else {
            $daily_sale = array();
        }

        $this->data['calender'] = $this->calendar->generate($year, $month, $daily_sale);
        $this->data['year'] = $year;
        $this->data['month'] = $month;

        if ($pdf) {
            $sales_pdf = array();
            //foreach($sales as $data_row)
            foreach ($sales_w as $data_row) {//because of warehouse show
                $sales_pdf[$data_row->date] = $data_row;
            }
            sort($sales_pdf);

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);
            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'DDDDDD'))));

            $this->excel->getActiveSheet()->getStyle("A1:K1")->applyFromArray($style);

            $this->excel->getActiveSheet()->mergeCells('A1:K1');
            $this->excel->getActiveSheet()->SetCellValue('A1', lang('Daily Sales Report ') . date("M-Y", mktime(0, 0, 0, $month, 1, $year)));
            $this->excel->getActiveSheet()->SetCellValue('A2', lang('Sr.No'));
            $this->excel->getActiveSheet()->SetCellValue('B2', lang('Date'));
            $this->excel->getActiveSheet()->SetCellValue('C2', lang('Gross Sale'));
            $this->excel->getActiveSheet()->SetCellValue('D2', lang('Return Sale'));
            $this->excel->getActiveSheet()->SetCellValue('E2', lang('Discount'));
            $this->excel->getActiveSheet()->SetCellValue('F2', lang('Shipping'));
            $this->excel->getActiveSheet()->SetCellValue('G2', lang('Product Tax'));
            $this->excel->getActiveSheet()->SetCellValue('H2', lang('Order Tax'));
            $this->excel->getActiveSheet()->SetCellValue('I2', lang('Total'));
            $this->excel->getActiveSheet()->SetCellValue('J2', lang('Urbin Piper'));
            $this->excel->getActiveSheet()->SetCellValue('K2', lang('Warehouse'));
            $row = 3;

            $sr = 1;
            foreach ($sales_pdf as $data_row) {
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->date . '/' . $month . '/' . $year);
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $this->sma->formatMoney($data_row->GrossSale + $data_row->return_amt + $data_row->total_discount));
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $this->sma->formatMoney($data_row->return_amt));

                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->formatMoney($data_row->discount));
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $this->sma->formatMoney($data_row->shipping));
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $this->sma->formatMoney($data_row->tax1));
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $this->sma->formatMoney($data_row->tax2));
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $this->sma->formatMoney($data_row->total));
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $this->sma->formatMoney($data_row->urban_piper));
                $this->excel->getActiveSheet()->SetCellValue('K' . $row, $data_row->warehouse);


                $row++;
                $sr++;
            }
            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(15);

            $this->excel->getActiveSheet()->getStyle("A2:K" . ($row - 1))->applyFromArray($style);

            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $filename = 'daily_sales_report';

            if ($pdf == 'pdf') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'xls') {
                $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
                ob_clean();
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                header('Cache-Control: max-age=0');
                ob_clean();
                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'img') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save(str_replace(__FILE__, 'assets/mdata/'.$this->Customer_assets.'/uploads/pdf/daily_sales_report.pdf', __FILE__));
                redirect("reports/create_image/daily_sales_report.pdf");
            }
        }


        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['warehouse_id'] = $this->session->userdata('warehouse_id');
        //$this->data['sel_warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        $this->data['active_warehouse_id'] = $warehouse_id == '' ? 0 : $warehouse_id;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('daily_sales_report')));
        $meta = array('page_title' => lang('daily_sales_report'), 'bc' => $bc);
        $this->page_construct('new_reports/daily', $meta, $this->data);
    }

    /**/

    public function daily_sales_items() {
        $date = $_GET['date'];
        $warehouse_id = $_GET['active_warehouse_id'];
        if (empty($date))
            return FALSE;

        $sale_data = $this->reports_model_new->getDailySalesItems($date, $warehouse_id);
        ?>
        <button type="button" type="button" class="btn btn-sm btn-default no-print pull-right" style="margin-right:10px;margin-bottom:10px;" onClick="printdivc('<?php echo $date; ?>');"><i class="fa fa-print"></i><?= lang('print'); ?>
        </button>
        <div class="table-responsive" id="dailysalesitemtable">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Units</th>
                        <th>Tax Rate</th>
                        <th>Tax Amount</th>
                        <th>Discount</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 0;
                    foreach ($sale_data as $key => $item) {
                        ?>
                        <tr>
                            <td><?= ++$i ?></td>
                            <td><?= $item->product_name ?></td>
                            <td><?= $item->product_code ?></td>
                            <td><?= $item->category_name ?></td>
                            <td><?= $this->sma->formatMoney($item->net_unit_price) ?></td>
                            <td><?= number_format($item->qty, 2) ?></td>
                            <td><?= $item->unit ?></td>
                            <td><?= $item->tax_rate ? number_format($item->tax_rate, 2) : 0; ?>%</td>
                            <td><?= $this->sma->formatMoney($item->tax) ?></td>
                            <td><?= $this->sma->formatMoney($item->discount) ?></td>
                            <td><?= $this->sma->formatMoney($item->total) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <script type="text/javascript">
            function printdivc() {
                var printContents = document.getElementById('dailysalesitemtable').innerHTML;
                var originalContents = document.body.innerHTML;

                document.body.innerHTML = printContents;

                window.print();

                document.body.innerHTML = originalContents;
            }

        </script>
        <?php
    }

    public function daily_sales_items_taxes() {
        $date = $_GET['date'];
        $warehouse_id = $_GET['active_warehouse_id'];
        if (empty($date))
            return FALSE;

        $saletax_data = $this->reports_model_new->getDailySalesItemsTaxes($date, $warehouse_id);
        //print_r($saletax_data);
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tax</th>
                        <th>CGST</th>
                        <th>SGST</th>
                        <th>IGST</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalTax = 0;
                    $amount = 0;
                    if (!empty($saletax_data)) {
                        foreach ($saletax_data as $key => $item) {
                            $rate = number_format(($item->tax) ? $item->tax : 0);

                            // $totalTax += $item->amount;
                            //echo $item->id;
                            //$gst_tax = $this->reports_model_new->gettaxitemid($item->id);
                            // print_r($gst_tax);
                            if ($item->CGST > 0) {
                                $amount = $item->CGST + $item->SGST;
                            } else {
                                $amount = $item->IGST;
                            }
                            ?>
                            <tr>
                                <td class="text-center"> <?= $rate ?>%  <?= (($item->IGST > 0) ? 'IGST' : 'GST') ?></td>
                                <td class="text-center"><?= (($item->CGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->SGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->IGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center">Rs. <?= number_format($amount, 2); ?></td>
                            </tr>
                            <?php
                            $totalTax += $amount;
                        }
                    }
                    ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Tax</th>
                        <th class="text-center">Rs.<?= number_format($totalTax, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php
    }

    function monthly_sales($warehouse_id = NULL, $year = NULL, $pdf = NULL, $user_id = NULL) {
        // $this->sma->checkPermissions();
        if ($warehouse_id != NULL) {
            $warehouse_id = $warehouse_id;
        } else if (!$this->Owner && !$this->Admin && $this->session->userdata('warehouse_id')) {
            $warehouse_id = str_replace(",", "_", $this->session->userdata('warehouse_id'));
        }
        if (!$year) {
            $year = date('Y');
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->load->language('calendar');
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['year'] = $year;
        /* $this->data['sales'] = $user_id ? $this->reports_model_new->getStaffMonthlySales($user_id, $year, $warehouse_id) : $this->reports_model_new->getMonthlySales($year, $warehouse_id);
          $_sales = $this->data['sales']; */

        $this->data['sales'] = $user_id ? $this->reports_model_new->getStaffMonthlySales_w($user_id, $year, $warehouse_id) : $this->reports_model_new->getMonthlySales_w($year, $warehouse_id);
        $_sales_w = $this->data['sales'];

        if ($pdf) {
            $sales_pdf = array();
            //foreach($_sales as $data_row)
            foreach ($_sales_w as $data_row) {
                $sales_pdf[$data_row->date] = $data_row;
            }
            sort($sales_pdf);

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);
            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'DDDDDD'))));

            $this->excel->getActiveSheet()->getStyle("A1:J1")->applyFromArray($style);

            $this->excel->getActiveSheet()->mergeCells('A1:J1');
            $this->excel->getActiveSheet()->SetCellValue('A1', lang('Monthly Sales Report ') . date("Y", mktime(0, 0, 0, 1, 1, $year)));
            $this->excel->getActiveSheet()->SetCellValue('A2', lang('Sr.No'));
            $this->excel->getActiveSheet()->SetCellValue('B2', lang('Date'));
            $this->excel->getActiveSheet()->SetCellValue('C2', lang('Gross Sale'));
            $this->excel->getActiveSheet()->SetCellValue('D2', lang('Return Sale'));
            $this->excel->getActiveSheet()->SetCellValue('E2', lang('Discount'));
            $this->excel->getActiveSheet()->SetCellValue('F2', lang('Shipping'));
            $this->excel->getActiveSheet()->SetCellValue('G2', lang('Product Tax'));
            $this->excel->getActiveSheet()->SetCellValue('H2', lang('Order Tax'));
            $this->excel->getActiveSheet()->SetCellValue('I2', lang('Total'));
            $this->excel->getActiveSheet()->SetCellValue('J2', lang('Warehouse'));


            $row = 3;

            $sr = 1;
            foreach ($sales_pdf as $data_row) {
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, date("M-Y", mktime(0, 0, 0, $data_row->date, 1, $year)));
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $this->sma->formatMoney($data_row->GrossSale + $data_row->return_amt + $data_row->total_discount));
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $this->sma->formatMoney($data_row->return_amt));

                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->formatMoney($data_row->discount));
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $this->sma->formatMoney($data_row->shipping));
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $this->sma->formatMoney($data_row->tax1));
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $this->sma->formatMoney($data_row->tax2));
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $this->sma->formatMoney($data_row->total));
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $data_row->warehouse);
                $row++;
                $sr++;
            }
            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);


            $this->excel->getActiveSheet()->getStyle("A2:J" . ($row - 1))->applyFromArray($style);

            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $filename = 'monthly_sales_report';

            if ($pdf == 'pdf') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'xls') {
                $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
                ob_clean();
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                header('Cache-Control: max-age=0');
                ob_clean();
                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'img') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save(str_replace(__FILE__, 'assets/mdata/'.$this->Customer_assets.'/uploads/pdf/monthly_sales_report.pdf', __FILE__));
                redirect("reports/create_image/monthly_sales_report.pdf");
                exit();
            }
        }


        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['warehouse_id'] = $this->session->userdata('warehouse_id');
//        $expwarehouse = explode(",", $warehouse_id); 
//        foreach($expwarehouse as $expw){
//            $getwerehouse[]= $warehouse_id ? $this->site->getWarehouseByID($expw) : NULL;
//        }
//        echo $warehouse_id;exit;
        $this->data['sel_warehouse'] = $warehouse_id ? (strpos($warehouse_id, '_') !== false) ? NULL : $this->site->getWarehouseByID($warehouse_id) : NULL; // $getwerehouse;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('monthly_sales_report')));
        $meta = array('page_title' => lang('monthly_sales_report'), 'bc' => $bc);
        $this->page_construct('new_reports/monthly', $meta, $this->data);
    }

    public function monthly_sales_items_taxes() {
        $month = $_GET['month'];
        $year = $_GET['year'];

        if (empty($month) || empty($year))
            return FALSE;

        $saletax_data = $this->reports_model_new->getMonthSalesItemsTaxes($month, $year);
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tax</th>
                        <th>CGST</th>
                        <th>SGST</th>
                        <th>IGST</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $totalTax = 0;
                    $amount = 0;
                    if (!empty($saletax_data)) {
                        foreach ($saletax_data as $key => $item) {
                            $rate = number_format(($item->tax) ? $item->tax : 0);

                            // $totalTax += $item->amount;
                            //echo $item->id;
                            //$gst_tax = $this->reports_model_new->gettaxitemid($item->id);
                            // print_r($gst_tax);
                            if ($item->CGST > 0) {
                                $amount = $item->CGST + $item->SGST;
                            } else {
                                $amount = $item->IGST;
                            }
                            ?>
                            <tr>
                                <td class="text-center"> <?= $rate ?>% <?= (($item->IGST > 0) ? 'IGST' : 'GST') ?></td>

                                <td class="text-center"><?= (($item->CGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->SGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->IGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center">Rs. <?= number_format($amount, 2); ?></td>
                            </tr>
                            <?php
                            $totalTax += $amount;
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Tax</th>
                        <th class="text-center">Rs. <?= number_format($totalTax, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php
    }

    /**/

    public function daily_Urbin_piper() {
        $date = $_GET['date'];
        $warehouse_id = $_GET['active_warehouse_id'];
        if (empty($date))
            return FALSE;

        $sale_data = $this->reports_model_new->getDailyUrbinpiper($date);
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Channel</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    if (!empty($sale_data)) {
                        foreach ($sale_data as $key => $item) {
                            $total += $item->total;
                            ?>
                            <tr>
                                <td class="text-center"><?= $item->invoice; ?></td>
                                <td class="text-center"><?= $item->up_channel ?></td>
                                <td class="text-center">Rs. <?= number_format($item->total, 2); ?></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th class="text-center">Rs. <?= number_format($total, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php
    }

    public function daily_sales_items_print() {
        $date = $_GET['date'];
        if (empty($date))
            return FALSE;
        $sale_data = $this->reports_model->getDailySalesItems($date);
        ?>
        <div class="table-responsive">
            <font size="4px" face="Times New Roman" >
            <table class="table table-bordered" >
                <thead>
                    <tr id="tr_data">
                        <th>#</th>
                        <th  style="width:25px;">Product Name</th>
                       <!--  <th>Code</th> -->
                        <th>Price</th>
                        <th  >Qty</th>
                       <!--  <th>Units</th> -->
                      <!--   <th>Tax Rate</th> -->
                        <th>Tax</th>
                        <th>Disc</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sale_data as $key => $item) { ?>
                        <tr id="tr_get">
                            <td><?= ++$i ?></td>
                            <td style="width:25px;"><?= $item->product_name ?></td>
                        <!--     <td><?= $item->product_code ?></td> -->
                            <td><?= $this->sma->formatMoney($item->net_unit_price) ?></td>
                            <td><?= number_format($item->qty, 2) ?></td>
                           <!--  <td><?= $item->unit ?></td> -->
                          <!--   <td><?= $item->tax_rate ? number_format($item->tax_rate, 2) : 0; ?>%</td> -->
                            <td><?= $this->sma->formatMoney($item->tax) ?><br>(<?= $item->tax_rate ? number_format($item->tax_rate, 2) : 0; ?>%)</td>
                            <td><?= $this->sma->formatMoney($item->discount) ?></td>
                            <td><?= $this->sma->formatMoney($item->total) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* daily purchase and monthly report */

    function daily_purchases($warehouse_id = NULL, $year = NULL, $month = NULL, $pdf = NULL, $user_id = NULL) {
        // $this->sma->checkPermissions('daily_purchases');
        if ($warehouse_id != NULL) {
            $warehouse_id = $warehouse_id;
        } else if (!$this->Owner && !$this->Admin && $this->session->userdata('warehouse_id')) {
            $warehouse_id = str_replace(",", "_", $this->session->userdata('warehouse_id'));
        }
        if (!$year) {
            $year = date('Y');
        }
        if (!$month) {
            $month = date('m');
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->data['sel_warehouse'] = $warehouse_id ? (strpos($warehouse_id, '_') !== false) ? NULL : $this->site->getWarehouseByID($warehouse_id) : NULL;

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $config = array('show_next_prev' => TRUE, 'next_prev_url' => site_url('reports_new/daily_purchases/' . ($this->data['sel_warehouse'] ? $this->data['sel_warehouse']->id : 0)), 'month_type' => 'long', 'day_type' => 'long');

        $config['template'] = '{table_open}<div class="table-responsive"><table border="0" cellpadding="0" cellspacing="0" class="table table-bordered dfTable">{/table_open}
        {heading_row_start}<tr>{/heading_row_start}
        {heading_previous_cell}<th><a href="{previous_url}">&lt;&lt;</a></th>{/heading_previous_cell}
        {heading_title_cell}<th colspan="{colspan}" id="month_year">{heading}</th>{/heading_title_cell}
        {heading_next_cell}<th><a href="{next_url}">&gt;&gt;</a></th>{/heading_next_cell}
        {heading_row_end}</tr>{/heading_row_end}
        {week_row_start}<tr>{/week_row_start}
        {week_day_cell}<td class="cl_wday">{week_day}</td>{/week_day_cell}
        {week_row_end}</tr>{/week_row_end}
        {cal_row_start}<tr class="days">{/cal_row_start}
        {cal_cell_start}<td class="day">{/cal_cell_start}
        {cal_cell_content}
        <div class="day_num">{day}</div>
        <div class="content">{content}</div>
        {/cal_cell_content}
        {cal_cell_content_today}
        <div class="day_num highlight">{day}</div>
        <div class="content">{content}</div>
        {/cal_cell_content_today}
        {cal_cell_no_content}<div class="day_num">{day}</div>{/cal_cell_no_content}
        {cal_cell_no_content_today}<div class="day_num highlight">{day}</div>{/cal_cell_no_content_today}
        {cal_cell_blank}&nbsp;{/cal_cell_blank}
        {cal_cell_end}</td>{/cal_cell_end}
        {cal_row_end}</tr>{/cal_row_end}
        {table_close}</table></div>{/table_close}';

        $this->load->library('calendar', $config);
        $purchases = $user_id ? $this->reports_model_new->getStaffDailyPurchases($user_id, $year, $month, $warehouse_id) : $this->reports_model_new->getDailyPurchases($year, $month, $warehouse_id);

        if (!empty($purchases)) {
            foreach ($purchases as $purchase) {
                $daily_purchase[$purchase->date] = "<table class='table table-bordered table-hover table-striped table-condensed data' style='margin:0;'><tr><td>" . lang("discount") . "</td><td>" . $this->sma->formatMoney($purchase->discount) . "</td></tr><tr><td>" . lang("shipping") . "</td><td>" . $this->sma->formatMoney($purchase->shipping) . "</td></tr><tr  style='cursor: pointer' onClick='getpurchaseitemstaxes(" . $year . "," . $month . "," . $purchase->date . ")'><td>" . lang("product_tax") . " <i class='fa fa-list-alt' aria-hidden='true'></i></td><td>" . $this->sma->formatMoney($purchase->tax1) . "</td></tr><tr><td>" . lang("order_tax") . "</td><td>" . $this->sma->formatMoney($purchase->tax2) . "</td></tr><tr><td>" . lang("total") . "</td><td>" . $this->sma->formatMoney($purchase->total) . "</td></tr><tr><td>Items</td><td onClick='getpurchaseitems(" . $year . "," . $month . "," . $purchase->date . ")'><i class='fa fa-list-alt' aria-hidden='true'></i></td></tr></table>";
            }
        } else {
            $daily_purchase = array();
        }

        $this->data['calender'] = $this->calendar->generate($year, $month, $daily_purchase);
        $this->data['year'] = $year;
        $this->data['month'] = $month;
        if ($pdf) {
            $purchase_pdf = array();
            foreach ($purchases as $data_row) {
                $purchase_pdf[$data_row->date] = $data_row;
            }
            sort($purchase_pdf);

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);
            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'DDDDDD'))));

            $this->excel->getActiveSheet()->getStyle("A1:G1")->applyFromArray($style);

            $this->excel->getActiveSheet()->mergeCells('A1:G1');
            $this->excel->getActiveSheet()->SetCellValue('A1', lang('Daily Purchases Report ') . date("M-Y", mktime(0, 0, 0, $month, 1, $year)));
            $this->excel->getActiveSheet()->SetCellValue('A2', lang('Sr.No'));
            $this->excel->getActiveSheet()->SetCellValue('B2', lang('Date'));
            $this->excel->getActiveSheet()->SetCellValue('C2', lang('Discount'));
            $this->excel->getActiveSheet()->SetCellValue('D2', lang('Shipping'));
            $this->excel->getActiveSheet()->SetCellValue('E2', lang('Product Tax'));
            $this->excel->getActiveSheet()->SetCellValue('F2', lang('Order Tax'));
            $this->excel->getActiveSheet()->SetCellValue('G2', lang('Total'));
            $row = 3;

            $sr = 1;
            foreach ($purchase_pdf as $data_row) {
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->date . '/' . $month . '/' . $year);
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $this->sma->formatMoney($data_row->discount));
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $this->sma->formatMoney($data_row->shipping));
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->formatMoney($data_row->tax1));
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $this->sma->formatMoney($data_row->tax2));
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $this->sma->formatMoney($data_row->total));

                $row++;
                $sr++;
            }
            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);

            $this->excel->getActiveSheet()->getStyle("A2:G" . ($row - 1))->applyFromArray($style);

            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $filename = 'daily_purchase_report';

            if ($pdf == 'pdf') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'xls') {
                $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
                ob_clean();
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                header('Cache-Control: max-age=0');
                ob_clean();
                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'img') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save(str_replace(__FILE__, 'assets/mdata/'.$this->Customer_assets.'/uploads/pdf/' . $filename . '.pdf', __FILE__));
                redirect("reports/create_image/" . $filename . ".pdf");
                exit();
            }
        }
        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['warehouse_id'] = $this->session->userdata('warehouse_id');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('daily_purchases_report')));
        $meta = array('page_title' => lang('daily_purchases_report'), 'bc' => $bc);
        $this->page_construct('new_reports/daily_purchases', $meta, $this->data);
    }

    function monthly_purchases($warehouse_id = NULL, $year = NULL, $pdf = NULL, $user_id = NULL) {
        // $this->sma->checkPermissions();
        if ($warehouse_id != NULL) {
            $warehouse_id = $warehouse_id;
        } elseif (!$this->Owner && !$this->Admin && $this->session->userdata('warehouse_id')) {
            $warehouse_id = $warehouse_id = str_replace(",", "_", $this->session->userdata('warehouse_id'));
        }
        if (!$year) {
            $year = date('Y');
        }
        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->load->language('calendar');
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['year'] = $year;
        $this->data['purchases'] = $user_id ? $this->reports_model_new->getStaffMonthlyPurchases($user_id, $year, $warehouse_id) : $this->reports_model_new->getMonthlyPurchases($year, $warehouse_id);
        $_purchases = $this->data['purchases'];
        if ($pdf) {
            $purchases_pdf = array();
            foreach ($_purchases as $data_row) {
                $purchases_pdf[$data_row->date] = $data_row;
            }
            sort($purchases_pdf);

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);
            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => array('rgb' => 'DDDDDD'))));

            $this->excel->getActiveSheet()->getStyle("A1:G1")->applyFromArray($style);

            $this->excel->getActiveSheet()->mergeCells('A1:G1');
            $this->excel->getActiveSheet()->SetCellValue('A1', lang('Monthly Purchase Report ') . date("Y", mktime(0, 0, 0, 1, 1, $year)));
            $this->excel->getActiveSheet()->SetCellValue('A2', lang('Sr.No'));
            $this->excel->getActiveSheet()->SetCellValue('B2', lang('Date'));
            $this->excel->getActiveSheet()->SetCellValue('C2', lang('Discount'));
            $this->excel->getActiveSheet()->SetCellValue('D2', lang('Shipping'));
            $this->excel->getActiveSheet()->SetCellValue('E2', lang('Product Tax'));
            $this->excel->getActiveSheet()->SetCellValue('F2', lang('Order Tax'));
            $this->excel->getActiveSheet()->SetCellValue('G2', lang('Total'));
            $row = 3;

            $sr = 1;
            foreach ($purchases_pdf as $data_row) {
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, date("M-Y", mktime(0, 0, 0, $data_row->date, 1, $year)));
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $this->sma->formatMoney($data_row->discount));
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $this->sma->formatMoney($data_row->shipping));
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->formatMoney($data_row->tax1));
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $this->sma->formatMoney($data_row->tax2));
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $this->sma->formatMoney($data_row->total));

                $row++;
                $sr++;
            }
            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);

            $this->excel->getActiveSheet()->getStyle("A2:G" . ($row - 1))->applyFromArray($style);

            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $filename = 'monthly_purchases_report';

            if ($pdf == 'pdf') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'xls') {
                $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
                ob_clean();
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                header('Cache-Control: max-age=0');
                ob_clean();
                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                $objWriter->save('php://output');
                exit();
            } elseif ($pdf == 'img') {
                $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                $rendererLibrary = 'MPDF';
                $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                    die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                $objWriter->save(str_replace(__FILE__, 'assets/mdata/'.$this->Customer_assets.'/uploads/pdf/monthly_purchases_report.pdf', __FILE__));
                redirect("reports/create_image/monthly_purchases_report.pdf");
                exit();
            }
        }

        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['warehouse_id'] = $this->session->userdata('warehouse_id');
        $this->data['sel_warehouse'] = $warehouse_id ? (strpos($warehouse_id, '_') !== false) ? NULL : $this->site->getWarehouseByID($warehouse_id) : NULL;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('monthly_purchases_report')));
        $meta = array('page_title' => lang('monthly_purchases_report'), 'bc' => $bc);
        $this->page_construct('new_reports/monthly_purchases', $meta, $this->data);
    }

    public function daily_purchase_items_taxes() {
        $date = $_GET['date'];
        // $warehouse_id = $_GET['active_warehouse_id'];
        if (empty($date))
            return FALSE;

        $purchasetax_data = $this->reports_model_new->getDailyPurchaseItemsTaxes($date);
        //echo'<pre>';
        //print_r($saletax_data);
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tax</th>
                        <th>CGST</th>
                        <th>SGST</th>
                        <th>IGST</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $totalTax = 0;
                    $amount = 0;
                    if (!empty($purchasetax_data)) {
                        foreach ($purchasetax_data as $key => $item) {
                            $rate = $item->gst_rate * 2;

                            if ($item->CGST > 0) {
                                $amount = $item->CGST + $item->SGST;
                            } else {
                                $amount = $item->IGST;
                            }
                            ?>
                            <tr>
                                <td class="text-center">GST <?= $rate ?>%</td>
                                <td class="text-center"><?= (($item->CGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->SGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->IGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center">Rs. <?= number_format($amount, 2); ?></td>
                            </tr>
                            <?php
                            $totalTax += $amount;
                        }
                    }
                    ?>
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Tax</th>
                        <th class="text-center">Rs. <?= number_format($totalTax, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    public function monthly_purchase_items_taxes() {
        $month = $_GET['month'];
        $year = $_GET['year'];

        if (empty($month) || empty($year))
            return FALSE;

        $purchasetax_data = $this->reports_model_new->getMonthPurchaseItemsTaxes($month, $year);
        ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tax</th>
                        <th>CGST</th>
                        <th>SGST</th>
                        <th>IGST</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalTax = 0;
                    $amount = 0;
                    if (!empty($purchasetax_data)) {
                        foreach ($purchasetax_data as $key => $item) {
                            $rate = $item->gst_rate * 2;

                            if ($item->CGST > 0) {
                                $amount = $item->CGST + $item->SGST;
                            } else {
                                $amount = $item->IGST;
                            }
                            ?>
                            <tr>
                                <td class="text-center">GST <?= $rate ?>%</td>
                                <td class="text-center"><?= (($item->CGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->SGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center"><?= (($item->IGST > 0) ? number_format($item->gst_rate, 1) : 0) ?>%</td>
                                <td class="text-center">Rs. <?= number_format($amount, 2); ?></td>
                            </tr>
                            <?php
                            $totalTax += $amount;
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Tax</th>
                        <th class="text-center">Rs. <?= number_format($totalTax, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php
    }

    /* Daily Purchase Item list */

    public function daily_purchase_items() {
        $date = $_GET['date'];
        if (empty($date))
            return FALSE;
        $purchase_data = $this->reports_model_new->getDailyPurchaseItems($date);
        ?>
        <button type="button" type="button" class="btn btn-sm btn-default no-print pull-right" style="margin-right:10px;margin-bottom:10px;" onClick="printdiv('<?php echo $date; ?>');"><i class="fa fa-print"></i><?= lang('print'); ?>
        </button>
        <div class="table-responsive" id="dailysalesitemtable">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Code</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Units</th>
                        <th>Tax Rate</th>
                        <th>Tax Amount</th>
                        <th>Discount</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_data as $key => $item) { ?>
                        <tr>
                            <td><?= ++$i ?></td>
                            <td><?= $item->product_name ?></td>
                            <td><?= $item->product_code ?></td>
                            <td><?= $this->sma->formatMoney($item->net_unit_cost) ?></td>
                            <td><?= number_format($item->qty, 2) ?></td>
                            <td><?= $item->unit ?></td>
                            <td><?= $item->tax_rate ? number_format($item->tax_rate, 2) : 0; ?>%</td>
                            <td><?= $this->sma->formatMoney($item->tax) ?></td>
                            <td><?= $this->sma->formatMoney($item->discount) ?></td>
                            <td><?= $this->sma->formatMoney($item->total) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function daily_purchase_items_print() {
        $date = $_GET['date'];
        if (empty($date))
            return FALSE;
        $purchase_data = $this->reports_model_new->getDailyPurchaseItems($date);
        ?>
        <div class="table-responsive">
            <font size="4px" face="Times New Roman" >
            <table class="table table-bordered" >
                <thead>
                    <tr id="tr_data">
                        <th>#</th>
                        <th  style="width:25px;">Product Name</th>
                       <!--  <th>Code</th> -->
                        <th>Price</th>
                        <th  >Qty</th>
                       <!--  <th>Units</th> -->
                      <!--   <th>Tax Rate</th> -->
                        <th>Tax</th>
                        <th>Disc</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchase_data as $key => $item) { ?>
                        <tr id="tr_get">
                            <td><?= ++$i ?></td>
                            <td style="width:25px;"><?= $item->product_name ?></td>
                        <!--     <td><?= $item->product_code ?></td> -->
                            <td><?= $this->sma->formatMoney($item->net_unit_cost) ?></td>
                            <td><?= number_format($item->qty, 2) ?></td>
                            <!--  <td><?= $item->unit ?></td> -->
                            <!--   <td><?= $item->tax_rate ? number_format($item->tax_rate, 2) : 0; ?>%</td> -->
                            <td><?= $this->sma->formatMoney($item->tax) ?><br>(<?= $item->tax_rate ? number_format($item->tax_rate, 2) : 0; ?>%)</td>
                            <td><?= $this->sma->formatMoney($item->discount) ?></td>
                            <td><?= $this->sma->formatMoney($item->total) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php
    }

    /**/

    /**
     * Reports   Send  on Email
     */
    public function sendreport() {

        $start_date = date('Y-m-d', strtotime('first day of last month'));
        $end_data = date('Y-m-d', strtotime('last day of last month'));
        $hsncode = $this->hsncodeReport($start_date, $end_data);


        $taxreport = $this->taxReports($start_date, $end_data);
        $purchaseGST = $this->purchaseGSTReport($start_date, $end_data);
        $salesGST = $this->salesGSTReport($start_date, $end_data);

        $multi_attach = array($purchaseGST, $salesGST, $hsncode, $taxreport);
        $to = $this->Settings->default_email;

        $subject = 'Reports Excel';

        $this->load->library('parser');
        $parse_data = array(
            'site_link' => base_url(),
            'site_name' => $this->Settings->site_name,
            'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '" alt="' . $this->Settings->site_name . '"/>',
        );
        if (file_exists('./themes/' . $this->theme . '/views/email_templates/reports.html')) {
            $sale_temp = file_get_contents('themes/' . $this->theme . '/views/email_templates/reports.html');
        } else {
            $sale_temp = file_get_contents('./themes/default/views/email_templates/reports.html');
        }

        $message = $this->parser->parse_string($sale_temp, $parse_data);

        if ($this->sma->send_email($to, $subject, $message, null, null, $multi_attach)) {
            unlink($purchaseGST);
            unlink($salesGST);
            unlink($hsncode);
            unlink($taxreport);

            $this->reports_model_new->reportemaillog();
            $this->sma->send_json(array('msg' => $this->lang->line("email_sent")));
        } else {
            unlink($purchaseGST);
            unlink($salesGST);
            unlink($hsncode);
            unlink($taxreport);
            $this->sma->send_json(array('msg' => $this->lang->line("email_failed")));
        }
    }

    /**
     * Purchase GST REPORTS
     * @return type
     */
    public function purchaseGSTReport($start_date, $end_date) {
        $this->db->select("" . $this->db->dbprefix('purchases') . ".date," . $this->db->dbprefix('purchases') . ".id, reference_no, " . $this->db->dbprefix('warehouses') . ".name as wname, supplier,IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,
            (SELECT  IF(sma_purchase_items.hsn_code ='' OR sma_purchase_items.hsn_code is null , '', (GROUP_CONCAT( CONCAT(sma_purchase_items.hsn_code,' (',FORMAT(sma_purchase_items.quantity,2),')')SEPARATOR ',') ) )
              as hsn_code FROM   `sma_purchase_items` WHERE   `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id`) as hsn_code,
            (SELECT IF(cgst > 0,CONCAT('(',format(gst_rate,2), '%)Rs.',format(sum(cgst),2)), 0.00) FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` AND `sma_purchase_items`.`cgst` > 0 ) as CGST,
	    (SELECT IF(sgst > 0,CONCAT('(',format(gst_rate,2), '%)Rs.',format(sum(sgst),2)), 0.00) FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` AND `sma_purchase_items`.`sgst` > 0) as SGST,
	    (SELECT IF(igst > 0, CONCAT('(',format(gst_rate,2), '%)Rs.',format(sum(igst),2)), 0.00)  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id`) as IGST,
            grand_total, (grand_total - total_tax ) as tax_able_amount, 
            (SELECT (GROUP_CONCAT(DISTINCT CONCAT(' ' , format(tax,2),'%'))) as tax_rate  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_rate,(SELECT  CONCAT(format(sum(item_tax),2), ' Rs')  FROM `sma_purchase_items` WHERE `sma_purchase_items`.`purchase_id` = `sma_purchases`.`id` ) as tax_amt, paid, (grand_total-paid) as balance, {$this->db->dbprefix('purchases')}.status, {$this->db->dbprefix('purchases')}.id as id", FALSE)->from('purchases')->join('companies comp', 'purchases.supplier_id=comp.id', 'left')->join('warehouses', 'warehouses.id=purchases.warehouse_id', 'left');
        //$this->db->where(DATE('.$this->db->dbprefix('purchases') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');
        $this->db->where('DATE(' . $this->db->dbprefix('purchases') . '.date) >= "' . $start_date . '"');
        $this->db->where('DATE(' . $this->db->dbprefix('purchases') . '.date) <= "' . $end_date . '"');
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
        } else {
            $data = NULL;
        }

        if (!empty($data)) {

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:P1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:P1');
            $this->excel->getActiveSheet()->SetCellValue('A1', 'GST Purchases Report');

            $this->excel->getActiveSheet()->getStyle("A2:P2")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A2:P2');
            $this->excel->getActiveSheet()->SetCellValue('A2', 'Date : ' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)));

            $this->excel->getActiveSheet()->setTitle(lang('purchase_report'));
            $this->excel->getActiveSheet()->SetCellValue('A3', lang('date'));
            $this->excel->getActiveSheet()->SetCellValue('B3', lang('reference_no'));
            $this->excel->getActiveSheet()->SetCellValue('C3', lang('warehouse'));
            $this->excel->getActiveSheet()->SetCellValue('D3', lang('supplier'));
            $this->excel->getActiveSheet()->SetCellValue('E3', lang('gstn'));
            $this->excel->getActiveSheet()->SetCellValue('F3', lang('hsn_code'));

            $this->excel->getActiveSheet()->SetCellValue('G3', lang('grand_total'));
            $this->excel->getActiveSheet()->SetCellValue('H3', lang('Taxable_Amount'));
            $this->excel->getActiveSheet()->SetCellValue('I3', lang('GST_Rate'));
            $this->excel->getActiveSheet()->SetCellValue('J3', lang('Tax Amount (Rs)'));

            $this->excel->getActiveSheet()->SetCellValue('K3', lang('paid'));
            $this->excel->getActiveSheet()->SetCellValue('L3', lang('balance'));
            $this->excel->getActiveSheet()->SetCellValue('M3', lang('status'));
            $this->excel->getActiveSheet()->SetCellValue('N3', lang('CGST'));
            $this->excel->getActiveSheet()->SetCellValue('O3', lang('CGST Rate (%)'));
            $this->excel->getActiveSheet()->SetCellValue('P3', lang('SGST'));
            $this->excel->getActiveSheet()->SetCellValue('Q3', lang('SGST Rate (%)'));

            $this->excel->getActiveSheet()->SetCellValue('R3', lang('IGST'));
            $this->excel->getActiveSheet()->SetCellValue('S3', lang('IGST Rate (%)'));
            $this->excel->getActiveSheet()->SetCellValue('T3', lang('VAT'));
            $this->excel->getActiveSheet()->SetCellValue('U3', lang('CESS'));

            $row = 4;
            $total = 0;
            $total_tax = 0;
            $paid = 0;
            $tax_amt = 0;
            $balance = 0;
            $cgst = 0;
            $sgst = 0;
            $igst = 0;
            foreach ($data as $data_row) {
                $id = $data_row->id;
                $datacgst = $this->reports_model_new->getPurcahseAsGst($id, 'cgst'); //CGST 
                if (isset($datacgst)) {
                    unset($arr1);
                    unset($arrrate1);
                    foreach ($datacgst as $dataitem1 => $tax1) {//sumgst
                        $arr1[] = $tax1->totalgst;
                        $arrrate1 [] = $tax1->gstrate;
                    }
                }
                $CGST = implode(", ", $arr1);
                $CGSTRATE = implode(", ", $arrrate1);
                $datasgst = $this->reports_model_new->getPurcahseAsGst($id, 'cgst'); //SGST 
                if (isset($datasgst)) {
                    unset($arr2);
                    unset($arrrate2);

                    foreach ($datasgst as $dataitem2 => $tax2) {//sumgst
                        $arr2[] = $tax2->totalgst;
                        $arrrate2 [] = $tax2->gstrate;
                    }
                }
                $SGST = implode(", ", $arr2);
                $SGSTRATE = implode(", ", $arrrate2);

                $dataigst = $this->reports_model_new->getPurcahseAsGst($id, 'igst'); //IGST 
                if (isset($dataigst)) {
                    unset($arr3);
                    unset($arrrate3);
                    foreach ($dataigst as $dataitem3 => $tax3) {//sumgst
                        $arr3[] = $tax3->totalgst;
                        $arrrate3 [] = $tax3->gstrate;
                    }
                }
                $IGST = implode(", ", $arr3);
                $IGSTRATE = implode(", ", $arrrate3);

                $Vat = $this->reports_model_new->getpurcahseVatCESS($id, 'VAT');
                $Cess = $this->reports_model_new->getpurcahseVatCESS($id, 'CESS');


                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($data_row->date));
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->reference_no);
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->wname);
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->supplier);
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $data_row->gstn_no);
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $data_row->hsn_code);

                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $data_row->grand_total);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $data_row->tax_able_amount);
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $data_row->tax_rate);
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $data_row->tax_amt);
                $this->excel->getActiveSheet()->SetCellValue('K' . $row, $data_row->paid);
                $this->excel->getActiveSheet()->SetCellValue('L' . $row, ($data_row->grand_total - $data_row->paid));
                $this->excel->getActiveSheet()->SetCellValue('M' . $row, $data_row->status);
                $this->excel->getActiveSheet()->SetCellValue('N' . $row, $CGST);
                $this->excel->getActiveSheet()->SetCellValue('O' . $row, $CGSTRATE);

                $this->excel->getActiveSheet()->SetCellValue('P' . $row, $SGST);
                $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $SGSTRATE);

                $this->excel->getActiveSheet()->SetCellValue('R' . $row, $IGST);
                $this->excel->getActiveSheet()->SetCellValue('S' . $row, $IGSTRATE);

                $this->excel->getActiveSheet()->SetCellValue('T' . $row, $Vat->taxamount);
                $this->excel->getActiveSheet()->SetCellValue('U' . $row, $Cess->taxamount);


                $total += $data_row->grand_total;
                $total_tax += $data_row->tax_able_amount;
                $tax_amt += $data_row->tax_amt;
                $paid += $data_row->paid;
                $balance += ($data_row->grand_total - $data_row->paid);
                $row++;
            }
            $this->excel->getActiveSheet()->getStyle("G" . $row . ":L" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

            $this->excel->getActiveSheet()->SetCellValue('G' . $row, $total);
            $this->excel->getActiveSheet()->SetCellValue('H' . $row, $total_tax);
            $this->excel->getActiveSheet()->SetCellValue('J' . $row, $tax_amt);
            $this->excel->getActiveSheet()->SetCellValue('K' . $row, $paid);
            $this->excel->getActiveSheet()->SetCellValue('L' . $row, $balance);

            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('K')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('N')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('O')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('P')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('R')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('R')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('S')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('T')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('U')->setWidth(20);

            $filename = 'GST_purchase_report_' . 'Date_' . date('d-m-Y', strtotime($start_date)) . '_to_' . date('d-m-Y', strtotime($end_date));
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
            header('Content-Type: application/vnd.ms-excel');
            //  header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save(str_replace(__FILE__, 'assets/' . $filename . '.xls', __FILE__));

            return 'assets/' . $filename . '.xls';
        }
    }

    /**
     * Sales GST Reports
     * @return string
     */
    public function salesGSTReport($start_date, $end_date) {
        $this->db->select("sales.id as sale_id,sales.date, sales.reference_no, sales.invoice_no,sales.biller, sales.customer,sales.product_tax as total_tax , 
                IF(comp.gstn_no IS NULL or comp.gstn_no = '', '-', comp.gstn_no) as gstn_no,   comp.address,  comp.city,  comp.phone,  comp.email , 
                sma_sales.grand_total as grand_total, sma_sales.paid as paid,sma_sales.rounding as rounding,sma_payments.paid_by, sales.payment_status", FALSE)
                ->from('sales')
                ->join('companies comp', 'sales.customer_id=comp.id', 'left')
                ->join('sma_payments ', 'sales.id=sma_payments.sale_id', 'left')
                ->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"')
                ->group_by('sales.id')
                ->order_by('sales.date desc');
        $this->db->group_by('sales.id');
        $q = $this->db->get();
        $data_sales = [];
        $saleCount = 0;
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                if (!in_array($row->sale_id, $data_sales)) {
                    $data_sales[] = $row->sale_id;
                }
                //Sales Details
                $data[$row->sale_id]['sale_id'] = $row->sale_id;
                $data[$row->sale_id]['date'] = $row->date;
                $data[$row->sale_id]['reference_no'] = $row->reference_no;
                $data[$row->sale_id]['invoice_no'] = $row->invoice_no;
                $data[$row->sale_id]['biller'] = $row->biller;
                $data[$row->sale_id]['customer'] = $row->customer;
                $cantact = ($row->address) ? $row->address : '';
                $cantact .= ($row->city) ? ' City:' . $row->city : '';
                $cantact .= ($row->phone) ? ' Phone:' . $row->phone : '';
                $cantact .= ($row->email) ? ' Email:' . $row->email : '';
                $data[$row->sale_id]['address'] = $cantact;
                $data[$row->sale_id]['gstn_no'] = $row->gstn_no;
                $data[$row->sale_id]['grand_total'] = $row->grand_total + $row->rounding;
                $data[$row->sale_id]['taxable_amt'] = $row->grand_total - $row->total_tax;
                $data[$row->sale_id]['total_tax'] = $row->total_tax;
                $data[$row->sale_id]['paid'] = $row->paid;
                $data[$row->sale_id]['balance'] = $row->grand_total + $row->rounding - $row->paid;
                $data[$row->sale_id]['paid_by'] = $row->paid_by;
                $data[$row->sale_id]['payment_status'] = $row->payment_status;
            }//endforeach

            $uniqueSalesIds = array_unique($data_sales);

            $SalesItems = $this->reports_model_new->getSalesItemsBySaleIds($uniqueSalesIds, $product);
            if (is_array($SalesItems)) {
                foreach ($SalesItems as $key => $SaleItemsRow) {
                    $id = $SaleItemsRow->items_id;
                    $datacgst = $this->reports_model_new->getSalesItemAsGst($id, 'cgst'); //CGST 
                    $datasgst = $this->reports_model_new->getSalesItemAsGst($id, 'sgst'); //CGST 
                    $dataigst = $this->reports_model_new->getSalesItemAsGst($id, 'igst'); //CGST 
                    //Sales Items Details
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['items_id'] = $SaleItemsRow->items_id;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['code'] = $SaleItemsRow->product_code;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['name'] = $SaleItemsRow->product_name;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['variantname'] = $SaleItemsRow->variant_name;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['gst'] = ($SaleItemsRow->gst) ? substr($SaleItemsRow->gst, 0, -3) : 0;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['hsn_code'] = $SaleItemsRow->hsn_code;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['quantity'] = $SaleItemsRow->quantity;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['unit'] = $SaleItemsRow->unit;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['tax_amt'] = ($SaleItemsRow->item_tax) ? $SaleItemsRow->item_tax : 0;
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['CGST'] = ($datacgst[0]->totalgst) ? 'Rs. ' . $datacgst[0]->totalgst : '0.00';
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['CGST_rate'] = ($datacgst[0]->gstrrate) ? $datacgst[0]->gstrrate . '%' : '0.00';
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['SGST'] = ($datasgst[0]->totalgst) ? 'Rs. ' . $datasgst[0]->totalgst : '0.00';
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['SGST_rate'] = ($datasgst[0]->gstrrate) ? $datasgst[0]->gstrrate . '%' : '0.00';

                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['IGST'] = ($dataigst[0]->totalgst) ? 'Rs. ' . $dataigst[0]->totalgst : '0.00';
                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['IGST_rate'] = ($dataigst[0]->gstrrate) ? $dataigst[0]->gstrrate . '%' : '0.00';

                    $data[$SaleItemsRow->sale_id]['items'][$SaleItemsRow->items_id]['subtotal'] = $SaleItemsRow->subtotal;
                }//end foreach
            }//end if
        } else {
            $data = NULL;
        }

        if (!empty($data)) {
            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:AG1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:AG1');
            $this->excel->getActiveSheet()->SetCellValue('A1', 'GST Sales Report');

            $this->excel->getActiveSheet()->getStyle("A2:AG2")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A2:AG2');
            $this->excel->getActiveSheet()->SetCellValue('A2', 'Date : ' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)));

            $this->excel->getActiveSheet()->setTitle(lang('sales_report'));
            $this->excel->getActiveSheet()->SetCellValue('A3', lang('sr no'));
            $this->excel->getActiveSheet()->SetCellValue('B3', lang('date'));
            $this->excel->getActiveSheet()->SetCellValue('C3', lang('Invoice No'));
            $this->excel->getActiveSheet()->SetCellValue('D3', lang('reference_no'));
            $this->excel->getActiveSheet()->SetCellValue('E3', lang('biller'));
            $this->excel->getActiveSheet()->SetCellValue('F3', lang('customer'));
            $this->excel->getActiveSheet()->SetCellValue('G3', lang('customer') . ' Contacts');
            $this->excel->getActiveSheet()->SetCellValue('H3', lang('gstn'));
            $this->excel->getActiveSheet()->SetCellValue('I3', lang('Grand Total (Rs)'));
            $this->excel->getActiveSheet()->SetCellValue('J3', lang('Taxable Amount (Rs)'));
            $this->excel->getActiveSheet()->SetCellValue('K3', lang('Tax Amount (Rs)'));
            $this->excel->getActiveSheet()->SetCellValue('L3', lang('Paid (Rs)'));
            $this->excel->getActiveSheet()->SetCellValue('M3', lang('Balance (Rs)'));
            $this->excel->getActiveSheet()->SetCellValue('N3', lang('Payment Method'));
            $this->excel->getActiveSheet()->SetCellValue('O3', lang('Payment Status'));

            //Sales Items Detail
            $this->excel->getActiveSheet()->SetCellValue('P3', lang('product_code'));
            $this->excel->getActiveSheet()->SetCellValue('Q3', lang('product_name'));
            $this->excel->getActiveSheet()->SetCellValue('R3', lang('Varient'));
            $this->excel->getActiveSheet()->SetCellValue('S3', lang('hsn_code'));
            $this->excel->getActiveSheet()->SetCellValue('T3', lang('quantity'));
            $this->excel->getActiveSheet()->SetCellValue('U3', lang('unit'));
            $this->excel->getActiveSheet()->SetCellValue('V3', lang('GST Rate (%)'));
            $this->excel->getActiveSheet()->SetCellValue('W3', lang('CGST'));
            $this->excel->getActiveSheet()->SetCellValue('X3', lang('CGST Rate (%)'));
            $this->excel->getActiveSheet()->SetCellValue('Y3', lang('SGST'));
            $this->excel->getActiveSheet()->SetCellValue('Z3', lang('SGST Rate (%)'));
            $this->excel->getActiveSheet()->SetCellValue('AA3', lang('IGST'));
            $this->excel->getActiveSheet()->SetCellValue('AB3', lang('IGST (%)'));
            $this->excel->getActiveSheet()->SetCellValue('AC3', lang('Subtotal (Rs)'));
            $this->excel->getActiveSheet()->SetCellValue('AD3', lang('VAT'));
            $this->excel->getActiveSheet()->SetCellValue('AE3', lang('VAT Rate (%)'));
            $this->excel->getActiveSheet()->SetCellValue('AF3', lang('CESS'));
            $this->excel->getActiveSheet()->SetCellValue('AG3', lang('CESS Rate (%)'));

            $row = 4;
            $cgst = 0;
            $sgst = 0;
            $igst = 0;
            $vat = 0;
            $cess = 0;
            $total = 0;
            $paid = 0;
            $balance = 0;
            $total_taxable_amt = 0;
            $totalSubtotal = 0;
            $sr = ($start) ? ($start - 1) : 0;

            $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

            foreach ($data as $sale_id => $salesdata) {
                $sale_data = (object) $salesdata;
                $VAT = $this->reports_model_new->getVatCess($sale_data->sale_id, "VAT");
                $CESS = $this->reports_model_new->getVatCess($sale_data->sale_id, "CESS");


                $sr++;
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, ($sr));
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $this->sma->hrld($sale_data->date));
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $sale_data->invoice_no);
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $sale_data->reference_no);
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $sale_data->biller);
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $sale_data->customer);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $sale_data->address);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $sale_data->gstn_no);
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $sale_data->grand_total);
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $sale_data->taxable_amt);
                $this->excel->getActiveSheet()->SetCellValue('K' . $row, $sale_data->total_tax);
                $this->excel->getActiveSheet()->SetCellValue('L' . $row, $sale_data->paid);
                $this->excel->getActiveSheet()->SetCellValue('M' . $row, $sale_data->balance);
                $this->excel->getActiveSheet()->SetCellValue('N' . $row, $this->reports_model_new->getpaymentmode($sale_data->sale_id));
                $this->excel->getActiveSheet()->SetCellValue('O' . $row, $sale_data->payment_status);
                if (!empty($sale_data->items)) {
                    foreach ($sale_data->items as $saleitem_id => $salesItemsData) {

                        $sales_items_data = (object) $salesItemsData;
                        $this->excel->getActiveSheet()->SetCellValue('P' . $row, $sales_items_data->code);
                        $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $sales_items_data->name);
                        $this->excel->getActiveSheet()->SetCellValue('R' . $row, $sales_items_data->variantname);
                        $this->excel->getActiveSheet()->SetCellValue('S' . $row, $sales_items_data->hsn_code);

                        $this->excel->getActiveSheet()->SetCellValue('T' . $row, $sales_items_data->quantity);
                        $this->excel->getActiveSheet()->SetCellValue('U' . $row, lang($sales_items_data->unit));
                        $this->excel->getActiveSheet()->SetCellValue('V' . $row, $sales_items_data->gst);

                        $this->excel->getActiveSheet()->SetCellValue('W' . $row, $sales_items_data->CGST);
                        $this->excel->getActiveSheet()->SetCellValue('X' . $row, $sales_items_data->CGST_rate);

                        $this->excel->getActiveSheet()->SetCellValue('Y' . $row, $sales_items_data->SGST);
                        $this->excel->getActiveSheet()->SetCellValue('Z' . $row, $sales_items_data->SGST_rate);

                        $this->excel->getActiveSheet()->SetCellValue('AA' . $row, $sales_items_data->IGST);
                        $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $sales_items_data->IGST_rate);
                        $this->excel->getActiveSheet()->SetCellValue('AC' . $row, $sales_items_data->subtotal);
                        $totalSubtotal += $sales_items_data->subtotal;
                        $row++;
                    }//end foreach
                }//end if.
                $this->excel->getActiveSheet()->SetCellValue('AD' . $row, ($VAT->taxamount) ? 'Rs. ' . $VAT->taxamount : '0.00' );
                $this->excel->getActiveSheet()->SetCellValue('AE' . $row, ($VAT->taxrate) ? $VAT->taxrate . '%' : '0.00' );
                $this->excel->getActiveSheet()->SetCellValue('AF' . $row, ($CESS->taxamount) ? 'Rs. ' . $CESS->taxamount : '0.00' );
                $this->excel->getActiveSheet()->SetCellValue('AG' . $row, ($CESS->taxrate) ? $CESS->taxrate . '%' : '0.00' );
                $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);

                $total += $sale_data->grand_total;
                $paid += $sale_data->paid;
                $total_tax += $sale_data->total_tax;
                $balance += $sale_data->balance;
                $total_taxable_amt += $sale_data->taxable_amt;
            }//end outer foreach

            $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
            $this->excel->getActiveSheet()->getStyle("A" . $row . ":AG" . $row)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

            $this->excel->getActiveSheet()->SetCellValue('H' . $row, 'Total Calculated Value:');
            $this->excel->getActiveSheet()->SetCellValue('I' . $row, $total);
            $this->excel->getActiveSheet()->SetCellValue('J' . $row, $total_taxable_amt);
            $this->excel->getActiveSheet()->SetCellValue('K' . $row, $total_tax);
            $this->excel->getActiveSheet()->SetCellValue('L' . $row, $paid);
            $this->excel->getActiveSheet()->SetCellValue('M' . $row, $balance);
            $this->excel->getActiveSheet()->SetCellValue('AC' . $row, $totalSubtotal);

            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(5);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(40);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('O')->setWidth(30);
            $this->excel->getActiveSheet()->getColumnDimension('P')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('Q')->setWidth(12);
            $this->excel->getActiveSheet()->getColumnDimension('R')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('S')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('T')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('U')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('V')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('W')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('X')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('Y')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('Z')->setWidth(10);
            $this->excel->getActiveSheet()->getColumnDimension('AA')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('AB')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('AC')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('AD')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('AE')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('AF')->setWidth(15);
            $this->excel->getActiveSheet()->getColumnDimension('AG')->setWidth(15);

            $filename = 'GST_sales_report_' . 'Date_' . date('d-m-Y', strtotime($start_date)) . '_to_' . date('d-m-Y', strtotime($end_date));
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            $this->excel->getActiveSheet()->getStyle('F2:F' . $row + 1)->getAlignment()->setWrapText(TRUE);
            header('Content-Type: application/vnd.ms-excel');
//                    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save(str_replace(__FILE__, 'assets/' . $filename . '.xls', __FILE__));
            return 'assets/' . $filename . '.xls';
        }
    }

    /**
     * HSN Code 
     * @return string
     */
    public function hsncodeReport($start_date, $end_date) {

        $this->db->select('(sma_sale_items.hsn_code) as hsn_code,'
                . 'ROUND(sma_sale_items.tax,2) as tax_rate,'
                . ' sum(sma_sale_items.invoice_unit_price * sma_sale_items.quantity) as basic_amount ,'
                . ' format(SUM(sma_sale_items.cgst), 2) as cgst,format(sum(sma_sale_items.sgst), 2) as sgst,'
                . ' format(sum(sma_sale_items.igst), 2) as igst, format(sum(sma_sale_items.sgst + sma_sale_items.cgst + sma_sale_items.igst), 2) as total_gst , '
                . ' sum(sma_sale_items.invoice_total_net_unit_price) as total_sales');
        $this->db->where('sma_sale_items.hsn_code != " "');
        $this->db->join('sma_sales', 'sma_sales.id = sma_sale_items.sale_id');
        $this->db->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');

        $this->db->group_by(['sma_sale_items.hsn_code', 'sma_sale_items.tax']);
        $GSTRate = $this->db->get('sma_sale_items')->result();

        if (!empty($GSTRate)) {
            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:H1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:H1');
            $this->excel->getActiveSheet()->SetCellValue('A1', 'Hsn Code Tax Report ');

            $this->excel->getActiveSheet()->getStyle("A2:H2")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A2:H2');
            $this->excel->getActiveSheet()->SetCellValue('A2', 'Date : ' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)));

            $this->excel->getActiveSheet()->setTitle(lang('Hsn Code Tax Report'));
            $this->excel->getActiveSheet()->SetCellValue('A3', lang('HSN Code'));
            $this->excel->getActiveSheet()->SetCellValue('B3', lang('GST Rate'));
            $this->excel->getActiveSheet()->SetCellValue('C3', lang('Basic Amt.'));
            $this->excel->getActiveSheet()->SetCellValue('D3', lang('CGST'));
            $this->excel->getActiveSheet()->SetCellValue('E3', lang('SGST'));
            $this->excel->getActiveSheet()->SetCellValue('F3', lang('IGST'));
            $this->excel->getActiveSheet()->SetCellValue('G3', lang('Total GST'));
            $this->excel->getActiveSheet()->SetCellValue('H3', lang('Sales Amt'));

            $row = 4;
            $totalBasicAmt = 0;
            $totalcgst = 0;
            $totalsgst = 0;
            $totaligst = 0;
            $totalgst = 0;
            $totalsales = 0;

            foreach ($GSTRate as $data_row) {
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $data_row->hsn_code);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->tax_rate);
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->basic_amount);
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->cgst);
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $data_row->sgst);
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $data_row->igst);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $data_row->total_gst);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $data_row->total_sales);

                $totalBasicAmt += $data_row->basic_amount;
                $totalcgst += $data_row->cgst;
                $totalsgst += $data_row->sgst;
                $totaligst += $data_row->igst;
                $totalgst += $data_row->total_gst;
                $totalsales += $data_row->total_sales;

                $row++;
            }

            $this->excel->getActiveSheet()->SetCellValue('C' . $row, $totalBasicAmt);
            $this->excel->getActiveSheet()->SetCellValue('D' . $row, $totalcgst);
            $this->excel->getActiveSheet()->SetCellValue('E' . $row, $totalsgst);
            $this->excel->getActiveSheet()->SetCellValue('F' . $row, $totaligst);
            $this->excel->getActiveSheet()->SetCellValue('G' . $row, $totalgst);
            $this->excel->getActiveSheet()->SetCellValue('H' . $row, $totalsales);

            $filename = 'Hsn_Code_Tax_Report_' . 'Date_' . date('d-m-Y', strtotime($start_date)) . '_to_' . date('d-m-Y', strtotime($end_date));
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            header('Content-Type: application/vnd.ms-excel');
//            header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save(str_replace(__FILE__, 'assets/' . $filename . '.xls', __FILE__));
            return 'assets/' . $filename . '.xls';
        }
    }

    /**
     * Tax Reports
     */
    public function taxReports($start_date, $end_date) {
        $this->db->select('ROUND(sma_sale_items.tax,2) as tax_rate,'
                . ' sum(sma_sale_items.invoice_unit_price * sma_sale_items.quantity ) as basic_amount ,'
                . ' SUM(sma_sale_items.sgst) as sgst,sum(sma_sale_items.cgst) as cgst,'
                . ' sum(sma_sale_items.igst) as igst, sum(sma_sale_items.sgst + sma_sale_items.cgst + sma_sale_items.igst) as total_gst , '
                . 'sum(sma_sale_items.invoice_total_net_unit_price) as total_sales');

        $this->db->where('sma_sale_items.gst_rate >  0');
        if (isset($start_date) && isset($end_date)) {
            $this->db->join('sma_sales', 'sma_sales.id = sma_sale_items.sale_id');
            $this->db->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . $start_date . '" and "' . $end_date . '"');
        }
        $GSTRate = $this->db->group_by('sma_sale_items.tax')->get('sma_sale_items')->result();

        if (!empty($GSTRate)) {
            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:H1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:H1');
            $this->excel->getActiveSheet()->SetCellValue('A1', 'Tax Report');

            $this->excel->getActiveSheet()->getStyle("A2:H2")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A2:H2');
            $this->excel->getActiveSheet()->SetCellValue('A2', 'Date : ' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)));

            $this->excel->getActiveSheet()->setTitle(lang('Tax Report'));
            $this->excel->getActiveSheet()->SetCellValue('A3', lang('Sr. No.'));
            $this->excel->getActiveSheet()->SetCellValue('B3', lang('GST Rate'));
            $this->excel->getActiveSheet()->SetCellValue('C3', lang('Taxable Amt.'));
            $this->excel->getActiveSheet()->SetCellValue('D3', lang('SGST'));
            $this->excel->getActiveSheet()->SetCellValue('E3', lang('CGST'));
            $this->excel->getActiveSheet()->SetCellValue('F3', lang('IGST'));
            $this->excel->getActiveSheet()->SetCellValue('G3', lang('Total GST'));
            $this->excel->getActiveSheet()->SetCellValue('H3', lang('Sales Amt.'));


            $row = 4;
            $totalbasic = $totalSGST = $totalCGST = $totalIGST = $totalGST = $totalsales = 0;

            foreach ($GSTRate as $key => $data_row) {
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $key + 1);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->tax_rate . '%');
                $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->basic_amount);
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->sgst);
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $data_row->cgst);
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $data_row->igst);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $data_row->total_gst);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $data_row->total_sales);

                $totalbasic += $data_row->basic_amount;
                $totalSGST += $data_row->sgst;
                $totalCGST += $data_row->cgst;
                $totalIGST += $data_row->igst;
                $totalGST += $data_row->total_gst;
                $totalsales += $data_row->total_sales;

                $row++;
            }


            $this->excel->getActiveSheet()->SetCellValue("A" . $row, "");
            $this->excel->getActiveSheet()->SetCellValue("B" . $row, "Total");
            $this->excel->getActiveSheet()->SetCellValue("C" . $row, $totalbasic);
            $this->excel->getActiveSheet()->SetCellValue("D" . $row, $totalSGST);
            $this->excel->getActiveSheet()->SetCellValue("E" . $row, $totalCGST);
            $this->excel->getActiveSheet()->SetCellValue("F" . $row, $totalIGST);
            $this->excel->getActiveSheet()->SetCellValue("G" . $row, $totalGST);
            $this->excel->getActiveSheet()->SetCellValue("H" . $row, $totalsales);



            $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
            $this->excel->getActiveSheet()->getColumnDimension('H')->setWidth(20);


            $filename = 'Tax Report_' . 'Date_' . date('d-m-Y', strtotime($start_date)) . '_to_' . date('d-m-Y', strtotime($end_date));
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

//            ob_clean();
            header('Content-Type: application/vnd.ms-excel');
//            header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
//            ob_clean();
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save(str_replace(__FILE__, 'assets/' . $filename . '.xls', __FILE__));
            return 'assets/' . $filename . '.xls';
        }
    }

    /**
     * End Report Send
     * */
    public function ajax_report_request_url() {

        $action = $this->input->post('action');

        switch ($action) {
            case "sales_extended_report_result":

                $this->sales_extended_report_result();

                break;

            default:
                $data = array('status' => '404', 'message' => 'Invalid Request');
                echo json_encode($data);
                break;
        }
    }

    /* * ******************New Gst Ajax Reports************************ */

    public function gst_reports() {

        $this->sma->checkPermissions('gst_reports',TRUE, 'reports');
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['billers'] = (array)$this->site->getAllCompanies('biller');
        $this->data['suppliers'] = (array)$this->site->getAllCompanies('supplier');
        $this->data['customers'] = $this->site->getAllCompanies('customer');     

         $this->data['customer_group'] = $this->site->getcustomerGroup();  

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('monthly_gst_report')));
        $meta = array('page_title' => lang('gst_report'), 'bc' => $bc);
        $this->page_construct('new_reports/gst_reports', $meta, $this->data);
    }

    

    public function request_gst_report($type = NULL, $year = NULL, $month = NULL, $xls = NULL, $warehouse = NULL, $biller = NULL, $supplier = NULL, $sale_items = FALSE, $customer = NULL, $start_date = NULL,  $end_date = NULL, $customer_group = NULL) {

        $type = $type ? $type : (isset($_GET['type']) ? $_GET['type'] : 'sale_gst_report');
        $year = $year ? $year : (isset($_GET['year']) ? $_GET['year'] : date('Y'));
        $month = ($month) ? $month : (isset($_GET['month']) ? $_GET['month'] : '01');
        $warehouse = ($warehouse) ? $warehouse : (isset($_GET['warehouse']) ? $_GET['warehouse'] : NULL);
        $biller = ($biller) ? $biller : (isset($_GET['biller']) ? $_GET['biller'] : NULL);
        $supplier = ($supplier) ? $supplier : (isset($_GET['supplier']) ? $_GET['supplier'] : NULL);
        $customer = ($customer) ? $customer : (isset($_GET['customer']) ? $_GET['customer'] : NULL);
                 
           $start_date = ($start_date) ? $start_date : (isset($_GET['start_date']) ? $_GET['start_date'] : NULL);
        $end_date = ($end_date) ? $end_date : (isset($_GET['end_date']) ? $_GET['end_date'] : NULL);
        $customer_group = ($customer_group) ? $customer_group : (isset($_GET['customer_group']) ? $_GET['customer_group'] : NULL);
                 
        if ($start_date) {
           $start_date = $this->sma->fld($start_date);
           $end_date = $this->sma->fld($end_date);
        }

 
        if ($type == "sale_gst_report") {
            
            $sale_items = $sale_items ? TRUE : (isset($_GET['sale_items']) && (bool)$_GET['sale_items'] ? TRUE : FALSE );
                        
            $data = $this->reports_model_new->getSaleGstData($year, $month, $warehouse, $biller, $sale_items, $customer, $start_date, $end_date, $customer_group );
            

            if (!empty($data)) {
                $warehouses = $this->site->getAllWarehouses();
                foreach ($warehouses as $warehouse) {
                    $wh[$warehouse->id] = $warehouse->name;
                }

                $i = 0;
                foreach ($data as $row) {
                   
                    $i++;
                    $rowData[$row->sale_id]['sr']               = $i;
                    $rowData[$row->sale_id]['sale_id']          = $row->sale_id;
                    $rowData[$row->sale_id]['invoice_no']       = $row->invoice_no;                    
                    $rowData[$row->sale_id]['sale_status']      = $row->sale_status;
                    $rowData[$row->sale_id]['date']             = $row->date;
                    $rowData[$row->sale_id]['customer_id']      = $row->customer_id;
                    $rowData[$row->sale_id]['customer']         = $row->customer;
                    $rowData[$row->sale_id]['customer_group_name'] = $row->customer_group_name;
                    $rowData[$row->sale_id]['note']         = $row->note;
                     $rowData[$row->sale_id]['state_code']         = $row->state_code;
                    
                    $rowData[$row->sale_id]['biller_id']        = $row->biller_id;
                    $rowData[$row->sale_id]['biller']           = $row->biller;
                    $rowData[$row->sale_id]['gstn_no']          = $row->gstn_no;
                    $rowData[$row->sale_id]['grand_total']      = $row->grand_total;
                    $rowData[$row->sale_id]['total_discount']   = $row->total_discount;
                    $rowData[$row->sale_id]['total_tax']        = $row->total_tax;
                    $rowData[$row->sale_id]['sale_status']      = $row->sale_status;
                    $rowData[$row->sale_id]['warehouse']        = $wh[$row->warehouse_id];
                    $rowData[$row->sale_id]['payment_status']   = $row->payment_status;
                    $rowData[$row->sale_id]['payment_term']     = $row->payment_term;                        
                    $rowData[$row->sale_id]['payment_method']     = $row->payment_method;                        
                  
                    $rowData[$row->sale_id]['total_weight']     = $row->total_weight;
                    $rowData[$row->sale_id]['reference_no']     = $row->reference_no;
                                    
                     $rowData[$row->sale_id]['state_code']      = $row->state_code;
                    $rowData[$row->sale_id]['total_quantity']   += $row->quantity;
                    
                    $rowData[$row->sale_id]['taxable_amount']   += $row->taxable_amount;
                    $rowData[$row->sale_id]['total_tax_amount'] += ($row->cgst + $row->sgst + $row->igst);
                    $rowData[$row->sale_id]['total_extra']      += ($row->shipping + $row->rounding);
                    $rowData[$row->sale_id]['invoice_amount']   += (($row->cgst + $row->sgst + $row->igst) + $row->taxable_amount);
                   
                    
                    
                    if($sale_items == TRUE){
                        $rowData[$row->sale_id]['total_items']      += 1;
                        
                        $rowData[$row->sale_id]['items'][$i]['taxable_amount']   = $row->taxable_amount;
                        $rowData[$row->sale_id]['items'][$i]['gst_rate']         = $row->gst_rate;
                        $rowData[$row->sale_id]['items'][$i]['cgst']             = $row->cgst;
                        $rowData[$row->sale_id]['items'][$i]['sgst']             = $row->sgst;
                        $rowData[$row->sale_id]['items'][$i]['igst']             = $row->igst;
                        $rowData[$row->sale_id]['items'][$i]['tax_total']        = ($row->cgst + $row->sgst + $row->igst);
                        $rowData[$row->sale_id]['items'][$i]['product_id']       = $row->product_id;
                        $rowData[$row->sale_id]['items'][$i]['product_code']     = $row->product_code;
                        $rowData[$row->sale_id]['items'][$i]['product_name']     = $row->product_name;
                        $rowData[$row->sale_id]['items'][$i]['option_name']      = $row->option_name;
                        $rowData[$row->sale_id]['items'][$i]['quantity']         = $row->quantity;
                        $rowData[$row->sale_id]['items'][$i]['item_discount']    = $row->item_discount;
                        $rowData[$row->sale_id]['items'][$i]['tax_rate']         = $row->tax_rate;
                        $rowData[$row->sale_id]['items'][$i]['hsn_code']         = $row->hsn_code;
                        $rowData[$row->sale_id]['items'][$i]['net_unit_price']   = $row->net_unit_price; 
                    } else {
                        $rowData[$row->sale_id]['gst'][$i]['total_items']    = $row->total_items;
                        $rowData[$row->sale_id]['gst'][$i]['tax_rate']       = $row->tax_rate;
                        $rowData[$row->sale_id]['gst'][$i]['taxable_amount'] = $row->taxable_amount;
                        $rowData[$row->sale_id]['gst'][$i]['gst_rate']       = $row->gst_rate;
                        $rowData[$row->sale_id]['gst'][$i]['cgst']           = $row->cgst;
                        $rowData[$row->sale_id]['gst'][$i]['sgst']           = $row->sgst;
                        $rowData[$row->sale_id]['gst'][$i]['igst']           = $row->igst;
                        $rowData[$row->sale_id]['gst'][$i]['tax_total']      = ($row->cgst + $row->sgst + $row->igst);
                    }
                    
                    
                    // $rowFooter['total_extra']       += ($row->shipping + $row->rounding);
                    $rowFooter['taxable_amount'] += $row->taxable_amount;
                    $rowFooter['total_tax_amount'] += ($row->cgst + $row->sgst + $row->igst);
                    $rowFooter['cgst'] += $row->cgst;
                    $rowFooter['sgst'] += $row->sgst;
                    $rowFooter['igst'] += $row->igst;
                }

                if ($xls !== NULL) {
                    if($sale_items == TRUE){
                        $this->__sale_items_gst_report_download($rowData, $rowFooter, $sale_items);
                    } else {
                        $this->__sale_gst_report_download($rowData, $rowFooter, $sale_items);
                    }
                } else {
                    $report_title = "sale_gst_report Month: $month  Year : $year";
                    $this->__sale_gst_report($rowData, $rowFooter, $sale_items);
                }
            }
        }
        
        if($type == "purchase_gst_report") {
            
            $data = $this->reports_model_new->getPurchaseGstData($year, $month, $warehouse, $supplier , $start_date, $end_date);
                       
             if (!empty($data)) {
                $warehouses = $this->site->getAllWarehouses();
                foreach ($warehouses as $warehouse) {
                    $wh[$warehouse->id] = $warehouse->name;
                }

                $i = 0;
                foreach ($data as $row) {
                    $i++;
                    $rowData[$row->purchase_id]['sr'] = $i;
                    $rowData[$row->purchase_id]['reference_no'] = $row->reference_no;
                    $rowData[$row->purchase_id]['status'] = $row->status;
                    $rowData[$row->purchase_id]['date'] = $row->date;
                    
                    $rowData[$row->purchase_id]['supplier_id'] = $row->supplier_id;
                    $rowData[$row->purchase_id]['supplier'] = $row->supplier;
                    $rowData[$row->purchase_id]['state_code'] = $row->state_code;
                    $rowData[$row->purchase_id]['gstn_no'] = $row->gstn_no;
                    $rowData[$row->purchase_id]['grand_total'] = $row->grand_total;
                    $rowData[$row->purchase_id]['total_discount'] = $row->total_discount;
                    $rowData[$row->purchase_id]['total_tax'] = $row->total_tax;
                    $rowData[$row->purchase_id]['sale_status'] = $row->sale_status;
                    $rowData[$row->purchase_id]['warehouse'] = $wh[$row->warehouse_id];

                    $rowData[$row->purchase_id]['total_items'] += $row->total_items;
                    $rowData[$row->purchase_id]['taxable_amount'] += $row->taxable_amount;
                    $rowData[$row->purchase_id]['total_tax_amount'] += ($row->cgst + $row->sgst + $row->igst);
                    $rowData[$row->purchase_id]['total_extra'] += ($row->shipping + $row->surcharge + $row->rounding);
                    $rowData[$row->purchase_id]['bill_amount'] += (($row->cgst + $row->sgst + $row->igst) + $row->taxable_amount);

                    $rowData[$row->purchase_id]['gst'][$i]['taxable_amount'] = $row->taxable_amount;
                    $rowData[$row->purchase_id]['gst'][$i]['gst_rate'] = $row->gst_rate;
                    $rowData[$row->purchase_id]['gst'][$i]['cgst'] = $row->cgst;
                    $rowData[$row->purchase_id]['gst'][$i]['sgst'] = $row->sgst;
                    $rowData[$row->purchase_id]['gst'][$i]['igst'] = $row->igst;

                    // $rowFooter['total_extra']       += ($row->shipping + $row->rounding);
                    $rowFooter['taxable_amount'] += $row->taxable_amount;
                    $rowFooter['total_tax_amount'] += ($row->cgst + $row->sgst + $row->igst);
                    $rowFooter['cgst'] += $row->cgst;
                    $rowFooter['sgst'] += $row->sgst;
                    $rowFooter['igst'] += $row->igst;
                }

                if ($xls !== NULL) {
                    $report_title = "purchase_gst_report Month: $month  Year : $year";
                    $this->__purchase_gst_report_download($rowData, $rowFooter);
                } else {                    
                    $this->__purchase_gst_report($rowData, $rowFooter, $report_title);
                }
            }
            
        }
    }

   private function __sale_gst_report($rowData, $rowFooter, $sale_items) {

        $table = '<table id="SlRData" class="table table-bordered table-hover table-striped table-condensed reports-table">
                        <thead>
                        <tr>
                            <th rowspan="2">Sr. No.</th>
                            <th rowspan="2">' . lang("Invoice_no") . '</th>
                            <th rowspan="2">' . lang("date") . '</th>
                            <th rowspan="2">' . lang("customer") . '</th>
                            <th rowspan="2">' . lang("Customer Group") . '</th>
                            <th rowspan="2">' . lang("Remark") . '</th>
                            <th rowspan="2">' . lang("State Name") . '</th>  
                            <th rowspan="2">' . lang("GSTN_Number") . '</th>
                            <th rowspan="2">' . lang("Taxable Amount") . '</th>';
        
                if($sale_items) {           
                    $table .= ' <th colspan="7">' . lang("Sale Items") . '</th> ';
                } else {
                    $table .= '<th rowspan="2">' . lang("Tax Rate") . '</th>';
                    $table .= '<th rowspan="2">' . lang("Items") . '</th>';
                }   
                
                $table .= ' <th colspan="2">' . lang("CGST") . '</th>
                            <th colspan="2">' . lang("SGST") . '</th>
                            <th colspan="2">' . lang("IGST") . '</th>
                            <th rowspan="2">' . lang("Tax Total") . '</th>                            
                            <th rowspan="2">' . lang("Invoice Total GST") . '</th>                            
                            <th rowspan="2">' . lang("Invoice Amount") . '</th>
                            <th rowspan="2">' . lang("Invoice Status") . '</th> 
                        </tr>
                        <tr> '; 
            
            if($sale_items) {                
                $table .= '<th>Product Name</th> 
                           <th>Variant</th> 
                           <th>Quantity</th> 
                           <th>Unit Price</th> 
                           <th>Disc.</th> 
                           <th>Tax Rate</th> 
                           <th>HSN Code</th>'; 
            }
            
            $table .= ' <th>%</th>
                        <th>Amt.</th>
                        <th>%</th>
                        <th>Amt.</th>
                        <th>%</th>
                        <th>Amt.</th>                         
                     </tr>
                </thead>
            <tbody>';

        if (!empty($rowData)) {

            $sr = 0;
            foreach ($rowData as $row_id => $row) {
                $sr++;

               
                $taxRow = ($sale_items == TRUE) ? count($row['items']) : count($row['gst']);
                $invoiceRowStart = '<tr data-rowid="' . $row_id . '">
                            <td rowspan="' . $taxRow . '">' . $sr . '</td>
                            <td rowspan="' . $taxRow . '">' . trim($row['invoice_no']) . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['date'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['customer'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['customer_group_name'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['note'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['state_code'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['gstn_no'] . '</td>
                            <td rowspan="' . $taxRow . '" align="right">' . number_format($row['taxable_amount'], 4) . '</td>';
                
                if($sale_items == TRUE){
                    $si = 0; $itemTds  = '';
                    foreach ($row['items'] as $item) {

                        $si++;
                         
                        $itemTds  = '<td>'.$item['product_name'].'</td>';
                        $itemTds .= '<td>'.$item['option_name'].'</td>';
                        $itemTds .= '<td>'.$item['quantity'].'</td>';
                        $itemTds .= '<td>'.$item['net_unit_price'].'</td>';
                        $itemTds .= '<td>'.$item['item_discount'].'</td>';
                        $itemTds .= '<td>'.number_format($item['tax_rate'],2).'%</td>';
                        $itemTds .= '<td>'.$item['hsn_code'].'</td>';
                        
                        $itemTds .= '<td>' . (($item['cgst'] > 0) ? number_format($item['gst_rate'], 2) . '%' : '00') . '</td>
                                    <td align="right">' . $item['cgst'] . '</td>
                                    <td>' . (($item['sgst'] > 0) ? number_format($item['gst_rate'], 2) . '%' : '00') . '</td>
                                    <td align="right">' . $item['sgst'] . '</td>
                                    <td>' . (($item['igst'] > 0) ? number_format($item['gst_rate'], 2) . '%' : '00') . '</td>
                                    <td align="right">' . $item['igst'] . '</td> 
                                    <td align="right">'.  $item['tax_total'] . '</td>';

                        if ($si > 1) {
                            $nextTaxRow .= '<tr data-rowid="' . $row_id . '">' . $itemTds . '</tr>';
                        } else {
                            $firstTaxRow = $itemTds;
                            $nextTaxRow = '';
                        }
                    }//end foreach
                    $footTh = '<th colspan="7"></th>';
                } else {
                    $footTh = '<th></th><th></th>';
                    $tx = 0;
                    foreach ($row['gst'] as $tax) {

                        $tx++;
                        $td_taxes = '<td>'.number_format($tax['tax_rate'], 2).'%</td> 
                                        <td>'.$tax['total_items'].'</td> 
                                        <td>' . (($tax['cgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00') . '</td>
                                        <td align="right">' . $tax['cgst'] . '</td>
                                        <td>' . (($tax['sgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00') . '</td>
                                        <td align="right">' . $tax['sgst'] . '</td>
                                        <td>' . (($tax['igst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00') . '</td>
                                        <td align="right">' . $tax['igst'] . '</td> 
                                        <td align="right">' . ($tax['cgst']+$tax['sgst']+$tax['igst']) . '</td>';

                        if ($tx > 1) {
                            $nextTaxRow .= '<tr data-rowid="' . $row_id . '">' . $td_taxes . '</tr>';
                        } else {
                            $firstTaxRow = $td_taxes;
                            $nextTaxRow = '';
                        }
                    }//end foreach
                }//end else 
                
                $invoiceRowEnd = '<td rowspan="' . $taxRow . '" align="right">' . number_format($row['total_tax_amount'], 2) . '</td>
                                <td rowspan="' . $taxRow . '" align="right">' . number_format($row['taxable_amount'] + $row['total_tax_amount'], 2) . '</td>
                                <td rowspan="' . $taxRow . '" align="right">' . $row['sale_status'] . '</td>  
                            </tr>';
                
                $table .= $invoiceRowStart . $firstTaxRow . $invoiceRowEnd . $nextTaxRow;
            }
        } else {

            $table .= '<tr><td colspan="' . $col . '" class="empty_table_body text-danger">Requested Data Not Available.</td></tr>';
        }

        $table .= ' </tbody>
                        <tfoot class="dtFilter">
                        <tr class="bg-primary">
                            <th>&nbsp;</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>                             
                            <th></th>                           
                            '.$footTh.'
                            <th style="color:#ffffff;">CGST</th>
                            <th style="color:#ffffff;" title="Total CGST Amount">' . number_format($rowFooter['cgst'], 2) . '</th>
                            <th style="color:#ffffff;">SGST</th>
                            <th style="color:#ffffff;" title="Total SGST Amount">' . number_format($rowFooter['sgst'], 2) . '</th>
                            <th style="color:#ffffff;">IGST</th>                            
                            <th style="color:#ffffff;" title="Total IGST Amount">' . number_format($rowFooter['igst'], 2) . '</th>
                            <th style="color:#ffffff;">' . number_format(($rowFooter['cgst']+$rowFooter['sgst']+$rowFooter['igst']), 2) . '</th>
                            <th style="color:#ffffff;" title="Total Tax Amount">' . number_format($rowFooter['total_tax_amount'], 2) . '</th>
                            <th style="color:#ffffff;" title="Total Invoice Amount">' . number_format($rowFooter['taxable_amount'] + $rowFooter['total_tax_amount'], 2) . '</th>
                            <th></th>                             
                        </tr>
                        </tfoot>
                    </table>';

        echo $table;
    }

    
    private function __sale_items_gst_report_download($rowData, $rowFooter, $sale_items) {

        if (!empty($rowData)) {

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:AF1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:AF1', 'GST Sales Report');

            $this->excel->getActiveSheet()->setTitle(lang('sale_gst_report'));

            // $this->excel->getActiveSheet()->SetCellValue('A1', $report_title);

            $cellColoumnTitle = ['A' => 'Sr No',
                'B' => 'Invoice No',
                'C' => 'Invoice Date',
                'D' => 'Customer Name',
                'E' => 'Customer Group',
                'F' => 'Remark',
                'G' => 'State Name',
                'H' => 'Customer GSTN',               
                'I' => 'product Name',
                'J' => 'product Code',
                'K' => 'Variant Name',
                'L' => 'Quantity',
                'M' => 'Unit Price',
                'N' => 'Discount',
                'O' => 'Tax Rate',
                'P' => 'HSN Code',                
                'Q' => 'CGST %',
                'R' => 'CGST Amt',
                'S' => 'SGST %',
                'T' => 'SGST Amt',
                'U' => 'IGST %',
                'V' => 'IGST Amt',
                
                'W' => "Product's Tax Amount",
                'X' => 'Total Taxable Amount',
                'Y' => 'Invoice Tax Amount',
                'Z' => 'Total Amount',
                'AA' => 'Invoice Status',
                'AB' => 'Biller Name',
                'AC' => 'Warehouse',
                'AD' => 'Reference No',
                'AE' => 'Payment Status',
                'AF' => 'Payment Term',
                'AG' => 'Total Quantity',
                'AH' => 'Total Weight',
                'AI' => 'Payment Mode',
                'AJ' => 'Time',
            ];

    
            
            foreach ($cellColoumnTitle as $cellkey => $cellTitle) {

                $this->excel->getActiveSheet()->SetCellValue($cellkey . '2', $cellTitle);
            }

            $cellData = [
                'A' => '',
                'B' => 'invoice_no',
                'C' => 'date',
                'D' => 'customer',
                'E' => 'customer_group_name',
                'F' => 'note',
                'G' => 'state_code',
                'H' => 'gstn_no',              
                'I' => 'product_name',
                'J' => 'product_code',
                'K' => 'option_name',
                'L' => 'quantity',
                'M' => 'net_unit_price',
                'N' => 'item_discount',
                'O' => 'tax_rate',
                'P' => 'hsn_code',
                'Q' => 'gst_rate',
                'R' => 'cgst',
                'S' => 'gst_rate',
                'T' => 'sgst',
                'U' => 'gst_rate',
                'V' => 'igst',
               
                'W' => 'tax_total',
                 'X' => 'taxable_amount',
                'Y' => 'total_tax_amount',
                'Z' => 'invoice_amount',
                'AA' => 'sale_status',
                'AB' => 'biller',
                'AC' => 'warehouse',
                'AD' => 'reference_no',
                'AE' => 'payment_status',
                'AF' => 'payment_term',
                'AG' => 'total_quantity',
                'AH' => 'total_weight',
                'AI' => 'payment_method',
                'AJ' => 'time',
            ];

            $row = 3;
            $sr = 0;
            $total_cgst = $total_sgst = $total_igst = $total_tax_amount = $total_taxable_amount
                    = $toal_invoice_tax_amount = $total_invoice_amount = 0;
            foreach ($rowData as $sale_id => $inv) {
                
                 $total_taxable_amount += $inv[$cellData['X']];
                 $toal_invoice_tax_amount  += $inv[$cellData['Y']];
                 $total_invoice_amount += $inv[$cellData['Z']];
               
                $sr++;
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);

                foreach (range('B', 'G') as $cell_1) {
                    $this->excel->getActiveSheet()->SetCellValue($cell_1 . $row, ( ($cell_1 == 'C')? date('Y-m-d',strtotime($inv[$cellData[$cell_1]])) :$inv[$cellData[$cell_1]]));
                }//end foreach

                foreach (range('V', 'Z') as $cell_3) {
                    $this->excel->getActiveSheet()->SetCellValue($cell_3 . $row, $inv[$cellData[$cell_3]]);
                }//end foreach
                
                foreach (range('A', 'J') as $cell_Ax) {
                    $this->excel->getActiveSheet()->SetCellValue('A'.$cell_Ax . $row, $inv[$cellData['A'.$cell_Ax]]);
                }//end foreach

                
                $tx = 0;
                $tax_row = '';
                foreach ($inv['items'] as $item) {

                    $tx++;
                    $tax_row = ($tx == 1) ? $row : ++$row;
                    
                    $this->excel->getActiveSheet()->SetCellValue('I' . $tax_row, $item['product_name']);
                    $this->excel->getActiveSheet()->SetCellValue('J' . $tax_row, $item['product_code']);
                    $this->excel->getActiveSheet()->SetCellValue('K' . $tax_row, $item['option_name']);
                    $this->excel->getActiveSheet()->SetCellValue('L' . $tax_row, $item['quantity']);
                    $this->excel->getActiveSheet()->SetCellValue('M' . $tax_row, $item['net_unit_price']);
                    $this->excel->getActiveSheet()->SetCellValue('N' . $tax_row, $item['item_discount']);
                    $this->excel->getActiveSheet()->SetCellValue('O' . $tax_row, $item['tax_rate']);
                    $this->excel->getActiveSheet()->SetCellValue('P' . $tax_row, $item['hsn_code']);
                                        
                    $cgst_rate = (($item['cgst'] > 0) ? number_format($item['gst_rate'], 2) . '%' : '00');
                    $sgst_rate = (($item['sgst'] > 0) ? number_format($item['gst_rate'], 2) . '%' : '00');
                    $igst_rate = (($item['igst'] > 0) ? number_format($item['gst_rate'], 2) . '%' : '00');

                    $this->excel->getActiveSheet()->SetCellValue('Q' . $tax_row, $cgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('R' . $tax_row, $item['cgst']);
                    $this->excel->getActiveSheet()->SetCellValue('S' . $tax_row, $sgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('T' . $tax_row, $item['sgst']);
                    $this->excel->getActiveSheet()->SetCellValue('U' . $tax_row, $igst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('V' . $tax_row, $item['igst']);
                    $this->excel->getActiveSheet()->SetCellValue('W' . $tax_row, $item['tax_total']);

                    $total_cgst += $item['cgst'];
                    $total_sgst += $item['sgst'];
                    $total_igst += $item['igst']; 
                    $total_tax_amount += $item['tax_total'];

      
                }//end foreach
                $row++;
                $this->excel->getActiveSheet()->getStyle("A" . $row . ":AJ" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
            }

            
             $this->excel->getActiveSheet()->mergeCells('A'.$row.':O'.$row);
             $this->excel->getActiveSheet()->SetCellValue('A' . $row , 'Total');
        
             $this->excel->getActiveSheet()->SetCellValue('R' . $row , $total_cgst);
             $this->excel->getActiveSheet()->SetCellValue('T' . $row , $total_sgst);
             $this->excel->getActiveSheet()->SetCellValue('V' . $row , $total_igst);
            
            $this->excel->getActiveSheet()->SetCellValue('W' . $row , $total_tax_amount);
            
            $this->excel->getActiveSheet()->SetCellValue('X' . $row , $total_taxable_amount);
            $this->excel->getActiveSheet()->SetCellValue('Y' . $row , $toal_invoice_tax_amount);
            $this->excel->getActiveSheet()->SetCellValue('Z' . $row , $total_invoice_amount);
            
            $this->excel->getActiveSheet()->getStyle("A" . $row++ . ":AJ" . $row++)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

            foreach (range('B', 'G') as $cell_4) {
                $this->excel->getActiveSheet()->getColumnDimension($cell_4)->setWidth(20);
            }//end foreach

            $filename = 'gst_sale_items_report';
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            //$this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
            ob_clean();
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            ob_clean();
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save('php://output');
            exit();
        }
    }
    
     private function __sale_gst_report_download($rowData, $rowFooter) {

        if (!empty($rowData)) {

         
            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:Z1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:Z1', 'GST Sales Report');

            $this->excel->getActiveSheet()->setTitle(lang('sale_gst_report'));

            // $this->excel->getActiveSheet()->SetCellValue('A1', $report_title);

            $cellColoumnTitle = ['A' => 'Sr No',
                'B' => 'Invoice No',
                'C' => 'Invoice Date',
                'D' => 'Customer Name',
                'E' => 'Customer Group',
                'F' => 'Remark',
                'G' => 'State Name',
                'H' => 'Customer GSTN',
                'I' => 'Tax Rate',
                'J' => 'Items',
                'K' => 'CGST %',
                'L' => 'CGST Amt',
                'M' => 'SGST %',
                'N' => 'SGST Amt',
                'O' => 'IGST %',
                'P' => 'IGST Amt',
                'Q' => 'Total Taxable Amount',
                'R' => "Product's  Tax Amount",
                'S' => 'Invoice Tax Amount',
                'T' => 'Total Discount',
                'U' => 'Total Amount',
                'V' => 'Invoice Status',
                'W' => 'Biller Name',
                'X' => 'Warehouse',
                'Y' => 'Payment Status',
                'Z' => 'Payment term',
                'AA' => 'Total Weight',
                'AB' => 'Reference Number',               
                'AC' => 'Payment Mode',
                'AD' => 'Time',
               
            ];

          
            foreach ($cellColoumnTitle as $cellkey => $cellTitle) {

                $this->excel->getActiveSheet()->SetCellValue($cellkey . '2', $cellTitle);
            }
           // $this->excel->getActiveSheet()->getStyle("A" . $row . ":Y" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
           

            $cellData = [
                'A' => '',
                'B' => 'invoice_no',
                'C' => 'date',
                'D' => 'customer',
                'E' => 'customer_group_name',
                'F' => 'note',
                'G' => 'state_code',
                'H' => 'gstn_no',
                'I' => 'tax_rate',
                'J' => 'total_items',
                'K' => 'gst_rate',
                'L' => 'cgst',
                'M' => 'gst_rate',
                'N' => 'sgst',
                'O' => 'gst_rate',
                'P' => 'igst',
                'Q' => 'taxable_amount',
                'R' => 'tax_total',                
                'S' => 'total_tax_amount',
                'T' => 'total_discount',
                'U' => 'invoice_amount',
                'V' => 'sale_status',
                'W' => 'biller',
                'X' => 'warehouse',
                'Y' => 'payment_status',
                'Z' => 'payment_term',
                'AA' => 'total_weight',
                'AB' => 'reference_no',              
                'AC' => 'payment_method',
                'AD' => 'date',
                
            ];

            $row = 3;
            $sr = 0;
            $total_tax_amount =  $total_product_tax_amount = $total_invoice_tax_amount
                    = $total_discount = $total_amount = $total_cgst =$total_sgst = $total_igst=0 ;

            foreach ($rowData as $invoice_no => $inv) {
                // $parts = explode(' ', $inv['date']);
                // $time = $parts[1];
               
               $total_tax_amount += $inv[$cellData['Q']];
               $date += $inv[$cellData];
               $total_invoice_tax_amount += $inv[$cellData['S']];
               $total_discount += $inv[$cellData['T']];
               $total_amount += $inv[$cellData['U']];
             
                $sr++;
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);

                foreach (range('B', 'G') as $cell_1) {
                    $this->excel->getActiveSheet()->SetCellValue($cell_1 . $row, (($cell_1 == 'C')? date('Y-m-d',strtotime($inv[$cellData[$cell_1]])) :$inv[$cellData[$cell_1]]));
                }//end foreach
             
                foreach (range('Q', 'Z') as $cell_3) {
                    $this->excel->getActiveSheet()->SetCellValue($cell_3 . $row, $inv[$cellData[$cell_3]]);
                }//end foreach
 
               $this->excel->getActiveSheet()->SetCellValue('P' . $row, $inv[$cellData['P']]);
               $this->excel->getActiveSheet()->SetCellValue('AB' . $row, $inv[$cellData['AB']]);
               $this->excel->getActiveSheet()->SetCellValue('AC' . $row, $inv[$cellData['AC']]);
               $this->excel->getActiveSheet()->SetCellValue('AD' . $row, $inv[$cellData['AD']]);
                $tx = 0;
                $tax_row = '';
                foreach ($inv['gst'] as $tax) {

                    $tx++;
                    $tax_row = ($tx == 1) ? $row : ++$row;
                    
                    $this->excel->getActiveSheet()->SetCellValue('I' . $tax_row, $tax['tax_rate']);
                    $this->excel->getActiveSheet()->SetCellValue('J' . $tax_row, $tax['total_items']);
                    
                    $cgst_rate = (($tax['cgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00');
                    $sgst_rate = (($tax['sgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00');
                    $igst_rate = (($tax['igst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00');

                    $this->excel->getActiveSheet()->SetCellValue('K' . $tax_row, $cgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('L' . $tax_row, $tax['cgst']);
                    $this->excel->getActiveSheet()->SetCellValue('M' . $tax_row, $sgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('N' . $tax_row, $tax['sgst']);
                    $this->excel->getActiveSheet()->SetCellValue('O' . $tax_row, $igst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('P' . $tax_row, $tax['igst']);
                    $this->excel->getActiveSheet()->SetCellValue('Q' . $tax_row, $tax['taxable_amount']);
                    $this->excel->getActiveSheet()->SetCellValue('R' . $tax_row, $tax['tax_total']);
                     $total_product_tax_amount += $tax['tax_total'];
                     
                     $total_cgst += $tax['cgst'];
                     $total_sgst += $tax['sgst'];
                     $total_igst += $tax['igst'];
                  }//end foreach
                
                
                $row++;
               // $this->excel->getActiveSheet()->getStyle("A" . $row . ":ACC" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
            
                
             }
            
             $this->excel->getActiveSheet()->mergeCells('A'.$row.':K'.$row);
             $this->excel->getActiveSheet()->SetCellValue('A' . $row , 'Total');
        
             $this->excel->getActiveSheet()->SetCellValue('K' . $row , $total_cgst);
             $this->excel->getActiveSheet()->SetCellValue('M' . $row , $total_sgst);
             $this->excel->getActiveSheet()->SetCellValue('O' . $row , $total_igst);
                
             
             $this->excel->getActiveSheet()->SetCellValue('P' . $row , $total_tax_amount);
             $this->excel->getActiveSheet()->SetCellValue('Q' . $row , $total_product_tax_amount);
             $this->excel->getActiveSheet()->SetCellValue('R' . $row , $total_invoice_tax_amount);
             $this->excel->getActiveSheet()->SetCellValue('S' . $row , $total_discount);
             $this->excel->getActiveSheet()->SetCellValue('T' . $row , $total_amount);
             
             $this->excel->getActiveSheet()->getStyle("A" . $row . ":AD" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

            
             
            foreach (range('B', 'AD') as $cell_4) {
                $this->excel->getActiveSheet()->getColumnDimension($cell_4)->setWidth(20);
            }//end foreach

            $filename = 'gst_sale_report';
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            //$this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
            ob_clean();
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            ob_clean();
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save('php://output');
            exit();
        }
    }
    
    private function __purchase_gst_report($rowData, $rowFooter) {

        $table = '<table id="SlRData" class="table table-bordered table-hover table-striped table-condensed reports-table">
                        <thead>
                        <tr>
                            <th rowspan="2">Sr. No.</th>
                            <th rowspan="2">' . lang("Bill Number") . '</th>
                            <th rowspan="2">' . lang("date") . '</th>
                            <th rowspan="2">' . lang("supplier") . '</th>
                            <th rowspan="2">' . lang("State Name") . '</th>
                            <th rowspan="2">' . lang("GSTN_Number") . '</th>
                            <th rowspan="2">' . lang("Taxable Amount") . '</th>                            
                            <th colspan="2">' . lang("CGST") . '</th>
                            <th colspan="2">' . lang("SGST") . '</th>
                            <th colspan="2">' . lang("IGST") . '</th>
                            <th rowspan="2">' . lang("Total GST") . '</th>                            
                            <th rowspan="2">' . lang("Total Invoice Amount") . '</th>
                            <th rowspan="2">' . lang("Status") . '</th> 
                        </tr>
                        <tr>                                                      
                            <th>%</th>
                            <th>Amt.</th>
                            <th>%</th>
                            <th>Amt.</th>
                            <th>%</th>
                            <th>Amt.</th>                             
                        </tr>
                        </thead>
                    <tbody>';

        if (!empty($rowData)) {

            $sr = 0;
            foreach ($rowData as $row_id => $row) {
                $sr++;

                $taxRow = count($row['gst']);
                $invoiceRowStart = '<tr data-rowid="' . $row_id . '">
                            <td rowspan="' . $taxRow . '">' . $sr . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['reference_no'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['date'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['supplier'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['state_code'] . '</td>
                            <td rowspan="' . $taxRow . '">' . $row['gstn_no'] . '</td>
                            <td rowspan="' . $taxRow . '" align="right">' . number_format($row['taxable_amount'], 4) . '</td>';

                $tx = 0;
                foreach ($row['gst'] as $tax) {

                    $tx++;
                    $td_taxes = '<td>' . (($tax['cgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00') . '</td>
                                    <td align="right">' . $tax['cgst'] . '</td>
                                    <td>' . (($tax['sgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00') . '</td>
                                    <td align="right">' . $tax['sgst'] . '</td>
                                    <td>' . (($tax['igst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00') . '</td>
                                    <td align="right">' . $tax['igst'] . '</td>';

                    if ($tx > 1) {
                        $nextTaxRow .= '<tr data-rowid="' . $row_id . '">' . $td_taxes . '</tr>';
                    } else {
                        $firstTaxRow = $td_taxes;
                        $nextTaxRow = '';
                    }
                }

                $invoiceRowEnd = '<td rowspan="' . $taxRow . '" align="right">' . number_format($row['total_tax_amount'], 2) . '</td>
                                    <td rowspan="' . $taxRow . '" align="right">' . number_format($row['taxable_amount'] + $row['total_tax_amount'], 2) . '</td>
                                    <td rowspan="' . $taxRow . '" align="right">' . $row['status'] . '</td>
                                </tr>';

                $table .= $invoiceRowStart . $firstTaxRow . $invoiceRowEnd . $nextTaxRow;
            }
        } else {

            $table .= '<tr><td colspan="' . $col . '" class="empty_table_body text-danger">Requested Data Not Available.</td></tr>';
        }

        $table .= ' </tbody>
                        <tfoot class="dtFilter">
                        <tr class="bg-primary">
                            <th>&nbsp;</th>
                            <th></th>
                             <th></th>                         
                            <th></th>
                            <th></th>
                            <th></th>                             
                            <th></th>
                            <th style="color:#ffffff;">CGST</th>
                            <th style="color:#ffffff;" title="Total CGST Amount">' . number_format($rowFooter['cgst'], 2) . '</th>
                            <th style="color:#ffffff;">SGST</th>
                            <th style="color:#ffffff;" title="Total SGST Amount">' . number_format($rowFooter['sgst'], 2) . '</th>
                            <th style="color:#ffffff;">IGST</th>
                            <th style="color:#ffffff;" title="Total IGST Amount">' . number_format($rowFooter['igst'], 2) . '</th>
                            <th style="color:#ffffff;" title="Total Tax Amount">' . number_format($rowFooter['total_tax_amount'], 2) . '</th>
                            <th style="color:#ffffff;" title="Total Invoice Amount">' . number_format($rowFooter['taxable_amount'] + $rowFooter['total_tax_amount'], 2) . '</th>
                            <th></th>                    
                        </tr>
                        </tfoot>
                    </table>';

        echo $table;
    }

    private function __purchase_gst_report_download($rowData, $rowFooter, $report_title) {

        if (!empty($rowData)) {

            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);

            $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

            $this->excel->getActiveSheet()->getStyle("A1:P1")->applyFromArray($style);
            $this->excel->getActiveSheet()->mergeCells('A1:P1', 'GST Purchase Report');

            $this->excel->getActiveSheet()->setTitle(lang('purchase_gst_report'));

            // $this->excel->getActiveSheet()->SetCellValue('A1', $report_title);

            $cellColoumnTitle = ['A' => 'Sr No',
                'B' => 'Bill No',
                'C' => 'Bill Date',
                'D' => 'Supplier Name',
                'E' => 'State Name',
                'F' => 'Supplier GSTN',
                'G' => 'Taxable Amount',
                'H' => 'CGST %',
                'I' => 'CGST Amt',
                'J' => 'SGST %',
                'K' => 'SGST Amt',
                'L' => 'IGST %',
                'M' => 'IGST Amt',
                'N' => 'Total Tax Amount',
                'O' => 'Total Invoice Amount',
                'P' => 'Status',                
                'Q' => 'Warehouse',
            ];

            foreach ($cellColoumnTitle as $cellkey => $cellTitle) {

                $this->excel->getActiveSheet()->SetCellValue($cellkey . '2', $cellTitle);
            }

            $cellData = [
                'A' => '',
                'B' => 'reference_no',
                'C' => 'date',
                'D' => 'supplier',
                'E' => 'state_code',
                'F' => 'gstn_no',
                'G' => 'taxable_amount',
                'H' => 'gst_rate',
                'I' => 'cgst',
                'J' => 'gst_rate',
                'K' => 'sgst',
                'L' => 'gst_rate',
                'M' => 'igst',
                'N' => 'total_tax_amount',
                'O' => 'bill_amount',
                'P' => 'status',
                'Q' => 'warehouse',
            ];

            $row = 3;
            $sr = 0;
            foreach ($rowData as $invoice_no => $inv) {
                $sr++;
                $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sr);

                foreach (range('B', 'G') as $cell_1) {
                    $this->excel->getActiveSheet()->SetCellValue($cell_1 . $row, $inv[$cellData[$cell_1]]);
                }//end foreach

                foreach (range('N', 'Q') as $cell_3) {
                    $this->excel->getActiveSheet()->SetCellValue($cell_3 . $row, $inv[$cellData[$cell_3]]);
                }//end foreach

                $tx = 0;
                $tax_row = '';
                foreach ($inv['gst'] as $tax) {

                    $tx++;
                    $tax_row = ($tx == 1) ? $row : ++$row;

                    $cgst_rate = (($tax['cgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00');
                    $sgst_rate = (($tax['sgst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00');
                    $igst_rate = (($tax['igst'] > 0) ? number_format($tax['gst_rate'], 2) . '%' : '00');

                    $this->excel->getActiveSheet()->SetCellValue('H' . $tax_row, $cgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('I' . $tax_row, $tax['cgst']);
                    $this->excel->getActiveSheet()->SetCellValue('J' . $tax_row, $sgst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('K' . $tax_row, $tax['sgst']);
                    $this->excel->getActiveSheet()->SetCellValue('L' . $tax_row, $igst_rate);
                    $this->excel->getActiveSheet()->SetCellValue('M' . $tax_row, $tax['igst']);
                    //$this->excel->getActiveSheet()->SetCellValue('P' . $tax_row, $tx);  
                }//end foreach
                $row++;
                $this->excel->getActiveSheet()->getStyle("A" . $row . ":P" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
            }

            $this->excel->getActiveSheet()->getStyle("A" . $row++ . ":P" . $row++)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);

            foreach (range('B', 'F') as $cell_4) {
                $this->excel->getActiveSheet()->getColumnDimension($cell_4)->setWidth(20);
            }//end foreach

            $filename = 'purchase_GST_report';
            $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

            //$this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
            ob_clean();
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
            header('Cache-Control: max-age=0');
            ob_clean();
            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save('php://output');
            exit();
        }
    }

    /**  ***************** //New Gst Ajax Reports************************ */
    
    
}
