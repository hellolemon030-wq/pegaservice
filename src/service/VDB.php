<?php
namespace Pegaservice\Homework\service;

class VDB extends BaseDb
{
    private static ?\PDO $pdo = null;

    /** 
     * 测试前，按实际修改数据库连接配置
     */
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