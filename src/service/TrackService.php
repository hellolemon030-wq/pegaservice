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
        $rows = $baseDb->fetchAll($sql, [$dateStart, $dateEnd, $status]);

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


    public function getUserById($id){
        $row = static::$baseDb->fetchRow(
            "SELECT id, user_name, status FROM main_list WHERE id = ?",
            [$id]
        );

        if ($row === null) {
            return null;
        }

        return UserModel::dbRecordToModel($row);
    }

    public function addUser($id,$userName,$status = StatusTrackModel::STATUS_A){
        return static::$baseDb->execute(
            "INSERT INTO main_list (id, user_name, status) VALUES (?, ?, ?)",
            [$id, $userName, $status]
        );
    }


    /**
     * track；
     * 假定trackNo/date都是正确的；
     * 假定，是因为，trackNo/status/date，似乎存在一定的关联性，但是需求并没有清晰；所以默认该次需求只是拷贝数据做查询；拷贝的数据源是正确的；
     * 只解决需求查询的问题；
     * 针对该次需求，最核心的关键是索引的问题；分析sql，最终落实联合索引为(status_date, status, id)；
     * 
     * 后续：
     * 数据写入，对于同一id，是否需要做唯一性校验？是否需要校验status/date的合理性？
     * 是否支持分布式；（保证先后顺序）；
     * 是否需要考虑事件重复被处理；
     * 当前status_track的主键索引为(id, track_no)，很可能需要更具实际进行调整；
     * 
     */
    public function track(UserModel $userModel,$date,$status,$trackNo = 1){
        $userId = $userModel->id;

        $date = static::date2QueryDate($date); 
        
        //todo: 是否需要确定写入数据的日期是否满足条件（例如，不能早于上一次的日期）？

        //todo: trackNo/status 的合理性校验？例如trackNo/status均智能递增；

        //当前相信外部调用的数据源，及调用顺序的正确性；只做数据入库；
        static::$baseDb->execute('begin');
        try{
            $result = static::$baseDb->execute(
                "INSERT INTO status_track (id, track_no, status, status_date)
                VALUES (?, ?, ?, ?)",
                [$userId, $trackNo, $status, $date]
            );
            if($result){
                $this->updateUserStatus($userModel,$status);
            }
            static::$baseDb->execute('commit');
        } catch (\Exception $e){
            static::$baseDb->execute('rollback');
            throw $e;
        }   
        return true;
    }

    public function updateUserStatus(UserModel $userModel,$newStatus){
        $userId = $userModel->id;
        $result = static::$baseDb->execute(
            "UPDATE main_list SET status = ? WHERE id = ?",
            [$newStatus, $userId]
        );
        return $result;
    }

    /**
     * check: select * from status_track join main_list where status_track.id = main_list.id;
     */
    public function serviceInit($reset = false){
        $db = self::getBaseDb();
        if($reset){
            $db->execute("DROP TABLE IF EXISTS status_track");
            $db->execute("drop TABLE IF EXISTS main_list");
        }

        // 创建表
        $db->execute("
            CREATE TABLE IF NOT EXISTS main_list (
                id INTEGER PRIMARY KEY,
                user_name TEXT NOT NULL,
                status TEXT NOT NULL
            );
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS status_track (
                id INT NOT NULL,
                track_no INT NOT NULL,
                status VARCHAR(10) NOT NULL,
                status_date varchar(16) NOT NULL,
                
                PRIMARY KEY (id, track_no),
                KEY idx_status_date_status_id (status_date, status, id)
            )
        ");

        $mainList = [
            [101,'User01','A'],
            [102,'User02','C'],
            [103,'User03','B'],
            [104,'User04','A'],
            [105,'User05','C'],
            [106,'User06','D'],
        ];
        foreach ($mainList as $row) {
            $this->addUser($row[0],$row[1],$row[2]);
        }

        // 插入 status_track
        $statusTrack = [
            [101,1,'A','2025/9/3'],
            [102,1,'A','2025/9/1'],
            [102,2,'B','2025/9/3'],
            [102,3,'C','2025/9/4'],
            [103,1,'A','2025/9/2'],
            [103,2,'B','2025/9/3'],
            [104,1,'A','2025/9/1'],
            [105,1,'A','2025/9/1'],
            [105,2,'B','2025/9/2'],
            [105,3,'C','2025/9/4'],
            [106,1,'A','2025/9/1'],
            [106,2,'B','2025/9/2'],
            [106,3,'C','2025/9/3'],
            [106,4,'D','2025/9/4'],
        ];
        foreach ($statusTrack as $row) {
            $userModel = new UserModel();
            $userModel->id = $row[0];
            $this->track($userModel,$row[3],$row[2],$row[1]);
        }
    }
}   