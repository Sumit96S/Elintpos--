<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Products extends MY_Controller {

    function __construct() {

        parent::__construct();
        if (!$this->loggedIn) {
            $this->session->set_userdata('requested_page', $this->uri->uri_string());
            $this->sma->md('login');
        }
        $this->lang->load('products', $this->Settings->user_language);
        $this->load->library('form_validation');
        $this->load->model('products_model');
        $this->load->model('Transfers_model');
        $this->digital_upload_path = 'files/'.$this->Customer_assets;
        $this->upload_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/';
        $this->thumbs_path = 'assets/mdata/'.$this->Customer_assets.'/uploads/thumbs/';
        $this->image_types = 'gif|jpg|jpeg|png|tif';
        $this->digital_file_types = 'zip|psd|ai|rar|pdf|doc|docx|xls|xlsx|ppt|pptx|gif|jpg|jpeg|png|tif|txt';
        $this->allowed_file_size = '1024';
        $this->popup_attributes = array('width' => '900', 'height' => '600', 'window_name' => 'sma_popup', 'menubar' => 'yes', 'scrollbars' => 'yes', 'status' => 'no', 'resizable' => 'yes', 'screenx' => '0', 'screeny' => '0');
        $this->data['Settings'] = $this->Settings;
    }

    function index($warehouse_id = NULL) {
        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['alert_qty'] = $this->uri->segment(4);
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = ($warehouse_id) ? $warehouse_id : $this->session->userdata('warehouse_id');
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('products')));
        $meta = array('page_title' => lang('products'), 'bc' => $bc);
        $this->page_construct('products/index', $meta, $this->data);
    }

    function getProducts($warehouse_id = NULL) {
        $this->sma->checkPermissions('index', TRUE);



        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }

        $detail_link = anchor('products/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('product_details'));
        $delete_link = "<a href='#' class='tip po' title='<b>" . $this->lang->line("delete_product") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete1' id='a__$1' href='" . site_url('products/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> " . lang('delete_product') . "</a>";
        $single_barcode = anchor('products/print_barcodes/$1', '<i class="fa fa-print"></i> ' . lang('print_barcode_label'));

        $set_fav_link = "<a  id='a__$1' href='" . site_url('products/favourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('add_favourite') . "</a>";
        $unset_fav_link = "<a  id='a__$1' href='" . site_url('products/Refavourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('remove_favourite') . "</a>";

        // $single_label = anchor_popup('products/single_label/$1/' . ($warehouse_id ? $warehouse_id : ''), '<i class="fa fa-print"></i> ' . lang('print_label'), $this->popup_attributes);
        $action = '<div class="text-center"><div class="btn-group text-left">' . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">' . lang('actions') . ' <span class="caret"></span></button>
		<ul class="dropdown-menu pull-right" role="menu">
			<li>' . $detail_link . '</li>
			<li><a href="' . site_url('products/add/$1') . '"><i class="fa fa-plus-square"></i> ' . lang('duplicate_product') . '</a></li>
			<li><a href="' . site_url('products/edit/$1') . '"><i class="fa fa-edit"></i> ' . lang('edit_product') . '</a></li>';
        if ($warehouse_id) {
            $action .= '<li><a href="' . site_url('products/set_rack/$1/' . $warehouse_id) . '" data-toggle="modal" data-target="#myModal"><i class="fa fa-bars"></i> ' . lang('set_rack') . '</a></li>';
        }

        if ($this->Settings->product_batch_setting > 0) {
            $action_add_batches = '<li><a href="' . site_url('products/add_batch?p=$1') . '"  data-toggle="modal" data-target="#myModal"><i class="fa fa-list"></i>' . lang('Manage Batches') . '<img src="' . site_url('themes/default/assets/images/new.gif') . '" height="20px" alt="new"></a></li>';
        }

        $action .= '<li><a href="' . site_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/$2" data-type="image" data-toggle="lightbox"><i class="fa fa-file-photo-o"></i> ' . lang('view_image') . '</a></li>
			<li>' . $single_barcode . '</li>
                        <li class="add_fav_link">' . $set_fav_link . '</li><li  class="remove_fav_link">' . $unset_fav_link . '</li>
                        ' . $action_add_batches . '    
			<li class="divider"></li>
			<li>' . $delete_link . '</li>
			</ul>
		</div></div>';
        $this->load->library('datatables');

        if ($warehouse_id) {
            //{$this->db->dbprefix('products')}.article_code as article_code ,
            $this->datatables->select(" sma_products.id as productid, {$this->db->dbprefix('products')}.image as image, "
                                    . " {$this->db->dbprefix('products')}.code as code,"
                                    . " {$this->db->dbprefix('products')}.article_code as article_code,"
                                    . " {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand,"
                                    . " {$this->db->dbprefix('categories')}.name as cname, latest_cost.lcost as cost, price as price,"
                                    . " COALESCE(SUM(DISTINCT wp.quantity), 0) as quantity, {$this->db->dbprefix('units')}.name as unit, wp.rack as rack, {$this->db->dbprefix('products')}.storage_type,"
                                    . " is_featured", FALSE)
                    ->from('products')
                    ->join('(SELECT product_id, (case when option_id =0 then purchase_unit_cost else 0 end) as lcost
                        FROM ' . $this->db->dbprefix('costing') . ' 
                        WHERE id IN (SELECT MAX(id) FROM ' . $this->db->dbprefix('costing') . ' GROUP BY product_id)
                        ) AS latest_cost', 'products.id = latest_cost.product_id', 'left');

            if ($this->Settings->display_all_products) {
                $this->datatables->join("( SELECT product_id, quantity , rack,warehouse_id  from 
                {$this->db->dbprefix('warehouses_products')}
                WHERE warehouse_id IN( {$warehouse_id}) ) wp",
                'products.id=wp.product_id', 'left');
                if ($this->Owner && $this->Admin) {
                    $this->datatables->where('wp.warehouse_id is not null'); // update by SW on 28-02-2017
                }
            } else {
                $this->datatables->join('warehouses_products wp', 'products.id=wp.product_id', 'left')
                        ->where('wp.warehouse_id IN(' . $warehouse_id . ')')
                        ->where('wp.quantity !=', 0);
            }

            $this->datatables->join('categories', 'products.category_id=categories.id', 'left')
                    ->join('units', 'products.sale_unit=units.id', 'left')
                    ->join('brands', 'products.brand=brands.id', 'left')
                    ->join('sma_costing', 'products.id=sma_costing.product_id', 'left');;
            if ($this->input->get('alert_qty')) { // update by SW on 8-08-2019
                $this->datatables->where('products.quantity <= products.alert_quantity');
            }
            $this->datatables->where('products.pos_combo_product', NULL);
            $this->datatables->group_by("products.id");
        } else {

            //echo $this->input->post('aqty');
            //{$this->db->dbprefix('products')}.article_code as article_code , 
            $this->datatables->select($this->db->dbprefix('products') . ".id as productid, 
            {$this->db->dbprefix('products')}.image as image, 
            {$this->db->dbprefix('products')}.code as code,
            {$this->db->dbprefix('products')}.article_code as article_code, 
            {$this->db->dbprefix('products')}.name as name, 
            {$this->db->dbprefix('brands')}.name as brand, 
            {$this->db->dbprefix('categories')}.name as cname, 
            latest_cost.lcost as cost, 
            price as price, 
            COALESCE(quantity, 0) as quantity, {$this->db->dbprefix('units')}.name as unit, '' as rack, {$this->db->dbprefix('products')}.storage_type, {$this->db->dbprefix('products')}.is_featured", FALSE)
                    ->from('products')
                    ->join('categories', 'products.category_id=categories.id', 'left')
                    ->join('units', 'products.sale_unit=units.id', 'left')
                    ->join('brands', 'products.brand=brands.id', 'left')
                    ->join('(SELECT product_id, (case when option_id =0 then purchase_unit_cost else 0 end) as lcost
                        FROM ' . $this->db->dbprefix('costing') . ' 
                        WHERE id IN (SELECT MAX(id) FROM ' . $this->db->dbprefix('costing') . ' GROUP BY product_id)
                        ) AS latest_cost', 'products.id = latest_cost.product_id', 'left');
            if ($this->input->get('alert_qty')) { // update by SW on 8-08-2019
                $this->datatables->where('products.quantity <= products.alert_quantity');
            }
            $this->datatables->where('products.pos_combo_product', NULL);
            $this->datatables->group_by("products.id");
        }

        if (!$this->Owner && !$this->Admin) {
            if (!$this->session->userdata('show_cost')) {
                $this->datatables->unset_column("cost");
            }
            if (!$this->session->userdata('show_price')) {
                $this->datatables->unset_column("price");
            }
        }

        $this->datatables->add_column("Actions", $action, "productid, image, code, name");

        echo $this->datatables->generate();
    }

    function poscombo($warehouse_id = NULL) {
        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $this->data['alert_qty'] = $this->uri->segment(4);
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        } else {
            $this->data['warehouses'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id')) : NULL;
            $this->data['warehouse_id'] = ($warehouse_id) ? $warehouse_id : $this->session->userdata('warehouse_id');
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : $this->site->getWarehouseByIDs($this->session->userdata('warehouse_id'));
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('products')));
        $meta = array('page_title' => lang('products'), 'bc' => $bc);
        $this->page_construct('products/poscombo', $meta, $this->data);
    }

    function getProductsposcombo($warehouse_id = NULL) {
        $this->sma->checkPermissions('index', TRUE);



        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }

        $detail_link = anchor('products/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('product_details'));
        $delete_link = "<a href='#' class='tip po' title='<b>" . $this->lang->line("delete_product") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete1' id='a__$1' href='" . site_url('products/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> " . lang('delete_product') . "</a>";
        $single_barcode = anchor('products/print_barcodes/$1', '<i class="fa fa-print"></i> ' . lang('print_barcode_label'));

        $set_fav_link = "<a  id='a__$1' href='" . site_url('products/favourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('add_favourite') . "</a>";
        $unset_fav_link = "<a  id='a__$1' href='" . site_url('products/Refavourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('remove_favourite') . "</a>";

        // $single_label = anchor_popup('products/single_label/$1/' . ($warehouse_id ? $warehouse_id : ''), '<i class="fa fa-print"></i> ' . lang('print_label'), $this->popup_attributes);
        $action = '<div class="text-center"><div class="btn-group text-left">' . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">' . lang('actions') . ' <span class="caret"></span></button>
		<ul class="dropdown-menu pull-right" role="menu">
			<li>' . $detail_link . '</li>
			<li><a href="' . site_url('products/add/$1') . '"><i class="fa fa-plus-square"></i> ' . lang('duplicate_product') . '</a></li>
			<li><a href="' . site_url('products/edit/$1') . '"><i class="fa fa-edit"></i> ' . lang('edit_product') . '</a></li>';
        if ($warehouse_id) {
            $action .= '<li><a href="' . site_url('products/set_rack/$1/' . $warehouse_id) . '" data-toggle="modal" data-target="#myModal"><i class="fa fa-bars"></i> ' . lang('set_rack') . '</a></li>';
        }

        if ($this->Settings->product_batch_setting > 0) {
            $action_add_batches = '<li><a href="' . site_url('products/add_batch?p=$1') . '"  data-toggle="modal" data-target="#myModal"><i class="fa fa-list"></i>' . lang('Manage Batches') . '<img src="' . site_url('themes/default/assets/images/new.gif') . '" height="20px" alt="new"></a></li>';
        }

        $action .= '<li><a href="' . site_url() . 'assets/mdata/'.$this->Customer_assets.'/uploads/$2" data-type="image" data-toggle="lightbox"><i class="fa fa-file-photo-o"></i> ' . lang('view_image') . '</a></li>
			<li>' . $single_barcode . '</li>
                        <li class="add_fav_link">' . $set_fav_link . '</li><li  class="remove_fav_link">' . $unset_fav_link . '</li>
                        ' . $action_add_batches . '    
			<li class="divider"></li>
			<li>' . $delete_link . '</li>
			</ul>
		</div></div>';
        $this->load->library('datatables');

        if ($warehouse_id) {
            //{$this->db->dbprefix('products')}.article_code as article_code ,
            $this->datatables->select(" sma_products.id as productid, {$this->db->dbprefix('products')}.image as image, "
                            . " {$this->db->dbprefix('products')}.code as code,"
                            . " {$this->db->dbprefix('products')}.article_code as article_code,"
                            . " {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand,"
                            . " {$this->db->dbprefix('categories')}.name as cname, cost as cost, price as price,"
                            . " COALESCE(sum(wp.quantity), 0) as quantity, {$this->db->dbprefix('units')}.name as unit, wp.rack as rack, {$this->db->dbprefix('products')}.storage_type,"
                            . " is_featured", FALSE)
                    ->from('products');

            if ($this->Settings->display_all_products) {
                $this->datatables->join("( SELECT product_id, quantity, rack,warehouse_id  from {$this->db->dbprefix('warehouses_products')} WHERE warehouse_id IN( {$warehouse_id}) ) wp", 'products.id=wp.product_id', 'left');
                $this->datatables->where('wp.warehouse_id is  not  null'); // update by SW on 28-02-2017
            } else {
                $this->datatables->join('warehouses_products wp', 'products.id=wp.product_id', 'left')
                        ->where('wp.warehouse_id IN(' . $warehouse_id . ')')
                        ->where('wp.quantity !=', 0);
            }

            $this->datatables->join('categories', 'products.category_id=categories.id', 'left')
                    ->join('units', 'products.sale_unit=units.id', 'left')
                    ->join('brands', 'products.brand=brands.id', 'left');
            if ($this->input->get('alert_qty')) { // update by SW on 8-08-2019
                $this->datatables->where('products.quantity <= products.alert_quantity');
            }
            $this->datatables->where('products.pos_combo_product = 1');
            $this->datatables->group_by("products.id");
        } else {

            //echo $this->input->post('aqty');
            //{$this->db->dbprefix('products')}.article_code as article_code , 
            $this->datatables->select($this->db->dbprefix('products') . ".id as productid, {$this->db->dbprefix('products')}.image as image, {$this->db->dbprefix('products')}.code as code,{$this->db->dbprefix('products')}.article_code as article_code, {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand, {$this->db->dbprefix('categories')}.name as cname, cost as cost, price as price, COALESCE(quantity, 0) as quantity, {$this->db->dbprefix('units')}.name as unit, '' as rack, {$this->db->dbprefix('products')}.storage_type, {$this->db->dbprefix('products')}.is_featured", FALSE)
                    ->from('products')
                    ->join('categories', 'products.category_id=categories.id', 'left')
                    ->join('units', 'products.sale_unit=units.id', 'left')
                    ->join('brands', 'products.brand=brands.id', 'left');
            if ($this->input->get('alert_qty')) { // update by SW on 8-08-2019
                $this->datatables->where('products.quantity <= products.alert_quantity');
            }
            $this->datatables->where('products.pos_combo_product = 1');
            $this->datatables->group_by("products.id");
        }

        if (!$this->Owner && !$this->Admin) {
            if (!$this->session->userdata('show_cost')) {
                $this->datatables->unset_column("cost");
            }
            if (!$this->session->userdata('show_price')) {
                $this->datatables->unset_column("price");
            }
        }

        $this->datatables->add_column("Actions", $action, "productid, image, code, name");

        echo $this->datatables->generate();
    }

    function set_rack($product_id = NULL, $warehouse_id = NULL) {
        $this->sma->checkPermissions('edit', TRUE);

        $this->form_validation->set_rules('rack', lang("rack_location"), 'trim|required');

        if ($this->form_validation->run() == TRUE) {
            $data = array('rack' => $this->input->post('rack'), 'product_id' => $product_id, 'warehouse_id' => $warehouse_id,);
        } elseif ($this->input->post('set_rack')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("products");
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->setRack($data)) {
            $this->session->set_flashdata('message', lang("rack_set"));
            redirect("products/" . $warehouse_id);
        } else {
            $this->data['error'] = validation_errors() ? validation_errors() : $this->session->flashdata('error');
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['product'] = $this->site->getProductByID($product_id);
            $wh_pr = $this->products_model->getProductQuantity($product_id, $warehouse_id);
            $this->data['rack'] = $wh_pr['rack'];
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'products/set_rack', $this->data);
        }
    }

    function product_barcode($product_code = NULL, $bcs = 'code128', $height = 60) {
        // if ($this->Settings->barcode_img) {
        return "<img src='" . site_url('products/gen_barcode/' . $product_code . '/' . $bcs . '/' . $height) . "' alt='{$product_code}' class='bcimg' />";
        // } else {
        //     return $this->gen_barcode($product_code, $bcs, $height);
        // }
    }

    function barcode($product_code = NULL, $bcs = 'code128', $height = 60) {
        return site_url('products/gen_barcode/' . $product_code . '/' . $bcs . '/' . $height);
    }

    function gen_barcode($product_code = NULL, $bcs = 'code128', $height = 60, $text = 1) {
        $drawText = ($text != 1) ? FALSE : TRUE;
        $this->load->library('zend');
        $this->zend->load('Zend/Barcode');
        $barcodeOptions = array('text' => $product_code, 'barHeight' => $height, 'drawText' => $drawText, 'factor' => 1.0);
        if ($this->Settings->barcode_img) {
            $rendererOptions = array('imageType' => 'jpg', 'horizontalPosition' => 'center', 'verticalPosition' => 'middle');
            $imageResource = Zend_Barcode::render($bcs, 'image', $barcodeOptions, $rendererOptions);
            return $imageResource;
        } else {
            $rendererOptions = array('renderer' => 'svg', 'horizontalPosition' => 'center', 'verticalPosition' => 'middle');
            $imageResource = Zend_Barcode::render($bcs, 'svg', $barcodeOptions, $rendererOptions);
            header("Content-Type: image/svg+xml");
            echo $imageResource;
        }
    }

    function print_barcodes($product_id = NULL) {
        $this->sma->checkPermissions('barcode', TRUE);
        $this->data['manage_barcode'] = $this->products_model->getManagebarcode();
        $this->form_validation->set_rules('style', lang("style"), 'required');

        if ($this->form_validation->run() == TRUE) {

            $style = $this->input->post('style');
            $bci_size = ($style == 10 || $style == 12 ? 50 : ($style == 14 || $style == 18 ? 30 : 20));
            $currencies = $this->site->getAllCurrencies();
            $s = isset($_POST['product']) ? sizeof($_POST['product']) : 0;
            if ($s < 1) {
                $this->session->set_flashdata('error', lang('no_product_selected'));
                redirect("products/print_barcodes");
            }
            for ($m = 0; $m < $s; $m++) {
                $pid = $_POST['product'][$m];
                $expdata = ($_POST['expdate'][$m]) ? $_POST['expdate'][$m] : FALSE;
                $batchno = ($_POST['batchno'][$m]) ? $_POST['batchno'][$m] : FALSE;
                $exppro = explode("_", $pid);

                $quantity = $_POST['quantity'][$m];
                $product = $this->products_model->getProductWithCategory($exppro[0]);
                $unitname = $this->db->select('code')->where('id', $product->unit)->get('sma_units')->row()->code;

                $product->price = $this->input->post('check_promo') ? ($product->promotion ? $product->promo_price : $product->price) : $product->price;
                if ($variants = $this->products_model->getProductOptions($pid)) {
                    foreach ($variants as $option) {
                        if ($this->input->post('vt_' . $pid . '_' . $option->id)) {
                            $barcodes[] = [
                                'barcode_img' => $this->input->post('barcode_img') ? $this->input->post('barcode_img') : FALSE,
                                'site' => $this->input->post('site_name') ? $this->Settings->site_name : FALSE,
                                'name' => $this->input->post('product_name') ? $product->name . ' - ' . $option->name : FALSE,
                                'image' => $this->input->post('product_image') ? $product->image : FALSE,
                                //'barcode' => $this->product_barcode($product->code . $this->Settings->barcode_separator . $option->id . ($this->input->post('pro_quantity') ? '-' . $this->input->post('pro_quantity') : ''), 'code128', $bci_size), 
                                'barcode' => ($this->Settings->barcode_type == 'sillagefragrances' ? $this->product_barcode($product->code . $this->Settings->barcode_separator . $option->id, 'code128', $bci_size) : $this->product_barcode($product->code . $this->Settings->barcode_separator . $option->id . ($this->input->post('pro_quantity') ? '-' . $this->input->post('pro_quantity') : ''), 'code128', $bci_size)),
                                'price' => $this->input->post('price') ? $this->sma->formatMoney($option->price != 0 ? $product->price + $option->price : $product->price) : FALSE,
                                'mrp' => $this->input->post('mrp') ? $this->sma->formatMoney($option->mrp) : FALSE,
                                'unit' => $this->input->post('unit') ? $unitname : FALSE,
                                'category' => $this->input->post('category') ? $product->category : FALSE,
                                'currencies' => $this->input->post('currencies'),
                                'variants' => $this->input->post('variants') ? $variants : FALSE,
                                'expdate' => $expdata,
                                'batchno' => $batchno,
                                'quantity' => $quantity,
                                'brand' => $this->input->post('Brand') ? $product->brannd_name : FALSE,
                                'Address' => ($this->input->post('address')) ? TRUE : FALSE,
                                'Date' => ($this->input->post('date')) ? $this->input->post('date') : FALSE,
                                'netqty' => ($this->input->post('pro_quantity') ? $this->input->post('pro_quantity') : FALSE),
                                'weight' => ($this->input->post('pro_weight')) ? $this->input->post('pro_weight') : FALSE,
                                'allexpdate' => (($expdata) ? $expdata : ($this->input->post('txtexpdate') ? $this->input->post('txtexpdate') : FALSE)),
                                'allbatchno' => (($batchno) ? $batchno : ($this->input->post('txtbatchno') ? $this->input->post('txtbatchno') : FALSE)),
                                'pro_quantity' => (($this->input->post('pro_quantity') ? $this->input->post('pro_quantity') : FALSE)),
                            ];
                        }
                    }
                } else {

                    $barcodes[] = [
                        'barcode_img' => $this->input->post('barcode_img') ? $this->input->post('barcode_img') : FALSE,
                        'site' => $this->input->post('site_name') ? $this->Settings->site_name : FALSE,
                        'name' => $this->input->post('product_name') ? $product->name : FALSE,
                        'image' => $this->input->post('product_image') ? $product->image : FALSE,
//                      'barcode' => $this->product_barcode($product->code . ($this->input->post('pro_quantity') ? '-' . $this->input->post('pro_quantity') : ''), $product->barcode_symbology, $bci_size), 
                        'barcode' => $this->Settings->barcode_type == 'sillagefragrances' ? $this->product_barcode($product->code, $product->barcode_symbology, $bci_size) : $this->product_barcode($product->code . ($this->input->post('pro_quantity') ? '-' . $this->input->post('pro_quantity') : ''), $product->barcode_symbology, $bci_size),
                        'price' => $this->input->post('price') ? $this->sma->formatMoney($product->price) : FALSE,
                        'mrp' => $this->input->post('mrp') ? $this->sma->formatMoney($product->mrp) : FALSE,
                        'unit' => $this->input->post('unit') ? $unitname : FALSE,
                        'category' => $this->input->post('category') ? $product->category : FALSE,
                        'currencies' => $this->input->post('currencies'),
                        'variants' => FALSE,
                        'expdate' => $expdata,
                        'batchno' => $batchno,
                        'quantity' => $quantity,
                        'brand' => $this->input->post('Brand') ? $product->brannd_name : FALSE,
                        'Address' => ($this->input->post('address')) ? TRUE : FALSE,
                        'Date' => ($this->input->post('date')) ? $this->input->post('date') : FALSE,
                        'netqty' => ($this->input->post('pro_quantity') ? $this->input->post('pro_quantity') : FALSE),
                        'weight' => ($this->input->post('pro_weight')) ? $this->input->post('pro_weight') : FALSE,
                        'allexpdate' => (($expdata) ? $expdata : ($this->input->post('txtexpdate') ? $this->input->post('txtexpdate') : FALSE)),
                        'allbatchno' => (($batchno) ? $batchno : ($this->input->post('txtbatchno') ? $this->input->post('txtbatchno') : FALSE)),
                        'pro_quantity' => (($this->input->post('pro_quantity') ? $this->input->post('pro_quantity') : FALSE)),
                    ];
                }
            }
            $this->data['barcodes'] = $barcodes;
            $this->data['currencies'] = $currencies;
            $this->data['biller'] = $this->products_model->getBillerDetails();
            $this->data['style'] = $style;
            $this->data['items'] = FALSE;
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('print_barcodes')));
            $meta = array('page_title' => lang('print_barcodes'), 'bc' => $bc);
            $this->page_construct('products/print_barcodes', $meta, $this->data);
        } else {

            if ($this->input->get('purchase') || $this->input->get('transfer')) {
                if ($this->input->get('purchase')) {
                    $purchase_id = $this->input->get('purchase', TRUE);
                    $items = $this->products_model->getPurchaseItems($purchase_id);
                } elseif ($this->input->get('transfer')) {
                    $transfer_id = $this->input->get('transfer', TRUE);
                    $items = $this->products_model->getTransferItems($transfer_id);
                }
                if ($items) {
                    foreach ($items as $item) {
                        if ($row = $this->products_model->getProductByID($item->product_id)) {
                            $selected_variants = FALSE;
                            if ($variants = $this->products_model->getProductOptions($row->id)) {
                                foreach ($variants as $variant) {
                                    $selected_variants[$variant->id] = isset($pr[$row->id]['selected_variants'][$variant->id]) && !empty($pr[$row->id]['selected_variants'][$variant->id]) ? 1 : ($variant->id == $item->option_id ? 1 : 0);
                                }
                            }
                            $datawhr['purchase_id'] = $purchase_id;
                            $datawhr['product_id'] = $item->product_id;
                            $Qty = 0;
                            if ($this->input->get('purchase')) {
                                if ($row_item = $this->products_model->getBarcodeItemQtySum('purchase_items', $datawhr)) {
                                    foreach ($row_item as $item1) {
                                        $Qty = $Qty + $item1->quantity;
                                        //echo $Qty.'<br/>';
                                    }
                                }
                            } else {
                                $Qty = $item->quantity;
                            }
                            $pr[$row->id] = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => $Qty, 'variants' => $variants, 'selected_variants' => $selected_variants);
                        }
                    }
                    $this->data['message'] = lang('products_added_to_list');
                }
            }

            if ($product_id) {
                if ($row = $this->site->getProductByID($product_id)) {

                    $selected_variants = FALSE;
                    if ($variants = $this->products_model->getProductOptions($row->id)) {
                        foreach ($variants as $variant) {
                            $selected_variants[$variant->id] = $variant->quantity > 0 ? 1 : 0;
                        }
                    }
                    $pr[$row->id] = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => $row->quantity, 'variants' => $variants, 'selected_variants' => $selected_variants);

                    $this->data['message'] = lang('product_added_to_list');
                }
            }

            if ($this->input->get('category')) {
                if ($products = $this->products_model->getCategoryProducts($this->input->get('category'))) {
                    foreach ($products as $row) {
                        $selected_variants = FALSE;
                        if ($variants = $this->products_model->getProductOptions($row->id)) {
                            foreach ($variants as $variant) {
                                $selected_variants[$variant->id] = $variant->quantity > 0 ? 1 : 0;
                            }
                        }
                        $pr[$row->id] = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => $row->quantity, 'variants' => $variants, 'selected_variants' => $selected_variants);
                    }
                    $this->data['message'] = lang('products_added_to_list');
                } else {
                    $pr = array();
                    $this->session->set_flashdata('error', lang('no_product_found'));
                }
            }

            if ($this->input->get('subcategory')) {
                if ($products = $this->products_model->getSubCategoryProducts($this->input->get('subcategory'))) {
                    foreach ($products as $row) {
                        $selected_variants = FALSE;
                        if ($variants = $this->products_model->getProductOptions($row->id)) {
                            foreach ($variants as $variant) {
                                $selected_variants[$variant->id] = $variant->quantity > 0 ? 1 : 0;
                            }
                        }
                        $pr[$row->id] = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => $row->quantity, 'variants' => $variants, 'selected_variants' => $selected_variants);
                    }
                    $this->data['message'] = lang('products_added_to_list');
                } else {
                    $pr = array();
                    $this->session->set_flashdata('error', lang('no_product_found'));
                }
            }

            $this->data['items'] = isset($pr) ? json_encode($pr) : FALSE;
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('print_barcodes')));
            $meta = array('page_title' => lang('print_barcodes'), 'bc' => $bc);
            $this->page_construct('products/print_barcodes', $meta, $this->data);
        }
    }

    function add($id = NULL) {

        $this->sma->checkPermissions();
        $this->load->helper('security');
        $Settings = $this->Settings;
        $url = base_url(); //'http://en.example.com';
        $ProductCustomField = $this->products_model->get_custom_product_field('url', $url);
        /*
          check url is available or not,
          if url are available then find subdomain from url and consider subdomain as a key, also find custom value from base_url(compare with url from table).
          if url are not available then consider pos_type of setting as a key, also find custom value from pos_type(compare with merchant_type from table).
          if url and merchant_type are not available then show default value
         */
        if (!empty($ProductCustomField)) {
            $parsedUrl = parse_url($url);
            $host = explode('.', $parsedUrl['host']);
            $subdomain = $host[0];
            //echo $subdomain; exit;
            $this->data['ProductCustomField'] = $ProductCustomField;
            $this->data['ProductCustomKey'] = $subdomain;
        } else {
            $this->data['ProductCustomField'] = $this->products_model->get_custom_product_field('merchant_type', $Settings->pos_type);
            if (!empty($this->data['ProductCustomField'])) {
                $this->data['ProductCustomKey'] = $Settings->pos_type;
            } else {
                $this->data['ProductCustomKey'] = 'NoProductCustomKey';
            }
        }
        $warehouses = $this->site->getAllWarehouses();
        
        // $this->form_validation->set_rules('price', lang("product_price"), 'numeric');
        $this->form_validation->set_rules('mrp', lang("product_mrp"), 'numeric');

		// $this->form_validation->set_rules('shelf_life', lang("shelf_life"), 'required|numeric');
        $this->form_validation->set_rules('cost', lang("product_cost"), 'required|numeric');
        $this->form_validation->set_rules('category', lang("category"), 'required');
        if ($this->input->post('type') == 'standard') {
            $this->form_validation->set_rules('unit', lang("product_unit"), 'required');
        }
        if ($this->input->post('barcode_symbology') == 'ean13') {
            $this->form_validation->set_rules('code', lang("product_code"), 'min_length[13]|max_length[13]');
        }
        $this->form_validation->set_rules('code', lang("product_code"), 'is_unique[products.code]|alpha_dash');
        $this->form_validation->set_rules('product_image', lang("product_image"), 'xss_clean');
        $this->form_validation->set_rules('digital_file', lang("digital_file"), 'xss_clean');
        $this->form_validation->set_rules('userfile', lang("product_gallery_images"), 'xss_clean');
        if ($this->form_validation->run() == TRUE) {
            $promotion = $this->input->post('promotion');
            if ($promotion):
                $promo_price = $this->input->post('promo_price');
                if ($promo_price == 0 || empty($promo_price)):
                    $this->session->set_flashdata('error', 'Please Enter ' . lang("promo_price"));
                    redirect("products/add");
                endif;

                $start_date = $this->input->post('start_date');
                $end_date = $this->input->post('end_date');
                if (!$this->sma->validPromoDate($start_date, $end_date)):
                    $this->session->set_flashdata('error', 'Invalid Promo date');
                    redirect("products/add");
                endif;
            endif;
            $tax_rate = $this->input->post('tax_rate') ? $this->site->getTaxRateByID($this->input->post('tax_rate')) : 0;

            $taxRate = ($tax_rate) ? $tax_rate->rate : 0;
            $price = $this->input->post('price');
            $mrp = $this->input->post('mrp');
            $cost = $this->input->post('cost');
            $eshop_price = $price;

            if ((bool) $taxRate && $price > 0 && $this->input->post('tax_method') == 1) {
                //Exclusive tax calculation 
                $taxAmt = ($price * $taxRate) / 100;
                $eshop_price += $taxAmt;
            }

            $data = array(
                'code' => $this->input->post('code'),
                'article_code' => $this->input->post('article_code'),
                'barcode_symbology' => $this->input->post('barcode_symbology'),
                'weight' => $this->input->post('weight'),
                'storage_type' => $this->input->post('storage_type'),
                'name' => $this->input->post('name'),
                'divisionid' => $this->input->post('division'),
                'hsn_code' => $this->input->post('hsn_code'),
                'type' => $this->input->post('type'),
                'brand' => $this->input->post('brand'),
                'category_id' => $this->input->post('category'),
                'subcategory_id' => $this->input->post('subcategory') ? $this->input->post('subcategory') : NULL,
                'cost' => $this->sma->formatDecimal($cost),
                'price' => $this->sma->formatDecimal($price),
                'mrp' => $this->sma->formatDecimal($mrp),
                'unit' => $this->input->post('unit'),
                'sale_unit' => $this->input->post('default_sale_unit'),
                'purchase_unit' => $this->input->post('default_purchase_unit'),
                'tax_rate' => $this->input->post('tax_rate'),
                'tax_method' => $this->input->post('tax_method'),
                'alert_quantity' => $this->input->post('alert_quantity'),
                'track_quantity' => $this->input->post('track_quantity') ? $this->input->post('track_quantity') : '0',
                'details' => $this->input->post('details'),
                'product_details' => $this->input->post('product_details'),
                'supplier1' => $this->input->post('supplier'),
                'supplier1price' => $this->sma->formatDecimal($this->input->post('supplier_price')),
                'supplier2' => $this->input->post('supplier_2'),
                'supplier2price' => $this->sma->formatDecimal($this->input->post('supplier_2_price')),
                'supplier3' => $this->input->post('supplier_3'),
                'supplier3price' => $this->sma->formatDecimal($this->input->post('supplier_3_price')),
                'supplier4' => $this->input->post('supplier_4'),
                'supplier4price' => $this->sma->formatDecimal($this->input->post('supplier_4_price')),
                'supplier5' => $this->input->post('supplier_5'),
                'supplier5price' => $this->sma->formatDecimal($this->input->post('supplier_5_price')),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'promotion' => $this->input->post('promotion'),
                'promo_price' => $this->sma->formatDecimal($this->input->post('promo_price')),
                'start_date' => $this->input->post('start_date') ? $this->sma->fld($this->input->post('start_date')) : NULL,
                'end_date' => $this->input->post('end_date') ? $this->sma->fld($this->input->post('end_date')) : NULL,
                'supplier1_part_no' => $this->input->post('supplier_part_no'),
                'supplier2_part_no' => $this->input->post('supplier_2_part_no'),
                'supplier3_part_no' => $this->input->post('supplier_3_part_no'),
                'supplier4_part_no' => $this->input->post('supplier_4_part_no'),
                'supplier5_part_no' => $this->input->post('supplier_5_part_no'),
                'repeat_sale_discount_rate' => $this->input->post('repeat_sale_discount_rate'),
                'repeat_sale_validity' => $this->input->post('repeat_sale_validity'),
                'eshop_name' => $this->input->post('name'),
                'eshop_price' => $this->sma->formatDecimal(round($eshop_price)),
				'shelf_life' => $this->input->post('shelf_life'),
                'storage_conditions' => $this->input->post('storage_conditions'),
                'season_id' => $this->input->post('season'),
                'rank' => $this->input->post('rank'),
                'flag_visible' => $this->input->post('flag_visible'),
                'discount_on_mrp' => $this->input->post('discount_on_mrp'),
            );

            if ($this->input->post('pos_type') == 'restaurant' || $this->input->post('pos_type') == 'bakery') {

                $data['up_items'] = ($this->input->post('up_items')) ? $this->input->post('up_items') : NULL;
                $data['food_type_id'] = ($this->input->post('up_food_type')) ? $this->input->post('up_food_type') : '1';

                $updata = array();

                if ($this->input->post('up_items') == '1') {
                    $postype_data = array(
                        'product_code' => $this->input->post('code'),
                        'pos_type' => $this->input->post('pos_type'),
                        'product_code' => $this->input->post('code'),
                        'price' => $this->input->post('upprice'),
                        'food_type_id' => ($this->input->post('up_food_type')) ? $this->input->post('up_food_type') : '1',
                        'available' => $this->input->post('available'),
                        'sold_at_store' => $this->input->post('sold_at_store'),
                        'recommended' => $this->input->post('recommended'),
                        'plat_zomato' => str_replace(' ', '', $this->input->post('tag_zomato')),
                        'plat_swiggy' => str_replace(' ', '', $this->input->post('tag_swiggy')),
                        'plat_foodpanda' => str_replace(' ', '', $this->input->post('tag_foodpanda')),
                        'plat_ubereats' => str_replace(' ', '', $this->input->post('tag_ubereats')),
                        'default_tag' => str_replace(' ', '', $this->input->post('default_tag')),
                        'manage_stock' => $this->input->post('manage_stock'),
                    );
                } //end if.
            }//end if


            $this->load->library('logs');
            $this->logs->write('products', json_encode($data), $val);
            $this->load->library('upload');
            if ($this->input->post('type') == 'standard') {
                $wh_total_quantity = 0;
                $pv_total_quantity = 0;
                for ($s = 2; $s > 5; $s++) {
                    $data['suppliers' . $s] = $this->input->post('supplier_' . $s);
                    $data['suppliers' . $s . 'price'] = $this->input->post('supplier_' . $s . '_price');
                }
                /* foreach ($warehouses as $warehouse) {
                  if ($this->input->post('wh_qty_' . $warehouse->id)) {
                  $warehouse_qty[] = array('warehouse_id' => $this->input->post('wh_' . $warehouse->id), 'quantity' => $this->input->post('wh_qty_' . $warehouse->id), 'rack' => $this->input->post('rack_' . $warehouse->id) ? $this->input->post('rack_' . $warehouse->id) : NULL);
                  $wh_total_quantity += $this->input->post('wh_qty_' . $warehouse->id);
                  }
                  } */

                if ($this->input->post('attributes')) {
                    $a = sizeof($_POST['attr_name']);
                    for ($r = 0; $r <= $a; $r++) {
                        if (isset($_POST['attr_name'][$r])) {

                            $attr_price = $_POST['attr_price'][$r];
                            $attr_eshop_price = $price + $attr_price;

                            if ((bool) $taxRate && $attr_eshop_price > 0 && $this->input->post('tax_method') == 1) {
                                //Exclusive tax calculation 
                                $taxAmt = ($attr_eshop_price * $taxRate) / 100;
                                $attr_eshop_price += $taxAmt;
                            }

                            $attr_eshop_mrp = $attr_eshop_price > $mrp ? $attr_eshop_price : $mrp;

                            $product_attributes[] = array(
                                'name' => $_POST['attr_name'][$r],
                                'warehouse_id' => $_POST['attr_warehouse'][$r],
                                'quantity' => $_POST['attr_quantity'][$r],
                                'price' => $_POST['attr_price'][$r],
                                'up_price' => (isset($_POST['attr_upprice'][$r]) ? $_POST['attr_upprice'][$r] : NULL),
                                'unit_quantity' => $_POST['attr_unit_quantity'][$r],
                                'unit_weight' => $_POST['attr_unit_weight'][$r],
                                'cost' => $_POST['attr_cost'][$r],
                                'eshop_name' => $_POST['attr_name'][$r],
                                'eshop_price' => round($attr_eshop_price),
                                'eshop_mrp' => round($attr_eshop_mrp),
                                'mrp' => $_POST['attr_mrp'][$r],
                                'variant_discount_on_mrp' => $_POST['attr_discount'][$r],
                            );

                            $pv_total_quantity += $_POST['attr_quantity'][$r];
                        }
                    }
                } else {
                    $product_attributes = NULL;
                }


                /* if ($wh_total_quantity != $pv_total_quantity && $pv_total_quantity != 0) {
                  $this->form_validation->set_rules('wh_pr_qty_issue', 'wh_pr_qty_issue', 'required');
                  $this->form_validation->set_message('required', lang('wh_pr_qty_issue'));
                  } */
            } else {
                $warehouse_qty = NULL;
                $product_attributes = NULL;
            }

            if ($this->input->post('type') == 'service') {
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'combo') {
                $total_price = 0;
                $c = sizeof($_POST['combo_item_code']) - 1;
                for ($r = 0; $r <= $c; $r++) {
                    if (isset($_POST['combo_item_code'][$r]) && isset($_POST['combo_item_quantity'][$r]) && isset($_POST['combo_item_price'][$r])) {
                        $items[] = array('item_code' => $_POST['combo_item_code'][$r], 'quantity' => $_POST['combo_item_quantity'][$r], 'unit_price' => $_POST['combo_item_price'][$r],);
                    }
                    $total_price += $_POST['combo_item_price'][$r] * $_POST['combo_item_quantity'][$r];
                }
                if ($this->sma->formatDecimal($total_price) != $this->sma->formatDecimal($this->input->post('price'))) {
                    //$this->form_validation->set_rules('combo_price', 'combo_price', 'required');
                    //$this->form_validation->set_message('required', lang('pprice_not_match_ciprice'));
                }
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'Bundle') {
                $total_price = 0;
                $c = sizeof($_POST['combo_item_code']) - 1;
                for ($r = 0; $r <= $c; $r++) {
                    if (isset($_POST['combo_item_code'][$r]) && isset($_POST['combo_item_quantity'][$r]) ) {
                        $items[] = array('item_code' => $_POST['combo_item_code'][$r], 'quantity' => $_POST['combo_item_quantity'][$r],);
                    }
                    $total_price += $_POST['combo_item_price'][$r] * $_POST['combo_item_quantity'][$r];
                }
                if ($this->sma->formatDecimal($total_price) != $this->sma->formatDecimal($this->input->post('price'))) {
                    //$this->form_validation->set_rules('combo_price', 'combo_price', 'required');
                    //$this->form_validation->set_message('required', lang('pprice_not_match_ciprice'));
                }
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'digital') {
                if ($_FILES['digital_file']['size'] > 0) {
                    $config['upload_path'] = $this->digital_upload_path;
                    $config['allowed_types'] = $this->digital_file_types;
                    $config['max_size'] = $this->allowed_file_size;
                    $config['overwrite'] = FALSE;
                    $config['encrypt_name'] = TRUE;
                    $config['max_filename'] = 25;
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('digital_file')) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        redirect("products/add");
                    }
                    $file = $this->upload->file_name;
                    $data['file'] = $file;
                } else {
                    $this->form_validation->set_rules('digital_file', lang("digital_file"), 'required');
                }
                $config = NULL;
                $data['track_quantity'] = 0;
            }
            if (!isset($items)) {
                $items = NULL;
            }
            if ($_FILES['product_image']['size'] > 0) {

                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['max_filename'] = 25;
                $config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('product_image')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("products/add");
                }
                $photo = $this->upload->file_name;
                $data['image'] = $photo;
                /* Main Image */
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->upload_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = 500;
                $config['height'] = 500;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                /* Main Image */
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = $this->Settings->twidth;
                $config['height'] = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                if ($this->Settings->watermark) {
                    $this->image_lib->clear();
                    $wm['source_image'] = $this->upload_path . $photo;
                    $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                    $wm['wm_type'] = 'text';
                    $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                    $wm['quality'] = '100';
                    $wm['wm_font_size'] = '16';
                    $wm['wm_font_color'] = '999999';
                    $wm['wm_shadow_color'] = 'CCCCCC';
                    $wm['wm_vrt_alignment'] = 'top';
                    $wm['wm_hor_alignment'] = 'right';
                    $wm['wm_padding'] = '10';
                    $this->image_lib->initialize($wm);
                    $this->image_lib->watermark();
                }
                $this->image_lib->clear();
                $config = NULL;
            }

            if ($_FILES['userfile']['name'][0] != "") {

                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $files = $_FILES;
                $cpt = count($_FILES['userfile']['name']);
                for ($i = 0; $i < $cpt; $i++) {

                    $_FILES['userfile']['name'] = $files['userfile']['name'][$i];
                    $_FILES['userfile']['type'] = $files['userfile']['type'][$i];
                    $_FILES['userfile']['tmp_name'] = $files['userfile']['tmp_name'][$i];
                    $_FILES['userfile']['error'] = $files['userfile']['error'][$i];
                    $_FILES['userfile']['size'] = $files['userfile']['size'][$i];

                    $this->upload->initialize($config);

                    if (!$this->upload->do_upload()) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        redirect("products/add");
                    } else {

                        $pho = $this->upload->file_name;

                        $photos[] = $pho;
                        /* Main Image */
                        $this->load->library('image_lib');
                        $config['image_library'] = 'gd2';
                        $config['source_image'] = $this->upload_path . $pho;
                        $config['new_image'] = $this->upload_path . $pho;
                        $config['maintain_ratio'] = TRUE;
                        $config['width'] = 500;
                        $config['height'] = 500;
                        //$this->image_lib->clear();
                        $this->image_lib->initialize($config);
                        if (!$this->image_lib->resize()) {
                            echo $this->image_lib->display_errors();
                        }
                        /* Main Image */
                        $this->load->library('image_lib');
                        $config['image_library'] = 'gd2';
                        $config['source_image'] = $this->upload_path . $pho;
                        $config['new_image'] = $this->thumbs_path . $pho;
                        $config['maintain_ratio'] = TRUE;
                        $config['width'] = $this->Settings->twidth;
                        $config['height'] = $this->Settings->theight;

                        $this->image_lib->initialize($config);

                        if (!$this->image_lib->resize()) {
                            echo $this->image_lib->display_errors();
                        }

                        if ($this->Settings->watermark) {
                            $this->image_lib->clear();
                            $wm['source_image'] = $this->upload_path . $pho;
                            $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                            $wm['wm_type'] = 'text';
                            $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                            $wm['quality'] = '100';
                            $wm['wm_font_size'] = '16';
                            $wm['wm_font_color'] = '999999';
                            $wm['wm_shadow_color'] = 'CCCCCC';
                            $wm['wm_vrt_alignment'] = 'top';
                            $wm['wm_hor_alignment'] = 'right';
                            $wm['wm_padding'] = '10';
                            $this->image_lib->initialize($wm);
                            $this->image_lib->watermark();
                        }

                        $this->image_lib->clear();
                    }
                }
                $config = NULL;
            } else {
                $photos = NULL;
            }
            $data['quantity'] = isset($wh_total_quantity) ? $wh_total_quantity : 0;
            // $this->sma->print_arrays($data, $warehouse_qty, $product_attributes);
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->addProduct($data, $items, $warehouse_qty, $product_attributes, $photos, $postype_data)) {
            $this->session->set_flashdata('message', lang("product_added"));

            if ($_SESSION['lastRedirect'] == 'purchases/add') {
                redirect('purchases/add');
            } else {
                redirect('products');
            }
        } else {

            $exppurl = explode('/', $_SERVER['HTTP_REFERER']);
            $lasturl = count($exppurl) - 1;
            $_SESSION['lastRedirect'] = $exppurl[$lasturl - 1] . '/' . $exppurl[$lasturl];


            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['categories'] = $this->site->getAllCategories();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['brands'] = $this->site->getAllBrands();
            $this->data['divisions'] = $this->site->getAllDivision();
            $this->data['base_units'] = $this->site->getAllBaseUnits();
            $this->data['warehouses'] = $warehouses;
            $this->data['warehouses_products'] = $id ? $this->products_model->getAllWarehousesWithPQ($id) : NULL;
            $this->data['product'] = $id ? $this->products_model->getProductByID($id) : NULL;
            $this->data['variants'] = $this->products_model->getAllVariants();
            $this->data['combo_items'] = ($id && $this->data['product']->type == 'combo') ? $this->products_model->getProductComboItems($id) : NULL;
            $this->data['combo_items'] = ($id && $this->data['product']->type == 'Bundle') ? $this->products_model->getProductComboItems($id) : NULL;
            $this->data['product_options'] = $id ? $this->products_model->getProductOptionsWithWH($id) : NULL;
            $cfields = $this->site->getCustomeFieldsLabel('product');
            $this->data['custome_fields'] = $cfields['product'];

              //UrbanPiper restaurant data
              if ($this->data['Settings']->pos_type == 'restaurant' || $this->data['Settings']->pos_type == 'bakery') {

                $this->data['foodtype'] = $this->products_model->getfoodstype(); // Use UrbanPiper
            }

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('add_product')));
            $meta = array('page_title' => lang('add_product'), 'bc' => $bc);
            $this->data['seasons'] = $this->site->get_all_season();
            $this->page_construct('products/add', $meta, $this->data);
        }
    }

    function addcombo($id = NULL) {
        if ($this->input->is_ajax_request()) {
            $tax_rate = $this->input->post('tax_rate') ? $this->site->getTaxRateByID($this->input->post('tax_rate')) : NULL;
            $getunit = $this->products_model->getUnitByCode('pcs');
            $data = array(
                'code' => $this->input->post('code'),
//                'article_code' => $this->input->post('article_code'),
                'barcode_symbology' => 'code128',
                'weight' => $this->input->post('weight'),
                'storage_type' => $this->input->post('storage_type'),
                'name' => $this->input->post('name'),
                'divisionid' => $this->input->post('division'),
                'hsn_code' => $this->input->post('hsn_code'),
                'type' => 'combo',
//                'brand' => $this->input->post('brand'),
                'category_id' => $this->products_model->poscategory(),
//                'subcategory_id' => $this->input->post('subcategory') ? $this->input->post('subcategory') : NULL,
                'cost' => $this->sma->formatDecimal($this->input->post('cost')),
                'price' => $this->sma->formatDecimal($this->input->post('price')),
                'mrp' => $this->sma->formatDecimal($this->input->post('mrp')),
                'unit' => $getunit->id, //$this->input->post('unit'),
                'sale_unit' => $getunit->id, // $this->input->post('default_sale_unit'),
                'purchase_unit' => $getunit->id, //$this->input->post('default_purchase_unit'),
                'tax_rate' => $this->input->post('tax_rate'),
                'tax_method' => $this->input->post('tax_method'),
                'pos_combo_product' => '1',
            );

            /* if ($this->input->post('pos_type') == 'restaurant') {

              $data['up_items'] = ($this->input->post('up_items')) ? $this->input->post('up_items') : NULL;
              $data['food_type_id'] = ($this->input->post('up_food_type')) ? $this->input->post('up_food_type') : '1';

              $updata = array();

              if ($this->input->post('up_items') == '1') {
              $postype_data = array(
              'pos_type' => $this->input->post('pos_type'),
              'product_code' => $this->input->post('code'),
              'price' => $this->input->post('upprice'),
              'food_type_id' => ($this->input->post('up_food_type')) ? $this->input->post('up_food_type') : '1',
              'available' => $this->input->post('available'),
              'sold_at_store' => $this->input->post('sold_at_store'),
              'recommended' => $this->input->post('recommended'),
              'plat_zomato' => str_replace(' ', '', $this->input->post('tag_zomato')),
              'plat_swiggy' => str_replace(' ', '', $this->input->post('tag_swiggy')),
              'plat_foodpanda' => str_replace(' ', '', $this->input->post('tag_foodpanda')),
              'plat_ubereats' => str_replace(' ', '', $this->input->post('tag_ubereats')),
              'default_tag' => str_replace(' ', '', $this->input->post('default_tag')),
              'manage_stock' => $this->input->post('manage_stock'),
              );
              } //end if.
              }//end if */

            $total_price = 0;
            $c = sizeof($_POST['combo_item_code']) - 1;
            for ($r = 0; $r <= $c; $r++) {
                if (isset($_POST['combo_item_code'][$r]) && isset($_POST['combo_item_quantity'][$r]) && isset($_POST['combo_item_price'][$r])) {
                    $items[] = array('item_code' => $_POST['combo_item_code'][$r], 'quantity' => $_POST['combo_item_quantity'][$r], 'unit_price' => $_POST['combo_item_price'][$r],);
                }
                $total_price += $_POST['combo_item_price'][$r] * $_POST['combo_item_quantity'][$r];
            }
            if ($this->sma->formatDecimal($total_price) != $this->sma->formatDecimal($this->input->post('price'))) {
                //$this->form_validation->set_rules('combo_price', 'combo_price', 'required');
                //$this->form_validation->set_message('required', lang('pprice_not_match_ciprice'));
            }
            if ($this->products_model->addProduct($data, $items, $warehouse_qty, $product_attributes, $photos, $postype_data)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Product has been added successfully',
                    'data' => $this->input->post('code')
                ];
            } else {
                $response = [
                    'status' => 'error',
                    'message' => lang("access_denied"),
                ];
            }
        } else {
            $response = [
                'status' => 'error',
                'message' => lang("access_denied"),
            ];
        }
        echo json_encode($response);
    }

    function suggestions() {
        $term = $this->input->get('term', TRUE);
        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }

        $rows = $this->products_model->getProductNames($term);

        if ($rows) {
            foreach ($rows as $row) {



                $pr[] = ['id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => 1];
            }
            $this->sma->send_json($pr);
        } else {
            $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
        }
    }

    function get_suggestions() {
        $term = $this->input->get('term', TRUE);
        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }

        $rows = $this->products_model->getProductsForPrinting($term, 15);
        if ($rows) {
            foreach ($rows as $row) {
                $c = rand(1000, 9999);
                $variants = $this->products_model->getProductOptions($row->id);
                $product = $this->site->getProductByID($row->id);

                if (!$option_id) {
                    $option_id = ($variants && $product->primary_variant) ? $product->primary_variant : 0; //Set primary varients
                }

                $this->load->model('products_model');
                $batch_option = ($option_id && $product->storage_type == 'packed') ? $option_id : 0;
                $batch = $this->products_model->getProductBatch($row->id, $batch_option);

                $ri = ($this->Settings->item_addition) ? $row->id : $row->id . '_' . $c;
                $pr[] = array('id' => $ri, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => 1, 'variants' => $variants, 'batches' => $batch, 'option_id' => $option_id);
            }
            $this->sma->send_json($pr);
        } else {
            $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
        }
    }

    function addByAjax() {
        if (!$this->mPermissions('add')) {
            exit(json_encode(array('msg' => lang('access_denied'))));
        }
        if ($this->input->get('token') && $this->input->get('token') == $this->session->userdata('user_csrf') && $this->input->is_ajax_request()) {
            $product = $this->input->get('product');
            if (!isset($product['code']) || empty($product['code'])) {
                exit(json_encode(array('msg' => lang('product_code_is_required'))));
            }
            if (!isset($product['name']) || empty($product['name'])) {
                exit(json_encode(array('msg' => lang('product_name_is_required'))));
            }
            if (!isset($product['category_id']) || empty($product['category_id'])) {
                exit(json_encode(array('msg' => lang('product_category_is_required'))));
            }
            if (!isset($product['unit']) || empty($product['unit'])) {
                exit(json_encode(array('msg' => lang('product_unit_is_required'))));
            }
            if (!isset($product['price']) || empty($product['price'])) {
                exit(json_encode(array('msg' => lang('product_price_is_required'))));
            }
            if (!isset($product['cost']) || empty($product['cost'])) {
                exit(json_encode(array('msg' => lang('product_cost_is_required'))));
            }
            if ($this->products_model->getProductByCode($product['code'])) {
                exit(json_encode(array('msg' => lang('product_code_already_exist'))));
            }
            if ($row = $this->products_model->addAjaxProduct($product)) {
                $tax_rate = $this->site->getTaxRateByID($row->tax_rate);
                $pr = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'qty' => 1, 'cost' => $row->cost, 'name' => $row->name, 'tax_method' => $row->tax_method, 'tax_rate' => $tax_rate, 'discount' => '0');
                $this->sma->send_json(array('msg' => 'success', 'result' => $pr));
            } else {
                exit(json_encode(array('msg' => lang('failed_to_add_product'))));
            }
        } else {
            json_encode(array('msg' => 'Invalid token'));
        }
    }

    function edit($id = NULL) {
        $this->sma->checkPermissions();
        $this->load->helper('security');
        $Settings = $this->Settings;
        $url = base_url(); //'http://en.example.com';
        $ProductCustomField = $this->products_model->get_custom_product_field('url', $url);
        /*
          check url is available or not,
          if url are available then find subdomain from url and consider subdomain as a key, also find custom value from base_url(compare with url from table).
          if url are not available then consider pos_type of setting as a key, also find custom value from pos_type(compare with merchant_type from table).
          if url and merchant_type are not available then show default value
         */
        if (!empty($ProductCustomField)) {
            $parsedUrl = parse_url($url);
            $host = explode('.', $parsedUrl['host']);
            $subdomain = $host[0];
            //echo $subdomain; exit;
            $this->data['ProductCustomField'] = $ProductCustomField;
            $this->data['ProductCustomKey'] = $subdomain;
        } else {
            $this->data['ProductCustomField'] = $this->products_model->get_custom_product_field('merchant_type', $Settings->pos_type);
            if (!empty($this->data['ProductCustomField'])) {
                $this->data['ProductCustomKey'] = $Settings->pos_type;
            } else {
                $this->data['ProductCustomKey'] = 'NoProductCustomKey';
            }
        }
        if ($this->input->post('id')) {
            $id = $this->input->post('id');
        }
        $warehouses = $this->site->getAllWarehouses();
        $warehouses_products = $this->products_model->getAllWarehousesWithPQ($id);
        $product = $this->site->getProductByID($id);
        if (!$id || !$product) {
            $this->session->set_flashdata('error', lang('prduct_not_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        if ($this->input->post('type') == 'standard') {
            $this->form_validation->set_rules('cost', lang("product_cost"), 'required');
            $this->form_validation->set_rules('unit', lang("product_unit"), 'required');
        }
        $this->form_validation->set_rules('code', lang("product_code"), 'alpha_dash');
        if ($this->input->post('code') !== $product->code) {
            $this->form_validation->set_rules('code', lang("product_code"), 'is_unique[products.code]');
        }
       
        // if($this->input->post('shelf_life') !== $product->shelf_life){
        //     $this->form_validation->set_rules('shelf_life', lang("shelf_life"), 'required|numeric');
        // }
        if ($this->input->post('barcode_symbology') == 'ean13') {
            $this->form_validation->set_rules('code', lang("product_code"), 'min_length[13]|max_length[13]');
        }
        $this->form_validation->set_rules('product_image', lang("product_image"), 'xss_clean');
        $this->form_validation->set_rules('digital_file', lang("digital_file"), 'xss_clean');
        $this->form_validation->set_rules('userfile', lang("product_gallery_images"), 'xss_clean');

        if ($this->form_validation->run('products/edit') == TRUE) {

            $promotion = $this->input->post('promotion');
            if ($promotion):
                $promo_price = $this->input->post('promo_price');
                if ($promo_price == 0 || empty($promo_price)):
                    $this->session->set_flashdata('error', 'Please Enter ' . lang("promo_price"));
                    redirect("products/edit/$id");
                endif;

                $start_date = $this->input->post('start_date');
                $end_date = $this->input->post('end_date');
                if (!$this->sma->validPromoDate($start_date, $end_date)):
                    $this->session->set_flashdata('error', 'Invalid Promo date');
                    redirect("products/edit/$id");
                endif;
            endif;

            $price = $this->input->post('price');
            $mrp = $this->input->post('mrp');
            $cost = $this->input->post('cost');

            $data = array(
                'code' => $this->input->post('code'),
                'article_code' => $this->input->post('article_code'),
                'divisionid' => $this->input->post('division'),
                'barcode_symbology' => $this->input->post('barcode_symbology'),
                'weight' => $this->input->post('weight'),
                'storage_type' => $this->input->post('storage_type'),
                'name' => $this->input->post('name'),
                'hsn_code' => $this->input->post('hsn_code'),
                'type' => $this->input->post('type'),
                'brand' => $this->input->post('brand'),
                'category_id' => $this->input->post('category'),
                'subcategory_id' => $this->input->post('subcategory') ? $this->input->post('subcategory') : NULL,
                'cost' => $this->sma->formatDecimal($cost),
                'price' => $this->sma->formatDecimal($price),
                'mrp' => $this->sma->formatDecimal($mrp),
                'unit' => $this->input->post('unit'),
                'sale_unit' => $this->input->post('default_sale_unit'),
                'purchase_unit' => $this->input->post('default_purchase_unit'),
                'tax_rate' => $this->input->post('tax_rate'),
                'tax_method' => $this->input->post('tax_method'),
                'alert_quantity' => $this->input->post('alert_quantity'),
                'track_quantity' => $this->input->post('track_quantity') ? $this->input->post('track_quantity') : '0',
                'details' => $this->input->post('details'),
                'product_details' => $this->input->post('product_details'),
                'supplier1' => $this->input->post('supplier'),
                'supplier1price' => $this->sma->formatDecimal($this->input->post('supplier_price')),
                'supplier2' => $this->input->post('supplier_2'),
                'supplier2price' => $this->sma->formatDecimal($this->input->post('supplier_2_price')),
                'supplier3' => $this->input->post('supplier_3'),
                'supplier3price' => $this->sma->formatDecimal($this->input->post('supplier_3_price')),
                'supplier4' => $this->input->post('supplier_4'),
                'supplier4price' => $this->sma->formatDecimal($this->input->post('supplier_4_price')),
                'supplier5' => $this->input->post('supplier_5'),
                'supplier5price' => $this->sma->formatDecimal($this->input->post('supplier_5_price')),
                'cf1' => $this->input->post('cf1'),
                'cf2' => $this->input->post('cf2'),
                'cf3' => $this->input->post('cf3'),
                'cf4' => $this->input->post('cf4'),
                'cf5' => $this->input->post('cf5'),
                'cf6' => $this->input->post('cf6'),
                'promotion' => $this->input->post('promotion'),
                'promo_price' => $this->sma->formatDecimal($this->input->post('promo_price')),
                'start_date' => $this->input->post('start_date') ? $this->sma->fld($this->input->post('start_date')) : NULL,
                'end_date' => $this->input->post('end_date') ? $this->sma->fld($this->input->post('end_date')) : NULL,
                'supplier1_part_no' => $this->input->post('supplier_part_no'),
                'supplier2_part_no' => $this->input->post('supplier_2_part_no'),
                'supplier3_part_no' => $this->input->post('supplier_3_part_no'),
                'supplier4_part_no' => $this->input->post('supplier_4_part_no'),
                'supplier5_part_no' => $this->input->post('supplier_5_part_no'),
                'primary_variant' => $this->input->post('primary_variant'),
                'repeat_sale_discount_rate' => $this->input->post('repeat_sale_discount_rate'),
                'repeat_sale_validity' => $this->input->post('repeat_sale_validity'),
                'shelf_life' => $this->input->post('shelf_life'),
                'storage_conditions' => $this->input->post('storage_conditions'),
                'Season_id' => $this->input->post('season'),
                'rank' => $this->input->post('rank'),
                'flag_visible' => $this->input->post('flag_visible'),
                'discount_on_mrp' => $this->input->post('discount_on_mrp'),
            );


            if ($this->input->post('pos_type') == 'restaurant' || $this->input->post('pos_type') == 'bakery') {

                $data['up_items'] = ($this->input->post('up_items')) ? $this->input->post('up_items') : 0;
                $data['food_type_id'] = ($this->input->post('up_food_type')) ? $this->input->post('up_food_type') : '1';

                $updata = array();

                if ($this->input->post('up_items') == '1') {
                    $postype_data = array(
                        'pos_type' => $this->input->post('pos_type'),
                        'up_update_id' => $this->input->post('up_products_data_id'),
                        'price' => $this->input->post('upprice'),
                        'food_type_id' => ($this->input->post('up_food_type')) ? $this->input->post('up_food_type') : '1',
                        'available' => $this->input->post('available'),
                        'sold_at_store' => $this->input->post('sold_at_store'),
                        'recommended' => $this->input->post('recommended'),
                        'plat_zomato' => str_replace(' ', '', $this->input->post('tag_zomato')),
                        'plat_swiggy' => str_replace(' ', '', $this->input->post('tag_swiggy')),
                        'plat_foodpanda' => str_replace(' ', '', $this->input->post('tag_foodpanda')),
                        'plat_ubereats' => str_replace(' ', '', $this->input->post('tag_ubereats')),
                        // 'default_tag' => str_replace(' ', '', $this->input->post('default_tag')),
                        'manage_stock' => $this->input->post('manage_stock'),
                    );
                } //end if.
            }//end if

            $this->load->library('upload');

            if ($this->input->post('type') == 'standard') {
                if ($product_variants = $this->products_model->getProductOptions($id)) {
                    foreach ($product_variants as $pv) {
                        $update_variants[] = array('id' => $this->input->post('variant_id_' . $pv->id), 'name' => $this->input->post('variant_name_' . $pv->id), 'cost' => $this->input->post('variant_cost_' . $pv->id), 'mrp' =>$this->input->post('variant_mrp_' . $pv->id),'price' => $this->input->post('variant_price_' . $pv->id),'up_price' => $this->input->post('variant_upprice_' . $pv->id), 'variant_discount_on_mrp' => $this->input->post('variant_discount_' . $pv->id),'unit_quantity' => $this->input->post('unit_quantity_' . $pv->id), 'unit_weight' => $this->input->post('unit_weight_' . $pv->id));
                    }
                } else {
                    $update_variants = NULL;
                }
                for ($s = 2; $s > 5; $s++) {
                    $data['suppliers' . $s] = $this->input->post('supplier_' . $s);
                    $data['suppliers' . $s . 'price'] = $this->input->post('supplier_' . $s . '_price');
                }
                if (is_array($warehouses)) {
                    foreach ($warehouses as $warehouse) {
                        $warehouse_qty[] = array('warehouse_id' => $this->input->post('wh_' . $warehouse->id), 'rack' => $this->input->post('rack_' . $warehouse->id) ? $this->input->post('rack_' . $warehouse->id) : NULL);
                    }
                }

                if ($this->input->post('attributes')) {
                    $a = sizeof($_POST['attr_name']);
                    for ($r = 0; $r <= $a; $r++) {
                        if (isset($_POST['attr_name'][$r])) {
                            if ($product_variatnt = $this->products_model->getPrductVariantByPIDandName($id, trim($_POST['attr_name'][$r]))) {
                                $this->form_validation->set_message('required', lang("product_already_has_variant") . ' (' . $_POST['attr_name'][$r] . ')');
                                $this->form_validation->set_rules('new_product_variant', lang("new_product_variant"), 'required');
                            } else {
                                $product_attributes[] = array(
                                    'name' => $_POST['attr_name'][$r],
                                    'warehouse_id' => $_POST['attr_warehouse'][$r],
                                    'quantity' => $_POST['attr_quantity'][$r],
                                    'cost' => $_POST['attr_cost'][$r],
                                    'price' => $_POST['attr_price'][$r],
                                    'up_price' => (isset($_POST['attr_upprice'][$r]) ? $_POST['attr_upprice'][$r] : NULL),
                                    'unit_quantity' => $_POST['attr_unit_quantity'][$r],
                                    'unit_weight' => $_POST['attr_unit_weight'][$r],
                                    'eshop_name' => $_POST['attr_name'][$r],
                                    'eshop_price' => round($attr_eshop_price),
                                    'eshop_mrp' => round($attr_eshop_mrp),
                                    'mrp' => $_POST['attr_mrp'][$r],
                                    'variant_discount_on_mrp' => $_POST['attr_discount'][$r],
                                );
                            }
                        }
                    }
                } else {
                    $product_attributes = NULL;
                }
            } else {
                $warehouse_qty = NULL;
                $product_attributes = NULL;
            }

            if ($this->input->post('type') == 'service') {
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'combo') {
                $total_price = 0;
                $c = sizeof($_POST['combo_item_code']) - 1;
                for ($r = 0; $r <= $c; $r++) {
                    if (isset($_POST['combo_item_code'][$r]) && isset($_POST['combo_item_quantity'][$r]) && isset($_POST['combo_item_price'][$r])) {
                        $items[] = array('item_code' => $_POST['combo_item_code'][$r], 'quantity' => $_POST['combo_item_quantity'][$r], 'unit_price' => $_POST['combo_item_price'][$r],);
                    }
                    $total_price += $_POST['combo_item_price'][$r] * $_POST['combo_item_quantity'][$r];
                }
                if ($this->sma->formatDecimal($total_price) != $this->sma->formatDecimal($this->input->post('price'))) {
                    //$this->form_validation->set_rules('combo_price', 'combo_price', 'required');
                    //$this->form_validation->set_message('required', lang('pprice_not_match_ciprice'));
                }
                $data['track_quantity'] = 0;
            }elseif ($this->input->post('type') == 'Bundle') {
                $total_price = 0;
                $c = sizeof($_POST['combo_item_code']) - 1;
                for ($r = 0; $r <= $c; $r++) {
                    if (isset($_POST['combo_item_quantity'][$r])) {
                        $items[] = array('item_code' => $_POST['combo_item_code'][$r], 'quantity' => $_POST['combo_item_quantity'][$r], 'unit_price' => $_POST['combo_item_price'][$r],);
                    }
                    $total_price += $_POST['combo_item_price'][$r] * $_POST['combo_item_quantity'][$r];
                }
                if ($this->sma->formatDecimal($total_price) != $this->sma->formatDecimal($this->input->post('price'))) {
                    //$this->form_validation->set_rules('combo_price', 'combo_price', 'required');
                    //$this->form_validation->set_message('required', lang('pprice_not_match_ciprice'));
                }
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'digital') {
                if ($_FILES['digital_file']['size'] > 0) {
                    $config['upload_path'] = $this->digital_upload_path;
                    $config['allowed_types'] = $this->digital_file_types;
                    $config['max_size'] = $this->allowed_file_size;
                    $config['overwrite'] = FALSE;
                    $config['encrypt_name'] = TRUE;
                    $config['max_filename'] = 25;
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('digital_file')) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        redirect("products/add");
                    }
                    $file = $this->upload->file_name;
                    $data['file'] = $file;
                } else {
                    $this->form_validation->set_rules('digital_file', lang("digital_file"), 'required');
                }
                $config = NULL;
                $data['track_quantity'] = 0;
            }
            if (!isset($items)) {
                $items = NULL;
            }
            if ($_FILES['product_image']['size'] > 0) {
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('product_image')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("products/edit/" . $id);
                }
                $photo = $this->upload->file_name;
                $data['image'] = $photo;
                /* Main Image */
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->upload_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = 500;
                $config['height'] = 500;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                /* Main Image */
                $this->load->library('image_lib');
                $config['image_library'] = 'gd2';
                $config['source_image'] = $this->upload_path . $photo;
                $config['new_image'] = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = $this->Settings->twidth;
                $config['height'] = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                if ($this->Settings->watermark) {
                    $this->image_lib->clear();
                    $wm['source_image'] = $this->upload_path . $photo;
                    $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                    $wm['wm_type'] = 'text';
                    $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                    $wm['quality'] = '100';
                    $wm['wm_font_size'] = '16';
                    $wm['wm_font_color'] = '999999';
                    $wm['wm_shadow_color'] = 'CCCCCC';
                    $wm['wm_vrt_alignment'] = 'top';
                    $wm['wm_hor_alignment'] = 'right';
                    $wm['wm_padding'] = '10';
                    $this->image_lib->initialize($wm);
                    $this->image_lib->watermark();
                }
                $this->image_lib->clear();
                $config = NULL;
            }

            if ($_FILES['userfile']['name'][0] != "") {
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['max_width'] = $this->Settings->iwidth;
                $config['max_height'] = $this->Settings->iheight;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $files = $_FILES;
                $cpt = count($_FILES['userfile']['name']);
                for ($i = 0; $i < $cpt; $i++) {
                    $_FILES['userfile']['name'] = $files['userfile']['name'][$i];
                    $_FILES['userfile']['type'] = $files['userfile']['type'][$i];
                    $_FILES['userfile']['tmp_name'] = $files['userfile']['tmp_name'][$i];
                    $_FILES['userfile']['error'] = $files['userfile']['error'][$i];
                    $_FILES['userfile']['size'] = $files['userfile']['size'][$i];

                    $this->upload->initialize($config);

                    if (!$this->upload->do_upload()) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        redirect("products/edit/" . $id);
                    } else {
                        $pho = $this->upload->file_name;

                        $photos[] = $pho;
                        /* Main Image */
                        $this->load->library('image_lib');
                        $config['image_library'] = 'gd2';
                        $config['source_image'] = $this->upload_path . $pho;
                        $config['new_image'] = $this->upload_path . $pho;
                        $config['maintain_ratio'] = TRUE;
                        $config['width'] = 500;
                        $config['height'] = 500;
                        //$this->image_lib->clear();
                        $this->image_lib->initialize($config);
                        if (!$this->image_lib->resize()) {
                            echo $this->image_lib->display_errors();
                        }
                        /* Main Image */
                        $this->load->library('image_lib');
                        $config['image_library'] = 'gd2';
                        $config['source_image'] = $this->upload_path . $pho;
                        $config['new_image'] = $this->thumbs_path . $pho;
                        $config['maintain_ratio'] = TRUE;
                        $config['width'] = $this->Settings->twidth;
                        $config['height'] = $this->Settings->theight;

                        $this->image_lib->initialize($config);

                        if (!$this->image_lib->resize()) {
                            echo $this->image_lib->display_errors();
                        }

                        if ($this->Settings->watermark) {
                            $this->image_lib->clear();
                            $wm['source_image'] = $this->upload_path . $pho;
                            $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                            $wm['wm_type'] = 'text';
                            $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                            $wm['quality'] = '100';
                            $wm['wm_font_size'] = '16';
                            $wm['wm_font_color'] = '999999';
                            $wm['wm_shadow_color'] = 'CCCCCC';
                            $wm['wm_vrt_alignment'] = 'top';
                            $wm['wm_hor_alignment'] = 'right';
                            $wm['wm_padding'] = '10';
                            $this->image_lib->initialize($wm);
                            $this->image_lib->watermark();
                        }

                        $this->image_lib->clear();
                    }
                }
                $config = NULL;
            } else {
                $photos = NULL;
            }
            $data['quantity'] = isset($wh_total_quantity) ? $wh_total_quantity : 0;
            // $this->sma->print_arrays($data, $warehouse_qty, $update_variants, $product_attributes, $photos, $items);
        }


        if ($this->form_validation->run() == TRUE && $this->products_model->updateProduct($id, $data, $items, $warehouse_qty, $product_attributes, $photos, $update_variants, $postype_data)) {
            $this->session->set_flashdata('message', lang("product_updated"));
            redirect('products');
        } else {
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['categories'] = $this->site->getAllCategories();
            $this->data['tax_rates'] = $this->site->getAllTaxRates();
            $this->data['brands'] = $this->site->getAllBrands();
            $this->data['division'] = $this->site->getAllDivision();
            $this->data['base_units'] = $this->site->getAllBaseUnits();
            $this->data['warehouses'] = $warehouses;
            $this->data['warehouses_products'] = $warehouses_products;
            $this->data['product'] = $product;
            $this->data['variants'] = $this->products_model->getAllVariants();
            $this->data['subunits'] = $this->site->getUnitsByBUID($product->unit);
            $this->data['product_variants'] = $this->products_model->getProductOptions($id);
            $this->data['combo_items'] = $product->type == 'combo' ? $this->products_model->getProductComboItems($product->id) : NULL;
            $this->data['combo_items'] = $product->type == 'Bundle' ? $this->products_model->getProductComboItems($product->id) : NULL;
            $this->data['product_options'] = $id ? $this->products_model->getProductOptionsWithWH($id) : NULL;
            $cfields = $this->site->getCustomeFieldsLabel('product');
            $this->data['custome_fields'] = $cfields['product'];

            // Urbanpiper restaurant data
            if ($this->data['Settings']->pos_type == 'restaurant' || $this->data['Settings']->pos_type == 'bakery') {

                $this->data['foodtype'] = $this->products_model->getfoodstype(); // Use UrbanPiper

                $urbanbpiper_Data = $this->products_model->getupnproduct($product->id);

                if ($product->up_items == '1') {
                    $this->data['urbanbpiper_Data'] = ($urbanbpiper_Data->id) ? $urbanbpiper_Data : $this->products_model->setupnproduct($product);
                } else {
                    $this->data['urbanbpiper_Data'] = ($urbanbpiper_Data->id) ? $urbanbpiper_Data : '';
                }
            }
            // End Urbanpiper Restaurant data
            $this->data['seasons'] = $this->site->get_all_season();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('edit_product')));
            $meta = array('page_title' => lang('edit_product'), 'bc' => $bc);
            $this->page_construct('products/edit', $meta, $this->data);
        }
    }

    function import_csv() {

        if ($this->GP['products-import'] == 1):
            $this->GP['products-csv'] = $this->GP['products-import'];
        endif;

        $this->sma->checkPermissions('csv');
        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        /* Custom Field Logic */
        $Settings = $this->Settings;
        $url = base_url(); //'http://en.example.com';
        $ProductCustomField = $this->products_model->get_custom_product_field('url', $url);
        /*
          check url is available or not,
          if url are available then find subdomain from url and consider subdomain as a key, also find custom value from base_url(compare with url from table).
          if url are not available then consider pos_type of setting as a key, also find custom value from pos_type(compare with merchant_type from table).
          if url and merchant_type are not available then show default value
         */
        if (!empty($ProductCustomField)) {
            $parsedUrl = parse_url($url);
            $host = explode('.', $parsedUrl['host']);
            $subdomain = $host[0];
            //echo $subdomain; exit;
            $this->data['ProductCustomField'] = $ProductCustomField;
            $this->data['ProductCustomKey'] = $subdomain;
        } else {
            $this->data['ProductCustomField'] = $this->products_model->get_custom_product_field('merchant_type', $Settings->pos_type);
            if (!empty($this->data['ProductCustomField'])) {
                $this->data['ProductCustomKey'] = $Settings->pos_type;
            } else {
                $this->data['ProductCustomKey'] = 'NoProductCustomKey';
            }
        }
        /* End Custom Field Logic */

        if ($this->form_validation->run() == TRUE) {

            if (isset($_FILES["userfile"])) {

                /* $this->load->library('upload');

                  $config['upload_path'] = $this->digital_upload_path;
                  $config['allowed_types'] = 'csv';
                  $config['max_size'] = $this->allowed_file_size;
                  $config['overwrite'] = TRUE;
                  $config['encrypt_name'] = TRUE;
                  $config['max_filename'] = 25;

                  $this->upload->initialize($config);

                  if( ! $this->upload->do_upload())
                  {

                  $error = $this->upload->display_errors();
                  $this->session->set_flashdata('error', $error);
                  redirect("products/import_csv");
                  }

                  $csv = $this->upload->file_name;

                  $arrResult = array();
                  $handle = fopen($this->digital_upload_path . $csv, "r");
                  if($handle)
                  {
                  while(($row = fgetcsv($handle, 5000, ",")) !== FALSE)
                  {
                  $arrResult[] = $row;
                  }
                  fclose($handle);
                  }
                  $titles = array_shift($arrResult); */
                $this->load->library('excel');
                $File = $_FILES['userfile']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                //$reader= PHPExcel_IOFactory::createReader('Excel2007');
                $reader->setReadDataOnly(true);
                $path = $File; //"./uploads/upload.xlsx";
                $excel = $reader->load($path);

                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);

                $arrayCount = count($sheet);
                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                    // echo $sheet[$i]["A"].$sheet[$i]["B"].$sheet[$i]["C"].$sheet[$i]["D"].$sheet[$i]["E"];
                }

                $keys = array('name', 'code', 'divisionid', 'article_code', 'barcode_symbology', 'brand', 'category_code', 'unit', 'sale_unit', 'purchase_unit', 'cost', 'price', 'alert_quantity', 'tax_rate', 'tax_method', 'image', 'subcategory_code', 'variants', 'mrp', 'hsn_code', 'warehouse', 'cf1', 'cf2', 'cf3', 'cf4', 'cf5', 'cf6', 'season_id');



                if ($this->Settings->pos_type == 'restaurant') {
                    $keys[] = 'up_items';
                    $keys[] = 'food_type_id';
                    $keys[] = 'up_price';
                    $keys[] = 'available';
                }

                $final = array();
                // $this->sma->print_arrays($arrResult);

                foreach ($arrResult as $key => $value) {
                    //$value[1] = empty($value[1]) ? rand(100000000, 999999999) : $value[1];
                    $value['B'] = empty($value['B']) ? rand(100000000, 999999999) : $value['B'];
                    $final[] = array_combine($keys, $value);
                }
                 
                //$this->sma->print_arrays($final);
                //$this->sma->print_arrays($final);
                $rw = 2;

                foreach ($final as $csv_pr) {
                  
                    if (!$this->products_model->getProductByCode(trim($csv_pr['code']))) {
                        $catd = $this->products_model->getCategoryByCode(trim($csv_pr['category_code']));

                        $catcode = $catd->code;

//                        if ($catd = $this->products_model->getCategoryByCode(trim($csv_pr['category_code']))) {
                        if ($catcode == trim($csv_pr['category_code'])) {
                            $brand = $this->products_model->getBrandByName(trim($csv_pr['brand']));
                            $unit = $this->products_model->getUnitByCode(trim($csv_pr['unit']));
                            $base_unit = $unit ? $unit->id : NULL;
                            $sale_unit = $base_unit;
                            $purcahse_unit = $base_unit;

                            if ($base_unit) {
                                $units = $this->site->getUnitsByBUID($base_unit);
                                foreach ($units as $u) {
                                    if ($u->code == trim($csv_pr['sale_unit'])) {
                                        $sale_unit = $u->id;
                                    }
                                    if ($u->code == trim($csv_pr['purchase_unit'])) {
                                        $purcahse_unit = $u->id;
                                    }
                                }
                            } else {
                                $this->session->set_flashdata('error', lang("check_unit") . " (" . $csv_pr['unit'] . "). " . lang("unit_code_x_exist") . " " . lang("line_no") . " " . $rw);

                                redirect("products/import_csv");
                            }
                            $pr_code[] = trim($csv_pr['code']);
                            $divisionid[] = trim($csv_pr['divisionid']);
                            $pr_name[] = trim($csv_pr['name']);
                            $pr_cat[] = $catd->id;
                            $pr_variants[] = trim($csv_pr['variants']);
                            $pr_brand[] = $brand ? $brand->id : NULL;
                            $pr_unit[] = $base_unit;
                            $sale_units[] = $sale_unit;
                            $purcahse_units[] = $purcahse_unit;
                            $tax_method[] = !empty($csv_pr['tax_method']) && strtolower($csv_pr['tax_method']) == 'exclusive' ? 1 : 0;
                            $prsubcat = $this->products_model->getCategoryByCode(trim($csv_pr['subcategory_code']));

                            $pr_subcat[] = $prsubcat ? $prsubcat->id : NULL;

                            $pr_cost[] = trim($csv_pr['cost']);
                            $pr_price[] = trim($csv_pr['price']);
                            $pr_aq[] = trim($csv_pr['alert_quantity']);

                            $tax_details = $this->products_model->getTaxRateByName(trim($csv_pr['tax_rate']));

                            $pr_tax[] = $tax_details ? $tax_details->id : NULL;
                            //$bs[] = mb_strtolower(trim($csv_pr['barcode_symbology']), 'UTF-8');
                            $bss = array('code25' => 'Code25', 'code39' => 'Code39', 'code128' => 'Code128', 'ean8' => 'EAN8', 'ean13' => 'EAN13', 'upca' => 'UPC-A', 'upce' => 'UPC-E');
                            if (array_key_exists(strtolower($csv_pr['barcode_symbology']), $bss)) {
                                $bs[] = strtolower($csv_pr['barcode_symbology']);
                            } else {
                                $bs[] = '';
                            }
                            //$this->sma->print_arrays($final);
                            $cf1[] = trim($csv_pr['cf1']);
                            $cf2[] = trim($csv_pr['cf2']);
                            $cf3[] = trim($csv_pr['cf3']);
                            $cf4[] = trim($csv_pr['cf4']);
                            $cf5[] = trim($csv_pr['cf5']);
                            $cf6[] = trim($csv_pr['cf6']);
                            $season_id[] = trim($csv_pr['season_id']);
                            $mrp[] = trim($csv_pr['mrp']);
                            $hsn_code[] = trim($csv_pr['hsn_code']);
                            $pr_article_code[] = trim($csv_pr['article_code']);
                            $wh = $this->products_model->getWarehouseIdByWarehouseCode(trim($csv_pr['warehouse']));
                            $warehouse[] = $wh->id;

                            $quantity[] = trim($csv_pr['quantity']);
                            if ($this->Settings->pos_type == 'restaurant') {
                                if (strtolower($csv_pr['up_items']) == 'yes')
                                    $up_items[] = 1;
                                else
                                    $up_items[] = '';
                                $food_type_id[] = trim($csv_pr['food_type_id']);
                                $up_price[] = trim($csv_pr['up_price']);
                                if (strtolower($csv_pr['available']) == 'yes')
                                    $available[] = 1;
                                else
                                    $available[] = '';
                            }else {
                                $up_items[] = '';
                                $food_type_id[] = '';
                                $up_price[] = '';
                                $available[] = '';
                            }
                        } else {


                            $this->session->set_flashdata('error', lang("check_category_code") . " (" . $csv_pr['category_code'] . "). " . lang("category_code_x_exist") . " " . lang("line_no") . " " . $rw);
                            redirect("products/import_csv");
                        }
                    } else {

                        $this->session->set_flashdata('error', 'Product code "' . $csv_pr['code'] . '" already exist');
                        redirect("products/import_csv");
                    }
                    $rw++;
                }
            }

            //$ikeys = array('code',  'divisionid', 'barcode_symbology', 'name', 'brand', 'category_id', 'unit', 'sale_unit', 'purchase_unit', 'cost', 'price', 'alert_quantity', 'tax_rate', 'tax_method', 'subcategory_id', 'variants', 'cf1', 'cf2', 'cf3', 'cf4', 'cf5', 'cf6', 'mrp', 'hsn_code', 'warehouse', 'quantity','article_code', 'up_items', 'food_type_id', 'up_price', 'available',);

            $ikeys = array('code', 'divisionid', 'barcode_symbology', 'name', 'brand', 'category_id', 'unit', 'sale_unit', 'purchase_unit', 'cost', 'price', 'alert_quantity', 'tax_rate', 'tax_method', 'subcategory_id', 'variants', 'cf1', 'cf2', 'cf3', 'cf4', 'cf5', 'cf6', 'mrp', 'hsn_code', 'warehouse', 'article_code', 'up_items', 'food_type_id', 'up_price', 'available','season_id',);

            $items = array();
            //foreach(array_map(NULL, $pr_code, $divisionid, $bs, $pr_name, $pr_brand, $pr_cat, $pr_unit, $sale_units, $purcahse_units, $pr_cost, $pr_price, $pr_aq, $pr_tax, $tax_method, $pr_subcat, $pr_variants, $cf1, $cf2, $cf3, $cf4, $cf5, $cf6, $mrp, $hsn_code, $warehouse, $quantity,  $pr_article_code, $up_items, $food_type_id, $up_price, $available) as $ikey => $value)

            foreach (array_map(NULL, $pr_code, $divisionid, $bs, $pr_name, $pr_brand, $pr_cat, $pr_unit, $sale_units, $purcahse_units, $pr_cost, $pr_price, $pr_aq, $pr_tax, $tax_method, $pr_subcat, $pr_variants, $cf1, $cf2, $cf3, $cf4, $cf5, $cf6, $mrp, $hsn_code, $warehouse, $pr_article_code, $up_items, $food_type_id, $up_price, $available, $season_id) as $ikey => $value) {
                $items[] = array_combine($ikeys, $value);
            }
           
        }
       
        if ($this->form_validation->run() == TRUE && $prs = $this->products_model->add_import_csv_products($items)) {
            $this->session->set_flashdata('message', sprintf(lang("products_added"), $prs));
            redirect('products');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));

            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('import_products_by_csv')));
            $meta = array('page_title' => lang('import_products_by_csv'), 'bc' => $bc);
            $this->page_construct('products/import_csv', $meta, $this->data);
        }
    }

    function update_price() {
        $this->sma->checkPermissions('csv');
        $this->load->helper('security');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {
            if (DEMO) {
                $this->session->set_flashdata('message', lang("disabled_in_demo"));
                redirect('welcome');
            }

            if (isset($_FILES["userfile"])) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = 'xls';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;
                $this->upload->initialize($config);

                if (!$this->upload->do_upload()) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect("products");
                }


                $this->load->library('excel');
                $File = $_FILES['userfile']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                $reader->setReadDataOnly(true);
                $path = $File;
                $excel = $reader->load($path);

                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);
                $arrayCount = count($sheet);
                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                }


                /* $csv = $this->upload->file_name;
                  $arrResult = array();
                  $handle = fopen($this->digital_upload_path . $csv, "r");
                  if ($handle) {
                  while (($row = fgets($handle, 1000, ",")) !== FALSE) {
                  $arrResult[] = $row;
                  }

                  fclose($handle);
                  }
                  print_r($arrResult);exit;

                  $titles = array_shift($arrResult); */

                $keys = array('code', 'Product_Name', 'article_code', 'price', 'mrp');
                if ($this->Settings->pos_type == 'restaurant' || $this->Settings->pos_type=='bakery' || $this->Settings->pos_type=='sweets') {
                    $keys[] = 'up_price';
                }
                $keys[] = 'Variants_Name';
                $keys[] = 'Variants_Mrp';
                $keys[] = 'Variants_Price';
                $keys[] = 'Variants_Discount_on_mrp';

                $final = $csvdata = array();

                foreach ($arrResult as $key => $value) {
                    $csvdata[] = array_combine($keys, $value);
                }

                $rw = 2;
                $flashError = '';
                foreach ($csvdata as $csv_pr) {

                    if (!$this->products_model->getProductByCode(trim($csv_pr['code']))) {
                        $flashError[] = lang("check_product_code") . " (" . $csv_pr['code'] . "). " . lang("code_x_exist") . " " . lang("line_no") . " " . $rw;
                        $this->session->set_flashdata('error', join('<br/>', $flashError));
                        redirect('products');
                    } else {
                        $final[] = $csv_pr;
                    }
                    $rw++;
                }
            }
        } elseif ($this->input->post('update_price')) {
            $this->session->set_flashdata('error', validation_errors());
            redirect("system_settings/group_product_prices/" . $group_id);
        }

        if ($this->form_validation->run() == TRUE && !empty($final)) {

            $this->products_model->updatePrice($final);
            foreach (array_keys($final) as $key) {
                unset($final[$key]['price']);
                unset($final[$key]['mrp']);
                unset($final[$key]['Variants_Name']);
                unset($final[$key]['Variants_Price']);
                unset($final[$key]['Product_Name']);
                unset($final[$key]['article_code']);
                unset($final[$key]['Variants_Mrp']);
                unset($final[$key]['Variants_Discount_on_mrp']);

                $final[$key]['product_code'] = $final[$key]['code'];
                unset($final[$key]['code']);
                $final[$key]['price'] = $final[$key]['up_price'];
                unset($final[$key]['up_price']);
            }


            $this->products_model->updateUPProductPrice($final);

            $this->session->set_flashdata('message', lang("price_updated"));
            redirect('products');
        } else {
            $this->data['userfile'] = array('name' => 'userfile', 'id' => 'userfile', 'type' => 'text', 'value' => $this->form_validation->set_value('userfile'));
            $this->data['modal_js'] = $this->site->modal_js();
            $this->load->view($this->theme . 'products/update_price', $this->data);
        }
    }

    function delete($id = NULL) {
        $this->sma->checkPermissions(NULL, TRUE);

        if ($this->input->get('id')) {
            $id = $this->input->get('id');
        }


        if ($res == 'created') {
            if ($this->input->is_ajax_request()) {
                echo lang("Sale/Purchase/Transfer/Quotation_has_been_created_against_this_product");
                die();
            }
            $this->session->set_flashdata('message', lang('Sale/Purchase/Transfer/Quotation_has_been_created_against_this_product'));
            redirect('welcome');
        }
        $res = $this->sma->storeDeletedData('products', 'id', $id);
        if ($this->products_model->deleteProduct($id)) {
            if ($this->input->is_ajax_request()) {
                echo lang("product_deleted");
                die();
            }
            $this->session->set_flashdata('message', lang('product_deleted'));
            redirect('welcome');
        }
    }

    function quantity_adjustments($warehouse_id = NULL) {
        $this->sma->checkPermissions('adjustments');

        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
            $this->data['warehouse_id'] = $warehouse_id;
        } else {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByID($warehouse_id) : NULL;
            $this->data['warehouse_id'] = $warehouse_id == NULL ? $this->session->userdata('warehouse_id') : $warehouse_id;
        }

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('quantity_adjustments')));
        $meta = array('page_title' => lang('quantity_adjustments'), 'bc' => $bc);
        $this->page_construct('products/quantity_adjustments', $meta, $this->data);
    }

    function getadjustments($warehouse_id = NULL) {
        $this->sma->checkPermissions('adjustments');

        $delete_link = "<a href='#' class='tip po' title='<b>" . $this->lang->line("delete_adjustment") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete' href='" . site_url('products/delete_adjustment/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i></a>";

        $this->load->library('datatables');
        $this->datatables->select("{$this->db->dbprefix('adjustments')}.id as id, date, reference_no, warehouses.name as wh_name, CONCAT({$this->db->dbprefix('users')}.first_name, ' ', {$this->db->dbprefix('users')}.last_name) as created_by, note, attachment")->from('adjustments')->join('warehouses', 'warehouses.id=adjustments.warehouse_id', 'left')->join('users', 'users.id=adjustments.created_by', 'left')->group_by("adjustments.id");
        if ($warehouse_id) {
            $getwarehouse = str_replace("_", ",", $warehouse_id);
            $this->datatables->where('adjustments.warehouse_id IN (' . $getwarehouse . ')');
        }

        if ($this->session->userdata('view_right') == '0') {
            $this->datatables->where('adjustments.created_by', $this->session->userdata('user_id'));
        }



        $this->datatables->add_column("Actions", "<div class='text-center'><a href='" . site_url('products/edit_adjustment/$1') . "' class='tip' title='" . lang("edit_adjustment") . "'><i class='fa fa-edit'></i></a> " . $delete_link . "</div>", "id");

        echo $this->datatables->generate();
    }

    function view_adjustment($id) {
        $this->sma->checkPermissions('adjustments', TRUE);

        $adjustment = $this->products_model->getAdjustmentByID($id);
        if (!$id || !$adjustment) {
            $this->session->set_flashdata('error', lang('adjustment_not_found'));
            $this->sma->md();
        }

        $this->data['inv'] = $adjustment;
        $this->data['rows'] = $this->products_model->getAdjustmentItems($id);
        $this->data['created_by'] = $this->site->getUser($adjustment->created_by);
        $this->data['updated_by'] = $this->site->getUser($adjustment->updated_by);
        $this->data['warehouse'] = $this->site->getWarehouseByID($adjustment->warehouse_id);
        $this->load->view($this->theme . 'products/view_adjustment', $this->data);
    }

    function add_adjustment($count_id = NULL) {

        $this->sma->checkPermissions('adjustments', TRUE);
        $this->form_validation->set_rules('warehouse', lang("warehouse"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld($this->input->post('date'));
            } else {
                $date = date('Y-m-d H:s:i');
            }

            $reference_no = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('qa');
            $warehouse_id = $this->input->post('warehouse');
            $notebill = $this->sma->clear_tags(strip_tags($this->input->post('note')));

            $i = isset($_POST['product_id']) ? sizeof($_POST['product_id']) : 0;

            for ($r = 0; $r < $i; $r++) {

                $product_id = $_POST['product_id'][$r];
                $product_code = $_POST['product_code'][$r];
                $product_name = $_POST['product_name'][$r];
                $storage_type = $_POST['storage_type'][$r];
                $cost = $_POST['cost'][$r];
                $price = $_POST['price'][$r];
                $real_unit_cost = $_POST['real_unit_cost'][$r];
                $expiry = $_POST['expiry'][$r];
                $tax_rate_id = $_POST['tax_rate_id'][$r];
                $tax_method = $_POST['tax_method'][$r];
                $product_type = $_POST['product_type'][$r];
                $unit_id = $_POST['unit'][$r];
                $hsn_code = $_POST['hsn_code'][$r];
                $mrp = $_POST['mrp'][$r];
                $variant = isset($_POST['variant'][$r]) && !empty($_POST['variant'][$r]) ? $_POST['variant'][$r] : 0;
                $item_batch_number = (isset($_POST['batch_number'][$r]) && $_POST['batch_number'][$r] != '') ? $_POST['batch_number'][$r] : NULL;
                $batch_qty = (isset($_POST['batch_qty'][$r]) && $_POST['batch_qty'][$r] != '') ? $_POST['batch_qty'][$r] : 0;
                $item_qty = (isset($_POST['item_qty'][$r]) && $_POST['item_qty'][$r] != '') ? $_POST['item_qty'][$r] : 0;
                $type = isset($_POST['type'][$r]) ? $_POST['type'][$r] : 'subtraction';
                $quantity = $_POST['quantity'][$r];
                $note = $_POST['note'][$r];

                $batchData = FALSE;

                $variant = ($storage_type == 'packed') ? $variant : 0;

                if ($this->Settings->product_batch_setting > 0 && $item_batch_number) {

                    $batchData = $this->site->getProductBatchData($item_batch_number, $product_id, $variant);

                    if ($batchData) {
                        $batch_id = $batchData->id;
                        $batch_number = $batchData->batch_no;
                        $cost = $batchData->cost ? $batchData->cost : $cost;
                        $real_unit_cost = $batchData->cost ? $batchData->cost : $real_unit_cost;
                        $base_unit_cost = $batchData->cost ? $batchData->cost : $cost;
                        $expiry = ($batchData->expiry != '' && $batchData->expiry !== '0000-00-00') ? $batchData->expiry : $expiry;
                    } else {

                        if ($variant) {
                            $variantData = $this->site->getVerientById($variant);
                        }

                        $new_batch = array(
                            'product_id' => $product_id,
                            'option_id' => $variant,
                            'batch_no' => $item_batch_number,
                            'cost' => $cost,
                            'price' => ($variantData) ? ($price + $variantData['price']) : $price,
                            'mrp' => $mrp,
                            'expiry_date' => $expiry
                        );
                        $this->site->addBatchInfo($new_batch);
                    }
                }

                $itemStocks = ($batchData !== FALSE) ? $batch_qty : $item_qty;

                if (!$this->Settings->overselling && $type == 'subtraction') {
                    if ($itemStocks < $quantity) {
                        $errorMsg = (($batchData !== FALSE) ? lang('warehouse_option_batch_qty_is_less_than_damage') : lang('warehouse_option_qty_is_less_than_damage'));
                        $this->session->set_flashdata('error', $product_name . ' : ' . $errorMsg);
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }

                $products[] = array(
                    'product_id' => $product_id,
                    'type' => $type,
                    'quantity' => $quantity,
                    'warehouse_id' => $warehouse_id,
                    'product_code' => $product_code,
                    'product_name' => $product_name,
                    'option_id' => $variant,
                    'net_unit_cost' => $cost,
                    'tax_rate_id' => $tax_rate_id,
                    'tax_method' => $tax_method,
                    'expiry' => $expiry,
                    'real_unit_cost' => $real_unit_cost,
                    'hsn_code' => $hsn_code,
                    'mrp' => $mrp,
                    'product_unit_id' => $unit_id,
                    'unit_quantity' => ($variantData && $variant) ? $variantData['unit_quantity'] : 1,
                    'batch_number' => $item_batch_number
                );
            }

            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("products"), 'required');
            } else {
                krsort($products);
            }

            $data = array('date' => $date, 'reference_no' => $reference_no, 'warehouse_id' => $warehouse_id, 'note' => $notebill, 'created_by' => $this->session->userdata('user_id'), 'count_id' => $this->input->post('count_id') ? $this->input->post('count_id') : NULL,);

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['attachment'] = $photo;
            }

            // $this->sma->print_arrays($data, $products);
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->addAdjustment($data, $products)) {
            $this->session->set_userdata('remove_qals', 1);
            $this->session->set_flashdata('message', lang("quantity_adjusted"));
            redirect('products/quantity_adjustments');
        } else {

            if ($count_id) {
                $stock_count = $this->products_model->getStouckCountByID($count_id);
                $items = $this->products_model->getStockCountItems($count_id);
                $c = rand(100000, 9999999);
                foreach ($items as $item) {
                    if ($item->counted != $item->expected) {
                        $product = $this->site->getProductByID($item->product_id);
                        $row = json_decode('{}');
                        $row->id = $item->product_id;
                        $row->code = $product->code;
                        $row->name = $product->name;
                        $row->qty = $item->counted - $item->expected;
                        $row->type = $row->qty > 0 ? 'addition' : 'subtraction';
                        $row->qty = $row->qty > 0 ? $row->qty : (0 - $row->qty);
                        $options = NULL;
                        $row->option = 0;
                        if ($product->storage_type == 'packed') {
                            $options = $this->products_model->getProductOptions($product->id);
                            $row->option = $item->product_variant_id ? $item->product_variant_id : 0;
                        }

                        $row->serial = '';
                        $ri = $this->Settings->item_addition ? $product->id : $c;

                        $pr[$ri] = array('id' => str_replace(".", "", microtime(TRUE)), 'item_id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'row' => $row, 'options' => $options);
                        $c++;
                    }
                }
            }
            $this->data['adjustment_items'] = $count_id ? json_encode($pr) : FALSE;
            $this->data['warehouse_id'] = $count_id ? $stock_count->warehouse_id : FALSE;
            $this->data['count_id'] = $count_id;
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('add_adjustment')));
            $meta = array('page_title' => lang('add_adjustment'), 'bc' => $bc);
            $this->page_construct('products/add_adjustment', $meta, $this->data);
        }
    }

    function edit_adjustment($id) {
        $this->sma->checkPermissions('adjustments', TRUE);
        $adjustment = $this->products_model->getAdjustmentByID($id);
        if (empty($adjustment)) {
            $this->session->set_flashdata('error', lang('Adjustment not found'));
            redirect("products/quantity_adjustments");
        }
        if (!$id || !$adjustment) {
            $this->session->set_flashdata('error', lang('adjustment_not_found'));
            $this->sma->md();
        }
        $this->form_validation->set_rules('warehouse', lang("warehouse"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld($this->input->post('date'));
            } else {
                $date = $adjustment->date;
            }

            $reference_no = $this->input->post('reference_no');
            $warehouse_id = $this->input->post('warehouse');
            $note = $this->sma->clear_tags($this->input->post('note'));

            $i = isset($_POST['product_id']) ? sizeof($_POST['product_id']) : 0;
            for ($r = 0; $r < $i; $r++) {

                $product_id = $_POST['product_id'][$r];
                $type = isset($_POST['type'][$r]) ? $_POST['type'][$r] : 'subtraction';
                $quantity = $_POST['quantity'][$r];
                $variant = isset($_POST['variant'][$r]) && !empty($_POST['variant'][$r]) ? $_POST['variant'][$r] : NULL;

                if (!$this->Settings->overselling && $type == 'subtraction') {
                    if ($variant) {
                        if ($op_wh_qty = $this->products_model->getProductWarehouseOptionQty($variant, $warehouse_id)) {
                            if ($op_wh_qty->quantity < $quantity) {
                                $this->session->set_flashdata('error', lang('warehouse_option_qty_is_less_than_damage'));
                                redirect($_SERVER["HTTP_REFERER"]);
                            }
                        } else {
                            $this->session->set_flashdata('error', lang('warehouse_option_qty_is_less_than_damage'));
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    }
                    if ($wh_qty = $this->products_model->getProductQuantity($product_id, $warehouse_id)) {
                        if ($wh_qty['quantity'] < $quantity) {
                            $this->session->set_flashdata('error', lang('warehouse_qty_is_less_than_damage'));
                            redirect($_SERVER["HTTP_REFERER"]);
                        }
                    } else {
                        $this->session->set_flashdata('error', lang('warehouse_qty_is_less_than_damage'));
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                }

                $products[] = array('product_id' => $product_id, 'type' => $type, 'quantity' => $quantity, 'warehouse_id' => $warehouse_id, 'option_id' => $variant,);
            }

            if (empty($products)) {
                $this->form_validation->set_rules('product', lang("products"), 'required');
            } else {
                krsort($products);
            }

            $data = array('date' => $date, 'reference_no' => $reference_no, 'warehouse_id' => $warehouse_id, 'note' => $note, 'created_by' => $this->session->userdata('user_id'));

            if ($_FILES['document']['size'] > 0) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = $this->digital_file_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('document')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }
                $photo = $this->upload->file_name;
                $data['attachment'] = $photo;
            }

            // $this->sma->print_arrays($data, $products);
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->updateAdjustment($id, $data, $products)) {
            $this->session->set_userdata('remove_qals', 1);
            $this->session->set_flashdata('message', lang("quantity_adjusted"));
            redirect('products/quantity_adjustments');
        } else {

            $inv_items = $this->products_model->getAdjustmentItems($id);
            krsort($inv_items);
            $c = rand(100000, 9999999);
            foreach ($inv_items as $item) {
                $product = $this->site->getProductByID($item->product_id);
                $row = json_decode('{}');
                $row->id = $item->product_id;
                $row->code = $product->code;
                $row->name = $product->name;
                $row->qty = $item->quantity;
                $row->type = $item->type;
                $row->product_qty = $item->product_qty;
                $row->storage_type = $product->storage_type;

                $options = $this->products_model->getProductOptions($product->id);
                $row->option = $item->option_id ? $item->option_id : 0;
                $row->serial = $item->serial_no ? $item->serial_no : '';

                $row_id = $row->id . $row->option;

                $ri = $this->Settings->item_addition ? $row_id : $c;

                $pr[$ri] = ['id' => $c, 'item_id' => $row_id, 'label' => $row->name . " (" . $row->code . ")", 'row' => $row, 'options' => $options];
                $c++;
            }

            $this->data['adjustment'] = $adjustment;


            $this->data['adjustment_items'] = json_encode($pr);
            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('edit_adjustment')));
            $meta = array('page_title' => lang('edit_adjustment'), 'bc' => $bc);
            $this->page_construct('products/edit_adjustment', $meta, $this->data);
        }
    }

    function add_adjustment_by_csv() {
        $this->sma->checkPermissions('adjustments', TRUE);
        $this->form_validation->set_rules('warehouse', lang("warehouse"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld($this->input->post('date'));
            } else {
                $date = date('Y-m-d H:s:i');
            }

            $reference_no = $this->input->post('reference_no') ? $this->input->post('reference_no') : $this->site->getReference('qa');
            $warehouse_id = $this->input->post('warehouse');
            $note = $this->sma->clear_tags($this->input->post('note'));
            $data = array('date' => $date, 'reference_no' => $reference_no, 'warehouse_id' => $warehouse_id, 'note' => $note, 'created_by' => $this->session->userdata('user_id'), 'count_id' => NULL,);

            if ($_FILES['csv_file']['size'] > 0) {

                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('csv_file')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                $csv = $this->upload->file_name;
                $data['attachment'] = $csv;

                // $arrResult = array();
                // $handle = fopen($this->digital_upload_path . $csv, "r");
                // if ($handle) {
                //     while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                //         $arrResult[] = $row;
                //     }
                //     fclose($handle);
                // }
                // $titles = array_shift($arrResult);

                $this->load->library('excel');
                $File = $_FILES['csv_file']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                //$reader= PHPExcel_IOFactory::createReader('Excel2007');
                $reader->setReadDataOnly(true);
                $path = $File; //"./uploads/upload.xlsx";
                $excel = $reader->load($path);
                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);
                $arrayCount = count($sheet);

                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                }
                $keys = array('code', 'quantity', 'variant');
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
                // $this->sma->print_arrays($final);
                $rw = 2;
                foreach ($final as $pr) {
                    if ($product = $this->products_model->getProductByCode(trim($pr['code']))) {
                        $csv_variant = trim($pr['variant']);
                        $variant = !empty($csv_variant) ? $this->products_model->getProductVariantID($product->id, $csv_variant) : FALSE;

                        $csv_quantity = trim($pr['quantity']);
                        $type = $csv_quantity > 0 ? 'addition' : 'subtraction';
                        $quantity = $csv_quantity > 0 ? $csv_quantity : (0 - $csv_quantity);

                        if (!$this->Settings->overselling && $type == 'subtraction') {
                            if ($variant) {
                                if ($op_wh_qty = $this->products_model->getProductWarehouseOptionQty($variant, $warehouse_id)) {
                                    if ($op_wh_qty->quantity < $quantity) {
                                        $this->session->set_flashdata('error', lang('warehouse_option_qty_is_less_than_damage') . ' - ' . lang('line_no') . ' ' . $rw);
                                        redirect($_SERVER["HTTP_REFERER"]);
                                    }
                                } else {
                                    $this->session->set_flashdata('error', lang('warehouse_option_qty_is_less_than_damage') . ' - ' . lang('line_no') . ' ' . $rw);
                                    redirect($_SERVER["HTTP_REFERER"]);
                                }
                            }
                            if ($wh_qty = $this->products_model->getProductQuantity($product->id, $warehouse_id)) {
                                if ($wh_qty['quantity'] < $quantity) {
                                    $this->session->set_flashdata('error', lang('warehouse_qty_is_less_than_damage') . ' - ' . lang('line_no') . ' ' . $rw);
                                    redirect($_SERVER["HTTP_REFERER"]);
                                }
                            } else {
                                $this->session->set_flashdata('error', lang('warehouse_qty_is_less_than_damage') . ' - ' . lang('line_no') . ' ' . $rw);
                                redirect($_SERVER["HTTP_REFERER"]);
                            }
                        }

                        $products[] = array('product_id' => $product->id, 'type' => $type, 'quantity' => $quantity, 'warehouse_id' => $warehouse_id, 'option_id' => $variant,);
                    } else {
                        $this->session->set_flashdata('error', lang('check_product_code') . ' (' . $pr['code'] . '). ' . lang('product_code_x_exist') . ' ' . lang('line_no') . ' ' . $rw);
                        redirect($_SERVER["HTTP_REFERER"]);
                    }
                    $rw++;
                }
            } else {
                $this->form_validation->set_rules('csv_file', lang("upload_file"), 'required');
            }

            // $this->sma->print_arrays($data, $products);
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->addAdjustment($data, $products)) {
            $this->session->set_flashdata('message', lang("quantity_adjusted"));
            redirect('products/quantity_adjustments');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('add_adjustment')));
            $meta = array('page_title' => lang('add_adjustment_by_csv'), 'bc' => $bc);
            $this->page_construct('products/add_adjustment_by_csv', $meta, $this->data);
        }
    }

    function delete_adjustment($id = NULL) {
        $this->sma->checkPermissions('delete', TRUE);
        $adjustment = $this->products_model->getAdjustmentByID($id);
        $inv_items = $this->products_model->getAdjustmentItems($id);
        $DatalogArr = array('data' => $adjustment, 'products' => $inv_items);
        $DataLog = array(
            'action_type' => 'Delete',
            'product_id' => '',
            'quantity' => '',
            'action_reff_id' => $id,
            'action_affected_data' => json_encode($DatalogArr),
            'action_comment' => 'Delete adjustments',
        );
        if ($this->products_model->deleteAdjustment($id)) {
            $this->sma->setUserActionLog($DataLog);
            echo lang("adjustment_deleted");
        }
    }

    function modal_view($id = NULL) {
        $this->sma->checkPermissions('index', TRUE);

        if ($this->Owner || $this->Admin) {
            $warehouse_ids = [];
        } else {
            $user = $this->site->getUser();
            $warehouseId = $user->warehouse_id;
            $warehouse_ids = explode(",", $warehouseId);
        }
        $pr_details = $this->site->getProductByID($id);
        if (!$id || !$pr_details) {
            $this->session->set_flashdata('error', lang('prduct_not_found'));
            $this->sma->md();
        }
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $pr_details->code . '/' . $pr_details->barcode_symbology . '/40/0') . "' alt='" . $pr_details->code . "' class='pull-left' />";
        if ($pr_details->type == 'combo') {
            $this->data['combo_items'] = $this->products_model->getProductComboItems($id);
        }
        if ($pr_details->type == 'Bundle') {
            $this->data['combo_items'] = $this->products_model->getProductComboItems($id);
        }
        $this->data['product'] = $pr_details;
        $this->data['unit'] = $this->site->getUnitByID($pr_details->unit);
        $this->data['sale_unit'] = $this->site->getUnitByID($pr_details->sale_unit);
        $this->data['brand'] = $this->site->getBrandByID($pr_details->brand);
        $this->data['images'] = $this->products_model->getProductPhotos($id);
        $this->data['category'] = $this->site->getCategoryByID($pr_details->category_id);
        $this->data['subcategory'] = $pr_details->subcategory_id ? $this->site->getCategoryByID($pr_details->subcategory_id) : NULL;
        $this->data['tax_rate'] = $pr_details->tax_rate ? $this->site->getTaxRateByID($pr_details->tax_rate) : NULL;
        $this->data['warehouses'] = $this->products_model->getAllWarehousesWithPQ($id, $warehouse_ids);
        $this->data['options'] = $this->products_model->getProductOptionsWithWH($id);
        
        $this->data['variants'] = $this->products_model->getProductOptions($id);
        $this->data['purchase'] = $this->products_model->getProductStockDetails($id);
        // $this->data['Avgcost'] = $this->Transfers_model->getCostingVariants($id);
        $optionsArray = [];
        foreach ($this->data['options'] as $option) {
            $optionId = $option->id;
            if (!isset($optionsArray[$optionId])) {
                $optionsArray[$optionId] = (array) $option;
                $optionsArray[$optionId]['warehouses'] = []; // Initialize warehouse array
            }
            if (!empty($option->wh_name)) { // Check if warehouse name exists
                $optionsArray[$optionId]['warehouses'][] = [
                    'warehouse_name' => $option->wh_name,
                    'quantity'       => $option->wh_qty,
                ];
            }
        }
        $variantsArray = [];
        foreach ($this->data['variants'] as $variant) {
            if (!isset($variantsArray[$variant->id])) {
                $variantsArray[$variant->id] = (array) $variant;
            }
        }
        foreach ($optionsArray as &$option) {
            if (isset($variantsArray[$option['id']]) && isset($variantsArray[$option['id']]['mrp'])) {
                $option['variants_mrp'] = $variantsArray[$option['id']]['mrp']; 
            }
        }
        // $this->data['options'] = array_values(array_map(function ($option) {
        //     return (object) $option;
        // }, $optionsArray));
        $this->load->view($this->theme . 'products/modal_view', $this->data);
    }

    function view($id = NULL) {
        $this->sma->checkPermissions('index');

        $pr_details = $this->products_model->getProductByID($id);
        if (!$id || !$pr_details) {
            $this->session->set_flashdata('error', lang('prduct_not_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $pr_details->code . '/' . $pr_details->barcode_symbology . '/40/0') . "' alt='" . $pr_details->code . "' class='pull-left' />";
        if ($pr_details->type == 'combo') {
            $this->data['combo_items'] = $this->products_model->getProductComboItems($id);
        }
        if ($pr_details->type == 'Bundle') {
            $this->data['combo_items'] = $this->products_model->getProductComboItems($id);
        }
        $this->data['product'] = $pr_details;
        $this->data['unit'] = $this->site->getUnitByID($pr_details->unit);
        $this->data['brand'] = $this->site->getBrandByID($pr_details->brand);
        $this->data['images'] = $this->products_model->getProductPhotos($id);
        $this->data['category'] = $this->site->getCategoryByID($pr_details->category_id);
        $this->data['subcategory'] = $pr_details->subcategory_id ? $this->site->getCategoryByID($pr_details->subcategory_id) : NULL;
        $this->data['tax_rate'] = $pr_details->tax_rate ? $this->site->getTaxRateByID($pr_details->tax_rate) : NULL;
        $this->data['popup_attributes'] = $this->popup_attributes;
        $this->data['warehouses'] = $this->products_model->getAllWarehousesWithPQ($id);
        $this->data['options'] = $this->products_model->getProductOptionsWithWH($id);
        $this->data['variants'] = $this->products_model->getProductOptions($id);
        $this->data['sold'] = $this->products_model->getSoldQty($id);
        $this->data['purchased'] = $this->products_model->getPurchasedQtyStatus($id);
        $this->data['stocks'] = $this->products_model->getProductStockDetails($id);

        $cfields = $this->site->getCustomeFieldsLabel('product');
        $this->data['custome_fields'] = $cfields['product'];

        $this->data['id'] = $id;

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => $pr_details->name));
        $meta = array('page_title' => $pr_details->name, 'bc' => $bc);
        $this->page_construct('products/view', $meta, $this->data);
    }

    function pdf($id = NULL, $view = NULL) {
        $this->sma->checkPermissions('index');

        $pr_details = $this->products_model->getProductByID($id);
        if (!$id || !$pr_details) {
            $this->session->set_flashdata('error', lang('prduct_not_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
        $this->data['barcode'] = "<img src='" . site_url('products/gen_barcode/' . $pr_details->code . '/' . $pr_details->barcode_symbology . '/40/0') . "' alt='" . $pr_details->code . "' class='pull-left' />";
        if ($pr_details->type == 'combo') {
            $this->data['combo_items'] = $this->products_model->getProductComboItems($id);
        }
        if ($pr_details->type == 'Bundle') {
            $this->data['combo_items'] = $this->products_model->getProductComboItems($id);
        }
        $this->data['product'] = $pr_details;
        $this->data['unit'] = $this->site->getUnitByID($pr_details->unit);
        $this->data['brand'] = $this->site->getBrandByID($pr_details->brand);
        $this->data['images'] = $this->products_model->getProductPhotos($id);
        $this->data['category'] = $this->site->getCategoryByID($pr_details->category_id);
        $this->data['subcategory'] = $pr_details->subcategory_id ? $this->site->getCategoryByID($pr_details->subcategory_id) : NULL;
        $this->data['tax_rate'] = $pr_details->tax_rate ? $this->site->getTaxRateByID($pr_details->tax_rate) : NULL;
        $this->data['popup_attributes'] = $this->popup_attributes;
        $this->data['warehouses'] = $this->products_model->getAllWarehousesWithPQ($id);
        $this->data['options'] = $this->products_model->getProductOptionsWithWH($id);
        $this->data['variants'] = $this->products_model->getProductOptions($id);

        $name = $pr_details->code . '_' . str_replace('/', '_', $pr_details->name) . ".pdf";
        if ($view) {
            $this->load->view($this->theme . 'products/pdf', $this->data);
        } else {
            $html = $this->load->view($this->theme . 'products/pdf', $this->data, TRUE);
            if (!$this->Settings->barcode_img) {
                $html = preg_replace("'\<\?xml(.*)\?\>'", '', $html);
            }
            $this->sma->generate_pdf($html, $name);
        }
    }

    function getSubCategories($category_id = NULL) {
        if ($rows = $this->products_model->getSubCategories($category_id)) {
            $data = json_encode($rows);
        } else {
            $data = FALSE;
        }
        echo $data;
    }

    function getCategoryTaxrate($category_id = NULL) {
        if ($rows = $this->products_model->getCategoryTaxrate($category_id)) {
            echo $rows[0]->tax_rate;
        } else {
            $data = FALSE;
        }
        echo $data;
    }

    function product_actions($wh = NULL) {
        if (!$this->Owner && !$this->GP['bulk_actions']) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'sync_quantity') {

                    foreach ($_POST['val'] as $id) {
                        $this->site->syncQuantity(NULL, NULL, NULL, $id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("products_quantity_sync"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'fav_products') {
                    if ($this->products_model->productsMarkFavourite($_POST['val'])) {
                        $this->session->set_flashdata('message', $this->lang->line("Product Mark as Favourite"));
                    } else {
                        $this->session->set_flashdata('error', $this->lang->line("Please try again"));
                    }
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'delete') {

                    $this->sma->checkPermissions('delete');
                    foreach ($_POST['val'] as $id) {
                        $this->sma->storeDeletedData('products', 'id', $id);
                        $this->products_model->deleteProduct($id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("products_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'labels') {

                    foreach ($_POST['val'] as $id) {
                        $row = $this->products_model->getProductByID($id);
                        $selected_variants = FALSE;
                        if ($variants = $this->products_model->getProductOptions($row->id)) {
                            foreach ($variants as $variant) {
                                $selected_variants[$variant->id] = $variant->quantity > 0 ? 1 : 0;
                            }
                        }
                        $pr[$row->id] = array('id' => $row->id, 'label' => $row->name . " (" . $row->code . ")", 'code' => $row->code, 'name' => $row->name, 'price' => $row->price, 'qty' => $row->quantity, 'variants' => $variants, 'selected_variants' => $selected_variants);
                    }

                    $this->data['items'] = isset($pr) ? json_encode($pr) : FALSE;
                    $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
                    $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('print_barcodes')));
                    $meta = array('page_title' => lang('print_barcodes'), 'bc' => $bc);
                    $this->page_construct('products/print_barcodes', $meta, $this->data);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);
                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:Y1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:Y1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Products');
                    $this->excel->getActiveSheet()->setTitle('Products');

                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('name'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('code'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('barcode_symbology'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('HSN Code'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('brand'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('category_code'));
                    $this->excel->getActiveSheet()->SetCellValue('G2', lang('unit_code'));
                    $this->excel->getActiveSheet()->SetCellValue('H2', lang('sale') . ' ' . lang('unit_code'));
                    $this->excel->getActiveSheet()->SetCellValue('I2', lang('purchase') . ' ' . lang('unit_code'));
                    $this->excel->getActiveSheet()->SetCellValue('J2', lang('cost'));
                    $this->excel->getActiveSheet()->SetCellValue('K2', lang('price'));
                    $this->excel->getActiveSheet()->SetCellValue('L2', lang('mrp'));
                    $this->excel->getActiveSheet()->SetCellValue('M2', lang('alert_quantity'));
                    $this->excel->getActiveSheet()->SetCellValue('N2', lang('tax_rate'));
                    $this->excel->getActiveSheet()->SetCellValue('O2', lang('tax_method'));
                    $this->excel->getActiveSheet()->SetCellValue('P2', lang('image'));
                    $this->excel->getActiveSheet()->SetCellValue('Q2', lang('subcategory_code'));
                    $this->excel->getActiveSheet()->SetCellValue('R2', lang('product_variants'));
                    $this->excel->getActiveSheet()->SetCellValue('S2', lang('pcf1'));
                    $this->excel->getActiveSheet()->SetCellValue('T2', lang('pcf2'));
                    $this->excel->getActiveSheet()->SetCellValue('U2', lang('pcf3'));
                    $this->excel->getActiveSheet()->SetCellValue('V2', lang('pcf4'));
                    $this->excel->getActiveSheet()->SetCellValue('W2', lang('pcf5'));
                    $this->excel->getActiveSheet()->SetCellValue('X2', lang('pcf6'));
                    $this->excel->getActiveSheet()->SetCellValue('Y2', lang('quantity'));

                    $row = 3;
                    $total_quantity = 0;
                    foreach ($_POST['val'] as $id) {
                        $product = $this->products_model->getProductDetail($id);
                        $brand = $this->site->getBrandByID($product->brand);
                        if ($units = $this->site->getUnitsByBUID($product->unit)) {
                            foreach ($units as $u) {
                                if ($u->id == $product->unit) {
                                    $base_unit = $u->code;
                                }
                                if ($u->id == $product->sale_unit) {
                                    $sale_unit = $u->code;
                                }
                                if ($u->id == $product->purchase_unit) {
                                    $purchase_unit = $u->code;
                                }
                            }
                        } else {
                            $base_unit = '';
                            $sale_unit = '';
                            $purchase_unit = '';
                        }
                        $variants = $this->products_model->getProductOptions($id);
                        $product_variants = '';
                        if ($variants) {
                            foreach ($variants as $variant) {
                                $product_variants .= trim($variant->name) . '|';
                            }
                        }
                        $quantity = $product->quantity;
                        if ($wh) {
                            if ($wh_qty = $this->products_model->getProductQuantity($id, $wh)) {
                                $quantity = $wh_qty['quantity'];
                            } else {
                                $quantity = 0;
                            }
                        }
                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $product->name);
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $product->code);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $product->barcode_symbology);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $product->hsn_code);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, ($brand ? $brand->name : ''));
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $product->category_code);
                        $this->excel->getActiveSheet()->SetCellValue('G' . $row, $base_unit);
                        $this->excel->getActiveSheet()->SetCellValue('H' . $row, $sale_unit);
                        $this->excel->getActiveSheet()->SetCellValue('I' . $row, $purchase_unit);
                        if ($this->Owner || $this->Admin || $this->session->userdata('show_cost')) {
                            $this->excel->getActiveSheet()->SetCellValue('J' . $row, $product->cost);
                        }
                        if ($this->Owner || $this->Admin || $this->session->userdata('show_price')) {
                            $this->excel->getActiveSheet()->SetCellValue('K' . $row, $product->price);
                            $this->excel->getActiveSheet()->SetCellValue('L' . $row, $product->mrp);
                        }

                        $this->excel->getActiveSheet()->SetCellValue('M' . $row, $product->alert_quantity);
                        $this->excel->getActiveSheet()->SetCellValue('N' . $row, $product->tax_rate_name);
                        $this->excel->getActiveSheet()->SetCellValue('O' . $row, $product->tax_method ? lang('exclusive') : lang('inclusive'));
                        $this->excel->getActiveSheet()->SetCellValue('P' . $row, $product->image);
                        $this->excel->getActiveSheet()->SetCellValue('Q' . $row, $product->subcategory_code);
                        $this->excel->getActiveSheet()->SetCellValue('R' . $row, $product_variants);
                        $this->excel->getActiveSheet()->SetCellValue('S' . $row, $product->cf1);
                        $this->excel->getActiveSheet()->SetCellValue('T' . $row, $product->cf2);
                        $this->excel->getActiveSheet()->SetCellValue('U' . $row, $product->cf3);
                        $this->excel->getActiveSheet()->SetCellValue('V' . $row, $product->cf4);
                        $this->excel->getActiveSheet()->SetCellValue('W' . $row, $product->cf5);
                        $this->excel->getActiveSheet()->SetCellValue('X' . $row, $product->cf6);
                        $this->excel->getActiveSheet()->SetCellValue('Y' . $row, $quantity);
                        $total_quantity += $quantity;

                        $row++;
                    }
                    $this->excel->getActiveSheet()->getStyle("y" . $row)->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_MEDIUM);
                    $this->excel->getActiveSheet()->SetCellValue('Y' . $row, $total_quantity);

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
                    $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('N')->setWidth(40);
                    $this->excel->getActiveSheet()->getColumnDimension('O')->setWidth(30);
                    $this->excel->getActiveSheet()->getColumnDimension('P')->setWidth(30);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'products_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
                        $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                        $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                        require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                        $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                        $rendererLibrary = 'MPDF';
                        $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                        if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                            die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                        }

                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                        return $objWriter->save('php://output');
                    }
                    if ($this->input->post('form_action') == 'export_excel') {
                        header('Content-Type: application/vnd.ms-excel');
                        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                        return $objWriter->save('php://output');
                    }

                    redirect($_SERVER["HTTP_REFERER"]);
                }
            } else {
                $this->session->set_flashdata('error', $this->lang->line("no_product_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    public function delete_image($id = NULL) {
        $this->sma->checkPermissions('edit', TRUE);
        if ($this->input->is_ajax_request()) {
            header('Content-Type: application/json');
            $id || die(json_encode(array('error' => 1, 'msg' => lang('no_image_selected'))));
            $this->db->delete('product_photos', array('id' => $id));
            die(json_encode(array('error' => 0, 'msg' => lang('image_deleted'))));
        }
        die(json_encode(array('error' => 1, 'msg' => lang('ajax_error'))));
    }

    public function getSubUnits($unit_id) {
        $unit = $this->site->getUnitByID($unit_id);
        if ($units = $this->site->getUnitsByBUID($unit_id)) {
            array_push($units, $unit);
        } else {
            $units = array($unit);
        }
        $this->sma->send_json($units);
    }

    public function qa_suggestions($warehouse_id = null) {

        $term = $this->input->get('term', TRUE);

        if (strlen($term) < 1 || !$term) {
            die("<script type='text/javascript'>setTimeout(function(){ window.top.location.href = '" . site_url('welcome') . "'; }, 10);</script>");
        }

        $analyzed = $this->sma->analyze_term($term);
        $sr = $analyzed['term'];
        $option_id = $analyzed['option_id'] ? $analyzed['option_id'] : 0;

        $rows = $this->products_model->getQASuggestions($sr, '50', $warehouse_id);

        $c = str_replace(".", "", microtime(true));
        $r = 0;
        if ($rows) {
            foreach ($rows as $row) {

                $batch = $productVariantsStocks = $options = FALSE;

                $product = $this->site->getProductByID($row->id);

                $row->qty = 1;
                $row->storage_type = $product->storage_type;
                $row->primary_variant = FALSE;
                $row->tax_rate_id = $product->tax_rate;
                $row->tax_method = $product->tax_method;
                $row->product_type = $product->type;
                $row->unit = $product->purchase_unit;
                $row->hsn_code = $product->hsn_code;
                $row->image = $product->image;
                $row->serial = '';
                $row->batch = '';
                $row->batch_number = '';
                $row->batch_stocks = 0;
                $row->item_stock = $product->quantity;
                $row->quantity = $product->quantity;
                $row->cost = $supplier_id ? $this->getSupplierCost($supplier_id, $row) : $product->cost;
                $row->price = $product->price;
                $row->real_unit_cost = $row->cost;
                $row->base_unit_cost = $row->cost;
                $row->mrp = $product->mrp;
                $row->expiry = '';

                $options = $this->products_model->getProductOptions($row->id);

                if ($options != FALSE && $option_id && !isset($options[$option_id])) {
                    continue;
                } elseif ($options != FALSE && !$option_id) {
                    if ($product->primary_variant) {
                        $option_id = $product->primary_variant;
                        $row->primary_variant = $product->primary_variant;
                    } else {
                        $optionFirstKey = key($options);
                        $option_id = $options[$optionFirstKey]->id;
                    }
                }

                $productVariantsStocks = FALSE;
                $batchStocks = TRUE;

                if ($batchStocks) {
                    $productVariantsStocks = $this->site->getWarehouseProductStocks($warehouse_id, $product->id, $batchStocks);
                }

                if ($product->storage_type == 'packed' && $options !== FALSE) {
                    foreach ($options as $key => $optionData) {
                        $option_key = $product->id . '_' . $optionData->id;
                        $optionData->quantity = isset($productVariantsStocks[$option_key]['variant_stocks']) ? $productVariantsStocks[$option_key]['variant_stocks'] : 0;
                        unset($options[$key]);
                        $options[$optionData->id] = $optionData;
                    }
                }

                /**
                 * Batch Config
                 * */
                if ($this->Settings->product_batch_setting > 0) {

                    $batch_option = ($options != FALSE && $product->storage_type == 'packed') ? $option_id : 0;

                    $productbatches = $this->products_model->getProductVariantsBatch($product->id);

                    if (is_array($productbatches) && $batchStocks) {

                        if ($productVariantsStocks != FALSE) {
                            foreach ($productbatches as $variant_id => $batchData) {
                                $product_option_key = $product->id . '_' . $variant_id;
                                $batchStock = $productVariantsStocks[$product_option_key]['batch_stocks'];
                                foreach ($batchData as $batch_id => $batch) {
                                    $batch->stocks = isset($batchStock[$batch->batch_no]) ? $batchStock[$batch->batch_no] : 0;
                                    $productbatches[$variant_id][$batch_id] = $batch;
                                }
                            }
                        }

                        $current_batch = $productbatches[$batch_option];

                        if ($current_batch) {
                            $firstKey = key($current_batch);
                            $batchoption = $current_batch;
                            $row->batch = $batchoption[$firstKey]->id;
                            $row->batch_number = $batchoption[$firstKey]->batch_no;
                            $row->batch_stocks = isset($batchoption[$firstKey]->stocks) ? $batchoption[$firstKey]->stocks : 0;
                            $row->cost = $batchoption[$firstKey]->cost;
                            $row->price = $batchoption[$firstKey]->price ? $batchoption[$firstKey]->price : $product->price;
                            $row->real_unit_cost = $batchoption[$firstKey]->cost;
                            $row->base_unit_cost = $batchoption[$firstKey]->cost;
                            $row->expiry = ($batchoption[$firstKey]->expiry != '' && $batchoption[$firstKey]->expiry !== '0000-00-00') ? $batchoption[$firstKey]->expiry : '';
                        }
                    }
                }
                /**
                 * End Batch Configs
                 * */
                $row->option = ($options != FALSE && $product->storage_type == 'packed') ? $option_id : 0;

                $product_option_key = $product->id . '_' . $row->option;

                $row->item_stock = ($productVariantsStocks != FALSE && isset($productVariantsStocks[$product_option_key]['variant_stocks'])) ? $productVariantsStocks[$product_option_key]['variant_stocks'] : $row->item_stock;

                $row_id = $row->id . $row->option;

                $pr[] = ['id' => ($c + $r), 'item_id' => $row_id, 'image' => $product->image, 'label' => $row->name . " (" . $row->code . ")", 'batchs' => $current_batch, 'options' => $options, 'option_batches' => $productbatches, 'row' => $row];

                $r++;
            }
            $this->sma->send_json($pr);
        } else {
            $this->sma->send_json(array(array('id' => 0, 'label' => lang('no_match_found'), 'value' => $term)));
        }
    }

    function adjustment_actions() {
        if (!$this->Owner && !$this->GP['bulk_actions']) {
            $this->session->set_flashdata('warning', lang('access_denied'));
            redirect($_SERVER["HTTP_REFERER"]);
        }

        $this->form_validation->set_rules('form_action', lang("form_action"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if (!empty($_POST['val'])) {
                if ($this->input->post('form_action') == 'delete') {

                    $this->sma->checkPermissions('delete');
                    foreach ($_POST['val'] as $id) {
                        $adjustment = $this->products_model->getAdjustmentByID($id);
                        $inv_items = $this->products_model->getAdjustmentItems($id);
                        $DatalogArr = array('data' => $adjustment, 'products' => $inv_items);
                        $DataLog = array(
                            'action_type' => 'Delete',
                            'product_id' => '',
                            'quantity' => '',
                            'action_reff_id' => $id,
                            'action_affected_data' => json_encode($DatalogArr),
                            'action_comment' => 'Delete adjustments',
                        );
                        $this->sma->setUserActionLog($DataLog);
                        $this->products_model->deleteAdjustment($id);
                    }
                    $this->session->set_flashdata('message', $this->lang->line("adjustment_deleted"));
                    redirect($_SERVER["HTTP_REFERER"]);
                } elseif ($this->input->post('form_action') == 'export_excel' || $this->input->post('form_action') == 'export_pdf') {

                    $this->load->library('excel');
                    $this->excel->setActiveSheetIndex(0);

                    $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                    $this->excel->getActiveSheet()->getStyle("A1:F1")->applyFromArray($style);
                    $this->excel->getActiveSheet()->mergeCells('A1:F1');
                    $this->excel->getActiveSheet()->SetCellValue('A1', 'Quantity Adjustments');

                    $this->excel->getActiveSheet()->setTitle('quantity_adjustments');
                    $this->excel->getActiveSheet()->SetCellValue('A2', lang('date'));
                    $this->excel->getActiveSheet()->SetCellValue('B2', lang('reference_no'));
                    $this->excel->getActiveSheet()->SetCellValue('C2', lang('warehouse'));
                    $this->excel->getActiveSheet()->SetCellValue('D2', lang('created_by'));
                    $this->excel->getActiveSheet()->SetCellValue('E2', lang('note'));
                    $this->excel->getActiveSheet()->SetCellValue('F2', lang('items'));

                    $row = 3;
                    foreach ($_POST['val'] as $id) {
                        $adjustment = $this->products_model->getAdjustmentByID($id);
                        $created_by = $this->site->getUser($adjustment->created_by);
                        $warehouse = $this->site->getWarehouseByID($adjustment->warehouse_id);
                        $items = $this->products_model->getAdjustmentItems($id);
                        $products = '';
                        if ($items) {
                            foreach ($items as $item) {
                                $variant = isset($item->variant) ? '_' . $item->variant : '';
                                $products .= $item->product_name . $variant . '(' . $this->sma->formatQuantity($item->type == 'subtraction' ? -$item->quantity : $item->quantity) . ')' . "\n";
                            }
                        }

                        $this->excel->getActiveSheet()->SetCellValue('A' . $row, $this->sma->hrld($adjustment->date));
                        $this->excel->getActiveSheet()->SetCellValue('B' . $row, $adjustment->reference_no);
                        $this->excel->getActiveSheet()->SetCellValue('C' . $row, $warehouse[$adjustment->warehouse_id]->name);
                        $this->excel->getActiveSheet()->SetCellValue('D' . $row, $created_by->first_name . ' ' . $created_by->last_name);
                        $this->excel->getActiveSheet()->SetCellValue('E' . $row, $this->sma->decode_html($adjustment->note));
                        $this->excel->getActiveSheet()->SetCellValue('F' . $row, $products);
                        $row++;
                    }

                    $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
                    $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
                    $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(40);
                    $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
                    $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $filename = 'quantity_adjustments_' . date('Y_m_d_H_i_s');
                    if ($this->input->post('form_action') == 'export_pdf') {
                        $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                        $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                        $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                        require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                        $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                        $rendererLibrary = 'MPDF';
                        $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                        if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                            die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                        }

                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                        return $objWriter->save('php://output');
                    }
                    if ($this->input->post('form_action') == 'export_excel') {
                        $this->excel->getActiveSheet()->getStyle('E2:E' . $row)->getAlignment()->setWrapText(TRUE);
                        $this->excel->getActiveSheet()->getStyle('F2:F' . $row)->getAlignment()->setWrapText(TRUE);
                        header('Content-Type: application/vnd.ms-excel');
                        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                        header('Cache-Control: max-age=0');

                        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                        return $objWriter->save('php://output');
                    }

                    redirect($_SERVER["HTTP_REFERER"]);
                }
            } else {
                $this->session->set_flashdata('error', $this->lang->line("no_record_selected"));
                redirect($_SERVER["HTTP_REFERER"]);
            }
        } else {
            $this->session->set_flashdata('error', validation_errors());
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    function stock_counts($warehouse_id = NULL) {
        $this->sma->checkPermissions('stock_count');

        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        } else {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id == NULL ? $this->session->userdata('warehouse_id') : $warehouse_id;
            $this->data['warehouse'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('stock_counts')));
        $meta = array('page_title' => lang('stock_counts'), 'bc' => $bc);
        $this->page_construct('products/stock_counts', $meta, $this->data);
    }

    function getCounts($warehouse_id = NULL) {
        $FileType = $this->uri->segment(3);
        $warehouse_id = $this->uri->segment(4);
        $StockId = $_POST['val'];
     
        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }
        //echo $FileType.' warehouse '.$warehouse_id; exit;
        $this->sma->checkPermissions('stock_count', TRUE);
        if ($FileType == 'file') {
            $detail_link = anchor('products/view_count/$1', '<label class="label label-primary pointer">' . lang('details') . '</label>', 'class="tip" title="' . lang('details') . '" data-toggle="modal" data-target="#myModal"');

            $this->load->library('datatables');
            $this->datatables->select("{$this->db->dbprefix('stock_counts')}.id as id, date, reference_no, {$this->db->dbprefix('warehouses')}.name as wh_name, type, brand_names, category_names, initial_file, final_file")->from('stock_counts')->join('warehouses', 'warehouses.id=stock_counts.warehouse_id', 'left');
            if ($warehouse_id) {
                $getwarehouse = str_replace("_", ",", $warehouse_id);
                $this->datatables->where('warehouse_id IN (' . $getwarehouse . ')');
            }

            $this->datatables->add_column('Actions', '<div class="text-center">' . $detail_link . '</div>', "id");
            echo $this->datatables->generate();
        } else {
         
            $this->db->select("{$this->db->dbprefix('stock_counts')}.id as id, date, reference_no,{$this->db->dbprefix('warehouses')}.name as wh_name,  brand_names, category_names, initial_file, final_file,
            {$this->db->dbprefix('warehouses_products')}.product_id as product_id,
            {$this->db->dbprefix('products')}.cost as product_cost,
            {$this->db->dbprefix('products')}.price as product_price,
            {$this->db->dbprefix('products')}.mrp as product_mrp,
            {$this->db->dbprefix('stock_counts')}.type as type")
            ->from('stock_counts')
            ->join('warehouses', 'warehouses.id = stock_counts.warehouse_id', 'left')
            ->join('warehouses_products', 'warehouses_products.warehouse_id = warehouses.id', 'left')
            ->join('products', 'products.id = warehouses_products.product_id', 'left');
            
            if ($warehouse_id) {
                $getwarehouse = str_replace("_", ",", $warehouse_id);
                $this->db->where('warehouse_id IN (' . $getwarehouse . ')');
            }
            if (is_numeric($StockId)) {
                $this->db->where('stock_counts.id', $StockId);
            }else {
                $this->db->where_in('stock_counts.id', $StockId);
            }
            $this->db->group_by('stock_counts.id');  
            $this->db->order_by("date", "desc");
            $q = $this->db->get();
            if ($q->num_rows() > 0) {
                foreach (($q->result()) as $row) {
                    $data[] = $row;
                }
            } else {
                $data = NULL;
            }
            //print_r($data);
            if (!empty($data)) {
               
                $this->load->library('excel');
                $this->excel->setActiveSheetIndex(0);
                $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));

                $this->excel->getActiveSheet()->getStyle("A1:F1")->applyFromArray($style);
                $this->excel->getActiveSheet()->mergeCells('A1:F1');
                $this->excel->getActiveSheet()->SetCellValue('A1', 'Stock Counts');
                $this->excel->getActiveSheet()->setTitle(lang('Stock_Count_Report'));
                $this->excel->getActiveSheet()->SetCellValue('A2', lang('Date'));
                $this->excel->getActiveSheet()->SetCellValue('B2', lang('sale_reference'));
                $this->excel->getActiveSheet()->SetCellValue('C2', lang('Warehouse'));
                $this->excel->getActiveSheet()->SetCellValue('D2', lang('Type'));
                $this->excel->getActiveSheet()->SetCellValue('E2', lang('Brand'));
                $this->excel->getActiveSheet()->SetCellValue('F2', lang('Categories'));
                // $this->excel->getActiveSheet()->SetCellValue('G2', lang('cost'));
                // $this->excel->getActiveSheet()->SetCellValue('H2', lang('price'));
                // $this->excel->getActiveSheet()->SetCellValue('I2', lang('mrp'));

                $row = 3;

                foreach ($data as $data_row) {
                    $profit = $data_row->TotalSales - $data_row->TotalPurchase;
                    $this->excel->getActiveSheet()->SetCellValue('A' . $row, $data_row->date);
                    $this->excel->getActiveSheet()->SetCellValue('B' . $row, $data_row->reference_no);
                    $this->excel->getActiveSheet()->SetCellValue('C' . $row, $data_row->wh_name);
                    $this->excel->getActiveSheet()->SetCellValue('D' . $row, $data_row->type);
                    $this->excel->getActiveSheet()->SetCellValue('E' . $row, $data_row->brand_names);
                    $this->excel->getActiveSheet()->SetCellValue('F' . $row, $data_row->category_names);
                    // $this->excel->getActiveSheet()->SetCellValue('G' . $row, $data_row->product_cost);
                    // $this->excel->getActiveSheet()->SetCellValue('H' . $row, $data_row->product_price);
                    // $this->excel->getActiveSheet()->SetCellValue('I' . $row, $data_row->product_mrp);

                    $row++;
                }

                $this->excel->getActiveSheet()->getColumnDimension('A')->setWidth(35);
                $this->excel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
                $this->excel->getActiveSheet()->getColumnDimension('E')->setWidth(25);
                $this->excel->getActiveSheet()->getColumnDimension('F')->setWidth(25);

                $filename = 'stock_count_report';
                $this->excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                if ($this->input->post('form_action') == 'export_pdf') {
                    $styleArray = array('borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)));
                    $this->excel->getDefaultStyle()->applyFromArray($styleArray);
                    $this->excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
                    require_once(APPPATH . "third_party" . DIRECTORY_SEPARATOR . "MPDF" . DIRECTORY_SEPARATOR . "mpdf.php");
                    $rendererName = PHPExcel_Settings::PDF_RENDERER_MPDF;
                    $rendererLibrary = 'MPDF';
                    $rendererLibraryPath = APPPATH . 'third_party' . DIRECTORY_SEPARATOR . $rendererLibrary;
                    if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
                        die('Please set the $rendererName: ' . $rendererName . ' and $rendererLibraryPath: ' . $rendererLibraryPath . ' values' . PHP_EOL . ' as appropriate for your directory structure');
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
                    header('Cache-Control: max-age=0');

                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'PDF');
                    $objWriter->save('php://output');
                    exit();
                }
                if ($this->input->post('form_action') == 'export_excel') {
                    $this->excel->getActiveSheet()->getStyle('C2:G' . $row)->getAlignment()->setWrapText(TRUE);
                    ob_clean();
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
                    header('Cache-Control: max-age=0');
                    ob_clean();
                    $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
                    $objWriter->save('php://output');
                    exit();
                }
            }
            $this->session->set_flashdata('error', lang('nothing_found'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    function view_count($id) {
        $this->sma->checkPermissions('stock_count', TRUE);
        $stock_count = $this->products_model->getStouckCountByID($id);
        if (!$stock_count->finalized) {
            $this->sma->md('products/finalize_count/' . $id);
        }

        $this->data['stock_count'] = $stock_count;
        $this->data['stock_count_items'] = $this->products_model->getStockCountItems($id);
        $this->data['warehouse'] = $this->site->getWarehouseByID($stock_count->warehouse_id);
        $this->data['adjustment'] = $this->products_model->getAdjustmentByCountID($id);
        $this->load->view($this->theme . 'products/view_count', $this->data);
    }

    function count_stock($page = NULL) {
        $this->sma->checkPermissions('stock_count');
        $this->form_validation->set_rules('warehouse', lang("warehouse"), 'required');
        $this->form_validation->set_rules('type', lang("type"), 'required');

        if ($this->form_validation->run() == TRUE) {

            $warehouse_id = $this->input->post('warehouse');
            $type = $this->input->post('type');
            $categories = $this->input->post('category') ? $this->input->post('category') : NULL;
            $brands = $this->input->post('brand') ? $this->input->post('brand') : NULL;
            $this->load->helper('string');
            $name = random_string('md5') . '.csv';
            $products = $this->products_model->getStockCountProducts($warehouse_id, $type, $categories, $brands);
            $pr = 0;
            $rw = 0;
            foreach ($products as $product) {
                if ($variants = $this->products_model->getStockCountProductVariants($warehouse_id, $product->id)) {
                    foreach ($variants as $variant) {
                        $items[] = array('product_code' => $product->code, 'product_name' => $product->name, 'variant' => $variant->name, 'expected' => $variant->quantity, 'counted' => '', 'cost' => $product->cost, 'price' => $product->price, 'mrp' => $product->mrp);
                        $rw++;
                    }
                } else {
                    $items[] = array('product_code' => $product->code, 'product_name' => $product->name, 'variant' => '', 'expected' => $product->quantity, 'counted' => '', 'cost' => $product->cost, 'price' => $product->price, 'mrp' => $product->mrp);
                    $rw++;
                }
                $pr++;
            }
            if (!empty($items)) {
                $csv_file = fopen('./files/'.$this->Customer_assets.'/' . $name, 'w');
                fputcsv($csv_file, array(lang('product_code'), lang('product_name'), lang('variant'), lang('expected'), lang('counted'), lang('cost'), lang('price'), lang('mrp')));
                foreach ($items as $item) {
                    fputcsv($csv_file, $item);
                }
                // file_put_contents('./files/'.$name, $csv_file);
                // fwrite($csv_file, $txt);
                fclose($csv_file);
            } else {
                $this->session->set_flashdata('error', lang('no_product_found'));
                redirect($_SERVER["HTTP_REFERER"]);
            }

            if ($this->Owner || $this->Admin) {
                $date = $this->sma->fld($this->input->post('date'));
            } else {
                $date = date('Y-m-d H:s:i');
            }
            $category_ids = '';
            $brand_ids = '';
            $category_names = '';
            $brand_names = '';
            if ($categories) {
                $r = 1;
                $s = sizeof($categories);
                foreach ($categories as $category_id) {
                    $category = $this->site->getCategoryByID($category_id);
                    if (!empty($category)) {
                        if ($r == $s) {
                            $category_names .= $category->name;
                            $category_ids .= $category->id;
                        } else {
                            $category_names .= $category->name . ', ';
                            $category_ids .= $category->id . ', ';
                        }
                        $r++;
                    }
                }
            }
            if ($brands) {
                $r = 1;
                $s = sizeof($brands);
                foreach ($brands as $brand_id) {
                    $brand = $this->site->getBrandByID($brand_id);
                    if (!empty($brand)) {
                        if ($r == $s) {
                            $brand_names .= $brand->name;
                            $brand_ids .= $brand->id;
                        } else {
                            $brand_names .= $brand->name . ', ';
                            $brand_ids .= $brand->id . ', ';
                        }
                        $r++;
                    }
                }
            }
            $data = array('date' => $date, 'warehouse_id' => $warehouse_id, 'reference_no' => $this->input->post('reference_no'), 'type' => $type, 'categories' => $category_ids, 'category_names' => $category_names, 'brands' => $brand_ids, 'brand_names' => $brand_names, 'initial_file' => $name, 'products' => $pr, 'rows' => $rw, 'created_by' => $this->session->userdata('user_id'));
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->addStockCount($data)) {
            $this->session->set_flashdata('message', lang("stock_count_intiated"));
            redirect('products/stock_counts');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['categories'] = $this->site->getAllCategories();
            $this->data['brands'] = $this->site->getAllBrands();
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => '#', 'page' => lang('count_stock')));
            $meta = array('page_title' => lang('count_stock'), 'bc' => $bc);
            $this->page_construct('products/count_stock', $meta, $this->data);
        }
    }

    function finalize_count($id) {
        $this->sma->checkPermissions('stock_count');
        $stock_count = $this->products_model->getStouckCountByID($id);
        if (!$stock_count || $stock_count->finalized) {
            $this->session->set_flashdata('error', lang("stock_count_finalized"));
            redirect('products/stock_counts');
        }

        $this->form_validation->set_rules('count_id', lang("count_stock"), 'required');

        if ($this->form_validation->run() == TRUE) {

            if ($_FILES['csv_file']['size'] > 0) {
                $note = $this->sma->clear_tags($this->input->post('note'));
                $data = array('updated_by' => $this->session->userdata('user_id'), 'updated_at' => date('Y-m-d H:s:i'), 'note' => $note);

                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = 'csv';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = TRUE;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('csv_file')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    redirect($_SERVER["HTTP_REFERER"]);
                }

                $csv = $this->upload->file_name;

                $arrResult = array();
                $handle = fopen($this->digital_upload_path . $csv, "r");
                if ($handle) {
                    while (($row = fgetcsv($handle, 5000, ",")) !== FALSE) {
                        $arrResult[] = $row;
                    }
                    fclose($handle);
                }
                $titles = array_shift($arrResult);
                $keys = array('product_code', 'product_name', 'product_variant', 'expected', 'counted');
                $final = array();
                foreach ($arrResult as $key => $value) {
                    $final[] = array_combine($keys, $value);
                }
                //$this->sma->print_arrays($final);
                $rw = 2;
                $differences = 0;
                $matches = 0;
                foreach ($final as $pr) {
                    if ($product = $this->products_model->getProductByCode(trim($pr['product_code']))) {
                        $pr['counted'] = !empty($pr['counted']) ? $pr['counted'] : 0;
                        if ($pr['expected'] == $pr['counted']) {
                            $matches++;
                        } else {
                            $pr['stock_count_id'] = $id;
                            $pr['product_id'] = $product->id;
                            $pr['cost'] = $product->cost;
                            $pr['product_variant_id'] = empty($pr['product_variant']) ? NULL : $this->products_model->getProductVariantID($pr['product_id'], $pr['product_variant']);
                            $products[] = $pr;
                            $differences++;
                        }
                    } else {
                        $this->session->set_flashdata('error', lang('check_product_code') . ' (' . $pr['product_code'] . '). ' . lang('product_code_x_exist') . ' ' . lang('line_no') . ' ' . $rw);
                        redirect('products/finalize_count/' . $id);
                    }
                    $rw++;
                }

                $data['final_file'] = $csv;
                $data['differences'] = $differences;
                $data['matches'] = $matches;
                $data['missing'] = $stock_count->rows - ($rw - 2);
                $data['finalized'] = 1;
            }

            // $this->sma->print_arrays($data, $products);
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->finalizeStockCount($id, $data, $products)) {
            $this->session->set_flashdata('message', lang("stock_count_finalized"));
            redirect('products/stock_counts');
        } else {

            $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));
            $this->data['stock_count'] = $stock_count;
            // $this->data['warehouse'] = $this->site->getWarehouseByID($stock_count->warehouse_id);
            $this->data['warehouse'] = $this->site->getWarehouseBy_ID($stock_count->warehouse_id);
            $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => site_url('products'), 'page' => lang('products')), array('link' => site_url('products/stock_counts'), 'page' => lang('stock_counts')), array('link' => '#', 'page' => lang('finalize_count')));
            $meta = array('page_title' => lang('finalize_count'), 'bc' => $bc);
            $this->page_construct('products/finalize_count', $meta, $this->data);
        }
    }

    /* -------------------------------- Code Start for making  product  feature ------------- */

    // ----------------- function Set Favourite status 	-------------------------//

    function favourite() {
        $product_id = $this->input->get('product_id');
        if ($product_id) {
            $this->products_model->setFavourites($product_id);
            $this->session->set_flashdata('message', lang("product_fav_mark"));
        }
        return redirect($_SERVER['HTTP_REFERER']);
    }

    // ----------------- function unset Favourite status 

    function Refavourite() {
        $product_id = $this->input->get('product_id');
        if ($product_id) {
            $this->products_model->unsetFavourites($product_id);
            $this->session->set_flashdata('message', lang("product_unfav_mark"));
        }
        return redirect($_SERVER['HTTP_REFERER']);
    }

    //--------- Favourite product list--------------//

    function list_favourite($warehouse_id = NULL) {
        $this->sma->checkPermissions();
        $this->data['error'] = (validation_errors()) ? validation_errors() : $this->session->flashdata('error');
        if ($this->Owner || $this->Admin || !$this->session->userdata('warehouse_id')) {
            $this->data['warehouses'] = $this->site->getAllWarehouses();
            $this->data['warehouse_id'] = $warehouse_id;
            $this->data['warehouse'] = $warehouse_id ? $this->site->getWarehouseByID($warehouse_id) : NULL;
        } else {
            $this->data['warehouses'] = NULL;
            $this->data['warehouse_id'] = $this->session->userdata('warehouse_id');
            $this->data['warehouse'] = $this->session->userdata('warehouse_id') ? $this->site->getWarehouseByID($this->session->userdata('warehouse_id')) : NULL;
        }

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('List_Favourite_Products')));
        $meta = array('page_title' => lang('List_Favourite_Products'), 'bc' => $bc);
        $this->page_construct('products/list_favourite', $meta, $this->data);
    }

    //--------- Favourite product list CallBack Function--------------//
    function getFavProducts($warehouse_id = NULL) {
        $this->sma->checkPermissions('index', TRUE);

        if ((!$this->Owner || !$this->Admin) && !$warehouse_id) {
            $user = $this->site->getUser();
            $warehouse_id = $user->warehouse_id;
        }
        $detail_link = anchor('products/view/$1', '<i class="fa fa-file-text-o"></i> ' . lang('product_details'));
        $delete_link = "<a href='#' class='tip po' title='<b>" . $this->lang->line("delete_product") . "</b>' data-content=\"<p>" . lang('r_u_sure') . "</p><a class='btn btn-danger po-delete1' id='a__$1' href='" . site_url('products/delete/$1') . "'>" . lang('i_m_sure') . "</a> <button class='btn po-close'>" . lang('no') . "</button>\"  rel='popover'><i class=\"fa fa-trash-o\"></i> " . lang('delete_product') . "</a>";
        $single_barcode = anchor('products/print_barcodes/$1', '<i class="fa fa-print"></i> ' . lang('print_barcode_label'));

        $set_fav_link = "<a  id='a__$1' href='" . site_url('products/favourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('add_favourite') . "</a>";
        $unset_fav_link = "<a  id='a__$1' href='" . site_url('products/Refavourite/') . "?product_id=$1'><i class=\"fa fa-star\"></i> " . lang('Remove_Favourite') . "</a>";

        // $single_label = anchor_popup('products/single_label/$1/' . ($warehouse_id ? $warehouse_id : ''), '<i class="fa fa-print"></i> ' . lang('print_label'), $this->popup_attributes);
        $action = '<div class="text-center"><div class="btn-group text-left">' . '<button type="button" class="btn btn-default btn-xs btn-primary dropdown-toggle" data-toggle="dropdown">' . lang('actions') . ' <span class="caret"></span></button>
		<ul class="dropdown-menu pull-right" role="menu">
			<li>' . $detail_link . '</li>';

        $action .= ' 
			 
                        <li class="add_fav_link">' . $set_fav_link . '</li><li  class="remove_fav_link">' . $unset_fav_link . '</li>
			 
			</ul>
		</div></div>';
        $this->load->library('datatables');
        if ($warehouse_id) {
            $this->datatables->select($this->db->dbprefix('products') . ".id as productid, {$this->db->dbprefix('products')}.image as image, {$this->db->dbprefix('products')}.code as code, {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand, {$this->db->dbprefix('categories')}.name as cname, cost as cost, price as price, COALESCE(wp.quantity, 0) as quantity, {$this->db->dbprefix('units')}.code as unit, wp.rack as rack, alert_quantity,is_featured", FALSE)->from('products');
            if ($this->Settings->display_all_products) {
                $this->datatables->join("( SELECT product_id, quantity, rack,warehouse_id  from {$this->db->dbprefix('warehouses_products')} WHERE warehouse_id = {$warehouse_id} ) wp", 'products.id=wp.product_id', 'left');
                $this->datatables->where('wp.warehouse_id is  not  null'); // update by SW on 28-02-2017
            } else {
                $this->datatables->join('warehouses_products wp', 'products.id=wp.product_id', 'left')->where('wp.warehouse_id', $warehouse_id)->where('wp.quantity !=', 0);
            }
            $this->datatables->join('categories', 'products.category_id=categories.id', 'left')->join('units', 'products.unit=units.id', 'left')->join('brands', 'products.brand=brands.id', 'left');
            // ->group_by("products.id");
        } else {
            $this->datatables->select($this->db->dbprefix('products') . ".id as productid, {$this->db->dbprefix('products')}.image as image, {$this->db->dbprefix('products')}.code as code, {$this->db->dbprefix('products')}.name as name, {$this->db->dbprefix('brands')}.name as brand, {$this->db->dbprefix('categories')}.name as cname, cost as cost, price as price, COALESCE(quantity, 0) as quantity, {$this->db->dbprefix('units')}.code as unit, '' as rack, alert_quantity, {$this->db->dbprefix('products')}.is_featured", FALSE)->from('products')->join('categories', 'products.category_id=categories.id', 'left')->join('units', 'products.unit=units.id', 'left')->join('brands', 'products.brand=brands.id', 'left')->group_by("products.id");
        }
        if (!$this->Owner && !$this->Admin) {
            if (!$this->session->userdata('show_cost')) {
                $this->datatables->unset_column("cost");
            }
            if (!$this->session->userdata('show_price')) {
                $this->datatables->unset_column("price");
            }
        }
        $this->datatables->where('products.is_featured =', 1);
        $this->datatables->add_column("Actions", $action, "productid, image, code, name");
        echo $this->datatables->generate();
    }

    /* -------------------------------- Code END for making  product  feature ------------- */

    /* --------------------------------- Product List -------------------------------------- */

    function product_list($warehouse_id = NULL) {
        $rows = $this->products_model->get_product_list($warehouse_id);
        echo json_encode($rows);
    }

    function warehouseproduct_list($warehouse_id = NULL) {
        $rows = $this->products_model->get_warehousesproduct_list($warehouse_id);
        echo json_encode($rows);
    }

    /* ---------------------------------- End Product List ---------------------------------- */

    function get_variant_details() {
        $VarientId = $this->input->get('VarientId');
        $ProductId = $this->input->get('ProductId');
        $WarehouseId = $this->input->get('WarehouseId');
        $rows = $this->products_model->getVariantDetails($VarientId, $ProductId, $WarehouseId);
        echo json_encode($rows);
    }

    /**
     * This methods usign export csv update product price
     * @return type
     */
    public function exportcsvupdateprice() {
        $getproduct = $this->products_model->getAllProducts();

        $this->load->library('excel');
        $this->excel->setActiveSheetIndex(0);
        $style = array('alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,), 'font' => array('name' => 'Arial', 'color' => array('rgb' => 'FF0000')), 'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_NONE, 'color' => array('rgb' => 'FF0000'))));
        $this->excel->getActiveSheet()->SetCellValue('A1', 'Code');
        $this->excel->getActiveSheet()->SetCellValue('B1', 'Product Name');
        $this->excel->getActiveSheet()->SetCellValue('C1', 'Article Code');
        $this->excel->getActiveSheet()->SetCellValue('D1', 'Price');
        $this->excel->getActiveSheet()->SetCellValue('E1', 'MRP');
        if ($this->Settings->pos_type == 'restaurant' || $this->Settings->pos_type=='bakery' || $this->Settings->pos_type=='sweets') {
            $this->excel->getActiveSheet()->SetCellValue('F1', 'UP_Price');
            $this->excel->getActiveSheet()->SetCellValue('G1', 'Variants_Name');
            $this->excel->getActiveSheet()->SetCellValue('H1', 'Variants_Price');
        } else {
            $this->excel->getActiveSheet()->SetCellValue('F1', 'Variants_Name');
            $this->excel->getActiveSheet()->SetCellValue('G1', 'Variants_Mrp');
            $this->excel->getActiveSheet()->SetCellValue('H1', 'Variants_Price');
            $this->excel->getActiveSheet()->SetCellValue('I1', 'Discount_On_Variants_Mrp');
        }

        $row = 2;
        foreach ($getproduct as $product_val) {
            $variants = $this->products_model->getProductOptions($product_val->id);
            $product_variants = '';
            $product_price = '';
            $variant_mrp = '';
            $variant_discount_on_mrp = '';
            if ($variants) {
                foreach ($variants as $variant) {
                    // Check if data exists for price and append accordingly
                    if (!empty($variant->name)) {
                        $product_variants .= trim($variant->name) . ', ';
                    }
                    if (!empty($variant->price)) {
                        $product_price .= $variant->price . ', ';
                    }
                    if (!empty($variant->mrp)) {
                        $variant_mrp .= $variant->mrp . ', ';
                    }
                    if (!empty($variant->variant_discount_on_mrp)) {
                        $variant_discount_on_mrp .= $variant->variant_discount_on_mrp . ', ';
                    }
                }
                // Remove trailing commas
                $product_variants = rtrim($product_variants, ', ');
                $product_price = rtrim($product_price, ', ');
                $variant_mrp = rtrim($variant_mrp, ', ');
                $variant_discount_on_mrp = rtrim($variant_discount_on_mrp, ', ');
                
            }
            $this->excel->getActiveSheet()->getCellByColumnAndRow('A', $row)->setValueExplicit($product_val->code, PHPExcel_Cell_DataType::TYPE_STRING);
            $this->excel->getActiveSheet()->SetCellValue('B' . $row, $product_val->name);
            $this->excel->getActiveSheet()->SetCellValue('C' . $row, $product_val->article_code);
            $this->excel->getActiveSheet()->SetCellValue('D' . $row, $product_val->price);
            $this->excel->getActiveSheet()->SetCellValue('E' . $row, $product_val->mrp);
            if ($this->Settings->pos_type == 'restaurant' || $this->Settings->pos_type=='bakery' || $this->Settings->pos_type=='sweets') {
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $product_val->up_price);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $product_variants);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $product_price);
            } else {
                $this->excel->getActiveSheet()->SetCellValue('F' . $row, $product_variants);
                $this->excel->getActiveSheet()->SetCellValue('G' . $row, $variant_mrp);
                $this->excel->getActiveSheet()->SetCellValue('H' . $row, $product_price);
                $this->excel->getActiveSheet()->SetCellValue('I' . $row, $variant_discount_on_mrp);
            }
            $row++;
        }

        $filename = 'update_product_price_' . date('Y_m_d_H_i_s');

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
        return $objWriter->save('php://output');
    }

    /**
     * This method using bulk Product Images
     */
    public function bulk_images() {
        $this->form_validation->set_rules('userxls', lang("upload_file"), 'xss_clean');
        $this->form_validation->set_rules('userfile', lang("upload_file"), 'xss_clean');

        if ($this->form_validation->run() == TRUE) {

            if (isset($_FILES["userxls"])) {
                $this->load->library('upload');
                $config['upload_path'] = $this->digital_upload_path;
                $config['allowed_types'] = 'xls';
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = TRUE;
                $config['encrypt_name'] = TRUE;
                $config['max_filename'] = 25;

                $this->load->library('excel');
                $File = $_FILES['userxls']['tmp_name'];
                $inputFileType = PHPExcel_IOFactory::identify($File);
                $reader = PHPExcel_IOFactory::createReader($inputFileType);
                $reader->setReadDataOnly(true);
                $path = $File;
                $excel = $reader->load($path);

                $sheet = $excel->getActiveSheet()->toArray(null, true, true, true);
                $arrayCount = count($sheet);
                $arrResult = array();
                for ($i = 2; $i <= $arrayCount; $i++) {
                    $arrResult[] = $sheet[$i];
                }



                $keys = array('code', 'Product_Name', 'Image', 'Gallery_1', 'Gallery_2', 'Gallery_3', 'Gallery_4', 'Gallery_5', 'Variants_Name', 'Variants_Images');
                $final = $csvdata = array();

                foreach ($arrResult as $key => $value) {
                    $csvdata[] = array_combine($keys, $value);
                }

                $rw = 2;
                $flashError = '';
                foreach ($csvdata as $csv_pr) {

                    if (!$this->products_model->getProductByCode(trim($csv_pr['code']))) {
                        $flashError[] = lang("check_product_code") . " (" . $csv_pr['code'] . "). " . lang("code_x_exist") . " " . lang("line_no") . " " . $rw;
                        $this->session->set_flashdata('error', join('<br/>', $flashError));
                        redirect('products');
                    } else {
                        $final[] = $csv_pr;
                    }
                    $rw++;
                }
                $this->products_model->bulkimageUpload($final);
            }




            /**
             * Bulk Product Images
             */
            if ($_FILES['userfile']['name'][0] != "") {
                $this->load->library('upload');
//                $this->upload_path = 'assets/uploads/products/';
//                $this->thumbs_path = 'assets/uploads/products/thumbs/';
                $config['upload_path'] = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size'] = $this->allowed_file_size;
                $config['overwrite'] = FALSE;
                $config['encrypt_name'] = FALSE;
                $config['max_filename'] = 25;
                $files = $_FILES;
                $cpt = count($_FILES['userfile']['name']);
                for ($i = 0; $i < $cpt; $i++) {

                    $_FILES['userfile']['name'] = $files['userfile']['name'][$i];
                    $_FILES['userfile']['type'] = $files['userfile']['type'][$i];
                    $_FILES['userfile']['tmp_name'] = $files['userfile']['tmp_name'][$i];
                    $_FILES['userfile']['error'] = $files['userfile']['error'][$i];
                    $_FILES['userfile']['size'] = $files['userfile']['size'][$i];

                    $this->upload->initialize($config);

                    if (!$this->upload->do_upload()) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        redirect("products/import_csv");
                    } else {

                        $pho = $this->upload->file_name;

                        $photos[] = $pho;

                        $this->load->library('image_lib');
                        $config['image_library'] = 'gd2';
                        $config['source_image'] = $this->upload_path . $pho;
                        $config['new_image'] = $this->thumbs_path . $pho;
                        $config['maintain_ratio'] = TRUE;
                        $config['width'] = $this->Settings->twidth;
                        $config['height'] = $this->Settings->theight;

                        $this->image_lib->initialize($config);

                        if (!$this->image_lib->resize()) {
                            echo $this->image_lib->display_errors();
                        }

                        if ($this->Settings->watermark) {
                            $this->image_lib->clear();
                            $wm['source_image'] = $this->upload_path . $pho;
                            $wm['wm_text'] = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                            $wm['wm_type'] = 'text';
                            $wm['wm_font_path'] = 'system/fonts/texb.ttf';
                            $wm['quality'] = '100';
                            $wm['wm_font_size'] = '16';
                            $wm['wm_font_color'] = '999999';
                            $wm['wm_shadow_color'] = 'CCCCCC';
                            $wm['wm_vrt_alignment'] = 'top';
                            $wm['wm_hor_alignment'] = 'right';
                            $wm['wm_padding'] = '10';
                            $this->image_lib->initialize($wm);
                            $this->image_lib->watermark();
                        }

                        $this->image_lib->clear();
                    }
                }
                $config = NULL;
            } else {
                $photos = NULL;
            }
            /**
             * End Bulk Product Image
             */
            $this->session->set_flashdata('message', 'Bulk Images has been Uploaded successfully!');
            redirect('products/import_csv');
        } else {
            $this->session->set_flashdata('error', (validation_errors() ? validation_errors() : $this->session->flashdata('error')));
            redirect('products/import_csv');
        }
    }

    /*     * Delete Varient* */

    function deleteVariant($id = NULL) {
        //$id = $this->input->get('id');
        //echo $id;exit;
        if ($this->products_model->deleteVarient($id)) {
            return true;
        } else {
            return false;
        }
        return false;
    }

    /* Products Production Batches */

    public function batches() {

        $this->sma->checkPermissions();

        $bc = array(array('link' => base_url(), 'page' => lang('home')), array('link' => '#', 'page' => lang('Products Batches')));
        $meta = array('page_title' => lang('Products Batches'), 'bc' => $bc);
        $this->page_construct('batch/batch_list', $meta, $this->data);
    }

    public function getBatcheslist() {

        $edit_link = $delete_link = '';
        if ($this->data['Owner'] || $this->data['GP']['products-edit_batch']) {
            $edit_link = anchor('products/edit_batch/$1', '<i class="fa fa-edit"></i> ', ' class="btn btn-primary"  data-toggle="modal" data-target="#myModal"');
        }
        if ($this->data['Owner'] || $this->data['GP']['products-delete_batch']) {
            $delete_link = " <a href='" . site_url('products/delete_batch/$1/$2') . "' class='btn btn-danger' onclick='return confirm_delete($2);' ><i class=\"fa fa-trash-o\"></i></a>";
        }
        $action = $edit_link . $delete_link;

        $this->load->library('datatables');
        $this->datatables
                ->select("sma_product_batches.id,sma_products.name as product_name,sma_product_variants.name as variant_name, sma_product_batches.batch_no,sma_product_batches.cost,sma_product_batches.price,sma_product_batches.mrp ")
                ->from('sma_product_batches')
                ->join('sma_products', 'sma_products.id = sma_product_batches.product_id', 'left')
                ->join('sma_product_variants', 'sma_product_variants.id = sma_product_batches.option_id', 'left');

        $this->datatables->add_column("Actions", $action, "sma_product_batches.id,sma_product_batches.batch_no");

        echo $this->datatables->generate();
    }

    public function get_product_batches($product_id = NULL, $option_id = 0) {

        if ($product_id != NULL) {

            $batches = $this->products_model->getProductBatch($product_id, $option_id);

            return $batches;
        }

        return FALSE;
    }

    public function ajaxBatchesRequest() {

        $ajaxAction = $_POST['ajaxAction'];

        if ($ajaxAction == 'getBatchesList') {

            $product_id = $_POST['product_id'];
            $option_id = $_POST['option_id'];

            $batches = $this->get_product_batches($product_id, $option_id);

            if ($batches) {
                echo '<table class="table table-bordered"><thead><tr><th>Variant</th><th>Batch Numbers</th><th>Cost</th><th>Price</th><th>MRP</th><th>Expiry Date</th><th>Action</th></tr></thead><tbody>';
                foreach ($batches as $key => $batch) {

                    if ($_POST['page'] == "view") {
                        $edit_link = '<td class="text-center">' . anchor('products/edit_batch/' . $batch->id, '<i class="fa fa-edit"></i> ', ' class="btn btn-primary"  data-toggle="modal" data-target="#myModal"');
                        $delete_link = " <a href='" . site_url('products/delete_batch/' . $batch->id . '/' . $batch->batch_no) . "' class='btn btn-danger' onclick='return confirm_delete(" . $batch->batch_no . ");' ><i class=\"fa fa-trash-o\"></i></a></td>";
                    } else {
                        $edit_link = '<td><a class="btn btn-primary btn-sm" onclick="edit_batch(' . $batch->id . ')"><i class="fa fa-edit"></i></a>';
                        $delete_link = " <a href='" . site_url('products/delete_batch/' . $batch->id . '/' . $batch->batch_no) . "' class='btn btn-danger btn-sm' onclick='return confirm_delete(" . $batch->batch_no . ");' ><i class=\"fa fa-trash-o\"></i></a></td>";
                    }

                    echo '<tr><td>' . $batch->variant_name . '</td><td>' . $batch->batch_no . '</td><td>' . $batch->cost . '</td><td>' . $batch->price . '</td><td>' . $batch->mrp . '</td><td>' . $batch->expiry_date . '</td>' . $edit_link . $delete_link . '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo "<p class='text-danger'>No Batches found for selected product.</p>";
            }
        }

        if ($ajaxAction == 'getVariantsList') {

            $product_id = $_POST['product_id'];
            $storageType = $this->products_model->getProductStorageType($product_id);
            $options = '<option value="0">-- NA --</option>';
            if ($storageType == 'packed') {
                $variants = $this->products_model->getProductOptions($product_id);
                if ($variants) {
                    $options = '<option value="0">--Select Variant--</option>';
                    if (is_array($variants)) {
                        foreach ($variants as $variant) {
                            $options .= '<option value="' . $variant->id . '">' . $variant->name . '</option>';
                        }
                    }
                }
            }

            echo $options;
        }

        if ($ajaxAction == 'getBatchData') {

            $id = $_POST['id'];

            $batch = $this->products_model->getProductBatchById($id);

            echo json_encode($batch[0]);
        }
    }

    public function add_batch() {

        $this->sma->checkPermissions();

        $this->form_validation->set_rules('products', lang("products_name"), 'trim|required'); //|alpha_numeric_spaces
        $this->form_validation->set_rules('batch_no', lang("batch_number"), 'trim|required'); //|alpha_numeric_spaces
        $this->form_validation->set_rules('cost', lang("cost"), 'trim|required|numeric'); //|alpha_numeric_spaces
        $this->form_validation->set_rules('price', lang("price"), 'trim|required|numeric'); //|alpha_numeric_spaces
        $this->form_validation->set_rules('mrp', lang("mrp"), 'trim|required|numeric'); //|alpha_numeric_spaces

        if ($this->form_validation->run() == TRUE) {

            $data = [
                'product_id' => $this->input->post('products'),
                'option_id' => $this->input->post('option_id'),
                'batch_no' => $this->input->post('batch_no'),
                'cost' => $this->input->post('cost'),
                'price' => $this->input->post('price'),
                'mrp' => $this->input->post('mrp'),
                'expiry_date' => $this->input->post('expiry_date'),
            ];
        } elseif ($this->input->post('add_batch')) {
            $this->session->set_flashdata('error', validation_errors());
            return redirect($_SERVER['HTTP_REFERER']);
        }
        $batch_no = trim($this->input->post('batch_no'));
        $product_id = $this->input->post('products');
        $option_id = $this->input->post('option_id');

        if ($batches = $this->get_product_batches($product_id, $option_id)) {

            foreach ($batches as $batch) {
                if ($batch->batch_no == $batch_no) {
                    $this->session->set_flashdata('error', 'Batch number ' . $batch_no . ' exists for selected product.');
                    return redirect($_SERVER['HTTP_REFERER']);
                    break;
                }
            }
        }

        if ($this->form_validation->run() == TRUE && $this->products_model->createBatch($data)) {
            $this->session->set_flashdata('message', lang("New Batch Number $batch_no Added."));
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->data['product_id'] = isset($_GET['p']) ? $_GET['p'] : '';
            $this->data['products'] = $this->products_model->getProducts();

            $this->load->view($this->theme . 'batch/add', $this->data);
        }
    }

    public function edit_batch($id) {

        $this->sma->checkPermissions();

        if ($this->input->post('submit_batch')) {

            $this->form_validation->set_rules('products', lang("products_name"), 'trim|required'); //|alpha_numeric_spaces
            $this->form_validation->set_rules('batch_no', lang("batch_number"), 'trim|required'); //|alpha_numeric_spaces
            $this->form_validation->set_rules('cost', lang("cost"), 'trim|required|numeric'); //|alpha_numeric_spaces
            $this->form_validation->set_rules('price', lang("price"), 'trim|required|numeric'); //|alpha_numeric_spaces
            $this->form_validation->set_rules('mrp', lang("mrp"), 'trim|required|numeric'); //|alpha_numeric_spaces

            if ($this->form_validation->run() == TRUE) {

                $data = [
                    'product_id' => $this->input->post('products'),
                    'option_id' => $this->input->post('option_id'),
                    'batch_no' => $this->input->post('batch_no'),
                    'cost' => $this->input->post('cost'),
                    'price' => $this->input->post('price'),
                    'mrp' => $this->input->post('mrp'),
                    'expiry_date' => $this->input->post('expiry_date'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                $this->session->set_flashdata('error', validation_errors());
                return redirect($_SERVER['HTTP_REFERER']);
            }
            $batch_no = trim($this->input->post('batch_no'));
            $edit_id = $this->input->post('edit_id');

            $product_id = $this->input->post('products');
            $option_id = $this->input->post('option_id');

            $batches = $this->get_product_batches($product_id, $option_id);
            foreach ($batches as $batch) {
                if ($batch->batch_no == $batch_no && $edit_id != $batch->id) {
                    $this->session->set_flashdata('error', 'Batch number ' . $batch_no . ' exists for selected product.');
                    return redirect($_SERVER['HTTP_REFERER']);
                    break;
                }
            }

            if ($this->form_validation->run() == TRUE && $this->products_model->updateBatch($data, $edit_id)) {
                $this->session->set_flashdata('message', lang("Batch Number $batch_no Updated."));
                return redirect($_SERVER['HTTP_REFERER']);
            }
        } else {

            $batch = $this->products_model->getProductBatchById($id);

            $this->data['products'] = $products = $this->products_model->getProductByID($batch[0]->product_id);
            $this->data['variant'] = ($batch[0]->option_id && $products->storage_type == 'packed') ? $this->products_model->getProductVariantByID($batch[0]->option_id) : NULL;

            $this->data['batchDetails'] = $batch[0];
            $this->load->view($this->theme . 'batch/edit', $this->data);
        }
    }

    public function delete_batch($id, $batch_no) {

        $this->sma->checkPermissions();

        if ($batchInUsed = $this->products_model->get_batch_in_used($batch_no)) {

            $this->session->set_flashdata('error', lang("Batch number $batch_no is in used. It Can not delete "));
            return redirect($_SERVER['HTTP_REFERER']);
        } else {

            $sql = $this->products_model->deleteBatch($id);

            if ($sql) {
                $this->session->set_flashdata('message', lang("Batch no $batch_no has been deleted successfuly. "));
                return redirect($_SERVER['HTTP_REFERER']);
            } else {
                $this->session->set_flashdata('error', lang("Batch no $batch_no not delete, Please try again. "));
                return redirect($_SERVER['HTTP_REFERER']);
            }
        }
    }

    /* End Products Production Batches */

    public function sync_product_stocks($product_id, $storage_type) {

        $this->sma->checkPermissions();

        $warehouses = $this->site->getAllActiveWarehouses();
        $variants = FALSE;
        $sync_status = FALSE;
        if ($storage_type == 'packed') {
            $variants = $this->site->getProductVariants($product_id);
        }

        if ($warehouses) {
            foreach ($warehouses as $warehouse) {

                $sync_status = $this->site->syncProductQty($product_id, $warehouse->id);

                if ($variants) {
                    foreach ($variants as $variant) {
                        $this->site->syncVariantQty($variant->id, $warehouse->id, $product_id);
                    }
                }

                // $this->site->syncProductBatchQty($batch_no, $product_id, $warehouse_id, $variant_id = 0 ) ;
            }
        }

        if ($sync_status) {
            $this->session->set_flashdata('message', lang('Product stocks sync successfully.'));
            redirect($_SERVER["HTTP_REFERER"]);
        } else {
            $this->session->set_flashdata('error', lang('Product stocks sync failed'));
            redirect($_SERVER["HTTP_REFERER"]);
        }
    }

    /*     * *****************************************************************
     * Purchases Notification 
     * ***************************************************************** */

    /**
     * Add New Product On Master POS
     */
    public function add_newproducts() {
        $productDetails = unserialize($this->input->post('productDetils'));

        foreach ($productDetails as $products) {
            // Store product unit
            $unitData = [
                'code' => $products->unit_code,
                'name' => $products->unit_name,
                'base_unit' => $products->unit_base_unit,
                'operator' => $products->unit_operator,
                'unit_value' => $products->unit_value,
                'operation_value' => $products->unit_operation_value,
            ];
            $unitID = $this->products_model->getUnitCheck($unitData);
            // End Product Unit
            // Store Category
            $category = ['code' => $products->category_code, 'name' => $products->category_name];
            $categoryId = $this->products_model->getCategoryCheck($category);
            // End Category
            // Store Subcategory
            $subcategoryId = NULL;
            if ($products->subcategory_id) {
                $subcategory = ['code' => $products->subcategories_code, 'name' => $products->subcategories_name, 'parent_id' => $categoryId];
                $subcategoryId = $this->products_model->getCategoryCheck($subcategory);
            }
            // End Subcategory
            // Store Brand
            $brandId = NULL;
            if ($products->brand) {
                $brands = ['code' => $products->brand_code, 'name' => $products->brand_name];
                $brandId = $this->products_model->getBrandCheck($brands);
            }
            // End Brand
            // Store New Products
            $fielddata = [
                'code' => $products->code,
                'article_code' => $products->article_code,
                'name' => $products->name,
                'weight' => $products->weight,
                'unit' => $unitID,
                'cost' => $products->price,
                'price' => $products->mrp,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'cf1' => $products->cf1,
                'cf2' => $products->cf2,
                'cf3' => $products->cf3,
                'cf4' => $products->cf4,
                'cf5' => $products->cf5,
                'cf6' => $products->cf6,
                'tax_rate' => $products->tax_rate,
                'details' => $products->details,
                'barcode_symbology' => $products->barcode_symbology,
                'product_details' => $products->product_details,
                'tax_method' => $products->tax_method,
                'type' => $products->type,
                'sale_unit' => $unitID,
                'purchase_unit' => $unitID,
                'brand' => $brandId,
                'mrp' => $products->mrp,
                'hsn_code' => $products->hsn_code,
                'storage_type' => $products->storage_type,
            ];
            $productId = $this->products_model->store_newproduct($fielddata);
            // End Products
            // Store variants
            if ($products->options) {
                $option = [];
                foreach ($products->options as $optionItems) {
                    $option = [
                        'product_id' => $productId,
                        'name' => $optionItems->name,
                        'cost' => $optionItems->price,
                        'price' => $optionItems->price,
                        'unit_weight' => $optionItems->unit_weight,
                    ];
                    $this->products_model->store_newOption($option);
                }
            } // End Variants
        }

        $response = [
            'status' => 'SUCCESS',
            'error_code' => 200,
            'msg' => 'New Product has been created successfully',
        ];
        echo json_encode($response);
    }

    /*     * *****************************************************************
     * End Purchases Notification 
     * ***************************************************************** */

    public function comboproduct() {
        $exppurl = explode('/', $_SERVER['HTTP_REFERER']);
        $lasturl = count($exppurl) - 1;
        $_SESSION['lastRedirect'] = $exppurl[$lasturl - 1] . '/' . $exppurl[$lasturl];


        $this->data['error'] = (validation_errors() ? validation_errors() : $this->session->flashdata('error'));

        $this->data['categories'] = $this->site->getAllCategories();
        $this->data['tax_rates'] = $this->site->getAllTaxRates();
        $this->data['brands'] = $this->site->getAllBrands();
        $this->data['divisions'] = $this->site->getAllDivision();
        $this->data['base_units'] = $this->site->getAllBaseUnits();
        $this->data['warehouses'] = $warehouses;
        $this->data['warehouses_products'] = $id ? $this->products_model->getAllWarehousesWithPQ($id) : NULL;
        $this->data['product'] = $id ? $this->products_model->getProductByID($id) : NULL;
        $this->data['variants'] = $this->products_model->getAllVariants();
        $this->data['combo_items'] = ($id && $this->data['product']->type == 'combo') ? $this->products_model->getProductComboItems($id) : NULL;
        $this->data['combo_items'] = ($id && $this->data['product']->type == 'Bundle') ? $this->products_model->getProductComboItems($id) : NULL;
        $this->data['product_options'] = $id ? $this->products_model->getProductOptionsWithWH($id) : NULL;
        $cfields = $this->site->getCustomeFieldsLabel('product');
        $this->data['custome_fields'] = $cfields['product'];

        //UrbanPiper restaurant data
        if ($this->data['Settings']->pos_type == 'restaurant') {
            $this->data['foodtype'] = $this->products_model->getfoodstype(); // Use UrbanPiper
        }

        $this->load->view($this->theme . 'products/add_combo_product', $this->data);
    }

    public function manage_price($category_id = null) {

        $this->data['products'] = null;

        $Allcategories = $this->products_model->getCategories();

        if ((bool) $Allcategories) {
            foreach ($Allcategories as $categiry) {
                $categories[$categiry['parent_id']][] = $categiry;
            }
        }

        $this->data['categories'] = $categories;

        $bc = array(['link' => base_url(), 'page' => lang('home')], ['link' => '#', 'page' => lang('products') . ' Price']);
        $meta = array('page_title' => lang('Products') . ' Price', 'bc' => $bc);
        $this->page_construct('products/manage_price', $meta, $this->data);
    }

    public function get_filter_products() {

        $filter['category_id'] = isset($_GET['category_id']) ? $_GET['category_id'] : null;
        $filter['subcategory_id'] = isset($_GET['subcategory_id']) ? $_GET['subcategory_id'] : 0;

        $products = $this->products_model->getFilterProducts($filter);
//        echo '<pre>';
//  print_r($products);
//        echo '</pre>';
//        exit;
        if (count($products)) {

            $table .= '<table class="table table-bordered"> ';


            foreach ($products as $product) {
                $prod_uid = $product['id'];
                $product['eshop_name'] = !empty($product['eshop_name']) ? $product['eshop_name'] : $product['name'];
                $product['eshop_price'] = ((bool) $product['eshop_price']) ? $product['eshop_price'] : $product['price'];
                $product_image = ($product['image'] == '') ? 'no_image.png' : $product['image'];
                $product['eshop_mrp'] = ((bool) $product['mrp']) ? $product['mrp'] : $product['eshop_price'];

                $table .= '<thead><tr>';
                //$table .= '<th><input type="checkbox" name="chk_all" id="chk_all" value="1" class="checkbox" /></th>';
                $table .= '<th>Image</th>';
                $table .= '<th class="col-sm-6">Products Ecommorse Name</th>';
                $table .= '<th>MRP</th>';
                $table .= '<th>Eshop Price (Including Tax)</th></tr></thead>';

                //$table .= '<tbody>';
                $table .= '<tr class="prdrow_' . $prod_uid . '">';
                // $table .= '<td><input type="checkbox" name="products[]" id="' . $prod_uid . '" value="' . $prod_uid . '" product="' . $product['id'] . '" variant="' . $product['variant_id'] . '" class="checkbox" /></td>';
                $table .= '<td><img src="' . site_url("assets/mdata/$this->Customer_assets/uploads/$product_image") . '" height="40" alt="' . $prod_uid . '" class="img" /></td>';

                $table .= '<td><input title="Ecommorse Product Name" type="text" name="eshop_name[' . $prod_uid . ']" id="eshop_name_' . $prod_uid . '" value="' . $product['eshop_name'] . '" placeholder="' . $product['name'] . '" required="required" class="form-control input-xs input_' . $prod_uid . '" /></td>';
                $table .= '<td><input type="text" name="eshop_mrp[' . $prod_uid . ']" id="eshop_mrp_' . $prod_uid . '" value="' . round($product['eshop_mrp']) . '" class="form-control input_' . $prod_uid . '" /></td>';
                $table .= '<td><input type="text" name="eshop_price[' . $prod_uid . ']" id="eshop_price_' . $prod_uid . '" value="' . round($product['eshop_price']) . '" class="form-control input_' . $prod_uid . '" /></td>';
                $table .= '</tr>';

                if (is_array($product['varants'])) {
                    $table .= '<tr><td>Variants</td><td colspan="3">';


                    //Variant Tables & Headings
                    $table .= '<table class="table table-bordered">';
                    $table .= '<tr><th>Variant Eshop Name</th><th>Unit Quantity</th><th>Variant Eshop MRP</th><th>Variant Eshop Price (Including Tax)</th></tr>';
                    $table .= '<tbody>';

                    foreach ($product['varants'] as $key => $variant) {

                        $variant_id = $variant['variant_id'];
                        $variant_name = $variant['variant_name'];
                        $variant_eshop_name = $variant['variant_eshop_name'] ? $variant['variant_eshop_name'] : $variant_name;

                        $variant_eshop_price = (bool) $variant['variant_eshop_price'] && $variant['variant_eshop_price'] >= $product['eshop_price'] ? $variant['variant_eshop_price'] : ($product['eshop_price'] + $variant['variant_price']);
                        $variant_eshop_mrp = (bool) $variant['variant_eshop_mrp'] ? $variant['variant_eshop_mrp'] : ($product['eshop_mrp'] + $variant['variant_price']);
                        $table .= '<tr>';
                        $table .= '<td><input palceholder="Ecommorse Variant Name" type="text" name="variant_eshop_name[' . $variant_id . ']" id="variant_eshop_name_' . $prod_uid . '" value="' . $variant_eshop_name . '" placeholder="' . $variant_eshop_name . '" required="required" class="form-control input-xs input_' . $prod_uid . '" /></td>';
                        $table .= '<td><span class="form-control">' . number_format($variant['variant_unit_quantity'], 2) . '</span></td>';
                        $table .= '<td><input type="text" name="variant_eshop_mrp[' . $variant_id . ']" id="variant_eshop_mrp_' . $prod_uid . '" value="' . round($variant_eshop_mrp) . '" class="form-control input_' . $prod_uid . '" /></td>';
                        $table .= '<td><input type="text" name="variant_eshop_price[' . $variant_id . ']" id="variant_eshop_price_' . $prod_uid . '" value="' . round($variant_eshop_price) . '" class="form-control input_' . $prod_uid . '" /></td>';
                        $table .= '</tr>';
                    }
                    $table .= '</tbody></table></td></tr>';
                }
            }//end foreach.

            echo $table .= '</table>';
        }
    }

    public function eshop_price_update() {

//        echo '<pre>';
//        print_r($_POST);
//        echo '</pre>';

        if ($_POST['action'] == "save_changes") {

            if (count($_POST['eshop_name'])) {
                foreach ($_POST['eshop_name'] as $product_id => $product) {
                    $eshop_name = trim($this->input->post("eshop_name[$product_id]"));
                    $eshop_mrp = trim($this->input->post("eshop_mrp[$product_id]"));
                    $eshop_price = trim($this->input->post("eshop_price[$product_id]"));

                    $productsData[] = [
                        "id" => $product_id,
                        "eshop_name" => $eshop_name,
                        "mrp" => $eshop_mrp,
                        "eshop_price" => $eshop_price,
                    ];
                }

                $this->db->update_batch('products', $productsData, 'id');
            }

            if (count($_POST['variant_eshop_name'])) {
                foreach ($_POST['variant_eshop_name'] as $variant_id => $variant) {
                    $variant_eshop_name = trim($this->input->post("variant_eshop_name[$variant_id]"));
                    $variant_eshop_mrp = trim($this->input->post("variant_eshop_mrp[$variant_id]"));
                    $variant_eshop_price = trim($this->input->post("variant_eshop_price[$variant_id]"));

                    $variantData[] = [
                        "id" => $variant_id,
                        "eshop_name" => $variant_eshop_name,
                        "eshop_mrp" => $variant_eshop_mrp,
                        "eshop_price" => $variant_eshop_price,
                    ];
                }

                $this->db->update_batch('product_variants', $variantData, 'id');
            }

            $this->session->set_flashdata('message', lang("Eshop Products price updated"));
            return redirect($_SERVER['HTTP_REFERER']);
        }

        $this->session->set_flashdata('error', lang("Inter server error"));
        return redirect($_SERVER['HTTP_REFERER']);
    }

}

//end class