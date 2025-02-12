<?php

defined('BASEPATH') OR exit('No direct script access allowed');
header('Access-Control-Allow-Origin: *');
class ApiOwner extends MY_Controller {
    
    private $api_private_key = '';
    private $posVersion = '';
    private $pos_type = 'amstead';
    private $ci = '';

    public function __construct() {
        parent::__construct();
        $this->load->model('ApiOwner_model');
         $this->load->model('reports_model');
        $this->posVersion = json_decode($this->Settings->pos_version);
        $this->pos_type = $this->Settings->pos_type;
        $this->api_private_key = isset($this->Settings->api_privatekey) && !empty($this->Settings->api_privatekey) ? $this->Settings->api_privatekey : $config->config['api3_private_key'];

        $this->ci = $ci = get_instance();
        $config = $ci->config;
        $this->merchant_phone = isset($config->config['merchant_phone']) && !empty($config->config['merchant_phone']) ? $config->config['merchant_phone'] : NULL;
        
        if ($this->posVersion->version < 4.03) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 404;
            $data['current_pos_version'] = $this->posVersion->version;
            $data['pos_version'] = $this->posVersion->version;
            $data['pos_type'] = $this->pos_type;
            $data['api_access_status'] = $this->Settings->api_access ? 'Active' : 'Blocked';
            $data['mag'] = 'API required the pos version 4.03 or above.';
            echo $this->json_op($data);
            exit;
        }//end if

        if (!$this->Settings->api_access) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 405;
            $data['current_pos_version'] = $this->posVersion->version;
            $data['pos_version'] = $this->posVersion->version;
            $data['pos_type'] = $this->pos_type;
            $data['api_access_status'] = $this->Settings->api_access ? 'Active' : 'Blocked';
            $data['mag'] = 'API access is blocked.';
            echo $this->json_op($data);
            exit;
        }//end if

        if (!isset($_POST)) {
            $data['status'] = 'ERROR';
            $data['error_code'] = 101;
            $data['mag'] = 'Invalid api request method';
            $data['private_key_msg'] = 'mismatch';
            echo $this->json_op($data);
            exit;
        } else {

            $privatekey = $this->input->post('privatekey');
            $this->action = $this->input->post('action');
            
            if ($this->api_private_key == NULL) {
                $data['status'] = 'ERROR';
                $data['error_code'] = 100;
                $data['mag'] = 'POS API private key not available or generated';
                $data['private_key_msg'] = 'mismatch';
                echo $this->json_op($data);
                exit;
            } elseif ($this->api_private_key !== $privatekey) {
                $data['status'] = 'ERROR';
                $data['error_code'] = 102;
                $data['mag'] = 'Private key mismatch';
                $data['private_key_msg'] = 'mismatch';
                echo $this->json_op($data);
                exit;
            }
        }//end else
    }
    
    
     public function index(){
         $action = $this->input->post('type');
         $response = '';
        switch ($action){
            case 'sales':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date');
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->getSales($startDate , $enddate);
                  
                break;

            case 'warehouse_sales':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date');
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->warehouseSales($startDate , $enddate );
                  
                break;

         
             
            case  'warehouse_list':
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->ApiOwner_model->getWarehouseSales();
                  
                
                break;

              case  'stockall':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date');
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->allStock($startDate , $enddate );
                  
                break;

             case  'net_sales':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date');    
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->netSales($startDate , $enddate);
                  
                
                break;
             
            case  'top_sales_products':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date'); 
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->topProduct('DESC', $startDate, $enddate);
                      
                
                 break;
            
            case  'top_low_products':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date'); 
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->topProduct('ASC', $startDate, $enddate);
                    
                 break;
            
            case 'payment':
                    $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date');
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->payment($startDate , $enddate);                
                break;

            case 'livestock':
                    $productCode = $this->input->post('code');
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->getLiveStock($productCode);           
                break;

             case 'Totalpayment':
                   $startDate = $this->input->post('from_date');
                    $enddate = $this->input->post('to_date');
                    $response['status']  = 'SUCCESS';
                    $response['code']    = '200';
                    $response['data']    = $this->totalpayment($startDate , $enddate);                
                
                break;
            

            default:
                   $response['status']  = 'Invalid Requiest';
                   $response['code']    = '404';
              break;
        }
        
       
          $this->response($response);
     
    }

    /**
     * Sale Data
     * 
     * @param type $startDate
     * @param type $enddate
     * @return type
     */
    public function getSales($startDate , $enddate){
            
       $data = $this->ApiOwner_model->getSales($startDate , $enddate);
       return $data;
    }


     /**
     * warehouse Sales Details
     * @param type $startDate
     * @param type $enddate
     * @return string
     */
    public function warehouseSales($startDate , $enddate){
       $start_date = $startDate ? $startDate : NULL;
       $end_date = $enddate ? $enddate : NULL;
       $htmlcontaint = '';
       $warehouses = $this->ApiOwner_model->getWarehouseSales();
    
       foreach($warehouses as $warehouse){
         $htmlcontaint .='<div class="row" style="margin-bottom:1em;">
                                        <div class="col-xl-13 col-sm-12 mb-xl-0 mb-4">
                                            <div class="card">
                                                <div class="card-body p-3">
                                                     <div class="row">
                                                        <div class="col-12">
                                                            <div class="numbers">
                                                                <h3 class="text-sm mb-0 text-uppercase font-weight-bold text-center">'.$warehouse->name.' </h3>';
      
     
           $sql = "SELECT w.`id` as warehouse_id,  w.`name` as warehouse , 
                sum(s.`total`) net_total, 
                sum(s.`total_discount`) as  discount, 
                sum(s.`total`) as net_sale,
                sum(s.`rounding`) as rounding,
                sum(s.`shipping`) as shipping,
                sum(s.`total_tax`) as tax, 
                count(s.`reference_no`) total_bills
                    
                    FROM `sma_sales` s 
                    LEFT JOIN `sma_warehouses` w ON s.`warehouse_id` = w.`id` ";

            $where = '';

            if ($start_date) {
                $start_date = $this->sma->fld($start_date);
                $end_date = $this->sma->fld($end_date);
                $where = " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date' ";
            }

                //$where .= " AND s.`warehouse_id` IN ({$warehouse->id})";
                $where .= " AND s.`warehouse_id`= {$warehouse->id}";
               

            $sql .= $where . " and sale_status !='returned' GROUP BY s.`warehouse_id`";

            $q = $this->db->query($sql);
            
            $num = $q->num_rows();

           
            
           

            if ($num > 0) {
                $total_gross_sale = 0;
                $total_discount = 0;
                $total_net_sale = 0;
                $calculate_total_tax = 0;
                $cal_total_bills = 0;
                $total_sold_items = 0;
                $total_due = 0;
                $total_return = 0;
                $total_sales = 0;
                $total_withcash = 0;
                $total_withoutcash = 0;


                foreach ($q->result() as $row) {
                    $WithCash = 0;
                    $WithoutCash = 0;
                    $Warehouse_id = $row->warehouse_id;
                    $SqlCash = "SELECT p.amount, p.paid_by FROM `sma_sales` s inner join `sma_payments` p on s.id=p.sale_id where s.warehouse_id='$Warehouse_id' and p.paid_by!='gift_card'"; //and  p.paid_by='cash' 
                    if ($start_date) {
                        $SqlCash .= " and DATE(s.date) BETWEEN '$start_date' AND '$end_date' ";
                    }
                    $Rescash = $this->db->query($SqlCash);
                    foreach ($Rescash->result() as $rowcash) {
                        if ($rowcash->paid_by == 'cash') {
                            $WithCash += $rowcash->amount;
                        } else {
                            $WithoutCash += $rowcash->amount;
                        }
                    }
                    $net_total = $row->net_total;




                    $gross_sale = $row->net_total + $row->discount + $row->tax;

                    $duesales = $this->reports_model->getreport($start_date, $end_date, 'due', $row->warehouse_id);
                    $returnsales = $this->reports_model->getreport($start_date, $end_date, 'return', $row->warehouse_id);
                    $partialamt = $this->reports_model->getreportbalance($start_date, $end_date, $row->warehouse_id);
                    $pendingamount = $this->reports_model->getreport($start_date, $end_date, 'pending', $row->warehouse_id);
                    $tax = $row->tax + $returnsales->tax;
                    //$net_sale   = $row->net_sale+$returnsales->net_sale+$tax;
                    $net_sale = ($row->net_sale + $row->shipping + $row->rounding) + ($returnsales->net_sale + $returnsales->rounding ) + $tax;

                    $total_dueamt = $duesales->total + $duesales->total_discount + $partialamt + $pendingamount->total; //
                    $total_returnAmt = str_replace('-', '', $returnsales->total); // + str_replace('-', '', $returnsales->total_discount);
                    // $total_returnAmt;
                    $discount = $row->discount + $returnsales->total_discount;
                    $total_sales = $row->Total_sales + $total_returnAmt;
                    $htmlcontaint .='<!--Gross Total-->	
                                     <p class="text-sm mb-0 text-uppercase font-weight-bold"> Gross Sale</p>

                                     <h5 class="font-weight-bolder">
                                        <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($gross_sale, 2) . '/-</span>
                                     </h5>
                                                                
                                     <!--Discount-->	
                                     <p class="text-sm mb-0 text-uppercase font-weight-bold"> Discount</p>

                                     <h5 class="font-weight-bolder">
                                        <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span> ' . number_format($discount, 2) . '/-</span>
                                     </h5>

                                     <!--Due Amount-->	
                                     <p class="text-sm mb-0 text-uppercase font-weight-bold"> Due Amount</p>

                                      <h5 class="font-weight-bolder">
                                        <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($total_dueamt, 2) . '/-</span>
                                      </h5>


                                      <!--Return Amount-->	
                                      <p class="text-sm mb-0 text-uppercase font-weight-bold"> Return Amount</p>

                                      <h5 class="font-weight-bolder">
                                         <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . (($total_returnAmt != 0) ? number_format($total_returnAmt, 2) : 0) . '/-</span>
                                      </h5>

                                      <!--Net Sale -->	
                                      <p class="text-sm mb-0 text-uppercase font-weight-bold"> Net Sale </p>

                                      <h5 class="font-weight-bolder">
                                        <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($net_sale, 2) . '/-</span>
                                      </h5>
                                                                
                                      <!--Cash -->	
                                      <p class="text-sm mb-0 text-uppercase font-weight-bold"> Cash</p>

                                      <h5 class="font-weight-bolder">
                                        <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($WithCash, 2) . '/-</span>
                                      </h5>

                                      <!--Without Cash -->	
                                      <p class="text-sm mb-0 text-uppercase font-weight-bold"> Without Cash</p>

                                       <h5 class="font-weight-bolder">
                                           <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($WithoutCash, 2) . '/-</span>
                                       </h5>


                                       <!--Total Tax -->	
                                       <p class="text-sm mb-0 text-uppercase font-weight-bold"> Total Tax </p>

                                       <h5 class="font-weight-bolder">
                                           <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($tax, 2) . '/-</span>
                                       </h5>

                                       <!--Total Invoice -->	
                                       <p class="text-sm mb-0 text-uppercase font-weight-bold"> Total Invoice </p>

                                       <h5 class="font-weight-bolder">
                                         <i class="fa fa-inr text-lg opacity-10" aria-hidden="true"></i> <span > ' . number_format($row->total_bills) . '/-</span>
                                       </h5>';
                }
       
            }
            $htmlcontaint .= '</div>
                            </div>    
                         </div>
                       </div>
                     </div>
                   </div>
                 </div>' ;
       
        } 

       return $htmlcontaint;
    }        
       


     
    /**
     * Warehouse Stock 
     * 
     * @param type $startDate
     * @param type $enddate
     */
    public function allStock($startDate , $enddate){
       $start_date = $startDate ? $startDate : NULL;
       $end_date = $enddate ? $enddate : NULL;
       
        $wdata = $this->reports_model->warehouseProductsStock($passwarehouse);

        $wps = $wdata['products'];
        $ws = $wdata['warehouse'];
        
       
        $datatable = '';
        $str_wp = 'wpq';
        
         $total = array();
         $totalstock = 0;
         if (is_array($wps)) {
           $datatableRow ='';
              foreach ($wps as $pid => $wpdata) {
                        $datatable.='<tr>';
                             $datatable.='<td>';
                                  $datatable.= $wpdata['name'] .' ('. $wpdata['code'].')';
                                  $datatable.='<table class="table">';
                                       $datatable.='<tr>';
                                          foreach ($ws as $whn) {
                                            $datatable.= "<th>$whn</th>";
                                           }
                                           $datatable.= '<th>Total</th>';
                                       $datatable.='</tr>';
                                       $datatable.='<tr>';
                                            $stock = 0;
                                            foreach ($ws as $wid => $whns) {
                                                   $qty = ($wpdata[$str_wp][$wid]) ? number_format($wpdata[$str_wp][$wid]) : 0;
                                                   $datatable.= '<td align="center">' . $qty . '</td>';
                                                   $stock += $qty;
                                                   $total[$whns] += $qty;
                                            } 
                                            $datatable.= '<td align="center">' . $stock . '</td>'; 
                                       $datatable.='</tr>';
                                  $datatable.='</table>';
                                  
                             $datatable.='</td>';
                        $datatable.= '</tr>';   
                 
                  
                
              }
            
         }
          return $datatable;
    }


      /**
     * Net Sales 
     * 
     * @param type $response
     */
    public function netSales($startDate , $enddate){
      $sales =   $this->ApiOwner_model->getNetsales($startDate , $enddate);
        
      $returnSales =  $this->ApiOwner_model->getreport($startDate , $enddate,'returned');
      $tax = $sales->tax + $returnSales->tax;
      
      $net_sale = ($sales->net_sale + $sales->shipping + $sales->rounding) + ($returnSales->net_sale + $returnSales->rounding ) + $tax;

      
      return ['netSales' => round($net_sale,2)];      
    
    }


     /**
     * Get Top Products
     * 
     * @param type $order
     * @return string
     * @param type $order
     * @return string
     */
    public function topProduct($order, $startDate, $enddate){
     
       $getData =  $this->ApiOwner_model->getTopProducts($order, $startDate, $enddate);
       $html = '';
       foreach($getData as $item){
           $html.='<tr>';
                $html.='<td> '.$item->product_name.' ('.$item->product_code.')'.' </td>';
                $html.='<td> '.number_format($item->qty,2).' </td>';
           $html.='</tr>';
       }
       
       return $html;
    }        


     /**
     * 
     * @param type $startDate
     * @param type $enddate
     * @return type
     */
    public function payment($startDate , $enddate){
        $ccsales = $this->ApiOwner_model->getTodayCCSales($startDate , $enddate);       //Paid By CC
        $dcsales = $this->ApiOwner_model->getTodayDCSales($startDate , $enddate);               //Paid By DC
        
        $gcsales = $this->ApiOwner_model->getTodayGiftCardSales($startDate , $enddate);         //Paid By GiftCard
        $othersales = $this->ApiOwner_model->getTodayOtherSales($startDate , $enddate);         //Paid By Others
        $cashsales = $this->ApiOwner_model->getTodayCashSales($startDate , $enddate);           //Paid By Cash
        $chsales = $this->ApiOwner_model->getTodayChSales($startDate , $enddate);               //Paid BY Cheque
        $pppsales = $this->ApiOwner_model->getTodayPPPSales($startDate , $enddate);
        $stripesales = $this->ApiOwner_model->getTodayStripeSales($startDate , $enddate);
        $authorizesales = $this->ApiOwner_model->getTodayAuthorizeSales($startDate , $enddate);
       
        $depositsales = $this->ApiOwner_model->getTodayDepSales( $startDate , $enddate);
        
        
        $duepayment = $this->ApiOwner_model->getTodayDueSales($startDate , $enddate);
        $duepartial = $this->ApiOwner_model->getcalpartial($startDate , $enddate); //20-03-19
       
        $totalsales = $this->ApiOwner_model->getTodaySales($startDate , $enddate);
        $totalsalespaid = $this->ApiOwner_model->getTodaySalesPaid($startDate , $enddate);
        $refunds = $this->ApiOwner_model->getTodayRefunds($startDate , $enddate);
        $expenses = $this->ApiOwner_model->getTodayExpenses($startDate , $enddate);
        $deposit_received = $this->ApiOwner_model->getTodayDeposit($startDate , $enddate);
       
        
        
        $respose = [
          'cheque_payment'          => ($chsales->paid? number_format($chsales->paid,2) : '0.00'),
          'credit_card_payment'     => ($ccsales->paid? number_format($ccsales->paid,2) : '0.00'),
          'debit_card_payment'      => ($dcsales->paid? number_format($dcsales->paid,2) : '0.00'),
          'gift_card_sale'          => ($gcsales->paid? number_format($gcsales->paid,2) : '0.00'),
          'deposit_payment'         => ($depositsales->paid? number_format($depositsales->paid,2) : '0.00'),
          'other_sale'              => ($othersales->paid? number_format($othersales->paid,2) : '0.00'),
          'cash_payment'            => ($cashsales->paid? number_format($cashsales->paid,2) : '0.00'), 
          'total_paid'              => ($totalsalespaid->paid? number_format($totalsalespaid->paid,2) : '0.00'),  
          'total_sales'             => ($totalsales->total ?number_format($totalsales->total+ str_replace("-", '', $refunds->returned),2) : '0.00'),  
          'total_due'               => (($duepayment->total  + $duepartial->partial_due)? number_format($duepayment->total  + $duepartial->partial_due,2) :'0.00'),  
          'refunds'                 => ($refunds->returned ? number_format($refunds->returned,2) : '0.00'),    
          'expenses'                => ($expenses->total ? number_format($expenses->total,2) : '0.00'), 
          'deposit_received'        =>  ($deposit_received->deposit_amount ?number_format($deposit_received->deposit_amount,2) : '0.00'),
            
             
            
       ];

        $paymentOptions = ['paytm' => 'PAYTM', 'neft' => 'NEFT', 'google_pay' => 'Googlepay', 'swiggy' => 'swiggy', 'zomato' => 'zomato', 'ubereats' => 'ubereats', 'magicpin' => 'magicpin', 'complimentary' => 'complimentry'];
       
        foreach ($paymentOptions as $payOpt_key => $payOpt) {
                $payOpt_key = $this->ApiOwner_model->getTodayPaymentOptionSales($payOpt, $startDate , $enddate);
          
                 $respose[$payOpt]=($payOpt_key->paid?number_format($payOpt_key->paid,2) :'0.00');
        }//end foreach.

    
        return $respose;
    }

    /**
     * 
     * @param type $startDate
     * @param type $enddate
     * @return typeTotal Payment
     */
    public function totalpayment($startDate , $enddate){
     $total =    $this->ApiOwner_model->totalPayment($startDate , $enddate);
     return ['total' => round($total,2)]; 
    }
    

    public function response($response){
      echo json_encode($response);
    }


    /**
     * Live Data
     */
    public function getLiveStock($productCode){
       $pQty =  $this->db->select('quantity')->where(['code' =>$productCode])->get('sma_products')->row();
       return ($this->db->affected_rows()?['qty'=>round($pQty->quantity,2)] : ['qty'=>'0']);
       
       
    }
    
}