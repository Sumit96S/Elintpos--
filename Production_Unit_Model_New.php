<?php
class Production_Unit_Model_New extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->created_by      = $this->session->userdata('user_id');
       
    }
    // get location data
    public function getLocations() {

        $this->db->where_in('location_type', array('3', '4')); //location_type != Production Unit and HO, means show only Retail Outlet and Stockist
        $this->db->order_by('name', 'ASC');
        $q = $this->db->get('sma_warehouses');
            if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    // get location id for filter 
    public function getLocationID($locationName) {
       
        $q = $this->db->get_where('sma_warehouses', ['name' => $locationName], 1);
        if ($q->num_rows() > 0) {
            $data = $q->row();
            return $data;
        }
        return FALSE;
    }

    // All Orders for Logged in Kitchen <= Production Unit
    public function getAllOrdersForLoggedInProductionUnit($user_id, $outletId, $sort_order_by_time) {

        $this->db->distinct();
        $this->db->select('sma_procurement_orders.*');
        $this->db->from('sma_procurement_orders');
        $this->db->join('sma_procurement_order_items', 'sma_procurement_orders.id = sma_procurement_order_items.procurement_orders_id', 'left');
        $this->db->join('sma_users', 'sma_users.warehouse_id = sma_procurement_order_items.production_unit_id', 'left');
        $this->db->where('sma_users.id', $user_id);

        // filter orders by outlet
        if($outletId){
            $this->db->where('sma_procurement_orders.location_id', $outletId);
        }
        if($sort_order_by_time){
            if ($sort_order_by_time == 'Newest') {
                $this->db->order_by('sma_procurement_orders.order_creation_date', 'desc');
            }else{
                $this->db->order_by("sma_procurement_orders.order_creation_date", 'asc');
            }
        }else{
            $this->db->order_by("sma_procurement_orders.id", 'asc');
        }
        

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->result();
        }
        return FALSE;
    }

    // completed Orders for Logged in Kitchen <= Production Unit
    public function getCompleteOrdersForLoggedInProductionUnit($user_id, $outletId, $sort_order_by_time, $order_dispatch) {

        $this->db->distinct();
        $this->db->select('sma_procurement_orders.*');
        $this->db->from('sma_procurement_orders');
        $this->db->join('sma_procurement_order_items', 'sma_procurement_orders.id = sma_procurement_order_items.procurement_orders_id', 'left');
        $this->db->join('sma_users', 'sma_users.warehouse_id = sma_procurement_order_items.production_unit_id', 'left');
        $this->db->where('sma_users.id', $user_id);
        // $this->db->where('sma_procurement_orders.status', 'Completed');

        // filter orders by outlet
        if($outletId){
            $this->db->where('sma_procurement_orders.location_id', $outletId);
        }
        if($order_dispatch == '1'){
            $this->db->where('sma_procurement_orders.status', 'Dispatched');
        }else{
            $this->db->where('sma_procurement_orders.status', 'Completed');
        }
        if($sort_order_by_time){
            if ($sort_order_by_time == 'Newest') {
                $this->db->order_by('sma_procurement_orders.order_creation_date', 'desc');
            }else{
                $this->db->order_by("sma_procurement_orders.order_creation_date", 'asc');
            }
        }else{
            $this->db->order_by("sma_procurement_orders.id", 'asc');
        }
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->result();
        }
        return FALSE;
    }

    // get order items against order

    public function getOrderItemsForSelectedOrder($procurementOrderId, $orderItemId, $location_name) {

        $this->db->select('poi.id as itemId, poi.procurement_orders_id, poi.product_id, poi.product_name, poi.order_quantity, poi.allot_quantity, poi.received_quantity, poi.product_code, poi.item_status, poi.production_unit_name, po.procurement_order_ref_no, po.location_name, po.location_code, po.status as order_status, productionunit_products.stock_quantity as stock_quantity, productionunit_products.open_order_quantity, po.note, poi.production_unit_id, po.order_creation_date');
        $this->db->from('sma_procurement_order_items as poi');
        $this->db->join('sma_procurement_orders as po', 'poi.procurement_orders_id = po.id', 'left');
        $this->db->join('products ', 'products.id = poi.product_id', 'left');
        $this->db->join('productionunit_products', 'productionunit_products.product_id = products.id', 'left');
    
        if($procurementOrderId){
            $this->db->where('poi.procurement_orders_id', $procurementOrderId);
        }
        if($orderItemId){
            $this->db->where('poi.id', $orderItemId);
        }
        // filter : sort location name wise order items
        if($location_name){
            $this->db->where('po.location_name', $location_name);
        }
       
        //$this->db->order_by("po.id", "desc");

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                // $row->user_location_id = $location_id; // Add the location_id to each row object
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    //update order details
    public function updateOrder($procurmentOrderId, $Locked, $isChecked, $allotQuantityInput, $orderItemId, $bulkAllotCheck, $itemData, $completeOrderFlag, $user_id,$deliveryDateTime) {

        if (!empty($deliveryDateTime)) {
            $this->db->update('sma_procurement_orders', array('planned_delivery_datetime' => $deliveryDateTime), array('id' => $procurmentOrderId));
       exit;
        }
        $order_items =  $this->getOrderItemsForSelectedOrder($procurmentOrderId); // get order id wise order items

        // when click on lock button, update status to 'locked'
        if($Locked == 'true'){
            $this->db->update('sma_procurement_orders', array('status' => 'Locked'), array('id' => $procurmentOrderId));
            $this->db->update('sma_procurement_order_items', array('item_status' => 'Locked'), array('procurement_orders_id' => $procurmentOrderId));
        }
        // update order item status, allot qty and stock qty
        if($isChecked){
            if ($isChecked === "Checked") {

                $data = array(
                    'allot_quantity' => $allotQuantityInput,
                    'item_status'    => 'Committed',
                    'alloted_by'     => $user_id

                );
                $this->db->where('id', $orderItemId);
                $this->db->update('sma_procurement_order_items', $data);
            }else{
                
                $data = array(
                    'allot_quantity' => 0,
                    'item_status'    => 'Locked',
                    'alloted_by'     => $user_id

                );
                $this->db->where('id', $orderItemId);
                $this->db->update('sma_procurement_order_items', $data);
            }
            $this->updateProductStock($allotQuantityInput, $orderItemId, $isChecked);
        }
        // update order item status, allot qty and stock qty for bulk allot
        if($bulkAllotCheck){
          
            if(!empty($itemData)){

                foreach ($itemData as $item) {
                 
                    $order_item_id  = $item['orderItemId'];
                    $allot_quantity_input = $item['allotQuantity'];

                    if($bulkAllotCheck === "allChecked"){

                        $data = array(
                            'allot_quantity' => $allot_quantity_input,
                            'item_status'    => 'Committed'
                        );
                        $this->db->where('id', $order_item_id);
                        $this->db->update('sma_procurement_order_items', $data);
    
                    }else {
                        $data = array(
                            'allot_quantity' => '0',
                            'item_status' => 'Locked'
                        );
                        
                        $this->db->where('id', $order_item_id);
                        $this->db->update('sma_procurement_order_items', $data);
                    }
                }
                $this->updateProductStock($allotQuantityInput, $orderItemId, $isChecked, $bulkAllotCheck, $itemData);
            }
        }
        // complete order functionality
        if($completeOrderFlag){

            $order_items =  $this->getOrderItemsForSelectedOrder($procurmentOrderId); // get order item id wise order items data
            $itemStatus = array('Completed', 'partially_completed', 'Pending');  // Array to store item statuses
            $CheckCompleteOrder = true;
            foreach($order_items as $data){
            
                $orderItemId           = $data->itemId;
                $item_status           = $data->item_status;
                $allot_quantity        = $data->allot_quantity;
                $order_quantity        = $data->order_quantity;

                if($item_status === 'Committed')
                {
                    if($allot_quantity == $order_quantity){
                        $this->db->update('sma_procurement_order_items', array('item_status' => 'Completed'), array('id' => $orderItemId));
                        $item_status  = 'Completed';
                    }elseif($allot_quantity < $order_quantity && $allot_quantity != 0){
                        $this->db->update('sma_procurement_order_items', array('item_status' => 'partially_completed'), array('id' => $orderItemId));
                        $item_status  = 'partially_completed';
                    }
                    else{
                        $this->db->update('sma_procurement_order_items', array('item_status' => 'Pending'), array('id' => $orderItemId));
                        $item_status  = 'Pending';
                    }
                }
                // After updating, check if the status is valid
                if (!in_array($item_status, $itemStatus)) {
                    $CheckCompleteOrder = false;
                }
            }
            // Update order status if all items are completed
            if ($CheckCompleteOrder) {
                $this->db->update('sma_procurement_orders', array('status' => 'Completed'), array('id' => $procurmentOrderId));
            }
        }
    }
    // update stock quantity when click on checkbox in grid
    public function updateProductStock($allotQuantityInput, $orderItemId, $isChecked, $bulkAllotCheck, $itemData) {

        // update stock for specific order item
        if($isChecked){

            $order_items =  $this->getOrderItemsForSelectedOrder($procurmentOrderId, $orderItemId); // get order item id wise order items data
            foreach($order_items as $data){
            
                $product_id            = $data->product_id;
                $stock_quantity        = $data->stock_quantity;
            }
            $new_stock_quantity = $stock_quantity - $allotQuantityInput;  
            $old_stock_quantity = $stock_quantity + $allotQuantityInput;  

            if($isChecked === "Checked"){

                $this->db->where('product_id', $product_id);
                $this->db->update('sma_productionunit_products', array('stock_quantity' => $new_stock_quantity));
            }else{

                $this->db->where('product_id', $product_id);
                $this->db->update('sma_productionunit_products', array('stock_quantity' => $old_stock_quantity));
            }
        }
        // update stock for bulk order items
        if($bulkAllotCheck){
            if(!empty($itemData)){
                foreach ($itemData as $item) {

                    $order_item_id  = $item['orderItemId'];
                    $allot_quantity_input = $item['allotQuantity'];

                    $order_items =  $this->getOrderItemsForSelectedOrder($procurmentOrderId, $order_item_id); // get order item id wise order items data
                    foreach($order_items as $data){
           
                        $product_id            = $data->product_id;
                        $stock_quantity        = $data->stock_quantity;
                    }
                    $new_stock_quantity = $stock_quantity - $allot_quantity_input;  
                    $old_stock_quantity = $stock_quantity + $allot_quantity_input;

                    if($bulkAllotCheck === "allChecked"){
                   
                        $this->db->where('product_id', $product_id);
                        $this->db->update('sma_productionunit_products', array('stock_quantity' => $new_stock_quantity));
                    }else{
                         
                        $this->db->where('product_id', $product_id);
                        $this->db->update('sma_productionunit_products', array('stock_quantity' => $old_stock_quantity));
                    }
                }
            }
        }
    }

    
      
}
?>