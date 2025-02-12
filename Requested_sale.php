<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Requested_sale extends MY_Controller {

    public function __construct() {
        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->Supplier) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->lang->load('sales', $this->Settings->user_language);
        $this->load->library('form_validation');

        $this->load->model('sales_model');
        // $this->load->model('challan_model');
        $this->load->helper('text');
        $this->digital_upload_path = 'files/';
        $this->upload_path = 'assets/uploads/';
        $this->thumbs_path = 'assets/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->load->model('reports_model');
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';

        $this->load->model('pos_model');
        $this->pos_settings = $this->pos_model->getSetting();
        $this->pos_settings->pin_code = $this->pos_settings->pin_code ? md5($this->pos_settings->pin_code) : null;
        $this->data['pos_settings'] = $this->pos_settings;
        $this->data['pos_settings']->pos_theme = json_decode($this->pos_settings->pos_theme);

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

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Bulk Discount')));
        $meta = array('page_title' => lang('sales'), 'bc' => $bc);
        
        $this->page_construct('sales/requested_sale', $meta, $this->data);
    }
    public function getSales($warehouse_id = null) {
        $this->sma->checkPermissions('index');
       
        $start_date = $this->input->post('start_date');
        $end_date = $this->input->post('end_date');
        $discount = $this->input->post('discount') ? $this->input->post('discount') . '%' : '0%';
        $selected_sales = $this->input->post('selected_sales');
        $selected_sales_array = explode(',', $selected_sales);
        $selected_sales_str = implode(',', array_map('intval', $selected_sales_array));

        list($discount_amount, $discount_type) = $this->parse_discount($discount);
        
        if ($start_date) {
            $start_date = $this->sma->fld($start_date);
            $end_date = $this->sma->fld($end_date);
        }  
        $start_date_only = date('Y-m-d', strtotime($start_date));
        $end_date_only = date('Y-m-d', strtotime($end_date));
        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }
        $detail_link1 = anchor('pos/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('view_receipt'));
        $detail_link = anchor('sales/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('sale_details'));
        $duplicate_link = anchor('sales/add?sale_id=$1', '<i class="fa fa-plus-circle"></i> ' . lang('duplicate_sale'));
        $payments_link = anchor('sales/payments/$1', '<i class="fa fa-money"></i> ' . lang('view_payments'), 'data-toggle="modal" data-target="#myModal"');
        $add_payment_link = anchor('sales/add_payment/$1', '<i class="fa fa-money"></i> ' . lang('add_payment'), 'data-toggle="modal" data-target="#myModal"');
        $add_delivery_link = anchor('sales/add_delivery/$1', '<i class="fa fa-truck"></i> ' . lang('add_delivery'), 'data-toggle="modal" data-target="#myModal"');
        $email_link = anchor('sales/email/$1', '<i class="fa fa-envelope"></i> ' . lang('email_sale'), 'data-toggle="modal" data-target="#myModal"');
        $edit_link = anchor('sales/edit/$1', '<i class="fa fa-edit"></i> ' . lang('edit_sale'), 'class="sledit"');
        $pdf_link = anchor('sales/pdf/$1', '<i class="fa fa-file-pdf-o"></i> ' . lang('download_pdf'));
        $return_link = anchor('sales/return_sale/$1', '<i class="fa fa-angle-double-left"></i> ' . lang('return_sale'));
        $email_purchase_link = anchor('sales', '<i class="fa fa-envelope"></i> ' . lang('Email_Purchase_Excel'), ' class="email_receipt_purchase_excel" data-id="$1" data-email-address="$3"');
        $delete_link = "<a href='#' class='po' title='<b>" . lang("delete_sale") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('sales/delete/$1') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('delete_sale') . "</a>";
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
             <li>' . $email_purchase_link . '</li>
        </ul>
        </div></div>';
        //$action = '<div class="text-center">' . $detail_link . ' ' . $edit_link . ' ' . $email_link . ' ' . $delete_link . '</div>';

        $this->load->library('datatables');
        $arrWr = [];
        if ($warehouse_id) {
            
            $this->datatables->select($this->db->dbprefix('sales') . ".id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no, biller, customer, sale_status, 
            SUM(sma_sale_items.subtotal) + sma_sales.order_tax AS current_grand_total,
            CASE
            WHEN SUM(sma_sale_items.subtotal) + sma_sales.order_tax = 
                (SUM(CASE 
                    WHEN '$discount_type' = 'percentage' THEN 
                        (sma_sale_items.subtotal - (sma_sale_items.subtotal * ($discount_amount / 100))) 
                    WHEN '$discount_type' = 'fixed' THEN 
                        (sma_sale_items.subtotal - $discount_amount)
                    ELSE 
                        sma_sale_items.subtotal 
                END) + sma_sales.order_tax) THEN 0
            ELSE 
                SUM(CASE 
                    WHEN '$discount_type' = 'percentage' THEN 
                        (sma_sale_items.subtotal - (sma_sale_items.subtotal * ($discount_amount / 100))) 
                    WHEN '$discount_type' = 'fixed' THEN 
                        (sma_sale_items.subtotal - $discount_amount)
                    ELSE 
                        sma_sale_items.subtotal 
                END) + sma_sales.order_tax
        END AS new_grand_total")
        ->from('sales')
        ->join('companies', 'companies.id=sales.customer_id', 'left')
        ->join('sma_sale_items', 'sma_sale_items.sale_id=sales.id', 'left')
        ->where('sales.sale_status', 'completed')
        ->where('sales.payment_status', 'paid')
        ->group_by($this->db->dbprefix('sales') . '.id');
            $arrWr = explode(',', $warehouse_id);
            $this->datatables->where_in('sales.warehouse_id', $arrWr);
        } else {
            // $this->datatables->select($this->db->dbprefix('sales') . ".id, DATE_FORMAT(" . $this->db->dbprefix('sales') . ".date, '%Y-%m-%d %T' ) as date,  ($this->db->dbprefix('sales') .reference_no), biller, customer, sale_status, 
            $this->datatables->select($this->db->dbprefix('sales') . ".id, 
            DATE_FORMAT(" . $this->db->dbprefix('sales') . ".date, '%Y-%m-%d %T') as date, 
            " . $this->db->dbprefix('sales') . ".reference_no,  
            " . $this->db->dbprefix('sales') . ".biller, 
            " . $this->db->dbprefix('sales') . ".customer, 
            " . $this->db->dbprefix('sales') . ".sale_status, 
            SUM(sma_sale_items.subtotal) + sma_sales.order_tax AS current_grand_total,
            CASE
            WHEN SUM(sma_sale_items.subtotal) + sma_sales.order_tax = 
                (SUM(CASE 
                    WHEN '$discount_type' = 'percentage' THEN 
                        (sma_sale_items.subtotal - (sma_sale_items.subtotal * ($discount_amount / 100))) 
                    WHEN '$discount_type' = 'fixed' THEN 
                        (sma_sale_items.subtotal - $discount_amount)
                    ELSE 
                        sma_sale_items.subtotal 
                END) + sma_sales.order_tax) THEN 0
            ELSE 
                SUM(CASE 
                    WHEN '$discount_type' = 'percentage' THEN 
                        (sma_sale_items.subtotal - (sma_sale_items.subtotal * ($discount_amount / 100))) 
                    WHEN '$discount_type' = 'fixed' THEN 
                        (sma_sale_items.subtotal - $discount_amount)
                    ELSE 
                        sma_sale_items.subtotal 
                END) + sma_sales.order_tax
        END AS new_grand_total
        ")
            ->from('sales')
            ->join('companies', 'companies.id=sales.customer_id', 'left')
            ->join('sma_sale_items', 'sma_sale_items.sale_id=sales.id', 'left')
            ->join('sma_payments', 'sma_payments.sale_id = sales.id', 'left')
            ->join('sma_tax_rates', 'sma_tax_rates.id = sales.order_tax_id', 'left') // Join sma_tax_rates to get the tax rate
            ->where('sales.sale_status', 'completed')
            ->where('sales.payment_status', 'paid')
            ->where('sma_payments.paid_by', 'cash')
            ->group_by($this->db->dbprefix('sales') . '.id');

       
        }
        if ($start_date) {

           
            $this->datatables->where('DATE(' . $this->db->dbprefix('sales') . '.date) BETWEEN "' . $start_date_only . '" and "' . $end_date_only . '"');
        }
       
        // $this->datatables->where('pos =', 1); //->or_where('sale_status =', 'returned');
        $this->datatables->where('eshop_sale =', 0); //  skip eshop_sale
        $this->datatables->where('offline_sale =', 0); //  skip offline_sale
        $this->datatables->where('up_sales =', 0); //  skip offline_sale
        // $this->datatables->where('date', $start_date_only);
        // if ($selected_sales) {
        //     $this->db->where_in('sales.id', explode(',', $selected_sales));
        // }
        if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        } elseif ($this->Customer) {
            $this->datatables->where('customer_id', $this->session->userdata('user_id'));
        }
        // $this->datatables->add_column("Actions", $action, $this->db->dbprefix('sales') . ".id,sale_status,cemail");
        echo $this->datatables->generate();
    }
    public function updateGrandTotal() {
        $discount = $this->input->post('discount');
        $selected_sales = explode(',', $this->input->post('selected_sales'));
    
        // Your logic to update the grand total in the database
        // Loop through each selected sale and update the grand total based on the discount
        foreach ($selected_sales as $sale_id) {
            // Example of calculating the new grand total
            // This should be based on your business logic
            $this->db->set('grand_total', "grand_total - (grand_total * ($discount / 100))", FALSE);
            $this->db->where('id', $sale_id);
            $this->db->update('sales');
        }
    
        // Return success response
        echo json_encode(['success' => true]);
    }
    
    public function parse_discount($discount) {
        $discount = trim($discount); 
        $amount = 0;
        $type = 'fixed'; 

        // Check for percentage
        if (strpos($discount, '%') !== false) {
            $discount_value = rtrim($discount, '%'); // Remove '%' and trim
            if (is_numeric($discount_value)) {
                $amount = $discount_value; // Percentage value
                $type = 'percentage';
            }
        } else {
            // Check for fixed amounts
            $discount_value = preg_replace('/[^0-9.]/', '', $discount); // Remove non-numeric characters
            if (is_numeric($discount_value) && $discount_value > 0) {
                $amount = $discount_value; // Fixed amount
                $type = 'fixed';
            }
        }
        return [$amount, $type];
    }

    public function save_data() {
        // $discount = $this->input->post('discount')? $this->input->post('discount'): 0;
        $discount = $this->input->post('discount') ? $this->input->post('discount') . '%' : '0%';

        $all_invoices = $this->sales_model->getInvoiceByID_bulkDiscount($_POST['val']);
        if (empty($_POST['val'])) {
            $this->session->set_flashdata('error', lang("no_sale_selected"));
            redirect($_SERVER["HTTP_REFERER"]);
        }
     
        foreach ($_POST['val'] as $id) {
            $sales = $this->sales_model->getAllInvoiceItems($id);
            $sales_data = $this->sales_model->getInvoiceByID($id);
            
            foreach ($sales as $sale) {
                
                if (isset($sale->product_code) && isset($sale->real_unit_price) && isset($sale->net_unit_price) && isset($sale->quantity)) {
                    $product_details = $item_type != 'manual' ? $this->sales_model->getProductByCode($sale->product_code) : null;
                    $item_mrp = !empty($sale->mrp) ? $sale->mrp : $product_details->mrp;
                    $item_mrp = $this->sma->formatDecimal($sale->mrp);
                    $pr_discount = 0;
                    $percentage = '%';
                    if (isset($discount)) {
                        $discount = $discount;

                        $dpos = $this->parse_discount($discount,$type=null);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                        
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($sale->real_unit_price)) * (Float) ($pds[0])) / 100), 4);
                
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount, 4);
                        }
                    }
                  
                    $unit_discount = $pr_discount;
                    $item_unit_price_less_discount = ($sale->unit_price - $unit_discount);
                    //$item_unit_price_less_discount = $this->sma->formatDecimal($unit_price - $unit_discount); //17/05/19
    
                    $item_net_price = $item_unit_price_less_discount;
                    $pr_item_discount = $this->sma->formatDecimal($pr_discount * $sale->quantity);
                    $product_discount += $pr_item_discount;
                    $pr_tax = 0;
                    $pr_item_tax = 0;
                    $item_tax = 0;
                    $tax = '';
                    $tax_method = $product_details->tax_method;
                    $invoice_net_unit_price = 0;
                    $item_unit_quantity = $sale->quantity;
                    $item_quantity = $sale->quantity;

                    if (isset($sale->item_tax) && $sale->item_tax != 0) {

                        $item_tax = $sale->item_tax;
                        $pr_tax = $sale->tax_rate_id;
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
                    $subtotal = $sale->subtotal;
                    $selected_sales = $this->input->post('selected_sales');
                    list($discount_amount, $discount_type) = $this->parse_discount($discount);
                    $grand_total = 0;
                
                    if ($discount_type == 'percentage') {
                        $subtotal =  ($subtotal - ($subtotal * ($discount_amount / 100)));
                    } elseif ($discount_type == 'fixed') {
                        $subtotal = $subtotal - $discount_amount;
                    } else {
                        $subtotal = $subtotal;
                    }
                    
                    $products[] = array(
                        'product_id' => $sale->product_id,
                        'product_code' => $sale->product_code,
                        'article_code' => $product_details->article_code,
                        'product_name' => $sale->product_name,
                        'product_type' => $sale->product_type,
                        'option_id' => $sale->option_id,
                        'shade_id' => $sale->shade_id,
                        'net_unit_price' => $item_net_price,
                        'unit_price' => $this->sma->formatDecimal($item_net_price + $item_tax),
                        'quantity' => $sale->quantity,
                        'product_unit_id' => $sale->product_unit_id,
                        'product_unit_code' => $unit ? $unit->code : NULL,
                        'unit_quantity' => $sale->unit_quantity,
                        'warehouse_id' => $sale->warehouse_id,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'discount' => $discount,
                        'item_discount' => $pr_item_discount,
                        'subtotal' => $this->sma->formatDecimal($subtotal),
                        'serial_no' => $item_serial,
                        'real_unit_price' => $sale->real_unit_price,
                        'mrp' => $item_mrp,
                        'hsn_code' => $sale->hsn_code,
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
                        'discounts_summery' =>  serialize($discountSummery),
                        'seller_id' => ($salesperson)?$expsalesperson[0] :NULL,  
                        'seller' => ($salesperson)? $expsalesperson[1]:NULL,
                        'sale_id' => $sale->sale_id
                    );
    
                    // $total += $this->sma->formatDecimal(($unit_price * $item_quantity), 4);
                    $total += $this->sma->formatDecimal(($item_net_price * $item_quantity), 4); //17/05/19
                }
            }
            
            // order discount if apply
            // if ($sales_data->order_discount && !$sale->item_discount) {
                
            //     $order_discount_id = $sales_data->order_discount;
            //     $opos = strpos($order_discount_id, $percentage);
            //     if ($opos !== false) {
            //         $ods = explode("%", $order_discount_id);
            //         $order_discount = $this->sma->formatDecimal(((($total + $product_tax) * (Float) ($ods[0])) / 100), 4);
            //     } else {
            //         $order_discount = $this->sma->formatDecimal($order_discount_id);
            //     }
            // } else {
            //     $order_discount_id = null;
            // }
            // $total_discount = $this->sma->formatDecimal($order_discount + $product_discount);
            
            // // order tax if apply for invoice
            // if ($this->Settings->tax2) {
            //     $order_tax_id = $sales_data->order_tax_id;

            //     if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
            //         if ($order_tax_details->type == 2) {
            //             $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
            //         } elseif ($order_tax_details->type == 1) {
            //             $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
            //         }
            //     }
            // } else {
            //     $order_tax_id = null;
            // }

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 4);
            // $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping) - $order_discount), 4);
           
        }
        list($discount_amount, $discount_type) = $this->parse_discount($discount);
      
        foreach ($all_invoices as & $invoice) {
           
            $grand_total = 0;
            $item_discount = 0;
            $item_tax = 0;
            $order_tax_id = $this->site->getTaxRateByID($invoice['order_tax_id']);
            $order_tax = 0;
            $order_tax_calulated = 0;
            $i = 0;
            if (is_array($products)) {
                foreach ($products as $product) {
                    if ($product['sale_id'] == $invoice['id']) {
                        $grand_total = $grand_total + $product['subtotal'];
                        $item_discount = $item_discount + $product['item_discount'];
                        $item_tax = $item_tax + $product['item_tax'];
                    }                    
                }
            }else{
                if ($products['sale_id'] == $invoice->id) {
                    $grand_total = $grand_total + $products['subtotal'];
                    $item_discount = $item_discount + $products['item_discount'];
                }
            }
            $order_tax = $grand_total*($order_tax_id->rate/100);
            $order_tax_calulated = $grand_total + $order_tax;
            $invoice['total_discount'] = $item_discount + $discount;
            $invoice['product_discount'] = $item_discount;
            $invoice['order_discount'] = $sales_data->order_discount;
            $invoice['total'] = $grand_total;
            $invoice['product_tax'] = $item_tax;
            $invoice['order_tax'] = $order_tax;
            $invoice['grand_total'] = $order_tax_calulated;
            $invoice['total_tax'] = $item_tax+$order_tax;
        }
        
        if ($this->sales_model->updateSale_bulkDiscount($all_invoices, $products)) {
            $this->session->set_flashdata('message', lang("Sales Updated Successfully."));
            redirect("requested_sale");

        }else {
            $this->session->set_flashdata('error', lang('Something Went Wrong.'));
            redirect("requested_sale");
        }
    }



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
        $this->data['bank_detail'] = $this->pos_model->getBankForInvoice();
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
            if($Settings->default_printer=='4'){
                 $this->load->view($this->theme . 'sales/modal_view_amstead', $this->data);
            }else {
                $this->load->view($this->theme . 'sales/modal_view', $this->data);
            }
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
        if ($inv->eshop_sale == 1):
            $this->load->model('eshop_model');
            $this->data['eshop_order'] = $this->eshop_model->getOrderDetails(array('sale_id' => $inv->id));


        endif;

        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->sales_model->getPaymentsForSale($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);

        $this->data['created_by'] = $this->site->getUser($inv->created_by);

        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->sales_model->getAllInvoiceItems($id);
       // $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getInvoiceByID($inv->return_id) : NULL;
        //$this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllInvoiceItems($inv->return_id) : NULL;

        $return_sales = $inv->return_id ? $this->sales_model->getAllReturnInvoiceByID($id) : NULL;
		//print_r($return_sales);
		//echo '<br>';
		$product_discount=0;
		$product_tax=0;
		$total=0;
		$grand_total=0;
		$order_discount=0;
		$order_tax=0;
		$paid=0;
		if(!empty($return_sales)){
			foreach($return_sales as $Keys => $Vals){
				$product_discount=$product_discount+$Vals['product_discount'];
				$product_tax=$product_tax+$Vals['product_tax'];
				$total=$total+$Vals['total'];
				$grand_total=$grand_total+$Vals['grand_total'];
				$order_discount=$order_discount+$Vals['order_discount'];
				$order_tax=$order_tax+$Vals['order_tax'];
				$paid=$paid+$Vals['paid'];
				//echo '<br/>';
			}
			$this->data['return_sale'] = (object) array(
				'product_discount'=>$product_discount,
				'product_tax'=>$product_tax,
				'total'=>$total,
				'grand_total'=>$grand_total,
				'order_tax'=>$order_tax,
				'product_discount'=>$product_discount,
			);
		}
		//print_r($this->data['return_sale']); exit;
        $this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllReturnInvoiceItems($id) : NULL;
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
        $Settings = $this->site->get_setting();
        //$this->sma->checkPermissions();

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
        $this->data['bank_detail'] = $this->pos_model->getBankForInvoice();
        
       if($inv->eshop_sale){
           $this->data['shipping_details'] = $this->pos_model->getShipingDetails($inv->order_no); 
        }

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        
        if($Settings->default_printer == "4"){
            $html = $this->load->view($this->theme . 'sales/pdf_amstead', $this->data, true);
         } else {
            $html = $this->load->view($this->theme . 'sales/pdf_reciept', $this->data, true);
         }  

        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }


        if ($view) {
            if($Settings->default_printer == "4"){
                $this->load->view($this->theme . 'sales/pdf_amstead', $this->data);
            } else {
                 $this->load->view($this->theme . 'sales/pdf_reciept', $this->data);
            }   
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer, $this->data['biller']->invoice_footer);
        } else {
            $this->sma->generate_pdf($html, $name, false, $this->data['biller']->invoice_footer);
        } /* echo */
    }

    public function combine_pdf($sales_id) {
        $this->sma->checkPermissions('pdf');

        foreach ($sales_id as $id) {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $inv = $this->sales_model->getInvoiceByID($id);
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
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
            $html_data = $this->load->view($this->theme . 'sales/pdf', $this->data, true);
            if (!$this->Settings->barcode_img) {
                $html_data = preg_replace("'\<\?xml(.*)\?\>'", '', $html_data);
            }

            $html[] = array(
                'content' => $html_data,
                'footer' => $this->data['biller']->invoice_footer,
            );
        }

        $name = lang("sales") . ".pdf";
        $this->sma->generate_pdf($html, $name);
    }

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
                'logo' => '<img src="' . base_url() . 'assets/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
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
                $btn_code .= '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=' . $paypal->account_email . '&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&image_url=' . base_url() . 'assets/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $paypal_fee) . '&no_shipping=1&no_note=1&currency_code=' . $this->default_currency->code . '&bn=FC-BuyNow&rm=2&return=' . site_url('sales/view/' . $inv->id) . '&cancel_return=' . site_url('sales/view/' . $inv->id) . '&notify_url=' . site_url('payments/paypalipn') . '&custom=' . $inv->reference_no . '__' . ($inv->grand_total - $inv->paid) . '__' . $paypal_fee . '"><img src="' . base_url('assets/images/btn-paypal.png') . '" alt="Pay by PayPal"></a> ';
            }
            if ($skrill->active == "1" && $inv->grand_total != "0.00") {
                if (trim(strtolower($customer->country)) == $biller->country) {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_my / 100);
                } else {
                    $skrill_fee = $skrill->fixed_charges + ($inv->grand_total * $skrill->extra_charges_other / 100);
                }
                $btn_code .= ' <a href="https://www.moneybookers.com/app/payment.pl?method=get&pay_to_email=' . $skrill->account_email . '&language=EN&merchant_fields=item_name,item_number&item_name=' . $inv->reference_no . '&item_number=' . $inv->id . '&logo_url=' . base_url() . 'assets/uploads/logos/' . $this->Settings->logo . '&amount=' . (($inv->grand_total - $inv->paid) + $skrill_fee) . '&return_url=' . site_url('sales/view/' . $inv->id) . '&cancel_url=' . site_url('sales/view/' . $inv->id) . '&detail1_description=' . $inv->reference_no . '&detail1_text=Payment for the sale invoice ' . $inv->reference_no . ': ' . $inv->grand_total . '(+ fee: ' . $skrill_fee . ') = ' . $this->sma->formatMoney($inv->grand_total + $skrill_fee) . '&currency=' . $this->default_currency->code . '&status_url=' . site_url('payments/skrillipn') . '"><img src="' . base_url('assets/images/btn-skrill.png') . '" alt="Pay by Skrill"></a>';
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
            //redirect("sales");
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

    public function export_excel() {
        $_SESSION['Send_Excel'] = 0;
        $_SESSION['sale_id'] = '';
        $id = $this->uri->segment(3);
        $inv = $this->pos_model->getInvoiceByID($id);
        $sale_item = $this->sales_model->getAllInvoiceItems($id);
        $customer_id = $inv->customer_id;
        $customer = $this->pos_model->getCompanyByID($customer_id);
        $to = $customer->email;
        if ($this->input->get('email'))
            $to = $this->input->get('email');

        if ($to != '') {
            $this->load->library('excel');
            $this->excel->setActiveSheetIndex(0);
            $this->excel->getActiveSheet()->setTitle(lang('sales'));
            $this->excel->getActiveSheet()->SetCellValue('A1', lang('code'));
            $this->excel->getActiveSheet()->SetCellValue('B1', lang('name'));
            $this->excel->getActiveSheet()->SetCellValue('C1', lang('style'));
            $this->excel->getActiveSheet()->SetCellValue('D1', lang('net_unit_cost'));
            $this->excel->getActiveSheet()->SetCellValue('E1', lang('quantity'));
            $this->excel->getActiveSheet()->SetCellValue('F1', lang('size'));
            $this->excel->getActiveSheet()->SetCellValue('G1', lang('color'));
            $this->excel->getActiveSheet()->SetCellValue('H1', lang('Product_Type'));
            $this->excel->getActiveSheet()->SetCellValue('I1', lang('Tax_Percent'));
            $this->excel->getActiveSheet()->SetCellValue('J1', lang('Tax_Type'));
            $this->excel->getActiveSheet()->SetCellValue('K1', lang('discount'));
            $this->excel->getActiveSheet()->SetCellValue('L1', lang('expiry'));
            $this->excel->getActiveSheet()->SetCellValue('M1', lang('category_code'));
            $this->excel->getActiveSheet()->SetCellValue('N1', lang('subcategory_code'));
            $this->excel->getActiveSheet()->SetCellValue('O1', lang('brand'));
            $this->excel->getActiveSheet()->SetCellValue('P1', lang('unit'));
            $this->excel->getActiveSheet()->SetCellValue('Q1', lang('price'));
            $this->excel->getActiveSheet()->SetCellValue('R1', lang('MRP_Price'));
            $this->excel->getActiveSheet()->SetCellValue('S1', lang('Alert_Quantity'));
            $this->excel->getActiveSheet()->SetCellValue('T1', lang('hsn_code'));
            $this->excel->getActiveSheet()->SetCellValue('U1', lang('warehouse'));

            $row = 2;

            //echo '<pre>';
            //print_r($customer_arr);
            foreach ($sale_item as $item) {
                //echo $item->type_code;
                $options_color = $this->sales_model->getProductOptionsByShapeId($item->shade_id, $item->product_id, COLOR);

                $tax_rate = $this->pos_model->getTaxRateByID($item->tax_rate_id);
                $product_type = $this->pos_model->getProduct_typeByID($item->type_code);

                $product_details = $this->sales_model->getProductByCode($item->product_code);
                $categoey_code = $this->sales_model->getCategoryCode($product_details->category_id);
                $subcategory_code = $this->sales_model->getCategoryCode($product_details->subcategory_id);
                $brand_code = $this->sales_model->getProductBrand($product_details->brand);
                $unit_code = $this->sales_model->getUnitById($product_details->unit);

                //echo $product_type->product_type_name;
                //print_r($product_type);
                $this->excel->getActiveSheet()->setCellValueExplicit('A' . $row, $item->product_code, PHPExcel_Cell_DataType::TYPE_STRING);
                $this->excel->getActiveSheet()->SetCellValue('B' . $row, $product_details->name);
                $this->excel->getActiveSheet()->setCellValueExplicit('C' . $row, $item->article_code, PHPExcel_Cell_DataType::TYPE_STRING);
                $this->excel->getActiveSheet()->SetCellValue('D' . $row, $item->real_unit_price);
                $this->excel->getActiveSheet()->SetCellValue('E' . $row, $item->quantity);
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $item->variant);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $options_color[0]->name);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $product_type->product_type_name);
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $tax_rate->name);
                $this->excel->getActiveSheet()->SetCellValue('J' . $row, $item->tax_method);
				
                $this->excel->getActiveSheet()->setCellValueExplicit('K' . $row, $item->discount, PHPExcel_Cell_DataType::TYPE_STRING);
                $this->excel->getActiveSheet()->SetCellValue('L' . $row, '');

                $this->excel->getActiveSheet()->SetCellValue('M' . $row, ($categoey_code) ? $categoey_code->code . '|' . $categoey_code->name : '');
                $this->excel->getActiveSheet()->SetCellValue('N' . $row, ($subcategory_code) ? $subcategory_code->code . '|' . $subcategory_code->code : '');
                $this->excel->getActiveSheet()->SetCellValue('O' . $row, ($brand_code) ? $brand_code->code . '|' . $brand_code->name : '');
                $this->excel->getActiveSheet()->SetCellValue('P' . $row, ($unit_code) ? $unit_code->code : '');
                $this->excel->getActiveSheet()->SetCellValue('Q' . $row, '');
                $this->excel->getActiveSheet()->SetCellValue('R' . $row, $item->mrp);
                $this->excel->getActiveSheet()->SetCellValue('S' . $row, '');
                $this->excel->getActiveSheet()->SetCellValue('T' . $row, $item->hsn_code);
                $this->excel->getActiveSheet()->SetCellValue('U' . $row, '');

                $row++;
            }

            $filename = 'sales_' . date('Y_m_d_H_i_s');

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="assets/' . $filename . '.xls"');
            header('Cache-Control: max-age=0');

            $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
            $objWriter->save(str_replace(__FILE__, 'assets/' . $filename . '.xls', __FILE__));
            $attachment = 'assets/' . $filename . '.xls';
            $attachment1 = $this->pdf($id, null, 'S');
            $multi_attach = array($attachment, $attachment1);
            $subject = 'Purchase Excel';
            $biller = $this->site->getCompanyByID($inv->biller_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->reference_no,
                'contact_person' => $customer->name,
                'company' => $customer->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/uploads/logos/' . $biller->logo . '" alt="' . ($biller->company != '-' ? $biller->company : $biller->name) . '"/>',
            );
            if (file_exists('./themes/' . $this->theme . '/views/email_templates/sale.html')) {
                $sale_temp = file_get_contents('themes/' . $this->theme . '/views/email_templates/sale.html');
            } else {
                $sale_temp = file_get_contents('./themes/default/views/email_templates/sale.html');
            }

            $message = $this->parser->parse_string($sale_temp, $parse_data);

            if ($this->sma->send_email($to, $subject, $message, null, null, $multi_attach)) {
                $this->sma->send_json(array('msg' => $this->lang->line("email_sent")));
            } else {
                $this->sma->send_json(array('msg' => $this->lang->line("email_failed")));
            }
            unlink($attachment);
        }
    }

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

       
        $this->sma->storeDeletedData('sales', 'id', $id);
        if ($this->sales_model->deleteSale($id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("sale_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('sale_deleted'));
            redirect('welcome');
        }
    }


}
