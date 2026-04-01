<?php
/**
 * 会计科目模型类
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_mdl_account_chart extends dbeav_model
{

    public function isExist($filter,$id = 0)
    {
        $sql = "SELECT id FROM ".$this->table_name(true)." WHERE ".$this->filter($filter);
        $id and $sql.=" and id <> ".$id;
        return $this->db->selectrow($sql) ? true : false;
    }

    public function modifier_account_category($col) {
        switch($col) {
            case 'platform_amount':
                return '平台承担';
            case 'actually_amount':
                return '客户实付';
            case 'refundplatform_amount':
                return '平台补贴退';
            case 'refundactually_amount':
                return '客户应退';
            default:
                return '';
        }
    }
}
