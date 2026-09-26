<?php
class wms_mdl_basic_material extends material_mdl_basic_material
{
    //是否有导出配置
    var $has_export_cnf = false;
    
    //导出的文件名
    var $export_name = '库存总览';
    
    public function table_name($real = false)
    {
        if ($real) {
            $table_name = 'sdb_material_basic_material';
        } else {
            $table_name = 'basic_material';
        }
        return $table_name;
    }

    public function get_schema()
    {
        return app::get('material')->model('basic_material')->get_schema();
    }

    /**
     * 列表
     */
    public function getlist($cols = '*', $filter = array(), $offset = 0, $limit = -1, $orderby = null)
    {
        $strWhere = array(1);
        if (isset($filter['branch_id']) && $filter['branch_id']) {
            if (is_array($filter['branch_id'])) {
                $strWhere[] = ' bp.branch_id IN (' . implode(',', $filter['branch_id']) . ') ';
            } else {
                $strWhere[] = ' bp.branch_id = ' . $filter['branch_id'];
            }
        }

        $orderType = $orderby ? $orderby : $this->defaultOrder;

        $sql = 'SELECT a.*, b.retail_price, b.cost, b.weight, b.unit, sum(bp.store) as store
                FROM ' . DB_PREFIX . 'ome_branch_product AS bp
                LEFT JOIN ' . DB_PREFIX . 'material_basic_material AS a ON bp.product_id=a.bm_id
                LEFT JOIN ' . DB_PREFIX . 'material_basic_material_ext AS b ON b.bm_id=a.bm_id ';

        #保质期物料
        if (isset($filter['use_expire']) && $filter['use_expire']) {
            $strWhere[] = " c.use_expire=" . $filter['use_expire'];

            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'material_basic_material_conf AS c ON c.bm_id=a.bm_id ';
            unset($filter['use_expire']);
        }

        $sql .= 'WHERE  ' . implode(' AND ', $strWhere) . $this->_filter($filter, 'a');
        $sql .= ' GROUP BY bp.product_id';

        $data = $this->db->selectLimit($sql, $limit, $offset);
        if (!kernel::single('desktop_user')->has_permission('cost_price')) {
            foreach ($data as $key => $val) {
                $data[$key]['cost'] = '-';
            }
        }
        return $data;
    }

    /**
     * 统计
     */
    public function countlist($filter = null)
    {
        $orderby  = false;
        $strWhere = array(1);
        if (isset($filter['branch_id']) && $filter['branch_id']) {
            if (is_array($filter['branch_id'])) {
                $strWhere[] = ' bp.branch_id IN (' . implode(',', $filter['branch_id']) . ') ';
            } else {
                $strWhere[] = ' bp.branch_id = ' . $filter['branch_id'];
            }
        }

        $sql = 'SELECT count(bp.product_id)
                FROM ' . DB_PREFIX . 'ome_branch_product AS bp
                LEFT JOIN ' . DB_PREFIX . 'material_basic_material AS a ON bp.product_id=a.bm_id ';

        #保质期物料
        if (isset($filter['use_expire']) && $filter['use_expire']) {
            $strWhere[] = " c.use_expire=" . $filter['use_expire'];

            $sql .= ' LEFT JOIN ' . DB_PREFIX . 'material_basic_material_conf AS c ON c.bm_id=a.bm_id ';
            unset($filter['use_expire']);
        }

        $sql .= ' WHERE  ' . implode(' AND ', $strWhere) . $this->_filter($filter, 'a');
        $sql .= " GROUP BY bp.product_id";

        $row = $this->db->select($sql);
        return intval(count($row));
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = '';
        if (isset($filter['visibled']) && $filter['visibled'] == '0') {
            unset($filter['visibled']);
        }

        return $where . " AND " . parent::_filter($filter, $tableAlias, $baseWhere);
    }

    /**
     * 库存导出
     */
    public function fgetlist_csv(&$data, $filter, $offset, $exportType = 1)
    {
        if ($offset == 0) {
            $title = array();

            foreach ($this->io_title('products') as $k => $v) {
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['products'] = '"' . implode('","', $title) . '"';
        }

        $productObj       = kernel::single('wms_receipt_material');
        $basicMBarcodeLib = kernel::single('material_basic_material_barcode');

        $limit = 100;

        if (!$list = $this->getlist('*', $filter, $offset * $limit, $limit)) {
            return false;
        }

        $list = material_basic_material_master::fillListRows($list);
        $masterCols = material_basic_material_master::extraCols();

        $bm_id     = array_column($list, 'bm_id');
        $branch_id = kernel::single('wms_branch')->getBranchwmsByUser(kernel::single('desktop_user')->is_super());

        $storeList = kernel::database()->select("SELECT product_id, SUM(store) store, SUM(arrive_store) arrive_store, SUM(store_freeze) store_freeze FROM sdb_ome_branch_product WHERE product_id IN(".implode(',', $bm_id).") AND branch_id IN (".implode(',',$branch_id).") GROUP BY product_id");
        $storeList = array_column($storeList, null, 'product_id');

        $barcodeList = app::get('material')->model('barcode')->getList('code,bm_id',['bm_id' => $bm_id]);
        $barcodeList = array_column($barcodeList, null, 'bm_id');

        foreach ($list as $aFilter) {
            $pRow  = array();
            $store = $storeList[$aFilter['bm_id']]['store'];

            //根据bm_id统计自有仓库冻结库存
            $store_freeze = $storeList[$aFilter['bm_id']]['store_freeze'];

            $arrive_store = $storeList[$aFilter['bm_id']]['arrive_store'];

            $barcode      = $barcodeList[$aFilter['bm_id']]['code'];

            $detail['bn']           = "\t" . $this->charset->utf2local($aFilter['material_bn']);
            $detail['barcode']      = "\t" . $this->charset->utf2local($barcode);
            $detail['name']         = $this->charset->utf2local($aFilter['material_name']);
            $detail['store']        = $store;
            $detail['store_freeze'] = $store_freeze;
            $detail['arrive_store'] = $arrive_store;
            foreach ($masterCols as $colKey => $colDef) {
                $detail[$colKey] = isset($aFilter[$colKey]) ? $this->charset->utf2local($aFilter[$colKey]) : '';
            }
            foreach ($this->oSchema['csv']['products'] as $k => $v) {

                $pRow[$k] = utils::apath($detail, explode('/', $v));
            }
            $data['contents']['products'][] = implode(',', $pRow);
        }

        return true;
    }

    public function export_csv($data, $exportType = 1)
    {

        $output   = array();

        $content = '';

        if ($data['title']['products']) {
            $content = $data['title']['products'] . "\n";
        }

        $output[] = $content . implode("\n", (array) $data['contents']['products']);

        $fp = fopen('php://output', 'a');

        fwrite($fp, implode("\n", $output));
    }

    public function io_title($filter, $ioType = 'csv')
    {

        switch ($filter) {
            case 'products':
                $this->oSchema['csv'][$filter] = array(

                    '*:货号'   => 'bn',
                    '*:条形码'  => 'barcode',
                    '*:货品名称' => 'name',
                    '*:库存'   => 'store',
                    '*:冻结库存' => 'store_freeze',
                    '*:在途库存' => 'arrive_store',
                );
                foreach (material_basic_material_master::extraCols() as $colKey => $colDef) {
                    $this->oSchema['csv'][$filter]['*:' . $colDef['label']] = $colKey;
                }
                break;
        }
        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType][$filter]);
        return $this->ioTitle[$ioType][$filter];
    }
}
