<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Event_model extends CI_Model
{
    public $eventDate = '';
    
    public function __construct()
    {   $this->eventDate = date("m-d");
        parent::__construct();
    }

    public function getAllCustomerFromEvent($date=NULL,$idArr=NULL)
    { 
        $this->eventDate = isset($date) && !empty($date)?$date:$this->eventDate;
        
         $where = "(  DATE_FORMAT(dob,'%m-%d') = '".$this->eventDate."' "
                . "OR DATE_FORMAT(anniversary,'%m-%d') =  '".$this->eventDate."' "
                . "OR DATE_FORMAT(dob_father,'%m-%d') =  '".$this->eventDate."' "
                . "OR DATE_FORMAT(dob_mother,'%m-%d') =  '".$this->eventDate."' "
                . "OR DATE_FORMAT(dob_child1,'%m-%d') =  '".$this->eventDate."' "
                . "OR DATE_FORMAT(dob_child2,'%m-%d') =  '".$this->eventDate."')";
        if(is_array($idArr) && count($idArr) > 0){
            $where = $where.' and id in ('. implode(',', $idArr).')';
        }
        $this->db->select("id,name,email,phone,DATE_FORMAT(dob,'%m-%d')  as dob ,"
                . " DATE_FORMAT(anniversary,'%m-%d')  as anniversary,"
                . " DATE_FORMAT(dob_father,'%m-%d')  as dob_father,"
                . " DATE_FORMAT(dob_mother,'%m-%d')  as  dob_mother,"
                . " DATE_FORMAT(dob_child1,'%m-%d')  as dob_child1,"
                . " DATE_FORMAT(dob_child2,'%m-%d')  as  dob_child2")
                 ->where( array('group_id' => 3))
                 ->where( $where);
            
            
         
        $q = $this->db->get( "companies"); 
    // $this->db->last_query();
        $returnArr = $returnArr['users'] =array()  ;
        $returnArr['dob'] = $returnArr['anniversary'] = $returnArr['dob_father'] =  array()  ;
        $returnArr['dob_mother'] = $returnArr['dob_child1'] = $returnArr['dob_child2'] =  array()  ;
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                 ($row->dob==$this->eventDate)?array_push($returnArr['dob'], $row->id):''; 
                 ($row->anniversary==$this->eventDate)?array_push($returnArr['anniversary'], $row->id):''; 
                 ($row->dob_father==$this->eventDate)?array_push($returnArr['dob_father'], $row->id):''; 
                 ($row->dob_mother==$this->eventDate)?array_push($returnArr['dob_mother'], $row->id):''; 
                 ($row->dob_child1==$this->eventDate)?array_push($returnArr['dob_child1'], $row->id):''; 
                 ($row->dob_child2==$this->eventDate)?array_push($returnArr['dob_child2'], $row->id):''; 
                 $returnArr['users'][$row->id] = array('id'=>$row->id,'name'=>$row->name,'phone'=>$row->phone,'email'=>$row->email,);
            }
            return $returnArr;
        }
        return FALSE;
    }

     
}
