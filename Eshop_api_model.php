<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Eshop_api_model extends CI_Model {

	public $select_fields;
        public function getFeaturedProducts($limit = 10) {

            $sql = "SELECT `id`,`code`, `name`, `price`, `image`, promotion,promo_price,start_date,end_date "
                    . "FROM `sma_products` "
                    . "WHERE `id` IN ( SELECT `product_id` FROM `sma_sale_items` group by `product_code` order by count(`product_name`) desc ) limit $limit ";

            $rec = $this->db->query($sql);
            
            if ($rec->num_rows()) {
                return $rec->result();
            }

            return FALSE;
       
    }

    
    

	public function getPopularCategories($limit = 10) {
        $sql = "SELECT count(si.product_code) as max_sale, c.name, c.id, c.image FROM `sma_categories` c inner join sma_products p on c.id=p.category_id inner join sma_sale_items si on p.code=si.product_code WHERE  1 group by c.id order by max_sale desc limit $limit ";

        $rec = $this->db->query($sql);
        
        if ($rec->num_rows()) {
            return $rec->result();
        }

        return FALSE;
    }
	public function get_parent_categories($ParentId=0) {
		$this->db->where('parent_id', $ParentId);
        $q = $this->db->select('id, code, name, image, parent_id')->order_by('name', 'asc')->get('categories');
        return $q->result();
    }
	public function get_categories($Id=0) {
		if($Id!=0)
			$this->db->where('parent_id', $Id);
        $q = $this->db->select('id, code, name, image, parent_id')->order_by('name', 'asc')->get('categories');
        return $q->result();
    }


    /*------------------- 24-06-2020 --------------------*/
       /**
    *  Max Sales Product
    * @param type $limit
    * @return boolean
    */
         public function get_brands($limit = 10) {
		$sql = "SELECT count(si.product_code) as max_sale, b.name, b.id, b.image FROM `sma_brands` b inner join sma_products p on 
                        b.id=p.category_id inner join sma_sale_items si on p.code=si.product_code WHERE  1 group by b.id order by max_sale desc 
                        limit $limit ";
		$rec = $this->db->query($sql);
		if ($rec->num_rows()) {
                   return $rec->result();
                }
         return FALSE;
    }
    public function getpopulerProduct( $limit = 10){
        $getmaxsales =  $this->db->select_max('product_id')->limit($limit)->group_by('product_code')->get('sma_sale_items')->result();
        if($getmaxsales){
            foreach($getmaxsales as $maxsales){
                $maxsalesProduct[] =$maxsales->product_id;
            }
          return  $this->db->select('id,code,name,price,image,promotion,promo_price,start_date,end_date')->where_in('id',$maxsalesProduct)->get('sma_products')->result();
       }
       return FALSE;
    }


     /**
     * 
     * @param type $product_hash
     * @return boolean
     */
    public function getProductVeriantsById($productid,$limit=0) {
        if($limit!=0)
       $this->db->limit(1, 0);
       $veriant =  $this->db->select('`id`,`name`,`cost`,`price`,`quantity`')->where('product_id',$productid)->get('product_variants')->result();
	   
       if($veriant){
        
           return $veriant;
       }else {
           return false;
       }
    }
    

    /**
    * Get Category vise products
    * @param type $limit
    * @return boolean
    */
    public function getPopularCategoryProducts($limit = 5){
        $category = $this->db->select('id, code, name, image, parent_id')->where('parent_id', 0)->order_by('name', 'asc')->limit($limit)->get('categories')->result();
        if($category){
            $productarr = array();
            $returnarr= array();
                foreach($category as $key => $categoryvalue){
                    $result= $this->db->where(['category_id'=>$categoryvalue->id])->limit(2)->get('products')->result();
                   $productarr[]= $result;
                   $returnarr = array_merge($returnarr,$productarr[$key]);
                }
                
                
            return $returnarr; 
            
        }
        return false;
       
        
    }
/**
     * 
     * @param type $product_hash, $product_option
     * @return boolean
     */
	public function getProductVariantDetails($ProductId, $OptionId=null) {
        $this->db->select("p.`id`, p.`code`, p.`name`, p.`unit`, p.`price`, p.`quantity`, p.`image`, p.`tax_rate` AS tax_rate_id, t.`rate` AS tax_rate, t.`name` AS tax_name, p.`tax_method`, p.category_id, p.subcategory_id, p.product_details,"
                    . "p.`promotion`, p.`promo_price`, p.`start_date`, p.`end_date`, p.`sale_unit`, u.name AS unit_name, "
                    . "pv.id as option_id, pv.name as option_name, pv.price as option_price , pv.quantity as option_quantity ");
		$this->db->from('products AS p');
		$this->db->join('product_variants AS pv', 'p.id =  pv.product_id', 'left');		
		$this->db->join('tax_rates AS t', 'p.tax_rate =  t.id', 'left');
        $this->db->join('units AS u', 'p.`sale_unit` =  u.id', 'left');
        $this->db->where('p.id', $ProductId);
		if($OptionId)
        $this->db->where('pv.id', $OptionId);
		$q = $this->db->get();
		return $Res = $q->result();
    }
	/**
    * Get Category vise products
    * @param type $category_hash, page_no, number_of_item_per_page
    * @return boolean
    */
    public function getCategoryProducts($category_id, $pageno = 1, $itemsPerPage = 18, $type) {
         
        if (is_numeric($category_id)) {
            $data['count'] = 0;
        }  

        $offset = ( $pageno - 1 ) * $itemsPerPage;

        for ($i = 1; $i <= 2; $i++) {
            if ($i == 1) {
                $this->db->select('p.`id`');
            } else {
                $this->db->select("p.`id`, p.`code`, p.`name`, p.`unit`, p.`price`, p.`quantity`, p.`image`, p.`tax_rate` AS tax_rate_id, t.`rate` AS tax_rate, t.`name` AS tax_name, p.`tax_method`, p.category_id, p.subcategory_id,"
                    . "p.`promotion`, p.`promo_price`, p.`start_date`, p.`end_date`, p.`sale_unit`, u.name AS unit_name "
                    );                
            }
            
            $this->db->from('products AS p');
            
            //$this->db->join('product_variants AS pv', 'p.id =  pv.product_id', 'left');
            $this->db->join('tax_rates AS t', 'p.tax_rate =  t.id', 'left');
            $this->db->join('units AS u', 'p.`sale_unit` =  u.id', 'left');
            if($type=='brand'){
		$this->db->where(['p.brand' => $category_id]);
	    } else{
		$this->db->where(['p.category_id' => $category_id]);

		$this->db->or_where('p.subcategory_id', $category_id);
	    }
            $this->db->where(['p.category_id' => $category_id]);

            $this->db->or_where('p.subcategory_id', $category_id);

            if ($i == 2) {

                $offset = ($pageno - 1 ) * $itemsPerPage;

                $this->db->limit($itemsPerPage, $offset);
                //$this->db->limit($itemsPerPage);
            }
            $var = 'q' . $i;
            $$var = $this->db->get();
        }//end for.

        $count = $q1->num_rows();
        $data['count'] = $count;
        $data['totalPages'] = ceil($count / $itemsPerPage);

        if ($count > 0) {
            $data['msg'] = '<div class="alert alert-info">Result: ' . $count . ' products found.</div>';
            $data['items'] = $q2->result();
        } else {
            $data['msg'] = '<div class="alert alert-info">Products not found in this category</div>';
            if($type=='brand')
            $data['msg'] = '<div class="alert alert-info">Products not found in this brand</div>';
        }
        return $data;
    }
	/**
     * 
     * @param type $product_hash
     * @return boolean
     */
	public function getProductsImages($product_id) {
        $q = $this->db->select('photo')
                ->where_in('product_id', $product_id)
                ->get('product_photos');

        if ($q->num_rows() > 0) {
                
            foreach ($q->result() as $row) {
                $data[] = $row->photo;
            }
            return $data;
        }

        return false;         
    }

    /**
     * Search product
     * @param type $term
     * @param type $limit
     * @return type
     */
    public function search($term, $limit = 10){
        $response = $this->db->select('name')->like('name',$term, 'both')->limit(10)->get('sma_products')->result();
        return ($response)? $response :false;
    }
    
    	/**
    * Get Category vise products
    * @param type $category_hash, page_no, number_of_item_per_page
    * @return boolean
    */
    public function getSearchProducts($term, $pageno = 1, $itemsPerPage = 18) {
       $term = str_replace("~", "/", $term);
        $offset = ( $pageno - 1 ) * $itemsPerPage;

        for ($i = 1; $i <= 2; $i++) {
            if ($i == 1) {
                $this->db->select('p.`id`');
            } else {
                $this->db->select("p.`id`, p.`code`, p.`name`, p.`unit`, p.`price`, p.`quantity`, p.`image`, p.`tax_rate` AS tax_rate_id, t.`rate` AS tax_rate, t.`name` AS tax_name, p.`tax_method`, p.category_id, p.subcategory_id,"
                    . "p.`promotion`, p.`promo_price`, p.`start_date`, p.`end_date`, p.`sale_unit`, u.name AS unit_name "
                    );                
            }
            
            $this->db->from('products AS p');
            
            //$this->db->join('product_variants AS pv', 'p.id =  pv.product_id', 'left');
            $this->db->join('tax_rates AS t', 'p.tax_rate =  t.id', 'left');
            $this->db->join('units AS u', 'p.`sale_unit` =  u.id', 'left');
            if($term!='false'){
                $this->db->like('p.name',$term, 'both');
            }

            if ($i == 2) {

                $offset = ($pageno - 1 ) * $itemsPerPage;

                $this->db->limit($itemsPerPage, $offset);
                //$this->db->limit($itemsPerPage);
            }
            $var = 'q' . $i;
            $$var = $this->db->get();
        }//end for.

        $count = $q1->num_rows();
        $data['count'] = $count;
        $data['totalPages'] = ceil($count / $itemsPerPage);

        if ($count > 0) {
            $data['msg'] = '<div class="alert alert-info">Result: ' . $count . ' products found.</div>';
            $data['items'] = $q2->result();
        } else {
            $data['msg'] = '<div class="alert alert-info">Products not found in this category</div>';
        }
        return $data;
    }

}    

