<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Eshop extends CI_Controller {

    private $APIKEY = "435DSFSDFDSF743500909809DFSFJKJ234324534";
    private $authToken = "32468723PWERWE234324SADA";
    public $Settings = '';

    public function __construct() {
        ini_set('magic_quotes_gpc', 0);
        parent::__construct();
        $this->load->model('pos_model');
        $this->load->model('eshop_model');
        $this->load->model('sales_model');
        $this->load->model('companies_model');
        $this->load->library('form_validation');
        $this->Settings = $this->site->get_setting();
    }

    private function validate_error_parsing() {
        $validator = &_get_validation_object();
        $val_error = $validator->error_array();
        $str = '';
        if (is_array($val_error)):
            foreach ($val_error as $key => $value) {
                $str = $str . $value . ',';
            }
        endif;
        $MsgArr['status'] = 'ERROR';
        $MsgArr['msg'] = !empty($str) ? substr($str, 0, -1) : false;
        $MsgArr['msg_arr'] = $val_error;
        return $this->json_op($MsgArr);
    }

    public function barcode($text = NULL, $bcs = 'code128', $height = 50) {
        return site_url('products/gen_barcode/' . $text . '/' . $bcs . '/' . $height);
    }

    public function reciept() {
        $code = $this->input->get('code');
        $res = $this->eshop_model->validateRecieptSales($code);
        if (!$res) {
            
        }
        $sale_id = $id = isset($res[0]['id']) ? $res[0]['id'] : false;
        if (!$sale_id) {
            die('No sale selected.');
        }


        $this->data['rows'] = $this->pos_model->getAllInvoiceItems($sale_id);
        $inv = $this->pos_model->getInvoiceByID($sale_id);
        $biller_id = $inv->biller_id;
        $customer_id = $inv->customer_id;
        $this->data['biller'] = $this->pos_model->getCompanyByID($biller_id);
        $this->data['customer'] = $this->pos_model->getCompanyByID($customer_id);

        $this->data['payments'] = $this->pos_model->getInvoicePayments($sale_id);
        $this->data['pos'] = $this->pos_model->getSetting();
        $this->data['barcode'] = $this->barcode($inv->reference_no, 'code128', 30);
        $this->data['inv'] = $inv;
        $this->data['sid'] = $sale_id;
        $this->data['page_title'] = $this->lang->line("invoice");

        $name = lang("sale") . "_" . str_replace('/', '_', $inv->reference_no) . ".pdf";
        $receipt = $this->load->view('default/views/sales/pdf', $this->data, TRUE);
        if (!$this->Settings->barcode_img) {
            $receipt = preg_replace("'\<\?xml(.*)\?\>'", '', $receipt);
        }
        $receipt = 'html hello';
        $this->sma->generate_pdf($receipt, $name, false);
        exit;
    }

    private function validate_auth_token($ret = NULL) {
        $user_id = $this->input->post('user_id');
        $auth_token = $this->input->post('auth_token');
        $MsgArr['status'] = 'ERROR';
        if (empty($user_id) || empty($auth_token)):
            if (empty($user_id)):
                $MsgArr['msg'] = 'user Id field is  empty ';
            endif;

            if (empty($auth_token)):
                $MsgArr['msg'] = 'Auth token  is missing  ';
            endif;
            return $this->json_op($MsgArr);
        endif;
        $res = $this->eshop_model->validateAuthToken($auth_token, $user_id);
        if (!$res) {
            $MsgArr['msg'] = 'Invalid Auth token  ';
            return $this->json_op($MsgArr);
        }
        if (!empty($ret)):
            $MsgArr['status'] = 'SUCCESS';
            return $this->json_op($MsgArr);
        endif;
    }

    private function ErrorMsg($key) {
        $arr = array();

        //----------------- Common ---------------------//
        $arr['E001'] = "Invalid Api Key";
        $arr['E002'] = "required parmeter  are  not  send";
        $arr['E003'] = "Unable to call api action";

        //----------------- passkey ---------------------//
        $arr['E0022'] = "Unable to notify";
        $arr['E0023'] = "Unable to update POSkey";
        $arr['E0024'] = "Unable to create POSkey";

        return $arr[$key];
    }

    private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }

    public function index() {
        $action = $this->input->get('action');
        $arr = array();
        switch ($action) {

            case 'getShippingMethod':
                $res = $this->shipping_method();
                break;

            case 'getPaymentMethod':
                $res = $this->payment_methods();
                break;

            case 'getOrderTaxMethod':
                $res = $this->order_tax();
                break;

            case 'getOfflineOrderTaxMethod':
                $res = $this->offline_order_tax();
                break;

            case 'getOfflineSales':
                $res = $this->offline_sales();
                break;

            case 'getTaxMethods':
            case 'getTaxRates':
                $res = $this->getTaxMethods();
                break;

            case 'getPages':
                $res = $this->pages();
                break;

            case 'checkout':
                $res = $this->checkout();
                break;

            case 'syncOfflineShop':
                $res = $this->syncOfflineShop();
                break;

            case 'check_pay_status':
                $res = $this->check_pay_status();
                break;

            case 'OrderDetails':
                $res = $this->OrderDetails();
                break;

            case 'OrderDetailsPaykey':
                $res = $this->OrderDetailsByPaykey();
                break;

            case 'UserOrder':
                $res = $this->UserOrder();
                break;

            case 'validateCODOrder':
                $res = $this->validateCODOrder();
                break;

            case 'validateAuth':
                $res = $this->validateAuth();
                break;

            default:
                redirect('shop');
                break;
        }

        return $this->json_op($arr);
    }

    private function validateAuth() {
        $this->validate_auth_token('return');
    }

    private function checkout() {

        $res = $this->pos_model->getSetting();

        $ci = get_instance();
        $config = $ci->config;
        $eshop_url = isset($config->config['eshop_url']) && !empty($config->config['eshop_url']) ? $config->config['eshop_url'] : null;
        if (empty($eshop_url)):
            $result['error'] = 'Eshop not configuerd properly';
            return $this->json_op($result);
        endif;
        $this->validate_auth_token();

        $result = array();
        $result['status'] = 'ERROR';

        /* -------------------------------- Form Validation Start ----------------------------- */
        $this->form_validation->set_rules('user_id', 'User Id', 'numeric|required');
        $this->form_validation->set_rules('billing_name', 'Billing Name', 'required|trim');
        $this->form_validation->set_rules('billing_email', 'Billing Email', 'required|valid_email');
        $this->form_validation->set_rules('billing_phone', 'Billing Phone', 'required|regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
        $this->form_validation->set_rules('billing_addr1', 'Billing Address', 'required|trim');
        $this->form_validation->set_rules('billing_city', 'Billing City', 'required|trim');
        $this->form_validation->set_rules('billing_state', 'Billing State', 'required|trim');
        $this->form_validation->set_rules('billing_country', 'Billing Country', 'required|trim');
        $this->form_validation->set_rules('billing_zipcode', 'Billing Zipcode', 'trim|numeric|min_length[6]|max_length[6]');

        $this->form_validation->set_rules('shipping_name', 'Shipping Name', 'required|trim');
        $this->form_validation->set_rules('shipping_email', 'Shipping Email', 'required|valid_email');
        $this->form_validation->set_rules('shipping_phone', 'Shipping Phone', 'required|regex_match[/^[0-9]{10}$/]', 'Invalid Phone number');
        $this->form_validation->set_rules('shipping_addr1', 'Shipping Address', 'required|trim');
        $this->form_validation->set_rules('shipping_city', 'Shipping City', 'required|trim');
        $this->form_validation->set_rules('shipping_state', 'Shipping State', 'required|trim');
        $this->form_validation->set_rules('shipping_country', 'Shipping Country', 'required|trim');
        $this->form_validation->set_rules('shipping_zipcode', 'Shipping Zipcode', 'trim|numeric|min_length[6]|max_length[6]');

        if ($this->form_validation->run() === FALSE) {
            $this->validate_error_parsing();
        }
        /* -------------------------------- Form Validation End  ----------------------------- */

        //----------------- validate login id ------------------// 
        $user_id = $login_id = $this->input->post('user_id');
        if (empty($user_id)):
            $result['msg'] = 'user id is mandetory';
            return $this->json_op($result);
        endif;

        //----------------- validate cart ------------------//
        $cart = $this->input->post('cart');

        if (empty($cart)):
            $result['msg'] = 'cart is empty';
            return $this->json_op($result);
        endif;

        $cart_arr = json_decode($cart, true);
        $cart_items = isset($cart_arr['total_item']) && !empty($cart_arr['total_item']) ? (int) $cart_arr['total_item'] : NULL;
        $tax = isset($cart_arr['tax']) && !empty($cart_arr['tax']) ? $cart_arr['tax'] : 0;
        $shipping_total = isset($cart_arr['shipping_total']) && !empty($cart_arr['shipping_total']) ? $cart_arr['shipping_total'] : 0;
        $item_subtotal = isset($cart_arr['item_subtotal']) && !empty($cart_arr['item_subtotal']) ? $cart_arr['item_subtotal'] : NULL;
        $cart_total2 = isset($cart_arr['cart_total']) && !empty($cart_arr['cart_total']) ? $cart_arr['cart_total'] : NULL;
        $calc_total2 = $tax + $shipping_total + $item_subtotal;

        if (bccomp($cart_total2, $calc_total2) != 0) {
            $result['msg'] = 'Please check  ,cart calculation are mismatch';
            return $this->json_op($result);
        }

        $product_count = isset($cart_arr['products']) && !empty($cart_arr['products']) ? count($cart_arr['products']) : 0;

        if ($cart_items !== $product_count):
            $result['msg'] = 'Please check, cart items are mismatch';
            return $this->json_op($result);
        endif;

        //----------------- Tax Method ------------------//
        if ($tax > 0) {
            $order_tax_id = $this->input->post('order_tax_id');
            if (empty($order_tax_id)):
                $result['msg'] = 'order_tax_id is empty';
                return $this->json_op($result);
            else:
                $taxres = $this->pos_model->getSetting();
                if ($order_tax_id != $taxres->eshop_order_tax):
                    $result['msg'] = 'Order Tax Id Mismatch : ' . $order_tax_id;
                    return $this->json_op($result);
                endif;
            endif;
        }

        //----------------- Payment Method ------------------//
        $payment_method = $this->input->post('payment_method');
        if (empty($payment_method)):
            $result['msg'] = 'Please select payment method';
            return $this->json_op($result);
        else:
            $avi_pay_methods = $this->payment_methods(1);
            if (!array_key_exists($payment_method, $avi_pay_methods)):
                $result['msg'] = 'Invalid payment method';
                return $this->json_op($result);
            endif;
        endif;

        //----------------- shipping Method ------------------//
        $shipping_method = $this->input->post('shipping_method');
        if (empty($shipping_method)):
            $result['msg'] = 'Please select shipping method';
            return $this->json_op($result);
        else:
            $ShippingRes = $this->eshop_model->getShippingMethods(array('is_deleted' => 0, 'is_active' => 1, 'code' => $shipping_method));
            if (empty($ShippingRes)):
                $result['msg'] = 'Invalid Shipping method ';
                return $this->json_op($result);
            else:
                $shipping_method_name = $ShippingRes[0]['name'];
                $shipping_method_id = $ShippingRes[0]['id'];
                $shipping_method_price = $ShippingRes[0]['price'];
            endif;

        endif;

        if (empty($shipping_method_name) || empty($shipping_method_id) || empty($shipping_method_price)):
            $result['msg'] = 'Shipping method credentials are mendetory';
            return $this->json_op($result);
        endif;
        //----------------- Billing Details ------------------//

        $billing_name = $this->input->post('billing_name');
        $billing_phone = $this->input->post('billing_phone');
        $billing_email = $this->input->post('billing_email');
        $billing_addr1 = $this->input->post('billing_addr1');
        $billing_addr2 = $this->input->post('billing_addr2');
        $billing_city = $this->input->post('billing_city');
        $billing_state = $this->input->post('billing_state');
        $billing_country = $this->input->post('billing_country');

        if (empty($billing_name) || empty($billing_phone) || empty($billing_email) || empty($billing_addr1) || empty($billing_city) || empty($billing_state)):
            $billing_error = array();
            empty($billing_name) ? $billing_error[] = 'Billing Name' : '';
            empty($billing_phone) ? $billing_error[] = 'Billing Phone' : '';
            empty($billing_email) ? $billing_error[] = 'Billing Email' : '';
            empty($billing_addr1) ? $billing_error[] = 'Billing Address' : '';
            empty($billing_city) ? $billing_error[] = 'Billing City' : '';
            empty($billing_state) ? $billing_error[] = 'Billing State' : '';
            $result['msg'] = @implode(',', $billing_error);
            return $this->json_op($result);
        endif;

        //----------------- shipping Details ------------------//
        $shipping_name = $this->input->post('shipping_name');
        $shipping_phone = $this->input->post('shipping_phone');
        $shipping_email = $this->input->post('shipping_email');
        $shipping_addr1 = $this->input->post('shipping_addr1');
        $shipping_addr2 = $this->input->post('shipping_addr2');
        $shipping_city = $this->input->post('shipping_city');
        $shipping_state = $this->input->post('shipping_state');
        $shipping_country = $this->input->post('shipping_country');

        if (empty($shipping_name) || empty($shipping_phone) || empty($shipping_email) || empty($shipping_addr1) || empty($shipping_city) || empty($shipping_state)):
            $shipping_error = array();
            empty($shipping_name) ? $shipping_error[] = 'Shipping Name' : '';
            empty($shipping_phone) ? $shipping_error[] = 'Shipping Phone' : '';
            empty($shipping_email) ? $shipping_error[] = 'Shipping Email' : '';
            empty($shipping_addr1) ? $shipping_error[] = 'Shipping Address' : '';
            empty($shipping_city) ? $shipping_error[] = 'Shipping City' : '';
            empty($shipping_state) ? $shipping_error[] = 'Shipping State' : '';
            $result['msg'] = @implode(',', $shipping_error);
            return $this->json_op($result);
        endif;

        $save_info = $this->input->post('save_info');
        $save_info = 1;
        if ($save_info == 1):
            //-------------------------------- Saving Billing Shippimg Info -----------------------------
            $_param = array('billing_name', 'billing_phone', 'billing_email', 'shipping_phone', 'shipping_email', 'billing_addr1', 'billing_addr2', 'billing_city', 'billing_state', 'billing_country', 'billing_zipcode', 'shipping_name', 'shipping_addr1', 'shipping_addr2', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_zipcode');
            $param = array();
            if (is_array($_param)):
                foreach ($_param as $_param_key) {
                    $_param_key_val = $this->input->post($_param_key);
                    if (!empty($_param_key_val)):
                        $param[$_param_key] = $this->input->post($_param_key);
                    endif;
                }
            endif;
            if (count($param) > 0):
                $res_copy = $this->companies_model->set_billing_shiiping_info($user_id, $param);
            endif;
        //-------------------------------- Saving Billing Shippimg Info -----------------------------
        endif;

        $billing_addr = @implode(', ', array($billing_addr1, $billing_addr2, $billing_city, $billing_state));
        $billing_addr = str_replace(', ,', ',', $billing_addr);

        $shipping_addr = @implode(', ', array($shipping_addr1, $shipping_addr2, $shipping_city, $shipping_state));
        $shipping_addr = str_replace(', ,', ',', $shipping_addr);

        $note = $this->input->post('note');

        $order = $e_order = array();
        $order['date'] = date("Y-m-d H:i:s");
        $order['customer_id'] = $login_id;
        $customer_obj = $this->companies_model->getCompanyByID($login_id);
        if (isset($customer_obj->id)):
            $order['customer'] = $customer_obj->name;
        endif;

        $order['biller_id'] = $res->default_biller;
        if ((int) $res->default_biller):
            $biller = $this->companies_model->getCompanyByID($res->default_biller);
            if (isset($biller->id)):
                $order['biller'] = $biller->company;
            endif;
        endif;

        $order['warehouse_id'] = $res->default_eshop_warehouse;
        $order['note'] = $note;
        $order['total'] = $item_subtotal;
        $order['grand_total'] = $cart_total2;
        $order['sale_status'] = ($payment_method == 'cod') ? 'completed' : 'pending';
        $order['payment_status'] = 'due';
        $order['eshop_sale'] = '1';
        $cf1 = $this->input->post('cf1');
        if (!empty($cf1)):
            if ($cf1 != 'undefined'):
                $order['cf1'] = $cf1;
            endif;
        endif;
        $cf2 = $this->input->post('cf2');
        if (!empty($cf2)):
            if ($cf2 != 'undefined'):
                $order['cf2'] = $cf2;
            endif;
        endif;
        //-------------- If tax  applicable -------------//
        if ($tax > 0) {
            $order['order_tax'] = $tax;
            $order['total_tax'] = $tax;
            $order['order_tax_id'] = $order_tax_id;
        }

        //-------------- If Shiipping is  applicable -------------//
        if ($shipping_method_price > 0) {
            $order['shipping'] = $shipping_method_price;
        }

        $e_order['customer_id'] = $login_id;
        $e_order['billing_name'] = $billing_name;
        $e_order['billing_addr'] = $billing_addr;
        $e_order['billing_email'] = $billing_email;
        $e_order['billing_phone'] = $billing_phone;

        $e_order['shipping_name'] = $shipping_name;
        $e_order['shipping_addr'] = $shipping_addr;
        $e_order['shipping_email'] = $shipping_email;
        $e_order['shipping_phone'] = $shipping_phone;
        $e_order['is_cod'] = ($payment_method == 'cod') ? 'YES' : 'NO';
        $e_order['shipping_method_name'] = $shipping_method_name . '(' . $shipping_method_price . ')';
        $OrderId = $this->eshop_model->addSales($order);
        if ($OrderId):
            $e_order['sale_id'] = $OrderId;

            $ref_No = 'ESHOP/ORD/' . $OrderId;
            $res_up = $this->eshop_model->updateSales($OrderId, array('reference_no' => $ref_No));
            $_OrderId = $this->eshop_model->addOrder($e_order);
            if ($res_up):
                foreach ($cart_arr['products'] as $product_id => $_cart_item) {
                    if ($product_id == $_cart_item['product_id']):

                        $warehouse_id = (!isset($_cart_item['warehouse_id']) || empty($_cart_item['warehouse_id'])) ? 1 : $_cart_item['warehouse_id'];

                        $p_arr = array(
                            'sale_id' => $OrderId,
                            'product_id' => $product_id,
                            'product_code' => $_cart_item['product_code'],
                            'product_name' => $_cart_item['product_name'],
                            'product_type' => 'standard',
                            'net_unit_price' => $_cart_item['net_unit_price'],
                            'unit_price' => $_cart_item['net_unit_price'],
                            'warehouse_id' => $warehouse_id,
                            'quantity' => $_cart_item['quantity'],
                            'subtotal' => $_cart_item['subtotal'],
                            'item_tax' => $tax,
                            'tax_rate_id' => 1,
                            'tax' => 0.0,
                            'discount' => 0.0,
                            'item_discount' => 0.0,
                            'real_unit_price' => $_cart_item['net_unit_price'],
                            'product_unit_id' => $_cart_item['product_unit_id'],
                            'product_unit_code' => $_cart_item['product_unit_code'],
                            'unit_quantity' => $_cart_item['unit_quantity']
                        );

                        $product_details = $this->site->getProductByID($product_id);
                        if (isset($product_details->mrp)):
                            $p_arr['mrp'] = $product_details->mrp;
                        endif;

                        $PrdId = $this->eshop_model->addSalesItem($p_arr);
                        if (!$PrdId):
                            $result['msg'] = "Unable to add product items";
                            return $this->json_op($result);
                            break;
                        endif;
                        unset($p_arr);
                    endif;
                }
                //------start Payment Process 

                $_arr = array('x_amount' => $payment['amount'], 'x_invoice_num' => $sale_id, 'x_description' => $payment['reference_no']);
                $_arr['x_amount'] = $cart_total2;
                $_arr['x_invoice_num'] = $OrderId;
                $_arr['x_description'] = $ref_No;
                $_arr['name'] = $billing_name;
                $_arr['email'] = $billing_email;
                $_arr['mobile'] = $billing_phone;
                $_arr['notify_url'] = rtrim($eshop_url, '/') . '/insta_notify.php';

                switch ($payment_method):
                    case 'cod':
                        $result['status'] = 'SUCCESS';
                        $result['msg'] = 'Order placed successfully ';
                        $shop_url = rtrim($eshop_url, '/') . '/';
                        $cod_shop_url = rtrim($eshop_url, '/') . '/cod_notify.php?TID=' . md5('COD' . $ref_No);
                        $result['redirect_url'] = $cod_shop_url;
                        return $this->json_op($result);
                        break;

                    case 'instamojo':
                        $pay_result = $this->eshop_model->instamojoEshop($_arr);
                        if (isset($pay_result['longurl']) && !empty($pay_result['longurl'])):
                            $result['status'] = 'SUCCESS';
                            $result['msg'] = 'Payment process initiated';
                            $result['redirect_url'] = $pay_result['longurl'];
                            return $this->json_op($result);
                        else:
                            $this->sales_model->deleteSale($OrderId);
                            $result['msg'] = $this->instamojo_error($pay_result['error']);
                            return $this->json_op($result);
                        endif;
                        break;
                endswitch;

            endif;
        endif;
        $result['msg'] = 'Unable to process the order';
        return $this->json_op($result);
    }

    private function isSaleReffNoExist($reffNo) {

        if (empty($reffNo))
            return false;

        $result = $this->eshop_model->getSaleByReff($reffNo);

        if ($result->reference_no == $reffNo) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function syncOfflineShop() {

        $ci = get_instance();

        $config = $ci->config;

        $result = array();

        if ($this->authToken !== $this->input->post('auth_token')) {
            $result['ofl_sale']['status'] = 'ERROR';
            $result['ofl_sale']['msg'] = 'Invalid Auth Token.';
            return $this->json_op($result);
        }

        $cart = $this->input->post('sales');

        if (count($cart) == 0):
            $result['ofl_sale']['status'] = 'ERROR';
            $result['ofl_sale']['msg'] = 'Order details is empty';
            return $this->json_op($result);
        endif;

        $cart_data = json_decode($cart, true);


        if (is_array($cart_data)) {

            foreach ($cart_data as $key => $cart_arr) {

                unset($rec);
                unset($saleReff);

                $order_no = $cart_arr['order_no'];

                if (!empty($order_no)) {

                    $user_id = $cart_arr['userid'];
                    $username = $cart_arr['username'];
                    $warehouse = $cart_arr['warehouse'];
                    if (empty($username) || empty($warehouse)) {
                        $ref_No = $this->site->getReferenceNumber('offapp');
                        $warehouse_id = $cart_arr['default_warehouse'];
                    } else {
                        $saleReff[] = substr(strtoupper($username), 0, 5);
                        $saleReff[] = trim(substr(strtoupper($warehouse), 0, 5));
                        $saleReff[] = 'HYBRIDAPP';
                        $saleReff[] = $this->site->getNextReference('offapp');

                        $ref_No = join('/', $saleReff);
                        $ref_No = str_replace(' ', '', $ref_No);
                        $warehouse_id = !empty($cart_arr['warehouse_id']) ? $cart_arr['warehouse_id'] : $this->Settings->offlinepos_warehouse;
                    }//end else

                    $payment_id = $cart_arr['payment_id'];
                    $transaction_type = $cart_arr['transaction_type'];
                    $cart_items = isset($cart_arr['total_item']) && !empty($cart_arr['total_item']) ? (int) $cart_arr['total_item'] : 0;
                    $product_tax = isset($cart_arr['product_tax']) && !empty($cart_arr['product_tax']) ? $cart_arr['product_tax'] : 0;
                    $order_tax_id = isset($cart_arr['order_tax_id']) && !empty($cart_arr['order_tax_id']) ? $cart_arr['order_tax_id'] : 0;
                    $order_tax = isset($cart_arr['order_tax']) && !empty($cart_arr['order_tax']) ? $cart_arr['order_tax'] : 0;
                    $tax = isset($cart_arr['total_tax']) && !empty($cart_arr['total_tax']) ? $cart_arr['total_tax'] : 0;

                    //----------------- Tax Method ------------------//
                    if ($order_tax > 0) {
                        $order_tax_id = $cart_arr['order_tax_id'];
                        if ($order_tax_id == '') {
                            $rec['status'] = 'ERROR';
                            $rec['order_no'] = $order_no;
                            $rec['msg'] = 'Order tax-id is empty';
                            $result['ofl_sale'][] = (array) $rec;
                            continue;
                        }
                    }

                    $product_discount = isset($cart_arr['product_discount']) && !empty($cart_arr['product_discount']) ?
                            $cart_arr['product_discount'] : 0;
                    $order_discount = isset($cart_arr['order_discount']) && !empty($cart_arr['order_discount']) ? $cart_arr['order_discount'] : 0;
                    $order_discount_id = $cart_arr['order_discount_lable'];

                    $shipping_total = $cart_arr['shipping'];

                    $item_subtotal = isset($cart_arr['item_subtotal']) && !empty($cart_arr['item_subtotal']) ? $cart_arr['item_subtotal'] : 0;

                    $total_discount = $order_discount + $product_discount;

                    $total_tax = isset($cart_arr['total_tax']) ? $cart_arr['total_tax'] : ($product_tax + $order_tax);

                    $net_total = $item_subtotal - $total_tax;

                    $grand_total = $this->sma->formatDecimal($cart_arr['grand_total'], 4);

                    $order_total = $this->sma->formatDecimal(($item_subtotal - $order_discount) + $order_tax, 4);
                    $sale_datetime = $cart_arr['sale_datetime'];
                    $note = isset($cart_arr['note']) && !empty($cart_arr['note']) ? $cart_arr['note'] : '';
                    $cheque_no = isset($cart_arr['cheque_no']) && !empty($cart_arr['cheque_no']) ? $cart_arr['cheque_no'] : NULL;

                    if (bccomp($order_total, $grand_total) != 0) {
                        $rec['order_no'] = $order_no;
                        $rec['status'] = 'ERROR';
                        $rec['msg'] = "Please check, Order cost calculation are mismatch ($order_total : $grand_total)";
                        $result['ofl_sale'][] = (array) $rec;

                        continue;
                    }

                    $product_count = isset($cart_arr['products']) && !empty($cart_arr['products']) ? count($cart_arr['products']) : 0;

                    if ($cart_items != $product_count) {

                        $rec['order_no'] = $order_no;
                        $rec['status'] = 'ERROR';
                        $rec['msg'] = 'Please check, Order items quantity are mismatch';

                        $result['ofl_sale'][] = (array) $rec;

                        continue;
                    }

                    //----------------- Payment Method ------------------//

                    $order = $e_order = array();

                    $paid = $cart_arr['amount_paid'];
                    $payment_method = $cart_arr['payment_method'];
                    //$sale_status    =  $cart_arr['sale_status']; 

                    $order['date'] = $cart_arr['sale_datetime'];

                    $order['biller_id'] = !empty($cart_arr['biller_id']) ? $cart_arr['biller_id'] : $this->Settings->offlinepos_biller;
                    $order['biller'] = $cart_arr['biller_name'];
                    $order['cf1'] = $cart_arr['cf1'];
                    $order['cf2'] = $cart_arr['cf2'];
                    $order['total_items'] = $cart_arr['total_item'];

                    $order['warehouse_id'] = $warehouse_id;
                    $order['note'] = $note;
                    $order['total'] = $net_total;
                    $order['grand_total'] = $cart_arr['grand_total'];
                    $order['sale_status'] = $cart_arr['sale_status'];
                    $order['paid'] = $cart_arr['amount_paid'];
                    $order['payment_status'] = strtolower($cart_arr['payment_status']);

                    $order['order_discount_id'] = $order_discount_id;
                    $order['order_discount'] = $order_discount;
                    $order['product_discount'] = $product_discount;
                    $order['total_discount'] = $total_discount;
                    $order['order_tax_id'] = $order_tax_id;
                    $order['product_tax'] = $product_tax;
                    $order['order_tax'] = $order_tax;
                    $order['total_tax'] = $total_tax;

                    $order['offline_sale'] = '1';
                    $order['offline_reference_no'] = $order_no;
                    $order['offline_payment_id'] = $payment_id;
                    $order['offline_transaction_type'] = $transaction_type;

                    $objCustomer = (array) $this->eshop_model->getDefaultCustomerInfo();

                    $order['customer'] = $objCustomer['name'];
                    $order['customer_id'] = $objCustomer['id'];
                    $order['reference_no'] = $ref_No;
                    $order['created_by'] = $user_id;

                    $balance_amt = $cart_arr['grand_total'] - $cart_arr['amount_paid'];

                    $OrderId = $this->eshop_model->addSales($order);
                    $sale_id = $OrderId;
                    if ($OrderId) {

                        $this->site->updateReference('offapp');

                        foreach ($cart_arr['products'] as $key => $_cart_item) {
                            $product_details = $this->site->getProductByID($_cart_item['product_id']);

                            $product_id = $_cart_item['product_id'];
                            $product_code = !empty($_cart_item['product_code']) ? $_cart_item['product_code'] : $product_details->code;
                            $product_name = !empty($_cart_item['product_name']) ? $_cart_item['product_name'] : $product_details->code;
                            $product_type = !empty($_cart_item['type']) ? $_cart_item['type'] : $product_details->type;
                            $tax_method = !empty($_cart_item['tax_method']) ? $_cart_item['tax_method'] : $product_details->tax_method;
                            $hsn_code = !empty($_cart_item['hsn_code']) ? $_cart_item['hsn_code'] : $product_details->hsn_code;
                            $mrp = $product_details->mrp;

                            $item_tax_attr = $_cart_item['item_tax_attr'];

                            $option_id = $_cart_item['option_id'] ? $_cart_item['option_id'] : NULL;
                            $real_unit_price = $_cart_item['real_unit_price'] ? $_cart_item['real_unit_price'] : $product_details->price;
                            $discount = $_cart_item['discount'];
                            $unit_discount = $_cart_item['unit_discount'] ? $_cart_item['unit_discount'] : number_format($_cart_item['item_discount'] / $_cart_item['quantity'], 4);
                            $item_discount = $_cart_item['item_discount'];

                            $tax_rate_id = $_cart_item['tax_rate_id'];
                            $tax = $_cart_item['tax'];
                            $unit_tax = $_cart_item['unit_tax'] ? $_cart_item['unit_tax'] : number_format($_cart_item['item_tax'] / $_cart_item['quantity'], 4);
                            $item_tax = $_cart_item['item_tax'];

                            $net_unit_price = $_cart_item['net_unit_price'];
                            $unit_price = $_cart_item['unit_price'];

                            $invoice_unit_price = $net_unit_price;
                            $invoice_net_unit_price = ($net_unit_price + $unit_discount + $unit_tax);

                            $subtotal = (($net_unit_price + $unit_tax ) * $_cart_item['quantity']);

                            $net_price = ($mrp * $_cart_item['quantity']);

                            $invoice_total_net_unit_price = number_format($invoice_net_unit_price * $_cart_item['quantity'], 4);

                            $p_arr = array(
                                'sale_id' => $sale_id,
                                'product_id' => $product_id,
                                'product_code' => $product_code,
                                'product_name' => $product_name,
                                'product_type' => $product_type,
                                'option_id' => $option_id,
                                'mrp' => $mrp,
                                'real_unit_price' => $real_unit_price,
                                'item_tax' => $item_tax,
                                'tax_method' => $tax_method,
                                'tax_rate_id' => $tax_rate_id,
                                'tax' => $tax,
                                'discount' => $discount,
                                'item_discount' => $item_discount,
                                'unit_discount' => $unit_discount,
                                'unit_price' => $unit_price,
                                'net_unit_price' => $net_unit_price,
                                'unit_tax' => $unit_tax,
                                'invoice_unit_price' => $invoice_unit_price,
                                'invoice_net_unit_price' => $invoice_net_unit_price,
                                'net_price' => $net_price,
                                'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                                'warehouse_id' => $warehouse_id,
                                'quantity' => $_cart_item['quantity'],
                                'hsn_code' => $hsn_code,
                                'subtotal' => $subtotal,
                                'product_unit_id' => $product_details->unit,
                                'product_unit_code' => $_cart_item['product_unit_code'],
                                'cf1' => $_cart_item['cf1'],
                                'cf2' => $_cart_item['cf2'],
                                'unit_quantity' => $_cart_item['quantity'],
                                'delivery_status' => 'pending',
                                'pending_quantity' => $_cart_item['quantity'],
                            );
                            $items[] = $p_arr;
                            $PrdId = $this->eshop_model->addSalesItem($p_arr);
                            $sale_item_id = $PrdId;

                            if ($warehouse_id && $_cart_item['quantity'] && $product_id) {
                                $this->eshop_model->update_warehouse_stocks($product_id, $_cart_item['quantity'], $warehouse_id);
                                $this->eshop_model->update_products_stocks($product_id, $_cart_item['quantity']);
                            }

                            if ($cart_arr['sale_status'] == 'completed') {

                                $item_costs = $this->site->item_costing($p_arr);
                                foreach ($item_costs as $item_cost) {
                                    if (isset($item_cost['date'])) {
                                        $item_cost['sale_item_id'] = $sale_item_id;
                                        $item_cost['sale_id'] = $sale_id;
                                        if (!isset($item_cost['pi_overselling'])) {
                                            $this->db->insert('costing', $item_cost);
                                        }
                                    } else {
                                        foreach ($item_cost as $ic) {
                                            if (is_array($ic)):
                                                $ic['sale_item_id'] = $sale_item_id;
                                                $ic['sale_id'] = $sale_id;
                                                if (!isset($ic['pi_overselling'])) {
                                                    $this->db->insert('costing', $ic);
                                                }
                                            endif;
                                        }
                                    }
                                }
                            }

                            if ($sale_item_id) {

                                //Add Sales Items Tax Attributes. 
                                $taxAttr['sale_id'] = $sale_id;
                                $taxAttr['item_id'] = $sale_item_id;

                                foreach ($item_tax_attr as $key => $itax_attr) {

                                    $taxAttr['attr_code'] = $itax_attr['attr_code'];
                                    $taxAttr['attr_name'] = $itax_attr['attr_name'];
                                    $taxAttr['attr_per'] = $itax_attr['attr_per'];
                                    $taxAttr['tax_amount'] = $itax_attr['tax_amount'];

                                    $taxAttrId = $this->eshop_model->addSalesItemTaxAttr($taxAttr);

                                    if (!$taxAttrId) {
                                        $rec['order_no'] = $order_no;
                                        $rec['status'] = 'ERROR';
                                        $rec['sale_id'] = $sale_id;
                                        $rec['item_id'] = $sale_item_id;
                                        $rec['attr_code'] = $itax_attr['attr_code'];
                                        $rec['msg'] = "Unable to add sales items tax attributes";

                                        $result['ofl_sale'][] = (array) $rec;
                                    }//end if.
                                }//end foreach.                            
                            } else {
                                $rec['order_no'] = $order_no;
                                $rec['status'] = 'ERROR';
                                $rec['product']['id'] = $_cart_item['product_id'];
                                $rec['product']['name'] = $_cart_item['product_name'];
                                $rec['msg'] = "Unable to add sale items";

                                $result['ofl_sale'][] = (array) $rec;
                            }

                            unset($p_arr);
                        }//end foreach.
                        //start Payment Process 
                        $payment_id = $cart_arr['payment_id'];

                        $paid_by = $cheque_no = $transaction_id = $cc_no = '';

                        $paid_by = $transaction_type;

                        $addPayments = true;

                        switch ($transaction_type) {

                            case 'cheque':
                                $cheque_no = $payment_id;
                                break;

                            case 'Credit Card':
                            case 'CC':
                                $transaction_id = $payment_id;
                                $paid_by = 'CC';
                                break;

                            case 'Debit Card':
                            case 'DC':
                                $transaction_id = $payment_id;
                                $paid_by = 'DC';
                                break;

                            case 'Due Payment':
                            case 'due_payment':
                                $addPayments = false;
                                $transaction_id = "";
                                break;

                            case 'Cash':
                            case 'cash':
                                $transaction_id = "";
                                break;

                            default:
                                $addPayments = false;
                                $transaction_id = $payment_id;
                                break;
                        }//end switch

                        if ($addPayments == true) {

                            $payment_referenc = $this->site->getReferenceNumber('pay');

                            $payment = array(
                                'date' => $sale_datetime,
                                'sale_id' => $sale_id,
                                'reference_no' => $payment_referenc,
                                'amount' => $paid,
                                'pos_balance' => $balance_amt,
                                'paid_by' => $transaction_type,
                                'transaction_id' => $transaction_id,
                                'cheque_no' => $cheque_no,
                                'note' => $note,
                                'created_by' => $user_id,
                                'type' => 'received',
                            );

                            $paymentRes = $this->sales_model->addOfflinePayment($payment);

                            if ($paymentRes == TRUE) {

                                $rec['pay_ref'] = $payment_referenc;
                            }
                        } else {
                            $rec['pay_ref'] = '';
                        }//end else

                        $rec['status'] = 'SUCCESS';
                        $rec['order_no'] = $order_no;
                        $rec['sale_id'] = $sale_id;
                        $rec['sale_ref'] = $ref_No;
                        $rec['msg'] = 'Order saved successfully';

                        $result['ofl_sale'][] = (array) $rec;
                    }//End if.
                }//End else.  
            }//End foreach.
        } else {
            $result['ofl_sale']['status'] = 'ERROR';
            $result['ofl_sale']['msg'] = 'Unable to process the order';
        }

        $data = (array) $result;

        //  json_encode($data, true);

        return $this->json_op($data);
    }

    private function getTaxMethods() {

        $result['gst_attributes'] = $this->pos_model->getTaxAttributes();

        $result['tax_methods'] = $this->pos_model->getAllTaxRates();

        if (is_array($result)) {
            $result['status'] = 'SUCCESS';
        } else {
            $result['status'] = 'ERROR';
        }

        return $this->json_op($result);
    }

    private function order_tax() {

        $result = array();
        $result['status'] = 'SUCCESS';
        $res = $this->pos_model->getSetting();
        if (!$res) {
            return false;
        }
        $this->validate_auth_token();

        $OrderTax = (array) $this->site->getTaxRateByID($res->eshop_order_tax);
        if (!$OrderTax['id']):
            $result['msg'] = 'No Tax applied';
            $result['counter'] = 0;
            return $this->json_op($result);
        else:
            $result['msg'] = $OrderTax['name'] . ' tax applied on  order';
            $result['counter'] = 1;
            $result['result'] = $OrderTax;
            return $this->json_op($result);
        endif;
        return $this->json_op($result);
    }

    private function offline_order_tax() {

        $result = array();
        $result['status'] = 'SUCCESS';
        $res = $this->pos_model->getSetting();
        if (!$res) {
            return false;
        }

        $OrderTax = (array) $this->site->getTaxRateByID($res->eshop_order_tax);

        if (!$OrderTax['id']):
            $result['msg'] = 'No Tax applied';
            $result['counter'] = 0;
            return $this->json_op($result);
        else:
            $result['msg'] = $OrderTax['name'] . ' tax applied on  order';
            $result['counter'] = 1;
            $result['result'] = $OrderTax;
            return $this->json_op($result);
        endif;
        return $this->json_op($result);
    }

    private function offline_sales() {

        $result['sales'] = $this->sales_model->getOfflineSales();

        return $this->json_op($result);
    }

    private function shipping_method() {

        $result = array();
        $result['status'] = 'ERROR';
        $res = $this->eshop_model->getShippingMethods(array('is_deleted' => 0, 'is_active' => 1));

        if (!is_array($res)):
            $result['msg'] = 'No Shipping Method Avilables';
        else:
            $result['status'] = 'SUCCESS';
            $result['msg'] = count($res) . ' active shipping method found';
            $result['counter'] = count($res);
            $i = 1;

            foreach ($res as $resData) {
                $result['result'][$i] = $resData;
                $i++;
            }

        endif;
        return $this->json_op($result);
    }

    private function payment_methods($flag = NULL) {

        $res = $this->pos_model->getSetting();
        $_eshop_cod = isset($res->eshop_cod) && !empty($res->eshop_cod) ? $res->eshop_cod : NUll;
        $_default_eshop_pay = isset($res->default_eshop_pay) && !empty($res->default_eshop_pay) ? $res->default_eshop_pay : NUll;

        $_instamozo = isset($res->instamojo) && !empty($res->instamojo) ? $res->instamojo : NUll;
        $_ccavenue = isset($res->ccavenue) && !empty($res->ccavenue) ? $res->ccavenue : NUll;
        $result = $payment_list = array();
        if ($_eshop_cod):
            $payment_list['cod'] = 'COD';
        endif;
        switch ($_default_eshop_pay) {
            case 'instamojo':
                if ($_instamozo):
                    $payment_list['instamojo'] = 'Credit Card / Debit Card / Netbanking';
                endif;
                break;

            case 'ccavenue':
                if ($_ccavenue):
                    $payment_list['ccavenue'] = 'CCavenue';
                endif;
                break;

            default:

                break;
        }
        if ($flag == 1) {
            return $payment_list;
        }
        if (count($payment_list) == 0):
            $result['status'] = 'ERROR';
            $result['msg'] = 'No active payment method found';
        else :
            $result['status'] = 'SUCCESS';
            $result['msg'] = count($payment_list) . ' active payment method found';
            $result['counter'] = count($payment_list);
            $i = 1;

            foreach ($payment_list as $payment_key => $payment_name) {
                $result['result'][$i]['id'] = $i;
                $result['result'][$i]['code'] = $payment_key;
                $result['result'][$i]['name'] = $payment_name;
                $i++;
            }
        endif;
        return $this->json_op($result);
    }

    private function sampleCart() {
        $cart_product = array();
        $product_id = 12;
        $product_code = '232';
        $product_name = 'sugar';
        $net_unit_price = '01.05';
        $quantity = 3;
        $subtotal = $net_unit_price * $quantity;
        $product_unit_id = 2;
        $product_unit_code = 'kg';
        $unit_quantity = 1;

        $cart_product[$product_id]['product_id'] = $product_id; // product_id
        $cart_product[$product_id]['product_code'] = $product_code; // product_code
        $cart_product[$product_id]['product_name'] = $product_name; // product_name
        $cart_product[$product_id]['net_unit_price'] = $net_unit_price; //  net_unit_price
        $cart_product[$product_id]['quantity'] = $quantity; //  quantity
        $cart_product[$product_id]['subtotal'] = $subtotal; // subtotal
        $cart_product[$product_id]['product_unit_id'] = $product_unit_id; // unit_id
        $cart_product[$product_id]['product_unit_code'] = $product_unit_code; //unit code e.g kg 
        $cart_product[$product_id]['unit_quantity'] = $unit_quantity; // unit qty if 3kg then value 3

        $product_id1 = 145;
        $product_code1 = '232';
        $product_name1 = 'milk';
        $net_unit_price1 = '02.15';
        $quantity1 = 3;
        $subtotal1 = $net_unit_price1 * $quantity1;
        $product_unit_id1 = 3;
        $product_unit_code1 = 'lt';
        $unit_quantity1 = 1;

        $cart_product[$product_id1]['product_id'] = $product_id1; // product_id
        $cart_product[$product_id1]['product_code'] = $product_code1; // product_code
        $cart_product[$product_id1]['product_name'] = $product_name1; // product_name
        $cart_product[$product_id1]['net_unit_price'] = $net_unit_price1; //  net_unit_price
        $cart_product[$product_id1]['quantity'] = $quantity1; //  quantity
        $cart_product[$product_id1]['subtotal'] = $subtotal1; // subtotal
        $cart_product[$product_id1]['product_unit_id'] = $product_unit_id1; // unit_id
        $cart_product[$product_id1]['product_unit_code'] = $product_unit_code1; //unit code e.g kg 
        $cart_product[$product_id1]['unit_quantity'] = $unit_quantity1; // unit qty if 3kg then value 3

        $cart = array();
        $cart['total_item'] = 2;
        $cart['tax'] = 1.92;
        $cart['shipping_total'] = 0.00;
        $cart['item_subtotal'] = $subtotal + $subtotal1;
        $cart['cart_total'] = $cart['tax'] + $cart['shipping_total'] + $cart['item_subtotal'];
        $cart['products'] = $cart_product;

        return json_encode($cart);
    }

    private function check_pay_status() {
        $payment_request_id = $this->input->get('payment_request_id');
        $payment_id = $this->input->get('payment_id');

        if (empty($payment_request_id) || empty($payment_id)):
            $result['error'] = 'Error in payment process';
            return $this->json_op($result);
        endif;

        $this->load->library('instamojo');
        $Transaction = $this->eshop_model->getInstamojoEshopTransaction(array('request_id' => $payment_request_id));
        $sid = $Transaction->order_id;
        $res12 = $this->eshop_model->updateInstamojoEshopTransaction($payment_request_id, array('payment_id' => $payment_id));
        $ci = get_instance();
        $ci->config->load('payment_gateways', TRUE);
        $payment_config = $ci->config->item('payment_gateways');
        $instamojo_credential = $payment_config['instamojo'];


        try {
            $api = new Instamojo($instamojo_credential['API_KEY'], $instamojo_credential['AUTH_TOKEN'], $instamojo_credential['API_URL']);
            $paymentDetail = $api->paymentDetail($payment_id);
            if (is_array($paymentDetail)):
                $pay_res = serialize($paymentDetail);
                $this->eshop_model->updateInstamojoEshopTransaction($payment_request_id, array('success_response' => $pay_res));
                if (isset($paymentDetail["status"]) && in_array($paymentDetail["status"], array('Credit', 'credit', 'Completed'))):
                    $res = $this->eshop_model->instomojoEshopAfterSale($paymentDetail, $sid);
                    if ($res):
                        $result['success'] = 'Payment done successfully';
                        return $this->json_op($result);
                    endif;
                endif;
                $result['error'] = 'Payment process under review';
                return $this->json_op($result);
            endif;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            return $this->json_op($result);
        }


        $result['error'] = 'Payment process under review';
        return $this->json_op($result);
    }

    public function validateCODOrder() {
        $TransKey = $this->input->post('transaction_key');
        $UserId = $this->input->post('user_id');
        if (empty($TransKey) || empty($UserId)) {
            if (empty($TransKey)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'TransKey is  empty';
                return $this->json_op($result);
            }
            if (empty($UserId)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'UserID is  empty';
                return $this->json_op($result);
            }
        }
        $OrderDeatil = $this->eshop_model->validateCODSales($TransKey, $UserId);
        $array = array();
        $array['status'] = 'ERROR';
        if (is_array($OrderDeatil) && count($OrderDeatil) > 0):
            $validOrder = $OrderDeatil[0]['id'];
            $array = array();
            $array['status'] = 'SUCCESS';
            //--------------Order Details --------------------// 
            $order_details = $this->site->getSaleByID($validOrder);
            $array['result']['order'] = (array) $order_details;
            //--------------Payments Details --------------------//
            $pay_details = $this->sales_model->getInvoicePayments($validOrder);
            $array['result']['payment'] = $pay_details[0];

            //-------------- Shipping -------------//
            $deli = $this->sales_model->getDeliveryByID($id);
            $array['result']['delivery'] = $deli;

            //--------------billing_shipping Details --------------------//
            $billing_details = $this->eshop_model->getOrderDetails(array('sale_id' => $validOrder));
            ;
            $array['result']['billing_shipping'] = $billing_details[0];

            //--------------Payments Details --------------------//
            $items_details = $this->sales_model->getAllInvoiceItems($validOrder);
            $items_details = (array) $items_details;
            $array['result']['items_count'] = count($items_details);
            $i = 1;
            foreach ($items_details as $item_details) {
                $array['result']['items'][$i] = $item_details;
                $i++;
            }
            return $this->json_op($array);
        endif;
        $array['msg'] = 'Invalid Order';
        return $this->json_op($array);
        return false;
    }

    public function OrderDetails() {

        $this->validate_auth_token();

        $TransKey = $this->input->post('transaction_key');
        $UserId = $this->input->post('user_id');
        $result = array();
        if (empty($TransKey) || empty($UserId)) {
            if (empty($TransKey)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'TransKey is  empty';
                return $this->json_op($result);
            }
            if (empty($UserId)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'UserID is  empty';
                return $this->json_op($result);
            }
        }
        $OrderDeatil = $this->eshop_model->validateSales(NULL, $UserId, $TransKey);
        $array = array();
        $array['status'] = 'ERROR';
        if (is_array($OrderDeatil) && count($OrderDeatil) > 0):
            $validOrder = $OrderDeatil[0]['id'];
            $array = array();
            $array['status'] = 'SUCCCESS';
            //--------------Order Details --------------------// 
            $order_details = $this->site->getSaleByID($validOrder);
            $array['result']['order'] = (array) $order_details;

            //--------------Payments Details --------------------//
            $pay_details = $this->sales_model->getInvoicePayments($validOrder);
            $array['result']['payment'] = $pay_details[0];


            //-------------- Shipping -------------//
            $deli = $this->sales_model->getDeliveryByID($id);
            $array['result']['delivery'] = $deli;

            //--------------billing_shipping Details --------------------//
            $billing_details = $this->eshop_model->getOrderDetails(array('sale_id' => $validOrder));
            ;
            $array['result']['billing_shipping'] = $billing_details[0];

            //--------------Item Details --------------------//
            $items_details = $this->sales_model->getAllInvoiceItems($validOrder);
            $items_details = (array) $items_details;
            $array['result']['items_count'] = count($items_details);
            $i = 1;
            foreach ($items_details as $item_details) {
                $array['result']['items'][$i] = $item_details;
                $i++;
            }

            return $this->json_op($array);
        endif;
        $array['msg'] = 'Invalid Order';
        return $this->json_op($array);
        return false;
    }

    public function UserOrder() {
        $array = array();
        $sort_array = array('sales_reference_no', 'sales_date', 'sales_payment_status');
        $array['status'] = 'SUCCESS';
        $UserId = $this->input->post('user_id');
        if (empty($UserId)):
            $array['status'] = 'ERROR';
            $array['msg'] = 'UserID is  empty';
            return $this->json_op($array);
        endif;
        $limit = $this->input->post('limit');
        $offset = $this->input->post('offset');
        $sort_field = $this->input->post('sort_field');
        $sort_field = !empty($sort_field) && in_array($sort_field, $sort_array) ? $sort_field : 'sales_id';
        switch ($sort_field) {
            case 'sales_reference_no':
                $sort_field = 'sales.reference_no';
                break;
            case 'sales_date':
                $sort_field = 'sales.date';
                break;
            case 'sales_payment_status':
                $sort_field = 'sales.payment_status';
                break;
            case 'sales_id':
                $sort_field = 'sales.id';
                break;
        }
        $sort_dir = $this->input->post('sort_dir');
        $sort_dir = ($sort_dir == 'asc') ? 'ASC' : 'DESC';


        $param['user_id'] = $UserId;
        $param['limit'] = $limit;
        $param['offset'] = $offset;
        $param['sort_field'] = $sort_field;
        $param['sort_dir'] = $sort_dir;

        $OrderDeatil = $this->eshop_model->getAllSalesByUser($param);
        if (is_array($OrderDeatil) && count($OrderDeatil) > 0):
            $array['msg'] = count($OrderDeatil) . '  recored found';
            $array['counter'] = count($OrderDeatil);
            $array['result'] = $OrderDeatil;
            return $this->json_op($array);
        endif;
        $array['msg'] = 'No recored found';
        $array['result'] = '';
        return $this->json_op($array);
    }

    public function instamojo_error($str) {
        $arr = json_decode($str, true);
        $res_str = '';
        foreach ($arr as $key => $val) {
            $res_str = $res_str . $key . ':' . implode('', $val);
        }
        return $res_str;
    }

    private function pages() {
        $result = array();
        $result['status'] = 'ERROR';
        $res = $this->eshop_model->getStaticPages(array('id' => 1));
        if (!$res->id) {
            $result['msg'] = 'Pages not found';
            return $this->json_op($result);
        }
        $result['status'] = 'SUCCESS';
        $result['msg'] = 'Pages  found';
        $result['result'] = $res;
        return $this->json_op($result);
    }

    public function OrderDetailsByPaykey() {
        $TransKey = $this->input->post('transaction_key');
        $UserId = $this->input->post('user_id');
        $result = array();
        if (empty($TransKey) || empty($UserId)) {
            if (empty($TransKey)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'TransKey is  empty';
                return $this->json_op($result);
            }
            if (empty($UserId)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'UserID is  empty';
                return $this->json_op($result);
            }
        }
        $OrderDeatil = $this->eshop_model->validateSales($TransKey, $UserId, NULL);
        $array = array();
        $array['status'] = 'ERROR';
        if (is_array($OrderDeatil) && count($OrderDeatil) > 0):
            $validOrder = $OrderDeatil[0]['id'];
            $array = array();
            $array['status'] = 'SUCCCESS';
            //--------------Order Details --------------------// 
            $order_details = $this->site->getSaleByID($validOrder);
            $array['result']['order'] = (array) $order_details;

            //--------------Payments Details --------------------//
            $pay_details = $this->sales_model->getInvoicePayments($validOrder);
            $array['result']['payment'] = $pay_details[0];

            //--------------billing_shipping Details --------------------//
            $billing_details = $this->eshop_model->getOrderDetails(array('sale_id' => $validOrder));
            ;
            $array['result']['billing_shipping'] = $billing_details[0];

            return $this->json_op($array);
        endif;
        $array['msg'] = 'Invalid Order';
        return $this->json_op($array);
        return false;
    }

    public function new_orders() {

        $result = $this->eshop_model->count_new_sales();
        if (is_array($result)) {
            echo json_encode($result);
        } else {
            echo json_encode(['num' => 0]);
        }
    }

    public function new_orders_alert() {

        echo $this->eshop_model->set_eshop_order_status(1);
    }

    public function new_eshop_orders() {

        $result = $this->eshop_model->count_new_eshop_order();
        if (is_array($result)) {
            echo json_encode($result);
        } else {
            echo json_encode(['num' => 0]);
        }
    }

    public function new_eshop_orders_alert() {

        echo $this->eshop_model->set_eshop_order_status_alert(1);
    }

    public function deleteShippingTm($id = NULL) {

        if ($this->eshop_model->deleteShippingTime($id)) {
            return true;
        } else {
            return false;
        }
        return false;
    }

}
