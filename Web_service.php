<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class web_service extends MY_Controller {
	
	
	public function action(){
		$keytype = $_POST['action'];
		unset($_POST['action']);
		switch($keytype){
		    case 'Product_add':
		    			
		    		$product_image = time().".png";
		    		$image = $this->input->post('Product_Image');
		    		$path = "assets/mdata/$this->Customer_assets/uploads/".$product_image;
		    		file_put_contents($path,base64_decode($image));
		    					    	       
		           $field_data =array(
		           	'code' => $this->input->post('code'),
		           	'article_code'=> $this->input->post('article_code'),
		           	'name'=> $this->input->post('name'),
		           	'hsn_code' => $this->input->post('hsn_code'),
		           	'cost' => $this->input->post('cost'),
		           	'price' => $this->input->post('price'),
		           	'mrp' => $this->input->post('mrp'),
		           	'alert_quantity' => $this->input->post('alert_quantity'),
		           	'type' => $this->input->post('type'),
		           	'image' =>$product_image ,
		           );
		    	  
		    	  $this->db->insert('sma_products',$field_data);
		    	  if($this->db->affected_rows()>0){
		    	      file_put_contents($path,base64_decode($image));
		    	  	$msg['status'] ="success";
		    	  	$msg['msg'] = "Product Save Successfuly...!";
		    	  }else{
		    	  	$msg['status'] ="success";
		    	  	$msg['msg'] = "Product Not Save Please Try Again.";
		    	  }
		    
		  	   echo json_encode($msg);     
		  	break;
			
		
		}
		
	
		           	
 		
	}
	
	public function testing(){
	
		$jsonString = file_get_contents("php://input");
$phpObject = json_decode($jsonString);

$newJsonString = json_encode($phpObject);
header('Content-Type: application/json');
//echo $newJsonString;

	print_r($phpObject->order);
		
	}
	
	
	
	
}