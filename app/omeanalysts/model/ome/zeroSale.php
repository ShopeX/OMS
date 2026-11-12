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

class omeanalysts_mdl_ome_zeroSale extends dbeav_model{

    var $table_name = 'zero_sale';

    public function searchOptions()
    {
        return array(
            'bn'=>$this->app->_('基础物料编码'),
            'name'=>$this->app->_('基础物料名称')
        );
    }

    public function table_name($real=false)
    {
        if($real){
            return kernel::database()->prefix.$this->app->app_id.'_'.$this->table_name;
        }else{
            return $this->table_name;
        }
    }

    public function get_schema()
    {
        $catSaleStatis_model = $this->app->model('zero_sale');
        $schema = $catSaleStatis_model->get_schema();
        $schema['columns']['store'] = array (
            'type' => 'int',
            'required' => true,
            'label' => '库存',
            'width' =>60,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 6,
        );
        $schema['in_list'][] = 'store';
        $schema['default_in_list'][] = 'store';
        return $schema;
    }

    public function count($filter=null){
        $store_tablename = 'sdb_omeanalysts_branch_product_stock_detail';
        $sql = 'SELECT count(*) c FROM `'.$this->table_name(true).'` AS zero JOIN `'.$store_tablename.'` AS store ON zero.bpsd_id=store.id WHERE '.$this->_filter($filter);
        $tmp = $this->db->count($sql);
        return $tmp;
    }

    public function getlist($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){

        $store_tablename = 'sdb_omeanalysts_branch_product_stock_detail';
        $orderType = $orderType?$orderType:$this->defaultOrder;
        $cols = str_replace('`store`,','',$cols);
        $sql = 'SELECT zero.branch_id,zero.bn,zero.bpsd_id,zero.type_id,zero.name,zero.brand_id FROM `'.$this->table_name(true).'` AS zero JOIN `'.$store_tablename.'` AS store ON zero.bpsd_id=store.id WHERE '.$this->_filter($filter);
        $time_to = strtotime($filter['time_to']);
        $sql .= ' GROUP BY zero.branch_id,zero.bn';
        if ( date('m',$time_to) >= date('m') ){
            $last_day = date("j",time()-24*60*60);
        }else{
            $last_day = date('t', $time_to);
        }
        if($orderType){
            if ( preg_match('/store/',$orderType) ){
                $orderType = str_replace('store','store.day'.$last_day,$orderType);
            }
            $sql.=' ORDER BY '.(is_array($orderType)?implode($orderType,' '):$orderType);
        }else{
            $sql.=' ORDER BY zero.`months` desc,store.day'.$last_day.' desc';
        }

        $data = $this->db->selectLimit($sql,$limit,$offset);
        if ($data){
            foreach ( $data as $key=>$value ){
                $sql = 'SELECT day'.$last_day.' as store FROM `'.$store_tablename.'` WHERE `id`='.$value['bpsd_id'];
                $tmp = $this->db->selectrow($sql);
                $store = $tmp['store'];
                $data[$key]['store'] = $store;
            }
        }

        return $data;
    }

    public function modifier_months($months)
    {
        return substr($months,0,4).'-'.substr($months,4);
    }
    
    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        $where = ' AND 1';
        // 日期搜索
        if ( isset($filter['time_from']) || isset($filter['time_to']) ){
            $time_from = date('Ym', strtotime($filter['time_from']));
            $time_to = date('Ym', strtotime($filter['time_to']));
            // 天数查询条件
            if ( date('m',strtotime($filter['time_to'])) >= date('m') ){
                $last_day = date("j",time()-24*60*60);
            }else{
                $last_day = date('t', strtotime($filter['time_to']));
            }
            $zero = str_pad('0',intval($last_day),'0');
            $day_sql = ' AND abs(substr(zero.days,1,'.$last_day.'))=\'0\'';
            $where = ' AND zero.`months`>=\''.$time_from.'\' AND zero.`months`<=\''.$time_to.'\''.$day_sql;
        }

        // 仓库搜索
        if ( isset($filter['branch_id']) ){
            $filter['type_id'] = $filter['branch_id'];
            unset($filter['branch_id']);
        }
        if ( isset($filter['type_id']) && $filter['type_id'] ){
            $where .= ' AND zero.branch_id=\''.$filter['type_id'].'\'';
        }
        unset($filter['type_id']);

        // 货号搜索
        if ( isset($filter['bn']) && $filter['bn'] ){
            $where .= ' AND zero.bn=\''.$filter['bn'].'\'';
            unset($filter['bn']);
        }

        // 仓库搜索
        if ( isset($filter['name']) && $filter['name'] ){
            $where .= ' AND zero.name=\''.$filter['name'].'\'';
            unset($filter['name']);
        }

        // 商品类目搜索
        if ( isset($filter['goods_type']) ){
            $where .= ' AND zero.`type_id` = '.$filter['goods_type'];
            unset($filter['goods_type']);
        }

        return parent::_filter($filter,$tableAlias,$baseWhere) . $where;
    }
    
    public function export_params(){
        //获取框架filter信息
        $params = unserialize($_POST['params']);
        $filter['time_from'] = $params['time_from'];
        $filter['time_to'] = $params['time_to'];
        $params = array(
            'filter' => $filter,
            //单文件 
            'single'=> array(
                '1'=> array(
                    //定义返回主体信息方法，提供自定义方法名给method，系统会分页调取主体信息
                    'method' => 'get_export_main',
                    'offset' => 0,
                    'limit' => 4000,
                    //导出文件名
                    'filename' => '类目销售对比统计',
                ),
            ),
        );
        return $params;
    }

    public function get_export_main_title(){
        $title = array(
           'col:仓库名称',
            'col:货号',
            'col:商品类目' ,
            'col:商品名称(规格)',
            'col:库存' ,
            'col:品牌' ,
        );
        return $title;
    }
    
     //注：$filter是在export_params()方法里获取到的filter,$offset,$limit已做处理，直接带到getList里即可
    public function get_export_main($filter,$offset,$limit,&$data){
        $list=$this->getList('*',$filter,$offset*$limit,$limit);
        $branchModel = app::get('ome')->model('branch');
        $brandModel = app::get('ome')->model('brand');
        $goods_typeModel = app::get('ome')->model('goods_type');
        foreach($list as $v){
            $branchs = $branchModel->dump($v['branch_id'],'name');
            $types = $goods_typeModel->dump($v['type_id'],'name');
            $brands = $brandModel->dump($v['brand_id'],'brand_name');
            //无需返回值，按 模版字段 组织好一维数组 赋给 $data
            $data[] = array(
                'col:仓库名称' => $branchs['name'],
                'col:商品类目' => $types['name'],
                'col:货号' => $v['bn'],
                'col:商品名称(规格)' => $v['name'],
                'col:库存' =>  $v['store'],
                'col:品牌' =>  $brands['brand_name'],
            );
        }
    }


}
