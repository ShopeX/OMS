<?php
/**
 * 定价物料mapping
 *
 * @author sunjing@shopex.cn
 * @version 0.1
 */

class goldentra_mdl_mapping extends dbeav_model {

	public static $mold_list = array();
	public static $mold_item = array();
	public static $mold_detail = array();
    var $export_name = '定价物料mapping';
    /**
     * 导入模板的标题
     *
     * @param Null
     * @return Array
     */
    function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    /**
     * 导入导出的标题
     *
     * @param Null
     * @return Array
     */
    function io_title( $filter, $ioType='csv' ){
        switch( $filter ){
            
            case 'export':
                $this->oSchema['csv'][$filter] = array(
                    '*:模号'     => 'mold_code',
                    //0
                    '*:最小重量'   => 'min_weight',
                    //1
                    '*:最大重量'   => 'max_weight',
                    //2
                    '*:显示克重'   => 'weight',
                    //3
                    '*:主石最小重量' => 'min_main',
                    //4
                    '*:主石最大重量' => 'max_main',
                    //5
                    '*:主石重量'   => 'main_weight',
                    //6
                    '*:最小辅石重'  => 'min_extra',
                    //7
                    '*:最大辅石重'  => 'max_extra',
                    //8
                    '*:配石重'    => 'extra_weight',
                    //9
                    '*:最小标签价'  => 'min_tag_price',
                    //10
                    '*:最大标签价'  => 'max_tag_price',
                    //11
                    '*:显示标签价'  => 'tag_price',
                    //12
                    '*:颜色'     => 'color',
                    //13
                    '*:净度'     => 'clarity',
                    //14
                    '*:切工'     => 'diamond_cut',
                    //15
                );
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
    }

    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg){
      
        if (empty($row)){
            return true;
        }
        $mark = false;
        
        if( substr($row[0],0,1) == '*' ){
            $titleRs =  array_flip($row);
            $mark = 'title';
            
           
            return $titleRs;
        }else{
        	$re = base_kvstore::instance('goldentra_mapping')->fetch('basic-'.$this->ioObj->cacheTime,$fileData);
        	if( !$re ) $fileData = array();
        	if(!$row[0] || !$row[1] || !$row[2] ){
                $msg['error'] = "确认必填项都有值";
                return false;
            }

            $row[0] = trim($row[0]);
            // //判断模号是否存在
            // $main = $this->fetch_mold($row[0]);
            // if ($main){
            //     $msg['error'] = $row[0]."模号已存在";
            //     return false;
            // }

            //已存在记录不可以再导入
            // $item = $this->fetch_mold_item($row[0]);
            // if ($item){
            //     $msg['error'] = $row[0]."已有记录不可以导入";
            //     return false;
            // }
            $row[1] = sprintf('%.3f',$row[1]);
            $row[2] = sprintf('%.3f',$row[2]);
            $row[4] = sprintf('%.3f',$row[4]); // 主石最小重量支持3位小数
            $comp = bccomp($row[2], $row[1],2);
            if ($comp<=0){
                $msg['error'] = $row[0]."价格区间异常,最小区间:".$row[1].',最大区间:'.$row[2];
                return false;
            }
            $sdf = array(
                'mold_code'		=>	$row[0],
                'min_weight'		=>	$row[1], 
                'max_weight'		=>	$row[2], 
                'weight'			=>	sprintf('%.3f',$row[3]), 
                'min_main'		=>	$row[4]?$row[4]:0, 
                'max_main'		=>	$row[5]?$row[5]:0, 
                'main_weight'    =>  $row[6]?$row[6]:0,
                'min_extra'		=>	$row[7]?$row[7]:0,
                'max_extra'		=>	$row[8]?$row[8]:0,
                'extra_weight'	=>	$row[9]?$row[9]:0,
                'min_tag_price'	=>	isset($row[10]) && $row[10] !== '' ? $row[10] : (isset($row[12]) && $row[12] !== '' ? $row[12] : 0),
                'max_tag_price'	=>	isset($row[11]) && $row[11] !== '' ? $row[11] : (isset($row[12]) && $row[12] !== '' ? bcadd($row[12], '0.001', 3) : 0),
                'tag_price'		=>	isset($row[12]) && $row[12] !== '' ? $row[12] : (isset($row[10]) && $row[10] !== '' ? $row[10] : 0),
            );
            if (bccomp($sdf['max_tag_price'], $sdf['min_tag_price'], 3) <= 0 && bccomp($sdf['tag_price'], 0, 3) > 0) {
                $sdf['min_tag_price'] = $sdf['tag_price'];
                $sdf['max_tag_price'] = bcadd($sdf['tag_price'], '0.001', 3);
            }

            $fileData['mold']['contents'][$row[0]][] = $sdf;
            if (count($fileData['mold']['contents'][$row[0]])>1){
                $check_mold = $fileData['mold']['contents'][$row[0]];
                
                $min_weight_list = array();
                $max_weight_list = array();

                $weight_list = array();
                $min_main_list = $max_main_list = $main_weight_list = array();
                $min_extra_list = $max_extra_list = $extra_weight_list = array();
                foreach($check_mold as $ck=>$cv){
                    $min_weight_list[$ck]   =   $cv['min_weight'];
                    $max_weight_list[$ck]   =   $cv['max_weight'];

                    $weight_list[$ck]       =   $cv['weight'];
                    $min_main_list[$ck]     =   $cv['min_main'];
                    $max_main_list[$ck]     =   $cv['max_main'];
                    $main_weight_list[$ck]  =   $cv['main_weight'];
                    $min_extra_list[$ck]    =   $cv['min_extra'];
                    $max_extra_list[$ck]    =   $cv['max_extra'];
                    $extra_weight_list[$ck] =   $cv['extra_weight'];
                }

                // $check = kernel::single('goldentra_mapping_validate')->check(array('min_weight'=>$min_weight_list,'max_weight'=>$max_weight_list),$err);
                $data = array(
                    'min_weight'    =>  $min_weight_list,
                    'max_weight'    =>  $max_weight_list,
                    'weight'        =>  $weight_list,
                    'min_main'      =>  $min_main_list,
                    'max_main'      =>  $max_main_list,
                    'main_weight'   =>  $main_weight_list,
                    'min_extra'     =>  $min_extra_list,
                    'max_extra'     =>  $max_extra_list,
                    'extra_weight'  =>  $extra_weight_list,
                );
                $check = kernel::single('goldentra_mapping_validate')->check($data,$err);
                if (!$check){
                    $msg['error'] = $row[0]."区间异常";
                    return false;
                }
            }
            base_kvstore::instance('goldentra_mapping')->store('basic-'.$this->ioObj->cacheTime,$fileData);
           	
        }
        return null;
    }

    function finish_import_csv()
    {

        @ini_set('memory_limit','1024M');
        set_time_limit(0);
        base_kvstore::instance('goldentra_mapping')->fetch('basic-'.$this->ioObj->cacheTime,$data);
        base_kvstore::instance('goldentra_mapping')->store('basic-'.$this->ioObj->cacheTime,'');

        $oQueue = app::get('base')->model('queue');
        $aP = $data;
        $pSdf = array();

        $count = 0;
        $limit = 10;
        $page = 0;
        $orderSdfs = array();


        //loop mold_code
        foreach ($aP['mold']['contents'] as $k => $aPi){
            $pSdf[$page][] = $aPi;
            $page++;
        }

        foreach($pSdf as $v){

            $queueData = array(
                'queue_title'=>'定价物料Mapping导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$v,
                    'app' => 'goldentra',
                    'mdl' => 'mapping'
                ),
                'worker'=>'goldentra_mapping_to_import.run',
            );
            $oQueue->save($queueData);
        }
        $oQueue->flush();
        
       
        return null;
    }

    function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }


    public function fetch_mold($mold_code) 
    {
        if(isset(self::$mold_list[$mold_code])) return self::$mold_list[$mold_code];
        
        $moldObj = app::get('goldentra')->model('mapping');
        $mold = $moldObj->dump(array('mold_code'=>$mold_code),'mapping_id');
        
        self::$mold_list[$mold_code] = $mold['mapping_id'];

        return self::$mold_list[$mold_code];
    }

    public function fetch_mold_item($mold_code){
    	if (isset(self::$mold_item[$mold_code])) return self::$mold_item[$mold_code];

    	$itemObj = app::get('goldentra')->model('mapping_item');
    	$item = $itemObj->dump(array('mold_code'=>$mold_code),'item_id');
    	self::$mold_item[$mold_code] = $item['item_id'];

    	return self::$mold_item[$mold_code];
    }

    public function fetch_mold_detail($mapping_id) 
    {
        if(isset(self::$mold_detail[$mapping_id])) return self::$mold_detail[$mapping_id];
        
        $moldObj = app::get('goldentra')->model('mapping');
        $mold = $moldObj->dump(array('mapping_id'=>$mapping_id),'mold_code');
        
        self::$mold_detail[$mapping_id] = $mold;
        return self::$mold_detail[$mapping_id] ;
    }

    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id){
        $mapping_ids = $filter['mapping_id'];
        $itemsObj = app::get('goldentra')->model('mapping_item');
        $db = kernel::database();
        if($curr_sheet == 1){
            $title = array();

            foreach($this->io_title('export') as $k => $v ){
                $title[] = mb_convert_encoding($v, 'GBK', 'UTF-8');
            }
            $data['content']['main'][]= '"'.implode('","',$title).'"';
        }

        $items_list = $itemsObj->getlist('*',array('mapping_id'=>$mapping_ids));
        foreach( $items_list as $aFilter ){
            $mold = $this->fetch_mold_detail($aFilter['mapping_id']);
            $pRow = array();
            $detail['mold_code'] = mb_convert_encoding($mold['mold_code'], 'GBK', 'UTF-8');
           	$detail['min_weight'] = $aFilter['min_weight'];
            $detail['max_weight'] = $aFilter['max_weight'];
            $detail['weight'] = $aFilter['weight'];
            $detail['min_main'] = $aFilter['min_main'];
            $detail['max_main'] = $aFilter['max_main'];
            $detail['main_weight'] = $aFilter['main_weight'];
            $detail['min_extra'] = $aFilter['min_extra'];
            $detail['max_extra'] = $aFilter['max_extra'];
            $detail['extra_weight'] = $aFilter['extra_weight'];
            $detail['min_tag_price'] = $aFilter['min_tag_price'];
            $detail['max_tag_price'] = $aFilter['max_tag_price'];
            $detail['tag_price'] = $aFilter['tag_price'];
            $detail['color'] = $aFilter['color'];
            $detail['clarity'] = $aFilter['clarity'];
            $detail['diamond_cut'] = $aFilter['diamond_cut'];

            $data['content']['main'][] = '"'.implode('","',$detail).'"';
        }

        return $data;

    }

    /**
     * HASH值以（D）+模号(M01)+克重(9999999)+主重+辅重组合
     */
    public function gen_id($items){
        //模号(M01)+克重(9999999)+主重+辅重
        if(!$items['mold_code']) return false;

        //main_weight  extra_weight
        $weight = $items['weight']*100;
        $weight = str_pad($weight,8,'0',STR_PAD_LEFT);

        $main_weight = $items['main_weight']*100;
        $main_weight = str_pad($main_weight,8,'0',STR_PAD_LEFT);

        $extra_weight = $items['extra_weight']*100;
        $extra_weight = str_pad($extra_weight,8,'0',STR_PAD_LEFT);

        $sign = 'D'.$items['mold_code'].$weight.$main_weight.$extra_weight;
        

        $sign = sprintf('%u', crc32($sign));
        $material_bn = 'D'.$items['mold_code'].$sign;

        return $material_bn; 
    }

    public function batch_save_mold($mapping_id){
        $itemsObj = app::get('goldentra')->model('mapping_item');

        $items_list = $itemsObj->getlist('*',array('mapping_id'=>$mapping_id));
        foreach($items_list as $v){
            if(!$v['bn']){
                $bn = $this->gen_id($v);
                if($bn){
                    $itemsObj->update(
                        array('bn' => $bn, 'up_time' => time()),
                        array('item_id' => $v['item_id'])
                    );
                }
            }
        }
    }

}
