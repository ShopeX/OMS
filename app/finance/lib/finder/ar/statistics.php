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

class finance_finder_ar_statistics{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $publicData;
    var $addon_cols = "addon";
    /**
     * __construct
     * @return mixed 返回值
     */
    public function __construct(){
        $this->publicData['ar_statistics_mdl'] = app::get('finance')->model('ar_statistics');
    }
    var $column_fee_money = '运费收入';
    /**
     * column_fee_money
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_fee_money($row){
        $addon = unserialize($row[$this->col_prefix.'addon']);
        return "￥".number_format($addon['fee_money'],2,'.','');
    }

    var $column_items_nums = '商品总数量';
    /**
     * column_items_nums
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_items_nums($row){
        $sql = "SELECT SUM(num) as nums FROM sdb_finance_ar_items WHERE ar_id =".$row['ar_id'];
        $nums = kernel::database()->select($sql);
        return is_null($nums[0]['nums'])? 0 : $nums[0]['nums'];
    }

    var $column_qcys = '期初应收';
    /**
     * column_qcys
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_qcys($row){
        $time_from = strtotime($_POST['time_from'].' 00:00:00');
        $money = $this->publicData['ar_statistics_mdl']->get_qcys($row['ar_id'],$time_from);
        return "￥".number_format($money,2,'.','');
    }

    var $column_bqys = '本期应收';
    /**
     * column_bqys
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_bqys($row){
        $money = $this->publicData['ar_statistics_mdl']->get_bqys($row['ar_id']);
        return "￥".number_format($money,2,'.','');
    }

    var $column_bqss = '本期实收';
    /**
     * column_bqss
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_bqss($row){
        $money = $this->publicData['ar_statistics_mdl']->get_bqss($row['ar_id']);
        return "￥".number_format($money,2,'.','');
    }

    var $column_qmys = '期末应收';
    /**
     * column_qmys
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_qmys($row){
        $money = $this->publicData['ar_statistics_mdl']->get_qmys($row['ar_id']);
        return "￥".number_format($money,2,'.','');
    }

}
?>