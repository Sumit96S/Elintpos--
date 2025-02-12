<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Eshop_api extends MY_Controller {

    private $api3_private_key = '';
    private $posVersion = '';
    private $ci = '';
    
    public function __construct() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: GET");
        parent::__construct();
        $this->load->model('eshop_api_model');
        $this->data['thumbs'] = base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
		$this->load->model('eshop_model');
        $this->load->model('shop_model');
        $this->load->model('pos_model');
		$this->load->helper('genfun_helper');
		$this->data['user_id'] = $this->session->userdata('id');
        $this->data['user_name'] = $this->session->userdata('name');
		$this->data['currency_symbol'] = $this->Settings->symbol;
        $this->data['currency'] = $this->Settings->default_currency;
        $this->data['shopinfo'] = $this->storeInfo();
		$this->data['shop_pagename'] = $this->uri->segment(2);
		$this->data['eshop_settings'] = $this->eshop_settings = $this->eshop_model->getEshopSettings(1);
		$this->pos_settings = $this->site->get_pos_setting();
        $this->data['pos_settings'] = $this->pos_settings;
    }    

     private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }
     
    

    public function featured_product(){
        
      $product =  $this->eshop_api_model->getFeaturedProducts();
        $ProductArr = array();
        foreach($product as $row){
            $veriant = $this->eshop_api_model->getProductVeriantsById($row->id);
            $row->option = ($veriant)?$veriant :False;
            $ProductArr[]=$row;
        }
        
        echo $this->json_op($ProductArr);
    } 
    
  /* public function popular_categories(){
        
        $category =  $this->eshop_api_model->getPopularCategories();
        echo $this->json_op($category);
    }*/
	public function get_brands_list() {
		$brand = $this->eshop_api_model->get_brands();
			foreach($brand as $brand_value){
				if (file_exists('assets/mdata/'.$this->Customer_assets.'/uploads/' . $brand_value->image)) {
					$checkimage = TRUE;
				} else {
					$brand_value->image = null;
					$checkimage = FALSE;
				}
				if($brand_value->image=='' || $checkimage == FALSE) {
					$colors = array('text-info', 'text-danger', 'text-success', 'text-light', 'text-warning', 'text-primary');
					$color_random = array_rand($colors);
					$brand_value->color = $colors[$color_random];
				}
				$data[] = $brand_value;
			}
		echo $this->json_op($data);
	}
    public function popular_categories(){
		$category =  $this->eshop_api_model->getPopularCategories();
		$data  = array();
		foreach($category as $category_value){
			if (file_exists('assets/mdata/'.$this->Customer_assets.'/uploads/' . $category_value->image)) {
				$checkimage = TRUE;
			} else {
				$category_value->image = null;
				$checkimage = FALSE;
			}
			if($category_value->image=='' || $checkimage == FALSE) {
				$colors = array('text-info', 'text-danger', 'text-success', 'text-light', 'text-warning', 'text-primary');
				$color_random = array_rand($colors);
				$category_value->color = $colors[$color_random];
			}
			$data[] = $category_value;
		}
		
		 echo $this->json_op($data);
    }
   public function get_categories(){
       $ParentCat = $this->eshop_api_model->get_parent_categories();
	   $DataArr = array();
	   foreach($ParentCat as $val){
			$Subcategories = array();
		    $ChildCat = $this->eshop_api_model->get_parent_categories($val->id);
			foreach($ChildCat as $child_val){
				$Subcategories[]=array(
					'id'=>$child_val->id,
					'name'=>$child_val->name,
					'code'=>$child_val->code,
					'parent_id'=>$child_val->parent_id,
			    );
			}
			$DataArr[]=array(
				'id'=>$val->id,
				'name'=>$val->name,
				'code'=>$val->code,
				'parent_id'=>$val->parent_id,
				'subcategories'=>$Subcategories,
			);
	   }
	   
        echo $this->json_op($DataArr);
    }


    /*------------------------- 24-06-2020   ------------------------------*/
    /**
     * Max Sales Product list
     */
    
    public function populerproduct(){
        $popular = $this->eshop_api_model->getpopulerProduct();
         $ProductArr = array();
        foreach($popular as $row){
            $veriant = $this->eshop_api_model->getProductVeriantsById($row->id);
            $row->option = ($veriant)?$veriant :False;
            $ProductArr[]=$row;
        }
        
        echo $this->json_op($ProductArr);
    }

   public function categoryproducts(){
        $categoryproduct = $this->eshop_api_model->getPopularCategoryProducts();
         $ProductArr = array();
        foreach($categoryproduct as $row){
            $veriant = $this->eshop_api_model->getProductVeriantsById($row->id);
            $row->option = ($veriant)?$veriant :False;
            $ProductArr[]=$row;
        }
        
        echo $this->json_op($ProductArr);
        echo $this->json_op($categoryproduct);
    }
    /**
     * Product list
     */
	 public function product_list($category_id=null, $pageno=1, $type=null) {
        
        $product_list = $this->eshop_api_model->getCategoryProducts($category_id, $pageno, 20, $type);
        $this->data['count'] = $product_list['count'];
        $this->data['totalPages'] = $product_list['totalPages'];
        $this->data['msg'] = $product_list['msg'];
        $this->data['category_id'] = $category_id;
        $this->data['pageno'] = $pageno;
		if($this->data['count']!=0){
		foreach($product_list['items'] as $row){
                        // && @getimagesize($this->data['thumbs'].$row->image)
			if(!empty($row->image)){
//echo $row->name.' '.$row->image.'<br/>';
				$image_src = $this->data['thumbs'].$row->image;
				$image_name = $row->name;
			} else {
				$image_src = $this->data['thumbs'].'no_image.jpg';
				$image_name = 'no_image';
			}
			
			$veriant_row = $this->eshop_api_model->getProductVeriantsById($row->id,1);
			$option_id = '';
			$option_name = '';
			$option_price = 0;
			$option_quantity = 0;
			if(!empty($veriant_row)){
				foreach($veriant_row as $variant_data){
					$option_id = $variant_data->id;
					$option_name = $variant_data->name;
					$option_price = $variant_data->price;
					$option_quantity = $variant_data->quantity;
				}
			}
			
			
			$product_item_id = $row->id . ( $option_id ? '_'.$option_id : '');
			
			$now = strtotime(date('Y-m-d H:i:s'));
			$promo_price = 0;
			if($row->promotion == 1 && strtotime($row->start_date) <= $now && strtotime($row->end_date) >= $now ){
			   $promo_price = $product_price = (float)$row->promo_price + (float)$option_price; 
			} else {
			   $product_price = (float)$row->price + (float)$option_price; 
			}   
			
			if($row->tax_method == 1){
				$product_price += (float)($product_price * $row->tax_rate / 100);
			}
			$DelPrice='';
			if($promo_price) {
				$DelPrice=$row->price + $option_price;
			}
			$stocks = round($option_quantity ? $option_quantity : $row->quantity);
			$veriant = $this->eshop_api_model->getProductVeriantsById($row->id);
			
			$this->data['items'][] = array(
				'id' => $row->id,
				'code' => $row->code,
				'name' => $row->name,
				'unit' => $row->unit,
				'price' => $row->price,
				'quantity' => $row->quantity,
				'image' => $image_src,
				'image_name' => $image_name,
				'tax_rate_id' => $row->tax_rate_id,
				'tax_rate' => $row->tax_rate,
				'tax_name' => $row->tax_name,
				'tax_method' => $row->tax_method,
				'category_id' => $row->category_id,
				'subcategory_id' => $row->subcategory_id,
				'promotion' => $row->promotion,
				'promo_price' => $row->promo_price,
				'start_date' => $row->start_date,
				'end_date' => $row->end_date,
				'sale_unit' => $row->sale_unit,
				'unit_name' => $row->unit_name,
				'option_id' => $option_id,
				'option_name' => $option_name,
				'option_price' => $option_price,
				'option_quantity' => $option_quantity,
				'product_item_id' => $product_item_id,
				'calculate_promo_price' => $promo_price,
				'product_price' => $product_price,
				'del_price' => $DelPrice,
				'stocks' => $stocks,
				'option' => ($veriant)?$veriant :False,
			);
	 }
	 }
		echo $this->json_op($this->data);
		//echo '<pre>';
		//print_r($this->data); exit;
		//$this->load_shop_view($this->shoptheme . '/product_list', $this->data);
    }
	function addToCart($product_item_id, $CartItemQty){
		$product = explode('_', $product_item_id);
        $product_id = $product[0];
        $variant_id = $product[1]? $product[1] : '';
		$this->getProductVariantDetails($product_id, $variant_id, $CartItemQty);
	}
	function getProductVariantDetails($ProductId, $OptionId, $CartItemQty=0){
		$Result = $this->eshop_api_model->getProductVariantDetails($ProductId, $OptionId);
		
		$Arr = array();
		if(!empty($Result)){
			foreach($Result as $row){
				$product_item_id = $row->id . ( $row->option_id ? '_'.$row->option_id : '');
			
			$now = strtotime(date('Y-m-d H:i:s'));
			$promo_price = 0;
			if($row->promotion == 1 && strtotime($row->start_date) <= $now && strtotime($row->end_date) >= $now ){
			   $promo_price = $product_price = (float)$row->promo_price + (float)$row->option_price; 
			} else {
			   $product_price = (float)$row->price + (float)$row->option_price; 
			}   
			
			if($row->tax_method == 1){
				$product_price += (float)($product_price * $row->tax_rate / 100);
			}
			$DelPrice='';
			if($promo_price) {
				$DelPrice=$row->price + $row->option_price;
			}
			if($row->option_id==null){ 
				$stocks = round($row->option_quantity ? $row->option_quantity : $row->quantity);
			}else{
				if($row->option_quantity==null)
					$stocks = 0;
				else
					$stocks = round($row->option_quantity ? $row->option_quantity : $row->quantity);
			}
// && @getimagesize($this->data['thumbs'].$row->image)
			if(!empty($row->image)){
				$image_src = $this->data['thumbs'].$row->image;
				$image_name = $row->name;
			} else {
				$image_src = $this->data['thumbs'].'no_image.jpg';
				$image_name = 'no_image';
			}
			
				$Arr=array(
					'id' => $row->id,
					'code' => $row->code,
					'name' => $row->name,
					'unit' => $row->unit,
					'price' => $row->price,
					'quantity' => $row->quantity,
					'image' => $image_src,
					'image_name' => $image_name,
					'tax_rate_id' => $row->tax_rate_id,
					'tax_rate' => $row->tax_rate,
					'tax_name' => $row->tax_name,
					'tax_method' => $row->tax_method,
					'category_id' => $row->category_id,
					'subcategory_id' => $row->subcategory_id,
					'promotion' => $row->promotion,
					'promo_price' => $row->promo_price,
					'start_date' => $row->start_date,
					'end_date' => $row->end_date,
					'sale_unit' => $row->sale_unit,
					'unit_name' => $row->unit_name,
					'option_id' => $row->option_id,
					'option_name' => $row->option_name,
					'option_price' => $row->option_price,
					'option_quantity' => $row->option_quantity,
					'product_item_id' => $product_item_id,
					'calculate_promo_price' => $promo_price,
					'product_price' => $product_price,
					'del_price' => $DelPrice,
					'stocks' => $stocks,
					'CartItemQty' => $CartItemQty,
				);
			}
		}
		echo $this->json_op($Arr);
	}
	public function product_details($product_item) {
		//$this->data['product_item_id'] = $product_item;
        $product = explode('_', $product_item);
        $product_id = $product[0];
        $variant_id = $product[1]? $product[1] : '';
        $products = $this->eshop_api_model->getProductVariantDetails($product_id, $variant_id);
		$Arr = array();
		if(!empty($products)){
			foreach($products as $row){
				$product_item_id = $row->id . ( $row->option_id ? '_'.$row->option_id : '');
			
			$now = strtotime(date('Y-m-d H:i:s'));
			$promo_price = 0;
			if($row->promotion == 1 && strtotime($row->start_date) <= $now && strtotime($row->end_date) >= $now ){
			   $promo_price = $product_price = (float)$row->promo_price + (float)$row->option_price; 
			} else {
			   $product_price = (float)$row->price + (float)$row->option_price; 
			}   
			
			if($row->tax_method == 1){
				$product_price += (float)($product_price * $row->tax_rate / 100);
			}
			$DelPrice='';
			if($promo_price) {
				$DelPrice=$row->price + $row->option_price;
			}
			if($row->option_id==null){ 
				$stocks = round($row->option_quantity ? $row->option_quantity : $row->quantity);
			}else{
				if($row->option_quantity==null)
					$stocks = 0;
				else
					$stocks = round($row->option_quantity ? $row->option_quantity : $row->quantity);
			}
// && @getimagesize($this->data['thumbs'].$row->image)
			if(!empty($row->image)){
				$image_src = $this->data['thumbs'].$row->image;
				$image_name = $row->name;
			} else {
				$image_src = $this->data['thumbs'].'no_image.jpg';
				$image_name = 'no_image';
			}
			
				$Arr=array(
					'id' => $row->id,
					'code' => $row->code,
					'name' => $row->name,
					'unit' => $row->unit,
					'price' => $row->price,
					'quantity' => $row->quantity,
					'image' => $image_src,
					'image_name' => $image_name,
					'tax_rate_id' => $row->tax_rate_id,
					'tax_rate' => $row->tax_rate,
					'tax_name' => $row->tax_name,
					'tax_method' => $row->tax_method,
					'category_id' => $row->category_id,
					'subcategory_id' => $row->subcategory_id,
					'promotion' => $row->promotion,
					'promo_price' => $row->promo_price,
					'start_date' => $row->start_date,
					'end_date' => $row->end_date,
					'sale_unit' => $row->sale_unit,
					'unit_name' => $row->unit_name,
					'option_id' => $row->option_id,
					'option_name' => $row->option_name,
					'option_price' => $row->option_price,
					'option_quantity' => $row->option_quantity,
					'product_item_id' => $product_item_id,
					'calculate_promo_price' => $promo_price,
					'product_price' => $product_price,
					'del_price' => $DelPrice,
					'stocks' => $stocks,
					'details' => $row->product_details,
				);
			}
		}
        $this->data['product'] = $Arr;
		/********************* PRODUCTS VARIANTS************************/
		$ArrVariant=[];
        $variants = $this->eshop_api_model->getProductVeriantsById($product_id);
		if(!empty($variants) && is_array($variants)){
			foreach ($variants as $variant) {
				if($variant_id == $variant->id) continue;
				$product_item_option_id = $product_id .  '_' . $variant->id;
				$ArrVariant[]=array(
					'variant_name'=>$variant->name,
					'product_item_option_id'=>$product_item_option_id
				);
			}
		}
		$this->data['variants']=$ArrVariant;
		/********************* PRODUCTS IMAGES************************/
        $product_images  = $this->eshop_api_model->getProductsImages($product_id);
		$ArrImg[]=$image_src;
		if(!empty($product_images) && is_array($product_images)){
			foreach ($product_images as $product_image) {
// && @getimagesize($this->data['thumbs'].$product_image)
				if(!empty($product_image)){
					$ArrImg[]=$this->data['thumbs'].$product_image;
				}
			}
		}
		$this->data['product_images']=$ArrImg;
		/*********************REALTED PRODUCTS************************/
		$ReletedProductArr = array();
		$Releted_products = $this->eshop_api_model->getCategoryProducts($products[0]->category_id,1, 10);
		if(!empty($Releted_products) && is_array($Releted_products)){
			
			foreach ($Releted_products['items'] as $row) {
				if($product_id == $row->id) continue;
// && @getimagesize($this->data['thumbs'].$row->image)
				if(!empty($row->image)){
					$image_src = $this->data['thumbs'].$row->image;
					$image_name = $row->name;
				} else {
					$image_src = $this->data['thumbs'].'no_image.jpg';
					$image_name = 'no_image';
				}
				
				$veriant_row = $this->eshop_api_model->getProductVeriantsById($row->id,1);
				$option_id = '';
				$option_name = '';
				$option_price = 0;
				$option_quantity = 0;
				if(!empty($veriant_row)){
					foreach($veriant_row as $variant_data){
						$option_id = $variant_data->id;
						$option_name = $variant_data->name;
						$option_price = $variant_data->price;
						$option_quantity = $variant_data->quantity;
					}
				}
				
				
				$product_item_id = $row->id . ( $option_id ? '_'.$option_id : '');
				
				$now = strtotime(date('Y-m-d H:i:s'));
				$promo_price = 0;
				if($row->promotion == 1 && strtotime($row->start_date) <= $now && strtotime($row->end_date) >= $now ){
				   $promo_price = $product_price = (float)$row->promo_price + (float)$option_price; 
				} else {
				   $product_price = (float)$row->price + (float)$option_price; 
				}   
				
				if($row->tax_method == 1){
					$product_price += (float)($product_price * $row->tax_rate / 100);
				}
				$DelPrice='';
				if($promo_price) {
					$DelPrice=$row->price + $option_price;
				}
				$stocks = round($option_quantity ? $option_quantity : $row->quantity);
				$veriant = $this->eshop_api_model->getProductVeriantsById($row->id);
				
				$ReletedProductArr[] = array(
					'id' => $row->id,
					'code' => $row->code,
					'name' => $row->name,
					'unit' => $row->unit,
					'price' => $row->price,
					'quantity' => $row->quantity,
					'image' => $image_src,
					'image_name' => $image_name,
					'tax_rate_id' => $row->tax_rate_id,
					'tax_rate' => $row->tax_rate,
					'tax_name' => $row->tax_name,
					'tax_method' => $row->tax_method,
					'category_id' => $row->category_id,
					'subcategory_id' => $row->subcategory_id,
					'promotion' => $row->promotion,
					'promo_price' => $row->promo_price,
					'start_date' => $row->start_date,
					'end_date' => $row->end_date,
					'sale_unit' => $row->sale_unit,
					'unit_name' => $row->unit_name,
					'option_id' => $option_id,
					'option_name' => $option_name,
					'option_price' => $option_price,
					'option_quantity' => $option_quantity,
					'product_item_id' => $product_item_id,
					'calculate_promo_price' => $promo_price,
					'product_price' => $product_price,
					'del_price' => $DelPrice,
					'stocks' => $stocks,
					'option' => ($veriant)?$veriant :False,
				);
			}
		}
		$this->data['releted_products']=$ReletedProductArr;
        echo $this->json_op($this->data);
    }
    
    /**
     * Get Products name
     */
    public function suggestions(){
        $result = $this->eshop_api_model->search($_GET['search'], $limit = 10);       
        echo json_encode($result);
    }
    
    /**
     * Search Product
     * @param type $term
     * @param type $pageno
     */
    public function search_product($term=null, $pageno=1) {
       
        $product_list = $this->eshop_api_model->getSearchProducts(urldecode($term), $pageno, 20);
      
        $this->data['count'] = $product_list['count'];
        $this->data['totalPages'] = $product_list['totalPages'];
        $this->data['msg'] = $product_list['msg'];
        $this->data['pageno'] = $pageno;
		if($this->data['count']!=0){
		foreach($product_list['items'] as $row){
			if(!empty($row->image) && @getimagesize($this->data['thumbs'].$row->image)){
				$image_src = $this->data['thumbs'].$row->image;
				$image_name = $row->name;
			} else {
				$image_src = $this->data['thumbs'].'no_image.jpg';
				$image_name = 'no_image';
			}
			
			$veriant_row = $this->eshop_api_model->getProductVeriantsById($row->id,1);
			$option_id = '';
			$option_name = '';
			$option_price = 0;
			$option_quantity = 0;
			if(!empty($veriant_row)){
				foreach($veriant_row as $variant_data){
					$option_id = $variant_data->id;
					$option_name = $variant_data->name;
					$option_price = $variant_data->price;
					$option_quantity = $variant_data->quantity;
				}
			}
			
			
			$product_item_id = $row->id . ( $option_id ? '_'.$option_id : '');
			
			$now = strtotime(date('Y-m-d H:i:s'));
			$promo_price = 0;
			if($row->promotion == 1 && strtotime($row->start_date) <= $now && strtotime($row->end_date) >= $now ){
			   $promo_price = $product_price = (float)$row->promo_price + (float)$option_price; 
			} else {
			   $product_price = (float)$row->price + (float)$option_price; 
			}   
			
			if($row->tax_method == 1){
				$product_price += (float)($product_price * $row->tax_rate / 100);
			}
			$DelPrice='';
			if($promo_price) {
				$DelPrice=$row->price + $option_price;
			}
			$stocks = round($option_quantity ? $option_quantity : $row->quantity);
			$veriant = $this->eshop_api_model->getProductVeriantsById($row->id);
			
			$this->data['items'][] = array(
				'id' => $row->id,
				'code' => $row->code,
				'name' => $row->name,
				'unit' => $row->unit,
				'price' => $row->price,
				'quantity' => $row->quantity,
				'image' => $image_src,
				'image_name' => $image_name,
				'tax_rate_id' => $row->tax_rate_id,
				'tax_rate' => $row->tax_rate,
				'tax_name' => $row->tax_name,
				'tax_method' => $row->tax_method,
				'category_id' => $row->category_id,
				'subcategory_id' => $row->subcategory_id,
				'promotion' => $row->promotion,
				'promo_price' => $row->promo_price,
				'start_date' => $row->start_date,
				'end_date' => $row->end_date,
				'sale_unit' => $row->sale_unit,
				'unit_name' => $row->unit_name,
				'option_id' => $option_id,
				'option_name' => $option_name,
				'option_price' => $option_price,
				'option_quantity' => $option_quantity,
				'product_item_id' => $product_item_id,
				'calculate_promo_price' => $promo_price,
				'product_price' => $product_price,
				'del_price' => $DelPrice,
				'stocks' => $stocks,
				'option' => ($veriant)?$veriant :False,
			);
	 }
	 }
		echo $this->json_op($this->data);
		
    }
    public function checkout_details() {
		$Data = json_decode(file_get_contents('php://input'),true);
		$productIds = [];
		foreach ($Data['slitems'] as $key => $val) {
			$productIds[] = $val['id'];
		}
		$items = $this->shop_model->getProductInfo($productIds);
		$eshop_order_tax = $this->shop_model->getOrdertax();
		$itemcount = 0;
		
		foreach ($Data['slitems'] as $key => $value) {
			$itemcount++;
			$ArrExplode = explode('_', $key);
			$keys = $ArrExplode[0];
			$CustomerId = 0;
			if ($this->session->userdata('id') > 0) {
				$CustomerId = $this->session->userdata('id');
			}
			$items[$key] = (array) get_product_price($items[$keys], $CustomerId, $eshop=1);
			$CartData[$key] = $items[$key];
			$CartData[$key]['qty'] = $value['CartItemQty'];
			$CartData[$key]['option_id'] = $value['option_id'];
			$CartData[$key]['option_name'] = $value['option_name'];
			$CartData[$key]['option_price'] = $value['option_price'];
		}//end foreach
        
        
		$taxes['methods'] = $this->getTaxMethods();
        $taxes['attribs'] = $this->getTaxAttribs();
		$cartSubtotal = $totalTax = $grossTotal = $ordertax = $total_qty = 0 ;
		foreach ($CartData as $key => $product) {
            $itemTax = $itemPrice = $cartItemSubTotal= $cartItemTotal =  0;
			$tax_type = $taxes['methods'][$product['tax_rate']]['type'];
			$tax_rate = $taxes['methods'][$product['tax_rate']]['rate'];
			$product['option_price'] = $product['option_price'] ? $product['option_price'] : 0;    
			$inclusiveInfo = "";
			$itemPrice = ($product['price'] + $product['option_price']);
           if($product['tax_method'] == 0) {
                if($tax_rate) {
                  $taxType = 'Tax-Inclusive' ;
                   //Inclusive Tax Type percentage
                    if($tax_type == 1){                        
                        $itemPrice = (((($product['price'] + $product['option_price']) * 100) / (100 + $tax_rate)));
                        if($product['tax_rate']>0){
                           $itemTax = (($product['price'] + $product['option_price'])- $itemPrice) * $product['qty'];
                        }
                        else{
                           $itemTax = 0;
                        }
                    }
                    //Tax Type Fixed
                    if($tax_type == 2){
                    	
                        $itemPrice = (($product['price'] + $product['option_price']) - $tax_rate);
                        if($product['tax_rate']>0){
                            $itemTax = $tax_rate * $product['qty'];
                        }
                        else {
                            $itemTax = 0;
                        }
                    }
                   
                    $inclusiveTaxAmt = (($product['price']+$product['option_price']) - $itemPrice);
                    $inclusiveInfo   = '<br/><i class="text-warning">'.$itemPrice .' + (Tax: '.$inclusiveTaxAmt.')</i>';
                }                                    
            } else  {   
               $itemPrice = $product['price'] + $product['option_price'];
                //Exclusive Tax Type percentage
                if($tax_type == 1){
                    $itemTax = (($itemPrice * $tax_rate / 100) * $product['qty']);
                }
                //Tax Type Fixed
                if($tax_type == 2){
                    $itemTax = $tax_rate * $product['qty'];
                }                
            }   
			$cartItemSubTotal = ( $itemPrice  * $product['qty']);
			$cartItemTotal = $cartItemSubTotal;
			$cartSubtotal += $cartItemSubTotal;
			$totalTax += $itemTax;
        
			$productId = $product['id'];
			$total_qty += $product['qty'];
			$real_unit_price = $product['option_price'] ? $product['option_price'] +$product['real_unit_price'] : $product['real_unit_price'];
			$actual_real_unit_price = $product['real_unit_price'];
			/*****************Main Details Product*************************/
			
			/***********************Product calulations*****************/
            
            $gstAttrs = (!empty($taxes['methods']) && isset($taxes['methods'][$product['tax_rate']])) ? $taxes['methods'][$product['tax_rate']]['tax_config'] : '';
            $item_subtotal = $cartItemTotal;
            $item_tax_rate = ($tax_rate) ? $tax_rate : 0;
            $item_tax_total = ($itemTax) ? $itemTax : 0;
            
            $cart['items'][$key] = array(
                'item_id' => $product['id'],
                'qty' => $product['qty'],
                'item_tax_method' => $product['tax_method'],
                'item_tax_type' => $tax_type,
                'item_tax_rate' => $tax_rate,
                'real_unit_price' => $real_unit_price,
                'actual_real_unit_price' => $actual_real_unit_price,
                'item_price' => str_replace( ',', '', $itemPrice ),
                'item_tax_total' => $itemTax,
                'item_price_total' => str_replace( ',', '', $cartItemTotal ),
                'item_option_id' => $product['option_id'],
                'item_option_name' => $product['option_name'],
                'item_option_price' => $product['option_price'],
                'total_amount' => number_format(str_replace(",","",$itemPrice * $product['qty']),2),
            );
			$cart['items'][$key]['code'] = $product['code'];
			$cart['items'][$key]['name'] = $product['name'];
            $cart['items'][$key]['image'] = $product['image'];
            $cart['items'][$key]['hsn_code'] = $product['hsn_code'];
            $cart['items'][$key]['brand'] = $product['brand'];
			$cart['items'][$key]['vname'] = $product['option_name'];

            //To set Tax Attributes.
            if (!empty($gstAttrs)) {
                foreach ($gstAttrs as $gstattr) {
                    $cart['items'][$key]['tax_attr'][$gstattr['code']] = [
                        'percentage' => number_format($gstattr['percentage'], 2),
                        'name' => $gstattr['name'],
                        'taxamt' => ($item_subtotal * $gstattr['percentage'] / 100),
                    ];
                }//end foreach.
            }
		}
		
		$cart['itemcount'] = $itemcount;
		if($eshop_order_tax['rate']) {
            $ordertax_id = $eshop_order_tax['id'];
            $order_tax = $eshop_order_tax['name'];
            $order_tax_rate = $eshop_order_tax['rate'];
            $order_tax_type = $eshop_order_tax['type'];
            if($order_tax_type == 1 ){
                $ordertax = ($totalTax + $cartSubtotal)*($order_tax_rate)/100;
            } else if($order_tax_type==2){
                $ordertax = $order_tax_rate; //Fixed order tax amount
            }
        } else {
            $ordertax = 0;
        }
        $grossTotal =  $cartSubtotal + $totalTax + $ordertax;
		
		$cart['cart_sub_total'] = number_format($cartSubtotal,4);
		$cart['display_cart_sub_total'] = number_format(str_replace(",","",$cart['cart_sub_total']),2);
        $cart['cart_tax_total'] = str_replace(",","",number_format($totalTax,4));
		$cart['display_cart_tax_total'] = number_format(str_replace(",","",$cart['cart_tax_total']),2);
        $cart['order_tax_total'] = number_format($ordertax,4);
        //$cart['cart_gross_rounding'] = $cartData['cart_gross_rounding'];
        $cart['cart_gross_total'] = number_format($grossTotal,4);
        $cart['item_quantity_total'] = $total_qty;            
        $cart['order_tax_id'] = $ordertax_id;                    
        $cart['order_tax_name'] = $eshop_order_tax['name'];
		
		$grossTotalSend = str_replace(",","",number_format(str_replace(",","",$cartSubtotal )+str_replace(",","",$totalTax),2));
        $grossRoundingcal = number_format(round($grossTotalSend) - $grossTotalSend,4);
        $grossTotalSend = round($grossTotalSend);
        $cart['cart_gross_rounding'] = $grossRoundingcal;
        $cart['cart_gross_total'] = $grossTotalSend;
        $grosstotal = $cart['total_order_amount'] = number_format(str_replace(",","",$cart['cart_sub_total'] )+str_replace(",","",$cart['cart_tax_total']),2);
		
		$grosstotalnum = str_replace( ',', '', $grosstotal );
		if( is_numeric( $grosstotalnum ) ) {
		   $grosstotal = $grosstotalnum;
		}
		$cart['total_payable_amount'] = $grosstotal = number_format(str_replace(",","",$cart['cart_gross_total']),2);
		//$ordert = array_values($cart['cart_order_tax']);
		//$cart['ordert'] = $ordert[0];
        $this->data['cart'] = $cart;
		
        /**************Payment Methods********************/
        $this->data['payment_methods'] = $this->payment_methods();
		/**************State********************/
        $this->data['state'] = $this->shop_model->getState();
		/**************Shipping Methods********************/
		$billing_shipping = $this->shop_model->get_billing_shipping($this->data['user_id']);
        $this->data['billing_shipping'] = array();
        if ($billing_shipping === false) {
            $this->data['customer'] = (array) $this->customer_info();
        } else {
            $this->data['billing_shipping'] = (array) $billing_shipping[0];
        }
		//print_r($this->data['billing_shipping']); exit;
		$shipingAmt=0;
		$shipingAmt1=0;
		$shipingAllTime=1;
		$shipping_methods = $this->shipping_methods();
		$ArrShippingMethods = array();
		$k=1;
		if(is_array($shipping_methods)){

			foreach ($shipping_methods as $key => $shippings) {
				if($shippings['code']=='home_delivery'){
					$var = floatval(preg_replace('/[^\d.]/', '', $cart['cart_gross_total']));
					
					if($var >= $this->data['shopinfo']['eshop_free_delivery_on_order']){
						$shipingAmt = 0.00;
					}else{
						$shipingAmt = number_format($shippings['price'],2);
					}
					$shipingAmt1=$shipingAmt;
					$shipingAllTime=$shippings['all_time'];
				}else{
					
					if($cart['cart_gross_total'] >= $this->data['shopinfo']['eshop_free_delivery_on_order']){
						$shipingAmt = 0.00;
					}else{
						$shipingAmt = number_format($shippings['price'],2);
					}
				}
				$ship_checked=false;
				if($k==1)
					$ship_checked=true;
				$ArrShippingMethods[]=array(
					'k'=>$k++,
					'ship_checked'=>$ship_checked,
					'id'=>$shippings['id'],
					'name'=>$shippings['name'],
					'code'=>$shippings['code'],
					'price'=>$shippings['price'],
					'is_active'=>$shippings['is_active'],
					'is_deleted'=>$shippings['is_deleted'],
					'all_time'=>$shippings['all_time'],
					'shipping_amount'=>$shipingAmt,
					'display_shipping_amount'=>($cart['cart_gross_total'] >= $this->data['shopinfo']['eshop_free_delivery_on_order'] ) ? '0.00 <del class="text-danger"> Rs.'.number_format($shippings['price'],2).'</del>' : number_format($shippings['price'],2),
					
				);
			}
		}
		//echo '<pre>';
		//print_r($ArrShippingMethods); exit;
		/**************Total Billing Amount********************/
		$this->data['shipingAmt'] = $shipingAmt1;
		$this->data['shipingAllTime'] = $shipingAllTime;
		$this->data['ShippingMethods'] = $ArrShippingMethods;
		$this->data['total_billing_amount'] = (number_format($shipingAmt1 + (str_replace(",","",$cart['cart_gross_total'])),2));
		$this->data['cart_serialize'] = serialize($cart);
		echo $this->json_op($this->data);
		
    }
	public function customer_info() {
        return $this->shop_model->getCustomerInfo();
    }

    public function shipping_methods() {

        $res = $this->eshop_model->getShippingMethods(array('is_deleted' => 0, 'is_active' => 1));

        return $res;
    }

    private function payment_methods($flag = NULL) {

        $res = $this->pos_model->getSetting();


        $_eshop_cod = isset($res->eshop_cod) && !empty($res->eshop_cod) ? $res->eshop_cod : NUll;
        $_default_eshop_pay = isset($res->default_eshop_pay) && !empty($res->default_eshop_pay) ? $res->default_eshop_pay : NUll;

        $_instamozo = isset($res->instamojo) && !empty($res->instamojo) ? $res->instamojo : NUll;
        $_ccavenue = isset($res->ccavenue) && !empty($res->ccavenue) ? $res->ccavenue : NUll;
        $_authorize = isset($res->authorize) && !empty($res->authorize) ? $res->authorize : NUll;
        $result = $payment_list = array();
        if ($_eshop_cod):
            $payment_list['cod'] = 'COD';
        endif;
        switch ($_default_eshop_pay) {

            case 'instamojo':
                if ($_instamozo):
                    $payment_list['instamojo'] = 'Credit Card / Debit Card / Netbanking';
                endif;
                break;

            case 'authorize':
                if ($_authorize):
                    $payment_list['authorize'] = 'Credit Card / Debit Card';
                endif;
                break;

            case 'ccavenue':
                if ($_ccavenue):
                    $payment_list['ccavenue'] = 'CCavenue';
                endif;
                break;

            default:

                break;
        }
        if ($flag == 1) {
            return $payment_list;
        }
        if (count($payment_list)):
            $i = 1;
            foreach ($payment_list as $payment_key => $payment_name) {
                $result[$i]['id'] = $i;
                $result[$i]['code'] = $payment_key;
                $result[$i]['name'] = $payment_name;
                $i++;
            }
        endif;

        return $result;
    }
	public function getTaxMethods() {

        $result = $this->pos_model->getAllTaxRates();
        foreach ($result as $key => $method) {
            $data[$method['id']] = $method;
        }

        return $data;
    }

    public function getTaxAttribs() {

        $result = $this->pos_model->getTaxAttributes();

        foreach ($result as $key => $attr) {
            $data[$attr->id] = (array) $attr;
        }

        return $data;
    }
	public function storeInfo() {
        $this->load->model('settings_model');
        //$res = $this->eshop_model->getSettings();
        $res = $this->eshop_model->getPosSettings();

        $config = $this->ci->config;
        $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : null;
        $res->merchant_phone = $merchant_phone;
        //$res->offline_sale_reff=$this->site->getNextReference('offapp');
        if (is_object($res)):
            $data = array();
            foreach ($res as $key => $value) {
                $data[$key] = $value;
            }
            return $data;
        endif;

        return false;

        //return $storeInfo = $this->shop_model->storeDetails();
    }
	public function getSloteTime($id){
        
       $getdata =  $this->eshop_model->getsloteTiming($id);
       
       if($getdata){
           $htmlpass = '<select name="time_slotes" class="form-control variantbox">';
           foreach($getdata as $values){
               $htmlpass.='<option> '.date('g:i A',strtotime($values->start_time)).' - '.date('g:i A',strtotime($values->end_time)).'</option>';
           }
           $htmlpass.='</select>';
           echo $htmlpass;
       } else {
           return FALSE;
       }
    }
}    

