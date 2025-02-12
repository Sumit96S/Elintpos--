<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Request-With');
header('Access-Control-Allow-Credentials: true');

defined('BASEPATH') OR exit('No direct script access allowed');

class Payswiff extends MY_Controller {

    private $APIKEY         = "435DSFSDFDSF743500909809DFSFJKJ234324534";
    private $MERCHANT_PHONE = "";
    
    public function __construct() {
        parent::__construct();
        
        $ci = get_instance();
        $config = $ci->config;
        $this->MERCHANT_PHONE = isset($config->config['merchant_phone']) ? $config->config['merchant_phone'] : '';
        
       // $this->load->model('auth_model'); 
        //$this->load->model('companies_model');
        $this->load->model('pos_model');
       // $this->load->model('eshop_model');
        $this->load->model('sales_model');
       // $this->load->library('form_validation');
    }
    
    public function v2(){
    	
       $responseData = json_decode($_POST['responseData'],TRUE);
             
    	if(!isset($responseData['secret_token'] )) :
            
            $data = json_decode(file_get_contents('php://input'), true);    	 
            $_POST = $data;
        
    	endif;
        $result = array();
        $result['status'] = 'error';
        
        $result = $this->payswiff_notify_app($responseData);
        $this->json_op($result);
        /*
    ?>  <script>
            window.MyHandler.InitiateCCAndroidBillPrint('<?php echo json_encode($result); ?>');
        </script>
    <?php
        */
    } 
    
    private function authToken(){
        return md5($this->MERCHANT_PHONE.$this->APIKEY);
    } 
    
    private function checkToken($responseData) {
 
        $post_auth_token = $responseData['secret_token'] ? $responseData['secret_token'] : NULL;
        $sale_id = $responseData['orderRefNo'] ? $responseData['orderRefNo'] : NULL;
        
        $auth_token = $this->authToken();

        $result = array();
        $result['status'] = 'error';
        //$result['redirest_url'] = base_url('pos');

        if (empty($post_auth_token)):
            $result['msg'] = 'Secret token is empty';
            $this->json_op($result);
        endif;
        
        if (empty($sale_id )):
            $result['msg'] = 'orderRefNo is empty';
            $this->json_op($result);
        endif;
        
        $_req = $this->pos_model->getPayswiffTransaction(array('order_id'=>$sale_id ,'secret_token'=>$post_auth_token));
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
    
    private function payswiff_notify_app($responseData) {      
  
        $this->load->library('logs');
        $this->logs->write('payswiff', json_encode($responseData), $val);
        
        $this->checkToken($responseData);
       /* 
        $this->load->library('paynearepay');
        $ci  = get_instance();
        $ci->config->load('payment_gateways', TRUE);
        */
        $result =  array();    
        $result['status'] = 'error';
       // $result['redirect_url'] = base_url('pos');  
       /* 
        $payment_config         = $ci->config->item('payment_gateways');
        $paynear_credential     = $payment_config['paynear'];

        $PAYNEAR_SECRET_KEY     = isset($paynear_credential['PAYNEAR_SECRET_KEY']) && !empty($paynear_credential['PAYNEAR_SECRET_KEY']) ? $paynear_credential['PAYNEAR_SECRET_KEY'] : '';
        $PAYNEAR_MERCHANT_ID    = isset($paynear_credential['PAYNEAR_MERCHANT_ID']) && !empty($paynear_credential['PAYNEAR_MERCHANT_ID']) ? $paynear_credential['PAYNEAR_MERCHANT_ID'] : '';
        $testMode               = isset($paynear_credential['PAYNEAR_SANDBOX']) &&  $paynear_credential['PAYNEAR_SANDBOX']==1   ? true : false;
        $api = new PaynearEpay($PAYNEAR_MERCHANT_ID, $PAYNEAR_SECRET_KEY, $testMode);
        */
        try{
       
            $result1 = $responseData;
           
            $ORDERID = $responseData['orderRefNo'] ? $responseData['orderRefNo'] : NULL;
            if($ORDERID):
                $this->pos_model->updatePayswiffTransaction($ORDERID, array('response_data'=>serialize($result1),'update_time'=>date('Y-m-d H:i:s')));
            endif;
            $params['orderRefNo']       = $result1['orderRefNo'];
            $params['paymentId']        = $result1['paymentId'];
            $params['transactionId']    = $result1['transactionId'];
            $params['amount']           = $result1['amount'];
            $params['transactionDate']  = $result1['txnDateNTime'];
            
            if($result1['responseCode']=='1001' && $result1['responseMessage']=='Success'):
                $_result = $result1;
                if($_result['responseCode']=='1001' && $_result['responseMessage']=='Success'):
                    $sid = $ORDERID; 
                    $_result['transactionDate'] = $result1['txnDateNTime'];
                    $res = $this->pos_model->PayswiffAfterSale($_result,$sid);
                    if($res): 
                       $result['status'] = 'success';
                       $result['msg']     = 'payment successfully saved';                       
                    else:
                         $result['status'] = 'failed';
                         $result['msg']     = 'Failed payment saved';                         
                    endif;
                    $result['print_data'] = $this->payswiff_app_invoice($ORDERID); 
                    return $result;
                else:
                    $result['msg'] = $_result['responseMessage'];
                    $result['print_data'] = $this->payswiff_app_invoice($ORDERID);
                    return $result;
                endif;
            else:
                $result['msg'] = 'PAYMENT DECLINED';
                $result['print_data'] = $this->payswiff_app_invoice($ORDERID); 
                return $result;
            endif;
            
         } catch(Exception $e) { 
            $result['msg'] = $e->getMessage();
            return $result;
         }
          return $result;
     } 
     
     
      public function payswiff_app_invoice($sale_id) {
          
        $_PID = $this->Settings->default_printer;
          
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        $this->load->helper('text');
//        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
//        $this->data['message'] = $this->session->flashdata('message');
        $inv = $this->pos_model->getInvoiceByID($sale_id);
        
        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($sale_id, $inv->return_id);
        endif;
      
        $isGstSale = $this->site->isGstSale($sale_id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;
         
//        if (!$this->session->userdata('view_right')) {
//            $this->sma->view_rights($inv->created_by, true);
//        }
        
        $print = array();
        $print['print_option'] = $this->site->defaultPrinterOption($_PID);
        $print['rows'] = $this->data['rows'] = $this->pos_model->getAllInvoiceItems($sale_id);
        $biller_id = $inv->biller_id;
        $customer_id = $inv->customer_id;
        $print['biller']    = $this->data['biller']   = $this->pos_model->getCompanyByID($biller_id);
        $print['customer']  = $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);
        $print['payments']  = $this->data['payments'] = $this->pos_model->getInvoicePayments($sale_id);
        $print['pos']       = $this->data['pos']      = $this->pos_model->getSetting();
        unset($print['pos']->pos_theme);
        $print['barcode'] = $this->data['barcode']   = $this->barcode($inv->reference_no, 'code128', 30);
        $print['return_sale'] = $this->data['return_sale'] = $inv->return_id ? $this->pos_model->getInvoiceByID($inv->return_id) : null;
        $print['return_rows'] = $this->data['return_rows'] = $inv->return_id ? $this->pos_model->getAllInvoiceItems($inv->return_id) : null;
        $print['return_payments'] = $this->data['return_payments'] = $this->data['return_sale'] ? $this->pos_model->getInvoicePayments($this->data['return_sale']->id) : null;
        $print['inv'] = $this->data['inv'] = $inv;
        $print['sid'] = $this->data['sid'] = $sale_id;
        $print['modal'] = $this->data['modal'] = $modal;
        $print['page_title'] = $this->data['page_title'] = $this->lang->line("invoice");
        $print['taxItems'] = $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($inv->id, $inv->return_id);
        //Set Sale items image
         
        if (!empty($print['rows'])) {
            foreach ($print['rows'] as $key => $row) {
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $print['rows'][$key]->image = $product->image;
            }
        } 
        
      $Settings = $this->Settings; //$this->site->get_setting();
      
                    
        $print['pos_type'] = $Settings->pos_type;
        $print['show_product_image'] = $Settings->invoice_product_image;

       // $this->data['sms_limit'] = $this->sma->BalanceSMS();
        if(isset($Settings->pos_type) && $Settings->pos_type == 'pharma'):
            $print['patient_name'] = $inv->cf1;
            $print['doctor_name'] = $inv->cf2;
        endif;
        $print['show_kot'] = false;
        if(isset($Settings->pos_type) && $Settings->pos_type == 'restaurant'):
            $print['show_kot'] = true;
        endif;
                            
        $print['brcode'] = $this->sma->save_barcode($inv->reference_no, 'code128', 66, false);
        $print['qrcode'] = $this->sma->qrcode('link', urlencode(site_url('sales/view/' . $inv->id)), 2);
        $arr = explode("'", $print['brcode']);
        $print['brcode'] = $arr[1];
        $qrr = explode("'", $print['qrcode']);
        $print['qrcode'] = $qrr[1];
        //echo $print['rows'][0]->net_unit_price;
        foreach ($print['rows'] as $key => $row) {
            //Set Sale items image.
            foreach ($row as $key2 => $value) {
                if ($key2 == 'quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'unit_quantity') {
                    $print['rows'][$key]->quantity = round($value, 2);
                }
                if ($key2 == 'product_id') {
                    $product = $this->pos_model->getProductByID($value, $select = 'image');
                    $print['rows'][$key]->cf1 = $product->image;
                }
            }
        } 
                    
       return $print;            
        
    }
    
    public function barcode($text = null, $bcs = 'code128', $height = 50) {
        return site_url('products/gen_barcode/' . $text . '/' . $bcs . '/' . $height);
    }
}
?>