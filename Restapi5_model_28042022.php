<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Restapi5_model extends CI_Model {
    
    private $imagePath;
    
    public function __construct() {

        parent::__construct();
            
        $this->load->database();
        
        $this->imagePath = base_url('assets/mdata/'.$this->Customer_assets.'/uploads/');        
    }
    
    public function get_system_settings() {
        
       $data = $this->db->get_where('settings' ,['setting_id'=>1])->result();
       
       return $data[0];
    }
    
    public function get_store_settings() {
        
        $store_setting_fields = 'company_name,mobile_app_name,biller_id,warehouse_id,overselling,rounding,cod AS cash_on_delivery,online_payment,free_delivery_above_amount,active_offer,suport_email,suport_phone,logo,return_within_days';
        
        $data = $this->db->select( $store_setting_fields )->get_where('webshop_settings' ,['id'=>1])->result();
        
        return $data[0];
    }
   
    public function get_company($group_name = null, $id = null) {
        
        $where  = null;
        $select = 'id,group_id,group_name,customer_group_id,customer_group_name,name,company,vat_no,pan_card,address,city,state,state_code,postal_code,country,phone,email,logo,award_points,deposit_amount,price_group_id,price_group_name,is_synced,gstn_no,offline_reff_id,updated_at';
                
        if($group_name != null) $where['group_name'] = $group_name;
        if(!empty($id)) $where['id'] = $id;
        
        if(!empty($where)){
            if(!empty($id)) {
                $data = $this->db->select($select)->get_where("companies", $where)->row_array();
            } else {
                $data = $this->db->select($select)->get_where("companies", $where)->result();
            }
        } else {
            $data = $this->db->select($select)->get("companies")->result();
        } 
        
        return $data;
    }
        
    public function get_catlogs() {
        
        $products = $this->get_products();
           
        $data['products']           = $products['data'];
        $data['categories']         = $this->get_categories();
        $data['brands']             = $this->get_brands();
        $data['product_variants']   = $this->_get_product_variants();
        $data['product_images']     = $this->_get_product_images();
     
        return $data;
    }
    
    public function get_catlog_products($product_id=null, $filters=null) {
        
        $storeSettings = $this->get_store_settings();        
        
        if((bool)$product_id == false) {       
            $count_all = $this->get_products($product_id, $filters, true);
        }
        
        $result = $this->get_products($product_id, $filters );        
        
        $products   = $result['data'];
        $count      = $result['count'];
        
        $request = $result;
        $request['count_all']  = $count_all;
                
        
        if((bool)$count){
            
            $shopping_cart_items = $wishlist_items = $customer_id = false;
            if(isset($filters['customer_id']) && (bool)$filters['customer_id']) {
                $customer_id = $filters['customer_id'];
                $shopping_cart_items = $this->_get_customer_shopping_cart_items($filters['customer_id']);
                $wishlist_items = $this->_get_customer_wishlist_items($filters['customer_id']);
            }
        
            foreach ($products as $key => $product) {                               
                
                $variants = $this->_get_product_variants($product->product_id);
                                
                if(!$storeSettings->overselling) {
                    $product_stocks = $this->_get_product_stocks($storeSettings->warehouse_id, $product->product_id);
                                        
                    if(!empty($variants) && count($variants)){
                        
                        if($product->storage_type == 'packed') {
                            $variant_stocks = $this->_get_products_variants_stocks($storeSettings->warehouse_id, $product->product_id);
                        }
                    
                        foreach ($variants as $key => $variant) {
                            
                            if($product->storage_type == 'loose'){
                                $variant->warehouse_stocks = $product_stocks->quantity / (($variant->unit_quantity == 0) ? 1 : $variant->unit_quantity);
                            } else {
                                $variant->warehouse_stocks = $variant_stocks[$product->product_id][$variant->id]->quantity;
                            }
                            
                        }//end foreach
                    }
                }
                
                //Add Product Tax Amount In Product Price if tax amount not include in price.
                if($product->tax_method_type === 'excluded' && (float)$product->tax_rate > 0 && (bool)$product->product_price){
                    
                    $product->product_unit_price = (float)$product->product_price + (float)($product->product_price * ((float)$product->tax_rate / 100)); 
                
                } else {
                    
                    $product->product_unit_price = $product->product_price;
                }
                
                //check products mrp or set.
                $product->product_mrp = ((bool)$product->product_mrp && ($product->product_mrp >= $product->product_unit_price) ) ? $product->product_mrp : $product->product_unit_price; 
                
                //check active promotion and apply promotion price to unit price.
                if((bool)$product->promotion && $product->promotion_start_date <= date('Y-m-d') && $product->promotion_end_date >= date('Y-m-d')  ){
                    
                    $product->product_unit_price = ((float)$product->promotion_price < (float)$product->product_unit_price) ? $product->promotion_price : $product->product_unit_price;
                } else {
                    $product->promotion = false;
                    unset($product->promotion_price);
                    unset($product->promotion_start_date);
                    unset($product->promotion_end_date);
                }
                
                unset($product->product_quantity);
                unset($product->product_unit_id);
                unset($product->in_eshop);
                unset($product->is_active);
                
                $product->warehouse_stocks = $product_stocks[0]->quantity;
                
                $data[$product->product_id]['product'] = $product;
                                
                if(count($variants)){
                    foreach ($variants as $variant) {
                        if($product->tax_method_type === 'excluded' && (bool)$variant->price){
                            
                            $variant->unit_price = ((float)$variant->price + (float)($variant->price * ((float)$product->tax_rate / 100))) + $product->product_unit_price; 
                        } else {
                            $variant->unit_price = (float)$variant->price + (float)$product->product_unit_price;
                        }
                        
                        //If Customer is login then check product variant exists in cart and wishlist.
                        if((bool)$customer_id){
                            $variant->exist_in_cart = $variant->exist_in_wishlist = false;
                            if(isset($shopping_cart_items[$product->product_id]) && isset($shopping_cart_items[$product->product_id][$variant->id])){
                                $variant->exist_in_cart = true;
                            }
                            if(isset($wishlist_items[$product->product_id]) && isset($wishlist_items[$product->product_id][$variant->id])){
                                $variant->exist_in_wishlist = true;
                            }
                        }
                    }
                } else {
                    unset($product->primary_variant);
                    
                    //If Customer is login then check product exists in cart and wishlist
                    if((bool)$customer_id){
                        $product->exist_in_cart = $product->exist_in_wishlist = false;
                        if(isset($shopping_cart_items[$product->product_id])){
                            $product->exist_in_cart = true;
                        }
                        if(isset($wishlist_items[$product->product_id])){
                            $product->exist_in_wishlist = true;
                        }
                    }
                }
                
                $data[$product->product_id]['variants'] = $variants;
                
                if($product_id == $product->product_id) {
                    
                    $photos = $this->_get_product_images($product->product_id);
                    
                    if(count($photos)){
                        foreach ($photos as $photo) {
                            unset($photo->id);
                            unset($photo->product_id);
                            unset($photo->variant_id);                            
                            $images[] = $photo;
                        }
                        $data[$product->product_id]['product_images'] = $images;
                    }

                    $data[$product->product_id]['product_reviews'] = $this->get_product_reviews($product->product_id, 5);
                    
                }                
            }//End products loop
            
            $request['data']  = $data;
            
            return $request;
        } else {
           return false;
        }
        
        
    }
    
    public function get_products($product_id=null, $filters=null, $only_count=false) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
                
        if((bool)$only_count){
            
            $product_fields = "count(p.id) AS count_all";            
        } else {
            
            $product_fields = "p.id AS product_id, p.code AS product_code, p.name AS product_name, p.unit AS product_unit_id, p.price AS product_price, p.mrp AS product_mrp, p.category_id, p.subcategory_id, p.brand AS brand_id, p.quantity AS product_quantity, p.tax_rate AS tax_rate_id, p.tax_method ,p.type AS product_type, p.promotion, p.promo_price AS promotion_price, DATE(p.start_date) AS promotion_start_date, DATE(p.end_date) AS promotion_end_date, p.sale_unit AS sale_unit_id, p.is_featured, p.storage_type, p.ratings_avarage, p.ratings_count, p.comments_count AS ratings_comments_count, p.primary_variant, p.in_eshop, p.is_active,";
            $product_fields .= "CONCAT('$imagePath' ,p.image) AS image_big, CONCAT('$smallImagePath',p.image) AS image_small,";

            //Fetch Single Product Descriptions
            if((bool)$product_id) {            
                $product_fields .= "p.details AS short_details, p.product_details, ";
            }

            $product_fields .= "u.name AS sale_unit_name, u.code AS sale_unit_code, c.name AS category_name, sc.name AS sub_category_name, b.name AS brand_name, t.rate AS tax_rate, t.name AS tax_name ";
        }//end else
        
        $this->db->select($product_fields);
        $this->db->from('products AS p');
        $this->db->join('units AS u', "u.id=p.sale_unit", "left");
        $this->db->join('categories AS c', "c.id=p.category_id", "left");
        $this->db->join('categories AS sc', "sc.id=p.subcategory_id", "left");
        $this->db->join('brands AS b', "b.id=p.brand", "left");
        $this->db->join('tax_rates AS t', "t.id=p.tax_rate", "left");
        
        $objDB = $this->db->where(['p.in_eshop'=>1, 'p.is_active'=>1]);
        
        if($product_id != null) {
           $objDB->where(['p.id' => $product_id]);
        } 
        elseif($filters != null) {
            
            if(isset($filters['is_featured']) && (bool)$filters['is_featured'] ){
                
                $objDB->where(['p.is_featured' => 1]);
            }
            elseif(isset($filters['is_promotion']) && (bool)$filters['is_promotion'] ){
                $date = date('Y-m-d');
                $objDB->group_start();
                    $objDB->where(['p.promotion' => 1]);
                    $objDB->where('(p.start_date) <=', $date);
                    $objDB->where('(p.end_date) >=', $date);
                $objDB->group_end();
            }            
            elseif(isset($filters['top_rattings']) && (bool)$filters['top_rattings'] ){
                
                $objDB->where('p.ratings_avarage >', '0');
                $objDB->order_by('p.ratings_avarage', 'desc');
            }
            elseif(isset($filters['top_sellings']) && (bool)$filters['top_sellings'] ){
                
                $topitemd = $this->get_top_sales($filters['items_per_page']);
                $objDB->where_in('p.id', $topitemd);
                
            } else {
            
                if(isset($filters['keyword']) && $filters['keyword'] != '' && strlen($filters['keyword']) >= 3 ){
                    
                    $objDB->group_start();
                        $objDB->like('p.name', $filters['keyword'], 'both');    // Produces:  LIKE '%keyword%' ESCAPE '!'
                        $objDB->or_like('p.code', $filters['keyword'], 'none');     // Produces: LIKE 'keyword' ESCAPE '!'
                        $objDB->or_like('p.product_details', $filters['keyword'], 'both'); // Produces:  LIKE '%keyword%' ESCAPE '!'
                        $objDB->or_like('b.name', $filters['keyword'], 'before');      // Produces:  LIKE '%keyword' ESCAPE '!'
                        $objDB->or_like('c.name', $filters['keyword'], 'before');      // Produces:  LIKE '%keyword' ESCAPE '!'
                    $objDB->group_end();                
                    
                } else {
                    
                    if(isset($filters['category_id']) && (bool)$filters['category_id'] && is_numeric($filters['category_id'])){

                        $objDB->where(['p.category_id' => $filters['category_id'] ]);
                    }
                    if(isset($filters['subcategory_id']) && (bool)$filters['subcategory_id'] && is_numeric($filters['subcategory_id']) ){

                        $objDB->where(['p.subcategory_id' => $filters['subcategory_id'] ]);
                    }
                    if(isset($filters['brand_id']) && (bool)$filters['brand_id'] && is_numeric($filters['brand_id'])){

                        $objDB->where(['p.brand' => $filters['brand_id']]);
                    }
                    
                }//end else keyword
            
            }//end else
            
            
            if(!(bool)$only_count){
                if(isset($filters['items_per_page']) && (bool)$filters['items_per_page'] && is_numeric($filters['items_per_page'])){

                    $pageno = isset($filters['page_number']) && !empty($filters['page_number']) ? $filters['page_number'] : 1; 
                    $itemsPerPage = $filters['items_per_page'];

                    $offset = ( $pageno - 1 ) * $itemsPerPage;

                    $objDB->limit( $itemsPerPage, $offset);
                } else {
                    $objDB->limit( 8 ); 
                }
            }
            
        }//end elseif $filters
                
        $data = $objDB->get()->result();
        
        if((bool)$only_count){
            //Get Count Without Limit
            return $data[0]->count_all;
            
        } else {
            $count = count($data);
            $result['count'] = $count;
            $products = [];

            if((bool)$count){

                foreach ($data as $key => $row) {

                    $row->tax_method_type = ($row->tax_method) ? 'excluded' : 'included';
                    $product = (array)$row;
                    ksort($product);
                    $products[] = (object)$product;
                }        
            }
            
            $result['data'] = $products;
            
            return $result;
        }
       
    }
    
    public function get_product_by_code($product_code=null) {
        
        if($product_code===null) { return false; }
        
        $product_fields = "p.id AS product_id, p.code AS product_code, p.name AS product_name, p.price AS product_price, p.mrp AS product_mrp, p.category_id, p.subcategory_id, p.brand AS brand_id, p.tax_rate AS tax_rate_id, p.tax_method ,p.type AS product_type, p.sale_unit AS sale_unit_id, p.storage_type, p.primary_variant, p.weight, p.hsn_code,";
        $product_fields .= "u.name AS sale_unit_name, u.code AS sale_unit_code"; 
        
        $this->db->select($product_fields);
        $this->db->from('products AS p');
        $this->db->join('units AS u', "u.id=p.sale_unit", "left");
        
        $this->db->where(['p.code' => $product_code]);
        
        $data = $this->db->get()->result();
        
        return $data[0];
        
    }
    
    public function get_variant_by_id($variant_id=null) {
        
        if($variant_id == null) { return false; }
        
        $data = $this->db->where(['id'=>$variant_id])->get('product_variants')->result();
        
        return $data[0];
    }
    
    public function get_top_sales($limit=null){
        
        $query = "SELECT `product_id`, sum(`quantity`) total_sale FROM `sma_sale_items` GROUP BY `product_id` ORDER BY `total_sale` DESC ";
                
        if((bool)$limit) {
            $query .= " LIMIT $limit ";
        }
        
        $result = $this->db->query($query)->result();
        
        if($result){
            foreach ($result as $key => $product) {
                $topSales[]= $product->product_id;
            }
            return $topSales;
        } else {
            return false;
        }
    }


    private function _get_product_variants($product_id=null) {
        $vdata = [];
         
        if($product_id) {
            $data = $this->db->get_where("product_variants", ['product_id' => $product_id])->result();
            if(!empty($data)){
                foreach ($data as $key => $variant) {
                    unset($variant->cost);
                    unset($variant->up_price);
                    $vdata[] = $variant;
                }
            }
            return $vdata;
        } else {
            $data = $this->db->get("product_variants")->result();
            if(!empty($data)){
                foreach ($data as $key => $variant) {
                    $vdata[$variant->product_id][] = $variant;
                }
            }
            return $vdata;
        }
        return false;
    }
    
    private function _get_product_images($product_id=null) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "id,product_id,variant_id,CONCAT('$imagePath' ,photo) AS image_big, CONCAT('$smallImagePath',photo) AS image_small";
        
        if($product_id != null) {
            $data = $this->db->select($select_fields)->get_where("product_photos", ['product_id'=>$product_id])->result();
        } else {
            $data = $this->db->select($select_fields)->get("product_photos")->result();
        }
        $photoData = null;
        if(!empty($data)){
            foreach ($data as $key => $photo) {
                if($product_id){
                    $photoData[] = $photo;
                } else {
                    $photoData[$photo->product_id][] = $photo;
                }
            }
        }
        return $photoData;
    }
    
    private function _get_product_stocks($warehouse_id=null, $product_id=null){
        
        $data = null;
        
        if($warehouse_id != null) {
            if($product_id != null) {
                $data = $this->db->get_where("sma_warehouses_products", ['warehouse_id'=>$warehouse_id, 'product_id'=>$product_id])->result();
            } else {
                $data = $this->db->get_where("sma_warehouses_products", ['warehouse_id'=>$warehouse_id])->result();
            }            
            return $data;
        }
        
        return false;
    }
    
    private function _get_products_variants_stocks($warehouse_id=null, $product_id=null, $variant_id=null){
        
        $vdata = null;
        
        if($warehouse_id != null) {
            
            if($variant_id != null) {
                $data = $this->db->get_where("warehouses_products_variants", ['warehouse_id'=>$warehouse_id, 'product_id'=>$product_id, 'option_id'=>$variant_id])->result_array();
            } elseif($product_id != null) {
                $data = $this->db->get_where("warehouses_products_variants", ['warehouse_id'=>$warehouse_id, 'product_id'=>$product_id])->result();
            } else {
                $data = $this->db->get_where("warehouses_products_variants", ['warehouse_id'=>$warehouse_id])->result();
            }
            
            if($data){
                if($variant_id != null) {
                    $vdata = $data;
                } else {
                    foreach ($data as $key => $variant) {                
                        $vdata[$variant->product_id][$variant->option_id] = $variant;
                    }
                }
            } 
        }
        
        return $vdata;
    }
    
   
    public function get_customer_views_products($customer_id=null, $limits=null) {
        
        if($customer_id !== null){
            
            $imagePath = $this->imagePath;
            $smallImagePath = $this->imagePath . 'thumbs/';
        
                $this->db->select("v.product_id, v.visits_count, p.name AS product_name, p.code AS product_code, CONCAT('$imagePath' ,p.image) AS image_big, CONCAT('$smallImagePath',p.image) AS image_small, p.ratings_avarage, p.ratings_count");
                $this->db->from('webshop_recently_viewed AS v');
                $this->db->join('products AS p', "p.id=v.product_id", 'left');
                $this->db->where(['v.user_id'=>$customer_id]);
                
                if($limits != null) {
                    $this->db->limit($limits);
                }
                
                $this->db->order_by('v.updated_at', 'desc');
                
            $data = $this->db->get()->result();
            
            return $data;            
        }
        
        return false;
    }
    
    public function add_customer_views_products($data){
        
        if(is_array($data)) {
            
            $result = $this->db->select('id')->where($data)->get('webshop_recently_viewed')->result();
            
            if($result){
                $row_id = $result[0]->id; 
                $this->db->query("UPDATE `sma_webshop_recently_viewed` SET `visits_count` = `visits_count`+1 WHERE `id`='$row_id' ");
                return $this->db->affected_rows();
            } else {
                $this->db->insert('webshop_recently_viewed', $data);
                return $this->db->insert_id(); 
            }                        
        }
        
        return false;
        
    }
    
    
    
    
    public function set_customer($data) {
        
        $this->db->insert('companies', $data);
        
        return $this->db->insert_id();
    }
    
    public function update_customer($customer_id, $data) {
        
        $this->db->where(['id' => $customer_id])->update('companies', $data);
        
        return $this->db->affected_rows();
    }
    
    public function get_customer_by_mobile_number($mobile){
       
        $data = $this->db->select('id,name,phone,email,password')->where(['phone'=>$mobile, 'group_name'=>'customer'])->get('companies')->result();
        
        return $data;
    }
    
    
    public function get_categories($category_id = null) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "id,code,name,CONCAT('$imagePath',image) AS image_big,CONCAT('$smallImagePath',image) AS image_small,parent_id";
        
        $this->db->select($select_fields);
        $this->db->where(['in_eshop'=>1, 'is_active'=>1]);
        
        if($category_id != null) {            
            $this->db->where(['id'=>$category_id]);            
        } else {
            $this->db->where(['parent_id'=>0]); 
        }
        
        $data = $this->db->get("categories")->result();
        
        if($category_id) {
            return $data[0];           
        } else {            
            return $data;             
        } 
    }
    
    public function get_subcategories($parent_id = null) {
        
        if((bool)$parent_id){
            
            $imagePath = $this->imagePath;
            $smallImagePath = $this->imagePath . 'thumbs/';
            
            $select_fields = "id,code,name,CONCAT('$imagePath',image) AS image_big,CONCAT('$smallImagePath',image) AS image_small,parent_id";
            
            $data = $this->db->select($select_fields)->where(['parent_id'=>$parent_id, 'is_active'=>1, 'in_eshop'=>1])->get("categories")->result();
            
            return ((bool)$data) ? $data : array();
        }
        
        return false;
    }
    
    public function get_brands($id=null) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "id,code,name,CONCAT('$imagePath' ,image) AS image_big, CONCAT('$smallImagePath',image) AS image_small,updated_at";
        
        if($id != null) {
            $data = $this->db->select($select_fields)->where(['id'=>$id])->get("brands")->row_array();
        } else {
            $data = $this->db->select($select_fields)->get("brands")->result();
        }
        
        return ((bool)$data) ? $data : false;  
    }
    
    
/*
 * Customer Addresses CURD Action Begin
 */
    public function get_customer_addresses($customer_id=null, $address_id=null, $limit=null) {
        
        $select_fields = 'id AS address_id,company_id AS customer_id,company_name,address_name,line1,line2,city,postal_code,state,country,phone,email_id,state_code,is_default';
        
        $this->db->select($select_fields);
                       
        if($customer_id != null){
            $this->db->where(['company_id' => $customer_id]);
        }

        if($address_id != null) {
            $this->db->where(['id' => $address_id]);
        } 
        
        $this->db->order_by('is_default', 'desc');
        
        if($limit){
            $this->db->limit($limit);
        }
        
        $data = $this->db->get("addresses")->result();
         
        if($data) {
            if($address_id) { return $data[0]; } else { return $data; } 
        } else {
            return false;
        }
    }
    
    public function add_customer_address($address_data) {
        
        if($address_data != null) {
            
            $this->db->insert("addresses", $address_data);
                
            return $this->db->insert_id();
        }
        
        return false;
    }
    
    public function update_customer_address($address_id , $address_data) {
        
        if($address_id != null) {
            
            $this->db->where(['id' => $address_id])->update("addresses", $address_data);
                
            return $this->db->affected_rows();
        }
        
        return false;
    }
    
    public function delete_customer_address($address_id) {
        
        if($address_id != null) {
            
            $this->db->where(['id' => $address_id])->delete("addresses");
                
            return $this->db->affected_rows();
        }
        
        return false;
    }
/*
 * // Customer Addresses CURD Action End
 */   
    
    
    private function _get_units(){
        $select_fields = "id,code,name,base_unit,operator,unit_value,operation_value";
        $data = $this->db->select($select_fields)->get('units')->result();
        if($data){
            foreach ($data as $key => $row) {
                $units[$row->id] = $row;
            }
        }
        return $units;
    }
    
    public function get_payment_methods(){
        $select_fields = "pgateway_title,pgateway_testing,pgateway_production";
        $data = $this->db->select($select_fields)->where(['is_active'=>1])->get('payment_gateways')->result();
        if($data){
            foreach ($data as $key => $row) {
                
                $methods[$row->pgateway_title]['testing'] = $row->pgateway_testing;
                $methods[$row->pgateway_title]['production'] = $row->pgateway_production;
            }
            return $methods;
        }
        return false;
    }
    
    
    public function add_order(array $order, array $order_items) {

        if ($this->db->insert('orders', $order)) {
            $order_id = $this->db->insert_id();
            $now = date('Y-m-d');
            //Get formated Invoice No
            $order_no = $this->sma->invoice_format($order_id, $now);
            //Update formated invoice no
            $this->db->where(['id' => $order_id])->update('orders', ['invoice_no' => $order_no]);

            if ($this->site->getReference('ordr') == $order['reference_no']) {
                $this->site->updateReference('ordr');
            }

            foreach ($order_items as $item) {

                $item['sale_id'] = $order_id;

                $this->db->insert('order_items', $item);
            }

            return $order_id;
        }
        return false;
    }
    
    public function order_canceled($order_id, $orderItems) {
        
        $this->db->where(['id'=>$order_id])->update('orders', ['sale_status'=>'canceled', 'delivery_status'=>'canceled']);
        
        if($orderItems){
            foreach ($orderItems as $item) {
                $this->db->where(['id'=>$item['order_item_id']])->update('order_items', ['delivery_status'=>'canceled']);
            }
        }
        
        return $this->db->affected_rows();
    }
    
    public function get_customer_orders($customer_id=null, $status=null) {
        
        if(!(bool)$customer_id) { return false; }
        
        $data = false;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $selectFields .= "o.id AS order_id, o.customer_id, o.date AS order_date, o.reference_no, o.warehouse_id, o.sale_status, o.payment_method, o.payment_status, o.grand_total, o.note, ";
        $selectFields .= "oi.id AS order_item_id, oi.product_id, oi.product_code, oi.product_name, oi.option_id AS variant_id, oi.quantity, oi.unit_price, oi.mrp, oi.discount, oi.subtotal, oi.delivery_status, ";
        $selectFields .= " CONCAT('$smallImagePath',p.image) AS image_small ";
         
        $this->db->select($selectFields);
        $this->db->from('orders AS o');
        $this->db->join('order_items AS oi', 'oi.sale_id = o.id', 'right');
        $this->db->join('products AS p', 'p.id = oi.product_id', 'left');                
        $this->db->where(['o.customer_id'=>$customer_id]);
        if((bool)$status){
            $this->db->where(['o.sale_status' => $status]);
        } else {
            $this->db->where(['o.sale_status != ' =>'completed']);
        }
        $this->db->order_by('o.date', 'desc');
        
        $orders = $this->db->get()->result();
        
        if($orders){
            foreach ($orders as $order) {
                
                $dataOrders[$order->order_id] = [
                    'order_id'       => $order->order_id,
                    'customer_id'    => $order->customer_id,
                    'order_date'     => $order->order_date,
                    'reference_no'   => $order->reference_no,
                    'warehouse_id'   => $order->warehouse_id,
                    'order_statue'   => $order->sale_status,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'grand_total'    => $order->grand_total,
                    'order_note'     => $order->note,
                ];
                
                $orderItems[$order->order_id][] = [
                    'order_item_id' => $order->order_item_id,
                    'product_id'    => $order->product_id,
                    'product_code'  => $order->product_code,                                    
                    'variant_id'    => $order->variant_id,
                    'quantity'      => $order->quantity,
                    'unit_price'    => $order->unit_price,
                    'mrp'           => $order->mrp,   
                    'discount'      => $order->discount,
                    'subtotal'      => $order->subtotal,
                    'delivery_status' => $order->delivery_status,
                    'image_small'   => $order->image_small,
                ];
            }
            
            foreach ($dataOrders as $oid => $dataOrder) {
                $dataOrder['items'] = $orderItems[$oid];
                $data[] = $dataOrder;
            }
        }
        
        return $data;
    }
    
    
    public function get_customer_sales($customer_id=null, $duration=null) {
        
        if(!(bool)$customer_id) { return false; }
        
        $data = false;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $selectFields .= "s.id AS sale_id, s.customer_id, s.invoice_no, s.date AS sale_date, s.reference_no, s.warehouse_id, s.sale_status, s.payment_method, s.payment_status, s.grand_total, s.note, ";
        $selectFields .= "si.id AS sale_item_id, si.product_id, si.product_code, si.product_name, si.option_id AS variant_id, si.quantity, si.unit_price, si.mrp, si.discount, si.subtotal, si.delivery_status, ";
        $selectFields .= " CONCAT('$smallImagePath',p.image) AS image_small ";
         
        $this->db->select($selectFields);
        $this->db->from('sales AS s');
        $this->db->join('sale_items AS si', 'si.sale_id = s.id', 'right');
        $this->db->join('products AS p', 'p.id = si.product_id', 'left');                 
        //$this->db->where(['s.customer_id'=>$customer_id, 's.eshop_sale'=>1]);
        $this->db->where(['s.customer_id'=>$customer_id]);
        
        if((bool)$duration){
            $this->db->where("s.date >= date_sub(now(), interval $duration month) ");
        }
        
        $this->db->order_by('s.date', 'desc');
            
        $sales = $this->db->get()->result();
        
        if($sales){
            foreach ($sales as $sale) {
                
                $dataSales[$sale->sale_id] = [
                    'sale_id'        => $sale->sale_id,
                    'customer_id'    => $sale->customer_id,
                    'sale_date'      => $sale->sale_date,
                    'reference_no'   => $sale->reference_no,
                    'warehouse_id'   => $sale->warehouse_id,
                    'sale_statue'    => $sale->sale_status,
                    'payment_method' => $sale->payment_method,
                    'payment_status' => $sale->payment_status,
                    'grand_total'    => $sale->grand_total,
                    'sale_note'      => $sale->note,
                ];
                
                $saleItems[$sale->sale_id][] = [
                    'sale_item_id'  => $sale->sale_item_id,
                    'product_id'    => $sale->product_id,
                    'product_code'  => $sale->product_code,                                    
                    'variant_id'    => $sale->variant_id,
                    'quantity'      => $sale->quantity,
                    'unit_price'    => $sale->unit_price,
                    'mrp'           => $sale->mrp,   
                    'discount'      => $sale->discount,
                    'subtotal'      => $sale->subtotal,
                    'delivery_status' => $sale->delivery_status,
                    'image_small'   => $sale->image_small,
                ];
            }
            
            foreach ($dataSales as $sid => $dataSale) {
                $dataSale['items'] = $saleItems[$sid];
                $data[] = $dataSale;
            }
        }
        
        return $data;
    }
    
    public function get_sales_invoice($sale_id=null) {
        
        $sale = $this->get_sale($sale_id);
        $invoice = $sale[0];
        $invoice['items']    = $this->get_sale_items($sale_id);
        $invoice['payments'] = $this->get_sale_payments($sale_id);
        
        return $invoice;
    }
    
    public function get_sale($sale_id=null) {
                
        $data = false;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "s.id AS sale_id, s.invoice_no, s.date, s.reference_no, s.customer, s.biller, s.note, s.shipping, s.rounding, s.grand_total, s.sale_status, s.payment_status, s.total_items, s.paid, s.return_id, s.return_sale_ref, s.total_weight, s.shipping_address_id, ";
        $select_fields .= "a.address_name, a.line1, a.line2, a.city, a.postal_code, a.state, a.country, a.phone, a.email_id, a.state_code";
        
        $sale = $this->db->select($select_fields)
                        ->from('sales AS s')
                        ->join('addresses AS a' , 'a.id=s.shipping_address_id', 'left')
                        ->where('s.id', $sale_id)
                        ->get()
                        ->result_array();
        
        return $sale;        
    }
    
    public function get_sale_items($sale_id=null) {
        
        $smallImagePath = $this->imagePath . 'thumbs/';
                       
        $selectFields = "si.id AS sale_item_id, si.sale_id, si.product_id, si.product_code, si.product_name, si.product_type, si.option_id, si.unit_price, si.mrp, si.quantity, si.discount, si.item_discount, si.tax, si.item_tax, si.subtotal, si.product_unit_code, si.hsn_code, si.note, si.delivery_status, si.pending_quantity, si.delivered_quantity, si.gst_rate, si.cgst, si.sgst, si.igst, si.item_weight, ";
        $selectFields .= " CONCAT('$smallImagePath',p.image) AS image_small ";
        
        $saleItems = $this->db->select($selectFields)
                        ->from('sale_items AS si')                         
                        ->join('products AS p', 'p.id = si.product_id', 'left')  
                        ->where('si.sale_id', $sale_id)
                        ->get()
                        ->result();
        
        return $saleItems;     
    }
    
    public function get_sale_payments($sale_id=null) {
        
        $selectFields .= "p.id, p.sale_id, p.date, p.reference_no, p.transaction_id, p.paid_by, p.cheque_no, p.amount, p.type, p.note, p.updated_at";
        
        $salePayment = $this->db->select($selectFields)
                        ->from('payments AS p')                         
                        ->where('p.sale_id', $sale_id)
                        ->get()
                        ->result();
        
        return $salePayment;     
    }
    
    public function add_payment(array $payment) {
        
        if ($this->db->insert('payments', $payment)) {
            $payment_id = $this->db->insert_id();
            if ($this->site->getReference('pay') == $payment['reference_no']) {
                $this->site->updateReference('pay');
            }
            
            return $payment_id;
        }
    }
    
    public function get_shipping_methods() {
        
        $select_fields = "id, code, name, price, all_time, order_to_warehouse, minimum_order_amount";
        
        $result = $this->db->select($select_fields)->where(['is_active'=>1, 'is_deleted'=>0])->get('eshop_shipping_methods')->result();
        
        return (array)$result;
    }
    
    
    public function get_customer_wishlist($customer_id = null) {

        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "p.id, p.code,p.name,CONCAT('$imagePath' ,p.image) AS image_big, CONCAT('$smallImagePath',p.image) AS image_small, p.price, wsh.id AS wsh_id, wsh.option_id AS wsh_option_id, wsh.product_id, pv.name AS variant_name, pv.price AS variant_price, pv.quantity AS variant_quantity, pv.unit_quantity AS variant_unit_quantity";
                
        $result = $this->db->select($select_fields)
                ->from('products AS p')
                ->join('eshop_wishlist AS wsh', 'wsh.product_id = p.id')
                ->join('product_variants AS pv', 'pv.id = wsh.option_id', 'left')
                ->where('wsh.user_id', $customer_id)
                ->order_by('wsh.id', 'desc')
                ->get()
                ->result();

        return array('count' => count($result), 'result' => $result);
    }
    
    public function add_customer_wishlist_products($data){
        
        $q = $this->db->where($data)->get('eshop_wishlist');

        if ($q->num_rows() > 0) {
            $this->db->where($data);
            $this->db->update('eshop_wishlist', array('date' => date('Y-m-d H:i:s')));
            return $this->db->affected_rows(); 
        } else {
            $this->db->insert('eshop_wishlist', $data);
            return $this->db->insert_id(); 
        }
        return false;
    }
    
    public function delete_customer_wishlist_products($data){
        
        $q = $this->db->where($data)->delete('eshop_wishlist');
        
        if($this->db->affected_rows()){
            return true;
        } 
        
        return false;
    }
    
    
    public function update_customer_password($customer_id , $customer_mobile, $new_password){
        
        $data = ['mobile_verification_code'=>null, 'password'=>md5($new_password), 'updated_at'=>date('Y-m-d H:i:s')];
        
        $this->db->where(['id'=>$customer_id, 'phone'=>$customer_mobile, 'group_id'=>3])->update('companies', $data);
        
        if($this->db->affected_rows()){
            return true;
        } 
        
        return false;
    }
    
    public function get_customer_mobile_verification_code($customer_mobile = null){
        
        if((bool)$customer_mobile) {
            $result = $this->db->select('id AS customer_id, mobile_verification_code')
                    ->where(['group_id'=>3, 'phone'=>$customer_mobile])
                    ->get('companies')
                    ->result_array();
        
            return $result[0];
        }
        
        return false;
    }
    
    public function get_otp($customer_mobile = null){
        
        if((bool)$customer_mobile) {
            $data['mobile_verification_code'] = rand(123456, 987654);

            $this->db->where(['group_id'=>3, 'phone'=>$customer_mobile])->update('companies', $data);

            if($this->db->affected_rows()){
                return $data['mobile_verification_code'];
            } 
        }
        
        return false;
    }
    
    
    public function addtocart($data) {
        
        $product_id  = $data['product_id'];            
        $variant_id  = $data['variant_id']; 
        
        $select_fields = "p.code AS product_code,p.name AS product_name";        
        $select_fields .= (bool)$variant_id ? ", v.name AS variant_name" : '';
        
        $this->db->select($select_fields);
        $this->db->from('products AS p');
        
        if((bool)$variant_id) {
            $this->db->join('product_variants AS v', 'v.product_id=p.id', 'left');
            $this->db->where(['v.id'=>$variant_id]);
        }
        
        $this->db->where(['p.id'=>$product_id]);
        
        $product = $this->db->get()->result_array();
        
        $cartData = array_merge($data, $product[0]);
        
        $this->db->insert('webshop_cart_items', $cartData);
        
        return $this->db->insert_id();
    }
    
    public function exist_in_cart($cartdata){
        
        unset($cartdata['quantity']);
        
        $products = $this->db->select('quantity')->where($cartdata)->get('webshop_cart_items');
        
        if($products->num_rows()){
            $row = $products->result_array();
            return $row[0]['quantity'];
        } else {
            return FALSE;
        }
    }
    
    public function updatecartquantity($where, $quantity){
        
        $this->db->where($where)->update('webshop_cart_items', ['quantity'=>$quantity]);
        
        return $this->db->affected_rows();
    }
    
    public function get_cart_products( $customer_id=null, $save_for_later = false ) {
        
        $smallImagePath = $this->imagePath . 'thumbs/';
        $save_later = (bool)$save_for_later ? 1 : 0;
        
        $product_fields = "p.id AS product_id, p.code AS product_code, p.name AS product_name, p.price AS product_price, p.mrp AS product_mrp, p.tax_rate AS tax_rate_id, p.tax_method ,p.promotion, p.promo_price AS promotion_price, DATE(p.start_date) AS promotion_start_date, DATE(p.end_date) AS promotion_end_date, p.storage_type, p.ratings_avarage, p.ratings_count, p.comments_count AS ratings_comments_count,";
        $product_fields .= " CONCAT('$smallImagePath',p.image) AS image_small,";

        $product_fields .= "t.rate AS tax_rate, t.name AS tax_name, ";
        $product_fields .= "wci.id AS cart_item_id, wci.variant_id, wci.variant_name, wci.quantity AS cart_quantity ";
                 
        $this->db->select($product_fields);
        $this->db->from('webshop_cart_items AS wci');
        $this->db->join('products AS p', 'p.id=wci.product_id', 'left');          
        $this->db->join('tax_rates AS t', "t.id=p.tax_rate", "left");
        $this->db->where(['wci.customer_id'=>$customer_id, 'wci.save_for_later'=>$save_later, 'wci.quantity >='=>1]);        
         
        $results = $this->db->get()->result();
         
        if((bool)$results){

            foreach ($results as $key => $row) {

                $row->tax_method_type = ($row->tax_method) ? 'excluded' : 'included';
                $product = (array)$row;
                ksort($product);
                $products[] = (object)$product;
            }

            return $products;
        }
            
       
        return false;
       
    }
        
    public function get_cart_items($customer_id=null, $save_for_later=false) {
        
        $storeSettings = $this->get_store_settings();        
        
        $products = $this->get_cart_products($customer_id, $save_for_later ); 
        
        if((bool)$products){
            
            foreach ($products as $key => $product) {                               
                
                $variant = (bool)$product->variant_id ? $this->get_variant_by_id($product->variant_id) : null;
                                
                if(!$storeSettings->overselling) {
                    
                    $product_stocks = $this->_get_product_stocks($storeSettings->warehouse_id, $product->product_id);
                    
                    $product->warehouse_stocks = $product_stocks[0]->quantity;
                    
                    if((bool)$variant){
                        
                        if($product->storage_type == 'packed') {
                            $variant_stocks = $this->_get_products_variants_stocks($storeSettings->warehouse_id, $product->product_id, $product->variant_id);
                        }
                                                  
                        if($product->storage_type == 'loose'){
                            $product->warehouse_stocks = $product_stocks[0]->quantity / (($variant['unit_quantity'] == 0) ? 1 : $variant['unit_quantity']);
                        } else {
                            $product->warehouse_stocks = (bool)$variant_stocks ? $variant_stocks[0]['quantity'] : 0;
                        }
                    }
                }
                
                //Add Product Tax Amount In Product Price if tax amount not include in price.
                if($product->tax_method_type === 'excluded' && (float)$product->tax_rate > 0 && (bool)$product->product_price){
                    
                    $product->product_unit_price = $this->sma->formatDecimal((float)$product->product_price + (float)($product->product_price * ((float)$product->tax_rate / 100)),2); 
                
                } else {
                    
                    $product->product_unit_price = $this->sma->formatDecimal($product->product_price,2);
                }
                
                //check products mrp or set.
                $product->product_mrp = $this->sma->formatDecimal(((bool)$product->product_mrp && ($product->product_mrp >= $product->product_unit_price) ) ? $product->product_mrp : $product->product_unit_price,2); 
                
                if($variant){                     
                    if($product->tax_method_type === 'excluded' && (bool)$variant->price){
                        $product->product_unit_price = $this->sma->formatDecimal(((float)$variant->price + (float)($variant->price * ((float)$product->tax_rate / 100))) + $product->product_unit_price,2); 
                    } else {
                        $product->product_unit_price = $this->sma->formatDecimal((float)$variant->price + (float)$product->product_unit_price,2);
                    }                    
                } 
                
                $product->unit_selling_price = (float)$product->product_unit_price;
               
                //check active promotion and apply promotion price to unit price.
                if((bool)$product->promotion && $product->promotion_start_date <= date('Y-m-d') && $product->promotion_end_date >= date('Y-m-d')  ){
                    
                    $product->unit_selling_price = $this->sma->formatDecimal(((float)$product->promotion_price < (float)$product->product_unit_price) ? $product->promotion_price : $product->product_unit_price,2);
                }
                
                unset($product->promotion_price);
                unset($product->promotion_start_date);
                unset($product->promotion_end_date);
                unset($product->product_price);
                unset($product->promotion);
                unset($product->tax_method);
                unset($product->tax_method_type);                               
                unset($product->tax_rate_id);
                unset($product->storage_type);
                
                $product->subtotal = $this->sma->formatDecimal($product->unit_selling_price * $product->cart_quantity,2);
                
                $data[] = $product;
                
            }
            
            return $data;
        } else {
           return false;
        }        
    }
    
    public function cart_items_delete( $customer_id=null , $cart_item_id=null, $save_for_later=0 ) {
        
        if($customer_id){            
            if($cart_item_id){
                $this->db->where(['customer_id'=>$customer_id, 'id'=>$cart_item_id, 'save_for_later'=>$save_for_later])->delete('webshop_cart_items');
            } else {
                $this->db->where(['customer_id'=>$customer_id, 'save_for_later'=>$save_for_later])->delete('webshop_cart_items');
            }
            return $this->db->affected_rows();
        }
        
        return false;
    } 
    
    public function add_saveforlater( $customer_id=null , $cart_item_id=null  ) {
        
        $this->db->where(['customer_id'=>$customer_id, 'id'=>$cart_item_id])->update('webshop_cart_items', ['save_for_later'=>1]);
          
        return $this->db->affected_rows();
    }
    
    public function move_to_cart( $customer_id=null , $cart_item_id=null  ) {
        
        $this->db->where(['customer_id'=>$customer_id, 'id'=>$cart_item_id])->update('webshop_cart_items', ['save_for_later'=>0]);
          
        return $this->db->affected_rows();
    }
    
     
    /*
     * Customer Feedback Manage 
     */
    public function get_product_reviews($product_id=null, $limit=null) {
        
        if($product_id !== null){
            
            if($limit){                
                $data = $this->db->select('id AS review_id, webshop_products_reviews.*')->where(['product_id'=>$product_id])->order_by('updated_at', 'desc')->limit($limit)->get("webshop_products_reviews")->result();
            } else {
                $data = $this->db->select('id AS review_id, webshop_products_reviews.*')->where(['product_id'=>$product_id])->order_by('updated_at', 'desc')->get("webshop_products_reviews")->result();
            }
            return $data;            
        }
        
        return false;
    }
    
    public function get_customer_feedback($customer_id = null, $product_id = null) {
        
        if($customer_id !== null){
            
            if((bool)$product_id){                
                $data = $this->db->select('id AS review_id, webshop_products_reviews.*')->where(['customer_id'=>$customer_id, 'product_id'=>$product_id])->order_by('updated_at', 'desc')->get("webshop_products_reviews")->result();
            } else {
                $data = $this->db->select('id AS review_id, webshop_products_reviews.*')->where(['customer_id'=>$customer_id])->order_by('updated_at', 'desc')->get("webshop_products_reviews")->result();
            }
            return $data;            
        }
        
        return false;
    }
    
    public function add_feedback($data) {
        
        $this->db->insert('webshop_products_reviews', $data);
        
        return $this->db->insert_id();
    }
    
    public function update_feedback($feedback_id, $data) {
        
        $this->db->where(['id'=>$feedback_id])->update('webshop_products_reviews', $data);
        
        return $this->db->affected_rows();
    }
    
    public function exist_feedback($where) {
        
        $result = $this->db->select('id')->get_where('webshop_products_reviews', $where)->result_array();
        
        if((bool)$result){
            return $result[0]['id'];
        } 
        
        return FALSE;
    }
    
    public function delete_customer_feedback($customer_id=null, $feedback_id=null) {
        
        $result = $this->db->where(['customer_id'=>$customer_id, 'id'=>$feedback_id])->delete('webshop_products_reviews');
        
        return $this->db->affected_rows();
    }
    
    
    private function _get_customer_wishlist_items($customer_id = null) {

        if(!(bool)$customer_id) { return false; }
        
        $query = $this->db->select("id, product_id, option_id AS variant_id")
                            ->where('user_id', $customer_id)
                            ->get('eshop_wishlist');
        
        if($query->num_rows()){
            $result = $query->result();
            foreach ($result as $row) {
                $data[$row->product_id][$row->variant_id] = $row->variant_id;
            }
            return $data;             
        }        
        return false;
    }
    
    private function _get_customer_shopping_cart_items($customer_id = null) {

        if(!(bool)$customer_id) { return false; }
        
        $query = $this->db->select("id, product_id, variant_id, quantity")
                            ->where( ['customer_id'=>$customer_id, 'save_for_later'=>0] )
                            ->get('webshop_cart_items');

        if($query->num_rows()){
            $result = $query->result();
            foreach ($result as $row) {
                $data[$row->product_id][$row->variant_id] = $row->quantity;
            }
            return $data;
             
        }        
        return false;
    }
    
    
    
    
}
