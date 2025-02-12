<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Production_Unit extends MY_Controller {

    function __construct() {
        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        $this->load->library('form_validation');
        $this->load->model('production_unit_model');
        $this->load->model('Production_Unit_Model_New');
        $this->load->model('Products_model');
        $this->load->database(); // Database library
        $this->load->library('datatables');
        $this->load->library('upload');
        $this->digital_upload_path = 'files/'.$this->Customer_assets;
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/production_unit';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';
        $this->popup_attributes = array('width' => '900', 'height' => '600', 'window_name' => 'sma_popup', 'menubar' => 'yes', 'scrollbars' => 'yes', 'status' => 'no', 'resizable' => 'yes', 'screenx' => '0', 'screeny' => '0');
        $this->data['Settings'] = $this->Settings;

    }

    function index($warehouse_id = NULL) {
        // $this->sma->checkPermissions();

        $this->data['locationNames'] = $this->production_unit_model->getLocations();      
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit')), array('link' => '#', 'page' => lang('Working_Orders')));
        $meta = array('page_title' => lang('Production_Unit'), 'bc' => $bc);
        $this->page_construct('production_unit/order_dispatch', $meta, $this->data);
    }
    
    public function getProcurementRefrenceNo($warehouse_id = NULL) {
        
        $locationName       = $_GET['location']; 
        $status             = $_GET['status'];
        $procurmentRefNo    = $_GET['procurmentRefNo'];
        $itemId             = $_GET['itemId']; 
        $quantity           = $_GET['quantity'];
        $itemData           = $_GET['itemData'];
        $checkbox           = $_GET['checkbox'];
        $Filter             = $_GET['Filter'];
        $Complete_unit_order = $_GET['Complete_unit_order'];
        $deliveryDateTime   = $_GET['deliveryDateTime'];
        $AllItems           = $_GET['AllItems']; // View full order 
       
        $user_id       = $this->session->userdata('user_id');
        $location_data = $this->site->getUser($user_id);
        $location_id   = ($AllItems == false) ? $location_data->warehouse_id : '';

        //update planned_delivery_datetime after set delivery time for orders
        if($deliveryDateTime){
            $datetime = [
                'id' => $procurmentRefNo,
                'planned_delivery_datetime' => $deliveryDateTime
            ];
            $this->production_unit_model->updateOrderItemStock($quantity, $itemId, $itemData, $procurmentDetails, $checkbox, $Complete_unit_order, $datetime); //update status after click on locked button and also click on complete order button
        } 
        if($checkbox == 1 || $checkbox == 0){
            $this->production_unit_model->updateOrderItemStock($quantity, $itemId, $itemData, Null, $checkbox); // update status, allot qty and stock after check and uncheck on checkbox
        }
        $refrenceNo = $this->production_unit_model->getProcurementRefrenceNo($locationName, $Filter);
        $procurmentId = [ ];
        foreach ($refrenceNo as  $value) {
            $procurmentId[] = $value->itemId;
        } 
        
        $procurmentsDetail = ''; 
        if ($status || $procurmentRefNo) {
           
            $this->db->select('poi.id as itemId, poi.procurement_orders_id, poi.product_id, poi.product_name, poi.product_code,poi.order_quantity, poi.allot_quantity, po.procurement_order_ref_no, po.location_name, po.note, po.status as order_status, poi.item_status, productionunit_products.stock_quantity as stock_quantity,productionunit_products.open_order_quantity, poi.production_unit_id, poi.production_unit_name');
            $this->db->from('sma_procurement_order_items poi');
            $this->db->join('products ', 'products.id = poi.product_id', 'left');
            $this->db->join('productionunit_products ', 'productionunit_products.product_id = products.id', 'left');
            $this->db->join('sma_procurement_orders po', 'po.id = poi.procurement_orders_id');
            // if ($status) {
            //     $this->db->where('poi.item_status', $status);
            // }
            if ($status == 'Completed') {
                // Condition for Completed Items tab 
                $this->db->where('poi.order_quantity = poi.allot_quantity', null, false);
            } elseif ($status == 'Partially Completed') {
                // Condition for Partially Completed Items tab 
                $this->db->where('poi.order_quantity > poi.allot_quantity AND poi.allot_quantity > 0');
            } elseif ($status == 'Pending') {
                // Condition for Pending Items tab 
                $this->db->where('(poi.allot_quantity = 0 OR poi.allot_quantity IS NULL)');
            }
            if ($procurmentRefNo) {
                $this->db->where('poi.procurement_orders_id', $procurmentRefNo);
            }
            if($location_id) {
                $this->db->where('poi.production_unit_id', $location_id); // show prodution_unit wise order items data
            }
            $this->db->order_by("poi.id", "DESC");
            $procurmentsDetail = $this->db->get()->result();
            $procurmentDetails = $procurmentsDetail;
            // $this->production_unit_model->updateOrderItemStock($quantity = null, $itemId = null, $itemData = null, $procurmentDetails, null, $Complete_unit_order); //update status after click on locked button and also click on complete order button
            $this->production_unit_model->updateOrderItemStock($quantity = null, $itemId = null, $itemData = null, $procurmentDetails, null, $Complete_unit_order, null, $status); //update status after click on locked button and also click on complete order button

        } else {
            $procurmentDetails = $this->production_unit_model->getProcurementdetails($procurmentId, $status = null, null, $location_id);
            foreach ($procurmentDetails as $item) {
                if ($item->item_status == "completed") {
                    $item->open_order_quantity = 0;
                }
            }
        }        
        $pr[] = ['refrenceNo' => $refrenceNo, 'procurmentDetails' => $procurmentDetails, 'location_data' => $location_data];
        echo json_encode($pr);
        return;

    }
    //Procurment Orders 
    public function procurementOrders() {
        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); //get user information
        $location_id   = $user_data->warehouse_id;
        if ($this->Owner || $this->Admin) {
            $location_data = $this->site->getAllWarehouses(); 
        }else {
            $location_data = $this->site->getWarehouseByIDs($location_id); //get
        }
        $locationName  = '';
        foreach ($location_data as $location) {
           $locationName = $location->name;
        }
            $this->data['error']       = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['categories']  = $this->production_unit_model->getProductCategoriesList();     
            $this->data['outletName']  = $location_data;

            $bc   = array(array('link' => base_url(), 'page' => lang('Procurement_Orders')), array('link' => '#', 'page' => lang('Place_Order')));
            $meta = array('page_title' => lang('Place_Order'), 'bc' => $bc);

            $this->page_construct('production_unit/procurement_order', $meta, $this->data);      
    }
    
    public function GetProductsbyCategoriesID() {   

        $categoriesId    = $this->input->get('categoriesId');
        $subcategoryId   = $this->input->get('subcategoryId');
        $location        =  $this->production_unit_model->getWarehouseByID($location_id);
        
        $productsByCategories = '';
        if ($categoriesId || $subcategoryId) {
            $productsByCategories  = $this->production_unit_model->getProductByCategories($categoriesId,$subcategoryId);    
        }
        if ($productsByCategories) {
            $i=0;
            foreach ($productsByCategories as $row) {
                
                $unitData = $this->production_unit_model->getUnitById($row->unit);
                $row->unit_name      = $unitData->name;
                $row->unit_price        = $row->price;  
                $row->org_price         = $row->price;
                $row->base_unit_price   = $row->price;
                $row->unit_weight       = $row->weight;
                $row->quantity          = 1;

                if (($location->price_group_id)) {                 
                    if ($pr_group_price = $this->site->getProductGroupPrice($row->id, $location->price_group_id)) {
                        $row->unit_price = $pr_group_price->price;
                    }
                }
                if ($row->unit_price == 0){
                    $row->unit_price = $row->org_price;
                }
                $tax_rate   = $this->site->getTaxRateByID($row->tax_rate);

            }
            $pr[] = ['productsByCat' => $productsByCategories,  'row' => $row, 'tax_rate' => $tax_rate];
        }else {
            $pr[] = ['productsByCat' => $productsByCategories];

        }
        echo json_encode($pr);
        return;
    }
      
    public function PlaceProcurementOrder(){

        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); //get user information
        $location_id   = $user_data->warehouse_id;
        if ($orders = $this->input->get('orders')) {
            // Debugging: Output orders received
           
            if (!empty($orders)) {
                $Items = array();
                $i = 0;
                $rows = "";
                $note = isset($orders[0]['note']) ? $orders[0]['note'] : '';
                $outletNames = isset($orders[0]['outletNames']) ? $orders[0]['outletNames'] : $locationName;
                $outletData = $this->Production_Unit_Model_New->getLocationID($outletNames);
                
                $requested_delivery_date = isset($orders[0]['requested_delivery_date']) ? $orders[0]['requested_delivery_date'] : '';
                $cgst = $sgst = $igst = 0;
                
                // Determine if it's interstate for GST calculation
                $interStateTax = !empty($location_data->state_code) ? true : false;
        
                foreach ($orders as $order) {
                    // Fetch product details
                    $rows = $this->production_unit_model->getProductDetailsByName($order['product']);
                    // Fetch production unit details for the product
                    $product_location = $this->production_unit_model->getProductionUnitDetailsForProduct($rows->id);
                    // Get warehouse details by location ID
                    $location  = $this->site->getWarehouseBy_ID($product_location->location_id);
        
                    // Process order details
                    $subtotal  = str_replace(['Rs.', ','], '', $order['sub_total']);
                    $tax       = str_replace(['Rs.', ','], '', $order['tax']);
                    $net_price = str_replace(['Rs.', ','], '', $order['net_price']);
        
                    // Calculate product price
                    $order_quantity = $order['0'];
                    $product_price = $this->sma->formatDecimal(($subtotal / $order_quantity), 2);
                    $item_net_price = $product_price;
        
                    // Calculate tax for the product
                    $calculated = $this->site->calculateTax($rows, $product_price, $order_quantity, $interStateTax);
                    $product_tax += $calculated['pr_item_tax'];
    
                    // Prepare item data
                    $Items[$i] = array(
                        'product_id'           => $rows->id,
                        'product_code'         => $rows->code,
                        'product_name'         => $order['product'],
                        'order_quantity'       => $order['0'],
                        'item_status'          => 'Open',
                        'unit_quantity'        => $rows->quantity,
                        'unit_price'           => $product_price,
                        'unit_cost'            => $rows->cost,
                        'product_unit_id'      => $rows->unit,
                        'product_unit_code'    => $order['unit'],
                        'tax_rate_id'          => $rows->tax_rate,
                        'tax'                  => $calculated['tax'],
                        'item_tax'             => $calculated['pr_item_tax'],
                        'net_unit_cost'        => $rows->cost,
                        'net_price'            => $net_price,
                        'subtotal'             => $subtotal,
                        'production_unit_id'   => $location->id,
                        'production_unit_name' => $location->name,
                        'production_unit_code' => $location->code
                    );
        
                    // Accumulate tax values
                    $cgst += $calculated['item_cgst'];
                    $sgst += $calculated['item_sgst'];
                    $igst += $calculated['item_igst'];
                    // Calculate total cost
                    $total += $this->sma->formatDecimal(($product_price * $order_quantity), 2);
                    $i++;
                }
        
                // Format tax and calculate grand total
                $total_tax = $this->sma->formatDecimal(($product_tax), 2);
                $shipping_amount = $this->sma->formatDecimal('');
                $grand_total = $this->sma->formatDecimal(($total  + $shipping_amount), 2);
        
                // Determine procurement order reference number
                $porder = $this->production_unit_model->getOrder($outletNames);
                $outlate_name = substr($porder->procurement_order_ref_no, 0, strpos($porder->procurement_order_ref_no, '/'));
                $refrence_No = substr($porder->procurement_order_ref_no, strrpos($porder->procurement_order_ref_no, '/') + 1);
        
                if ($outletNames === $outlate_name) {
                    $numeric_part = intval($refrence_No);
                    $new_numeric_part = $numeric_part + 1;
                    $incremented_number = sprintf('%04d', $new_numeric_part); // Format as 4-digit number
                } else {
                    $incremented_number = '0001';
                }
                $order_no = $outletNames . '/' . $incremented_number;
        
                // Prepare data for insertion
                $orderItems = count($Items);
                $data = array(
                    'procurement_order_ref_no' => $order_no,
                    'location_code'            => $outletData->code,
                    // 'location_id'              => $location_id,
                    'location_id'              => $outletData->id,

                    'location_name'            => $outletNames,
                    'location_state_code' => 'MH',
                    'created_by'               => $user_id,
                    'requested_delivery_date'  => $requested_delivery_date,
                    'note'                     => $note,
                    'status'                   => 'Open',
                    'total'                    => $orderItems,
                    'total_tax'                => $total_tax,
                    'shipping_amount'          => '',
                    'grand_total'              => $grand_total
                );
        
                // Insert data into procurement orders table
                if ($this->production_unit_model->PlaceProcurementOrder($data, $Items)) {
                    $this->load->library('session');
                    $affected_rows = $this->db->affected_rows();
                    $this->session->set_userdata('affected_rows_count', $affected_rows);
                    $response = array('status' => 'success', 'message' => 'Order Placed Successfully.');
                } else {
                    $response = array('status' => 'error', 'message' => 'Failed to insert data.');
                }
                echo json_encode($response);
                return;
            } else {
                // If no items found in orders
                $response = array('status' => 'error', 'message' => 'Item Data Not Found.');
                echo json_encode($response);
            }
        } else {
            // If no orders received
            $this->session->set_flashdata('error', validation_errors());
            redirect("production_Unit/procurementOrders");
        }
    
    }
   
    public function getProcurementOrderList() {     

        // Example PHP controller endpoint
        $status          = $this->input->get('orderStatus');
        $order_id        = $this->input->get('order_id');

        if ($status) {
            $order_dispatch = ($this->Settings->set_order_dispatch == '1') ? '1' : '0';
            $getOrderData  = $this->production_unit_model->getProcurmentOrders($status, $order_dispatch); 
            $pr[] = ['getOrderData' => $getOrderData];

        }else {
            $pr = "Please Select Status";
        }
        echo json_encode($pr);
        return;
    }
     
    public function getProcurementOrderItemsListByOrderStatus() {     

        $status          = $this->input->get('orderStatus');
        $order_id        = $this->input->get('order_id');

        if ($order_id) {
            $item_data     = $this->production_unit_model->getOrdersByItems($order_id,$status );
            $pr[] = ['item_data' => $item_data];
        }else {
            $pr = "Please Select Status";
        }
        echo json_encode($pr);
        return;
    }

    public function updateProcurementOrder(){
        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); //get user information
        $location_id   = $user_data->warehouse_id;
        $location_data = $this->site->getWarehouseByIDs($location_id); //get
        $currentDateTime = date('Y-m-d H:i:s');
        $order_id = '';
        $locationName = '';
        foreach ($location_data as $location) {
        $locationName = $location->name;
        }
    
        if ($orders = $this->input->get('orders')) {
          
            if (!empty($orders)) {
                $Items = array();
                $i = 0;
                $rows = "";
                $status = "";
                $note                    = isset($orders[0]['note']) ? $orders[0]['note'] : '';
                $requested_delivery_date = isset($orders[0]['requested_delivery_date']) ? $orders[0]['requested_delivery_date'] : '';
                $outletNames = isset($orders[0]['outletNames']) ? $orders[0]['outletNames'] : $locationName;
                $cgst = $sgst = $igst = 0;
                $order_id = $orders[0]['order_id'];
                $interStateTax = !empty($location_data->state_code) ? true : false;  //for gst calculation
                $selectedHorizonatlTabValue = '';
                $received_qty = '';
                foreach ($orders as $order) { 
                   
                    $received_qty = $order['received_qty'];
                    $rows             = $this->production_unit_model->getProductDetailsByName($order['product']);
                    $orderItemDetails = $this->production_unit_model->getOrderByItems($order_id,$rows->id);
                    $product_location = $this->production_unit_model->getProductionUnitDetailsForProduct($rows->id);  //productionunit_products data
                    $location         = $this->site->getWarehouseBy_ID($product_location->location_id); 
                    $subtotal         = str_replace(['Rs.', ','], '', $order['sub_total']);                
                    $tax              = str_replace(['Rs.', ','], '', $order['tax']);                
                    $net_price        = str_replace(['Rs.', ','], '', $order['net_price']);           
                    $adjustment_price        = str_replace(['Rs.', ','], '', $order['adjustments']);           
                    // $subtotal         = $this->sma->formatDecimal(($order['sub_total']),2);
                    $order_quantity   = $order['0'];
                    $product_price    = $this->sma->formatDecimal(($subtotal/$order_quantity),2);
                    $item_net_price   = $product_price;
                    $calculated       = $this->site->calculateTax($rows, $product_price, $order_quantity, $interStateTax); // Tax calculation
                    $product_tax += $calculated['pr_item_tax'];
                    $selectedHorizonatlTabValue = $order['selectedHorizonatlTabValue'];
                
                    if ($selectedHorizonatlTabValue == 'reject') {
                        $selectedHorizonatlTabValue = ($selectedHorizonatlTabValue === 'received_order') ? $order['selectedHorizonatlTabValue'] : 'reject';
                    }else {
                        $selectedHorizonatlTabValue = ($selectedHorizonatlTabValue === 'received_order') ? $order['selectedHorizonatlTabValue'] : 'update_order';
                    }
                  
                    $Items[$i]   = array(
                        'product_id'            => $rows->id,
                        'product_code'          => $rows->code,
                        'product_name'          => $order['product'],
                        // 'batch_number'       => '',
                        // 'manufacturing_date' => '',
                        // 'expiry_date'        => '',
                        'order_quantity'        => $order['0'],
                        // 'received_quantity'  => '',
                        'item_status'           =>'Open',
                        'unit_quantity'         => $rows->quantity,
                        'unit_price'            => $product_price,
                        'unit_cost'             => $rows->cost,
                        'product_unit_id'       => $rows->unit,
                        'product_unit_code'     => $order['unit'],
                        'tax_rate_id'           => $rows->tax_rate,
                        'tax'                   => $calculated['tax'],
                        'item_tax'              => $calculated['pr_item_tax'],
                        'net_unit_cost'         => $rows->cost,
                        'net_price'             => $net_price,
                        'subtotal'              => $calculated['subtotal'],
                        'production_unit_id'    => $location->id,
                        'production_unit_name'  => $location->name,
                        'production_unit_code'  => $location->code    
                    );  
                    if ($selectedHorizonatlTabValue === 'received_order') { 
                        $Items[$i]   = array(
                            'id'                      => $orderItemDetails->order_id,           
                            'received_quantity'       => $received_qty,
                            'received_by'             => $user_id ,          
                            'actual_delivery_date'    => $currentDateTime ,
                            'item_status'             =>'Received', 
                            'product_id'              => $rows->id,
                            'adjustment_price'        => $adjustment_price

                        );
                    }
                    if ($selectedHorizonatlTabValue === 'reject') { 
                        $Items[$i]   = array(
                            'id'                      => $orderItemDetails->order_id,           
                            'received_quantity'       => $received_qty,
                            'received_by'             => $user_id ,          
                            'actual_delivery_date'    => $currentDateTime ,
                            'item_status'             =>'Rejected',
                            'product_id'              => $rows->id,
                            'adjustment_price'        => $adjustment_price
                        );
                    }
                    $cgst += $calculated['item_cgst'];
                    $sgst += $calculated['item_sgst'];
                    $igst += $calculated['item_igst']; 
                    $igst += $calculated['item_igst'];
                    $adjustment_price += $this->sma->formatDecimal(($adjustment_price),2);
                    $total+= $this->sma->formatDecimal(($product_price * $order_quantity), 2); 
                    $i++;  
                
                }  
                
                $adjustment_price       = $adjustment_price;
                $total_tax       = $this->sma->formatDecimal(($product_tax),2);
                $shipping_amount = $this->sma->formatDecimal('');
                $grand_total     = $this->sma->formatDecimal(($total  + $shipping_amount),2);

                // procurement_order_ref_no logic
                $porder = $this->production_unit_model->getOrder($location_data->code); 
                $getOrderRefrenceNoById = $this->production_unit_model->getOrderRefrenceNoById($order_id); 
                $outlate_name = substr($porder->procurement_order_ref_no, 0, strpos($porder->procurement_order_ref_no, '/'));
                $refrence_No = substr($porder->procurement_order_ref_no, strrpos($porder->procurement_order_ref_no, '/') + 1);
                
                if ($locationName === $outlate_name) {
                    $numeric_part = intval($refrence_No);
                    $new_numeric_part = $numeric_part + 1;
                    $incremented_number = sprintf('%04d', $new_numeric_part); // Format as 4-digit number
                } else {
                    $incremented_number = '0001';
                }
                $order_no = $locationName . '/' . $incremented_number;
                // procurement_order_ref_no logic end
                $orderItems      = count($Items); 
                $outletData = $this->Production_Unit_Model_New->getLocationID($outletNames);

                $data = array(
                    'procurement_order_ref_no' => $getOrderRefrenceNoById->procurement_order_ref_no,
                    'location_code'            => $outletData->code,
                    // 'location_id'              => $location_id,
                    'location_id'              => $outletData->id,
                    'location_name'            => $outlate_name,
                    'location_state_code'      => 'MH',
                    'created_by'               => $user_id,
                    'requested_delivery_date'  => $requested_delivery_date,
                    'note'                     => $note,
                    'status'                   => 'Open',
                    'total'                    => $orderItems,
                    'total_tax'                => $total_tax,
                    'shipping_amount'          => '',
                    'grand_total'              => $grand_total
                    // 'actual_order_amount'      => '',
                    // 'actual_delivery_date'     => '',
                );
           
                if ($selectedHorizonatlTabValue === 'received_order') { 
                    $data   = array(
                        // 'received_quantity'       => $received_qty,
                        'status'                   => 'Received',
                        'received_by'             =>   $user_id ,          
                        'location_id'             => $orderItemDetails->location_id,
                        'actual_delivery_date'    =>   $currentDateTime,
                        'adjustment_price'        => $adjustment_price          
                    );
                }
                if ($selectedHorizonatlTabValue === 'reject') { 
                    $data   = array(
                        // 'received_quantity'       => $received_qty,
                        'status'                   => 'Rejected',
                        'received_by'             =>   $user_id ,          
                        'location_id'             => $orderItemDetails->location_id,
                        'actual_delivery_date'    =>   $currentDateTime, 
                        'adjustment_price'        => $adjustment_price         
                    );
                }
             
            if ($this->production_unit_model->updateProcurementOrder($order_id ,$data , $Items, $selectedHorizonatlTabValue)) {
                    $response = array('status' => 'success', 'message' => 'Data inserted successfully.');
                }
                else {
                    $response = array('status' => 'error', 'message' => 'Failed to insert data.');
                }
                echo json_encode($response);
                return;
            } else {
                $this->session->set_flashdata('error', validation_errors());
                redirect("production_Unit/procurementOrders");
                
            }
        }
        else {
            // Send error response if 'orders' parameter is missing
            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['categories']  = $this->production_unit_model->getProdCategories();     
            $this->data['user_name']  = $user_data->first_name;
            $bc = array(array('link' => base_url(), 'page' => lang('Procurement_Orders')), array('link' => '#', 'page' => lang('Place_Order')));
            $meta = array('page_title' => lang('Production_Unit'), 'bc' => $bc);
            $this->page_construct('production_unit/procurement_order', $meta, $this->data);
        }
    }
    #=========================================== Production Manager Dashboard Screen  ============================================
   
    // Production Manager Dashboard
    public function manager_dashboard() {

        // $this->sma->checkPermissions();
        $user_id               = $this->session->userdata('user_id');   
        $user_data             = $this->site->getUser($user_id); 
        $location_id           = $user_data->warehouse_id;
        $location_data         = $this->site->getWarehouseByIDs($location_id); 
        $productionUnitName    = $this->input->get('productionUnitName');
        
        $default_location      = reset($location_data); // Get the first location
        $default_location_name = $default_location->name; 
       
        $productionUnit  = '';
        foreach ($location_data as $location) {
            $productionUnit[] = $location->name;
        }
      
        if($product_id = $this->input->get('productId')){

            if(!empty($product_id)){
                // Production Dashboard :reset stock
                $this->production_unit_model->resetProductStock($product_id);
            }else {
                $this->session->set_flashdata('error', validation_errors());
                redirect("production_Unit/manager_dashboard");
            }

        }else{

            // Fetch products based on the default location or selected production unit
            if ($productionUnitName) {
                $products = $this->production_unit_model->getproductDetailsByLocation($productionUnitName);
            } else {
                $products = $this->production_unit_model->getproductDetailsByLocation($default_location->name);
            }
            if ($this->input->is_ajax_request()) {
                echo json_encode($products);
                return;
            }
            $this->data['products'] = $products;
            // $this->data['products']  = $this->production_unit_model->getproductDetails();  
            $this->data['productionUnitName']  = $productionUnit; 
            $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit')), array('link' => '#', 'page' => lang('Manager_Dashboard')));
            $meta = array('page_title' => lang('production_dashboard'), 'bc' => $bc);
            $this->page_construct('production_unit/production_dashboard', $meta, $this->data);
        }
    }
    public function getProductWiseAllDetails() {

        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); 
        $location_id   = $user_data->warehouse_id;
        $location_data = $this->site->getWarehouseByIDs($location_id); 

        $locationCode  = '';
        foreach ($location_data as $location) {
            $locationCode = $location->code;
        }

        $product_id           = $this->input->get('productId');
        $manufacturingDate    = $this->input->get('manufacturingDate'); // manufacturingDate of product

        $OrderDetails         = $this->production_unit_model->getProductWiseOrderdetails($product_id);
        $productStock         = $this->production_unit_model->getProductStock($product_id);
        $productBatches       = $this->production_unit_model->getProductBatches($product_id); // For All Batches
        $latestProductBatches = $this->production_unit_model->getLatestProductBatches($product_id); // For latest 5 Batches
        $productDetails       = $this->production_unit_model->getProductDetailsByName($name = Null, $product_id);
        $lastBatch            = $this->production_unit_model->getLastBatchNumber($product_id); // Get the last batch number for the specific location and product
        
        $product_shelf_life = isset($productDetails->shelf_life) ? $productDetails->shelf_life : '';
        // Calculate product expiry date
        if(empty($product_shelf_life)){
            $expiryDate = '';
        }else{
            $expiryDate = date('Y-m-d', strtotime($manufacturingDate  . ' + ' . $productDetails->shelf_life . ' days'));
        }
        
        $productDetails->expiryDate = $expiryDate; 
        $productBatches->expiryDate = $expiryDate; 

        $pr[] = ['OrderDetails' => $OrderDetails, 'productStock' => $productStock, 'productBatches' => $productBatches, 'latestProductBatches' => $latestProductBatches, 'productDetails' => $productDetails, 'lastBatch' => $lastBatch, 'locationCode' => $locationCode];
        echo json_encode($pr);
        return;
        
    }
     //Insert Batches
    public function addProductWiseBatches() {
        
        $product_id     = $this->input->get('productId');
        $batchQuantity  = $this->input->get('batchQuantity');
        $manufacturingDate  = $this->input->get('manufacturingDate');

        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); 
        $location_id   = $user_data->warehouse_id;
        $location_data = $this->site->getWarehouseByIDs($location_id); 
        $locationCode  = '';
        foreach ($location_data as $location) {
            $locationCode = $location->code;
        }

        # Location Wise Batch Number 
        // Get the last batch number for the specific location and product
        $lastBatch = $this->production_unit_model->getLastBatchNumber($product_id);
        
        // Prepare the new batch number
        if ($lastBatch) {
            $lastBatchNumber = intval(substr($lastBatch->batch_no, -3));
            $newBatchNumber  = str_pad($lastBatchNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newBatchNumber = '001';
        }
        $batch_no = $locationCode . '/' . $product_id . '/' . $newBatchNumber;
        // echo json_encode($batch_no);
        // return;
        # Location Wise Batch Number end


        if(!empty($batchQuantity)){
          
            $productDetails = $this->production_unit_model->getProductDetailsByName($name = Null, $product_id);
            $product_shelf_life = $productDetails->shelf_life;
            
            // Calculate product expiry date
            $expiryDate = date('Y-m-d', strtotime($manufacturingDate  . ' + ' . $product_shelf_life . ' days'));
            // $batch_no = $this->createLocationWiseBatchNumber($product_id);
            
            $data = array(
                'batch_no'        => $batch_no,
                'product_id'      => $product_id,
                'created_at'      => $manufacturingDate,
                'quantity'        => $batchQuantity,
                'expiry_date'     => $expiryDate,
                'cost'            => $productDetails->cost,
                'mrp'             => $productDetails->mrp,
                'price'           => $productDetails->price,
                'location_id'     => $location_id
            );
         
            if ($this->production_unit_model->addProductBatches($data)) {

                $productBatches       = $this->production_unit_model->getProductBatches($product_id); // For All Batches
                $latestProductBatches = $this->production_unit_model->getLatestProductBatches($product_id); // For latest 5 Batches
                $productStock         = $this->production_unit_model->getProductStock($product_id); // for show updated stock after add batch
                
                $productBatchesData = ['productBatches' => $productBatches, 'latestProductBatches' => $latestProductBatches, 'productStock' => $productStock];
                echo json_encode($productBatchesData);
                return;
            }
        }else{
            $this->session->set_flashdata('error', validation_errors());
            redirect("Production_Unit/manager_dashboard");
        }
    }

    // Production Dashboard :create new batch number
    public function createLocationWiseBatchNumber($product_id) {

        $product_id  = $this->input->get('productId');
        $user_id     = $this->session->userdata('user_id');   
        $user_data   = $this->site->getUser($user_id); 
        $location_id = $user_data->warehouse_id;

        $location_data = $this->site->getWarehouseByIDs($location_id); 
        $locationCode  = '';
        foreach ($location_data as $location) {
            $locationCode = $location->code;
        }
    
        // Get the last batch number for the specific location and product
        $lastBatch = $this->production_unit_model->getLastBatchNumber($product_id);
    
        // Prepare the new batch number
        if ($lastBatch) {
            $lastBatchNumber = intval(substr($lastBatch->batch_no, -3));
            $newBatchNumber  = str_pad($lastBatchNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newBatchNumber = '001';
        }
    
        $batch_no = $locationCode . '/' . $product_id . '/' . $newBatchNumber;
        echo json_encode(['batch_no' => $batch_no]);
        return $batch_no;
    }

    public function updateDashboardOrderItemDetails() {

        $orderItemId          = $this->input->get('orderItemId');  // get order item id 
        $isChecked            = $this->input->get('isChecked');   // flag for allot qty checkbox click 
        $allotQuantityInput   = $this->input->get('allotQuantityInput');
        $product_id           = $this->input->get('productId');
        $user_id              = $this->session->userdata('user_id');   

        // $this->production_unit_model->updateDashboardOrderItems($orderItemId, $allotQuantityInput, $isChecked); 
        $this->Production_Unit_Model_New->updateOrder(Null, Null, $isChecked, $allotQuantityInput, $orderItemId, Null, Null, Null, $user_id); //update status after click on locked button and also click on complete order button
        
        // get update order and order item details to show on view
        $OrderDetails         = $this->production_unit_model->getProductWiseOrderdetails($product_id);
       
        $updatedOrderDetails[] = ['OrderDetails' => $OrderDetails];
        echo json_encode($updatedOrderDetails);
        return;
    }

    #=========================================== End Production Manager Dashboard Screen  ============================================
    public function receive_delivery() {
        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); //get user information
        $location_id   = $user_data->warehouse_id;
        $location_data = $this->site->getWarehouseByIDs($location_id); //get

        $locationName  = '';
        foreach ($location_data as $location) {
           $locationName = $location->name;
        }
            $this->data['error']       = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['categories']  = $this->production_unit_model->getProductCategoriesList();     
            $this->data['outletName']  = $location_data;

            $bc   = array(array('link' => base_url(), 'page' => lang('Procurement_orders')), array('link' => '#', 'page' => lang('Receive_Delivery')));
            $meta = array('page_title' => lang('Receive_Delivery'), 'bc' => $bc);

            $this->page_construct('production_unit/receive_delivery', $meta, $this->data);      
    }
    public function ordering_history() {
        $user_id       = $this->session->userdata('user_id');
        $user_data     = $this->site->getUser($user_id); //get user information
        $location_id   = $user_data->warehouse_id;
        $location_data = $this->site->getWarehouseByIDs($location_id); //get

        $locationName  = '';
        foreach ($location_data as $location) {
           $locationName = $location->name;
        }
            $this->data['error']       = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
            $this->data['categories']  = $this->production_unit_model->getProductCategoriesList();     
            $this->data['outletName']  = $location_data;

            $bc   = array(array('link' => base_url(), 'page' => lang('Procurement_orders')), array('link' => '#', 'page' => lang('Ordering_History')));
            $meta = array('page_title' => lang('Ordering_History'), 'bc' => $bc);

            $this->page_construct('production_unit/ordering_history', $meta, $this->data);    

    }	

    function add_product($id = NULL) {

        $this->load->helper('security'); 
        $productIds = $this->input->post('productIds');
        $user_id = $this->session->userdata('user_id');
        $user_data = $this->site->getUser($user_id); // Get user information
        $location_id = $user_data->warehouse_id;
        $pds = explode(",", $location_id);
       
        if (!empty($productIds)) {
            $data = array();
            $existingProducts = array();
            $productsAdded = false; // Flag to track if products were added
            $productsExist = false; // Flag to track if products already exist
    
            foreach ($productIds as $productId) {

                // Check if the product already exists in the production unit
                $check = $this->production_unit_model->getProductStock($productId); 
                if ($check && $check->product_id == $productId) {
                    
                    $product = $this->site->getProductByID($productId);
                    if ($product) {
                        $existingProducts[] = $product->name; 
                    }
                    $productsExist = true; // Set the flag for existing products
                    // $existingProducts[] = $productId;

                } else {
                    $data[] = array(
                        'product_id' => $productId,
                        'location_id' => $pds[0],
                        'stock_quantity' => 0, 
                        'open_order_quantity' => 0, 
                        'batch_production' => 0, 
                        'retail_production' => 0, 
                        'rack' => 0, 
                        'manufacturing_cost' => 0.00, 
                    );
                }
            }
            if (!empty($data)) {
                $result = $this->production_unit_model->add_product_to_productionunit($data);
                if ($result) {
                    $productsAdded = true; // Set the flag for products added
                }
            }
            $message = '';
            if ($productsAdded) {
                $message .= 'Products added successfully. ';
            }
            if ($productsExist) {
                $message .= 'The following products already exist: ' . implode(', ', $existingProducts);
            }
            if ($message) {
                $this->session->set_flashdata('message', $message);
            }
            $this->page_construct('production_unit/add_product', $meta, $this->data);
        } else {
            $this->page_construct('production_unit/add_product', $meta, $this->data);
        }
    }

    // public function suggestions() {

    //     $searchTerm = $this->input->get('term');
    //     $productName = $this->input->get('name');
    //     $product_id = $this->input->get('product_id');
    //     $variants = 0;
    //     $productId = 0;
    //     $data = 0;
    //     $existingProducts = $this->production_unit_model->getExistingProducts();
    //     if ($product_id) {
    //         $variants = $this->production_unit_model->get_product_variants($product_id);
    //         // echo json_encode($variants);
    //     }
    
    //     if ($productName) {
    //         $productId = $this->production_unit_model->getProductIdByName($productName);
    //         // echo json_encode($productId);
    //     }
    
    //     if ($searchTerm) {
    //         $data = $this->production_unit_model->get_products($searchTerm);
    //         // echo json_encode($data);
    //     }

    // }

    public function suggestions() {
        $searchTerm = $this->input->get('term');
        $productName = $this->input->get('name');
        $product_id = $this->input->get('product_id');
        
        $suggestedProducts = [];
        $existingProducts = $this->production_unit_model->getExistingProducts();
    
        if ($product_id) {
            $variants = $this->production_unit_model->get_product_variants($product_id);
            $suggestedProducts = array_merge($suggestedProducts, $variants);
        }
        if ($productName) {
            $productId = $this->production_unit_model->getProductIdByName($productName);
            if ($productId) {
                $suggestedProducts[] = (object) ['id' => $productId]; // Ensure it has the correct structure
            }
        }
        if ($searchTerm) {
            $data = $this->production_unit_model->get_products($searchTerm);
            $suggestedProducts = array_merge($suggestedProducts, $data);
        }
        foreach ($suggestedProducts as $product) {
            $present = false; 
            foreach ($existingProducts as $c_product) {
                if ($product->id == $c_product->product_id) {
                    $present = true; 
                    break; 
                }
            }
            if (!$present) {
                $filteredSuggestions[] = $product; 
            }
        }
        echo json_encode(array_values($filteredSuggestions)); 
    }
    

////////////////////////////////////////// Order Dispatch ///////////////////////////////////////////
    public function Ready_To_Dispatch() {

        // $this->sma->checkPermissions();
        $locationNames = $this->Production_Unit_Model_New->getLocations();    
        if ($this->input->is_ajax_request()) {
            echo json_encode($locationNames);
            return;
        }
        $this->data['locationNames'] = $locationNames;
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('Production_Unit')), array('link' => '#', 'page' => lang('Ready_To_Dispatch')));
        $meta = array('page_title' => lang('Production_Unit'), 'bc' => $bc);
        $this->page_construct('production_unit/order_dispatch', $meta, $this->data);
    }   

    public function insert_data() {
        // Retrieve POST data
        $courier_val = $this->input->post('Courier');
        $courier_options = [
            '0' => 'DTDC',
            '1' => 'FedEx',
            '2' => 'DHL',
            '3' => 'Aramex'
        ];
        $courier_text = isset($courier_options[$courier_val]) ? $courier_options[$courier_val] : '';
        
        $tracking_number = $this->input->post('Tracking_Number');
        $procurement_order_ref_no = $this->input->post('refNumber');
        $procurement_orders = $this->production_unit_model->getProcurementOrderData($procurement_order_ref_no); 
        $procurement_order_id = $procurement_orders->id;

        $picked_up_by = $this->input->post('Picked_Up_by');
        $notes = $this->input->post('Notes');

        // Initialize the data array
        $data = [
            'procurement_order_ref_no' => $procurement_order_ref_no,
            'courier' => $courier_text,
            'tracking_number' => $tracking_number,
            'picked_up_by' => $picked_up_by,
            'notes' => $notes,
        ];

        // Handle file upload
        $this->load->library('upload');
        $config['upload_path'] = $this->upload_path;
        $config['allowed_types'] = $this->digital_file_types;
        // $config['max_size'] = $this->allowed_file_size;
        // $config['allowed_types'] = $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $config['max_size'] = $this->allowed_file_size = '8000';
        $config['overwrite'] = FALSE;
        $config['encrypt_name'] = TRUE;
        $this->upload->initialize($config);

        if (!empty($_FILES['attachment']['name'])) {
            if ($this->upload->do_upload('attachment')) {
                $upload_data = $this->upload->data();
                $data['attachment'] = $upload_data['file_name']; // Get the file name
            } else {
                $error = $this->upload->display_errors();
                $this->session->set_flashdata('error', $error);
                // redirect($this->agent->referrer()); // Or $_SERVER["HTTP_REFERER"]
                // redirect('production_unit/Ready_To_Dispatch',$data);
                redirect('Production_Unit/Ready_To_Dispatch');

                return; // Exit after redirect
            }
        }

        // Insert data into the database
        // $this->load->model('Production_Unit_Model');
        $inserted = $this->production_unit_model->CourierDetails($data, $procurement_order_id);
        if ($inserted) {
            $this->session->set_flashdata('message', 'Order dispatched successfully.');
        } else {
            $this->session->set_flashdata('error', 'Failed to dispatch order.');
        }

        redirect('Production_Unit/Ready_To_Dispatch');
    }

    // All Orders for Logged in Kitchen <= Production Unit
    public function getAllOrdersForLoggedInProductionUnit(){

        $user_id             = $this->session->userdata('user_id');
        $user_data           = $this->site->getUser($user_id); //get user information
        $user_location_data  = $this->site->getWarehouseBy_ID($user_data->warehouse_id); //get user information
    
        $location_name       = $this->input->get('locationName');
        $sort_order_by_time  = $this->input->get('sortOrderByTime');

        $outlets   = $this->Production_Unit_Model_New->getLocationID($location_name);    
    
        $AllOrders = $this->Production_Unit_Model_New->getAllOrdersForLoggedInProductionUnit($user_id, $outlets->id, $sort_order_by_time);    
        // Add Production unit name  to each order (for show PU name in header)
        foreach ($AllOrders as $Order) {
            $Order->ProductionUnitName = $user_location_data->name;
        }
        
        $Orders[]  = ['AllOrders' => $AllOrders];
        echo json_encode($Orders);
        return;
    }

    // Get order items for orders
    public function getOrderItemsForSelectedOrder(){

    
        $user_id            = $this->session->userdata('user_id');
        $user_data          = $this->site->getUser($user_id); //get user information
        $location_id        = $user_data->warehouse_id;
        $procurmentOrderId  = $this->input->get('procurmentOrderId'); //get order Id
        $location_name      = $this->input->get('locationName');

        if($procurmentOrderId){
            // click on specific order, show that order wise order items
            $order_items = $this->Production_Unit_Model_New->getOrderItemsForSelectedOrder($procurmentOrderId); //get order items
        
        }else{
            // get all orders data
            $AllOrders = $this->Production_Unit_Model_New->getAllOrdersForLoggedInProductionUnit($user_id);    
        
            $filteredOrders = array_filter($AllOrders, function($order) {
                return in_array($order->status, ['Open', 'Locked']);
            });
            $filteredOrders = array_values($filteredOrders);
            $order_id = $filteredOrders[0]->id;

            // show by default first order items data in grid
            $order_items = $this->Production_Unit_Model_New->getOrderItemsForSelectedOrder($order_id, null, $location_name); //get order items
            
            if($procurmentOrderId =''){
                $order_items = []; 
            }
        }

        // Add location_id to each order_item (for toggle switch)
        foreach ($order_items as $item) {
            $item->user_location_id = $location_id;
            $order_status = $item->order_status;
        }
        $OrderItems[] = ['order_items' => $order_items,'order_status' => $order_status];
    
        echo json_encode($OrderItems);
        return;
    }

    // update order data
    public function updateOrder()
    {
        $user_id            = $this->session->userdata('user_id');
        $user_data          = $this->site->getUser($user_id); //get user information
        $location_id        = $user_data->warehouse_id;

        $procurmentOrderId  = $this->input->get('procurmentOrderId'); //get order Id      
        $Locked             = $this->input->get('Locked'); //flag for update status when click on lock button
        $orderItemId        = $this->input->get('orderItemId'); //get order item id
        $isChecked          = $this->input->get('checkbox') ;//flag for update status, allot qty adn stock qty when click on checkbox
        $allotQuantityInput = $this->input->get('allotQuantityInput');
        $bulkAllotCheck     = $this->input->get('bulkAllotCheck'); // flag for bulk allot 
        $itemData           = $this->input->get('itemData'); // order item id and allot qty for bulk
        $completeOrderFlag  = $this->input->get('completeOrderFlag'); // flag for complete order button
        $deliveryDateTime   = $this->input->get('deliveryDateTime');
        
        $this->Production_Unit_Model_New->updateOrder($procurmentOrderId, $Locked, $isChecked, $allotQuantityInput, $orderItemId, $bulkAllotCheck, $itemData, $completeOrderFlag, $user_id,$deliveryDateTime); //update status after click on locked button and also click on complete order button

        // get update order and order item status to show on view
        $order_items = $this->Production_Unit_Model_New->getOrderItemsForSelectedOrder($procurmentOrderId); //get order items
        foreach($order_items as $value){
            $order_status = $value->order_status;
            $value->user_location_id = $location_id;

        }
        $updatedOrderDetails[] = ['order_items' => $order_items,'order_status' => $order_status];
        echo json_encode($updatedOrderDetails);
        return;
        
    }
    // Inventory

    function inventory($warehouse_id = NULL) {
        // $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['alert_qty'] = $this->uri->segment(4);
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = ($warehouse_id) ? $warehouse_id : $this->session->userdata('warehouse_id');
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Inventory')));
        $meta = array('page_title' => lang('products'), 'bc' => $bc);
        $this->page_construct('production_unit/inventory', $meta, $this->data);
    }

    function getProducts($warehouse_id = NULL) {
        // $this->sma->checkPermissions('index', TRUE);

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }

        $detail_link = anchor('products/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('product_details'));
        $delete_link = "<a href='#' class='tip po' title='<b>" . $this->lang->line("delete_product") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete1' id='a__$1' href='" . site_url('products/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> " . lang('delete_product') . "</a>";
        $single_barcode = anchor('products/print_barcodes/$1', '<i class="fa fa-print"></i> ' . lang('print_barcode_label'));

        $set_fav_link = "<a  id='a__$1' href='" . site_url('products/favourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('add_favourite') . "</a>";
        $unset_fav_link = "<a  id='a__$1' href='" . site_url('products/Refavourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('remove_favourite') . "</a>";

        // $single_label = anchor_popup('products/single_label/$1/' . ($warehouse_id ? $warehouse_id : ''), '<i class="fa fa-print"></i> ' . lang('print_label'), $this->popup_attributes);
        $action = '<div class="text-center"><div class="btn-group text-left">' . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">' . lang('actions') . ' <span class="caret"></span></button>
        <ul class="dropdown-menu pull-right" role="menu">
            <li>' . $detail_link . '</li>
            <li><a href="' . site_url('products/add/$1') . '"><i class="fa fa-plus-square"></i> ' . lang('duplicate_product') . '</a></li>
            <li><a href="' . site_url('products/edit/$1') . '"><i class="fa fa-edit"></i> ' . lang('edit_product') . '</a></li>';
        if ($warehouse_id) {
            $action .= '<li><a href="' . site_url('products/set_rack/$1/' . $warehouse_id) . '" data-toggle="modal" data-target="#myModal"><i class="fa fa-bars"></i> ' . lang('set_rack') . '</a></li>';
        }

        if ($this->Settings->product_batch_setting > 0) {
            $action_add_batches = '<li><a href="' . site_url('products/add_batch?p=$1') . '"  data-toggle="modal" data-target="#myModal"><i class="fa fa-list"></i>' . lang('Manage Batches') . '<img src="' . site_url('themes/default/assets/images/new.gif') . '" height="20px" alt="new"></a></li>';
        }

        $action .= '<li><a href="' . site_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/$2" data-type="image" data-toggle="lightbox"><i class="fa fa-file-photo-o"></i> ' . lang('view_image') . '</a></li>
            <li>' . $single_barcode . '</li>
                        <li class="add_fav_link">' . $set_fav_link . '</li><li  class="remove_fav_link">' . $unset_fav_link . '</li>
                        ' . $action_add_batches . '    
            <li class="divider"></li>
            <li>' . $delete_link . '</li>
            </ul>
        </div></div>';
        $this->load->library('datatables');

        if ($warehouse_id) {
            //{$this->db->dbprefix('products')}.article_code as article_code ,
            $this->datatables->select("sma_products.id as productid,  "
            . "{$this->db->dbprefix('products')}.code as code,"
            . "{$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand,"
            . "{$this->db->dbprefix('categories')}.name as cname,"
            . "{$this->db->dbprefix('units')}.name as unit, wp.rack as rack, {$this->db->dbprefix('products')}.storage_type, "
            . "FORMAT(COALESCE(quantity_sum, 0), 2) as quantity, is_featured", FALSE)
            ->from('products');

            if ($this->Settings->display_all_products) {
                $this->datatables->join("( SELECT product_id, rack, warehouse_id, SUM(quantity) as quantity_sum FROM {$this->db->dbprefix('warehouses_products')} WHERE warehouse_id IN( {$warehouse_id}) AND quantity != 0 GROUP BY product_id, rack ) wp", 'products.id=wp.product_id', 'left');
                $this->datatables->where('wp.warehouse_id IS NOT NULL');
            } else {
                $this->datatables->join("( SELECT product_id, SUM(quantity) as quantity_sum FROM {$this->db->dbprefix('warehouses_products')} WHERE warehouse_id IN({$warehouse_id}) AND quantity != 0 GROUP BY product_id ) wp", 'products.id=wp.product_id', 'left');
            }

            $this->datatables->join('categories', 'products.category_id=categories.id', 'left')
                ->join('units', 'products.sale_unit=units.id', 'left')
                ->join('brands', 'products.brand=brands.id', 'left');

            if ($this->input->get('alert_qty')) {
                $this->datatables->where('products.quantity <= products.alert_quantity');
            }
            $this->datatables->where('products.pos_combo_product', NULL);
            $this->datatables->group_by("sma_products.id, {$this->db->dbprefix('products')}.code, {$this->db->dbprefix('products')}.name, {$this->db->dbprefix('brands')}.name, {$this->db->dbprefix('categories')}.name, {$this->db->dbprefix('units')}.name, wp.rack, {$this->db->dbprefix('products')}.storage_type, is_featured");
        
        } else {

            //echo $this->input->post('aqty');
            //{$this->db->dbprefix('products')}.article_code as article_code , 
            $this->datatables->select($this->db->dbprefix('products') . ".id as productid, {$this->db->dbprefix('products')}.code as code, {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand, {$this->db->dbprefix('categories')}.name as cname, {$this->db->dbprefix('units')}.name as unit, '' as rack, {$this->db->dbprefix('products')}.storage_type, FORMAT(COALESCE(quantity, 0),2) as quantity, {$this->db->dbprefix('products')}.is_featured", FALSE)
                    ->from('products')
                    ->join('categories', 'products.category_id=categories.id', 'left')
                    ->join('units', 'products.sale_unit=units.id', 'left')
                    ->join('brands', 'products.brand=brands.id', 'left');
            if ($this->input->get('alert_qty')) { // update by SW on 8-08-2019
                $this->datatables->where('products.quantity <= products.alert_quantity');
            }
            $this->datatables->where('products.pos_combo_product', NULL);
            $this->datatables->group_by("products.id");
        }

        $this->datatables->add_column("Actions", $action, "productid, image, code, name");

        echo $this->datatables->generate();
    }
    public function download_attachment($id){
        $id =   urldecode($id);
        // $this->load->model('Production_Unit_Model');
        
        $Order = $this->production_unit_model->getOrderRefrenceNoById($id);
        $OrderRefrenceNo = $Order->procurement_order_ref_no;
        $filename = $this->production_unit_model->get_attachment($OrderRefrenceNo);
       
        if ($filename) {
            $file_path = FCPATH . 'assets/mdata/'.$this->Customer_assets.'/uploads/production_unit/' . $filename;
            if (($file_path)) {
                $this->load->helper('download');
                force_download($file_path, NULL);
            } else {
                $this->session->set_flashdata('error', lang("Attachment Not Found"));
                return redirect($_SERVER['HTTP_REFERER']);
            }
        } else {
            $this->session->set_flashdata('error', lang("Attachment Not Found"));
            return redirect($_SERVER['HTTP_REFERER']);
        }
        
    }
    function product_actions($wh = NULL) {
        if (!$this->Owner && !$this->GP['bulk_actions']) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');
        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'sync_quantity') {

                    foreach ($_POST['val'] as $id) {
                        $this->site->syncQuantity(NULL, NULL, NULL, $id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("products_quantity_sync"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'fav_products') {
                    if ($this->Products_model->productsMarkFavourite($_POST['val'])) {
                        $this->session->set_flashdata('message', $this->lang->line("Product Mark as Favourite"));
                    } else {
                        $this->session->set_flashdata('error', $this->lang->line("Please try again"));
                    }
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'delete') {

                    $this->sma->checkPermissions('delete');
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('products', 'id', $id);
                        $this->Products_model->deleteProduct($id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("products_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'labels') {

                    foreach ($_POST['val'] as $id) {
                        $row = $this->Products_model->getProductByID($id);
                        $selected_variants = FALSE;
                        if ($variants = $this->Products_model->getProductOptions($row->id)) {
                            foreach ($variants as $variant) {
                                $selected_variants[$variant->id] = $variant->quantity > 0 ? 1 : 0;
                            }
                        }
                        $pr[$row->id] = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => $row->quantity, 'variants' => $variants, 'selected_variants' => $selected_variants);
                    }

                    $this->data['items'] = isset($pr) ? json_encode($pr) : FALSE;
                    $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
                    $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('print_barcodes')));
                    $meta = array('page_title' => lang('print_barcodes'), 'bc' => $bc);
                    $this->page_construct('products/print_barcodes', $meta, $this->data);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:F1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:F1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Products');
                    $this->excel->getActiveSheet()->setTitle('Products');

                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('brand'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('category_code'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('sale') . ' ' . lang('unit_code'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('quantity'));

                    $row = 3;
                    $total_quantity = 0;
                    foreach ($_POST['val'] as $id) {
                        $product = $this->Products_model->getProductDetail($id);
                        $brand = $this->site->getBrandByID($product->brand);
                        if ($units = $this->site->getUnitsByBUID($product->unit)) {
                            foreach ($units as $u) {
                                if ($u->id == $product->unit) {
                                    $base_unit = $u->code;
                                }
                                if ($u->id == $product->sale_unit) {
                                    $sale_unit = $u->code;
                                }
                                if ($u->id == $product->purchase_unit) {
                                    $purchase_unit = $u->code;
                                }
                            }
                        } else {
                            $base_unit = '';
                            $sale_unit = '';
                            $purchase_unit = '';
                        }
                        $variants = $this->Products_model->getProductOptions($id);
                        $product_variants = '';
                        if ($variants) {
                            foreach ($variants as $variant) {
                                $product_variants .= trim($variant->name) . '|';
                            }
                        }
                        $quantity = $product->quantity;
                        if ($wh) {
                            if ($wh_qty = $this->Products_model->getProductQuantity($id, $wh)) {
                                $quantity = $wh_qty['quantity'];
                            } else {
                                $quantity = 0;
                            }
                        }
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $product->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $product->code);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, ($brand ? $brand->name : ''));
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $product->category_code);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $sale_unit);
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $quantity);
                        $total_quantity += $quantity;

                        $row++;
                    }
                    $styleArray = [
                        'borders' => [
                            'allborders' => [
                                'style' => PHPExcel_Style_Border::BORDER_MEDIUM,
                            ],
                        ],
                    ];
                    
                    $this->excel->getActiveSheet()->getStyle("F" . $row)->applyFromArray($styleArray);
                    $this->excel->getActiveSheet()->SetCellValue('F' . $row, $total_quantity);
                        // Apply border to the cell
                        $styleArray = [
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => PHPExcel_Style_Border::BORDER_THIN,
                                    'color' => ['argb' => 'FF000000'], // Black color
                                ],
                            ],
                        ];

                    $this->excel->getActiveSheet()->getStyle('F' . $row)->applyFromArray($styleArray);
                    
                    // $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
                    // $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    // $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
                    // $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
                    // $this->excel->getActiveSheet()->getColumnDimension('N')->setWidth(40);
                    // $this->excel->getActiveSheet()->getColumnDimension('O')->setWidth(30);
                    // $this->excel->getActiveSheet()->getColumnDimension('P')->setWidth(30);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'Inventory_' . date('Y_m_d_H_i_s');
                    
                    if ($this->input->post('form_action') == 'export_pdf') {
                        $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                        $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                        require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                        $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                        $rendererLibrary = 'MPDF';
                        $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                        if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                            die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
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
                $this->session->set_flashdata('error', $this->lang->line("no_product_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }
}
?>