<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Billers extends MY_Controller
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
        $this->lang->load('billers', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('companies_model');
    }

    function index($action = NULL)
    {
        $this->sma->checkPermissions();

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['action'] = $action;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('billers')));
        $meta = array('page_title' => lang('billers'), 'bc' => $bc);
        $this->page_construct('billers/index', $meta, $this->data);
    }

    function getBillers()
    {
        $this->sma->checkPermissions('index');

        $this->load->library('datatables');
        $this->datatables
            ->select("id, company, name, vat_no, phone, email, city, country")
            ->from("companies")
            ->where('group_name', 'biller')
            ->add_column("Actions", "<div class=\"text-center\"><a class=\"tip\" title='" . $this->lang->line("edit_biller") . "' href='" . site_url('billers/edit/$1') . "' data-toggle='modal' data-target='#myModal'><i class=\"fa fa-edit\"></i></a> <a href='#' class='tip po' title='<b>" . $this->lang->line("delete_biller") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('billers/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a></div>", "id");
        //->unset_column('id');
        echo $this->datatables->generate();
    }

    function add()
    {
        $this->sma->checkPermissions(false, true);

        $this->form_validation->set_rules('email', $this->lang->line("email_address"), 'is_unique[companies.email]');
        $this->form_validation->set_rules('phone', lang("phone"), 'is_unique[companies.phone]');
        
        if($this->input->post('country') == 'India'){            
            $this->form_validation->set_rules('state', lang("state"), 'required');            
        } 
        $this->form_validation->set_rules('postal_code', lang("Pincode"), 'required');
        
        if ($this->form_validation->run('supplier/add') == true) {
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


            $data = array('name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' => NULL,
                'group_name' => 'biller',
                'company' => $this->input->post('company'),
                'address' => $this->input->post('address'),
                'vat_no' => $this->input->post('vat_no'),
                'gstn_no' => $this->input->post('gstn_no'),
                'city' => $this->input->post('city'),
                'state' => $state,//$this->input->post('state'),
                'state_code'=>$state_code,//$this->site->getStateCodeFromName($this->input->post('state')),
                
                'postal_code' => $this->input->post('postal_code'),
                'country' => $country, //$this->input->post('country'),
                'phone' => $this->input->post('phone'),
                'logo' => $this->input->post('logo'),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'invoice_footer' => $this->input->post('invoice_footer'),
            );
            $location_map =  $this->input->post('location_map');
            if(!empty($location_map)):
            	$locArr = explode(',',$location_map);
            	 $data['lat']= $locArr[0];
            	 $data['lng']= $locArr[1];
            endif; 	
            
        } elseif ($this->input->post('add_biller')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('billers');
        }

        if ($this->form_validation->run() == true && $resBillerID = $this->companies_model->addCompany($data)) {
        $biller =  $this->companies_model->getCompanyByID($resBillerID);
        	$this->sma->saveBillerLocation($biller);
            $this->session->set_flashdata('message', $this->lang->line("biller_added"));
            redirect("billers");
        } 
        else {
            $this->data['logos'] = $this->getLogoList();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['country'] = $this->site->getCountry();
            $cfields = $this->site->getCustomeFieldsLabel('biller') ;
            $this->data['custome_fields'] = $cfields['biller'];
            
            $this->load->view($this->theme . 'billers/add', $this->data);
        }
    }

    function edit($id = NULL)
    {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $company_details = $this->companies_model->getCompanyByID($id);
        if ($this->input->post('email') != $company_details->email) {
            $this->form_validation->set_rules('code', lang("email_address"), 'is_unique[companies.email]');
        }
        if($this->input->post('country') == 'India'){            
            $this->form_validation->set_rules('state', lang("state"), 'required');            
        } 
        
        $this->form_validation->set_rules('postal_code', lang("Pincode"), 'required');
        
        if ($this->form_validation->run('supplier/add') == true) {
            $data = array('name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' => NULL,
                'group_name' => 'biller',
                'company' => $this->input->post('company'),
                'address' => $this->input->post('address'),
                'vat_no' => $this->input->post('vat_no'),
                
                'gstn_no' => $this->input->post('gstn_no'),
                'city' => $this->input->post('city'),
                'state' => $this->input->post('state'),
                'state_code'=>$this->site->getStateCodeFromName($this->input->post('state')),
                
                'postal_code' => $this->input->post('postal_code'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),
                'logo' => $this->input->post('logo'),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'invoice_footer' => $this->input->post('invoice_footer'),
            );
            $location_map =  $this->input->post('location_map');
            if(!empty($location_map)):
            	$locArr = explode(',',$location_map);
            	 $data['lat']= $locArr[0];
            	 $data['lng']= $locArr[1];
            endif; 	
            
        } elseif ($this->input->post('edit_biller')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('billers');
        }

        if ($this->form_validation->run() == true && $this->companies_model->updateCompany($id, $data)) {
         $biller =  $this->companies_model->getCompanyByID($id);
        	$this->sma->saveBillerLocation($biller);
            $this->session->set_flashdata('message', $this->lang->line("biller_updated"));
            redirect("billers");
        } 
        else {
            $this->data['biller'] = $company_details;
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['logos'] = $this->getLogoList();
             $this->data['states'] = $this->site->getAllStates();
            $this->data['modal_js'] = $this->site->modal_js();
            $cfields = $this->site->getCustomeFieldsLabel('biller') ;
            $this->data['custome_fields'] = $cfields['biller'];
            
            $this->load->view($this->theme . 'billers/edit', $this->data);
        }
    }


    function delete($id = NULL)
    {
        $this->sma->checkPermissions(NULL, TRUE);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }
        $this->sma->storeDeletedData('companies', 'id', $id);
        if ($this->companies_model->deleteBiller($id)) {
        $this->sma->removeBillerLocation($id);
            echo $this->lang->line("biller_deleted");
        } else {
            $this->sma->deleteTableDataById('companies', $id);
            $this->session->set_flashdata('warning', lang('biller_x_deleted_have_sales'));
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
        $rows['results'] = $this->companies_model->getBillerSuggestions($term, $limit);
        $this->sma->send_json($rows);
    }

    function getBiller($id = NULL)
    {
        $this->sma->checkPermissions('index');

        $row = $this->companies_model->getCompanyByID($id);
        $this->sma->send_json(array(array('id' => $row->id, 'text' => $row->company)));
    }

    public function getLogoList()
    {
        $this->load->helper('directory');
        $dirname = "assets/mdata/$this->Customer_assets/uploads/logos";
        $ext = array("jpg", "png", "jpeg", "gif");
        $files = array();
        if ($handle = opendir($dirname)) {
            while (false !== ($file = readdir($handle)))
                for ($i = 0; $i < sizeof($ext); $i++)
                    if (stristr($file, "." . $ext[$i])) //NOT case sensitive: OK with JpeG, JPG, ecc.
                        $files[] = $file;
            closedir($handle);
        }
        sort($files);
        return $files;
    }

    function biller_actions()
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
                        if (!$this->companies_model->deleteBiller($id)) {
                           $this->sma->deleteTableDataById('companies', $id);
                            $error = true;
                        }
                    }
                    if ($error) {
                        $this->session->set_flashdata('warning', lang('billers_x_deleted_have_sales'));
                    } else {
                        $this->session->set_flashdata('message', $this->lang->line("billers_deleted"));
                    }
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                if ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,),'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000') )));

                    $this->excel->getActiveSheet()->getStyle("A1:F1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:F1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Billers');
                    $this->excel->getActiveSheet()->setTitle(lang('billers'));

                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('company'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('phone'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('email'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('city'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('country'));
                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $customer = $this->site->getCompanyByID($id);
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $customer->company);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $customer->name);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $customer->phone);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $customer->email);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $customer->city);
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $customer->country);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'billers_' . date('Y_m_d_H_i_s');
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
                $this->session->set_flashdata('error', $this->lang->line("no_biller_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

}
