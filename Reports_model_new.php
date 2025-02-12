<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Reports_model_new extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function getProductNames($term, $limit = 5) {
        $this->db->select('id, code, name')
                ->like('name', $term, 'both')->or_like('code', $term, 'both');
        $this->db->limit($limit);
        $q = $this->db->get('products');
        if ($q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /*     * *1-21-2020 new Gst Report Model** */

    public function getStaff() {
        if ($this->Admin) {
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

    public function getSalesItemsBySaleIds(array $saleIds, $products) {
        $salesIn = join(',', $saleIds);

        /* $query = "SELECT id as items_id, sale_id, item_tax, subtotal, tax as gst, hsn_code as hsn_code, quantity as quantity, 
          product_unit_code as unit , product_code, product_name, product_id
          FROM  " . $this->db->dbprefix('sale_items') . "
          WHERE `sale_id` IN ($salesIn) "; */

        $query = "SELECT {$this->db->dbprefix('sale_items')}.id as items_id, {$this->db->dbprefix('sale_items')}.sale_id, {$this->db->dbprefix('sale_items')}.item_tax, {$this->db->dbprefix('sale_items')}.subtotal, {$this->db->dbprefix('sale_items')}.tax as gst, {$this->db->dbprefix('sale_items')}.hsn_code as hsn_code, {$this->db->dbprefix('sale_items')}.quantity as quantity, 
                    {$this->db->dbprefix('sale_items')}.product_unit_code as unit , {$this->db->dbprefix('sale_items')}.product_code, {$this->db->dbprefix('sale_items')}.product_name, {$this->db->dbprefix('sale_items')}.product_id ,{$this->db->dbprefix('product_variants')}.name as variant_name
                FROM  {$this->db->dbprefix('sale_items')}  lEFT JOIN {$this->db->dbprefix('product_variants')} ON {$this->db->dbprefix('product_variants')}.id = {$this->db->dbprefix('sale_items')}.option_id
                ";  // WHERE `sale_id` IN ($salesIn)
        if ($products) {
            $query .= " WHERE {$this->db->dbprefix('sale_items')}.product_id= $products";
        } else {
            $query .= " WHERE `sale_id` IN ($salesIn)";
        }

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getpaymentmode($sales_id = null) {
        $getoption = $this->db->select(' GROUP_CONCAT(DISTINCT  paid_by) as paid_by')
                        ->where(['sale_id' => $sales_id])
                        ->get('sma_payments')->row();

        return $getoption->paid_by;
    }

    public function getSalesHsunt($salesid, $type) {
        $array = array('sale_id' => $salesid);
        $get = $this->db->select('(GROUP_CONCAT(DISTINCT ' . $type . ')) as hsunt')
                        ->where($array)
                        ->get('sma_sale_items')->row();
        return $get->hsunt;
    }

    public function getSalesQty($sid) {
        $array = array('sale_id' => $sid);
        $get = $this->db->select('format(sum(quantity), 2) as qty')
                        ->where($array)
                        ->get('sma_sale_items')->row();
        return $get->qty;
    }

    public function getSalesTax($saleid) {
        $array = array('sale_id' => $saleid);
        $get = $this->db->select('(GROUP_CONCAT(CONCAT(" " , format(tax,2),"%"))) as tax')
                        ->where($array)
                        ->get('sma_sale_items')->row();
        return $get->tax;
    }

    public function getSalesAsGst($saleid, $type) {
        $myQry = "SELECT  sum($type) as sum  FROM  sma_sale_items WHERE sale_id = $saleid  and $type > 0 Group By gst_rate";

        $res = $this->db->query($myQry, false)->row();
        // echo $saleid;
        // echo $res->sum;
        if ($res->sum != " " && $res->sum != 0.0000) {
            $myQuery = "SELECT  DISTINCT CONCAT('(',gst_rate, '%)Rs.',sum($type))as sumgst  FROM  sma_sale_items WHERE sale_id = $saleid  Group By gst_rate";
        } else {
            $myQuery = "SELECT  DISTINCT CONCAT('Rs.',sum($type))as sumgst  FROM  sma_sale_items WHERE sale_id = $saleid  Group By gst_rate";
        }
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
        }
        return $data;
    }

    public function getSalesItemAsGst($id, $type) {
        $myQry = "SELECT  sum($type) as sum  FROM  sma_sale_items WHERE id = $id  and $type > 0 Group By gst_rate";

        $res = $this->db->query($myQry, false)->row();
        // echo $saleid;
        // echo $res->sum;
        if ($res->sum != " " && $res->sum != 0.0000) {
            $myQuery = "SELECT  DISTINCT CONCAT('(',gst_rate, '%)Rs.',sum($type))as sumgst, gst_rate as gstrrate , sum($type) as totalgst  FROM  sma_sale_items WHERE id = $id  Group By gst_rate";
        } else {
            $myQuery = "SELECT  DISTINCT CONCAT('Rs.',sum($type))as sumgst  FROM  sma_sale_items WHERE id = $id  Group By gst_rate";
        }
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
        }
        return $data;
    }

    /**/
    /* daily sale Report */

    public function getPurcahseAsGst($purchaseid, $type) {
        $myQry = "SELECT  sum($type) as sum  FROM  sma_purchase_items WHERE purchase_id = $purchaseid  and $type > 0 Group By gst_rate";

        $res = $this->db->query($myQry, false)->row();
        // echo $saleid;
        if ($res->sum != " " && $res->sum != 0.0000) {
            $myQuery = "SELECT  DISTINCT CONCAT('(',gst_rate, '%)Rs.',sum($type))as sumgst, gst_rate as gstrate, sum($type) as totalgst  FROM  sma_purchase_items WHERE purchase_id = $purchaseid  Group By gst_rate";
        } else {
            $myQuery = "SELECT  DISTINCT CONCAT('Rs.',sum($type))as sumgst  FROM  sma_purchase_items WHERE purchase_id = $purchaseid  Group By gst_rate";
        }
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
        }
        return $data;
    }

    public function getStaffDailySales($user_id, $year, $month, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( date,  '%e' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping,SUM(CASE WHEN up_sales = 1 THEN grand_total ELSE 0 END ) AS urban_piper
            FROM " . $this->db->dbprefix('sales') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN( {$getwarehouse} ) AND ";
        }
        if ($this->Owner || $this->Admin) {
            if ($user_id) {
                $myQuery .= " created_by = {$user_id} AND ";
            }
        } else {
            if ($this->session->userdata('view_right') == '0') {
                $myQuery .= " created_by = {$user_id} AND ";
            }
        }
        $myQuery .= " DATE_FORMAT( date,  '%Y-%m' ) =  '{$year}-{$month}'
            GROUP BY DATE_FORMAT( date,  '%e' )";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getStaffMonthlySales($user_id, $year, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ',', $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( date,  '%c' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
            FROM " . $this->db->dbprefix('sales') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN ({$getwarehouse}) AND ";
        }

        if ($this->Owner || $this->Admin) {
            if ($user_id) {
                $myQuery .= " created_by = {$user_id} AND ";
            }
        } else {
            if ($this->session->userdata('view_right') == '0') {
                $myQuery .= " created_by = {$user_id} AND ";
            }
        }

        $myQuery .= "  DATE_FORMAT( date,  '%Y' ) =  '{$year}'
            GROUP BY date_format( date, '%c' ) ORDER BY date_format( date, '%c' ) ASC";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /* 12-28-2019 It show to warehouse */

    public function getStaffDailySales_w($user_id, $year, $month, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( s.date,  '%e' ) AS date, SUM( COALESCE( s.product_tax, 0 ) ) AS tax1, SUM( COALESCE( s.order_tax, 0 ) ) AS tax2, SUM( COALESCE( s.grand_total + s.rounding, 0 ) ) AS total,SUM(IF(s.sale_status='returned',abs(s.grand_total) + abs(s.rounding) + abs(s.total_discount),0)) as return_amt,SUM( COALESCE( (grand_total+ rounding  + total_discount), 0 ) ) AS GrossSale, SUM( COALESCE( s.total_discount, 0 ) ) AS discount, SUM( COALESCE( s.shipping, 0 ) ) AS shipping,SUM(CASE WHEN up_sales = 1 THEN IF(sale_status = 'Acknowledged',grand_total ,0) ELSE 0 END ) AS urban_piper, w.name as warehouse  FROM   sma_sales s  LEFT JOIN sma_warehouses w on s.warehouse_id = w.id WHERE ";
        if ($warehouse_id) {
            $myQuery .= " s.warehouse_id IN( {$getwarehouse} ) AND ";
        }
        if ($this->Owner || $this->Admin) {
            if ($user_id) {
                $myQuery .= " s.created_by = {$user_id} AND ";
            }
        } else {
            if ($this->session->userdata('view_right') == '0') {
                $myQuery .= " s.created_by = {$user_id} AND ";
            }
        }
        $myQuery .= " DATE_FORMAT( s.date,  '%Y-%m' ) =  '{$year}-{$month}'
            GROUP BY DATE_FORMAT( s.date,  '%e' )";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getDailySales($year, $month, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);

        // $myQuery = "SELECT DATE_FORMAT( date,  '%e' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
        //	FROM " . $this->db->dbprefix('sales') . " WHERE ";

        $myQuery = "SELECT DATE_FORMAT( date,  '%e' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping,SUM(CASE WHEN up_sales = 1 THEN grand_total ELSE 0 END ) AS urban_piper
            FROM " . $this->db->dbprefix('sales') . " WHERE ";
        /* if ($warehouse_id) {
          $myQuery .= " warehouse_id = {$warehouse_id} AND ";
          } */

        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN( {$getwarehouse} ) AND ";
        }
        /* if ($this->session->userdata('view_right') == '0') {
          $myQuery .= " created_by = {$user_id} AND  ";
          } */

        $myQuery .= " DATE_FORMAT( date,  '%Y-%m' ) =  '{$year}-{$month}'
			GROUP BY DATE_FORMAT( date,  '%e' )";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getDailySales_w($year, $month, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);

        $myQuery = "SELECT DATE_FORMAT( s.date,  '%e' ) AS date, SUM( COALESCE( s.product_tax, 0 ) ) AS tax1, SUM( COALESCE( s.order_tax, 0 ) ) AS tax2, SUM( COALESCE( s.grand_total + s.rounding, 0 ) ) AS total, SUM(IF(s.sale_status='returned',abs(s.grand_total) + abs(s.rounding),0)) as return_amt,SUM(IF(s.sale_status!='returned', s.total_discount ,0)) as total_discount,SUM( COALESCE( (grand_total+ rounding ), 0 ) ) AS GrossSale, SUM( COALESCE( s.total_discount, 0 ) ) AS discount, SUM( COALESCE( s.shipping, 0 ) ) AS shipping, SUM(CASE WHEN up_sales = 1 THEN IF(sale_status = 'Acknowledged',grand_total ,0) ELSE 0 END ) AS urban_piper, w.name as warehouse 	FROM   sma_sales s  LEFT JOIN sma_warehouses w on s.warehouse_id = w.id WHERE ";
        /* if ($warehouse_id) {
          $myQuery .= " warehouse_id = {$warehouse_id} AND ";
          } */

        if ($warehouse_id) {
            $myQuery .= " s.warehouse_id IN( {$getwarehouse} ) AND ";
        }
        /* if ($this->session->userdata('view_right') == '0') {
          $myQuery .= " s.created_by = {$user_id} AND  ";
          } */

        $myQuery .= " DATE_FORMAT( s.date,  '%Y-%m' ) =  '{$year}-{$month}'
			GROUP BY DATE_FORMAT( s.date,  '%e' )";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getStaffMonthlySales_w($user_id, $year, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ',', $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT(  s.date,  '%c' ) AS date, SUM( COALESCE(  s.product_tax, 0 ) ) AS tax1, SUM( COALESCE(  s.order_tax, 0 ) ) AS tax2, SUM( COALESCE(  s.grand_total + s.rounding, 0 ) ) AS total, SUM(IF(s.sale_status='returned',abs(s.grand_total) + abs(s.rounding) + abs(s.total_discount),0)) as return_amt,SUM( COALESCE( (grand_total+ rounding  + total_discount), 0 ) ) AS GrossSale, SUM( COALESCE(  s.total_discount, 0 ) ) AS discount, SUM( COALESCE(  s.shipping, 0 ) ) AS shipping, w.name as warehouse   FROM   sma_sales s  LEFT JOIN sma_warehouses w on s.warehouse_id = w.id WHERE ";
        if ($warehouse_id) {
            $myQuery .= " s.warehouse_id IN ({$getwarehouse}) AND ";
        }

        if ($this->Owner || $this->Admin) {
            if ($user_id) {
                $myQuery .= " s.created_by = {$user_id} AND ";
            }
        } else {
            if ($this->session->userdata('view_right') == '0') {
                $myQuery .= " s.created_by = {$user_id} AND ";
            }
        }

        $myQuery .= "  DATE_FORMAT( s.date,  '%Y' ) =  '{$year}'
            GROUP BY date_format( s.date, '%c' ) ORDER BY date_format( s.date, '%c' ) ASC";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getMonthlySales_w($year, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT  DATE_FORMAT(  s.date,  '%c' ) AS date, SUM( COALESCE(  s.product_tax, 0 ) ) AS tax1, SUM( COALESCE(  s.order_tax, 0 ) ) AS tax2, SUM( COALESCE(  s.grand_total + s.rounding, 0 ) ) AS total,SUM(IF(s.sale_status!='returned', s.total_discount ,0)) as total_discount, SUM(IF(s.sale_status='returned',abs(s.grand_total) + abs(s.rounding),0)) as return_amt,SUM( COALESCE( (s.grand_total+ s.rounding  ), 0 ) ) AS GrossSale, SUM( COALESCE(  s.total_discount, 0 ) ) AS discount, SUM( COALESCE(  s.shipping, 0 ) ) AS shipping, w.name as warehouse  FROM   sma_sales s  LEFT JOIN sma_warehouses w on s.warehouse_id = w.id WHERE ";
        if ($warehouse_id) {
            $myQuery .= " s.warehouse_id IN ({$getwarehouse}) AND ";
        }
        $myQuery .= " DATE_FORMAT( date,  '%Y' ) =  '{$year}'
			GROUP BY date_format( s.date, '%c' ) ORDER BY date_format( s.date, '%c' ) ASC";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getDailySalesItems($date, $warehouse_id = 0) {
        $query = "SELECT  si.product_id ,si.product_code ,  si.product_name ,  si.net_unit_price, si.product_unit_code as unit,
                    SUM(  si.quantity ) as qty, SUM(  si.item_tax ) as tax, si.tax as tax_rate, SUM(  si.item_discount ) as discount, SUM(  si.subtotal ) as total, c.id as category_id, c.name as category_name
                FROM  " . $this->db->dbprefix('sale_items') . " si  left join " . $this->db->dbprefix('products') . " p on p.id=si.product_id left join  " . $this->db->dbprefix('categories') . " c on c.id=p.category_id
                WHERE  si.sale_id IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('sales') . "  WHERE DATE( `date` ) =  '$date' )";
        if ($warehouse_id != 0) {
            $query .= " and si.warehouse_id='$warehouse_id'  ";
        }
        $query .= " GROUP BY  si.product_code 
                ORDER BY  si.product_name ";

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getDailySalesItemsTaxes($date, $warehouse_id = 0) {
        $select_warehouse = '';
        if ($warehouse_id != 0) {
            $select_warehouse = " and warehouse_id='$warehouse_id'  ";
        }
        /* $query = "SELECT sum(`tax_amount`) amount, ( `attr_per` * 2) as rate,item_id
          FROM  " . $this->db->dbprefix('sales_items_tax') . "
          WHERE `sale_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('sales') . "  WHERE DATE( `date` ) =  '$date' " . $select_warehouse . " )
          AND `attr_per` > 0 GROUP BY `attr_per` ORDER BY `attr_per` ASC "; */

        $query = "SELECT tax, gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sale_items') . " 
                WHERE `sale_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('sales') . "  WHERE DATE( `date` ) =  '$date' " . $select_warehouse . " ) 
                    GROUP BY `gst_rate` ";

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /* public function gettaxitemid($item_id) {

      $qry = "SELECT (SELECT attr_per FROM  " . $this->db->dbprefix('sales_items_tax') . "  WHERE `attr_code` = 'CGST' AND  item_id ='$item_id' ) AS CGST ,(SELECT attr_per FROM  " . $this->db->dbprefix('sales_items_tax') . "  WHERE `attr_code` = 'SGST' AND item_id ='$item_id') AS SGST ,(SELECT attr_per FROM  " . $this->db->dbprefix('sales_items_tax') . "  WHERE `attr_code` = 'IGST' AND  item_id ='$item_id' ) AS IGST FROM  " . $this->db->dbprefix('sales_items_tax') . "  WHERE   item_id ='$item_id' Group By item_id";
      $sqlrs = $this->db->query($qry, false);
      if ($sqlrs->num_rows() > 0) {
      foreach (($sqlrs->result()) as $row_rs) {
      $data[] = $row_rs;
      }
      return $data;
      }
      return FALSE;
      } */

    public function getMonthSalesItemsTaxes($month, $year) {
        /* $query = "SELECT sum(`tax_amount`) amount, ( `attr_per` * 2) as rate,item_id
          FROM  " . $this->db->dbprefix('sales_items_tax') . "
          WHERE `sale_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('sales') . "  WHERE  DATE_FORMAT( date,  '%c' ) =  '{$month}' AND  DATE_FORMAT( date,  '%Y' ) =  '{$year}' )
          AND `attr_per` > 0 GROUP BY `attr_per` ORDER BY `attr_per` ASC "; */


        $query = "SELECT tax, gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('sale_items') . " 
                WHERE `sale_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('sales') . "  WHERE  DATE_FORMAT( date,  '%c' ) =  '{$month}' AND  DATE_FORMAT( date,  '%Y' ) =  '{$year}' ) 
                    GROUP BY `gst_rate`";
        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getMonthlySales($year, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT  DATE_FORMAT( date,  '%c' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
			FROM " . $this->db->dbprefix('sales') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN ({$getwarehouse}) AND ";
        }
        $myQuery .= " DATE_FORMAT( date,  '%Y' ) =  '{$year}'
			GROUP BY date_format( date, '%c' ) ORDER BY date_format( date, '%c' ) ASC";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /* Urbin Piper Daily Report */

    public function getDailyUrbinpiper($date) {

        $query = "SELECT  Count(id) AS invoice, up_channel, SUM( COALESCE( grand_total, 0 ) ) AS total
            FROM  " . $this->db->dbprefix('sales') . "  WHERE up_sales = 1 AND DATE( `date` ) =  '$date'  GROUP BY up_channel ";

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
    /* daily and monthy purchase function */

    public function getDailyPurchases($year, $month, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( date,  '%e' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
            FROM " . $this->db->dbprefix('purchases') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN ({$getwarehouse}) AND ";
        }
        $myQuery .= " DATE_FORMAT( date,  '%Y-%m' ) =  '{$year}-{$month}'
            GROUP BY DATE_FORMAT( date,  '%e' )";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getMonthlyPurchases($year, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( date,  '%c' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
            FROM " . $this->db->dbprefix('purchases') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN ({$getwarehouse}) AND ";
        }
        $myQuery .= " DATE_FORMAT( date,  '%Y' ) =  '{$year}'
            GROUP BY date_format( date, '%c' ) ORDER BY date_format( date, '%c' ) ASC";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getStaffDailyPurchases($user_id, $year, $month, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( date,  '%e' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
            FROM " . $this->db->dbprefix('purchases') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN ( {$getwarehouse} ) AND ";
        }

        // 03/04/19
        if ($this->session->userdata('view_right') == '0') {
            $myQuery .= " created_by = {$user_id} AND ";
        }
        // End  03/04/19


        $myQuery .= "  DATE_FORMAT( date,  '%Y-%m' ) =  '{$year}-{$month}'
            GROUP BY DATE_FORMAT( date,  '%e' )";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getStaffMonthlyPurchases($user_id, $year, $warehouse_id = NULL) {
        $getwarehouse = str_replace("_", ",", $warehouse_id);
        $myQuery = "SELECT DATE_FORMAT( date,  '%c' ) AS date, SUM( COALESCE( product_tax, 0 ) ) AS tax1, SUM( COALESCE( order_tax, 0 ) ) AS tax2, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( total_discount, 0 ) ) AS discount, SUM( COALESCE( shipping, 0 ) ) AS shipping
            FROM " . $this->db->dbprefix('purchases') . " WHERE ";
        if ($warehouse_id) {
            $myQuery .= " warehouse_id IN ( {$getwarehouse}) AND ";
        }

        if ($this->session->userdata('view_right') == '0') {
            $myQuery .= " created_by = {$user_id} AND ";
        }

        $myQuery .= " created_by = {$user_id} AND DATE_FORMAT( date,  '%Y' ) =  '{$year}'
            GROUP BY date_format( date, '%c' ) ORDER BY date_format( date, '%c' ) ASC";
        $q = $this->db->query($myQuery, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getDailyPurchaseItemsTaxes($date) {

        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('purchase_items') . " 
            WHERE `purchase_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('purchases') . "  WHERE DATE( `date` ) =  '$date' ) 
            GROUP BY `gst_rate` ";

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }

            return $data;
        }
        return FALSE;
    }

    public function getDailyPurchaseItems($date) {
        $query = "SELECT  `product_id` ,`product_code` ,  `product_name` ,  `net_unit_cost` , `product_unit_code` unit,
                    SUM(  `quantity` ) qty, SUM(  `item_tax` ) tax, tax as tax_rate, SUM(  `item_discount` ) discount, SUM(  `subtotal` ) total
                FROM  " . $this->db->dbprefix('purchase_items') . "  
                WHERE  `purchase_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('purchases') . "  WHERE DATE( `date` ) =  '$date' )
                GROUP BY  `product_code` 
                ORDER BY  `product_name` ";

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getMonthPurchaseItemsTaxes($month, $year) {
        $query = "SELECT gst_rate,sum(cgst) AS CGST,sum(sgst) AS SGST,sum(igst) AS IGST
            FROM  " . $this->db->dbprefix('purchase_items') . "
            WHERE `purchase_id` IN ( SELECT  `id`  FROM  " . $this->db->dbprefix('purchases') . "  WHERE  DATE_FORMAT( date,  '%c' ) =  '{$month}' AND  DATE_FORMAT( date,  '%Y' ) =  '{$year}' ) 
             GROUP BY `gst_rate` ";

        $q = $this->db->query($query, false);
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    /* Tax Purchase CGST SGST IGST */

    public function getpurchasetaxitemid($item_id) {
        $qry = "SELECT (SELECT attr_per FROM  " . $this->db->dbprefix('purchase_items_tax') . "  WHERE `attr_code` = 'CGST' AND  item_id ='$item_id' ) AS CGST ,(SELECT attr_per FROM  " . $this->db->dbprefix('purchase_items_tax') . "  WHERE `attr_code` = 'SGST' AND item_id ='$item_id') AS SGST ,(SELECT attr_per FROM  " . $this->db->dbprefix('purchase_items_tax') . "  WHERE `attr_code` = 'IGST' AND  item_id ='$item_id' ) AS IGST FROM  " . $this->db->dbprefix('purchase_items_tax') . "  WHERE   item_id ='$item_id' Group By item_id";
        $sqlrs = $this->db->query($qry, false);
        if ($sqlrs->num_rows() > 0) {
            foreach (($sqlrs->result()) as $row_rs) {
                $data[] = $row_rs;
            }
            return $data;
        }
        return FALSE;
    }

    /**/

    /**
     * 
     * @param type $hsn
     * @return type
     */
    public function getSaleIdByHsn($hsn) {
        if (empty($hsn)):
            return -1;
        endif;

        $this->db
                ->select('sale_id')
                ->where('hsn_code', $hsn);
        $q = $this->db->get('sale_items');

        if ($q->num_rows() > 0) {
            $resultArr = array();
            foreach (($q->result()) as $row) {
                $resultArr[] = $row->sale_id;
            }
            return implode(',', $resultArr);
        }
        return -1;
    }

    /**
     * Get VAT and CESS
     * @param type $id
     * @param type $attr_code
     * @return type
     */
    public function getVatCess($id, $attr_code) {
        $sql = "SELECT GROUP_CONCAT( CONCAT( ' (' ,`attr_per`, '%)Rs.', format(`tax_amount`,2) ) ) AS $attr_code
            , attr_per as taxrate , format(`tax_amount`,2) AS taxamount FROM  `view_sales_gst_report` 
            WHERE  `sale_id` = $id
            AND `attr_code` =  '$attr_code' ";


        $query = $this->db->query($sql)->row();

        return $query;
    }

    /**
     * 
     * @param type $purchaseid
     * @param type $attr_code
     * @return type
     */
    public function getpurcahseVatCESS($purchaseid, $attr_code) {
        $sql = "Select SUM('tax_amount') as taxamount, attr_per From sma_purchase_items_tax WHERE "
                . "purchase_id = $purchaseid AND attr_code = '$attr_code'";
        $query = $this->db->query($sql)->row();
        return $query;
    }

    /**
     * Manage Log to Report Send on Email
     */
    public function reportemaillog() {
        $data = [
            'send_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->db->insert('reports_email_log', $data);
    }

    public function getCustomerCompanies() {
        $q = $this->db->select('id, name, company')->order_by('name', 'ASC')->get_where('companies', array('group_name' => 'customer'));
        if ($q->num_rows() > 0) {
            foreach (($q->result()) as $row) {
                $data[] = $row;
            }
            return $data;
        }
        return FALSE;
    }

    public function getSaleGstData($year = NULL, $month = NULL, $warehouse = NULL, $biller = NULL, $sale_items = FALSE, $customer = NULL, $start_date = NULL, $end_date = NULL, $customer_group = NULL) {

        $year = $year ? $year : date('Y');
        $month = $month ? $month : date('m');

         if($start_date){
            $fromDate = date("Y-m-d", strtotime($start_date))." 00:00:00";
             $toDate = date("Y-m-d", strtotime($end_date)) . ' 23:59:59';
        } else {
           $fromDate = "$year-$month-01 00:00:00";
           $toDate = date("Y-m-t", strtotime($fromDate)) . ' 23:59:59';
        }
        if($sale_items === TRUE){
            $select_sales_items = ", si.id AS item_id, si.product_id, si.product_code, si.product_name, si.option_id, pv.name AS option_name, si.quantity , si.item_discount, si.tax AS tax_rate, si.hsn_code, si.net_unit_price, ((si.`subtotal`) - (si.`item_tax`) ) taxable_amount, `gst_rate`, (si.`cgst`) cgst, (si.`sgst`) sgst, (si.`igst`) igst ";
        } else {
            $select_sales_items = ", count(si.`id`) total_items, (SUM(si.`subtotal`) - SUM(si.`item_tax`) ) taxable_amount, `gst_rate`, SUM(si.`cgst`) cgst, SUM(si.`sgst`) sgst, SUM(si.`igst`) igst, si.tax AS tax_rate ";
        }
        //GROUP_CONCAT(p.`paid_by`) as payment_method,
        $qry = "SELECT s.id sale_id, s.`invoice_no`, s.`reference_no`, s.`date`, s.`reference_no`, s.`customer_id`, s.`customer`, s.`note`, c.`gstn_no`, c.`customer_group_name`, c.state as `state_code`, s.`biller_id`, s.`biller`, 
                s.`warehouse_id`, s.`sale_status`, s.`payment_status`, s.`payment_term`, s.`total_tax`, s.`shipping`, s.`grand_total`, s.`total_discount`, 
                s.`rounding`, s.`total_weight`  $select_sales_items  
            FROM " . $this->db->dbprefix('sales') . " s 
            RIGHT JOIN " . $this->db->dbprefix('sale_items') . " si ON si.`sale_id` = s.`id` 
            RIGHT JOIN " . $this->db->dbprefix('companies') . " c ON c.id = s.`customer_id`";  
          //  LEFT JOIN " . $this->db->dbprefix('payments') . " p ON p.sale_id = s.`id` ";
           
        
        if($sale_items === TRUE){        
             $qry .= " LEFT JOIN ". $this->db->dbprefix('product_variants') ." pv ON pv.id = si.option_id ";
        }
        
       $qry .= " WHERE s.`date` BETWEEN ('$fromDate') AND ('$toDate') ";

        if ($warehouse) {
            $qry .= " AND s.warehouse_id = '$warehouse' ";
        }
        if ($biller) {
            $qry .= " AND s.biller_id = '$biller' ";
        }
        
        if($customer){
             $qry .= " AND s.customer_id = '$customer' ";
        }
        
        if($customer_group){
            $qry .= " AND c.customer_group_id = '$customer_group' ";
        }

        if($sale_items === FALSE){
            $qry .= " GROUP By si.`sale_id`, si.`gst_rate`  
                     ORDER By s.`date`, si.`sale_id` ";
        } else {
            $qry .= " GROUP By si.`id` ORDER By s.`date` DESC, si.`sale_id`, si.`id` ASC";
        }

        $sqlrs = $this->db->query($qry, false);

        if ($sqlrs->num_rows() > 0) {
            foreach ($sqlrs->result() as $row_rs) {
                 $row_rs->payment_method= $this->getPaymentMethod($row_rs->sale_id);
                $data[] = $row_rs;
            }
            
            return $data;
        }
        return FALSE;
    }

public function getPaymentMethod($sale_id){
       $paymentMode = $this->db->select('GROUP_CONCAT(" ",paid_by) as payment_method')->where(['sale_id'=>$sale_id])->get('payments')->row();
       return ($this->db->affected_rows()?$paymentMode->payment_method :FALSE);
    }   
    

    public function getPurchaseGstData($year = NULL, $month = NULL, $warehouse = NULL, $supplier = NULL, $start_date = NULL, $end_date = NULL) {

        $year = $year ? $year : date('Y');
        $month = $month ? $month : date('m');
        if($start_date){
            $fromDate = date("Y-m-d", strtotime($start_date))." 00:00:00";
            $toDate = date("Y-m-d", strtotime($end_date)) . ' 23:59:59';
        } else {
            $fromDate = "$year-$month-01 00:00:00";
            $toDate = date("Y-m-t", strtotime($fromDate)) . ' 23:59:59';
        }

        $qry = "SELECT p.id purchase_id, p.`reference_no`, p.`date`, p.`supplier_id`, p.`supplier`, c.`gstn_no`, c.state as `state_code`, p.`warehouse_id`, p.`status`, p.`payment_status`, p.`payment_term`, p.`total_tax`, p.`shipping`, p.`surcharge`, p.`grand_total`, p.`total_discount`, p.`rounding` , count(pi.`product_id`) total_items, (SUM(pi.`subtotal`) - SUM(pi.`item_tax`) ) taxable_amount, pi.`gst_rate`, SUM(pi.`cgst`) cgst, SUM(pi.`sgst`) sgst, SUM(pi.`igst`) igst 
                    FROM " . $this->db->dbprefix('purchases') . " p 
                    RIGHT JOIN " . $this->db->dbprefix('purchase_items') . " pi ON pi.`purchase_id` = p.`id` 
                    RIGHT JOIN " . $this->db->dbprefix('companies') . " c ON c.id = p.`supplier_id` 
                    WHERE p.`date` BETWEEN ('$fromDate') AND ('$toDate') ";

        if ((bool)$warehouse) {
            $qry .= " AND p.warehouse_id = '$warehouse' ";
        }
        if ((bool)$supplier) {
            $qry .= " AND p.supplier_id = '$supplier' ";
        }

        $qry .= " GROUP By pi.`purchase_id`, pi.`gst_rate` 
                   ORDER By p.`date`, pi.`purchase_id` ";

        $sqlrs = $this->db->query($qry, false);

        if ($sqlrs->num_rows() > 0) {
            foreach ($sqlrs->result() as $row_rs) {
                $data[] = $row_rs;
            }
            return $data;
        }
        return FALSE;
    }


     /**
     * Get Current Date Deposit
     * @param type $date
     * @return type
     */
    public function getCurrentDeposit($date){
         $getData =  $this->db->select("sum( COALESCE(amount, 0)) as deposit_amount")
                ->where('DATE_FORMAT(date,"%Y-%m-%d")',$date)
                ->get('deposits')->row();
       
         return $getData->deposit_amount;

    }

}
