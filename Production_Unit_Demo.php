<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Production_Unit_Demo extends MY_Controller {

    function __construct() {

        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        
    }

    function index($warehouse_id = NULL) {
        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
       
        $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit')), array('link' => '#', 'page' => lang('Production_Unit')));
        $meta = array('page_title' => lang('Production_Unit_Demo'), 'bc' => $bc);
        $this->page_construct('production_unit/order_dispatch', $meta, $this->data);
    }
	//Order Dispatch
    function order_dispatch_demo($warehouse_id = NULL) {

        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
       
        $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit_Demo')), array('link' => '#', 'page' => lang('Production_Unit_Demo')));
        $meta = array('page_title' => lang('Order_Dispatch_demo'), 'bc' => $bc);
        $this->page_construct('production_unit/order_dispatch_demo', $meta, $this->data);
    }
	public function Procurement_Order() {

        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
       
        $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit')), array('link' => '#', 'page' => lang('Production_Unit')));
        $meta = array('page_title' => lang('Production_Unit'), 'bc' => $bc);
        $this->page_construct('production_unit/procurement_order_demo', $meta, $this->data);
    }

    public function production_manger_dashboard() {

        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
       
        $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit')), array('link' => '#', 'page' => lang('Production_Unit')));
        $meta = array('page_title' => lang('Production_Unit'), 'bc' => $bc);
        $this->page_construct('production_unit/production_manger_dashboard_Demo', $meta, $this->data);
    }
	
}
?>