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

class financebase_finder_bill_import_order {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }




    var $column_edit = "操作";
    var $column_edit_width = "110";
    var $column_edit_order = 1;

    function column_edit($row)
    {
        $finder_id = $_GET['_finder']['finder_id'];

        $seeBtn = '';

        if ($row['confirm_status'] == 0) {
            $seeBtn .= <<<EOF
            <a onclick="javascript:new Request({
                url:'index.php?app=financebase&ctl=admin_shop_settlement_cainiao&act=doChangeStatus&type=order&id={$row['id']}',
                data:'',
                method:'post',
                onSuccess:function(response){
                    alert(response);
                    finder = finderGroup['{$finder_id}'];
                    finder.refresh.delay(100, finder);
                }
            }).send();" href="javascript:;" >确认</a>
EOF;


            $seeBtn .= <<<EOF
               &nbsp;&nbsp; <a onclick="javascript:if(confirm('数据取消将无法恢复，需要重新导入')) new Request({
                    url:'index.php?app=financebase&ctl=admin_shop_settlement_cainiao&act=doCancel&type=order&id={$row['id']}',
                    data:'',
                    method:'post',
                    onSuccess:function(response){
                        alert(response);
                        finder = finderGroup['{$finder_id}'];
                        finder.refresh.delay(100, finder);
                    }
                }).send();" href="javascript:;" >取消</a>
EOF;
        }
        return $seeBtn;
    }

//	var $column_pay_serial_number = "支付流水号";
//    var $column_pay_serial_number_width = "300";
//    function column_pay_serial_number($row)
//    {
//        return $row['pay_serial_number'];
//    }


//    var $column_name = "确认人";
//    var $column_name_width = "100";
//    function column_name($row)
//    {
//        return $row['name'];
//    }


}

