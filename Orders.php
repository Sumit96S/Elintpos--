<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Orders extends MY_Controller {

    public function __construct() {
        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->Supplier) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SEpRVER["HTTP_REFERER"]);
        }

        $this->lang->load('orders', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('sales_model');
        $this->load->model('orders_model');
        $this->digital_upload_path = 'files/'.$this->Customer_assets;
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        //$this->load->model('reports_model');
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';

        //$this->load->model('site_model');
        $this->pos_settings = $this->site->get_pos_setting();
        $this->pos_settings->pin_code = $this->pos_settings->pin_code ? md5($this->pos_settings->pin_code) : null;
        $this->data['pos_settings'] = $this->pos_settings;
        // $this->data['pos_settings']->pos_theme = json_decode($this->pos_settings->pos_theme);

        $this->data['logo'] = true;
    }

    public function index($warehouse_id = null) {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : null;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = $warehouse_id == null ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('orders')));
        $meta = array('page_title' => lang('orders'), 'bc' => $bc);
        $this->page_construct('orders/index', $meta, $this->data);
    }

    public function getOrders($warehouse_id = null) {
        $this->sma->checkPermissions('index');

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }
        $detail_link1 = anchor('pos/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('view_receipt'));
        $detail_link = anchor('orders/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('order_details'));
        $duplicate_link = anchor('orders/add?order_id=$1', '<i class="fa fa-plus-circle"></i> ' . lang('duplicate_order'));
        $payments_link = anchor('orders/payments/$1', '<i class="fa fa-money"></i> ' . lang('view_payments'), 'data-toggle="modal" data-target="#myModal"');
        $add_payment_link = anchor('orders/add_payment/$1', '<i class="fa fa-money"></i> ' . lang('add_payment'), 'data-toggle="modal" data-target="#myModal"');
        $add_delivery_link = anchor('orders/add_delivery/$1', '<i class="fa fa-truck"></i> ' . lang('add_delivery'), 'data-toggle="modal" data-target="#myModal"');
        $email_link = anchor('orders/email/$1', '<i class="fa fa-envelope"></i> ' . lang('email_order'), 'data-toggle="modal" data-target="#myModal"');
        $edit_link = anchor('orders/edit/$1', '<i class="fa fa-edit"></i> ' . lang('edit_order'), 'class="sledit"');
        $pdf_link = anchor('orders/pdf/$1', '<i class="fa fa-file-pdf-o"></i> ' . lang('download_pdf'));
        $return_link = anchor('orders/return_order/$1', '<i class="fa fa-angle-double-left"></i> ' . lang('return_order'));
        $delete_link = "<a href='#' class='po' title='<b>" . lang("delete_order") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('orders/delete/$1') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('delete_order') . "</a>";
        $action = '<div class="text-center"><div class="btn-group text-left">'
                . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">'
                . lang('actions') . ' <span class="caret"></span></button>
        <ul class="dropdown-menu pull-right" role="menu">
            <li>' . $detail_link1 . '</li>
            <li>' . $detail_link . '</li>
            <li>' . $duplicate_link . '</li>
            <li>' . $payments_link . '</li>
            <li class="link_$2">' . $add_payment_link . '</li>
            <li class="link_$2">' . $add_delivery_link . '</li>
            <li>' . $edit_link . '</li>
            <li>' . $pdf_link . '</li>
            <li>' . $email_link . '</li>
            <li class="link_$2">' . $return_link . '</li>
            <li>' . $delete_link . '</li>
        </ul>
    </div></div>';
        //$action = '<div class="text-center">' . $detail_link . ' ' . $edit_link . ' ' . $email_link . ' ' . $delete_link . '</div>';

        $this->load->library('datatables');
        $arrWr = [];
        if ($warehouse_id) {

            $this->datatables
                    ->select("id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no,invoice_no as invoice_no, biller, customer, sale_status, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, payment_status, attachment, return_id")
                    ->from('orders');

            $arrWr = explode(',', $warehouse_id);

            $this->datatables->where_in('warehouse_id', $arrWr);
        } else {
            $this->datatables
                    ->select("id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no,invoice_no as invoice_no, biller, customer, sale_status, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, payment_status, attachment, return_id")
                    ->from('orders');
        }
        //$this->datatables->where('pos =', 0); //->or_where('sale_status =', 'returned');


        if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        } elseif ($this->Customer) {
            $this->datatables->where('customer_id', $this->session->userdata('user_id'));
        }
        $this->datatables->add_column("Actions", $action, "id,order_status");

        echo $this->datatables->generate();
    }

    public function getWarehouseByUserId() {
        $user_value = $this->input->get('user_value') ? $this->input->get('user_value') : NULL;
        $user = $this->site->getUser($user_value);
        $Explode = explode(',', $user->warehouse_id);
        $ArrWarehouse = array();
        foreach ($Explode as $key) {
            $ResultWarehouse = $this->site->getWarehouseByID($key);
            $ArrWarehouse[] = array(
                $key => $ResultWarehouse->name,
            );
        }
        echo json_encode($ArrWarehouse);
    }

    /* ------------------------------------------------------------------ */

    public function add($quote_id = null) {
        $this->sma->checkPermissions();
        //$sale_id = $this->input->get('sale_id') ? $this->input->get('sale_id') : NULL;
        $chalan_id = $this->input->get('chalan_id') ? $this->input->get('chalan_id') : NULL;
        $order_id = $this->input->get('order_id') ? $this->input->get('order_id') : NULL;
        $sale_type = $this->input->get('sale_type') ? $this->input->get('sale_type') : NULL;
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('customer', lang("customer"), 'required');
        $this->form_validation->set_rules('biller', lang("biller"), 'required');
        $this->form_validation->set_rules('sale_status', lang("sale_status"), 'required');
        $this->form_validation->set_rules('sale_action', lang("sale_action"), 'required');
        $this->form_validation->set_rules('payment_status', lang("payment_status"), 'required');

        $Settings = $this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            // $this->form_validation->set_rules('patient_name',  'Patient Name', 'trim|required');
            // $this->form_validation->set_rules('doctor_name', 'Doctor Name' , 'trim|required');
        }


        if ($this->form_validation->run() == true) {

            $sale_action = $this->input->post('sale_action');

            $refKey = $sale_action == 'chalan' ? 'ordr' : 'so';

            $reference = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference($refKey);

            if ($this->Owner || $this->Admin || $this->GP['sales-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $warehouse_id = $this->input->post('warehouse');
            $customer_id = $this->input->post('customer');
            $biller_id = $this->input->post('biller');
            $total_items = $this->input->post('total_items');
            $sale_status = $this->input->post('sale_status');
            $payment_status = $this->input->post('payment_status');
            $payment_term = $this->input->post('payment_term');
            $due_date = $payment_term ? date('Y-m-d', strtotime('+' . $payment_term . ' days', strtotime($date))) : null;
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $customer_details = $this->site->getCompanyByID($customer_id);
            $customer = $customer_details->company != '-' ? $customer_details->company : $customer_details->name;
            $biller_details = $this->site->getCompanyByID($biller_id);
            $biller = $biller_details->company != '-' ? $biller_details->company : $biller_details->name;
            $note = $this->sma->clear_tags($this->input->post('note'));
            $staff_note = $this->sma->clear_tags($this->input->post('staff_note'));
            $quote_id = $this->input->post('quote_id') ? $this->input->post('quote_id') : null;
            $syncQuantity = $this->input->post('syncQuantity');
            $order_id = $this->input->post('order_id') ? $this->input->post('order_id') : null;
            $sale_type_input = $this->input->post('sale_type') ? $this->input->post('sale_type') : '';

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
            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            $sale_cgst = $sale_sgst = $sale_igst = 0;

            for ($r = 0; $r < $i; $r++) {
                $item_id = $_POST['product_id'][$r];
                $item_type = $_POST['product_type'][$r];

                $hsn_code = $_POST['hsn_code'][$r];
                $hsn_code = ($hsn_code == 'null') ? '' : $hsn_code;

                $item_code = $_POST['product_code'][$r];
                $item_name = $_POST['product_name'][$r];
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' && $_POST['product_option'][$r] != 'null' ? $_POST['product_option'][$r] : null;
                $real_unit_price = $_POST['real_unit_price'][$r];
                $unit_price = $item_unit_price = $_POST['unit_price'][$r];
                $item_unit_quantity = $_POST['quantity'][$r];
                $item_serial = isset($_POST['serial'][$r]) ? $_POST['serial'][$r] : '';
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                $item_unit = $_POST['product_unit'][$r];
                //$item_quantity = $_POST['product_base_quantity'][$r];
                $item_quantity = $_POST['quantity'][$r];
                $item_mrp = $_POST['mrp'][$r];

                if (isset($item_code) && isset($real_unit_price) && isset($item_unit_price) && isset($item_quantity)) {
                    $product_details = $item_type != 'manual' ? $this->sales_model->getProductByCode($item_code) : null;
                    $item_mrp = !empty($item_mrp) ? $item_mrp : $product_details->mrp;
                    $item_mrp = $this->sma->formatDecimal($item_mrp);

                    $pr_discount = 0;

                    if (isset($item_discount)) {
                        $discount = $item_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($real_unit_price)) * (Float) ($pds[0])) / 100), 4);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount, 4);
                        }
                    }
                    $unit_discount = $pr_discount;
                    $item_unit_price_less_discount = ($unit_price - $unit_discount);
                    //$item_unit_price_less_discount = $this->sma->formatDecimal($unit_price - $unit_discount); //17/05/19

                    $item_net_price = $item_unit_price_less_discount;
                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $item_unit_quantity);
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $item_tax = 0;
                    $tax = '';
                    $tax_method = $product_details->tax_method;
                    $invoice_net_unit_price = 0;

                    if (isset($item_tax_rate) && $item_tax_rate != 0) {

                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        $tax = $tax_details->rate . "%";
                        if ($tax_details->rate != 0) {
                            if ($tax_details->type == 1) {

                                if ($product_details && $product_details->tax_method == 1) {
                                    $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / 100, 4);

                                    $net_unit_price = $item_unit_price_less_discount;
                                    $unit_price = $item_unit_price_less_discount + $item_tax;

                                    $invoice_unit_price = $item_unit_price_less_discount;
                                    $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / (100 + $tax_details->rate), 4);

                                    $item_net_price = $item_unit_price_less_discount - $item_tax;

                                    $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                    $unit_price = $item_unit_price_less_discount;

                                    $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                    $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                                }
                            } elseif ($tax_details->type == 2) {

                                if ($product_details && $product_details->tax_method == 1) {
                                    $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / 100, 4);

                                    $net_unit_price = $item_unit_price_less_discount;
                                    $unit_price = $item_unit_price_less_discount + $item_tax;

                                    $invoice_unit_price = $item_unit_price_less_discount;
                                    $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / (100 + $tax_details->rate), 4);

                                    $item_net_price = $item_unit_price_less_discount - $item_tax;

                                    $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                    $unit_price = $item_unit_price_less_discount;

                                    $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                    $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                                }
                            }//end else.
                        } else {

                            $net_unit_price = $item_unit_price_less_discount;
                            $unit_price = $item_unit_price_less_discount;
                            $invoice_unit_price = $item_unit_price_less_discount;
                            $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                        }

                        $item_tax = $item_tax ? $item_tax : 0;
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);

                        $unit_tax = $item_tax;
                    } else {
                        $net_unit_price = $item_unit_price_less_discount;
                        $unit_price = $item_unit_price_less_discount;

                        $invoice_unit_price = $item_unit_price_less_discount;
                        $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                    }//end else

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

                    $product_tax += $pr_item_tax;
                    $subtotal = (($item_net_price * $item_unit_quantity) + $pr_item_tax);
                    $unit = $this->site->getUnitByID($item_unit);

                    $mrp = $item_mrp;
                    $invoice_unit_price = $this->sma->formatDecimal($invoice_unit_price, 4);
                    $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);
                    $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_quantity), 4);
                    $net_unit_price = $this->sma->formatDecimal($net_unit_price, 4);
                    $unit_price = $this->sma->formatDecimal($unit_price, 4);
                    $net_price = $this->sma->formatDecimal(($mrp * $item_quantity), 4);
                    $subtotal = $this->sma->formatDecimal(($unit_price * $item_quantity), 4);

                    $products[] = array(
                        'product_id' => $item_id,
                        'product_code' => $item_code,
                        'article_code' => $product_details->article_code,
                        'product_name' => $item_name,
                        'product_type' => $item_type,
                        'option_id' => $item_option,
                        'net_unit_price' => $item_net_price,
                        'unit_price' => $this->sma->formatDecimal($item_net_price + $item_tax),
                        'quantity' => $item_quantity,
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit ? $unit->code : NULL,
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
                        'mrp' => $item_mrp,
                        'hsn_code' => $hsn_code,
                        'delivery_status' => 'pending',
                        'pending_quantity' => $item_quantity,
                        'delivered_quantity' => 0,
                        'tax_method' => $tax_method,
                        'unit_discount' => $unit_discount,
                        'unit_tax' => $unit_tax,
                        'invoice_unit_price' => $invoice_unit_price,
                        'net_price' => $net_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                        'gst_rate' => $item_gst,
                        'cgst' => $item_cgst,
                        'sgst' => $item_sgst,
                        'igst' => $item_igst,
                    );

                    $sale_cgst += $item_cgst;
                    $sale_sgst += $item_sgst;
                    $sale_igst += $item_igst;

                    // $total += $this->sma->formatDecimal(($unit_price * $item_quantity), 4);
                    $total += $this->sma->formatDecimal(($item_net_price * $item_quantity), 4); //17/05/19
                }
            }
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }

            if ($this->input->post('order_discount')) {
                $order_discount_id = $this->input->post('order_discount');
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
                    } elseif ($order_tax_details->type == 1) {
                        $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
                    }
                }
            } else {
                $order_tax_id = null;
            }

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 4);
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping) - $order_discount), 4);
            $rounding = '';

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
                'due_date' => $due_date,
                'paid' => 0,
                'created_by' => $this->session->userdata('user_id'),
                'cgst' => $sale_cgst,
                'sgst' => $sale_sgst,
                'igst' => $sale_igst,
            );
            if ($payment_status == 'partial' || $payment_status == 'paid') {
                if ($this->input->post('paid_by') == 'deposit') {
                    if (!$this->site->check_customer_deposit($customer_id, $this->input->post('amount-paid'))) {
                        $this->session->set_flashdata('error', lang("amount_greater_than_deposit"));
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }
                if ($this->input->post('paid_by') == 'gift_card') {
                    $gc = $this->site->getGiftCardByNO($this->input->post('gift_card_no'));
                    $amount_paying = $grand_total >= $gc->balance ? $gc->balance : $grand_total;
                    $gc_balance = $gc->balance - $amount_paying;
                    $payment = array(
                        'date' => $date,
                        'reference_no' => $this->input->post('payment_reference_no'),
                        'amount' => $this->sma->formatDecimal($amount_paying),
                        'paid_by' => $this->input->post('paid_by'),
                        'cheque_no' => $this->input->post('cheque_no'),
                        'cc_no' => $this->input->post('gift_card_no'),
                        'cc_holder' => $this->input->post('pcc_holder'),
                        'cc_month' => $this->input->post('pcc_month'),
                        'cc_year' => $this->input->post('pcc_year'),
                        'cc_type' => $this->input->post('pcc_type'),
                        'created_by' => $this->session->userdata('user_id'),
                        'note' => $this->input->post('payment_note'),
                        'transaction_id' => $this->input->post('transaction_id'),
                        'type' => 'received',
                        'gc_balance' => $gc_balance,
                    );
                } else {
                    $payment = array(
                        'date' => $date,
                        'reference_no' => $this->input->post('payment_reference_no'),
                        'amount' => $this->sma->formatDecimal($this->input->post('amount-paid')),
                        'paid_by' => $this->input->post('paid_by'),
                        'cheque_no' => $this->input->post('cheque_no'),
                        'cc_no' => $this->input->post('pcc_no'),
                        'cc_holder' => $this->input->post('pcc_holder'),
                        'cc_month' => $this->input->post('pcc_month'),
                        'cc_year' => $this->input->post('pcc_year'),
                        'cc_type' => $this->input->post('pcc_type'),
                        'created_by' => $this->session->userdata('user_id'),
                        'note' => $this->input->post('payment_note'),
                        'transaction_id' => $this->input->post('transaction_id'),
                        'type' => 'received',
                    );
                }
            } else {
                $payment = array();
            }

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['attachment'] = $photo;
            }

            // $this->sma->print_arrays($data, $products, $payment);
        }

        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $patient_name = $this->input->post('patient_name');
            $patient_name = !empty($patient_name) ? $patient_name : '-';
            if ($patient_name):
                $data['cf1'] = $patient_name;
            endif;

            $doctor_name = $this->input->post('doctor_name');
            $doctor_name = !empty($doctor_name) ? $doctor_name : '-';
            if ($doctor_name):
                $data['cf2'] = $doctor_name;
            endif;
        }

        $extrasPara = array('sale_action' => $sale_action, 'syncQuantity' => $syncQuantity, 'order_id' => $order_id);

        if ($this->form_validation->run() == true && $sale_id = $this->sales_model->addSale($data, $products, $payment, array(), $extrasPara)) {
            $this->session->set_userdata('remove_slls', 1);
            if ($quote_id) {
                $this->db->update('quotes', array('status' => 'completed'), array('id' => $quote_id));
            }
            $this->session->set_flashdata('message', lang("sale_added"));
            if ($this->input->post('submit_type') == 'print') {
                /* ------ For checking Print/notPrint Button updated by SW 21/01/2017 --------------- */
                $print = $this->input->post('submit_type') == '' ? $this->input->post('submit_type') : 'print';
                $_SESSION['print_type'] = $print;
                $_SESSION['Sales'] = "Sales";
                /* ------ End For checking Print/notPrint Button updated by SW 21/01/2017 --------------- */
                if ($sale_action == 'chalan') {
                    redirect("pos/view_chalan/" . $sale_id);
                } else {
                    redirect("pos/view/" . $sale_id);
                }
            } else {
                $inv = $this->sales_model->getInvoiceByID($sale_id);

                if ($sale_type_input != '') {
                    redirect('sales/all_sale_lists');
                } else {
                    if ($sale_action == 'chalan') {
                        redirect('sales/challans');
                    } else {
                        redirect('orders');
                    }
                }
            }
        } else {

            $this->data['syncQuantity'] = 1;
            $this->data['saleAction'] = true;

            if ($order_id || $chalan_id) {
                if ($chalan_id) {
                    $this->data['quote'] = $this->orders_model->getOrderByID($chalan_id);
                    $items = $this->orders_model->getAllOrderItems($chalan_id);
                    $this->data['syncQuantity'] = 0;
                    $this->data['saleAction'] = false;
                    $this->data['order_id'] = $chalan_id;
                } elseif ($order_id) {
                    $this->data['quote'] = $this->orders_model->getOrderByID($order_id);
                    $items = $this->orders_model->getAllOrderItems($order_id);
                    $this->data['saleAction'] = false;
                    $this->data['order_id'] = $order_id;
                    $this->data['syncQuantity'] = 0;
                }
                krsort($items);
                $c = rand(100000, 9999999);
                foreach ($items as $item) {
                    $row = $this->site->getProductByID($item->product_id);
                    if (!$row) {
                        $row = json_decode('{}');
                        $row->tax_method = 0;
                    } else {
                        unset($row->cost, $row->details, $row->product_details, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                    }
                    $row->quantity = 0;
                    $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                    if ($pis) {
                        foreach ($pis as $pi) {
                            $row->quantity += $pi->quantity_balance;
                        }
                    }

                    $unitData = $this->sales_model->getUnitById($row->unit);
                    $row->unit_lable = $unitData->name;
                    $row->id = $item->product_id;
                    $row->code = $item->product_code;
                    $row->name = $item->product_name;
                    $row->type = $item->product_type;
                    $row->qty = $item->quantity;
                    $row->base_quantity = $item->quantity;
                    $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                    $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                    $row->unit = $item->product_unit_id;
                    $row->qty = $item->unit_quantity;
                    $row->discount = $item->discount ? $item->discount : '0';
                    $row->price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                    $row->unit_price = $row->tax_method ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                    $row->real_unit_price = $item->real_unit_price;
                    $row->tax_rate = $item->tax_rate_id;
                    $row->serial = '';
                    $row->option = $item->option_id;
                    $options = $this->sales_model->getProductOptions($row->id, $item->warehouse_id);
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
                    $units = $this->site->getUnitsByBUID($row->base_unit);
                    $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                    $ri = $this->Settings->item_addition ? $row->id : $c;

                    $pr[$ri] = array('id' => $c, 'item_id' => $row->id, 'image' => $row->image, 'label' => $row->name . " (" . $row->code . ")",
                        'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                    $c++;
                }
                $this->data['quote_items'] = json_encode($pr);
            }

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['sale_type'] = $sale_type_input ? $sale_type_input : $sale_type;
            $this->data['billers'] = $this->site->getAllCompanies('biller');
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            //$this->data['currencies'] = $this->sales_model->getAllCurrencies();
            $this->data['slnumber'] = ''; //$this->site->getReference('so');
            $this->data['payment_ref'] = ''; //$this->site->getReference('pay');
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('orders'), 'page' => lang('orders')), array('link' => '#', 'page' => lang('add_order')));
            $meta = array('page_title' => lang('add_sale'), 'bc' => $bc);
            $this->data['sale_action'] = $this->uri->segment(2);

            $this->page_construct('orders/add', $meta, $this->data);
        }
    }

    public function edit($id = null) {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $saleType = '';
        if ($this->uri->segment(4))
            $saleType = $this->uri->segment(4);
        $inv = $this->sales_model->getInvoiceByID($id);

        if ($inv->sale_status == 'returned' || $inv->return_id || $inv->return_sale_ref) {
            $this->session->set_flashdata('error', lang('sale_x_action'));
            redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
        }
        if (!$this->session->userdata('edit_right')) {
            $this->sma->view_rights($inv->created_by);
        }
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('customer', lang("customer"), 'required');
        $this->form_validation->set_rules('biller', lang("biller"), 'required');
        $this->form_validation->set_rules('sale_status', lang("sale_status"), 'required');
        $this->form_validation->set_rules('delivery_status', lang("delivery_status"), 'required');
        $this->form_validation->set_rules('payment_status', lang("payment_status"), 'required');

        if ($this->form_validation->run() == true) {

            $reference = $this->input->post('reference_no');
            if ($this->Owner || $this->Admin || $this->GP['sales-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = $inv->date;
            }
            $warehouse_id = $this->input->post('warehouse');
            $saleTypeInput = $this->input->post('saleType');
            $customer_id = $this->input->post('customer');
            $biller_id = $this->input->post('biller');
            $total_items = $this->input->post('total_items');
            $sale_status = $this->input->post('sale_status');
            $payment_status = $this->input->post('payment_status');
            $delivery_status = $this->input->post('delivery_status');
            $payment_term = $this->input->post('payment_term');
            $due_date = $payment_term ? date('Y-m-d', strtotime('+' . $payment_term . ' days', strtotime($date))) : null;
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $customer_details = $this->site->getCompanyByID($customer_id);
            $customer = $customer_details->company != '-' ? $customer_details->company : $customer_details->name;
            $biller_details = $this->site->getCompanyByID($biller_id);
            $biller = $biller_details->company != '-' ? $biller_details->company : $biller_details->name;
            $note = $this->sma->clear_tags($this->input->post('note'));
            $staff_note = $this->sma->clear_tags($this->input->post('staff_note'));

            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                $item_id = $_POST['product_id'][$r];
                $item_type = $_POST['product_type'][$r];
                $item_code = $_POST['product_code'][$r];

                $hsn_code = $_POST['hsn_code'][$r];
                $hsn_code = ($hsn_code == 'null') ? '' : $hsn_code;

                $item_name = $_POST['product_name'][$r];
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' && $_POST['product_option'][$r] != 'null' ? $_POST['product_option'][$r] : null;
                $real_unit_price = $this->sma->formatDecimal($_POST['real_unit_price'][$r]);
                $unit_price = $this->sma->formatDecimal($_POST['unit_price'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];
                $item_serial = isset($_POST['serial'][$r]) ? $_POST['serial'][$r] : '';
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                $item_unit = $_POST['product_unit'][$r];
                $item_quantity = $_POST['product_base_quantity'][$r];
                $item_mrp = $_POST['mrp'][$r];
                $item_cf1 = $_POST['cf1'][$r];
                $item_cf2 = $_POST['cf2'][$r];



                if (isset($item_code) && isset($real_unit_price) && isset($unit_price) && isset($item_quantity)) {
                    $product_details = $item_type != 'manual' ? $this->sales_model->getProductByCode($item_code) : null;
                    // $unit_price = $real_unit_price;
                    $item_mrp = !empty($item_mrp) ? $item_mrp : $product_details->mrp;
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
                    $unit_discount = $pr_discount;
                    $item_unit_price_less_discount = $this->sma->formatDecimal($unit_price - $unit_discount, 6);
                    $item_net_price = $net_unit_price = $item_unit_price_less_discount;


                    /* $unit_price = $this->sma->formatDecimal($unit_price - $pr_discount);
                      $item_net_price = $unit_price; */

                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $item_unit_quantity);
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $item_tax = 0;
                    $tax = "";
                    $net_unit_price = $item_unit_price_less_discount;
                    $unit_price = $item_unit_price_less_discount;
                    $invoice_unit_price = $item_unit_price_less_discount;
                    $invoice_net_unit_price = ($item_unit_price_less_discount + $unit_discount);

                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $tax_method = $product_details->tax_method;
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $unit_tax = $item_tax;
                        } elseif ($tax_details->type == 2) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                        $unit_tax = $item_tax;
                    }

                    $invoice_unit_price = $this->sma->formatDecimal($invoice_unit_price, 4);
                    $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);
                    $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_quantity), 4);
                    $product_tax += $pr_item_tax;
                    $subtotal = (($item_net_price * $item_unit_quantity) + $pr_item_tax);
                    $unit = $this->site->getUnitByID($item_unit);
                    $net_price = $this->sma->formatDecimal(($item_mrp * $item_quantity), 4);

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
                        'product_unit_code' => $unit->code,
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
                        'mrp' => $item_mrp,
                        'hsn_code' => $hsn_code,
                        'cf1' => $item_cf1,
                        'cf2' => $item_cf2,
                        'cf1_name' => 'Exp. Date',
                        'cf2_name' => 'Batch No.',
                        'net_price' => $net_price,
                        'tax_method' => $tax_method,
                        'unit_discount' => $unit_discount,
                        'unit_tax' => $unit_tax,
                        'invoice_unit_price' => $invoice_unit_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                    );

                    $total += $this->sma->formatDecimal(($item_net_price * $item_unit_quantity), 4);
                }
            }
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }
            if ($this->input->post('order_discount')) {
                $order_discount_id = $this->input->post('order_discount');
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

            /* 12-6-2019 */
            $rounding = '';

            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }
            /*             * **** */
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
                'delivery_status' => $delivery_status,
                'payment_status' => $payment_status,
                'payment_term' => $payment_term,
                'rounding' => $rounding,
                'due_date' => $due_date,
                'updated_by' => $this->session->userdata('user_id'),
                'updated_at' => date('Y-m-d H:i:s'),
            );

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['attachment'] = $photo;
            }

            // $this->sma->print_arrays($data, $products);
        }

        if ($this->form_validation->run() == true && $this->sales_model->updateSale($id, $data, $products)) {

            $this->session->set_userdata('remove_slls', 1);
            $this->session->set_flashdata('message', lang("sale_updated"));

            if ($saleTypeInput != '') {
                redirect('sales/all_sale_lists');
            } else {
                redirect('sales');
            }
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['inv'] = $this->sales_model->getInvoiceByID($id);
            if ($this->Settings->disable_editing) {
                if ($this->data['inv']->date <= date('Y-m-d', strtotime('-' . $this->Settings->disable_editing . ' days'))) {
                    $this->session->set_flashdata('error', sprintf(lang("sale_x_edited_older_than_x_days"), $this->Settings->disable_editing));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            }
            $inv_items = $this->sales_model->getAllInvoiceItems($id);
            krsort($inv_items);
            $c = rand(100000, 9999999);
            foreach ($inv_items as $item) {

                $row = $this->site->getProductByID($item->product_id);
                if (!$row) {
                    $row = json_decode('{}');
                    $row->tax_method = 0;
                    $row->quantity = 0;
                } else {
                    unset($row->cost, $row->details, $row->product_details, $row->barcode_symbology, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                }
                $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                if ($pis) {
                    foreach ($pis as $pi) {
                        $row->quantity += $pi->quantity_balance;
                    }
                }

                $unitData = $this->sales_model->getUnitById($row->unit);
                $row->unit_lable = $unitData->name;
                $row->id = $item->product_id;
                $row->code = $item->product_code;
                $row->name = $item->product_name;
                $row->type = $item->product_type;
                $row->base_quantity = $item->quantity;
                $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                $row->unit = $item->product_unit_id;
                $row->qty = $item->unit_quantity;
                $row->quantity += $item->quantity;
                $row->discount = $item->discount ? $item->discount : '0';
                $row->price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                $row->unit_price = ($row->tax_method ) ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                $row->real_unit_price = $item->real_unit_price;
                $row->tax_rate = $item->tax_rate_id;
                $row->serial = $item->serial_no;
                $row->option = $item->option_id;
                $row->delivery_status = $item->delivery_status;
                $row->delivered_qty = $item->delivered_quantity;
                $row->pending_qty = $item->pending_quantity;
                $row->net_unit_price = $item->net_unit_price;
                $options = $this->sales_model->getProductOptions($row->id, $item->warehouse_id);

                if ($options) {
                    $option_quantity = 0;
                    foreach ($options as $option) {
                        $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $item->option_id);
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $option_quantity += $pi->quantity_balance;
                            }
                        }
                        $option_quantity += $item->quantity;
                        if ($option->quantity > $option_quantity) {
                            $option->quantity = $option_quantity;
                        }
                    }
                }

                $combo_items = false;
                if ($row->type == 'combo') {
                    $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                    $te = $combo_items;
                    foreach ($combo_items as $combo_item) {
                        $combo_item->quantity = $combo_item->qty * $item->quantity;
                    }
                }
                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                $ri = $this->Settings->item_addition ? $row->id : $c;

                $pr[$ri] = array('id' => $c, 'item_id' => $row->id, 'image' => $row->image, 'label' => $row->name . " (" . $row->code . ")",
                    'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'cf1' => $row->cf1, 'cf2' => $row->cf2, 'units' => $units, 'options' => $options);
                $c++;
            }

            $this->data['inv_items'] = json_encode($pr);

            $this->data['id'] = $id;
            //$this->data['currencies'] = $this->site->getAllCurrencies();
            $this->data['billers'] = ($this->Owner || $this->Admin || !$this->session->userdata('biller_id')) ? $this->site->getAllCompanies('biller') : null;
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['sale_type'] = $saleTypeInput ? $saleTypeInput : $saleType;
            $this->data['sale_action'] = $this->uri->segment(2);
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('sales'), 'page' => lang('sales')), array('link' => '#', 'page' => lang('edit_sale')));
            $meta = array('page_title' => lang('edit_sale'), 'bc' => $bc);

            $this->page_construct('sales/edit', $meta, $this->data);
        }
    }

    /* ------------------------------- */

    
    public function return_order($id = null) {
        $this->sma->checkPermissions('return_sales');

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $orderType = '';
        if ($this->uri->segment(4)) {
            $orderType = $this->uri->segment(4);
        }
        $order = $this->orders_model->getOrderByID($id);

if($order->sale_invoice_no){
            $this->session->set_flashdata('error', lang("Sorry...! Sales has been created."));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($order->return_id) {
            if ($this->Settings->sale_multiple_return_edit == 0) {
                $this->session->set_flashdata('error', lang("order_already_returned"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        }

        $customer_details = $this->site->getCompanyByID($order->customer_id);
        $biller_details = $this->site->getCompanyByID($order->biller_id);

        if ((!empty($customer_details->state_code) && !empty($biller_details->state_code)) && $customer_details->state_code != $biller_details->state_code) {
            $interStateTax = true;
        } else {
            $interStateTax = false;
        }

        $this->form_validation->set_rules('return_surcharge', lang("return_surcharge"), 'required');

        if ($this->form_validation->run() == true) {

            $reference = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('re_ordr');
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }

            $return_surcharge = $this->input->post('return_surcharge') ? $this->input->post('return_surcharge') : 0;
            $note = $this->sma->clear_tags($this->input->post('note'));

            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $order_cgst = $order_sgst = $order_igst = 0;

            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                if ($_POST['quantity'][$r] > 0) {
                    $item_id = $_POST['product_id'][$r];
                    $item_type = $_POST['product_type'][$r];
                    $item_code = $_POST['product_code'][$r];
                    $item_name = $_POST['product_name'][$r];
                    $order_item_id = $_POST['sale_item_id'][$r];
                    $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' ? $_POST['product_option'][$r] : null;
                    $real_unit_price = $this->sma->formatDecimal($_POST['real_unit_price'][$r]);
                    $unit_price = $this->sma->formatDecimal($_POST['unit_price'][$r]);
//                    $item_unit_quantity = (0 - $_POST['quantity'][$r]);
                    
                    $item_unit_quantity = (0 - $_POST['product_base_quantity'][$r]);
                    $item_unit_quantity_new = (0 - $_POST['quantity'][$r]);
                    $item_serial = isset($_POST['serial'][$r]) ? $_POST['serial'][$r] : '';
                    $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                    $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                    $item_unit = $_POST['product_unit'][$r];
                    $item_quantity = (0 - $_POST['product_base_quantity'][$r]);
                    $item_mrp = $_POST['mrp'][$r];
                    if (isset($item_code) && isset($real_unit_price) && isset($unit_price) && isset($item_quantity)) {
                        $product_details = $item_type != 'manual' ? $this->sales_model->getProductByCode($item_code) : null;
                        // $unit_price = $real_unit_price;
                        $item_mrp = !empty($item_mrp) ? $item_mrp : $product_details->mrp;
                        $item_mrp = $this->sma->formatDecimal($item_mrp);
                        $pr_discount = 0;
                        $unit_discount = 0;

                        if (isset($item_discount)) {
                            $discount = $item_discount;
                            $dpos = strpos($discount, $percentage);
                            if ($dpos !== false) {
                                $pds = explode("%", $discount);
                                $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_price)) * (Float) ($pds[0])) / 100), 4);
                            } else {
                                $pr_discount = $this->sma->formatDecimal($discount, 4);
                            }
                        }
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
                            $tax_method = $product_details->tax_method;
                            $pr_tax = $item_tax_rate;
                            $tax_details = $this->site->getTaxRateByID($pr_tax);
                            if ($tax_details->type == 1 && $tax_details->rate != 0) {

                                if ($product_details && $product_details->tax_method == 1) {
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

                                if ($product_details && $product_details->tax_method == 1) {
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

                        $product_tax += $pr_item_tax;
                        $subtotal = $this->sma->formatDecimal((($item_net_price * $item_unit_quantity) + $pr_item_tax), 4);
                        $unit = $this->site->getUnitByID($item_unit);

                        $unit_discount = 0 - $this->sma->formatDecimal($unit_discount, 4);
                        $unit_tax = 0 - $this->sma->formatDecimal($unit_tax, 4);
                        $invoice_unit_price = $this->sma->formatDecimal($invoice_unit_price, 4);
                        $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);
                        $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_unit_quantity), 4);
                        $net_price = $this->sma->formatDecimal(($item_mrp * $item_unit_quantity), 4);


                        $products[] = array(
                            'product_id' => $item_id,
                            'product_code' => $item_code,
                            'product_name' => $item_name,
                            'product_type' => $item_type,
                            'article_code' => $product_details->article_code,
                            'hsn_code' => $product_details->hsn_code,
                            'option_id' => $item_option,
                            'net_unit_price' => $item_net_price,
                            'unit_price' => $this->sma->formatDecimal($item_net_price + $item_tax),
                            'quantity' => $item_quantity,
                            'product_unit_id' => $item_unit,
                            'product_unit_code' => $unit->code,
                            'unit_quantity' => $item_unit_quantity_new,
                            'warehouse_id' => $order->warehouse_id,
                            'item_tax' => $pr_item_tax,
                            'tax_rate_id' => $pr_tax,
                            'tax' => $tax,
                            'discount' => $item_discount,
                            'item_discount' => $pr_item_discount,
                            'subtotal' => $this->sma->formatDecimal($subtotal),
                            'serial_no' => $item_serial,
                            'real_unit_price' => $real_unit_price,
                            'sale_item_id' => $order_item_id,
                            'mrp' => $item_mrp,
                            'tax_method' => $tax_method,
                            'unit_discount' => $unit_discount,
                            'unit_tax' => $unit_tax,
                            'invoice_unit_price' => $invoice_unit_price,
                            'invoice_net_unit_price' => $invoice_net_unit_price,
                            'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                            'net_price' => $net_price,
                            'gst_rate' => $item_gst,
                            'cgst' => $item_cgst,
                            'sgst' => $item_sgst,
                            'igst' => $item_igst,
                        );

                        $si_return[] = array(
                            'id' => $order_item_id,
                            'sale_id' => $id,
                            'product_id' => $item_id,
                            'option_id' => $item_option,
                            'quantity' => (0 - $item_unit_quantity_new),
                            'warehouse_id' => $order->warehouse_id,
                        );

                        $order_cgst += $item_cgst;
                        $order_sgst += $item_sgst;
                        $order_igst += $item_igst;

                        $total += $this->sma->formatDecimal(($item_net_price * $item_unit_quantity), 4);
                    }
                }
            }
            

            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }

            if ($this->input->post('discount')) {
                $order_discount_id = $this->input->post('discount');
                /* $opos = strpos($order_discount_id, $percentage);
                  if ($opos !== false) {
                  $ods = explode("%", $order_discount_id);
                  $order_discount = $this->sma->formatDecimal(((($total + $product_tax) * (Float) ($ods[0])) / 100), 4);
                  } else {
                  $order_discount = $this->sma->formatDecimal($order_discount_id, 4);
                  } */
            } else {
                $order_discount_id = null;
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
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($return_surcharge) - $order_discount), 4);
            $rounding = '';
            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }
            $data = ['date' => $date,
                'sale_id' => $id,
                'eshop_sale' => $order->eshop_sale,
                'invoice_no' => $order->invoice_no,
                'sale_as_chalan' => $order->sale_as_chalan,
                'reference_no' => $order->reference_no,
                'seller_id' => $order->seller_id,
                'seller' => $order->seller,
                'customer_id' => $order->customer_id,
                'customer' => $order->customer,
                'biller_id' => $order->biller_id,
                'biller' => $order->biller,
                'warehouse_id' => $order->warehouse_id,
                'note' => $note,
                'total' => $total,
                'product_discount' => $product_discount,
                'order_discount_id' => $order_discount_id,
                'order_discount' => $order_discount,
                'total_discount' => $total_discount,
                'product_tax' => $product_tax,
                'order_tax_id' => $order_tax_id,
                'order_tax' => $order_tax,
                'total_tax' => $total_tax,
                'surcharge' => $this->sma->formatDecimal($return_surcharge),
                'grand_total' => $grand_total,
                'created_by' => $this->session->userdata('user_id'),
                'return_sale_ref' => $reference,
                'rounding' => $rounding,
                'sale_status' => 'returned',
                'payment_status' => $order->payment_status == 'paid' ? 'due' : 'pending',
                'total_items' => (0 - ($this->input->post('total_items'))),
                'cgst' => $order_cgst,
                'sgst' => $order_sgst,
                'igst' => $order_igst,
            ];
            if ($this->input->post('amount-paid') && $this->input->post('amount-paid') > 0) {
                $pay_ref = $this->input->post('payment_reference_no') ? $this->input->post('payment_reference_no') : $this->site->getReference('pay');
                /* 9-11-2019 Add paid amount to giftcard and Deposit */
                $amount_paying = $grand_total >= $gc->balance ? $gc->balance : $grand_total;
                $amount = $this->input->post('amount-paid') ? $this->input->post('amount-paid') : 0;

                $gc = $this->site->getGiftCardByNO($this->input->post('gift_card_no')); //Gift Card Balance
                $gc_balance = $gc->balance + $amount; //Add Amount To gift card balance 
                $desposit = $this->site->customerDepositAmt($order->customer_id); //Deposit balance
                $deposit_balance = $desposit + $amount; //Add Amount To Deposit balance 

                $pos_paid = $this->input->post('pospaid') ? $this->input->post('pospaid') : 0;
                $pos_balance = $this->input->post('posbalance') ? $this->input->post('posbalance') : $this->input->post('posbalance');
                /* end */

                if ($this->input->post('paid_by') == 'deposit') {

                    $payment = array(
                        'date' => $date,
                        'reference_no' => $pay_ref,
                        'amount' => (0 - $this->input->post('amount-paid')),
                        'paid_by' => $this->input->post('paid_by'),
                        'cheque_no' => $this->input->post('cheque_no'),
                        'cc_no' => $cc_no,
                        'cc_holder' => $deposit_balance,
                        'cc_month' => $this->input->post('pcc_month'),
                        'cc_year' => $this->input->post('pcc_year'),
                        'cc_type' => $this->input->post('pcc_type'),
                        'created_by' => $this->session->userdata('user_id'),
                        'pos_paid' => $pos_paid,
                        'pos_balance' => $pos_balance,
                        'type' => 'returned',
                    );
                } else if ($this->input->post('paid_by') == 'gift_card') {
                    $cc_no = $this->input->post('gift_card_no');
                    $payment = array(
                        'date' => $date,
                        'reference_no' => $pay_ref,
                        'amount' => (0 - $this->input->post('amount-paid')),
                        'paid_by' => $this->input->post('paid_by'),
                        'cheque_no' => $this->input->post('cheque_no'),
                        'cc_no' => $cc_no,
                        'cc_holder' => $gc_balance,
                        'cc_month' => $this->input->post('pcc_month'),
                        'cc_year' => $this->input->post('pcc_year'),
                        'cc_type' => $this->input->post('pcc_type'),
                        'created_by' => $this->session->userdata('user_id'),
                        'pos_paid' => $pos_paid,
                        'pos_balance' => $pos_balance,
                        'type' => 'returned',
                        'gc_balance' => $gc_balance,
                    );
                } else {
                    $cc_no = $this->input->post('pcc_no');
                    $payment = array(
                        'date' => $date,
                        'reference_no' => $pay_ref,
                        'amount' => (0 - $this->input->post('amount-paid')),
                        'paid_by' => $this->input->post('paid_by'),
                        'cheque_no' => $this->input->post('cheque_no'),
                        'cc_no' => $cc_no,
                        'cc_holder' => $this->input->post('pcc_holder'),
                        'cc_month' => $this->input->post('pcc_month'),
                        'cc_year' => $this->input->post('pcc_year'),
                        'cc_type' => $this->input->post('pcc_type'),
                        'created_by' => $this->session->userdata('user_id'),
                        'pos_paid' => $pos_paid,
                        'pos_balance' => $pos_balance,
                        'type' => 'returned',
                    );
                }

                $data['payment_status'] = $grand_total == $this->input->post('amount-paid') ? 'paid' : 'partial';
            } else {
                $payment = array();
            }

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['attachment'] = $photo;
            }

            // $this->sma->print_arrays($data, $products, $si_return, $payment);
        }

        if ($order->sale_as_chalan) {
            $sale_action = 'chalan';
            $syncQuantity = 1;
        } else {
            $sale_action = 'order';
            $syncQuantity = 0;
        }

        $extrasPara = array('sale_action' => $sale_action, 'syncQuantity' => $syncQuantity, 'order_id' => $order->id);

        if ($this->form_validation->run() == true && $this->orders_model->addOrder($data, $products, $payment, $si_return, $extrasPara)) {
            $this->session->set_flashdata('message', lang("return_order_added"));

            /* ------------------------- Revert reward Point on  return---------------------------- */
            $order = $this->orders_model->getOrderByID($id);
            $company = $this->site->getCompanyByID($order->customer_id);

            redirect('sales/challans');
        } 
        
        else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['inv'] = $order;
            if ($this->data['inv']->sale_status != 'completed') {
                $this->session->set_flashdata('error', lang("order_status_x_competed"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
            
            if ($this->Settings->disable_editing) {
                if ($this->data['inv']->date <= date('Y-m-d', strtotime('-' . $this->Settings->disable_editing . ' days'))) {
                    $this->session->set_flashdata('error', lang("order_x_edited_older_than_3_months"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            }            
                 
            $inv_items = $this->orders_model->getAllOrderItems($id);
            $payment = $this->orders_model->getOrderPayments($id);

            krsort($inv_items);
            $c = rand(100000, 9999999);
            foreach ($inv_items as $item) {
                $ReturnQty = 0;
                $ReturnSaleRow = $this->orders_model->getAllReturnOrderItemByItemID($item->id);
                if (!empty($ReturnSaleRow)) {
                    foreach ($ReturnSaleRow as $keys => $val) {
                        $ReturnQty = $ReturnQty + $val['quantity'];
                    }
                }
                $UnitQty = $item->unit_quantity + $ReturnQty;
                if ($UnitQty != 0) {
                    $row = $this->site->getProductByID($item->product_id);
                    if (!$row) {
                        $row = json_decode('{}');
                        $row->tax_method = 0;
                        $row->quantity = 0;
                    } else {
                        unset($row->cost, $row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                    }
                    $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                    
                    if ($pis) {
                        foreach ($pis as $pi) {
                            $row->quantity += $pi->quantity_balance;
                        }
                    }
                    
                    $row->id = $item->product_id;
                    $row->sale_item_id = $item->id;
                    $row->code = $item->product_code;
                    $row->name = $item->product_name;
                    $row->type = $item->product_type;
                    $row->base_quantity =$item->quantity; // $UnitQty; //
                    $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                    $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                    $row->unit = $item->product_unit_id;

                    $row->qty = $item->unit_quantity + $ReturnQty;
                    $row->oqty = $item->unit_quantity + $ReturnQty;
                    $row->quantity = $item->quantity + $ReturnQty;
                    
                    $row->discount = $item->discount ? $item->discount : '0';
                    //$row->price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                     
                    $row->unit_price = $row->tax_method ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                    $row->real_unit_price = $item->real_unit_price;
                    $row->tax_rate = $item->tax_rate_id;
                    $row->serial = $item->serial_no;
                    $row->option = $item->option_id;
                    $row->rounding = ($item->rounding) ? $item->rounding : 0;
                    $options = $this->sales_model->getProductOptions($row->id, $item->warehouse_id, true);
                    $units = $this->site->getUnitsByBUID($row->base_unit);
                    $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                    
                    
                    
                    $row_id = $row->id . $row->option;
                    
                    $ri = $this->Settings->item_addition ? $row_id : $c;

                    $pr[$ri] = array('id' => $c, 'item_id' => $row_id, 'label' => $row->name . " (" . $row->code . ")", 'row' => $row, 'units' => $units, 'tax_rate' => $tax_rate, 'options' => $options);
                    $c++;
                }
            }
            $this->data['sale_type'] = $orderTypeInput ? $orderTypeInput : $orderType;
            $this->data['inv_items'] = json_encode($pr);
            $this->data['id'] = $id;
            $this->data['payment_ref'] = '';
            $this->data['reference'] = ''; // $this->site->getReference('re');
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['payment'] = $payment->paid_by;
            $this->data['cc_no'] = $payment->cc_no;
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('orders'), 'page' => lang('order')), array('link' => '#', 'page' => lang('return_order')));
            $meta = array('page_title' => lang('return_order'), 'bc' => $bc);
            $this->page_construct('orders/return_order', $meta, $this->data);
        }
    }


    /* ------------------------------------------------------------------------ */

    public function modal_view($id = null) {
        $this->sma->checkPermissions('index', true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->sales_model->getInvoiceByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view && !empty($inv->return_id)):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['created_by'] = $this->site->getUser($inv->created_by);
        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->sales_model->getAllInvoiceItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getInvoiceByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllInvoiceItems($inv->return_id) : NULL;
        $Settings = $this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->load->view($this->theme . 'sales/modal_view_pharma', $this->data);
        } else {
            $this->load->view($this->theme . 'sales/modal_view', $this->data);
        }
    }

    public function view($id = null) {
        $this->sma->checkPermissions('index');

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->sales_model->getInvoiceByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }


        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->sales_model->getPaymentsForSale($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);

        $this->data['created_by'] = $this->site->getUser($inv->created_by);

        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->sales_model->getAllInvoiceItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getInvoiceByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllInvoiceItems($inv->return_id) : NULL;


        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('sales'), 'page' => lang('sales')), array('link' => '#', 'page' => lang('view')));
        $meta = array('page_title' => lang('view_sales_details'), 'bc' => $bc);
        $Settings = $this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->page_construct('sales/view-sales-pharma', $meta, $this->data);
        } else {
            $this->page_construct('sales/view', $meta, $this->data);
        }
    }

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

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        $html = $this->load->view($this->theme . 'sales/pdf', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }


        if ($view) {
            $this->load->view($this->theme . 'sales/pdf', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    public function combine_pdf($orders_id) {
        $this->sma->checkPermissions('pdf');

        foreach ($orders_id as $id) {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $inv = $this->orders_model->getOrderByID($id);
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
            $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
            $this->data['payments'] = $this->orders_model->getPaymentsForOrder($id);
            $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
            $this->data['user'] = $this->site->getUser($inv->created_by);
            $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
            $this->data['inv'] = $inv;
            $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
            $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
            $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;
            $html_data = $this->load->view($this->theme . 'orders/pdf', $this->data, true);
            if (!$this->Settings->barcode_img) {
                $html_data = preg_replace("'\<\?xml(.*)\?\>'", '', $html_data);
            }

            $html[] = array(
                'content' => $html_data,
                'footer' => $this->data['biller']->invoice_footer,
            );
        }

        $name = lang("orders") . ".pdf";
        $this->sma->generate_pdf($html, $name);
    }

    /* 11-25-2018 Combine Invoice Pdf on Sales list */

    public function combine_invoice_pdf($orders_id) {
        $this->sma->checkPermissions('pdf');

        foreach ($orders_id as $id) {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $inv = $this->orders_model->getOrderByID($id);
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $this->sma->checkPermissions('index');

            $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
            $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
            $this->data['payments'] = $this->orders_model->getPaymentsForOrder($id);
            $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);

            $this->data['created_by'] = $this->site->getUser($inv->created_by);

            $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
            $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
            $this->data['inv'] = $inv;
            $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
            $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
            $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;


            $_PID = $this->Settings->default_printer;
            $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
            if ($this->data['default_printer']->tax_classification_view):
                $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
            endif;
            $this->data['taxItems'] = $this->orders_model->getAllTaxItemsGroup($id, $inv->return_id);
            //print_r($this->data['rows']);

            $html_data = $this->load->view($this->theme . 'orders/view_invoice', $this->data, true);
            if (!$this->Settings->barcode_img) {
                $html_data = preg_replace("'\<\?xml(.*)\?\>'", '', $html_data);
            }

            $html[] = array(
                'content' => $html_data,
                'footer' => $this->data['biller']->invoice_footer,
            );
        }

        $name = lang("orders") . ".pdf";
        $this->sma->generate_pdf($html, $name);
    }

    /**/

    public function email($id = null) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $inv = $this->sales_model->getInvoiceByID($id);
        $this->form_validation->set_rules('to', lang("to") . " " . lang("email"), 'trim|required|valid_email');
        $this->form_validation->set_rules('subject', lang("subject"), 'trim|required');
        $this->form_validation->set_rules('cc', lang("cc"), 'trim|valid_emails');
        $this->form_validation->set_rules('bcc', lang("bcc"), 'trim|valid_emails');
        $this->form_validation->set_rules('note', lang("message"), 'trim');

        if ($this->form_validation->run() == true) {
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $to = $this->input->post('to');
            $subject = $this->input->post('subject');
            if ($this->input->post('cc')) {
                $cc = $this->input->post('cc');
            } else {
                $cc = null;
            }
            if ($this->input->post('bcc')) {
                $bcc = $this->input->post('bcc');
            } else {
                $bcc = null;
            }
            $customer = $this->site->getCompanyByID($inv->customer_id);
            $biller = $this->site->getCompanyByID($inv->biller_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->reference_no,
                'contact_person' => $customer->name,
                'company' => $customer->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
            );
            $msg = $this->input->post('note');
            $message = $this->parser->parse_string($msg, $parse_data);
            $paypal = $this->sales_model->getPaypalSettings();
            $skrill = $this->sales_model->getSkrillSettings();
            $btn_code = '<div id="payment_buttons" class="text-center margin010">';
            if ($paypal->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_my / 100);
                } else {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_other / 100);
                }
                $btn_code .= '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=' . $paypal->account_email . '&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&image_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $paypal_fee) . '&no_shipping=1&no_note=1&currency_code=' . $this->default_currency->code . '&bn=FC-BuyNow&rm=2&return=' . site_url('sales/view/' . $inv->id) . '&cancel_return=' . site_url('sales/view/' . $inv->id) . '&notify_url=' . site_url('payments/paypalipn') . '&custom=' . $inv->reference_no . '__' . ($inv->grand_total - $inv->paid) . '__' . $paypal_fee . '"><img src="' . base_url('assets/images/btn-paypal.png') . '" alt="Pay by PayPal"></a> ';
            }
            if ($skrill->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_my / 100);
                } else {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_other / 100);
                }
                $btn_code .= ' <a href="https://www.moneybookers.com/app/payment.pl?method=get&pay_to_email=' . $skrill->account_email . '&language=EN&merchant_fields=item_name,item_number&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&logo_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $skrill_fee) . '&return_url=' . site_url('sales/view/' . $inv->id) . '&cancel_url=' . site_url('sales/view/' . $inv->id) . '&detail1_description=' . $inv->reference_no . '&detail1_text=Payment for the sale invoice ' . $inv->reference_no . ': ' . $inv->grand_total . '(+ fee: ' . $skrill_fee . ') = ' . $this->sma->formatMoney($inv->grand_total + $skrill_fee) . '&currency=' . $this->default_currency->code . '&status_url=' . site_url('payments/skrillipn') . '"><img src="' . base_url('assets/images/btn-skrill.png') . '" alt="Pay by Skrill"></a>';
            }

            $btn_code .= '<div class="clearfix"></div>
    </div>';
            $message = $message . $btn_code;

            $attachment = $this->pdf($id, null, 'S');
        } elseif ($this->input->post('send_email')) {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->session->set_flashdata('error', $this->data['error']);
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->sma->send_email($to, $subject, $message, null, null, $attachment, $cc, $bcc)) {
            delete_files($attachment);
            $this->session->set_flashdata('message', lang("email_sent_msg"));
            // redirect("sales");
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            if (file_exists('./themes/' . $this->theme . '/views/email_templates/sale.html')) {
                $sale_temp = file_get_contents('themes/' . $this->theme . '/views/email_templates/sale.html');
            } else {
                $sale_temp = file_get_contents('./themes/default/views/email_templates/sale.html');
            }

            $this->data['subject'] = array('name' => 'subject',
                'id' => 'subject',
                'type' => 'text',
                'value' => $this->form_validation->set_value('subject', lang('invoice') . ' (' . $inv->reference_no . ') ' . lang('from') . ' ' . $this->Settings->site_name),
            );
            $this->data['note'] = array('name' => 'note',
                'id' => 'note',
                'type' => 'text',
                'value' => $this->form_validation->set_value('note', $sale_temp),
            );
            $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);

            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'sales/email', $this->data);
        }
    }

    /* ------------------------------- */

    public function delete($id = null) {
        $this->sma->checkPermissions(null, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $inv = $this->sales_model->getInvoiceByID($id);
        if ($inv->sale_status == 'returned') {
            $this->session->set_flashdata('error', lang('sale_x_action'));
            $this->sma->md();
        }

        if ($this->sales_model->deleteSale($id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("sale_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('sale_deleted'));
            redirect('welcome');
        }
    }

    public function delete_return($id = null) {
        $this->sma->checkPermissions(null, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        if ($this->sales_model->deleteReturn($id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("return_sale_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('return_sale_deleted'));
            redirect('welcome');
        }
    }

    public function orders_actions() {
        if (!$this->Owner && !$this->GP['bulk_actions']) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == true) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {

                    $this->sma->checkPermissions('delete');
                    foreach ($_POST['val'] as $id) {
                        $this->orders_model->actionDeleteOrder($id);
                    }
                    $this->session->set_flashdata('message', lang("challans_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'combine') {

                    $html = $this->combine_pdf($_POST['val']);
                } elseif ($this->input->post('form_action') == 'combine_invoice') {

                    $html = $this->combine_invoice_pdf($_POST['val']);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:H1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:H1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Sales');
                    $this->excel->getActiveSheet()->setTitle(lang('sales'));

                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('date'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('reference_no'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('invoice_no'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('biller'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('customer'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('grand_total'));
                    $this->excel->getActiveSheet()->SetCellValue('G2', lang('paid'));
                    $this->excel->getActiveSheet()->SetCellValue('H2', lang('payment_status'));
                    $this->excel->getActiveSheet()->SetCellValue('I2', lang('Delivery Status'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $order = $this->orders_model->getOrderByID($id);
                        $delivery = $this->orders_model->getDeliveryByOrderID($id);

                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($order->date));
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $order->reference_no);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $order->id);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $order->biller);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $order->customer);
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $order->grand_total);
                        $this->excel->getActiveSheet()->SetCellValue('G' . $row, lang($order->paid));
                        $this->excel->getActiveSheet()->SetCellValue('H' . $row, lang($order->payment_status));
                        $this->excel->getActiveSheet()->SetCellValue('I' . $row, lang($order->delivery_status) . ' ' . lang($delivery->status));
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'orders_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
                        $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                        $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                        require_once APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php";
                        $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                        $rendererLibrary = 'MPDF';
                        $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                        if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                            die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' .
                                    PHP_EOL . ' as appropriate for your directory structure');
                        }

                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                        return $objWriter->save('php://output');
                    }
                    if ($this->input->post('form_action') == 'export_excel') {
                        header('Content-Type: application/vnd.ms-excel');
                        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                        return $objWriter->save('php://output');
                    }

                    redirect($_SERVER["HTTP_REFERER"]);
                }
            } else {
                $this->session->set_flashdata('error', lang("no_orders_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function suggestions() {
        $term = $this->input->get('term', true);
        $warehouse_id = $this->input->get('warehouse_id', true);
        $customer_id = $this->input->get('customer_id', true);
        $option_note = $this->input->get('option_note', true);

        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }

        $exp = explode("_", $term); // Using Barcode

        $analyzed = $this->sma->analyze_term($term);
        $sr = $analyzed['term'];
        $option_id = $analyzed['option_id'];

        $warehouse = $this->site->getWarehouseByID($warehouse_id);
        $customer = $this->site->getCompanyByID($customer_id);
        $customer_group = $this->site->getCustomerGroupByID($customer->customer_group_id);

        if ((!$this->Owner || !$this->Admin)):
            $rows = $this->sales_model->getProductNames($sr, $warehouse_id, 50, 1);
        else:
            $rows = $this->sales_model->getProductNames($sr, $warehouse_id);
        endif;

        //$rows->item_note = $item_note;

        if ($rows) {
            $c = str_replace(".", "", microtime(true));
            $r = 0;
            foreach ($rows as $row) {
                unset($row->cost, $row->details, $row->product_details, $row->barcode_symbology, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);


                $option = false; //old
                $option = ($exp[1]) ? $exp[1] : false; // Using Barcode Scan time
                /* ---- 14- 03-19 -- */
                $unitData = $this->sales_model->getUnitById($row->unit);
                $row->unit_lable = $unitData->name;
                /* --- 14-03-19 --- */
                $row->quantity = 0;
                $row->item_tax_method = $row->tax_method;
                $row->qty = 1;
                $row->discount = '0';
                $row->serial = '';

                $options = $this->sales_model->getProductOptions($row->id, $warehouse_id);
                if ($options) {
                    $opt = $option_id && $r == 0 ? $this->sales_model->getProductOptionByID($option_id) : $options[0];
                    if (!$option_id || $r > 0) {
                        $option_id = $opt->id;
                    }
                } else {
                    $opt = json_decode('{}');
                    $opt->price = 0;
                    $option_id = 0;
                }
                if ($this->Settings->attributes == 1)
                    $row->option = $option_id;
                $pis = $this->site->getPurchasedItems($row->id, $warehouse_id, $row->option);
                if ($pis) {
                    foreach ($pis as $pi) {
                        $row->quantity += $pi->quantity_balance;
                    }
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
                    $combo_items = $this->sales_model->getProductComboItems($row->id, $warehouse_id);
                }
                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);


                $pr[] = array('id' => ($c + $r), 'item_id' => $row->id, 'image' => $row->image, 'label' => $row->name . " (" . $row->code . ")", 'category' => $row->category_id,
                    'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options, 'note' => ($option_note) ? $option_note : "");
                $r++;
            }
            $this->sma->send_json($pr);
        } else {
            $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
        }
    }

    public function modal_view_order($id = null) {
        $this->sma->checkPermissions('index', true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getOrderByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view && !empty($inv->return_id)):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['created_by'] = $this->site->getUser($inv->created_by);
        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;
        $Settings = $this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->load->view($this->theme . 'orders/modal_view_pharma', $this->data);
        } else {
            $this->load->view($this->theme . 'orders/modal_view', $this->data);
        }
    }

    public function view_order($id = null) {
        $this->sma->checkPermissions('index');

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getOrderByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }

        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->sales_model->getPaymentsForSale($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);

        $this->data['created_by'] = $this->site->getUser($inv->created_by);

        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;


        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('sales'), 'page' => lang('sales')), array('link' => '#', 'page' => lang('view')));
        $meta = array('page_title' => lang('view_sales_details'), 'bc' => $bc);
        $Settings = $this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->page_construct('orders/view-sales-pharma', $meta, $this->data);
        } else {
            $this->page_construct('orders/view', $meta, $this->data);
        }
    }

    public function order_as_pdf($id = null, $view = null, $save_bufffer = null) {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getOrderByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->sales_model->getPaymentsForSale($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;
        //$this->data['paypal'] = $this->sales_model->getPaypalSettings();
        //$this->data['skrill'] = $this->sales_model->getSkrillSettings();

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        $html = $this->load->view($this->theme . 'orders/pdf', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }


        if ($view) {
            $this->load->view($this->theme . 'orders/pdf', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    public function delete_order($id = null) {
        $this->sma->checkPermissions(null, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $inv = $this->orders_model->getOrderByID($id);

        $syncQuantity = $inv->sale_as_chalan ? $inv->sale_as_chalan : 0;

        if ($inv->sale_status == 'returned') {
            $this->session->set_flashdata('error', lang('order_x_action'));
            $this->sma->md();
        }

        $sale_id = null;
        if ($inv->sale_invoice_no) {
            $sale = $this->sales_model->getSaleByInvoiceNo($inv->sale_invoice_no);
            if ($sale) {
                $sale_id = $sale->id;
            }
        }

        if ($this->orders_model->deleteOrder($id, $syncQuantity, $sale_id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("order_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('order_deleted'));
            redirect('welcome');
        }
    }

    /*     * *************************************************************************
      Start Eshop Order
     * ************************************************************************* */

    public function eshop_order($warehouse_id = null) {

        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : null;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = $warehouse_id == null ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Eshop_Order')));
        $meta = array('page_title' => lang('Eshop_Order'), 'bc' => $bc);

        $this->page_construct('orders/eshop_order', $meta, $this->data);
    }

    public function get_eshop_order($warehouse_id = null) {
        $this->sma->checkPermissions('index');

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }
        $detail_link1 = anchor('orders/eshop_order_view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('view_receipt'));

//        $detail_link = anchor('sales/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('sale_details'));
        //$duplicate_link = anchor('sales/add?chalan_id=$1', '<i class="fa fa-plus-circle"></i> ' . lang('create_sale'));
        $CreateAttr = array('class' => "invoice_created_link");
        //$duplicate_link = anchor('orders/add_sale_from_eshop_order?order_id=$1&syncQuantity=0&sale_action=eshop_order', '<i class="fa fa-plus-circle"></i> ' . lang('create_sale'), $CreateAttr);
        $duplicate_link = anchor('orders/edit_eshop_order/$1', '<i class="fa fa-plus-circle"></i> ' . lang('create_sale'), $CreateAttr);
        $payments_link = anchor('orders/paymentseshop_order/$1', '<i class="fa fa-money"></i> ' . lang('view_payments'), 'data-toggle="modal" data-target="#myModal"');
        $add_payment_link = anchor('orders/add_eshop_order_payment/$1', '<i class="fa fa-money"></i> ' . lang('add_payment'), 'data-toggle="modal" data-target="#myModal"');
//        $add_delivery_link = anchor('sales/add_delivery/$1', '<i class="fa fa-truck"></i> ' . lang('add_delivery'), 'data-toggle="modal" data-target="#myModal"');
        $email_link = anchor('orders/emaileshoporder/$1', '<i class="fa fa-envelope"></i> ' . lang('Email Eshop Order'), 'data-toggle="modal" data-target="#myModal"');
        $edit_link = anchor('orders/edit_eshop_order/$1', '<i class="fa fa-edit"></i> ' . lang('Edit_Eshop_Order'), 'class="sledit invoice_created_link"');
        $onclick = array('class' => "order_created_link", 'onclick' => "return confirm('Are you sure, order has been ready?')");
        $order_ready_link = anchor('orders/order_ready/$1', '<i class="fa fa-angle-double-right"></i> ' . lang('Order Ready'), $onclick);
        $return_link = anchor('orders/return_order/$1', '<i class="fa fa-angle-double-left"></i> ' . lang('Return Eshop Order'));
        $delete_link = "<a href='#' class='po' title='<b>" . lang("Delete Eshop Order") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('orders/delete_eshop_order/$1') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('Delete_Order') . "</a>";
        
        $order_accepted = anchor('orders/order_accepted/$1', '<i class="fa fa-check"></i> ' . lang('Order Accept'), '');
        
        $action = '<div class="text-center"><div class="btn-group text-left">'
                . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">'
                . lang('actions') . ' <span class="caret"></span></button>
        <ul class="dropdown-menu pull-right" role="menu">
            <li>' . $detail_link1 . '</li>
            <li>' . $detail_link . '</li>
            <li>' . $order_ready_link . '</li>
            <li>' . $duplicate_link . '</li>
            <li>' . $payments_link . '</li>
            <li class="link_$2">' . $add_payment_link . '</li>
            <li class="link_$2">' . $add_delivery_link . '</li>
            <li>' . $edit_link . '</li>
            <li>' . $pdf_link . '</li>
            <li>' . $email_link . '</li>
            <li class="link_$2">' . $return_link . '</li>
            <li>' . $delete_link . '</li>
            <li>' . $order_accepted . '</li>
        </ul>
    </div></div>';


        $this->load->library('datatables');
        $arrWr = [];
        if ($warehouse_id) {
//biller
            $this->datatables
                    ->select("id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, invoice_no as reference_no, sale_invoice_no as invoice_no, customer, sale_status, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, payment_status, attachment, return_id")
                    ->from('orders');

            $arrWr = explode(',', $warehouse_id);

            $this->datatables->where_in('warehouse_id', $arrWr);
        } else {
            $this->datatables
                    ->select("id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, invoice_no as reference_no,sale_invoice_no as invoice_no, customer, sale_status, (grand_total+rounding), paid, (grand_total+rounding-paid) as balance, payment_status, attachment, return_id")
                    ->from('orders');
        }
        $this->datatables->where('eshop_sale =', 1);
        $this->datatables->where('sale_invoice_no =', null);


        if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        } elseif ($this->Customer) {
            $this->datatables->where('customer_id', $this->session->userdata('user_id'));
        }
        $this->datatables->add_column("Order", '', "sale_status");
        $this->datatables->add_column("Actions", $action, "id,sale_status");


        echo $this->datatables->generate();
    }

    public function modal_view_eshop_order($id = null) {
        $this->sma->checkPermissions('index', true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getOrderByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view && !empty($inv->return_id)):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->orders_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['created_by'] = $this->site->getUser($inv->created_by);
        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;
        $this->data['sale_as_chalan'] = $inv->sale_as_chalan;
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);


        $this->data['billing_address'] =$this->orders_model->getShipingAdress($inv->billing_address_id);
        $this->data['shiping_address'] =$this->orders_model->getShipingAdress($inv->shipping_address_id);
        



        $Settings = $this->site->get_setting();
        if (isset($Settings->pos_type) && $Settings->pos_type == 'pharma') {
            $this->load->view($this->theme . 'orders/modal_view_pharma', $this->data);
        } else {
            $this->load->view($this->theme . 'orders/modal_view_eshop_order', $this->data);
        }
    }

    public function delete_eshop_order($id = null) {
        $this->sma->checkPermissions(null, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $inv = $this->orders_model->getOrderByID($id);

        if ($inv->sale_invoice_no) {
            $sale = $this->sales_model->getSaleByInvoiceNo($inv->sale_invoice_no);
            $sale_id = $sale->id;
            $syncQuantity = ($sale->id) ? 0 : 1;
        } else {
            $syncQuantity = 1;
            $sale_id = null;
        }

        if ($inv->sale_status == 'returned') {
            $this->session->set_flashdata('error', lang('order_x_action'));
            $this->sma->md();
        }

        if ($this->orders_model->deleteOrder($id, $syncQuantity, $sale_id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("order_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('order_deleted'));
            redirect('orders/eshop_order');
        }
    }

    public function edit_eshop_order($id = null) {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $saleType = '';
        if ($this->uri->segment(4))
            $saleType = $this->uri->segment(4);
        $inv = $this->orders_model->getEshopOrderByID($id);

        if ($inv->sale_status == 'returned' || $inv->return_id || $inv->return_sale_ref) {
            $this->session->set_flashdata('error', lang('sale_x_action'));
            redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
        }
        if (!$this->session->userdata('edit_right')) {
            $this->sma->view_rights($inv->created_by);
        }
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('customer', lang("customer"), 'required');
        $this->form_validation->set_rules('biller', lang("biller"), 'required');
        $this->form_validation->set_rules('sale_status', lang("sale_status"), 'required');
        $this->form_validation->set_rules('delivery_status', lang("delivery_status"), 'required');
        $this->form_validation->set_rules('payment_status', lang("payment_status"), 'required');

        if ($this->form_validation->run() == true) {

            $reference = $this->input->post('reference_no');
            if ($this->Owner || $this->Admin || $this->GP['sales-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = $inv->date;
            }
            $warehouse_id = $this->input->post('warehouse');
            $saleTypeInput = $this->input->post('saleType');
            $customer_id = $this->input->post('customer');
            $biller_id = $this->input->post('biller');

            $total_items = $this->input->post('total_items');
            $sale_status = $this->input->post('sale_status');
            $payment_status = $this->input->post('payment_status');
            $delivery_status = $this->input->post('delivery_status');
            $payment_term = $this->input->post('payment_term');
            $due_date = $payment_term ? date('Y-m-d', strtotime('+' . $payment_term . ' days', strtotime($date))) : null;
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $customer_details = $this->site->getCompanyByID($customer_id);
            $customer =  $customer_details->name; //$customer_details->company != '-' ? $customer_details->company : $customer_details->name;
            $biller_details = $this->site->getCompanyByID($biller_id);
            $biller = $biller_details->company != '-' ? $biller_details->company : $biller_details->name;
            $note = $this->sma->clear_tags($this->input->post('note'));
            $staff_note = $this->sma->clear_tags($this->input->post('staff_note'));

            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $total_weight = 0;
            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                $item_id = $_POST['product_id'][$r];
                $item_type = $_POST['product_type'][$r];
                $item_code = $_POST['product_code'][$r];

                $hsn_code = $_POST['hsn_code'][$r];
                $hsn_code = ($hsn_code == 'null') ? '' : $hsn_code;

                $item_name = $_POST['product_name'][$r];
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' && $_POST['product_option'][$r] != 'null' ? $_POST['product_option'][$r] : null;
                $real_unit_price = $this->sma->formatDecimal($_POST['real_unit_price'][$r]);
                $unit_price = $this->sma->formatDecimal($_POST['unit_price'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];
                $item_serial = isset($_POST['serial'][$r]) ? $_POST['serial'][$r] : '';
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                $item_unit = $_POST['product_unit'][$r];
                $item_quantity = $_POST['product_base_quantity'][$r];
                $item_mrp = $_POST['mrp'][$r];
                $item_cf1 = $_POST['cf1'][$r];
                $item_cf2 = $_POST['cf2'][$r];
                $item_weight = $_POST['item_weight'][$r];


                if (isset($item_code) && isset($real_unit_price) && isset($unit_price) && isset($item_quantity)) {
                    $product_details = $item_type != 'manual' ? $this->sales_model->getProductByCode($item_code) : null;
                    // $unit_price = $real_unit_price;
                    $item_mrp = !empty($item_mrp) ? $item_mrp : $product_details->mrp;
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
                    $unit_discount = $pr_discount;
                    $item_unit_price_less_discount = $this->sma->formatDecimal($unit_price - $unit_discount, 6);
                    $item_net_price = $net_unit_price = $item_unit_price_less_discount;


                    /* $unit_price = $this->sma->formatDecimal($unit_price - $pr_discount);
                      $item_net_price = $unit_price; */

                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $item_unit_quantity);
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $item_tax = 0;
                    $tax = "";
                    $net_unit_price = $item_unit_price_less_discount;
                    $unit_price = $item_unit_price_less_discount;
                    $invoice_unit_price = $item_unit_price_less_discount;
                    $invoice_net_unit_price = ($item_unit_price_less_discount + $unit_discount);

                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $tax_method = $product_details->tax_method;
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $unit_tax = $item_tax;
                        } elseif ($tax_details->type == 2) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_price) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $unit_price - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                        $unit_tax = $item_tax;
                    }

                    $invoice_unit_price = $this->sma->formatDecimal($invoice_unit_price, 4);
                    $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);
                    $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_unit_quantity), 4);
                    $product_tax += $pr_item_tax;
                    $subtotal = (($item_net_price * $item_unit_quantity) + $pr_item_tax);
                    $unit = $this->site->getUnitByID($item_unit);
                    $net_price = $this->sma->formatDecimal(($item_mrp * $item_quantity), 4);

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
                        'product_unit_code' => $unit->code,
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
                        'mrp' => $item_mrp,
                        'hsn_code' => $hsn_code,
                        'cf1' => $item_cf1,
                        'cf2' => $item_cf2,
                        'cf1_name' => 'Exp. Date',
                        'cf2_name' => 'Batch No.',
                        'net_price' => $net_price,
                        'tax_method' => $tax_method,
                        'unit_discount' => $unit_discount,
                        'unit_tax' => $unit_tax,
                        'invoice_unit_price' => $invoice_unit_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                        'item_weight' => $item_weight,
                    );

                    $total_weight += $item_weight;
                    $total += $this->sma->formatDecimal(($item_net_price * $item_unit_quantity), 4);
                }
            }
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }
            if ($this->input->post('order_discount')) {
                $order_discount_id = $this->input->post('order_discount');
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

            /* 12-6-2019 */
            $rounding = '';

            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }
            /*             * **** */
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
                'delivery_status' => $delivery_status,
                'payment_status' => $payment_status,
                'payment_term' => $payment_term,
                'rounding' => $rounding,
                'due_date' => $due_date,
                'updated_by' => $this->session->userdata('user_id'),
                'updated_at' => date('Y-m-d H:i:s'),
                'total_weight' => $total_weight,
            );
            //print_r($data); exit;
            if ($payment_status == 'partial' || $payment_status == 'paid') {
                if ($this->input->post('amount-paid') != 0) {
                    if ($this->input->post('paid_by') == 'deposit') {
                        if (!$this->site->check_customer_deposit($customer_id, $this->input->post('amount-paid'))) {
                            $this->session->set_flashdata('error', lang("amount_greater_than_deposit"));
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    }
                    if ($this->input->post('paid_by') == 'gift_card') {
                        $gc = $this->site->getGiftCardByNO($this->input->post('gift_card_no'));
                        $amount_paying = $grand_total >= $gc->balance ? $gc->balance : $grand_total;
                        $gc_balance = $gc->balance - $amount_paying;
                        $payment = array(
                            'date' => $date,
                            'reference_no' => $this->input->post('payment_reference_no'),
                            'amount' => $this->sma->formatDecimal($amount_paying),
                            'paid_by' => $this->input->post('paid_by'),
                            'cheque_no' => $this->input->post('cheque_no'),
                            'cc_no' => $this->input->post('gift_card_no'),
                            'cc_holder' => $this->input->post('pcc_holder'),
                            'cc_month' => $this->input->post('pcc_month'),
                            'cc_year' => $this->input->post('pcc_year'),
                            'cc_type' => $this->input->post('pcc_type'),
                            'created_by' => $this->session->userdata('user_id'),
                            'note' => $this->input->post('payment_note'),
                            'transaction_id' => $this->input->post('transaction_id'),
                            'type' => 'received',
                            'gc_balance' => $gc_balance,
                        );
                    } else {
                        $payment = array(
                            'date' => $date,
                            'reference_no' => $this->input->post('payment_reference_no'),
                            'amount' => $this->sma->formatDecimal($this->input->post('amount-paid')),
                            'paid_by' => $this->input->post('paid_by'),
                            'cheque_no' => $this->input->post('cheque_no'),
                            'cc_no' => $this->input->post('pcc_no'),
                            'cc_holder' => $this->input->post('pcc_holder'),
                            'cc_month' => $this->input->post('pcc_month'),
                            'cc_year' => $this->input->post('pcc_year'),
                            'cc_type' => $this->input->post('pcc_type'),
                            'created_by' => $this->session->userdata('user_id'),
                            'note' => $this->input->post('payment_note'),
                            'transaction_id' => $this->input->post('transaction_id'),
                            'type' => 'received',
                        );
                    }
                }
            } else {
                $payment = array();
            }
            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['attachment'] = $photo;
            }
        }
        if ($this->form_validation->run() == true && $this->orders_model->updateEshopOrder($id, $data, $products, $payment)) {
            if ($sale_status == 'completed') {
                redirect('orders/add_sale_from_eshop_order?order_id=' . $id . '&syncQuantity=1&sale_action=eshop_order');
            } else {
                $this->session->set_userdata('remove_slls', 1);
                $this->session->set_flashdata('message', lang("sale_updated"));
                redirect('orders/eshop_order');
            }
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['inv'] = $this->orders_model->getEshopOrderByID($id);
            $this->data['eshop_order'] = $this->orders_model->getOrderDetails($id);
            if ($this->Settings->disable_editing) {
                if ($this->data['inv']->date <= date('Y-m-d', strtotime('-' . $this->Settings->disable_editing . ' days'))) {
                    $this->session->set_flashdata('error', sprintf(lang("sale_x_edited_older_than_x_days"), $this->Settings->disable_editing));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            }
            $inv_items = $this->orders_model->getAllEshopOrderItems($id);

            krsort($inv_items);
            $c = rand(100000, 9999999);
            foreach ($inv_items as $item) {

                $row = $this->site->getProductByID($item->product_id);
                if (!$row) {
                    $row = json_decode('{}');
                    $row->tax_method = 0;
                    $row->quantity = 0;
                } else {
                    unset($row->cost, $row->details, $row->product_details, $row->barcode_symbology, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);
                }
                $pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
                if ($pis) {
                    foreach ($pis as $pi) {
                        $row->quantity += $pi->quantity_balance;
                    }
                }

                $unitData = $this->sales_model->getUnitById($row->unit);
                $saleunitData = $this->sales_model->getUnitById($row->sale_unit);
                $row->unit_lable = $unitData->name;
                $row->sale_unit_lable = $saleunitData->name;
                $row->id = $item->product_id;
                $row->code = $item->product_code;
                $row->name = $item->product_name;
                $row->type = $item->product_type;
                $row->base_quantity = $item->quantity;
                $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                $row->base_unit_price = $row->price ? $row->price : $item->unit_price;
                $row->unit = $item->product_unit_id;
                $row->qty = $item->unit_quantity;
                $row->quantity += $item->quantity;
                $row->discount = $item->discount ? $item->discount : '0';
                $row->price = $this->sma->formatDecimal($item->net_unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity));
                $row->unit_price = ($row->tax_method ) ? $item->unit_price + $this->sma->formatDecimal($item->item_discount / $item->quantity) + $this->sma->formatDecimal($item->item_tax / $item->quantity) : $item->unit_price + ($item->item_discount / $item->quantity);
                $row->real_unit_price = $item->real_unit_price;
                $row->tax_rate = $item->tax_rate_id;
                $row->serial = $item->serial_no;
                $row->option = $item->option_id;
                $row->delivery_status = $item->delivery_status;
                $row->delivered_qty = $item->delivered_quantity;
                $row->pending_qty = $item->pending_quantity;
                $row->net_unit_price = $item->net_unit_price;

                $options = $this->sales_model->getProductOptions($row->id, $item->warehouse_id);

                if ($options) {
                    $option_quantity = 0;
                    foreach ($options as $option) {
                        $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $item->option_id);
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $option_quantity += $pi->quantity_balance;
                            }
                        }
                        $option_quantity += $item->quantity;
                        if ($option->quantity > $option_quantity) {
                            $option->quantity = $option_quantity;
                        }

                        if ($item->option_id == $option->id) {

                            $row->unit_weight = !empty($option->unit_quantity) ? $option->unit_quantity : $item->item_weight;

                            $row->item_weight = $row->unit_weight * $item->unit_quantity;
                        }
                    }
                }

                if (!$row->item_weight) {
                    $row->item_weight = !empty($item->item_weight) ? $item->item_weight : $item->quantity;
                    $row->unit_weight = $row->item_weight / $item->unit_quantity;
                }

                $combo_items = false;
                if ($row->type == 'combo') {
                    $combo_items = $this->sales_model->getProductComboItems($row->id, $item->warehouse_id);
                    $te = $combo_items;
                    foreach ($combo_items as $combo_item) {
                        $combo_item->quantity = $combo_item->qty * $item->quantity;
                    }
                }
                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                $ri = $this->Settings->item_addition ? $row->id : $c;

                $pr[$ri] = array('id' => $c, 'item_id' => $row->id, 'image' => $row->image, 'label' => $row->name . " (" . $row->code . ")",
                    'row' => $row, 'combo_items' => $combo_items, 'tax_rate' => $tax_rate, 'cf1' => $row->cf1, 'cf2' => $row->cf2, 'units' => $units, 'options' => $options);
                $c++;
            }

            $this->data['inv_items'] = json_encode($pr);
            $this->data['eshop_sale'] = $inv->eshop_sale;
            $this->data['id'] = $id;
            $this->data['payments'] = $this->orders_model->getPaymentsForOrder($id);
            //$this->data['currencies'] = $this->site->getAllCurrencies();
            $this->data['billers'] = ($this->Owner || $this->Admin || !$this->session->userdata('biller_id')) ? $this->site->getAllCompanies('biller') : null;
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['sale_type'] = $saleTypeInput ? $saleTypeInput : $saleType;
            $this->data['sale_action'] = $this->uri->segment(2);
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('sales'), 'page' => lang('sales')), array('link' => '#', 'page' => lang('Edit Eshop Order')));
            $meta = array('page_title' => lang('Edit Eshop Order'), 'bc' => $bc);
            //echo '<pre>';
            //print_r($this->data['inv_items']); exit;
            $this->page_construct('orders/edit_eshop_order', $meta, $this->data);
        }
    }

    public function eshoporderpdf($id = null, $view = null, $save_bufffer = null) {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getEshopOrderByID($id);
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
        $this->data['payments'] = $this->orders_model->getPaymentsForOrder($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllEshopOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getEshopOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllEshopOrderItems($inv->return_id) : NULL;
        //$this->data['paypal'] = $this->sales_model->getPaypalSettings();
        //$this->data['skrill'] = $this->sales_model->getSkrillSettings();
        $this->data['eshop_settings'] = $this->orders_model->getEshopSettings();
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);
        $name = lang("Eshop_order") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        $html = $this->load->view($this->theme . 'orders/pdf_eshop_order', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }


        if ($view) {
            $this->load->view($this->theme . 'orders/pdf_eshop_order', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    public function emaileshoporder($id = null) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $inv = $this->orders_model->getEshopOrderByID($id);
        $this->form_validation->set_rules('to', lang("to") . " " . lang("email"), 'trim|required|valid_email');
        $this->form_validation->set_rules('subject', lang("subject"), 'trim|required');
        $this->form_validation->set_rules('cc', lang("cc"), 'trim|valid_emails');
        $this->form_validation->set_rules('bcc', lang("bcc"), 'trim|valid_emails');
        $this->form_validation->set_rules('note', lang("message"), 'trim');

        if ($this->form_validation->run() == true) {
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $to = $this->input->post('to');
            $subject = $this->input->post('subject');
            if ($this->input->post('cc')) {
                $cc = $this->input->post('cc');
            } else {
                $cc = null;
            }
            if ($this->input->post('bcc')) {
                $bcc = $this->input->post('bcc');
            } else {
                $bcc = null;
            }
            $customer = $this->site->getCompanyByID($inv->customer_id);
            $biller = $this->site->getCompanyByID($inv->biller_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->reference_no,
                'contact_person' => $customer->name,
                'company' => $customer->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
            );
            $msg = $this->input->post('note');
            $message = $this->parser->parse_string($msg, $parse_data);
            $paypal = $this->sales_model->getPaypalSettings();
            $skrill = $this->sales_model->getSkrillSettings();
            $btn_code = '<div id="payment_buttons" class="text-center margin010">';
            if ($paypal->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_my / 100);
                } else {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_other / 100);
                }
                $btn_code .= '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=' . $paypal->account_email . '&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&image_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $paypal_fee) . '&no_shipping=1&no_note=1&currency_code=' . $this->default_currency->code . '&bn=FC-BuyNow&rm=2&return=' . site_url('sales/view/' . $inv->id) . '&cancel_return=' . site_url('sales/view/' . $inv->id) . '&notify_url=' . site_url('payments/paypalipn') . '&custom=' . $inv->reference_no . '__' . ($inv->grand_total - $inv->paid) . '__' . $paypal_fee . '"><img src="' . base_url('assets/images/btn-paypal.png') . '" alt="Pay by PayPal"></a> ';
            }
            if ($skrill->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_my / 100);
                } else {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_other / 100);
                }
                $btn_code .= ' <a href="https://www.moneybookers.com/app/payment.pl?method=get&pay_to_email=' . $skrill->account_email . '&language=EN&merchant_fields=item_name,item_number&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&logo_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $skrill_fee) . '&return_url=' . site_url('sales/view/' . $inv->id) . '&cancel_url=' . site_url('sales/view/' . $inv->id) . '&detail1_description=' . $inv->reference_no . '&detail1_text=Payment for the sale invoice ' . $inv->reference_no . ': ' . $inv->grand_total . '(+ fee: ' . $skrill_fee . ') = ' . $this->sma->formatMoney($inv->grand_total + $skrill_fee) . '&currency=' . $this->default_currency->code . '&status_url=' . site_url('payments/skrillipn') . '"><img src="' . base_url('assets/images/btn-skrill.png') . '" alt="Pay by Skrill"></a>';
            }

            $btn_code .= '<div class="clearfix"></div>
    </div>';
            $message = $message . $btn_code;

            $attachment = $this->eshoporderpdf($id, null, 'S');
        } elseif ($this->input->post('send_email')) {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->session->set_flashdata('error', $this->data['error']);
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->sma->send_email($to, $subject, $message, null, null, $attachment, $cc, $bcc)) {
            delete_files($attachment);
            $this->session->set_flashdata('message', lang("email_sent_msg"));
            // redirect("sales");
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            if (file_exists('./themes/' . $this->theme . '/views/email_templates/sale.html')) {
                $sale_temp = file_get_contents('themes/' . $this->theme . '/views/email_templates/sale.html');
            } else {
                $sale_temp = file_get_contents('./themes/default/views/email_templates/sale.html');
            }

            $this->data['subject'] = array('name' => 'subject',
                'id' => 'subject',
                'type' => 'text',
                'value' => $this->form_validation->set_value('subject', lang('Order') . ' (' . $inv->reference_no . ') ' . lang('from') . ' ' . $this->Settings->site_name),
            );
            $this->data['note'] = array('name' => 'note',
                'id' => 'note',
                'type' => 'text',
                'value' => $this->form_validation->set_value('note', $sale_temp),
            );
            $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);

            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'sales/email', $this->data);
        }
    }

    public function edit_eshop_order_payment($id = null) {
        $this->sma->checkPermissions('edit', true);
        $this->load->helper('security');
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $payment = $this->sales_model->getPaymentByID($id);
        if ($payment->paid_by == 'ppp' || $payment->paid_by == 'stripe') {
            $this->session->set_flashdata('error', lang('x_edit_payment'));
            $this->sma->md();
        }
        $this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('amount-paid', lang("amount"), 'required');
        $this->form_validation->set_rules('paid_by', lang("paid_by"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->input->post('paid_by') == 'deposit') {
                $sale = $this->orders_model->getEshopOrderByID($this->input->post('sale_id'));
                $customer_id = $sale->customer_id;
                $amount = $this->input->post('amount-paid') - $payment->amount;
                if (!$this->site->check_customer_deposit($customer_id, $amount)) {
                    $this->session->set_flashdata('error', lang("amount_greater_than_deposit"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            } else {
                $customer_id = null;
            }
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = $payment->date;
            }
            $payment = array(
                'date' => $date,
                'sale_id' => $this->input->post('sale_id'),
                'reference_no' => $this->input->post('reference_no'),
                'amount' => $this->input->post('amount-paid'),
                'paid_by' => $this->input->post('paid_by'),
                'cheque_no' => $this->input->post('cheque_no'),
                'cc_no' => $this->input->post('pcc_no'),
                'cc_holder' => $this->input->post('pcc_holder'),
                'cc_month' => $this->input->post('pcc_month'),
                'cc_year' => $this->input->post('pcc_year'),
                'cc_type' => $this->input->post('pcc_type'),
                'note' => $this->input->post('note'),
                'created_by' => $this->session->userdata('user_id'),
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
        } elseif ($this->input->post('edit_payment')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->orders_model->updateEshopOrderPayment($id, $payment, $customer_id)) {
            $this->session->set_flashdata('message', lang("payment_updated"));
            redirect("orders/eshop_order");
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['payment'] = $payment;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'orders/edit_eshop_order_payment', $this->data);
        }
    }

    public function add_eshop_order_payment($id = null) {
        $this->sma->checkPermissions('payments', true);
        $this->load->helper('security');
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $sale = $this->orders_model->getEshopOrderByID($id);
        if ($sale->payment_status == 'paid' && $sale->grand_total == $sale->paid) {
            $this->session->set_flashdata('error', lang("sale_already_paid"));
            $this->sma->md();
        }

        //$this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('amount-paid', lang("amount"), 'required');
        $this->form_validation->set_rules('paid_by', lang("paid_by"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->input->post('paid_by') == 'deposit') {
                $sale = $this->orders_model->getEshopOrderByID($this->input->post('sale_id'));
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
                'order_id' => $this->input->post('sale_id'),
                'reference_no' => $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('pay'),
                'amount' => $this->input->post('amount-paid'),
                'paid_by' => $this->input->post('paid_by'),
                'cheque_no' => $this->input->post('cheque_no'),
                'cc_no' => $this->input->post('paid_by') == 'gift_card' ? $this->input->post('gift_card_no') : $this->input->post('pcc_no'),
                'cc_holder' => $this->input->post('pcc_holder'),
                'cc_month' => $this->input->post('pcc_month'),
                'cc_year' => $this->input->post('pcc_year'),
                'cc_type' => $this->input->post('pcc_type'),
                'note' => $this->input->post('note'),
                'transaction_id' => $this->input->post('transaction_id'),
                'created_by' => $this->session->userdata('user_id'),
                'type' => 'received',
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

        if ($this->form_validation->run() == true && $this->sales_model->addPayment($payment, $customer_id, 'eshop_order')) {
            $this->session->set_flashdata('message', lang("payment_added"));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            if ($sale->sale_status == 'returned' && $sale->paid == $sale->grand_total) {
                $this->session->set_flashdata('warning', lang('payment_was_returned'));
                $this->sma->md();
            }
            $this->data['inv'] = $sale;
            $this->data['payment_ref'] = ''; //$this->site->getReference('pay');
            $this->data['modal_js'] = $this->site->modal_js();

            $this->load->view($this->theme . 'sales/add_payment', $this->data);
        }
    }

    public function paymentseshop_order($id = null) {
        $this->sma->checkPermissions(false, true);
        $this->data['payments'] = $this->orders_model->getEshopOrderInvoicePayments($id);
        $this->data['inv'] = $this->orders_model->getEshopOrderByID($id);
        $this->load->view($this->theme . 'orders/eshop_order_payment', $this->data);
    }

    public function add_sale_from_eshop_order($order_id = null, $syncQuantity = 0, $sale_action = 'eshop_order') {

        $this->sma->checkPermissions('index', true);
        //$this->load->helper('security');

        if ($this->input->get('order_id')) {
            $order_id = $this->input->get('order_id');
        }
        if ($this->input->get('syncQuantity')) {
            $syncQuantity = $this->input->get('syncQuantity');
        }
        if ($this->input->get('sale_action')) {
            $sale_action = $this->input->get('sale_action');
        }

        if ($order_id) {

            $reference = $this->site->getReference('so');

            $date = date('Y-m-d H:i:s');

            $order = $this->orders_model->getOrderByID($order_id);

            if ($order->sale_status == 'returned') {
                $this->session->set_flashdata('error', 'This action can not be performed with a return record');

                redirect('sales/eshop_order');
            }

            if (!empty($order->sale_invoice_no)) {
                $this->session->set_flashdata('error', 'Sale already created');

                redirect('orders/eshop_order');
            }

            $data = array('date' => $date,
                'reference_no' => $reference,
                'customer_id' => $order->customer_id,
                'customer' => $order->customer,
                'biller_id' => $order->biller_id,
                'biller' => $order->biller,
                'seller_id' => $order->seller_id,
                'seller' => $order->seller,
                'warehouse_id' => $order->warehouse_id,
                'note' => $order->note,
                'staff_note' => $order->staff_note,
                'total' => $order->total,
                'product_discount' => $order->product_discount,
                'order_discount_id' => $order->order_discount_id,
                'order_discount' => $order->order_discount,
                'total_discount' => $order->total_discount,
                'product_tax' => $order->product_tax,
                'order_tax_id' => $order->order_tax_id,
                'order_tax' => $order->order_tax,
                'total_tax' => $order->total_tax,
                'shipping' => $order->shipping,
                'grand_total' => $order->grand_total,
                'total_items' => $order->total_items,
                'sale_status' => $order->sale_status,
                'payment_status' => $order->payment_status,
                'delivery_status' => $order->delivery_status,
                'payment_term' => $order->payment_term,
                'rounding' => $order->rounding,
                'due_date' => $order->due_date,
                'created_by' => $this->session->userdata('user_id'),
                'paid' => $order->paid,
                'cgst' => $order->cgst,
                'sgst' => $order->sgst,
                'igst' => $order->igst,
                'order_no' => $order->invoice_no,
                'total_weight' => $order->total_weight,
                'eshop_sale' => 1,
            );

            $orderItems = $this->orders_model->getOrderItem($order_id);
            if ($orderItems) {
                foreach ($orderItems as $key => $item) {

                    $products[] = array(
                        'product_id' => $item->product_id,
                        'product_code' => $item->product_code,
                        'article_code' => $item->article_code,
                        'product_name' => $item->product_name,
                        'product_type' => $item->product_type,
                        'option_id' => $item->option_id,
                        'net_unit_price' => $item->net_unit_price,
                        'unit_discount' => $item->unit_discount,
                        'unit_tax' => $item->unit_tax,
                        'invoice_unit_price' => $item->invoice_unit_price,
                        'invoice_net_unit_price' => $item->invoice_net_unit_price,
                        'unit_price' => $item->unit_price,
                        'quantity' =>$item->unit_quantity,
                        'net_price' => $item->net_price,
                        'invoice_total_net_unit_price' => $item->invoice_total_net_unit_price,
                        'warehouse_id' => $item->warehouse_id,
                        'item_tax' => $item->item_tax,
                        'tax_method' => $item->tax_method,
                        'tax_rate_id' => $item->tax_rate_id,
                        'tax' => $item->tax,
                        'discount' => $item->discount,
                        'item_discount' => $item->item_discount,
                        'subtotal' => $item->subtotal,
                        'serial_no' => $item->serial_no,
                        'real_unit_price' => $item->real_unit_price,
                        'product_unit_id' => $item->product_unit_id,
                        'product_unit_code' => $item->product_unit_code,
                        'unit_quantity' =>  $item->quantity,
                        'cf1' => $item->cf1,
                        'cf2' => $item->cf2,
                        'cf1_name' => $item->cf1_name,
                        'cf2_name' => $item->cf2_name,
                        'mrp' => $item->mrp,
                        'hsn_code' => $item->hsn_code,
                        'note' => $item->note,
                        'delivery_status' => $item->delivery_status,
                        'pending_quantity' => $item->pending_quantity,
                        'delivered_quantity' => $item->delivered_quantity,
                        'gst_rate' => $item->gst_rate,
                        'cgst' => $item->cgst,
                        'sgst' => $item->sgst,
                        'igst' => $item->igst,
                        'item_weight' => $item->item_weight,
                    );
                }
            } else {
                $this->session->set_flashdata('error', $this->lang->line("Eshop order Items Not found"));

                redirect("orders/eshop_order");
            }
            $extrasPara = array('sale_action' => $sale_action, 'syncQuantity' => $syncQuantity, 'order_id' => $order_id);


            if ($sale_id = $this->orders_model->addSaleFromEshopOrder($data, $products, $extrasPara)) {

                if ($order->return_id) {
                    if ($this->add_sale_return_from_eshop_order_return($order->return_id, $sale_id, $syncQuantity)) {
                        $this->email_sms_sale($sale_id);
                        $this->session->set_flashdata('message', $this->lang->line("Delivery eshop order and return items added to sales successfully"));
                        redirect("eshop_sales/sales");
                    }
                } else {
                    $this->email_sms_sale($sale_id);
                    $this->session->set_flashdata('message', $this->lang->line("Delivery eshop order add to sale successfully"));
                    redirect("eshop_sales/sales");
                }
            }
        }
    }

    public function email_sms_sale($id = null) {
        $this->sma->checkPermissions('index');
        $_PID = $this->Settings->default_printer;
        $WarehouseId = $this->Settings->default_warehouse;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        if (!$id) {
            die('No sale selected.');
        }
        $inv = $this->sales_model->getInvoiceByID($id);

        $sms_code = md5('Reciept' . $inv->reference_no . $inv->id);
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $RowWarehouse = $this->orders_model->getWarehouseByID($WarehouseId);
        //echo 'ssss'.$id;
        //print_r($this->data['customer']);
        $to = $this->data['customer']->email;
        $Phone = $this->data['customer']->phone;
        $BillerPhone = $RowWarehouse->phone;
        $Billerto = $RowWarehouse->email;

        $direct_location = 0;
        if ($Phone != '') {
            $str = '';
            if ($inv->grand_total):
                $str = 'Thank you for ordering from ' . $this->Settings->site_name . ' to view receipt ';
            endif;

            $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
            $url = $this->get_tiny_url(base_url('reciept/pdf/') . $sms_code);
            $msg = $str . ' click on ' . $url;
            $res = $this->sma->SendSMS($Phone, $msg, 'ORDER_PLACED_RECEIPT');
        }
        if ($BillerPhone != '') {
            $str = '';
            if ($inv->grand_total):
                $str = 'Reciept' . $inv->invoice_no . ' invoice has been completed. Total Bill amount ' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency;
            endif;

            $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
            $url = $this->get_tiny_url(base_url('reciept/pdf/') . $sms_code);
            $msg = $str . ' You can view receipt ' . $url;
            $res = $this->sma->SendSMS($BillerPhone, $msg, 'ESHOP_ORDER_COMLETED');
        }
        //exit;
        if ($to != '') {
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $customer = $this->site->getCompanyByID($inv->customer_id);
            $biller = $this->site->getCompanyByID($inv->biller_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->invoice_no,
                'order_no' => $inv->order_no,
                'contact_person' => $customer->name,
                'company' => $customer->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
            );
            //$msg = $this->input->post('note');
            $msg = '<h3>{logo}</h3><h4>Sale Details</h4><p>
    Hello {contact_person} ({company}),</p><p>Order ({reference_number}) has been completed. </p><p>Please find the attachment for your sale order ({order_no})
    details.</p><p>
    <a href="{client_link}"></a></p><p>
    Best regards,
    <br>
    {site_name}</p>';
            $message = $this->parser->parse_string($msg, $parse_data);
            $msg_shopkeeper = '<h3>{logo}</h3><h4>Sale Details</h4><p>
    Hello Shopkeeper,</p><p>Order ({reference_number}) has been completed. </p><p>Please find the attachment for sale order ({reference_number})
    details.</p><p>
    <a href="{client_link}"></a></p><p>
    Best regards,
    <br>
    {site_name}</p>';
            $message_shopkeeper = $this->parser->parse_string($msg_shopkeeper, $parse_data);
            $paypal = $this->sales_model->getPaypalSettings();
            $skrill = $this->sales_model->getSkrillSettings();
            $message = $message;

            $attachment = $this->salepdf($id, null, 'S');

            $subject = 'Receipt from ' . $biller->company;
            //$this->sma->send_email($Billerto, $subject, $message_shopkeeper, null, null, $attachment, $cc, $bcc);
            if ($this->sma->send_email($to, $subject, $message, null, null, $attachment, $cc, $bcc)) {
                delete_files($attachment);
                //$this->session->set_flashdata('message', lang("email_sent_msg"));
            }
        }
    }

    public function salepdf($id = null, $view = null, $save_bufffer = null) {
        $this->sma->checkPermissions();
        $this->load->model('pos_model');
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
        $name = lang("Eshop_order") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        //$html = $this->load->view($this->theme . 'sales/pdf', $this->data, true);
        $html = $this->load->view($this->theme . 'sales/pdf_reciept', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }


        if ($view) {
            $this->load->view($this->theme . 'sales/pdf_reciept', $this->data);
            //$this->load->view($this->theme . 'sales/pdf', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    public function add_sale_return_from_eshop_order_return($order_return_id, $sale_id, $syncQuantity = 0) {

        $this->sma->checkPermissions('index', true);

        if ($order_return_id) {

            $orderReturn = $this->orders_model->getOrderByID($order_return_id);

            $sales = $this->sales_model->getInvoiceByID($sale_id);

            if (!empty($sales->return_id)) {
                $this->session->set_flashdata('message', 'Sale already returned');
                if ($syncQuantity) {
                    redirect('sales');
                }
            }

            $return_sale_ref = $this->site->getReference('re');

            $date = date('Y-m-d H:i:s');

            $data = array('date' => $date,
                'sale_id' => $sales->id,
                'invoice_no' => $sales->invoice_no,
                'reference_no' => $sales->reference_no,
                'return_sale_ref' => $return_sale_ref,
                'customer_id' => $orderReturn->customer_id,
                'customer' => $orderReturn->customer,
                'biller_id' => $orderReturn->biller_id,
                'biller' => $orderReturn->biller,
                'seller_id' => $orderReturn->seller_id,
                'seller' => $orderReturn->seller,
                'warehouse_id' => $orderReturn->warehouse_id,
                'note' => $orderReturn->note,
                'staff_note' => $orderReturn->staff_note,
                'total' => $orderReturn->total,
                'product_discount' => $orderReturn->product_discount,
                'order_discount_id' => $orderReturn->order_discount_id,
                'order_discount' => $orderReturn->order_discount,
                'total_discount' => $orderReturn->total_discount,
                'product_tax' => $orderReturn->product_tax,
                'order_tax_id' => $orderReturn->order_tax_id,
                'order_tax' => $orderReturn->order_tax,
                'total_tax' => $orderReturn->total_tax,
                'shipping' => $orderReturn->shipping,
                'grand_total' => $orderReturn->grand_total,
                'total_items' => $orderReturn->total_items,
                'sale_status' => $orderReturn->sale_status,
                'payment_status' => $orderReturn->payment_status,
                'payment_term' => $orderReturn->payment_term,
                'due_date' => $orderReturn->due_date,
                'created_by' => $orderReturn->created_by,
                'paid' => $orderReturn->paid,
                'cgst' => $orderReturn->cgst,
                'sgst' => $orderReturn->sgst,
                'igst' => $orderReturn->igst,
            );

            $salesItems = $this->sales_model->getSalesItemBySaleID($sale_id);

            if (is_array($salesItems)) {
                foreach ($salesItems as $item_id => $sitems) {
                    $salesProductsItems[$sitems->product_id] = $item_id;
                }
            }

            $orderItems = $this->orders_model->getOrderItem($order_return_id);

            if ($orderItems) {
                foreach ($orderItems as $key => $item) {

                    $products[] = array(
                        'sale_item_id' => $salesProductsItems[$item->product_id],
                        'product_id' => $item->product_id,
                        'product_code' => $item->product_code,
                        'article_code' => $item->article_code,
                        'product_name' => $item->product_name,
                        'product_type' => $item->product_type,
                        'option_id' => $item->option_id,
                        'net_unit_price' => $item->net_unit_price,
                        'unit_discount' => $item->unit_discount,
                        'unit_tax' => $item->unit_tax,
                        'invoice_unit_price' => $item->invoice_unit_price,
                        'invoice_net_unit_price' => $item->invoice_net_unit_price,
                        'unit_price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'net_price' => $item->net_price,
                        'invoice_total_net_unit_price' => $item->invoice_total_net_unit_price,
                        'warehouse_id' => $item->warehouse_id,
                        'item_tax' => $item->item_tax,
                        'tax_method' => $item->tax_method,
                        'tax_rate_id' => $item->tax_rate_id,
                        'tax' => $item->tax,
                        'discount' => $item->discount,
                        'item_discount' => $item->item_discount,
                        'subtotal' => $item->subtotal,
                        'serial_no' => $item->serial_no,
                        'real_unit_price' => $item->real_unit_price,
                        'product_unit_id' => $item->product_unit_id,
                        'product_unit_code' => $item->product_unit_code,
                        'unit_quantity' => $item->unit_quantity,
                        'cf1' => $item->cf1,
                        'cf2' => $item->cf2,
                        'cf1_name' => $item->cf1_name,
                        'cf2_name' => $item->cf2_name,
                        'mrp' => $item->mrp,
                        'hsn_code' => $item->hsn_code,
                        'note' => $item->note,
                        'delivery_status' => $item->delivery_status,
                        'pending_quantity' => $item->pending_quantity,
                        'delivered_quantity' => $item->delivered_quantity,
                        'gst_rate' => $item->gst_rate,
                        'cgst' => $item->cgst,
                        'sgst' => $item->sgst,
                        'igst' => $item->igst,
                    );
                }
            } else {
                $this->session->set_flashdata('error', $this->lang->line("Eshop Order Return Items Not found"));

                redirect("sales");
            }
            $extrasPara = array('sale_action' => 'sale_return', 'syncQuantity' => $syncQuantity, 'sale_id' => $sale_id, 'order_id' => $order_return_id);

            if ($sale_return_id = $this->orders_model->addSaleReturnFromShopOrderReturn($data, $products, $extrasPara)) {
                return $sale_return_id;
            }
        }
    }

    public function barcode($text = null, $bcs = 'code128', $height = 50) {
        return site_url('products/gen_barcode/' . $text . '/' . $bcs . '/' . $height);
    }

    public function eshop_order_view($Id = null, $modal = null) {

        $this->load->model('orders_model');
        $this->load->model('pos_model');

        $this->data['myclass'] = $ci = & get_instance();
        $this->data['pos_settingss'] = $this->site->get_pos_setting();
        // $this->sma->checkPermissions('sales');
        if ($this->input->get('id')) {
            $Id = $this->input->get('id');
        }

        $Settings = $this->Settings = $this->site->get_setting();

        $_PID = $this->Settings->default_printer;

        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        $this->load->helper('text');
        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['message'] = $this->session->flashdata('message');
        $inv = $this->orders_model->getOrderByID($Id);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($Id, $inv->return_id);
        endif;

        //$isGstSale = $this->site->isGstSale($Id);
        $inv->GstSale = $inv->product_tax == 0.0000 ? 0 : 1;

        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }
        $print = array();
        $print['print_option'] = $this->site->defaultPrinterOption($_PID);
        $print['rows'] = $this->data['rows'] = $this->orders_model->getAllOrderItems($Id);
        $biller_id = $inv->biller_id;
        $customer_id = $inv->customer_id;
        $print['biller']   = $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
        $print['customer'] = $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);
        $print['payments'] = $this->data['payments'] = $this->orders_model->getOrderPayments($Id);
        $print['pos'] = $this->data['pos'] = $this->pos_model->getSetting();
        unset($print['pos']->pos_theme);
        $print['barcode'] = $this->data['barcode'] = $this->barcode($inv->reference_no, 'code128', 30);
        $print['return_sale'] = $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : null;
        $print['return_rows'] = $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : null;
        $print['return_payments'] = $this->data['return_payments'] = $this->data['return_sale'] ? $this->orders_model->getOrderPayments($this->data['return_sale']->id) : null;
        $print['inv'] = $this->data['inv'] = $inv;
        $print['sid'] = $this->data['sid'] = $Id;
        $print['modal'] = $this->data['modal'] = $modal;
        $print['page_title'] = $this->data['page_title'] = $this->lang->line("invoice");
        $print['taxItems'] = $this->data['taxItems'] = $this->orders_model->getAllTaxItemsGroup($inv->id, $inv->return_id);
                
        $print['billing_address'] = $this->data['billing_address'] =$this->orders_model->getShipingAdress($inv->billing_address_id);
        $print['shiping_address'] = $this->data['shiping_address'] =$this->orders_model->getShipingAdress($inv->shipping_address_id);
        

        if($inv->shipping_outlet) {            
            $shipping_outlet = $this->site->getWarehouseByID($inv->shipping_outlet);
            $this->data['inv']->shipping_outlet_name = $shipping_outlet[$inv->shipping_outlet]->name;
        }
        //Set Sale items image

        if (!empty($print['rows'])) {
            foreach ($print['rows'] as $key => $row) {
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $print['rows'][$key]->image = $product->image;
            }
        }

        $print['pos_type'] = $Settings->pos_type;

        $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;

        $this->data['sms_limit'] = $this->sma->BalanceSMS();

        $this->data['show_kot'] = false;
        if (isset($Settings->pos_type) && $Settings->pos_type == 'restaurant'):
            $this->data['show_kot'] = true;
        endif;
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($Id);
        $this->load->view($this->theme . 'orders/view_eshop_order', $this->data);

        $print['brcode'] = $this->sma->save_barcode($inv->reference_no, 'code128', 66, false);
        $print['qrcode'] = $this->sma->qrcode('link', urlencode(site_url('orders/eshop_order_view/' . $inv->id)), 2);
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

        if ($Id != $_SESSION['print'] && (isset($_SESSION['print_type']) && $_SESSION['print_type'] == null)) {
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
        $_SESSION['print'] = $Id;
    }

    public function eshop_order_as_pdf($id = null, $view = null, $save_bufffer = null) {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getOrderByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;
        $this->data['taxItems'] = $this->orders_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->orders_model->getPaymentsForOrder($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;
        //$this->data['paypal'] = $this->sales_model->getPaypalSettings();
        //$this->data['skrill'] = $this->sales_model->getSkrillSettings();
        $this->data['eshop_settings'] = $this->orders_model->getEshopSettings();
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);

                 $this->data['billing_address'] =$this->orders_model->getShipingAdress($inv->billing_address_id);
        $this->data['shiping_address'] =$this->orders_model->getShipingAdress($inv->shipping_address_id);
        

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        $html = $this->load->view($this->theme . 'orders/pdf_eshop_order', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }
        if ($view) {
            $this->load->view($this->theme . 'orders/pdf_eshop_order', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    public function get_tiny_url($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, 'http://tinyurl.com/api-create.php?url=' . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public function order_accepted($id = null) {

       // $this->sma->checkPermissions('index');

        $_PID = $this->Settings->default_printer;
        $WarehouseId = $this->data['pos_settings']->default_eshop_warehouse;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        if (!$id) {
            die('No sale selected.');
        }
        $ArrData = array(
            'sale_status' => 'accepted'
        );
        $this->orders_model->updateStatus($id, $ArrData);
        $inv = $this->orders_model->getOrderByID($id);

        $sms_code = md5('Reciept' . $inv->reference_no . $inv->id);
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);
        $RowWarehouse = $this->orders_model->getWarehouseByID($WarehouseId);
       
        $to = $this->data['customer']->email;
        $Phone = $this->data['customer']->phone;
        $BillerPhone = $RowWarehouse->phone;
        $Billerto = $RowWarehouse->email;
        $direct_location = 0;

        if ($Phone != '') {
            $str = '';
            if ($inv->grand_total):
                $str = 'Hello, Your Order ID ' . $inv->invoice_no . ' has been accepted.';
            endif;

            $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
            $url = $this->get_tiny_url(base_url('reciept/pdf_eshop_order/') . $sms_code);
            $msg = $str . ' Order Receipt ' . $url;
            $res = $this->sma->SendSMS($Phone, $msg, 'ESHOP_ORDER_ACCEPTED');
            
            $direct_location = 1;
        }
         
        if ($to != '') {
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $customer = $this->site->getCompanyByID($inv->customer_id);
            $biller = $this->site->getCompanyByID($inv->biller_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->invoice_no,
                'contact_person' => $customer->name,
                'company' => $customer->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
            );
            $msg = '<h3>{logo}</h3><h4>Order Details</h4><p>
                    Hello {contact_person} ({company}),</p><p>Order ({reference_number}) has been accepted. </p><p>Please find the attachment for order ({reference_number})
                    details.</p><p>
                    <a href="{client_link}"></a></p><p>
                    Best regards,
                    <br>
                    {site_name}</p>';
            $message = $this->parser->parse_string($msg, $parse_data);
            $paypal = $this->sales_model->getPaypalSettings();
            $skrill = $this->sales_model->getSkrillSettings();
            $btn_code = '<div id="payment_buttons" class="text-center margin010">';
            if ($paypal->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_my / 100);
                } else {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_other / 100);
                }
                $btn_code .= '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=' . $paypal->account_email . '&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&image_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $paypal_fee) . '&no_shipping=1&no_note=1&currency_code=' . $this->default_currency->code . '&bn=FC-BuyNow&rm=2&return=' . site_url('sales/view/' . $inv->id) . '&cancel_return=' . site_url('sales/view/' . $inv->id) . '&notify_url=' . site_url('payments/paypalipn') . '&custom=' . $inv->reference_no . '__' . ($inv->grand_total - $inv->paid) . '__' . $paypal_fee . '"><img src="' . base_url('assets/images/btn-paypal.png') . '" alt="Pay by PayPal"></a> ';
            }
            if ($skrill->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_my / 100);
                } else {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_other / 100);
                }
                $btn_code .= ' <a href="https://www.moneybookers.com/app/payment.pl?method=get&pay_to_email=' . $skrill->account_email . '&language=EN&merchant_fields=item_name,item_number&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&logo_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $skrill_fee) . '&return_url=' . site_url('sales/view/' . $inv->id) . '&cancel_url=' . site_url('sales/view/' . $inv->id) . '&detail1_description=' . $inv->reference_no . '&detail1_text=Payment for the sale invoice ' . $inv->reference_no . ': ' . $inv->grand_total . '(+ fee: ' . $skrill_fee . ') = ' . $this->sma->formatMoney($inv->grand_total + $skrill_fee) . '&currency=' . $this->default_currency->code . '&status_url=' . site_url('payments/skrillipn') . '"><img src="' . base_url('assets/images/btn-skrill.png') . '" alt="Pay by Skrill"></a>';
            }

            $btn_code .= '<div class="clearfix"></div></div>';
            $message = $message . $btn_code;

            $attachment = $this->eshoporderpdf($id, null, 'S');

            $subject = 'Receipt from ' . $this->data['biller']->company;
            $msg_shopkeeper = '<h3>{logo}</h3><h4>Sale Details</h4><p>
                                Hello Shopkeeper,</p><p>Order ({reference_number}) has been acceped. </p><p>Please find the attachment for order ({reference_number})
                                details.</p><p><a href="{client_link}"></a></p><p>
                                Best regards,<br>{site_name}</p>';
            $message_shopkeeper = $this->parser->parse_string($msg_shopkeeper, $parse_data);
            //$this->sma->send_email($Billerto, $subject, $message_shopkeeper, null, null, $attachment, $cc, $bcc);
            if ($this->sma->send_email($to, $subject, $message, null, null, $attachment, $cc, $bcc)) {
                delete_files($attachment);
                //$this->session->set_flashdata('message', lang("email_sent_msg"));
            }
            $direct_location = 1;
        }

        if ($direct_location == 1) {
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function order_ready($id = null) {

        $this->sma->checkPermissions('index');

        $_PID = $this->Settings->default_printer;
        $WarehouseId = $this->data['pos_settings']->default_eshop_warehouse;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        if (!$id) {
            die('No sale selected.');
        }
        $ArrData = array(
            'sale_status' => 'order_ready'
        );
        $this->orders_model->updateStatus($id, $ArrData);
        $inv = $this->orders_model->getOrderByID($id);

        $sms_code = md5('Reciept' . $inv->reference_no . $inv->id);
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);
        $RowWarehouse = $this->orders_model->getWarehouseByID($WarehouseId);

        //echo 'ssss'.$id;
        //print_r($this->data['customer']);
        $to = $this->data['customer']->email;
        $Phone = $this->data['customer']->phone;
        $BillerPhone = $RowWarehouse->phone;
        $Billerto = $RowWarehouse->email;
        $direct_location = 0;

        if ($Phone != '') {
//$str = 'Thanks for visiting '.$this->Settings->site_name.'. Your order has been ready. Your Total Bill amount '.$this->sma->formatDecimal($inv->grand_total).' '.$this->Settings->default_currency;
            $str = '';
            if ($inv->grand_total):
                $str = 'Hello, Your Order ID ' . $inv->invoice_no . ' of amount ' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency . ' is Ready.';
            endif;

            $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
            $url = $this->get_tiny_url(base_url('reciept/pdf_eshop_order/') . $sms_code);
            $msg = $str . ' Order Receipt ' . $url;
            $res = $this->sma->SendSMS($Phone, $msg, 'ESHOP_ORDER_READY');

            /* if(!empty($res)):
              $Obj = json_decode($res);
              //echo $Obj;
              if(isset($Obj) && $Obj->type=='success'):
              $rec['sms_log'] =  $this->sma->setSMSLog($mobile,$msg,$Obj->message);
              $rec['sms_balance_update'] = $this->sma->update_sms_count(1);
              echo '{"msg":"Your receipt has been successfully sent by SMS."} ';
              else:
              echo '{"msg": "'.$Obj->message.'"} ';
              endif;
              else:
              echo '{"msg": "Error , Please try again "} ';
              endif; */
            $direct_location = 1;
        }
        if ($BillerPhone != '') {
            $str = '';
            if ($inv->grand_total):
                $str = 'Receipt ' . $inv->invoice_no . ' order has been ready. Total Bill amount ' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency;
            endif;

            $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
            $url = $this->get_tiny_url(base_url('reciept/pdf_eshop_order/') . $sms_code);
            $msg = $str . ' You can view receipt   ' . $url;
            $res = $this->sma->SendSMS($BillerPhone, $msg, 'ORDER_READY_RECEIPT');

            /* if(!empty($res)):
              $Obj = json_decode($res);
              //echo $Obj;
              if(isset($Obj) && $Obj->type=='success'):
              $rec['sms_log'] =  $this->sma->setSMSLog($mobile,$msg,$Obj->message);
              $rec['sms_balance_update'] = $this->sma->update_sms_count(1);
              echo '{"msg":"Your receipt has been successfully sent by SMS."} ';
              else:
              echo '{"msg": "'.$Obj->message.'"} ';
              endif;
              else:
              echo '{"msg": "Error , Please try again "} ';
              endif; */
            $direct_location = 1;
        }
        if ($to != '') {
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $customer = $this->site->getCompanyByID($inv->customer_id);
            $biller = $this->site->getCompanyByID($inv->biller_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->invoice_no,
                'contact_person' => $customer->name,
                'company' => $customer->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
            );
            $msg = '<h3>{logo}</h3><h4>Order Details</h4><p>
    Hello {contact_person} ({company}),</p><p>Order ({reference_number}) has been ready. </p><p>Please find the attachment for order ({reference_number})
    details.</p><p>
    <a href="{client_link}"></a></p><p>
    Best regards,
    <br>
    {site_name}</p>';
            $message = $this->parser->parse_string($msg, $parse_data);
            $paypal = $this->sales_model->getPaypalSettings();
            $skrill = $this->sales_model->getSkrillSettings();
            $btn_code = '<div id="payment_buttons" class="text-center margin010">';
            if ($paypal->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_my / 100);
                } else {
                    $paypal_fee = $paypal->fixed_charges + ($inv->grand_total * $paypal->extra_charges_other / 100);
                }
                $btn_code .= '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=' . $paypal->account_email . '&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&image_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $paypal_fee) . '&no_shipping=1&no_note=1&currency_code=' . $this->default_currency->code . '&bn=FC-BuyNow&rm=2&return=' . site_url('sales/view/' . $inv->id) . '&cancel_return=' . site_url('sales/view/' . $inv->id) . '&notify_url=' . site_url('payments/paypalipn') . '&custom=' . $inv->reference_no . '__' . ($inv->grand_total - $inv->paid) . '__' . $paypal_fee . '"><img src="' . base_url('assets/images/btn-paypal.png') . '" alt="Pay by PayPal"></a> ';
            }
            if ($skrill->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_my / 100);
                } else {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_other / 100);
                }
                $btn_code .= ' <a href="https://www.moneybookers.com/app/payment.pl?method=get&pay_to_email=' . $skrill->account_email . '&language=EN&merchant_fields=item_name,item_number&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&logo_url=' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $skrill_fee) . '&return_url=' . site_url('sales/view/' . $inv->id) . '&cancel_url=' . site_url('sales/view/' . $inv->id) . '&detail1_description=' . $inv->reference_no . '&detail1_text=Payment for the sale invoice ' . $inv->reference_no . ': ' . $inv->grand_total . '(+ fee: ' . $skrill_fee . ') = ' . $this->sma->formatMoney($inv->grand_total + $skrill_fee) . '&currency=' . $this->default_currency->code . '&status_url=' . site_url('payments/skrillipn') . '"><img src="' . base_url('assets/images/btn-skrill.png') . '" alt="Pay by Skrill"></a>';
            }

            $btn_code .= '<div class="clearfix"></div></div>';
            $message = $message . $btn_code;

            $attachment = $this->eshoporderpdf($id, null, 'S');

            $subject = 'Receipt from ' . $this->data['biller']->company;
            $msg_shopkeeper = '<h3>{logo}</h3><h4>Sale Details</h4><p>
    Hello Shopkeeper,</p><p>Order ({reference_number}) has been ready. </p><p>Please find the attachment for order ({reference_number})
    details.</p><p>
    <a href="{client_link}"></a></p><p>
    Best regards,
    <br>
    {site_name}</p>';
            $message_shopkeeper = $this->parser->parse_string($msg_shopkeeper, $parse_data);
            //$this->sma->send_email($Billerto, $subject, $message_shopkeeper, null, null, $attachment, $cc, $bcc);
            if ($this->sma->send_email($to, $subject, $message, null, null, $attachment, $cc, $bcc)) {
                delete_files($attachment);
                //$this->session->set_flashdata('message', lang("email_sent_msg"));
            }
            $direct_location = 1;
        }

        if ($direct_location == 1) {
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function delete_payment($id = null) {
        $this->sma->checkPermissions('delete');

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        if ($this->orders_model->deletePayment($id)) {
            //echo lang("payment_deleted");
            $this->session->set_flashdata('message', lang("payment_deleted"));
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function order_items($warehouse_id = null) {

        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : null;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = $warehouse_id == null ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Order Items')));
        $meta = array('page_title' => lang('Order Items'), 'bc' => $bc);

        $this->page_construct('orders/order_items_list', $meta, $this->data);
    }

    public function get_order_items($warehouse_id = null, $pdf = NULL, $xls = NULL) {
        $this->sma->checkPermissions('index');

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }

        if ($pdf || $xls) {

            $this->db->select("{$this->db->dbprefix('order_items')}.product_code , "
                            . "{$this->db->dbprefix('order_items')}.product_name , "
                            . "{$this->db->dbprefix('product_variants')}.name AS varient_name, "
                            . "sum({$this->db->dbprefix('order_items')}.unit_quantity) AS unit_quantity, "
                            . "sum({$this->db->dbprefix('order_items')}.item_weight) AS item_weight ,"
                            . "{$this->db->dbprefix('product_variants')}.quantity AS in_stock")
                    ->from('order_items')
                    ->join('orders', 'orders.id=order_items.sale_id', 'left')
                    ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                    ->where(['orders.eshop_sale' => 1])
                    ->where("orders.sale_status NOT IN ('completed','cancelled')")
                    ->group_by('order_items.product_id, order_items.option_id');

            if ($warehouse_id) {
                $arrWr = explode(',', $warehouse_id);

                $this->db->where_in('warehouse_id', $arrWr);
            }

            if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
                $this->db->where('created_by', $this->session->userdata('user_id'));
            } elseif ($this->Customer) {
                $this->db->where('customer_id', $this->session->userdata('user_id'));
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
                $this->excel->getActiveSheet()->setTitle(lang('Order Items Quantity List'));
                $this->excel->getActiveSheet()->SetCellValue('A1', lang('Product Code'));
                $this->excel->getActiveSheet()->SetCellValue('B1', lang('Product Name'));
                $this->excel->getActiveSheet()->SetCellValue('C1', lang('Varient Name'));
                $this->excel->getActiveSheet()->SetCellValue('D1', lang('Units Order'));
                $this->excel->getActiveSheet()->SetCellValue('E1', lang('Item Weight'));
                $this->excel->getActiveSheet()->SetCellValue('F1', lang('Item In Stocks'));

                $row = 2;
                foreach ($data as $data_row) {
                    $this->excel->getActiveSheet()->SetCellValue('A' . $row, $data_row->product_code);
                    $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->product_name);
                    $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->varient_name);
                    $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->unit_quantity);
                    $this->excel->getActiveSheet()->SetCellValue('E' . $row, $data_row->item_weight);
                    $this->excel->getActiveSheet()->SetCellValue('F' . $row, $data_row->in_stock);

                    $row++;
                }

                $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(50);
                $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
                $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
                $filename = 'order_items_quantity';
                $hstyle = array(
                    'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    )
                );
                $this->excel->getDefaultStyle()->applyFromArray($hstyle);
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
                    $this->excel->getActiveSheet()->getStyle('C2:C' . $row)->getAlignment()->setWrapText(TRUE);
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
            $this->session->set_flashdata('error', lang('nothing_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->load->library('datatables');
            $arrWr = [];

            $this->datatables
                    ->select(" {$this->db->dbprefix('order_items')}.product_name AS product_name, "
                            . "{$this->db->dbprefix('product_variants')}.name AS variant_name, "
                            . "sum({$this->db->dbprefix('order_items')}.unit_quantity) AS unit_quantity, "
                            . "sum({$this->db->dbprefix('order_items')}.item_weight) AS item_weight ,"
                            . "IF( {$this->db->dbprefix('product_variants')}.quantity IS NULL , {$this->db->dbprefix('products')}.quantity , {$this->db->dbprefix('product_variants')}.quantity ) AS in_stock")
                    ->from('order_items')
                    ->join('orders', 'orders.id=order_items.sale_id', 'left')
                    ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                    ->join('products', 'products.id=order_items.product_id', 'left')
                    ->where(['orders.eshop_sale' => 1])
                    ->where("orders.sale_status NOT IN ('completed','cancelled')")
                    ->group_by('order_items.product_id, order_items.option_id');

            if ($warehouse_id) {
                $arrWr = explode(',', $warehouse_id);

                $this->datatables->where_in('warehouse_id', $arrWr);
            }

            if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
                $this->datatables->where('created_by', $this->session->userdata('user_id'));
            } elseif ($this->Customer) {
                $this->datatables->where('customer_id', $this->session->userdata('user_id'));
            }

            echo $this->datatables->generate();
        }
    }

    public function order_items_stocks($warehouse_id = null) {

        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : null;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = $warehouse_id == null ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Order Items Stocks')));
        $meta = array('page_title' => lang('Order Items Stocks'), 'bc' => $bc);

        $this->page_construct('orders/order_items_stocks', $meta, $this->data);
    }

    public function get_order_items_stocks($warehouse_id = null, $pdf = NULL, $xls = NULL) {
        $this->sma->checkPermissions('index');

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }

        if ($pdf || $xls) {

            $this->db->select("{$this->db->dbprefix('order_items')}.product_code , "
                            . "{$this->db->dbprefix('order_items')}.product_name , "
                            . "sum({$this->db->dbprefix('order_items')}.item_weight) AS item_weight ,"
                            . "{$this->db->dbprefix('products')}.quantity AS in_stock")
                    ->from('order_items')
                    ->join('orders', 'orders.id=order_items.sale_id', 'left')
                    ->where(['orders.eshop_sale' => 1, "orders.sale_status !=" => 'completed'])
                    ->group_by('order_items.product_id');

            if ($warehouse_id) {
                $arrWr = explode(',', $warehouse_id);

                $this->db->where_in('warehouse_id', $arrWr);
            }

            if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
                $this->db->where('created_by', $this->session->userdata('user_id'));
            } elseif ($this->Customer) {
                $this->db->where('customer_id', $this->session->userdata('user_id'));
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
                $this->excel->getActiveSheet()->setTitle(lang('Items Quantity Order'));
                $this->excel->getActiveSheet()->SetCellValue('A1', lang('Product Code'));
                $this->excel->getActiveSheet()->SetCellValue('B1', lang('Product Name'));
                $this->excel->getActiveSheet()->SetCellValue('C1', lang('Item Weight'));
                $this->excel->getActiveSheet()->SetCellValue('D1', lang('Item In Stocks'));

                $row = 2;
                foreach ($data as $data_row) {
                    $this->excel->getActiveSheet()->SetCellValue('A' . $row, $data_row->product_code);
                    $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->product_name);
                    $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->item_weight);
                    $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->in_stock);

                    $row++;
                }

                $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(50);
                $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(30);
                $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
                $filename = 'items_order_quantity';
                $hstyle = array(
                    'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                    )
                );
                $this->excel->getDefaultStyle()->applyFromArray($hstyle);
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
                    $this->excel->getActiveSheet()->getStyle('C2:C' . $row)->getAlignment()->setWrapText(TRUE);
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
            $this->session->set_flashdata('error', lang('nothing_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->load->library('datatables');
            $arrWr = [];

            $this->datatables
                    ->select("{$this->db->dbprefix('order_items')}.product_name AS product_name, "
                            . "sum({$this->db->dbprefix('order_items')}.item_weight) AS item_weight ,"
                            . "{$this->db->dbprefix('products')}.quantity AS in_stock")
                    ->from('order_items')
                    ->join('orders', 'orders.id=order_items.sale_id', 'left')
                    ->join('products', 'products.id=order_items.product_id', 'left')
                    ->where(['orders.eshop_sale' => 1, "orders.sale_status !=" => 'completed'])
                    ->group_by('order_items.product_id');

            if ($warehouse_id) {
                $arrWr = explode(',', $warehouse_id);

                $this->datatables->where_in('warehouse_id', $arrWr);
            }

            if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
                $this->datatables->where('created_by', $this->session->userdata('user_id'));
            } elseif ($this->Customer) {
                $this->datatables->where('customer_id', $this->session->userdata('user_id'));
            }

            echo $this->datatables->generate();
        }
    }

    /*     * *************************************************************************
      End Eshop Order
     * ************************************************************************* */
}

//end class
