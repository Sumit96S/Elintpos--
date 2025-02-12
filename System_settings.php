<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class system_settings extends MY_Controller {

    private $user_printer_setting_access = array('printers', 'getprinters', 'edit_printer_bill', 'add_printer_bill', 'delete_printer_bill');

    public function __construct() {
        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->shopowner->group_id != '8' && $this->shopowner->group_id != '9') {
            if (!$this->Owner) {
                $allowed = 0;
                if (!in_array($this->v, $this->user_printer_setting_access)) {
                    $this->session->set_flashdata('warning', lang('access_denied'));
                    redirect('welcome');
                }
                if ($this->GP['printer-setting'] == 1 && in_array($this->v, $this->user_printer_setting_access)) {
                    $allowed = 1;
                }
                if ($allowed === 0) {
                    $this->session->set_flashdata('warning', lang('access_denied'));
                    redirect('welcome');
                }
            }
        }

        $this->lang->load('settings', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('companies_model');
        $this->load->model('settings_model');
        $this->load->model('products_model');
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';

        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif';
        $this->allowed_file_size = '2048';
    }

    public function index() {
        $this->form_validation->set_rules('site_name', lang('site_name'), 'trim|required');
        $this->form_validation->set_rules('dateformat', lang('dateformat'), 'trim|required');
        $this->form_validation->set_rules('timezone', lang('timezone'), 'trim|required');
        $this->form_validation->set_rules('mmode', lang('maintenance_mode'), 'trim|required');
        //$this->form_validation->set_rules('logo', lang('logo'), 'trim');
        $this->form_validation->set_rules('iwidth', lang('image_width'), 'trim|numeric|required');
        $this->form_validation->set_rules('iheight', lang('image_height'), 'trim|numeric|required');
        $this->form_validation->set_rules('twidth', lang('thumbnail_width'), 'trim|numeric|required');
        $this->form_validation->set_rules('theight', lang('thumbnail_height'), 'trim|numeric|required');
        $this->form_validation->set_rules('display_all_products', lang('display_all_products'), 'trim|numeric|required');
        $this->form_validation->set_rules('watermark', lang('watermark'), 'trim|required');
        $this->form_validation->set_rules('currency', lang('default_currency'), 'trim|required');
        $this->form_validation->set_rules('email', lang('default_email'), 'trim|required');
        $this->form_validation->set_rules('language', lang('language'), 'trim|required');
        $this->form_validation->set_rules('warehouse', lang('default_warehouse'), 'trim|required');
        $this->form_validation->set_rules('biller', lang('default_biller'), 'trim|required');
        $this->form_validation->set_rules('tax_rate', lang('product_tax'), 'trim|required');
        $this->form_validation->set_rules('tax_rate2', lang('invoice_tax'), 'trim|required');
        $this->form_validation->set_rules('sales_prefix', lang('sales_prefix'), 'trim');
        $this->form_validation->set_rules('quote_prefix', lang('quote_prefix'), 'trim');
        $this->form_validation->set_rules('purchase_prefix', lang('purchase_prefix'), 'trim');
        $this->form_validation->set_rules('transfer_prefix', lang('transfer_prefix'), 'trim');
        $this->form_validation->set_rules('delivery_prefix', lang('delivery_prefix'), 'trim');
        $this->form_validation->set_rules('payment_prefix', lang('payment_prefix'), 'trim');
        $this->form_validation->set_rules('return_prefix', lang('return_prefix'), 'trim');
        $this->form_validation->set_rules('expense_prefix', lang('expense_prefix'), 'trim');
        $this->form_validation->set_rules('detect_barcode', lang('detect_barcode'), 'trim|required');
        $this->form_validation->set_rules('theme', lang('theme'), 'trim|required');
        $this->form_validation->set_rules('rows_per_page', lang('rows_per_page'), 'trim|required|greater_than[9]|less_than[501]');
        $this->form_validation->set_rules('accounting_method', lang('accounting_method'), 'trim|required');
        $this->form_validation->set_rules('product_serial', lang('product_serial'), 'trim|required');
        $this->form_validation->set_rules('product_discount', lang('product_discount'), 'trim|required');
        $this->form_validation->set_rules('bc_fix', lang('bc_fix'), 'trim|numeric|required');
        $this->form_validation->set_rules('protocol', lang('email_protocol'), 'trim|required');
        $this->form_validation->set_rules('default_printer', lang('default_printer'), 'trim|required');

        $this->form_validation->set_rules('product_batch_setting', lang('product_batch_setting'), 'trim|required');
        $this->form_validation->set_rules('product_batch_required', lang('product_batch_required'), 'trim|required');


        if ($this->input->post('protocol') == 'smtp') {
            $this->form_validation->set_rules('smtp_host', lang('smtp_host'), 'required');
            $this->form_validation->set_rules('smtp_user', lang('smtp_user'), 'required');
            $this->form_validation->set_rules('smtp_pass', lang('smtp_pass'), 'required');
            $this->form_validation->set_rules('smtp_port', lang('smtp_port'), 'required');
        }
        if ($this->input->post('protocol') == 'sendmail') {
            $this->form_validation->set_rules('mailpath', lang('mailpath'), 'required');
        }
        $this->form_validation->set_rules('decimals', lang('decimals'), 'trim|required');
        $this->form_validation->set_rules('decimals_sep', lang('decimals_sep'), 'trim|required');
        $this->form_validation->set_rules('thousands_sep', lang('thousands_sep'), 'trim|required');
        $this->form_validation->set_rules('sms_sender', lang('sms_sender'), 'trim|required');

        $this->load->library('encrypt');

        if ($this->form_validation->run() == TRUE) {

            $language = $this->input->post('language');

            if ((file_exists(APPPATH . 'language' . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . 'sma_lang.php') && is_dir(APPPATH . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . $language)) || $language == 'english') {
                $lang = $language;
            } else {
                $this->session->set_flashdata('error', lang('language_x_found'));
                redirect("system_settings");
                $lang = 'english';
            }

            $tax1 = ($this->input->post('tax_rate') != 0) ? 1 : 0;
            $tax2 = ($this->input->post('tax_rate2') != 0) ? 1 : 0;

            $data = array(
                'site_name' => DEMO ? 'Stock Manager Advance' : $this->input->post('site_name'), 
                'rows_per_page' => $this->input->post('rows_per_page'),                 
                'timezone' => DEMO ? 'Asia/Kuala_Lumpur' : $this->input->post('timezone'),
                'iwidth' => $this->input->post('iwidth'), 
                'iheight' => $this->input->post('iheight'), 
                'twidth' => $this->input->post('twidth'), 
                'theight' => $this->input->post('theight'), 
                'watermark' => $this->input->post('watermark'), 
                // 'reg_ver' => $this->input->post('reg_ver'),
                // 'allow_reg' => $this->input->post('allow_reg'),
                // 'reg_notification' => $this->input->post('reg_notification'),
                'default_email' => DEMO ? 'noreply@sma.tecdiary.my' : $this->input->post('email'),
                'language' => $lang, 'default_warehouse' => $this->input->post('warehouse'),
                'default_tax_rate' => $this->input->post('tax_rate'),
                'default_tax_rate2' => $this->input->post('tax_rate2'),
                'sales_prefix' => $this->input->post('sales_prefix'),
                'quote_prefix' => $this->input->post('quote_prefix'),
                'purchase_prefix' => $this->input->post('purchase_prefix'),
                'transfer_prefix' => $this->input->post('transfer_prefix'),
                'delivery_prefix' => $this->input->post('delivery_prefix'),
                'payment_prefix' => $this->input->post('payment_prefix'),
                'ppayment_prefix' => $this->input->post('ppayment_prefix'),
                'qa_prefix' => $this->input->post('qa_prefix'),
                'return_prefix' => $this->input->post('return_prefix'),
                'expense_prefix' => $this->input->post('expense_prefix'),
                'auto_detect_barcode' => trim($this->input->post('detect_barcode')),
                'product_serial' => $this->input->post('product_serial'),
                'customer_group' => $this->input->post('customer_group'),
                'product_expiry' => $this->input->post('product_expiry'),
                'product_discount' => $this->input->post('product_discount'),
                'bc_fix' => $this->input->post('bc_fix'),
                'tax1' => $tax1,
                'tax2' => $tax2,
                'racks' => $this->input->post('racks'),
                'restrict_calendar' => $this->input->post('restrict_calendar'),
                'captcha' => $this->input->post('captcha'),
                'protocol' => DEMO ? 'mail' : $this->input->post('protocol'),
                'mailpath' => $this->input->post('mailpath'), 'smtp_host' => $this->input->post('smtp_host'),
                'smtp_user' => $this->input->post('smtp_user'),
                'smtp_port' => $this->input->post('smtp_port'),
                'smtp_crypto' => $this->input->post('smtp_crypto') ? $this->input->post('smtp_crypto') : NULL,
                'decimals' => $this->input->post('decimals'),
                'decimals_sep' => $this->input->post('decimals_sep'),
                'thousands_sep' => $this->input->post('thousands_sep'),
                'default_biller' => $this->input->post('biller'),
                'invoice_view' => $this->input->post('invoice_view'),
                'rtl' => $this->input->post('rtl'),
                'each_spent' => $this->input->post('each_spent') ? $this->input->post('each_spent') : NULL, 'ca_point' => $this->input->post('ca_point') ? $this->input->post('ca_point') : NULL, 'each_sale' => $this->input->post('each_sale') ? $this->input->post('each_sale') : NULL, 'sa_point' => $this->input->post('sa_point') ? $this->input->post('sa_point') : NULL, 'sac' => $this->input->post('sac'), 'qty_decimals' => $this->input->post('qty_decimals'), 'display_all_products' => $this->input->post('display_all_products'), 'display_symbol' => $this->input->post('display_symbol'), 'symbol' => $this->input->post('symbol'), 'remove_expired' => $this->input->post('remove_expired'), 'barcode_separator' => $this->input->post('barcode_separator'), 'set_focus' => $this->input->post('set_focus'), 'disable_editing' => $this->input->post('disable_editing'), 'price_group' => $this->input->post('price_group'), 'barcode_img' => $this->input->post('barcode_renderer'),
                'sales_image' => $this->input->post('sales_image'),
                'quotation_image' => $this->input->post('quotation_image'),
                'purchase_image' => $this->input->post('purchase_image'),
                'invoice_product_image' => $this->input->post('invoice_product_image'),
                'display_zero_sale_for_product_report' => $this->input->post('display_zero_sale_for_product_report'),
                'offlinepos_warehouse' => $this->input->post('offlinepos_warehouse'),
                'offlinepos_biller' => $this->input->post('offlinepos_biller'),
                'show_quotation_unit_price' => $this->input->post('show_quotation_unit_price'),
                'show_sales_unit_price' => $this->input->post('show_sales_unit_price'),
                'show_purchase_unit_cost' => $this->input->post('show_purchase_unit_cost'),
                'sales_order_discount' => $this->input->post('sales_order_discount'),
                'purchase_order_discount' => $this->input->post('purchase_order_discount'),
                'default_printer' => $this->input->post('default_printer'),
                'auto_acceptance' => $this->input->post('auto_acceptance'),
                'tax_classification_view' => $this->input->post('tax_classification_view'),
                'invoice_view_purchase' => $this->input->post('invoice_view_purchase'),
                'tax_classification_view__purchase' => $this->input->post('tax_classification_view__purchase'),
                'synch_reward_points' => $this->input->post('synch_reward_points'),
                'synch_customers' => $this->input->post('synch_customers'),
                'sale_multiple_return_edit' => $this->input->post('sale_multiple_return_edit'),
                'award_point_by_percent' => $this->input->post('award_point_by_percent'),
                'each_redeem' => $this->input->post('each_redeem'),
                'reports_send_on_email' => $this->input->post('reports_send_on_email'),
                'product_weight' => $this->input->post('product_weight'),
                'send_sales_excel'  => $this->input->post('send_sales_excel'),  
                'send_sales_excel'  => $this->input->post('send_sales_excel'),  
                'send_sales_excel'  => $this->input->post('send_sales_excel'),   
                'barcode_separator_weight' => $this->input->post('barcode_separator_weight'),
                'synced_data_sales'  => $this->input->post('synced_data_sales'),
                'modify_qty_add_products' => $this->input->post('modify_qty_add_products'),
                'barcode_scan_camera'  => $this->input->post('barcode_scan_camera'),
                'deposit_discount'   => $this->input->post('deposit_discount'),
                'deposit_cash_limit'   => $this->input->post('deposit_cash_limit'),
                'deposit_service_offer_manage'   => $this->input->post('deposit_service_offer_manage'),
                'deposit_service_offer' => $this->input->post('deposit_service_offer'),
                'payment_settlement' => $this->input->post('payment_settlement_method'),
                'sms_brand_name' => $this->input->post('sms_brand_name'),
                'sms_promotional_header' => $this->input->post('sms_promotional_header'),
                'sms_sender' => $this->input->post('sms_sender'),
				'production_order_age' => $this->input->post('production_order_age'),
				'set_order_dispatch' => $this->input->post('set_order_dispatch'),
                'product_batch_setting' => $this->input->post('product_batch_setting'), // Added this line
            );


            if( $this->input->post('edit_code')=='e603796dddf1b823126b32fd6ad212d2') {
                
                $data['default_currency'] = $this->input->post('currency');          
                $data['accounting_method'] = $this->input->post('accounting_method');
                $data['mmode'] = $this->input->post('mmode');
                $data['theme'] = $this->input->post('theme');
                $data['dateformat'] = $this->input->post('dateformat');
                $data['attributes'] = $this->input->post('attributes');
                $data['update_cost'] = $this->input->post('update_cost');
                $data['product_external_platform'] = $this->input->post('product_external_platform');
                $data['product_batch_setting'] = $this->input->post('product_batch_setting');
                $data['product_batch_required'] = $this->input->post('product_batch_required');	
                $data['overselling'] = $this->input->post('restrict_sale');
                $data['reference_format'] = $this->input->post('reference_format');
                $data['item_addition'] = $this->input->post('item_addition');
                $data['add_tax_in_cart_unit_price'] = $this->input->post('add_tax_in_cart_unit_price');
                $data['add_discount_in_cart_unit_price'] = $this->input->post('add_discount_in_cart_unit_price');
                $data['invoice_length'] = $this->input->post('invoice_length');
                $data['invoice_format'] = $this->input->post('invoice_format');
                $data['sale_loose_products_with_variants'] = $this->input->post('sale_loose_products_with_variants');	
                $data['sms_sender'] = $this->input->post('sms_sender');
                $data['sms_promotional_header'] = $this->input->post('sms_promotional_header');	
                $data['financial_type'] = $this->input->post('financial_type');                
            }
            
            
            if ($this->input->post('smtp_pass')) {
                $data['smtp_pass'] = $this->encrypt->encode($this->input->post('smtp_pass'));
            }
            
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateSetting($data)) {
            if (!DEMO && TIMEZONE != $data['timezone']) {
                if (!$this->write_index($data['timezone'])) {
                    $this->session->set_flashdata('error', lang('setting_updated_timezone_failed'));
                    redirect('system_settings');
                }
            }

            $this->session->set_flashdata('message', lang('setting_updated'));
            redirect("system_settings");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['billers'] = $this->site->getAllCompanies('biller');
            $this->data['settings'] = $this->settings_model->getSettings();
            $this->data['currencies'] = $this->settings_model->getAllCurrencies();
            $this->data['date_formats'] = $this->settings_model->getDateFormats();
            $this->data['tax_rates'] = $this->settings_model->getAllTaxRates();
            $this->data['customer_groups'] = $this->settings_model->getAllCustomerGroups();
            $this->data['price_groups'] = $this->settings_model->getAllPriceGroups();
            $this->data['location_types'] = $this->settings_model->getAllLocationTypes();
            $this->data['owned_by'] = $this->settings_model->getAllowned_by();
            $this->data['warehouses'] = $this->settings_model->getAllWarehouses();
            $this->data['printers'] = $this->site->getAllPrinter();

            // $this->data['smtp_pass'] = (($this->data['settings']->smtp_pass)) ? @$this->encrypt->decode($this->data['settings']->smtp_pass) : '';
            $this->data['post_theme'] = $this->site->getpostheme();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('system_settings')));
            $meta = array('page_title' => lang('system_settings'), 'bc' => $bc);
            $this->page_construct('settings/index', $meta, $this->data);
        }
    }

    public function paypal() {

        $this->form_validation->set_rules('active', $this->lang->line('activate'), 'trim');
        $this->form_validation->set_rules('account_email', $this->lang->line('paypal_account_email'), 'trim|valid_email');
        if ($this->input->post('active')) {
            $this->form_validation->set_rules('account_email', $this->lang->line('paypal_account_email'), 'required');
        }
        $this->form_validation->set_rules('fixed_charges', $this->lang->line('fixed_charges'), 'trim');
        $this->form_validation->set_rules('extra_charges_my', $this->lang->line('extra_charges_my'), 'trim');
        $this->form_validation->set_rules('extra_charges_other', $this->lang->line('extra_charges_others'), 'trim');

        if ($this->form_validation->run() == TRUE) {

            $data = array('active' => $this->input->post('active'), 'account_email' => $this->input->post('account_email'), 'fixed_charges' => $this->input->post('fixed_charges'), 'extra_charges_my' => $this->input->post('extra_charges_my'), 'extra_charges_other' => $this->input->post('extra_charges_other'));
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updatePaypal($data)) {
            $this->session->set_flashdata('message', $this->lang->line('paypal_setting_updated'));
            redirect("system_settings/paypal");
        } else {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

            $this->data['paypal'] = $this->settings_model->getPaypalSettings();

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('paypal_settings')));
            $meta = array('page_title' => lang('paypal_settings'), 'bc' => $bc);
            $this->page_construct('settings/paypal', $meta, $this->data);
        }
    }

    public function skrill() {

        $this->form_validation->set_rules('active', $this->lang->line('activate'), 'trim');
        $this->form_validation->set_rules('account_email', $this->lang->line('paypal_account_email'), 'trim|valid_email');
        if ($this->input->post('active')) {
            $this->form_validation->set_rules('account_email', $this->lang->line('paypal_account_email'), 'required');
        }
        $this->form_validation->set_rules('fixed_charges', $this->lang->line('fixed_charges'), 'trim');
        $this->form_validation->set_rules('extra_charges_my', $this->lang->line('extra_charges_my'), 'trim');
        $this->form_validation->set_rules('extra_charges_other', $this->lang->line('extra_charges_others'), 'trim');

        if ($this->form_validation->run() == TRUE) {

            $data = array('active' => $this->input->post('active'), 'account_email' => $this->input->post('account_email'), 'fixed_charges' => $this->input->post('fixed_charges'), 'extra_charges_my' => $this->input->post('extra_charges_my'), 'extra_charges_other' => $this->input->post('extra_charges_other'));
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateSkrill($data)) {
            $this->session->set_flashdata('message', $this->lang->line('skrill_setting_updated'));
            redirect("system_settings/skrill");
        } else {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

            $this->data['skrill'] = $this->settings_model->getSkrillSettings();

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('skrill_settings')));
            $meta = array('page_title' => lang('skrill_settings'), 'bc' => $bc);
            $this->page_construct('settings/skrill', $meta, $this->data);
        }
    }

    public function change_logo() {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            $this->sma->md();
        }
        $this->load->helper('security');
        $this->form_validation->set_rules('site_logo', lang("site_logo"), 'xss_clean');
        $this->form_validation->set_rules('login_logo', lang("login_logo"), 'xss_clean');
        $this->form_validation->set_rules('biller_logo', lang("biller_logo"), 'xss_clean');
        if ($this->form_validation->run() == TRUE) {

            if ($_FILES['site_logo']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path . 'logos/';
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = 400;
                $config['max_height'] = 200;
                $config['overwrite'] = FALSE;
                $config['max_filename'] = 25;
                //$config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('site_logo')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $site_logo = $this->upload->file_name;
                $this->db->update('settings', array('logo' => $site_logo), array('setting_id' => 1));
            }

            if ($_FILES['login_logo']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path . 'logos/';
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = 400;
                $config['max_height'] = 200;
                $config['overwrite'] = FALSE;
                $config['max_filename'] = 25;
                //$config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('login_logo')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $login_logo = $this->upload->file_name;
                $this->db->update('settings', array('logo2' => $login_logo), array('setting_id' => 1));
            }

            if ($_FILES['biller_logo']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path . 'logos/';
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = 400;
                $config['max_height'] = 200;
                $config['overwrite'] = FALSE;
                $config['max_filename'] = 25;
                //$config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('biller_logo')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
            }

            if ($_FILES['webshop_logo']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path . 'logos/';
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = 400;
                $config['max_height'] = 200;
                $config['overwrite'] = FALSE;
                $config['max_filename'] = 25;
                //$config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('webshop_logo')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
               
                $this->db->where(['id'=>'1'])->update('sma_webshop_settings',['logo' => $photo ]);
            }
            
            
            if ($_FILES['webshop_favicon']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path . 'logos/';
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = 400;
                $config['max_height'] = 200;
                $config['overwrite'] = FALSE;
                $config['max_filename'] = 25;
                //$config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('webshop_favicon')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                
                
                $this->db->where(['id'=>'1'])->update('sma_webshop_settings',['favicon' => $photo ]);
            }
             

            $this->session->set_flashdata('message', lang('logo_uploaded'));
            redirect($_SERVER["HTTP_REFERER"]);
        } elseif ($this->input->post('upload_logo')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/change_logo', $this->data);
        }
    }

    public function write_index($timezone) {

        $template_path = './assets/config_dumps/index.php';
        $output_path = SELF;
        $index_file = file_get_contents($template_path);
        $new = str_replace("%TIMEZONE%", $timezone, $index_file);
        $handle = fopen($output_path, 'w+');
        @chmod($output_path, 0777);

        if (is_writable($output_path)) {
            if (fwrite($handle, $new)) {
                @chmod($output_path, 0644);
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    public function updates() {
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
            $this->db->update('settings', array('purchase_code' => $this->input->post('purchase_code', TRUE), 'envato_username' => $this->input->post('envato_username', TRUE)), array('setting_id' => 1));
            redirect('system_settings/updates');
        } else {
            $fields = array('version' => $this->Settings->version, 'code' => $this->Settings->purchase_code, 'username' => $this->Settings->envato_username, 'site' => base_url());
            $this->load->helper('update');
            $protocol = is_https() ? 'https://' : 'http://';
            $updates = get_remote_contents($protocol . 'api.tecdiary.com/v1/update/', $fields);
            $this->data['updates'] = json_decode($updates);
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('updates')));
            $meta = array('page_title' => lang('updates'), 'bc' => $bc);
            $this->page_construct('settings/updates', $meta, $this->data);
        }
    }

    public function install_update($file, $m_version, $version) {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        $this->load->helper('update');
        save_remote_file($file . '.zip');
        $this->sma->unzip('./files/'.$this->Customer_assets.'/updates/' . $file . '.zip');
        if ($m_version) {
            $this->load->library('migration');
            if (!$this->migration->latest()) {
                $this->session->set_flashdata('error', $this->migration->error_string());
                redirect("system_settings/updates");
            }
        }
        $this->db->update('settings', array('version' => $version, 'update' => 0), array('setting_id' => 1));
        unlink('./files/'.$this->Customer_assets.'/updates/' . $file . '.zip');
        $this->session->set_flashdata('success', lang('update_done'));
        redirect("system_settings/updates");
    }

    public function backups() {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        //  $this->data['files'] = glob('./files/backups/*.zip', GLOB_BRACE);
        $this->data['dbs'] = glob('./files/'.$this->Customer_assets.'/backups/*.txt', GLOB_BRACE);
        // krsort($this->data['files']);
        krsort($this->data['dbs']);
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('backups')));
        $meta = array('page_title' => lang('backups'), 'bc' => $bc);
        $this->page_construct('settings/backups', $meta, $this->data);
    }

    public function get_db_views() {


        $db_tables = $this->db->query("SELECT * FROM INFORMATION_SCHEMA.TABLES "
                        . "WHERE TABLE_SCHEMA = '" . $this->db->database . "' AND TABLE_TYPE = 'VIEW' ")
                ->result_array();
        $dbviewlist = [];
        if (is_array($db_tables)) {
            foreach ($db_tables as $key => $tableInfo) {

                $dbviewlist[] = $tableInfo['TABLE_NAME'];
            }
        }

        return $dbviewlist;
    }

    public function backup_database() {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        $this->load->dbutil();
        //$ignore = array('sma_purchase_gst','sma_quote_gst','sma_sales_gst','sma_view_combo_products_items_sale','sma_view_non_sync_custmer','view_sales_gst_report','view_sales_history');
        $ignore = $this->get_db_views();
        $prefs = array('format' => 'txt', 'filename' => 'sma_db_backup.sql', 'ignore' => $ignore);
        $back = $this->dbutil->backup($prefs);
        $backup = & $back;

        $posversion = json_decode($this->settings_model->getSettings()->pos_version);


        // $db_name = 'db-backup-on-' . date("Y-m-d-H-i-s") . '.txt';
        $db_name = 'db-backup-on-' . date("Y-m-d-H-i-s") . '_version_' . $posversion->version . '.txt';
        $save = './files/'.$this->Customer_assets.'/backups/' . $db_name;
        $this->load->helper('file');
        write_file($save, $backup);

        /**
         * Backup Manage in database
         */
        $fields = [
            'file_name' => rtrim($db_name, '.txt'),
            'description' => $this->input->post('description'),
            'pos_version' => $posversion->version,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->settings_model->backups('Insert', $fields);
        /**
         * End 
         */
        $this->session->set_flashdata('messgae', lang('db_saved'));
        redirect("system_settings/backups");
    }

    public function restore_database($dbfile) {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        $file = './files/'.$this->Customer_assets.'/backups/' . $dbfile . '.txt';
        //$lines = file_get_contents('./files/backups/' . $dbfile . '.txt');
        $res = $this->runSqlFile($file);

        // $this->db->conn_id->multi_query($file);
        //  $this->db->conn_id->close();
        if ($res['total_query']) {
            redirect('logout/db');
        }
    }

    public function runSqlFile($filename) {

        //Temporary variable, used to store current query
        $templine = '';
        // Read in entire file
        $lines = file($filename);
        $import = $s = $e = 0;
        $dbtableviews = $this->get_db_views();
        // Loop through each line
        foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || substr($line, 0, 1) == '#' || $line == '' || $line == 'utf8_general_ci;') {
                continue;
            }

            $ignore = false;
            foreach ($dbtableviews as $key => $viewName) {
                if (strstr($line, $viewName)) {
                    $ignore = true;
                    break;
                }
            }//end foreach

            if ($ignore == true) {
                continue;
            }

            if (strstr($line, 'utf8_general_ci')) {
                continue;
            }

            // Add this line to the current segment
            $templine .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {
                $pattern = "/ALGORITHM=UNDEFINED DEFINER=`.*`@`.*` SQL SECURITY DEFINER/";
                $replacement = '';
                $templine = preg_replace($pattern, $replacement, $templine);

                // Perform the query
                $r = $this->db->query($templine);
                $import++;

                if ($r) {
                    $s++;
                    $dataResponse['response'][$import] = 'Success';
                    $data['query_success'] = $s;
                } else {

                    if (strpos($posconn->error, 'Duplicate') !== false || strpos($posconn->error, 'already exists') !== false) {
                        $s++;
                        $dataResponse['response'][$import] = 'Success';
                        $data['query_success'] = $s;
                    } else {
                        $dataResponse['response'][$import] = $posconn->error;
                        $e++;
                        $data['query_failed'] = $e;
                    }
                }

                //Reset temp variable to empty
                $templine = '';
            }
        }//end foreach.

        if ($e) {
            $data['response'] = $dataResponse['response'];
        }

        $data['total_query'] = $import;

        return $data;
    }

    public function download_database($dbfile) {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        $this->load->library('zip');
        $this->zip->read_file('./files/'.$this->Customer_assets.'/backups/' . $dbfile . '.txt');
        $name = $dbfile . '.zip';
        $this->zip->download($name);
        exit();
    }

    public function download_backup($zipfile) {
        exit();
    }

    public function restore_backup($zipfile) {
        exit();
    }

    public function delete_database($dbfile) {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        unlink('./files/'.$this->Customer_assets.'/backups/' . $dbfile . '.txt');
        $this->settings_model->backups('Delete', $dbfile);
        $this->session->set_flashdata('messgae', lang('db_deleted'));
        redirect("system_settings/backups");
    }

    public function delete_backup($zipfile) {
        if (DEMO) {
            $this->session->set_flashdata('warning', lang('disabled_in_demo'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang('access_denied'));
            redirect("welcome");
        }
        unlink('./files/'.$this->Customer_assets.'/backups/' . $zipfile . '.zip');
        $this->session->set_flashdata('messgae', lang('backup_deleted'));
        redirect("system_settings/backups");
    }

    public function email_templates($template = "credentials") {

        $this->form_validation->set_rules('mail_body', lang('mail_message'), 'trim|required');
        $this->load->helper('file');
        $temp_path = is_dir('./themes/' . $this->theme . 'email_templates/');
        $theme = $temp_path ? $this->theme : 'default';
        if ($this->form_validation->run() == TRUE) {
            $data = $_POST["mail_body"];
            if (write_file('./themes/' . $this->theme . 'email_templates/' . $template . '.html', $data)) {
                $this->session->set_flashdata('message', lang('message_successfully_saved'));
                redirect('system_settings/email_templates#' . $template);
            } else {
                $this->session->set_flashdata('error', lang('failed_to_save_message'));
                redirect('system_settings/email_templates#' . $template);
            }
        } else {

            $this->data['credentials'] = file_get_contents('./themes/' . $this->theme . 'email_templates/credentials.html');
            $this->data['sale'] = file_get_contents('./themes/' . $this->theme . 'email_templates/sale.html');
            $this->data['quote'] = file_get_contents('./themes/' . $this->theme . 'email_templates/quote.html');
            $this->data['purchase'] = file_get_contents('./themes/' . $this->theme . 'email_templates/purchase.html');
            $this->data['transfer'] = file_get_contents('./themes/' . $this->theme . 'email_templates/transfer.html');
            $this->data['payment'] = file_get_contents('./themes/' . $this->theme . 'email_templates/payment.html');
            $this->data['forgot_password'] = file_get_contents('./themes/' . $this->theme . 'email_templates/forgot_password.html');
            $this->data['activate_email'] = file_get_contents('./themes/' . $this->theme . 'email_templates/activate_email.html');
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('email_templates')));
            $meta = array('page_title' => lang('email_templates'), 'bc' => $bc);
            $this->page_construct('settings/email_templates', $meta, $this->data);
        }
    }

    public function create_group() {
        $this->form_validation->set_rules('group_name', lang('group_name'), 'required|alpha_dash|is_unique[groups.name]');

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => strtolower($this->input->post('group_name')), 'description' => $this->input->post('description'));
        } elseif ($this->input->post('create_group')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/user_groups");
        }

        if ($this->form_validation->run() == TRUE && ($new_group_id = $this->settings_model->addGroup($data))) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add groups',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang('group_added'));
            redirect("system_settings/permissions/" . $new_group_id);
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['group_name'] = array('name' => 'group_name', 'id' => 'group_name', 'type' => 'text', 'class' => 'form-control', 'value' => $this->form_validation->set_value('group_name'),);
            $this->data['description'] = array('name' => 'description', 'id' => 'description', 'type' => 'text', 'class' => 'form-control', 'value' => $this->form_validation->set_value('description'),);
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/create_group', $this->data);
        }
    }

    public function edit_group($id) {

        if (!$id || empty($id)) {
            redirect('system_settings/user_groups');
        }

        $group = $this->settings_model->getGroupByID($id);

        $this->form_validation->set_rules('group_name', lang('group_name'), 'required|alpha_dash');

        if ($this->form_validation->run() === TRUE) {
            $data = array('name' => strtolower($this->input->post('group_name')), 'description' => $this->input->post('description'));
            $group_update = $this->settings_model->updateGroup($id, $data);
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit groups',
            );

            $this->sma->setUserActionLog($DataLog);
            if ($group_update) {
                $this->session->set_flashdata('message', lang('group_udpated'));
            } else {
                $this->session->set_flashdata('error', lang('attempt_failed'));
            }
            redirect("system_settings/user_groups");
        } else {

            if (empty($group)) {
                $this->session->set_flashdata('error', lang('Group not found'));
                redirect("system_settings/user_groups");
            }
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['group'] = $group;

            $this->data['group_name'] = array('name' => 'group_name', 'id' => 'group_name', 'type' => 'text', 'class' => 'form-control', 'value' => $this->form_validation->set_value('group_name', $group->name),);
            $this->data['group_description'] = array('name' => 'group_description', 'id' => 'group_description', 'type' => 'text', 'class' => 'form-control', 'value' => $this->form_validation->set_value('group_description', $group->description),);
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_group', $this->data);
        }
    }

    public function permissions($id = NULL) {
        $this->form_validation->set_rules('group', lang("group"), 'is_natural_no_zero');
        //$this->form_validation->set_rules('default-printer', 'Default Bill Print Option', 'required');

        if ($this->form_validation->run() == TRUE) {
            $data = array('products-index' => $this->input->post('products-index'),
                'products-edit' => $this->input->post('products-edit'),
                'products-add' => $this->input->post('products-add'),
                'products-delete' => $this->input->post('products-delete'),
                'products-cost' => $this->input->post('products-cost'),
                'products-price' => $this->input->post('products-price'),
                'customers-index' => $this->input->post('customers-index'),
                'customers-edit' => $this->input->post('customers-edit'),
                'customers-add' => $this->input->post('customers-add'),
                'customers-delete' => $this->input->post('customers-delete'),
                'suppliers-index' => $this->input->post('suppliers-index'),
                'suppliers-edit' => $this->input->post('suppliers-edit'),
                'suppliers-add' => $this->input->post('suppliers-add'),
                'suppliers-delete' => $this->input->post('suppliers-delete'),
                'sales-index' => $this->input->post('sales-index'),
                'sales-edit' => $this->input->post('sales-edit'),
                'sales-add' => $this->input->post('sales-add'),
                'sales-delete' => $this->input->post('sales-delete'),
                'sales-email' => $this->input->post('sales-email'),
                'sales-pdf' => $this->input->post('sales-pdf'),
                'sales-deliveries' => $this->input->post('sales-deliveries'),
                'sales-edit_delivery' => $this->input->post('sales-edit_delivery'),
                'sales-add_delivery' => $this->input->post('sales-add_delivery'),
                'sales-delete_delivery' => $this->input->post('sales-delete_delivery'),
                'sales-email_delivery' => $this->input->post('sales-email_delivery'),
                'sales-pdf_delivery' => $this->input->post('sales-pdf_delivery'),
                'sales-gift_cards' => $this->input->post('sales-gift_cards'),
                'sales-edit_gift_card' => $this->input->post('sales-edit_gift_card'),
                'sales-add_gift_card' => $this->input->post('sales-add_gift_card'),
                'sales-delete_gift_card' => $this->input->post('sales-delete_gift_card'),
                'quotes-index' => $this->input->post('quotes-index'),
                'quotes-edit' => $this->input->post('quotes-edit'),
                'quotes-add' => $this->input->post('quotes-add'),
                'quotes-delete' => $this->input->post('quotes-delete'),
                'quotes-email' => $this->input->post('quotes-email'),
                'quotes-pdf' => $this->input->post('quotes-pdf'),
                'purchases-index' => $this->input->post('purchases-index'),
                'purchases-edit' => $this->input->post('purchases-edit'),
                'purchases-add' => $this->input->post('purchases-add'),
                'purchases-delete' => $this->input->post('purchases-delete'),
                'purchases-email' => $this->input->post('purchases-email'),
                'purchases-pdf' => $this->input->post('purchases-pdf'),
                'sales-return_sales' => $this->input->post('sales-return_sales'),
                'reports-quantity_alerts' => $this->input->post('reports-quantity_alerts'),
                'reports-expiry_alerts' => $this->input->post('reports-expiry_alerts'),
                'reports-products' => $this->input->post('reports-products'),
                'reports-daily_sales' => $this->input->post('reports-daily_sales'),
                'reports-monthly_sales' => $this->input->post('reports-monthly_sales'),
                'reports-payments' => $this->input->post('reports-payments'),
                'reports-sales' => $this->input->post('reports-sales'),
                'reports-purchases' => $this->input->post('reports-purchases'),
                'reports-customers' => $this->input->post('reports-customers'),
                'reports-suppliers' => $this->input->post('reports-suppliers'),
                'sales-payments' => $this->input->post('sales-payments'),
                'purchases-payments' => $this->input->post('purchases-payments'),
                'purchases-expenses' => $this->input->post('purchases-expenses'),
                'products-adjustments' => $this->input->post('products-adjustments'),
                'Allow_Stock_Addition' =>  $this->input->post('products-ad_type_addition') == '1' ? 1 : 0,
                'Allow_Stock_Subtraction' =>  $this->input->post('products-ad_type_subtraction') == '1' ? 1 : 0,
                'products-adjustments_byCSV' =>  $this->input->post('products-adjustments_byCSV') == '1' ? 1 : 0,
                'bulk_actions' => $this->input->post('bulk_actions'),
                'customers-deposits' => $this->input->post('customers-deposits'),
                'customers-delete_deposit' => $this->input->post('customers-delete_deposit'),
                'products-barcode' => $this->input->post('products-barcode'),
                'purchases-return_purchases' => $this->input->post('purchases-return_purchases'),
                'reports-expenses' => $this->input->post('reports-expenses'),
                'reports-daily_purchases' => $this->input->post('reports-daily_purchases'),
                'reports-monthly_purchases' => $this->input->post('reports-monthly_purchases'),
                'products-stock_count' => $this->input->post('products-stock_count'),
                'edit_price' => $this->input->post('edit_price'),
                'sales-date' => $this->input->post('sales_date'),
                'sales-delete-suspended' => $this->input->post('sales_delete_suspended'),
                'purchases-date' => $this->input->post('purchases_date'),
                'quotes-date' => $this->input->post('quotes_date'),
                'products-import' => $this->input->post('products_import'),
                'printer-setting' => $this->input->post('printer_setting'),
                'cart-price_edit' => (int) $this->input->post('cart-price_edit'),
                'cart-unit_view' => (int) $this->input->post('cart-unit_view'),
                'cart-show_bill_btn' => (int) $this->input->post('cart-show_bill_btn'),
                'pos-show-order-btn' => (int) $this->input->post('pos_show_order_btn'),
                'reports-warehouse_sales_report' => $this->input->post('reports-warehouse_sales_report'),
                'report_purchase_gst' => $this->input->post('report_purchase_gst'),
                'crm_portal' => $this->input->post('crm_portal'),
                'purchase_add_csv' => $this->input->post('purchase_add_csv'),
                'sales_add_csv' => $this->input->post('sales_add_csv'),
                'all_sale_lists' => $this->input->post('all_sale_lists'),
                'offlinepos-synchronization' => $this->input->post('data_synchronization'),
                'eshop_sales-sales' => $this->input->post('eshop_sales-sales'),
                'offline-sales' => $this->input->post('offline-sales'),
                'urbanpiper_view' => $this->input->post('urbanpiper_view'),
                'urbanpiper_add' => $this->input->post('urbanpiper_add'),
                'urbanpiper_edit' => $this->input->post('urbanpiper_edit'),
                'urbanpiper_delete' => $this->input->post('urbanpiper_delete'),
                'urbanpiper_sales' => $this->input->post('urbanpiper_sales'),
                'urbanpiper_maange_order' => $this->input->post('urbanpiper_maange_order'),
                'urbanpiper_settings' => $this->input->post('urbanpiper_settings'),
                'urbanpiper_maange_stores' => $this->input->post('urbanpiper_maange_stores'),
                'urbanpiper_maange_catalogue' => $this->input->post('urbanpiper_maange_catalogue'),
                'products-batches' => $this->input->post('products-batches'),
                'products-add_batch' => $this->input->post('products-add_batch'),
                'products-edit_batch' => $this->input->post('products-edit_batch'),
                'products-delete_batch' => $this->input->post('products-delete_batch'),
                'sales-challans' => $this->input->post('sales-challans'),
                'sales-add_challans' => $this->input->post('sales-add_challans'),
                'orders-eshop_order' => $this->input->post('orders-eshop_order'),
                'orders-order_items' => $this->input->post('orders-order_items'),
                'orders-order_items_stocks' => $this->input->post('orders-order_items_stocks'),
                'transfers-date' => $this->input->post('transfers-date'),
                'transfers-index' => $this->input->post('transfers-index'),
                'transfers-edit' => $this->input->post('transfers-edit'),
                'transfers-add' => $this->input->post('transfers-add'),
                'transfers-delete' => $this->input->post('transfers-delete'),
                'transfers-email' => $this->input->post('transfers-email'),
                'transfers-pdf' => $this->input->post('transfers-pdf'),
                'transfers_add_csv' => $this->input->post('transfers_add_csv'),
                'product_remove' => $this->input->post('product_remove'),
                'transfer_status_completed' => $this->input->post('transfer_status_completed'),
                'transfer_status_request' => $this->input->post('transfer_status_request'),
                'transfer_status_sent' => $this->input->post('transfer_status_sent'),
                'transfers-request' => $this->input->post('transfers-request'),
                'transfers-add_request' => $this->input->post('transfers-add_request'),
                'transfers-edit_request' => $this->input->post('transfers-edit_request'),
                'transfers-cancel_request' => $this->input->post('transfers-cancel_request'),
                'transfers-delete_request' => $this->input->post('transfers-delete_request'),
                'transfers-request_change_status' => $this->input->post('transfers-request_change_status'),

                'reports-gst_reports'              => $this->input->post('reports-gst_reports'),
                'sales_gst_report'              => $this->input->post('sales_gst_report'),
                'purchase_gst_report'              => $this->input->post('purchase_gst_report'),

                'order-index' => $this->input->post('order-index'),
                'order-add' => $this->input->post('order-add'),
                'order-edit' => $this->input->post('order-edit'),
                'order-delete' => $this->input->post('order-delete'),
               
                'purchases-notification' =>  $this->input->post('purchases-notification'),

                'reports-payment_chart_details' =>  $this->input->post('reports-payment_chart_details'),
                'reports-sale_purchase_chart_details' =>  $this->input->post('reports-sale_purchase_chart_details'),
                'reports-categories_brand_chart_details' =>  $this->input->post('reports-categories_brand_chart_details'),
                'reports-get_customer_wise_sales' =>  $this->input->post('reports-get_customer_wise_sales'),
                'reports-products_transactions' =>  $this->input->post('reports-products_transactions'),
                'reports-products_ledgers' =>  $this->input->post('reports-products_ledgers'),
                'reports-hsncode_reports' =>  $this->input->post('reports-hsncode_reports'),
                'reports-transfer_request' =>  $this->input->post('reports-transfer_request'),
                'reports-deposit' =>  $this->input->post('reports-deposit'),
                
                'pos_clear_table'  =>   $this->input->post('pos_clear_table'),

                'checkout' => $this->input->post('checkout'), 
                     
                'bill_print' => $this->input->post('bill_print'),
                 'sales-return_invoice_days' => $this->input->post('sales-return_invoice_days'),
						  
				'Procurement_Orders' => $this->input->post('Procurement_Orders'), 
                'Order_Dispatch' => $this->input->post('Order_Dispatch'), 
                'production_manager_dashboard' => $this->input->post('production_manager_dashboard'), 
                // New Report
                'reports-adjustments-reports' => $this->input->post('adjustments-reports'), // returns '1' if checked, NULL if unchecked
                'reports-best-seller' => $this->input->post('best-seller'),
                'reports-brand-reports' => $this->input->post('brand-reports'),
                'reports-categories-reports' => $this->input->post('categories-reports'),
                'reports-challan-reports' => $this->input->post('challan-reports'),
                'reports-customer-ledgers' => $this->input->post('customer-ledgers'),
                'reports-deposit-ledgers' => $this->input->post('deposit-ledgers'),
                'reports-deposit-recharge-ledgers' => $this->input->post('deposit-recharge-ledgers'),
                'reports-due-purchase-reports' => $this->input->post('due-purchase-reports'),
                'reports-due-sales-reports' => $this->input->post('due-sales-reports'),
                'reports-overview-chart' => $this->input->post('overview-chart'),
                'reports-payments-summary' => $this->input->post('payments-summary'),
                'reports-product-varient-purchase-report' => $this->input->post('product-varient-purchase-report'),
                'reports-product-varient-stock-report' => $this->input->post('product-varient-stock-report'),
                'reports-product-combo-item' => $this->input->post('product-combo-item'),
                'reports-product-costing' => $this->input->post('product-costing'),
                'reports-product-profit-loss-report' => $this->input->post('product-profit-loss-report'),
                'reports-product-wise-sale-report' => $this->input->post('product-wise-sale-report'),
                'reports-profit-loss-report' => $this->input->post('profit-loss-report'),
                'reports-register-report' => $this->input->post('register-report'),
                'reports-sales-extended-report' => $this->input->post('sales-extended-report'),
                'reports-sales-person-report' => $this->input->post('sales-person-report'),
                'reports-staff-report' => $this->input->post('staff-report'),
                'reports-tax-report' => $this->input->post('tax-report'),
                'reports-term-wise-sale-report' => $this->input->post('term-wise-sale-report'),
                'reports-transfer-report' => $this->input->post('transfer-report'),
                'reports-user-log-report' => $this->input->post('user-log-report'),
                'reports-warehouse-stock-report' => $this->input->post('warehouse-stock-report'),

                //POS Screen 
                'pos-category_search' => $this->input->post('pos-category_search'),
                'pos-discription' => $this->input->post('pos-discription'),
                'pos-apply_coupon' => $this->input->post('pos-apply_coupon')
                
            );
            // echo "<pre>";
            // print_r($data['products-index']);
            // exit;
            if (POS) {
                $data['pos-index'] = $this->input->post('pos-index');
            }
            // $this->sma->print_arrays($data);
        }
        if ($this->form_validation->run() == TRUE && $this->settings_model->updatePermissions($id, $data)) {
            $this->session->set_flashdata('message', lang("group_permissions_updated"));
            redirect("system_settings/user_groups");
            // redirect($_SERVER["HTTP_REFERER"]);
        } else {

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

            $this->data['id'] = $id;
            $this->data['p'] = $this->settings_model->getGroupPermissions($id);
            $this->data['group'] = $this->settings_model->getGroupByID($id);
            $this->data['printers'] = $this->site->getAllPrinter();

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('group_permissions')));
            $meta = array('page_title' => lang('group_permissions'), 'bc' => $bc);
            $this->page_construct('settings/permissions', $meta, $this->data);
        }
    }

    public function user_groups() {

        if (!$this->Owner) {
            $this->session->set_flashdata('error', lang("access_denied"));
            redirect('auth');
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');

        $this->data['groups'] = $this->settings_model->getGroups();
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('groups')));
        $meta = array('page_title' => lang('groups'), 'bc' => $bc);
        $this->page_construct('settings/user_groups', $meta, $this->data);
    }

    public function delete_group($id = NULL) {
        if (!$this->Owner) {
            $this->session->set_flashdata('warning', lang("access_denied"));
            redirect('welcome', 'refresh');
        }

        if ($this->settings_model->checkGroupUsers($id)) {
            $this->session->set_flashdata('error', lang("group_x_b_deleted"));
            redirect("system_settings/user_groups");
        }
        $group = $this->settings_model->getGroupByID($id);
        if ($this->settings_model->deleteGroup($id)) {
            $DataLog = array(
                'action_type' => 'Delete',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($group),
                'action_comment' => 'Delete groups',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("group_deleted"));
            redirect("system_settings/user_groups");
        }
    }

    public function currencies() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('currencies')));
        $meta = array('page_title' => lang('currencies'), 'bc' => $bc);
        $this->page_construct('settings/currencies', $meta, $this->data);
    }

    public function getCurrencies() {

        $this->load->library('datatables');
        $this->datatables->select("id, code, name, rate")->from("currencies")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_currency/$1') . "' class='tip' title='" . lang("edit_currency") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_currency") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_currency/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function add_currency() {

        $this->form_validation->set_rules('code', lang("currency_code"), 'trim|is_unique[currencies.code]|required');
        $this->form_validation->set_rules('name', lang("name"), 'required');
        $this->form_validation->set_rules('rate', lang("exchange_rate"), 'required|numeric');

        if ($this->form_validation->run() == TRUE) {
            $data = array('code' => $this->input->post('code'), 'name' => $this->input->post('name'), 'rate' => $this->input->post('rate'),);
        } elseif ($this->input->post('add_currency')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/currencies");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addCurrency($data)) { //check to see if we are creating the customer
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add currencies',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("currency_added"));
            redirect("system_settings/currencies");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['page_title'] = lang("new_currency");
            $this->load->view($this->theme . 'settings/add_currency', $this->data);
        }
    }

    public function edit_currency($id = NULL) {

        $this->form_validation->set_rules('code', lang("currency_code"), 'trim|required');
        $cur_details = $this->settings_model->getCurrencyByID($id);
        if (empty($cur_details)) {
            $this->session->set_flashdata('error', lang('Currency not found'));
            redirect("system_settings/currencies");
        }
        if ($this->input->post('code') != $cur_details->code) {
            $this->form_validation->set_rules('code', lang("currency_code"), 'is_unique[currencies.code]');
        }
        $this->form_validation->set_rules('name', lang("currency_name"), 'required');
        $this->form_validation->set_rules('rate', lang("exchange_rate"), 'required|numeric');

        if ($this->form_validation->run() == TRUE) {

            $data = array('code' => $this->input->post('code'), 'name' => $this->input->post('name'), 'rate' => $this->input->post('rate'),);
        } elseif ($this->input->post('edit_currency')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/currencies");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateCurrency($id, $data)) { //check to see if we are updateing the customer
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit currencies',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("currency_updated"));
            redirect("system_settings/currencies");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['currency'] = $this->settings_model->getCurrencyByID($id);
            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_currency', $this->data);
        }
    }

    public function delete_currency($id = NULL) {
        $cur_details = $this->settings_model->getCurrencyByID($id);
        $DataLog = array(
            'action_type' => 'Delete',
            'product_id' => '',
            'quantity' => '',
            'action_reff_id' => $id,
            'action_affected_data' => json_encode($cur_details),
            'action_comment' => 'Delete currencies',
        );

        $this->sma->setUserActionLog($DataLog);
        if ($this->settings_model->deleteCurrency($id)) {
            echo lang("currency_deleted");
        }
    }

    public function currency_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $cur_details = $this->settings_model->getCurrencyByID($id);
                        $DataLog = array(
                            'action_type' => 'Delete',
                            'product_id' => '',
                            'quantity' => '',
                            'action_reff_id' => $id,
                            'action_affected_data' => json_encode($cur_details),
                            'action_comment' => 'Delete currencies',
                        );

                        $this->sma->setUserActionLog($DataLog);
                        $this->settings_model->deleteCurrency($id);
                    }
                    $this->session->set_flashdata('message', lang("currencies_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $this->excel->getActiveSheet()->setTitle(lang('currencies'));
                    $this->excel->getActiveSheet()->SetCellValue('A1', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('B1', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('C1', lang('rate'));

                    $row = 2;
                    foreach ($_POST['val'] as $id) {
                        $sc = $this->settings_model->getCurrencyByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sc->code);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $sc->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $sc->rate);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'currencies_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function categories() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('categories')));
        $meta = array('page_title' => lang('categories'), 'bc' => $bc);
        $this->page_construct('settings/categories', $meta, $this->data);
    }

    public function getCategories() {

        $print_barcode = anchor('products/print_barcodes/?category=$1', '<i class="fa fa-print"></i>', 'title="' . lang('print_barcodes') . '" class="tip"');

        $this->load->library('datatables');
        $this->datatables->select("{$this->db->dbprefix('categories')}.id as id, 
        {$this->db->dbprefix('categories')}.image, 
        {$this->db->dbprefix('categories')}.code, 
        {$this->db->dbprefix('categories')}.name, c.name as parent, t.name as tax_rate ,
        {$this->db->dbprefix('categories')}.rank as Rank,
        {$this->db->dbprefix('categories')}.flag_visible as flag_visible", FALSE)
                ->from("categories")
                ->join("categories c", 'c.id=categories.parent_id', 'left')
                ->join("tax_rates t", 't.id=categories.tax_rate', 'left')
                ->group_by('categories.id')
                ->add_column("Actions", "<div class=\"text-center\"> <a href='" . site_url('system_settings/orderingItemByCategories/$1') . "' data-toggle='modal' data-target='#myModal' class='tip' title='" . lang("Set Rank") . "'><i class=\"fa fa-list-ul\"></i></a> " ."  $print_barcode <a href='" . site_url('system_settings/edit_category/$1') . "' data-toggle='modal' data-target='#myModal' class='tip' title='" . lang("edit_category") . "'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_category") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete-sure' href='" . site_url('system_settings/delete_category/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");

        echo $this->datatables->generate();
    }

    function add_category() {

        $this->load->helper('security');
        $this->form_validation->set_rules('code', lang("category_code"), 'trim|is_unique[categories.code]|required');
        $this->form_validation->set_rules('name', lang("name"), 'required|min_length[3]');
        $this->form_validation->set_rules('userfile', lang("category_image"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {
            
           if($this->input->post('fix_tax_rate')=='variable tax'){
                $taxslabs = array();
                foreach($this->input->post('price') as $key => $price){
                    if($price){
                        $taxslabs[]=[
                            'price'=>$price,
                            'condition'=>$this->input->post('condition')[$key],
                            'taxratevalue'=>$this->input->post('taxratevalue')[$key],
                            'upto' => $this->input->post('upto')[$key]
                        ];
                    }
                }
                $taxdata = serialize($taxslabs);
            }
   
            $data = array(
                'name' => $this->input->post('name'),
                'code' => $this->input->post('code'),
                'parent_id' => $this->input->post('parent'),
                'tax_rate' => $this->input->post('tax_rate'),
                'warehouse_id' =>  ($this->input->post('warehouse') == '') ? "*All" : implode(",",$this->input->post('warehouse')),
                'fix_tax_rate' => $this->input->post('fix_tax_rate'),
                'tax_slabs' => isset($taxdata)? $taxdata : NULL,
            );

            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['image'] = $photo;
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = $this->Settings->twidth;
                $config['height'] = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                if ($this->Settings->watermark) {
                    $this->image_lib->clear();
                    $wm['source_image'] = $this->upload_path . $photo;
                    $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                    $wm['wm_type'] = 'text';
                    $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                    $wm['quality'] = '100';
                    $wm['wm_font_size'] = '16';
                    $wm['wm_font_color'] = '999999';
                    $wm['wm_shadow_color'] = 'CCCCCC';
                    $wm['wm_vrt_alignment'] = 'top';
                    $wm['wm_hor_alignment'] = 'right';
                    $wm['wm_padding'] = '10';
                    $this->image_lib->initialize($wm);
                    $this->image_lib->watermark();
                }
                $this->image_lib->clear();
                $config = NULL;
            }
        } elseif ($this->input->post('add_category')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/categories");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addCategory($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add categories',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("category_added"));
            redirect("system_settings/categories");
        } else {

            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['categories'] = $this->settings_model->getParentCategories();
            $this->data['tax_rates'] = $this->settings_model->getAllTaxRates();
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_category', $this->data);
        }
    }

    // public function edit_category($id = NULL) {
    //     $this->load->helper('security');
    //     $this->form_validation->set_rules('code', lang("category_code"), 'trim|required');
    //     $pr_details = $this->settings_model->getCategoryByID($id);
    //     if (empty($pr_details)) {
    //         $this->session->set_flashdata('error', lang('Category not found'));
    //         redirect("system_settings/categories");
    //     }
    //     if ($this->input->post('code') != $pr_details->code) {
    //         $this->form_validation->set_rules('code', lang("category_code"), 'is_unique[categories.code]');
    //     }
    //     $this->form_validation->set_rules('name', lang("category_name"), 'required|min_length[3]');
    //     $this->form_validation->set_rules('userfile', lang("category_image"), 'xss_clean');

    //     if ($this->form_validation->run() == TRUE) {

    //         $data = array(
    //             'name' => $this->input->post('name'),
    //             'code' => $this->input->post('code'),
    //             'parent_id' => $this->input->post('parent'),
    //             'tax_rate' => $this->input->post('tax_rate'),
    //         );

    //         if ($_FILES['userfile']['size'] > 0) {
    //             $this->load->library('upload');
    //             $config['upload_path'] = $this->upload_path;
    //             $config['allowed_types'] = $this->image_types;
    //             $config['max_size'] = $this->allowed_file_size;
    //             $config['max_width'] = $this->Settings->iwidth;
    //             $config['max_height'] = $this->Settings->iheight;
    //             $config['overwrite'] = FALSE;
    //             $config['encrypt_name'] = TRUE;
    //             $config['max_filename'] = 25;
    //             $this->upload->initialize($config);
    //             if (!$this->upload->do_upload()) {
    //                 $error = $this->upload->display_errors();
    //                 $this->session->set_flashdata('error', $error);
    //                 redirect($_SERVER["HTTP_REFERER"]);
    //             }
    //             $photo = $this->upload->file_name;
    //             $data['image'] = $photo;
    //             $this->load->library('image_lib');
    //             $config['image_library'] = 'gd2';
    //             $config['source_image'] = $this->upload_path . $photo;
    //             $config['new_image'] = $this->thumbs_path . $photo;
    //             $config['maintain_ratio'] = TRUE;
    //             $config['width'] = $this->Settings->twidth;
    //             $config['height'] = $this->Settings->theight;
    //             $this->image_lib->clear();
    //             $this->image_lib->initialize($config);
    //             if (!$this->image_lib->resize()) {
    //                 echo $this->image_lib->display_errors();
    //             }
    //             if ($this->Settings->watermark) {
    //                 $this->image_lib->clear();
    //                 $wm['source_image'] = $this->upload_path . $photo;
    //                 $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
    //                 $wm['wm_type'] = 'text';
    //                 $wm['wm_font_path'] = 'system/fonts/texb.ttf';
    //                 $wm['quality'] = '100';
    //                 $wm['wm_font_size'] = '16';
    //                 $wm['wm_font_color'] = '999999';
    //                 $wm['wm_shadow_color'] = 'CCCCCC';
    //                 $wm['wm_vrt_alignment'] = 'top';
    //                 $wm['wm_hor_alignment'] = 'right';
    //                 $wm['wm_padding'] = '10';
    //                 $this->image_lib->initialize($wm);
    //                 $this->image_lib->watermark();
    //             }
    //             $this->image_lib->clear();
    //             $config = NULL;
    //         }
    //     } elseif ($this->input->post('edit_category')) {
    //         $this->session->set_flashdata('error', validation_errors());
    //         redirect("system_settings/categories");
    //     }

    //     if ($this->form_validation->run() == TRUE && $this->settings_model->updateCategory($id, $data)) {
    //         if ($this->input->post('apply_tax_to_all_product')) {
    //             $dataCat['tax_rate'] = $this->input->post('tax_rate');
    //             if ($this->input->post('parent') == '') {

    //                 $this->settings_model->updateProductTax1($id, $this->input->post('tax_rate'));
    //             } else {

    //                 $this->settings_model->updateProductTax('subcategory_id', $id, $dataCat);
    //             }
    //         }
    //         $DataLogArray = array('categories' => $data, 'Tax' => $TaxArray);
    //         $DataLog = array(
    //             'action_type' => 'Edit',
    //             'product_id' => '',
    //             'quantity' => '',
    //             'action_reff_id' => $id,
    //             'action_affected_data' => json_encode($DataLogArray),
    //             'action_comment' => 'Edit categories',
    //         );

    //         $this->sma->setUserActionLog($DataLog);
    //         $this->session->set_flashdata('message', lang("category_updated"));
    //         redirect("system_settings/categories");
    //     } else {

    //         $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
    //         $this->data['category'] = $this->settings_model->getCategoryByID($id);
    //         $this->data['categories'] = $this->settings_model->getParentCategories();
    //         $this->data['tax_rates'] = $this->settings_model->getAllTaxRates();
    //         $this->data['modal_js'] = $this->site->modal_js();
    //         $this->load->view($this->theme . 'settings/edit_category', $this->data);
    //     }
    // }

    public function edit_category($id = NULL) {
        $this->load->helper('security');
        $this->form_validation->set_rules('code', lang("category_code"), 'trim|required');
        $pr_details = $this->settings_model->getCategoryByID($id);
        if (empty($pr_details)) {
            $this->session->set_flashdata('error', lang('Category not found'));
            redirect("system_settings/categories");
        }
        if ($this->input->post('code') != $pr_details->code) {
            $this->form_validation->set_rules('code', lang("category_code"), 'is_unique[categories.code]');
        }
        $this->form_validation->set_rules('name', lang("category_name"), 'required|min_length[3]');
        $this->form_validation->set_rules('userfile', lang("category_image"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {
            if($this->input->post('fix_tax_rate')=='variable tax'){
                $taxslabs = array();
                foreach($this->input->post('price') as $key => $price){
                    if($price){
                        $taxslabs[]=[
                            'price'=>$price,
                            'condition'=>$this->input->post('condition')[$key],
                            'taxratevalue'=>$this->input->post('taxratevalue')[$key],
                            'upto' => $this->input->post('upto')[$key]
                        ];
                    }
                }
                $taxdata = serialize($taxslabs);
            }

            $data = array(
                'name' => $this->input->post('name'),
                'code' => $this->input->post('code'),
                'parent_id' => $this->input->post('parent'),
                'tax_rate' => $this->input->post('tax_rate'),
                'fix_tax_rate' => $this->input->post('fix_tax_rate'),
                'tax_slabs' => isset($taxdata)? $taxdata : NULL,
                'warehouse_id' => implode(",",$this->input->post('warehouse')),

                );

            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['image'] = $photo;
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = $this->Settings->twidth;
                $config['height'] = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                if ($this->Settings->watermark) {
                    $this->image_lib->clear();
                    $wm['source_image'] = $this->upload_path . $photo;
                    $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                    $wm['wm_type'] = 'text';
                    $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                    $wm['quality'] = '100';
                    $wm['wm_font_size'] = '16';
                    $wm['wm_font_color'] = '999999';
                    $wm['wm_shadow_color'] = 'CCCCCC';
                    $wm['wm_vrt_alignment'] = 'top';
                    $wm['wm_hor_alignment'] = 'right';
                    $wm['wm_padding'] = '10';
                    $this->image_lib->initialize($wm);
                    $this->image_lib->watermark();
                }
                $this->image_lib->clear();
                $config = NULL;
            }
        } elseif ($this->input->post('edit_category')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/categories");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateCategory($id, $data)) {
            if ($this->input->post('apply_tax_to_all_product')) {
                $dataCat['tax_rate'] = $this->input->post('tax_rate');
                if ($this->input->post('parent') == '') {

                    $this->settings_model->updateProductTax1($id, $this->input->post('tax_rate'));
                } else {

                    $this->settings_model->updateProductTax('subcategory_id', $id, $dataCat);
                }
            }
            $DataLogArray = array('categories' => $data, 'Tax' => $TaxArray);
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($DataLogArray),
                'action_comment' => 'Edit categories',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("category_updated"));
            redirect("system_settings/categories");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['category'] = $this->settings_model->getCategoryByID($id);
            $this->data['categories'] = $this->settings_model->getParentCategories();
            $this->data['tax_rates'] = $this->settings_model->getAllTaxRates();
            $this->data['modal_js'] = $this->site->modal_js();  
            $this->data['warehous'] =  $id = $this->site->warehouseById($id);
            $this->data['warehouses'] = $this->site->getAllWarehouses();

           
            $this->load->view($this->theme . 'settings/edit_category', $this->data);
        }
    }

    public function delete_category($id = NULL) {

        if ($this->site->getSubCategories($id)) {
            $this->session->set_flashdata('error', lang("category_has_subcategory"));
            redirect("system_settings/categories");
        }

        if ($this->site->getPrdCategories($id)) {
            $this->session->set_flashdata('error', "Category is assign to a product");
            redirect("system_settings/categories");
        }

        if ($this->site->getPrdSubCategories($id)) {
            $this->session->set_flashdata('error', "Subcategory is assign to a product");
            redirect("system_settings/categories");
        }

        $this->sma->storeDeletedData('categories', 'id', $id);
        if ($this->settings_model->deleteCategory($id)) {
            $this->session->set_flashdata('message', lang("category_deleted"));
            redirect("system_settings/categories");
        }
    }

    public function category_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');
        $values = explode(",", $_POST['Checkbox'][0]);
        $_POST['Checkbox'] = array_map('intval', $values);
        if ($_POST['Checkbox'][0] < 1) {
           $_POST['Checkbox'] = '';
       }
        if ($this->form_validation->run() == TRUE) {
            if ($this->input->post('form_action') == 'submitButton') {
                $this->session->set_flashdata('message', lang("Rank Update Successfully"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
          
            if (!empty($_POST['Checkbox'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['Checkbox'] as $id) {
                        $this->sma->storeDeletedData('categories', 'id', $id);
                        $this->settings_model->deleteCategory($id);
                    }
                    $this->session->set_flashdata('message', lang("categories_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:E1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:E1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Categories');
                    $this->excel->getActiveSheet()->setTitle(lang('categories'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('image'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('parent_category'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('tax_rate'));

                    $row = 3;
                    foreach ($_POST['Checkbox'] as $id) {
                        $sc = $this->settings_model->getCategoryByID($id);
                        $parent_actegory = '';
                        if ($sc->parent_id) {
                            $pc = $this->settings_model->getCategoryByID($sc->parent_id);
                            $parent_actegory = $pc->code;
                        }
                        if ($sc->tax_rate) {
                            $tax = $this->settings_model->getTaxRateByID($sc->tax_rate);
                            $tax_rate = $tax->rate . '%';
                        }
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sc->code);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $sc->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $sc->image);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $parent_actegory);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $tax_rate);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'categories_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function tax_rates() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('tax_rates')));
        $meta = array('page_title' => lang('tax_rates'), 'bc' => $bc);
        $this->page_construct('settings/tax_rates', $meta, $this->data);
    }

    public function getTaxRates() {

        $this->load->library('datatables');
        $this->datatables->select("id, name, code, rate, type")->from("tax_rates")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_tax_rate/$1') . "' class='tip' title='" . lang("edit_tax_rate") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_tax_rate") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete23' href='" . site_url('system_settings/delete_tax_rate/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function add_tax_rate() {

        $this->form_validation->set_rules('name', lang("name"), 'trim|is_unique[tax_rates.name]|required');
        $this->form_validation->set_rules('type', lang("type"), 'required');
        $this->form_validation->set_rules('rate', lang("tax_rate"), 'required|numeric');

        if ($this->form_validation->run() == TRUE) {
            $_rate = (float) $this->input->post('rate');
            $tax_attr_str = $this->input->post('tax_attr_str');
            $tax_config = array();
            $_rate_config = 0;
            if (!empty($tax_attr_str)):
                $tax_attr_Arr = explode(',', $tax_attr_str);
                foreach ($tax_attr_Arr as $attrId) {
                    $per = $this->input->post('tax_attr_' . $attrId);

                    if ($per !== ''):
                        $per = (float) $per;
                        $_rate_config = $_rate_config + $per;
                        $_config_attr = $this->settings_model->getTaxAttrByID($attrId);
                        is_object($_config_attr) ? $_config_attr->percentage = $per : '';
                        $tax_config[$attrId] = (array) $_config_attr;

                    endif;
                }
            endif;

            if ($_rate > 0 && $_rate_config > 0 && $_rate != $_rate_config):
                $this->session->set_flashdata('error', 'taxt configuration is not correct');
                redirect("system_settings/tax_rates");
            endif;

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'), 'type' => $this->input->post('type'), 'rate' => $this->input->post('rate'), 'tax_config' => serialize($tax_config),);
        }elseif ($this->input->post('add_tax_rate')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/tax_rates");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addTaxRate($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add tax_rates',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("tax_rate_added"));
            redirect("system_settings/tax_rates");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['tax_js'] = $this->site->tax_rate_js();
            $this->load->view($this->theme . 'settings/add_tax_rate', $this->data);
        }
    }

    public function edit_tax_rate($id = NULL) {

        $this->form_validation->set_rules('name', lang("name"), 'trim|required');
        $tax_details = $this->settings_model->getTaxRateByID($id);
        if (empty($tax_details)) {
            $this->session->set_flashdata('error', lang('Tax Rate not found'));
            redirect("system_settings/tax_rates");
        }
        if ($this->input->post('name') != $tax_details->name) {
            $this->form_validation->set_rules('name', lang("name"), 'is_unique[tax_rates.name]');
        }
        $this->form_validation->set_rules('type', lang("type"), 'required');
        $this->form_validation->set_rules('rate', lang("tax_rate"), 'required|numeric');

        if ($this->form_validation->run() == TRUE) {
            $_rate = (float) $this->input->post('rate');
            $tax_attr_str = $this->input->post('tax_attr_str');
            $tax_config = array();
            $_rate_config = 0;
            if (!empty($tax_attr_str)):
                $tax_attr_Arr = explode(',', $tax_attr_str);
                foreach ($tax_attr_Arr as $attrId) {
                    $per = $this->input->post('tax_attr_' . $attrId);

                    if ($per !== ''):
                        $per = (float) $per;
                        $_rate_config = $_rate_config + $per;
                        $_config_attr = $this->settings_model->getTaxAttrByID($attrId);
                        is_object($_config_attr) ? $_config_attr->percentage = $per : '';
                        $tax_config[$attrId] = (array) $_config_attr;

                    endif;
                }
            endif;

            if ($_rate > 0 && $_rate_config > 0 && $_rate != $_rate_config):
                $this->session->set_flashdata('error', 'taxt configuration is not correct');
                redirect("system_settings/tax_rates");
            endif;

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'), 'type' => $this->input->post('type'), 'rate' => $this->input->post('rate'), 'tax_config' => serialize($tax_config),);
        }elseif ($this->input->post('edit_tax_rate')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/tax_rates");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateTaxRate($id, $data)) { //check to see if we are updateing the customer
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit tax_rates',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("tax_rate_updated"));
            redirect("system_settings/tax_rates");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['tax_rate'] = $this->settings_model->getTaxRateByID($id);

            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['tax_js'] = $this->site->tax_rate_js();
            $this->load->view($this->theme . 'settings/edit_tax_rate', $this->data);
        }
    }

    public function delete_tax_rate($id = NULL) {
        $tax_details = $this->settings_model->getTaxRateByID($id);
        if ($this->settings_model->getTaxRateByIDPrd($id)) {
            $this->session->set_flashdata('error', lang("Tax is assign to a product"));
            redirect('system_settings/tax_rates');
        } elseif ($this->settings_model->deleteTaxRate($id)) {
            $DataLog = array(
                'action_type' => 'Delete',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($tax_details),
                'action_comment' => 'Delete tax_rates',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("tax_rate_deleted"));
            redirect('system_settings/tax_rates');
        }
    }

    public function tax_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $tax_details = $this->settings_model->getTaxRateByID($id);
                        $DataLog = array(
                            'action_type' => 'Delete',
                            'product_id' => '',
                            'quantity' => '',
                            'action_reff_id' => $id,
                            'action_affected_data' => json_encode($tax_details),
                            'action_comment' => 'Delete tax_rates',
                        );

                        $this->sma->setUserActionLog($DataLog);
                        $this->settings_model->deleteTaxRate($id);
                    }
                    $this->session->set_flashdata('message', lang("tax_rates_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:D1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:D1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Tax Rates');
                    $this->excel->getActiveSheet()->setTitle(lang('tax_rates'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('tax_rate'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('type'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $tax = $this->settings_model->getTaxRateByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $tax->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $tax->code);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $tax->rate);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, ($tax->type == 1) ? lang('percentage') : lang('fixed'));
                        $row++;
                    }
                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'tax_rates_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function customer_groups() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('customer_groups')));
        $meta = array('page_title' => lang('customer_groups'), 'bc' => $bc);
        $this->page_construct('settings/customer_groups', $meta, $this->data);
    }

    public function getCustomerGroups() {

        $this->load->library('datatables');
        $this->datatables->select("id, name, percent")->from("customer_groups")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_customer_group/$1') . "' class='tip' title='" . lang("edit_customer_group") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_customer_group") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_customer_group/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function add_customer_group() {

        $this->form_validation->set_rules('name', lang("group_name"), 'trim|is_unique[customer_groups.name]|required');
        $this->form_validation->set_rules('percent', lang("group_percentage"), 'required|numeric');

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'), 'percent' => $this->input->post('percent'),
             'apply_as_discount'=> (($this->input->post('apply_as_discount'))?$this->input->post('apply_as_discount'):NULL)
           );
        } elseif ($this->input->post('add_customer_group')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/customer_groups");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addCustomerGroup($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add customer_groups',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("customer_group_added"));
            redirect("system_settings/customer_groups");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_customer_group', $this->data);
        }
    }

    public function edit_customer_group($id = NULL) {

        $this->form_validation->set_rules('name', lang("group_name"), 'trim|required');
        $pg_details = $this->settings_model->getCustomerGroupByID($id);
        if (empty($pg_details)) {
            $this->session->set_flashdata('error', lang('Customer Group not found'));
            redirect("system_settings/customer_groups");
        }
        if ($this->input->post('name') != $pg_details->name) {
            $this->form_validation->set_rules('name', lang("group_name"), 'is_unique[tax_rates.name]');
        }
        $this->form_validation->set_rules('percent', lang("group_percentage"), 'required|numeric');

        if ($this->form_validation->run() == TRUE) {

            $data = array('name' => $this->input->post('name'), 'percent' => $this->input->post('percent'),
              'apply_as_discount'=> (($this->input->post('apply_as_discount'))?$this->input->post('apply_as_discount'):NULL)
            );
        } elseif ($this->input->post('edit_customer_group')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/customer_groups");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateCustomerGroup($id, $data)) {
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit customer_groups',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("customer_group_updated"));
            redirect("system_settings/customer_groups");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['customer_group'] = $this->settings_model->getCustomerGroupByID($id);

            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_customer_group', $this->data);
        }
    }

    public function delete_customer_group($id = NULL) {
        $this->sma->storeDeletedData('customer_groups', 'id', $id);
        if ($this->settings_model->deleteCustomerGroup($id)) {
            echo lang("customer_group_deleted");
        }
    }

    public function customer_group_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('customer_groups', 'id', $id);
                        $this->settings_model->deleteCustomerGroup($id);
                    }
                    $this->session->set_flashdata('message', lang("customer_groups_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $this->excel->getActiveSheet()->setTitle(lang('tax_rates'));
                    $this->excel->getActiveSheet()->SetCellValue('A1', lang('group_name'));
                    $this->excel->getActiveSheet()->SetCellValue('B1', lang('group_percentage'));
                    $row = 2;
                    foreach ($_POST['val'] as $id) {
                        $pg = $this->settings_model->getCustomerGroupByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $pg->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $pg->percent);
                        $row++;
                    }
                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'customer_groups_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_customer_group_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function warehouses() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('warehouses')));
        $meta = array('page_title' => lang('warehouses'), 'bc' => $bc);
        $this->page_construct('settings/warehouses', $meta, $this->data);
    }

    public function getWarehouses() {

        $this->load->library('datatables');
        $this->datatables->select("{$this->db->dbprefix('warehouses')}.id as id, map, code, {$this->db->dbprefix('warehouses')}.name as name, {$this->db->dbprefix('price_groups')}.name as price_group, phone, email, address,is_active")
                ->from("warehouses")
                ->join('price_groups', 'price_groups.id=warehouses.price_group_id', 'left')
                ->where(["warehouses.is_deleted" => 0, "warehouses.is_disabled" => 0])
                ->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_warehouse/$1') . "' class='tip' title='" . lang("edit_warehouse") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_warehouse") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_warehouse/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id,is_active,is_deleted");

        echo $this->datatables->generate();
    }

    public function add_warehouse() {
        $this->load->helper('security');
        $this->form_validation->set_rules('code', lang("code"), 'trim|is_unique[warehouses.code]|required');
        $this->form_validation->set_rules('name', lang("name"), 'required');
        $this->form_validation->set_rules('city', lang("city"), 'required');
        $this->form_validation->set_rules('state', lang("state"), 'required');
        $this->form_validation->set_rules('postal_code', lang("postal_code"), 'required');
        // $this->form_validation->set_rules('address', lang("address"), 'required');
        $this->form_validation->set_rules('userfile', lang("map_image"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {
            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = 'assets/mdata/' . $this->Customer_assets . '/uploads/';
                $config['allowed_types'] = 'gif|jpg|png|jpeg';
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = '2000';
                $config['max_height'] = '2000';
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('message', $error);
                    redirect("system_settings/warehouses");
                }
    
                $map = $this->upload->file_name;
    
                $this->load->helper('file');
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = 'assets/mdata/' . $this->Customer_assets . '/uploads/' . $map;
                $config['new_image'] = 'assets/mdata/' . $this->Customer_assets . '/uploads/thumbs/' . $map;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = 76;
                $config['height'] = 76;
    
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
    
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
            } else {
                $map = NULL;
            }

            $stateInfo = explode('~', $this->input->post('state'));
            $name = $this->input->post('name');
            $city = $this->input->post('city');
            $country = $this->input->post('country');
            $postal_code = $this->input->post('postal_code');

            $address = !empty($this->input->post('address')) ? $this->input->post('address') : "$name, $city $stateInfo[1] $country $postal_code ";
    
            $retail_production = $this->input->post('retail_production') == 'yes' ? 1 : 0;
            $batch_production = $this->input->post('batch_production') == 'yes' ? 1 : 0;
    
            $data = [
                'code' => $this->input->post('code'),
                'name' => $this->input->post('name'),
                'phone' => $this->input->post('phone'),
                'email' => $this->input->post('email'),
                'city' => $this->input->post('city'),
                'postal_code' => $this->input->post('postal_code'),
                'country' => $this->input->post('country'),
                'state' => $stateInfo[0],
                'state_code' => $stateInfo[1],
                'address' => $address,
                'price_group_id' => $this->input->post('price_group'),
                'location_type' => $this->input->post('location_type'),
                'owned_by' => $this->input->post('owned_by'),
                'map' => $map,
                'retail_production' => $retail_production,
                'batch_production' => $batch_production,
                'contact_person_name' => $this->input->post('contact_person_name'),
                'address_line1' => $this->input->post('address_line1'),
                'address_line2' => $this->input->post('address_line2'),
            ];
        } elseif ($this->input->post('add_warehouse')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/warehouses");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addWarehouse($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add warehouses',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("warehouse_added"));
            redirect("system_settings/warehouses");
           
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['price_groups'] = $this->settings_model->getAllPriceGroups();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['location_types'] = $this->settings_model->getAllLocationTypes();
            $this->data['owned_by'] = $this->settings_model->getAllOwned_by();
            $this->load->view($this->theme . 'settings/add_warehouse', $this->data);
        }
    }

    public function edit_warehouse($id = NULL) {
        $this->load->helper('security');
        $this->form_validation->set_rules('code', lang("code"), 'trim|required');
        $wh_details = $this->settings_model->getWarehouseByID($id);
        if (empty($wh_details)) {
            $this->session->set_flashdata('error', lang('Warehouse not found'));
            redirect("system_settings/warehouses");
        }
        if ($this->input->post('code') != $wh_details->code) {
            $this->form_validation->set_rules('code', lang("code"), 'is_unique[warehouses.code]');
        }
        $this->form_validation->set_rules('city', lang("city"), 'required');
        $this->form_validation->set_rules('state', lang("state"), 'required');
        $this->form_validation->set_rules('postal_code', lang("postal_code"), 'required');

        $this->form_validation->set_rules('address', lang("address"), 'required');
        $this->form_validation->set_rules('map', lang("map_image"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {
            $retail_production = $this->input->post('retail_production') == 'yes' ? 1 : 0;
            $batch_production = $this->input->post('batch_production') == 'yes' ? 1 : 0;
            $in_eshop = $this->input->post('in_eshop');
            $eshop_biller_id = $this->input->post('eshop_biller_id');
            $stateInfo = explode('~', $this->input->post('state'));

            $data = [
                'code' => $this->input->post('code'),
                'name' => $this->input->post('name'),
                'phone' => $this->input->post('phone'),
                'email' => $this->input->post('email'),
                'address' => $this->input->post('address'),
                'city' => $this->input->post('city'),
                'country' => $this->input->post('country'),
                'state' => $stateInfo[0],
                'state_code' => $stateInfo[1],
                'postal_code' => $this->input->post('postal_code'),
                'price_group_id' => $this->input->post('price_group'),
                'location_type' => $this->input->post('location_type'),
                'owned_by' => $this->input->post('owned_by'),
                'contact_person_name' => $this->input->post('contact_person_name'),
                'retail_production' => $retail_production,
                'batch_production' => $batch_production,

                'is_active' => $this->input->post('is_active'),
                'in_eshop' => $in_eshop ? $in_eshop : 0,
                'eshop_biller_id' => $eshop_biller_id ? $eshop_biller_id : NULL,
                'address_line1' => $this->input->post('address_line1'),
                'address_line2' => $this->input->post('address_line2'),
            ];

            if ($_FILES['userfile']['size'] > 0) {

                $this->load->library('upload');

                $config['upload_path'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
                $config['allowed_types'] = 'gif|jpg|png|jpeg';
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = '2000';
                $config['max_height'] = '2000';
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('message', $error);
                    redirect("system_settings/warehouses");
                }

                $data['map'] = $this->upload->file_name;

                $this->load->helper('file');
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/' . $data['map'];
                $config['new_image'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/' . $data['map'];
                $config['maintain_ratio'] = TRUE;
                $config['width'] = 76;
                $config['height'] = 76;

                $this->image_lib->clear();
                $this->image_lib->initialize($config);

                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
            }
        } elseif ($this->input->post('edit_warehouse')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/warehouses");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateWarehouse($id, $data)) { //check to see if we are updateing the customer
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit warehouses',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("warehouse_updated"));
            redirect("system_settings/warehouses");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['warehouse'] = $this->settings_model->getWarehouseByID($id);
            $this->data['price_groups'] = $this->settings_model->getAllPriceGroups();
            $this->data['location_types'] = $this->settings_model->getAllLocationTypes();
            $this->data['owned_by'] = $this->settings_model->getAllowned_by();
            $this->data['eshop_setting'] = $this->settings_model->getEshopSettings();
            $this->data['billers'] = $this->settings_model->getBillers();
            $this->data['states'] = $this->site->getAllStates();

            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_warehouse', $this->data);
        }
    }

    public function delete_warehouse($id = NULL) {
        $this->sma->storeDeletedData('warehouses', 'id', $id);
        if ($this->settings_model->deleteWarehouse($id)) {
            echo lang("warehouse_deleted");
        }
    }

    public function warehouse_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('warehouses', 'id', $id);
                        $this->settings_model->deleteWarehouse($id);
                    }
                    $this->session->set_flashdata('message', lang("warehouses_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:C1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:C1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Warehouses');
                    $this->excel->getActiveSheet()->setTitle(lang('Warehouses'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('address'));


                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $wh = $this->settings_model->getWarehouseByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $wh->code);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $wh->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $wh->address);

                        $row++;
                    }
                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);

                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'warehouses_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_warehouse_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function variants() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('variants')));
        $meta = array('page_title' => lang('variants'), 'bc' => $bc);
        $this->page_construct('settings/variants', $meta, $this->data);
    }

    public function getVariants() {

        $this->load->library('datatables');
        $this->datatables->select("id, name")->from("variants")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_variant/$1') . "' class='tip' title='" . lang("edit_variant") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_variant") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_variant/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function add_variant() {

        $this->form_validation->set_rules('name', lang("name"), 'trim|is_unique[variants.name]|required');

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'));
        } elseif ($this->input->post('add_variant')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/variants");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addVariant($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add variants',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("variant_added"));
            redirect("system_settings/variants");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_variant', $this->data);
        }
    }

    public function edit_variant($id = NULL) {

        $this->form_validation->set_rules('name', lang("name"), 'trim|required');
        $tax_details = $this->settings_model->getVariantByID($id);
        if (empty($tax_details)) {
            $this->session->set_flashdata('error', lang('Variant not found'));
            redirect("system_settings/variants");
        }
        if ($this->input->post('name') != $tax_details->name) {
            $this->form_validation->set_rules('name', lang("name"), 'is_unique[variants.name]');
        }

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'));
        } elseif ($this->input->post('edit_variant')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/variants");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateVariant($id, $data)) {
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit variants',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("variant_updated"));
            redirect("system_settings/variants");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['variant'] = $tax_details;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_variant', $this->data);
        }
    }

    public function delete_variant($id = NULL) {
        $this->sma->storeDeletedData('variants', 'id', $id);
        if ($this->settings_model->deleteVariant($id)) {
            echo lang("variant_deleted");
        }
    }

    public function expense_categories() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('expense_categories')));
        $meta = array('page_title' => lang('categories'), 'bc' => $bc);
        $this->page_construct('settings/expense_categories', $meta, $this->data);
    }

    public function getExpenseCategories() {

        $this->load->library('datatables');
        $this->datatables->select("id, code, name")->from("expense_categories")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_expense_category/$1') . "' data-toggle='modal' data-target='#myModal' class='tip' title='" . lang("edit_expense_category") . "'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_expense_category") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_expense_category/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");

        echo $this->datatables->generate();
    }

    public function add_expense_category() {

        $this->form_validation->set_rules('code', lang("category_code"), 'trim|is_unique[categories.code]|required');
        $this->form_validation->set_rules('name', lang("name"), 'required|min_length[3]');

        if ($this->form_validation->run() == TRUE) {

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'),);
        } elseif ($this->input->post('add_expense_category')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/expense_categories");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addExpenseCategory($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add expense_categories',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("expense_category_added"));
            redirect("system_settings/expense_categories");
        } else {
            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_expense_category', $this->data);
        }
    }

    public function edit_expense_category($id = NULL) {
        $this->form_validation->set_rules('code', lang("category_code"), 'trim|required');
        $category = $this->settings_model->getExpenseCategoryByID($id);
        if (empty($category)) {
            $this->session->set_flashdata('error', lang('Expense Category not found'));
            redirect("system_settings/expense_categories");
        }
        if ($this->input->post('code') != $category->code) {
            $this->form_validation->set_rules('code', lang("category_code"), 'is_unique[expense_categories.code]');
        }
        $this->form_validation->set_rules('name', lang("category_name"), 'required|min_length[3]');

        if ($this->form_validation->run() == TRUE) {

            $data = array('code' => $this->input->post('code'), 'name' => $this->input->post('name'));
        } elseif ($this->input->post('edit_expense_category')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/expense_categories");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateExpenseCategory($id, $data, $photo)) {
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit expense_categories',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("expense_category_updated"));
            redirect("system_settings/expense_categories");
        } else {
            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['category'] = $category;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_expense_category', $this->data);
        }
    }

    public function delete_expense_category($id = NULL) {

        /* if ($this->settings_model->hasExpenseCategoryRecord($id)) {
          $this->session->set_flashdata('error', lang("category_has_expenses"));
          redirect("system_settings/expense_categories", 'refresh');
          } */
        $category = $this->settings_model->getExpenseCategoryByID($id);
        $DataLog = array(
            'action_type' => 'Delete',
            'product_id' => '',
            'quantity' => '',
            'action_reff_id' => $id,
            'action_affected_data' => json_encode($category),
            'action_comment' => 'Delete expense_categories',
        );

        $this->sma->setUserActionLog($DataLog);
        if ($this->settings_model->deleteExpenseCategory($id)) {
            echo lang("expense_category_deleted");
        }
    }

    public function expense_category_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $category = $this->settings_model->getExpenseCategoryByID($id);
                        $DataLog = array(
                            'action_type' => 'Delete',
                            'product_id' => '',
                            'quantity' => '',
                            'action_reff_id' => $id,
                            'action_affected_data' => json_encode($category),
                            'action_comment' => 'Delete expense_categories',
                        );

                        $this->sma->setUserActionLog($DataLog);
                        $this->settings_model->deleteExpenseCategory($id);
                    }
                    $this->session->set_flashdata('message', lang("categories_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:B1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:B1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Expense Categories');
                    $this->excel->getActiveSheet()->setTitle(lang('categories'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('name'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $sc = $this->settings_model->getExpenseCategoryByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $sc->code);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $sc->name);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'categories_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function import_categories() {

        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {

            if (isset($_FILES["userfile"])) {

                $this->load->library('upload');
                $config['upload_path'] = 'files/';
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("system_settings/categories");
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen('files/' . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }

                $titles = array_shift($arrResult);
                $keys = array('code', 'name', 'image', 'pcode', 'up_category', 'tax_rate');
                $final = array();

                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
                $dup_category = $subcategory_arr = array();

                foreach ($final as $csv_ct) {
                    if (!$this->settings_model->getCategoryByCode(trim($csv_ct['code'])) && !in_array(trim($csv_ct['code']), $dup_category)) {

                        $upm = (trim($csv_ct['up_category']) == 'Yes') ? '1' : NULL;
                        $pcat = NULL;
                        $pcode = trim($csv_ct['pcode']);
                        $tax_rate->id = 1;
                        if ($csv_ct['tax_rate']) {
                            $tax_rate = $this->settings_model->getTaxByRate(str_replace('%', '', $csv_ct['tax_rate']));
                        }
                        if (!empty($pcode)) {
 $data[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']), 'image' => trim($csv_ct['image']), 'up_category' => $upm, 'tax_rate' => $tax_rate->id);
                           
                        } else {
                            if ($pcategory = $this->settings_model->getCategoryByCode(trim($csv_ct['pcode']))) {
                                $data[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']), 'image' => trim($csv_ct['image']), 'parent_id' => $pcategory->id, 'up_category' => $upm, 'tax_rate' => $tax_rate->id);
                            } else {
                                $subcategory_arr[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']), 'image' => trim($csv_ct['image']), 'pcode' => $pcode, 'up_category' => $upm, 'tax_rate' => $tax_rate->id);
                            }
                        }
                        array_push($dup_category, trim($csv_ct['code']));
                    } else {

                        $this->session->set_flashdata('error', 'Duplicate Category Code ' . $csv_ct['code']);
                        redirect("system_settings/categories");
                    }
                    //echo $csv_ct['code'];
                }
            }

            // $this->sma->print_arrays($dup_category );
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addCategories($data)) {

            if (isset($subcategory_arr) && is_array($subcategory_arr) && count($subcategory_arr) > 0):
                $this->import_pending_subcategory($subcategory_arr);
            endif;
            $DataArr = array('data' => $data, 'subcategory' => $subcategory_arr, 'type' => 'import_category');
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($DataArr),
                'action_comment' => 'Add categories',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("categories_added"));
            redirect('system_settings/categories');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/import_categories', $this->data);
        }
    }

    public function import_subcategories() {

        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {

            if (isset($_FILES["userfile"])) {

                $this->load->library('upload');
                $config['upload_path'] = 'files/';
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("system_settings/categories");
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen('files/' . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }
                $titles = array_shift($arrResult);
                $keys = array('code', 'name', 'category_code', 'image', 'tax_rate');
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }

                $rw = 2;
                foreach ($final as $csv_ct) {
                    if (!$this->settings_model->getSubcategoryByCode(trim($csv_ct['code']))) {
                        $tax_rate->id = 1;
                        if ($csv_ct['tax_rate']) {
                            $tax_rate = $this->settings_model->getTaxByRate(str_replace('%', '', $csv_ct['tax_rate']));
                        }
                        if ($parent_actegory = $this->settings_model->getCategoryByCode(trim($csv_ct['category_code']))) {
                            $data[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']), 'image' => trim($csv_ct['image']), 'category_id' => $parent_actegory->id, 'tax_rate' => $tax_rate->id);
                        } else {
                            $this->session->set_flashdata('error', lang("check_category_code") . " (" . $csv_ct['category_code'] . "). " . lang("category_code_x_exist") . " " . lang("line_no") . " " . $rw);
                            redirect("system_settings/categories");
                        }
                    }
                    $rw++;
                }
            }

            // $this->sma->print_arrays($data);
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addSubCategories($data)) {
            $this->session->set_flashdata('message', lang("subcategories_added"));
            redirect('system_settings/categories');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/import_subcategories', $this->data);
        }
    }

    public function import_expense_categories() {

        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {

            if (isset($_FILES["userfile"])) {

                $this->load->library('upload');
                $config['upload_path'] = 'files/';
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("system_settings/expense_categories");
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen('files/' . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }
                $titles = array_shift($arrResult);
                $keys = array('code', 'name');
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }

                foreach ($final as $csv_ct) {
                    if (!$this->settings_model->getExpenseCategoryByCode(trim($csv_ct['code']))) {
                        $data[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']),);
                    }
                }
            }

            // $this->sma->print_arrays($data);
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addExpenseCategories($data)) {
            $DataArr = array('data' => $data, 'type' => 'import_expense_category');
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($DataArr),
                'action_comment' => 'Add expense_categories',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("categories_added"));
            redirect('system_settings/expense_categories');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/import_expense_categories', $this->data);
        }
    }

    public function units() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('units')));
        $meta = array('page_title' => lang('units'), 'bc' => $bc);
        $this->page_construct('settings/units', $meta, $this->data);
    }

    public function getUnits() {


        $this->load->library('datatables');
        $this->datatables->select("{$this->db->dbprefix('units')}.id as id, {$this->db->dbprefix('units')}.code, {$this->db->dbprefix('units')}.name, b.name as base_unit, {$this->db->dbprefix('units')}.operator, {$this->db->dbprefix('units')}.operation_value", FALSE)->from("units")->join("units b", 'b.id=units.base_unit', 'left')->group_by('units.id')->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_unit/$1') . "' data-toggle='modal' data-target='#myModal' class='tip' title='" . lang("edit_unit") . "'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_unit") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete-sure' href='" . site_url('system_settings/delete_unit/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");

        echo $this->datatables->generate();
    }

    public function add_unit() {

        $this->form_validation->set_rules('code', lang("unit_code"), 'trim|is_unique[units.code]|required');
        $this->form_validation->set_rules('name', lang("unit_name"), 'trim|required');
        if ($this->input->post('base_unit')) {
            $this->form_validation->set_rules('operator', lang("operator"), 'required');
            $this->form_validation->set_rules('operation_value', lang("operation_value"), 'trim|required');
        }

        if ($this->form_validation->run() == TRUE) {

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'), 'base_unit' => $this->input->post('base_unit') ? $this->input->post('base_unit') : NULL, 'operator' => $this->input->post('base_unit') ? $this->input->post('operator') : NULL, 'operation_value' => $this->input->post('operation_value') ? $this->input->post('operation_value') : NULL,);
        } elseif ($this->input->post('add_unit')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/units");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addUnit($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add units',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("unit_added"));
            redirect("system_settings/units");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['base_units'] = $this->site->getAllBaseUnits();
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_unit', $this->data);
        }
    }

    public function edit_unit($id = NULL) {

        $this->form_validation->set_rules('code', lang("code"), 'trim|required');
        $unit_details = $this->site->getUnitByID($id);
        $unit_details = $this->site->getUnitByID($id);
        if (empty($unit_details)) {
            $this->session->set_flashdata('error', lang('Unit not found'));
            redirect("system_settings/units");
        }
        if ($this->input->post('code') != $unit_details->code) {
            $this->form_validation->set_rules('code', lang("code"), 'is_unique[units.code]');
        }
        $this->form_validation->set_rules('name', lang("name"), 'trim|required');
        if ($this->input->post('base_unit')) {
            $this->form_validation->set_rules('operator', lang("operator"), 'required');
            $this->form_validation->set_rules('operation_value', lang("operation_value"), 'trim|required');
        }

        if ($this->form_validation->run() == TRUE) {

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'), 'base_unit' => $this->input->post('base_unit') ? $this->input->post('base_unit') : NULL, 'operator' => $this->input->post('base_unit') ? $this->input->post('operator') : NULL, 'operation_value' => $this->input->post('operation_value') ? $this->input->post('operation_value') : NULL,);
        } elseif ($this->input->post('edit_unit')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/units");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateUnit($id, $data)) {
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit units',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("unit_updated"));
            redirect("system_settings/units");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['unit'] = $unit_details;
            $this->data['base_units'] = $this->site->getAllBaseUnits();
            $this->load->view($this->theme . 'settings/edit_unit', $this->data);
        }
    }

    public function delete_unit($id = NULL) {

        if (($this->site->getPrdUnit($id))) {
            $this->session->set_flashdata('error', lang("product_has_unit") . ' ');
            redirect("system_settings/units");
        }

        if ($this->site->getUnitsByBUID($id)) {
            $this->sma->storeDeletedData('units', 'id', $id);
            if ($this->settings_model->deleteUnit($id)) {
                $this->session->set_flashdata('message', lang("unit_deleted"));
                redirect("system_settings/units");
            }
        } else {
            $this->session->set_flashdata('error', lang("unit_has_subunit") . ' ');
            redirect("system_settings/units");
        }
    }

    public function unit_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('units', 'id', $id);
                        $this->settings_model->deleteUnit($id);
                    }
                    $this->session->set_flashdata('message', lang("units_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:E1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:E1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Units');
                    $this->excel->getActiveSheet()->setTitle(lang('units'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('base_unit'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('operator'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('operation_value'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $unit = $this->site->getUnitByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $unit->code);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $unit->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $unit->base_unit);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $unit->operator);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $unit->operation_value);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'categories_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function price_groups() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('price_groups')));
        $meta = array('page_title' => lang('price_groups'), 'bc' => $bc);
        $this->page_construct('settings/price_groups', $meta, $this->data);
    }

    public function getPriceGroups() {

        $this->load->library('datatables');
        $this->datatables->select("id, name")->from("price_groups")->where(['type' => 'customer' ])->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/group_product_prices/$1') . "' class='tip' title='" . lang("group_product_prices") . "'><i class=\"fa fa-eye\"></i></a>  <a href='" . site_url('system_settings/edit_price_group/$1') . "' class='tip' title='" . lang("edit_price_group") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_price_group") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_price_group/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function add_price_group() {

        $this->form_validation->set_rules('name', lang("group_name"), 'trim|is_unique[price_groups.name]|required|alpha_numeric_spaces');

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'), 'type'=>  $this->input->post('type'));
        } elseif ($this->input->post('add_price_group')) {
            $this->session->set_flashdata('error', validation_errors());
            return redirect($_SERVER['HTTP_REFERER']);
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addPriceGroup($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add price_groups',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("price_group_added"));
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['type'] = (($this->input->get('type'))?$this->input->get('type') : 'customer');
            
            $this->load->view($this->theme . 'settings/add_price_group', $this->data);
        }
    }

    public function edit_price_group($id = NULL) {

        $this->form_validation->set_rules('name', lang("group_name"), 'trim|required|alpha_numeric_spaces');
        $pg_details = $this->settings_model->getPriceGroupByID($id);
        if (empty($pg_details)) {
            $this->session->set_flashdata('error', lang('Price Group not found'));
           return redirect($_SERVER['HTTP_REFERER']);
        }
        if ($this->input->post('name') != $pg_details->name) {
            $this->form_validation->set_rules('name', lang("group_name"), 'is_unique[price_groups.name]');
        }

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'));
        } elseif ($this->input->post('edit_price_group')) {
            $this->session->set_flashdata('error', validation_errors());
           return redirect($_SERVER['HTTP_REFERER']);
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updatePriceGroup($id, $data)) {
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit price_groups',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("price_group_updated"));
           return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['price_group'] = $pg_details;
            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_price_group', $this->data);
        }
    }

    public function delete_price_group($id = NULL) {
        $pg_details = $this->settings_model->getPriceGroupByID($id);
        $DataLog = array(
            'action_type' => 'Delete',
            'product_id' => '',
            'quantity' => '',
            'action_reff_id' => $id,
            'action_affected_data' => json_encode($pg_details),
            'action_comment' => 'Delete price_groups',
        );

        $this->sma->setUserActionLog($DataLog);
        if ($this->settings_model->deletePriceGroup($id)) {
            echo lang("price_group_deleted");
        }
    }

    public function product_group_price_actions($group_id) {
        if (!$group_id) {
            $this->session->set_flashdata('error', lang('no_price_group_selected'));
            redirect('system_settings/price_groups');
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'update_price') {

                    foreach ($_POST['val'] as $id) {
                        $this->settings_model->setProductPriceForPriceGroup($id, $group_id, $this->input->post('price' . $id));
                    }
                    $this->session->set_flashdata('message', lang("products_group_price_updated"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'delete') {

                    foreach ($_POST['val'] as $id) {
                        $pg_details = $this->settings_model->getPriceGroupByID($id);
                        $DataLog = array(
                            'action_type' => 'Delete',
                            'product_id' => '',
                            'quantity' => '',
                            'action_reff_id' => $id,
                            'action_affected_data' => json_encode($pg_details),
                            'action_comment' => 'Delete price_groups',
                        );
                        $this->sma->setUserActionLog($DataLog);
                        $this->settings_model->deleteProductGroupPrice($id, $group_id);
                    }
                    $this->session->set_flashdata('message', lang("products_group_price_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $this->excel->getActiveSheet()->setTitle(lang('tax_rates'));
                    $this->excel->getActiveSheet()->SetCellValue('A1', lang('product_code'));
                    $this->excel->getActiveSheet()->SetCellValue('B1', lang('product_name'));
                    $this->excel->getActiveSheet()->SetCellValue('C1', lang('price'));
                    $this->excel->getActiveSheet()->SetCellValue('D1', lang('group_name'));
                    $row = 2;
                    $group = $this->settings_model->getPriceGroupByID($group_id);
                    foreach ($_POST['val'] as $id) {
                        $pgp = $this->settings_model->getProductGroupPriceByPID($id, $group_id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $pgp->code);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $pgp->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $pgp->price);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $group->name);
                        $row++;
                    }
                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
                    $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
                    $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'price_groups_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_price_group_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function group_product_prices($group_id = NULL) {

        if (!$group_id) {
            $this->session->set_flashdata('error', lang('no_price_group_selected'));
            redirect('system_settings/price_groups');
        }

        $this->data['price_group'] = $this->settings_model->getPriceGroupByID($group_id);
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => site_url('system_settings/price_groups'), 'page' => lang('price_groups')), array('link' => '#', 'page' => lang('group_product_prices')));
        $meta = array('page_title' => lang('group_product_prices'), 'bc' => $bc);
        $this->page_construct('settings/group_product_prices', $meta, $this->data);
    }

    public function getProductPrices($group_id = NULL) {
        if (!$group_id) {
            $this->session->set_flashdata('error', lang('no_price_group_selected'));
            redirect('system_settings/price_groups');
        }

        $pp = "( SELECT {$this->db->dbprefix('product_prices')}.product_id as product_id, {$this->db->dbprefix('product_prices')}.price as price FROM {$this->db->dbprefix('product_prices')} WHERE price_group_id = {$group_id} ) PP";

        $this->load->library('datatables');
        $this->datatables->select("{$this->db->dbprefix('products')}.id as id, {$this->db->dbprefix('products')}.code as product_code, {$this->db->dbprefix('products')}.name as product_name, PP.price as price ")->from("products")->join($pp, 'PP.product_id=products.id', 'left')->edit_column("price", "$1__$2", 'id, price')->add_column("Actions", "<div class=\"text-center\"><button class=\"btn btn-primary btn-xs form-submit\" type=\"button\"><i class=\"fa fa-check\"></i></button></div>", "id");

        echo $this->datatables->generate();
    }

    public function update_product_group_price($group_id = NULL) {
        if (!$group_id) {
            $this->sma->send_json(array('status' => 0));
        }

        $product_id = $this->input->post('product_id', TRUE);
        $price = $this->input->post('price', TRUE);
        if (!empty($product_id) && !empty($price)) {
            if ($this->settings_model->setProductPriceForPriceGroup($product_id, $group_id, $price)) {
                $this->sma->send_json(array('status' => 1));
            }
        }

        $this->sma->send_json(array('status' => 0));
    }

    public function update_prices_csv($group_id = NULL) {

        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {

            if (DEMO) {
                $this->session->set_flashdata('message', lang("disabled_in_demo"));
                redirect('welcome');
            }

            if (isset($_FILES["userfile"])) {

                $this->load->library('upload');
                $config['upload_path'] = 'files/';
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("system_settings/group_product_prices/" . $group_id);
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen('files/' . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }
                $titles = array_shift($arrResult);

                $keys = array('code', 'price');

                $final = array();

                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
                $rw = 2;
                foreach ($final as $csv_pr) {
                    if ($product = $this->site->getProductByCode(trim($csv_pr['code']))) {
                        $data[] = array('product_id' => $product->id, 'price' => $csv_pr['price'], 'price_group_id' => $group_id);
                    } else {
                        $this->session->set_flashdata('message', lang("check_product_code") . " (" . $csv_pr['code'] . "). " . lang("code_x_exist") . " " . lang("line_no") . " " . $rw);
                        redirect("system_settings/group_product_prices/" . $group_id);
                    }
                    $rw++;
                }
            }
        } elseif ($this->input->post('update_price')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/group_product_prices/" . $group_id);
        }

        if ($this->form_validation->run() == TRUE && !empty($data)) {
            $this->settings_model->updateGroupPrices($data);
            $this->session->set_flashdata('message', lang("price_updated"));
            redirect("system_settings/group_product_prices/" . $group_id);
        } else {

            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));
            $this->data['group'] = $this->site->getPriceGroupByID($group_id);
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/update_price', $this->data);
        }
    }

    public function brands() {
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('brands')));
        $meta = array('page_title' => lang('brands'), 'bc' => $bc);
        $this->page_construct('settings/brands', $meta, $this->data);
    }

    public function getBrands() {

        $this->load->library('datatables');
        $this->datatables->select("id, image, code, name", FALSE)->from("brands")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_brand/$1') . "' data-toggle='modal' data-target='#myModal' class='tip' title='" . lang("edit_brand") . "'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("
            ") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete-sure' href='" . site_url('system_settings/delete_brand/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");

        echo $this->datatables->generate();
    }

    public function add_brand() {

        $this->form_validation->set_rules('name', lang("brand_name"), 'trim|required|is_unique[brands.name]'); //|alpha_numeric_spaces

        if ($this->form_validation->run() == TRUE) {

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'),);

            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['image'] = $photo;
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = $this->Settings->twidth;
                $config['height'] = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                $this->image_lib->clear();
            }
        } elseif ($this->input->post('add_brand')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/brands");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addBrand($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add brands',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("brand_added"));
            redirect("system_settings/brands");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_brand', $this->data);
        }
    }

    public function edit_brand($id = NULL) {

        $this->form_validation->set_rules('name', lang("brand_name"), 'trim|required|alpha_numeric_spaces');
        $brand_details = $this->site->getBrandByID($id);
        if (empty($brand_details)) {
            $this->session->set_flashdata('error', lang('Brands not found'));
            redirect("system_settings/brands");
        }
        if ($this->input->post('name') != $brand_details->name) {
            $this->form_validation->set_rules('name', lang("brand_name"), 'is_unique[brands.name]');
        }

        if ($this->form_validation->run() == TRUE) {

            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'),);

            if ($_FILES['userfile']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['image'] = $photo;
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = $this->Settings->twidth;
                $config['height'] = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                $this->image_lib->clear();
            }
        } elseif ($this->input->post('edit_brand')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/brands");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateBrand($id, $data)) {
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit brands',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("brand_updated"));
            redirect("system_settings/brands");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['brand'] = $brand_details;
            $this->load->view($this->theme . 'settings/edit_brand', $this->data);
        }
    }

    public function delete_brand($id = NULL) {

        if ($this->settings_model->brandHasProducts($id)) {
            $this->session->set_flashdata('error', lang("brand_has_products"));
            redirect("system_settings/brands");
        }
        $this->sma->storeDeletedData('brands', 'id', $id);
        if ($this->settings_model->deleteBrand($id)) {
            $this->session->set_flashdata('message', lang("brand_deleted"));
            redirect("system_settings/brands");
        }
    }

    public function import_brands() {

        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {

            if (isset($_FILES["userfile"])) {

                $this->load->library('upload');
                $config['upload_path'] = 'files/';
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("system_settings/brands");
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen('files/' . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }
                $titles = array_shift($arrResult);
                $keys = array('name', 'code', 'image');
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }

                foreach ($final as $csv_ct) {
                    if (!$this->settings_model->getBrandByName(trim($csv_ct['name']))) {
                        if (!$this->settings_model->getBrandByCode(trim($csv_ct['code']))) {
                            $data[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']), 'image' => trim($csv_ct['image']),);
                        } else {
                            $this->session->set_flashdata('error', 'brand code ' . $csv_ct['code'] . ' already exist');
                            redirect("system_settings/brands");
                        }
                    } else {
                        $this->session->set_flashdata('error', 'brand name ' . $csv_ct['name'] . ' already exist');
                        redirect("system_settings/brands");
                    }
                }
            }

            // $this->sma->print_arrays($data);
        }

        if ($this->form_validation->run() == TRUE && !empty($data) && $this->settings_model->addBrands($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add brands',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("brands_added"));
            redirect('system_settings/brands');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/import_brands', $this->data);
        }
    }

    public function brand_actions() {

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('brands', 'id', $id);
                        $this->settings_model->deleteBrand($id);
                    }
                    $this->session->set_flashdata('message', lang("brands_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:C1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:C1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Brands');
                    $this->excel->getActiveSheet()->setTitle(lang('brands'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('image'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $brand = $this->site->getBrandByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $brand->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $brand->code);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $brand->image);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'categories_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
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
                $this->session->set_flashdata('error', lang("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function import_pending_subcategory($subcategory_arr) {
        $dup_data = array();
        foreach ($subcategory_arr as $csv_ct):
            if (!$this->settings_model->getCategoryByCode(trim($csv_ct['code'])) && !in_array(trim($csv_ct['code']), $dup_data)) {
                $pcode = trim($csv_ct['pcode']);

                if (!empty($pcode)) {
                    if ($pcategory = $this->settings_model->getCategoryByCode(trim($csv_ct['pcode']))) {
                        $data[] = array('code' => trim($csv_ct['code']), 'name' => trim($csv_ct['name']), 'image' => trim($csv_ct['image']), 'parent_id' => $pcategory->id, 'tax_rate' => $csv_ct['tax_rate']);
                        array_push($dup_data, $csv_ct['code']);
                    }
                }
            } else {
                $this->session->set_flashdata('error', 'Duplicate Category Code ' . $csv_ct['code']);
                redirect("system_settings/categories");
            }
        endforeach;
        if (isset($data) && is_array($data)):
            $this->settings_model->addCategories($data);
        endif;
    }

    public function printers() {

        $this->data['default_printer'] = $this->Settings->default_printer;
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('printers')));
        $meta = array('page_title' => lang('Printers'), 'bc' => $bc);
        $this->page_construct('settings/printers', $meta, $this->data);
    }

    public function getPrinters() {
        $this->load->library('datatables');
        $this->datatables->select("{$this->db->dbprefix('printer_bill')}.id as id, {$this->db->dbprefix('printer_bill')}.name ", FALSE)->from("printer_bill")->where('is_deleted=0')->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_printer_bill/$1') . "'   class='tip' title='" . lang("Configure Bill Table Option") . "'><i class=\"fa fa-wrench\"></i></a> <a href='#' class='tip po  default_printer_$1' title='<b>" . lang("delete") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete23' href='" . site_url('system_settings/delete_printer_bill/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");

        echo $this->datatables->generate();
    }

    public function add_printer_bill() {
        $this->form_validation->set_rules('name', lang("name"), 'required');
        $this->form_validation->set_rules('width', lang("width"), 'required');
        $default_arr = array(1, 12);

        $id_arr = $name_arr = $val_arr = array();
        if ($this->form_validation->run() == TRUE) {
            foreach ($default_arr as $key => $f_field) {
                $f_field_obj = $this->site->getPrinterFieldByID($f_field);
                $id_arr[] = $f_field;
                $name_arr[] = $f_field_obj->name;
                ;
                $val_arr[] = $f_field_obj->value;
            }
            $data['name'] = $this->input->post('name');
            $data['width'] = $this->input->post('width');
            $data['f_column'] = 1;
            $data['l_column'] = 12;
            $data['column_id_str'] = implode(',', $id_arr);
            $data['column_name_str'] = implode(',', $name_arr);
            $data['data'] = implode(',', $val_arr);
            $resUpdate = $this->site->addPrinter($data);
            if ($resUpdate):
                $this->session->set_flashdata('message', 'Inserted successfully. Please Configure ' . $data['name']);

                redirect('system_settings/edit_printer_bill/' . $resUpdate);
            endif;
        }

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('printers')));
        $meta = array('page_title' => lang('Printers'), 'bc' => $bc);
        $this->page_construct('settings/printers_add', $meta, $this->data);
    }

    public function edit_printer_bill($id) {
        $this->form_validation->set_rules('id', lang("id"), 'required');
        $this->form_validation->set_rules('name', lang("name"), 'required');
        $this->form_validation->set_rules('width', lang("width"), 'required');

        $printer_option = $this->site->getPrinterByID($id);
        if ($this->form_validation->run() == TRUE) {
            if (!empty($_POST['id']) && $id == $_POST['id']) {
                $option_sequence = $this->input->post('option_sequence');
                $f_field = isset($printer_option->f_column) && !empty($printer_option->f_column) ? $printer_option->f_column : NULL;
                $l_field = isset($printer_option->l_column) && !empty($printer_option->l_column) ? $printer_option->l_column : NULL;

                if (empty($f_field) || empty($l_field)):
                    $this->session->set_flashdata('error', 'Required field empty');
                    redirect('system_settings/printers');
                endif;

                $id_arr = $name_arr = $val_arr = array();
                /* ------------First Field ------------------- */
                $f_field_obj = $this->site->getPrinterFieldByID($f_field);
                $f_field_name = $this->input->post('o_name_' . $f_field);
                if (!empty($f_field_name)):
                    $f_field_name = str_replace(',', ' ', $f_field_name);
                endif;
                $f_field_name = !empty($f_field_name) ? $f_field_name : $f_field_obj->name;

                $id_arr[] = $f_field;
                $name_arr[] = $f_field_name;
                $val_arr[] = $f_field_obj->value;
                /* ------------First Field End------------------- */

                if (!empty($option_sequence)):
                    $option_arr = explode(',', $option_sequence);
                    foreach ($option_arr as $f_id) {
                        $_field = $this->input->post('opt_' . $f_id);
                        if (!empty($_field)):
                            $_field_obj = $this->site->getPrinterFieldByID($f_id);
                            $_field_name = $this->input->post('o_name_' . $f_id);
                            $_field_name = !empty($_field_name) ? $_field_name : $_field_obj->name;
                            if (!empty($_field_name)):
                                $_field_name = str_replace(',', ' ', $_field_name);
                            endif;
                            $id_arr[] = $f_id;
                            $name_arr[] = $_field_name;
                            $val_arr[] = $_field_obj->value;
                        endif;
                    }
                endif;

                /* ------------Last Field ------------------- */
                $l_field_obj = $this->site->getPrinterFieldByID($l_field);
                $l_field_name = $this->input->post('o_name_' . $l_field);
                $l_field_name = !empty($f_field_name) ? $l_field_name : $l_field_obj->name;

                if (!empty($l_field_name)):
                    $l_field_name = str_replace(',', ' ', $l_field_name);
                endif;

                $id_arr[] = $l_field;
                $name_arr[] = $l_field_name;
                $val_arr[] = $l_field_obj->value;
                /* ------------Last Field End------------------- */
                $data['name'] = $this->input->post('name');
                $data['width'] = $this->input->post('width');
                $data['crop_product_name'] = !empty($this->input->post('crop_product_name')) ? $this->input->post('crop_product_name') : 0;
                $data['show_invoice_logo'] = !empty($this->input->post('show_invoice_logo')) ? $this->input->post('show_invoice_logo') : 0;
                $data['show_sr_no'] = !empty($this->input->post('show_sr_no')) ? $this->input->post('show_sr_no') : 0;
                $data['show_tin'] = !empty($this->input->post('show_tin')) ? $this->input->post('show_tin') : 0;
                $data['font_size'] = !empty($this->input->post('font_size')) ? $this->input->post('font_size') : 14;
                $data['show_customer_info'] = !empty($this->input->post('show_customer_info')) ? $this->input->post('show_customer_info') : 0;
                $data['tax_classification_view'] = !empty($this->input->post('tax_classification_view')) ? $this->input->post('tax_classification_view') : 0;


                /* --------------Added 13 -09- 2017--------------- */
                $data['show_barcode_qrcode'] = !empty($this->input->post('show_barcode_qrcode')) ? $this->input->post('show_barcode_qrcode') : 0;
                $data['show_award_point'] = !empty($this->input->post('show_award_point')) ? $this->input->post('show_award_point') : 0;
                $data['show_order_cf'] = !empty($this->input->post('show_order_cf')) ? $this->input->post('show_order_cf') : 0;
                /* --------------Added 19 -09- 2017--------------- */
                $data['append_taxval_in_productname'] = !empty($this->input->post('append_taxval_in_productname')) ? $this->input->post('append_taxval_in_productname') : 0;

                $data['show_saving_amount'] = !empty($this->input->post('show_saving_amount')) ? $this->input->post('show_saving_amount') : 0;
                $data['show_kot_tokan'] = !empty($this->input->post('show_kot_tokan')) ? $this->input->post('show_kot_tokan') : 0;
                $data['show_offer_description'] = !empty($this->input->post('show_offer_description')) ? $this->input->post('show_offer_description') : 0;

                /* --------------Added 25 -06- 2018--------------- */
                $data['show_combo_products_list'] = !empty($this->input->post('show_combo_products_list')) ? $this->input->post('show_combo_products_list') : 0;
                /* --------------Added 17 -07- 2018--------------- */
                $data['append_product_code_in_name'] = !empty($this->input->post('append_product_code_in_name')) ? $this->input->post('append_product_code_in_name') : 0;
                $data['append_hsn_code_in_name'] = !empty($this->input->post('append_hsn_code_in_name')) ? $this->input->post('append_hsn_code_in_name') : 0;
                $data['append_note_in_name'] = !empty($this->input->post('append_note_in_name')) ? $this->input->post('append_note_in_name') : 0;
                $data['show_product_image'] = !empty($this->input->post('show_product_image')) ? $this->input->post('show_product_image') : 0;
                $data['product_image_size'] = !empty($this->input->post('product_image_size')) ? $this->input->post('product_image_size') : 'width:30px;height:30px;';

$data['logo_position'] = !empty($this->input->post('logo_position')) ? $this->input->post('logo_position') : 'center';
$data['sales_person']  = !empty($this->input->post('sales_person'))?$this->input->post('sales_person'):0;
 $data['signature']  = !empty($this->input->post('signature'))?$this->input->post('signature'):0;
                $data['footer_align']  = !empty($this->input->post('footer_align'))?$this->input->post('footer_align'):'center';
                               

                /* --------------Added 12 -03- 2019--------------- */
                $data['append_article_code_in_name'] = !empty($this->input->post('append_article_code_in_name')) ? $this->input->post('append_article_code_in_name') : 0;

                /**
                 * KOT Printer Setting
                 */
                $data['kot_printing_combo_product'] = !empty($this->input->post('kot_printing_combo_product')) ? $this->input->post('kot_printing_combo_product') : 0;
                $data['kot_printing_category_name'] = !empty($this->input->post('kot_printing_category_name')) ? $this->input->post('kot_printing_category_name') : 0;
                $data['kot_print_site_name'] = !empty($this->input->post('kot_print_site_name')) ? $this->input->post('kot_print_site_name') : 0;
                $data['kot_print_customer_name'] = !empty($this->input->post('kot_print_customer_name')) ? $this->input->post('kot_print_customer_name') : 0;
                $data['kot_category_font_size'] = !empty($this->input->post('kot_category_font_size')) ? $this->input->post('kot_category_font_size') : '12px';
                $data['kot_product_name'] = !empty($this->input->post('kot_product_name')) ? $this->input->post('kot_product_name') : '11px';
                $data['kot_sub_product_name'] = !empty($this->input->post('kot_sub_product_name')) ? $this->input->post('kot_sub_product_name') : '10px';

                /**
                 * End KOT Printer Settings
                 */
                $data['sale_refe_no'] = !empty($this->input->post('sale_refe_no')) ? $this->input->post('sale_refe_no') : 0;
                $data['table_no'] = !empty($this->input->post('table_no')) ? $this->input->post('table_no') : 0;
                $data['qty_bold'] = !empty($this->input->post('qty_bold')) ? $this->input->post('qty_bold') : 0;
                $data['product_name_bold'] = !empty($this->input->post('product_name_bold')) ? $this->input->post('product_name_bold') : 0;
                $data['ascending_order_product_list'] = !empty($this->input->post('ascending_order_product_list')) ? $this->input->post('ascending_order_product_list') : 0;

                $data['show_bill_no'] = !empty($this->input->post('show_bill_no')) ? $this->input->post('show_bill_no') : 0;

                $data['deposit_opening_closing_balance'] = !empty($this->input->post('deposit_opening_closing_balance')) ? $this->input->post('deposit_opening_closing_balance') : 0;


              

                if (count($id_arr) == count($name_arr) && count($id_arr) == count($val_arr)):
                    $data['column_id_str'] = implode(',', $id_arr);
                    $data['column_name_str'] = implode(',', $name_arr);
                    $data['data'] = implode(',', $val_arr);
                    $resUpdate = $this->site->updatePrinter($_POST['id'], $data);
                    if ($resUpdate):
                        $this->session->set_flashdata('message', ' Bill Table Configured successfully');
                        redirect('system_settings/printers');
                    endif;
                endif;

                $this->session->set_flashdata('error', '  Bill Table not configured successfully');
            }
        }

        $this->data['fields_option'] = $this->site->getAllPrinterFields();
        $this->data['printer_option'] = $printer_option;
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('printers')));
        $meta = array('page_title' => lang('Printers'), 'bc' => $bc);
        $this->page_construct('settings/printers_edit', $meta, $this->data);
    }

    public function delete_printer_bill($id) {
        if (!empty((int) $id)):
            $resUpdate = $this->site->updatePrinter($id, array('is_deleted' => 1));
            if ($resUpdate):
                $this->session->set_flashdata('message', 'Deleted successfully');
                redirect('system_settings/printers');
            endif;
        endif;
        $this->session->set_flashdata('error', 'Not deleted successfully');
        redirect('system_settings/printers');
    }

    public function tax_rates_attr() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('tax_rates_attr')));
        $meta = array('page_title' => lang('tax_rate_attr'), 'bc' => $bc);
        $this->page_construct('settings/tax_rates_attr', $meta, $this->data);
    }

    public function getTaxRateAttrs() {

        $this->load->library('datatables');
        $this->datatables->select("id, name, code")->from("tax_attr")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_tax_rate_attr/$1') . "' class='tip' title='" . lang("edit_tax_rate_attr") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a>  </div>", "id");


        echo $this->datatables->generate();
    }

    public function add_tax_rate_attr() {

        $this->form_validation->set_rules('name', lang("name"), 'trim|is_unique[tax_attr.name]|required');

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'),);
        } elseif ($this->input->post('add_tax_rate_attr')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/tax_rates_attr");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->addTaxRateAttr($data)) {
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Add tax_attr',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("tax_rate_attr_added"));
            redirect("system_settings/tax_rates_attr");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/add_tax_rate_attr', $this->data);
        }
    }

    public function edit_tax_rate_attr($id = NULL) {
        $this->form_validation->set_rules('name', lang("name"), 'trim|required');
        $tax_details = $this->settings_model->getTaxAttrByID($id);
        if ($this->input->post('name') != $tax_details->name) {
            $this->form_validation->set_rules('name', lang("name"), 'is_unique[tax_attr.name]');
        }

        if ($this->form_validation->run() == TRUE) {
            $data = array('name' => $this->input->post('name'), 'code' => $this->input->post('code'),);
        } elseif ($this->input->post('edit_tax_rate')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/tax_rates_attr");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateTaxRateAttr($id, $data)) { //check to see if we are updateing the customer
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($data),
                'action_comment' => 'Edit tax_attr',
            );

            $this->sma->setUserActionLog($DataLog);
            $this->session->set_flashdata('message', lang("tax_rate_updated"));
            redirect("system_settings/tax_rates_attr");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['tax_rate_attr'] = $this->settings_model->getTaxAttrByID($id);
            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_tax_rate_attr', $this->data);
        }
    }

    public function generate_privatekey() {

        $data['api_privatekey'] = md5(time());
        if ($this->settings_model->updateSetting($data)) {
            echo json_encode($data);
        }
    }

    public function offerdiscount() {
        if ($this->input->post()) {
            $post = $this->input->post();

            if($post['offer_on_brands'] != ''){
                $brands = array();
                foreach($_POST['offer_on_brands']['brand'] as $key => $brand){
                    if($_POST['offer_on_brands']['rate'][$key]){
                        $brands[]= $brand.'~'.$_POST['offer_on_brands']['rate'][$key];
                    }
                }
            }            

            $multi_product = empty($post['offer_on_products_multiple']) ? NULL : implode(',', $post['offer_on_products_multiple']);
            $offer_on_product = empty($multi_product) ? $post['offer_on_products'] : $multi_product;

            $category = explode('~', $post['offer_category_id']);
            //$brand = ($post['offer_on_brands'] != '') ? implode(',', $post['offer_on_brands']) : NULL;
            $brand = ($post['offer_on_brands'] != '') ? implode(',', $brands) : NULL;
            $product = ($post['offer_free_products']) != '' ? $post['offer_free_products'] : NULL;
            $product_cat = ($post['offer_on_category'] != '') ? implode(',', $post['offer_on_category']) : NULL;
            $offerdyas = ($post['offer_on_days']) != '' ? implode(',', $post['offer_on_days']) : NULL;
            $start_data = ($_POST['offer_start_date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['offer_start_date']))) : NULL;
            $end_data = ($_POST['offer_start_date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['offer_end_date']))) : NULL;
            $offerdiscountrate = $post['offer_discount_rate'];
            $offerinvocerate = $post['offer_on_invoice_amount'];
            $offer_start_time = ($_POST['offer_start_time']) ? $_POST['offer_start_time'] : NULL;
            $offer_end_time = ($_POST['offer_end_time']) ? $_POST['offer_end_time'] : NULL;

            $warehouse = ($post['offer_on_warehouses'] == '') ? NULL : implode(',', $post['offer_on_warehouses']);

            unset($post['offer_on_products_multiple'], $post['offer_start_time'], $post['offer_end_time'], $post['offer_category_id'], $post['offer_on_brands'], $post['offer_on_warehouses'], $post['offer_free_products'], $post['offer_on_category'], $post['offer_on_days'], $post['offer_end_date'], $post['offer_start_date'], $post['offer_on_products']);
            $post['offer_keyword'] = $category[0];
            $post['offer_category_id'] = $category[1];
            $post['offer_on_brands'] = $brand;
            $post['offer_free_products'] = $product;
            $post['offer_on_category'] = $product_cat;
            $post['offer_on_days'] = $offerdyas;
            $post['offer_on_products'] = empty($offer_on_product) ? NULL : $offer_on_product;
            $post['offer_on_warehouses'] = $warehouse;
            $post['offer_discount_rate'] = $offerdiscountrate;
            $post['offer_on_invoice_amount'] = $offerinvocerate;
            $post['offer_start_date'] = $start_data;
            $post['offer_end_date'] = $end_data;
            $post['offer_start_time'] = $offer_start_time;
            $post['offer_end_time'] = $offer_end_time;
            $post['created_at'] = date('Y-m-d H:i:s');


            $this->db->insert('sma_offers', $post);
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $this->db->insert_id(),
                'action_affected_data' => json_encode($post),
                'action_comment' => 'Add offers',
            );

            $this->sma->setUserActionLog($DataLog);
            if ($this->db->affected_rows()) {
                $this->session->set_flashdata('message', lang('Offer add successfully'));
            } else {
                $this->session->set_flashdata('error', lang('Offer not add please try again'));
            }
            redirect('system_settings/offer_list');
            //   }
        } else {


            if (!$this->Owner) {

                $this->session->set_flashdata('error', lang('access_denied'));
                redirect("welcome");
            }

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['categories'] = $this->site->getAllCategories();
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['offers_categories'] = $this->site->getTabledataCondition('sma_offers_categories', 'id,offer_keyword,offer_category', array('is_active' => '1'), 'offer_category', 'ASC');
            $this->data['product_list'] = $this->site->getTabledataCondition('sma_products', 'id,code,name');
            $this->data['category_list'] = $this->site->getTabledataCondition('sma_categories', 'id,code,name');
            $this->data['brands_list'] = $this->site->getTabledataCondition('sma_brands', 'id,code,name');


            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('offers_and_discount')));
            $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
            $this->page_construct('settings/offer_discount', $meta, $this->data);
        }
    }

    public function offer_list() {
        $this->data['offercategory'] = $this->settings_model->getcategory(array('is_active' => '1'));
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('offerlist')));
        $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
        $this->page_construct('settings/offer_list', $meta, $this->data);
    }

    public function offer_action($action = null, $id = null) {
        if ($action != '' && $id != '') {
            switch ($action) {
                case 'delete':
                    $this->db->where('id', $id)->delete('sma_offers');
                    $DataLog = array(
                        'action_type' => 'Delete',
                        'product_id' => '',
                        'quantity' => '',
                        'action_reff_id' => $id,
                        'action_affected_data' => json_encode($ResultOffer),
                        'action_comment' => 'Delete offers',
                    );

                    $this->sma->setUserActionLog($DataLog);
                    if ($this->db->affected_rows()) {
                        $this->session->set_flashdata('message', lang('Offer delete successfully'));
                        return redirect($_SERVER['HTTP_REFERER']);
                    } else {
                        $this->session->set_flashdata('error', lang('Offer not deleted  please try again '));
                        return redirect($_SERVER['HTTP_REFERER']);
                    }
                    break;

                case 'view':
                    $offerdata = $this->db->select('sma_offers.*,sma_offers_categories.offer_category')->join('sma_offers_categories', 'sma_offers.offer_keyword=sma_offers_categories.offer_keyword')->where('sma_offers.id', $id)->order_by('sma_offers.id', 'DESC')->get('sma_offers')->row();
                    $this->data['offerdata'] = $offerdata;
                    // Warehouse Data
                    $get_warehouse = $this->db->select('id,name')->get('sma_warehouses')->result_array();
                    foreach ($get_warehouse as $war) {
                        $data[$war['id']] = $war['name'];
                    }
                    // Product List
                    $get_product = $this->site->getTabledataCondition('sma_products', 'id,code,name');
                    foreach ($get_product as $prd) {
                        $prl[$prd->id] = $prd->name . '(' . $prd->code . ')';
                    }

                    // Category
                    $get_category = $this->site->getTabledataCondition('sma_categories', 'id,code,name');
                    foreach ($get_category as $p_category) {
                        $prl_category[$p_category->id] = $p_category->name . '(' . $p_category->code . ')';
                    }
                    // Get Brand List
                    $get_brand = $this->site->getTabledataCondition('sma_brands', 'id,code,name');
                    foreach ($get_brand as $p_brand) {
                        $prl_brand[$p_brand->id] = $p_brand->name . '(' . $p_brand->code . ')';
                    }

                    $this->data['brands_list'] = $prl_brand;
                    $this->data['category_list'] = $prl_category;
                    $this->data['product_list'] = $prl;
                    $this->data['warehouses'] = $data;
                    $this->load->view($this->theme . 'settings/offer_view', $this->data);
                    break;

                case 'status':
                    $getstatsu = $this->db->select('is_active')->where('id', $id)->get('sma_offers')->row();
                    if ($getstatsu->is_active == 1) {
                        $status = '0';
                    } else {
                        $status = '1';
                    }
                    $field = array(
                        'is_active' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('id', $id)->update('sma_offers', $field);
                    if ($this->db->affected_rows()) {
                        $this->session->set_flashdata('message', lang('Offer status update successfully'));
                        return redirect($_SERVER['HTTP_REFERER']);
                    } else {
                        $this->session->set_flashdata('error', lang('Offer not update  please try again '));
                        return redirect($_SERVER['HTTP_REFERER']);
                    }
                    break;

                default :
                    return redirect($_SERVER['HTTP_REFERER']);
                    break;
            }
        } else {
            return redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function offer_edit($id = null) {

        if ($id != '') {
            if ($this->input->post()) {
                $post = $this->input->post();

                if($post['offer_on_brands'] != ''){
                    $brands = array();
                    foreach($_POST['offer_on_brands']['brand'] as $key => $brand){
                        if($_POST['offer_on_brands']['rate'][$key]){
                            $brands[]= $brand.'~'.$_POST['offer_on_brands']['rate'][$key];
                        }
                    }
                }
                $multi_product = empty($post['offer_on_products_multiple']) ? NULL : implode(',', $post['offer_on_products_multiple']);
                $offer_on_product = empty($multi_product) ? $post['offer_on_products'] : $multi_product;

                $getoffer_id = $post['offer_id'];
                $category = explode('~', $post['offer_category_id']);
                //$brand = ($post['offer_on_brands'] != '') ? implode(',', $post['offer_on_brands']) : NULL;
               $brand = ($post['offer_on_brands'] != '') ? implode(',', $brands) : NULL;
                $product = ($post['offer_free_products']) != '' ? $post['offer_free_products'] : NULL;
                $product_cat = ($post['offer_on_category'] != '') ? implode(',', $post['offer_on_category']) : NULL;
                $offerdyas = ($post['offer_on_days']) != '' ? implode(',', $post['offer_on_days']) : NULL;
                $start_data = ($_POST['offer_start_date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['offer_start_date']))) : NULL;
                $end_data = ($_POST['offer_start_date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['offer_end_date']))) : NULL;
                $offerdiscountrate = $post['offer_discount_rate'];
                $offerinvocerate = $post['offer_on_invoice_amount'];
                $offer_start_time = ($_POST['offer_start_time']) ? $_POST['offer_start_time'] : NULL;
                $offer_end_time = ($_POST['offer_end_time']) ? $_POST['offer_end_time'] : NULL;

                $warehouse = ($post['offer_on_warehouses'] == '') ? NULL : implode(',', $post['offer_on_warehouses']);
                unset($post['offer_on_products_multiple'], $post['offer_start_time'], $post['offer_end_time'], $post['offer_category_id'], $post['offer_id'], $post['offer_on_brands'], $post['offer_on_warehouses'], $post['offer_free_products'], $post['offer_on_category'], $post['offer_on_days'], $post['offer_end_date'], $post['offer_start_date'], $post['offer_on_products']);
                $post['offer_keyword'] = $category[0];
                $post['offer_category_id'] = $category[1];
                $post['offer_on_brands'] = $brand;
                $post['offer_free_products'] = $product;
                $post['offer_on_category'] = $product_cat;
                $post['offer_on_days'] = $offerdyas;
                $post['offer_on_products'] = empty($offer_on_product) ? NULL : $offer_on_product;
                $post['offer_on_warehouses'] = $warehouse;
                $post['offer_discount_rate'] = $offerdiscountrate;
                $post['offer_on_invoice_amount'] = $offerinvocerate;

                $post['offer_start_date'] = $start_data;
                $post['offer_end_date'] = $end_data;
                $post['offer_start_time'] = $offer_start_time;
                $post['offer_end_time'] = $offer_end_time;
                $post['updated_at'] = date('Y-m-d H:i:s');

                $this->db->where('id', $getoffer_id)->update('sma_offers', $post);
                $DataLog = array(
                    'action_type' => 'Edit',
                    'product_id' => '',
                    'quantity' => '',
                    'action_reff_id' => $getoffer_id,
                    'action_affected_data' => json_encode($post),
                    'action_comment' => 'Edit offers',
                );

                $this->sma->setUserActionLog($DataLog);
                if ($this->db->affected_rows()) {
                    $this->session->set_flashdata('message', lang('Offer update successfully'));
                } else {
                    $this->session->set_flashdata('error', lang('Offer not update please try again'));
                }
                redirect('system_settings/offer_list');
            } else {

                $offerdata = $this->db->select('sma_offers.*,sma_offers_categories.offer_category')->join('sma_offers_categories', 'sma_offers.offer_keyword=sma_offers_categories.offer_keyword')->where('sma_offers.id', $id)->order_by('sma_offers.id', 'DESC')->get('sma_offers')->row();
                if (!$this->Owner) {
                    $this->session->set_flashdata('error', lang('access_denied'));
                    redirect("welcome");
                }
                $this->data['offerdata'] = $offerdata;
                $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
                $this->data['categories'] = $this->site->getAllCategories();
                $this->data['warehouses'] = $this->site->getAllWarehouses();
                $this->data['offers_categories'] = $this->site->getTabledataCondition('sma_offers_categories', 'id,offer_keyword,offer_category', array('is_active' => '1'), 'offer_category', 'ASC');
                $this->data['product_list'] = $this->site->getTabledataCondition('sma_products', 'id,code,name');
                $this->data['category_list'] = $this->site->getTabledataCondition('sma_categories', 'id,code,name');
                $this->data['brands_list'] = $this->site->getTabledataCondition('sma_brands', 'id,code,name');


                $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('offers_and_discount')));
                $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
                $this->page_construct('settings/offer_edit', $meta, $this->data);
            }
        } else {
            return redirect('system_settings/offer_list');
        }
    }

    public function getofferlist($offer_key = null) {
        $offerdata = $this->settings_model->getofferdata($offer_key);
        $get_warehouse = $this->settings_model->getwarehousename();
        foreach ($get_warehouse as $war) {
            $data[$war['id']] = $war['name'];
        }
        $warehouses = $data;
        $product_list = $this->site->getTabledataCondition('sma_products', 'id,code,name');
        $category_list = $this->site->getTabledataCondition('sma_categories', 'id,code,name');
        $brands_list = $this->site->getTabledataCondition('sma_brands', 'id,code,name');
        // Table Bind
        $tabledata = '';
        $sr = 1;
        $tabledata .= '<table id="offertable" cellpadding="0" cellspacing="0" border="0" class="table table-bordered table-hover table-striped">';
        $tabledata .= '<thead><tr class="active"><th >Sr.</th>';
        $tabledata .= '<th >Date</th>';
        $tabledata .= '<th>Offer</th><th>Offer Description</th><th style="width: 30% !important;">Offer Duration</th><th>Offer on warehouses</th>';
        $tabledata .= '<th>Status</th><th style="width:100px;">Action</th></tr></thead><tbody>';
        foreach ($offerdata as $row) {
            $warehousename = '';
            $getware = explode(',', $row->offer_on_warehouses);
            if (is_array($getware)) {
                foreach ($getware as $werhs) {
                    $warehousename .= $warehouses[$werhs] . '<br/>';
                }
            }
            $offerdyas = explode(",", $row->offer_on_days);
            $showdays = '';
            $days = array('0' => 'Sun', '1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat');
            foreach ($offerdyas as $val) {
                $showdays[] = $days[$val];
            }
            $CurrentTime = date("g:i A");
            $offer_start_time = date("g:i A", strtotime($row->offer_start_time));
            $offer_end_time = date("g:i A", strtotime($row->offer_end_time));
            $CurrentDate = date("Y-m-d", strtotime(date("d-m-Y")));
            $StartDate = date("Y-m-d", strtotime($row->offer_start_date));
            $EndDate = date("Y-m-d", strtotime($row->offer_end_date));

            $tabledata .= '<tr>';
            $tabledata .= '<td>' . $sr . '</td>';
            $tabledata .= '<td>' . date('d-m-Y h:i:A', strtotime($row->created_at)) . '</td>';
            $tabledata .= '<td>' . $row->offer_category . '</td>';

            $tabledata .= '<td>' . $row->offer_name . '</td>';
            $tabledata .= '<td class="text-left" >';
            $tabledata .= "Date :   ";
            $tabledata .= ($row->offer_start_date) ? date('d-m-Y', strtotime($row->offer_start_date)) : '---';
            $tabledata .= ($row->offer_end_date) ? ' To ' . date('d-m-Y', strtotime($row->offer_end_date)) : '---';
            $tabledata .= '<br/> Time :  ';
            $tabledata .= ($row->offer_start_time) ? date("g:i A", strtotime($row->offer_start_time)) : '---';
            $tabledata .= ($row->offer_end_time) ? ' To ' . date("g:i A", strtotime($row->offer_end_time)) : '---';
            $tabledata .= '<br/> Days : ';
            $tabledata .= implode(", ", $showdays) . '</td>';
            $tabledata .= '<td>' . $warehousename . '</td>';
            $tabledata .= '<td class="text-center">';
            if ($row->is_active == 1) {
                if ($StartDate != NULL && $EndDate != NULL) {

                    if (($CurrentDate >= $StartDate) && ($EndDate >= $CurrentDate)) {
                        if (strtotime($CurrentTime) >= strtotime($offer_start_time) && strtotime($offer_end_time) >= strtotime($CurrentTime)) {
                            $tabledata .= '<a class="btn btn-success btn-xs"  onclick="return confirm(&quot; Are you sure? &quot;)"  href="system_settings/offer_action/status/' . $row->id . '">';
                            $tabledata .= '<strong> Active </strong></a>';
                        } else {
                            $status = '0';
                            $field = array(
                                'is_active' => $status,
                                'updated_at' => date('Y-m-d H:i:s')
                            );
                            $this->db->where('id', $row->id)->update('sma_offers', $field);
                            $tabledata .= '<a class="btn btn-danger btn-xs"  onclick="return confirm(&quot;Are you sure?&quot;)"  href="system_settings/offer_action/status/' . $row->id . '">';
                            $tabledata .= '<strong> Inactive </strong></a>';
                        }
                    } else {
                        $status = '0';
                        $field = array(
                            'is_active' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        );
                        $this->db->where('id', $row->id)->update('sma_offers', $field);
                        $tabledata .= '<a class="btn btn-danger btn-xs"  onclick="return confirm(&quot;Are you sure?&quot;)"  href="system_settings/offer_action/status/' . $row->id . '">';
                        $tabledata .= '<strong> Inactive </strong></a>';
                    }
                }
            } else {
                $tabledata .= '<a class="btn btn-danger btn-xs"  onclick="return confirm(&quot;Are you sure?&quot;)"  href="system_settings/offer_action/status/' . $row->id . '">';
                $tabledata .= '<strong> Inactive </strong></a>';
            }
            $tabledata .= '</td>';
            $tabledata .= '<td>';
            $tabledata .= '<a class="text-info offeraction" title="View" data-placement="bottom" data-html="true" href="' . base_url() . 'system_settings/offer_action/view/' . $row->id . '" data-toggle="ajax"  tabindex="-1"><i class="fa fa-eye" aria-hidden="true"></i> </a> |';
            $tabledata .= '<a href="system_settings/offer_edit/' . $row->id . '" class="text-primary offeraction" title="Edit"> <i class="fa fa-pencil-square-o" aria-hidden="true"></i>  </a> |';
            $tabledata .= '<a href="system_settings/offer_action/delete/' . $row->id . '" class="text-danger offeraction"  title="Delete" onClick ="return confirm(\'Are you sure?\')"> <i class="fa fa-trash" aria-hidden="true"></i>  </a>';

            $tabledata .= '</td>';

            $tabledata .= '</tr>';
            $sr++;
        }
        $tabledata .= '</tbody></table>';
        // End Table
        echo json_encode($tabledata);
    }

    public function offercategory() {
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('offer Category')));
        $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
        $this->page_construct('settings/offer_category', $meta, $this->data);
    }

    public function getcategory() {
        $category = $this->settings_model->getcategory(array('is_delete' => '0'));
        $tabledata = '';
        $sr = 1;
        $tabledata .= '<table id="offertable" cellpadding="0" cellspacing="0" border="0" class="table table-bordered table-hover table-striped">';
        $tabledata .= '<thead>';
        $tabledata .= '<tr class="active"><th> Sr. </th><th>' . lang("offers_category") . '</th><th>' . lang("Status") . '</th><th style="width:100px;">' . lang("actions") . '</th></tr></thead><tbody>';
        foreach ($category as $category_val) {
            $tabledata .= '<tr>';
            $tabledata .= '<td>' . $sr . '</td>';
            $tabledata .= '<td>' . $category_val->offer_category . '</td>';
            $tabledata .= '<td class="text-center">';
            if ($category_val->is_active == 1) {
                $tabledata .= '<button class="btn btn-sm btn-success btn-xs " onclick="myfunction(' . $category_val->id . ',0,&#39;status&#39;);" value="' . $category_val->id . '~1">';
                $tabledata .= '<strong> Active </strong></button>';
            } else {
                $tabledata .= '<button class="btn btn-sm btn-danger btn-xs "  onclick="myfunction(' . $category_val->id . ',1,&#39;status&#39;);" id="statuschange" value="' . $category_val->id . '~1" >';
                $tabledata .= '<strong> Inactive </strong></button>';
            }
            $tabledata .= '</td>';
            $tabledata .= '<td>';

            $tabledata .= '<button  class="text-primary offeraction btn btn-primary btn-sm" title="Edit" onclick="myfunction(' . $category_val->id . ',&#39;' . $category_val->offer_category . '&#39;,&#39;edit&#39;);"> <i class="fa fa-pencil-square-o" aria-hidden="true"></i>  Edit </button> ';

            $tabledata .= '</td>';
            $tabledata .= '</tr>';
            $sr++;
        }
        $tabledata .= '</tbody></table>';
        echo json_encode($tabledata);
    }

    public function offer_category_action() {
        $id = $_GET['id'];
        $keytype = $_GET['keytype'];
        $value = $_GET['value'];
        $response = '';
        switch ($keytype) {
            case 'status':
                $response = $this->settings_model->category_update(array('id' => $id), array('is_active' => $value));

                break;

            case 'edit':
                $response = $this->settings_model->category_update(array('id' => $id), array('offer_category' => $value));
                break;

            default :

                break;
        }

        echo json_encode($response);
    }

    public function restaurant_tables() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('restaurant_tables')));
        $meta = array('page_title' => lang('restaurant_tables'), 'bc' => $bc);
        $this->page_construct('settings/restaurant_tables', $meta, $this->data);
    }

   public function get_restaurant_tables() {

        $this->load->library('datatables');
        //$this->datatables->select("id, name, type, table_group,price_group_name")->from("restaurant_tables")->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_restaurant_table/$1') . "' class='tip' title='" . lang("edit_restaurant_table") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_restaurant_table") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_restaurant_table/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        $this->datatables->select("{$this->db->dbprefix('restaurant_tables')}.id as id, {$this->db->dbprefix('restaurant_tables')}.name, r.name as parent, {$this->db->dbprefix('restaurant_tables')}.type, {$this->db->dbprefix('restaurant_tables')}.table_group,{$this->db->dbprefix('restaurant_tables')}.price_group_name")
                ->from("restaurant_tables")
                ->join('restaurant_tables r','r.id = restaurant_tables.parent_id','left')
                ->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_restaurant_table/$1') . "' class='tip' title='" . lang("edit_restaurant_table") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_restaurant_table") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_restaurant_table/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function add_restaurant_table() {

        $this->form_validation->set_rules('name', lang("restaurant_table_name"), 'trim|is_unique[restaurant_tables.name]|required');

        if ($this->form_validation->run() == TRUE) {
            if($this->input->post('price_group')){
                 $priceGroup = explode("~", $this->input->post('price_group'));
            }
            $data = array('name' => $this->input->post('name'),
                            'type' => ($this->input->post('type')? $this->input->post('type') : NULL),
                        'table_group' => ($this->input->post('table_group')? $this->input->post('table_group'): NULL),
                        'price_group_id' => (isset($priceGroup[0])?$priceGroup[0] : NULL), 
                        'price_group_name' => (isset($priceGroup[1])?$priceGroup[1] : NULL),
                        'parent_id'        => ($this->input->post('parent_id')? $this->input->post('parent_id') : 0),
                          );
        } elseif ($this->input->post('add_restaurant_table')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/restaurant_tables");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->add_restaurant_table($data)) {
            $this->session->set_flashdata('message', lang("restaurant_table_added"));
            redirect("system_settings/restaurant_tables");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['price_group'] = $this->settings_model->price_group('table');
             $this->data['tables'] = $this->settings_model->getTables();
            $this->load->view($this->theme . 'settings/add_restaurant_table', $this->data);
        }
    }

    public function edit_restaurant_table($id = NULL) {

        $this->form_validation->set_rules('name', lang("restaurant_table_name"), 'trim|required|alpha_numeric_spaces');
        $pg_details = $this->settings_model->restaurant_table_by_id($id);
        if ($this->input->post('name') != $pg_details->name) {
            $this->form_validation->set_rules('name', lang("restaurant_table"), 'is_unique[restaurant_tables.name]');
        }

        if ($this->form_validation->run() == TRUE) {
              if($this->input->post('price_group')){
                 $priceGroup = explode("~", $this->input->post('price_group'));
            }
            $data = array('name' => $this->input->post('name'),
                          'type' => ($this->input->post('type')? $this->input->post('type') : NULL),
                          'table_group' => ($this->input->post('table_group')? $this->input->post('table_group'): NULL),
                          'price_group_id' => (isset($priceGroup[0])?$priceGroup[0] : NULL), 
                          'price_group_name' => (isset($priceGroup[1])?$priceGroup[1] : NULL),
                           'parent_id'        => ($this->input->post('parent_id')? $this->input->post('parent_id') : 0),
                    
                         );
        } elseif ($this->input->post('edit_restaurant_table')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/restaurant_tables");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->update_restaurant_table($id, $data)) {
            $this->session->set_flashdata('message', lang("restaurant_table_updated"));
            redirect("system_settings/restaurant_tables");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['restaurant_table'] = $pg_details;
            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['price_group'] = $this->settings_model->price_group('table');
            $this->data['tables'] = $this->settings_model->getTables();
            $this->load->view($this->theme . 'settings/edit_restaurant_table', $this->data);
        }
    }

    public function delete_restaurant_table($id = NULL) {
        if ($this->settings_model->delete_restaurant_table($id)) {
            echo lang("restaurant_table_deleted");
        }
    }

    public function user_group_actions() {
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
                        $group = $this->settings_model->getGroupByID($id);
                        $DataLog = array(
                            'action_type' => 'Delete',
                            'product_id' => '',
                            'quantity' => '',
                            'action_reff_id' => $id,
                            'action_affected_data' => json_encode($group),
                            'action_comment' => 'Delete groups',
                        );

                        $this->sma->setUserActionLog($DataLog);
                        $this->settings_model->deleteGroup($id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("User_group_deleted_successfully"));
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:B1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:B1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'User Groups');
                    $this->excel->getActiveSheet()->setTitle(lang('User Groups'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('group_name'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('group_description'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $user_group_row = $this->settings_model->getGroupByID($id);

                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $user_group_row->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $user_group_row->description);

                        $row++;
                    }


                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'user_group_' . date('Y_m_d_H_i_s');
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
                $this->session->set_flashdata('error', $this->lang->line("No_user_group_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function update_UP_Package() {

        $ordercounts = $_POST['ordercounts'];

        if ($ordercounts > 0) {

            $this->db->query("UPDATE sma_settings SET `up_balance_order` = `up_balance_order`+" . $ordercounts . " WHERE `setting_id`='1' ");

            if ($this->db->affected_rows()) {
                $data['status'] = 'success';
            } else {
                $data['status'] = 'error';
                $data['msg'] = $this->db->_error_message();
            }
        } else {
            $data['status'] = 'error';
            $data['msg'] = 'Invalid Order Count';
        }

        echo json_encode($data);
    }

    /**
     * Manage barcode printer
     * @return type
     */
    public function manage_barcode() {

        if ($_POST) {
            if ($this->input->post('barcode_type') == 'dynamic') {

                foreach ($_POST['manage_barcode'] as $key => $barcode) {

                    $getbarcode = explode("~", $barcode);
                    $barcodedata[] = array(
                        'name_key' => $getbarcode[0],
                        'name' => $getbarcode[1],
                        'status' => '1',
                        'font_weight' => isset($_POST[$getbarcode[0] . '_style']) ? $_POST[$getbarcode[0] . '_style'] : NULL,
                        'font_size' => isset($_POST[$getbarcode[0] . '_size']) ? $_POST[$getbarcode[0] . '_size'] : NULL,
                        'display' => isset($_POST[$getbarcode[0] . '_display']) ? $_POST[$getbarcode[0] . '_display'] : NULL,
                    );
                }

                $result = $this->settings_model->storeManagebarcode($barcodedata);

                $this->settings_model->barcodeAlign(['barcode_align' => $this->input->post('barcode_align'), 'barcode_type' => $this->input->post('barcode_type')]);

                if ($result) {
                    $this->session->set_flashdata('message', 'Barcode has been manage successfuly.');
                    return redirect('system_settings/manage_barcode');
                } else {
                    $this->session->set_flashdata('error', 'Barcode not manage. Please try again.');
                    return redirect('system_settings/manage_barcode');
                }
            } else if ($this->input->post('barcode_type') == 'dynamic2') {
                foreach ($_POST['manage_barcode_dynamic2'] as $key => $barcode) {

                    $getbarcode = explode("~", $barcode);
                    $barcodedata[] = array(
                        'name_key' => $getbarcode[0],
                        'name' => $getbarcode[1],
                        'status' => '1',
                        'font_weight' => isset($_POST[$getbarcode[0] . '_style']) ? $_POST[$getbarcode[0] . '_style'] : NULL,
                        'font_size' => isset($_POST[$getbarcode[0] . '_size']) ? $_POST[$getbarcode[0] . '_size'] : NULL,
                        'display' => isset($_POST[$getbarcode[0] . '_display']) ? $_POST[$getbarcode[0] . '_display'] : NULL,
                    );
                }

                $result = $this->settings_model->storeManagebarcode($barcodedata);



                $this->settings_model->barcodeAlign(['barcode_align' => $this->input->post('barcode_align'), 'barcode_type' => $this->input->post('barcode_type')]);

                if ($result) {
                    $this->session->set_flashdata('message', 'Barcode has been manage successfuly.');
                    return redirect('system_settings/manage_barcode');
                } else {
                    $this->session->set_flashdata('error', 'Barcode not manage. Please try again.');
                    return redirect('system_settings/manage_barcode');
                }
            } else if ($this->input->post('barcode_type') == 'sidebyside') {
                foreach ($_POST['manage_side_barcode'] as $key => $barcode) {

                    $getbarcode = explode("~", $barcode);
                    $barcodedata[] = array(
                        'name_key' => $getbarcode[0],
                        'name' => $getbarcode[1],
                        'status' => '1',
                        'font_weight' => isset($_POST[$getbarcode[0] . '_style']) ? $_POST[$getbarcode[0] . '_style'] : NULL,
                        'font_size' => isset($_POST[$getbarcode[0] . '_size']) ? $_POST[$getbarcode[0] . '_size'] : NULL,
                        'display' => isset($_POST[$getbarcode[0] . '_display']) ? $_POST[$getbarcode[0] . '_display'] : NULL,
                    );
                }

                $result = $this->settings_model->storeManagebarcode($barcodedata);


                $this->settings_model->barcodeAlign(['barcode_align' => $this->input->post('barcode_align'), 'barcode_type' => $this->input->post('barcode_type')]);

                if ($result) {
                    $this->session->set_flashdata('message', 'Barcode has been manage successfuly.');
                    return redirect('system_settings/manage_barcode');
                } else {
                    $this->session->set_flashdata('error', 'Barcode not manage. Please try again.');
                    return redirect('system_settings/manage_barcode');
                }
            } else {
                $result = $this->settings_model->barcodeAlign(['barcode_type' => $this->input->post('barcode_type')]);

                if ($result) {
                    $this->session->set_flashdata('message', 'Barcode has been manage successfuly.');
                    return redirect('system_settings/manage_barcode');
                } else {
                    $this->session->set_flashdata('error', 'Barcode not manage. Please try again.');
                    return redirect('system_settings/manage_barcode');
                }
            }
        } else {
            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('Manage Barcode')));
            $meta = array('page_title' => lang('Manage Barcode'), 'bc' => $bc);
            $this->data['managebarcode'] = $this->settings_model->getManagebarcode();
            $this->page_construct('settings/manage_barcode', $meta, $this->data);
        }
    }


    /* SMS Config Functions */

    public function sms_configs() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('SMS Configs')));
        $meta = array('page_title' => lang('Sms_Configs'), 'bc' => $bc);
        $this->page_construct('settings/sms_configs', $meta, $this->data);
    }

    public function getSMSConfigs() {

        $this->load->library('datatables');
        $this->datatables->select("id, template_name, IF((client_dlt_te_id IS NULL OR client_dlt_te_id = '') , dlt_te_id , client_dlt_te_id) dlt_te_id, sms_format")
                ->from("sms_configs")
                ->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/edit_sms_config/$1') . "' class='tip' title='" . lang("edit_sms_config") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> </div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

    public function edit_sms_config($id = NULL) {

        $config_details = $this->settings_model->getSmsConfigByID($id);
        if (empty($config_details)) {
            $this->session->set_flashdata('error', lang('Sms Config not found'));
            redirect("system_settings/sms_configs");
        }
        if ($this->input->post('client_dlt_te_id') != $config_details->client_dlt_te_id) {
            $this->form_validation->set_rules('client_dlt_te_id', lang("SMS DLT_TE_ID"), 'trim|numeric|is_unique[sms_configs.client_dlt_te_id]|is_unique[sms_configs.dlt_te_id]');
        }

        if ($this->form_validation->run() == TRUE) {
            if ($this->input->post('client_dlt_te_id') != $config_details->dlt_te_id) {
                $data = array('client_dlt_te_id' => trim($this->input->post('client_dlt_te_id')), 'updated_by' => $this->session->userdata('user_id'));
            } else {
                $data = array('client_dlt_te_id' => NULL, 'updated_by' => $this->session->userdata('user_id'));
            }
        } elseif ($this->input->post('edit_sms_config')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/sms_configs");
        }

        if ($this->form_validation->run() == TRUE && $this->settings_model->updateSmsConfig($id, $data)) {

            $this->session->set_flashdata('message', "SMS Config Updated.");
            redirect("system_settings/sms_configs");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['config'] = $this->settings_model->getSmsConfigByID($id);
            $this->data['id'] = $id;
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'settings/edit_sms_config', $this->data);
        }
    }

    /* End SMS Config Functions */

    public function custom_fields() {

        if ($this->input->post('update_settings')) {

            $cf_type = ['product', 'customer', 'supplier', 'biller', 'employee'];
            foreach ($cf_type as $key => $type) {
                $data[$type] = [
                    'type' => $type,
                    'cf1' => (trim($_POST[$type]['cf1']) ? trim($_POST[$type]['cf1']) : null),
                    'cf2' => (trim($_POST[$type]['cf2']) ? trim($_POST[$type]['cf2']) : null),
                    'cf3' => (trim($_POST[$type]['cf3']) ? trim($_POST[$type]['cf3']) : null),
                    'cf4' => (trim($_POST[$type]['cf4']) ? trim($_POST[$type]['cf4']) : null),
                    'cf5' => (trim($_POST[$type]['cf5']) ? trim($_POST[$type]['cf5']) : null),
                    'cf6' => (trim($_POST[$type]['cf6']) ? trim($_POST[$type]['cf6']) : null),
                ];
            }//end foreach

            $this->settings_model->updateCustomFields($data);

            $this->session->set_flashdata('message', lang('setting_updated'));
            redirect("system_settings/custom_fields");
        } else {

            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

            $this->data['custom_fields'] = $this->settings_model->getCustomFields();

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('custom_fields')));
            $meta = array('page_title' => lang('custom_fields'), 'bc' => $bc);
            $this->page_construct('settings/custom_fields', $meta, $this->data);
        }
    }


    /*************************
     * Discount Coupon
     **************************/
    public function discount_coupon_list() {
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Coupon list')));
        $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
        $this->page_construct('settings/discount_coupons', $meta, $this->data);
    }

    /**
     * Get Discount Coupon List
     */
    public function getdiscountcouponlist() {
      
        $offerdata = $this->settings_model->getDiscountCouponData();
        $tabledata = '';
        $sr = 1;
        $tabledata .= '<table id="offertable" cellpadding="0" cellspacing="0" border="0" class="table table-bordered table-hover table-striped">';
        $tabledata .= '<thead><tr class="active"><th >Sr.</th>';
        $tabledata .= '<th >Date</th>';
        $tabledata .= '<th> Coupon Code</th><th>Expiry Date</th>';
        $tabledata .= '<th>Customer</th><th>Customer Group</th><th>Operation</th><th>Max Coupon</th><th>Used Coupon</th><th>Status </th><th>Active</th><th style="width:100px;">Action</th></tr></thead><tbody>';

        $get_customergrp = $this->settings_model->getCustomerGroup();
        foreach ($get_customergrp as $custgrp) {
            $datacus[$custgrp['id']] = $custgrp['name'];
        }
        $customergrp = $datacus;
        $get_customer = $this->settings_model->getCompanies();
        foreach ($get_customer as $cust) {
            $datacustomer[$cust['id']] = $cust['name'];
        }
        $customer_arr = $datacustomer;
        foreach ($offerdata as $row) {
            /* Offe Customer Group name */
            $customergrpname = '';
            $getcustgrp = explode(',', $row->customer_group_id);
            if (is_array($getcustgrp)) {
                foreach ($getcustgrp as $getcustid) {
                    $customergrpname .= $customergrp[$getcustid];
                }
            }
            $customername = '';
            $getcust = explode(',', $row->customer_id);
            if (is_array($getcust)) {
                foreach ($getcust as $getcustid) {
                    $customername .= $customer_arr[$getcustid];
                }
            }
            
           $statusbtn  = $this->buttonColor($row->status);
            

            $tabledata .= '<tr>';
            $tabledata .= '<td>' . $sr . '</td>';
            $tabledata .= '<td>' . date('d-m-Y h:i:A', strtotime($row->created_at)) . '</td>';


            $tabledata .= '<td>' . $row->coupon_code . '</td>';
            $tabledata .= '<td class="text-left" >';
            $tabledata .= date('d-m-Y',strtotime($row->expiry_date));
            $tabledata .= '</td>';
            $tabledata .= '<td class="text-center">' . (($customername)?$customername : 'ALL') . '</td>';
            $tabledata .= '<td class="text-center">' . (($customergrpname)?$customergrpname : 'ALL') . '</td>';
            $tabledata .= '<td class="text-center">Minimum Cart Value: ' . $row->minimum_cart_value . '<br/>Discount: ' . $row->discount_rate . '</td>';
            $tabledata .= '<td class="text-center">'. $row->max_coupons . '</td>';
            $tabledata .= '<td class="text-center">'. $row->used_coupons . '</td>';
            $tabledata .= '<td class="text-center">';
                $tabledata .= '<div class="dropdown">';
                   $tabledata .= '<button id="status_'.$row->id.'" class="btn  btn-xs '.$statusbtn.' dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                       $tabledata .= ucfirst($row->status);
                   $tabledata .= '</button>';
                   $tabledata .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $tabledata .= '<span onclick="managestatus('.$row->id.',\'active\' )" class="dropdown-item" style=" "> Active</span>';
                        $tabledata .= '<span onclick="managestatus('.$row->id.',\'used\' )" class="dropdown-item" href="#" >Used</span>';
                        $tabledata .= '<span onclick="managestatus('.$row->id.',\'expired\' )" class="dropdown-item" href="#" >Expired</span>';
                   $tabledata .= '</div>';
                $tabledata .= '</div>';
            
            
           $tabledata .= '</td>';
           
            
            $tabledata .= '<td class="text-center">';
            if ($row->is_active == 1) {
                $tabledata .= '<a class="btn btn-success btn-xs"  onclick="return confirm(&quot;Are you sure?&quot;)"  href="system_settings/discount_coupon_action/status/' . $row->id . '">';
                $tabledata .= '<strong> Active </strong></a>';
            } else {
                $tabledata .= '<a class="btn btn-danger btn-xs"  onclick="return confirm(&quot;Are you sure?&quot;)"  href="system_settings/discount_coupon_action/status/' . $row->id . '">';
                $tabledata .= '<strong> Inactive </strong></a>';
            }
            $tabledata .= '</td>';
            $tabledata .= '<td>';
            $tabledata .= '<a class="text-info offeraction" title="View" data-placement="bottom" data-html="true" href="' . base_url() . 'system_settings/discount_coupon_action/view/' . $row->id . '" data-toggle="ajax"  tabindex="-1"><i class="fa fa-eye" aria-hidden="true"></i> </a> |';
            $tabledata .= '<a href="system_settings/discount_coupon_edit/' . $row->id . '" class="text-primary offeraction" title="Edit"> <i class="fa fa-pencil-square-o" aria-hidden="true"></i>  </a> |';
            $tabledata .= '<a href="system_settings/discount_coupon_action/delete/' . $row->id . '" class="text-danger offeraction"  title="Delete" onClick ="return confirm(\'Are you sure?\')"> <i class="fa fa-trash" aria-hidden="true"></i>  </a>';

            $tabledata .= '</td>';

            $tabledata .= '</tr>';
            $sr++;
        }
        $tabledata .= '</tbody></table>';
        // End Table
        echo json_encode($tabledata);
    }

    /**
     * Add Discount Coupon
     * @return type
     */
    public function add_discount_coupon() {
        if ($_POST) {

            $this->form_validation->set_rules('coupon_code', lang('Coupon_Code'), 'trim|required');
            $this->form_validation->set_rules('discount', lang('Discount Rate'), 'trim|required');
            $this->form_validation->set_rules('expiry_date', lang('Expiry Date'), 'trim|required');
            if ($this->form_validation->run() == TRUE) {
                $date = $this->sma->fld(trim($this->input->post('expiry_date')));
                $data = [
                    'coupon_code' => $this->input->post('coupon_code'),
                    'coupon_descripion' => $this->input->post('coupon_descripion'),
                    'customer_id' => $this->input->post('customer'),
                    'customer_group_id' => $this->input->post('customer_group'),
                    'minimum_cart_amount' => $this->input->post('minimum_cart_value'),
                    'discount_rate' => $this->input->post('discount'),
                    'maximum_discount_amount' => $this->input->post('maximum_discount_amount'),
                    'expiry_date' => $date,
                    'max_coupons' => $this->input->post('max_coupons'),
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                if ($this->settings_model->StoreDiscountCoupon($data)) {
                    $this->session->set_flashdata('message', 'Coupon has been added successfully. ');
                    return redirect("system_settings/discount_coupon_list");
                } else {
                    $this->session->set_flashdata('error', 'Sorry, Please try again.');
                    return redirect('add_discount_coupon');
                }
            }

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['customers'] = $this->companies_model->getAllCustomerCompanies();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Add Coupon')));
            $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
            $this->page_construct('settings/discount_add_coupon', $meta, $this->data);
        } else {
            if (!$this->Owner) {
                $this->session->set_flashdata('error', lang('access_denied'));
                redirect("welcome");
            }

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['customers'] = $this->companies_model->getAllCustomerCompanies();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Add Coupon')));
            $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
            $this->page_construct('settings/discount_add_coupon', $meta, $this->data);
        }
    }

    
    /**
     * 
     * @param type $id
     * @return type
     */
    public function discount_coupon_edit($id = NULL){
        $this->data['CouponInfo'] = $this->settings_model->DiscountCouponInfo($id);
      
        if ($_POST) {

            $this->form_validation->set_rules('coupon_code', lang('Coupon_Code'), 'trim|required');
            $this->form_validation->set_rules('discount', lang('Discount Rate'), 'trim|required');
            $this->form_validation->set_rules('expiry_date', lang('Expiry Date'), 'trim|required');
            if ($this->form_validation->run() == TRUE) {
                $date = $this->sma->fld(trim($this->input->post('expiry_date')));
                $data = [
                    'coupon_code' => $this->input->post('coupon_code'),
                    'coupon_descripion' => $this->input->post('coupon_descripion'),
                    'customer_id' => $this->input->post('customer'),
                    'customer_group_id' => $this->input->post('customer_group'),
                    'minimum_cart_amount' => $this->input->post('minimum_cart_value'),
                    'discount_rate' => $this->input->post('discount'),
                    'maximum_discount_amount' => $this->input->post('maximum_discount_amount'),
                    'expiry_date' => $date,
                    'max_coupons' => $this->input->post('max_coupons'),
                    'status'       => $this->input->post('status'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                if ($this->settings_model->UpdateDiscountCoupon($data, ['id' => $this->input->post('id')])) {
                    $this->session->set_flashdata('message', 'Coupon has been updated successfully. ');
                    return redirect("system_settings/discount_coupon_list");
                } else {
                    $this->session->set_flashdata('error', 'Sorry, Please try again.');
                    return redirect('discount_coupon_edit');
                }
            }

            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['customers'] = $this->companies_model->getAllCustomerCompanies();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Add Coupon')));
            $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
            $this->page_construct('settings/discount_coupon_edit', $meta, $this->data);
        } else {
            if (!$this->Owner) {
                $this->session->set_flashdata('error', lang('access_denied'));
                redirect("welcome");
            }

            
            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['customers'] = $this->companies_model->getAllCustomerCompanies();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Edit Coupon')));
            $meta = array('page_title' => lang('pos_settings'), 'bc' => $bc);
            $this->page_construct('settings/discount_coupon_edit', $meta, $this->data);
        }
    }
              
    /**
     * 
     * @param type $action
     * @param type $id
     * @return type
     */
    public function discount_coupon_action($action = null, $id = null) {
        if ($action != '' && $id != '') {
            switch ($action) {
                case 'delete':
                        $Result = $this->settings_model->DiscountCouponInfo($id);
                        $data = [
                            'is_deleted' => '1',
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                        if($this->settings_model->UpdateDiscountCoupon($data, ['id' => $id])){
                             $DataLog = array(
                                 'action_type' => 'Delete',
                                 'product_id' => '',
                                 'quantity' => '',
                                 'action_reff_id' => $id,
                                 'action_affected_data' => json_encode($Result),
                                 'action_comment' => 'Delete discount coupons',
                             );

                             $this->sma->setUserActionLog($DataLog);
                             $this->session->set_flashdata('message', lang('Coupon has been deleted successfully'));
                             return redirect($_SERVER['HTTP_REFERER']);
                        }else{
                             $this->session->set_flashdata('error', lang('Coupon not deleted  please try again '));
                             return redirect($_SERVER['HTTP_REFERER']);
                        }    
                    break;

                case 'view':
                   
                    $this->data['CouponsInfo'] = $this->settings_model->DiscountCouponInfo($id);
                    // Customer Group Data
                    $get_customer_group = $this->db->select('id,name')->get('sma_customer_groups')->result_array();
                    foreach ($get_customer_group as $war) {
                        $prl_customer_group[$war['id']] = $war['name'];
                    }
                    // Companies Data
                    $get_companies = $this->db->select('id,name')->get('sma_companies')->result_array();
                    foreach ($get_companies as $war) {
                        $prl_companies[$war['id']] = $war['name'];
                    }

                    $this->data['customer_group_list'] = $prl_customer_group;
                    $this->data['companies_list'] = $prl_companies;
                    $this->load->view($this->theme . 'settings/discount_coupon_view', $this->data);
                    break;

                case 'status':
                    $getstatsu = $this->db->select('is_active')->where('id', $id)->get('sma_discount_coupons')->row();
                    if ($getstatsu->is_active == 1) {
                        $status = '0';
                    } else {
                        $status = '1';
                    }
                    $field = array(
                        'is_active' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    );
                    $this->db->where('id', $id)->update('sma_discount_coupons', $field);
                    if ($this->db->affected_rows()) {
                        $this->session->set_flashdata('message', lang('Coupon status update successfully'));
                        return redirect($_SERVER['HTTP_REFERER']);
                    } else {
                        $this->session->set_flashdata('error', lang('Coupon not update  please try again '));
                        return redirect($_SERVER['HTTP_REFERER']);
                    }
                    break;

                default :
                    return redirect($_SERVER['HTTP_REFERER']);
                    break;
            }
        } else {
            return redirect($_SERVER['HTTP_REFERER']);
        }
    }

    /**
     * 
     * @param type $status
     * @return string
     */
    public function buttonColor($status){
        switch ($status){
            case 'active':
                 $btn = 'btn-success';       
                break;
            
            case 'used':
                 $btn = 'btn-warning';       
                break;
            
            case 'expired':
                    $btn = 'btn-danger';                
                break;
            
            default:
                    $btn = 'btn-secondary';
                break;
                
        }
        
        return $btn;
    }
    
    
    /**
     * Discount Coupon Status Manage
     */
    public function discount_coupon_status(){
        $status = $this->input->post('status');
        $id = $this->input->post('id');
        
        $result = $this->settings_model->UpdateDiscountCoupon(['status' =>$status ], ['id' => $id]);
        if($result){
            $response = [
              'status' => TRUE,
              'changestatus' => ucfirst($status), 
              'button' =>  $this->buttonColor($status),
              'message' => 'Discount coupon has been changed successfully' 
            ];
        }else{
             $response = [
              'status' => FALSE,
              'message' => 'Discount coupon not change, Please try again' 
            ];
        }
        
        echo json_encode($response);
    }
    
    /*********************************
     *  End Discount Coupon
     *********************************/

     /**
     * Barcode A4 Page Settings
     */
    public function barcode_stiker_size(){
        if($_POST){
          
            $field = [
                "paper_size"        => $this->input->post('paper_size'),
                "margin_top"        => $this->input->post('margin_top'),
                "margin_bottom"     => $this->input->post('margin_bottom'),
                "margin_left"       => $this->input->post('margin_left'),
                "margin_right"      => $this->input->post('margin_right'),
                "label_width"       => $this->input->post('label_width'),
                "label_height"      => $this->input->post('label_height'),
                "gap_label_top"     => $this->input->post('gap_label_top'),
                "gap_label_bottom"  => $this->input->post('gap_label_bottom'),
                "gap_label_left"    => $this->input->post('gap_label_left'),
                "gap_label_right"   => $this->input->post('gap_label_right'),
                "sticker_per_page"   => $this->input->post('sticker_per_page'),
                /*"no_of_row"         => $this->input->post('no_of_row'),
                "no_of_columns"     => $this->input->post('no_of_columns'),*/
            ];
            $dataField  = serialize($field);
    
            $this->db->where(['setting_id' => '1'])->update('sma_settings',['barcode_a4_page_dynamic' => $dataField]);
            if($this->db->affected_rows()){
                  $this->session->set_flashdata('message', lang('A4 page dynamic has been update successfully'));
                  return redirect($_SERVER['HTTP_REFERER']);
            }else{
                $this->session->set_flashdata('error', lang('A4 page dynamic not update, please try again '));
                return redirect($_SERVER['HTTP_REFERER']);
            }
        }else{ 
           $this->load->view($this->theme . 'settings/print_barcode_paper_size', $this->data);  
        }
            
    }
    
    
    /**
     * End Barcode A4 Page settings
     */

        /**
     * Table Price Group
     */
    public function restaurant_tables_price_groups() {

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('system_settings'), 'page' => lang('system_settings')), array('link' => '#', 'page' => lang('price_groups')));
        $meta = array('page_title' => lang('price_groups'), 'bc' => $bc);
        $this->page_construct('settings/table_price_groups', $meta, $this->data);
    }

    public function tablegetPriceGroups() {

        $this->load->library('datatables');
        $this->datatables->select("id, name")->from("price_groups")->where(['type' => 'table' ])->add_column("Actions", "<div class=\"text-center\"><a href='" . site_url('system_settings/group_product_prices/$1') . "' class='tip' title='" . lang("group_product_prices") . "'><i class=\"fa fa-eye\"></i></a>  <a href='" . site_url('system_settings/edit_price_group/$1') . "' class='tip' title='" . lang("edit_price_group") . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_price_group") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('system_settings/delete_price_group/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');

        echo $this->datatables->generate();
    }

   /**
     * 
     * @return boolean
     */
    public function table_seats(){
        $tableId = $this->input->get('table_id');
        $seats = $this->input->get('seats');
        
        $data = $this->settings_model->tableSeats($tableId, ['seats' =>$seats]);
         $response = array();
        if($data){
            $response = [
                            'status' => TRUE,
                            'seats'  => $seats
                        ];         
        }else{
            $response = ['status' => false];
        }
        echo json_encode($response);
        
    }

    function orderingItemByCategories($CategoryId){
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $this->data['categoryName'] = $this->products_model->getCategoryById($CategoryId);
        $this->data['products'] = $this->products_model->getCategoryByProducts($CategoryId);
        $this->data['modal_js'] = $this->site->modal_js();
        $this->load->view($this->theme . 'settings/ordering_item', $this->data);
	}
    function saveOrderingItem($CategoryId = null){
        $variants = $this->input->get('variants');
        if (empty($variants)) {
            $this->output
                ->set_status_header(400)
                ->set_output(json_encode(['error' => 'No data provided']));
            return;
        }
        $data = [];
        foreach ($variants as $variant) {
            $data[] = [
                'variant_name' => $variant['variantName'],
                'rank_no'      => $variant['rankNo'],
                'is_selected'  =>  $variant['isSelected'] == 'true' ? '1' : '0'
            ];
        }
        if (!empty($data)) {
            $result =   $this->settings_model->saveOrdering($data);
            if ($result) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save data']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
        }
    }
    public function saveCategories() {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }
    
        $categories = $this->input->post('categories');
        $data = [];
    
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category['id'],
                'rank' => $category['rank'],
                'flag_visible' => $category['visible']
            ];
        }
        $this->settings_model->update_category($data);
        $affected_rows = $this->db->affected_rows();
        if ($affected_rows > 0) {
            $this->session->set_flashdata('message', lang("Rank Updated Successfully"));
            echo json_encode(['status' => 'success']);
        } else {
            $this->session->set_flashdata('error', 'No Rank Updated.');
            echo json_encode(['status' => 'error']);
        }
    }
    
    
}
