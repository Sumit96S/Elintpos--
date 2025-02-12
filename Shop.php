<?php
defined('BASEPATH') OR exit('No direct script access allowed');
error_reporting(0);

class Shop extends MY_Controller {

    public $data;
    public $view_shop;
    public $Settings;
    public $eshop_settings;
    public $shopinfo;
    public $eshop_warehouse_id;
    private $ci;
    private $shoptheme;
    private $eshop_active;

    public function __construct() {

        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: GET");
        parent::__construct();

        $this->view_shop = 'default/views/shop/';
        $this->data['shopcomponents'] = base_url() . 'themes/' . $this->view_shop;
        $this->data['user_id'] = $this->session->userdata('id');
        $this->data['user_name'] = $this->session->userdata('name');

        $this->data['assets'] = base_url() . "themes/default/assets/shop/";
        $this->data['thumbs'] = base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->data['eshop_image'] = "assets/mdata/$this->Customer_assets/uploads/eshop_user/";
        $this->data['baseurl'] = base_url();

        $this->ci = get_instance();

        $this->load->library('form_validation');
        $this->load->library('facebook');

        $this->load->model('shop_model');
        $this->load->model('eshop_model');
        $this->load->model('orders_model');
        $this->load->model('pos_model');

        $this->load->helper('genfun_helper');

        $this->data['currency_symbol'] = $this->Settings->symbol;
        $this->data['currency'] = $this->Settings->default_currency;

        $shopinfo = $this->storeInfo();

        $shopinfo['default_biller'] = (isset($shopinfo['default_eshop_biller']) && $shopinfo['default_eshop_biller'] != '') ? $shopinfo['default_eshop_biller'] : $shopinfo['default_biller'];

        $shopinfo['default_eshop_warehouse'] = $this->eshop_warehouse_id = (isset($this->shopinfo['default_eshop_warehouse']) && $this->shopinfo['default_eshop_warehouse'] != '') ? $this->shopinfo['default_eshop_warehouse'] : $this->Settings->default_warehouse;

        $shopinfo['eshop_overselling'] = (isset($shopinfo['eshop_overselling']) && $shopinfo['eshop_overselling'] != '') ? $shopinfo['eshop_overselling'] : 0;

        $this->data['shopinfo'] = $this->shopinfo = $shopinfo;

        $this->data['shopMeta']['keywords'] = 'pos eshop';

        $this->data['shop_pagename'] = $this->uri->segment(2);
        $this->data['cartqty'] = $this->updateCartCount();
        $this->data['category'] = $this->parentCategories();

        $this->data['eshop_settings'] = $this->eshop_settings = $this->eshop_model->getEshopSettings(1);

        if ($this->shop_model->session_authenticate()) {
            $this->data['visitor'] = 'user';
        } else {
            $this->data['visitor'] = 'guest';
            $this->data['user_name'] = 'Guest';
        }
        $this->pos_settings = $this->site->get_pos_setting();
        $this->data['pos_settings'] = $this->pos_settings;
        $this->data['userdata'] = $this->get_customer_details($this->data['user_id']);

        $this->setShopTheme();

        $this->data['active_multi_outlets'] = $this->eshop_settings->active_multi_outlets;

        if ($this->eshop_settings->active_multi_outlets) {

            $shopinfo['default_eshop_warehouse'] = $this->eshop_warehouse_id = isset($_SESSION['eshop_location_id']) ? $_SESSION['eshop_location_id'] : $this->eshop_warehouse_id;

            $outlets = $this->shop_model->getEshopOutlets();
            
            $this->data['current_outlet'] = $outlets[$this->eshop_warehouse_id]['name'];

            $_SESSION['eshop_biller_id'] = $outlets[$this->eshop_warehouse_id]['biller_id'] ? $outlets[$this->eshop_warehouse_id]['biller_id'] : $shopinfo['default_biller'];
            
            if (isset($_SESSION['shipping_methods']) && $_SESSION['shipping_methods']['location']) {
                
                $shipping_methods = $this->eshop_model->getShippingMethods(['code' => $_SESSION['shipping_methods']['methods']]);
                $this->data['shipping_methods'] = $shipping_methods[0];
                $_SESSION['shipping_methods']['id'] = $shipping_methods[0]['id'];
                $_SESSION['shipping_methods']['minimum_order_amount'] = $shipping_methods[0]['minimum_order_amount'];
                
                $shippingData = '<b>Shipping Details</b>';
                $shippingData .= '<br/><b>Outlet&nbsp;&nbsp; : </b>' . $outlets[$_SESSION['shipping_methods']['location']]['name'];
                $shippingData .= '<br/><b>Method : </b>' . $shipping_methods[0]['name'];
                $shippingData .= '<br/><b>Pincode : </b>' . $_SESSION['shipping_methods']['pincode'];
                $shippingData .= '<br/><b>Date&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : </b>' . $_SESSION['shipping_methods']['date'];
                $shippingData .= '<br/><b>Time&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : </b>' . ($_SESSION['shipping_methods']['time'] == '00:00' ? 'Any Time' : $_SESSION['shipping_methods']['time']);

                $this->data['outlets'] = [$this->eshop_warehouse_id => $shippingData];
                
                $this->data['current_outlet'] = $outlets[$_SESSION['shipping_methods']['location']]['name'];
            } else {                
                foreach ($outlets as $otid=> $outlet) {
                    $this->data['outlets'][$otid] = $outlet['name'];
                }                
            }

        } else {
            $_SESSION['eshop_location_id'] = $this->eshop_warehouse_id;
            $_SESSION['eshop_biller_id'] = $shopinfo['default_biller'];
            
            if (isset($_SESSION['shipping_methods']) && $_SESSION['shipping_methods']['methods']) {
                
                $shipping_methods = $this->eshop_model->getShippingMethods(['code' => $_SESSION['shipping_methods']['methods']]);
                
                $_SESSION['shipping_methods']['minimum_order_amount'] = $shipping_methods[0]['minimum_order_amount'];
                
                $this->data['shipping_methods'] = $shipping_methods[0];
                $_SESSION['shipping_methods']['id'] = $shipping_methods[0]['id'];                               
                $shippingData  = '<b>Method : </b>' . $shipping_methods[0]['name'];
                $shippingData .= '<br/><b>Pincode : </b>' . $_SESSION['shipping_methods']['pincode'];
                $shippingData .= '<br/><b>Date&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : </b>' . $_SESSION['shipping_methods']['date'];
                $shippingData .= '<br/><b>Time&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : </b>' . ($_SESSION['shipping_methods']['time'] == '00:00' ? 'Any Time' : $_SESSION['shipping_methods']['time']);

                $this->data['shipping_info'] = $shippingData;
            }
        }

        $this->data['shopinfo'] = $shopinfo;

        if (!$this->shopinfo['eshop_overselling']) {
            //$this->data['products_stocks'] = $stocks = $this->shop_model->getProductsStocks($this->eshop_warehouse_id);
            $this->data['products_stocks']   = $stocks = $this->shop_model->getWarehouseProductsStocks($this->eshop_warehouse_id);
            $this->data['pending_orders']    = $this->shop_model->getPendingOrderItems($this->eshop_warehouse_id);
        }
        
        
        $this->eshop_active = (bool)$this->Settings->active_eshop ? $this->Settings->active_eshop : 0;
 
        if (!$this->eshop_active && $this->router->fetch_method() != "index") {

            redirect('shop/index');
        }
    }

    public function getHotProducts() {
        $products = $this->shop_model->getHotProducts($this->eshop_settings->display_top_products);
        return $products;
    }

    public function setShopTheme() {
        $this->data['shoptheme'] = $this->shoptheme = !empty($this->data['shopinfo']['default_eshop_theame']) ? $this->data['shopinfo']['default_eshop_theame'] : 'T1';
    }

    public function load_shop_view($method = '', $data = array()) {
        $method = isset($method) ? $method : '';
        $this->load->view($this->view_shop . $method, $data);
    }

    public function authenticate() {

        if (!$this->shop_model->session_authenticate()) {
            redirect('shop/login');
        }
    }

    public function exitInvalidSession() {

        if (!$this->shop_model->session_authenticate()) {
            redirect('shop/login');
        }
    }

    public function welcomeValidSession() {

        if ($this->shop_model->session_authenticate()) {
            redirect('shop/home');
        }
    }

    public function index() {

        if (!$this->eshop_active) {

            $this->load->view('default/views/shop/service_off', $this->data);
        } else {

            $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));
            if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {
                $this->welcomeValidSession();
            }

            $this->data['hot_products'] = $this->getHotProducts();

            if ($this->eshop_settings->user_landing_page == 'select_delivery_option' && !isset($_SESSION['shipping_methods'])) {

                $this->data['shipping_methods'] = $this->eshop_model->getShippingMethods(['is_active' => 1]);

                $this->load_shop_view('user_landing', $this->data);
            } else {

                $this->load_shop_view($this->shoptheme . '/index', $this->data);
            }
        }
    }

    public function reset_shipping_methods() {

        unset($_SESSION['shipping_methods']);
        redirect('shop/set_shipping_methods');
    }
    
    public function set_shipping_methods() {
        
        if($_POST['action']=="set_shippings"){
            
            $order_location = $_POST['order_received_outlet'] ? $_POST['order_received_outlet'] : $_POST['location'];
        
            $delivery_date = in_array($_POST['shipping_methods'] , ['delivery', 'pickup'] ) ? date('Y-m-d') : $_POST['delivery_date'];

            $_SESSION['shipping_methods'] = [
                "id" => NULL,
                "methods" => $_POST['shipping_methods'],
                "pincode" => $_POST['pincode'],
                "order_to_outlet" => $order_location,
                "location" => $_POST['location'],
                "date" => $delivery_date,
                "time" => $_POST['delivery_time'],
            ];

            if ($this->eshop_settings->active_multi_outlets) {
                $_SESSION['eshop_location_id'] = $order_location;
            }

            redirect('shop/index');
            
        } else {
            $this->data['shipping_methods'] = $this->eshop_model->getShippingMethods(['is_active' => 1]);

            $this->load_shop_view('user_landing', $this->data);
        }
    }               

    public function welcome() {


        //$this->authenticate();


        if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {
            $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));
            //$this->exitInvalidSession();
            $this->data['hot_products'] = $this->getHotProducts();
            $this->load_shop_view($this->shoptheme . '/welcome', $this->data);
        } else {
            redirect('shop/index');
            //$this->load_shop_view($this->shoptheme . '/index', $this->data);
        }
    }

    public function order_details() {

        $this->exitInvalidSession();

        $this->load_shop_view($this->shoptheme . '/order_details1', $this->data);
    }

    public function storeInfo() {
        $this->load->model('settings_model');
        //$res = $this->eshop_model->getSettings();
        $res = $this->eshop_model->getPosSettings();

        $config = $this->ci->config;
        $merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : null;
        $res->merchant_phone = $merchant_phone;
        //$res->offline_sale_reff=$this->site->getNextReference('offapp');
        if (is_object($res)):
            $data = array();
            foreach ($res as $key => $value) {
                $data[$key] = $value;
            }
            return $data;
        endif;

        return false;

        //return $storeInfo = $this->shop_model->storeDetails();
    }

    public function searchCategories() {

        $keyword = $_GET['keyword'];

        if (strlen($keyword) < 3)
            return false;

        $categoryList = $this->shop_model->searchCategory($keyword);

        if (is_array($categoryList)) {
            $html = '<ul class="no-style" style="padding:0px 20px;">';
            foreach ($categoryList as $key => $catArr) {

                $html .= '<li class="cursor-pointer"><a onClick="loadCategoryProducts(' . $catArr->id . ');">' . $catArr->name . '</a></li>';
            }
            echo $html .= '<li style="text-align:right;"><i style="color:red; cursor:pointer;" onclick="clearSearchCategory()">Clear Search</i></li></ul><hr/>';
        }
    }

    public function allCategories($keyword = '') {

        $allCategory = $this->shop_model->getCategory('ALL', $keyword);

        $categoryArr['default'] = $allCategory['default_category'];

        foreach ($allCategory as $key => $catArr) {

            if (in_array($key, ['status', 'count', 'default_category'])) {
                continue;
            }

            $categoryArr['list'][$catArr['parent_id']][$catArr['id']] = $catArr;
        }

        echo json_encode($categoryArr);
    }

    public function parentCategories() {

        $res = $this->shop_model->getParentCategories();

        if (is_array($res) && count($res)) {

            return $res;
        } else {
            return false;
        }
    }

    public function childCategories($parent_id = '') {

        if (empty($parent_id)):
            return FALSE;
        endif;

        $res = $this->shop_model->getChildCategories($parent_id);
        if (is_array($res) && count($res)) {

            return $res;
        } else {
            return false;
        }
    }

    public function allChildCategories() {

        $res = $this->shop_model->getAllChildCategories();
        if (is_array($res) && count($res)) {
            foreach ($res as $key => $catData) {
                $data[$catData['parent_id']][] = $catData;
            }
            return $data;
        } else {
            return false;
        }
    }

    public function loadCategories() {

        $categoryJson = $_POST['categoryJson'];

        if (empty($categoryJson)) {

            $categoryArr = $this->parentCategories();
            $category = $categoryArr['list'];
        } else {
            $category = json_decode($categoryJson);
        }

        if (is_array($category)) {

            foreach ($category as $key => $catArr) {
                ?>
                <div class="panel">
                    <span onclick="loadSubCategory(<?= $key ?>);" type="button" class="panel-heading panel-title" data-toggle="collapse" data-target="#collapsible-<?= $key; ?>" data-parent="#myAccordion"><?php echo $catArr['name']; ?> <span class="pull-right">(<?php echo $catArr['subcat_count']; ?>) <i class="fa fa-angle-double-down"></i></span></span>
                    <div id="collapsible-<?= $key; ?>" class="collapse">
                        <div class="panel-body" style="padding:0" id="subcategory_list_<?= $key; ?>"></div>
                    </div>
                </div>
                <?php
            }//endforeach.
        }//end if.
    }

    public function loadSubcategory() {

        $parent_id = $_GET['parent_id'];

        $this->load->model('products_model');

        $subcategoryArr = $this->childCategories($parent_id);

        if (is_array($subcategoryArr)) {

            foreach ($subcategoryArr as $catArr) {
                $list .= '<div style="font-weight: normal;cursor: pointer; text-transform: capitalize;" onClick="loadCategoryProducts(' . $catArr['id'] . ');" class="category-link">' . strtolower($catArr['name']) . '<span class="pull-right">(' . $catArr['products_count'] . ') <i class="fa fa-chevron-right"></i></span></div>';
            }//end foreach.

            echo $list;
        } else {
            echo '';
        }
    }

    public function Pagignations($pagingData) {

        $categoryhash = $pagingData['categoryhash'];
        $total_records = $pagingData['count'];
        $active_pageno = $pagingData['pageno'];
        $itemsPerPage = $pagingData['itemsPerPage'];
        $pagCallFunction = $pagingData['pagCallFunction'];
        $displayPage = (!empty($pagingData['displayPage'])) ? $pagingData['displayPage'] : 5;

        if ($total_records <= $itemsPerPage)
            return false;

        $pagelist = ceil($total_records / $itemsPerPage);

        $pagignation = '<ul class="pagination pagination-sm" style="margin-top: 0px; margin-bottom: 0px; baground-color:#FFF !important;">';

        $prePage = $active_pageno - 1;
        $nextPage = $active_pageno + 1;

        if ($active_pageno == 1) {
            $pagignation .= '<li class="disabled"><a>&laquo;</a></li>';
        }

        if ($active_pageno > 1) {
            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(' . $prePage . ')">&laquo;</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $prePage . ')">&laquo;</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/' . $prePage) . '">&laquo;</a></li>';
            }
        }

        $initpage = ($displayPage < $active_pageno && $pagelist > $displayPage ) ? ceil($active_pageno - ($displayPage / 2)) : 1;

        if ($initpage > 1) {
            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(1)">1</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',\'1\')">1</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/1') . '">1</a></li>';
            }

            $pagignation .= '<li class="disabled"><a>...</a></li>';
        }

        for ($i = 1; $i <= $displayPage; $i++) {

            $p = $initpage;

            if ($p > $pagelist)
                break;

            $activeClass = ($active_pageno == $p) ? ' class="active" ' : '';

            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li ' . $activeClass . ' ><a onclick="' . $pagCallFunction . '(' . $p . ')">' . $p . '</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li ' . $activeClass . ' ><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $p . ')">' . $p . '</a></li>';
            } else {
                $pagignation .= '<li ' . $activeClass . ' ><a href="' . base_url('shop/home/' . $categoryhash . '/' . $p) . '">' . $p . '</a></li>';
            }


            $initpage++;
        }

        if ($pagelist > $displayPage && $pagelist > $p) {
            $pagignation .= '<li><a>...</a></li>';

            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(' . $pagelist . ')">' . $pagelist . '</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $pagelist . ')">' . $pagelist . '</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/' . $pagelist) . '">' . $pagelist . '</a></li>';
            }
        }

        if ($active_pageno < $pagelist) {

            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(' . $nextPage . ')">&raquo;</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $nextPage . ')">&raquo;</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/' . $nextPage) . '">&raquo;</a></li>';
            }
        }
        if ($active_pageno == $pagelist) {
            $pagignation .= '<li class="disabled"><a>&raquo;</a></li>';
        }

        $pagignation .= ' </ul>';

        return $pagignation;
    }

    public function viewCatlogProducts() {
        echo $this->catlogProducts();
    }

    /*
     * Para: $category may be int id or md5() hash of id
     */

    public function productNavigations($category) {

        $details = $this->shop_model->category_navigation($category);

        $navegation = '<i class="fa fa-sitemap"></i> PRODUCTS / ';
        if (!empty($details[0]['parent'])) {
            $navegation .= $details[0]['parent'] . ' / ';
        }
        $navegation .= $details[0]['category'];
        $navegation .= '<small> (' . $details[0]['products_count'] . ' Items) </small>';

        return $navegation;
    }

    public function catlogProducts() {

        $catlogProductView = '';
        $catId = isset($_GET['catId']) ? $_GET['catId'] : $this->data['default_category'];

        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : $this->data['per_page_items'];

        $keyword = (isset($_GET['keyword']) && !empty($_GET['keyword'])) ? $_GET['keyword'] : '';

        $pageno = (empty($page)) ? 1 : $page;
        $itemsPerPage = (empty($limit)) ? 20 : $limit;

        if (!empty($keyword)) {
            $catlog = $this->shop_model->searchProducts($keyword, $pageno, $itemsPerPage);
            $navigation = "Search Results For : <b><q>$keyword</q></b> ";
        } else {
            $catlog = $this->shop_model->getCategoryProducts($catId, $pageno, $itemsPerPage);
            $navigation = $this->productNavigations($catId);
        }

        if ($catlog['totalPages'] > 1) {

            $pagingData['count'] = $catlog['count'];
            $pagingData['pageno'] = $page;
            $pagingData['itemsPerPage'] = $limit;
            $pagingData['pagCallFunction'] = 'loadPageProducts';
            $pagingData['displayPage'] = 10;
            $pagingData['load_ajax'] = TRUE;
            $pagingData['categoryhash'] = md5($catId);

            $pagignation = $this->Pagignations($pagingData);
        }

        if ($catlog['count'] > 0) {

            $catlogProductView = '<div class="row" style="padding:5px 0px;"><div class="col-sm-12 text-primary">' . $navigation . '</div></div>
               <div class="row search_box" style="padding:5px 0px;">
                    <div class=" col-sm-9">
                        <div class="sortby">
                         ' . $pagignation . '    
                        </div>
                    </div>
                    <div class="col-sm-3" >
                        <div class="input-group input-group-sm">
                            <input type="text" id="searchProducts" value="' . $keyword . '" placeholder="Search Products" class="form-control">
                            <span class="input-group-btn">
                              <button type="button"  onClick="searchProducts();" class="btn btn-info btn-flat">Go!</button>
                            </span>
                        </div>
                    </div> 
                </div>';


            foreach ($catlog['items'] as $product) {


                if ($this->session->userdata('id') > 0) {
                    $product = (array) get_product_price($product, $this->session->userdata('id'));
                }

                $catlogProductView .= '<div class="col-sm-3 col-xs-6">
                    <div class="product-image-wrapper">
                        <div class="single-products">
                            <div class="productinfo text-center">
                                <div class="image-outer">
                                    <img src="' . $this->data['thumbs'] . $product['image'] . '" alt="' . $product['code'] . '" />                                            
                                </div>
                                <h2>' . $this->Settings->symbol . ' ' . number_format($product['price'], 0) . '</h2>
                                <p>' . $product['name'] . '</p>
                                <a data-target="#" onclick="addToCart(' . $product['id'] . ')" class="hvr-pop btn btn-default add-to-cart"><i class="fa fa-shopping-cart"></i>Add to cart</a>
                            </div>
                        </div>
                    </div>
                </div>';
            }//end foreach. 
        } else {

            $catlogProductView .= '<div class="alert alert-ifo">Zero products available.</div>';
        }//end else.

        return $catlogProductView;
    }

    public function searchProducts($keyword, $pageno = 1, $itemsPerPage = 12) {

        if (empty($keyword))
            return false;

        $catlog_products = $this->shop_model->searchProducts($keyword, $pageno, $itemsPerPage);
    }

    public function home() {
//        $this->authenticate();

        if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {

            $default_category = false;
            if (is_array($this->data['category'])) {
                foreach ($this->data['category'] as $category) {
                    if ($category['id'] == $this->data['shopinfo']['default_category']) {
                        $default_category = $category['id'];
                        break;
                    }
                }
            }

            $this->data['default_category'] = ($default_category) ? $default_category : $this->data['category'][0]['id'];
            $this->data['page_no'] = $pagingData['pageno'] = ($this->uri->segment(4)) ? $this->uri->segment(4) : 1;
            $this->data['per_page_items'] = $pagingData['itemsPerPage'] = 16;
            $this->data['brands'] = $this->shop_model->brandList();
            $this->data['price'] = $this->shop_model->getPriceList();

            $product_category_hash = empty($this->uri->segment(3)) ? md5($this->data['default_category']) : $this->uri->segment(3);

            if ($this->shoptheme == 'T1') {
                $this->data['default_products'] = $this->catlogProducts();
            } else {
                $this->data['subCategories'] = $this->allChildCategories();

                if ($_POST['action'] == "search_products" && !empty($this->input->post('search_keyword'))) {
                    $this->data['catlogProducts'] = $this->shop_model->searchProducts($this->input->post('search_keyword'), $this->input->post('page'), $pagingData['itemsPerPage'], $this->eshop_warehouse_id);
                    $pagingData['search_products'] = $this->input->post('search_keyword');
                    $pagingData['pageno'] = $this->input->post('page');
                    $this->data['navigation'] = "Search Products For : <q>" . $this->input->post('search_keyword') . "</q> <small>(Found " . $this->data['catlogProducts']['count'] . " Items)</small>";
                } else {

                    $this->data['catlogProducts'] = $this->shop_model->getCategoryProducts($product_category_hash, $pagingData['pageno'], $pagingData['itemsPerPage'], $this->eshop_warehouse_id);

                    $this->data['navigation'] = $this->productNavigations($product_category_hash);
                    $this->data['selectCategory'] = $this->shop_model->category_navigation($product_category_hash);
                }//end else

                if ($this->data['catlogProducts']['totalPages'] > 1) {
                    $pagingData['count'] = $this->data['catlogProducts']['count'];
                    $pagingData['pagCallFunction'] = 'loadPageProducts';
                    $pagingData['displayPage'] = 10;
                    $pagingData['load_ajax'] = FALSE;
                    $pagingData['categoryhash'] = $product_category_hash;

                    $this->data['pagignation'] = $this->Pagignations($pagingData);
                }
            }//end else
            $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));
            
            $this->load_shop_view($this->shoptheme . '/home', $this->data);
        } else {
            redirect('shop');
            //  $this->load_shop_view($this->shoptheme . '/index', $this->data);
        }
    }

    public function product_info() {

        //$this->authenticate();

        $product_hash = empty($this->uri->segment(3)) ? redirect('home') : $this->uri->segment(3);

        if ($this->shoptheme == 'T1') {
            redirect('home');
        } else {
            $this->data['product'] = $product = $this->shop_model->getProductInfoByHash($product_hash);
            $pro_id = $this->data['product']['id'];
            $this->data['images']   = $this->shop_model->getProductImagesByHash($product_hash);
            $this->data['veriants'] = $this->shop_model->getProductVeriantsById($pro_id);
            $this->data['images']   = $this->shop_model->getProductPhotos($pro_id);
            $catid = ($product['subcategory_id']) ? $product['subcategory_id'] : $product['category_id'];
            $this->data['navigation'] = $this->productNavigations($catid);
        }//end else



        $category[] = $this->shop_model->getCategoryName($product['category_id']);
        $category[] = $this->shop_model->getCategoryName($product['subcategory_id']);
        $this->data['navigation'] = $category;
        $this->load_shop_view($this->shoptheme . '/product_details', $this->data);
    }

    public function addCartItems() {

        // $this->authenticate();

        $productArr = explode('_', $_GET['product_id']);
        $product_id = $productArr[0];

        $qty = (isset($_GET['qty']) && $_GET['qty']) ? $_GET['qty'] : 0;

        $optionArray = $_GET['option'] ? explode('~', $_GET['option']) : array();
        if (count($optionArray)) {
            $option_id = $optionArray[0];
            $option_name = $optionArray[1];
            $option_price = $optionArray[2];

            $_SESSION['cart'][$product_id . '_' . $option_id]['product_id'] = $product_id;
            $_SESSION['cart'][$product_id . '_' . $option_id]['option_id'] = $option_id;
            $_SESSION['cart'][$product_id . '_' . $option_id]['option_name'] = $option_name;
            $_SESSION['cart'][$product_id . '_' . $option_id]['option_price'] = $option_price;

            if (isset($_SESSION['cart'][$product_id . '_' . $option_id])) {

                $qty ? $_SESSION['cart'][$product_id . '_' . $option_id]['qty'] = $qty : $_SESSION['cart'][$product_id . '_' . $option_id]['qty'] += 1;
            } else {
                $_SESSION['cart'][$product_id . '_' . $option_id]['qty'] = 1;
            }
        } else {

            $_SESSION['cart'][$product_id]['product_id'] = $product_id;

            if (isset($_SESSION['cart'][$product_id])) {
                $qty ? $_SESSION['cart'][$product_id]['qty'] = $qty : $_SESSION['cart'][$product_id]['qty'] += 1;
            } else {
                $_SESSION['cart'][$product_id]['qty'] = 1;
            }
        }//end else        

        echo $this->updateCartCount();
    }

    public function updateCartCount() {

        $sum = 0;

        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $pid => $arr) {
                $sum += $arr['qty'];
            }
        }
        return $sum;
    }

    public function updateCartItems() {

        $product_id = $_GET['product_id'];
        $action = $_GET['action'];

        if (isset($_SESSION['cart'][$product_id])) {
            if ($action == '-') {
                $_SESSION['cart'][$product_id]['qty'] -= 1;
            }
            if ($action == '+') {
                $_SESSION['cart'][$product_id]['qty'] += 1;
            }

            if ($_SESSION['cart'][$product_id]['qty'] <= 0) {
                unset($_SESSION['cart'][$product_id]);
            }
        }

        echo $_SESSION['cart'][$product_id]['qty'];
    }

    public function removeCartItems() {

        $product_id = isset($_GET['id']) ? $_GET['id'] : $this->uri->segment(3);

        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }

        redirect('shop/cart');
    }

    public function clearCart() {
        unset($_SESSION['cart']);
        redirect('shop/home');
    }

    public function cart() {
        // $this->authenticate();
        /* if (count($_SESSION['cart']) <= 0) {
          redirect('shop/home');
          } */
        if (count($_SESSION['cart']) > 0) {
            $productIds = [];
            foreach ($_SESSION['cart'] as $key) {
                $productIds[] = $key['product_id'];
            }
            $optionId = array_values($_SESSION['cart']);
            $optionIds = $optionId[0]['option_id'];
            $items = $this->shop_model->getProductInfo($productIds);
            $this->data['eshop_order_tax'] = $this->shop_model->getOrdertax();
            foreach ($_SESSION['cart'] as $key => $value) {
                $ArrExplode = explode('_', $key);
                $keys = $ArrExplode[0];
                //if ($this->session->userdata('id') > 0) {
                $items[$key] = (array) get_product_price($items[$keys], $this->session->userdata('id'));
                //}
                $this->data['cart'][$key] = $items[$key];
                $this->data['cart'][$key]['qty'] = $value['qty'];
                $this->data['cart'][$key]['option_id'] = $value['option_id'];
                $this->data['cart'][$key]['option_name'] = $value['option_name'];
                $this->data['cart'][$key]['option_price'] = $value['option_price'];
            }//end foreach
        }

        $this->data['taxes']['methods'] = $this->getTaxMethods();
        $this->data['taxes']['attribs'] = $this->getTaxAttribs();
        $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));

        $this->load_shop_view($this->shoptheme . '/cart', $this->data);
    }

    public function checkout() {
        $this->authenticate();
        $userdata = $this->session->userdata();
        $user_id = $userdata['id'];
        $user_state_code = $this->sma->getstatecode($user_id);
        $billers = $this->site->get_setting();
        $billers_id = $billers->default_biller;
        $billers_state_code = $this->sma->getstatecode($billers_id);
        $GSTType = ($user_state_code == $billers_state_code) ? 'GST' : 'IGST';

        $cartData = $_POST;
                    
        if (count($cartData['items']) <= 0) {
            redirect('shop/cart');
        }
        $tax_methods = $this->getTaxMethods();
        $cart['cart_sub_total'] = $cartData['cart_sub_total'];
        $cart['cart_tax_total'] = str_replace(",", "", $cartData['cart_tax_total']);
        $cart['order_tax_total'] = $cartData['order_tax_total'];
        $cart['cart_gross_rounding'] = $cartData['cart_gross_rounding'];
        $cart['cart_gross_total'] = $cartData['cart_gross_total'];
        $cart['item_quantity_total'] = $cartData['item_quantity_total'];

        $cart['order_tax_id'] = $cartData['order_tax_id'];
        $cart['order_tax_name'] = $cartData['order_tax_name'];
        $cart['note'] = $cartData['note'];
        $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));
        $itemcount = 0;
        foreach ($cartData['items_ids'] as $key => $item_id) {
            // print_r($item_ids);
            // $ArrExplode = explode('_',$item_id);
            // $item_ids=$ArrExplode[0];
            // $option_ids=$ArrExplode[1];

            $itemcount++;
            $productIds[] = $item_id;
            if ($cartData['item_option_id'][$key]) {
                $productVerientsIds[] = $cartData['item_option_id'][$key];
            }

            /*             * Auto GST 11-8-2020* */
            $taxrate = $tax_methods[$cartData['item_tax_id'][$key]]['rate'];
            $taxdata = array();
            if ($GSTType == 'IGST') {
                $tax_rate = $taxrate;
                $taxArr['name'] = "Integrated Goods and Service Tax";
                $taxArr['code'] = "IGST";
                $taxArr['percentage'] = $tax_rate;
                $taxdata[] = $taxArr;
            } else {
                $taxvatype = array('CGST' => 'Central Goods and Service Tax', 'SGST' => 'State Goods and Service Tax');
                $tax_rate = ($taxrate / 2);

                foreach ($taxvatype as $keys => $taxval) {

                    $taxArr['name'] = $taxval;
                    $taxArr['code'] = $keys;
                    $taxArr['percentage'] = $tax_rate;
                    $taxdata[] = $taxArr;
                }
            }

            $gstAttrs = $taxdata;

            /* $gstAttrs = (!empty($tax_methods) && isset($tax_methods[$cartData['item_tax_id'][$key]])) ? 
              $tax_methods[$cartData['item_tax_id'][$key]]['tax_config'] : ''; */
            /*             * *** */

            $item_subtotal = $cartData['item_price_total'][$key];
            $item_tax_rate = ($cartData['item_tax_rate'][$key]) ? $cartData['item_tax_rate'][$key] : 0;
            $item_tax_total = ($cartData['item_tax_total'][$key]) ? $cartData['item_tax_total'][$key] : 0;

            $cart['items'][$key] = array(
                'item_id' => $item_id,
                'qty' => $cartData['qty'][$key],
                'item_tax_method' => $cartData['item_tax_method'][$key],
                'item_tax_type' => $cartData['item_tax_type'][$key],
                'item_tax_rate' => $cartData['item_tax_rate'][$key],
                'real_unit_price' => $cartData['real_unit_price'][$key],
                'actual_real_unit_price' => $cartData['actual_real_unit_price'][$key],
                'item_price' => $cartData['item_price'][$key],
                'item_tax_total' => $cartData['item_tax_total'][$key],
                'item_price_total' => $cartData['item_price_total'][$key],
                'item_option_id' => $cartData['item_option_id'][$key],
                'item_option_name' => $cartData['item_option_name'][$key],
                'item_option_price' => $cartData['item_option_price'][$key],
                'product_remark' => $cartData['product_remark'][$key],
            );


            //To set Tax Attributes.
            if (!empty($gstAttrs)) {
                foreach ($gstAttrs as $gstattr) {
                    $cart['items'][$key]['tax_attr'][$gstattr['code']] = [
                        'percentage' => number_format($gstattr['percentage'], 2),
                        'name' => $gstattr['name'],
                        'taxamt' => ($item_subtotal * $gstattr['percentage'] / 100),
                    ];
                }//end foreach.
            }
        }//end foreach.
        $cart['itemcount'] = $itemcount;

        $products = $this->shop_model->getProductInfo($productIds);

        foreach ($cartData['items_ids'] as $prodkey => $prod_id) {
            //foreach ($products as $pid => $prodata) {

            $cart['items'][$prodkey]['code'] = $products[$prod_id]['code'];
            $cart['items'][$prodkey]['name'] = $products[$prod_id]['name'];
            $cart['items'][$prodkey]['image'] = $products[$prod_id]['image'];
            $cart['items'][$prodkey]['hsn_code'] = $products[$prod_id]['hsn_code'];
            $cart['items'][$prodkey]['brand'] = $products[$prod_id]['brand'];
            if ($cartData['item_option_name'][$prodkey]) {
                $cart['items'][$prodkey]['vname'] = $cartData['item_option_name'][$prodkey];
            }
        }//end foreach
        // $this->data['store'] = $this->storeInfo();
        $this->data['eshop_order_tax'] = $this->shop_model->getOrdertax();
        $this->data['cart'] = $cart;
        $this->data['shipping_methods'] = $this->shipping_methods();
        $this->data['payment_methods'] = $this->payment_methods();
        $billing_shipping = $this->shop_model->get_billing_shipping($this->data['user_id']);
        /* if (isset($this->data['billing_shipping']) && $this->data['billing_shipping'] === false) {
          $this->data['customer'] = (array) $this->customer_info();
          } else { */
        $this->data['billing_shipping'] = (array) $billing_shipping[0];
        //}
        $this->data['customer'] = (array) $this->customer_info();
        $this->data['state'] = $this->shop_model->getState();
        $this->load_shop_view($this->shoptheme . '/checkout', $this->data);
    }

    public function payment() {

        $this->authenticate();

        if (empty($_POST)) {
            redirect('shop/checkout');
        }
        $checkoutData = $_POST;
                    
        $this->data['payment_methods'] = $this->payment_methods();
        $this->data['checkoutData'] = $checkoutData;
        $this->data['order_data'] = unserialize($checkoutData['order_data']);
                    
        $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));
        $this->data['withordertax_amount'] = $checkoutData['withordertax_amount'];
        $this->data['order_tax_id'] = $checkoutData['order_tax_id'];
        $this->data['order_tax'] = $checkoutData['order_tax'];
        $this->data['shipping_amount'] = $checkoutData['shipping_amount'];
        $this->load_shop_view($this->shoptheme . '/payment', $this->data);
    }

    public function submit_payment() {

        $IGST = $CGST = $SGST = 0;

        $this->authenticate();
        $checkoutData = unserialize($_POST['checkoutData']);
        $cart = unserialize($checkoutData['order_data']);

        $payment_methods = $_POST['payment_type'];
        $payment_type_id = $_POST['payment_type_id'];
        $shipping_amount = $_POST['shipping_amount'];

        /*         * system setting default biller* */
        $setting = $this->site->get_setting();
        $biller_id = isset($_SESSION['eshop_biller_id']) ? $_SESSION['eshop_biller_id'] : $setting->default_biller;
        $biller = $this->site->getCompanyByID($biller_id);
        $biller_name  = $biller->name;
        $biller_phone = $biller->phone;
          
        $order_tax_amount = $cart['order_tax_total'];
        $ordertax_id = $cart['order_tax_id'];
        $cart_gross_rounding = $cart['cart_gross_rounding'];
        $item_quantity_total = $cart['item_quantity_total'];
        $cart_gross_total = str_replace(",", "", $cart['cart_gross_total']);

        $DeliverLater = isset($_SESSION['shipping_methods']['date']) ? $_SESSION['shipping_methods']['date'] : (($checkoutData['DeliverLater']) ? date('Y-m-d', strtotime($checkoutData['DeliverLater'])) : NULL);

        $timeslote = isset($_SESSION['shipping_methods']['time']) ? $_SESSION['shipping_methods']['time'] : (isset($checkoutData['time_slotes']) ? $checkoutData['time_slotes'] : NULL);

        $shopinfo = $this->data['shopinfo'];

        $this->load->model('sales_model');

        $unites = $this->shop_model->getUnites();

        $cart_items_count = $cart['itemcount'];

        $order_tax_id = ($cart['order_tax_id']) ? $cart['order_tax_id'] : 0;

        //$order_tax = ($cart['order_tax']) ? $cart['order_tax'] : 0;

        $order_tax = ($cart['order_tax_total']) ? $cart['order_tax_total'] : 0;

        $totalTax = ($cart['order_tax_total'] + $cart['cart_tax_total']);

        $ref_No  = $this->site->getReferenceNumber('eshop');
        $orderno = $this->site->getReferenceNumber('ordr');

        $userdata = $this->session->userdata();

        $user_id    = $userdata['id'];
        $user_email = $userdata['email'];
        $user_name  = $userdata['name'];
        $user_phone = $userdata['phone'];
        $order_date = date('Y-m-d H:i:s');

        $shipping_method_id  = isset($_SESSION['shipping_methods']['id']) ? $_SESSION['shipping_methods']['id'] : $checkoutData['shippingType'];

        $shippingMethodInfo = $this->eshop_model->getShippingMethods(['id' => $shipping_method_id]);
        $shippingMethodName = $shippingMethodInfo[0]['name'];

        $cf1 = ($checkoutData['cf1']) ? $checkoutData['cf1'] : '';
        $cf2 = ($checkoutData['cf2']) ? $checkoutData['cf2'] : '';

        $grand_total = $cart_gross_total + $shipping_amount;
        //  "biller_id" => $shopinfo['default_biller'],"biller" => $shopinfo['biller_name'],
        
//        $eshop_warehouse = (($shopinfo['default_eshop_warehouse']) ? $shopinfo['default_eshop_warehouse'] : $this->Settings->default_warehouse);
//        
//        if($shippingMethodInfo[0]['order_to_warehouse'] && $this->eshop_setting->active_multi_outlets ){
//            $warehouse_id = $shippingMethodInfo[0]['order_to_warehouse'];
//        } else {
//            $warehouse_id = $eshop_warehouse;
//        }
        
        $order = array(
            "date" => $order_date,
            "reference_no" => $ref_No,
            "invoice_no" => $orderno,
            "customer_id" => $user_id,
            "customer" => $user_name,
            "biller_id" => $biller_id,
            "biller" => $biller_name,
            "warehouse_id" => $_SESSION['eshop_location_id'],
            "total" => str_replace(",", "", $cart['cart_sub_total']),
            "product_discount" => 0,
            "order_discount_id" => '',
            "order_discount" => 0,
            "total_discount" => 0,
            "product_tax" => $cart['cart_tax_total'],
            "order_tax_id" => $order_tax_id,
            "order_tax" => $order_tax,
            "total_tax" => $totalTax,
            "shipping" => $shipping_amount,
            "grand_total" => $grand_total - (($cart['cart_gross_rounding']) ? $cart['cart_gross_rounding'] : 0),
            "sale_status" => "pending",
            "payment_status" => "due",
            "delivery_status" => "pending",
            "total_items" => $item_quantity_total,
            "paid" => 0,
            "pos" => 0,
            "eshop_sale" => 1,
            "cf1" => $cf1,
            "cf2" => $cf2,           
            "rounding" => $cart['cart_gross_rounding'],
            "eshop_order_alert_status" => 0,
            "eshop_order_alert_status" => 0,
            "deliver_later" => ($DeliverLater) ? $DeliverLater : NULL,
            "time_slotes" => $timeslote,
            "note" => $cart['note'],
            "shipping_method" => $shipping_method_id,
            "shipping_outlet" => isset($_SESSION['shipping_methods']['location']) ? $_SESSION['shipping_methods']['location'] : $_SESSION['eshop_location_id'],
        );


        $orderData = $checkoutData;

        $billing_shipping = array(
            "billing_name" => $orderData['billing_name'],
            "billing_gstn_no" => $orderData['billing_gstn_no'],
            "billing_phone" => $orderData['billing_phone'],
            "billing_email" => $orderData['billing_email'],
            "billing_addr1" => $orderData['billing_addr1'],
            "billing_addr2" => $orderData['billing_addr2'],
            "billing_city" => $orderData['billing_city'],
            "billing_state" => $orderData['billing_state'],
            "billing_country" => $orderData['billing_country'],
            "billing_zipcode" => $orderData['billing_zipcode'],
            //  "shipping_billing_is_same" => $orderData['shipping_billing_is_same'],
            //  "save_info" => $orderData['save_info'],
            "shipping_name" => $orderData['shipping_name'],
            "shipping_phone" => $orderData['shipping_phone'],
            "shipping_email" => $orderData['shipping_email'],
            "shipping_addr1" => $orderData['shipping_addr1'],
            "shipping_addr2" => $orderData['shipping_addr2'],
            "shipping_city" => $orderData['shipping_city'],
            "shipping_state" => $orderData['shipping_state'],
            "shipping_country" => $orderData['shipping_country'],
            "shipping_zipcode" => isset($_SESSION['shipping_methods']['pincode']) ? $_SESSION['shipping_methods']['pincode'] : $orderData['shipping_zipcode'],
            "shippingType" => isset($_SESSION['shipping_methods']['methods']) ? $_SESSION['shipping_methods']['methods'] : $orderData['shippingType'],
            "paymentType" => $payment_type_id,
        );

        if (is_array($cart['items']) && count($cart['items'])) {

            $order_sale_id = $this->eshop_model->add_sale_order($order);

            //If sale insert successfully.
            if ($order_sale_id) {
                $notify['sale_id'] = $order_sale_id;
                //Get Eshop shipping info              
                $e_order['is_cod'] = ($payment_methods == 'cod') ? 'YES' : 'NO';
                $e_order['shipping_method_name'] = $shippingMethodInfo[0]['name'];
                $e_order['sale_id'] = $order_sale_id;
                $e_order['date']    = $order_date;
                $e_order['customer_id']     = $user_id;
                $e_order['billing_name']    = $orderData['billing_name'];
                $e_order['billing_email']   = $orderData['billing_email'];
                $e_order['billing_phone']   = $orderData['billing_phone'];

                $billing_addr = $orderData['billing_addr1'] . ', ' . $orderData['billing_addr2'];
                $billing_addr .= ', ' . $orderData['billing_city'];
                $billing_addr .= ', ' . $orderData['billing_state'];
                $billing_addr .= ', ' . $orderData['billing_country'];
                $billing_addr .= '-'  . $orderData['billing_zipcode'];

                $e_order['billing_addr']    = $billing_addr;
                $e_order['shipping_name']   = $orderData['shipping_name'];
                $e_order['shipping_email']  = $orderData['shipping_email'];
                $e_order['shipping_phone']  = $orderData['shipping_phone'];

                $shippingAddress = $orderData['shipping_addr1'] . ', ' . $orderData['shipping_addr2'];
                $shippingAddress .= ', ' . $orderData['shipping_city'];
                $shippingAddress .= ', ' . $orderData['shipping_state'];
                $shippingAddress .= ', ' . $orderData['shipping_country'];
                $shippingAddress .= '-'  . (isset($_SESSION['shipping_methods']['pincode']) ? $_SESSION['shipping_methods']['pincode'] : $orderData['shipping_zipcode']);

                $e_order['shipping_addr'] = $shippingAddress;

                //Insert Eshop order details.
                $order_id = $this->eshop_model->addOrder($e_order);

                //Update Eshop sale refference no.
                $updateReference        = $this->site->updateReference('eshop');
                $updateReference_order  = $this->site->updateReference('ordr');

                //Fourcefully save billing & shipping info

                if ($orderData['save_info'] == 1):
                    //-------------------------------- Saving Billing Shippimg Info -----------------------------
                    $param = $billing_shipping;
                    unset($param['billing_gstn_no']);
                    unset($param['shippingType']);
                    unset($param['paymentType']);
                    $this->load->model('companies_model');
                    $this->companies_model->set_billing_shiiping_info($this->data['user_id'], $param);
                //-------------------------------- Saving Billing Shippimg Info -----------------------------
                endif;

                //add order Items
                foreach ($cart['items'] as $pid => $cartitems) {

                    $item_code = $cartitems['code'];

                    $product_details = $item_type != 'manual' ? $this->pos_model->getProductByCode($item_code) : null;

                    $productinfo = (array)$product_details;

                    $mrp = ($productinfo['mrp']) ? $productinfo['mrp'] : 0;

                    $pr_discount            = $unit_discount = $item_discount = 0;
                    $percentage             = '%';
                    $actual_real_unit_price = $cartitems['actual_real_unit_price'];
                    $real_unit_price        = $cartitems['real_unit_price'];
                    $item_price             = $cartitems['item_price'];

                    $item_quantity = $item_unit_quantity = $cartitems['qty'];

                    if (isset($item_discount) && $item_discount) {
                        $discount = $item_discount;
                        $dpos = strpos($discount, $percentage);
                        if ($dpos !== false) {
                            $pds = explode("%", $discount);
                            $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($real_unit_price)) * (Float) ($pds[0])) / 100), 6);
                            // $pr_discount = $this->sma->formatDecimal(((($this->sma->formatDecimal($item_price)) * (Float) ($pds[0])) / 100), 6);
                        } else {
                            $pr_discount = $this->sma->formatDecimal($discount, 6);
                        }
                    }//end if.

                    $unit_discount = $pr_discount;

                    $item_unit_price_less_discount = $this->sma->formatDecimal($real_unit_price - $unit_discount, 6);
                    // $item_unit_price_less_discount = $this->sma->formatDecimal($item_price - $unit_discount, 6);
                    $item_net_price     = $net_unit_price = $item_unit_price_less_discount;
                    $pr_item_discount   = $this->sma->formatDecimal($pr_discount * $item_unit_quantity, 6);
                    $product_discount  += $pr_item_discount;
                    $pr_tax         = 0;
                    $pr_item_tax    = 0;
                    $item_tax       = 0;
                    $unit_tax       = 0;
                    $tax            = "";
                    $tax_method     = '';
                    $net_unit_price = $item_unit_price_less_discount;
                    $unit_price     = $item_unit_price_less_discount;
                    $invoice_unit_price     = $item_unit_price_less_discount;
                    $invoice_net_unit_price = ($item_unit_price_less_discount + $unit_discount);
                    //echo $item_tax_rate; exit;
                    $item_tax_rate = $product_details->tax_rate;
                    if (isset($item_tax_rate) && (int) $item_tax_rate > 0) {
                        $tax_method = $product_details->tax_method;
                        $pr_tax = $item_tax_rate;
                        $tax_details = $this->site->getTaxRateByID($pr_tax);
                        //Tax Type In Percentage (%)
                        if ($tax_details->type == 1 && $tax_details->rate != 0) {

                            if ($product_details && $tax_method == 1) {
                                //Exclusive Tax Calculations
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / 100, 6);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {
                                //Inclusive Tax Calculations  ($tax_method = 0 ) 
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / (100 + $tax_details->rate), 6);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $item_unit_price_less_discount - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }

                            $unit_tax = $item_tax;
                        } elseif ($tax_details->type == 2) {
                            //Tax Type is Fixed Amount
                            if ($product_details && $tax_method == 1) {
                                //Exclusive Tax Calculations ($tax_method = 1)
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / 100, 6);
                                $tax = $tax_details->rate . "%";

                                $net_unit_price = $item_unit_price_less_discount;
                                $unit_price = $item_unit_price_less_discount + $item_tax;

                                $invoice_unit_price = $item_unit_price_less_discount;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount + $item_tax;
                            } else {

                                //Inclusive Tax Calculations ($tax_method = 0  )
                                $item_tax = $this->sma->formatDecimal((($item_unit_price_less_discount) * $tax_details->rate) / (100 + $tax_details->rate), 6);
                                $tax = $tax_details->rate . "%";
                                $item_net_price = $item_unit_price_less_discount - $item_tax;

                                $net_unit_price = $item_unit_price_less_discount - $item_tax;
                                $unit_price = $item_unit_price_less_discount;

                                $invoice_unit_price = $item_unit_price_less_discount - $item_tax;
                                $invoice_net_unit_price = $item_unit_price_less_discount + $unit_discount;
                            }//end else

                            $item_tax = $this->sma->formatDecimal($tax_details->rate, 6);
                            $tax = $tax_details->rate;
                        }
                        $pr_item_tax = $this->sma->formatDecimal(($item_tax * $item_unit_quantity), 6);

                        $unit_tax = $item_tax;
                    }//end if.

                    $product_tax += $this->sma->formatDecimal(($unit_tax * $item_quantity), 6);
                    $item_unit  = $productinfo['unit'];
                    $unit       = $this->site->getUnitByID($item_unit);
                    $mrp        = isset($product_details->mrp) && !empty($product_details->mrp) ? $product_details->mrp : $item_net_price;

                    $invoice_unit_price     = $this->sma->formatDecimal($invoice_unit_price, 4);
                    $invoice_net_unit_price = $this->sma->formatDecimal($invoice_net_unit_price, 4);
                    $invoice_total_net_unit_price = $this->sma->formatDecimal(($invoice_net_unit_price * $item_quantity), 4);
                    $net_unit_price         = $this->sma->formatDecimal($net_unit_price, 4);
                    $unit_price             = $this->sma->formatDecimal($unit_price, 4);
                    $net_price              = $this->sma->formatDecimal(($mrp * $item_quantity), 4);
                    $subtotal               = $this->sma->formatDecimal(($unit_price * $item_quantity), 4);
                    $unit_quantity          = $cartitems['qty'];

                    if ($cartitems['item_option_id']) {
                        $verient    = $this->site->getVerientById($cartitems['item_option_id']);
                        $verient_unit_quantity = (float) ($verient['unit_quantity']) ? $verient['unit_quantity'] : 1;
                        $quantity   = $verient_unit_quantity * $cartitems['qty'];

                        $unit_weight = (float) ($verient['unit_quantity']) ? $verient['unit_quantity'] : $productinfo['weight'];
                    } else {
                        $quantity    = $cartitems['qty'];
                        $unit_weight = (float) ($productinfo['weight']) ? $productinfo['weight'] : 1;
                    }

                    $item_weight = (float) ($unit_weight) ? (float) $unit_weight * (float) $unit_quantity : $quantity;

                    $sale_items = array(
                        "sale_id"           => $order_sale_id,
                        "product_id"        => $cartitems['item_id'],
                        "product_code"      => $cartitems['code'],
                        "product_name"      => $cartitems['name'],
                        "product_type"      => $productinfo['type'],
                        "option_id"         => $cartitems['item_option_id'],
                        "warehouse_id"      => $shopinfo['default_eshop_warehouse'],
                        "tax_method"        => $cartitems['item_tax_method'],
                        "tax_rate_id"       => $item_tax_rate,
                        "tax"               => $tax,
                        "mrp"               => $mrp,
                        "real_unit_price"   => $actual_real_unit_price, //$productinfo['price'],
                        "unit_discount"     => $unit_discount,
                        "unit_tax"          => $unit_tax,
                        "unit_price"        => $unit_price,
                        "net_unit_price"    => $net_unit_price,
                        'invoice_unit_price' => $invoice_unit_price,
                        'invoice_net_unit_price' => $invoice_net_unit_price,
                        "quantity"          => $quantity,
                        "item_discount"     => $pr_item_discount,
                        "item_tax"          => $pr_item_tax,
                        'net_price'         => $net_price,
                        'invoice_total_net_unit_price' => $invoice_total_net_unit_price,
                        "subtotal"          => $subtotal,
                        "discount"          => $discount,
                        "product_unit_id"   => $productinfo['sale_unit'],
                        "product_unit_code" => $unites[$productinfo['sale_unit']]['code'],
                        "unit_quantity"     => $unit_quantity,
                        "cf1"               => $productinfo['cf1'],
                        "cf2"               => $productinfo['cf2'],
                        "cf3"               => $productinfo['cf3'],
                        "cf4"               => $productinfo['cf4'],
                        "cf5"               => $productinfo['cf5'],
                        "cf6"               => $productinfo['cf6'],
                        "hsn_code"          => $productinfo['hsn_code'],
                        "note"              => '',
                        "delivery_status"   => 'pending',
                        "pending_quantity"  => $cartitems['qty'],
                        "delivered_quantity" => '0',
                        "item_weight"       => $item_weight,
                        "note"              => $cartitems['product_remark'],
                    );

                    $order_total_weight += (float) $item_weight;

                    if (is_array($cartitems['tax_attr'])) {
                        $sale_items["gst_rate"] = (($cartitems['tax_attr']['IGST']['percentage'] != 0) ? $cartitems['tax_attr']['IGST']['percentage'] : (($cartitems['tax_attr']['CGST']['percentage']) ? $cartitems['tax_attr']['CGST']['percentage'] : 0));
                        $sale_items["cgst"] = ($cartitems['tax_attr']['CGST']['taxamt']) ? $cartitems['tax_attr']['CGST']['taxamt'] : 0;
                        $sale_items["sgst"] = ($cartitems['tax_attr']['SGST']['taxamt']) ? $cartitems['tax_attr']['SGST']['taxamt'] : 0;
                        $sale_items["igst"] = ($cartitems['tax_attr']['IGST']['taxamt']) ? $cartitems['tax_attr']['IGST']['taxamt'] : 0;

                        $IGST += $cartitems['tax_attr']['IGST']['taxamt'];
                        $CGST += $cartitems['tax_attr']['CGST']['taxamt'];
                        $SGST += $cartitems['tax_attr']['SGST']['taxamt'];
                    }

                    $orderData['items'][$cartitems['item_id']] = $sale_items;

                    $sale_item_id = $this->eshop_model->add_sales_order_items($sale_items);

                    if ($sale_item_id) {

                        if (is_array($cartitems['tax_attr'])) {
                            foreach ($cartitems['tax_attr'] as $taxcode => $taxattr) {
                                $cartItemsTax[] = array(
                                    "item_id"       => $sale_item_id,
                                    "order_id"      => $order_sale_id,
                                    "attr_code"     => $taxcode,
                                    "attr_name"     => $taxattr['name'],
                                    "attr_per"      => $taxattr['percentage'],
                                    "tax_amount"    => $taxattr['taxamt'],
                                );
                            }//end foreach.
                        }//end if.  

                        unset($sale_items);
                        /*if (!empty($cartItemsTax)) {
                            foreach ($cartItemsTax as $item_tax_attr) {
                                $taxAttrId = $this->eshop_model->addSalesItemTaxAttr($item_tax_attr);
                            }//end foreach
                            if ($taxAttrId) {
                                unset($cartItemsTax);
                            }
                        }*/
                    }//end if.
                }//foreach
                // $res = $this->pos_model->getSetting();

                /**
                 * Update Total GST on Order table
                 */
                $fieldorderGst = [
                    'cgst' => ($CGST) ? $CGST : NULL,
                    'sgst' => ($SGST) ? $SGST : NULL,
                    'igst' => ($IGST) ? $IGST : NULL,
                    'total_weight' => $order_total_weight,
                ];

                $this->eshop_model->updateorderGst($order_sale_id, $fieldorderGst);

                /**
                 * End update total GST on Order Table
                 */
                $user_id = $this->data['user_id'];

                $config = $this->ci->config;
                $result = array();
                $result['status'] = 'ERROR';

                $eshop_url = isset($config->config['eshop_url']) && !empty($config->config['eshop_url']) ? $config->config['eshop_url'] : null;

                //------start Payment Process 

                $_arr = array('x_amount' => $grand_total, 'x_invoice_num' => $order_sale_id, 'x_description' => $ref_No);
                $_arr['x_amount'] = $grand_total;
                $_arr['x_invoice_num'] = $order_sale_id;
                $_arr['x_description'] = $ref_No;
                $_arr['name'] = $orderData['billing_name'];
                $_arr['email'] = $orderData['billing_email'];
                $_arr['mobile'] = $orderData['billing_phone'];
                $_arr['notify_url'] = rtrim($eshop_url, '/') . '/insta_notify';
                $orderData['order_id'] = $order_id;
                $orderData['order_no'] = $orderno;
                $orderData['order_date'] = $order_date;
                $orderData['user_name'] = $user_name;
                $orderData['user_mobile'] = $user_phone;
                $orderData['user_email'] = $user_email;
                $orderData['shipping_amount'] = $shipping_amount;
                $orderData['payment_methods'] = $payment_methods;
                $orderData['payment_status'] = $payment_status;
                //$orderData['customer'] = $customer_name;
                $orderData['ref_No'] = $ref_No;
                $orderData['invoice_no'] = $orderno;
                $orderData['phone'] = $user_phone;
//print_r($orderData); exit;
                //Empty the cart.
                unset($_SESSION['cart']);
                
                /// SMS Send   
                if ($user_phone != '') {
                    $sms_code = md5('Reciept' . $ref_No . $order_sale_id);
                    
                    /*Send SMS to customer*/
                    $customer_sms = 'Hello, your Order is placed successfully. Order Id is ' . $orderno . '. To view order receipt click on ';
                    $invoice_url = $this->sma->get_tiny_url(base_url('reciept/pdforder/') . $sms_code);
                    $customer_sms = $customer_sms . $invoice_url;                    
                    $res = $this->sma->SendSMS($user_phone, $customer_sms, 'ESHOP_ORDER_PLACED');                    
                    if (!empty($res)):
                        $Obj = json_decode($res);
                        if (isset($Obj) && $Obj->type == 'success'):
                            $this->sma->setSMSLog($user_phone, $customer_sms, $Obj->message);
                            $this->sma->update_sms_count(1);                      
                        endif;                    
                    endif;
                    /*End Send SMS to customer*/
                }
                
                if ($biller_phone != '') {
                    /*Send SMS to biller*/
                   $PaidSt = '';                    
                   if ($payment_methods) {
                       $PaidSt = '. Paid by ' . $payment_methods . '(' . $payment['transaction_id'] . ')';
                   }

                   $biller_sms = 'New order Received from ' . $user_name . ' - +91' . $user_phone . ' Order Id:- ' . $orderno . ' ' . $invoice_url . ' Order Type:- ' . $shippingMethodName . ', Order Amount:- Rs.' . $this->sma->formatDecimal($grand_total) . ' ' . $this->Settings->default_currency . ' ' . $PaidSt; 
                   $res2 = $this->sma->SendSMS($biller_phone, $biller_sms, 'ESHOP_ORDER_RECEIVED');
                   if (!empty($res2)):
                       $Obj = json_decode($res2);
                       if (isset($Obj) && $Obj->type == 'success'):
                           $this->sma->setSMSLog($biller_phone, $biller_sms, $Obj->message);
                           $this->sma->update_sms_count(1);                      
                       endif;                    
                   endif;

               }
               //SMS End 
                
                switch ($payment_methods):

                    case 'cod':
                        $cod_shop_url = rtrim($eshop_url, '/') . '/cod_notify/' . md5('COD' . $ref_No);
                        $orderData['payment_status'] = 'Due';

                        $this->Orders_Emails($orderData);
                        $this->Shopkeeper_Emails($orderData);
                        
                        redirect($cod_shop_url);
                        break;

                    case 'UPI_QR':
                        $payment['order_id'] = $order_sale_id;
                        $payment['amount'] = $grand_total;
                        $payment['paid_by'] = 'UPI_QRCODE';
                        $payment['currency'] = 'INR';
                        $payment['type'] = 'received';
                        $payment['transaction_id'] = $this->input->post('transaction_id');
                        $payment['reference_no'] = $this->site->getReference('pay');

                        $this->shop_model->addPayment($payment);
                        $this->shop_model->updateStatusOrder($order_sale_id, 'pending', $payment['note'], 'paid', $payment['amount']);
                        $cod_shop_url = rtrim($eshop_url, '/') . '/cod_notify/' . md5('COD' . $ref_No);

                        $orderData['payment_status'] = 'Paid';
                        $this->Orders_Emails($orderData);
                        $this->Shopkeeper_Emails($orderData);
                        
                        $this->smstobiller($biller_phone, $orderData, $shippingMethodName , $url, $payment);
                        redirect($cod_shop_url);
                        break;

                    case 'PAYTM':

                        $paytmpayment['order_id'] = $order_sale_id;
                        $paytmpayment['amount'] = $grand_total;
                        $paytmpayment['userid'] = $user_id;

                        $this->Orders_Emails($orderData);
                        $this->Shopkeeper_Emails($orderData);
                        $this->paytm_init($paytmpayment);

                        break;

                    case 'instamojo':
                        $pay_result = $this->eshop_model->instamojoEshop($_arr);


                        if (isset($pay_result['longurl']) && !empty($pay_result['longurl'])):
                            $this->Orders_Emails($orderData);
                            $this->Shopkeeper_Emails($orderData);
                            redirect($pay_result['longurl']);
                        else:
                            //$this->sales_model->deleteSale($order_sale_id);
                            $this->orders_model->deleteOrder($order_sale_id);
                            $result['msg'] = $this->instamojo_error($pay_result['error']);
                            return $result;
                        endif;
                        break;

                    case 'authorize':
                        $this->load->library('Authorizenet');

                        $payment['sale_id'] = $order_sale_id;
                        $payment['amount'] = $grand_total;

                        $cc_no = str_replace(' ', '', $this->input->post('cc_number'));
                        $cc_expiry = $this->input->post('cc_expiry');
                        $cc_amount = $this->input->post('amount');

                        $expcc = explode('-', $cc_expiry);
//                        $payment['cc_month'] = $expcc[1];
//                        $payment['cc_year'] = $expcc[0];

                        $cc_cvv = str_replace('_', '', $this->input->post('cc_cvv'));

                        $this->authorizenet->setCCData($cc_no, $cc_expiry, $cc_cvv, $cc_amount);
                        $this->authorizenet->set_order_data(['id' => $order_sale_id, 'reff' => $ref_No]);
                        $this->authorizenet->set_customer_data($billing_shipping);

                        $paymentResponce = $this->authorizenet->TransactionRequest();

                        if ($paymentResponce['status'] == "SUCCESS") {

                            $payment['paid_by'] = 'CC';
                            $payment['currency'] = 'USD';
                            $payment['type'] = 'received';
                            $payment['note'] = 'auth_code:' . $paymentResponce['auth_code'];
                            $payment['transaction_id'] = $paymentResponce['transation_id'];
                            $payment['reference_no'] = $this->site->getReference('pay');
                            $notify['transaction_id'] = $paymentResponce['transation_id'];

                            $this->shop_model->addPayment($payment);
                            $this->shop_model->updateStatus($order_sale_id, 'completed', $payment['note'], 'paid');
                        } else {
                            $payment['transaction_id'] = 'false';
                        }


                        $authorisenet_notify = rtrim($eshop_url, '/') . '/authorisenet_notify/' . $paymentResponce['status'] . '/' . $payment['transaction_id'] . '/' . $order_sale_id;
                        //$this->Orders_Emails($orderData);
                        redirect($authorisenet_notify);

                        break;
                endswitch;
                
            }//end if.
        }//end if
    }                
    
    public function order_submit() {

        $this->authenticate();

        ob_start();
        $orderData = $_POST;

        $paymentMethods = $this->payment_methods();

        $payment_methods = $paymentMethods[$orderData['paymentType']]['code'];

        $shopinfo = $this->data['shopinfo'];

        $this->load->model('sales_model');

        $cart = unserialize($orderData['order_data']);

        $unites = $this->shop_model->getUnites();

        $cart_items_count = count($orderData['cart_items']);

        $order_tax_id = ($cart['order_tax_id']) ? $cart['order_tax_id'] : 0;

        $order_tax = ($cart['order_tax']) ? $cart['order_tax'] : 0;

        $totalTax = $cart['cart_tax_total'] + $order_tax;

        $ref_No = $this->site->getReferenceNumber('eshop');

        $userdata = $this->session->userdata();

        $user_id = $userdata['id'];
        $user_name = $userdata['name'];
        $order_date = date('Y-m-d H:i:s');

        $shippingMethodInfo = $this->eshop_model->getShippingMethods(['id' => $orderData['shippingType']]);

        $cf1 = ($orderData['cf1']) ? $orderData['cf1'] : '';
        $cf2 = ($orderData['cf2']) ? $orderData['cf2'] : '';

        $order = array(
            "date" => $order_date,
            "reference_no" => $ref_No,
            "customer_id" => $user_id,
            "customer" => $user_name,
            "biller_id" => $shopinfo['default_biller'],
            "biller" => $shopinfo['biller_name'],
            "warehouse_id" => $_SESSION['eshop_location_id'],
            "total" => $cart['cart_sub_total'],
            "product_discount" => 0,
            "order_discount_id" => '',
            "order_discount" => 0,
            "total_discount" => 0,
            "product_tax" => $cart['cart_tax_total'],
            "order_tax_id" => $order_tax_id,
            "order_tax" => $order_tax,
            "total_tax" => $totalTax,
            "shipping" => 0,
            "grand_total" => $cart['cart_gross_total'],
            "sale_status" => "completed",
            "payment_status" => "due",
            "total_items" => $cart_items_count,
            "paid" => 0,
            "pos" => 0,
            "offline_sale" => 0,
            "eshop_sale" => 1,
            "cf1" => $cf1,
            "cf2" => $cf1,
            "note" => '',
            "rounding" => $cart['cart_gross_rounding'],
            "eshop_order_alert_status" => 0,
        );



        $billing_shipping = array(
            "billing_name" => $orderData['billing_name'],
            "billing_gstn_no" => $orderData['billing_gstn_no'],
            "billing_phone" => $orderData['billing_phone'],
            "billing_email" => $orderData['billing_email'],
            "billing_addr1" => $orderData['billing_addr1'],
            "billing_addr2" => $orderData['billing_addr2'],
            "billing_city" => $orderData['billing_city'],
            "billing_state" => $orderData['billing_state'],
            "billing_country" => $orderData['billing_country'],
            "billing_zipcode" => $orderData['billing_zipcode'],
            "shipping_billing_is_same" => $orderData['shipping_billing_is_same'],
            "save_info" => $orderData['save_info'],
            "shipping_name" => $orderData['shipping_name'],
            "shipping_phone" => $orderData['shipping_phone'],
            "shipping_email" => $orderData['shipping_email'],
            "shipping_addr1" => $orderData['shipping_addr1'],
            "shipping_city" => $orderData['shipping_city'],
            "shipping_state" => $orderData['shipping_state'],
            "shipping_country" => $orderData['shipping_country'],
            "shipping_zipcode" => isset($_SESSION['shipping_methods']['pincode']) ? $_SESSION['shipping_methods']['pincode'] : $orderData['shipping_zipcode'],
            "shippingType" => isset($_SESSION['shipping_methods']['methods']) ? $_SESSION['shipping_methods']['methods'] : $orderData['shippingType'],
            "paymentType" => $orderData['paymentType'],
        );

        if (is_array($cart['items']) && count($cart['items'])) {

            $order_sale_id = $this->eshop_model->addSales($order);

            //If sale insert successfully.
            if ($order_sale_id) {

                //Get Eshop shipping info              
                $e_order['is_cod'] = ($payment_methods == 'cod') ? 'YES' : 'NO';
                $e_order['shipping_method_name'] = isset($_SESSION['shipping_methods']['methods']) ? $_SESSION['shipping_methods']['methods'] : $shippingMethodInfo[0]['name'];
                $e_order['sale_id'] = $order_sale_id;
                $e_order['date'] = $order_date;
                $e_order['customer_id'] = $user_id;
                $e_order['billing_name'] = $orderData['billing_name'];
                $e_order['billing_addr'] = $orderData['billing_addr1'] . ', ' . $orderData['billing_addr2'];
                $e_order['billing_email'] = $orderData['billing_email'];
                $e_order['billing_phone'] = $orderData['billing_phone'];
                $e_order['shipping_name'] = $orderData['shipping_name'];
                $e_order['shipping_addr'] = $orderData['shipping_addr1'] . ', ' . $orderData['shipping_addr2'];
                $e_order['shipping_email'] = $orderData['shipping_email'];
                $e_order['shipping_phone'] = $orderData['shipping_phone'];

                //Insert Eshop order details.
                $order_id = $this->eshop_model->addOrder($e_order);

                //Update Eshop sale refference no.
                $updateReference = $this->site->updateReference('eshop');

                //Fourcefully save billing & shipping info
                /*  $save_info = 1;
                  if($save_info==1):
                  //-------------------------------- Saving Billing Shippimg Info -----------------------------
                  $_param = array('billing_name', 'billing_phone', 'billing_email', 'shipping_phone', 'shipping_email', 'billing_addr1', 'billing_addr2', 'billing_city', 'billing_state', 'billing_country', 'billing_zipcode', 'shipping_name', 'shipping_addr1', 'shipping_addr2', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_zipcode');
                  $param = array();
                  if (is_array($_param)):
                  foreach ($_param as $_param_key) {
                  $_param_key_val = $this->input->post($_param_key);
                  if (!empty($_param_key_val)):
                  $param[$_param_key] = $this->input->post($_param_key);
                  endif;
                  }
                  endif;
                  if (count($param) > 0):
                  $res_copy = $this->companies_model->set_billing_shiiping_info($this->data['user_id'], $param);
                  endif;
                  //-------------------------------- Saving Billing Shippimg Info -----------------------------
                  endif;
                 */
                $order_total_items = 0;
                //add order Items
                foreach ($cart['items'] as $pid => $cartitems) {

                    $productData = $this->shop_model->getProductInfo($pid);
                    $productinfo = $productData[$pid];

                    if ($this->session->userdata('id') > 0) {
                        $productinfo = (array) get_product_price($productinfo, $this->session->userdata('id'));
                    }
                    $mrp = ($productinfo['mrp']) ? $productinfo['mrp'] : 0;

                    $sale_items = array(
                        "sale_id" => $order_sale_id,
                        "product_id" => $pid,
                        "product_code" => $cartitems['code'],
                        "product_name" => $cartitems['name'],
                        "product_type" => $productinfo['type'],
                        "net_unit_price" => $cartitems['item_price'],
                        "unit_price" => $cartitems['item_price'],
                        "real_unit_price" => $cartitems['item_price'],
                        "quantity" => $cartitems['qty'],
                        "warehouse_id" => $_SESSION['eshop_location_id'],
                        "item_tax" => $cartitems['item_tax_total'],
                        "tax_rate_id" => $cartitems['item_tax_id'],
                        "tax" => $cartitems['tax_rate'],
                        "discount" => 0,
                        "item_discount" => 0,
                        "subtotal" => $cartitems['item_subtotal'],
                        "product_unit_id" => $productinfo['sale_unit'],
                        "product_unit_code" => $unites[$productinfo['sale_unit']]['code'],
                        "unit_quantity" => $cartitems['qty'],
                        "cf1" => $productinfo['cf1'],
                        "cf2" => $productinfo['cf2'],
                        "cf3" => $productinfo['cf3'],
                        "cf4" => $productinfo['cf4'],
                        "cf5" => $productinfo['cf5'],
                        "cf6" => $productinfo['cf6'],
                        "hsn_code" => $productinfo['hsn_code'],
                        "mrp" => $mrp,
                    );

                    $order_total_items += $cartitems['qty'];

                    $sale_item_id = $this->eshop_model->addSalesItem($sale_items);

                    if ($sale_item_id) {

                        unset($sale_items);

                        if (is_array($cartitems['tax_attr'])) {
                            foreach ($cartitems['tax_attr'] as $taxcode => $taxattr) {

                                $cartItemsTax[] = array(
                                    "item_id" => $sale_item_id,
                                    "sale_id" => $order_sale_id,
                                    "attr_code" => $taxcode,
                                    "attr_name" => $taxattr['name'],
                                    "attr_per" => $taxattr['percentage'],
                                    "tax_amount" => $taxattr['taxamt'],
                                );
                            }//end foreach.
                        }//end if.  


                        if (!empty($cartItemsTax)) {
                            foreach ($cartItemsTax as $item_tax_attr) {

                                $taxAttrId = $this->eshop_model->addSalesItemTaxAttr($item_tax_attr);
                            }//end foreach
                            if ($taxAttrId) {
                                unset($cartItemsTax);
                            }
                        }
                    }//end if.
                }//foreach
                // $res = $this->pos_model->getSetting();
                $user_id = $this->data['user_id'];

                $config = $this->ci->config;
                $result = array();
                $result['status'] = 'ERROR';

                $eshop_url = isset($config->config['eshop_url']) && !empty($config->config['eshop_url']) ? $config->config['eshop_url'] : null;

                //------start Payment Process 

                $_arr = array('x_amount' => $cart['cart_gross_total'], 'x_invoice_num' => $order_sale_id, 'x_description' => $ref_No);
                $_arr['x_amount'] = $cart['cart_gross_total'];
                $_arr['x_invoice_num'] = $order_sale_id;
                $_arr['x_description'] = $ref_No;
                $_arr['name'] = $orderData['billing_name'];
                $_arr['email'] = $orderData['billing_email'];
                $_arr['mobile'] = $orderData['billing_phone'];
                $_arr['notify_url'] = rtrim($eshop_url, '/') . '/insta_notify';

                //Empty the cart.
                unset($_SESSION['cart']);
                unset($_SESSION['shipping_methods']);
                unset($_SESSION['eshop_location_id']);

                switch ($payment_methods):

                    case 'cod':
                        $cod_shop_url = rtrim($eshop_url, '/') . '/cod_notify/' . md5('COD' . $ref_No);

                        redirect($cod_shop_url);
                        break;

                    case 'instamojo':
                        $pay_result = $this->eshop_model->instamojoEshop($_arr);

                        if (isset($pay_result['longurl']) && !empty($pay_result['longurl'])):

                            redirect($pay_result['longurl']);
                        else:
                            $this->sales_model->deleteSale($order_sale_id);
                            $result['msg'] = $this->instamojo_error($pay_result['error']);
                            return $result;
                        endif;
                        break;

                    case 'authorize':
                        $this->load->library('Authorizenet');

                        $payment['sale_id'] = $order_sale_id;
                        $payment['amount'] = $cart['cart_gross_total'];

                        $cc_no = str_replace(' ', '', $this->input->post('cc_number'));
                        $cc_expiry = $this->input->post('cc_expiry');

                        $expcc = explode('-', $cc_expiry);
//                        $payment['cc_month'] = $expcc[1];
//                        $payment['cc_year'] = $expcc[0];

                        $cc_pin = $this->input->post('cc_pin');

                        $this->authorizenet->setCCData($cc_no, $cc_expiry, $cc_pin, $payment['amount']);
                        $this->authorizenet->set_order_data(['id' => $order_sale_id, 'reff' => $ref_No]);
                        $this->authorizenet->set_customer_data($billing_shipping);

                        $paymentResponce = $this->authorizenet->TransactionRequest();

                        if ($paymentResponce['status'] == "SUCCESS") {

                            $payment['paid_by'] = 'CC';
                            $payment['currency'] = 'USD';
                            $payment['type'] = 'received';
                            $payment['note'] = 'auth_code:' . $paymentResponce['auth_code'];
                            $payment['transaction_id'] = $paymentResponce['transation_id'];
                            $payment['reference_no'] = $this->site->getReference('pay');

                            $this->shop_model->addPayment($payment);
                            $this->shop_model->updateStatus($order_sale_id, 'completed', $payment['note'], 'paid');
                        }

                        $authorisenet_notify = rtrim($eshop_url, '/') . '/authorisenet_notify/' . $paymentResponce['status'] . '/' . $payment['transaction_id'] . '/' . $order_sale_id;

                        redirect($authorisenet_notify);

                        break;
                endswitch;
            }//end if.
        }//end if
    }

    public function authorisenet_notify() {

        $this->authenticate();

        $invid = $this->uri->segment(5);

        $this->data['order_status'] = $this->uri->segment(3);

        $this->data['transaction_id'] = $this->uri->segment(4);

        $orderInfo = $this->shop_model->getInvoiceByID($invid);

        $this->data['order_info'] = (array) $orderInfo;

        $this->load_shop_view($this->shoptheme . '/authorisenet_notify', $this->data);
    }

    public function cod_notify() {

        $TransKey = $this->uri->segment(3);
        $User_id = $this->data['user_id'];

        $orderInfo = $this->eshop_model->validateCODSales($TransKey, $User_id);

        if ($orderInfo) {
            $this->data['order_status'] = 'SUCCESS';
            $this->data['order_info'] = $orderInfo[0];
        } else {
            $this->data['order_status'] = 'FAIL';
        }

        $this->load_shop_view($this->shoptheme . '/cod_notify', $this->data);
    }

    private function insta_notify() {

        $payment_request_id = $this->input->get('payment_request_id');
        $payment_id = $this->input->get('payment_id');

        $this->data['payment_id'] = $payment_id;

        if (empty($payment_request_id) || empty($payment_id)):
            $this->data['error'] = 'Error in payment process';
            $this->load_shop_view($this->shoptheme . '/decline_order', $this->data);
        endif;

        $this->load->library('instamojo');

        $Transaction = $this->eshop_model->getInstamojoEshopTransaction(array('request_id' => $payment_request_id));
        $sid = $Transaction->order_id;
        $res12 = $this->eshop_model->updateInstamojoEshopTransaction($payment_request_id, array('payment_id' => $payment_id));


        $ci->config->load('payment_gateways', TRUE);

        $payment_config = $ci->config->item('payment_gateways');
        $instamojo_credential = $payment_config['instamojo'];

        try {
            $api = new Instamojo($instamojo_credential['API_KEY'], $instamojo_credential['AUTH_TOKEN'], $instamojo_credential['API_URL']);
            $paymentDetail = $api->paymentDetail($payment_id);
            if (is_array($paymentDetail)):
                $pay_res = serialize($paymentDetail);
                $this->eshop_model->updateInstamojoEshopTransaction($payment_request_id, array('success_response' => $pay_res));
                if (isset($paymentDetail["status"]) && in_array($paymentDetail["status"], array('Credit', 'credit', 'Completed'))):
                    $res = $this->eshop_model->instomojoEshopAfterSale($paymentDetail, $sid);
                    if ($res):
                        $this->data['sale'] = $this->eshop_model->getSalesDetails($sale_id);
                        $this->data['success'] = 'Payment done successfully';
                        $this->load_shop_view($this->shoptheme . '/success_order', $this->data);
                    endif;
                endif;
                $this->data['error'] = 'Payment process under review';
                $this->load_shop_view($this->shoptheme . '/decline_order', $this->data);
            endif;
        } catch (Exception $e) {
            $this->data['error'] = $e->getMessage();
            $this->load_shop_view($this->shoptheme . '/decline_order', $this->data);
        }

        $this->data['error'] = 'Payment process under review';
        $this->load_shop_view($this->shoptheme . '/decline_order', $this->data);
    }

    public function customer_info() {
        return $this->shop_model->getCustomerInfo();
    }

    public function shipping_methods() {

        $res = $this->eshop_model->getShippingMethods(array('is_deleted' => 0, 'is_active' => 1));

        return $res;
    }

    private function payment_methods($flag = NULL) {

        $res = $this->pos_model->getSetting();


        $_eshop_cod = isset($res->eshop_cod) && !empty($res->eshop_cod) ? $res->eshop_cod : NUll;
        $_default_eshop_pay = isset($res->default_eshop_pay) && !empty($res->default_eshop_pay) ? $res->default_eshop_pay : NUll;

        $_instamozo = isset($res->instamojo) && !empty($res->instamojo) ? $res->instamojo : NUll;
        $_ccavenue = isset($res->ccavenue) && !empty($res->ccavenue) ? $res->ccavenue : NUll;
        $_authorize = isset($res->authorize) && !empty($res->authorize) ? $res->authorize : NUll;
        $result = $payment_list = array();
        if ($_eshop_cod):
            $payment_list['cod'] = 'COD';
        endif;
        switch ($_default_eshop_pay) {

            case 'instamojo':
                if ($_instamozo):
                    $payment_list['instamojo'] = 'Credit Card / Debit Card / Netbanking';
                endif;
                break;

            case 'authorize':
                if ($_authorize):
                    $payment_list['authorize'] = 'Credit Card / Debit Card';
                endif;
                break;

            case 'ccavenue':
                if ($_ccavenue):
                    $payment_list['ccavenue'] = 'CCavenue';
                endif;
                break;

            default:

                break;
        }
        if ($flag == 1) {
            return $payment_list;
        }
        if (count($payment_list)):
            $i = 1;
            foreach ($payment_list as $payment_key => $payment_name) {
                $result[$i]['id'] = $i;
                $result[$i]['code'] = $payment_key;
                $result[$i]['name'] = $payment_name;
                $i++;
            }
        endif;

        return $result;
    }

    public function getTaxMethods() {

        $result = $this->pos_model->getAllTaxRates();
        foreach ($result as $key => $method) {
            $data[$method['id']] = $method;
        }

        return $data;
    }

    public function getTaxAttribs() {

        $result = $this->pos_model->getTaxAttributes();

        foreach ($result as $key => $attr) {
            $data[$attr->id] = (array) $attr;
        }

        return $data;
    }

    public function myaccount() {
        $this->authenticate();
        //$this->exitInvalidSession();

        $this->data['customer'] = $this->get_customer_details($this->data['user_id']);

        $billingShipping = $this->shop_model->get_billing_shipping($this->data['user_id']);

        $this->data['billing_shipping'] = (array) $billingShipping[0];
        $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->session->userdata('id'));

        $param['user_id'] = $this->data['user_id'];

        $this->data['myorder'] = $this->shop_model->getOrdersByUser($param);
        $this->data['myinvoice'] = $this->shop_model->getEshopSalesByUser($param);
        switch ($_POST['action']) {
            case 'update_customer':
                $this->update_customer();
                break;
            case 'update_addresses':
                $param = $_POST;
                unset($param['customer_id']);
                unset($param['action']);
                $this->load->model('companies_model');
                $rec = $this->companies_model->set_billing_shiiping_info($this->data['user_id'], $param);
                if ($rec) {
                    $msg = 'success';
                } else {
                    $msg = 'error';
                }
                redirect('shop/myaccount?msg=' . $msg . '#parentHorizontalTab3');
                break;
            case 'change_password':
                $rec = $this->changeCustomerPasswd();
                if ($rec['status'] == 'SUCCESS') {
                    $this->data['msg'] = $rec['msg'];

                    $this->shop_model->end_user_session();

                    redirect('shop/login', $this->data);
                } elseif ($rec['status'] == 'ERROR') {
                    $this->data['error'] = $rec['msg'];
                }
                break;
        }//end switch.

        if ($this->input->get('msg') == 'success') {
            $this->data['msg'] = 'Information has been updated successfully';
        } elseif ($this->input->get('msg') == 'error') {
            $this->data['error'] = "Error In update information";
        }
        unset($_POST);
        $this->load_shop_view($this->shoptheme . '/dashboard', $this->data);
    }

    public function my_account() {
        $this->authenticate();
        //$this->exitInvalidSession();
        if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {
            $this->data['myorder'] = $this->shop_model->getRecentOrderByUser($this->data['user_id']);
            $this->load_shop_view($this->shoptheme . '/dashboard', $this->data);
        } else {
            $this->data['customer'] = $this->get_customer_details($this->data['user_id']);
            if ($this->input->get('msg') == 'success') {
                $this->data['msg'] = 'Information has been updated successfully';
            } elseif ($this->input->get('msg') == 'error') {
                $this->data['error'] = "Error In update information";
            }
            $this->load_shop_view($this->shoptheme . '/my_profile', $this->data);
        }
    }

    public function myorders() {
        $this->authenticate();
        // $this->exitInvalidSession();

        /* $param['user_id'] = $this->data['user_id'];
          $param['limit'] = 20;
          $param['offset'] = 0;
          $param['sort_field'] = 'sales.eshop_sale';
          $param['sort_dir'] = 'DESC';
          $param['search_by'] = '';
          $param['search_param'] = '';

          $this->data['myorder'] = $this->shop_model->getOrdersByUser($param); */

        $param['user_id'] = $this->data['user_id'];

        $this->data['myorder'] = $this->shop_model->getOrdersByUser($param);
        $this->data['myinvoice'] = $this->shop_model->getEshopSalesByUser($param);
        $this->load_shop_view($this->shoptheme . '/myorders', $this->data);
    }

    public function cancle_order() {

        $this->exitInvalidSession();

        $TransKey = $_GET['oref'];
        $UserId = $this->data['user_id'];

        //$OrderDeatil = $this->eshop_model->validateSales(NULL, $UserId, $TransKey);
        $OrderDeatil = $this->eshop_model->validateSalesEshop(NULL, $UserId, $TransKey);
        $redairecturl =  'shop/myaccount';   

        if (is_array($OrderDeatil) && count($OrderDeatil) > 0) {

            $saleupdate['sale_status'] = 'cancelled';

            $this->db->where('id', $TransKey);
            //$result = $this->db->update('sales', $saleupdate);
            $result = $this->db->update('orders', $saleupdate);

            if ($result) {
                $redairecturl .= '?act=success';
            } else {
                $redairecturl .= '?act=fail';
            }
        } else {
            $redairecturl .= '?act=invalid';
        }

        redirect($redairecturl . '#parentHorizontalTab2');
    }

    public function orderDetails() {

        $this->exitInvalidSession();

        $TransKey = $this->input->get('transaction_key');
        $UserId = $this->input->get('user_id');
        $this->data['order_status_type'] = $status = $this->input->get('status');

        $result = array();

        if (empty($TransKey) || empty($UserId)) {
            if (empty($TransKey)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'TransKey is  empty';
                return $this->json_op($result);
            }
            if (empty($UserId)) {
                $result['status'] = 'ERROR';
                $result['msg'] = 'UserID is  empty';
                return $this->json_op($result);
            }
        }

        if ($status == 'pending')
            $OrderDeatil = $this->eshop_model->validateSalesEshop(NULL, $UserId, $TransKey);
        else
            $OrderDeatil = $this->eshop_model->validateSales(NULL, $UserId, $TransKey);


        $array = array();
        $array['status'] = 'ERROR';
        if (is_array($OrderDeatil) && count($OrderDeatil) > 0) :
            $validOrder = $OrderDeatil[0]['id'];
            $array = array();
            $array['status'] = 'SUCCCESS';
            //--------------Order Details --------------------//
            if ($status == 'pending')
                $order_details = $this->site->getSaleByIDEshop($validOrder);
            else
                $order_details = $this->site->getSaleByID($validOrder);


            $order['sale'] = (array) $order_details;


            $this->load->model('sales_model');

            //--------------Payments Details --------------------//
            if ($status == 'pending') {
                $pay_details = $this->orders_model->getOrderPayments($validOrder);
                $paidamount = $this->shop_model->getpaidamount(['order_id' => $validOrder]);
            } else {
                $pay_details = $this->sales_model->getInvoicePayments($validOrder);
                $paidamount = $this->shop_model->getpaidamount(['sale_id' => $validOrder]);
            }

            $order['payment'] = $pay_details[0];
            $order['paidamount'] = $paidamount;

            //-------------- Shipping -------------//
            $deli = $this->sales_model->getDeliveryByID($id);
            $order['delivery'] = $deli;

            //--------------billing_shipping Details --------------------//
            if ($status == 'pending') {
                $billing_details = $this->eshop_model->getOrderDetails(array('sale_id' => $validOrder, 'customer_id' => $UserId));
            } else {
                $order_row = $this->site->getSaleBySaleInvoice($order_details->invoice_no);
                $billing_details = $this->eshop_model->getOrderDetails(array('sale_id' => $order_row->id, 'customer_id' => $UserId));
            }

            $order['billing_shipping'] = $billing_details[0];

            //--------------Item Details --------------------//

            if ($status == 'pending')
                $items_details = $this->orders_model->getAllOrderItems($validOrder);
            else
                $items_details = $this->sales_model->getAllInvoiceItems($validOrder);


            $items_details = (array) $items_details;
            $order['items_count'] = count($items_details);
            $i = 1;
            foreach ($items_details as $item_details) {
                $order['items'][$i] = $item_details;
                $i++;
            }

            $this->data['order'] = $order;

            $html = $this->load->view($this->view_shop . 'orders_details', $this->data);

        else:
            echo '<div class="modal-header alert alert-danger">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Invalid Order</h4>
                </div>';
        endif;
    }

    public function billing_shipping() {

        $this->authenticate();
        // $this->exitInvalidSession(); 

        $this->data['billing_shipping'] = $this->shop_model->get_billing_shipping($this->data['user_id']);

        if ($this->data['billing_shipping'] === false) {
            $this->data['form_action'] = 'insert';
        } else {
            $this->data['form_action'] = 'update';
        }

        if (isset($_GET['act']) && !empty($_GET['act'])) {
            $this->data['actmsg'] = $_GET['act'];
        }

        $this->load_shop_view($this->shoptheme . '/billing_shipping', $this->data);
    }

    public function change_password() {

        $this->authenticate();
        //$this->exitInvalidSession(); 

        $this->data['user'] = $this->customer_info();

        if (isset($_POST['form_action']) && !empty($_POST['form_action'])) {
            //form submit change_password
            if ($_POST['form_action'] == 'change_password') {

                $result = $this->changeCustomerPasswd();

                if ($result['status'] == 'SUCCESS') {
                    $this->data['cpactmsg'] = '<div class="alert alert-success">' . $result['msg'] . '</div>';
                    $this->logout();
                } else {
                    $this->data['cpactmsg'] = '<div class="alert alert-danger">' . $result['msg'] . '</div>';
                }
            }//End if change_password
            //Form Submit update_profile
            if ($_POST['form_action'] == 'update_profile') {

                $userInfo = $this->data['user'];
                $have_change = false;

                if ($userInfo->name != $_POST['name']) {
                    $have_change = true;
                    $postData['name'] = $_POST['name'];
                }
                if ($userInfo->email != $_POST['email']) {
                    $have_change = true;
                    $postData['email'] = $_POST['email'];
                }
                if ($userInfo->company != $_POST['company']) {
                    $have_change = true;
                    $postData['company'] = $_POST['company'];
                }
                if ($userInfo->gstn_no != $_POST['gstn_no']) {
                    $have_change = true;
                    $postData['gstn_no'] = $_POST['gstn_no'];
                }

                if ($have_change === true) {
                    $result = $this->shop_model->updateCustomerInfo($postData, $_POST['user_id']);
                    if ($result) {
                        $this->data['actmsg'] = '<div class="alert alert-success">Profile details has been updated successfully.</div>';
                    } else {
                        $this->data['actmsg'] = '<div class="alert alert-danger">Error in update profile details.</div>';
                    }
                } else {
                    $this->data['actmsg'] = '<div class="alert alert-warning">No profile changes found.</div>';
                }
            }//end if update_profile
        }//end if form_action


        $this->load_shop_view($this->shoptheme . '/change_password', $this->data);
    }

    public function changeCustomerPasswd() {

        /* -------------------------------- Form Validation Start  ----------------------------- */
        $this->form_validation->set_rules('user_id', 'User Id', 'numeric|required');
        $this->form_validation->set_rules('password', 'Password ', 'required');
        $this->form_validation->set_rules('new_password', 'New Password ', 'required');
        $this->form_validation->set_rules('confirm_password', 'confirm Password ', 'required');
        if ($this->form_validation->run() === FALSE) {
            $this->validate_error_parsing();
        }
        /* -------------------------------- Form Validation End  ----------------------------- */

        $login_id = $this->input->post('user_id');
        $password = $this->input->post('password');
        $new_password = $this->input->post('new_password');
        $confirm_password = $this->input->post('confirm_password');
        $parra = array('id' => $login_id, 'password' => md5($password));

        $MsgArr['status'] = 'ERROR';

        if (empty($login_id) || empty($password) || empty($new_password)):
            if (empty($login_id)):
                $MsgArr['status'] = 'ERROR';
                $MsgArr['msg'] = "User Id is  required";
                return $MsgArr;
            endif;
            if (empty($password)):
                $MsgArr['status'] = 'ERROR';
                $MsgArr['msg'] = "Password is  required";
                return $MsgArr;
            endif;
            if (empty($new_password)):
                $MsgArr['status'] = 'ERROR';
                $MsgArr['msg'] = "New password is  required";
                return $MsgArr;
            endif;

            return $MsgArr;

        else:
            if ($new_password != $confirm_password):
                $MsgArr['status'] = 'ERROR';
                $MsgArr['msg'] = "Password not match";
                return $MsgArr;
            endif;

            $res = $this->shop_model->getCompanyCustomer(array('id' => $login_id, 'password' => md5($password)));

            if (!is_object($res)):
                $MsgArr['status'] = 'ERROR';
                $MsgArr['msg'] = "Invalid current password";
                return $MsgArr;
            else:
                $res1 = $this->shop_model->updateCompany($res->id, array('password' => md5($new_password)));

                if ($res1):
                    $MsgArr['status'] = 'SUCCESS';
                    $MsgArr['msg'] = "Password has been changed successfully";
                    return $MsgArr;
                endif;
                $MsgArr['status'] = 'ERROR';
                $MsgArr['msg'] = "Error in change password";
                return $MsgArr;
            endif;

        endif;
    }

    public function save_billing_shipping() {

        $this->exitInvalidSession();

        foreach ($_POST as $key => $value) {

            if (in_array($key, ['submit', 'user_id', 'form_action']))
                continue;
            $data[$key] = $value;
        }

        if ($_POST['form_action'] == 'update') {
            $this->db->where('user_id', $_POST['user_id']);
            $result = $this->db->update('eshop_user_details', $data);
        }

        if ($_POST['form_action'] == 'insert') {
            $data['user_id'] = $_POST['user_id'];
            $result = $this->db->insert('eshop_user_details', $data);
        }

        $redairecturl = rtrim($this->data['baseurl'], '/') . '/shop/billing_shipping';

        if ($result) {
            $redairecturl .= '?act=success';
        } else {
            $redairecturl .= '?act=fail';
        }

        redirect($redairecturl);
    }

    public function about_us() {

        //$this->exitInvalidSession();

        $this->data['page_containt'] = $this->shop_pages();

        $this->load_shop_view($this->shoptheme . '/about_us', $this->data);
    }

    public function contact() {

        // $this->exitInvalidSession();

        $this->data['page_containt'] = $this->shop_pages();

        $this->load_shop_view($this->shoptheme . '/contact', $this->data);
    }

    public function faq() {

        // $this->exitInvalidSession();

        $this->data['page_containt'] = $this->shop_pages();

        $this->load_shop_view($this->shoptheme . '/faq', $this->data);
    }

    public function privacy_policy() {

        //  $this->exitInvalidSession();

        $this->data['page_containt'] = $this->shop_pages();

        $this->load_shop_view($this->shoptheme . '/privacy_policy', $this->data);
    }

    public function terms_conditions() {

        // $this->exitInvalidSession();

        $this->data['page_containt'] = $this->shop_pages();

        $this->load_shop_view($this->shoptheme . '/terms_conditions', $this->data);
    }

    public function shop_pages() {

        $result = array();

        $res = $this->shop_model->getStaticPages(array('id' => 1));
        if (!$res->id) {
            $result = '<h2>Sorry! Pages containt yet not updated</h2>';
        } else {
            $result = $res;
        }

        return $result;
    }

    public function add_customer() {
        $this->load->model('companies_model');
        $this->form_validation->set_rules('name', lang("Name"), 'required|alpha_numeric_spaces');
        $this->form_validation->set_rules('email', lang("email_address"), 'is_unique[companies.email]', array('is_unique' => 'Email ID is already registered please proceed to Login'));
        $this->form_validation->set_rules('phone', lang("phone"), 'required|numeric|exact_length[10]|is_unique[companies.phone]', array('is_unique' => '%s no is already registered please proceed to Login'));

        $this->form_validation->set_rules('eshop_pass', 'Password', 'required|min_length[8]|matches[cpassword]');
        $this->form_validation->set_rules('cpassword', 'Confirm Password', 'trim|required');

        if ($this->form_validation->run() == true) {

            $vecode = md5(time() . $this->input->post('email'));
            $vmcode = rand(111111, 999999);
            $data = array('name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' => '3',
                'group_name' => 'customer',
                'customer_group_id' => '1',
                'customer_group_name' => 'General',
                'price_group_id' => '2',
                'is_synced' => '0',
                'price_group_name' => 'Standered',
                'company' => '-',
                //'address' => $this->input->post('address'),
                'country' => 'India',
                'phone' => $this->input->post('phone'),
                'password' => md5($this->input->post('eshop_pass')),
                'email_verification_code' => $vecode,
                'mobile_verification_code' => $vmcode,
            );

            if ($cid = $this->companies_model->addCompany($data)) {



                if ($this->sendVerificationEmail($data['email'], $cid, $vecode)) {
                    /* if (strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile') || strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'android')) { */
                    $this->otp_sms($this->input->post('phone'), $vmcode);
                    return redirect('shop/otp_varification?code=' . $cid . '&mobileno=' . $this->input->post('phone'));
                    /*  } else {
                      redirect('shop/registration_success');
                      } */
                } else {
                    //if (strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile') || strstr(strtolower($_SERVER['HTTP_USER_AGENT']), 'android')) {

                    $this->otp_sms($this->input->post('phone'), $vmcode);
                    return redirect('shop/otp_varification?code=' . $cid . '&mobileno=' . $this->input->post('phone'));
                    /* } else {
                      $this->data['error'] = "<p class='text-red'>Error: Email not sent</p>";
                      $this->load_shop_view('signup', $this->data);
                      } */
                }
            } else {
                echo "<div class='alert alert-danger'>Error in add customers</div>";
            }
        } else {
            $this->data['form'] = $this->input->post();
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->load_shop_view('signup', $this->data);
        }
    }

    public function registration_success($param) {
        $this->load_shop_view('registration_success', $this->data);
    }

    public function sendVerificationEmail($email, $id, $vecode) {
        if (empty($email))
            return false;
        $VerificationLink = base_url("shop/email_verification/$id/$vecode");
        $subject = "Welcome To Simplypos Eshop Services";
        $from = 'info@simplysafe.in';
        $from_name = 'Suport Simplysafe';

        $content = "<p>Dear Customer,<br/><br/></p><p>Your customer registration has been successfully completed.</p>";
        $content .= "<p>Please click on the above link to complete account verification.</p>";
        $content .= "<p>After completion of verification you can access our E-shop portal using provided login id & password.</p>";
        $content .= "<p>Click on below link to verify your account or copy and paste to your web browser.</a></p>";
        $content .= "<p><a href='" . $VerificationLink . "'>" . $VerificationLink . "</a></p>";
        $content .= "<p>Thank you,<br/>Simplypos Eshop Services</p>";

        $rec = $this->sendEmail($email, $subject, $content, $from, $from_name, $attachment = null, $cc = null, $bcc = null);

        if ($rec === FALSE) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function edit_customer($id = NULL) {
        $this->sma->checkPermissions(false, true);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }

        $company_details = $this->companies_model->getCompanyByID($id);
        // if ($this->input->post('email') != $company_details->email) {
        //$this->form_validation->set_rules('email', lang("email_address"), 'is_unique[companies.email]');
        //  }

        if ($this->input->post('phone') != $company_details->phone) {
            $this->form_validation->set_rules('phone', lang("phone"), 'is_unique[companies.phone]');
        }

        if ($this->form_validation->run('customer/add') == true) {
            $cg = $this->site->getCustomerGroupByID($this->input->post('customer_group'));
            $pg = $this->site->getPriceGroupByID($this->input->post('price_group'));
            $e_password = $this->input->post('eshop_pass');
            $data = array('name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'group_id' => '3',
                'group_name' => 'customer',
                'customer_group_id' => $this->input->post('customer_group'),
                'customer_group_name' => $cg->name,
                'price_group_id' => $this->input->post('price_group') ? $this->input->post('price_group') : NULL,
                'price_group_name' => $this->input->post('price_group') ? $pg->name : NULL,
                'company' => $this->input->post('company'),
                'address' => $this->input->post('address'),
                'vat_no' => $this->input->post('vat_no'),
                'gstn_no' => $this->input->post('gstn_no'),
                'city' => $this->input->post('city'),
                'state' => $this->input->post('state'),
                'state_code' => $this->site->getStateCodeFromName($this->input->post('state')),
                'postal_code' => $this->input->post('postal_code'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'award_points' => $this->input->post('award_points'),
                'dob' => $this->sma->fsd($this->input->post('dob')),
                'anniversary' => $this->sma->fsd($this->input->post('anniversary')),
                'dob_father' => $this->sma->fsd($this->input->post('dob_father')),
                'dob_mother' => $this->sma->fsd($this->input->post('dob_mother')),
                'dob_child1' => $this->sma->fsd($this->input->post('dob_child1')),
                'dob_child2' => $this->sma->fsd($this->input->post('dob_child2')),
            );
            if (!empty($e_password)):
                $data['password'] = md5($e_password);
            endif;
        } elseif ($this->input->post('edit_customer')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }

        if ($this->form_validation->run() == true && $this->companies_model->updateCompany($id, $data)) {
            $this->session->set_flashdata('message', lang("customer_updated"));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->data['customer'] = $company_details;
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->data['customer_groups'] = $this->companies_model->getAllCustomerGroups();
            $this->data['states'] = $this->site->getAllStates();
            $this->data['price_groups'] = $this->companies_model->getAllPriceGroups();
            $this->load->view($this->shoptheme . '/customers/edit', $this->data);
        }
    }

    public function sent_otp() {

        $customerData = $this->get_customer_details($this->uri->segment(3));

        $otp = $customerData['mobile_verification_code'];
        $id = $customerData['id'];
        $mobile = $customerData['phone'];

        $msg = "Simplypos mobile verification code: $otp ";

        $this->sma->SendSMS($mobile, $msg, 'MOBILE_VERIFICATION_CODE');

        redirect("shop/mobile_verification/$id/" . md5($mobile));
    }

    public function mobile_verification() {

        $this->data['id'] = $this->uri->segment(3);

        if ($this->input->post('action') == 'Submit_otp') {

            $this->data['error'] = '';
            $this->data['action_status'] = FALSE;

            $customerData = $this->get_customer_details($this->uri->segment(3));

            $entered_otp = str_replace([' ', '-'], '', $this->input->post('entered_otp'));

            if ($entered_otp === $customerData['mobile_verification_code']) {

                if ($this->updateCustomer($customerData['id'], array('mobile_is_verified' => '1'))) {
                    $this->data['action_status'] = TRUE;
                } else {
                    $this->data['action_status'] = FALSE;
                    $this->data['error'] = 'Sorry! Can not update mobile verification status.';
                }
            } else {

                $this->data['error'] = "Sorry! You have entered wrong verification code.";
            }
        }

        $this->load_shop_view('mobile_verification', $this->data);
    }

    public function resend_verification_link() {

        $id = $this->uri->segment(3);

        $customerData = $this->get_customer_details($this->uri->segment(3));

        $data = array();
        $emailSend = $smsSend = FALSE;

        if ($customerData['email_is_verified'] == 0 && !empty($customerData['email'])) {
            $data['email_verification_code'] = md5(time() . $customerData['email']);
            $emailSend = TRUE;
        }
        if ($customerData['mobile_is_verified'] == 0 && !empty($customerData['phone'])) {
            $data['mobile_verification_code'] = rand(111111, 999999);
            $smsSend = TRUE;
        }


        if (!empty($data)) {
            if ($this->updateCustomer($id, $data)) {

                if ($emailSend) {
                    if ($this->sendVerificationEmail($customerData['email'], $id, $data['email_verification_code'])) {
                        $this->data['action_status'] = TRUE;
                        $this->load_shop_view('resend_email_verification', $this->data);
                    }
                } elseif ($smsSend) {
                    redirect("shop/sent_otp/$id");
                }
            } else {
                redirect("shop/sent_otp/$id");
            }
        }
    }

    public function resend_email_verification() {

        $this->data['action_status'] = FALSE;

        if (!empty($this->input->post('id')) && !empty($this->input->post('email')) && !empty($this->input->post('vecode'))) {

            if ($this->sendVerificationEmail($this->input->post('email'), $this->input->post('id'), $this->input->post('vecode'))) {

                $this->data['action_status'] = TRUE;
            }
        }

        $this->load_shop_view('resend_email_verification', $this->data);
    }

    public function email_verification() {

        $customerData = $this->get_customer_details($this->uri->segment(3));

        if (is_array($customerData)) {

            $this->data['name'] = $customerData['name'];
            $this->data['email'] = $customerData['email'];
            $this->data['mobile'] = $customerData['phone'];
            $this->data['vmcode'] = $customerData['mobile_verification_code'];
            $this->data['vecode'] = $customerData['email_verification_code'];
            $this->data['id'] = $customerData['id'];
            $this->data['email_is_verified'] = $customerData['email_is_verified'];
            $this->data['mobile_is_verified'] = $customerData['mobile_is_verified'];

            if ($customerData['email_is_verified'] == 1) {
                $this->data['action_status'] = TRUE;
            } elseif ($customerData['email_verification_code'] === $this->uri->segment(4)) {
                if ($this->updateCustomer($customerData['id'], array('email_is_verified' => '1'))) {
                    $this->data['action_status'] = TRUE;
                } else {
                    $this->data['action_status'] = FALSE;
                    $this->data['error'] = 'Sorry! Can not update email verification status.';
                }
            } else {
                $this->data['action_status'] = FALSE;
                $this->data['error'] = 'Sorry! Invalid Verification Code.';
            }
        } else {

            $this->data['action_status'] = FALSE;
            $this->data['error'] = 'Sorry! Unidentified Information';
            $this->data['name'] = '';
            $this->data['email'] = '';
            $this->data['mobile'] = '';
            $this->data['vmcode'] = '';
            $this->data['vecode'] = '';
            $this->data['id'] = '';
            $this->data['email_is_verified'] = '';
            $this->data['mobile_is_verified'] = '';
        }

        $this->load_shop_view('email_verification', $this->data);
    }

    public function signup() {

        $this->load_shop_view('signup', $this->data);
    }

    public function login() {
        $this->session->set_userdata('referred_from', $_SERVER['HTTP_REFERER']);

        if (isset($_POST['btn_submit']) && $_POST['btn_submit'] === 'Authentication') {

            $authData['login_id'] = $this->input->post('login_id');
            $authData['password'] = $this->input->post('login_passkey');

            $responce = $this->shop_model->getAuthCustomer($authData);

            if (is_array($responce) && $responce['status'] == 'SUCCESS') {

                $userdata = $responce['result'][0];

                if ($userdata['email_is_verified'] == 0 && $userdata['mobile_is_verified'] == 0) {
                    /* $this->data['login_error'] = "Sorry! Your account verification is pending.";
                      $this->data['resend_verification_link'] = TRUE;
                      $this->data['customer_id'] = $userdata['id']; */

                    $vmcode = rand(111111, 999999);
                    $data = [
                        'customer_id' => $userdata['id'],
                        'phone' => $userdata['phone'],
                        'vcode' => $vmcode,
                    ];
                    $this->session->set_userdata('guestlogininfo', $data);
                    $this->otp_sms($userdata['phone'], $vmcode);
                    return redirect('shop/getotp');
                    
                } else {

                    $sessData['id'] = $userdata['id'];
                    $sessData['shop_theme'] = $userdata['id'];
                    $sessData['name']  = $userdata['name'];
                    $sessData['email'] = $userdata['email'];
                    $sessData['phone'] = $userdata['phone'];
                    $sessData['auth_token'] = md5(time() . $userdata['phone']);

                    $this->shop_model->set_user_session($sessData);
                    $this->shop_model->userCartData($userdata['id']);

                    if ($this->session->userdata('referred_from') != '' && $this->session->userdata('referred_from') == base_url('shop/cart')) {
                        $this->session->unset_userdata('referred_from');
                        redirect('shop/checkout');
                    } else {
                        if (count($_SESSION['cart']) > 0) {
                            redirect('shop/checkout');
                        } else {
                            redirect('shop/home');
                        }
                    }
                }
            } else {
                $this->data['login_error'] = $responce['error'];
            }//end else.
        }

        /** Google Login * */
        include_once APPPATH . "third_party/googlelogin/autoload.php";

        $google_client = new Google_Client();

        $google_client->setClientId('970274428101-9ik4fu8dk2god6k1thdrevu0t0k3csgd.apps.googleusercontent.com'); //Define your ClientID

        $google_client->setClientSecret('ayQvZ-sd1usYBnzwgVq6AU-8'); //Define your Client Secret Key

        $google_client->setRedirectUri(base_url() . 'shop/googlelogin'); //Define your Redirect Uri

        $google_client->addScope('email');

        $google_client->addScope('profile');
        /** Google Login */
        /* Facebook Login Code */
        $userProfile = array();
        if ($this->facebook->is_authenticated()) {
            $userProfile = $this->facebook->request('get', '/me?fields=id,first_name,last_name,email,gender,locale,picture');
        } else {
            $this->data['authUrl'] = $this->facebook->login_url();
        }

        $login_button = '<a href="' . $google_client->createAuthUrl() . '" class="btn btn-danger" style="font-size: large;"><i class="fa fa-google fa-fw"></i> Login</a>';
        $this->data['login_button'] = $login_button;

        $this->load_shop_view('login', $this->data);
    }

    public function ___login() {


        if (isset($_POST['btn_submit']) && $_POST['btn_submit'] === 'Authentication') {

            $authData['login_id'] = $this->input->post('login_id');
            $authData['password'] = $this->input->post('login_passkey');

            $this->authcheck($authData);
        }

        /** Google Login * */
//        include_once APPPATH . "third_party/googlelogin/autoload.php";
//        $google_client = new Google_Client();
//
//        $google_client->setClientId('970274428101-9ik4fu8dk2god6k1thdrevu0t0k3csgd.apps.googleusercontent.com'); //Define your ClientID
//
//        $google_client->setClientSecret('ayQvZ-sd1usYBnzwgVq6AU-8'); //Define your Client Secret Key
//
//        $google_client->setRedirectUri(base_url() . 'shop/googlelogin'); //Define your Redirect Uri
//
//        $google_client->addScope('email');
//
//        $google_client->addScope('profile');
        /** Google Login */
        $this->welcomeValidSession();


        /* Facebook Login Code */
//        $userProfile = array();
//        if ($this->facebook->is_authenticated()) {
//            $userProfile = $this->facebook->request('get', '/me?fields=id,first_name,last_name,email,gender,locale,picture');
//            
//        } else {
//            $this->data['authUrl'] = $this->facebook->login_url();
//        }

        /* Facebook Login code */
        /* Gmail Login */
        // $this->data['google_login_url'] = $this->google->get_login_url();
        /* gmail login */

//        $login_button = '<a href="' . $google_client->createAuthUrl() . '" class="btn btn-danger" style="font-size: large;"><i class="fa fa-google fa-fw"></i> Login</a>';
//        $this->data['login_button'] = $login_button;

        $this->load_shop_view('login', $this->data);
    }

    public function facebook_login() {
        $userProfile = array();
        if ($this->facebook->is_authenticated()) {
            $this->data['authUrl'] = $this->facebook->login_url();
            $userProfile = $this->facebook->request('get', '/me?fields=id,first_name,last_name,email,gender,locale,picture');
            $customer = $this->shop_model->getCustomerByloginId($userProfile['email']);

            if (!empty($customer)) {
                $authData['login_id'] = $userProfile['email'];
                $authData['password'] = 'test@123';

                $this->authcheck($authData);
                //$this->load_shop_view('login', $this->data);
            } else {

                $data = array('name' => $userProfile['first_name'] . ' ' . $userProfile['last_name'],
                    'email' => $userProfile['email'],
                    'group_id' => '3',
                    'customer_group_id' => '1',
                    'customer_group_name' => 'General',
                    'price_group_id' => '2',
                    'is_synced' => '0',
                    'price_group_name' => 'Standered',
                    'company' => '-',
                    'address' => '-',
                    'country' => 'India',
                    'phone' => 'null',
                    'password' => md5('test@123'),
                    'email_is_verified' => '1',
                        //'email_verification_code' => $vecode,
                        // 'mobile_verification_code' => $vmcode,
                );
                // print_r($data);exit;
                $this->load->model('companies_model');
                $this->companies_model->addCompany($data);
                $authData['login_id'] = $userProfile['email'];
                $authData['password'] = 'test@123';

                $this->authcheck($authData);
                // $this->load_shop_view('login', $this->data);
            }
        }
    }

    public function logout() {

        $this->shop_model->end_user_session();
        unset($_SESSION['shipping_methods']);
        unset($_SESSION['eshop_location_id']);
        // $this->authenticate();
        /* facebook code */
        $this->facebook->destroy_session();
        redirect('shop/login');
        /**/
    }

    public function authcheck($authData) {

        $responce = $this->shop_model->getAuthCustomer($authData);

        if (is_array($responce)) {
            if ($responce['status'] == 'SUCCESS') {

                $userdata = $responce['result'][0];

                if ($userdata['email_is_verified'] == 0 && $userdata['mobile_is_verified'] == 0) {
                    $this->data['login_error'] = "Sorry! Your account verification is pending.";
                    $this->data['resend_verification_link'] = TRUE;
                    $this->data['customer_id'] = $userdata['id'];
                } else {

                    $sessData['id'] = $userdata['id'];
                    $sessData['shop_theme'] = $userdata['id'];
                    $sessData['name'] = $userdata['name'];
                    $sessData['email'] = $userdata['email'];
                    $sessData['phone'] = $userdata['phone'];
                    $sessData['auth_token'] = md5(time() . $userdata['phone']);

                    $this->shop_model->set_user_session($sessData);
                    $this->shop_model->userCartData($userdata['id']);

                    if ($this->session->userdata('referred_from') != '' && $this->session->userdata('referred_from') == base_url('shop/cart')) {
                        $this->session->unset_userdata('referred_from');
                        redirect('shop/checkout');
                    } else {
                        if (count($_SESSION['cart']) > 0) {
                            redirect('shop/checkout');
                        } else {
                            redirect('shop/home');
                        }
                    }
                }
            } else {
                $this->data['login_error'] = $responce['error'];
            }//end else.
        }
    }

    public function get_customer_details($id = NULL, $email = NULL, $phone = NULL) {

        if ($id !== NULL) {
            return $this->shop_model->getCustomerByID($id);
        } elseif ($email !== NULL) {
            return $this->shop_model->getCustomerByEmail($email);
        } elseif ($phone !== NULL) {
            return $this->shop_model->getCustomerByPhone($phone);
        } else {
            return 0;
        }
    }

    public function updateCustomer($id, $data = array()) {
        $this->db->where('id', $id);

        if ($this->db->update('companies', $data)) {

            return true;
        }
        return false;
    }

    public function update_customer() {

        if (empty($_POST)) {
            if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {
                redirect('shop/myaccount');
            } else {
                redirect('shop/my_account');
            }
        }

        $id = $this->input->post('customer_id');

        $data['name'] = $this->input->post('name');
        $data['address'] = $this->input->post('address');
        $data['email'] = $this->input->post('email');
        $data['city'] = $this->input->post('city');
        $data['state'] = $this->input->post('state');
        $data['country'] = $this->input->post('country');
        $data['postal_code'] = $this->input->post('postal_code');
        $data['company'] = $this->input->post('company');
        if (isset($_POST['phone'])) {
            $data['phone'] = $this->input->post('phone');
        }
        if ($this->Settings->default_currency == 'INR') {
            $data['gstn_no'] = $this->input->post('gstn_no');
        } else {
            $data['vat_no'] = $this->input->post('vat_no');
        }

        $this->db->where('id', $id);

        if ($this->db->update('companies', $data)) {
            if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {
                redirect('shop/myaccount?msg=success');
            } else {

                redirect('shop/my_account?msg=success');
            }
        } else {
            if ($this->shoptheme == 'T1' || $this->shoptheme == 'T2') {
                redirect('shop/myaccount?msg=error');
            } else {
                redirect('shop/my_account?msg=error');
            }
        }
    }

    public function forgot_password() {

        if (!empty($this->input->post('login_id'))) {

            $customer = $this->shop_model->getCustomerByloginId($this->input->post('login_id'));

            if (!empty($customer)) {
                $data['mobile_verification_code'] = rand(123456, 987654);

                if ($this->updateCustomer($customer['id'], $data)) {

                    /*
                     *   Send code by email                    
                     */
                    $subject = "Request received for change password";
                    $from = 'info@simplysafe.in';
                    $from_name = 'Support Simplysafe';

                    $content = "<p>Dear " . $customer['name'] . ",<br/><br/></p><p>We have received your change password request for following simplypos E-shop.</p>";
                    $content .= "<p>Eshop: " . base_url('shop') . "</p>";
                    $content .= "<p><br/><br/>Use the code to verify your request : <big><b>" . $data['mobile_verification_code'] . "</b></gib><br/><br/></p>";
                    $content .= "<p>Thank you,<br/>Simplypos Eshop Services</p>";

                    $rec = $this->sendEmail($customer['email'], $subject, $content, $from, $from_name, $attachment = null, $cc = null, $bcc = null);

                    /*
                     * Send code by SMS 
                     */
                    $msg = "Simplypos Forget password verification code: " . $data['mobile_verification_code'];
                    $this->sma->SendSMS($customer['phone'], $msg, 'ESHOP_FORGET_PASSWORD');
                }

                redirect('shop/reset_password?token=' . base64_encode($customer['phone']));
            } else {
                $this->data['login_error'] = "Email/Mobile No is Not Registered!";
            }
        }

        $this->load_shop_view('forgot_password', $this->data);
    }

    public function reset_password() {


        $identity = trim($this->input->post('identity'));
        $verification_code = trim($this->input->post('verification_code'));
        $new_passwd = trim($this->input->post('new_passwd'));
        $confirm_passwd = trim($this->input->post('confirm_passwd'));

        if ($this->input->post('btn_submit') == 'resetpasswd') {
            if (strlen($new_passwd) <= 2) {
                $this->data['login_error'] = "Password length should be minimum 3 charectors";
            } elseif ($new_passwd !== $confirm_passwd) {
                $this->data['login_error'] = "Confirm password not match.";
            } else {

                $customer = $this->shop_model->getCustomerByloginId($identity);
                if (!empty($customer)) {
                    if ($customer['mobile_verification_code'] === $verification_code) {

                        $data['mobile_verification_code'] = rand(123456, 987654);
                        $data['password'] = md5($new_passwd);
                        if ($this->updateCustomer($customer['id'], $data)) {
                            redirect('shop/password_reset_success');
                        } else {
                            $this->data['login_error'] = "Password reset failed";
                        }
                    } else {
                        $this->data['login_error'] = "Verification code is not valid";
                    }
                } else {
                    $this->data['login_error'] = "Identity is not valid";
                }
            }
        }

        if (isset($_GET['token'])) {
            $this->data['customerIdentity'] = base64_decode($_GET['token']);
        }
        $this->load_shop_view('reset_password', $this->data);
    }

    public function password_reset_success() {

        $this->load_shop_view('password_reset_success', $this->data);
    }

    public function sendEmail($email, $subject, $content, $from = null, $from_name = null, $attachment, $cc = null, $bcc = null) {

        $res = $this->sma->send_email($email, $subject, $content, $from = null, $from_name = null, $attachment, $cc = null, $bcc = null);

        return $res;
    }

    private function json_op($arr) {
        $arr = is_array($arr) ? $arr : array();
        echo @json_encode($arr);
        exit;
    }

    //15/07/2019
    public function addTowishlistItems() {
        $userId = $this->data['user_id'];
        $product_id = $_GET['product_id'];
        $dataArr = array($userId, $product_id);
        if ($_GET['option']) {
            $optionarray = explode("~", $_GET['option']);
            if ($optionarray[0] != '') {
                $dataArr [] = $optionarray[0];
            }
        }

        $this->shop_model->addWishList($dataArr);
        // $this->updateWishCount();
        $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->data['user_id']);
        $wishlist_count = $this->data['wishlistdata']['count'];
        echo $wishlist_count;
    }

    /* public function updateWishCount() {
      $this->data['wishlistdata'] = $this->shop_model->getWishListItems($this->data['user_id']);
      $wishlist_count = $this->data['wishlistdata']['count'];
      echo $wishlist_count;
      } */

    public function WishListItems() {
        $userId = $this->data['user_id'];
        $this->data['wishlistdata'] = $this->shop_model->getWishListItems($userId);
        $this->load_shop_view($this->shoptheme . '/wishlist', $this->data);
    }

    public function removewishlist() {
        $proId = $_GET['product_id'];
        $removedata = ['product_id' => $proId, 'user_id' => $this->data['user_id']];
        if ($_GET['option']) {
            $optionarray = explode("~", $_GET['option']);
            if ($optionarray[0] != '') {
                $removedata['option_id'] = $optionarray[0];
            }
        }

        $this->db->where($removedata);
        $this->db->delete('sma_eshop_wishlist');
    }

    /* public function Filterproducts() {
      $getvar = $_GET;
      $pageno = $_GET['pageno'];
      $catId = $_GET['catId'];
      $catIdss = explode('_', $catId);
      $data = $this->shop_model->FilterproductsData($getvar);
      $count = $data['count'];
      $totalPages = $data['totalPages'];
      $output = '';
      $shoptheme = 'T2';
      $itemsPerRow = 4;
      $item_col = 12 / $itemsPerRow;
      if (is_array($data['rows']) && !empty($data['rows'])) {

      $p = 0;
      if ($p == 1) {

      }
      foreach ($data['rows'] as $rows) {
      $p++;
      $output .= '<div class="w3ls_w3l_banner_nav_right_grid1 w3ls_w3l_banner_nav_right_grid1_veg">';
      $assets = '/themes/default/assets/shop/';
      $fielname = (file_exists('assets/uploads/thumbs/' . $rows['image'])) ? 'assets/uploads/thumbs/' . $rows['image'] : "assets/uploads/thumbs/no_image.png";
      $output .= '<div class="col-md-' . $item_col . ' w3ls_w3l_banner_left w3ls_w3l_banner_left_asdfdfd">';
      $output .= '<div class="hover14 column">';
      $output .= '<div class="agile_top_brand_left_grid w3l_agile_top_brand_left_grid">';
      $output .= '<div class="agile_top_brand_left_grid_pos"><img src="' . base_url($assets . $shoptheme) . '/images/instock.png" alt=" " class="img-responsive img-rounded" /> </div>';

      $output .= '<div class="agile_top_brand_left_grid1">';
      $output .= '<figure>';
      $output .= '<div class="snipcart-item block">';
      $output .= '<div class="snipcart-thumb">';
      $output .= '<a href="' . base_url('shop/product_info/' . md5($rows['id'])) . '" />
      <img src="' . base_url($thumbs . $fielname) . '" alt="' . $rows['code'] . '" class="img-responsive img-rounded" />
      <p class="text-center">' . $rows['name'] . '</p>
      <h4 class="text-center">' . $currency_symbol . '' . number_format($rows['price'], 2) . ' Rs.</span>
      </h4></a>';
      $veriants = $this->shop_model->getProductVeriantsByHash(md5($rows['id']));
      if ($veriants) {
      $output .= '<div class="snipcart-details" style="margin: 0.5em auto 0">';
      $output .= '<select class="form-control option1" id="variants_' . $rows['id'] . '" name="variants_' . $rows['id'] . '">';
      foreach ($veriants as $veriantskey => $veriantss) {
      $output .= '<option value="' . $veriantskey . '~' . $veriantss->name . '~' . $veriantss->price . '">' . $veriantss->name . '</option>';
      }
      $output .= '</select>';
      $output .= '</div>';
      } else {
      $output .= '<div class="snipcart-details" style="margin: 0.5em auto 0">&nbsp;';
      $output .= '</div>';
      }

      $output .= ' <div class="snipcart-details " >
      <table>
      <tr>
      <td style="width: 50%"><strong>QTY:</strong></td>
      <td >
      <input class="form-control" id="qty_' . $rows['id'] . '" value="1" type="number" min="1">
      </td>
      </tr>
      </table>
      </div>  ';

      $output .= '<div class="snipcart-details">
      <input type="button" name="addtocart" id="addtocart"  onclick="addToCart(' . $rows['id'] . ')" value="Add to cart" class="button pull-left" />
      <span id="addtowishlist_' . $product['id'] . '" onclick="addTowishlist(' . $rows['id'] . ')" class="button pull-right" style="background:green; padding:5px; font-size:12px;color:#fff;width:40%; cursor: pointer;">WISHLIST</span>
      </div>';
      $output .= '<div class="snipcart-details">
      <a href="' . base_url('shop/product_info/' . md5($rows['id'])) . '"><input type="button" name="view"  value="View Details" class="btn btn-info col-sm-12" /></a>
      </div>';
      $output .= '</div>';
      $output .= '</div>';
      $output .= '</figure>';
      $output .= '</div>';

      $output .= '</div>';
      $output .= '</div>';
      $output .= '</div>';
      if ($p == $itemsPerRow) {
      $p = 0;
      $output .= ' <div class="clearfix"> </div>';
      }
      }
      if ($p != $itemsPerRow && $p != 0) {
      $output .= '</div>';
      $output .= ' <div class="clearfix"> </div>';
      $output .= '</div>';
      }
      } else {
      echo '<div align="center" class="text-danger" style="padding:10em 0 28em 0">Sorry, No Products Found</div>';
      }
      $product_category_hash = empty($this->uri->segment(3)) ? md5($this->data['shopinfo']['default_category']) : $this->uri->segment(3);
      echo $output;
      if ($totalPages > 1) {
      $pagingData['itemsPerPage'] = 20;
      $pagingData['count'] = $count;
      $pagingData['pagCallFunction'] = 'filterProducts';
      $pagingData['displayPage'] = 10;
      $pagingData['load_ajax'] = TRUE;
      $pagingData['categoryhash'] = $product_category_hash;
      $pagingData['pageno'] = $pageno;
      $this->data['pagignation'] = $this->AjaxPagignations($pagingData);
      }
      echo '</div>';
      } */

    /*
     * Get Sub Category 
     */

    public function FilterSubcategory() {

        $catId = $_GET['catId'];
        $catIdss = explode('_', $catId);

        $subcategorys = $this->shop_model->get_subcategorys($catIdss);

        $output = '<ul class="list-group" id="subcatlist" style="height: 300px !important; overflow: overlay;">';
        foreach ($subcategorys as $subcategorys_val) {
            $output .= '<li class="list-group-item category_check">';
            $output .= '<div class="form-check">';
            $output .= '<label class="form-check-label containercheck"> &nbsp; &nbsp;' . ucfirst($subcategorys_val->name);
            $output .= '<input type="checkbox" name="subcategory" class="form-check-input filter_subclick" onclick="change_value()" id="cat_' . $subcategorys_val->id . '" value="' . $subcategorys_val->id . '">';
            $output .= '<span class="checkmark"></span>';
            $output .= '</label>';
            $output .= '</div>';
            $output .= '</li>';
        }
        $output .= '</ul>';
        echo $output;
    }

    public function Filterproducts() {
        $getvar = $_GET;
        $pageno = $_GET['pageno'];
        $catId = $_GET['catId'];
        $catIdss = explode('_', $catId);
        $data = $this->shop_model->FilterproductsData($getvar);
        $count = $data['count'];
        $totalPages = $data['totalPages'];
        $output = '';
        $shoptheme = 'T2';
        $itemsPerRow = 4;
        $item_col = 12 / $itemsPerRow;

        if (!$this->shopinfo['eshop_overselling']) {
            $products_stocks = $this->data['products_stocks'];
            $pending_orders = $this->data['pending_orders'];
        }

        $this->data['productsDataArr'] = $data['rows'];

        echo '<h4 class="w3l_fruit" style="padding: 20px 0 0 20px;"><i class="fa fa-sitemap"></i> Filtere Products Results <small> (' . $count . ' Items) </small></h4>';

        if ($totalPages > 1) {
            $pagingData['itemsPerPage'] = 20;
            $pagingData['count'] = $count;
            $pagingData['pagCallFunction'] = 'filterProducts';
            $pagingData['displayPage'] = 10;
            $pagingData['load_ajax'] = TRUE;
            $pagingData['categoryhash'] = $product_category_hash;
            $pagingData['pageno'] = $pageno;
            $this->data['pagignation'] = $this->AjaxPagignations($pagingData);
        }

        $this->load->view('default/views/shop/T2/products_listing', $this->data);
    }

    public function AjaxPagignations($pagingData) {
        $categoryhash = $pagingData['categoryhash'];
        $total_records = $pagingData['count'];
        $active_pageno = $pagingData['pageno'];
        $itemsPerPage = $pagingData['itemsPerPage'];
        $pagCallFunction = $pagingData['pagCallFunction'];
        $displayPage = (!empty($pagingData['displayPage'])) ? $pagingData['displayPage'] : 5;
        if ($total_records <= $itemsPerPage)
            return false;

        $pagelist = ceil($total_records / $itemsPerPage);

        $pagignation = '<div align="center"><ul class="pagination pagination-sm">';

        $prePage = $active_pageno - 1;
        $nextPage = $active_pageno + 1;

        if ($active_pageno == 1) {
            $pagignation .= '<li class="disabled"><a>&laquo;</a></li>';
        }

        if ($active_pageno > 1) {
            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(' . $prePage . ')">&laquo;</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $prePage . ')">&laquo;</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/' . $prePage) . '">&laquo;</a></li>';
            }
        }

        $initpage = ($displayPage < $active_pageno && $pagelist > $displayPage ) ? ceil($active_pageno - ($displayPage / 2)) : 1;

        if ($initpage > 1) {
            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(1)">1</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',\'1\')">1</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/1') . '">1</a></li>';
            }

            $pagignation .= '<li class="disabled"><a>...</a></li>';
        }

        for ($i = 1; $i <= $displayPage; $i++) {

            $p = $initpage;
            if ($p > $pagelist)
                break;

            $activeClass = ($active_pageno == $p) ? ' class="active" ' : '';

            if ($pagingData['load_ajax'] == TRUE) {

                $pagignation .= '<li ' . $activeClass . ' ><a onclick="' . $pagCallFunction . '(' . $p . ')">' . $p . '</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li ' . $activeClass . ' ><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $p . ')">' . $p . '</a></li>';
            } else {
                $pagignation .= '<li ' . $activeClass . ' ><a href="' . base_url('shop/home/' . $categoryhash . '/' . $p) . '">' . $p . '</a></li>';
            }


            $initpage++;
        }

        if ($pagelist > $displayPage && $pagelist > $p) {
            $pagignation .= '<li><a>...</a></li>';

            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(' . $pagelist . ')">' . $pagelist . '</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $pagelist . ')">' . $pagelist . '</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/' . $pagelist) . '">' . $pagelist . '</a></li>';
            }
        }

        if ($active_pageno < $pagelist) {

            if ($pagingData['load_ajax'] == TRUE) {
                $pagignation .= '<li><a onclick="' . $pagCallFunction . '(' . $nextPage . ')">&raquo;</a></li>';
            } elseif (!empty($pagingData['search_products'])) {
                $pagignation .= '<li><a onclick="searchPage(\'' . $pagingData['search_products'] . '\',' . $nextPage . ')">&raquo;</a></li>';
            } else {
                $pagignation .= '<li><a href="' . base_url('shop/home/' . $categoryhash . '/' . $nextPage) . '">&raquo;</a></li>';
            }
        }
        if ($active_pageno == $pagelist) {
            $pagignation .= '<li class="disabled"><a>&raquo;</a></li>';
        }

        $pagignation .= ' </ul></div>';
        return $pagignation;
    }

    public function Orders_Emails($orderdata) {

        $ordersArr = unserialize($orderdata['order_data']);
        $cart_tax_total = $ordersArr['cart_tax_total'];
        $cart_sub_total = $ordersArr['cart_sub_total'];
        $cart_tax_rate = $ordersArr['cart_tax_total'];
        $order_tax_total = $ordersArr['order_tax_total'];
        $cart_order_tax = $ordersArr['cart_order_tax'];
        $order_tax_name = $ordersArr['order_tax_name'];
        $cart_gross_total = $ordersArr['cart_gross_total'];

        $ref_No = $orderdata['ref_No'];
        $Order_no = $orderdata['invoice_no'];
        $payment_status = $orderdata['payment_status'];
        $payment_methods = $orderdata['payment_methods'];
        $shippingTypeName = $orderdata['shippingTypeName']; //18092019
        $shiping_amount = (($orderdata['shipping_amount']) ? $orderdata['shipping_amount'] : '0');

        if (empty($orderdata['user_email']))
            return false;
        $subject = "Simplypos eshop order";
        $from = 'sales@simplysafe.in';
        $from_name = 'Sales Simplysafe';

        $content = "<p>Dear Customer,<br/><br/></p><p>Your E-shop order is received successfully.</p>";

        $content .= '<style>tr>th,tr>td{padding: 8px;line-height: 1.42857143;vertical-align: top;border-top: 1px solid #ddd;} table{ border-collapse: collapse;
            border-spacing: 0;} tr{display: table-row;vertical-align: inherit;border-color: inherit;border: 1px solid #ddd;}tr>th{background:#000; color:#fff;tr :first-child{border-right:0;}}</style>';
        $content .= '<p>Dear ' . $orderdata['user_name'] . ', thank you for purchasing our products.<p>';
        $content .= '<table width="100%" align="center">
                        <tr>
                            <td><b>Ref.No.:</b> ' . $Order_no . '</td><td></td><td></td><td></td>
                            <td><b>Date:</b> ' . $orderdata['order_date'] . '</td>
                        </tr>
                      <tr>
                      <th style="background: #000;color:#FFF;">Item</th>
                      <th style="background: #000;color:#FFF;">Price</th>
                      <th style="background: #000;color:#FFF;">Quantity</th>
                                         
                      <th style="background: #000;color:#FFF;">Tax</th>
                      <th style="background: #000;color:#FFF;">Total</th>
                      </tr>';

        $items = $ordersArr['items'];
        $total_qty = 0;
        foreach ($items as $itemkey => $itemsval) {
            $total_qty += $itemsval['qty'];

            $content .= '<tr>';
            $content .= '<td align="center">' . $itemsval['name'] . ($itemsval['vname'] );
            if ($itemsval['vname'])
                $content .= '<sub>(' . $itemsval['vname'] . ')</sub></td>';
            $content .= '<td align="center">Rs.' . number_format($itemsval['real_unit_price'], 2) . '</td>';
            $content .= '<td align="center">' . $itemsval['qty'] . '</td>';
            //  $content .= '<td align="center">Rs.' . number_format($itemdata['item_discount'], 2) . '<sub>(' . floor($itemdata['discount']) . '%)</sub></td>';
            $content .= '<td align="center">Rs.' . number_format($itemsval['item_tax_total'], 2) . '<sub>(' . floor($itemsval['item_tax_rate']) . '%)</sub></td>';
            $content .= '<td align="center">Rs.' . number_format($itemsval['item_price_total'], 2) . '</td>';
            $content .= '</tr>';
        }

        $content .= '<tr><td colspan="2" align="right" >Total</td><td>' . $total_qty . '</td><td align="right">Sub Total: </td><td align="center">Rs.' . number_format(str_replace(",", "", $cart_sub_total), 2) . '</td></tr>';

        $content .= '<tr><td colspan="3"><td align="right">Item Tax: </td><td align="center">Rs.' . number_format(str_replace(",", "", $cart_tax_total), 2) . '</td></tr>';

        if ($order_tax_total) {
            $content .= '<tr><td colspan="3"><td align="right">Order Tax(' . $order_tax_name . '): </td><td align="center">Rs.' . number_format($order_tax_total, 2) . '</td></tr>';
        }
        $content .= '<tr><td colspan="3"><td align="right">Shipping: </td><td align="center">Rs.' . ($orderdata['shipping_amount']) . '</td></tr>';
        $content .= '<tr><td colspan="3"><td align="right"><b>Gross Total: </b></td><td align="center"><b>Rs.' . number_format($cart_gross_total + $shiping_amount, 2) . '</b></td> 
                    </tr>';
        $content .= '</table>';
        $content .= '</br><div>
                          
                        <table width="40%" border="0" align="center">
                            <tr>
                                <th align"center" style="background: #000;color:#FFF;" colspan="2">Billing Summary</th>
                            </tr>
                            <tr> 
                                <td>Shipping by</td><td>' . $shippingTypeName . '</td>
                            </tr>
                            <tr>
                                <td>Payment Status :</td><td>' . $payment_status . '</td>
                            </tr>
                             <tr>
                                <td>Payment Method :</td> <td>' . $payment_methods . '</td>
                            </tr>
                            <tr>
                                <td>Total Billing Amount :</td> <td>' . number_format($cart_gross_total + $shiping_amount, 2) . ' Rs.</td>
                            </tr>
                            </table>
                       </div>';
        $content .= '<div>
                      </br>
                          <table width="70%" border="0" align="center">
                          <tr>
                                <td align"left"><b>Billing Details</b></td>
                                <td align"left"><b>Shipping Details</b></td>
                            </tr>
                            <tr> <td>Name : ' . $orderdata['billing_name'] . '<br>Phone : ' . $orderdata['billing_phone'] . '<br>Email : ' . $orderdata['billing_email'] . '<br>Address1 : ' . $orderdata['billing_addr1'] . '<br>Address 2 :' . $orderdata['billing_addr1'] . '<br>City :' . $orderdata['billing_city'] . '<br>State :' . $orderdata['billing_state'] . '<br>Country 
 :' . $orderdata['billing_country'] . '<br>Zipcode :' . $orderdata['billing_zipcode'] . '<br>' . '</td>
                                <td>Name : ' . $orderdata['shipping_name'] . '<br>Phone : ' . $orderdata['shipping_phone'] . '<br>Email : ' . $orderdata['shipping_email'] . '<br>Address1  :' . $orderdata['shipping_addr1'] . '<br>Address 2:' . $orderdata['shipping_addr1'] . '<br>City : ' . $orderdata['shipping_city'] . '<br>State : ' . $orderdata['shipping_state'] . '<br>Country : ' . $orderdata['shipping_country'] . '<br>Zipcode : ' . $orderdata['shipping_zipcode'] . '</td>
                            </tr>
                         </table>
                       </div>';

        $content .= "</br><p>Thank you,<br/>Simplypos Eshop Services</p>";

        $rec = $this->sendEmail($orderdata['user_email'], $subject, $content, $from, $from_name, $attachment = null, $cc = null, $bcc = null);


        // merchant revives a new order
        /*
          $WarehouseId = $this->data['pos_settings']->default_eshop_warehouse;
          $RowWarehouse = $this->orders_model->getWarehouseByID($WarehouseId);
          $Billerto = $RowWarehouse->email;
          $subjectmarchant = "New order Received from ".$orderdata['user_name'];
          $eshop_details = $this->eshop_model->getEshopSettings('1'); //$eshop_details->shop_email
          $mrec = $this->sendEmail($Billerto, $subjectmarchant, $content, $from, $from_name, $attachment = null, $cc = null, $bcc = null);
         */


        if ($rec === FALSE) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    /**
     * New Function
     */
    public function category() {

        $this->load_shop_view($this->shoptheme . '/categorys', $this->data);
    }

    public function subcategory($id = NULL) {

        $this->data['subcategorylist'] = $this->shop_model->getChildCategories($id);
        $this->data['category_data'] = $this->shop_model->getSingleCategory($id);
        $this->load_shop_view($this->shoptheme . '/subcategorys', $this->data);
    }

    // SMS Send Funtion
    public function sms_send($passurl) {
        //echo $passurl;exit;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $passurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    //End  SMS Send Function

    /**
     * 
     * @param type $mobileno
     * @param type $otp_no
     */
    public function otp_sms($mobileno, $otp_no) {

        $mesage = 'OTP for Eshop is ' . $otp_no . 'for single use.';
        $this->sma->SendSMS($mobileno, $mesage, 'ESHOP_SIGNUP_OTP');
    }

    /**
     * Product Name
     */
    public function getProductName() {
        $productmname = $this->shop_model->getProductName();
        $pr = array();
        foreach ($productmname as $productmname_val) {
            $pr[] = $productmname_val['name'];
        }
        echo json_encode($pr);
    }

    /**
     * End New Function
     */

    /**
     * Download PDF
     * @param type $code
     */
    public function download_pdf($code, $Type) {
        if ($Type == 'orders')
            $passurl = base_url('reciept/pdforder/') . $code;
        else
            $passurl = base_url('reciept/pdf/') . $code;
        redirect($passurl);
    }

    /**
     * OTP Verification
     */
    public function otp_varification() {
        $this->data['error'] = '';
        $this->data['action_status'] = FALSE;

        if ($_POST) {
            $otp = str_replace([" ", "-"], "", $this->input->post('entered_otp'));
            $id = $this->input->post('otp_id');

            $condition = [
                'id' => $id,
                'mobile_verification_code' => $otp,
            ];
            $otpData = $this->shop_model->otp_action('check', $condition, '');
            if ($otpData) {
                $this->data['action_status'] = TRUE;

                $this->shop_model->otp_action('Update', ['phone' => $otpData->phone, 'id' => $otpData->id], ['mobile_verification_code' => NULL, 'mobile_is_verified' => '1']);
            } else {
                $this->data['action_status'] = FALSE;
                $this->data['error'] = 'Sorry, Please enter valid OTP';
            }
        }
        $this->load_shop_view('otp_verification', $this->data);
    }

    /**
     * Resend OTP
     * @param type $id
     * @return type
     */
    public function resend_otp($id) {
        $getdata = $this->shop_model->otp_action('check', ['id' => $id], '');
        $otpnumber = rand(111111, 999999);
        $fieldotp = [
            'mobile_verification_code' => $otpnumber,
        ];
        $result = $this->shop_model->otp_action('Update', ['id' => $getdata->id], $fieldotp);
        $this->otp_sms($getdata->phone, $otpnumber);
        return redirect($_SERVER['HTTP_REFERER']);
    }

    /* shopkeeper mail */

    public function Shopkeeper_Emails($orderdata) {

        $ordersArr = unserialize($orderdata['order_data']);
        $cart_tax_total = $ordersArr['cart_tax_total'];
        $cart_sub_total = $ordersArr['cart_sub_total'];
        $cart_tax_rate = $ordersArr['cart_tax_total'];
        $order_tax_total = $ordersArr['order_tax_total'];
        $cart_order_tax = $ordersArr['cart_order_tax'];
        $order_tax_name = $ordersArr['order_tax_name'];
        $cart_gross_total = $ordersArr['cart_gross_total'];

        $ref_No = $orderdata['ref_No'];
        $Order_no = $orderdata['invoice_no'];
        $payment_status = $orderdata['payment_status'];
        $payment_methods = $orderdata['payment_methods'];
        $shippingTypeName = $orderdata['shippingTypeName']; //18092019
        $shiping_amount = (($orderdata['shipping_amount']) ? $orderdata['shipping_amount'] : '0');

        if (empty($orderdata['user_email']))
            return false;
        $subject = "Simplypos eshop order";
        $from = 'sales@simplysafe.in';
        $from_name = 'Sales Simplysafe';

        $content = "<p>Dear Shopkeeper,<br/><br/></p><p>New order Received from " . $orderdata['user_name'] . " - +91" . $orderdata['phone'] . " Order Id:- " . $Order_no . "</p>"; ///dear shop
        // $content = "<p>Dear Customer,<br/><br/></p><p>Your E-shop order is received successfully.</p>";///dear shop

        $content .= '<style>tr>th,tr>td{padding: 8px;line-height: 1.42857143;vertical-align: top;border-top: 1px solid #ddd;} table{ border-collapse: collapse;
            border-spacing: 0;} tr{display: table-row;vertical-align: inherit;border-color: inherit;border: 1px solid #ddd;}tr>th{background:#000; color:#fff;tr :first-child{border-right:0;}}</style>';
        //$content .= '<p>Dear ' . $orderdata['user_name'] . ', thank you for purchasing our products.<p>';//yty
        $content .= '<table width="100%" align="center">
                        <tr>
                            <td><b>Ref.No.:</b> ' . $Order_no . '</td><td></td><td></td><td></td>
                            <td><b>Date:</b> ' . $orderdata['order_date'] . '</td>
                        </tr>
                      <tr>
                      <th>Item</th>
                      <th>Price</th>
                      <th>Quantity</th>
                                         
                      <th>Tax</th>
                      <th>Total</th>
                      </tr>';

        $items = $ordersArr['items'];

        foreach ($items as $itemkey => $itemsval) {


            $content .= '<tr>';
            $content .= '<td align="center">' . $itemsval['name'] . ($itemsval['vname'] );
            if ($itemsval['vname'])
                $content .= '<sub>(' . $itemsval['vname'] . ')</sub></td>';
            $content .= '<td align="center">Rs.' . number_format($itemsval['real_unit_price'], 2) . '</td>';
            $content .= '<td align="center">' . $itemsval['qty'] . '</td>';
            //  $content .= '<td align="center">Rs.' . number_format($itemdata['item_discount'], 2) . '<sub>(' . floor($itemdata['discount']) . '%)</sub></td>';
            $content .= '<td align="center">Rs.' . number_format($itemsval['item_tax_total'], 2) . '<sub>(' . floor($itemsval['item_tax_rate']) . '%)</sub></td>';
            $content .= '<td align="center">Rs.' . number_format($itemsval['item_price_total'], 2) . '</td>';
            $content .= '</tr>';
        }

        $content .= '<tr><td colspan="3"></td><td align="right">Sub Total: </td><td align="center">Rs.' . number_format($cart_sub_total, 2) . '</td></tr>';

        $content .= '<tr><td colspan="3"><td align="right">Item Tax: </td><td align="center">Rs.' . number_format($cart_tax_total, 2) . '</td></tr>';
        if ($order_tax_total) {
            $content .= '<tr><td colspan="3"><td align="right">Order Tax(' . $order_tax_name . '): </td><td align="center">Rs.' . number_format($order_tax_total, 2) . '</td></tr>';
        }
        $content .= '<tr><td colspan="3"><td align="right">Shipping: </td><td align="center">Rs.' . ($orderdata['shipping_amount']) . '</td></tr>';
        $content .= '<tr><td colspan="3"><td align="right"><b>Gross Total: </b></td><td align="center"><b>Rs.' . number_format($cart_gross_total + $shiping_amount, 2) . '</b></td> 
                    </tr>';
        $content .= '</table>';
        $content .= '</br><div>
                          
                        <table width="40%" border="0" align="center">
                            <tr>
                                <th align"center" colspan="2">Billing Summary</th>
                            </tr>
                            <tr> 
                                <td>Shipping by</td><td>' . $shippingTypeName . '</td>
                            </tr>
                            <tr>
                                <td>Payment Status :</td><td>' . $payment_status . '</td>
                            </tr>
                             <tr>
                                <td>Payment Method :</td> <td>' . $payment_methods . '</td>
                            </tr>
                            <tr>
                                <td>Total Billing Amount :</td> <td>' . number_format($cart_gross_total + $shiping_amount, 2) . ' Rs.</td>
                            </tr>
                            </table>
                       </div>';
        $content .= '<div>
                      </br>
                          <table width="70%" border="0" align="center">
                          <tr>
                                <td align"left"><b>Billing Details</b></td>
                                <td align"left"><b>Shipping Details</b></td>
                            </tr>
                            <tr> <td>Name : ' . $orderdata['billing_name'] . '<br>Phone : ' . $orderdata['billing_phone'] . '<br>Email : ' . $orderdata['billing_email'] . '<br>Address1 : ' . $orderdata['billing_addr1'] . '<br>Address 2 :' . $orderdata['billing_addr1'] . '<br>City :' . $orderdata['billing_city'] . '<br>State :' . $orderdata['billing_state'] . '<br>Country 
 :' . $orderdata['billing_country'] . '<br>Zipcode :' . $orderdata['billing_zipcode'] . '<br>' . '</td>
                                <td>Name : ' . $orderdata['shipping_name'] . '<br>Phone : ' . $orderdata['shipping_phone'] . '<br>Email : ' . $orderdata['shipping_email'] . '<br>Address1  :' . $orderdata['shipping_addr1'] . '<br>Address 2:' . $orderdata['shipping_addr1'] . '<br>City : ' . $orderdata['shipping_city'] . '<br>State : ' . $orderdata['shipping_state'] . '<br>Country : ' . $orderdata['shipping_country'] . '<br>Zipcode : ' . $orderdata['shipping_zipcode'] . '</td>
                            </tr>
                         </table>
                       </div>';

        //$content .= "</br><p>Thank you,<br/>Simplypos Eshop Services</p>";
        //$rec = $this->sendEmail($orderdata['user_email'], $subject, $content, $from, $from_name, $attachment = null, $cc = null, $bcc = null);
        // merchant revives a new order
        $WarehouseId = $this->data['pos_settings']->default_eshop_warehouse;
        $RowWarehouse = $this->orders_model->getWarehouseByID($WarehouseId);
        $Billerto = $RowWarehouse->email;
        $subjectmarchant = "New order Received from " . $orderdata['user_name'];
        $eshop_details = $this->eshop_model->getEshopSettings('1'); //$eshop_details->shop_email
        $mrec = $this->sendEmail($Billerto, $subjectmarchant, $content, $from, $from_name, $attachment = null, $cc = null, $bcc = null);



        if ($mrec === FALSE) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    /*     * ***** */

    /**
     * Paytm Payment Gatway
     * @return type
     */
    public function paytm_init($paytmpayment) {

        $sale_id = $paytmpayment['order_id'];

        if ((int) $sale_id > 0):
            $_req = $this->shop_model->getPaytmTransaction(array('eshop_order' => $sale_id));
            if ($_req->id):
                $this->session->set_flashdata('error', "Paytm" . lang('payment_process_already_initiated'));
                redirect('shop/myaccount');
            endif;
            $sale = $this->site->getSaleByIDEshop($sale_id);
            if ($sale->id == $sale_id):
                $customer = $this->site->getCompanyByID($sale->customer_id);

                $ci = get_instance();
                $ci->config->load('payment_gateways', true);
                $payment_config = $ci->config->item('payment_gateways');

                $paytm_credential = $payment_config['paytm'];

                $this->load->library('paytm', $paytm_credential);

                $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

                $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

                $API_URL = isset($paytm_credential['PAYTM_TXN_URL']) && !empty($paytm_credential['PAYTM_TXN_URL']) ? $paytm_credential['PAYTM_TXN_URL'] : '';

                $PAYTM_MERCHANT_WEBSITE = isset($paytm_credential['PAYTM_MERCHANT_WEBSITE']) && !empty($paytm_credential['PAYTM_MERCHANT_WEBSITE']) ? $paytm_credential['PAYTM_MERCHANT_WEBSITE'] : '';

                $arr['tid'] = time();

                $paramList["MID"] = $PAYTM_MERCHANT_MID;
                $paramList["ORDER_ID"] = $sale->id;
                $paramList["CUST_ID"] = $customer->id;
                $paramList["INDUSTRY_TYPE_ID"] = 'Retail';
                $paramList["CHANNEL_ID"] = 'WEB';
                $paramList["TXN_AMOUNT"] = $this->sma->formatDecimal($sale->grand_total);
                $paramList["WEBSITE"] = $PAYTM_MERCHANT_WEBSITE;
                $paramList["MSISDN"] = $customer->phone; //Mobile number of customer
                $paramList["EMAIL"] = $customer->email;  //Email ID of customer
                $paramList["VERIFIED_BY"] = "EMAIL"; //
                $paramList["IS_USER_VERIFIED"] = "YES"; //
                $paramList['CALLBACK_URL'] = base_url('shop/paytm_notify');

                try {

                    $checkSum = $this->paytm->getChecksumFromArray($paramList, $PAYTM_MERCHANT_KEY);

                    //$this->data['merchant_id']  = $merchant_id;
                    // $this->data['paytm_access_code'] = $access_code;
                    $this->data['paramList'] = $paramList;
                    $this->data['PAYTM_TXN_URL'] = $API_URL;
                    $this->data['CHECKSUMHASH'] = $checkSum;

                    $this->shop_model->addpaytmTransaction(array('sale_id' => $sale_id, 'req_data' => $paramList));

                    $this->load_shop_view($this->shoptheme . '/paytm', $this->data);
//                    $this->load->view($this->theme . 'pos/paytm', $this->data);
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            endif;
        endif;
    }

    /**
     * Paytm Payment
     */
    public function paytm_notify() {

        $this->load->library('paytm');

        $ci = get_instance();
        $ci->config->load('payment_gateways', true);
        $payment_config = $ci->config->item('payment_gateways');

        $paytm_credential = $payment_config['paytm'];

        $PAYTM_MERCHANT_KEY = isset($paytm_credential['PAYTM_MERCHANT_KEY']) && !empty($paytm_credential['PAYTM_MERCHANT_KEY']) ? $paytm_credential['PAYTM_MERCHANT_KEY'] : '';

        $PAYTM_MERCHANT_MID = isset($paytm_credential['PAYTM_MERCHANT_MID']) && !empty($paytm_credential['PAYTM_MERCHANT_MID']) ? $paytm_credential['PAYTM_MERCHANT_MID'] : '';

        $API_URL = isset($paytm_credential['API_URL']) && !empty($paytm_credential['API_URL']) ? $paytm_credential['API_URL'] : '';

        $MID = $this->input->post('MID') ? $this->input->post('MID') : null;

        $ORDERID = $this->input->post('ORDERID') ? $this->input->post('ORDERID') : null;

        if ($ORDERID):
            $this->shop_model->updatePaytmTransaction($ORDERID, array('response_data' => serialize($_POST), 'update_time' => date('Y-m-d H:i:s')));
        endif;

        $STATUS = $this->input->post('STATUS') ? $this->input->post('STATUS') : null;
        $RESPMSG = $this->input->post('RESPMSG') ? $this->input->post('RESPMSG') : null;
        if ($_POST['STATUS'] != 'TXN_SUCCESS') {
            $this->session->set_flashdata('error', $_POST['RESPMSG']);
            if ((int) $ORDERID > 0):
                $getorderdetails = $this->site->getSaleByIDEshop($ORDERID);
                $ref_No = $getorderdetails->reference_no;
                $cod_shop_url = 'shop/cod_notify/' . md5('COD' . $ref_No);
                redirect($cod_shop_url);
            else:
                redirect("shop");
            endif;
        }

        try {
            $api = new Paytm($paytm_credential);
            $requestParamList = array("MID" => $PAYTM_MERCHANT_MID, "ORDERID" => $ORDERID);

            $responseParamList = $api->getTxnStatus($requestParamList);

            $_ORDERID = $responseParamList['ORDERID'] ? $responseParamList['ORDERID'] : null;
            $_STATUS = $responseParamList['STATUS'] ? $responseParamList['STATUS'] : null;
            $_RESPMSG = $responseParamList['RESPMSG'] ? $responseParamList['RESPMSG'] : null;
            $_TXNID = $responseParamList['TXNID'] ? $responseParamList['TXNID'] : null;
            if ($_ORDERID == $ORDERID && $_STATUS == 'TXN_SUCCESS'):

                $msg = 'success';
                $sid = $ORDERID;
                $tracking_id = $_TXNID;

                $getorderdetails = $this->site->getSaleByIDEshop($sid);
                $ref_No = $getorderdetails->reference_no;

                $res = $this->shop_model->PaytmAfterSale($responseParamList, $sid);
                if ($res):
                    $this->session->set_flashdata('message', lang('payment_done'));
                    $cod_shop_url = 'shop/cod_notify/' . md5('COD' . $ref_No);
                    redirect($cod_shop_url);
                endif;

                $this->session->set_flashdata('message', $_RESPMSG);
                $cod_shop_url = 'shop/cod_notify/' . md5('COD' . $ref_No);
                redirect($cod_shop_url);
            else:
                $this->session->set_flashdata('error', $_RESPMSG);
                $cod_shop_url = 'shop/cod_notify/' . md5('COD' . $ref_No);
                redirect($cod_shop_url);
            endif;
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect("shop");
        }
    }

    /**
     * End Paytm Payment Gatway
     * @return type
     */

    /**
     * Google Login Method
     */
    public function googlelogin() {
        include_once APPPATH . "third_party/googlelogin/autoload.php";

        $google_client = new Google_Client();

        $google_client->setClientId('970274428101-9ik4fu8dk2god6k1thdrevu0t0k3csgd.apps.googleusercontent.com'); //Define your ClientID

        $google_client->setClientSecret('ayQvZ-sd1usYBnzwgVq6AU-8'); //Define your Client Secret Key

        $google_client->setRedirectUri(base_url() . 'shop/googlelogin'); //Define your Redirect Uri

        $google_client->addScope('email');

        $google_client->addScope('profile');

        if (isset($_GET["code"])) {
            $token = $google_client->fetchAccessTokenWithAuthCode($_GET["code"]);

            if (!isset($token["error"])) {
                $google_client->setAccessToken($token['access_token']);

                $this->session->set_userdata('access_token', $token['access_token']);

                $google_service = new Google_Service_Oauth2($google_client);

                $google_data = $google_service->userinfo->get();


                $current_datetime = date('Y-m-d H:i:s');

                $customer = $this->shop_model->getCustomerByloginId($google_data['email']);


                if (!empty($customer)) {
                    $authData['login_id'] = $google_data['email'];
                    $authData['password'] = 'test@123';

                    $this->authcheckgoogle($authData);
                } else {

                    $data = array('name' => $google_data['given_name'] . ' ' . $google_data['family_name'],
                        'email' => $google_data['email'],
                        'group_id' => '3',
                        'customer_group_id' => '1',
                        'customer_group_name' => 'General',
                        'price_group_id' => '2',
                        'is_synced' => '0',
                        'price_group_name' => 'Standered',
                        'company' => '-',
                        'address' => '-',
                        'country' => 'India',
                        'phone' => 'null',
                        'password' => md5('test@123'),
                        'email_is_verified' => '1',
                    );

                    $this->load->model('companies_model');
                    $this->companies_model->addCompany($data);
                    $authData['login_id'] = $google_data['email'];
                    $authData['password'] = 'test@123';
                    $this->authcheckgoogle($authData);
                }
                if ($this->session->userdata('referred_from') != '' && $this->session->userdata('referred_from') == base_url('shop/cart')) {
                    $this->session->unset_userdata('referred_from');
                    redirect('shop/checkout');
                } else {
                    if (count($_SESSION['cart']) > 0) {
                        redirect('shop/checkout');
                    } else {
                        redirect('shop/home');
                    }
                }
            }
        }
    }

    /**
     * End Google Login method
     */

    /**
     * google Auth Check
     * @param type $authData
     */
    public function authcheckgoogle($authData) {

        $responce = $this->shop_model->getAuthCustomergoogle($authData);


        if (is_array($responce)) {
            if ($responce['status'] == 'SUCCESS') {

                $userdata = $responce['result'][0];
                $sessData['id'] = $userdata['id'];
                $sessData['shop_theme'] = $userdata['id'];
                $sessData['name'] = $userdata['name'];
                $sessData['email'] = $userdata['email'];
                $sessData['phone'] = $userdata['phone'];
                $sessData['auth_token'] = md5(time() . $userdata['phone']);

                $this->shop_model->set_user_session($sessData);
                $this->shop_model->userCartData($userdata['id']);
                if ($this->session->userdata('referred_from') != '' && $this->session->userdata('referred_from') == base_url('shop/cart')) {
                    $this->session->unset_userdata('referred_from');
                    redirect('shop/checkout');
                } else {

                    if (count($_SESSION['cart']) > 0) {
                        redirect('shop/checkout');
                    } else {
                        redirect('shop/home');
                    }
                }
            } else {
                $this->data['login_error'] = $responce['error'];
                return redirect('shop/login');
            }//end else.
        }
    }

    /**
     * Check Pincode
     * @param type $pincode
     */
    public function checkpincode($pincode) {
        
        $outlet = null;
        if($this->eshop_warehouse_id && $this->eshop_settings->active_multi_outlets ){ $outlet = $this->eshop_warehouse_id; }
        
        $result = $this->shop_model->checkPincode($pincode, $outlet);
        if ($result) {
            $response = [
                'status' => 'success',
                'message' => 'Pincode allow',
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'We currently do not deliver at your location',
            ];
        }
        echo json_encode($response);
    }

    /**
     * 
     * @param type $id
     * @return boolean
     */
    public function getSloteTime($id) {

        $getdata = $this->eshop_model->getsloteTiming($id);

        if ($getdata) {
            $htmlpass = '<div class="col-md-6 "><select name="time_slotes" required="required" class="form-control">';
            foreach ($getdata as $values) {
                $htmlpass .= '<option> ' . date('g:i A', strtotime($values->start_time)) . ' - ' . date('g:i A', strtotime($values->end_time)) . '</option>';
            }
            $htmlpass .= '</select></div>';
            
            $htmlpass .='<div class="col-md-6 ">
                            <input  type="date" name="DeliverLater" min="'.date('Y-m-d').'" id="deliverydata" required="required" class="form-control"/>
                        </div>';
            
            echo $htmlpass;
        } else {
            return FALSE;
        }
    }

    /**
     * 
     * Product List
     * Eshop
     */
    public function product_list($category_id = null, $pageno = 1) {
        //$this->data['product_list'] = $this->eshop_model->getCategoryProducts($category_id, $pageno, 20);
        $this->data['category_id'] = $category_id;
        $this->data['pageno'] = $pageno;
        $this->load_shop_view($this->shoptheme . '/product_list', $this->data);
    }

    /**
     * 
     * Product Details
     * Eshop
     */
    public function product_details($product_item) {
        $this->data['product_item_id'] = $product_item;
        $product = explode('_', $product_item);
        $product_id = $product[0];
        $variant_id = $product[1] ? $product[1] : '';
        $Result = $this->eshop_model->getProducts($product_id, $variant_id);
        $stocks = 0;
        if (!empty($Result)) {
            $option_id = $Result[$product_id]['option_id'];
            $option_quantity = $Result[$product_id]['option_quantity'];
            $quantity = $Result[$product_id]['quantity'];
            if ($option_id == null) {
                $stocks = round($option_quantity ? $option_quantity : $quantity);
            } else {
                if ($option_quantity == null)
                    $stocks = 0;
                else
                    $stocks = round($option_quantity ? $option_quantity : $quantity);
            }
        }
        $this->data['stocks'] = $stocks;
        $this->load_shop_view($this->shoptheme . '/product_details', $this->data);
    }

    public function shopping_cart() {
        $this->load_shop_view($this->shoptheme . '/cart', $this->data);
    }

    public function checkout_cart() {
        if ($this->data['visitor'] == 'user') {
            $this->load_shop_view($this->shoptheme . '/checkout', $this->data);
        } else {
            redirect('shop/login');
        }
    }

    public function confirm_payment() {
        if (empty($_POST)) {
            redirect('shop/checkout_cart');
        }
        $this->data['checkoutData'] = $_POST;
        /* echo '<pre>';
          print_r($this->data);
          exit; */
        if ($this->data['visitor'] == 'user') {
            $this->load_shop_view($this->shoptheme . '/payments', $this->data);
        } else {
            redirect('shop/login');
        }
    }

    /**
     *  Search Product
     * @param type $term
     * @param type $pageno
     */
    public function search($term = null, $pageno = 1) {
        $this->data['pageno'] = $pageno;

        if (isset($_GET['q'])) {
            if ($_GET['q'] != '') {
                $term = $_GET['q'];
            } else {
                $term = 'false';
            }
        } else {
            if ($term == null) {
                $term = 'false';
            } else {
                $term = $term;
            }
        }

        $this->data['term'] = $term;
        $this->load_shop_view($this->shoptheme . '/search_product', $this->data);
    }

    public function changeprofile() {

        if ($_FILES['file']['name'][0] != "") {
            $config['upload_path'] = 'assets/mdata/'.$this->Customer_assets.'/uploads/avatars/';
            $config['allowed_types'] = 'jpeg|jpg|png';
            $config['max_size'] = '2000';
            $config['file_name'] = time() . '.png';

            $this->load->library('upload', $config);

            if (!$this->upload->do_upload('file')) {
                $error = array('error' => $this->upload->display_errors());

                $response = [
                    'status' => 'faild',
                    'message' => $error,
                ];
                echo json_encode($response);
            } else {
                $data = $this->upload->data();
                $this->shop_model->uploadphoto($_SESSION['id'], $data['file_name']);

                $response = [
                    'status' => 'success',
                    'message' => 'Image upload successfully',
                    'imageurl' => base_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/avatars/' . $data['file_name'],
                ];

                echo json_encode($response);
            }
        }
    }

    /**
     * Guest Login
     */
    public function guestlogin() {

        if ($_POST) {
            $vmcode = rand(111111, 999999);
            $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'phone' => $this->input->post('phone'),
                'vcode' => $vmcode,
            ];
            $this->session->set_userdata('guestlogininfo', $data);
            $this->otp_sms($this->input->post('phone'), $vmcode);
            return redirect('shop/getotp');
        } else {
            $this->load_shop_view('guest-login', $this->data);
        }
    }

    /**
     * Guest Login Otp Varification
     */
    public function getotp() {
        if ($_POST) {
            $entered_otp = str_replace([' ', '-'], '', $this->input->post('entered_otp'));
            $guestdata = $this->session->userdata('guestlogininfo');
            if ($entered_otp == $guestdata['vcode']) {
                $data = [
                    'name' => $guestdata['name'],
                    'email' => $guestdata['email'],
                    'phone' => $guestdata['phone'],
                    'mobile_verification_code' => $guestdata['vcode'],
                    'mobile_is_verified' => '1',
                ];
                $result = $this->shop_model->guestlogin($data);
                $sessData['id'] = $result->id;
                $sessData['shop_theme'] = $result->id;
                $sessData['name'] = $result->name;
                $sessData['email'] = $result->email;
                $sessData['phone'] = $result->phone;
                $sessData['auth_token'] = md5(time() . $result->phone);
                $this->shop_model->set_user_session($sessData);
                $this->shop_model->userCartData($result->id);
                if ($this->session->userdata('referred_from') != '' && $this->session->userdata('referred_from') == base_url('shop/cart')) {
                    $this->session->unset_userdata('guestlogininfo');
                    $this->session->unset_userdata('referred_from');
                    redirect('shop/checkout');
                } else {
                    if (count($_SESSION['cart']) > 0) {
                        redirect('shop/checkout');
                    } else {
                        redirect('shop/home');
                    }
                }
            } else {
                $this->session->set_flashdata('error', 'Sorry, Please enter valid OTP');
            }
        }
        $this->load_shop_view('guest_otp', $this->data);
    }

    /**
     * Resend OTP Guest Login
     * @return type
     */
    public function resendquestopt() {
        $vmcode = rand(111111, 999999);
        $guestdata = $this->session->userdata('guestlogininfo');
        $this->otp_sms($guestdata['phone'], $vmcode);
        $guestdata['vcode'] = $vmcode;
        $this->session->set_userdata('guestlogininfo', $guestdata);
        return redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * End Guest Login 
     */
    public function set_outlet($outlet_id) {

        $_SESSION['eshop_location_id'] = $outlet_id;

        redirect($_SERVER["HTTP_REFERER"]);
    }

    public function get_shipping_times($method_id = null) {

        $method_id = $method_id ? $method_id : $_GET['method_id'];

        $shipping_times = $this->eshop_model->getsloteTiming($method_id);

        if (is_array($shipping_times)) {
            $timeSlots = '<option value="">--Select Time--</option>';
            foreach ($shipping_times as $objtime) {
                $stime = date('g:i A', strtotime($objtime->start_time));
                $etime = date('g:i A', strtotime($objtime->end_time));
                $timeSlots .= '<option value="' . $stime . ' To ' . $etime . '">' . $stime . ' To ' . $etime . '</option>';
            }
        } else {
            $timeSlots = $this->shop_model->get_times(null, '+02 hours');
        }

        echo $timeSlots;
    }

    public function get_time_slots() {

        echo $timeSlots = $this->shop_model->get_times(null, '+02 hours');
    }

    public function get_pincode_location($pincode) {

        $pincode = $pincode ? $pincode : $_GET['pincode'];

        $locations = $this->shop_model->get_pincode_location($pincode);
        
        $olist = '';
        if ($locations) {
            foreach ($locations as $id => $location) {
                $olist .= '<option value="' . $id . '" >' . $location . '</option>';
            }
        } else {
            $olist = '<option value="'.$this->eshop_warehouse_id.'">Pincode (' . $pincode . ') is not in our delivery list. Please Select Nearest Outlet</option>';
            $locations = $this->shop_model->getEshopOutlets();
          
            if ($locations) {
                foreach ($locations as $id => $location) {
                    $olist .= '<option value="' . $id . '" >' . $location['name'] . '</option>';
                }
            }
        }
        echo $olist;
    }

}
