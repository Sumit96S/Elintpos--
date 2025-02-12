<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Cron_job extends MY_Controller {
 
 
    function __construct() {
        parent::__construct();
      
    }
    
    public function index(){
       $curl = curl_init();
        $apiRequestUrl = base_url().'cron_job/getData';
        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiRequestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $requsetData,
        ));

         $response = curl_exec($curl);
         if(curl_errno($curl)){
          echo 'Request Error:' . curl_error($curl);
         }else{
           print_r($response);
         }
    }
    
    
   public function getData(){
      $data =  $this->db->select('id, code, name')->get('sma_products')->result();
     echo json_encode($data);
   }
 
    
}