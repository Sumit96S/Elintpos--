<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchases extends MY_Controller {

    public function __construct() {
        parent::__construct();
        
        $authentication_methods = array('pdf_view', 'pdf');
        if (!in_array($this->router->fetch_method(), $authentication_methods)) {
            if (!$this->loggedIn) {
                $this->session->set_userdata('requested_page', $this->uri->uri_string());
                $this->sma->md('login');
            }
        }
        if ($this->Customer) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        
        $this->lang->load('purchases', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('purchases_model');
        $this->load->model('products_model');
        $this->load->model('pos_model');
        
        $this->digital_upload_path = 'files/'.$this->Customer_assets;
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';
        $this->data['logo'] = true;
        
        $this->pos_settings = $this->pos_model->getSetting();
        $this->pos_settings->pin_code = $this->pos_settings->pin_code ? md5($this->pos_settings->pin_code) : null;
        $this->data['pos_settings'] = $this->pos_settings;
        $this->data['pos_settings']->pos_theme = json_decode($this->pos_settings->pos_theme);
    }

    public function index($warehouse_id = null) {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : null;
        } else {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id == null ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByID($warehouse_id) : null;
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('purchases')));
        $meta = array('page_title' => lang('purchases'), 'bc' => $bc);
        $this->page_construct('purchases/index', $meta, $this->data);
    }

    public function getPurchases($warehouse_id = null) {
        $this->sma->checkPermissions('index');

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }
        $duplicate_link = anchor('purchases/add?purchase_id=$1', '<i class="fa fa-plus-circle"></i> ' . lang('duplicate_purchases'));

        $detail_link = anchor('purchases/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('purchase_details'));
        $payments_link = anchor('purchases/payments/$1', '<i class="fa fa-money"></i> ' . lang('view_payments'), 'data-toggle="modal" data-target="#myModal"');
        
               
        $add_payment_link = anchor('purchases/add_payment/$1', '<i class="fa fa-money"></i> ' . lang('add_payment'), 'data-toggle="modal" data-target="#myModal"');

        $edit_link = anchor('purchases/edit/$1', '<i class="fa fa-edit"></i> ' . lang('edit_purchase'));
            
        $email_link = anchor('purchases/email/$1', '<i class="fa fa-envelope"></i> ' . lang('email_purchase'), 'data-toggle="modal" data-target="#myModal"');
        $pdf_link = anchor('purchases/pdf/$1', '<i class="fa fa-file-pdf-o"></i> ' . lang('download_pdf'));
        $sms_link = anchor('reciept/purchase_sms/$1', '<i class="fa fa-send"></i> ' . lang('SMS Purchases'), 'class="send_quote_sms" purchase_id="$1"');
        $print_barcode = anchor('products/print_barcodes/?purchase=$1', '<i class="fa fa-print"></i> ' . lang('print_barcodes'));
        $return_link = anchor('purchases/return_purchase/$1', '<i class="fa fa-angle-double-left"></i> ' . lang('return_purchase'));
        $delete_link = "<a href='#' class='po' title='<b>" . $this->lang->line("delete_purchase") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('purchases/delete/$1/$2') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('delete_purchase') . "</a>";            
        $action = '<div class="text-center row_status_$2"><div class="btn-group text-left">'
                . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">'
                . lang('actions') . ' <span class="caret"></span></button>
                <ul class="dropdown-menu pull-right" role="menu">
                    <li class="link_view_$2">' . $detail_link . '</li>
                    <li class="link_duplicate_$2">' . $duplicate_link . '</li>
                    <li class="link_view_payment_$2 link_view_payment_$3">' . $payments_link . '</li>
                    <li class="link_add_payment_$2 link_add_payment_$3">' . $add_payment_link . '</li>
                    <li class="link_edit_$2">' . $edit_link . '</li>
                    <li class="link_pdf_$2">' . $pdf_link . '</li>
                    <li class="link_sms_$2">' . $sms_link . '</li>
                    <li class="link_email_$2">' . $email_link . '</li>
                    <li class="link_print_barcode_$2">' . $print_barcode . '</li>
                    <li class="link_return_$2">' . $return_link . '</li>
                    <li class="link_delete_$2">' . $delete_link . '</li>
                </ul>
            </div></div>';
        //$action = '<div class="text-center">' . $detail_link . ' ' . $edit_link . ' ' . $email_link . ' ' . $delete_link . '</div>';

        $this->load->library('datatables');
        if ($warehouse_id) {
            $getwarehouse = str_replace("_", ",", $warehouse_id);

            /* $this->datatables
              ->select("id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no, supplier, status, (grand_total+rounding), paid, ((grand_total+rounding)-paid) as balance, payment_status, attachment")
              ->from('purchases')
              ->where('warehouse_id IN ('.$getwarehouse.')'); */
            $this->datatables
                    ->select("{$this->db->dbprefix('purchases')}.id as id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no, CONCAT(" . $this->db->dbprefix('companies') . ".company, '(', supplier,')') as supplier, status, (grand_total+rounding), paid, ((grand_total+rounding)-paid) as balance, payment_status, attachment")
                    ->from('purchases')
                    ->where('warehouse_id IN (' . $getwarehouse . ')')->join('companies', 'sma_companies.id=purchases.supplier_id', 'left');
        } else {

            /* $this->datatables
              ->select("id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no, supplier, status, (grand_total+rounding), paid, ((grand_total+rounding)-paid) as balance, payment_status, attachment")
              ->from('purchases'); */

            $this->datatables
                    ->select("{$this->db->dbprefix('purchases')}.id as id, DATE_FORMAT(date, '%Y-%m-%d %T') as date, reference_no, CONCAT(" . $this->db->dbprefix('companies') . ".company, '(', supplier,')') as supplier, status, (grand_total+rounding), paid, ((grand_total+rounding)-paid) as balance, payment_status, attachment")
                    ->from('purchases')->join('companies', 'sma_companies.id=purchases.supplier_id', 'left');
        }
        // $this->datatables->where('status !=', 'returned');
        if (!$this->Customer && !$this->Supplier && !$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        } elseif ($this->Supplier) {
            $this->datatables->where('supplier_id', $this->session->userdata('user_id'));
        }
        
        $this->datatables->where('status !=', 'deleted');
        
        $this->datatables->add_column("Actions", $action, "id,status,payment_status");
       
        echo $this->datatables->generate();
    }

    public function modal_view($purchase_id = null) {
        $this->sma->checkPermissions('index', true);

        if ($this->input->get('id')) {
            $purchase_id = $this->input->get('id');
        }

        $exp_product = explode('_', $purchase_id);
        if (isset($exp_product[1])) {
            $this->data['products_id'] = $exp_product[1];
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->purchases_model->getPurchaseByID($purchase_id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by, true);
        }
        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->purchases_model->getAllTaxItems($inv->id, $inv->return_id);
        endif;
        $this->data['biller'] = $this->site->getCompanyByID($this->Settings->default_biller);
        $this->data['taxItems'] = $this->purchases_model->getAllTaxItemsGroup($purchase_id, $inv->return_id);
        $this->data['rows'] = $this->purchases_model->getAllPurchaseItems($purchase_id);
        $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['payments'] = $this->purchases_model->getPaymentsForPurchase($purchase_id);
        $this->data['created_by'] = $this->site->getUser($inv->created_by);
        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['return_purchase'] = $inv->return_id ? $this->purchases_model->getPurchaseByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->purchases_model->getAllPurchaseItems($inv->return_id) : NULL;

        if (isset($this->data['products_id'])) {
            $this->load->view($this->theme . 'purchases/modal_view_products', $this->data);
        } else {
            $this->load->view($this->theme . 'purchases/modal_view', $this->data);
        }
    }

    public function view($purchase_id = null) {
        $this->sma->checkPermissions('index');

        if ($this->input->get('id')) {
            $purchase_id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->purchases_model->getPurchaseByID($purchase_id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }
        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->purchases_model->getAllTaxItems($inv->id, $inv->return_id);
        endif;
        $this->data['biller'] = $this->site->getCompanyByID($this->Settings->default_biller);
        $this->data['rows'] = $this->purchases_model->getAllPurchaseItems($purchase_id);
        $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['payments'] = $this->purchases_model->getPaymentsForPurchase($purchase_id);
        $this->data['created_by'] = $this->site->getUser($inv->created_by);
        $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
        $this->data['return_purchase'] = $inv->return_id ? $this->purchases_model->getPurchaseByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->purchases_model->getAllPurchaseItems($inv->return_id) : NULL;
        $this->data['taxItems'] = $this->purchases_model->getAllTaxItemsGroup($purchase_id, $inv->return_id);

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('purchases'), 'page' => lang('purchases')), array('link' => '#', 'page' => lang('view')));
        $meta = array('page_title' => lang('view_purchase_details'), 'bc' => $bc);
        $this->page_construct('purchases/view', $meta, $this->data);
    }

    //generate pdf and force to download
    public function pdf_view($purchase_id = null, $view = null, $save_bufffer = null) {

        $res = $this->purchases_model->validateRecieptPurchase($code);
        //print_r($res);
        if (!$res) {
            
        }
        $purchase_id = $id = isset($res[0]['id']) ? $res[0]['id'] : FALSE;
        if (!$purchase_id) {
            die('No quote selected.');
        }

        $this->pdf($purchase_id);
    }

    public function pdf($purchase_id = null, $view = null, $save_bufffer = null) {

        if ($this->input->get('id')) {
            $purchase_id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->purchases_model->getPurchaseByID($purchase_id);

        //$this->data['payments'] = $this->purchases_model->getPaymentsForPurchase($purchase_id);

        /* if (!$this->session->userdata('view_right')) {
          $this->sma->view_rights($inv->created_by);
          } */
        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->purchases_model->getAllTaxItems($inv->id, $inv->return_id);
        endif;
        $this->data['biller'] = $this->site->getCompanyByID($this->Settings->default_biller);
        $this->data['taxItems'] = $this->purchases_model->getAllTaxItemsGroup($purchase_id, $inv->return_id);
        $this->data['rows'] = $this->purchases_model->getAllPurchaseItems($purchase_id);
        $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['created_by'] = $this->site->getUser($inv->created_by);
        $this->data['inv'] = $inv;
        $this->data['return_purchase'] = $inv->return_id ? $this->purchases_model->getPurchaseByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->purchases_model->getAllPurchaseItems($inv->return_id) : NULL;
        $name = $this->lang->line("purchase") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        $html = $this->load->view($this->theme . 'purchases/pdf', $this->data, true);
        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }
        if ($view) {
            $this->load->view($this->theme . 'purchases/pdf', $this->data);
        } elseif ($save_bufffer) {
            return $this->sma->generate_pdf($html, $name, $save_bufffer);
        } else {
            $this->sma->generate_pdf($html, $name);
        }
    }

    public function combine_pdf($purchases_id) {
        $this->sma->checkPermissions('pdf');

        foreach ($purchases_id as $purchase_id) {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $inv = $this->purchases_model->getPurchaseByID($purchase_id);
            if (!$this->session->userdata('view_right')) {
                $this->sma->view_rights($inv->created_by);
            }
            $this->data['taxItems'] = $this->purchases_model->getAllTaxItemsGroup($purchase_id, $inv->return_id);
            $this->data['rows'] = $this->purchases_model->getAllPurchaseItems($purchase_id);
            $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
            $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
            $this->data['created_by'] = $this->site->getUser($inv->created_by);
            $this->data['inv'] = $inv;
            $this->data['return_purchase'] = $inv->return_id ? $this->purchases_model->getPurchaseByID($inv->return_id) : NULL;
            $this->data['return_rows'] = $inv->return_id ? $this->purchases_model->getAllPurchaseItems($inv->return_id) : NULL;
            $inv_html = $this->load->view($this->theme . 'purchases/pdf', $this->data, true);
            if (!$this->Settings->barcode_img) {
                $inv_html = preg_replace("'\<\?xml(.*)\?\>'", '', $inv_html);
            }
            $html[] = array(
                'content' => $inv_html,
                'footer' => '',
            );
        }

        $name = lang("purchases") . ".pdf";
        $this->sma->generate_pdf($html, $name);
    }

    public function email($purchase_id = null) {

        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $purchase_id = $this->input->get('id');
        }

        $inv = $this->purchases_model->getPurchaseByID($purchase_id);
        $this->form_validation->set_rules('to', $this->lang->line("to") . " " . $this->lang->line("email"), 'trim|required|valid_email');
        $this->form_validation->set_rules('subject', $this->lang->line("subject"), 'trim|required');
        $this->form_validation->set_rules('cc', $this->lang->line("cc"), 'trim|valid_emails');
        $this->form_validation->set_rules('bcc', $this->lang->line("bcc"), 'trim|valid_emails');
        $this->form_validation->set_rules('note', $this->lang->line("message"), 'trim');

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
            $supplier = $this->site->getCompanyByID($inv->supplier_id);
            $this->load->library('parser');
            $parse_data = array(
                'reference_number' => $inv->reference_no,
                'contact_person' => $supplier->name,
                'company' => $supplier->company,
                'site_link' => base_url(),
                'site_name' => $this->Settings->site_name,
                'logo' => '<img src="' . base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/logos/' . $this->Settings->logo . '" alt="' . $this->Settings->site_name . '"/>',
            );
            $msg = $this->input->post('note');
            $message = $this->parser->parse_string($msg, $parse_data);
            $attachment = $this->pdf($purchase_id, null, 'S');
        } elseif ($this->input->post('send_email')) {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->session->set_flashdata('error', $this->data['error']);
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->sma->send_email($to, $subject, $message, null, null, $attachment, $cc, $bcc)) {

            delete_files($attachment);
            if ($inv->status == 'pending' || $inv->status == '') {
                $this->db->update('purchases', array('status' => 'ordered'), array('id' => $purchase_id));
            }
            $this->session->set_flashdata('message', $this->lang->line("email_sent_msg"));
            //redirect("purchases");
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            if (file_exists('./themes/' . $this->theme . '/views/email_templates/purchase.html')) {
                $purchase_temp = file_get_contents('themes/' . $this->theme . '/views/email_templates/purchase.html');
            } else {
                $purchase_temp = file_get_contents('./themes/default/views/email_templates/purchase.html');
            }
            $this->data['subject'] = array('name' => 'subject',
                'id' => 'subject',
                'type' => 'text',
                'value' => $this->form_validation->set_value('subject', lang('purchase_order') . ' (' . $inv->reference_no . ') ' . lang('from') . ' ' . $this->Settings->site_name),
            );
            $this->data['note'] = array('name' => 'note',
                'id' => 'note',
                'type' => 'text',
                'value' => $this->form_validation->set_value('note', $purchase_temp),
            );
            $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);

            $this->data['id'] = $purchase_id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'purchases/email', $this->data);
        }
    }

    public function add($quote_id = null) {
        $this->sma->checkPermissions();

        $this->form_validation->set_message('is_natural_no_zero', $this->lang->line("no_zero_required"));
        //$this->form_validation->set_rules('reference_no', $this->lang->line("ref_no"), 'required');
        $this->form_validation->set_rules('warehouse', $this->lang->line("warehouse"), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('supplier', $this->lang->line("supplier"), 'required');
        $purchase_id = $this->input->get('purchase_id') ? $this->input->get('purchase_id') : NULL;

        $this->session->unset_userdata('csrf_token');
        if ($this->form_validation->run() == true) {

            $reference = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('po');
            if ($this->Owner || $this->Admin || $this->GP['purchases-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $warehouse_id = $this->input->post('warehouse');
            $supplier_id = $this->input->post('supplier');
            $status = $this->input->post('status');
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $supplier_details = $this->site->getCompanyByID($supplier_id);
            //$supplier = $supplier_details->company != '-'  ? $supplier_details->company : $supplier_details->name;
            $supplier = !empty($supplier_details->name) ? $supplier_details->name : $supplier_details->company;
            $note = $this->sma->clear_tags($this->input->post('note'));
            $payment_term = $this->input->post('payment_term');
            $due_date = $payment_term ? date('Y-m-d', strtotime('+' . $payment_term . ' days', strtotime($date))) : null;
            
            /*Set GST Type Logic*/
            $supplier_state_code = $supplier_details->state_code != '' ? $supplier_details->state_code : NULL;
            $warehouse = $this->site->getWarehouseByID($warehouse_id);            
            if($warehouse[$warehouse_id]->state_code != ''){
                $billers_id = $this->pos_settings->default_biller;
                $billers_state_code = $this->sma->getstatecode($billers_id);
            }            
            $purchase_state_code = $warehouse[$warehouse_id]->state_code != '' ? $warehouse[$warehouse_id]->state_code : ($billers_state_code != '' ? $billers_state_code : NULL);
            $GSTType = 'GST';
            if($supplier_state_code != NULL && $purchase_state_code != NULL){
                $GSTType = ($supplier_state_code == $purchase_state_code) ? 'GST' : 'IGST';
            }
            
            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $i = sizeof($_POST['product']);
            $total_cgst = $total_sgst = $total_igst = 0;
            for ($r = 0; $r < $i; $r++) {
                
                $item_code = $_POST['product'][$r];
                
                if($item_code != '') {
                    $product_details = $this->purchases_model->getProductByCode($item_code);
                    $product_id = $product_details->id;
                }
                
                $item_option = 0; $item_batch_number = NULL; $batchData = NULL; 
                
                if($product_details->storage_type == 'packed') {
                    $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' ? $_POST['product_option'][$r] : 0; 
                }
                
                if($this->Settings->product_batch_setting !== 0) {
                    $row_batch_number = (isset($_POST['batch_number'][$r]) && $_POST['batch_number'][$r]!='') ? $_POST['batch_number'][$r] : NULL;
                    if ($row_batch_number) { 
                        $batch = explode('~', $row_batch_number);
                        $item_batch_number = (count($batch)==2) ? $batch[1] : $batch[0];
                        $batchData = $this->site->getProductBatchData($item_batch_number, $product_id, $item_option);                    
                    }
                }
                
                $hsn_code           = (isset($_POST['hsn_code'][$r]) && $_POST['hsn_code'][$r] != '') ? $hsn_code : $product_details->hsn_code;
                $item_net_cost      = $this->sma->formatDecimal($_POST['net_cost'][$r]);
                $unit_cost          = $this->sma->formatDecimal($_POST['unit_cost'][$r]);
                $real_unit_cost     = $this->sma->formatDecimal($_POST['real_unit_cost'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r];
                
                $item_tax_rate      = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : 0;
                $item_discount      = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : 0;
                $item_expiry        = (isset($_POST['expiry'][$r]) && !empty($_POST['expiry'][$r])) ? $this->sma->fsd($_POST['expiry'][$r]) : null;

                if($batchData == NULL && $item_batch_number != NULL ){
                    $batchData = array(
                        'product_id'=>$product_id, 
                        'option_id'=>$item_option, 
                        'batch_no'=>$item_batch_number,
                        'cost' => $unit_cost,
                        'price' => '',
                        'mrp' => '',
                        'expiry_date' => $item_expiry ,
                    );
                    $this->site->addBatchInfo($batchData);
                }

                $supplier_part_no   = (isset($_POST['part_no'][$r]) && !empty($_POST['part_no'][$r])) ? $_POST['part_no'][$r] : null;
                $item_unit          = $_POST['product_unit'][$r];
                // $item_quantity      = $_POST['product_base_quantity'][$r];
                $item_quantity      = $item_unit_quantity;

                $item_tax_method    = $_POST['tax_method'][$r]; 

                if (isset($item_code) && isset($real_unit_cost) && isset($unit_cost) && isset($item_quantity)) {
                    
                    if ($item_expiry) {
                        $today = date('Y-m-d');
                        if ($item_expiry <= $today) {
                            $this->session->set_flashdata('error', lang('product_expiry_date_issue') . ' (' . $product_details->name . ')');
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    }
                    // $unit_cost = $real_unit_cost;
                    $pr_discount = 0;

                    if (isset($item_discount)) {
                        $discount = $item_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_cost)) * (Float) ($pds[0])) / 100), 4);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount);
                        }
                    }

                    $unit_cost          = $this->sma->formatDecimal($unit_cost - $pr_discount);
                    $item_net_cost      = $unit_cost;
                    $pr_item_discount   = $this->sma->formatDecimal($pr_discount * $item_unit_quantity);
                    $product_discount   += $pr_item_discount;
                    $pr_tax             = 0;
                    $pr_item_tax        = 0;
                    $item_tax           = 0;
                    $tax                = "";
                    $cgst = $sgst = $igst = $gst_rate = 0;
                    
                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {
                            $taxmethod = ($item_tax_method == '') ? $product_details->tax_method : $item_tax_method;
                            if ($product_details && $taxmethod == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_cost = $unit_cost - $item_tax;
                            }
                        } elseif ($tax_details->type == 2) {

                            if ($product_details && $taxmethod == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_cost = $unit_cost - $item_tax;
                            }

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                    }

                    $product_tax    += $pr_item_tax;
                    $subtotal       = (($item_net_cost * $item_unit_quantity) + $pr_item_tax);
                    $unit           = $this->site->getUnitByID($item_unit);
                    $item_option    = $item_option ? $item_option : 0;
                    
                    $quantity_received = ($status == 'received') ? $item_quantity : 0;
                                        
                    if($pr_item_tax) {
                        if($GSTType == 'IGST'){
                            $igst = $pr_item_tax;
                            $gst_rate = $tax_details->rate;
                        } else {
                            $cgst = $sgst = ($pr_item_tax / 2);
                            $gst_rate = ($tax_details->rate / 2);
                        }
                    }
            
                    $products[] = array(
                        'product_id'        => $product_details->id,
                        'product_code'      => $item_code,
                        'product_name'      => $product_details->name,
                        'option_id'         => $item_option,
                        'net_unit_cost'     => $item_net_cost,
                        'unit_cost'         => $this->sma->formatDecimal($item_net_cost + $item_tax),
                        'quantity'          => $item_quantity,
                        'product_unit_id'   => $item_unit,
                        'product_unit_code' => $unit->code,
                        'unit_quantity'     => $item_unit_quantity,
                        'quantity_balance'  => $quantity_received,
                        'quantity_received' => $quantity_received,
                        'warehouse_id'      => $warehouse_id,
                        'item_tax'          => $pr_item_tax,
                        'tax_rate_id'       => $pr_tax,
                        'tax'               => $tax,
                        'discount'          => $item_discount,
                        'item_discount'     => $pr_item_discount,
                        'subtotal'          => $this->sma->formatDecimal($subtotal),
                        'expiry'            => $item_expiry,
                        'batch_number'      => $item_batch_number,
                        'real_unit_cost'    => $real_unit_cost,
                        'date'              => date('Y-m-d', strtotime($date)),
                        'status'            => $status,
                        'supplier_part_no'  => $supplier_part_no,
                        'hsn_code'          => $hsn_code,
                        'tax_method'        => $item_tax_method,
                        'gst_rate'          => $gst_rate,
                        'cgst'              => $cgst,
                        'sgst'              => $sgst,
                        'igst'              => $igst,
                    );

                    $total += $this->sma->formatDecimal(($item_net_cost * $item_unit_quantity), 4);
                    
                    $total_cgst += $cgst;
                    $total_sgst += $sgst;
                    $total_igst += $igst;
                }
            }
            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {
                krsort($products);
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
            //$total_discount = $this->sma->formatDecimal($order_discount + $product_discount);
            $total_discount = $this->sma->formatDecimal($product_discount);
            
            if ($this->Settings->tax2 != 0) {
                $order_tax_id = $this->input->post('order_tax');
                if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                    if ($order_tax_details->type == 2) {
                        $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
                    }
                    if ($order_tax_details->type == 1) {
                        // $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
                        $order_tax = $this->sma->formatDecimal(((($total + $product_tax ) * $order_tax_details->rate) / 100), 4);
                    }
                }
            } else {
                $order_tax_id = null;
            }

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 4);
            //$grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping) - $order_discount), 4);
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping)), 4);
            $rounding = '';
            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }
                        
            $data = [
                'reference_no'      => $reference,
                'date'              => $date,
                'supplier_id'       => $supplier_id,
                'supplier'          => $supplier,
                'warehouse_id'      => $warehouse_id,
                'note'              => $note,
                'total'             => $total,
                'product_discount'  => $product_discount,
                'order_discount_id' => $order_discount_id,
                'order_discount'    => $order_discount,
                'total_discount'    => $total_discount,
                'product_tax'       => $product_tax,
                'order_tax_id'      => $order_tax_id,
                'order_tax'         => $order_tax,
                'total_tax'         => $total_tax,
                'shipping'          => $this->sma->formatDecimal($shipping),
                'grand_total'       => $grand_total,
                'status'            => $status,
                'created_by'        => $this->session->userdata('user_id'),
                'payment_term'      => $payment_term,
                'rounding'          => $rounding,
                'due_date'          => $due_date,
                'cgst'              => $total_cgst,
                'sgst'              => $total_sgst,
                'igst'              => $total_igst,
            ];

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

        if ($this->form_validation->run() == true && $this->purchases_model->addPurchase($data, $products)) {
            $this->session->set_userdata('remove_pols', 1);
            $this->session->set_flashdata('message', $this->lang->line("purchase_added"));
            redirect('purchases');
        } else {

            if ($quote_id || $purchase_id) {

                if ($quote_id):
                    $this->data['quote'] = $this->purchases_model->getQuoteByID($quote_id);
                    $supplier_id = $this->data['quote']->supplier_id;
                    $items = $this->purchases_model->getAllQuoteItems($quote_id);
                elseif ($purchase_id):
                    $this->data['quote'] = $this->purchases_model->getPurchaseByID($purchase_id);
                    $supplier_id = $this->data['quote']->supplier_id;
                    $items = $this->purchases_model->getAllPurchaseItems($purchase_id);
                endif;

                krsort($items);
                $c = rand(100000, 9999999);
                foreach ($items as $item) {
                    $row = $this->site->getProductByID($item->product_id);
                    if ($row->type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($row->id, $item->warehouse_id);
                        foreach ($combo_items as $citem) {
                            $crow = $this->site->getProductByID($citem->id);
                            if (!$crow) {
                                $crow = json_decode('{}');
                                $crow->qty = $item->quantity;
                            } else {
                                unset($crow->details, $crow->product_details, $crow->price);
                                $crow->qty = $citem->qty * $item->quantity;
                            }
                            $crow->base_quantity = $item->quantity;
                            $crow->base_unit = $crow->unit ? $crow->unit : $item->product_unit_id;
                            $crow->base_unit_cost = $crow->cost ? $crow->cost : $item->unit_cost;
                            $crow->unit = $item->product_unit_id;
                            $crow->discount = $item->discount ? $item->discount : '0';
                            $supplier_cost = $supplier_id ? $this->getSupplierCost($supplier_id, $crow) : $crow->cost;
                            $crow->cost = $supplier_cost ? $supplier_cost : 0;
                            $productlist = $this->purchases_model->getProductByID($crow->id);
                            $crow->tax_method = $productlist->tax_method;
                            $crow->tax_rate = $item->tax_rate_id;
                            $crow->real_unit_cost = $crow->cost ? $crow->cost : 0;
                            $crow->expiry = '';
                            $row->batch_number = '';
                            $options = $this->purchases_model->getProductOptions($crow->id);
                            $units = $this->site->getUnitsByBUID($row->base_unit);
                            $tax_rate = $this->site->getTaxRateByID($crow->tax_rate);
                            $ri = $this->Settings->item_addition ? $crow->id : $c;

                            $pr[$ri] = array('id' => $c, 'item_id' => $crow->id, 'image' => $crow->image, 'label' => $crow->name . " (" . $crow->code . ")", 'row' => $crow, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                            $c++;
                        }
                    } elseif ($row->type == 'Bundle') {
                        $combo_items = $this->site->getProductComboItems($row->id, $item->warehouse_id);
                        foreach ($combo_items as $citem) {
                            $crow = $this->site->getProductByID($citem->id);
                            if (!$crow) {
                                $crow = json_decode('{}');
                                $crow->qty = $item->quantity;
                            } else {
                                unset($crow->details, $crow->product_details, $crow->price);
                                $crow->qty = $citem->qty * $item->quantity;
                            }
                            $crow->base_quantity = $item->quantity;
                            $crow->base_unit = $crow->unit ? $crow->unit : $item->product_unit_id;
                            $crow->base_unit_cost = $crow->cost ? $crow->cost : $item->unit_cost;
                            $crow->unit = $item->product_unit_id;
                            $crow->discount = $item->discount ? $item->discount : '0';
                            $supplier_cost = $supplier_id ? $this->getSupplierCost($supplier_id, $crow) : $crow->cost;
                            $crow->cost = $supplier_cost ? $supplier_cost : 0;
                            $productlist = $this->purchases_model->getProductByID($crow->id);
                            $crow->tax_method = $productlist->tax_method;
                            $crow->tax_rate = $item->tax_rate_id;
                            $crow->real_unit_cost = $crow->cost ? $crow->cost : 0;
                            $crow->expiry = '';
                            $row->batch_number = '';
                            $options = $this->purchases_model->getProductOptions($crow->id);
                            $units = $this->site->getUnitsByBUID($row->base_unit);
                            $tax_rate = $this->site->getTaxRateByID($crow->tax_rate);
                            $ri = $this->Settings->item_addition ? $crow->id : $c;

                            $pr[$ri] = array('id' => $c, 'item_id' => $crow->id, 'image' => $crow->image, 'label' => $crow->name . " (" . $crow->code . ")", 'row' => $crow, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                            $c++;
                        }
                    } elseif ($row->type == 'standard') {
                        if (!$row) {
                            $row = json_decode('{}');
                            $row->quantity = 0;
                        } else {
                            unset($row->details, $row->product_details);
                        }

                        $row->id = $item->product_id;

                        $row->code = $item->product_code;
                        $row->name = $item->product_name;
                        $row->base_quantity = $item->quantity;
                        $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                        $row->base_unit_cost = $row->cost ? $row->cost : $item->unit_cost;
                        $row->unit = $item->product_unit_id;
                        $row->qty = $item->unit_quantity;
                        $row->option = $item->option_id ? $item->option_id : 0;
                        $row->discount = $item->discount ? $item->discount : '0';
                        $supplier_cost = $supplier_id ? $this->getSupplierCost($supplier_id, $row) : $row->cost;
                        $row->cost = $supplier_cost ? $supplier_cost : 0;
                        $row->tax_rate = $item->tax_rate_id;
                        $row->expiry = '';
                        $row->batch_number = '';
                        $row->real_unit_cost = $row->cost ? $row->cost : 0;
                        $productlist = $this->purchases_model->getProductByID($row->id);
                        $row->tax_method = $productlist->tax_method;
                        $unitData = $this->purchases_model->getUnitById($row->unit);
                        $row->unit_lable = $unitData->name;
                        $options = $this->purchases_model->getProductOptions($row->id);

                        $units = $this->site->getUnitsByBUID($row->base_unit);
                        $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                        $ri = $this->Settings->item_addition ? $row->id : $c;

                        $pr[$ri] = array('id' => $c, 'item_id' => $row->id, 'image' => $row->image, 'label' => $row->name . " (" . $row->code . ")",
                            'row' => $row, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options);
                        $c++;
                    }
                }
                $this->data['quote_items'] = json_encode($pr);
            }

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['quote_id'] = $quote_id ? $quote_id : $purchase_id;
            // $this->data['suppliers'] = $this->site->getAllCompanies('supplier');
            $this->data['categories'] = $this->site->getAllCategories();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['warehouses'] = $this->site->getAllActiveWarehouses();
            $this->data['ponumber'] = ''; //$this->site->getReference('po');
            $this->load->helper('string');
            $value = random_string('alnum', 20);
            $this->session->set_userdata('user_csrf', $value);
            $this->data['csrf'] = $this->session->userdata('user_csrf');
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('purchases'), 'page' => lang('purchases')), array('link' => '#', 'page' => lang('add_purchase')));
            $meta = array('page_title' => lang('add_purchase'), 'bc' => $bc);

            $this->page_construct('purchases/add', $meta, $this->data);
        }
    }

    public function edit($id = null) {
            
        $this->sma->checkPermissions();
        $PreUrl = '';
        if ($this->input->post('previous_url')) {
            $previous_url = $this->input->post('previous_url');
            $ExplodePreUrl = explode('/', $previous_url);
            $PreUrl = $ExplodePreUrl[4];
        }
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $inv = $this->purchases_model->getPurchaseByID($id);
            
        if(!$this->site->isWarehouseActive($inv->warehouse_id)){
            $this->session->set_flashdata('error', 'Purchase warehouse is inactive. Can not modified purchase.');
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if ($inv->status == 'returned' || $inv->return_id || $inv->return_purchase_ref) {
            $this->session->set_flashdata('error', lang('purchase_x_action'));
            redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
        }
        if (!$this->session->userdata('edit_right')) {
            $this->sma->view_rights($inv->created_by);
        }
        $this->form_validation->set_message('is_natural_no_zero', $this->lang->line("no_zero_required"));
        $this->form_validation->set_rules('reference_no', $this->lang->line("ref_no"), 'required');
        $this->form_validation->set_rules('warehouse', $this->lang->line("warehouse"), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('supplier', $this->lang->line("supplier"), 'required');

        $this->session->unset_userdata('csrf_token');
        if ($this->form_validation->run() == true) {

            $reference = $this->input->post('reference_no');
            if ($this->Owner || $this->Admin || $this->GP['purchases-date']) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = $inv->date;
            }
            $warehouse_id       = $this->input->post('warehouse');
            $supplier_id        = $this->input->post('supplier');
            $status             = $this->input->post('status');
            $shipping           = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $supplier_details   = $this->site->getCompanyByID($supplier_id);
            $supplier           = $supplier_details->company != '-' ? $supplier_details->company : $supplier_details->name;
            $note               = $this->sma->clear_tags($this->input->post('note'));
            $payment_term       = $this->input->post('payment_term');
            $due_date           = $payment_term ? date('Y-m-d', strtotime('+' . $payment_term . ' days', strtotime($date))) : NULL;
            
            $supplier_state_code = $supplier_details->state_code != '' ? $supplier_details->state_code : NULL;
            $warehouse = $this->site->getWarehouseByID($warehouse_id);            
            if($warehouse[$warehouse_id]->state_code != ''){
                $billers_id = $this->pos_settings->default_biller;
                $billers_state_code = $this->sma->getstatecode($billers_id);
            }            
            $purchase_state_code = $warehouse[$warehouse_id]->state_code != '' ? $warehouse[$warehouse_id]->state_code : ($billers_state_code != '' ? $billers_state_code : NULL);
            $GSTType = 'GST';
            if($supplier_state_code != NULL && $purchase_state_code != NULL){
                $GSTType = ($supplier_state_code == $purchase_state_code) ? 'GST' : 'IGST';
            }
            
            $total              = 0;
            $product_tax        = 0;
            $order_tax          = 0;
            $product_discount   = 0;
            $order_discount     = 0;
            $percentage         = '%';
            $purchase_status    = false;
            $total_cgst = $total_sgst = $total_igst = 0;
            $i = sizeof($_POST['product']);
            
            for ($r = 0; $r < $i; $r++) {
                
                $item_code = $_POST['product'][$r];
                
                if($item_code != '') {
                    $product_details = $this->purchases_model->getProductByCode($item_code);
                    $product_id = $product_details->id;
                }
                
                $item_option = 0; $item_batch_number = NULL; $batchData = NULL; 
                
                if($product_details->storage_type == 'packed') {
                    $item_option = isset($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' ? $_POST['product_option'][$r] : 0; 
                }
                
                if($this->Settings->product_batch_setting !== 0) {
                    $row_batch_number = (isset($_POST['batch_number'][$r]) && $_POST['batch_number'][$r]!='') ? $_POST['batch_number'][$r] : NULL;
                    if ($row_batch_number) { 
                        $batch = explode('~', $row_batch_number);
                        $item_batch_number = (count($batch)==2) ? $batch[1] : $batch[0];
                        $batchData = $this->site->getProductBatchData($item_batch_number, $product_id, $item_option);                    
                    }
                }
                
                $hsn_code           = (isset($_POST['hsn_code'][$r]) && $_POST['hsn_code'][$r] != '') ? $hsn_code : $product_details->hsn_code;
                $item_net_cost      = $this->sma->formatDecimal($_POST['net_cost'][$r]);
                $unit_cost          = $this->sma->formatDecimal($_POST['unit_cost'][$r]);
                $real_unit_cost     = $this->sma->formatDecimal($_POST['real_unit_cost'][$r]);
                $item_unit_quantity = $_POST['quantity'][$r] ? $_POST['quantity'][$r] : $_POST['product_base_quantity'][$r];
                                
                if($batchData == NULL && $item_batch_number != NULL ){
                    $batchData = [
                        'product_id'    => $product_id, 
                        'option_id'     => $item_option, 
                        'batch_no'      => $item_batch_number,
                        'cost'          => $unit_cost,
                        'price'         => '',
                        'mrp'           => '',
                        'expiry_date'   => $item_expiry,
                    ];
                    $this->site->addBatchInfo($batchData);
                }
                
                $item_tax_rate  = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                $item_discount  = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                $item_expiry    = (isset($_POST['expiry'][$r]) && !empty($_POST['expiry'][$r])) ? $this->sma->fsd($_POST['expiry'][$r]) : null;
            
                $supplier_part_no   = (isset($_POST['part_no'][$r]) && !empty($_POST['part_no'][$r])) ? $_POST['part_no'][$r] : null;
                $quantity_balance   = $_POST['quantity_balance'][$r];
                $ordered_quantity   = $_POST['ordered_quantity'][$r];
                $item_unit          = $_POST['product_unit'][$r];
                $item_quantity      = $_POST['product_base_quantity'][$r];
                $item_tax_method    = $_POST['tax_method'][$r];

                if ($status == 'received' || $status == 'partial') {
                    
                    $quantity_received  = $_POST['received'][$r] ? $_POST['received'][$r] : 0;
                    
                    if($quantity_received <= 0) {                        
                        $item_status = 'pending';
                    } elseif ($quantity_received < $item_quantity) {                       
                        $item_status = 'partial';
                    } elseif ($quantity_received == $item_quantity) {                        
                        $item_status = 'received';
                    } elseif ($quantity_received > $item_quantity) {
                        $this->session->set_flashdata('error', lang("received_more_than_ordered"));
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                    $balance_qty = $quantity_received - ($ordered_quantity - $quantity_balance);
            
                } else {
                    $item_status        = $status;
                    $balance_qty        = 0;
                    $quantity_received  = 0;
                }
                
                if (isset($item_code) && isset($real_unit_cost) && isset($unit_cost) && isset($item_quantity) && isset($quantity_balance)) {
            
                    // $unit_cost = $real_unit_cost;
                    $pr_discount = 0;

                    if ($item_discount) {
                        $discount = $item_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_cost)) * (Float) ($pds[0])) / 100), 4);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount);
                        }
                    }

                    $unit_cost          = $this->sma->formatDecimal($unit_cost - $pr_discount);
                    $item_net_cost      = $unit_cost;
                    $pr_item_discount   = $this->sma->formatDecimal($pr_discount * $item_unit_quantity);
                    $product_discount   += $pr_item_discount;
                    $pr_tax             = 0;
                    $pr_item_tax        = 0;
                    $item_tax           = 0;
                    $tax                = "";
                    $cgst = $sgst = $igst = $gst_rate = 0;
                    
                    if (isset($item_tax_rate) && $item_tax_rate != 0) {
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {
                            $taxmethod = ($item_tax_method == '') ? $product_details->tax_method : $item_tax_method;

                            if ($product_details && $taxmethod == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_cost = $unit_cost - $item_tax;
                            }
                        } elseif ($tax_details->type == 2) {

                            if ($product_details && $taxmethod == 1) {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                $tax = $tax_details->rate . "%";
                            } else {
                                $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                $tax = $tax_details->rate . "%";
                                $item_net_cost = $unit_cost - $item_tax;
                            }

                            $item_tax = $this->sma->formatDecimal($tax_details->rate);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                    }

                    $product_tax += $pr_item_tax;
                    $subtotal = (($item_net_cost * $item_unit_quantity) + $pr_item_tax);
                    $unit = $this->site->getUnitByID($item_unit);
                    $item_option = $item_option ? $item_option : 0;
                    
                    if($pr_item_tax) {
                        if($GSTType == 'IGST'){
                            $igst = $pr_item_tax;
                            $gst_rate = $tax_details->rate;
                        } else {
                            $cgst = $sgst = ($pr_item_tax / 2);
                            $gst_rate = ($tax_details->rate / 2);
                        }
                    }
                    
                    
                    $items[] = [
                        'product_id'        => $product_details->id,
                        'product_code'      => $item_code,
                        'product_name'      => $product_details->name,
                        'option_id'         => $item_option,
                        'net_unit_cost'     => $item_net_cost,
                        'unit_cost'         => $this->sma->formatDecimal($item_net_cost + $item_tax),
                        'quantity'          => $item_quantity,
                        'product_unit_id'   => $item_unit,
                        'product_unit_code' => $unit->code,
                        'unit_quantity'     => ($item_unit_quantity ? $item_unit_quantity :$item_quantity),
                        'quantity_balance'  => $balance_qty,
                        'quantity_received' => $quantity_received,
                        'warehouse_id'      => $warehouse_id,
                        'item_tax'          => $pr_item_tax,
                        'tax_rate_id'       => $pr_tax,
                        'tax'               => $tax,
                        'discount'          => $item_discount,
                        'item_discount'     => $pr_item_discount,
                        'subtotal'          => $this->sma->formatDecimal($subtotal),
                        'expiry'            => $item_expiry,
                        'batch_number'      => $item_batch_number,
                        'real_unit_cost'    => $real_unit_cost,
                        'supplier_part_no'  => $supplier_part_no,
                        'date'              => date('Y-m-d', strtotime($date)),
                        'hsn_code'          => $hsn_code,
                        'tax_method'        => $item_tax_method,
                        'gst_rate'          => $gst_rate,
                        'cgst'              => $cgst,
                        'sgst'              => $sgst,
                        'igst'              => $igst,
                        'status'            => $item_status,
                    ];

                    $total += $item_net_cost * $item_unit_quantity;
                }
                
                $total_cgst += $cgst;
                $total_sgst += $sgst;
                $total_igst += $igst;
                
                if (($status == 'received' || $status == 'partial') && $purchase_status != 'partial') {
                    if( $item_status == 'partial' ){
                        $purchase_status = 'partial'; 
                    } elseif( ($purchase_status == false || $purchase_status == 'received') && $item_status == 'received' ) {
                        $purchase_status = 'received';                         
                    } elseif( ($purchase_status == 'received' && $item_status != 'received') || ($purchase_status != 'received' && $item_status == 'received' ) ) { 
                        $purchase_status = 'partial'; 
                    } elseif($item_status == 'pending' && ($purchase_status == false || $purchase_status == 'pending') ) {
                        $purchase_status = 'pending';
                    }
                }
                
            }//end foreach
            
            if ($status == 'received' || $status == 'partial') {
                $status = $purchase_status != false ? $purchase_status : 'received';
            } 
            
            if (empty($items)) {
                $this->form_validation->set_rules('product', lang("order_items"), 'required');
            } else {            
                krsort($items);
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
            //$total_discount = $this->sma->formatDecimal($product_discount);

            if ($this->Settings->tax2 != 0) {
                $order_tax_id = $this->input->post('order_tax');
                if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                    if ($order_tax_details->type == 2) {
                        $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
                    }
                    if ($order_tax_details->type == 1) {
                        //  $order_tax = $this->sma->formatDecimal(((($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100), 4);
                        $order_tax = $this->sma->formatDecimal(((($total + $product_tax ) * $order_tax_details->rate) / 100), 4);
                    }
                }
            } else {
                $order_tax_id = null;
            }

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 4);
            //$grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping) - $order_discount), 4);
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($shipping)), 4);
            $rounding = '';

            if ($this->pos_settings->rounding > 0) {
                $round_total = $this->sma->roundNumber($grand_total, $this->pos_settings->rounding);
                $rounding = ($round_total - $grand_total);
            }

            $data = [
                    'reference_no'  => $reference,
                    'supplier_id'   => $supplier_id,
                    'supplier'      => $supplier,
                    'warehouse_id'  => $warehouse_id,
                    'note'          => $note,
                    'total'         => $total,
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
                    'status' => $status,
                    'updated_by' => $this->session->userdata('user_id'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'payment_term' => $payment_term,
                    'rounding' => $rounding,
                    'due_date' => $due_date,
                    'cgst' => $total_cgst,
                    'sgst' => $total_sgst,
                    'igst' => $total_igst,
                ];
            
            if ($date) {
                $data['date'] = $date;
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

            //$this->sma->print_arrays($data, $items);
        }
        
        
        if ($this->form_validation->run() == true && $this->purchases_model->updatePurchase($id, $data, $items)) {
                         
            $this->session->set_userdata('remove_pols', 1);
            $this->session->set_flashdata('message', $this->lang->line("purchase_updated"));
//            if ($PreUrl == 'purchases')
//                redirect('reports/purchases');
//            elseif ($PreUrl == 'purchases_gst_report')
//                redirect('reports/purchases_gst_report');
//            else 
                redirect('purchases');
        } 
        
        else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['inv'] = $inv;
            if ($this->Settings->disable_editing) {
                if ($this->data['inv']->date <= date('Y-m-d', strtotime('-' . $this->Settings->disable_editing . ' days'))) {
                    $this->session->set_flashdata('error', sprintf(lang("purchase_x_edited_older_than_x_days"), $this->Settings->disable_editing));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            }
            $inv_items = $this->purchases_model->getAllPurchaseItems($id, 'product_code');  //second parameter order by name
            krsort($inv_items);
            $c = rand(100000, 9999999);
            
            foreach ($inv_items as $item) {

                $row = $product = $this->site->getProductByID($item->product_id);
            
                if ($row == FALSE) { continue; }
                unset($row->alert_quantity, $row->price, $row->cf1, $row->cf2, $row->cf3, $row->cf4, $row->cf5, $row->cf6, $row->track_quantity, $row->details, $row->warehouse, $row->barcode_symbology, $row->file, $row->product_details, $row->supplier1, $row->supplier2, $row->supplier3, $row->supplier4, $row->supplier5, $row->supplier1price, $row->supplier2price, $row->supplier3price, $row->supplier4price, $row->supplier5price, $row->promotion, $row->promo_price, $row->start_date, $row->end_date, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no, $row->sale_unit, $row->brand, $row->is_featured, $row->divisionid, $row->up_items, $row->food_type_id, $row->up_price, $row->ratings_avarage, $row->ratings_count, $row->comments_count);
                
                $options = ($row->storage_type == 'packed') ? $this->purchases_model->getProductOptions($row->id) : FALSE;
                
                $row->expiry = ((isset($item->expiry) && !empty($item->expiry) && $item->expiry != '0000-00-00') ? $this->sma->hrsd($item->expiry) : '');
                $row->base_quantity         = $item->quantity;
                $row->batch_number          = $item->batch_number;
                $row->base_unit             = $row->unit ? $row->unit : $item->product_unit_id;
                $row->base_unit_cost        = $row->cost ? $row->cost : $item->unit_cost;
                $row->unit                  = $item->product_unit_id;
                $row->qty                   = $item->unit_quantity;
                $row->oqty                  = $item->quantity;
                $row->supplier_part_no      = $item->supplier_part_no;
                $row->received              = $item->quantity_received ? $item->quantity_received : $item->quantity;
                $row->quantity_balance      = $item->quantity_balance + ($item->quantity - $row->received);
                $row->discount              = $item->discount ? $item->discount : '0';
                               
                $row->option                = ($options !== FALSE && $item->option_id) ? $item->option_id : 0;
                $row->real_unit_cost        = $item->real_unit_cost;
                $row->cost                  = $this->sma->formatDecimal($item->net_unit_cost + ($item->item_discount / $item->quantity));
                $row->tax_rate              = $item->tax_rate_id;
                $unitData                   = $this->purchases_model->getUnitById($row->unit);
                $row->unit_lable            = $unitData->name;
                $row->tax_method            = ($item->tax_method == '') ? $row->tax_method : $item->tax_method;
            
                $units                  = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate               = $this->site->getTaxRateByID($row->tax_rate);
             
                $batchoption        = FALSE;
                $row->batch         = FALSE;
                $row->batch_number  = FALSE;
                
            /** * Batch Config *  */    
            if($this->Settings->product_batch_setting > 0) {     
                
                $option_id = $batch_option = $row->option;
                
                $productbatches = $this->products_model->getProductVariantsBatch($item->product_id);
                
                $batch = $productbatches[$batch_option];
                
                if ($batch) {
                    $firstKey = key($batch);
                    $batchoption = $batch;
                    $row->batch = $batchoption[$firstKey]->id;
                    $row->batch_number = $batchoption[$firstKey]->batch_no;                    
                    $row->cost = $batchoption[$firstKey]->cost;
                    $row->real_unit_cost = $batchoption[$firstKey]->cost;
                    $row->base_unit_cost = $batchoption[$firstKey]->cost;
                    $row->expiry = ($batchoption[$firstKey]->expiry != '' && $batchoption[$firstKey]->expiry !== '0000-00-00') ? $batchoption[$firstKey]->expiry : '';
                }                
            }
            /**
            * End Batch Configs
            **/
                
                $row_id = $row->id . $row->option;
                
                $ri = $this->settings->item_addition == 1 ? $row_id : $c;
                
                $pr[$ri] = array('id' => $c, 'item_id' => $row_id, 'label' => $row->name . " (" . $row->code . ")",
                    'row' => $row, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options, 'batchs' => $batchoption, 'option_batches'=> $productbatches );
                $c++;
            }

            $this->data['inv_items'] = json_encode($pr);
            $this->data['id'] = $id;
            $this->data['suppliers']    = $this->site->getAllCompanies('supplier');
            $this->data['purchase']     = $this->purchases_model->getPurchaseByID($id);
            $this->data['categories']   = $this->site->getAllCategories();
            $this->data['tax_rates']    = $this->site->getAllTaxRates();
            $this->data['warehouses']   = $this->site->getAllActiveWarehouses();
            $this->load->helper('string');
            $value = random_string('alnum', 20);
            $this->session->set_userdata('user_csrf', $value);
            $this->session->set_userdata('remove_pols', 1);
            $this->data['csrf'] = $this->session->userdata('user_csrf');
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('purchases'), 'page' => lang('purchases')), array('link' => '#', 'page' => lang('edit_purchase')));
            $meta = array('page_title' => lang('edit_purchase'), 'bc' => $bc);
            $this->page_construct('purchases/edit', $meta, $this->data);
        }
    }

    public function purchase_by_csv() {
        $this->sma->checkPermissions('index', true);
        $this->load->helper('security');
        $this->form_validation->set_message('is_natural_no_zero', $this->lang->line("no_zero_required"));
        $this->form_validation->set_rules('warehouse', $this->lang->line("warehouse"), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('supplier', $this->lang->line("supplier"), 'required|is_natural_no_zero');
        $this->form_validation->set_rules('userfile', $this->lang->line("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == true) {
            $quantity = "quantity";
            $product = "product";
            $unit_cost = "unit_cost";
            $tax_rate = "tax_rate";
            
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = null;
            }
            $warehouse_id = $this->input->post('warehouse');
            $supplier_id = $this->input->post('supplier');
            $status = $this->input->post('status');
            $shipping = $this->input->post('shipping') ? $this->input->post('shipping') : 0;
            $supplier_details = $this->site->getCompanyByID($supplier_id);
            $supplier = $supplier_details->company != '-' ? $supplier_details->company : $supplier_details->name;
            $note = $this->sma->clear_tags($this->input->post('note'));
            $item_tax_method = '';
            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $item_price = 0;
            $mrp = 0;
                                    
            if (isset($_FILES["userfile"])) {

                /* $this->load->library('upload');

                  $config['upload_path'] = $this->digital_upload_path;
                  $config['allowed_types'] = 'csv';
                  $config['max_size'] = $this->allowed_file_size;
                  $config['overwrite'] = true;

                  $this->upload->initialize($config);

                  if (!$this->upload->do_upload()) {
                  $error = $this->upload->display_errors();
                  $this->session->set_flashdata('error', $error);
                  redirect("purchases/purchase_by_csv");
                  }

                  $csv = $this->upload->file_name;

                  $arrResult = array();
                  $handle = fopen($this->digital_upload_path . $csv, "r");
                  if ($handle) {
                  while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                  $arrResult[] = $row;
                  }
                  fclose($handle);
                  }
                  $titles = array_shift($arrResult); */
                
                /*-- Set GST Type Logic -- */
                $supplier_state_code = $supplier_details->state_code != '' ? $supplier_details->state_code : NULL;
                // $warehouse = $this->site->getWarehouseByID($warehouse_id); 
                $warehouse = $this->site->getWarehouseID($warehouse_id); 

                     
                if($warehouse[$warehouse_id]->state_code != ''){
                    $billers_id = $this->pos_settings->default_biller;
                    $billers_state_code = $this->sma->getstatecode($billers_id);
                }            
                $purchase_state_code = $warehouse[$warehouse_id]->state_code != '' ? $warehouse[$warehouse_id]->state_code : ($billers_state_code != '' ? $billers_state_code : NULL);
                $GSTType = 'GST';
                if($supplier_state_code != NULL && $purchase_state_code != NULL){
                    $GSTType = ($supplier_state_code == $purchase_state_code) ? 'GST' : 'IGST';
                }
                /*-- End GST Type Logic -- */
                
                $this->load->library('excel');
                $File = $_FILES['userfile']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                //$reader= PHPExcel_IOFactory::createReader('Excel2007');
                $reader->setReadDataOnly(true);
                $path = $File; //"./uploads/upload.xlsx";
                $excel = $reader->load($path);

                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);
            
                $arrayCount = count($sheet);
              
                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                    // echo $sheet[$i]["A"].$sheet[$i]["B"].$sheet[$i]["C"].$sheet[$i]["D"].$sheet[$i]["E"];
                }
            
                // $cols = ['code', 'net_unit_cost', 'quantity', 'variant', 'item_tax_rate', 'discount', 'expiry', 'batch_number'];
                $cols = ['code', 'net_unit_cost', 'quantity', 'variant', 'item_tax_rate', 'discount', 'expiry', 'batch_number', 'price', 'mrp'];
              
                for($c=0; $c < (count($sheet[1])); $c++){
                    $keys[] =  $cols[$c];
                }            
              //  echo "arrResult: ".count($arrResult);
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
               //  echo "<br/>final: ".count($final);           
                $total_cgst = $total_sgst = $total_igst = 0;
                
                $rw = 2;
                $str = "";
                $flag = 0;
                $i=0;
                foreach ($final as $csv_pr) {
                    
                    $item_code           = isset($csv_pr['code'])            ? trim($csv_pr['code'])          : NULL;
                    $net_unit_cost       = isset($csv_pr['net_unit_cost'])   ? trim($csv_pr['net_unit_cost']) : NULL;
                    $quantity            = isset($csv_pr['quantity'])        ? trim($csv_pr['quantity'])      : 0;
                    $variant             = isset($csv_pr['variant'])         ? trim($csv_pr['variant'])       : 0;
                    $item_tax_rate       = isset($csv_pr['item_tax_rate'])   ? trim($csv_pr['item_tax_rate']) : NULL;
                    $item_discount       = isset($csv_pr['discount'])        ? trim($csv_pr['discount'])      : NULL;
                    $expiry              = isset($csv_pr['expiry'])          ? trim($csv_pr['expiry'])        : NULL;
                    $item_batch_number   = isset($csv_pr['batch_number'])    ? trim($csv_pr['batch_number'])  : NULL;
                    $item_price          = isset($csv_pr['price'])           ? trim($csv_pr['price'])  : NULL;
                    $mrp                 = isset($csv_pr['mrp'])             ? trim($csv_pr['mrp'])  : NULL;
                    
                    $cgst = $sgst = $igst = $gst_rate = 0;          
                    
                    if ( $item_code && $net_unit_cost && $quantity) {
                        
                        $real_unit_cost     = $item_net_cost = $this->sma->formatDecimal($net_unit_cost);
                        $item_quantity      = $quantity;                        
                        $item_expiry        = ($expiry !== NULL) ? date('Y-m-d', strtotime($expiry)) : NULL;            
                        
                        if ($product_details = $this->purchases_model->getProductByCode( $item_code )) {
                            
                            $product_id = $product_details->id;
                            if ($variant) {
                                $item_option = $this->purchases_model->getProductVariantByName($variant, $product_id);
                                if (!$item_option) {
                                    $this->session->set_flashdata('error', lang("pr_not_found") . " ( " . $product_details->name . " - " . $variant . " ). " . lang("line_no") . " " . $rw);
                                    redirect($_SERVER["HTTP_REFERER"]);
                                }
                            } else {
                                $item_option = json_decode('{}');
                                $item_option->id = 0;
                            }

                            $item_option_id = 0; $item_batch_number = NULL; $batchData = NULL; 
                
                            if($product_details->storage_type == 'packed') {
                                $item_option_id = isset($item_option->id) && $item_option->id ? $item_option->id : 0; 
                            }
            
                            if ($item_batch_number) {             
                                $batchData = $this->site->getProductBatchData($item_batch_number, $product_id, $item_option_id);                    
                            }
            
                            if($batchData == NULL && $item_batch_number != NULL ){
                                $mrp = $product_details->mrp > $item_net_cost ? $product_details->mrp : ($product_details->price > $item_net_cost ? $product_details->price : $item_net_cost);
                                $batchData = [
                                    'product_id'    =>  $product_id, 
                                    'option_id'     =>  $item_option_id, 
                                    'batch_no'      =>  $item_batch_number,
                                    'cost'          =>  $item_net_cost,
                                    'price'         =>  $product_details->price,
                                    'mrp'           =>  $mrp,
                                    'expiry_date'   =>  $item_expiry ,
                                ];
                                $this->site->addBatchInfo($batchData);
                            }//end if
                
                            if ( $item_discount && $this->Settings->product_discount) {
                                $discount = $item_discount;
                                $dpos = strpos($discount, $percentage);
                                if ($dpos !== false) {
                                    $pds = explode("%", $discount);
                                    $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($item_net_cost)) * (Float) ($pds[0])) / 100), 4);
                                } else {
                                    $pr_discount = $this->sma->formatDecimal($discount);
                                }
                            } else {
                                $pr_discount = 0;
                            }
                            $unit_cost = $this->sma->formatDecimal($item_net_cost - $pr_discount);
                            $item_net_cost = $unit_cost;
                            $pr_item_discount = $this->sma->formatDecimal(($pr_discount * $item_quantity), 4);
                            $product_discount += $pr_item_discount;

                            if (isset($item_tax_rate) && $item_tax_rate ) {

                                //if ($tax_details = $this->purchases_model->getTaxRateByName($item_tax_rate)) {
                                if ($tax_details = $this->purchases_model->getTaxRateByCode($item_tax_rate)) {

                                    $pr_tax = $tax_details->id;
                                    if ($tax_details->type == 1) {
                                        $taxmethod = ($item_tax_method == '') ? $product_details->tax_method : $item_tax_method;
                                        if ($product_details && $taxmethod == 1) {
                                            $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                            $tax = $tax_details->rate . "%";
                                        } else {
                                            $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                            $tax = $tax_details->rate . "%";
                                            $item_net_cost = $unit_cost - $item_tax;
                                        }
                                    } elseif ($tax_details->type == 2) {

                                        $item_tax = $this->sma->formatDecimal($tax_details->rate);
                                        $tax = $tax_details->rate;
                                    }
                                    $pr_item_tax = $this->sma->formatDecimal(($item_tax * $item_quantity), 4);
                                } else {
                                    $this->session->set_flashdata('error', lang("tax_not_found") . " ( " . $item_tax_rate . " ). " . lang("line_no") . " " . $rw);
                                    redirect($_SERVER["HTTP_REFERER"]);
                                }
                            } elseif ($product_details->tax_rate) {
                                $pr_tax = $product_details->tax_rate;
                                $tax_details = $this->site->getTaxRateByID($pr_tax);
                                if ($tax_details->type == 1) {
                                    if (!$product_details->tax_method) {
                                        $item_tax = $this->sma->formatDecimal((($item_net_cost - $pr_discount) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                        $tax = $tax_details->rate . "%";
                                        $item_net_cost = $unit_cost - $item_tax;
                                    } else {
                                        $item_tax = $this->sma->formatDecimal((($item_net_cost - $pr_discount) * $tax_details->rate) / 100, 4);
                                        $tax = $tax_details->rate . "%";
                                    }
                                } elseif ($tax_details->type == 2) {
                                    $item_tax = $this->sma->formatDecimal($tax_details->rate);
                                    $tax = $tax_details->rate;
                                }
                                $pr_item_tax = $this->sma->formatDecimal(($item_tax * $item_quantity), 4);
                            } else {

                                $pr_tax = 0;
                                $pr_item_tax = 0;
                                $tax = "";
                            }

                            $product_tax += $pr_item_tax;
                            $subtotal = (($item_net_cost * $item_quantity) + $pr_item_tax);
                            $unit = $this->site->getUnitByID($product_details->unit);

                            $item_option->id = $item_option->id ? $item_option->id : 0;
                            
                            $quantity_balance = 0;
                            if($status == "received"){                                
                                $quantity_balance = $item_quantity;
                            }
                            
                            if($pr_item_tax) {
                                if($GSTType == 'IGST'){
                                    $igst = $pr_item_tax;
                                    $gst_rate = $tax_details->rate;
                                } else {
                                    $cgst = $sgst = ($pr_item_tax / 2);
                                    $gst_rate = ($tax_details->rate / 2);
                                }
                            }
                            
                            $products[] = [
                                'product_id'        => $product_details->id,
                                'product_code'      => $item_code,
                                'product_name'      => $product_details->name,
                                'option_id'         => $item_option_id,
                                'batch_number'      => $item_batch_number,
                                'net_unit_cost'     => $item_net_cost,
                                'quantity'          => $item_quantity,                                
                                'unit_quantity'     => $item_quantity,
                                'quantity_balance'  => $quantity_balance,
                                'quantity_received' => $quantity_balance,
                                'warehouse_id'      => $warehouse_id,
                                'item_tax'          => $pr_item_tax,
                                'tax_rate_id'       => $pr_tax,
                                'tax'               => $tax,
                                'tax_method'        => $product_details->tax_method,
                                'discount'          => $item_discount,
                                'item_discount'     => $pr_item_discount,
                                'expiry'            => $item_expiry,
                                'subtotal'          => $this->sma->formatDecimal($subtotal),
                                'date'              => date('Y-m-d H:i:s', strtotime($date)),
                                'status'            => $status,
                                'unit_cost'         => $this->sma->formatDecimal(($item_net_cost + $item_tax), 4),
                                'real_unit_cost'    => $real_unit_cost,
                              //'real_unit_cost' => $this->sma->formatDecimal(($item_net_cost + $item_tax + $pr_discount), 4),
                                'product_unit_id'   => $product_details->unit,
                                'product_unit_code' => $unit->code, 
                                'hsn_code'          => $product_details->hsn_code,
                                'gst_rate'          => $gst_rate,
                                'cgst'              => $cgst,
                                'sgst'              => $sgst,
                                'igst'              => $igst,
                            ];

                            if($item_price && $mrp)
                            {
                                $pm=[
                                    'price'             =>$this->sma->formatDecimal($item_price),
                                    'mrp'               =>$this->sma->formatDecimal($mrp),
                                ];
                                $price_mrp[$i]=$pm;
                            }
                            $total += $this->sma->formatDecimal(($item_net_cost * $item_quantity), 4);
                        } 
                        else {
                            
                            $str = $str.", ".$csv_pr['code']."(Line No:".$rw.")";
                            $flag =1;
                            // $this->session->set_flashdata('error', $this->lang->line("pr_not_found") . " (" . $str . "). " . $this->lang->line("line_no") . " " . $rw);
                            // redirect($_SERVER["HTTP_REFERER"]);
                        }
                        $rw++;
                        
                        $total_cgst += $cgst;
                        $total_sgst += $sgst;
                        $total_igst += $igst;
                    }//end if
                    else{
                       $invalidItems[] = [
                           "line_number"    => $rw,
                           "item_code"      => $item_code,
                           "net_unit_cost"  => $net_unit_cost,
                           "quantity"       => $quantity,
                       ];
                    }
                    $i++;
                }//end foreach 
                if($flag == 1)
                {
                  $this->session->set_flashdata('error', $this->lang->line("pr_not_found") . " (" . substr($str,2) . "). ");// . $this->lang->line("line_no") . " " . $rw);
                  redirect($_SERVER["HTTP_REFERER"]);  
                }
            }//if File Uploaded

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
            
            $total_discount = $this->sma->formatDecimal(($order_discount + $product_discount), 4);

            if ($this->Settings->tax2 != 0) {
                $order_tax_id = $this->input->post('order_tax');
                if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                    if ($order_tax_details->type == 2) {
                        $order_tax = $this->sma->formatDecimal($order_tax_details->rate);
                    }
                    if ($order_tax_details->type == 1) {
                        $order_tax = $this->sma->formatDecimal((($total + $product_tax - $total_discount) * $order_tax_details->rate) / 100);
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
            
            $reference = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('po');

            $data = [
                'reference_no'      => $reference,
                'date'              => $date,
                'supplier_id'       => $supplier_id,
                'supplier'          => $supplier,
                'warehouse_id'      => $warehouse_id,
                'note'              => $note,
                'total'             => $total,
                'product_discount'  => $product_discount,
                'order_discount_id' => $order_discount_id,
                'order_discount'    => $order_discount,
                'total_discount'    => $total_discount,
                'product_tax'       => $product_tax,
                'order_tax_id'      => $order_tax_id,
                'order_tax'         => $order_tax,
                'total_tax'         => $total_tax,
                'shipping'          => $this->sma->formatDecimal($shipping),
                'grand_total'       => $grand_total,
                'status'            => $status,
                'rounding'          => $rounding,
                'created_by'        => $this->session->userdata('username'),
                'cgst'              => $total_cgst,
                'sgst'              => $total_sgst,
                'igst'              => $total_igst,
            ];

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

            //$this->sma->print_arrays($data, $products);
        }
//        if(isset($data)) {
//            echo "<br/>products: ".count($products);
//            echo "<br/>invalidItems: ".count($invalidItems);
//            echo "<pre>";
//            print_r($invalidItems);
//            print_r($data);
//            print_r($products);
//            echo "</pre>";
//            exit;
//        }
        if ($this->form_validation->run() == true ) {
            
            $this->db->insert('purchases', $data);
            
            $purchase_id = $this->db->insert_id();

            if ($this->site->getReference('po') == $data['reference_no']) {
                $this->site->updateReference('po');
            }
            
            if($products) {
            
                $pinsert = 0;
                foreach ($products as $item) {
            
                    $item['purchase_id'] = $purchase_id;
            
                    $items[] = $item;
                    $pinsert++;
                }
                
                if( $this->purchases_model->addPurchaseItemsByCsv($items,$price_mrp)){
                   
                    if ($data['status'] == 'received' ) {
                        $this->site->syncQuantity(NULL, $purchase_id);
                    }
                }
            }
            
            $this->session->set_flashdata('message', $this->lang->line("purchase_added"));
            redirect("purchases/index");            
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['ponumber'] = ''; // $this->site->getReference('po');

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('purchases'), 'page' => lang('purchases')), array('link' => '#', 'page' => lang('add_purchase_by_csv')));
            $meta = array('page_title' => lang('add_purchase_by_csv'), 'bc' => $bc);
            $this->page_construct('purchases/purchase_by_csv', $meta, $this->data);
        }
    }

    public function delete($id = null) {
        $this->sma->checkPermissions(null, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        
        $purchase = $this->purchases_model->getPurchaseByID($id);
        
        if($purchase->status=="partial" || $purchase->status=="received") {
            
            $this->session->set_flashdata('error', 'The purchase could not delete because the item had already been added to stocks');
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->sma->storeDeletedData('purchases', 'id', $id);
            if ($this->purchases_model->deletePurchase($id)) {
                if ($this->input->is_ajax_request()) {
                    echo lang("purchase_deleted");
                    die();
                }
                $this->session->set_flashdata('message', lang('purchase_deleted'));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        }
    }

    /* public function infotest()
      {
      $product = $this->purchases_model->getProductByID(71);
      echo '<pre>';
      print_r($product->image);
      echo '</pre>';
      } */

    public function suggestions() {

        $quantity = $this->input->get('quantity', true);
        $subtotal = $this->input->get('subtotal', true);

        $termstring = $this->input->get('term', true);
        $supplier_id = $this->input->get('supplier_id', true);

        if (strlen($termstring) < 3 || !$termstring) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }
        $termdata = explode(",", str_replace(" ", "", $termstring));
      
       
        foreach ($termdata as $term) {
           
            if ($term != '') {
                $qty_value = explode("-", $term); //Using Barcode - Qty
                $product_qty = isset($qty_value[1]) ? $qty_value[1] : 1;
                $exp = $qty_value[0] ? explode("_", $qty_value[0]) : ''; // Using Barcode
                $analyzed = $this->sma->analyze_term($qty_value[0]);
                $sr = $analyzed['term'];
                if (strpos($term, '-') !== false) {
                    $rows = $this->purchases_model->getProductNames($qty_value);
                }else {
                    $rows = $this->purchases_model->getProductNames($sr);

                }
                $option_id = $analyzed['option_id'] ? $analyzed['option_id'] : 0;
                
                if ($rows) {
                    // $c = str_replace(".", "", microtime(true));
                    $r = 0;
                    foreach ($rows as $row) {
                        $c = str_replace(".", "", microtime(true));

                        $option = $options = $productbatches = false;
                        $option = isset($exp[1]) ? $exp[1] : false;
                        $row->item_tax_method = $row->tax_method;
                        $options = $this->purchases_model->getProductOptions($row->id);
                        $product = $this->purchases_model->getProductByID($row->id);

                        // if(!empty($options)) {
                        //     $option_id = (!$option_id && $product->primary_variant) ? $product->primary_variant : $options[0]->id; //Set primary varients
                        // }
                    
                        /**
                         * Batch Config
                         *  */
                        $batchoption = $productbatches = false;
                        $row->batch = false;
                        $row->batch_number = '';
                        $row->cost = $supplier_id ? $this->getSupplierCost($supplier_id, $row) : $row->cost;
                        $row->real_unit_cost = $row->cost;
                        $row->base_unit_cost = $row->cost;
                        $row->expiry = '';
                        
                        if ($this->Settings->product_batch_setting) {
                            
                            $this->load->model('products_model');
                            $batch_option = ($option_id && $product->storage_type == 'packed') ? $option_id : 0;

                            $productbatches = $this->products_model->getProductVariantsBatch($row->id);

                            $batch = $productbatches[$batch_option];

                            if ($batch) {
                                $firstKey = key($batch);
                                $batchoption = $batch;
                                $row->batch = $batchoption[$firstKey]->id;
                                $row->batch_number = $batchoption[$firstKey]->batch_no;
                                //$row->cost = $supplier_id ? $this->getSupplierCost($supplier_id, $row) : $batchoption[0]->cost;
                                $row->cost = $batchoption[$firstKey]->cost;
                                $row->real_unit_cost = $batchoption[$firstKey]->cost;
                                $row->base_unit_cost = $batchoption[$firstKey]->cost;
                                $row->expiry = ($batchoption[$firstKey]->expiry != '' && $batchoption[$firstKey]->expiry !== '0000-00-00') ? $batchoption[$firstKey]->expiry : '';

                            } 
                        }
                        
                        /**
                         * End Batch Configs
                         **/
                        if ($options) {
                            $opt = ($option_id && $r == 0 ) ? $this->purchases_model->getProductOptionByID($option_id) : current($options);
                            if (!$option_id || $r > 0) {
                                $option_id = $opt->id;
                            }
                        } else {
                            $opt = json_decode('{}');
                            $opt->cost = 0;                    
                            $option_id = false;
                        }
                        
                        $row->option = $option_id ? $option_id : 0;
                        $row->supplier_part_no = '';
                        if ($opt->cost != 0) {
                            $row->cost = $opt->cost;
                            $row->base_unit_cost = $row->cost;
                            $row->real_unit_cost = $row->cost;
                        }
                        $unitData = $this->purchases_model->getUnitById($row->unit);
                        $row->unit_lable = $unitData->name;
                        //$row->cost = $supplier_id ? $this->getSupplierCost($supplier_id, $row) : $row->cost;
                        //$row->real_unit_cost = $row->cost;
                        $row->base_quantity =(float) $product_qty;
                        $row->base_unit = $row->unit;
                        //$row->base_unit_cost = $row->cost;
                        $row->unit = $row->purchase_unit ? $row->purchase_unit : $row->unit;

                        $row->new_entry = 1;
                        $row->qty = (float) $product_qty;;
                        $row->qty = ($quantity) ? $quantity :  $row->qty ;
                        $subtotal = $this->sma->formatDecimal($subtotal);
                        if ($subtotal == '') {
                            $row->real_unit_cost = $row->cost;
                        }else{
                            $row->real_unit_cost = $subtotal;
                        }
                        $row->quantity_balance = '';
                        $row->discount = '0';
                        $row->image = $product->image;

                        unset($row->details, $row->product_details, $row->price, $row->file, $row->supplier1price, $row->supplier2price, $row->supplier3price, $row->supplier4price, $row->supplier5price, $row->supplier1_part_no, $row->supplier2_part_no, $row->supplier3_part_no, $row->supplier4_part_no, $row->supplier5_part_no);

                        $units = $this->site->getUnitsByBUID($row->base_unit);
                        $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                    
                        $row_id = $row->id . $row->option;
                        
                        $pr[] = ['id' => ($c + $r), 'item_id' => $row_id, 'image' => $product->image, 'label' => $row->name . " (" . $row->code . ")",
                            'row' => $row, 'tax_rate' => $tax_rate, 'units' => $units, 'options' => $options, 'option_batches'=> $productbatches, 'batchs' => $batchoption, 'primary_variant'=> $product->primary_variant];
                        
                        $r++;
                    }
                }else {
                    $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
                }
            }
        }

            if (!empty($pr)) {
                $this->sma->send_json($pr);
            } else {
                $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
            }
    }

      public function purchase_actions() {
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
                        $this->sma->storeDeletedData('purchases', 'id', $id);
                        $this->purchases_model->deletePurchase($id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("purchases_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'combine') {

                    $html = $this->combine_pdf($_POST['val']);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:G1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:G1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Purchases');
                    $this->excel->getActiveSheet()->setTitle(lang('purchases'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('date'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('reference_no'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('supplier'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('grand_total'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('Balance'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('status'));
                    $this->excel->getActiveSheet()->SetCellValue('G2', lang('payment_status'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $purchase = $this->purchases_model->getPurchaseByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($purchase->date));
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $purchase->reference_no);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $purchase->supplier);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $this->sma->formatMoney($purchase->grand_total));
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->formatMoney($purchase->grand_total - $purchase->paid));
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $purchase->status);
                        $this->excel->getActiveSheet()->SetCellValue('G' . $row, $purchase->payment_status);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'purchases_' . date('Y_m_d_H_i_s');
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
                }elseif ($this->input->post('form_action') == 'export_invoice_to_excel') {
                      $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:N1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:N1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Purchase');
                    $styleArray = array(
                        'borders' => array(
                            'allborders' => array(
                                'style' => PHPExcel_Style_Border::BORDER_THIN
                            )
                        )
                    );
                    $this->excel->getActiveSheet()->getStyle("A3:N3")->applyFromArray($styleArray);
                    $this->excel->getActiveSheet()->SetCellValue('A3', lang('date'));
                    $this->excel->getActiveSheet()->SetCellValue('B3', lang('reference_no'));
                    $this->excel->getActiveSheet()->SetCellValue('C3', lang('warehouse'));
                    $this->excel->getActiveSheet()->SetCellValue('D3', lang('supplier'));
                    $this->excel->getActiveSheet()->SetCellValue('E3', lang('Proudct Code'));
                    $this->excel->getActiveSheet()->SetCellValue('F3', lang('Product Name'));
                    $this->excel->getActiveSheet()->SetCellValue('G3', lang('Article Code'));
                    $this->excel->getActiveSheet()->SetCellValue('H3', lang('HSN_Code'));
                    $this->excel->getActiveSheet()->SetCellValue('I3', lang('Variant'));
//                    $this->excel->getActiveSheet()->SetCellValue('J3', lang('price'));
                    $this->excel->getActiveSheet()->SetCellValue('J3', lang('Unit Cost'));
                    $this->excel->getActiveSheet()->SetCellValue('K3', lang('quantity'));
                    $this->excel->getActiveSheet()->SetCellValue('L3', lang('discount'));
                    $this->excel->getActiveSheet()->SetCellValue('M3', lang('Tax_Percent'));
                    $this->excel->getActiveSheet()->SetCellValue('N3', lang('Sub Total'));

                    $row = 4;
                    foreach ($_POST['val'] as $id) {
                        $purchase = $this->purchases_model->getPurchaseByID($id);
                        
                        $parchaseItems = $this->purchases_model->getAllPurchaseItems($id);
                      
                        $warehouse = $this->site->getWarehouseBy_ID($purchase->warehouse_id);
                    
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($purchase->date));
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $purchase->reference_no);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $warehouse->name);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $purchase->supplier);
                        $start = $row;

                        $netPrice = $totalQty = $unitPrice = $price = 0;
                        foreach ($parchaseItems as $item) {
                          print
                            $options_color = $this->purchases_model->getProductOptionsByShapeId($item->shade_id, $item->product_id, COLOR);
                             
                            $tax_rate = $this->site->getTaxRateByID($item->tax_rate_id);

                            $this->excel->getActiveSheet()->setCellValueExplicit('E' . $row, $item->product_code, PHPExcel_Cell_DataType::TYPE_STRING);
                            $this->excel->getActiveSheet()->SetCellValue('F' . $row, $item->product_name);
                            $this->excel->getActiveSheet()->setCellValueExplicit('G' . $row, $item->style_code, PHPExcel_Cell_DataType::TYPE_STRING);
                            $this->excel->getActiveSheet()->SetCellValue('H' . $row, $item->hsn_code);
                            $this->excel->getActiveSheet()->SetCellValue('I' . $row, $item->variant);
//                            $this->excel->getActiveSheet()->SetCellValue('J' . $row, $item->price);
                            $this->excel->getActiveSheet()->SetCellValue('J' . $row, $item->real_unit_cost);
                            $this->excel->getActiveSheet()->SetCellValue('K' . $row, $item->quantity);
                            $this->excel->getActiveSheet()->setCellValueExplicit('L' . $row, $item->discount, PHPExcel_Cell_DataType::TYPE_STRING);
                            $this->excel->getActiveSheet()->SetCellValue('M' . $row, $tax_rate->name);
                            $this->excel->getActiveSheet()->SetCellValue('N' . $row, $item->subtotal);

                            $netPrice += $item->real_unit_cost;
                            $totalQty += $item->quantity;
                            $unitPrice += $item->net_unit_cost;
//                            $price += $item->price;



                            $styleArray = array(
                                'borders' => array(
                                    'allborders' => array(
                                        'style' => PHPExcel_Style_Border::BORDER_THIN
                                    ),
                                )
                            );
                            $this->excel->getActiveSheet()->getStyle("A" . $row . ":N" . $row)->applyFromArray($styleArray);


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
                        $this->excel->getActiveSheet()->getStyle("A" . $row . ":N" . $row)->applyFromArray($styleArray);

                        $styleArray = array(
                            'font' => array(
                                'bold' => true,
                                'color' => array('rgb' => '000'),
                                'size' => 12,
                                'name' => 'Calibri'
                        ));

                        $this->excel->getActiveSheet()->getStyle("A" . $row . ":N" . $row)->applyFromArray($styleArray);


                        $this->excel->getActiveSheet()->getStyle("R" . $row)->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getStyle("K" . $row)->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getStyle("L" . $row)->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getStyle("M" . $row)->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getStyle("Q" . $row)->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->mergeCells("A" . $row . ":I" . $row);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, " Total");
                        $this->excel->getActiveSheet()->SetCellValue('N' . $row, $purchase->grand_total);
                        $this->excel->getActiveSheet()->SetCellValue('J' . $row, $netPrice);
                        $this->excel->getActiveSheet()->SetCellValue('K' . $row, $totalQty);
//                        $this->excel->getActiveSheet()->SetCellValue('J' . $row, $price);

                        //removed one line from excelsheet
                        // $row = $row + 1;

                        $row++;
                    }

                    $filename = 'purchases_items_' . date('Y_m_d_H_i_s');
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                    return $objWriter->save('php://output');
                }
            } else {
                $this->session->set_flashdata('error', $this->lang->line("no_purchase_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }


    public function payments($id = null) {
        $this->sma->checkPermissions(false, true);

        $this->data['payments'] = $this->purchases_model->getPurchasePayments($id);
        $this->data['inv'] = $this->purchases_model->getPurchaseByID($id);
        $this->load->view($this->theme . 'purchases/payments', $this->data);
    }

    public function payment_note($id = null) {
        $this->sma->checkPermissions('payments', true);
        $payment = $this->purchases_model->getPaymentByID($id);
        $inv = $this->purchases_model->getPurchaseByID($payment->purchase_id);
        $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['payment'] = $payment;
        $this->data['page_title'] = $this->lang->line("payment_note");

        $this->load->view($this->theme . 'purchases/payment_note', $this->data);
    }

    public function add_payment($id = null) {
        $this->sma->checkPermissions('payments', true);
        $this->load->helper('security');
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $purchase = $this->purchases_model->getPurchaseByID($id);
        if ($purchase->payment_status == 'paid' && $purchase->grand_total == $purchase->paid) {
            $this->session->set_flashdata('error', lang("purchase_already_paid"));
            $this->sma->md();
        }
        elseif(!$this->site->isWarehouseActive($purchase->warehouse_id)){
            $this->session->set_flashdata('error', 'Purchase warehouse is inactive. Can not modified payments.');
            $this->sma->md();
        }
        //$this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('amount-paid', lang("amount"), 'required');
        $this->form_validation->set_rules('paid_by', lang("paid_by"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $payment = array(
                'date' => $date,
                'purchase_id' => $this->input->post('purchase_id'),
                'reference_no' => $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('ppay'),
                'amount' => $this->input->post('amount-paid'),
                'paid_by' => $this->input->post('paid_by'),
                'cheque_no' => $this->input->post('cheque_no'),
                'cc_no' => $this->input->post('pcc_no'),
                'cc_holder' => $this->input->post('pcc_holder'),
                'cc_month' => $this->input->post('pcc_month'),
                'cc_year' => $this->input->post('pcc_year'),
                'cc_type' => $this->input->post('pcc_type'),
                'note' => $this->sma->clear_tags($this->input->post('note')),
                'created_by' => $this->session->userdata('user_id'),
                'transaction_id' => $this->input->post('transaction_id'),
                'type' => 'sent',
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

        if ($this->form_validation->run() == true && $this->purchases_model->addPayment($payment)) {
            $this->session->set_flashdata('message', lang("payment_added"));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['inv'] = $purchase;
            $this->data['payment_ref'] = ''; //$this->site->getReference('ppay');
            $this->data['modal_js'] = $this->site->modal_js();

            $this->load->view($this->theme . 'purchases/add_payment', $this->data);
        }
    }

    public function edit_payment($id = null) {
        $this->sma->checkPermissions('edit', true);
        $this->load->helper('security');
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->form_validation->set_rules('reference_no', lang("reference_no"), 'required');
        $this->form_validation->set_rules('amount-paid', lang("amount"), 'required');
        $this->form_validation->set_rules('paid_by', lang("paid_by"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $payment = array(
                'date' => $date,
                'purchase_id' => $this->input->post('purchase_id'),
                'reference_no' => $this->input->post('reference_no'),
                'amount' => $this->input->post('amount-paid'),
                'paid_by' => $this->input->post('paid_by'),
                'cheque_no' => $this->input->post('cheque_no'),
                'cc_no' => $this->input->post('pcc_no'),
                'cc_holder' => $this->input->post('pcc_holder'),
                'cc_month' => $this->input->post('pcc_month'),
                'cc_year' => $this->input->post('pcc_year'),
                'cc_type' => $this->input->post('pcc_type'),
                'transaction_id' => $this->input->post('transaction_id'),
                'note' => $this->sma->clear_tags($this->input->post('note')),
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

        if ($this->form_validation->run() == true && $this->purchases_model->updatePayment($id, $payment)) {
            $this->session->set_flashdata('message', lang("payment_updated"));
            redirect("purchases");
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['payment'] = $this->purchases_model->getPaymentByID($id);
            
            $this->data['modal_js'] = $this->site->modal_js();

            $this->load->view($this->theme . 'purchases/edit_payment', $this->data);
        }
    }

    public function delete_payment($id = null) {
        $this->sma->checkPermissions('delete', true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->sma->storeDeletedData('payments', 'id', $id);
        if ($this->purchases_model->deletePayment($id)) {
            //echo lang("payment_deleted");
            $this->session->set_flashdata('message', lang("payment_deleted"));
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    /* -------------------------------------------------------------------------------- */

    public function expenses($id = null) {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('expenses')));
        $meta = array('page_title' => lang('expenses'), 'bc' => $bc);
        $this->page_construct('purchases/expenses', $meta, $this->data);
    }

    public function getExpenses() {
        $this->sma->checkPermissions('expenses');

        $detail_link = anchor('purchases/expense_note/$1', '<i class="fa fa-file-text-o"></i> ' . lang('expense_note'), 'data-toggle="modal" data-target="#myModal2"');
        $edit_link = anchor('purchases/edit_expense/$1', '<i class="fa fa-edit"></i> ' . lang('edit_expense'), 'data-toggle="modal" data-target="#myModal"');
        //$attachment_link = '<a href="'.base_url('assets/uploads/$1').'" target="_blank"><i class="fa fa-chain"></i></a>';
        $delete_link = "<a href='#' class='po' title='<b>" . $this->lang->line("delete_expense") . "</b>' data-content=\"<p>"
                . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('purchases/delete_expense/$1') . "'>"
                . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> "
                . lang('delete_expense') . "</a>";
        $action = '<div class="text-center"><div class="btn-group text-left">'
                . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">'
                . lang('actions') . ' <span class="caret"></span></button>
        <ul class="dropdown-menu pull-right" role="menu">
            <li>' . $detail_link . '</li>
            <li>' . $edit_link . '</li>
            <li>' . $delete_link . '</li>
        </ul>
    </div></div>';

        $this->load->library('datatables');

        $this->datatables
                ->select($this->db->dbprefix('expenses') . ".id as id, date, reference,{$this->db->dbprefix('warehouses')}.name as warehouse , {$this->db->dbprefix('expense_categories')}.name as category, amount, note, CONCAT({$this->db->dbprefix('users')}.first_name, ' ', {$this->db->dbprefix('users')}.last_name) as user, attachment", false)
                ->from('expenses')
                ->join('users', 'users.id=expenses.created_by', 'left')
                ->join('warehouses', 'warehouses.id=expenses.warehouse_id', 'left')
                ->join('expense_categories', 'expense_categories.id=expenses.category_id', 'left')
                ->group_by('expenses.id');

        if (!$this->Owner && !$this->Admin && !$this->session->userdata('view_right')) {
            $this->datatables->where('created_by', $this->session->userdata('user_id'));
        }

        if ($this->input->get('warehouse')) {
            $getwarehouse = str_replace("_", ",", $this->input->get('warehouse'));
            $this->datatables->where('expenses.warehouse_id IN(' . $getwarehouse . ')');
        }
        //$this->datatables->edit_column("attachment", $attachment_link, "attachment");
        $this->datatables->add_column("Actions", $action, "id");
        echo $this->datatables->generate();
    }

    public function expense_note($id = null) {
        $expense = $this->purchases_model->getExpenseByID($id);
         if($expense->warehouse_id){
            $warehouse = $this->site->getWarehouseByID($expense->warehouse_id);
        }
  

        $this->data['user'] = $this->site->getUser($expense->created_by);
        $this->data['category'] = $expense->category_id ? $this->purchases_model->getExpenseCategoryByID($expense->category_id) : NULL;
        $this->data['warehouse'] = $expense->warehouse_id ? $warehouse[$expense->warehouse_id] : NULL;
        $this->data['expense'] = $expense;
        $this->data['page_title'] = $this->lang->line("expense_note");
        $this->load->view($this->theme . 'purchases/expense_note', $this->data);
    }

    public function add_expense() {
        $this->sma->checkPermissions('expenses', true);
        $this->load->helper('security');

        //$this->form_validation->set_rules('reference', lang("reference"), 'required');
        $this->form_validation->set_rules('amount', lang("amount"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $data = array(
                'date' => $date,
                'reference' => $this->input->post('reference') ? $this->input->post('reference') : $this->site->getReference('ex'),
                'amount' => $this->input->post('amount'),
                'created_by' => $this->session->userdata('user_id'),
                'note' => $this->sma->clear_tags($this->input->post('note')),
                'category_id' => $this->input->post('category', true),
                'warehouse_id' => $this->input->post('warehouse', true),
            );

            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path;
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
                $data['attachment'] = $photo;
            }

            //$this->sma->print_arrays($data);
        } elseif ($this->input->post('add_expense')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('purchases/expenses');
        }

        if ($this->form_validation->run() == true && $this->purchases_model->addExpense($data)) {
            $this->session->set_flashdata('message', lang("expense_added"));
            redirect('purchases/expenses');
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['exnumber'] = ''; //$this->site->getReference('ex');
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['categories'] = $this->purchases_model->getExpenseCategories();
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'purchases/add_expense', $this->data);
        }
    }

    public function edit_expense($id = null) {
        $this->sma->checkPermissions('edit', true);
        $this->load->helper('security');
        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $this->form_validation->set_rules('reference', lang("reference"), 'required');
        $this->form_validation->set_rules('amount', lang("amount"), 'required');
        $this->form_validation->set_rules('userfile', lang("attachment"), 'xss_clean');
        if ($this->form_validation->run() == true) {
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $data = array(
                'date' => $date,
                'reference' => $this->input->post('reference'),
                'amount' => $this->input->post('amount'),
                'note' => $this->input->post('note', true),
                'category_id' => $this->input->post('category', true),
                'warehouse_id' => $this->input->post('warehouse', true),
            );
            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path;
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
                $data['attachment'] = $photo;
            }

            //$this->sma->print_arrays($data);
        } elseif ($this->input->post('edit_expense')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->purchases_model->updateExpense($id, $data)) {
            $this->session->set_flashdata('message', lang("expense_updated"));
            redirect("purchases/expenses");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['expense'] = $this->purchases_model->getExpenseByID($id);
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['categories'] = $this->purchases_model->getExpenseCategories();
            $this->load->view($this->theme . 'purchases/edit_expense', $this->data);
        }
    }

    public function delete_expense($id = null) {
        $this->sma->checkPermissions('delete', true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $expense = $this->purchases_model->getExpenseByID($id);
        if ($this->purchases_model->deleteExpense($id)) {
            if ($expense->attachment) {
                unlink($this->upload_path . $expense->attachment);
            }
            echo lang("expense_deleted");
        }
    }

    public function expense_actions() {
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
                        $this->purchases_model->deleteExpense($id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("expenses_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:G1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:G1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Expenses');

                    $this->excel->getActiveSheet()->setTitle(lang('expenses'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('date'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('reference'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('warehouse'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('category'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('amount'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('note'));
                    $this->excel->getActiveSheet()->SetCellValue('G2', lang('created_by'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $expense = $this->purchases_model->getExpenseByID($id);
                        $getwarehouse = $this->db->select('name')->where('id', $expense->warehouse_id)->get('sma_warehouses')->row();
                        $getcategory = $this->db->select('name')->where('id', $expense->category_id)->get('expense_categories')->row();

                        $user = $this->site->getUser($expense->created_by);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($expense->date));
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $expense->reference);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $getwarehouse->name);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $getcategory->name);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->formatMoney($expense->amount));
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $expense->note);
                        $this->excel->getActiveSheet()->SetCellValue('G' . $row, $user->first_name . ' ' . $user->last_name);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(35);
                    $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'expenses_' . date('Y_m_d_H_i_s');
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
                $this->session->set_flashdata('error', $this->lang->line("no_expense_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function view_return($id = null) {
        $this->sma->checkPermissions('return_purchases');

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->purchases_model->getReturnByID($id);
        if (!$this->session->userdata('view_right')) {
            $this->sma->view_rights($inv->created_by);
        }
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
        $this->data['payments'] = $this->purchases_model->getPaymentsForPurchase($id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->purchases_model->getAllReturnItems($id);
        $this->data['purchase'] = $this->purchases_model->getPurchaseByID($inv->purchase_id);
        $this->load->view($this->theme . 'purchases/view_return', $this->data);
    }

    public function return_purchase($id = null) {
        $this->sma->checkPermissions('return_purchases');

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $purchase = $this->purchases_model->getPurchaseByID($id);
        if ($purchase->return_id) {
            $this->session->set_flashdata('error', lang("purchase_already_returned"));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->form_validation->set_rules('return_surcharge', lang("return_surcharge"), 'required');

        if ($this->form_validation->run() == true) {

            $reference = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('rep');
            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }

            $return_surcharge = $this->input->post('return_surcharge') ? $this->input->post('return_surcharge') : 0;
            $note = $this->sma->clear_tags($this->input->post('note'));
                        
            $GSTType = ($purchase->igst > 0) ? 'IGST' : 'GST';
                        
            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            $percentage = '%';
            $total_cgst = $total_sgst = $total_igst = 0;
            $i = isset($_POST['product']) ? sizeof($_POST['product']) : 0;
            for ($r = 0; $r < $i; $r++) {
                if ($_POST['quantity'][$r] > 0) {
                    $item_id = $_POST['product_id'][$r];
                    $item_code = $_POST['product'][$r];
                    $purchase_item_id = $_POST['purchase_item_id'][$r];
                    $item_option = isset($_POST['product_option'][$r]) && !empty($_POST['product_option'][$r]) && $_POST['product_option'][$r] != 'false' ? $_POST['product_option'][$r] : 0;
                    $real_unit_cost = $this->sma->formatDecimal($_POST['real_unit_cost'][$r]);
                    $unit_cost = $this->sma->formatDecimal($_POST['unit_cost'][$r]);
                    $item_unit_quantity = (0 - $_POST['quantity'][$r]);
                    $item_expiry = isset($_POST['expiry'][$r]) ? $_POST['expiry'][$r] : '';
                    $item_batch_number = isset($_POST['batch_number'][$r]) ? $_POST['batch_number'][$r] : '';
                    $item_tax_rate = isset($_POST['product_tax'][$r]) ? $_POST['product_tax'][$r] : null;
                    $item_discount = isset($_POST['product_discount'][$r]) ? $_POST['product_discount'][$r] : null;
                    $item_unit = $_POST['product_unit'][$r];
                    $item_quantity = (0 - $_POST['product_base_quantity'][$r]);
                    $item_tax_method = $_POST['tax_method'][$r];
                    if (isset($item_code) && isset($real_unit_cost) && isset($unit_cost) && isset($item_quantity)) {
                        $product_details = $this->purchases_model->getProductByCode($item_code);

                        $item_type = $product_details->type;
                        $item_name = $product_details->name;

                        if (isset($item_discount)) {
                            $discount = $item_discount;
                            $dpos = strpos($discount, $percentage);
                            if ($dpos !== false) {
                                $pds = explode("%", $discount);
                                $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($unit_cost)) * (Float) ($pds[0])) / 100), 4);
                            } else {
                                $pr_discount = $this->sma->formatDecimal($discount);
                            }
                        } else {
                            $pr_discount = 0;
                        }
                        $unit_cost = $this->sma->formatDecimal($unit_cost - $pr_discount);
                        $item_net_cost = $unit_cost;
                        $pr_item_discount = $this->sma->formatDecimal(($pr_discount * $item_unit_quantity), 4);
                        $product_discount += $pr_item_discount;
                        $taxmethod = ($item_tax_method == '') ? $product_details->tax_method : $item_tax_method;
                        $cgst = $sgst = $igst = $gst_rate = 0;
                        
                        if (isset($item_tax_rate) && $item_tax_rate != 0) {
                            $pr_tax = $item_tax_rate;
                            $tax_details = $this->site->getTaxRateByID($pr_tax);
                            if ($tax_details->type == 1 && $tax_details->rate != 0) {
                                if ($taxmethod == 1) {
                                    $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                    $tax = $tax_details->rate . "%";
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                    $tax = $tax_details->rate . "%";
                                    $item_net_cost = $unit_cost - $item_tax;
                                }

                                /* if (!$product_details->tax_method) {
                                  $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                  $tax = $tax_details->rate . "%";
                                  } else {
                                  $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                  $tax = $tax_details->rate . "%";
                                  } */
                            } elseif ($tax_details->type == 2) {

                                if ($product_details && $taxmethod == 1) {
                                    $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / 100, 4);
                                    $tax = $tax_details->rate . "%";
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($unit_cost) * $tax_details->rate) / (100 + $tax_details->rate), 4);
                                    $tax = $tax_details->rate . "%";
                                    $item_net_cost = $unit_cost - $item_tax;
                                }

                                $item_tax = $this->sma->formatDecimal($tax_details->rate);
                                $tax = $tax_details->rate;
                            }
                            $pr_item_tax = $this->sma->formatDecimal($item_tax * $item_unit_quantity, 4);
                        } else {
                            $pr_tax = 0;
                            $pr_item_tax = 0;
                            $tax = "";
                        }

                        $product_tax += $pr_item_tax;
                        $subtotal = $this->sma->formatDecimal((($item_net_cost * $item_unit_quantity) + $pr_item_tax), 4);
                        $unit = $this->site->getUnitByID($item_unit);
                        $item_option = $item_option ? $item_option : 0;
                        
                        if($pr_item_tax) {
                            if($GSTType == 'IGST'){
                                $igst = $pr_item_tax;
                                $gst_rate = $tax_details->rate;
                            } else {
                                $cgst = $sgst = ($pr_item_tax / 2);
                                $gst_rate = ($tax_details->rate / 2);
                            }
                        }                        
                        
                        $products[] = array(
                            'product_id' => $item_id,
                            'product_code' => $item_code,
                            'product_name' => $item_name,
                            'option_id' => $item_option,
                            'net_unit_cost' => $item_net_cost,
                            'unit_cost' => $this->sma->formatDecimal($item_net_cost + $item_tax),
                            'quantity' => $item_quantity,
                            'product_unit_id' => $item_unit,
                            'product_unit_code' => $unit->code,
                            'unit_quantity' => $item_unit_quantity,
                            'batch_number' => $item_batch_number,
                            'quantity_balance' => $item_quantity,
                            'warehouse_id' => $purchase->warehouse_id,
                            'item_tax' => $pr_item_tax,
                            'tax_rate_id' => $pr_tax,
                            'tax' => $tax,
                            'discount' => $item_discount,
                            'item_discount' => $pr_item_discount,
                            'subtotal' => $this->sma->formatDecimal($subtotal),
                            'real_unit_cost' => $real_unit_cost,
                            'status' => 'returned',
                            'purchase_item_id' => $purchase_item_id,
                            'tax_method' => $item_tax_method,
                            'gst_rate' => $gst_rate,
                            'cgst' => $cgst,
                            'sgst' => $sgst,
                            'igst' => $igst,
                        );
                        
                        $total_cgst += $cgst;
                        $total_sgst += $sgst;
                        $total_igst += $igst;
                        
                        $total += $this->sma->formatDecimal(($item_net_cost * $item_unit_quantity), 4);
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
                  $order_discount = $this->sma->formatDecimal($order_discount_id);
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

            $total_tax = $this->sma->formatDecimal(($product_tax + $order_tax), 4);
            $grand_total = $this->sma->formatDecimal(($total + $total_tax + $this->sma->formatDecimal($return_surcharge) - $order_discount), 4);
            $data = array('date' => $date,
                'purchase_id' => $id,
                'reference_no' => $purchase->reference_no,
                'supplier_id' => $purchase->supplier_id,
                'supplier' => $purchase->supplier,
                'warehouse_id' => $purchase->warehouse_id,
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
                'return_purchase_ref' => $reference,
                'status' => 'returned',
                'payment_status' => $purchase->payment_status == 'paid' ? 'due' : 'pending',
                'cgst' => $total_cgst,
                'sgst' => $total_sgst,
                'igst' => $total_igst,
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

        if ($this->form_validation->run() == true && $this->purchases_model->addPurchase($data, $products)) {
            $this->session->set_flashdata('message', lang("return_purchase_added"));
            redirect("purchases");
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['inv'] = $purchase;
            $status = $this->data['inv']->status;
            if ($this->data['inv']->status != 'received' && $this->data['inv']->status != 'partial') {
                $this->session->set_flashdata('error', lang("purchase_status_x_received"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
            if ($this->Settings->disable_editing) {
                if ($this->data['inv']->date <= date('Y-m-d', strtotime('-' . $this->Settings->disable_editing . ' days'))) {
                    $this->session->set_flashdata('error', lang("purchase_x_cant_return_older_than_x_days"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }
            }
            $inv_items = $this->purchases_model->getAllPurchaseItems($id);
            krsort($inv_items);

            $c = rand(100000, 9999999);
            foreach ($inv_items as $item) {

                $row = $this->site->getProductByID($item->product_id);
                $row->expiry = (($item->expiry && $item->expiry != '0000-00-00') ? $this->sma->hrsd($item->expiry) : '');
                $row->batch_number = $item->batch_number;
                $row->base_quantity = $item->quantity;
                $row->base_unit = $row->unit ? $row->unit : $item->product_unit_id;
                $row->base_unit_cost = $row->cost ? $row->cost : $item->unit_cost;
                $row->unit = $item->product_unit_id;
                $row->received = $item->quantity_received ? $item->quantity_received : $item->quantity;
                $row->qty = $item->unit_quantity;
                if ($status == 'partial')
                    $row->qty = $item->unit_quantity - $row->received;
                $row->oqty = $item->unit_quantity;
                $row->purchase_item_id = $item->id;
                $row->supplier_part_no = $item->supplier_part_no;

                $row->quantity_balance = $item->quantity_balance + ($item->quantity - $row->received);
                $row->discount = $item->discount ? $item->discount : '0';
                $options = $this->purchases_model->getProductOptions($row->id);
                $row->option = $item->option_id ? $item->option_id : 0;
                $row->real_unit_cost = $item->real_unit_cost;
                $row->cost = $this->sma->formatDecimal($item->net_unit_cost + ($item->item_discount / $item->quantity));
                $row->tax_rate = $item->tax_rate_id;
                $row->tax_method = $item->tax_method;
                $unitData = $this->purchases_model->getUnitById($row->unit);
                $row->unit_lable = $unitData->name;
                unset($row->details, $row->product_details, $row->price, $row->file, $row->product_group_id);
                $units = $this->site->getUnitsByBUID($row->base_unit);
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                $ri = $this->Settings->item_addition ? $row->id : $c;

                $pr[$ri] = array('id' => $c, 'item_id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'row' => $row, 'units' => $units, 'tax_rate' => $tax_rate, 'options' => $options);

                $c++;
            }

            $this->data['inv_items'] = json_encode($pr);
            $this->data['id'] = $id;
            $this->data['reference'] = '';
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('purchases'), 'page' => lang('purchases')), array('link' => '#', 'page' => lang('return_purchase')));
            $meta = array('page_title' => lang('return_purchase'), 'bc' => $bc);
            $this->page_construct('purchases/return_purchase', $meta, $this->data);
        }
    }

    public function getSupplierCost($supplier_id, $product) {
        switch ($supplier_id) {
            case $product->supplier1:
                $cost = $product->supplier1price > 0 ? $product->supplier1price : $product->cost;
                break;
            case $product->supplier2:
                $cost = $product->supplier2price > 0 ? $product->supplier2price : $product->cost;
                break;
            case $product->supplier3:
                $cost = $product->supplier3price > 0 ? $product->supplier3price : $product->cost;
                break;
            case $product->supplier4:
                $cost = $product->supplier4price > 0 ? $product->supplier4price : $product->cost;
                break;
            case $product->supplier5:
                $cost = $product->supplier5price > 0 ? $product->supplier5price : $product->cost;
                break;
            default:
                $cost = $product->cost;
        }
        return $cost;
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

        if ($this->form_validation->run() == true && $this->purchases_model->updateStatus($id, $status, $note)) {
            $this->session->set_flashdata('message', lang('status_updated'));
            redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'sales');
        } else {

            $this->data['inv'] = $this->purchases_model->getPurchaseByID($id);
            $this->data['returned'] = FALSE;
            if ($this->data['inv']->status == 'returned' || $this->data['inv']->return_id) {
                $this->data['returned'] = TRUE;
            }
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'purchases/update_status', $this->data);
        }
    }

    public function get_purchaseDetails($purchase_id) {
        if (!$purchase_id) {
            die('No quote selected.');
        }
        $inv = $this->purchases_model->getPurchaseByID($purchase_id);
        $this->data['supplier'] = $this->site->getCompanyByID($inv->supplier_id);
        return $this->sma->send_json($this->data['supplier']);
    }


        
    /**************************************************************************
     *  Purchase Notification
     **************************************************************************/
    /**
     * Purchase Notification
     */
    public function purchase_notification(){
         $this->sma->checkPermissions('notification',TRUE,'purchases');
        $this->data['notification_List'] = $this->purchases_model->get_Purchase_Notification();
        $this->purchases_model->removed_notification();
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Purchases_Notification')));
        $meta = array('page_title' => lang('Purchases_Notification'), 'bc' => $bc);
        $this->page_construct('purchases/purchase_notification', $meta, $this->data);
    }
    
    /**
     * 
     * @param type $notificationId
     */
    public function purchase_notification_items($notificationId, $privatekey){
       $data = $this->purchases_model->getPurchaseNotificationItems($notificationId);
              
               $this->data['items'] = unserialize($data->items);
               $this->data['notificationDetails'] = $data;
               $this->data['privatekey'] = $privatekey;
               $this->load->view($this->theme . 'purchases/notification_items', $this->data);
    }
    
    
     public function new_purchase() {

        $result = $this->purchases_model->count_new_purchase();
        if (is_array($result)) {
            echo json_encode($result);
        } else {
            echo json_encode(['num' => 0]);
        }
    }
    
     public function new_purchase_alert() {

        echo $this->purchases_model->set_notification_order_status($status);
    }
    
    
    public function storesyndata(){
        $notificationId = $this->input->post('notificationId');
        $syndata = $this->input->post('syndata');
       if($this->purchases_model->syndataStore($notificationId,$syndata)){
            $get =  $this->purchases_model->getPurchaseNotificationItems($notificationId);
                $getItems = unserialize($get->synced_data)->items;

                $notexitProduct =[];
                foreach($getItems as $itemvalue){
                    if($this->purchases_model->checkProductExit($itemvalue->product_code)== FALSE){
                        $notexitProduct[] =$itemvalue->product_code;
                    }
               }  

               if(!empty($notexitProduct)){
//                    $notexitProduct[]= '04760673';
                    $response = [
                         'status' => 'ERROR',
                         'requrestURL' => $get->request_pos_url,
                         'data'    => $notexitProduct,

                     ];
               }else{
                   $response = [
                         'status' => 'SUCCESS',
                   ];
               }

               echo json_encode($response);
       }
        
    }
    
    
    /**
     * Store Purchases 
     * @param type $notificationId
     */
    public function storePurchase($notificationId){
       $getData =  $this->purchases_model->getPurchaseNotificationItems($notificationId);
       $purchasesData = unserialize($getData->synced_data);
       $supplier = $this->purchases_model->checkSupplier($purchasesData->biller); //

        $reference = $this->site->getReference('po');
       $purchasefield = [
           'reference_no'       =>      $reference,
              
           'date'               =>      $purchasesData->date,
           'warehouse_id'       =>      $this->Settings->default_warehouse,    
           'total'              =>      $purchasesData->total,
           'product_discount'   =>      $purchasesData->product_discount,
           'order_discount'     =>      $purchasesData->order_discount,
           'total_discount'     =>      $purchasesData->total_discount,
           'product_tax'        =>      $purchasesData->product_tax,
           'order_tax_id'       =>      $purchasesData->order_tax_id,
           'order_tax'          =>      $purchasesData->order_tax,
           'total_tax'          =>      $purchasesData->total_tax,
           'shipping'           =>      $purchasesData->shipping,
           'grand_total'        =>      $purchasesData->grand_total,
           'paid'               =>      $purchasesData->paid,
           'created_by'         =>      $this->session->userdata('user_id'),
           'rounding'           =>      $purchasesData->rounding,
           'cgst'               =>      $purchasesData->cgst,
           'sgst'               =>      $purchasesData->sgst,
           'igst'               =>      $purchasesData->igst,
           'status'             =>      'received',
       ];  
        
       if(!empty($supplier)){
           $purchasefield['supplier_id']  =    $supplier['id'];
           $purchasefield['supplier']     =    $supplier['name'];
       }
      
       $productItems = [];
       foreach($purchasesData->items as $items){
           $optionid = 0;
            if($items->option_id){
                $optionid = $this->purchases_model->getoptionId($items->variants);
            }
           $productDetails =   $this->products_model->getProductCode($items->product_code);
           $unitcodeId =   $this->purchases_model->getUnitCodeId($items->product_unit_code);
           $productItems[]= [
              
               'product_id'         =>  $productDetails->id,
               'product_code'       =>  $productDetails->code,  
               'product_name'       =>  $productDetails->name,
               'option_id'          =>  $optionid,
               'warehouse_id'       =>  $this->Settings->default_warehouse,
               'net_unit_cost'      =>  $items->net_unit_price,
               'quantity'           =>  $items->quantity,
               'item_tax'           =>  $items->item_tax,
               'tax_rate_id'        =>  $items->tax_rate_id,
               'tax'                =>  $items->tax,
               'tax_method'         =>  $items->tax_method,
               'discount'           =>  $items->discount,
               'item_discount'      =>  $items->item_discount,              
               'subtotal'           =>  $items->subtotal,
               'date'               =>  $purchasesData->date,
               'unit_cost'          =>  $items->unit_price,
               'real_unit_cost'     =>  $items->real_unit_price,
               'quantity_received'  =>  $items->quantity,
               'quantity_balance'  =>   $items->quantity,
               'unit_quantity'      =>  $items->unit_quantity,
               'product_unit_id'    =>  $unitcodeId,
               'product_unit_code'  =>  $items->product_unit_code,
               'hsn_code'           =>  $items->hsn_code,
               'gst_rate'           =>  $items->gst_rate,
               'cgst'               =>  $items->cgst,
               'sgst'               =>  $items->sgst,
               'igst'               =>  $items->igst,
               'updated_at'         =>  date('Y-m-d h:i:s'),
               'status'             =>      'received',
           ];
       }
       
        if($this->purchases_model->addPurchase($purchasefield, $productItems)){
            $this->db->where(['id'=> $notificationId])->update('notifications_purchases',['is_status'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
            $response = [
                         'status' => 'SUCCESS',
                         'msg'    => 'Purchase has been added successfully',
                   ];
        }else{
             $response = [
                         'status' => 'ERROR',
                         'msg'    => 'Purchase not add, Please try again',
                     ];
        }

       
         echo json_encode($response);
    }
 
    /*************************************************************
     * End Purchase Notification
     *************************************************************/
    

}
