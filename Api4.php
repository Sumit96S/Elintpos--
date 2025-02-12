<?php defined('BASEPATH') OR exit('No direct script access allowed');
header('Access-Control-Allow-Origin: *');
class Api4 extends MY_Controller {

    private $api_private_key = '';
    private $posVersion = '';
    private $pos_type = 'amstead';
    private $ci = '';

    public function __construct() {
        parent::__construct();
 
       

        $this->load->model('Superadmin_model');
         $this->load->model('Api4_model');

        $this->posVersion = json_decode($this->Settings->pos_version);
        $this->pos_type = $this->Settings->pos_type;
        $this->api_private_key = isset($this->Settings->api_privatekey) && !empty($this->Settings->api_privatekey) ? $this->Settings->api_privatekey : $config->config['api3_private_key'];

        $this->ci = $ci = get_instance();
        $config = $ci->config;
        $this->merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : NULL;
        
        if ($this->posVersion->version < 4.03) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 404;
            $data['current_pos_version'] = $this->posVersion->version;
            $data['pos_version'] = $this->posVersion->version;
            $data['pos_type'] = $this->pos_type;
            $data['api_access_status'] = $this->Settings->api_access ? 'Active' : 'Blocked';
            $data['mag'] = 'API required the pos version 4.03 or above.';
            echo $this->json_op($data);
            exit;
        }//end if

        if (!$this->Settings->api_access) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 405;
            $data['current_pos_version'] = $this->posVersion->version;
            $data['pos_version'] = $this->posVersion->version;
            $data['pos_type'] = $this->pos_type;
            $data['api_access_status'] = $this->Settings->api_access ? 'Active' : 'Blocked';
            $data['mag'] = 'API access is blocked.';
            echo $this->json_op($data);
            exit;
        }//end if

        if (!isset($_POST)) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 101;
            $data['mag'] = 'Invalid api request method';
            $data['private_key_msg'] = 'mismatch';
            echo $this->json_op($data);
            exit;
        } else {

            $privatekey = $this->input->post('privatekey');
            $this->action = $this->input->post('action');
            
            if ($this->api_private_key == NULL) {
                $data['status'] = 'ERROR';
                $data['error_code'] = 100;
                $data['mag'] = 'POS API private key not available or generated';
                $data['private_key_msg'] = 'mismatch';
                echo $this->json_op($data);
                exit;
            } elseif ($this->api_private_key !== $privatekey) {
                $data['status'] = 'ERROR';
                $data['error_code'] = 102;
                $data['mag'] = 'Private key mismatch';
                $data['private_key_msg'] = 'mismatch';
                echo $this->json_op($data);
                exit;
            }
        }//end else
    }
    
     
    public function index() { 
       
        $action = $this->input->post('action');

        $this->synchdate = ($this->input->post('synchdate') !== '') ? $this->input->post('synchdate') : NULL;

        $this->Superadmin_model->setLastSynchTime($this->synchdate);

        $data = $this->getSuperadminUpdatesData($action);
        
        $this->json_op($data);
    }
    
    /*public function getSuperadminUpdatesData($action) {
        
        $tables = $this->Superadmin_model->getSynchTables($action);       
        
        $data['status'] = "ERROR";
         
        if(is_array($tables)) {
            
            foreach ($tables as $key => $tableName) {                
                $tableNameData = ($tableName == 'sma_users') ? 'sma_pos_users' : $tableName;
                $data['data'][$tableNameData] = $this->Superadmin_model->getSynchData($tableName, $action);
            }
            
            if($data){
                $data['status'] = "SUCCESS";
            }
            
            return $data;
        }
        return false;
    }*/
   public function getSuperadminUpdatesData($action) {
        $data['status'] = "ERROR";
        if( in_array($action , ['synch_updates_deleted', 'synch_masters_deleted'])) {
			$Res = $this->Superadmin_model->getSynchData('sma_deleted_data', $action);
			
			if(!empty($Res)){
				foreach ($Res as $key => $values) {
					$tableNameData = ($values->table_name == 'sma_users') ? 'sma_pos_users' : $values->table_name;
					if($tableNameData!='sma_costing' && $action=='synch_masters_deleted'){
						if( in_array($tableNameData , ['sma_products', 'sma_product_variants', 'sma_categories', 'sma_brands', 'sma_companies', 'sma_customer_groups', 'sma_warehouses', 'sma_variants', 'sma_units', 'sma_users', 'sma_customer_groups', 'sma_combo_items']))
							$data['data'][$tableNameData][] = $values->deleted_id;
					}
					if($tableNameData!='sma_costing' && $action=='synch_updates_deleted'){
						if( in_array($tableNameData , ['sma_sales', 'sma_sale_items', 'sma_warehouses_products', 'sma_warehouses_products_variants', 'sma_purchases', 'sma_purchase_items', 'sma_payments']))
						$data['data'][$tableNameData][] = $values->deleted_id;
					}
				}
				if($data){
					$data['status'] = "SUCCESS";
				}
				return $data;
			}
		}else{
			$tables = $this->Superadmin_model->getSynchTables($action);
			if(is_array($tables)) {
				foreach ($tables as $key => $tableName) {                
					$tableNameData = ($tableName == 'sma_users') ? 'sma_pos_users' : $tableName;
					$data['data'][$tableNameData] = $this->Superadmin_model->getSynchData($tableName, $action);
				}
				
				if($data){
					$data['status'] = "SUCCESS";
				}
				
				return $data;
			}
		}
        return false;
    }
    private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }       


   
   /**
     * Get Database Details
     */
    public function getposdata(){
        $response = [
            'status' => 'SUCCESS',
            'data' => [
                'posuser' => $this->db->username,
                'postoken' => $this->db->password,
                'posdb' => $this->db->database,
            ],
        ];
        $this->json_op($response);
    }
    


   /**
     * Sales Transaction
     */
    public function transactions(){

        $branchId = ($this->input->post('warehouseCode'))?$this->input->post('warehouseCode'):NULL; 
        $startdate = ($this->input->post('startdate'))?$this->input->post('startdate'):NULL; 
        $enddate = ($this->input->post('enddate'))?$this->input->post('enddate'):NULL; 
        
        if($branchId){
            $wherehouse = $this->db->select('id')->where(['code' => $branchId])->get('sma_warehouses')->row();
            if($this->db->affected_rows()){
                $branchId = $wherehouse->id;
            }else{
                $data['status'] = 'ERROR';
                $data['error_code'] = 404;
                $data['mag'] = 'Invalid Warehouse Code';
                echo $this->json_op($data);
                exit;
            }
        }
        
        $response =   $this->Superadmin_model->getTransaction($branchId, $startdate, $enddate);
 
        $this->json_op($response);
    }

     /****************************************************
     *  Sales Notofication
     ****************************************************/
    /**
     * Add New Sales Request Notification 
     */
    public function salesNotification(){
       
        $response = array();
        if($_POST){
              $data = [
                'request_pos_url' => $this->input->post('requestSendURL'),
                'sales_id'        => $this->input->post('sales_id'),
                'invoice_no'      => $this->input->post('invoice_no'),
                'reference_no'    =>  $this->input->post('reference_no'), 
               // 'items'           =>  $this->input->post('items'),
                'biller_id'           =>  $this->input->post('biller_id'),
                'biller'           =>  $this->input->post('biller'),
                'is_status'       => '1',
                'created_at'      => date('Y-m-d h:i:s')
              ];
            
            if($this->Api4_model->add_Sales_Request_Notification($data)){
                $response = ['status' => 'SUCCESS']; 
            }else{
               $response = [
                    'status' => 'ERROR',
                    'error_code'    => 404,                
                ]; 
            }
            
        } else{
            $response = [
                'status' => 'ERROR',
                'error_code'    => 404,
                'mag'       => 'Invalid Request',
            ];
        }

        $this->json_op($response);
        exit;
    }
    
    
    
    /**
     * Get Purchases
     */
    public function getpurchases(){
       $salesId = $this->input->post('salesId');
       $data = $this->Api4_model->getSalesDetails($salesId);
        if($data){
          $response = [
              'status' => 'SUCCESS',
              'error_code'    => 200,
              'data' => serialize($data)
           ];  
        }else{
          $response = [
                'status' => 'ERROR',
                'error_code'    => 404,
                'mag'       => 'Invalid Request',
            ];  
        }
      
        
       
       $this->json_op($response);
       exit;
    }
    
    
    public function getProductDetails(){
        $barcodes = $this->input->post('barcodes');
       $products = $this->Api4_model->getProdutsDetails($barcodes);
       if($products){
           $response = [
               'status' => 'SUCCESS',
               'error_code'    => 200,
               'data' => serialize($products)
           ];
           
       }else{
            $response = [
                'status' => 'ERROR',
                'error_code'    => 404,
                'mag'       => 'Invalid Request',
            ]; 
       }
       $this->json_op($response);
       exit;
    }
    

     /**
     * Set Supplier Private Key
     */
    public function setSupplierKey(){
      
        $supplierName = $this->input->post('suppliername');
        $supplierkey  = $this->input->post('supplierKey');
        
         $data = [
                'name' => $supplierName,
                'privatekey' => $supplierkey,
                'customer_url' => $this->input->post('supplierURL')
             ];
        
        $result = $this->Api4_model->add_SupplierKey($supplierName, serialize($data));
        
        if($result){
            $response = [
               'status' => 'SUCCESS',              
           ];
        }else{
           $response = [
                'status' => 'ERROR',
                'error_code'    => 404,
                'mag'       => 'Invalid Request',
            ];  
        }
        $this->json_op($response);
        exit;        
    }

    /**
    /**
     * Purchase Notification
     * @param type $sale_id
     * @return type
     */
   public function getPurchaseItems(){
      $salesId =  $this->input->post('salesId');
      $data =  $this->Api4_model->getSalesItemsList($salesId);
      if($data){
            $tabledata = '';
             foreach($data  as $key => $items){
                $tabledata.='<tr>';
                 $tabledata.='<td>'.($key+1) .'</td>' ;
                  $tabledata.='<td>'. $items['product_code'] .'</td>' ;
                  $tabledata.='<td>'. $items['product_name'] .'</td>' ;
                  $tabledata.='<td>'. round($items['quantity'],2) .'</td>' ;
               $tabledata.='</tr>';

            }

          $response = [
              'status' => 'SUCCESS',
              'error_code'    => 200,
              'data' => $tabledata 
           ];  
        }else{
          $response = [
                'status' => 'ERROR',
                'error_code'    => 404,
                'mag'       => 'Invalid Request',
            ];  
        }
      
        
       
       $this->json_op($response);
       exit;
       
   }
    

     /****************************************************
     * End  Sales Notofication
     ****************************************************/
    

     /**
     * Transfer Records
     */
    public function transfer(){
       $transferData = $this->Api4_model->getTransferData();
       if($transferData){
          echo json_encode($transferData); 
       }else{
          $response= ['status' => 400,
                       'message' => 'Records not founds' 
                        ];
          echo json_encode($response);
       }
    }


  
}

?>