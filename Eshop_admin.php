<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Eshop_admin extends MY_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }

        if (!$this->Owner) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect('welcome');
        }
        $this->load->library('form_validation');
        $this->load->model('settings_model');
        $this->load->model('pos_model');
        $this->load->model('eshop_model');

        $this->data['eshop_setting'] = $this->eshop_setting = $this->eshop_model->getEshopSettings('1');
    }

    public function pages() {
        $this->form_validation->set_rules('about_us', lang('About Us'), 'trim|required');
        $this->form_validation->set_rules('contact_us', lang('Contact Us'), 'trim|required');
        $this->form_validation->set_rules('terms', lang('Terms & conditions'), 'trim|required');
        $this->form_validation->set_rules('p_policy', lang('Privacy Policy'), 'trim|required');
        $this->form_validation->set_rules('faq', lang('FAQ'), 'trim|required');

        if ($this->form_validation->run() == true) {
            $data['about_us'] = $this->input->post('about_us', false);
            $data['contact_us'] = $this->input->post('contact_us', false);
            $data['terms'] = $this->input->post('terms', false);
            $data['p_policy'] = $this->input->post('p_policy', false);
            $data['faq'] = $this->input->post('faq', false);
            if ($res = $this->eshop_model->updateEshopPages(1, $data)):
                $this->session->set_flashdata('message', lang('Data_updated_successfully'));
                redirect("eshop_admin/pages");
            else:
                $this->session->set_flashdata('error', lang('Data_not_updated_successfully'));
                redirect('eshop_admin/pages');
            endif;
        }
        else {
            $this->data['pages'] = $this->eshop_model->getEshopPages(1);
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('notifications')));
            $meta = array('page_title' => lang('notifications'), 'bc' => $bc);
            $this->page_construct('eshop/pages', $meta, $this->data);
        }
    }

    public function settings() {

        $this->load->helper('html');

        $this->data['eshop_upload'] = $uploadPath = "assets/mdata/$this->Customer_assets/uploads/eshop_user/";

        if ($this->input->post('action') === 'save_settings') {

            $settings['facebook_link'] = !empty($this->input->post('facebook_link')) ? 'https://' . str_replace(['https://', 'http://'], '', $this->input->post('facebook_link')) : '';
            $settings['google_link'] = !empty($this->input->post('google_link')) ? 'https://' . str_replace(['https://', 'http://'], '', $this->input->post('google_link')) : '';
            $settings['twitter_link'] = !empty($this->input->post('twitter_link')) ? 'https://' . str_replace(['https://', 'http://'], '', $this->input->post('twitter_link')) : '';
            $settings['instagram_link'] = !empty($this->input->post('instagram_link')) ? 'https://' . str_replace(['https://', 'http://'], '', $this->input->post('instagram_link')) : '';

            $settings['shop_name'] = $this->input->post('shop_name');
            $settings['shop_phone'] = $this->input->post('shop_phone');
            $settings['shop_email'] = $this->input->post('shop_email');
            $settings['display_top_products'] = $this->input->post('display_top_products');
            $settings['display_hot_offers'] = $this->input->post('display_hot_offers');
            $settings['active_multi_outlets'] = $this->input->post('active_multi_outlets');
            $settings['user_login_action'] = $this->input->post('user_login_action');
            $settings['user_landing_page'] = $this->input->post('user_landing_page');
            $settings['order_cancel_duration'] = $this->input->post('order_cancel_duration');


            $default_banner = $this->input->post('default_banner');

            $settings['homepage_image_text_1'] = $this->input->post('homepage_image_text_1');
            $settings['homepage_image_text_1_2'] = $this->input->post('homepage_image_text_1_2');
            $settings['homepage_image_text_2'] = $this->input->post('homepage_image_text_2');
            $settings['homepage_image_text_3'] = $this->input->post('homepage_image_text_3');
            $settings['show_homepage_images_text'] = $this->input->post('show_homepage_images_text');
            $settings['upi_id'] = $this->input->post('upi_id');

            /**
             * Payment Option
             */
            $settings['cash_on_delivery'] = $this->input->post('cash_on_delivery');
            $settings['qr_upi_payment'] = $this->input->post('qr_upi_payment');
            $settings['paytm_payment'] = $this->input->post('paytm_payment');
            $settings['accept_cc_dc_delivery'] = $this->input->post('accept_cc_dc_delivery');

            $settings['disabled_ordering'] = $this->input->post('disabled_ordering');
            $ordering_time_open = $this->input->post('ordering_time_open');
            $ordering_time_close = $this->input->post('ordering_time_close');
            $ordering_days = $this->input->post('ordering_days');

            $settings['ordering_time'] = !empty($ordering_time_open) && !empty($ordering_time_close) ? $ordering_time_open . '~' . $ordering_time_close : '';
            $settings['ordering_days'] = !empty($ordering_days) ? join(',', $ordering_days) : '';


            if (is_array($default_banner) && !empty($default_banner)) {
                $settings['default_banner'] = json_encode($default_banner);
            } else {
                $settings['default_banner'] = '';
            }

            //Copy Eshop Logo
            if (!empty($_FILES['eshop_logo']['tmp_name'])) {
                list($filename, $ext) = explode('.', $_FILES['eshop_logo']['name']);
                $logoImage = md5(time() . $filename) . '.' . $ext;
                if (copy($_FILES['eshop_logo']['tmp_name'], $uploadPath . $logoImage)) {
                    $settings['eshop_logo'] = $uploadPath . $logoImage;
                }
            }


            //Copy Payment QRCode
            if (!empty($_FILES['payment_qrcode']['tmp_name'])) {
                list($filename, $ext) = explode('.', $_FILES['payment_qrcode']['name']);
                $paymentQRImage = md5(time() . $filename) . '.' . $ext;
                if (copy($_FILES['payment_qrcode']['tmp_name'], $uploadPath . $paymentQRImage)) {
                    $settings['payment_qrcode'] = $uploadPath . $paymentQRImage;
                }
            }


            //Copy Eshop hot_offers_banner
            if (!empty($_FILES['hot_offers_banner']['tmp_name'])) {
                list($filename, $ext) = explode('.', $_FILES['hot_offers_banner']['name']);
                $logoImage = md5(time() . $filename) . '.' . $ext;
                if (copy($_FILES['hot_offers_banner']['tmp_name'], $uploadPath . $logoImage)) {
                    $settings['hot_offers_banner'] = $uploadPath . $logoImage;
                }
            }

            //Copy Banners
            if (!empty($_FILES['banner_image']['tmp_name'])) {
                $i = 0;
                foreach ($_FILES['banner_image']['name'] as $key => $file) {

                    if (empty($file))
                        continue;
                    $i++;
                    list($filename, $ext) = explode('.', $file);
                    //$bannerImage = "banner_static_".$key.'.'.$ext;
                    $bannerImage = md5(time() . $filename) . '.' . $ext;

                    if (copy($_FILES['banner_image']['tmp_name'][$key], $uploadPath . $bannerImage)) {
                        $settings['banner_image_' . $key] = $uploadPath . $bannerImage;
                    }
                }//end foreach
            }

            //Copy Homepahe Images
            if (!empty($_FILES['homepage_image']['tmp_name'])) {

                foreach ($_FILES['homepage_image']['name'] as $key => $file) {

                    if (empty($file))
                        continue;

                    list($filename, $ext) = explode('.', $file);
                    // $homepageImage = "homepage_image_$key.$ext";
                    $homepageImage = md5(time() . $filename) . '.' . $ext;
                    if (copy($_FILES['homepage_image']['tmp_name'][$key], $uploadPath . $homepageImage)) {
                        $settings['homepage_image_' . $key] = $uploadPath . $homepageImage;
                    }
                }//end foreach
            }

            if (!empty($settings)) {
                $rec = $this->eshop_model->updateEshopSettings(1, $settings);
            }

            if ($rec) {
                unset($_POST);
                $this->session->set_flashdata('message', lang("Eshop_Setting_Updated"));
                $this->session->keep_flashdata('message');

                redirect('eshop_admin/settings');
            } else {
                $this->page_construct('eshop/settings', $meta, $this->data);
            }
        } else {
            $this->data['eshop_settings'] = $this->eshop_model->getEshopSettings(1);
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('settings')));
            $meta = array('page_title' => lang('settings'), 'bc' => $bc);
            $this->page_construct('eshop/settings', $meta, $this->data);
        }
    }

    public function shipping_methods() {

        if ($this->input->post('action') === 'save_shipping') {

            $settingFiled = [
                'delivery_pincode' => ($this->input->post('delivery_pincode')) ? $this->input->post('delivery_pincode') : NULL,
            ];

            $this->db->where(['id' => '1'])->update('eshop_settings', $settingFiled);


            foreach ($_POST['price'] as $id => $price) {
                $price = (is_numeric($price)) ? $price : 0;
                $codeName = str_replace('_', ' ', $_POST['code'][$id]);
                $batchArr[] = array(
                    'id' => $id,
                    'price' => $price,
                    'name' => (isset($_POST['name'][$id]) && !empty($_POST['name'][$id])) ? $_POST['name'][$id] : $codeName,
                    'is_active' => isset($_POST['active'][$id]) ? $_POST['active'][$id] : NULL,
                    'all_time' => isset($_POST['alltime'][$id]) ? $_POST['alltime'][$id] : NULL,
                    'order_to_warehouse' => isset($_POST['order_to_warehouse'][$id]) ? $_POST['order_to_warehouse'][$id] : NULL,
                    'minimum_order_amount' => isset($_POST['minimum_order_amount'][$id]) ? $_POST['minimum_order_amount'][$id] : 0,
                );

                if (!empty($_POST[$id . '_slots_start_time_method'])) {
                    foreach ($_POST[$id . '_slots_start_time_method'] as $key => $value) {
                        if ($_POST[$id . '_slots_start_time_method'][$key]) {
                            $slotstime = array(
                                'shipping_method_id' => $id,
                                'start_time' => ($_POST[$id . '_slots_start_time_method'][$key]) ? $_POST[$id . '_slots_start_time_method'][$key] : NULL,
                                'end_time' => ($_POST[$id . '_slots_end_time_method'][$key]) ? $_POST[$id . '_slots_end_time_method'][$key] : NULL,
                            );
                            $this->eshop_model->add_shippin_slotes($slotstime);
                        }
                    }
                }
            }//end foreach.

            if (is_array($batchArr)) {
                $rec = $this->db->update_batch('eshop_shipping_methods', $batchArr, 'id');

                $redirecturl = str_replace(base_url(), '', $_SERVER['HTTP_REFERER']);
                
                if ($rec) {
                    unset($_POST);
                    $this->session->set_flashdata('message', lang("Shippping Methods Updated"));
                    $this->session->keep_flashdata('message');
                    redirect($redirecturl);
                    //redirect('eshop_admin/shipping_methods');
                } else {
                   // redirect('eshop_admin/shipping_methods');
                    //$this->page_construct('eshop/shipping_methods', $meta, $this->data);
                     redirect($redirecturl);
                }
               
            }
        } else {

            $this->data['shippings'] = $this->eshop_model->getShippingMethods();

            if ($this->eshop_setting->active_multi_outlets) {
                $this->data['warehouses'] = $this->eshop_model->getEshopOutlets();
            } else {
                $this->data['warehouse_id'] = $this->pos_model->getSetting('default_eshop_warehouse');
            }
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Shippings')));
            $meta = array('page_title' => lang('Shippings'), 'bc' => $bc);
            $this->page_construct('eshop/shipping_methods', $meta, $this->data);
        }
    }

    public function deleteimage() {

        $fieldname = $this->uri->segment(3);

        if (!empty($fieldname)) {
            $eshop_settings = $this->eshop_model->getEshopSettings(1);

            if (unlink(str_replace(base_url(), '', $eshop_settings->$fieldname))) {
                $this->eshop_model->updateEshopSettings(1, [$fieldname => '']);
            }
        }

        redirect('eshop_admin/settings');
    }

    /**
     * 
     * @param type $key
     * @param type $pincode
     */
    public function actionPincode($action=null, $pincode=null) {

        $action = $action ? $action : $_POST['action'];
        $data['pincode'] = $pincode ? $pincode : $_POST['pincode'];
        if ($action == 'add') {
            $data['warehouse'] = $_POST['warehouse'];
            $data['charges']   = $_POST['charges'];  
            $data['delivery_time_from'] = $_POST['delivery_time_from'];
            $data['delivery_time_till'] = $_POST['delivery_time_till'];
        }

        if ($data['pincode']) {
            $result = $this->eshop_model->pincodeaction($action, $data);
            echo json_encode($result);
        } else {
            echo false;
        }
    }

    /**
     * Get Pincode List
     */
    public function pincodes() {
        $result = $this->eshop_model->pincodeaction('list', '');

        if ($result) {
            $html = '<table class="table table-bordered"><thead><tr><th>Pincode</th><th> Charges </th><th>Outlet</th><th>Delivery Time</th><th></th></tr></thead>';
            $warehouses = $this->eshop_model->getEshopOutlets();
            
            foreach ($result as $pincodevalue) {
                $html .= '<tr><td>' . $pincodevalue->pincode . '</td><td> '.$this->sma->formatMoney($pincodevalue->charges).' </td><td>' . $warehouses[$pincodevalue->warehouse_id] . '</td><td>' . $pincodevalue->delivery_time . '</td><td><i class="fa fa-times" onclick="deletepincode(' . $pincodevalue->pincode . ')"> </i></td><tr>';
            }
            $html .= '</table>';
        } else {
            $html = 'Records not found';
        }

        echo $html;
    }

    public function manage_products($category_id = null) {

        if ($this->input->post('action') === 'save_changes') {

            $categories = $this->input->post('categories');
            $products = $this->input->post('products');

            $this->db->query("Update `sma_categories` set `in_eshop` = '0' ");

            if (count($categories)) {

                $categories_in = join(',', $categories);
                $this->db->query("Update `sma_categories` set `in_eshop` = '1' where `id` IN ($categories_in) ");
            }

            $this->db->query("Update `sma_products` set `in_eshop` = '0' where `category_id` = '$category_id' ");

            if (count($products)) {
                $products_in = join(',', $products);
                $this->db->query("Update `sma_products` set `in_eshop` = '1' where `id` IN ($products_in) ");
            } else {
                $this->db->query("Update `sma_categories` set `in_eshop` = '0' where `id` = '$category_id' ");
            }

            $this->session->set_flashdata('message', lang("Changes Updated successfully"));
            $this->session->keep_flashdata('message');

            $redirecturl = str_replace(base_url(), '', $_SERVER['HTTP_REFERER']);
            
            redirect($redirecturl);
        } else {

            $this->load->model('products_model');
            $this->data['products'] = null;
            if ($category_id) {

                $this->data['category_id'] = $category_id;
                $this->data['products'] = $this->products_model->getCategoryProducts($category_id);
            }

            $this->data['categories'] = $this->products_model->getCategories('', 'parent_id=0');

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('products')));
            $meta = array('page_title' => lang('Products'), 'bc' => $bc);
            $this->page_construct('eshop/eshop_products', $meta, $this->data);
        }
    }

}
