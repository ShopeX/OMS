<?php

class taoguaninventory_mdl_inventory extends dbeav_model{
    var $export_name = '盘点表';

    /*
     * 获取货品
     */
    function getBranchProduct($branch_id, $barcode)
    {
        $sql = "SELECT a.bm_id AS product_id, a.material_name AS name, a.material_bn AS bn, bp.store as 'num' 
                FROM sdb_material_basic_material a 
                LEFT JOIN sdb_material_codebase AS c ON a.bm_id=c.bm_id 
                JOIN sdb_ome_branch_product bp ON a.bm_id=bp.product_id 
                WHERE c.code='". $barcode ."' AND bp.branch_id='". $branch_id ."'";
        
        $row = $this->db->selectrow($sql);
        if ($row){

            return $row;
        }else {
            return false;
        }
    }

    function existPosNotProcess($pos_id){
        $sql = "SELECT COUNT(d.delivery_id) AS '_count' FROM sdb_ome_dly_items_pos dip
                        JOIN sdb_ome_delivery_items di
                            ON dip.item_id=di.item_id
                        JOIN sdb_ome_delivery d
                            ON di.delivery_id=d.delivery_id
                        WHERE dip.pos_id = '".$pos_id."'
                            AND d.process = 'false'";
        $row = $this->db->selectrow($sql);
        if ($row['_count'] > 0) return true;
        return false;
    }

    /*
     * 损益汇总表
     */
    function getInventoryList($col='*', $filter=null, $lim=0, $limit=-1){

        $sql = " SELECT $col FROM `sdb_taoguaninventory_inventory` WHERE 1 ";
        $begin_date = $filter['begin_date'];
        $end_date = $filter['end_date'];
        if ($begin_date) $sql .= " and FROM_UNIXTIME(`inventory_date`,'%Y-%m-%d')>='".$begin_date."' ";
        if ($end_date) $sql .= " and FROM_UNIXTIME(`inventory_date`,'%Y-%m-%d')<='".$end_date."' ";
        $limit = " limit $lim,$limit ";
        $tmp = $this->db->select($sql.$limit);
        return $tmp;
    }
    /*
    * 获取货位信息
    */
    function getPosList($data=null, $lim=0, $limit=1, $type='search'){
        //$search_flag = 0;
        $inventorylistLib = kernel::single('taoguaninventory_inventorylist');
        $branch_id = $data['branch_id'];
        $joinsql    = '';
        
        /* 基础物料已弃用sdb_ome_goods
        #新增品牌搜索
        $brand_id = $data['brand_id'];
        $joinsql = '';
        $joinsql = ' left join sdb_ome_goods as g ON g.goods_id=p.goods_id';
        if($data['brand_id']){
            
            $wheresql_and.= "  AND g.brand_id=".$data['brand_id'];
        }
        //商品类型搜索
        if($data['type_id']){
            
            $wheresql_and.= "  AND g.type_id=".$data['type_id'];
        }
        if($data['product_visibility']=='0'){//隐藏商品是否显示
            $wheresql_and.= "  AND g.visibility='true'";
        }
        */
        
        if ($data['product_name']){
            foreach ($data['product_name'] as $k=>$v){
                $wheresql[]= " a.material_name regexp '".$v."' ";
            }
            //$search_flag = 1;
        }
        if ($data['product_bn']){
            foreach ($data['product_bn'] as $k=>$v){
                $wheresql[]= " a.material_bn regexp '".$v."' ";
            }
            $search_flag = 1;
        }
         // 处理数量条件
        if ($data['branch_store']){
            $store_operator = $data['store_operator'];
            foreach ($data['branch_store'] as $k=>$v){
                if ($store_operator[$k]=='>=') {
                    $store_operator[$k] = ">=";
                }else{
                    $store_operator[$k] = "<=";
                }
                $wheresql_and.= " AND  bp.store ".$store_operator[$k]." '".$data['branch_store'][$k]."' ";
            }

        }
        if($wheresql){
            $wheresql = implode(' or ',$wheresql);
            $where_pre = " AND (  ".$wheresql.")";
        }else{
            $wheresql = '';
        }


        if ($type=='search') $limitsql = " limit $lim,$limit ";

        //分页导出
        if ($type!='search' && $data['start_page'] && $data['end_page'] && $data['page_size']) {
            $page_size = intval($data['page_size']);
            $end_page = intval($data['end_page']);
            $start_page = intval($data['start_page']);
            if($start_page == 0) $start_page = 1;
            if($end_page == 0) $end_page = 1;
            $lim = $page_size * ($start_page - 1);
            $limit = $page_size * (abs($end_page - $start_page) + 1);
            $limitsql = " limit $lim,$limit ";
        }
        
        $sql = 'SELECT pos.store_position, bp.store, a.bm_id AS product_id, a.material_name AS name, a.material_bn AS bn, a.visibled AS visibility 
                FROM sdb_ome_branch_pos AS pos LEFT JOIN sdb_ome_branch_product_pos AS pp ON pos.pos_id=pp.pos_id 
                LEFT JOIN sdb_material_basic_material AS a ON pp.product_id=a.bm_id '.$joinsql.' 
                LEFT JOIN sdb_ome_branch_product AS bp ON a.bm_id=bp.product_id AND bp.branch_id='.$data['branch_id'].' 
                WHERE pos.branch_id='.$data['branch_id'];
        
        $result = $this->db->select($sql.$where_pre.$wheresql_and.$limitsql);

        $arr = array();
        if ($result)
        foreach ($result as $k=>$v){
            $price = $inventorylistLib->get_price($v['product_id'],$branch_id);
            $v['price'] = $price;
            $arr[] = $v;
        }
        
        $selectField =  'SELECT count(*) as count FROM sdb_ome_branch_pos AS pos 
                         LEFT JOIN sdb_ome_branch_product_pos AS pp ON pos.pos_id=pp.pos_id 
                         LEFT JOIN sdb_material_basic_material AS a ON pp.product_id=a.bm_id '.$joinsql.' 
                         LEFT JOIN sdb_ome_branch_product AS bp ON a.bm_id=bp.product_id AND bp.branch_id='.$data['branch_id'].' 
                         WHERE pos.branch_id='.$data['branch_id'].$where_pre.$wheresql_and;
        
        $selectcount = $this->db->select($selectField);

        $count = $selectcount[0]['count'];
        $arr['count'] = $count;
        return $arr;
    }
    /*
    * 获取货品信息
    */
    function getProduct($data=null, $lim=0, $limit=1, $type='search'){
        $inventorylistLib = kernel::single('taoguaninventory_inventorylist');

        $branch_id = $data['branch_id'];
        $wheresql_and = '';
        $joinsql      = '';
        
        /* 基础物料已弃用sdb_ome_goods
        #新增品牌搜索
        $brand_id = $data['brand_id'];
        $joinsql = ' left join sdb_ome_goods as g ON g.goods_id=p.goods_id';
        if($data['brand_id']){
            
            $wheresql_and.= "  AND g.brand_id=".$data['brand_id'];
        }
        //商品类型搜索
        if($data['type_id']){
            
            $wheresql_and.= "  AND g.type_id=".$data['type_id'];
        }
        if($data['product_visibility']=='0'){
            $wheresql_and.= "  AND g.visibility='true'";
        }
        */
        $sql = " SELECT bp.store, a.bm_id AS product_id, a.material_name AS name, a.material_bn AS bn, a.visibled AS visibility
                 FROM sdb_material_basic_material AS a ".$joinsql."
                         LEFT JOIN  `sdb_ome_branch_product` bp on a.bm_id = bp.product_id
                         WHERE  bp.branch_id in ('$branch_id' ) and a.bm_id = bp.product_id ";
        
        if ($data['product_name']){
            foreach ($data['product_name'] as $k=>$v)
            {
                $wheresql[]= " a.material_name regexp '".$v."' ";
            }

        }
        if ($data['product_bn']){
            foreach ($data['product_bn'] as $k=>$v)
            {
                $wheresql[]= " a.material_bn regexp '".$v."' ";
            }

        }

        // 处理数量条件
        if ($data['branch_store']){
            $store_operator = $data['store_operator'];
            foreach ($data['branch_store'] as $k=>$v){
                if ($store_operator[$k]=='>=') {
                    $store_operator[$k] = ">=";
                }else{
                    $store_operator[$k] = "<=";
                }
                $wheresql_and .= " and bp.store ".$store_operator[$k]." '".$data['branch_store'][$k]."' ";
            }

        }
       if($wheresql){
            $wheresql = implode(' or ',$wheresql);
            $where_pre = " AND (  ".$wheresql.")";
        }else{
            $wheresql = '';
        }

        if ($type=='search') $limitsql = " limit $lim,$limit ";

        //分页导出
        if ($type!='search' && $data['start_page'] && $data['end_page'] && $data['page_size']) {
            $page_size = intval($data['page_size']);
            $end_page = intval($data['end_page']);
            $start_page = intval($data['start_page']);
            if($start_page == 0) $start_page = 1;
            if($end_page == 0) $end_page = 1;
            $lim = $page_size * ($start_page - 1);
            $limit = $page_size * (abs($end_page - $start_page) + 1);
            $limitsql = " limit $lim,$limit ";
        }
        
        $result = $this->db->select($sql.$where_pre.$wheresql_and.$limitsql);
        $arr = array();
        if ($result)
        foreach ($result as $k=>$v)
        {
            $v['price'] = $inventorylistLib->get_price($v['product_id'],$branch_id);;
            $arr[] = $v;
        }
        
        $selectField =  "SELECT count(*) as count 
                         FROM sdb_material_basic_material AS a ".$joinsql." 
                         LEFT JOIN  `sdb_ome_branch_product` bp on a.bm_id = bp.product_id 
                         WHERE  bp.branch_id='$branch_id' and a.bm_id=bp.product_id ".$where_pre.$wheresql_and;
        
        $selectcount = $this->db->select($selectField);

        $count = $selectcount[0]['count'];

        $arr['count'] = $count ;

        return $arr;
    }

    /*
     * 盘点明细总计
     * getInventoryTotal
     */
    function getInventoryTotal($inventory_id=null,$is_auto=null,$shortage_over=null){
        $sql = " SELECT sum(accounts_num) accounts_num,sum(actual_num) actual_num,sum(shortage_over) shortage_over
               FROM `sdb_taoguaninventory_inventory_items` ";
        if ($inventory_id)
        $wheresql = "WHERE `inventory_id`='$inventory_id'";
        if($is_auto=='0' || $is_auto=='1'){
            $wheresql.="AND is_auto='$is_auto'";
        }
        if($shortage_over==1){
            $wheresql.="AND shortage_over!=0";
        }

        $tmp = $this->db->select($sql.$wheresql);
        $count = $this->db->select(" SELECT count(*) count FROM `sdb_taoguaninventory_inventory_items` $wheresql ");
        $tmp['count'] = $count[0]['count'];
        return $tmp;
    }

    /*
     * 盘点汇总总计
     * getTotal
     */
    function getTotal($begin_date=null, $end_date=null){

        $sql = " SELECT sum(difference) total_shortage_over
                 FROM `sdb_taoguaninventory_inventory` ";
        $wheresql0 = " WHERE 1 ";
        //if ($begin_date) $wheresql .= " and FROM_UNIXTIME(`inventory_date`,'%Y-%m-%d')>='$begin_date' ";
        //if ($end_date) $wheresql .= " and FROM_UNIXTIME(`inventory_date`,'%Y-%m-%d')<='$end_date' ";
        if ($begin_date) $wheresql .= " and FROM_UNIXTIME(`inventory_date`,'%Y-%m-%d')>='".$begin_date."' ";
        if ($end_date) $wheresql .= " and FROM_UNIXTIME(`inventory_date`,'%Y-%m-%d')<='".$end_date."' ";

        $tmp = $this->db->select($sql.$wheresql0.$wheresql);
        $tesql = " SELECT count(*) count FROM `sdb_taoguaninventory_inventory` ".$wheresql0.$wheresql;

        $count = $this->db->select($tesql);
        $tmp['count'] = $count[0]['count'];
        return $tmp;
    }

    /*
     * 确认
     */
    function confirm($data=null){

        $sql = " UPDATE `sdb_taoguaninventory_inventory` SET `confirm_status`='2',`confirm_op`='".$data['confirm_op']."',`confirm_time`='".$data['confirm_time']."' WHERE `inventory_id`='".$data['inventory_id']."' ";

        if ($this->db->exec($sql)) return true;
        else return false;
    }

    /*
    * 盘点表编号
    */
    function gen_id(){
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $inventory_bn = 'PD'.date('YmdH').str_pad($i,6,'0',STR_PAD_LEFT);
            $row = $this->db->selectrow("SELECT `inventory_bn` from `sdb_taoguaninventory_inventory` where `inventory_bn`='".$inventory_bn."'");
        }while($row);
        return $inventory_bn;
    }


   //盘点日期计格式化
   function modifier_inventory_date($row){
        $tmp = date('Y-m-d',$row);
        return $tmp;
    }

    function searchOptions(){
        return array(
                'inventory_name'=>app::get('base')->_('盘点名称'),
                 'inventory_bn'=>app::get('base')->_('盘点单编号'),

            );
    }


     function io_title( $filter, $ioType='csv' ){
        switch( $filter ){
            case 'shortage_over':
                    $this->oSchema['csv'][$filter] = array(
                    '*:商品名称' => 'name',
                    '*:货号' => 'bn',
                    '*:单位' => 'unit',
                    '*:账面数量' => 'store',
                    '*:成本价' => 'price',
                    '*:实际数量' => 'entity_num',
                    '*:盈(+)亏(-)记录' => 'shortage_over',
                    '*:盈(+)亏(-)金额' => 'shortage_over_price',
                );
                break;
            case 'export':
                $this->oSchema['csv'][$filter] = array(
                    '*:商品名称' => 'name',
                    '*:货号' => 'bn',
                    '*:规格型号' => 'spec_info',
                    '*:单位' => 'unit',
                    //'*:货位' => 'store_position',
                    '*:账面数量' => 'store',
                    '*:价格' => 'price',
                    '*:实际数量' => 'entity_num',
                    '*:品质情况' => 'condition',
                    '*:条形码' => 'barcode',
                    '*:货位' => 'pos_name',
                    '*:可视状态' => 'visibility',
                );
                break;
            case 'branch':
                $this->oSchema['csv'][$filter] = array(
                    '*:仓库' => 'branch',
                    '*:盘点名称' => 'name',
                    '*:盘点人' => 'f_checker',
                    '*:复核人' => 's_checker',
                    '*:账务负责人' => 'finance_dept',
                    '*:仓库负责人' => 'warehousing_dept',
                    '*:盘点类型' => 'type',
                );
                break;
            case 'import':
                $this->oSchema['csv'][$filter] = array(
                    '*:商品名称' => 'name',
                    '*:货号' => 'bn',
                    '*:规格型号' => 'spec_info',
                    '*:单位' => 'unit',
                    '*:货位' => 'store_position',
                    '*:账面数量' => 'store',
                    '*:价格' => 'price',
                    '*:实际数量' => 'num',
                    '*:品质情况' => 'condition',
                    '*:条形码' => 'barcode',
                );
                break;
            case 'expire':
                    $this->oSchema['csv'][$filter] = array(
                    '*:保质期编号' => 'expire_bn',
                    '*:货号' => 'bn',
                    '*:条码' => 'barcode',
                    '*:货位' => 'pos_name',
                    '*:录入数量' => 'in_num',
                );
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
     }



     //csv导出
     function fgetlist_csv(&$data, $filter, $offset){
        $this->charset = kernel::single('base_charset');
        $post = $filter;
        $inventorylistLib = kernel::single('taoguaninventory_inventorylist');
        if ( $post ){
            //导出的完整数据数组
            $data['content']['main'] = array();
            // 仓库信息
            $branch = app::get('ome')->model('branch')->dump($post['branch_id']);
            $data['content']['branch']['branch']            = $branch['name'];
            $data['content']['branch']['name']              = $post['inventory_name'];
            $data['content']['branch']['f_checker']         = $post['inventory_checker'];
            $data['content']['branch']['s_checker']         = $post['second_checker'];
            $data['content']['branch']['finance_dept']      = $post['finance_dept'];
            $data['content']['branch']['warehousing_dept']  = $post['warehousing_dept'];
            $inventory_type = $this->get_inventory_type($post['inventory_type'],'key');
            $data['content']['branch']['type']              = $inventory_type;

            //导出数据第一行，仓库相关信息标题
            $title = array();
            foreach( $this->io_title('branch') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['content']['main'][] = '"'.implode('","',$title).'"';


            //仓库相信信息数据行
            foreach( $this->oSchema['csv']['branch'] as $k => $v ){
                    $branchRow[$v] = $this->charset->utf2local( utils::apath( $data['content']['branch'],explode('/',$v) ) );
            }
            $data['content']['main'][] = '"'.implode('","',$branchRow).'"';

            //盘点明细内容的标题行
            $title = array();
            foreach( $this->io_title('export') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['content']['main'][] = '"'.implode('","',$title).'"';


            if($post['inventory_type']!=4){
            $export_data['branch_id'] = $post['branch_id'];
            #新增按品牌搜索
            $export_data['brand_id'] = $post['brand_id'];
            $export_data['type_id'] = $post['type_id'];
            $export_data['pos_name'] = $post['pos_name'];
            $export_data['product_bn'] = $post['product_bn'];
            $export_data['product_name'] = $post['product_name'];
            $export_data['branch_store'] = $post['branch_store'];
            $export_data['store_operator'] = $post['store_operator'];
            $export_data['ignore'] = $post['ignore'];
            $export_data['page_size'] = $post['page_size'];
            $export_data['start_page'] = $post['start_page'];
            $export_data['end_page'] = $post['end_page'];
            $export_data['product_visibility'] = $post['product_visibility'];#隐藏商品是否导出
            // 商品信息
            if($post['export_type']=='0'){

                $inventory_list = $this->getPosList($export_data, '', '', 'export');
            }else{
                $inventory_list = $this->getProduct($export_data, '', '', 'export');
            }

            unset($inventory_list['count']);
            if ($inventory_list){
                foreach ($inventory_list as $row){
                    $row['bn'] = $row['bn']."\t";
                    $row['barcode'] = $row['barcode']."\t";
                    $price = $inventorylistLib->get_price($row['product_id'],$post['branch_id']);
                    $row['price'] = $price;

                    if($row['visibility']){
                        $row['visibility'] = strtoupper($row['visibility'])=='TRUE' ? '显示' : '隐藏';
                    }else{
                        $row['visibility'] = '';
                    }
                    if($post['export_type']==1){
                        $pos_name = $this->getProductPos($row['product_id'],$export_data['branch_id']);
                        if($pos_name) {
                            foreach($pos_name as $v) {
                                $pos_names[] = $v['pos_name'];
                            }
                            $pos_name = implode('、',$pos_names);
                            unset($pos_names);
                            $row['pos_name'] = $pos_name;
                        }
						if($post['store_show']=='0'){                            $row['store']='';
                        }
                    }else{
                        $row['pos_name'] = $row['store_position'];
                        $row['store']='';
                    }

                        foreach( $this->oSchema['csv']['export'] as $k => $v ){
                            if ($v){
                                $pRow[$v] = $this->utf8togbk( utils::apath( $row,explode('/',$v) ) );
                            }else {
                                $pRow[$v] = '';
                            }
                        }
                       $data['content']['main'][] = '"'.implode('","',$pRow).'"';
    				}
    			}
            }
            //$data['name'] = $post['inventory_name'];

            $data['records'] = count($data['content']['main'])-3;
            
            return true;
        }
    }
    function utf8togbk($s)
    {
        return iconv("UTF-8", "GBK//TRANSLIT", $s);
    }

    function export_csv($data,$exportType = 1 ){
        $output = array();
        //if( $exportType == 2 ){
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        //}
        echo implode("\n",$output);
    }

    public function exportName(&$data){
        $post = $_POST;
        $branch = app::get('ome')->model('branch')->dump($post['branch_id']);
        $inventory_type = $this->get_inventory_type($post['inventory_type'],'key');

        $data['name'] = date('Ymd').$branch['name'].$inventory_type.kernel::single('desktop_user')->get_name();
    }
    function prepared_import_csv(){
        set_time_limit(0);
        $this->ioObj->cacheTime = time();
        
        $this->branch_pos = app::get('ome')->model('branch_pos');
        $this->branch = app::get('ome')->model('branch');
        $this->branch_product = app::get('ome')->model('branch_product');
        $this->kvdata = '';
        $this->aa = 0;
    }

    function finish_import_csv(){
        $data = $this->kvdata; unset($this->kvdata);
        $oEncoded_state = app::get('taoguaninventory')->model('encoded_state');
        $get_state = $oEncoded_state->get_state('inventory');
        $oQueue = app::get('base')->model('queue');
        $number = $page = 0;   $limit = 50;
        $branch_id = $data['branch']['branch_id'];
        $branch_name = $data['branch']['name'];
        $inv = array();
        $inv['inventory_name']      =  $data['branch']['contents'][0][1];
        $inv['inventory_bn']          = $get_state['state_bn'];
        $inv['inventory_date']       = time();
        $inv['add_time']               = time();
        $inv['inventory_checker']   = $data['branch']['contents'][0][2];
        $inv['second_checker']      = $data['branch']['contents'][0][3];
        $inv['finance_dept']          = $data['branch']['contents'][0][4];
        $inv['warehousing_dept']   = $data['branch']['contents'][0][5];
        $inv['op_name']                = kernel::single('desktop_user')->get_name();
        $inv['op_id']                     = kernel::single('desktop_user')->get_id();
        $inv['branch_id']               = $branch_id;
        $inv['branch_name']          = $branch_name;
        $inv['inventory_type']         = $data['branch']['contents'][0][6];
        $inv['pos'] = $_POST['import_type']==0 ? 1 : 0;
        

        $re = $this->save($inv);

         if($re){
            $encoded_state_data = array();
            $encoded_state_data['currentno'] = $get_state['currentno'];
            $encoded_state_data['eid'] = $get_state['eid'];
            $state_result = $oEncoded_state->save($encoded_state_data);
            if($inv['inventory_type']=='2'){
                kernel::single('taoguaninventory_inventorylist')->auto_product_list($inv['inventory_id'],$branch_id);
            }
        }
        $psdf['branch_id']     = $branch_id;
        $psdf['branch']         = $branch_name;
        $psdf['inv_id']           = $inv['inventory_id'];
        $psdf['import_type']   = $_POST['import_type'];

        $sdfs = array();
        foreach ($data['products']['contents'] as $k => $v){
            $sdf = array();

            $sdf['product_id']     = $v['product_id'];
            $sdf['bn']                = trim($v[1]);
            $sdf['name']            = $v[0];
            $sdf['spec_info']      = $v[2];
            $sdf['unit']              = $v[3];
            $sdf['pos_name'] =     $_POST['import_type']==1 ? '' : $v[9];
            $sdf['store']           = (int)$v[4];
            $sdf['price']           = $v[5];
            $sdf['num']            = (int)$v[6];
            $sdf['condition']      = $v[7];

            if ($number < $limit){
                $number++;
            }else{
                $page++;
                $number = 0;
            }
            $sdfs[$page][] = $sdf;
        }
        
        foreach ($sdfs as $i){
            $psdf['products']  = $i;
            $queueData = array(
                'queue_title'=>'盘点导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$psdf,
                    'app' => 'taoguaninventory',
                    'mdl' => 'inventory'
                ),
                'worker'=>'taoguaninventory_products_import.run',
            );

            $oQueue->save($queueData);
        }
        $oQueue->flush();
        return null;
    }
    //导入
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        $this->aa++;
        if (empty($row)){
            if ($this->flag){
                # 错误信息处理
                if ($this->not_exist_product_bn){
                    $temp = $this->not_exist_product_bn;
                    $tmp = array_unique($temp); sort($tmp);
                    $msg['error'] .= '\n数据库中不存在的商品货号：';
                    $ms = '';
                    foreach ($tmp as $k => $v){
                        if ($k >= 10){
                            $ms = '...\n'; break;
                        }

                        if ($k < 5){
                            $tmp1[] = $v; continue;
                        }
                        $tmp2[] = $v;
                    }

                    $msg['error'] .= '\n'.implode(',', $tmp1);
                    if (!empty($tmp2)) $msg['error'] .= '\n'.implode(',', $tmp2);
                    $msg['error'] .= $ms;
                    $tmp1 = $tmp2 = null;
                }

                $this->kvdata = '';
                return false;
            }

            return true;
        }

        $mark = false;
        $fileData = $this->kvdata;

        if( !$fileData ) $fileData = array();

        if( substr($row[0],0,2) == '*:' ){
            $titleRs =  array_flip($row);
            $mark = 'title';

            return $titleRs;
        }else{
            if( $row[0] ){
                # 盘点货品处理
                if( array_key_exists( '*:商品名称',$title ) )
                {
                    $product     = $basicMaterialObj->dump(array('material_bn'=>trim($row[1])), '*');
                    
                    if(!$product){
                        $this->flag = true;
                        $this->not_exist_product_bn = isset($this->not_exist_product_bn)?array_merge($this->not_exist_product_bn,array($row[1])):array($row[1]);
                    }else {
                        $row['product_id'] = ($product['bm_id'] ? $product['bm_id'] : 0);
                    }
                    unset($product);
                    $fileData['products']['contents'][] = $row;
                } else {
                    # 盘点仓库处理
                    $branch_sql = "SELECT branch_id,`name` FROM sdb_ome_branch WHERE `name`='".trim($row[0])."' ";
                    
                    $branch = $this->db->selectrow( $branch_sql );
                   
                    $branch_id = $branch['branch_id'];
                    if ( !$branch ){
                        $msg['error'] = "没有此仓库：".$row[0];
                        unset($branch);
                        return false;
                    }
                    $fileData['branch']['branch_id'] = $branch_id;
                    $fileData['branch']['name'] = $branch['name'];
                    $inventory_type = $this->get_inventory_type($row[6],'value');
                    if(!$inventory_type){
                        $msg['error'] = "盘点类型无法标识";
                        return false;
                    }

                    # 全盘
                    if($inventory_type==2){
                        $inv_exist = $this->db->select('SELECT inventory_id FROM sdb_taoguaninventory_inventory WHERE branch_id='.$branch_id.' AND confirm_status=\'1\' AND inventory_type in (2,3)');
                        
                        if($inv_exist){
                           $msg['error'] = "此仓库已有盘点方式为全盘或部分的盘点单存在,请确认后再导入";
                           unset($inv_exist);
                            return false;
                        }

                       
                    }else if(($inventory_type==3) || ($inventory_type==1)){# 部分与自定义
                         $inv_exist2 = $this->getlist('inventory_id',array('branch_id'=>$branch_id,'inventory_type'=>2,'confirm_status'=>1),0,1);
                         if($inv_exist2){
                            $msg['error'] = "请将此仓库全盘确认后再新建部分盘点";
                            unset($inv_exist2);
                            return false;
                        }
                    }else if($inventory_type==4){//期初
                        $branch_product = kernel::single('taoguaninventory_inventorylist')->check_product_iostock($branch_id);

                        if($branch_product){
                            $msg['error'] = "此仓库已存在进出库商品不可以期初盘点";
                            unset($branch_product);
                            return false;
                        }
                        $branch_inventory = kernel::single('taoguaninventory_inventorylist')->get_inventorybybranch_id($branch_id);
                        if($branch_inventory){
                            $msg['error'] = "此仓库已有类型为期初的盘点单存在!";
                            unset($branch_inventory);
                            return false;
                        }
                    }
                    $row[6] = $inventory_type;
                    $fileData['branch']['contents'][] = $row;
                }

                $this->kvdata = $fileData;
            }else {
                $msg['error'] = "商品名称不能为空！";
                return false;
            }
        }
        return null;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    function getProductPos($product_id,$branch_id=0){
        if (is_array($product_id)) {
            $product_id = reset($product_id);
        }
        $product_id = intval($product_id);
        if (!$product_id) {
            return array();
        }

        $sql = 'select bp.store_position as pos_name, bp.pos_id, create_time
            from
                sdb_ome_branch_product_pos as bpp
            left join
                sdb_ome_branch_pos as bp on bpp.pos_id=bp.pos_id
            where
                bpp.product_id='.$product_id;

        if($branch_id && $branch_id>0){
            $sql .= ' AND bp.branch_id='.$branch_id;
        }

        $rows = $this->db->select($sql);
        foreach($rows as $k=>$row){
            $row['create_time'] = date('Y-m-d H:i',$row['create_time']);
            $rows[$k] = $row;
        }
        return $rows;
    }

    function delProductPos($product_id,$delPos){
        return $this->db->exec('delete from sdb_ome_branch_product_pos where product_id='.$product_id.' and pos_id in('.implode(',', $delPos).') ');
    }

    /**
    *彻底删除盘点
    *
    */
    function batch_delete($data){
        $db = kernel::database();
       if($data){
            foreach($data as $k=>$v){
                $inventory_id = $v;
                $db->exec('DELETE FROM sdb_taoguaninventory_inventory WHERE inventory_id='.$inventory_id);
                $db->exec('DELETE FROM sdb_taoguaninventory_inventory_object WHERE inventory_id='.$inventory_id);
                $db->exec('DELETE FROM sdb_taoguaninventory_inventory_items WHERE inventory_id='.$inventory_id);            }
            return true;
        }

    }

     function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    /*
     * 根据条码获取货品
     */
    function getProductbybarcode($branch_id, $barcode)
    {
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        #查询条形码对应的bm_id
        $bm_ids    = $basicMaterialBarcode->getBmidListByBarcode($barcode);
        if (is_array($bm_ids)) {
            $bm_ids = reset($bm_ids);
        }
        if (empty($bm_ids)) {
            return false;
        }

        $basicMateriaItem    = $basicMaterialLib->getBasicMaterialDetail($bm_ids);
        if ($basicMateriaItem)
        {
            $basicMateriaItem['product_id']    = $basicMateriaItem['bm_id'];
            $basicMateriaItem['name']    = $basicMateriaItem['material_name'];
            
            return $basicMateriaItem;
        }
        else
        {
            return false;
        }
    }

    /*
     * 根据货号获取货品
     */
    function getProductbybn($branch_id, $bn)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $row    = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id');
        if(empty($row))
        {
            return false;
        }
        
        $basicMateriaItem    = $basicMaterialLib->getBasicMaterialDetail($row['bm_id']);
        if ($basicMateriaItem)
        {
            $basicMateriaItem['product_id']    = $basicMateriaItem['bm_id'];
            $basicMateriaItem['name']    = $basicMateriaItem['material_name'];
            
            return $basicMateriaItem;
        }
        else
        {
            return false;
        }
    }

    function dead_inventory($inventory_list){
        $opObj  = app::get('ome')->model('operation_log');
         if( is_array($inventory_list) ){
            foreach($inventory_list as $k=>$v){
                $sql = 'UPDATE sdb_taoguaninventory_inventory SET confirm_status=3 WHERE confirm_status=1 AND inventory_id='.$v;

                $result = $this->db->exec($sql);
                if($result){
                    $opObj->write_log('inventory_modify@taoguaninventory', $v, '盘点单作废成功');

                }
            }

        }
        return true;
    }

    /**
    * search 有两个值key 时是按键返回值 value时按值返回键
    */
    function get_inventory_type($inventory_type,$search){
        $type = array (
                '1' => '自定义',
                '2' => '全盘',
                '3' => '部分盘点',
                '4' => '期初',
            );
        if($search=='key'){
            return $type[$inventory_type];
        }else{


            $result =  array_search($inventory_type,$type);
            return $result;
        }
    }
    public function _filter($filter, $tableAlias=null, $baseWhere=null) {
        
        
        return parent::_filter($filter, $tableAlias, $baseWhere) . $where;
    }


    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams) {
        $type = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        }
        elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }
    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams) {
        $params = $logParams['params'];
        $type = 'warehouse';
        if ($logParams['app'] == 'taoguaninventory' && $logParams['ctl'] == 'admin_inventorylist') {
            $type .= '_checkManager_check';
        }
        elseif ($logParams['app'] == 'taoguaninventory' && $logParams['ctl'] == 'admin_inventory') {
            $type .= '_checkManager_checkTemplate';
        }
        $type .= '_export';
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams) {
        $params = $logParams['params'];
        $type = 'warehouse';
        if ($logParams['app'] == 'taoguaninventory' && $logParams['ctl'] == 'admin_inventorylist') {
            $type .= '_checkManager_check';
        }
        $type .= '_import';
        return $type;
    }

    public function fcount_csv($filter=null){
        $sql = $this->_export_sql($filter);

        $sql = sprintf($sql,'count(1) AS _c');

        $row = $this->db->selectrow($sql);

        return $row['_c'] > 0 ? $row['_c'] : 1;
    }

    /**
     * 队列导出SQL
     *
     * @return void
     * @author 
     **/
    private function _export_sql($filter)
    {
        if ($filter['export_type'] == '0') {
            $sql  = "SELECT %s FROM `sdb_ome_branch_pos` AS pos 
                    LEFT JOIN `sdb_ome_branch_product_pos` AS pp ON (pos.pos_id=pp.pos_id)
                    LEFT JOIN `sdb_ome_products` AS p ON (pp.product_id=p.product_id)
                    LEFT JOIN `sdb_ome_goods` AS g ON (p.goods_id=g.goods_id)
                    LEFT JOIN `sdb_ome_branch_product` AS bp ON (p.product_id=bp.product_id)
                    WHERE 1";        
        } else {
            $sql = "SELECT %s FROM `sdb_ome_products` AS p
                    LEFT JOIN `sdb_ome_goods` AS g ON (p.goods_id=g.goods_id)
                    LEFT JOIN `sdb_ome_branch_product` AS bp ON (p.product_id=bp.product_id)
                    WHERE 1";
        }


        if ($filter['branch_id']) $sql .= ' AND bp.branch_id='.$filter['branch_id'];
        if ($filter['brand_id']) $sql .= ' AND g.brand_id='.$filter['brand_id'];
        if ($filter['type_id']) $sql .= ' AND g.type_id='.$filter['type_id'];
        if ($filter['product_visibility'] == '0') $sql .= ' AND g.visibility="true"';
        if ($filter['product_name']) {
            $product_name_sql = array();
            foreach ($filter['product_name'] as $value) {
                $product_name_sql[]= 'p.name regexp "'. $value .'"';
            }
            $sql .= ' AND ('.implode(' OR ', $product_name_sql).')';
        }
        if ($filter['product_bn']) {
            $product_bn_sql = array();
            foreach ($filter['product_bn'] as $value) {
                $product_bn_sql[] = 'p.bn regexp "'.$value.'"';
            }
            $sql .= ' AND ('.implode(' OR ', $product_bn_sql).')';
        }
        if ($filter['branch_store']) {
            foreach ($filter['branch_store'] as $key => $value) {
                $op = $filter['store_operator'][$key] == '>=' ?  '>=' : '<=';

                $sql .= ' AND bp.store '.$op.$value;
            }
        }

        return $sql;
    }

    /**
     * 队列导出
     *
     * @return void
     * @author 
     **/
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $offset, $limit, $op_id)
    {
        $data = array();

        $charset = kernel::single('base_charset');

        $branch_id = $filter['branch_id'];

        $export_title = $this->io_title('export');
        if ($curr_sheet == 1) {
            // 仓库及操作人信息
            $data[] = $this->io_title('branch');

            $branch = app::get('ome')->model('branch')->dump($branch_id,'name');
            $data[] = array(
                'branch'           => $branch['name'],
                'name'             => $filter['inventory_name'],
                'f_checker'        => $filter['inventory_checker'],
                's_checker'        => $filter['second_checker'],
                'finance_dept'     => $filter['finance_dept'],
                'warehousing_dept' => $filter['warehousing_dept'],
                'type'             => $this->get_inventory_type($filter['inventory_type'],'key'),
            );

            $data[] = $export_title;
        }

        $start_page = intval($filter['start_page']); $end_page = intval($filter['end_page']);
        $exportctl = ($start_page && $start_page > $curr_sheet) || ($end_page && $end_page < $curr_sheet) ? false : true;

        if ( $exportctl && $filter['inventory_type'] != 4 ) {

            $inventorylistLib = kernel::single('taoguaninventory_inventorylist');
            // if ($filter['inventory_type'] != 4) {
                $sql = $this->_export_sql($filter);

                if ($filter['export_type'] == '0') {
                    $sql = sprintf($sql, 'pos.store_position,p.visibility,p.product_id,p.bn,p.name,p.spec_info,p.unit,p.barcode,bp.store');
                } else {
                    $sql = sprintf($sql, 'p.visibility,p.product_id,p.bn,p.name,p.spec_info,p.unit,p.barcode,bp.store');
                }
                
                // if (intval($filter['start_page']) > 1) {
                //     $offset = ($filter['start_page'] - $curr_sheet) * $limit;
                // }

                $rows = $this->db->selectlimit($sql,$limit,$offset);

                foreach ($rows as $row) {
                    $row['bn'] = $row['bn'] . "\t";
                    $row['barcode'] = $row['barcode'] . "\t";
                    $row['price'] = $inventorylistLib->get_price($row['product_id'],$branch_id);
                    $row['visibility'] = strtoupper($row['visibility'])=='TRUE' ? '显示' : '隐藏';

                    if ($filter['export_type'] == '1') {
                        $branch_pos = $this->getProductPos($row['product_id'],$branch_id);

                        if ($filter['store_show'] == '0') $row['store'] = '';

                        $row['pos_name'] = '';

                        if ($branch_pos) {
                            foreach ($branch_pos as $pos) {
                                $row['pos_name'] .= $pos['pos_name'] . '、';
                            }

                            $row['pos_name'] = trim($row['pos_name'],'、');
                        }

                    } else {
                        $row['store'] = '';
                        $row['pos_name'] = $row['store_position'];
                    }
                    
                    $drow = array();
                    foreach ($this->oSchema['csv']['export'] as $col) {
                        $drow[] = utils::apath($row, explode('/', $col));

                    }

                    $data[] = $drow;
                }
            // }
        } else {
            $data[] = array("","");
        }

        $content = array('content'=>array('main'=>array()));        
        if ($data) {
            foreach ($data as $key => $value) {
                $value = array_map(array($charset,'utf2local'), $value);

                $content['content']['main'][] = '"'.implode('","',$value).'"';
            }
        }

        return $content;
    }

}
