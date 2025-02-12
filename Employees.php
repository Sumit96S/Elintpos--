<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Employees extends MY_Controller
{

    function __construct()
    {
        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if (!$this->Owner) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->lang->load('employees_lang', $this->Settings->user_language);
        $this->load->library('form_validation');         
        $this->load->model('employees_model');
	    $this->digital_upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/people/';
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/people/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/people/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';
        $this->data['logo'] = true;
    }

    function index($action = NULL)
    {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['action'] = $action;
        $bc = [['link' => base_url(), 'page' => lang('home')], ['link' => '#', 'page' => lang('Employees')]];
        $meta =  ['page_title' => lang('employees'), 'bc' => $bc];
        $this->page_construct('employees/index', $meta, $this->data);
    }

    function get_employees()
    {
        $this->sma->checkPermissions('index');

        $this->load->library('datatables');
        $this->datatables
            ->select("id, group_name, name, phone, email, city, pan_card ")
            ->from("companies")
            ->where('group_id >', 4)
            ->add_column("Actions", "<div class=\"text-center\"><a class=\"tip\" title='" . $this->lang->line("edit") . "' href='" . site_url('employees/edit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . $this->lang->line("delete") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('employees/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');
        echo $this->datatables->generate();
    }

    function add()
    {
        $this->sma->checkPermissions();
                 
        //$this->form_validation->set_rules('email', $this->lang->line("email_address"), 'is_unique[companies.email]');
        $this->form_validation->set_rules('phone', $this->lang->line("phone"), 'required');

        if ($this->form_validation->run('employees/add') == true) {
          
            if ($this->input->post('country') == 'other' && $this->input->post('add_country') != '') {
                $this->site->addCountry(['name' => $this->input->post('add_country')]);
                $country = $this->input->post('add_country');
            } else {
                $country = ($this->input->post('country') != 'other') ? $this->input->post('country') : NULL;
            }

            if (($this->input->post('state') == 'other' || $this->input->post('state') == '') && ($this->input->post('statecode') != '' && $this->input->post('statename') != '' )) {
                $countryid = $this->site->getCountryId($country);
                $state = $this->input->post('statename');
                $state_code = $this->input->post('statecode');
                $statedata = [
                    'country_id' => $countryid,
                    'code' => $state_code,
                    'name' => $state,
                ];
                $this->site->addstate($statedata);
            } else {
                if ($this->input->post('state') == 'other') {
                    $state = NULL;
                    $state_code = NULL;
                } else {
                    $state = $this->input->post('state');
                    $state_code = $this->site->getStateCodeFromName($state);
                }
            }
         
           $group = explode("~",$this->input->post('employee_Type'));

            $data = array('name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id'   => $group[0],
                'group_name' => $group[1],                 
                'name' =>   $this->input->post('name'),
                'address' => $this->input->post('address'),                 
                'dob'    => $this->sma->fsd(trim($this->input->post('date'))),                 
                'city' => $this->input->post('city'),
                'state' => $state, 
                'state_code'=>$state_code,  
                'postal_code' => $this->input->post('postal_code'),
                'pan_card' => $this->input->post('pan_card'),
                'country' => $country, 
                'email' => $this->input->post('email'),
                'phone' => $this->input->post('phone'),                 
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'), 
            );
             	
            
            if ($_FILES['photos']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('photos')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                }
                $photo = $this->upload->file_name;
                $data['logo']= $photo;
            }

            if ($_FILES['attch_document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('attch_document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                }
                $photo = $this->upload->file_name;
                $data['cf1']= $photo; //Id Proof File Name
            }
            
        } elseif ($this->input->post('add_employee')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('employees/index');
        }

        if ($this->form_validation->run() == true && $this->employees_model->addEmployee($data)) {
                     
            $this->session->set_flashdata('message', $this->lang->line("employee_added"));
            redirect('employees/index');
        } else {

            $this->data['employeeType'] =  $this->employees_model->getEmployeeTypes();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['country'] = $this->site->getCountry();
            $this->data['modal_js'] = $this->site->modal_js();
            $cfields = $this->site->getCustomeFieldsLabel('employee') ;
            $this->data['custome_fields'] = $cfields['employee'];
            
            $this->load->view($this->theme . 'employees/add', $this->data);
        }
    }

    function edit($id = NULL)
    {
        $this->sma->checkPermissions();

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        } 
         
        $this->form_validation->set_rules('name', lang("name"), 'trim|required');
        $this->form_validation->set_rules('phone', lang("phone"), 'trim|required');		
       
         if ( $this->form_validation->run('employees/edit') == true) {
             
              $exp_emptype = explode("~",$this->input->post('employee_Type'));
	      $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' =>  $exp_emptype[0],
                'group_name' => ($exp_emptype[1]=='sales'?'Sales Staff':$exp_emptype[1]),
                'address' => $this->input->post('address'),                 
                'dob'    => $this->sma->fsd(trim($this->input->post('date'))),                 
                'city' => $this->input->post('city'),
                'state' => $this->input->post('state'),
                'state_code'=>$this->site->getStateCodeFromName($this->input->post('state')),
                'postal_code' => $this->input->post('postal_code'),
                'pan_card' => $this->input->post('pan_card'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),                 
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),                 
            ];
             
            if ($_FILES['photos']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('photos')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                }
                $photo = $this->upload->file_name;
                $data['logo']= $photo;                
            }

            if ($_FILES['attch_document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = false;
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('attch_document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                }
                $photo = $this->upload->file_name;
                $data['cf1']= $photo;
                
            }
           } elseif ($this->input->post('edit_employee')){
               $this->session->set_flashdata('error', validation_errors());
               redirect($_SERVER["HTTP_REFERER"]);
           }
    
         if ($this->form_validation->run() == true && $this->employees_model->updateEmployee($id, $data)) {
                   
            $this->session->set_flashdata('message', $this->lang->line("employee_updated"));
	     
            redirect('employees/index');            
         } else {
             
            $this->data['employee'] = $this->employees_model->getEmployeeByID($id);
            $this->data['employeeType'] =  $this->employees_model->getEmployeeTypes();
         
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            
            $this->data['states'] = $this->site->getAllStates();
            $this->data['modal_js'] = $this->site->modal_js();
            $cfields = $this->site->getCustomeFieldsLabel('employee') ;
            $this->data['custome_fields'] = $cfields['employee'];
            
            $this->load->view($this->theme . 'employees/edit', $this->data);
         }
    }


    function delete($id = NULL)
    {
        $this->sma->checkPermissions(NULL, TRUE);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->sma->storeDeletedData('companies', 'id', $id);
        if ($this->employees_model->deleteEmployee($id)) {
         
            echo $this->lang->line("employee_deleted");
        } else {             
            $this->session->set_flashdata('warning', lang('employee_x_deleted_have_sales'));
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . (isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : site_url('welcome')) . "'; }, 0);</script>");
        }
    }

    function suggestions($term = NULL, $limit = NULL)
    {
        $this->sma->checkPermissions('index');

        if ($this->input->get('term')) {
            $term = $this->input->get('term', TRUE);
        }
        $limit = $this->input->get('limit', TRUE);
        $rows['results'] = $this->employees_model->getsales_personuggestions($term, $limit);
        $this->sma->send_json($rows);
    }


    function salesshaff_actions()
    {
        if (!$this->Owner && !$this->GP['bulk_actions']) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == true) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {
                    $this->sma->checkPermissions('delete');
                    $error = false;
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('companies', 'id', $id);
                        if (!$this->employees_model->deleteSeller($id)) {
                            $this->sma->deleteTableDataById('companies', $id);
                            $error = true;
                        }
                    }
                    if ($error) {
                        $this->session->set_flashdata('warning', lang('sales_person_x_deleted_have_sales'));
                    } else {
                        $this->session->set_flashdata('message', $this->lang->line("sales_person_deleted"));
                    }
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {
                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                      $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,),'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000') )));

                    $this->excel->getActiveSheet()->getStyle("A1:E1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:E1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Sales Person');

                    $this->excel->getActiveSheet()->setTitle(lang('sales_person'));
                    //$this->excel->getActiveSheet()->SetCellValue('A1', lang('company'));
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('phone'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('email'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('city'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('state'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $customer = $this->employees_model->getEmployeeByID($id);

                        //$this->excel->getActiveSheet()->SetCellValue('A' . $row, $customer->company);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $customer->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $customer->phone);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $customer->email);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $customer->city);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $customer->state);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'sales_person_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
                        $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                        $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                        require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                        $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                        $rendererLibrary = 'MPDF';
                        $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                        if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                            die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' .
                                PHP_EOL . ' as appropriate for your directory structure');
                        }

                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                        return $objWriter->save('php://output');
                    }
                    if ($this->input->post('form_action') == 'export_excel') {

                        header('Content-Type: application/vnd.ms-excel');
                        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                        return $objWriter->save('php://output');
                    }

                    redirect($_SERVER["HTTP_REFERER"]);
                }
            } else {
                $this->session->set_flashdata('error', $this->lang->line("No_Sales_Person_Selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

     function getPancardNo()
    {
        //$id = $this->input->get('id');
        $pan_card = $this->input->get('pan_card');
        $panvalue = $this->db->select('pan_card')->where('pan_card',$pan_card)->get('sma_companies')->row()->pan_card;
        $this->sma->send_json($panvalue);
    }


}
