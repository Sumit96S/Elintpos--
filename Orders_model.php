<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Orders_model extends CI_Model
{
    private $orders;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->orders = [];
        
        $this->load->model('sales_model');
    }
     
    public function addOrder($data = array(), $items = array(), $payment = array(), $si_return = array(), $extrasPara = array() )
    {
        $this->load->model('sales_model');
        if($data['sale_status'] !='returned'){
         $cost = $this->site->costing($items);
        }
         
        $sale_action    = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $order_id       = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity   = $extrasPara['syncQuantity'];
            
        $data['sale_as_chalan'] = ($sale_action == 'chalan' ? 1 : 0);
         
        if ($this->db->insert('orders', $data)) {
            
            $order_id = $this->db->insert_id();
            
            if(empty($data['invoice_no'])){
                //Get formated Invoice No
                 $invoice_no = ($sale_action == 'chalan') ? $data['invoice_no'] : $this->sma->invoice_format($order_id,date());
              
                //$invoice_no = $this->sma->invoice_format($order_id,date());             
                //Update formated invoice no
                $this->db->where(['id'=>$order_id])->update('orders', ['invoice_no' => $invoice_no]);
            }
            
            if ($this->site->getReference(ordr) == $data['reference_no']) {
                $this->site->updateReference(ordr);
            }
            if ($this->site->getReference(re_ordr) == $data['return_sale_ref']) {
               $this->site->updateReference(re_ordr);
            }
	    $Setting =   $this->Settings;
            
            foreach ($items as $item) {
		//------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                $_prd       =   $Setting->pos_type=='pharma' ?$this->site->getProductByID($item['product_id']):NULL;
                $item['cf1'] = $Setting->pos_type=='pharma' ?$_prd->cf1:'';
                $item['cf2'] = $Setting->pos_type=='pharma' ?$_prd->cf2:'';
                //------------------ End ----------------//
                $item['sale_id'] = $order_id;
                $this->db->insert(order_items, $item);
                $sale_item_id = $this->db->insert_id();
                    
                $_taxSaleID =  $order_id;
                
                $_tax_type = ($sale_action == 'chalan' ? 'o' : NULL);
                
                $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID , $_tax_type);
                
                if($data['sale_status'] == 'completed') {

                    $item_costs = $this->site->item_costing($item);
                    
                    foreach ($item_costs as $item_cost) {
                        if (isset($item_cost['date'])) { 
                            
                            $item_cost['order_item_id'] = $sale_item_id;
                            $item_cost['order_id'] = $order_id;
                            
                            if(! isset($item_cost['pi_overselling'])) {
                                $this->db->insert('costing', $item_cost);
                            }
                        } else {
                            foreach ($item_cost as $ic) {
                            	if(is_array($ic)):
                                     
                                    $ic['order_item_id'] = $sale_item_id;
                                    $ic['order_id']      = $order_id;

                                    if(! isset($ic['pi_overselling'])) {
                                        $this->db->insert('costing', $ic);
                                    }
                                endif;
                            }
                        }
                    }
                }                         
            }            

            if ($data['sale_status'] == 'completed' && $syncQuantity) {
                
                $this->site->syncPurchaseItems($cost);
            }

            if (!empty($si_return)) {
                foreach ($si_return as $return_item) {
                    $product = $this->site->getProductByID($return_item['product_id']);
                    if ($product->type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($return_item['product_id'], $return_item['warehouse_id']);
                        foreach ($combo_items as $combo_item) {
                            
                            $this->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                            $this->updatePurchaseItem(NULL,($return_item['quantity']*$combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                        }
                    } else {
                        $this->updateCostingLine($return_item['id'], $return_item['product_id'], $return_item['quantity']);
                        $this->updatePurchaseItem(NULL, $return_item['quantity'], $return_item['id']);
                    }
                }
                $this->db->update('orders', array('return_sale_ref' => $data['return_sale_ref'], 'surcharge' => $data['surcharge'],'return_sale_total' => $data['grand_total'], 'return_id' => $order_id), array('id' => $data['sale_id']));
            }

            if ($data['payment_status'] == 'partial' || $data['payment_status'] == 'paid' && !empty($payment)) {
                if (empty($payment['reference_no'])) {
                    $payment['reference_no'] = $this->site->getReference('pay');
                }
                
                $payment['order_id'] = $order_id;                 
                
                if ($payment['paid_by'] == 'gift_card') {
                    $this->db->update('gift_cards', array('balance' => $payment['gc_balance']), array('card_no' => $payment['cc_no']));
                    unset($payment['gc_balance']);
                    $this->db->insert('payments', $payment);
                } else {
                    if ($payment['paid_by'] == 'deposit') {
                        $customer = $this->site->getCompanyByID($data['customer_id']);
                        $this->db->update('companies', array('deposit_amount' => $payment['cc_holder']), array('id' => $data['customer_id']));
                        //$this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount-$payment['amount'])), array('id' => $customer->id));
                    }
                    $this->db->insert('payments', $payment);
                }
                if ($this->site->getReference('pay') == $payment['reference_no']) {
                    $this->site->updateReference('pay');
                }
                $this->site->syncOrderPayments($order_id);
            }
            
            if($syncQuantity) {
                $this->site->syncQuantity( NULL, NULL, NULL, NULL, $order_id );

                 // Urbanpiper Stock Manage 
               /*if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                } */                                    
            }            
            
            if ($this->Settings->synch_reward_points) {
                $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);
            }
            
            return $order_id;
        }

        return false;
    }
    
    public function getAllTaxOrderItems($order_id,$return_id,$itemId=NULL)  {
        $this->db->select("attr_code,attr_name,attr_per, `tax_amount`  AS `amt`,item_id");
        $this->db->where_in('order_id', array($order_id,$return_id)); 
        $q =  $this->db->get('orders_items_tax'); 
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
               $data[$row->item_id][$row->attr_code] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function getAllTaxItemsGroup($order_id,$return_id=NULL)  {
        $this->db->select("attr_code,attr_name,attr_per,sum(`tax_amount`) AS `amt`");
        $this->db->where_in('order_id', array((int)$order_id,(int)$return_id)); 
        $this->db->group_by('attr_code'); 
          $this->db->order_by('id', 'asc'); 
        $q =  $this->db->get('orders_items_tax');
        
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function getAllOrderItems($order_id) {
        
        if($this->pos_settings->item_order == 0 || $this->pos_settings->item_order == 2) {
            $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, product_variants.name as variant, products.image, products.details as details, categories.id as category_id, categories.name as category_name, product_variants.price as variant_price')
                    ->join('products', 'products.id=order_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
                    ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                    ->group_by('order_items.id');
                   // ->order_by('id', 'asc');
                    if($this->pos_settings->display_category == 0)
                            $this->db->order_by('order_items.subtotal', 'desc');
                    else
                            $this->db->order_by('categories.id', 'desc');
                    
        } elseif ($this->pos_settings->item_order == 1) {
            $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, product_variants.name as variant, categories.id as category_id, categories.name as category_name,products.image, products.details as details, product_variants.price as variant_price')
                    ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
                    ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                    ->join('products', 'products.id=order_items.product_id', 'left')
                    ->join('categories', 'categories.id=products.category_id', 'left')
                    ->group_by('order_items.id');
                  //  ->order_by('categories.id', 'asc')
                    if($this->pos_settings->display_category == 0)
                            $this->db->order_by('order_items.subtotal', 'desc');
                    else
                            $this->db->order_by('categories.id', 'desc');
        }//end else
        
        $q = $this->db->get_where('order_items', array('sale_id' => $order_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                if ($row->product_type == 'combo') {
                    $row->combo_items = $this->sales_model->getProductComboItems($row->product_id);
                }
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function getOrderPayments($order_id) {
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('payments', array('order_id' => $order_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getOrderByID($id)
    {
        $q = $this->db->get_where('orders', array('id' => $id ), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    
    public function getOrderItemByID($id)
    {
        $q = $this->db->get_where('order_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    
    public function getOrderItem($id)
    {
        $q = $this->db->get_where('order_items', array('sale_id' => $id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    
    public function actionDeleteOrder($id) {
        
        $order = $this->getOrderByID($id);
        
        $syncQuantity = $order->sale_as_chalan ? $order->sale_as_chalan : 0; 
        
        $sale_id = null;
        if($order->sale_invoice_no) {
           $sale = $this->sales_model->getSaleByInvoiceNo($inv->sale_invoice_no);
           if($sale){ $sale_id = $sale->id; }
        }
        
        return $this->deleteOrder($id, $syncQuantity, $sale_id);
    }
    
    public function deleteOrder($id, $syncQuantity=0, $sale_id=null)
    {
        if($syncQuantity) {
            $order_items = $this->resetOrderActions($id);
        }
        if ($this->db->delete('order_items', array('sale_id' => $id)) && $this->db->delete('orders', array('id' => $id)) ) {
            $this->db->delete('orders_items_tax', array('order_id' => $id));
            $this->db->delete('orders', array('sale_id' => $id));
            
            if($sale_id){
                $this->db->update('payments', array('order_id' => null, 'sale_id' => $sale_id), array('order_id' => $id));
                $this->db->update('costing', array('order_id' => null, 'sale_id' => $sale_id, 'order_item_id' => null ), array('order_id' => $id));
            } else {
                $this->db->delete('payments', array('order_id' => $id));
                $this->db->delete('costing', array('order_id' => $id));
            }
            
            if($syncQuantity) {
                $this->site->syncQuantity(NULL, NULL, $order_items);
            }
            return true;
        }
        return FALSE;
    }
    
    public function resetOrderActions($id, $return_id = NULL, $check_return = NULL)
    {
        if ($order = $this->getOrderByID($id)) {
            if ($check_return && $order->sale_status == 'returned') {
                $this->session->set_flashdata('warning', lang('sale_x_action'));
                redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
            }

            if ($order->sale_status == 'completed') {
                $items = $this->getAllOrderItems($id);
                foreach ($items as $item) {
                    if ($item->product_type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            if($combo_item->type == 'standard') {
                                $qty = ($item->unit_quantity*$combo_item->qty);                                
                                $this->updatePurchaseItem(NULL, $qty, NULL, $combo_item->id, $item->warehouse_id);
                            }
                        }
                    } else {
                        $option_id = isset($item->option_id) && !empty($item->option_id) ? $item->option_id : NULL;
                        $this->updatePurchaseItem(NULL, $item->unit_quantity, $item->id, $item->product_id, $item->warehouse_id, $option_id);
                    }
                }
                if ($order->return_id || $return_id) {
                    $rid = $return_id ? $return_id : $order->return_id;
                    $returned_items = $this->getAllOrderItems(FALSE, $rid);
                    foreach ($returned_items as $item) {

                        if ($item->product_type == 'combo') {
                            $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                            foreach ($combo_items as $combo_item) {
                                if($combo_item->type == 'standard') {
                                    $qty = ($item->unit_quantity*$combo_item->qty);
                                    $this->updatePurchaseItem(NULL, $qty, NULL, $combo_item->id, $item->warehouse_id);
                                }
                            }
                        } else {
                            $option_id = isset($item->option_id) && !empty($item->option_id) ? $item->option_id : NULL;
                            $this->updatePurchaseItem(NULL, $item->unit_quantity, $item->id, $item->product_id, $item->warehouse_id, $option_id);
                        }

                    }
                }
                $this->site->syncQuantity(NULL, NULL, $items);
                //$this->sma->update_award_points($order->grand_total, $order->customer_id, $order->created_by, TRUE);
                return $items;
            }
        }
    }


    public function updatePurchaseItem($id, $qty, $order_item_id, $product_id = NULL, $warehouse_id = NULL, $option_id = NULL)
    {
        if ($id) {
            if($pi = $this->getPurchaseItemByID($id)) {
                $pr = $this->site->getProductByID($pi->product_id);
                if ($pr->type == 'combo') {
                    $combo_items = $this->site->getProductComboItems($pr->id, $pi->warehouse_id);
                    foreach ($combo_items as $combo_item) {
                        if($combo_item->type == 'standard') {
                            $cpi = $this->site->getPurchasedItem(array('product_id' => $combo_item->id, 'warehouse_id' => $pi->warehouse_id, 'option_id' => NULL));
                            $bln = $pi->quantity_balance + ($qty*$combo_item->qty);
                            $this->db->update('purchase_items', array('quantity_balance' => $bln), array('id' => $combo_item->id));
                        }
                    }
                } else {
                    $bln = $pi->quantity_balance + $qty;
                    $this->db->update('purchase_items', array('quantity_balance' => $bln), array('id' => $id));
                }
            }
        } else {
            if ($order_item_id) {
                if ($order_item = $this->getOrderItemByID($order_item_id)) {
                    $option_id = isset($order_item->option_id) && !empty($order_item->option_id) ? $order_item->option_id : NULL;
                    $clause = array('product_id' => $order_item->product_id, 'warehouse_id' => $order_item->warehouse_id, 'option_id' => $option_id);
                    if ($pi = $this->site->getPurchasedItem($clause)) {
                        $quantity_balance = $pi->quantity_balance+$qty;
                        $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $pi->id));
                    } else {
                        $clause['purchase_id'] = NULL;
                        $clause['transfer_id'] = NULL;
                        $clause['quantity'] = 0;
                        $clause['quantity_balance'] = $qty;
                        $this->db->insert('purchase_items', $clause);
                    }
                }
            } else {
                if ($product_id && $warehouse_id) {
                    $pr = $this->site->getProductByID($product_id);
                    $clause = array('product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'option_id' => $option_id);
                    if ($pr->type == 'standard') {
                        if ($pi = $this->site->getPurchasedItem($clause)) {
                            $quantity_balance = $pi->quantity_balance+$qty;
                            $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $pi->id));
                        } else {
                            $clause['purchase_id'] = NULL;
                            $clause['transfer_id'] = NULL;
                            $clause['quantity'] = 0;
                            $clause['quantity_balance'] = $qty;
                            $this->db->insert('purchase_items', $clause);
                        }
                    } elseif ($pr->type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($pr->id, $warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            $clause = array('product_id' => $combo_item->id, 'warehouse_id' => $warehouse_id, 'option_id' => NULL);
                            if($combo_item->type == 'standard') {
                                if ($pi = $this->site->getPurchasedItem($clause)) {
                                    $quantity_balance = $pi->quantity_balance+($qty*$combo_item->qty);
                                    $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), $clause);
                                } else {
                                    $clause['transfer_id'] = NULL;
                                    $clause['purchase_id'] = NULL;
                                    $clause['quantity'] = 0;
                                    $clause['quantity_balance'] = $qty;
                                    $this->db->insert('purchase_items', $clause);
                                }
                            }
                        }
                    }
                }
            }
        }
    }//end function

   
    public function updateCostingLine($order_item_id, $product_id, $quantity)
    {
        if ($costings = $this->getCostingLines($order_item_id, $product_id)) {
            foreach ($costings as $cost) {
                if ($cost->quantity >= $quantity) {
                    $qty = $cost->quantity - $quantity;
                    $bln = $cost->quantity_balance && $cost->quantity_balance >= $quantity ? $cost->quantity_balance - $quantity : 0;
                    $this->db->update('costing', array('quantity' => $qty, 'quantity_balance' => $bln), array('id' => $cost->id));
                    $quantity = 0;
                } elseif ($cost->quantity < $quantity) {
                    $qty = $quantity - $cost->quantity;
                    $this->db->delete('costing', array('id' => $cost->id));
                    $quantity = $qty;
                }
            }
            return TRUE;
        }
        return FALSE;
    }
    
    public function getCostingLines($order_item_id, $product_id, $order_id = NULL)
    {
        if ($sale_id) { $this->db->where('order_id', $order_id); }
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('costing', array('order_item_id' => $order_item_id, 'product_id' => $product_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function addSale( $data = array(), $items = array(), $payment = array(), $si_return = array(), $extrasPara = array() )
    {
        $this->load->model('sales_model');
        
        $cost = $this->site->costing($items);
         
        $sale_action    = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $order_id       = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity   = $extrasPara['syncQuantity'];
                 
        if ($this->db->insert('sales', $data)) {
            
            $sale_id = $this->db->insert_id();

            //Get formated Invoice No
            $invoice_no = $this->sma->invoice_format($sale_id,date());             
            //Update formated invoice no
            $this->db->where(['id'=>$sale_id])->update('sales', ['invoice_no' => $invoice_no]);
            
            if($order_id) {
                //Update sale_invoice_no after convert order into sales. 
               $this->db->where(['id'=>$order_id])->update('orders', ['sale_invoice_no' => $invoice_no]); 
            }
            // End Invoice No
            

            if ($this->site->getReference('so') == $data['reference_no']) {
                $this->site->updateReference('so');
            }
            if ($this->site->getReference('re') == $data['return_sale_ref']) {
               $this->site->updateReference('re');
            }
	    $Setting =   $this->Settings;
            
            foreach ($items as $item) {
		//------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                $_prd       =   $Setting->pos_type=='pharma' ?$this->site->getProductByID($item['product_id']):NULL;
                $item['cf1'] = $Setting->pos_type=='pharma' ?$_prd->cf1:'';
                $item['cf2'] = $Setting->pos_type=='pharma' ?$_prd->cf2:'';
                //------------------ End ----------------//
                $item['sale_id'] = $sale_id;
                $this->db->insert('sale_items', $item);
                $sale_item_id = $this->db->insert_id();
                    
                $_taxSaleID =  $sale_id;
                
                $_tax_type = ($sale_action == 'chalan' ? 'o' : NULL);
                
                $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID , $_tax_type);
                
                if($data['sale_status'] == 'completed') {

                    $item_costs = $this->site->item_costing($item);
                    
                    foreach ($item_costs as $item_cost) {
                        if (isset($item_cost['date'])) {
                             
                            $item_cost['sale_item_id'] = $sale_item_id;
                            $item_cost['sale_id'] = $sale_id;
                                                        
                            if(! isset($item_cost['pi_overselling'])) {
                                $this->db->insert('costing', $item_cost);
                            }
                        } else {
                            foreach ($item_cost as $ic) {
                            	if(is_array($ic)):
                                    if($sale_action == 'chalan'){
                                        $ic['order_item_id'] = $sale_item_id;
                                        $ic['order_id']      = $sale_id;
                                    } else {
                                        $ic['sale_item_id'] = $sale_item_id;
                                        $ic['sale_id']      = $sale_id;
                                    }

                                    if(! isset($ic['pi_overselling'])) {
                                        $this->db->insert('costing', $ic);
                                    }
                                endif;
                            }
                        }
                    }
                }                         
            }            

            if ($data['sale_status'] == 'completed' && $syncQuantity) {
                
                $this->site->syncPurchaseItems($cost);
            }

            if (!empty($si_return)) {
                foreach ($si_return as $return_item) {
                    $product = $this->site->getProductByID($return_item['product_id']);
                    if ($product->type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($return_item['product_id'], $return_item['warehouse_id']);
                        foreach ($combo_items as $combo_item) { 
                            
                            $this->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                            $this->updatePurchaseItem(NULL,($return_item['quantity']*$combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                        }
                    } else {                        
                        $this->updateCostingLine($return_item['id'], $return_item['product_id'], $return_item['quantity']);
                        $this->updatePurchaseItem(NULL, $return_item['quantity'], $return_item['id']);
                    }
                }
                $this->db->update('sales', array('return_sale_ref' => $data['return_sale_ref'], 'surcharge' => $data['surcharge'],'return_sale_total' => $data['grand_total'], 'return_id' => $sale_id), array('id' => $data['sale_id']));
            }

            if ($data['payment_status'] == 'partial' || $data['payment_status'] == 'paid' && !empty($payment)) {
                if (empty($payment['reference_no'])) {
                    $payment['reference_no'] = $this->site->getReference('pay');
                }
               
                $payment['sale_id']  = $sale_id;
                
                if ($payment['paid_by'] == 'gift_card') {
                    $this->db->update('gift_cards', array('balance' => $payment['gc_balance']), array('card_no' => $payment['cc_no']));
                    unset($payment['gc_balance']);
                    $this->db->insert('payments', $payment);
                } else {
                    if ($payment['paid_by'] == 'deposit') {
                        $customer = $this->site->getCompanyByID($data['customer_id']);
                        $this->db->update('companies', array('deposit_amount' => $payment['cc_holder']), array('id' => $data['customer_id']));
                        //$this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount-$payment['amount'])), array('id' => $customer->id));
                    }
                    $this->db->insert('payments', $payment);
                }
                if ($this->site->getReference('pay') == $payment['reference_no']) {
                    $this->site->updateReference('pay');
                }
                $this->site->syncSalePayments($sale_id);
            }
            
            if($syncQuantity) {                 
                $this->site->syncQuantity($sale_id);

              // Urbanpiper Stock Manage 
               /* if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                } */                    
            }            
            
            if ($this->Settings->synch_reward_points && $syncQuantity) {
                $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);
            }
            
            return $sale_id;
        }

        return false;
    }

    public function updateOrdersDeliveryStatus($order_id, array $updateItemsDelivery , $deliveryStatus){
        
        if ($this->db->update('orders', ['delivery_status'=>$deliveryStatus], array('id' => $order_id))) {
           
            if(is_array($updateItemsDelivery)){
                foreach ($updateItemsDelivery as $itm_id => $itemsStatus) {
                    
                    $this->db->update('order_items', $itemsStatus, array('id' => $itm_id));
                }//end foreach
            }//end if
            return true;
        }//end if
        
        return false;
    }
    
    public function getDeliveryItemByOrderID($order_id)
    {
        $q = $this->db->query("SELECT sum(delivered_quantity) as delivered , sum(quantity) as quantity FROM sma_order_items WHERE sale_id = '$order_id' ");
        if ($q->num_rows() > 0) {
             return $q->row();
        }

        return FALSE;
    }

    public function getDeliveryByOrderID($order_id)
    {
        $q = $this->db->get_where('deliveries', array('order_id' => $order_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    
    public function getPaymentsForOrder($order_id)
    {
        $this->db->select('payments.date, payments.paid_by, payments.amount,payments.transaction_id, payments.cc_no, payments.cheque_no, payments.reference_no, users.first_name, users.last_name, type')
            ->join('users', 'users.id=payments.created_by', 'left');
        $q = $this->db->get_where('payments', array('order_id' => $order_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    /********************************************************
	Start Eshop Order
	*********************************************************/
	public function addSaleFromEshopOrder( $data = array(), $items = array(), $extrasPara = array() )
    {
        $cost = $this->site->costing($items);        
        $sale_action    = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $order_id       = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity   = $extrasPara['syncQuantity'];
                 
        if ($this->db->insert('sales', $data)) {
            
            $sale_id = $this->db->insert_id();

            //Get formated Invoice No
            $invoice_no = $this->sma->invoice_format($sale_id, $data['date']);             
            //Update formated invoice no
            $this->db->where(['id'=>$sale_id])->update('sales', ['invoice_no' => $invoice_no]);
            
            if($order_id) {
                //Update sale_invoice_no after convert order into sales. 
               $this->db->where(['id'=>$order_id])->update('orders', ['sale_invoice_no' => $invoice_no]); 
               //Update order payment if exists
               $this->db->where(['order_id'=>$order_id])->update('payments', ['sale_id' => $sale_id]);
            }
            // End Invoice No
            
            if ($this->site->getReference('so') == $data['reference_no']) {
                $this->site->updateReference('so');
            }
            
	    $Setting =   $this->Settings;
            if($items) {
                foreach ($items as $item) {
                    //------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                    $_prd       =  $Setting->pos_type=='pharma' ?$this->site->getProductByID($item['product_id']):NULL;
                    $item['cf1'] = $Setting->pos_type=='pharma' ?$_prd->cf1:'';
                    $item['cf2'] = $Setting->pos_type=='pharma' ?$_prd->cf2:'';
                    //------------------ End ----------------//
                    $item['sale_id'] = $sale_id;
                    $this->db->insert('sale_items', $item);
                    $sale_item_id = $this->db->insert_id();

                    $_taxSaleID =  $sale_id;

                    $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID );

                    $this->db->where(['order_id' => $order_id, 'product_id'=>$item['product_id']])->update('costing', ['sale_id'=>$sale_id, 'sale_item_id'=>$sale_item_id]);



                     if ($data['sale_status'] == 'completed') {

                        $item_costs = $this->site->item_costing($item);

                        foreach ($item_costs as $item_cost) {
                            if (isset($item_cost['date'])) {
                                if ($sale_action == 'chalan') {
                                    $item_cost['order_item_id'] = $sale_item_id;
                                    $item_cost['order_id'] = $sale_id;
                                } else {
                                    $item_cost['sale_item_id'] = $sale_item_id;
                                    $item_cost['sale_id'] = $sale_id;
                                }
                                if (!isset($item_cost['pi_overselling'])) {
                                    unset($item_cost['unit_quantity']);
                                    $this->db->insert('costing', $item_cost);
                                }
                            } else {
                                foreach ($item_cost as $ic) {
                                    if (is_array($ic)):
                                        if ($sale_action == 'chalan') {
                                            $ic['order_item_id'] = $sale_item_id;
                                            $ic['order_id'] = $sale_id;
                                        } else {
                                            $ic['sale_item_id'] = $sale_item_id;
                                            $ic['sale_id'] = $sale_id;
                                        }

                                        if (!isset($ic['pi_overselling'])) {
                                            unset($ic['unit_quantity']);
                                            $this->db->insert('costing', $ic);
                                        }
                                    endif;
                                }
                            }
                        }
                    }

                }

                if ($data['sale_status'] == 'completed' && $syncQuantity) {

                      $this->site->syncPurchaseItems($cost);
                }
              
                 $this->site->syncQuantity($sale_id);

                return $sale_id;
            }
        }

        return false;
    }
	public function addSaleReturnFromShopOrderReturn( $data = array(), $items = array(), $extrasPara = array() )
    {               
        $sale_action    = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $sale_id       = $extrasPara['sale_id'] ? $extrasPara['sale_id'] : $data['sale_id'];
        $order_id       = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity   = $extrasPara['syncQuantity'];
                 
        if ($this->db->insert('sales', $data)) {
            
            $sale_return_id = $this->db->insert_id();
            
            if($sale_return_id && $sale_id){
                $this->db->where(['id' => $sale_id])->update('sales', ['return_id'=>$sale_return_id]);
            }
            
            if($order_id) {
                //Update sale_invoice_no after convert order into sales. 
               $this->db->where(['id'=>$order_id])->update('orders', ['sale_invoice_no' => $data['invoice_no']]); 
               //Update order payment if exists
               $this->db->where(['order_id'=>$order_id])->update('payments', ['sale_id' => $sale_return_id]);
            }
            // End Invoice No
            
            if ($this->site->getReference('re') == $data['return_sale_ref']) {
                $this->site->updateReference('re');
            }
            
	    $Setting = $this->Settings;
            if($items) {
                foreach ($items as $item) {
                    //------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                    $_prd       =  $Setting->pos_type=='pharma' ?$this->site->getProductByID($item['product_id']):NULL;
                    $item['cf1'] = $Setting->pos_type=='pharma' ?$_prd->cf1:'';
                    $item['cf2'] = $Setting->pos_type=='pharma' ?$_prd->cf2:'';
                    //------------------ End ----------------//
                    $item['sale_id'] = $sale_return_id;
                    $this->db->insert('sale_items', $item);
                    $sale_item_id = $this->db->insert_id();

                    $_taxSaleID = $sale_return_id;

                    $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID );

                    $this->db->where(['order_id' => $order_id, 'product_id'=>$item['product_id']])->update('costing', ['sale_id'=>$sale_return_id, 'sale_item_id'=>$sale_item_id]);
                }

                return $sale_return_id;
            }
        }

        return false;
    }
	public function getEshopOrderByID($id)
    {
        $q = $this->db->get_where('orders', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
	public function getEshopOrderInvoicePayments($sale_id)
    {
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('payments', array('order_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }
	public function getAllEshopOrderItems($sale_id, $return_id = NULL)
    {
        $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, product_variants.name as variant, product_variants.price as variant_price, products.hsn_code as hsncode, orders.rounding as rounding')
            ->join('products', 'products.id=order_items.product_id', 'left')
            ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
            ->join('orders', 'orders.id=order_items.sale_id', 'left') 
            ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
            ->group_by('order_items.id')
            ->order_by('id', 'asc');
        if ($sale_id && !$return_id) {
            $this->db->where('order_items.sale_id', $sale_id);
        } elseif ($return_id) {
            $this->db->where('order_items.sale_id', $return_id);
        }
        $q = $this->db->get('order_items');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }            
            return $data;
        }
        return FALSE;
    }
	public function updateEshopOrder($id, $data, $items = array(), $payment)
    {
        $this->resetEshopOrderActions($id, FALSE, TRUE);
        $customer_id = $data['customer_id'];
        $customer_state_code = $this->sma->getstatecode($customer_id);
        
        $billers_id  = $data['biller_id'];
        $billers_state_code = $this->sma->getstatecode($billers_id);
        $GSTType = ($customer_state_code == $billers_state_code)? 'GST':'IGST';	
         //echo $data['biller_id'] ; echo $data['customer_id'];
        // echo 'billstate'.$billers_state_code; echo 'custstate'.$customer_state_code;echo $GSTType;exit;	
        if ($data['sale_status'] == 'completed') {
            $cost = $this->site->order_costing($items);
        }
     
        if ($this->db->update('orders', $data, array('id' => $id)) && 
            $this->db->delete('order_items', array('sale_id' => $id)) && 
            $this->db->delete('costing', array('order_id' => $id))) {
	    $this->db->delete('orders_items_tax', array('order_id' => $id));
            if(!empty($items)){
                foreach ($items as $item) {

                $item['sale_id'] = $id;
                $this->db->insert('order_items', $item);
                $sale_item_id = $this->db->insert_id();
                
                $_taxSaleID = $id;
                $_tax_type = 'o';
             
                $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'],$item['net_unit_price'],$item['unit_quantity'],$sale_item_id,$_taxSaleID,$_tax_type);
                
                  /*Add New field to Sale_items Code cgst,igst,sgst 17-1-2020*/
             

                $tax_ItemAtrr = $this->sma->taxArr_rate_gst($item['tax_rate_id'], $item['net_unit_price'], $item['quantity'], $sale_item_id, $_taxSaleID,$GSTType);
           
             
                if($GSTType != 'IGST'){
                $cgst = $tax_ItemAtrr[0]['CGST'] !="" ? $tax_ItemAtrr[0]['CGST'] : 0;
                $sgst = $tax_ItemAtrr[1]['SGST'] !="" ? $tax_ItemAtrr[1]['SGST'] : 0;
                $igst = 0;
                }else{
                $cgst = 0;
                $sgst = 0;
                $igst = $tax_ItemAtrr[0]['IGST'] !="" ? $tax_ItemAtrr[0]['IGST'] : 0;   
                } 

                
                $this->db->update('order_items', array('gst_rate' => $tax_ItemAtrr[0]['attr_per'], 'cgst' => $cgst,'sgst' => $sgst, 'igst' =>  $igst), array('id' => $sale_item_id));
               
                /**/
                              
                if ($data['sale_status'] == 'completed' && $this->site->getProductByID($item['product_id'])) {
                   
                    $item_costs = $this->site->order_item_costing($item);
                     
                    if(!empty($item_costs)) {
                        foreach($item_costs as $item_cost) {
                        if(isset($item_cost['date'])) {
                            $item_cost['order_item_id']  = $sale_item_id;
                            $item_cost['order_id']       = $id;
                            if(!isset($item_cost['pi_overselling'])) {
                                $this->db->insert('costing', $item_cost);
                            }
                        } else {
                             
                            if(!empty($item_cost) && (is_array($item_cost) || is_object($item_cost))){                                 
                                    foreach ($item_cost as $key=>$ic) {                               
                                        $item_cost['order_item_id'] = $sale_item_id;
                                        $item_cost['order_id'] = $id;
                                        
                                        if(! isset($item_cost['pi_overselling'])) {                                           
                                            $this->db->insert('costing', $item_cost);
                                        }
                                    }
                                }
                            }
                        }
                 
   }
                }
            /*New Field Add to Sales cgst,igst,sgst 17-1-2020 */
            $total_cgst  = $total_cgst + $cgst;
            $total_sgst  = $total_sgst + $sgst;
            $total_igst  = $total_igst + $igst;
           
            }
            $this->db->update('orders', array('cgst' => $total_cgst, 'sgst' => $total_sgst,'igst' => $total_igst), array('id' => $id));
        }
         /**/
        if ($data['sale_status'] == 'completed') {
            $this->site->syncPurchaseItems($cost);
        }
		if ($data['payment_status'] == 'partial' || $data['payment_status'] == 'paid' && !empty($payment)) {
                if (empty($payment['reference_no'])) {
                    $payment['reference_no'] = $this->site->getReference('pay');
                }
                
                $payment['order_id'] = $id;
                
                if ($payment['paid_by'] == 'gift_card') {
                    $this->db->update('gift_cards', array('balance' => $payment['gc_balance']), array('card_no' => $payment['cc_no']));
                    unset($payment['gc_balance']);
                    $sqlpayment = $this->db->where(['order_id'=>$id])->get('payments')->row();
                    if($sqlpayment){
                        $this->db->where(['order_id'=>$id])->update('payments',$payment);
                    }else{
                    $this->db->insert('payments', $payment);
                    }
                } else {
                    if ($payment['paid_by'] == 'deposit') {
                        $customer = $this->site->getCompanyByID($data['customer_id']);
                        $this->db->update('companies', array('deposit_amount' => $payment['cc_holder']), array('id' => $data['customer_id']));
                        //$this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount-$payment['amount'])), array('id' => $customer->id));
                    }
                    $sqlpayment = $this->db->where(['order_id'=>$id])->get('payments')->row();
                    if($sqlpayment){
                        $this->db->where(['order_id'=>$id])->update('payments',$payment);
                    }else{
                    $this->db->insert('payments', $payment);
                    }
                }
                if ($this->site->getReference('pay') == $payment['reference_no']) {
                    $this->site->updateReference('pay');
                }
            }
			$this->site->syncSaleActionPayments($id, 'eshop_order');
           // if($syncQuantity) {
                $this->site->syncQuantity( NULL, NULL, NULL, NULL, $id );
            //}            
            
         // can setting for each order because if each sale in sale is true then add award point to user
            $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);

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
	public function resetEshopOrderActions($id, $return_id = NULL, $check_return = NULL)
    {
        if ($sale = $this->getEshopOrderByID($id)) {
            if ($check_return && $sale->sale_status == 'returned') {
                $this->session->set_flashdata('warning', lang('sale_x_action'));
                redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
            }

            if ($sale->sale_status == 'completed') {
                $items = $this->getAllEshopOrderItems($id);
                foreach ($items as $item) {
                    if ($item->product_type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            if($combo_item->type == 'standard') {
                                $qty = ($item->quantity*$combo_item->qty);
                                $this->updatePurchaseItem(NULL, $qty, NULL, $combo_item->id, $item->warehouse_id);
                            }
                        }
                    } else {
                        $option_id = isset($item->option_id) && !empty($item->option_id) ? $item->option_id : NULL;
                        $this->updatePurchaseItem(NULL, $item->quantity, $item->id, $item->product_id, $item->warehouse_id, $option_id);
                    }
                }
                if ($sale->return_id || $return_id) {
                    $rid = $return_id ? $return_id : $sale->return_id;
                    $returned_items = $this->getAllEshopOrderItems(FALSE, $rid);
                    foreach ($returned_items as $item) {

                        if ($item->product_type == 'combo') {
                            $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                            foreach ($combo_items as $combo_item) {
                                if($combo_item->type == 'standard') {
                                    $qty = ($item->quantity*$combo_item->qty);
                                    $this->updatePurchaseItem(NULL, $qty, NULL, $combo_item->id, $item->warehouse_id);
                                }
                            }
                        } else {
                            $option_id = isset($item->option_id) && !empty($item->option_id) ? $item->option_id : NULL;
                            $this->updatePurchaseItem(NULL, $item->quantity, $item->id, $item->product_id, $item->warehouse_id, $option_id);
                        }

                    }
                }
                $this->site->syncQuantity(NULL, NULL, $items);
                $this->sma->update_award_points($sale->grand_total, $sale->customer_id, $sale->created_by, TRUE);
                return $items;
            }
        }
    }
	public function updateStatus($id, $data = array())
    {
        if ($this->db->update('orders', $data, array('id' => $id))) {
            return true;
        }
        return false;
    }
	public function getPaymentByID($id)
    {
        $q = $this->db->get_where('payments', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
	public function updateEshopOrderPayment($id, $data = array(), $customer_id = null)
    {
        $opay = $this->getPaymentByID($id);
        if ($this->db->update('payments', $data, array('id' => $id))) {
			//echo $data['sale_id'];
            $this->site->syncOrderPayments($data['sale_id']);
            if ($opay->paid_by == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($opay->cc_no);
                $this->db->update('gift_cards', array('balance' => ($gc->balance+$opay->amount)), array('card_no' => $opay->cc_no));
            } elseif ($opay->paid_by == 'deposit') {
                if (!$customer_id) {
                    $sale = $this->getEshopOrderByID($opay->order_id);
                    $customer_id = $sale->customer_id;
                }
                $customer = $this->site->getCompanyByID($customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount+$opay->amount)), array('id' => $customer->id));
            }
            if ($data['paid_by'] == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($data['cc_no']);
                $this->db->update('gift_cards', array('balance' => ($gc->balance - $data['amount'])), array('card_no' => $data['cc_no']));
            } elseif ($customer_id && $data['paid_by'] == 'deposit') {
                $customer = $this->site->getCompanyByID($customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount-$data['amount'])), array('id' => $customer_id));
            }
            return true;
        }
        return false;
    }
	public function deletePayment($id)
    {
        $opay = $this->getPaymentByID($id);
        if ($this->db->delete('payments', array('id' => $id))) {
            $this->site->syncOrderPayments($opay->order_id);
            if ($opay->paid_by == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($opay->cc_no);
                $this->db->update('gift_cards', array('balance' => ($gc->balance+$opay->amount)), array('card_no' => $opay->cc_no));
            } elseif ($opay->paid_by == 'deposit') {
                $sale = $this->getEshopOrderByID($opay->order_id);
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount+$opay->amount)), array('id' => $customer->id));
            }
            return true;
        }
        return FALSE;
    }
public function ordersRounding($order_id) {
        $rounding = $this->db->select('rounding')->where(['id' => $sale_id])->get('sma_orders')->row();
        return $rounding;
    }
public function getPurchaseItemByID($id)
    {
        $q = $this->db->get_where('purchase_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
public function getWarehouseByID($id)
    {
        $q = $this->db->get_where('warehouses', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
	/********************************************************
	End Eshop Order
	*********************************************************/
	public function getAllReturnOrderItemByItemID($id)
    {
        $q = $this->db->get_where('sma_order_items', array('sale_item_id' => $id));
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }
	public function getAllReturnOrderByID($id)
    {
        $q = $this->db->get_where('orders', array('sale_id' => $id));
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }
	public function getAllReturnOrderItems($sale_id, $return_id = NULL)
    {
        $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, product_variants.name as variant, product_variants.price as variant_price, products.hsn_code as hsncode, orders.rounding as rounding')
            ->join('products', 'products.id=order_items.product_id', 'left')
            ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
            ->join('orders', 'orders.id=order_items.sale_id', 'left') 
            ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
            ->group_by('order_items.id')
            ->order_by('id', 'asc');
       $this->db->where('orders.sale_id', $sale_id);
        $q = $this->db->get('order_items');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }            
            return $data;
        }
        return FALSE;
    }
	public function getOrderPayments1($order_id) {
		$q = $this->db->query("select * from sma_payments where order_id in($order_id)");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }

        return FALSE;
    }
	function orderRounding($sale_id) {
		$rounding = $this->db->select('rounding')->where(['id' => $sale_id])->get('sma_orders')->row();
		return $rounding;
	}
public function getOrderDetails($id)
    {
        $q = $this->db->get_where('eshop_order', array('sale_id' => $id));
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }
	public function getEshopOrderByInvoice($id)
    {
        $q = $this->db->get_where('orders', array('invoice_no' => $id));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /**
     * Get Eshop Settings
     * @return type
     */
   public function getEshopSettings(){
       $getEshopSetting =  $this->db->where(['id'=>'1'])->get('sma_eshop_settings')->row();
       return $getEshopSetting;
   }


    /**
    * Biling and Shiping address get
    */
   public function getShipingAdress($id){
      $address =  $this->db->where(['id'=>$id])->get('sma_addresses')->row();
      return $address;
      
   }
    
}//End Class
