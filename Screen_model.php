<?php
/**
 * Created by PhpStorm.
 * User: ravi
 * Date: 10/26/2017
 * Time: 9:41 AM
 */
class Screen_model extends CI_Model{

    public function __construct()
    {
        parent::__construct();
        //$this->load->language('cron');
    }
    function find($id){

        $this->db->from('division');
        $this->db->where("id",$id);
        $query = $this->db->get();
        return $query->row();
    }

    function delivered($id,$data){
        $this->db->where("id",$id);
        return $this->db->update('suspended_items',$data);
    }

    function getAllSuspendedBills($parm = array())
    {
        if($parm['id'] == 0){
            $parm['id'] = "SELECT id FROM ".$this->db->dbprefix('division');
        }

        $query = "SELECT p.divisionid,sb.kot_tokan,sb.suspend_note,sb.customer,d.name as division_name,t.name as table_name ,sbi.*,(sbi.quantity-sbi.isdelivered) as balance_quantity, c.id as category_id, c.name as category_name,
        IF(pv.name IS NULL,sbi.product_name,CONCAT  (sbi.product_name,' (',IF(sbi.note = '', pv.name, concat(pv.name,':',sbi.note)),')')) as product_name
         FROM ".$this->db->dbprefix('suspended_bills')." sb
        INNER JOIN ".$this->db->dbprefix('suspended_items')." sbi ON sbi.suspend_id = sb.id
        LEFT JOIN ".$this->db->dbprefix('product_variants')." pv ON pv.id = sbi.option_id AND pv.name IS NOT NULL
        INNER JOIN ".$this->db->dbprefix('products')." p ON p.id = sbi.product_id
        LEFT JOIN ".$this->db->dbprefix('categories')." c ON c.id = p.category_id
        INNER JOIN ".$this->db->dbprefix('division')." d ON d.id = p.divisionid
        LEFT JOIN ".$this->db->dbprefix('restaurant_tables')." t ON t.id = sb.table_id
        WHERE sb.date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) and p.divisionid in({$parm['id']}) and sbi.isdelivered != sbi.quantity
        
        ";
        if($this->pos_settings->display_category == 1)
			 $query .= " order by c.id desc ";
        $q = $this->db->query($query);
        return $q->result();
    }

    function kot(){
        $parm['id'] = 0;
        $rows = $this->getAllSuspendedBills($parm);
        $rows = $this->objToArray($rows);

        $arr = array();
        foreach($rows as $key => $item){
            $arr[$item['table_name']][$key] = $item;
        }

        ksort($arr, SORT_NUMERIC);
        //print_r($arr);exit;
        return $arr;
    }

    function objToArray($obj, &$arr){

        if(!is_object($obj) && !is_array($obj)){
            $arr = $obj;
            return $arr;
        }

        foreach ($obj as $key => $value)
        {
            if (!empty($value)){
                $arr[$key] = array();
                $this->objToArray($value, $arr[$key]);
            }else{
                $arr[$key] = $value;
            }
        }
        return $arr;
    }


      /**
     * 
     * @param type $product_id
     */
    public function getComboProduct($product_id = NULL){
        $combo = $this->db->where(['product_id'=> $product_id])->get('sma_combo_items')->result();
        foreach ($combo as $key => $val){
           $get_product =  $this->db->select('name')->where(['code' => $val->item_code])->get('sma_products')->row();
            
            $combo_items .= $get_product->name.' '.  round($val->quantity).' + '; 
        }
        return  '( '.rtrim($combo_items,' + ').' )';
    }
    
    /**
     * Check  Printer Setting Combo Product List
     * @return type
     */
    public function getComboItemShow(){
        $getprinter = $this->db->select('default_printer')->where(['setting_id'=> '1'])->get('sma_settings')->row();
        $printer_setting = $this->db->select('*')->where(['id' => $getprinter->default_printer])->get('sma_printer_bill')->row();
        return $printer_setting;  
    }
}