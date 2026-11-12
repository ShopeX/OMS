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


class eccommon_ctl_regions extends desktop_controller{

    var $workground = 'eccommon_center';

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
	{
		parent::__construct($app);
		header("cache-control: no-store, no-cache, must-revalidate");
	}

    /**
     * index
     * @return mixed 返回值
     */
    public function index(){
        $obj_regions_op = kernel::single('eccommon_regions_operation');
        $this->path[]=array('text'=>'配送地区列表');

        if ($obj_regions_op->getTreeSize())
		{
			//超过100条
            $this->pagedata['area'] = $obj_regions_op->getRegionById();
            $this->page('regions/area_treeList.html');
        }
		else
		{
            $obj_regions_op->getMap();
            $this->pagedata['area'] = $obj_regions_op->regions;
            $this->page('regions/area_map.html');
        }
    }

    /**
     * showRegionTreeList
     * @param mixed $serid ID
     * @param mixed $multi multi
     * @return mixed 返回值
     */
    public function showRegionTreeList($serid,$multi=false)
	{
         if ($serid)
		 {
			$this->pagedata['sid'] = $serid;
         }
		 else
		 {
			$this->pagedata['sid'] = substr(time(),6,4);
         }

         $this->pagedata['multi'] =  $multi;
         $this->singlepage('regions/regionSelect.html');
    }

    /**
     * 获取ChildNode
     * @return mixed 返回结果
     */
    public function getChildNode()
	{
		$obj_regions_op = kernel::single('eccommon_regions_operation');
        $this->pagedata['area'] = $obj_regions_op->getRegionById($_POST['regionId']);
        $this->display('regions/area_sub_treeList.html');
    }

    /**
     * 获取RegionById
     * @param mixed $pregionid ID
     * @return mixed 返回结果
     */
    public function getRegionById($pregionid)
	{
		$obj_regions_op = kernel::single('eccommon_regions_operation');
        echo json_encode($obj_regions_op->getRegionById($pregionid));
    }

    /**
     * 添加新地区界面
     * @params string 父级region id
     * @return null
     */
    public function showNewArea($pRegionId=null)
	{
        if ($pRegionId){
            $dArea = app::get('eccommon')->model('regions');
            $this->pagedata['parent'] = $dArea->getRegionByParentId($pRegionId);
        }
        $this->path[] = array('text'=>'添加配送地区');
        $this->display('regions/area_new.html');
    }

    /**
     * 添加新地区
     * @params null
     * @return null
     */
    public function addDlArea()
	{
		$this->begin('index.php?app=eccommon&ctl=regions&act=index');
		// 写实现收敛到 capability（与 api_regions 共用同一份；ownTransaction=false：本页事务由 begin()/end() 托管）
		$result = (new eccommon_capability_regions())->addArea($_POST, false);
		$this->end($result['ok'], $result['ok'] ? $result['msg'] : '新建失败，'.$result['msg']);
    }

    /**
     * 修改地区信息的入口
     * @params null
     * @return null
     */
    public function saveDlArea()
	{
		$this->begin('index.php?app=eccommon&ctl=regions&act=index');
		// 写实现收敛到 capability（与 api_regions 共用同一份；ownTransaction=false：本页事务由 begin()/end() 托管）
		$result = (new eccommon_capability_regions())->saveArea($_POST, false);
		$this->end($result['ok'], $result['ok'] ? $result['msg'] : '修改失败，'.$result['msg']);
    }

    /**
     * 编辑显示页面
     * @params string 地区的regions id
     * @return null
     */
    public function detailDlArea($aRegionId)
	{
        $this->path[] = array('text'=>'配送地区编辑');
        $oObj = app::get('eccommon')->model('regions');
        $this->pagedata['area'] = $oObj->getDlAreaById($aRegionId);
        $this->display('regions/area_edit.html');
    }

    /**
     * 删除对应regions id 的地区
     * @params string region id
     * @return null
     */
    public function toRemoveArea($regionId)
	{
        $this->begin('index.php?app=eccommon&ctl=regions&act=index');
        // 写实现（含「已有订单使用」守卫）收敛到 capability（与 api_regions 共用同一份；ownTransaction=false）
        $result = (new eccommon_capability_regions())->removeArea($regionId, false);
        $this->end($result['ok'], $result['msg']);
    }

    /**
     * 更新地区排序数据
     * @params null
     * @return null
     */
    public function updateOrderNum()
	{
        $this->begin('index.php?app=eccommon&ctl=regions&act=index');
        // 写实现收敛到 capability（与 api_regions 共用同一份；ownTransaction=false：本页事务由 begin()/end() 托管）
        $pOrder = (isset($_POST['p_order']) && is_array($_POST['p_order'])) ? $_POST['p_order'] : array();
        $result = (new eccommon_capability_regions())->updateOrderNum($pOrder, false);
        $this->end($result['ok'], $result['msg']);
    }

    /**
     * 获取ChlidById
     * @param mixed $pId ID
     * @return mixed 返回结果
     */
    public function getChlidById($pId)
	{
        $obj_regions_op = kernel::single('eccommon_regions_operation');
        $this->pagedata['area'] = $obj_regions_op->getRegionById($pId);
        $this->pagedata['area_pid'] = $pId;
        $this->display('regions/area_child_treeList.html');
    }

    /**
     * 获取ChildSub
     * @return mixed 返回结果
     */
    public function getChildSub()
	{
		$obj_regions_op = kernel::single('eccommon_regions_operation');
        $this->pagedata['area'] = $obj_regions_op->getRegionById($_POST['regionId']);
        $this->display('regions/area_child_sub_treeList.html');
    }
}
