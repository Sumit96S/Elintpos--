<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transfers_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function getProductNames($term, $warehouse_id, $limit = 20) {
        $this->db->select('products.id, code, name, warehouses_products.quantity, cost, tax_rate, type, unit, purchase_unit, tax_method, storage_type, primary_variant')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->group_by('products.id');
        if ($this->Settings->overselling) {
            $this->db->where("type = 'standard' AND (name LIKE '%" . $term . "%' OR code LIKE '%" . $term . "%' OR article_code LIKE '%" . $term . "%' OR  concat(name, ' (', code, ')') LIKE '%" . $term . "%')");
        } else {
            $this->db->where("type = 'standard' AND warehouses_products.warehouse_id = '" . $warehouse_id . "' AND "
                    . "(name LIKE '%" . $term . "%' OR code LIKE '%" . $term . "%' OR  concat(name, ' (', code, ')') LIKE '%" . $term . "%')");
            //AND warehouses_products.quantity > 0 
        }
        $this->db->limit($limit);
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getWHProduct($id) {
        $this->db->select('products.id, code, name, warehouses_products.quantity, cost, tax_rate')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->group_by('products.id');
        $q = $this->db->get_where('products', array('warehouses_products.product_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function addTransfer($data = [], $items = []) {
        
        $status = $data['status'];
         $productsids = array();
        if ($this->db->insert('transfers', $data)) {
            $transfer_id = $this->db->insert_id();
            if ($this->site->getReference('to') == $data['transfer_no']) {
                $this->site->updateReference('to');
            }
            $new_items=$items;
            foreach ($new_items as $n_item) {
                $n_item['transfer_id'] = $transfer_id;
                $n_item['item_status'] = $status;
                $n_item['created_by'] = $data['created_by'];
                $this->db->insert('transfer_item_new', $n_item);
            }
            foreach ($items as $item) {
                $item['transfer_id'] = $transfer_id;
                if ($status == 'completed') {
                    $item['date'] = date('Y-m-d');
                    $item['warehouse_id'] = $data['to_warehouse_id'];
                    $item['status'] = 'received';
                    $item['quantity_balance']  = $item['quantity_balance']  ? $item['quantity_balance']  : $item['quantity'];
                    $item['quantity_received'] = $item['quantity_received'] ? $item['quantity_received'] : $item['quantity'];
                    
                    if((float)$item['item_tax'] > 0){ 
                        $gst = (float)$item['item_tax'];
                        $tax = substr($item['tax'],0,4);
                                              
                        $item['gst_rate'] = (float)$tax / 2;
                        $item['cgst'] =  $item['sgst'] = ((float)$gst / 2);
                        $item['igst'] = 0;
                        //Check IGST Conditions   
                        if($data['to_warehouse_state_code']!='' && $data['from_warehouse_state_code']!=''){
                            if($data['to_warehouse_state_code'] != $data['from_warehouse_state_code']){                            
                               
                                $item['gst_rate'] = $tax;
                                $item['cgst'] =  $item['sgst'] = 0;
                                $item['igst'] = $gst;
                            }
                        } 
                    }
                    
                    $this->db->insert('purchase_items', $item);
                } else {
                    $item['item_status'] = $status;
                    $this->db->insert('transfer_items', $item);
                }

                if ($status == 'sent' || $status == 'completed') {
                    $this->syncTransderdItem($item['product_id'], $data['from_warehouse_id'], $item['quantity'], $item['option_id'], $item['batch_number']);

                   $productsids [] = $item['product_id'];    
                }
            }


            /*if(!empty($productsids)){
                // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $this->UPM->Product_out_of_stock($productsids,  $data['from_warehouse_id']);
                    $this->UPM->Product_out_of_stock($productsids,  $data['to_warehouse_id']);
               }
            }*/

            return true;
        }
        return false;
    }
    
    public function getPurchaseItemByID($id, $ProductId, $option_id, $batch_number = null) {

        $this->db->where(['transfer_id' => $id, 'product_id' => $ProductId]);
        
        if ($option_id) {
            $this->db->where(['option_id' => $option_id]);
        }
        if ($batch_number !== null && $batch_number) {
            $this->db->where(['batch_number' => $batch_number]);
        }
        $q = $this->db->get('purchase_items');
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function updateTransfer($id, $data = [], $items = []) {
                 
        $status = $data['status'];

        if ($this->db->update('transfers', $data, ['id' => $id])) {            
             
            $this->db->delete('transfer_items', ['transfer_id' => $id]);
             
            foreach ($items as $item) {
                
                $item['transfer_id'] = $id;
                               
                if ($status !== 'completed') {
                    $titem = $item;
                    $titem['item_status'] = $status;
                    unset($titem['hsn_code']);
                    $this->db->insert('transfer_items', $titem);
                }
                
                if($status == 'sent'){
                    
                    $qty = $item['unit_quantity'];
                                           
                    $pclause = ['product_id' => $item['product_id'], 'warehouse_id' => $data['from_warehouse_id'], 'option_id' => $item['option_id'], 'quantity_balance >' => 0 ];
                    $piw1 = $this->getPurchasedItems($pclause);
                                        
                    if($piw1) { 
                        foreach ($piw1 as $key => $pi) {
                            
                            if($pi->quantity_balance < $qty) {
                                $quantity_balance = 0;
                                $qty = $qty - $pi->quantity_balance;
                            } else {
                                $quantity_balance = $pi->quantity_balance - $qty;
                                $qty = 0;
                            }   

                            if($this->db->update('purchase_items', ['quantity_balance' => $quantity_balance], ['id' => $pi->id])){
                                
                                $tclause = ['transfer_id'=> $item['transfer_id'], 'product_id' => $item['product_id'], 'warehouse_id' => $data['to_warehouse_id'], 'option_id' => $item['option_id'], 'status'=>'!received' ];
                                $tiw2   = $this->getPurchasedItems($tclause);
                                
                                if($tiw2){ 
                                    $trpi = (array)$tiw2[0];                                   
                                    
                                } else {                    
                                   // $trpi = (array)$pi;                               
                                                                   
                                    $trpi['transfer_id']      = $item['transfer_id'];
                                    $trpi['batch_number']     = !empty($item['batch_number']) ? $item['batch_number'] : NULL;
                                    $trpi['product_id']       = $pi->product_id;
                                    $trpi['product_code']     = $pi->product_code;
                                    $trpi['product_name']     = $pi->product_name;
                                    $trpi['option_id']        = $pi->option_id;
                                    $trpi['quantity']         = $item['quantity'];
                                    $trpi['unit_quantity']    = $item['unit_quantity'];
                                    $trpi['quantity_balance']     = 0;
                                    $trpi['quantity_received']    = 0;                                    
                                    $trpi['status']           = 'pending';
                                    $trpi['warehouse_id']     = $data['to_warehouse_id'];
                                    $trpi['date']             = date('Y-m-d');
                                    $trpi['unit_cost']        = $item['unit_cost'];
                                    $trpi['real_unit_cost']   = $item['real_unit_cost'];
                                    $trpi['net_unit_cost']    = $item['net_unit_cost'];
                                    $trpi['tax_rate_id']      = $item['tax_rate_id'];
                                    $trpi['tax']              = $item['tax'];
                                    $trpi['item_tax']         = $item['item_tax'];
                                    $trpi['subtotal']         = $item['subtotal'];
                                    $trpi['expiry']           = $item['expiry'];
                                    $trpi['hsn_code']         = $item['hsn_code'];
                                    
                                    if($item['item_tax']) {
                                        $gst_rate = substr($item['tax'], 0, 4 );
                                        $gst = (float)$item['item_tax'] / 2;
                                        
                                        $trpi['gst_rate'] = ((float)$gst_rate / 2);
                                        $trpi['cgst'] = $gst;
                                        $trpi['sgst'] = $gst;
                                        $trpi['igst'] = 0;
                                        
                                        //Set IGST Conditions
                                        if($data['from_warehouse_state_code'] != '' && $data['to_warehouse_state_code']!=''){                                        
                                            if($data['from_warehouse_state_code'] != $data['to_warehouse_state_code']){

                                                $trpi['gst_rate'] = $gst_rate;
                                                $trpi['cgst'] = 0;
                                                $trpi['sgst'] = 0;
                                                $trpi['igst'] = $item['item_tax'];
                                            }                                        
                                        } 
                                    }
                                    
                                    $this->db->insert('purchase_items' , $trpi);
                                }
                            } 
                            if($qty == 0) { break; }    
                             
                        }//end foreach
                        
                    }                  
                
                   $this->site->syncProductQty($item['product_id'], $data['from_warehouse_id']);
                   if($item['option_id']){
                        $this->site->syncVariantQty($item['option_id'], $data['from_warehouse_id'], $item['product_id']);
                   }
                }//End status == sent                
                elseif($status == 'completed'){
                    
                    $clause2 = ['transfer_id' => $id ,'product_id' => $item['product_id'], 'warehouse_id' => $data['to_warehouse_id'], 'option_id' => $item['option_id'], 'batch_number' => $item['batch_number'], 'status' => '!received' ];
                    $piw2 = $this->getPurchasedItems($clause2);
                    
                    if($piw2) {
                        
                        $quantity_balance   = $piw2->quantity_balance + $item['unit_quantity'];
                        $quantity           = $item['request_quantity'];
                        $quantity_received  = $piw2->quantity_received + $item['unit_quantity'];
                        $status = $quantity == $quantity_received ? 'received' : 'partial';                        
                        $update = [
                                'quantity'          => $quantity,
                                'quantity_balance'  => $quantity_balance,
                                'quantity_received' => $quantity_received, 
                                'status'            => $status, 
                            ];
                        
                        $this->db->update('purchase_items', $update, ['id' => $piw2->id]);
                    } else {
                        $trdata['transfer_id']      = $item['transfer_id'];
                        $trdata['product_id']       = $item['product_id'];
                        $trdata['product_code']     = $item['product_code'];
                        $trdata['product_name']     = $item['product_name'];
                        $trdata['option_id']        = ($item['option_id'] ? $item['option_id'] : 0);
                        $trdata['batch_number']     = (!empty($item['batch_number']) ? $item['batch_number'] : NULL);
                        $trdata['warehouse_id']     = $data['to_warehouse_id'];
                        $trdata['net_unit_cost']    = $item['net_unit_cost'];
                        $trdata['unit_cost']        = $item['unit_cost'];
                        $trdata['real_unit_cost']   = $item['real_unit_cost'];
                        $trdata['product_unit_id']  = $item['product_unit_id'];
                        $trdata['product_unit_code']= $item['product_unit_code'];                        
                        $trdata['item_tax']         = $item['item_tax'];
                        $trdata['tax_rate_id']      = $item['tax_rate_id'];
                        $trdata['tax']              = $item['tax'];
                        $trdata['subtotal']         = $item['subtotal'];
                        $trdata['unit_quantity']    = $item['unit_quantity'];                        
                        $trdata['quantity']         = $item['quantity'];                         
                        $trdata['quantity_balance'] = $item['quantity'];
                        $trdata['quantity_received']= $item['quantity'];
                        $trdata['status']           = 'received';
                        $trdata['date']             = date('Y-m-d');
                        $trdata['hsn_code']         =  $item['hsn_code'];
                        
                        if($item['item_tax']) {
                            $gst_rate = substr($item['tax'], 0, 4 );
                            $gst = (float)$item['item_tax'] / 2;

                            $trdata['gst_rate'] = ((float)$gst_rate / 2);
                            $trdata['cgst'] = $gst;
                            $trdata['sgst'] = $gst;
                            $trdata['igst'] = 0;
                            //Set IGST Conditions
                            if($data['from_warehouse_state_code']!='' && $data['to_warehouse_state_code']!=''){                                        
                                if($data['from_warehouse_state_code'] != $data['to_warehouse_state_code']){

                                    $trdata['gst_rate'] = $gst_rate;
                                    $trdata['cgst'] = 0;
                                    $trdata['sgst'] = 0;
                                    $trdata['igst'] = $item['item_tax'];
                                }                                        
                            }
                        }
                        
                        $this->db->insert('purchase_items', $trdata);                        
                    }
                    
                    $this->site->syncProductQty($item['product_id'], $data['to_warehouse_id']);
                    if($item['option_id']){
                        $this->site->syncVariantQty($item['option_id'], $data['to_warehouse_id'], $item['product_id']);
                    }
                } //End Status == complited                  
            }
           
            $this->db->update('transfers', ['status' => $data['status']], array('id' => $id));
            
            return true;
        }

        return false;
    }

    public function updateTransfer_backup($id, $data = array(), $items = array()) {
        
        $status_main = $data['status'];
         $productsids  = array(); 
       // $ostatus     = $this->resetTransferActions($id, $status_main);
        $status      = $status1 = $data['status'];

        if ($this->db->update('transfers', $data, array('id' => $id))) {
            $tbl = $ostatus == 'completed' ? 'purchase_items' : 'transfer_items';
            $this->db->delete($tbl, array('transfer_id' => $id));
            if ($status1 == 'partial') {
                $status = $data['status'] = $Upddata['status'] = 'sent';
            }
            foreach ($items as $item) {
                $item['transfer_id'] = $id;
                if ($item['unit_quantity'] != 0) {
                    $item['unit_quantity'] = $item['sent_quantity'];
                }
                if ($status1 == 'partial') {
                    if ($item['request_quantity'] != $item['sent_quantity']) {
                        $status = $Upddata['status'] = $data['status'] = $status1;
                    }
                }

                if ($status == 'completed') {
                    $item['date'] = date('Y-m-d');
                    $item['warehouse_id'] = $data['to_warehouse_id'];
                    $item['status'] = 'received';
                    unset($item['request_quantity'], $item['sent_quantity']);
                    $Res = $this->getPurchaseItemByID($id, $item['product_id'], $item['option_id'], $item['batch_number']);
                    if (empty($Res)) {
                        $this->db->insert('purchase_items', $item);
                    } else {
                        $field_array = [
                            'transfer_id' => $id, 'product_id' => $item['product_id'], 'batch_number' => $item['batch_number'],
                        ];

                        if ($item['option_id'] !== '0') {
                            $field_array['option_id'] = $item['option_id'];
                        }

                        // array('transfer_id' => $id, 'product_id'=>$item['product_id'], 'option_id' =>$item['option_id'])
                        $this->db->update('purchase_items', $item, $field_array);
                    }
                } else if ($status == 'partial') {

                    $this->db->insert('transfer_items', $item);

                    $item['date']           = date('Y-m-d');
                    $item['warehouse_id']   = $data['to_warehouse_id'];
                    $item['status']         = 'received';

                    unset($item['request_quantity'], $item['sent_quantity']);
                    $this->db->insert('purchase_items', $item);
                } else {
                    $this->db->insert('transfer_items', $item);
                }

                if ($data['status'] == 'sent' || $data['status'] == 'completed' || $data['status'] == 'partial' || $data['status'] == 'sent_balance') {
                    $this->syncTransderdItem($item['product_id'], $data['from_warehouse_id'], $item['quantity'], $item['option_id'], $item['batch_number'], $status_main);

                    $productsids[] = $item['product_id'];    
                }
            }

           /*if(!empty($productsids)){
                // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $this->UPM->Product_out_of_stock($productsids,  $data['from_warehouse_id']);
                    $this->UPM->Product_out_of_stock($productsids,  $data['to_warehouse_id']);
               }
             }*/

            if ($status1 == 'partial')
                $this->db->update('transfers', $Upddata, array('id' => $id));
            return true;
        }

        return false;
    }

    public function updateStatus($id, $status, $note) {
        $ostatus = $this->resetTransferActions($id);

        $transfer = $this->getTransferByID($id);
        $items = $this->getAllTransferItems($id, $transfer->status);

       $productsids = array();

        if ($this->db->update('transfers', array('status' => $status, 'note' => $note), array('id' => $id))) {
            $tbl = $ostatus == 'completed' ? 'purchase_items' : 'transfer_items';
            $this->db->delete($tbl, array('transfer_id' => $id));

            foreach ($items as $item) {
                $item = (array) $item;
                $item['transfer_id'] = $id;
                unset($item['id'], $item['variant'], $item['unit']);
                if ($status == 'completed') {
                    $item['date'] = date('Y-m-d');
                    $item['warehouse_id'] = $transfer->to_warehouse_id;
                    $item['status'] = 'received';
                    $this->db->insert('purchase_items', $item);
                } else {
                    $this->db->insert('transfer_items', $item);
                }

                if ($status == 'sent' || $status == 'completed') {
                    $this->syncTransderdItem($item['product_id'], $transfer->from_warehouse_id, $item['quantity'], $item['option_id'], $item['batch_number']);
                     $productsids[] = $item['product_id'];
                } else {
                    $this->site->syncQuantity(NULL, NULL, NULL, $item['product_id']);

                   $productsids[] = $item['product_id'];
                }
            }

            /*if(!empty($productsids)){
                // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $this->UPM->Product_out_of_stock($productsids,  $transfer->from_warehouse_id);
                    $this->UPM->Product_out_of_stock($productsids,  $transfer->to_warehouse_id);
               }
             } */
            return true;
        }
        return false;
    }

    public function getProductWarehouseOptionQty($option_id, $warehouse_id) {
        $q = $this->db->get_where('warehouses_products_variants', array('option_id' => $option_id, 'warehouse_id' => $warehouse_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductByCategoryID($id) {

        $q = $this->db->get_where('products', array('category_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return true;
        }

        return FALSE;
    }

    public function getProductQuantity($product_id, $warehouse = DEFAULT_WAREHOUSE) {
        $q = $this->db->get_where('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse), 1);
        if ($q->num_rows() > 0) {
            return $q->row_array(); //$q->row();
        }
        return FALSE;
    }

    public function insertQuantity($product_id, $warehouse_id, $quantity) {
        if ($this->db->insert('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $quantity))) {
            $this->site->syncProductQty($product_id, $warehouse_id);
            return true;
        }
        return false;
    }

    public function updateQuantity($product_id, $warehouse_id, $quantity) {
        if ($this->db->update('warehouses_products', array('quantity' => $quantity), array('product_id' => $product_id, 'warehouse_id' => $warehouse_id))) {
            $this->site->syncProductQty($product_id, $warehouse_id);
            return true;
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

    public function getTransferByID($id) {
        $q = $this->db->get_where('transfers', ['id' => $id], 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getAllTransferItems($transfer_id, $status) {
        
       // if ($status == 'completed' || ($this->uri->segment(2) == 'view' )) {
        if ($status == 'completed') {
            $this->db->select('purchase_items.*, product_variants.name as variant, products.unit,categories.name as category_name')
                    ->from('purchase_items')
                    ->join('products', 'products.id=purchase_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->join('product_variants', 'product_variants.id=purchase_items.option_id', 'left')
                    ->group_by('purchase_items.id')
                    ->where('transfer_id', $transfer_id)
                    ->order_by('purchase_items.product_name','ASC');
        } else {
            $this->db->select('transfer_items.*, product_variants.name as variant, products.unit,categories.name as category_name')
                    ->from('transfer_items')
                    ->join('products', 'products.id=transfer_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->join('product_variants', 'product_variants.id=transfer_items.option_id', 'left')
                    ->group_by('transfer_items.id')
                    ->where('transfer_id', $transfer_id)
                    ->order_by('transfer_items.product_name', 'ASC');
        }
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getWarehouseProduct($warehouse_id, $product_id, $variant_id) {
        if ($variant_id) {
            return $this->getProductWarehouseOptionQty($variant_id, $warehouse_id);
        } else {
            return $this->getWarehouseProductQuantity($warehouse_id, $product_id);
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

    public function resetTransferActions($id, $CurrentStatus = '') {
        $otransfer = $this->getTransferByID($id);
        $oitems = $this->getAllTransferItems($id, $otransfer->status);
        $ostatus = $otransfer->status;
        if ($ostatus == 'sent' || $ostatus == 'completed') {
            // $this->db->update('purchase_items', array('warehouse_id' => $otransfer->from_warehouse_id, 'transfer_id' => NULL), array('transfer_id' => $otransfer->id));
            foreach ($oitems as $item) {
                $option_id = (isset($item->option_id) && !empty($item->option_id)) ? $item->option_id : 0;
              //  $clause = ['purchase_id' => NULL, 'transfer_id' => NULL, 'product_id' => $item->product_id, 'warehouse_id' => $otransfer->from_warehouse_id, 'option_id' => $option_id, 'batch_number' => $item->batch_number ];
                $clause = [ 'product_id' => $item->product_id, 'warehouse_id' => $otransfer->from_warehouse_id, 'option_id' => $option_id, 'batch_number' => $item->batch_number ];
                $pi = $this->site->getPurchasedItem( ['id' => $item->id] );//Transfer Items
                if ($ppi = $this->site->getPurchasedItem($clause)) {
                    $quantity_balance = $ppi->quantity_balance + $item->quantity;
                    if ($CurrentStatus == 'completed' && $ostatus == 'sent') {
                        //$clause['quantity_balance'] = 0;
                        $clause['transfer_id'] = $id;
                    }
                    $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $ppi->id));
                } else {
//                    if ($CurrentStatus == 'completed' && $ostatus == 'sent') {
//                        //$clause['quantity_balance'] = 0;
//                        $clause['transfer_id'] = $id;
//                    }
//                    $clause['quantity'] = $item->quantity;
//                    $clause['item_tax'] = 0;
//                    $clause['quantity_balance'] = $item->quantity;
//                    $clause['status'] = 'received';
//                    $this->db->insert('purchase_items', $clause);
                }
            }
        }
        return $ostatus;
    }

    public function deleteTransfer($id, $transfer='') {
        
        $otransfer = !empty($transfer) ? $transfer : $this->getTransferByID($id);
        
        if($otransfer->status !== 'completed') {
            $oitems  = $this->getAllTransferItems($id, $otransfer->status);
            foreach ($oitems as $item) {
                $sent_quantity = ( (float)$item->sent_quantity > 0 ) ? $item->sent_quantity : $item->quantity;
                
                $clause = [ 'product_id' => $item->product_id, 'warehouse_id' => $otransfer->from_warehouse_id, 'option_id' => $option_id, 'batch_number' => $item->batch_number ];
                
                $pti = $this->site->getPurchasedItem( $clause ); //Transfer Items
                
                $quantity_balance = $pti->quantity_balance + $sent_quantity;
                
                $this->db->update('purchase_items', ['quantity_balance' => $quantity_balance], ['id' => $pti->id] );
            }
            
            if ($this->db->delete('transfers', ['id' => $id]) && $this->db->delete('transfer_items', ['transfer_id' => $id]) && $this->db->delete('purchase_items', ['transfer_id' => $id])) {
                foreach ($oitems as $item) {
                    $this->site->syncQuantity(NULL, NULL, NULL, $item->product_id);

                    // Urbanpiper Stock Manage 
                   /* if($this->Settings->pos_type == 'restaurant'){
                        $this->load->model("Urban_piper_model","UPM");
                        $this->UPM->Product_out_of_stock([$item->product_id],  $otransfer->from_warehouse_id);
                     }*/                     
                }
                return true;
            }
        }
        return FALSE;
    }
    
    //Backup Dated On 11-07-2021 , Version 4.13
   /* public function deleteTransfer($id) {
        $ostatus = $this->resetTransferActions($id);
        $oitems  = $this->getAllTransferItems($id, $ostatus);
        $tbl = $ostatus == 'completed' ? 'purchase_items' : 'transfer_items';
        if ($this->db->delete('transfers', ['id' => $id]) && $this->db->delete($tbl, ['transfer_id' => $id])) {
            foreach ($oitems as $item) {
                $this->site->syncQuantity(NULL, NULL, NULL, $item->product_id);
            }
            return true;
        }
        return FALSE;
    }*/

    public function getProductOptions($product_id, $warehouse_id, $zero_check = TRUE) {
        $this->db->select('product_variants.id as id, product_variants.name as name, product_variants.cost as cost, product_variants.quantity as total_quantity, warehouses_products_variants.quantity as quantity')
                ->join('warehouses_products_variants', 'warehouses_products_variants.option_id=product_variants.id', 'left')
                ->where('product_variants.product_id', $product_id)
                ->where('warehouses_products_variants.warehouse_id', $warehouse_id)
                ->group_by('product_variants.id');
        if ($zero_check === TRUE) {
            $this->db->where('warehouses_products_variants.quantity >', 0);
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
        $this->db->select('products.id as id, combo_items.item_code as code, combo_items.quantity as qty, products.name as name, warehouses_products.quantity as quantity')
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

    public function getProductVariantByName($name, $product_id) {
        $q = $this->db->get_where('product_variants', array('name' => $name, 'product_id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function syncTransderdItem($product_id, $warehouse_id, $quantity, $option_id = NULL, $batch_number = NULL, $Status = NULL) {
        
        if ($pis = $this->site->getPurchasedItems($product_id, $warehouse_id, $option_id, $batch_number)) {
            
            $balance_qty = $quantity;
            foreach ($pis as $pi) {
                if ($balance_qty <= $quantity && $quantity > 0) {
                    if ($pi->quantity_balance >= $quantity) {
                        $balance_qty = $pi->quantity_balance - $quantity;
                        if ($Status != 'completed') {
                            $this->db->update('purchase_items', ['quantity_balance' => $balance_qty], ['id' => $pi->id]);
                        }
                        $quantity = 0;
                    } elseif ($quantity > 0) {
                        $quantity = $quantity - $pi->quantity_balance;
                        $balance_qty = $quantity;
                        $this->db->update('purchase_items', ['quantity_balance' => 0], ['id' => $pi->id]);
                    }
                }
                if ($quantity == 0) {
                    break;
                }
            }
        } else {
           // $clause = ['purchase_id' => NULL, 'transfer_id' => NULL, 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'option_id' => $option_id, 'batch_number' => $batch_number];
            $clause = [ 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'option_id' => $option_id, 'batch_number' => $batch_number];
            if ($pi = $this->site->getPurchasedItem($clause)) {
                $quantity_balance = $pi->quantity_balance - $quantity;
                $this->db->update('purchase_items', ['quantity_balance' => $quantity_balance], ['id' => $pi->id]);
            } else {
                $clause['quantity'] = 0;
                $clause['item_tax'] = 0;
                $clause['status'] = 'received';
                $clause['quantity_balance'] = (0 - $quantity);
                $this->db->insert('purchase_items', $clause);
            }
        }
        $this->site->syncQuantity(NULL, NULL, NULL, $product_id);


         // Urbanpiper Stock Manage 
        /*if($this->Settings->pos_type == 'restaurant'){
          $this->load->model("Urban_piper_model","UPM");
          $this->UPM->Product_out_of_stock([$product_id], $warehouse_id);
       }*/

    }

    public function getProductOptionByID($id) {
        $q = $this->db->get_where('product_variants', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getTransferProductList($warehouse_id, $werehouse_id_2) {


        $this->db->select('products.id, products.code, products.name,products.cost, products.tax_rate, products.type, products.unit, products.purchase_unit, products.tax_method, product_variants.name as option,product_variants.id as varentid,tax_rates.rate,tax_rates.type as tax_type')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->join('product_variants', 'product_variants.product_id=products.id', 'left')
                ->join('tax_rates', 'tax_rates.id=tax_rate', 'left');
        //->group_by('products.id');
        $this->db->where("products.type = 'standard' ")->where("warehouses_products.warehouse_id = $warehouse_id ")->order_by('products.name', 'ASC');
        //AND warehouses_products.warehouse_id = '" . $warehouse_id . "' 

        $q = $this->db->get('products');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                if ($row->option) {

                    $qty1 = $this->db->select('quantity')->where(['product_id' => $row->id, 'warehouse_id' => $warehouse_id, 'option_id' => $row->varentid])->get('sma_warehouses_products_variants')->row(); //Shock  Quantity Warehouse 1 query With Varent
                    $qty2 = $this->db->select('quantity  as werehouse_2_quantity')->where(['product_id' => $row->id, 'warehouse_id' => $werehouse_id_2, 'option_id' => $row->varentid])->get('sma_warehouses_products_variants')->row(); //Shock  Quantity Warehouse 2 query With Varent
                } else {
                    $qty1 = $this->db->select('quantity')->where(['product_id' => $row->id, 'warehouse_id' => $warehouse_id])->get('sma_warehouses_products')->row(); //Shock Quantity Warehouse 1 query Without Varent
                    $this->db->select('quantity as werehouse_2_quantity')
                            ->where(['product_id' => $row->id, 'warehouse_id' => $werehouse_id_2]);
                    $qty2 = $this->db->get('warehouses_products')->row(); //Shock  Quantity Warehouse 2 query Without Varent
                }
                $row->werehouse_2_quantity = $qty2->werehouse_2_quantity;
                $row->quantity = $qty1->quantity;
                $sql = $this->db->where(array('product_id' => $row->id))->get('product_variants');




                $optionvartiant = array();
                if ($sql->num_rows() > 0) {
                    foreach (($sql->result()) as $rows) {
                        $optionvartiant[] = $rows;
                        // $quantity =$quantity + $row->quantity;
                    }
                }

                $units = $this->site->getUnitsByBUID($row->unit);
                // $data[] = $row; 
                $data[] = array('item' => $row, 'variant' => $optionvartiant, 'units' => $units);
            }
            return $data;
        }
        return FALSE;
    }

    public function getVariantQuantity($varient_id, $warehouse_id) {
        $this->db->select('quantity');
        $this->db->where('option_id', $varient_id);
        $this->db->where_in('warehouse_id', $warehouse_id);
        $data = $this->db->get('warehouses_products_variants');
        if ($data->num_rows() > 0) {
            return $data->result();
        }
        return FALSE;
    }

    public function getTransferCompletedItems($transfer_id) {
        
        $q = $this->db->select('id, product_id, option_id, batch_number, quantity, quantity_received')->where('transfer_id', $transfer_id)->get('purchase_items');
        
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                
                $data[$row->product_id][$row->option_id] = $row;
            }
            return $data;
        }
    }
    
     public function getPurchasedItems($where_clause) {

        $product_storage_type = $where_clause['product_id'] ? $this->site->getProductStorageType($where_clause['product_id']) : 'packed';

        $orderby = ($this->Settings->accounting_method == 1) ? 'desc' : 'asc';
        $this->db->order_by('date', $orderby);
        $this->db->order_by('purchase_id', $orderby);
        $this->db->order_by('quantity_balance', 'DESC');

        if ($where_clause['option_id'] && $product_storage_type == 'packed') {
            $this->db->where('option_id', $where_clause['option_id']);
        }
        unset($where_clause['option_id']);

        if ($this->Settings->product_batch_setting > 0 && $where_clause['batch_number']) {
            $this->db->where('batch_number', $where_clause['batch_number']);
        }
        unset($where_clause['batch_number']);

        if ($where_clause['status']) {
            if($where_clause['status'] == '!received'){
                $this->db->where('status !=', 'received');
            } else {
                $this->db->where('status', $where_clause['status']);
            }
            unset($where_clause['status']);
        } else {
            $this->db->group_start()->where('status', 'received')->or_where('status', 'partial')->or_where('status', 'returned')->group_end();
        }

        $this->db->where($where_clause);

        $q = $this->db->get('purchase_items');

        if ($q->num_rows() > 0) {
           foreach (($q->result()) as $row) {
                
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    
    public function getTransferRequestByID($id) {
        $q = $this->db->get_where('transfer_request', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    
    
    public function getAllTransferRequestItems($request_id) {
        
        $this->db->select('transfer_request_items.*, product_variants.name as variant, products.unit')
                    ->from('transfer_request_items')
                    ->join('products', 'products.id=transfer_request_items.product_id', 'left')
                    ->join('product_variants', 'product_variants.id=transfer_request_items.option_id', 'left')
                    ->group_by('transfer_request_items.id')
                    ->where('transfer_request_id', $request_id);
       
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }
    
    public function addRequest($data = [], $items = []) {
        $status = $data['status'];
        if ($this->db->insert('transfer_request', $data)) {
            $transfer_request_id = $this->db->insert_id();
            if ($this->site->getReference('treq') == $data['transfer_request_no']) {
                $this->site->updateReference('treq');
            }
            foreach ($items as $item) {                
                $item['transfer_request_id'] = $transfer_request_id;                 
                $this->db->insert('transfer_request_items', $item);                                 
            }

            return true;
        }
        return false;
    }
    
    public function updateTransferRequest($id, $request, $request_items, $transfer, $transfer_items) {
        
        if(($request['status']=='partial' || $request['status']=='completed') && is_array($transfer) && is_array($transfer_items)){
            $this->addTransfer($transfer, $transfer_items);                    
        } 
        
        return $this->_updateTransferRequest($id, $request, $request_items);
        
        return FALSE;
    }
    
    private function _updateTransferRequest($id, $data = [], $items = []) {
                 
        $status = $data['status'];

        if ($this->db->update('transfer_request', $data, ['id' => $id])) {
            
            $this->db->delete('transfer_request_items', ['transfer_request_id' => $id]);
                
            foreach ($items as $item) {
                $item['transfer_request_id'] = $id;
                $item['item_status'] = 'partial'; 
                if ($item['request_quantity'] == $item['sent_quantity']) {
                    $item['item_status'] = 'complited';
                } 
                
                $this->db->insert('transfer_request_items', $item);
            }
            
            return true;
        }

        return false;
    }
    
    public function deleteTransferRequest($id) {
       
        if ($this->db->delete('transfer_request', ['id' => $id]) && $this->db->delete('transfer_request_items', ['transfer_request_id' => $id]) ) {
            
            return TRUE;
        }
         
        return FALSE;
    }
    // Transfer report
    public function getFromWarehouses()
    {
        $quary="SELECT DISTINCT(from_warehouse_name),from_warehouse_id FROM sma_transfers";
        $res= $this->db->query($quary);
        return $res->result();   
    }
    public function getToWarehouses()
    {
        $quary="SELECT DISTINCT(to_warehouse_name ),to_warehouse_id FROM sma_transfers";
        $res= $this->db->query($quary);
        return $res->result();
    }
    public function getCategory()
    {
        $quary = "select sma_categories.name as name,category_id from sma_categories,sma_products, sma_transfer_items where sma_products.category_id=sma_categories.id and  sma_transfer_items.product_id=sma_products.id";

        $res= $this->db->query($quary);
        return $res->result();
    }
    public function getBrand()
    {
        $quary = "select sma_brands.name as name,sma_products.brand from sma_brands,sma_products, sma_transfer_items where sma_products.brand=sma_brands.id and  sma_transfer_items.product_id=sma_products.id";

        $res= $this->db->query($quary);
        return $res->result();
    }
    public function transferStatus()
    {
        $quary="SELECT DISTINCT(status) FROM sma_transfers";
        $res= $this->db->query($quary);
        return $res->result();
    }
    public function getUsers()
    {
        $quary = "select sma_transfers.created_by as id,username from sma_transfers,sma_users where sma_transfers.created_by=sma_users.id";
        $res= $this->db->query($quary);
        return $res->result();
    }
    public function getRefNo()
    {
        $quary="SELECT transfer_no,id FROM sma_transfers";
        $res= $this->db->query($quary);
       return $res->result();
    }
    public function getProductsById($id)
    {
        // $quary = "select sma_products.id as id,code,sma_products.name as name from sma_products,sma_transfer_item_new where sma_products.id=sma_transfer_item_new.product_id and sma_transfer_item_new.transfer_id=$id";
        $quary = "select * from sma_transfer_item_new where sma_transfer_item_new.transfer_id=$id";
        $res= $this->db->query($quary);
        return $res->result();
    }
    public function getProductsByIds($id)
{
    $query = "
        SELECT  p.name AS product_name,pv.name AS variant_name,tin.quantity AS quantity,tin.real_unit_cost AS unit_cost,tin.tax AS tax,tin.subtotal AS subtotal
        FROM sma_transfer_item_new tin
        JOIN sma_products p ON tin.product_id = p.id
        LEFT JOIN sma_product_variants pv ON tin.option_id = pv.id
        WHERE tin.transfer_id = $id
    ";
    $res = $this->db->query($query);
    return $res->result();
}

    public function getUserByID($id)
    {
        $quary= "select username from sma_transfers,sma_users where sma_transfers.created_by=sma_users.id and sma_transfers.id=$id";
        $res=$this->db->query($quary);
        return $res->result();
    }
    public function getAllTransferReportItems($transfer_id, $status) {
        
        // if ($status == 'completed' || ($this->uri->segment(2) == 'view' )) {  transfer_item_new
         if ($status == 'completed') {
             $this->db->select('purchase_items.*, product_variants.name as variant, products.unit')
                     ->from('purchase_items')
                     ->join('products', 'products.id=purchase_items.product_id', 'left')
                     ->join('product_variants', 'product_variants.id=purchase_items.option_id', 'left')
                     ->group_by('purchase_items.id')
                     ->where('transfer_id', $transfer_id);
         } else {
             $this->db->select('transfer_item_new.*, product_variants.name as variant, products.unit')
                     ->from('transfer_item_new')
                     ->join('products', 'products.id=transfer_item_new.product_id', 'left')
                     ->join('product_variants', 'product_variants.id=transfer_item_new.option_id', 'left')
                     ->group_by('transfer_item_new.id')
                     ->where('transfer_id', $transfer_id);
         }
         $q = $this->db->get();
         if ($q->num_rows() > 0) {
             foreach (($q->result()) as $row) {
                 $data[] = $row;
             }
             return $data;
         }
     }
    //Average Cost 

    public function getCosting($id) {
        $this->db->select('purchase_unit_cost AS cost', FALSE);
        $this->db->where('product_id', $id);
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1); 
        $q = $this->db->get('sma_costing');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }
}
