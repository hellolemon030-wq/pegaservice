# pegaservice

## 写在前面

本项目以一次实际业务需求为原型，作为一个开放性课题进行实现。

需求背景、字段含义及业务语义均基于已有信息进行合理推断，  
在不影响核心需求实现的前提下，部分业务设定可能与真实生产环境存在差异。

本项目的目标在于：
- 清晰拆解需求
- 设计合理的数据模型与查询方案
- 在有限前提下给出工程层面的实现与取舍说明

## 需求及现有资源

### 具体需求表述内容

指定した日付(status_track.status_date)の区間内で，
状態(status_track.status)が「B」であるユーザー名(main_list.user_name)のリストを取得する

表参考如下：
### main_list表

| id  | user_name | status |
|-----|-----------|--------|
| 101 | User01    | A      |
| 102 | User02    | C      |
| 103 | User03    | B      |
| 104 | User04    | A      |
| 105 | User05    | C      |
| 106 | User06    | D      |


### status_track表

status_track表，id字段为外键，对应main_list.id，一般用于join的时候匹配main_list.user_name（或换句话说，用户id是永恒固定的，user_name也有可能会被变更，业务用main_list.id为准）；

| id  | track_no | status | status_date |
|-----|----------|--------|-------------|
| 101 | 1        | A      | 2025/09/03  |
| 102 | 1        | A      | 2025/09/01  |
| 102 | 2        | B      | 2025/09/03  |
| 102 | 3        | C      | 2025/09/04  |
| 103 | 1        | A      | 2025/09/02  |
| 103 | 2        | B      | 2025/09/03  |
| 104 | 1        | A      | 2025/09/01  |
| 105 | 1        | A      | 2025/09/01  |
| 105 | 2        | B      | 2025/09/02  |
| 105 | 3        | C      | 2025/09/04  |
| 106 | 1        | A      | 2025/09/01  |
| 106 | 2        | B      | 2025/09/02  |
| 106 | 3        | C      | 2025/09/03  |
| 106 | 4        | D      | 2025/09/04  |

> - `status_date` 在数据库中的原始格式为 `Y/n/j`（如 `2025/9/3`）
> - 文档中为提高可读性，统一展示为 `YYYY-MM-DD`


## 简单分析

### 数据表基本分析

#### main_list表

用户最终状态表，支撑的业务需求，大致就是，能反映当前时间节点，用户最后的状态；  

#### status_track表

状态变更的具体明细；

## 需求实现相关

### 需求解决核心 【sql】

```sql
SELECT
    st.id AS id,
    ml.user_name AS user_name,
    st.status AS status
FROM status_track st
JOIN main_list ml ON st.id = ml.id
WHERE 
    st.status_date BETWEEN '2025-09-01' AND '2025-09-02'
    AND 
    st.status = 'B';
```

单从需求分析，表的设计，核心索引，status_track.status_date。以及main_list.id  
对于status_track表，假定数据量有一定量，后续需求返回数据如果需要返回status/track_no，可能会用到索引覆盖；定义主键索引(id,track_no)以及联合索引(status_date, status, id)

### backend具体实现-业务抽象

1. **用户表 main_list**  
   - **位置**：[src/model/MainListModel.php](src/model/MainListModel.php)  
   - **描述**：存储用户基础信息（id, user_name, status）  

2. **状态明细表 status_track**  
   - **位置**：[src/model/StatusTrackModel.php](src/model/StatusTrackModel.php)  
   - **描述**：存储每个用户的状态变更记录（id, track_no, status, status_date）  

3. **数据库抽象 BaseDb**  
   - **位置**：[src/service/BaseDb.php](src/service/BaseDb.php)  
   - **描述**：提供统一数据库接口，可对接不同框架或 PDO，实现解耦  

---

### backend具体实现-业务代码

1. **核心业务服务 TrackService**  
   - **位置**：[src/service/TrackService.php](src/service/TrackService.php)  
   - **描述**：该次业务核心，初始化/数据写入/最终需求查询接口； 
   - **代码架构**：`setBaseDb()` 方法可注入不同数据库实现，实现不同框架/不同项目均能低成本应用项目成果；

2. **演示调用 demo**  
   - **位置**：[demo/demo.php](demo/demo.php)  
   - **描述**：CLI / HTTP 调用入口，自动根据 `action` 调用对应方法  

---

### 运行示例

**CLI 模式**：

```bash
# 初始化测试数据
php demo.php --action=dbInit

# 查询指定时间和状态的用户
php demo.php --action=queryUserByDateLimit --status=A --dateStart=2025/09/01 --dateEnd=2025/09/02
```

**http 模式**：
```bash
GET /demo.php?action=queryUserByDateLimit&status=A&dateStart=2025/09/01&dateEnd=2025/09/02
```

---

### 前端实现

页面使用 **jQuery 内嵌日期选择插件（datepicker）**，快速实现了一个简单的用户交互界面：

- 用户可以选择 **起始日期** 和 **结束日期**
- 用户可以选择或输入 **状态**
- 页面提供 **多文本输入框**（textarea），可用于显示或输入查询结果或批量参数
- 点击查询按钮即可通过 AJAX 调用 `demo.php` 获取结果并显示在页面上

#### 本地整体服务测试

在本地启动 PHP 内置服务器即可测试：
> 本地调试前，确认mysql连接问题；[src/service/VDB.php](src/service/VDB.php)  
```
# 本地调试前，确认mysql连接问题；
php -S 127.0.0.1:8000

# 初始化数据库；
http://127.0.0.1:8000/demo/demo.php?action=dbInit

# 打开测试页面；
http://127.0.0.1:8000/demo/index.html
```



## update log

### 1.0
- 实现初始版本，仅聚焦本次需求的核心查询逻辑：
  - 按指定时间区间和状态查询用户列表
- 完成基础数据模型及 SQL 查询实现

### 1.1
- 调整并整理 `TrackService` 的职责划分
- 补充基础写入能力（`addUser` / `track` / `updateUserStatus`），用于初始化数据及模拟状态变更
- 在不影响现有查询逻辑的前提下，为后续功能扩展预留结构



## 已知限制与后续思考
当前实现以满足既定查询需求为目标，以下内容为实现过程中产生的延伸思考。

### 数据模型层面
- 针对当前唯一的疑点，status_track表，具体内容的生成，分布式场景/单点写入可能遇到的问题，事务的应用等，后续可能会做一些具体的文字内容分析；
- 关于数据规模增长后等的一些简单优化手段的文字说明；

### 工程集成方向[已实现line信息交互]
- 以独立模块的形式对接至实际项目中，演示在不侵入现有业务代码的前提下，如何低成本接入不同系统；
- 例如接入到 我的另外一个在做的作品集：简易 LINE Bot 项目里，模拟 SaaS 系统中的基础服务模块

#### 集成项目说明

- 项目名称：lineBotDemo
- 项目类型：Laravel + LINE Bot 示例项目
- 模块角色：基础服务模块（Reply Engine / 活动路由）
- 集成方式：Git Submodule
- 使用场景：LINE 消息交互、指令解析

项目地址：
https://github.com/hellolemon030-wq/lineBotDemo/tree/feather-pega

重点目录：
https://github.com/hellolemon030-wq/lineBotDemo/tree/feather-pega/app/Services/pegaservice

在该项目中，pegaservice 作为独立模块被引入，用于模拟
SaaS 系统中可复用的基础服务层能力。


## 附加说明

<a id="trackno-explanation"></a>

### 主キーとtrack_noの説明

#### track_no说明
1. 当前需求用不上的字段，可能有2个具体作用；a，后续需求期望查询具体记录对应的track_no，如果track_no强制遵循自增1的话，当前字段实际是冗余字段，用以提高查询效率；没有该字段，也可以通过稍微复杂一点的原生sql得出结果，当然，该查询对于数据库存在一定的负载风险，增加该冗余字段将有效对该需求进行优化；

```sql
SELECT
    st.id,
    ml.user_name,
    st.status,
    (
        SELECT COUNT(*)
        FROM status_track st2
        WHERE st2.id = st.id
          AND st2.status_date <= st.status_date
    ) AS computed_track_no
FROM status_track st
JOIN main_list ml ON st.id = ml.id
WHERE st.status_date BETWEEN '2025/9/1' AND '2025/9/2'
  AND st.status = 'B';
```
2. 为什么以id/track_no作为主键？核心理由：a，异步写入模型，避免重复事件的写入（*）；b，索引覆盖，联合索引(status_date, status, id)+主键索引(id, track_no)的组合，索引覆盖已覆盖该表支持后续track_no的查询需求，无需回表。

#### データ量増加の対策

1. 关于索引，当前的复合索引及主键索引，在不增加新字段的场合下，所有字段都已经被索引覆盖；（当然，需要空间换效率，以及增加数据的写开销）
2. 在数据量急速增多时，当前的索引组合写开销比较大，同时，为了提高查询效率，如果能将数据控制在一定范围，是最好的。因此，可以增加定时清理数据的策略；例如，当前的查询需求，是提供一年的量的数据查询的；那么，可以写脚本每天可以将365天以前的数据迁移到其他数据库进行存储；
3. 数据量大的表的查询，可能会带来数据库高负荷的问题，自然会影响到所在系统，为了彻底避免影响其他业务系统，可以降低查询需求的服务与其他服务的耦合，例如引入主从数据库设计，又例如该表所在数据库实例与其他数据库进行分离。