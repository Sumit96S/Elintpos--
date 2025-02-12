<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Sitenew extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->setSettings();
    }

    public function get_total_qty_alerts() {
        $this->db->where('quantity < alert_quantity', NULL, FALSE)->where('track_quantity', 1);
        return $this->db->count_all_results('products');
    }

    public function get_expiring_qty_alerts() {
        $date = date('Y-m-d', strtotime('+3 months'));
        $this->db->select('SUM(quantity_balance) as alert_num')
                ->where('expiry !=', NULL)->where('expiry !=', '0000-00-00')
                ->where('expiry <', $date);
        $q = $this->db->get('purchase_items');
        if ($q->num_rows() > 0) {
            $res = $q->row();
            return (INT) $res->alert_num;
        }
        return FALSE;
    }

    public function get_setting() {
        $q = $this->db->get('settings');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    
    public function get_pos_setting() {
        $q = $this->db->get('pos_settings');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getDateFormat($id) {
        $q = $this->db->get_where('date_format', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllCompanies($group_name) {
        $q = $this->db->get_where('companies', array('group_name' => $group_name));
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

    public function getCustomerGroupByID($id) {
        $q = $this->db->get_where('customer_groups', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getUser($id = NULL) {
        if (!$id) {
            $id = $this->session->userdata('user_id');
        }
        $q = $this->db->get_where('users', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getUserById($id = NULL) {

        $sql = "SELECT `sma_users`.`id` as `id`, `first_name`, `last_name`, `email`, `company`, `sma_groups`.`name`, `active` FROM `sma_users` LEFT JOIN `sma_groups` ON `sma_users`.`group_id`=`sma_groups`.`id` WHERE `sma_users`.`id` ='$id' and `company_id` IS NULL GROUP BY `sma_users`.`id`";
        $q = $this->db->query($sql);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllUser() {

        $sql = 'SELECT `sma_users`.`id` as `id`, `first_name`, `last_name`, `email`, `company`, `sma_groups`.`name`, `active` FROM `sma_users` LEFT JOIN `sma_groups` ON `sma_users`.`group_id`=`sma_groups`.`id` WHERE `company_id` IS NULL GROUP BY `sma_users`.`id`';
        $q = $this->db->query($sql);
        if ($q->num_rows() > 0) {
            return $q->result_array();
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

    public function getAllCurrencies() {
        $q = $this->db->get('currencies');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCurrencyByCode($code) {
        $q = $this->db->get_where('currencies', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllTaxRates() {
        $q = $this->db->get('tax_rates');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getTaxRateByID($id) {
        $q = $this->db->get_where('tax_rates', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllWarehouses() {
        $q = $this->db->get('warehouses');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getWarehouseByID($id) {

        if (is_numeric($id)) {
            $q = $this->db->get_where('warehouses', array('id' => $id), 1);
            if ($q->num_rows() > 0) {
                $data[$id] = $q->row();
                return $data;
            }
        } else {
            return $this->getWarehouseByIDs($id);
        }
        return FALSE;
    }

    /* 11-28-2019 */

    public function getWarehouseBy_ID($id) {

        if (is_numeric($id)) {
            $q = $this->db->get_where('warehouses', array('id' => $id));
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        } else {
            return $this->getWarehouseByIDs($id);
        }
        return FALSE;
    }

    public function getWarehouseByIDs($id) {

        $wrids = explode(',', $id);

        $q = $this->db->where_in('id', $wrids)->get('warehouses');
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProdCategories() {

        $sql = "SELECT * FROM `sma_categories` WHERE `id` in (SELECT distinct `category_id` FROM `sma_products`) ORDER BY `name`";
        $q = $this->db->query($sql);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllCategories() {

        $this->db->where('parent_id', NULL)->or_where('parent_id', 0)->order_by('name');
        $q = $this->db->get("categories");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProdSubCategories($parent_id) {

        $sql = "SELECT * FROM `sma_categories` WHERE `id` IN ( SELECT distinct `subcategory_id` FROM `sma_products` WHERE `category_id` = '$parent_id')";

        $q = $this->db->query($sql);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getSubCategories($parent_id) {
        $this->db->where('parent_id', $parent_id)->order_by('name');
        $q = $this->db->get("categories");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPrdCategories($id) {
        $this->db->where(array('category_id' => $id));
        $q = $this->db->get("products");

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPrdSubCategories($id) {
        $this->db->where(array('subcategory_id' => $id));
        $q = $this->db->get("products");

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPrdUnit($id) {
        $this->db->where(array('sale_unit' => $id))->or_where('purchase_unit', $id)->or_where('unit', $id);
        $q = $this->db->get("products");

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCategoryByID($id) {
        $q = $this->db->get_where('categories', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getGiftCardByID($id) {
        $q = $this->db->get_where('gift_cards', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getGiftCardByNO($no) {
        $q = $this->db->get_where('gift_cards', array('card_no' => $no), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateInvoiceStatus() {
        $date = date('Y-m-d');
        $q = $this->db->get_where('invoices', array('status' => 'unpaid'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                if ($row->due_date < $date) {
                    $this->db->update('invoices', array('status' => 'due'), array('id' => $row->id));
                }
            }
            $this->db->update('settings', array('update' => $date), array('setting_id' => '1'));
            return true;
        }
    }

    public function modal_js() {
        return '<script type="text/javascript">' . file_get_contents($this->data['assets'] . 'js/modal.js') . '</script>';
    }

    /**
     * Original Function Defination.
     * Devided in multiple function
     */
    /*
      public function getReference($field) {
      $q = $this->db->get_where('order_ref', array('ref_id' => '1'), 1);
      if ($q->num_rows() > 0) {
      $ref = $q->row();

      switch ($field) {
      case 'so':
      $prefix = $this->Settings->sales_prefix;
      break;
      case 'pos':
      $prefix = isset($this->Settings->sales_prefix) ? $this->Settings->sales_prefix . '/POS' : '';
      break;
      case 'qu':
      $prefix = $this->Settings->quote_prefix;
      break;
      case 'po':
      $prefix = $this->Settings->purchase_prefix;
      break;
      case 'to':
      $prefix = $this->Settings->transfer_prefix;
      break;
      case 'do':
      $prefix = $this->Settings->delivery_prefix;
      break;
      case 'pay':
      $prefix = $this->Settings->payment_prefix;
      break;
      case 'ppay':
      $prefix = $this->Settings->ppayment_prefix;
      break;
      case 'ex':
      $prefix = $this->Settings->expense_prefix;
      break;
      case 're':
      $prefix = $this->Settings->return_prefix;
      break;
      case 'rep':
      $prefix = $this->Settings->returnp_prefix;
      break;
      case 'qa':
      $prefix = $this->Settings->returnp_prefix;
      break;
      case 'offapp':
      $prefix = 'OFFLINE/SALE';
      break;
      case 'eshop':
      $prefix = 'ESHOP/SALE';
      break;
      default:
      $prefix = '';
      }

      $ref_no = (!empty($prefix)) ? $prefix . '/' : '';

      if ($this->Settings->reference_format == 1) {
      $ref_no .= date('Y') . "/" . sprintf("%04s", $ref->{$field});
      } elseif ($this->Settings->reference_format == 2) {
      $ref_no .= date('Y') . "/" . date('m') . "/" . sprintf("%04s", $ref->{$field});
      } elseif ($this->Settings->reference_format == 3) {
      $ref_no .= sprintf("%04s", $ref->{$field});
      } else {
      $ref_no .= $this->getRandomReference();
      }

      return $ref_no;
      }
      return FALSE;
      }
     */

    /**
     * Function Modified by sunil dated on: 08-09-2017
     */
    public function getReference($field) {

        $refnum = $this->getNextReference($field);

        if ($refnum) {

            $ref_no = $this->getReferenceFormat($field, $refnum);

            return $ref_no;
        }

        return false;
    }

    public function getReferenceNumber($field, $refFormat = '') {

        $refnum = $this->getNextReference($field);

        if ($refnum) {

            return $this->getReferenceFormat($field, $refnum, $refFormat);
        }

        return false;
    }

    public function getReferenceFormat($field, $refnum, $refFormat = '') {

        $prefix = $this->getReferencePrefix($field);

        $ref_no = (!empty($prefix)) ? $prefix . '/' : '';

        $refFormat = (empty($refFormat)) ? $this->Settings->reference_format : $refFormat;

        if ($refFormat == 1) {
            $ref_no .= date('Y') . "/" . sprintf("%04s", $refnum);
        } elseif ($refFormat == 2) {
            $ref_no .= date('Y') . "/" . date('m') . "/" . sprintf("%04s", $refnum);
        } elseif ($refFormat == 3) {
            $ref_no .= sprintf("%04s", $refnum);
        } else {
            $ref_no .= $this->getRandomReference();
        }

        return $ref_no;
    }

    public function getNextReference($field) {

        $q = $this->db->get_where('order_ref', array('ref_id' => '1'), 1);

        if ($q->num_rows() > 0) {
            $ref = (array) $q->row();
            return $ref[$field];
        }
        return false;
    }

    public function setSettings() {

        if (!isset($this->Settings)) {
            $this->Settings = $this->get_setting();
        }
    }

    public function getReferencePrefix($field) {

        switch ($field) {
            case 'so':
                $prefix = $this->Settings->sales_prefix;
                break;
            case 'pos':
                $prefix = isset($this->Settings->sales_prefix) ? $this->Settings->sales_prefix . '/POS' : '';
                break;
            case 'qu':
                $prefix = $this->Settings->quote_prefix;
                break;
            case 'po':
                $prefix = $this->Settings->purchase_prefix;
                break;
            case 'to':
                $prefix = $this->Settings->transfer_prefix;
                break;
            case 'do':
                $prefix = $this->Settings->delivery_prefix;
                break;
            case 'pay':
                $prefix = $this->Settings->payment_prefix;
                break;
            case 'ppay':
                $prefix = $this->Settings->ppayment_prefix;
                break;
            case 'ex':
                $prefix = $this->Settings->expense_prefix;
                break;
            case 're':
                $prefix = $this->Settings->return_prefix;
                break;
            case 'rep':
                $prefix = $this->Settings->returnp_prefix;
                break;
            case 'qa':
                $prefix = $this->Settings->returnp_prefix;
                break;
            case 'offapp':
                $prefix = 'OFFLINE/SALE';
                break;
            case 'eshop':
                $prefix = 'ESHOP/SALE';
                break;
            case 'up':
                $prefix = 'UP/SALE';
                break;

            default:
                $prefix = '';
                break;
        }

        return $prefix;
    }

    public function getRandomReference($len = 12) {
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $result .= mt_rand(0, 9);
        }

        if ($this->getSaleByReference($result)) {
            $this->getRandomReference();
        }

        return $result;
    }

    public function getSaleByReference($ref) {
        $this->db->like('reference_no', $ref, 'before');
        $q = $this->db->get('sales', 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateReference($field) {

        $q = $this->db->get_where('order_ref', array('ref_id' => '1'), 1);

        if ($q->num_rows() > 0) {
            $ref = $q->row();
            $this->db->update('order_ref', array($field => $ref->{$field} + 1), array('ref_id' => '1'));
            return TRUE;
        } else {
            echo $this->db->error();
            return FALSE;
        }
    }

    public function checkPermissions() {
        $q = $this->db->get_where('permissions', array('group_id' => $this->session->userdata('group_id')), 1);
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getNotifications() {
        $date = date('Y-m-d H:i:s', time());
        $this->db->where("from_date <=", $date);
        $this->db->where("till_date >=", $date);
        if (!$this->Owner) {
            if ($this->Supplier) {
                $this->db->where('scope', 4);
            } elseif ($this->Customer) {
                $this->db->where('(scope = 1 or scope = 3)');
                //$this->db->where('scope', 1)->or_where('scope', 3);
            } elseif (!$this->Customer && !$this->Supplier) {
                $this->db->where('(scope = 2 or scope = 3)');
                // $this->db->where('scope', 2)->or_where('scope', 3);
            }
        }
        $q = $this->db->get("notifications");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getUpcomingEvents() {
        $dt = date('Y-m-d');
        $this->db->where('start >=', $dt)->order_by('start')->limit(5);
        if ($this->Settings->restrict_calendar) {
            $this->db->where('user_id', $this->session->userdata('user_id'));
        }

        $q = $this->db->get('calendar');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getUserGroup($user_id = false) {
        if (!$user_id) {
            $user_id = $this->session->userdata('user_id');
        }
        $group_id = $this->getUserGroupID($user_id);
        $q = $this->db->get_where('groups', array('id' => $group_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getUserGroupID($user_id = false) {
        $user = $this->getUser($user_id);
        return $user->group_id;
    }

    public function getWarehouseProductsVariants($option_id, $warehouse_id = NULL) {
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        $q = $this->db->get_where('warehouses_products_variants', array('option_id' => $option_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPurchasedItem($where_clause) {
        $orderby = ($this->Settings->accounting_method == 1) ? 'desc' : 'asc';
        $this->db->order_by('date', $orderby);
        $this->db->order_by('purchase_id', $orderby);
        $q = $this->db->get_where('purchase_items', $where_clause);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /*
      public function syncVariantQty($variant_id, $warehouse_id, $product_id = NULL) {
      $balance_qty = $this->getBalanceVariantQuantity($variant_id);
      $wh_balance_qty = $this->getBalanceVariantQuantity($variant_id, $warehouse_id);


      // Return product variant and warehouse variant Qty
      $balance_return_qty = abs($this->getBalanceVariantQuantityretrun($variant_id));
      $wh_balance_qty_returnqty =abs($this->getBalanceVariantQuantityretrun($variant_id, $warehouse_id));
      // End

      if ($this->db->update('product_variants', array('quantity' => $balance_qty - $balance_return_qty), array('id' => $variant_id))) {
      if ($this->getWarehouseProductsVariants($variant_id, $warehouse_id)) {
      $this->db->update('warehouses_products_variants', array('quantity' => $wh_balance_qty - $wh_balance_qty_returnqty), array('option_id' => $variant_id, 'warehouse_id' => $warehouse_id));
      } else {
      if($wh_balance_qty) {
      $this->db->insert('warehouses_products_variants', array('quantity' => $wh_balance_qty - $wh_balance_qty_returnqty, 'option_id' => $variant_id, 'warehouse_id' => $warehouse_id, 'product_id' => $product_id));
      }
      }
      return TRUE;
      }
      return FALSE;
      }
     */

    public function syncVariantQty($variant_id, $warehouse_id, $product_id = NULL) {
        $balance_qty = $this->getBalanceVariantQuantity($variant_id);
        $wh_balance_qty = $this->getBalanceVariantQuantity($variant_id, $warehouse_id);
        if ($this->db->update('product_variants', array('quantity' => $balance_qty), array('id' => $variant_id))) {
            if ($this->getWarehouseProductsVariants($variant_id, $warehouse_id)) {
                $this->db->update('warehouses_products_variants', array('quantity' => $wh_balance_qty), array('option_id' => $variant_id, 'warehouse_id' => $warehouse_id));
            } else {
                if ($wh_balance_qty) {
                    $this->db->insert('warehouses_products_variants', array('quantity' => $wh_balance_qty, 'option_id' => $variant_id, 'warehouse_id' => $warehouse_id, 'product_id' => $product_id));
                }
            }
            return TRUE;
        }
        return FALSE;
    }

    public function getWarehouseProducts($product_id, $warehouse_id = NULL) {
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        $q = $this->db->get_where('warehouses_products', array('product_id' => $product_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function syncProductQty($product_id, $warehouse_id) {
        $balance_qty = $this->getBalanceQuantity($product_id);
        $wh_balance_qty = $this->getBalanceQuantity($product_id, $warehouse_id);
        if ($this->db->update('products', array('quantity' => $balance_qty), array('id' => $product_id))) {
            if ($this->getWarehouseProducts($product_id, $warehouse_id)) {
                $this->db->update('warehouses_products', array('quantity' => $wh_balance_qty), array('product_id' => $product_id, 'warehouse_id' => $warehouse_id));
            } else {
                if (!$wh_balance_qty) {
                    $wh_balance_qty = 0;
                }
                $product = $this->site->getProductByID($product_id);
                if(!empty($product)){
                $this->db->insert('warehouses_products', array('quantity' => $wh_balance_qty, 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'avg_cost' => $product->cost));
                }
            }
            return TRUE;
        }
        return FALSE;
    }

    public function getSaleByID($id) {
        $q = $this->db->get_where('sales', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

   public function getSaleByIDEshop($id) {
        $q = $this->db->get_where('orders', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getSaleBySaleInvoice($id) {
        $q = $this->db->get_where('orders', array('sale_invoice_no' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getOrderByID($id) {
        $q = $this->db->get_where('orders', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getSalePayments($sale_id) {
        $q = $this->db->get_where('payments', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getOrderPayments($order_id) {
        $q = $this->db->get_where('payments', array('order_id' => $order_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function syncSaleActionPayments($id, $sale_action = '') {

        if ($sale_action == 'eshop_order') {
            return $this->syncOrderPayments($id);
        } elseif ($sale_action == 'chalan') {
            return $this->syncOrderPayments($id);
        } else {
            return $this->syncSalePayments($id);
        }
    }

    public function syncSalePayments($id) {
        $sale = $this->getSaleByID($id);
        $payments = $this->getSalePayments($id);
        $paid = 0;
        $grand_total = $sale->grand_total + $sale->rounding;
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $paid += $payment->amount;
            }
        }
        $payment_status = $paid == 0 ? 'pending' : $sale->payment_status;
        // updated  by SW for partial 03022017
        if ($this->sma->formatDecimal($grand_total) <= $this->sma->formatDecimal($paid)) {
            $payment_status = 'paid';
        } elseif ($paid != 0) {
            $payment_status = 'partial';
        } elseif ($sale->due_date <= date('Y-m-d') && !$sale->sale_id) {
            $payment_status = 'due';
        }

        if ($this->db->update('sales', array('paid' => $paid, 'payment_status' => $payment_status), array('id' => $id))) {
            return true;
        }

        return FALSE;
    }

    public function syncOrderPayments($id) {
        $sale = $this->getOrderByID($id);
        $payments = $this->getOrderPayments($id);
        $paid = 0;
        $grand_total = $sale->grand_total + $sale->rounding;
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $paid += $payment->amount;
            }
        }
        $payment_status = $paid == 0 ? 'pending' : $sale->payment_status;
        // updated  by SW for partial 03022017
        if ($this->sma->formatDecimal($grand_total) <= $this->sma->formatDecimal($paid)) {
            $payment_status = 'paid';
        } elseif ($paid != 0) {
            $payment_status = 'partial';
        } elseif ($sale->due_date <= date('Y-m-d') && !$sale->sale_id) {
            $payment_status = 'due';
        }

        if ($this->db->update('orders', array('paid' => $paid, 'payment_status' => $payment_status), array('id' => $id))) {
            return true;
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

    public function getPurchasePayments($purchase_id) {
        $q = $this->db->get_where('payments', array('purchase_id' => $purchase_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function syncPurchasePayments($id) {
        $purchase = $this->getPurchaseByID($id);
        $payments = $this->getPurchasePayments($id);
        $paid = 0;
        if (is_array($payments)) {
            foreach ($payments as $payment) {
                $paid += $payment->amount;
            }
        }

        $payment_status = $paid <= 0 ? 'pending' : $purchase->payment_status;
        if ($this->sma->formatDecimal($purchase->grand_total) > $this->sma->formatDecimal($paid) && $paid > 0) {
            $payment_status = 'partial';
        } elseif ($this->sma->formatDecimal($purchase->grand_total) <= $this->sma->formatDecimal($paid)) {
            $payment_status = 'paid';
        }

        if ($this->db->update('purchases', array('paid' => $paid, 'payment_status' => $payment_status), array('id' => $id))) {
            return true;
        }

        return FALSE;
    }

    private function getBalanceQuantity($product_id, $warehouse_id = NULL) {
        $this->db->select('SUM(COALESCE(quantity_balance, 0)) as stock', False);
        $this->db->where('product_id', $product_id)->where('quantity_balance !=', 0);
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        $this->db->group_start()->where('status', 'received')->or_where('status', 'partial')->or_where('status', 'returned')->group_end();
        $q = $this->db->get('purchase_items');
        if ($q->num_rows() > 0) {
            $data = $q->row();
            return $data->stock;
        }
        return 0;
    }

    private function getBalanceVariantQuantity($variant_id, $warehouse_id = NULL) {
        $this->db->select('SUM(COALESCE(quantity_balance, 0)) as stock', False);
        $this->db->where('option_id', $variant_id)->where('quantity_balance !=', 0);
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        $this->db->group_start()->where('status', 'received')->or_where('status', 'partial')->group_end();
        $q = $this->db->get('purchase_items');
        if ($q->num_rows() > 0) {
            $data = $q->row();
            return $data->stock;
        }
        return 0;
    }

    /**
     *  04-12-2019
     * @param type $variant_id
     * @param type $warehouse_id
     * @return int
     * @param type $variant_id
     * @param type $warehouse_id
     * @return int Get Return Qty 
     */
    private function getBalanceVariantQuantityretrun($variant_id, $warehouse_id = NULL) {
        $this->db->select('SUM(COALESCE(quantity_balance, 0)) as stock', False);
        $this->db->where('option_id', $variant_id)->where('quantity_balance !=', 0);
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        $this->db->group_start()->where('status', 'returned')->or_where('status', 'partial')->group_end();
        $q = $this->db->get('purchase_items');
        if ($q->num_rows() > 0) {
            $data = $q->row();
            return $data->stock;
        }
        return 0;
    }

    /**
     * End Get return qty 
     */
    public function calculateAVCost($product_id, $warehouse_id, $net_unit_price, $unit_price, $quantity, $product_name, $option_id, $item_quantity) {
        $real_item_qty = $quantity;
        $wp_details = $this->getWarehouseProduct($warehouse_id, $product_id);
        $product_avg_cost = $this->db->select('cost')->where('id', $product_id)->get('products')->row();

        if ($pis = $this->getPurchasedItems($product_id, $warehouse_id, $option_id)) {
            $cost_row = array();
            $quantity = $item_quantity;
            $balance_qty = $quantity;
            $avg_net_unit_cost = $wp_details->avg_cost;
            $avg_unit_cost = $wp_details->avg_cost;
            foreach ($pis as $pi) {
                if (!empty($pi) && $pi->quantity > 0 && $balance_qty <= $quantity && $quantity > 0) {
                    if ($pi->quantity_balance >= $quantity && $quantity > 0) {
                        $balance_qty = $pi->quantity_balance - $quantity;
                        $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $real_item_qty, 'purchase_net_unit_cost' => $avg_net_unit_cost, 'purchase_unit_cost' => $avg_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => $balance_qty, 'inventory' => 1, 'option_id' => $option_id);
                        $quantity = 0;
                    } elseif ($quantity > 0) {
                        $quantity = $quantity - $pi->quantity_balance;
                        $balance_qty = $quantity;
                        $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $pi->quantity_balance, 'purchase_net_unit_cost' => $avg_net_unit_cost, 'purchase_unit_cost' => $avg_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => 0, 'inventory' => 1, 'option_id' => $option_id);
                    }
                }
                if (empty($cost_row)) {
                    break;
                }
                $cost[] = $cost_row;
                if ($quantity == 0) {
                    break;
                }
            }
        }
        if ($quantity > 0 && !$this->Settings->overselling) {
            $this->session->set_flashdata('error', sprintf(lang("quantity_out_of_stock_for_%s"), ($pi->product_name ? $pi->product_name : $product_name)));
            redirect($_SERVER["HTTP_REFERER"]);
        } elseif ($quantity > 0) {
            $cost[] = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => NULL, 'quantity' => $real_item_qty, 'purchase_net_unit_cost' => is_array($wp_details) ? $wp_details->avg_cost : $product_avg_cost->cost, 'purchase_unit_cost' => is_array($wp_details) ? $wp_details->avg_cost : $product_avg_cost->cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => NULL, 'overselling' => 1, 'inventory' => 1);
            $cost[] = array('pi_overselling' => 1, 'product_id' => $product_id, 'quantity_balance' => (0 - $quantity), 'warehouse_id' => $warehouse_id, 'option_id' => $option_id);
        }
        return $cost;
    }

    public function calculateCost($product_id, $warehouse_id, $net_unit_price, $unit_price, $quantity, $product_name, $option_id, $item_quantity) {
        $pis = $this->getPurchasedItems($product_id, $warehouse_id, $option_id);
        $real_item_qty = $quantity;
        $quantity = $item_quantity;
        $balance_qty = $quantity;
        foreach ($pis as $pi) {
            $cost_row = NULL;
            if (!empty($pi) && $balance_qty <= $quantity && $quantity > 0) {
                $purchase_unit_cost = $pi->unit_cost ? $pi->unit_cost : ($pi->net_unit_cost + ($pi->item_tax / $pi->quantity));
                if ($pi->quantity_balance >= $quantity && $quantity > 0) {
                    $balance_qty = $pi->quantity_balance - $quantity;
                    $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $real_item_qty, 'purchase_net_unit_cost' => $pi->net_unit_cost, 'purchase_unit_cost' => $purchase_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => $balance_qty, 'inventory' => 1, 'option_id' => $option_id);
                    $quantity = 0;
                } elseif ($quantity > 0) {
                    $quantity = $quantity - $pi->quantity_balance;
                    $balance_qty = $quantity;
                    $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $pi->quantity_balance, 'purchase_net_unit_cost' => $pi->net_unit_cost, 'purchase_unit_cost' => $purchase_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => 0, 'inventory' => 1, 'option_id' => $option_id);
                }
            }
            $cost[] = $cost_row;
            if ($quantity == 0) {
                break;
            }
        }
        if ($quantity > 0) {
            $this->session->set_flashdata('error', sprintf(lang("quantity_out_of_stock_for_%s"), ($pi->product_name ? $pi->product_name : $product_name)));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        return $cost;
    }

    public function getPurchasedItems($product_id, $warehouse_id, $option_id = NULL) {
        $orderby = ($this->Settings->accounting_method == 1) ? 'desc' : 'asc';

        $this->db->select('id, quantity, quantity_balance, net_unit_cost, unit_cost, item_tax');
        $this->db->where('product_id', $product_id)->where('warehouse_id', $warehouse_id)->where('quantity_balance >', 0);
        if ($option_id) {
            $this->db->where('option_id', $option_id);
        }
        $this->db->group_start()->where('status', 'received')->or_where('status', 'partial')->group_end();
        $this->db->group_by('id');
        $this->db->order_by('date', $orderby);
        $this->db->order_by('purchase_id', $orderby);
        $q = $this->db->get('purchase_items');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductComboItems($pid, $warehouse_id = NULL) {
        $this->db->select('products.id as id, combo_items.item_code as code, combo_items.quantity as qty, products.name as name, products.type as type, combo_items.unit_price as unit_price, warehouses_products.quantity as quantity')
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

    public function item_costing($item, $pi = NULL) {
        $item_quantity = $pi ? $item['aquantity'] : $item['quantity'];
        if (!isset($item['option_id']) || empty($item['option_id']) || $item['option_id'] == 'null') {
            $item['option_id'] = NULL;
        }
        
        if ($this->Settings->accounting_method != 2 && !$this->Settings->overselling) {

            if ($this->getProductByID($item['product_id'])) {
                if ($item['product_type'] == 'standard') {
                    $unit = $this->getUnitByID($item['product_unit_id']);
                    $item['net_unit_price'] = $this->convertToBase($unit, $item['net_unit_price']);
                    $item['unit_price'] = $this->convertToBase($unit, $item['unit_price']);
                    $cost = $this->calculateCost($item['product_id'], $item['warehouse_id'], $item['net_unit_price'], $item['unit_price'], $item['quantity'], $item['product_name'], $item['option_id'], $item_quantity);
                } elseif ($item['product_type'] == 'combo') {
                    $combo_items = $this->getProductComboItems($item['product_id'], $item['warehouse_id']);
                    foreach ($combo_items as $combo_item) {
                        $pr = $this->getProductByCode($combo_item->code);
                        if ($pr->tax_rate) {
                            $pr_tax = $this->getTaxRateByID($pr->tax_rate);
                            if ($pr->tax_method) {
                                $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $pr_tax->rate) / (100 + $pr_tax->rate));
                                $net_unit_price = $combo_item->unit_price - $item_tax;
                                $unit_price = $combo_item->unit_price;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $pr_tax->rate) / 100);
                                $net_unit_price = $combo_item->unit_price;
                                $unit_price = $combo_item->unit_price + $item_tax;
                            }
                        } else {
                            $net_unit_price = $combo_item->unit_price;
                            $unit_price = $combo_item->unit_price;
                        }
                        if ($pr->type == 'standard') {
                            $cost[] = $this->calculateCost($pr->id, $item['warehouse_id'], $net_unit_price, $unit_price, ($combo_item->qty * $item['quantity']), $pr->name, NULL, $item_quantity);
                        } else {
                            $cost[] = array(array('date' => date('Y-m-d'), 'product_id' => $pr->id, 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => NULL, 'quantity' => ($combo_item->qty * $item['quantity']), 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $combo_item->unit_price, 'sale_unit_price' => $combo_item->unit_price, 'quantity_balance' => NULL, 'inventory' => NULL));
                        }
                    }
                } else {
                    $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
                }
            } elseif ($item['product_type'] == 'manual') {
                $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
            }
        } else {

            if ($this->getProductByID($item['product_id'])) {
                if ($item['product_type'] == 'standard') {
                    $cost = $this->calculateAVCost($item['product_id'], $item['warehouse_id'], $item['net_unit_price'], $item['unit_price'], $item['quantity'], $item['product_name'], $item['option_id'], $item_quantity);
                } elseif ($item['product_type'] == 'combo') {
                    $combo_items = $this->getProductComboItems($item['product_id'], $item['warehouse_id']);
                    foreach ($combo_items as $combo_item) {
                        $cost = $this->calculateAVCost($combo_item->id, $item['warehouse_id'], $item['net_unit_price'], $item['unit_price'], ($combo_item->qty * $item['quantity']), $item['product_name'], $item['option_id'], $item_quantity);
                    }
                } else {
                    $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
                }
            } elseif ($item['product_type'] == 'manual') {
                $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'sale_item_id' => 'sale_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
            }
        }
        return $cost;
    }

    public function costing($items) {
        $citems = array();
        foreach ($items as $item) {
            $pr = $this->getProductByID($item['product_id']);
            if ($pr->type == 'standard') {
                if (isset($citems['p' . $item['product_id'] . 'o' . $item['option_id']])) {
                    $citems['p' . $item['product_id'] . 'o' . $item['option_id']]['aquantity'] += $item['quantity'];
                } else {
                    $citems['p' . $item['product_id'] . 'o' . $item['option_id']] = $item;
                    $citems['p' . $item['product_id'] . 'o' . $item['option_id']]['aquantity'] = $item['quantity'];
                }
            } elseif ($pr->type == 'combo') {
                $combo_items = $this->getProductComboItems($item['product_id'], $item['warehouse_id']);
                foreach ($combo_items as $combo_item) {
                    if ($combo_item->type == 'standard') {
                        if (isset($citems['p' . $combo_item->id . 'o' . $item['option_id']])) {
                            $citems['p' . $combo_item->id . 'o' . $item['option_id']]['aquantity'] += ($combo_item->qty * $item['quantity']);
                        } else {
                            $cpr = $this->getProductByID($combo_item->id);
                            if ($cpr->tax_rate) {
                                $cpr_tax = $this->getTaxRateByID($cpr->tax_rate);
                                if ($cpr->tax_method) {
                                    $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $cpr_tax->rate) / (100 + $cpr_tax->rate));
                                    $net_unit_price = $combo_item->unit_price - $item_tax;
                                    $unit_price = $combo_item->unit_price;
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $cpr_tax->rate) / 100);
                                    $net_unit_price = $combo_item->unit_price;
                                    $unit_price = $combo_item->unit_price + $item_tax;
                                }
                            } else {
                                $net_unit_price = $combo_item->unit_price;
                                $unit_price = $combo_item->unit_price;
                            }
                            $cproduct = array('product_id' => $combo_item->id, 'product_name' => $cpr->name, 'product_type' => $combo_item->type, 'quantity' => ($combo_item->qty * $item['unit_quantity']), 'net_unit_price' => $net_unit_price, 'unit_price' => $unit_price, 'warehouse_id' => $item['warehouse_id'], 'item_tax' => $item_tax, 'tax_rate_id' => $cpr->tax_rate, 'tax' => ($cpr_tax->type == 1 ? $cpr_tax->rate . '%' : $cpr_tax->rate), 'option_id' => NULL);
                            $citems['p' . $combo_item->id . 'o' . $item['option_id']] = $cproduct;
                            $citems['p' . $combo_item->id . 'o' . $item['option_id']]['aquantity'] = ($combo_item->qty * $item['quantity']);
                        }
                    }
                }
            }
        }
        // $this->sma->print_arrays($combo_items, $citems);
        $cost = array();
        foreach ($citems as $item) {
            if($item['option_id']!='')
              $item['aquantity'] = $citems['p' . $item['product_id'] . 'o' . $item['option_id']]['aquantity'];
            $cost[] = $this->item_costing($item, TRUE);
        }
        return $cost;
    }

    public function syncQuantity($sale_id = NULL, $purchase_id = NULL, $oitems = NULL, $product_id = NULL, $order_id = NULL) {

        if ($sale_id) {
            $sale_items = $this->getAllSaleItems($sale_id);
            foreach ($sale_items as $item) {
                if ($item->product_type == 'standard') {
                    $this->syncProductQty($item->product_id, $item->warehouse_id);
                    if (isset($item->option_id) && !empty($item->option_id)) {
                        $this->syncVariantQty($item->option_id, $item->warehouse_id, $item->product_id);
                    }
                } elseif ($item->product_type == 'combo') {
                    $combo_items = $this->getProductComboItems($item->product_id, $item->warehouse_id);
                    foreach ($combo_items as $combo_item) {
                        if ($combo_item->type == 'standard') {
                            $this->syncProductQty($combo_item->id, $item->warehouse_id);
                        }
                    }
                }
            }
        } elseif ($order_id) {
            $order_items = $this->getAllOrderItems($order_id);
            foreach ($order_items as $item) {
                if ($item->product_type == 'standard') {
                    $this->syncProductQty($item->product_id, $item->warehouse_id);
                    if (isset($item->option_id) && !empty($item->option_id)) {
                        $this->syncVariantQty($item->option_id, $item->warehouse_id, $item->product_id);
                    }
                } elseif ($item->product_type == 'combo') {
                    $combo_items = $this->getProductComboItems($item->product_id, $item->warehouse_id);
                    foreach ($combo_items as $combo_item) {
                        if ($combo_item->type == 'standard') {
                            $this->syncProductQty($combo_item->id, $item->warehouse_id);
                        }
                    }
                }
            }
        } elseif ($purchase_id) {

            $purchase_items = $this->getAllPurchaseItems($purchase_id);
            foreach ($purchase_items as $item) {
                $this->syncProductQty($item->product_id, $item->warehouse_id);
                if (isset($item->option_id) && !empty($item->option_id)) {
                    $this->syncVariantQty($item->option_id, $item->warehouse_id, $item->product_id);
                }
            }
        } elseif ($oitems) {

            foreach ($oitems as $item) {
                if (isset($item->product_type)) {
                    if ($item->product_type == 'standard') {
                        $this->syncProductQty($item->product_id, $item->warehouse_id);
                        if (isset($item->option_id) && !empty($item->option_id)) {
                            $this->syncVariantQty($item->option_id, $item->warehouse_id, $item->product_id);
                        }
                    } elseif ($item->product_type == 'combo') {
                        $combo_items = $this->getProductComboItems($item->product_id, $item->warehouse_id);
                        foreach ($combo_items as $combo_item) {
                            if ($combo_item->type == 'standard') {
                                $this->syncProductQty($combo_item->id, $item->warehouse_id);
                            }
                        }
                    }
                } else {
                    $this->syncProductQty($item->product_id, $item->warehouse_id);
                    if (isset($item->option_id) && !empty($item->option_id)) {
                        $this->syncVariantQty($item->option_id, $item->warehouse_id, $item->product_id);
                    }
                }
            }
        } elseif ($product_id) {
            $warehouses = $this->getAllWarehouses();
            foreach ($warehouses as $warehouse) {
                $this->syncProductQty($product_id, $warehouse->id);
                if ($product_variants = $this->getProductVariants($product_id)) {
                    foreach ($product_variants as $pv) {
                        $this->syncVariantQty($pv->id, $warehouse->id, $product_id);
                    }
                }
            }
        }
    }

    public function getProductVariants($product_id) {
        $q = $this->db->get_where('product_variants', array('product_id' => $product_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllSaleItems($sale_id) {
        $q = $this->db->get_where('sale_items', array('sale_id' => $sale_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllOrderItems($order_id) {
        $q = $this->db->get_where('order_items', array('sale_id' => $order_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllPurchaseItems($purchase_id) {
        $q = $this->db->get_where('purchase_items', array('purchase_id' => $purchase_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function syncPurchaseItems($data = array()) {
        if (!empty($data)) {
            foreach ($data as $items) {
                foreach ($items as $item) {
                    if (isset($item['pi_overselling'])) {
                        unset($item['pi_overselling']);
                        $option_id = (isset($item['option_id']) && !empty($item['option_id'])) ? $item['option_id'] : NULL;
                        $clause = array('purchase_id' => NULL, 'transfer_id' => NULL, 'product_id' => $item['product_id'], 'warehouse_id' => $item['warehouse_id'], 'option_id' => $option_id);
                        if ($pi = $this->getPurchasedItem($clause)) {
                            $quantity_balance = $pi->quantity_balance + $item['quantity_balance'];
                            $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $pi->id));
                        } else {
                            $clause['quantity'] = 0;
                            $clause['item_tax'] = 0;
                            $clause['quantity_balance'] = $item['quantity_balance'];
                            $clause['status'] = 'received';
                            $this->db->insert('purchase_items', $clause);
                        }
                    } else {
                        if ($item['inventory']) {
                            $this->db->update('purchase_items', array('quantity_balance' => $item['quantity_balance']), array('id' => $item['purchase_item_id']));
                        }
                    }
                }
            }
            return TRUE;
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

    public function check_customer_deposit($customer_id, $amount) {
        $customer = $this->getCompanyByID($customer_id);
        return $customer->deposit_amount >= $amount;
    }

    public function getWarehouseProduct($warehouse_id, $product_id) {
        $q = $this->db->get_where('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllBaseUnits() {
        $q = $this->db->get_where("units", array('base_unit' => NULL));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getUnitsByBUID($base_unit) {
        $this->db->where('id', $base_unit)->or_where('base_unit', $base_unit);
        $q = $this->db->get("units");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getUnitByID($id) {
        $q = $this->db->get_where("units", array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getPriceGroupByID($id) {
        $q = $this->db->get_where('price_groups', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductGroupPrice($product_id, $group_id) {
        $q = $this->db->get_where('product_prices', array('price_group_id' => $group_id, 'product_id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllBrands() {
        $q = $this->db->get("brands");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getBrandByID($id) {
        $q = $this->db->get_where('brands', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function convertToBase($unit, $value) {
        switch ($unit->operator) {
            case '*':
                return $value / $unit->operation_value;
                break;
            case '/':
                return $value * $unit->operation_value;
                break;
            case '+':
                return $value - $unit->operation_value;
                break;
            case '-':
                return $value + $unit->operation_value;
                break;
            default:
                return $value;
        }
    }

    public function getEshopPaymentDueOrder() {
        $this->db->select('count(*) as cnt');
        $q = $this->db->get_where('sales', array('eshop_sale' => 1, 'sale_status' => 'completed', 'payment_status' => 'due'));
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    //--------------------------- Printer Function ------------------------------//
    public function getAllPrinterFields() {
        $q = $this->db->get_where('printer_bill_fields', array('is_deleted' => 0));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPrinterByID($id) {
        $q = $this->db->get_where('printer_bill', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getPrinterFieldByID($id) {
        $q = $this->db->get_where('printer_bill_fields', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllPrinter() {
        $q = $this->db->get_where('printer_bill', array('is_deleted' => 0));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function addPrinter($data = array()) {
        if ($this->db->insert('printer_bill', $data)) {
            $cid = $this->db->insert_id();
            return $cid;
        }
        return false;
    }

    public function updatePrinter($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('printer_bill', $data)) {
            return $id;
        }
        return false;
    }

    public function defaultPrinterOption($id) {
        if ((int) $id > 0):
            $printer = $this->getPrinterByID($id);

            if (!$printer):
                return false;
            endif;
            $optionArr = array();
            $id_str = $printer->column_id_str;
            $id_arr = explode(',', $id_str);
            foreach ($id_arr as $id) {
                $obj = $this->getPrinterFieldByID($id);
                if ($obj):
                    $optionArr[$id] = $obj;
                endif;
            }
            $printer->optionDetails = $optionArr;
            return $printer;
        endif;
    }

    public function getAllContactGroup($GID = NULL) {

        $q = $this->db->get_where('contact_group');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllContactGroupByID($id) {
        $q = $this->db->get_where('contact_group', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllContactGroupMember($GID) {
        $arr = array();
        if (!empty($GID)):
            $arr['group_id'] = $GID;
        endif;
        $q = $this->db->get_where('contact_group_member', $arr);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllContactGroupMemberDetails($GID) {
        $arr = array();
        if (!empty($GID)):
            $arr['group_id'] = $GID;
        endif;
        $this->db->select('C.name,C.email,C.phone,C.id');
        $this->db->from('contact_group_member GM');
        $this->db->join('contact_group G', 'GM.group_id =  G.id', 'left');
        $this->db->join('companies C', 'GM.customer_id =  C.id', 'left');
        $this->db->where('G.id', $GID);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getContactGroupMemberCount($id) {
        $this->db->select('count(*) as cnt');
        $q = $this->db->get_where('contact_group_member', array('group_id' => $id), 1);
        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->cnt;
        }
        return FALSE;
    }

    public function getBirthDayTemplate($type) {
        $q = $this->db->get_where('contact_template', array('is_default' => '1', 'event_type' => 1, 'template_type' => $type), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAnniversaryTemplate($type) {
        $q = $this->db->get_where('contact_template', array('is_default' => '1', 'event_type' => 2, 'template_type' => $type), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getContactTemplateByID($id) {
        $q = $this->db->get_where('contact_template', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllContactTemplate($type = NULL, $isDefault = 0) {
        $typeArr = array();
        $typeArr = in_array((int) $type, array(1, 2, 3)) ? $typeArr['template_type'] = $type : NULL;
        $typeArr['is_default'] = $isDefault;
        $q = $this->db->get_where('contact_template', $typeArr);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    //---------------------Add Contact Group  --------------------//
    public function addContactGroup($data = array()) {
        if ($this->db->insert('contact_group', $data)) {
            $cid = $this->db->insert_id();

            return $cid;
        }
        return false;
    }

    //---------------------Update Contact Group  --------------------//
    public function updateContactGroup($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('contact_group', $data)) {
            return true;
        }
        return false;
    }

    //---------------------Delete Contact Group --------------------//
    public function deleteContactGroup($id) {
        if ($this->db->delete('contact_group', array('id' => $id))) {
            $this->deleteContactGroupMember($id);
            return true;
        }
        return FALSE;
    }

    //---------------------Add Contact Group Member--------------------//
    public function addContactGroupMember($data = array()) {
        if ($this->db->insert('contact_group_member', $data)) {
            $cid = $this->db->insert_id();
            return $cid;
        }
        return false;
    }

    //---------------------Delete Contact Group Member--------------------//
    public function deleteContactGroupMember($group_id, $customer_id = NULL) {

        $whereArr = array('group_id' => $group_id);
        if (!empty((int) $customer_id)):
            $whereArr['customer_id'] = $member_id;
        endif;
        if ($this->db->delete('contact_group_member', $whereArr)) {
            return true;
        }
        return FALSE;
    }

    //---------------------Add Contact Template --------------------//
    public function addContactTemplate($data = array()) {
        if ($this->db->insert('contact_template', $data)) {
            $cid = $this->db->insert_id();

            return $cid;
        }
        return false;
    }

    //---------------------Update Contact Template--------------------//
    public function updateContactTemplate($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('contact_template', $data)) {
            return true;
        }
        return false;
    }

    //---------------------Delete Contact Template --------------------//
    public function deleteContactTemplate($id) {
        if ($this->db->delete('contact_template', array('id' => $id))) {
            $this->deleteContactGroupMember($group_id);
            return true;
        }
        return FALSE;
    }

    public function customerName($param) {
        $where = array();
        $phone = isset($param['phone']) && !empty($param['phone']) ? $param['phone'] : NULL;
        $email = isset($param['email']) && !empty($param['email']) ? $param['email'] : NULL;
        if (empty($phone) && empty($email)):
            return false;
        endif;

        if ($phone):
            $where['phone'] = $phone;
        endif;

        if ($email):
            $where['email'] = $email;
        endif;

        $q = $this->db->get_where('companies', $where, 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    //--------------------- GST --------------------//	
    public function getTaxAttr() {
        $q = $this->db->get('tax_attr');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function tax_rate_js() {
        $url = $this->data['assets'] . 'js/tax_rate.js';

        return '<script type="text/javascript" src="' . $url . '"></script>';
    }

    public function add_tax_attr_amount($data) {
        if ($this->db->insert('sales_items_tax', $data)):
            $sale_id = $this->db->insert_id();
            return $sale_id;
        else:
            return false;
        endif;
    }

    public function add_tax_attr_amount_purchase($data) {
        if ($this->db->insert('purchase_items_tax', $data)):
            $purchase_items_tax = $this->db->insert_id();
            return $purchase_items_tax;
        else:
            return false;
        endif;
    }

    public function taxAttrPercentageBySaleTaxId($taxCode, $taxID, $sale_id) {
        $this->db->select("it.attr_per as percentage");
        $this->db->where_in('it.sale_id', array((int) $sale_id));
        $this->db->where('i.tax_rate_id', $taxID);
        $this->db->where('it.attr_code', $taxCode);
        $this->db->from('sales_items_tax it');
        $this->db->join('sale_items i', 'it.item_id=i.id', 'left');
        $this->db->group_by('it.attr_code');
        $q = $this->db->get();

        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->percentage;
        }
        return FALSE;
    }

    public function taxAttrPercentageByPurchaseTaxId($taxCode, $taxID, $purchase_id) {
        $this->db->select("it.attr_per as percentage");
        $this->db->where_in('it.purchase_id', array((int) $purchase_id));
        $this->db->where('i.tax_rate_id', $taxID);
        $this->db->where('it.attr_code', $taxCode);
        $this->db->from('purchase_items_tax it');
        $this->db->join('purchase_items i', 'it.item_id=i.id', 'left');
        $this->db->group_by('it.attr_code');
        $q = $this->db->get();

        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->percentage;
        }
        return FALSE;
    }

    public function isGstSale($id) {
        $q = $this->db->get_where('sales_items_tax', array('sale_id' => $id), 1);
        // echo $this->db->last_query();
        if ($q->num_rows() > 0) {
            return $q->num_rows();
        }
        return FALSE;
    }

    public function isGstPurchase($id) {
        $q = $this->db->get_where('purchase_items_tax', array('purchase_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->num_rows();
        }
        return FALSE;
    }

    public function add_tax_attr_amount_quote($data) {
        if ($this->db->insert('quote_items_tax', $data)):
            $quote_items_tax = $this->db->insert_id();
            return $quote_items_tax;
        else:
            return false;
        endif;
    }

    public function taxAttrPercentageByQuoteTaxId($taxCode, $taxID, $quote_id) {
        $this->db->select("it.attr_per as percentage");
        $this->db->where_in('it.quote_id', array((int) $quote_id));
        $this->db->where('i.tax_rate_id', $taxID);
        $this->db->where('it.attr_code', $taxCode);
        $this->db->from('quote_items_tax it');
        $this->db->join('quote_items i', 'it.item_id=i.id', 'left');
        $this->db->group_by('it.attr_code');
        $q = $this->db->get();

        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->percentage;
        }
        return FALSE;
    }

    /* 24-10-2019  total amount show */

    public function taxAttrAmtByQuoteTaxId($taxCode, $taxID, $quote_id) {
        $this->db->select("ROUND(it.tax_amount,2) as amt");
        $this->db->where_in('it.quote_id', array((int) $quote_id));
        $this->db->where('i.tax_rate_id', $taxID);
        $this->db->where('it.attr_code', $taxCode);
        $this->db->from('quote_items_tax it');
        $this->db->join('quote_items i', 'it.item_id=i.id', 'left');
        $this->db->group_by('it.attr_code');
        $q = $this->db->get();

        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->amt;
        }
        return FALSE;
    }

    /**/

    public function isGstQuote($id) {
        $q = $this->db->get_where('quote_items_tax', array('quote_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->num_rows();
        }
        return FALSE;
    }

    public function resetExpirePromos() {

        $expirePromos = $this->geteExpirePromos();
        if (!$expirePromos):
            return false;
        elseif (!is_array($expirePromos)):
            return false;
        endif;
        $pro = 0;
        foreach ($expirePromos as $prd) {
            $this->db->update('products', array('promotion' => 0), array('id' => $prd->id, 'promotion' => 1));
            $pro++;
        }
        return true;
    }

    private function geteExpirePromos() {

        $ci = get_instance();
        $config = $ci->config;
        $IST_OFFSET = isset($config->config['IST_OFFSET']) ? $config->config['IST_OFFSET'] : 0;
        $datetime = date("Y-m-d H:i:s", time() + $IST_OFFSET);
        $this->db->select('id');
        $q = $this->db->get_where('products', array('promotion' => 1, 'end_date !=' => NULL, 'end_date <=' => $datetime));


        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllStates() {
        $q = $this->db->get('state_master');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getStateFromStateCode($code) {
        $q = $this->db->get_where('state_master', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->name;
        }
        return FALSE;
    }

    public function getStateCodeFromName($name) {

        if (empty($name)) {
            return NULL;
        }

        $q = $this->db->get_where('state_master', array('name' => $name), 1);
        if ($q->num_rows() > 0) {
            $res = $q->row();
            return $res->code;
        }
        return FALSE;
    }

    public function addCronMember($data) {
        $dt = date("Y-m-d H:i:s");
        if (count($data) > 0) {
            $this->db->delete('cron_user', array('1' => '1'));
            foreach ($data as $key => $dataVal) {

                if ($this->db->insert('cron_user', array('customer_id' => $dataVal['customer_id'], 'postdate' => $dt))) {
                    $id = $this->db->insert_id();
                }
            }
            return true;
        }
        return FALSE;
    }

    public function getAllCronCustomer() {
        $this->db->select("customer_id");
        $q = $this->db->get_where('cron_user');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row->customer_id;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllDivision() {
        $q = $this->db->get("division");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getDivisionByID($id) {
        $q = $this->db->get_where('division', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllRestaurantTables() {
        $q = $this->db->get('restaurant_tables');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getTableData($tablename) {

        $q = $this->db->get($tablename);

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getTabledataCondition() { //Note : 0. Table name, 1. get Field, 2. Condition  3. order Field, 4. Order Conditon 
        $getdata = func_get_args();

        $this->db->select($getdata[1]);
        if ($getdata[2]) {
            $this->db->where($getdata[2]);
        }
        if ($getdata[3] && $getdata[4]) {
            $this->db->order_by($getdata[3], $getdata[4]);
        }
        $q = $this->db->get($getdata[0]);
        if ($q->num_rows() > 0) {

            return $q->result();
        }
        return FALSE;
    }

    public function getOfferCategories() {
        $q = $this->db->select('id,offer_keyword,offer_category')->where(['is_active' => 1, 'is_delete' => 0])->get('offers_categories');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    // Get Themes 17-04-19
    public function getpostheme() {
        return $this->db->select('theme_name,theme_label')->where(['is_active' => '1', 'is_delete' => '0'])->order_by('theme_name', 'ASC')->get('sma_themes')->result();
    }

    // End Get Themes
    public function getCompanyDetailsByGroupID($id) { // call this function pos/index
        $q = $this->db->get_where('companies', array('group_id' => $id));
        return $q->result_array();
    }

    /* 8-11-2019 */

    public function customerDepositAmt($customer_id) {
        $customer = $this->getCompanyByID($customer_id);
        return $customer->deposit_amount;
    }
    /*20-02-2020 for deleted data*/
	public function getTableDatas($TableName, $columnId, $DeletedId) {
		$q = $this->db->get_where($TableName, array($columnId => $DeletedId));
		return $q->result_array();
	}
	public function insertTableData($Data) {
		if ($this->db->insert('deleted_data', $Data)){
			return true;
		}
		return false;
	}
	public function deleteTableDataById($TableName, $ColumnId, $id) {
        if ($this->db->delete('deleted_data', array('table_name' => $TableName, $ColumnId => $id))) {
            return true;
        }
        return FALSE;
    }
	/*end 20-02-2020 for deleted data*/
	//03-04-2020 eshop order
	public function order_costing($items) {
        $citems = array();
        foreach ($items as $item) {
            $pr = $this->getProductByID($item['product_id']);
            if ($pr->type == 'standard') {
                if (isset($citems['p' . $item['product_id'] . 'o' . $item['option_id']])) {
                    $citems['p' . $item['product_id'] . 'o' . $item['option_id']]['aquantity'] += $item['quantity'];
                } else {
                    $citems['p' . $item['product_id'] . 'o' . $item['option_id']] = $item;
                    $citems['p' . $item['product_id'] . 'o' . $item['option_id']]['aquantity'] = $item['quantity'];
                }
            } elseif ($pr->type == 'combo') {
                $combo_items = $this->getProductComboItems($item['product_id'], $item['warehouse_id']);
                foreach ($combo_items as $combo_item) {
                    if ($combo_item->type == 'standard') {
                        if (isset($citems['p' . $combo_item->id . 'o' . $item['option_id']])) {
                            $citems['p' . $combo_item->id . 'o' . $item['option_id']]['aquantity'] += ($combo_item->qty * $item['quantity']);
                        } else {
                            $cpr = $this->getProductByID($combo_item->id);
                            if ($cpr->tax_rate) {
                                $cpr_tax = $this->getTaxRateByID($cpr->tax_rate);
                                if ($cpr->tax_method) {
                                    $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $cpr_tax->rate) / (100 + $cpr_tax->rate));
                                    $net_unit_price = $combo_item->unit_price - $item_tax;
                                    $unit_price = $combo_item->unit_price;
                                } else {
                                    $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $cpr_tax->rate) / 100);
                                    $net_unit_price = $combo_item->unit_price;
                                    $unit_price = $combo_item->unit_price + $item_tax;
                                }
                            } else {
                                $net_unit_price = $combo_item->unit_price;
                                $unit_price = $combo_item->unit_price;
                            }
                            $cproduct = array('product_id' => $combo_item->id, 'product_name' => $cpr->name, 'product_type' => $combo_item->type, 'quantity' => ($combo_item->qty * $item['unit_quantity']), 'net_unit_price' => $net_unit_price, 'unit_price' => $unit_price, 'warehouse_id' => $item['warehouse_id'], 'item_tax' => $item_tax, 'tax_rate_id' => $cpr->tax_rate, 'tax' => ($cpr_tax->type == 1 ? $cpr_tax->rate . '%' : $cpr_tax->rate), 'option_id' => NULL);
                            $citems['p' . $combo_item->id . 'o' . $item['option_id']] = $cproduct;
                            $citems['p' . $combo_item->id . 'o' . $item['option_id']]['aquantity'] = ($combo_item->qty * $item['quantity']);
                        }
                    }
                }
            }
        }
        // $this->sma->print_arrays($combo_items, $citems);
        $cost = array();
        foreach ($citems as $item) {
            if($item['option_id']!='')
              $item['aquantity'] = $citems['p' . $item['product_id'] . 'o' . $item['option_id']]['aquantity'];
            $cost[] = $this->order_item_costing($item, TRUE);
        }
        return $cost;
    }
	public function order_item_costing($item, $pi = NULL) {
        $item_quantity = $pi ? $item['aquantity'] : $item['quantity'];
        if (!isset($item['option_id']) || empty($item['option_id']) || $item['option_id'] == 'null') {
            $item['option_id'] = NULL;
        }
        
        if ($this->Settings->accounting_method != 2 && !$this->Settings->overselling) {

            if ($this->getProductByID($item['product_id'])) {
                if ($item['product_type'] == 'standard') {
                    $unit = $this->getUnitByID($item['product_unit_id']);
                    $item['net_unit_price'] = $this->convertToBase($unit, $item['net_unit_price']);
                    $item['unit_price'] = $this->convertToBase($unit, $item['unit_price']);
                    $cost = $this->calculateOrderCost($item['product_id'], $item['warehouse_id'], $item['net_unit_price'], $item['unit_price'], $item['quantity'], $item['product_name'], $item['option_id'], $item_quantity);
                } elseif ($item['product_type'] == 'combo') {
                    $combo_items = $this->getProductComboItems($item['product_id'], $item['warehouse_id']);
                    foreach ($combo_items as $combo_item) {
                        $pr = $this->getProductByCode($combo_item->code);
                        if ($pr->tax_rate) {
                            $pr_tax = $this->getTaxRateByID($pr->tax_rate);
                            if ($pr->tax_method) {
                                $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $pr_tax->rate) / (100 + $pr_tax->rate));
                                $net_unit_price = $combo_item->unit_price - $item_tax;
                                $unit_price = $combo_item->unit_price;
                            } else {
                                $item_tax = $this->sma->formatDecimal((($combo_item->unit_price) * $pr_tax->rate) / 100);
                                $net_unit_price = $combo_item->unit_price;
                                $unit_price = $combo_item->unit_price + $item_tax;
                            }
                        } else {
                            $net_unit_price = $combo_item->unit_price;
                            $unit_price = $combo_item->unit_price;
                        }
                        if ($pr->type == 'standard') {
                            $cost[] = $this->calculateOrderCost($pr->id, $item['warehouse_id'], $net_unit_price, $unit_price, ($combo_item->qty * $item['quantity']), $pr->name, NULL, $item_quantity);
                        } else {
                            $cost[] = array(array('date' => date('Y-m-d'), 'product_id' => $pr->id, 'order_item_id' => 'order_items.id', 'purchase_item_id' => NULL, 'quantity' => ($combo_item->qty * $item['quantity']), 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $combo_item->unit_price, 'sale_unit_price' => $combo_item->unit_price, 'quantity_balance' => NULL, 'inventory' => NULL));
                        }
                    }
                } else {
                    $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'order_item_id' => 'order_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
                }
            } elseif ($item['product_type'] == 'manual') {
                $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'order_item_id' => 'order_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
            }
        } else {

            if ($this->getProductByID($item['product_id'])) {
                if ($item['product_type'] == 'standard') {
                    $cost = $this->calculateOrderAVCost($item['product_id'], $item['warehouse_id'], $item['net_unit_price'], $item['unit_price'], $item['quantity'], $item['product_name'], $item['option_id'], $item_quantity);
                } elseif ($item['product_type'] == 'combo') {
                    $combo_items = $this->getProductComboItems($item['product_id'], $item['warehouse_id']);
                    foreach ($combo_items as $combo_item) {
                        $cost = $this->calculateOrderAVCost($combo_item->id, $item['warehouse_id'], $item['net_unit_price'], $item['unit_price'], ($combo_item->qty * $item['quantity']), $item['product_name'], $item['option_id'], $item_quantity);
                    }
                } else {
                    $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'order_item_id' => 'order_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
                }
            } elseif ($item['product_type'] == 'manual') {
                $cost = array(array('date' => date('Y-m-d'), 'product_id' => $item['product_id'], 'order_item_id' => 'order_items.id', 'purchase_item_id' => NULL, 'quantity' => $item['quantity'], 'purchase_net_unit_cost' => 0, 'purchase_unit_cost' => 0, 'sale_net_unit_price' => $item['net_unit_price'], 'sale_unit_price' => $item['unit_price'], 'quantity_balance' => NULL, 'inventory' => NULL));
            }
        }
        return $cost;
    }
public function calculateOrderCost($product_id, $warehouse_id, $net_unit_price, $unit_price, $quantity, $product_name, $option_id, $item_quantity) {
        $pis = $this->getPurchasedItems($product_id, $warehouse_id, $option_id);
        $real_item_qty = $quantity;
        $quantity = $item_quantity;
        $balance_qty = $quantity;
        foreach ($pis as $pi) {
            $cost_row = NULL;
            if (!empty($pi) && $balance_qty <= $quantity && $quantity > 0) {
                $purchase_unit_cost = $pi->unit_cost ? $pi->unit_cost : ($pi->net_unit_cost + ($pi->item_tax / $pi->quantity));
                if ($pi->quantity_balance >= $quantity && $quantity > 0) {
                    $balance_qty = $pi->quantity_balance - $quantity;
                    $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'order_item_id' => 'order_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $real_item_qty, 'purchase_net_unit_cost' => $pi->net_unit_cost, 'purchase_unit_cost' => $purchase_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => $balance_qty, 'inventory' => 1, 'option_id' => $option_id);
                    $quantity = 0;
                } elseif ($quantity > 0) {
                    $quantity = $quantity - $pi->quantity_balance;
                    $balance_qty = $quantity;
                    $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'order_item_id' => 'order_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $pi->quantity_balance, 'purchase_net_unit_cost' => $pi->net_unit_cost, 'purchase_unit_cost' => $purchase_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => 0, 'inventory' => 1, 'option_id' => $option_id);
                }
            }
            $cost[] = $cost_row;
            if ($quantity == 0) {
                break;
            }
        }
        if ($quantity > 0) {
            $this->session->set_flashdata('error', sprintf(lang("quantity_out_of_stock_for_%s"), ($pi->product_name ? $pi->product_name : $product_name)));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        return $cost;
    }
public function calculateOrderAVCost($product_id, $warehouse_id, $net_unit_price, $unit_price, $quantity, $product_name, $option_id, $item_quantity) {
        $real_item_qty = $quantity;
        $wp_details = $this->getWarehouseProduct($warehouse_id, $product_id);
        $product_avg_cost = $this->db->select('cost')->where('id', $product_id)->get('products')->row();

        if ($pis = $this->getPurchasedItems($product_id, $warehouse_id, $option_id)) {
            $cost_row = array();
            $quantity = $item_quantity;
            $balance_qty = $quantity;
            $avg_net_unit_cost = $wp_details->avg_cost;
            $avg_unit_cost = $wp_details->avg_cost;
            foreach ($pis as $pi) {
                if (!empty($pi) && $pi->quantity > 0 && $balance_qty <= $quantity && $quantity > 0) {
                    if ($pi->quantity_balance >= $quantity && $quantity > 0) {
                        $balance_qty = $pi->quantity_balance - $quantity;
                        $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'order_item_id' => 'order_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $real_item_qty, 'purchase_net_unit_cost' => $avg_net_unit_cost, 'purchase_unit_cost' => $avg_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => $balance_qty, 'inventory' => 1, 'option_id' => $option_id);
                        $quantity = 0;
                    } elseif ($quantity > 0) {
                        $quantity = $quantity - $pi->quantity_balance;
                        $balance_qty = $quantity;
                        $cost_row = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'order_item_id' => 'order_items.id', 'purchase_item_id' => $pi->id, 'quantity' => $pi->quantity_balance, 'purchase_net_unit_cost' => $avg_net_unit_cost, 'purchase_unit_cost' => $avg_unit_cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => 0, 'inventory' => 1, 'option_id' => $option_id);
                    }
                }
                if (empty($cost_row)) {
                    break;
                }
                $cost[] = $cost_row;
                if ($quantity == 0) {
                    break;
                }
            }
        }
        if ($quantity > 0 && !$this->Settings->overselling) {
            $this->session->set_flashdata('error', sprintf(lang("quantity_out_of_stock_for_%s"), ($pi->product_name ? $pi->product_name : $product_name)));
            redirect($_SERVER["HTTP_REFERER"]);
        } elseif ($quantity > 0) {
            $cost[] = array('date' => date('Y-m-d'), 'product_id' => $product_id, 'order_item_id' => 'order_items.id', 'purchase_item_id' => NULL, 'quantity' => $real_item_qty, 'purchase_net_unit_cost' => is_array($wp_details) ? $wp_details->avg_cost : $product_avg_cost->cost, 'purchase_unit_cost' => is_array($wp_details) ? $wp_details->avg_cost : $product_avg_cost->cost, 'sale_net_unit_price' => $net_unit_price, 'sale_unit_price' => $unit_price, 'quantity_balance' => NULL, 'overselling' => 1, 'inventory' => 1);
            $cost[] = array('pi_overselling' => 1, 'product_id' => $product_id, 'quantity_balance' => (0 - $quantity), 'warehouse_id' => $warehouse_id, 'option_id' => $option_id);
        }
        return $cost;
    }

/*15-5-2020 New CSI TAX show Function*/
public function getSItemsTaxes($saleId,$taxRate) {
       
        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sale_items') . "  WHERE `sale_id` =  '$saleId' AND  gst_rate = '$taxRate' GROUP BY `gst_rate` ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function getTotalItemsTaxes($saleId) {
       
        $query = "SELECT sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sale_items') . "  WHERE `sale_id` =  '$saleId' ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPurchaseItemsTaxes($purchaseId,$taxRate) {
       
        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('purchase_items') . "  WHERE `purchase_id` =  '$purchaseId' AND  gst_rate = '$taxRate' GROUP BY `gst_rate` ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function getPurchaseTotalItemsTaxes($purchaseId) {
       
        $query = "SELECT sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('purchase_items') . "  WHERE `purchase_id` =  '$purchaseId' ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }  
    
    public function getQuoteItemsTaxes($quoteId,$taxRate) {
       
        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('quote_items') . "  WHERE `quote_id` =  '$quoteId' AND  gst_rate = '$taxRate' GROUP BY `gst_rate` ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function getQuoteTotalItemsTaxes($quoteId) {
       
        $query = "SELECT sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('quote_items') . "  WHERE `quote_id` =  '$quoteId' ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getReturnSaleTaxes($saleId) {
       
        $query = "SELECT sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sales') . "  WHERE `sale_id` =  '$saleId' ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getSaleTaxes($saleId) {
       
        $query = "SELECT sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sales') . "  WHERE `id` =  '$saleId' ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
public function getOItemsTaxes($saleId,$taxRate) {
       
        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('order_items') . "  WHERE `sale_id` =  '$saleId' AND  gst_rate = '$taxRate' GROUP BY `gst_rate` ";
            
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
public function getTotalOrderItemsTaxes($saleId) {
       
        $query = "SELECT sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('order_items') . "  WHERE `sale_id` =  '$saleId' ";
            
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
}
