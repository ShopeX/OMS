<?php
/*
 * 供应商
 */
class purchase_mdl_supplier extends dbeav_model{
    var $has_many = array(
        'brand' => 'supplier_brand:replace'
    );
    //是否有导出配置
    var $has_export_cnf = true;
    //导出的文件名
    var $export_name = '供应商';
    
    /*
     * 添加/修改供应商信息
     */
    public function save_supplierDo($data,$op_type=false){
        //供应商入库
        $contacter = array();
        if ($data['lianxi_name'])
        foreach ($data['lianxi_name'] as $k=>$v){
            $ishave = 0;
            $c['name'] = $v;
            $c['telphone'] = $data['lianxi_telphone'][$k];
            $c['email'] = $data['lianxi_email'][$k];
            $c['qqwangwang'] = $data['lianxi_qqwangwang'][$k];
            if (!$c['name'] and !$c['telphone'] and !$c['email'] and !$c['qqwangwang']) $ishave = 0;
            else $ishave = 1;
            $contacter[] = $c;
        }
        if ($ishave) $contacter = serialize($contacter);
        else $contacter = '';
        $data['contacter'] = $contacter;

        // 兼容MYSQL8.0
        if (!$data['arrive_days']) {
            unset($data['arrive_days']);
        }


		if ($data['supplier_id']){
			$datas = $data;
			unset($datas['brand']);
			$supplier_result = $this->save($datas);
		}else{
			$supplier_result = $this->insert($data);
		}
        //初始化供应商品牌模块
        $oSBrand = $this->app->model("supplier_brand");
        
        //如果是更新品牌，先删除，然后重新入库
        if($op_type){
            $oSBrand->delete(array("supplier_id"=>$data['supplier_id']));
        }
        
        //供应商品牌关联入库
        $brand_arr = array();
        if ($data['brand']){
            foreach ($data['brand'] as $k=>$v){
                $brand_arr = array("supplier_id"=>$data['supplier_id'],"brand_id"=>$v);
                if($v) $brand_result = $oSBrand->saveSupplierBrand($brand_arr);
            }
        }
        
        if ($supplier_result) return true;
        else return false;
        
    }
    
    /*
     * 全角半角转换
     */
    function half_shaped($str=null){
        //$str = "０１２３ＡＢＣＤＦＷＳ＼＂，．？＜＞｛｝［］＊＆＾％＃＠！～（）＋－｜：；";
        $str = preg_replace('/\xa3([\xa1-\xfe])/e', 'chr(ord(\1)-0x80)', $str);
        return $str;
    }
    
    /*
     * 供应商详情 supplier_detail
     * @param int
     * @return ArrayObject
     */
    public function supplier_detail($supplier_id='', $cols='*')
    {
        if ($supplier_id){
               $oSupplier = $this->app->model("supplier");
            $filter = array("supplier_id"=>$supplier_id);
            return $oSupplier->dump($filter, $cols);
        }else return false;
    }
    
    /*
     * 获取供应商采购单预付款
     * @package get_balance
     */
    public function get_balance($supplier_id=null)
    {
        $oPo = $this->app->model('po');
        $filter = array('supplier_id'=>$supplier_id);
        $balance = $oPo->getList('po_bn,deposit_balance,purchase_time', $filter);
        $result = array();
        foreach ($balance as $key=>$val){
            if ($val['deposit_balance']<1) continue;
            $result[] = $val;
        }
        return $result;
    }
    
    /*
     * 供应商商品历史价格：history_price
     */
    public function history_price_search($supplier_id,$goods_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        
        $product_ids = array();
        $products    = $basicMaterialSelect->getlist('bm_id', array('bm_id'=>$goods_id));
        if(empty($products))
        {
            return '';
        }
        foreach ($products as $key => $val)
        {
            $product_ids[]    = $val['product_id'];
        }
        
        $sql = " SELECT c.`product_id`,c.`eo_bn`,c.`purchase_price`,c.`purchase_time` FROM `sdb_purchase_branch_product_batch` c 
        WHERE c.`supplier_id`='$supplier_id' and c.`product_id` in (". implode(',', $product_ids) .")";
        $his_price = $this->db->select($sql);
        return $his_price;
    }
    
    //信用等级
    public function getCreditlve(){
        for ($i=1;$i<=5;$i++){
            $arr[$i] = $i;
        }
        return $arr;
    }
    
    function validate(&$data){
        $bn = trim($data['bn']);#供应商编码 
        $name = trim($data['name']);#供应商名称
        $pattrn = '/^[a-zA-Z0-9]{1,32}$/';
        if (!preg_match($pattrn ,$bn)){
            trigger_error(app::get('base')->_('供应商编号由字母及数字组成，且最多32位'),E_USER_ERROR);
        }
        if(!$bn){
            trigger_error(app::get('base')->_('供应商编码不能为空'),E_USER_ERROR);
        }
        if(!$name){
            trigger_error(app::get('base')->_('供应商名称不能为空'),E_USER_ERROR);
        }
        $row_bn = $row_name = "";
        $row_bn = $this->dump(array('bn'=>$bn), 'bn');
        $row_name = $this->dump(array('name'=>$name), 'name');
        if ($data['supplier_id']){
          /*
           * 修改供应商判断是否重复
           */
          if ($data['ini_bn']<>$bn){
            if($row_bn){
              trigger_error(app::get('base')->_('该供应商编码已经存在'),E_USER_ERROR);
            }
          }
          if ($data['ini_name']<>$name){
            if($row_name){
              trigger_error(app::get('base')->_('该供应商名称已经存在'),E_USER_ERROR);
            }
          }
        }else{
          $msg = $row_bn['bn'] ? '编码' : '名称';
          if($row_bn['bn']==$bn or $row_name['name']==$name){
            trigger_error(app::get('base')->_('该供应商'.$msg.'已经存在'),E_USER_ERROR);
          }
        }
    }
    
    //填写供应商表单数据过滤
    public function filterFormItem($data=null,$needFilter=null){
        
        if ($data)
        foreach ($data as $k=>$v){
            if ( (in_array($k,($needFilter)) or $k==$needFilter) and $v==""){
                trigger_error('供应商编码不能为空！',E_USER_ERROR);
            }
        }
    }
    
    /*
     * 获取供应商品牌 getBrand
     * 
     * @return array
     */
    function getBrand($brand_id=''){
        if (kernel::servicelist('purchase_supplier_brand_apps'))
        foreach(kernel::servicelist('purchase_supplier_brand_apps') as $object){
            return $object->getBrand($brand_id);
        }
    }
    
   /*
     * 删除供应商
     * @package pre_recycle
     */
    function pre_recycle($data=null){
        $poObj = $this->app->model('po');
        $returnObj = $this->app->model('returned_purchase');
        foreach ($data as $val){
           if($val['supplier_id']){
               $returnPurchase = $returnObj->getList('rp_id', array('supplier_id'=>$val['supplier_id']), 0,-1);
               $pos = $poObj->getList('po_id,po_status,deposit_balance', array('supplier_id'=>$val['supplier_id']), 0,-1);
               if($returnPurchase){
                   return false;
               }
               foreach ($pos as $po){
                   if($po['deposit_balance']>0 || $po['po_status'] != '2'){
                   	   $this->recycle_msg = '该供应商已经被使用，无法删除！';
                       return false;
                   }
               }
           }
        }
        return true;
    }
   
   //QQ旺旺
   function chat_code($string=null, $type=null){
       
        if (!$type){
            if (preg_match("/\//i",$string)){
                $string = explode("/",$string);
                $qq = $string[0];
                $wangwang = $string[1];
                $type = "qqwangwang";
            }elseif (preg_match("/^\d{1,12}$/i",$string)){
                $type = "qq";
            }else $type = "wangwang";
        }
        switch($type){
            case 'qq':
            $msg = <<<EOF
<a href="tencent://message/?uin=$string&amp;Site=&amp;Menu=yes" target="blank"><img border="0" alt="点击这里给我发消息" src="http://wpa.qq.com/pa?p=3:$string:7"></a>
EOF;
            break;
            case 'wangwang':
            $msg = <<<EOF
<a target="_blank" href="http://amos1.taobao.com/msg.ww?v=2&uid=$string&s=1" ><img border="0" src="http://amos1.taobao.com/online.ww?v=2&uid=$string&s=1" alt="点击这里给我发消息" /></a>
EOF;
            break;
            case 'qqwangwang':
            $msg = <<<EOF
<a href="tencent://message/?uin=$qq&amp;Site=&amp;Menu=yes" target="blank"><img border="0" alt="点击这里给我发消息" src="http://wpa.qq.com/pa?p=3:$qq:7"></a>
EOF;
            $msg .= <<<EOF
<a target="_blank" href="http://amos1.taobao.com/msg.ww?v=2&uid=$wangwang&s=1" ><img border="0" src="http://amos1.taobao.com/online.ww?v=2&uid=$wangwang&s=1" alt="点击这里给我发消息" /></a>
EOF;
            break;
        }
        return $msg;
       
   }
   
   //供应商字段，鼠标移上去加上提示信息：供应商缩写
   function modifier_name($row){
       $tmp = '<span title="供应商缩写">'.$row.'</span>';
       return $tmp;
    }
    
   /*
     * 快速查找供应商
     */
    public function getSupplier($name=null)
    {
        $quoted = $this->db->quote((string) $name);
        $sql = " SELECT name,brief,supplier_id,arrive_days FROM `sdb_purchase_supplier` 
        WHERE name regexp ". $quoted ." or brief regexp ". $quoted;
        $data = $this->db->select($sql);
        $result = array();
        if ($data)
        foreach ($data as $v){
            if(!$v['arrive_days']) $v['arrive_days'] = '';
            $result[] = $v;        
        }
        return $result;
    }
    
    /*
     * 导出供应商模板
     */
    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }
    function io_title( $filter, $ioType='csv' ){
        switch( $filter ){
            case 'supplier':
                $this->oSchema['csv'][$filter] = array(
                    //'*:CSCID' => '',
                    '*:编码' => 'bn',
                    '*:简称' => 'name',
                    '*:快速索引' => 'brief',
                    '*:公司名称' => 'company',
                    '*:省市县区' => 'area',
                    '*:街道地址' => 'addr',
                    '*:邮编' => 'zip',
                    '*:公司电话' => 'telphone',
                    '*:传真' => 'fax',
                    '*:采购员' => 'operator',
                    '*:到货天数' => 'arrive_days',
                    '*:信用等级' => 'credit_lv',
                    '*:供应品牌' => 'brand',
                    '*:开户行' => 'bank',
                    '*:银行帐号' => 'account',
                    '*:联系人' => 'contacter_name',
                    '*:电话' => 'contacter_telphone',
                    '*:E-mail' => 'contacter_email',
                    '*:QQ/旺旺' => 'contacter_qqwangwang',
                    '*:备注' => 'memo',
                );
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
     }
     
     /*
      * 导出供应商记录
      */
     function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 ){
        if( !$data['title']['supplier'] ){
            $title = array();
            foreach( $this->io_title('supplier') as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['supplier'] = '"'.implode('","',$title).'"';
        }
        $limit = 100;
        
        if( !$list=$this->getList('*',$filter,$offset*$limit,$limit, 'supplier_id desc') )return false;
        foreach( $list as $aFilter ){
            list($main,$area,$region_id) = explode(':',$aFilter['area']);
            $pRow = array();
            $detail['bn'] = $aFilter['bn'];
            $detail['name'] = $aFilter['name'];
            $detail['brief'] = $aFilter['brief'];
            $detail['company'] = $aFilter['company'];
            $detail['area'] = $area;//$aFilter['area'];
            $detail['addr'] = $aFilter['addr'];
            $detail['zip'] = $aFilter['zip'];
            $detail['telphone'] = $aFilter['telphone'];
            $detail['fax'] = $aFilter['fax'];
            $detail['operator'] = $aFilter['operator'];
            $detail['arrive_days'] = $aFilter['arrive_days'];
            $detail['credit_lv'] = $aFilter['credit_lv'];
            //供应商品牌
            $oBrand = $this->app->model('supplier_brand');
            $brand_detail = $oBrand->getlist('*',array("supplier_id"=>$aFilter['supplier_id']));
            $brand = array();
            foreach ($brand_detail as $k=>$v){
                $brandname = $this->getBrand($v['brand_id']);
                $brand[] = $brandname[0]['brand_name'];
            }
            $detail['brand'] = implode(",",$brand);
            $detail['bank'] = $aFilter['bank'];
            $detail['account'] = $aFilter['account'];
            //获取第一条联系人信息
            $contacter = unserialize($aFilter['contacter']);
            $detail['contacter_name'] = $contacter[0]['name'];
            $detail['contacter_email'] = $contacter[0]['email'];
            $detail['contacter_telphone'] = $contacter[0]['telphone'];
            $detail['contacter_qqwangwang'] = $contacter[0]['qqwangwang'];
            $detail['memo'] = $aFilter['memo'];
            foreach( $this->oSchema['csv']['supplier'] as $k => $v ){
                $pRow[$k] = $this->charset->utf2local( utils::apath( $detail,explode('/',$v) ) );
            }
            $data['content']['supplier'][] = '"'.implode('","',$pRow).'"';
        }
        $data['name'] = '供应商'.date("Ymd",time());
        
        return false;
    }
    function export_csv($data,$exportType = 1 ){
        $output = array();
        //if( $exportType == 2 ){
            foreach( $data['title'] as $k => $val ){
                $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
            }
        //}
        error_log(print_r($output,1),3,DATA_DIR."/output.txt");
        echo implode("\n",$output);
    }
    
    
    /*
     * CSV导入
     */
    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    function finish_import_csv(){
        base_kvstore::instance('purchase_supplier')->fetch('supplier-'.$this->ioObj->cacheTime,$data);
        base_kvstore::instance('purchase_supplier')->store('supplier-'.$this->ioObj->cacheTime,'');
        $pTitle = array_flip( $this->io_title('supplier') );
        $pSchema = $this->oSchema['csv']['supplier'];
        $oQueue = app::get('base')->model('queue');
        $aP = $data;
        $pSdf = array();
        
        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();
        
        foreach ($aP['supplier']['contents'] as $k => $aPi){
            if($count < $limit){
                $count ++;
            }else{
                $count = 0;
                $page ++;
            }
            $pSdf[$page][] = $aPi;
        }
        
        foreach($pSdf as $v){
            $queueData = array(
                'queue_title'=>'供应商导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$v,
                    'app' => 'purchase',
                    'mdl' => 'supplier'
                ),
                'worker'=>'purchase_supplier_to_import.run',
            );
            $oQueue->save($queueData);
        }
        $oQueue->flush();
        return null;
    }
    
    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    //CSV导入业务处理
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg){
        
        if (empty($row)){
            return true;
        }
        $mark = false;
        $re = base_kvstore::instance('purchase_supplier')->fetch('supplier-'.$this->ioObj->cacheTime,$fileData);
        
        if( !$re ) $fileData = array();

        if( substr($row[0],0,1) == '*' ){
            $titleRs =  array_flip($row);
            $mark = 'title';
            
            return $titleRs;
        }else{
            if( $row[0] and $row[1] ){
                //判断编码与供应商简称是否存在
                for($si=0;$si<=1;$si++){
                    if ($si==0){
                        $s_filter = array("bn"=>$row[0]);
                        $message = '系统已存在:'.$row[0].'的供应商编码，不能重复导入;\n如需修改，请进入系统进行操作。'; 
                    }else{
                        $s_filter = array("name"=>$row[1]);
                        $message = '系统已存在:'.$row[1].'的供应商简称，不能重复导入;\n如需修改，请进入系统进行操作。'; 
                    }
                    $exists_data = $this->dump($s_filter, 'supplier_id');
                    if ($exists_data['supplier_id']){
                        $msg['error'] = $message;
                        return false;
                    }
                }
                $fileData['supplier']['contents'][] = $row;
                
                base_kvstore::instance('purchase_supplier')->store('supplier-'.$this->ioObj->cacheTime,$fileData);
            }else{
                $msg['error'] = "供应商编码与简称必须填写";
                return false;
            }
        }
        return null;
    }
   
    function searchOptions(){
        return array(
                'name'=>app::get('base')->_('供应商'),
                'bn'=>app::get('base')->_('编号'),
                'brief'=>app::get('base')->_('快速索引'),
            );
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
        $type = 'purchase';
        if ($logParams['app'] == 'purchase' && $logParams['ctl'] == 'admin_supplier') {
            $type .= '_supplierManager_supplier';
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
        $type = 'purchase';
        if ($logParams['app'] == 'purchase' && $logParams['ctl'] == 'admin_supplier') {
            $type .= '_supplierManager_supplier';
        }
        $type .= '_import';
        return $type;
    }
}
?>
