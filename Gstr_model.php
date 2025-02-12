<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Gstr_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }
    
    public function getGstrReport($param){ 
            $this->db->select('id,date,reference_no,grand_total,product_tax,order_tax');
        $s_date = isset($param['s_date'])?$param['s_date']:NULL;
        if(!empty($param['s_date'])):
        	$param['s_date'] = $param['s_date'].'00';
        endif;
        $e_date = isset($param['e_date'])?$param['e_date']:NULL;
        if(!empty($param['e_date'])):
        	$param['e_date'] = $param['e_date'].'00';
        endif;
        if ($s_date) {
            $this->db->where($this->db->dbprefix('sales').'.date BETWEEN "' . $s_date . '" and "' . $e_date . '"');
        }
         $this->db->order_by('id', 'desc');
        $q = $this->db->get('sales');
        
       if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
             	$row->items =  $this->salesItemsWithTax($row->id);
                $data[] = $row;
            }
          
            return $data;
        }
        return FALSE;
    }
     
    public function salesItemsWithTax($sale_id)
    {
    
        $this->db->select('sale_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, product_variants.name as variant')
            ->join('products', 'products.id=sale_items.product_id', 'left')
            ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
            ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
            ->group_by('sale_items.id')
            ->order_by('id', 'asc');
          $this->db->where('sale_id', $sale_id);
         
        $q = $this->db->get('sale_items');
      
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $row->ItemTax = $this->salesItemsTax($row->id);
                $data[] = (array)$row;
            }
            return $data;
        }
        return FALSE;
    }
     public function salesItemsTax($item_id)
    {
        $this->db->where('item_id', $item_id);
        $q = $this->db->get('sales_items_tax');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->attr_code] = (array)$row;
            }
            return $data;
        }
        return FALSE;
    }
}
