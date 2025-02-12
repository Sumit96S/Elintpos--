<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Sendsmsemail extends MY_Controller
{

    function __construct(){
        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }

       /* if (!$this->Owner && !$this->Admin) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }*/
         $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->load->library('form_validation');
         $this->load->library('sma');
        $this->load->library('upload');
           $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
    }

    function index()
    { 
       /* if (!$this->Owner && !$this->Admin) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }*/

        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $current_user =   $this->site->getUser( $this->session->userdata('id'));
        $this->data['default_email'] =  $current_user->email ;
        $this->data['sms_limit']     =  $this->sma->BalanceSMS();
        
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('notifications')));
        $meta = array('page_title' => lang('notifications'), 'bc' => $bc);
        $this->page_construct('sendsmsemail/index', $meta, $this->data);
    }
    
    function coustmer_notification()
    {
   /*
      if (!$this->Owner && !$this->Admin) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
*/
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $current_user =   $this->site->getUser( $this->session->userdata('id'));
        $this->data['default_email'] =  $current_user->email ;
        $this->data['sms_limit']     =  $this->sma->BalanceSMS();
        
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('notifications')));
        $meta = array('page_title' => lang('notifications'), 'bc' => $bc);
        $this->page_construct('sendsmsemail/coustmer_notification', $meta, $this->data);
    }
 
    
    function add(){ 
       
        $this->form_validation->set_rules('message', 'Message ', 'required');
        $this->form_validation->set_rules('subject', 'subject', 'required');
        $this->form_validation->set_rules('hiddencust', lang("Customer List"), 'required');
        $this->form_validation->set_rules('cmbtype[]', lang("Type "), 'required');
        $file = '';
        if ($this->form_validation->run() == true) {
            
               if ($_FILES['image']['size'] > 0) {
                    $config['upload_path'] = $this->upload_path;  
                    $config['overwrite'] = true;
                    $config['encrypt_name'] = TRUE;
                    $config['max_filename'] = 25;
                    $config['allowed_types'] = $this->digital_file_types;
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('image')) {
                          $error = $this->upload->display_errors(); 
                    }else{
                         $file = $this->upload->file_name; 
                    }
                }  
                
            $data = array(
                'sender' => $this->input->post('sender'),
                'subject' => $this->input->post('subject') ,
                'hiddencust' => $this->input->post('hiddencust'),
                'unicode' => $this->input->post('unicode'),
                'cmbtype' => $this->input->post('cmbtype'), 
                'msgtype' => $this->input->post('msgtype'), 
            ); 
                
            $client_arr = @explode(',',$data['hiddencust']);
            $mobile_arr = $email_arr = array();
            
            foreach ($client_arr as  $client_data):
                $_arr =  @explode(':',$client_data);
                //----------Mobile--------------
                if(isset($_arr[0]) && !empty($_arr[0])):
                    $mobile_arr[]= $_arr[0];
                endif;
                
                //----------Email--------------
                if(isset($_arr[1]) && !empty($_arr[1])):
                    $email_arr[]= $_arr[1];
                endif;
            endforeach;
            $content = $this->input->post('message');
            $attachment  = !empty($file)?base_url($this->upload_path.$file):NULL;
            $attachment1 = !empty($file)? $this->upload_path.$file:NULL; 
            $_type = $this->input->post('cmbtype');
            $ci = get_instance();
	    $config = $ci->config;
	    $_merchant_phone = isset($config->config['merchant_phone'])?$config->config['merchant_phone']:'';
       		 
            if(is_array($_type)):
                if(in_array('email',$_type)):
                    $this->SendEmail($email_arr,$data['subject'],$content,$attachment1,$data['sender']) ;
                endif;
            endif;
            
            if(is_array($_type) ):
                if(in_array('sms',$_type)):
                    $this->SendSms($mobile_arr,$content);
                endif;
            endif;
            
            if(is_array($_type) && !empty($_merchant_phone)):
                if(in_array('push_message',$_type)):

                	    $_postArr = array();
		            $_postArr['action']     = 'messageInsert';
		            $_postArr['sender']     = $_merchant_phone;
		            $_postArr['subject']    = $data['subject'];
		            $_postArr['attachment'] = $attachment;
		            $_postArr['message']    = $content;
		            
		            if(is_array($mobile_arr) && count($mobile_arr) >0):
		                $_postArr['receiver']       = implode(',',$mobile_arr);
		            endif;
		            
		            if(is_array($email_arr) && count($email_arr) >0):
		                $_postArr['receiver_email']   = implode(',',$email_arr);
		            endif;
		            
		            $_postArr['receiver1']  = $data['hiddencust'];
		            $_postArr['msgtype']    = $data['msgtype'];
		            $_postArr['refrer']     = base_url(); 
		            $_postArr['type']       = @implode(',', $_type);
		            $_postArr['unicode']    = $this->input->post('unicode');
		            
		            $res_api =  $this->CallAPI('POST', 'https://simplypos.in/api/api-message.php', $_postArr);     
		            if(!empty( $res_api))  :
		            	 $_jsonObj = json_decode($res_api);
		            	 if($_jsonObj->status =='error'):
		            	 	$this->session->set_flashdata('error', 'Message not Send Successfully');
            				redirect(base_url('sendsmsemail').'?msg=fail');
		            	 endif;
		            endif;
		           
		            
           
            	endif;
            endif;
            $re_url = base_url('sendsmsemail').'?msg=done';
            $error = $this->session->flashdata('error');
            if(empty($error)):
                $this->session->set_flashdata('success', 'Message Send Successfully');
            else:
               $re_url = base_url('sendsmsemail').'?msg=not_done';
            endif;
            
            redirect($re_url);
        } else  {
            $this->session->set_flashdata('error', validation_errors());
            redirect("Sendsmsemail");
        } 
    }
    
    private function SendEmail($emails,$subject,$content,$attachment,$sender){
    	if(empty($emails) || empty($content)):
            return false;
        endif;
        
        $tpl = $this->EmailTemplate();        
        $content = str_replace('[MSGBODY]', $content, $tpl);
       
        foreach ($emails as $email) {
            $this->sma->send_email($email,$subject,$content ,$sender,NULL,$attachment);
        }
    }
 
    private function SendEmailNew($emails,$subject,$content,$attachment,$sender) {
        if(empty($emails) || empty($content)):
            return false;
        endif;
        $tpl = $this->EmailTemplate();        
        $content = str_replace('[MSGBODY]', $content, $tpl);
        $this->load->library('email');
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
        
        if(is_array($emails) && count($emails) > 0):
            $this->email->subject($subject);
            $this->email->message($content);
            $this->email->attach($attachment);
            $this->email->from($sender,'');
            foreach ($emails as $email) {
                $this->email->to($email);
                $this->email->send();
            }
        endif;
    }
    
    /*
    private function SendSms($mobiles,$content) {
        if(empty($mobiles) || empty($content)):
            return false;
        endif;
        $content = strip_tags($content);
       
        $user = "simplysafe";
        $password = "Simplysafe1$$";
      
        $sid = "SIMPLY";
        $msg =  $content ;  
        $fl = 0;
        $gwid = 2;   
        $data['gwid'] = $gwid; 
        if(is_array($mobiles) && count($mobiles) > 0):
            foreach ($mobiles as $mobile) :
                $this->CallSMS($mobile,$msg);
            endforeach;
        endif;
    }
   */
    
    private function SendSms($mobiles,$content) {
        if(empty($mobiles) || empty($content)):
            return false;
        endif;
        $msg =  strip_tags($content);
        
        if(is_array($mobiles) && count($mobiles) > 0):
            foreach ($mobiles as $mobile) :
                $this->CallSMS($mobile,$msg);
            endforeach;
        endif;
    }
    
    private function EmailTemplate(){
        $html ='Dear Customer,[MSGBODY] Thanks ';
        return $html;        
    }
    
    function CallSMS($mobile,$msg){
         $res =  $this->sma->SendSMS($mobile,$msg);
      
	 if(!empty($res)):
            $Obj = json_decode($res);
            if(isset($Obj) && $Obj->type == 'error'):
                $this->session->set_flashdata('error', $Obj->message);
            endif;
	 else:
            $this->session->set_flashdata('error', 'SMS not send successfully to '.$mobile);
	 endif;
    } 
    
    
    
    function CallAPI($method, $url, $data = false) {
        $curl = curl_init();

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
    
    function post_to_url($url, $data) {
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
}
