<?php
namespace Pegaservice\Homework\service;

class VDB extends BaseDb
{
    private static ?\PDO $pdo = null;

    /** 获取 PDO 实例 */
    public static function getPDO(): \PDO
    {
        if (self::$pdo === null) {
            $host = '127.0.0.1';
            $dbname = 'homework';
            $user = 'root';
            $pass = '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            self::$pdo = new \PDO($dsn, $user, $pass);
            self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }

    /** 初始化测试数据 */
    public static function initTestData(): void
    {
        $vdb = new self();

        // 创建表
        $vdb->execute("
            CREATE TABLE IF NOT EXISTS main_list (
                id INTEGER PRIMARY KEY,
                user_name TEXT NOT NULL,
                status TEXT NOT NULL
            );
        ");

        $vdb->execute("

            CREATE TABLE IF NOT EXISTS status_track (
                id INT NOT NULL,
                track_no INT NOT NULL,
                status VARCHAR(10) NOT NULL,
                status_date varchar(16) NOT NULL,
                
                PRIMARY KEY (id, track_no),
                KEY idx_status_date_status_id (status_date, status, id)
            )
        ");

        // 清空旧数据
        $vdb->execute("TRUNCATE TABLE status_track");
        $vdb->execute("TRUNCATE TABLE main_list");

        // 插入 main_list
        $mainList = [
            [101,'User01','A'],
            [102,'User02','C'],
            [103,'User03','B'],
            [104,'User04','A'],
            [105,'User05','C'],
            [106,'User06','D'],
        ];
        foreach ($mainList as $row) {
            $vdb->execute("INSERT INTO main_list (id,user_name,status) VALUES (?,?,?)", $row);
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
            $vdb->execute("INSERT INTO status_track (id,track_no,status,status_date) VALUES (?,?,?,?)", $row);
        }
    }

    /** 执行查询并返回所有行 */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        // $stmt->debugDumpParams();
        return $stmt->fetchAll();
    }

    /** 执行查询并返回单行 */
    public function fetchRow(string $sql, array $params = []): ?array
    {
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** 执行非查询语句 */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}