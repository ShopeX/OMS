<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


class desktop_ctl_rpcnotify extends desktop_controller{
    
    var $workground = 'desktop_ctl_system';
    var $limit = 20;

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct(&$app) 
    {
        if(defined('WITHOUT_DESKTOP_RPCNOTIFY') && constant('WITHOUT_DESKTOP_RPCNOTIFY')){
            die(app::get('desktop')->_('通知已被禁用'));
        }
        parent::__construct($app);
    }//End Function

    function index(){
        // 清除三个月前的通知，直接用SQL执行
        $threeMonthsAgo = strtotime('-3 months');
        $db = kernel::database();
        $table = app::get('base')->model('rpcnotify')->table_name(true);
        $sql = "DELETE FROM {$table} WHERE notifytime < {$threeMonthsAgo}";
        $db->exec($sql);

        $this->finder('base_mdl_rpcnotify',array(
            'title'=>app::get('desktop')->_('消息通知'),
            'actions'=>array(
                            array('label'=>app::get('desktop')->_('标记为未读'), 'id'=>'id-rpcynotify-submit', 'submit'=>'index.php?ctl=rpcnotify&act=unread'),
                            array('label'=>app::get('desktop')->_('标记为已读'), 'id'=>'id-rpcynotify-submit', 'submit'=>'index.php?ctl=rpcnotify&act=read'),
                        ),
            'use_buildin_recycle' => false,
            #'use_buildin_setcol' => false,
            #'use_buildin_tagedit' => false,
            ));
    }
    
    function get() {
        $filter = array();
        $arr = app::get('base')->model('rpcnotify')->getList( '*',$filter, 0, $this->limit );
        echo json_encode($arr);
    }
    
    
    
    /**
     * read
     * @return mixed 返回值
     */
    public function read() {
        if (!$_POST['from']) {
            $this->begin( kernel::router()->gen_url( array('app'=>'desktop','ctl'=>'rpcnotify','act'=>'index') ) );
        }else{
            $this->begin( kernel::router()->gen_url( array('app'=>'desktop','ctl'=>'dashboard','act'=>'index') ) );
        }
        
        $id = $_POST['id'];
        $is_selected_all = $_POST['isSelectedAll'];

        if( !$id && !$is_selected_all) 
            $this->end( false, app::get('desktop')->_('操作失败') );
        if ($id) {
            //桌面消息缓存
            $cacheInfo = cachecore::fetch('system_notice_data');
            foreach( (array)$id as $val ) {
                if ($cacheInfo) {
                   $infoKey = array_search($val, array_column($cacheInfo, 'id'));
                   $cacheInfo[$infoKey]['status'] = 'true';
                    cachecore::store('system_notice_data', $cacheInfo, 1800);
                }
                $data = array('status'=>'true','id'=>$val,'read_user'=>kernel::single('desktop_user')->get_login_name());
                $flag = app::get('base')->model('rpcnotify')->save( $data );
                if( $flag == false ) break;
            }
        }else {
            $data = array('status'=>'true','read_user'=>kernel::single('desktop_user')->get_login_name());
            $filter = array();
            $flag = app::get('base')->model('rpcnotify')->update( $data, $filter );
        }
        $this->end( $flag, ($flag ? app::get('desktop')->_('操作成功') : app::get('desktop')->_('操作失败')) );
    }
    
    public function unread() {
        if (!$_POST['from']) {
            $this->begin( kernel::router()->gen_url( array('app'=>'desktop','ctl'=>'rpcnotify','act'=>'index') ) );
        }else{
            $this->begin( kernel::router()->gen_url( array('app'=>'desktop','ctl'=>'dashboard','act'=>'index') ) );
        }
        
        $id = $_POST['id'];
        $is_selected_all = $_POST['isSelectedAll'];

        if( !$id && !$is_selected_all) 
            $this->end( false, app::get('desktop')->_('操作失败') );
        if ($id) {
            //桌面消息缓存
            $cacheInfo = cachecore::fetch('system_notice_data');
            foreach( (array)$id as $val ) {
                if ($cacheInfo) {
                   $infoKey = array_search($val, array_column($cacheInfo, 'id'));
                   $cacheInfo[$infoKey]['status'] = 'false';
                    cachecore::store('system_notice_data', $cacheInfo, 1800);
                }
                $data = array('status'=>'false','id'=>$val,'read_user'=>'');
                $flag = app::get('base')->model('rpcnotify')->save( $data );
                if( $flag == false ) break;
            }
        }else {
            $data = array('status'=>'false','read_user'=>'');
            $filter = array();
            $flag = app::get('base')->model('rpcnotify')->update( $data, $filter );
        }
        $this->end( $flag, ($flag ? app::get('desktop')->_('操作成功') : app::get('desktop')->_('操作失败')) );
    }
}
