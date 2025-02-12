<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller {

    private $APIKEY     = "435DSFSDFDSF743500909809DFSFJKJ234324534";
    private $SMSAPIKEY  = "435DS87945235464713213SFJKJ29345321";
    
    /*---------------- update 12-09-17-------------------------*/
        private $merchant_phone = '';
        private $smsUser        = '';
        private $smsPassword    = '';
        private $smsSID         = '';
        private $smsAPI         = '';
    /*---------------- update 12-09-17-------------------------*/
    
    
    public function __construct() {
        parent::__construct();
       
        $this->load->model('auth_model'); 
        $this->load->model('companies_model');
         $this->load->model('pos_model');
        $this->load->model('eshop_model');
        $this->load->library('form_validation');
        $ci = get_instance();
        $config = $ci->config;
        $this->merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone'])?$config->config['merchant_phone']:null;
        $this->smsUser = isset($config->config['smsUser']) && !empty($config->config['smsUser'])?$config->config['smsUser']:null;
        $this->smsPassword = isset($config->config['smsPassword']) && !empty($config->config['smsPassword'])?$config->config['smsPassword']:null;
        $this->smsSID = isset($config->config['smsSID']) && !empty($config->config['smsSID'])?$config->config['smsSID']:null;
        $this->smsAPI = isset($config->config['smsAPI']) && !empty($config->config['smsAPI'])?$config->config['smsAPI']:null;
    }
       
    public function random_password($length = 8) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $password = substr(str_shuffle($chars), 0, $length);
        return $password;
    }

    public function random_auth_token($length = 25) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $password = substr(str_shuffle($chars), 0, $length);
        return $password;
    }

    public function random_otp($length = 6) {
        $chars = "0123456789";
        $password = substr(str_shuffle($chars), 0, $length);
        return $password;
    }

    public function v2() {
        $api_key = $this->input->post('api_key');
        //--------------------- Validate API KEY ---------------------------------// 
        if ($api_key != $this->APIKEY) {
            $arr['error'] = $this->ErrorMsg('E001');
            return $this->json_op($arr);
        }
        $api_act = $this->input->post('action');
        if (empty($api_act)) {
            $arr['error'] = $this->ErrorMsg('E003');
            return $this->json_op($arr);
        }
        switch ($api_act) {
            case 'getpasskey':
                $this->getpasskey();
                break;
            case 'CreateRequest':
                $this->CreateRequest();
                break;
            default:
                $arr['error'] = $this->ErrorMsg('E003');
                return $this->json_op($arr);
                break;
        }
        exit;
    }

    private function ErrorMsg($key) {
        $arr = array();

        //----------------- Common ---------------------//
        $arr['E001'] = "Invalid Api Key";
        $arr['E002'] = "required parmeter  are  not  send";
        $arr['E003'] = "Unable to call api action";

        //----------------- passkey ---------------------//
        $arr['E0022'] = "Unable to notify";
        $arr['E0023'] = "Unable to update POSkey";
        $arr['E0024'] = "Unable to create POSkey";

        return $arr[$key];
    }

    private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }

    public function CreateRequest() {
        //-------------- Collecting Post value------------------//
        $merchant_phone = $this->input->post('merchant_phone');
        $customer_name = $this->input->post('customer_name');
        $customer_mobile = $this->input->post('customer_mobile');
        $comment = $this->input->post('comment');
        $arr = array();
        $pass_key = md5($this->random_password(8));
        $password_raw = $this->random_password(6);
        $password = md5($password_raw);

        if (!empty($merchant_phone) && !empty($customer_name) && !empty($customer_mobile) && !empty($comment)):
        
        $checkDup = $this->companies_model->checkApiNotify(array('customer_code' => $customer_mobile));
            if($checkDup==true):
                $arr['error'] = 'Not inserted successfully';
                return $this->json_op($arr);
            endif;
            //-------------- Set Post value in  array ------------------//   
            $mer_array = array('merchant_code' => $merchant_phone, 'customer_code' => $customer_mobile, 'customer_name' => $customer_name, 'comment' => $comment);
           // var_dump($mer_array);exit;
            $NotifyID = $this->companies_model->addApiNotify($mer_array);
            if ((int) $NotifyID > 0):
                $arr['success'] = 'Inserted successfully';
                return $this->json_op($arr);
            else:
                $arr['error'] = 'Not inserted successfully';
                return $this->json_op($arr);
            endif;
        endif;

        $arr['error'] = $this->ErrorMsg('E002');
        return $this->json_op($arr);
    }

    public function getpasskey() {
        //-------------- Collecting Post value------------------//
        $merchant_phone = $this->input->post('merchant_phone');
        $customer_name = $this->input->post('customer_name');
        $customer_mobile = $this->input->post('customer_mobile');
        $comment = $this->input->post('comment');
        $arr = array();
        $pass_key = md5($this->random_password(8));
        $password_raw = $this->random_password(6);
        $password = md5($password_raw);
 

        if (!empty($merchant_phone) && !empty($customer_name) && !empty($customer_mobile) && !empty($comment)):
            //-------------- Set Post value in  array ------------------//   
            $NotifyID = 1;
            if ((int) $NotifyID > 0):
                $CustomerID = $this->companies_model->getCompanyCustomer(array('group_name' => 'customer', 'phone' => $customer_mobile));
                if ((int) $CustomerID->id > 0) ://update
                    if ($RES = $this->companies_model->updateCompany($CustomerID->id, array('pass_key' => $pass_key))):
                        $arr['success'] = 'POS KEY UPDATED';
                        $arr['pos_key'] = $pass_key;
                        return $this->json_op($arr);
                    endif;
                    $arr['error'] = $this->ErrorMsg('E0023');

                    return $this->json_op($arr);
                else: //Insert 

                    if ($this->companies_model->addCompany(array('name' => $customer_name, 'phone' => $customer_mobile, 'pass_key' => $pass_key, 'group_name' => 'customer', 'group_id' => 3, 'password' => $password))):

                        $res = $this->sendPassword($customer_name, $password_raw, $customer_mobile); //
                        $arr['success'] = 'POS KEY generated';
                        $arr['pos_key'] = $pass_key;
                        $arr['sms_res'] = $res;
                        return $this->json_op($arr);
                    endif;
                    $arr['error'] = $this->ErrorMsg('E0024');
                    return $this->json_op($arr);
                endif;
            else:
                $arr['error'] = $this->ErrorMsg('E0022');
                return $this->json_op($arr);
            endif;
        endif;

        $arr['error'] = $this->ErrorMsg('E002');
        return $this->json_op($arr);
    }

    public function sendPassword($name, $pass, $no) {
        $url = base_url() . '/shop/';
        $pass_str = 'your username:' . $no . ' password is : ' . $pass . ' ' . $url;
        $datasms = array(
            "user" => $this->smsUser,
            "password" => $this->smsPassword,
            "msisdn" => "+91" . $no,
            "sid" =>  $this->smsSID,
            "msg" => "Dear Customer, $pass_str Thanks and regards.",
            "fl" => 0,
            "gwid" => 2
        );
        $surlsms =  $this->smsAPI;//'http://payonlinerecharge.com/vendorsms/pushsms.aspx';
        $this->post_to_url($surlsms, $datasms);
    }

    public function post_to_url($url, $data) {
        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= $key . '=' . $value . '&';
        }
        rtrim($fields, '&');
        $post = curl_init();
        curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_POST, count($data));
        curl_setopt($post, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($post);

        curl_close($post);
        return $result;
    }

    public function catlog() {
        $this->load->model('products_model');
        $action = $this->input->get('action');
        $action = isset($action) && !empty($action) ? $action : NULL;
        $MsgArr = array();
        switch ($action) {
            //var_dump($this->product_model);
            case 'allProducts':
                $keyword = $this->input->get('keyword');
                $category_id = $this->input->get('category_id');
                $subcategory_id = $this->input->get('subcategory_id');
                $offset = $this->input->get('offset');
                $limit = $this->input->get('limit');
                $param = array('keyword' => $keyword, 'offset' => $offset, 'limit' => $limit, 'category_id' => $category_id, 'subcategory_id' => $subcategory_id);
                $MsgArr['status'] = "ERROR";
                              
                $res = $this->products_model->getAllProduct($param);
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $total_product_count = $this->products_model->products_count_eshop($keyword,$category_id, $subcategory_id);
                    $MsgArr['total_product_count'] = $total_product_count;
                    $MsgArr['count'] = count($res);
                    foreach ($res as $resData) {
                        $MsgArr[] = $resData;
                    }
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['msg'] = "No records founds";
                
                return $this->json_op($MsgArr);
                break;
                
            case 'getAllProducts':
                $keyword = $this->input->get('keyword');
                $category_id = $this->input->get('category_id');
                $subcategory_id = $this->input->get('subcategory_id');
                $offset = $this->input->get('offset');
                $limit = $this->input->get('limit');
                $param = array('keyword' => $keyword, 'offset' => $offset, 'limit' => $limit, 'category_id' => $category_id, 'subcategory_id' => $subcategory_id);
                $MsgArr['status'] = "ERROR";
                              
                $res = $this->products_model->getAllProduct($param);
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $total_product_count = $this->products_model->products_count_eshop($keyword,$category_id, $subcategory_id);
                    $MsgArr['total_product_count'] = $total_product_count;
                    $MsgArr['count'] = count($res);
                    foreach ($res as $resData) {
                        $MsgArr['items'][] = (object)$resData;
                    }
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['msg'] = "No records founds";
                
                return $this->json_op($MsgArr);
                break;

            case 'allCategories':
                $keyword = $this->input->get('keyword');
                $param = array('keyword' => $keyword);
                 $possettting = $this->pos_model->getSetting();
                $res = $this->products_model->getCategories(NULL, $param);
                $default_cat_id = isset($possettting->default_category) && !empty($possettting->default_category)?$possettting->default_category:0;
             	if($default_cat_id):
                   $default_cat_product_count =  $this->products_model->products_count($default_cat_id);
                    if($default_cat_product_count==0){
                        $default_cat_id =0;
                    }
                endif;
                $MsgArr = array();
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['count'] = count($res);
                    $i =1;
                    foreach ($res as $resData) {
                    
                    	if($resData['parent_id']  > 0):
                    		$prdCount  = $this->products_model->products_count($resData['parent_id'],$resData['id']);
                           	$resData['product_count'] = $prdCount; 
                    	else:
                    	   $prdCount  = $this->products_model->products_count($resData['id']);
                           $resData['product_count'] = $prdCount; 
                           	if($default_cat_id==0  &&  $prdCount > 0):
	                            $default_cat_id = $resData['id'];
	                        endif;
                         endif;
                    
                     //   var_dump( $resData);
                        $MsgArr[] = $resData;
                    }
$MsgArr['default_category'] = $default_cat_id;
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records";
                return $this->json_op($MsgArr);
                break;
            
            case 'getAllCategories':
                $keyword = $this->input->get('keyword');
                $param = array('keyword' => $keyword);
                 $possettting = $this->pos_model->getSetting();
                $res = $this->products_model->getCategories(NULL, $param);
                $default_cat_id = isset($possettting->default_category) && !empty($possettting->default_category)?$possettting->default_category:0;
             	if($default_cat_id):
                   $default_cat_product_count =  $this->products_model->products_count($default_cat_id);
                    if($default_cat_product_count==0){
                        $default_cat_id =0;
                    }
                endif;
                $MsgArr = array();
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['count']  = count($res);
                    $i =1;
                    foreach($res as $key=>$resData) {
                    
                    	if($resData['parent_id']  > 0):
                            $prdCount  = $this->products_model->products_count($resData['parent_id'],$resData['id']);
                            $resData['product_count'] = $prdCount; 
                    	else:
                    	    $prdCount  = $this->products_model->products_count($resData['id']);
                            $resData['product_count'] = $prdCount; 
                            if($default_cat_id == 0 && $prdCount > 0):
                                $default_cat_id = $resData['id'];
                            endif;
                        endif;
            
                        $MsgArr['category'][] =  (object)$resData;
                    }
                    
                $MsgArr['default_category'] = $default_cat_id;
            
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records";
                return $this->json_op($MsgArr);
                break;
                
            case 'allParentCategories':
                $keyword = $this->input->get('keyword');
                $param = array('keyword' => $keyword);
                $res = $this->products_model->getCategories(0, $param);
                $MsgArr = array();
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['count'] = count($res);
                    foreach ($res as $resData) {
                        $MsgArr[] = $resData;
                    }
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records";
                return $this->json_op($MsgArr);
                break;

            case 'allSubcategories':
                $keyword = $this->input->get('keyword');
                $param = array('keyword' => $keyword);
                $MsgArr = array();
                $parent_id = $this->input->get('parent_id');
                $parent_id = isset($parent_id) && !empty($parent_id) ? $parent_id : NULL;

                if ($parent_id === NULL):
                    $MsgArr['error'] = "parent category id is  MENDATORY";
                    return $this->json_op($MsgArr);
                endif;

                $res = $this->products_model->getCategories($parent_id, $keyword);
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['count'] = count($res);
                    foreach ($res as $resData) {
                        $MsgArr[] = $resData;
                    }
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records";
                return $this->json_op($MsgArr);
                break;
            case 'syncOnlineSettings':

                $query = $this->db->get('settings');
                $rows[settings] = $query->result();
                $query = $this->db->get('printer_bill');
                $rows[printer_bill] = $query->result(); 
                $query = $this->db->get('printer_bill_fields');
                $rows[printer_bill_fields] = $query->result();                 
                $response['status'] = 'success';
                $response['rows'] = $rows;
                return $this->json_op($response);
                
            break;
            default:
                break;
        }
    }

    public function store() {
        $action = $this->input->get('action');
        $action = isset($action) && !empty($action) ? $action : NULL;
        $MsgArr = array();
        switch ($action) {
            case 'generalDetails':
                $this->load->model('settings_model');
                $res = $this->eshop_model->getSettings();
                $res2 = $this->eshop_model->getPosSettings();
                 $ci = get_instance();
        	 $config = $ci->config;
	         $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone'])?$config->config['merchant_phone']:null;
                 $res->merchant_phone=$merchant_phone;
                 $res->offline_sale_reff=$this->site->getNextReference('offapp');;
                if (is_object($res) && is_object($res2)):
                    $data = array();
                    foreach ($res as $key => $value) {
                        $data[$key] = $value;
                    }
                    foreach ($res2 as $key2 => $value2) {
                        $data[$key2] = $value2;
                    }
                  
                    $MsgArr['status'] = "SUCCESS";
                    
                    $MsgArr['setting'] = $data;
                    $MsgArr[0] = $data;
                    return $this->json_op($MsgArr);
                endif;

                $MsgArr['error'] = "No Records";
                return $this->json_op($MsgArr);
                break;

            default:
                break;
        }
    }

    public function user() {
        $action = $this->input->get('action');
        switch ($action) {
            case 'auth':
                $this->user_auth();
                break;

            case 'changepassowrd':
                $this->user_update('changepassowrd');
                break;
                
 	    case 'createuser':
                $this->user_update('createuser');
                break;
                
            case 'reset_password_token':
                $this->user_update('reset_password_token');
                break;

            case 'reset_password':
                $this->user_update('reset_password');
                break;

            case 'set_billing_shiiping_info':
                $this->user_update('set_billing_shiiping_info');
                break;

            case 'get_billing_shiiping_info':
                $this->user_update('get_billing_shiiping_info');
                break;

            case 'upload_photo':
                $this->user_update('upload_photo');
                break;
            
            
            case 'get_user':
                $this->user_update('get_user');
                break;

            case 'set_user_info':
                $this->user_update('set_user_info');
                break;
            
            case 'update_user_info':
                $this->user_update('update_user_info');
                break;    
             
            case 'sync_custmer_count':
                $this->user_update('sync_custmer_count');
                break;
            
            case 'sync_custmer':
                $this->user_update('sync_custmer');
                break;         
                
            default:

                break;
        }
    }
    
    public function getuserinfo() {
        
        $user_phone = $this->input->post('phone');
        $source = $this->input->post('source');
            
        $userdata = $this->auth_model->getuserinfobyphone($user_phone);
            
        $data['status'] = "ERROR";
        
        if($userdata){ 
            
            if($userdata->offline_mobile_app_access != 1 && $source == "mobileOfflineApp"){
                $data['error'] = "Offline mobile app access is blocked";
            }
            elseif($userdata->offline_windows_app_access != 1 && $source == "windowsOfflineApp"){
                $data['error'] = "Offline windows app access is blocked";
            }
            elseif($userdata->active != 1){
                $data['error'] = "User status is inactive";
            }
            else{
                $data['status'] = "SUCCESS";
                $data['info']   = $userdata;
                $data['biller'] = ($userdata->biller_id > 0) ? $this->companies_model->getBillerByID($userdata->biller_id) : $this->companies_model->getOfflineDefaultBiller();
            }                
        }
            
            
        return $this->json_op($data);
          
    }
    
    public function blockOfflinePosAccess(){
        
        $user_phone = $this->input->post('phone');
        $source = $this->input->post('source');
        
        if( $this->auth_model->updatePosAccessStatus($user_phone,$source,$status=0)){
            
            $data['status'] = "SUCCESS";
        } else {
            $data['status'] = "ERROR";
        }
        return $this->json_op($data);
    }

    private function user_auth() {
        $action = $this->input->post('passkey');
        $token = $this->random_auth_token();
        $action = isset($action) && !empty($action) ? 'passkey' : 'password';
        $MsgArr = array();
        switch ($action) {
            case 'passkey':
                $passkey = $this->input->post('passkey');
                if (empty($passkey)):
                    $MsgArr['error'] = "Invalid User";
                    return $this->json_op($MsgArr);
                else:
                    $res = $this->companies_model->getAuthCustomer(array('pass' => $passkey, 'pass_type' => 'pass_key'));
                    if (is_array($res)):
                        $res_token = $this->eshop_model->UpdateAuthToken($token, $res[0]['id']);
                        if (!$res_token) {
                            $MsgArr['status'] = "ERROR";
                            $MsgArr['msg'] = 'Unable  to create auth token';
                            return $this->json_op($MsgArr);
                        }
                        $MsgArr['status'] = "success";
                        $res[0]['auth_token'] = $res_token;
                        $res[0]['hide_signup']  = 1;
                        $MsgArr['result'] = $res;
                        return $this->json_op($MsgArr);
                    endif;
                endif;
                break;

            case 'password':

                $login_id = $this->input->post('login_id');
                $password = $this->input->post('password');
                if (empty($login_id) || empty($password)):
                    $MsgArr['error'] = "Invalid User";
                    return $this->json_op($MsgArr);
                else:

                    $res = $this->companies_model->getAuthCustomer(array('loginid' => $login_id, 'pass' => md5($password), 'pass_type' => 'password'));

                    if (is_array($res)):
                        $res_token = $this->eshop_model->UpdateAuthToken($token, $res[0]['id']);

                        if (!$res_token) {
                            $MsgArr['status'] = "ERROR";
                            $MsgArr['msg'] = 'Unable  to create auth token';
                            return $this->json_op($MsgArr);
                        }
                        $MsgArr['status'] = "SUCCESS";
                        $res[0]['auth_token'] = $res_token;
                        $MsgArr['result'] = $res;

                        return $this->json_op($MsgArr);
                    endif;
                endif;
                break;

            default:
                break;
        }
        $MsgArr['error'] = "Invalid User";
        return $this->json_op($MsgArr);
    }

    private function user_update($action) {

        $MsgArr = array();
        switch ($action) {
            case 'createuser':
           
                $ci = get_instance();
                $config_merchant_phone = $ci->config->config["merchant_phone"] ;
                
                
                /* -------------------------------- Form Validation Start  ----------------------------- */
                $this->form_validation->set_rules('name', ' Name', 'trim|required');
                $this->form_validation->set_rules('email', 'Email', 'valid_email|required');
                $this->form_validation->set_rules('phone', 'Phone', 'required|regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
            
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                $name = $this->input->post('name');
                $email = $this->input->post('email');
                $phone = $this->input->post('phone');
                
                if (empty($name) || empty($email) || empty($phone)  ):
                    $MsgArr['status'] = "ERROR ";
                    $MsgArr['msg'] = "mandetory fileds are blank ";
                    return $this->json_op($MsgArr);
                endif;
                
            
                $dup_email = $this->companies_model->duplicateUser($email, 'email', Null);
                $dup_phone = $this->companies_model->duplicateUser($phone, 'phone', Null);
                if ($dup_email === 0 && $dup_phone === 0):
                    $url = 'https://simplypos.in/api/merchant-api.php';
                    $data = array(
                        "action" => "marchantRequest",
                        "merchant" => $config_merchant_phone,
                        "customerName" => $name,
                        "customerMobile" => $phone
                    );

                    $result = $this->post_to_url($url, $data);
                    
		        if(!empty($result)):
		        	$res = json_decode($result);
                                if($res->result==1){
                                   $MsgArr['status'] = "SUCCESS";
                        	   $MsgArr['msg'] = 'Request submitted successfully ,Please wait for  approval';
                                    return $this->json_op($MsgArr);
                                }
                                $MsgArr['status'] = "ERROR";
                        	$MsgArr['msg'] = $res->message;
                                return $this->json_op($MsgArr);
		        endif;
        
                       	  $MsgArr['status'] = "ERROR ";
                          $MsgArr['msg'] = "duplicate Phone ";
                        
                        return $this->json_op($MsgArr);
            
                else:
                    if ($dup_email != 0):
                        $MsgArr['status'] = "ERROR ";
                        $MsgArr['msg'] = "duplicate Email Id ";
                    elseif ($dup_phone != 0):
                        $MsgArr['status'] = "ERROR ";
                        $MsgArr['msg'] = "duplicate Phone ";
                    endif;
                    return $this->json_op($MsgArr);
                endif;
                break;

            case 'changepassowrd':
                $this->validate_auth_token();
                /* -------------------------------- Form Validation Start  ----------------------------- */
                $this->form_validation->set_rules('user_id', 'User Id', 'numeric|required');
                $this->form_validation->set_rules('password', 'Password ', 'required');
                $this->form_validation->set_rules('new_password', 'New Password ', 'required');
                $this->form_validation->set_rules('confirm_password', 'confirm Password ', 'required');
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                $login_id = $this->input->post('user_id');
                $password = $this->input->post('password');
                $new_password = $this->input->post('new_password');
                $confirm_password = $this->input->post('confirm_password');
                
                $MsgArr['status'] = 'ERROR';
                if (empty($login_id) || empty($password) || empty($new_password)):
                    if (empty($login_id)):
                        $MsgArr['status'] = 'ERROR';
                        $MsgArr['msg'] = "User ID is  required";
                        return $this->json_op($MsgArr);
                    endif;
                    if (empty($password)):
                        $MsgArr['status'] = 'ERROR';
                        $MsgArr['msg'] = "Password is  required";
                        return $this->json_op($MsgArr);
                    endif;
                    if (empty($new_password)):
                        $MsgArr['status'] = 'ERROR';
                        $MsgArr['msg'] = "New password is  required";
                        return $this->json_op($MsgArr);
                    endif;

                    return $this->json_op($MsgArr);
                else:
  			if ( $new_password !=  $confirm_password):
                        $MsgArr['status'] = 'ERROR';
                        $MsgArr['msg'] = "Password not match";
                        return $this->json_op($MsgArr);
                    endif;

                    $res = $this->companies_model->getCompanyCustomer(array('id' => $login_id, 'password' => md5($password)));
                    if (!is_object($res)):
                        $MsgArr['status'] = 'ERROR';
                        $MsgArr['msg'] = "Invalid current password";
                        return $this->json_op($MsgArr);
                    endif;
                    $res1 = $this->companies_model->updateCompany($res->id, array('password' => md5($new_password)));
                    if ($res1):
                        $MsgArr['status'] = 'SUCCESS';
                        $MsgArr['msg'] = "password has been updated successfully";
                        return $this->json_op($MsgArr);
                    endif;
                    $MsgArr['status'] = 'ERROR';
                    $MsgArr['msg'] = "New has not been updated successfully";
                    return $this->json_op($MsgArr);
                endif;
                break;

            case 'set_billing_shiiping_info':   //Set Billing & shipping Details
                $this->validate_auth_token();
                /* -------------------------------- Form Validation Start ----------------------------- */
                $this->form_validation->set_rules('user_id', 'User Id', 'numeric|required');
                $this->form_validation->set_rules('billing_name', 'Billing Name', 'trim');
                $this->form_validation->set_rules('billing_email', 'Billing Email', 'valid_email');
                $this->form_validation->set_rules('billing_phone', 'Billing Phone', 'regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
                $this->form_validation->set_rules('billing_addr1', 'Billing Address', 'trim');
                $this->form_validation->set_rules('billing_addr2', 'Billing Address', 'trim');
                $this->form_validation->set_rules('billing_city', 'Billing City', 'trim');
                $this->form_validation->set_rules('billing_state', 'Billing State', 'trim');
                $this->form_validation->set_rules('billing_country', 'Billing Country', 'trim');
                $this->form_validation->set_rules('billing_zipcode', 'Billing Zipcode', 'trim|numeric|min_length[6]|max_length[6]');

                $this->form_validation->set_rules('shipping_name', 'Shipping Name', 'trim');
                $this->form_validation->set_rules('shipping_email', 'Shipping Email', 'valid_email');
                $this->form_validation->set_rules('shipping_phone', 'Shipping Phone', 'regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
                $this->form_validation->set_rules('shipping_addr1', 'Shipping Address', 'trim');
                $this->form_validation->set_rules('shipping_addr2', 'Shipping Address', 'trim');
                $this->form_validation->set_rules('shipping_city', 'Shipping City', 'trim');
                $this->form_validation->set_rules('shipping_state', 'Shipping State', 'trim');
                $this->form_validation->set_rules('shipping_country', 'Shipping Country', 'trim');
                $this->form_validation->set_rules('shipping_zipcode', 'Shipping Zipcode', 'trim|numeric|min_length[6]|max_length[6]');

                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                $user_id = $this->input->post('user_id');
                if ((int) $user_id == 0):
                    $MsgArr['status'] = 'ERROR';
                    $MsgArr['error'] = 'user id field is  mandetory';
                    return $this->json_op($MsgArr);
                endif;
                $_param = array('billing_name', 'billing_phone', 'billing_email', 'shipping_phone', 'shipping_email', 'billing_addr1', 'billing_addr2', 'billing_city', 'billing_state', 'billing_country', 'billing_zipcode', 'shipping_name', 'shipping_addr1', 'shipping_addr2', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_zipcode');


                $param = array();

                if (is_array($_param)):
                    foreach ($_param as $_param_key) {
                        $_param_key_val = $this->input->post($_param_key);
                        if (!empty($_param_key_val)):
                            $param[$_param_key] = $this->input->post($_param_key);
                        endif;
                    }
                endif;
                if (count($param) == 0):
                    $MsgArr['status'] = 'ERROR';
                    $MsgArr['msg'] = 'update fields are mandetory';
                    return $this->json_op($MsgArr);
                endif;

                $res = $this->companies_model->set_billing_shiiping_info($user_id, $param);
                if (!(int) $res):
                    $MsgArr['status'] = 'ERROR';
                    $MsgArr['msg'] = 'Invalid User';
                    return $this->json_op($MsgArr);
                else:
                    $MsgArr['status'] = 'SUCCESS';
                    $MsgArr['msg'] = 'set successfully';
                    return $this->json_op($MsgArr);
                endif;

                break;

            case 'set_user_info':
                $this->validate_auth_token();
                /* -------------------------------- Form Validation Start ----------------------------- */
                $this->form_validation->set_rules('user_id', 'User Id', 'trim|numeric|required');
                $this->form_validation->set_rules('name', 'Name', 'trim|required');
                $this->form_validation->set_rules('email', 'Email', 'trim|valid_email|required');
               
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                $MsgArr['status'] = 'ERROR';
                $user_id = $this->input->post('user_id');

                if ((int) $user_id == 0):
                    $MsgArr['msg'] = 'User-id field is mandetory';
                    return $this->json_op($MsgArr);
                endif;

                $name = $this->input->post('name');
                $email = $this->input->post('email');
              


                $dup_email = $this->companies_model->duplicateUser($email, 'email', $user_id);
               
                 if ($dup_email === 0 ):
                    if ($this->companies_model->updateCompany($user_id, array('name' => $name, 'email' => $email))):
                        $MsgArr['status'] = "SUCCESS";
                        $MsgArr['msg'] = 'User updated Successfully';
                        return $this->json_op($MsgArr);
                    else :
                        $MsgArr['msg'] = "User not updated Successfully ";
                        return $this->json_op($MsgArr);
                    endif;
                else:
                    if ($dup_email != 0):
                        $MsgArr['msg'] = "duplicate Email Id ";
                        return $this->json_op($MsgArr);
                    
                    endif;
                endif;

                $MsgArr['msg'] = "User not updated Successfully ";
                return $this->json_op($MsgArr);
                break;

            case 'get_billing_shiiping_info':  //Get Billing & shipping Details
                $this->validate_auth_token();
                $user_id = $this->input->post('user_id');
                /* -------------------------------- Form Validation Start  ----------------------------- */
                $this->form_validation->set_rules('user_id', 'User Id', 'numeric|required');
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                if ((int) $user_id == 0):
                    $MsgArr['status'] = 'ERROR';
                    $MsgArr['error'] = 'User id field is  mandetory';
                    return $this->json_op($MsgArr);
                endif;
                $res = $this->companies_model->get_eshop_user($user_id);
                $_param = array('billing_name', 'billing_phone', 'billing_email', 'shipping_phone', 'shipping_email', 'billing_addr1', 'billing_addr2', 'billing_city', 'billing_city', 'billing_state', 'billing_country', 'billing_zipcode', 'shipping_name', 'shipping_addr1', 'shipping_addr2', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_zipcode');

                if (is_array($res)):

                    if (is_array($_param)):
                        foreach ($res[0] as $_res_key => $_res_val) {
                            if (!in_array($_res_key, $_param)):
                                unset($res[0][$_res_key]);
                            endif;
                        }
                    endif;
                    $MsgArr['status'] = 'SUCCESS';
                    $MsgArr['msg'] = '';
                    $MsgArr['result'] = $res[0];
                    return $this->json_op($MsgArr);
                else:
                    $MsgArr['status'] = 'SUCCESS';
                    $MsgArr['msg'] = 'no data found';
                    foreach ($_param as $_res_val) {
                        $res1[$_res_val] = '';
                    }
                    $MsgArr['result'] = $res1;
                endif;

                return $this->json_op($MsgArr);
                break;

            case 'get_user': //User Details
                $user_id = $this->input->post('user_id');
                $this->validate_auth_token();
                /* -------------------------------- Form Validation Start  ----------------------------- */
                $this->form_validation->set_rules('user_id', 'User Id', 'numeric|required');
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                if ((int) $user_id == 0):
                    $MsgArr['error'] = 'user id field is  mandetory';
                    return $this->json_op($MsgArr);
                endif;

                $res = $this->companies_model->get_eshop_user($user_id);
                if (is_array($res)):
                    $MsgArr = $res[0];
                    return $this->json_op($MsgArr);
                endif;
                return $this->json_op($MsgArr);
                break;

            case 'upload_photo': //Upload Photo
                $user_id = $this->input->post('user_id');

                if ((int) $user_id == 0):
                    $MsgArr['error'] = 'user id field is  mandetory';
                    return $this->json_op($MsgArr);
                endif;

                $this->load->library('upload');

                if ($_FILES['photo']['size'] > 0 && $user_id > 0):
                    $config = array();
                    $config['upload_path'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/eshop_user/';
                    $config['overwrite'] = true;
                    $config['encrypt_name'] = TRUE;
                    $config['max_filename'] = 25;
                    $config['allowed_types'] = 'jpg|jpeg|png';
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('photo')):
                        $error = $this->upload->display_errors();
                        $MsgArr['error'] = $error;
                        return $this->json_op($MsgArr);
                    else:
                        $file = $this->upload->file_name;
                        $res = $this->companies_model->set_photo($user_id, array('user_photo' => $file, 'user_photo_path' => $config['upload_path']));
                        if ($res):
                            $MsgArr['success'] = $res;
                            return $this->json_op($MsgArr);
                        endif;
                    endif;
                endif;

                return $this->json_op($MsgArr);
                break;

            case 'reset_password_token':
                $site = $this->site->get_setting();
                $phone = $this->input->post('phone');
                /* -------------------------------- Form Validation Start  ----------------------------- */
                $this->form_validation->set_rules('phone', 'Phone', 'required|regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */

                if (empty($phone)):
                    $MsgArr['error'] = "Invalid User1";
                    return $this->json_op($MsgArr);
                else:
                    $res = $this->companies_model->getCompanyCustomer(array('phone' => $phone));
                    if (!is_object($res)):
                        $MsgArr['status'] = "ERROR ";
                        $MsgArr['msg'] = "Invalid User";
                        return $this->json_op($MsgArr);
                    endif;

                    $data = array();
                    $data['user_id'] = $res->id;
                    $data['token'] = $this->random_otp(6);
                    $data['status'] = 1;
                    $data['token_start'] = date('Y-m-d H:i:s', strtotime('now'));
                    $data['token_end'] = date('Y-m-d H:i:s', strtotime('now') + 3600);

                    $res1 = $this->companies_model->addEshopPasswordToken($data);
                    if ($res1):
                        $MsgArr['status'] = "SUCCESS";
                        $MsgArr['msg'] = 'OTP for password reset is send';
                        $msg = 'OTP for password reset is ' . $data['token'];
                        if (isset($res->email) && isset($site->default_email)):
                            $email_tpl = $this->EmailTemplate('reset_password_token');
                            $email_tpl = str_replace('[MSGBODY]', $msg, $email_tpl);
                            $subject = 'Password Reset Token';
                            $resEmail = $this->SendEmail(array('email' => $res->email, 'subject' => $subject, 'from' => $site->default_email, 'msg' => $email_tpl, 'sender' => $site->site_name));
                        endif;

                        $sms = $this->SendSMS($phone, $msg);
                        return $this->json_op($MsgArr);
                    else:
                        $MsgArr['status'] = "ERROR ";
                        $MsgArr['msg'] = "Unable to  create token";
                        return $this->json_op($MsgArr);
                    endif;
                endif;
                break;

            case 'reset_password':
                $site = $this->site->get_setting();
                /* -------------------------------- Form Validation Start  ----------------------------- */
                $this->form_validation->set_rules('phone', 'Phone', 'required|regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
                $this->form_validation->set_rules('token', 'TOKEN', 'trim|required');
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */
                $phone = $this->input->post('phone');
                $password_token = $this->input->post('token');
                if (empty($phone) || empty($password_token)):
                    $MsgArr['status'] = "ERROR ";
                    $MsgArr['msg'] = "mandetry fields are blank";
                    return $this->json_op($MsgArr);
                else:

                    $res = $this->companies_model->getCompanyCustomer(array('phone' => $phone));

                    if (!is_object($res)):
                        $MsgArr['status'] = "ERROR ";
                        $MsgArr['msg'] = "Invalid User";
                        return $this->json_op($MsgArr);
                    endif;
                    $data = array();
                    $data['user_id'] = $res->id;
                    $data['token'] = $password_token;
                    $token_res = $this->companies_model->validateEshopPasswordToken($data);

                    if ($token_res):
                        $new_password = $this->random_password(8);
                        $res1 = $this->companies_model->updateCompany($res->id, array('password' => md5($new_password)));
                        if ($res1):
                            $MsgArr['status'] = "SUCCESS";
                            $MsgArr['msg'] = "password has been reset successfully,please check Email";
                            if (isset($res->email) && isset($site->default_email)):
                                $email_tpl = $this->EmailTemplate('reset_password');
                                $msg = 'New Password :' . $new_password;
                                $email_tpl = str_replace('[MSGBODY]', $msg, $email_tpl);
                                $subject = 'Reset Password';
                                $this->SendEmail(array('email' => $res->email, 'subject' => $subject, 'from' => $site->default_email, 'msg' => $email_tpl, 'sender' => $site->site_name));
                            endif;
                            return $this->json_op($MsgArr);
                        endif;
                    else:
                        $MsgArr['status'] = "ERROR ";
                        $MsgArr['msg'] = "Invalid token ";
                        return $this->json_op($MsgArr);
                    endif;

                endif;

                break;

            case 'update_user_info':
                /* -------------------------------- Form Validation Start ----------------------------- */
                $this->form_validation->set_rules('phone', 'Phone ', 'trim|required|regex_match[/^[0-9]{10}$/]'); 
                if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                /* -------------------------------- Form Validation End  ----------------------------- */
               
                $MsgArr['status'] = 'ERROR';
                
                $phone = $this->input->post('phone');
                $phoneRes = $this->companies_model->getCompanyCustomer(array('phone'=>$phone));
                if(empty($phoneRes)):
                    $MsgArr['msg'] = "Invalid User ";
                    return $this->json_op($MsgArr);
                endif;
                
                
                $phone = $this->input->post('phone');
                
                $name  = $this->input->post('name');
              
                $arr = array('name','email','address','dob','anniversary','dob_father','dob_mother','dob_child1','dob_child2'  );
                $email = $this->input->post('email');
                if(!empty($email)):
                  	
                     
                     if($email == $phoneRes->email):
                           $this->form_validation->set_rules('email', 'Email', 'valid_email');
                     else:
                           $this->form_validation->set_rules('email', 'Email', 'valid_email|is_unique[companies.email]');
                     endif;
                endif;
                
                $dob = $this->input->post('dob');
                if(!empty($dob) ){
                  
                  $this->form_validation->set_rules('dob', 'Date Birth', 'integer|min_length[10]|max_length[10]');
                }
                
                $date_anniversary = $this->input->post('anniversary');
                if(!empty($date_anniversary)  ){
                  $this->form_validation->set_rules('anniversary', 'anniversary Date', 'integer|min_length[10]|max_length[10]');
                }
                
                $dob_father = $this->input->post('dob_father');
                if(!empty($dob_father) && $this->ValidateYYYYDate($dob_father)==false ){
                 $this->form_validation->set_rules('dob_father', 'Father Date Birth', 'integer|min_length[10]|max_length[11]');
                }
                
                $dob_mother = $this->input->post('dob_mother');
                 if(!empty($dob_mother)){
 			$this->form_validation->set_rules('dob_mother', 'Mother Date Birth', 'integer|min_length[10]|max_length[11]');
                }
                
                $dob_child1= $this->input->post('dob_child1');
                if(!empty($dob_child1) ){
                  $this->form_validation->set_rules('dob_child1', 'Older Date Birth', 'integer|min_length[10]|max_length[10]');
                }
                
                
                $dob_child2= $this->input->post('dob_child2');
                if(!empty($dob_child2)  ){
                 $this->form_validation->set_rules('dob_child2', 'Younger Date Birth', 'integer|min_length[10]|max_length[10]');
                }
                
            	if ($this->form_validation->run() === FALSE) {
                    $this->validate_error_parsing();
                }
                
                $phoneRes = $this->companies_model->getCompanyCustomer(array('phone'=>$phone));
                
                if(empty($phoneRes)):
                    $MsgArr['msg'] = "Invalid User ";
                    return $this->json_op($MsgArr);
                endif;
                
                if ($phoneRes->id > 0 ):
                    $f_arr = array();
                    foreach ($arr as $key => $value) {
                        if(!empty($this->input->post($value))):
                            if(in_array($value,array( 'dob','anniversary','dob_father','dob_mother','dob_child1','dob_child2' ))):
                             $f_arr[$value] = date('Y-m-d',$this->input->post($value));
                            else:
                            	
                            $f_arr[$value] = $this->input->post($value);
                            endif;
                        endif;
                    }
                    if (count($f_arr)==0):
                    	$MsgArr['msg'] = "User not updated Successfully ";
                        return $this->json_op($MsgArr);
                    endif;
                    $f_arr['is_synced']=1;
                    if (count($f_arr) > 1  && $this->companies_model->updateCompany($phoneRes->id, $f_arr)):
                        $MsgArr['status'] = "SUCCESS";
                        $MsgArr['msg'] = 'User updated Successfully';
                        return $this->json_op($MsgArr);
                    else :
                        $MsgArr['msg'] = "User not updated Successfully ";
                        return $this->json_op($MsgArr);
                    endif;
                else:
                    if ($dup_email != 0):
                       $MsgArr['msg'] = "Invalid User ";
                        return $this->json_op($MsgArr);
                    
                    endif;
                endif;

                $MsgArr['msg'] = "User not updated Successfully ";
                return $this->json_op($MsgArr);
                break;
		
            case 'sync_custmer_count':
			$MsgArr['status'] = "ERROR";
			$Res = $this->companies_model->nonSyncCustmerCount();
			if(empty($Res )):
				$MsgArr['msg'] = "Unknown Error  ";
				return $this->json_op($MsgArr);
			endif;
			$MsgArr['status'] = "SUCCESS";
			$MsgArr['count'] = (int)$Res;
			 $MsgArr['msg'] = $MsgArr['count']." Non sync  recod  found ";
			 return $this->json_op($MsgArr);
		break;
		
            case 'sync_custmer':
			$MsgArr['status'] = "ERROR";
			$CurrentNonSync = $this->companies_model->nonSyncCustmerCount();
		   
			if($CurrentNonSync == 0):
				$MsgArr['msg'] = "no record found";
				return $this->json_op($MsgArr);
			endif; 
			$resNonSync       = $this->companies_model->nonSyncCustmer(15);
			if(!is_array($resNonSync)):
				$MsgArr['msg'] = "Unknown error Please try again , Code-012";
				return $this->json_op($MsgArr);
			endif;
			foreach ($resNonSync as    $custmerObj) {
				if(isset($custmerObj->id)):
				$customer       = $this->companies_model->getCompanyByID($custmerObj->id);
				$this->load->library('sma');
				$this->sma->SyncCustomerData($customer);
				endif;
			}
			$afterSync = $this->companies_model->nonSyncCustmerCount();
			if($CurrentNonSync==$afterSync):
				 $MsgArr['msg'] = "No  record  sync";
				 return $this->json_op($MsgArr);
			else:
				 $MsgArr['msg'] = $CurrentNonSync-$afterSync." record  sync";
				 return $this->json_op($MsgArr);
			endif;
			
			 $MsgArr['msg'] =  $afterSync." record remains to  sync";
			return $this->json_op($MsgArr);
		break;
            
            default:
                break;
        }
    }
    
    private function ValidateYYYYDate($date){
	if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$date))
	    {
	        return true;
	    }else{
	        return false;
	    }
     }

    private function EmailTemplate($type) {
        $html = '';
        switch ($type) {
            case 'reset_password_token':
                $html = 'Dear Customer,[MSGBODY] Thanks ';
                break;

            case 'reset_password':
                $html = 'Dear Customer,<br>[MSGBODY] Thanks ';
                break;
        }
        return $html;
    }

    private function SendSMS($mobile, $content) {
        $msg = "Dear Customer, $content Thanks and regards.";
         
        $datasms = array(
            "user" =>   $this->smsUser,
            "password" => $this->smsPassword,
            "msisdn" => "+91" . $mobile,
            "sid" => $this->smsSID,
            "msg" => $msg,
            "fl" => 0,
            "gwid" => 2
        );
        $url =$this->smsAPI; // 'http://payonlinerecharge.com/vendorsms/pushsms.aspx';
        foreach ($datasms as $key => $value) {
            $fields .= $key . '=' . $value . '&';
        }
        rtrim($fields, '&');
        $post = curl_init();
        curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_POST, count($data));
        curl_setopt($post, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($post);
        curl_close($post);
        return $result;
    }

    //SendEmail(array('email'=>$email,'subject'=>$subject,'from'=>$from,'msg'=>$msg,'sender'=>$sender));
    private function SendEmail($param) {
        $email = isset($param['email']) ? $param['email'] : NULL;
        $subject = isset($param['subject']) ? $param['subject'] : NULL;
        $from = isset($param['from']) ? $param['from'] : NULL;
        $sender = isset($param['sender']) ? $param['sender'] : '';
        $msg = isset($param['msg']) ? $param['msg'] : NULL;
        $attachment = isset($param['attachment']) ? $param['attachment'] : NULL;
        if (empty($email) || empty($subject) || empty($from) || empty($msg)):
            return false;
        endif;
        $this->load->library('email');
        $config = array();
        $config['useragent'] = " ";
        $config['protocol'] = $this->Settings->protocol;
        $config['mailtype'] = "html";
        $config['crlf'] = "\r\n";
        $config['newline'] = "\r\n";
        if ($this->Settings->protocol == 'sendmail') {
            $config['mailpath'] = $this->Settings->mailpath;
        } elseif ($this->Settings->protocol == 'smtp') {
            $this->load->library('encrypt');
            $config['smtp_host'] = $this->Settings->smtp_host;
            $config['smtp_user'] = $this->Settings->smtp_user;
            $config['smtp_pass'] = $this->encrypt->decode($this->Settings->smtp_pass);
            $config['smtp_port'] = $this->Settings->smtp_port;
            if (!empty($this->Settings->smtp_crypto)) {
                $config['smtp_crypto'] = $this->Settings->smtp_crypto;
            }
        }
        $this->email->initialize($config);
        $this->email->subject($subject);
        $this->email->message($msg);
        if (!empty($attachment)):
            $this->email->attach($attachment);
        endif;
        $this->email->from($from, $sender);
        $this->email->to($email);
        return $this->email->send();
    }

    private function validate_error_parsing() {
        $validator = &_get_validation_object();
        $val_error = $validator->error_array();
        $str = '';
        if (is_array($val_error)):
            foreach ($val_error as $key => $value) {
                $str = $str . $value . ',';
            }
        endif;
        $MsgArr['status'] = 'ERROR';
        $MsgArr['msg'] = !empty($str) ? substr($str, 0, -1) : false;
        $MsgArr['msg_arr'] = $val_error;
        return $this->json_op($MsgArr);
    }

    private function validate_auth_token() {
        $user_id = $this->input->post('user_id');
        $auth_token = $this->input->post('auth_token');
        $MsgArr['status'] = 'ERROR';
        if (empty($user_id) || empty($auth_token)):
            if (empty($user_id)):
                $MsgArr['msg'] = 'user Id field is  empty ';
            endif;

            if (empty($auth_token)):
                $MsgArr['msg'] = 'Auth token  is missing  ';
            endif;
            return $this->json_op($MsgArr);
        endif;
        $res = $this->eshop_model->validateAuthToken($auth_token, $user_id);
        if (!$res) {
            $MsgArr['msg'] = 'Invalid Auth token  ';
            return $this->json_op($MsgArr);
        }
    }

    public  function send_smsdashboard_sms(){
        $MsgArr = array();
        $MsgArr['status'] = 'ERROR';
        $this->load->model('event_model');
        
        $SMSKEY = $this->input->get('SMSKEY');
        $EDATE = $this->input->get('EDATE');
        $date = isset($EDATE) && !empty($EDATE)?$EDATE:date("m-d");
        if($SMSKEY!=$this->SMSAPIKEY):
            $MsgArr['msg'] = 'Invalid api key ';
            return $this->json_op($MsgArr);
        endif;
        $selectedMember = $this->site->getAllCronCustomer();
        $res = $this->event_model->getAllCustomerFromEvent($date,$selectedMember);
        
 
        if (!$res) {
            $MsgArr['msg'] = 'No event founds ';
            return $this->json_op($MsgArr);
        }
        $param = $res;
        $arr['users']       = isset($param['users']) && is_array($param['users']) ? $param['users'] : array();
        if (count($arr['users']) == 0):
            $MsgArr['msg'] = 'No event founds ';
            return $this->json_op($MsgArr);
        endif;
        
        $arr['dob']         = isset($param['dob']) && is_array($param['dob']) ? $param['dob'] : array();
        $arr['anniversary'] = isset($param['anniversary']) && is_array($param['anniversary']) ? $param['anniversary'] : array();
        $arr['dob_father']  = isset($param['dob_father']) && is_array($param['dob_father']) ? $param['dob_father'] : array();
        $arr['dob_mother']  = isset($param['dob_mother']) && is_array($param['dob_mother']) ? $param['dob_mother'] : array();
        $arr['dob_child1']  = isset($param['dob_child1']) && is_array($param['dob_child1']) ? $param['dob_child1'] : array();
        $arr['dob_child2']  = isset($param['dob_child2']) && is_array($param['dob_child2']) ? $param['dob_child2'] : array();
        $Y =1;
        $N =0;
        
        $resB = $this->site->getBirthDayTemplate(1);
        $msgB =  isset($resB->template_content)?$resB->template_content:'';
        
        $resA = $this->site->getAnniversaryTemplate(1);
        $msgA =  isset($resA->template_content)?$resA->template_content:'';
          
        $b_array =  array_merge($arr['dob'],$arr['dob_father'], $arr['dob_mother'], $arr['dob_child1'],$arr['dob_child2']);
        
        foreach ($arr['users'] as $userData) :
            $userID = isset($userData['id']) ? $userData['id'] : '0';
            $userName = isset($userData['name']) ? $userData['name'] : '-';
            $userEmail = isset($userData['email']) ? $userData['email'] : '-';
            $userPhone = isset($userData['phone']) ? $userData['phone'] : '-';
            if ($userID > 0):
            
                
                // ----------------------- B'day --------------------//
                
            	if(!empty($msgB)):
            		 in_array($userID,$b_array)? $this->sma->SendSMS($userPhone, $msgB) : '';
            	endif;
            	
                // -----------------------anniversary--------------------//
                
                if(!empty($msgA)):
                	in_array($userID, $arr['anniversary']) ? $this->sma->SendSMS($userPhone, $msgA) : '';
                endif;	
                
            endif;
        endforeach;
        
        $MsgArr['status'] = 'SUCCESS';
        $MsgArr['msg'] = 'SMS process initiated successfully ';
        return $this->json_op($MsgArr);
    }
        
    public  function send_smsdashboard_email() {
        $MsgArr = array();
        $MsgArr['status'] = 'ERROR';
        $this->load->model('event_model');
        
        $SMSKEY = $this->input->get('SMSKEY');
        $EDATE = $this->input->get('EDATE');
        $date = isset($EDATE) && !empty($EDATE)?$EDATE:date("m-d");
        if($SMSKEY!=$this->SMSAPIKEY):
            $MsgArr['msg'] = 'Invalid api key ';
            return $this->json_op($MsgArr);
        endif;
        
        $selectedMember = $this->site->getAllCronCustomer();
        $res = $this->event_model->getAllCustomerFromEvent($date,$selectedMember);
        
 
        if (!$res) {
            $MsgArr['msg'] = 'No event founds ';
            return $this->json_op($MsgArr);
        }
        $param = $res;
        $arr['users']       = isset($param['users']) && is_array($param['users']) ? $param['users'] : array();
        if (count($arr['users']) == 0):
            $MsgArr['msg'] = 'No event founds ';
            return $this->json_op($MsgArr);
        endif;
        
        $arr['dob']         = isset($param['dob']) && is_array($param['dob']) ? $param['dob'] : array();
        $arr['anniversary'] = isset($param['anniversary']) && is_array($param['anniversary']) ? $param['anniversary'] : array();
        $arr['dob_father']  = isset($param['dob_father']) && is_array($param['dob_father']) ? $param['dob_father'] : array();
        $arr['dob_mother']  = isset($param['dob_mother']) && is_array($param['dob_mother']) ? $param['dob_mother'] : array();
        $arr['dob_child1']  = isset($param['dob_child1']) && is_array($param['dob_child1']) ? $param['dob_child1'] : array();
        $arr['dob_child2']  = isset($param['dob_child2']) && is_array($param['dob_child2']) ? $param['dob_child2'] : array();
        $Y =1;
        $N =0;
       
        
         $resB = $this->site->getBirthDayTemplate(2);
        $msgB = isset($resB->template_content)?'<p>'.$resB->template_content.'</p>':'';
        $subB = isset($resB->template_name)?$resB->template_name:'';
        
        
        $resA = $this->site->getAnniversaryTemplate(2);
        $msgA =  isset($resA->template_content)?$resA->template_content:'';
        $subA = isset($resA->template_name)?$resA->template_name:'';
        
        $b_array =  array_merge($arr['dob'],$arr['dob_father'], $arr['dob_mother'], $arr['dob_child1'],$arr['dob_child2']);
        $site = $this->site->get_setting();
        foreach ($arr['users'] as $userData) :
            $userID = isset($userData['id']) ? $userData['id'] : '0';
            $userName = isset($userData['name']) ? $userData['name'] : '-';
            $userEmail = isset($userData['email']) ? $userData['email'] : '-';
               $userEmail = trim($userEmail);
            $userPhone = isset($userData['phone']) ? $userData['phone'] : '-';
            if ($userID > 0):
            
            
               // ----------------------- B'day --------------------//
                
            	if(!empty($msgB) && !empty($subB)):
            	$paramB = array('email' => $userEmail, 'subject' => $subB, 'from' => $site->default_email, 'msg' => $msgB, 'sender' => $site->site_name);
            	 $res =  in_array($userID,$b_array)?    $this->SendEmail($paramB) : 
            	 '';
            	
            	endif;
            	
                // -----------------------anniversary--------------------//
                
                if(!empty($msgA)  && !empty($subA)):
                 $paramA = array('email' => $userEmail, 'subject' => $subA, 'from' => $site->default_email, 'msg' => $msgA, 'sender' => $site->site_name);
                 $res_A =  in_array($userID, $arr['anniversary'])?$this->SendEmail( $paramA) : '';
                 
                endif;	
                
            endif;
        endforeach;
        
        $MsgArr['status'] = 'SUCCESS';
        $MsgArr['msg'] = 'Email process initiated successfully ';
        return $this->json_op($MsgArr);
    }
     
    
}

?>