<?php
namespace Pegaservice\Homework\service;

use Pegaservice\Homework\model\StatusTrackModel;
use Pegaservice\Homework\model\UserModel;

class TrackService{
    // 依赖注入 BaseDb
    static protected BaseDb $baseDb;

    /**
     * 设置 BaseDb
     */
    static public function setBaseDb(BaseDb $baseDb): void {
        self::$baseDb = $baseDb;
    }

    /**
     * 获取 BaseDb
     */
    static public function getBaseDb(): BaseDb {
        if (!isset(self::$baseDb)) {
            // throw new \RuntimeException("BaseDb is not set. Call TrackService::setBaseDb first.");
            self::$baseDb = new VDB();
        }
        return self::$baseDb;
    }

    /**
     * 本次业务需求的核心
     * @return UserModel[]
     */
    public function queryUserByDateLimit($dateStart,$dateEnd,$status = StatusTrackModel::STATUS_A){
        
        if (empty($dateStart) || empty($dateEnd)) {
            throw new \InvalidArgumentException("dateStart or dateEnd is empty");
        }

        // 格式化日期
        $dateStart = self::date2QueryDate($dateStart);
        $dateEnd = self::date2QueryDate($dateEnd);

        if ($dateEnd < $dateStart) {
            throw new \InvalidArgumentException("dateEnd cannot be earlier than dateStart");
        }

        $sql = "
            SELECT st.id AS id, ml.user_name AS user_name, st.status AS status
            FROM status_track st
            JOIN main_list ml ON st.id = ml.id
            WHERE st.status_date BETWEEN ? AND ?
            AND st.status = ?
        ";

        $baseDb = self::getBaseDb();

        // 使用 BaseDb 执行查询
        $rows = self::$baseDb->fetchAll($sql, [$dateStart, $dateEnd, $status]);

        // 映射成 UserModel 对象数组
        $models = [];
        foreach ($rows as $row) {
            $models[] = UserModel::dbRecordToModel($row);
        }

        return $models;
    }

    static public function date2QueryDate($date){
        return date('Y/n/j', strtotime($date));
    }



    // track;;
    public function track(UserModel $userModel,$date,$status,$trackNo = 1){
        // Implementation of tracking logic
    }


}   