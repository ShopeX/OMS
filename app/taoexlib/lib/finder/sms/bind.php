<?php
class taoexlib_finder_sms_bind
{
    //var $addon_cols = "";
    var $column_confirm='操作';
    var $column_confirm_width = "120";

    function column_confirm($row){
        $rowHtml = "<a href='javascript:voide(0);' onclick=\"new Dialog('index.php?app=taoexlib&ctl=admin_sms_bind&act=edit_bind&p[0]={$row['bind_id']}&finder_id={$_GET['_finder']['finder_id']}',{width:760,height:480,title:'修改发送规则'}); \">编辑</a>";
        if($row['status']){
            $rowHtml .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要暂时停止发送规则吗？\\n\\n注意：暂停后用此规则的订单将不会发送短信。\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_bind&act=setStatus&p[0]={$row['bind_id']}&p[1]={$row['status']}&finder_id={$_GET['_finder']['finder_id']}\";}'>暂停</a>"; 
        }else{
            $rowHtml .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要开启此发送规则吗？\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_bind&act=setStatus&p[0]={$row['bind_id']}&p[1]={$row['status']}&finder_id={$_GET['_finder']['finder_id']}\";}'>开启</a>"; 
        }
        if ($row['is_default']=='1') {
            $rowHtml .="&nbsp;<a href='javascript:voide(0);' style='color:green;'>默认规则</a>";
        } else {
            $rowHtml .= "&nbsp;<a href='javascript:void(0);' target='download' onclick='if(confirm(\"你确定要设定默认吗？\")) {href=\"index.php?app=taoexlib&ctl=admin_sms_bind&act=setDefault&p[0]={$row['bind_id']}&finder_id={$_GET['_finder']['finder_id']}\";}'>设为默认</a>"; 
        }
        //$rowHtml.= 
        return $rowHtml;
    }
    var $addon_cols = "tid";
    var $column_smsgroup ='短信分组';
    var $column_smsgroup_width = "120";

    function column_smsgroup($row){

        if ($row['is_default']=='1') {
            $desc = '如果选择发送，所有没有分组的订单，在发送短信时，都会使用此发送规则对应的模板作为短信发送的内容。如果选择不发送，所有未分组的订单都不会发送短信';
            $html = "<a href='javascript:voide(0);'>所有未分组订单</a>";  
        } else {
            $rule_info = app::get('omeauto')->model('order_type')->select()->columns('name,tid')->where('tid=?',$row['_0_tid'])->instance()->fetch_row();
            $desc = '短信分组规则：'.$rule_info['name'].'(点击编辑)';
            $html = "<a href='javascript:voide(0);' onclick=\"new Dialog('index.php?app=taoexlib&ctl=admin_sms_rule&act=edit_rule&p[0]={$rule_info['tid']}&finder_id={$_GET['_finder']['finder_id']}',{width:760,height:480,title:'修改分组规则'}); \">{$rule_info['name']}</a>";
        }
         
        return "<div onmouseover='bindFinderColTip(event)' rel='".$desc."'><span>".$html."</span><div>";
    }


}

