<?php
class goldentra_ctl_admin_daily_goldprice extends desktop_controller
{
    public $workground = 'goldentra_center';

	public function index(){

        $actions[] = array(
            'label'   => '批量更新金价日期',
            'confirm' => '你确定要对勾选的记录更新金价日期为今天吗？',
            'submit'  => 'index.php?app=goldentra&ctl=admin_daily_goldprice&act=updatePriceDate',
        );

		$params = array(
            'title'=>'每日金价',
            'actions' => $actions,
            'base_filter' => $base_filter,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_filter'=>true,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
        );

         $this->finder('goldentra_mdl_daily_goldprice',$params);
	}

    public function edit($goldprice_id){
        $dailyGoldPriceObj = app::get('goldentra')->model('daily_goldprice');
        $goldprice_detail = $dailyGoldPriceObj->dump($goldprice_id,'*');
        $this->pagedata['goldprice_detail'] = $goldprice_detail;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->page('admin/daily_goldprice/edit.html');
    }

    public function save(){
        $this->begin('index.php?app=goldentra&ctl=admin_daily_goldprice&act=index');

        $dailyGoldPriceObj = app::get('goldentra')->model('daily_goldprice');
        $opObj = app::get('ome')->model('operation_log');

        if($_POST['gold_price']){
            $data['gold_price']	=	$_POST['gold_price'];
        }

        if($_POST['gold_date']){
            $data['price_date'] =   strtotime($_POST['gold_date']);
        }

        if ($_POST['goldprice_id']){
            $data['goldprice_id'] = $_POST['goldprice_id'];
        }
        $data['up_time'] = time();

        $dailyGoldPriceObj->save($data);
        $opObj->write_log('daily_goldprice_edit@goldentra', $data['goldprice_id'], '编辑金价为'.$data['gold_price']);
        $this->end(true, '操作成功');
    }

    public function async_update_pricetag($shop_bn)
    {

        $result = kernel::single('goldentra_daily_goldprice')->getMaterialListOfUpdate($shop_bn);

        if (empty($result)) {
            die('没有物料需要更新');
        }

        list ($shop_id, $price, $material_list) = $result;
        $this->pagedata['shop_id'] = $shop_id;
        $this->pagedata['price'] = $price;
        $this->pagedata['material_list_json'] = json_encode($material_list);
        $this->page('admin/daily_goldprice/async_update_pricetag.html');
    }

    public function updatePricetag()
    {
        $shop_id = $_POST['shop_id'];
        $price = $_POST['price'];
        $material_rows = json_decode($_POST['material_rows'], true);

        kernel::single('goldentra_daily_goldprice')->updatePricetag($shop_id, $price, $material_rows);

        $result = array(
            'doFail'=> 0,
            'doSucc'=> count($material_rows),
            'dealResult'=> count($material_rows),
        );
        echo json_encode($result);
    }

    public function updatePriceDate(){
        
        $this->begin('index.php?app=goldentra&ctl=admin_daily_goldprice&act=index');

        $dailyGoldPriceObj = app::get('goldentra')->model('daily_goldprice');
        $opObj = app::get('ome')->model('operation_log');

        if ($_POST['goldprice_id']){
            foreach ($_POST['goldprice_id'] as $k => $id) {
                $updateSdf = [
                    'price_date' => strtotime(date('Y-m-d')),
                    'up_time' => time(),
                ];
                $dailyGoldPriceObj->update($updateSdf, ['goldprice_id' => $id]);
                $opObj->write_log('daily_goldprice_edit@goldentra', $id, '更新金价日期为'.date('Y-m-d H:i:s'));
            }
        }
        
        $this->end(true, '操作成功');
    }

}
