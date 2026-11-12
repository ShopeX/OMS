<?php
class taoguaniostockorder_finder_iso{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

//    var $detail_basic = "出库单详情";
//    var $detail_item = "出库单明细";
//
//    
//
//    function __construct(){
//        if ($_GET['io']){
//           $this->detail_basic = '入库单详情';
//           $this->detail_item = '入库单明细';
//        }else{
//           $this->detail_basic = '出库单详情';
//           $this->detail_item = '出库单明细';
//        }
//    }
//    var $addon_cols = "confirm";
//    var $column_confirm='操作';
//    var $column_confirm_width = "120";
//    function column_confirm($row){
//        $io = $_GET['io'];
//
//        if($_GET['act'] == 'search_iostockorder'){
//            $id = $row['iso_id'];
//            $button = <<<EOF
//        &nbsp;&nbsp;<a href="index.php?app=purchase&ctl=admin_eo&act=printeo&p[0]=$id&t=$io" target="_bank" class="lnk">打印</a>
//EOF;
//            return $button;
//        }
//        $filter_data = array();
//        if ($_isoST)
//        foreach ($_isoST as $key=>$v){
//            if (preg_match("/^_+/i",$key)) continue;
//            $filter_data[$key] = $v;
//        }
//        $filter = urlencode(serialize($filter_data));
//        $find_id = $_GET['_finder']['finder_id'];
//        $iso_id = $row['iso_id'];
//
//        if($_GET['io']){
//            $button = <<<EOF
//            <a href="index.php?app=taoguaniostockorder&ctl=admin_iostockorder&act=iostockorder_confirm&p[0]=$iso_id&p[1]=1&filter=$filter&find_id=$find_id" target="_blank">入库确认</a>
//            
//EOF;
//if($_GET['act']!='allocate_iostock'){
//            $button.= <<<EOF
//                <a href="index.php?app=taoguaniostockorder&ctl=admin_iostockorder&act=iostock_edit&p[0]=$iso_id&p[1]=1&filter=$filter&find_id=$find_id" target="_blank">编辑</a>
//EOF;
//}
//        }else{
//            $button = <<<EOF
//            <a href="index.php?app=taoguaniostockorder&ctl=admin_iostockorder&act=iostockorder_confirm&p[0]=$iso_id&p[1]=0&filter=$filter&find_id=$find_id" target="_blank">出库确认</a>
//            <a href="index.php?app=taoguaniostockorder&ctl=admin_iostockorder&act=iostock_edit&p[0]=$iso_id&p[1]=0&filter=$filter&find_id=$find_id" target="_blank">编辑</a>
//EOF;
//       }
//
//       return $button;
//    }

}
?>
