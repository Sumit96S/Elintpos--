<?php
  header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');

defined('BASEPATH') OR exit('No direct script access allowed');

class Paynear extends CI_Controller {

    private $APIKEY         = "435DSFSDFDSF743500909809DFSFJKJ234324534";
    private $MERCHANT_PHONE = '';
    
    public function __construct() {
        parent::__construct();
        
        $ci = get_instance();
        $config = $ci->config;
        $this->MERCHANT_PHONE = isset($config->config['merchant_phone']) ? $config->config['merchant_phone'] : '';
        
        $this->load->model('auth_model'); 
        $this->load->model('companies_model');
        $this->load->model('pos_model');
        $this->load->model('eshop_model');
        $this->load->library('form_validation');
      

    }
    
    public function v2(){
    	
    	if(!isset($_POST['secret_token'] )) :
    	 $data = json_decode(file_get_contents('php://input'), true);
    	 $_POST = $data;
    	endif;
        $result = array();
        $result['status'] = 'error';
        $this->paynear_notify_app();
    } 
    
    private  function authToken(){
          return  md5($this->MERCHANT_PHONE.$this->APIKEY);
    } 
    
    private function checkToken() {
 
        $post_auth_token = $this->input->post('secret_token') ? $this->input->post('secret_token') : NULL;
        $sale_id = $this->input->post('orderRefNo') ? $this->input->post('orderRefNo') : NULL;
        
        $auth_token = $this->authToken();

        $result = array();
        $result['status'] = 'error';
        $result['redirest_url'] = base_url('pos');

        if (empty($post_auth_token)):
            $result['msg'] = 'Secret token is empty';
            $this->json_op($result);
        endif;
        
        if (empty($sale_id )):
            $result['msg'] = 'orderRefNo is empty';
            $this->json_op($result);
        endif;
        
        $_req=  $this->pos_model->getPaynearTransaction(array('order_id'=>$sale_id ,'secret_token'=>$post_auth_token));
        if(!isset($_req->order_id)){
	        $result['msg'] = 'Invalid orderRefNo ';
            	$this->json_op($result);
	}
        if($_req->order_id==$sale_id && $_req->secret_token==$post_auth_token):
        	// valid TOKEN
        else:
        	$result['msg'] = 'Invalid secret token';
            	$this->json_op($result);
        endif;
        
    }

    private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }
    
    private function paynear_notify_app() {
      
  
        $this->load->library('logs');
        $this->logs->write('paynear', json_encode($_POST), $val);
        
        $this->checkToken();
        
        $this->load->library('paynearepay');
        $ci  = get_instance();
        $ci->config->load('payment_gateways', TRUE);
        $result =  array();    
        $result['status'] = 'error';
        $result['redirect_url'] =  base_url('pos');  
        
        $payment_config         = $ci->config->item('payment_gateways');
        $paynear_credential     = $payment_config['paynear'];

        $PAYNEAR_SECRET_KEY     = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
        $PAYNEAR_MERCHANT_ID    = isset($paynear_credential['PAYNEAR_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_MERCHANT_ID'] : '';
        $testMode               = isset($paynear_credential['PAYNEAR_SANDBOX']) &&  $paynear_credential['PAYNEAR_SANDBOX']==1   ? true : false;
        $api = new PaynearEpay($PAYNEAR_MERCHANT_ID, $PAYNEAR_SECRET_KEY, $testMode);
        
        try{
       
            $result1 = $_POST;
           
            $ORDERID = $this->input->post('orderRefNo') ? $this->input->post('orderRefNo') : NULL;
            if($ORDERID):
                $this->pos_model->updatePaynearTransaction($ORDERID, array('response_data'=>serialize($result1),'update_time'=>date('Y-m-d H:i:s')));
            endif;
            $params['orderRefNo']    = $result1['orderRefNo'];
            $params['paymentId']     = $result1['paymentId'];
            $params['transactionId'] = $result1['transactionId'];
            $params['amount']        = $result1['amount'];
            $params['transactionDate']  = $result1['txnDateNTime'];
            if($result1['responseCode']=='00' && $result1['responseMessage']=='Success'):
                $_result = $result1;
                if($_result['responseCode']=='00' && $_result['responseMessage']=='Success'):
                    $sid = $ORDERID; 
                     $_result['transactionDate']  = $result1['txnDateNTime'];
                    $res = $this->pos_model->PaynearAfterSale($_result,$sid);
                    if($res): 
                       $result['status'] = 'success';
                       $result['msg']     = 'saved successfully';
                       $result['redirect_url'] =  base_url("pos/view/" . $sid);  
                       $this->json_op($result);
                      
                    endif; 
                else:
                    $result['msg']     = $_result['responseMessage'];
                    $this->json_op($result);
                endif;
                
            endif;
            $result['msg']         = 'Unkonwn Error please Try  again . ERR-01';
            $result['msg_details'] = $_result;
            $this->json_op($result);
         } catch(Exception $e){ 
            $result['msg'] =  $e->getMessage();
            $this->json_op($result);
         }
          $this->json_op($result);  
     
     }        

}
?>