<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Help_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

        
    /**
     * Get State List
     * @return type
     */
    public function getState(){
       $result =  $this->db->order_by('name','ASC')->get('state_master')->result();
       return $result;
        
    }
    
}