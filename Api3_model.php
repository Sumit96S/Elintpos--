<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Api3_model extends CI_Model {

    private $sales;
    public $select_fields;
    private $offline_pos_version;

    public function __construct() {
        parent::__construct();

        $this->sales = []; 
        
        $this->offline_pos_version = '5.00';
    }
    
    public function setOfflinePosVersion($offline_pos_version='5.00') {
        $this->offline_pos_version = $offline_pos_version;
    }

    public function isOfflinePosSalesItemsReffExist($sale_reff, $item_reff) {

        $q = $this->db->select('id')
                ->where('offlinepos_sale_reff', $sale_reff)
                ->where('offlinepos_saleitem_reff', $item_reff)
                ->get('sale_items');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function isOfflinePosSalesItemsTaxExist($tax_sale_id, $tax_item_id, $tax_attr_code) {

        $q = $this->db->select('id')
                ->where('item_id', $tax_item_id)
                ->where('sale_id', $tax_sale_id)
                ->where('attr_code', $tax_attr_code)
                ->get('sales_items_tax');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function isOfflinePosSalesItemsCostingExist($cost_sale_id, $cost_sale_item_id, $cost_product_id) {

        $q = $this->db->select('id')
                ->where('sale_item_id', $cost_sale_item_id)
                ->where('sale_id', $cost_sale_id)
                ->where('product_id', $cost_product_id)
                ->get('costing');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function isOfflinePosSalesPaymentExist($pay_sale_id, $pay_reference_no) {

        $q = $this->db->select('id')
                ->where('reference_no', $pay_reference_no)
                ->where('sale_id', $pay_sale_id)
                ->get('payments');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function isOfflinePosSalesDeliveryExist($delvery_sale_id, $delvery_do_reference_no) {

        $q = $this->db->select('id')
                ->where('sale_id', $delvery_sale_id)
                ->where('do_reference_no', $delvery_do_reference_no)
                ->get('deliveries');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function addOfflineCustomers($customers) {

        if (count($customers) && !empty($customers)) {
            $rdata = [];
            foreach ($customers as $key => $customer) {
                
                $CustomerId = $customer->id;
                unset($customer->is_synced);
                unset($customer->online_reff_id);

                $salesCustomerExist = $this->isOfflineCustomerExist($customer->id);
                if (!$salesCustomerExist) {
                    $salesCustomerMobileExist = $this->isOfflineCustomerMobileExist($customer->phone);
                    if (!$salesCustomerMobileExist) {
                        //$data = (array)$customer;

                        $data = array('name' => $customer->name, 'phone' => $customer->phone, 'offline_reff_id' => $customer->id, 'group_name' => 'customer');

                        if ($this->db->insert('companies', $data)) {
                            $rdata[$customer->id] = $LastCustomerId = $this->db->insert_id();
                        } else {
                            $rdata[$customer->id] = 'false';
                        }
                    } else {
                        $rdata[$customer->id] = $salesCustomerExist;
                    }
                } else {
                    $rdata[$customer->id] = $salesCustomerExist;
                }
            }//end foreach.  
            return $rdata;
        }
        return FALSE;
    }

    public function isOfflineCustomerExist($customer_id) {

        $q = $this->db->select('id')
                ->where('offline_reff_id', $customer_id)
                ->get('companies');

        if ($q->num_rows() > 0) {

            $row = $q->row();
            return $row->id;
        }
    }

    public function isOfflineCustomerMobileExist($customer_phone) {

        $q = $this->db->select('id')
                ->where('phone', $customer_phone)
                ->get('companies');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function getOfflinePosSalesReffIds($sale_reff) {

        $q = $this->db->select('id,offlinepos_sale_reff')->where_in('offlinepos_sale_reff', $sale_reff)->get('sales');

        if ($q->num_rows() > 0) {
            foreach ($q->result() as $reff) {
                $salereff[$reff->offlinepos_sale_reff] = $reff->id;
            }
            return $salereff;
        }
        return FALSE;
    }

    public function getOfflinePosSalesItemsReffIds($sale_reff) {

        $q = $this->db->select('id,offlinepos_saleitem_reff')->where_in('offlinepos_sale_reff', $sale_reff)->get('sale_items');

        if ($q->num_rows() > 0) {
            foreach ($q->result() as $reff) {
                $saleitemreff[$reff->offlinepos_saleitem_reff] = $reff->id;
            }
            return $saleitemreff;
        }
        return FALSE;
    }

    public function isOfflinePosSalesReffExist($offlinepos_sale_reff) {

        return $this->getOfflineposSaleIdByReff($offlinepos_sale_reff);
    }

    public function getOfflineposSaleIdByReff($sale_reff) {

        $q = $this->db->select('id')->where('offlinepos_sale_reff', $sale_reff)->get('sales');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->id;
        }
        return FALSE;
    }

    public function addOfflineSales($sales) {

        if (!empty($sales)) {
            $saleReff = [];
            foreach ($sales as $offlinesaleid => $sale) {
                unset($sale->id);
                $data = (array) $sale;
                
                $SalesReffExist = $this->isOfflinePosSalesReffExist($sale->offlinepos_sale_reff);
               
                if (!$SalesReffExist) {

                    if ($sale->sale_status == 'returned') {

                        $reffSales = $this->getSalebyOfflineSaleRefferenceNo($sale->offlinepos_sale_reff);
                
                        if ($reffSales->return_id) {
                            $saleReff[$sale->offlinepos_sale_reff] = $reffSales->return_id;
                        } else {
                            $data['sale_id'] = $reffSales->id;
                            $data['reference_no'] = $reffSales->reference_no;
                            $data['return_sale_ref'] = $this->site->getReference('re');

                            if ($this->db->insert('sales', $data)) {
                                $insert_id = $this->db->insert_id();
                                $saleReff[$sale->offlinepos_sale_reff] = $insert_id;
                                $returnData = array('return_sale_ref' => $data['return_sale_ref'], 'return_id' => $insert_id);
                                $this->updateReffSales($returnData, $reffSales->id);

                                if ($this->site->getReference('re') == $data['return_sale_ref']) {
                                    $this->site->updateReference('re');
                                }
                            } else {
                                $saleReff[$sale->offlinepos_sale_reff] = $this->db->_error_message();
                            }
                        }                
                    } else {

                        if ($this->db->insert('sales', $data)) {
                            // $saleReff[$sale->offlinepos_sale_reff] = $this->db->insert_id();
                            $LastSaleId = $this->db->insert_id();
                            $saleReff[$sale->offlinepos_sale_reff] = $LastSaleId;

                            $todaydate = date('Y-m-d');
                            $invoice_no = $this->sma->invoice_format($LastSaleId, $todaydate);
                            
                            $updateData['invoice_no'] = $invoice_no;
                            
                            $CustomerId = $this->isOfflineCustomerExist($sale->customer_id);

                            if ($CustomerId) {
                                $updateData['customer_id'] = $CustomerId;                                
                            }

                            $this->db->where('id', $LastSaleId)->update("sales", $updateData);
                            
                        } else {
                            $saleReff[$sale->offlinepos_sale_reff] = $this->db->_error_message();
                        }
                    }
                }//end if.
                else {
                    $saleReff[$sale->offlinepos_sale_reff] = $SalesReffExist;
                }
            }//end foreach.

            return $saleReff;
        }
        return FALSE;
    }

    public function getSalebyOfflineSaleRefferenceNo($offlinepos_sale_reff) {

        $q = $this->db->where(['offlinepos_sale_reff'=>$offlinepos_sale_reff])->where("sale_status != returned")->get('sales');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0];
        }
    }
    
    public function getSalebyRefferenceNo($sale_reference_no) {

        $q = $this->db->where('reference_no', $sale_reference_no)->get('sales');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0];
        }
    }

    public function updateReffSales(array $returnData, $reffsaleid) {

        $this->db->update('sales', $returnData, array('id' => $reffsaleid));
    }

    //17-06-2019

    public function synQty($SaleData) {
        foreach ($SaleData as $key => $sale_id) {
            $this->site->syncQuantity($sale_id);
        }
    }

    public function synProductVarientQty($variant_id, $warehouse_id, $quantity, $product_id = NULL) {

        $Sql = "UPDATE `sma_product_variants` SET quantity = quantity-'$quantity' WHERE id='$variant_id'";
        $this->db->query($Sql);

        if ($wpvData = $this->getWarehouseProductsVariants($variant_id, $warehouse_id, $product_id)) {
            $Ssql = "UPDATE `sma_warehouses_products_variants` SET quantity= quantity-'$quantity' WHERE option_id='$variant_id' and warehouse_id='$warehouse_id' and `product_id`= '$product_id' ";
            if ($this->db->query($Ssql)) {
                return $wpvq = $wpvData[0]->quantity - $quantity;
            }
        } else {
            $this->db->insert('sma_warehouses_products_variants', array('quantity' => 0, 'option_id' => $variant_id, 'warehouse_id' => $warehouse_id, 'product_id' => $product_id));

            return 0;
        }
    }

    public function getWarehouseProductsVariants($option_id, $warehouse_id = NULL, $product_id = NULL) {
        if ($warehouse_id) {
            $this->db->where('warehouse_id', $warehouse_id);
        }
        if ($product_id) {
            $this->db->where('product_id', $product_id);
        }
        $q = $this->db->get_where('warehouses_products_variants', array('option_id' => $option_id));
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getProductsVariants($option_id, $product_id = NULL) {

        if ($product_id) {
            $this->db->where('product_id', $product_id);
        }
        $q = $this->db->get_where('sma_product_variants', array('id' => $option_id));
        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row;
        }
        return FALSE;
    }

    //17-06-2019
    public function addOfflineSalesItems($sales_items, $saleReff) {

        if (!empty($sales_items)) {

            $saleItemReff = $stock = [];
            foreach ($sales_items as $key => $item) {
                unset($item->id);
                $item->sale_id = $saleReff[$item->offlinepos_sale_reff];
                $data = (array) $item;
                $itemsReffExist = $this->isOfflinePosSalesItemsReffExist($item->offlinepos_sale_reff, $item->offlinepos_saleitem_reff);
                if (!$itemsReffExist) {
                    if ($this->db->insert('sale_items', $data)) {
                        $saleItemReff[$item->offlinepos_saleitem_reff] = $this->db->insert_id();
                        //Update Product Stocks
                        if ($item->quantity != '' && $item->product_id) {
                            $stock['products'][$item->product_id] = $this->updateOnlineStocks($item->product_id, $item->quantity);
                            $stock['warehouses'][$item->warehouse_id][$item->product_id] = $this->updateOnlineStocks($item->product_id, $item->quantity, $item->warehouse_id);
                            $stock['purchase_items'][$item->product_id] = $this->updatePurchaseBalance($item->product_id, $item->quantity, $item->warehouse_id, $item->option_id);
                        }
                        if ($item->option_id) {
                            $wpvq = $this->synProductVarientQty($item->option_id, $item->warehouse_id, $item->quantity, $item->product_id);

                            $pvData = $this->getProductsVariants($item->option_id);

                            $stock['products_varient'][$item->option_id] = $pvData->quantity;
                            $stock['warehouses_varient'][$item->warehouse_id][$item->product_id][$item->option_id] = $wpvq;
                        }
                    } else {
                        $saleItemReff[$item->offlinepos_saleitem_reff] = $this->db->_error_message();
                    }
                }//end if.
                else {
                    //Get Product Stocks Count
                    if ($item->warehouse_id != '' && $item->product_id != '') {
                        $stock['products'][$item->product_id] = $this->get_products_stock_count($item->product_id);
                        $stock['warehouses'][$item->warehouse_id][$item->product_id] = $this->get_warehouses_products_stock_count($item->warehouse_id, $item->product_id);

                        if ($item->option_id) {
                            $wpvq = $this->getWarehouseProductsVariants($item->option_id, $item->warehouse_id, $item->product_id);

                            $pvData = $this->getProductsVariants($item->option_id);

                            $stock['products_varient'][$item->option_id] = $pvData->quantity;
                            $stock['warehouses_varient'][$item->warehouse_id][$item->product_id][$item->option_id] = $wpvq[0]->quantity;
                        }
                    }



                    $saleItemReff[$item->offlinepos_saleitem_reff] = $itemsReffExist;
                }
            }//end foreach.

            $sales['items'] = $saleItemReff;
            $sales['stocks'] = $stock;

            return $sales;
        }
        return FALSE;
    }

    public function updatePurchaseBalance($pid, $quantity, $warehouse_id, $option_id = null) {

        // $purProd = $this->get_purchase_products_balance($warehouse, $pid, $option);
        $pis = $this->getPurchasedItems($pid, $warehouse_id, $option_id);

        if (is_array($pis)) {
            foreach ($pis as $pi) {
                if (!empty($pi) && $pi->quantity_balance >= $quantity && $pi->quantity_balance > 0) {
                    $pur_id = $pi->id;
                    $p2 = $this->db->query("UPDATE `sma_purchase_items` SET `quantity_balance` = `quantity_balance` - ($quantity) WHERE `id` = '$pur_id' ");
                    $pis[$pur_id]->quantity_balance = $pi->quantity_balance - $quantity;
                    return $pis;
                    break;
                }
            }
        }
        return false;
    }

    public function getPurchasedItems($product_id, $warehouse_id, $option_id = NULL) {
        $orderby = ($this->Settings->accounting_method == 1) ? 'desc' : 'asc';

        $this->db->select('id,purchase_id,product_id,product_code,product_name,option_id,warehouse_id, quantity, quantity_balance, net_unit_cost, unit_cost, item_tax,tax_rate_id,tax,tax_method,discount,item_discount,subtotal,date,status,real_unit_cost,quantity_received,product_unit_id,product_unit_code,unit_quantity,hsn_code');
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
                $data[$row->id] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function updateOnlineStocks($pid, $qty, $warehouse = '') {

        if ($warehouse) {
            $q2 = $this->db->query("UPDATE `sma_warehouses_products` SET `quantity` = `quantity` - ($qty) WHERE `product_id` = '$pid' and `warehouse_id` = '$warehouse'");
            $count = ($q2) ? $this->get_warehouses_products_stock_count($warehouse, $pid) : FALSE;
        } else {
            $q1 = $this->db->query("UPDATE `sma_products` SET `quantity` = `quantity` - ($qty) WHERE `id` = '$pid'");
            $count = ($q1) ? $this->get_products_stock_count($pid) : FALSE;
        }
        return $count;
    }

    public function addOfflineSalesItemsTaxes($taxes, $saleReff, $salesItemsReff) {

        if (!empty($taxes)) {

            foreach ($taxes as $key => $tax) {

                $tax->item_id = $salesItemsReff[$tax->offlinepos_saleitem_reff];
                $tax->sale_id = $saleReff[$tax->offlinepos_sale_reff];

                if (!$tax->item_id || !$tax->sale_id) {
                    continue;
                }
                $id = $tax->id;
                unset($tax->id);
                unset($tax->offlinepos_sale_reff);
                unset($tax->offlinepos_saleitem_reff);

                $taxItemExist = $this->isOfflinePosSalesItemsTaxExist($tax->sale_id, $tax->item_id, $tax->attr_code);
                if (!$taxItemExist) {
                    $data = (array) $tax;
                    if ($this->db->insert('sales_items_tax', $data)) {
                        $rdata[$id] = $this->db->insert_id();
                    } else {
                        $rdata[$id] = 'false';
                    }
                } else {
                    $rdata[$id] = $taxItemExist;
                }
            }//end foreach.  

            return $rdata;
        }
        return FALSE;
    }

    public function addOfflineSalesItemsCosting($costings, $saleReff, $salesItemsReff) {

        if (!empty($costings)) {
            foreach ($costings as $key => $cost) {

                $cost->sale_item_id = $salesItemsReff[$cost->offlinepos_saleitem_reff];
                $cost->sale_id = $saleReff[$cost->offlinepos_sale_reff];

                if (!$cost->sale_item_id || !$cost->sale_id) {
                    continue;
                }
                $id = $cost->id;
                unset($cost->id);
                unset($cost->offlinepos_sale_reff);
                unset($cost->offlinepos_saleitem_reff);

                $saleItemCosting = $this->isOfflinePosSalesItemsCostingExist($cost->sale_id, $cost->sale_item_id, $cost->product_id);
                if (!$saleItemCosting) {
                    $data = (array) $cost;
                    if ($this->db->insert('costing', $data)) {
                        $rdata[$id] = $this->db->insert_id();
                    } else {
                        $rdata[$id] = 'false';
                    }
                }//end if
                else {
                    $rdata[$id] = $saleItemCosting;
                }
            }//end foreach. 
            return $rdata;
        }//end if
        return FALSE;
    }

    public function addOfflineSalesPayment($payments, $saleReff) {

        if (count($payments) && !empty($payments)) {
            foreach ($payments as $key => $pay) {

                $pay->sale_id = ($saleReff[$pay->offlinepos_sale_reff]) ? $saleReff[$pay->offlinepos_sale_reff] : $this->getOfflineposSaleIdByReff($pay->offlinepos_sale_reff);

                if (!$pay->sale_id) {
                    continue;
                }

                $id = $pay->id;
                unset($pay->id);
                unset($pay->offlinepos_sale_reff);

                $salesPaymentExist = $this->isOfflinePosSalesPaymentExist($pay->sale_id, $pay->reference_no);
                if (!$salesPaymentExist) {
                    $data = (array) $pay;
                    if ($this->db->insert('payments', $data)) {
                        $rdata[$id] = $this->db->insert_id();
                    } else {
                        $rdata[$id] = 'false';
                    }
                } else {
                    $rdata[$id] = $salesPaymentExist;
                }
            }//end foreach.  
            return $rdata;
        }
        return FALSE;
    }

    public function addOfflineSalesDeliveries($deliveries, $saleReff) {

        if (count($deliveries) && !empty($deliveries)) {
            $rdata = [];
            foreach ($deliveries as $key => $delvery) {

                $delvery->sale_id = ($saleReff[$delvery->offlinepos_sale_reff]) ? $saleReff[$delvery->offlinepos_sale_reff] : $this->getOfflineposSaleIdByReff($delvery->offlinepos_sale_reff);

                if (!$delvery->sale_id) {
                    continue;
                }

                $id = $delvery->id;
                unset($delvery->id);
                unset($delvery->offlinepos_sale_reff);

                $salesDeliveryExist = $this->isOfflinePosSalesDeliveryExist($delvery->sale_id, $delvery->do_reference_no);
                if (!$salesDeliveryExist) {
                    $data = (array) $delvery;
                    if ($this->db->insert('deliveries', $data)) {
                        $rdata[$id] = $this->db->insert_id();
                    } else {
                        $rdata[$id] = 'false';
                    }
                } else {
                    $rdata[$id] = $salesDeliveryExist;
                }
            }//end foreach.  
            return $rdata;
        }
        return FALSE;
    }

    public function get_products_stock_count($pid) {

        $q = $this->db->select('quantity')->get_where('products', ['id' => $pid]);

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->quantity;
        }
    }

    public function get_warehouses_products_stock_count($whid, $pid) {

        $q = $this->db->select('quantity')
                ->where('product_id', $pid)
                ->where('warehouse_id', $whid)
                ->get('warehouses_products');

        if ($q->num_rows() > 0) {
            $row = $q->result();
            return $row[0]->quantity;
        }
    }

    public function get_categories() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,code,name,image,parent_id,tax_rate,is_active,updated_at";

        $q = $this->db->select($select)->get('categories');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_brands() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,code,name,image";

        $q = $this->db->select($select)->get('brands');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_companies() {

        $select_fields = "id,group_id,group_name,customer_group_id,customer_group_name,name,company,vat_no,pan_card,address,city,state,state_code,postal_code,country,phone,email,cf1,cf2,cf3,cf4,cf5,pass_key,cf6,invoice_footer,payment_term,logo,award_points,deposit_amount,price_group_id,price_group_name,password,dob,anniversary,dob_father,dob_mother,dob_child1,dob_child2,is_synced,lat,lng,gstn_no,email_verification_code,mobile_verification_code,email_is_verified,mobile_is_verified";
        
        $select = ($this->select_fields != '') ? $this->select_fields : $select_fields;
        
        $q = $this->db->select($select)->get('companies');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_contact_group() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,group_name,group_desc,group_created,group_updated";

        $q = $this->db->select($select)->get('contact_group');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_contact_group_member() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,group_id,customer_id";

        $q = $this->db->select($select)->get('sma_contact_group_member');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_products() {

        $select_fields = "id,article_code,code,name,weight,unit,cost,price,mrp,alert_quantity,image,category_id,subcategory_id,cf1,cf2,cf3,cf4,cf5,cf6,quantity,tax_rate,track_quantity,details,warehouse,barcode_symbology,file,product_details,tax_method,type,supplier1,supplier1price,supplier2,supplier2price,supplier3,supplier3price,supplier4,supplier4price,supplier5,supplier5price,promotion,promo_price,start_date,end_date,supplier1_part_no,supplier2_part_no,supplier3_part_no,supplier4_part_no,supplier5_part_no,sale_unit,purchase_unit,brand,hsn_code,is_featured,divisionid,up_items,food_type_id,up_price,updated_at,storage_type,primary_variant,is_active";
        
        $select = ($this->select_fields != '') ? $this->select_fields : $select_fields;

        $q = $this->db->select($select)->get('products');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_product_prices() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,product_id,price_group_id,price";

        $q = $this->db->select($select)->get('product_prices');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_product_variants() {
        $select = ($this->select_fields != '') ? $this->select_fields : "id,product_id,name,cost,price,quantity,unit_quantity,unit_weight,updated_at";
        $q = $this->db->select($select)->get('product_variants');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_combo_items() {
        $select = ($this->select_fields != '') ? $this->select_fields : "id,product_id,item_code,quantity,unit_price,updated_at";
        $q = $this->db->select($select)->get('combo_items');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_calendar() {
        $select = ($this->select_fields != '') ? $this->select_fields : "id,title,description,start,end,color,user_id";
        $q = $this->db->select($select)->get('calendar');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    //Master Table
    public function get_variants() {
        $select = ($this->select_fields != '') ? $this->select_fields : "id,name";
        $q = $this->db->select($select)->get('variants');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_tax_attr() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,name,code";

        $q = $this->db->select($select)->get('tax_attr');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_tax_hsncodes() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,hsn_code,tax_rate,tax_item_desc,filename";

        $q = $this->db->select($select)->get('tax_hsncodes');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_tax_rates() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,name,code,rate,type,pos_substitut_tax,is_substitutable,tax_config";

        $q = $this->db->select($select)->get('tax_rates');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_warehouses() {
        $select = ($this->select_fields != '') ? $this->select_fields : "id,code,name,address,city,state,state_code,postal_code,country,map,phone,email,price_group_id,is_active,is_deleted,is_disabled,pos_biller_id";
        $q = $this->db->select($select)->get('warehouses');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_warehouses_products() {

        $select = ($this->select_fields != '') ? $this->select_fields : "id,product_id,warehouse_id,quantity,rack,avg_cost";
        $q = $this->db->select($select)->get('warehouses_products');
        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_warehouses_products_variants() {
        $select = ( $this->select_fields != '' ) ? $this->select_fields : "id,option_id,product_id,warehouse_id,quantity,rack";
        $q = $this->db->select($select)->get('warehouses_products_variants');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_purchases() {
                
        $select_fields = "id,reference_no,date,supplier_id,supplier,warehouse_id,note,total,product_discount,order_discount_id,order_discount,total_discount,product_tax,order_tax_id,order_tax,total_tax,shipping,grand_total,paid,status,payment_status,created_by,updated_by,updated_at,attachment,payment_term,due_date,return_id,surcharge,return_purchase_ref,purchase_id,return_purchase_total,rounding,cgst,sgst,igst";
        
        $select = ( $this->select_fields != '' ) ? $this->select_fields : $select_fields;
        
        $q = $this->db->select($select)->get('purchases');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_purchase_items() {
                
        $select_items = "id,purchase_id,transfer_id,adjustment_id,product_id,product_code,product_name,option_id,net_unit_cost,quantity,warehouse_id,item_tax,tax_rate_id,tax,tax_method,discount,item_discount,expiry,subtotal,quantity_balance,date,status,unit_cost,real_unit_cost,quantity_received,supplier_part_no,purchase_item_id,product_unit_id,product_unit_code,unit_quantity,hsn_code,batch_number,gst_rate,cgst,sgst,igst";
        
        $select = ( $this->select_fields != '' ) ? $this->select_fields : $select_items;
        
        $q = $this->db->select($select)->get('purchase_items');
        
        $num = $q->num_rows();
        
        if ($num) {                       
            $data['num'] = $num;

            $data['rows'] = $q->result();
                
            return $data;
        } else {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } 
    }
    
    public function get_purchase_stocks() {
                
        $select = "`purchase_id`,`transfer_id`,`adjustment_id`,`product_id`,`product_code`,`product_name`,`option_id`, sum(`quantity`) AS quantity, `warehouse_id`, SUM(`quantity_balance`) AS quantity_balance, `status`, sum(`quantity_received`) AS quantity_received, sum(`unit_quantity`) AS unit_quantity";
        
        $q = $this->db->select($select)->where('status', 'received')->group_by("warehouse_id, product_id, option_id")->get('purchase_items');
        
        $num = $q->num_rows();
        
        if ($num) {
            
            $data['num'] = $num;

            $data['rows'] = $q->result();
                
            return $data;
            
        } else {
            
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } 
    }

    public function get_purchase_items_tax() {
        $select = ( $this->select_fields != '' ) ? $this->select_fields : "id,item_id,purchase_id,attr_code,attr_name,attr_per,tax_amount";
        $q = $this->db->select($select)->get('purchase_items_tax');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_offlinepos_system_settings() {
        //Pos Version 4.16
        $select = "setting_id as id,logo,logo2,site_name,pos_version,language,default_warehouse,accounting_method,default_currency,default_tax_rate,rows_per_page,default_tax_rate2,dateformat,item_addition,theme,product_serial,default_discount,product_discount,discount_method,tax1,tax2,overselling,restrict_user,restrict_calendar,timezone,iwidth,iheight,twidth,theight,watermark,reg_ver,allow_reg,reg_notification,auto_reg,protocol,mailpath,smtp_host,smtp_user,smtp_pass,smtp_port,smtp_crypto,corn,customer_group,default_email,mmode,bc_fix,auto_detect_barcode,captcha,reference_format,racks,attributes,product_expiry,decimals,qty_decimals,decimals_sep,thousands_sep,invoice_view,tax_classification_view,default_biller,rtl,each_spent,ca_point,each_sale,sa_point,update,sac,display_all_products,display_symbol,symbol,remove_expired,barcode_separator,set_focus,price_group,barcode_img,disable_editing,update_cost,pos_type,default_printer,auto_acceptance,invoice_view_purchase,invoice_product_image,tax_classification_view__purchase,sales_image,quotation_image,purchase_image,sms_sender,sms_promotional_header,api_privatekey,api_access,add_tax_in_cart_unit_price,add_discount_in_cart_unit_price,offlinepos_warehouse,offlinepos_biller,show_quotation_unit_price,show_sales_unit_price,show_purchase_unit_cost,synch_reward_points,synch_customers,display_zero_sale_for_product_report,sales_order_discount,purchase_order_discount,product_external_platform,invoice_format,invoice_length,financial_type,order_receipt_label,sale_multiple_return_edit,reports_send_on_email,each_redeem,redeem_point,award_point_by_percent,barcode_align,barcode_type,product_batch_setting,product_batch_required,sale_loose_products_with_variants,product_weight,send_sales_excel,barcode_separator_weight,barcode_a4_page_dynamic,synced_data_sales";
        //Pos Version 4.20
        $select .= ",modify_qty_add_products";        
        //Pos Version 5.00
        if((float)$this->offline_pos_version >= 5.00){
            $select .= ', barcode_scan_camera';
        }
        
        $q = $this->db->select($select)->get('settings');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            return $data;
        }
    }

    public function get_offlinepos_pos_settings() {

        //Pos Version 4.20
        $select = "pos_id as id,eshop_order_tax,cat_limit,pro_limit,default_category,default_customer,default_biller,display_time,cf_title1,cf_title2,cf_value1,cf_value2,receipt_printer,cash_drawer_codes,focus_add_item,add_manual_product,customer_selection,add_customer,toggle_category_slider,toggle_subcategory_slider,cancel_sale,suspend_sale,print_items_list,finalize_sale,today_sale,open_hold_bills,close_register,submit_and_print,other,keyboard,pos_printers,java_applet,product_button_color,tooltips,paypal_pro,stripe,rounding,char_per_line,pin_code,after_sale_page,item_order,authorize,toggle_brands_slider,instamojo,ccavenue,paytm,paytm_opt,razorpay,credit_card,debit_card,gift_card,neft,google_pay,swiggy,zomato,ubereats,magicpin,UPI_QRCODE,complimentary,paynear,payumoney,award_point,pos_screen_products,pos_theme,invoice_auto_sms,offers_status,active_offer_category,recent_pos_limit,display_token,display_category,pos_amount,display_seller,product_variant_popup,use_product_price,change_qty_as_per_user_price,order_receipt,print_all_category,categorys";
        
        if((float)$this->offline_pos_version >= 5.00){
            $select .= ', add_deposit_btn_show, kot_save, bill_print, suspend_popup_checkout, table_shift, combo_add_pos';
        }
        
        
        $q = $this->db->select($select)->get('pos_settings');

        if (!$q) {
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                $row = $q->result();

                $row[0]->authorize = 0;
                $row[0]->instamojo = 0;
                $row[0]->ccavenue = 0;
                $row[0]->paytm = 0;
                $row[0]->paynear = 0;
                $row[0]->payumoney = 0;

                $data['rows'] = $row;
            }
            return $data;
        }
    }

    public function getTableData($tablename, $selects = '') {

        if (!empty($tablename)) {

            $this->select_fields = ($selects != '') ? $selects : '';

            switch ($tablename) {

                case 'sma_settings':

                    $data = $this->get_offlinepos_system_settings();

                    $data['rows'][0]->setting_id = 1;
                    $data['rows'][0]->default_warehouse = ($data['rows'][0]->offlinepos_warehouse) ? $data['rows'][0]->offlinepos_warehouse : $data['rows'][0]->default_warehouse;
                    $data['rows'][0]->default_biller = ($data['rows'][0]->offlinepos_biller) ? $data['rows'][0]->offlinepos_biller : $data['rows'][0]->default_biller;
                    unset($data['rows'][0]->id);
                    unset($data['rows'][0]->offlinepos_warehouse);
                    unset($data['rows'][0]->offlinepos_biller);
                    return $data;
                    break;

                case 'sma_pos_settings':
                    $data = $this->get_offlinepos_pos_settings();
                    $data['rows'][0]->pos_id = 1;
                    unset($data['rows'][0]->id);
                    return $data;
                    break;

                case 'sma_warehouses_products_variants':
                    $data = $this->get_warehouses_products_variants();
                    break;

                case 'sma_warehouses_products':
                    $data = $this->get_warehouses_products();
                    break;

                case 'sma_warehouses':
                    $data = $this->get_warehouses();
                    break;

                case 'sma_tax_rates':
                    $data = $this->get_tax_rates();
                    break;

                case 'sma_tax_hsncodes':
                    $data = $this->get_tax_hsncodes();
                    break;

                case 'sma_tax_attr':
                    $data = $this->get_tax_attr();
                    break;

                case 'sma_variants':
                    $data = $this->get_variants();
                    break;

                case 'sma_product_variants':
                    $data = $this->get_product_variants();
                    break;

                case 'sma_product_prices':
                    $data = $this->get_product_prices();
                    break;

                case 'sma_products':
                    $data = $this->get_products();
                    break;

                case 'sma_companies':
                    $data = $this->get_companies();
                    break;

                case 'sma_contact_group':
                    $data = $this->get_contact_group();
                    break;

                case 'sma_contact_group_member':
                    $data = $this->get_contact_group_member();
                    break;

                case 'sma_brands':
                    $data = $this->get_brands();
                    break;

                case 'sma_categories':
                    $data = $this->get_categories();
                    break;

                case 'sma_combo_items':
                    $data = $this->get_combo_items();
                    break;

                case 'sma_calendar':
                    $data = $this->get_calendar();
                    break;


                default:
                    $this->select_fields = '';
                    $data = $this->fetchTableData($tablename);
                    break;
            }
            $this->select_fields = '';
            return $data;
        }

        return FALSE;
    }

    public function fetchTableData($tablename) {

        $tableFields['sma_addresses'] = 'id,company_id,company_name,address_name,line1,line2,city,postal_code,state,country,phone,updated_at,email_id,state_code';
        $tableFields['sma_contact_template'] = 'id,template_name,template_type,is_default,template_content,template_created,template_updated,event_type,admin_note,attachment';
        $tableFields['sma_currencies'] = 'id,code,name,rate,auto_update';
        $tableFields['sma_custemail'] = 'id,sender,subject,message,send_date,customer_list';
        
        $tableFields['sma_customer_groups'] = 'id,name,percent';
        
        
        $tableFields['sma_country_master'] = 'id,name';
        $tableFields['sma_date_format'] = 'id,js,php,sql';
        $tableFields['sma_calendar'] = 'id,title,description,start,end,color,user_id';
        $tableFields['sma_division'] = 'id,name,code,date';
        $tableFields['sma_gift_cards'] = 'id,date,card_no,value,customer_id,customer,balance,expiry,created_by';
        $tableFields['sma_gift_card_topups'] = 'id,date,card_id,amount,created_by';
        $tableFields['sma_groups'] = 'id,name,description';

        $tableFields['sma_offers'] = 'id,offer_category_id,offer_keyword,offer_name,offer_invoice_descriptions,offer_start_date,offer_end_date,offer_start_time,offer_end_time,offer_on_days,offer_on_warehouses,offer_on_products,offer_items_condition,offer_on_products_quantity,offer_on_products_amount,offer_on_category,offer_on_category_quantity,offer_on_category_amount,offer_on_brands,offer_on_invoice_amount,offer_free_products,offer_free_products_quantity,offer_discount_rate,offer_amount_including_tax,is_active,is_delete,created_at,updated_at';
        $tableFields['sma_offers_categories'] = 'id,offer_keyword,offer_category,is_active,is_delete';

        $tableFields['sma_permissions'] = 'id,group_id,products-index,products-add,products-edit,products-delete,products-cost,products-price,products-import,products-batches,products-add_batch,products-edit_batch,products-delete_batch,quotes-index,quotes-add,quotes-edit,quotes-pdf,quotes-email,quotes-delete,quotes-date,sales-index,sales-add,sales-edit,sales-pdf,sales-email,sales-delete,sales-date,sales-delete-suspended,purchases-index,purchases-add,purchases-edit,purchases-pdf,purchases-email,purchases-delete,purchases-date,purchase_add_csv,transfers-index,transfers-add,transfers-edit,transfers-pdf,transfers-email,transfers-delete,transfers-date,transfers_add_csv,transfers-add_request,transfers-edit_request,transfers-cancel_request,transfers-delete_request,transfers-request_change_status,transfers-request,transfer_status_completed,transfer_status_request,transfer_status_sent,customers-index,customers-add,customers-edit,customers-delete,suppliers-index,suppliers-add,suppliers-edit,suppliers-delete,sales-deliveries,sales-add_delivery,sales-edit_delivery,sales-delete_delivery,sales-email_delivery,sales-pdf_delivery,sales-gift_cards,sales-add_gift_card,sales-edit_gift_card,sales-delete_gift_card,sales_add_csv,all_sale_lists,pos-index,sales-return_sales,reports-index,reports-warehouse_stock,reports-quantity_alerts,reports-expiry_alerts,reports-products,reports-daily_sales,reports-monthly_sales,reports-sales,reports-payments,reports-purchases,report_purchase_gst,reports-profit_loss,reports-customers,reports-suppliers,reports-staff,reports-register,sales-payments,purchases-payments,purchases-expenses,products-adjustments,bulk_actions,customers-deposits,customers-delete_deposit,products-barcode,purchases-return_purchases,reports-expenses,reports-daily_purchases,reports-warehouse_sales_report,reports-monthly_purchases,crm_portal,products-stock_count,edit_price,printer-setting,default-printer,cart-price_edit,cart-unit_view,cart-show_bill_btn,pos-show-order-btn,offlinepos-synchronization,offline-sales,eshop_sales-sales,sales-challans,sales-add_challans,orders-eshop_order,orders-order_items,orders-order_items_stocks';

        $tableFields['sma_price_groups'] = 'id,name';
        $tableFields['sma_printer_bill'] = 'id,name,width,f_column,l_column,column_id_str,column_name_str,data,crop_product_name,is_deleted,show_invoice_logo,show_sr_no,show_tin,font_size,tax_classification_view,show_customer_info,show_barcode_qrcode,show_award_point,show_order_cf,append_taxval_in_productname,show_combo_products_list,append_product_code_in_name,append_article_code_in_name,append_hsn_code_in_name,append_note_in_name,product_image_size,show_product_image,show_saving_amount,show_kot_tokan,show_offer_description,kot_printing_combo_product,kot_printing_category_name,kot_print_site_name,kot_print_customer_name,kot_category_font_size,kot_product_name,kot_sub_product_name,sale_refe_no,table_no,qty_bold,product_name_bold,ascending_order_product_list,logo_position,sales_person';
                
        $tableFields['sma_printer_bill_fields'] = 'id,name,is_fixed,value,desc,format,formula,is_deleted';
        $tableFields['sma_restaurant_tables'] = 'id,name,seats';
        $tableFields['sma_state_master'] = 'id,code,name';
        $tableFields['sma_stock_counts'] = 'id,date,reference_no,warehouse_id,type,initial_file,final_file,brands,brand_names,categories,category_names,note,products,rows,differences,matches,missing,created_by,updated_by,updated_at,finalized';
        $tableFields['sma_stock_count_items'] = 'id,stock_count_id,product_id,product_code,product_name,product_variant,product_variant_id,expected,counted,cost';
        $tableFields['sma_tax_attr'] = 'id,name,code';
        $tableFields['sma_tax_rates'] = 'id,name,code,rate,type,pos_substitut_tax,is_substitutable,tax_config';
        $tableFields['sma_themes'] = 'id,theme_name,theme_label,is_active,is_delete';
        $tableFields['sma_units'] = 'id,code,name,base_unit,operator,unit_value,operation_value';
        $tableFields['sma_users'] = 'id,username,password,salt,email,active,first_name,last_name,company,phone,avatar,gender,group_id,warehouse_id,biller_id,company_id,show_cost,show_price,award_points,view_right,edit_right,allow_discount,offline_mobile_app_access,offline_windows_app_access,table_assign';

        $tableFields['sma_purchases'] = "id,reference_no,date,supplier_id,supplier,warehouse_id,note,total,product_discount,order_discount_id,order_discount,total_discount,product_tax,order_tax_id,order_tax,total_tax,shipping,grand_total,paid,status,payment_status,created_by,updated_by,updated_at,attachment,payment_term,due_date,return_id,surcharge,return_purchase_ref,purchase_id,return_purchase_total,rounding,cgst,sgst,igst";
        $tableFields['sma_purchase_items'] = 'id,purchase_id,transfer_id,adjustment_id,product_id,product_code,product_name,option_id,net_unit_cost,quantity,warehouse_id,item_tax,tax_rate_id,tax,tax_method,discount,item_discount,expiry,subtotal,quantity_balance,date,status,unit_cost,real_unit_cost,quantity_received,supplier_part_no,purchase_item_id,product_unit_id,product_unit_code,unit_quantity,hsn_code,batch_number,gst_rate,cgst,sgst,igst,updated_at';
        $tableFields['sma_purchase_items_tax'] = 'id,item_id,purchase_id,attr_code,attr_name,attr_per,tax_amount';
        
        if((float)$this->offline_pos_version >= 5.00){
            $tableFields['sma_customer_groups'] .= ', apply_as_discount';
            $tableFields['sma_printer_bill']    .= ', show_bill_no';
            $tableFields['sma_restaurant_tables'] .= ', type, table_group, price, price_group_id, price_group_name, bill_printed, parent_id';
            $tableFields['sma_price_groups'] .= ', type';
            $tableFields['sma_users'] .= ', table_assign';
            $tableFields['sma_permissions'] .= ', bill_print, checkout_button, shift_table, table_clear, pos_clear_table, checkout';
        }
        

        $selectFields = ($this->select_fields != '') ? join(',', $this->select_fields) : ( $tableFields[$tablename] != '' ? $tableFields[$tablename] : '*' );

        $selectFields = ($selectFields == '*') ? '*' : ( '`' . str_replace([' ', ','], ['', '`, `'], $selectFields) . '`') ;

        $q = $this->db->select($selectFields)->get($tablename);

        if (!$q) {
            $data['query'] = $this->db->last_query();
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['num'] = $num;

            if ($num > 0) {
                foreach (($q->result()) as $row) {
                    $data['rows'][] = $row;
                }//end foreach.
            }
            return $data;
        }
    }

    public function getShippingMethods($arr = []) {
        if (is_array($arr)) {
            $q = $this->db->get_where('eshop_shipping_methods', $arr, null);
            //echo $this->db->last_query(); 
            // exit;
            if ($q->num_rows() > 0) {
                return $q->result_array();
            }
        } else {
            $q = $this->db->get('eshop_shipping_methods');

            if ($q->num_rows() > 0) {
                return $q->result_array();
            }
        }
        return FALSE;
    }

    public function update_settings(array $data) {

        $this->db->where('setting_id', 1);
        $q = $this->db->update('settings', $data);

        if ($q) {
            $data['status'] = "SUCCESS";
        } else {
            $data['status'] = "ERROR";
        }

        return $data;
    }

    public function get_product_images_list() {

        $q = $this->db->select('image')->where_not_in('image', ['no_image.png', ''])->group_by('image')->get('products');

        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row->image;
            }
        } else {
            $data['error_no'] = 105;
            $data['error'] = 'No images found';
        }

        return $data;
    }

    public function get_users() {

        $select = "id,username,password,salt,email,created_on,active,first_name,last_name,company,phone,avatar,gender,group_id,warehouse_id,biller_id,company_id,show_cost,show_price,award_points,view_right,edit_right,allow_discount,offline_mobile_app_access,offline_windows_app_access";
        if((float)$this->offline_pos_version >= 5.00){
            $select .= ',table_assign';
        }
        $q = $this->db->select($select)->where(['offline_windows_app_access' => '1'])->get('users');

        if (!$q) {
            $data['status'] = "ERROR";
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['status'] = "SUCCESS";
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
           
            return $data;
        }
    }

    public function get_users_permissions() {
        
        $SelectFields = "`id`, `group_id`, `products-index`, `products-add`, `products-edit`, `products-delete`, `products-cost`, `products-price`, `products-import`, `sales-index`, `sales-add`, `sales-edit`, `sales-pdf`, `sales-email`, `sales-delete`, `sales-date`, `sales-delete-suspended`, `purchases-index`, `customers-index`, `customers-add`, `customers-edit`, `customers-delete`, `sales-deliveries`, `sales-add_delivery`, `sales-edit_delivery`, `sales-delete_delivery`, `sales-email_delivery`, `sales-pdf_delivery`, `sales-gift_cards`, `sales-add_gift_card`, `sales-edit_gift_card`, `sales-delete_gift_card`, `all_sale_lists`, `pos-index`, `sales-return_sales`, `reports-index`, `reports-warehouse_stock`, `reports-quantity_alerts`, `reports-expiry_alerts`, `reports-products`, `reports-daily_sales`, `reports-monthly_sales`, `reports-sales`, `reports-payments`, `reports-purchases`, `report_purchase_gst`, `reports-profit_loss`, `reports-customers`, `reports-suppliers`, `reports-staff`, `reports-register`, `sales-payments`, `purchases-payments`, `purchases-expenses`, `bulk_actions`, `customers-deposits`, `customers-delete_deposit`, `products-barcode`, `reports-expenses`, `reports-daily_purchases`, `reports-warehouse_sales_report`, `reports-monthly_purchases`, `products-stock_count`, `edit_price`, `printer-setting`, `default-printer`, `cart-price_edit`, `cart-unit_view`, `cart-show_bill_btn`, `pos-show-order-btn`, `offlinepos-synchronization`";
        if((float)$this->offline_pos_version >= 5.00){
            $SelectFields .= ', bill_print, checkout_button, shift_table, table_clear, pos_clear_table, checkout';
        }
        
        $q = $this->db->select($SelectFields)->get('permissions');
        if (!$q) {
            $data['status'] = "ERROR";
            $data['error_no'] = $this->db->_error_number();
            $data['error'] = $this->db->_error_message();
            return $data;
        } else {
            $num = $q->num_rows();
            $data['status'] = "SUCCESS";
            $data['num'] = $num;

            if ($num > 0) {
                $data['rows'] = $q->result();
            }
            
            return $data;
        }
    }

    public function sale_count_data($search = '') {
        //return $this->db->count_all('sma_sales');
        $Sql = "select COUNT(*)AS num from sma_sales ";
        if (isset($search['value'])) {
            if ($search['value'] != '') {
                $Sql .= "  where (reference_no like '%" . $search['value'] . "%' or biller like '%" . $search['value'] . "%' or customer like '%" . $search['value'] . "%' or payment_status like '%" . $search['value'] . "%' or sale_status like '%" . $search['value'] . "%' ) ";
            }
        }
        $Query = $this->db->query($Sql);
        $result = $Query->result_array();
        return $result[0]['num'];
    }

    function getSaleList($startpoint, $per_page, $search = '') {
        //calculate the first page
        //$start = (($todb['page_no']-1)*$todb['limit'] > 0? ($todb['page_no']-1)* $todb['limit'] : 0 ); 
        $SelectFields = "id, DATE_FORMAT(date, '%Y-%m-%d %T') as sale_date, reference_no, biller, customer, sale_status, (grand_total+rounding) as total, paid, (grand_total+rounding-paid) as balance, payment_status, attachment, return_id, if(pos=1, 'POS', if(offline_sale=1, 'Offline', if(eshop_sale=1, 'Eshop', if(up_sales=1, 'up_sales', 'Sale')))) as sale_type";
        if((float)$this->offline_pos_version >= 5.00){
            //$SelectFields .= ', bill_no';
        }
        $query = "SELECT $selectFields FROM sma_sales ";
        
        if (isset($search['value'])) {
            if ($search['value'] != '') {
                $query .= "  where (reference_no like '%" . $search['value'] . "%' or biller like '%" . $search['value'] . "%' or customer like '%" . $search['value'] . "%' or payment_status like '%" . $search['value'] . "%' or sale_status like '%" . $search['value'] . "%' )";
            }
        }
        $query .= " order by id desc ";
        if ($startpoint != '') {
            $query .= " LIMIT {$startpoint} , {$per_page}";
        } else {
            $query .= " ";
        }

        return $result = $this->db->query($query);

        /* if ($result->num_rows() > 0) {
          foreach ($result->result() as $row) {
          $data[] = $row;
          }
          return $data;
          }else{
          return false;
          } */
    }

//05-09-2019
    /* download POS Details */
   function getDeletedList($data, $FieldId, $FieldValue, $start_limit='', $end_limit=''){
		$Sql = "select deleted_id as $FieldId, table_name from sma_deleted_data where  ";
		$Sql .= " table_name='$FieldValue' ";
		if($data['updated_at']!=''){
			$Sql .= " and Date(date) >= '".$data['updated_at']."'";
		}
		$Sql .= " order by deleted_id asc ";
		//if($start_limit!=''){
			//$Sql .= " limit $start_limit, $end_limit ";
			//echo $Sql;
		//}
		$result = $this->db->query($Sql);
		return $result->result_array();
	}
    function getBrandList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'code, name, image, id as brand_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_brands where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_brands where 1";
        }
        if ($start_limit != '') {
            $Sql .= " limit $start_limit, $end_limit ";
            //echo $Sql;
        }

        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getCategoryList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'code, name, image, parent_id, up_category, up_description, up_enabled, up_add_status, id as category_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_categories where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_categories where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getComboItemsList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'product_id, item_code, quantity, unit_price, id as combo_item_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_combo_items where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_combo_items where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getCompanyList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'group_id, group_name, customer_group_id, customer_group_name, name, company, vat_no, pan_card, address, city, state, state_code, postal_code, country, phone, email, cf1, cf2, cf3, cf4, cf5, cf6, invoice_footer, payment_term, logo, award_points, deposit_amount, price_group_id, price_group_name, dob, gstn_no,  id as company_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_companies where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_companies where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        //echo $Sql;
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getCustomerGroupList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'name, percent, id as customer_group_id';
        if((float)$this->offline_pos_version >= 5.00){
            $SelectFields .= ', apply_as_discount';
        }
        
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_customer_groups where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_customer_groups where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getPaymentList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'date, sale_id, return_id, purchase_id, reference_no, transaction_id, paid_by, cheque_no, cc_no, cc_holder, cc_month, cc_year, cc_type, amount, currency, created_by, attachment, type, note, pos_paid, pos_balance, approval_code, id as payment_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_payments where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_payments where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getUserList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'email, active, first_name, last_name, company, phone, group_id, id as user_id, table_assign';
        if((float)$this->offline_pos_version >= 5.00){
            $SelectFields .= ', table_assign';
        }
        
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_users where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_users where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getProductList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'code, article_code, name, unit, cost, price, mrp, alert_quantity, image, category_id, subcategory_id, cf1, cf2, cf3, cf4, cf5, cf6, quantity, tax_rate, track_quantity, details, warehouse, barcode_symbology, file, product_details, tax_method, type, supplier1, supplier1price, supplier2, supplier2price, supplier3, supplier3price, supplier4, supplier4price, supplier5, supplier5price, promotion, promo_price, start_date, end_date, supplier1_part_no, supplier2_part_no, supplier3_part_no, supplier4_part_no, supplier5_part_no, sale_unit, purchase_unit, brand, hsn_code, is_featured, divisionid, up_items, food_type_id, up_price,  id as product_id';
        
        if((float)$this->offline_pos_version >= 5.00){
            $SelectFields .= ', pos_combo_product';
        }
        
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_products where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_products where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getUnitList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'code, name, base_unit, operator, unit_value, operation_value, id as unit_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_units where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_units where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getVariantList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'name, id as variant_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_variants where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_variants where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getWarehouseList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'code, name, address, map, phone, email, price_group_id, id as warehouse_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_warehouses where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_warehouses where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getProductvariantList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'product_id, name, cost, price, quantity, id as product_varient_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_product_variants where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_product_variants where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getAllSaleList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'date, invoice_no, reference_no, customer_id, customer, biller_id, biller, seller_id, seller, warehouse_id, note, staff_note, total, product_discount, order_discount_id, total_discount, order_discount, product_tax, order_tax_id, order_tax, total_tax, shipping, grand_total, sale_status, payment_status, payment_term, due_date, created_by, updated_by, total_items, pos, paid, return_id, surcharge, attachment, return_sale_ref, sale_id, return_sale_total, rounding, eshop_sale, offline_sale, offline_reference_no, offline_payment_id, offline_transaction_type, cf1, cf2, delivery_status, eshop_order_alert_status, offlinepos_sale_reff, offer_category, offer_description, kot_tokan, up_channel, up_response, up_status, up_sales, up_item_level_total_charges, up_order_id, up_delivery_datetime, up_coupon, up_next_status, up_prev_state, up_state_timestamp, up_message, up_status_response, up_sales_notification, up_order_level_total_charges, id as invoice_sale_id';
        if((float)$this->offline_pos_version >= 5.00){
            $SelectFields .= ', bill_no';
        }
        
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_sales where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_sales where 1 ";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        //echo $Sql;
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getAllSaleItemList($data, $start_limit = '', $end_limit = '') {

        $SelectFields = 'sale_id, product_id, product_code, article_code, product_name, product_type, option_id, warehouse_id, tax_method, tax_rate_id, tax, mrp, real_unit_price, unit_discount, unit_tax, unit_price, net_unit_price, invoice_unit_price, invoice_net_unit_price, quantity, item_discount, item_tax, net_price, invoice_total_net_unit_price, subtotal, discount, serial_no, sale_item_id, product_unit_id, product_unit_code, unit_quantity, cf1, cf2, cf3, cf4, cf5, cf6, cf1_name, cf2_name, cf3_name, cf4_name, cf5_name, cf6_name,  hsn_code, note, delivery_status, pending_quantity,delivered_quantity,up_order_id, up_packaging_charge, urbanpiper, up_option_order_id, up_option_title, up_option_price, up_option_id, up_option_response, id as invoice_sale_item_id';

        //$SelectFields = 'sale_id, product_id, product_code, article_code, product_name, product_type, option_id, warehouse_id, tax_method, tax_rate_id, tax, mrp, real_unit_price, unit_discount, unit_tax, unit_price, net_unit_price, invoice_unit_price, invoice_net_unit_price, quantity,  hsn_code, note, delivery_status, pending_quantity,delivered_quantity,up_order_id, up_packaging_charge, urbanpiper, up_option_order_id, up_option_title, up_option_id, up_option_response, id as invoice_sale_item_id';


        /* $this->db->select($SelectFields);
          //if($start_limit!=''){
          $this->db->limit($end_limit,$start_limit);
          //}
          $result = $this->db->get('sma_sale_items')->result_array();
          return $result;
          if($data['updated_at']!=''){
          $Sql = "select $SelectFields from sma_sale_items where DATE_FORMAT(updated_at, '%Y-%m-%d') >= '".$data['updated_at']."'";
          }else{
          $Sql = "select $SelectFields from sma_sale_items where 1 ";
          }
          if($start_limit!='')
          $Sql .= " limit $start_limit, $end_limit ";
          echo $Sql;
          $result = $this->db->query($Sql); */

        $this->db->select($SelectFields);
        if ($data['updated_at'] != '') {

            $this->db->where('Date(updated_at) >=', $data['updated_at']);
        }
        if ($start_limit != '')
            $this->db->limit($end_limit, $start_limit);
        $result = $this->db->get('sma_sale_items')->result_array();
        return $result;
    }

    function getAllSaleItemTaxList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'item_id, sale_id, attr_code, attr_name, attr_per, tax_amount, id as invoice_sale_item_tax_id';
                
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_sales_items_tax where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_sales_items_tax where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getAllWarehouseProductList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'product_id, warehouse_id, quantity, rack, avg_cost, id as warehouse_product_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_warehouses_products where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_warehouses_products where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getAllWarehouseProductVariantList($data, $start_limit = '', $end_limit = '') {
        $SelectFields = 'option_id, product_id, warehouse_id, quantity, rack, id as warehouse_product_variant_id';
        if ($data['updated_at'] != '') {
            $Sql = "select $SelectFields from sma_warehouses_products_variants where Date(updated_at) >= '" . $data['updated_at'] . "'";
        } else {
            $Sql = "select $SelectFields from sma_warehouses_products_variants where 1";
        }
        if ($start_limit != '')
            $Sql .= " limit $start_limit, $end_limit ";
        $result = $this->db->query($Sql);
        return $result->result_array();
    }

    function getNotificaionById($NotificationId) {
        $query = "select * from sma_notifications where id='$NotificationId'";
        return $result = $this->db->query($query);
    }

    function deleteNotificaionById($NotificationId) {
        $this->db->where('id', $NotificationId);
        $this->db->delete('sma_notifications');
    }

    function addNotificaion($Datas) {
        $this->db->insert('sma_notifications', $Datas);
        return $this->db->insert_id();
    }

    function updateNotificaion($id, $Datas) {
        $this->db->where('id', $id);
        $this->db->update('sma_notifications', $Datas);
    }

    /* end download POS Details */
}
