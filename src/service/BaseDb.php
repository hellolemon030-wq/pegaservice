<?php
namespace Pegaservice\Homework\service;

abstract class BaseDb
{
    /**
     * 执行查询，返回数组（每行是关联数组）
     * @param string $sql
     * @param array $params
     * @return array
     */
    abstract public function fetchAll(string $sql, array $params = []): array;

    /**
     * 执行查询，返回单行（关联数组）
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    abstract public function fetchRow(string $sql, array $params = []): ?array;

    /**
     * 执行非查询语句（insert/update/delete）
     * @param string $sql
     * @param array $params
     * @return int 受影响行数
     */
    abstract public function execute(string $sql, array $params = []): int;
}