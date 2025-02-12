<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Companies_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function getAllBillerCompanies() {
        $q = $this->db->get_where('companies', array('group_name' => 'biller'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllCustomerCompanies() {
        $q = $this->db->order_by('name', 'ASC')->get_where('companies', array('group_name' => 'customer'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getSMSCustomerList() {
        $q = $this->db->select('id,name,phone')->order_by('name', 'ASC')->get_where('companies', array('group_name' => 'customer'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllSupplierCompanies() {
        $q = $this->db->get_where('companies', array('group_name' => 'supplier'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllCustomerGroups() {
        $q = $this->db->get('customer_groups');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCompanyUsers($company_id) {
        $q = $this->db->get_where('users', array('company_id' => $company_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCompanyByID($id) {
        $q = $this->db->get_where('companies', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getCompanyByEmail($email) {
        $q = $this->db->get_where('companies', array('email' => $email), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addCompany($data = array(), $synch_customer_data = false) {
        if ($this->db->insert('companies', $data)) {
            $cid = $this->db->insert_id();
            if ($data['group_id'] == 3 && $synch_customer_data):
                $coustmer = $this->getCompanyByID($cid);
                $this->load->library('sma');
                $this->sma->SyncCustomerData($coustmer);
            endif;
            $_SESSION["quick_customerid"] = $cid;
            return $cid;
        }
        return false;
    }

    public function updateCompany($id, $data = array(), $synch_customer_data = false) {
        $this->db->where('id', $id);
        if (!isset($data['is_synced'])):
            $data['is_synced'] = 0;
        endif;
        if ($this->db->update('companies', $data)) {
            if ($data['group_id'] == 3 && $data['is_synced'] != 1 && $synch_customer_data):
                $coustmer = $this->getCompanyByID($id);
                $this->load->library('sma');
                $this->sma->SyncCustomerData($coustmer);
            endif;
            return true;
        }
        return false;
    }

    public function addCompanies($data = array()) {
        if (!empty($data)) {
            foreach ($data as $itesms) {
                $this->db->insert('companies', $itesms);
                $customerId = $this->db->insert_id();
                if ($itesms['deposit_amount']) {
                    $deposit = [
                        'date' => date('Y-m-d H:i:s'),
                        'company_id' => $customerId,
                        'amount' => $itesms['deposit_amount'],
                        'created_by' => $this->session->userdata('user_id'),
                    ];
                    $this->db->insert('deposits', $deposit);
                }
            }
            return true;
        }

        /* if ($this->db->insert_batch('companies', $data)) {
          return true;
          } */
        return false;
    }

    public function deleteCustomer($id) {
        if ($this->getCustomerSales($id)) {
            return false;
        }
        if ($this->db->delete('companies', array('id' => $id, 'group_name' => 'customer')) && $this->db->delete('users', array('company_id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function deleteSupplier($id) {
        if ($this->getSupplierPurchases($id)) {
            return false;
        }
        if ($this->db->delete('companies', array('id' => $id, 'group_name' => 'supplier')) && $this->db->delete('users', array('company_id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function deleteBiller($id) {
        if ($this->getBillerSales($id)) {
            return false;
        }
        if ($this->db->delete('companies', array('id' => $id, 'group_name' => 'biller'))) {
            return true;
        }
        return FALSE;
    }

    public function getBillerSuggestions($term, $limit = 10) {
        $this->db->select("id, company as text");
        $this->db->where(" (id LIKE '%" . $term . "%' OR name LIKE '%" . $term . "%' OR company LIKE '%" . $term . "%') ");
        $q = $this->db->get_where('companies', array('group_name' => 'biller'), $limit);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }

    public function getCustomerSuggestions($term, $limit = 10) {
        //$this->db->select("id, (CASE WHEN company = '-' THEN name ELSE CONCAT(company, ' (', name, ')') END) as text", FALSE);
        //$this->db->where(" (id LIKE '%" . $term . "%' OR name LIKE '%" . $term . "%' OR company LIKE '%" . $term . "%' OR email LIKE '%" . $term . "%' OR phone LIKE '%" . $term . "%') ");
        $this->db->select("id, (CASE WHEN company IS NULL THEN name ELSE CONCAT(company, ' (', name, ')') END) as text", FALSE);
        $this->db->where(" (id LIKE '%" . $term . "%' OR IF(name LIKE '%" . $term . "%',name LIKE '%" . $term . "%',Replace(coalesce(name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%'  ) OR company LIKE '%" . $term . "%' OR email LIKE '%" . $term . "%' OR phone LIKE '%" . $term . "%' OR cf1 LIKE '%" . $term . "%'  OR cf2 LIKE '%" . $term . "%') ");


        $q = $this->db->get_where('companies', array('group_name' => 'customer'), $limit);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }

    public function getSupplierSuggestions($term, $limit = 10) {
        //$this->db->select("id, (CASE WHEN company = '-' THEN name ELSE CONCAT(company, ' (', name, ')') END) as text", FALSE);
        //$this->db->where(" (id LIKE '%" . $term . "%' OR name LIKE '%" . $term . "%' OR company LIKE '%" . $term . "%' OR email LIKE '%" . $term . "%' OR phone LIKE '%" . $term . "%') ");
        $this->db->select("id, (CASE WHEN company IS NULL THEN name ELSE CONCAT(company, ' (', name, ')') END) as text", FALSE);
        $this->db->where(" (id LIKE '%" . $term . "%' OR IF(name LIKE '%" . $term . "%',name LIKE '%" . $term . "%',Replace(coalesce(name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%'  ) OR company LIKE '%" . $term . "%' OR email LIKE '%" . $term . "%' OR phone LIKE '%" . $term . "%') ");

        $q = $this->db->get_where('companies', array('group_name' => 'supplier'), $limit);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }

    public function getCustomerSales($id) {
        $this->db->where('customer_id', $id)->from('sales');
        return $this->db->count_all_results();
    }

    public function getBillerSales($id) {
        $this->db->where('biller_id', $id)->from('sales');
        return $this->db->count_all_results();
    }

    public function getSupplierPurchases($id) {
        $this->db->where('supplier_id', $id)->from('purchases');
        return $this->db->count_all_results();
    }

    public function addDeposit($data, $cdata) {
        if ($this->db->insert('deposits', $data)) {
            if ($deposit_id = $this->db->insert_id()) {
                $this->db->update('companies', $cdata, array('id' => $data['company_id']));
                if ($this->db->affected_rows()) {
                    return $deposit_id;
                }
            }
        }
        return false;
    }

    public function updateDeposit($id, $data, $cdata) {
        if ($this->db->update('deposits', $data, array('id' => $id)) &&
                $this->db->update('companies', $cdata, array('id' => $data['company_id']))) {
            return true;
        }
        return false;
    }

    public function getDepositByID($id) {
        $q = $this->db->get_where('deposits', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function deleteDeposit($id) {
        $deposit = $this->getDepositByID($id);
        $company = $this->getCompanyByID($deposit->company_id);
        $cdata = array(
            'deposit_amount' => ($company->deposit_amount - $deposit->amount)
        );
        if ($this->db->update('companies', $cdata, array('id' => $deposit->company_id)) &&
                $this->db->delete('deposits', array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function getAllPriceGroups() {
        $q = $this->db->get('price_groups');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCompanyAddresses($company_id) {
        $q = $this->db->get_where('addresses', array('company_id' => $company_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function addAddress($data) {
        if ($this->db->insert('addresses', $data)) {
            return true;
        }
        return false;
    }

    public function updateAddress($id, $data) {
        if ($this->db->update('addresses', $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function deleteAddress($id) {
        if ($this->db->delete('addresses', array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function getAddressByID($id) {
        $q = $this->db->get_where('addresses', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addApiNotify($data) {
        if ($this->db->insert('apinotify', $data)) {
            $aid = $this->db->insert_id();
            return $aid;
        }
        return false;
    }

    public function getCompanyCustomer($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('companies', $arr, 1);
            //    echo $this->db->last_query(); 
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;

        return FALSE;
    }

    public function getAuthCustomer($param = NULL) {
        $this->db->select('id,name,phone,email');
        $loginid = isset($param['loginid']) && !empty($param['loginid']) ? $param['loginid'] : NULL;
        $pass = isset($param['pass']) && !empty($param['pass']) ? $param['pass'] : NULL;
        $pass_type = isset($param['pass_type']) && !empty($param['pass_type']) ? $param['pass_type'] : 'password';

        switch ($pass_type) {
            case 'pass_key':
                $where = "pass_key='$pass'";
                break;

            default:
                $where = "password='$pass' AND (email='$loginid' OR phone='$loginid' )";
                break;
        }
        $this->db->where($where);
        $this->db->limit(1, 0);
        $q = $this->db->get('companies');
        // $this->db->last_query(); 
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function addEshopPasswordToken($data = array()) {
        $this->db->delete('eshop_password_token', array('user_id' => $data['user_id']));
        if ($this->db->insert('eshop_password_token', $data)) {
            $cid = $this->db->insert_id();
            return $cid;
        }
        return false;
    }

    public function validateEshopPasswordToken($param = array()) {

        $user_id = isset($param['user_id']) && !empty($param['user_id']) ? $param['user_id'] : NULL;
        $token = isset($param['token']) && !empty($param['token']) ? $param['token'] : NULL;
        $dt = date("Y-m-d H:i:s");
        if (empty($user_id) || empty($token)) :
            return false;
        endif;
        $this->db->where('user_id  =', $user_id);
        $this->db->where('token  =', $token);
        $this->db->where('status  =', 1);
        $this->db->where('token_end >=', $dt);
        $this->db->where('token_start<=', $dt);
        $q = $this->db->get('eshop_password_token');
        // echo $this->db->last_query(); 
        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res;
        }
        return FALSE;
        return FALSE;
    }

    public function get_eshop_user($id = null, $fields = null) {
        $user_id = isset($id) && !empty((int) $id) ? $id : NULL;
        $fields = isset($fields) && !empty($fields) ? $fields : '*';
        if (empty((int) $user_id)):
            return false;
        endif;
        $this->db->select('*');
        $where = "user_id='$user_id'  ";
        $this->db->where($where);
        $this->db->limit(1, 0);
        $q = $this->db->get('eshop_user_details');
        $this->db->last_query();
        if ($q->num_rows() > 0) {
            $res = $q->result_array();
            $comp = $this->getCompanyByID($user_id);

            if (is_object($comp) && $fields == '*'):
                $res[0]['email'] = $comp->email;
                $res[0]['phone'] = $comp->phone;
                $res[0]['name'] = $comp->name;
            endif;
            return $res;
        }
        return FALSE;
    }

    public function set_billing_shiiping_info($id = null, $param = null) {
        $user_id = isset($id) && !empty((int) $id) ? $id : NULL;
        $res = $this->get_eshop_user($user_id);
        $act = isset($res[0]['id']) && !empty((int) $res[0]['id']) ? 'edit' : 'add';
        $param = isset($param) && is_array($param) ? $param : NULL;

        if (empty($param) || empty($user_id)):
            return false;
        endif;
        switch ($act) {
            case 'edit':
                $this->db->where('user_id', $user_id);
                if ($this->db->update('eshop_user_details', $param)) {
                    return true;
                }
                return false;
                break;

            case 'add':

                $param['user_id'] = $user_id;
                if ($this->db->insert('eshop_user_details', $param)) {
                    $cid = $this->db->insert_id();
                    return $cid;
                }
                return false;
                break;
        }
        return false;
    }

    //array('$user_photo'=>,'user_photo_path'=>$user_photo_path);

    public function set_photo($id, $param) {
        $user_id = isset($id) && !empty((int) $id) ? $id : NULL;
        if (empty($user_id)):
            return false;
        endif;
        $this->db->where('user_id', $user_id);
        if ($this->db->update('eshop_user_details', $param)) {
            return $param['user_photo_path'] . $param['user_photo'];
        }
        return false;
    }

    public function duplicateUser($fieldVal, $field, $userID = null) {
        if (!empty($fieldVal) && !empty($field)):
            $this->db->where($field, $fieldVal);
        endif;
        if (!empty($userID)):
            $this->db->where(" id != '" . $userID . "' ");
        endif;

        $this->db->limit(1, 0);
        $q = $this->db->get('companies');
        return $q->num_rows();
    }

    public function nonSyncCustmerCount() {
        $this->db->select("id");
        $q = $this->db->get_where('view_non_sync_custmer', array());

        if ($q->num_rows() > 0) {
            return $q->num_rows();
        }
        return false;
    }

    public function nonSyncCustmer($limit = 10) {
        $this->db->select("id");
        if (!empty($limit)):
            $this->db->limit($limit, 0);
        endif;
        $q = $this->db->get_where('view_non_sync_custmer', array());
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function checkApiNotify($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('apinotify', $arr, 1);
            if ($q->num_rows() > 0) {
                return true;
            }
        endif;

        return FALSE;
    }

    public function getBillerByID($id) {
        $q = $this->db->select('id,name,company,vat_no,address,city,state,state_code,postal_code,country,phone,email,invoice_footer,gstn_no')->get_where('companies', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getOfflineDefaultBiller() {
        $q = $this->db->query('SELECT c.* FROM `sma_companies` c INNER JOIN `sma_settings` s ON s.offlinepos_biller = c.id');

        if ($q->num_rows() > 0) {

            return $q->row();
        }
        return FALSE;
    }

    /**
     * This method using get gift Card column to Customer list
     * @return type
     */
    public function getGiftCard($cust_id = null) {
        $array = array('customer_id' => $cust_id, 'balance >' => '0', 'expiry >=' => date('Y-m-d'));
        $get = $this->db->select('balance as giftbalance')
                        ->where($array)
                        ->order_by('balance', DESC)
                        ->limit(1)
                        ->get('sma_gift_cards')->row();

        return $get->giftbalance;
    }

    /** End get payment option * */

    /**
     * 
     * @param type $customerId
     * @return type
     */
    public function getDepositandGift($customerId) {
        $deposit = $this->db->select('deposit_amount')->where(['id' => $customerId])->get('companies')->row();


        $giftcard = $this->getGiftCardAmt($customerId);

        $reponse = [
            'deposit' => ($deposit->deposit_amount) ? round($deposit->deposit_amount, 2) : '',
            'giftcardqty' => $giftcard->giftqty,
            'giftcardAmt' => ($giftcard->giftbalance) ? $this->sma->formatMoney($giftcard->giftbalance) : '',
        ];
        return $reponse;
    }

    /**
     * 
     * @param type $cust_id
     * @return type
     */
    public function getGiftCardAmt($cust_id = null) {
        $array = array('customer_id' => $cust_id, 'balance >' => '0', 'expiry >=' => date('Y-m-d'));
        $get = $this->db->select('sum(balance)as giftbalance ,count(id) as giftqty')
                        ->where($array)
                        ->order_by('balance', DESC)
                        ->get('sma_gift_cards')->row();

        return $get;
    }

    /**
     * Get Employee Type List
     * @return type
     */
    public function getEmployeeTypes() {
        return $this->db->where('is_employee', 1)->get('groups')->result();
    }

    /**
     * Suplier Privatekey Notification
     */

    /**
     * Count New Notification
     * @return type
     */
    public function count_new_purchase() {
        $q = $this->db->select('id')->where(['notification_supplier !=' => NULL])->get('companies')->result();

        $data['num'] = count($q);

        foreach ($q as $items) {
            $data['suplier_id'][] = $items->id;
        }
        return $data;
    }

    /**
     * Removed Notification alert
     * @param type $status
     * @return type
     */
    public function set_notification_order_status($ids) {

        $getData = $this->db->select('id,notification_supplier')
                        ->where(['notification_supplier !=' => NULL])->where_in('id', $ids)
                        ->get('companies')->result();


        foreach ($getData as $items) {
            $getnItemsData = unserialize($items->notification_supplier);

            $data = [
                'privatekey' => $getnItemsData['privatekey'],
                'customer_url' => $getnItemsData['customer_url'],
                'notification_supplier' => NULL,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $this->db->where(['id' => $items->id])->update('companies', $data);
        }
    }

    /**
     * End Suplier Privatekey Notification
     */

    /**
     * 
     * @param type $customerID
     * @return type
     */
    public function getOPCLDeposit($customerID, $date = NULL) {
        $date = ($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        $getData = $this->db->where(['customer_id' => $customerID, 'DATE(date)' => $date])
                        ->get('sma_customer_wallet_transactions')->row();

        return $getData;
    }

    /**
     * Get Total Reacharge Amount
     */
    public function getTotalReacharge($date, $customer_id) {
        $result = $this->db->select('COALESCE(sum(amount), 0) as totalAmt')->where(['Date(date)' => $date, 'company_id' => $customer_id])->get('sma_deposits')->row();


        return ($this->db->affected_rows() ? $result->totalAmt : '0');
    }

    /**
     * Get used Deposit
     */
    public function getUseddeposit($date, $customer_id) {

        $result = $this->db->select('COALESCE(sum(sma_payments.amount), 0) as totalAmt')
                        ->join('sma_sales', 'sma_sales.id = sma_payments.sale_id', 'inner')
                        ->where(['Date(sma_payments.date)' => $date, 'sma_sales.customer_id' => $customer_id, 'sma_payments.paid_by' => 'deposit'])->get('sma_payments')->row();
        return ($this->db->affected_rows() ? $result->totalAmt : '0');
    }

    public function set_customer_wallet_log(array $logData) {

        if (is_array($logData)) {

            $this->db->insert("customer_wallet_transactions", $logData);

            return $this->db->affected_rows();
        }

        return false;
    }
  
    public function customerDeposit($id) {
        $q = $this->db->get_where('companies', ['id' => $id], 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }
    
	public function importBulkDeposit($data = array(),$user)
	 {

        if (!empty($data)) {
            $i=0;
            foreach ($data as $itesms) {
                               
                $quary = "select sma_companies.id,deposit_amount from sma_companies where phone=".$itesms['phone'];
                $res= $this->db->query($quary);
                $id = $res->result()[0]->id;
                $deposit = $res->result()[0]->deposit_amount;
                if(!$deposit)
                {
                    $deposit = 0;
                }
                $su_cash = $data[$i]['super_cash'];
                $de_type = $data[$i]['paid_by'];
                $new_dep = $deposit + $data[$i]['deposit_amount'] + $su_cash;
                $amount = $data[$i]['deposit_amount'] + $su_cash;
                if( $su_cash==null){
                    $su_cash=0;
                }

                if($this->db->update('companies', array('deposit_amount' => $new_dep), array('id' => $id)))
                {
                  
                    $deposit_arr = [
                       // "date" => "CURRENT_TIMESTAMP",
                        "company_id" => $id,
                        "amount"=>$amount,
                        "paid_by"=>$de_type,
                        "note" => "services",
                        "created_by" => $user,
                        "updated_by" => 0,
                        "updated_at" =>NULL,
                        "super_cash" => $su_cash,
                        "services" => "0",
                        
                    ];
                    $this->db->insert('deposits', $deposit_arr);
                    $newId = $this->db->insert_id();


                    if($res)
                    {
                    $str = "'".'{"table_name":"sma_deposits","where":{"id": '.$newId.'}}'."'";
                     $sqlnew = "INSERT INTO `sma_customer_wallet_transactions`(`id`, `customer_id`, `date`, `descriptions`, `amount`, `cr_dr`, `transaction_details`, `opening_balance`, `closing_balance`, `updated_at`, `created_by`) VALUES (NULL,$id,CURRENT_TIMESTAMP,'Add Amount',$amount,'CR',$str,$deposit,$new_dep,CURRENT_TIMESTAMP,$user)";
                     $this->db->query($sqlnew);
                    }
                }
                else
                {
                    return false;
                }

                $i++;
            }
            // exit;
            return true;

        }
	
    }

}


