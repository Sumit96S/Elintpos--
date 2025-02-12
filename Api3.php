<?php
defined('BASEPATH') OR exit('No direct script access allowed');

ini_set('memory_limit', '1024M'); // or you could use 1G

class Api3 extends MY_Controller {

    private $api3_private_key = '';
    private $posVersion = '';
    private $offline_pos_version = 4.20;
    private $ci = '';    

    public function __construct() {
        parent::__construct();

        //$this->load->model('auth_model'); 
        $this->load->model('api3_model');
        $this->load->model('companies_model');
        $this->load->model('pos_model');
        $this->load->model('eshop_model');
        $this->load->model('sales_model');
        $this->load->library('form_validation');

        $this->posVersion = json_decode($this->Settings->pos_version);
        $this->api3_private_key = isset($this->Settings->api_privatekey) && !empty($this->Settings->api_privatekey) ? $this->Settings->api_privatekey : NULL;
        
        if((float)$this->offline_pos_version > (float)$this->posVersion){
            $this->offline_pos_version = 4.16;
        }
        
        $this->ci = $ci = get_instance();
        $config = $ci->config;
        $this->merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : NULL;


        if ($this->posVersion->version < 3.00) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 404;
            $data['pos_version'] = $this->posVersion->version;
            $data['current_pos_version'] = $this->posVersion->version;
            $data['api_access_status'] = $this->Settings->api_access ? 'Active' : 'Blocked';
            $data['mag'] = 'API3 required the pos version 3.00 or above.';
            echo $this->json_op($data);
            exit;
        }//end if

        if (!$this->Settings->api_access) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 405;
            $data['current_pos_version'] = $this->posVersion->version;
            $data['pos_version'] = $this->posVersion->version;
            $data['api_access_status'] = $this->Settings->api_access ? 'Active' : 'Blocked';
            $data['mag'] = 'API access is blocked.';
            echo $this->json_op($data);
            exit;
        }//end if

        if (!isset($_POST)) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 101;
            $data['mag'] = 'Invalid api request method';
            $data['private_key_msg'] = 'mismatch';
            echo $this->json_op($data);
            exit;
        } else {

            $privatekey = $this->input->post('privatekey');
            $this->action = $this->input->post('action');

            if ($this->api3_private_key == NULL) {
                $data['status'] = 'ERROR';
                $data['error_code'] = 100;
                $data['mag'] = 'POS API3 private key not available or generated';
                $data['private_key_msg'] = 'mismatch';
                echo $this->json_op($data);
                exit;
            } elseif ($this->api3_private_key !== $privatekey) {
                $data['status'] = 'ERROR';
                $data['error_code'] = 102;
                $data['mag'] = 'Private key mismatch';
                $data['private_key_msg'] = 'mismatch';
                echo $this->json_op($data);
                exit;
            }
        }//end else
    }

    public function index() {

        $this->action = $this->input->post('action');
    }

    private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }

    public function eshop() {

        $action = $this->input->post('action');

        switch ($action) {

            case 'getsettings':
                $data = $this->store_settings();
                echo $this->json_op($data);
                break;

            case 'getcategoryidbyname':
            case 'getcategorynamebyid':
            case 'getallcategories':
            case 'getparentcategories':
            case 'getsubcategories':
            case 'getallproducts':
            case 'getproductstocks':

                echo $this->catlog($action);
                break;

            case 'getcustomers':
                $res = $this->getCustomers();

                if (is_array($res)) {
                    $data['status'] = 'SUCCESS';
                    $data['count'] = count($res);

                    foreach ($res as $key => $customer) {
                        unset($customer->pass_key);
                        unset($customer->password);
                        unset($customer->group_id);
                        unset($customer->group_name);
                        unset($customer->lat);
                        unset($customer->lng);
                        unset($customer->email_verification_code);
                        unset($customer->mobile_verification_code);
                        unset($customer->anniversary);
                        unset($customer->dob_father);
                        unset($customer->dob_mother);
                        unset($customer->dob_child1);
                        unset($customer->dob_child2);
                        unset($customer->email_is_verified);
                        unset($customer->mobile_is_verified);
                        unset($customer->is_synced);
                        unset($customer->vat_no);

                        $customersData[] = $customer;
                    }//end foreach
                    $data['customers'] = $customersData;
                }//end if 
                else {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                }
                echo $this->json_op($data);
                break;

            case 'getcustomersales':
                $customer_id = $this->input->post('customer_id');
                $sale_status = $this->input->post('sale_status');
                $sale_type = 'eshop_sale'; //$this->input->post('sale_type');
                $res = $this->getCustomerSales($customer_id, $sale_status, $sale_type);
                if (!$res) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['sales_count'] = count($res);

                    if (is_array($res)) {
                        foreach ($res as $key => $sale) {
                            unset($sale->note);
                            unset($sale->staff_note);
                            unset($sale->created_by);
                            unset($sale->updated_by);
                            unset($sale->updated_at);
                            unset($sale->pos);
                            unset($sale->offline_sale);
                            unset($sale->offline_reference_no);
                            unset($sale->offline_payment_id);
                            unset($sale->offline_transaction_type);

                            $sales[] = $sale;
                        }//end foreach.
                    }
                    $data['sales'] = $sales;
                }
                echo $this->json_op($data);
                break;

            case 'getsales':

                $salesTypes['pos'] = isset($_POST['pos_sales']) ? $this->input->post('pos_sales') : 0;
                $salesTypes['eshop_sale'] = isset($_POST['eshop_sales']) ? $this->input->post('eshop_sales') : 0;
                $salesTypes['offline_sale'] = isset($_POST['offline_sales']) ? $this->input->post('offline_sales') : 0;

                $res = $this->sales_model->getSales($salesTypes);

                if (!$res) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['sales_count'] = count($res);
                    $data['sales'] = $res;
                }
                echo $this->json_op($data);
                break;
            case 'postdata':
                $data['status'] = 'SUCCESS';
                $data['data'] = isset($_POST['data']) ? $this->input->post('data') : 0;

                echo $this->json_op($data);
                break;
            case '':
            default:
                $data['status'] = 'ERROR';
                $data['error_code'] = 103;
                $data['mag'] = 'Invalid request';

                echo $this->json_op($data);
                break;
        }//end switch. 

        exit;
    }

    public function super_admin() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }
        $action = $this->input->post('action');
        if ($action == 'SaleList') {
            $total = $this->api3_model->sale_count_data(isset($_REQUEST['search']) ? $_REQUEST['search'] : "");
            $data = array();
            $Result = $this->api3_model->getSaleList(isset($_REQUEST['start']) ? $_REQUEST['start'] : "", isset($_REQUEST['length']) ? $_REQUEST['length'] : "", isset($_REQUEST['search']) ? $_REQUEST['search'] : "");
            if ($Result->num_rows() > 0) {
                foreach ($Result->result() as $row) {
                    $row_data['id'] = $row->id;
                    $row_data['sale_date'] = $row->sale_date;
                    $row_data['reference_no'] = $row->reference_no;
                    $row_data['biller'] = $row->biller;
                    $row_data['customer'] = $row->customer;
                    $row_data['sale_status'] = $row->sale_status;
                    $row_data['total'] = $row->total;
                    $row_data['paid'] = $row->paid;
                    $row_data['balance'] = $row->balance;
                    $row_data['payment_status'] = $row->payment_status;
                    $data[] = $row_data;
                }
            }
            //$datas = array_map('array_values', $data);
            $DataArray = array('draw' => $_REQUEST['draw'], 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data);
            echo json_encode($DataArray);
        }
        if ($action == 'SaleDetails') {
            $id = $this->input->post('id');
            $inv = $this->sales_model->getInvoiceByID($id);
            $this->data['taxItems'] = $this->sales_model->getAllTaxItemsGroup($id, $inv->return_id);
            $this->data['customer'] = $this->site->getCompanyByID($inv->customer_id);
            $this->data['biller'] = $this->site->getCompanyByID($inv->biller_id);
            $this->data['created_by'] = $this->site->getUser($inv->created_by);
            $this->data['updated_by'] = $inv->updated_by ? $this->site->getUser($inv->updated_by) : null;
            $this->data['warehouse'] = $this->site->getWarehouseByID($inv->warehouse_id);
            $this->data['inv'] = $inv;
            $this->data['rows'] = $this->sales_model->getAllInvoiceItems($id);
            $this->data['payments'] = $this->sales_model->getInvoicePayments($id);
            $this->data['return_sale'] = $inv->return_id ? $this->sales_model->getInvoiceByID($inv->return_id) : NULL;
            $this->data['return_rows'] = $inv->return_id ? $this->sales_model->getAllInvoiceItems($inv->return_id) : NULL;
            //Details
            $r = 1;
            $tax_summary = array();
            $sale_item = array();
            $sale_return_rows = array();
            $ViewPayments = array();
            //print_r($this->data['rows']);
            foreach ($this->data['rows'] as $row):
                $VariantPrice = 0;
                if ($row->option_id != 0)
                    $VariantPrice = $row->variant_price;

                if (isset($tax_summary[$row->tax_code])) {
                    $tax_summary[$row->tax_code]['items'] += $row->quantity;
                    $tax_summary[$row->tax_code]['tax'] += $row->item_tax;
                    $tax_summary[$row->tax_code]['amt'] += ($row->quantity * $row->net_unit_price);
                } else {
                    $tax_summary[$row->tax_code]['items'] = $row->quantity;
                    $tax_summary[$row->tax_code]['tax'] = $row->item_tax;
                    $tax_summary[$row->tax_code]['amt'] = ($row->quantity * $row->net_unit_price);
                    $tax_summary[$row->tax_code]['name'] = $row->tax_name;
                    $tax_summary[$row->tax_code]['code'] = $row->tax_code;
                    $tax_summary[$row->tax_code]['rate'] = $row->tax_rate;
                    $tax_summary[$row->tax_code]['tax_rate_id'] = $row->tax_rate_id;
                }
                $sale_item[] = array(
                    'No' => $r,
                    'Product_Name' => $row->product_code . ' - ' . $row->product_name . ($row->variant ? ' (' . $row->variant . ')' : ''),
                    'Serial_No' => $row->serial_no,
                    'Unit_Price' => $this->sma->formatMoney($row->real_unit_price + $VariantPrice),
                    'Qty' => $this->sma->formatQuantity($row->unit_quantity) . ' ' . $row->product_unit_code,
                    'Net_Price' => $this->sma->formatMoney($row->unit_quantity * ($row->real_unit_price + $VariantPrice)),
                    'Discount' => '',
                    'Tax' => '',
                    'Sub_Total' => $this->sma->formatMoney($row->subtotal),
                );
                $r++;
            endforeach;
            if ($this->data['return_rows']) {
                foreach ($this->data['return_rows'] as $row):
                    $VariantPrice = 0;
                    if ($row->option_id != 0)
                        $VariantPrice = $row->variant_price;
                    if (isset($tax_summary[$row->tax_code])) {
                        $tax_summary[$row->tax_code]['items'] += $row->quantity;
                        $tax_summary[$row->tax_code]['tax'] += $row->item_tax;
                        $tax_summary[$row->tax_code]['amt'] += ($row->quantity * $row->net_unit_price);
                    } else {
                        $tax_summary[$row->tax_code]['items'] = $row->quantity;
                        $tax_summary[$row->tax_code]['tax'] = $row->item_tax;
                        $tax_summary[$row->tax_code]['amt'] = ($row->quantity * $row->net_unit_price);
                        $tax_summary[$row->tax_code]['name'] = $row->tax_name;
                        $tax_summary[$row->tax_code]['code'] = $row->tax_code;
                        $tax_summary[$row->tax_code]['rate'] = $row->tax_rate;
                        $tax_summary[$row->tax_code]['tax_rate_id'] = $row->tax_rate_id;
                    }
                    $sale_return_rows[] = array(
                        'No' => $r,
                        'Product_Name' => $row->product_code . ' - ' . $row->product_name . ($row->variant ? ' (' . $row->variant . ')' : ''),
                        'Serial_No' => $row->serial_no,
                        'Unit_Price' => $this->sma->formatMoney(($row->unit_price + $VariantPrice + $row->item_discount) - $row->item_tax),
                        'Qty' => $this->sma->formatQuantity($row->unit_quantity) . ' ' . $row->product_unit_code,
                        'Net_Price' => $this->sma->formatMoney($row->unit_quantity * ($row->unit_price + $VariantPrice + $row->item_discount - $row->item_tax)),
                        'Discount' => ($row->discount != 0 ? '<small>(' . $row->discount . ')</small> ' : '') . $this->sma->formatMoney($row->item_discount),
                        'Tax' => ($row->item_tax != 0 && $row->tax_code ? '<small>(' . $row->tax_code . ')</small>' : '') . ' ' . $this->sma->formatMoney($row->item_tax),
                        'Sub_Total' => $this->sma->formatMoney($row->subtotal),
                    );
                    $r++;
                endforeach;
            }

            $TaxItemsData = $this->sma->taxInvvoiceTabel($tax_summary, $this->data['taxItems'], $inv, $this->data['return_sale'], $Settings);
            if ($this->data['payments']) {
                foreach ($this->data['payments'] as $payment):
                    $ViewPayments[] = array(
                        'payment_date' => $payment->date,
                        'Reference_No' => $payment->reference_no,
                        'Amount' => $this->sma->formatMoney($payment->amount),
                        'Paid_By' => $payment->paid_by,
                    );
                endforeach;
            }
            $this->data['sale_item'] = $sale_item;
            $this->data['sale_return_rows'] = $sale_return_rows;
            $this->data['sale_return_rows'] = $sale_return_rows;
            $this->data['TaxItemsData'] = $TaxItemsData;
            $this->data['ViewPayments'] = $ViewPayments;
            echo $this->json_op($this->data);
        }
    }

    public function offline() {

        $action = $this->input->post('action');
        $this->offline_pos_version = isset($_POST['offlineposversion']) ? $this->input->post('offlineposversion') : $this->offline_pos_version;
        
        $this->api3_model->setOfflinePosVersion( $this->offline_pos_version );
        
        switch ($action) {

            case 'getsettings':
                $data = $this->store_settings();
                echo $this->json_op($data);
                break;

            case 'getallcategories':
            case 'getparentcategories':
            case 'getsubcategories':
            case 'getallproducts':
            case 'getproductstocks':

                echo $this->catlog($action);
                break;

            case 'getcustomers':
                $data = $this->getCustomers();

                if (is_array($data)) {
                    foreach ($data as $key => $customer) {
                        unset($customer->pass_key);
                        unset($customer->password);
                        unset($customer->group_id);
                        unset($customer->group_name);
                        unset($customer->lat);
                        unset($customer->lng);
                        unset($customer->email_verification_code);
                        unset($customer->mobile_verification_code);
                        unset($customer->anniversary);
                        unset($customer->dob_father);
                        unset($customer->dob_mother);
                        unset($customer->dob_child1);
                        unset($customer->dob_child2);

                        $customersData[] = $customer;
                    }//end foreach
                }//end if                
                echo $this->json_op($customersData);
                break;

            case 'getcustomersales':
                $customer_id = $this->input->post('customer_id');
                $sale_status = $this->input->post('sale_status');
                $sale_type = 'offline_sale';  //$this->input->post('sale_type');
                $res = $this->getCustomerSales($customer_id, $sale_status, $sale_type);
                if (!$res) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['sales_count'] = count($res);
                    $data['sales'] = $res;
                }
                echo $this->json_op($data);
                break;

            case 'getsales':

                $selects = isset($_POST['select']) ? $this->input->post('select') : NULL;

                $salesTypes['pos'] = isset($_POST['pos_sales']) ? $this->input->post('pos_sales') : 0;
                $salesTypes['eshop_sale'] = isset($_POST['eshop_sales']) ? $this->input->post('eshop_sales') : 0;
                $salesTypes['offline_sale'] = isset($_POST['offline_sales']) ? $this->input->post('offline_sales') : 0;

                $res = $this->sales_model->getSales($salesTypes, $selects);

                if (!$res) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['sales_count'] = count($res);
                    $data['sales'] = $res;
                }
                echo $this->json_op($data);
                break;
            //getSaleItemsBySaleIds
            case 'getsaleitemsbysaleids':

                $salesIds = isset($_POST['sale_ids']) && !empty($_POST['sale_ids']) ? $this->input->post('sale_ids') : FALSE;
                $sale_ids = explode(',', $salesIds);
                $res = $this->sales_model->getSaleItems($sale_ids, NULL);

                if (!$res) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['sales_count'] = count($res);
                    $data['sales'] = $res;
                }
                echo $this->json_op($data);

                break;

            //getSaleItemsBySaleType
            case 'getsaleitemsbysaletype':

                $salesTypes['pos'] = isset($_POST['pos_sales']) ? $this->input->post('pos_sales') : 0;
                $salesTypes['eshop_sale'] = isset($_POST['eshop_sales']) ? $this->input->post('eshop_sales') : 0;
                $salesTypes['offline_sale'] = isset($_POST['offline_sales']) ? $this->input->post('offline_sales') : 0;

                $res = $this->sales_model->getSaleItems(NULL, $salesTypes);

                if (!$res) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'No records found';
                    $data['error_code'] = 105;
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['items_count'] = count($res);
                    $data['sale_items'] = $res;
                }

                echo $this->json_op($data);

                break;


            case 'gettabledata':

                if (isset($_POST['tablename']) && !empty($_POST['tablename'])) {
                    
                    $tablename = $this->input->post('tablename');

                    $selects = json_decode($this->input->post('selects'), TRUE);

                    $res = $this->api3_model->getTableData($tablename, $selects);

                    if (isset($res['error_no']) && $res['error_no']) {
                        $data['status'] = 'ERROR';
                        $data['msg'] = $res['error'];
                        $data['error_code'] = 107;
                    } else {
                        $data['status'] = 'SUCCESS';
                        $data['count'] = $res['num'];
                        $data['rows'] = $res['rows'];
                    }

                    echo $this->json_op($data);
                    
                } else {
                    
                    $data['status'] = 'ERROR';
                    $data['msg'] = 'Invalid parameters: tablename is empty';
                    $data['error_code'] = 107;

                    echo $this->json_op($data);
                }

                break;

            case 'synchronization':

                $type = $this->input->post('type');

                $synchtype = $this->input->post('synchtype');

                if ($synchtype == 'upload') {

                    $salesdata = urldecode($this->input->post('updatedata'));

                    // $arr = json_decode($salesdata);

                    switch ($type) {
                        case 'sales':

                            $data = $this->synchOfflineposSales($salesdata);

                            echo $this->json_op($data);

                            break;
                    }//end switch.
                } elseif ($synchtype == 'download') {

                    $data = $this->get_synchronization_data($type);
                        
                    echo $this->json_op($data);
                } elseif ($synchtype == 'update') {

                    $updatedata = urldecode($this->input->post('updatedata'));

                    switch ($type) {
                        case 'settings':

                            $update_data = json_decode($updatedata, TRUE);

                            $data = $this->api3_model->update_settings($update_data);

                            echo $this->json_op($data);

                            break;
                    }//end switch.
                } elseif ($synchtype == 'getdata') {

                    switch ($type) {
                        case 'users':

                            $data['users_data'] = $this->api3_model->get_users();
                            $data['permission_data'] = $this->api3_model->get_users_permissions();

                            echo $this->json_op($data);

                            break;
                    }//end switch.
                }
                break;

            case 'gettaxmetods':

                $data = $this->get_tax_methods();

                echo $this->json_op($data);

                break;

            case 'getpaymentmetods':

                $data = $this->get_payment_methods();

                echo $this->json_op($data);

                break;

            case 'getshippingmetods':

                $data = $this->get_shipping_method();

                echo $this->json_op($data);

                break;

            case '':
            default:
                $data['status'] = 'ERROR';
                $data['error_code'] = 103;
                $data['mag'] = 'Invalid request';
                echo $this->json_op($data);
                break;
        }//end switch. 

        exit;
    }

    public function getCustomers() {
        $this->load->model('companies_model');

        return $data = $this->companies_model->getAllCustomerCompanies();
    }

    public function getCustomerSales($customer_id, $sale_status = '', $sale_type = '') {

        return $data = $this->eshop_model->getCustomerSales(['user_id' => $customer_id, 'sale_status' => $sale_status, 'sale_type' => $sale_type]);
    }

    public function getCustomerAddresses($customer_id) {
        
    }

    public function store_settings() {

        $this->load->model('settings_model');
        $res = $this->eshop_model->getSettings();
        $res2 = $this->eshop_model->getPosSettings();
        $config = $this->ci->config;
        $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : null;
        $res->merchant_phone = $merchant_phone;
        $res->eshop_next_sale_reff = $this->site->getNextReference('eshop');

        if (is_object($res) && is_object($res2)):
            $data = array();
            foreach ($res as $key => $value) {
                $data[$key] = $value;
            }
            foreach ($res2 as $key2 => $value2) {
                $data[$key2] = $value2;
            }

            $MsgArr['status'] = "SUCCESS";

            $MsgArr['setting'] = $data;

            return $MsgArr;
        endif;
    }

    public function catlog($action) {

        $this->load->model('products_model');

        $MsgArr = array();

        switch ($action) {

            case 'getproductstocks':
                $category_id = (isset($_POST['category_id']) && !empty($_POST['category_id'])) ? $this->input->post('category_id') : 0;
                $listbycategory = isset($_POST['listbycategory']) ? $this->input->post('listbycategory') : 0;
                $res = $this->products_model->getWherehousProducts(NULL, $category_id, $listbycategory);
                if ($res) {
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['listbycategory'] = $listbycategory;
                    $MsgArr['count'] = count($res);
                    $MsgArr['data'] = $res;
                } else {
                    $MsgArr['status'] = "ERROR";
                    $MsgArr['msg'] = "No records founds";
                    $MsgArr['error_code'] = 105;
                }
                return $this->json_op($MsgArr);
                break;

            case 'getallproducts':
                $keyword = $this->input->post('keyword');
                $category_id = $this->input->post('category_id');
                $subcategory_id = $this->input->post('subcategory_id');
                $offset = $this->input->post('offset');
                $limit = $this->input->post('limit');
                $param = array('keyword' => $keyword, 'offset' => $offset, 'limit' => $limit, 'category_id' => $category_id, 'subcategory_id' => $subcategory_id);
                $MsgArr['status'] = "ERROR";

                $res = $this->products_model->getAllProductStock($param);

                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['image_path'] = base_url('assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/');
                    $MsgArr['count'] = $this->products_model->products_count_eshop($keyword, $category_id, $subcategory_id);
                    $MsgArr['total_product_count'] = count($res);
                    $MsgArr['tax_method'][] = (object) ['0' => 'inclusive', '1' => 'exclusive'];
                    foreach ($res as $resData) {
                        $MsgArr['items'][] = (object) $resData;
                    }
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['msg'] = "No records founds";
                $MsgArr['error_code'] = 105;
                return $this->json_op($MsgArr);
                break;

            case 'allCategories':
                $keyword = $this->input->post('keyword');
                $param = array('keyword' => $keyword);
                $possettting = $this->pos_model->getSetting();
                $res = $this->products_model->getCategories(NULL, $param);
                $default_cat_id = isset($possettting->default_category) && !empty($possettting->default_category) ? $possettting->default_category : 0;
                if ($default_cat_id):
                    $default_cat_product_count = $this->products_model->products_count($default_cat_id);
                    if ($default_cat_product_count == 0) {
                        $default_cat_id = 0;
                    }
                endif;
                $MsgArr = array();
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['count'] = count($res);
                    $i = 1;
                    foreach ($res as $resData) {

                        if ($resData['parent_id'] > 0):
                            $prdCount = $this->products_model->products_count($resData['parent_id'], $resData['id']);
                            $resData['product_count'] = $prdCount;
                        else:
                            $prdCount = $this->products_model->products_count($resData['id']);
                            $resData['product_count'] = $prdCount;
                            if ($default_cat_id == 0 && $prdCount > 0):
                                $default_cat_id = $resData['id'];
                            endif;
                        endif;

                        //   var_dump( $resData);
                        $MsgArr[] = $resData;
                    }
                    $MsgArr['default_category'] = $default_cat_id;
                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records";
                return $this->json_op($MsgArr);
                break;

            case 'getallcategories':
                $keyword = $this->input->post('keyword');
                $param = array('keyword' => $keyword);
                $possettting = $this->pos_model->getSetting();
                $res = $this->products_model->getCategories(NULL, $param);
                $default_cat_id = isset($possettting->default_category) && !empty($possettting->default_category) ? $possettting->default_category : 0;
                if ($default_cat_id):
                    $default_cat_product_count = $this->products_model->products_count($default_cat_id);
                    if ($default_cat_product_count == 0) {
                        $default_cat_id = 0;
                    }
                endif;
                $MsgArr = array();
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['image_path'] = base_url('assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/');
                    $MsgArr['count'] = count($res);
                    $i = 1;
                    foreach ($res as $key => $resData) {

                        if ($resData['parent_id'] > 0):
                            $prdCount = $this->products_model->products_count($resData['parent_id'], $resData['id']);
                            $resData['product_count'] = $prdCount;
                        else:
                            $prdCount = $this->products_model->products_count($resData['id']);
                            $resData['product_count'] = $prdCount;
                            if ($default_cat_id == 0 && $prdCount > 0):
                                $default_cat_id = $resData['id'];
                            endif;
                        endif;

                        $MsgArr['category'][] = (object) $resData;
                    }

                    $MsgArr['default_category'] = $default_cat_id;

                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records Found";
                $MsgArr['error_code'] = 105;
                return $this->json_op($MsgArr);
                break;

            case 'getparentcategories':
                $keyword = $this->input->post('keyword');
                $param = array('keyword' => $keyword);
                $res = $this->products_model->getCategories(0, $param);
                $MsgArr = array();
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['image_path'] = base_url('assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/');
                    $MsgArr['count'] = count($res);
                    $MsgArr['data'] = $res;

                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records Found";
                $MsgArr['error_code'] = 105;
                return $this->json_op($MsgArr);
                break;

            case 'getsubcategories':
                $keyword = $this->input->post('keyword');
                $param = array('keyword' => $keyword);
                $MsgArr = array();
                $parent_id = $this->input->post('parent_id');
                $parent_id = isset($parent_id) && !empty($parent_id) ? $parent_id : NULL;

                if ($parent_id === NULL):
                    $MsgArr['error'] = "Parent category id is mandatory";
                    $MsgArr['error_code'] = 104;
                    return $this->json_op($MsgArr);
                endif;

                $res = $this->products_model->getCategories($parent_id, $keyword);
                if (is_array($res)):
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['image_path'] = base_url('assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/');
                    $MsgArr['count'] = count($res);
                    $MsgArr['data'] = $res;

                    return $this->json_op($MsgArr);
                endif;
                $MsgArr['error'] = "No Records Found";
                $MsgArr['error_code'] = 105;
                return $this->json_op($MsgArr);
                break;

            case 'syncOnlineSettings':

                $query = $this->db->get('settings');
                $rows[settings] = $query->result();
                $query = $this->db->get('printer_bill');
                $rows[printer_bill] = $query->result();
                $query = $this->db->get('printer_bill_fields');
                $rows[printer_bill_fields] = $query->result();
                $response['status'] = 'success';
                $response['rows'] = $rows;
                return $this->json_op($response);

                break;

            case 'getcategorynamebyid':

                $category_id = (isset($_POST['category_id']) && !empty($_POST['category_id'])) ? $this->input->post('category_id') : 0;

                $res = $this->products_model->getCategoryName($category_id);
                if ($res == FALSE) {
                    $MsgArr['status'] = "ERROR";
                    $MsgArr['msg'] = "No records founds";
                    $MsgArr['error_code'] = 105;
                } else {
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['data'] = $res;
                }
                return $this->json_op($MsgArr);

                break;

            case 'getcategoryidbyname':

                $category_name = (isset($_POST['category_name']) && !empty($_POST['category_name'])) ? $this->input->post('category_name') : 0;

                $res = $this->products_model->getCategoryIdByName($category_name);
                if ($res == FALSE) {
                    $MsgArr['status'] = "ERROR";
                    $MsgArr['msg'] = "No records founds";
                    $MsgArr['error_code'] = 105;
                } else {
                    $MsgArr['status'] = "SUCCESS";
                    $MsgArr['data'] = $res;
                }
                return $this->json_op($MsgArr);

                break;

            default:
                break;
        }
    }

    public function get_synchronization_data($type) {
               
        $data['status'] = 'ERROR';

        switch ($type) {

            case 'products':

                $products = $this->api3_model->get_products();

                if ($products['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $products['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['data']['products'] = $products;
                    $data['data']['product_prices'] = $this->api3_model->get_product_prices();
                    $data['data']['product_variants'] = $this->api3_model->get_product_variants();
                    $data['data']['variants'] = $this->api3_model->get_variants();
                }//end if

                return $data;

                break;
            case 'categories':

                $categories = $this->api3_model->get_categories();
                if ($categories['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $categories['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['data']['categories'] = $categories;
                }//end if

                return $data;

                break;
            case 'brands':

                $brands = $this->api3_model->get_brands();

                if ($brands['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $brands['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['data']['brands'] = $brands;
                }//end if
                return $data;

                break;
            case 'images':
                $imageType = $this->input->post('updatedata');
                ///$images = $this->api3_model->get_product_images_list();                
                $path = str_replace('app/controllers', '', __DIR__);

                $dir = ($imageType == 'thumbs') ? $path . 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/' : $path . 'assets/mdata/'.$this->Customer_assets.'/uploads/';

                // Open a directory, and read its contents
                if (is_dir($dir)) {
                    if ($dh = opendir($dir)) {
                        while (($file = readdir($dh)) !== false) {
                            if (in_array($file, ['.', '..'])) {
                                continue;
                            }
                            if (is_dir($dir . $file)) {
                                continue;
                            }
                            if ($this->is_image($dir . $file)) {
                                $images[] = $file;
                            }
                        }
                        closedir($dh);
                    }
                }

                if ($images['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $images['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['data'] = $images;
                }//end if
                return $data;

                break;

            case 'customers':

                $customers = $this->api3_model->get_companies();

                if ($customers['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $customers['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['data']['companies'] = $customers;
                }//end if
                return $data;

                break;

            case 'warehouses':
            case 'stocks':

                $warehouses = $this->api3_model->get_warehouses();
                
                $data['status'] = 'ERROR';
                if ($warehouses['error_no']) {                    
                    $data['msg'] = $warehouses['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['data']['warehouses'] = $warehouses;
                    $data['data']['warehouses_products'] = $this->api3_model->get_warehouses_products();
                    $data['data']['warehouses_products_variants'] = $this->api3_model->get_warehouses_products_variants();
                    $data['data']['purchases'] = $this->api3_model->get_purchases();
                     //  $data['data']['purchase_items'] = $this->api3_model->get_purchase_items();
                    $data['data']['purchase_items'] = $this->api3_model->get_purchase_stocks();
                   // $data['data']['purchase_items_tax'] = $this->api3_model->get_purchase_items_tax();
                }//end if
            
                return $data;

                break;

            case 'taxes':

                $tax_attr = $this->api3_model->get_tax_attr();

                if ($tax_attr['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $tax_attr['error'];
                } else {

                    $data['status'] = 'SUCCESS';
                    $data['data']['tax_attr'] = $tax_attr;
                    $data['data']['tax_rates'] = $this->api3_model->get_tax_rates();

                    // $data['data']['tax_hsncodes']  =  $tax_hsncodes    = $this->api3_model->get_tax_hsncodes(); 
                }//end if

                return $data;

                break;
            case 'settings':

                $settings = $this->api3_model->get_offlinepos_system_settings();
                $pos_settings = $this->api3_model->get_offlinepos_pos_settings();

                if ($settings['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $settings['error'];
                } else if ($pos_settings['error_no']) {
                    $data['status'] = 'ERROR';
                    $data['msg'] = $pos_settings['error'];
                } else {
                    $data['status'] = 'SUCCESS';
                    $data['pk']['settings'] = 'setting_id';
                    $data['pk']['pos_settings'] = 'pos_id';

                    $settings['rows'][0]->default_warehouse = ($settings['rows'][0]->offlinepos_warehouse) ? $settings['rows'][0]->offlinepos_warehouse : $settings['rows'][0]->default_warehouse;
                    $settings['rows'][0]->default_biller = ($settings['rows'][0]->offlinepos_biller) ? $settings['rows'][0]->offlinepos_biller : $settings['rows'][0]->default_biller;
                    unset($settings['rows'][0]->offlinepos_warehouse);
                    unset($settings['rows'][0]->offlinepos_biller);

                    $data['data']['settings'] = $settings;
                    $data['data']['pos_settings'] = $pos_settings;
                }

                return $data;

                break;

            default:
                $data['status'] = 'ERROR';
                $data['msg'] = 'Invalid Synchronization Request';
                return $data;
                break;
        }
    }

    public function synchOfflineposSales($jsonSalesData) {

        $salesDataArr = json_decode($jsonSalesData);

        $MsgArr['data_post'] = $salesDataArr;
        if (!empty($salesDataArr->customers)) {
            $data['customers'] = $customerreff = $this->api3_model->addOfflineCustomers($salesDataArr->customers);
        }

        $data['sales'] = $salereff = $this->api3_model->addOfflineSales($salesDataArr->sales);

        $sales = $this->api3_model->addOfflineSalesItems($salesDataArr->items, $salereff);

        $data['items'] = $salesItemsReff = $sales['items'];

        $data['stocks'] = $sales['stocks'];

        if (count($data['items'])) {

            $data['tax'] = $this->api3_model->addOfflineSalesItemsTaxes($salesDataArr->tax, $salereff, $salesItemsReff);

            $data['costing'] = $this->api3_model->addOfflineSalesItemsCosting($salesDataArr->costing, $salereff, $salesItemsReff);

            $data['payments'] = $this->api3_model->addOfflineSalesPayment($salesDataArr->payments, $salereff);

            $data['deliveries'] = $this->api3_model->addOfflineSalesDeliveries($salesDataArr->deliveries, $salereff);

            $MsgArr['status'] = "SUCCESS";

            $MsgArr['data'] = $data;
        }//end if
        else {
            $MsgArr['status'] = "ERROR";
            $MsgArr['msg'] = "Synchronization failed";
            $MsgArr['error_code'] = 109;
        }

        return $MsgArr;
    }

    private function get_tax_methods() {

        $gst_attributes = $this->pos_model->getTaxAttributes();

        $tax_methods = $this->pos_model->getAllTaxRates();

        if (is_array($tax_methods) && !empty($tax_methods)) {
            $result['status'] = 'SUCCESS';
            $result['gst_attributes'] = $gst_attributes;
            $result['tax_methods'] = $tax_methods;
        } else {
            $result['status'] = 'ERROR';
        }

        return $this->json_op($result);
    }

    private function get_payment_methods($flag = NULL) {

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

    private function get_shipping_method() {

        $result = array();
        $result['status'] = 'ERROR';
        $res = $this->api3_model->getShippingMethods(array('is_deleted' => 0, 'is_active' => 1));

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

    private function is_image($path) {
        $a = getimagesize($path);
        $image_type = $a[2];

        if (in_array($image_type, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP))) {
            return true;
        }
        return false;
    }
    // download Pos Database details
    public function download_pos_details() {
        $ResultArr['current_pos_version'] = $this->posVersion->version;
        $this->data['action'] = $action = $this->input->post('action');
        if ($this->posVersion->version >= 1.03 && $action == 'POSDetails') {
            $start_limit = $this->input->post('start_limit');
            $end_limit = $this->input->post('end_limit');
            $ResultType = $this->input->post('ResultType');
            $this->data['updated_at'] = $this->input->post('updated_at');
            $Operation = $this->data['Operation'] = $this->input->post('Operation');
            if ($Operation == 'sync') {
                $module = $this->input->post('module');
                switch ($module) {
                    case "brands":
                        if ($ResultType == 'count') {
                            $ResultBrand = $this->api3_model->getBrandList($this->data);
                            $ResultArr['brandsCount'] = count($ResultBrand);
                            $ResultArr['brandsList'] = $this->api3_model->getBrandList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['brandsList'] = $this->api3_model->getBrandList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "categories":
                        if ($ResultType == 'count') {
                            $Resultcat = $this->api3_model->getCategoryList($this->data);
                            $ResultArr['categoriesCount'] = count($Resultcat);
                            $ResultArr['categoriesList'] = $this->api3_model->getCategoryList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['categoriesList'] = $this->api3_model->getCategoryList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "combo_items":
                        if ($ResultType == 'count') {
                            $Resultcombo_items = $this->api3_model->getComboItemsList($this->data);
                            $ResultArr['combo_itemsCount'] = count($Resultcombo_items);
                            $ResultArr['combo_itemsList'] = $this->api3_model->getComboItemsList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['combo_itemsList'] = $this->api3_model->getComboItemsList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "payments":
                        if ($ResultType == 'count') {
                            $Resultpayments = $this->api3_model->getPaymentList($this->data);
                            $ResultArr['paymentsCount'] = count($Resultpayments);
                            $ResultArr['paymentsList'] = $this->api3_model->getPaymentList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['paymentsList'] = $this->api3_model->getPaymentList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "pos_users":
                        if ($ResultType == 'count') {
                            $Resultpos_users = $this->api3_model->getUserList($this->data);
                            $ResultArr['pos_usersCount'] = count($Resultpos_users);
                            $ResultArr['pos_usersList'] = $this->api3_model->getUserList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['pos_usersList'] = $this->api3_model->getUserList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "customer_groups":
                        if ($ResultType == 'count') {
                            $Resultcustomer_groups = $this->api3_model->getCustomerGroupList($this->data);
                            $ResultArr['customer_groupsCount'] = count($Resultcustomer_groups);
                            $ResultArr['customer_groupsList'] = $this->api3_model->getCustomerGroupList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['customer_groupsList'] = $this->api3_model->getCustomerGroupList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "companies":
                        if ($ResultType == 'count') {
                            $Resultcompanies = $this->api3_model->getCompanyList($this->data);
                            $ResultArr['companiesCount'] = count($Resultcompanies);
                            $ResultArr['companiesList'] = $this->api3_model->getCompanyList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['companiesList'] = $this->api3_model->getCompanyList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "variants":
                        if ($ResultType == 'count') {
                            $Resultvariants = $this->api3_model->getVariantList($this->data);
                            $ResultArr['variantsCount'] = count($Resultvariants);
                            $ResultArr['variantsList'] = $this->api3_model->getVariantList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['variantsList'] = $this->api3_model->getVariantList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "products":
                        if ($ResultType == 'count') {
                            $Resultproducts = $this->api3_model->getProductList($this->data);
                            $ResultArr['productsCount'] = count($Resultproducts);
                            $ResultArr['productsList'] = $this->api3_model->getProductList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['productsList'] = $this->api3_model->getProductList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "units":
                        if ($ResultType == 'count') {
                            $Resultunits = $this->api3_model->getUnitList($this->data);
                            $ResultArr['unitsCount'] = count($Resultunits);
                            $ResultArr['unitsList'] = $this->api3_model->getUnitList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['unitsList'] = $this->api3_model->getUnitList($this->data, $start_limit, $end_limit);
                        }
                        break;

                    case "warehouses":
                        if ($ResultType == 'count') {
                            $Resultwarehouses = $this->api3_model->getWarehouseList($this->data);
                            $ResultArr['warehousesCount'] = count($Resultwarehouses);
                            $ResultArr['warehousesList'] = $this->api3_model->getWarehouseList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['warehousesList'] = $this->api3_model->getWarehouseList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "warehouses_products":
                        if ($ResultType == 'count') {
                            $Resultwarehouses_products = $this->api3_model->getAllWarehouseProductList($this->data);
                            $ResultArr['warehouses_productsCount'] = count($Resultwarehouses_products);
                            $ResultArr['warehouses_productsList'] = $this->api3_model->getAllWarehouseProductList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['warehouses_productsList'] = $this->api3_model->getAllWarehouseProductList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "warehouses_products_variants":
                        if ($ResultType == 'count') {
                            $Resultwarehouses_products_variants = $this->api3_model->getAllWarehouseProductVariantList($this->data);
                            $ResultArr['warehouses_products_variantsCount'] = count($Resultwarehouses_products_variants);
                            $ResultArr['warehouses_products_variantsList'] = $this->api3_model->getAllWarehouseProductVariantList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['warehouses_products_variantsList'] = $this->api3_model->getAllWarehouseProductVariantList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "product_variants":
                        if ($ResultType == 'count') {
                            $Resultproduct_variants = $this->api3_model->getProductvariantList($this->data);
                            $ResultArr['product_variantsCount'] = count($Resultproduct_variants);
                            $ResultArr['product_variantsList'] = $this->api3_model->getProductvariantList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['product_variantsList'] = $this->api3_model->getProductvariantList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "sales":
                        if ($ResultType == 'count') {
                            $Resultsales = $this->api3_model->getAllSaleList($this->data);
                            $ResultArr['salesCount'] = count($Resultsales);
                            $ResultArr['salesList'] = $this->api3_model->getAllSaleList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['salesList'] = $this->api3_model->getAllSaleList($this->data, $start_limit, $end_limit);
                            //print_r($this->data['salesList']);
                        }
                        break;
                    case "sale_items":
                        if ($ResultType == 'count') {
                            $Resultsale_items = $this->api3_model->getAllSaleItemList($this->data);
                            $ResultArr['sale_itemsCount'] = count($Resultsale_items);
                            $ResultArr['sale_itemsList'] = $this->api3_model->getAllSaleItemList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['sale_itemsList'] = $this->api3_model->getAllSaleItemList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "sales_items_tax":
                        if ($ResultType == 'count') {
                            $Resultsales_items_tax = $this->api3_model->getAllSaleItemTaxList($this->data);
                            $ResultArr['sales_items_taxCount'] = count($Resultsales_items_tax);
                            $ResultArr['sales_items_taxList'] = $this->api3_model->getAllSaleItemTaxList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['sales_items_taxList'] = $this->api3_model->getAllSaleItemTaxList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "purchases":
                        if ($ResultType == 'count') {
                            $Resultpurchases = $this->api3_model->getAllPurchaseList($this->data);
                            $ResultArr['purchasesCount'] = count($Resultpurchases);
                            $ResultArr['purchasesList'] = $this->api3_model->getAllPurchaseList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['purchasesList'] = $this->api3_model->getAllPurchaseList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "purchase_items":
                        if ($ResultType == 'count') {
                            $Resultpurchase_items = $this->api3_model->getAllPurchaseItemList($this->data);
                            $ResultArr['purchase_itemsCount'] = count($Resultpurchase_items);
                            $ResultArr['purchase_itemsList'] = $this->api3_model->getAllPurchaseItemList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['purchase_itemsList'] = $this->api3_model->getAllPurchaseItemList($this->data, $start_limit, $end_limit);
                        }
                        break;
                    case "purchase_items_tax":
                        if ($ResultType == 'count') {
                            $Resultpurchase_items_tax = $this->api3_model->getAllPurchaseItemTaxList($this->data);
                            $ResultArr['purchase_items_taxCount'] = count($Resultpurchase_items_tax);
                            $ResultArr['purchase_items_taxList'] = $this->api3_model->getAllPurchaseItemTaxList($this->data, $start_limit, $end_limit);
                        } else {
                            $ResultArr['purchase_items_taxList'] = $this->api3_model->getAllPurchaseItemTaxList($this->data, $start_limit, $end_limit);
                        }
                        break;
                }
            } else {
                $TableArr = array('user_id' => 'pos_users', 'customer_group_id' => 'customer_groups', 'company_id' => 'companies', 'variant_id' => 'variants', 'unit_id' => 'units', 'brand_id' => 'brands', 'category_id' => 'categories', 'product_id' => 'products', 'product_varient_id' => 'product_variants', 'combo_item_id' => 'combo_items', 'warehouse_id' => 'warehouses', 'warehouse_product_id' => 'warehouses_products', 'warehouse_product_variant_id' => 'warehouses_products_variants', 'invoice_sale_id' => 'sales', 'invoice_sale_item_id' => 'sale_items', 'reference_purchase_id' => 'purchases', 'reference_purchase_item_id' => 'purchase_items', 'payment_id' => 'payments');
                foreach ($TableArr as $keys => $values) {
                    $ResultArr[$values] = $this->api3_model->getDeletedList($this->data, $keys, $values, $start_limit, $end_limit);
                }
            }
        }
        if ($action == 'NotificationAdd') {

            header('Access-Control-Allow-Origin: *');
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
            $method = $_SERVER['REQUEST_METHOD'];
            if ($method == "OPTIONS") {
                die();
            }
            $NotificationId = $this->input->post('NotificationId');
            $Datas['from_date'] = $this->input->post('from_date');
            $Datas['till_date'] = $this->input->post('to_date');
            $Datas['comment'] = $this->input->post('comment');
            $Datas['scope'] = $this->input->post('scope');
            if ($NotificationId == '') {
                $row_data['not_id'] = $this->api3_model->addNotificaion($Datas);
                $row_data['res_action'] = 'Add';
                $data[] = $row_data;
            } else {
                $not_id = $this->input->post('not_id');
                $this->api3_model->updateNotificaion($not_id, $Datas);
                $row_data['res_action'] = 'Update';
                $data[] = $row_data;
            }
            //$datas = array_map('array_values', $data);
            $ResultArr = array('data' => $data);
        }
        if ($action == 'NotificationDelete') {
            header('Access-Control-Allow-Origin: *');
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
            $method = $_SERVER['REQUEST_METHOD'];
            if ($method == "OPTIONS") {
                die();
            }
            $NotificationId = $this->input->post('NotificationId');
            $Result = $this->api3_model->deleteNotificaionById($NotificationId);
            $ResultArr = array('data' => 'delete');
        }
        echo $this->json_op($ResultArr);
    }

    // download Pos Database details
    
    public function get_purchase_items() {

        $purchase_items = $this->api3_model->get_purchase_items();  
         echo "<pre>";       
        print_r($purchase_items);
         echo "</pre>"; 
    }
    
}

?>