<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Smsdashboard extends MY_Controller
{
    private $mobileArr = array();
    private $emailArr  = array();
    function __construct(){
        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
       /*   if (!$this->Owner && !$this->Admin) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }*/
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->load->library('form_validation');
        $this->load->library('sma');
        $this->load->library('upload');
        
        $this->load->model('companies_model');
        $this->load->model('event_model');
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
    }
            
 
    private function SendEmail($emails,$subject,$content,$attachment,$sender) {
        if(empty($emails) || empty($content)):
            return false;
        endif;
        foreach ($emails as $email) {
           $res =  $this->sma->send_email($email, $subject, $content, $from = null, $from_name = null, $attachment, $cc = null, $bcc = null) ;
           if($res===false):
                 $this->session->set_flashdata('error', 'Email not send successfully to '.$email);
           endif;
        }
    }
    
    private function SendSms($mobiles, $content, $sms_header=null, $DLT_TE_ID=null) {
        if(empty($mobiles) || empty($content)):
            return false;
        endif; 
        $content = strip_tags($content);
        if(is_array($mobiles) && count($mobiles) > 0):
            foreach ($mobiles as $mobile) : 
                $this->CallSMS($mobile,$content, $sms_header, $DLT_TE_ID);
            endforeach;
        endif;
    }
      
    function CallSMS($mobile,$msg, $sms_header=null, $DLT_TE_ID=null){
        if(strpos($msg,'{client_name}')){
            if(isset($this->mobileArr[$mobile])){ 
             $msg = str_replace('{client_name}', $this->mobileArr[$mobile], $msg);
            } else {
             $client = $this->site->customerName(array('phone'=>$mobile)); 
             $msg = str_replace('{client_name}', $client->name, $msg);
            }//end else
        }//end else
        
        $res =  $this->sma->SendCrmSMS($mobile, $msg, $sms_header, $DLT_TE_ID);
        
	if(!empty($res)):
            $Obj = json_decode($res);
       
            if(isset($Obj) && $Obj->type=='success') {
                $rec['status']  = 'success';
                $rec['msg']  = 'SMS send successfully to '.$mobile;   
                $rec['sms_log'] = $this->sma->setSMSLog($mobile,$msg,$Obj->message);
                $this->session->set_flashdata('success', 'SMS send successfully to '.$mobile);                
            } else {
                $rec['status']  = 'error';
                $rec['mobile']  = $mobile;
                $rec['msg']  = $Obj->message;   
                $this->session->set_flashdata('error', 'SMS not send successfully to '.$mobile);
            }
	else:
            $rec['status']  = 'error';            
            $rec['msg']  = 'SMS not send successfully to '.$mobile;            
            $this->session->set_flashdata('error', 'SMS not send successfully to '.$mobile);
	endif;
        return $rec;
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
    
    function index(){
   /*
      if (!$this->Owner && !$this->Admin) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
     */       
     	$res = $this->event_model->getAllCustomerFromEvent();
      
        $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
        $current_user =   $this->site->getUser( $this->session->userdata('id'));
        $this->data['groupList'] =  $this->site->getAllContactGroup();
        
        $this->data['templateList']  =  $this->site->getAllContactTemplate();
      
        $this->data['default_email'] =  $current_user->email ;
        $this->data['sms_limit']     =  $this->sma->BalanceSMS();
        $this->data['notification']     =  $res ;
        $this->data['customer'] = $this->companies_model->getAllCustomerCompanies();
        $this->data['customerlist'] = $this->companies_model->getSMSCustomerList();
	$resCron= $this->sma->SmsCron(1,1);
	 
	$this->data['pos_sms_cron'] =  $resCron->pos_sms_cron ;
   	$this->data['pos_sms_cron_type'] =  $resCron->pos_sms_cron_type ;
   	
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('notifications')));
        $meta = array('page_title' => lang('notifications'), 'bc' => $bc);
        $this->page_construct('smsdashboard/index', $meta, $this->data);
    }
    
    function groupAdd(){
        
        $this->form_validation->set_rules('group_name', lang("Group Name"), 'required');      
        
        if ($this->form_validation->run('smsdashboard/groupAdd') == true) :
            $member = $this->input->post('group_mem');
            
            if(count($member)==0):
                exit(json_encode(array('error' => 'Please select group member')));
            endif;
           /* if(count($member) > 20):
                exit(json_encode(array('error' => 'Not allow more then 20 members')));
            endif;*/
            $data = array( 
               'group_name'=>$this->input->post('group_name'),
               'group_desc'=>$this->input->post('group_desc'),
               'group_created'=>date("Y-m-d H:i:s"),
              );
        elseif ($this->input->post('add_group')):
             exit(json_encode(array('error' => validation_errors())));
        endif;
        
       
        
        if ($this->form_validation->run() == true && $gid = $this->site->addContactGroup($data)) {
            if($gid && is_array($member)):
                foreach ($member as $memberId) {
                    $this->site->addContactGroupMember(array('group_id'=>$gid,'customer_id'=>$memberId));
                }
                 exit(json_encode(array('msg' => 'Group is created & member are added successfully . ')));
            endif;
               exit(json_encode(array('error' => 'Group is created but member are not added successfully . ')));
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['customer'] = $this->companies_model->getSMSCustomerList(); 
            $this->load->view($this->theme . 'smsdashboard/group/add', $this->data);
        }
    }
    
    function groupEdit() {
        $group_id = $this->input->get('group_id');
        if (empty($group_id)):
            exit(json_encode(array('error' => 'Group ID  missing')));
        endif;
        $this->form_validation->set_rules('group_name', lang("Group Name"), 'required');

        if ($this->form_validation->run('smsdashboard/groupEdit') == true) :

            $member = $this->input->post('group_mem');
            if (count($member) == 0):
                exit(json_encode(array('error' => 'Please select group member')));
            endif;
            
            /*if(count($member) > 20):
                exit(json_encode(array('error' => 'Not allow more then 20 members')));
            endif;*/
            
            $data = array(
                'group_name' => $this->input->post('group_name'),
                'group_desc' => $this->input->post('group_desc'),
                'group_created' => date("Y-m-d H:i:s"),
            );
        elseif ($this->input->post('edit_group')):
           
            exit(json_encode(array('error' => validation_errors())));
        endif;

        if ($this->form_validation->run() == true && $gid = $this->site->updateContactGroup($group_id, $data)) {
            if ($group_id && is_array($member)):
                $this->site->deleteContactGroupMember($group_id);
                foreach ($member as $memberId) {
                    $this->site->addContactGroupMember(array('group_id' => $group_id, 'customer_id' => $memberId));
                }
                exit(json_encode(array('msg' => 'Group is updated successfully . ')));
            endif;
            exit(json_encode(array('error' => 'Group is updated but member are not added successfully . ')));
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['group'] = $this->site->getAllContactGroupByID($group_id);
            $members = $this->site->getAllContactGroupMemberDetails($group_id);
            $_member = array();
            $_member_name = array();
            foreach ($members as $memberData) {
                $_member[] = $memberData->id ;
                $_member_name[] = $memberData->name ;
            }
            $this->data['members_name'] = $_member_name;
            $this->data['members'] = $_member;

            $this->data['customer'] = $this->companies_model->getAllCustomerCompanies();
            $this->load->view($this->theme . 'smsdashboard/group/edit', $this->data);
        }
    }
     
    function groupDelete(){
        $group_id = $this->input->get('group_id');
        if (empty($group_id)):
            exit(json_encode(array('error' => 'Group ID  missing')));
        endif;
        $this->form_validation->set_rules('group_action', lang("Delete action"), 'required');

        if ($this->form_validation->run('smsdashboard/groupDelete') == true) :
            
        elseif ($this->input->post('group_action')):
            
            exit(json_encode(array('error' => validation_errors())));
        endif;

        if ($this->form_validation->run() == true && $gid = $this->site->deleteContactGroup($group_id)) {
            exit(json_encode(array('msg' => 'Group is deleted successfully . '))); 
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['group'] = $this->site->getAllContactGroupByID($group_id);
            $this->load->view($this->theme . 'smsdashboard/group/delete', $this->data);
        }
    }
    
    function group_list_grid(){
        $g_class_arr = array('bblue' ,'bdarkGreen' ,'borange' ,'blightOrange');
        $group_count = 0 ;
        $res = $this->site->getAllContactGroup();
        if($res):
            $group_count = count($res);
        endif;
        $GroupGrid = $this->sma->GroupGrid($res, $g_class_arr);
        $arr = array();
        $arr['res'] = $GroupGrid;
        $arr['group_count'] = $group_count;
        echo json_encode($arr);
    }
    
    function groupList(){
        $groupList =  $this->site->getAllContactGroup();
        $tableHeader ='<tr>
                         <th width="10%">#ID</th>
                         <th>Group Name</th> 
                         <th  width="25%">Total No. members</th> 
                         <th width="30%">Action</th>
                     </tr>';
         $tableB =  '';
        foreach ($groupList as $key => $groupData) : 
        $count  =   $this->sma->contact_member_count($groupData->id);
        $tableB =   $tableB.'<tr>
                        <td>#'.$groupData->id.' </td>
                        <td>'.$groupData->group_name.' </td>
                        <td class="text-center">'.$count.' </td>
                        <td>
                            <a href="javascript:void(0);" data-toggle="modal" data-target="#groupModel" class="edit_group"  data-type="edit" data-value="'.$groupData->id.'" Title="Manage '.$groupData->group_name.'  ">
                                <i class="fa fa-pencil" aria-hidden="true"></i> 
                            </a>&nbsp;
                             <a href="javascript:void(0);" data-toggle="modal" data-target="#groupModel" class="edit_group" data-type="del" data-value="'.$groupData->id.'" Title="Delete '.$groupData->group_name.'  ">
                                <i class="fa fa-trash-o" aria-hidden="true"></i>
                            </a>&nbsp;
                        </td>
                    </tr>'; 
        endforeach; 
        if(empty($tableB)){
            $tableB = '<tr><td colspan="4">No Data Found</th> </tr>';
        }   
        $table = '<table class="table table-hover"><tbody>'.$tableHeader.$tableB.'</tbody></table>';
        $arr = array();
        $arr['res'] = $table;
       echo json_encode($arr);
    }
        
    function templateAdd(){
            
        $this->form_validation->set_rules('template_name', lang("template Name"), 'required');      
        $this->form_validation->set_rules('template_type', lang("template Type"), 'required|numeric');      
        $this->form_validation->set_rules('template_content', lang("template Name"), 'required');      
        
        if($this->input->post('template_type') == 1){
            $this->form_validation->set_rules('template_key', lang("Template Key"), 'trim|required');  
            $this->form_validation->set_rules('dlt_te_id', lang("template dlt_te_id"), 'trim|required');  
        }
        
        if ($this->form_validation->run('smsdashboard/templateAdd') == true) :
            
            if ($_FILES['attachment']['size'] > 0) { 
            $config['upload_path'] = $this->upload_path;  
            $config['overwrite'] = true;
            $config['encrypt_name'] = TRUE;
            $config['max_filename'] = 25;
            $config['allowed_types'] = $this->digital_file_types;
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('attachment')) {
                  $error = $this->upload->display_errors(); 
                   exit(json_encode(array('error' => $error)));   
            }else{
                 $file = $this->upload->file_name; 
                 //chmod($this->upload_path.$file,0777); 
            }
        }  
        $attachment1 =    !empty($file)?$this->upload_path.$file:'';
             $template_key = $dlt_te_id = '';
             if($this->input->post('template_type') == 1){
                $template_key = $this->input->post('template_key');
                $dlt_te_id = $this->input->post('dlt_te_id');
             }
            
            $data = array( 
               'template_name'      =>  $this->input->post('template_name'),
               'template_type'      =>  $this->input->post('template_type'),
               'template_content'   =>  $this->input->post('template_content'),
               'template_key'       =>  $template_key,
               'dlt_te_id'          =>  $dlt_te_id,
               'template_created'   =>  date("Y-m-d H:i:s"),
               'attachment'         =>  $attachment1
              );
        elseif ($this->input->post('add_template')):
            exit(json_encode(array('error' => validation_errors())));
        endif;
        
       
        
        if ($this->form_validation->run() == true && $gid = $this->site->addContactTemplate($data)) {
            exit(json_encode(array('msg' => 'Template is created   successfully . ')));
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['templateType'] = $this->sma->conatctTemplateType();  
            $this->load->view($this->theme . 'smsdashboard/template/add', $this->data);
        }
    }
    
    function templateEdit() { 
        $template_id = $this->input->get('template_id');
        if (empty($template_id)):
            exit(json_encode(array('error' => 'template ID  missing')));
        endif;
        
        $this->form_validation->set_rules('template_name', lang("template Name"), 'required');
        $this->form_validation->set_rules('template_type', lang("template Type"), 'required|numeric');      
        $this->form_validation->set_rules('template_content', lang("template Name"), 'required');      

        if($this->input->post('template_type') == 1){
            $this->form_validation->set_rules('template_key', lang("Template Key"), 'trim|required');  
            $this->form_validation->set_rules('dlt_te_id', lang("template dlt_te_id"), 'trim|required');  
        }
        
        if ($this->form_validation->run('smsdashboard/templateEdit') == true) :
            
            if ($_FILES['attachment']['size'] > 0) { 
            $config['upload_path'] = $this->upload_path;  
            $config['overwrite'] = true;
            $config['encrypt_name'] = TRUE;
            $config['max_filename'] = 25;
            $config['allowed_types'] = $this->digital_file_types;
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('attachment')) {
                  $error = $this->upload->display_errors(); 
                   exit(json_encode(array('error' => $error)));   
            }else{
                 $file = $this->upload->file_name; 
                 //chmod($this->upload_path.$file,0777); 
            }
            }
            
              $attachment1 =    !empty($file)?$this->upload_path.$file:'';
            $template_key = $dlt_te_id = '';
            if($this->input->post('template_type') == 1){
               $template_key = $this->input->post('template_key');
               $dlt_te_id = $this->input->post('dlt_te_id');
            }
            
            $data = array(
               'template_name'      =>  $this->input->post('template_name'),
               'template_type'      =>  $this->input->post('template_type'),
               'template_content'   =>  $this->input->post('template_content'),
               'template_key'       =>  $template_key,
               'dlt_te_id'          =>  $dlt_te_id,
               'template_updated'   =>  date("Y-m-d H:i:s"),              
            );
            
            !empty($attachment1 ) ? $data['attachment'] = $attachment1:'';
            
        elseif ($this->input->post('edit_template')):
            exit(json_encode(array('error' => validation_errors())));
        endif;

        if ($this->form_validation->run() == true && $gid = $this->site->updateContactTemplate($template_id, $data)) {
           exit(json_encode(array('msg' => 'template is updated  successfully . ')));
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['template'] = $template = $this->site->getContactTemplateByID($template_id);
            $t_type =  ( $template->is_default==1 ) ? $template->template_type : NULL;
            
            $this->data['templateType'] = $this->sma->conatctTemplateType($t_type);  
            $this->load->view($this->theme . 'smsdashboard/template/edit', $this->data);
        }
    }
     
    function templateDelete(){
        $template_id = $this->input->get('template_id');
        if (empty($template_id)):
            exit(json_encode(array('error' => 'template ID  missing')));
        endif;
        $this->form_validation->set_rules('template_action', lang("Delete action"), 'required');
        if ($this->form_validation->run('smsdashboard/templateDelete') == true) :
            
        elseif ($this->input->post('template_action')):
             exit(json_encode(array('error' => validation_errors())));
        endif;

        if ($this->form_validation->run() == true && $gid = $this->site->deleteContactTemplate($template_id)) {
            exit(json_encode(array('msg' => 'template is deleted successfully . '))); 
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error')); 
            $this->data['template'] = $this->site->getContactTemplateByID($template_id);
            $this->load->view($this->theme . 'smsdashboard/template/delete', $this->data);
        }
    }
    
    function template_list_grid(){
        $templateList =  $this->site->getAllContactTemplate(); 
        $arr = array();
        $arr['res_1'] =$this->sma->TemplateList($templateList, 1);
        $arr['res_2'] =$this->sma->TemplateList($templateList, 2);
        $arr['res_3'] =$this->sma->TemplateList($templateList, 3);
        echo json_encode($arr);
    }
    
    function template_details(){
        $template_id = $this->input->get('template_id');
        if (empty($template_id)):
            exit(json_encode(array('error' => 'template ID  missing')));
        endif;
        $obj = $this->site->getContactTemplateByID($template_id);
        $arr = array();
               
        $arr['res_type']   	   = $obj->template_type;     
        $arr['res_content'] 	   = ( $arr['res_type']==2)?@nl2br($obj->template_content):$obj->template_content;
        $arr['res_subject'] 	   = $obj->template_name;          
        $arr['res_content_length'] = @strlen($obj->template_content);   
        $arr['attachment']         = $obj->attachment; 
        $arr['attachment_op']      = !empty($obj->attachment)?'<a href="'.base_url($obj->attachment).'" target="_blank"> <i class="fa fa-paperclip fa-lg" aria-hidden="true"></i> Attachment</a>':'';    
        
        echo json_encode($arr);
    }
    
    function templateList(){
    
	$is_default = $this->input->get('is_default');
	$is_default = ($is_default==1)?1:0;	
        $templateModel = ($is_default==1)?'template_d_Model':'templateModel';	
        $template   =  $this->site->getAllContactTemplate(NULL,$is_default);
        
        $typeArr = array(1=>'SMS',2=>'Email',3=>'Application Message');
        $tableHeader ='<tr>
                         <th>#ID</th>
                         <th>Template Name</th> 
                         <th>Template Type</th> 
                         <th>DLT_TE_ID</th> 
                         <th>Action</th>
                     </tr>';
         $tableB =  '';
        foreach ($template as $key => $templateData) :
        $tableB =   $tableB.'<tr>
                        <td>#'.$templateData->id.'</td>
                        <td>'.$templateData->template_name.'</td> 
                        <td>'.$typeArr[$templateData->template_type].'</td> 
                        <td>'.$templateData->dlt_te_id.'</td> 
                        <td> 
                            <a href="javascript:void(0);" data-toggle="modal" data-target="#'.$templateModel.'" class="edit_group"  data-type="edit" data-value="'.$templateData->id.'" Title="Manage '.$templateData->template_name.'  ">
                                <i class="fa fa-pencil" aria-hidden="true"></i> 
                            </a>&nbsp;';
                
                   if($is_default==0):        
        $tableB =   $tableB.'<a href="javascript:void(0);" data-toggle="modal" data-target="#'.$templateModel.'" class="edit_group" data-type="del" data-value="'.$templateData->id.'" Title="Delete '.$templateData->template_name.'  ">
                                <i class="fa fa-trash-o" aria-hidden="true"></i></a>';
                     endif;           
                                
         $tableB =   $tableB.'&nbsp;
                        </td>
                    </tr>'; 
        endforeach; 
        if(empty($tableB)){
            $tableB = '<tr><td colspan="4">No Data Found</th> </tr>';
        }   
        $table = '<table class="table table-hover"><tbody>'.$tableHeader.$tableB.'</tbody></table>';
        $arr = array();
        $arr['res'] = $table;
       echo json_encode($arr);
    }
    
    
    /*------------- Add single Sms---------------*/
    function addSingleSMS(){ 
        $getstorename = $this->db->select('site_name')->where('setting_id','1')->get('sma_settings')->row();
        $this->form_validation->set_rules('hiddencust_sms', lang("Customer Contact"), 'required');
        $this->form_validation->set_rules('sms_body', lang("Message"), 'required'); 
        $this->form_validation->set_rules('sms_promotional_header', lang("Promotional SMS Header"), 'trim|required');
       //$this->form_validation->set_rules('sms_template_key', lang("SMS Template Key"), 'trim|required');
        $this->form_validation->set_rules('dlt_te_id', lang("SMS dlt_te_id"), 'trim|required');
         
        $file = '';
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        $contactArr = $this->ContactParse($this->input->post('hiddencust_sms'));
        $content =$this->input->post('sms_body');
        $sms_length = $this->input->post('sms_length'); 
        $mobile = isset($contactArr['mobile']) && is_array($contactArr['mobile'])?$contactArr['mobile']:''; 
        if(!empty($mobile)){
             $smscount = count($mobile); 
            
            /*//$result = $this->CallSMS($contactArr['mobile'][0],$content);
            
            if(!empty($result['status']=='error')):
                unset($_SESSION['error']);
                $this->session->set_flashdata('error', $result['msg']); 
                $this->session->mark_as_flash('error');               
                exit(json_encode(array('error' => $error)));   
            elseif(!empty($result['status']=='success')):            
                $rec['status'] = $result['status'];
                $rec['success'] = $result['msg'];
                $rec['sms_log'] = $result['sms_log'];
                $rec['sms_balance_update'] = $this->sma->update_sms_count(($smscount * $sms_length));
                $rec['sms_count'] = $this->sma->BalanceSMS();
                $this->session->set_flashdata('success', 'SMS send successfully to '.$mobile); 
                exit(json_encode($rec));   
            endif;*/

            $sms_header = $this->input->post('sms_promotional_header'); 

            $sms_template_key = null; //$this->input->post('sms_template_key');     
            $DLT_TE_ID = $this->input->post('dlt_te_id');     

            $this->SendSms($mobile, $content, $sms_header, $DLT_TE_ID);
           
            $error = $this->session->flashdata('error');

            if(!empty($error)):
                unset($_SESSION['error']);
                $this->session->unset_tempdata('error');
                exit(json_encode(array('error' => $error)));   
            else:
                $rec['sms_balance_update'] = $this->sma->update_sms_count(($smscount* $sms_length));
                exit(json_encode(array('success' => 'Sms send successfully', 'sms_count'=>$this->sma->BalanceSMS())));   
            endif;
            
        } else {
            $rec['status'] = 'error';
            $rec['error'] = 'Mobile no not found.';
            $rec['sms_count'] = $this->sma->BalanceSMS();
            $this->session->set_flashdata('error', 'Mobile no not found'); 
            exit(json_encode($rec));
        }            
    }
    
    /*------------- Add Group Sms---------------*/
    function addGroupSMS(){
        $this->form_validation->set_rules('group_id', lang("Customer Group"), 'required');
        $this->form_validation->set_rules('sms_promotional_header', lang("Promotional SMS Header"), 'trim|required');
      //  $this->form_validation->set_rules('sms_template_key', lang("SMS Template Key"), 'trim|required');
        $this->form_validation->set_rules('dlt_te_id', lang("SMS dlt_te_id"), 'trim|required');
        $this->form_validation->set_rules('sms_body', lang("Message"), 'required');  
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        $this->SetConatctFromGroup($this->input->post('group_id'));
        $content = $this->input->post('sms_body');
        $mobile_arr = is_array($this->mobileArr) && count($this->mobileArr) > 0 ? array_keys($this->mobileArr):array();
        $sms_length = $this->input->post('sms_length'); 
        if(count($mobile_arr)==0){
            exit(json_encode(array('error' => 'Unable to send SMS')));   
        } 
        $smscount = count($mobile_arr);  
        $sms_header = $this->input->post('sms_promotional_header'); 
        $sms_template_key = null; //$this->input->post('sms_template_key');
        $DLT_TE_ID = $this->input->post('dlt_te_id');
            
        $this->SendSms($mobile_arr, $content , $sms_header, $DLT_TE_ID);
        $error = $this->session->flashdata('error');
        
        if(!empty($error)):
            unset($_SESSION['error']);
            $this->session->unset_tempdata('error');
            exit(json_encode(array('error' => $error)));   
        else:
            $rec['sms_balance_update'] = $this->sma->update_sms_count(($smscount* $sms_length));
            exit(json_encode(array('success' => 'Sms send successfully', 'sms_count'=>$this->sma->BalanceSMS())));   
        endif;
        
    }
            



     
    /*------------- Award Point SMS --------------*/
    function addAwardSingleSMS(){ 
        $getstorename = $this->db->select('site_name')->where('setting_id','1')->get('sma_settings')->row();
        $this->form_validation->set_rules('hiddencust_sms', lang("Customer Contact"), 'required');
        $this->form_validation->set_rules('sms_promotional_header', lang("Promotional SMS Header"), 'trim|required');
       //$this->form_validation->set_rules('sms_template_key', lang("SMS Template Key"), 'trim|required');
//        $this->form_validation->set_rules('dlt_te_id', lang("SMS dlt_te_id"), 'trim|required');
         
        $file = '';
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        $contactArr = $this->ContactParse($this->input->post('hiddencust_sms'));
       
        
        $sms_length = $this->input->post('sms_length'); 
        $mobile = isset($contactArr['mobile']) && is_array($contactArr['mobile'])?$contactArr['mobile']:''; 
        if(!empty($mobile)){
            
            
             $smscount = count($mobile); 
            
             foreach($mobile as $customer_id){
                 $customerDetails =  $this->db->select('award_points,phone')->where(['id' =>$customer_id])->get('sma_companies')->row();
                 
               //  print_r($customerDetails);
                 $content = 'Dear Valued Loyal customer, Thank you for choosing Fantasy Bakery n Patisserie. Your total loyalty points are '.$customerDetails->award_points.' that you can redeem anytime.';

                 $sms_header = $this->input->post('sms_promotional_header'); 
                 $sms_template_key = null; //$this->input->post('sms_template_key');     
                 $DLT_TE_ID = $this->input->post('dlt_te_id');     

                  $this->SendSms([$customerDetails->phone], $content, $sms_header, $DLT_TE_ID);
           
            }

           
            $error = $this->session->flashdata('error');

            if(!empty($error)):
                unset($_SESSION['error']);
                $this->session->unset_tempdata('error');
                exit(json_encode(array('error' => $error)));   
            else:
                $rec['sms_balance_update'] = $this->sma->update_sms_count(($smscount* $sms_length));
                exit(json_encode(array('success' => 'Sms send successfully', 'sms_count'=>$this->sma->BalanceSMS())));   
            endif;
            
        } else {
            $rec['status'] = 'error';
            $rec['error'] = 'Mobile no not found.';
            $rec['sms_count'] = $this->sma->BalanceSMS();
            $this->session->set_flashdata('error', 'Mobile no not found'); 
            exit(json_encode($rec));
        }            
    }
    
    
    function addAwardGroupSMS(){
        $this->form_validation->set_rules('group_id', lang("Customer Group"), 'required');
        $this->form_validation->set_rules('sms_promotional_header', lang("Promotional SMS Header"), 'trim|required');
      //  $this->form_validation->set_rules('sms_template_key', lang("SMS Template Key"), 'trim|required');
        $this->form_validation->set_rules('dlt_te_id', lang("SMS dlt_te_id"), 'trim|required');
//        $this->form_validation->set_rules('sms_body', lang("Message"), 'required');  
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        $this->SetConatctFromGroup($this->input->post('group_id'));
//        $content = $this->input->post('sms_body');
        $mobile_arr = is_array($this->mobileArr) && count($this->mobileArr) > 0 ? array_keys($this->mobileArr):array();
     
        $sms_length = $this->input->post('sms_length'); 
        if(count($mobile_arr)==0){
            exit(json_encode(array('error' => 'Unable to send SMS')));   
        } 
        $smscount = count($mobile_arr);
        foreach( $mobile_arr as $mobile_items){
            $customerDetails =  $this->db->select('award_points,phone')->where(['phone' =>$mobile_items])->get('sma_companies')->row();
                 $content = 'Dear Valued Loyal customer, Thank you for choosing Fantasy Bakery n Patisserie. Your total loyalty points are '.$customerDetails->award_points.' that you can redeem anytime.';

            $sms_header = $this->input->post('sms_promotional_header'); 
            $sms_template_key = null; //$this->input->post('sms_template_key');
             $DLT_TE_ID = $this->input->post('dlt_te_id');
            
           $this->SendSms([$customerDetails->phone], $content , $sms_header, $DLT_TE_ID);
        }
        
        $error = $this->session->flashdata('error');
        
        if(!empty($error)):
            unset($_SESSION['error']);
            $this->session->unset_tempdata('error');
            exit(json_encode(array('error' => $error)));   
        else:
            $rec['sms_balance_update'] = $this->sma->update_sms_count(($smscount* $sms_length));
            exit(json_encode(array('success' => 'Sms send successfully', 'sms_count'=>$this->sma->BalanceSMS())));   
        endif;
        
    }
            
    /*-------------- End Award Point SMS ------------*/
    
    
    /*------------- Add single Email---------------*/
    function addSingleEmail(){ 
        $this->form_validation->set_rules('hiddencust_email', lang("Customer Contact"), 'required');
        $this->form_validation->set_rules('subject', lang("Subject"), 'required');
        $this->form_validation->set_rules('email_body', lang("Message"), 'required');  
        
        $file = '';
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        } 
        if ($_FILES['attachment']['size'] > 0) { 
            $config['upload_path'] = $this->upload_path;  
            $config['overwrite'] = true;
            $config['encrypt_name'] = TRUE;
            $config['max_filename'] = 25;
            $config['allowed_types'] = $this->digital_file_types;
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('attachment')) {
                  $error = $this->upload->display_errors(); 
                   exit(json_encode(array('error' => $error)));   
            }else{
                 $file = $this->upload->file_name; 
                 //chmod($this->upload_path.$file,0777); 
            }
        }  
        $attachment1 =    !empty($file)?$this->upload_path.$file:NULL;
        $attachment_template = $this->input->post('attachment_template');
        
        if($attachment1==NULL && !empty($attachment_template)):
         $attachment1 = $attachment_template;
        endif;    
        
        $contactArr = $this->ContactParse($this->input->post('hiddencust_email'));
        $subject    = $this->input->post('subject');
        $content    = $this->input->post('email_body');
        $email_arr = isset($contactArr['email']) && is_array($contactArr['email'])?$contactArr['email']:array();
       
        $this->load->library('logs');
        $this->logs->write('emailBug1', $to.'|'.json_encode($email_arr), $val);
        
          if(count( $email_arr)==0){
        	 exit(json_encode(array('error' => 'Email-ID is  blank')));
        }  
        
        $this->SendEmail($email_arr,$subject,$content,$attachment1,NULL) ;
        $error = $this->session->flashdata('error');
        
        if(!empty($error)):
            exit(json_encode(array('error' => $error)));   
        else:
            exit(json_encode(array('success' => 'Email send successfully')));   
        endif;
    }
        
    /*------------- Add Group Email---------------*/
    function addGroupEmail(){
        $this->form_validation->set_rules('group_id', lang("Customer Group"), 'required'); 
        $this->form_validation->set_rules('subject', lang("Subject"), 'required');
        $this->form_validation->set_rules('email_body', lang("Message"), 'required');  
        
        $file = '';
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        if ($_FILES['attachment']['size'] > 0) {
            $config['upload_path'] = $this->upload_path;  
            $config['overwrite'] = true;
            $config['encrypt_name'] = TRUE;
            $config['max_filename'] = 25;
            $config['allowed_types'] = $this->digital_file_types;
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('attachment')) {
                  $error = $this->upload->display_errors(); 
            }else{
                 $file = $this->upload->file_name; 
            }
        }  
        $attachment1 = !empty($file)? $this->upload_path.$file:NULL; 
        
        $attachment_template = $this->input->post('attachment_template');
        
        if($attachment1==NULL && !empty($attachment_template)):
         $attachment1 = $attachment_template;
        endif;    
        $this->SetConatctFromGroup($this->input->post('group_id'));   
        $email_arr = is_array($this->emailArr) && count($this->emailArr) > 0 ? array_keys($this->emailArr):array();
        $mobile_arr = is_array($this->mobileArr) && count($this->mobileArr) > 0 ? array_keys($this->mobileArr):array();
        $subject    = $this->input->post('subject');
        $content    = $this->input->post('email_body'); 
        
        $this->SendEmail($email_arr,$subject,$content,$attachment1,NULL) ;
        $error = $this->session->flashdata('error');
        
        if(!empty($error)):
            exit(json_encode(array('error' => $error)));   
        else:
            if(count($mobile_arr) != count($email_arr)){
                exit(json_encode(array('success' => 'Email submitted successfully but some email not send successfully due to missing of email id')));   
            }
            exit(json_encode(array('success' => 'Email send successfully')));   
        endif;
    }
            
    
    /*------------- Add Single Email---------------*/
    function addSingleAppmsg(){
        $this->form_validation->set_rules('hiddencust_appmsg', lang("Customer Contact"), 'required');
        $this->form_validation->set_rules('subject', lang("Subject"), 'required');
        $this->form_validation->set_rules('appmsg_body', lang("Message"), 'required');  
        $file = '';
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        if ($_FILES['attachment']['size'] > 0) {
            $config['upload_path'] = $this->upload_path;  
            $config['overwrite'] = true;
            $config['encrypt_name'] = TRUE;
            $config['max_filename'] = 25;
            $config['allowed_types'] = $this->digital_file_types;
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('attachment')) {
                  $error = $this->upload->display_errors(); 
            }else{
                 $file = $this->upload->file_name; 
            }
        }  
        $attachment1 = !empty($file)? base_url($this->upload_path.$file):NULL; 
        $attachment_template = $this->input->post('attachment_template');
        if($attachment1==NULL && !empty($attachment_template)):
         $attachment1 = $attachment_template;
        endif;    
        
        $contactArr = $this->ContactParse($this->input->post('hiddencust_appmsg'));
        $mobile_arr = isset($contactArr['mobile']) && is_array($contactArr['mobile'])?$contactArr['mobile']:array(); 
        $email_arr = isset($contactArr['email']) && is_array($contactArr['email'])?$contactArr['email']:array();
        
        $subject    = $this->input->post('subject');
        $msgtype    = $this->input->post('msgtype');
        $content    = $this->input->post('appmsg_body');
        
        
        $ci = get_instance();
        $config = $ci->config;
        $_merchant_phone = isset($config->config['merchant_phone'])?$config->config['merchant_phone']:'';
        
        $_postArr = array();
        $_postArr['action']     = 'messageInsert';
        $_postArr['sender']     = $_merchant_phone;
        $_postArr['subject']    =  $subject ;
        $_postArr['attachment'] = $attachment;
        $_postArr['message']    = $content; 
        if(is_array($mobile_arr) && count($mobile_arr) >0):
            $_postArr['receiver']       = implode(',',$mobile_arr);
        endif;

        if(is_array($email_arr) && count($email_arr) >0):
            $_postArr['receiver_email']   = implode(',',$email_arr);
        endif;

        $_postArr['receiver1']  = $this->input->post('hiddencust_appmsg');
        $_postArr['msgtype']    = $msgtype;
        $_postArr['refrer']     = base_url(); 
        $_postArr['type']       = 'push_message';
        $_postArr['unicode']    = $this->input->post('unicode');

        $res_api =  $this->CallAPI('POST', 'https://simplypos.in/api/api-message.php', $_postArr);     
            
        if(!empty( $res_api))  :
            $_jsonObj = json_decode($res_api);
            if($_jsonObj->status =='error'): 
                exit(json_encode(array('error' => 'Message not Send Successfully')));   
            endif;
            exit(json_encode(array('success' => 'Message Send Successfully')));  
        endif;
        exit(json_encode(array('error' => 'Message not Send Successfully')));  
    }
    
    /*------------- Add Group Email---------------*/
    function addGroupAppmsg(){
        $this->form_validation->set_rules('group_id', lang("Customer Group"), 'required'); 
        $this->form_validation->set_rules('subject', lang("Subject"), 'required');
        $this->form_validation->set_rules('appmsg_body', lang("Message"), 'required');  
        $file = '';
        if ($this->form_validation->run() == false) {
          exit(json_encode(array('error' => validation_errors())));   
        }
        if ($_FILES['attachment']['size'] > 0) {
            $config['upload_path'] = $this->upload_path;  
            $config['overwrite'] = true;
            $config['encrypt_name'] = TRUE;
            $config['max_filename'] = 25;
            $config['allowed_types'] = $this->digital_file_types;
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('attachment')) {
                  $error = $this->upload->display_errors(); 
            }else{
                 $file = $this->upload->file_name; 
            }
        }  
        $attachment1 = !empty($file)? base_url($this->upload_path.$file):NULL; 
        $attachment_template = $this->input->post('attachment_template');
        if($attachment1==NULL && !empty($attachment_template)):
         $attachment1 = $attachment_template;
        endif;    
        
        
        $this->SetConatctFromGroup($this->input->post('group_id'));
        
        $mobile_arr = is_array($this->mobileArr) && count($this->mobileArr) > 0 ? array_keys($this->mobileArr):array();
        $email_arr = is_array($this->emailArr) && count($this->emailArr) > 0 ? array_keys($this->emailArr):array();
        
        $subject    = $this->input->post('subject');
        $msgtype    = $this->input->post('msgtype');
        $content    = $this->input->post('appmsg_body');
        
        $ci = get_instance();
        $config = $ci->config;
        $_merchant_phone = isset($config->config['merchant_phone'])?$config->config['merchant_phone']:'';
        
        $_postArr = array();
        $_postArr['action']     = 'messageInsert';
        $_postArr['sender']     = $_merchant_phone;
        $_postArr['subject']    =  $subject;
        $_postArr['attachment'] = $attachment;
        $_postArr['message']    = $content; 
        if(is_array($mobile_arr) && count($mobile_arr) >0):
            $_postArr['receiver']       = implode(',',$mobile_arr);
        endif;

        if(is_array($email_arr) && count($email_arr) >0):
            $_postArr['receiver_email']   = implode(',',$email_arr);
        endif;

       // $_postArr['receiver1']  = $data['hiddencust_appmsg'];
        $_postArr['msgtype']    = $msgtype;
        $_postArr['refrer']     = base_url(); 
        $_postArr['type']       = 'push_message';
        $_postArr['unicode']    = $this->input->post('unicode');

        $res_api =  $this->CallAPI('POST', 'https://simplypos.in/api/api-message.php', $_postArr);     
            if(!empty( $res_api))  :
                $_jsonObj = json_decode($res_api);
                if($_jsonObj->status =='error'): 
                    exit(json_encode(array('error' => 'Message not Send Successfully')));   
                endif;
                exit(json_encode(array('success' => 'Message Send Successfully')));  
            endif;
        exit(json_encode(array('error' => 'Message not Send Successfully'))); 
    }
            
    
    function ContactParse($hiddencust){
        $mobile_arr = $email_arr = array();
        $client_arr = @explode(',',$hiddencust);            
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
        return array('mobile'=>$mobile_arr,'email'=>$email_arr,);
    }
    
    public function SetConatctFromGroup($GID){
        $_contactArr =  $this->site->getAllContactGroupMemberDetails($GID); 
        if(is_array($_contactArr) && count($_contactArr) > 0):
            foreach ($_contactArr as $obj) {
             $this->mobileArr[$obj->phone] = $obj->name;
             $this->emailArr[$obj->email]  = $obj->name;
            }
        endif;
    }
    
     public function set_sms_cron(){
         $pos_sms_cron = $this->input->post('pos_sms_cron');
         $pos_sms_cron_type = $this->input->post('pos_sms_cron_type');
	 $res= $this->sma->SmsCron($pos_sms_cron,NULL,$pos_sms_cron_type);
	 if($res!==false){
	 	exit(json_encode($res)); 
	 }
	 exit(json_encode(array('error' => 'Message not Send Successfully'))); 
    }
    
    public function sms_list(){
         $offset= !empty($this->input->post('offset'))?$this->input->post('offset'):0;
         $limit= !empty($this->input->post('limit'))?$this->input->post('limit'):30;
         $res= $this->sma->SMSList($offset ,$limit );
         
       // print_r($res); exit;
	 if($res!==false){
	 	 $res = json_decode($res);
	 	 if($res->status=='SUCCESS' &&  $res->total_count > 0):
	 	 	
	 	 	 $data = array();
	 	  
	 	 	 $data['data'] = ' ';
			 foreach($res->result as $objData){
				 $data['data'] =  $data['data'].'<tr> <td>'.$objData->posttime.'</td> <td>'.$objData->receiver_phone.'</td> <td>'.$objData->note.'</td> </tr>'; 
			 }
			  $data['data'] =  $data['data']. ' ';
			print(json_encode($data));
	
	 	 endif;
	 }
	 
    }

   public function set_sms_cron_user(){
        $pos_cron_user = $this->input->post('customer_id'); 
        
        $data = array();
        
        if(is_array($pos_cron_user)):
            foreach ($pos_cron_user as $key => $value) {
                $data[]=array('customer_id'=>$value);
            }
        endif;
      //  $this->sma->print_arrays($data);
        $res= $this->site->addCronMember($data);
        if($res==true){
            $return['success']= 'set Successfully';
            $return['cron_user']=$this->site->getAllCronCustomer();
            exit(json_encode($return));  
        }
        exit(json_encode(array('error' => 'Not set successfully'))); 
    }
}
