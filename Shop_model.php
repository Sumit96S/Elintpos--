<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Shop_model extends CI_Model {

    public $errors;
    public $messages;
    public $basePath;
    public $apiResponce;
    public $apiUrl;

    public function __construct() {
        parent::__construct();

        //initialize messages and error
        $this->messages = array();
        $this->errors = array();
        $this->basePath = base_url();
        $this->apiResponce = '';
        $this->apiUrl = '';
    }

    public function get_setting() {
        $q = $this->db->select('setting_id,logo,logo2,default_warehouse,default_currency,default_tax_rate,symbol,pos_type')
                ->get('settings');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getOrdertax() {
        $q = $this->db->select('sma_tax_rates.id, sma_tax_rates.name, sma_tax_rates.type, sma_tax_rates.rate,')
                ->join('sma_tax_rates', 'sma_pos_settings.eshop_order_tax = sma_tax_rates.id', 'inner')
                ->get('sma_pos_settings');

        return $q->row_array();
    }

    public function postUrl($url, $data = array()) {

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $postDataArr[] = $key . "=" . $value;
            }

            $postData = join('&', $postDataArr);
        } else {
            $postData = '';
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded",
                "postman-token: 3bda5de7-1610-baef-2618-ff16b9dce0da"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "API Error :" . $err;
            exit;
        } else {
            return $response;
        }
    }

    public function isJSON($string) {
        return is_string($string) && is_array(json_decode($string, true)) ? true : false;
    }

    public function JSon2Arr($string) {

        if ($this->isJSon($string)) {
            return (array) json_decode($string, true);
        }

        return $string;
    }

    public function Arr2JSon(array $arr) {

        if (is_array($arr)) {
            return json_encode($arr, true);
        }

        return $arr;
    }

    /**     * 
     * @param array $userData = Array("id"=>"125","name"=>"Jon Thomas","phone"=>"1111111111","email"=>"jonthomas@gmail.com","auth_token"=>"zO15IBpmeuxGb9N4dAEXCQK2g")
     */
    public function set_user_session($userData) {

        $this->session->set_userdata($userData);
    }

    /**
     * 
     * @return boolean
     */
    public function session_authenticate() {

        if ($this->session->has_userdata('id') && $this->session->has_userdata('auth_token')) {
            return $this->session->has_userdata('id');
        } else {
            return false;
        }
    }

    /**
     * 
     */
    public function destory_user_token() {
        $this->session->unset_userdata('auth_token');
    }

    public function end_user_session() {
        if (count($_SESSION['cart']) > 0) {
            $cartItem = array();
            foreach ($_SESSION['cart'] as $cartProduct) {
                $cartItem[] = [
                    'user_id' => $this->session->userdata('id'),
                    'product_id' => $cartProduct['product_id'],
                    'option_id' => $cartProduct['option_id'],
                    'option_name' => $cartProduct['option_name'],
                    'option_price' => $cartProduct['option_price'],
                    'qty' => $cartProduct['qty'],
                    'date' => date('Y-m-d H:i:s'),
                ];
            }
            $this->db->insert_batch('sma_eshop_cart', $cartItem);
        }

        $array_items = ['id', 'name', 'phone', 'email', 'auth_token'];
        $this->session->unset_userdata($array_items);
        session_destroy();
    }

    /**
     * 
     * @param array $authData
     * @return type
     */
    public function authenticate_user(array $authData) {

        $this->apiUrl = $this->basePath . "api/user?action=auth";

        $this->apiResponce = $this->postUrl($this->apiUrl, $authData);

        return $this->JSon2Arr($this->apiResponce);
    }

    public function getAuthCustomer(array $param) {

        $loginid = $param['login_id'];
        $password = md5($param['password']);
        $data['status'] = 'ERROR';
        $where = "password='$password' AND ( email='$loginid' OR phone='$loginid' ) AND group_name = 'customer' ";

        $this->db->select('id,name,email,phone,mobile_verification_code,email_verification_code,email_is_verified,mobile_is_verified');
        $this->db->where($where);
        $this->db->limit(1, 0);
        $q = $this->db->get('companies');
        //  echo $this->db->last_query(); 

        if ($q->num_rows() > 0) {
            $data['status'] = 'SUCCESS';
            $data['result'] = $q->result_array();
        } else {
            $data['error'] = "Invalid User Input";
        }

        return $data;
    }

    public function getCustomerInfo() {

        $id = $this->session->userdata('id');

        $q = $this->db->select('id,name, company, vat_no, address, city, state, postal_code, country, phone, email, dob, gstn_no')
                ->where(array('group_name' => 'customer', 'id' => $id))
                ->get('companies');

        if ($q->num_rows() > 0) {
            $data = (array) $q->result();
            return $data[0];
        }

        return false;
    }

    public function updateCustomerInfo(array $data, $id = '') {

        if (!$id) {
            $id = $this->session->userdata('id');
        }

        $this->db->where('id', $id);
        $q = $this->db->update('companies', $data);

        if ($q) {
            return true;
        }

        return false;
    }

    public function storeDetails() {

        $this->apiUrl = $this->basePath . "api/store?action=generalDetails";

        $this->apiResponce = $this->JSon2Arr($this->postUrl($this->apiUrl, $authData = ''));

        return $this->apiResponce['setting'];
    }

    public function getProducts($cat_id, $keyword = '', $limit = 20, $offset = 0) {

        $this->apiUrl = $this->basePath . "api/catlog?action=allProducts";

        $authData['category_id'] = $cat_id;
        $authData['limit'] = $limit;
        $authData['offset'] = $offset;

        if (!empty($keyword)) {
            $authData['keyword'] = $keyword;
        }

        $this->apiResponce = $this->JSon2Arr($this->postUrl($this->apiUrl, $authData));

        return $this->apiResponce;
    }

    public function getUnites() {

        $q = $this->db->select('`id`, `code`,`name`')
                ->get('units');

        $count = $q->num_rows();

        $data = [];

        if ($count > 0) {
            $rowArr = $q->result();
            foreach ($rowArr as $row) {

                $data[$row->id] = (array) $row;
            }
        }

        return $data;
    }

    public function getProductInfo($product_ids) {

        $q = $this->db->select('`id`, `code`,`name`,`image`,`unit`,`cost`,`price`, `promotion`, `promo_price` ,`start_date` ,`end_date` ,`category_id`, `subcategory_id`, `cf1`, `cf2`, `tax_rate`, `tax_method`, `type`, `sale_unit`, `purchase_unit`,`brand`,`hsn_code`, `quantity`, `storage_type`, `primary_variant` ')
                ->where_in('id', $product_ids)
                ->get('products');

        $count = $q->num_rows();

        $data = [];

        if ($count > 0) {
            $rowArr = $q->result();
            foreach ($rowArr as $row) {

                $data[$row->id] = (array) $row;
            }
        }

        return $data;
    }

    public function getProductVeriantsInfo($pvid) {

        $q = $this->db->select('`id`,`name`,`cost`,`price`,`quantity`, `unit_quantity` ')
                ->where_in('id', $pvid)
                ->get('product_variants');

        $count = $q->num_rows();

        $data = [];

        if ($count > 0) {
            $rowArr = $q->result();
            //print_r($rowArr);
            foreach ($rowArr as $row) {

                $data[$row->id] = (array) $row;
            }
            return $data;
        }
        return false;
    }

    public function getProductInfovariant($product_ids) {
        $data = [];
        if (is_array($product_ids)) {

            foreach ($product_ids as $ids) {

                $ArrExplode = explode('_', $ids);
                $item_id = $ArrExplode[0];
                $option_id = $ArrExplode[1];
                $Opt = '';
                if ($option_id) {
                    $Opt = ', `pv`.`id` as `option_id`, `pv`.`name` as `vname`, `pv`.`quantity` as variant_quantity , pv.`unit_quantity` ';
                }

                $q = "SELECT `p`.`id`, `p`.`code`, `p`.`name`, `p`.`image`, `p`.`unit`, `p`.`cost`, `p`.`price`, `p`.`category_id`, `p`.`subcategory_id`, `p`.`cf1`, `p`.`cf2`, `p`.`tax_rate`, `p`.`tax_method`, `p`.`type`, `p`.`sale_unit`, `p`.`purchase_unit`, `p`.`brand`, `p`.`hsn_code`, `p`.`quantity`, `p`.`storage_type` " . $Opt . " FROM `sma_products` `p` ";

                if ($option_id) {
                    $q .= " inner JOIN `sma_product_variants` `pv` ON `pv`.`product_id` = `p`.`id`";
                }

                $q .= " WHERE `p`.`id` IN($item_id)";

                if ($option_id) {
                    $q .= " and pv.id='" . $option_id . "'";
                }

                $qr = $this->db->query($q);

                $count = $qr->num_rows();

                if ($count > 0) {
                    $rowArr = $qr->result();
                    foreach ($rowArr as $row) {
                        //echo $row->id.'_'.$row->option_id;
                        $data[$ids] = (array) $row;
                    }
                }
            }
            return $data;
        }
        return false;
    }

    public function getProductInfoByHash($product_hash) {

        $q = $this->db->select('products.id, products.code, products.name, products.image, products.unit, products.cost, products.mrp, products.price, products.category_id, products.subcategory_id, products.cf1, products.cf2, products.cf3, products.cf4, products.tax_rate, products.tax_method, products.type, products.sale_unit, products.purchase_unit, products.brand, products.hsn_code, products.details, products.product_details, products.promotion, products.promo_price, products.start_date, products.end_date, products.quantity, brands.name as brandname, products.storage_type, products.primary_variant ')
                ->join('brands', 'brands.id = products.brand', 'left')
                ->where('in_eshop', '1')
                ->where_in('md5(sma_products.id)', $product_hash)
                ->get('products');

        $count = $q->num_rows();
        if ($count > 0) {
            $row = $q->result();
            return (array) $row[0];
        }

        return false;
    }

    public function getProductVeriantsByHash($product_hash) {

        $q = $this->db->select('`id`,`name`,`cost`,`price`,`quantity`, `unit_quantity`')
                ->where_in('md5(product_id)', $product_hash)
                ->get('product_variants');

        $count = $q->num_rows();

        $data = [];

        if ($count > 0) {
            $rowArr = $q->result();
            foreach ($rowArr as $row) {

                $data[$row->id] = $row;
            }
            return $data;
        }

        return false;
    }

    public function getProductVeriantsById($product_id) {

        $q = $this->db->select('`id`,`name`,`cost`,`price`,`quantity`, `unit_quantity`')
                ->where_in('product_id', $product_id)
                ->get('product_variants');

        $count = $q->num_rows();

        $data = [];

        if ($count > 0) {
            $rowArr = $q->result();
            //print_r($rowArr);
            foreach ($rowArr as $row) {

                $data[$row->id] = $row;
            }
            return $data;
        }
        return false;
    }

    public function getProductImagesByHash($product_hash) {

        $q = $this->db->select('`id`,`photo`')
                ->where_in('md5(product_id)', $product_hash)
                ->get('product_photos');

        $count = $q->num_rows();

        $data = [];

        if ($count > 0) {
            $rowArr = $q->result();
            foreach ($rowArr as $row) {

                $data[] = $row;
            }
            return $data;
        }

        return false;
    }

    /*
     * Para: $category_hash should be md5() hash of category_id
     */

    public function getCategoryProducts($category, $pageno = 1, $itemsPerPage = 20, $eshop_warehouse_id = null) {
        if (is_numeric($category)) {
            $data['count'] = 0;
            $category_hash = md5($category);
        } else {
            $category_hash = $category;
        }

        $offset = ( $pageno - 1 ) * $itemsPerPage;

        for ($i = 1; $i <= 2; $i++) {

            if ($i == 1) {
                $this->db->select('`id`');
            } else {
                $this->db->select('`id`, `code`,`name`,`image`,`unit`,`cost`,`price`,`mrp`, promotion, promo_price, start_date, end_date, `category_id`, `subcategory_id`, `cf1`, `cf2`, `tax_rate`, `tax_method`, `type`, `sale_unit`,`purchase_unit`,`brand`,`hsn_code`, `quantity`, `storage_type`, `primary_variant` ');
            }

            $this->db->where("in_eshop = '1' AND is_active = '1' AND (md5(category_id) = '$category_hash' OR md5(subcategory_id) = '$category_hash' ) ");

            $this->db->order_by('name', 'asc');
            
            if ($i == 2) {

                $offset = ($pageno - 1 ) * $itemsPerPage;

                $this->db->limit($itemsPerPage, $offset);
                //$this->db->limit($itemsPerPage);
            }
            $var = 'q' . $i;
            $$var = $this->db->get('products');
        }//end for.

        $count = $q1->num_rows();
        $data['count'] = $count;
        $data['totalPages'] = ceil($count / $itemsPerPage);

        if ($count > 0) {
            $data['msg'] = '<div class="alert alert-info">Result: ' . $count . ' products found.</div>';

            foreach (($q2->result()) as $row) {
                $data['items'][] = (array) $row;
            }
        } else {
            $data['msg'] = '<div class="alert alert-info">Products not found in this category</div>';
        }
        return $data;
    }

    public function getHotProducts($limit = 8) {
        $sql = "SELECT `id`,`code`, `name`, `price`, `image`,`mrp`, promotion, promo_price, start_date, end_date, quantity , storage_type "
                . "FROM `sma_products` "
                . "WHERE `id` IN ( SELECT `product_id` FROM `sma_sale_items` group by `product_code` order by count(`product_name`) desc ) AND in_eshop = '1' limit $limit ";


        $rec = $this->db->query($sql);

        if ($rec->num_rows()) {
            return $rec->result_array();
        }

        return FALSE;
    }

    public function searchProducts($keyword, $pageno = 1, $itemsPerPage = 20, $eshop_warehouse_id = null) {
        if (empty($keyword)) {

            $data['count'] = 0;
            $data['msg'] = '<div class="alert alert-danger">Invalid keyword</div>';
            return $data;
        }

        $offset = ( $pageno - 1 ) * $itemsPerPage;

        for ($i = 1; $i <= 2; $i++) {

            if ($i == 1) {
                $this->db->select('`id`');
            } else {
                $this->db->select('`id`, `code`,`name`,`image`,`unit`,`cost`,`price`,`mrp`, `promotion`, `promo_price` ,`start_date` ,`end_date`, `category_id`,`subcategory_id`,`cf1`,`cf2`,`tax_rate`, `tax_method`, `type`, `sale_unit`,`purchase_unit`,`brand`,`hsn_code`,`quantity`, `storage_type`, `primary_variant`');
            }

            $this->db->where(['in_eshop' => '1']);

            $this->db->like('name', $keyword);
            $this->db->or_like('code', $keyword);
            $this->db->or_like('hsn_code', $keyword);

            if ($i == 2) {

                $offset = ($pageno - 1 ) * $itemsPerPage;

                $this->db->limit($itemsPerPage, $offset);
                //$this->db->limit($itemsPerPage);
            }
            $var = 'q' . $i;
            $$var = $this->db->get('products');
        }//end for.

        $count = $q1->num_rows();
        $data['count'] = $count;
        $data['totalPages'] = ceil($count / $itemsPerPage);

        if ($count > 0) {
            $data['msg'] = '<div class="alert alert-info">Result: ' . $count . ' products found.</div>';

            foreach (($q2->result()) as $row) {
                $data['items'][] = (array) $row;
            }
        } else {
            $data['msg'] = '<div class="alert alert-info">Result: ' . $count . ' products found.</div>';
        }
        return $data;
    }

    public function ____searchProducts($keyword, $pageno = 1, $itemsPerPage = 12) {
        if (empty($keyword))
            return false;

        $this->db->select('`id`, `code`,`name`,`image`,`unit`,`cost`,`price`,`category_id`,`subcategory_id`,`cf1`,`cf2`,`tax_rate`, `tax_method`, `type`, `sale_unit`,`purchase_unit`,`brand`,`hsn_code`, `quantity`, `storage_type`, `primary_variant` ');

        $this->db->where(['in_eshop' => '1']);

        $this->db->like('name', $keyword);
        $this->db->or_like('code', $keyword);
        $this->db->or_like('hsn_code', $keyword);

        $offset = ( $pageno - 1 ) * $itemsPerPage;

        $this->db->limit($offset, $itemsPerPage);

        $q = $this->db->get('products');
        $count = $q->num_rows();
        $data['count'] = $count;

        if ($count > 0) {
            foreach (($q->result()) as $row) {
                $data['items'] = $row;
            }
        }
        return $this->Arr2JSon($data);
    }

    public function getCategory($type = 'ALL', $key = '') {

        switch ($type) {
            case 'PARENT':
                $action = "?action=allParentCategories";
                break;

            case 'CHILD':
                $action = "?action=allSubcategories&parent_id=" . $key;
                break;

            case 'SEARCH':
                $action = "?action=allCategories&keyword=" . $key;
                break;

            default:
            case 'ALL':
                $action = "?action=allCategories";
                break;
        }

        $this->apiUrl = $this->basePath . "api/catlog" . $action;

        $this->apiResponce = $this->JSon2Arr($this->postUrl($this->apiUrl, $authData = ''));

        return $this->apiResponce;
    }

    public function getCategoryName($id) {
        $q = $this->db->select('name')->get_where('categories', ['id' => $id]);
        if ($q->num_rows() > 0) {
            $row = $q->result_array();
            return $row[0]['name'];
        }
        return false;
    }

    public function searchCategory($searchkey = '') {

        if (empty($searchkey) || strlen($searchkey) < 3)
            return false;

        $this->db->select('`id`, `code`,`name`,`image`');
        $this->db->where('in_eshop', '1');
        $this->db->like('name', $searchkey);
        $this->db->limit(0, 5);
        $q = $this->db->get('categories');

        if ($q->num_rows() > 0) {

            $list = (array) $q->result();

            return $list;
        }

        return false;
    }

    public function getParentCategories() {

        $query = "SELECT `id`, `code` ,`name` ,`image` ,
                        `id` as cat_id,(select count(id) from sma_categories where 
                        `parent_id`=`cat_id`) as subcat_count,
                        (SELECT count(`id`)  
                                FROM `sma_products` where `category_id` = `sma_categories`.`id` and `in_eshop` = '1' ) as products_count,
                        IF(parent_id IS NULL,0,parent_id) as parent_id                        
                        FROM `sma_categories`                        
                        WHERE `in_eshop` = '1' AND `id` in (SELECT `category_id` 
                                FROM `sma_products` group by `category_id`) 
                  ORDER BY `sma_categories`.`name` ASC";


        $q = $this->db->query($query);

        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getChildCategories($parent_id) {

        $query = "SELECT `id`, `code` ,`name` ,`image` ,
                        `id` as subcat_id,(select count(id) from sma_categories where 
                        `parent_id`=`subcat_id`) as subcat_count,
                        (SELECT count(`id`)  
                                FROM `sma_products` 
                                where `subcategory_id` = `sma_categories`.`id` and `category_id` = '$parent_id' and `in_eshop` = '1') as products_count,
                        IF(parent_id IS NULL,0,parent_id) as parent_id                        
                        FROM `sma_categories`                        
                        WHERE `id` in (SELECT `subcategory_id` 
                                FROM `sma_products` 
                                where `category_id` = '$parent_id' group by `subcategory_id`) 
                  ORDER BY `sma_categories`.`name` ASC";

        $q = $this->db->query($query);

        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getAllChildCategories() {

        $query = "SELECT `id`, `code` ,`name` ,`image` ,
                        `id` as subcat_id, parent_id,
                        (SELECT count(`id`)  
                                FROM `sma_products` 
                                where `subcategory_id` = `sma_categories`.`id` and `in_eshop` = '1') as products_count,
                        IF(parent_id IS NULL,0,parent_id) as parent_id                        
                        FROM `sma_categories`                        
                        WHERE `id` in (SELECT `subcategory_id` 
                                FROM `sma_products` 
                                group by `subcategory_id`) 
                  ORDER BY `sma_categories`.`name` ASC";

        $q = $this->db->query($query);

        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function category_navigation($category) {

        if (is_numeric($category)) {
            $category_hash = md5($category);
        } else {
            $category_hash = $category;
        }

        $query = "SELECT `name` as category,                    
                        IF(parent_id IS NULL,NULL,
                            (SELECT `name` FROM `sma_categories` WHERE `id` = A.`parent_id` )
                        ) as parent ,
                        (SELECT count(`id`)  
                                FROM `sma_products` 
                                where md5(`subcategory_id`) = '$category_hash' OR md5(`category_id`) = '$category_hash' and `in_eshop` = '1' ) as products_count
                        
                        FROM `sma_categories` A                        
                        WHERE md5(`id`) = '$category_hash' LIMIT 1";

        $q = $this->db->query($query);

        if ($q->num_rows() > 0) {
            return $q->result_array();
        }
        return FALSE;
    }

    public function getRecentOrderByUser($User_id) {

        if (empty($User_id)):
            return false;
        endif;

        $this->db->select("sales.id as order_id,sales.reference_no as order_no,DATE_FORMAT(sma_sales.date,'%b %d %Y %h:%i %p') as order_date,"
                . "sales.payment_status ,payments.reference_no as payment_no,payments.transaction_id as transaction_no"
                . ", deliveries.do_reference_no  as delivery_reference_no"
                . ", deliveries.status  as delivery_status"
        );
        $this->db->from('sales');
        $this->db->join('payments', 'sales.id =  payments.sale_id', 'left');
        $this->db->join('deliveries', 'sales.id =  deliveries.sale_id', 'left');
        $this->db->where('sales.customer_id', $User_id);
        $this->db->where("sales.eshop_sale='1'");
        $this->db->order_by('sales.date', 'DESC');
        $this->db->limit(5, 0);

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            $i = 1;
            foreach (($q->result()) as $row) {
                if (!empty($row->delivery_status)):
                    $row->order_status = @ucfirst($row->delivery_status);
                elseif (!empty($row->payment_status)):
                    $row->order_status = ($row->payment_status == 'due') ? 'Payment due' : @ucfirst($row->payment_status);
                else :
                    $row->order_status = 'Payment due';
                endif;

                $data[] = $row;
                $i++;
            }
            return $data;
        }
        return FALSE;
    }

    public function getEshopSalesByUser($param) {
        $User_id = isset($param['user_id']) && !empty($param['user_id']) ? $param['user_id'] : NULL;
        $limit = isset($param['limit']) && !empty($param['limit']) ? $param['limit'] : NULL;
        $offset = isset($param['offset']) && !empty($param['offset']) ? $param['offset'] : 0;
        $sort_field = isset($param['sort_field']) && !empty($param['sort_field']) ? $param['sort_field'] : 'sales.id'; //'sales.id';
        $sort_dir = isset($param['sort_dir']) && !empty($param['sort_dir']) ? $param['sort_dir'] : 'desc';
        $search_by = isset($param['search_by']) && !empty($param['search_by']) ? $param['search_by'] : NULL;
        $search_param = isset($param['search_param']) && !empty($param['search_param']) ? $param['search_param'] : NULL;

        if (!empty($search_by) && is_array($search_param)):
            switch ($search_by) {
                case 'order_ref':
                    if (empty($search_param['order_ref'])):
                        return false;
                    endif;
                    $this->db->where('sales.reference_no', $search_param['order_ref']);
                    break;

                case 'order_date':
                    if (empty($search_param['order_date1']) || empty($search_param['order_date2'])):
                        return false;
                    endif;
                    $this->db->where('date(sales.`date`) between  ' . " '" . $search_param['order_date1'] . "'  and '" . $search_param['order_date2'] . "' ");
                    break;

                case 'pay_status':
                    if (empty($search_param['pay_status'])):
                        return false;
                    endif;
                    $this->db->where('sales.payment_status', $search_param['pay_status']);
                    break;

                case 'pay_ref':
                    if (empty($search_param['pay_ref'])):
                        return false;
                    endif;
                    $this->db->where('payments.reference_no', $search_param['pay_ref']);
                    break;

                case 'pay_trans':
                    if (empty($search_param['pay_trans'])):
                        return false;
                    endif;
                    $this->db->where('payments.transaction_id', $search_param['pay_trans']);
                    break;

                default:
                    break;
            }
        endif;

        if (empty($User_id)):
            return false;
        endif;

        $this->db->select("sales.id as order_id,sales.grand_total as grand_total,sales.reference_no as order_no,  sales.delivery_status as sales_delivery_status , order_no as order_no_view, DATE_FORMAT(sma_sales.date,'%b %d %Y %h:%i %p') as order_date,"
                . "sales.payment_status ,sales.sale_status ,payments.reference_no as payment_no,payments.transaction_id as transaction_no"
                . ", deliveries.do_reference_no  as delivery_reference_no"
                . ", deliveries.status  as delivery_status"
        );
        $this->db->from('sales');
        $this->db->join('payments', 'sales.id =  payments.sale_id', 'left');
        $this->db->join('deliveries', 'sales.id =  deliveries.sale_id', 'left');
        $this->db->order_by('sales.date', 'desc');
        $this->db->where('sales.customer_id', $User_id);
        $this->db->where("sales.eshop_sale='1'");
        $this->db->where("sales.sale_status='completed'");

        //--------------SORT ------------------------------
        if (!empty($sort_field) && !empty($sort_dir)):
            $this->db->order_by($sort_field, $sort_dir);
        endif;

        //--------------Limit ------------------------------
        if (!empty($limit) && !empty($offset)):
            $this->db->limit($limit, $offset);
        endif;

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            $i = 1;
            foreach (($q->result()) as $row) {
                if (!empty($row->delivery_status)):
                    $row->order_status = @ucfirst($row->delivery_status);
                elseif (!empty($row->payment_status)):
                    $row->order_status = ($row->payment_status == 'due') ? 'Payment due' : @ucfirst($row->payment_status);
                else :
                    $row->order_status = 'Payment due';
                endif;

                $data[] = $row;
                $i++;
            }
            return $data;
        }
        return FALSE;
    }

    public function getOrdersByUser($param) {
        $User_id = isset($param['user_id']) && !empty($param['user_id']) ? $param['user_id'] : NULL;
        $limit = isset($param['limit']) && !empty($param['limit']) ? $param['limit'] : NULL;
        $offset = isset($param['offset']) && !empty($param['offset']) ? $param['offset'] : 0;
        $sort_field = isset($param['sort_field']) && !empty($param['sort_field']) ? $param['sort_field'] : 'orders.id'; //'sales.id';
        $sort_dir = isset($param['sort_dir']) && !empty($param['sort_dir']) ? $param['sort_dir'] : 'desc';
        $search_by = isset($param['search_by']) && !empty($param['search_by']) ? $param['search_by'] : NULL;
        $search_param = isset($param['search_param']) && !empty($param['search_param']) ? $param['search_param'] : NULL;

        if (!empty($search_by) && is_array($search_param)):
            switch ($search_by) {
                case 'order_ref':
                    if (empty($search_param['order_ref'])):
                        return false;
                    endif;
                    $this->db->where('orders.reference_no', $search_param['order_ref']);
                    break;

                case 'order_date':
                    if (empty($search_param['order_date1']) || empty($search_param['order_date2'])):
                        return false;
                    endif;
                    $this->db->where('date(orders.`date`) between  ' . " '" . $search_param['order_date1'] . "'  and '" . $search_param['order_date2'] . "' ");
                    break;

                case 'pay_status':
                    if (empty($search_param['pay_status'])):
                        return false;
                    endif;
                    $this->db->where('orders.payment_status', $search_param['pay_status']);
                    break;

                case 'pay_ref':
                    if (empty($search_param['pay_ref'])):
                        return false;
                    endif;
                    $this->db->where('payments.reference_no', $search_param['pay_ref']);
                    break;

                case 'pay_trans':
                    if (empty($search_param['pay_trans'])):
                        return false;
                    endif;
                    $this->db->where('payments.transaction_id', $search_param['pay_trans']);
                    break;

                default:
                    break;
            }
        endif;

        if (empty($User_id)):
            return false;
        endif;

        /* $this->db->select("sales.id as order_id,sales.reference_no as order_no,DATE_FORMAT(sma_sales.date,'%b %d %Y %h:%i %p') as order_date,"
          . "sales.payment_status ,sales.sale_status ,payments.reference_no as payment_no,payments.transaction_id as transaction_no"
          . ", deliveries.do_reference_no  as delivery_reference_no"
          . ", deliveries.status  as delivery_status"
          );
          $this->db->from('sales');
          $this->db->join('payments', 'sales.id =  payments.sale_id', 'left');
          $this->db->join('deliveries', 'sales.id =  deliveries.sale_id', 'left');
          $this->db->order_by('sales.date', 'desc');
          $this->db->where('sales.customer_id', $User_id);
          $this->db->where("sales.eshop_sale='1'"); */


        $this->db->select("orders.id as order_id,orders.grand_total as grand_total,orders.rounding as rounding , orders.delivery_status as order_delivery_status , orders.invoice_no as order_number,orders.reference_no as order_no, orders.date ,DATE_FORMAT(sma_orders.date,'%b %d %Y %h:%i %p') as order_date,"
                . "orders.payment_status ,orders.sale_status ,payments.reference_no as payment_no,payments.transaction_id as transaction_no"
                . ", deliveries.do_reference_no  as delivery_reference_no"
                . ", deliveries.status  as delivery_status"
        );
        $this->db->from('orders');
        $this->db->join('payments', 'orders.id =  payments.order_id', 'left');
        $this->db->join('deliveries', 'orders.id =  deliveries.order_id', 'left');
        $this->db->order_by('orders.date', 'desc');
        $this->db->where('orders.customer_id', $User_id);
        $this->db->where("orders.eshop_sale='1'");
        $this->db->where_not_in("orders.sale_status",['cancelled', 'completed']);
        //--------------SORT ------------------------------
        if (!empty($sort_field) && !empty($sort_dir)):
            $this->db->order_by($sort_field, $sort_dir);
        endif;

        //--------------Limit ------------------------------
        if (!empty($limit) && !empty($offset)):
            $this->db->limit($limit, $offset);
        endif;

        $q = $this->db->get();
        if ($q->num_rows() > 0) {
            $i = 1;
            foreach (($q->result()) as $row) {
                if (!empty($row->delivery_status)):
                    $row->order_status = @ucfirst($row->delivery_status);
                elseif (!empty($row->payment_status)):
                    $row->order_status = ($row->payment_status == 'due') ? 'Payment due' : @ucfirst($row->payment_status);
                else :
                    $row->order_status = 'Payment due';
                endif;

                $data[] = $row;
                $i++;
            }
            return $data;
        }
        return FALSE;
    }

    public function get_billing_shipping($user_id) {

        $q = $this->db->get_where("eshop_user_details", array("user_id" => $user_id));

        if ($q->num_rows() > 0) {

            return $q->result();
        }

        return false;
    }

    public function getCompanyCustomer($arr) {

        if (is_array($arr)):
            $q = $this->db->get_where('companies', $arr, 1);
            //    echo $this->db->last_query(); 

            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;

        return FALSE;
    }

    public function updateCompany($id, $data = array()) {
        $this->db->where('id', $id);
        if (!isset($data['is_synced'])):
            $data['is_synced'] = 0;
        endif;
        if ($this->db->update('companies', $data)) {
            if ($data['group_id'] == 3 && $data['is_synced'] != 1):
                $coustmer = $this->getCompanyByID($id);
                $this->load->library('sma');
                $this->sma->SyncCustomerData($coustmer);
            endif;
            return true;
        }
        return false;
    }

    public function getStaticPages($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('eshop_pages', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    public function addPayment($data = array()) {
        if ($this->db->insert('payments', $data)) {
            if ($this->site->getReference('pay') == $data['reference_no']) {
                $this->site->updateReference('pay');
            }
            return true;
        }
        return false;
    }

    public function updateStatus($id, $status, $note, $payStatus = '') {
        $sale = $this->getInvoiceByID($id);
        $items = $this->getAllInvoiceItems($id);
        $cost = array();
        if ($status == 'completed' && $status != $sale->sale_status) {
            foreach ($items as $item) {
                $items_array[] = (array) $item;
            }
            $cost = $this->site->costing($items_array);
        }

        $payment_status = (empty($payStatus)) ? $sale->payment_status : $payStatus;

        if ($this->db->update('sales', array('sale_status' => $status, 'payment_status' => $payment_status, 'note' => $note), array('id' => $id))) {

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

    public function getCustomerByID($id) {
        $q = $this->db->select('id,name,email,phone,mobile_verification_code,email_verification_code,email_is_verified,mobile_is_verified,address,city,state,country,postal_code,company,vat_no,gstn_no,logo')
                ->get_where('companies', array('id' => $id), 1);

        if ($q->num_rows() > 0) {
            return (array) $q->row();
        }
        return 0;
    }

    public function getCustomerByEmail($email) {
        $q = $this->db->select('id,name,email,phone,mobile_verification_code,email_verification_code,email_is_verified,mobile_is_verified')
                ->get_where('companies', array('email' => $email), 1);
        if ($q->num_rows() > 0) {
            return (array) $q->row();
        }
        return 0;
    }

    public function getCustomerByloginId($loginid) {
        $q = $this->db->select('id,name,email,phone,mobile_verification_code,email_verification_code,email_is_verified,mobile_is_verified')
                ->where(['phone' => $loginid])
                ->or_where(['email' => $loginid])
                ->get('companies');
        if ($q->num_rows() > 0) {
            return (array) $q->row();
        }
        return 0;
    }

    public function getInvoiceByID($id) {
        $q = $this->db->get_where('sales', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    public function getAllInvoiceItems($sale_id, $return_id = NULL) {
        $this->db->select('sale_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, products.product_details as product_details, product_variants.name as variant')
                ->join('products', 'products.id=sale_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=sale_items.option_id', 'left')
                ->join('tax_rates', 'tax_rates.id=sale_items.tax_rate_id', 'left')
                ->group_by('sale_items.id')
                ->order_by('id', 'asc');
        if ($sale_id && !$return_id) {
            $this->db->where('sale_id', $sale_id);
        } elseif ($return_id) {
            $this->db->where('sale_id', $return_id);
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

    public function getProductPhotos($id) {
        $q = $this->db->get_where("product_photos", array('product_id' => $id));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {

                $data[] = $row;
            }
            return $data;
        }
    }

    //15/07/2019
    public function addWishList($data) {
        $userId = $data[0];
        $prodId = $data[1];
        $optionId = NULL;
        if (isset($data[2])) {
            $optionId = $data[2];
        }
        $this->db->where(['user_id' => $userId, 'product_id' => $prodId, 'option_id' => $optionId]);
        $q = $this->db->get('eshop_wishlist');

        if ($q->num_rows() > 0) {
            $this->db->where(['product_id' => $prodId, 'option_id' => $optionId]);
            $this->db->update('eshop_wishlist', array('user_id' => $userId, 'product_id' => $prodId, 'option_id' => $optionId, 'date' => date('Y-m-d H:i:s')));
        } else {

            $this->db->insert('eshop_wishlist', array('user_id' => $userId, 'product_id' => $prodId, 'option_id' => $optionId, 'date' => date('Y-m-d H:i:s')));
        }
        return true;
    }

    public function getWishListItems($userId) {


        $result = $this->db->select('p.`id`, p.`code`,p.`name`,p.`image`,p.`unit`,p.`cost`,p.`price`,p.`mrp`, p.promotion, p.promo_price, p.start_date, p.end_date, p.`tax_rate`, p.`tax_method`, p.`type`, p.`sale_unit`, p.`purchase_unit`, p.`quantity`, p.`storage_type`, wsh.id AS wsh_id, wsh.option_id AS wsh_option_id, wsh.product_id, pv.name AS variant_name, pv.price AS variant_price, pv.quantity AS variant_quantity, pv.unit_quantity AS variant_unit_quantity ')
                ->from('products AS p')
                ->join('eshop_wishlist AS wsh', 'wsh.product_id = p.id')
                ->join('product_variants AS pv', 'pv.id = wsh.option_id', 'left')
                ->where('wsh.user_id', $userId)
                ->order_by('wsh.id', 'desc')
                ->get()
                ->result();

        return array('count' => count($result), 'result' => $result);
    }

    public function brandList() {
        $q = $this->db->select('*')
                ->get('sma_brands');
        $row = $q->result_array();
        return $row;
    }

    public function FilterproductsData($getvar) {

        $catId = $getvar['catId'];
        $subcatid = $getvar['subcategory'];
        $brandId = $getvar['BrandsId'];
        $PriceVal = $getvar['PriceVal'];
        $pageno = $getvar['pageno'];
        $itemsPerPage = $getvar['itemsPerPage'] ? $getvar['itemsPerPage'] : 20;
        $catIdss = explode('_', $catId);
        $subcatIdss = explode('_', $subcatid);
        $brandIdss = explode('_', $brandId);
        $PriceVal1 = explode('_', $PriceVal);
        $pricvals = array_values($PriceVal1);
        $pricval = [];
        foreach ($pricvals as $pricvalkey => $pricvalss) {
            $pricvalss1 = explode('~', $pricvalss);
            $pricval[] = array_values($pricvalss1);
        }
        $priceArr = call_user_func_array('array_merge', $pricval);
        $priceMin = min($priceArr);
        $priceMax = max($priceArr);
        $sql = "SELECT count(id) as count FROM sma_products WHERE code != '' AND in_eshop = '1' ";
        $this->db->select('`id`, `code`,`name`,`image`,`unit`,`cost`,`price`,`mrp`, `promotion`, `promo_price`, `start_date`, `end_date`, `category_id`,`subcategory_id`,`cf1`,`cf2`,`tax_rate`, `tax_method`, `type`, `sale_unit`,`purchase_unit`,`brand`,`hsn_code`, `quantity`, `storage_type`, `primary_variant` ');

        if ($catId != "") {
            $this->db->where_in('category_id', $catIdss);
            $sql .= " AND category_id IN (" . join(',', $catIdss) . ") ";
        }

        if ($subcatid != "") {
            $this->db->where_in('subcategory_id', $subcatIdss);
            $sql .= " AND category_id IN (" . join(',', $subcatIdss) . ") ";
        }

        if (($brandId) != "") {
            $this->db->where_in('brand', $brandIdss);
            $sql .= " AND brand IN (" . join(',', $brandIdss) . ") ";
        }
        if ($PriceVal != "") {
            $this->db->where("price BETWEEN '$priceMin' AND '$priceMax'");
            $sql .= " AND price  BETWEEN '$priceMin' AND '$priceMax' ";
        }

        $this->db->where(['in_eshop' => '1']);

        $offset = ($pageno - 1 ) * $itemsPerPage;

        $this->db->limit($itemsPerPage, $offset);

        $q = $this->db->get('sma_products');

        $sqlcount = $this->db->query($sql)->result();

        $count = $sqlcount[0]->count;
        // $data['sqlcount'] =  $sqlcount;
        $data['count'] = $count;
        $data['totalPages'] = ($count) ? ceil($count / $itemsPerPage) : 0;
        if ($q->num_rows() > 0) {
            $data['rows'] = $q->result_array();
        }
        return $data;
    }

    public function getPriceList() {
        $q = $this->db->select("MIN(price) AS minprice, MAX(price) AS maxprice")
                ->where(['in_eshop' => '1'])
                ->get('sma_products');
        return $q->row_array();
    }

    /**
     * 
     * @param type $id
     * @return type
     */
    public function getSingleCategory($id) {
        $getcateory = $this->db->where(['id' => $id, 'in_eshop' => '1'])->get('categories')->row();
        return $getcateory;
    }

    /**
     * 
     * @param type $ids
     * @return type
     */
    public function get_subcategorys($ids) {
        $subcategory = $this->db->where_in('parent_id', $ids)->where('in_eshop', '1')->order_by('name', 'ASC')->get('categories')->result();
        return $subcategory;
    }

    /**
     * Get Product Name
     * @return type
     */
    public function getProductName() {
        $product_name = $this->db->select('name')->get('products')->result_array();
        return $product_name;
    }

    /**
     * 
     * @param type $id
     * @param type $status
     * @param type $note
     * @param type $payStatus
     * @return boolean
     */
    public function updateStatusOrder($id, $status, $note, $payStatus = '', $PaidAmt) {
        $sale = $this->getInvoiceOrderByID($id);
        $items = $this->getAllInvoiceItemsOrder($id);
        $cost = array();
        if ($status == 'completed' && $status != $sale->sale_status) {
            foreach ($items as $item) {
                $items_array[] = (array) $item;
            }
            $cost = $this->site->costing($items_array);
        }

        $payment_status = (empty($payStatus)) ? $sale->payment_status : $payStatus;

        $this->db->update('orders', array('sale_status' => $status, 'payment_status' => $payment_status, 'note' => $note, 'paid' => $PaidAmt), array('id' => $id));


        return false;
    }

    /**
     * 
     * @param type $id
     * @return boolean
     */
    public function getInvoiceOrderByID($id) {
        $q = $this->db->get_where('orders', array('id' => $id), 1);
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE;
    }

    /**
     * 
     * @param type $sale_id
     * @param type $return_id
     * @return boolean
     */
    public function getAllInvoiceItemsOrder($sale_id, $return_id = NULL) {
        $this->db->select('order_items.*, tax_rates.code as tax_code, tax_rates.name as tax_name, tax_rates.rate as tax_rate, products.image, products.details as details, products.product_details as product_details, product_variants.name as variant')
                ->join('products', 'products.id=order_items.product_id', 'left')
                ->join('product_variants', 'product_variants.id=order_items.option_id', 'left')
                ->join('tax_rates', 'tax_rates.id=order_items.tax_rate_id', 'left')
                ->group_by('order_items.id')
                ->order_by('id', 'asc');
        if ($sale_id && !$return_id) {
            $this->db->where('sale_id', $sale_id);
        } elseif ($return_id) {
            $this->db->where('sale_id', $return_id);
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
     * OTP action
     * 
     * @param type $key
     * @param type $data
     * @param type $update
     * @return type
     */
    public function otp_action($key, $data, $update = NULL) {
        switch ($key) {

            case 'Update':

                $this->db->where($data)->update('sma_companies', $update);
                return ($this->db->affected_rows()) ? TRUE : FALSE;
                break;

            case 'check':
                $q = $this->db->select('id,phone,mobile_verification_code')->where($data)->get('sma_companies')->row();
                return ($this->db->affected_rows()) ? $q : FALSE;
                break;
        }
    }

    /**
     * 
     * @param type $arr
     * @return boolean
     */
    public function getPaytmTransaction($arr) {
        if (is_array($arr)):
            $q = $this->db->get_where('paytm', $arr, 1);
            if ($q->num_rows() > 0) {
                return $q->row();
            }
        endif;
        return FALSE;
    }

    /**
     * Paytm Payment
     * @param type $data
     */
    public function addPaytmTransaction($data) {
        $arr = array();
        $arr['eshop_order'] = $data["sale_id"];
        $arr['request_data'] = serialize($data["req_data"]);
        $arr['created_time'] = date("Y-m-d H:i:s");
        $this->db->insert('paytm', $arr);
    }

    /**
     * 
     * @param type $id
     * @param type $data
     * @return boolean
     */
    public function updatePaytmTransaction($id, $data = array()) {

        $this->db->where('eshop_order', $id);
        if ($this->db->update('paytm', $data)) {
            return true;
        }
        return false;
    }

    public function paytmTransTime($time) {
        if (!empty($time)) {
            $arr1 = explode(".", $time);

            return $arr1[0];
        }
        return false;
    }

    /*     * *
     * Payment Table store details
     */

    public function PaytmAfterSale($result, $sid) {
        $payment = array();
        $payment['transaction_id'] = $result['TXNID'];
        $payment['amount'] = $result['TXNAMOUNT'];
        $payment['currency'] = $result['CURRENCY'];
        $payment['order_id'] = $sid;
        $payment['paid_by'] = 'paytm';
        $payment['reference_no'] = $this->site->getReference('pay');
        $payment['type'] = 'received';
        $trans_date = $this->paytmTransTime($result['TXNDATE']);
        if (!empty($trans_date)):
            $payment['date'] = $trans_date;
        endif;

        if (!empty($payment['transaction_id']) && !empty($payment['amount']) && !empty($payment['order_id'])):

            $this->db->insert('payments', $payment);
            $pay_id = $this->db->insert_id();
            $this->updateStatusOrder($payment['order_id'], 'pending', 'Paytm Payment', 'paid', $payment['amount']);
            return $sid;
        endif;

        return false;
    }

    /**
     * Get Paid amount
     * @param type $condition
     * @return type
     */
    public function getpaidamount($condition) {
        $q = $this->db->select('sum(amount) as paid_amount')->where($condition)->get('sma_payments')->row();
        return ($this->db->affected_rows()) ? $q->paid_amount : 0;
    }

    /**
     * Google Email Check
     * */
    public function getAuthCustomergoogle(array $param) {

        $loginid = $param['login_id'];
        $password = md5($param['password']);
        $data['status'] = 'ERROR';
        //password='$password' AND 
        $where = "( email='$loginid' OR phone='$loginid' )";

        $this->db->select('id,name,email,phone,mobile_verification_code,email_verification_code,email_is_verified,mobile_is_verified');
        $this->db->where($where);
        $this->db->limit(1, 0);
        $q = $this->db->get('companies');
        //  echo $this->db->last_query(); 

        if ($q->num_rows() > 0) {
            $data['status'] = 'SUCCESS';
            $data['result'] = $q->result_array();
        } else {
            $data['error'] = "Invalid User Input";
        }

        return $data;
    }

    /**
     * Get State
     * @return type
     */
    public function getState() {
        $state = $this->db->order_by('name', 'ASC')->get('sma_state_master')->result();
        return $state;
    }

    /**
     * Check Pincode
     * @param type $pincode
     * @return boolean
     */
    public function checkPincode($pincode, $outlet=null) {
        
        if ($pincode) { $where['pincode']= $pincode; } else { return FALSE; }
        
        if($outlet){ $where['warehouse_id']= $outlet; }
        
        $this->db->where( $where )->get('sma_pincode');

        return ($this->db->affected_rows() > 0) ? TRUE : FALSE;
            
        return FALSE;
    }

    /**
     * 
     * @param type $userid
     * @param type $imagename
     */
    public function uploadphoto($userid, $imagename) {
        $getdata = $this->db->select('logo')->where(['id' => $userid])->get('companies')->row();
        if ($getdata->logo) {
            $file_pointer = 'assets/mdata/'.$this->Customer_assets.'/uploads/avatars/' . $getdata->logo;
            if (file_exists($file_pointer)) {
                unlink($file_pointer);
            }
        }
        $datavalue = ['logo' => $imagename];

        $this->db->where(['id' => $userid])->update('companies', $datavalue);
    }

    /**
     * Guest Login
     */
    public function guestlogin($data) {
        $this->db->where(['phone' => $data['phone']]);
        if ($data['email']) {
            $this->db->or_where(['email' => $data['email']]);
        }
        $sql = $this->db->get('companies')->row();

        if ($sql) {
            if ($sql->mobile_is_verified != 1) {
                $this->db->where(['id' => $sql->id])->update('companies', ['mobile_is_verified' => '1']);
            }
            return $sql;
        } else {
            $data = array('name' => $data['name'],
                'email' => $data['email'],
                'group_id' => '3',
                'group_name' => 'customer',
                'customer_group_id' => '1',
                'customer_group_name' => 'General',
                'price_group_id' => '2',
                'is_synced' => '0',
                'price_group_name' => 'Standered',
                'company' => '-',
                'country' => 'India',
                'phone' => $data['phone'],
            );

            $this->db->insert('companies', $data);
            $insert_id = $this->db->insert_id();
            $sql = $this->db->where(['id' => $insert_id])->get('companies')->row();
            return $sql;
        }
    }

    /**
     * End Guest Login
     */

    /**
     * GET USER CART PRODUCT
     * @param type $userid
     */
    public function userCartData($userid) {
        $cart_data = $this->db->where(['user_id' => $userid])->get('eshop_cart')->result();
        if ($cart_data) {
            foreach ($cart_data as $cartItem) {
                $qty = ($cartItem->qty) ? $cartItem->qty : 0;
                if ($cartItem->option_id) {
                    $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['product_id'] = $cartItem->product_id;
                    $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['option_id'] = $cartItem->option_id;
                    $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['option_name'] = $cartItem->option_name;
                    $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['option_price'] = $cartItem->option_price;

                    if (isset($_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id])) {

                        $qty ? $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['qty'] = $qty : $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['qty'] += 1;
                    } else {
                        $_SESSION['cart'][$cartItem->product_id . '_' . $cartItem->option_id]['qty'] = 1;
                    }
                } else {
                    $_SESSION['cart'][$cartItem->product_id]['product_id'] = $cartItem->product_id;

                    if (isset($_SESSION['cart'][$cartItem->product_id])) {
                        $qty ? $_SESSION['cart'][$cartItem->product_id]['qty'] = $qty : $_SESSION['cart'][$cartItem->product_id]['qty'] += 1;
                    } else {
                        $_SESSION['cart'][$cartItem->product_id]['qty'] = 1;
                    }
                }
            }
            $this->db->where(['user_id' => $userid])->delete('eshop_cart');
        }
    }

    public function getWarehouseProductsStocks($warehouse_id = NULL) {

            
        $data = $this->db->select("p.id, p.type AS product_type, pi.warehouse_id, pi.product_id, pi.product_code, pi.product_name, pi.option_id, pi.quantity_balance, p.primary_variant, p.storage_type  ")
                ->from('purchase_items AS pi')
                ->join('products AS p' , 'p.id = pi.product_id', 'left')
                ->where(['pi.warehouse_id'=>$warehouse_id])            
                ->where(['p.in_eshop'=> 1, 'p.is_active'=>1])            
                ->group_start()->where('pi.status', 'received')->or_where('pi.status', 'partial')->group_end()
                ->order_by('pi.product_id, pi.option_id', 'desc')
                ->get()
                ->result();

        if ($data) {

            foreach ($data as $row) {

                $stocks[$row->product_id]['warehouse_id'] = $row->warehouse_id;
                $stocks[$row->product_id]['product_code'] = $row->product_code;
                $stocks[$row->product_id]['product_name'] = $row->product_name;
                $stocks[$row->product_id]['total_quantity'] += $row->quantity_balance;
                $stocks[$row->product_id]['storage_type'] = $row->storage_type;
                $stocks[$row->product_id]['varients_count'] += ($row->option_id ? 1 : 0);
                
                if($row->option_id) {
                    $stocks[$row->product_id][$row->option_id]['quantity'] += $row->quantity_balance;
                }
            }

            return $stocks;
        }
        return FALSE;
    }

    public function getProductsStocks($warehouse_id = NULL) {

        $data = $this->db->select("`product_id`, `product_code`, `product_name`, `batch_number`, `option_id`, sum(`quantity`) quantity, sum(`quantity_balance`) quantity_balance, `warehouse_id` , count(`id`) num ")
                ->where(['warehouse_id' => $warehouse_id, 'quantity >' => 0])
                ->group_by('`option_id`, `product_id`')
                ->get('purchase_items')
                ->result();

        if ($data) {
            foreach ($data as $row) {
                $stocks[$row->product_id][$row->option_id] = (array) $row;
            }
            return $stocks;
        }
        return FALSE;
    }

    public function getPendingOrderItems($warehouse_id = NULL) {

        if ($warehouse_id) {
            $where = "oi.`sale_id` IN (SELECT `id` FROM `sma_orders` WHERE `eshop_sale` = '1' AND `warehouse_id` = '$warehouse_id' AND `sale_status` NOT IN ('completed','cancelled') AND `return_id` IS NULL ) ";
        } else {
            $where = "oi.`sale_id` IN (SELECT `id` FROM `sma_orders` WHERE `eshop_sale` = '1' AND `sale_status` NOT IN ('completed','cancelled') AND `return_id` IS NULL )";
        }

        $data = $this->db->select("p.id AS `product_id`, p.code AS `product_code`, p.storage_type, oi.`option_id`, sum(oi.`quantity`) quantity, sum(oi.`unit_quantity`) order_unit_quantity, oi.warehouse_id ")
                ->from('products AS p')
                ->join('order_items oi', 'p.id = oi.product_id', 'left')
                ->where($where)
                ->group_by('oi.`option_id`, oi.`product_id`')
                ->order_by('p.id', 'desc')
                ->get()
                ->result();

        if ($data) {
            foreach ($data as $row) {
            
                $order[$row->product_id]['storage_type']    = $row->storage_type;
                $order[$row->product_id]['order_quantity'] += $row->quantity;

                if ($row->storage_type == 'packed' && $row->option_id) {
                    $order[$row->product_id][$row->option_id]['order_quantity'] += $row->quantity;
                }
            }
            return $order;
        }
        return FALSE;
    }

    public function getEshopOutlets() {

        $outlets = $this->db->select('id, code, name, eshop_biller_id')->where(['in_eshop' => '1', 'is_active' => '1', 'is_disabled' => '0'])->get('warehouses')->result();
        if (count($outlets)) {
            foreach ($outlets as $outlet) {
                $data[$outlet->id]['name'] = $outlet->name;
                $data[$outlet->id]['code'] = $outlet->code;
                $data[$outlet->id]['biller_id'] = $outlet->eshop_biller_id;
            }
            return $data;
        }
        return false;
    }

    public function get_times($default = '', $interval = '+30 minutes') {

        $output = '<option value="">Any Time </option>';

        $current = strtotime('00:00');
        $end = strtotime('23:59');

        while ($current <= $end) {
            $time = date('H:i', $current);
            $sel = ( $time == $default ) ? ' selected' : '';
            $tocurrent = strtotime($interval, $current);
            $output .= "<option value=\"{$time}\"{$sel}>" . date('h.i A', $current) . ' To ' . date('h.i A', $tocurrent) . '</option>';
            $current = strtotime($interval, $current);
        }

        return $output;
    }

    public function get_pincode_location($pincode) {

        $outlets = $this->db->select('p.warehouse_id AS id, w.name')
                ->from('pincode AS p')
                ->join('warehouses AS w', 'w.id = p.warehouse_id')
                ->where(['p.pincode' => $pincode])
                ->get()
                ->result();

        if (count($outlets)) {
            foreach ($outlets as $outlet) {
                $data[$outlet->id] = $outlet->name;
            }
            return $data;
        }
        return false;
    }

}
