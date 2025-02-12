<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH . "libraries/razorpay/razorpay-php/Razorpay.php");

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class Webshop extends MY_Controller {

    public $assets;
    public $viewpath;
    public $data;
    public $webshop_settings;
    public $is_admin_login;

    public function __construct() {

        parent::__construct();

        $this->load->library('sma');

        $this->load->model('site');

        $this->load->model('webshop_model');

        $this->load->helper('webshop_helper');

        $this->data['assets'] = base_url("themes/default/assets/webshop/");

        $this->data['uploads'] = base_url("assets/mdata/$this->Customer_assets/uploads/");

        $this->data['thumbs'] = base_url("assets/mdata/$this->Customer_assets/uploads/thumbs/");

        $this->data['images'] = base_url("assets/mdata/$this->Customer_assets/uploads/images/");

        $this->data['is_admin_login'] = $this->is_admin_login = ($this->loggedIn && ($this->Owner || $this->Admin)) ? true : false;

        $this->active_webshop = (bool) $this->Settings->active_webshop ? $this->Settings->active_webshop : 0;
        
        if (!$this->active_webshop && $this->uri->segment(2) != 'service_off') {
            redirect('webshop/service_off');
        }

        $this->data['webshop_settings'] = $this->webshop_settings = $this->webshop_model->get_webshop_settings();

        $this->data['home_page'] = $this->webshop_settings->home_page;

        $this->data['theme_color'] = !empty($this->webshop_settings->theme_color) ? $this->webshop_settings->theme_color : 'orange';

        $this->data['strip_color'] = !empty($this->webshop_settings->header_strip_style) ? $this->webshop_settings->header_strip_style : 1;

        $this->data['categories'] = $this->webshop_model->get_categories();

        $this->data['main_categories'] = $this->data['categories']['main'];
        
        $this->data['webshop_pos_settings'] = $this->webshop_model->get_webshop_pos_settings();
     
        $category_id = isset($_GET['catid']) && $_GET['catid'] != '' ? $_GET['catid'] : (($this->uri->segment(2) == "category_products" && !empty($this->uri->segment(3))) ? $this->uri->segment(3) : null);

        $this->data['category_brands'] = $this->webshop_model->get_category_brands($category_id);

        if (!empty($this->data['category_brands'])) {
            $this->data['brands_list'] = $this->get_brand_list($this->data['category_brands']);
        }

        $this->data['all_brands'] = $this->webshop_model->get_all_brands();


        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $this->data['cart_items'] = $_SESSION['cart'];
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();
        }

        $this->data['wishlist_count'] = $this->webshop_model->get_wishlist_count($this->session->webshop->user_id);

        $this->data['custom_pages'] = $this->webshop_model->getCustomPages();
    }

    public function service_off() {
        $this->load_view("service_off", $this->data);
    }

    public function load_view($method = '', $data = array()) {

        $this->load->view('default/views/webshop/' . $method, $data);
    }

    public function webshop_request() {

        $action = $_POST['action'];
        $postData = $_POST;

        switch ($action) {
            case "get_section":

                $this->get_sections($postData);

                break;

            case "get_product_images":

                $this->set_product_gallery($postData);

                break;

            case "add_to_cart":

                $this->add_to_cart($postData);

                break;

            case "update_cart":

                $this->update_cart($postData);

                break;

            case "add_to_wishlist":

                $this->add_to_wishlist($postData);

                break;

            case "remove_from_wishlist":

                $this->remove_from_wishlist($postData);

                break;

            case "load_header_cart":

                $this->load_header_cart_items();

                break;

            case "remove_cart_item":

                $this->remove_cart_item($postData);

                break;

            case "apply_coupon":

                $this->apply_coupon($postData);

                break;

            case "manage_address":

                $this->manage_address($postData);

                break;

            case "manage_eshop_category":
                
                $this->manage_eshop_category($postData);
                
                break;
            
            default:
                break;
        }//end switch.
    }

    public function index() {

        if (!$this->active_webshop) {

            $this->load_view("service_off", $this->data);
        } else {

            $this->data['themeSections'] = $themeSections = $this->webshop_model->get_theme_sections($this->webshop_settings->home_page);

            $this->set_theme_sections_data($themeSections);

            $this->data['sliders'] = $this->webshop_model->get_sliders();

            $this->data['features'] = $this->webshop_model->get_features();

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();



            //        echo '<pre>';            
            //        print_r($category_products);
            //        echo '</pre>';

            $this->load_view("index", $this->data);
        }
    }

    public function get_brand_list($category_brands) {

        if (is_array($category_brands)) {
            foreach ($category_brands as $brands) {
                foreach ($brands as $brand) {
                    $data[] = $brand;
                }
            }
            return $data;
        }
        return false;
    }

    public function products() {

        $page = (isset($_GET['page']) && !empty($_GET['page'])) ? $_GET['page'] : 1;
        $limit = 12;

        if ($_GET['q'] == "cetegory") {
            $idHash = $_GET['id'];
            $data = $this->webshop_model->get_products_list('category', $idHash, $usedHash = TRUE, $limit, $page);

            $this->data['items_total'] = $data['items_total'];
            $this->data['listItems'] = $data['items'];
            //$this->data['product_variants']   = $data['product_variants'];
        }

        if ($_GET['q'] == "brand") {
            $idHash = $_GET['id'];
            $data = $this->webshop_model->get_products_list('brand', $idHash, $usedHash = TRUE, $limit, $page);

            $this->data['items_total'] = $data['items_total'];
            $this->data['listItems'] = $data['items'];
        }

        $this->data['idHash'] = $idHash;
        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $this->load_view("products", $this->data);
    }

    public function product_details() {

        $product_hash = $this->uri->segment(3);

        $this->data['product_details'] = $productDetails = $this->webshop_model->get_product_by_hash($product_hash);
        $product = $productDetails['item'];

        $this->data['product'] = $product;
        $this->data['product_variants'] = $productDetails['variants'];
        $this->data['gallary_images'] = $productDetails['images'];

        $this->data['active_search_category'] = $productDetails['item']['category_id'];

        $this->webshop_model->set_recent_viewed_product($productDetails['item']['id']);

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $categoryHash = ($product['subcategory_id']) ? md5($product['subcategory_id']) : md5($product['category_id']);
        $reletedItems = $this->webshop_model->get_products_list('category', $categoryHash, $usedHash = TRUE, 20);
        $this->data['related_products'] = $reletedItems['items'];

        $this->load_view("product_details", $this->data);
    }

    public function category_products() {

        $this->data['get_category_id'] = $product_category = $this->uri->segment(3);

        $this->data['idHash'] = md5($product_category);

        $this->data['active_search_category'] = $product_category;

        if ($this->uri->segment(4)) {
            $this->data['get_subcategory_id'] = $product_category = $this->uri->segment(4);
        }

        //$this->data['product_variants'] = $this->webshop_model->get_category_product_variants($product_category);
        // $this->data['listItems'] = $this->webshop_model->get_category_products($product_category);
        $idHash = md5($product_category);
        $data = $this->webshop_model->get_products_list('category', $idHash, $usedHash = TRUE, $limit, $page);

        $this->data['items_total'] = $data['items_total'];
        $this->data['listItems'] = $data['items'];

        $this->data['subcategories'] = $this->data['categories'][$this->data['get_category_id']];

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $this->load_view("category_products", $this->data);
    }

    public function search_products() {

        $this->data['search_keyword'] = $keyword = $this->input->get('search');

        $this->data['get_category_id'] = $category = $this->input->get('search_by_category');

        $this->data['active_search_category'] = $category ? $category : '';

        $this->data['product_variants'] = $this->webshop_model->get_category_product_variants($category);

        $this->data['listItems'] = $products = $this->webshop_model->search_category_products($keyword, $category);

        $list_item =[];
        foreach($this->data['listItems'] as $itemsList){
            $list_item[]= $itemsList['code'];
        }
        
       $other_product = $this->webshop_model->search_other_products($keyword, $category);
        $otherProductArray =[];
        foreach($other_product as $itemOtherProduct){
            if(!in_array($itemOtherProduct['code'],$list_item )){
                $otherProductArray[] = $itemOtherProduct;
            }
        }
        
         $this->data['otherItems'] = $otherProductArray;


       // $this->data['otherItems'] = $this->webshop_model->search_other_products($keyword, $category);

        $this->load_view("search_products", $this->data);
    }

    public function wishlist() {

        $wishlist = $this->webshop_model->get_wishlist($this->session->webshop->user_id);

        foreach ($wishlist as $list) {
            $products[] = $list->product_id;

            $this->data['wishlist_variants'][$list->product_id][] = $list->option_id;
        }

        $this->data['wishlist'] = $this->webshop_model->get_products_list('products', $products, true);

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

        $this->load_view("wishlist", $this->data);
    }

    public function compare() {

        $this->load_view("compare", $this->data);
    }

    public function cart() {

        if (!isset($_SESSION['cart'])) {
            redirect('webshop/index');
        }

        $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();
        $this->data['state_list'] = $this->webshop_model->get_state();

        $this->load_view("cart", $this->data);
    }

    public function checkout() {

        if (!isset($_SESSION['cart'])) {
            redirect('webshop/index');
        }

        $this->data['state_list'] = $this->webshop_model->get_state();
        $this->data['postdata'] = (!empty($_SESSION['postdata'])) ? $_SESSION['postdata'] : NULL;

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

         $customer_id = (int) $this->session->webshop->user_id;

            $this->data['customer_id'] = $customer_id;
            $this->data['addresses'] = $this->webshop_model->get_customer_address($customer_id);
          
        }

        $this->load_view("checkout", $this->data);
    }

    public function submit_order() {

        if ($this->input->post('submit_order')) {

            if (md5(date('Y-m-d H')) == $this->input->post('submit_order')) {

                if ($this->input->post('default_shipping_address') && $this->input->post('customer_id')) {
                    $customer_id = $this->input->post('customer_id');
                    $address_id = $this->input->post('default_shipping_address');
                    $address = $this->webshop_model->get_customer_address($customer_id, $address_id);
                    $billing_address = $address[$address_id];
                    $shipping_address = $address[$address_id];

                    $customer = $this->webshop_model->get_customer(['id' => $customer_id]);

                    $shipping_address_id = $billing_address_id = $address_id;
                } else {
                  if(isset($_POST['billing_address_1'])){
                    $billing_stateData = explode('~', $this->input->post('billing_state'));

                    $billing_phone = $this->input->post('billing_phone');
                    $billing_email = $this->input->post('billing_email');
                    $customer = $this->webshop_model->get_customer(['phone' => $billing_phone, 'email' => $billing_email]);

                    if (!$customer) {

                        $account_password = NULL;
                        if (!empty($this->input->post('account_password'))) {
                            $account_password = md5($this->input->post('account_password'));
                        }

                        $customerData = array(
                            "group_id" => '3',
                            "group_name" => 'customer',
                            "customer_group_id" => '1',
                            "customer_group_name" => 'General',
                            "price_group_id" => '2',
                            "price_group_name" => 'Standered',
                            "name" => $this->input->post('billing_first_name') . ' ' . $this->input->post('billing_last_name'),
                            "company" => $this->input->post('billing_company'),
                            "address" => $this->input->post('billing_address_1') . ' ' . $this->input->post('billing_address_2'),
                            "city" => $this->input->post('billing_city'),
                            "state" => $billing_stateData[0],
                            "state_code" => $billing_stateData[1],
                            "postal_code" => $this->input->post('billing_postcode'),
                            "country" => $this->input->post('billing_country'),
                            "phone" => $this->input->post('billing_phone'),
                            "email" => $this->input->post('billing_email'),
                            "password" => $account_password,
                        );

                        $customer = $this->webshop_model->add_customer($customerData);

                        $this->send_welcome_mail($customerData);
                    }

                   }else{
                          $customer_id = $this->input->post('customer_id');
                          $customer = $this->webshop_model->get_customer(['id' => $customer_id]);

                          $address_id =   $this->webshop_model->getAddressDefault($this->input->post('customer_id'),'default');    
                          $shipping_address_id = $billing_address_id = $address_id;
                    }


                    if (!empty($this->input->post('billing_address_id'))) {
                        $billing_address_id = $this->input->post('billing_address_id');
                    } else {
                      if(isset($_POST['billing_address_1'])){
                        $billing_address = array(
                            "company_id" => $customer['id'],
                            "address_name" => $this->input->post('billing_first_name') . ' ' . $this->input->post('billing_last_name'),
                            "company_name" => $this->input->post('billing_company'),
                            "line1" => $this->input->post('billing_address_1'),
                            "line2" => $this->input->post('billing_address_2'),
                            "city" => $this->input->post('billing_city'),
                            "postal_code" => $this->input->post('billing_postcode'),
                            "state" => $billing_stateData[0],
                            "state_code" => $billing_stateData[1],
                            "country" => $this->input->post('billing_country'),
                            "phone" => $this->input->post('billing_phone'),
                            "email_id" => $this->input->post('billing_email'),
                        );

                        $billing_address_id = $this->webshop_model->add_address($billing_address);
                      }else{
                           $billing_address_id =   $this->webshop_model->getAddressDefault($customer['id'],'default');    
                      }
                    }


                    if (!empty($this->input->post('shipping_address_id'))) {

                        $shipping_address_id = $this->input->post('shipping_address_id');
                    } else {

                        if ($this->input->post('billing_and_shipping_address_is_same')) {

                            $shipping_address = $billing_address;
                            $shipping_address['address_type'] = 'shipping';

                            $shipping_address_id = $billing_address_id;
                        } else {
                         if(isset($_POST['shipping_address_1'])){
                            $shipping_stateData = explode('~', $this->input->post('shipping_state'));

                            $shipping_address = array(
                                "company_id" => $customer['id'],
                                "address_name" => $this->input->post('shipping_first_name') . ' ' . $this->input->post('shipping_last_name'),
                                "company_name" => $this->input->post('shipping_company'),
                                "line1" => $this->input->post('shipping_address_1'),
                                "line2" => $this->input->post('shipping_address_2'),
                                "city" => $this->input->post('shipping_city'),
                                "postal_code" => $this->input->post('shipping_postcode'),
                                "state" => $shipping_stateData[0],
                                "state_code" => $shipping_stateData[1],
                                "country" => $this->input->post('shipping_country'),
                                "phone" => $this->input->post('shipping_phone'),
                                "email_id" => $this->input->post('shipping_email'),
                            );

                            $shipping_address_id = $this->webshop_model->add_address($shipping_address);
                           }else{
                               $shipping_address_id =   $this->webshop_model->getAddressDefault($customer['id'], 'shipping');  
                          }  
                         
                        }
                    }//end if.
                }//end else

                $warehouse_id = $this->webshop_settings->warehouse_id;
                $biller_id = $this->webshop_settings->biller_id;
                $biller = $this->webshop_model->get_company_by_id($biller_id);

                if ((!empty($customer['state_code']) && !empty($biller['state_code'])) && $customer['state_code'] != $biller['state_code']) {
                    $interStateTax = true;
                } else {
                    $interStateTax = false;
                }

                if (is_array($this->input->post('item_id'))) {

                    $cartItems = $this->input->post('item_id');
                    $cart_options = $this->input->post('option_id');
                    $cart_option_price = $this->input->post('option_price');
                    $cart_item_unit_quantity = $this->input->post('item_unit_quantity');
                    $cart_item_quantity = $this->input->post('item_quantity');
                    $cart_item_unit_price = $this->input->post('item_unit_price');
                    $cart_item_tax_rate = $this->input->post('item_tax_rate');
                    $cart_item_tax_method = $this->input->post('item_tax_method');
                    $cart_item_promotion_price = $this->input->post('item_promotion_price');
                    $cart_item_product_price = $this->input->post('item_product_price');

                    $units = $this->webshop_model->get_units();

                    $sale_cgst = $sale_sgst = $sale_igst = 0;
                    $total = $total_item_tax = $total_item_discount = 0;
                    $total_items = 0;

                    foreach ($cartItems as $key => $product_id) {

                        $option_id = $cart_options[$key];
                        $option_price = (float) $cart_option_price[$key];
                        $quintity = (float) $cart_item_quantity[$key];
                        $unit_quantity = (float) $quintity * (float) $cart_item_unit_quantity[$key];

                        $unit_price = (float) $cart_item_unit_price[$key];
                        $tax_rate = (float) $cart_item_tax_rate[$key];
                        $tax_method = $cart_item_tax_method[$key];
                        $promotion_price = (float) $cart_item_promotion_price[$key];
                        $item_price = (float) $cart_item_product_price[$key];

                        $selectData = "id,code,article_code,name,unit AS unit_id,price,weight,cf1,cf2,tax_rate AS tax_id , tax_method, type AS product_type, sale_unit AS sale_unit_id, mrp, hsn_code, storage_type, promotion, promo_price,start_date,end_date";

                        $productData = $this->webshop_model->get_product_by_id($product_id, $selectData);

                        $product = $productData[$product_id];

                        $product['tax_rate'] = $tax_rate;

                        $unit_code = $units[$product['sale_unit_id']]['code'];
                        $variant_price = array('1' => $option_price); //Send para value in array
                        //Helper Function
                        $productPrice = product_sale_price($product, $variant_price, $discount = NULL);

                        $invoice_unit_price = $productPrice['net_unit_price'];
                        $invoice_net_unit_price = $productPrice['net_unit_price'] + $productPrice['unit_discount'] + $productPrice['unit_tax'];
                        $net_price = $unit_quantity * $product['mrp'];
                        $invoice_total_net_unit_price = $invoice_net_unit_price * $unit_quantity;

                        $item_tax = $productPrice['unit_tax'] * $unit_quantity;
                        $item_discount = $productPrice['unit_discount'] * (float) $unit_quantity;

                        $subtotal = (($productPrice['net_unit_price'] * (float) $unit_quantity) + (float) $item_tax);

                        if ($interStateTax) {
                            $item_gst = $tax_rate;
                            $item_cgst = 0;
                            $item_sgst = 0;
                            $item_igst = $item_tax;
                        } else {
                            $item_gst = (float) $tax_rate / 2;
                            $item_cgst = (float) $item_tax / 2;
                            $item_sgst = (float) $item_tax / 2;
                            $item_igst = 0;
                        }

                        $products[] = array(
                            "product_id" => $product_id,
                            "product_code" => $product['code'],
                            "article_code" => $product['article_code'],
                            "product_name" => $product['name'],
                            "product_type" => $product['product_type'],
                            "option_id" => $option_id,
                            "net_unit_price" => $this->sma->formatDecimal($productPrice['net_unit_price'], 4),
                            "unit_discount" => $productPrice['unit_discount'],
                            "unit_tax" => $this->sma->formatDecimal($productPrice['unit_tax'], 4),
                            "invoice_unit_price" => $this->sma->formatDecimal($invoice_unit_price, 4),
                            "invoice_net_unit_price" => $this->sma->formatDecimal($invoice_net_unit_price, 4),
                            "unit_price" => $productPrice['unit_price'],
                            "quantity" => $unit_quantity,
                            "net_price" => $net_price,
                            "invoice_total_net_unit_price" => $invoice_total_net_unit_price,
                            "warehouse_id" => $warehouse_id,
                            "item_tax" => $this->sma->formatDecimal($item_tax, 4),
                            "tax_method" => $tax_method,
                            "tax_rate_id" => $product['tax_id'],
                            "tax" => $productPrice['tax_rate'],
                            "discount" => $productPrice['discount_rate'],
                            "item_discount" => $this->sma->formatDecimal($item_discount, 4),
                            "subtotal" => $this->sma->formatDecimal($subtotal, 4),
                            "real_unit_price" => $productPrice['real_unit_price'],
                            "product_unit_id" => $product['sale_unit_id'],
                            "product_unit_code" => $unit_code,
                            "unit_quantity" => $quintity,
                            "mrp" => $this->sma->formatDecimal($product['mrp'], 4),
                            "hsn_code" => $product['hsn_code'],
                            "note" => '',
                            "delivery_status" => 'pending',
                            "pending_quantity" => $quintity,
                            "delivered_quantity" => 0,
                            "gst_rate" => $item_gst,
                            "cgst" => $this->sma->formatDecimal($item_cgst, 4),
                            "sgst" => $this->sma->formatDecimal($item_sgst, 4),
                            "igst" => $this->sma->formatDecimal($item_igst, 4),
                            "item_weight" => $unit_quantity,
                        );

                        $total_items++;

                        $sale_cgst += $item_cgst;
                        $sale_sgst += $item_sgst;
                        $sale_igst += $item_igst;

                        $total += ((float) $productPrice['net_unit_price'] * (float) $unit_quantity);
                        $total_item_tax += (float) $item_tax;
                        $total_item_discount += (float) $item_discount;
                    }//end foreach.

                    $reference = $this->site->getReferenceNumber('eshop');
                    $date = date('Y-m-d H:i:s');
                    $customer_id = $customer['id'];
                    $customer_name = $customer['name'];
                    $note = $this->db->escape($this->input->post('order_comments'));
                    $shipping = (($this->input->post('shipping_chages'))? $this->input->post('shipping_chages') : 0);
                    $order_discount_id = NULL;
                    $order_discount = 0;
                    $order_tax_id = NULL;
                    $order_tax = 0;

                    $total_discount = $total_item_discount + $order_discount;
                    $total_tax = $total_item_tax + $order_tax;

                    $grand_total = (($total + $total_tax + $shipping) - $order_discount);
                    $rounding = 0;

                    if ($this->webshop_settings->rounding > 0) {
                        $round_total = $this->sma->roundNumber($grand_total, $this->webshop_settings->rounding);
                        $rounding = ($round_total - $grand_total);
                    }

                    $order = array(
                        'eshop_sale' => 1,
                        'date' => $date,
                        'reference_no' => $reference,
                        'customer_id' => $customer_id,
                        'customer' => $customer_name,
                        'biller_id' => $biller_id,
                        'biller' => $biller['name'],
                        'warehouse_id' => $warehouse_id,
                        'note' => $note,
                        'staff_note' => '',
                        'total' => $this->sma->formatDecimal($total, 4),
                        'product_discount' => $this->sma->formatDecimal($total_item_discount, 4),
                        'order_discount_id' => $order_discount_id,
                        'order_discount' => $this->sma->formatDecimal($order_discount, 4),
                        'total_discount' => $this->sma->formatDecimal($total_discount, 4),
                        'product_tax' => $this->sma->formatDecimal($total_item_tax, 4),
                        'order_tax_id' => $order_tax_id,
                        'order_tax' => $this->sma->formatDecimal($order_tax, 4),
                        'total_tax' => $this->sma->formatDecimal($total_tax, 4),
                        'shipping' => $this->sma->formatDecimal($shipping, 4),
                        'grand_total' => $this->sma->formatDecimal($grand_total, 4),
                        'total_items' => $total_items,
                        'sale_status' => 'pending',
                        'payment_status' => 'due',
                        'payment_method' => $this->input->post('payment_method'),
                        'payment_term' => $this->input->post('term'),
                        'rounding' => $this->sma->formatDecimal($rounding, 4),
                        'due_date' => NULL,
                        'paid' => 0,
                        'eshop_order_alert_status' => 0,
                        'created_by' => NULL,
                        'cgst' => $this->sma->formatDecimal($sale_cgst, 4),
                        'sgst' => $this->sma->formatDecimal($sale_sgst, 4),
                        'igst' => $this->sma->formatDecimal($sale_igst, 4),
                        'billing_address_id' => $billing_address_id,
                        'shipping_address_id' => $shipping_address_id,
                    );
                }


                if (count($products) && !empty($order)) {

                    $order_id = $this->webshop_model->add_order($order, $products);


                    if ($order_id) {

                        //$this->send_invoice_by_email(['order_id'=>$order_id, 'customer'=>$customer]);

                        if ($this->input->post('payment_method') == 'cod') {
                            unset($_SESSION['cart']);
                            redirect("webshop/order_success?order=$order_id&customer=$customer_id");
                        } else if ($this->input->post('payment_method') == 'online_payment') {

                            redirect("webshop/payments?order=$order_id&customer=$customer_id");
                        }

                        redirect("webshop/order_success?order=$order_id&customer=$customer_id");
                    }
                }
            } else {
                $this->session->set_flashdata('error_message', 'Request Timeout');
                $_SESSION['postdata'] = $this->input->post();
                redirect('webshop/checkout/timeout');
            }
        } else {
            $this->session->set_flashdata('error_message', 'Invalid Request');
            redirect('webshop/cart/invalid');
        }
    }

    public function send_invoice_by_email(array $para) {

        $order_id = $para['order_id'];
        $customer = $para['customer'];

        $to_email = $customer['email'];
        $to_name = $customer['name'];

        /* Email Code write here */
    }

    public function send_welcome_mail($customer) {

        $to_email = $customer['email'];
        $to_name = $customer['name'];

        /* Email Code write here */
    }

    public function payment_ccavResponseHandler() {

        $this->load->helper('crypto_helper');

        echo '<pre>';
        print_r($_POST);

        $workingKey = $_POST['working_key']; // Working Key should be provided here.
        $encResponse = $_POST["encResp"]; // This is the response sent by the CCAvenue Server
        $rcvdString = decrypt($encResponse, $workingKey); // Crypto Decryption used as per the specified working key.
        $order_status = "";
        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);
        for ($i = 0; $i < $dataSize; $i ++) {
            $information = explode('=', $decryptValues [$i]);
            $responseMap [$information [0]] = $information [1];
        }

        print_r($responseMap);
        echo '</pre>';

        $order_status = $responseMap ['order_status'];


        // $this->load_view("payment_ccavResponseHandler", $this->data);
    }

    public function payment_ccavRequestHandler($postData) {

        $this->load->helper('crypto_helper');

        $merchant_data = '';
        $working_key = $this->input->post('API_KEY');
        $access_code = $this->input->post('ACCESS_CODE');
        $api_url = $this->input->post('API_URL');

        $postData = array(
            "reference_no" => $this->input->post('reference_no'),
            "customer_id" => $this->input->post('customer_id'),
            "date" => $this->input->post('date'),
            "language" => $this->input->post('language'),
            "amount" => $this->input->post('amount'),
            "currency" => $this->input->post('currency'),
            "billing_name" => $this->input->post('billing_name'),
            "billing_company" => $this->input->post('billing_company'),
            "billing_address" => $this->input->post('billing_address'),
            "billing_city" => $this->input->post('billing_city'),
            "billing_state" => $this->input->post('billing_state'),
            "billing_country" => $this->input->post('billing_country'),
            "billing_zip" => $this->input->post('billing_zip'),
            "billing_tel" => $this->input->post('billing_tel'),
            "billing_email" => $this->input->post('billing_email'),
            "redirect_url" => $this->input->post('redirect_url'),
            "cancel_url" => $this->input->post('cancel_url'),
        );

        foreach ($postData as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }
        $merchant_data .= "order_id=" . $this->input->post('order_id');

        $encrypted_data = encrypt($merchant_data, $working_key);
        ?>
        <form method="post" name="redirect" action="<?= $api_url ?>">
            <input type="hidden" name="encRequest" value="<?= $encrypted_data ?>" /> 
            <input type="hidden" name="access_code" value="<?= $access_code ?>" />  
            <input type="hidden" name="working_key" value="<?= $working_key ?>" />  
        </form>         
        <script language="javascript">document.redirect.submit();</script>
        <?php
    }

    public function payment_cancel() {
        echo '<pre>';
        print_r($_POST);
        echo '</pre>';
    }

    public function payments() {

        $order_id = $this->input->get('order');

        $customer_id = $this->input->get('customer');

        if (!empty($this->input->post('submit'))) {

            if (!empty($this->input->post('payment_gatway'))) {

                switch ($this->input->post('payment_gatway')) {

                    case "ccavenue":

                        $this->payment_ccavRequestHandler();

                        break;

                    case "instamojo":

                        $pay_result = $this->webshop_model->instamojoEshop($_POST);


                        if (isset($pay_result['longurl']) && !empty($pay_result['longurl'])):
                            redirect($pay_result['longurl'], 'refresh');

                        else:
                            $this->webshop_model->deleteSale($order_id);
                            $result['msg'] = $this->instamojo_error($pay_result['error']);
                            return $result;
                        endif;


                        break;

                    case 'paytm':
                        $paytmpayment['order_id'] = $_POST['order_id'];
                        $paytmpayment['amount'] = $_POST['amount'];
                        $paytmpayment['userid'] = $_POST['customer_id'];

//                        $this->Orders_Emails($orderData);
//                        $this->Shopkeeper_Emails($orderData);
                        $this->paytm_init($paytmpayment);

                        break;

                    case 'razorpay':
                        $this->razorpay_init($_POST);
//                            echo 'Razorpay';
                        break;






                    default:
                        break;
                }//end switch
            }
        } else {

            $ci = get_instance();
            $ci->config->load('payment_gateways', true);

            $this->data['payment_config'] = $ci->config->item('payment_gateways');

            /* echo "@@@@@@@@@@@";
              print_r($this->data['payment_config']); */

            $this->data['customer_id'] = $customer_id;

            $this->data['order'] = $order = $this->webshop_model->get_order_by_id($order_id);

            $this->data['payments_gatway'] = $this->webshop_model->get_payment_gatways();

            $this->data['billing_address'] = $this->webshop_model->get_address_by_id($order['billing_address_id']);

            /* echo "###################";
              print_r($this->data['payments_gatway']);
              echo "<br/>webshop_settings:";
              print_r($this->webshop_settings);
              echo "<br/>order Data:";
              print_r($this->data['order']);
              echo "<br/>billing_address:";
              print_r($this->data['billing_address']);
              echo '</pre>'; */

            $this->load_view("payments", $this->data);
        }
    }

    public function order_success() {

        $order_id = $this->input->get('order');

        $this->data['order'] = $this->webshop_model->get_order_by_id($order_id);
        $this->data['items'] = $this->webshop_model->get_order_items_by_order_id($order_id);

        $this->load_view("order_success", $this->data);
    }

    private function set_theme_sections_data($themeSections) {

        if (!is_array($themeSections)) {
            return false;
        }

        foreach ($themeSections as $sections) {

            switch ($sections->section_name) {

                case 'section_subcategory_tabs_multiple_sections':

                    $sectionData = json_decode(unserialize($sections->section_data), TRUE);

                    $this->data[$sections->section_name]['section_titles'] = $sectionData['section_titles'];
                    $this->data[$sections->section_name]['section_tab_categories'] = $sectionData['section_tab_categories'];

                    $category_products = $this->webshop_model->get_category_tab_products($sectionData['section_tab_categories']);

                    $this->data[$sections->section_name]['section_products'] = $category_products;

                    break;

                case 'section_category_exclusive_products':
                case 'section_category_tab_right_highlite_products':
                case 'section_category_tab_vertical_align':
                case 'section_category_tab_center_align':
                case 'section_category_tab_right_align':
                case 'section_category_tab_left_align':

                    $sectionData = json_decode(unserialize($sections->section_data), TRUE);

                    $this->data[$sections->section_name]['section_titles'] = $sections->section_title;
                    $this->data[$sections->section_name]['section_tabs'] = $sectionData['tabs'];
                    $this->data[$sections->section_name]['section_products'] = $this->webshop_model->get_tab_products_by_id($sectionData['products']);

                    if (isset($sectionData['highlite'])) {
                        $this->data[$sections->section_name]['section_products_highlite'] = $this->webshop_model->get_tab_products_by_id($sectionData['highlite']);
                    }

//                    echo '<pre>';
//                    print_r($this->data[$sections->section_name]);
//                    echo '</pre>'; 

                    break;

                case 'section_fullwidth_notice':

                    $this->data['section_fullwidth_notice'] = $sections->section_data;
                    break;

                case 'section_top_categories':

                    $this->data['section_top_categories'] = $sections->section_data;
                    break;


                default:
                    break;
            }//end switch
        }//end foreach
    }

    public function add_to_wishlist($postData) {

        $data['product_id'] = $postData['product_id'];
        $data['option_id'] = !empty($postData['variant_id']) ? $postData['variant_id'] : 0;

        if (isset($this->session->webshop) && $this->session->webshop->is_login && $this->session->webshop->user_id) {
            $data['user_id'] = $this->session->webshop->user_id;

            $wishlist = $this->webshop_model->add_to_wishlist($data);

            $result['count'] = count($wishlist);
            $result['status'] = 'SUCCESS';
            $result['items'] = $wishlist;

            echo json_encode($result);
        } else {
            $result['status'] = 'FAIL';
            $result['error'] = 'User session invalide';
            echo json_encode($result);
        }
    }

    public function remove_from_wishlist($postData) {

        $data['product_id'] = $postData['product_id'];
        $data['option_id'] = ($postData['variant_id']) ? $postData['variant_id'] : 0;

        if (isset($this->session->webshop) && $this->session->webshop->is_login && $this->session->webshop->user_id) {
            $data['user_id'] = $this->session->webshop->user_id;

            $wishlist = $this->webshop_model->remove_from_wishlist($data);

            $result['count'] = count($wishlist);
            $result['status'] = 'SUCCESS';
            $result['items'] = $wishlist;

            echo json_encode($result);
        } else {
            $result['status'] = 'FAIL';
            $result['error'] = 'User session invalide';
            echo json_encode($result);
        }
    }

    public function add_to_cart($postData) {

        $product_id = $postData['product_id'];
        $variant_id = !empty($postData['variant_id']) ? $postData['variant_id'] : 0;
        $variant_price = !empty($postData['variant_price']) ? $postData['variant_price'] : 0;
        $unit_quantity = !empty($postData['variant_unit_quantity']) ? $postData['variant_unit_quantity'] : 1;
        $product_unit_price = $postData['product_price'];
        $quantity = $postData['quantity'];
        $tax_rate = $postData['tax_rate'];
        $tax_method = $postData['tax_method'];
        $price = $postData['price'];
        $promotion_price = $postData['promotion_price'];

        $item_key = ((int) $variant_id) ? $product_id . "_" . $variant_id : $product_id;

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$item_key])) {

            $_SESSION['cart'][$item_key]['quantity'] += $quantity;

            $data['cart_items'] = count($_SESSION['cart']);
            $data['status'] = 'SUCCESS';
        } else {

            $_SESSION['cart'][$item_key] = [
                "product_id" => $product_id,
                "variant_id" => $variant_id,
                "variant_price" => $variant_price,
                "unit_quantity" => $unit_quantity,
                "product_price" => $product_unit_price,
                "quantity" => $quantity,
                "tax_rate" => $tax_rate,
                "tax_method" => $tax_method,
                "price" => $price,
                "promotion_price" => $promotion_price,
            ];

            $data['cart_count'] = count($_SESSION['cart']);
            $data['status'] = 'SUCCESS';
        }//end else
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $key => $item) {
            $subtotal += (float) $item['product_price'] * (float) $item['quantity'];
        }

        $data['cart_total'] = number_format($subtotal, 2);


        echo json_encode($data);
    }

    public function update_cart($postData) {

        $item_key = $postData['itemKey'];
        $itemQty = $postData['itemQty'];
        // $itemUnitPrice  = $postData['itemUnitPrice']; 

        if (!isset($_SESSION['cart'])) {
            return FALSE;
        }

        if (isset($_SESSION['cart'][$item_key])) {

            $_SESSION['cart'][$item_key]['quantity'] = $itemQty;

            echo 'SUCCESS';
        }
    }

    public function load_header_cart_items() {

        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            $this->data['cart_items'] = $_SESSION['cart'];
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();

            $this->load_view("headers/header_cart_items", $this->data);
        } else {
            echo 'EMPTY';
        }
    }

    public function remove_cart_item($postData) {

        if (isset($_SESSION['cart'][$postData['cart_item_key']]) && !empty($_SESSION['cart'])) {
            unset($_SESSION['cart'][$postData['cart_item_key']]);
            $this->data['cart_items'] = $_SESSION['cart'];
            $this->data['cart_data'] = $this->webshop_model->get_cart_data();

            if ($postData['action_source'] == "cart_page") {
                $this->load_view("cart_content", $this->data);
            } else {
                $this->load_view("headers/header_cart_items", $this->data);
            }
        }
    }

    public function set_product_gallery($postData) {

        $this->data['gallary_images'] = $this->webshop_model->get_product_images($postData['product_id'], $postData['variant_id']);

        $this->load_view("product_single_item_gallery", $this->data);
    }

    public function get_sections($postData) {


        $section_name = $postData['section'];

        switch ($section_name) {

            case 'section_features_list':

                $this->data['features'] = $this->webshop_model->get_features();
                if (is_array($this->data['features'])) {
                    $this->load_view("section_features_list", $this->data);
                }
                break;

            case 'section_top_categories':

                $this->load_view("section_top_categories", $this->data);
                break;


            default:
                break;
        }//end switch
    }

    public function storeInfo() {

        $this->load->model('settings_model');

        $res = $this->eshop_model->getPosSettings();

        $config = $this->ci->config;
        $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : null;
        $res->merchant_phone = $merchant_phone;

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

    public function login() {

        if ($this->input->post('submit_login')) {

            $username = $this->input->post('webshop_username');
            $passwdHash = md5($this->input->post('webshop_password'));
            $return_page = $this->input->post('return_page');

            $authData = $this->webshop_model->authenticate_user($username, $passwdHash);

            if (!empty($authData)) {
                $authData->user_id = $authData->id;
                $authData->is_login = TRUE;
                if (isset($_SESSION['cart'])) {
                    $this->session->cart = $_SESSION['cart'];
                } else {
                    unset($_SESSION['cart']);
                }
                $this->session->webshop = $authData;
                redirect($return_page . "?msg=auth_success");
            } else {
                redirect("webshop/login?msg=error");
            }
        } else {
            if ($this->session->webshop->is_login && $this->session->webshop->user_id) {
                redirect("webshop/index");
            }

            $this->data['return_page'] = str_replace(base_url(), '', $_SERVER['HTTP_REFERER']);
            $this->load_view("login_registration", $this->data);
        }
    }

    public function logout() {

        unset($_SESSION['cart']);
        unset($_SESSION['webshop']);

        if (!$this->session->webshop->is_login && !$this->session->webshop->user_id) {
            redirect("webshop");
        }
    }

    public function register() {

        if ($this->input->post('submit_register')) {

            $data['group_id'] = 3;
            $data['group_name'] = 'customer';
            $data['customer_group_id'] = 1;
            $data['customer_group_name'] = 'General';
            $data['name'] = $this->input->post('name');
            $data['email'] = $this->input->post('email');
            $data['phone'] = $this->input->post('phone');
            $data['password'] = md5($this->input->post('passwd'));

            /*
             * 
             * Write Validation Code & Check Customer email/Phone not exists
             *              
             */

            if ($this->webshop_model->add_customer($data)) {

                if ($this->send_registration_email()) {

                      $username = $this->input->post('email');
                      $passwdHash = md5($this->input->post('passwd'));
                      $return_page = 'webshop/your_profile';
                      $authData = $this->webshop_model->authenticate_user($username, $passwdHash);

                        if (!empty($authData)) {
                            $authData->user_id = $authData->id;
                            $authData->is_login = TRUE;
                            if (isset($_SESSION['cart'])) {
                                $this->session->cart = $_SESSION['cart'];
                            } else {
                                unset($_SESSION['cart']);
                            }
                            $this->session->webshop = $authData;
                            redirect($return_page . "?msg=auth_success");
                        } else {
                            redirect("webshop/login?msg=error");
                        }
                    

                }
            }
        } else {
            $this->load_view("login_registration", $this->data);
        }
    }

    public function send_registration_email() {

        /*
         * Write Send Email Code Here
         */
        return TRUE;
    }

    public function apply_coupon($postData) {

        $coupon_code = $postData['coupon_code'];
        $cart_amount = $postData['cart_amount'];

        $couponData = $this->webshop_model->get_coupon_data($coupon_code);

        $data['status'] = 'failed';
        $data['msg'] = "Invalid coupon code " . $couponData->coupon_code;

        if ($couponData->coupon_code === $coupon_code) {

            if ($couponData->minimum_cart_amount > $cart_amount) {
                $data['status'] = 'failed';
                $data['msg'] = "Cart amount is less as per coupon conditions.";
            } elseif (strtotime($couponData->expiry_date) < strtotime(date('Y-m-d')) || $couponData->status == 'expired') {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " has been expired.";
            } elseif ($couponData->status != 'active') {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " is no more active.";
            } elseif ($couponData->max_coupons != '' && $couponData->max_coupons > 0 && $couponData->used_coupons > 0 && $couponData->max_coupons <= $couponData->used_coupons) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon " . $couponData->coupon_code . " max limit has been already reach.";
            } elseif ($couponData->customer_id != '' && $couponData->customer_id != $this->session->webshop->user_id) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon code " . $couponData->coupon_code . " is belongs to another customer.";
            } elseif ($couponData->customer_group_id != '' && $couponData->customer_group_id != $this->session->webshop->customer_group_id) {
                $data['status'] = 'failed';
                $data['msg'] = "Coupon code " . $couponData->coupon_code . " is belongs to another customer groups.";
            } else {

                if (!empty($couponData->discount_rate)) {
                    $discount = $couponData->discount_rate;
                    $dpos = strpos($discount, '%');
                    if ($dpos !== false) {
                        $cup_ds = explode("%", $discount);
                        $coupon_discount = $this->sma->formatDecimal(( ( (Float) $cart_amount * (Float) $cup_ds[0] ) / 100), 4);
                    } else {
                        $coupon_discount = $this->sma->formatDecimal($discount, 4);
                    }

                    if ($couponData->maximum_discount_amount > 0 && $coupon_discount > $couponData->maximum_discount_amount) {
                        $coupon_discount = $couponData->maximum_discount_amount;
                    }

                    $couponData->aplied_discount_amount = $coupon_discount;

                    $data['status'] = 'success';
                    $data['msg'] = "Coupon applied successfully.";
                    $data['coupon_data'] = $couponData;
                }//end if
            }
        }

        echo json_encode($data);
    }

    public function page($PageKey, $pageHashId) {

        $this->data['page_data'] = $this->webshop_model->getCustomPages($pageHashId);

        $this->load_view("page", $this->data);
    }

    public function your_account() {

        $this->load_view("your_account", $this->data);
    }

    public function your_address() {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

            $this->data['state_list'] = $this->webshop_model->get_state();

            $customer_id = (int) $this->session->webshop->user_id;
            $this->data['customer_id'] = $customer_id;
            $this->data['addresses'] = $this->webshop_model->get_customer_address($customer_id);

            $this->load_view("your_address", $this->data);
        } else {
            redirect("webshop/login");
        }
    }

    public function manage_address($postData) {

        if (isset($postData['submit_address'])) {

            $customer_id = $this->input->post('customer_id');
            $addressAction = $this->input->post('addressModalAction');

            $address_name = $this->input->post('address_name');
            $company_name = $this->input->post('company_name');
            $line_1 = $this->input->post('address_line_1');
            $line_2 = $this->input->post('address_line_2');
            $country = $this->input->post('country');
            $state = $this->input->post('state');
            $state_name = $this->input->post('state_name');
            $city = $this->input->post('city');
            $postal_code = $this->input->post('postal_code');
            $phone = $this->input->post('phone');
            $email_id = $this->input->post('email_id');
            $default_address = $this->input->post('default_address');

            $stateData = explode('~', $state);

            $state_name = $stateData[0];
            $state_code = $stateData[1];

            $data = [
                'company_id' => $customer_id,
                'company_name' => $company_name,
                'address_name' => $address_name,
                'line1' => $line_1,
                'line2' => $line_2,
                'city' => $city,
                'postal_code' => $postal_code,
                'state' => $state_name,
                'country' => $country,
                'phone' => $phone,
                'email_id' => $email_id,
                'state_code' => $state_code,
            ];


            if ($addressAction == 'add') {
                if ($address_id = $this->webshop_model->set_customer_address($data)) {

                    if ($default_address == 1) {
                        $this->webshop_model->set_address_default($customer_id, $address_id);
                    }

                    $this->session->set_flashdata('message', "Address Added Successfully.");
                    redirect("webshop/your_address");
                }
            } else if ($addressAction == 'edit') {
                $addressId = $this->input->post('addressModalActionId');
                $this->webshop_model->update_customer_address($data, $addressId);
                $this->session->set_flashdata('message', "Address Updated Successfully.");
                redirect("webshop/your_address");
            }
        }
    }

    public function address_set_default($customer_id, $address_id) {

        if ($this->webshop_model->set_address_default($customer_id, $address_id)) {

            $this->session->set_flashdata('message', "Default Address Set Successfully.");
            redirect("webshop/your_address");
        }
    }

    public function checkout_set_shipping_address($customer_id, $address_id) {

        if ($this->webshop_model->set_address_default($customer_id, $address_id)) {

            $this->session->set_flashdata('message', "Default Address Set Successfully.");
            redirect("webshop/checkout");
        }
    }

    public function address_delete($address_id) {

        if ($this->webshop_model->delete_address($address_id)) {

            $this->session->set_flashdata('message', "Address Deleted Successfully.");
            redirect("webshop/your_address");
        }
    }

    public function your_orders() {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

            $this->webshop_model->set_recent_viewed_product($productDetails['item']['id']);

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $customer_id = (int) $this->session->webshop->user_id;
            $this->data['customer_id'] = $customer_id;

            $this->data['orders'] = $this->webshop_model->get_customer_orders($customer_id);
   
            $this->load_view("your_orders", $this->data);
        } else {
            redirect("webshop/login");
        }
    }

    public function order_details($order_id) {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

            if (empty($order_id) || $order_id == '') {
                redirect("webshop/your_orders");
            }

            $this->webshop_model->set_recent_viewed_product($productDetails['item']['id']);

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $customer_id = (int) $this->session->webshop->user_id;
            $this->data['customer_id'] = $customer_id;

            $this->data['order'] = $this->webshop_model->get_customer_orders($customer_id, $order_id);

            $this->load_view("order_details", $this->data);
        } else {
            redirect("webshop/login");
        }
    }

    public function profile_update() {

        // $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');
        $this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');

        if (isset($_POST['upload_image'])) {
            
            $upload_path = './assets/images/customers/';
            if(!file_exists($upload_path)){
                mkdir($upload_path, 0777, true);
                $indexfile = fopen($upload_path."index.html", "w") or die("Unable to open file!");                
                fwrite($indexfile, '<p>Directory access is forbidden.</p>');                
                fclose($indexfile);
            }
            
            $fileconfig['upload_path'] = $upload_path;
            $fileconfig['allowed_types'] = 'jpg|png|jpeg';
            $fileconfig['max_size'] = 2000;
            $fileconfig['max_width'] = 700;
            $fileconfig['max_height'] = 900;
            $fileconfig['is_image'] = 1;

            $fileconfig['file_name'] = md5($this->session->webshop->user_id);

            $this->load->library('upload', $fileconfig);
            $this->upload->overwrite = true;
            if (!$this->upload->do_upload('profile_image')) {

                $this->session->set_flashdata('error', $this->upload->display_errors());
                redirect("webshop/your_profile");
            } else {

                $upload_data = $this->upload->data(); //Returns array of containing all of the data related to the file you uploaded.
                $file_name = $upload_data['file_name'];

                if ($this->webshop_model->set_profile_photo($file_name, $this->session->webshop->user_id)) {

                    $this->session->set_flashdata('message', "Profile Image Uploaded Successfully.");
                    redirect("webshop/your_profile");
                }
            }
        } 
        else if (isset($_POST['submitProfle'])) {

            $this->form_validation->set_rules('name', 'Your Name', 'trim|required|alpha_numeric_spaces');
            // $this->form_validation->set_rules('phone',  'Phone Number', 'trim|required|numeric|exact_length[10]|is_unique[companies.phone]');
            $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
            $this->form_validation->set_rules('country', 'country', 'required|alpha');
            $this->form_validation->set_rules('state', 'state', 'trim|required|alpha');
            $this->form_validation->set_rules('city', 'city', 'trim|required|alpha');
            $this->form_validation->set_rules('pincode', 'pincode', 'trim|required|numeric|exact_length[6]');

            if ($this->form_validation->run() == FALSE) {
                $this->session->set_flashdata('error', 'Validation Errors!');
                $this->your_profile('edit');
            } else {
                extract($_POST);

                $company = $company == '' ? '-' : $company;

                $data = [
                    "name" => $name,
                    "email" => $email,
                    "country" => $country,
                    "state" => $state,
                    "city" => $city,
                    "postal_code" => $pincode,
                    "address" => $address,
                    "pan_card" => $pan_card,
                    "gstn_no" => $gstn_no,
                    "vat_no" => $vat_no,
                    "company" => $company,
                ];

                if ($this->webshop_model->update_profile($data, $this->session->webshop->user_id)) {

                    $this->session->set_flashdata('message', "Profile Updated Successfully.");
                    redirect("webshop/your_profile");
                } else {
                    $this->session->set_flashdata('error', "Profile Update Sql Error.");
                    redirect("webshop/your_profile");
                }
            }
        } else {
            redirect("webshop/your_profile");
        }
    }

    public function your_profile($action = 'view') {

        if (isset($this->session->webshop) && $this->session->webshop->user_id) {

            $this->data['recent_viewed'] = $this->webshop_model->get_recent_viewed_product();

            $this->data['customer'] = $customer = $this->webshop_model->get_customer(['id' => $this->session->webshop->user_id]);

            $this->data['images'] = base_url("assets/images/customers/");
            $this->data['action'] = $action;
            $this->load_view("your_profile", $this->data);
        } else {
            redirect("webshop/login");
        }
    }

    public function my_views_history() {

        $this->load_view("my_views_history", $this->data);
    }

    public function change_password() {
        
        if (isset($this->session->webshop) && $this->session->webshop->user_id) {
        
            if (isset($_POST['changePassword'])) {
                
                $this->load->library('form_validation');
                $this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');
                
                $this->form_validation->set_rules('current_password', 'current password', 'required');
                $this->form_validation->set_rules('newpassword', 'newpassword', 'trim|required|min_length[8]|max_length[22]|differs[current_password]');
                $this->form_validation->set_rules('confirm', 'confirm', 'trim|required|matches[newpassword]');
                
                if ($this->form_validation->run() == FALSE) {
                    $this->session->set_flashdata('error', 'Validation Errors!');
                    $this->load_view("change_password", $this->data);
                } else {
                    $current_password = $this->input->post('current_password');                    
                    $newpassword      = md5($this->input->post('newpassword'));
                    
                    if($this->webshop_model->is_valid_current_password($this->session->webshop->user_id, $current_password) === false){
                        $this->session->set_flashdata('error', 'Invalid Current Password');
                        redirect("webshop/change_password/error");
                    } else {
                        
                        if($this->webshop_model->update_new_password($this->session->webshop->user_id, $newpassword)){
                            
                            $this->session->set_flashdata('message', 'Password has been changed successfully.');
                            redirect("webshop/change_password/success");
                        } else {
                            $this->session->set_flashdata('error', 'Sql error!');
                            redirect("webshop/change_password/error");
                        }
                    }
                    
                }
                
            } else {

                $this->load_view("change_password", $this->data);
            }

        } else {
            redirect("webshop/login");
        }
    }

    public function forgot_password() {
        
        if ($this->session->webshop->is_login && $this->session->webshop->user_id) {
            redirect("webshop/index");
        }
        
        if(isset($_POST['reset_paword'])){
            
            
            
        } else {
            
            $this->load_view("forgot_password", $this->data);
        }
        
    }
    
    /**
     * Instamojo Payment Gateway
     */
    public function payment_instamojoResponseHandler() {
        $payment_request_id = $this->input->get('payment_request_id');
        $payment_id = $this->input->get('payment_id');

        $this->data['payment_id'] = $payment_id;

        if (empty($payment_request_id) || empty($payment_id)):
            $this->data['error'] = 'Error in payment process';
            $this->load_view('/decline_order', $this->data);
        endif;

        $this->load->library('instamojo');

        $Transaction = $this->webshop_model->getInstamojoEshopTransaction(array('request_id' => $payment_request_id));

        $order_id = $Transaction->order_id;
        $res12 = $this->webshop_model->updateInstamojoEshopTransaction($payment_request_id, array('payment_id' => $payment_id));


        $this->load->library('instamojo');
        $ci = get_instance();

        $ci->config->load('payment_gateways', TRUE);

        $payment_config = $ci->config->item('payment_gateways');

        $instamojo_credential = $payment_config['instamojo'];

        try {
            $api = new Instamojo($instamojo_credential['API_KEY'], $instamojo_credential['AUTH_TOKEN'], $instamojo_credential['API_URL']);
            $paymentDetail = $api->paymentDetail($payment_id);

            if (is_array($paymentDetail)):
                $pay_res = serialize($paymentDetail);
                $this->webshop_model->updateInstamojoEshopTransaction($payment_request_id, array('success_response' => $pay_res));
                if (isset($paymentDetail["status"]) && in_array($paymentDetail["status"], array('Credit', 'credit', 'Completed'))):
                    $res = $this->webshop_model->instomojoEshopAfterSale($paymentDetail, $order_id);
                    if ($res):
                        $this->data['sale'] = $this->webshop_model->get_order_by_id($order_id);
                        $this->data['success'] = 'Payment done successfully';

                        unset($_SESSION['cart']);
                        redirect("webshop/order_success?order=$order_id"); //&customer=$customer_id
                    endif;
                endif;
                $this->data['error'] = 'Payment process under review';
                $this->webshop_model->deleteSale($order_id);
                $this->load_view('decline_order', $this->data);
            endif;
        } catch (Exception $e) {
            $this->data['error'] = $e->getMessage();
            $this->webshop_model->deleteSale($order_id);
            $this->load_view('decline_order', $this->data);
        }
        $this->webshop_model->deleteSale($order_id);
        $this->data['error'] = 'Payment process under review';
        $this->load_view('decline_order', $this->data);
    }

    /**
     * End Instamojo Payment Gateway
     *   
     */
    /**
     * Paytm Payment Gateway
     */

    /**
     * Paytm Payment Gatway
     * @return type
     */
    public function paytm_init($paytmpayment) {

        $order_id = $paytmpayment['order_id'];


        if ((int) $order_id > 0):
            $_req = $this->webshop_model->getPaytmTransaction(array('order_id' => $order_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Paytm" . lang('payment_process_already_initiated'));
                redirect('webshop');
            endif;
            $order = $this->site->getSaleByIDEshop($order_id);

            if ($order->id == $order_id):


                $customer = $this->site->getCompanyByID($order->customer_id);

                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');

                $paytm_credential = $payment_config['paytm'];

                $this->load->library('paytm', $paytm_credential);

                $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

                $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

                $API_URL = isset($paytm_credential['PAYTM_TXN_URL']) && !empty($paytm_credential['PAYTM_TXN_URL']) ? $paytm_credential['PAYTM_TXN_URL'] : '';

                $PAYTM_MERCHANT_WEBSITE = isset($paytm_credential['PAYTM_MERCHANT_WEBSITE']) && !empty($paytm_credential['PAYTM_MERCHANT_WEBSITE']) ? $paytm_credential['PAYTM_MERCHANT_WEBSITE'] : '';

                $arr['tid'] = time();


                $paramList["MID"] = $PAYTM_MERCHANT_MID;
                $paramList["ORDER_ID"] = $order->id;
                $paramList["CUST_ID"] = $customer->id;
                $paramList["INDUSTRY_TYPE_ID"] = 'Retail';
                $paramList["CHANNEL_ID"] = 'WEB';
                $paramList["TXN_AMOUNT"] = $this->sma->formatDecimal($order->grand_total);
                $paramList["WEBSITE"] = $PAYTM_MERCHANT_WEBSITE;
                $paramList["MSISDN"] = $customer->phone; //Mobile number of customer
                $paramList["EMAIL"] = $customer->email;  //Email ID of customer
                $paramList["VERIFIED_BY"] = "EMAIL"; //
                $paramList["IS_USER_VERIFIED"] = "YES"; //
                $paramList['CALLBACK_URL'] = base_url('webshop/payment_paytmResponseHandler');

                try {

                    $checkSum = $this->paytm->getChecksumFromArray($paramList, $PAYTM_MERCHANT_KEY);

                    //$this->data['merchant_id']  = $merchant_id;
                    // $this->data['paytm_access_code'] = $access_code;
                    $this->data['paramList'] = $paramList;
                    $this->data['PAYTM_TXN_URL'] = $API_URL;
                    $this->data['CHECKSUMHASH'] = $checkSum;

                    $this->webshop_model->addpaytmTransaction(array('order_id' => $order_id, 'req_data' => $paramList));



                    $this->load_view('paytm', $this->data);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            endif;
        endif;
    }

    /**
     * Paytm Payment
     */
    public function payment_paytmResponseHandler() {

        $this->load->library('paytm');

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');

        $paytm_credential = $payment_config['paytm'];

        $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

        $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

        $API_URL = isset($paytm_credential['API_URL']) && !empty($paytm_credential['API_URL']) ? $paytm_credential['API_URL'] : '';

        $MID = $this->input->post('MID') ? $this->input->post('MID') : null;

        $ORDERID = $this->input->post('ORDERID') ? $this->input->post('ORDERID') : null;

        if ($ORDERID):
            $this->webshop_model->updatePaytmTransaction($ORDERID, array('response_data' => serialize($_POST), 'update_time' => date('Y-m-d H:i:s')));
        endif;

        $STATUS = $this->input->post('STATUS') ? $this->input->post('STATUS') : null;
        $RESPMSG = $this->input->post('RESPMSG') ? $this->input->post('RESPMSG') : null;

        if ($_POST['STATUS'] != 'TXN_SUCCESS') {
            $this->session->set_flashdata('error', $_POST['RESPMSG']);
            if ((int) $ORDERID > 0):
                $getorderdetails = $this->site->getSaleByIDEshop($ORDERID);
                $ref_No = $getorderdetails->reference_no;

                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$ORDERID");
            else:
                redirect("webshop");
            endif;
        }

        try {
            $api = new Paytm($paytm_credential);
            $requestParamList = array("MID" => $PAYTM_MERCHANT_MID, "ORDERID" => $ORDERID);

            $responseParamList = $api->getTxnStatus($requestParamList);

            $_ORDERID = $responseParamList['ORDERID'] ? $responseParamList['ORDERID'] : null;
            $_STATUS = $responseParamList['STATUS'] ? $responseParamList['STATUS'] : null;
            $_RESPMSG = $responseParamList['RESPMSG'] ? $responseParamList['RESPMSG'] : null;
            $_TXNID = $responseParamList['TXNID'] ? $responseParamList['TXNID'] : null;
            if ($_ORDERID == $ORDERID && $_STATUS == 'TXN_SUCCESS'):

                $msg = 'success';
                $sid = $ORDERID;
                $tracking_id = $_TXNID;

                $getorderdetails = $this->site->getSaleByIDEshop($sid);
                $ref_No = $getorderdetails->reference_no;

                $res = $this->webshop_model->PaytmAfterSale($responseParamList, $sid);
                if ($res):
                    $this->session->set_flashdata('message', lang('payment_done'));
                    unset($_SESSION['cart']);
                    redirect("webshop/order_success?order=$sid");

                endif;

                $this->session->set_flashdata('message', $_RESPMSG);
                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$sid");

            else:
                $this->session->set_flashdata('error', $_RESPMSG);
                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$sid");

            endif;
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect("webshop");
        }
    }

    /**
     * End Paytm Payment Gatway
     * @return type
     */
    /**
     * End Paytm Payment Gateway
     */

    /**
     * Razorpay Payment Gateway
     */
    public function razorpay_init($data) {

        $sale_id = $data['order_id'];
//        $this->input->get('sid');
        if ((int) $sale_id > 0) {

            $sale = $this->site->getSaleByIDEshop($sale_id);
            if ($sale->id == $sale_id) {

                $customer = $this->site->getCompanyByID($sale->customer_id);


                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];
//            print_r($paymentData);
//                	$api = new Api('rzp_test_nEc2AabwdiJ6xf', '0vfdxx3UBkZrjfJL1hg9KrT5');
                $api = new Api($paymentData['RAZORPAY_KEY'], $paymentData['RAZORPAY_SECRET']);

                /**
                 * You can calculate payment amount as per your logic
                 * Always set the amount from backend for security reasons
                 */
                $_SESSION['payable_amount'] = $sale->grand_total;
                $_SESSION['currency'] = $this->Settings->default_currency;


                $razorpayOrder = $api->order->create(array(
                    'receipt' => $sale->invoice_no,
                    'amount' => $sale->grand_total * 100,
                    'currency' => $this->Settings->default_currency,
                    'payment_capture' => 1, // auto capture
                ));



                $amount = $razorpayOrder['amount'];

                $razorpayOrderId = $razorpayOrder['id'];


                $_SESSION['razorpay_order_id'] = $razorpayOrderId;
                $datapass = $this->prepareData($amount, $razorpayOrderId);

                $datapass['prefill'] = array(
                    'email' => $customer->email,
                    'contact' => $customer->phone,
                    'name' => $customer->name,
                    'description' => 'sales'
                );
                $datapass['notes'] = array(
                    'address' => $customer->address,
                    'merchant_order_id' => $sale->id
                );
                $datapass['name'] = $this->Settings->site_name;
                $datapass['description'] = '#Order No: ' . $sale->id;


                $this->data['data'] = $datapass;

                //redirect("webshop/razorpay", $this->data);
                $this->load_view('razorpay', $this->data);
            } else {
                redirect('pos');
            }
        } else {
            redirect('pos');
        }
    }

    /**
     * This function preprares payment parameters
     * @param $amount
     * @param $razorpayOrderId
     * @return array
     */
    public function prepareData($amount, $razorpayOrderId) {

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];
        $data = array(
            "key" => $paymentData['RAZORPAY_KEY'],
            "amount" => $amount,
            "name" => $this->Settings->site_name,
            "theme" => array(
                "color" => "#3868f1"
            ),
            "order_id" => $razorpayOrderId,
        );
        return $data;
    }

    /**
     * This function verifies the payment,after successful payment
     */
    public function razorpay_verify() {
        $sid = $this->input->get('sid');
        $success = true;

        $error = "payment_failed";
        if (empty($_POST['razorpay_payment_id']) === false) {
            $ci = get_instance();
            $ci->config->load('payment_gateways', true);
            $paymentData = $ci->config->item('payment_gateways')['RAZORPAY'];

            $api = new Api($paymentData['RAZORPAY_KEY'], $paymentData['RAZORPAY_SECRET']);


            try {

                $attributes = array(
                    'razorpay_order_id' => $_SESSION['razorpay_order_id'],
                    'razorpay_payment_id' => $_POST['razorpay_payment_id'],
                    'razorpay_signature' => $_POST['razorpay_signature'],
                    'amount' => $_SESSION['payable_amount'],
                    'currency' => $_SESSION['currency'],
                );
                $api->utility->verifyPaymentSignature($attributes);
            } catch (SignatureVerificationError $e) {
                $success = false;
                $error = 'Razorpay_Error : ' . $e->getMessage();
            }
        }


        if ($success === true) {

            $res = $this->webshop_model->RazorPayAfterSale($attributes, $sid);

            if ($res):
                $this->session->set_flashdata('message', lang('payment_done'));
                unset($_SESSION['cart']);
                redirect("webshop/order_success?order=$sid");
            endif;
        }
        else {
            $this->webshop_model->deleteSale($sid);
            $this->data['error'] = 'The transaction has been declined.';
            $this->load_view('decline_order', $this->data);
        }
    }

    /**
     * End Razorpay
     */
       
    public function action(){
        
        $orderId = $this->input->post('order_id');
        $action = $this->input->post('action');
        $reason = $this->input->post('reason');
        
        $action = ($action == 'cancel')?'cancelled' : 'returned';
        
        if($this->webshop_model->orderAction($orderId, ['sale_status' =>$action ,'note' =>$reason ])){
             $response = [
                'status_code' => 200,
                'status'    => 'success',
                'messages'       => 'Your order status has been changes'
            ];
        }else{
            $response = [
                'status_code' => 500,
                'status'    => 'error',
                'messages'       => 'Sorry, Please try again'
            ];
        }
        
        echo json_encode($response);
    }
    

 /**
     * Get Pincode Charges
     */
    public function getpincodecharges(){
        $pincode = $this->input->get('pincode');
        $result = $this->webshop_model->pincodecharges($pincode);
        if($result){
            $response = [
                'status' => 'success',
                'charges' => (($result->charges)?$this->sma->formatDecimal($result->charges) : '0'),
            ];
        }else{
            $response =[
                'status' => 'error',
                'charges' => '0',
            ];
        }
        echo json_encode($response);
    }
}

//end Class