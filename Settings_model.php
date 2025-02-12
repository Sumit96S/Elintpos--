<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Settings_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function updateLogo($photo) {
        $logo = array('logo' => $photo);
        if ($this->db->update('settings', $logo)) {
            return true;
        }
        return false;
    }

    public function updateLoginLogo($photo) {
        $logo = array('logo2' => $photo);
        if ($this->db->update('settings', $logo)) {
            return true;
        }
        return false;
    }

    public function getSettings() {
        $q = $this->db->get('settings');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getPosSettings() {
        $q = $this->db->get('pos_settings');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getDateFormats() {
        $q = $this->db->get('date_format');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function updateSetting($data) {
        $this->db->where('setting_id', '1');
        if ($this->db->update('settings', $data)) {
            return true;
        }
        return false;
    }

    public function addTaxRate($data) {
        if ($this->db->insert('tax_rates', $data)) {
            return true;
        }
        return false;
    }

    public function updateTaxRate($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('tax_rates', $data)) {
            return true;
        }
        return false;
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

    public function getTaxByRate($rate) {
        $q = $this->db->get_where('tax_rates', array('rate' => $rate, 'is_substitutable' => 0), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addWarehouse($data) {
        if ($this->db->insert('warehouses', $data)) {
            return true;
        }
        return false;
    }

    public function updateWarehouse($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('warehouses', $data)) {
            return true;
        }
        return false;
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
        $q = $this->db->get_where('warehouses', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function deleteTaxRate($id) {
        if ($this->db->delete('tax_rates', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function deleteInvoiceType($id) {
        if ($this->db->delete('invoice_types', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function deleteWarehouse($id) {
        if ($this->db->where('id', $id)->update('warehouses', ['is_active' => '0', 'is_deleted' => '1']) && $this->db->delete('warehouses_products', array('warehouse_id' => $id)) && $this->db->delete('warehouses_products_variants', array('warehouse_id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function addCustomerGroup($data) {
        if ($this->db->insert('customer_groups', $data)) {
            return true;
        }
        return false;
    }

    public function updateCustomerGroup($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('customer_groups', $data)) {
            return true;
        }
        return false;
    }

    public function getAllCustomerGroups() {
        $q = $this->db->get('customer_groups');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
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

    public function deleteCustomerGroup($id) {
        if ($this->db->delete('customer_groups', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getGroups() {
        $this->db->where('id >', 4);
        $q = $this->db->get('groups');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    //production unit
    public function getAllLocationTypes() {
        $q = $this->db->get('sma_location_type');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getAllowned_by() {
        $q = $this->db->get('sma_owned_by');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    public function getGroupByID($id) {
        $q = $this->db->get_where('groups', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getGroupPermissions($id) {
        $q = $this->db->get_where('permissions', array('group_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function GroupPermissions($id) {
        $q = $this->db->get_where('permissions', array('group_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function updatePermissions($id, $data = array()) {
        if ($this->db->update('permissions', $data, array('group_id' => $id)) && $this->db->update('users', array('show_price' => $data['products-price'], 'show_cost' => $data['products-cost']), array('group_id' => $id))) {
            return true;
        }
        return false;
    }

    public function addGroup($data) {
        if ($this->db->insert("groups", $data)) {
            $gid = $this->db->insert_id();
            $this->db->insert('permissions', array('group_id' => $gid));
            return $gid;
        }
        return false;
    }

    public function updateGroup($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update("groups", $data)) {
            return true;
        }
        return false;
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

    public function getCurrencyByID($id) {
        $q = $this->db->get_where('currencies', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addCurrency($data) {
        if ($this->db->insert("currencies", $data)) {
            return true;
        }
        return false;
    }

    public function updateCurrency($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update("currencies", $data)) {
            return true;
        }
        return false;
    }

    public function deleteCurrency($id) {
        if ($this->db->delete("currencies", array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getParentCategories() {
        $this->db->where('parent_id', NULL)->or_where('parent_id', 0);
        $q = $this->db->get("categories");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getCategoryByID($id) {
        $q = $this->db->get_where("categories", array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getCategoryByCode($code) {
        $q = $this->db->get_where('categories', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addCategory($data) {
        if ($this->db->insert("categories", $data)) {
            return true;
        }
        return false;
    }

    public function addCategories($data) {
        if ($this->db->insert_batch('categories', $data)) {
            return true;
        }
        return false;
    }

    public function updateCategory($id, $data = array()) {
        if ($this->db->update("categories", $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function updateProductTax1($id, $TaxRate) {
        $sql = "UPDATE `sma_products` SET `tax_rate`='$TaxRate' WHERE `category_id`='$id' and `subcategory_id` IS NULL";
        $this->db->query($sql);
        return false;
    }

    public function updateProductTax($FieldId, $id, $data = array()) {
        if ($this->db->update("products", $data, array($FieldId => $id))) {
            return true;
        }
        return false;
    }

    public function deleteCategory($id) {
        if ($this->db->delete("categories", array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function getPaypalSettings() {
        $q = $this->db->get('paypal');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updatePaypal($data) {
        $this->db->where('id', '1');
        if ($this->db->update('paypal', $data)) {
            return true;
        }
        return FALSE;
    }

    public function getSkrillSettings() {
        $q = $this->db->get('skrill');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateSkrill($data) {
        $this->db->where('id', '1');
        if ($this->db->update('skrill', $data)) {
            return true;
        }
        return FALSE;
    }

    public function checkGroupUsers($id) {
        $q = $this->db->get_where("users", array('group_id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function deleteGroup($id) {
        if ($this->db->delete('groups', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function addVariant($data) {
        if ($this->db->insert('variants', $data)) {
            return true;
        }
        return false;
    }

    public function updateVariant($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('variants', $data)) {
            return true;
        }
        return false;
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

    public function getVariantByID($id) {
        $q = $this->db->get_where('variants', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function deleteVariant($id) {
        if ($this->db->delete('variants', array('id' => $id))) {
            return true;
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

    public function getExpenseCategoryByCode($code) {
        $q = $this->db->get_where("expense_categories", array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addExpenseCategory($data) {
        if ($this->db->insert("expense_categories", $data)) {
            return true;
        }
        return false;
    }

    public function addExpenseCategories($data) {
        if ($this->db->insert_batch("expense_categories", $data)) {
            return true;
        }
        return false;
    }

    public function updateExpenseCategory($id, $data = array()) {
        if ($this->db->update("expense_categories", $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function hasExpenseCategoryRecord($id) {
        $this->db->where('category_id', $id);
        return $this->db->count_all_results('expenses');
    }

    public function deleteExpenseCategory($id) {
        if ($this->db->delete("expense_categories", array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function addUnit($data) {
        if ($this->db->insert("units", $data)) {
            return true;
        }
        return false;
    }

    public function updateUnit($id, $data = array()) {
        if ($this->db->update("units", $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function deleteUnit($id) {
        if ($this->db->delete("units", array('id' => $id))) {
            $this->db->delete("units", array('base_unit' => $id));
            return true;
        }
        return FALSE;
    }

    public function addPriceGroup($data) {
        if ($this->db->insert('price_groups', $data)) {
            return true;
        }
        return false;
    }

    public function updatePriceGroup($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('price_groups', $data)) {
            return true;
        }
        return false;
    }

    public function getAllPriceGroups() {
        $q = $this->db->get('price_groups');
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
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

    public function deletePriceGroup($id) {
        if ($this->db->delete('price_groups', array('id' => $id)) && $this->db->delete('product_prices', array('price_group_id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function setProductPriceForPriceGroup($product_id, $group_id, $price) {
        if ($this->getGroupPrice($group_id, $product_id)) {
            if ($this->db->update('product_prices', array('price' => $price), array('price_group_id' => $group_id, 'product_id' => $product_id))) {
                return true;
            }
        } else {
            if ($this->db->insert('product_prices', array('price' => $price, 'price_group_id' => $group_id, 'product_id' => $product_id))) {
                return true;
            }
        }
        return FALSE;
    }

    public function getGroupPrice($group_id, $product_id) {
        $q = $this->db->get_where('product_prices', array('price_group_id' => $group_id, 'product_id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getProductGroupPriceByPID($product_id, $group_id) {
        $pg = "(SELECT {$this->db->dbprefix('product_prices')}.price as price, {$this->db->dbprefix('product_prices')}.product_id as product_id FROM {$this->db->dbprefix('product_prices')} WHERE {$this->db->dbprefix('product_prices')}.product_id = {$product_id} AND {$this->db->dbprefix('product_prices')}.price_group_id = {$group_id}) GP";

        $this->db->select("{$this->db->dbprefix('products')}.id as id, {$this->db->dbprefix('products')}.code as code, {$this->db->dbprefix('products')}.name as name, GP.price", FALSE)
                // ->join('products', 'products.id=product_prices.product_id', 'left')
                ->join($pg, 'GP.product_id=products.id', 'left');
        $q = $this->db->get_where('products', array('products.id' => $product_id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateGroupPrices($data = array()) {
        foreach ($data as $row) {
            if ($this->getGroupPrice($row['price_group_id'], $row['product_id'])) {
                $this->db->update('product_prices', array('price' => $row['price']), array('product_id' => $row['product_id'], 'price_group_id' => $row['price_group_id']));
            } else {
                $this->db->insert('product_prices', $row);
            }
        }
        return true;
    }

    public function deleteProductGroupPrice($product_id, $group_id) {
        if ($this->db->delete('product_prices', array('price_group_id' => $group_id, 'product_id' => $product_id))) {
            return TRUE;
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

    public function getBrandByCode($code) {
        $q = $this->db->get_where('brands', array('code' => $code), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addBrand($data) {
        if ($this->db->insert("brands", $data)) {
            return true;
        }
        return false;
    }

    public function addBrands($data) {
        if ($this->db->insert_batch('brands', $data)) {
            return true;
        }
        return false;
    }

    public function updateBrand($id, $data = array()) {
        if ($this->db->update("brands", $data, array('id' => $id))) {
            return true;
        }
        return false;
    }

    public function deleteBrand($id) {
        if ($this->db->delete("brands", array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    public function brandHasProducts($id) {
        $q = $this->db->get_where('products', array('brand' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getTaxRateByIDPrd($id) {
        $this->db->where(array('tax_rate' => $id));
        $q = $this->db->get("products");
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

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

    public function getTaxAttrByID($id) {

        $q = $this->db->get_where('tax_attr', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function addTaxRateAttr($data) {
        if ($this->db->insert('tax_attr', $data)) {
            return true;
        }
        return false;
    }

    public function updateTaxRateAttr($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('tax_attr', $data)) {
            return true;
        }
        return false;
    }

    // offer  29/03/19
    public function getofferdata($offer_key = null) {
        return $this->db->select('sma_offers.*,sma_offers_categories.offer_category')->join('sma_offers_categories', 'sma_offers.offer_keyword=sma_offers_categories.offer_keyword')
                        ->order_by('sma_offers.id', 'DESC')->where(['sma_offers_categories.offer_keyword' => $offer_key])->get('sma_offers')->result();
    }

    public function getwarehousename() {
        return $this->db->select('id,name')->get('sma_warehouses')->result_array();
    }

    public function getcategory($condition) {
        return $this->db->select('*')->where($condition)->get('sma_offers_categories')->result();
    }

    public function category_update($condition, $data) {

        $this->db->where($condition)->update('sma_offers_categories', $data);
        if ($this->db->affected_rows()) {
            return "TRUE";
        } else {
            return "FALSE";
        }
    }

    // End Offer 29/03/19


    public function add_restaurant_table($data) {
        if ($this->db->insert('restaurant_tables', $data)) {
            return true;
        }
        return false;
    }

    public function update_restaurant_table($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update('restaurant_tables', $data)) {
            return true;
        }
        return false;
    }

    public function restaurant_table_by_id($id) {
        $q = $this->db->get_where('restaurant_tables', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function delete_restaurant_table($id) {
        if ($this->db->delete('restaurant_tables', array('id' => $id))) {
            return true;
        }
        return FALSE;
    }

    /**
     * Action for database backups
     * @param type $key
     * @param type $data
     */
    public function backups($key = NULL, $data = NULL) {

        switch ($key) {
            case 'Insert':
                $this->db->insert('sma_backup_database', $data);
                break;

            case 'Delete':
                $this->db->where(['file_name' => $data])->delete('sma_backup_database');
                break;

            case 'Select':
                $getdata = $this->db->select()->where(['file_name' => $data])->get('sma_backup_database')->row();
                return $getdata;
                break;
        }
    }

    /**
     * Store Barcode in table
     * @param type $barcodeData
     * @return type
     */
    public function storeManagebarcode($barcodeData) {
        $this->db->empty_table('sma_manage_barcode');
        $this->db->insert_batch('sma_manage_barcode', $barcodeData);
        return ($this->db->affected_rows()) ? TRUE : FALSE;
    }

    /**
     * Get Manage Barcode
     * @return type
     */
    public function getManagebarcode() {
        $data = $this->db->order_by('id', 'ASC')->get('sma_manage_barcode')->result();
        return $data;
    }

    /**
     * Manage Barcode Align
     */
    public function barcodeAlign($barcodedata) {
        $this->db->where(['setting_id' => '1'])->update('settings', $barcodedata);
        return ($this->db->affected_rows()) ? TRUE : FALSE;
    }

   
    public function getCustomerGroup() {
        return $this->db->select('id,name')->get('sma_customer_groups')->result_array();
    }

    public function getCompanies() {
        return $this->db->select('id,name')->where('group_name', 'customer')->get('sma_companies')->result_array();
    }

 

    public function getSmsConfigByID($id) {
        $q = $this->db->get_where('sms_configs', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function updateSmsConfig($id, $data = array()) {
        $this->db->where('id', $id);
        if ($this->db->update("sms_configs", $data)) {
            return true;
        }
        return false;
    }

    public function getEshopSettings() {
        $q = $this->db->get('sma_eshop_settings');
        if ($q->num_rows() > 0) {
            return $q->row(0);
        }
        return FALSE;
    }

    public function getBillers() {
        return $this->db->select('id,name')->where('group_name', 'biller')->get('sma_companies')->result_array();
    }
    
    public function getCustomFields() {
        $q = $this->db->get('settings_custom_fields');
        if ($q->num_rows() > 0) {
           foreach (($q->result()) as $row) {
                $data[$row->type][] = $row;
            }
            return $data;
        }
        return FALSE;
    }
    
    public function updateCustomFields($data) {
        
        if(is_array($data)) {
            $this->db->update_batch('settings_custom_fields',$data, 'type');
            return TRUE;
        }
        return FALSE;
    }


   /**************************************************
     * Disocunt Coupon  
     **************************************************/
    /**
     * Discount Coupon
     * @return type
     */
    public function getDiscountCouponData() {
        return $this->db->select('*')->where(['is_deleted' => '0'])->order_by('sma_discount_coupons.id', 'DESC')->get('sma_discount_coupons')->result();
    }
    
    
    /**
     * Store Discount Coupan 
     * @param type $data
     * @return type
     */
    public function StoreDiscountCoupon($data){
        $this->db->insert('discount_coupons', $data);
        return  ($this->db->affected_rows()? TRUE :FALSE);
    }
    
    
    /**
     * 
     * @param type $data
     * @param type $condition
     * @return type
     */
    public function UpdateDiscountCoupon($data, $condition){
        $this->db->where($condition)->update('discount_coupons',$data);
        return  ($this->db->affected_rows()? TRUE :FALSE);
    }
    
    
    /**
     * Get Discount Coupon Info
     * @param type $id
     * @return type
     */
    public function DiscountCouponInfo($id){
      $getdata =   $this->db->where('id', $id)->get('discount_coupons')->row();
      return  ($this->db->affected_rows()? $getdata :FALSE);
    }
    
    
    /*******************************************
     * End Discount Coupon
     *******************************************/
    
    /**
     *  Table Price Group
     **/
    public function price_group($type){
      $priceGroup =   $this->db->select('*')->where(['type'=> $type])->get('price_groups')->result();
      return $priceGroup;
    }

   

    public function getTables(){
    
       $getTabels = $this->db->select('*')->where(['parent_id'=> '0'])->get('restaurant_tables')->result();
       return $getTabels;    
       
    }
    public function saveOrdering($variants) {
        $this->db->trans_start(); 
        $data = [];
        foreach ($variants as $variant) {
            $data[] = [
                'name' => $variant['variant_name'],
                'rank' => $variant['rank_no'],
                'flag_visible' => $variant['is_selected'],
            ];
        }
        if (!empty($data)) {
            $this->db->update_batch('products', $data, 'name');
        }
        $this->db->trans_complete(); 
        if ($this->db->trans_status() === FALSE) {
            return false;
        }
        return true; 
    }
    public function update_category($data) {
        if (empty($data)) {
            return false; 
        }
        $this->db->update_batch('categories', $data, 'id');
        return $this->db->affected_rows();
    }
}
