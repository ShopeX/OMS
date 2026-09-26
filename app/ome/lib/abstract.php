<?php
/**
 * 抽象类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.03.31
 */
abstract class ome_abstract
{
    public $page_size = 100;
    
    protected $logiMapCode = [
        'shentong' => ['code'=>'STO','name'=>'申通快递'],
        'jd' => ['code'=>'JD','name'=>'京东快递'],
        'shunfeng' => ['code'=>'SF','name'=>'顺丰快递'],
        'yuantong' => ['code'=>'YTO','name'=>'圆通快递'],
        'zhongtong' => ['code'=>'ZTO','name'=>'中通快递'],
        'yunda' => ['code'=>'YUNDA','name'=>'韵达快递'],
        'jtexpress' => ['code'=>'JITU','name'=>'极兔速递'],
        'youzhengguonei' => ['code'=>'POSTB','name'=>'邮政快递'],
        'ems' => ['code'=>'EMS','name'=>'EMS'],
    ];
    
    public function __construct()
    {
        //--
    }
    
    /**
     * 成功输出
     * 
     * @param string $msg
     * @param string $data
     * @return array
     */
    final public function succ($msg='', $data=null)
    {
        return array('rsp'=>'succ', 'msg'=>$msg, 'data'=>$data);
    }
    
    /**
     * 失败输出
     * 
     * @param string $msg
     * @param string $data
     * @return array
     */
    final public function error($error_msg, $data=null)
    {
        return array('rsp'=>'fail', 'msg'=>$error_msg, 'error_msg'=>$error_msg, 'data'=>$data);
    }
}