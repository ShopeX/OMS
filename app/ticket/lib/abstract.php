<?php
/**
 * 抽象类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.07.23
 */
abstract class ticket_abstract
{
    public $page_size = 100;
    
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
    
    /**
     * 过滤特殊字符
     *
     * @param $str
     * @return string
     */
    public function charFilter($str)
    {
        return str_replace(array("\t","\r","\n",'"',"\\"), array(''), $str);
    }
}