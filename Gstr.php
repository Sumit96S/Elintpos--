<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Gstr extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
	ini_set('memory_limit', '512M'); 
	
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }

        $this->lang->load('reports', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('gstr_model');
        

    }

    public function index(){
        $this->sma->checkPermissions('sales');
        
        $this->form_validation->set_rules('start_date', $this->lang->line("From Date"), 'trim|required');
        $this->form_validation->set_rules('end_date', $this->lang->line("End date"), 'trim|required'); 
        
        if ($this->form_validation->run() == TRUE) {
            $param['s_date'] = $this->input->post('start_date') ?$this->sma->fld( $this->input->post('start_date')) : NULL;
            $param['e_date'] = $this->input->post('end_date') ?$this->sma->fld(  $this->input->post('end_date') ): NULL;
            $res = $this->gstr_model->getGstrReport($param); 
            $this->data['result'] = $res; 
        }
         
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['billers'] = $this->site->getAllCompanies('biller');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('reports'), 'page' => lang('reports')), array('link' => '#', 'page' => lang('sales_report')));
        $meta = array('page_title' => lang('sales_report'), 'bc' => $bc);
        $this->page_construct('gstr/index', $meta, $this->data);
    }
    
}
