<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Help extends MY_Controller
{

    function __construct()
    {
        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->Customer || $this->Supplier) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->load->model('help_model');
    }
 
    
    /**
     * Show State List
     */
    public function statelist(){
        $this->data['stateList'] = $this->help_model->getState();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['action'] = $action;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('State List')));
        $meta = array('page_title' => lang('State List'), 'bc' => $bc);
        $this->page_construct('help/statecode', $meta, $this->data);
    }
    
}
