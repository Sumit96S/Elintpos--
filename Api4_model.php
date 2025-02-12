<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Api4_model extends CI_Model {

    private $sales;
    public $select_fields;

    public function __construct() {
        parent::__construct();

        $this->sales = [];
    }
    
    /**
     * Add New Sales Request notification 
     * @param type $data
     * @return type
     */
    public function add_Sales_Request_Notification($data){
       
        $this->db->insert('notifications_purchases', $data);
        return ($this->db->affected_rows())?  TRUE: FALSE;
    }
    
    
    
   public function getSalesDetails($salesId ){
      $sales = $this->db->where(['id' => $salesId])->get('sales')->row();
      if($this->db->affected_rows()){
          
        $salesItems = $this->db->select('sale_items.*,product_variants.name as variants')->join('product_variants','product_variants.id = sale_items.option_id','left')->where(['sale_id'=> $salesId])->get('sale_items')->result();
   
        $sales->items = $salesItems;
        
       return $sales;
      }
      return false;
     
   }
   
   /**
    * 
    * @param type $productBarcode
    */
   public function getProdutsDetails($productBarcode){
     $productDetails =   $this->db->select('products.*,units.code as unit_code,units.name as unit_name, units.base_unit as unit_base_unit,units.operator as unit_operator,units.unit_value as unit_value,units.operation_value as unit_operation_value, categories.code as category_code, categories.name as category_name, subcategories.code as subcategories_code, subcategories.name as subcategories_name, brands.code as brand_code, brands.name as brand_name')
             ->join('units','units.id = products.unit','left')     
             ->join('categories','categories.id = products.category_id','left')
             ->join('categories as subcategories','subcategories.id = products.subcategory_id','left')
             ->join('brands','brands.id = products.brand','left')

             
             ->where_in('products.code',$productBarcode)->get('products')->result();
    $productData = [];
    foreach($productDetails as $items){
        $productvariant =  $this->db->where(['product_id'=> $items->id])->get('product_variants')->result();
         if($this->db->affected_rows()){
             $items->options = $productvariant;
         }else{
             $items->options = False;
         }
         $productData[] = $items;
     }
     
     
     return $productData;
   }


   /**
    * Manage Supplier API Key
    * @param type $supplierName
    * @param type $supplierkey
    * @return type
    */
   public function add_SupplierKey($supplierName, $data){
      
        $this->db->where(['name' => $supplierName,'group_name' => 'supplier'])->update('companies',['notification_supplier' =>$data]);
       return ($this->db->affected_rows())? TRUE : FALSE;
   }

  
   
   /**
    * Get Transfer Records
    * @return boolean
    */
   public function getTransferData(){
       $response = array();
       $transfers = $this->db->select('id,transfer_no, date, to_warehouse_code, transfer_no, status ')->get('sma_transfers')->result();
       if($this->db->affected_rows()){
           foreach($transfers as $transfer){
              $transfer_items = $this->getAllTransferItems($transfer->id, $transfer->status);
              if($transfer_items){
                  foreach($transfer_items as $items){
                    $response[]=[
                        "TransactionDate" => $transfer->date,
                        "ReferenceNo"     => $transfer->transfer_no,
                        "StoreNo"         => $transfer->to_warehouse_code,
                        "ItemId"          => $items->product_code,
                        "VariantId"       => ($items->variant?$items->variant : ''),
                        "Quantity"        => (float) $items->quantity,
                    ];
                  }
              }
           }
           return $response;
       }
       return false;
   }
   
   /**
    * 
    * @param type $transfer_id
    * @param type $status
    * @return type
    */
     public function getAllTransferItems($transfer_id, $status) {
        if ($status == 'completed') {
            $this->db->select('purchase_items.*, product_variants.name as variant, products.unit')
                    ->from('purchase_items')
                    ->join('products', 'products.id=purchase_items.product_id', 'left')
                    ->join('product_variants', 'product_variants.id=purchase_items.option_id', 'left')
                    ->group_by('purchase_items.id')
                    ->where('transfer_id', $transfer_id);
        } else {
            $this->db->select('transfer_items.*, product_variants.name as variant, products.unit')
                    ->from('transfer_items')
                    ->join('products', 'products.id=transfer_items.product_id', 'left')
                    ->join('product_variants', 'product_variants.id=transfer_items.option_id', 'left')
                    ->group_by('transfer_items.id')
                    ->where('transfer_id', $transfer_id);
        }
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

   /**
     *
    * @param type $sale_id
    * @return \type Purchase Notification
     * @param type $sale_id
     * @return type
     */
   public function getSalesItemsList($sale_id){
        $salesItems = $this->db->select('sale_items.sale_id,sale_items.product_code,sale_items.product_name,sale_items.quantity,sale_items.unit_price ,product_variants.name as variant_name')
                        ->join('product_variants', 'product_variants.id = sale_items.option_id', 'left')
                        ->where(['sale_id' => $sale_id])->get('sale_items')->result_array();

        return $salesItems;
   } 
           

        
}