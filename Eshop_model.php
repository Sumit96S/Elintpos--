<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Eshop_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function getShippingMethods($arr = []) {
        if (is_array($arr)) {
            $q = $this->db->get_where('eshop_shipping_methods', $arr, null);
            
            if ($q->num_rows() > 0) {
                return $q->result_array();
            }
        } else {
            $q = $this->db->get('eshop_shipping_methods');

            if ($q->num_rows() > 0) {
                return $q->result_array();
            }
        }
        return FALSE;
    }

    public function getSaleByReff($reffNo) {

        return $this->site->getSaleByReference($reffNo);
    }

    public function add_sale_order(array $data) {

        $query = $this->db->insert('orders', $data);

        if ($query) {
            $cid = $this->db->insert_id();
            return $cid;
        } else {
            $error = $this->db->error();
            echo '<div class="alert alert-danger">Error: ' . $error['message'] . '</div>';
            return false;
        }
    }

    public function updateSales($id, $data = array()) {

        $this->db->where('id', $id);
//        $this->db->update('sales', $data)
        if ($this->db->update('orders', $data)) {
            return $id;
        }
        return false;
    }

    public function addOrder($data = array()) {

        if ($this->db->insert('eshop_order', $data)) {
            $cid = $this->db->insert_id();
            return $cid;
        }
        return false;
    }

    public function updateOrder($id, $data = array()) {

        $this->db->where('id', $id);
        if ($this->db->update('eshop_order', $data)) {
            return $id;
        }
        return false;
    }

    public function add_sales_order_items($data = array()) {                
        if ($this->db->insert('order_items', $data)) {
            $cid = $this->db->insert_id();
            return $cid;
        } else {
            echo $this->db->_error_message();
            exit;
        }
        return false;
    }

    public function update_warehouse_stocks($product_id, $quantity, $warehouse) {

        $sql = "UPDATE `sma_warehouses_products` SET `quantity` = `quantity`- $quantity WHERE `warehouse_id`='$warehouse' AND `product_id`='$product_id' ";

        if ($this->db->query($sql)) {
            return TRUE;
        }
        return FALSE;
    }

    public function update_products_stocks($product_id, $quantity) {

        $sql = "UPDATE `sma_products` SET `quantity` = `quantity`- $quantity WHERE `id`='$product_id' ";

        if ($this->db->query($sql)) {
            return TRUE;
        }
        return FALSE;
    }

    public function addSalesItemTaxAttr(array $data) {
//        $this->db->insert('sales_items_tax', $data)
        if ($this->db->insert('orders_items_tax', $data)) {
            $taxAttrId = $this->db->insert_id();
            return $taxAttrId;
        }
        return false;
    }

    public function instamojoEshop($data) {
        $this->load->library('instamojo');
        $ci = get_instance();
        $ci->config->load('payment_gateways', TRUE);
        $payment_config = $ci->config->item('payment_gateways');
        $instamojo_credential = $payment_config['instamojo'];
        $api = new Instamojo($instamojo_credential['API_KEY'], $instamojo_credential['AUTH_TOKEN'], $instamojo_credential['API_URL']);
        $res = array();
        try {

            $response = $api->paymentRequestCreate(array(
                "purpose" => $data['x_description'],
                "amount" => $data['x_amount'],
                "send_email" => true,
                "email" => $data['email'],
                "phone" => $data['mobile'],
                "redirect_url" => $data['notify_url'],
            ));
            if (is_array($response)):
                $json_decode = $response;
            elseif (is_string($response)) :
                $json_decode = json_decode($response, true);
            endif;


            if (isset($json_decode['longurl']) && !empty($json_decode['longurl'])) {
                $res['longurl'] = $json_decode['longurl'];
                $arr = array();
                $arr['order_id'] = $data["x_invoice_num"];
                $arr['request_response'] = serialize($json_decode);
                $arr['update_date'] = date("Y-m-d H:i:s");
                $arr['request_id'] = $json_decode['id'];
                $this->db->insert('instamojo', $arr);
            }
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
        }
        return $res;
    }

    public function getInstamojoEshopTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('instamojo', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updateInstamojoEshopTransaction($id, $data = array()) {
        $this->db->where('request_id', $id);
        if ($this->db->update('instamojo', $data)) {
            return true;
        }
        return false;
    }

    public function instomojoEshopAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['payment_id'];
        $payment['amount'] = $result['amount'];
        $payment['currency'] = $result['currency'];
        $payment['sale_id'] = $sid;
        $payment['paid_by'] = 'instomojo';
        $payment['date'] = $result['created_at'];
        $payment['reference_no'] = 'PAY/' . $sid . '/' . $this->site->getReference('pay');
        $payment['type'] = 'received';
        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):
            $this->updateSales($sid, array('sale_status' => 'completed'));
            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;
        return false;
    }

    public function getOrderDetails($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('eshop_order', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->result_array();
            }
        endif;
        return FALSE;
    }

    public function validateCODSales($TransKey = NULL, $User_id = NULL) {

        /* $this->db->select('sales.id, sales.reference_no, orders.invoice_no,sales.customer');
          $this->db->from('sales');
          $this->db->join('payments', 'sales.id =  payments.sale_id','left');
          if(!empty($User_id)):
          $this->db->where('sales.customer_id', $User_id);
          endif;

          if(!empty($TransKey)):
          $this->db->where( " MD5(CONCAT('COD',sma_sales.`reference_no`))", $TransKey);
          endif;

          $this->db->order_by("sales.id ","desc");
          $q = $this->db->get();
          if ($q->num_rows() > 0) :
          return $q->result_array();
          endif;
          return FALSE; */


        $this->db->select('orders.id, orders.reference_no, orders.invoice_no,orders.customer');
        $this->db->from('orders');
        $this->db->join('payments', 'orders.id =  payments.sale_id', 'left');
        if (!empty($User_id)):
            $this->db->where('orders.customer_id', $User_id);
        endif;

        if (!empty($TransKey)):
            $this->db->where(" MD5(CONCAT('COD',sma_orders.`reference_no`))", $TransKey);
        endif;

        $this->db->order_by("orders.id ", "desc");
        $q = $this->db->get();
        if ($q->num_rows() > 0) :
            return $q->result_array();
        endif;
        return FALSE;
    }

    public function getSalesDetails($sale_id) {

        if (!$sale_id)
            return false;

        $q = $this->db->get('sales')->where("id", $sale_id);

        if ($q->num_rows() > 0) :
            return $q->result_array();
        endif;

        return false;
    }

    public function validateSales($TransKey = NULL, $User_id = NULL, $OrderId = NULL) {
        $this->db->select('sales.id');
        $this->db->from('sales');
        $this->db->join('payments', 'sales.id =  payments.sale_id', 'left');
        if (!empty($User_id)):
            $this->db->where('sales.customer_id', $User_id);
        endif;

        if (!empty($TransKey)):
            $this->db->where('payments.transaction_id', $TransKey);
        endif;

        if (!empty($OrderId)):
            $this->db->where('sales.id', $OrderId);
        endif;

        $this->db->order_by("sales.id ", "desc");
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function validateSalesEshop($TransKey = NULL, $User_id = NULL, $OrderId = NULL) {
        $this->db->select('orders.id');
        $this->db->from('orders');
        $this->db->join('payments', 'orders.id =  payments.sale_id', 'left');
        if (!empty($User_id)):
            $this->db->where('orders.customer_id', $User_id);
        endif;

        if (!empty($TransKey)):
            $this->db->where('payments.transaction_id', $TransKey);
        endif;

        if (!empty($OrderId)):
            $this->db->where('orders.id', $OrderId);
        endif;

        $this->db->order_by("orders.id ", "desc");
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getAllSalesByUser($param) {
        $User_id = isset($param['user_id']) && !empty($param['user_id']) ? $param['user_id'] : NULL;
        $limit = isset($param['limit']) && !empty($param['limit']) ? $param['limit'] : NULL;
        $offset = isset($param['offset']) && !empty($param['offset']) ? $param['offset'] : 0;
        $sort_field = isset($param['sort_field']) && !empty($param['sort_field']) ? $param['sort_field'] : 'sales.id';
        $sort_dir = isset($param['sort_dir']) && !empty($param['sort_dir']) ? $param['sort_dir'] : 'desc';
        $search_by = isset($param['search_by']) && !empty($param['search_by']) ? $param['search_by'] : NULL;
        $search_param = isset($param['search_param']) && !empty($param['search_param']) ? $param['search_param'] : NULL;
        if (!empty($search_by) && is_array($search_param)):
            switch ($search_by) {
                case 'order_ref':
                    if (empty($search_param['order_ref'])):
                        return false;
                    endif;
                    $this->db->where('sales.reference_no', $search_param['order_ref']);
                    break;

                case 'order_date':
                    if (empty($search_param['order_date1']) || empty($search_param['order_date2'])):
                        return false;
                    endif;
                    $this->db->where('date(sales.`date`) between  ' . " '" . $search_param['order_date1'] . "'  and '" . $search_param['order_date2'] . "' ");
                    break;

                case 'pay_status':
                    if (empty($search_param['pay_status'])):
                        return false;
                    endif;
                    $this->db->where('sales.payment_status', $search_param['pay_status']);
                    break;

                case 'pay_ref':
                    if (empty($search_param['pay_ref'])):
                        return false;
                    endif;
                    $this->db->where('payments.reference_no', $search_param['pay_ref']);
                    break;

                case 'pay_trans':
                    if (empty($search_param['pay_trans'])):
                        return false;
                    endif;
                    $this->db->where('payments.transaction_id', $search_param['pay_trans']);
                    break;

                default:
                    break;
            }
        endif;

        if (empty($User_id)):
            return false;
        endif;

        $this->db->select("sales.id as order_id,sales.reference_no as order_no,DATE_FORMAT(sma_sales.date,'%b %d %Y %h:%i %p') as order_date,"
                . "sales.payment_status ,payments.reference_no as payment_no,payments.transaction_id as transaction_no"
                . ", deliveries.do_reference_no  as delivery_reference_no"
                . ", deliveries.status  as delivery_status"
        );
        $this->db->from('sales');
        $this->db->join('payments', 'sales.id =  payments.sale_id', 'left');
        $this->db->join('deliveries', 'sales.id =  deliveries.sale_id', 'left');
        $this->db->where('sales.customer_id', $User_id);
        $this->db->where("sales.sale_status!='pending'");

        //--------------SORT ------------------------------
        if (!empty($sort_field) && !empty($sort_dir)):
            $this->db->order_by($sort_field, $sort_dir);
        endif;

        //--------------Limit ------------------------------
        if (!empty($limit) && !empty($offset)):
            $this->db->limit($limit, $offset);
        endif;

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            $i = 1;
            foreach (($q->result()) as $row) {
                if (!empty($row->delivery_status)):
                    $row->order_status = @ucfirst($row->delivery_status);
                elseif (!empty($row->payment_status)):
                    $row->order_status = ($row->payment_status == 'due') ? 'Payment due' : @ucfirst($row->payment_status);
                else :
                    $row->order_status = 'Payment due';
                endif;

                $data[] = $row;
                $i++;
            }
            return $data;
        }
        return FALSE;
    }

    public function updateEshopPages($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('eshop_pages', $data)) {
            return true;
        }
        return false;
    }

    public function updateEshopSettings($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('eshop_settings', $data)) {
            return true;
        }
        return false;
    }

    public function getEshopPages($id) {
        $q = $this->db->get_where('eshop_pages', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getEshopSettings($id) {
        $q = $this->db->get_where('eshop_settings', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function GetAuthToken($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('eshop_authtoken', $arr, 1);
            if ($q->num_rows() > 0) {
                $res = $q->row();
                return $res->token;
            }
        endif;
        return FALSE;
    }

    public function validateAuthToken($token, $UserID) {
        if (empty($UserID) || empty($token)):
            return false;
        endif;
        $date_time = date("Y-m-d H:i:s");
        $this->db->select('token');
        $this->db->from('eshop_authtoken');
        $this->db->limit(1, 0);
        $this->db->where('user_id', $UserID);
        $this->db->where('token', $token);
        $this->db->where("start_date <= '$date_time' ");
        $this->db->where("end_date >= '$date_time' ");
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->token;
        }
        return false;
    }

    public function UpdateAuthToken($token, $UserID) {
        if (empty($UserID) || empty($token)):
            return false;
        endif;
        $res = $this->GetAuthToken(array('user_id' => $UserID));
        $data['token'] = $token;
        $data['user_id'] = $UserID;
        $data['start_date'] = date('Y-m-d H:i:s', strtotime('now'));
        $data['end_date'] = date('Y-m-d H:i:s', strtotime('now') + (3600 * 5));

        $act = 'update';
        if (!$res):
            $act = 'add';
        endif;
        switch ($act) {

            case 'add':
                if ($this->db->insert('eshop_authtoken', $data)) {
                    $cid = $this->db->insert_id();
                    return $token;
                }

                break;

            case 'update':
                $this->db->where('user_id', $UserID);
                if ($this->db->update('eshop_authtoken', $data)) {
                    return $token;
                }
                return false;

                break;
            default:
                break;
        }
    }

    public function validateRecieptSales($TransKey = NULL, $User_id = NULL) {
        $this->db->select('sales.id');
        $this->db->from('sales');

        if (!empty($TransKey)):
            $this->db->where(" MD5(CONCAT('Reciept',sma_sales.`reference_no`,sma_sales.`id`))", $TransKey);
        endif;

        $this->db->order_by("sales.id ", "desc");
        $q = $this->db->get();


        if ($q->num_rows() > 0) :
            return $q->result_array();
        endif;
        return FALSE;
    }

    /**
     * Eshop Order 
     * @param type $TransKey
     * @param type $User_id
     * @return boolean
     */
    public function validateRecieptOrder($TransKey = NULL, $User_id = NULL) {
        $this->db->select('orders.id');
        $this->db->from('orders');

        if (!empty($TransKey)):
            $this->db->where(" MD5(CONCAT('Reciept',sma_orders.`reference_no`,sma_orders.`id`))", $TransKey);
        endif;

        $this->db->order_by("orders.id ", "desc");
        $q = $this->db->get();


        if ($q->num_rows() > 0) :
            return $q->result_array();
        endif;
        return FALSE;
    }

    public function getStaticPages($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('eshop_pages', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function getSettings() {

        $this->db->select('site_name,logo2,pos_type,default_warehouse,default_tax_rate,offlinepos_warehouse,offlinepos_biller');
        $q = $this->db->get('settings');

        if ($q->num_rows() > 0) :
            return $q->row();
        endif;
        return FALSE;
    }

    public function getPosSettings() {

        $this->db->select('pos_settings.`eshop_order_tax`, pos_settings.`default_eshop_warehouse`, pos_settings.`default_category`, pos_settings.`default_customer`, pos_settings.`default_biller`, pos_settings.`default_eshop_biller`, pos_settings.eshop_overselling, pos_settings.`cf_title1`, pos_settings.`cf_title2`, pos_settings.`default_eshop_theame`, companies.name as biller_name,companies.address,companies.city,companies.state,companies.postal_code,companies.country,companies.phone,companies.email as default_email, companies.gstn_no, eshop_free_delivery_on_order');
        $this->db->from('pos_settings');
        $this->db->join('companies', 'default_biller = companies.id', 'left');
        $q = $this->db->get();

        if ($q->num_rows() > 0) :
            return $q->row();
        endif;
        return FALSE;
    }

    public function getDefaultCustomerInfo() {

        $this->db->select('pos_settings.`default_customer` id , companies.`name` , companies.`email`, companies.`phone`, companies.`address`, companies.`gstn_no`, companies.`company`');
        $this->db->from('pos_settings');
        $this->db->join('companies', 'default_customer = companies.id', 'left');
        $q = $this->db->get();

        if ($q->num_rows() > 0) :
            return $q->row();
        endif;
        return FALSE;
    }

    public function count_new_sales() {
        $data['num'] = 0;
        $data['notify'] = 0;
        $data['new_order'] = 0;

        $q = $this->db->select('id, sale_status, eshop_sale, eshop_order_alert_status')
                ->where('eshop_sale', '1')
                ->get('sma_sales');

        if ($q->num_rows()) {
            foreach ($q->result() as $sale) {
                if ($sale->sale_status == 'cancle' || $sale->eshop_order_alert_status > 1)
                    continue;
                $data['num'] ++;
                $data['notify'] += ($sale->eshop_order_alert_status == 1 ) ? 1 : 0;
                $data['new_order'] += ($sale->eshop_order_alert_status == 0 ) ? 1 : 0;
                //$data['sales'][] = $sale;
            }
            return $data;
        }
    }

    public function set_eshop_order_status($status = 1) {

        if ($status == 1) {
            return $this->db->where(['eshop_sale' => '1', 'eshop_order_alert_status' => '0'])
                            ->update('sma_sales', ['eshop_order_alert_status' => $status]);
        }
        if ($status == 2) {
            return $this->db->where('eshop_sale', '1')
                            ->update('sma_sales', ['eshop_order_alert_status' => $status]);
        }
    }

    public function getCustomerSales($param) {
        $User_id = isset($param['user_id']) && !empty($param['user_id']) ? $param['user_id'] : NULL;
        $limit = isset($param['limit']) && !empty($param['limit']) ? $param['limit'] : NULL;
        $offset = isset($param['offset']) && !empty($param['offset']) ? $param['offset'] : 0;
        $sale_status = isset($param['sale_status']) && !empty($param['sale_status']) ? $param['sale_status'] : NULL;
        $sale_type = isset($param['sale_type']) && !empty($param['sale_type']) ? $param['sale_type'] : NULL;
//        $sort_field     = isset($param['sort_field']) && !empty($param['sort_field'])?$param['sort_field']:'sales.id';
//        $sort_dir       = isset($param['sort_dir']) && !empty($param['sort_dir'])?$param['sort_dir']:'desc'; 
//        $search_by      = isset($param['search_by']) && !empty($param['search_by'])?$param['search_by']: NULL ;
//        $search_param   = isset($param['search_param']) && !empty($param['search_param'])?$param['search_param']:NULL ;
        /*  if(!empty($search_by) && is_array($search_param)):
          switch ($search_by) {
          case 'order_ref':
          if(empty($search_param['order_ref'])):
          return false;
          endif;
          $this->db->where('sales.reference_no', $search_param['order_ref']);
          break;

          case 'order_date':
          if(empty($search_param['order_date1']) || empty($search_param['order_date2'])):
          return false;
          endif;
          $this->db->where('date(sales.`date`) between  '." '".$search_param['order_date1']."'  and '".$search_param['order_date2']."' ");
          break;

          case 'pay_status':
          if(empty($search_param['pay_status'])):
          return false;
          endif;
          $this->db->where('sales.payment_status', $search_param['pay_status']);
          break;

          case 'pay_ref':
          if(empty($search_param['pay_ref'])):
          return false;
          endif;
          $this->db->where('payments.reference_no', $search_param['pay_ref']);
          break;

          case 'pay_trans':
          if(empty($search_param['pay_trans'])):
          return false;
          endif;
          $this->db->where('payments.transaction_id', $search_param['pay_trans']);
          break;

          default:
          break;
          }
          endif; */

        if (empty($User_id)):
            return false;
        endif;

        $this->db->select("sales.* "
                . ", payments.reference_no as payment_reference_no, payments.transaction_id as transaction_no"
                . ", deliveries.do_reference_no  as delivery_reference_no"
                . ", deliveries.status  as delivery_status, deliveries.delivery_type"
        );
        $this->db->from('sales');
        $this->db->join('payments', 'sales.id = payments.sale_id', 'left');
        $this->db->join('deliveries', 'sales.id = deliveries.sale_id', 'left');
        $this->db->where('sales.customer_id', $User_id);
        if ($sale_status !== NULL) {
            $this->db->where("sales.sale_status!='$sale_status'");
        }
        if ($sale_type !== NULL) {
            $this->db->where("sales.$sale_type='1'");
        }
        //--------------SORT ------------------------------
        /* if(!empty($sort_field) && !empty($sort_dir) ):
          $this->db->order_by($sort_field,$sort_dir);
          endif; */

        //--------------Limit ------------------------------
        if (!empty($limit) && !empty($offset)):
            $this->db->limit($limit, $offset);
        endif;

        $q = $this->db->get();

        if ($q->num_rows() > 0) {
            $i = 1;
            foreach (($q->result()) as $row) {
                if (!empty($row->delivery_status)):
                    $row->order_status = @ucfirst($row->delivery_status);
                elseif (!empty($row->payment_status)):
                    $row->order_status = ($row->payment_status == 'due') ? 'Payment due' : @ucfirst($row->payment_status);
                else :
                    $row->order_status = 'Payment due';
                endif;

                $data[] = $row;
                $i++;
            }
            return $data;
        }
        return FALSE;
    }

    public function validateRecieptEshopOrder($TransKey = NULL, $User_id = NULL) {
        $this->db->select('orders.id');
        $this->db->from('orders');

        if (!empty($TransKey)):
            $this->db->where(" MD5(CONCAT('Reciept',sma_orders.`reference_no`,sma_orders.`id`))", $TransKey);
        endif;

        $this->db->order_by("orders.id ", "desc");
        $q = $this->db->get();


        if ($q->num_rows() > 0) :
            return $q->result_array();
        endif;
        return FALSE;
    }

    public function count_new_eshop_order() {
        $data['num'] = 0;
        $data['notify'] = 0;
        $data['new_order'] = 0;

        $q = $this->db->select('id, sale_status, eshop_sale, eshop_order_alert_status')
                ->where('eshop_sale', '1')
                ->get('sma_orders');

        if ($q->num_rows()) {
            foreach ($q->result() as $sale) {
                if ($sale->sale_status == 'cancle' || $sale->eshop_order_alert_status > 1)
                    continue;
                $data['num'] ++;
                $data['notify'] += ($sale->eshop_order_alert_status == 1 ) ? 1 : 0;
                $data['new_order'] += ($sale->eshop_order_alert_status == 0 ) ? 1 : 0;
                //$data['sales'][] = $sale;
            }
            return $data;
        }
    }

    public function set_eshop_order_status_alert($status = 1) {
        if ($status == 1) {
            return $this->db->where(['eshop_sale' => '1', 'eshop_order_alert_status' => '0'])
                            ->update('sma_orders', ['eshop_order_alert_status' => $status]);
        }
        if ($status == 2) {
            return $this->db->where('eshop_sale', '1')
                            ->update('sma_orders', ['eshop_order_alert_status' => $status]);
        }
    }

    /**
     * Update GST on Order Table
     */
    public function updateorderGst($order_id, $data) {
        $this->db->where(['id' => $order_id])->update('sma_orders', $data);
    }

    /**
     * End Update GST on Order Table
     */

    /**
     * Pincode Action
     * @param type $key
     * @param type $pincode
     * @return string
     */
    public function pincodeaction($action, $data) {

        $pincode = isset($data['pincode']) ? $data['pincode'] : null;

        switch ($action) {
            case 'add':
                $this->db->where(['pincode' => $pincode])->get('pincode')->row();
                if ($this->db->affected_rows() > 0) {
                    $response = [
                        'status' => 'failed',
                        'message' => '<span class="text-danger">' . $pincode . ' This pincode allready available.</span>',
                    ];
                } else {
                    $warehouse_id = isset($data['warehouse']) ? $data['warehouse'] : null;
                     $charges = $data['charges'];
                    if ($data['delivery_time_from'] && $data['delivery_time_till']) {
                        $delivery_time_from = $data['delivery_time_from'];
                        $delivery_time_till = $data['delivery_time_till'];

                        $delivery_time = "$delivery_time_from To $delivery_time_till";
                    }

                    $addData = [
                        'pincode' => $pincode,
                        'warehouse_id' => $warehouse_id,
                        'delivery_time' => $delivery_time,
                        'charges' => $charges, 
                    ];


                    $this->db->insert('sma_pincode', $addData);
                    if ($this->db->affected_rows() > 0) {
                        $response = [
                            'status' => 'success',
                            'message' => '<span class="text-success">New pincode has been added successfully!</span>',
                        ];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => '<span class="text-danger">' . $pincode . ' This pincode not add, Please try again.</span>',
                        ];
                    }
                }

                break;

            case 'delete':
                $this->db->where(['pincode' => $pincode])->delete('sma_pincode');
                if ($this->db->affected_rows() > 0) {
                    $response = [
                        'status' => 'success',
                        'message' => '<span class="text-success">Pincode has been deleted successfully!</span>',
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => '<span class="text-danger">' . $pincode . ' This pincode not delete, Please try again.</span>',
                    ];
                }

                break;

            case 'list':
                $getpincode = $this->db->get('pincode')->result();
                if ($this->db->affected_rows() > 0) {
                    $response = $getpincode;
                } else {
                    $response = FALSE;
                }
                break;

            default :
                $response = [
                    'status' => 'error',
                    'message' => '<span class="text-danger">Request not found.</span>',
                ];
                break;
        }


        return $response;
    }

    /**
     * 
     * @param type $data
     * @return boolean
     */
    public function add_shippin_slotes($data) {
        $this->db->where($data)->get('sma_shipping_time')->row();
        if ($this->db->affected_rows() > 0) {
            return false;
        } else {
            $this->db->insert('sma_shipping_time', $data);
            if ($this->db->affected_rows() > 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 
     * @param type $id
     * @return boolean
     */
    public function getsloteTiming($id = null) {

        if ($id) {
            $timeslote = $this->db->where(['shipping_method_id' => $id])->order_by('start_time')->get('sma_shipping_time')->result();
        } else {
            $timeslote = $this->db->order_by('shipping_method_id , start_time')->get('sma_shipping_time')->result();
        }
        if ($this->db->affected_rows() > 0) {
            return $timeslote;
        } else {
            return false;
        }
    }

    public function getProducts($product_id, $variant_id = null) {

        $this->db->select("p.`id`, p.`code`, p.`name`, p.`unit`, p.`price`, p.`quantity`, p.`image`, p.`tax_rate` AS tax_rate_id, t.`rate` AS tax_rate, t.`name` AS tax_name, p.`tax_method`, p.category_id, p.subcategory_id, p.details, p.product_details, p.type AS product_type, p.brand, "
                . "p.`promotion`, p.`promo_price`, p.`start_date`, p.`end_date`, p.`sale_unit`, u.name AS unit_name, "
                . "pv.id as option_id, pv.name as option_name, pv.price as option_price , pv.quantity as option_quantity ");

        $this->db->from('products AS p');

        $this->db->join('product_variants AS pv', 'p.id =  pv.product_id', 'left');
        $this->db->join('tax_rates AS t', 'p.tax_rate =  t.id', 'left');
        $this->db->join('units AS u', 'p.`sale_unit` =  u.id', 'left');

        $this->db->where(['p.id' => $product_id]);
        if ($variant_id) {
            $this->db->where(['pv.id' => $variant_id]);
        }
        $q = $this->db->get();

        if ($q->num_rows() > 0) {

            foreach ($q->result() as $row) {
                $data[$row->id] = (array) $row;
            }

            return $data;
        }

        return false;
    }

    public function getVerientById($variant_id = null) {

        $q = $this->db->get_where('product_variants', ['id' => $variant_id]);

        if ($q->num_rows() > 0) {

            $data = $q->result();

            return (array) $data[0];
        }

        return false;
    }

    /*     * Delete ShippingTime* */

    public function deleteShippingTime($id) {
        if ($this->db->delete('sma_shipping_time', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getEshopOutlets() {

        $outlets = $this->db->select('id, code, name')->where(['in_eshop' => '1', 'is_active' => '1', 'is_disabled' => '0'])->get('warehouses')->result();
        if (count($outlets)) {
            foreach ($outlets as $outlet) {
                $data[$outlet->id] = $outlet->name;
            }
            return $data;
        }
        return false;
    }

    public function getWarehouses() {

        $outlets = $this->db->select('id, code, name')->where(['is_active' => '1', 'is_disabled' => '0'])->get('warehouses')->result();
        if (count($outlets)) {
            foreach ($outlets as $outlet) {
                $data[$outlet->id] = $outlet->name;
        }
            return $data;
        }
        return false;
    }

}
