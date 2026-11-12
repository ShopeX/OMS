<?php
class ome_finder_order_groupon{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

	
    var $column_confirm='操作';
    var $column_confirm_width = "80";

    function column_confirm($row){
        
        $result = "<a href='index.php?app=ome&ctl=admin_order_groupon&act=index&action=to_export&order_groupon_id=".$row['order_groupon_id']."&_io_type=csv' target='download'>导出</a>";
    	//$result .= '&nbsp;|&nbsp;';
        //$result .= "<a href='index.php?app=ome&ctl=admin_consign&act=do_sync&p[0]={$order_id}&finder_id=$find_id' target='download'>查看</a>";
    	
        return $result;
    }
}
?>
