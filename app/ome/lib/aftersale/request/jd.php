<?php
/**
 * 京东厂直（node_type=jd / erpapi matrix jd）
 * 编辑页、详情、附加表与 POP 共用父类；保存回传策略与 luban 一致：同意(3)、拒绝(5)均 sync 通知平台。
 */
class ome_aftersale_request_jd extends ome_aftersale_request_360buy
{
    function pre_save_return($data)
    {
        set_time_limit(0);
        $rs = array('rsp' => 'succ', 'msg' => '', 'data' => '');
        $return_id = $data['return_id'];
        $status    = $data['status'];

        if ($status == '3' || $status == '5') {
            $rsp = kernel::single('ome_service_aftersale')->update_status($return_id, $status, 'sync');
            if ($rsp && $rsp['rsp'] == 'fail') {
                $rs['rsp'] = 'fail';
                $rs['msg'] = $rsp['msg'];
            }
        }

        return $rs;
    }
}
