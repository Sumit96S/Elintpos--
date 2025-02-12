<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Transfersnew extends MY_Controller {

    function __construct() {
        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->Customer || $this->Supplier) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->lang->load('transfers', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('transfers_model_new', 'transfers_model');
        $this->digital_upload_path = 'files/'.$this->Customer_assets;
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';
        $this->data['logo'] = true;
    }

    function index() {
        // $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('transfers')));
        $meta = array('page_title' => lang('transfers'), 'bc' => $bc);
        $this->page_construct('transfers_new/index', $meta, $this->data);
    }

    function getTransfers() {
        // $this->sma->checkPermissions('index');

        $detail_link = anchor('transfersnew/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('transfer_details'), 'data-toggle="modal" data-target="#myModal"');
        $email_link = anchor('transfersnew/email/$1', '<i class="fa fa-envelope"></i> ' . lang('email_transfer'), 'data-toggle="modal" data-target="#myModal"');
        $edit_link = anchor('transfersnew/edit/$1', '<i class="fa fa-edit"></i> ' . lang('edit_transfer'));
        $pdf_link = anchor('transfersnew/pdf/$1', '<i class="fa fa-file-pdf-o"></i> ' . lang('download_pdf'));
        $print_barcode = anchor('products/print_barcodes/?transfer=$1', '<i class="fa fa-print"></i> ' . lang('print_barcodes'));
        $delete_link = "<a href='#' class='tip po' title='<b>" . lang("delete_transfer") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' id='a__$1' href='" . site_url('transfersnew/delete/$1') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('delete_transfer') . "</a>";
        $action = '<div class="text-center"><div class="btn-group text-left">'
                . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">'
                . lang('actions') . ' <span class="caret"></span></button>
        <ul class="dropdown-menu pull-right" role="menu">
            <li>' . $detail_link . '</li>
            <li>' . $edit_link . '</li>
            <li>' . $pdf_link . '</li>
            <li>' . $email_link . '</li>
            <li>' . $print_barcode . '</li>
            <li>' . $delete_link . '</li>
        </ul>
    </div></div>';

        $this->load->library('datatables');

        $this->datatables
                ->select("id, date, transfer_no, from_warehouse_name as fname, from_warehouse_code as fcode, to_warehouse_name as tname,to_warehouse_code as tcode, total, total_tax, grand_total, status, attachment")
                ->from('transfers')
                ->edit_column("fname", "$1 ($2)", "fname, fcode")
                ->edit_column("tname", "$1 ($2)", "tname, tcode");

        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        }
        if ($this->session->userdata('view_right')) {
            if ($this->input->get('warehouse')) {
                $getwarehouse = str_replace("_", ",", $this->input->get('warehouse'));
                $this->datatables->where('to_warehouse_id IN (' . $getwarehouse . ')');
                $this->datatables->or_where('from_warehouse_id IN (' . $getwarehouse . ')');
            }
        }

        $this->datatables->add_column("Actions", $action, "id")
                ->unset_column('fcode')
                ->unset_column('tcode');
        echo $this->datatables->generate();
    }

    function add() {
        // $this->sma->checkPermissions();
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('to_warehouse', lang("warehouse") . ' (' . lang("to") . ')', 'required|is_natural_no_zero');
        $this->form_validation->set_rules('from_warehouse', lang("warehouse") . ' (' . lang("from") . ')', 'required|is_natural_no_zero');

        if ($this->form_validation->run()) {

            $transfer_no = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('to');
            if ($this->Owner || $this->Admin || $this->GP['transfers-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $to_warehouse = $this->input->post('to_warehouse');
            $from_warehouse = $this->input->post('from_warehouse');
            $note = $this->sma->clear_tags($this->input->post('note'));
            $shipping = $this->input->post('shipping');
            $status = $this->input->post('status');
            $fromWarehouseDetails = $this->site->getWarehouseByID($from_warehouse);
            $from_warehouse_details = $fromWarehouseDetails[$from_warehouse];
            $from_warehouse_code = $from_warehouse_details->code;
            $from_warehouse_name = $from_warehouse_details->name;
            $toWarehouseDetails = $this->site->getWarehouseByID($to_warehouse);
            $to_warehouse_details = $toWarehouseDetails[$to_warehouse];
            $to_warehouse_code = $to_warehouse_details->code;
            $to_warehouse_name = $to_warehouse_details->name;

            $total = 0;
            $product_tax = 0;

            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                $item_code = $_POST['product_code'][$r];
                $item_net_cost = $this->sma->formatDecimal($_POST['net_cost'][$r]);
                $unit_cost = $this->sma->formatDecimal($_POST['unit_cost'][$r]);
                $real_unit_cost = $this->sma->formatDecimal($_POST['real_unit_cost'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : NULL;
                $item_expiry = isset($_POST['expiry'][$r]) ? $this->sma->fsd($_POST['expiry'][$r]) : NULL;
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' && $_POST['product_option'][$r] != 'undefined' && $_POST['product_option'][$r] != 'null' ? $_POST['product_option'][$r] : NULL;
                $item_unit = $_POST['product_unit'][$r];
                $item_quantity = $_POST['product_base_quantity'][$r];

                if (isset($item_code) && isset($real_unit_cost) && isset($unit_cost) && isset($item_quantity)) {
                    $product_details = $this->transfers_model->getProductByCode($item_code);

                    // if (!$this->Settings->overselling) {
                    $warehouse_quantity = $this->transfers_model->getWarehouseProduct($from_warehouse_details->id, $product_details->id, $item_option);

                    if ($warehouse_quantity->quantity < $item_quantity) {
                        $this->session->set_flashdata('error', lang("no_match_found") . " (" . lang('product_name') . " <strong>" . $product_details->name . "</strong> " . lang('product_code') . " <strong>" . $product_details->code . "</strong>)");
                        redirect("transfersnew/add");
                    }
                    // }

                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                            }
                        } elseif ($tax_details->type == 2) {

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        } elseif ($tax_details->rate == 0) {
                            $item_tax = 0;
                            $tax = $tax_details->rate . "%";
                            if ($tax_details->type == 2)
                                $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                    } else {
                        $pr_tax = 0;
                        $pr_item_tax = 0;
                        $tax = "";
                    }

                    $item_net_cost = ($product_details && $product_details->tax_method == 1) ? $this->sma->formatDecimal($unit_cost) : $this->sma->formatDecimal($unit_cost - $item_tax, 4);
                    $product_tax += $pr_item_tax;
                    $subtotal = $this->sma->formatDecimal((($item_net_cost * $item_unit_quantity) + $pr_item_tax), 4);
                    $unit = $this->site->getUnitByID($item_unit);

                    $products[] = array(
                        'product_id' => $product_details->id,
                        'product_code' => $item_code,
                        'product_name' => $product_details->name,
                        'option_id' => $item_option,
                        'net_unit_cost' => $item_net_cost,
                        'unit_cost' => $this->sma->formatDecimal($item_net_cost + $item_tax, 4),
                        'quantity' => $item_quantity,
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit->code,
                        'unit_quantity' => $item_unit_quantity,
                        'quantity_balance' => $item_quantity,
                        'warehouse_id' => $to_warehouse,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'subtotal' => $this->sma->formatDecimal($subtotal),
                        'expiry' => $item_expiry,
                        'real_unit_cost' => $real_unit_cost,
                        'date' => date('Y-m-d', strtotime($date))
                    );

                    $total += $this->sma->formatDecimal(($item_net_cost * $item_unit_quantity), 4);
                }
            }
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }

            $grand_total = $this->sma->formatDecimal(($total + $shipping + $product_tax), 4);
            $data = array('transfer_no' => $transfer_no,
                'date' => $date,
                'from_warehouse_id' => $from_warehouse,
                'from_warehouse_code' => $from_warehouse_code,
                'from_warehouse_name' => $from_warehouse_name,
                'to_warehouse_id' => $to_warehouse,
                'to_warehouse_code' => $to_warehouse_code,
                'to_warehouse_name' => $to_warehouse_name,
                'note' => $note,
                'total_tax' => $product_tax,
                'total' => $total,
                'grand_total' => $grand_total,
                'created_by' => $this->session->userdata('user_id'),
                'status' => $status,
                'shipping' => $shipping
            );

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
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

        if ($this->form_validation->run() == true && $this->transfers_model->addTransfer($data, $products)) {
            $this->session->set_userdata('remove_tols', 1);
            $this->session->set_flashdata('message', lang("transfer_added"));
            redirect("transfersnew");
        } else {


            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['name'] = array('name' => 'name',
                'id' => 'name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('name'),
            );
            $this->data['quantity'] = array('name' => 'quantity',
                'id' => 'quantity',
                'type' => 'text',
                'value' => $this->form_validation->set_value('quantity'),
            );

            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['rnumber'] = ''; //$this->site->getReference('to');

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('transfers_new'), 'page' => lang('transfers_new')), array('link' => '#', 'page' => lang('add_transfer')));
            $meta = array('page_title' => lang('transfer_quantity'), 'bc' => $bc);
            $this->page_construct('transfers_new/add', $meta, $this->data);
        }
    }

    function edit($id = NULL) {
        // $this->sma->checkPermissions();
        $this->data['page_mode'] = 'edit';
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $transfer = $this->transfers_model->getTransferByID($id);
        $last_status = $transfer->status;
        if ($this->session->userdata('view_right') == '0') {
            if (!$this->session->userdata('edit_right')) {
                $this->sma->view_rights($transfer->created_by);
            }
        }
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('to_warehouse', lang("warehouse") . ' (' . lang("to") . ')', 'required|is_natural_no_zero');
        $this->form_validation->set_rules('from_warehouse', lang("warehouse") . ' (' . lang("from") . ')', 'required|is_natural_no_zero');

        if ($this->form_validation->run()) {

            $transfer_no = $this->input->post('reference_no');
            if ($this->Owner || $this->Admin || $this->GP['transfers-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $to_warehouse = $this->input->post('to_warehouse');
            $from_warehouse = $this->input->post('from_warehouse');
            $note = $this->sma->clear_tags($this->input->post('note'));
            $shipping = $this->input->post('shipping');
            $status = $this->input->post('status');

            if ($this->Owner || $this->Admin) { //echo 'aa';
            } else {
                /* if($status=='completed'){
                  if($this->session->userdata('user_id')!=$transfer->created_by){
                  $error = 'Only admin can edit transfer.';
                  $this->session->set_flashdata('error', $error);
                  redirect($_SERVER["HTTP_REFERER"]);
                  }
                  } */
                if ($status == 'pending') {
                    if ($this->session->userdata('user_id') != $transfer->created_by) {
                        $error = 'Only admin can edit transfer.';
                        $this->session->set_flashdata('error', $error);
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }

                if ($status == 'sent' || $status == 'sent_balance' || $status == 'partial') {
                    if ($this->session->userdata('user_id') == $transfer->created_by) {
                        $error = 'Only admin can edit transfer.';
                        $this->session->set_flashdata('error', $error);
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }
                if ($last_status == 'pending' && $status == 'sent') {
                    if ($this->session->userdata('user_id') != $transfer->created_by) {
                        $error = 'Only admin can edit transfer.';
                        $this->session->set_flashdata('error', $error);
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }
            }

            $fromWarehouseDetails = $this->site->getWarehouseByID($from_warehouse);
            $from_warehouse_details = $fromWarehouseDetails[$from_warehouse];
            $from_warehouse_code = $from_warehouse_details->code;
            $from_warehouse_name = $from_warehouse_details->name;

            $toWarehouseDetails = $this->site->getWarehouseByID($to_warehouse);
            $to_warehouse_details = $toWarehouseDetails[$to_warehouse];
            $to_warehouse_code = $to_warehouse_details->code;
            $to_warehouse_name = $to_warehouse_details->name;

            $total = 0;
            $total2 = 0;
            $product_tax = 0;
            $product_tax2 = 0;
            /* if($last_status==$status){
              $error = 'Status has been not changed';
              $this->session->set_flashdata('error', $error);
              redirect($_SERVER["HTTP_REFERER"]);
              } */
            /* if($status=='partial_completed'){
              $DataStatus['status']=$status;
              $this->db->update('transfers', $DataStatus, array('id' => $id));
              //echo 'fgf'; exit;
              $this->session->set_flashdata('message', lang('status_updated'));
              redirect("transfersnew");
              } */

            //if($last_status !='completed'){
            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {

                $item_code = $_POST['product_code'][$r];
                $item_net_cost = $this->sma->formatDecimal($_POST['net_cost'][$r]);
                $unit_cost = $this->sma->formatDecimal($_POST['unit_cost'][$r]);
                $real_unit_cost = $this->sma->formatDecimal($_POST['real_unit_cost'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];

                $quantity_balance = $_POST['quantity_balance'][$r];


                $ordered_quantity = $_POST['ordered_quantity'][$r];
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : NULL;
                $item_expiry = isset($_POST['expiry'][$r]) ? $this->sma->fsd($_POST['expiry'][$r]) : NULL;
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' && $_POST['product_option'][$r] != 'undefined' && $_POST['product_option'][$r] != 'null' ? $_POST['product_option'][$r] : NULL;
                $item_unit = $_POST['product_unit'][$r];
                $item_quantity = $_POST['product_base_quantity'][$r];
                $item_request_quantity = $_POST['request_quantity'][$r];
                $sent_quantity = $_POST['sent_quantity'][$r];

                if ($status == 'request') {
                    $item_unit_quantity = $item_request_quantity;
                    $ordered_quantity = 0;
                    $item_quantity = $item_request_quantity;
                } else if ($status == 'sent') {
                    if ($last_status != 'request') {
                        $ordered_quantity = $item_quantity;
                    }
                    if ($item_request_quantity != 0.00) {
                        if ($_POST['quantity'][$r] > ($item_request_quantity)) {
                            $error = 'Product quantity not more than balance quantity';
                            $this->session->set_flashdata('error', $error);
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    }
                } else if ($status == 'completed') {
                    if ($item_request_quantity != 0.00) {
                        if ($_POST['quantity'][$r] > ($item_request_quantity)) {
                            $error = 'Product quantity not more than balance quantity';
                            $this->session->set_flashdata('error', $error);
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    }
                } elseif ($status == 'sent_balance') {
                $pr_details = $this->transfers_model->getProductByCode($item_code);
                    $Res = $this->transfers_model->getPurchaseItemByID($id, $pr_details->id, $item_option);

                    if ($Res) {
                        $item_quantity = $item_quantity + $Res->quantity_balance;
                        if ($last_status == 'sent_balance')
                            $sent_quantity = $Res->quantity_balance;
                        $RequestQty = $Res->quantity_balance + $item_unit_quantity;
                        if ($RequestQty > ($item_request_quantity)) {
                            $error = 'Product quantity not more than balance quantity';
                            $this->session->set_flashdata('error', $error);
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    }
                }

                //if($status=='completed')
                $quantity_balance = $item_quantity;

                $total_send_quantity = 0;

                if ($status == 'completed') {
                    if ($sent_quantity == 0)
                        $total_send_quantity = ($sent_quantity + $item_unit_quantity );
                    else
                        $total_send_quantity = ($sent_quantity);
                } else {
                    if ($status == 'partial_completed') {
                        $total_send_quantity = $item_unit_quantity;
                    } else if ($status == 'partial') {
                        $total_send_quantity = $item_unit_quantity;
                    } else {
                        $total_send_quantity = ($sent_quantity + $item_unit_quantity );
                        if ($item_request_quantity != 0.00 && $status == 'sent')
                            $total_send_quantity = $item_unit_quantity;

                        if ($status == 'pending') {
                            $total_send_quantity = $item_unit_quantity;
                        }
                    }
                }
                if ($last_status != 'pending') {
                    if ($status != 'pending') {
                        if ($status != 'completed') {
                            if ($status != 'request') {
                                if ($last_status != 'sent') {
                                    if ($_POST['quantity'][$r] > ($item_request_quantity)) {
                                        $error = 'Product quantity not more than balance quantity';
                                        $this->session->set_flashdata('error', $error);
                                        redirect($_SERVER["HTTP_REFERER"]);
                                    }
                                }
                            }
                        }
                    }
                }


                if ($status == 'partial') {
                    if ($total_send_quantity == $item_request_quantity) {
                        $error = 'Request quantity and sent quantity are same, so, please select sent balance status and then do completed.';
                        $this->session->set_flashdata('error', $error);
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }

                //echo $total_send_quantity; exit;
                /*  else { */
                /* if($_POST['quantity'][$r] < ($item_request_quantity- $sent_quantity)){

                  $status =  'partial';
                  } */
                if (isset($item_code) && isset($real_unit_cost) && isset($unit_cost) && isset($item_quantity)) {
                    $product_details = $this->transfers_model->getProductByCode($item_code);
                    //echo $product_details->name;
                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                            }
                        } elseif ($tax_details->type == 2) {

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        } elseif ($tax_details->rate == 0) {
                            $item_tax = 0;
                            $tax = $tax_details->rate . "%";
                            if ($tax_details->type == 2)
                                $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                        $pr_item_tax2 = $this->sma->formatDecimal($item_tax * $total_send_quantity, 4);
                    } else {
                        $pr_tax = 0;
                        $pr_item_tax = 0;
                        $pr_item_tax2 = 0;
                        $tax = "";
                    }

                    $item_net_cost = ($product_details && $product_details->tax_method == 1) ? $this->sma->formatDecimal($unit_cost) : $this->sma->formatDecimal($unit_cost - $item_tax, 4);
                    $product_tax += $pr_item_tax;
                    $product_tax2 += $pr_item_tax2; //5/419
                    $subtotal = $this->sma->formatDecimal((($item_net_cost * $item_unit_quantity) + $pr_item_tax), 4);
                    $subtotal2 = $this->sma->formatDecimal((($item_net_cost * $total_send_quantity) + $pr_item_tax2), 4);
                    $unit = $this->site->getUnitByID($item_unit);

                    $balance_qty = ($status != 'completed' ) ? $item_quantity : ($item_quantity - ($ordered_quantity - $quantity_balance));

                    if ($item_request_quantity == 0) {
                        //echo 'a';
                        $complete_qty = $quantity_balance;
                    } else {
                        //echo 'b';
                        $complete_qty = $item_request_quantity;
                    }
                    //echo ' complete: '.$complete_qty;


                    $balance_qty = ($status != 'completed' ) ? $item_quantity : $complete_qty;
                    //echo $item_request_quantity.' rr <br>';
                    //echo 'main bal '.$balance_qty.' item '.$item_quantity.' order '.$ordered_quantity.' qty_balance '.$quantity_balance;

                    $total_send_quantity = !empty($total_send_quantity) ? $total_send_quantity : 0;

                    $products[] = array(
                        'product_id' => $product_details->id,
                        'product_code' => $item_code,
                        'product_name' => $product_details->name,
                        'option_id' => $item_option,
                        'net_unit_cost' => $item_net_cost,
                        'unit_cost' => $this->sma->formatDecimal(($item_net_cost + $item_tax), 4),
                        'quantity' => $item_quantity,
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit->code,
                        'unit_quantity' => $item_unit_quantity,
                        'quantity_balance' => $balance_qty,
                        'request_quantity' => $item_request_quantity,
                        'sent_quantity' => $total_send_quantity,
                        'warehouse_id' => $to_warehouse,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'subtotal' => $this->sma->formatDecimal($subtotal),
                        'expiry' => $item_expiry,
                        'real_unit_cost' => $real_unit_cost,
                        'date' => date('Y-m-d', strtotime($date)),
                    );

                    $total += $this->sma->formatDecimal(($item_net_cost * $item_unit_quantity), 4);
                    $total2 += $this->sma->formatDecimal(($item_net_cost * $total_send_quantity), 4);
                }
            }
            /* } */
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }
            //}

            $grand_total = $this->sma->formatDecimal(($total2 + $shipping + $product_tax2), 4);
            $data = array('transfer_no' => $transfer_no,
                'date' => $date,
                'from_warehouse_id' => $from_warehouse,
                'from_warehouse_code' => $from_warehouse_code,
                'from_warehouse_name' => $from_warehouse_name,
                'to_warehouse_id' => $to_warehouse,
                'to_warehouse_code' => $to_warehouse_code,
                'to_warehouse_name' => $to_warehouse_name,
                'note' => $note,
                'total_tax' => $product_tax2,
                'total' => $total2,
                'grand_total' => $grand_total,
                //'created_by' => $this->session->userdata('user_id'),
                'status' => $status,
                'shipping' => $shipping
            );
            //print_r($products); exit;
            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
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

        /* if($this->form_validation->run() == true && $last_status=='completed'){
          $status = $this->input->post('status');
          $note = $this->sma->clear_tags($this->input->post('note'));
          if($this->transfers_model->updateStatus($id, $status, $note)){
          $this->session->set_flashdata('message', lang('status_updated'));
          redirect("transfersnew");
          }

          } else */
        //echo '<pre>'; print_r($products); exit;
        if ($this->form_validation->run() == true && $this->transfers_model->updateTransfer($id, $data, $products)) {
            $this->session->set_userdata('remove_tols', 1);
            $this->session->set_flashdata('message', lang("transfer_updated"));
            //exit;
            redirect("transfersnew");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['transfer'] = $this->transfers_model->getTransferByID($id);

            $transfer_status = $this->data['transfer']->status;

            $transfer_items = $this->transfers_model->getAllTransferItems($id, $transfer_status);

            krsort($transfer_items);
            $c = rand(100000, 9999999);
            foreach ($transfer_items as $item) {

                $row = $this->site->getProductByID($item->product_id);
                //$get_qty = $this->db->select('quantity')->where(['product_id'=>$item->product_id,'warehouse_id'=>$this->data['transfer']->from_warehouse_id])->get('sma_warehouses_products')->row();


                if (!$row) {
                    $row = json_decode('{}');
                } else {
                    unset($row->details, $row->product_details, $row->image, $row->barcode_symbology, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->supplier1price, $row->supplier2price, $row->cfsupplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);

                    unset($row->alert_quantity, $row->article_code, $row->brand, $row->divisionid, $row->end_date, $row->file, $row->food_type_id, $row->hsn_code, $row->is_featured, $row->promo_price, $row->promotion, $row->ratings_avarage, $row->ratings_count, $row->start_date, $row->supplier3price, $row->up_items, $row->up_price, $row->updated_at, $row->weight, $row->warehouse, $row->track_quantity);
                }

                $row->expiry = (($item->expiry && $item->expiry != '0000-00-00') ? $this->sma->hrsd($item->expiry) : '');

                $item->sent_quantity = (float) $item->sent_quantity ? $item->sent_quantity : 0;

                if ($item->request_quantity) {
                    if ($transfer_status == 'sent') {
                        $row->base_quantity = $item->sent_quantity;
                    } else if ($transfer_status == 'pending') {
                        $row->base_quantity = $item->quantity;
                    } else if ($transfer_status == 'partial_completed') {
                        $row->base_quantity = $item->quantity;
                    } else {
                        $row->base_quantity = $item->request_quantity - $item->sent_quantity;
                        if ($row->base_quantity == 0.00) {
                            $row->base_quantity = $item->request_quantity;
                    }
                    }
                } else {
                    $row->base_quantity = $item->quantity;
                }

                //$row->base_quantity = ($item->request_quantity)?$item->request_quantity - $item->sent_quantity :$item->quantity;
                $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                $row->base_unit_cost = $row->cost ? $row->cost : $item->unit_cost;
                $row->unit = $item->product_unit_id;
                /* if($transfer_status=='partial' || $transfer_status=='request'){
                  $row->qty = ($item->request_quantity)?$item->request_quantity - $item->sent_quantity :$item->unit_quantity;
                  }else{
                  $row->qty = ($item->request_quantity)?$item->sent_quantity  :$item->unit_quantity;
                  } */
                $PrQtyBallance = 0;
                if ($transfer_status == 'partial_completed') {
                    $row->qty = $item->sent_quantity;
                } elseif ($transfer_status == 'completed') {
                    $row->qty = ($item->request_quantity) ? $item->sent_quantity : $item->unit_quantity;
                } elseif ($transfer_status == 'sent') {
                    $row->qty = ($item->request_quantity) ? $item->sent_quantity : $item->unit_quantity;
                } elseif ($transfer_status == 'sent_balance') {
                    $Res = $this->transfers_model->getPurchaseItemByID($id, $item->product_id, $item->option_id);

                    if ($Res) {
                        $PrQtyBallance = $Res->quantity_balance;
                    }

                    if ($item->request_quantity == $item->sent_quantity) {
                        $row->qty = ($item->request_quantity) ? $item->sent_quantity : $item->unit_quantity;
                    } else {
                        $row->qty = ($item->request_quantity) ? $item->request_quantity - $item->sent_quantity : $item->unit_quantity;
                    }
                } elseif ($transfer_status == 'pending') {
                    $row->qty = $item->unit_quantity;
                } else {
                    $row->qty = ($item->request_quantity) ? $item->request_quantity - $item->sent_quantity : $item->unit_quantity;
                }

                $row->PrQtyBallance = $item->request_quantity - $PrQtyBallance;
                $row->quantity_balance = $item->request_quantity - $item->sent_quantity;
                $row->ordered_quantity = ($item->request_quantity) ? $item->request_quantity : $item->quantity;
                $row->request_quantity = $item->request_quantity;
                $row->sent_quantity = $item->sent_quantity;
                $row->cost = $item->net_unit_cost;
                if ($item->quantity == 0)
                    $row->unit_cost = $item->net_unit_cost + (0);
                else
                    $row->unit_cost = $item->net_unit_cost + ($item->item_tax / $item->quantity);
                $row->real_unit_cost = $item->real_unit_cost;
                $row->tax_rate = $item->tax_rate_id;
                $row->option = $item->option_id;

                /* show stock quantity with variant option 19-09-2019 */
                if ($row->option) {
                    $get_qty = $this->db->select('quantity')->where(['product_id' => $row->id, 'warehouse_id' => $this->data['transfer']->from_warehouse_id, 'option_id' => $row->option])->get('sma_warehouses_products_variants')->row();
                } else {
                    $get_qty = $this->db->select('quantity')->where(['product_id' => $item->product_id, 'warehouse_id' => $this->data['transfer']->from_warehouse_id])->get('sma_warehouses_products')->row();
                }
                $row->quantity = $get_qty->quantity;
                /**/

                $options = $this->transfers_model->getProductOptions($row->id, $this->data['transfer']->from_warehouse_id, FALSE);

                //$pis = $this->site->getPurchasedItems($item->product_id, $item->warehouse_id, $item->option_id);
//                if ($pis) {
//                    foreach ($pis as $pi) {
//                        //$row->quantity += $pi->quantity_balance;
//                    }
//                }
                //$row->quantity += $item->quantity;
                $row->stockwarehouse2 = $this->getstockwarehousedata($this->data['transfer']->to_warehouse_id, $row->id, ($item->option_id) ? $item->option_id : NULL);

                if ($options) {
                    $option_quantity = 0;
                    foreach ($options as $option) {
                        $pis = $this->site->getPurchasedItems($row->id, $item->warehouse_id, $item->option_id);
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $option_quantity += $pi->quantity_balance;
                            }
                        }
                        $option->org_quantity = $option_quantity;
                        $option_quantity += $item->quantity;
                        if ($option->quantity > $option_quantity) {
                            $option->quantity = $option_quantity;
                        }
                    }
                }

                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                //$ri = $this->Settings->item_addition ? $row->id : $c;
                $ri = $row->id;
                if ($row->option)
                    $ri = $row->id . $row->option;
                $IdRow = $row->id;
                if ($row->option)
                    $IdRow = $row->id . $row->option;
                //'id' => $c
                $pr[$ri] = array('id' => $IdRow, 'item_id' => $row->id, 'status' => $transfer_status, 'option_id' => $row->option, 'label' => $row->name . " (" . $row->code . ")",
                    'row' => $row, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                $c++;
            }

            $this->data['transfer_items'] = json_encode($pr);
            $this->data['id'] = $id;
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('transfers_new'), 'page' => lang('transfers_new')), array('link' => '#', 'page' => lang('edit_transfer')));
            $meta = array('page_title' => lang('edit_transfer_quantity'), 'bc' => $bc);
            $this->data['sent_edit_transfer'] = 0;
            if ($this->Owner || $this->Admin) { //echo 'aa';
            } else {
                if ($transfer_status == 'sent' || $transfer_status == 'completed') {
                    if ($this->session->userdata('user_id') != $this->data['transfer']->created_by) {
                        $this->data['sent_edit_transfer'] = 1;
                    }
                }
            }

            $this->page_construct('transfers_new/edit', $meta, $this->data);
        }
    }

    function transfer_by_csv() {
        //$this->sma->checkPermissions('csv');
        // $this->sma->checkPermissions('index',TRUE);
        $this->load->helper('security');
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('to_warehouse', lang("warehouse") . ' (' . lang("to") . ')', 'required|is_natural_no_zero');
        $this->form_validation->set_rules('from_warehouse', lang("warehouse") . ' (' . lang("from") . ')', 'required|is_natural_no_zero');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run()) {

            $transfer_no = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('to');
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $to_warehouse = $this->input->post('to_warehouse');
            $from_warehouse = $this->input->post('from_warehouse');
            $note = $this->sma->clear_tags($this->input->post('note'));
            $shipping = $this->input->post('shipping');
            $status = $this->input->post('status');

            $fromWarehouseDetails = $this->site->getWarehouseByID($from_warehouse);
            $from_warehouse_details = $fromWarehouseDetails[$from_warehouse];
            $from_warehouse_code = $from_warehouse_details->code;
            $from_warehouse_name = $from_warehouse_details->name;

            $toWarehouseDetails = $this->site->getWarehouseByID($to_warehouse);
            $to_warehouse_details = $toWarehouseDetails[$to_warehouse];
            $to_warehouse_code = $to_warehouse_details->code;
            $to_warehouse_name = $to_warehouse_details->name;

            $total = 0;
            $product_tax = 0;

            if (isset($_FILES["userfile"])) {

                /* $config['upload_path'] = $this->digital_upload_path;
                  $config['allowed_types'] = 'csv';
                  $config['max_size'] = $this->allowed_file_size;
                  $config['overwrite'] = TRUE;

                  $this->load->library('upload', $config);
                  $this->upload->initialize($config);

                  if (!$this->upload->do_upload()) {

                  $error = $this->upload->display_errors();
                  $this->session->set_flashdata('error', $error);
                  redirect("transfers_new/transfer_bt_csv");
                  }

                  $csv = $this->upload->file_name;

                  $arrResult = array();
                  $handle = fopen($this->digital_upload_path . $csv, "r");
                  if ($handle) {
                  while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                  $arrResult[] = $row;
                  }
                  fclose($handle);
                  }
                  $titles = array_shift($arrResult); */
                $this->load->library('excel');
                $File = $_FILES['userfile']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                //$reader= PHPExcel_IOFactory::createReader('Excel2007');
                $reader->setReadDataOnly(true);
                $path = $File; //"./uploads/upload.xlsx";
                $excel = $reader->load($path);

                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);
                //print_r($sheet);
                $arrayCount = count($sheet);
                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                    // echo $sheet[$i]["A"].$sheet[$i]["B"].$sheet[$i]["C"].$sheet[$i]["D"].$sheet[$i]["E"];
                }
                $keys = array('product', 'net_cost', 'quantity', 'variant', 'expiry');
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }

                $rw = 2;
                foreach ($final as $csv_pr) {

                    $item_code = $csv_pr['product'];
                    $item_net_cost = $csv_pr['net_cost'];
                    $item_quantity = $csv_pr['quantity'];
                    $variant = isset($csv_pr['variant']) ? $csv_pr['variant'] : NULL;
                    $item_expiry = isset($csv_pr['expiry']) ? date('Y-m-d', strtotime($csv_pr['expiry'])) : NULL; //isset($csv_pr['expiry']) ? $this->sma->fsd($csv_pr['expiry']) : NULL;

                    if (isset($item_code) && isset($item_net_cost) && isset($item_quantity)) {
                        if (!($product_details = $this->transfers_model->getProductByCode($item_code))) {
                            $this->session->set_flashdata('error', lang("pr_not_found") . " ( " . $csv_pr['product'] . " ). " . lang("line_no") . " " . $rw);
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                        if ($variant) {
                            $item_option = $this->transfers_model->getProductVariantByName($variant, $product_details->id);
                            if (!$item_option) {
                                $this->session->set_flashdata('error', lang("pr_not_found") . " ( " . $csv_pr['product'] . " - " . $csv_pr['variant'] . " ). " . lang("line_no") . " " . $rw);
                                redirect($_SERVER["HTTP_REFERER"]);
                            }
                        } else {
                            $item_option = json_decode('{}');
                            $item_option->id = NULL;
                        }

                        if (!$this->Settings->overselling) {
                            $warehouse_quantity = $this->transfers_model->getWarehouseProduct($from_warehouse_details->id, $product_details->id, $item_option->id);
                            if ($warehouse_quantity->quantity < $item_quantity) {
                                $this->session->set_flashdata('error', lang("no_match_found") . " (" . lang('product_name') . " <strong>" . $product_details->name . "</strong> " . lang('product_code') . " <strong>" . $product_details->code . "</strong>) " . lang("line_no") . " " . $rw);
                                redirect($_SERVER["HTTP_REFERER"]);
                            }
                        }
                        if (isset($product_details->tax_rate)) {
                            $pr_tax = $product_details->tax_rate;
                            $tax_details = $this->site->getTaxRateByID($pr_tax);
                            // New Method update 8/04/19
                            if ($tax_details->type == 1 && $tax_details->rate != 0) {
                                if ($product_details && $product_details->tax_method == 1) {
                                    $item_tax = $this->sma->formatDecimal((($item_net_cost) * $tax_details->rate) / 100, 4);
                                    $tax = $tax_details->rate . "%";
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($item_net_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                    $tax = $tax_details->rate . "%";
                                }
                            } elseif ($tax_details->type == 2) {

                                $item_tax = $this->sma->formatDecimal($tax_details->rate);
                                $tax = $tax_details->rate;
                            }
                            $product_tax = $this->sma->formatDecimal($item_tax * $item_quantity, 4);
                            // End 8/04/19

                            /* if ($tax_details->type == 1 && $tax_details->rate != 0) {  //old method
                              $item_tax = ((($item_quantity * $item_net_cost) * $tax_details->rate) / 100);
                              $product_tax += $item_tax;
                              } else {
                              $item_tax = $tax_details->rate;
                              $product_tax += $item_tax;
                              }

                              if ($tax_details->type == 1)
                              $tax = $tax_details->rate . "%";
                              else
                              $tax = $tax_details->rate; */
                        } else {
                            $pr_tax = 0;
                            $item_tax = 0;
                            $tax = "";
                        }
                        $item_net_cost = ($product_details && $product_details->tax_method == 1) ? $this->sma->formatDecimal($item_net_cost) : $this->sma->formatDecimal($item_net_cost - $item_tax, 4);

                        $subtotal = (($item_net_cost * $item_quantity) + $item_tax);

                        $products[] = array(
                            'product_id' => $product_details->id,
                            'product_code' => $item_code,
                            'product_name' => $product_details->name,
                            'option_id' => $item_option->id,
                            'net_unit_cost' => $item_net_cost,
                            'quantity' => $item_quantity,
                            'quantity_balance' => $item_quantity,
                            'unit_quantity' => $item_quantity,
                            'item_tax' => $item_tax,
                            'tax_rate_id' => $pr_tax,
                            'tax' => $tax,
                            'expiry' => $item_expiry,
                            'subtotal' => $subtotal,
                            'real_unit_cost' => $this->sma->formatDecimal($item_net_cost + ($item_tax / $item_quantity))
                        );

                        $total += $item_net_cost * $item_quantity;
                    }
                    $rw++;
                }
            }

            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_item"), 'required');
            } else {
                krsort($products);
            }
            $grand_total = $total + $shipping + $product_tax;
            $data = array('transfer_no' => $transfer_no,
                'date' => $date,
                'from_warehouse_id' => $from_warehouse,
                'from_warehouse_code' => $from_warehouse_code,
                'from_warehouse_name' => $from_warehouse_name,
                'to_warehouse_id' => $to_warehouse,
                'to_warehouse_code' => $to_warehouse_code,
                'to_warehouse_name' => $to_warehouse_name,
                'note' => $note,
                'total_tax' => $product_tax,
                'total' => $total,
                'grand_total' => $grand_total,
                'created_by' => $this->session->userdata('user_id'),
                'status' => $status,
                'shipping' => $shipping
            );

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
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

        if ($this->form_validation->run() == true && $this->transfers_model->addTransfer($data, $products)) {
            $this->session->set_userdata('remove_tols', 1);
            $this->session->set_flashdata('message', lang("transfer_added"));
            redirect("transfers_new");
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['name'] = array('name' => 'name',
                'id' => 'name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('name'),
            );
            $this->data['quantity'] = array('name' => 'quantity',
                'id' => 'quantity',
                'type' => 'text',
                'value' => $this->form_validation->set_value('quantity'),
            );

            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['rnumber'] = $this->site->getReference('to');

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('transfers_new'), 'page' => lang('transfers_new')), array('link' => '#', 'page' => lang('transfer_by_csv')));
            $meta = array('page_title' => lang('add_transfer_by_csv'), 'bc' => $bc);
            $this->page_construct('transfers_new/transfer_by_csv', $meta, $this->data);
        }
    }

    function view($transfer_id = NULL) {
        // $this->sma->checkPermissions('index', TRUE);

        if ($this->input->get('id')) {
            $transfer_id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $transfer = $this->transfers_model->getTransferByID($transfer_id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($transfer->created_by, true);
        }
        $this->data['rows'] = $this->transfers_model->getAllTransferItems($transfer_id, $transfer->status);
        $fromWarehouse = $this->site->getWarehouseByID($transfer->from_warehouse_id);
        $toWarehouse = $this->site->getWarehouseByID($transfer->to_warehouse_id);
        $this->data['from_warehouse'] = $fromWarehouse[$transfer->from_warehouse_id];
        $this->data['to_warehouse'] = $toWarehouse[$transfer->to_warehouse_id];
        $this->data['transfer'] = $transfer;
        $this->data['tid'] = $transfer_id;
        $this->data['created_by'] = $this->site->getUser($transfer->created_by);
        $this->load->view($this->theme . 'transfers_new/view', $this->data);
    }

    function pdf($transfer_id = NULL, $view = NULL, $save_bufffer = NULL) {
        if ($this->input->get('id')) {
            $transfer_id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $transfer = $this->transfers_model->getTransferByID($transfer_id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($transfer->created_by);
        }
        $this->data['rows'] = $this->transfers_model->getAllTransferItems($transfer_id, $transfer->status);
        $fromWarehouse = $this->site->getWarehouseByID($transfer->from_warehouse_id);
        $toWarehouse = $this->site->getWarehouseByID($transfer->to_warehouse_id);
        $this->data['from_warehouse'] = $fromWarehouse[$transfer->from_warehouse_id];
        $this->data['to_warehouse'] = $toWarehouse[$transfer->to_warehouse_id];
        $this->data['transfer'] = $transfer;
        $this->data['tid'] = $transfer_id;
        $this->data['created_by'] = $this->site->getUser($transfer->created_by);
        $name = lang("transfer") . "_" . str_replace('/', '_', $transfer->transfer_no) . ".pdf";
        $html = $this->load->view($this->theme . 'transfers_new/pdf', $this->data, TRUE);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }
        if ($view) {
            $this->load->view($this->theme . 'transfers_new/pdf', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer);
        } else {
            $this->sma->generate_pdf($html, $name);
        }
    }

    public function combine_pdf($transfers_id) {
        // $this->sma->checkPermissions('pdf');

        foreach ($transfers_id as $transfer_id) {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $transfer = $this->transfers_model->getTransferByID($transfer_id);
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($transfer->created_by);
            }
            $this->data['rows'] = $this->transfers_model->getAllTransferItems($transfer_id, $transfer->status);
            $fromWarehouse = $this->site->getWarehouseByID($transfer->from_warehouse_id);
            $toWarehouse = $this->site->getWarehouseByID($transfer->to_warehouse_id);
            $this->data['from_warehouse'] = $fromWarehouse[$transfer->from_warehouse_id];
            $this->data['to_warehouse'] = $toWarehouse[$transfer->to_warehouse_id];
            $this->data['transfer'] = $transfer;
            $this->data['tid'] = $transfer_id;
            $this->data['created_by'] = $this->site->getUser($transfer->created_by);

            $html[] = array(
                'content' => $this->load->view($this->theme . 'transfers_new/pdf', $this->data, TRUE),
                'footer' => '',
            );
        }

        $name = lang("transfers") . ".pdf";
        $this->sma->generate_pdf($html, $name);
    }

    function email($transfer_id = NULL) {
        // $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $transfer_id = $this->input->get('id');
        }
        $transfer = $this->transfers_model->getTransferByID($transfer_id);
        //$this->form_validation->set_rules('to', lang("to") . " " . lang("email"), 'trim|required|valid_email');
        $this->form_validation->set_rules('subject', lang("subject"), 'trim|required');
        $this->form_validation->set_rules('cc', lang("cc"), 'trim|valid_emails');
        $this->form_validation->set_rules('bcc', lang("bcc"), 'trim|valid_emails');
        $this->form_validation->set_rules('note', lang("message"), 'trim');

        if ($this->form_validation->run() == true) {
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($transfer->created_by);
            }
            $to = $this->input->post('to');
            $subject = $this->input->post('subject');
            if ($this->input->post('cc')) {
                $cc = $this->input->post('cc');
            } else {
                $cc = NULL;
            }
            if ($this->input->post('bcc')) {
                $bcc = $this->input->post('bcc');
            } else {
                $bcc = NULL;
            }

            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $transfer->transfer_no,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '" alt="' . $this->Settings->site_name . '"/>'
            );
            $msg = $this->input->post('note');
            $message = $this->parser->parse_string($msg, $parse_data);
            //$name = lang("transfer") . "_" . str_replace('/', '_', $transfer->transfer_no) . ".pdf";
            //$file_content = $this->pdf($transfer_id, NULL, 'S');
            //$attachment = array('file' => $file_content, 'name' => $name, 'mime' => 'application/pdf');
            $attachment = $this->pdf($transfer_id, NULL, 'S'); //delete_files($attachment);
        } elseif ($this->input->post('send_email')) {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->session->set_flashdata('error', $this->data['error']);
            redirect($_SERVER["HTTP_REFERER"]);
        }


        if ($this->form_validation->run() == true && $this->sma->send_email($to, $subject, $message, NULL, NULL, $attachment, $cc, $bcc)) {
            delete_files($attachment);
            //$this->session->set_flashdata('message', lang("email_sent"));
            //redirect("transfers_new");
            $this->session->set_flashdata('message', lang("email_sent_msg"));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            if (file_exists('./themes/' . $this->theme . '/views/email_templates/transfer.html')) {
                $transfer_temp = file_get_contents('themes/' . $this->theme . '/views/email_templates/transfer.html');
            } else {
                $transfer_temp = file_get_contents('./themes/default/views/email_templates/transfer.html');
            }
            $this->data['subject'] = array('name' => 'subject',
                'id' => 'subject',
                'type' => 'text',
                'value' => $this->form_validation->set_value('subject', lang('transfer_order') . ' (' . $transfer->transfer_no . ') ' . lang('from') . ' ' . $transfer->from_warehouse_name),
            );
            $this->data['note'] = array('name' => 'note',
                'id' => 'note',
                'type' => 'text',
                'value' => $this->form_validation->set_value('note', $transfer_temp),
            );
            $towarehouse = $this->site->getWarehouseByID($transfer->to_warehouse_id);
            $this->data['warehouse'] = $towarehouse[$transfer->to_warehouse_id];

            $this->data['id'] = $transfer_id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'transfers_new/email', $this->data);
        }
    }

    function delete($id = NULL) {
        // $this->sma->checkPermissions(NULL, TRUE);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        if ($this->transfers_model->deleteTransfer($id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("transfer_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('transfer_deleted'));
            redirect('transfersnew');
        }
    }

    function suggestions() {
        // $this->sma->checkPermissions('index', TRUE);
        $term = $this->input->get('term', TRUE);
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $warehouse2 = $this->input->get('warehouse_2', TRUE);

        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }

        $exp = explode("_", $term); // Using Barcode

        $analyzed = $this->sma->analyze_term($term);
        $sr = $analyzed['term'];
        $option_id = $analyzed['option_id'];

        $rows = $this->transfers_model->getProductNames($sr, $warehouse_id);
        if ($rows) {
            $c = str_replace(".", "", microtime(true));
            $r = 0;
            foreach ($rows as $row) {
                // $option = FALSE;
                $option = ($exp[1]) ? $exp[1] : false; // Using Barcode Scan time
                $row->quantity = 0;
                $row->item_tax_method = $row->tax_method;
                $row->base_quantity = 1;
                $row->base_unit = $row->unit;
                $row->base_unit_cost = $row->cost;
                $row->unit = $row->purchase_unit ? $row->purchase_unit : $row->unit;
                $row->qty = 1;
                $row->discount = '0';
                $row->expiry = '';
                $row->quantity_balance = 0;
                $row->ordered_quantity = 0;
                $options = $this->transfers_model->getProductOptions($row->id, $warehouse_id);
                if ($options) {
                    $opt = $option_id && $r == 0 ? $this->transfers_model->getProductOptionByID($option_id) : $options[0];
                    if (!$option_id || $r > 0) {
                        $option_id = $opt->id;
                    }
                } else {
                    $opt = json_decode('{}');
                    $opt->cost = 0;
                    $option_id = 0;
                }
                $row->option = $option_id;
                /* $pis = $this->site->getPurchasedItems($row->id, $warehouse_id, $row->option);
                  if($pis){
                  foreach ($pis as $pi) {
                  $row->quantity += $pi->quantity_balance;
                  }
                  } */

                /* $get_qty = $this->db->select('quantity')->where(['product_id'=>$row->id,'warehouse_id'=>$warehouse_id])->get('sma_warehouses_products')->row(); */

                if ($option_id) {
                    $get_qty = $this->db->select('quantity')->where(['product_id' => $row->id, 'warehouse_id' => $warehouse_id, 'option_id' => $row->option])->get('sma_warehouses_products_variants')->row();
                } else {
                    $get_qty = $this->db->select('quantity')->where(['product_id' => $row->id, 'warehouse_id' => $warehouse_id])->get('sma_warehouses_products')->row();
                }

                $row->quantity = $get_qty->quantity;
                $row->stockwarehouse2 = $this->getstockwarehousedata($warehouse2, $row->id, $option_id);

                if ($options) {
                    $option_quantity = 0;
                    foreach ($options as $option) {
                        $pis = $this->site->getPurchasedItems($row->id, $warehouse_id, $row->option);
                        if ($pis) {
                            foreach ($pis as $pi) {
                                $option_quantity += $pi->quantity_balance;
                            }
                        }
                        $option->org_quantity = $option_quantity;
                        if ($option->quantity > $option_quantity) {
                            $option->quantity = $option_quantity;
                        }
                    }
                }
                if ($opt->cost != 0) {
                    $row->cost = $opt->cost;
                }
                $row->real_unit_cost = $row->cost;
                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                //'id' => ($c + $r)
                $Id = $row->id;
                if ($option_id)
                    $Id = $row->id . $option_id;
                $pr[] = array('id' => $Id, 'item_id' => $row->id, 'option_id' => $option_id, 'label' => $row->name . " (" . $row->code . ")",
                    'row' => $row, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                $r++;
            }
            $this->sma->send_json($pr);
        } else {
            $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
        }
    }

    function transfer_actions() {
        if (!$this->Owner) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == true) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {

                    foreach ($_POST['val'] as $id) {
                        $this->transfers_model->deleteTransfer($id);
                    }
                    $this->session->set_flashdata('message', lang("transfers_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'combine') {

                    $html = $this->combine_pdf($_POST['val']);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:H1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:H1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Transfers');

                    $this->excel->getActiveSheet()->setTitle(lang('transfers'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('date'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('reference_no'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('from_warehouse'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('to_warehouse'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('Taxable amount'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('Total GST tax'));
                    $this->excel->getActiveSheet()->SetCellValue('G2', lang('grand_total'));
                    $this->excel->getActiveSheet()->SetCellValue('H2', lang('status'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $tansfer = $this->transfers_model->getTransferByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($tansfer->date));
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $tansfer->transfer_no);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $tansfer->from_warehouse_name);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $tansfer->to_warehouse_name);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $tansfer->total);
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $tansfer->total_tax);
                        $this->excel->getActiveSheet()->SetCellValue('G' . $row, $tansfer->grand_total);
                        $this->excel->getActiveSheet()->SetCellValue('H' . $row, $tansfer->status);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'transfers_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
                        $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                        $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                        require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
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
                $this->session->set_flashdata('error', lang("no_transfer_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function update_status($id) {

        $this->form_validation->set_rules('status', lang("status"), 'required');

        if ($this->form_validation->run() == true) {
            $status = $this->input->post('status');
            $note = $this->sma->clear_tags($this->input->post('note'));
        } elseif ($this->input->post('update')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'sales');
        }

        if ($this->form_validation->run() == true && $this->transfers_model->updateStatus($id, $status, $note)) {
            $this->session->set_flashdata('message', lang('status_updated'));
            redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'sales');
        } else {

            $this->data['inv'] = $this->transfers_model->getTransferByID($id);
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'transfers_new/update_status', $this->data);
        }
    }

    /* --------------------------------- Product List -------------------------------------- */

    public function getTransferProduct($warehouse_id = NULL, $werehouse_id_2 = NULL) {
        $rows = $this->transfers_model->getTransferProductList($warehouse_id, $werehouse_id_2);
        echo json_encode($rows);
    }

    /* --------------------------------- Product List -------------------------------------- */

    public function getstockwarehouse() {
        $get_data = $this->input->get();
        // $this->db->select('quantity as werehouse_2_quantity')
        //         ->where(['product_id'=>$get_data['product'],'warehouse_id'=>$get_data['warehouse2']]);       
        //        $sql = $this->db->get('warehouses_products')->row();

        if ($get_data['vartient']) {
            $this->db->select('quantity as werehouse_2_quantity')
                    ->where(['product_id' => $get_data['product'], 'warehouse_id' => $get_data['warehouse2'], 'option_id' => $get_data['vartient']]);
            $sql = $this->db->get('warehouses_products_variants')->row();
        } else {
            $this->db->select('quantity as werehouse_2_quantity')
                    ->where(['product_id' => $get_data['product'], 'warehouse_id' => $get_data['warehouse2']]);
            $sql = $this->db->get('warehouses_products')->row();
        }
        echo json_encode($sql->werehouse_2_quantity);
    }

    public function getstockwarehousedata($warehouse, $productid, $option) {


        if ($option != NULL) {
            $this->db->select('quantity as werehouse_2_quantity')
                    ->where(['product_id' => $productid, 'warehouse_id' => $warehouse, 'option_id' => $option]);
            $sql = $this->db->get('warehouses_products_variants')->row();
        } else {
            $this->db->select('quantity as werehouse_2_quantity')
                    ->where(['product_id' => $productid, 'warehouse_id' => $warehouse]);
            $sql = $this->db->get('warehouses_products')->row();
        }

//        $this->db->last_query();
//     
//        echo json_encode($sql->werehouse_2_quantity);

        return $sql->werehouse_2_quantity;
    }

    // Add Request 2/4/19
    function request() {

        //  $this->sma->checkPermissions('index',TRUE);
        $this->form_validation->set_message('is_natural_no_zero', lang("no_zero_required"));
        $this->form_validation->set_rules('to_warehouse', lang("warehouse") . ' (' . lang("to") . ')', 'required|is_natural_no_zero');
        $this->form_validation->set_rules('from_warehouse', lang("warehouse") . ' (' . lang("from") . ')', 'required|is_natural_no_zero');

        if ($this->form_validation->run()) {

            $transfer_no = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('to');
            if ($this->Owner || $this->Admin || $this->GP['transfers-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $to_warehouse = $this->input->post('to_warehouse');
            $from_warehouse = $this->input->post('from_warehouse');
            $note = $this->sma->clear_tags($this->input->post('note'));
            $shipping = $this->input->post('shipping');
            if ($shipping == '')
                $shipping = 0;
            $status = $this->input->post('status');

            $fromWarehouseDetails = $this->site->getWarehouseByID($from_warehouse);
            $from_warehouse_details = $fromWarehouseDetails[$from_warehouse];
            $from_warehouse_code = $from_warehouse_details->code;
            $from_warehouse_name = $from_warehouse_details->name;

            $toWarehouseDetails = $this->site->getWarehouseByID($to_warehouse);
            $to_warehouse_details = $toWarehouseDetails[$to_warehouse];
            $to_warehouse_code = $to_warehouse_details->code;
            $to_warehouse_name = $to_warehouse_details->name;

            $total = 0;
            $product_tax = 0;

            $i = isset($_POST['product_code']) ? sizeof($_POST['product_code']) : 0;
            for ($r = 0; $r < $i; $r++) {
                $item_code = $_POST['product_code'][$r];
                $item_net_cost = $this->sma->formatDecimal($_POST['net_cost'][$r]);
                $unit_cost = $this->sma->formatDecimal($_POST['unit_cost'][$r]);
                $real_unit_cost = $this->sma->formatDecimal($_POST['real_unit_cost'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];
                $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : NULL;
                $item_expiry = isset($_POST['expiry'][$r]) ? $this->sma->fsd($_POST['expiry'][$r]) : NULL;
                $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' && $_POST['product_option'][$r] != 'undefined' && $_POST['product_option'][$r] != 'null' ? $_POST['product_option'][$r] : NULL;
                $item_unit = $_POST['product_unit'][$r];
                $item_quantity = $_POST['product_base_quantity'][$r];

                if (isset($item_code) && isset($real_unit_cost) && isset($unit_cost) && isset($item_quantity)) {
                    $product_details = $this->transfers_model->getProductByCode($item_code);
                    // if (!$this->Settings->overselling) {
                    /* $warehouse_quantity = $this->transfers_model->getWarehouseProduct($from_warehouse_details->id, $product_details->id, $item_option);
                      if ($warehouse_quantity->quantity < $item_quantity) {
                      $this->session->set_flashdata('error', lang("no_match_found") . " (" . lang('product_name') . " <strong>" . $product_details->name . "</strong> " . lang('product_code') . " <strong>" . $product_details->code . "</strong>)");
                      redirect("transfers_new/add");
                      } */
                    // }
                    $warehouse_quantity = $this->transfers_model->getWarehouseProduct($from_warehouse_details->id, $product_details->id, $item_option);
                    /* if ($warehouse_quantity->quantity < $item_unit_quantity) {
                      $this->session->set_flashdata('error', "Request stock should be less than or equal to stock. (" . lang('product_name') . " <strong>" . $product_details->name . "</strong> " . lang('product_code') . " <strong>" . $product_details->code . "</strong>)");
                      redirect("transfersnew/request");
                      } */
                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $pr_tax = $item_tax_rate;
                        //echo '<br/>'; 
                        $item_tax = 0;
                        $tax = "";
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $product_details->tax_method == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                            }
                        } elseif ($tax_details->type == 2) {

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                    } else {
                        $pr_tax = 0;
                        $pr_item_tax = 0;
                        $tax = "";
                    }

                    $item_net_cost = ($product_details && $product_details->tax_method == 1) ? $this->sma->formatDecimal($unit_cost) : $this->sma->formatDecimal($unit_cost - $item_tax, 4);
                    $product_tax += $pr_item_tax;
                    $subtotal = $this->sma->formatDecimal((($item_net_cost * $item_unit_quantity) + $pr_item_tax), 4);
                    $unit = $this->site->getUnitByID($item_unit);

                    $products[] = array(
                        'product_id' => $product_details->id,
                        'product_code' => $item_code,
                        'product_name' => $product_details->name,
                        'option_id' => $item_option,
                        'net_unit_cost' => $item_net_cost,
                        'unit_cost' => $this->sma->formatDecimal($item_net_cost + $item_tax, 4),
                        'quantity' => $item_quantity,
                        'request_quantity' => $item_unit_quantity,
                        'product_unit_id' => $item_unit,
                        'product_unit_code' => $unit->code,
                        'unit_quantity' => $item_unit_quantity,
                        'quantity_balance' => $item_quantity,
                        'warehouse_id' => $to_warehouse,
                        'item_tax' => $pr_item_tax,
                        'tax_rate_id' => $pr_tax,
                        'tax' => $tax,
                        'subtotal' => $this->sma->formatDecimal($subtotal),
                        'expiry' => $item_expiry,
                        'real_unit_cost' => $real_unit_cost,
                        'date' => date('Y-m-d', strtotime($date))
                    );

                    $total += $this->sma->formatDecimal(($item_net_cost * $item_unit_quantity), 4);
                }
            }
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
            }
            //echo $total.' ff '.$shipping.' gg '.$product_tax;
            $grand_total = $this->sma->formatDecimal(($total + $shipping + $product_tax), 4);
            $data = array('transfer_no' => $transfer_no,
                'date' => $date,
                'from_warehouse_id' => $from_warehouse,
                'from_warehouse_code' => $from_warehouse_code,
                'from_warehouse_name' => $from_warehouse_name,
                'to_warehouse_id' => $to_warehouse,
                'to_warehouse_code' => $to_warehouse_code,
                'to_warehouse_name' => $to_warehouse_name,
                'note' => $note,
                'total_tax' => $product_tax,
                'total' => $total,
                'grand_total' => $grand_total,
                'created_by' => $this->session->userdata('user_id'),
                'status' => $status,
                'shipping' => $shipping
            );

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
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
        //echo '<pre>';
        //print_r($products);
//exit;
        if ($this->form_validation->run() == true && $this->transfers_model->addTransfer($data, $products)) {
            $this->session->set_userdata('remove_tols', 1);
            $this->session->set_flashdata('message', lang("transfer_added"));
            redirect("transfersnew");
        } else {


            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['name'] = array('name' => 'name',
                'id' => 'name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('name'),
            );
            $this->data['quantity'] = array('name' => 'quantity',
                'id' => 'quantity',
                'type' => 'text',
                'value' => $this->form_validation->set_value('quantity'),
            );

            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['rnumber'] = ''; //$this->site->getReference('to');

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('transfers_new'), 'page' => lang('transfers_new')), array('link' => '#', 'page' => lang('Add Request')));
            $meta = array('page_title' => lang('transfer_quantity'), 'bc' => $bc);


            $this->page_construct('transfers_new/add_request', $meta, $this->data);
        }
    }

    public function getQuantity() {

        $warehouse_id = array();
        $vartient = $this->input->get('vartient', TRUE);
        $warehouse_id[0] = $this->input->get('from_warehouse', TRUE);
        $warehouse_id[1] = $this->input->get('to_warehouse', TRUE);

        $rows = $this->transfers_model->getVariantQuantity($vartient, $warehouse_id);

        echo json_encode($rows);
    }

    // End Request
}
