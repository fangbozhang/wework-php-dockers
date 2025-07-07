<?php

namespace app\common\model;

use think\Model;

/**
 * 公司配置
 */
class CompanyConfig extends Model {

    protected $name = 'we_company_config';

    /**
     * 获取所有启用的企业
     * @return CompanyConfig[]|array|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getActiveCompanies()
    {
        return self::where('status', 1)->select();
    }


}