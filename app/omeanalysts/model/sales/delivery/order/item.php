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
class omeanalysts_mdl_sales_delivery_order_item extends sales_mdl_delivery_order_item
{

    public $has_export_cnf = false;

    public $export_name = '发货销售统计';

    public $mark_type = array('b0' => '灰色', 'b1' => '红色', 'b2' => '橙色', 'b3' => '黄色', 'b4' => '蓝色', 'b5' => '紫色', 'b6' => '粉红色', 'b7' => '绿色', '' => '-');

    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
    );

    public function __construct($app)
    {
        return parent::__construct(app::get('ome'));
    }

    public function table_name($real = false)
    {
        if ($real) {
            $table_name = kernel::database()->prefix . 'sales_delivery_order_item';
        } else {
            $table_name = "sales_delivery_order_item";
        }

        return $table_name;
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {

        $where = '';

        // if (isset($filter['_order_create_time_search']) || isset($filter['_paytime_search']) || isset($filter['_ship_time_search'])) {
        //     unset($filter['time_from'], $filter['time_to']);
        // }

        if (isset($filter['order_id']) && $filter['order_id']) {
            $orderObj  = $this->app->model("orders");
            $rows      = $orderObj->getList('order_id', array('order_bn' => $filter['order_id']));
            $orderId[] = -1;
            foreach ($rows as $row) {
                $orderId[] = $row['order_id'];
            }
            $where .= '  AND order_id IN (' . implode(',', $orderId) . ')';

            unset($filter['order_id']);
        }

        if (isset($filter['own_branches']) && $filter['own_branches']) {
            $where .= '  AND branch_id in (' . implode(',', $filter['own_branches']) . ')';
        }
        unset($filter['own_branches']);

        if (isset($filter['branch_id']) && $filter['branch_id']) {
            $where .= '  AND branch_id = \'' . addslashes($filter['branch_id']) . '\'';
        }
        unset($filter['branch_id']);

        if (isset($filter['shop_id']) && $filter['shop_id']) {
            if (!is_array($filter['shop_id'])) {
                $where .= '  AND shop_id = \''.addslashes($filter['shop_id']).'\'';
            } else {      
                if (count($filter['shop_id']) == 1) {
                    $where .= '  AND shop_id = \''.addslashes($filter['shop_id'][0]).'\'';
                } else {
                    $where .= '  AND shop_id IN (\''.implode("','", $filter['shop_id']).'\')';
                }
            }
        }
        unset($filter['shop_id']);

        if (isset($filter['shop_type']) && $filter['shop_type']) {
            $shopList = kernel::single('omeanalysts_shop')->getShopList();
            $shop_ids = $shopList[$filter['shop_type']];

            if ($shop_ids) {
                $where .= " AND shop_id in ('" . implode('\',\'', $shop_ids) . "')";
            }

        }
        unset($filter['shop_type']);

        if (isset($filter['org_id']) && $filter['org_id']) {
            $where .= " AND org_id in ('" . implode('\',\'', $filter['org_id']) . "')";
        }
        unset($filter['org_id']);

        if (isset($filter['time_from']) && $filter['time_from']) {
            $where .= " AND delivery_time>='" . strtotime($filter['time_from']) . "'";
        }
        unset($filter['time_from']);

        if (isset($filter['time_to']) && $filter['time_to']) {
            $where .= " AND delivery_time<='" . strtotime($filter['time_to'] . '23:59:59') . "'";
        }
        unset($filter['time_to']);

        $out_filter = parent::_filter($filter, $tableAlias, $baseWhere) . $where;

        // $out_filter = str_replace('`sdb_ome_sales`', 'S', $out_filter);

        return $out_filter;
    }

    public function get_sales($filter = null)
    {
        $cols = 'count(distinct order_id) as order_counts,sum(nums) as product_nums,sum(sales_amount) as sale_amounts';

        $sql  = 'SELECT ' . $cols . ' FROM sdb_sales_delivery_order_item WHERE ' . $this->_filter($filter);
        $rows = $this->db->selectrow($sql);

        return $rows;
    }

    public function count($filter = null)
    {
        $sql = "SELECT count(id) as _count FROM sdb_sales_delivery_order_item WHERE " . $this->_filter($filter);

        $rows = $this->db->selectrow($sql);

        return intval($rows['_count']);
    }

    public function getList($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderType = null)
    {

        $sql  = "SELECT {$cols} FROM " . $this->table_name(true) . " WHERE " . $this->_filter($filter) . "";
        $rows = $this->db->selectLimit($sql, $limit, $offset);

        foreach ($rows as $key => $row) {
            // 数据解密
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($row[$field])) {
                    $rows[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($row[$field], $type);
                }
            }
        }
        return $rows;

        if (isset($filter['_gross_sales_search']) && ($filter['_gross_sales_search'])) {
            $tmp_gross_sales['_gross_sales_search'] = $filter['_gross_sales_search'];
            $tmp_gross_sales['gross_sales']         = $filter['gross_sales'];
            $tmp_gross_sales['gross_sales_from']    = $filter['gross_sales_from'];
            $tmp_gross_sales['gross_sales_to']      = $filter['gross_sales_to'];
            unset($filter['_gross_sales_search'], $filter['gross_sales'], $filter['gross_sales_from'], $filter['gross_sales_to']);
        }

        if (isset($filter['_gross_sales_rate_search']) && ($filter['_gross_sales_rate_search'])) {
            $tmp_gross_sales_rate['_gross_sales_rate_search'] = $filter['_gross_sales_rate_search'];
            $tmp_gross_sales_rate['gross_sales_rate']         = $filter['gross_sales_rate'];
            $tmp_gross_sales_rate['gross_sales_rate_from']    = $filter['gross_sales_rate_from'];
            $tmp_gross_sales_rate['gross_sales_rate_to']      = $filter['gross_sales_rate_to'];
            unset($filter['_gross_sales_rate_search'], $filter['gross_sales_rate'], $filter['gross_sales_rate_from'], $filter['gross_sales_rate_to']);
        }

        $oItem = kernel::single("ome_mdl_sales_items");
        $cols  = 'D.logi_no,D.ship_area,S.sale_id,S.order_id,S.sale_bn,S.discount,S.total_amount,S.cost_freight,S.sale_amount,S.branch_id,S.delivery_cost_actual,S.member_id,S.additional_costs,S.payment,S.delivery_id,S.order_create_time,S.paytime,S.ship_time,S.shop_id';
        $sql   = 'SELECT ' . $cols . ' FROM sdb_ome_sales S left join sdb_ome_delivery D on S.delivery_id = D.delivery_id WHERE ' . $this->_filter($filter);

        $_SESSION['filter'] = $filter;

        $rows = $this->db->selectLimit($sql, $limit, $offset);

        foreach ($rows as $key => $row) {

            // 数据解密
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($row[$field])) {
                    $rows[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($row[$field], $type);
                }
            }

            $ship_area   = explode(':', $rows[$key]['ship_area']);
            $total_items = $oItem->getList('nums,cost,cost_amount,sales_amount', array('sale_id' => $rows[$key]['sale_id']));

            $total_product_nums = $goods_sales_prices = $cost_amounts = 0;

            foreach ($total_items as $v) {
                $total_product_nums += $v['nums'];
                $goods_sales_prices += $v['sales_amount'];
                $cost_amounts += $v['cost_amount'];
            }

            $total_products_types = count($total_items);

            $rows[$key]['product_nums']      = $total_product_nums;
            $rows[$key]['products_type']     = $total_products_types;
            $rows[$key]['goods_sales_price'] = $goods_sales_prices;
            $rows[$key]['cost_amount']       = $cost_amounts;
            $rows[$key]['shop_type']         = kernel::single('omeanalysts_shop')->getShopDetail($row['shop_id']);
            $rows[$key]['ship_area']         = $ship_area[1];

            $cost_amount          = $rows[$key]['cost_amount'] ? $rows[$key]['cost_amount'] : 0;
            $sale_amount          = $rows[$key]['sale_amount'] ? $rows[$key]['sale_amount'] : 0;
            $delivery_cost_actual = $rows[$key]['delivery_cost_actual'] ? $rows[$key]['delivery_cost_actual'] : 0;
            //毛利 gross_sales
            $gross_sales               = $sale_amount - $cost_amount - $delivery_cost_actual; //毛利
            $rows[$key]['gross_sales'] = round($gross_sales, 3);

            //毛利率 gross_sales_rate
            $gross_sales_rate               = ($sale_amount && $sale_amount != 0) ? (round($gross_sales / $sale_amount, 2) * 100) : 0;
            $rows[$key]['gross_sales_rate'] = $gross_sales_rate . "%";

            if (isset($tmp_gross_sales)) {
                if (!$this->money_filter('gross_sales', $tmp_gross_sales, $rows[$key]['gross_sales'])) {
                    unset($rows[$key]);
                }
            }

            if (isset($tmp_gross_sales_rate)) {
                if (!$this->money_filter('gross_sales_rate', $tmp_gross_sales_rate, $gross_sales_rate)) {
                    unset($rows[$key]);
                }
            }

            unset($total_product_nums, $goods_sales_prices, $cost_amounts);

        }

        return $rows;
    }


    public function money_filter($key, $filter, $target)
    {
        switch ($filter['_' . $key . '_search']) {
            case 'than':
                if (isset($filter[$key]) && ($filter[$key])) {
                    $_where = ($target > $filter[$key]) ? true : false;
                }
                break;
            case 'lthan':
                if (isset($filter[$key]) && ($filter[$key])) {
                    $_where = ($target < $filter[$key]) ? true : false;
                }
                break;
            case 'nequal':
                if (isset($filter[$key]) && ($filter[$key])) {
                    $_where = ($target == $filter[$key]) ? true : false;
                }
                break;
            case 'sthan':
                if (isset($filter[$key]) && ($filter[$key])) {
                    $_where = ($target <= $filter[$key]) ? true : false;
                }
                break;
            case 'bthan':
                if (isset($filter[$key]) && ($filter[$key])) {
                    $_where = ($target >= $filter[$key]) ? true : false;
                }
                break;
            case 'between':
                if (isset($filter[$key . '_from']) && ($filter[$key . '_from']) && isset($filter[$key . '_to']) && ($filter[$key . '_to'])) {
                    $_where = (($target >= $filter[$key]) && ($target < $filter[$key])) ? true : false;
                }
                break;
        }

        return $_where;
    }

    public function exportName(&$data)
    {
        $data['name'] = $_POST['time_from'] . '到' . $_POST['time_to'] . '发货销售统计';
    }

    public function fgetlist_csv(&$data, $filter, $offset, $exportType = 1)
    {

        if (isset($_SESSION['filter']) && $_SESSION['filter']) {
            $filter = array_merge($filter, $_SESSION['filter']);
        }

        $limit = 100;

        $productssale = $this->getList('*', $filter, $offset * $limit, $limit);

        if (!$productssale) {
            return false;
        }

        $export_fields = 'column_sale_bn,column_org_order_bn,column_order_type,column_order_create_time,column_sales_material_name,column_goods_type_name,column_color,column_size,column_discount,column_freight,column_pmt_describe,column_delivery_time,column_reship_signfor_time,column_order_pay_time,column_payment,column_refund_sent_time,column_predict_delivery_time,column_email,column_member_name,column_mobile,column_province,column_city,column_district,column_addr,column_uname';

        $finderObj = kernel::single('omeanalysts_finder_ome_delivery_order_item');

        foreach ($productssale as $key=>$aFilter) {
            foreach (explode(',', $export_fields) as $v) {
                if ('column_' == substr($v, 0, 7) && method_exists($finderObj, $v)) {
                    $cv = $finderObj->{$v}($aFilter, $productssale);
                    $productssale[$key][substr($v,7)] = $cv;
                }
            }
        }

        @ini_set('memory_limit', '1024M');
        if (!$data['title']) {
            $title = array();
            foreach ($this->io_title() as $k => $v) {
                $title[] = $v;
            }

            $data['title']['omeanalysts_sales'] = mb_convert_encoding('"' . implode('","', $title) . '"', 'GBK', 'UTF-8');
        }

        foreach ($productssale as $k => $aFilter) {

            $productRow['*:仓库名称'] = $aFilter['branch_id'];
            $productRow['*:销售单号'] = $aFilter['sale_bn'];
            $productRow['*:订单号'] = $aFilter['order_id'];
            $productRow['*:原订单号'] = $aFilter['org_order_bn'];
            $productRow['*:订单类型'] = $aFilter['order_type'];
            $productRow['*:订单日期'] = $aFilter['order_create_time'];
            $productRow['*:销售物料编码'] = $aFilter['sales_material_bn'];
            $productRow['*:销售物料名称'] = $aFilter['sales_material_name'];
            $productRow['*:分类组'] = $aFilter['goods_type_name'];
            $productRow['*:颜色'] = $aFilter['color'];
            $productRow['*:尺码'] = $aFilter['size'];
            $productRow['*:销售数量'] = $aFilter['nums'];
            $productRow['*:销售价'] = $aFilter['sale_price'];
            $productRow['*:吊牌价'] = $aFilter['price'];
            $productRow['*:销售折扣'] = $aFilter['discount'];
            $productRow['*:销售金额'] = $aFilter['sales_amount'];
            $productRow['*:运费'] = $aFilter['freight'];
            $productRow['*:订单应收款（不包括邮费）'] = $aFilter['actually_amount'];
            $productRow['*:优惠金额'] = $aFilter['apportion_pmt'];
            $productRow['*:优惠原因'] = $aFilter['pmt_describe'];
            $productRow['*:发货日期'] = $aFilter['delivery_time'];
            $productRow['*:收到退货日期'] = $aFilter['reship_signfor_time'];
            $productRow['*:收款日期'] = $aFilter['order_pay_time'];
            $productRow['*:支付方式'] = $aFilter['payment'];
            $productRow['*:退款时间'] = $aFilter['refund_sent_time'];
            $productRow['*:预计发货周期'] = $aFilter['predict_delivery_time'];
            $productRow['*:客户邮箱'] = $aFilter['email'];
            $productRow['*:客户姓名'] = $aFilter['member_name'];
            $productRow['*:客户电话'] = $aFilter['mobile'];
            $productRow['*:地区'] = $aFilter['area'];
            $productRow['*:地址'] = $aFilter['addr'];
            $productRow['*:会员ID'] = $aFilter['uname'];
            $productRow['*:来源店铺'] = $aFilter['shop_id'];
            $productRow['*:发货单号'] = $aFilter['delivery_bn'];

            $data['content']['omeanalysts_sales'][] = mb_convert_encoding('"' . implode('","', $productRow) . '"', 'GBK', 'UTF-8');
        }

        return true;

    }

    public function export_csv($data, $exportType = 1)
    {

        $output = array();

        $output[] = $data['title']['omeanalysts_sales'] . "\n" . implode("\n", (array) $data['content']['omeanalysts_sales']);

        echo implode("\n", $output);
    }

    public function io_title($filter = null, $ioType = 'csv')
    {
        switch ($ioType) {
            case 'csv':
            default:
                $this->oSchema['csv']['main'] = array(
                    '*:仓库名称' => 'branch_id',
                    '*:销售单号' => 'sale_bn',
                    '*:订单号' => 'order_id',
                    '*:原订单号' => 'org_order_bn',
                    '*:订单类型' => 'order_type',
                    '*:订单日期' => 'order_create_time',
                    '*:销售物料编码' => 'sales_material_bn',
                    '*:销售物料名称' => 'sales_material_name',
                    '*:分类组' => 'goods_type_name',
                    '*:颜色' => 'color',
                    '*:尺码' => 'size',
                    '*:销售数量' => 'nums',
                    '*:销售价' => 'sale_price',
                    '*:吊牌价' => 'price',
                    '*:销售折扣' => 'discount',
                    '*:销售金额' => 'sales_amount',
                    '*:运费' => 'freight',
                    '*:订单应收款（不包括邮费）' => 'actually_amount',
                    '*:优惠金额' => 'apportion_pmt',
                    '*:优惠原因' => 'pmt_describe',
                    '*:发货日期' => 'delivery_time',
                    '*:收到退货日期' => 'reship_signfor_time',
                    '*:收款日期' => 'order_pay_time',
                    '*:支付方式' => 'payment',
                    '*:退款时间' => 'refund_sent_time',
                    '*:客户邮箱' => 'email',
                    '*:客户姓名' => 'member_name',
                    '*:客户电话' => 'mobile',
                    '*:地区' => 'area',
                    '*:地址' => 'addr',
                    '*:预计发货周期' => 'predict_delivery_time',
                    '*:会员ID' => 'uname',
                    '*:来源店铺' => 'shop_id',
                    '*:发货单号' => 'delivery_bn',
                );
        }
        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType]['main']);
        return $this->ioTitle[$ioType][$filter];
    }

    public function get_schema()
    {
        $schema = array(
            'columns'         => array(
                'id'                   => array(
                    'type'     => 'int unsigned',
                    'required' => true,
                    'pkey'     => true,
                ),
                'branch_id'            => array(
                    'type'            => 'table:branch@ome',
                    'label'           => '仓库名称',
                    'order'           => '1',
                    'editable'        => false,
                    'orderby'         => true,
                    'width'           => 85,
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                // 'sale_bn'              => array(
                //     'type'            => 'varchar(32)',
                //     'label'           => '销售单号',
                //     'order'           => '3',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'searchtype'      => 'has',
                //     'filtertype'      => 'normal',
                //     'filterdefault'   => true,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                'order_id'             => array(
                    'type'            => 'table:orders@ome',
                    'label'           => '订单号',
                    'order'           => '5',
                    'editable'        => false,
                    'orderby'         => true,
                    'searchtype'      => 'has',
                    'width'           => '160',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                // 'org_order_bn'         => array(
                //     'type'            => 'varchar(32)',
                //     'label'           => '原订单号',
                //     'order'           => '7',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'searchtype'      => 'has',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'order_type'=>array(
                //     'type' => 'varchar(32)',
                //     'label' => '订单类型',
                //     'order' =>'8',
                //     'editable' => false,
                //     'orderby' =>true,
                //     'in_list' => true,
                //     'default_in_list' => true,
                //     'width' => '70',
                // ),
                // 'order_create_time'    => array(
                //     'type'            => 'time',
                //     'label'           => '订单日期',
                //     'order'           => '9',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'filtertype'      => 'time',
                //     'filterdefault'   => true,
                //     'width'           => 130,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                'sales_material_bn'    => array(
                    'type'            => 'varchar(200)',
                    'label'           => '销售物料编码',
                    'order'           => '11',
                    'editable'        => false,
                    'orderby'         => true,
                    'width'           => 100,
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                // 'sales_material_name'  => array(
                //     'type'            => 'varchar(200)',
                //     'label'           => '销售物料名称',
                //     'order'           => '13',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'is_title'        => true,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                //     'width'           => 260,
                //     'searchtype'      => 'has',
                //     'filtertype'      => 'normal',
                //     'filterdefault'   => true,
                // ),
                // 'goods_type_name'      => array(
                //     'type'            => 'varchar(100)',
                //     'label'           => '分类组', //'商品类型'
                //     'order'           => '15',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'default'         => '',
                //     'is_title'        => true,
                //     'width'           => 150,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                //     'filterdefault'   => true,
                //     'searchtype'      => 'has',
                //     'filtertype'      => 'normal',
                // ),
                // 'color'                => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '颜色',
                //     'order'           => '17',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 100,
                //     'editable'        => false,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'size'                 => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '尺码',
                //     'order'           => '19',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 100,
                //     'editable'        => false,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                'nums'                 => array(
                    'type'            => 'mediumint',
                    'label'           => '销售数量',
                    'order'           => '21',
                    'editable'        => false,
                    'orderby'         => true,
                    'width'           => '70',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                'sale_price'           => array(
                    'type'            => 'money',
                    'label'           => '销售价',
                    'order'           => '23',
                    'editable'        => false,
                    'orderby'         => true,
                    'default'         => '0',
                    'width'           => '70',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                'price'                => array(
                    'type'            => 'money',
                    'label'           => '吊牌价',
                    'order'           => '25',
                    'editable'        => false,
                    'orderby'         => true,
                    'default'         => 0,
                    'width'           => '70',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                // 'discount'             => array(
                //     'type'            => 'money',
                //     'label'           => '销售折扣',
                //     'order'           => '27',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'default'         => '1',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                //     'width'           => 60,
                //     'comment'         => '销售折扣',
                // ),
                'sales_amount'         => array(
                    'type'            => 'money',
                    'label'           => '销售金额',
                    'order'           => '29',
                    'editable'        => false,
                    'orderby'         => true,
                    'default'         => '0',
                    'filtertype'      => 'number',
                    'filterdefault'   => true,
                    'width'           => '80',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                // 'freight'              => array(
                //     'type'            => 'money',
                //     'label'           => '运费',
                //     'order'           => '31',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'default'         => 0,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                'actually_amount'      => array(
                    'type'            => 'money',
                    'label'           => '订单应收款（不包括邮费）', // 已支付金额 减去平台支付优惠，加平台支付总额
                    'order'           => '33',
                    'editable'        => false,
                    'orderby'         => true,
                    'default'         => '0',
                    'width'           => '170',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                'apportion_pmt'        => array(
                    'type'            => 'money',
                    'label'           => '优惠金额',
                    'order'           => '35',
                    'editable'        => false,
                    'orderby'         => true,
                    'default'         => '0',
                    'width'           => '80',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                // 'pmt_describe'         => array(
                //     'type'            => 'longtext',
                //     'label'           => '优惠原因',
                //     'order'           => '37',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'delivery_time'        => array(
                //     'type'            => 'time',
                //     'label'           => '发货日期',
                //     'order'           => '39',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'comment'         => '发货日期',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                //     'filtertype'      => 'time',
                //     'filterdefault'   => true,
                // ),
                // 'reship_signfor_time'  => array(
                //     'type'            => 'time',
                //     'label'           => '收到退货日期',
                //     'order'           => '41',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'comment'         => '收到退货日期',
                //     'filtertype'      => 'time',
                //     'filterdefault'   => true,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'order_pay_time'       => array(
                //     'type'            => 'time',
                //     'label'           => '收款日期',
                //     'order'           => '43',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 130,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                //     'filtertype'      => 'time',
                //     'filterdefault'   => true,
                // ),
                // 'payment'              => array(
                //     'type'            => 'varchar(100)',
                //     'label'           => '支付方式',
                //     'order'           => '45',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 65,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => false,
                // ),
                // 'refund_sent_time'     => array(
                //     'type'            => 'time',
                //     'label'           => '退款时间',
                //     'order'           => '47',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 130,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'predict_delivery_time' => array(
                //     'type'            => 'time',
                //     'label'           => '用户确认收款时间',
                //     'order'           => '49',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 130,
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'email'                => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '客户邮箱',
                //     'order'           => '51',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 110,
                //     'sdfpath'         => 'contact/email',
                //     'searchtype'      => 'has',
                //     'filtertype'      => 'normal',
                //     'filterdefault'   => 'true',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'member_name'                 => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '客户姓名',
                //     'order'           => '53',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 160,
                //     'sdfpath'         => 'contact/name',
                //     'filtertype'      => 'normal',
                //     'filterdefault'   => 'true',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'mobile'               => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '客户电话',
                //     'order'           => '55',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 175,
                //     'sdfpath'         => 'contact/phone/mobile',
                //     'filtertype'      => 'normal',
                //     'filterdefault'   => 'true',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'area'                 => array(
                //     'type'            => 'region',
                //     'label'           => '地区',
                //     'order'           => '57',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'width'           => 160,
                //     'sdfpath'         => 'contact/area',
                //     'filtertype'      => 'yes',
                //     'filterdefault'   => 'true',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'addr'                 => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '地址',
                //     'order'           => '59',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'sdfpath'         => 'contact/addr',
                //     'width'           => 200,
                //     'filtertype'      => 'normal',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                // 'uname'                => array(
                //     'type'            => 'varchar(255)',
                //     'label'           => '会员ID',
                //     'order'           => '61',
                //     'editable'        => false,
                //     'orderby'         => true,
                //     'is_title'        => true,
                //     'width'           => 160,
                //     'searchtype'      => 'head',
                //     'filtertype'      => 'normal',
                //     'filterdefault'   => 'true',
                //     // 'in_list'         => true,
                //     // 'default_in_list' => true,
                // ),
                'shop_id'              => array(
                    'type'            => 'table:shop@ome',
                    'label'           => '来源店铺',
                    'order'           => '63',
                    'editable'        => false,
                    'orderby'         => true,
                    'width'           => 100,
                    'filtertype'      => 'normal',
                    'filterdefault'   => true,
                    'width'           => '150',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
                'delivery_bn'          => array(
                    'type'            => 'varchar(32)',
                    'label'           => '发货单号',
                    'order'           => '65',
                    'editable'        => false,
                    'orderby'         => true,
                    'comment'         => '发货单号',
                    'width'           => 140,
                    'searchtype'      => 'nequal',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                    'width'           => '110',
                    // 'in_list'         => true,
                    // 'default_in_list' => true,
                ),
            ),
            'idColumn'        => 'id',
            'in_list'         => array(
                'branch_id',
                'order_id',
                'sales_material_bn',
                'nums',
                'sale_price',
                'price',
                'sales_amount',
                'actually_amount',
                'apportion_pmt',
                'shop_id',
                'delivery_bn',
            ),
            'default_in_list' => array(
                'branch_id',
                'order_id',
                'sales_material_bn',
                'nums',
                'sale_price',
                'price',
                'sales_amount',
                'actually_amount',
                'apportion_pmt',
                'shop_id',
                'delivery_bn',
            ),
        );

        return $schema;
    }

    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams)
    {
        $type    = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        } elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }
    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams)
    {
        $params = $logParams['params'];
        $type   = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_orderSales';
        }
        $type .= '_export';
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams)
    {
        $params = $logParams['params'];
        $type   = 'report';
        if ($logParams['app'] == 'omeanalysts' && $logParams['ctl'] == 'ome_analysis') {
            $type .= '_salesReport_orderSales';
        }
        $type .= '_import';
        return $type;
    }

    //根据查询条件获取导出数据
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
    {

        //根据选择的字段定义导出的第一行标题
        if ($curr_sheet == 1) {
            $data['content']['main'][] = $this->getExportTitle($fields);
        }

        $productssale = $this->getList('*', $filter, $start, $end);
        if (!$productssale) {
            return false;
        }

        $export_fields = 'column_sale_bn,column_org_order_bn,column_order_type,column_order_create_time,column_sales_material_name,column_goods_type_name,column_color,column_size,column_discount,column_freight,column_pmt_describe,column_delivery_time,column_reship_signfor_time,column_order_pay_time,column_payment,column_refund_sent_time,column_predict_delivery_time,column_email,column_member_name,column_mobile,column_province,column_city,column_district,column_addr,column_uname';

        $finderObj = kernel::single('omeanalysts_finder_ome_delivery_order_item');

        foreach ($productssale as $key=>$aFilter) {
            foreach (explode(',', $export_fields) as $v) {
                if ('column_' == substr($v, 0, 7) && method_exists($finderObj, $v)) {
                    $cv = $finderObj->{$v}($aFilter, $productssale);
                    $productssale[$key][substr($v,7)] = $cv;
                }
            }
        }

        //商品销售额  商品成本
        foreach ($productssale as $k => $aFilter) {

            $productRow['branch_id'] = $aFilter['branch_id'];
            $productRow['sale_bn'] = $aFilter['sale_bn'];
            $productRow['order_id'] = $aFilter['order_id'];
            $productRow['org_order_bn'] = $aFilter['org_order_bn'];
            $productRow['order_type'] = $aFilter['order_type'];
            $productRow['order_create_time'] = $aFilter['order_create_time'];
            $productRow['sales_material_bn'] = $aFilter['sales_material_bn'];
            $productRow['sales_material_name'] = $aFilter['sales_material_name'];
            $productRow['goods_type_name'] = $aFilter['goods_type_name'];
            $productRow['color'] = $aFilter['color'];
            $productRow['size'] = $aFilter['size'];
            $productRow['nums'] = $aFilter['nums'];
            $productRow['sale_price'] = $aFilter['sale_price'];
            $productRow['price'] = $aFilter['price'];
            $productRow['discount'] = $aFilter['discount'];
            $productRow['sales_amount'] = $aFilter['sales_amount'];
            $productRow['freight'] = $aFilter['freight'];
            $productRow['actually_amount'] = $aFilter['actually_amount'];
            $productRow['apportion_pmt'] = $aFilter['apportion_pmt'];
            $productRow['pmt_describe'] = $aFilter['pmt_describe'];
            $productRow['delivery_time'] = $aFilter['delivery_time'];
            $productRow['reship_signfor_time'] = $aFilter['reship_signfor_time'];
            $productRow['order_pay_time'] = $aFilter['order_pay_time'];
            $productRow['payment'] = $aFilter['payment'];
            $productRow['refund_sent_time'] = $aFilter['refund_sent_time'];
            $productRow['predict_delivery_time'] = $aFilter['predict_delivery_time'];
            $productRow['email'] = $aFilter['email'];
            $productRow['member_name'] = $aFilter['member_name'];
            $productRow['mobile'] = $aFilter['mobile'];
            $productRow['area'] = $aFilter['area'];
            $productRow['addr'] = $aFilter['addr'];
            $productRow['uname'] = $aFilter['uname'];
            $productRow['shop_id'] = $aFilter['shop_id'];
            $productRow['delivery_bn'] = $aFilter['delivery_bn'];

            $exptmp_data = array();
            foreach (explode(',', $fields) as $key => $col) {
                if (isset($productRow[$col])) {
                    $productRow[$col] = mb_convert_encoding($productRow[$col], 'GBK', 'UTF-8');
                    $exptmp_data[]    = $productRow[$col];
                } else {
                    $exptmp_data[] = '';
                }
            }

            $data['content']['main'][] = implode(',', $exptmp_data);
        }

        return $data;
    }

    public function modifier_shop_id($shop_id, $list, $row)
    {
        static $shopList;

        if (isset($shopList)) {
            return $shopList[$shop_id];
        }

        $shopIds  = array_unique(array_column($list, 'shop_id'));
        $shopList = app::get('ome')->model('shop')->getList('shop_id,name', ['shop_id' => $shopIds]);
        $shopList = array_column($shopList, 'name', 'shop_id');

        return $shopList[$shop_id];
    }

}
