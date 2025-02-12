<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class webshop_settings extends MY_Controller {

    public $webshop_settings;

    public function __construct() {
        parent::__construct();

        $this->active_webshop = (bool) $this->Settings->active_webshop ? $this->Settings->active_webshop : 0;
        if (!$this->active_webshop) {
            redirect('access_denied');
        }

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }

        if (!$this->Owner) {
            $allowed = 0;

            if ($allowed === 0) {
                $this->session->set_flashdata('warning', lang('access_denied'));
                redirect('welcome');
            }
        }

        $this->lang->load('settings', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('webshop_settings_model');

        $this->webshop_settings = $this->webshop_settings_model->getWebshopSettings();

        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';

        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif';
        $this->allowed_file_size = '2048';
        
    }

    public function service_off() {

        $this->load->view('default/views/service_off', $this->data);
    }

    public function index() {

        $this->form_validation->set_rules('home_page', lang('home_page'), 'trim|required');
        $this->form_validation->set_rules('product_list_page', lang('product_list_page'), 'trim|required');
        $this->form_validation->set_rules('product_list_view', lang('product_list_view'), 'trim|required');
        $this->form_validation->set_rules('product_description', lang('product_description'), 'trim|required');
        $this->form_validation->set_rules('header_strip_style', lang('header_strip_style'), 'trim|required');
        $this->form_validation->set_rules('theme_color', lang('theme_color'), 'trim|required');
        $this->form_validation->set_rules('header_style', lang('header_style'), 'trim|required');

        if ($this->form_validation->run() == TRUE) {

            $data = array(
                "home_page" => $this->input->post('home_page'),
                "product_list_page" => $this->input->post('product_list_page'),
                "product_list_view" => $this->input->post('product_list_view'),
                "product_description" => $this->input->post('product_description'),
                "theme_color" => $this->input->post('theme_color'),
                "header_strip_style" => $this->input->post('header_strip_style'),
                "header_style" => $this->input->post('header_style'),
            );

            if ($this->webshop_settings_model->updateWebshopSettings($data)) {
                $this->session->set_flashdata('message', lang('setting_updated'));
                redirect('webshop_settings/index');
            } else {
                $this->session->set_flashdata('error', lang('setting_updated_failed'));
                redirect('webshop_settings/index');
            }
        } else {

            $this->data['webshop_settings'] = $this->webshop_settings;

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Ecommerce Layout')));
            $meta = array('page_title' => lang('Ecommerce Layout'), 'bc' => $bc);

            $this->page_construct('webshop_settings/index', $meta, $this->data);
        }
    }

    public function sliders() {

        if (isset($_POST['update_settings'])) {

            if ($this->input->post('is_active_1') == 1) {

                $data[] = array(
                    "slide_key" => 'SLIDE_1',
                    "slide_image" => $this->input->post('slide_image_1'),
                    "background_image" => $this->input->post('slide_bg_1'),
                    "title" => $this->input->post('slide_title_1'),
                    "sub_title" => $this->input->post('slide_subtitle_1'),
                    "button_caption" => $this->input->post('slide_button_1'),
                    "button_link" => $this->input->post('slide_button_link_1'),
                    "bottom_caption" => $this->input->post('slide_bottom_1'),
                    "title_color" => $this->input->post('title_color_1'),
                    "subtitle_color" => $this->input->post('subtitle_color_1'),
                    "is_active" => 1,
                    "is_updated" => 1,
                    "updated_at" => date('Y-m-d H:i:s'),
                );
            } else {
                $data[] = array("slide_key" => 'SLIDE_1', "is_active" => 0, "updated_at" => date('Y-m-d H:i:s'));
            }

            if ($this->input->post('is_active_2') == 2) {

                $data[] = array(
                    "slide_key" => 'SLIDE_2',
                    "slide_image" => $this->input->post('slide_image_2'),
                    "background_image" => $this->input->post('slide_bg_2'),
                    "title" => $this->input->post('slide_title_2'),
                    "sub_title" => $this->input->post('slide_subtitle_2'),
                    "button_caption" => $this->input->post('slide_button_2'),
                    "button_link" => $this->input->post('slide_button_link_2'),
                    "bottom_caption" => $this->input->post('slide_bottom_2'),
                    "title_color" => $this->input->post('title_color_2'),
                    "subtitle_color" => $this->input->post('subtitle_color_2'),
                    "is_active" => 1,
                    "is_updated" => 1,
                    "updated_at" => date('Y-m-d H:i:s'),
                );
            } else {
                $data[] = array("slide_key" => 'SLIDE_2', "is_active" => 0, "updated_at" => date('Y-m-d H:i:s'),);
            }

            if ($this->webshop_settings_model->updateWebshopSliderSettings($data)) {
                $this->session->set_flashdata('message', 'Slider Setting Updated');
                redirect('webshop_settings/sliders');
            }
        } elseif (isset($_POST['reset_default'])) {

            $data[] = array("slide_key" => 'SLIDE_1', "is_active" => 1, "is_updated" => 0, "updated_at" => date('Y-m-d H:i:s'));
            $data[] = array("slide_key" => 'SLIDE_2', "is_active" => 1, "is_updated" => 0, "updated_at" => date('Y-m-d H:i:s'));

            if ($this->webshop_settings_model->updateWebshopSliderSettings($data)) {
                $this->session->set_flashdata('message', 'Slider Reset Successfully');
                redirect('webshop_settings/sliders');
            }
        } else {

            $this->data['sliders'] = $this->webshop_settings_model->get_sliders();

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Home Slider')));
            $meta = array('page_title' => lang('Home Slider'), 'bc' => $bc);

            $this->page_construct('webshop_settings/sliders', $meta, $this->data);
        }
    }

    public function sliders_images() {

        if ($_POST['upload_images'] == "Upload Images") {


            if ($_FILES["background_images"]["error"] && $_FILES["slider_images"]["error"]) {

                $this->session->set_flashdata('error', lang('Please Select Images'));
                redirect('webshop_settings/sliders');
            }

            $statusBg = $statusImg = TRUE;
            // Check if file was uploaded without errors
            if (isset($_FILES["background_images"]) && $_FILES["background_images"]["error"] == 0) {
                $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "png" => "image/png");
                $filename = $_FILES["background_images"]["name"];
                $filetype = $_FILES["background_images"]["type"];
                $filesize = $_FILES["background_images"]["size"];

                // Verify file extension
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if (!array_key_exists($ext, $allowed))
                    die("Error: Please select a valid file format.");

                // Verify file size - 512MB maximum
                $maxsize = 0.5 * 1024 * 1024;
                if ($filesize > $maxsize)
                    die("Error: File size is larger than the allowed limit.");

                // Verify MYME type of the file
                if (in_array($filetype, $allowed)) {
                    // Check whether file exists before uploading it
                    if (file_exists("assets/mdata/$this->Customer_assets/uploads/webshop/slider/bg/" . $filename)) {
                        $statusBg = FALSE;
                        $statusBgMsg = $filename . " is already exists.";
                    } else {
                        move_uploaded_file($_FILES["background_images"]["tmp_name"], "assets/mdata/$this->Customer_assets/uploads/webshop/slider/bg/" . $filename);
                        $statusBg = TRUE;
                    }
                } else {
                    $statusBg = FALSE;
                }
            } else {
                $statusBg = FALSE;
                $statusBgMsg = $_FILES["background_images"]["error"];
            }

            // Check if file was uploaded without errors
            if (isset($_FILES["slider_images"]) && $_FILES["slider_images"]["error"] == 0) {
                $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "png" => "image/png");
                $filename = $_FILES["slider_images"]["name"];
                $filetype = $_FILES["slider_images"]["type"];
                $filesize = $_FILES["slider_images"]["size"];

                // Verify file extension
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if (!array_key_exists($ext, $allowed))
                    die("Error: Please select a valid file format.");

                // Verify file size - 512MB maximum
               /* $maxsize = 0.5 * 1024 * 1024;
                if ($filesize > $maxsize)
                    die("Error: File size is larger than the allowed limit.");*/

                // Verify MYME type of the file
                if (in_array($filetype, $allowed)) {
                    // Check whether file exists before uploading it
                    if (file_exists("assets/mdata/$this->Customer_assets/uploads/webshop/slider/slide/" . $filename)) {
                        $statusImg = false;
                        $statusImgMsg = $filename . " is already exists.";
                    } else {
                        move_uploaded_file($_FILES["slider_images"]["tmp_name"], "assets/mdata/$this->Customer_assets/uploads/webshop/slider/slide/" . $filename);
                        $statusImg = true;
                    }
                } else {
                    $statusImg = false;
                }
            } else {
                $statusImg = false;
                $statusImgMsg = "Error: " . $_FILES["slider_images"]["error"];
            }

            if ($statusImg || $statusBg) {

                $this->session->set_flashdata('message', lang('Images Uploaded Successfully'));
                redirect('webshop_settings/sliders');
            }
        } else {

            echo "Invalid Action";
        }
    }

    public function sections() {

        if ($this->input->post('update_settings')) {

            $sections = $this->input->post('section_id');
            $section_title = $this->input->post('section_title');
            $display_status = $this->input->post('display_status');
            $display_order = $this->input->post('display_order');

            foreach ($sections as $section_id) {

                $status = ($display_status[$section_id] ? $display_status[$section_id] : 0);

                $data[] = array(
                    "id" => $section_id,
                    "section_title" => $section_title[$section_id],
                    "display_status" => $status,
                    "display_order" => $display_order[$section_id],
                );
            }//end foreach 

            if ($this->webshop_settings_model->updateWebshopSections($data)) {
                $this->session->set_flashdata('message', lang('section_updated'));
                redirect('webshop_settings/sections');
            } else {
                $this->session->set_flashdata('error', lang('sections_updated_failed'));
                redirect('webshop_settings/sections');
            }
        } else {

            $this->data['sections'] = $this->webshop_settings_model->getThemeSections($this->webshop_settings->home_page);

            $bc = array(array('link' => base_url(), 'page' => lang('Home')), array('link' => '#', 'page' => lang('Ecommerce / Homepage Sections')));
            $meta = array('page_title' => lang('Ecommerce Homepage Sections'), 'bc' => $bc);

            $this->page_construct('webshop_settings/sections', $meta, $this->data);
        }
    }

    public function elements($element_name) {

        if (!$element_name) {
            redirect('webshop_settings/sections');
        }

//        echo '<pre>';
//        print_r($this->webshop_settings->theme_color);
//        echo '</pre>';

        $this->data['webshop_settings'] = $this->webshop_settings;

        $sections = $this->webshop_settings_model->getActiveSections($this->webshop_settings->home_page);

        foreach ($sections as $key => $section) {
            $this->data['active_sections'][] = $section->section_name;
            $this->data['sections'][$section->section_name] = $section->section_data;
        }

        $this->data['section_name'] = $element_name;

        switch ($element_name) {

            case "section_subcategory_tabs_multiple_sections":

                $this->data['categories'] = $this->webshop_settings_model->get_categories();

                break;

            case "section_category_tab_right_highlite_products":
            case "section_category_exclusive_products":
            case "section_category_tab_vertical_align":
            case "section_category_tab_center_align":
            case "section_category_tab_right_align":
            case "section_category_tab_left_align":

                $this->data['categories'] = $this->webshop_settings_model->get_categories();
                $this->data['category_products'] = $this->webshop_settings_model->get_category_products();

                break;

            case "section_features_list":

                $this->data['features'] = $this->webshop_settings_model->get_features();

                break;

            case "section_fullwidth_notice":

                $this->data['section_data'] = $this->data['sections']['section_fullwidth_notice'];

                break;

            case "section_top_categories":

                $this->data['categories'] = $this->webshop_settings_model->get_categories();

                $this->data['section_data'] = $this->data['sections']['section_top_categories'];

                break;

            default:
                break;
        }//end switch.


        $bc = array(array('link' => base_url(), 'page' => lang('Home')), array('link' => base_url('webshop_settings/sections'), 'page' => lang('Ecommerce Homepage ')), array('link' => '#', 'page' => ucwords(lang($element_name)) . ' Settings'));

        $meta = array('page_title' => lang('Ecommerce Homepage Sections Setting'), 'bc' => $bc);

        $this->data['elemtnt_name'] = 'elements_' . $element_name;

        $this->page_construct('webshop_settings/elements', $meta, $this->data);
    }

    public function elements_section_features_list() {

        if ($this->input->post('update_elements')) {

            $titles = $this->input->post('title');
            $subtitles = $this->input->post('subtitle');
            $icons = $this->input->post('icon');
            $status = $this->input->post('is_active');

            foreach ($titles as $key => $title) {
                $data[] = array(
                    "id" => $key,
                    "title" => $title,
                    "subtitle" => $subtitles[$key],
                    "icon" => $icons[$key],
                    "is_active" => $status[$key],
                );
            }

            $update = $this->webshop_settings_model->updateFeatures($data);

            if ($update) {
                $this->session->set_flashdata('message', lang('Features_updated'));
                redirect('webshop_settings/elements/section_features_list');
            } else {
                $this->session->set_flashdata('error', lang('Features_not_updated'));
                redirect('webshop_settings/elements/section_features_list');
            }
        }
    }

    public function elements_section_subcategory_tabs_multiple_sections() {

        if ($this->input->post('update_elements')) {

            if (isset($_POST['section_subcategory_tabs_products'])) {

                $postData['section_titles'] = $this->input->post('section_title');
                $postData['section_tab_categories'] = $this->input->post('section_subcategory_tabs_products');

                $section_data = serialize(json_encode($postData, TRUE));

                $update = $this->db->where(["section_name" => "section_subcategory_tabs_multiple_sections"])
                        ->update('webshop_homepage_sections', ['section_data' => $section_data]);

                if ($update) {
                    $this->session->set_flashdata('message', lang('elements_updated'));
                    redirect('webshop_settings/elements/section_subcategory_tabs_multiple_sections');
                } else {
                    $this->session->set_flashdata('error', lang('elements_updated_failed'));
                    redirect('webshop_settings/elements/section_subcategory_tabs_multiple_sections');
                }
            }
        }
    }

    public function elements_section_category_tab_right_highlite_products() {

        if ($this->input->post('update_elements')) {

            $this->set_section_category_tab_data('section_category_tab_right_highlite_products', $_POST);
        }
    }

    public function elements_section_category_tab_vertical_align() {

        if ($this->input->post('update_elements')) {

            $this->set_section_category_tab_data('section_category_tab_vertical_align', $_POST);
        }
    }

    public function elements_section_category_tab_center_align() {

        if ($this->input->post('update_elements')) {

            $this->set_section_category_tab_data('section_category_tab_center_align', $_POST);
        }
    }

    public function elements_section_category_tab_left_align() {

        if ($this->input->post('update_elements')) {

            $this->set_section_category_tab_data('section_category_tab_left_align', $_POST);
        }
    }

    public function elements_section_category_tab_right_align() {

        if ($this->input->post('update_elements')) {

            $this->set_section_category_tab_data('section_category_tab_right_align', $_POST);
        }
    }

    public function elements_section_category_exclusive_products() {

        if ($this->input->post('update_elements')) {

            $this->set_section_category_tab_data('section_category_exclusive_products', $_POST);
        }
    }

    public function set_section_category_tab_data($section_name, $post_data) {

        if ($post_data) {

            if (is_array($post_data['section_category_tabs'])) {

                $section_category_tabs = $post_data['section_category_tabs'];
                $section_title = $post_data['section_title'];
                $section_products = $post_data['section_category_products'];

                if (isset($post_data['section_category_highlite_products'])) {
                    $section_highlite_products = $post_data['section_category_highlite_products'];
                }

                foreach ($section_category_tabs as $category_id) {

                    $postData['tabs'][$category_id] = $section_title[$category_id];
                    $postData['products'][$category_id] = $section_products[$category_id];

                    if (isset($section_highlite_products[$category_id])) {
                        $postData['highlite'][$category_id] = $section_highlite_products[$category_id];
                    }
                }//end foreach

                $sectionData = serialize(json_encode($postData, TRUE));

                $update = $this->db->where(["section_name" => $section_name])
                        ->update('webshop_homepage_sections', ['section_data' => $sectionData]);

                if ($update) {
                    $this->session->set_flashdata('message', lang('elements_updated'));
                    redirect('webshop_settings/elements/' . $section_name);
                } else {
                    $this->session->set_flashdata('error', lang('elements_updated_failed'));
                    redirect('webshop_settings/elements/' . $section_name);
                }
            }
        }
    }

    public function elements_section_top_categories() {

        if ($this->input->post('update_elements')) {

            if (isset($_POST['section_top_categories'])) {

                $postData['category_titles'] = $this->input->post('category_titles');
                $postData['section_top_categories'] = $this->input->post('section_top_categories');

                $section_data = serialize(json_encode($postData, TRUE));

                $update = $this->db->where(["section_name" => "section_top_categories"])
                        ->update('webshop_homepage_sections', ['section_data' => $section_data]);

                if ($update) {
                    $this->session->set_flashdata('message', lang('elements_updated'));
                    redirect('webshop_settings/elements/section_top_categories');
                } else {
                    $this->session->set_flashdata('error', lang('elements_updated_failed'));
                    redirect('webshop_settings/elements/section_top_categories');
                }
            }
        }
    }

    public function elements_section_fullwidth_notice() {

        if ($this->input->post('update_elements')) {

            $section_data = $this->input->post('section_data');

            $update = $this->db->where(["section_name" => "section_fullwidth_notice"])
                    ->update('webshop_homepage_sections', ['section_data' => $section_data]);

            if ($update) {
                $this->session->set_flashdata('message', lang('elements_updated'));
                redirect('webshop_settings/elements/section_fullwidth_notice');
            } else {
                $this->session->set_flashdata('error', lang('elements_updated_failed'));
                redirect('webshop_settings/elements/section_fullwidth_notice');
            }
        }
    }

    public function custom_pages() {

        $custom_pages = $this->webshop_settings_model->getCustomPages();

        $bc = array(array('link' => base_url(), 'page' => lang('Home')), array('link' => base_url('webshop_settings/custom_pages'), 'page' => lang('Custom Pages')), array('link' => '#', 'page' => ucwords($pageData['page_title'])));

        $meta = array('page_title' => lang('Ecommerce Custom Pages'), 'bc' => $bc);

        $this->data['custom_pages'] = $custom_pages;

        $this->page_construct('webshop_settings/pages', $meta, $this->data);
    }

    public function edit_custom_pages($page = null, $page_key = null) {

        if (isset($_POST['update_custom_pages'])) {

            $page_title = trim($this->input->post('page_title'));

            $page_type = $this->input->post('page_type');
            $page_section = $this->input->post('page_section');
            $is_active = $this->input->post('is_active');
            $page_id = $this->input->post('page_id');

            $page_key = str_replace([' & ',' ','&','-','\''], ['_'], strtolower($page_title));

            $page_file = $page_text = '';
            if ($page_type == 'text') {
                $page_text = $_POST['page_text']; //trim($this->input->post('page_text'));
            } else {
                $page_file = trim($_FILES['page_file']['name']);
                if(!empty($page_file)){ 
                    $this->do_upload('page_file', 'pages');
                }
            }

            $data = [
                'page_title'    => $page_title,
                'page_text'     => $page_text,
                'page_file'     => $page_file,
                'page_type'     => $page_type,
                'page_section'  => $page_section,
                'is_active'     => $is_active,
                'page_key'      => $page_key,
            ];
            
            if($this->db->where(['id'=>$page_id])->update('webshop_static_pages' ,$data)){
                $this->session->set_flashdata('message', 'Page Updated Successfully');
                redirect('webshop_settings/custom_pages');
            }            
            
        } else {

            $pageData = $this->webshop_settings_model->getCustomPages($page_key);

            $bc = array(array('link' => base_url(), 'page' => lang('Home')), array('link' => base_url('webshop_settings/custom_pages/'), 'page' => lang('Edit Custom Pages')), array('link' => '#', 'page' => ucwords('Edit ' . $pageData[$page_key]['page_title'])));

            $meta = array('page_title' => lang('Edit Custom Pages'), 'bc' => $bc);

            $this->data['page_data'] = $pageData[$page];

            $this->page_construct('webshop_settings/page_edit', $meta, $this->data);
        }
    }

    public function do_upload($field_name, $folder='') {
        
        $config = array(
            'upload_path'   => "./assets/mdata/$this->Customer_assets/uploads/webshop/".($folder?$folder.'/':''),
            'allowed_types' => "gif|jpg|png|jpeg|pdf|doc|docx",
            'overwrite'     => TRUE,
            'max_size'      => "2048000", // Can be set to particular file size , here it is 2 MB(2048 Kb)
            'max_height'    => "768",
            'max_width'     => "1024"
        );
        $this->load->library('upload', $config);
        if ($this->upload->do_upload($field_name)) {
            $data = array('status'=>'success', 'upload_data' => $this->upload->data());
            return $data;
        } else {
            $data = array('status'=>'fail', 'error' => $this->upload->display_errors());
            return $data;
        }
    }
    
    public function shipping_methods() {
        
        $this->load->model('eshop_model');
        
        $this->data['shippings'] = $this->eshop_model->getShippingMethods();

        if ($this->webshop_settings->active_multi_outlets) {
            $this->data['warehouses'] = $this->eshop_model->getEshopOutlets();
        } else {
            $this->data['warehouse_id'] = $this->webshop_settings->warehouse_id;
        }
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Shippings')));
        $meta = array('page_title' => lang('Shippings'), 'bc' => $bc);
        $this->page_construct('eshop/shipping_methods', $meta, $this->data);
    }
    
    public function manage_products($category_id = null) {
        
        $this->load->model('products_model');
        $this->data['products'] = null;
        if ($category_id) {

            $this->data['category_id'] = $category_id;
            $this->data['subcategories'] = $this->products_model->getCategories($category_id);
            $this->data['products'] = $this->products_model->getCategoryProducts($category_id);
        }

        $this->data['categories'] = $this->products_model->getCategories('', 'parent_id=0');

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('products')));
        $meta = array('page_title' => lang('Products'), 'bc' => $bc);
        $this->page_construct('webshop_settings/manage_products', $meta, $this->data);
    }
    
    public function settings() {
        
        $this->form_validation->set_rules('free_delivery_above_amount', lang('free_delivery_above_amount'), 'trim|required');
        $this->form_validation->set_rules('suport_email',           lang('suport_email'), 'trim|required');
        $this->form_validation->set_rules('suport_phone',           lang('suport_phone'), 'trim|required');
        $this->form_validation->set_rules('return_within_days',     lang('return_within_days'), 'trim|required');
        $this->form_validation->set_rules('active_multi_outlets',   lang('active_multi_outlets'), 'trim|required');
        $this->form_validation->set_rules('overselling', lang('overselling'), 'trim|required');
        $this->form_validation->set_rules('rounding', lang('rounding'), 'trim|required');
        $this->form_validation->set_rules('cod', lang('cod'), 'trim|required');
        $this->form_validation->set_rules('online_payment', lang('online_payment'), 'trim|required');
        $this->form_validation->set_rules('warehouse_id', lang('warehouse_id'), 'trim|required');
        $this->form_validation->set_rules('biller_id', lang('biller_id'), 'trim|required');
        

        if ($this->form_validation->run() == TRUE) {

            $data = array(
                "free_delivery_above_amount" => $this->input->post('free_delivery_above_amount'),
                "suport_email"       => $this->input->post('suport_email'),
                "suport_phone"       => $this->input->post('suport_phone'),
                "return_within_days" => $this->input->post('return_within_days'),
                "overselling"        => $this->input->post('overselling'),
                "rounding"           => $this->input->post('rounding'),
                "cod"                => $this->input->post('cod'),
                "online_payment"     => $this->input->post('online_payment'),
                "warehouse_id"       => $this->input->post('warehouse_id'),
                "biller_id"          => $this->input->post('biller_id'),
            );

            if ($this->webshop_settings_model->updateWebshopSettings($data)) {
                $this->session->set_flashdata('message', lang('setting_updated'));
                redirect('webshop_settings/settings');
            } else {
                $this->session->set_flashdata('error', lang('setting_updated_failed'));
                redirect('webshop_settings/settings');
            }
        } else {

            $this->data['webshop_settings'] = $this->webshop_settings;
            $this->data['warehouses']       = $this->webshop_settings_model->get_warehouses();
            $this->data['billers']          = $this->webshop_settings_model->get_billers();            
            
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Ecommerce Settings')));
            $meta = array('page_title' => lang('Ecommerce Settings'), 'bc' => $bc);

            $this->page_construct('webshop_settings/settings', $meta, $this->data);
        }
    }
    
     public function webshop_ajax_request() {
    
        $action = $_POST['action'];
        $postData = $_POST;
    
        switch ($action) {
                        
            case "manage_eshop_category":
                
                $this->manage_eshop_category($postData);
                
                break;
            
            case "manage_eshop_product":
                
                $this->manage_eshop_product($postData);
                
                break;
            
            default:
                break;
        }//end switch.
}

    
    public function manage_eshop_category($postData) {
        
        $category_id  = isset($postData['category_id'])  ? $postData['category_id']  : false;
        $parent_id    = ((bool)$postData['parent_id'])   ? $postData['parent_id']    : 0;
        $eshop_status = isset($postData['eshop_status']) ? $postData['eshop_status'] : 0;
        $data['in_eshop'] = $eshop_status;
        $where = null;
        if((bool)$category_id){
            $where['id'] = $category_id;
        } 
        elseif(!(bool)$category_id && (bool)$parent_id ){
            $where['parent_id'] = $parent_id;
        }
        $response = [
                    'status_code'   => 500,
                    'status'        => 'ERROR',
                    'messages'      => 'Failed'
                ];
        
        if($where) {
            if($this->webshop_settings_model->update_eshop_status('categories', $where, $data)) {
                $response = [
                    'status_code'   => 200,
                    'status'        => 'SUCCESS',
                    'messages'      => 'Updated'
                ];
            }
        }
        
        echo json_encode($response);
    }
    
    public function manage_eshop_product($postData) {
        
        $product_id = isset($postData['product_id']) ? $postData['product_id'] : false;
        $variant_id = isset($postData['variant_id']) ? $postData['variant_id'] : 0;
        $eshop_status = isset($postData['eshop_status']) ? $postData['eshop_status'] : 0;
        $data['in_eshop'] = $eshop_status;
        $where = null;
        
        if((bool)$product_id){
            if((bool)$variant_id){
                $where['id'] = $variant_id;
                $where['product_id'] = $product_id;
                $tablename = "product_variants";
            } else {
                $where['id'] = $product_id;
                $tablename = "products";
            }
        }
        
        $response = [
                    'status_code'   => 500,
                    'status'        => 'ERROR',
                    'messages'      => 'Failed'
                ];
        
        if($where) {
            if($this->webshop_settings_model->update_eshop_status($tablename, $where, $data)) {
                $response = [
                    'status_code'   => 200,
                    'status'        => 'SUCCESS',
                    'messages'      => 'Updated'
                ];
            }
        }
        
        echo json_encode($response);
        
    }
    
    
    
    
    
    
}
//End class
