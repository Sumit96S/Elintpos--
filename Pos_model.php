<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function getSetting($field_name = null) {
        
        if($field_name){
            $q = $this->db->select($field_name)->get('pos_settings');
        } else {
        $q = $this->db->get('pos_settings');
        }
        
        if ($q->num_rows() > 0) {
            $data = $q->row();
            unset($data->pos_id, $data->purchase_code, $data->envato_username, $data->version); 
            if($field_name){
                $arrFields = explode(',',$field_name);
                if(count($arrFields) == 1){
                    return $data->$field_name;
                }
            }
            return $data;
        }
        return FALSE;
    }

    public function updateSetting($data) {
        $this->db->where('pos_id', '1');
        if ($this->db->update('pos_settings', $data)) {
            return true;
        }
        return false;
    }

    public function products_count($category_id, $subcategory_id = NULL, $brand_id = NULL, $warehouse_id = NULL) {
        if ($category_id) {
            $this->db->where('category_id', $category_id);
        }
        if ($subcategory_id) {
            $this->db->where('subcategory_id', $subcategory_id);
        }
        if ($brand_id) {
            $this->db->where('brand', $brand_id);
        }
        if ($warehouse_id):
            $this->db->join('warehouses_products wp', 'products.id=wp.product_id', 'left')
                    ->where('wp.warehouse_id', $warehouse_id)
                    ->where('wp.quantity !=', 0);
        endif;
        $this->db->from('products');
        return $this->db->count_all_results();
    }

    // public function fetch_products($category_id, $limit, $start, $subcategory_id = NULL, $brand_id = NULL, $warehouse_id = NULL) {
    //     $this->db->order_by('id', 'desc');
    //     $this->db->limit($limit, $start);
    //     if ($brand_id) {
    //         $this->db->where('brand', $brand_id);
    //     } elseif ($category_id) {
    //         $this->db->where('category_id', $category_id);
    //     }
    //     if ($subcategory_id) {
    //         $this->db->where('subcategory_id', $subcategory_id);
    //     }
    //     $this->db->order_by("name", "asc");
    //     if ($warehouse_id):
    //         $this->db->join('warehouses_products wp', 'products.id=wp.product_id', 'left')
    //                 ->where('wp.warehouse_id', $warehouse_id)
    //                 ->where('wp.quantity !=', 0);
    //     endif;

    //     $query = $this->db->get("products");

    //     if ($query->num_rows() > 0) {
    //         foreach ($query->result() as $row) {
    //             $data[] = $row;
    //         }
    //         return $data;
    //     }
    //     return false;
    // }
    public function fetch_products($category_id, $limit, $start, $subcategory_id = NULL, $brand_id = NULL, $warehouse_id = NULL, $seasons_id = NULL, $pos_screen_products = Null) {
       
        $today_date = date('Y-m-d'); 
        $this->db->limit($limit, $start);
        if ($brand_id) {
            $this->db->where('brand', $brand_id);
        } elseif ($category_id) {
            $this->db->where('category_id', $category_id);
        }
        if ($subcategory_id) {
            $this->db->where('subcategory_id', $subcategory_id);
        }
        if ($seasons_id) {
            $this->db->where('season_id', $seasons_id);
        }
        $this->db->where('products.flag_visible', 1); // products show or not on pos screen flag
        $this->db->join('seasons s', 'products.season_id = s.id', 'left');

        if($pos_screen_products == 2){ //rank wise products
            $this->db->order_by("CASE WHEN rank IS NULL OR rank = '' THEN 2 ELSE 1 END", "ASC");
            $this->db->order_by('rank', 'asc');
        }
        if($pos_screen_products == 3){ // seasons wise products
            $this->db->order_by('CASE WHEN s.FromDate <= CURDATE() AND s.ToDate >= CURDATE() THEN 1 ELSE 2 END', 'asc');
        }
        
        $this->db->order_by('CASE WHEN s.FromDate <= CURDATE() AND s.ToDate >= CURDATE() THEN 1 ELSE 2 END', 'asc');

        $this->db->order_by("name", "asc");
        if ($warehouse_id):
            $this->db->join('warehouses_products wp', 'products.id=wp.product_id', 'left')
                    ->where('wp.warehouse_id', $warehouse_id)
                    ->where('wp.quantity !=', 0);
        endif;

        $query = $this->db->get("products");

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function registerData($user_id) {
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $q = $this->db->get_where('pos_register', array('user_id' => $user_id, 'status' => 'open'), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function openRegister($data) {
        if ($this->db->insert('pos_register', $data)) {
            return true;
        }
        return FALSE;
    }

    public function getOpenRegisters() {
        $this->db->select("date, user_id, cash_in_hand, CONCAT(" . $this->db->dbprefix('users') . ".first_name, ' ', " . $this->db->dbprefix('users') . ".last_name, ' - ', " . $this->db->dbprefix('users') . ".email) as user", FALSE)
                ->join('users', 'users.id=pos_register.user_id', 'left')
                ->order_by('users.id', 'desc');
        $q = $this->db->get_where('pos_register', array('status' => 'open'));
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function closeRegister($rid, $user_id, $data) {
        if (!$rid) {
            $rid = $this->session->userdata('register_id');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        if ($data['transfer_opened_bills'] == -1) {
            $this->db->delete('suspended_bills', array('created_by' => $user_id));
        } elseif ($data['transfer_opened_bills'] != 0) {
            $this->db->update('suspended_bills', array('created_by' => $data['transfer_opened_bills']), array('created_by' => $user_id));
        }
        if ($this->db->update('pos_register', $data, array('id' => $rid, 'user_id' => $user_id))) {
            return true;
        }
        return FALSE;
    }

    public function getUsers() {
        $q = $this->db->get_where('users', array('company_id' => NULL));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductsByCode($code) {
        $this->db->like('code', $code, 'both')->order_by("code");
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getWHProduct($code, $warehouse_id) {
        $this->db->select('products.*, warehouses_products.quantity, categories.id as category_id, categories.name as category_name')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->join('categories', 'categories.id=products.category_id', 'left')
                ->group_by('products.id');
        $q = $this->db->get_where("products", array('products.code' => $code));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getWHProductById($id) {
        $this->db->select('products.*, warehouses_products.quantity, categories.id as category_id, categories.name as category_name')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->join('categories', 'categories.id=products.category_id', 'left')
                ->group_by('products.id');
        $q = $this->db->get_where("products", array('products.id' => $id));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductOptions($product_id, $warehouse_id, $all = NULL) {
        $wpv = "( SELECT option_id, warehouse_id, quantity from {$this->db->dbprefix('warehouses_products_variants')} WHERE product_id = {$product_id}) FWPV";
        $this->db->select('product_variants.id as id, product_variants.name as name, product_variants.price as price, product_variants.quantity as total_quantity, FWPV.quantity as quantity', FALSE)
                ->join($wpv, 'FWPV.option_id=product_variants.id', 'left')
                //->join('warehouses', 'warehouses.id=product_variants.warehouse_id', 'left')
                ->where('product_variants.product_id', $product_id)
                ->group_by('product_variants.id');

        if (!$this->Settings->overselling && !$all) {
            $this->db->where('FWPV.warehouse_id', $warehouse_id);
            $this->db->where('FWPV.quantity >', 0);
        }
        $q = $this->db->get('product_variants');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductComboItems($pid, $warehouse_id) {
        $this->db->select('products.id as id, combo_items.item_code as code, combo_items.quantity as qty, products.name as name, products.type as type, warehouses_products.quantity as quantity, ' . (int)$pid . ' as product_id')
                ->join('products', 'products.code=combo_items.item_code', 'left')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->where('warehouses_products.warehouse_id', $warehouse_id)
                ->group_by('combo_items.id');
        $q = $this->db->get_where('combo_items', array('combo_items.product_id' => $pid));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
        return FALSE;
    }

    public function updateOptionQuantity($option_id, $quantity) {
        if ($option = $this->getProductOptionByID($option_id)) {
            $nq = $option->quantity - $quantity;
            if ($this->db->update('product_variants', array('quantity' => $nq), array('id' => $option_id))) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function addOptionQuantity($option_id, $quantity) {
        if ($option = $this->getProductOptionByID($option_id)) {
            $nq = $option->quantity + $quantity;
            if ($this->db->update('product_variants', array('quantity' => $nq), array('id' => $option_id))) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function getProductOptionByID($id) {
        $q = $this->db->get_where('product_variants', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductWarehouseOptionQty($option_id, $warehouse_id) {
        $q = $this->db->get_where('warehouses_products_variants', array('option_id' => $option_id, 'warehouse_id' => $warehouse_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateProductOptionQuantity($option_id, $warehouse_id, $quantity, $product_id) {
        if ($option = $this->getProductWarehouseOptionQty($option_id, $warehouse_id)) {
            $nq = $option->quantity - $quantity;
            if ($this->db->update('warehouses_products_variants', array('quantity' => $nq), array('option_id' => $option_id, 'warehouse_id' => $warehouse_id))) {
                $this->site->syncVariantQty($option_id, $warehouse_id);
                return TRUE;
            }
        } else {
            $nq = 0 - $quantity;
            if ($this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $nq))) {
                $this->site->syncVariantQty($option_id, $warehouse_id);
                return TRUE;
            }
        }
        return FALSE;
    }

    public function addSale($data = array(), $items = array(), $payments = array(), $sid = NULL) {

         $billers_id = $data['biller_id'];
        $billers_state_code = $this->sma->getstatecode($billers_id);

        $customer_id = $data['customer_id'];
        $customer_state_code = ($this->sma->getstatecode($customer_id))?$this->sma->getstatecode($customer_id) : $billers_state_code;       
        $GSTType = ($customer_state_code == $billers_state_code) ? 'GST' : 'IGST';

        $cost = $this->site->costing($items);

        if ($this->db->insert('sales', $data)) {
            $sale_id = $this->db->insert_id();

            // Invoice No
            $invoice_no_array = ['invoice_no' => $this->sma->invoice_format($sale_id, date('Y-m-d')),];

            $this->db->where(['id' => $sale_id])->update('sales', $invoice_no_array);
            // End Invoice No

            $this->site->updateReference('pos');
            $Setting = $this->Settings;

            foreach ($items as $item) {
                //------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//

                $_prd = $Setting->pos_type == 'pharma' ? $this->site->getProductByID($item['product_id']) : NULL;
                $item['cf1'] = ($Setting->pos_type == 'pharma' && isset($_prd->cf1)) ? $_prd->cf1 : '';
                $item['cf2'] = ($Setting->pos_type == 'pharma' && isset($_prd->cf2)) ? $_prd->cf2 : '';
                //------------------ End ----------------//
                //$this->sma->print_arrays($item);		
                $item['sale_id'] = $sale_id;
                $this->db->insert('sale_items', $item);
                //$sale_item_id = $this->db->insert_id();
                $sale_item_id = $this->db->insert_id();
                $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['quantity'], $sale_item_id, $sale_id);
                /* Add New field to Sale */
                $tax_ItemAtrr = $this->sma->taxArr_rate_gst($item['tax_rate_id'], $item['net_unit_price'], $item['quantity'], $sale_item_id, $sale_id, $GSTType);

                if ($data['sale_status'] == 'completed' && $this->site->getProductByID($item['product_id'])) {

                    $item_costs_data = $this->site->item_costing($item);
                   
                    if($item['product_type'] == "standard"){ 
                        $item_costs = $item_costs_data;
                    } else {
                        if(is_array($item_costs_data))
                        foreach ($item_costs_data as $coitm_cost) {
                            if(is_array($coitm_cost))
                            foreach ($coitm_cost as $key => $item_cost) {
                                $item_costs[] = $item_cost;
                            }                             
                        }
                    } 
//                    echo '<pre>product_type: '.$item['product_type'];
//                    print_r($item_costs);
//                    echo '<pre>';
                    
                    foreach ($item_costs as $key => $item_cost) {

                        if (is_array($item_cost[$key])) {

                            foreach ($item_cost as $subitemcost) {
                               
                                $subitemcost['sale_item_id'] = $sale_item_id;
                                $subitemcost['sale_id'] = $sale_id;

                                if (!isset($subitemcost['pi_overselling'])) {
                   
                                    $this->db->insert('costing', $subitemcost);
                                }
                            }
                        } else {

                            $item_cost['sale_item_id'] = $sale_item_id;
                            $item_cost['sale_id'] = $sale_id;
                            if (!isset($item_cost['pi_overselling'])) {
                                $this->db->insert('costing', $item_cost);
                            }
                        }
                    }
                }                
            }

            if ($data['sale_status'] == 'completed') {
                $this->site->syncPurchaseItems($cost);
            }

            $msg = array();
            if (!empty($payments)) {
                $paid = 0;
                foreach ($payments as $payment) {
                    if (!empty($payment) && isset($payment['amount']) && $payment['amount'] != 0) {
                        $payment['sale_id'] = $sale_id;
                        $payment['reference_no'] = $this->site->getReference('pay');
                        if ($payment['paid_by'] == 'ppp') {
                            $card_info = array("number" => $payment['cc_no'], "exp_month" => $payment['cc_month'], "exp_year" => $payment['cc_year'], "cvc" => $payment['cc_cvv2'], 'type' => $payment['cc_type']);
                            $result = $this->paypal($payment['amount'], $card_info);
                            if (!isset($result['error'])) {
                                $payment['transaction_id'] = $result['transaction_id'];
                                $payment['date'] = $this->sma->fld($result['created_at']);
                                $payment['amount'] = $result['amount'];
                                $payment['currency'] = $result['currency'];
                                unset($payment['cc_cvv2']);
                                $this->db->insert('payments', $payment);
                                $this->site->updateReference('pay');
                                $paid += $payment['amount'];
                            } else {
                                $msg[] = lang('payment_failed');
                                if (!empty($result['message'])) {
                                    foreach ($result['message'] as $m) {
                                        $msg[] = '<p class="text-danger">' . $m['L_ERRORCODE'] . ': ' . $m['L_LONGMESSAGE'] . '</p>';
                                    }
                                } else {
                                    $msg[] = lang('paypal_empty_error');
                                }
                            }
                        } elseif ($payment['paid_by'] == 'stripe') {
                            $card_info = array("number" => $payment['cc_no'], "exp_month" => $payment['cc_month'], "exp_year" => $payment['cc_year'], "cvc" => $payment['cc_cvv2'], 'type' => $payment['cc_type']);
                            $result = $this->stripe($payment['amount'], $card_info);
                            if (!isset($result['error'])) {
                                $payment['transaction_id'] = $result['transaction_id'];
                                $payment['date'] = $this->sma->fld($result['created_at']);
                                $payment['amount'] = $result['amount'];
                                $payment['currency'] = $result['currency'];
                                unset($payment['cc_cvv2']);
                                $this->db->insert('payments', $payment);
                                $this->site->updateReference('pay');
                                $paid += $payment['amount'];
                            } else {
                                $msg[] = lang('payment_failed');
                                $msg[] = '<p class="text-danger">' . $result['code'] . ': ' . $result['message'] . '</p>';
                            }
                        } elseif ($payment['paid_by'] == 'authorize') {
                            $authorize_arr = array("x_card_num" => $payment['cc_no'], "x_exp_date" => ($payment['cc_month'] . '/' . $payment['cc_year']), "x_card_code" => $payment['cc_cvv2'], 'x_amount' => $payment['amount'], 'x_invoice_num' => $sale_id, 'x_description' => 'Sale Ref ' . $data['reference_no'] . ' and Payment Ref ' . $payment['reference_no']);
                            list($first_name, $last_name) = explode(' ', $payment['cc_holder'], 2);
                            $authorize_arr['x_first_name'] = $first_name;
                            $authorize_arr['x_last_name'] = $last_name;
                            $result = $this->authorize($authorize_arr);
                            if (!isset($result['error'])) {
                                $payment['transaction_id'] = $result['transaction_id'];
                                $payment['approval_code'] = $result['approval_code'];
                                $payment['date'] = $this->sma->fld($result['created_at']);
                                unset($payment['cc_cvv2']);
                                $this->db->insert('payments', $payment);
                                $this->site->updateReference('pay');
                                $paid += $payment['amount'];
                            } else {
                                $msg[] = lang('payment_failed');
                                $msg[] = '<p class="text-danger">' . $result['msg'] . '</p>';
                            }
                        } elseif ($payment['paid_by'] == 'instamojo') {
                            $_arr = array('x_amount' => $payment['amount'], 'x_invoice_num' => $sale_id, 'x_description' => 'Sale Ref ' . $data['reference_no']);
                            $customer = $this->site->getCompanyByID($data['customer_id']);
                            $_arr['name'] = $customer->name;
                            $_arr['email'] = $customer->email;
                            $_arr['mobile'] = $customer->phone;
                            $_arr['notify_url'] = base_url('pos/instamojo_notify');
                            $result = $this->instamojo($_arr);
                            if (isset($result['longurl']) && !empty($result['longurl'])) {
                                $data['redirect_pay_url'] = $result['longurl'];
                            } else {
                                $msg[] = lang('payment_failed');
                                $msg[] = '<p class="text-danger">' . $result['error'] . '</p>';
                            }
                        } elseif ($payment['paid_by'] == 'ccavenue') {
                            $_url = base_url('pos/ccavenue_init') . '?sid=' . $sale_id;
                            $data['redirect_pay_url'] = $_url;
                        } elseif ($payment['paid_by'] == 'razorpay') {
                            $_url = base_url('pos/razorpay_init') . '?sid=' . $sale_id;
                            $data['redirect_pay_url'] = $_url;
                        } elseif ($payment['paid_by'] == 'paytm') {
                            $_url = base_url('pos/paytm_init') . '?sid=' . $sale_id;
                            $data['redirect_pay_url'] = $_url;
                        } elseif ($payment['paid_by'] == 'payswiff') {
                            $_url = base_url('pos/payswiff_init') . '?sid=' . $sale_id;
                            $data['redirect_pay_url'] = $_url;
                        } elseif ($payment['paid_by'] == 'paynear') {
                            $_url = base_url('pos/paynear_init') . '?sid=' . $sale_id;
                            if (!empty($_POST['paynear_mobile_app']) && $_POST['paynear_mobile_app'] == 1) {
                                $_url = $_url . '&mobile_app=' . md5('MPA' . $sale_id) . '&paynear_type=' . $_POST['paynear_mobile_app_type'];
                            }
                            $data['redirect_pay_url'] = $_url;
                        } elseif ($payment['paid_by'] == 'payumoney') {
                            $_url = base_url('pos/payumoney_init') . '?sid=' . $sale_id;
                            $data['redirect_pay_url'] = $_url;
                        } else {
                            if ($payment['paid_by'] == 'award_point') {
                                $customer = $this->site->getCompanyByID($data['customer_id']);
                                $DataAwardPoint = array(
                                    'sale_id' => $sale_id,
                                    'award_point' => $payment['ap'],
                                    'customer_id' => $data['customer_id'],
                                );
                                $this->db->insert('award_point_log', $DataAwardPoint);
                                $this->db->update('companies', array('award_points' => ($customer->award_points - $payment['ap'])), array('id' => $data['customer_id']));
                                unset($payment['ap']);
                            } elseif ($payment['paid_by'] == 'gift_card') {
                                $this->db->update('gift_cards', array('balance' => $payment['gc_balance']), array('card_no' => $payment['cc_no']));
                                unset($payment['gc_balance']);
                            } elseif ($payment['paid_by'] == 'deposit') {
                                $customer = $this->site->getCompanyByID($data['customer_id']);
                                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount - $payment['amount'])), array('id' => $customer->id));
                            }
                            unset($payment['cc_cvv2']);
                            if (($payment['paid_by'] == 'other' || $payment['paid_by'] == 'NEFT' ) && !empty($_POST['other_tran'][0])) {
                                $payment['transaction_id'] = $_POST['other_tran'][0];
                                $payment['note'] = $_POST['other_tran_mode'][0];
                            }


                            $this->db->insert('payments', $payment);

                            $this->site->updateReference('pay');
                            $paid += $payment['amount'];
                        }
                    }
                }
                $this->site->syncSalePayments($sale_id);
            }
            $redirect_pay_url = isset($data['redirect_pay_url']) ? $data['redirect_pay_url'] : null;
            $this->site->syncQuantity($sale_id);
            if ($sid) {
                $this->deleteBill($sid);
            }
            $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);


            // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                } 



             if($data['coupon_code']){
                $this->usedCopuonCodeManage($data['coupon_code']);
            }

            return array('sale_id' => $sale_id, 'message' => $msg, 'redirect_pay_url' => $redirect_pay_url);
        }

        return false;
    }

    public function getProductByCode($code) {
        $q = $this->db->get_where('products', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductByName($name) {
        $q = $this->db->get_where('products', array('name' => $name), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getAllBillerCompanies() {
        $q = $this->db->get_where('companies', array('group_name' => 'biller'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }

    public function getAllCustomerCompanies() {
        $q = $this->db->get_where('companies', array('group_name' => 'customer'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }

    public function getCompanyByID($id) {

        $q = $this->db->get_where('companies', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getAllProducts() {
        $q = $this->db->query('SELECT * FROM products ORDER BY id');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }

    public function getProductByID($id, $select = '') {
        if (!empty($select)) {
            $q = $this->db->select($select)
                    ->where('id', $id)
                    ->get('products');
        } else {
            $q = $this->db->get_where('products', array('id' => $id), 1);
        }
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getAllTaxRates() {
        $q = $this->db->get('tax_rates');

        if ($q->num_rows() > 0) {

            foreach (($q->result()) as $row) {

                $data['id'] = $row->id;
                $data['name'] = $row->name;
                $data['code'] = $row->code;
                $data['rate'] = $row->rate;
                $data['type'] = $row->type;
                $data['tax_config'] = array();
                if (!empty($row->tax_config)) {
                    $tax_config = unserialize($row->tax_config);
                    foreach ($tax_config as $key => $value) {

                        $data['tax_config'][] = $value;
                    }
                }

                $rdata[] = $data;
            }

            return $rdata;
        }
    }

    public function getTaxAttributes() {
        $q = $this->db->get('sma_tax_attr');

        if ($q->num_rows() > 0) {

            foreach (($q->result()) as $row) {

                $data[] = $row;
            }

            return $data;
        }
    }

    public function getTaxRateByID($id) {

        $q = $this->db->get_where('tax_rates', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function updateProductQuantity($product_id, $warehouse_id, $quantity) {

        if ($this->addQuantity($product_id, $warehouse_id, $quantity)) {
            return true;
        }

        return false;
    }

    public function addQuantity($product_id, $warehouse_id, $quantity) {
        if ($warehouse_quantity = $this->getProductQuantity($product_id, $warehouse_id)) {
            $new_quantity = $warehouse_quantity['quantity'] - $quantity;
            if ($this->updateQuantity($product_id, $warehouse_id, $new_quantity)) {
                $this->site->syncProductQty($product_id, $warehouse_id);
                return TRUE;
            }
        } else {
            if ($this->insertQuantity($product_id, $warehouse_id, -$quantity)) {
                $this->site->syncProductQty($product_id, $warehouse_id);
                return TRUE;
            }
        }
        return FALSE;
    }

    public function insertQuantity($product_id, $warehouse_id, $quantity) {
        if ($this->db->insert('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $quantity))) {
            return true;
        }
        return false;
    }

    public function updateQuantity($product_id, $warehouse_id, $quantity) {
        if ($this->db->update('warehouses_products', array('quantity' => $quantity), array('product_id' => $product_id, 'warehouse_id' => $warehouse_id))) {
            return true;
        }
        return false;
    }

    public function getProductQuantity($product_id, $warehouse) {
        $q = $this->db->get_where('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse), 1);
        if ($q->num_rows() > 0) {
            return $q->row_array(); //$q->row();
        }
        return FALSE;
    }

    public function getItemByID($id) {
        $q = $this->db->get_where('sale_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllSales() {
        $q = $this->db->get('sales');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function sales_count() {
        return $this->db->count_all("sales");
    }

    public function fetch_sales($limit, $start) {
        $this->db->limit($limit, $start);
        $this->db->order_by("id", "desc");
        $query = $this->db->get("sales");

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getAllInvoiceItems($sale_id) {

 $printerSettings = $this->site->defaultPrinterOption($this->Settings->default_printer);
       
        $this->load->model('Sales_model');


        //SELECT c.id, c.name FROM `sma_products` p inner join sma_sale_items si on p.id=si.product_id inner join sma_categories c on p.category_id=c.id WHERE si.sale_id='9610' group by c.id
        if ($this->pos_settings->item_order == 0 || $this->pos_settings->item_order == 2) {
            $this->db->select('sale_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, product_variants.name as variant, products.details as details, categories.id as category_id, categories.name as category_name, product_variants.price as variant_price')
                    ->join('products', 'products.id=sale_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
                    ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                    ->group_by('sale_items.id');
            // ->order_by('id', 'asc');
            if($printerSettings->ascending_order_product_list == 1){
                $this->db->order_by('sale_items.product_name', 'ASC');
            }
           /* if ($this->pos_settings->display_category == 0)
                $this->db->order_by('sale_items.subtotal', 'desc');
            else
                $this->db->order_by('categories.id', 'desc');*/
        } elseif ($this->pos_settings->item_order == 1) {
            $this->db->select('sale_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, product_variants.name as variant, categories.id as category_id, categories.name as category_name, products.details as details')
                    ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
                    ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                    ->join('products', 'products.id=sale_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->group_by('sale_items.id');
            //  ->order_by('categories.id', 'asc')
             if($printerSettings->ascending_order_product_list == 1){
                $this->db->order_by('sale_items.product_name', 'ASC');
            }
           /* if ($this->pos_settings->display_category == 0)
                $this->db->order_by('sale_items.subtotal', 'desc');
            else
                $this->db->order_by('categories.id', 'desc');*/
        }
        $q = $this->db->get_where('sale_items', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                if ($row->product_type == 'combo') {
                    $row->combo_items = $this->Sales_model->getProductComboItems($row->product_id);
                }
                if ($row->product_type == 'Bundle') {
                    $row->combo_items = $this->Sales_model->getProductComboItems($row->product_id);
                }
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllOrderItems($order_id) {
        $this->load->model('Sales_model');

        if ($this->pos_settings->item_order == 0) {
            $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, product_variants.name as variant, products.details as details, categories.id as category_id, categories.name as category_name, product_variants.price as variant_price')
                    ->join('products', 'products.id=order_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
                    ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                    ->group_by('order_items.id');
            // ->order_by('id', 'asc');
            if ($this->pos_settings->display_category == 0)
                $this->db->order_by('order_items.subtotal', 'desc');
            else
                $this->db->order_by('categories.id', 'desc');
        } elseif ($this->pos_settings->item_order == 1) {
            $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, product_variants.name as variant, categories.id as category_id, categories.name as category_name, products.details as details')
                    ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
                    ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                    ->join('products', 'products.id=order_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->group_by('order_items.id');
            //  ->order_by('categories.id', 'asc')
            if ($this->pos_settings->display_category == 0)
                $this->db->order_by('order_items.subtotal', 'desc');
            else
                $this->db->order_by('categories.id', 'desc');
        }//end else

        $q = $this->db->get_where('order_items', array('sale_id' => $order_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                if ($row->product_type == 'combo') {
                    $row->combo_items = $this->Sales_model->getProductComboItems($row->product_id);
                }
                if ($row->product_type == 'Bundle') {
                    $row->combo_items = $this->Sales_model->getProductComboItems($row->product_id);
                }
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getSuspendedsSaleItems($ids) {
        if (!is_array($ids)) {
            return []; // Return an empty array if $ids is not an array
        }
        $this->db->where_in('suspend_id', $ids);
        $query = $this->db->get('suspended_items');
        if ($query->num_rows() > 0) {
            return $query->result();
        }
        return [];
    }    
    public function getSuspendedSaleItems($id) {
        if (is_array($id)) {
            // $q = $this->db->where_in('suspended_items', array('suspend_id' => $id));
            $this->db->where_in('suspend_id', $id);
            $q = $this->db->get('suspended_items');
        }else {
            $q = $this->db->get_where('suspended_items', array('suspend_id' => $id));
        }
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
              
            }

            return $data;
        }
    }

    public function getSuspendedSales($user_id = NULL) {
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $q = $this->db->get_where('suspended_bills', array('created_by' => $user_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
    }
    
    public function getOpenBillByID($id) {
        if (is_array($id)) {
            $this->db->where_in('id', $id);  
            $q = $this->db->get('suspended_bills');
            if ($q->num_rows() > 0) {
                $data = [];
                foreach (($q->result()) as $row) {
                    $data[] = $row;
                }
                return $data;
            }
        } else {
            $q = $this->db->get_where('suspended_bills', array('id' => $id), 1); 
            if ($q->num_rows() > 0) {
                return $q->row();  
            } 
        }
        return FALSE;  
    }
    
    public function getOpenBillBySuspendID($id) {
        $id = ltrim($id, '0');
        $this->db->select('id');
        $this->db->where('customer_id', $id);
        $q = $this->db->get('suspended_bills'); 
    
        if ($q->num_rows() > 0) {
            $data = [];
            foreach ($q->result() as $row) {
                $data[] = $row->id;
            }
            return $data; 
        }
        return []; 
    }
    
    public function getSuspendBulkIds($id) {
        $q = $this->db->where_in('suspended_bills', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $q->result();
        }
        return FALSE;
    }

    public function getInvoiceByID($id) {

        $q = $this->db->get_where('sales', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function bills_count() {
        /*if (!$this->Owner && !$this->Admin) {
            $this->db->where('created_by', $this->session->userdata('user_id'));
        }*/
        return $this->db->count_all_results("suspended_bills");
    }

    public function fetch_bills($limit, $start, $dir = NULL,$customerId) {
        $dir1 = empty($dir) ? 'ASC' : 'DESC';
        /*if (!$this->Owner && !$this->Admin) {
            $this->db->where('created_by', $this->session->userdata('user_id'));
        }*/
        if($customerId){
            $this->db->where('customer_id', $customerId);
        }
        $this->db->limit($limit, $start);
        $this->db->order_by("id", $dir1);
        $query = $this->db->get("suspended_bills");
       
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getTodaySales() {
        $sdate = date('Y-m-d 00:00:00');
        $edate = date('Y-m-d 23:59:59');
        $q = $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total', FALSE)
                ->get_where('sales', "date >= '$sdate'");

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }
    public function getTodaySale($user_id=NULL) {
        $sdate = date('Y-m-d 00:00:00');
        $edate = date('Y-m-d 23:59:59');

        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total', FALSE);
        $this->db->where('date >=', $sdate);
        $this->db->where('date <=', $edate);

        if($user_id) {
            $this->db->where('created_by', $user_id);
        }
        $q = $this->db->get('sales');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* --- 20-03-19 -- */

    public function getcalpartial($user_id=NULL) {
        $sdate = date('Y-m-d 00:00:00');
        $edate = date('Y-m-d 23:59:59');

            $this->db->select('SUM( COALESCE( grand_total, 0 ) ) - SUM( COALESCE( paid, 0 ) ) AS partial_due', FALSE);
            $this->db->where(['payment_status' => 'partial']);
            $this->db->where("date >= '$sdate'");
        if($user_id) {
            $this->db->where('created_by', $user_id);
        }
        $q = $this->db->get('sales');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* --- 20-03-19 --- */

    public function getTodayDueSales($user_id=NULL) {
        $sdate = date('Y-m-d 00:00:00');
        $edate = date('Y-m-d 23:59:59');
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total', FALSE);
             $this->db->where(['payment_status' => 'due']);
             $this->db->where("date >= '$sdate'");
        if($user_id) {
            $this->db->where('created_by', $user_id);
        }  
        $q = $this->db->get('sales');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodaySalesPaid($user_id=NULL) {
        $sdate = date('Y-m-d 00:00:00');
        $edate = date('Y-m-d 23:59:59');

        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('sales.date >=', $sdate)->where('payments.date <=', $edate);
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getCosting() {
        $date = date('Y-m-d');
        $this->db->select('SUM( COALESCE( purchase_unit_cost, 0 ) * quantity ) AS cost, SUM( COALESCE( sale_unit_price, 0 ) * quantity ) AS sales, SUM( COALESCE( purchase_net_unit_cost, 0 ) * quantity ) AS net_cost, SUM( COALESCE( sale_net_unit_price, 0 ) * quantity ) AS net_sales', FALSE)
                ->where('date', $date);

        $q = $this->db->get('costing');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayCCSales($user_id = NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'CC');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayDCSales($user_id = NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_dc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'DC');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayGiftCardSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_gc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'gift_card');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayOtherSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_other_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'other');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* 5-11-2019 */

    public function getTodayDepSales($register_open_time=Null, $user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_gc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'deposit');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }
    public function getTodayUpiQrcodePayments($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'UPI_QRCODE');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
     }

    /**/

    public function getTodayCashSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'cash');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayRefunds($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')->where('payments.date >', $date);
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayExpenses($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('SUM( COALESCE( amount, 0 ) ) AS total', FALSE)
                ->where('date >', $date);
        if($user_id) {
            $this->db->where('created_by', $user_id);
        }
        $q = $this->db->get('expenses');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayCashRefunds($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')->where('payments.date >', $date)->where('payments.paid_by', 'cash');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayChSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'Cheque');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayPPPSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'ppp');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayStripeSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'stripe');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayAuthorizeSales($user_id=NULL) {
        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'authorize');
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getTodayPaymentOptionSales($payOpt = '', $user_id=NULL) {
        if (empty($payOpt))
            return false;

        $date = date('Y-m-d 00:00:00');
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', $payOpt);
        if($user_id) {
            $this->db->where('payments.created_by', $user_id);
        }
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date);
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getdueAmt($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS duetotal', FALSE)
                ->where('payment_status', 'due')->where('date >', $date);
        $this->db->where('created_by', $user_id);

        $q = $this->db->get('sma_sales');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* ---20 -03-19 -- */

    public function getpartialAmt($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) )  - SUM( COALESCE( paid, 0 ) ) AS partial_due', FALSE)
                ->where('payment_status', 'partial')->where('date >', $date);
        $this->db->where('created_by', $user_id);

        $q = $this->db->get('sma_sales');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* -- 20-03-19-- */

    public function getRegisterCCSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'CC');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterCashSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'cash');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterRefunds($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')->where('payments.date >', $date);
        $this->db->where('payments.created_by', $user_id);
        $this->db->where('payments.paid_by ', 'cash');
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterRefundsOther($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')->where('payments.date >', $date);
        $this->db->where('payments.created_by', $user_id);
        $this->db->where('payments.paid_by !=', 'cash');

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }


    public function getRegisterCashRefunds($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')->where('payments.date >', $date)->where('payments.paid_by', 'cash');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterExpenses($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('SUM( COALESCE( amount, 0 ) ) AS total', FALSE)
                ->where('date >', $date);
        $this->db->where('created_by', $user_id);

        $q = $this->db->get('expenses');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterChSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('payments.paid_by', 'Cheque');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterGCSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('paid_by', 'gift_card');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterPPPSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('paid_by', 'ppp');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* 05-11-2019   GetDepositSale */

    public function getRegisterdepSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('paid_by', 'deposit');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* 5-11-2019 */

    /* --- 13-03-19 New Register Seles  All payment Option Use Where Condition ---- */

    public function getRegisterPaymentSales($date, $user_id = NULL, $condition) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('paid_by', $condition);
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /* -- End 13-03-19 --- */

    public function getRegisterStripeSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('paid_by', 'stripe');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function getRegisterAuthorizeSales($date, $user_id = NULL) {
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')->where('payments.date >', $date)->where('paid_by', 'authorize');
        $this->db->where('payments.created_by', $user_id);

        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    public function suspendSale($data = array(), $items = array(), $did = NULL) {

        //KOT Log  05-04-19
       /* $getkot_log = $this->getkotlog(array('kot_date' => date('Y-m-d')));
        if (empty($getkot_log)) {
            $tokan = '1';
            $kotlog = array('tokan' => $tokan, 'kot_date' => date('Y-m-d'));
            $this->actionkotlog('Insert', $kotlog, array('id' => $getkot_log->id));
        } else {
            $tokan = $getkot_log->tokan + 1;
            $kotlog = array('tokan' => $tokan);
            $this->actionkotlog('Update', $kotlog, array('id' => $getkot_log->id));
        }*/
        // End KOT log 05-04-19 
            $details = array(
                'customer_id' =>  $data['customer_id'],
                'created_by' => $this->session->userdata('user_id'),
            );
            $referene_no = $this->sma->generateSuspendRefNo($details);
        $sData = array(
            'count' => $data['total_items'],
            'biller_id' => $data['biller_id'],
            'reference_no' => $referene_no,
            'customer_id' => $data['customer_id'],
            'warehouse_id' => $data['warehouse_id'],
            'customer' => $data['customer'],
            'date' => $data['date'],
            'suspend_note' => $data['suspend_note'],
            'table_id' => $data['table_id'],
            'total' => $data['grand_total'],
            'order_tax_id' => $data['order_tax_id'],
            'order_discount_id' => $data['order_discount_id'],
            'created_by' => $this->session->userdata('user_id'),
           // 'kot_tokan' => $tokan
        );
        // print_r($sData);exit;
        if ($did) {
     
            $suspendSales = $this->db->select('table_id')->where(['id'=> $did])->get('suspended_bills')->row();
       
            $this->db->where(['id'=>$suspendSales->table_id])->update('sma_restaurant_tables',['bill_printed'=>0 ]);
            //,'seats' => 0

   
            $query = $this->db->get_where('suspended_items', array('suspend_id' => $did));
            $previous_items = $query->result_array();

            /* foreach($items as $key=>$val){
              $old_key = array_search($val['product_id'], array_column($previous_items, 'product_id'));//print_r($items);print_r($previous_items);exit;
              if($old_key){
              $items[$key]['isdelivered'] = $previous_items[$old_key]['isdelivered'];
              }else{
              $items[$key]['isdelivered']=0;
              }
              } */
            foreach ($items as $key => $val) {

                if (isset($val['delivered_quantity'])) {
                    unset($val['delivered_quantity']);
                }
                $old_item = array();
                foreach ($previous_items as $pkey => $pval) {


                    $pval['option_id'] = (int) $pval['option_id'];
                    $val['option_id'] = (int) $val['option_id'];
                    $pval['quantity'] = (float) $pval['quantity'];
                    $val['quantity'] = (float) $val['quantity'];

                    if ($val['product_id'] == $pval['product_id'] && $val['option_id'] == $pval['option_id']) {

                        if ($pval['quantity'] <= $val['quantity']) {
                            $items[$key]['isdelivered'] = $pval['isdelivered'];
                        } else {
                            //echo "fggfgg";exit;
                            $items[$key]['isdelivered'] = 0;
                        }
                        $old_item = $items[$key];
                    }
                }

                if (array_key_exists('isdelivered', $old_item)) {
                    
                } else {
                    $items[$key]['isdelivered'] = 0;
                }
            }


            if ($this->db->update('suspended_bills', $sData, array('id' => $did)) && $this->db->delete('suspended_items', array('suspend_id' => $did))) {
                $addOn = array('suspend_id' => $did);
                end($addOn);
                foreach ($items as &$var) {
                    $var = array_merge($addOn, $var);
                }

                if ($this->db->insert_batch('suspended_items', $items)) {
                    return TRUE;
                }
            }
        } else {
// echo "<pre>";
// print_r($sData);
// exit;
//             if ($sData['reference_no'] == $referene_no) {
               
//             }
            if ($this->db->insert('suspended_bills', $sData)) {
                $suspend_id = $this->db->insert_id();
                $addOn = array('suspend_id' => $suspend_id);
                end($addOn);
                foreach ($items as &$var) {
                    $var = array_merge($addOn, $var);
                }
                if ($this->db->insert_batch('suspended_items', $items)) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    /* public function suspendSale($data = array(), $items = array(), $did = NULL)
      {
      $sData = array(
      'count' => $data['total_items'],
      'biller_id' => $data['biller_id'],
      'customer_id' => $data['customer_id'],
      'warehouse_id' => $data['warehouse_id'],
      'customer' => $data['customer'],
      'date' => $data['date'],
      'suspend_note' => $data['suspend_note'],
      'total' => $data['grand_total'],
      'order_tax_id' => $data['order_tax_id'],
      'order_discount_id' => $data['order_discount_id'],
      'created_by' => $this->session->userdata('user_id')
      );

      if ($did) {

      if ($this->db->update('suspended_bills', $sData, array('id' => $did)) && $this->db->delete('suspended_items', array('suspend_id' => $did))) {
      $addOn = array('suspend_id' => $did);
      end($addOn);
      foreach ($items as &$var) {
      $var = array_merge($addOn, $var);
      }
      if ($this->db->insert_batch('suspended_items', $items)) {
      return TRUE;
      }
      }

      } else {

      if ($this->db->insert('suspended_bills', $sData)) {
      $suspend_id = $this->db->insert_id();
      $addOn = array('suspend_id' => $suspend_id);
      end($addOn);
      foreach ($items as &$var) {
      $var = array_merge($addOn, $var);
      }
      if ($this->db->insert_batch('suspended_items', $items)) {
      return TRUE;
      }
      }

      }
      return FALSE;
      } */

      public function deleteBill($ids) {
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        $tables = $this->db->select('table_id')
                            ->where_in('id', $ids)  // Use where_in to handle multiple IDs
                            ->get('suspended_bills')
                            ->result();
        foreach ($tables as $table) {
            $this->db->where(['id' => $table->table_id])
                     ->update('restaurant_tables', ['bill_printed' => '0', 'seats' => 0]);
        }
        if (is_array($ids)) {
            $this->db->where_in('suspend_id', $ids);
            $this->db->delete('suspended_items');
            $this->db->where_in('id', $ids);
            $this->db->delete('suspended_bills');
            if ($this->db->affected_rows() > 0) {
                return true;
            }
        }else {
            if ($this->db->delete('suspended_items', array('suspend_id' => $ids)) && $this->db->delete('suspended_bills', array('id' => $ids))) {
                return true;
            }
        }
       
       
        return FALSE;
    }
    

    public function getInvoicePayments($sale_id) {
        $q = $this->db->get_where("payments", array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }

        return FALSE;
    }

    public function getInvoicePayments1($sale_id) {
        //$this->db->where_in('sale_id', $sale_id);
        // $q = $this->db->get("payments");
        $q = $this->db->query("select * from sma_payments where sale_id in($sale_id)");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }

        return FALSE;
    }

    public function stripe($amount = 0, $card_info = array(), $desc = '') {
        $this->load->model('stripe_payments');
        //$card_info = array( "number" => "4242424242424242", "exp_month" => 1, "exp_year" => 2016, "cvc" => "314" );
        //$amount = $amount ? $amount*100 : 3000;
        unset($card_info['type']);
        $amount = $amount * 100;
        if ($amount && !empty($card_info)) {
            $token_info = $this->stripe_payments->create_card_token($card_info);
            if (!isset($token_info['error'])) {
                $token = $token_info->id;
                $data = $this->stripe_payments->insert($token, $desc, $amount, $this->default_currency->code);
                if (!isset($data['error'])) {
                    $result = array('transaction_id' => $data->id,
                        'created_at' => date($this->dateFormats['php_ldate'], $data->created),
                        'amount' => ($data->amount / 100),
                        'currency' => strtoupper($data->currency)
                    );
                    return $result;
                } else {
                    return $data;
                }
            } else {
                return $token_info;
            }
        }
        return false;
    }

    public function paypal($amount = NULL, $card_info = array(), $desc = '') {
        $this->load->model('paypal_payments');
        //$card_info = array( "number" => "5522340006063638", "exp_month" => 2, "exp_year" => 2016, "cvc" => "456", 'type' => 'MasterCard' );
        //$amount = $amount ? $amount : 30.00;
        if ($amount && !empty($card_info)) {
            $data = $this->paypal_payments->Do_direct_payment($amount, $this->default_currency->code, $card_info, $desc);
            if (!isset($data['error'])) {
                $result = array('transaction_id' => $data['TRANSACTIONID'],
                    'created_at' => date($this->dateFormats['php_ldate'], strtotime($data['TIMESTAMP'])),
                    'amount' => $data['AMT'],
                    'currency' => strtoupper($data['CURRENCYCODE'])
                );
                return $result;
            } else {
                return $data;
            }
        }
        return false;
    }

    public function authorize($authorize_data) {
        $this->load->library('authorize_net');
        // $authorize_data = array( 'x_card_num' => '4111111111111111', 'x_exp_date' => '12/20', 'x_card_code' => '123', 'x_amount' => '25', 'x_invoice_num' => '15454', 'x_description' => 'References');
        $this->authorize_net->setData($authorize_data);

        if ($this->authorize_net->authorizeAndCapture()) {
            $result = array(
                'transaction_id' => $this->authorize_net->getTransactionId(),
                'approval_code' => $this->authorize_net->getApprovalCode(),
                'created_at' => date($this->dateFormats['php_ldate']),
            );
            return $result;
        } else {
            return array('error' => 1, 'msg' => $this->authorize_net->getError());
        }
    }

    public function addPayment($payment = array(), $customer_id = null) {
        if (isset($payment['sale_id']) && isset($payment['paid_by']) && isset($payment['amount'])) {
            $payment['pos_paid'] = $payment['amount'];
            $inv = $this->getInvoiceByID($payment['sale_id']);
            $paid = $inv->paid + $payment['amount'];
            if ($payment['paid_by'] == 'ppp') {
                $card_info = array("number" => $payment['cc_no'], "exp_month" => $payment['cc_month'], "exp_year" => $payment['cc_year'], "cvc" => $payment['cc_cvv2'], 'type' => $payment['cc_type']);
                $result = $this->paypal($payment['amount'], $card_info);
                if (!isset($result['error'])) {
                    $payment['transaction_id'] = $result['transaction_id'];
                    $payment['date'] = $this->sma->fld($result['created_at']);
                    $payment['amount'] = $result['amount'];
                    $payment['currency'] = $result['currency'];
                    unset($payment['cc_cvv2']);
                    $this->db->insert('payments', $payment);
                    $paid += $payment['amount'];
                } else {
                    $msg[] = lang('payment_failed');
                    if (!empty($result['message'])) {
                        foreach ($result['message'] as $m) {
                            $msg[] = '<p class="text-danger">' . $m['L_ERRORCODE'] . ': ' . $m['L_LONGMESSAGE'] . '</p>';
                        }
                    } else {
                        $msg[] = lang('paypal_empty_error');
                    }
                }
            } elseif ($payment['paid_by'] == 'stripe') {
                $card_info = array("number" => $payment['cc_no'], "exp_month" => $payment['cc_month'], "exp_year" => $payment['cc_year'], "cvc" => $payment['cc_cvv2'], 'type' => $payment['cc_type']);
                $result = $this->stripe($payment['amount'], $card_info);
                if (!isset($result['error'])) {
                    $payment['transaction_id'] = $result['transaction_id'];
                    $payment['date'] = $this->sma->fld($result['created_at']);
                    $payment['amount'] = $result['amount'];
                    $payment['currency'] = $result['currency'];
                    unset($payment['cc_cvv2']);
                    $this->db->insert('payments', $payment);
                    $paid += $payment['amount'];
                } else {
                    $msg[] = lang('payment_failed');
                    $msg[] = '<p class="text-danger">' . $result['code'] . ': ' . $result['message'] . '</p>';
                }
            } elseif ($payment['paid_by'] == 'authorize') {
                $authorize_arr = array("x_card_num" => $payment['cc_no'], "x_exp_date" => ($payment['cc_month'] . '/' . $payment['cc_year']), "x_card_code" => $payment['cc_cvv2'], 'x_amount' => $payment['amount'], 'x_invoice_num' => $inv->id, 'x_description' => 'Sale Ref ' . $inv->reference_no . ' and Payment Ref ' . $payment['reference_no']);
                list($first_name, $last_name) = explode(' ', $payment['cc_holder'], 2);
                $authorize_arr['x_first_name'] = $first_name;
                $authorize_arr['x_last_name'] = $last_name;
                $result = $this->authorize($authorize_arr);
                if (!isset($result['error'])) {
                    $payment['transaction_id'] = $result['transaction_id'];
                    $payment['approval_code'] = $result['approval_code'];
                    $payment['date'] = $this->sma->fld($result['created_at']);
                    unset($payment['cc_cvv2']);
                    $this->db->insert('payments', $payment);
                    $paid += $payment['amount'];
                } else {
                    $msg[] = lang('payment_failed');
                    $msg[] = '<p class="text-danger">' . $result['msg'] . '</p>';
                }
            } else {
                if ($payment['paid_by'] == 'gift_card') {
                    $gc = $this->site->getGiftCardByNO($payment['cc_no']);
                    $this->db->update('gift_cards', array('balance' => ($gc->balance - $payment['amount'])), array('card_no' => $payment['cc_no']));
                } elseif ($customer_id && $payment['paid_by'] == 'deposit') {
                    $customer = $this->site->getCompanyByID($customer_id);
                    $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount - $payment['amount'])), array('id' => $customer_id));
                }
                unset($payment['cc_cvv2']);
                $this->db->insert('payments', $payment);
                $paid += $payment['amount'];
            }
            if (!isset($msg)) {
                if ($this->site->getReference('pay') == $data['reference_no']) {
                    $this->site->updateReference('pay');
                }
                $this->site->syncSalePayments($payment['sale_id']);
                return array('status' => 1, 'msg' => '');
            }
            return array('status' => 0, 'msg' => $msg);
        }
        return false;
    }

    public function instamojo($data) {
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

    public function getInstamojoTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('instamojo', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updateInstamojoTransaction($id, $data = array()) {

        $this->db->where('request_id', $id);
        if ($this->db->update('instamojo', $data)) {
            return true;
        }
        return false;
    }

    public function instomojoAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['payment_id'];
        $payment['amount'] = $result['amount'];
        $payment['currency'] = $result['currency'];
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['amount'];
        $payment['paid_by'] = 'instomojo';
        $payment['date'] = $result['created_at'];
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }

    public function addCcavenueTransaction($data) {
        $arr = array();
        $arr['order_id'] = $data["sale_id"];
        $arr['request_data'] = serialize($data["req_data"]);
        $arr['created_time'] = date("Y-m-d H:i:s");
        $this->db->insert('ccavenue', $arr);
    }

    public function getCcavenueTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('ccavenue', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updateCcavenueTransaction($id, $data = array()) {

        $this->db->where('order_id', $id);
        if ($this->db->update('ccavenue', $data)) {
            return true;
        }
        return false;
    }

    public function CcavenueAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['tracking_id'];
        $payment['amount'] = $result['amount'];
        $payment['currency'] = $result['currency'];
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['amount'];
        $payment['paid_by'] = 'ccavenue';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $trans_date = $this->CcavenueTransTime($result['trans_date']);
        if (!empty($trans_date)):
            $payment['date'] = $trans_date;
        endif;

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }

    public function CcavenueTransTime($time) {
        if (!empty($time)) {
            $arr1 = explode(" ", $time);
            $arr2 = explode("/", $arr1[0]);
            return $dt = $arr2[2] . '-' . $arr2[1] . '-' . $arr2[0] . ' ' . $arr1[1];
        }
        return false;
    }

    public function syncOrderReward($sale_id) {
        if (empty($sale_id)):
            return false;
        endif;
        $dupSunc = $this->isOrderRewardSync($sale_id);
        if ($dupSunc > 0) {
            return false;
        }
        if ($this->db->insert('rewardpoint_sync', array('sale_id' => $sale_id, 'posttime' => date("Y-m-d H:i:s")))) {
            $cid = $this->db->insert_id();
            return $cid;
        }
        return false;
    }

    public function isOrderRewardSync($sale_id) {
        $q = $this->db->get_where('rewardpoint_sync', array('sale_id' => $sale_id), 1);
        if ($q->num_rows() > 0) {
            return $q->num_rows();
        }
        return FALSE;
    }

    public function addPaytmTransaction($data) {
        $arr = array();
        $arr['order_id'] = $data["sale_id"];
        $arr['request_data'] = serialize($data["req_data"]);
        $arr['created_time'] = date("Y-m-d H:i:s");
        $this->db->insert('paytm', $arr);
    }

    public function getPaytmTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('paytm', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updatePaytmTransaction($id, $data = array()) {

        $this->db->where('order_id', $id);
        if ($this->db->update('paytm', $data)) {
            return true;
        }
        return false;
    }

    public function PaytmAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['TXNID'];
        $payment['amount'] = $result['TXNAMOUNT'];
        $payment['currency'] = $result['CURRENCY'];
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['TXNAMOUNT'];
        $payment['paid_by'] = 'paytm';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $trans_date = $this->paytmTransTime($result['TXNDATE']);
        if (!empty($trans_date)):
            $payment['date'] = $trans_date;
        endif;

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }

    public function paytmTransTime($time) {
        if (!empty($time)) {
            $arr1 = explode(".", $time);

            return $arr1[0];
        }
        return false;
    }

    public function addPayswiffTransaction($data) {
        $arr = array();
        $arr['order_id'] = $data["sale_id"];
        $arr['request_data'] = serialize($data["req_data"]);

        if (isset($data['secret_token']) && !empty($data['secret_token'])):
            $arr['secret_token'] = $data['secret_token'];
        endif;

        $arr['created_time'] = date("Y-m-d H:i:s");
        $this->db->insert('payswiff', $arr);
    }

    public function getPayswiffTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('payswiff', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updatePayswiffTransaction($id, $data = array()) {
        $this->db->where('order_id', $id);
        if ($this->db->update('payswiff', $data)) {
            return true;
        }
        return false;
    }

    public function PayswiffAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['transactionId'];
        $payment['amount'] = $result['amount'];
        $payment['currency'] = $result['currencyCode'];
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['amount'];
        $payment['paid_by'] = 'paynear';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $payment['date'] = $result['transactionDate'];

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }

    public function addPaynearTransaction($data) {
        $arr = array();
        $arr['order_id'] = $data["sale_id"];
        $arr['request_data'] = serialize($data["req_data"]);

        if (isset($data['secret_token']) && !empty($data['secret_token'])):
            $arr['secret_token'] = $data['secret_token'];
        endif;

        $arr['created_time'] = date("Y-m-d H:i:s");
        $this->db->insert('paynear', $arr);
    }

    public function getPaynearTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('paynear', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updatePaynearTransaction($id, $data = array()) {
        $this->db->where('order_id', $id);
        if ($this->db->update('paynear', $data)) {
            return true;
        }
        return false;
    }

    public function PaynearAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['transactionId'];
        $payment['amount'] = $result['amount'];
        $payment['currency'] = $result['currencyCode'];
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['amount'];
        $payment['paid_by'] = 'paynear';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $payment['date'] = $result['transactionDate'];

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }

    public function addPayumoneyTransaction($data) {
        $arr = array();
        $arr['order_id'] = $data["sale_id"];
        $arr['request_data'] = serialize($data["req_data"]);
        $arr['created_time'] = date("Y-m-d H:i:s");
        $this->db->insert('payumoney', $arr);
    }

    public function getPayumoneyTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('payumoney', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function updatePayumoneyTransaction($id, $data = array()) {

        $this->db->where('order_id', $id);
        if ($this->db->update('payumoney', $data)) {
            return true;
        }
        return false;
    }

    public function PayumoneyAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['payuMoneyId'];
        $payment['amount'] = $result['net_amount_debit'];
        $payment['currency'] = 'INR';
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['net_amount_debit'];
        $payment['paid_by'] = 'payumoney';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $payment['date'] = $result['addedon'];


        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }

    public function featuerd_products_count() {
        $this->db->where('is_featured', 1);
        $this->db->from('products');
        return $this->db->count_all_results();
    }

    public function fetch_featuerd_products($limit, $start) {

        $this->db->limit($limit, $start);
        $this->db->where('is_featured', 1);
        $this->db->order_by("id", "asc");
        $query = $this->db->get("products");

        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function deleteSuspend($did) {
        $this->db->delete('suspended_bills', array('id' => $did)) &&
                $this->db->delete('suspended_items', array('suspend_id' => $did));
        return true;
    }

    public function getGiftcardByCustomer($customer_id) {
        $result = $this->db->query("SELECT `value`, `balance`, `card_no`,expiry FROM `sma_gift_cards` WHERE `customer_id` = '$customer_id' AND `balance` > 0 AND `expiry` <= 'now()' ORDER BY `balance` DESC LIMIT 1");

        if ($result->num_rows() > 0) {
            foreach ($result->result_array() as $row) {
                $data = $row;
            }
            return $data;
        }
        return false;
    }

    /* 06-11-2019 */

    public function getDepositByCustomer($customer_id) {

        $result = $this->db->query("SELECT  c.deposit_amount as balanceamt ,d.amount as value FROM sma_companies as c left join  sma_deposits d on d.company_id=c.id  WHERE  c.id = '$customer_id' ");

        if ($result->num_rows() > 0) {
            foreach ($result->result_array() as $row) {
                $data = $row;
            }
            return $data;
        }
        return false;
    }

    /**/

    public function updateOrderTransaction($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('order_transactions', $data)) {
            return true;
        }
        return false;
    }

    public function getActiveOffers($offer_keyword) {
        $q = $this->db->select('*');
        $q = $this->db->where(['offer_keyword' => $offer_keyword, 'is_active' => 1, 'is_delete' => 0])->get('offers');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    //18-04-19
    public function get_offer_details($id = NULL) {
        return $this->db->select('*')->where(['id' => $id])->get('offers')->row();
    }

    public function offerUpdates($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('offers', $data)) {
            return true;
        }
    }

    // 05-04-19
    public function getkotlog($condition) {


        return $this->db->select('*')->where($condition)->get('sma_kot_log')->row();
    }

    public function actionkotlog() {
        $getdata = func_get_args(); // 0. Key Type, 1. Update/Insert Data, 2. Where Condition
        $retrundata = '';
        switch ($getdata[0]) {
            case 'Insert':
                $this->db->insert('sma_kot_log', $getdata[1]);
                if ($this->db->affected_rows() > 0) {
                    $retrundata = 'TRUE';
                } else {
                    $retrundata = 'FALSE';
                }
                break;
            case 'Update':
                $this->db->where($getdata[2])->update('sma_kot_log', $getdata[1]);
                if ($this->db->affected_rows() > 0) {
                    $retrundata = 'TRUE';
                } else {
                    $retrundata = 'FALSE';
                }
                break;
        }
        return $retrundata;
    }

    // End 05-04-019
    // Pass KOT Tokan
    public function getkottokans($id = NULL) {
        $kot = $this->db->select('kot_tokan')->where('id', $id)->get('sma_suspended_bills')->row();
        return $kot->kot_tokan;
    }

    // End 9-04-19
    // Theme Update
    public function themeChange($theme) {
        $data = array('theme' => $theme);
        $this->db->where('setting_id', '1')->update('sma_settings', $data);

        if ($this->db->affected_rows() > 0) {
            $themeResponse = "TRUE";
        } else {
            $themeResponse = "FALSE";
        }
        return $themeResponse;
    }

    // End Theme Update
    public function getRecentPosSale($limit, $Customer) {
        $Sql = "SELECT s.id, DATE_FORMAT(s.date, '%Y-%m-%d %T') as date, s.reference_no, s.biller, s.customer, s.sale_status, (s.grand_total+s.rounding) as grand_total, s.paid, (s.grand_total+s.rounding-s.paid) as balance, s.payment_status, s.attachment, s.return_id, s.delivery_status, c.email as cemail FROM sma_sales as s left join sma_companies c on c.id=s.customer_id  WHERE 1 and s.pos=1 " . $Customer . " order by s.id desc limit 0," . $limit;
        $Res = $this->db->query($Sql);
        return $Res->result_array();
    }

    public function getUsersbyGroupId() {
        $q = $this->db->select('email')
                ->get_where('users', array('group_id' => 1, 'active' => 1), 1);
        if ($q->num_rows() > 0) {
            foreach ($q->row() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function need_to_delete() {
        $needdelete = $this->db->select('id')->where(['suspend_note' => 'need to delete'])->get('sma_suspended_bills')->result();
        foreach ($needdelete as $deleteitem) {
            $this->db->where(['suspend_id' => $deleteitem->id])->delete('sma_suspended_items');
        }
        $this->db->where(['suspend_note' => 'need to delete'])->delete('sma_suspended_bills');
    }

    /**
     * 
     * @param type $cardNo
     * @return type
     */
    public function giftcardBalance($cardNo) {

        $q = $this->db->select('balance')->where(['card_no' => $cardNo])->get('sma_gift_cards')->row();
        return $q;
    }

    /* Deposite Amount Show Invoice 4-11-2019 */

    public function depositeBalance($company_id) {
        $deposite = $this->db->select('deposit_amount')->where(['id' => $company_id])->get('sma_companies')->row();
        return $deposite;
    }

    /* Rounding Show Invoice 4-11-2019 */

    public function salesRounding($sale_id) {
        $rounding = $this->db->select('rounding')->where(['id' => $sale_id])->get('sma_sales')->row();
        return $rounding;
    }

    /*     * *Return functionality 17-03-2020** */

    public function getAllReturnInvoiceByID($id) {
        $q = $this->db->get_where('sales', array('sale_id' => $id));
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getAllReturnInvoiceItemByItemID($id) {
        $q = $this->db->get_where('sma_sale_items', array('sale_item_id' => $id));
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getAllReturnInvoiceItems($sale_id, $return_id = NULL) {
        $this->db->select('sale_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, product_variants.name as variant, product_variants.price as variant_price, products.hsn_code as hsncode, sales.rounding as rounding')
                ->join('products', 'products.id=sale_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                ->join('sales', 'sales.id=sale_items.sale_id', 'left')
                ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
                ->group_by('sale_items.id')
                ->order_by('id', 'asc');
        $this->db->where('sales.sale_id', $sale_id);
        $q = $this->db->get('sale_items');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /*     * *** */

    /**
     * 
     * @param type $orderNo
     * @return boolean
     */
    public function getShipingDetails($orderNo) {
        $getOrderid = $this->db->select('id,deliver_later,time_slotes')->where(['invoice_no' => $orderNo])->get('orders')->row();

        if ($getOrderid) {
            $shippingdetails = $this->db->where(['sale_id' => $getOrderid->id])->get('eshop_order')->row();
            $shippingdetails->deliver_later = $getOrderid->deliver_later;
            $shippingdetails->time_slotes = $getOrderid->time_slotes;

            return $shippingdetails;
        } else {
            return false;
        }
        return false;
    }

    /*     * *12-09-2020** */

    public function getAwardPointByCustomer($customer_id) {

        $result = $this->db->query("SELECT c.award_points, c.phone FROM sma_companies c  WHERE  c.id = '$customer_id' ");

        if ($result->num_rows() > 0) {
            foreach ($result->result_array() as $row) {
                $data = $row;
            }
            return $data;
        }
        return false;
    }

    /*     * *12-09-2020** */

    public function dailysales() {
        $result = $this->db->where('DATE(date)', date('Y-m-d'))->get('sma_sales')->result();
        return $result;
    }

    public function dailySalesProduct($salesId) {
        $result = $this->db->where(['sale_id' => $salesId])->get('sma_sale_items')->result();
        return $result;
    }

    public function getCouponByCode($coupon_code) {
        $q = $this->db->select('*');
        $q = $this->db->where(['coupon_code' => $coupon_code, 'is_active' => 1, 'is_delete' => 0])->get('coupons');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /**
     * Razorpay Payment Store
     * @param type $result
     * @param type $sid
     * @return boolean
     */
    public function RazorPayAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['razorpay_payment_id'];
        $payment['amount'] = $result['amount'];
        $payment['currency'] = $result['currency'];
        $payment['sale_id'] = $sid;
        $payment['pos_paid'] = $result['amount'];
        $payment['paid_by'] = 'razorpay';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $trans_date = date('Y-m-d H:i:s');
        if (!empty($trans_date)):
            $payment['date'] = $trans_date;
        endif;

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['sale_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->site->updateReference('pay');
            $this->site->syncSalePayments($sid);
            return $sid;
        endif;

        return false;
    }


   
    /***************************************
     * Discount Coupon
     ***************************************/
    /**
     * 
     * @param type $coupon_code
     * @return boolean
     */
       public function getDiscountCouponByCode($coupon_code){
           
             $q = $this->db->select('*');
             $q = $this->db->where(['coupon_code' => $coupon_code, 'is_active' => 1, 'is_deleted' => 0])->get('discount_coupons');
             if ($q->num_rows() > 0) {
                foreach (($q->result()) as $row) {
                    $data[$row->id] = $row;
                 }
                 return $data;
             }
             return FALSE; 
       } 
    
       /**
        * 
        * @param type $CouponCode
        * @return boolean
        */
       public function usedCopuonCodeManage($CouponCode){
           
          $couponDetails =  $this->db->select('*')->where(['coupon_code' => $CouponCode])->get('sma_discount_coupons')->row();
           
          if($this->db->affected_rows()){
              $updateData = array();
         
              $usedCoupon = $couponDetails->used_coupons + 1;


              if($couponDetails->max_coupons){                
                  if($couponDetails->max_coupons == $usedCoupon ){
                      $updateData['status'] = 'used';
                  }
                  $updateData['used_coupons'] = $usedCoupon;
              }else{
                  $updateData['used_coupons'] = $usedCoupon ;
              }
              $updateData['updated_at'] = date('Y-m-d H:i:s');
              $this->db->where(['id' => $couponDetails->id])->update('sma_discount_coupons',$updateData);
              if($this->db->affected_rows()){
                    return TRUE;
              }else{
                  return FALSE;
              }             
              
          } else {
              return FALSE;
          }
           
       }
    
    /***************************************
     * End Discount Coupon
     * *************************************/

      /**
     * Get Current Date Deposit
     * @param type $date
     * @return type
     */
    public function getRegisterDeposit($register_open_time,  $user_id = NULL){
        if (!$date) {
            $date = $this->session->userdata('register_open_time');
        }
        
         if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        
         $this->db->select("sum( COALESCE(amount, 0)) as deposit_amount,  group_concat(DISTINCT  paid_by SEPARATOR ',') as paid_by")
                ->where('DATE_FORMAT(date,"%Y-%m-%d") >',$date);
                $this->db->where('created_by', $user_id);
//                $this->db->group_by('date(date)');
               $getData =    $this->db->get('deposits')->row();
       
              
         return $getData;
    }
    
    /**
     * 
     * @param type $user_id
     * @return type
     */
    public function getTodayDeposit($user_id = NULL){
        $date = date('Y-m-d 00:00:00');
         
         if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        
         $this->db->select("sum( COALESCE(amount, 0)) as deposit_amount,  group_concat(DISTINCT  paid_by SEPARATOR ',') as paid_by")
                ->where('date >',$date);
                $this->db->where('created_by', $user_id);
               
               $getData =    $this->db->get('deposits')->row();
            return $getData;
    }
    
    /**
     * Get Table Groups
     * @return type
     */
    public function getTableGroup($tableids){
               $this->db->select('table_group');
                if($tableids){
                    $tables = explode(",", $tableids);
                    $this->db->where_in('id',$tableids);
                }
        $getTables =  $this->db->group_by('table_group')->get('restaurant_tables')->result_array();
        return ($this->db->affected_rows()? $getTables : FALSE);
        
    }
    

  
     /**
     * 
     * @param type $table_id
     * @return type
     */
    public function getBillData($table_id){
       $getData=  $this->db->where(['table_id'=> $table_id])->get('suspended_bills')->row();

       if($getData){
           if(!$getData->bill_no){
            $billno =  $this->manageBillNo();
            $this->db->where(['id'=>$getData->id])->update('suspended_bills',['bill_no'=>$billno]);
            $getData->bill_no = $billno;
          }
       }
     
       $billdetails = $this->db->where(['suspend_id' =>$getData->id ])->get('suspended_items')->result();
       $table = $this->db->where(['id' =>$table_id ])->get('restaurant_tables')->row();
       $response = ['inv' =>$getData, 'items'=> $billdetails,'table' =>$table    ];
       return $response;
    }
    
    /**
     * Bill No Manage
     * @return int
     */
    public function manageBillNo(){
      // $billlog =  $this->db->select('*')->where(['bill_date'=> date('Y-m-d')])->get('sma_bill_log')->row();
       $billlog =  $this->db->select('*')->order_by('id','DESC')->get('sma_bill_log')->row();
      
      if($this->db->affected_rows()){
           $billNo =   $billlog->bill_no +1;
           $this->db->where(['id'=>$billlog->id])->update('sma_bill_log',['bill_no'=>$billNo]);
           return $billNo;
       }else{
           $billNo = 1;
           $this->db->insert('sma_bill_log',[
               'bill_no' => $billNo,
               'bill_date'  => date('Y-m-d')
           ]);
           return $billNo;
       }
       
    }



    /**
     * 
     * @param type $tableId
     * @param type $data
     * @return type
     * Table Seats Upated
     */
    public function tableSeats($tableId, $data){
        $this->db->where(['id'=> $tableId])->update('restaurant_tables', $data);
        return (($this->db->affected_rows())?TRUE :FALSE);
    }



    /**
     * 
     * @return boolean
     */
    public function depositCal(){
       $customer =  $this->db->where(['group_name'=> 'customer'])->get('sma_companies')->result();
            
        if($this->db->affected_rows()){
            
            foreach($customer as $item){
                $this->checkOPCL('CL', $item->id,$item->deposit_amount);
                $this->checkOPCL('OP', $item->id,$item->deposit_amount);
             
            }
        }
        
        return false;
    }
    
    /**
     * 
     * @param type $type
     * @param type $customerId
     * @param type $amount
     * @return boolean
     */
    public function checkOPCL($type, $customerId,$amount){
     
        switch($type){
            case 'OP':
                   $log = $this->db->where(['date'=> date('Y-m-d'),'customer_id' => $customerId ])->get('customer_deposit_opening_balance')->row();
                    if($this->db->affected_rows()){
                        return TRUE;
                    }else{
                        $feild = [
                            'date' => date('Y-m-d'),
                            'customer_id' => $customerId,
                            'opening_balance' => $amount,
                        ];
                        $this->db->insert('customer_deposit_opening_balance', $feild);
                        return TRUE;
                        
                    }
                break;
            
            case 'CL':
                   $log =  $this->db->where(['date'=> date('Y-m-d', strtotime('-1 day')),'customer_id' => $customerId ])->get('customer_deposit_opening_balance')->row();
                    
                   if($this->db->affected_rows()){
                       $this->db->where(['id'=> $log->id])->update('customer_deposit_opening_balance',['closing_balance'=>$amount ]);
                       return TRUE;
                   }else{
                       $feild = [
                            'date' => date('Y-m-d', strtotime('-1 day')),
                            'customer_id' => $customerId,
                            'closing_balance' => $amount,
                        ];
                        $this->db->insert('customer_deposit_opening_balance', $feild);
                        return TRUE; 
                   }
                
                break;
            
            
            default:
                    return TRUE;
                
                break;
        }
        
       
    }
     public function dueAmount($tableId){
    
        $this->db->select_sum('grand_total', 'grand_total');
        $this->db->from('sma_sales');
        // $this->db->where('payment_status IN ("due", "partial")', null, false); // Using false to avoid escaping the IN clause
        $this->db->where('payment_status IN ("due")', null, false); // Using false to avoid escaping the IN clause
        $this->db->where('customer_id', $tableId);
        
        $q = $this->db->get();
        
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {  
                // $data[$row->id] = $row;
             }
             return $row;
         }
         return FALSE; 
        
    }
    public function partialAmount($tableId){
       
        $this->db->select_sum('grand_total', 'grand_total');
        $this->db->select_sum('paid', 'paid');
        $this->db->from('sma_sales');
        $this->db->where_in('payment_status', ['partial']);
        $this->db->where('customer_id', $tableId);

        $q = $this->db->get();

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $AmountDue= $this->dueAmount($tableId);
                $totalPartialAmount =($row->grand_total)-($row->paid) ;
                $total =($AmountDue->grand_total)+($totalPartialAmount) ;
                $data[$row->id] = $total;
             }
             return $data;
         }
         return FALSE; 
        
    }
   // Return 
   public function getPreviousPosSale() {
        $this->db->select('*');
        $this->db->from('sales');
        $this->db->where('sale_status', 'exchange');
        $this->db->where('pos', 1); 
        $this->db->order_by('id', 'DESC'); 
        $q = $this->db->get();
        
        if ($q->num_rows() > 0) {
            return $q->row(); 
        }
        return FALSE;
    }
    //Counter 
    public function getOpenBillByIDs($ids) {
        $results = [];
        $data = [];
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $q = $this->db->get_where('suspended_bills', array('id' => $id), 1);
                if ($q->num_rows() > 0) { 
                    $results[] = $q->row(); 
                }
            }
            if (!empty($results)) { 
                foreach ($results as $row) {
                    $data[] = $row; 
                }
                return $data; 
            }
        }else {
            $q = $this->db->get_where('suspended_bills', array('id' => $ids), 1);
        }
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getkottokan($id=NULL){
       
        $sids =   $this->getOpenBillByIDs($id);
      
        if (is_array($sids)) {      
            $id = []; // Use plural for clarity
            foreach ($sids as $sid) {
                $id[] = $sid->id; // Append each ID to the array
                $kots = $this->db->select('kot_tokan')
                ->where_in('id', $ids)
                ->get('sma_suspended_bills')
                ->result();
                $kot_array = [];
                foreach ($kots as $kot) {
                $kot_array = $kot->kot_tokan;
                }
                return $kot_array;

            }
        }elseif (!is_array($sids)) {
            $kot= $this->db->select('kot_tokan')->where('id',$sids->id)->get('sma_suspended_bills')->row();
            return $kot->kot_tokan;
        }
        else {
            $kot= $this->db->select('kot_tokan')->where('id',$id)->get('sma_suspended_bills')->row();
            return $kot->kot_tokan;
        }
       
        // $kot= $this->db->select('kot_tokan')->where('id',$id)->get('sma_suspended_bills')->row();
        // return $kot->kot_tokan;
    }
	
    public function checkCustomerdetails($Name){
        $this->db->select('phone');
        $this->db->where('name', $Name);
        $this->db->order_by('id', 'DESC'); 
        $this->db->limit(1);
        $query = $this->db->get('companies');
        // Check if the name exists
     
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $phone_no = $row->phone;
            if ($phone_no) {
                if ($Name == 'Walk in Customer') {
                  return (string)((int)$phone_no + 1);
                }else {
                    return null;
                }
            } else {
                return '9999';
            }
        } else {
            return null;
        }
    }
    public function checkCustomerid($Name){
        $this->db->select('id');
        $this->db->where('name', $Name);
        $this->db->order_by('id', 'DESC'); 
        $this->db->limit(1);
        $query = $this->db->get('companies');
        // Check if the name exists
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $id = $row->id;
            return $id;
        } else {
            return null;
        }
    }
    public function getCustomerNameById($id){
        $this->db->select('name');
        $this->db->where('id', $id);
        $this->db->order_by('id', 'DESC'); 
        $this->db->limit(1);
        $query = $this->db->get('companies');
       
        // Check if the name exists
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $name = $row->name;
           
            return $name;
        } else {
            return null;
        }
    }
    public function get_single_suspended_bill($customer_id) {
        $user_id = $this->session->userdata('user_id');
        if (isset($customer_id) && !empty($customer_id)) {
            $this->db->select('*');
            $this->db->from('suspended_bills');
            $this->db->where('customer_id', $customer_id); 
            $this->db->where('created_by', $user_id); 
            $this->db->order_by('id', 'DESC');  // Add this line to order by 'created_at' in descending order
            $q = $this->db->get();
            
            if ($q->num_rows() > 0) {
                return $q->row(); 
            } else {
                return null; 
            }
        } else {
            return null; 
        }
    }
    
    
    public function getSuspendBillWithDetails($id) {
       
        $this->db->select('suspended_bills.*, suspended_items.*, companies.*');
        $this->db->from('suspended_bills');
        $this->db->join('suspended_items', 'suspended_bills.id = suspended_items.suspend_id', 'left');
        $this->db->join('product_variants', 'product_variants.id = suspended_items.product_id', 'left');
        $this->db->join('companies', 'suspended_bills.customer_id = companies.id', 'left');
        $this->db->where('suspended_bills.id', $id);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->result(); 
        }
        return FALSE;
    }
    public function getSuspendBillWithDetail($id) {
       
        $this->db->select('suspended_bills.*, suspended_items.*, companies.*');
        $this->db->from('suspended_bills');
        $this->db->join('suspended_items', 'suspended_bills.id = suspended_items.suspend_id', 'left');
        $this->db->join('companies', 'suspended_bills.customer_id = companies.id', 'left');
        $this->db->where('suspended_bills.id', $id);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->row(); 
        }
        return FALSE;
    }
    public function getTaxByCode($id) {
        $q = $this->db->get_where('tax_rates', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    // Counter 
    public function getOpenBillByRefNo($id) {
       
        $this->db->where('reference_no', $id);
        $q = $this->db->get('suspended_bills');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row;
            }
            return $data;
        }
    }
}
