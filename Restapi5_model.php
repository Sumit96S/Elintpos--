<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Restapi5_model extends CI_Model {
    
    private $imagePath;
    
    public function __construct() {

        parent::__construct();
            
        $this->load->database();
        
        $this->imagePath = base_url('assets/mdata/'.$this->Customer_assets.'/uploads/');        
    }
    
    public function get_state_list( $country_id = 1) {
        
       //$data = $this->db->get_where('state_master' ,['country_id'=> $country_id])->result();
       $data = $this->db->where(['country_id'=> $country_id])->group_by('code')->order_by('name')->get('state_master')->result();
       
       return $data;
    }
    
    public function get_system_settings() {
        
       $data = $this->db->get_where('settings' ,['setting_id'=>1])->result();
       
       return $data[0];
    }
    
    public function get_store_settings() {
        
        $store_setting_fields = 'company_name,mobile_app_name,biller_id,warehouse_id,overselling,rounding,cod AS cash_on_delivery,online_payment,free_delivery_above_amount,active_offer,suport_email,suport_phone,logo,return_within_days,products_per_page,products_group_by_variants';
        
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
    
    public function get_catlog_products($product_id=null, $filters=null, $default_limit = 12) {
        
        $storeSettings = $this->get_store_settings();        
        $count_request = isset($filters['count_request']) ? (bool)$filters['count_request'] : true;
        
        if((bool)$product_id == false && $count_request) {       
            $count_all = $this->get_products($product_id, $filters, true);
        }
        
        $result = $this->get_products($product_id, $filters , false , $default_limit);        
        
        $products   = $result['data'];
        $count      = $result['count'];
        
        if($count_request) {
            $request = $result;
            $request['count_all'] = $count_all;                
        }
        
        if((bool)$count){
            
            $shopping_cart_items = $wishlist_items = $customer_id = false;
            if(isset($filters['customer_id']) && (bool)$filters['customer_id']) {
                $customer_id = $filters['customer_id'];
                $shopping_cart_items = $this->_get_customer_shopping_cart_items($filters['customer_id']);
                $wishlist_items = $this->_get_customer_wishlist_items($filters['customer_id']);
            }
             
            $product_ids = $result['product_ids'];
                          
            //Bulk Data Fetching Function
            $allProductStocks = $this->_get_products_stocks_byids( $product_ids , $storeSettings);
            $allVariantsData  = $this->_get_products_allvariants( $product_ids , $storeSettings);
            
            foreach ($products as $key => $product) {
                
                $product->stocks =  $this->sma->formatDecimal($allProductStocks[$product->product_id], 2);
                $product->product_unit_price = $this->sma->formatDecimal($product->product_price, 2);                    
                $product->product_mrp = $this->sma->formatDecimal(( (float)$product->product_mrp > 0 && ($product->product_mrp >= $product->product_price ) ) ? $product->product_mrp : $product->product_price , 2); 
                $product->variants = [];
                $product->variant_id = "0";
                
                if(!empty($allVariantsData) && isset($allVariantsData[$product->product_id])) {                    
                                        
                    $product_variants = $allVariantsData[$product->product_id];
                    if((bool)$product->primary_variant){
                        $currentVariant = $product_variants[$product->primary_variant];
                    } else {
                        $currentVariant = current($product_variants);
                        $product->primary_variant = $currentVariant->id;
                    }
                    $temp_product_name = $product->product_name;
                    $product->product_name .= ' ('.$currentVariant->name.')';
                    
                     $product->product_unit_price = $this->sma->formatDecimal( ((float)$currentVariant->price > 0 && $currentVariant->price >= $product->product_price) ? $currentVariant->price : $product->product_price , 2);
                        
                     $product->product_mrp  = $this->sma->formatDecimal( ( (float)$currentVariant->mrp > 0 && (float)$currentVariant->mrp >= $product->product_mrp && $currentVariant->mrp >= $currentVariant->product_unit_price ) ? $currentVariant->mrp : ($product->product_mrp >= $currentVariant->product_unit_price ? $product->product_mrp : $currentVariant->product_unit_price) , 2);
                        
                //    $product->product_unit_price = $this->sma->formatDecimal( ($currentVariant->price ? $currentVariant->price : $product->product_unit_price) ,2);
                //    $product->product_mrp = $this->sma->formatDecimal( ( $currentVariant->mrp && ($currentVariant->mrp >= $product->product_mrp ) ? $currentVariant->mrp : ($product->product_mrp + $currentVariant->price)) , 2);
                    
                                        
                    if($product->storage_type == 'loose'){
                        $product->stocks = $this->sma->formatDecimal( ($currentVariant->stocks / ( ($currentVariant->unit_quantity == 0) ? 1 : $currentVariant->unit_quantity )),2);
                    } else {
                        $product->stocks = $this->sma->formatDecimal( $currentVariant->stocks ,2);
                    }
                    $product->variant_id = $product->primary_variant;
                    $i=0;
                    foreach ($product_variants as $vid => $vdata) {
                        
                        $i++;
                        //If Customer is login then check product variant exists in cart and wishlist.
                        if((bool)$customer_id){
                            $vdata->exist_in_cart = $vdata->exist_in_wishlist = false;
                            if(isset($shopping_cart_items[$product->product_id]) && isset($shopping_cart_items[$product->product_id][$vdata->id])){
                                $vdata->exist_in_cart = true;
                            }
                            if(isset($wishlist_items[$product->product_id]) && isset($wishlist_items[$product->product_id][$vdata->id])){
                                $vdata->exist_in_wishlist = true;
                            }
                        }
                        $vdata->variant_id   = $vdata->id;
                        $vdata->product_name = $temp_product_name . ' ('.$vdata->name .')';
                        $vdata->product_unit_price = ( (float)$vdata->price > 0 && (float)$vdata->price >= (float)$product->product_price) ? $vdata->price : $product->product_price ;
                        
                        $vdata->product_mrp  = ((float)$vdata->mrp > (float)$vdata->product_unit_price ) ? $vdata->mrp : (($product->product_mrp > $vdata->product_unit_price) ? $product->product_mrp :  $vdata->product_unit_price) ;
                        
                        $vdata->product_savings  = $this->sma->formatDecimal( ((float)$vdata->product_mrp - (float)$vdata->product_unit_price),2) ;
                        $vdata->product_discount = $this->sma->formatDecimal((100 * ( (float)$vdata->product_mrp - $vdata->product_unit_price ) / $vdata->product_mrp) ) . '%' ;
                        
                        $vdata->image_big = null;
                        $vdata->image_small = null;
                        
                        unset($vdata->mrp);
                        unset($vdata->price);
                        
                        if($product->variant_id == $vid){
                            $productVariants[0] = $vdata;
                        } else {
                            $productVariants[$i] = $vdata;
                        } 
                        unset($vdata);
                    }
                    ksort($productVariants);
                    foreach ($productVariants as $PVariant) {
                        $product->variants[] = $PVariant;
                    }
                    
                }
               
                //If Customer is login then check product variant exists in cart and wishlist.
                if((bool)$customer_id){
                    $product->exist_in_cart = $product->exist_in_wishlist = false;
                    if(isset($shopping_cart_items[$product->product_id]) && isset($shopping_cart_items[$product->product_id][$product->variant_id])){
                        $product->exist_in_cart = true;
                    }
                    if(isset($wishlist_items[$product->product_id]) && isset($wishlist_items[$product->product_id][$product->variant_id])){
                        $product->exist_in_wishlist = true;
                    }
                }                
               
                $product->product_savings = $this->sma->formatDecimal( ($product->product_mrp - $product->product_unit_price) ,2);
                $product->product_discount = $this->sma->formatDecimal((100 * ( $product->product_mrp - $product->product_unit_price ) / $product->product_mrp) ) . '%' ;
                
                unset($product->storage_type);
                unset($product->primary_variant);
                unset($product->tax_name);
                unset($product->tax_rate);
                unset($product->product_price);                
                
                if(!empty($product->product_details)) {
                    $product->product_details = ($product->product_details);
                }
                
                $pdata = (array)$product;
                ksort($pdata);
                
                $photos = $this->_get_product_images($product->product_id);
                $pdata['product_images'] = [];
                if((bool)$photos){
                    $pdata['product_images'] = $photos;
                }
               
                $product_reviews = $this->get_product_reviews($product->product_id, 3);
                $pdata['product_reviews'] = [];
                if((bool)$product_reviews){
                    $pdata['product_reviews'] = $product_reviews;
                }
                 
                $data[] = (object)$pdata;
                                
            }//End products loop
            
            $request['data']  = $data;
            
            return $request;
        } else {
           return false;
        }
        
        
    }
    
    public function get_products($product_id=null, $filters=null, $only_count=false, $default_limit=12) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
                
        if((bool)$only_count){
            
            $product_fields = "count(p.id) AS count_all";            
        } else {
            
            $product_fields = "p.id AS product_id, p.code AS product_code, p.category_id, p.subcategory_id, p.mrp AS product_mrp, p.storage_type, p.ratings_avarage, p.ratings_count, p.comments_count AS ratings_comments_count, p.primary_variant,";
            $product_fields .= "CONCAT('$imagePath' ,p.image) AS image_big, CONCAT('$smallImagePath',p.image) AS image_small,";

            //Fetch Single Product Descriptions
            //if((bool)$product_id) {            
                $product_fields .= "p.product_details, ";
            //}
             
            $product_fields .= "IF(p.eshop_name is null, p.name, p.eshop_name) AS product_name, ";
            $product_fields .= "IF(p.eshop_price > 0, p.eshop_price, p.price) AS product_price, ";
            

            $product_fields .= "b.name AS brand_name, t.rate AS tax_rate, t.name AS tax_name ";
        }//end else
        
        $this->db->select($product_fields);
        $this->db->from('products AS p');
//        $this->db->join('units AS u', "u.id=p.sale_unit", "left");
//        $this->db->join('categories AS c', "c.id=p.category_id", "left");
//        $this->db->join('categories AS sc', "sc.id=p.subcategory_id", "left");
        $this->db->join('brands AS b', "b.id=p.brand", "left");
        $this->db->join('tax_rates AS t', "t.id=p.tax_rate", "left");
        
        $objDB = $this->db->where(['p.in_eshop'=>1, 'p.is_active'=>1]);
        
        if($product_id != null) {
           $objDB->where(['p.id' => $product_id]);
        } 
        elseif($filters != null) {
            
            if(isset($filters['cart_items']) && (bool)$filters['customer_id'] ){
                $save_for_later = isset($filters['save_for_later']) ? $filters['save_for_later'] : 0;
                $cart_items = $this->get_cart_items($filters['customer_id'], $save_for_later);
                $objDB->where_in('p.id', $cart_items);
            }
            elseif(isset($filters['is_featured']) && (bool)$filters['is_featured'] ){
                
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
                
            } 
            else {
            
                if(isset($filters['keyword']) && $filters['keyword'] != '' && strlen($filters['keyword']) >= 3 ){
                    
                    $objDB->group_start();
                        $objDB->like('p.name', $filters['keyword'], 'both');    // Produces:  LIKE '%keyword%' ESCAPE '!'
                        $objDB->or_like('p.eshop_name', $filters['keyword'], 'both');    // Produces:  LIKE '%keyword%' ESCAPE '!'
                        $objDB->or_like('p.code', $filters['keyword'], 'none');     // Produces: LIKE 'keyword' ESCAPE '!'
                        $objDB->or_like('p.product_details', $filters['keyword'], 'both'); // Produces:  LIKE '%keyword%' ESCAPE '!'
                        $objDB->or_like('b.name', $filters['keyword'], 'before');      // Produces:  LIKE '%keyword' ESCAPE '!'
                       //$objDB->or_like('c.name', $filters['keyword'], 'before');      // Produces:  LIKE '%keyword' ESCAPE '!'
                    $objDB->group_end();                
                    
                } else {
                    
                    if(isset($filters['brand_id']) && (bool)$filters['brand_id'] && is_numeric($filters['brand_id'])){

                        $objDB->where(['p.brand' => $filters['brand_id']]);
                        
                    } else {
                        
                        if(isset($filters['category_id']) && (bool)$filters['category_id'] && is_numeric($filters['category_id'])){

                            $objDB->where(['p.category_id' => $filters['category_id'] ]);
                        }
                        if(isset($filters['subcategory_id']) && (bool)$filters['subcategory_id'] && is_numeric($filters['subcategory_id']) ){

                            $objDB->where(['p.subcategory_id' => $filters['subcategory_id'] ]);
                        }
                    }
                    
                    $objDB->order_by('p.idx', 'RANDOM');
                    
                }//end else keyword
            
            }//end else
            
            
            if(!(bool)$only_count){
                if(isset($filters['items_per_page']) && (bool)$filters['items_per_page'] && is_numeric($filters['items_per_page'])){

                    $pageno = isset($filters['page_number']) && !empty($filters['page_number']) ? $filters['page_number'] : 1; 
                    $itemsPerPage = $filters['items_per_page'];

                    $offset = ( $pageno - 1 ) * $itemsPerPage;

                    $objDB->limit( $itemsPerPage, $offset);
                } else {
                    $objDB->limit( $default_limit ); 
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

                    //$row->tax_method_type = ($row->tax_method) ? 'excluded' : 'included';
                    $product_ids[] = $row->product_id;
                    $product = (array)$row;
                    
                    ksort($product);
                    $products[] = (object)$product;
                }        
            }
            
            $result['product_ids'] = $product_ids;
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
        $select = 'id, eshop_name AS name, eshop_price AS price, eshop_mrp AS mrp, unit_quantity, product_id';
        
        $where['is_active'] = 1;
        $where['in_eshop']  = 1;
        $where['id']  = $variant_id;
        
        $data = $this->db->select($select)->where( $where )->get('product_variants')->result();
        
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


    private function _get_products_allvariants(array $product_ids, $settings) {
        $vdata = [];
        $selectFields = 'id, eshop_name AS name, eshop_price AS price, eshop_mrp AS mrp, unit_quantity, product_id';
        
        $this->db->select($selectFields);
        
        $where['is_active'] = 1;
        $where['in_eshop']  = 1;
        
        $this->db->where( $where );
         
        if(is_array($product_ids)) {
           $this->db->where_in('product_id', $product_ids);  
        }
        
        $data = $this->db->get("product_variants")->result();
        
        if(!empty($data)){
                        
            $settings = $settings ? $settings : $this->get_store_settings();
                       
            if( !(bool)$settings->overselling ){
                
                foreach ($data as $key => $variant) {
                
                    $variantIds[] = $variant->id;
                }
                
                $warehouse_id = (bool)$settings->warehouse_id ? $settings->warehouse_id : null;
            
                $variantStocks = $this->_get_variants_stocks_byids( $warehouse_id , $variantIds );
                
            }
            
            
            foreach ($data as $key => $variant) {
                $mrp  =  $variant->mrp;
                $variant->mrp = $mrp > $variant->price ? $mrp : $variant->price ;
                
                if($settings->overselling == 0 ){
                    $variant->stocks = number_format( floatval($variantStocks[$variant->product_id][$variant->id]),2);
                } else {
                    $variant->stocks = "999.00";
                }
                
                $vdata[$variant->product_id][$variant->id] = $variant;                
            }
        }
        
        return $vdata;
    }
    
    private function _get_product_variants($product_id=null, $variant_id=null) {
        $vdata = [];
        $selectFields = 'id, eshop_name AS name, eshop_price AS price, eshop_mrp AS mrp, unit_quantity, product_id';
        
        $where['is_active'] = 1;
        $where['in_eshop']  = 1;
        
        if((bool)$variant_id) {
            $where['id'] = $variant_id; 
        }
            
        if($product_id) {
            $where['product_id'] = $product_id;
        }
        
        $data = $this->db->select($selectFields)->where( $where )->get("product_variants")->result();
        if(!empty($data)){
            foreach ($data as $key => $variant) {
                $mrp  =  $variant->mrp;
                $variant->mrp = $mrp > $variant->price ? $mrp : $variant->price ;
                $vdata[$variant->product_id][] = $variant;
            }
        }
        return $vdata;
    }
    
    private function _get_product_images($product_id=null) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "product_id, CONCAT('$imagePath' ,photo) AS image_big, CONCAT('$smallImagePath',photo) AS image_small";
        
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
    
    private function _get_products_stocks_byids( array $product_ids, $settings = null){
        
        $data = null;
        
        $settings = $settings ? $settings : $this->get_store_settings();
                       
        if( (bool)$settings->overselling ){
            
            foreach ($product_ids as $key => $product_id) {
                $stocks[$product_id] = number_format(999,2);
            }
            return $stocks;
            
        } else {
            
            $warehouse_id = (bool)$settings->warehouse_id ? $settings->warehouse_id : null;
            
            $selectFields = "SUM(quantity) AS quantity, product_id";

            $this->db->select($selectFields);
            $this->db->where_in('product_id', $product_ids );    
            
            if((bool)$warehouse_id) {

                $this->db->where('warehouse_id', $warehouse_id);
            } else {
                
                $this->db->group_by('product_id');
            }                
                
                $data = $this->db->get("warehouses_products")->result();

                if(!empty($data)){
                    foreach ($data as $key => $row) {
                        $data[$row->product_id] = $row->quantity;
                    }
                    foreach ($product_ids as $key => $product_id) {
                        $quantity = isset($data[$product_id]) ? $data[$product_id]  : 0;
                        $stocks[$product_id] = number_format($quantity,2);
                    }

                }
                return $stocks;
            

            return false;
        }
    }
    
    private function _get_product_stocks($warehouse_id=null, $product_id=null){
        
        $data = null;
        $where = null;
        if($warehouse_id != null) {
            $where['warehouse_id'] = $warehouse_id;
            
            if($product_id != null) {
                $where['product_id'] = $product_id;
            } 
            
            $data = $this->db->get_where("sma_warehouses_products", $where )->result();
                        
            return $data;
        }
        
        return false;
    }
    
    private function _get_products_variants_stocks($warehouse_id=null, $product_id=null, $variant_id=null){
        
        $vdata = null;
        
        if($warehouse_id != null) {
            
            $select = "product_id, option_id, quantity";
            
            if($variant_id != null) {
                $data = $this->db->select($select)->get_where("warehouses_products_variants", ['warehouse_id'=>$warehouse_id, 'product_id'=>$product_id, 'option_id'=>$variant_id])->result();
            } elseif($product_id != null) {
                $data = $this->db->select($select)->get_where("warehouses_products_variants", ['warehouse_id'=>$warehouse_id, 'product_id'=>$product_id])->result();
            } else {
                $data = $this->db->select($select)->get_where("warehouses_products_variants", ['warehouse_id'=>$warehouse_id])->result();
            }
            
            if($data){
                foreach ($data as $key => $variant) {                
                    $vdata[$variant->product_id][$variant->option_id] = $variant->quantity;
                }
            } 
        }
        
        return $vdata;
    }
    
    private function _get_variants_stocks_byids($warehouse_id=null, array $variant_ids ){
        
        $vdata = [];
        
        if($warehouse_id != null) {
            
            $select = "product_id, option_id, quantity";
            
            $this->db->select($select);

            $this->db->where(['warehouse_id'=>$warehouse_id]);
            $this->db->where_in('option_id', $variant_ids);
            
            $data = $this->db->get("warehouses_products_variants")->result();
                        
            if($data){
                foreach ($data as $key => $variant) {
                    $qty = ($variant->quantity == null) ? 0 :$variant->quantity ;
                    $vdata[$variant->product_id][$variant->option_id] = number_format($qty, 2);
                }
            } 
        }
        
        return $vdata;
    }
    
    
    public function get_customer_views_products($customer_id=null, $limits=20) {
        
        if($customer_id !== null){
            
            $imagePath = $this->imagePath;
            $smallImagePath = $this->imagePath . 'thumbs/';
        
                $this->db->select("v.product_id, v.visits_count, p.eshop_name AS product_name, p.code AS product_code, CONCAT('$imagePath' ,p.image) AS image_big, CONCAT('$smallImagePath',p.image) AS image_small, p.ratings_avarage, p.ratings_count");
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
        
        if((bool)$category_id) {            
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
        
         
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';

        $select_fields = "id,code, name, CONCAT('$imagePath',image) AS image_big,CONCAT('$smallImagePath',image) AS image_small, parent_id";

        $this->db->select( $select_fields );
        
        $this->db->where(['is_active'=>1, 'in_eshop'=>1]);
        
        if((bool)$parent_id){        
           $this->db->where('parent_id', $parent_id);
        } else {           
           $this->db->where('parent_id >', 0);
        }        
                
        $result = $this->db->get("categories")->result();
        
        if(!(bool)$parent_id && count($result)){ 
            foreach ($result as $row) {
                $data[$row->parent_id][] = $row;
            }
        } else {
            $data = $result;
        }
        
        return ((bool)$result) ? $data : array();
        
    }
    
    public function get_brands($id=null) {
        
        $imagePath = $this->imagePath;
        $smallImagePath = $this->imagePath . 'thumbs/';
        
        $select_fields = "id,code,name,CONCAT('$imagePath' ,image) AS image_big, CONCAT('$smallImagePath',image) AS image_small ";
        
        if($id != null) {
            return $data = $this->db->select($select_fields)->where(['id'=>$id])->get("brands")->row_array();
        } else {
            return $data = $this->db->select($select_fields)->get("brands")->result();
        }
        
        return false;
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
            return [];
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
    
    public function is_valid_customer_address_id(array $data) {
        
        if($data['company_id'] && $data['id']) {
            
            $result = $this->db->select('id')->get_where("addresses", $data)->result();
                
            if($result){
                return true;
            }
            
            return false;
        }
        
        return false;
    }
    
    public function set_customer_default_address(array $data) {
        
        if($data['company_id'] && $data['id']) {
                            
            if($this->db->where(['company_id' => $data['company_id']])->update("addresses", ['is_default'=>0])) {
                
                $this->db->where($data)->update("addresses", ['is_default'=>1]);
                
                if($this->db->affected_rows()) {
                    return true;
                } 
            }
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
            $i=0;
            foreach ($data as $key => $row) {
                
                $methods[$i]['method'] = $row->pgateway_title ;
                $methods[$i]['testing'] = json_decode($row->pgateway_testing , true) ;
                $methods[$i]['production'] = json_decode($row->pgateway_production, true);
                $i++;
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
        $data = [];
        if($result) {
            $shipping_times = $this->get_shipping_times();
            foreach ($result as $key => $method) {
               
                $method->times = $shipping_times[$method->id];
                $data[] = $method;
            }            
        }
        
        return $data;
    }
    
    public function get_shipping_times() {
        
        $select_fields = "shipping_method_id, start_time, end_time";
        
        $result = $this->db->select($select_fields)->where(['is_active'=>1])->get('shipping_time')->result();
        $times = [];
        if($result){
            foreach ($result as $key => $row) {
                $times[$row->shipping_method_id][] = $row;
            }
        }
        return $times;
    }
    
    public function get_shipping_pincodes() {
        
        $select_fields = "pincode, delivery_time";
        
        $result = $this->db->select($select_fields)->get('pincode')->result();
        $pincode = [];
        if((bool)$result){
            foreach ($result as $key => $row) {
                $pincode[] = $row;
            }
        }
       return $pincode;
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
            $this->db->where(['v.id'=>$variant_id, 'v.is_active' => 1, 'v.in_eshop' =>1]);
        }
        
        $this->db->where(['p.id'=>$product_id]);
        
        $product = $this->db->get()->result_array();
        
        $cartData = array_merge($data, $product[0]);
        
        $this->db->insert('webshop_cart_items', $cartData);
        
        return $this->db->insert_id();
    }
    
    public function exist_in_cart($cartdata){
        
        unset($cartdata['quantity']);
        
        $products = $this->db->select('quantity')->where($cartdata)->where('quantity >',0)->get('webshop_cart_items');
        
        if($products->num_rows()){
            $row = $products->result_array();
            return $row[0]['quantity'];
        } else {
            return FALSE;
        }
    }
    
    public function updatecartquantity($where, $quantity){
        
        $updated_at  = date('Y-m-d H:i:s');
        
        $this->db->where($where)->limit(1)->update('webshop_cart_items', ['quantity'=>$quantity, 'updated_at'=>$updated_at]);
        $affected_rows = $this->db->affected_rows();
        
        $this->db->where('quantity <=',0)->delete('webshop_cart_items');
        
        return $affected_rows;
    }
    
    public function get_cart_products( $customer_id=null, $save_for_later = false ) {
        
        $smallImagePath = $this->imagePath . 'thumbs/';
        $save_later = (bool)$save_for_later ? 1 : 0;
        
        
        $product_fields = "p.id AS product_id, p.code AS product_code, p.eshop_name AS product_name, p.eshop_price AS product_price, p.mrp AS product_mrp, p.storage_type,";
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
                
                $product->stocks = '999.00';
                
                //check active promotion and apply promotion price to unit price.
                if((bool)$product->promotion && ($product->promotion_start_date <= date('Y-m-d') && $product->promotion_end_date >= date('Y-m-d'))  ){
                    
                    $product->product_unit_price = $this->sma->formatDecimal((float)$product->promotion_price ,2);
                } else {
                    $product->product_unit_price = $this->sma->formatDecimal($product->product_price,2);
                }
                
                if(!$storeSettings->overselling) {
                    $product_stocks = $this->_get_product_stocks($storeSettings->warehouse_id, $product->product_id);
                    $product->stocks = $this->sma->formatDecimal($product_stocks[0]->quantity,2);
                }
                
                //check products mrp or set.
                if((bool)$product->product_mrp && ($product->product_mrp >= $product->product_unit_price) ){
                    
                    $product->product_mrp = $this->sma->formatDecimal( $product->product_mrp ,2); 
                    
                } else {
                    $product->product_mrp = $this->sma->formatDecimal( $product->product_unit_price ,2); 
                }
                
                if((bool)$product->variant_id){
                    
                    $variant = $this->get_variant_by_id($product->variant_id);
                    $product->product_name .= ' ('.$variant->name.')';
                    $product->product_unit_price = $this->sma->formatDecimal((float)$variant->price, 2);
                    
                    $product->product_mrp = $this->sma->formatDecimal( ((bool)$variant->mrp ? $variant->mrp : ($product->product_mrp + $variant->price)) , 2);
                    
                    if(!$storeSettings->overselling) {
                        
                        $variant_stocks = $this->_get_products_variants_stocks($storeSettings->warehouse_id, $product->product_id, $product->variant_id);
                        
                        if($product->storage_type == 'loose'){
                            $product->stocks = $this->sma->formatDecimal(($product->stocks / (($variant->unit_quantity == 0) ? 1 : $variant->unit_quantity)) , 2);
                        } else {
                            $product->stocks = $this->sma->formatDecimal( ((bool)$variant_stocks ? $variant_stocks[$product->product_id][$product->variant_id] : 0) , 2);
                        }
                    }                    
                }
                
                $product->product_savings = $this->sma->formatDecimal( ($product->product_mrp - $product->product_unit_price) * $product->cart_quantity ,2);
                $product->product_discount = $this->sma->formatDecimal((100 * ( $product->product_mrp - $product->product_unit_price ) / $product->product_mrp),2) . '%' ;
                
                unset($product->promotion_price);
                unset($product->promotion_start_date);
                unset($product->promotion_end_date);
                unset($product->product_price);
                unset($product->promotion);               
                unset($product->storage_type);
                unset($product->tax_method_type);
                
                $prdarr = (array) $product;
                ksort($prdarr);
                $product = (object)$prdarr;
                
                $product->subtotal = $this->sma->formatDecimal($product->product_unit_price * $product->cart_quantity,2);
                
                $data[] = $product;
                
            }
            
            return $data;
        } else {
           return false;
        }        
    }
    
    public function get_customer_cart_items($customer_id=null, $save_for_later=false) {
        
        $storeSettings = $this->get_store_settings();        
        
        $products = $this->get_cart_products($customer_id, $save_for_later ); 
        
        $cart_item_total += $product_mrp;
        $cart_total_discount_amount += $product_savings;
        $cart_total_without_tax += ($item_sub_total - $item_tax_amount);
        $cart_total_tax += $item_tax_amount;
        $cart_total_amount += $item_sub_total;
        $cart_shipping_amount = 0;
        $cart_total_items = 0;
                
        if((bool)$products){
            
            foreach ($products as $key => $product) {
                
                
                $product->stocks = '999.00';
                
                $product_unit_price = $product->product_price ;
                
                
                //check products mrp or set.
                if((float)$product->product_mrp > 0 && ($product->product_mrp >= $product_unit_price ) ){
                    
                    $product_mrp =  $product->product_mrp ;                     
                } else {
                    $product_mrp =  $product_unit_price ; 
                }
                
                if(!$storeSettings->overselling) {
                    $product_stocks  = $this->_get_product_stocks($storeSettings->warehouse_id, $product->product_id);
                    $product->stocks = $this->sma->formatDecimal($product_stocks[0]->quantity,2);
                }
                    
                if((bool)$product->variant_id){
                    
                    $variant = $this->get_variant_by_id($product->variant_id);
                    $product->product_name .= ' ('.$variant->name.')';
                    
//                    $product->product_unit_price = $this->sma->formatDecimal((float)$variant->price, 2);
//                    
//                    $product->product_mrp = $this->sma->formatDecimal( ((bool)$variant->mrp ? $variant->mrp : ($product->product_mrp + $variant->price)) , 2);
                    
                    
                    $product_unit_price = ( (float)$variant->price > 0 && (float)$variant->price >= (float)$product_unit_price) ? $variant->price : $product_unit_price;

                    $product_mrp  = ((float)$variant->mrp > (float)$variant->product_unit_price ) ? $variant->mrp : (($product_mrp > $variant->product_unit_price) ? $product_mrp :  $variant->product_unit_price) ;

                    
                    if(!$storeSettings->overselling) {
                        
                        $variant_stocks = $this->_get_products_variants_stocks($storeSettings->warehouse_id, $product->product_id, $product->variant_id);
                        
                        if($product->storage_type == 'loose'){
                            $product->stocks = $this->sma->formatDecimal(($product->stocks / (($variant->unit_quantity == 0) ? 1 : $variant->unit_quantity)) , 2);
                        } else {
                            $product->stocks = $this->sma->formatDecimal( ((bool)$variant_stocks ? $variant_stocks[$product->product_id][$product->variant_id] : 0) , 2);
                        }
                    }                    
                } 
                
                $product->product_mrp  = $this->sma->formatDecimal($product_mrp , 2);
                $product->product_unit_price  = $this->sma->formatDecimal($product_unit_price , 2);
                
                $item_savings  = ((float)$product_mrp - (float)$product_unit_price) * $product->cart_quantity;
                
                $product->product_savings  = $this->sma->formatDecimal($product_savings ,2);
                $product->product_discount = $this->sma->formatDecimal((100 * ( $product_savings ) / $product_mrp) ) . '%' ;
                
                
                $item_sub_total  = $product_unit_price * $product->cart_quantity;
                $product->subtotal = $this->sma->formatDecimal($item_sub_total, 2);
                $item_tax_amount = 0;
                
                if((float)$product->tax_rate > 0){
                    
                    $tax_amount = ((float)$product->product_unit_price * (float)$product->tax_rate) / (100 + (float)$product->tax_rate);
                    $item_tax_amount = $tax_amount * $product->cart_quantity;
                }
                                                                 
                unset($product->product_price);           
                unset($product->storage_type);
                unset($product->tax_name);
                unset($product->tax_rate);  
                
                $cart_total_items += $product->cart_quantity;
                $cart_price_total += ($product_mrp * $product->cart_quantity);
                $cart_total_discount_amount += $item_savings;
                $cart_total_without_tax += ($item_sub_total - $item_tax_amount);
                $cart_total_tax += $item_tax_amount;
                $cart_total_amount += $item_sub_total;
                $cart_shipping_amount = $this->sma->formatDecimal( (($item_sub_total > $storeSettings->free_delivery_above_amount) ? 0 : 50), 2);
                         
                
                $data['cart']['items'][] = $product;
            }            
             
            $data['cart']['total']['items']            = $this->sma->formatDecimal($cart_total_items);
            $data['cart']['total']['price']            = $this->sma->formatDecimal($cart_price_total,2);
            $data['cart']['total']['discount']         = $this->sma->formatDecimal(0-$cart_total_discount_amount,2);
            $data['cart']['total']['subtotal']         = $this->sma->formatDecimal($cart_total_without_tax,2);
            $data['cart']['total']['tax_amount']       = $this->sma->formatDecimal($cart_total_tax,2);
            $data['cart']['total']['delivery_charges'] = $this->sma->formatDecimal($cart_shipping_amount,2);
            
            $data['cart']['total']['billing_amount'] = $this->sma->formatDecimal( ($cart_total_without_tax + $cart_total_tax + $cart_shipping_amount) ,2);
            
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
            
            $select = "id AS review_id, product_id, product_name, variant_id, variant_name, customer_id, customer_name, reviews_date, reviews_rattings, reviews_title, reviews_details, customer_images, like_count, dislike_count, updated_at";
            
            if($limit){                
                $data = $this->db->select($select)->where(['product_id'=>$product_id])->order_by('updated_at', 'desc')->limit($limit)->get("webshop_products_reviews")->result();
            } else {
                $data = $this->db->select($select)->where(['product_id'=>$product_id])->order_by('updated_at', 'desc')->get("webshop_products_reviews")->result();
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
    
    
    public function get_customer_cart_count($customer_id = null) {
        
        if((bool)$customer_id){
            
            $result = $this->db->select("count(id) AS count")->where(['customer_id'=>$customer_id, 'save_for_later'=>0])->get('webshop_cart_items')->result_array();
        
            if($result){
                return $result[0]['count'];
            }
        }
        
        return false;
        
    }
    
    public function get_customer_wishlist_count($customer_id = null) {
        
        if((bool)$customer_id){
            
            $result = $this->db->select("count(id) AS count")->where(['user_id'=>$customer_id])->get('eshop_wishlist')->result_array();
        
            if($result){
                return $result[0]['count'];
            }
        }
        
        return false;
        
    }
    
    
}
