<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Employees_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }
  
    public function getEmployeeTypes() {
        return $this->db->where('is_employee', 1)->get('groups')->result();
    }
    
    public function addEmployee($data = [])
    {
        if ($this->db->insert('companies', $data)) {
            $eid = $this->db->insert_id();             
            return $eid;
        }
        return false;
    }

    public function updateEmployee($id, $data = [])
    {
        $this->db->where('id', $id);
         
        if ($this->db->update('companies', $data)) {             
            return true;
        }
        return false;
    }
    
    public function getEmployeeByID($id)
    {
        $q = $this->db->get_where('companies', ['id' => $id], 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    
    public function deleteEmployee($id)
    {
        if ($this->getEmployeeSales($id)) {
            return false;
        }
        elseif ($this->getEmployeeDeliveries($id)) {
            return false;
        }
        elseif ($this->db->delete('companies', ['id' => $id])) {
            return true;
        }
        return FALSE;
    }
    
    public function getEmployeeSales($id)
    {
        $this->db->where('seller_id', $id)->from('sales');
        return $this->db->count_all_results();
    }
    
    public function getEmployeeDeliveries($id)
    {
        $this->db->where('delivery_person_id', $id)->from('deliveries');
        return $this->db->count_all_results();
    }

}
