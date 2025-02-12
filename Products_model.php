<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Products_model extends CI_Model {

    public function __construct() {
        parent::__construct();
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

    public function getCategoryProducts($category_id) {
        $q = $this->db->get_where('products', array('category_id' => $category_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getSubCategoryProducts($subcategory_id) {
        $q = $this->db->get_where('products', array('subcategory_id' => $subcategory_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductOptions($pid) {
        $q = $this->db->get_where('product_variants', ['product_id' => $pid]);
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getProductVariant($pid) {
        $q = $this->db->get_where('product_variants', ['product_id' => $pid]);
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                // $data[$row->id] = $row;
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductOptionsWithWH($pid, $GroupId='')
    {
		if($GroupId!='')
			$this->db->where(array('product_variants.group_id' => $GroupId));
        $this->db->select($this->db->dbprefix('product_variants') . '.*, ' . $this->db->dbprefix('warehouses') . '.name as wh_name, ' . $this->db->dbprefix('warehouses') . '.id as warehouse_id, ' . $this->db->dbprefix('warehouses_products_variants') . '.quantity as wh_qty,'. $this->db->dbprefix('warehouses_products_variants') . '.avg_cost as avg_cost')
            ->join('warehouses_products_variants', 'warehouses_products_variants.option_id=product_variants.id', 'left')
            ->join('warehouses', 'warehouses.id=warehouses_products_variants.warehouse_id', 'left')
            ->group_by(array('' . $this->db->dbprefix('product_variants') . '.id', '' . $this->db->dbprefix('warehouses_products_variants') . '.warehouse_id'))
            ->order_by('product_variants.id');
        $q = $this->db->get_where('product_variants', array('product_variants.product_id' => $pid, 'warehouses_products_variants.quantity !=' => NULL));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function getProductComboItems($pid) {
        $this->db->select($this->db->dbprefix('products') . '.id as id, ' . $this->db->dbprefix('products') . '.code as code, ' . $this->db->dbprefix('combo_items') . '.quantity as qty, ' . $this->db->dbprefix('products') . '.name as name, ' . $this->db->dbprefix('combo_items') . '.unit_price as price')->join('products', 'products.code=combo_items.item_code', 'left')->group_by('combo_items.id');
        $q = $this->db->get_where('combo_items', array('product_id' => $pid));
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

    public function getProductWithCategory($id) {
        $this->db->select($this->db->dbprefix('products') . '.*, ' . $this->db->dbprefix('categories') . '.name as category, ' . $this->db->dbprefix('brands') . '.name as brannd_name')
                ->join('categories', 'categories.id=products.category_id', 'left')
                ->join('sma_brands', 'sma_brands.id=products.brand', 'left');
        $q = $this->db->get_where('products', array('products.id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function has_purchase($product_id, $warehouse_id = NULL) {
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        $q = $this->db->get_where('purchase_items', array('product_id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return TRUE;
        }
        return FALSE;
    }

    public function getProductDetails($id) {
        $this->db->select($this->db->dbprefix('products') . '.code, ' . $this->db->dbprefix('products') . '.name, ' . $this->db->dbprefix('categories') . '.code as category_code, cost, price, quantity, alert_quantity')
                ->join('categories', 'categories.id=products.category_id', 'left');
        $q = $this->db->get_where('products', array('products.id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductDetail($id) {
        $this->db->select($this->db->dbprefix('products') . '.*, ' . $this->db->dbprefix('tax_rates') . '.name as tax_rate_name, ' . $this->db->dbprefix('tax_rates') . '.code as tax_rate_code, c.code as category_code, sc.code as subcategory_code', FALSE)
                ->join('tax_rates', 'tax_rates.id=products.tax_rate', 'left')
                ->join('categories c', 'c.id=products.category_id', 'left')
                ->join('categories sc', 'sc.id=products.subcategory_id', 'left');
        $q = $this->db->get_where('products', array('products.id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getSubCategories($parent_id) {
        $this->db->select('id as id, name as text')
                ->where('parent_id', $parent_id)->order_by('name');
        $q = $this->db->get("categories");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCategoryTaxrate($category_id) {

        $this->db->select('id,tax_rate');

        $this->db->where('id', $category_id);

        $q = $this->db->get("categories");

        if ($q->num_rows() > 0) {
            return $q->result();
        }
        return FALSE;
    }

    public function getCategoryName($category_id) {

        $this->db->select('id,name,code');

        if ($category_id) {
            $ids = explode(',', $category_id);
            $this->db->where_in('id', $ids);
        }

        $this->db->order_by('name');

        $q = $this->db->get("categories");

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[$row->id]['id'] = $row->id;
                $data[$row->id]['name'] = $row->name;
                $data[$row->id]['code'] = $row->code;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCategoryIdByName($category_name) {

        $this->db->select('id,name,code');

        if ($category_name) {
            $categories = explode(',', $category_name);

            $this->db->where_in('name', $categories);
        }

        $this->db->order_by('name');

        $q = $this->db->get("categories");

        if ($q->num_rows() > 0) {
            return $q->result();
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

    public function getAllWarehousesWithPQ($product_id, $warehouse_ids) {
       
        $this->db->select('' . $this->db->dbprefix('warehouses') . '.*, ' . $this->db->dbprefix('warehouses_products') . '.quantity, ' . $this->db->dbprefix('warehouses_products') . '.rack')
                ->join('warehouses_products', 'warehouses_products.warehouse_id=warehouses.id', 'left')
                ->where('warehouses_products.product_id', $product_id)
                ->group_by('warehouses.id');
                if (!empty($warehouse_ids) && is_array($warehouse_ids)) {
                    $this->db->where_in('warehouses_products.warehouse_id', $warehouse_ids);
                }
        $q = $this->db->get('warehouses');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductPhotos($id) {
        $q = $this->db->get_where("product_photos", array('product_id' => $id));
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

    public function getProductStorageType($product_id) {
        $q = $this->db->select('storage_type')->get_where('products', array('id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row()->storage_type;
        }
        return FALSE;
    }

    public function addProduct($data, $items, $warehouse_qty, $product_attributes, $photos, $postype_data = NULL) {

        if ($this->db->insert('products', $data)) {

            $product_id = $this->db->insert_id();
            $DatalogArr = array('product_data' => $data, 'items' => $items, 'warehouse_qty' => $warehouse_qty, 'product_attributes' => $product_attributes, 'photos' => $photos, 'postype_data' => $postype_data);
            $DataLog = array(
                'action_type' => 'Add',
                'product_id' => $product_id,
                'quantity' => '',
                'action_reff_id' => $product_id,
                'action_affected_data' => json_encode($DatalogArr),
                'action_comment' => 'Add products',
            );
            $this->sma->setUserActionLog($DataLog);
           // Urbanpiper

           if ($data['up_items'] == '1' && $postype_data['pos_type'] == 'restaurant' || $data['up_items'] == '1' && $postype_data['pos_type'] == 'bakery') {

            $postype_data['product_id'] = $product_id;
            unset($postype_data['pos_type']);
            $postype_data_ep = array(
                'product_id' => $postype_data['product_id'],
                'product_code' => $postype_data['product_code'],
                'price' => $postype_data['price'],
                'food_type_id' => $postype_data['food_type_id'],
                'available' => $postype_data['available'],
                'sold_at_store' => $postype_data['sold_at_store'],
                'recommended' => $postype_data['recommended'],
                'plat_zomato' => $postype_data['plat_zomato'],
                'plat_swiggy' => $postype_data['plat_swiggy'],
                'plat_foodpanda' => $postype_data['plat_foodpanda'],
                'plat_ubereats' => $postype_data['plat_ubereats'],
                //'default_tag' => $postype_data['default_tag'],
                'manage_stock' => $postype_data['manage_stock'],  
            );
       
                $this->db->insert('up_products', $postype_data_ep); 
        }
        // End Urbanpiper
            if ($items) {
                foreach ($items as $item) {
                    $item['product_id'] = $product_id;
                    $this->db->insert('combo_items', $item);
                }
            }

            $warehouses = $this->site->getAllWarehouses();
            if ($data['type'] == 'combo' || $data['type'] == 'Bundle' || $data['type'] == 'service') {
                foreach ($warehouses as $warehouse) {
                    $this->db->insert('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse->id, 'quantity' => 0));
                }
            }

            $tax_rate = $this->site->getTaxRateByID($data['tax_rate']);
            /*
              if ($warehouse_qty && !empty($warehouse_qty)) {
              foreach ($warehouse_qty as $wh_qty) {
              if (isset($wh_qty['quantity']) && !empty($wh_qty['quantity'])) {
              $this->db->insert('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $wh_qty['warehouse_id'], 'quantity' => $wh_qty['quantity'], 'rack' => $wh_qty['rack'], 'avg_cost' => $data['cost']));

              if (!$product_attributes) {
              $tax_rate_id = $tax_rate ? $tax_rate->id : NULL;
              $tax = $tax_rate ? (($tax_rate->type == 1) ? $tax_rate->rate . "%" : $tax_rate->rate) : NULL;
              $unit_cost = $data['cost'];
              if ($tax_rate) {
              if ($tax_rate->type == 1 && $tax_rate->rate != 0) {
              if ($data['tax_method'] == '0') {
              $pr_tax_val = ($data['cost'] * $tax_rate->rate) / (100 + $tax_rate->rate);
              $net_item_cost = $data['cost'] - $pr_tax_val;
              $item_tax = $pr_tax_val * $wh_qty['quantity'];
              } else {
              $net_item_cost = $data['cost'];
              $pr_tax_val = ($data['cost'] * $tax_rate->rate) / 100;
              $unit_cost = $data['cost'] + $pr_tax_val;
              $item_tax = $pr_tax_val * $wh_qty['quantity'];
              }
              } else {
              $net_item_cost = $data['cost'];
              $item_tax = $tax_rate->rate;
              }
              } else {
              $net_item_cost = $data['cost'];
              $item_tax = 0;
              }

              $subtotal = (($net_item_cost * $wh_qty['quantity']) + $item_tax);

              $item = array(
              'product_id' => $product_id,
              'product_code' => $data['code'],
              'product_name' => $data['name'],
              'net_unit_cost' => $net_item_cost,
              'unit_cost' => $unit_cost,
              'real_unit_cost' => $unit_cost,
              'quantity' => $wh_qty['quantity'],
              'quantity_balance' => $wh_qty['quantity'],
              'item_tax' => $item_tax,
              'tax_rate_id' => $tax_rate_id,
              'tax' => $tax,
              'subtotal' => $subtotal,
              'warehouse_id' => $wh_qty['warehouse_id'],
              'date' => date('Y-m-d'),
              'status' => 'received',
              );
              $this->db->insert('purchase_items', $item);
              $this->site->syncProductQty($product_id, $wh_qty['warehouse_id']);
              }
              }
              }
              } */

            if ($product_attributes) {
                foreach ($product_attributes as $pr_attr) {
                    $pr_attr_details = $this->getPrductVariantByPIDandName($product_id, $pr_attr['name']);

                    $pr_attr['product_id'] = $product_id;
                    $variant_warehouse_id = $pr_attr['warehouse_id'];
                    unset($pr_attr['warehouse_id']);
                    if ($pr_attr_details) {
                        $option_id = $pr_attr_details->id;
                    } else {
                        $this->db->insert('product_variants', $pr_attr);
                        $option_id = $this->db->insert_id();
                    }
                    /* if ($pr_attr['quantity']) {
                      if (!$this->getWarehouseProductVariant($variant_warehouse_id, $product_id, $option_id)) {
                      $this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $variant_warehouse_id, 'quantity' => $pr_attr['quantity']));
                      }
                      $tax_rate_id = $tax_rate ? $tax_rate->id : NULL;
                      $tax = $tax_rate ? (($tax_rate->type == 1) ? $tax_rate->rate . "%" : $tax_rate->rate) : NULL;
                      $unit_cost = $data['cost'];
                      if ($tax_rate) {
                      if ($tax_rate->type == 1 && $tax_rate->rate != 0) {
                      if ($data['tax_method'] == '0') {
                      $pr_tax_val = ($data['cost'] * $tax_rate->rate) / (100 + $tax_rate->rate);
                      $net_item_cost = $data['cost'] - $pr_tax_val;
                      $item_tax = $pr_tax_val * $pr_attr['quantity'];
                      } else {
                      $net_item_cost = $data['cost'];
                      $pr_tax_val = ($data['cost'] * $tax_rate->rate) / 100;
                      $unit_cost = $data['cost'] + $pr_tax_val;
                      $item_tax = $pr_tax_val * $pr_attr['quantity'];
                      }
                      } else {
                      $net_item_cost = $data['cost'];
                      $item_tax = $tax_rate->rate;
                      }
                      } else {
                      $net_item_cost = $data['cost'];
                      $item_tax = 0;
                      }

                      $subtotal = (($net_item_cost * $pr_attr['quantity']) + $item_tax);
                      $item = array(
                      'product_id' => $product_id,
                      'product_code' => $data['code'],
                      'product_name' => $data['name'],
                      'net_unit_cost' => $net_item_cost,
                      'unit_cost' => $unit_cost,
                      'quantity' => $pr_attr['quantity'],
                      'option_id' => $option_id,
                      'quantity_balance' => $pr_attr['quantity'],
                      'item_tax' => $item_tax,
                      'tax_rate_id' => $tax_rate_id,
                      'tax' => $tax,
                      'subtotal' => $subtotal,
                      'warehouse_id' => $variant_warehouse_id,
                      'date' => date('Y-m-d'),
                      'status' => 'received',
                      );
                      $this->db->insert('purchase_items', $item);
                      }

                      foreach ($warehouses as $warehouse) {
                      if (!$this->getWarehouseProductVariant($warehouse->id, $product_id, $option_id)) {
                      $this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse->id, 'quantity' => 0));
                      }
                      } */

                    // $this->site->syncVariantQty($option_id, $variant_warehouse_id);
                }
            }

            if ($photos) {
                foreach ($photos as $photo) {
                    $this->db->insert('product_photos', array('product_id' => $product_id, 'photo' => $photo));
                }
            }

            return true;
        }
        return false;
    }

    public function getPrductVariantByPIDandName($product_id, $name) {
        $q = $this->db->get_where('product_variants', array('product_id' => $product_id, 'name' => $name), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addAjaxProduct($data) {
        if ($this->db->insert('products', $data)) {
            $product_id = $this->db->insert_id();
            return $this->getProductByID($product_id);
        }
        return false;
    }

    public function add_products($products = array()) {
        if (!empty($products)) {
            foreach ($products as $product) {

                if (!empty($product['variants'])) {
                    $variants = explode('|', $product['variants']);
                }
                unset($product['variants']);

                if ($this->db->insert('products', $product)) {
                    $product_id = $this->db->insert_id();
                    if (is_array($variants)) {
                        foreach ($variants as $variant) {
                            if ($variant && trim($variant) != '') {
                                $vat = array('product_id' => $product_id, 'name' => trim($variant));
                                $this->db->insert('product_variants', $vat);
                            }
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

    public function add_import_csv_products($products = array()) {
        if (!empty($products)) {
            foreach ($products as $product) {
                $variants = explode('|', $product['variants']);
                unset($product['variants']);

                $warehouse_arr = array();
                $product_attributes_arr = array();

                if (isset($product['warehouse'])) {
                    if (!empty($product['warehouse'])) {
                        $warehouse_arr['warehouse_id'] = $product['warehouse'];
                        $warehouse_arr['avg_cost'] = $product['cost'];
                    }
                    unset($product['warehouse']);
                }

                if (isset($product['quantity'])) {
                    if (!empty($product['quantity']) && $product['quantity'] > 0) {
                        $warehouse_arr['quantity'] = $product['quantity'];
                    } else {
                        $warehouse_arr['quantity'] = 0;
                        $product['quantity'] = 0;
                    }
                }

                if (!isset($warehouse_arr['warehouse_id']) && isset($product['quantity'])) {
                    unset($product['quantity']);
                }

                if (isset($warehouse_arr['warehouse_id']) && !isset($product['quantity'])) {
                    $warehouse_arr['quantity'] = 0;
                    $product['quantity'] = 0;
                }
                $StoreRow = $this->db->select('id, ref_id')->where('store_add_urbanpiper', 1)->get('sma_up_stores')->row();
                $available = $product['available'];
                unset($product['available']);
                if ($this->db->insert('products', $product)) {
                    $product_id = $this->db->insert_id();

                    // Urbanpiper

                    if ($product['up_items'] == '1') {
                        $field = array(
                            'product_id' => $product_id,
                            'product_code' => $product['code'],
                            'price' => $product['up_price'],
                            'food_type_id' => $product['food_type_id'],
                        );
                        $this->db->insert('sma_up_products', $field);
                        $fieldPlatform = array(
                            'product_id' => $product_id,
                            'product_code' => $product['code'],
                            'available' => $available,
                            'up_store_id' => $StoreRow->id,
                            'up_store_ref_id' => $StoreRow->ref_id,
                        );
                        $this->db->insert('sma_up_products_platform', $fieldPlatform);
                    }

                    // End Urbanpiper

                    if (isset($warehouse_arr['warehouse_id'])) {
                        $warehouse_arr['product_id'] = $product_id;
                        if ($this->db->insert('warehouses_products', $warehouse_arr)) {
                            $warehouses_products_id = $this->db->insert_id();

                            $tax_details = $this->site->getTaxRateByID($product['tax_rate']);

                            if ($tax_details) {

                                $tax_rate_id = $tax_details ? $tax_details->id : NULL;
                                $tax = $tax_details ? (($tax_details->type == 1) ? $tax_details->rate . "%" : $tax_details->rate) : NULL;
                                $unit_cost = $product['cost'];
                                if ($tax_details) {
                                    if ($tax_details->type == 1 && $tax_details->rate != 0) {
                                        if ($product['tax_method'] == 0) {
                                            $pr_tax_val = ($product['cost'] * $tax_details->rate) / (100 + $tax_details->rate);
                                            $net_item_cost = $product['cost'] - $pr_tax_val;
                                            $item_tax = $pr_tax_val * $product['quantity'];
                                        } else {
                                            $net_item_cost = $product['cost'];
                                            $pr_tax_val = ($product['cost'] * $tax_details->rate) / 100;
                                            $unit_cost = $product['cost'] + $pr_tax_val;
                                            $item_tax = $pr_tax_val * $product['quantity'];
                                        }
                                    } else {
                                        $net_item_cost = $product['cost'];
                                        $item_tax = $tax_details->rate;
                                    }
                                } else {
                                    $net_item_cost = $product['cost'];
                                    $item_tax = 0;
                                }

                                $subtotal = (($net_item_cost * $product['quantity']) + $item_tax);
                                $item = array(
                                    'product_id' => $product_id,
                                    'product_code' => $product['code'],
                                    'product_name' => $product['name'],
                                    'net_unit_cost' => $net_item_cost,
                                    'unit_cost' => $unit_cost,
                                    'quantity' => $product['quantity'],
                                    'quantity_balance' => $product['quantity'],
                                    'item_tax' => $item_tax,
                                    'tax_rate_id' => $tax_rate_id,
                                    'tax' => $tax,
                                    'subtotal' => $subtotal,
                                    'warehouse_id' => $warehouse_arr['warehouse_id'],
                                    'date' => date('Y-m-d'),
                                    'status' => 'received',
                                );

                                if ($this->db->insert('purchase_items', $item)) {
                                    $purchase_items_id = $this->db->insert_id();
                                }
                            } else {

                                $subtotal = $product['cost'] * $product['quantity'];
                                $item = array(
                                    'product_id' => $product_id,
                                    'product_code' => $product['code'],
                                    'product_name' => $product['name'],
                                    'net_unit_cost' => $product['cost'],
                                    'unit_cost' => $product['cost'],
                                    'quantity' => $product['quantity'],
                                    'quantity_balance' => $product['quantity'],
                                    'item_tax' => 0,
                                    'tax_rate_id' => 0,
                                    'tax' => '',
                                    'subtotal' => $subtotal,
                                    'warehouse_id' => $warehouse_arr['warehouse_id'],
                                    'date' => date('Y-m-d'),
                                    'status' => 'received',
                                );

                                if ($this->db->insert('purchase_items', $item)) {
                                    $purchase_items_id = $this->db->insert_id();
                                }
                            }
                        }
                    }

                    $warehouses = $this->site->getAllWarehouses();

                    foreach ($variants as $variant) {
                        if ($variant && trim($variant) != '') {
                            $vat = array('product_id' => $product_id, 'name' => trim($variant), 'cost' => $product['cost'], 'quantity' => $product['quantity']);

                            if ($this->db->insert('product_variants', $vat)) {
                                $option_id = $this->db->insert_id();

                                if ($product['quantity'] != 0) {
                                    $this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse_arr['warehouse_id'], 'quantity' => $product['quantity']));
                                    $warehouses_products_variants_id = $this->db->insert_id();
                                }

                                foreach ($warehouses as $warehouse) {
                                    if (!$this->getWarehouseProductVariant($warehouse->id, $product_id, $option_id)) {
                                        $this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse->id, 'quantity' => 0));
                                        $warehouses_products_variants_id = $this->db->insert_id();
                                    }
                                    //else{
                                    //	$this->site->syncVariantQty($option_id, $warehouse->id);
                                    //}
                                }
                            }
                        }
                    }
                    $DatalogArr = array('product_data' => $product, 'items' => $item, 'warehouse_qty' => $warehouse_arr, 'product_attributes' => $variants, 'photos' => '', 'type' => 'import_csv');
                    $DataLog = array(
                        'action_type' => 'Add',
                        'product_id' => $product_id,
                        'quantity' => '',
                        'action_reff_id' => $product_id,
                        'action_affected_data' => json_encode($DatalogArr),
                        'action_comment' => 'Add products',
                    );
                    $this->sma->setUserActionLog($DataLog);
                }
            }
            return true;
        }
        return false;
    }

    public function getProductNames($term, $limit = 20) {
        $this->db->select('' . $this->db->dbprefix('products') . '.id, code, ' . $this->db->dbprefix('products') . '.name as name, ' . $this->db->dbprefix('products') . '.price as price, ' . $this->db->dbprefix('product_variants') . '.name as vname')
                ->where("type != 'combo' AND "
                        . "(" . $this->db->dbprefix('products') . ".name LIKE '%" . $term . "%' OR code LIKE '%" . $term . "%' OR
                concat(" . $this->db->dbprefix('products') . ".name, ' (', code, ')') LIKE '%" . $term . "%')");

        $this->db->join('product_variants', 'product_variants.product_id=products.id', 'left')
                ->where('' . $this->db->dbprefix('product_variants') . '.name', NULL)
                ->group_by('products.id')->limit($limit);

        $q = $this->db->get('products');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getQASuggestions($term, $limit = 50, $warehouse_id) {
        $this->db->select('' . $this->db->dbprefix('products') . '.id, code, ' . $this->db->dbprefix('products') . '.name as name')
                ->where("type != 'combo' AND "
                        . "(" . $this->db->dbprefix('products') . ".name LIKE '%" . $term . "%' OR code LIKE '%" . $term . "%'  OR {$this->db->dbprefix('products')}.article_code LIKE '%" . $term . "%'  OR
                concat(" . $this->db->dbprefix('products') . ".name, ' (', code, ')') LIKE '%" . $term . "%')")
                ->limit($limit);
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $get_qty = $this->db->select('quantity')->where(['product_id' => $row->id, 'warehouse_id' => $warehouse_id])->get('sma_warehouses_products')->row();
                $row->product_qty = ($get_qty->quantity == '') ? 0 : $get_qty->quantity;
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getProductsForPrinting($term, $limit = 5) {
        $this->db->select('' . $this->db->dbprefix('products') . '.id, code, ' . $this->db->dbprefix('products') . '.name as name, ' . $this->db->dbprefix('products') . '.price as price')
                ->where("(" . $this->db->dbprefix('products') . ".name LIKE '%" . $term . "%' OR code LIKE '%" . $term . "%' OR
                concat(" . $this->db->dbprefix('products') . ".name, ' (', code, ')') LIKE '%" . $term . "%')")
                ->limit($limit);
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return false;
    }

    public function updateProduct($id, $data, $items, $warehouse_qty, $product_attributes, $photos, $update_variants, $postype_data = NULL) {
        $DatalogArr = array('product_data' => $data, 'items' => $items, 'warehouse_qty' => $warehouse_qty, 'product_attributes' => $product_attributes, 'photos' => $photos, 'postype_data' => $postype_data, 'variants' => $update_variants);
        $DataLog = array(
            'action_type' => 'Edit',
            'product_id' => $id,
            'quantity' => '',
            'action_reff_id' => $id,
            'action_affected_data' => json_encode($DatalogArr),
            'action_comment' => 'Edit products',
        );
        $this->sma->setUserActionLog($DataLog);
        if ($this->db->update('products', $data, array('id' => $id))) {

          // Urbanpiper for restaurant

          if (($data['up_items'] == '1' && $postype_data['pos_type'] == 'restaurant') || $data['up_items'] == '1' && $postype_data['pos_type'] == 'bakery') {

            $update_id = $postype_data['up_update_id'];
            unset($postype_data['pos_type']);
            unset($postype_data['up_update_id']);

            $this->db->where('id', $update_id)->update('sma_up_products', $postype_data);
        }
        // End Urbanpiper     

            if ($items) {
                $this->db->delete('combo_items', array('product_id' => $id));
                foreach ($items as $item) {
                    $item['product_id'] = $id;
                    $this->db->insert('combo_items', $item);
                }
            }

            $tax_rate = $this->site->getTaxRateByID($data['tax_rate']);

            if ($warehouse_qty && !empty($warehouse_qty)) {
                foreach ($warehouse_qty as $wh_qty) {
                    $this->db->update('warehouses_products', array('rack' => $wh_qty['rack']), array('product_id' => $id, 'warehouse_id' => $wh_qty['warehouse_id']));
                }
            }

            if ($update_variants) {
                $this->db->update_batch('product_variants', $update_variants, 'id');
            }

            if ($photos) {
                foreach ($photos as $photo) {
                    $this->db->insert('product_photos', array('product_id' => $id, 'photo' => $photo));
                }
            }

            if ($product_attributes) {
                foreach ($product_attributes as $pr_attr) {

                    $pr_attr['product_id'] = $id;
                    $variant_warehouse_id = $pr_attr['warehouse_id'];
                    unset($pr_attr['warehouse_id']);
                    $this->db->insert('product_variants', $pr_attr);
                    $option_id = $this->db->insert_id();

                    if ($pr_attr['quantity'] != 0) {
                        $this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $id, 'warehouse_id' => $variant_warehouse_id, 'quantity' => $pr_attr['quantity']));

                        $tax_rate_id = $tax_rate ? $tax_rate->id : NULL;
                        $tax = $tax_rate ? (($tax_rate->type == 1) ? $tax_rate->rate . "%" : $tax_rate->rate) : NULL;
                        $unit_cost = $data['cost'];
                        if ($tax_rate) {
                            if ($tax_rate->type == 1 && $tax_rate->rate != 0) {
                                if ($data['tax_method'] == '0') {
                                    $pr_tax_val = ($data['cost'] * $tax_rate->rate) / (100 + $tax_rate->rate);
                                    $net_item_cost = $data['cost'] - $pr_tax_val;
                                    $item_tax = $pr_tax_val * $pr_attr['quantity'];
                                } else {
                                    $net_item_cost = $data['cost'];
                                    $pr_tax_val = ($data['cost'] * $tax_rate->rate) / 100;
                                    $unit_cost = $data['cost'] + $pr_tax_val;
                                    $item_tax = $pr_tax_val * $pr_attr['quantity'];
                                }
                            } else {
                                $net_item_cost = $data['cost'];
                                $item_tax = $tax_rate->rate;
                            }
                        } else {
                            $net_item_cost = $data['cost'];
                            $item_tax = 0;
                        }

                        $subtotal = (($net_item_cost * $pr_attr['quantity']) + $item_tax);
                        $item = array(
                            'product_id' => $id,
                            'product_code' => $data['code'],
                            'product_name' => $data['name'],
                            'net_unit_cost' => $net_item_cost,
                            'unit_cost' => $unit_cost,
                            'quantity' => $pr_attr['quantity'],
                            'option_id' => $option_id,
                            'quantity_balance' => $pr_attr['quantity'],
                            'item_tax' => $item_tax,
                            'tax_rate_id' => $tax_rate_id,
                            'tax' => $tax,
                            'subtotal' => $subtotal,
                            'warehouse_id' => $variant_warehouse_id,
                            'date' => date('Y-m-d'),
                            'status' => 'received',
                        );
                        $this->db->insert('purchase_items', $item);
                    }
                }
            }

            $this->site->syncQuantity(NULL, NULL, NULL, $id);
            return true;
        } else {
            return false;
        }
    }

    public function updateProductOptionQuantity($option_id, $warehouse_id, $quantity, $product_id) {
        if ($option = $this->getProductWarehouseOptionQty($option_id, $warehouse_id)) {
            if ($this->db->update('warehouses_products_variants', array('quantity' => $quantity), array('option_id' => $option_id, 'warehouse_id' => $warehouse_id))) {
                $this->site->syncVariantQty($option_id, $warehouse_id);
                return TRUE;
            }
        } else {
            if ($this->db->insert('warehouses_products_variants', array('option_id' => $option_id, 'product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $quantity))) {
                $this->site->syncVariantQty($option_id, $warehouse_id);
                return TRUE;
            }
        }
        return FALSE;
    }

    public function updatePrice($data = array()) {
        $varaint = array();
        foreach (array_keys($data) as $key) {
            if ($data[$key]['Variants_Name']) {
                $varaint[$key]['product_code'] = $data[$key]['code'];
                $varaint[$key]['Variants_Name'] = $data[$key]['Variants_Name'];
                $varaint[$key]['Variants_Price'] = $data[$key]['Variants_Price'];
                $varaint[$key]['Variants_Mrp'] = $data[$key]['Variants_Mrp'];
                $varaint[$key]['Variants_Discount_on_mrp'] = $data[$key]['Variants_Discount_on_mrp'];
            }
            unset($data[$key]['Variants_Name']);
            unset($data[$key]['Variants_Price']);
            unset($data[$key]['Product_Name']);
            unset($data[$key]['Variants_Discount_on_mrp']);
            unset($data[$key]['Variants_Mrp']);
        }
        if (!empty($varaint)) {
            $this->updateVariantPrice($varaint);
        }

        if ($this->db->update_batch('products', $data, 'code')) {
            return true;
        }
        return false;
    }

    /**
     * Varaint Price Updated
     * @param type $data
     * @return boolean
     */
    public function updateVariantPrice($data = array()) {
        foreach ($data as $varaintdata) {
            $getproductid = $this->db->select('id')->where(['code' => $varaintdata['product_code']])->get('products')->row();
            $productid = $getproductid->id;

            $expvariant = explode(",", $varaintdata['Variants_Name']);
            $extprice = explode(",", $varaintdata['Variants_Price']);
            $Variants_Mrp = explode(",", $varaintdata['Variants_Mrp']);
            $Variants_Discount_on_mrp = explode(",", $varaintdata['Variants_Discount_on_mrp']);
            if (is_array($expvariant)) {
                foreach ($expvariant as $key => $expv) {
                    $updatedata = ["price" => $extprice[$key],"mrp" => $Variants_Mrp[$key],"variant_discount_on_mrp" => $Variants_Discount_on_mrp[$key], "updated_at" => date('Y-m-d H:i:s')];
                    $variantname = rtrim(ltrim($expv));
                    $this->db->where(["product_id" => $productid, "name" => $variantname])->update('product_variants', $updatedata);
                }
            }
        }
    }

    public function updateUPProductPrice($data = array()) {
        if ($this->db->update_batch('up_products', $data, 'product_code')) {
            return true;
        }
        return false;
    }

    public function deleteProduct($id) {
        if ($this->db->delete('products', array('id' => $id)) && $this->db->delete('warehouses_products', array('product_id' => $id))) {
            $this->db->delete('warehouses_products_variants', array('product_id' => $id));
            $this->db->delete('product_variants', array('product_id' => $id));
            $this->db->delete('product_photos', array('product_id' => $id));
            $this->db->delete('product_prices', array('product_id' => $id));
            return true;
        }
        return FALSE;
    }

    public function totalCategoryProducts($category_id) {
        $q = $this->db->get_where('products', array('category_id' => $category_id));
        return $q->num_rows();
    }

    public function getCategoryByCode($code) {
        $q = $this->db->get_where('categories', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getWarehouseIdByWarehouseCode($code) {
        $q = $this->db->get_where('warehouses', array('code' => $code), 1);
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

    public function getAdjustmentByID($id) {
        $q = $this->db->get_where('adjustments', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAdjustmentItems($adjustment_id) {
        $this->db->select('adjustment_items.*, products.code as product_code, products.name as product_name, products.image, products.details as details, product_variants.name as variant')
                ->join('products', 'products.id=adjustment_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=adjustment_items.option_id', 'left')
                ->group_by('adjustment_items.id')
                ->order_by('id', 'asc');

        $this->db->where('adjustment_id', $adjustment_id);

        $q = $this->db->get('adjustment_items');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $getProdutQty = $this->db->select('quantity')->where(['product_id' => $row->product_id, 'warehouse_id' => $row->warehouse_id])->get('sma_warehouses_products')->row();
                $row->product_qty = $getProdutQty->quantity;
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    //Backup Function
    public function syncAdjustment($data = array()) {
        if (!empty($data)) {
            $where_clause = array('product_id' => $data['product_id'], 'option_id' => $data['option_id'], 'warehouse_id' => $data['warehouse_id'], 'status' => 'received', 'batch_number' => $data['batch_number']);
            /* if ($purchase_item = $this->site->getPurchasedItem($where_clause)) {
              if ($purchase_item->quantity == 0) {
              $quantity_balance = ($data['type'] == 'subtraction') ? $purchase_item->quantity_balance - $data['quantity'] : $purchase_item->quantity_balance + $data['quantity'];
              $quantity = ($data['type'] == 'subtraction') ? $purchase_item->quantity_balance : $purchase_item->quantity + $data['quantity'];

              $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance, 'quantity' => $quantity,), array('id' => $purchase_item->id));
              } else {
              $quantity_balance = ($data['type'] == 'subtraction') ? $purchase_item->quantity_balance - $data['quantity'] : $purchase_item->quantity_balance + $data['quantity'];

              $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $purchase_item->id));
              }
              } else { */
            $pr = $this->site->getProductByID($data['product_id']);
            $item = array(
                'product_id' => $data['product_id'],
                'product_code' => $pr->code,
                'product_name' => $pr->name,
                'net_unit_cost' => $pr->cost,
                'adjustment_id' => !empty($data['adjustment_id']) ? $data['adjustment_id'] : null,
                'unit_cost' => $pr->cost,
                'quantity' => !empty($data['adjustment_id']) ? (($data['type'] == 'subtraction') ? (0 - $data['quantity']) : $data['quantity']) : 0,
                'option_id' => $data['option_id'] ? $data['option_id'] : 0,
                'quantity_balance' => ($data['type'] == 'subtraction') ? (0 - $data['quantity']) : $data['quantity'],
                'item_tax' => 0,
                'tax_rate_id' => 1,
                'tax' => 0,
                'tax_method' => 0,
                'subtotal' => ($pr->cost * $data['quantity']),
                'warehouse_id' => $data['warehouse_id'],
                'date' => date('Y-m-d'),
                'status' => 'received',
                'expiry' => $data['expiry'] ? $data['expiry'] : null,
                'batch_number' => $data['batch_number'] ? $data['batch_number'] : null,
                'product_unit_id' => $data['unit_id'] ? $data['unit_id'] : $pr->purchase_unit,
                'hsn_code' => $pr->hsn_code,
                'unit_quantity' => 1,
            );
            $this->db->insert('purchase_items', $item);
            // }

            $this->site->syncProductQty($data['product_id'], $data['warehouse_id']);
            if ($data['option_id']) {
                $this->site->syncVariantQty($data['option_id'], $data['warehouse_id'], $data['product_id']);
            }

            /* // Urbanpiper Stock Manage 
              if($this->Settings->pos_type == 'restaurant'){
              $this->load->model("Urban_piper_model","UPM");

              $this->UPM->Product_out_of_stock([$data['product_id']], $data['warehouse_id']);
              } */
        }
    }

    public function getPurchasedItem($where_clause, $quantity, $adjustment_type) {

        $orderby = ($this->Settings->accounting_method == 1) ? 'desc' : 'asc';
        $this->db->order_by('date', $orderby);
        $this->db->order_by('purchase_id', $orderby);

        if ($this->Settings->product_batch_setting > 0 && $where_clause['batch_number']) {
            $this->db->where('batch_number', $where_clause['batch_number']);
        }
        unset($where_clause['batch_number']);

        if ($where_clause['adjustment_id'] == TRUE) {
            $this->db->where('(adjustment_id IS NOT NULL)');
            unset($where_clause['adjustment_id']);
        } else if (!$this->Settings->overselling) {
            $this->db->where('(purchase_id IS NOT NULL OR transfer_id IS NOT NULL OR adjustment_id IS NOT NULL)');
        }

        if ($where_clause['status']) {
            $this->db->where('status', $where_clause['status']);
            unset($where_clause['status']);
        } else {
            $this->db->group_start()->where('status', 'received')->or_where('status', 'partial')->group_end();
        }

        if ($adjustment_type = 'subtraction') {
            $this->db->where(" ( `quantity_balance` - $quantity ) >= '0' ");
        } else {
            $this->db->where(" ( `quantity_balance` + $quantity ) <= `quantity` OR `quantity` == '0' ");
        }

        $this->db->where($where_clause);

        $q = $this->db->get('purchase_items');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function syncAdjustment_new($data = array()) {

        if (!empty($data)) {

            $where_clause = array('product_id' => $data['product_id'], 'option_id' => $data['option_id'], 'warehouse_id' => $data['warehouse_id'], 'status' => 'received', 'batch_number' => $data['batch_number']);

            if ($purchase_item = $this->getPurchasedItem($where_clause, $data['quantity'], $data['type'])) {

                $quantity_balance = ($data['type'] == 'subtraction') ? $purchase_item->quantity_balance - $data['quantity'] : $purchase_item->quantity_balance + $data['quantity'];
                $quantity = $purchase_item->quantity;

                if ($purchase_item->quantity == 0 && !$purchase_item->purchase_id) {

                    $quantity = ($data['type'] == 'subtraction') ? $purchase_item->quantity_balance : $purchase_item->quantity + $data['quantity'];
                }

                $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance, 'quantity' => $quantity), array('id' => $purchase_item->id));
            } else {

                $pr = $this->site->getProductByID($data['product_id']);
                $item = array(
                    'adjustment_id' => $data['adjustment_id'],
                    'product_id' => $data['product_id'],
                    'product_code' => $pr->code,
                    'product_name' => $pr->name,
                    'net_unit_cost' => $data['net_unit_cost'],
                    'unit_cost' => $data['cost'],
                    'real_unit_cost' => $data['real_unit_cost'],
                    'quantity' => ($data['type'] == 'subtraction') ? (0 - $data['quantity']) : $data['quantity'],
                    'option_id' => $data['option_id'] ? $data['option_id'] : 0,
                    'quantity_balance' => ($data['type'] == 'subtraction') ? (0 - $data['quantity']) : $data['quantity'],
                    'item_tax' => 0,
                    'tax_rate_id' => 1,
                    'tax' => 0,
                    'tax_method' => $data['tax_method'] ? $data['tax_method'] : $pr->tax_method,
                    'subtotal' => ($data['net_unit_cost'] * $data['quantity']),
                    'warehouse_id' => $data['warehouse_id'],
                    'date' => date('Y-m-d'),
                    'status' => 'received',
                    'expiry' => $data['expiry'] ? $data['expiry'] : null,
                    'batch_number' => $data['batch_number'] ? $data['batch_number'] : null,
                    'product_unit_id' => $data['product_unit_id'] ? $data['product_unit_id'] : $pr->purchase_unit,
                    'hsn_code' => $pr->hsn_code,
                    'unit_quantity' => $data['unit_quantity'] ? $data['unit_quantity'] : 1
                );
                $this->db->insert('purchase_items', $item);
            }

            $this->site->syncProductQty($data['product_id'], $data['warehouse_id']);
            if ($data['option_id']) {
                $this->site->syncVariantQty($data['option_id'], $data['warehouse_id'], $data['product_id']);
            }
        }
    }

    public function reverseAdjustment($id) {
        if ($products = $this->getAdjustmentItems($id)) {
            foreach ($products as $adjustment) {
                $where_clause = array('product_id' => $adjustment->product_id, 'warehouse_id' => $adjustment->warehouse_id, 'option_id' => $adjustment->option_id, 'status' => 'received');
                if ($purchase_item = $this->site->getPurchasedItem($where_clause)) {
                    $quantity_balance = $adjustment->type == 'subtraction' ? $purchase_item->quantity_balance + $adjustment->quantity : $purchase_item->quantity_balance - $adjustment->quantity;
                    $this->db->update('purchase_items', array('quantity_balance' => $quantity_balance), array('id' => $purchase_item->id));
                }

                $this->site->syncProductQty($adjustment->product_id, $adjustment->warehouse_id);
                if ($adjustment->option_id) {
                    $this->site->syncVariantQty($adjustment->option_id, $adjustment->warehouse_id, $adjustment->product_id);
                }
            }
        }
    }

    public function addAdjustment($data, $products) {

        if ($this->db->insert('adjustments', $data)) {
            $adjustment_id = $this->db->insert_id();

            foreach ($products as $product) {
                $product['adjustment_id'] = $adjustment_id;

                $adjustment_item = array(
                    'adjustment_id' => $adjustment_id,
                    'product_id' => $product['product_id'],
                    'option_id' => $product['option_id'],
                    'batch_number' => $product['batch_number'] ? $product['batch_number'] : NULL,
                    'quantity' => $product['quantity'],
                    'warehouse_id' => $product['warehouse_id'],
                    'serial_no' => $product['serial_no'],
                    'type' => $product['type']
                );

                $this->db->insert('adjustment_items', $adjustment_item);
                $adjustment_items_id = $this->db->insert_id();
                $this->syncAdjustment($product);

                /* Products Action Logs */
                $DatalogArr = array('adjustment_items_id' => $adjustment_items_id, 'adjustment_item' => $item);
                $DataLog = array(
                    'action_type' => 'Quantity Adjustments ',
                    'product_id' => $product['product_id'],
                    'option_id' => $product['option_id'],
                    'batch_number' => $product['batch_number'] ? $product['batch_number'] : NULL,
                    'quantity' => ($product['type'] == 'subtraction') ? 0 - $product['quantity'] : $product['quantity'],
                    'action_reff_id' => "sma_adjustments.id:$adjustment_id",
                    'action_affected_data' => json_encode($product),
                    'action_comment' => $product['type'] . ' | ' . $data['note']
                );
                $this->sma->setUserActionLog($DataLog);
                /* //Products Action Logs */
            }
            if ($this->site->getReference('qa') == $data['reference_no']) {
                $this->site->updateReference('qa');
            }
            return true;
        }
        return false;
    }

    public function updateAdjustment($id, $data, $products) {
        $this->reverseAdjustment($id);
        if ($this->db->update('adjustments', $data, array('id' => $id)) &&
                $this->db->delete('adjustment_items', array('adjustment_id' => $id))) {
            $DatalogArr = array('data' => $data, 'products' => $products);
            $DataLog = array(
                'action_type' => 'Edit',
                'product_id' => '',
                'quantity' => '',
                'action_reff_id' => $id,
                'action_affected_data' => json_encode($DatalogArr),
                'action_comment' => 'Edit adjustments',
            );
            $this->sma->setUserActionLog($DataLog);
            foreach ($products as $product) {
                $product['adjustment_id'] = $id;
                $this->db->insert('adjustment_items', $product);
                $this->syncAdjustment($product);
            }
            return true;
        }
        return false;
    }

    public function deleteAdjustment($id) {
        $this->reverseAdjustment($id);
        if ($this->db->delete('adjustments', array('id' => $id)) &&
                $this->db->delete('adjustment_items', array('adjustment_id' => $id))) {
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

    public function addQuantity($product_id, $warehouse_id, $quantity, $rack = NULL) {

        if ($this->getProductQuantity($product_id, $warehouse_id)) {
            if ($this->updateQuantity($product_id, $warehouse_id, $quantity, $rack)) {
                return TRUE;
            }
        } else {
            if ($this->insertQuantity($product_id, $warehouse_id, $quantity, $rack)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function insertQuantity($product_id, $warehouse_id, $quantity, $rack = NULL) {
        $product = $this->site->getProductByID($product_id);
        if ($this->db->insert('warehouses_products', array('product_id' => $product_id, 'warehouse_id' => $warehouse_id, 'quantity' => $quantity, 'rack' => $rack, 'avg_cost' => $product->cost))) {
            $this->site->syncProductQty($product_id, $warehouse_id);
            return true;
        }
        return false;
    }

    public function updateQuantity($product_id, $warehouse_id, $quantity, $rack = NULL) {
        $data = $rack ? array('quantity' => $quantity, 'rack' => $rack) : $data = array('quantity' => $quantity);
        if ($this->db->update('warehouses_products', $data, array('product_id' => $product_id, 'warehouse_id' => $warehouse_id))) {
            $this->site->syncProductQty($product_id, $warehouse_id);
            return true;
        }
        return false;
    }

    public function products_count($category_id, $subcategory_id = NULL) {
        if ($category_id) {
            $this->db->where('category_id', $category_id);
        }
        if ($subcategory_id) {
            $this->db->where('subcategory_id', $subcategory_id);
        }
        $this->db->from('products');
        return $this->db->count_all_results();
    }

    public function fetch_products($category_id, $limit, $start, $subcategory_id = NULL) {

        $this->db->limit($limit, $start);
        if ($category_id) {
            $this->db->where('category_id', $category_id);
        }
        if ($subcategory_id) {
            $this->db->where('subcategory_id', $subcategory_id);
        }
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

    public function getProductWarehouseOptionQty($option_id, $warehouse_id) {
        $q = $this->db->get_where('warehouses_products_variants', array('option_id' => $option_id, 'warehouse_id' => $warehouse_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function syncVariantQty($option_id) {
        $wh_pr_vars = $this->getProductWarehouseOptions($option_id);
        $qty = 0;
        foreach ($wh_pr_vars as $row) {
            $qty += $row->quantity;
        }
        if ($this->db->update('product_variants', array('quantity' => $qty), array('id' => $option_id))) {
            return TRUE;
        }
        return FALSE;
    }

    public function getProductWarehouseOptions($option_id) {
        $q = $this->db->get_where('warehouses_products_variants', array('option_id' => $option_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function setRack($data) {
        if ($this->db->update('warehouses_products', array('rack' => $data['rack']), array('product_id' => $data['product_id'], 'warehouse_id' => $data['warehouse_id']))) {
            return TRUE;
        }
        return FALSE;
    }

    public function getSoldQty($id) {
        $this->db->select("date_format(" . $this->db->dbprefix('sales') . ".date, '%Y-%M') month, SUM( " . $this->db->dbprefix('sale_items') . ".quantity ) as sold, SUM( " . $this->db->dbprefix('sale_items') . ".subtotal ) as amount")
                ->from('sales')
                ->join('sale_items', 'sales.id=sale_items.sale_id', 'left')
                ->group_by("date_format(" . $this->db->dbprefix('sales') . ".date, '%Y-%m')")
                ->where($this->db->dbprefix('sale_items') . '.product_id', $id)
                //->where('DATE(NOW()) - INTERVAL 1 MONTH')
                ->where('DATE_ADD(curdate(), INTERVAL 1 MONTH)')
                ->order_by("date_format(" . $this->db->dbprefix('sales') . ".date, '%Y-%m') desc")->limit(3);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPurchasedQty($id) {
        $this->db->select("date_format(" . $this->db->dbprefix('purchases') . ".date, '%Y-%M') month, SUM( " . $this->db->dbprefix('purchase_items') . ".quantity ) as purchased, SUM( " . $this->db->dbprefix('purchase_items') . ".subtotal ) as amount")
                ->from('purchases')
                ->join('purchase_items', 'purchases.id=purchase_items.purchase_id', 'left')
                ->group_by("date_format(" . $this->db->dbprefix('purchases') . ".date, '%Y-%m')")
                ->where($this->db->dbprefix('purchase_items') . '.product_id', $id)
                //->where('DATE(NOW()) - INTERVAL 1 MONTH')
                ->where('DATE_ADD(curdate(), INTERVAL 1 MONTH)')
                ->order_by("date_format(" . $this->db->dbprefix('purchases') . ".date, '%Y-%m') desc")->limit(3);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPurchasedQtyStatus($id) {
        $this->db->select("date_format(" . $this->db->dbprefix('purchases') . ".date, '%Y-%M') month, SUM( " . $this->db->dbprefix('purchase_items') . ".quantity ) as purchased, SUM( " . $this->db->dbprefix('purchase_items') . ".subtotal ) as amount")
                ->from('purchases')
                ->join('purchase_items', 'purchases.id=purchase_items.purchase_id', 'left')
                ->group_by("date_format(" . $this->db->dbprefix('purchases') . ".date, '%Y-%m')")
                ->where($this->db->dbprefix('purchase_items') . '.product_id', $id)
                //->where('DATE(NOW()) - INTERVAL 1 MONTH')
                ->where($this->db->dbprefix('purchases') . '.status', 'received')
                //->group_start()->where($this->db->dbprefix('purchases') . '.status', 'received')->or_where($this->db->dbprefix('purchases') . '.status', 'partial')->or_where($this->db->dbprefix('purchases') . '.status', 'returned')->group_end()
                ->where('DATE_ADD(curdate(), INTERVAL 1 MONTH)')
                ->order_by("date_format(" . $this->db->dbprefix('purchases') . ".date, '%Y-%m') desc")->limit(3);
        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getAllVariants() {
        $q = $this->db->get('variants');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getWarehouseProductVariant($warehouse_id, $product_id, $option_id = NULL) {

        $this->db->where(['product_id' => $product_id, 'warehouse_id' => $warehouse_id]);
        if ($option_id) {
            $this->db->where(['option_id' => $option_id]);
            $q = $this->db->get('warehouses_products_variants', 1);
        } else {
            $q = $this->db->get('warehouses_products_variants');
        }

        $num_rows = $q->num_rows();

        if ($num_rows == 1) {
            return $q->row();
        } elseif ($num_rows > 1) {
            foreach (($q->result()) as $row) {
                $data[$row->option_id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getPurchaseItems($purchase_id) {
        $q = $this->db->get_where('purchase_items', array('purchase_id' => $purchase_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getBarcodeItemQtySum($TableName, $data) {
        $q = $this->db->get_where($TableName, $data);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getTransferItems($transfer_id) {
        $q = $this->db->get_where('purchase_items', array('transfer_id' => $transfer_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getUnitByCode($code) {
        $q = $this->db->get_where("units", array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
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

    public function getBrandByName($name) {
        $q = $this->db->get_where('brands', array('name' => $name), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getStockCountProducts($warehouse_id, $type, $categories = NULL, $brands = NULL) {
        $this->db->select("{$this->db->dbprefix('products')}.id as id, "
                         . "{$this->db->dbprefix('products')}.code as code, "
                         . "{$this->db->dbprefix('products')}.name as name, "
                         . "{$this->db->dbprefix('products')}.cost as cost, "
                         . "{$this->db->dbprefix('products')}.price as price, "
                         . "{$this->db->dbprefix('products')}.mrp as mrp, "
                         . "{$this->db->dbprefix('warehouses_products')}.quantity as quantity")
                ->join('warehouses_products', 'warehouses_products.product_id = products.id', 'left')
                ->where('warehouses_products.warehouse_id', $warehouse_id)
                ->where('products.type', 'standard')
                ->order_by('products.code', 'asc');
        if ($categories) {
            $r = 1;
            $this->db->group_start();
            foreach ($categories as $category) {
                if ($r == 1) {
                    $this->db->where('products.category_id', $category);
                } else {
                    $this->db->or_where('products.category_id', $category);
                }
                $r++;
            }
            $this->db->group_end();
        }
        if ($brands) {
            $r = 1;
            $this->db->group_start();
            foreach ($brands as $brand) {
                if ($r == 1) {
                    $this->db->where('products.brand', $brand);
                } else {
                    $this->db->or_where('products.brand', $brand);
                }
                $r++;
            }
            $this->db->group_end();
        }

        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getStockCountProductVariants($warehouse_id, $product_id) {
        $this->db->select("{$this->db->dbprefix('product_variants')}.id, {$this->db->dbprefix('product_variants')}.name, {$this->db->dbprefix('warehouses_products_variants')}.quantity as quantity")
                ->join('warehouses_products_variants', 'warehouses_products_variants.option_id=product_variants.id', 'left');
        $q = $this->db->get_where('product_variants', array('product_variants.product_id' => $product_id, 'warehouses_products_variants.warehouse_id' => $warehouse_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

    public function addStockCount($data) {
        if ($this->db->insert('stock_counts', $data)) {
            return TRUE;
        }
        return FALSE;
    }

    public function finalizeStockCount($id, $data, $products) {
        if ($this->db->update('stock_counts', $data, array('id' => $id))) {
            foreach ($products as $product) {
                $this->db->insert('stock_count_items', $product);
            }
            return TRUE;
        }
        return FALSE;
    }

    public function getStouckCountByID($id) {
        $q = $this->db->get_where("stock_counts", array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getStockCountItems($stock_count_id) {
        $q = $this->db->get_where("stock_count_items", array('stock_count_id' => $stock_count_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return NULL;
    }

    public function getAdjustmentByCountID($count_id) {
        $q = $this->db->get_where('adjustments', array('count_id' => $count_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductVariantID($product_id, $name) {
        $q = $this->db->get_where("product_variants", array('product_id' => $product_id, 'name' => $name), 1);
        if ($q->num_rows() > 0) {
            $variant = $q->row();
            return $variant->id;
        }
        return NULL;
    }

    public function getProductVariantByID($variant_id) {
        $q = $this->db->get_where("product_variants", array('id' => $variant_id), 1);
        if ($q->num_rows() > 0) {
            $variant = $q->row();
            return $variant;
        }
        return NULL;
    }

    //-------------------------- Create API MODEL For SHOP-----------------------//

    public function getCategories($parent_id = null, $param = null) {

        $this->db->select('id , code ,name ,image ,id as cat_id, in_eshop, is_active, (select count(*) from sma_categories where parent_id=cat_id) as subcat_count ,IF(parent_id IS NULL,0,parent_id) as parent_id ');

        //------------------Parent ID ---------------------//
        if ($parent_id !== null):
            $parent_id = !empty($parent_id) ? $parent_id : 0;
            $this->db->where('parent_id', $parent_id);
        endif;

        //------------------Keyword---------------------//
        if (isset($param) && is_array($param)):
            $seach_keyword = isset($param['keyword']) && !empty($param['keyword']) ? $param['keyword'] : NULL;
            if (!empty($seach_keyword)):
                $this->db->where('name', $seach_keyword);
            endif;
        endif;

        $this->db->order_by('id');
        $q = $this->db->get("categories");
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getAllProduct($param = null) {

        $this->db->select('products.*,units.id as unit_id ,units.code as unit_code ,units.name as unit_name');
        if (is_array($param)):
            //------------------Keyword---------------------//
            $seach_keyword = isset($param['keyword']) && !empty($param['keyword']) ? $param['keyword'] : NULL;
            if (!empty($seach_keyword)):
                $this->db->like('products.name', $seach_keyword);
            endif;

            //------------------Keyword---------------------//
            $category_id = isset($param['category_id']) && !empty($param['category_id']) ? $param['category_id'] : NULL;
            if (!empty($category_id)):
                $this->db->where('products.category_id', $category_id);
            endif;

            //------------------Keyword---------------------//
            $subcategory_id = isset($param['subcategory_id']) && !empty($param['subcategory_id']) ? $param['subcategory_id'] : NULL;
            if (!empty($subcategory_id)):
                $this->db->where('products.subcategory_id', $subcategory_id);
            endif;

            //------------------Limit ---------------------//
            $seach_offset = isset($param['offset']) && $param['offset'] !== null ? (int) $param['offset'] : NULL;
            $seach_limit = isset($param['limit']) && $param['limit'] !== null ? (int) $param['limit'] : NULL;
            if ($seach_offset !== null && $seach_limit !== null):
                $this->db->limit($seach_limit, $seach_offset);
            endif;

        endif;


        $this->db->order_by('products.name');
        $this->db->join('units', 'products.sale_unit =  units.id', 'left');
        $this->db->join('product_variants', 'products.id =  product_variants.product_id', 'left');
        $this->db->select('product_variants.id as variant_id, product_variants.name AS variant_name, product_variants.cost AS variant_cost, product_variants.price AS variant_price, product_variants.quantity AS variant_quantity');
        $q = $this->db->get("products");
        //echo $this->db->last_query(); 
        if ($q->num_rows() > 0) {
            $products_modified = $q->result_array();
            $arr = array();
            foreach ($products_modified as $key => $value) {
                if (in_array($value['id'], $arr)) {
                    $key1 = array_search($value['id'], $arr);
                    if ($value['variant_id']) {
                        $products_modified[$key1]['variants'][] = array('variant_id' => $value['variant_id'], 'variant_name' => $value['variant_name'], 'variant_cost' => $value['variant_cost'], 'variant_price' => $value['variant_price'], 'variant_quantity' => $value['variant_quantity']);
                    }
                    unset($products_modified[$key]);
                } else {
                    $arr[$key] = $value['id'];
                    if ($value['variant_id']) {
                        $products_modified[$key]['variants'][] = array('variant_id' => $value['variant_id'], 'variant_name' => $value['variant_name'], 'variant_cost' => $value['variant_cost'], 'variant_price' => $value['variant_price'], 'variant_quantity' => $value['variant_quantity']);
                    }
                    unset($products_modified[$key]['variant_id']);
                    unset($products_modified[$key]['variant_name']);
                    unset($products_modified[$key]['variant_cost']);
                    unset($products_modified[$key]['variant_price']);
                    unset($products_modified[$key]['variant_quantity']);
                }
            }
            return $products_modified;
        }
        return FALSE;
    }

    public function products_count_eshop($seach_keyword, $category_id, $subcategory_id = NULL) {
        if (!empty($category_id)) {

            $this->db->where('category_id', $category_id);
        }
        if (!empty($subcategory_id)) {
            $this->db->where('subcategory_id', $subcategory_id);
        }
        if (!empty($seach_keyword)):
            $this->db->like('products.name', $seach_keyword);
        endif;
        $this->db->from('products');
        $cnt = $this->db->count_all_results();
        return $cnt;
    }

    public function setFavourites($id) {
        $data = array('is_featured' => 1);
        if ($id) {
            $this->db->update('products', $data, array('id' => $id));
            return true;
        } else {
            return false;
        }
    }

    public function unsetFavourites($id) {
        $data = array('is_featured' => 0);
        if ($id) {
            $this->db->update('products', $data, array('id' => $id));
            return true;
        } else {
            return false;
        }
    }

    public function getWherehousProducts($warehouse_id = NULL, $categories = 0, $listbycategory = 0) {
        $this->db->select("{$this->db->dbprefix('products')}.id as id, {$this->db->dbprefix('products')}.code as code, {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('products')}.type,{$this->db->dbprefix('products')}.category_id,{$this->db->dbprefix('products')}.subcategory_id, {$this->db->dbprefix('warehouses_products')}.warehouse_id,{$this->db->dbprefix('warehouses_products')}.quantity as quantity");
        $this->db->join('warehouses_products', 'warehouses_products.product_id=products.id', 'left');
        if ($warehouse_id) {
            $this->db->where('warehouses_products.warehouse_id', $warehouse_id);
        }

        if ($categories) {
            $r = 1;
            $this->db->group_start();
            $categoryIds = explode(',', $categories);
            foreach ($categoryIds as $category) {
                if ($r == 1) {
                    $this->db->where('products.category_id', $category);
                } else {
                    $this->db->or_where('products.category_id', $category);
                }
                $r++;
            }
            $this->db->group_end();
        }

        $q = $this->db->get('products');

        if ($q->num_rows() > 0) {

            foreach (($q->result()) as $row) {

                $products[$row->id]['id'] = $row->id;
                $products[$row->id]['name'] = $row->name;
                $products[$row->id]['code'] = $row->code;
                $products[$row->id]['type'] = $row->type;
                $products[$row->id]['category_id'] = $row->category_id;
                $products[$row->id]['subcategory_id'] = $row->subcategory_id;
                $products[$row->id]['wherehouse'][$row->warehouse_id] = $row->quantity;
            }
            foreach ($products as $id => $wherehouses) {

                $products[$id]['total'] = 0;

                foreach ($wherehouses['wherehouse'] as $key => $value) {
                    $products[$id]['total'] += $value;
                }
            }

            if ($listbycategory) {

                foreach ($products as $id => $prod) {
                    $stocks[$prod['category_id']][$id]['id'] = $prod['id'];
                    $stocks[$prod['category_id']][$id]['name'] = $prod['name'];
                    $stocks[$prod['category_id']][$id]['code'] = $prod['code'];
                    $stocks[$prod['category_id']][$id]['type'] = $prod['type'];
                    $stocks[$prod['category_id']][$id]['category_id'] = $prod['category_id'];
                    $stocks[$prod['category_id']][$id]['subcategory_id'] = $prod['subcategory_id'];
                    $stocks[$prod['category_id']][$id]['wherehouse'] = $prod['wherehouse'];
                    $stocks[$prod['category_id']][$id]['total'] = $prod['total'];
                }
            } else {

                $stocks = $products;
            }

            return $stocks;
        } else {
            return $q->num_rows();
        }
    }

    public function getAllProductStock($param = null) {

        $this->db->select('products.*,units.id as unit_id ,units.code as unit_code ,units.name as unit_name');
        if (is_array($param)):
            //------------------Keyword---------------------//
            $seach_keyword = isset($param['keyword']) && !empty($param['keyword']) ? $param['keyword'] : NULL;
            if (!empty($seach_keyword)):
                $this->db->like('products.name', $seach_keyword);
            endif;

            //------------------Keyword---------------------//
            $category_id = isset($param['category_id']) && !empty($param['category_id']) ? $param['category_id'] : NULL;
            if (!empty($category_id)):
                $this->db->where('products.category_id', $category_id);
            endif;

            //------------------Keyword---------------------//
            $subcategory_id = isset($param['subcategory_id']) && !empty($param['subcategory_id']) ? $param['subcategory_id'] : NULL;
            if (!empty($subcategory_id)):
                $this->db->where('products.subcategory_id', $subcategory_id);
            endif;

            //------------------Limit ---------------------//
            $seach_offset = isset($param['offset']) && $param['offset'] !== null ? (int) $param['offset'] : NULL;
            $seach_limit = isset($param['limit']) && $param['limit'] !== null ? (int) $param['limit'] : NULL;
            if ($seach_offset !== null && $seach_limit !== null):
                $this->db->limit($seach_limit, $seach_offset);
            endif;

        endif;

        $this->db->order_by('products.name');
        $this->db->join('units', 'products.sale_unit =  units.id', 'left');
        $this->db->join('product_variants', 'products.id =  product_variants.product_id', 'left');
        $this->db->select('product_variants.id AS variant_id,product_variants.name AS variant_name, product_variants.cost AS variant_cost, product_variants.price AS variant_price, product_variants.quantity AS variant_quantity');
        $q = $this->db->get("products");
        //echo $this->db->last_query(); 
        if ($q->num_rows() > 0) {
            $products_modified = $q->result_array();
            $arr = array();
            // $wherehouseData = $this->getWherehousProducts(NULL, $category_id);

            foreach ($products_modified as $key => $value) {
                if (in_array($value['id'], $arr)) {
                    $key1 = array_search($value['id'], $arr);
                    $products_modified[$key1]['variants'][] = array('variant_id' => $value['variant_id'], 'variant_name' => $value['variant_name'], 'variant_cost' => $value['variant_cost'], 'variant_price' => $value['variant_price'], 'variant_quantity' => $value['variant_quantity']);
                    unset($products_modified[$key]);
                } else {
                    $arr[$key] = $value['id'];
                    $products_modified[$key]['variants'][] = array('variant_id' => $value['variant_id'], 'variant_name' => $value['variant_name'], 'variant_cost' => $value['variant_cost'], 'variant_price' => $value['variant_price'], 'variant_quantity' => $value['variant_quantity']);
                    unset($products_modified[$key]['variant_id']);
                    unset($products_modified[$key]['variant_name']);
                    unset($products_modified[$key]['variant_cost']);
                    unset($products_modified[$key]['variant_price']);
                    unset($products_modified[$key]['variant_quantity']);
                }

//                if(isset($wherehouseData[$value['id']])) {
//                    $products_modified[$key]['stocks'] = $wherehouseData[$value['id']];
//                }
            }
            return $products_modified;
        }
        return FALSE;
    }

    function getVariantDetails($VarientId, $ProductId, $WarehouseId) {
        //echo "select pv.* from sma_warehouses_products wp inner join sma_product_variants pv on pv.product_id=wp.product_id where pv.product_id='$ProductId' and pv.name='$VarientName' and wp.warehouse_id='$WarehouseId'";
        /*
          $this->db->where('product_variants.product_id', $ProductId);
          $this->db->where('product_variants.id', $VarientId);
          $this->db->where('warehouses_products.warehouse_id', $WarehouseId);
          $this->db->join('product_variants ', 'product_variants.product_id=warehouses_products.product_id','inner');
          $this->db->select('product_variants.*');
          $q = $this->db->get("warehouses_products "); */

        $this->db->where('product_id', $ProductId);
        $this->db->where('option_id', $VarientId);
        $this->db->where('warehouse_id', $WarehouseId);
        $this->db->select('*');
        $q = $this->db->get("warehouses_products_variants"); //25-09-2019 according to warehousesvariants
        return $q->result();
    }

    // Get Product List
    function get_product_list() {
        $get_arg = func_get_args(); // get Werehouse id 0 index 
        /*
          $this->db->select('warehouses_products.id,products.id as product_id,warehouses_products.quantity,products.name,products.code')
          ->join('warehouses_products', 'products.id = warehouses_products.product_id and warehouses_products.warehouse_id = '.$get_arg[0],'left')
          ->order_by('products.name','ASC');
         */

        $this->db->select('warehouses_products.id,products.id as product_id,warehouses_products.quantity,products.name,products.code')
                ->join('warehouses_products', 'products.id = warehouses_products.product_id', 'left')
                ->where(['warehouses_products.warehouse_id' => $get_arg[0]])->order_by('products.name', 'ASC');


        $get_data = $this->db->get('products')->result();

        foreach ($get_data as $row_value) {

            //$q = $this->db->where(array('product_id' => $row_value->product_id))->get('product_variants');
            $q = $this->db->select('product_variants.id as id, product_variants.name as name, product_variants.cost as cost, product_variants.quantity as total_quantity, warehouses_products_variants.quantity as quantity')
                            ->join('warehouses_products_variants', 'warehouses_products_variants.option_id=product_variants.id', 'left')
                            ->where('product_variants.product_id', $row_value->product_id)
                            ->where('warehouses_products_variants.warehouse_id', $get_arg[0])->get('product_variants');
            $data = [];
            $quantity = 0;
            if ($q->num_rows() > 0) {
                foreach (($q->result()) as $row) {
                    $data[] = $row;
                    $quantity = $quantity + $row->quantity;
                }
                $variant = (object) $data;
                $product_quantity = $quantity;
            } else {
                $data = '';
                $variant = '';
                $product_quantity = '';
            }
            $passdata[] = array('item' => $row_value, 'variant' => $variant, 'quantity' => $product_quantity);
        }
        return $passdata;
    }

    // End Get Product List   
    // Get Foodtype
    public function getfoodstype() {
        return $this->db->where(array('is_active' => '1', 'is_delete' => '0'))->get('sma_food_type')->result();
    }

    // End Get Foodtype
    // Urbanpiper data get  28-05-19
    public function getupnproduct($productid) {
        return $this->db->select('*')->where('product_id', $productid)->get('sma_up_products')->row();
    }

    public function setupnproduct($product) {

        $data['product_id'] = $product->id;
        $data['product_code'] = $product->code;
        $data['price'] = $product->price;
        $data['food_type_id'] = $product->food_type_id;

        $objdata = new stdClass();

        if ($this->db->insert('sma_up_products', $data)) {
            $objdata->id = $this->db->insert_id();
        }

        $objdata->product_id = $data['product_id'];
        $objdata->product_code = $data['product_code'];
        $objdata->price = $data['price'];
        $objdata->food_type_id = $data['food_type_id'];
        return $objdata;
    }

    public function get_custom_product_field($Field, $Type) {
        $q = $this->db->get_where('product_custom_field', array($Field => $Type));
        if ($q->num_rows() > 0) {
            foreach (($q->result_array()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
    }

// Get warehouseProduct List
    function get_warehousesproduct_list() {
        $get_arg = func_get_args(); // get Werehouse id 0 index 

        $this->db->select('warehouses_products.id,products.id as product_id,warehouses_products.quantity,products.name,products.code, product_variants.name as option,product_variants.id as varentid')
                ->join('product_variants', 'product_variants.product_id=products.id', 'left')
                ->join('warehouses_products', 'products.id = warehouses_products.product_id and warehouses_products.warehouse_id = ' . $get_arg[0], 'left')
                ->order_by('products.name', 'ASC');

        $get_data = $this->db->get('products')->result();
        $warehouse_id = $get_arg[0];
        foreach ($get_data as $row_value) {

            if ($row_value->option) {
                $qty1 = $this->db->select('quantity')->where(['product_id' => $row_value->product_id, 'warehouse_id' => $warehouse_id, 'option_id' => $row_value->varentid])->get('sma_warehouses_products_variants')->row(); //Shock  Quantity Warehouse 1 query With Varent
            } else {
                $qty1 = $this->db->select('quantity')->where(['product_id' => $row_value->product_id, 'warehouse_id' => $warehouse_id])->get('sma_warehouses_products')->row(); //Shock Quantity Warehouse 1 query Without Varent
            }

            $data = [];
            //$product_quantity = $qty1->quantity;
            $row_value->quantity = $qty1->quantity;
            $q = $this->db->where(array('product_id' => $row_value->product_id))->get('product_variants');
            $quantity = 0;
            if ($q->num_rows() > 0) {
                foreach (($q->result()) as $row) {
                    $data[] = $row;
                    $quantity = $quantity + $row->quantity;
                }
                $variant = (object) $data;
                $product_quantity = $quantity;
            } else {
                $data = '';
                $variant = '';
                $product_quantity = '';
            }
            $passdata[] = array('item' => $row_value, 'variant' => $variant, 'quantity' => $product_quantity);
        }
        return $passdata;
    }

    // End Get warehouseProduct List   

    /**
     * Get Biller Details
     * @return type
     */
    public function getBillerDetails() {
        $biller_id = $this->db->select('default_biller')->where(['pos_id' => '1'])->get('sma_pos_settings')->row();
        $billerDetails = $this->db->select('*')->where(['id' => $biller_id->default_biller])->get('sma_companies')->row();
        return $billerDetails;
    }

    /**
     * Bulk Image Upload
     * @param type $data
     */
    public function bulkimageUpload($data) {
        foreach ($data as $imagevalue) {
            $getproductid = $this->db->select('id')->where(['code' => $imagevalue['code']])->get('products')->row();
            $productid = $getproductid->id;
            if (!empty($imagevalue['Image'])) {
                $prductimage = ['image' => $imagevalue['Image']];
                $this->db->where(['id' => $productid])->update('products', $prductimage);
            }

            if (!empty($imagevalue['Gallery_1']) || !empty($imagevalue['Gallery_2']) || !empty($imagevalue['Gallery_3']) || !empty($imagevalue['Gallery_4']) || !empty($imagevalue['Gallery_5'])) {

                $uploadGalaryImage = array();

                if ($imagevalue['Gallery_1']) {
                    $uploadGalaryImage[] = [
                        'product_id' => $productid,
                        'photo' => $imagevalue['Gallery_1'],
                    ];
                }
                if ($imagevalue['Gallery_2']) {
                    $uploadGalaryImage[] = [
                        'product_id' => $productid,
                        'photo' => $imagevalue['Gallery_2'],
                    ];
                }
                if ($imagevalue['Gallery_3']) {
                    $uploadGalaryImage [] = [
                        'product_id' => $productid,
                        'photo' => $imagevalue['Gallery_3'],
                    ];
                }
                if ($imagevalue['Gallery_4']) {
                    $uploadGalaryImage[] = [
                        'product_id' => $productid,
                        'photo' => $imagevalue['Gallery_4'],
                    ];
                }
                if ($imagevalue['Gallery_5']) {
                    $uploadGalaryImage[] = [
                        'product_id' => $productid,
                        'photo' => $imagevalue['Gallery_5'],
                    ];
                }
            }
            if ($imagevalue['Variants_Name'] && $imagevalue['Variants_Images']) {
                $exp_variant = explode(",", $imagevalue['Variants_Name']);
                $exp_variantImage = explode(",", $imagevalue['Variants_Images']);

                foreach ($exp_variant as $key => $variantval) {
                    $variantname = rtrim(ltrim($variantval));
                    $pr_val = $this->db->select('id')->where(["product_id" => $productid, "name" => $variantname])->get('product_variants')->row();
                    $prvar_id = $pr_val->id;

                    $uploadGalaryVaraint[] = [
                        'product_id' => $productid,
                        'variant_id' => $prvar_id,
                        'photo' => rtrim(ltrim($exp_variantImage[$key])),
                    ];
                }

                $this->db->insert_batch('product_photos', $uploadGalaryVaraint);
            }

            if (!empty($uploadGalaryImage)) {
                $this->db->insert_batch('product_photos', $uploadGalaryImage);
            }
        }
    }

    /*     * Delete Var* */

    public function deleteVarient($id) {
        if ($this->db->delete('product_variants', array('id' => $id))) {
            $this->db->delete('warehouses_products_variants', array('option_id' => $id));

            return true;
        }
        return FALSE;
    }

    /**
     * Get Manage Barcode
     * @return type
     */
    public function getManagebarcode() {
        $data = $this->db->order_by('id', 'ASC')->get('manage_barcode')->result();
        return $data;
    }

    public function getProductStockDetails($product_id) {

        $orderby = ($this->Settings->accounting_method == 1) ? 'desc' : 'asc';

        $q = $this->db->select("`id`,`date`,`product_id`,`product_name`,`product_code`,`option_id`,`purchase_id`,`transfer_id`,`adjustment_id`,`quantity`,`quantity_received`,`quantity_balance`,`unit_quantity`,`warehouse_id`,`status`,`batch_number`,`updated_at`")
                ->where(array('product_id' => $product_id))
                ->group_start()->where('status', 'received')->or_where('status', 'partial')->or_where('status', 'returned')->group_end()
                ->order_by('date', $orderby)
                ->get('purchase_items');

        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductSaleInfo($product_id) {
        $q = $this->db->select("`product_name`, `option_id`, sum(`quantity`) quantity, sum(`unit_quantity`) unit_quantity ")
                ->where(array('product_id' => $product_id))
                ->group_by('option_id')
                ->get('sale_items');

        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /* Manage Products Batches Functions */

    public function createBatch($data) {
        $this->db->insert('sma_product_batches', $data);
        if ($this->db->affected_rows()) {
            return true;
        } else {
            return false;
        }
    }

    public function updateBatch($data, $id) {
        $this->db->where(['id' => $id])->update('sma_product_batches', $data);
        if ($this->db->affected_rows()) {
            return true;
        } else {
            return false;
        }
    }

    public function batchDetails($id) {
        return $this->db->where(['id' => $id])->get('sma_product_batches')->row();
    }

    public function deleteBatch($id) {
        $this->db->where(['id' => $id])->delete('sma_product_batches');
        if ($this->db->affected_rows()) {
            return true;
        } else {
            return false;
        }
    }

    public function getProducts() {
        return $this->db->select('id,name,code')->order_by('name', 'asc')->get('sma_products')->result();
    }

    public function getProductBatch($product_id, $option_id = 0) {

        $where = ['b.product_id' => $product_id, 'b.is_active' => 1, 'b.is_delete' => 0];

        if ($option_id) {
            $where['b.option_id'] = $option_id;
        }

        $batchNo = $this->db->select('b.*, v.name as variant_name')
                ->where($where)
                ->order_by('b.batch_no', 'desc')
                ->from('product_batches AS b')
                ->join('product_variants AS v', 'v.id=b.option_id', 'left')
                ->order_by('b.option_id')
                ->get()
                ->result();

        if ($this->db->affected_rows()) {
            $response = array();
            foreach ($batchNo as $batchva) {
                $response[$batchva->id] = $batchva;

                if ($batchva->expiry_date != '' && $batchva->expiry_date !== '0000-00-00') {
                    $expiry_strtotime = strtotime($batchva->expiry_date);
                    $response[$batchva->id]->expiry = date("d-m-Y", $expiry_strtotime);
                } else {
                    $response[$batchva->id]->expiry = '';
                }
            }
            return $response;
        } else {
            return false;
        }
    }

    public function getProductVariantsBatch($product_id) {

        $where = ['product_id' => $product_id, 'is_active' => 1, 'is_delete' => 0];

        $batchNo = $this->db->where($where)
                ->order_by('batch_no', 'desc')
                ->get('product_batches')
                ->result();

        if ($this->db->affected_rows() && count($batchNo) > 0) {
            $response = array();
            foreach ($batchNo as $batchva) {

                if ($batchva->expiry_date != '' && $batchva->expiry_date !== '0000-00-00') {
                    $expiry_strtotime = strtotime($batchva->expiry_date);
                    $batchva->expiry = date("d-m-Y", $expiry_strtotime);
                } else {
                    $batchva->expiry = '';
                }

                $response[$batchva->option_id][$batchva->id] = $batchva;
            }
            return $response;
        } else {
            return false;
        }
    }

    public function getProductBatchById($batch_id) {

        $batchNo = $this->db->where(['id' => $batch_id])
                ->get('product_batches')
                ->result();

        if ($this->db->affected_rows()) {
            return $batchNo;
        } else {
            return false;
        }
    }

    // Get batch no list and qty
    public function getProductBatchWithQty($product_id) {
        $batchNo = $this->db->select('sma_product_batches.id, sma_product_batches.batch_no, sma_product_batches.cost, sma_product_batches.price, sma_product_batches.mrp, IF(sum(`sma_purchase_items`.`quantity_balance`),sum(`sma_purchase_items`.`quantity_balance`) ,0) as qty ')
                        ->join('sma_purchase_items', 'sma_purchase_items.batch_number = sma_product_batches.batch_no', 'left')
                        ->where(['sma_product_batches.product_id' => $product_id])
                        ->group_by('sma_product_batches.batch_no')
                        ->order_by('sma_product_batches.id', 'ASC')->get('sma_product_batches')->result();
        if ($this->db->affected_rows()) {
            $response = array();
            foreach ($batchNo as $batchva) {
                if (!$this->Settings->overselling) {
                    if ($batchva->qty > 0) {
                        $response[$batchva->id] = $batchva;
                    }
                } else {
                    $response[$batchva->id] = $batchva;
                }
            }
            return $response;
        } else {
            return false;
        }
    }

    public function get_batch_in_used($id) {

        return TRUE;
    }

    /* End Manage Products Batches Functions */

    public function getProductOptionByID($id) {
        $q = $this->db->get_where('product_variants', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /*     * ******************************************************************
     * Purchases Notification
     * ****************************************************************** */

    /**
     * New Master pos data store
     * @param type $unitData
     * @return type
     */
    public function getUnitCheck($unitData) {
        $getData = $this->db->select('id')->where(['code' => $unitData['code']])->get('units')->row();
        if ($this->db->affected_rows()) {
            return $getData->id;
        } else {

            $unitData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert('units', $unitData);
            return $this->db->insert_id();
        }
    }

    /**
     * New Master pos data store category Check
     * @param type $unitData
     * @return type
     */
    public function getCategoryCheck($category) {

        $getData = $this->db->select('id')->where(['code' => $category['code']])->get('categories')->row();
        if ($this->db->affected_rows()) {
            return $getData->id;
        } else {
            $category['updated_at'] = date('Y-m-d H:i:s');

            $this->db->insert('categories', $category);
            return $this->db->insert_id();
        }
    }

    /**
     * New Master pos data store Brand Check
     * @param type $unitData
     * @return type
     */
    public function getBrandCheck($brands) {
        $getData = $this->db->select('id')->where(['code' => $brands['code']])->get('brands')->row();
        if ($this->db->affected_rows()) {
            return $getData->id;
        } else {
            $brands['updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert('brands', $brands);
            return $this->db->insert_id();
        }
    }

    /**
     * Store New Product for master pos
     * @param type $data
     * @return type
     */
    public function store_newproduct($data) {
        $getData = $this->db->select('id')->where(['code' => $data['code']])->get('products')->row();
        if ($this->db->affected_rows()) {
            return $getData->id;
        } else {
            $this->db->insert('products', $data);
            return $this->db->insert_id();
        }
    }

    /**
     * Store New Product Variants for master pos
     * @param type $options
     * @return type
     */
    public function store_newOption($options) {
        $getData = $this->db->select('id')->where(['product_id' => $options['product_id'], 'name' => $options['name']])->get('product_variants')->row();
        if ($this->db->affected_rows()) {
            return TRUE;
        } else {
            $this->db->insert('product_variants', $options);
            return ($this->db->affected_rows()) ? TRUE : FALSE;
        }
    }

    /**
     * Check product codes
     * @param type $barcode
     * @return type
     */
    public function getProductCode($barcode) {
        $getProductDetails = $this->db->select('*')->where(['code' => $barcode])->get('products')->row();
        return $getProductDetails;
    }

    /*     * ******************************************************************
     * End Purchases Notification
     * ****************************************************************** */

    /**
     * Bulk Product Mark on Favourite
     * @param type $productIds
     * @return type
     */
    public function productsMarkFavourite($productIds) {
        $this->db->where_in('id', $productIds)->update('products', ['is_featured' => 1]);
        return ($this->db->affected_rows()) ? TRUE : FALSE;
    }

    /**
     * 
     * @return type
     */
    public function poscategory() {
        $category = $this->db->select('id')->where(['code' => 'POSCOMBO'])->get('categories')->row();
        if ($this->db->affected_rows()) {
            return $category->id;
        } else {
            $feild = [
                'code' => 'POSCOMBO',
                'name' => 'POS Combo',
            ];
            $this->db->insert('categories', $feild);
            return $this->db->insert_id();
        }
    }

    public function getFilterProducts($filter = null, $limit = null, $page = 1) {

        if ($filter == null) {
            return false;
        }


        $selectFields = 'p.id, p.code, p.name, p.image, p.price, p.mrp, p.primary_variant, p.in_eshop, p.eshop_price, p.eshop_name, p.is_active, ';
        $selectFields .= 'pv.id as variant_id, pv.name as variant_name, pv.price as variant_price, pv.unit_quantity as variant_unit_quantity, pv.eshop_name as variant_eshop_name, '
                . 'pv.eshop_mrp as variant_eshop_mrp,  pv.eshop_price as variant_eshop_price ';

        $this->db->select($selectFields);
        $this->db->from('products AS p');
        $this->db->join('product_variants AS pv', 'p.id = pv.product_id', 'left');

        if ((bool) $filter['category_id']) {
            $this->db->where(['p.category_id' => $filter['category_id']]);
        }

        if ((bool) $filter['subcategory_id']) {
            $this->db->where(['p.subcategory_id' => $filter['subcategory_id']]);
        }

        $result = $this->db->get()->result();

        if (count($result)) {
            foreach ($result as $key => $product) {

                $data[$product->id]['id'] = $product->id;
                $data[$product->id]['name'] = $product->name;
                $data[$product->id]['image'] = $product->image;
                $data[$product->id]['mrp'] = $product->mrp;
                $data[$product->id]['eshop_price'] = $product->eshop_price;
                $data[$product->id]['eshop_name'] = $product->eshop_name;
                $data[$product->id]['in_eshop'] = $product->in_eshop;

                if ($product->variant_id) {
                    $data[$product->id]['primary_variant'] = $product->primary_variant;
                    $data[$product->id]['varants'][] = [
                        'variant_id' => $product->variant_id,
                        'variant_name' => $product->variant_name,
                        'variant_price' => $product->variant_price,
                        'variant_unit_quantity' => $product->variant_unit_quantity,
                        'variant_eshop_name' => $product->variant_eshop_name,
                        'variant_eshop_mrp' => $product->variant_eshop_mrp,
                        'variant_eshop_price' => $product->variant_eshop_price,
                    ];
                } else {
                    $data[$product->id]['varants'] = null;
                }
            }

            return $data;
        }

        return false;
    }
    public function getCategoryById($id)
    {
        $q = $this->db->get_where('categories', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }
    public function getCategoryByProducts($category_id)
    {
        $q = $this->db->get_where('products', array('category_id' => $category_id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

}
