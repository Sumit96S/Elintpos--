<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Sales_model extends CI_Model {

    private $sales;

    public function __construct() {
        parent::__construct();

        $this->sales = [];
    }

//     public function getProductNames($term, $warehouse_id, $limit = 20, $warehouseRes = NULL) {
//       //  $wp = "( SELECT product_id, warehouse_id, quantity as quantity from {$this->db->dbprefix('warehouses_products')} ) FWP";

//        // $this->db->select('products.*, FWP.quantity as wp_quantity, categories.id as category_id, categories.name as category_name', FALSE)
//         $this->db->select('products.*, categories.id as category_id, categories.name as category_name', FALSE)
//                 //->join($wp, 'FWP.product_id=products.id', 'left')
//                 ->join('categories', 'categories.id=products.category_id', 'left')
//                 ->group_by('products.id');
//         //if ($this->Settings->overselling) {
//             /* $this->db->where("({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')"); */

//             $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',{$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'  OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");


//            // if ((int) $warehouse_id > 0 && !empty($warehouseRes)):
//             //$this->db->where("FWP.warehouse_id = '".$warehouse_id."'");
//            // endif;
//        // } else {
//             /* $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
//               . "({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')"); */
// //            $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
// //                    . "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
//        // }
//         $this->db->limit($limit);
//         $q = $this->db->get('products');

//         if ($q->num_rows() > 0) {
//             foreach (($q->result()) as $row) {
//                 $data[] = $row;
//             }
//             return $data;
//         }
//     }

    public function getProductNames($term, $warehouse_id, $limit = 50, $warehouseRes=NULL, $product_Id = null){
        $wp = "( SELECT product_id, warehouse_id, quantity as quantity from {$this->db->dbprefix('warehouses_products')} ) FWP";

        $this->db->select('products.*, FWP.quantity as quantity, categories.id as category_id, categories.name as category_name, sma_combo_items.quantity as comboquantity, ' . (int)$product_Id . ' as product_id', FALSE)
            ->join($wp, 'FWP.product_id=products.id', 'left')
            ->join('categories', 'categories.id=products.category_id', 'left')
            ->join('categories as subcategories', 'subcategories.id=categories.parent_id', 'left')
            ->join('brands', 'brands.id=products.brand', 'left')
            ->join('seasons', 'seasons.id=products.season_id', 'left')
            ->join('sma_combo_items', 'sma_combo_items.product_id = products.id', 'left')
            ->group_by('products.id');
        if ($this->Settings->overselling) {
           /* $this->db->where("({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')"); */
    
            //    $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',{$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%".str_replace(" ","",$term)."%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
            $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', REPLACE(COALESCE({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') 
            OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' 
            OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' 
            OR CONCAT({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'
            OR brands.name LIKE '%" . $term . "%'
            OR categories.name LIKE '%" . $term . "%'
            OR seasons.SeasonName LIKE '%" . $term . "%'
            OR subcategories.name LIKE '%" . $term . "%')");

             if((int)$warehouse_id > 0 && !empty($warehouseRes)):
                //$this->db->where("FWP.warehouse_id = '".$warehouse_id."'");
            endif;
            
        } else {
            /* $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
                . "({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%')"); */

            //$this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
            //. "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%".str_replace(" ","",$term)."%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
            
            $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
            . "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', REPLACE(COALESCE({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') 
            OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' 
            OR CONCAT({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'
            OR brands.name LIKE '%" . $term . "%'
            OR categories.name LIKE '%" . $term . "%'
            OR seasons.SeasonName LIKE '%" . $term . "%'
            OR subcategories.name LIKE '%" . $term . "%')");
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

    public function getProductComboItems($pid, $warehouse_id = NULL) {
        $this->db->select('products.id as id, combo_items.item_code as code, combo_items.quantity as qty,combo_items.unit_price, products.name as name,products.type as type, warehouses_products.quantity as quantity')
                ->join('products', 'products.code=combo_items.item_code', 'left')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->group_by('combo_items.id');
        if ($warehouse_id) {
            $this->db->where('warehouses_products.warehouse_id', $warehouse_id);
        }
        $q = $this->db->get_where('combo_items', array('combo_items.product_id' => $pid));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
        return FALSE;
    }

    public function getProductByCode($code) {
        $q = $this->db->get_where('products', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function syncQuantity($sale_id) {
        if ($sale_items = $this->getAllInvoiceItems($sale_id)) {
            foreach ($sale_items as $item) {
                $this->site->syncProductQty($item->product_id, $item->warehouse_id);
                if (isset($item->option_id) && !empty($item->option_id)) {
                    $this->site->syncVariantQty($item->option_id, $item->warehouse_id);
                }
            }
        }
    }

    public function getProductQuantity($product_id, $warehouse) {
        $q = $this->db->get_where('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse), 1);
        if ($q->num_rows() > 0) {
            return $q->row_array(); //$q->row();
        }
        return FALSE;
    }

    public function getProductOptions($product_id, $warehouse_id, $all = NULL) {
        $wpv = "( SELECT option_id, warehouse_id, quantity from {$this->db->dbprefix('warehouses_products_variants')} WHERE product_id = {$product_id}) FWPV";
        $this->db->select('product_variants.id as id, product_variants.name as name, product_variants.price as price, product_variants.quantity as total_quantity, product_variants.unit_quantity as unit_quantity, FWPV.quantity as quantity', FALSE)
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

    public function getProductVariants($product_id) {
        $q = $this->db->get_where('product_variants', ['product_id' => $product_id]);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getProductoptionData($product_id) {
        
        $this->db->order_by('name', 'ASC'); 
        $q = $this->db->get_where('product_variants', ['product_id' => $product_id]);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getItemByID($id) {

        $q = $this->db->get_where('sale_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getSalesItemBySaleID($sale_id) {
        $q = $this->db->get_where('sale_items', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }

        return FALSE;
    }

    public function getAllInvoiceItems($sale_id, $return_id = NULL) {
        $this->db->select('sale_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, product_variants.name as variant, products.image , product_variants.price as variant_price, products.hsn_code as hsncode, sales.rounding as rounding')
                ->join('products', 'products.id=sale_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                ->join('sales', 'sales.id=sale_items.sale_id', 'left')
                ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
                ->group_by('sale_items.id')
                ->order_by('id', 'asc');
        if ($sale_id && !$return_id) {
            $this->db->where('sale_items.sale_id', $sale_id);
        } elseif ($return_id) {
            $this->db->where('sale_items.sale_id', $return_id);
        }
        $q = $this->db->get('sale_items');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllInvoiceItemsWithDetails($sale_id) {
        $this->db->select('sale_items.*, products.details, product_variants.name as variant');
        $this->db->join('products', 'products.id=sale_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                ->group_by('sale_items.id');
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('sale_items', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getInvoiceByID($id) {
        $q = $this->db->get_where('sales', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getSaleByInvoiceNo($invoice_no) {
        $q = $this->db->get_where('sales', array('invoice_no' => $invoice_no), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getReturnByID($id) {
        $q = $this->db->get_where('sales', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getReturnBySID($sale_id) {
        $q = $this->db->get_where('sales', array('sale_id' => $sale_id), 1);
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

    public function getCreditNoteHistoryByID($customer_id, $card_no) {
        $q = $this->db->query("SELECT date,sales_id as invoice_id,amount,cc_holder as balance_amt FROM view_sales_history WHERE  company_id = '$customer_id' AND cc_no='$card_no' AND paid_by='credit_note' ORDER BY sales_id DESC LIMIT 10");

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function topupCreditNote($data = array(), $card_data = NULL) {
        if ($this->db->insert('credit_note_topups', $data)) {
            $this->db->update('credit_note', $card_data, array('id' => $data['card_id']));
            return true;
        }
        return false;
    }

    public function addCreditNote($data = array(), $ca_data = array(), $sa_data = array()) {
        if ($this->db->insert('credit_note', $data)) {
            if (!empty($ca_data)) {
                $this->db->update('companies', array('award_points' => $ca_data['points']), array('id' => $ca_data['customer']));
            } elseif (!empty($sa_data)) {
                $this->db->update('users', array('award_points' => $sa_data['points']), array('id' => $sa_data['user']));
            }
            return true;
        }
        return false;
    }

    public function updateCreditNote($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('credit_note', $data)) {
            return true;
        }
        return false;
    }

    public function deleteCreditNote($id) {
        if ($this->db->delete('credit_note', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function CreateCreditNote($gift_card_data) {
        $this->db->insert('sma_credit_note', $gift_card_data);
        return ($this->db->affected_rows()) ? $this->db->insert_id() : FALSE;
    }

    public function getAllCreditNoteTopups($card_id) {
        $this->db->select("{$this->db->dbprefix('credit_note_topups')}.*, {$this->db->dbprefix('users')}.first_name, {$this->db->dbprefix('users')}.last_name, {$this->db->dbprefix('users')}.email")
                ->join('users', 'users.id=credit_note_topups.created_by', 'left')
                ->order_by('id', 'desc')->limit(10);
        $q = $this->db->get_where('credit_note_topups', array('card_id' => $card_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /**
     * 
     * @param type $no
     * @return boolean
     */
    public function checkCreaditNo($no) {
        $this->db->where(['card_no' => $no])->get('credit_note')->row();
        if ($this->db->affected_rows()) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /*     * *02-09-2020** */

    public function addSale($data = [], $items = [], $payments = [], $si_return = [], $extrasPara = []) {
        
        $this->load->model('orders_model');
        if($data['sale_status'] !='returned'){
           $cost = $this->site->costing($items);       
         }  
        $sale_action = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $order_id = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity = $extrasPara['syncQuantity'];

        if ($sale_action == 'chalan') {
            $sma_sales = 'orders';
            $sma_sales_items = 'order_items';
            $sma_sales_items_tax = 'orders_items_tax';
            $saleRefKey = 'ordr';
            $ReturnSaleRefKey = 're_ordr';
            $data['sale_as_chalan'] = 1;
        } else {
            $sma_sales = 'sales';
            $sma_sales_items = 'sale_items';
            $sma_sales_items_tax = 'sales_items_tax';
            $saleRefKey = 'so';
            $ReturnSaleRefKey = 're';
        }

        if ($this->db->insert($sma_sales, $data)) {

            $sale_id = $this->db->insert_id();
            $todaydate = $data['date']?date('Y-m-d',strtotime($data['date'])) :date('Y-m-d');
            //Get formated Invoice No
            // $invoice_no = $this->sma->invoice_format($sale_id,date());   
            $invoice_no = ($sale_action == 'chalan') ? $sale_id : $this->sma->invoice_format($sale_id, $todaydate);
            //Update formated invoice no
            $this->db->where(['id' => $sale_id])->update($sma_sales, ['invoice_no' => $invoice_no]);

            if ($order_id) {
                //Update sale_invoice_no after convert order into sales. 
                $this->db->where(['id' => $order_id])->update('orders', ['sale_invoice_no' => $invoice_no]);
            }
            // End Invoice No

            if ($this->site->getReference($saleRefKey) == $data['reference_no']) {
                $this->site->updateReference($saleRefKey);
            }
            if (isset($data['return_sale_ref']) &&  $this->site->getReference($ReturnSaleRefKey) == $data['return_sale_ref']) {
                $this->site->updateReference($ReturnSaleRefKey);
            }
            $Setting = $this->Settings;

            foreach ($items as $item) {
                //------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                //$_prd       =   $Setting->pos_type=='pharma' ?$this->site->getProductByID($item['product_id']):NULL;
                //$item['cf1'] = $Setting->pos_type=='pharma' ?$_prd->cf1:'';
                //$item['cf2'] = $Setting->pos_type=='pharma' ?$_prd->cf2:'';
                //------------------ End ----------------//
                $item['sale_id'] = $sale_id;
                $this->db->insert($sma_sales_items, $item);
                $sale_item_id = $this->db->insert_id();

                $DatalogArr = array('item' => $item, 'sale_item_id' => $sale_item_id);
                $DataLog = array(
                    'action_type' => 'Sale Products',
                    'product_id' => $item['product_id'],
                    'option_id' => $item['option_id'],
                    'batch_number' => $item['batch_number'] ? $item['batch_number'] : NULL,
                    'quantity' => $item['quantity'],
                    'action_reff_id' => "sma_sales.id:$sale_id",
                    'action_affected_data' => json_encode($DatalogArr),
                    'action_comment' => 'Add Sale'
                );
                $this->sma->setUserActionLog($DataLog);

                $_taxSaleID =  $sale_id;
                $_tax_type = ($sale_action == 'chalan' ? 'o' : NULL);
                $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID , $_tax_type);

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

            if (!empty($si_return)) {
                foreach ($si_return as $return_item) {
                    
                    $purchase_item_id = null;
                   
                    if ($this->Settings->overselling == 0) {
                        $costing_cause = ['sale_id'=>$return_item['sale_id'], 'product_id'=>$return_item['product_id'], 'sale_item_id'=>$return_item['id']];
                        $costingItem = $this->site->getProductCostings($costing_cause);                       
                        $purchase_item_id = $costingItem ? $costingItem->purchase_item_id : null;
                    }
                        
                    $product = $this->site->getProductByID($return_item['product_id']);
                    if ($product->type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($return_item['product_id'], $return_item['warehouse_id']);
                        foreach ($combo_items as $combo_item) {
                            if ($sale_action == 'chalan') {
                                $this->orders_model->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                                $this->orders_model->updatePurchaseItem($purchase_item_id, ($return_item['quantity'] * $combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                            } else {
                                $this->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                                $this->updatePurchaseItem($purchase_item_id, ($return_item['quantity'] * $combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                            }
                            /* if($sale_action == 'sale') {
                              $this->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                              $this->updatePurchaseItem(NULL,($return_item['quantity']*$combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                              } */
                        }
                    } elseif ($product->type == 'Bundle') {
                        $combo_items = $this->site->getProductComboItems($return_item['product_id'], $return_item['warehouse_id']);
                        foreach ($combo_items as $combo_item) {
                            if ($sale_action == 'chalan') {
                                $this->orders_model->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                                $this->orders_model->updatePurchaseItem($purchase_item_id, ($return_item['quantity'] * $combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                            } else {
                                $this->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                                $this->updatePurchaseItem($purchase_item_id, ($return_item['quantity'] * $combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                            }
                            /* if($sale_action == 'sale') {
                              $this->updateCostingLine($return_item['id'], $combo_item->id, $return_item['quantity']);
                              $this->updatePurchaseItem(NULL,($return_item['quantity']*$combo_item->qty), NULL, $combo_item->id, $return_item['warehouse_id']);
                              } */
                        }
                    }else {
                        
                        if ($sale_action == 'chalan') {
                            $this->orders_model->updateCostingLine($return_item['id'], $return_item['product_id'], $return_item['quantity']);
                            $this->orders_model->updatePurchaseItem($purchase_item_id, $return_item['quantity'], $return_item['id']);
                        } else {
                            $this->updateCostingLine($return_item['id'], $return_item['product_id'], $return_item['quantity']);
                            $this->updatePurchaseItem($purchase_item_id, $return_item['quantity'], $return_item['id']);
                        }
                        /* if($sale_action == 'sale') {
                          $this->updateCostingLine($return_item['id'], $return_item['product_id'], $return_item['quantity']);
                          $this->updatePurchaseItem(NULL, $return_item['quantity'], $return_item['id']);
                          } */
                    }
                }
                $this->db->update($sma_sales, array('return_sale_ref' => $data['return_sale_ref'], 'surcharge' => $data['surcharge'], 'return_sale_total' => $data['grand_total'], 'return_id' => $sale_id), array('id' => $data['sale_id']));
            }

            /* if ($data['payment_status'] == 'partial' || $data['payment_status'] == 'paid' && !empty($payment)) {
              if (empty($payment['reference_no'])) {
              $payment['reference_no'] = $this->site->getReference('pay');
              }

              if($sale_action == 'chalan') {
              $payment['order_id'] = $sale_id;
              } else {
              $payment['sale_id']  = $sale_id;
              }

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
              //$this->site->syncSalePayments($sale_id);
              $this->site->syncSaleActionPayments($sale_id, $sale_action);
              } */
            if ($sale_action == 'sale_return') {
                if ($data['payment_status'] == 'partial' || $data['payment_status'] == 'paid' && !empty($payments)) {
                    if (empty($payments['reference_no'])) {
                        $payments['reference_no'] = $this->site->getReference('pay');
                    }

                    if ($sale_action == 'chalan') {
                        $payments['order_id'] = $sale_id;
                    } else {
                        $payments['sale_id'] = $sale_id;
                    }

                    if ($payments['paid_by'] == 'gift_card') {
                        $this->db->update('gift_cards', array('balance' => $payments['gc_balance']), array('card_no' => $payments['cc_no']));
                        unset($payments['gc_balance']);
                        $this->db->insert('payments', $payments);
                    } elseif ($payments['paid_by'] == 'credit_note') {
                        $this->db->update('credit_note', array('balance' => $payments['gc_balance']), array('card_no' => $payments['cc_no']));
                        unset($payments['gc_balance']);
                        $this->db->insert('payments', $payments);
                    } else {
                        if ($payments['paid_by'] == 'deposit') {
                            $customer = $this->site->getCompanyByID($data['customer_id']);
                            $this->db->update('companies', array('deposit_amount' => $payments['cc_holder']), array('id' => $data['customer_id']));
                            //$this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount-$payments['amount'])), array('id' => $customer->id));
                        }
                        $this->db->insert('payments', $payments);
                    }
                    if ($this->site->getReference('pay') == $payments['reference_no']) {
                        $this->site->updateReference('pay');
                    }
                    //$this->site->syncSalePayments($sale_id);
                    $this->site->syncSaleActionPayments($sale_id, $sale_action);
                }
            } else {
                /*                 * *
                 * Multiple payment logic
                 * * */
                $msg = array();
                if (($data['payment_status'] == 'partial' || $data['payment_status'] == 'paid') && !empty($payments)) {
                    //print_r($payments);
                    $paid = 0;
                    foreach ($payments as $payment) {
                        if (!empty($payment) && isset($payment['amount']) && $payment['amount'] != 0) {
                            if (empty($payment['reference_no'])) {
                                $payment['reference_no'] = $this->site->getReference('pay');
                            }

                            if ($sale_action == 'chalan') {
                                $payment['order_id'] = $sale_id;
                            } else {
                                $payment['sale_id'] = $sale_id;
                            }
                            if ($payment['paid_by'] == 'gift_card') {
                                $this->db->update('gift_cards', array('balance' => $payment['gc_balance']), array('card_no' => $payment['cc_no']));
                                unset($payment['gc_balance']);
                            } elseif ($payment['paid_by'] == 'credit_note') {

                                $this->db->update('credit_note', array('balance' => $payment['gc_balance']), array('card_no' => $payment['cc_no']));
                                unset($payment['gc_balance']);
                            } elseif ($payment['paid_by'] == 'deposit') {
                                $customer = $this->site->getCompanyByID($data['customer_id']);
                                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount - $payment['amount'])), array('id' => $customer->id));
                            }

                            $this->db->insert('payments', $payment);
                            if ($this->site->getReference('pay') == $payment['reference_no']) {
                                $this->site->updateReference('pay');
                            }
                            $paid += $payment['amount'];
                        }
                    }
                    $this->site->syncSaleActionPayments($sale_id, $sale_action);
                }

                /*                 * *
                 *  End Multiple payment logic
                 * * */
            }
            if ($syncQuantity) {
                if ($sale_action == 'chalan') {
                    $this->site->syncQuantity(NULL, NULL, NULL, NULL, $sale_id);
                } else {
                    $this->site->syncQuantity($sale_id);
                }

                 // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $data['warehouse_id']);
                }  
                
            }

            if ($this->Settings->synch_reward_points) {
                $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);
            }

            return $sale_id;
        }

        return false;
    }

    public function updateSale($id, $data, $items = array()) {
        
        $this->resetSaleActions($id, FALSE, TRUE);
       
        $customer_id = $data['customer_id'];
        $customer_state_code = $this->sma->getstatecode($customer_id);

        $billers_id = $data['biller_id'];
        $billers_state_code = $this->sma->getstatecode($billers_id);
        $GSTType = ($customer_state_code == $billers_state_code) ? 'GST' : 'IGST';

      if($data['sale_status'] == 'completed') {
            $cost = $this->site->costing($items);
        }

        if ($this->db->update('sales', $data, array('id' => $id))) {
            $this->sma->storeDeletedData('sales', 'id', $id, 0);
            $this->db->delete('sale_items', array('sale_id' => $id));
            $this->db->delete('costing', array('sale_id' => $id));
            $this->db->delete('sales_items_tax', array('sale_id' => $id));
            $this->db->delete('user_action_logs', array('action_reff_id' => "sma_sales.id:$id"));

            if (!empty($items)) {
                foreach ($items as $item) {

                    $item['sale_id'] = $id;
                    $this->db->insert('sale_items', $item);
                    $sale_item_id = $this->db->insert_id();

                    $DatalogArr = array('item' => $item, 'sale_item_id' => $sale_item_id);
                    $DataLog = array(
                        'action_type'       => 'Sale Products',
                        'product_id'        => $item['product_id'],
                        'option_id'         => $item['option_id'],
                        'batch_number'      => $item['batch_number'] ? $item['batch_number'] : NULL,
                        'quantity'          => $item['quantity'],
                        'action_reff_id'    => "sma_sales.id:$sale_id",
                        'action_affected_data' => json_encode($DatalogArr),
                        'action_comment'    => 'Edit Sale'
                    );
                    $this->sma->setUserActionLog($DataLog);

                    $_taxSaleID = $id;
                    $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'],$item['unit_quantity'], $sale_item_id, $_taxSaleID);

                    /* Add New field to Sale_items Code cgst,igst,sgst 17-1-2020 */
                    $tax_ItemAtrr = $this->sma->taxArr_rate_gst($item['tax_rate_id'], $item['net_unit_price'], $item['quantity'], $sale_item_id, $sale_id, $GSTType);

                    //if($tax_ItemAtrr[0]['attr_code'] != 'IGST'){
                    if ($GSTType != 'IGST') {
                        $cgst = $tax_ItemAtrr[0]['CGST'] != "" ? $tax_ItemAtrr[0]['CGST'] : 0;
                        $sgst = $tax_ItemAtrr[1]['SGST'] != "" ? $tax_ItemAtrr[1]['SGST'] : 0;
                        $igst = 0;
                    } else {
                        $cgst = 0;
                        $sgst = 0;
                        $igst = $tax_ItemAtrr[0]['IGST'] != "" ? $tax_ItemAtrr[0]['IGST'] : 0;
                    }
                    $this->db->update('sale_items', array('gst_rate' => $tax_ItemAtrr[0]['attr_per'], 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst), array('id' => $sale_item_id));
                    /**/

                    if ($data['sale_status'] == 'completed' && $prd = $this->site->getProductByID($item['product_id'])) {

                        $item_costs = $this->site->item_costing($item);
                        if($prd->storage_type == "loose"){
                            if($prd->primary_variant) {
                                $variant = $this->site->getVerientById($prd->primary_variant);
                                //$item_costs['purchase_unit_cost'] = $variant['cost']  ? $prd->cost + $variant['cost']   : $prd->cost;
                                $item_costs['sale_unit_price']      = $variant['price'] ? $prd->price + $variant['price'] : $prd->price;
                                $item_costs['sale_net_unit_price']  = $item_costs['sale_unit_price'] - $item['unit_tax'];
                                $item_costs['quantity']             = $item['unit_quantity'];
                            }
                        }
                        if (!empty($item_costs)) {
                            foreach ($item_costs as $item_cost) {
                                if (isset($item_cost['date'])) {
                                    $item_cost['sale_item_id'] = $sale_item_id;
                                    $item_cost['sale_id'] = $id;
                                    if (!isset($item_cost['pi_overselling'])) {
                                        unset($item_cost['unit_quantity']);
                                        $this->db->insert('costing', $item_cost);
                                    }
                                } else {
                                    if (!empty($item_cost) && (is_array($item_cost) || is_object($item_cost))) {
                                        foreach ($item_cost as $key => $ic) {
                                            $item_cost['sale_item_id'] = $sale_item_id;
                                            $item_cost['sale_id'] = $id;

                                            if (!isset($item_cost['pi_overselling'])) {
                                                unset($item_cost['unit_quantity']);
                                                $this->db->insert('costing', $item_cost);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    /* New Field Add to Sales cgst,igst,sgst 17-1-2020 */
                    $total_cgst = $total_cgst + $cgst;
                    $total_sgst = $total_sgst + $sgst;
                    $total_igst = $total_igst + $igst;
                }
                $this->db->update('sales', array('cgst' => $total_cgst, 'sgst' => $total_sgst, 'igst' => $total_igst), array('id' => $id));
            }
            /**/
            if ($data['sale_status'] == 'completed') {
                $this->site->syncPurchaseItems($cost);
            }

            $this->site->syncSalePayments($id);
            $this->site->syncQuantity($id);
            $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);

              // Urbanpiper Stock Manage 
               if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $sale->warehouse_id);
                } 

            return true;
        }
        return false;
    }

    public function updateStatus($id, $status, $note) {

        $sale = $this->getInvoiceByID($id);
        $items = $this->getAllInvoiceItems($id);
        $cost = array();
        if ($status == 'completed' && $status != $sale->sale_status) {
            foreach ($items as $item) {
                $items_array[] = (array) $item;
            }
            $cost = $this->site->costing($items_array);
        }

        if ($this->db->update('sales', array('sale_status' => $status, 'note' => $note), array('id' => $id))) {

            if ($status == 'completed' && $status != $sale->sale_status) {

                foreach ($items as $item) {
                    $item = (array) $item;
                    if ($this->site->getProductByID($item['product_id'])) {
                        $item_costs = $this->site->item_costing($item);
                        foreach ($item_costs as $item_cost) {
                            $item_cost['sale_item_id'] = $item['id'];
                            $item_cost['sale_id'] = $id;
                            if (!isset($item_cost['pi_overselling'])) {
                                $this->db->insert('costing', $item_cost);
                            }
                        }
                    }
                }
            } elseif ($status != 'completed' && $sale->sale_status == 'completed') {
                $this->resetSaleActions($id);
            }

            if (!empty($cost)) {
                $this->site->syncPurchaseItems($cost);
            }
            return true;
        }
        return false;
    }
    
    public function setSaleDeleted($id) {
        
        $data = [
            'sale_status'       => 'deleted',
            'payment_status'    => 'deleted',
            'total'             => 0,
            'product_discount'  => 0,
            'order_discount_id' => NULL,
            'total_discount'    => 0,
            'order_discount'    => 0,
            'product_tax'       => 0,
            'order_tax_id'      => NULL,
            'order_tax'         => 0,
            'total_tax'         => 0,
            'shipping'          => 0,
            'grand_total'       => 0,
            'paid'              => 0,
            'surcharge'         => 0,
            'rounding'          => 0,
            'delivery_status'   => NULL,
            'cgst'              => 0,
            'sgst'              => 0,
            'igst'              => 0,
            'total_weight'      => 0,
            'return_sale_ref'   => NULL,
            'sale_id'           => NULL,
            'return_sale_total' => NULL,
            'return_id'         => NULL,
            'total_items'       => NULL,
        ];
        
        $sale = $this->getInvoiceByID($id);
        if($sale->status == 'returned'){            
            $this->db->update('sales', ['return_id'=>NULL, 'return_sale_ref'=>NULL, 'return_sale_total'=>NULL], ['id' => $sale->sale_id] );            
        } else if($sale->return_id) {
            $this->db->update('sales', $data, ['id' => $sale->return_id] );
            $this->db->delete('sale_items', ['sale_id' => $sale->return_id]);
        }        
        
        if ($this->db->update('sales', $data, ['id' => $id] )) {
           return TRUE;
        }
        
        return TRUE;
    }
    
    public function deleteSale($id) {
        $sale_items = $this->resetSaleActions($id);
        if ($this->db->delete('sale_items', ['sale_id' => $id]) && $this->setSaleDeleted($id) && $this->db->delete('costing', ['sale_id' => $id])) {
            
            $this->db->delete('sales_items_tax', array('sale_id' => $id));
           // $this->db->delete('sales', array('sale_id' => $id));
            $this->db->delete('payments', array('sale_id' => $id));
            $this->site->syncQuantity(NULL, NULL, $sale_items);
            return true;
        }
        return FALSE;
    }

    public function resetSaleActions($id, $return_id = NULL, $check_return = NULL) {
        
        if ($sale = $this->getInvoiceByID($id)) {
            if ($check_return && $sale->sale_status == 'returned') {
                $this->session->set_flashdata('warning', lang('sale_x_action'));
                redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
            }

            if ($sale->sale_status == 'completed') {
                $items = $this->getAllInvoiceItems($id);
                foreach ($items as $item) {
                    $purchase_item_id = null;
                    if (!$this->Settings->overselling) {
                        $costingItem = $this->site->getProductCostings(['sale_id'=>$sale->id, 'product_id'=>$item->product_id, 'sale_item_id'=>$item->id]);
                        $purchase_item_id = $costingItem ? $costingItem->purchase_item_id : null;
                    }
                        
                    if ($item->product_type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            if ($combo_item->type == 'standard') {
                                $qty = ($item->unit_quantity * $combo_item->qty);
                                $this->updatePurchaseItem(NULL, $qty, NULL, $combo_item->id, $item->warehouse_id);
                            }
                        }
                    } else {
                        $option_id      = isset($item->option_id) && !empty($item->option_id) ? $item->option_id : 0;
                        $batch_number   = isset($item->batch_number) && !empty($item->batch_number) ? $item->batch_number : NULL;                                                
                        $this->updatePurchaseItem($purchase_item_id, $item->unit_quantity, $item->id, $item->product_id, $item->warehouse_id, $option_id, $batch_number);
                    }
                }
                if ($sale->return_id || $return_id) {
                    $rid = $return_id ? $return_id : $sale->return_id;
                    $returned_items = $this->getAllInvoiceItems(FALSE, $rid);
                    foreach ($returned_items as $item) {
                        $purchase_item_id = null;
                        if (!$this->Settings->overselling) {
                            $costingItem = $this->site->getProductCostings(['sale_id'=>$sale->id, 'product_id'=>$item->product_id, 'sale_item_id'=>$item->id]);
                            $purchase_item_id = $costingItem ? $costingItem->purchase_item_id : null;
                        }
                        if ($item->product_type == 'combo') {
                            $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                            foreach ($combo_items as $combo_item) {
                                if ($combo_item->type == 'standard') {
                                    $qty = ($item->unit_quantity * $combo_item->qty);
                                    $this->updatePurchaseItem(NULL, $qty, NULL, $combo_item->id, $item->warehouse_id);
                                }
                            }
                        } else {
                            $option_id = isset($item->option_id) && !empty($item->option_id) ? $item->option_id : 0;
                            $this->updatePurchaseItem($purchase_item_id, $item->unit_quantity, $item->id, $item->product_id, $item->warehouse_id, $option_id);
                        }
                    }
                }
                $this->site->syncQuantity(NULL, NULL, $items);
                $this->sma->update_award_points($sale->grand_total, $sale->customer_id, $sale->created_by, TRUE);



 // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct->product_id;
                    }
                    $this->UPM->Product_out_of_stock($productids, $sale->warehouse_id);
                }
                return $items;
            }
        }
    }

    public function updatePurchaseItem($id, $qty, $sale_item_id, $product_id = NULL, $warehouse_id = NULL, $option_id = 0, $batch_number = NULL) {
         
        if ($id) {
            if ($pi = $this->getPurchaseItemByID($id)) {
                $pr = $this->site->getProductByID($pi->product_id);
                if ($pr->type == 'combo') {
                    $combo_items = $this->site->getProductComboItems($pr->id, $pi->warehouse_id);
                    foreach ($combo_items as $combo_item) {
                        if ($combo_item->type == 'standard') {
                            $cpi = $this->site->getPurchasedItem(array('product_id' => $combo_item->id, 'warehouse_id' => $pi->warehouse_id, 'option_id' => 0));
                            $bln = $pi->quantity_balance + ($qty * $combo_item->qty);
                            $this->db->update('purchase_items', array('quantity_balance' => $bln), array('id' => $combo_item->id));
                        }
                    }
                } else {
                    $bln = $pi->quantity_balance + $qty;
                    $this->db->update('purchase_items', array('quantity_balance' => $bln), array('id' => $id));
                }
            }
        } else {
            if ($sale_item_id) {
                if ($sale_item = $this->getSaleItemByID($sale_item_id)) {
                    $pr = $this->site->getProductByID($sale_item->product_id);
                    
                    $option_id = (($sale_item->option_id) && $pr->storage_type == 'packed') ? $sale_item->option_id : 0;
                    $batch_number = isset($sale_item->batch_number) && !empty($sale_item->batch_number) ? $sale_item->batch_number : NULL;
                    $clause = array('product_id' => $sale_item->product_id, 'warehouse_id' => $sale_item->warehouse_id, 'option_id' => $option_id, 'batch_number' => $batch_number);
                    if ($pi = $this->site->getPurchasedItem($clause)) {
                        $quantity_balance = $pi->quantity_balance + $qty;
                        $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $pi->id));
                    } else {
                        $clause['product_code']     = $pr->code;
                        $clause['product_name']     = $pr->name;
                        $clause['tax_rate_id']      = $pr->tax_rate;
                        $clause['tax_method']       = $pr->tax_method;
                        $clause['purchase_id']      = NULL;
                        $clause['transfer_id']      = NULL;
                        $clause['quantity']         = 0;
                        $clause['quantity_balance'] = $qty;
                        $clause['status']           = 'received';
                        $this->db->insert('purchase_items', $clause);
                    }
                }
            } else {
                if ($product_id && $warehouse_id) {
                    $pr = $this->site->getProductByID($product_id);
                    $option_id = ($option_id && $pr->storage_type == 'packed') ? $option_id : 0;
                    $clause = array('product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'option_id' => $option_id, 'batch_number' => $batch_number);
                    if ($pr->type == 'standard') {
                        if ($pi = $this->site->getPurchasedItem($clause)) {
                            $quantity_balance = $pi->quantity_balance + $qty;
                            $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $pi->id));
                        } else {
                            $clause['product_code']     = $pr->code;
                            $clause['product_name']     = $pr->name;
                            $clause['tax_rate_id']      = $pr->tax_rate;
                            $clause['tax_method']       = $pr->tax_method;
                            $clause['purchase_id']      = NULL;
                            $clause['transfer_id']      = NULL;
                            $clause['quantity']         = 0;
                            $clause['quantity_balance'] = $qty;
                            $clause['status']           = 'received';
                            $this->db->insert('purchase_items', $clause);
                        }
                    } elseif ($pr->type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($pr->id, $warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            $clause = array('product_id' => $combo_item->id, 'warehouse_id' => $warehouse_id, 'option_id' => 0);
                            if ($combo_item->type == 'standard') {
                                if ($pi = $this->site->getPurchasedItem($clause)) {
                                    $quantity_balance = $pi->quantity_balance + ($qty * $combo_item->qty);
                                    $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), $clause);
                                } else {
                                    $clause['product_code']     = $combo_item->code;
                                    $clause['product_name']     = $combo_item->name;
                                    $clause['tax_rate_id']      = $combo_item->tax_rate;
                                    $clause['tax_method']       = $combo_item->tax_method;
                                    $clause['transfer_id']      = NULL;
                                    $clause['purchase_id']      = NULL;
                                    $clause['quantity']         = 0;
                                    $clause['quantity_balance'] = $qty;
                                    $clause['status']           = 'received';
                                    $this->db->insert('purchase_items', $clause);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function getPurchaseItemByID($id) {
        $q = $this->db->get_where('purchase_items', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getCostingLines($sale_item_id, $product_id, $sale_id = NULL) {
        if ($sale_id) {
            $this->db->where('sale_id', $sale_id);
        }
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('costing', array('sale_item_id' => $sale_item_id, 'product_id' => $product_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getSaleItemByID($id) {
        $q = $this->db->get_where('sale_items', array('id' => $id), 1);
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

    public function addDelivery($data = array()) {
        if ($this->db->insert('deliveries', $data)) {
            if ($this->site->getReference('do') == $data['do_reference_no']) {
                $this->site->updateReference('do');
            }
            return true;
        }
        return false;
    }

    public function updateDelivery($id, $data = array()) {
        if ($this->db->update('deliveries', $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function updateSalesDeliveryStatus($sale_id, array $updateItemsDelivery, $saleDeliveryStatus) {

        if ($this->db->update('sales', ['delivery_status' => $saleDeliveryStatus], array('id' => $sale_id))) {

            if (is_array($updateItemsDelivery)) {
                foreach ($updateItemsDelivery as $itm_id => $itemsStatus) {

                    $this->db->update('sale_items', $itemsStatus, array('id' => $itm_id));
                }//end foreach
            }//end if
            return true;
        }//end if

        return false;
    }

    public function getDeliveryItemBySaleID($sale_id) {
        $q = $this->db->query("SELECT sum(delivered_quantity) as delivered , sum(quantity) as quantity FROM sma_sale_items WHERE sale_id = '$sale_id' ");
        if ($q->num_rows() > 0) {
            return $q->row();
        }

        return FALSE;
    }

    public function getDeliveryByID($id) {
        $q = $this->db->get_where('deliveries', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getDeliveryBySaleID($sale_id) {
        $q = $this->db->get_where('deliveries', array('sale_id' => $sale_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function deleteDelivery($id) {
        if ($this->db->delete('deliveries', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getInvoicePayments($sale_id) {
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('payments', array('sale_id' => $sale_id));
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

    public function getPaymentsForSale($sale_id) {
        $this->db->select('payments.date, payments.paid_by,payments.cc_holder, payments.amount,payments.transaction_id, payments.cc_no, payments.cheque_no, payments.reference_no, users.first_name, users.last_name, type')
                ->join('users', 'users.id=payments.created_by', 'left');
        $q = $this->db->get_where('payments', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function addPayment($data = array(), $customer_id = null, $challan = NULL) {
        if ($this->db->insert('payments', $data)) {
            if ($this->site->getReference('pay') == $data['reference_no']) {
                $this->site->updateReference('pay');
            }


            if ($challan == 'chalan') {
                $this->site->syncOrderPayments($data['order_id']);
            } elseif ($challan == 'eshop_order') {
                $this->site->syncOrderPayments($data['order_id']);
            } else {
                $this->site->syncSalePayments($data['sale_id']);
            }

            //$this->site->syncSalePayments($data['sale_id']);
            if ($data['paid_by'] == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($data['cc_no']);
                $this->db->update('gift_cards', array('balance' => ($gc->balance - $data['amount'])), array('card_no' => $data['cc_no']));
            } elseif ($customer_id && $data['paid_by'] == 'deposit') {
                $customer = $this->site->getCompanyByID($customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount - $data['amount'])), array('id' => $customer_id));
            }
            return true;
        }
        return false;
    }

    public function addOfflinePayment($data = array()) {
        if ($this->db->insert('payments', $data)) {
            if ($this->site->getReference('pay') == $data['reference_no']) {
                $this->site->updateReference('pay');
            }
            return true;
        }
        return false;
    }

    public function updatePayment($id, $data = array(), $customer_id = null) {
        $opay = $this->getPaymentByID($id);
        if ($this->db->update('payments', $data, array('id' => $id))) {
            if ($data['sale_id'] == 0) {
                $this->site->syncOrderPayments($data['order_id']);
            } else {
                $this->site->syncSalePayments($data['sale_id']);
            }
            if ($opay->paid_by == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($opay->cc_no);
                $this->db->update('gift_cards', array('balance' => ($gc->balance + $opay->amount)), array('card_no' => $opay->cc_no));
            } elseif ($opay->paid_by == 'deposit') {
                if (!$customer_id) {
                    $sale = $this->getInvoiceByID($opay->sale_id);
                    $customer_id = $sale->customer_id;
                }
                $customer = $this->site->getCompanyByID($customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount + $opay->amount)), array('id' => $customer->id));
            }
            if ($data['paid_by'] == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($data['cc_no']);
                $this->db->update('gift_cards', array('balance' => ($gc->balance - $data['amount'])), array('card_no' => $data['cc_no']));
            } elseif ($customer_id && $data['paid_by'] == 'deposit') {
                $customer = $this->site->getCompanyByID($customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount - $data['amount'])), array('id' => $customer_id));
            }
            return true;
        }
        return false;
    }

    public function deletePayment($id) {
        $opay = $this->getPaymentByID($id);
        if ($this->db->delete('payments', array('id' => $id))) {
            $this->site->syncSalePayments($opay->sale_id);
            if ($opay->paid_by == 'gift_card') {
                $gc = $this->site->getGiftCardByNO($opay->cc_no);
                $this->db->update('gift_cards', array('balance' => ($gc->balance + $opay->amount)), array('card_no' => $opay->cc_no));
            } elseif ($opay->paid_by == 'deposit') {
                $sale = $this->getInvoiceByID($opay->sale_id);
                $customer = $this->site->getCompanyByID($sale->customer_id);
                $this->db->update('companies', array('deposit_amount' => ($customer->deposit_amount + $opay->amount)), array('id' => $customer->id));
            }
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

    /* ----------------- Gift Cards --------------------- */

    public function addGiftCard($data = array(), $ca_data = array(), $sa_data = array()) {
        if ($this->db->insert('gift_cards', $data)) {
            if (!empty($ca_data)) {
                $this->db->update('companies', array('award_points' => $ca_data['points']), array('id' => $ca_data['customer']));
            } elseif (!empty($sa_data)) {
                $this->db->update('users', array('award_points' => $sa_data['points']), array('id' => $sa_data['user']));
            }
            return true;
        }
        return false;
    }

    public function updateGiftCard($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('gift_cards', $data)) {
            return true;
        }
        return false;
    }

    public function deleteGiftCard($id) {
        if ($this->db->delete('gift_cards', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getPaypalSettings() {
        $q = $this->db->get_where('paypal', array('id' => 1));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getSkrillSettings() {

        /* $q = $this->db->get_where('skrill', array('id' => 1));
          if ($q->num_rows() > 0) {
          return $q->row();
          } */
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

    public function getStaff() {
        if (!$this->Owner) {
            $this->db->where('group_id !=', 1);
        }
        $this->db->where('group_id !=', 3)->where('group_id !=', 4);
        $q = $this->db->get('users');
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

    public function getTaxRateByName($name) {
        $q = $this->db->get_where('tax_rates', array('name' => $name), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateCostingLine($sale_item_id, $product_id, $quantity) {
        if ($costings = $this->getCostingLines($sale_item_id, $product_id)) {
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

    public function topupGiftCard($data = array(), $card_data = NULL) {
        if ($this->db->insert('gift_card_topups', $data)) {
            $this->db->update('gift_cards', $card_data, array('id' => $data['card_id']));
            return true;
        }
        return false;
    }

    public function getAllGCTopups($card_id) {
        $this->db->select("{$this->db->dbprefix('gift_card_topups')}.*, {$this->db->dbprefix('users')}.first_name, {$this->db->dbprefix('users')}.last_name, {$this->db->dbprefix('users')}.email")
                ->join('users', 'users.id=gift_card_topups.created_by', 'left')
                ->order_by('id', 'desc')->limit(10);
        $q = $this->db->get_where('gift_card_topups', array('card_id' => $card_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getEshopDeclineOrder() {
        $this->db->select('id');
        $this->db->where("DATE(  `date` ) < '" . date("Y-m-d") . "'");
        $q = $this->db->get_where('sales', array('eshop_sale' => 1, 'sale_status' => 'pending', 'payment_status' => 'due'));
        // echo $this->db->last_query(); 
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = (int) $row->id;
            }
            return $data;
        }
        return false;
    }

    public function getOfflineSalesList() {

        $salesData = $this->offlineSales();

        if ($salesData) {
            return $salesData['sales'];
        }
        return false;
    }

    public function offlineSales() {

        $this->db->select('`id`, `date`, `reference_no`, `customer`, `biller_id`, `biller`, `warehouse_id`, `note`, `total`, '
                . ' `product_discount`, `order_discount_id` as order_discount_lable, `total_discount`, `order_discount`, `product_tax`, '
                . ' `order_tax_id`, `order_tax`, `total_tax`, `shipping`, `grand_total`, `sale_status`, `payment_status`, `total_items`, `cf1`, `cf2`,'
                . ' `offline_payment_id`, `offline_transaction_type` ');

        $q = $this->db->get_where('sales', array('offline_sale' => 1));
        // echo $this->db->last_query(); 
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {

                $salesData['sales'][$row->id] = (array) $row;

                $salesData['saleIds'][] = $row->id;
            }
            return $salesData;
        }
        return false;
    }

    public function getOfflineSales() {

        $salesData = $this->offlineSales();

        if ($salesData) {

            $this->sales = $salesData['sales'];
            $saleIds = $salesData['saleIds'];

            //Set Sale Items
            $this->getOfflineSalesItems($saleIds);
            //Set Sale Payment
            $this->getOfflineSalesPayments($saleIds);

            return $this->sales;
        }
        return false;
    }

    public function getOfflineSalesItems(array $saleIds) {

        $q = $this->db->select('`id`, `sale_id`, `product_id`, `product_code`, `product_name`, `product_type`, `net_unit_price`, `unit_price`, `quantity`, '
                        . '`warehouse_id`, `item_tax`, `tax_rate_id`, `tax`, `discount`, `item_discount`, `subtotal`, `serial_no`, `product_unit_id`, '
                        . '`product_unit_code`, `unit_quantity`, `cf1`, `cf2`, `cf3`, `cf4`, `cf5`, `cf6`')
                ->where_in('sale_id', $saleIds)
                ->get('sma_sale_items');

        // echo "@@@@". $this->db->last_query(); 

        if ($q->num_rows() > 0) {

            foreach (($q->result()) as $row) {
                //$items[$row->sale_id]['items'][] =
                $this->sales[$row->sale_id]['items'][] = $row;
            }
        }
    }

    public function getOfflineSalesPayments(array $saleIds) {

        $q = $this->db->select(' `id`, `date`, `sale_id`, `reference_no`, `amount`, `type`, `note`,`pos_balance`')
                ->where_in('sale_id', $saleIds)
                ->get('sma_payments');

        // echo "@@@@". $this->db->last_query(); 

        if ($q->num_rows() > 0) {

            foreach (($q->result()) as $row) {
                $this->sales[$row->sale_id]['payment'] = $row;
            }
        }
    }

    public function getAllTaxItems($sale_id, $return_id, $itemId = NULL) {
        $this->db->select("attr_code,attr_name,attr_per, `tax_amount`  AS `amt`,item_id");
        $this->db->where_in('sale_id', array($sale_id, $return_id));
        $q = $this->db->get('sales_items_tax');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->item_id][$row->attr_code] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllTaxItemsGroup($sale_id, $return_id = NULL) {
        $this->db->select("attr_code,attr_name,attr_per,sum(`tax_amount`) AS `amt`");
        $this->db->where_in('sale_id', array((int) $sale_id, (int) $return_id));
        $this->db->group_by('attr_code');
        $this->db->order_by('id', 'asc');
        $q = $this->db->get('sales_items_tax');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /*
     * Attr : Array $salesTypes
     * PARA Values: ['pos'=>true, 'eshop_sale'=>true, 'offline_sale'=>true]
     */

    public function getSales(array $salesTypes, $select = NULL) {

        if ($select !== NULL) {
            if (is_array($select)) {
                $this->db->select(join(',', $select));
            }
            if (is_string($select)) {
                $this->db->select("$select");
            }
        }

        if (is_array($salesTypes) && !empty($salesTypes)) {

            foreach ($salesTypes as $key => $value) {

                if ($value) {
                    $this->db->or_where($key, '1');
                }
            }//end foreach          
        }//end if.

        $q = $this->db->get('sales');

        if ($q->num_rows() > 0) {
            return $q->result();
        }
        return false;
    }

    /*
     * Para: Array $saleids
     * Para: Array $salesTypes
     */

    public function getSaleItems(array $saleids = NULL, array $salesTypes = NULL) {
        if (is_array($salesTypes) && !empty($salesTypes)) {

            $saledata = $this->getSales($salesTypes, $select = 'id');

            if (is_array($saledata)) {
                foreach ($saledata as $key => $obj) {
                    $saleids[] = $obj->id;
                }
            }

            $this->db->where_in('sale_id', $saleids);
            $q = $this->db->get('sale_items');
        }//end if.
        elseif ($sale_ids !== NULL) {
            $this->db->where('sale_id', $saleids);
            $q = $this->db->get('sale_items');
        }

        if ($q->num_rows() > 0) {
            return $q->result();
        }
        return false;
    }

    /* --- 14-03-19 --- */

    public function getUnitById($id) {
        $q = $this->db->get_where("units", array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /* --- 14-03-19 --- */
    /* 9-11-2019 */

    public function getPaymentsSale($sale_id) {
        $this->db->select('payments.date, payments.paid_by, payments.amount,payments.transaction_id, payments.cc_no, payments.cheque_no, payments.reference_no');
        $q = $this->db->get_where('payments', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /*     * *** */

    /* 20-11-2019  Gift History */

    public function getGiftHistoryByID($customer_id, $card_no) {
        $q = $this->db->query("SELECT date,sales_id as invoice_id,amount,cc_holder as balance_amt FROM view_sales_history WHERE  company_id = '$customer_id' AND cc_no='$card_no' AND paid_by='gift_card' ORDER BY sales_id DESC LIMIT 10");

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function addSaleFromChallan($data = array(), $items = array(), $extrasPara = array()) {

        $sale_action = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $order_id = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity = $extrasPara['syncQuantity'];

        if ($this->db->insert('sales', $data)) {

            $sale_id = $this->db->insert_id();

            //Get formated Invoice No
            $invoice_no = $this->sma->invoice_format($sale_id, $data['date']);
            //Update formated invoice no
            $this->db->where(['id' => $sale_id])->update('sales', ['invoice_no' => $invoice_no]);

            if ($order_id) {
                //Update sale_invoice_no after convert order into sales. 
                $this->db->where(['id' => $order_id])->update('orders', ['sale_invoice_no' => $invoice_no]);
                //Update order payment if exists
                $this->db->where(['order_id' => $order_id])->update('payments', ['sale_id' => $sale_id]);
            }
            // End Invoice No

            if ($this->site->getReference('so') == $data['reference_no']) {
                $this->site->updateReference('so');
            }

            $Setting = $this->Settings;
            if ($items) {
                foreach ($items as $item) {
                    //------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                    $_prd = $Setting->pos_type == 'pharma' ? $this->site->getProductByID($item['product_id']) : NULL;
                    $item['cf1'] = $Setting->pos_type == 'pharma' ? $_prd->cf1 : '';
                    $item['cf2'] = $Setting->pos_type == 'pharma' ? $_prd->cf2 : '';
                    //------------------ End ----------------//
                    $item['sale_id'] = $sale_id;
                    $this->db->insert('sale_items', $item);
                    $sale_item_id = $this->db->insert_id();

                    $_taxSaleID = $sale_id;

                    $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID );

                    $this->db->where(['order_id' => $order_id, 'product_id' => $item['product_id']])->update('costing', ['sale_id' => $sale_id, 'sale_item_id' => $sale_item_id]);
                }

                return $sale_id;
            }
        }

        return false;
    }

    public function addSaleReturnFromChallanReturn($data = array(), $items = array(), $extrasPara = array()) {
        $sale_action = $extrasPara['sale_action'] ? $extrasPara['sale_action'] : null;
        $sale_id = $extrasPara['sale_id'] ? $extrasPara['sale_id'] : $data['sale_id'];
        $order_id = $extrasPara['order_id'] ? $extrasPara['order_id'] : null;
        $syncQuantity = $extrasPara['syncQuantity'];

        if ($this->db->insert('sales', $data)) {

            $sale_return_id = $this->db->insert_id();

            if ($sale_return_id && $sale_id) {
                $this->db->where(['id' => $sale_id])->update('sales', ['return_id' => $sale_return_id]);
            }

            if ($order_id) {
                //Update sale_invoice_no after convert order into sales. 
                $this->db->where(['id' => $order_id])->update('orders', ['sale_invoice_no' => $data['invoice_no']]);
                //Update order payment if exists
                $this->db->where(['order_id' => $order_id])->update('payments', ['sale_id' => $sale_return_id]);
            }
            // End Invoice No

            if ($this->site->getReference('re') == $data['return_sale_ref']) {
                $this->site->updateReference('re');
            }

            $Setting = $this->Settings;
            if ($items) {
                foreach ($items as $item) {
                    //------------------Change For  Pharma for  saving Exp. date & Batch No ----------------//
                    $_prd = $Setting->pos_type == 'pharma' ? $this->site->getProductByID($item['product_id']) : NULL;
                    $item['cf1'] = $Setting->pos_type == 'pharma' ? $_prd->cf1 : '';
                    $item['cf2'] = $Setting->pos_type == 'pharma' ? $_prd->cf2 : '';
                    //------------------ End ----------------//
                    $item['sale_id'] = $sale_return_id;
                    $this->db->insert('sale_items', $item);
                    $sale_item_id = $this->db->insert_id();

                    $_taxSaleID = $sale_return_id;

                     $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID );

                    $this->db->where(['order_id' => $order_id, 'product_id' => $item['product_id']])->update('costing', ['sale_id' => $sale_return_id, 'sale_item_id' => $sale_item_id]);
                }

                return $sale_return_id;
            }
        }

        return false;
    }

    /*     * *************************************************************************
     * Sales Challans
     * ************************************************************************* */

    /**
     * This method using get challan records
     * 
     * @param type $id
     * @return boolean
     */
    public function getChallanByID($id) {
        $q = $this->db->get_where('orders', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /**
     * This method using get challan payments
     * 
     * @param type $sale_id
     * @return type
     */
    public function getChallanInvoicePayments($sale_id) {
        $this->db->order_by('id', 'asc');
        $q = $this->db->get_where('payments', array('order_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    /**
     * This method using get challan Items
     * 
     * @param type $sale_id
     * @param type $return_id
     * @return boolean
     */
    public function getAllChallanItems($sale_id, $return_id = NULL) {
        $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, product_variants.name as variant, product_variants.price as variant_price, products.hsn_code as hsncode, sales.rounding as rounding')
                ->join('products', 'products.id=order_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                ->join('sales', 'sales.id=order_items.sale_id', 'left')
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

    /**
     * 
     * This method using update challan
     * 
     * @param type $id
     * @param type $data
     * @param type $items
     * @return boolean
     */
    public function updateChallan($id, $data, $items = array()) {
        $this->resetChallenActions($id, FALSE, TRUE);
        //$this->resetSaleActions($id, FALSE, TRUE);


        if ($data['sale_status'] == 'completed') {
            $cost = $this->site->costing($items);
        }


        if ($this->db->update('orders', $data, array('id' => $id)) &&
                $this->db->delete('order_items', array('sale_id' => $id)) &&
                $this->db->delete('costing', array('order_id' => $id))) {
            $this->db->delete('orders_items_tax', array('order_id' => $id));
            if (!empty($items)) {
                foreach ($items as $item) {

                    $item['sale_id'] = $id;
                    $this->db->insert('order_items', $item);
                    $sale_item_id = $this->db->insert_id();

                    $_taxSaleID = $id;
                    $_tax_type = 'o';

                     $taxAtrr = $this->sma->taxAtrrClassification($item['tax_rate_id'], $item['net_unit_price'],$item['unit_quantity'], $sale_item_id, $_taxSaleID, $_tax_type);

                    /* Add New field to Sale_items Code cgst,igst,sgst 17-1-2020 */
                    $tax_ItemAtrr = $this->sma->taxArr_rate($item['tax_rate_id'], $item['net_unit_price'], $item['unit_quantity'], $sale_item_id, $_taxSaleID);
                    if ($tax_ItemAtrr[0]['attr_code'] != 'IGST') {
                        $cgst = $tax_ItemAtrr[0]['CGST'] != "" ? $tax_ItemAtrr[0]['CGST'] : 0;
                        $sgst = $tax_ItemAtrr[1]['SGST'] != "" ? $tax_ItemAtrr[1]['SGST'] : 0;
                        $igst = $tax_ItemAtrr[2]['IGST'] != "" ? $tax_ItemAtrr[2]['IGST'] : 0;
                    } else {
                        $cgst = 0;
                        $sgst = 0;
                        $igst = $tax_ItemAtrr[0]['IGST'] != "" ? $tax_ItemAtrr[0]['IGST'] : 0;
                    }
                    $this->db->update('order_items', array('gst_rate' => $tax_ItemAtrr[0]['attr_per'], 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst), array('id' => $sale_item_id));

                    /**/

                    if ($data['sale_status'] == 'completed' && $this->site->getProductByID($item['product_id'])) {

                        $item_costs = $this->site->item_costing($item);

                        if (!empty($item_costs)) {
                            foreach ($item_costs as $item_cost) {
                                if (isset($item_cost['date'])) {
                                    $item_cost['order_item_id'] = $sale_item_id;
                                    $item_cost['order_id'] = $id;
                                    if (!isset($item_cost['pi_overselling'])) {
                                        $this->db->insert('costing', $item_cost);
                                    }
                                } else {

                                    if (!empty($item_cost) && (is_array($item_cost) || is_object($item_cost))) {
                                        foreach ($item_cost as $key => $ic) {
                                            $item_cost['order_item_id'] = $sale_item_id;
                                            $item_cost['order_id'] = $id;

                                            if (!isset($item_cost['pi_overselling'])) {
                                                $this->db->insert('costing', $item_cost);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    /* New Field Add to Sales cgst,igst,sgst 17-1-2020 */
                    $total_cgst = $total_cgst + $cgst;
                    $total_sgst = $total_sgst + $sgst;
                    $total_igst = $total_igst + $igst;
                }
                $this->db->update('sales', array('cgst' => $total_cgst, 'sgst' => $total_sgst, 'igst' => $total_igst), array('id' => $id));
            }
            /**/
            if ($data['sale_status'] == 'completed') {
                $this->site->syncPurchaseItems($cost);
            }
            $this->site->syncSaleActionPayments($id, 'chalan');

            if ($syncQuantity) {
                $this->site->syncQuantity(NULL, NULL, NULL, NULL, $id);

                // Urbanpiper Stock Manage 
                if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $orderWerehouse = $this->db->select('warehouse_id')->where(['id'=>$id])->get('sma_orders')->row();
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct['product_id'];
                    }
                    $this->UPM->Product_out_of_stock($productids, $orderWerehouse->warehouse_id);
                }
            }


            $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);



            return true;
        }
        return false;
    }

    /**
     *  Reset Challen Action
     * @param type $id
     * @param type $return_id
     * @param type $check_return
     * @return type
     */
    public function resetChallenActions($id, $return_id = NULL, $check_return = NULL) {
        if ($sale = $this->getChallanByID($id)) {
            if ($check_return && $sale->sale_status == 'returned') {
                $this->session->set_flashdata('warning', lang('sale_x_action'));
                redirect(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : 'welcome');
            }

            if ($sale->sale_status == 'completed') {
                $items = $this->getAllChallanItems($id);
                foreach ($items as $item) {
                    if ($item->product_type == 'combo') {
                        $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            if ($combo_item->type == 'standard') {
                                $qty = ($item->quantity * $combo_item->qty);
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
                    $returned_items = $this->getAllChallanItems(FALSE, $rid);
                    foreach ($returned_items as $item) {

                        if ($item->product_type == 'combo') {
                            $combo_items = $this->site->getProductComboItems($item->product_id, $item->warehouse_id);
                            foreach ($combo_items as $combo_item) {
                                if ($combo_item->type == 'standard') {
                                    $qty = ($item->quantity * $combo_item->qty);
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

              // Urbanpiper Stock Manage 
               if($this->Settings->pos_type == 'restaurant'){
                    $this->load->model("Urban_piper_model","UPM");
                    $productids = array();
                    foreach($items as $upproduct){
                        $productids[] = $upproduct->product_id;
                    }
                    $this->UPM->Product_out_of_stock($productids, $sale->warehouse_id);
                }
                return $items;
            }
        }
    }

    /*     * *************************************************************************
     * End Sales Challans
     * ************************************************************************* */
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
    /* Pos Invoice 13-5-2020 */

    public function getSalesItemsTaxes($saleId) {
        $select_warehouse = '';

        /* $query = "SELECT sum(`tax_amount`) amount, ( `attr_per` * 2) as rate,item_id
          FROM  " . $this->db->dbprefix('sales_items_tax') . "
          WHERE `sale_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('sales') . "  WHERE DATE( `date` ) =  '$date' " . $select_warehouse . " )
          AND `attr_per` > 0 GROUP BY `attr_per` ORDER BY `attr_per` ASC "; */

        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sale_items') . "  WHERE `sale_id` =  '$saleId'  GROUP BY `gst_rate` ";

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /**/

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

    /**
     * 
     * @return type
     */
    public function getDelivaryPerson() {
        return $this->db->select('name,phone')->where(['group_name' => 'delivery'])->get('sma_companies')->result();
    }

    /**
     * Add Product Manual
     * @param type $products
     * @return type
     */
    public function addproductManual($products) {
        $this->db->insert('sma_products', $products);
        $product_id = $this->db->insert_id();
        return $product_id;
    }



    
    /**
     * 
     * @param type $category_id
     * @return type
     */
    public function getCategoryCode($category_id){
       $q =  $this->db->select('code, name')->where(['id'=>$category_id])->get('categories')->row();
       return ($this->db->affected_rows())? $q : FALSE;
    }

    
    
    /**
     * 
     * @param type $brand_id
     * @return type
     */
    public function getProductBrand($brand_id){
         $q =  $this->db->select('code, name')->where(['id'=>$brand_id])->get('brands')->row();
       return ($this->db->affected_rows())? $q : FALSE;
    }
   


    /**
     * 
     * @param type $term
     * @param type $warehouse_id
     * @param type $limit
     * @param type $warehouseRes
     * @return type
     */
    public function getQRScanProductNames($term, $warehouse_id, $limit = 20, $warehouseRes = NULL) {
        $wp = "( SELECT product_id, warehouse_id, quantity as quantity from {$this->db->dbprefix('warehouses_products')} ) FWP";

        $this->db->select('products.*, FWP.quantity as quantity, categories.id as category_id, categories.name as category_name', FALSE)
                ->join($wp, 'FWP.product_id=products.id', 'left')
                ->join('categories', 'categories.id=products.category_id', 'left')
                ->group_by('products.id');
        $this->db->where("{$this->db->dbprefix('products')}.code = '" . $term . "'");


        if ((int) $warehouse_id > 0 && !empty($warehouseRes)):
        //$this->db->where("FWP.warehouse_id = '".$warehouse_id."'");
        endif;

        $this->db->limit($limit);
        $q = $this->db->get('products');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }


     /**
     * Purchase Notification
     * @param type $sale_id
     * @return type
     */
    public function getSalesItems($sale_id){
       /*$salesItems =   $this->db->select('sale_items.*,product_variants.name as variant_name')
                 ->join('product_variants','product_variants.id = sale_items.option_id','left')
                 ->where(['sale_id'=>$sale_id])->get('sale_items')->result_array();*/
        $salesItems = $this->db->select('sale_items.sale_id,sale_items.product_code,sale_items.product_name,sale_items.quantity, sale_items.unit_price ,product_variants.name as variant_name')
                        ->join('product_variants', 'product_variants.id = sale_items.option_id', 'left')
                        ->where(['sale_id' => $sale_id])->get('sale_items')->result_array();

         
       return $salesItems;
    }
 
    /**
     * End Purchase Notification
     */


     /**
     * 
     * @param type $productId
     * @return type
     */
    public function getCategoryByProductId($productId) {
        $this->db->where('products.id', $productId);
        $this->db->select('categories.name as Catname, categories.id as cid, categories.parent_id as pid')
                ->join('products', 'products.category_id = categories.id', 'left');
        $q = $this->db->get('categories');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $row;
        }
    }

    /**
     * 
     * @param type $id
     * @param type $parent_id
     * @return boolean
     */
    public function getSubCategories($id, $parent_id) {
        $q = $this->db->get_where("categories", array('id' => $id, 'parent_id' => $parent_id));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /**
     * 
     * @param type $productId
     * @return type
     */
    public function getBrandByProductId($productId) {
        $this->db->where('products.id', $productId);
        $this->db->select('brands.name as brandname, brands.id as bid')
                ->join('products', 'products.brand = brands.id', 'left');
        $q = $this->db->get('brands');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $row;
        }
    }

    /**
     * 
     * @return boolean
     */
    public function getCompanies() {
        $q = $this->db->get('settings');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    

    /**
    * 
    * @param type $data
    * @return boolean
    */
    public function addDeposit($data)
    {
        if($this->db->insert('deposits', $data)){
            /*$company = $this->db->select('id, deposit_amount')->where(['id'=>$data['company_id']])->get('companies')->row();
             $cdata = array(
                'deposit_amount' => ($company->deposit_amount + $data['amount'])
            );
            $this->db->update('companies', $cdata, array('id' => $data['company_id']));*/
            return true;
        }
        return false;
    }
 
      /**
     * Repeated Sales 
     * @param type $customerId
     * @param type $productCode
     * @param type $days
     * @return boolean
     */
    public function getRepeatSalesCheck($customerId, $productCode, $days){
           $productsCodes = ['24049013','44000822','91873420'];
           $percentage = "%";
           
               $currentDate = date('Y-m-d');
          
               if($days > 1){
                     $enddate = date('Y-m-d',strtotime('-'.$days.' day'));
               }
           
           if(in_array($productCode, $productsCodes)){
          
               
               $this->db->select('*')->where_in('product_code',$productsCodes)
                       ->where('customer_id',$customerId);
                    if(isset($enddate)){
                        // $this->db->where('DATE(sale_date) BETWEEN ' . $enddate . ' and ' . $currentDate);
                         $this->db->where('DATE(sale_date) >= ' , $enddate);
                         $this->db->where('DATE(sale_date) <= ' ,  $currentDate);
                    }else{
                        $this->db->where('DATE(sale_date)', $currentDate);
                    }
               $getData =  $this->db->get('view_customer_sale_items')->result();
                
               if($this->db->affected_rows()){
                     
                   $discountP = $discountAmt = 0;
                    foreach($getData as $getItems){
                        if($productCode !== $getItems->product_code){
                            
                            $discount = $getItems->repeat_sale_discount_rate;
                              $dpos = strpos($discount, $percentage);
                              if($dpos !== false){
                                    $pds = explode("%", $discount);
                                    $discountP += $pds[0];

                              }else{
                                  $discountAmt += $discount;
                              }
                          }else {
                             $discountP = $discountAmt = 0;
                         }
                    }
                    
                    return $response =['discountP'=>$discountP,'discountAmt' =>$discountAmt ];
               }
               return false;

           }
           
           return false;
    }




   /**
     * 
     * @param type $table_id
     * @return boolean 
     */
    public function getTableDetails($table_id){
       $tableDetails =  $this->db->select('*')->where(['id'=>$table_id])->get('restaurant_tables')->row();
       if($this->db->affected_rows()){
           return $tableDetails;
       }
       return false;
    }
    public function getAllInvoiceItems1($sale_id, $return_id = NULL) {
        $this->db->select('sale_items.*,companies.*,sales.invoice_no,sales.date as s_date,sales.customer_id,sales.biller_id,sales.total as s_total,sales.grand_total as s_grand_total, sales.sgst as s_sgst, sales.cgst as s_cgst, sales.igst as s_igst ,sales.sale_status ')
                ->join('products', 'products.id=sale_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                ->join('sales', 'sales.id=sale_items.sale_id', 'left')
                ->join('companies', 'companies.id=sales.customer_id', 'left')
                ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
                ->group_by('sale_items.id')
                ->order_by('sale_items.id', 'asc');
        if ($sale_id && !$return_id) {
            $this->db->where('sale_items.sale_id', $sale_id);
        } elseif ($return_id) {
            $this->db->where('sale_items.sale_id', $return_id);
        }
        $q = $this->db->get('sale_items');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getBuyer($id)
    {
        $q = $this->db->get_where('companies', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getSeller($id)
    {
        $q = $this->db->get_where('companies', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getItemsByInv($id)
    {
        $this->db->where('sale_id', $id);
        $q = $this->db->get('sale_items');
            if ($q->num_rows() > 0) {
                return $q->result();
            }
        return FALSE;
    }
    public function getProductUnit($pid)
    {
        // select sma_units.name from sma_products,sma_units where  sma_products.unit = sma_units.id and sma_products.id = 55
        $res = $this->db->query("select sma_units.name from sma_products,sma_units where  sma_products.unit = sma_units.id and sma_products.id =".$pid);

        if ($res->num_rows() > 0) {
            foreach (($res->result()) as $row) {
                $unit = $row->name;
            }
            return $unit;        
        }
        // return ;
        // $this->db->select("customer", FALSE);
                // ->where('supplier_id', $supplier_id);
        // $q = $this->db->get('sales');
        // if ($q->num_rows() > 0) {
        //     foreach (($q->result()) as $row) {
        //         $data[] = $row['customer'];
        //     }
        //     return $data;
        // }
        return FALSE;
    }
    //counter 
    public function getProductOptionsByAttr($product_id, $warehouse_id, $GroupId='')
    {
		 $this->db->select('product_variants.*');
		if($GroupId!='')
			$this->db->where(array('product_variants.group_id' => $GroupId));
		$this->db->where(array('product_variants.product_id' => $product_id));
		$this->db->where(array('warehouses_products_variants.warehouse_id' => $warehouse_id));
		$this->db->group_by('warehouses_products_variants.option_id');
		$this->db->join('warehouses_products_variants', 'warehouses_products_variants.option_id=product_variants.id', 'inner');
		$q = $this->db->get('product_variants');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getCategoryTax($categoryId){
        $result =  $this->db->select('*')->where(['id'=>$categoryId])->get('categories')->row();
        return  $result;
     }
     public function getVariableTax($categoryid){
        $result =  $this->db->select('tax_slabs')->where(['id'=>$categoryid])->get('categories')->row();
       
        if($result){
            if($result->tax_slabs){
              return unserialize($result->tax_slabs);
            }else {
                return FALSE;
            }
        }else {
            return FALSE;
        }
     }
     public function getInvoiceByID_bulkDiscount($id)
     {
         if (is_array($id)) {
             // $q = $this->db->where_in('id', $id)->get('sales');
             $this->db->select('id,total,product_discount,total_discount, grand_total, order_tax_id');
             $this->db->from('sales');
             $this->db->where_in('id', $id);
             $q = $this->db->get();
             if ($q->num_rows() > 0) {
                 // return $q->row(); // or $q->result() if you expect multiple rows
                 return $q->result_array();
             }        
             return FALSE;
         } else {
             $q = $this->db->get_where('sales', array('id' => $id));
         }
         if ($q->num_rows() > 0) {
             return $q->result_array();
         }
         return FALSE;
     }
     public function updateSale_bulkDiscount($data, $items = array())
     {
         
          // GST TYPE
         //   $customer_id = $data['customer_id'];
         //   $biller_id   = $data['biller_id'];
         //   $customer_state_code = $this->sma->getstatecode($customer_id);
         //   $biller_state_code   = $this->sma->getstatecode($biller_id);
         //   $GSTType = ($customer_state_code == $biller_state_code)?'GST':'IGST'; 
         // // End GST Type          
 
         // $this->resetSaleActions($id, FALSE, TRUE);
 
         // if ($data['sale_status'] == 'completed') {
         //         $cost = $this->site->costing($items);
         // }
         
         // $total_cgst = 0;$total_sgst = 0;$total_igst = 0;
         // $this->sma->print_arrays($cost);
         $DatalogArr = array('data'=>$data, 'sale_items'=>$items);
         $DataLog = array(
             'action_type'=>'Edit',
             'product_id'=>'',
             'quantity'=>'',
             'action_reff_id'=>$id,
             'action_affected_data'=>json_encode($DatalogArr),
             'action_comment'=>'Edit sales',
         );
         $this->sma->setUserActionLog($DataLog);
         $update_data = [];
         
         if ($this->db->update_batch('sales', $data, 'id')) {
             
             // $this->sma->storeDeletedData('sales', 'id', $id,0);
             // $this->db->delete('sale_items', array('sale_id' => $id));
             // $this->db->delete('costing', array('sale_id' => $id));
             // $this->db->delete('sales_items_tax', array('sale_id' => $id));
             
             if(!empty($items)){
                 foreach ($items as $item) {
                     $this->db->where('sale_id', $item['sale_id']);
                     $this->db->where('product_id', $item['product_id']);
                     $this->db->delete('sale_items');
                     $this->db->insert('sale_items', $item);
                 }
             }
             // if(!empty($items)){
             //     foreach ($items as $item) {
             //         $id = $item['sale_id'];
             //     // $item['sale_id'] = $id;
             //     $this->db->insert('sale_items', $item);
             //     $sale_item_id = $this->db->insert_id();
                 
             //     $_taxSaleID = $id;
             //     $taxAtrr = $this->sma->taxClassification($item['tax_rate_id'],$item['net_unit_price'],$item['unit_quantity'],$sale_item_id,$_taxSaleID,$GSTType);
                 
             //       /*Add New field to Sale_items Code cgst,igst,sgst 17-1-2020*/
             //     $tax_ItemAtrr = $this->sma->taxArr_rate($item['tax_rate_id'],$item['net_unit_price'],$item['unit_quantity'],$sale_item_id,$_taxSaleID,$GSTType);
             //      /*End */  
                 
             //     if ($data['sale_status'] == 'completed' && $this->site->getProductByID($item['product_id'])) {
                    
             //        $item_costs = $this->site->item_costing($item);
                      
             //         if(!empty($item_costs)) {
             //             foreach($item_costs as $item_cost) {
             //             if(isset($item_cost['date'])) {
             //                 $item_cost['sale_item_id']  = $sale_item_id;
             //                 $item_cost['sale_id']       = $id;
             //                 if(!isset($item_cost['pi_overselling'])) {
             //                     $this->db->insert('costing', $item_cost);
             //                 }
             //             } else {
                              
             //                 if(!empty($item_cost) && (is_array($item_cost) || is_object($item_cost))){                                 
             //                         foreach ($item_cost as $key=>$ic) {                               
             //                             $item_cost['sale_item_id'] = $sale_item_id;
             //                             $item_cost['sale_id'] = $id;
                                         
             //                             if(! isset($item_cost['pi_overselling'])) {                                           
             //                                 $this->db->insert('costing', $item_cost);
             //                             }
             //                         }
             //                     }
             //                 }
             //             }
             //         }
             //     }
             //     /*New Field Add to Sales cgst,igst,sgst 17-1-2020 */
             //         $total_cgst  = $total_cgst + $tax_ItemAtrr['CGST'];
             //         $total_sgst  = $total_sgst + $tax_ItemAtrr['SGST'];
             //         $total_igst  = $total_igst + $tax_ItemAtrr['IGST'];
             //     }
 
             //     $this->db->update('sales', array('cgst' => $total_cgst, 'sgst' => $total_sgst,'igst' => $total_igst), array('id' => $id));  // Using for update GST columns
             // }
             // if ($data['sale_status'] == 'completed') {
             //     $this->site->syncPurchaseItems($cost);
             // }
             // $this->site->syncSalePayments($id);
             // $this->site->syncQuantity($id);
             // $this->sma->update_award_points($data['grand_total'], $data['customer_id'], $data['created_by']);
             return true;
         }
         return false;
     }
     public function getPreviousSaleNo(){
        $this->db->select('*');
        $this->db->from('sales');
        $this->db->where('sale_status', 'returned');
        $this->db->order_by('id', 'DESC'); // Replace 'id' with your actual column name
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            return $q->row(); // or $q->result() if you expect multiple rows
        }        
        return FALSE;
    }
    // Bundle Functionality

    public function getProductBybundelId($pid, $warehouse_id = NULL) {
        $q = $this->db->get_where('combo_items', ['product_id' => $pid]);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return false;

    }
    public function getProductComboItem($pid, $warehouse_id = NULL) {
      
        $this->db->select('products.id as id, combo_items.item_code as code, combo_items.quantity as qty,combo_items.unit_price, products.name as name,products.type as type, warehouses_products.quantity as quantity')
                ->join('products', 'products.code=combo_items.item_code', 'left')
                ->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left')
                ->group_by('combo_items.id');
        if ($warehouse_id) {
            $this->db->where('warehouses_products.warehouse_id', $warehouse_id);
        }
        $q = $this->db->get_where('combo_items', array('combo_items.product_id' => $pid));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
        return FALSE;
    }
    public function getProductName($term, $warehouse_id, $limit = 50, $warehouseRes=NULL)
    {

        $wp = "( SELECT product_id, warehouse_id, quantity as quantity from {$this->db->dbprefix('warehouses_products')} ) FWP";

        $this->db->select('products.*, FWP.quantity as quantity, categories.id as category_id, categories.name as category_name', FALSE)
            ->join($wp, 'FWP.product_id=products.id', 'left')
            ->join('categories', 'categories.id=products.category_id', 'left')
            ->join('categories as subcategories', 'subcategories.id=categories.parent_id', 'left')
            ->join('brands', 'brands.id=products.brand', 'left')
            ->join('seasons', 'seasons.id=products.season_id', 'left')

            ->group_by('products.id');
        if ($this->Settings->overselling) {
           /* $this->db->where("({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')"); */
    
            //    $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',{$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%".str_replace(" ","",$term)."%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
            $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', REPLACE(COALESCE({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') 
            OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' 
            OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' 
            OR CONCAT({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'
            OR brands.name LIKE '%" . $term . "%'
            OR categories.name LIKE '%" . $term . "%'
            OR seasons.SeasonName LIKE '%" . $term . "%'
            OR subcategories.name LIKE '%" . $term . "%')");

             if((int)$warehouse_id > 0 && !empty($warehouseRes)):
                //$this->db->where("FWP.warehouse_id = '".$warehouse_id."'");
            endif;
            
        } else {
            /* $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
                . "({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%')"); */

            //$this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
            //. "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%".str_replace(" ","",$term)."%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
            
            $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
            . "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', REPLACE(COALESCE({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') 
            OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' 
            OR CONCAT({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'
            OR brands.name LIKE '%" . $term . "%'
            OR categories.name LIKE '%" . $term . "%'
            OR seasons.SeasonName LIKE '%" . $term . "%'
            OR subcategories.name LIKE '%" . $term . "%')");
        }
        $this->db->limit($limit);
        $q = $this->db->get('products');
          
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row;
            }
            return $data;
        }
    }
    public function getProductid($term, $warehouse_id, $limit = 50, $warehouseRes=NULL)
    {

        $wp = "( SELECT product_id, warehouse_id, quantity as quantity from {$this->db->dbprefix('warehouses_products')} ) FWP";

        $this->db->select('products.*, FWP.quantity as quantity, categories.id as category_id, categories.name as category_name', FALSE)
            ->join($wp, 'FWP.product_id=products.id', 'left')
            ->join('categories', 'categories.id=products.category_id', 'left')
            ->join('categories as subcategories', 'subcategories.id=categories.parent_id', 'left')
            ->join('brands', 'brands.id=products.brand', 'left')
            ->join('seasons', 'seasons.id=products.season_id', 'left')

            ->group_by('products.id');
        if ($this->Settings->overselling) {
           /* $this->db->where("({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')"); */
    
            //    $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',{$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%".str_replace(" ","",$term)."%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
            $this->db->where("(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', REPLACE(COALESCE({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') 
            OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' 
            OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%' 
            OR CONCAT({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'
            OR brands.name LIKE '%" . $term . "%'
            OR categories.name LIKE '%" . $term . "%'
            OR seasons.SeasonName LIKE '%" . $term . "%'
            OR subcategories.name LIKE '%" . $term . "%')");

             if((int)$warehouse_id > 0 && !empty($warehouseRes)):
                //$this->db->where("FWP.warehouse_id = '".$warehouse_id."'");
            endif;
            
        } else {
            /* $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
                . "({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%' OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%')"); */

            //$this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
            //. "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%',Replace(coalesce({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%".str_replace(" ","",$term)."%') OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' OR  concat({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%')");
            
            $this->db->where("(products.track_quantity = 0 OR FWP.quantity > 0) AND FWP.warehouse_id = '" . $warehouse_id . "' AND "
            . "(IF({$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', {$this->db->dbprefix('products')}.name LIKE '%" . $term . "%', REPLACE(COALESCE({$this->db->dbprefix('products')}.name,''), ' ','') LIKE '%" . str_replace(" ", "", $term) . "%') 
            OR {$this->db->dbprefix('products')}.code LIKE '%" . $term . "%' 
            OR CONCAT({$this->db->dbprefix('products')}.name, ' (', {$this->db->dbprefix('products')}.code, ')') LIKE '%" . $term . "%'
            OR brands.name LIKE '%" . $term . "%'
            OR categories.name LIKE '%" . $term . "%'
            OR seasons.SeasonName LIKE '%" . $term . "%'
            OR subcategories.name LIKE '%" . $term . "%')");
        }
        $this->db->limit($limit);
        $q = $this->db->get('products');
          
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row->id;
            }
            return $data;
        }
    }
    public function getcombodata($Bid, $warehouse_id = null, $product_id){
        $q = $this->db->get_where('combo_items', array('combo_items.product_id' => $product_id, 'combo_items.item_code' => $Bid));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getcombodatabyId($Bid, $warehouse_id = null, $product_id){
      
        $q = $this->db->get_where('combo_items', array('combo_items.product_id' => $product_id, 'combo_items.item_code' => $Bid));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getProductBundelItems($pid, $warehouse_id = NULL) {
        $this->db->select('
                products.id as id, 
                combo_items.item_code as code, 
                combo_items.quantity as qty, 
                combo_items.unit_price, 
                products.name as name, 
                products.type as type, 
                warehouses_products.quantity as quantity,
                ' . (int)$pid . ' as product_id');  // Add $pid as product_id in the select clause
        
        $this->db->join('products', 'products.code = combo_items.item_code', 'left')
                 ->join('warehouses_products', 'warehouses_products.product_id = products.id', 'left')
                 ->group_by('combo_items.id');
                 
        if ($warehouse_id) {
            $this->db->where('warehouses_products.warehouse_id', $warehouse_id);
        }
    
        $q = $this->db->get_where('combo_items', array('combo_items.product_id' => $pid));
        
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                // Include the product_id (pid) in the result
                $data[] = (object) array_merge((array)$row, ['product_id' => $pid]);
            }
            return $data;
        }
    
        return FALSE;
    }
}
