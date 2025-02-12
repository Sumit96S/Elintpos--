<?php
/**
 * Created by PhpStorm.
 * User: ravi
 * Date: 10/26/2017
 * Time: 9:17 AM
 */

class Screens extends MY_Controller{
    public function __construct()
    {
        parent::__construct();

        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        if ($this->Customer || $this->Supplier) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->load->model('Screen_model');
         $this->load->model('pos_model');
		     $this->pos_settings = $this->pos_model->getSetting();
        $this->pos_settings->pin_code = $this->pos_settings->pin_code ? md5($this->pos_settings->pin_code) : null;
        $this->data['pos_settings'] = $this->pos_settings;
        $this->data['pos_settings']->pos_theme = json_decode($this->pos_settings->pos_theme);
    }

    function delivered($id,$item_quantity){
        return $this->Screen_model->delivered($id,array("isdelivered"=>$item_quantity));
    }

    function display($id){
        $this->data['pos_settingss'] = $this->data['pos_settings'];
        $this->data['division'] = $this->Screen_model->find($id);
        $this->data['id'] = $id;
        $this->data['today'] = date('Y-m-d H:i:s');
        $this->data['list'] = $this->Screen_model->getAllSuspendedBills($this->data);
        $this->data['default_printer'] = $this->Screen_model->getComboItemShow();
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('pos'), 'page' => lang('pos')), array('link' => '#', 'page' => lang('pos_sales')));
        $meta = array('page_title' => lang('screen'), 'bc' => $bc);
        
        $data_json['group_items'] = array();
        foreach($this->data['list'] as $key=>$val){
            
            $this->data['list'][$key]->items = '';
              
            if($val->product_type == 'combo'){
                $this->data['list'][$key]->items= $this->Screen_model->getComboProduct($val->product_id);
            }

            $val->suspend_note = (empty($val->suspend_note))?'No Table':$val->suspend_note;
            $data_json['group_items'][$val->suspend_note][] = $val;
        }
        $data_json['items'] = $this->data['list'];
        $data_json['settings'] = $this->data['Settings'];
        $this->data['data_json'] = json_encode($data_json);
        //print_r($this->data['data_json'] );exit;
        $this->page_construct('screens/index', $meta, $this->data);
    }


    // Restore specific version of database
    public function restore_db($file)
    {
        $path = '';
        if ($file=='latest')
            $path = FCPATH.'RAVI/latest.sql';
        else if ( in_array($file, $this->mBackupSqlFiles) )
            $path = FCPATH.'RAVI/'.$file;

        // proceed to execute SQL queries
        if ( !empty($path) && file_exists($path) )
        {
            //$sql = file_get_contents($path);
            //$this->db->query($sql);
            $username = $this->db->username;
            $password = $this->db->password;
            $database = $this->db->database;
            exec("mysql -u $username -p$password --database $database < $path");
        }

        echo "done";
    }

    function kot(){

        $this->data['list'] = $this->Screen_model->kot();
//print_r($this->data['list'] );exit;
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('pos'), 'page' => lang('pos')), array('link' => '#', 'page' => lang('pos_sales')));
        $meta = array('page_title' => "KOT", 'bc' => $bc);
        $this->page_construct('screens/kot', $meta, $this->data);
    }
}