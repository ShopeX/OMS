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


class eccommon_regions_operation
{
    // 应用实例对象
    static private $app='eccommon';

    // 模型实例
    static private $model;


    //所有地区数据

    static private $regions_arr;


	// 外部可调用的地区数组
	public $regions;

    // 构造方法
    public function __construct($app)
    {
        if(!isset(self::$model)){
            self::$model = app::get(self::$app)->model('regions');
        }
        if(!isset(self::$regions_arr)){

            //先从kv里取
            //空的话再从数据库取

            //self::$regions_arr = self::$model->getList('region_id,local_name,package,p_region_id,region_path,region_grade',null,0,-1);
        }
    }

	 /**
     * 主要用于后台显示，判断当前的数据是否超过100，显示方式不同
     * @params null
     * @return boolean
     */
    public function getTreeSize()
    {
        $cnt = self::$model->count();
        if ($cnt > 100){
            return true;
        }else{
            return false;
        }
    }

	/**
     * 获取指定parent region id的下级地区数量
     * @params string region id
     * @return int 数量
     */
    private function getChildCount($region_id)
    {
		$cnt = self::$model->count(array('p_region_id' => intval($region_id)));
		return $cnt;
    }

	/**
     * 得到地区信息 - parent region id， 层级，下级地区
     * @params string region id
     * @return array 指定信息的数组
     */
    public function getRegionById($regionId='')
    {
        if ($regionId){
			$aTemp = self::$model->getList('region_id,p_region_id,local_name,ordernum,region_path, region_grade', array('p_region_id' => $regionId), 0, -1, 'ordernum ASC,region_id ASC');
        }else{
			$aTemp = self::$model->getList('region_id,p_region_id,local_name,ordernum,region_path, region_grade', array('region_grade' => '1'), 0, -1, 'ordernum ASC,region_id ASC');
        }

        if (is_array($aTemp)&&count($aTemp) > 0)
        {
            foreach($aTemp as $key => $val)
            {
                $aTemp[$key]['p_region_id']=intval($val['p_region_id']);
                $aTemp[$key]['step'] = intval(substr_count($val['region_path'],','))-1;
                $aTemp[$key]['child_count'] = $this->getChildCount($val['region_id']);
            }
        }

        return $aTemp;
    }

    /**
     * 获取所有地区（完整树形结构）
     * @return array 扁平列表，包含所有层级的地区
     */
    public function getAllRegions(): array
    {
        // 使用原生SQL获取所有地区数据
        $sql = "SELECT region_id, p_region_id, local_name, ordernum, region_grade, " .
               "(SELECT count(*) FROM " . self::$model->table_name(1) . " WHERE p_region_id = r.region_id) AS child_count " .
               "FROM " . self::$model->table_name(1) . " AS r " .
               "ORDER BY ordernum ASC, region_id ASC";

        $rows = self::$model->db->select($sql);

        if (!$rows || count($rows) === 0) {
            return [];
        }

        // 构建扁平列表，与原有 API 格式兼容
        $list = [];
        foreach ($rows as $region) {
            $list[] = [
                'region_id' => (string) $region['region_id'],
                'parent_id' => $region['p_region_id'] ? (string) $region['p_region_id'] : '',
                'name' => $region['local_name'],
                'ordernum' => (int) ($region['ordernum'] ?? 0),
                'region_grade' => (int) ($region['region_grade'] ?? 0),
                'child_count' => (int) ($region['child_count'] ?? 0),
            ];
        }

        return $list;
    }

 	/**
  	 * 得到地区的结构图
	 * @params int parent region id
	 * @return array 结构图数组
	 */
	public function getMap($prId='')
	{
        if ($prId){
            $sql="select region_id,region_grade,local_name,ordernum,(select count(*) from ".self::$model->table_name(1)." where p_region_id=r.region_id) as child_count from ".self::$model->table_name(1)." as r where r.p_region_id=".intval($prId)." order by ordernum asc,region_id";
        }else{
            $sql="select region_id,region_grade,local_name,ordernum,(select count(*) from ".self::$model->table_name(1)." where p_region_id=r.region_id) as child_count from ".self::$model->table_name(1)." as r where r.p_region_id is null order by ordernum asc,region_id";
        }

        $row = self::$model->db->select($sql);

        if (isset($row) && $row)
        {
            foreach ($row as $key => $val)
			{
                $this->regions[] = array(
                    "local_name"=>$val['local_name'],
                    "region_id"=>$val['region_id'],
                    "region_grade"=>$val['region_grade'],
                    "ordernum"=>$val['ordernum']
                );

                if ($val['child_count']){
                    $this->getMap($val['region_id']);
                }
            }
        }
    }

    /**
     * 新建修改信息
     * @params array - 请求的数据信息
     * @params string - message
     */
    public function insertDlArea($aData,&$msg)
    {

        if (!trim($aData['local_name']))
        {
            $msg = '地区名称不能为空！';
            return false;
        }

        $aData['ordernum'] = $aData['ordernum'] ? $aData['ordernum'] : '50';
        if (self::$model->checkDlArea($aData['local_name'], $aData['p_region_id']))
        {
            $msg = '该地区名称已经存在！';
            return false;
        }

        $tmp = self::$model->dump(intval($aData['p_region_id']), 'region_path');
        if (!$tmp)
            $tmp['region_path'] = ",";

        $region_path = $tmp['region_path'];
        $aData = array_filter($aData);

        #最多允许新建五级地区
        $count_region_grade    = count(explode(",", $tmp['region_path'])) - 2;
        if($count_region_grade >= 5)
        {
            $msg    = '最多只允许新建五级地区';
            return false;
        }
        
        if (self::$model->save($aData))
        {
            $regionId = self::$model->db->lastInsertId();
            $tmp = self::$model->dump($regionId, '*');
            $tmp['region_path'] = $region_path . $regionId . ',';
            $tmp['region_grade'] = count(explode(",", $tmp['region_path'])) - 2;
            self::$model->save($tmp);

            //设置父级地区有子地区标识
            if($tmp['region_grade'] >1){
                $tmp_parent['region_id'] = $tmp['p_region_id'];

                $tmp_parent['haschild'] = 1;

                self::$model->save($tmp_parent);
            }
            

            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 更改地区信息
     * @params array - 新的地区的信息的数组
     * @params string - 更新结果消息
     */
    public function updateDlArea($aData,&$msg)
    {
        $is_save = false;

        if ($aData['region_id'] == $aData['p_region_id'])
        {
            $msg = '上级地区不能为本地区！';
            return false;
        }
        else
        {
            $idGroup = self::$model->getGroupRegionId($aData['region_id']);
			if ($idGroup)
			{
				if (in_array($aData['p_region_id'],$idGroup)){
					$msg = '上级地区不能为本地区的子地区！';
					return false;
				}
			}
        }

        if(!$aData['region_id'])
        {
            $msg = '参数丢失！';
            return false;
        }
        else
        {
            $cPath = self::$model->dump(intval($aData['region_id']), 'region_path,p_region_id');
        }

        if (!trim($aData['local_name']))
        {
            $msg = '地区名称不能为空！';
            return false;
        }

        if (intval($aData['p_region_id']))
        {
            $tmp = self::$model->dump(intval($aData['p_region_id']), 'region_path');
            $aData['region_path'] = $tmp['region_path'].$aData['region_id'].",";
        }
        else
        {
            $aData['region_path'] = ",".$aData['region_id'].",";
        }

        $aData['ordernum'] = isset($aData['ordernum']) ? $aData['ordernum'] : '50';
        $aData['region_grade'] = count(explode(",",$aData['region_path'])) - 2;
        $aData = array_filter($aData);
        $is_save = self::$model->save($aData);

        //如果原来的父级地区没子地区了，设置成为0

        if(intval($cPath['p_region_id']) >1){
            $same_lv_regions = self::$model->getList('region_id',array('p_region_id' => intval($cPath['p_region_id'])), 0, -1);

            if(count($same_lv_regions) <= 0){
                $tmp_parent['region_id'] = $cPath['p_region_id'];

                $tmp_parent['haschild'] = 0;

                self::$model->save($tmp_parent);

            }
        }
        


        //地区设置成子地区，父级地区有子地区标识为1
        if($aData['region_grade'] >1){
            $tmp_parent2['region_id'] = $aData['p_region_id'];
            $tmp_parent2['haschild'] = 1;

            self::$model->save($tmp_parent2);
        }
        

        return ($is_save && $this->updateSubPath($cPath['region_path'],$aData['region_path']));
    }

	/**
     * 删除指定id的地区信息
     * @params int region id
     * @return boolean 删除成功与否
     */
    public function toRemoveArea($regionId)
    {
		$tmpRow = self::$model->dump(intval($regionId), 'region_path');

		self::$model->delete(array('region_id' => intval($regionId)));

        //如果是最后个子地区，那么父级地区设置为没有child

        if(intval($tmpRow['p_region_id']) >1){

            $same_lv_regions = $this->model->getList('region_id',array('p_region_id' => intval($tmpRow['p_region_id'])), 0, -1);
                if(count($same_lv_regions) <= 0){
                    $tmp_parent['region_id'] = $tmpRow['p_region_id'];

                    $tmp_parent['haschild'] = 0;

                    self::$model->save($tmp_parent);
                }
        }
        

        // 删除相应的所有的下级地区
        $this->toRemoveSubArea($tmpRow['region_path']);

        return true;
    }

    /**
     * 删除指定的级别的区域
     * @params string 层级字符串
     * @return boolean 删除是否成功
     */
    private function toRemoveSubArea($path)
    {
        if ($path)
		{
			return self::$model->delete(array('region_path' => $path));
		}
    }

    /**
     * 更新下级地区的path值
     * @params string 上级的region_path
     * @params string 下一级地区的region_path
     */
    private function updateSubPath($Opath,$Npath)
    {
        $offset = count(explode(",",$Npath)) - count(explode(",",$Opath));

        return self::$model->db->exec("update ".self::$model->table_name(1)." set region_path=replace(region_path,".self::$model->db->quote($Opath)
            .",".self::$model->db->quote($Npath)."),region_grade=region_grade + "
            .intval($offset)." where region_path LIKE '%".$Opath."%'");
    }
}
