<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Access_denied extends MY_Controller
{

    function __construct()
    {
        parent::__construct();
 
    }

    public function index()
    {
        $this->load->view('default/views/service_off');     
    }


}
