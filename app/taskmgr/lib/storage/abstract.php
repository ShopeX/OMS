<?php
/**
 * 文件生成存储抽象类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

abstract class taskmgr_storage_abstract {

    protected $_extName = '';

    //saas定义的是用户域名
    private function kvprefix() {
        return (defined('KV_PREFIX')) ? KV_PREFIX : 'default';
    }

    //根据参数生成文件名
    public function _ident($key){

        if ($this->_extName) {
            return md5(microtime().$this->kvprefix()).$key.'.'.$this->_extName;
        }

        return md5(microtime().$this->kvprefix()).$key.'.csv';
    }

    public function setExtName($extName) {
        $this->_extName = $extName;
    }
}
