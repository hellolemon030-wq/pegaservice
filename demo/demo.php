<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pegaservice\Homework\model\StatusTrackModel;
use Pegaservice\Homework\service\TrackService;
use Pegaservice\Homework\service\VDB;

/**
 * 运行示例：
 * CLI 模式：
 * php demo.php --action=dbInit
 * php demo.php --action=queryUserByDateLimit --status=A --dateStart=2025/09/01 --dateEnd=2025/09/02
 */
class demo{

    private array $allowActions = [
        'queryUserByDateLimit',
        'dbInit',
    ];

    public function __run(){
        // 获取 action 参数，默认 queryUserByDateLimit
        $action = $this->getParam('action', '');
        if(empty($action)){
            $this->error("Action parameter is required.");
            return;
        }

        //todo: 只有允许的 action 列表/或时public且为非_run;
        // ===== TODO 安全校验实现 =====
        if (!in_array($action, $this->allowActions, true)) {
            $this->error("Action '$action' is not allowed.");
        }

        // 安全检查：方法是否存在且可调用
        if (method_exists($this, $action) && is_callable([$this, $action])) {
            try{
                $this->$action();
            } catch (\Exception $e){
                //throw $e;
                $this->error($e->getMessage(), $e->getCode() ?: 500);
            }
        } else {
            $this->error("Action '$action' is not valid."); 
            return;
        }   
    }

    private function getParam($paramKey,$default = null){
        // CLI 模式
        if($this->isCmd()){
            global $argv;
            foreach ($argv as $arg) {
                if (str_starts_with($arg, "--$paramKey=")) {
                    return substr($arg, strlen("--$paramKey="));
                }
            }
            return $default;;
        }

        // HTTP 模式
        if (isset($_GET[$paramKey])) return $_GET[$paramKey];
        if (isset($_POST[$paramKey])) return $_POST[$paramKey];

        return $default;
    }

    private function outPut($data){
        if ($this->isCmd()) {
            $this->returnCmd($data);
        } else {
            $this->returnJson($data);
        }
    }
    private function error($errorMessage,$errorCode = 400){
        $this->outPut([
            'error' => $errorMessage,
            'code' => $errorCode
        ]);
        exit;
    }

    private function isCmd(){
        return php_sapi_name() === 'cli';   
    }
    private function isHttp(){
        return !$this->isCmd();
    }   

    private function returnJson($data){
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function returnCmd($data, $level = 0){
        $indent = str_repeat("  ", $level); // 缩进显示层级
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                echo $indent . "[$key] => ";
                if (is_array($value) || is_object($value)) {
                    echo "\n";
                    $this->returnCmd($value, $level + 1);
                } else {
                    echo $value . "\n";
                }
            }
        } else {
            echo $indent . $data . "\n";
        }
    }

    private function getTrackService():TrackService{
        $trackService = new TrackService();
        TrackService::setBaseDb(new VDB()); // 可以对接不同的 BaseDb 实现，例如适应不同业务框架的 DB 访问层；例如laravel的Eloquent等；
        return $trackService;
    }

    public function queryUserByDateLimit(){
        $trackService = $this->getTrackService();

        $dateStart = $this->getParam('dateStart');
        $dateEnd = $this->getParam('dateEnd');
        $status = $this->getParam('status',StatusTrackModel::STATUS_A);

        $result = $trackService->queryUserByDateLimit($dateStart, $dateEnd, $status);
        $this->outPut($result);
    }

    public function dbInit(){
        $trackService = $this->getTrackService();
        $trackService->serviceInit(true);
        $this->outPut("Database initialized successfully.");
    }
}

$demo = new demo();
$demo->__run();


