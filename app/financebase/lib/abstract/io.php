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

require_once ROOT_DIR.'/vendor/autoload.php';

abstract class financebase_abstract_io{

	public $page_size = 500;

	public $local_path = DATA_DIR.'/financebase/tmp_local/';

	public $file_prefix = '';

	public $file_data = array();

	public $is_write_file = false;

	public $data = array();

	public $file_id = 1;

	public $column_date_cnt = array();

	public $date_time_diff = 0;

    /**
     * 获取Info
     * @param mixed $file file
     * @param mixed $sheet sheet
     * @return mixed 返回结果
     */
    public function getInfo($file,$sheet = 0)
	{
		
	}

    /**
     * 获取Data
     * @param mixed $file file
     * @param mixed $sheet sheet
     * @param mixed $length length
     * @param mixed $start start
     * @param mixed $compatible_csv compatible_csv
     * @return mixed 返回结果
     */
    public function getData($file='',$sheet = 0,$length=0,$start=0,$compatible_csv = true)
	{
		$start = $start ? $start + 1 : 1;
		try {

	        if (empty($file)) {
	            throw new \Exception('文件为空!');
	        }

	        if(!file_exists($file)) {
                /* 转码 */
                $file = iconv("utf-8", "gb2312", $file);
                if(!file_exists($file)) {
                    throw new \Exception('文件不存在!');
                }
	        }
            $path = pathinfo($file);
            $config   = ['path' => $path['dirname']];
            $excel    = new \Vtiful\Kernel\Excel($config);

            // 读取测试文件
            $excel->openFile($path['basename']);
            
            // 支持按工作表名称或索引打开工作表
            if (is_string($sheet) && $sheet !== '0') {
                // 按工作表名称打开
                $excel->openSheet($sheet);
            } elseif (is_numeric($sheet) && $sheet > 0) {
                // 按工作表索引打开（从0开始）
                $excel->openSheet(null, \Vtiful\Kernel\Excel::SKIP_NONE);
                // Vtiful 不直接支持索引，需要通过名称
                // 如果需要支持索引，需要先获取所有工作表名称
            } else {
                // 默认打开第一个工作表
                $excel->openSheet();
            }
            
            $row = $excel->nextRow();
			if(empty($row)) {
				throw new \Exception('文件没有数据!');
			}
            $type = array_pad([], count($row), \Vtiful\Kernel\Excel::TYPE_STRING);
            foreach($this->column_date_cnt as $k => $v) {
                $type[$v-1] = \Vtiful\Kernel\Excel::TYPE_TIMESTAMP;
            }
            $excel->setType($type);
			$this->data = array(); 
			$this->data[] = $row;
			while ($length--) { 
				$row = $excel->nextRow();
				if($row === NULL) {
					break;
				}
				if(empty($row)) {
					continue;
				}
				foreach($this->column_date_cnt as $k => $v) {
					if($row[$v-1] && is_int($row[$v-1])) $row[$v-1] = date('Y-m-d H:i:s', $row[$v-1]);
				}
				$this->data[]=$row;
				$this->writeData(false,$compatible_csv);
			} 

	        $this->writeData(true,$compatible_csv);
	        return $this->data;
	    } catch (\Exception $e) {
	        throw $e;
	    }
	}


    /**
     * writeData
     * @param mixed $is_force is_force
     * @param mixed $compatible_csv compatible_csv
     * @return mixed 返回值
     */
    public function writeData($is_force = false, $compatible_csv = false )
	{

		if(!$this->is_write_file) return;

		if(!$this->file_prefix) $this->file_prefix = md5(time());

		$file_name = sprintf("%s_%d.json",$this->file_prefix,$this->file_id);
		$local_file = $this->local_path.$file_name;

		if( $is_force || $this->page_size <= count($this->data) )
		{

			if($compatible_csv)
			{
				$this->data = array_values($this->data);
			    foreach ($this->data as $k=>$v) $this->data[$k] = array_values($v);
			}

        	if(file_put_contents($local_file, json_encode($this->data,JSON_UNESCAPED_UNICODE)))
        	{
        		$this->file_data[] = $local_file;
        	}
        	$this->data = array();
			$this->file_id++; 
		}
	}

    /**
     * 设置PageSize
     * @param mixed $num num
     * @return mixed 返回操作结果
     */
    public function setPageSize($num)
	{
		$this->page_size = $num;
	}

    /**
     * 设置LocalPath
     * @param mixed $path path
     * @return mixed 返回操作结果
     */
    public function setLocalPath($path)
	{
		$this->local_path = $path;
	}

    /**
     * 设置FilePrefix
     * @param mixed $file_prefix file_prefix
     * @return mixed 返回操作结果
     */
    public function setFilePrefix($file_prefix)
	{
		$this->file_prefix = $file_prefix;
	}

    /**
     * 设置WriteFile
     * @param mixed $is_write_file is_write_file
     * @return mixed 返回操作结果
     */
    public function setWriteFile($is_write_file)
	{
		$this->is_write_file = $is_write_file;
	}

	public function setCellDateCnt($column_date_cnt=array(),$time_diff=0)
	{
		$this->column_date_cnt = $column_date_cnt;
		$this->date_time_diff = $time_diff;
	}

}