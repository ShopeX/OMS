<?php
/**
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_finder_shop {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'config,shop_type,name,shop_bn,business_type';
    public static $shop_regu_apply;

    function __construct($app)
    {
        $this->app = $app;

        $this->_render = app::get('inventorydepth')->render();
    }

    public $column_operator = '操作';
    public $column_operator_order = 1;
    public $column_operator_width = 180;
    public function column_operator($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $shop_name = addslashes($row['name']);

        $return = '';

        // 下载
        if (inventorydepth_shop_api_support::items_all_get_support($row[$this->col_prefix .'shop_type'],$row[$this->col_prefix . 'business_type']) 
            && $row[$this->col_prefix . 'business_type']!='maochao') {
            $src = app::get('desktop')->res_full_url.'/bundle/download.gif';
            
            $downloadUrl = "index.php?app=inventorydepth&ctl=shop&act=download_page&shop_id={$row['shop_id']}&downloadType=shop";

            if(in_array($row[$this->col_prefix .'shop_type'], array('dewu'))){
                $downloadUrl = "index.php?app=inventorydepth&ctl=shop&act=downloadAllGoods&p[0]=". $row['shop_id'] .'&finder_id='.$finder_id;
            }

            // 多请求并发下载
            if (in_array($row[$this->col_prefix .'shop_type'], ['360buy'])) {
                $downloadUrl = "index.php?app=inventorydepth&ctl=shop&act=downloadPageV2&shop_id={$row['shop_id']}&downloadType=shop";
            }

            $downloadBtn = <<<EOF
            <a style="margin:5px;padding:5px;background:url('{$src}') no-repeat scroll 50% 50%;" href="{$downloadUrl}" target="dialog::{title:'下载{$shop_name}店铺商品',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}" title="下载{$shop_name}店铺商品"></a>
EOF;



            $return .= $downloadBtn;
        } else {
            $return .= <<<EOF
            <a style="margin:5px;padding:5px;"></a>
EOF;
        }

        $warehouseLib = kernel::single('purchase_warehouse');
        $isVopSc      = $warehouseLib->isVopSc($row[$this->col_prefix .'shop_type']);
        #判断是否显示购物小车
        if ($isVopSc || inventorydepth_shop_api_support::stock_get_not_support($row[$this->col_prefix .'shop_type'])) {
            $src = app::get('desktop')->res_full_url.'/bundle/lorry.gif';
            $return .= <<<EOF
        <a style="margin:5px;padding:10px;background:url('{$src}') no-repeat scroll 50% 50%;" title="更新{$shop_name}店铺所有货品库存" target="dialog::{title:'更新{$shop_name}店铺所有货品库存',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}" href='index.php?app=inventorydepth&ctl=shop_adjustment&act=uploadPage&p[0]={$row["shop_id"]}'></a>
EOF;
        } else {
            $return .= <<<EOF
            <a style="margin:5px;padding:10px;"></a>
EOF;
        }

        $src = app::get('desktop')->res_full_url . '/bundle/cloud_upload.png';
        $return .= <<<EOF
        <a style="margin:5px;padding:10px;background:url('{$src}') no-repeat scroll 50% 50%;" title="传上{$shop_name}店铺指定货品库存" target="dialog::{title:'传上{$shop_name}店铺指定货品库存',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}" href='index.php?app=inventorydepth&ctl=shop_adjustment&act=displayAssignUpload&shop_id={$row["shop_id"]}'></a>
EOF;

        //下载缓存商品（猫超店铺类型不支持，不展示按钮）
        $is_show_download = true;
        if($row[$this->col_prefix .'shop_type'] == 'taobao' && $row[$this->col_prefix . 'business_type'] == 'maochao'){
            $is_show_download = false;
        }
        
        // 缓存商品下载
        if ($is_show_download) {
            $downloadUrl = "index.php?app=inventorydepth&ctl=shop&act=pageSync&shop_id={$row['shop_id']}&downloadType=shop";
            $return .= <<<EOF
            <a style="margin:5px;padding:5px;" href="{$downloadUrl}" target="dialog::{title:'下载{$shop_name}店铺 缓存商品',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}" title="下载{$shop_name}店铺 缓存商品">缓存商品下载</a>
EOF;
        }
        return $return;
    }

    /*
    public $column_shop_url = '店铺URL';
    public $column_in_list = false;
    public function column_shop_url($row)
    {
        $config = unserialize($row[$this->col_prefix.'config']);

        $url = ('http://' == substr($config['url'], 0,7)) ? $config['url'] : 'http://'.$config['url'];
        return <<<EOF
        <a target='_blank' href='{$url}'>{$url}</a>
EOF;
    }*/

    public $column_request = '自动回写库存';
    public $column_request_order = 2;
    public $column_request_width = 100;
    public function column_request($row)
    {
        $request = kernel::single('inventorydepth_shop')->getStockConf($row['shop_id']);

        if ($request == 'true') {
            $word = $this->app->_('开启');
            $color = 'green';
            $title = '点击关闭向该店铺自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop&act=set_request&p[0]=false&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }else{
            $word = $this->app->_('关闭');
            $color = '#a7a7a7';
            $title = '点击开启向该店铺自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop&act=set_request&p[0]=true&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }

        //不支持库存回写的店铺类型
        if(in_array($row['shop_type'], inventorydepth_shop_api_support::$no_write_back_stock)) {
            return '<span title="该平台不支持库存回写">' . $word . '</span>';
        }
        
        return <<<EOF
        <a style="background-color:{$color};float:left;text-decoration:none;" href="{$href}"><span title="{$title}" style="color:#eeeeee;padding:2px;">&nbsp;{$word}&nbsp;</span></a>
EOF;
    }
    
    /*
    public $column_frame = '自动上下架';
    public $column_frame_order = 3;
    public $column_frame_width = 100;
    public function column_frame($row)
    {
        $request = kernel::single('inventorydepth_shop')->getFrameConf($row['shop_id']);

        if ($request == 'true') {
            $word = $this->app->_('开启');
            $color = 'green';
            $title = '点击关闭向该店铺自动进行上下架管理功能';
            $href = 'index.php?app=inventorydepth&ctl=shop&act=set_frame&p[0]=false&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }else{
            $word = $this->app->_('关闭');
            $color = '#a7a7a7';
            $title = '点击开启向该店铺自动进行上下架管理功能';

            $href = 'index.php?app=inventorydepth&ctl=shop&act=set_frame&p[0]=true&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }

        return <<<EOF
        <a style="background-color:{$color};float:left;text-decoration:none;" href="{$href}"><span title="{$title}" style="color:#eeeeee;padding:2px;">&nbsp;{$word}&nbsp;</span></a>
EOF;
    }*/

    public $column_skus_count = '货品总数';
    public $column_skus_count_order = 40;
    public function column_skus_count($row)
    {
        if (!inventorydepth_shop_api_support::items_all_get_support($row['shop_type'],$row[$this->col_prefix . 'business_type'])) {
            //return '-';
        }

        $count = $this->app->model('shop_skus')->count(array('shop_id'=>$row['shop_id']));

        return <<<EOF
        <a href='index.php?app=inventorydepth&ctl=shop_adjustment&act=index&filter[shop_id]={$row["shop_id"]}&source_page=shop'>{$count}</a>
EOF;
    }

    public $column_items_count = '商品总数';
    public $column_items_count_order = 30;
    public function column_items_count($row)
    {
        if (!inventorydepth_shop_api_support::items_all_get_support($row['shop_type'],$row[$this->col_prefix . 'business_type'])) {
            //return '-';
        }

        $count = $this->app->model('shop_items')->count(array('shop_id'=>$row['shop_id']));

        return <<<EOF
        <a href='index.php?app=inventorydepth&ctl=shop_frame&act=index&filter[shop_id]={$row["shop_id"]}&source_page=shop'>{$count}</a>
EOF;
    }

    public $column_stock_regulation = '库存更新规则';
    public $column_stock_regulation_order = 29;
    public function column_stock_regulation($row)
    {
        $regulation_id = $this->app->model('regulation_apply')->select()->columns('regulation_id')
                                        ->where('shop_id=?',$row['shop_id'])
                                        ->where('type=?','1')
                                        ->where('`condition`=?','stock')
                                        ->where('`using`=?','true')
                                        ->instance()->fetch_one();

        $rr = $this->app->model('regulation')->select()->columns('regulation_id,heading')
                ->where('regulation_id=?',$regulation_id)
                ->where('`using`=?','true')
                ->instance()->fetch_row();
        if($rr){
        return <<<EOF
        <a href="index.php?app=inventorydepth&ctl=regulation&act=dialogEdit&p[0]={$rr['regulation_id']}&finder_id={$_GET['_finder']['finder_id']}" target="dialog::{title:'修改规则',width:800}">{$rr['heading']}</a>
EOF;
        }else{
            $src = app::get('desktop')->res_full_url.'/bundle/btn_add.gif';
            $shop_bn = $row[$this->col_prefix.'shop_bn'];
           return <<<EOF
            <div><a title="添加规则" target="dialog::{title:'添加规则'}" href="index.php?app=inventorydepth&ctl=regulation&act=dialogAdd&p[0]={$row['shop_id']}&p[1]={$shop_bn}&finder_id={$_GET['_finder']['finder_id']}"><img src={$src} ></a></div>
EOF;
        }
    }

    /*public $column_offline_stock_regulation = '门店库存更新规则';
    public $column_offline_stock_regulation_order = 29;
    public $column_offline_stock_regulation_width = '150';
    public function column_offline_stock_regulation($row)
    {
        $regulation_id = $this->app->model('regulation_apply')->select()->columns('regulation_id')
                                        ->where('shop_id=?',$row['shop_id'])
                                        ->where('type=?','3')
                                        ->where('`condition`=?','stock')
                                        ->where('`using`=?','true')
                                        ->instance()->fetch_one();

        $rr = $this->app->model('regulation')->select()->columns('regulation_id,heading')
                ->where('regulation_id=?',$regulation_id)
                ->where('`using`=?','true')
                ->instance()->fetch_row();
        if($rr){
        return <<<EOF
        <a href="index.php?app=inventorydepth&ctl=regulation&act=dialogEdit&p[0]={$rr['regulation_id']}&finder_id={$_GET['_finder']['finder_id']}&type=3" target="dialog::{title:'修改规则',width:800}">{$rr['heading']}</a>
EOF;
        }else{
            $src = app::get('desktop')->res_full_url.'/bundle/btn_add.gif';
            $shop_bn = $row[$this->col_prefix.'shop_bn'];
           return <<<EOF
            <div><a title="添加规则" target="dialog::{title:'添加规则'}" href="index.php?app=inventorydepth&ctl=regulation&act=dialogAdd&p[0]={$row['shop_id']}&p[1]={$shop_bn}&finder_id={$_GET['_finder']['finder_id']}&type=3"><img src={$src} ></a></div>
EOF;
        }
    }*/
    
    /*
    public $column_frame_regulation = '上下架规则';
    public $column_frame_regulation_order = 30;
    public function column_frame_regulation($row)
    {
        $regulation_id = $this->app->model('regulation_apply')->select()->columns('regulation_id')
                                        ->where('shop_id=?',$row['shop_id'])
                                        ->where('type=?','1')
                                        ->where('`condition`=?','frame')
                                        ->where('`using`=?','true')
                                        ->instance()->fetch_one();

        $rr = $this->app->model('regulation')->select()->columns('regulation_id,heading')
                ->where('regulation_id=?',$regulation_id)
                ->where('`using`=?','true')
                ->instance()->fetch_row();
        if($rr){
        return <<<EOF
        <a href="index.php?app=inventorydepth&ctl=regulation&act=dialogEdit&p[0]={$rr['regulation_id']}&finder_id={$_GET['_finder']['finder_id']}" target="dialog::{title:'修改规则',width:900}">{$rr['heading']}</a>
EOF;
        }else{
            $src = app::get('desktop')->res_full_url.'/bundle/btn_add.gif';
            $shop_bn = $row[$this->col_prefix.'shop_bn'];
           return <<<EOF
            <div><a title="添加规则" target="dialog::{title:'添加规则',width:900}" href="index.php?app=inventorydepth&ctl=regulation&act=dialogAdd&p[0]={$row['shop_id']}&p[1]={$shop_bn}&p[2]=frame&finder_id={$_GET['_finder']['finder_id']}"><img src={$src} ></a></div>
EOF;
        }
    }*/

    public $column_supply_branches = '供货仓';
    public $column_supply_branches_order = 90;
    public $column_supply_branches_width = 280;
    public function column_supply_branches($row)
    {
        $branches = kernel::single('inventorydepth_shop')->getBranchByshop($row[$this->col_prefix.'shop_bn']);
        
        $branchList = app::get('ome')->model('branch')->getList('name',array('branch_bn'=>$branches));
        if ($branchList) {
            $branches = array_map('current', $branchList);
        }
        if ($branches) {
            $str = implode('</span>、<span style="color:#0000ff">', $branches);
            $htmlContent = '<span style="color:#0000ff">'.$str.'</span>';
            $html = '<a href="index.php?app=inventorydepth&ctl=shop&act=displayBranchRelation&p[0]='.$row[$this->col_prefix . 'shop_bn'].'&p[1]='.$row['shop_id'].'" target="dialog::{width:800,height:440,title:\'设置绑定关系\'}"><span style="color:#0000ff">' . $htmlContent . '</span></a>';
            return '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;" class="desc-tip" onmouseover="bindFinderColTip(event);">' . $html . '<textarea style="display:none;"><h3>店铺【<span style="color:red;">' . $row[$this->col_prefix . 'name'] . '</span>】供货仓库</h3>' . $html . '</textarea></div>';
        } else {
            $html = '<a href="index.php?app=inventorydepth&ctl=shop&act=displayBranchRelation&p[0]='.$row[$this->col_prefix . 'shop_bn'].'&p[1]='.$row['shop_id'].'" target="dialog::{width:800,height:440,title:\'设置绑定关系\'}"><div style="color:red;font-weight:bold;" onmouseover="bindFinderColTip(event);" rel="请先去仓库管理里绑定仓库与店铺关系，否则将影响库存回写！！！">无仓库供货</div></a>';
            return $html;
        }
    }

    public $column_offline = '云店门店';
    public $column_offline_order = 100;
    public function column_offline($row) 
    {
        if(!in_array($row[$this->col_prefix .'shop_type'], array('ecos.ecshopx'))) {
            return '';
        }
        $offline = app::get('ome')->model('shop_onoffline')->getList('off_id', ['on_id'=>$row['shop_id']]);
        $o2oStoreList = app::get('o2o')->model('store')->getList('shop_id,store_id,store_bn,name',['shop_id'=>array_column($offline,'off_id')]);
        $o2oStoreName = array_column($o2oStoreList,'name');
        if($offline) {
            $html = '<a href="index.php?app=inventorydepth&ctl=shop&act=displaShopOnoffline&p[0]='.$row[$this->col_prefix . 'shop_bn'].'&p[1]='.$row['shop_id'].'" target="dialog::{width:800,height:440,title:\'设置绑定关系\'}"><span style="color:#0000ff">' . implode('</span>、<span style="color:#0000ff">', $o2oStoreName) . '</span></a>';
            return '<div class="desc-tip" onmouseover="bindFinderColTip(event);">' . $html . '<textarea style="display:none;"><h3>店铺【<span style="color:red;">' . $row[$this->col_prefix . 'name'] . '</span>】云店门店</h3>' . $html . '</textarea></div>';
        }else{
            $html = '<a href="index.php?app=inventorydepth&ctl=shop&act=displaShopOnoffline&p[0]='.$row[$this->col_prefix . 'shop_bn'].'&p[1]='.$row['shop_id'].'" target="dialog::{width:800,height:440,title:\'设置绑定关系\'}"><div style="color:red;font-weight:bold;" onmouseover="bindFinderColTip(event);" rel="请先去门店管理里绑定门店与网店关系，否则将影响库存回写！！！">无云店门店</div></a>';
            return $html;
        }
    }

    public $detail_operation_log = '操作日志';
    public function detail_operation_log($shop_id)
    {
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $filter = array('obj_type' => 'shop','obj_id' => $shop_id);
        $optLogModel->defaultOrder = ' log_id desc ';
        $optLogList = $optLogModel->getList('*',$filter,0,200);
        foreach ($optLogList as &$log) {
            $log['operation'] = $optLogModel->get_operation_name($log['operation']);
        }
        
        $this->_render->pagedata['optLogList'] = $optLogList;
        return $this->_render->fetch('finder/shop/operation_log.html');
    }

}
