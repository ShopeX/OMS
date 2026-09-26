<?php
class iostock_ctl_admin_orderreturns extends desktop_controller{
    public $name = "出入库管理";
    public $workground = "iostock_center";

    public function index(){
        $this->title = '退货入库';
        $filter = array('type_id'=>array('30'),'iostock_bn'=>array($_SESSION['bn']));
        unset($_SESSION['bn']);
        $params = array(
            'title'=>$this->title,
            'base_filter' => $filter,
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>true,
            'orderBy' => 'create_time desc',
//        仓库，入库单号、(会员姓名)、发货单号、入库时间、售后申请人、商品货号、商品名称、退货数量、退货单价、退货金额、税率
            'finder_cols'=>'branch_id,column_iostockbn,column_member_name,original_bn,create_time,oper,bn,column_name,nums,iostock_price,column_amount,cost_tax,shop_name',

        );
        $this->finder('iostock_mdl_orderreturns',$params);
    }

    function exportTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=orderreturns.csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $pObj = $this->app->model('orderreturns');
        $title1 = $pObj->exportTemplate('main');
        $title2 = $pObj->exportTemplate('item');
        echo '"'.implode('","',$title1).'"';
        echo "\n\n";
        echo '"'.implode('","',$title2).'"';
    }
}
