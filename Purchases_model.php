<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Purchases_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function getProductNames($term, $limit = 20) {
        if (strpos($term, '-') !== false) {
                $analyzed = $this->sma->analyze_term($term[0]);
                $term = $analyzed['term'];
                $this->db->where(['code'=>$term]);
        } else {
            $this->db->where("type = 'standard' AND (IF(name LIKE '%" . $term . "%',name LIKE '%" . $term . "%', Replace(coalesce(name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%' ) OR code LIKE '%" . $term . "%' OR article_code LIKE '%" . $term . "%'  OR  concat(name, ' (', code, ')') LIKE '%" . $term . "%' OR article_code LIKE '%" . $term . "%'  OR  concat(name, ' (', code, ')') LIKE '%" . $term . "%' OR article_code LIKE '%" . $term . "%'  OR  concat(name, ' (', code, ')') LIKE '%" . $term . "%')");
        }
        $this->db->limit($limit);
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
                
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllProducts() {
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductByID($id) {
        $q = $this->db->get_where('products', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductsByCode($code) {
        $this->db->select('*')->from('products')->like('code', $code, 'both');
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
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

    public function getAllPurchases() {
        $q = $this->db->get('purchases');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getAllPurchaseItems($purchase_id, $OrderByname = '') {
        $this->db->select('purchase_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.unit, products.details as details, product_variants.name as variant, products.image as image,products.article_code, product_batches.batch_no')
                ->join('products', 'products.id=purchase_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=purchase_items.option_id', 'left')
                ->join('tax_rates', 'tax_rates.id=purchase_items.tax_rate_id', 'left')
                ->join('product_batches', 'products.id=product_batches.product_id', 'left')
                ->group_by('purchase_items.id');
        if ($OrderByname != '')
            $this->db->order_by($OrderByname, 'asc');
        else
            $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('purchase_items', array('purchase_id' => $purchase_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getItemByID($id) {
        $q = $this->db->get_where('purchase_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getTaxRateByName($name) {
        $q = $this->db->get_where('tax_rates', array('name' => $name), 1);
        if ($q->num_rows() > 0) {

            return $q->row();
        }
        return FALSE;
    }

    public function getPurchaseByID($id) {
        $q = $this->db->get_where('purchases', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
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

    public function addProductOptionQuantity($option_id, $warehouse_id, $quantity, $product_id) {
        if ($option = $this->getProductWarehouseOptionQty($option_id, $warehouse_id)) {
            $nq = $option->quantity + $quantity;
            if ($this->db->update('warehouses_products_variants', array('quantity' => $nq), array('option_id' => $option_id, 'warehouse_id' => $warehouse_id))) {
                return TRUE;
            }
        } else {
            if ($this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $quantity))) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function resetProductOptionQuantity($option_id, $warehouse_id, $quantity, $product_id) {
        if ($option = $this->getProductWarehouseOptionQty($option_id, $warehouse_id)) {
            $nq = $option->quantity - $quantity;
            if ($this->db->update('warehouses_products_variants', array('quantity' => $nq), array('option_id' => $option_id, 'warehouse_id' => $warehouse_id))) {
                return TRUE;
            }
        } else {
            $nq = 0 - $quantity;
            if ($this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $nq))) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function getOverSoldCosting($product_id) {
        $q = $this->db->get_where('costing', array('overselling' => 1));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function addPurchaseItemsByCsv($items=[],$pm=[]) {
         
        if ($items) {
                
            if($this->db->insert_batch('purchase_items', $items)){
                
                //$this->db->affected_rows();
                $i=0; 
                foreach ($items as $key => $item) {
                    /*
                    if ($data['status'] == 'received') {
                        // Action Log 
                        $DatalogArr = array('purchase_id' => $purchase_id, 'purchase_items' => $item);
                        $DataLog = array(
                            'action_type'           => 'Add Purchases',
                            'product_id'            => $item['product_id'],
                            'option_id'             => $item['option_id'],
                            'batch_number'          => $item['batch_number'] ? $item['batch_number'] : NULL,
                            'quantity'              => $item['quantity_received'],
                            'cr_dr'                 => 'cr',
                            'warehouse_id'          => $data['warehouse_id'],
                            'action_reff_id'        => "sma_purchases.id:$purchase_id",
                            'action_affected_data'  => json_encode($DatalogArr),
                            'action_comment'        => 'Products Purchesed',
                            'is_inventory'          => 1,
                        );
                        $this->sma->setUserActionLog($DataLog);
                        // End Action Log 
                    } */
                   
                    if ($this->Settings->update_cost) {
                        if ($item['option_id']) {
                            $this->db->update('product_variants', ['cost' => $item['real_unit_cost'],'price' => $pm[$i]['price']], ['id' => $item['option_id'], 'product_id' => $item['product_id'] ]);
                            $this->db->update('products', ['mrp' => $pm[$i]['mrp']],['id' => $item['product_id']] );
                        } else {
                            $this->db->update('products', ['cost' => $item['real_unit_cost'],'price' => $pm[$i]['price'],'mrp' => $pm[$i]['mrp']],['id' => $item['product_id']] );
                        }
                    }
                    $i++;
                    if ($data['status'] == 'received' || $data['status'] == 'returned') {
                        $this->updateAVCO( ['product_id' => $item['product_id'], 'warehouse_id' => $item['warehouse_id'], 'quantity' => $item['quantity'], 'cost' => $item['real_unit_cost']]);
                    }
                }//end foreach
              

                return true;
            }//end if Insert Batch
        }
        return false;
    }
    
    
    public function addPurchase($data, $items=[]) {
         
        if ($this->db->insert('purchases', $data)) {
            $purchase_id = $this->db->insert_id();

            if ($this->site->getReference('po') == $data['reference_no']) {
                $this->site->updateReference('po');
            }
            
            if ($data['status'] == 'returned' && $this->site->getReference('rep') == $data['return_purchase_ref']) {
                $this->site->updateReference('rep');
            }
            foreach ($items as $item) {
                $item['purchase_id'] = $purchase_id;
                if ($this->db->insert('purchase_items', $item)) {
                    $purchase_item_id = $this->db->insert_id();
                    $purchase_item_ids[] = $purchase_item_id;
                  
                    $purchaseitemRecords = $this->db->select('*')->from('purchase_items')->where_in('id', $purchase_item_ids)->group_by('id') ->get()->result_array(); 
                    $purchaseitemRecord = [];  
                    foreach ($purchaseitemRecords as $record) {
                        $purchaseitemRecord[] = $record;
                    }
                    if ($data['status'] == 'received') {
                        $DatalogArr = array('purchase_item_id' => $purchase_item_ids, 'purchase_items' => $item);
                        $DataLog = array(
                            'action_type'           => 'Add Purchases',
                            'product_id'            => $item['product_id'],
                            'option_id'             => $item['option_id'],
                            'batch_number'          => $item['batch_number'] ? $item['batch_number'] : NULL,
                            'quantity'              => $item['quantity_received'],
                            'cr_dr'                 => 'cr',
                            'warehouse_id'          => $data['warehouse_id'],
                            'action_reff_id'        => "sma_purchases.id:$purchase_id",
                            'action_affected_data'  => json_encode($DatalogArr),
                            'action_comment'        => 'Products Purchesed',
                            'is_inventory'          => 1,
                        );
                        $this->sma->setUserActionLog($DataLog);
                    }

                    if ($this->Settings->update_cost) {
                        if ($item['option_id']) {
                            $this->db->update('product_variants', ['cost' => $item['real_unit_cost']], ['id' => $item['option_id'], 'product_id' => $item['product_id'] ]);
                        } else {
                            $this->db->query("UPDATE sma_products SET cost=" . $item['real_unit_cost'] . " WHERE sma_products.id=" . $item['product_id']);
                        }
                    }
                    
                    if ($data['status'] == 'received' || $data['status'] == 'returned') {
                        $warehouseproductDetails = $this->updateAVCO([
                            'product_id'   => $item['product_id'],
                            'warehouse_id' => $item['warehouse_id'],
                            'quantity'     => $item['quantity'],
                            'cost'         => $item['real_unit_cost'],
                        ]);
                        $warehouseproductvariantsDetails = $this->updateVariantsAVCO([
                            'product_id'   => $item['product_id'],
                            'warehouse_id' => $item['warehouse_id'],
                            'quantity'     => $item['quantity'],
                            'option_id'    => $item['option_id'],
                            'cost'         => $item['real_unit_cost'],
                        ]);
                        $this->site->syncQuantity($NULL, $purchase_id);
                        if ($data['status'] == 'received') {
                            $productdata = array(
                                'product_id'            => $item['product_id'],
                                'option_id'             => $item['option_id'],
                                'batch_number'          => $item['batch_number'] ? $item['batch_number'] : NULL,
                            );
                            $productdata['purchase_id'] = $purchase_id;
                            $this->insertProductCostingAfterPurchase($productdata, $warehouseproductDetails,$purchaseitemRecord,$warehouseproductvariantsDetails);
                        }
                        
                    }
                } 
            } 
      
            if ($data['status'] == 'returned') {
                $this->db->update('purchases', array('return_purchase_ref' => $data['return_purchase_ref'], 'surcharge' => $data['surcharge'], 'return_purchase_total' => $data['grand_total'], 'return_id' => $purchase_id), array('id' => $data['purchase_id']));
            }

            if ($data['status'] == 'received' || $data['status'] == 'returned') {
                
                  
                // Urbanpiper Stock Manage 
                /*if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                }*/

            }
            return true;
        }
        return false;
    }


    public function updatePurchase($id, $data, $items = []) {
                 
        $opurchase  = $this->getPurchaseByID($id);
        $oitems     = $this->getAllPurchaseItems($id);

        if ($this->db->update('purchases', $data, ['id' => $id])) {
            $purchase_id = $id;
            $this->sma->storeDeletedData('purchases', 'id', $id, 0);
            $this->db->delete('purchase_items', array('purchase_id' => $id));
            $this->db->delete('purchase_items_tax', array('purchase_id' => $id));
            $this->db->delete('user_action_logs', array('action_reff_id' => "sma_purchases.id:$purchase_id"));

            foreach ($items as $item) {
                $item['purchase_id'] = $id;
                if ($this->db->insert('purchase_items', $item)) {
                    $purchase_item_id = $this->db->insert_id();

                    if (in_array($data['status'], ['received', 'partial'])) {
                        /* Action Log */
                        $DatalogArr = array('purchase_item_id' => $purchase_item_id, 'purchase_items' => $item);
                        $DataLog = array(
                            'action_type' => 'Edit Purchases',
                            'product_id'  => $item['product_id'],
                            'option_id'   => $item['option_id'],
                            'batch_number'=> $item['batch_number'] ? $item['batch_number'] : NULL,
                            'quantity'    => $item['quantity_received'],
                            'action_reff_id' => "sma_purchases.id:$purchase_id",
                            'action_affected_data' => json_encode($DatalogArr),
                            'action_comment' => 'Products Purchesed',
                        );
                        $this->sma->setUserActionLog($DataLog);
                        /* End Action Log */
                    }

                    if ($data['status'] == 'received' || $data['status'] == 'partial') {
                        $this->updateAVCO(['product_id' => $item['product_id'], 'warehouse_id' => $item['warehouse_id'], 'quantity' => $item['quantity'], 'cost' => $item['real_unit_cost']]);
                    }
                }//end insert Items
            }//end foreach.

            $this->site->syncQuantity(NULL, NULL, $oitems);
            if ($data['status'] == 'received' || $data['status'] == 'partial') {
                $this->site->syncQuantity(NULL, $id);
                foreach ($oitems as $oitem) {
                    $this->updateAVCO(['product_id' => $oitem->product_id, 'warehouse_id' => $oitem->warehouse_id, 'quantity' => (0 - $oitem->quantity), 'cost' => $oitem->real_unit_cost]);
                }
 
                // Urbanpiper Stock Manage 
                /*if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                }*/
                
            }

            $this->site->syncPurchasePayments($id);
            return true;
        }

        return false;
    }

    public function updateStatus($id, $status, $note) {
        // $purchase = $this->getPurchaseByID($id);
        $items = $this->site->getAllPurchaseItems($id);

        if ($this->db->update('purchases', ['status' => $status, 'note' => $note], ['id' => $id] )) {
            foreach ($items as $item) {
                $qb = $status == 'completed' ? ($item->quantity_balance + ($item->quantity - $item->quantity_received)) : $item->quantity_balance;
                $qr = $status == 'completed' ? $item->quantity : $item->quantity_received;
                $this->db->update('purchase_items', array('status' => $status, 'quantity_balance' => $qb, 'quantity_received' => $qr), array('id' => $item->id));
                $this->updateAVCO(array('product_id' => $item->product_id, 'warehouse_id' => $item->warehouse_id, 'quantity' => $item->quantity, 'cost' => $item->real_unit_cost));
            }
            $this->site->syncQuantity(NULL, NULL, $items);

            // Urbanpiper Stock Manage 
               /* if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $werehouseid = $this->db->select('warehouse_id')->where(['id' => $id])->get('sma_purchases'); 
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct->product_id;
                    }
                    $this->UPM->Product_out_of_stock($productids, $werehouseid->warehouse_id);
                }*/
            return true;
        }
        return false;
    }
    
    public function setPurchaseDeleted($purchase) {
        
        $data = [
            'status'            =>  'deleted',
            'payment_status'    =>  'deleted',
            'total'             =>  0,
            'product_discount'  =>  0,
            'order_discount_id' =>  NULL,
            'order_discount'    =>  0,
            'total_discount'    =>  0,
            'product_tax'       =>  0,
            'order_tax_id'      =>  0,
            'order_tax'         =>  0,
            'total_tax'         =>  0,
            'shipping'          =>  0,
            'grand_total'       =>  0,
            'paid'              =>  0,
            'payment_term'      =>  0,
            'due_date'                  => NULL,            
            'rounding'                  =>  0,
            'cgst'                      =>  0,
            'sgst'                      =>  0,
            'igst'                      =>  0,
            'return_purchase_total'     =>  0,
            'return_purchase_ref'       => NULL,
            'purchase_id'               => NULL,
            'return_id'                 => NULL,
        ];
        
        if($purchase->status == 'returned'){             
            $this->db->update('purchases', ['return_purchase_ref'=>NULL, 'return_purchase_total'=>0, 'return_id'=>NULL], ['id' => $purchase->purchase_id]);
        } elseif( $purchase->return_id ) {
            $this->db->update('purchases', $data, ['id' => $purchase->return_id] );
        }
        
        if ($this->db->update('purchases', $data, ['id' => $purchase->id] )) {
            return TRUE;
        }
       
    }
    
    public function deletePurchase($id) {
        $purchase = $this->getPurchaseByID($id);
        $purchase_items = $this->site->getAllPurchaseItems($id);
        if ($this->db->delete('purchase_items', array('purchase_id' => $id)) && $this->setPurchaseDeleted($purchase) ) {
            $this->db->delete('purchase_items_tax', array('purchase_id' => $id));
            $this->db->delete('payments', array('purchase_id' => $id));
            if ($purchase->status == 'received' || $purchase->status == 'partial') {
                foreach ($purchase_items as $oitem) {
                    $this->updateAVCO(array('product_id' => $oitem->product_id, 'warehouse_id' => $oitem->warehouse_id, 'quantity' => (0 - $oitem->quantity), 'cost' => $oitem->real_unit_cost));
                    $received = $oitem->quantity_received ? $oitem->quantity_received : $oitem->quantity;
                    if ($oitem->quantity_balance < $received) {
                        $clause = array('purchase_id' => NULL, 'transfer_id' => NULL, 'product_id' => $oitem->product_id, 'warehouse_id' => $oitem->warehouse_id, 'option_id' => $oitem->option_id);
                        if ($pi = $this->site->getPurchasedItem($clause)) {
                            $quantity_balance = $pi->quantity_balance + ($oitem->quantity_balance - $received);
                            $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), $clause);
                        } else {
                            $clause['quantity'] = 0;
                            $clause['item_tax'] = 0;
                            $clause['quantity_balance'] = ($oitem->quantity_balance - $received);
                            $this->db->insert('purchase_items', $clause);
                        }
                    }
                }
            }
            $this->site->syncQuantity(NULL, NULL, $purchase_items);
            return true;
        }
        return FALSE;
    }

    public function getWarehouseProductQuantity($warehouse_id, $product_id) {
        $q = $this->db->get_where('warehouses_products', array('warehouse_id' => $warehouse_id, 'product_id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getWarehouseProductVariantsQuantity($warehouse_id, $product_id,$optionid) {
        $q = $this->db->get_where('warehouses_products_variants', array('warehouse_id' => $warehouse_id, 'product_id' => $product_id,'option_id' => $optionid), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getPurchasePayments($purchase_id) {
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('payments', array('purchase_id' => $purchase_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getPaymentByID($id) {
        $q = $this->db->get_where('payments', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getPaymentsForPurchase($purchase_id) {
        $this->db->select('payments.date, payments.paid_by, payments.amount, payments.reference_no, users.first_name, users.last_name, type')
                ->join('users', 'users.id=payments.created_by', 'left');
        $q = $this->db->get_where('payments', array('purchase_id' => $purchase_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function addPayment($data = array()) {
        if ($this->db->insert('payments', $data)) {
            if ($this->site->getReference('pay') == $data['reference_no']) {
                $this->site->updateReference('ppay');
            }
            $this->site->syncPurchasePayments($data['purchase_id']);
            return true;
        }
        return false;
    }

    public function updatePayment($id, $data = array()) {
        if ($this->db->update('payments', $data, array('id' => $id))) {
            $this->site->syncPurchasePayments($data['purchase_id']);
            return true;
        }
        return false;
    }

    public function deletePayment($id) {
        $opay = $this->getPaymentByID($id);
        if ($this->db->delete('payments', array('id' => $id))) {
            $this->site->syncPurchasePayments($opay->purchase_id);
            return true;
        }
        return FALSE;
    }

    public function getProductOptions($product_id) {
        $q = $this->db->get_where('product_variants', array('product_id' => $product_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductVariantByName($name, $product_id) {
        $q = $this->db->get_where('product_variants', array('name' => $name, 'product_id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getExpenseByID($id) {
        $q = $this->db->get_where('expenses', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addExpense($data = array()) {
        if ($this->db->insert('expenses', $data)) {
            if ($this->site->getReference('ex') == $data['reference']) {
                $this->site->updateReference('ex');
            }
            return true;
        }
        return false;
    }

    public function updateExpense($id, $data = array()) {
        if ($this->db->update('expenses', $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function deleteExpense($id) {
        if ($this->db->delete('expenses', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getQuoteByID($id) {
        $q = $this->db->get_where('quotes', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllQuoteItems($quote_id) {
        $q = $this->db->get_where('quote_items', array('quote_id' => $quote_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getReturnByID($id) {
        $q = $this->db->get_where('return_purchases', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllReturnItems($return_id) {
        $this->db->select('return_purchase_items.*, products.details as details, product_variants.name as variant')
                ->join('products', 'products.id=return_purchase_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=return_purchase_items.option_id', 'left')
                ->group_by('return_purchase_items.id')
                ->order_by('id', 'asc');
        $q = $this->db->get_where('return_purchase_items', array('return_id' => $return_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getPurcahseItemByID($id) {
        $q = $this->db->get_where('purchase_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function returnPurchase($data = array(), $items = array()) {

        $purchase_items = $this->site->getAllPurchaseItems($data['purchase_id']);

        if ($this->db->insert('return_purchases', $data)) {
            $return_id = $this->db->insert_id();
            if ($this->site->getReference('rep') == $data['reference_no']) {
                $this->site->updateReference('rep');
            }
            foreach ($items as $item) {
                $item['return_id'] = $return_id;
                $this->db->insert('return_purchase_items', $item);

                if ($purchase_item = $this->getPurcahseItemByID($item['purchase_item_id'])) {
                    if ($purchase_item->quantity == $item['quantity']) {
                        $this->db->delete('purchase_items', array('id' => $item['purchase_item_id']));
                    } else {
                        $nqty = $purchase_item->quantity - $item['quantity'];
                        $bqty = $purchase_item->quantity_balance - $item['quantity'];
                        $rqty = $purchase_item->quantity_received - $item['quantity'];
                        $tax = $purchase_item->unit_cost - $purchase_item->net_unit_cost;
                        $discount = $purchase_item->item_discount / $purchase_item->quantity;
                        $item_tax = $tax * $nqty;
                        $item_discount = $discount * $nqty;
                        $subtotal = $purchase_item->unit_cost * $nqty;
                        $this->db->update('purchase_items', array('quantity' => $nqty, 'quantity_balance' => $bqty, 'quantity_received' => $rqty, 'item_tax' => $item_tax, 'item_discount' => $item_discount, 'subtotal' => $subtotal), array('id' => $item['purchase_item_id']));
                    }
                }
            }
            $this->calculatePurchaseTotals($data['purchase_id'], $return_id, $data['surcharge']);
            $this->site->syncQuantity(NULL, NULL, $purchase_items);
            $this->site->syncQuantity(NULL, $data['purchase_id']);


              // Urbanpiper Stock Manage 
               /* if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                }*/

            return true;
        }
        return false;
    }

    public function calculatePurchaseTotals($id, $return_id, $surcharge) {
        $purchase = $this->getPurchaseByID($id);
        $items = $this->getAllPurchaseItems($id);
        if (!empty($items)) {
            $total = 0;
            $product_tax = 0;
            $order_tax = 0;
            $product_discount = 0;
            $order_discount = 0;
            foreach ($items as $item) {
                $product_tax += $item->item_tax;
                $product_discount += $item->item_discount;
                $total += $item->net_unit_cost * $item->quantity;
            }
            if ($purchase->order_discount_id) {
                $percentage = '%';
                $order_discount_id = $purchase->order_discount_id;
                $opos = strpos($order_discount_id, $percentage);
                if ($opos !== false) {
                    $ods = explode("%", $order_discount_id);
                    $order_discount = (($total + $product_tax) * (Float) ($ods[0])) / 100;
                } else {
                    $order_discount = $order_discount_id;
                }
            }
            if ($purchase->order_tax_id) {
                $order_tax_id = $purchase->order_tax_id;
                if ($order_tax_details = $this->site->getTaxRateByID($order_tax_id)) {
                    if ($order_tax_details->type == 2) {
                        $order_tax = $order_tax_details->rate;
                    }
                    if ($order_tax_details->type == 1) {
                        $order_tax = (($total + $product_tax - $order_discount) * $order_tax_details->rate) / 100;
                    }
                }
            }
            $total_discount = $order_discount + $product_discount;
            $total_tax = $product_tax + $order_tax;
            $grand_total = $total + $total_tax + $purchase->shipping - $order_discount + $surcharge;
            $data = array(
                'total' => $total,
                'product_discount' => $product_discount,
                'order_discount' => $order_discount,
                'total_discount' => $total_discount,
                'product_tax' => $product_tax,
                'order_tax' => $order_tax,
                'total_tax' => $total_tax,
                'grand_total' => $grand_total,
                'return_id' => $return_id,
                'surcharge' => $surcharge
            );

            if ($this->db->update('purchases', $data, array('id' => $id))) {
                return true;
            }
        } else {
            $this->db->delete('purchases', array('id' => $id));
        }
        return FALSE;
    }

    public function getExpenseCategories() {
        $q = $this->db->get('expense_categories');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getExpenseCategoryByID($id) {
        $q = $this->db->get_where("expense_categories", array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateAVCO($data) {
       
        if ($wp_details = $this->getWarehouseProductQuantity($data['warehouse_id'], $data['product_id'])) {
            $total_cost = (($wp_details->quantity * $wp_details->avg_cost) + ($data['quantity'] * $data['cost']));
            $total_quantity = $wp_details->quantity + $data['quantity'];
            if (!empty($total_quantity)) {
                $avg_cost = ($total_cost / $total_quantity);
                $this->db->update('warehouses_products', array('avg_cost' => $avg_cost), array('product_id' => $data['product_id'], 'warehouse_id' => $data['warehouse_id']));
            }
        } else {
            $this->db->insert('warehouses_products', array('product_id' => $data['product_id'], 'warehouse_id' => $data['warehouse_id'], 'avg_cost' => $data['cost'], 'quantity' => 0));
        }
        $query = $this->db->get_where(
            'warehouses_products',
            array('product_id' => $data['product_id'], 'warehouse_id' => $data['warehouse_id'])
        );
        return $query->row_array();
    }
    public function updateVariantsAVCO($data) {
        $wp_details = $this->getWarehouseProductVariantsQuantity($data['warehouse_id'], $data['product_id'], $data['option_id']);
        
        if ($wp_details) {
            $total_cost = (($wp_details->quantity * $wp_details->avg_cost) + ($data['quantity'] * $data['cost']));
            $total_quantity = $wp_details->quantity + $data['quantity'];
    
            if ($total_quantity > 0) { 
                $avg_cost = $total_cost / $total_quantity;
                $this->db->update(
                    'warehouses_products_variants',
                    ['avg_cost' => $avg_cost, 'quantity' => $total_quantity],
                    ['product_id' => $data['product_id'], 'warehouse_id' => $data['warehouse_id'], 'option_id' => $data['option_id']]
                );
            }
        } else {
            $this->db->insert(
                'warehouses_products_variants',
                ['product_id' => $data['product_id'], 'warehouse_id' => $data['warehouse_id'], 'option_id' => $data['option_id'], 'avg_cost' => $data['cost'], 'quantity' => $data['quantity']]
            );
        }
    
        return $this->db->get_where(
            'warehouses_products_variants',
            ['product_id' => $data['product_id'], 'option_id' => $data['option_id'], 'warehouse_id' => $data['warehouse_id']]
        )->row_array();
    }
    

    public function getAllTaxItems($purchase_id, $return_id, $itemId = NULL) {
        $this->db->select("attr_code,attr_name,attr_per, `tax_amount`  AS `amt`,item_id");
        $this->db->where_in('purchase_id', array($purchase_id, $return_id));
        $q = $this->db->get('purchase_items_tax');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->item_id][$row->attr_code] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllTaxItemsGroup($purchase_id, $return_id = NULL) {
        $this->db->select("attr_code,attr_name,attr_per,sum(`tax_amount`) AS `amt`");
        if ($return_id != NULL)
            $this->db->where_in('purchase_id', array((int) $purchase_id, (int) $return_id));
        else
            $this->db->where_in('purchase_id', array((int) $purchase_id));
        $this->db->group_by('attr_code');
        $this->db->order_by('id', 'asc');
        $q = $this->db->get('purchase_items_tax');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getUnitById($id) {
        $q = $this->db->get_where("units", array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function validateRecieptPurchase($TransKey = NULL, $User_id = NULL) {

        $this->db->select('purchases.id');
        $this->db->from('purchases');

        if (!empty($TransKey)):
            $this->db->where("MD5(CONCAT('purchase_reciept',id))", $TransKey);
        endif;

        $this->db->order_by("id ", "desc");
        $q = $this->db->get();

        if ($q->num_rows() > 0) :
            return $q->result_array();
        endif;
        return FALSE;
    }

    /*     * tax code* */

    public function getTaxRateByCode($name) {
        $q = $this->db->get_where('tax_rates', array('code' => $name), 1);
        if ($q->num_rows() > 0) {

            return $q->row();
        }
        return FALSE;
    }

    /*     * *** */

       /****************************************************************
     * Purchase Notification
     ****************************************************************/
    /**
     * Get Purchase Notification 
     * @return type
     */
    public function get_Purchase_Notification(){
       $data =  $this->db->select('notifications_purchases.*, companies.privatekey')->join('companies','companies.name=notifications_purchases.biller')->where(['notifications_purchases.is_status' => '1','companies.group_name' => 'supplier'])->get('notifications_purchases')->result();
       return $data;
    }
    
    /**
     * 
     * @param type $notificationId
     * @return boolean
     */
    public function getPurchaseNotificationItems($notificationId){
        $data = $this->db->where(['id' => $notificationId])->get('notifications_purchases')->row();
        if($this->db->affected_rows()){
          
            return $data;
        }
        return false;  
    }
    
    /**
     * Notifiaction update
     */
    public function removed_notification(){
        
        $this->db->where(['is_notification'=>'1'])->update('notifications_purchases',['is_notification'=> '0','updated_at'=>date('Y-m-d H:i:s')]);
        
    }
    
    /**
     * Count New Notification
     * @return type
     */
    public function count_new_purchase(){
         $q = $this->db->where(['is_notification'=>'1'])->get('notifications_purchases')->result();
         $data['num'] = count($q);
         return $data;
    } 
    
    /**
     * Removed Notification alert
     * @param type $status
     * @return type
     */
    public function set_notification_order_status($status = 1)
    {
         return $this->db->where(['is_notification' => '1'])
                ->update('notifications_purchases', ['is_notification' =>'0','updated_at'=>date('Y-m-d H:i:s')]);
      
    }
    
    
    /**
     * 
     * @param type $notificationId
     * @param type $data
     * @return type
     */
    public function syndataStore($notificationId, $data){
        $field = [
            'synced_data' => $data,
            'synced_date' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        $this->db->where(['id' => $notificationId])->update('notifications_purchases',$field);
        return ($this->db->affected_rows())? TRUE :FALSE;
        
    }
    
    /**
     * Check Product Exits or not using product barcode
     * @param type $productcode
     * @return type
     */
    public function checkProductExit($productcode){
        $this->db->where(['code'=>$productcode ])->get('products')->row();
        return ($this->db->affected_rows())? TRUE :FALSE;
    }
    
    /**
     * Check Supplyer
     * @param type $supplier
     * @return boolean
     */
    public function checkSupplier($supplier){
      $suppliers =   $this->db->select('*')->where(['group_name' => 'supplier', 'name' => $supplier])->get('companies')->row();
      if($this->db->affected_rows()){
           $supplierData = ['id' => $suppliers->id, 'name' => $suppliers->name];
           return $supplierData;
      }else{
          return false;
      }
      
    }
    
    /**
     * Option Id Get
     * @param type $optionname
     * @return type
     */
    public function getoptionId($optionname){
      $options =   $this->db->select('id')->where(['name'=>$optionname])->get('product_variants')->row();
      return $options->id;
    }
    
 
    
    /**
     * Get Unit Code ID
     * @param type $code
     * @return type
     */
    public function getUnitCodeId($code){
       $getunitcode =  $this->db->where(['code'=>$code])->get('units')->row();
       if($this->db->affected_rows()){
           return $getunitcode->id;
       }else{
          
           $this->db->insert('units',['code'=> $code, 'name'=> ucfirst($code), 'updated_at'=> date('Y-m-d H:i:s')]);
           return $this->db->insert_id();
         
       }
    }
    
     /****************************************************************
     * End Purchase Notification
     ****************************************************************/

      public function getProductOptionsByShapeId($id, $product_id)
    {
		
        $q = $this->db->get_where('product_variants', array('product_id' => $product_id, 'id'=>$id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function insertProductCostingAfterPurchase($productdata, $warehouseproductDetails,$purchaseitemRecord,$warehouseproductvariantsDetails){
        $product_id = $productdata['product_id'];
        $this->load->database(); 
        $latestRecord = $this->db->select('*')->from('sma_costing') ->where('product_id', $product_id)->order_by('date', 'DESC') ->limit(1)->get()->row_array();
        if($productdata['option_id']){
            $warehouseproductDetails['quantity'] = $warehouseproductvariantsDetails['quantity'];
            $avg_cost = $warehouseproductvariantsDetails['avg_cost'];
        }else{
            $avg_cost = $warehouseproductDetails['avg_cost'];
        }
        // Total Stock Quantity = Current Stock Quantity + Lastest Transaction Purchase stock Quantity
        $totalStockQuantity = $warehouseproductDetails['quantity'] + $purchaseitemRecord['quantity'];
        //Calculate stock values
        //Current Stock Value = Current Stock Quantity * Latest row sma_costing.purchase_unit_cost 
        $currentStockValue = $latestRecord['quantity_balance'] * $latestRecord['purchase_unit_cost'] ;
        //Purchase stock Value = Purchase stock Quantity * Purchase Cost 
        $purchasestockValue= $purchaseitemRecord['quantity'] * $purchaseitemRecord['unit_cost'];
        //Total Goods Value = Current Stock Value + Purchase stock Value
        $totalGoodsValue = $currentStockValue + $purchasestockValue ;
         //sma_costing.purchase_unit_cost =  Total Goods Value / Total Stock Quantity
         $purchaseunitcost = $totalGoodsValue/$totalStockQuantity;
         $costingdata = array(
            'date'                  => date('Y-m-d'),
            'product_id'            => $productdata['product_id'],
            'purchase_id'           => $productdata['purchase_id'],
            'purchase_item_id'      => $purchaseitemRecord['id'],
            'quantity'              => $purchaseitemRecord['quantity'],
            'purchase_unit_cost'    =>  $avg_cost,
            'quantity_balance'      => $purchaseitemRecord['quantity_balance'],
            'option_id'             => $productdata['option_id'],
            'batch_number'          => $productdata['batch_number'],
        );
        $this->db->insert('sma_costing', $costingdata);
    }
}
