<?php
/**
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_finder_shop_items {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $addon_cols = 'shop_bn_crc32,shop_id,store_info,statistical_time,frame_set,approve_status,iid,shop_bn,shop_type';

    private $js_approve_status = false;

    function __construct($app)
    {
        $this->app = $app;

        $this->_render = app::get('inventorydepth')->render();
    }


    public $column_approve_status = '商品在架状态';
    public $column_approve_status_order = 3;
    public function column_approve_status($row) 
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $shop_id = $row[$this->col_prefix.'shop_id'];
        $shop_bn = $row[$this->col_prefix.'shop_bn'];
        $shop_bn = addslashes(str_replace('+','%2B',$shop_bn));
        $shop_type = $row[$this->col_prefix.'shop_type'];
        if ($this->js_approve_status === false) {
            $this->js_approve_status = true;
            $return = <<<EOF
            <script>
                void function(){
                    function approve_request(){
                        var data = Array.flatten(arguments);
                        new Request.JSON({
                            url:"index.php?app=inventorydepth&ctl=shop_frame&act=getApproveStatus",
                            method:"post",
                            data:{"iid":data,"shop_id":"{$shop_id}","shop_bn":"{$shop_bn}","finder_id":"{$finder_id}","shop_type":"{$shop_type}"},
                            onComplete:function(rsp){
                                if(rsp.status=='fail'){console.log(rsp.msg);return;}
                                if(rsp.status=='succ'){
                                    rsp.data.each(function(item,index){
                                        var id = 'item-approve-'+item.id;
                                        if (\$defined(\$(id))){
                                            \$(id).setHTML(item.html);
                                        }
                                        
                                        id = 'store-statistics-'+item.id;
                                        if(\$defined(\$(id))){
                                            var html = '<em style=\'color:#cc0000;\'>'+item.num+'</em>/<em style=\'color:#0033ff;\'>'+item.actual_stock+'</em>';
                                            \$(id).setHTML(html);
                                        }
                                    });
                                }
                            }
                        }).send();
                    }
                    \$('main').addEvent('domready',function(){
                        var data = []; var dtime=0;
                        \$ES('.item-approve-status').each(function(i){
                            if(data.length>=20){
                                dtime = \$random(0,1000);
                                approve_request.delay(dtime,this,data);
                                data = [];
                            }
                            data.push(i.get("iid"));
                        });
                        if (data.length>0) {
                                dtime = \$random(0,1000);
                                approve_request.delay(dtime,this,data);
                        }
                        
                    });
                }();
            </script>
EOF;
        }
        
        $return .= <<<EOF
        <div class='item-approve-status' iid="{$row['iid']}" id="item-approve-{$row['id']}"></div>
EOF;
        return $return;
    }

    public $column_store_statistics = '前端/总';
    public $column_store_statistics_width = 90;
    public function column_store_statistics($row)
    {
        return <<<EOF
        <div id='store-statistics-{$row['id']}'></div>
EOF;
    }

    public $column_sku_num = 'SKU数';
    public $column_sku_num_order = 61;
    public function column_sku_num($row)
    {
        $filter = array(
            'shop_id' => $row[$this->col_prefix.'shop_id'],
            'shop_iid' => $row['iid'],
        );
        
        $count = $this->app->model('shop_skus')->count($filter);
        return $count;
        /*
        return <<<EOF
        <a href="index.php?app=inventorydepth&ctl=shop_adjustment&act=index&filter[shop_iid]={$row['iid']}&filter[shop_id]={$row[$this->col_prefix.'shop_id']}">{$count}</a>
EOF;*/
    }

    // public $column_regulation = '应用上下架规则';
    // public $column_regulation_order = 62;
    private $js_regulation = false;
    public function column_regulation($row) 
    {
        $id = $row['id']; 
        $iid = $row['iid']; 
        $shop_id = $row[$this->col_prefix.'shop_id']; 
        $shop_bn = $row[$this->col_prefix.'shop_bn'];
        $shop_bn = addslashes(str_replace('+','%2B',$shop_bn));
        if ($this->js_regulation === false) {
            $this->js_regulation = true;
            $return = <<<EOF
            <script>
                void function(){
                    function regulation_request(data){
                        new Request.JSON({
                            url:"index.php?app=inventorydepth&ctl=shop_frame&act=getApplyRegu",
                            method:"post",
                            data:{"iid":data,"shop_id":"{$shop_id}","shop_bn":"{$shop_bn}"},
                            onComplete:function(rsp){
                                if(rsp.status=='fail'){console.log(rsp.msg);return;}
                                if(rsp.status=='succ'){
                                    rsp.data.each(function(item,index){
                                        var id = 'regulation-'+item.id;
                                        if (\$defined(\$(id))){
                                            \$(id).setHTML(item.html);
                                        }
                                        
                                    });
                                }
                            }
                        }).send();
                    }
                    \$('main').addEvent('domready',function(){
                        var data = [];
                        \$ES('.apply-regulation').each(function(i){
                            data.push(i.get("iid"));
                        });
                        if (data.length>0) {
                            regulation_request(data);
                        }
                        
                    });
                }();
            </script>
EOF;
        }
        $return .= <<<EOF
        <div id="regulation-{$id}" class="apply-regulation" iid="{$iid}"></div>
EOF;
        
        return $return;
    }

    public $detail_operation_log = '操作日志';
    public function detail_operation_log($item_id)
    {
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $filter = array('obj_type' => 'item','obj_id' => $item_id);
        $optLogList = $optLogModel->getList('*',$filter,0,200);
        foreach ($optLogList as &$log) {
            $log['operation'] = $optLogModel->get_operation_name($log['operation']);
        }

        $this->_render->pagedata['optLogList'] = $optLogList;
        return $this->_render->fetch('finder/frame/operation_log.html');
    }

}
