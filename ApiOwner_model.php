<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class ApiOwner_model extends CI_Model {
    
    private $imagePath;
    
    public function __construct() {

        parent::__construct();
            
        $this->load->database();
        
    }
    
   
    public function getSales($startDate, $endDate){
        
         $getData =   $this->db->select('format(sum(grand_total),2) as totalSales')->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'")->get('sales')->row();
             
      
      $due =   $this->db->select('format(sum(grand_total),2) as totalDue')->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'")->where(['payment_status' => 'due'])->get('sales')->row();
       
      $return    =   $this->getReturn($startDate, $endDate);
      $cash      =   $this->getAmount($startDate, $endDate,'cash');
      $online      =   $this->getAmount($startDate, $endDate,'online');
      

      $respons =[
          'totalSales' => ($getData->totalSales)?$getData->totalSales : '00.00',
          'totalDue'   => ($due->totalDue)?$due->totalDue : '00.00',
          'totalReturn' => ($return)?$return : '00.00', 
          'totalCash'   => ($cash)?$cash : '00.00', 
          'totalOnline'   => ($online)?$online : '00.00', 
          'totalDiscount'   => ($discount)?$discount : '00.00', 
      ];
       return $respons;
    }

    /**
     * Get Warehouses
     */
    public function getWarehouseSales(){
       $warehouse = $this->db->get('sma_warehouses')->result();
        return $warehouse;
    }



    /**
     * 
     * @param type $startDate
     * @param type $endDate
     * @return type
     */
     public function getReturn($startDate, $endDate) {
      
        $this->db->select(' format(SUM( COALESCE( amount, 0 ) ),2) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'");
//                ->where('payments.paid_by !=', 'cash');

        $q = $this->db->get('payments')->row();
        
        return $q->returned;
    }
    
    /**
     * 
     * @param type $startDate
     * @param type $endDate
     * @param type $type
     * @return type
     */
    public function getAmount($startDate, $endDate, $type) {
      
        $this->db->select(' format(SUM( COALESCE( amount, 0 ) ),2) AS amount', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'");
             if($type == 'cash'){
                $this->db->where('payments.paid_by', 'cash');
             }else{
                $this->db->where('payments.paid_by !=', 'cash');
             }
        $q = $this->db->get('payments')->row();
        
        return $q->amount;
    }
    
    /**
     * Get Discount
     */
    public function getDiscount($startDate, $endDate){
       $q =  $this->db->select('format(SUM(COALESCE( total_discount, 0 )),2) as discount',FALSE)
                ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->get('sales')->row();
                
         return $q->discount;   
    
    }  

    
    /**
     * Get Net Sales
     * @return type
     */
    public function getNetsales($start_date, $end_date){
         $sql = "SELECT  
                sum(`total`) net_total, 
                sum(`total_discount`) as  discount, 
                sum(`total`) as net_sale,
                sum(`rounding`) as rounding,
                sum(`shipping`) as shipping,
                sum(`total_tax`) as tax, 
                count(`reference_no`) total_bills
                    
                    FROM `sma_sales` "; 
                    

            $where = '';

           

           

            $sql .= $where . "WHERE  sale_status !='returned' ";
           if ($start_date) {
                $start_date = $this->sma->fld($start_date);
                $end_date = $this->sma->fld($end_date);
                $sql .= "  and   DATE(date) BETWEEN '$start_date' AND '$end_date'  ";
            }
            $q = $this->db->query($sql);
           return $q->row();
    }

    
     /**
     * Get Return  Sales
     * @return type
     */
      public function getreport($start_date, $end_date, $condition) {

        $sql = "SELECT w.`id` as warehouse_id,  w.`name` as warehouse ,sum(s.`grand_total`) as total, sum(s.`total_discount`) as total_discount,sum(s.`rounding`) as rounding, sum(s.`total`) as net_sale, sum(s.`total_tax`) as tax 
                    FROM `sma_sales` s 
                    LEFT JOIN `sma_warehouses` w on s.`warehouse_id` = w.`id`
                    ";
        $where = '';

        if ($start_date) {
            $where = "  WHERE DATE(date) BETWEEN '$start_date' AND '$end_date' ";
        }
        if ($condition == 'due') {
            $where .= " AND payment_status = 'due'";
        } elseif ($condition == 'returned') {
            $where .= " AND sale_status = 'returned' ";
        } elseif ($condition == 'pending') {
            $where .= " AND payment_status = 'pending' ";
        }

       
        $sql .= $where;

        $q = $this->db->query($sql);
        return $q->row();
    }


   /**
     * Get Top Products
     * 
     * @return type
     */
    public function getTopProducts($order, $startDate, $endDate){
     $topProduct =  $this->db->select('SUM(quantity) as qty, product_name, product_code')
                ->where("Date(updated_at) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->group_by('product_code')->having('count(product_code) > 1')
                ->order_by('qty',$order)->limit(10)->get('sale_items')->result();
      return $topProduct;
        
    }


    /**
     * Payment Details
     */
    
    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayCCSales($startDate, $endDate) {
        
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'CC');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayDCSales($startDate, $endDate) {
      
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_dc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'DC');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayGiftCardSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_gc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'gift_card');
       
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayOtherSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_other_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'other');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

   
    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayDepSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_gc_slips, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'deposit');
       
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

   
    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayCashSales($startDate, $endDate) {
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'cash');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayRefunds($startDate, $endDate) {
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'");
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayExpenses($startDate, $endDate) {
        $this->db->select('SUM( COALESCE( amount, 0 ) ) AS total', FALSE)
                ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'");
        
       
        $q = $this->db->get('expenses');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayCashRefunds($startDate, $endDate) {
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS returned', FALSE)
                ->join('sales', 'sales.id=payments.return_id', 'left')
                ->where('type', 'returned')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'cash');
       
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayChSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'Cheque');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayPPPSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'ppp');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayStripeSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'stripe');
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayAuthorizeSales($startDate, $endDate) {
        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', 'authorize');
       
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $payOpt
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodayPaymentOptionSales($payOpt = '', $startDate, $endDate) {
        if (empty($payOpt))
            return false;

        $this->db->select('COUNT(' . $this->db->dbprefix('payments') . '.id) as total_cheques, SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'")
                ->where('payments.paid_by', $payOpt);
      
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }


    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return type
     */
    public function getTodayDeposit($startDate, $endDate){
       
         $this->db->select("sum( COALESCE(amount, 0)) as deposit_amount,  group_concat(DISTINCT  paid_by SEPARATOR ',') as paid_by")
                  ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'");
                
               $getData =    $this->db->get('deposits')->row();
            return $getData;
    }
    
    
    
    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
     public function getTodayDueSales($startDate, $endDate) {
      
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total', FALSE);
             $this->db->where(['payment_status' => 'due'])
             ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'");
        
        $q = $this->db->get('sales');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getTodaySalesPaid($startDate, $endDate) {
       
        $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total, SUM( COALESCE( amount, 0 ) ) AS paid', FALSE)
                ->join('sales', 'sales.id=payments.sale_id', 'left')
                ->where('type', 'received')
                ->where("Date(sma_payments.date) BETWEEN '{$startDate}' AND '{$endDate}'");
        
        $q = $this->db->get('payments');
        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }

    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
    public function getcalpartial($startDate, $endDate) {
      
            $this->db->select('SUM( COALESCE( grand_total, 0 ) ) - SUM( COALESCE( paid, 0 ) ) AS partial_due', FALSE);
            $this->db->where(['payment_status' => 'partial'])
            ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'");
        
        
        $q = $this->db->get('sales');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }
    
    
    /**
     * 
     * @param type $start_date
     * @param type $end_date
     * @return boolean
     */
     public function getTodaySales($startDate, $endDate) {
        
        $q = $this->db->select('SUM( COALESCE( grand_total, 0 ) ) AS total', FALSE)
                  ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'")
                  ->get('sales');

        if ($q->num_rows() > 0) {
            return $q->row();
        }
        return false;
    }
    
    /**
     * End Payment Details
     */


     /**
     * Total Payment
     * @return type
     * 
     */
    public function totalPayment($startDate, $endDate){
       $q = $this->db->select('sum( COALESCE( amount, 0 ) ) as total', FALSE)
                ->where("Date(date) BETWEEN '{$startDate}' AND '{$endDate}'")
                  ->get('sma_payments')->row() ;
       return ($this->db->affected_rows()? $q->total : '0.00');       
    }
   
}