<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reciept extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->lang->load('sales', $this->Settings->user_language);
        $this->load->helper('text');
        $this->load->library('form_validation');
        $this->load->model('orders_model');
        $this->load->model('sales_model');
        $this->load->model('eshop_model');
	    $this->load->helper('sms');
        $this->digital_upload_path = 'files/'.$this->Customer_assets;
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';
        $this->data['logo'] = true;
        $this->pos_settings = $this->site->get_pos_setting();
        $this->data['pos_settings'] = $this->pos_settings;
    }

    public function send_sms() {
         
        $code = $this->input->get('code');
       
        $res = $this->eshop_model->validateRecieptSales($code);
      
        if ($res) {
            $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        } else {
            die('Invalide receipt code');
        }

        if (!$sale_id) {
            die('No sale selected.');
        }

        $inv = $this->sales_model->getInvoiceByID($id);
        $customer_Award =  $this->db->select('award_points')->where(['id' => $inv->customer_id])->get('companies')->row();
        $sms_template = $this->Settings->sms_massage;
      
        $placeholders_data = array(
            "site_name"         => $this->Settings->site_name,
            "default_currency"  => $this->Settings->default_currency,
            "grand_total"       => $this->sma->formatDecimal($inv->grand_total),
            "award_points"      => $customer_Award->award_points
        );
        $str = replace_placeholders($sms_template, $placeholders_data);
           
            $url = base_url('reciept/pdf/') . $code;
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://lntp.in?key=hbfsbfnbkfsdhbkdgf367n&q='.$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

        $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
        $no = $this->input->get('phone');
        //$url = $this->get_tiny_url(base_url('reciept/pdf/') . $code);
        $url = $response->url;
       // $pass_str = $str . ' You can view receipt ' . $url;
		$pass_str = $str .' '. $url;
        $this->CallSMS($no, $pass_str, 'SALE_INVOICE');
    }

    /**
     * New Eshop SMS 
     */
    public function send_sms_eshop() {
        $code = $this->input->get('code');
        $res = $this->eshop_model->validateRecieptOrder($code);

        if ($res) {
            $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        }

        if (!$sale_id) {
            die('No sale selected.');
        }

        $inv = $this->orders_model->getOrderByID($id);
        $Arr['sale_id'] = $id;
        $ResEshopOrder = $this->eshop_model->getOrderDetails($Arr);
//print_r($ResEshopOrder); exit;
        $Payment = $this->orders_model->getPaymentsForOrder($id);

        $PaidSt = '';
        if (!empty($Payment)) {
            if ($Payment->paid_by != 'cash') {
                $PaidSt = '. Paid by ' . $Payment->paid_by . '(' . $Payment->transaction_id . ')';
            }
        }

        $str = '';
        if ($inv->grand_total):
            $str = 'Hello, your Order is placed successfully. Order Id is ' . $inv->invoice_no . '. to view order receipt click on';

        // $str = 'Hello, your Order is placed successfully. Order Reference '.$inv->reference_no.'. to view order receipt click on';
        /* $str = 'Thanks for visiting '.$this->Settings->site_name.'. Your Total Bill amount '.$this->sma->formatDecimal($inv->grand_total).' '.$this->Settings->default_currency; */
        endif;

        $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : false;
        $no = $this->input->get('phone');

        $url = $this->get_tiny_url(base_url('reciept/pdforder/') . $code);
        $pass_str = $str . '  ' . $url;
        $this->CallSMS($no, $pass_str, 'ESHOP_ORDER_PLACED');

        // merchant revives a new order
        $WarehouseId = $this->data['pos_settings']->default_eshop_warehouse;
        $RowWarehouse = $this->orders_model->getWarehouseByID($WarehouseId);
        $eshop_details = $this->eshop_model->getEshopSettings('1');
        $BillerPhone = $RowWarehouse->phone;
        $Billerto = $RowWarehouse->email;

        $mstr = '';
        if ($inv->grand_total):
            $mstr = 'New order Received from ' . $inv->customer . ' - +91' . $no . ' Order Id:- ' . $inv->invoice_no . ' ' . $url . ' Order Type:- ' . $ResEshopOrder[0]['shipping_method_name'] . ', Order Amount:- Rs.' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency . ' ' . $PaidSt; //.'. Paid by '.$Payment->paid_by.$transaction_id;
        endif;
        $this->CallSMS($BillerPhone, $mstr, 'ESHOP_ORDER_RECEIVED');  
    }

    public function quote_sms($quote_id, $phone) {
        $this->load->model('quotes_model');
        $code = md5('quote_reciept' . $quote_id);
        $res = $this->quotes_model->validateRecieptQuote($code);
        //print_r($res);
        if (!$res) {
            
        }
        $quote_id = $id = isset($res[0]['id']) ? $res[0]['id'] : FALSE;
        if (!$quote_id) {
            die('No quote selected.');
        }

        $inv = $this->quotes_model->getQuoteByID($quote_id);
        //$this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        //print_r($this->data['customer']);
        //print_r($inv);exit;
        $str = '';
        if ($inv->grand_total):
            $str = 'Thanks for visiting ' . $this->Settings->site_name . '. Your Total Quote amount ' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency;
        endif;

        $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : FALSE;
        $no = ($this->input->get('phone')) ? $this->input->get('phone') : $phone;
        //$no = "9881815256";
        $url = $this->get_tiny_url(base_url('quotes/pdf_view/') . $code);
        $pass_str = $str . ' You can view reciept   ' . $url;
        //$this->db->update('quotes', array('status' => 'sent'), array('id' => $quote_id));
        $this->CallSMS($no, $pass_str, 'QUOTATION_RECEPT');
    }

    public function purchase_sms($purchase_id, $phone) {
        $this->load->model('purchases_model');
        $code = md5('purchase_reciept' . $purchase_id);
        $res = $this->purchases_model->validateRecieptPurchase($code);
        if (!$res) {
            
        }
        $quote_id = $id = isset($res[0]['id']) ? $res[0]['id'] : FALSE;
        if (!$quote_id) {
            die('No quote selected.');
        }
        $inv = $this->purchases_model->getPurchaseByID($purchase_id);
        $str = '';
        if ($inv->grand_total):
            $str = 'Thanks for visiting ' . $this->Settings->site_name . '. Your Total Purchase Invoice ' . $this->sma->formatDecimal($inv->grand_total) . ' ' . $this->Settings->default_currency;
        endif;
        $grand_total = isset($res[0]['grand_total']) ? $res[0]['grand_total'] : FALSE;
        $no = ($this->input->get('phone')) ? $this->input->get('phone') : $phone;
        $url = $this->get_tiny_url(base_url('purchases/pdf_view/') . $code);
        $pass_str = $str . ' You can view reciept   ' . $url;
        $this->db->update('quotes', array('status' => 'sent'), array('id' => $quote_id));
        $this->CallSMS($no, $pass_str, 'PURCHESE_INVOICE');
    }

    function get_tiny_url($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, 'http://tinyurl.com/api-create.php?url=' . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return  $data !== 'Error' ? $data : $url;
    }

    function CallSMS($mobile, $msg, $sms_template_key = NULL) {

        if ($sms_template_key == NULL) {
            echo '{"msg":"SMS template key is null."}';
        } else {
             
            $res = $this->sma->SendSMS($mobile, $msg, $sms_template_key);
            
            if (!empty($res)):
                $Obj = json_decode($res);
                if (isset($Obj) && $Obj->Status == 'Success'):
                    $rec['sms_log'] = $this->sma->setSMSLog($mobile, $msg, $Obj->Description);
                    $rec['sms_balance_update'] = $this->sma->update_sms_count(1);
                    echo '{"msg":"Your receipt has been successfully sent by SMS."} ';
                else:
                    echo '{"msg": "' . $Obj->Description . '"} ';
                endif;
            else:
                echo '{"msg": "Error , Please try again "} ';
            endif;
        }
    }

    public function pdf($id = null, $view = null, $save_bufffer = null) {
        if ($this->input->get('id')) {
            $code = $this->input->get('id');
        }

        $res = $this->eshop_model->validateRecieptSales($id);
        if (!$res) {
            
        }
        $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        if (!$sale_id) {
            die('No sale selected.');
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->sales_model->getInvoiceByID($id);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->sales_model->getAllTaxItems($id, $inv->return_id);
        endif;
        $isGstSale = $this->site->isGstSale($id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;
        $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($inv->id, $inv->return_id);

        $this->default_currency = $this->site->getCurrencyByCode($this->Settings->default_currency);
        $this->data['default_currency'] = $this->default_currency;
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->sales_model->getPaymentsForSale($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->sales_model->getAllInvoiceItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getInvoiceByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllInvoiceItems($inv->return_id) : NULL;

        if ($inv->eshop_sale) {
            $this->data['shipping_details'] = $this->sales_model->getShipingDetails($inv->order_no);
        }
        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . '_' . time() . ".pdf";
        $Settings = $this->Settings; //$this->site->get_setting();
        //Set Sale items image
        $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;
        if (!empty($this->data['rows'])) {
            foreach ($this->data['rows'] as $key => $row) {
                $this->load->model('pos_model');
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $this->data['rows'][$key]->image = $product->image;
                if (!empty($row->option_id)) {
                    $this->data['rows'][$key]->product_name = $row->product_name . ' (' . $row->variant . ')';
                }
            }
        }

        $html = $this->load->view($this->theme . 'sales/pdf_reciept', $this->data, true);

        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }

        $file_path = $this->sma->generate_pdf($html, $name, 'S', $this->data['biller']->invoice_footer);
        if (!empty($file_path)) {
            $file_path1 = FCPATH . $file_path;
            if (file_exists($file_path1)) :
                $_url = base_url($file_path);
                $this->sma->md($_url);
                exit;
            endif;
        }
    }

    /**
     * Eshop order 
     * @param type $id
     * @param type $view
     * @param type $save_bufffer
     */
    public function pdforder($id = null, $view = null, $save_bufffer = null) {
        if ($this->input->get('id')) {
            $code = $this->input->get('id');
        }

        $res = $this->eshop_model->validateRecieptOrder($id);
        if (!$res) {
            
        }
        $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        if (!$sale_id) {
            die('No sale selected.');
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $inv = $this->orders_model->getOrderByID($id);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;
        $isGstSale = $this->site->isGstSale($id);
        $inv->GstSale = !empty($isGstSale) ? 1 : 0;
        $this->data['taxItems'] = $this->orders_model->getAllTaxItemsGroup($inv->id, $inv->return_id);

        $this->default_currency = $this->site->getCurrencyByCode($this->Settings->default_currency);
        $this->data['default_currency'] = $this->default_currency;
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
        $this->data['payments'] = $this->orders_model->getOrderPayments($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;
        $this->data['eshop_settings'] = $this->orders_model->getEshopSettings();
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);
        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . '_' . time() . ".pdf";
        $Settings = $this->Settings; //$this->site->get_setting();
        //Set Sale items image
        $this->data['inv']->invoice_product_image = $Settings->invoice_product_image;
        if (!empty($this->data['rows'])) {
            foreach ($this->data['rows'] as $key => $row) {
                $this->load->model('pos_model');
                $product = $this->pos_model->getProductByID($row->product_id, $select = 'image');
                $this->data['rows'][$key]->image = $product->image;
            }
        }

        $html = $this->load->view($this->theme . 'orders/pdf_reciept', $this->data, true);

        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }

        $file_path = $this->sma->generate_pdf($html, $name, 'S', $this->data['biller']->invoice_footer);
        if (!empty($file_path)) {
            $file_path1 = FCPATH . $file_path;
            if (file_exists($file_path1)) :
                $_url = base_url($file_path);
                $this->sma->md($_url);
                exit;
            endif;
        }
    }

    public function post_to_url($url, $data) {
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

    public function pdf_eshop_order($id = null, $view = null, $save_bufffer = null) {
        if ($this->input->get('id')) {
            $code = $this->input->get('id');
        }

        $res = $this->eshop_model->validateRecieptEshopOrder($id);
        if (!$res) {
            
        }
        $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        if (!$sale_id) {
            die('No sale selected.');
        }

        $_PID = $this->Settings->default_printer;
        $this->data['default_printer'] = $this->site->defaultPrinterOption($_PID);


        $inv = $this->orders_model->getOrderByID($id);

        if ($this->data['default_printer']->tax_classification_view):
            $inv->rows_tax = $this->orders_model->getAllTaxOrderItems($id, $inv->return_id);
        endif;

        $this->data['taxItems'] = $this->orders_model->getAllTaxItemsGroup($id, $inv->return_id);

        $this->default_currency = $this->site->getCurrencyByCode($this->Settings->default_currency);
        $this->data['default_currency'] = $this->default_currency;
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $inv->reference_no) . "' alt='" . $inv->reference_no . "' class='pull-left' />";
        $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);

        $this->data['payments'] = $this->orders_model->getPaymentsForOrder($id);
        $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
        $this->data['billerDetails'] = $this->orders_model->getOrderDetails($id);
        $this->data['user'] = $this->site->getUser($inv->created_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
        $this->data['inv'] = $inv;
        $this->data['rows'] = $this->orders_model->getAllOrderItems($id);
        $this->data['return_sale'] = $inv->return_id ? $this->orders_model->getOrderByID($inv->return_id) : NULL;
        $this->data['return_rows'] = $inv->return_id ? $this->orders_model->getAllOrderItems($inv->return_id) : NULL;

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . '_' . time() . ".pdf";
        $Settings = $this->Settings; //$this->site->get_setting();
        //Set Sale items image
        // $this->data['inv']->invoice_product_image= $Settings->invoice_product_image;
        /* if(!empty($this->data['rows'])){
          foreach ($this->data['rows'] as $key => $row) {
          $this->load->model('pos_model');
          $product = $this->pos_model->getProductByID($row->product_id, $select='image');
          $this->data['rows'][$key]->image = $product->image;
          }
          } */

        $html = $this->load->view($this->theme . 'orders/pdf_eshop_order', $this->data, true);

        if (!$this->Settings->barcode_img) {
            $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
        }

        $file_path = $this->sma->generate_pdf($html, $name, 'S', $this->data['biller']->invoice_footer);
        if (!empty($file_path)) {
            $file_path1 = FCPATH . $file_path;
            if (file_exists($file_path1)) :
                $_url = base_url($file_path);
                $this->sma->md($_url);
                exit;
            endif;
        }
    }

    /*     * *12-09-2020** */

    function verify_mobile() {
        if ($this->input->get('code')) {
            $code = $this->input->get('code', true);
        }
        if ($this->input->get('phone')) {
            $phone = $this->input->get('phone', true);
        }
        $msg = 'Your OTP for redeeming award point is ' . $code . $this->Settings->site_name . '.';
        $this->CallSMS($phone, $msg, 'REWARDS_POINT_OTP');
    }

    /*     * *12-09-2020** */
}
