<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once(APPPATH . "libraries/razorpay/razorpay-php/Razorpay.php");

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class Pos extends MY_Controller {

    public function __construct() {

        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->Customer || $this->Supplier) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->load->helper('genfun_helper');
        $this->load->model('pos_model');

        $this->load->model('sales_model');
        $this->load->helper('text');
        $this->pos_settings = $this->pos_model->getSetting();
        $this->pos_settings->pin_code = $this->pos_settings->pin_code ? md5($this->pos_settings->pin_code) : null;
        $this->data['pos_settings'] = $this->pos_settings;
        $this->data['pos_settings']->pos_theme = json_decode($this->pos_settings->pos_theme);
        $this->session->set_userdata('last_activity', now());
        $this->lang->load('pos', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->sma->setSettings($this->Settings);
    }

    public function sales($warehouse_id = null) {
        $this->sma->checkPermissions('index');
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

        if (isset($this->data['error'])) {
            $error_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $logger = array($this->data['error'], $error_url);
            $this->pos_error_log($logger);
        }
        if ($this->Owner || $this->Admin) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : null;
        } else {
            $user = $this->site->getUser();
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = $warehouse_id == null ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByID($warehouse_id) : null;
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('pos'), 'page' => lang('pos')), array('link' => '#', 'page' => lang('pos_sales')));
        $meta = array('page_title' => lang('pos_sales'), 'bc' => $bc);
        $this->page_construct('pos/sales', $meta, $this->data);
    }

    public function getSales($warehouse_id = null) {

        $this->sma->checkPermissions('index');

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }

        $duplicate_link = anchor('sales/add?sale_id=$1', '<i class="fa fa-plus-circle"></i> ' . lang('duplicate_sale'));
        $detail_link = anchor('pos/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('view_receipt'));
        $detail_link2 = anchor('sales/modal_view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('sale_details_modal'), 'data-toggle="modal" data-target="#myModal"');
        $detail_link3 = anchor('sales/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('sale_details'));
        $payments_link = anchor('sales/payments/$1', '<i class="fa fa-money"></i> ' . lang('view_payments'), 'data-toggle="modal" data-target="#myModal"');
        $add_payment_link = anchor('pos/add_payment/$1', '<i class="fa fa-money"></i> ' . lang('add_payment'), 'data-toggle="modal" data-target="#myModal"');
        $add_delivery_link = anchor('sales/add_delivery/$1', '<i class="fa fa-truck"></i> ' . lang('add_delivery'), 'data-toggle="modal" data-target="#myModal"');
        $email_link = anchor('#', '<i class="fa fa-envelope"></i> ' . lang('email_sale'), 'class="email_receipt" data-id="$1" data-email-address="$2"');
        $edit_link = anchor('sales/edit/$1', '<i class="fa fa-edit"></i> ' . lang('edit_sale'), 'class="sledit"');
        $return_link = anchor('sales/return_sale/$1', '<i class="fa fa-angle-double-left"></i> ' . lang('return_sale'));
        $delete_link = "<a href='#' class='po' title='<b>" . lang("delete_sale") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('sales/delete/$1') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('delete_sale') . "</a>";
        $action = '<div class="text-center"><div class="btn-group text-left">'
                . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">'
                . lang('actions') . ' <span class="caret"></span></button>
    <ul class="dropdown-menu pull-right" role="menu">
        <li class="link_detail_$3">' . $detail_link . '</li>
        <li class="link_detail_$3">' . $detail_link2 . '</li>
        <li class="link_detail_$3">' . $detail_link3 . '</li>
        <li class="link_duplicate_$3">' . $duplicate_link . '</li>
        <li class="link_view_payment_$4">' . $payments_link . '</li>
        <li class="link_add_payment_$4">' . $add_payment_link . '</li>
        <li class="link_delivery_$3">' . $add_delivery_link . '</li>
        <li class="link_edit_$3">' . $edit_link . '</li>
        <li class="link_email_$3">' . $email_link . '</li>
        <li class="link_return_$3">' . $return_link . '</li>
        <li class="link_delete_$3">' . $delete_link . '</li>
    </ul>
</div></div>';
        //$action = '<div class="text-center">' . $detail_link . ' ' . $edit_link . ' ' . $email_link . ' ' . $delete_link . '</div>';

        $this->load->library('datatables');

        if ($warehouse_id) {

            /* $this->datatables
              ->select($this->db->dbprefix('sales') . ".id as id, date, reference_no, biller, customer, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, sale_status, payment_status, delivery_status, companies.email as cemail")
              ->from('sales')
              ->join('companies', 'companies.id=sales.customer_id', 'left')
              ->where('warehouse_id IN ('.$getwarehouse.')')
              ->group_by('sales.id'); */
            $this->datatables
                    ->select($this->db->dbprefix('sales') . ".id as id, date, reference_no," . $this->db->dbprefix('sales') . ".invoice_no as invoice_id, biller, customer, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, sale_status, payment_status, delivery_status, companies.email as cemail")
                    ->from('sales')
                    ->join('companies', 'companies.id=sales.customer_id', 'left')
                    ->where('pos', 1)
                    ->group_by('sales.id');

            $arrWr = explode(',', $warehouse_id);
            $this->datatables->where_in('warehouse_id', $arrWr);
        } else {
            $this->datatables
                    ->select($this->db->dbprefix('sales') . ".id as id, date, reference_no," . $this->db->dbprefix('sales') . ".invoice_no as invoice_id, biller, customer, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, sale_status, payment_status, delivery_status, companies.email as cemail")
                    ->from('sales')
                    ->join('companies', 'companies.id=sales.customer_id', 'left')
                    ->where('pos', 1)
                    ->group_by('sales.id');
        }


        if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        } elseif ($this->Customer) {
            $this->datatables->where('customer_id', $this->session->userdata('user_id'));
        }

        $this->datatables->add_column("Actions", $action, "id, cemail, sale_status, payment_status")->unset_column('cemail');

        echo $this->datatables->generate();
    }

    public function count_alert_products() {//08/08/2019
        $q = $this->db->select('*')
                ->where('quantity <= alert_quantity')
                ->get('sma_products');
        $count = $q->num_rows();
        $prodVariant_Array = [];
        if ($count > 0) {
            $rowArr = $q->result_array();
            $q1 = $this->db->select('name, quantity');
            foreach ($rowArr as $products_arr => $products_arrKey) {
                $product_id = $products_arrKey['id'];
                $q1 = $this->db->get_where('sma_product_variants', array('product_id' => $product_id));
                $result1 = $q1->result_array();
                $prodVariant_Array[$product_id] = $products_arrKey;
                $prodVariant_Array[$product_id]['option'] = $result1;
            }
        }
        return array('count' => $count, 'result' => $prodVariant_Array);
    }
    public function getcustomerid($id) {
      
       if (is_array($id)) {
        $this->db->where_in('id', $id);
        $q = $this->db->get('suspended_bills');
       }else {
        $this->db->where('id', $id);
        $q = $this->db->get('suspended_bills');
       }
      
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row->customer_id;
            }
            return $data;
        }
    }
    public function getsuspendidbycustomer($id) {
        $id =  $this->getcustomerid($id);
        $this->db->where('customer_id', $id);
        $q = $this->db->get('suspended_bills');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row->id;
            }
            return $data;
        }
    }
    public function index($sid = null) {
        $this->pos_model->need_to_delete();
        $this->data['pos_settingss'] = $this->data['pos_settings'];
        $this->sma->checkPermissions();
        $result = implode('/', array_slice($this->uri->segments, -3));
        $suspendId = implode('/', array_slice($this->uri->segments, -1));

        $flag = $this->session->userdata('flag');
        // if ( $flag == 'back_to_pos') {
        //     $sid = $suspendId;
        // }

        if (!$this->pos_settings->default_biller || !$this->pos_settings->default_customer || !$this->pos_settings->default_category) {
            $this->session->set_flashdata('warning', lang('please_update_settings'));
            redirect('pos/settings');
        }
        if ($register = $this->pos_model->registerData($this->session->userdata('user_id'))) {
            $register_data = array('register_id' => $register->id, 'cash_in_hand' => $register->cash_in_hand, 'register_open_time' => $register->date);
            $this->session->set_userdata($register_data);
        } else {
            $this->session->set_flashdata('error', lang('register_not_open'));
            redirect('pos/open_register');
        }
        
        $customerRefNo = $this->input->post('customerRefNo');

        if (($customerRefNo ||$result)) {
            
            if ($customerRefNo) {
                $customerRefNo = explode("/", $customerRefNo)[1];
                $suspended_sales = $this->pos_model->getOpenBillBySuspendID($customerRefNo);
               
                if (!empty($suspended_sales)) {
                    $sid = [];
                    foreach ($suspended_sales as $suspended_sale) {
                        $sid[] = $suspended_sale; 
                    }
                }
            }elseif($result){
                
                $suspended_sales = $this->pos_model->getOpenBillByRefNo($result);
              
                if (!empty($suspended_sales)) {
                    if (is_array($suspended_sales)) {
                     $sid = '';
                        foreach ($suspended_sales as $suspended_sale) {
                            $sid = $suspended_sale->id; 
                        }
                    }else {
                        $sid = $suspended_sales->id; 
                    }
                   
                }
              
            }
        }
      
        if ($sid) {
            $customer_id = $this->getcustomerid($sid);
        }
       
        $suspend = $this->input->post('suspend') ? true : false;
        $this->data['sid'] = $this->input->get('suspend_id') ? $this->input->get('suspend_id') : $sid;
        if (is_array($sid) && !$suspend) {
        // if (is_array($sid)) {
           
            $did = $sid[0] ? $sid[0] : null;
          
        }else {
            $did = $this->input->post('delete_id') ? $this->input->post('delete_id') : null;
          
            // if(is_array($sid)){
            //     $did = $sid[0];
            // }
        }
       
        $this->data['login_controller'] = 'login';
        $this->data['alertProd_Count'] = $this->count_alert_products();
        // $this->data['sid'] = $this->input->get('suspend_id') ? $this->input->get('suspend_id') : $sid;
        // $did = $this->input->post('delete_id') ? $this->input->post('delete_id') : null;
        $suspend = $this->input->post('suspend') ? true : false;
        $count = $this->input->post('count') ? $this->input->post('count') : null;

        //validate form input
        // $this->form_validation->set_rules('customer', $this->lang->line("customer"), 'trim|required');
        $this->form_validation->set_rules('warehouse', $this->lang->line("warehouse"), 'required');
        $this->form_validation->set_rules('biller', $this->lang->line("biller"), 'required');
        $display_seller = $this->pos_settings->display_seller;
        $Settings = $this->Settings; //$this->site->get_setting();

        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->form_validation->set_rules('patient_name', 'Patient Name', 'trim');
            $this->form_validation->set_rules('doctor_name', 'Doctor Name', 'trim');
        }
        $this->data['salesperson_details'] = $this->site->getCompanyDetailsByGroupID(5); // 5 for sales person
        if ($this->form_validation->run() == true) {

            $date = date('Y-m-d H:i:s');
            $warehouse_id = $this->input->post('warehouse');
            $customer_id = ($this->input->post('customer')) ? $this->input->post('customer') : $customer_id;
            $biller_id = $this->input->post('biller');
            $SalesPersonDetails = $this->input->post('pos_sale_person');
            $ExplodeSalesPerson = explode('-', $SalesPersonDetails);
            $SellerId = $ExplodeSalesPerson[0];
            $SellerName = $ExplodeSalesPerson[1];
            $total_items = $this->input->post('total_items');
            $sale_status = 'completed';
            $payment_status = 'due';
            $payment_term = 0;
            $due_date = date('Y-m-d', strtotime('+' . $payment_term . ' days'));
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $customer_details = $this->site->getCompanyByID($customer_id);
            $customer = ($customer_details->company != '' && $customer_details->company != '-') ? $customer_details->company : $customer_details->name;
            $biller_details = $this->site->getCompanyByID($biller_id);
            $biller = ($biller_details->company != '-' && $biller_details->company != '') ? $biller_details->company : $biller_details->name;
            $note = $this->sma->clear_tags($this->input->post('pos_note'));
            $staff_note = $this->sma->clear_tags($this->input->post('staff_note'));
            $reference = $this->site->getReference('pos');

            $offer_category = $this->input->post('offer_category');
            $offer_description = $this->input->post('offer_description');
            if ($customer_id) {
                $Name     =  $this->pos_model->getCustomerNameById($customer_id);
                if ($Name == 'Walk in Customer') {
                  $customer_id =  $this->pos_model->checkCustomerid($Name);
                }
             }
            if ((!empty($customer_details->state_code) && !empty($biller_details->state_code)) && $customer_details->state_code != $biller_details->state_code) {
                $interStateTax = true;
            } else {
                $interStateTax = false;
            }
            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $sale_cgst = $sale_sgst = $sale_igst = 0;
            $saleData = $this->pos_model->getPreviousPosSale();
            $parts = explode("/", $saleData->reference_no);
            $right_section = end($parts);
            $RefNoForExchange = $this->sma->getExchangeSaleReferenceNo($right_section);
            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                $item_id = $_POST['product_id'][$r];
                $hsn_code = $_POST['hsn_code'][$r];
                $hsn_code = ($hsn_code == 'null') ? '' : $hsn_code;
                $item_type = $_POST['product_type'][$r];
                $item_code = $_POST['product_code'][$r];
                $item_article_code = $_POST['article_code'][$r];
                $item_name = $_POST['product_name'][$r];
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' ? $_POST['product_option'][$r] : 0;
                $real_unit_price = $this->sma->formatDecimal($_POST['real_unit_price'][$r], 6);
                $unit_price = $item_unit_price = $this->sma->formatDecimal($_POST['unit_price'][$r], 6);
                $item_serial = isset($_POST['serial'][$r]) ? $_POST['serial'][$r] : '';
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : 0;
                // $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : 0;
                $item_discount = $_POST['product_discount'][$r];
                $item_mrp_discount = $_POST['discount_on_mrp'][$r];
                $item_description = isset($_POST['item_description'][$r]) ? $_POST['item_description'][$r] : null;
                $item_unit = $_POST['product_unit'][$r];
                $item_unit_quantity = $this->sma->formatDecimal($_POST['product_base_quantity'][$r], 4);
                $item_quantity = $this->sma->formatDecimal($_POST['quantity'][$r], 4);
                $item_note = ($_POST['item_description'][$r]) ? $_POST['item_description'][$r] : $_POST['item_note'][$r];
                $manualeditprice = $_POST['manualedit'][$r]; // Manual Price Edit 05-09-19
                $item_weight = $_POST['item_weight'][$r];
                $item_mrp_discount = $_POST['discount_on_mrp'][$r];
                $mrp = $_POST['mrp'][$r]; // product mrp

                $batch_number = (isset($_POST['batch_number'][$r]) && $_POST['batch_number'][$r] != 'undefined' && $_POST['batch_number'][$r] != '') ? $_POST['batch_number'][$r] : NULL;
                if ($item_quantity < 0) {
                    $sale_status = 'exchange';
                    // break; 
                }

                if (isset($item_code) && isset($real_unit_price) && isset($item_unit_price) && isset($item_quantity)) {

                    $product_details = $item_type != 'manual' ? $this->pos_model->getProductByCode($item_code) : null;

                    $pr_discount = $unit_discount = 0;

                    if (isset($item_discount)) {
                        $discount = $item_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            //Note : unitprice is product and variant price. Real unit price is actual product price. if we taken realunitprice then grandtotal and discount calculate wrong becuase real unit price not included variant price. so now taken unit_price.(28-03-2020)
                            //$pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($real_unit_price)) * (Float) ($pds[0])) / 100), 6);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_price)) * (Float) ($pds[0])) / 100), 6);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount, 6);
                        }
                        $unit_discount = $pr_discount;
                    }
                    // discount on mrp
                    if (isset($item_mrp_discount) && $item_mrp_discount !== "0%") {
                        $discount = $item_mrp_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($mrp)) * (Float) ($pds[0])) / 100), 6);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount, 6);
                        }
                        // $unit_price = $this->sma->formatDecimal($unit_price + $pr_discount, 6); 
                        $unit_discount = $pr_discount;
                    }

                    //  $item_unit_price_less_discount = $this->sma->formatDecimal($real_unit_price - $unit_discount,6);

                    $item_unit_price_less_discount = $this->sma->formatDecimal($unit_price - $unit_discount, 6); // 17/05/19

                    $item_net_price = $net_unit_price = $item_unit_price_less_discount;
                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $item_quantity, 6);
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $item_tax = 0;
                    $unit_tax = 0;
                    $tax = "";
                    $tax_method = '';
                    $net_unit_price = $item_unit_price_less_discount;
                    $unit_price = $item_unit_price_less_discount;
                    $invoice_unit_price = $item_unit_price_less_discount;
                    $invoice_net_unit_price = ($item_unit_price_less_discount + $unit_discount);

                    if (isset($item_tax_rate) && (int) $item_tax_rate > 0) {
                        $tax_method = $product_details->tax_method;
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        //Tax Type In Percentage (%)
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $tax_method == 1 && $manualeditprice == '') {
                                //Exclusive Tax Calculations
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / 100, 6);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                //Inclusive Tax Calculations    
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / (100 + $tax_details->rate), 6);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $item_unit_price_less_discount - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $unit_tax = $item_tax;
                        } elseif ($tax_details->type == 2) {
                            //Tax Type is Fixed Amount
                            if ($product_details && $tax_method == 1) {
                                //Exclusive Tax Calculations
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / 100, 6);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                //Inclusive Tax Calculations 
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / (100 + $tax_details->rate), 6);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $item_unit_price_less_discount - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }//end else

                            $item_tax = $this->sma->formatDecimal($tax_details->rate, 6);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal(($item_tax * $item_quantity), 6);
                        $unit_tax = $item_tax;
                    }//end if.

                    $product_tax += $this->sma->formatDecimal(($unit_tax * $item_quantity), 6);

                    $unit = $this->site->getUnitByID($item_unit);
                    // $mrp = isset($product_details->mrp) && !empty($product_details->mrp) ? $product_details->mrp : $item_net_price;

                    $invoice_unit_price = $this->sma->formatDecimal($invoice_unit_price, 4);
                    $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);

                    $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_quantity), 4);
                    $net_unit_price = $this->sma->formatDecimal($net_unit_price, 4);
                    $unit_price = $this->sma->formatDecimal($unit_price, 4);
                    $net_price = $this->sma->formatDecimal(($mrp * $item_quantity), 4);
                    $subtotal = $this->sma->formatDecimal(($unit_price * $item_quantity), 4);

                    if ($interStateTax) {
                        $item_gst = $tax_details->rate;
                        $item_cgst = 0;
                        $item_sgst = 0;
                        $item_igst = $pr_item_tax;
                    } else {
                        $item_gst = $this->sma->formatDecimal($tax_details->rate / 2, 4);
                        $item_cgst = $this->sma->formatDecimal($pr_item_tax / 2, 4);
                        $item_sgst = $this->sma->formatDecimal($pr_item_tax / 2, 4);
                        $item_igst = 0;
                    }
                    if (!empty($item_quantity)) {

                        // Store in negative quantity products array
                        $exchnage_item_list[] = array(
                            'product_id' => $item_id,
                        'product_code' => $item_code,
                        'article_code' => $product_details->article_code,
                        'product_name' => $item_name,
                        'product_type' => $item_type,
                        'option_id' => $item_option,
                        'shade_id' => $product_option_color,
                        'net_unit_price' => $net_unit_price,
                        'unit_price' => $unit_price,
                        'quantity' => ($item_quantity)*(-1),
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit ? $unit->code : null,
                        'unit_quantity' => ($item_unit_quantity)*(-1),
                        'warehouse_id' => $warehouse_id,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'discount' => $item_discount,
                        'item_discount' => $pr_item_discount,
                        'subtotal' => $subtotal,
                        'serial_no' => $item_serial,
                        'real_unit_price' => $real_unit_price,
                        'mrp' => $mrp,
                        'net_price' => $net_price,
                        'hsn_code' => $hsn_code,
                        'note' => $item_note,
                        'delivery_status' => 'pending',
                        'pending_quantity' => $item_quantity,
                        'delivered_quantity' => 0,
                        'tax_method' => $tax_method,
                        'unit_discount' => $unit_discount,
                        'unit_tax' => $unit_tax,
                        'invoice_unit_price' => $invoice_unit_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                        );
                    }
                    $products[] = array(
                        'product_id' => $item_id,
                        'product_code' => $item_code,
                        'article_code' => $product_details->article_code,
                        'product_name' => $item_name,
                        'product_type' => $item_type,
                        'option_id' => $item_option,
                        'net_unit_price' => $net_unit_price,
                        'unit_price' => $unit_price,
                        'quantity' => $item_quantity,
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit ? $unit->code : null,
                        'unit_quantity' => $item_unit_quantity,
                        'warehouse_id' => $warehouse_id,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        // 'discount' => $item_discount,
                        'discount' => isset($item_mrp_discount) && $item_mrp_discount !=  "0%" ? $item_mrp_discount : $item_discount,
                        'item_discount' => $pr_item_discount,
                        'subtotal' => $subtotal,
                        'serial_no' => $item_serial,
                        'real_unit_price' => $real_unit_price,
                        'mrp' => $mrp,
                        'net_price' => $net_price,
                        'hsn_code' => $hsn_code,
                        'note' => $item_note,
                        'delivery_status' => 'pending',
                        'pending_quantity' => $item_quantity,
                        'delivered_quantity' => 0,
                        'tax_method' => $tax_method,
                        'unit_discount' => $unit_discount,
                        'unit_tax' => $unit_tax,
                        'invoice_unit_price' => $invoice_unit_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                        'batch_number' => $batch_number,
                        'gst_rate' => $item_gst,
                        'cgst' => $item_cgst,
                        'sgst' => $item_sgst,
                        'igst' => $item_igst,
                        'item_weight' => $item_weight,
                    );
                 
                    $sale_cgst += $item_cgst;
                    $sale_sgst += $item_sgst;
                    $sale_igst += $item_igst;

                    $total += $this->sma->formatDecimal(($net_unit_price * $item_quantity), 4);
                }
            }


            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } elseif ($this->pos_settings->item_order == 1) {
                // krsort($products);
                $products;
            }

            $order_discount_id = null;
            $order_discount = 0;

            if ($this->input->post('discount')) {
                $order_discount_id = $this->input->post('discount');
                /* $opos = strpos($order_discount_id, $percentage);
                  if ($opos !== false) {
                  $ods = explode("%", $order_discount_id);
                  $order_discount = $this->sma->formatDecimal(((($total + $product_tax) * (Float) ($ods[0])) / 100), 6);
                  } else {
                  $order_discount = $this->sma->formatDecimal($order_discount_id, 6);
                  } */
            }
            $total_discount = $this->sma->formatDecimal($order_discount + $product_discount, 6);


            if ($this->Settings->tax2) {
                $order_tax_id = $this->input->post('order_tax');
                if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                    if ($order_tax_details->type == 2) {
                        $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
                    }
                    if ($order_tax_details->type == 1) {
                        $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
                    }
                }
            } else {
                $order_tax_id = null;
            }

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 6);
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping) - $order_discount), 2);
            $rounding = '';

            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }

            if (!$did) {
                /* $getkot_log = $this->pos_model->getkotlog(array('kot_date' => date('Y-m-d')));
                  if (empty($getkot_log)) {
                  $tokan = '1';
                  $kotlog = array('tokan' => $tokan, 'kot_date' => date('Y-m-d'));
                  $this->pos_model->actionkotlog('Insert', $kotlog, array('id' => $getkot_log->id));
                  } else {
                  $tokan = $getkot_log->tokan + 1;
                  $kotlog = array('tokan' => $tokan);
                  $this->pos_model->actionkotlog('Update', $kotlog, array('id' => $getkot_log->id));
                  } */
            }
            if($sale_status == 'exchange'){
                $reference = $RefNoForExchange;
            }else{
                $reference = $this->site->getReference('pos');
            }
            $data = array('date' => $date,
                'reference_no' => $reference,
                'seller_id' => $SellerId,
                'seller' => $SellerName,
                'customer_id' => $customer_id,
                'customer' => $customer,
                'biller_id' => $biller_id,
                'biller' => $biller,
                'warehouse_id' => $warehouse_id,
                'note' => $note,
                'staff_note' => $staff_note,
                'total' => $total,
                'product_discount' => $product_discount,
                'order_discount_id' => $order_discount_id,
                'order_discount' => $order_discount,
                'total_discount' => $total_discount,
                'product_tax' => $product_tax,
                'order_tax_id' => $order_tax_id,
                'order_tax' => $order_tax,
                'total_tax' => $total_tax,
                'shipping' => $this->sma->formatDecimal($shipping),
                'grand_total' => $grand_total,
                'total_items' => $total_items,
                'sale_status' => $sale_status,
                'payment_status' => $payment_status,
                'payment_term' => $payment_term,
                'rounding' => $rounding,
                'pos' => 1,
                'paid' => $this->input->post('amount-paid') ? $this->input->post('amount-paid') : 0,
                'created_by' => $this->session->userdata('user_id'),
                'offer_category' => $offer_category ? $offer_category : NULL,
                'offer_description' => $offer_description ? $offer_description : NULL,
                //'kot_tokan' => ($did) ? $this->pos_model->getkottokan($did) : $tokan,
                'coupon_code' => $this->input->post('coupon_code'),
                'cgst' => $sale_cgst,
                'sgst' => $sale_sgst,
                'igst' => $sale_igst,
            );


            if (!$suspend) {
                $p = isset($_POST['amount']) ? sizeof($_POST['amount']) : 0;
                $paid = 0;
                $depositLog = null;
                for ($r = 0; $r < $p; $r++) {
                    if (isset($_POST['amount'][$r]) && !empty($_POST['amount'][$r]) && isset($_POST['paid_by'][$r]) && !empty($_POST['paid_by'][$r])) {
                        $amount = $this->sma->formatDecimal($_POST['balance_amount'][$r] > 0 ? $_POST['amount'][$r] - $_POST['balance_amount'][$r] : $_POST['amount'][$r]);
                        if ($_POST['paid_by'][$r] == 'deposit') {
                            if (!$this->site->check_customer_deposit($customer_id, $amount)) {
                                $this->session->set_flashdata('error', lang("amount_greater_than_deposit"));
                                if ($_POST['submit_type'] == 'notprint') {
                                    exit;
                                } else {
                                    redirect($_SERVER["HTTP_REFERER"]);
                                }
                            } else {
                                $deposit = $this->site->customerDepositAmt($customer_id);
                                $deposit_balance = $deposit - $amount;
                                $payment[] = array(
                                    'date' => $date,
                                    // 'reference_no' => $this->site->getReference('pay'),
                                    'amount' => $amount,
                                    'paid_by' => $_POST['paid_by'][$r],
                                    'cheque_no' => $_POST['cheque_no'][$r],
                                    'cc_no' => $_POST['cc_no'][$r],
                                    'cc_holder' => $deposit_balance,
                                    'cc_month' => $_POST['cc_month'][$r],
                                    'cc_year' => $_POST['cc_year'][$r],
                                    'cc_type' => $_POST['cc_type'][$r],
                                    'cc_cvv2' => $_POST['cc_cvv2'][$r],
                                    'created_by' => $this->session->userdata('user_id'),
                                    'type' => 'received',
                                    'note' => $_POST['payment_note'][$r],
                                    'pos_paid' => $_POST['amount'][$r],
                                    'pos_balance' => $_POST['balance_amount'][$r],
                                );

                                $depositLog[] = [
                                    "customer_id" => $customer_id,
                                    "date" => $date,
                                    "descriptions" => "Amount Paid",
                                    "amount" => $amount,
                                    "cr_dr" => 'DR',
                                    "opening_balance" => ((bool) $deposit ? $deposit : 0),
                                    "closing_balance" => $deposit_balance,
                                    "created_by" => $this->session->userdata('user_id'),
                                ];
                            }
                        } else if ($_POST['paid_by'][$r] == 'award_point') {
                            $payment[] = array(
                                'date' => $date,
                                // 'reference_no' => $this->site->getReference('pay'),
                                'amount' => $amount,
                                'paid_by' => $_POST['paid_by'][$r],
                                'ap' => $_POST['ap'][$r],
                                'cheque_no' => $_POST['cheque_no'][$r],
                                'cc_no' => $_POST['cc_no'][$r],
                                'cc_holder' => $_POST['cc_holder'][$r],
                                'cc_month' => $_POST['cc_month'][$r],
                                'cc_year' => $_POST['cc_year'][$r],
                                'cc_type' => $_POST['cc_type'][$r],
                                'cc_cvv2' => $_POST['cc_cvv2'][$r],
                                'created_by' => $this->session->userdata('user_id'),
                                'type' => 'received',
                                'note' => $_POST['payment_note'][$r],
                                'pos_paid' => $_POST['amount'][$r],
                                'pos_balance' => $_POST['balance_amount'][$r],
                                'transaction_id' => ($_POST['cc_transac_no'][$r]) ? $_POST['cc_transac_no'][$r] : $_POST['other_tran'][$r],
                            );
                        } else if ($_POST['paid_by'][$r] == 'gift_card') {
                            $gc = $this->site->getGiftCardByNO($_POST['paying_gift_card_no'][$r]);
                            $amount_paying = $_POST['amount'][$r] >= $gc->balance ? $gc->balance : $_POST['amount'][$r];
                            $gc_balance = $gc->balance - $amount_paying;
                            $payment[] = array(
                                'date' => $date,
                                // 'reference_no' => $this->site->getReference('pay'),
                                'amount' => $amount,
                                'paid_by' => $_POST['paid_by'][$r],
                                'cheque_no' => $_POST['cheque_no'][$r],
                                'cc_no' => $_POST['paying_gift_card_no'][$r],
                                'cc_holder' => $gc_balance,
                                'cc_month' => $_POST['cc_month'][$r],
                                'cc_year' => $_POST['cc_year'][$r],
                                'cc_type' => $_POST['cc_type'][$r],
                                'cc_cvv2' => $_POST['cc_cvv2'][$r],
                                'created_by' => $this->session->userdata('user_id'),
                                'type' => 'received',
                                'note' => $_POST['payment_note'][$r],
                                'pos_paid' => $_POST['amount'][$r],
                                'pos_balance' => $_POST['balance_amount'][$r],
                                'gc_balance' => $gc_balance,
                            );
                        } else {
                            $payment[] = array(
                                'date' => $date,
                                // 'reference_no' => $this->site->getReference('pay'),
                                'amount' => $amount,
                                'paid_by' => $_POST['paid_by'][$r],
                                'cheque_no' => $_POST['cheque_no'][$r],
                                'cc_no' => $_POST['cc_no'][$r],
                                'cc_holder' => $_POST['cc_holder'][$r],
                                'cc_month' => $_POST['cc_month'][$r],
                                'cc_year' => $_POST['cc_year'][$r],
                                'cc_type' => $_POST['cc_type'][$r],
                                'cc_cvv2' => $_POST['cc_cvv2'][$r],
                                'created_by' => $this->session->userdata('user_id'),
                                'type' => 'received',
                                'note' => $_POST['payment_note'][$r],
                                'pos_paid' => $_POST['amount'][$r],
                                'pos_balance' => $_POST['balance_amount'][$r],
                                'transaction_id' => ($_POST['cc_transac_no'][$r]) ? $_POST['cc_transac_no'][$r] : $_POST['other_tran'][$r],
                            );
                        }
                    }
                }
            }
            if (!isset($payment) || empty($payment)) {
                $payment = array();
            }

            //  $this->sma->print_arrays($data, $products, $payment);
        }

        if ($this->form_validation->run() == true && !empty($products) && !empty($data)) {

            if ($suspend) {

                $data['suspend_note'] = $this->input->post('suspend_note');
                $data['table_id'] = $this->input->post('table_id');
                $this->tableBillPrint($this->input->post('table_id'));
                foreach ($products as $key => $arr) {

                    unset($arr['delivered_quantity']);
                    unset($arr['delivery_status']);
                    unset($arr['pending_quantity']);

                    $products[$key] = $arr;
                }

                $suspend_products = $products;
                foreach ($suspend_products as $key => $sus_product) {
                    unset($sus_product['gst_rate'], $sus_product['cgst'], $sus_product['sgst'], $sus_product['igst']);
                    $suspend_products[$key] = $sus_product;
                }
                $suspend_data = $data;
                unset($suspend_data['cgst'], $suspend_data['sgst'], $suspend_data['igst'], $suspend_products['item_weight']);

                if ($this->pos_model->suspendSale($suspend_data, $suspend_products, $did)) {
                    $this->session->set_userdata('remove_posls', 1);
                    if ($this->Settings->theme == "theme_three" || $this->Settings->theme == "theme_four" || $this->Settings->theme == "theme_five") {
                        $this->print_qrcode($customer_id);
                    }else {
                        $this->session->set_flashdata('message', $this->lang->line("sale_suspended"));
                        if ($Settings->pos_type == 'restaurant') {
                            return redirect("pos/kot");
                        } else {
                            return redirect("pos");
                        }
                    }
                    exit;
                }
            } else {

                if ($did) {
                    $getBill = $this->pos_model->getOpenBillByID($did);
                    $data['bill_no'] = $getBill->bill_no;
                }


                if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
                    $patient_name = $this->input->post('patient_name');
                    if ($patient_name):
                        $data['cf1'] = $patient_name;
                    endif;

                    $doctor_name = $this->input->post('doctor_name');
                    if ($doctor_name):
                        $data['cf2'] = $doctor_name;
                    endif;
                }
                //  $this->sma->print_arrays($data, $products, $payment);

                if ($sale = $this->pos_model->addSale($data, $products, $payment, $did)) {

                    if (count($depositLog)) {
                        $this->load->model('companies_model');
                        foreach ($depositLog as $deposit_log) {
                            $deposit_log["transaction_details"] = json_encode(["table_name" => "sma_payments", "where" => ["sale_id" => $sale['sale_id'], "paid_by" => "deposit"]]);

                            $this->companies_model->set_customer_wallet_log($deposit_log);
                        }
                    }

                    if ($this->Settings->send_sales_excel) {
                        $_SESSION['Send_Excel'] = 1;
                        $_SESSION['sale_id'] = $sale['sale_id'];
                    }

                    $this->session->set_userdata('remove_posls', 1);
                    if (isset($sale['redirect_pay_url']) && !empty($sale['redirect_pay_url'])) {
                        header("Location:  " . $sale['redirect_pay_url']);
                        exit;
                    }


                    $msg = $this->lang->line("sale_added");

                    if (!empty($sale['message'])) {
                        foreach ($sale['message'] as $m) {
                            $msg .= '<br>' . $m;
                        }
                    }

                    /* ------ For checking Print/notPrint Button updated by SW 21/01/2017 --------------- */
                    $print = isset($_POST['submit_type']) ? $_POST['submit_type'] : 'print';
                    $_SESSION['print_type'] = $print;
                    /* ------ End For checking Print/notPrint Button updated by SW 21/01/2017 --------------- */

                    $this->session->set_flashdata('message', $msg);


                    //redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale['sale_id']);
                    if ($_POST['submit_type'] == 'notprint') {
                        $response['status'] = 'success';
                        $response['sale'] = $sale;
                        $response['message'] = $msg;

                        if ($this->pos_settings->invoice_auto_sms == '1') {
                            /// SMS Send   
                            if ($customer_id != 1) {
                                if ($this->sma->BalanceSMS()) {
                                    $customer_phone = $this->db->select('phone')->where('id', $customer_id)->get('sma_companies')->row();

                                    $sms_code = md5('Reciept' . $reference . $sale['sale_id']);

                                    //$urlpass = site_url('reciept/send_sms?code=' . $sms_code . '&phone=' . $customer_phone->phone);
                                    //$this->sms_send($urlpass);
                                    $this->send_sms($sms_code, $customer_phone->phone);
                                }
                            }
                            //SMS End 
                        }
                        echo json_encode($response);
                        exit;
                    } else {

                        if ($this->pos_settings->invoice_auto_sms == '1') {
                            // SMS Send  
                            if ($customer_id != 1) {
                                if ($this->sma->BalanceSMS()) {
                                    $customer_phone = $this->db->select('phone')->where('id', $customer_id)->get('sma_companies')->row();

                                    $sms_code = md5('Reciept' . $reference . $sale['sale_id']);

                                    //$urlpass = site_url('reciept/send_sms?code=' . $sms_code . '&phone=' . $customer_phone->phone);
                                    //$this->sms_send($urlpass);

                                    $this->send_sms($sms_code, $customer_phone->phone);
                                }
                            }
                            //SMS End 
                        }

                        return redirect($this->pos_settings->after_sale_page ? "pos/kot" : "pos/view/" . $sale['sale_id']);

                        exit;
                    }
                }
            }
        } else {
            $this->data['suspend_sale'] = null;
            if ($sid) {
                if ($this->Settings->theme == "theme_three" || $this->Settings->theme == "theme_four" || $this->Settings->theme == "theme_five") {
                        $suspended_sale = $this->pos_model->getOpenBillByID($sid);
                }else {
                    $suspended_sale = $this->pos_model->getOpenBillByID($sid);
                }
                if ( $flag == 'back_to_pos') {
                    $sid   = $sid;
                    $suspended_sale = $this->pos_model->getOpenBillByID($sid);
                }
                // $sid = $this->getsuspendidbycustomer($sid);
                if ($suspended_sale) {
                    $inv_items = $this->pos_model->getSuspendedSaleItems($sid);
                
                    krsort($inv_items);
                    $c = rand(100000, 9999999);
                    foreach ($inv_items as $item) {

                        $row = $this->site->getProductByID($item->product_id);

                        if (!$row) {
                            $row = json_decode('{}');
                            $row->tax_method = 0;
                        } else {
                            $category = $this->site->getCategoryByID($row->category_id);
                            $row->category_name = $category->name;
                            unset($row->cost, $row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                        }


                        $item->option_id = $item->option_id ? $item->option_id : 0;
                        $option = $options = $productbatches = false;

                        $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                        $row->quantity = 0;
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $row->quantity += $pi->quantity_balance;
                            }
                        }
                        $row->quantity_total = $this->sma->formatDecimal($row->quantity);

                        $row->id = $item->product_id;
                        $row->code = $item->product_code;
                        $row->name = $item->product_name;
                        $row->type = $item->product_type;
                        $row->option = $item->option_id;
                        $row->discount = $item->discount ? $item->discount : '0';
                        //$row->price = !$item->option_id ? $item->unit_price : $row->price;
                        //$row->unit_price = $item->unit_price;
                        $row->suspended_price = $item->real_unit_price;
                        $row->price = $this->sma->formatDecimal($item->real_unit_price);

                        //$row->price = $this->sma->formatDecimal($item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                        // $row->unit_price = $row->tax_method ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                        $row->unit_price = $item->unit_price;
                        $row->real_unit_price = $item->real_unit_price;
                        $row->base_quantity = $this->sma->formatDecimal($item->quantity);
                        $row->base_unit = isset($row->unit) ? $row->unit : $item->product_unit_id;
                        $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                        $row->net_unit_price = $item->net_unit_price;
                        $row->unit = $item->product_unit_id;
                        $row->qty = $this->sma->formatDecimal($item->unit_quantity);   //$item->quantity ? $item->quantity : 1;
                        $row->unit_quantity = $item->unit_quantity ? $this->sma->formatDecimal($item->unit_quantity) : 1;
                        $row->tax_rate = $item->tax_rate_id;
                        $row->serial = $item->serial_no;
                        $row->note = $item->note;
                        $row->cf1 = $item->cf1;
                        $row->cf2 = $item->cf2;
                        $row->discount = $item->discount ? $item->discount : '0';
                        $row->discount_on_mrp = $item->discount ? $item->discount : '0';
                        $row->pr_var_discount = $item->discount ? $item->discount : '0';

                        $row->batch_number = $item->batch_number != 'false' || !$item->batch_number || $item->batch_number != null ? $item->batch_number : '';
                        $row->unit_weight = $row->weight * $item->unit_quantity;


                        //$options = $this->pos_model->getProductOptions($row->id, $item->warehouse_id);
                        $options = $this->Settings->attributes == 1 ? $this->sales_model->getProductVariants($row->id) : false;

                        if ($options) {
                            foreach ($options as $option) {
                                if ($row->storage_type == "packed" && $row->option) {
                                    $option_quantity = 0;
                                    if ($option->id == $row->option) {
                                        $row->unit_quantity = $option->unit_quantity;
                                        $row->unit_weight = $option->unit_quantity ? $option->unit_quantity : $row->unit_weight;
                                        $option_id = $option->id;
                                        $row->quantity = $option->quantity;
                                        $row->mrp = $option->price;

                                    }

                                    $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $option->id);
                                    if ($pis) {
                                        foreach ($pis as $pi) {
                                            $option_quantity += $pi->quantity_balance;
                                        }
                                    }
                                    $option->quantity = $option_quantity;
                                } else {
                                    //Loose products Variants Quantity Calculate
                                    $option->quantity = number_format($row->quantity_total / $option->unit_quantity, 2);

                                    $option_id = ($row->option != 0 && $row->option == $option->id) ? $row->option : (($row->primary_variant && $option->id == $row->primary_variant) ? $row->primary_variant : false);
                                    if ($option_id && $option_id == $option->id) {
                                        $opt = $options[$option_id];
                                        $row->unit_quantity = $opt->unit_quantity;
                                        $row->unit_weight = $opt->unit_quantity ? $opt->unit_quantity : $row->unit_weight;
                                        $row->quantity = $row->quantity / $opt->unit_quantity;
                                        $row->mrp = $opt->price;

                                    }
                                }

                                $product_options[$option->id] = $option;

                                $option->real_price = $option->price;
                                if ($option->id == $item->option_id) {
                                    $option->price = $row->suspended_price - $row->price;
                                }
                            }
                        } else {
                            $product_options = FALSE;
                            $row->option = 0;
                            $options = FALSE;
                            $row->price = $row->suspended_price;
                        }

                        if (($row->storage_type == 'loose' && !$this->Settings->sale_loose_products_with_variants)) {
                            $options = FALSE;
                            $row->option = 0;
                            $row->price = $row->suspended_price;
                        }

                        $batchoption = '';
                        $productbatches = '';

                        $combo_items = false;
                        if ($row->type == 'combo') {
                            $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                        }
                        if ($row->type == 'Bundle') {
                            $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                        }
                        $units = $this->site->getUnitsByBUID($row->base_unit);
                        $tax_rate = $this->site->getTaxRateByID($row->tax_rate);

                        // category leve tax
                        $itemCategory = ($row->subcategory_id) ? $row->subcategory_id : $row->category_id;
                        $categoryTax = FALSE;
                        if ($itemCategory) {
                            $categoryTax = $this->sales_model->getCategoryTax($itemCategory);
                        }
                        if ($categoryTax) {
                            if (strtolower($categoryTax->fix_tax_rate) == 'fix tax') {
                                $gettax = $this->site->getTaxRateByID($categoryTax->tax_rate);
                                $fixtaxrate = $gettax->id . '~' . $gettax->rate;
                                $varaibletax = False;
                            } else {
                                $varaibletax = $this->sales_model->getVariableTax((($row->subcategory_id) ? $row->subcategory_id : $row->category_id));
                                $fixtaxrate = False;
                            }
                        }

                        $row_id = $row->id . $row->option;
                        $ri = $this->Settings->item_addition ? $row_id : $c;
                        $pr[$ri] = ['id' => $c, 'item_id' => $row_id, 'label' => $row->name . " (" . $row->code . ")", 'divisionid' => $row->divisionid,
                            'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options, 'batchs' => $batchoption, 'product_batches' => $productbatches, 'note' => $row->note, 'categoryTaxData' => $categoryTax, 'category' => $row->category_id, 'sub_category' => $row->subcategory_id,'fixtax' => $fixtaxrate, 'category_tax' => $varaibletax];
                        $c++;
                       
                    }
                   
                    $count = $this->getCustomerSuspendCount($customer_id);
                    $this->data['count'] = ($count->suspended_bill_count) > 0 ? ($count->suspended_bill_count) :  count($suspended_sale);
                    $this->data['phoneNo'] = ($count->phone);
                    $this->data['ScanId'] = $suspended_sale->reference_no;
                    $this->data['items'] = json_encode($pr);
                    $this->data['sid'] = $sid;
                    $this->data['suspend_sale'] = $suspended_sale;
                    $this->data['message'] = lang('suspended_sale_loaded');
                    $this->data['customer'] = $this->pos_model->getCompanyByID($suspended_sale->customer_id);
                    $this->data['reference_note'] = $suspended_sale->suspend_note;
                    $this->data['table_id'] = $suspended_sale->table_id;

                   
                } else {
                    //$this->session->set_flashdata('error', lang("bill_x_found"));
                    redirect("pos");
                }
            } else {
                $this->data['customer'] = $this->pos_model->getCompanyByID($this->pos_settings->default_customer);

                $this->data['reference_note'] = null;
            }

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['message'] = isset($this->data['message']) ? $this->data['message'] : $this->session->flashdata('message');
            $this->data['biller'] = $this->site->getCompanyByID($this->pos_settings->default_biller);
            $this->data['tables'] = $this->site->getAllRestaurantTables();
            $this->data['tabless'] = $this->site->getRestaurantTables();
            $this->data['billers'] = $this->site->getAllCompanies('biller');
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['user'] = $this->site->getUser();
            $this->data["tcp"] = $this->pos_model->products_count($this->pos_settings->default_category);
            $this->data["tables_groups"] = $this->pos_model->getTableGroup($this->data['user']->table_assign);


            $featuerd_products_count = $this->pos_model->featuerd_products_count();
            $seasons_id = $this->input->get('seasonsId');
            $_SESSION["seasonsId"] = $this->input->get('seasonsId');
            if ((int) $featuerd_products_count > 0 && $this->pos_settings->pos_screen_products == 1):
                $this->data['products'] = $this->featuerdProducts();
                $this->data['featuerd_products'] = 1;
                $this->data["tcp"] = $featuerd_products_count;

            else:
                $this->data["tcp"] = $this->pos_model->products_count($this->pos_settings->default_category);
                $this->data['products'] = $this->ajaxproducts($this->pos_settings->default_category, null, $seasons_id);
            endif;
            
            // $this->data['categories'] = $this->site->getProdCategories();
            $this->data['categories'] = $this->site->getProdCategoriesWithDefaultCategory($this->pos_settings->default_category);

            $this->data['brands'] = $this->site->getAllBrands();
            $this->data['subcategories'] = $this->site->getSubCategories($this->pos_settings->default_category);
            $this->data['post_theme'] = $this->site->getpostheme();
            $this->data['sms_limit'] = $this->sma->BalanceSMS();

            if ($this->pos_settings->paynear == 1):
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');
                $paynear_credential = $payment_config['paynear'];
                $this->pos_settings->paynear_app = isset($paynear_credential['PAYNEAR_APP_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_APP_SECRET_KEY']) ? $paynear_credential['PAYNEAR_APP_SECRET_KEY'] : '';
                $this->pos_settings->paynear_web = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
            endif;
            $this->data['pos_settings'] = $this->pos_settings;

            $this->data['report_send'] = $this->sendEmailReport();


            $this->data['opend_bill_count_custom'] = $this->pos_model->bills_count(); //updated by SW 0n25-01-2015
            //Set Active Offers.
            $this->data['active_offers'] = '';
            if ($this->pos_settings->offers_status):
                $this->data['active_offers_category'] = $this->pos_settings->active_offer_category;

                if ($this->pos_settings->active_offer_category):
                    $this->data['active_offers'] = $this->pos_model->getActiveOffers($this->pos_settings->active_offer_category);
                endif;
            endif;

            if ($this->uri->segment(3)) {

                $this->data['kot_tokan'] = '0'; //$this->pos_model->getkottokan($this->uri->segment(3));
            }
            $this->data['kot_token_no'] = $this->getKotToken();
            $this->data['order_tokan'] = 0; //$this->pos_model->getkotlog(array('kot_date' => date('Y-m-d')));
            $this->data['seasons'] = $this->site->get_all_season();
            //$this->checkOPCLTrigger(); // Opening And Closing Balance Deposit Manage     
            
            
            if ($this->Settings->theme == "theme_three" || $this->Settings->theme == "theme_four" || $this->Settings->theme == 'theme_five') {
                $this->load->view($this->theme . 'pos/add_ep', $this->data);
            }else {
                $this->load->view($this->theme . 'pos/add', $this->data);
            }
            
        }
        unset($_SESSION["quick_customerid"]);
        unset($_SESSION["quick_customername"]);
        unset($_SESSION["quick_customerphone"]);
    }

    public function view_bill() {
        $this->sma->checkPermissions('index');
        $this->data['tax_rates'] = $this->site->getAllTaxRates();
        $this->load->view($this->theme . 'pos/view_bill', $this->data);
    }

    public function stripe_balance() {
        if (!$this->Owner) {
            return false;
        }
        $this->load->model('stripe_payments');

        return $this->stripe_payments->get_balance();
    }

    public function paypal_balance() {
        if (!$this->Owner) {
            return false;
        }
        $this->load->model('paypal_payments');

        return $this->paypal_payments->get_balance();
    }

    public function registers() {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['registers'] = $this->pos_model->getOpenRegisters();
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('pos'), 'page' => lang('pos')), array('link' => '#', 'page' => lang('open_registers')));
        $meta = array('page_title' => lang('open_registers'), 'bc' => $bc);
        $this->page_construct('pos/registers', $meta, $this->data);
    }

    public function open_register() {
        $this->sma->checkPermissions('index');
        $this->form_validation->set_rules('cash_in_hand', lang("cash_in_hand"), 'trim|required|numeric');

        if ($this->form_validation->run() == true) {
            $data = array(
                'date' => date('Y-m-d H:i:s'),
                'cash_in_hand' => $this->input->post('cash_in_hand'),
                'user_id' => $this->session->userdata('user_id'),
                'status' => 'open',
            );
        }
        if ($this->form_validation->run() == true && $this->pos_model->openRegister($data)) {
            $this->session->set_flashdata('message', lang("welcome_to_pos"));
            redirect("pos");
        } else {
            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('open_register')));
            $meta = array('page_title' => lang('open_register'), 'bc' => $bc);
            $this->page_construct('pos/open_register', $meta, $this->data);
        }
    }

    public function close_register($user_id = null) {
        $this->sma->checkPermissions('index');
        if (!$this->Owner && !$this->Admin) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->form_validation->set_rules('total_cash', lang("total_cash"), 'trim|required|numeric');
        $this->form_validation->set_rules('total_cheques', lang("total_cheques"), 'trim|required|numeric');
        $this->form_validation->set_rules('total_cc_slips', lang("total_cc_slips"), 'trim|required|numeric');

        if ($this->form_validation->run() == true) {
            if ($this->Owner || $this->Admin) {
                $user_register = $user_id ? $this->pos_model->registerData($user_id) : null;
                $rid = $user_register ? $user_register->id : $this->session->userdata('register_id');
                $user_id = $user_register ? $user_register->user_id : $this->session->userdata('user_id');
            } else {
                $rid = $this->session->userdata('register_id');
                $user_id = $this->session->userdata('user_id');
            }
            $data = array(
                'closed_at' => date('Y-m-d H:i:s'),
                'total_cash' => $this->input->post('total_cash'),
                'total_cheques' => $this->input->post('total_cheques'),
                'total_cc_slips' => $this->input->post('total_cc_slips'),
                'total_cash_submitted' => $this->input->post('total_cash_submitted'),
                'total_cheques_submitted' => $this->input->post('total_cheques_submitted'),
                'total_cc_slips_submitted' => $this->input->post('total_cc_slips_submitted'),
                'note' => $this->input->post('note'),
                'status' => 'close',
                'transfer_opened_bills' => $this->input->post('transfer_opened_bills'),
                'closed_by' => $this->session->userdata('user_id'),
            );
        } elseif ($this->input->post('close_register')) {
            $this->session->set_flashdata('error', (validation_errors() ? validation_errors() : $this->session->flashdata('error')));
            //redirect("pos");
            redirect("pos/registers");
        }

        if ($this->form_validation->run() == true && $this->pos_model->closeRegister($rid, $user_id, $data)) {
            $this->session->set_flashdata('message', lang("register_closed"));
            redirect('pos/SendAutoEmail/close_register/');
            if ($this->uri->segment(3) == 1)
                redirect("welcome");
        } else {
            if ($this->Owner || $this->Admin) {
                $user_register = $user_id ? $this->pos_model->registerData($user_id) : null;
                $register_open_time = $user_register ? $user_register->date : null;
                $this->data['cash_in_hand'] = $user_register ? $user_register->cash_in_hand : null;
                $this->data['register_open_time'] = $user_register ? $register_open_time : null;
            } else {
                $register_open_time = $this->session->userdata('register_open_time');
                $this->data['cash_in_hand'] = null;
                $this->data['register_open_time'] = null;
            }
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['ccsales'] = $this->pos_model->getRegisterCCSales($register_open_time, $user_id);
            $this->data['cashsales'] = $this->pos_model->getRegisterCashSales($register_open_time, $user_id);
            $this->data['chsales'] = $this->pos_model->getRegisterChSales($register_open_time, $user_id);
            $this->data['gcsales'] = $this->pos_model->getRegisterGCSales($register_open_time);
            $this->data['pppsales'] = $this->pos_model->getRegisterPPPSales($register_open_time, $user_id);
            /* --- 13-03-19 --- */
            $this->data['othersales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'other');
            $this->data['dcsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'DC');
            $this->data['neftsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'NEFT');
            $this->data['paytmsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'PAYTM');
            $this->data['googlepaysales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'Googlepay');
            $this->data['swiggysales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'swiggy');
            $this->data['zomatosales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'zomato');
            $this->data['ubereatssales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'ubereats');
            $this->data['complimentrysales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'complimentry');
            $this->data['upiqrcode'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'UPI_QRCODE');

            /* --- end 13-03-19  -- */
            $this->data['stripesales'] = $this->pos_model->getRegisterStripeSales($register_open_time, $user_id);
            $this->data['authorizesales'] = $this->pos_model->getRegisterAuthorizeSales($register_open_time, $user_id);
            $this->data['totalsales'] = $this->pos_model->getRegisterSales($register_open_time, $user_id);
            $this->data['duesales'] = $this->pos_model->getdueAmt($register_open_time, $user_id); //18-03-19
            $this->data['duepartial'] = $this->pos_model->getpartialAmt($register_open_time); //20-03-19
            $this->data['refunds'] = $this->pos_model->getRegisterRefunds($register_open_time, $user_id);
            $this->data['cashrefunds'] = $this->pos_model->getRegisterCashRefunds($register_open_time, $user_id);
            $this->data['expenses'] = $this->pos_model->getRegisterExpenses($register_open_time, $user_id);
            $this->data['users'] = $this->pos_model->getUsers($user_id);
            $this->data['suspended_bills'] = $this->pos_model->getSuspendedsales($user_id);
            $this->data['user_id'] = $user_id;
            $this->data['modal_js'] = $this->site->modal_js();

            $this->data['deposit_received'] = $this->pos_model->getRegisterDeposit($register_open_time, $user_id);
            $this->data['depositsales'] = $this->pos_model->getRegisterdepSales($register_open_time);

            $this->load->view($this->theme . 'pos/close_register', $this->data);
        }
    }

    public function getProductDataByCode($code = null, $warehouse_id = null) {
        $this->sma->checkPermissions('index');
        if ($this->input->get('code')) {
            $code = $this->input->get('code', true);
        }
        if ($this->input->get('warehouse_id')) {
            $warehouse_id = $this->input->get('warehouse_id', true);
        }
        if ($this->input->get('customer_id')) {
            $customer_id = $this->input->get('customer_id', true);
        }

        if ($this->Settings->pos_type == 'restaurant') {
            if ($this->input->get('table_id')) {
                $table_id = $this->input->get('table_id', true);
            }
        }

        if (!$code) {
            echo null;
            die();
        }
       
        $saleData = $this->pos_model->getPreviousPosSale();
        $parts = explode("/", $saleData->reference_no);
        $right_section = end($parts);
        $RefNo = $this->sma->getReturnSaleReferenceNo($right_section);
        $warehouse = $this->site->getWarehousesID($warehouse_id);
        $customer = $this->site->getCompanyByID($customer_id);
        $customer_group = $this->site->getCustomerGroupByID($customer->customer_group_id);
        // $row = $this->pos_model->getWHProduct($code, $warehouse_id);
        $row = $this->site->getProductByCode($code);

        if (isset($table_id)) {
            $table_details = $this->sales_model->getTableDetails($table_id);
        }

        $option_id = false;

        if ($row) {
            if (($this->pos_settings->active_repeat_customer_discount) && ($this->pos_settings->auto_apply_repeat_customer_discount) && ($customer_id != 1)) {
                $getDiscount = $this->sales_model->getRepeatSalesCheck($customer_id, $row->code, $row->repeat_sale_validity);
                if ($getDiscount) {
                    $discountP = $getDiscount['discountP'];
                    $discountAmt = $getDiscount['discountAmt'];
                }
            }
            unset($row->cost, $row->details, $row->product_details, $row->barcode_symbology, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
            unset($row->alert_quantity, $row->article_code, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->file, $row->food_type_id, $row->in_eshop, $row->is_featured, $row->purchase_unit, $row->ratings_avarage, $row->ratings_count, $row->supplier3price, $row->track_quantity, $row->updated_at, $row->comments_count);

            /** Changes according to pos setting Use Product Price Field* */
            if ($this->pos_settings->use_product_price == 'mrp') {
                $row->price = $row->mrp ? $row->mrp : $row->price;
            }

            if (isset($table_details)) {
                if ($pr_group_price = $this->site->getProductGroupPrice($row->id, $table_details->price_group_id)) {
                    $row->price = $pr_group_price->price;
                }
            }

            $unitData = $this->sales_model->getUnitById($row->unit);
            $row->unit_lable = $unitData->name;
            $row->quantity_total = $row->quantity;
            $row->item_tax_method = $row->tax_method;
            $row->base_quantity = 1;
            $row->qty = 1;
            $row->unit_quantity = 1;
            if (isset($discountP)) {
                $row->discount = (($discountP) ? $discountP . '%' : (($customer_group->apply_as_discount) ? $customer_group->percent . '%' : '0'));
            } else {
                $row->discount = ($customer_group->apply_as_discount) ? $customer_group->percent . '%' : '0';
            }
            $row->discount_on_mrp =  ($row->discount_on_mrp == 'Null' || $row->discount_on_mrp == '') ? "0%" : $row->discount_on_mrp ;
            $row->return_ref_no = $RefNo;
            $row->warehouse = $warehouse_id;
            $row->unit_price = $row->price;
            $row->unit_weight = $row->weight;
            $row->base_unit_price = $row->price;
            $row->option = 0;

            $pis = $this->site->getPurchasedItems($row->id, $warehouse_id);
            if ($pis) {
                $pw_quantity = 0;
                foreach ($pis as $pi) {
                    $pw_quantity += $pi->quantity_balance;
                }
                $row->quantity = $pw_quantity;
            }

            $options = $this->Settings->attributes == 1 ? $this->site->getProductVariants($row->id, true) : false;
            $opt = false;

            if ($options !== false) {
                // $opt = ($row->primary_variant && !empty($options[$row->primary_variant])) ? $options[$row->primary_variant] : current($options);
                $opt = current($options);
                $option_id = $opt->id;
                // $row->mrp = $opt->price;
                $row->mrp = $opt->mrp;

            }else{
                $row->mrp = $row->mrp;
            }

            if ($opt !== false) {
                $row->unit_price = $row->price + $opt->price;
                $row->base_unit_price = $row->unit_price;
                $row->unit_quantity = $opt->unit_quantity ? $opt->unit_quantity : 1;
                $row->unit_weight = $opt->unit_weight;
                $row->option = $option_id;
            }//end if

            $product_options = false;

            if ($options) {
                foreach ($options as $option) {
                    if ($row->storage_type == "loose") {
                        $pis = $this->site->getPurchasedItems($row->id, $warehouse_id);
                    } else {
                        $pis = $this->site->getPurchasedItems($row->id, $warehouse_id, $option->id);
                    }
                    $option_quantity = 0;
                    if ($pis) {
                        foreach ($pis as $pi) {
                            $option_quantity += $pi->quantity_balance;
                        }
                    }
                    if ($row->storage_type == "loose") {
                        //Loose products Variants Quantity Calculate
                        $option->quantity = number_format($option_quantity / $option->unit_quantity, 2);
                    } else {
                        $option->quantity = $option_quantity;
                    }
                    if ((!$this->Settings->overselling && $option->quantity > 0) || $this->Settings->overselling) {
                        $product_options[$option->id] = $option;
                    }
                }
                unset($options);
                $row->quantity = $product_options[$row->option]->quantity;
                $options = $product_options;
            }//end if

            if ($row->type == 'standard' && (!$this->Settings->overselling && $row->quantity < 1)) {
                echo null;
                die();
            }

            $row->mrp = $row->mrp ? $row->mrp : $row->unit_price;

            if ($row->storage_type == 'loose' && $this->Settings->sale_loose_products_with_variants != 1) {
                $options = false;
                $option_quantity = 0;
                $row->option = 0;
            } //end if  
            $row->org_price = $row->price;
     
            if ($row->promotion) {
                 // $today = strtotime(date('Y-m-d'));
                    // $row->unit_price = (strtotime($row->start_date) <= $today && strtotime($row->end_date) >$today ) ? $row->unit_price : $row->promo_price;
                    $today = date('Y-m-d');
                    $start_date = date('Y-m-d', strtotime($row->start_date)); // Format start date
                    $end_date = date('Y-m-d', strtotime($row->end_date)); // Format end date
                    $row->unit_price = ($start_date <= $today && $end_date >= $today) ? $row->promo_price : $row->unit_price;
            } elseif ($customer->price_group_id) {
                if ($pr_group_price = $this->site->getProductGroupPrice($row->id, $customer->price_group_id)) {
                    $row->unit_price = $pr_group_price->price;
                }
            } 
            if ($warehouse->price_group_id) {
                if ($pr_group_price = $this->site->getProductGroupPrice($row->id, $warehouse->price_group_id)) {
                    $row->unit_price = $pr_group_price->price;
                }
            }
    

            if ($row->price == 0) {
                $row->price = $row->org_price;
            } else {
                if ($customer_group->apply_as_discount) {
                    $row->price = $row->price;
                } else {
                    $row->price = $row->price - (($row->price * $customer_group->percent) / 100);
                }
            }

            if ($customer_group->apply_as_discount) {
                $row->unit_price = $row->unit_price;
            } else {
                if (isset($discountP) || isset($discountAmt)) {
                    if ($discountP) {
                        $row->unit_price = $row->unit_price - (($row->unit_price * $discountP) / 100);
                    }

                    if ($discountAmt) {
                        $row->unit_price = $row->unit_price - $discountAmt;
                    }
                } else {
                    $row->unit_price = $row->unit_price - (($row->unit_price * $customer_group->percent) / 100);
                }
            }

            $row->real_unit_price = $row->price ? $row->price : $row->unit_price;
            $row->base_quantity = $row->unit_quantity ? ($row->unit_quantity * $row->qty) : $row->qty;
            $row->unit_quantity = $row->unit_quantity ? $row->unit_quantity : 1;
            $row->base_unit = $row->unit;
            $row->unit = $row->sale_unit ? $row->sale_unit : $row->unit;
            $row->warehouse_price_group_id         = $warehouse->price_group_id;

            $combo_items = false;
            if ($row->type == 'combo') {
                $combo_items = $this->pos_model->getProductComboItems($row->id, $warehouse_id);
            }
            if ($row->type == 'Bundle') {
                $combo_items = $this->pos_model->getProductComboItems($row->id, $warehouse_id);
            }
            // category leve tax
            $itemCategory = ($row->subcategory_id) ? $row->subcategory_id : $row->category_id;
            $categoryTax = FALSE;
            if ($itemCategory) {
                $categoryTax = $this->sales_model->getCategoryTax($itemCategory);
            }
            if ($categoryTax) {
                if (strtolower($categoryTax->fix_tax_rate) == 'fix tax') {
                    $gettax = $this->site->getTaxRateByID($categoryTax->tax_rate);
                    $fixtaxrate = $gettax->id . '~' . $gettax->rate;
                    $varaibletax = False;
                } else {
                    $varaibletax = $this->sales_model->getVariableTax((($row->subcategory_id) ? $row->subcategory_id : $row->category_id));
                    $fixtaxrate = False;
                }
            }
            $units = $this->site->getUnitsByBUID($row->base_unit);
            $tax_rate = $this->site->getTaxRateByID($row->tax_rate);

            $c = str_replace(".", "", microtime(true));
            $row_id = $row->id . $row->option;
         
            $pr = array('id' => $c, 'item_id' => $row_id, 'label' => $row->name . " (" . $row->code . ")", 'category' => $row->category_id, 'sub_category' => $row->subcategory_id, 'divisionid' => $row->divisionid, 'brand' => $row->brand, 'fixtax' => $fixtaxrate, 'category_tax' => $varaibletax, 'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
            $this->sma->send_json($pr);
        } else {
            echo null;
        }
    }

    public function getProductDataById($code = null, $warehouse_id = null) {
        $this->sma->checkPermissions('index');
        if ($this->input->get('code')) {
            $code = $this->input->get('code', true);
        }
        if ($this->input->get('warehouse_id')) {
            $warehouse_id = $this->input->get('warehouse_id', true);
        }
        if ($this->input->get('customer_id')) {
            $customer_id = $this->input->get('customer_id', true);
        }
        if (!$code) {
            echo null;
            die();
        }
        $warehouse = $this->site->getWarehouseByID($warehouse_id);
        $customer = $this->site->getCompanyByID($customer_id);
        $customer_group = $this->site->getCustomerGroupByID($customer->customer_group_id);
        $row = $this->pos_model->getWHProductById($code);
        $option = false;
        if ($row) {
            unset($row->cost, $row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
            $row->item_tax_method = $row->tax_method;
            $row->qty = 1;
            $row->discount = '0';
            $row->serial = '';
            $options = $this->pos_model->getProductOptions($row->id, $warehouse_id);
            if ($options) {
                $opt = current($options);
                if (!$option) {
                    $option = $opt->id;
                }
            } else {
                $opt = json_decode('{}');
                $opt->price = 0;
            }
            if ($this->Settings->attributes == 1)
                $row->option = $option;
            $row->quantity = 0;
            $pis = $this->site->getPurchasedItems($row->id, $warehouse_id, $row->option);
            if ($pis) {
                foreach ($pis as $pi) {
                    $row->quantity += $pi->quantity_balance;
                }
            }
            if ($row->type == 'standard' && (!$this->Settings->overselling && $row->quantity < 1)) {
                echo null;
                die();
            }
            if ($options) {
                $option_quantity = 0;
                foreach ($options as $option) {
                    $pis = $this->site->getPurchasedItems($row->id, $warehouse_id, $row->option);
                    if ($pis) {
                        foreach ($pis as $pi) {
                            $option_quantity += $pi->quantity_balance;
                        }
                    }
                    if ($option->quantity > $option_quantity) {
                        $option->quantity = $option_quantity;
                    }
                }
            }
            $row->org_price = $row->price;
            if ($row->promotion) {
                $row->price = $row->promo_price;
            } elseif ($customer->price_group_id) {
                if ($pr_group_price = $this->site->getProductGroupPrice($row->id, $customer->price_group_id)) {
                    $row->price = $pr_group_price->price;
                }
            } elseif ($warehouse->price_group_id) {
                if ($pr_group_price = $this->site->getProductGroupPrice($row->id, $warehouse->price_group_id)) {
                    $row->price = $pr_group_price->price;
                }
            }
            if ($row->price == 0)
                $row->price = $row->org_price;
            $row->price = $row->price - (($row->price * $customer_group->percent) / 100);
            $row->real_unit_price = $row->price;
            $row->base_quantity = 1;
            $row->base_unit = $row->unit;
            $row->base_unit_price = $row->price;
            $row->unit = $row->sale_unit ? $row->sale_unit : $row->unit;
            $combo_items = false;
            if ($row->type == 'combo') {
                $combo_items = $this->pos_model->getProductComboItems($row->id, $warehouse_id);
            }
            if ($row->type == 'Bundle') {
                $combo_items = $this->pos_model->getProductComboItems($row->id, $warehouse_id);
            }
            $units = $this->site->getUnitsByBUID($row->base_unit);
            $tax_rate = $this->site->getTaxRateByID($row->tax_rate);

            $pr = array('id' => str_replace(".", "", microtime(true)), 'item_id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'category' => $row->category_id, 'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);

            $this->sma->send_json($pr);
        } else {
            echo null;
        }
    }

    public function ajaxproducts($category_id = null, $brand_id = null,$seasons_id) {
        $this->sma->checkPermissions('index');
        $Settings = $this->Settings;
        $pos_screen_products = $this->pos_settings->pos_screen_products;

        $warehouse_id = null;
        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            //$user = $this->site->getUser();
            //$warehouse_id = $user->warehouse_id;
        }

        if ($this->input->get('brand_id')) {
            $brand_id = $this->input->get('brand_id');
        }
        if ($this->input->get('category_id')) {
            $category_id = $this->input->get('category_id');
        } else {
            $category_id = $this->pos_settings->default_category;
        }
        if ($this->input->get('subcategory_id')) {
            $subcategory_id = $this->input->get('subcategory_id');
        } else {
            $subcategory_id = null;
        }
        if ($this->input->get('per_page') == 'n') {
            $page = 0;
        } else {
            $page = $this->input->get('per_page');
        }

        if ($this->input->get('seasonId')) {
            $seasons_id = $this->input->get('seasonId');
        }

        $this->load->library("pagination");

        $config = array();
        $config["base_url"] = base_url() . "pos/ajaxproducts";
        $config["total_rows"] = $this->pos_model->products_count($category_id, $subcategory_id, $brand_id, $warehouse_id);
        $config["per_page"] = $this->pos_settings->pro_limit;
        $config['prev_link'] = false;
        $config['next_link'] = false;
        $config['display_pages'] = false;
        $config['first_link'] = false;
        $config['last_link'] = false;

        $this->pagination->initialize($config);

        $products = $this->pos_model->fetch_products($category_id, $config["per_page"], $page, $subcategory_id, $brand_id, $warehouse_id,$seasons_id, $pos_screen_products);
        
        $pro = 1;
        $prcount = $config["total_rows"];
        $i = 1;
        $prods = '<div>';

        if (!empty($products)) {
            $i = 0; // Initialize index for alternating background color
            foreach ($products as $product) {
        
                // Set product count and category id formatting
                $count = $product->id;
                if ($count < 10) {
                    $count = "0" . ($count / 100) * 100;
                }
                if ($category_id < 10) {
                    $category_id = "0" . ($category_id / 100) * 100;
                }
                if (file_exists('assets/mdata/'.$this->Customer_assets.'/uploads/' . $product->image)) { //thumbs/
                    if ($product->image != "") {
                        $imgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/' . $product->image; //thumbs/
                    } else {
                        $imgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/no_image.png';
                    }
                } else {
                    $imgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/no_image.png';
                }
        
                // Define four light background colors and matching text colors
                $colors = [
                    "background-color: #ADD8E6; color: #000000;", // Light Blue with Black text
                    "background-color: #90EE90; color: #000000;", // Light Green with Black text
                    "background-color: #FFB6C1; color: #000000;", // Light Pink with Black text
                    "background-color: #FFFFE0; color: #000000;"  // Light Yellow with Black text
                ];
        
                // Alternate between the four background and text color combinations
                $background_and_text_color = $colors[$i % 4];
        
                // Generate product button with alternating background and span text color
                if ($Settings->theme != 'default' && $Settings->theme != 'theme_three') {
                    $prods .= "<button id=\"product-" . $category_id . $count . "\" type=\"button\" value='" . $product->code . "' title=\"" . $product->name . "\" class=\"btn-prni btn-" . $this->pos_settings->product_button_color . " product pos-tip\" data-container=\"body\" style='" . $background_and_text_color . "'>
                <img src=\"" . $imgsrc . "\" alt=\"" . $product->name . "\" class='img-rounded " . $image_class . "' style='width:" . $this->Settings->twidth . "px;height:" . $this->Settings->theight . "px;' />
                <br><span class='bgspan' style='" . $background_and_text_color . "'>" . character_limiter($product->name, 15) . "</span></button>";
                } elseif($Settings->theme = 'theme_three') {
                    $prods .= "<button id=\"product-" . $category_id . $count . "\" type=\"button\" value='" . $product->code . "' title=\"" . $product->name . "\" class=\"btn-prni btn-" . $this->pos_settings->product_button_color . " product pos-tip\" data-container=\"body\" style='" . $background_and_text_color . "'>
                                <img src=\"" . $imgsrc . "\" alt=\"" . $product->name . "\" style='width:" . $this->Settings->twidth . "px;height:" . $this->Settings->theight . "px;' class='img-rounded hidden-img' />
                                <span class='' style='" . $background_and_text_color . "'>" . character_limiter($product->name, 20) . "</span></button>";
                }else{
                    $prods .= "<button id=\"product-" . $category_id . $count . "\" type=\"button\" value='" . $product->code . "' title=\"" . $product->name . "\" class=\"btn-prni btn-" . $this->pos_settings->product_button_color . " product pos-tip\" data-container=\"body\" style='" . $background_and_text_color . "'>
                    <img src=\"" . $imgsrc . "\" alt=\"" . $product->name . "\" style='width:" . $this->Settings->twidth . "px;height:" . $this->Settings->theight . "px;' class='img-rounded' />
                    <span class='' style='" . $background_and_text_color . "'>" . character_limiter($product->name, 20) . "</span></button>";
                }
                $pro++;

                //if (++$i == 21) {28/06/2019
                if (++$i == $config["per_page"]) {
                    break;
                }
                //$i==21;
                //break 21;
            }
        }
        $prods .= "</div>";

        if ($this->input->get('per_page')) {

            $tcp = $this->pos_model->products_count($category_id, $subcategory_id, $brand_id, $warehouse_id);
            $this->sma->send_json(array('products' => $prods, 'tcp' => $tcp));

            //echo $prods;
        } else {
            return $prods;
        }
    }

    public function is_url_exist($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code == 200) {
            $status = true;
        } else {
            $status = false;
        }
        curl_close($ch);
        return $status;
    }

    public function ajaxcategorydata($category_id = null) {
        $this->sma->checkPermissions('index');
        $Settings = $this->Settings;
        if ($this->input->get('category_id')) {
            $category_id = $this->input->get('category_id');
        } else {
            $category_id = $this->pos_settings->default_category;
        }

        $subcategories = $this->site->getSubCategories($category_id);
        $scats = '';
        $sub_cat = '';
        if ($subcategories) {
            if ($Settings->theme != 'default') {
                foreach ($subcategories as $category) {
                    $scats .= "<button id=\"subcategory-" . $category->id . "\" type=\"button\" value='" . $category->id . "' class=\"btn-prni1 subcategory purple-background\">" . $category->name . "</button>";
                    $sub_cat .= "<div class='inline-item active'><button id='subcategory-" . $category->id . "' type='button' value='" . $category->id . "' class='btn-prni1 subcategory purple-background'>" . $this->char_limit($category->name, 15) . "</button></div>";
                    }
            }
            
            
             else {

                foreach ($subcategories as $category) {
                    if (file_exists('assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/' . $category->image)) {
                        if ($category->image != "") {
                            $subimgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/' . $category->image;
                        } else {
                            $subimgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/no_image.png';
                        }
                    } else {
                        $subimgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/no_image.png';
                    }
                    $scats .= "<button id=\"subcategory-" . $category->id . "\" type=\"button\" value='" . $category->id . "' class=\"btn-prni subcategory\" >/*--<img src=\"" . $subimgsrc . "\" style='width:" . $this->Settings->twidth . "px;height:" . $this->Settings->theight . "px;' class='img-rounded img-thumbnail' /><span>" . $category->name . "</span></button>";
                    $sub_cat .= "<div class='' style='width: 78.8px; margin-left:1rem; margin-right: 3rem;'><div class='item'><button id='subcategory-" . $category->id . "' type='button' value='" . $category->id . "' class='btn-prni subcategory  purple-background custom-sub-category' ><img src=\"" . $subimgsrc . "\" style='width:" . $this->Settings->twidth . "px;height:" . $this->Settings->theight . "px;' class='img-rounded img-thumbnail' /><span class='text-white'>" . $this->char_limit($category->name, 20) . "</span></button></button></div></div>";
                }

                $sub_cat .= "<div class='owl-item active' ></div>";
            }
        }

        $products = $this->ajaxproducts($category_id);

        if (!($tcp = $this->pos_model->products_count($category_id))) {
            $tcp = 0;
        }

        $this->sma->send_json(array('products' => $products, 'subcategories' => $scats, 'subcategories2' => $sub_cat, 'tcp' => $tcp));
    }

    public function ajaxbranddata($brand_id = null) {
        $this->sma->checkPermissions('index');
        if ($this->input->get('brand_id')) {
            $brand_id = $this->input->get('brand_id');
        }

        $products = $this->ajaxproducts(false, $brand_id);

        if (!($tcp = $this->pos_model->products_count(false, false, $brand_id))) {
            $tcp = 0;
        }

        $this->sma->send_json(array('products' => $products, 'tcp' => $tcp));
    }

    /* ------------------------------------------------------------------------------------ */

    public function view_up($sale_id = null, $modal = null) {
        $this->data['pos_settingss'] = $this->data['pos_settings'];
        $this->sma->checkPermissions('index');
        if ($this->input->get('id')) {
            $sale_id = $this->input->get('id');
        }
        $_PID = $this->Settings->default_printer;


        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        $this->load->helper('text');
        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['message'] = $this->session->flashdata('message');
        $inv = $this->pos_model->getInvoiceByID($sale_id);

        $upOrder = unserialize($inv->up_response);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($sale_id, $inv->return_id);
        endif;

        $isGstSale = $this->site->isGstSale($sale_id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;
        //var_dump( $inv->GstSale);

        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }
        $print = array();
        $print['print_option'] = $this->site->defaultPrinterOption($_PID);
        $print['rows'] = $this->data['rows'] = $this->pos_model->getAllInvoiceItems($sale_id);
        $biller_id = $inv->biller_id;
        $customer_id = $inv->customer_id;
        $print['biller'] = $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
        $print['customer'] = $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);
        $print['payments'] = $this->data['payments'] = $this->pos_model->getInvoicePayments($sale_id);
        $print['pos'] = $this->data['pos'] = $this->pos_model->getSetting();
        unset($print['pos']->pos_theme);
        $print['barcode'] = $this->data['barcode'] = $this->barcode($inv->reference_no, 'code128', 30);
        $print['return_sale'] = $this->data['return_sale'] = $inv->return_id ? $this->pos_model->getInvoiceByID($inv->return_id) : null;
        $print['return_rows'] = $this->data['return_rows'] = $inv->return_id ? $this->pos_model->getAllInvoiceItems($inv->return_id) : null;
        $print['return_payments'] = $this->data['return_payments'] = $this->data['return_sale'] ? $this->pos_model->getInvoicePayments($this->data['return_sale']->id) : null;
        $print['inv'] = $this->data['inv'] = $inv;
        $print['upOrder'] = $this->data['upOrder'] = $upOrder;
        $print['sid'] = $this->data['sid'] = $sale_id;
        $print['modal'] = $this->data['modal'] = $modal;
        $print['page_title'] = $this->data['page_title'] = $this->lang->line("invoice");
        $print['taxItems'] = $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($inv->id, $inv->return_id);
        //Set Sale items image

        if (!empty($print['rows'])) {
            foreach ($print['rows'] as $key => $row) {
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $print['rows'][$key]->image = $product->image;
            }
        }

        $Settings = $this->Settings; //$this->site->get_setting();


        $print['pos_type'] = $Settings->pos_type;
        $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;

        $this->data['sms_limit'] = $this->sma->BalanceSMS();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma'):
            $print['patient_name'] = $inv->cf1;
            $print['doctor_name'] = $inv->cf2;
        endif;
        $this->data['show_kot'] = false;
        if (isset($Settings->pos_type) && $Settings->pos_type == 'restaurant'):
            $this->data['show_kot'] = true;
        endif;

        $this->load->view($this->theme . 'pos/view_up', $this->data);

        $print['brcode'] = $this->sma->save_barcode($inv->reference_no, 'code128', 66, false);
        $print['qrcode'] = $this->sma->qrcode('link', urlencode(site_url('sales/view/' . $inv->id)), 2);
        $arr = explode("'", $print['brcode']);
        $print['brcode'] = $arr[1];
        $qrr = explode("'", $print['qrcode']);
        $print['qrcode'] = $qrr[1];
        //echo $print['rows'][0]->net_unit_price;
        foreach ($print['rows'] as $key => $row) {
            //Set Sale items image.
            foreach ($row as $key2 => $value) {
                if ($key2 == 'quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'unit_quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'product_id') {
                    $product = $this->pos_model->getProductByID($value, $select = 'image');
                    $print['rows'][$key]->cf1 = $product->image;
                }
            }
        }


        if ($sale_id != $_SESSION['print'] && $_SESSION['print_type'] == null) {
            $row_taxes_print = $inv->rows_tax;
            unset($inv->rows_tax);
            $row_taxes_print_arr = array();
            if (count($row_taxes_print)) {
                foreach ($row_taxes_print as $_key => $_data) {
                    foreach ($_data as $_key1 => $value1) {
                        $row_taxes_print_arr[] = $value1;
                    }
                }
            }
            $inv->rows_tax = $row_taxes_print_arr;
            ?>
<script>
window.MyHandler.setPrintRequest('<?php echo json_encode($print); ?>');
</script>
<?php
            unset($print);
        }
        $_SESSION['print'] = $sale_id;
    }

    public function view($sale_id = null, $modal = null) {
        $this->data['myclass'] = $ci = & get_instance();
        $this->data['pos_settingss'] = $this->data['pos_settings'];
        $this->data['go_back'] = $_SERVER["HTTP_REFERER"];
        $this->sma->checkPermissions('index');
        if ($this->input->get('id')) {
            $sale_id = $this->input->get('id');
        }
        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        $cfields = $this->site->getCustomeFieldsLabel('customer');
        $this->data['custome_fields'] = $cfields['customer'];
        $this->load->helper('text');
        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['message'] = $this->session->flashdata('message');
        $inv = $this->pos_model->getInvoiceByID($sale_id);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($sale_id, $inv->return_id);
        endif;

        $isGstSale = $this->site->isGstSale($sale_id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;
        //var_dump( $inv->GstSale);

        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }
        $print = array();
        $print['print_option'] = $this->site->defaultPrinterOption($_PID);
        $print['rows'] = $this->data['rows'] = $this->pos_model->getAllInvoiceItems($sale_id);
        $biller_id = $inv->biller_id;
        $customer_id = $inv->customer_id;
        $print['biller'] = $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
        $print['customer'] = $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);
        $print['payments'] = $this->data['payments'] = $this->pos_model->getInvoicePayments($sale_id);
        $print['pos'] = $this->data['pos'] = $this->pos_model->getSetting();
        unset($print['pos']->pos_theme);
        $print['barcode'] = $this->data['barcode'] = $this->barcode($inv->reference_no, 'code128', 30);
        //$print['return_sale'] = $this->data['return_sale'] = $inv->return_id ? $this->pos_model->getInvoiceByID($inv->return_id) : null;
        //$print['return_rows'] = $this->data['return_rows'] = $inv->return_id ? $this->pos_model->getAllInvoiceItems($inv->return_id) : null;
        $return_sales = $inv->return_id ? $this->pos_model->getAllReturnInvoiceByID($sale_id) : NULL;
        //print_r($return_sales);
        //echo '<br>';
        $product_discount = 0;
        $product_tax = 0;
        $total = 0;
        $grand_total = 0;
        $order_discount = 0;
        $order_tax = 0;
        $paid = 0;
        $rounding = 0;
        $ArrReturnId = array();
        $ReturnIds = '';
        if (!empty($return_sales)) {
            foreach ($return_sales as $Keys => $Vals) {
                $product_discount = $product_discount + $Vals['product_discount'];
                $product_tax = $product_tax + $Vals['product_tax'];
                $total = $total + $Vals['total'];
                $rounding = $rounding + $Vals['rounding'];
                $grand_total = $grand_total + $Vals['grand_total'];
                $order_discount = $order_discount + $Vals['order_discount'];
                $order_tax = $order_tax + $Vals['order_tax'];
                $paid = $paid + $Vals['paid'];
                $ArrReturnId[] = $Vals['id'];
                //echo '<br/>';
            }
            $print['return_sale'] = $this->data['return_sale'] = (object) array(
                        'product_discount' => $product_discount,
                        'product_tax' => $product_tax,
                        'total' => $total,
                        'rounding' => $rounding,
                        'grand_total' => $grand_total,
                        'order_discount' => $order_discount,
                        'order_tax' => $order_tax,
                        'paid' => $paid,
            );
            $ReturnIds = "'" . implode("','", $ArrReturnId) . "'"; //implode("','", $ArrReturnId);
        }


        $print['return_rows'] = $this->data['return_rows'] = $inv->return_id ? $this->pos_model->getAllReturnInvoiceItems($sale_id) : null;
        if (!empty($ArrReturnId)) {
            $print['return_payments'] = $this->data['return_payments'] = $this->data['return_sale'] ? $this->pos_model->getInvoicePayments1($ReturnIds) : null;
        }
        $print['inv'] = $this->data['inv'] = $inv;

        if ($inv->order_no) {
            $print['shipping_details'] = $this->data['shipping_details'] = $this->pos_model->getShipingDetails($inv->order_no);
        }

        $print['sid'] = $this->data['sid'] = $sale_id;
        $print['modal'] = $this->data['modal'] = $modal;
        $print['page_title'] = $this->data['page_title'] = $this->lang->line("invoice");
        $print['taxItems'] = $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($inv->id, $inv->return_id);
        $print['salestax'] = $this->data['salestax'] = $this->sales_model->getSalesItemsTaxes($sale_id); ///my code
        $this->data['dueAmount'] = $this->pos_model->partialAmount($customer_id);
        $totalDues = $this->data['dueAmount'];
        foreach ($totalDues as $totalDue ) {
            $this->data['dueAmounts'] = $totalDue;
        }

       //Set Sale items image

        if (!empty($print['rows'])) {
            foreach ($print['rows'] as $key => $row) {
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $print['rows'][$key]->image = $product->image;
            }
        }

        $Settings = $this->Settings; //$this->site->get_setting();

        /*         * **************  Server Load Time Error  ********************* */
//        if ($this->Settings->synch_reward_points) {
//            if ($inv->sale_status == 'completed' && $inv->payment_status == 'paid'):
//                $syncID = $this->pos_model->syncOrderReward($inv->id);
//                if ($syncID):
//                    $ci = get_instance();
//                    $order_pt = floor(($this->data['inv']->grand_total / $Settings->each_spent) * $Settings->ca_point);
//                    $data = array();
//                    $data['customer_id'] = $this->data['customer']->phone;
//                    $data['merchant_id'] = $ci->config->item('merchant_phone');
//                    $data['points'] = $order_pt;
//                    $data['order_id'] = $sale_id;
//                    $data['remark'] = 'Order ID ' . $sale_id . ' point achived' . $order_pt;
//                    $url = 'http://simplypos.co.in/api/v1/customer/merchant/transaction/reward';
//                   // $res = $this->post_to_url($url, $data);
//                endif;
//            endif;
//        }//end if.
        /*         * ************************ End Serrver Load Time Error ******** */

        $print['pos_type'] = $Settings->pos_type;

        $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;

        $this->data['sms_limit'] = $this->sma->BalanceSMS();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $print['patient_name'] = $inv->cf1;
            $print['doctor_name'] = $inv->cf2;
            $this->load->view($this->theme . 'pos/view-pharma', $this->data);
        } else if ($Settings->default_printer == '4') {
            $this->load->view($this->theme . 'pos/view_tally', $this->data);
        } else {
            $this->data['show_kot'] = false;
            if (isset($Settings->pos_type) && $Settings->pos_type == 'restaurant'):
                $this->data['show_kot'] = true;
            endif;

            $this->load->view($this->theme . 'pos/view', $this->data);
        }

        $print['brcode'] = $this->sma->save_barcode($inv->reference_no, 'code128', 66, false);
        $print['qrcode'] = $this->sma->qrcode('link', urlencode(site_url('sales/view/' . $inv->id)), 2);
        $arr = explode("'", $print['brcode']);
        $print['brcode'] = $arr[1];
        $qrr = explode("'", $print['qrcode']);
        $print['qrcode'] = $qrr[1];
        //echo $print['rows'][0]->net_unit_price;
        foreach ($print['rows'] as $key => $row) {
            //Set Sale items image.
            foreach ($row as $key2 => $value) {
                if ($key2 == 'quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'unit_quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'product_id') {
                    $product = $this->pos_model->getProductByID($value, $select = 'image');
                    $print['rows'][$key]->cf1 = $product->image;
                }
            }
        }
        /*
          foreach($print['payments'] as $key => $row){
          foreach($row as $key2 => $value){
          $print['payments'][$key]->$key2 = round($value);
          }
          }
          foreach($print['inv'] as $key => $row){
          $print['inv']->$key = round($row, 2);
          }
         */

        if (( isset($_SESSION['print']) && $sale_id != $_SESSION['print']) && (isset($_SESSION['print_type']) && $_SESSION['print_type'] == null)) {
            $row_taxes_print = $inv->rows_tax;
            unset($inv->rows_tax);
            $row_taxes_print_arr = array();
            if (count($row_taxes_print)) {
                foreach ($row_taxes_print as $_key => $_data) {
                    foreach ($_data as $_key1 => $value1) {
                        $row_taxes_print_arr[] = $value1;
                    }
                }
            }
            $inv->rows_tax = $row_taxes_print_arr;
            ?>
<script>
window.MyHandler.setPrintRequest('<?php echo json_encode($print); ?>');
</script>
<?php
            unset($print);
        }
        $_SESSION['print'] = $sale_id;
    }

    public function register_details() {
        $this->sma->checkPermissions('index');
        $register_open_time = $this->session->userdata('register_open_time');
        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['ccsales'] = $this->pos_model->getRegisterCCSales($register_open_time);
        $this->data['cashsales'] = $this->pos_model->getRegisterCashSales($register_open_time);
        $this->data['chsales'] = $this->pos_model->getRegisterChSales($register_open_time);
        $this->data['gcsales'] = $this->pos_model->getRegisterGCSales($register_open_time);
        $this->data['pppsales'] = $this->pos_model->getRegisterPPPSales($register_open_time);
        /* 5-11-2019 */
        $this->data['depositsales'] = $this->pos_model->getRegisterdepSales($register_open_time);
        /* end */
        /* --- 13-03-19 --- */
        $this->data['othersales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'other');
        $this->data['dcsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'DC');
        $this->data['neftsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'NEFT');
        $this->data['paytmsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'PAYTM');
        $this->data['googlepaysales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'Googlepay');
        $this->data['swiggysales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'swiggy');
        $this->data['zomatosales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'zomato');
        $this->data['ubereatssales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'ubereats');
        $this->data['magicpinsales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'magicpin');
        $this->data['complimentrysales'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'complimentry');

        $this->data['upiqrcode'] = $this->pos_model->getRegisterPaymentSales($register_open_time, $user_id, 'UPI_QRCODE');

        /* --- end 13-03-19  -- */
        $this->data['stripesales'] = $this->pos_model->getRegisterStripeSales($register_open_time);
        $this->data['authorizesales'] = $this->pos_model->getRegisterAuthorizeSales($register_open_time);
        $this->data['totalsales'] = $this->pos_model->getRegisterSales($register_open_time);
        $this->data['duesales'] = $this->pos_model->getdueAmt($register_open_time); //18-03-19
        $this->data['duepartial'] = $this->pos_model->getpartialAmt($register_open_time); //20-03-19

        $this->data['refunds'] = $this->pos_model->getRegisterRefunds($register_open_time);
        $this->data['returned_other'] = $this->pos_model->getRegisterRefundsOther($register_open_time);

        $this->data['expenses'] = $this->pos_model->getRegisterExpenses($register_open_time);

        $this->data['deposit_received'] = $this->pos_model->getRegisterDeposit($register_open_time);
        $this->load->view($this->theme . 'pos/register_details', $this->data);
    }

    public function today_sale() {
        if (!$this->Owner && !$this->Admin) {
            // $this->session->set_flashdata('error', lang('access_denied'));
            // $this->sma->md();
            $user_id = $this->session->userdata('user_id');
        } else {
            $user_id = NULL;
        }

        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['ccsales'] = $this->pos_model->getTodayCCSales($user_id);       //Paid By CC
        $this->data['dcsales'] = $this->pos_model->getTodayDCSales($user_id);               //Paid By DC
        $this->data['gcsales'] = $this->pos_model->getTodayGiftCardSales($user_id);         //Paid By GiftCard
        $this->data['othersales'] = $this->pos_model->getTodayOtherSales($user_id);         //Paid By Others
        $this->data['cashsales'] = $this->pos_model->getTodayCashSales($user_id);           //Paid By Cash
        $this->data['chsales'] = $this->pos_model->getTodayChSales($user_id);               //Paid BY Cheque
        $this->data['pppsales'] = $this->pos_model->getTodayPPPSales($user_id);
        $this->data['stripesales'] = $this->pos_model->getTodayStripeSales($user_id);
        $this->data['authorizesales'] = $this->pos_model->getTodayAuthorizeSales($user_id);
        $this->data['Qrcode'] = $this->pos_model->getTodayUpiQrcodePayments($user_id);//Qrcode

        /* 5-11-2019 */
        $this->data['depositsales'] = $this->pos_model->getTodayDepSales($register_open_time, $user_id);
        /* end */
        /* ---- 13-03-19 --- */
        $this->data['duepayment'] = $this->pos_model->getTodayDueSales($user_id);
        $this->data['duepartial'] = $this->pos_model->getcalpartial($user_id); //20-03-19
        /* ----End  13-03-19 --- */
        // $this->data['totalsales'] = $this->pos_model->getTodaySales($user_id);
        $this->data['totalsales'] = $this->pos_model->getTodaySale($user_id);
        $this->data['totalsalespaid'] = $this->pos_model->getTodaySalesPaid($user_id);
        $this->data['refunds'] = $this->pos_model->getTodayRefunds($user_id);
        $this->data['expenses'] = $this->pos_model->getTodayExpenses($user_id);
        $this->data['deposit_received'] = $this->pos_model->getTodayDeposit($user_id);
        $this->data['paytmsales'] = $this->pos_model->getRegisterPaymentSales($date, $user_id, 'PAYTM');
        $this->data['paymentOptions'] = ['paytm' => 'PAYTM', 'neft' => 'NEFT', 'google_pay' => 'Googlepay', 'swiggy' => 'swiggy', 'zomato' => 'zomato', 'ubereats' => 'ubereats', 'magicpin' => 'magicpin', 'complimentary' => 'complimentry'];
        foreach ($this->data['paymentOptions'] as $payOpt_key => $payOpt) {
            if ($this->data['pos_settings']->$payOpt_key) {
                $this->data[$payOpt_key] = $this->pos_model->getTodayPaymentOptionSales($payOpt, $user_id);
            }
        }//end foreach.


        $this->load->view($this->theme . 'pos/today_sale', $this->data);
    }

    public function check_pin() {
        $pin = $this->input->post('pw', true);
        if ($pin == $this->pos_pin) {
            $this->sma->send_json(array('res' => 1));
        }
        $this->sma->send_json(array('res' => 0));
    }

    public function barcode($text = null, $bcs = 'code128', $height = 50) {
        return site_url('products/gen_barcode/' . $text . '/' . $bcs . '/' . $height);
    }

    public function settings() {

       
        $this->form_validation->set_message('is_natural_no_zero', $this->lang->line('no_zero_required'));
        $this->form_validation->set_rules('pro_limit', $this->lang->line('pro_limit'), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('pin_code', $this->lang->line('delete_code'), 'numeric');
        $this->form_validation->set_rules('category', $this->lang->line('default_category'), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('customer', $this->lang->line('default_customer'), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('biller', $this->lang->line('default_biller'), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('default_eshop_theame', $this->lang->line('default_eshop_theame'), 'required');
        $this->form_validation->set_rules('eshop_free_delivery_on_order', $this->lang->line('eshop_free_delivery_on_order'), 'required|numeric');
        $this->form_validation->set_rules('offers_status', $this->lang->line('offers_status'), 'required');
        //$this->form_validation->set_rules('default_eshop_biller', $this->lang->line('eshop_biller'), 'required|is_natural_no_zero');

        if ($this->form_validation->run() == true) {

            //POS2 Banners
            if (!empty($_FILES['banner_image']['tmp_name'])) {
                $uploadPath = "assets/mdata/$this->Customer_assets/uploads/pos2_banners/";
                $i = 0;
                foreach ($_FILES['banner_image']['name'] as $key => $file) {

                    if (empty($file))
                        continue;
                    $i++;
                    list($filename, $ext) = explode('.', $file);
                    //$bannerImage = "banner_static_".$key.'.'.$ext;
                    $bannerImage = md5(time() . $filename) . '.' . $ext;

                    if (copy($_FILES['banner_image']['tmp_name'][$key], $uploadPath . $bannerImage)) {
                        $banners['pos2_banner_' . $key] = $uploadPath . $bannerImage;
                    }
                }//end foreach
            }


            $data = array(
                'pro_limit' => $this->input->post('pro_limit'),
                'pin_code' => $this->input->post('pin_code') ? $this->input->post('pin_code') : null,
                'default_category' => $this->input->post('category'),
                'default_customer' => $this->input->post('customer'),
                'default_biller' => $this->input->post('biller'),
                'display_time' => $this->input->post('display_time'),
                'receipt_printer' => $this->input->post('receipt_printer'),
                'cash_drawer_codes' => $this->input->post('cash_drawer_codes'),
                'cf_title1' => $this->input->post('cf_title1'),
                'cf_title2' => $this->input->post('cf_title2'),
                'cf_value1' => $this->input->post('cf_value1'),
                'cf_value2' => $this->input->post('cf_value2'),
                'focus_add_item' => $this->input->post('focus_add_item'),
                'add_manual_product' => $this->input->post('add_manual_product'),
                'customer_selection' => $this->input->post('customer_selection'),
                'add_customer' => $this->input->post('add_customer'),
                'toggle_category_slider' => $this->input->post('toggle_category_slider'),
                'toggle_subcategory_slider' => $this->input->post('toggle_subcategory_slider'),
                'toggle_brands_slider' => $this->input->post('toggle_brands_slider'),
                'cancel_sale' => $this->input->post('cancel_sale'),
                'suspend_sale' => $this->input->post('suspend_sale'),
                'print_items_list' => $this->input->post('print_items_list'),
                'finalize_sale' => $this->input->post('finalize_sale'),
                'today_sale' => $this->input->post('today_sale'),
                'open_hold_bills' => $this->input->post('open_hold_bills'),
                'close_register' => $this->input->post('close_register'),
                'tooltips' => $this->input->post('tooltips'),
                'keyboard' => $this->input->post('keyboard'),
                'pos_printers' => $this->input->post('pos_printers'),
                'java_applet' => $this->input->post('enable_java_applet'),
                'product_button_color' => $this->input->post('product_button_color'),
                'paypal_pro' => $this->input->post('paypal_pro'),
                'stripe' => $this->input->post('stripe'),
                'authorize' => $this->input->post('authorize'),
                'rounding' => $this->input->post('rounding'),
                'item_order' => $this->input->post('item_order'),
                'after_sale_page' => $this->input->post('after_sale_page'),
                'instamojo' => $this->input->post('instamojo'),
                'ccavenue' => $this->input->post('ccavenue'),
                'paytm' => $this->input->post('paytm'),
                'paytm_opt' => $this->input->post('paytm_opt'),
                'credit_card' => $this->input->post('credit_card'),
                'debit_card' => $this->input->post('debit_card'),
                'gift_card' => $this->input->post('gift_card'),
                'neft' => $this->input->post('neft'),
                'google_pay' => $this->input->post('google_pay'),
                'swiggy' => $this->input->post('swiggy'),
                'zomato' => $this->input->post('zomato'),
                'ubereats' => $this->input->post('ubereats'),
                'magicpin' => $this->input->post('magicpin'), //magic_pin payment
                'complimentary' => $this->input->post('complimentary'),
                'paynear' => $this->input->post('paynear'),
                'payumoney' => $this->input->post('payumoney'),
                'award_point' => $this->input->post('Award_Point_Payment'),
                'Cheque' => $this->input->post('Cheque'),
                'deposit' => $this->input->post('deposit'),
                'razorpay' => $this->input->post('razorpay'),
                'default_eshop_warehouse' => $this->input->post('default_eshop_warehouse'),
                'default_eshop_pay' => $this->input->post('default_eshop_pay'),
                'eshop_cod' => $this->input->post('eshop_cod'),
                'eshop_order_tax' => $this->input->post('eshop_order_tax'),
                'default_eshop_theame' => $this->input->post('default_eshop_theame'),
                'eshop_free_delivery_on_order' => $this->input->post('eshop_free_delivery_on_order'),
                'pos_screen_products' => $this->input->post('pos_screen_products'),
                'pos_theme' => json_encode($this->input->post('pos_theme')),
                'invoice_auto_sms' => $this->input->post('invoice_auto_sms'),
                'offers_status' => $this->input->post('offers_status'),
                'active_offer_category' => $this->input->post('active_offer_category'),
                'recent_pos_limit' => $this->input->post('recent_pos_limit'),
                'display_token' => $this->input->post('display_token'),
                'display_seller' => $this->input->post('display_seller'),
                'alert_qty_auto_email' => $this->input->post('alert_qty_auto_email'),
                'daily_sale_auto_email' => $this->input->post('daily_sale_auto_email'),
                'pos_amount' => $this->input->post('pos_amount'),
                'display_category' => $this->input->post('display_category'),
                'UPI_QRCODE' => $this->input->post('UPI_QRCODE'),
                'product_variant_popup' => $this->input->post('product_variant_popup'),
                'use_product_price' => $this->input->post('use_product_price'),
                'cart_show_pos2' => $this->input->post('cart_show_pos2'),
                'eshop_overselling' => $this->input->post('eshop_overselling'),
                'default_eshop_biller' => $this->input->post('default_eshop_biller'), 
                'display_qr_code_scanner' => $this->input->post('display_qr_code_scanner'),
                'change_qty_as_per_user_price' => $this->input->post('change_qty_as_per_user_price'),
                'order_receipt' => $this->input->post('order_receipt'),
                'add_deposit_btn_show' => $this->input->post('add_deposit_btn_show'),
                'categorylocation' => $this->input->post('categorylocation'),
                'active_repeat_customer_discount' => $this->input->post('active_repeat_customer_discount'),
                'auto_apply_repeat_customer_discount' => $this->input->post('auto_apply_repeat_customer_discount'),
                'combo_add_pos' => $this->input->post('combo_add_pos'),
                'restaurant_table' => ($this->input->post('restaurant_table') ? $this->input->post('restaurant_table') : 0 ),
                'pos_price_display' => ($this->input->post('pos_price_display') ? $this->input->post('pos_price_display') : 0 ),
            );


            if ($this->input->post('order_receipt') == '1') {
                $data['print_all_category'] = $this->input->post('print_all_category');
            } else {
                $data['print_all_category'] = NULL;
            }
            if ($this->input->post('order_receipt') == '1' && $this->input->post('print_all_category') == '0') {
                $data['categorys'] = implode(",", $this->input->post('categorys'));
            } else {
                $data['categorys'] = NULL;
            }

            if (isset($banners)) {
                $data = array_merge($data, $banners);
            }

            $payment_config = array(
                'APIUsername' => $this->input->post('APIUsername'),
                'APIPassword' => $this->input->post('APIPassword'),
                'APISignature' => $this->input->post('APISignature'),
                'stripe_secret_key' => $this->input->post('stripe_secret_key'),
                'stripe_publishable_key' => $this->input->post('stripe_publishable_key'),
                'api_login_id' => $this->input->post('api_login_id'),
                'api_transaction_key' => $this->input->post('api_transaction_key'),
                'instamojo_api_key' => $this->input->post('instamojo_api_key'),
                'instamojo_auth_token' => $this->input->post('instamojo_auth_token'),
                'ccavenue_merchant_id' => $this->input->post('ccavenue_merchant_id'),
                'ccavenue_access_code' => $this->input->post('ccavenue_access_code'),
                'ccavenue_working_key' => $this->input->post('ccavenue_working_key'),
                'PAYTM_ENVIRONMENT' => $this->input->post('PAYTM_ENVIRONMENT'),
                'PAYTM_MERCHANT_KEY' => $this->input->post('PAYTM_MERCHANT_KEY'),
                'PAYTM_MERCHANT_MID' => $this->input->post('PAYTM_MERCHANT_MID'),
                'PAYTM_MERCHANT_WEBSITE' => $this->input->post('PAYTM_MERCHANT_WEBSITE'),
                'PAYNEAR_APP_SECRET_KEY' => $this->input->post('PAYNEAR_APP_SECRET_KEY'),
                'PAYNEAR_SECRET_KEY' => $this->input->post('PAYNEAR_SECRET_KEY'),
                'PAYNEAR_MERCHANT_ID' => $this->input->post('PAYNEAR_MERCHANT_ID'),
                'PAYNEAR_APP_MERCHANT_ID' => $this->input->post('PAYNEAR_APP_MERCHANT_ID'),
                'PAYUMONEY_MID' => $this->input->post('PAYUMONEY_MID'),
                'PAYUMONEY_KEY' => $this->input->post('PAYUMONEY_KEY'),
                'PAYUMONEY_SALT' => $this->input->post('PAYUMONEY_SALT'),
                'PAYUMONEY_AUTH_HEADER' => $this->input->post('PAYUMONEY_AUTH_HEADER'),
                'PAYNEAR_SANDBOX' => 0,
            );
        } elseif ($this->input->post('update_settings')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER['HTTP_REFERER']);
        }

        if ($this->form_validation->run() == true && $this->pos_model->updateSetting($data)) {
            if ($this->write_payments_config($payment_config)) {
                $this->session->set_flashdata('message', $this->lang->line('pos_setting_updated'));
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                $this->session->set_flashdata('error', $this->lang->line('pos_setting_updated_payment_failed'));
                redirect($_SERVER['HTTP_REFERER']);
            }
        } else {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

            $this->data['pos'] = $this->pos_model->getSetting();

            $this->data['pos']->pos_theme = json_decode($this->data['pos']->pos_theme);

            $this->data['categories'] = $this->site->getAllCategories();

//$this->data['customer'] = $this->pos_model->getCompanyByID($this->pos_settings->default_customer);
            $this->data['billers'] = $this->pos_model->getAllBillerCompanies();
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['offer_categories'] = $this->site->getOfferCategories();
            $this->config->load('payment_gateways');

            $this->data['stripe_secret_key'] = $this->config->item('stripe_secret_key');
            $this->data['stripe_publishable_key'] = $this->config->item('stripe_publishable_key');
            $authorize = $this->config->item('authorize');
            $this->data['api_login_id'] = $authorize['api_login_id'];
            $this->data['api_transaction_key'] = $authorize['api_transaction_key'];
            $this->data['APIUsername'] = $this->config->item('APIUsername');
            $this->data['APIPassword'] = $this->config->item('APIPassword');
            $this->data['APISignature'] = $this->config->item('APISignature');
            $this->data['paypal_balance'] = null; // $this->pos_settings->paypal_pro ? $this->paypal_balance() : NULL;
            $this->data['stripe_balance'] = null; // $this->pos_settings->stripe ? $this->stripe_balance() : NULL;
            $instamojo = $this->config->item('instamojo');
            $this->data['instamojo_auth_token'] = $instamojo['AUTH_TOKEN'];
            $this->data['instamojo_api_key'] = $instamojo['API_KEY'];

            $ccavenue = $this->config->item('ccavenue');
            $this->data['ccavenue_merchant_id'] = $ccavenue['MERCHANT_ID'];
            $this->data['ccavenue_access_code'] = $ccavenue['ACCESS_CODE'];
            $this->data['ccavenue_working_key'] = $ccavenue['API_KEY'];

            $paytm = $this->config->item('paytm');
            $this->data['PAYTM_ENVIRONMENT'] = $paytm['PAYTM_ENVIRONMENT'];
            $this->data['PAYTM_MERCHANT_KEY'] = $paytm['PAYTM_MERCHANT_KEY'];
            $this->data['PAYTM_MERCHANT_MID'] = $paytm['PAYTM_MERCHANT_MID'];
            $this->data['PAYTM_MERCHANT_WEBSITE'] = $paytm['PAYTM_MERCHANT_WEBSITE'];

            $paynear = $this->config->item('paynear');
            $this->data['PAYNEAR_APP_SECRET_KEY'] = $paynear['PAYNEAR_APP_SECRET_KEY'];
            $this->data['PAYNEAR_SECRET_KEY'] = $paynear['PAYNEAR_SECRET_KEY'];
            $this->data['PAYNEAR_MERCHANT_ID'] = $paynear['PAYNEAR_MERCHANT_ID'];
            $this->data['PAYNEAR_APP_MERCHANT_ID'] = $paynear['PAYNEAR_APP_MERCHANT_ID'];
            $this->data['PAYNEAR_SANDBOX'] = $paynear['PAYNEAR_SANDBOX'];

            $payumoney = $this->config->item('payumoney');
            $this->data['PAYUMONEY_MID'] = $payumoney['PAYUMONEY_MID'];
            $this->data['PAYUMONEY_KEY'] = $payumoney['PAYUMONEY_KEY'];
            $this->data['PAYUMONEY_SALT'] = $payumoney['PAYUMONEY_SALT'];
            $this->data['PAYUMONEY_AUTH_HEADER'] = $payumoney['PAYUMONEY_AUTH_HEADER'];

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('pos_settings')));
            $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);

            $this->page_construct('pos/settings', $meta, $this->data);
        }
    }

    public function write_payments_config($config) {
        
        $file_contents = file_get_contents('./assets/config_dumps/payment_gateways.php');
        $output_path = APPPATH . 'config/payment_gateways.php';
        $this->load->library('parser');
        $parse_data = array(
            'APIUsername' => $config['APIUsername'],
            'APIPassword' => $config['APIPassword'],
            'APISignature' => $config['APISignature'],
            'stripe_secret_key' => $config['stripe_secret_key'],
            'stripe_publishable_key' => $config['stripe_publishable_key'],
            'api_login_id' => $config['api_login_id'],
            'api_transaction_key' => $config['api_transaction_key'],
            'instamojo_api_key' => $config['instamojo_api_key'],
            'instamojo_auth_token' => $config['instamojo_auth_token'],
            'ccavenue_merchant_id' => $config['ccavenue_merchant_id'],
            'ccavenue_access_code' => $config['ccavenue_access_code'],
            'ccavenue_working_key' => $config['ccavenue_working_key'],
            'PAYTM_ENVIRONMENT' => $config['PAYTM_ENVIRONMENT'],
            'PAYTM_MERCHANT_KEY' => $config['PAYTM_MERCHANT_KEY'],
            'PAYTM_MERCHANT_MID' => $config['PAYTM_MERCHANT_MID'],
            'PAYTM_MERCHANT_WEBSITE' => $config['PAYTM_MERCHANT_WEBSITE'],
            'PAYNEAR_SECRET_KEY' => $config['PAYNEAR_SECRET_KEY'],
            'PAYNEAR_MERCHANT_ID' => $config['PAYNEAR_MERCHANT_ID'],
            'PAYNEAR_SANDBOX' => $config['PAYNEAR_SANDBOX'],
            'PAYNEAR_APP_SECRET_KEY' => $config['PAYNEAR_APP_SECRET_KEY'],
            'PAYNEAR_APP_MERCHANT_ID' => $config['PAYNEAR_APP_MERCHANT_ID'],
            'PAYUMONEY_MID' => $config['PAYUMONEY_MID'],
            'PAYUMONEY_KEY' => $config['PAYUMONEY_KEY'],
            'PAYUMONEY_SALT' => $config['PAYUMONEY_SALT'],
            'PAYUMONEY_AUTH_HEADER' => $config['PAYUMONEY_AUTH_HEADER'],
        );
        $new_config = $this->parser->parse_string($file_contents, $parse_data);

        $handle = fopen($output_path, 'w+');
        @chmod($output_path, 0777);

        if (is_writable($output_path)) {
            if (fwrite($handle, $new_config)) {
                @chmod($output_path, 0644);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function opened_bills($per_page = 0) {
        $this->load->library('pagination');

        //$this->table->set_heading('Id', 'The Title', 'The Content');
        if ($this->input->get('per_page')) {
            $per_page = $this->input->get('per_page');
        }

        $config['base_url'] = site_url('pos/opened_bills');
        $config['total_rows'] = $this->pos_model->bills_count();
        $config['per_page'] = 6;
        $config['num_links'] = 3;

        $config['full_tag_open'] = '<ul class="pagination pagination-sm">';
        $config['full_tag_close'] = '</ul>';
        $config['first_tag_open'] = '<li>';
        $config['first_tag_close'] = '</li>';
        $config['last_tag_open'] = '<li>';
        $config['last_tag_close'] = '</li>';
        $config['next_tag_open'] = '<li>';
        $config['next_tag_close'] = '</li>';
        $config['prev_tag_open'] = '<li>';
        $config['prev_tag_close'] = '</li>';
        $config['num_tag_open'] = '<li>';
        $config['num_tag_close'] = '</li>';
        $config['cur_tag_open'] = '<li class="active"><a>';
        $config['cur_tag_close'] = '</a></li>';

        $this->pagination->initialize($config);
        $data['r'] = true;
        $bills = $this->pos_model->fetch_bills($config['per_page'], $per_page, 1);
        if (!empty($bills)) {
            $html = $susdelIcon = "";
            $html .= '<ul class="ob">';
            foreach ($bills as $bill) {
                if ($this->data['Admin'] || $this->data['Owner'] || $this->data['GP']['sales-delete-suspended'] == 1) {
                    $susdelIcon = '<a delete-id="' . $bill->id . '" class="delete_suspend" href="javascript:void(0);"><i class="fa fa-trash" aria-hidden="true"></i></a>';
                }
                $pos_settingss = $this->data['pos_settings'];
                $ShowTokenText = '';
                if ($pos_settingss->display_token == 1)
                    $ShowTokenText = 'Token No : ' . $bill->kot_tokan;
                $html .= '<li>' . $susdelIcon . '<button type="button" class="btn btn-info sus_sale" id="' . $bill->id . '"> ' . $ShowTokenText . '<p>' . $bill->suspend_note . '</p><strong>' . $bill->customer . '</strong><br>' . lang('date') . ': ' . $bill->date . '<br>' . lang('items') . ': ' . $bill->count . '<br>' . lang('total') . ': ' . $this->sma->formatMoney($bill->total) . '</button></li>';
            }
            $html .= '</ul>';
        } else {
            $html = "<h3>" . lang('no_opeded_bill') . "</h3><p>&nbsp;</p>";
            $data['r'] = false;
        }

        $data['html'] = $html;

        $data['page'] = $this->pagination->create_links();
        echo $this->load->view($this->theme . 'pos/opened', $data, true);
    }

    public function delete($id = null) {
        $this->sma->checkPermissions('index');
        if ($this->pos_model->deleteBill($id)) {
            echo lang("suspended_sale_deleted");
        }
    }

    /*
      public function email_receipt($sale_id = null) {
      $this->sma->checkPermissions('index');
      if ($this->input->post('id')) {
      $sale_id = $this->input->post('id');
      }
      $_PID = $this->Settings->default_printer;
      $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

      if (!$sale_id) {
      die('No sale selected.');
      }
      if ($this->input->post('email')) {
      $to = $this->input->post('email');
      }
      $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
      $this->data['message'] = $this->session->flashdata('message');

      $this->data['rows'] = $this->pos_model->getAllInvoiceItems($sale_id);
      $inv = $this->pos_model->getInvoiceByID($sale_id);
      if ($this->data['default_printer']->tax_classification_view):
      $inv->rows_tax = $this->sales_model->getAllTaxItems($sale_id, $inv->return_id);
      endif;
      $isGstSale = $this->site->isGstSale($sale_id);
      $inv->GstSale = !empty($isGstSale) ? 1 : 0;

      $biller_id = $inv->biller_id;
      $customer_id = $inv->customer_id;
      $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
      $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);

      $this->data['payments'] = $this->pos_model->getInvoicePayments($sale_id);
      $this->data['pos'] = $this->pos_model->getSetting();
      $this->data['barcode'] = $this->barcode($inv->reference_no, 'code128', 30);
      $this->data['inv'] = $inv;
      $this->data['sid'] = $sale_id;
      $this->data['page_title'] = $this->lang->line("invoice");

      if (!$to) {
      $to = $this->data['customer']->email;
      }
      if (!$to) {
      $this->sma->send_json(array('msg' => $this->lang->line("no_meil_provided")));
      }
      $this->data['customer']->email = $to;
      $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($inv->id, $inv->return_id);
      $Settings = $this->Settings; //$this->site->get_setting();
      //Set Sale items image
      $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;
      if (!empty($this->data['rows'])) {
      foreach ($this->data['rows'] as $key => $row) {
      $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
      $this->data['rows'][$key]->image = $product->image;
      }
      }

      $receipt = $this->load->view($this->theme . 'pos/email_receipt', $this->data, true);

      if ($this->sma->send_email($to, 'Receipt from ' . $this->data['biller']->company, $receipt)) {
      $this->sma->send_json(array('msg' => $this->lang->line("email_sent")));
      } else {
      $this->sma->send_json(array('msg' => $this->lang->line("email_failed")));
      }
      }

     */

    public function email_receipt($sale_id = null) {
        $this->sma->checkPermissions('index');
        if ($this->input->post('id')) {
            $sale_id = $this->input->post('id');
        }
        if (!$sale_id) {
            die('No sale selected.');
        }
        if ($this->input->post('email')) {
            $to = $this->input->post('email');
        }
        $inv = $this->pos_model->getInvoiceByID($sale_id);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($sale_id, $inv->return_id);
        endif;

        $isGstSale = $this->site->isGstSale($sale_id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;

        $biller_id = $inv->biller_id;
        $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
        $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);

        if (!$to) {
            $to = $this->data['customer']->email;
        }
        if (!$to) {
            $this->sma->send_json(array('msg' => $this->lang->line("no_meil_provided")));
        }

        $this->data['customer']->email = $to;

        $attachment = $this->pdf($sale_id, null, 'S');

        $receipt = 'Please find attachment';


        if ($this->sma->send_email($to, 'Receipt from ' . $this->data['biller']->company, $receipt, null, null, $attachment)) {
            delete_files($attachment);
            $this->sma->send_json(array('msg' => $this->lang->line("email_sent")));
        } else {
            $this->sma->send_json(array('msg' => $this->lang->line("email_failed")));
        }
    }

    /* public function SendAutoEmail1(){
      $Alert_Products =  $this->count_alert_products();
      // $to = $this->session->userdata('email');
      $to = 'nita@simplysafe.in';
      $from = 'info@simplysafe.in';
      $subject = 'hello test email';
      if ($this->sma->send_email($to, 'Receipt from ' . $from, $subject)) {
      $this->sma->send_json(array('msg' => $this->lang->line("email_sent")));
      } else {
      $this->sma->send_json(array('msg' => $this->lang->line("email_failed")));
      }
      } */

    public function active() {
        $this->session->set_userdata('last_activity', now());
        if ((now() - $this->session->userdata('last_activity')) <= 20) {
            die('Successfully updated the last activity.');
        } else {
            die('Failed to update last activity.');
        }
    }

    public function add_payment($id = null) {
        $this->sma->checkPermissions('payments', true, 'sales');
        $this->load->helper('security');
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('amount-paid', lang("amount"), 'required');
        $this->form_validation->set_rules('paid_by', lang("paid_by"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->input->post('paid_by') == 'deposit') {
                $sale = $this->pos_model->getInvoiceByID($this->input->post('sale_id'));
                $customer_id = $sale->customer_id;
                if (!$this->site->check_customer_deposit($customer_id, $this->input->post('amount-paid'))) {
                    $this->session->set_flashdata('error', lang("amount_greater_than_deposit"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            } else {
                $customer_id = null;
            }
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $payment = array(
                'date' => $date,
                'sale_id' => $this->input->post('sale_id'),
                'reference_no' => $this->input->post('reference_no'),
                'amount' => $this->input->post('amount-paid'),
                'paid_by' => $this->input->post('paid_by'),
                'cheque_no' => $this->input->post('cheque_no'),
                'cc_no' => $this->input->post('paid_by') == 'gift_card' ? $this->input->post('gift_card_no') : $this->input->post('pcc_no'),
                'cc_holder' => $this->input->post('pcc_holder'),
                'cc_month' => $this->input->post('pcc_month'),
                'cc_year' => $this->input->post('pcc_year'),
                'cc_type' => $this->input->post('pcc_type'),
                'cc_cvv2' => $this->input->post('pcc_ccv'),
                'note' => $this->input->post('note'),
                'created_by' => $this->session->userdata('user_id'),
                'type' => 'received',
                'transaction_id' => $this->input->post('transaction_id'),
            );

            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $payment['attachment'] = $photo;
            }

            //$this->sma->print_arrays($payment);
        } elseif ($this->input->post('add_payment')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $msg = $this->pos_model->addPayment($payment, $customer_id)) {
            if ($msg) {
                if ($msg['status'] == 0) {
                    unset($msg['status']);
                    $error = '';
                    foreach ($msg as $m) {
                        if (is_array($m)) {
                            foreach ($m as $e) {
                                $error .= '<br>' . $e;
                            }
                        } else {
                            $error .= '<br>' . $m;
                        }
                    }
                    $this->session->set_flashdata('error', '<pre>' . $error . '</pre>');
                } else {
                    $this->session->set_flashdata('message', lang("payment_added"));
                }
            } else {
                $this->session->set_flashdata('error', lang("payment_failed"));
            }
            //redirect("pos/sales");
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $sale = $this->pos_model->getInvoiceByID($id);
            $this->data['inv'] = $sale;
            $this->data['payment_ref'] = $this->site->getReference('pay');
            $this->data['modal_js'] = $this->site->modal_js();

            $this->load->view($this->theme . 'pos/add_payment', $this->data);
        }
    }

    public function updates() {
        /*
          if (DEMO) {

          $this->session->set_flashdata('warning', lang('disabled_in_demo'));
          redirect($_SERVER["HTTP_REFERER"]);
          }
          if (!$this->Owner) {
          $this->session->set_flashdata('error', lang('access_denied'));
          redirect("welcome");
          }
          $this->form_validation->set_rules('purchase_code', lang("purchase_code"), 'required');
          $this->form_validation->set_rules('envato_username', lang("envato_username"), 'required');
          if ($this->form_validation->run() == TRUE) {
          $this->db->update('pos_settings', array('purchase_code' => $this->input->post('purchase_code', TRUE), 'envato_username' => $this->input->post('envato_username', TRUE)), array('pos_id' => 1));
          redirect('pos/updates');
          } else {
          $fields = array('version' => $this->pos_settings->version, 'code' => $this->pos_settings->purchase_code, 'username' => $this->pos_settings->envato_username, 'site' => base_url());
          $this->load->helper('update');
          $protocol = is_https() ? 'https://' : 'http://';
          $updates = get_remote_contents($protocol . 'tecdiary.com/api/v1/update/', $fields);
          $this->data['updates'] = json_decode($updates);
          $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('updates')));
          $meta = array('page_title' => lang('updates'), 'bc' => $bc);
          $this->page_construct('pos/updates', $meta, $this->data);
          } */
    }

    public function install_update($file, $m_version, $version) {
        /* if (DEMO) {
          $this->session->set_flashdata('warning', lang('disabled_in_demo'));
          redirect($_SERVER["HTTP_REFERER"]);
          }
          if (!$this->Owner) {
          $this->session->set_flashdata('error', lang('access_denied'));
          redirect("welcome");
          }
          $this->load->helper('update');
          save_remote_file($file . '.zip');
          $this->sma->unzip('./files/updates/' . $file . '.zip');
          if ($m_version) {
          $this->load->library('migration');
          if (!$this->migration->latest()) {
          $this->session->set_flashdata('error', $this->migration->error_string());
          redirect("pos/updates");
          }
          }
          $this->db->update('pos_settings', array('version' => $version, 'update' => 0), array('pos_id' => 1));
          unlink('./files/updates/' . $file . '.zip');
          $this->session->set_flashdata('success', lang('update_done'));
          redirect("pos/updates");
         *
         */
    }

    public function getProductByID($id = null) {
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $row = $this->site->getProductByID($id);
        echo json_encode($row);
    }

    public function instamojo_notify() {
        $payment_request_id = $this->input->get('payment_request_id');
        $payment_id = $this->input->get('payment_id');
        $this->load->library('instamojo');
        if (!empty($payment_request_id) && !empty($payment_id)):

            $Transaction = $this->pos_model->getInstamojoTransaction(array('request_id' => $payment_request_id));

            if ($Transaction->request_id == $payment_request_id) {
                $sid = $Transaction->order_id;
                $this->pos_model->updateInstamojoTransaction($payment_request_id, array('payment_id' => $payment_id));
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');

                $instamojo_credential = $payment_config['instamojo'];
                $api = new Instamojo($instamojo_credential['API_KEY'], $instamojo_credential['AUTH_TOKEN'], $instamojo_credential['API_URL']);
                $paymentDetail = $api->paymentDetail($payment_id);
                if (is_array($paymentDetail)):
                    $pay_res = serialize($paymentDetail);
                    $this->pos_model->updateInstamojoTransaction($payment_request_id, array('success_response' => $pay_res));
                    if (isset($paymentDetail["status"]) && in_array($paymentDetail["status"], array('Credit', 'credit', 'Completed'))):
                        $res = $this->pos_model->instomojoAfterSale($paymentDetail, $sid);
                        if ($res):
                            $this->session->set_flashdata('message', lang('payment_done'));
                            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                        else:

                        endif;

                    endif;
                endif;
            }
        endif;
    }

    public function ccavenue_init() {
        $this->load->library('ccavenue');
        $sale_id = $this->input->get('sid');
        if ((int) $sale_id > 0):
            $_req = $this->pos_model->getCcavenueTransaction(array('order_id' => $sale_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "CCavenue" . lang('payment_process_already_initiated'));
                redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale_id);
            endif;
            $sale = $this->site->getSaleByID($sale_id);
            if ($sale->id == $sale_id):
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');
                $ccavenue_credential = $payment_config['ccavenue'];
                $merchant_id = isset($ccavenue_credential['MERCHANT_ID']) && !empty($ccavenue_credential['MERCHANT_ID']) ? $ccavenue_credential['MERCHANT_ID'] : '';
                $access_code = isset($ccavenue_credential['ACCESS_CODE']) && !empty($ccavenue_credential['ACCESS_CODE']) ? $ccavenue_credential['ACCESS_CODE'] : '';
                $working_key = isset($ccavenue_credential['API_KEY']) && !empty($ccavenue_credential['API_KEY']) ? $ccavenue_credential['API_KEY'] : '';
                $API_URL = isset($ccavenue_credential['API_URL']) && !empty($ccavenue_credential['API_URL']) ? $ccavenue_credential['API_URL'] : '';
                $arr['tid'] = time();
                $arr['merchant_id'] = $merchant_id;
                $arr['order_id'] = $sale_id;
                $arr['amount'] = $sale->grand_total;
                $arr['currency'] = $this->Settings->default_currency;
                $arr['redirect_url'] = base_url('pos/ccavenue_notify');
                $arr['cancel_url'] = base_url('pos/ccavenue_cancel');
                $arr['billing_name'] = $customer->name;
                $arr['billing_tel'] = $customer->phone;
                $arr['billing_email'] = $customer->email;
                $arr['billing_city'] = $customer->city;
                $arr['billing_state'] = $customer->state;
                $arr['billing_zip'] = $customer->postal_code;
                $arr['merchant_param1'] = $sale->reference_no;
                try {
                    $api = new Ccavenue($merchant_id, $access_code, $working_key);
                    $encrypted_data = $api->getPostData($arr);
                    $this->data['merchant_id'] = $merchant_id;
                    $this->data['ccavenue_access_code'] = $access_code;
                    $this->data['ccavenue_working_key'] = $working_key;
                    $this->data['url'] = $API_URL;
                    $this->data['encrypted_data'] = $encrypted_data;

                    $this->pos_model->addCcavenueTransaction(array('sale_id' => $sale_id, 'req_data' => $arr));
                    $this->page_construct('pos/ccavenue', null, $this->data);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            endif;
        endif;
    }

    public function ccavenue_notify() {

        $this->load->library('ccavenue');
        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');
        $ccavenue_credential = $payment_config['ccavenue'];
        $merchant_id = isset($ccavenue_credential['MERCHANT_ID']) && !empty($ccavenue_credential['MERCHANT_ID']) ? $ccavenue_credential['MERCHANT_ID'] : '';
        $access_code = isset($ccavenue_credential['ACCESS_CODE']) && !empty($ccavenue_credential['ACCESS_CODE']) ? $ccavenue_credential['ACCESS_CODE'] : '';
        $working_key = isset($ccavenue_credential['API_KEY']) && !empty($ccavenue_credential['API_KEY']) ? $ccavenue_credential['API_KEY'] : '';
        $API_URL = isset($ccavenue_credential['API_URL']) && !empty($ccavenue_credential['API_URL']) ? $ccavenue_credential['API_URL'] : '';
        try {
            $api = new Ccavenue($merchant_id, $access_code, $working_key);
            $_data1 = isset($_POST["encResp"]) ? $_POST["encResp"] : '';
            $decrypted_data = $api->getResultData($_data1);
            if (is_array($decrypted_data)):
                $id = isset($decrypted_data["order_id"]) ? $decrypted_data["order_id"] : null;
                if ((int) $id > 0):
                    $_req = $this->pos_model->getCcavenueTransaction(array('order_id' => $id));
                    if (isset($_req->order_id)) {
                        $this->pos_model->updateCcavenueTransaction($id, array('response_data' => serialize($decrypted_data), 'update_time' => date('Y-m-d H:i:s')));
                    }
                endif;
                $o_status = isset($decrypted_data["order_status"]) ? $decrypted_data["order_status"] : null;
                switch ($o_status) {
                    case 'Success':
                        $msg = 'success';
                        $sid = $id;
                        $tracking_id = isset($decrypted_data["tracking_id"]) ? $decrypted_data["tracking_id"] : null;
                        $res = $this->pos_model->CcavenueAfterSale($decrypted_data, $sid);
                        if ($res):
                            $this->session->set_flashdata('message', lang('payment_done'));
                            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                        endif;
                        break;
                    case 'Failure':
                        $msg = 'The transaction has been declined.';
                        $this->session->set_flashdata('message', $msg);
                        redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                        break;
                    default:
                        break;
                }
            endif;
            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/");
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/");
        }
    }

    public function ccavenue_cancel() {
        $this->session->set_flashdata('error', lang('payment_not_done'));
        redirect($this->pos_settings->after_sale_page ? "pos" : "pos");
    }

    public function post_to_url($url, $data) {
        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= $key . '=' . $value . '&';
        }
        rtrim($fields, '&');
        $post = curl_init();
        curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_POST, count($data));
        curl_setopt($post, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($post, CURLOPT_TIMEOUT, 60);
        $result = curl_exec($post);
        curl_close($post);
        return $result;
    }

    public function paytm_init() {

        $sale_id = $this->input->get('sid');

        if ((int) $sale_id > 0):
            $_req = $this->pos_model->getPaytmTransaction(array('order_id' => $sale_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Paytm" . lang('payment_process_already_initiated'));
                redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale_id);
            endif;
            $sale = $this->site->getSaleByID($sale_id);
            if ($sale->id == $sale_id):
                $customer = $this->site->getCompanyByID($sale->customer_id);

                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');

                $paytm_credential = $payment_config['paytm'];

                $this->load->library('paytm', $paytm_credential);

                $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

                $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

                $API_URL = isset($paytm_credential['PAYTM_TXN_URL']) && !empty($paytm_credential['PAYTM_TXN_URL']) ? $paytm_credential['PAYTM_TXN_URL'] : '';

                $PAYTM_MERCHANT_WEBSITE = isset($paytm_credential['PAYTM_MERCHANT_WEBSITE']) && !empty($paytm_credential['PAYTM_MERCHANT_WEBSITE']) ? $paytm_credential['PAYTM_MERCHANT_WEBSITE'] : '';

                $arr['tid'] = time();

                $paramList["MID"] = $PAYTM_MERCHANT_MID;
                $paramList["ORDER_ID"] = $sale->id;
                $paramList["CUST_ID"] = $customer->id;
                $paramList["INDUSTRY_TYPE_ID"] = 'Retail';
                $paramList["CHANNEL_ID"] = 'WEB';
                $paramList["TXN_AMOUNT"] = $this->sma->formatDecimal($sale->grand_total);
                $paramList["WEBSITE"] = $PAYTM_MERCHANT_WEBSITE;
                $paramList["MSISDN"] = $customer->phone; //Mobile number of customer
                $paramList["EMAIL"] = $customer->email;  //Email ID of customer
                $paramList["VERIFIED_BY"] = "EMAIL"; //
                $paramList["IS_USER_VERIFIED"] = "YES"; //
                $paramList['CALLBACK_URL'] = base_url('pos/paytm_notify');

                try {

                    $checkSum = $this->paytm->getChecksumFromArray($paramList, $PAYTM_MERCHANT_KEY);

                    //$this->data['merchant_id']  = $merchant_id;
                    // $this->data['paytm_access_code'] = $access_code;
                    $this->data['paramList'] = $paramList;
                    $this->data['PAYTM_TXN_URL'] = $API_URL;
                    $this->data['CHECKSUMHASH'] = $checkSum;

                    $this->pos_model->addpaytmTransaction(array('sale_id' => $sale_id, 'req_data' => $paramList));

                    $this->load->view($this->theme . 'pos/paytm', $this->data);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            endif;
        endif;
    }

    public function paytm_notify() {

        $this->load->library('paytm');

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');

        $paytm_credential = $payment_config['paytm'];

        $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

        $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

        $API_URL = isset($paytm_credential['API_URL']) && !empty($paytm_credential['API_URL']) ? $paytm_credential['API_URL'] : '';

        $MID = $this->input->post('MID') ? $this->input->post('MID') : null;

        $ORDERID = $this->input->post('ORDERID') ? $this->input->post('ORDERID') : null;

        if ($ORDERID):
            $this->pos_model->updatePaytmTransaction($ORDERID, array('response_data' => serialize($_POST), 'update_time' => date('Y-m-d H:i:s')));
        endif;

        $STATUS = $this->input->post('STATUS') ? $this->input->post('STATUS') : null;
        $RESPMSG = $this->input->post('RESPMSG') ? $this->input->post('RESPMSG') : null;
        if ($_POST['STATUS'] != 'TXN_SUCCESS') {
            $this->session->set_flashdata('error', $_POST['RESPMSG']);
            if ((int) $ORDERID > 0):
                redirect("pos/view/" . $ORDERID);
            else:
                redirect("pos/");
            endif;
        }

        try {
            $api = new Paytm($paytm_credential);
            $requestParamList = array("MID" => $PAYTM_MERCHANT_MID, "ORDERID" => $ORDERID);

            $responseParamList = $api->getTxnStatus($requestParamList);

            $_ORDERID = $responseParamList['ORDERID'] ? $responseParamList['ORDERID'] : null;
            $_STATUS = $responseParamList['STATUS'] ? $responseParamList['STATUS'] : null;
            $_RESPMSG = $responseParamList['RESPMSG'] ? $responseParamList['RESPMSG'] : null;
            $_TXNID = $responseParamList['TXNID'] ? $responseParamList['TXNID'] : null;
            if ($_ORDERID == $ORDERID && $_STATUS == 'TXN_SUCCESS'):

                $msg = 'success';
                $sid = $ORDERID;
                $tracking_id = $_TXNID;
                $res = $this->pos_model->PaytmAfterSale($responseParamList, $sid);
                if ($res):
                    $this->session->set_flashdata('message', lang('payment_done'));
                    redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                endif;

                $this->session->set_flashdata('message', $_RESPMSG);
                redirect("pos/view/" . $ORDERID);
            else:
                $this->session->set_flashdata('error', $_RESPMSG);
                redirect("pos/view/" . $ORDERID);
            endif;
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/");
        }
    }

    public function payswiff_init() {
        $sale_id = $this->input->get('sid');

        if ((int) $sale_id > 0):
            $_req = $this->pos_model->getPayswiffTransaction(array('order_id' => $sale_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Payswiff" . lang('payment_process_already_initiated'));
                redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale_id);
            endif;
            $sale = $this->site->getSaleByID($sale_id);
            if ($sale->id == $sale_id):
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $ci = get_instance();
                /*    $ci->config->load('payment_gateways', true);

                  $payment_config = $ci->config->item('payment_gateways');
                  $paynear_credential = $payment_config['paynear'];

                  $PAYNEAR_SECRET_KEY = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
                  $PAYNEAR_MERCHANT_ID = isset($paynear_credential['PAYNEAR_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_MERCHANT_ID'] : '';
                  $testMode = isset($paynear_credential['PAYNEAR_SANDBOX']) && !empty($paynear_credential['PAYNEAR_SANDBOX']) ? true : false;

                  $PAYNEAR_APP_MERCHANT_ID = isset($paynear_credential['PAYNEAR_APP_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_APP_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_APP_MERCHANT_ID'] : '';
                 */

                $arr['tid'] = time();

                $paramList["referenceNo"] = $sale->id;
                $paramList["outletId"] = '0';
                //$paramList["apiVersion"] = '2.0.1';
                $paramList["currency"] = $this->Settings->default_currency;
                $paramList["currencyCode"] = 'INR';
                $paramList["locale"] = 'EN-US';
                $paramList["description"] = $sale->reference_no;
                $paramList["amount"] = $this->sma->formatDecimal($sale->grand_total);
                $paramList["channel"] = '3';
                $paramList["responseURL"] = base_url('pos/payswiff_notify'); //Email ID of customer

                $paramList["billingContactName"] = str_replace("-", '', $customer->name); //
                $paramList["billingAddress"] = $customer->address; //
                $paramList["billingCity"] = $customer->city; //
                $paramList["billingState"] = $customer->state; //
                $paramList["billingPostalCode"] = $customer->postal_code; //
                $paramList["billingCountry"] = 'IND'; //
                $paramList["billingEmail"] = $customer->email; //
                $paramList["billingPhone"] = $customer->phone; //
                $paramList["shippingContactName"] = '';
                $paramList["shippingAddress"] = '';
                $paramList["shippingCity"] = '';
                $paramList["shippingState"] = '';
                $paramList["shippingPostalCode"] = '';
                $paramList["shippingCountry"] = '';
                $paramList["shippingEmail"] = '';
                $paramList["shippingPhone"] = '';

                $config = $ci->config;
                /*
                  $this->load->library('apicrypter');
                  $ApiCrypter = new ApiCrypter();
                  $PAYNEAR_APP_SECRET_KEY = isset($paynear_credential['PAYNEAR_APP_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_APP_SECRET_KEY']) ? $paynear_credential['PAYNEAR_APP_SECRET_KEY'] : '';

                  if ($PAYNEAR_APP_SECRET_KEY):
                  $app_paynear_key = $ApiCrypter->encrypt($PAYNEAR_APP_SECRET_KEY);
                  endif;
                 */
                $MERCHANT_PHONE = isset($config->config['merchant_phone']) ? $config->config['merchant_phone'] : '';
                $APIKEY = "435DSFSDFDSF743500909809DFSFJKJ234324534";

                $paramList["api_url"] = base_url('payswiff/v2');
                $paramList["secret_token"] = md5($MERCHANT_PHONE . $APIKEY . $sale->id . time());
                // $paramList["PAYNEAR_MERCHANT_ID"] = $PAYNEAR_MERCHANT_ID;
                //  $mobile_app_paynear_type = $this->input->get('paynear_type');

                $this->pos_model->addPayswiffTransaction(array('sale_id' => $sale_id, 'req_data' => $paramList, 'secret_token' => $paramList["secret_token"]));
                ?>
<script>
//if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {

window.MyHandler.InitiateCCPaymentAndroid('<?php echo json_encode($paramList) ?>');
// } else {

<?php
// redirect("pos/view/$sale_id");
                ?>
// }
</script>
<?php
            /*
              exit;


              $api = new PaynearEpay($PAYNEAR_MERCHANT_ID, $PAYNEAR_SECRET_KEY, $testMode);
              try {
              $paramList1 = $paramList;
              $paramList1['currency'] = $this->Settings->default_currency;
              $this->pos_model->addpaynearTransaction(array('sale_id' => $sale_id, 'req_data' => $paramList1));
              $api->initiatePayment($paramList);
              } catch (Exception $e) {

              $this->session->set_flashdata('error', $e->getMessage());
              redirect("pos");
              } */
            endif;
        endif;
    }

    public function payswiff_notify() {

        $this->load->library('paynearepay');
        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');
        $paynear_credential = $payment_config['paynear'];

        $PAYNEAR_SECRET_KEY = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
        $PAYNEAR_MERCHANT_ID = isset($paynear_credential['PAYNEAR_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_MERCHANT_ID'] : '';
        $testMode = isset($paynear_credential['PAYNEAR_SANDBOX']) && $paynear_credential['PAYNEAR_SANDBOX'] == 1 ? true : false;
        $api = new PaynearEpay($PAYNEAR_MERCHANT_ID, $PAYNEAR_SECRET_KEY, $testMode);
        try {
            $result = $api->getPaymentResponse($_POST);

            $ORDERID = $this->input->post('orderRefNo') ? $this->input->post('orderRefNo') : null;
            $_req = $this->pos_model->getPaynearTransaction(array('order_id' => $ORDERID));
            if (!isset($_req->order_id)) {
                $this->session->set_flashdata('error', "Paynear details not  found agaienst  Sale id  " . $ORDERID);
                redirect("pos");
            }

            if ($ORDERID):
                $this->pos_model->updatePaynearTransaction($ORDERID, array('response_data' => serialize($result), 'update_time' => date('Y-m-d H:i:s')));
            endif;

            $params['orderRefNo'] = $result['orderRefNo'];
            $params['paymentId'] = $result['paymentId'];
            $params['transactionId'] = $result['transactionId'];
            $params['amount'] = $result['amount'];
            if ($result['responseCode'] == '000' && $result['responseMessage'] == 'Success'):
                $_result = $api->getTransactionStatus($params);
                if ($_result['responseCode'] == '000' && $_result['responseMessage'] == 'Success'):
                    $sid = $ORDERID;
                    $res = $this->pos_model->PaynearAfterSale($_result, $sid);
                    if ($res):
                        $this->session->set_flashdata('message', lang('payment_done'));
                        redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                    endif;
                endif;
            endif;
            $this->session->set_flashdata('error', $result['responseMessage']);
            redirect("pos");
        } catch (Exception $e) {
            $this->session->set_flashdata('error', $e->getMessage());
            redirect("pos");
        }
        $this->session->set_flashdata('error', 'Something went wrong ,please try again ');
        redirect("pos");
    }

    public function paynear_init() {

        $this->load->library('paynearepay');

        $sale_id = $this->input->get('sid');
        $mobile_app = $this->input->get('mobile_app');

        $_mobile_app = md5('MPA' . $sale_id);
        $valid_app_call = '';
        if (!empty($mobile_app) && $_mobile_app == $mobile_app) {
            $valid_app_call = '1';
        }

        if ((int) $sale_id > 0):
            $_req = $this->pos_model->getPaynearTransaction(array('order_id' => $sale_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Paynear" . lang('payment_process_already_initiated'));
                redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale_id);
            endif;
            $sale = $this->site->getSaleByID($sale_id);
            if ($sale->id == $sale_id):
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');
                $paynear_credential = $payment_config['paynear'];

                $PAYNEAR_SECRET_KEY = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
                $PAYNEAR_MERCHANT_ID = isset($paynear_credential['PAYNEAR_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_MERCHANT_ID'] : '';
                $testMode = isset($paynear_credential['PAYNEAR_SANDBOX']) && !empty($paynear_credential['PAYNEAR_SANDBOX']) ? true : false;

                $PAYNEAR_APP_MERCHANT_ID = isset($paynear_credential['PAYNEAR_APP_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_APP_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_APP_MERCHANT_ID'] : '';
                $arr['tid'] = time();

                $paramList["referenceNo"] = $sale->id;
                $paramList["outletId"] = '0';
                $paramList["apiVersion"] = '2.0.1';
                $paramList["currencyCode"] = 'INR';
                $paramList["locale"] = 'EN-US';
                $paramList["description"] = $sale->reference_no;
                $paramList["amount"] = $this->sma->formatDecimal($sale->grand_total);
                $paramList["channel"] = '3';
                $paramList["responseURL"] = base_url('pos/paynear_notify'); //Email ID of customer

                $paramList["billingContactName"] = str_replace("-", '', $customer->name); //
                $paramList["billingAddress"] = $customer->address; //
                $paramList["billingCity"] = $customer->city; //
                $paramList["billingState"] = $customer->state; //
                $paramList["billingPostalCode"] = $customer->postal_code; //
                $paramList["billingCountry"] = 'IND'; //
                $paramList["billingEmail"] = $customer->email; //
                $paramList["billingPhone"] = $customer->phone; //
                $paramList["shippingContactName"] = '';
                $paramList["shippingAddress"] = '';
                $paramList["shippingCity"] = '';
                $paramList["shippingState"] = '';
                $paramList["shippingPostalCode"] = ''; //
                $paramList["shippingCountry"] = '';
                $paramList["shippingEmail"] = '';
                $paramList["shippingPhone"] = '';

                if ($valid_app_call == '1'):
                    $this->load->library('apicrypter');
                    $config = $ci->config;
                    $ApiCrypter = new ApiCrypter();
                    $PAYNEAR_APP_SECRET_KEY = isset($paynear_credential['PAYNEAR_APP_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_APP_SECRET_KEY']) ? $paynear_credential['PAYNEAR_APP_SECRET_KEY'] : '';

                    if ($PAYNEAR_APP_SECRET_KEY):
                        $app_paynear_key = $ApiCrypter->encrypt($PAYNEAR_APP_SECRET_KEY);
                    endif;

                    $MERCHANT_PHONE = isset($config->config['merchant_phone']) ? $config->config['merchant_phone'] : '';
                    $APIKEY = "435DSFSDFDSF743500909809DFSFJKJ234324534";

                    $paramList["api_url"] = base_url('paynear/v2');
                    $paramList["secret_token"] = md5($MERCHANT_PHONE . $APIKEY . $sale->id . time());
                    $paramList["PAYNEAR_MERCHANT_ID"] = $PAYNEAR_MERCHANT_ID;
                    $mobile_app_paynear_type = $this->input->get('paynear_type');
                    ?>
<script>
window.MyHandler.setTransactindata('<?php echo $PAYNEAR_APP_MERCHANT_ID; ?>',
    '<?php echo $paramList["referenceNo"]; ?>', '<?php echo $paramList["amount"]; ?>',
    '<?php echo $paramList["api_url"]; ?>', '<?php echo $paramList["secret_token"]; ?>',
    '<?php echo $this->Settings->default_currency; ?>', '<?php echo $mobile_app_paynear_type; ?>',
    '<?php echo $app_paynear_key ?>');
</script>
<?php
                    $this->pos_model->addpaynearTransaction(array('sale_id' => $sale_id, 'req_data' => $paramList, 'secret_token' => $paramList["secret_token"]));

                    exit;
                endif;

                $api = new PaynearEpay($PAYNEAR_MERCHANT_ID, $PAYNEAR_SECRET_KEY, $testMode);
                try {
                    $paramList1 = $paramList;
                    $paramList1['currency'] = $this->Settings->default_currency;
                    $this->pos_model->addpaynearTransaction(array('sale_id' => $sale_id, 'req_data' => $paramList1));
                    $api->initiatePayment($paramList);
                } catch (Exception $e) {

                    $this->session->set_flashdata('error', $e->getMessage());
                    redirect("pos");
                }
            endif;
        endif;
    }

    public function paynear_notify() {

        $this->load->library('paynearepay');
        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');
        $paynear_credential = $payment_config['paynear'];

        $PAYNEAR_SECRET_KEY = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
        $PAYNEAR_MERCHANT_ID = isset($paynear_credential['PAYNEAR_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_MERCHANT_ID'] : '';
        $testMode = isset($paynear_credential['PAYNEAR_SANDBOX']) && $paynear_credential['PAYNEAR_SANDBOX'] == 1 ? true : false;
        $api = new PaynearEpay($PAYNEAR_MERCHANT_ID, $PAYNEAR_SECRET_KEY, $testMode);
        try {
            $result = $api->getPaymentResponse($_POST);

            $ORDERID = $this->input->post('orderRefNo') ? $this->input->post('orderRefNo') : null;
            $_req = $this->pos_model->getPaynearTransaction(array('order_id' => $ORDERID));
            if (!isset($_req->order_id)) {
                $this->session->set_flashdata('error', "Paynear details not  found agaienst  Sale id  " . $ORDERID);
                redirect("pos");
            }

            if ($ORDERID):
                $this->pos_model->updatePaynearTransaction($ORDERID, array('response_data' => serialize($result), 'update_time' => date('Y-m-d H:i:s')));
            endif;

            $params['orderRefNo'] = $result['orderRefNo'];
            $params['paymentId'] = $result['paymentId'];
            $params['transactionId'] = $result['transactionId'];
            $params['amount'] = $result['amount'];
            if ($result['responseCode'] == '000' && $result['responseMessage'] == 'Success'):
                $_result = $api->getTransactionStatus($params);
                if ($_result['responseCode'] == '000' && $_result['responseMessage'] == 'Success'):
                    $sid = $ORDERID;
                    $res = $this->pos_model->PaynearAfterSale($_result, $sid);
                    if ($res):
                        $this->session->set_flashdata('message', lang('payment_done'));
                        redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                    endif;
                endif;
            endif;
            $this->session->set_flashdata('error', $result['responseMessage']);
            redirect("pos");
        } catch (Exception $e) {
            $this->session->set_flashdata('error', $e->getMessage());
            redirect("pos");
        }
        $this->session->set_flashdata('error', 'Something went wrong ,please try again ');
        redirect("pos");
    }

    public function payumoney_init() {
        $this->load->library('payumoney');
        $sale_id = $this->input->get('sid');
        if ((int) $sale_id > 0):
            $_req = $this->pos_model->getPayumoneyTransaction(array('order_id' => $sale_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Payumoney " . lang('payment_process_already_initiated'));
                redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale_id);
            endif;
            $sale = $this->site->getSaleByID($sale_id);
            if ($sale->id == $sale_id):
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');

                $payumoney_credential = $payment_config['payumoney'];

                $PAYUMONEY_MID = isset($payumoney_credential['PAYUMONEY_MID']) && !empty($payumoney_credential['PAYUMONEY_MID']) ? $payumoney_credential['PAYUMONEY_MID'] : '';
                $PAYUMONEY_KEY = isset($payumoney_credential['PAYUMONEY_KEY']) && !empty($payumoney_credential['PAYUMONEY_KEY']) ? $payumoney_credential['PAYUMONEY_KEY'] : '';
                $PAYUMONEY_SALT = isset($payumoney_credential['PAYUMONEY_SALT']) && !empty($payumoney_credential['PAYUMONEY_SALT']) ? $payumoney_credential['PAYUMONEY_SALT'] : '';
                $PAYUMONEY_AUTH_HEADER = isset($payumoney_credential['PAYUMONEY_AUTH_HEADER']) && !empty($payumoney_credential['PAYUMONEY_AUTH_HEADER']) ? $payumoney_credential['PAYUMONEY_AUTH_HEADER'] : '';
                $posted = array();

                $posted['key'] = $PAYUMONEY_KEY;
                $posted['txnid'] = $sale->id;
                $posted['amount'] = $this->sma->formatDecimal($sale->grand_total);
                $posted['firstname'] = str_replace(array(' ', '-'), '', $customer->name);
                $posted['email'] = $customer->email;
                $posted['phone'] = $customer->phone;
                $posted['lastname'] = '';
                $posted['address1'] = $customer->address;
                $posted['address2'] = '';
                $posted['city'] = $customer->city;
                $posted['state'] = $customer->state;
                $posted['country'] = 'IND';
                $posted['zipcode'] = $customer->postal_code;
                $posted['productinfo'] = 'POS ORDER ' . $sale->reference_no;
                $posted['udf1'] = $sale_id;
                $posted['udf2'] = '';
                $posted['udf3'] = '';
                $posted['udf4'] = '';
                $posted['udf5'] = '';
                $posted['pg'] = '';
                $posted['furl'] = base_url('pos/payumoney_cancel');
                $posted['surl'] = base_url('pos/payumoney_notify');
                $posted['service_provider'] = 'payu_paisa';

                try {
                    $api = new Payumoney($PAYUMONEY_MID, $PAYUMONEY_KEY, $PAYUMONEY_SALT, $PAYUMONEY_AUTH_HEADER);
                    $encrypted_data = $api->calculate_hash_before_transaction($posted);
                    $posted['hash'] = $encrypted_data;

                    $this->pos_model->addPayumoneyTransaction(array('sale_id' => $sale_id, 'req_data' => $posted));
                    $this->data['posted'] = $posted;
                    $this->data['apiAction'] = $api->getApiUrl();

                    $this->page_construct('pos/payumoney', null, $this->data);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            endif;
        endif;
    }

    public function payumoney_notify() {
        $this->load->library('payumoney');
        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');
        $payumoney_credential = $payment_config['payumoney'];

        $PAYUMONEY_MID = isset($payumoney_credential['PAYUMONEY_MID']) && !empty($payumoney_credential['PAYUMONEY_MID']) ? $payumoney_credential['PAYUMONEY_MID'] : '';
        $PAYUMONEY_KEY = isset($payumoney_credential['PAYUMONEY_KEY']) && !empty($payumoney_credential['PAYUMONEY_KEY']) ? $payumoney_credential['PAYUMONEY_KEY'] : '';
        $PAYUMONEY_SALT = isset($payumoney_credential['PAYUMONEY_SALT']) && !empty($payumoney_credential['PAYUMONEY_SALT']) ? $payumoney_credential['PAYUMONEY_SALT'] : '';
        $PAYUMONEY_AUTH_HEADER = isset($payumoney_credential['PAYUMONEY_AUTH_HEADER']) && !empty($payumoney_credential['PAYUMONEY_AUTH_HEADER']) ? $payumoney_credential['PAYUMONEY_AUTH_HEADER'] : '';
        try {
            $posted = $_POST;
            $api = new Payumoney($PAYUMONEY_MID, $PAYUMONEY_KEY, $PAYUMONEY_SALT, $PAYUMONEY_AUTH_HEADER);
            $res = $api->check_hash_after_transaction($PAYUMONEY_SALT, $posted);
            $sid = $ORDERID = $this->input->post('udf1') ? $this->input->post('udf1') : null;
            $_req = $this->pos_model->getPayumoneyTransaction(array('order_id' => $ORDERID));
            if (isset($_req->order_id) && $ORDERID == $_req->order_id) {
                $this->pos_model->updatePayumoneyTransaction($ORDERID, array('response_data' => serialize($posted), 'update_time' => date('Y-m-d H:i:s')));
            }

            if ($res === true) {

                $validateOrder = $api->validateOrder($ORDERID);

                if (empty($validateOrder)) {
                    $this->session->set_flashdata('error', "Order is not validated  successfully");
                    redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                }
                $jsonObj = json_decode($validateOrder);

                if (isset($jsonObj->result[0]->paymentId) && $posted['payuMoneyId'] == $jsonObj->result[0]->paymentId && $posted['status'] == 'success'):
                    $res1 = $this->pos_model->PayumoneyAfterSale($posted, $ORDERID);
                    /*    */
                    if ($res1):
                        $this->session->set_flashdata('message', lang('payment_done'));
                        redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
                    endif;
                    echo "IN";
                endif;
            }
            $this->session->set_flashdata('error', lang('payment_not_done'));
            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $this->session->set_flashdata('error', lang('payment_not_done'));
        redirect($this->pos_settings->after_sale_page ? "pos" : "pos");
    }

    public function payumoney_cancel() {
        $this->load->library('payumoney');
        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');
        $payumoney_credential = $payment_config['payumoney'];

        $PAYUMONEY_MID = isset($payumoney_credential['PAYUMONEY_MID']) && !empty($payumoney_credential['PAYUMONEY_MID']) ? $payumoney_credential['PAYUMONEY_MID'] : '';
        $PAYUMONEY_KEY = isset($payumoney_credential['PAYUMONEY_KEY']) && !empty($payumoney_credential['PAYUMONEY_KEY']) ? $payumoney_credential['PAYUMONEY_KEY'] : '';
        $PAYUMONEY_SALT = isset($payumoney_credential['PAYUMONEY_SALT']) && !empty($payumoney_credential['PAYUMONEY_SALT']) ? $payumoney_credential['PAYUMONEY_SALT'] : '';
        $PAYUMONEY_AUTH_HEADER = isset($payumoney_credential['PAYUMONEY_AUTH_HEADER']) && !empty($payumoney_credential['PAYUMONEY_AUTH_HEADER']) ? $payumoney_credential['PAYUMONEY_AUTH_HEADER'] : '';
        try {
            $posted = $_POST;
            $api = new Payumoney($PAYUMONEY_MID, $PAYUMONEY_KEY, $PAYUMONEY_SALT, $PAYUMONEY_AUTH_HEADER);
            $res = $api->check_hash_after_transaction($PAYUMONEY_SALT, $posted);

            $ORDERID = $this->input->post('udf1') ? $this->input->post('udf1') : null;
            $_req = $this->pos_model->getPayumoneyTransaction(array('order_id' => $ORDERID));

            if (isset($_req->order_id) && $ORDERID == $_req->order_id) {
                $this->pos_model->updatePayumoneyTransaction($ORDERID, array('response_data' => serialize($posted), 'update_time' => date('Y-m-d H:i:s')));
            }

            $this->session->set_flashdata('error', lang('payment_not_done') . $_POST['unmappedstatus']);
            redirect($this->pos_settings->after_sale_page ? "pos" : "pos");
        } catch (Exception $e) {

            $this->session->set_flashdata('error', lang('payment_not_done') . $e->getMessage());
        }

        $this->session->set_flashdata('error', lang('payment_not_done'));
        redirect($this->pos_settings->after_sale_page ? "pos" : "pos");
    }

    public function featuerdProducts() {
        $this->sma->checkPermissions('index');

        if ($this->input->get('per_page') == 'n') {
            $page = 0;
        } else {
            $page = $this->input->get('per_page');
        }

        $this->load->library("pagination");

        $config = array();
        $config["base_url"] = base_url() . "pos/featuerdProducts";
        $config["total_rows"] = $this->pos_model->featuerd_products_count();
        $config["per_page"] = $this->pos_settings->pro_limit;
        $config['prev_link'] = false;
        $config['next_link'] = false;
        $config['display_pages'] = false;
        $config['first_link'] = false;
        $config['last_link'] = false;

        $this->pagination->initialize($config);

        $products = $this->pos_model->fetch_featuerd_products($config["per_page"], $page);
        $pro = 1;
        $prcount = $config["total_rows"];
        $i = 1;

        $prods = '<div>';
        if (!empty($products)) {
            $i = 0;
            foreach ($products as $product) {

                $count = $product->id;
                if ($count < 10) {
                    $count = "0" . ($count / 100) * 100;
                }
                if (isset($product->category_id) && $product->category_id < 10) {
                    $product->category_id = "0" . ($product->category_id / 100) * 100;
                }
                if (file_exists('assets/mdata/'.$this->Customer_assets.'/uploads/' . $product->image)) {
                    if ($product->image != "") {
                        $imgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/' . $product->image;
                    } else {
                        $imgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/no_image.png';
                    }
                } else {
                    $imgsrc = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/no_image.png';
                }

                $prods .= "<button id=\"product-" . $product->category_id . $count . "\" type=\"button\" value='" . $product->code . "' title=\"" . $product->name . "\" class=\"btn-prni btn-" . $this->pos_settings->product_button_color . " product pos-tip\" data-container=\"body\"><img src=\"" . base_url() . $imgsrc . "\" alt=\"" . $product->name . "\" style='width:" . $this->Settings->twidth . "px;height:" . $this->Settings->theight . "px;' class='img-rounded' /><span>" . character_limiter($product->name, 20) . ($this->pos_settings->pos_price_display ? '<br/>' . $this->sma->formatMoney($product->price) : '') . "</span></button>";

                $pro++;
                /* if (++$i == 21) {
                  break;
                  } */

                //$i==21;
                //break 21;
            }
        }
        $prods .= "</div>";

        if ($this->input->get('per_page')) {
            $tcp = $this->pos_model->featuerd_products_count();
            $this->sma->send_json(array('products' => $prods, 'tcp' => $tcp));

            //echo $prods;
        } else {
            return $prods;
        }
    }

    //split order
    public function split_order_save($sid = null) {

        $this->sma->checkPermissions();

        if (!$this->pos_settings->default_biller || !$this->pos_settings->default_customer || !$this->pos_settings->default_category) {
            $this->session->set_flashdata('warning', lang('please_update_settings'));
            redirect('pos/settings');
        }
        if ($register = $this->pos_model->registerData($this->session->userdata('user_id'))) {
            $register_data = array('register_id' => $register->id, 'cash_in_hand' => $register->cash_in_hand, 'register_open_time' => $register->date);
            $this->session->set_userdata($register_data);
        } else {
            $this->session->set_flashdata('error', lang('register_not_open'));
            redirect('pos/open_register');
        }

        $this->data['sid'] = $this->input->get('suspend_id') ? $this->input->get('suspend_id') : $sid;
        $did = $this->input->post('delete_id') ? $this->input->post('delete_id') : null;
        $suspend = $this->input->post('suspend') ? true : false;
        $count = $this->input->post('count') ? $this->input->post('count') : null;

        //validate form input
        $this->form_validation->set_rules('customer', $this->lang->line("customer"), 'trim|required');
        $this->form_validation->set_rules('warehouse', $this->lang->line("warehouse"), 'required');
        $this->form_validation->set_rules('biller', $this->lang->line("biller"), 'required');
        $Settings = $this->Settings; //$this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->form_validation->set_rules('patient_name', 'Patient Name', 'trim');
            $this->form_validation->set_rules('doctor_name', 'Doctor Name', 'trim');
        }

        if ($this->form_validation->run() == true) {

            $date = date('Y-m-d H:i:s');
            $warehouse_id = $this->input->post('warehouse');
            $customer_id = $this->input->post('customer');
            $biller_id = $this->input->post('biller');
            $total_items = $this->input->post('total_items');
            $sale_status = 'completed';
            $payment_status = 'due';
            $payment_term = 0;
            $due_date = date('Y-m-d', strtotime('+' . $payment_term . ' days'));
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $customer_details = $this->site->getCompanyByID($customer_id);
            $customer = $customer_details->company != '-' ? $customer_details->company : $customer_details->name;
            $biller_details = $this->site->getCompanyByID($biller_id);
            $biller = $biller_details->company != '-' ? $biller_details->company : $biller_details->name;
            $note = $this->sma->clear_tags($this->input->post('pos_note'));
            $staff_note = $this->sma->clear_tags($this->input->post('staff_note'));

            $offer_category = $this->input->post('offer_category');
            $offer_description = $this->input->post('offer_description');

            $reference = $this->site->getReference('pos');

            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                $item_id = $_POST['product_id'][$r];
                $hsn_code = $_POST['hsn_code'][$r];
                $hsn_code = ($hsn_code == 'null') ? '' : $hsn_code;
                $item_type = $_POST['product_type'][$r];
                $item_code = $_POST['product_code'][$r];
                $item_name = $_POST['product_name'][$r];
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' ? $_POST['product_option'][$r] : null;
                $real_unit_price = $this->sma->formatDecimal($_POST['real_unit_price'][$r]);
                $unit_price = $this->sma->formatDecimal($_POST['unit_price'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];
                $item_serial = isset($_POST['serial'][$r]) ? $_POST['serial'][$r] : '';
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                $item_unit = $_POST['product_unit'][$r];
                $item_quantity = $_POST['product_base_quantity'][$r];
                $item_note = $_POST['item_note'][$r];

                if (isset($item_code) && isset($real_unit_price) && isset($unit_price) && isset($item_quantity)) {
                    $product_details = $item_type != 'manual' ? $this->pos_model->getProductByCode($item_code) : null;
                    // $unit_price = $real_unit_price;
                    $pr_discount = 0;

                    if (isset($item_discount)) {
                        $discount = $item_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_price)) * (Float) ($pds[0])) / 100), 4);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount);
                        }
                    }

                    $unit_price = $this->sma->formatDecimal($unit_price - $pr_discount);
                    $item_net_price = $unit_price;
                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $item_unit_quantity);
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $item_tax = 0;
                    $tax = "";

                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;
                            }
                        } elseif ($tax_details->type == 2) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;
                            }

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal(($item_tax * $item_unit_quantity), 4);
                    }

                    $product_tax += $pr_item_tax;
                    $subtotal = (($item_net_price * $item_unit_quantity) + $pr_item_tax);
                    $unit = $this->site->getUnitByID($item_unit);
                    $mrp = isset($product_details->mrp) && !empty($product_details->mrp) ? $product_details->mrp : $item_net_price;
                    $products[] = array(
                        'product_id' => $item_id,
                        'product_code' => $item_code,
                        'product_name' => $item_name,
                        'product_type' => $item_type,
                        'option_id' => $item_option,
                        'net_unit_price' => $item_net_price,
                        'unit_price' => $this->sma->formatDecimal($item_net_price + $item_tax),
                        'quantity' => $item_quantity,
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit ? $unit->code : null,
                        'unit_quantity' => $item_unit_quantity,
                        'warehouse_id' => $warehouse_id,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'discount' => $item_discount,
                        'item_discount' => $pr_item_discount,
                        'subtotal' => $this->sma->formatDecimal($subtotal),
                        'serial_no' => $item_serial,
                        'real_unit_price' => $real_unit_price,
                        'mrp' => $mrp,
                        'hsn_code' => $hsn_code,
                        'note' => $item_note,
                    );

                    $total += $this->sma->formatDecimal(($item_net_price * $item_unit_quantity), 4);
                }
            }

            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } elseif ($this->pos_settings->item_order == 1) {
                //krsort($products);
                $products;
            }

            if ($this->input->post('discount')) {
                $order_discount_id = $this->input->post('discount');
                $opos = strpos($order_discount_id, $percentage);
                if ($opos !== false) {
                    $ods = explode("%", $order_discount_id);
                    $order_discount = $this->sma->formatDecimal(((($total + $product_tax) * (Float) ($ods[0])) / 100), 4);
                } else {
                    $order_discount = $this->sma->formatDecimal($order_discount_id);
                }
            } else {
                $order_discount_id = null;
            }
            $total_discount = $this->sma->formatDecimal($order_discount + $product_discount);

            if ($this->Settings->tax2) {
                $order_tax_id = $this->input->post('order_tax');
                if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                    if ($order_tax_details->type == 2) {
                        $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
                    }
                    if ($order_tax_details->type == 1) {
                        $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
                    }
                }
            } else {
                $order_tax_id = null;
            }

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 4);
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping) - $order_discount), 4);
            $rounding = 0;
            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }
            $data = array('date' => $date,
                'reference_no' => $reference,
                'customer_id' => $customer_id,
                'customer' => $customer,
                'biller_id' => $biller_id,
                'biller' => $biller,
                'warehouse_id' => $warehouse_id,
                'note' => $note,
                'staff_note' => $staff_note,
                'total' => $total,
                'product_discount' => $product_discount,
                'order_discount_id' => $order_discount_id,
                'order_discount' => $order_discount,
                'total_discount' => $total_discount,
                'product_tax' => $product_tax,
                'order_tax_id' => $order_tax_id,
                'order_tax' => $order_tax,
                'total_tax' => $total_tax,
                'shipping' => $this->sma->formatDecimal($shipping),
                'grand_total' => $grand_total,
                'total_items' => $total_items,
                'sale_status' => $sale_status,
                'payment_status' => $payment_status,
                'payment_term' => $payment_term,
                'rounding' => $rounding,
                'pos' => 1,
                'paid' => $this->input->post('amount-paid') ? $this->input->post('amount-paid') : 0,
                'created_by' => $this->session->userdata('user_id'),
                'offer_category' => $offer_category ? $offer_category : NULL,
                'offer_description' => $offer_description ? $offer_description : NULL,
            );

            if (!$suspend) {
                $p = isset($_POST['amount']) ? sizeof($_POST['amount']) : 0;
                $paid = 0;
                for ($r = 0; $r < $p; $r++) {
                    if (isset($_POST['amount'][$r]) && !empty($_POST['amount'][$r]) && isset($_POST['paid_by'][$r]) && !empty($_POST['paid_by'][$r])) {
                        $amount = $this->sma->formatDecimal($_POST['balance_amount'][$r] > 0 ? $_POST['amount'][$r] - $_POST['balance_amount'][$r] : $_POST['amount'][$r]);
                        if ($_POST['paid_by'][$r] == 'deposit') {
                            if (!$this->site->check_customer_deposit($customer_id, $amount)) {
                                $this->session->set_flashdata('error', lang("amount_greater_than_deposit"));
                                redirect($_SERVER["HTTP_REFERER"]);
                            }
                        }
                        if ($_POST['paid_by'][$r] == 'gift_card') {
                            $gc = $this->site->getGiftCardByNO($_POST['paying_gift_card_no'][$r]);
                            $amount_paying = $_POST['amount'][$r] >= $gc->balance ? $gc->balance : $_POST['amount'][$r];
                            $gc_balance = $gc->balance - $amount_paying;
                            $payment[] = array(
                                'date' => $date,
                                // 'reference_no' => $this->site->getReference('pay'),
                                'amount' => $amount,
                                'paid_by' => $_POST['paid_by'][$r],
                                'cheque_no' => $_POST['cheque_no'][$r],
                                'cc_no' => $_POST['paying_gift_card_no'][$r],
                                'cc_holder' => $_POST['cc_holder'][$r],
                                'cc_month' => $_POST['cc_month'][$r],
                                'cc_year' => $_POST['cc_year'][$r],
                                'cc_type' => $_POST['cc_type'][$r],
                                'cc_cvv2' => $_POST['cc_cvv2'][$r],
                                'created_by' => $this->session->userdata('user_id'),
                                'type' => 'received',
                                'note' => $_POST['payment_note'][$r],
                                'pos_paid' => $_POST['amount'][$r],
                                'pos_balance' => $_POST['balance_amount'][$r],
                                'gc_balance' => $gc_balance,
                            );
                        } else {
                            $payment[] = array(
                                'date' => $date,
                                // 'reference_no' => $this->site->getReference('pay'),
                                'amount' => $amount,
                                'paid_by' => $_POST['paid_by'][$r],
                                'cheque_no' => $_POST['cheque_no'][$r],
                                'cc_no' => $_POST['cc_no'][$r],
                                'cc_holder' => $_POST['cc_holder'][$r],
                                'cc_month' => $_POST['cc_month'][$r],
                                'cc_year' => $_POST['cc_year'][$r],
                                'cc_type' => $_POST['cc_type'][$r],
                                'cc_cvv2' => $_POST['cc_cvv2'][$r],
                                'created_by' => $this->session->userdata('user_id'),
                                'type' => 'received',
                                'note' => $_POST['payment_note'][$r],
                                'pos_paid' => $_POST['amount'][$r],
                                'pos_balance' => $_POST['balance_amount'][$r],
                                'transaction_id' => $_POST['cc_transac_no'][$r],
                            );
                        }
                    }
                }
            }
            if (!isset($payment) || empty($payment)) {
                $payment = array();
            }

            //$this->sma->print_arrays($data, $products, $payment);
        }

        if ($this->form_validation->run() == true && !empty($products) && !empty($data)) {
            if ($suspend) {

                $data['suspend_note'] = $this->input->post('suspend_note');
                $suspend_products = $products;
                foreach ($suspend_products as $key => $sus_product) {
                    unset($sus_product['gst_rate'], $sus_product['cgst'], $sus_product['sgst'], $sus_product['igst']);
                    $suspend_products[$key] = $sus_product;
                }
                $suspend_data = $data;
                unset($suspend_data['cgst'], $suspend_data['sgst'], $suspend_data['igst']);

                if ($result = $this->pos_model->suspendSale($suspend_data, $suspend_products, $did)) {

                    echo json_encode($result);
                    exit;
                    /* $this->session->set_userdata('remove_posls', 1);
                      $this->session->set_flashdata('message', $this->lang->line("sale_suspended"));
                      redirect("pos");
                      exit; */
                }
            } else {
                if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
                    $patient_name = $this->input->post('patient_name');
                    if ($patient_name):
                        $data['cf1'] = $patient_name;
                    endif;

                    $doctor_name = $this->input->post('doctor_name');
                    if ($doctor_name):
                        $data['cf2'] = $doctor_name;
                    endif;
                }
                //  $this->sma->print_arrays($data, $products, $payment);

                if ($sale = $this->pos_model->addSale($data, $products, $payment, $did)) {
                    $this->session->set_userdata('remove_posls', 1);
                    /* if (isset($sale['redirect_pay_url']) && !empty($sale['redirect_pay_url'])) {
                      header("Location:  " . $sale['redirect_pay_url']);
                      exit;
                      } */
                    $msg = $this->lang->line("sale_added");
                    if (!empty($sale['message'])) {
                        foreach ($sale['message'] as $m) {
                            $msg .= '<br>' . $m;
                        }
                    }

                    /* ------ For checking Print/notPrint Button updated by SW 21/01/2017 --------------- */
                    $print = isset($_POST['submit_type']) ? $_POST['submit_type'] : 'print';
                    $_SESSION['print_type'] = $print;
                    /* ------ End For checking Print/notPrint Button updated by SW 21/01/2017 --------------- */

                    $this->session->set_flashdata('message', $msg);

                    //redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale['sale_id']);
                    if ($_POST['submit_type'] == 'notprint') {
                        $response['status'] = 'success';
                        $response['sale'] = $sale;
                        $response['message'] = $msg;
                        echo json_encode($response);
                        exit;
                    } else {
                        redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sale['sale_id']);
                        exit;
                    }
                }
            }
        } else {
            $this->data['suspend_sale'] = null;
            if ($sid) {
                if ($suspended_sale = $this->pos_model->getOpenBillByID($sid)) {
                    $inv_items = $this->pos_model->getSuspendedSaleItems($sid);
                    krsort($inv_items);
                    $c = rand(100000, 9999999);
                    foreach ($inv_items as $item) {
                        $row = $this->site->getProductByID($item->product_id);
                        if (!$row) {
                            $row = json_decode('{}');
                            $row->tax_method = 0;
                            $row->quantity = 0;
                        } else {
                            $category = $this->site->getCategoryByID($row->category_id);
                            $row->category_name = $category->name;
                            unset($row->cost, $row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                        }
                        $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $row->quantity += $pi->quantity_balance;
                            }
                        }
                        $row->id = $item->product_id;
                        $row->code = $item->product_code;
                        $row->name = $item->product_name;
                        $row->type = $item->product_type;
                        $row->quantity += $item->quantity;
                        $row->discount = $item->discount ? $item->discount : '0';
                        $row->price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                        $row->unit_price = $row->tax_method ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                        $row->real_unit_price = $item->real_unit_price;
                        $row->base_quantity = $item->quantity;
                        $row->base_unit = isset($row->unit) ? $row->unit : $item->product_unit_id;
                        $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                        $row->unit = $item->product_unit_id;
                        $row->qty = $item->unit_quantity;
                        $row->tax_rate = $item->tax_rate_id;
                        $row->serial = $item->serial_no;
                        $row->option = $item->option_id;
                        $options = $this->pos_model->getProductOptions($row->id, $item->warehouse_id);

                        if ($options) {
                            $option_quantity = 0;
                            foreach ($options as $option) {
                                $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $item->option_id);
                                if ($pis) {
                                    foreach ($pis as $pi) {
                                        $option_quantity += $pi->quantity_balance;
                                    }
                                }
                                if ($option->quantity > $option_quantity) {
                                    $option->quantity = $option_quantity;
                                }
                            }
                        }

                        $combo_items = false;
                        if ($row->type == 'combo') {
                            $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                        }
                        if ($row->type == 'Bundle') {
                            $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                        }
                        $units = $this->site->getUnitsByBUID($row->base_unit);
                        $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                        $ri = $this->Settings->item_addition ? $row->id : $c;

                        $pr[$ri] = array('id' => $c, 'item_id' => $row->id, 'label' => $row->name . " (" . $row->code . ")",
                            'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                        $c++;
                    }

                    $this->data['items'] = json_encode($pr);
                    $this->data['sid'] = $sid;
                    $this->data['suspend_sale'] = $suspended_sale;
                    $this->data['message'] = lang('suspended_sale_loaded');
                    $this->data['customer'] = $this->pos_model->getCompanyByID($suspended_sale->customer_id);
                    $this->data['reference_note'] = $suspended_sale->suspend_note;
                } else {
                    //$this->session->set_flashdata('error', lang("bill_x_found"));
                    redirect("pos");
                }
            } else {
                $this->data['customer'] = $this->pos_model->getCompanyByID($this->pos_settings->default_customer);

                $this->data['reference_note'] = null;
            }

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['message'] = isset($this->data['message']) ? $this->data['message'] : $this->session->flashdata('message');

            $this->data['biller'] = $this->site->getCompanyByID($this->pos_settings->default_biller);
            $this->data['billers'] = $this->site->getAllCompanies('biller');
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['user'] = $this->site->getUser();
            $this->data["tcp"] = $this->pos_model->products_count($this->pos_settings->default_category);

            $featuerd_products_count = $this->pos_model->featuerd_products_count();
            if ($this->input->get('seasonsId')) {
                $seasons_id = $this->input->get('seasonsId');
             
                $products_category = $this->pos_model->getSeasonWiseProductsCategory($seasons_id);
                $product_category = array();
                $category_id = '';
                foreach($products_category as $category){
                    $category_id = $category->category_id;
                    $product_category[] = $this->site->getCategoryByID($category_id);
                }
                
                $this->data['categories']  =  $product_category;               
                $category_id = isset($category_id) ? $category_id : $this->pos_settings->default_category;
             
            }else {
                $category_id = isset($category_id) ? $category_id : $this->pos_settings->default_category;
                // $this->data['categories'] = $this->site->getProdCategories();
                $this->data['categories'] = $this->site->getProdCategoriesWithDefaultCategory($category_id);
                // $this->data['categories'] = $this->site->getProdCategoriesWithProdSeason();

            }

            if ((int) $featuerd_products_count > 0 && $this->pos_settings->pos_screen_products == 1):
                $this->data['products'] = $this->featuerdProducts();
                $this->data['featuerd_products'] = 1;
            else:
                $this->data['products'] = $this->ajaxproducts($this->pos_settings->default_category,null,$seasons_id);
            endif;

            $this->data['categories'] = $this->site->getAllCategories();
            $this->data['brands'] = $this->site->getAllBrands();
            $this->data['subcategories'] = $this->site->getSubCategories($this->pos_settings->default_category);

            if ($this->pos_settings->paynear == 1):
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');
                $paynear_credential = $payment_config['paynear'];
                $this->pos_settings->paynear_app = isset($paynear_credential['PAYNEAR_APP_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_APP_SECRET_KEY']) ? $paynear_credential['PAYNEAR_APP_SECRET_KEY'] : '';
                $this->pos_settings->paynear_web = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
            endif;
            $this->data['pos_settings'] = $this->pos_settings;

            $this->data['opend_bill_count_custom'] = $this->pos_model->bills_count(); //updated by SW 0n25-01-2015
            if ($this->Settings->theme == "theme_three" || $this->Settings->theme == "theme_four" || $this->Settings->theme == "theme_five") {
                $this->load->view($this->theme . 'pos/add_ep', $this->data);
            }else {
                $this->load->view($this->theme . 'pos/add', $this->data);
            }
        }
    }

    public function check_temp_order() {
        $rows = $this->pos_model->fetch_temp_bills(1, null, 'desc');
        echo json_encode($rows[0]);
        exit;
    }

    public function deleteSuspend($did) {
        $this->pos_model->deleteSuspend($did);
    }

    public function searchGiftcardByCustomer($customer_id = null, $bill_amt = null) {
        if ($this->input->get('customer_id')) {
            $customer_id = $this->input->get('customer_id', true);
        }
        if ($this->input->get('bill_amt')) {
            $bill_amt = $this->input->get('bill_amt', true);
        }

        $data = $this->pos_model->getGiftcardByCustomer($customer_id);

        echo json_encode($data);
    }

    public function searchDepositByCustomer($customer_id = null, $bill_amt = null) {
        if ($this->input->get('customer_id')) {
            $customer_id = $this->input->get('customer_id', true);
        }
        if ($this->input->get('bill_amt')) {
            $bill_amt = $this->input->get('bill_amt', true);
        }

        $data = $this->pos_model->getDepositByCustomer($customer_id);

        echo json_encode($data);
    }

    public function checkoutdata() {
        $this->data['billers'] = $this->site->getAllCompanies('biller');
        $this->load->view($this->theme . 'pos/checkout', $this->data);
    }

    public function set_saleorder() {

        $data = $this->input->post('data');
        $dataarr = json_decode($data);

        $biller_id = ($dataarr->biller_id != 'null' && $dataarr->biller_id) ? $dataarr->biller_id : $this->pos_settings->default_biller;

        $this->db->insert('order_transactions', ['created_time' => 'now()', 'request_data' => $data]);

        $order_id = $this->db->insert_id();

        $dataarr->order_id = $order_id;
        $dataarr->biller = $this->site->getCompanyByID($biller_id);
        $dataarr->customer = $this->site->getCompanyByID($dataarr->customer_id);

        if ($this->pos_model->updateOrderTransaction($order_id, ['request_data' => serialize($dataarr), 'created_time' => date('Y-m-d H:i:s')])) {

            echo json_encode($dataarr);
        }
    }

    // SMS Send Funtion
    public function sms_send($passurl) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $passurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function send_sms($code, $no) {

        // $code = $this->input->get('code');
        $this->load->model('eshop_model');
        $res = $this->eshop_model->validateRecieptSales($code);

        if ($res) {
            $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        } else {
            die('Invalide receipt code');
        }

        if (!$sale_id) {
            die('No sale selected.');
        }

        $inv = $this->sales_model->getInvoiceByID($id);

        $str = '';
        if ($inv->grand_total):
            $str = 'Thanks for visiting ' . $this->Settings->site_name . '. Your Total Bill amount ' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency;
        endif;

 //

        $url = base_url('reciept/pdf/') . $code;
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://lntp.in?key=hbfsbfnbkfsdhbkdgf367n&q='.$url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);
        //

        $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
        $url = $response->url;
        $pass_str = $str . ' You can view receipt ' . $url;

        $output = $this->CallSMS($no, $pass_str, 'SALE_INVOICE');
        return $output;
    }

    function get_tiny_url($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, 'http://tinyurl.com/api-create.php?url=' . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    function CallSMS($mobile, $msg, $sms_template_key = NULL) {

        if ($sms_template_key == NULL) {
            return '{"msg":"SMS template key is null."}';
        } else {

            $res = $this->sma->SendSMS($mobile, $msg, $sms_template_key);

            if (!empty($res)):
                $Obj = json_decode($res);
                if (isset($Obj) && $Obj->Status == 'Success'):
                    $rec['sms_log'] = $this->sma->setSMSLog($mobile, $msg, $Obj->Description);
                    $rec['sms_balance_update'] = $this->sma->update_sms_count(1);
                    return '{"msg":"Your receipt has been successfully sent by SMS."} ';
                else:
                    return '{"msg": "' . $Obj->Description . '"} ';
                endif;
            else:
                return '{"msg": "Error , Please try again "} ';
            endif;
        }
    }

    //End  SMS Send Funtion

    public function char_limit($string, $limit) {
        return (strlen($string) > $limit) ? substr($string, 0, $limit - 1) . '...' : $string;
    }

    // Get Data Dependancy
    public function get_dependancy() {
        $getKyes = array_keys($this->input->get());
        $get_data = $this->input->get($getKyes[0]);
        $get_customer = $this->db->select('id,name,phone,customer_group_id')->where($getKyes['0'], $get_data)->get('sma_companies')->row();
        echo json_encode($get_customer);
    }

    // Get Cusoemr

    public function getCustomerAuto() {
        $term = $this->input->get('term', true);
        $retrun = $this->input->get('return');


        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }

        //if($retrun=='name'){
        $sql = "SELECT id, name, phone FROM sma_companies WHERE " . $retrun . " LIKE '%" . $term . "%' OR cf1 LIKE '%" . $term . "%'  OR cf2 LIKE '%" . $term . "%'  limit 50";

        /* }else{ 
          //$sql = "SELECT id, name, phone FROM sma_companies WHERE " . $retrun . " LIKE '%" . $term . "%'  limit 50";
          } */
        $cmp = $this->db->query($sql)->result();
        foreach ($cmp as $row) {
            $data[] = $row->$retrun;
        }
        echo json_encode($data);
    }

    public function getCustomerName() {
        $term = $this->input->get('term', true);
        $retrun = $this->input->get('return');
        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }
        // $sql = "SELECT id, name, phone FROM sma_companies WHERE " . $retrun . " LIKE '%" . $term . "%'  limit 50";
        $sql = "SELECT id, name, phone FROM sma_companies WHERE  IF(" . $retrun . " LIKE '%" . $term . "%'," . $retrun . " LIKE '%" . $term . "%',Replace(coalesce(" . $retrun . ",''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%' OR cf1 LIKE '" . $term . "'  OR cf2 LIKE '%" . $term . "%' ) limit 50";

        $cmp = $this->db->query($sql)->result();
        foreach ($cmp as $row) {
            $data[] = $row->name . ' (' . $row->phone . ')';
        }
        echo json_encode($data);
    }

    // End Get Data Dependancy
    // End  09-04-19    
    // Setting model popup
    public function short_setting() {
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['pos'] = $this->pos_model->getSetting();
        $this->data['pos']->pos_theme = json_decode($this->data['pos']->pos_theme);
        $this->data['categories'] = $this->site->getAllCategories();
        //$this->data['customer'] = $this->pos_model->getCompanyByID($this->pos_settings->default_customer);
        $this->data['billers'] = $this->pos_model->getAllBillerCompanies();
        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['tax_rates'] = $this->site->getAllTaxRates();
        $this->data['offer_categories'] = $this->site->getOfferCategories();
        $this->config->load('payment_gateways');
        $this->data['stripe_secret_key'] = $this->config->item('stripe_secret_key');
        $this->data['stripe_publishable_key'] = $this->config->item('stripe_publishable_key');
        $authorize = $this->config->item('authorize');
        $this->data['api_login_id'] = $authorize['api_login_id'];
        $this->data['api_transaction_key'] = $authorize['api_transaction_key'];
        $this->data['APIUsername'] = $this->config->item('APIUsername');
        $this->data['APIPassword'] = $this->config->item('APIPassword');
        $this->data['APISignature'] = $this->config->item('APISignature');
        $this->data['paypal_balance'] = null; // $this->pos_settings->paypal_pro ? $this->paypal_balance() : NULL;
        $this->data['stripe_balance'] = null; // $this->pos_settings->stripe ? $this->stripe_balance() : NULL;
        $instamojo = $this->config->item('instamojo');
        $this->data['instamojo_auth_token'] = $instamojo['AUTH_TOKEN'];
        $this->data['instamojo_api_key'] = $instamojo['API_KEY'];

        $ccavenue = $this->config->item('ccavenue');
        $this->data['ccavenue_merchant_id'] = $ccavenue['MERCHANT_ID'];
        $this->data['ccavenue_access_code'] = $ccavenue['ACCESS_CODE'];
        $this->data['ccavenue_working_key'] = $ccavenue['API_KEY'];

        $paytm = $this->config->item('paytm');
        $this->data['PAYTM_ENVIRONMENT'] = $paytm['PAYTM_ENVIRONMENT'];
        $this->data['PAYTM_MERCHANT_KEY'] = $paytm['PAYTM_MERCHANT_KEY'];
        $this->data['PAYTM_MERCHANT_MID'] = $paytm['PAYTM_MERCHANT_MID'];
        $this->data['PAYTM_MERCHANT_WEBSITE'] = $paytm['PAYTM_MERCHANT_WEBSITE'];

        $paynear = $this->config->item('paynear');
        $this->data['PAYNEAR_APP_SECRET_KEY'] = $paynear['PAYNEAR_APP_SECRET_KEY'];
        $this->data['PAYNEAR_SECRET_KEY'] = $paynear['PAYNEAR_SECRET_KEY'];
        $this->data['PAYNEAR_MERCHANT_ID'] = $paynear['PAYNEAR_MERCHANT_ID'];
        $this->data['PAYNEAR_APP_MERCHANT_ID'] = $paynear['PAYNEAR_APP_MERCHANT_ID'];
        $this->data['PAYNEAR_SANDBOX'] = $paynear['PAYNEAR_SANDBOX'];

        $payumoney = $this->config->item('payumoney');
        $this->data['PAYUMONEY_MID'] = $payumoney['PAYUMONEY_MID'];
        $this->data['PAYUMONEY_KEY'] = $payumoney['PAYUMONEY_KEY'];
        $this->data['PAYUMONEY_SALT'] = $payumoney['PAYUMONEY_SALT'];
        $this->data['PAYUMONEY_AUTH_HEADER'] = $payumoney['PAYUMONEY_AUTH_HEADER'];
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('pos_settings')));
        // $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);

        $this->load->view($this->theme . 'pos/short_setting', $this->data);
    }

    // End Setting Model Popup
    // Theme chnages
    function change_theme($theme = NULL) {
        echo json_encode($this->pos_model->themeChange($theme));
    }

    // End Themes Changes

    public function addcustomer() {
        $response = array();
        $this->load->model('companies_model');
        $biller = $this->companies_model->getCompanyByID($this->Settings->default_biller);

        $field = array(
            'company' => '-',
            'name' => $this->input->get('name'),
            'phone' => $this->input->get('mobile_no'),
            'group_id' => 3,
            'customer_group_id' => 1,
            'group_name' => 'customer',
            'state' => $biller->state,
            'state_code' => $biller->state_code,
            'country' => $biller->country,
        );

        $this->db->insert('sma_companies', $field);
        if ($this->db->affected_rows()) {
            $id = $this->db->insert_id();
            $response['msg'] = 'Success';
            $response['id'] = $id;
        } else {
            $response['msg'] = 'Error';
        }
        echo json_encode($response);
    }

    //offerdetails
    function change_offerdetails($offer = NULL) {
        echo json_encode($this->pos_model->get_offer_details($offer));
    }

    function updates_offerdetails() {
        $response = array();

        $data = $this->input->get();
        $id = $this->input->get('offer_id');
        unset($data['offer_id']);
        $res = $this->pos_model->offerUpdates($id, $data);
//print_r( $res );exit;
        if ($res = "true") {
            $response['msg'] = 'offer updated successfully';
        } else {
            $response['msg'] = 'offer NOt updated successfully';
        }
        echo json_encode($response);
        //$this->session->set_flashdata('message');         
    }

    function recent_pos_list() {
        //print_r($this->data['pos_settings']);
        $pos_settingss = $this->data['pos_settings'];
        $limit = $pos_settingss->recent_pos_limit;
        $Customer = '';
        //if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
        if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin) {
            $Customer = ' and s.created_by = ' . $this->session->userdata('user_id');
        } elseif ($this->Customer) {
            $Customer = ' and s.customer_id = ' . $this->session->userdata('user_id');
        }
        $this->data['POSDATA'] = $this->pos_model->getRecentPosSale($limit, $Customer);
        // exit;
        $this->load->view($this->theme . 'pos/recent_pos_list', $this->data);
    }

    public function alert_qty_email($save_bufffer = 'S') {
        $alertprods = $this->count_alert_products();
        $this->load->library('pdf');
        $html = '';
        $html .= 'Products list below Alert quantity<br><table style="border-collapse: collapse;" border="1" >
                          <thead>
                          <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Name</th>
                            <th scope="col">Variants(Name, Qty)</th>
                            <th scope="col">Product Code</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Alert Quantity</th>
                          </tr>
                        </thead>
                        <tbody>';
        $i = 1;
        foreach ($alertprods['result'] as $alertprod) {
            $html .= '<tr>
                                <td>' . $i++ . '</td>
                                <td>' . $alertprod['name'] . '</td>';
            $html .= '<td>';
            if ((!empty($alertprod['option']))) {
                foreach ($alertprod['option'] as $alertProdOption) {
                    $html .= $alertProdOption['name'] . ' , ' . floor($alertProdOption['quantity']) . '<br>';
                }
            }
            $html .= '</td>';
            $html .= '<td>' . $alertprod['code'] . '</td>';
            $html .= '<td>' . floor($alertprod['quantity']) . '</td>';
            $html .= '<td>' . floor($alertprod['alert_quantity']) . '</td>
                          </tr>';
        }
        $html .= '</tbody>
                  </table>';
        $pdfFilepath = 'alert_qty_product.pdf';
        $pdfattach = $this->sma->generate_pdf($html, $pdfFilepath, $save_bufffer);
        $alertprods = $this->count_alert_products();
        $to = $this->pos_model->getUsersbyGroupId();
        // $to ='tejaswini@simplysafe.in, nita@simplysafe.in';
        // $pdf_file = $this->alert_qty_pdf('S');
        $subject = 'Alert Quantity Email';
        $message = '';
        $message .= 'Hello Merchant, There are ' . $alertprods['count'] . ' ' . 'Products are under alert quantity. Please Find the attachment.';
        if ($to != '')
            $this->sma->send_email($to, $subject, $message, null, null, $pdfattach);
    }

    public function dailysale_email() {
        $ccsales = $this->pos_model->getTodayCCSales();
        $dcsales = $this->pos_model->getTodayDCSales();
        $gcsales = $this->pos_model->getTodayGiftCardSales();
        $othersales = $this->pos_model->getTodayOtherSales();
        $cashsales = $this->pos_model->getTodayCashSales();
        $chsales = $this->pos_model->getTodayChSales();
        $pppsales = $this->pos_model->getTodayPPPSales();
        $stripesales = $this->pos_model->getTodayStripeSales();
        $authorizesales = $this->pos_model->getTodayAuthorizeSales();
        $duepayment = $this->pos_model->getTodayDueSales();
        $duepartial = $this->pos_model->getcalpartial(); //20-03-19
        $totalsales = $this->pos_model->getTodaySales();
        $totalsalespaid = $this->pos_model->getTodaySalesPaid();
        $refunds = $this->pos_model->getTodayRefunds();
        $expenses = $this->pos_model->getTodayExpenses();
        $paymentOptions = ['paytm' => 'PAYTM', 'neft' => 'NEFT', 'google_pay' => 'Googlepay', 'swiggy' => 'swiggy', 'zomato' => 'zomato', 'ubereats' => 'ubereats', 'complimentary' => 'complimentry'];
        $msg = '';
        $msg .= '<table border="1" style="border-collapse: collapse;"><thead><tr>
                        <th scope="col">Payment Mode</th>
                        <th scope="col">Paid Amount</th>
                   </tr></thead><tbody>';
        $msg .= '<tr><td>' . lang('cash_in_hand') . '</td><td>' . $this->session->userdata('cash_in_hand') . '</td></tr>';
        $msg .= '<tr><td>' . lang('cash_sale') . '</td><td>' . ($cashsales->paid ? $cashsales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('ch_sale') . '</td><td>' . ($chsales->paid ? $chsales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('cc_sale') . '</td><td>' . ($ccsales->paid ? $ccsales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('dc_sale') . '</td><td>' . ($dcsales->paid ? $dcsales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('Gift Card Sale') . '</td><td>' . ($gcsales->paid ? $gcsales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('Other Sale') . '</td><td>' . ($othersales->paid ? $othersales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('paypal_pro') . '</td><td>' . ($pppsales->paid ? $pppsales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('Authorize Net') . '</td><td>' . ($authorizesales->paid ? $authorizesales->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('stripe') . '</td><td>' . ($stripesales->total ? $stripesales->total : '0.00') . '</td></tr>';
        $total_paid = 0;
        //  echo $pos_settings->$payOpt_key;
        /* if(is_array($paymentOptions)){
          foreach ($paymentOptions as $payOpt_key=>$payOpt) {
          if($pos_settings->$payOpt_key) {
          $this->data[$payOpt_key] = $this->pos_model->getTodayPaymentOptionSales($payOpt);
          print_r($this->pos_model->getTodayPaymentOptionSales($payOpt));exit;
          $total_paid .= $payoption->paid;
          $msg .='<tr><td>'.ucfirst(lang($payOpt)).'</td><td>'.$payoption->paid .'</td></tr>';
          }}}exit; */
        foreach ($paymentOptions as $payOpt_key => $payOpt) {
            if ($this->data['pos_settings']->$payOpt_key) {
                $this->data['pos_settings']->$payOpt_key;
                $total_paid .= $payoption->paid;
                $this->data[$payOpt_key] = $this->pos_model->getTodayPaymentOptionSales($payOpt);
                $msg .= '<tr><td>' . ucfirst(lang($payOpt)) . '</td><td>' . ($payOpt_key->paid ? $payOpt_key->paid : '0.00') . '</td></tr>';
            }
        }
        $msg .= '<tr><td>' . lang('Total Paid') . '</td><td>' . ($totalsalespaid->paid ? $totalsalespaid->paid : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('total_sales') . '</td><td>' . ($totalsales->total ? $totalsales->total : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('Total Due') . '</td><td>' . ($duepayment->total + $duepartial->partial_due) . '</td></tr>';
        $msg .= '<tr><td>' . lang('refunds') . '</td><td>' . ($refunds->returned ? $refunds->returned : '0.00') . '</td></tr>';
        $msg .= '<tr><td>' . lang('total_cash') . '</td><td>' . ($cashsales->paid ? $this->sma->formatMoney(($cashsales->paid + ($this->session->userdata('cash_in_hand'))) - $expense - (str_replace('-', '', $refunds->returned ? $refunds->returned : 0) )) : $this->sma->formatMoney($this->session->userdata('cash_in_hand') - $expense))
                . '</td></tr>';
        $msg .= '</tbody></table>';
        $dailysaleProduct = $this->dailysoldProduct();
        $multi_attach = array($dailysaleProduct);
        //return $msg;
        $to = $this->pos_model->getUsersbyGroupId();

        $subject = 'Daily Sale Email';
        if ($to != '')
            $this->sma->send_email($to, $subject, $msg, null, null, $multi_attach);
        unlink($dailysaleProduct);
    }

    public function SendAutoEmail() {
        $sendemailurl = $this->uri->segment(3); //sendemail_logout
        $daily_saleemail = 0;
        $qty_alertemail = 0;
        if ($sendemailurl == 'close_register') {//send_email on close regiseter
            if ($this->pos_settings->daily_sale_auto_email == 1) {
                $daily_saleemail = 1;
                if ($daily_saleemail == 1) {
                    $this->dailysale_email();
                }
            }
            if ($this->pos_settings->alert_qty_auto_email == 1) {
                $qty_alertemail = 1;
                if ($qty_alertemail == 1) {
                    $this->alert_qty_email();
                }
            }
        }
        if ($sendemailurl == 'sendemail_logout') { //send_email on logout
            if ($this->pos_settings->daily_sale_auto_email == 2) {
                $daily_saleemail = 1;
                if ($daily_saleemail == 1) {
                    $this->dailysale_email();
                }
            }
            if ($this->pos_settings->alert_qty_auto_email == 2) {
                $qty_alertemail = 1;
                if ($qty_alertemail == 1) {
                    $this->alert_qty_email();
                }
            }
        }
        if ($sendemailurl == 'close_register') {
            redirect('/pos/registers/1');
        } elseif ($sendemailurl == 'sendemail_logout') {
            redirect('auth/logout/1');
        }
    }

    /**
     * 
     * @param type $cardNo
     * @return type
     * Date 13-09-19
     */
    public function giftcardBalance($cardNo) {
        $q = $this->pos_model->giftcardBalance($cardNo);

        return $q->balance;
    }

    /*     * * Date 4-11-19 */

    public function depositebal($cardNo) {
        $q = $this->pos_model->depositeBalance($cardNo);
        return $q->deposit_amount;
    }

    /** Date 21-11-19  */
    public function saleRounding($saleid) {
        $q = $this->pos_model->salesRounding($saleid);
        return $q->rounding;
    }

    public function deleteimage() {

        $fieldname = $this->uri->segment(3);

        if (!empty($fieldname)) {

            if (unlink(str_replace(base_url(), '', $this->pos_settings->$fieldname))) {
                $this->pos_model->updateSetting([$fieldname => NULL]);
            }
        }

        redirect('pos/settings');
    }

    /**
     * Check Report Send Status
     * @return string|boolean
     */
    public function sendEmailReport() {
        if ($this->Settings->reports_send_on_email) {
            $email = $this->Settings->default_email;
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && $email) {
                $start_date = date('Y-m-') . '1';
                $end_date = date('Y-m-') . '7';

                if ((date('Y-m-d', strtotime($start_date)) >= date('Y-m-d')) && ( date('Y-m-d') <= date('Y-m-d', strtotime($end_date)))) {
                    $this->db->where('MONTH(send_date)', date('m'));
                    $this->db->where('YEAR(send_date)', date('Y'));
                    $sql = $this->db->get('sma_reports_email_log')->row();
                    if ($sql == '') {
                        return 'send_reports';
                    }
                }
            }
        }
        return false;
    }

    /*     * *New eshop sale mail Attach file** */

    public function pdf($id = null, $view = null, $save_bufffer = null) {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->sales_model->getInvoiceByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->sales_model->getPaymentsForSale($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->sales_model->getAllInvoiceItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getInvoiceByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllInvoiceItems($inv->return_id) : NULL;
        //$this->data['paypal'] = $this->sales_model->getPaypalSettings();
        //$this->data['skrill'] = $this->sales_model->getSkrillSettings();

        if ($inv->eshop_sale) {
            $this->data['shipping_details'] = $this->pos_model->getShipingDetails($inv->order_no);
        }

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";


        $html = $this->load->view($this->theme . 'sales/pdf_reciept', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }


        if ($view) {
            $this->load->view($this->theme . 'sales/pdf_reciept', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    /*     * ** */
    /*     * *12-09-2020** */

    public function searchAwardPointByCustomer($customer_id = null, $bill_amt = null) {
        if ($this->input->get('customer_id')) {
            $customer_id = $this->input->get('customer_id', true);
        }
        if ($this->input->get('bill_amt')) {
            $bill_amt = $this->input->get('bill_amt', true);
        }
        $award_point_by_percent = $this->Settings->award_point_by_percent;
        $ApplyPercentByAwardPoint = ($bill_amt * $award_point_by_percent) / 100;
        $RedeemAmt = $this->Settings->each_redeem;
        $RedeemPoint = $this->Settings->ca_point;
        $data = $this->pos_model->getAwardPointByCustomer($customer_id);
        $CustomerAwardPoint = $data['award_points'];
        $CustomerPhone = $data['phone'];
        $Msg = '';
        $FinalRedeemPoint = 0;
        $InvoiceAmt = $bill_amt;
        if ($CustomerAwardPoint <= 0) {
            $Msg = 'AwardPointNotFound';
        } else {
            if ($RedeemPoint == 0 || $RedeemPoint == '') {
                $Msg = 'AwardPointNotAdded';
            } else {

                if ($ApplyPercentByAwardPoint >= $RedeemAmt) {
                    $CalRedeemPoint = $ApplyPercentByAwardPoint / $RedeemAmt;
                    $CalculateRedeemPoint = round($CalRedeemPoint, 2);
                    if ($CustomerAwardPoint >= $CalculateRedeemPoint) {
                        //$FinalRedeemPoint = $CustomerAwardPoint-$CalculateRedeemPoint;
                        $FinalRedeemPoint = $CalculateRedeemPoint;
                        $Msg = 'Success';
                    } else {
                        $FinalRedeemPoint = $CustomerAwardPoint;
                        $InvoiceAmt = $RedeemAmt * $FinalRedeemPoint;
                        $Msg = 'Success';
                    }
                } else {
                    $Msg = 'AwardPointNotValid';
                }
            }
        }
        $ArrData = array('msg' => $Msg, 'redeem_point' => $FinalRedeemPoint, 'invoice_amount' => $InvoiceAmt, 'phone' => $CustomerPhone );
        echo json_encode($ArrData);
    }

    function send_otp($MobileNo = null) {
        $OTP = rand(100000, 999999);
        if ($this->input->get('MobileNo')) {
            $MobileNo = $this->input->get('MobileNo', true);
        }
        $urlpass = site_url('reciept/verify_mobile?code=' . $OTP . '&phone=' . $MobileNo);

        // $sms_reponse = $this->sms_send($urlpass);

        $Arr = array('OTP' => $OTP, 'sms' => $sms_reponse, 'url' => $urlpass);
        //$Arr = array('OTP'=>$OTP);
        echo json_encode($Arr);
    }

    /*     * *12-09-2020** */

    public function dailysoldProduct() {
        $sales = $this->pos_model->dailysales();



        $this->load->library('excel');
        $this->excel->setActiveSheetIndex(0);
        $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

        $this->excel->getActiveSheet()->getStyle("A1:O1")->applyFromArray($style);
        $this->excel->getActiveSheet()->mergeCells('A1:O1');
        $this->excel->getActiveSheet()->SetCellValue('A1', 'Daily Sales Products');
        $styleArray = array(
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            )
        );
        $this->excel->getActiveSheet()->getStyle("A3:O3")->applyFromArray($styleArray);
        $this->excel->getActiveSheet()->SetCellValue('A3', lang('date'));
        $this->excel->getActiveSheet()->SetCellValue('B3', lang('Invoice No'));
        $this->excel->getActiveSheet()->SetCellValue('C3', lang('reference_no'));
        $this->excel->getActiveSheet()->SetCellValue('D3', lang('Customer Name'));
        $this->excel->getActiveSheet()->SetCellValue('E3', lang('Proudct Code'));
        $this->excel->getActiveSheet()->SetCellValue('F3', lang('Product Name'));
        $this->excel->getActiveSheet()->SetCellValue('G3', lang('Article_Code'));
        $this->excel->getActiveSheet()->SetCellValue('H3', lang('HSN_Code'));
        $this->excel->getActiveSheet()->SetCellValue('I3', lang('Variant'));
        $this->excel->getActiveSheet()->SetCellValue('J3', lang('quantity'));
        $this->excel->getActiveSheet()->SetCellValue('K3', lang('Unit_Price'));
        $this->excel->getActiveSheet()->SetCellValue('L3', lang('Discount'));
        $this->excel->getActiveSheet()->SetCellValue('M3', lang('Tax'));
        $this->excel->getActiveSheet()->SetCellValue('N3', lang('price'));
        $this->excel->getActiveSheet()->SetCellValue('O3', lang('Subtotal'));

        $row = 4;
        $total_sales = 0;
        foreach ($sales as $sale_invoice) {
            $sales_item = $this->pos_model->dailySalesProduct($sale_invoice->id);
            $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($sale_invoice->date));
            $this->excel->getActiveSheet()->SetCellValue('B' . $row, $sale_invoice->invoice_no);
            $this->excel->getActiveSheet()->SetCellValue('C' . $row, $sale_invoice->reference_no);
            $this->excel->getActiveSheet()->SetCellValue('D' . $row, $sale_invoice->customer);
            $start = $row;
            foreach ($sales_item as $item) {
                $this->excel->getActiveSheet()->setCellValueExplicit('E' . $row, $item->product_code, PHPExcel_Cell_DataType::TYPE_STRING);
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $item->product_name);
                $this->excel->getActiveSheet()->setCellValueExplicit('G' . $row, $item->article_code, PHPExcel_Cell_DataType::TYPE_STRING);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $item->hsn_code);
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, '');
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $item->quantity);
                $this->excel->getActiveSheet()->SetCellValue('K' . $row, $item->invoice_net_unit_price);
                $this->excel->getActiveSheet()->setCellValueExplicit('L' . $row, $item->discount, PHPExcel_Cell_DataType::TYPE_STRING);
                $this->excel->getActiveSheet()->SetCellValue('M' . $row, $item->item_tax);
                $this->excel->getActiveSheet()->SetCellValue('N' . $row, $item->invoice_total_net_unit_price);
                $this->excel->getActiveSheet()->SetCellValue('O' . $row, $item->subtotal);
                $styleArray = array(
                    'borders' => array(
                        'allborders' => array(
                            'style' => PHPExcel_Style_Border::BORDER_THIN
                        ),
                    )
                );
                $this->excel->getActiveSheet()->getStyle("A" . $row . ":O" . $row)->applyFromArray($styleArray);


                $row++;
            }
            $this->excel->getActiveSheet()->mergeCells('A' . $start . ':A' . ($row - 1));
            $this->excel->getActiveSheet()->mergeCells('B' . $start . ':B' . ($row - 1));
            $this->excel->getActiveSheet()->mergeCells('C' . $start . ':C' . ($row - 1));
            $this->excel->getActiveSheet()->mergeCells('D' . $start . ':D' . ($row - 1));

            $styleArray = array(
                'borders' => array(
                    'top' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN
                    ),
                    'bottom' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN
                    )
                )
            );
            $this->excel->getActiveSheet()->getStyle("A" . $row . ":O" . $row)->applyFromArray($styleArray);

            $styleArray = array(
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => '000'),
                    'size' => 12,
                    'name' => 'Calibri'
                ), 'borders' => array(
                    'allborders' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN
                    ),
            ));

            $this->excel->getActiveSheet()->getStyle("A" . $row . ":O" . $row)->applyFromArray($styleArray);
            $this->excel->getActiveSheet()->mergeCells('A' . $row . ':N' . $row);
            $this->excel->getActiveSheet()->SetCellValue('A' . $row, 'Grand Total');
            $this->excel->getActiveSheet()->SetCellValue('O' . $row, $sale_invoice->grand_total);

            $total_sales += $sale_invoice->grand_total;

            $row = $row + 1;
            $row++;
        }
        $row = $row + 1;
        $this->excel->getActiveSheet()->getStyle("A" . $row . ":O" . $row)->applyFromArray($styleArray);
        $this->excel->getActiveSheet()->mergeCells('A' . $row . ':N' . $row);
        $this->excel->getActiveSheet()->SetCellValue('A' . $row, 'Total Sales');
        $this->excel->getActiveSheet()->SetCellValue('O' . $row, $total_sales);

        $filename = 'daily_sales_product_' . date('Y_m_d_H_i_s');



        header('Content-Type: application/vnd.ms-excel');

        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
        $objWriter->save(str_replace(__FILE__, 'assets/' . $filename . '.xls', __FILE__));
        return 'assets/' . $filename . '.xls';
    }

    function get_coupon_by_code($coupon_code) {
        // $active_coupons = $this->pos_model->getCouponByCode($coupon_code);

        $active_coupons = $this->pos_model->getDiscountCouponByCode($coupon_code);

        echo json_encode($active_coupons);
    }

    /*     * **************************************************************************
     * Razorpay Integration
     * ************************************************************************** */

    public function razorpay_init() {
        $sale_id = $this->input->get('sid');
        if ((int) $sale_id > 0) {
            $sale = $this->site->getSaleByID($sale_id);
            if ($sale->id == $sale_id) {
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];
                //	$api = new Api('rzp_test_nEc2AabwdiJ6xf', '0vfdxx3UBkZrjfJL1hg9KrT5');
                $api = new Api($paymentData['RAZORPAY_KEY'], $paymentData['RAZORPAY_SECRET']);

                /**
                 * You can calculate payment amount as per your logic
                 * Always set the amount from backend for security reasons
                 */
                $_SESSION['payable_amount'] = $sale->grand_total;
                $_SESSION['currency'] = $this->Settings->default_currency;


                $razorpayOrder = $api->order->create(array(
                    'receipt' => $sale->invoice_no,
                    'amount' => $sale->grand_total * 100,
                    'currency' => $this->Settings->default_currency,
                    'payment_capture' => 1, // auto capture
                ));



                $amount = $razorpayOrder['amount'];

                $razorpayOrderId = $razorpayOrder['id'];


                $_SESSION['razorpay_order_id'] = $razorpayOrderId;
                $datapass = $this->prepareData($amount, $razorpayOrderId);
                $datapass['prefill'] = array(
                    'email' => $customer->email,
                    'contact' => $customer->phone,
                    'name' => $customer->name,
                    'description' => 'sales'
                );
                $datapass['notes'] = array(
                    'address' => $customer->address,
                    'merchant_order_id' => $sale->id
                );
                $datapass['name'] = $this->Settings->site_name;
                $datapass['description'] = '#Order No: ' . $sale->id;


                $this->data['data'] = $datapass;

                $this->load->view($this->theme . 'pos/razorpay', $this->data);
            } else {
                redirect('pos');
            }
        } else {
            redirect('pos');
        }
    }

    /**
     * This function verifies the payment,after successful payment
     */
    public function razorpay_verify() {
        $sid = $this->input->get('sid');
        $success = true;
        $error = "payment_failed";
        if (empty($_POST['razorpay_payment_id']) === false) {
            $ci = get_instance();
            $ci->config->load('payment_gateways', true);
            $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];

            $api = new Api($paymentData['RAZORPAY_KEY'], $paymentData['RAZORPAY_SECRET']);


            try {

                $attributes = array(
                    'razorpay_order_id' => $_SESSION['razorpay_order_id'],
                    'razorpay_payment_id' => $_POST['razorpay_payment_id'],
                    'razorpay_signature' => $_POST['razorpay_signature'],
                    'amount' => $_SESSION['payable_amount'],
                    'currency' => $_SESSION['currency'],
                );
                $api->utility->verifyPaymentSignature($attributes);
            } catch (SignatureVerificationError $e) {
                $success = false;
                $error = 'Razorpay_Error : ' . $e->getMessage();
            }
        }
        if ($success === true) {

            $res = $this->pos_model->RazorPayAfterSale($attributes, $sid);
            if ($res):
                $this->session->set_flashdata('message', lang('payment_done'));
                redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
            endif;
        }
        else {
            $msg = 'The transaction has been declined.';
            $this->session->set_flashdata('message', $msg);
            redirect($this->pos_settings->after_sale_page ? "pos" : "pos/view/" . $sid);
        }
    }

    /**
     * This function preprares payment parameters
     * @param $amount
     * @param $razorpayOrderId
     * @return array
     */
    public function prepareData($amount, $razorpayOrderId) {

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];
        $data = array(
            "key" => $paymentData['RAZORPAY_KEY'],
            "amount" => $amount,
            "name" => $this->Settings->site_name,
            "theme" => array(
                "color" => "#3868f1"
            ),
            "order_id" => $razorpayOrderId,
        );
        return $data;
    }

    /*     * ***********************************************************************
     *        --- End Razorpay Payment Getaway ---
     * *********************************************************************** */

    public function testmail() {

        //Load email library
        $this->load->library('email');
        $this->load->library('encrypt');

//SMTP & mail configuration
        $config = array(
            'protocol' => 'smtp',
            'smtp_host' => 'ssl://smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_user' => 'simplyposmailtest',
            'smtp_pass' => 'Vipin@554',
            'mailtype' => 'html',
            'charset' => 'utf-8',
            'smtp_timeout' => 50,
            'smtp_crypto' => "tls",
            'mailpath' => '/usr/sbin/sendmail',
        );


        $this->email->initialize($config);
        $this->email->set_mailtype("html");
        $this->email->set_newline("\r\n");

//Email content
        $htmlContent = '<h1>Sending email via SMTP server</h1>';
        $htmlContent .= '<p>This email has sent via SMTP server from CodeIgniter application.</p>';

        $this->email->to('gti.chetansonkusare@gmail.com');
        $this->email->from('simplyposmailtest@gmail.com', 'MyWebsite');
        $this->email->subject('How to send email via SMTP server in CodeIgniter');
        $this->email->message($htmlContent);


//Send email
        if ($this->email->send()) {
            echo "Mail Sent Successfully";
        } else {
            echo "Failed to send email";
            show_error($this->email->print_debugger());
        }
    }

    public function RepeateDiscount() {
        $product_code = $this->input->get('product_code');
        $customer_id = $this->input->get('customer_id');
        $repeat_sale_validity = $this->input->get('repeat_sale_validity');
        if ($customer_id != '1') {
            $getDiscount = $this->sales_model->getRepeatSalesCheck($customer_id, $product_code, $repeat_sale_validity);
            if ($getDiscount) {

//             $discountP = $getDiscount['discountP'];
//             $discountAmt = $getDiscount['discountAmt'];
                $response = [
                    'status' => TRUE,
                    'discount' => ($getDiscount['discountP']) ? $getDiscount['discountP'] . '%' : $getDiscount['discountAmt'],
                ];
            } else {
                $response = [
                    'status' => FALSE,
                    'message' => 'Offer not apply',
                ];
            }
        } else {
            $response = [
                'status' => FALSE,
                'message' => 'Offer not apply',
            ];
        }

        echo json_encode($response);
    }

    /**
     * Table Bill Print to table color change
     */
    public function tableBillPrint($table_id) {
        $pasvalue = (($this->input->get('billPrint')) ? $this->input->get('billPrint') : 0);

        $this->db->where(['id' => $table_id])->update('sma_restaurant_tables', ['bill_printed' => $pasvalue]);
        return ($this->db->affected_rows()) ? TRUE : FALSE;
    }

    public function billPrint($table_id) {
        $getData = $this->pos_model->getBillData($table_id);


        $inv = $getData['inv'];
        $items = $getData['items'];
        $table = $getData['table'];

        $totalItems = $totalPrice = 0;
        $html = '<div style="text-align:center">';
        $html .= '<strong>' . $this->Settings->site_name . '</strong><br/>';
        $html .= '<strong>Table No: ' . $table->name . '</strong><br/>';
        $html .= '<strong>Bill No: ' . $inv->bill_no . '</strong><br/>';
//       $html .='<strong>Guests'. $table->seats.'</strong>';
        $html .= '</div>';
        $html .= '<table style="width:100%; text-align: left; border-collapse: collapse; border-left: 0; border-right: 0;" border="1" cellspecing="5" cellpadding="5">';
        $html .= '<thead><tr>';
        $html .= '<th>Product</th><th> Qty </th><th> Price </th><th> Total </th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        foreach ($items as $invItems) {
            $totalItems += $invItems->quantity;
            $totalPrice += $invItems->subtotal;
            $html .= '<tr>';
            $html .= '<td> ' . $invItems->product_name . '</td>';
            $html .= '<td> ' . $this->sma->formatDecimal($invItems->quantity) . '</td>';
            $html .= '<td> ' . $this->sma->formatDecimal(round($invItems->unit_price), 2) . '</td>';
            $html .= '<td> ' . $this->sma->formatDecimal(round($invItems->subtotal), 2) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody><tfoot>';
        $html .= '<tr>';
        $html .= '<th>Items</th>';
        $html .= '<th></th><th></th><th> ' . $totalItems . '(' . $totalItems . ')' . '</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>Total</th>';
        $html .= '<th></th><th></th><th> ' . $this->sma->formatDecimal($totalPrice, 2) . '</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>Rounding</th>';
        $html .= '<th></th><th></th><th> ' . $this->sma->formatDecimal((round($totalPrice) - $totalPrice), 2) . '</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>Grand Total</th>';
        $html .= '<th></th><th></th><th> ' . $this->sma->formatDecimal(round($totalPrice), 2) . '</th>';
        $html .= '</tr>';

//                $html .='<tr>';
//                    $html .='<td>Waiter</td>';
//                    $html .='<td colspan="3"></td>';
//                $html .='</tr>';
        $html .= '<tr>';
        $html .= '<td>Date and Time</td>';
        $html .= '<td colspan="3">' . date('d/m/Y g:i A') . '</td>';
        $html .= '</tr>';
        $html .= '</tfoot>';
        $html .= '</table>';

        echo $html;
    }

    /**
     * KOT Table Page
     */
    public function kot() {
        $this->data['user'] = $this->site->getUser();
        $this->data["tables_groups"] = $this->pos_model->getTableGroup($this->data['user']->table_assign);
        $this->data['tables'] = $this->site->getAllRestaurantTables();
        $this->load->view($this->theme . 'pos/kot', $this->data);
    }

    /**
     * Get UserToken
     */
    public function getKotToken() {
        $getToken = $this->db->order_by('id', 'DESC')->get('kot_log')->row();
        return $getToken->tokan;
    }

    public function updateToken() {
        $token_no = $_GET['TokenNo'];
        $getToken = $this->db->order_by('id', 'DESC')->get('kot_log')->row();
        $this->db->where(['id' => $getToken->id])->update('kot_log', [
            'tokan' => $token_no,
        ]);
    }

    /**
     * 
     * @return boolean
     */
    public function table_seats() {
        $tableId = $this->input->get('table_id');
        $seats = $this->input->get('seats');

        $data = $this->pos_model->tableSeats($tableId, ['seats' => $seats]);
        $response = array();
        if ($data) {
            $response = [
                'status' => TRUE,
                'seats' => $seats
            ];
        } else {
            $response = ['status' => false];
        }
        echo json_encode($response);
    }

    /**
     * Manage deposit Opening and closing Balance
     */
    public function depositOPCL() {
        $this->pos_model->depositCal();
    }

    /**
     * 
     * @return boolean
     */
    public function checkOPCLTrigger() {
        $this->db->where(['date' => date('Y-m-d')])->get('sma_customer_deposit_opening_balance')->row();
        if ($this->db->affected_rows()) {
            return TRUE;
        } else {
            $this->depositOPCL();
        }
    }
      public function Sale_print($sale_id = null) 
    {
        $this->data['myclass'] = $ci = & get_instance();
        $this->data['pos_settingss'] = $this->data['pos_settings'];
        $this->data['go_back'] = $_SERVER["HTTP_REFERER"];
        $this->sma->checkPermissions('index');
        if ($this->input->get('id')) {
            $sale_id = $this->input->get('id');
        }
        $_PID = $this->Settings->default_printer;

        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        $cfields = $this->site->getCustomeFieldsLabel('customer');
        $this->data['custome_fields'] = $cfields['customer'];
        $this->load->helper('text');
        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['message'] = $this->session->flashdata('message');
        $inv = $this->pos_model->getInvoiceByID($sale_id);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($sale_id, $inv->return_id);
        endif;

        $isGstSale = $this->site->isGstSale($sale_id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;
        //var_dump( $inv->GstSale);

        
        $print = array();
        $print['print_option'] = $this->site->defaultPrinterOption($_PID);
        $print['rows'] = $this->data['rows'] = $this->pos_model->getAllInvoiceItems($sale_id);
        $biller_id = $inv->biller_id;
        $customer_id = $inv->customer_id;
        $print['biller'] = $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
        $print['customer'] = $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);
        $print['payments'] = $this->data['payments'] = $this->pos_model->getInvoicePayments($sale_id);
        $print['pos'] = $this->data['pos'] = $this->pos_model->getSetting();
        unset($print['pos']->pos_theme);
        $print['barcode'] = $this->data['barcode'] = $this->barcode($inv->reference_no, 'code128', 30);
        $return_sales = $inv->return_id ? $this->pos_model->getAllReturnInvoiceByID($sale_id) : NULL;
        $product_discount = 0;
        $product_tax = 0;
        $total = 0;
        $grand_total = 0;
        $order_discount = 0;
        $order_tax = 0;
        $paid = 0;
        $rounding = 0;
        $ArrReturnId = array();
        $ReturnIds = '';
       

        $print['return_rows'] = $this->data['return_rows'] = $inv->return_id ? $this->pos_model->getAllReturnInvoiceItems($sale_id) : null;
        if (!empty($ArrReturnId)) {
            $print['return_payments'] = $this->data['return_payments'] = $this->data['return_sale'] ? $this->pos_model->getInvoicePayments1($ReturnIds) : null;
        }
        $print['inv'] = $this->data['inv'] = $inv;

        if ($inv->order_no) {
            $print['shipping_details'] = $this->data['shipping_details'] = $this->pos_model->getShipingDetails($inv->order_no);
        }

        $print['sid'] = $this->data['sid'] = $sale_id;
        $print['page_title'] = $this->data['page_title'] = $this->lang->line("invoice");
        $print['taxItems'] = $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($inv->id, $inv->return_id);
        $print['salestax'] = $this->data['salestax'] = $this->sales_model->getSalesItemsTaxes($sale_id); ///my code

        if (!empty($print['rows'])) {
            foreach ($print['rows'] as $key => $row) {
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $print['rows'][$key]->image = $product->image;
            }
        }

        $Settings = $this->Settings; 
        $print['pos_type'] = $Settings->pos_type;
        $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;
        $this->data['sms_limit'] = $this->sma->BalanceSMS();
        $this->load->view($this->theme . 'pos/print_sale', $this->data);

        
        $print['brcode'] = $this->sma->save_barcode($inv->reference_no, 'code128', 66, false);
        $print['qrcode'] = $this->sma->qrcode('link', urlencode(site_url('sales/view/' . $inv->id)), 2);
        $arr = explode("'", $print['brcode']);
        $print['brcode'] = $arr[1];
        $qrr = explode("'", $print['qrcode']);
        $print['qrcode'] = $qrr[1];
        foreach ($print['rows'] as $key => $row) {
            foreach ($row as $key2 => $value) {
                if ($key2 == 'quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'unit_quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'product_id') {
                    $product = $this->pos_model->getProductByID($value, $select = 'image');
                    $print['rows'][$key]->cf1 = $product->image;
                }
            }
        }
        if (( isset($_SESSION['print']) && $sale_id != $_SESSION['print']) && (isset($_SESSION['print_type']) && $_SESSION['print_type'] == null)) {
            $row_taxes_print = $inv->rows_tax;
            unset($inv->rows_tax);
            $row_taxes_print_arr = array();
            if (count($row_taxes_print)) {
                foreach ($row_taxes_print as $_key => $_data) {
                    foreach ($_data as $_key1 => $value1) {
                        $row_taxes_print_arr[] = $value1;
                    }
                }
            }
            $inv->rows_tax = $row_taxes_print_arr;
            ?>
<script>
window.MyHandler.setPrintRequest('<?php echo json_encode($print); ?>');
</script>
<?php
            unset($print);
        }
        $_SESSION['print'] = $sale_id;
    }
    // item return 
    public function returnsale($id = null) {
        // $this->sma->checkPermissions('return_sales');
        $this->data['pos_settingss'] = $this->data['pos_settings'];
        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); //get user information
        $warehouse_id   = $user_data->warehouse_id;
        if ($warehouse_id === null) {
            $warehouse_id = 0;
        }
        if (!$this->pos_settings->default_biller || !$this->pos_settings->default_customer || !$this->pos_settings->default_category) {
            $this->session->set_flashdata('warning', lang('please_update_settings'));
            redirect('pos/settings');
        }
        $this->form_validation->set_rules('customer', $this->lang->line("customer"), 'trim|required');
        $this->form_validation->set_rules('warehouse', $this->lang->line("warehouse"), 'required');
        $this->form_validation->set_rules('biller', $this->lang->line("biller"), 'required');
        
        $ids = $this->input->post('product_id');
        $grand_total = $this->input->post('grandtotal');
        $return_quantity = $this->input->post('return_quantity');
        $return_amounts = $this->input->post('return_amounts');
        $amount_paid = $this->input->post('amount-paid');
        $payment_reference_no = $this->input->post('payment_reference_no');
        $date = date('Y-m-d H:i:s');
        // $warehouse_id = $this->input->post('warehouse');
        $customer_id = $this->input->post('customer');
        $biller_id = $this->input->post('biller');
        $SalesPersonDetails = $this->input->post('pos_sale_person');
        $ExplodeSalesPerson = explode('-', $SalesPersonDetails);
        $SellerId = $ExplodeSalesPerson[0];
        $SellerName = $ExplodeSalesPerson[1];
        
        $customer_details = $this->site->getCompanyByID($customer_id);
        $customer = ($customer_details->company != '' && $customer_details->company != '-') ? $customer_details->company : $customer_details->name;
        $biller_details = $this->site->getCompanyByID($biller_id);
        $biller = ($biller_details->company != '-' && $biller_details->company != '') ? $biller_details->company : $biller_details->name;
        $SellerId = $ExplodeSalesPerson[0];
        $total = 0;
        $product_tax = 0;
        $order_tax = 0;
        $product_discount = 0;
        $order_discount = 0;
        $percentage = '%';
    
        $products = [];
        $si_return = [];
    
        $i = sizeof($_POST['product_id']);
        for ($r = 0; $r < $i; $r++) {
            
            $productIds = explode(',', $_POST['product_id'][$r]);
            $returnQuantities = explode(',', $_POST['return_quantity'][$r]);
            $return_subtotals = explode(',', $this->input->post('return_subtotal')[$r]);
            $return_netprices = explode(',', $this->input->post('return_netprice')[$r]);
            $item_discount = explode(',', $this->input->post('return_product_discount')[$r]);

            $return_product_taxes = explode(',', $this->input->post('return_product_tax')[$r]);
            $item_article_code = $_POST['article_code'][$r];
            $item_name = $_POST['product_name'][$r];
            $totalpayable = $_POST['totalpayable'][$r];
            $return_item_price = explode(',', $this->input->post('return_item_price')[$r]);
           
            if (count($productIds) == count($returnQuantities)) {
                foreach ($productIds as $index => $productId) {
                    $Products = $this->site->getProductsDataById($productId);

                    $item_mrp = !empty($item_mrp) ? $item_mrp : $Products->mrp;
                    $item_mrp = $this->sma->formatDecimal($item_mrp);
                    $pr_discount = 0;
                    $unit_discount = 0;
                    if (isset($item_discount)) {
                        $discount = $item_discount[$index];
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $unit_price = $return_item_price[$index];
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_price)) * (Float) ($pds[$index])) / 100), 4);
                        } else {
                            $unit_price = $return_item_price[$index];
                            $pr_discount = $this->sma->formatDecimal($discount, 4);
                        }
                    }
                  
                    $item_unit_quantity = $returnQuantities[$index];
                    $item_tax_rate = $return_product_taxes[$index];
                    $unit_discount = $pr_discount;
                   
                    $item_unit_price_less_discount = $this->sma->formatDecimal(($unit_price - $pr_discount), 4);
                    $unit_price = $this->sma->formatDecimal(($unit_price - $pr_discount), 4);
                   
                    $item_net_price = $unit_price;
                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $item_unit_quantity, 4);
                    
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $unit_tax = 0;
                    $item_tax = 0;
                    $tax = "";
                    $tax_method = '';
                    $net_unit_price = $item_unit_price_less_discount;
                    $unit_price = $item_unit_price_less_discount;
                    $invoice_unit_price = $item_unit_price_less_discount;
                    $invoice_net_unit_price = ($item_unit_price_less_discount + $unit_discount);

                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $tax_method = $Products->tax_method;
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($Products && $Products->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }
                        } elseif ($tax_details->type == 2) {

                            if ($Products && $Products->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $unit_tax = $item_tax;

                        $pr_item_tax = $this->sma->formatDecimal(($item_tax * $item_unit_quantity), 4);
                    }
                    
                    
                    $product_tax += $pr_item_tax;
                    $subtotal = $this->sma->formatDecimal((($item_net_price * $item_unit_quantity) + $pr_item_tax), 4);
                    $unit = $this->site->getUnitByID($Products->unit);
                    $unit_discount = 0 - $this->sma->formatDecimal($unit_discount, 4);
                    $unit_tax = 0 - $this->sma->formatDecimal($unit_tax, 4);
                    $invoice_unit_price = $this->sma->formatDecimal($invoice_unit_price, 4);
                    $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);
                    $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_unit_quantity), 4);
                    $net_price = $this->sma->formatDecimal(($item_mrp * $item_unit_quantity), 4);

                    $products[] = [
                        'product_id' => $productId,
                        'product_code' => $Products->code,
                        'product_name' => $Products->name,
                        'product_type' => $Products->type,
                        'article_code' => $Products->article_code,
                        'hsn_code'    => $Products->hsn_code,
                        'option_id'   => $item_option,
                        'net_unit_price' => $this->sma->remove_commas($net_unit_price),
                        'unit_price' => $this->sma->formatDecimal($item_net_price + $item_tax),
                        'quantity'   => $returnQuantities[$index],
                        'product_unit_id' => $Products->unit,
                        'product_unit_code' => $unit ? $unit->code : null,
                        'unit_quantity' => $returnQuantities[$index],
                        'warehouse_id' => $warehouse_id,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'discount'   => $this->sma->remove_commas($discount),  // This is the discount logic
                        'item_discount' => $this->sma->remove_commas($pr_item_discount),
                        'subtotal'   => $this->sma->remove_commas($subtotal),
                        'serial_no' => $item_serial,
                        'real_unit_price' => $Products->price,
                        'sale_item_id' => $sale_item_id,
                        'mrp' => $this->sma->remove_commas($Products->mrp),
                        'tax_method' => $Products->tax_method,
                        'unit_discount' => $unit_discount,
                        'unit_tax' => $unit_tax,
                        'invoice_unit_price' => $invoice_unit_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                        'net_price' => $net_price,
                        'seller_id' => $SellerId,
                        'seller' => $SellerName

                    ];
                    $si_return[] = array(
                        'product_id' => $productId,
                        'quantity' => $returnQuantities[$index],
                        'warehouse_id' => $warehouse_id,
                        'customer_id' => $customer_id,
                        'biller_id' => $biller_id,
                        'option_id' => $item_option
                    );
                    $total += $this->sma->formatDecimal(($item_net_price * $item_unit_quantity), 4);
                }
            }
        }
      
        // Order discount and tax calculation
        $order_discount_id = $this->input->post('order_discount');
        if ($order_discount_id) {
            $opos = strpos($order_discount_id, $percentage);
            if ($opos !== false) {
                $ods = explode("%", $order_discount_id);
                $order_discount = (($total + $product_tax) * (Float) ($ods[0])) / 100;
            } else {
                $order_discount = $this->sma->formatDecimal($order_discount_id, 4);
            }
        }
        $total_discount = $order_discount + $product_discount;
    
        if ($this->Settings->tax2) {
            $order_tax_id = $this->input->post('order_tax');
            if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                if ($order_tax_details->type == 2) {
                    $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
                }
                if ($order_tax_details->type == 1) {
                    $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
                }
            }
        } else {
            $order_tax_id = null;
        }
       
        $total_tax = $this->sma->formatDecimal($product_tax + $order_tax, 4);
        $grand_total = $this->sma->formatDecimal((($total + $total_tax) - ($order_discount)), 4);
        
        // $return_amounts = str_replace(',', '', $return_amounts);
        
        $data = array(
            'date' => $date,
            'reference_no' => $payment_reference_no,
            'return_sale_ref' => $payment_reference_no,
            'total' => $this->sma->remove_commas($return_amounts),
            'seller_id' => $SellerId,
            'customer_id' => $customer_id,
            'customer' => $customer_details->name,
            'biller_id' => $biller_id,
            'biller' => $biller_details->name,
            'warehouse_id' => $warehouse_id,
            'grand_total' => $this->sma->remove_commas($return_amounts),
            'order_tax_id' => $order_tax_id,
            'order_tax' => $order_tax,
            'total_discount' => $this->sma->remove_commas($total_discount),
            'total_tax' => $total_tax,
            'sale_status' => 'returned',
            'payment_status' => 'paid',
            'pos' => 1,
            'seller' => $SellerName,
            'note' => $note,
            'product_discount' => $this->sma->remove_commas($product_discount),
            'order_discount_id' => $order_discount_id,
            'order_discount' => $this->sma->remove_commas($order_discount),
            'total_discount' => $total_discount,
            'product_tax' => $product_tax,
            'order_tax' => $order_tax,
            'total_items' => $total_items,
            'payment_term' => $payment_term,
            'rounding' => $this->sma->remove_commas($rounding),
            'paid' => $this->sma->remove_commas(($return_amounts),4),
            'created_by' => $this->session->userdata('user_id'),
        );

       
        if (!isset($payment) || empty($payment)) {
            $payment = array();
        }

        $payment = array(
            'date' => $date,
            'reference_no' => $payment_reference_no,
            'amount' => $amount_paid,
            'paid_by' => 'Cash',
            'created_by' => $this->session->userdata('user_id'),
            'type' => 'returned',
        );
        $extrasPara = array(
            'sale_action' => 'sale_return',
        );
        if ($sale_id = $this->sales_model->addsale($data, $products, $payment, $si_return, $extrasPara)) {
            session_start();  // Start the session
            $_SESSION['flag'] = true;  // Set the session variable
            $this->session->set_flashdata('message', lang("Return_Sale_Added"));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        
    }
  public function getCustomerMobileNo($term = null) {
       
        $term = $this->input->get('term', true);
       if (strlen($term) < 1 || !$term) {
           die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
       }
       // $sql = "SELECT id, name, phone FROM sma_companies WHERE " . $retrun . " LIKE '%" . $term . "%'  limit 50";
       $sql = "
       SELECT c.id, c.name, c.phone ,COUNT(sb.id) AS suspended_bill_count
       FROM sma_companies c
       JOIN sma_suspended_bills sb ON c.id = sb.customer_id 
       WHERE sb.customer_id = '" . $term . "'
       LIMIT 50";
       $cmp = $this->db->query($sql)->result();
       foreach ($cmp as $row) {
           $data = $row;
       }
       echo json_encode($data);
   }
   public function getCustomerSuspendCount($term = null) {
    
       if (strlen($term) < 1 || !$term) {
           die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
       }
       $sql = "
       SELECT c.id, c.name, c.phone ,COUNT(sb.id) AS suspended_bill_count
       FROM sma_companies c
       JOIN sma_suspended_bills sb ON c.id = sb.customer_id 
       WHERE sb.customer_id = '" . $term . "'
       LIMIT 50";
       $cmp = $this->db->query($sql)->result();
       foreach ($cmp as $row) {
           $data = $row;
           return  $data;
       }
       return false;
   }
   public function checkCounter($term = null) {
       $term = $this->input->get('term', true);
       
       $this->db->select('*');
       $this->db->from('sma_suspended_bills');
       $this->db->where('reference_no', $term);
       $this->db->limit(50);
       $cmp = $this->db->get()->result();
       if ($cmp) {
            return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'success', 'data' => $cmp]));  // Include the data in the response
        }
       return $this->output
       ->set_content_type('application/json')
       ->set_output(json_encode(['status' => 'error', 'message' => 'No data found']));
   }
   public function checkCustomerdetails() {
        $this->load->model('companies_model');
        $scanValue =  $this->input->get('scanValue');
        $customer =  $this->input->get('term');
        $CounterNO = end(explode('/', $scanValue));  
        $customerId = explode('/', $scanValue)[0];
        $phoneNo = $this->pos_model->checkCustomerdetails($customer);
       
        if ($phoneNo) {
            $data = array('name' => $customer,
                'email' => '',
                'group_id' => '3',
                'group_name' => 'customer',
                'customer_group_id' => '1',
                'customer_group_name' => '',
                'price_group_id' => '',
                'price_group_name' => '',
                'company' => '',
                'address' => '',                              
                'gstn_no' => '',
                'city' => '',
                'state' => '',                
                'state_code'=>'',                
                'postal_code' => '',
                'country' => '',
                'phone' => $phoneNo,
                'pan_card'=>'',
                'gst_state_code'=>'',                      
            );
            $user_id = $this->session->userdata('user_id');
            if ($CounterNO === $user_id) {
                    $this->companies_model->updateCompany($customerId,$data);
                    $response['status'] = 'success';
                    return $response;
            }else{
                    $result = $this->companies_model->addCompany($data);
            }
                if ($result) {
                    $response['status'] = 'success';
                } else {
                    $response['status'] = 'success';
                }
        } else {
            $response['status'] = 'success';
        }
        return $response;
    }
    public function print_qrcode($customer_id) {
        $this->sma->checkPermissions('index');
        $this->data['suspend_data'] = $susdetails =  $this->pos_model->get_single_suspended_bill($customer_id); // Fetch the data
        $this->data['item_details'] = $suspenddata = $this->pos_model->getSuspendBillWithDetails($susdetails->id); 
        if (!empty($suspenddata)) {
            // $this->data['refNo'] = $suspend = $suspenddata[0];
            $this->data['customer_detail'] = $suspend = $suspenddata[0];
        } else {
            $this->data['item_detail'] = null;
        }
        $this->data['biller'] = $this->site->getCompanyByID($this->pos_settings->default_biller);
        $this->data['tables'] = $this->site->getAllRestaurantTables();
        $this->data['billers'] = $this->site->getAllCompanies('biller');
        $this->data['warehouses'] = $this->site->getAllWarehouses();
        $this->data['tax_rates'] = $this->pos_model->getTaxByCode($suspend->tax_rate_id);
        $this->data['tax_rates'] = $this->pos_model->getTaxByCode($susdetails->order_tax_id);
        $this->data['user'] = $this->site->getUser();
        $this->load->view($this->theme . 'pos/sus_print', $this->data);
    }
    public function customer_suspended_bills($per_page = 0) {
        $this->load->library('pagination');
        $scanValue = $this->input->get('scanValue'); //Reference number
        $parts = explode('/', $scanValue);
        $customerId = $parts[1]; // customer id
        $UserId= $parts[2]; 
      
        $customer = $this->site->getCompanyByID($customerId);

        //$this->table->set_heading('Id', 'The Title', 'The Content');
        if ($this->input->get('per_page')) {
            $per_page = $this->input->get('per_page');
        }

        $config['base_url'] = site_url('pos/customer_suspended_bills');
        $config['total_rows'] = $this->pos_model->bills_count();
        $config['per_page'] = 6;
        $config['num_links'] = 3;

        $config['full_tag_open'] = '<ul class="pagination pagination-sm">';
        $config['full_tag_close'] = '</ul>';
        $config['first_tag_open'] = '<li>';
        $config['first_tag_close'] = '</li>';
        $config['last_tag_open'] = '<li>';
        $config['last_tag_close'] = '</li>';
        $config['next_tag_open'] = '<li>';
        $config['next_tag_close'] = '</li>';
        $config['prev_tag_open'] = '<li>';
        $config['prev_tag_close'] = '</li>';
        $config['num_tag_open'] = '<li>';
        $config['num_tag_close'] = '</li>';
        $config['cur_tag_open'] = '<li class="active"><a>';
        $config['cur_tag_close'] = '</a></li>';

        $this->pagination->initialize($config);
        $data['r'] = true;
        $bills = $this->pos_model->fetch_bills($config['per_page'], $per_page, 1, $customerId);
        
        if (!empty($bills)) {
            $html = "";
            $html .= '<ul class="ob list-unstyled">'; // Using list-unstyled to remove default list styles
        
            foreach ($bills as $bill) {
                $susdelIcon = '';
                if ($this->data['Admin'] || $this->data['Owner'] || $this->data['GP']['sales-delete-suspended'] == 1) {
                    $susdelIcon = '<a delete-id="' . $bill->id . '" class="delete_suspend1" href="javascript:void(0);" style="float:right;"><i class="fa fa-trash" aria-hidden="true"></i></a>';
                }
                $pos_settingss = $this->data['pos_settings'];
                $ShowTokenText = '';
                if ($pos_settingss->display_token == 1) {
                    $ShowTokenText = 'Token No : ' . $bill->kot_tokan;
                }
                
                $html .= '<li>' .
                         '<input type="checkbox" class="bill-checkbox" id="checkbox_' . $bill->id . '" checked>' . // Checkbox added and checked by default
                         '<label for="checkbox_' . $bill->id . '" style="margin-left: 5px;"></label>' . // Label for the checkbox
                         '<button type="button" class="btn btn-info cust_sus_sale" id="' . $bill->id . '">' .
                         $ShowTokenText . 
                         '<p>' . $bill->suspend_note . '</p>' . 
                        //  '<strong>' . $bill->customer . '</strong><br>' . 
                         lang('reference_no') . ': ' . $bill->reference_no . '<br>' . 
                         lang('date') . ': ' . $bill->date . '<br>' . 
                         lang('items') . ': ' . $bill->count . '<br>' . 
                         lang('total') . ': ' . $this->sma->formatMoney($bill->total) . 
                         '</button>' . 
                         $susdelIcon . // Move the icon here
                         '</li>';
            }
            $html .= '</ul>';
        } else {
            $html = "<h3>" . lang('no_opeded_bill') . "</h3><p>&nbsp;</p>";
            $data['r'] = false;
        }
        
        $UserId = $this->getuserId($bill->id);
        $data['html'] = $html;
        $data['page'] = $this->pagination->create_links();
        $data['customer_name'] = $customer->name;
        // $data['id'] = $UserId->created_by;
        $data['id'] = $UserId;
        $data['billid'] = $bill->id;
        $data['bills_data'] = $bills;

        echo json_encode($data);
        // echo $this->load->view($this->theme . 'pos/customer_suspended_bills', $data, true);
    }
    public function loadSuspendScanItem($SuspendIds){

        $SuspendIds = $this->input->get('SuspendIds'); //suspend_ids againt only one customer
        if ($SuspendIds) {
          
            $suspended_sales = $this->pos_model->getOpenBillByIDs($SuspendIds);
            $inv_items = $this->pos_model->getSuspendedsSaleItems($SuspendIds);
            krsort($inv_items);
            $c = rand(100000, 9999999);
            foreach ($inv_items as $item) {
                $row = $this->site->getProductByID($item->product_id);
                if (!$row) {
                    $row = json_decode('{}');
                    $row->tax_method = 0;
                    $row->quantity = 0;
                } else {
                    $category = $this->site->getCategoryByID($row->category_id);
                    $row->category_name = $category->name;
                    unset($row->cost, $row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                }
                $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                if ($pis) {
                    foreach ($pis as $pi) {
                        $row->quantity += $pi->quantity_balance;
                    }
                }
                
                // $row->flag = is_numeric($sid) ? 'false': 'true';
                $row->suspendprice = 'suspendprice';
                $row->order_tax_id = $suspended_sales[0]->order_tax_id;
                $row->id = $item->product_id;
                $row->code = $item->product_code;
                $row->name = $item->product_name;
                $row->type = $item->product_type;
                // $row->quantity += floor($item->quantity);
                $row->quantity += ($item->quantity);

                $row->discount = $item->discount ? $item->discount : '0';
                $row->unit_price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                $row->unit_price = $row->tax_method ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                $row->real_unit_price = $item->real_unit_price + $item->item_discount;
                $row->base_quantity = $item->quantity;
                $row->base_unit = isset($row->unit) ? $row->unit : $item->product_unit_id;
                $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                $row->unit = $item->product_unit_id;
                // $row->qty = $item->unit_quantity;
                $row->qty = $item->quantity;
                $row->tax_rate = $item->tax_rate_id;
                $row->serial = $item->serial_no;
                $row->option = $item->option_id;
                $row->option_color = $item->shade_id;
                $row->note = $item->note;
                $row->invoice_unit_price = $item->invoice_unit_price;
                $options = $this->pos_model->getProductOptions($row->id, $item->warehouse_id, NULL, SIZE);
          
                $row->mrp = $item->mrp;
                $row->discount_on_mrp =  ($item->discount == 'Null' || $item->discount == '') ? "0%" : $item->discount ;
               
                if ($options) {

                    $option_quantity = 0;
                    foreach ($options as $option) {
                        
                        $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $item->option_id);
                      
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $option_quantity += $pi->quantity_balance;
                            }
                        }
                        if ($option->quantity > $option_quantity) {
                            $option->quantity = $option_quantity;
                        }
                    }
                }
                $options_color = $this->sales_model->getProductOptionsByAttr($row->id, $item->warehouse_id, COLOR);
                $combo_items = false;

                if ($row->type == 'combo') {
                    $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                }
                if ($row->type == 'Bundle') {
                    $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                }
                $categoryTax = $this->sales_model->getCategoryTax((($row->subcategory_id)?$row->subcategory_id :$row->category_id));
               
                if($categoryTax){
                    if($categoryTax->fix_tax_rate=='fix tax'){
                        $gettax =  $this->site->getTaxRateByID($categoryTax->tax_rate);
                        $fixtaxrate = $gettax->id.'~'.$gettax->rate;
                        $varaibletax = False;
                    } else {
                        $varaibletax = $this->sales_model->getVariableTax((($row->subcategory_id)?$row->subcategory_id :$row->category_id));
                        $fixtaxrate = False;
                    }
                }
              
                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                //$ri = $this->Settings->item_addition ? $row->id : $c;
                $ri = $this->Settings->item_addition ? (($item->option_id > 0) ? $row->id . $item->option_id : $row->id) : $c;
                if ($options) {
                    $ri = $row->id . '' . $row->option . '' . $row->option_color;
                }
                $row->suspendcount = count($suspended_sales);
                $pr[] = array('id' => $c, 'item_id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'fixtax'=>$fixtaxrate, 'category_tax'=>$varaibletax,
                    'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options, 'options_color' => $options_color, 'note' => $row->note, 'SuspendIds' => $SuspendIds);
                    $c++;
                
            }
           
            $this->data['ScanId'] = $suspended_sales[0];
            $this->data['items'] = json_encode($pr);
            $this->data['sid'] = $sid;
            $this->data['count'] = count($suspended_sales);
            $this->data['suspend_sale'] = $suspended_sale;
            $this->data['message'] = lang('suspended_sale_loaded');
            $this->data['customer'] = $this->pos_model->getCompanyByID($suspended_sale->customer_id);
            $this->data['reference_note'] = $suspended_sale->suspend_note;
            $this->data['table_id'] = 0; //$suspended_sale->table_id;  
            $this->sma->send_json($pr);  
               
        }else {
            redirect("pos");
        }
    }
    public function loadScanItem($sid){
        $scanVal = $this->input->get('scan_value');
        $sid = explode("/", $scanVal)[1];
        if ($sid) {
            if ($suspended_sales = $this->pos_model->getOpenBillByID($sid)) {
                $suspended_saleIds = []; // Use plural for clarity
                foreach ($suspended_sales as $suspended_sale) {
                    $suspended_saleIds[] = $suspended_sale->id; // Append each ID to the array
                }
                $inv_items = $this->pos_model->getSuspendedSaleItems($suspended_saleIds);
              
                krsort($inv_items);
                $c = rand(100000, 9999999);
                foreach ($inv_items as $item) {
                    $row = $this->site->getProductByID($item->product_id);
                    if (!$row) {
                        $row = json_decode('{}');
                        $row->tax_method = 0;
                        $row->quantity = 0;
                    } else {
                        $category = $this->site->getCategoryByID($row->category_id);
                        $row->category_name = $category->name;
                        unset($row->cost, $row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                    }
                    $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                    if ($pis) {
                        foreach ($pis as $pi) {
                            $row->quantity += $pi->quantity_balance;
                        }
                    }
                    
                    // $row->flag = is_numeric($sid) ? 'false': 'true';
                    $row->suspendprice = 'suspendprice';
                    $row->order_tax_id = $suspended_sales[0]->order_tax_id;
                    $row->id = $item->product_id;
                    $row->code = $item->product_code;
                    $row->name = $item->product_name;
                    $row->type = $item->product_type;
                    $row->quantity += $item->quantity;
                    $row->discount = $item->discount ? $item->discount : '0';
                    $row->price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                    $row->unit_price = $row->tax_method ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                    $row->real_unit_price = $item->real_unit_price;
                    $row->base_quantity = $item->quantity;
                    $row->base_unit = isset($row->unit) ? $row->unit : $item->product_unit_id;
                    $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                    $row->unit = $item->product_unit_id;
                    $row->qty = $item->unit_quantity;
                    $row->tax_rate = $item->tax_rate_id;
                    $row->serial = $item->serial_no;
                    $row->option = $item->option_id;
                    $row->option_color = $item->shade_id;
                    $row->note = $item->note;
                    $options = $this->pos_model->getProductOptions($row->id, $item->warehouse_id, NULL, SIZE);

                    if ($options) {
                        $option_quantity = 0;
                        foreach ($options as $option) {
                            $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $item->option_id);
                            if ($pis) {
                                foreach ($pis as $pi) {
                                    $option_quantity += $pi->quantity_balance;
                                }
                            }
                            if ($option->quantity > $option_quantity) {
                                $option->quantity = $option_quantity;
                            }
                        }
                    }
                    $options_color = $this->sales_model->getProductOptionsByAttr($row->id, $item->warehouse_id, COLOR);
                    $combo_items = false;
                    if ($row->type == 'combo') {
                        $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                    }
                    if ($row->type == 'Bundle') {
                        $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                    }
                    
                    $units = $this->site->getUnitsByBUID($row->base_unit);
                    $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                    //$ri = $this->Settings->item_addition ? $row->id : $c;
                    $ri = $this->Settings->item_addition ? (($item->option_id > 0) ? $row->id . $item->option_id : $row->id) : $c;
                    if ($options) {
                        $ri = $row->id . '' . $row->option . '' . $row->option_color;
                    }
                   
                    $row->suspendcount = count($suspended_sales);
                    $pr[] = array('id' => $c, 'item_id' => $row->id, 'label' => $row->name . " (" . $row->code . ")",
                        'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options, 'options_color' => $options_color, 'note' => $row->note);
                    $c++;
                    
                }
             
                $count = $this->getCustomerSuspendCount($sid);
                $this->data['count'] = ($count->suspended_bill_count);
                $this->data['phoneNo'] = ($count->phone);
                $this->data['items'] = json_encode($pr);
                $this->data['sid'] = $sid;
                $this->data['suspend_sale'] = $suspended_sale;
                // $this->data['message'] = lang('suspended_sale_loaded');
                $this->data['customer'] = $this->pos_model->getCompanyByID($suspended_sale->customer_id);
                $this->data['reference_note'] = $suspended_sale->suspend_note;
                $this->data['table_id'] = 0; //$suspended_sale->table_id;  
                $this->sma->send_json($pr);          
            } else {
                //$this->session->set_flashdata('error', lang("bill_x_found"));
                redirect("pos");
            }
        }else {
            redirect("pos");
        }
    }
    public function deleteSuspendForUnpaidInvoice($id = null) {
        $this->sma->checkPermissions('index');
        if ($this->pos_model->deleteBill($id)) {
            $this->session->set_flashdata('message', $this->lang->line("Cancel_Unpaid_Invoice_Sucessfully."));
            redirect('pos/index');
        }
    }
    public function getuserId($id = null) {
        $this->db->select('created_by');
        $this->db->from('sma_suspended_bills');
        $this->db->where('id', $id);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row;
            }
            return $data;
        }
        return FALSE;
    }

    
}