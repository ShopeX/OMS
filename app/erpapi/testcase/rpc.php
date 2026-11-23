<?php
class rpc extends PHPUnit_Framework_TestCase
{
    function setUp() {}


    /**
    * 商品
    */
    public function testrpc(){
        $core_http = kernel::single('base_httpclient');

        $url = 'http://192.168.41.98/erpbugfix/index.php/api';
        // $headers['Content-Type'] = 'application/json';
        $response = $core_http->set_timeout(10)->post($url, $params,$headers);
        // error_log(var_export($response,true),3,'d:response.log');
        var_dump($response);exit;   
    }


    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function purchase_create()
    {
        // 创建采购单
        $purchase = array (
          'supplier_id'     => '1',
          'operator'        => 'admin',
          'op_name'         => 'admin',
          'po_type'         => 'credit',
          'name'            => '20150710采购单',
          'emergency'       => NULL,
          'purchase_time'   => 1436512290,
          'branch_id'       => '5',
          'arrive_time'     => '3',
          'deposit'         => '101',
          'deposit_balance' => '101',
          'amount'          => NULL,
          'product_cost'    => NULL,
          'delivery_cost'   => '1',
          'memo'            => NULL,
          'items' => 
          array (
            0 => 
            array (
              'nums' => '100',
              'price' => '99.000',
              'bn' => 'test1',
              'name' => '测试商品一',
            ),
          ),
        );  

        app::get('purchase')->model('po')->savePo($purchase);

        kernel::single('console_event_trigger_purchase')->create(array('po_id'=>1), false);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function purchase_search()
    {
        $Opo = app::get('purchase')->model('po');
        $po = $Opo->dump(10, 'branch_id,out_iso_bn,po_bn');
        $branch_id = $po['branch_id'];
        $wms_id = kernel::single('ome_branch')->getWmsIdById($branch_id);
        $data = array(
            'out_order_code' => $po['out_iso_bn'],
            'stockin_bn'     => $po['po_bn'],
        );

        $result = kernel::single('console_event_trigger_purchase')->search($wms_id,$data, true);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function purchase_cancel()
    {
        $po_bn     = 'I201507101617003194';
        $branch_id = $po['branch_id'];
        $purchaseObj = kernel::single('console_event_trigger_purchase');
        $data = array(
            'io_type'=>'PURCHASE',
            'io_bn'=>$po_bn,
            'branch_id'=>1,
        );

        $result = $purchaseObj->cancel($data, true);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function purchasereturn_create()
    {
        kernel::single('console_event_trigger_purchasereturn')->create(array('rp_id'=>1), false);
    }


    public function purchasereturn_cancel()
    {
        $rpObj = app::get('purchase')->model('returned_purchase');
        $rp = $rpObj->dump(1,'memo,branch_id,rp_bn,check_status,return_status,out_iso_bn');

        $data = array(
            'io_type'=>'PURCHASE_RETURN',
            'io_bn'=>$rp['rp_bn'],
            'branch_id'=>1,
            'out_iso_bn'=>$rp['out_iso_bn'],
        );

        kernel::single('console_event_trigger_purchasereturn')->cancel($data);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function stockdump_create()
    {
        $result = kernel::single('console_iostockdata')->notify_stockdump(2,'create');
    }

    public function stockdump_cancel()
    {
        $result = kernel::single('console_iostockdata')->notify_stockdump(2,'cancel');
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function supplier_create()
    {   $supplier = app::get('purchase')->model('supplier');
        $data = $supplier->dump(array('supplier_id'=>1), 'bn,name,area,addr');
        $result = kernel::single('console_event_trigger_supplier')->create(4, $data, false);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function delivery_create()
    {
        //发货单通知单推送仓库
        ome_delivery_notice::create(1);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function delivery_cancel()
    {
        $eventLib = kernel::single('ome_event_trigger_delivery');

        $delivery = kernel::database()->selectrow("SELECT delivery_id,branch_id,delivery_bn from sdb_ome_delivery where delivery_id=1");

        $branchLib = kernel::single('ome_branch');
        $wms_id = $branchLib->getWmsIdById($delivery['branch_id']);
        $res = $eventLib->cancel($wms_id,array('outer_delivery_bn'=>$delivery['delivery_bn']),true);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function goods_add()
    {
        $succ_inner_sku = array('test1','test2','test3','test4','test5');
        $foreignSkuModel =  app::get('console')->model('foreign_sku');
        $foreignSkuModel->update(array('sync_status'=>'1'),array('inner_sku'=>$succ_inner_sku));
        $pid = array(
                array('bn'=>'test1'),
                // array('bn'=>'test2'),
                // array('bn'=>'test3'),
                // array('bn'=>'test4'),
                // array('bn'=>'test5'),
            );
        kernel::single('console_goodssync')->syncProduct_notifydata(4,$pid);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function goods_update()
    {
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function reship_create()
    {
        $reship_data = kernel::single('ome_receipt_reship')->reship_create(array('reship_id'=>1));
        $wms_id = kernel::single('ome_branch')->getWmsIdById($reship_data['branch_id']);
        $rsp_result = kernel::single('console_event_trigger_reship')->create('4', $reship_data, false);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function reship_cancel()
    {
            $reship_data = kernel::single('console_reship')->reship_data(17);

            foreach ($reship_data as $rk=>$rv) {
                $wms_id = kernel::single('ome_branch')->getWmsIdById($rk);

                kernel::single('console_event_trigger_reship')->cancel($wms_id, $rv, true);
            }

    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function response_delivery_status_update()
    {
    }
}
