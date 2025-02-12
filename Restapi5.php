<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Restapi5 extends REST_Controller {
    
    private $_settings;
    private $store_settings;
    private $per_page_limit;
    
    public function __construct() {
       
       parent::__construct();
       
       $this->load->model(['restapi5_model', 'site']);       
       $this->load->helper(['form', 'url']);
       $this->load->library(['form_validation', 'sma']);
       //$this->form_validation->set_error_delimiters('', '');
        
       $this->_settings = $this->restapi5_model->get_system_settings();
       
       $this->store_settings = $this->restapi5_model->get_store_settings();
       
       $this->per_page_limit = (bool)$this->store_settings->products_per_page ? $this->store_settings->products_per_page : 12;
       
       $this->_authenticate_api_request(); 
    }
    
    private function _authenticate_api_request() {
                
                
        //$privatekey = $this->input->get('X-API-KEY');    //Sent by Query Params
        //
        //Sent by The authorization API-Key (HTTPHEADER) header 
       $privatekey = $_SERVER['HTTP_X_API_KEY'] ? $_SERVER['HTTP_X_API_KEY'] : ($this->input->get('X-API-KEY'));
                
        if ($this->_settings->api_access != 1) {
            $data['status'] = 'ERROR';
            $data['code'] = REST_Controller::HTTP_FORBIDDEN;
            $data['api_access_status'] = $this->_settings->api_access ? 'Active' : 'Blocked';
            $data['error'] = 'API access is blocked.';            
            $this->response($data, REST_Controller::HTTP_FORBIDDEN);
            echo json_encode($data);
            exit();
        }//end if
        if (empty($this->_settings->api_privatekey)) {
            $data['status'] = 'ERROR';
            $data['code'] = REST_Controller::HTTP_FORBIDDEN;
            $data['error'] = 'API key not available or generated';
            $this->response($data, REST_Controller::HTTP_FORBIDDEN);
            echo json_encode($data);
            exit();
        } 
        elseif (( $this->_settings->api_privatekey !== $privatekey) || (empty($privatekey)) ) {
            $data['status'] = 'ERROR';
            $data['code'] = REST_Controller::HTTP_UNAUTHORIZED;
            $data['error'] = 'API key mismatch';
            $this->response($data, REST_Controller::HTTP_UNAUTHORIZED);
            echo json_encode($data);
            exit();
        }
        
    }
    
    private function _get_api_request_body_data(){
        /*
        * When received post data in Json format
        */
       $input = file_get_contents('php://input');

       /*
        * Save input date into global post variable for apply CI field validation.
        */
       $postJson = json_decode($input, true); 
       
       return $postJson;
    }
    
    
    /**
     * Get All Data from this method.
     *
     * @return Response
    */
    public function index_get($action = null, $id = null, $para_x = null, $para_y = null, $para_z = null) {
            
        switch ($action) {
                
                case 'store_settings':                
                    
                    $response['status'] = 'SUCCESS';
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $data['data'] = $this->store_settings;
                    $this->response($data, REST_Controller::HTTP_OK);
                    break;
                
                case 'customers':
                    $customers = $this->restapi5_model->get_company('customer', $id);
                    
                    $response['status'] = 'SUCCESS';
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $data['data'] = $customers;
                    $this->response($data, REST_Controller::HTTP_OK);
                    break;
                    
                case 'billers':
                   $biller = $this->restapi5_model->get_company('biller', $id);
                    
                    $response['status'] = 'SUCCESS';
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $data['data'] = $biller;
                    $this->response($data, REST_Controller::HTTP_OK);
                    break;
                
                case 'suppliers':
                    $supplier = $this->restapi5_model->get_company('supplier', $id);
                    
                    $response['status'] = 'SUCCESS';
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $data['data'] = $supplier;
                    $this->response($data, REST_Controller::HTTP_OK);
                    break;

                case 'catlogs': 
                    
                    $this->_get_catlogs();
                    
                    break;
                
                case 'catlog_products':                
                    $this->_get_catlog_products($id);                
                    break;
                
                case 'product_details':
                    $product_id = $id;
                    $this->_get_catlog_products( $product_id );
                    break;
                
                case 'products':                    
                    $products = $this->restapi5_model->get_products($id); 
                    
                    $response['status'] = 'SUCCESS';
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $data['data'] = $products;
                    $this->response($data, REST_Controller::HTTP_OK);
                    break; 
                
                case 'product_reviews':
                    $product_id = $id;
                    $limit = $para_x;
                    $this->_get_product_reviews($product_id, $limit);
                    break; 
                
                case 'categories':                    
                    $this->_get_categories($id);
                    break;
                
                case 'subcategories':                    
                    $this->_get_subcategories($id);
                    break;
                
                case 'brands':                    
                    $this->_get_brands($id);
                    break;
                
                case 'customer_address': 
                    $customer_id = $id;
                    $address_id = $para_x;
                    
                    $this->_get_customer_address($customer_id, $address_id);
                    
                    break;
                
                case 'customer_views_products':
                    
                    $customer_id = $id;
                    $limits  = $para_x;
                    
                    $this->_customer_views_products($customer_id, $limits);
                                    
                    break;
                
                case 'payment_methods':
                    
                    $this->_get_payment_methods();
                    break;
                
                case 'shipping_methods':
                    
                    $this->_get_shipping_methods();
                    break;
                
                case 'payment_shipping_methods':
                    $this->_get_payment_shipping_methods();
                    break;
                
                case 'customer_wishlist':
                    
                    $this->_get_customer_wishlist($id);
                    break;
                
                case 'customer_orders':
                    
                    $this->_get_customer_orders($id);
                    break;
                
                case 'customer_pending_orders':
                    
                    $this->_get_customer_orders($id, 'pending');
                    break;
                
                case 'customer_canceled_orders':
                    
                    $this->_get_customer_orders($id, 'canceled');
                    break;
                
                case 'customer_allsales':
                    
                    $this->_get_customer_allsales($id);
                    break;
                
                case 'sale_invoice':
                    
                    $this->_get_sales_invoice($id);
                    break;
                
                case 'cart_items':
                    
                    $this->_cart_items($id);
                    break;
                
                case 'saveforlater':
                    $saveforlater = 1;
                    $this->_cart_items($id, $saveforlater);
                    break;
                
                case 'customer_feedback':
                    $customer_id = $id;
                    $product_id = $para_x;
                    $this->_get_customer_feedback($customer_id, $product_id);
                    break; 
                
                
                /******************************
                 * Mobile Screenwise API
                 ******************************/
                case 'mobile_homescreen':
                    
                    $customer_id = $id;                    
                    $this->_mobile_homescreen( $customer_id );
                    
                break;
            
                case 'mobile_categoryproducts':
                    
                    $category_id    = $id;                    
                    $subcategory_id = $para_x;                    
                    $customer_id    = $para_y;                    
                    $this->_mobile_categoryproducts( $category_id , $subcategory_id, $customer_id );
                    
                break;
                
                case 'mobile_brandproducts':
                    
                    $brand_id    = $id;                    
                    $customer_id = $para_x;                   
                    $this->_mobile_brandproducts( $brand_id , $customer_id );
                    
                break;
                
                case 'mobile_productdetails':
                    
                    $product_id    = $id;                    
                    $customer_id   = $para_x;                   
                    $this->_mobile_productdetails( $product_id , $customer_id );
                    
                break;
            
                case 'mobile_cartscreen':
                    
                    $customer_id    = $id;                    
                
                    $this->_mobile_cartscreen( $customer_id );
                    
                break;
            
                case 'state_list':
                    
                    $country_id    = $id;                   
                    $this->_get_state_list( $country_id );
                    
                break;
                
                default:
                    
                    $response['status'] = 'SUCCESS';
                    $response['code'] = REST_Controller::HTTP_OK;
                    $response['api_document'] = 'https://bit.ly/3tPB5Df';
                        
                    $this->response($response, REST_Controller::HTTP_OK);
                            
                    break;
            }
      
    }
      
    /**
     * Set All Data from this method.
     *
     * @return Response
    */
    public function index_post($action=null, $id = null, $para_x = null ) {
                
        switch ($action) {
            
            case 'customers':                
                $this->_customers_add($inputs);                
                break;
            
            case 'customer_views_products':
                $this->_add_customer_views_products();
                break;
            
            case 'customer_address':
                $this->_add_customer_address();
                break;
            
            case 'customer_wishlist':
                $this->_add_customer_wishlist();
                break;
            
            case 'catlog_products':
                
                $this->_get_catlog_products();
                break;
            
            case 'product_details':
                $product_id = $id;
                $this->_get_catlog_products( $product_id );
                break;
            
            case 'send_password_otp':
                $this->_send_password_otp();
                break;
                        
            case 'password_otp_varification':
                $this->_password_otp_varification();
                break;
            
            case 'change_password':
                $this->_change_password();
                break;
            
            case 'customer_sales':                    
                $this->_get_customer_sales();
                break;
            
            case 'order':                    
                $this->_order_post();
                break;
            
            case 'order_canceled':                    
                $this->_order_canceled();
                break;
            
            case 'addtocart':                    
                $this->_addtocart();
                break;
            
            case 'customer_rating':                    
                $this->_add_rating();
                break;
            
            case 'customer_feedback':                    
                $this->_add_feedback();
                break;
            
            default:
                break;
        
                
        }//end switch.   
        
    } //index_post
     
    /**
     * Get All Data from this method.
     *
     * @return Response
    */
    public function index_put($action=null, $id=null, $para_x = null, $para_y = null, $para_z = null) { 
        
        switch ($action) {
            
            case 'customers': 
                $customer_id = $id;
                $this->_customers_update($customer_id);                
                break;
            
            case 'customer_address':
                $this->_update_customer_address($id);
                break;
            
            case 'customer_default_address':
                $this->_set_customer_default_address();
                break;
            
            case 'updatecartquantity':
                $this->_updatecartquantity();
                break;
            
            //Cart item move to saveforlater
            case 'saveforlater':
                $customer_id = $id;
                $cart_item_id = $para_x;
                
                $this->_move_to_saveforlater($customer_id, $cart_item_id);
                break;
            
            //saveforlater item move to cart
            case 'movetocart':
                $customer_id = $id;
                $cart_item_id = $para_x;
                
                $this->_move_to_cart($customer_id, $cart_item_id);
                break;
            
            case 'customer_rating':
                
                $this->_update_rating();
                break;
            
            case 'customer_feedback':
                
                $this->_update_feedback();
                break;
            
            default:
                break;                
        }//end switch. 
    }
     
    /**
     * Get All Data from this method.
     *
     * @return Response
    */
    public function index_delete($action=null, $id=null, $para_x = null, $para_y = null, $para_z = null) {
        
        switch ($action) {
            
            case 'customer_address':
                $this->_delete_customer_address($id);
                break;
            
            case 'customer_wishlist':
                $this->_delete_customer_wishlist_item();
                break;
            
            case 'cart_items':
                $customer_id    = $id;
                $cart_item_id   = $para_x;
                $save_for_later = 0;
                $this->_cart_items_delete($customer_id, $cart_item_id, $save_for_later);
                break;
            
            case 'saveforlater':
                $customer_id    = $id;
                $cart_item_id   = $para_x;
                $save_for_later = 1;
                $this->_cart_items_delete($customer_id, $cart_item_id, $save_for_later);
                break;
            
            case 'customer_feedback':
                $customer_id   = $id;
                $feedback_id   = $para_x;
                $this->_delete_feedback($customer_id, $feedback_id);
                break;
            
            default:
                break;
        }//end switch. 
    }
    	
 
    /*************************
     * User Actions
     *************************/    
    public function registration_post() {
        
        if(!isset($_POST['phone'])) {
            
            $postJson = $this->_get_api_request_body_data(); 
            
            if(is_array($postJson)){
                $_POST = $postJson;
            } else {                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
                $response['error']  = !empty($input) ? 'Invalid Json Format Data' : 'Invalid Request';
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST);            
            } 
        }
        
        if(isset($_POST['phone'])) {
            $this->_customers_add();  
        }
    }
    
    public function login_post() {
        
        if(!isset($_POST['username']) && !isset($_POST['password'])) {
            
            $postJson = $this->_get_api_request_body_data(); 
            
            if(is_array($postJson)) {
               $_POST = $postJson; 
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
                $response['error']  = !empty($input) ? 'Invalid Json Format Data' : 'Invalid Request';
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST);            
            } 
        }//end if.            
        
        if(isset($_POST['username']) && isset($_POST['password'])) {
        
            $this->form_validation->set_rules('username', 'Username', 'trim|required');
            $this->form_validation->set_rules('password', 'Password', 'trim|required');

            if($this->form_validation->run() !== FALSE)
            {
                $mobile     = $this->input->post('username');
                $password   = md5($this->input->post('password'));

                $data = $this->restapi5_model->get_customer_by_mobile_number($mobile); 

                if($data) {
                   $userdata = $data[0];

                    if($userdata->password === $password){
                       unset($userdata->password);
                       $response['status']  = 'SUCCESS';
                       $response['code']    = REST_Controller::HTTP_OK;
                       $rdata['response'] = $response;
                       $rdata['data']    = $userdata;
                       
                       $this->response($rdata, REST_Controller::HTTP_OK);
                    } else {
                        $response['status'] = 'ERROR';
                        $response['code']   = REST_Controller::HTTP_UNAUTHORIZED;
                        $response['error']  = 'Invalid Password';
                        $rdata['response'] = $response;
                        $this->response($rdata, REST_Controller::HTTP_UNAUTHORIZED); 
                    }
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_FOUND;
                    $response['error']  = 'User Not Found';
                    $rdata['response'] = $response;
                    $this->response($rdata, REST_Controller::HTTP_NOT_FOUND); 
                }            
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE);
            }            
        } 
        
    }//end if login_post
        
    private function _send_password_otp() {
          
       $postJson = $this->_get_api_request_body_data(); 

        if(is_array($postJson)){
           
            $_POST = $postJson;                            
            
            $this->form_validation->set_rules('customer_mobile', 'Customer mobile number', 'trim|required|numeric|exact_length[10]');
        
            if ($this->form_validation->run() !== FALSE)
            {
                $otp = $this->restapi5_model->get_otp($postJson['customer_mobile']);
                
                if($otp) {
                    $appname = $this->_settings->mobile_app_name;
                    $appname = (bool)$appname ? $appname : 'Simplypos';
                    /*
                    * Send code by SMS 
                    */               
                    $msg = "$appname Forget password verification code: " . $otp;
                    $result = $this->sma->SendSMS($postJson['customer_mobile'], $msg, 'ESHOP_FORGET_PASSWORD'); 
                    
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_INTERNAL_SERVER_ERROR;                    
                    $rdata['response']  = $response;
                    
                    if($result){
                       $data = json_decode($result, true);
                       if($data['type']=="success"){
                            $data['otp'] = $otp;                           
                            $response['status'] = 'SUCCESS';
                            $response['code']   = REST_Controller::HTTP_OK;            
                            $rdata['response']  = $response;            
                            $rdata['data']  = $data;
                        } else {
                            $response['error']  = $data['message'];
                        }
                    }

                    $this->response($rdata, $response['code']); 
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_MODIFIED;
                    $response['error']  = 'OTP not generated';
                    $rdata['response']  = $response;
                    $this->response($rdata, REST_Controller::HTTP_NOT_MODIFIED);  
                }
            
            } else {
                $response['status'] = 'ERROR';
                $response['code'] = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $rdata['request'] = $_POST;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE);
            }
           
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }//End else
       
    }
    
    private function _password_otp_varification() {
          
       $postJson = $this->_get_api_request_body_data(); 

        if(is_array($postJson)){
           
            $_POST = $postJson;
                            
            $this->form_validation->set_rules('customer_mobile', 'Customer mobile number', 'trim|required|numeric|exact_length[10]');
            $this->form_validation->set_rules('otp', 'OTP', 'trim|required|numeric|exact_length[6]');
        
            if ($this->form_validation->run() !== FALSE)
            {
                $customer = $this->restapi5_model->get_customer_mobile_verification_code($postJson['customer_mobile']);
            
                if($customer['mobile_verification_code'] === $postJson['otp']) {
                    unset($customer['mobile_verification_code']);                   
                    $response['status'] = 'SUCCESS';
                    $response['code']   = REST_Controller::HTTP_OK;            
                    $rdata['response']  = $response;            
                    $rdata['data']  = $customer;

                    $this->response($rdata, REST_Controller::HTTP_OK); 
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_UNAUTHORIZED;
                    $response['error']  = 'Invalid OTP';
                    $rdata['data']  = $customer['mobile_verification_code'];
                    $this->response($rdata, REST_Controller::HTTP_UNAUTHORIZED);  
                }
            } else {
                $response['status'] = 'ERROR';
                $response['code'] = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $rdata['request'] = $_POST;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE);
            }
           
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }//End else
       
    }
    
    private function _change_password() {
          
       $postJson = $this->_get_api_request_body_data(); 

        if(is_array($postJson)){
           
            $_POST = $postJson;
                            
            $this->form_validation->set_rules('customer_id', 'Customer id', 'trim|required|numeric');
            $this->form_validation->set_rules('customer_mobile', 'Customer mobile number', 'trim|required|numeric|exact_length[10]');
            $this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[8]|max_length[12]|callback_check_strong_password');
        
            if ($this->form_validation->run() !== FALSE)
            {
                $result = $this->restapi5_model->update_customer_password($postJson['customer_id'], $postJson['customer_mobile'], $postJson['password']);
            
                if($result) {                                        
                    $response['status'] = 'SUCCESS';
                    $response['code']   = REST_Controller::HTTP_OK;            
                    $rdata['response']  = $response;
                    $this->response($rdata, REST_Controller::HTTP_OK); 
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error']  = 'Request Failed';
                    $rdata['data']  = $response;
                    $this->response($rdata, REST_Controller::HTTP_NOT_IMPLEMENTED);  
                }
            } else {
                $response['status'] = 'ERROR';
                $response['code']  = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error'] = validation_errors();
                $rdata['response'] = $response;
                $rdata['request']  = $_POST;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE);
            }
           
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST);
        }//End else
       
    }
    
    public function check_strong_password($str) {
        
       if (preg_match('#[0-9]#', $str) && preg_match('#[a-zA-Z]#', $str)) {
         return TRUE;
       }
       $this->form_validation->set_message('check_strong_password', 'The password field must be contains at least one letter and one digit.');
       
       return FALSE;
       
    }

    
    /****************************
     * Customers Add & Update
     ****************************/
    private function _customers_add() {           
                
        $this->form_validation->set_rules('name', 'Customer Name', 'trim|required');
        $this->form_validation->set_rules('phone', 'Mobile Number', 'trim|required|numeric|exact_length[10]|is_unique[sma_companies.phone]');
        $this->form_validation->set_rules('email', 'Email Address', 'trim|required|valid_email|is_unique[sma_companies.email]');
        $this->form_validation->set_rules('password', 'Login Password', 'trim|required|min_length[8]|max_length[12]|callback_check_strong_password');
              
        if ($this->form_validation->run() !== FALSE) {
            
            $data['name']       = ucwords(strtolower($this->input->post('name')));
            $data['phone']      = $this->input->post('phone');
            $data['email']      = strtolower($this->input->post('email'));
            $data['password']   = md5($this->input->post('password'));
            $data['group_id']            = 3;
            $data['group_name']          = 'customer';
            $data['customer_group_id']   = 1;
            $data['customer_group_name'] = 'General';
            $data['customer_group_id']   = 1;
            $data['company']             = '-';
            
            if($result = $this->restapi5_model->set_customer($data)){
                unset($data['password']);
                $data['id'] = $result;
                $response['status'] = 'SUCCESS';
                $response['code'] = REST_Controller::HTTP_OK;
                $rdata['response'] = $response;
                $rdata['data'] = $data;
                
                $this->response($rdata, REST_Controller::HTTP_OK);
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_INTERNAL_SERVER_ERROR;
                $response['error']  = $result;
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_NOT_ACCEPTABLE;
            $response['error']  = validation_errors();
            $rdata['response'] = $response;
            $rdata['request'] = $_POST;
            $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE);
        }
                
    }
    
    private function _customers_update($customer_id = null) {           
         
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $this->form_validation->set_data($postJson);
            
            if(!(bool)$customer_id) {
                $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            }
            
            $this->form_validation->set_rules('customer_name', 'customer_name', 'trim|required');
            $this->form_validation->set_rules('address', 'address', 'trim|required');
            $this->form_validation->set_rules('city', 'city', 'trim|required');
            $this->form_validation->set_rules('state', 'state', 'trim|required');
            $this->form_validation->set_rules('state_code', 'state_code', 'trim|required');
            $this->form_validation->set_rules('postal_code', 'postal_code', 'trim|required|numeric');
            $this->form_validation->set_rules('country', 'country', 'trim|required');           
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = (bool)$customer_id ? $customer_id : $this->put('customer_id');  
        
                $data['name']           = ucwords(strtolower($this->put('customer_name')));
               // $data['email']          = strtolower($this->put('email'));
                $data['address']        = $this->put('address');
                $data['city']           = ucwords($this->put('city'));
                $data['state']          = ucwords($this->put('state'));
                $data['state_code']     = $this->put('state_code');
                $data['postal_code']    = $this->put('postal_code');
                $data['country']        = ucwords($this->put('country'));            
                $data['company']        = ((bool)$this->put('company')) ? $this->put('company') : '-';
                $data['updated_at']     = date('y-m-d H:i:s');
            
                if($result = $this->restapi5_model->update_customer($customer_id, $data)){
                                        
                    $response['status'] = 'SUCCESS';
                    $response['code']   = REST_Controller::HTTP_OK;
                    $rdata['response']  = $response;

                    $this->response($rdata, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_INTERNAL_SERVER_ERROR;
                    $response['error']  = "Update request failed";
                    $rdata['response']  = $response;
                    $this->response($rdata, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                }
            } else {
                $response['status'] = 'ERROR';
                $response['code'] = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $rdata['request'] = $postJson;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE);
            }
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response'] = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }       
    }
    
    
    /************************************
     * Customer Recently View Products
     ************************************/
    private function _add_customer_views_products(){
        
       $postJson = $this->_get_api_request_body_data(); 

       if(is_array($postJson)){
            $_POST = $postJson;
                    
            $this->form_validation->set_rules('customer_id', 'customer_id', 'required');
            $this->form_validation->set_rules('product_id', 'product_id', 'required');

            if ($this->form_validation->run() !== FALSE)
            {
                $data['user_id']    = $_POST['customer_id'];
                $data['product_id'] = $_POST['product_id'];
                $result = $this->restapi5_model->add_customer_views_products($data);
                
                $response['status'] = 'SUCCESS';
                $response['code'] =  REST_Controller::HTTP_OK;
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_OK);
                
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response'] = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }
        
    }
    
    private function _customer_views_products($customer_id = null, $limits = null){
        
        if((bool)$customer_id) { 
            
            $recentlyViews = $this->restapi5_model->get_customer_views_products($customer_id, $limits);

            $response['status'] = 'SUCCESS';

            if($recentlyViews) {
                $response['code'] = REST_Controller::HTTP_OK;
                $data['response'] = $response;
                $data['data'] = $recentlyViews;
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $response['code'] = REST_Controller::HTTP_OK;
                $response['error'] = 'records not found.';
                $data['response'] = $response;
                $this->response($data, REST_Controller::HTTP_OK);
            }

        } else {
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_BAD_REQUEST;
            $response['error'] = 'Customer id is missing.';

            $data['response'] = $response;

            $this->response($data, REST_Controller::HTTP_BAD_REQUEST);
        } 
        
    }

    /*****************************
     * Customer Wishlist Items
     *****************************/
    private function _get_customer_wishlist($customer_id=null) {
        
        $wishlist = (array)$this->restapi5_model->get_customer_wishlist($customer_id);
        
        if($wishlist['count']){
            $response['status'] = "SUCCESS";
            $response['code'] = REST_Controller::HTTP_OK;
            $response['count'] = $wishlist['count'];

            $data['response'] = $response;
            $data['data'] = $wishlist['result'];

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_NOT_FOUND;
            $response['error'] = 'Wishlist Is Empty';
            $data['response'] = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
        
    }
    
    private function _add_customer_wishlist(){
        
       $postJson = $this->_get_api_request_body_data(); 

       if(is_array($postJson)){
            $_POST = $postJson;
                    
            $this->form_validation->set_rules('customer_id', 'customer_id', 'required');
            $this->form_validation->set_rules('product_id', 'product_id', 'required');

            if ($this->form_validation->run() !== FALSE)
            {
                $data['user_id']    = $_POST['customer_id'];
                $data['product_id'] = $_POST['product_id'];
                $data['option_id'] = isset($_POST['variant_id']) ? $_POST['variant_id'] : 0;
                
                $result = $this->restapi5_model->add_customer_wishlist_products($data);
                
                $response['status'] = 'SUCCESS';
                $response['code'] =  REST_Controller::HTTP_OK;
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_OK);
                
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response'] = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }
        
    }
    
    private function _delete_customer_wishlist_item(){
        
        $postJson = $this->_get_api_request_body_data();
            
        if(is_array($postJson)){

            if ((bool)$postJson['customer_id'] && (bool)$postJson['product_id'])
            {
                $data['user_id']    = $postJson['customer_id'];
                $data['product_id'] = $postJson['product_id'];
                $data['option_id']  = isset($postJson['variant_id']) ? $postJson['variant_id'] : 0;
                
                $result = $this->restapi5_model->delete_customer_wishlist_products($data);
                if($result) {
                    $response['status'] = 'SUCCESS';
                    $response['code'] =  REST_Controller::HTTP_OK;
                    $rdata['response'] = $response;
                    $this->response($rdata, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error']  = "Request Failed";
                    $rdata['response'] = $response;
                    $this->response($rdata, REST_Controller::HTTP_NOT_IMPLEMENTED);
                } 
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = "Customer id & Product id is required.";
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response'] = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }
        
    }
    
    
    /**********************
     * Customer Address
     **********************/
    private function _add_customer_address(){
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            $_POST = $postJson;
                    
            $this->form_validation->set_rules('customer_id', 'customer_id', 'required');
            $this->form_validation->set_rules('customer_name', 'customer_name', 'required');
            $this->form_validation->set_rules('address_line_1', 'address_line_1', 'required');
            $this->form_validation->set_rules('address_line_2', 'address_line_2', 'required');
            $this->form_validation->set_rules('city', 'City Name', 'required');
            $this->form_validation->set_rules('state', 'state', 'required');
            $this->form_validation->set_rules('postal_code', 'postal_code', 'required');
            $this->form_validation->set_rules('phone', 'customer_phone', 'required');

            if ($this->form_validation->run() !== FALSE)
            {
                $data['company_id']     = $_POST['customer_id'];
                $data['address_name']   = $_POST['customer_name'];
                $data['line1']          = $_POST['address_line_1'];
                $data['line2']          = $_POST['address_line_2'];
                $data['city']           = $_POST['city'];
                $data['state']          = $_POST['state'];
                $data['state_code']     = $_POST['state_code'] ? $_POST['state_code'] : null;
                $data['postal_code']    = $_POST['postal_code'];
                $data['phone']          = $_POST['phone'];
                $data['email_id']       = !empty($_POST['email']) ? $_POST['email'] : null;
                $data['country']        = $_POST['country'] ? $_POST['country'] : 'India';                
                $data['is_default']     = $_POST['is_default'] ? $_POST['is_default'] : 1;                
                
                $result = $this->restapi5_model->add_customer_address($data);
               
                $response['status'] = 'SUCCESS';
                $response['code'] = REST_Controller::HTTP_OK;
                $response['msg'] = "$result Record Inserted";
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_OK);
                
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response'] = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }
        
    }
    
    private function _get_customer_address($customer_id = null, $address_id = null ) {
        if($customer_id) {
            $addresses = $this->restapi5_model->get_customer_addresses($customer_id, $address_id);
            
            $response['status'] = 'SUCCESS';
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data'] = $addresses ? $addresses : [];
            $this->response($data, REST_Controller::HTTP_OK);            

        } else {
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_BAD_REQUEST;
            $response['error'] = 'Customer id is missing.';                        
            $data['response'] = $response;

            $this->response($data, REST_Controller::HTTP_BAD_REQUEST);
        }
    }
    
    private function _update_customer_address($address_id = null){
       
        if((bool)$address_id){
            
           $postJson = $this->_get_api_request_body_data(); 

           if(is_array($postJson) && $address_id){

                $data = [];
                if(!empty($postJson['customer_name']))  { $data['address_name']   = $postJson['customer_name']; }
                if(!empty($postJson['address_line_1'])) { $data['line1']          = $postJson['address_line_1']; }
                if(!empty($postJson['address_line_2'])) { $data['line2']          = $postJson['address_line_2']; }
                if(!empty($postJson['city']))           { $data['city']           = $postJson['city']; }
                if(!empty($postJson['state']))          { $data['state']          = $postJson['state']; }
                if(!empty($postJson['state_code']))     { $data['state_code']     = $postJson['state_code']; }
                if(!empty($postJson['postal_code']))    { $data['postal_code']    = $postJson['postal_code']; }
                if(!empty($postJson['phone']))          { $data['phone']          = $postJson['phone']; }
                if(!empty($postJson['email']))          { $data['email_id']       = $postJson['email']; }
                if(!empty($postJson['country']))        { $data['country']        = $postJson['country']; }                
                if($postJson['is_default']!='')         { $data['is_default']     = $postJson['is_default']; }                
                
                $data['updated_at']     = date('Y-m-d H:i:s');
                
                if(!empty($data)) {
                    
                    $result = $this->restapi5_model->update_customer_address($address_id , $data);

                    if($result) {
                        $response['status'] = 'SUCCESS';
                        $response['code'] = REST_Controller::HTTP_OK;
                        $response['msg'] = "$result Records Updated";
                        $rdata['response'] = $response;
                        $this->response($rdata, REST_Controller::HTTP_OK);
                    } else {
                        $response['status'] = 'ERROR';
                        $response['code'] = REST_Controller::HTTP_NOT_MODIFIED;
                        $response['error'] = 'No Update Found';
                        $rdata['response'] = $response;
                        $this->response($rdata, REST_Controller::HTTP_NOT_MODIFIED);
                    }
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_METHOD_NOT_ALLOWED;
                    $response['error']  = 'Invalid Data Request';
                    $rdata['response'] = $response;
                    $this->response($rdata, REST_Controller::HTTP_METHOD_NOT_ALLOWED); 
                }
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
                $response['error']  = 'Invalid Json Request';
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            }

        } else {
            
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_NOT_FOUND;
            $response['error'] = 'Address id is missing.';
            $rdata['response'] = $response;
            $this->response($rdata, REST_Controller::HTTP_NOT_FOUND); 
        }
        
    }
    
    private function _set_customer_default_address(){
            
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $this->form_validation->set_data($postJson);
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            $this->form_validation->set_rules('address_id', 'address_id', 'trim|required|numeric');
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = $this->put('customer_id');            
                $address_id  = $this->put('address_id');
                           
                $address_data = [
                    "company_id" => $customer_id,
                    "id"  => $address_id
                ];
            
                if($this->restapi5_model->is_valid_customer_address_id($address_data)){
                   
                    $setDefaultAddress = $this->restapi5_model->set_customer_default_address($address_data);
            
                    if((bool)$setDefaultAddress){
                        $response['status'] = "SUCCESS";
                        $response['code'] = REST_Controller::HTTP_OK;
                        $data['response'] = $response;
                        $this->response($data, REST_Controller::HTTP_OK);
                    } else {
                        $response['status'] = 'ERROR';
                        $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                        $response['error'] = 'Request Failed';
                        $data['response'] = $response;
                        $this->response($data, REST_Controller::HTTP_OK);
                    } 
                    
                } else {                    
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_FOUND;
                    $response['error']  = "Address id {$address_id} is not belongs to the customer.";
                    $rdata['response']  = $response;
                    $this->response($rdata, REST_Controller::HTTP_OK); 
                }
                
            } else {
                $validation_error = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', validation_errors());
                $validation_error = str_replace(['<p>','</p>','\n'], '', $validation_error);
                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = $validation_error;
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_OK); 
            }            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST);             
        }//End else
        
    }
    
    private function _delete_customer_address($address_id = null){

       if($address_id){
            
            $result = $this->restapi5_model->delete_customer_address($address_id);
            
            if($result) {
                $response['status'] = 'SUCCESS';
                $response['code']   = REST_Controller::HTTP_OK;
                $response['msg'] = "$result Records Deleted";
                $data['response'] = $response;
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $response['status'] = 'ERROR';
                $response['code'] = REST_Controller::HTTP_NOT_FOUND;
                $response['error'] = 'No Record Found';
                $data['response'] = $response;
                $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            }            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Address id is missing';
            $data['response'] = $response;
            $this->response($data, REST_Controller::HTTP_BAD_REQUEST); 
        }
        
    }
      
    
    /*****************************
     * Products Catlog Actions
     *****************************/
    private function _get_catlogs() {
      
        $catlogs = $this->restapi5_model->get_catlogs();
                
        $response['status'] = 'SUCCESS';
        $response['code'] = REST_Controller::HTTP_OK;
        $data['response'] = $response;
        $data['data'] = $catlogs;
        $this->response($data, REST_Controller::HTTP_OK);
        
    }
    
    private function _get_catlog_products($product_id = null, $filters = null){
     
        $filters = ($filters !== null) ? $filters : $this->_get_api_request_body_data();
        
        if($product_id){
           // $data['request'] = ['product_id'=>$product_id];
        } else if($filters) {
           // $data['request'] = $filters;
            $product_id = (isset($filters['product_id']) && (bool)$filters['product_id']) ? $filters['product_id'] : null;
        }
        
        $result = $this->restapi5_model->get_catlog_products($product_id, $filters, $this->per_page_limit); 
       
        $response['status']          = 'SUCCESS';
        $response['code']            = REST_Controller::HTTP_OK;
        if(!$product_id){
            $response['total_count']     = (int)$result['count_all'];
            $response['response_count']  = (int)$result['count'];
        }
        $data['response'] = $response;
        $data['data']     = $result['data'];

        $this->response($data, REST_Controller::HTTP_OK);
       
    }
    
    private function _get_categories($category_id = null){        
        
        $categories = $this->restapi5_model->get_categories( $category_id ); 
         
        $response['status'] = "SUCCESS";
        $response['code'] = REST_Controller::HTTP_OK;

        $data['response'] = $response;
        $data['data'] = $categories ? $categories : [];
        
        $this->response($data, REST_Controller::HTTP_OK);
    }
    
    private function _get_subcategories($parent_id = null){
        
        $categories = $this->restapi5_model->get_subcategories($parent_id); 
         
        if($categories !== false){
            $response['status'] = "SUCCESS";
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data'] = $categories ? $categories : [];

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $response['status'] = "ERROR";
            $response['code'] = REST_Controller::HTTP_NOT_FOUND;
            $response['error'] = 'Not Found';
            $data['response'] = $response;

            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
    }

    private function _get_brands($brand_id) {
        
        $brands = $this->restapi5_model->get_brands($brand_id);
         
        if($brands !== false){
            $response['status'] = "SUCCESS";
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data'] = $brands ? $brands : [];

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $response['status'] = "ERROR";
            $response['code'] = REST_Controller::HTTP_NOT_FOUND;
            $response['error'] = 'Internal Server Error';
            $data['response'] = $response;

            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
    }
      
    private function _get_payment_methods() {
        
        $paymethods = $this->restapi5_model->get_payment_methods();
                     
        $response['status'] = "SUCCESS";
        $response['code'] = REST_Controller::HTTP_OK;            
        $data['response'] = $response;
        $data['data'] = $paymethods ? $paymethods : [];

        $this->response($data, REST_Controller::HTTP_OK);
    }
    
    private function _get_shipping_methods() {
        
        $shipmethods = $this->restapi5_model->get_shipping_methods();
                     
        $response['status'] = "SUCCESS";
        $response['code'] = REST_Controller::HTTP_OK;

        $data['response'] = $response;
        $data['data'] = $shipmethods ? $shipmethods : [];

        $this->response($data, REST_Controller::HTTP_OK);
    }
           
    
    /***********************************
     * Customer Orders & Sale History
     ***********************************/
    private function _order_post() {
        
       $postJson = $this->_get_api_request_body_data();
              
        if(is_array($postJson)){
           
            $_POST = $postJson;
            
            $postProducts = $postJson['products'];
            $postPayment  = $postJson['payment'];
            $postDelivery = $postJson['delivery'];
                
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required');
            $this->form_validation->set_rules('biller_id', 'biller_id', 'trim|required');
            $this->form_validation->set_rules('warehouse_id', 'warehouse_id', 'trim|required');
            $this->form_validation->set_rules('total_items', 'total_items', 'trim|required');
            $this->form_validation->set_rules('gross_total', 'gross_total', 'trim|required');
            $this->form_validation->set_rules('delivery[delivery_status]', 'delivery_status', 'trim|required');
            $this->form_validation->set_rules('payment[payment_status]', 'payment_status', 'trim|required');
            $this->form_validation->set_rules('payment[payment_method]', 'payment_method', 'trim|required');
            $this->form_validation->set_rules('products[]', 'products', 'trim|required');
            
            foreach($postProducts as $ind=>$product) 
            {
                $this->form_validation->set_rules("products[$ind][product_code]", "product_code[$ind]", 'trim|required');
                $this->form_validation->set_rules("products[$ind][quantity]", "product_quantity[$ind]", 'trim|required');
                $this->form_validation->set_rules("products[$ind][unit_price]", "unit_price[$ind]", 'trim|required');
                $this->form_validation->set_rules("products[$ind][unit_selling_price]", "unit_selling_price[$ind]", 'trim|required');
            }
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = $this->input->post('customer_id');
                $customer = (array)$this->restapi5_model->get_company('customer', $customer_id);
            
                $address_id  = $postDelivery['delivery_address_id'];
                if($address_id) {
                    $address = (array)$this->restapi5_model->get_customer_addresses(null , $address_id);
                } 
                elseif(!empty ($postDelivery['delivery_address'])) {
                    $address['company_id']   = $customer_id;
                    $address['address_name'] = $postDelivery['address_name'];                    
                    $address['line1']        = $postDelivery['address_line_1'];
                    $address['line2']        = $postDelivery['address_line_2'];
                    $address['city']         = $postDelivery['city'];
                    $address['postal_code']  = $postDelivery['pincode'];
                    $address['state']        = $postDelivery['state_name'];
                    $address['state_code']   = $postDelivery['state_code'];
                    $address['country']      = $postDelivery['country'];
                    $address['phone']        = $postDelivery['phone'];
                    $address['email_id']     = $postDelivery['email'];
                    $address['is_default']   = 1;
                    
                    $address_id = $this->restapi5_model->add_customer_address($address);                                        
                } 
                else {
                    /*
                     * Fetch Customer Default Address 
                     * If address and address id not provided.  
                     */
                    $defaultAddress = (array)$this->restapi5_model->get_customer_addresses($customer_id, null, 1);
                    if($defaultAddress){
                        $address = (array)$defaultAddress[0];
                        $address_id = $address['address_id'];
                    } else {
                        /*
                         * Add Customer details in address table 
                         * if customer address is not available.
                         */
                        $address['company_id']   = $customer_id;
                        $address['address_name'] = $customer['name'];                    
                        $address['line1']        = $customer['address'];
                        $address['line2']        = null;
                        $address['city']         = $customer['city'];
                        $address['postal_code']  = $customer['postal_code'];
                        $address['state']        = $customer['state'];
                        $address['state_code']   = $customer['state_code'];
                        $address['country']      = $customer['country'];
                        $address['phone']        = $customer['phone'];
                        $address['email']        = $customer['email'];
                        $address['is_default']   = 1;
                        
                        $address_id = $this->restapi5_model->add_customer_address($address);
                    }//end else add customer address
                }//end else
            
                $warehouse_id   = $this->input->post('warehouse_id');
                $biller_id      = $this->input->post('biller_id');
                $biller         = (array)$this->restapi5_model->get_company('biller', $biller_id);
                
                /*
                 * Get assign variable for calculate IGST OR CGST+SGST
                 */
                if ((!empty($address['state_code']) && !empty($biller['state_code'])) && $address['state_code'] != $biller['state_code']) {
                    $interStateTax = true;
                } else {
                    $interStateTax = false;
                }
                
                if($postJson['products']){
                    
                    $isValidProducts = true;
                    $total_cgst = $total_sgst = $total_igst = 0;
                    $total_item_weight = 0;
                    $total_item_discount = 0;
                    $total_item_tax = 0;
                    $gross_total = 0;
                    $total_items = 0;
                    
                    foreach ($postJson['products'] as $key=>$item) {
            
                        $product = (array)$this->restapi5_model->get_product_by_code($item['product_code']);
            
                        if($product['product_id'] == $item['product_id']){
                            
                            $product_name = $product['product_name'];
                            $product_name .= ($item['variant_id']) ? ' ('. $item['variant_name'].')' : '';
                            
                            $option_id = ((bool)$item['variant_id']) ? $item['variant_id'] : 0;
                            
                            $quantity = ((bool)$item['quantity']) ? $item['quantity'] : 1;
                            $item_weight = $product['weight'] * $quantity;                        
                            $unit_quantity = $quantity;
                            
                            if($product['storage_type'] == 'packed' && (bool)$option_id){
                               $variant = (array)$this->restapi5_model->get_variant_by_id($option_id);
                               $variant_unit_qty = (bool)$variant['unit_quantity'] ? $variant['unit_quantity'] : 1;
                               $unit_quantity = $quantity * $variant_unit_qty;
                               $item_weight   = $quantity * $variant['unit_weight'];
                            }
                            $total_item_weight += $item_weight;
                                                        
                            $real_unit_price    = $this->sma->formatDecimal($item['unit_price'],4);
                            $unit_price         = $this->sma->formatDecimal($item['unit_selling_price'],4);
                            
                            $mrp                = (bool)$product['mrp'] ? $product['mrp'] : $real_unit_price;
                            $net_price          = $mrp * $quantity;
                            
                            $discount_rate = null;
                            $unit_discount = 0;
                            $item_discount = 0;
                                
                            if($real_unit_price > $unit_price){
                                $unit_discount = $real_unit_price - $unit_price;
                                $item_discount = $this->sma->formatDecimal(($unit_discount * $quantity),4);                                
                                $discount_rate = $this->sma->formatDecimal((100 * ($real_unit_price - $unit_price) / $real_unit_price),4) . '%';
                            }//end if discounts
                            $total_item_discount += $item_discount;
                            
                            $tax_rate = str_replace('%','',$item['tax_rate']);
                            $unit_tax = 0;
                            $item_tax = 0;
                            $tax = 0;
                            if((bool)$tax_rate){
                                $unit_tax = $this->sma->formatDecimal(((float)$unit_price * (float)$tax_rate / (100 + (float)$tax_rate)),4);
                                $item_tax = $this->sma->formatDecimal(($unit_tax * $quantity),4);
                                $tax = $this->sma->formatDecimal($tax_rate,2) . '%';
                            }
                            
                            $cgst = $sgst = $igst = 0;
                            if($item_tax) {
                                if((bool)$interStateTax){
                                    $igst = $item_tax;
                                } else {
                                    $cgst = $sgst = $this->sma->formatDecimal(($item_tax / 2),4);
                                }
                                
                                $total_item_tax += $item_tax;
                                $total_cgst += $cgst;
                                $total_sgst += $sgst;
                                $total_igst += $igst;
                            }
                            
                            $net_unit_price     = $real_unit_price - ($unit_discount + $unit_tax); 
                            $invoice_unit_price = $real_unit_price - ($unit_discount + $unit_tax);
                            
                            $invoice_net_unit_price         = $net_unit_price + $unit_discount + $unit_tax;
                            $invoice_total_net_unit_price   = $invoice_net_unit_price * $quantity;
                            
                            $subtotal = $unit_price * $quantity;                            
                            $total += $this->sma->formatDecimal(((float) $net_unit_price * (float) $quantity),4);
                            
                            $orderItems[] = [
                                "product_id"        => $product['product_id'],
                                "product_code"      => $product['product_code'],
                                "product_name"      => $product_name,
                                "product_type"      => $product['product_type'],
                                "option_id"         => $option_id,                                
                                "real_unit_price"   => $real_unit_price,
                                "unit_discount"     => $unit_discount,                                
                                "item_discount"     => $item_discount,
                                "discount"          => $discount_rate,
                                "unit_tax"          => $unit_tax,
                                "item_tax"          => $item_tax,
                                "tax"               => $tax,
                                "tax_method"        => 0,
                                "tax_rate_id"       => $product['tax_rate_id'],
                                "quantity"          => $quantity,
                                "unit_price"        => $unit_price,
                                "subtotal"          => $subtotal,
                                "mrp"               => $mrp,
                                "net_price"         => $net_price,
                                "net_unit_price"                => $net_unit_price,                                
                                "invoice_unit_price"            => $invoice_unit_price,
                                "invoice_net_unit_price"        => $invoice_net_unit_price,
                                "invoice_total_net_unit_price"  => $invoice_total_net_unit_price,
                                "warehouse_id"      => $warehouse_id,                                
                                "product_unit_id"   => $product['sale_unit_id'],
                                "product_unit_code" => $product['sale_unit_code'],
                                "unit_quantity"     => $unit_quantity,
                                "item_weight"       => $item_weight,
                                "hsn_code"          => $product['hsn_code'],
                                "note"              => $item['note'],
                                "delivery_status"   => $postDelivery['delivery_status'],
                                "pending_quantity"  => $quantity,
                                "delivered_quantity"=> 0,
                                "gst_rate" => $tax,
                                "cgst" => $cgst,
                                "sgst" => $sgst,
                                "igst" => $igst,           
                            ];
                            
                            $total_items++;
                            
                        } else {
                            $isValidProducts = false;
                            $response['status'] = 'ERROR';
                            $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                            $response['error']  = "Product id mismatch";
                            $response['product_code'][$key] = $item['product_code'];
                            $rdata['response'] = $response;
                        }//end else
                        
                    }//end foreach.
                    
                    
                    if((bool)$total_items && $total_items == $this->input->post('total_items')){
                        
                        $reference = $this->site->getReferenceNumber('ordr');
                        $date = date('Y-m-d H:i:s');
                        $customer_id = $customer['id'];
                        $customer_name = $customer['name'];
                        $note = $this->db->escape($this->input->post('customer_note'));
                        $shipping = $this->input->post('shipping_amount');
                        $shipping_method = $this->input->post('shipping_method');
                        $order_discount_id = NULL;
                        $order_discount = 0;
                        $order_tax_id = NULL;
                        $order_tax = 0;

                        $total_discount = $total_item_discount + $order_discount;
                        $total_tax = $total_item_tax + $order_tax;

                        $grand_total = (($total + $total_tax + $shipping) - $order_discount);
                        $rounding = 0;

                        $store_settings = $this->restapi5_model->get_store_settings();

                        if ((bool)$store_settings->rounding) {
                            $round_total = $this->sma->roundNumber($grand_total, $store_settings->rounding);
                            $rounding = ($round_total - $grand_total);
                        }

                        $order = array(
                            'eshop_sale'    => 1,
                            'date'          => $date,
                            'reference_no'  => $reference,
                            'customer_id'   => $customer_id,
                            'customer'      => $customer_name,
                            'biller_id'     => $biller_id,
                            'biller'        => $biller['name'],
                            'warehouse_id'  => $warehouse_id,
                            'note'          => $note,
                            'staff_note'    => null,
                            'total'             => $this->sma->formatDecimal($total, 4),
                            'product_discount'  => $this->sma->formatDecimal($total_item_discount, 4),
                            'order_discount_id' => $order_discount_id,
                            'order_discount'    => $this->sma->formatDecimal($order_discount, 4),
                            'total_discount'    => $this->sma->formatDecimal($total_discount, 4),
                            'product_tax'       => $this->sma->formatDecimal($total_item_tax, 4),
                            'order_tax_id'      => $order_tax_id,
                            'order_tax'         => $this->sma->formatDecimal($order_tax, 4),
                            'total_tax'         => $this->sma->formatDecimal($total_tax, 4),
                            'shipping'          => $this->sma->formatDecimal($shipping, 4),
                            'grand_total'       => $this->sma->formatDecimal($grand_total, 4),
                            'total_items'       => $total_items,
                            'sale_status'       => 'pending',
                            'payment_status'    => $postPayment['payment_status'],
                            'payment_method'    => $postPayment['payment_method'],
                            'payment_term'      => null,
                            'rounding'          => $this->sma->formatDecimal($rounding, 4),
                            'due_date'          => NULL,
                            'paid'              => $postPayment['paid_amount'],
                            'eshop_order_alert_status' => 0,
                            'created_by'        => NULL,
                            'cgst'              => $this->sma->formatDecimal($total_cgst, 4),
                            'sgst'              => $this->sma->formatDecimal($total_sgst, 4),
                            'igst'              => $this->sma->formatDecimal($total_igst, 4),
                            'billing_address_id'  => $address_id,
                            'shipping_address_id' => $address_id,
                            'deliver_later'     => $postDelivery['deliver_date'],
                            'time_slotes'       => $postDelivery['delivery_time'],
                            'shipping_method'   => $shipping_method,
                            'total_weight'      => $total_item_weight,
                        );
                        
                       
                        $order_id = $this->restapi5_model->add_order($order, $orderItems);
                        $payments = null;
                        if($postPayment && $order_id){
                            
                            $payment_date = !empty($postPayment['transaction_datetime']) ? $postPayment['transaction_datetime'] : date('Y-m-d H:i:s');
                            $transaction_response = !empty($postPayment['transaction_response']) ? serialize($postPayment['transaction_response']) : null;
                            $payment_reference_no = $this->site->getReferenceNumber('pay');
                            $payments = array(
                                'order_id'      => $order_id,
                                'date'          => $payment_date,
                                'reference_no'  => $payment_reference_no,
                                'transaction_id'=> $postPayment['transaction_id'],
                                'paid_by'       => $postPayment['payment_mode'],
                                'amount'        => $postPayment['paid_amount'],
                                'type'          => 'received',
                                'pos_paid'      => $postPayment['paid_amount'],
                                'pos_balance'   => $postPayment['balance_amount'],
                                'note'          => $transaction_response,
                            );
                        
                            $payment_id = $this->restapi5_model->add_payment($payments);
                            $payments['payment_id'] = $payment_id;
                        }//end if Payment
                        
                                                
                        if($order_id) {
                            $response['status'] = 'SUCCESS';
                            $response['code'] = REST_Controller::HTTP_OK;
                            $response['msg'] = "Order Submitted";
                            $rdata['response'] = $response;
                            
                            $order['order_id'] = $order_id;
                            $address['address_id'] = $address_id;
                            
                            $rdata['data']['order'] = $order;
                            $rdata['data']['items'] = $orderItems;
                            $rdata['data']['payments'] = $payments;
                            $rdata['data']['address']  = $address;
                            
                            $this->response($rdata, REST_Controller::HTTP_OK);
                        }
                        
                    }//end if valid Items
                    else
                    {
                        $response['status'] = 'ERROR';
                        $response['code'] = REST_Controller::HTTP_NON_AUTHORITATIVE_INFORMATION;
                        $response['msg'] = "Order total_items not match with item list count";
                        $rdata['response'] = $response;
                            
                        $this->response($rdata, REST_Controller::HTTP_OK); 
                    }
                    
                }//end if valid Request Json.
                
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::OK); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }//End else
       
    }//end functio order_post
    
    private function _order_canceled() {
        
       $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
           
            $_POST = $postJson;
            
            $postProducts = $postJson['products'];
                
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            $this->form_validation->set_rules('order_id', 'order_id', 'trim|required|numeric');
            
            if((bool)$postProducts){
                foreach($postProducts as $ind=>$product) 
                {
                    $this->form_validation->set_rules("products[$ind][product_code]", "product_code[$ind]", 'trim|required');
                    $this->form_validation->set_rules("products[$ind][order_item_id]", "order_item_id[$ind]", 'trim|required');
                }
            }
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = $this->input->post('customer_id');                
                $order_id    = $this->input->post('order_id');                
            
                $postProducts = ((bool)$postProducts) ? (array)$postProducts : null;
                
                if($this->restapi5_model->order_canceled($order_id, $postProducts)){
                    $response['status'] = 'SUCCESS';
                    $response['code']   = REST_Controller::HTTP_OK;
                    $rdata['response'] = $response;
                    $this->response($rdata, REST_Controller::HTTP_OK); 
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error']  = "Request has been failed";
                    $rdata['response'] = $response;
                    $this->response($rdata, REST_Controller::HTTP_NOT_IMPLEMENTED); 
                }
                
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response'] = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }//End else
       
    }//end functio order_post
        
    private function _get_customer_orders($customer_id = null, $status = null) {
        
        if((bool)$customer_id) {        
        
            $orders = $this->restapi5_model->get_customer_orders($customer_id, $status);
            
            $response['status'] = "SUCCESS";
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data'] = $orders ? $orders : [];

            $this->response($data, REST_Controller::HTTP_OK);
            
        } else {
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_BAD_REQUEST;
            $response['error'] = 'Customer id is missing';
            $data['response'] = $response;
            $this->response($data, REST_Controller::HTTP_BAD_REQUEST);
        }
        
    }
    
    private function _get_customer_sales() {
           
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $_POST = $postJson;
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = $this->input->post('customer_id');
                $duration  = (bool)$_POST['duration_in_months'] ? $_POST['duration_in_months'] : 6;
                                
                $sales = $this->restapi5_model->get_customer_sales($customer_id, $duration);
            
                $response['status'] = "SUCCESS";
                $response['code'] = REST_Controller::HTTP_OK;
                $data['response'] = $response;
                $data['data'] = $sales ? $sales : [];
                $this->response($data, REST_Controller::HTTP_OK);
            
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }//End else
      
    }
    
    private function _get_customer_allsales($customer_id=null) {
            
        if ((bool)$customer_id)
        {
            $sales = $this->restapi5_model->get_customer_sales($customer_id);
            
            $response['status'] = "SUCCESS";
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data']     = $sales ? $sales : [];
            $this->response($data, REST_Controller::HTTP_OK);
            
        } else {
            
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = "Customer id is missing";
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
        }
        
    }
    
    private function _get_sales_invoice($sale_id=null){
        
        if((bool)$sale_id) {        
        
            $invoice = $this->restapi5_model->get_sales_invoice($sale_id);

            if((bool)$invoice){
                $response['status'] = "SUCCESS";
                $response['code'] = REST_Controller::HTTP_OK;
                $data['response'] = $response;
                $data['data'] = $invoice;
                $this->response($data, REST_Controller::HTTP_OK);
            } else {

                $response['status'] = 'ERROR';
                $response['code'] = REST_Controller::HTTP_NOT_FOUND;
                $response['error'] = 'Invoice not found';
                $data['response'] = $response;
                $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            }        
        } else {
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_BAD_REQUEST;
            $response['error'] = 'Invoice id is missing';
            $data['response'] = $response;
            $this->response($data, REST_Controller::HTTP_BAD_REQUEST);
        }
    }
    
    
    /**************************
     * Shopping Cart Actions
     **************************/
    private function _addtocart() {
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $_POST = $postJson;
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            $this->form_validation->set_rules('product_id', 'product_id', 'trim|required|numeric');
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = $this->input->post('customer_id');            
                $product_id  = $this->input->post('product_id');            
                $variant_id  = (bool)$_POST['variant_id'] ? $_POST['variant_id'] : 0;            
                $quantity    = (bool)$_POST['quantity'] ? $_POST['quantity'] : 1;            
                           
                $cartdata = [
                    "customer_id" => $customer_id,
                    "product_id"  => $product_id,
                    "variant_id"  => $variant_id
                ];
                
                if($exist_quantity = $this->restapi5_model->exist_in_cart($cartdata)){
                    
                    $newQuantity = ($exist_quantity + $quantity);
                    $addtocart = $this->restapi5_model->updatecartquantity($cartdata, $newQuantity);
                    
                } else {
                    
                    $cartdata['quantity'] = $quantity;
                    $addtocart = $this->restapi5_model->addtocart($cartdata);                    
                }

                if((bool)$addtocart){
                    $response['status'] = "SUCCESS";
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error'] = 'Request Failed';
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                } 
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = validation_errors();
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            
        }//End else        
        
    }
    
    private function _updatecartquantity() {
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $this->form_validation->set_data($postJson);
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            $this->form_validation->set_rules('product_id', 'product_id', 'trim|required|numeric');
            $this->form_validation->set_rules('quantity', 'quantity', 'trim|required|numeric');
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id = $this->put('customer_id');            
                $product_id  = $this->put('product_id');            
                $quantity    = $this->put('quantity');            
                $variant_id  = (bool)$postJson['variant_id'] ? $this->put('variant_id') : 0; 
                           
                $cartdata = [
                    "customer_id" => $customer_id,
                    "product_id"  => $product_id,
                    "variant_id"  => $variant_id
                ];
            
                if($exist_quantity = $this->restapi5_model->exist_in_cart($cartdata)){
                   
                    $addtocart = $this->restapi5_model->updatecartquantity($cartdata, $quantity);
            
                    if((bool)$addtocart){
//                        $response['status'] = "SUCCESS";
//                        $response['code'] = REST_Controller::HTTP_OK;
//                        $data['response'] = $response;
//                        $this->response($data, REST_Controller::HTTP_OK);
                          $this->_mobile_cartscreen($customer_id);
                    } else {
                        $response['status'] = 'ERROR';
                        $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                        $response['error'] = 'Request Failed';
                        $data['response'] = $response;
                        $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                    }
                
                } else {
                    
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_FOUND;
                    $response['error']  = "Requested item not found";
                    $rdata['response']  = $response;
                    $this->response($rdata, REST_Controller::HTTP_NOT_FOUND); 
                }
                 
            } else {
                $validation_error = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', validation_errors());
                $validation_error = str_replace(['<p>','</p>','\n'], '', $validation_error);
                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = $validation_error;
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            
        }//End else        
        
    }
        
    private function _cart_items($customer_id=null, $save_for_later = 0) {        
        
        if((bool)$customer_id) {        
        
            $cart_items = $this->restapi5_model->get_cart_items($customer_id, $save_for_later);
            
            $response['status'] = "SUCCESS";
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data'] = $cart_items ? $cart_items : [];
            $this->response($data, REST_Controller::HTTP_OK);
            
        } else {
            $response['status'] = 'ERROR';
            $response['code'] = REST_Controller::HTTP_BAD_REQUEST;
            $response['error'] = 'Customer id is missing';
            $data['response'] = $response;
            $this->response($data, REST_Controller::HTTP_BAD_REQUEST);
        }
        
    }
    
    private function _cart_items_delete($customer_id = null, $cart_item_id = null, $save_for_later = 0) {
        
         if((bool)$customer_id) {        
        
            $cart_items_delete = $this->restapi5_model->cart_items_delete( $customer_id , $cart_item_id, $save_for_later );

            if((bool)$cart_items_delete){
//                $response['status'] = "SUCCESS";
//                $response['code']   = REST_Controller::HTTP_OK;
//                $data['response']   = $response;
//                $this->response($data, REST_Controller::HTTP_OK);
                  $this->_mobile_cartscreen($customer_id);
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                $response['error']  = 'Request Failed';
                $data['response']   = $response;
                $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
            }        
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Customer id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
        
    }
            
    private function _move_to_saveforlater($customer_id = null, $cart_item_id = null) {
        
         if((bool)$cart_item_id) {        
        
            $add_saveforlater = $this->restapi5_model->add_saveforlater( $customer_id , $cart_item_id );

            if((bool)$add_saveforlater){
//                $response['status'] = "SUCCESS";
//                $response['code']   = REST_Controller::HTTP_OK;
//                $data['response']   = $response;
//                $this->response($data, REST_Controller::HTTP_OK);
                $this->_mobile_cartscreen($customer_id);
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                $response['error']  = 'Request Failed';
                $data['response']   = $response;
                $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
            }        
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Item id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
        
    }
    
    private function _move_to_cart($customer_id = null, $cart_item_id = null) {
        
        if((bool)$cart_item_id) {        
        
            $movetocart = $this->restapi5_model->move_to_cart( $customer_id , $cart_item_id );

            if((bool)$movetocart){
//                $response['status'] = "SUCCESS";
//                $response['code']   = REST_Controller::HTTP_OK;
//                $data['response']   = $response;
//                $this->response($data, REST_Controller::HTTP_OK);
                $this->_mobile_cartscreen($customer_id);
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                $response['error']  = 'Request Failed';
                $data['response']   = $response;
                $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
            }        
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Item id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
        
    }
    
    
    /*************************
     * Customer Feedbacks
     *************************/
    private function _get_product_reviews($product_id = null, $limit = null) {
    
        if($product_id){
            $reviews = $this->restapi5_model->get_product_reviews($product_id, $limit); 
            $response['status'] = 'SUCCESS';
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data']     = $reviews ? $reviews : [];
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Product id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
    }
        
    private function _add_rating() {
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $_POST = $postJson;
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            $this->form_validation->set_rules('customer_name', 'customer_name', 'trim|required');
            $this->form_validation->set_rules('product_id', 'product_id', 'trim|required|numeric');
            $this->form_validation->set_rules('product_name', 'product_name', 'trim|required');            
            $this->form_validation->set_rules('rating', 'rating', 'trim|required|numeric');
            
            if((bool)$postJson['variant_id']){
                $this->form_validation->set_rules('variant_id', 'variant_id', 'trim|required|numeric');
                $this->form_validation->set_rules('variant_name', 'variant_name', 'trim|required');
            }
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id    = $this->input->post('customer_id');            
                $customer_name  = $this->input->post('customer_name');            
                $product_id     = $this->input->post('product_id');            
                $product_name   = $this->input->post('product_name');            
                $variant_id     = (bool)$_POST['variant_id'] ? $_POST['variant_id'] : 0;            
                $variant_name   = (bool)$_POST['variant_name'] ? $_POST['variant_name'] : null;            
                $rating         = $_POST['rating'];            
                                 
                $ratingWhere = [
                    "customer_id" => $customer_id,                    
                    "product_id"  => $product_id,                    
                    "variant_id"  => $variant_id,
                ];
                
                if($feedback_id = $this->restapi5_model->exist_feedback($ratingWhere)){
                    
                    $updateRating = [
                        "reviews_ratings"   => $rating,
                        "updated_at"        => date('Y-m-d H:i:s'),
                    ];
                    
                    $resultFeedback = $this->restapi5_model->update_feedback($feedback_id, $updateRating);
                    
                } else {
                    
                    $insertRating = [
                        "customer_id"       => $customer_id,
                        "customer_name"     => $customer_name,
                        "product_id"        => $product_id,
                        "product_name"      => $product_name,
                        "variant_id"        => $variant_id,
                        "variant_name"      => $variant_name,
                        "reviews_ratings"  => $rating,
                        "reviews_date"      => date('Y-m-d H:i:s'),
                        "updated_at"        => date('Y-m-d H:i:s'),
                    ];
                    
                    $resultFeedback = $this->restapi5_model->add_feedback($insertRating);                    
                }

                if((bool)$resultFeedback){
                    $response['status'] = "SUCCESS";
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error'] = 'Request Failed';
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                } 
            } else {
                $validation_error = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', validation_errors());
                $validation_error = str_replace(['<p>','</p>','\n'], '', $validation_error);
                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = $validation_error;
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            
        }//End else        
        
    }
    
    private function _update_rating() {
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $this->form_validation->set_data($postJson);
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');           
            $this->form_validation->set_rules('review_id', 'review_id', 'trim|required|numeric');        
            $this->form_validation->set_rules('rating', 'rating', 'trim|required|numeric');
                        
            if ($this->form_validation->run() !== FALSE)
            {
                $feedback_id    = $this->put('review_id');
                $rating        = $this->put('rating');            
                                    
                $updateData = [
                    "reviews_ratings"  => $rating,
                    "updated_at"  => date('Y-m-d H:i:s'),
                ];

                $resultFeedback = $this->restapi5_model->update_feedback($feedback_id, $updateData);            

                if((bool)$resultFeedback){
                    $response['status'] = "SUCCESS";
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error'] = 'Request Failed';
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                } 
            } else {
                $validation_error = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', validation_errors());
                $validation_error = str_replace(['<p>','</p>','\n'], '', $validation_error);
                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = $validation_error;
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            
        }//End else        
        
    }
   
    private function _get_customer_feedback($customer_id = null, $product_id = null){
        
        if((bool)$customer_id){
            $feedbacks = $this->restapi5_model->get_customer_feedback($customer_id, $product_id); 
            $response['status'] = 'SUCCESS';
            $response['code'] = REST_Controller::HTTP_OK;
            $data['response'] = $response;
            $data['data']     = $feedbacks ? $feedbacks : [];
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Customer id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }
    }
    
    private function _add_feedback() {
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $_POST = $postJson;
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');
            $this->form_validation->set_rules('customer_name', 'customer_name', 'trim|required');
            $this->form_validation->set_rules('product_id', 'product_id', 'trim|required|numeric');
            $this->form_validation->set_rules('product_name', 'product_name', 'trim|required');            
            $this->form_validation->set_rules('rating', 'rating', 'trim|required|numeric');
            $this->form_validation->set_rules('reviews_title', 'reviews_title', 'trim|required');
            $this->form_validation->set_rules('reviews_details', 'reviews_details', 'trim|required');
            
            if((bool)$postJson['variant_id']){
                $this->form_validation->set_rules('variant_id', 'variant_id', 'trim|required|numeric');
                $this->form_validation->set_rules('variant_name', 'variant_name', 'trim|required');
            }
            
            if ($this->form_validation->run() !== FALSE)
            {
                $customer_id    = $this->input->post('customer_id');            
                $customer_name  = $this->input->post('customer_name');            
                $product_id     = $this->input->post('product_id');            
                $product_name   = $this->input->post('product_name');            
                $variant_id     = (bool)$_POST['variant_id'] ? $_POST['variant_id'] : 0;            
                $variant_name   = (bool)$_POST['variant_name'] ? $_POST['variant_name'] : null;            
                $rating            = $_POST['rating'];            
                $reviews_title      = $_POST['reviews_title'];            
                $reviews_details    = $_POST['reviews_details'];            
                                 
                $existsWhere = [
                    "customer_id" => $customer_id,                    
                    "product_id"  => $product_id,                    
                    "variant_id"  => $variant_id,
                ];
                
                if($feedback_id = $this->restapi5_model->exist_feedback($existsWhere)){
                    
                    $updateData = [
                        "reviews_ratings"  => $rating,
                        "reviews_title"     => $reviews_title,
                        "reviews_details"   => $reviews_details,
                        "updated_at"        => date('Y-m-d H:i:s'),
                    ];
                    
                    $resultFeedback = $this->restapi5_model->update_feedback($feedback_id, $updateData);
                    
                } else {
                    
                    $insertData = [
                        "customer_id"       => $customer_id,
                        "customer_name"     => $customer_name,
                        "product_id"        => $product_id,
                        "product_name"      => $product_name,
                        "variant_id"        => $variant_id,
                        "variant_name"      => $variant_name,
                        "reviews_ratings"  => $rating,
                        "reviews_title"     => $reviews_title,
                        "reviews_details"   => $reviews_details,
                        "reviews_date"      => date('Y-m-d H:i:s'),
                        "updated_at"        => date('Y-m-d H:i:s'),
                    ];
                    
                    $resultFeedback = $this->restapi5_model->add_feedback($insertData);                    
                }

                if((bool)$resultFeedback){
                    $response['status'] = "SUCCESS";
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error'] = 'Request Failed';
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                } 
            } else {
                $validation_error = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', validation_errors());
                $validation_error = str_replace(['<p>','</p>','\n'], '', $validation_error);
                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = $validation_error;
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            
        }//End else        
        
    }
    
    private function _update_feedback() {
        
        $postJson = $this->_get_api_request_body_data();

        if(is_array($postJson)){
            
            $this->form_validation->set_data($postJson);
            
            $this->form_validation->set_rules('customer_id', 'customer_id', 'trim|required|numeric');           
            $this->form_validation->set_rules('review_id', 'review_id', 'trim|required|numeric');        
            $this->form_validation->set_rules('rating', 'rating', 'trim|required|numeric');
            $this->form_validation->set_rules('reviews_title', 'reviews_title', 'trim|required');
            $this->form_validation->set_rules('reviews_details', 'reviews_details', 'trim|required');
                        
            if ($this->form_validation->run() !== FALSE)
            {
                $feedback_id     = $this->put('review_id');
                $rating          = $this->put('rating');            
                $reviews_title   = $this->put('reviews_title');            
                $reviews_details = $this->put('reviews_details');            
                                    
                $updateData = [
                    "reviews_ratings"  => $rating,
                    "reviews_title"     => $reviews_title,
                    "reviews_details"   => $reviews_details,
                    "updated_at"        => date('Y-m-d H:i:s'),
                ];

                $resultFeedback = $this->restapi5_model->update_feedback($feedback_id, $updateData);            

                if((bool)$resultFeedback){
                    $response['status'] = "SUCCESS";
                    $response['code'] = REST_Controller::HTTP_OK;
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code'] = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error'] = 'Request Failed';
                    $data['response'] = $response;
                    $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                } 
            } else {
                $validation_error = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', validation_errors());
                $validation_error = str_replace(['<p>','</p>','\n'], '', $validation_error);
                
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_ACCEPTABLE;
                $response['error']  = $validation_error;
                $rdata['response']  = $response;
                $this->response($rdata, REST_Controller::HTTP_NOT_ACCEPTABLE); 
            }
            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_BAD_REQUEST;
            $response['error']  = 'Invalid Json Request';
            $rdata['response']  = $response;
            $this->response($rdata, REST_Controller::HTTP_BAD_REQUEST); 
            
        }//End else        
        
    }
       
    private function _delete_feedback($customer_id = null, $feedback_id=null) {
        
        if((bool)$customer_id){            
            if((bool)$feedback_id){
                
                if($this->restapi5_model->delete_customer_feedback($customer_id, $feedback_id)){ 
                    $response['status'] = 'SUCCESS';
                    $response['code']   = REST_Controller::HTTP_OK;
                    $data['response']   = $response;
                    $this->response($data, REST_Controller::HTTP_OK);
                } else {
                    $response['status'] = 'ERROR';
                    $response['code']   = REST_Controller::HTTP_NOT_IMPLEMENTED;
                    $response['error']  = 'Request Failed';
                    $data['response']   = $response;
                    $this->response($data, REST_Controller::HTTP_NOT_IMPLEMENTED);
                }
                
            } else {
                $response['status'] = 'ERROR';
                $response['code']   = REST_Controller::HTTP_NOT_FOUND;
                $response['error']  = 'Feedback id is missing';
                $data['response']   = $response;
                $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            }            
        } else {
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Customer id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
        }        
    }
    
    
    
    /*
     * Mobile App APIs Screen wise Data
     */
    private function _mobile_homescreen( $customer_id = null ) {
        
        $categoriesData = [];
            
        $top_rattings_products = null;
        $top_sellings_products = null;
        $promotion_products = null;
        $customer_recent_views = null;
        
        $response['status'] = 'SUCCESS';
        $response['code']   = REST_Controller::HTTP_OK;
        $data['response']   = $response;  
         
        /*******************************
         * Store Settings
         *******************************/
        $data['data']['store_settings'] = $this->store_settings;
        
        /*******************************
         * Products Categories
         *******************************/
        $categories = $this->restapi5_model->get_categories();
        
        if(count($categories)){
            $sub_categories = $this->restapi5_model->get_subcategories();
            foreach ($categories as $key => $category) {
                $category->subcategory = (isset($sub_categories[$category->id])) ? $sub_categories[$category->id] : [];
                $categoriesData[] = $category;
            }//end foreach.
        }//end if.        
       
        
        $data['data']['categories'] = $categoriesData;
             
        /*******************************
         * Products Brands 
         *******************************/
        $brands  = $this->restapi5_model->get_brands(); 
        $data['data']['brands'] = $brands;
        
        /*******************************
         * Top Selling Products
         *******************************/
        $top_sellings_filters = ['top_sellings'=>true, 'count_request'=>false, 'customer_id'=>$customer_id];
        $top_sellings_products = $this->restapi5_model->get_catlog_products(null, $top_sellings_filters, $this->per_page_limit); 
            
        $data['data']['top_sellings_products'] = $top_sellings_products['data'];
        
        /****************************
         * Top Rating Products
         ****************************/
        $top_rattings_filters = ['top_rattings'=>true, 'count_request'=>false, 'customer_id'=>$customer_id];
        $top_rattings_products = $this->restapi5_model->get_catlog_products(null, $top_rattings_filters, $this->per_page_limit); 
         
        $data['data']['top_rattings'] = $top_rattings_products['data'];               
        
        /*********************************
         * Promotional Products
         *********************************/
        $promotional_filters = ['is_promotion'=>true, 'count_request'=>false, 'customer_id'=>$customer_id];
        $promotional_products = $this->restapi5_model->get_catlog_products(null, $promotional_filters, $this->per_page_limit); 
         
        $data['data']['promotional_products'] = $promotional_products['data'];
        
        
        /*********************************
         * Featured Products 
         *********************************/
        $featured_filters = ['is_featured'=>true, 'count_request'=>false, 'customer_id'=>$customer_id];
        $featured_products = $this->restapi5_model->get_catlog_products(null, $featured_filters, $this->per_page_limit); 
         
        $data['data']['featured_products'] = $featured_products['data'];
        
        if($customer_id) {
            /**************************************
             * Customer Recent View Products
             **************************************/
            $recently_views = $this->restapi5_model->get_customer_views_products( $customer_id );
            $data['data']['recently_views'] = $recently_views;
            
            /**************************************
             * Customer Shopping Cart Products
             **************************************/
           /* $shopping_cart = $this->restapi5_model->get_cart_items($customer_id, false);
            $data['data']['shopping_cart']['count'] = count($shopping_cart);
            $data['data']['shopping_cart']['items'] = $shopping_cart;
            */
            $cart_count = $this->restapi5_model->get_customer_cart_count($customer_id);
            $data['data']['shopping_cart']['count'] = $cart_count;
                    
                    
            /**************************************
             * Customer Wishlist Products
             **************************************/
          /*  $wishlist = $this->restapi5_model->get_customer_wishlist($customer_id);            
            $data['data']['wishlist']['count'] = $wishlist['count'];
            $data['data']['wishlist']['items'] = $wishlist['result'];
            */
            $wishlist_count = $this->restapi5_model->get_customer_wishlist_count($customer_id);            
            $data['data']['wishlist']['count'] = $wishlist_count;
            
        }        
        
       
        $this->response($data, REST_Controller::HTTP_OK);
        
    }
        
    private function _mobile_categoryproducts( $category_id = null, $subcategory_id = null, $customer_id = null ) {
        
        if(!(bool)$category_id) {            
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Category id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            
        } else {            
            
            $response['status'] = 'SUCCESS';
            $response['code']   = REST_Controller::HTTP_OK;
            $data['response']   = $response;  

            /*******************************
             * Store Settings
             *******************************/
            //$data['data']['store_settings'] = $this->store_settings;

            /*******************************
             * Products Categories
             *******************************/
            $sub_categories = $this->restapi5_model->get_subcategories($category_id);

            $data['data']['subcategories'] = $sub_categories;


            /*******************************
             * Category Products
             *******************************/
            $category_filters = ['category_id'=>$category_id, 'subcategory_id'=>$subcategory_id, 'customer_id'=>$customer_id];
            $category_products = $this->restapi5_model->get_catlog_products(null, $category_filters, $this->per_page_limit); 

            $data['data']['category_products'] = $category_products['data'];

            if($customer_id) {
                /**************************************
                 * Customer Recent View Products
                 **************************************/
                $recently_views = $this->restapi5_model->get_customer_views_products( $customer_id );
                $data['data']['recently_views'] = $recently_views;

                /**************************************
                * Customer Shopping Cart Products
                **************************************/
              /* $shopping_cart = $this->restapi5_model->get_cart_items($customer_id, false);
               $data['data']['shopping_cart']['count'] = count($shopping_cart);
               $data['data']['shopping_cart']['items'] = $shopping_cart;
               */
               $cart_count = $this->restapi5_model->get_customer_cart_count($customer_id);
               $data['data']['shopping_cart']['count'] = $cart_count;


               /**************************************
                * Customer Wishlist Products
                **************************************/
             /*  $wishlist = $this->restapi5_model->get_customer_wishlist($customer_id);            
               $data['data']['wishlist']['count'] = $wishlist['count'];
               $data['data']['wishlist']['items'] = $wishlist['result'];
               */
               $wishlist_count = $this->restapi5_model->get_customer_wishlist_count($customer_id);            
               $data['data']['wishlist']['count'] = $wishlist_count;
               
            } 

            $this->response($data, REST_Controller::HTTP_OK);
        }
    }
    
    private function _mobile_brandproducts( $brand_id = null, $customer_id = null ) {
        
        if(!(bool)$brand_id) {            
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Brand id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            
        } else {             

            $response['status'] = 'SUCCESS';
            $response['code']   = REST_Controller::HTTP_OK;
            $data['response']   = $response;  

            /*******************************
             * Store Settings
             *******************************/
            //$data['data']['store_settings'] = $this->store_settings;

            /*******************************
             * Products Brands 
             *******************************/
            $brands  = $this->restapi5_model->get_brands(); 
            $data['data']['brands'] = $brands;


            /*******************************
             * Brand Products
             *******************************/
            $brand_filters = ['brand_id'=>$brand_id, 'customer_id'=>$customer_id];
            $brand_products = $this->restapi5_model->get_catlog_products(null, $brand_filters, $this->per_page_limit); 

            $data['data']['brand_products'] = $brand_products['data'];

            if($customer_id) {
                /**************************************
                 * Customer Recent View Products
                 **************************************/
                $recently_views = $this->restapi5_model->get_customer_views_products( $customer_id );
                $data['data']['recently_views'] = $recently_views;

                /**************************************
                * Customer Shopping Cart Products
                **************************************/
              /* $shopping_cart = $this->restapi5_model->get_cart_items($customer_id, false);
               $data['data']['shopping_cart']['count'] = count($shopping_cart);
               $data['data']['shopping_cart']['items'] = $shopping_cart;
               */
               $cart_count = $this->restapi5_model->get_customer_cart_count($customer_id);
               $data['data']['shopping_cart']['count'] = $cart_count;


               /**************************************
                * Customer Wishlist Products
                **************************************/
             /*  $wishlist = $this->restapi5_model->get_customer_wishlist($customer_id);            
               $data['data']['wishlist']['count'] = $wishlist['count'];
               $data['data']['wishlist']['items'] = $wishlist['result'];
               */
               $wishlist_count = $this->restapi5_model->get_customer_wishlist_count($customer_id);            
               $data['data']['wishlist']['count'] = $wishlist_count;
            }

            $this->response($data, REST_Controller::HTTP_OK);
        }
    }
    
    private function _mobile_productdetails( $product_id = null, $customer_id = null ) {
               
        if(!(bool)$product_id) {            
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Product id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            
        } else {
            
            $response['status'] = 'SUCCESS';
            $response['code']   = REST_Controller::HTTP_OK;
            $data['response']   = $response;  

            /*******************************
             * Store Settings
             *******************************/
            //$data['data']['store_settings'] = $this->store_settings;
            
            $filters = ['customer_id'=>$customer_id];
            $product_details = $this->restapi5_model->get_catlog_products($product_id, $filters); 

            $data['data']['product_details'] = $product_details['data'];
            
            $product_reviews = $this->restapi5_model->get_product_reviews($product_id);
            
            $data['data']['all_reviews'] = $product_reviews;
            
            if($customer_id) {
                 
                /**************************************
                 * Add Customer Recent View Products
                 **************************************/ 
                $cvproduct['user_id']    = $customer_id;
                $cvproduct['product_id'] = $product_id;
                $data['data']['update_recently_views'] = $this->restapi5_model->add_customer_views_products($cvproduct);
                 
                /**************************************
                 * Get Customer Recent View Products
                 **************************************/
                $recently_views = $this->restapi5_model->get_customer_views_products( $customer_id );
                $data['data']['recently_views'] = $recently_views;

                /**************************************
                * Customer Shopping Cart Products
                **************************************/
              /* $shopping_cart = $this->restapi5_model->get_cart_items($customer_id, false);
               $data['data']['shopping_cart']['count'] = count($shopping_cart);
               $data['data']['shopping_cart']['items'] = $shopping_cart;
               */
               $cart_count = $this->restapi5_model->get_customer_cart_count($customer_id);
               $data['data']['shopping_cart']['count'] = $cart_count;


               /**************************************
                * Customer Wishlist Products
                **************************************/
             /*  $wishlist = $this->restapi5_model->get_customer_wishlist($customer_id);            
               $data['data']['wishlist']['count'] = $wishlist['count'];
               $data['data']['wishlist']['items'] = $wishlist['result'];
               */
               $wishlist_count = $this->restapi5_model->get_customer_wishlist_count($customer_id);            
               $data['data']['wishlist']['count'] = $wishlist_count;
            }   
            
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }
    
    private function _mobile_cartscreen($customer_id = null) {
        
        if(!(bool)$customer_id) { 
            
            $response['status'] = 'ERROR';
            $response['code']   = REST_Controller::HTTP_NOT_FOUND;
            $response['error']  = 'Customer id is missing';
            $data['response']   = $response;
            $this->response($data, REST_Controller::HTTP_NOT_FOUND);
            
        } else {
            
            $response['status'] = 'SUCCESS';
            $response['code']   = REST_Controller::HTTP_OK;
            $data['response']   = $response;  
            
            $cartdata = $this->restapi5_model->get_customer_cart_items($customer_id, 0);
            $save_for_later = $this->restapi5_model->get_cart_items($customer_id, 1);
            
            $data['data']['cart'] = (bool)$cartdata ? $cartdata['cart'] : (object)[];
            $data['data']['save_for_later'] = (bool)$save_for_later ? $save_for_later : [];
            
            $data['data']['payment_methods']   = $this->restapi5_model->get_payment_methods();
            $data['data']['shipping_methods']  = $this->restapi5_model->get_shipping_methods();
            $data['data']['delivery_pincodes'] = $this->restapi5_model->get_shipping_pincodes();
            $data['data']['delivery_address']  = $this->restapi5_model->get_customer_addresses($customer_id , null, 1);
                    
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }
    
    private function _get_payment_shipping_methods() {
        
        $paymethods  = $this->restapi5_model->get_payment_methods();
        $shipmethods = $this->restapi5_model->get_shipping_methods();
        $shippincodes = $this->restapi5_model->get_shipping_pincodes();
            
        $response['status'] = "SUCCESS";
        $response['code'] = REST_Controller::HTTP_OK;            
        $data['response'] = $response;
        $data['data']['payment_methods']   = $paymethods;
        $data['data']['shipping_methods']  = $shipmethods;
        $data['data']['delivery_pincodes'] = $shippincodes;

        $this->response($data, REST_Controller::HTTP_OK);
        
    }
    
    private function _get_state_list( $country_id = 1 ) {
        
        $states  = $this->restapi5_model->get_state_list( $country_id );
            
        $response['status'] = "SUCCESS";
        $response['code']   = REST_Controller::HTTP_OK;            
        $data['response']   = $response;
        $data['data']       = $states;

        $this->response( $data, REST_Controller::HTTP_OK );
        
    }
    
            
        
        
    
    
    
}//End Class.

?>