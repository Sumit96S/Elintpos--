<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Customers extends MY_Controller {

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
        $this->lang->load('customers', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('companies_model');

        $this->load->model('pos_model');
        $this->pos_settings = $this->pos_model->getSetting();
    }

    public function index($action = NULL) {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['action'] = $action;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('customers')));
        $meta = array('page_title' => lang('customers'), 'bc' => $bc);
        $this->page_construct('customers/index', $meta, $this->data);
    }

    public function getCustomers() {
        $this->sma->checkPermissions('index');
        $this->load->library('datatables');
        /* $this->datatables
          ->select("id, company, name, email, phone, price_group_name, customer_group_name, vat_no,gstn_no, deposit_amount, award_points,('SELECT balance FROM sma_gift_cards WHERE customer_id = sma_companies.id AND balance > 0 AND expiry >= DATE(now()) ORDER BY balance DESC LIMIT 1') as giftAmt")
          ->from("companies")
          ->where('group_name', 'customer')
          ->add_column("Actions", "<div class=\"text-center\"><a class=\"tip\" title='" . lang("list_deposits") . "' href='" . site_url('customers/deposits/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-money\"></i></a> <a class=\"tip\" title='" . lang("add_deposit") . "' href='" . site_url('customers/add_deposit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-plus\"></i></a> <a class=\"tip\" title='" . lang("list_addresses") . "' href='" . site_url('customers/addresses/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-location-arrow\"></i></a> <a class=\"tip\" title='" . lang("list_users") . "' href='" . site_url('customers/users/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-users\"></i></a> <a class=\"tip\" title='" . lang("add_user") . "' href='" . site_url('customers/add_user/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-user-plus\"></i></a> <a class=\"tip\" title='" . lang("edit_customer") . "' href='" . site_url('customers/edit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_customer") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('customers/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id"); */
        /*
          $this->datatables
          ->select("id, company, name, email, phone, price_group_name, customer_group_name,gstn_no, deposit_amount,('SELECT balance FROM sma_gift_cards WHERE customer_id = sma_companies.id  AND balance > 0 AND expiry >= DATE(now()) ORDER BY balance DESC LIMIT 1') as giftAmt")
          ->from("companies")
          ->where('group_name', 'customer')
          ->add_column("Actions", "<div class=\"text-center\"><a class=\"tip\" title='" . lang("list_deposits") . "' href='" . site_url('customers/deposits/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-money\"></i></a>   <a class=\"tip\" title='" . lang("add_deposit") . "' href='" . site_url('customers/add_deposit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-plus\"></i></a> <a class=\"tip\" title='" . lang("list_addresses") . "' href='" . site_url('customers/addresses/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-location-arrow\"></i></a> <a class=\"tip\" title='" . lang("edit_customer") . "' href='" . site_url('customers/edit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_customer") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('customers/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
         */
        $this->datatables
                ->select("id, company, name, email, phone, price_group_name, customer_group_name, gstn_no, deposit_amount,award_points, cf1,cf2");
        $this->datatables->add_column('opening_deposit_balance', '');
        $this->datatables->add_column('closing_deposit_balance', '');
        $this->datatables->add_column('GiftCard', '');
        $this->datatables->from("companies")
                ->where('group_name', 'customer')
                ->add_column("Actions", "<div class=\"text-center\"><a class=\"tip\" title='" . lang("list_deposits") . "' href='" . site_url('customers/deposits/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-money\"></i></a> <a class=\"tip\" title='" . lang("Deposits_History") . "' href='" . site_url('customers/depositsHistory/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-history\"></i></a> <a class=\"tip\" title='" . lang("add_deposit") . "' href='" . site_url('customers/add_deposit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-plus\"></i></a> <a class=\"tip\" title='" . lang("list_addresses") . "' href='" . site_url('customers/addresses/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-location-arrow\"></i></a> <a class=\"tip\" title='" . lang("edit_customer") . "' href='" . site_url('customers/edit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_customer") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('customers/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");


        //->unset_column('id');
        echo $this->datatables->generate();
    }

    public function view($id = NULL) {
        $this->sma->checkPermissions('index', true);
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['customer'] = $this->companies_model->getCompanyByID($id);
        $cfields = $this->site->getCustomeFieldsLabel('customer');
        $this->data['custome_fields'] = $cfields['customer'];
        $this->load->view($this->theme . 'customers/view', $this->data);
    }

    public function add_quick() {

        $this->form_validation->set_rules('cf1', lang("PAN_Card"), 'is_unique[companies.cf1]');
        $this->form_validation->set_rules('phone', lang("phone"), 'required|exact_length[10]');
        $this->form_validation->set_rules('email', lang("email_address"), 'is_unique[companies.email]');
        //$this->form_validation->set_rules('add_country', lang("Country Name"), 'trim|required');            
        //$this->form_validation->set_rules('state', lang("state"), 'trim|required');            
        // $this->form_validation->set_rules('state_code', lang("State Code"), 'trim|required');            
        //$this->form_validation->set_rules('statename', lang("State Name"), 'trim|required');
        //$this->form_validation->set_rules('postal_code', lang("Pincode"), 'required');        

        $synch_customer_data = ($this->Settings->synch_customers) ? true : false;

        if ($this->form_validation->run('customer/add') == true) {

            $company = !empty($this->input->post('company')) ? $this->input->post('company') : '-';

            $country = $this->input->post('add_country');
            $state_name = $this->input->post('statename');
            $state_code = $this->input->post('state_code');
            $_SESSION["quick_customername"] = $this->input->post('name');
            $_SESSION["quick_customerphone"] = $this->input->post('phone');
            if ($this->input->post('country') == 'other' && $country != '') {

                $this->db->insert('country_master', ['name' => $country]);
                $country_id = $this->db->insert_id();
                $statedata = [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name,
                ];
                $this->site->addstate($statedata);
            } else if (($this->input->post('state') == 'other' || $this->input->post('state') == '') && ($state_code != '' && $state_name != '' )) {

                $country_id = $this->site->getCountryId($country);
                $statedata = [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name,
                ];
                $this->site->addstate($statedata);
            }

            $customer_group_id = $this->input->post('customer_group');
            $cg = $this->site->getCustomerGroupByID($customer_group_id);
            $customer_group_name = $cg->name;

            $price_group_id = $this->input->post('price_group') ? $this->input->post('price_group') : NULL;
            $pg = $this->site->getPriceGroupByID($this->input->post('price_group'));
            $price_group_name = $this->input->post('price_group') ? $pg->name : NULL;

            $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' => '3',
                'group_name' => 'customer',
                'customer_group_id' => $customer_group_id,
                'customer_group_name' => $customer_group_name,
                'price_group_id' => $price_group_id,
                'price_group_name' => $price_group_name,
                'company' => $company,
                'address' => $this->input->post('address'),
                'vat_no' => $this->input->post('vat_no'),
                'gstn_no' => $this->input->post('gstn_no'),
                'city' => $this->input->post('city'),
                'state' => $state_name,
                'state_code' => $state_code,
                'postal_code' => $this->input->post('postal_code'),
                'country' => $country,
                'phone' => $this->input->post('phone'),
                'pan_card' => $this->input->post('pan_card'),
                'dob' => $this->sma->fsd($this->input->post('dob')),
                'anniversary' => $this->sma->fsd($this->input->post('anniversary')),
                'dob_father' => $this->sma->fsd($this->input->post('dob_father')),
                'dob_mother' => $this->sma->fsd($this->input->post('dob_mother')),
                'dob_child1' => $this->sma->fsd($this->input->post('dob_child1')),
                'dob_child2' => $this->sma->fsd($this->input->post('dob_child2')),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
            ];
        } elseif ($this->input->post('add_customer')) {
            $this->session->set_flashdata('error', validation_errors());
            return redirect($_SERVER['HTTP_REFERER']);
        }

        if ($this->form_validation->run() == true && $cid = $this->companies_model->addCompany($data, $synch_customer_data)) {
            $this->session->set_flashdata('message', lang("customer_added"));
            $ref = isset($_SERVER["HTTP_REFERER"]) ? explode('?', $_SERVER["HTTP_REFERER"]) : NULL;

            // Storing session data
            

            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['price_groups'] = $this->companies_model->getAllPriceGroups();
            $this->data['country'] = $this->site->getCountry();
            $this->data['biller'] = $this->companies_model->getCompanyByID($this->Settings->default_biller);

            $this->load->view($this->theme . 'customers/add_quick', $this->data);
        }
    }

    public function add() {

        $this->sma->checkPermissions(false, true);
        $this->form_validation->set_rules('cf1', lang("PAN_Card"), 'is_unique[companies.cf1]');
        $this->form_validation->set_rules('phone', lang("phone"), 'required|exact_length[10]');
        $this->form_validation->set_rules('email', lang("email_address"), 'is_unique[companies.email]');
        //$this->form_validation->set_rules('add_country', lang("Country Name"), 'trim|required');            
        //$this->form_validation->set_rules('state', lang("state"), 'trim|required');            
        //$this->form_validation->set_rules('state_code', lang("State Code"), 'trim|required');            
        //$this->form_validation->set_rules('statename', lang("State Name"), 'trim|required');
        //$this->form_validation->set_rules('postal_code', lang("Pincode"), 'required');        

        $synch_customer_data = ($this->Settings->synch_customers) ? true : false;

        if ($this->form_validation->run('customer/add') == true) {

            $company = !empty($this->input->post('company')) ? $this->input->post('company') : '-';

            $country = $this->input->post('add_country');
            $state_name = $this->input->post('statename');
            $state_code = $this->input->post('state_code');

            if ($this->input->post('country') == 'other' && $country != '') {

                $this->db->insert('country_master', ['name' => $country]);
                $country_id = $this->db->insert_id();
                $statedata = [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name,
                ];
                $this->site->addstate($statedata);
            } else if (($this->input->post('state') == 'other' || $this->input->post('state') == '') && ($state_code != '' && $state_name != '' )) {

                $country_id = $this->site->getCountryId($country);
                $statedata = [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name,
                ];
                $this->site->addstate($statedata);
            }

            $customer_group_id = $this->input->post('customer_group');
            $cg = $this->site->getCustomerGroupByID($customer_group_id);
            $customer_group_name = $cg->name;

            $price_group_id = $this->input->post('price_group') ? $this->input->post('price_group') : NULL;
            $pg = $this->site->getPriceGroupByID($this->input->post('price_group'));
            $price_group_name = $this->input->post('price_group') ? $pg->name : NULL;

            $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' => '3',
                'group_name' => 'customer',
                'customer_group_id' => $customer_group_id,
                'customer_group_name' => $customer_group_name,
                'price_group_id' => $price_group_id,
                'price_group_name' => $price_group_name,
                'company' => $company,
                'address' => $this->input->post('address'),
                'vat_no' => $this->input->post('vat_no'),
                'gstn_no' => $this->input->post('gstn_no'),
                'city' => $this->input->post('city'),
                'state' => $state_name,
                'state_code' => $state_code,
                'postal_code' => $this->input->post('postal_code'),
                'country' => $country,
                'phone' => $this->input->post('phone'),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'award_points' => $this->input->post('award_points'),
                'dob' => $this->sma->fsd($this->input->post('dob')),
                'anniversary' => $this->sma->fsd($this->input->post('anniversary')),
                'dob_father' => $this->sma->fsd($this->input->post('dob_father')),
                'dob_mother' => $this->sma->fsd($this->input->post('dob_mother')),
                'dob_child1' => $this->sma->fsd($this->input->post('dob_child1')),
                'dob_child2' => $this->sma->fsd($this->input->post('dob_child2')),
                'pan_card' => $this->input->post('pan_card'),
            ];


            if ($this->Settings->synced_data_sales) {
                $data['synced_data'] = $this->input->post('synced_data');
                $data['customer_url'] = $this->input->post('customer_url');
                $data['privatekey'] = $this->input->post('privatekey');
            }
        } elseif ($this->input->post('add_customer')) {
            $this->session->set_flashdata('error', validation_errors());
            return redirect($_SERVER['HTTP_REFERER']);
        }

        if ($this->form_validation->run() == true && $cid = $this->companies_model->addCompany($data, $synch_customer_data)) {
            if ($this->Settings->synced_data_sales && $data['privatekey']) {

                $biller_details = $this->site->getCompanyByID($this->pos_settings->default_biller);
                $_SESSION['Send_customer'] = [
                    'status' => '1',
                    'suppliername' => $biller_details->name,
                    'supplierKey' => $this->Settings->api_privatekey,
                    'send_customer_url' => $this->input->post('customer_url') . '/api4/setSupplierKey',
                    'supplierURL' => base_url(),
                    'pivatekey' => $data['privatekey']
                ];
            }


            $this->session->set_flashdata('message', lang("customer_added"));
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['price_groups'] = $this->companies_model->getAllPriceGroups();
            $this->data['country'] = $this->site->getCountry();
            $this->data['biller'] = $this->companies_model->getCompanyByID($this->Settings->default_biller);
            $cfields = $this->site->getCustomeFieldsLabel('customer');
            $this->data['custome_fields'] = $cfields['customer'];

            $this->load->view($this->theme . 'customers/add', $this->data);
        }
    }

    public function edit($id = NULL) {

        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $company_details = $this->companies_model->getCompanyByID($id);
        $original_value = $this->db->select('cf1')->where('id', $id)->get('sma_companies')->row()->cf1;
        if ($this->input->post('cf1') != $original_value) {
            $this->form_validation->set_rules('cf1', lang("PAN_Card"), 'is_unique[companies.cf1]');
        }
        if ($this->input->post('phone') != $company_details->phone) {
            $this->form_validation->set_rules('phone', lang("phone"), 'required|exact_length[10]|is_unique[companies.phone]');
        }
        if ($this->input->post('email') != $company_details->email) {
            $this->form_validation->set_rules('email', lang("email_address"), 'is_unique[companies.email]');
        }

        /* $this->form_validation->set_rules('add_country', lang("Country Name"), 'trim|required');            
          $this->form_validation->set_rules('state', lang("state"), 'trim|required');
          $this->form_validation->set_rules('state_code', lang("State Code"), 'trim|required');
          $this->form_validation->set_rules('statename', lang("State Name"), 'trim|required');
          $this->form_validation->set_rules('postal_code', lang("Pincode"), 'required'); */

        if ($this->form_validation->run('customer/add') == true) {

            $company = !empty($this->input->post('company')) ? $this->input->post('company') : '-';
            $country = $this->input->post('add_country');
            $state_name = $this->input->post('statename');
            $state_code = $this->input->post('state_code');

            if ($this->input->post('country') == 'other' && $country != '') {

                $this->db->insert('country_master', ['name' => $country]);
                $country_id = $this->db->insert_id();
                $statedata = [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name,
                ];
                $this->site->addstate($statedata);
            } else if (($this->input->post('state') == 'other' || $this->input->post('state') == '') && ($state_code != '' && $state_name != '' )) {

                $country_id = $this->site->getCountryId($country);
                $statedata = [
                    'country_id' => $country_id,
                    'code' => $state_code,
                    'name' => $state_name,
                ];
                $this->site->addstate($statedata);
            }

            $cg = $this->site->getCustomerGroupByID($this->input->post('customer_group'));
            $pg = $this->site->getPriceGroupByID($this->input->post('price_group'));
            $e_password = $this->input->post('eshop_pass');
            $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'customer_group_id' => $this->input->post('customer_group'),
                'customer_group_name' => $cg->name,
                'price_group_id' => $this->input->post('price_group') ? $this->input->post('price_group') : NULL,
                'price_group_name' => $this->input->post('price_group') ? $pg->name : NULL,
                'company' => $company,
                'address' => $this->input->post('address'),
                'vat_no' => $this->input->post('vat_no'),
                'gstn_no' => $this->input->post('gstn_no'),
                'city' => $this->input->post('city'),
                'state' => $state_name,
                'state_code' => $state_code,
                'postal_code' => $this->input->post('postal_code'),
                'country' => $country,
                'phone' => $this->input->post('phone'),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'award_points' => $this->input->post('award_points'),
                'dob' => $this->sma->fsd($this->input->post('dob')),
                'anniversary' => $this->sma->fsd($this->input->post('anniversary')),
                'dob_father' => $this->sma->fsd($this->input->post('dob_father')),
                'dob_mother' => $this->sma->fsd($this->input->post('dob_mother')),
                'dob_child1' => $this->sma->fsd($this->input->post('dob_child1')),
                'dob_child2' => $this->sma->fsd($this->input->post('dob_child2')),
                'pan_card' => $this->input->post('pan_card'),
            ];
            if (!empty($e_password)):
                $data['password'] = md5($e_password);
            endif;

            if ($this->Settings->synced_data_sales) {
                $data['synced_data'] = $this->input->post('synced_data');
                $data['customer_url'] = $this->input->post('customer_url');
                $data['privatekey'] = $this->input->post('privatekey');
            } else {
                $data['synced_data'] = NULL;
                $data['customer_url'] = NULL;
                $data['privatekey'] = NULL;
            }
        } elseif ($this->input->post('edit_customer')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->companies_model->updateCompany($id, $data, $this->Settings->synch_customers)) {

            if ($this->Settings->synced_data_sales && $data['privatekey']) {
                if ($company_details->privatekey != $data['privatekey']) {
                    $biller_details = $this->site->getCompanyByID($this->pos_settings->default_biller);
                    $_SESSION['Send_customer'] = [
                        'status' => '1',
                        'suppliername' => $biller_details->name,
                        'supplierKey' => $this->Settings->api_privatekey,
                        'send_customer_url' => $this->input->post('customer_url') . '/api4/setSupplierKey',
                        'supplierURL' => base_url(),
                        'pivatekey' => $data['privatekey']
                    ];
                }
            }

            $this->session->set_flashdata('message', lang("customer_updated"));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->data['customer'] = $company_details;
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['price_groups'] = $this->companies_model->getAllPriceGroups();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['country'] = $this->site->getCountry();

            $cfields = $this->site->getCustomeFieldsLabel('customer');
            $this->data['custome_fields'] = $cfields['customer'];

            $this->load->view($this->theme . 'customers/edit', $this->data);
        }
    }

    public function users($company_id = NULL) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $company_id = $this->input->get('id');
        }


        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['modal_js'] = $this->site->modal_js();
        $this->data['company'] = $this->companies_model->getCompanyByID($company_id);
        $this->data['users'] = $this->companies_model->getCompanyUsers($company_id);
        $this->load->view($this->theme . 'customers/users', $this->data);
    }

    function add_user($company_id = NULL) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $company_id = $this->input->get('id');
        }
        $company = $this->companies_model->getCompanyByID($company_id);

        $this->form_validation->set_rules('email', lang("email_address"), 'is_unique[users.email]');
        $this->form_validation->set_rules('password', lang('password'), 'required|min_length[8]|max_length[20]|matches[password_confirm]');
        $this->form_validation->set_rules('password_confirm', lang('confirm_password'), 'required');

        if ($this->form_validation->run('companies/add_user') == true) {
            $active = $this->input->post('status');
            $notify = $this->input->post('notify');
            list($username, $domain) = explode("@", $this->input->post('email'));
            $email = strtolower($this->input->post('email'));
            $password = $this->input->post('password');
            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
                'last_name' => $this->input->post('last_name'),
                'phone' => $this->input->post('phone'),
                'gender' => $this->input->post('gender'),
                'company_id' => $company->id,
                'company' => $company->company,
                'group_id' => 3
            );
            $this->load->library('ion_auth');
        } elseif ($this->input->post('add_user')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('customers');
        }

        if ($this->form_validation->run() == true && $this->ion_auth->register($username, $password, $email, $additional_data, $active, $notify)) {
            $this->session->set_flashdata('message', lang("user_added"));
            redirect("customers");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['company'] = $company;
            $this->load->view($this->theme . 'customers/add_user', $this->data);
        }
    }

    public function import_csv() {
        $this->sma->checkPermissions('add', true);
        $this->load->helper('security');
        $this->form_validation->set_rules('csv_file', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == true) {

            if (DEMO) {
                $this->session->set_flashdata('warning', lang("disabled_in_demo"));
                redirect($_SERVER["HTTP_REFERER"]);
            }

            if (isset($_FILES["csv_file"])) /* if($_FILES['userfile']['size'] > 0) */ {

                $this->load->library('upload');

                $config['upload_path'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/csv/';
                $config['allowed_types'] = 'csv';
                $config['max_size'] = '2000';
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;

                $this->upload->initialize($config);

                if (!$this->upload->do_upload('csv_file')) {

                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("customers");
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen("assets/mdata/$this->Customer_assets/uploads/csv/" . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 5001, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }
                $titles = array_shift($arrResult);

                $keys = array('company', 'name', 'email', 'phone', 'address', 'city', 'state', 'state_code', 'postal_code', 'country', 'gstn_no', 'vat_no', 'cf1', 'cf2', 'cf3', 'cf4', 'cf5', 'cf6', 'deposit_amount');


                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
                $rw = 2;
                foreach ($final as $csv) {
                    //if ($this->companies_model->getCompanyByEmail($csv['email'])) {
                    // $this->session->set_flashdata('error', lang("check_customer_email") . " (" . $csv['email'] . "). " . lang("customer_already_exist") . " (" . lang("line_no") . " " . $rw . ")");
                    // redirect("customers");
                    //  }
                    if ($csv['name'] == '' || $csv['phone'] == '' || $csv['state'] == '' || $csv['state_code'] == '') {
                        $this->session->set_flashdata('error', "Please required Name, Phone No, State, State Code");
                        redirect("customers");
                    }

                    $rw++;
                }
                foreach ($final as $record) {
                    if ($record['name'] != '' && $record['phone'] != '' && $record['state'] != '' && $record['state_code'] != '') {

                        $record['group_id'] = 3;
                        $record['group_name'] = 'customer';
                        $record['customer_group_id'] = 1;
                        $record['customer_group_name'] = 'General';
                        $data[] = $record;
                    }
                }
                //$this->sma->print_arrays($data);
            }
        } elseif ($this->input->post('import')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('customers');
        }

        if ($this->form_validation->run() == true && !empty($data)) {
            if ($this->companies_model->addCompanies($data)) {
                $this->session->set_flashdata('message', lang("customers_added"));
                redirect('customers');
            }
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'customers/import', $this->data);
        }
    }

    public function delete($id = NULL) {
        $this->sma->checkPermissions(NULL, TRUE);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        if ($this->input->get('id') == 1) {
            $this->session->set_flashdata('error', lang('customer_x_deleted'));
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . (isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : site_url('welcome')) . "'; }, 0);</script>");
        }
        $this->sma->storeDeletedData('companies', 'id', $id);
        if ($this->companies_model->deleteCustomer($id)) {
            echo lang("customer_deleted");
        } else {
            $this->sma->deleteTableDataById('companies', $id);
            $this->session->set_flashdata('warning', lang('customer_x_deleted_have_sales'));
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . (isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : site_url('welcome')) . "'; }, 0);</script>");
        }
    }

    public function suggestions($term = NULL, $limit = NULL) {
        // $this->sma->checkPermissions('index');
        if ($this->input->get('term')) {
            $term = $this->input->get('term', TRUE);
        }
        if (strlen($term) < 1) {
            return FALSE;
        }
        $limit = $this->input->get('limit', TRUE);
        $rows['results'] = $this->companies_model->getCustomerSuggestions($term, $limit);
        $this->sma->send_json($rows);
    }

    public function getCustomer($id = NULL) {
        // $this->sma->checkPermissions('index');
        $row = $this->companies_model->getCompanyByID($id);
        if($row->name == 'Walk in Customer'){
            $this->sma->send_json(array(array('id' => $row->id, 'text' => ($row->company != '-' ? $row->name : $row->name), 'company_name' => $row->name)));
        }else{
            $this->sma->send_json(array(array('id' => $row->id, 'text' => ($row->company != '-' ? $row->company : $row->name), 'company_name' => $row->company)));
        }
    }

    /**
     * 
     * @param type $id
     */
    public function getCustomereshop($id = NULL) {
        // $this->sma->checkPermissions('index');
        $row = $this->companies_model->getCompanyByID($id);
        $this->sma->send_json(array(array('id' => $row->id, 'text' => $row->name, 'company_name' => $row->name)));
    }

    public function get_customer_details($id = NULL) {
        $this->sma->send_json($this->companies_model->getCompanyByID($id));
    }

    public function get_award_points($id = NULL) {
        $this->sma->checkPermissions('index');
        $row = $this->companies_model->getCompanyByID($id);
        $this->sma->send_json(array('ca_points' => $row->award_points));
    }

    public function customer_actions() {
        if (!$this->Owner && !$this->GP['bulk_actions']) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == true) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'export_deposit') {

                $this->load->library('excel');
        $this->excel->setActiveSheetIndex(0);
        $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));
        $this->excel->getActiveSheet()->SetCellValue('A1', 'Customer name');
        $this->excel->getActiveSheet()->SetCellValue('B1', 'Phone No ');
        $this->excel->getActiveSheet()->SetCellValue('C1', 'Customer Group');
        $this->excel->getActiveSheet()->SetCellValue('D1', 'Member Card No');
        $this->excel->getActiveSheet()->SetCellValue('E1', 'Flat No');
        $this->excel->getActiveSheet()->SetCellValue('F1', 'Amount ');
        $this->excel->getActiveSheet()->SetCellValue('G1', 'Supercash ');
        $this->excel->getActiveSheet()->SetCellValue('H1', 'Payment Mode');
        // $this->excel->getActiveSheet()->SetCellValue('I1', 'Deposit Type');

        $row = 2;
        foreach ($_POST['val'] as $id) {
            $customer = $this->companies_model->customerDeposit($id);

            $this->excel->getActiveSheet()->SetCellValue('A' . $row, $customer->name);
            $this->excel->getActiveSheet()->SetCellValue('B' . $row, $customer->phone);
            $this->excel->getActiveSheet()->SetCellValue('C' . $row, $customer->customer_group_name);
            $this->excel->getActiveSheet()->SetCellValue('D' . $row, $customer->cf1);
            $this->excel->getActiveSheet()->SetCellValue('E' . $row, $customer->cf2);
            $this->excel->getActiveSheet()->SetCellValue('F' . $row);
            // $this->excel->getActiveSheet()->SetCellValue('F' . $row, $customer->deposit_amount);
            $this->excel->getActiveSheet()->SetCellValue('G' . $row);
            $this->excel->getActiveSheet()->SetCellValue('H' . $row);
            // $this->excel->getActiveSheet()->SetCellValue('I' . $row,"services");
            $row++;
        }

        // $this->excel->getActiveSheet()->protectCells('B1:B'.$row);
        // $this->excel->getActiveSheet()->protectCells('A1:B1', 'PHP');
        // $this->excel->getActiveSheet()->getProtection()->setSheet(true); //->protectCells('A1:B1', 'PHP');
        
        // $filename = 'sample_customers_bulk_deposit' . date('Y_m_d_H_i_s');
        $filename = 'sample_customers_bulk_deposit';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
        return $objWriter->save('php://output');
    }
                if ($this->input->post('form_action') == 'delete') {
                    $this->sma->checkPermissions('delete');
                    $error = false;
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('companies', 'id', $id);
                        if (!$this->companies_model->deleteCustomer($id)) {
                            $this->sma->deleteTableDataById('companies', $id);
                            $error = true;
                        }
                    }
                    if ($error) {
                        $this->session->set_flashdata('warning', lang('customers_x_deleted_have_sales'));
                    } else {
                        $this->session->set_flashdata('message', lang("customers_deleted"));
                    }
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:R1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:R1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Customers');


                    $this->excel->getActiveSheet()->setTitle(lang('customers'));

                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('company'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('email'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('phone'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('address'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('city'));
                    $this->excel->getActiveSheet()->SetCellValue('G2', lang('state'));
                    $this->excel->getActiveSheet()->SetCellValue('H2', lang('postal_code'));
                    $this->excel->getActiveSheet()->SetCellValue('I2', lang('country'));
                    $this->excel->getActiveSheet()->SetCellValue('J2', lang('vat_no'));
                    $this->excel->getActiveSheet()->SetCellValue('K2', lang('GST No'));
                    $this->excel->getActiveSheet()->SetCellValue('L2', lang('deposit_amount'));
                    $this->excel->getActiveSheet()->SetCellValue('M2', lang('Price Group'));
                    $this->excel->getActiveSheet()->SetCellValue('N2', lang('Customer Group'));
                    $this->excel->getActiveSheet()->SetCellValue('O2', lang('ccf1'));
                    $this->excel->getActiveSheet()->SetCellValue('P2', lang('ccf2'));
                    $this->excel->getActiveSheet()->SetCellValue('Q2', lang('ccf3'));
                    $this->excel->getActiveSheet()->SetCellValue('R2', lang('ccf4'));
                    $this->excel->getActiveSheet()->SetCellValue('S2', lang('ccf5'));
                    $this->excel->getActiveSheet()->SetCellValue('T2', lang('ccf6'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $customer = $this->site->getCompanyByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $customer->company);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $customer->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $customer->email);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $customer->phone);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $customer->address);
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $customer->city);
                        $this->excel->getActiveSheet()->SetCellValue('G' . $row, $customer->state);
                        $this->excel->getActiveSheet()->SetCellValue('H' . $row, $customer->postal_code);
                        $this->excel->getActiveSheet()->SetCellValue('I' . $row, $customer->country);
                        $this->excel->getActiveSheet()->SetCellValue('J' . $row, $customer->vat_no);
                        $this->excel->getActiveSheet()->SetCellValue('K' . $row, $customer->gstn_no);
                        $this->excel->getActiveSheet()->SetCellValue('L' . $row, $customer->deposit_amount);
                        $this->excel->getActiveSheet()->SetCellValue('M' . $row, $customer->price_group_name);
                        $this->excel->getActiveSheet()->SetCellValue('N' . $row, $customer->customer_group_name);
                        $this->excel->getActiveSheet()->SetCellValue('O' . $row, $customer->cf1);
                        $this->excel->getActiveSheet()->SetCellValue('P' . $row, $customer->cf2);
                        $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $customer->cf3);
                        $this->excel->getActiveSheet()->SetCellValue('R' . $row, $customer->cf4);
                        $this->excel->getActiveSheet()->SetCellValue('S' . $row, $customer->cf5);
                        $this->excel->getActiveSheet()->SetCellValue('T' . $row, $customer->cf6);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'customers_' . date('Y_m_d_H_i_s');
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
                $this->session->set_flashdata('error', lang("no_customer_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function deposits($company_id = NULL) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $company_id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['modal_js'] = $this->site->modal_js();
        $this->data['company'] = $this->companies_model->getCompanyByID($company_id);
        $this->load->view($this->theme . 'customers/deposits', $this->data);
    }

    public function get_deposits($company_id = NULL) {
        $this->sma->checkPermissions('deposits');
        $this->load->library('datatables');
        $this->datatables
                ->select("deposits.id as id, date, amount, paid_by, CONCAT({$this->db->dbprefix('users')}.first_name, ' ', {$this->db->dbprefix('users')}.last_name) as created_by", false)
                ->from("deposits")
                ->join('users', 'users.id=deposits.created_by', 'left')
                ->where($this->db->dbprefix('deposits') . '.company_id', $company_id)
                ->add_column("Actions", "<div class=\"text-center\"><a class=\"tip\" title='" . lang("deposit_note") . "' href='" . site_url('customers/deposit_note/$1') . "' data-toggle='modal' data-target='#myModal2'><i class=\"fa fa-file-text-o\"></i></a> <a class=\"tip\" title='" . lang("edit_deposit") . "' href='" . site_url('customers/edit_deposit/$1') . "' data-toggle='modal' data-target='#myModal2'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . lang("delete_deposit") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('customers/delete_deposit/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id")
                ->unset_column('id');
        echo $this->datatables->generate();
    }

    public function add_deposit($company_id = NULL) {

        $this->sma->checkPermissions('deposits', true);

        if ($this->input->get('id')) {
            $company_id = $this->input->get('id');
        }
        $company = $this->companies_model->getCompanyByID($company_id);

        if ($this->Owner || $this->Admin) {
            $this->form_validation->set_rules('date', lang("date"), 'required');
        }
        $this->form_validation->set_rules('amount', lang("amount"), 'required|numeric');

        if ($this->form_validation->run() == true) {

            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = date('Y-m-d H:i:s');
            }
            $note = ($this->input->post('services-check')) ? 'services' : $this->input->post('note');

            $data = array(
                'date' => $date,
                'amount' => $this->input->post('amount'),
                'paid_by' => $this->input->post('paid_by'),
                'super_cash' => $this->input->post('super_price'),
                'services' => $this->input->post('services-check'),
                'note' => $note,
                'company_id' => $company->id,
                'created_by' => $this->session->userdata('user_id'),
            );

            // Updated total deposite
            $cdata = array(
                'deposit_amount' => ($company->deposit_amount + $this->input->post('amount'))
            );

            $depositLog = [
                "customer_id" => $company->id,
                "date" => $date,
                "descriptions" => "Add Amount",
                "amount" => $this->input->post('amount'),
                "cr_dr" => 'CR',
                "opening_balance" => ((bool) $company->deposit_amount ? $company->deposit_amount : 0),
                "closing_balance" => ((float) $company->deposit_amount + (float) $this->input->post('amount')),
                "created_by" => $this->session->userdata('user_id'),
            ];
        } elseif ($this->input->post('add_deposit')) {
            $this->session->set_flashdata('error', validation_errors());
            //redirect('customers');
            return redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $deposit_id = $this->companies_model->addDeposit($data, $cdata)) {

            $depositLog["transaction_details"] = json_encode(["table_name" => "sma_deposits", "where" => ["id" => $deposit_id]]);

            $this->companies_model->set_customer_wallet_log($depositLog);

            $this->session->set_flashdata('message', lang("deposit_added"));

            $OpeningBalance = ((bool) $company->deposit_amount ? $company->deposit_amount : 0);

            //$company = $this->companies_model->getCompanyByID($company_id);
            //$log = $this->companies_model->getOPCLDeposit($company->id, date('Y-m-d', strtotime($date)));

            $_SESSION['Print_Deposite_Receipt'] = [
                'status' => '1',
                'customer_Details' => $company,
                'last_deposit' => $data,
                'openingBalance' => $OpeningBalance,
            ];

            return redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['company'] = $company;
            $this->load->view($this->theme . 'customers/add_deposit', $this->data);
        }
    }

    public function edit_deposit($id = NULL) {
        $this->sma->checkPermissions('deposits', true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $deposit = $this->companies_model->getDepositByID($id);
        $company = $this->companies_model->getCompanyByID($deposit->company_id);

        if ($this->Owner || $this->Admin) {
            $this->form_validation->set_rules('date', lang("date"), 'required');
        }
        $this->form_validation->set_rules('amount', lang("amount"), 'required|numeric');

        if ($this->form_validation->run() == true) {

            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld(trim($this->input->post('date')));
            } else {
                $date = $deposit->date;
            }
            $data = array(
                'date' => $date,
                'amount' => $this->input->post('amount'),
                'paid_by' => $this->input->post('paid_by'),
                'note' => $this->input->post('note'),
                'company_id' => $deposit->company_id,
                'updated_by' => $this->session->userdata('user_id'),
                'updated_at' => $date = date('Y-m-d H:i:s'),
            );

            $cdata = array(
                'deposit_amount' => (($company->deposit_amount - $deposit->amount) + $this->input->post('amount'))
            );
        } elseif ($this->input->post('edit_deposit')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('customers');
        }

        if ($this->form_validation->run() == true && $this->companies_model->updateDeposit($id, $data, $cdata)) {
            $this->session->set_flashdata('message', lang("deposit_updated"));
            redirect("customers");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['company'] = $company;
            $this->data['deposit'] = $deposit;
            $this->load->view($this->theme . 'customers/edit_deposit', $this->data);
        }
    }

    public function delete_deposit($id) {
        $this->sma->checkPermissions(NULL, TRUE);

        if ($this->companies_model->deleteDeposit($id)) {
            echo lang("deposit_deleted");
        }
    }

    public function deposit_note($id = null) {
        $this->sma->checkPermissions('deposits', true);
        $deposit = $this->companies_model->getDepositByID($id);
        $this->data['customer'] = $this->companies_model->getCompanyByID($deposit->company_id);
        $this->data['deposit'] = $deposit;
        $this->data['page_title'] = $this->lang->line("deposit_note");
        $this->load->view($this->theme . 'customers/deposit_note', $this->data);
    }

    public function addresses($company_id = NULL) {
        $this->sma->checkPermissions('index', true);
        $this->data['modal_js'] = $this->site->modal_js();
        $this->data['company'] = $this->companies_model->getCompanyByID($company_id);
        $this->data['addresses'] = $this->companies_model->getCompanyAddresses($company_id);
        $this->load->view($this->theme . 'customers/addresses', $this->data);
    }

    public function add_address($company_id = NULL) {
        $this->sma->checkPermissions('add', true);
        $company = $this->companies_model->getCompanyByID($company_id);

        $this->form_validation->set_rules('line1', lang("line1"), 'required');
        $this->form_validation->set_rules('city', lang("city"), 'required');
        $this->form_validation->set_rules('state', lang("state"), 'required');
        $this->form_validation->set_rules('country', lang("country"), 'required');
        $this->form_validation->set_rules('phone', lang("phone"), 'required');

        if ($this->form_validation->run() == true) {

            $data = array(
                'line1' => $this->input->post('line1'),
                'line2' => $this->input->post('line2'),
                'city' => $this->input->post('city'),
                'postal_code' => $this->input->post('postal_code'),
                'state' => $this->input->post('state'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),
                'company_id' => $company->id,
            );
        } elseif ($this->input->post('add_address')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('customers');
        }

        if ($this->form_validation->run() == true && $this->companies_model->addAddress($data)) {
            $this->session->set_flashdata('message', lang("address_added"));
            redirect("customers");
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['company'] = $company;
            $this->load->view($this->theme . 'customers/add_address', $this->data);
        }
    }

    public function edit_address($id = NULL) {
        $this->sma->checkPermissions('edit', true);

        $this->form_validation->set_rules('line1', lang("line1"), 'required');
        $this->form_validation->set_rules('city', lang("city"), 'required');
        $this->form_validation->set_rules('state', lang("state"), 'required');
        $this->form_validation->set_rules('country', lang("country"), 'required');
        $this->form_validation->set_rules('phone', lang("phone"), 'required');

        if ($this->form_validation->run() == true) {

            $data = array(
                'line1' => $this->input->post('line1'),
                'line2' => $this->input->post('line2'),
                'city' => $this->input->post('city'),
                'postal_code' => $this->input->post('postal_code'),
                'state' => $this->input->post('state'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),
                'updated_at' => date('Y-m-d H:i:s'),
            );
        } elseif ($this->input->post('edit_address')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('customers');
        }

        if ($this->form_validation->run() == true && $this->companies_model->updateAddress($id, $data)) {
            $this->session->set_flashdata('message', lang("address_updated"));
            redirect("customers");
        } else {

            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['address'] = $this->companies_model->getAddressByID($id);
            $this->load->view($this->theme . 'customers/edit_address', $this->data);
        }
    }

    public function delete_address($id) {
        $this->sma->checkPermissions('delete', TRUE);

        if ($this->companies_model->deleteAddress($id)) {
            $this->session->set_flashdata('message', lang("address_deleted"));
            redirect("customers");
        }
    }

    public function getEmail() {
        $emailid = $this->input->get('emailid');
        $row = $this->companies_model->getCompanyByEmail($emailid);
        if (empty($row)) {
            echo 1;
        } else {
            echo 0;
        }
    }

    /** @return json for Customer 12-21-2019 */
    public function getGiftBalance() {
        $id = $this->input->get('id');
        $retrun_option = $this->companies_model->getGiftCard($id);
        $depositBalance = $this->companies_model->getOPCLDeposit($id);
        $response = ['giftcard' => $retrun_option,
            'opening_balance' => $depositBalance->opening_balance,
            'closing_balance' => $depositBalance->closing_balance,
        ];
        echo json_encode($response);
    }

    /**
     * Check mobile no register or not
     */
    public function checkMobileno() {
        $groupname = $_GET['groupname'];
        $mobileno = $_GET['mobileno'];
        $result = $this->site->checkMobileno($groupname, $mobileno);
        if ($result) {
            $response['status'] = "success";
        } else {
            $response['status'] = "error";
        }
        echo json_encode($response);
    }

    /**
     * Get State List Country Vise
     */
    public function getstates() {
        $country = $_GET['country'];
        $statedata = $this->site->getstates($country);

        if ($statedata) {
            $output = '<option value="">--Select State--</option>';
            foreach ($statedata as $statevalue) {
                $output .= '<option value="' . $statevalue->name . '~' . $statevalue->code . '">' . $statevalue->name . ' (' . $statevalue->code . ')</option>';
            }
            $output .= '<option value="other">Other</option>';
            $response['status'] = "success";
            $response['data'] = $output;
            echo json_encode($response);
        } else {
            $output = '<option value="">--Select State--</option>';
            $output .= '<option value="other">Other</option>';
            $response['status'] = "error";
            $response['data'] = $output;
            echo json_encode($response);
        }
    }

    /**
     * 
     * @param type $customerId
     */
    public function getdeposit($customerId) {
        $result = $this->companies_model->getDepositandGift($customerId);
        echo json_encode($result);
    }

    /**
     * Suplier Privatekey Notification
     */
    public function supplier_key() {

        $result = $this->companies_model->count_new_purchase();
        if (is_array($result)) {
            echo json_encode($result);
        } else {
            echo json_encode(['num' => 0]);
        }
    }

    public function supplier_key_accept() {

        echo $this->companies_model->set_notification_order_status($status);
    }

    /**
     * End Suplier Privatekey Notification
     */

    /**
     *  Get Depostis History
     * @param type $company_id
     *
     */
    public function depositsHistory($company_id = NULL) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $company_id = $this->input->get('id');
        }

        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
        $this->data['modal_js'] = $this->site->modal_js();
        $this->data['company'] = $this->companies_model->getCompanyByID($company_id);
        $this->load->view($this->theme . 'customers/deposits_history', $this->data);
    }

    /**
     * 
     * @param type $company_id
     */
    function get_deposits_history($company_id = NULL) {
        $this->sma->checkPermissions('deposits');
        $this->load->library('datatables');
        $this->datatables
                ->select("payments.id, payments.date, sales.invoice_no, payments.reference_no, payments.amount, payments.cc_holder as balance ", false)
                ->from("payments")
                ->join('sales', 'sales.id=payments.sale_id', 'Inner')
                ->where($this->db->dbprefix('payments') . '.paid_by', 'deposit')
                ->where($this->db->dbprefix('sales') . '.customer_id', $company_id);

        echo $this->datatables->generate();
    }

    /**
     * End Deposit History
     */

    /**
     * Reachage Amount
     */
    public function getDepositreacharge() {

        $date = $this->sma->fld(trim($this->input->get('date')));

        $customerId = $_GET['customer_id'];

        $result = $this->companies_model->getTotalReacharge($date, $customerId);
        $useddeposit = $this->companies_model->getUseddeposit($date, $customerId);
        $response = [
            'amount' => $result,
            'useddeposit' => $useddeposit,
        ];
        echo json_encode($response);
    }

      //controller for bulk deposit

      public function getBulkDeposit() {
        $this->sma->checkPermissions('add', true);
        $this->load->helper('security');
        $this->form_validation->set_rules('deposit_file', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == true) {

            if (DEMO) {
                $this->session->set_flashdata('warning', lang("disabled_in_demo"));
                redirect($_SERVER["HTTP_REFERER"]);
            }

            if (isset($_FILES["deposit_file"])) /* if($_FILES['userfile']['size'] > 0) */ {

                $this->load->library('upload');
                $config['upload_path'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/csv/';
                $config['allowed_types'] = 'xls';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload('deposit_file')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("customers");
                }

                $this->load->library('excel');
                $File = $_FILES['deposit_file']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                $reader->setReadDataOnly(true);
                $path = $File;
                $excel = $reader->load($path);

                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);
                $arrayCount = count($sheet);
                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                }

                $keys = array('name', 'phone', 'customer_group_name', 'cf1','cf2','deposit_amount','super_cash','paid_by');

                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
               
                $rw = 3;
                foreach ($final as $csv) {
                                       
                    if ($csv['name'] == '' || $csv['phone'] == '' ||  $csv['deposit_amount'] == '' ||  $csv['paid_by'] == '' ) {
                        $this->session->set_flashdata('error', "Please required Name, Phone No, deposit_amount, payment type");
                        redirect("customers");
                    }

                    $rw++;
                }
                $data= $final;

                $user = $this->session->userdata('user_id');

            }
        } elseif ($this->input->post('import')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('customers');
        }

        if ($this->form_validation->run() == true && !empty($data)) {
            if ($this->companies_model->importBulkDeposit($data,$user)) {
                $this->session->set_flashdata('message', lang("customers_added"));
                redirect('customers');
            }
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'customers/bulk_deposit', $this->data);
        }
    }
  
}
    
 

        
