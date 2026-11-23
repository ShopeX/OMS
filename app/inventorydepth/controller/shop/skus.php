<?php
/**
 * 货品明细controller
 *
 *
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_ctl_shop_skus extends desktop_controller {

    var $workground = 'goods_manager';
    var $defaultWorkground = 'goods_manager';
    
    function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * 货品明细列表
     *
     * @return void
     * @author
     **/
    public function index()
    {
        $params = array(
            'title' => $this->app->_('店铺货品明细'),
            'actions' => array(
                //0 => array('label'=>$this->app->_('下载商品'),'target'=>'dialog::{}','submit'=>'index.php?app=inventorydepth&ctl=shop&act=download_page&downloadType=iid'),
                1 => array('label'=>$this->app->_('开启回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=true','target'=>'refresh'),
                2 => array('label'=>$this->app->_('关闭回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=false','target'=>'refresh'),
                //3 => array('label' => $this->app->_('开启自动上下架'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_frame&p[0]=true','target'=>'refresh'),
                //4 => array('label' => $this->app->_('开启自动上下架'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_frame&p[0]=false','target'=>'refresh'),
            ),
            'use_buildin_export' => true,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'base_filter' => $_POST,
        );

        $this->finder('inventorydepth_mdl_shop_skus',$params);
    }

    /**
     * 列表TAB页
     *
     * @return void
     * @author
     **/
    public function _views()
    {
        $views = array(
            0 => array('label'=>$this->app->_('全部'),'addon'=>'','href'=>'','filter'=>''),
            1 => array('label'=>$this->app->_('已关联'),'addon'=>'','href'=>'','filter'=>array('mapping'=>1)),
            2 => array('label'=>$this->app->_('未关联'),'addon'=>'','href'=>'','filter'=>array('mapping'=>0)),
            3 => array('label'=>$this->app->_('货号为空'),'addon'=>'','href'=>'','filter'=>array('shop_product_bn'=>'nobn')),
            4 => array('label'=>$this->app->_('货号重复'),'addon'=>'','href'=>'','filter'=>array('shop_product_bn'=>'repeat')),
        );

        $skusModel = $this->app->model('shop_skus');
        foreach ($views as $key=>&$view) {
            $view['filter']['shop_id'] = $_REQUEST['shop_id'];
            $view['addon'] = $skusModel->count($view['filter']);
            $view['href'] = 'index.php?app=inventorydepth&ctl=shop_skus&act=index&view='.$key.'&shop_id='.$_REQUEST['shop_id'];
        }
        return $views;
    }

    /**
     * 回写设置
     *
     * @return void
     * @author
     **/
    public function set_request($config = 'true',$id = null)
    {
        if($id) $_POST['id'][] = $id;
        
        //shop_id
        if(empty($_POST['id'])){
           $_POST['shop_id']    = $_SESSION['shop_id'];
        }
        
        if ($_POST) {
            if ($config === 'true' || $config === true) {
                $brandSelfSkus = $this->app->model('shop_skus')->getList('id', array('id' => $_POST['id'], 'business_mode' => 'brand_self'));
                if ($brandSelfSkus) {
                    $this->splash('error','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('引凤货品（brand_self）不允许开启自动回写库存'));
                    return;
                }
            }

            $this->app->model('shop_skus')->update(array('request'=>$config), $_POST);

            // 记录操作日志
            $optLogModel = app::get('inventorydepth')->model('operation_log');
            $optLogModel->batch_write_logs('sku',$_POST['id'],'stockset',($config=='true' ? '开启库存回写' : '关闭库存回写'));

            $this->splash('success','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('设置成功'));
        }else{
            $this->splash('error','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('请选择SKU'));
        }
    }

    /**
     * 上下架设置
     * @access public
     * @param void
     * @return void
     */
    public function set_frame($config = 'true',$id = null) 
    {
        if($id) $_POST['id'][] = $id;

        if ($_POST) {
            $this->app->model('shop_skus')->update(array('request_frame'=>$config),$_POST);
            $this->splash('success','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('设置成功'));
        }else{
            $this->splash('error','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('请选择SKU'));
        }   
    }
}
