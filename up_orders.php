<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="box">
    <div class="box-header">
        <h2 class="blue"><i class="fa-fw fa fa-barcode"></i><?= lang('Order')  ?> </h2>
    </div>
    <div class="box-content">
        <div class="row">
            <div id="showmsgalert"></div>
        </div>
        <?php
       // echo '<pre>';
       // print_r($upOrders[0]);
        
       //
       // $up_status_response =  unserialize($upOrders[0]->up_status_response);
       
//        print_r($up_status_response);
//        echo json_encode($up_response);
//       
//        echo '</pre>';
//        exit;
        ?>
        <div class="row">
            <div class="col-lg-12">
                <div class="table-responsive">
                    <table class="table table-bordered" id="orderlist" >
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Order Time</th>
                                <th>UP Order Id</th>
                                <th>Delivery</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Cannel</th>                                
                                <th>Customer</th>
                                <th>Rider</th>
                                <th>Rider OTP</th>
                                <th>action</th>
                            </tr>                            
                        </thead>
                        <tbody>
                        <?php
                         if(is_array($upOrders)){
                             foreach ($upOrders as $key => $order) {
                                 
                                  $up_response =  unserialize($order->up_response);
                                  
//                                  echo '<pre>';
//                                  print_r($up_response->customer->phone);
//                                  echo '</pre>';
//                                  exit;
                         ?>
                            <tr>
                                <td><?=++$i?></td>
                                <td><?=$order->up_state_timestamp?></td>
                                <td><?=$order->up_order_id?></td>
                                <td><?=$order->up_delivery_datetime?></td>
                                <td><?=$order->total?></td>
                                <td>
                                <?php
                                 
                                $btn_class = $order->up_next_status == 'Cancelled' ? 'btn-danger' : ($order->up_next_status == 'Placed' ? 'btn-info' : ($order->up_next_status == 'Completed' ? 'btn-success' : 'btn-primary'));
                                  $status_data='<div class="text-center"><div class="btn-group text-left"><button type="button" class="btn btn-xs '.$btn_class.' dropdown-toggle" data-toggle="dropdown" id="current_status_'.$order->up_order_id.'">'.$order->up_next_status.' <span class="caret"></span></button>';
                                    $status_data.='<ul class="dropdown-menu pull-right" role="menu">';
                                        foreach($upOrderStatusList as $orderstatus) {
                                            if($orderstatus!==$order->up_next_status) {
                                                $btn_text = $orderstatus == 'Cancelled' ? 'text-danger' : ($orderstatus == 'Placed' ? 'text-info' : ($orderstatus == 'Completed' ? 'text-success' : 'text-primary'));
                                                if(in_array($orderstatus, ['Acknowledged','Food Ready','Cancelled']) ) {
                                                    $status_data.='<li><button class="btn '.$btn_text.'" style="background:none" id="'.str_replace(' ','',$orderstatus).'_status_'.$order->up_order_id.'" onclick="order_status(\''.$order->up_order_id.'\',\''.$orderstatus.'\')" >'.$orderstatus.'</button></li>';
                                                } else {
                                                    $status_data.='<li><button class="btn '.$btn_text.'" style="background:none" >'.$orderstatus.'</button></li>';
                                                }//end else.                                                
                                            }//end if
                                        }//end foreach                                        
                                    $status_data.='</ul>';
                               echo $status_data.'</div></div>';
                                  //  echo form_dropdown('up_next_status', $upOrderStatusList, $order->up_next_status);
                                ?>
                                </td>
                                <td><?=$order->up_channel?></td>
                                <td onclick="view_customer('<?=$order->order_rider_id?>')" ><?=$up_response->customer->name?><br/>Mb.:<?=$up_response->customer->phone?></td>
                                <td><?php if($order->order_rider_id) { ?><a class="btn btn-xs btn-info" onclick="view_rider_info('<?=$order->order_rider_id?>')" ><?=$order->current_state?></a><?php } else { echo '<button class="btn btn-xs default">Not Assign</button>'; } ?></td>
                                <td><?=substr($up_response->customer->phone, -4)?></td>
                                <td><button class="btn btn-xs btn-primary" data-toggle="modal" data-target="#myModal" id="<?=$order->sale_id?>" >Order Details</button></td>                                
                            </tr>
                        <?php
                             } //end foreach.                             
                         }//end if
                        ?>
                        </tbody>
                    </table>    
                </div>
            </div>
        </div>
    </div>    
</div>    

<!-- Message Modal -->
<div id="myModal" class="modal" role="dialog">
    <div class="modal-dialog">
      <!-- Modal content -->
      <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal">&times;</button>
              <h4 class="modal-title" id="modeltitle"></h4>
            </div>
            <div class="modal-body">
                <h3 class="text-center" id="showmsg"></h3>
            </div>
            <div class="modal-footer">
                <button type="button" id="confirm_ok" style="display:none;" class="btn btn-success msg_model_buttons_ok"> Ok </button>
                <button type="button" id="closemodel" class="btn btn-danger msg_model_buttons_close" >Close</button>
            </div>
          </div>
    </div>
</div>
<!-- End Message model --->

<!-- Pass Status -->
<div id="pass_datamodal" class="modal" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close closemodels" data-dismiss="modal">&times;</button>
              <h4 class="modal-title" > Message</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <input type="hidden" id="up_order_id"/>
                    <input type="hidden" id="up_order_status"/>
                    <label> Message </label>
                    <input type="text" name="message" placeholder="Message"  id="status_message" class="form-control">
                </div>
            </div>
            <div class="modal-footer" id="msg_model_buttons">
                <button type="button" id="passdatastatus" class="btn btn-success "> Ok </button>
                <button type="button"  class="btn btn-danger closemodels " >Close</button>
            </div>
        </div>
    </div>
</div>
<!--  End pass Status -->

<img src="<?= $assets ?>images/loader.gif" class="loaderclass" id="pageloader">
<script>
    $(document).ready(function(){
        $('#orderlist').DataTable();
        
         $('#pageloader').hide();
         $('#confirm_ok').hide();
        // getstore();
    });
    
     // Get the modal
    var modal = document.getElementById('myModal');
    var statusmodal = document.getElementById('pass_datamodal');

    // Get the button that opens the modal
    var btn = document.getElementById("myBtn");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks on the button, open the modal 
   

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
      modal.style.display = "none";
    }

    $('#closemodel').click(function(){
        modal.style.display = "none";
    });
    
    $('.closemodels').click(function(){
        statusmodal.style.display = "none";
        $('#status_message').val('');
        
    });
    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
      if(event.target==statusmodal){
          statusmodal.style.display = "none";
      }
    }
    
   
  function order_status(order_id, order_status){
      
      $('#up_order_id').val('');
      $('#up_order_status').val('');
      $('#status_message').val('');
      $('#confirm_ok').show();
      $('#showmsg').html('Are you sure to change '+order_status+' order status?');
      $('#modeltitle').html('confirm');
      modal.style.display = "block";
        
        $('#confirm_ok').click(function(){
             modal.style.display = "none";
             statusmodal.style.display = "block";
             $('#up_order_id').val(order_id);
             $('#up_order_status').val(order_status);
        });
  }
    
    
  $('#passdatastatus').click(function(){
        statusmodal.style.display = "none";
        modal.style.display = "block";
        var order_id = $('#up_order_id').val();
        var order_status = $('#up_order_status').val();
        var status_message =  $('#status_message').val();
       // statusmodal.style.display = "none";
        $('.msg_model_buttons_ok').hide();
        $('.msg_model_buttons_close').hide();
        $('#showmsg').html('<div style="text-align:center;"><img src="<?= base_url('assets/images/ajax-loader.gif')?>" alt="please wait loading..."></div>');
        setTimeout(function(){
            update_order_status( order_id, order_status, status_message); 
        }, 500);
  });
    
    
    function update_order_status( order_id, order_status, status_message){
        $('.msg_model_buttons_close').show();
        $('#modeltitle').html('response');
        $.ajax({
           type:'ajax',
           dataType:'json',
           url:'<?= site_url("urban_piper/update_order_status/") ?>'+order_id+'/'+order_status+'/'+status_message,
           async:false,
          
           success:function(result){
               
               if(result.status=='success'){
                   
                    $('#showmsgalert').html('<div class="alert alert-success">'+result.message+'</div>');
                    $('#showmsg').html('<div class="alert alert-success">'+result.message+'</div>');
                    
                    $('#current_status_'+order_id).html(order_status);
                    $('#current_status_'+order_id).removeClass('btn-info');
                    $('#current_status_'+order_id).removeClass('btn-warning');
                    $('#current_status_'+order_id).removeClass('btn-primady');
                    $('#current_status_'+order_id).removeClass('btn-danger');
                    $('#current_status_'+order_id).addClass('btn-success');
                    order_status = (order_status == 'Food Ready') ? 'FoodReady' : order_status;
                    
                    $('#'+order_status+'_status_'+order_id).hide();
               } else {
                    $('#showmsgalert').html('<div class="alert alert-danger">'+result.message+'</div>');
                    $('#showmsg').html('<div class="alert alert-danger">'+result.message+'</div>');                    
               }
              
               
              setTimeout(function(){  modal.style.display = "none"; }, 1000);
             // setTimeout(function(){ location.reload(true); }, 2000);
               
//               modal.style.display = "block";
//               getstore();
            },error:function(){
                console.log('error');
            }     
        });
    }
    
    
    
    
    function getstore(){
    $('#confirm_ok').hide();
        $.ajax({
           type:'ajax',
           dataType:'json',
           url:'<?= site_url() ?>/urban_piper/uprbanpiper_order',
           async:false,
//           beforeSend: function(){
//               $('#pageloader').show();
//           },
           success:function(result){
               //console.log(result);
              $('#order_list').html(result);
            },error:function(){
              console.log('error');
            } 
        });
        $('#storelist').DataTable(); 
    }
    
    function store_status(){
        
        $.ajax({
           type:'ajax',
           dataType:'json',
           url:'<?= site_url("urban_piper/action/") ?>'+arguments[0]+"/"+arguments[1],
           async:false,
           success:function(result){
               
               if(result.status=='success'){
                    $('#showmsg').html(result.messages);
                    setTimeout(function(){ location.reload(true); }, 2000);                    
               } else {
                    $('#showmsg').html(result.messages);
               }
//               console.log(result);
               $('#modeltitle').html('message');
               modal.style.display = "block";
              
            },error:function(){
                console.log('error');
            }     
        });
    }
</script>    
