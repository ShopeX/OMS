<?php
class regulation extends PHPUnit_Framework_TestCase
{
    function setUp() {
    
    }

    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function fgetlist_csv(&$data) 
    {
        $result = kernel::single('inventorydepth_mdl_shop_frame')->fgetlist_csv($data,array('shop_id'=>'680278fc7422e669669276c47310bb4b'),0);
        if ($result === true) {
            $this->fgetlist_csv($data);
        }
        return $data;
    }

    public function testRun(){

        $result = $this->items_all_get(array('approve_status'=>'onsale'),'680278fc7422e669669276c47310bb4b',100,50);
        echo "<pre>"; print_r($result);exit;

        $result = kernel::single('inventorydepth_rpc_request_shop_frame')->approve_status_list_update($approve_status,'680278fc7422e669669276c47310bb4b');
        echo "<pre>"; print_r($result);exit;
    }

    public function items_all_get($filter=array(),$shop_id='0c922f9b185d4086379c489a0afd7435',$offset=1,$limit=200)
    {
        $timeout = 20;

        if(!$shop_id) return false;

        $param = array(
                'page_no'        => $offset,
                'page_size'      => $limit,
                'fields'         => 'iid,outer_id,bn,num,title,default_img_url,modified,detail_url,approve_status,skus,price,barcode ',
            );
        
        $param = array_merge((array)$param,(array)$filter);
        
        $api_name = 'store.items.all.get';

        return kernel::single('ome_rpc_request')->call($api_name,$param,$shop_id,$timeout); 
    }
}
