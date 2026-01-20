# pegaservice

※ 中国語版 README： [README.cn.md](README.cn.md)

## はじめに

本プロジェクトの目的は以下の通りです。
- 要件を整理し、技術的に分解すること
- 適切なデータモデルおよび検索設計を行うこと
- 制約条件下における実装方針と判断理由を明示すること

## 要件および既存リソース

### 要件内容

指定した日付(status_track.status_date)の区間内で，
状態(status_track.status)が「B」であるユーザー名(main_list.user_name)のリストを取得する

以下のテーブル構成を前提とします。
### main_list テーブル

| id  | user_name | status |
|-----|-----------|--------|
| 101 | User01    | A      |
| 102 | User02    | C      |
| 103 | User03    | B      |
| 104 | User04    | A      |
| 105 | User05    | C      |
| 106 | User06    | D      |


### status_track テーブル

status_track.id は main_list.id を参照する外部キーです。
JOIN 時には主に main_list.user_name を取得する目的で使用します。

業務的には ユーザーIDは不変であり、  
user_name は将来的に変更される可能性がある前提としています。  
そのため、業務上の識別子は常に id を基準とします。  

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

> - データベース上の status_date の実フォーマットは Y/n/j（例：2025/9/3）
> - 本ドキュメントでは可読性向上のため YYYY-MM-DD 表記で記載しています

## 設計方針の整理

### テーブル構成の整理

#### main_list テーブル

各ユーザーの最新状態を保持するテーブルです。
業務上は「現在時点におけるユーザーの最終状態」を参照する目的で使用します。

#### status_track テーブル

ユーザーの状態変更履歴を保持するテーブルです。
本課題では、過去の状態を日付条件で検索するための主なデータソースとなります。

## 要件実現について

### 中核となる SQL

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

要件から判断すると、検索条件の中心は以下の項目です。
- status_track.status_date
- status_track.status

status_track に一定規模のデータが存在する前提では、  
検索効率を重視したインデックス設計が重要となります。

本実装では以下を前提としています。
- 主キー：(id, track_no)
- 検索用の複合インデックス：(status_date, status, id)

将来的に track_no や status を結果として返す要件が追加された場合でも、  
インデックスカバリングを活用できる構成としています。

### バックエンド実装

#### ドメインモデル

1. **ユーザーモデル（main_list）**  
   - **位置**：[src/model/MainListModel.php](src/model/MainListModel.php)  
   - **概要**：ユーザーの基本情報を保持（id, user_name, status）  

2. **ステータス履歴モデル（status_track）**  
   - **位置**：[src/model/StatusTrackModel.php](src/model/StatusTrackModel.php)  
   - **概要**：状態変更履歴を管理（id, track_no, status, status_date）

---

#### サービス層

1. **TrackService**  
   - **位置**：[src/service/TrackService.php](src/service/TrackService.php)  
   - **概要**：本機能の中核を担うサービス。
   - `setBaseDb()` を通じて、利用する DB 実装を切り替えることが可能。  
     これにより、特定の DB 実装に依存しない **疎結合な構成** を実現している  
     （詳細は[src/service/BaseDb.php](src/service/BaseDb.php) を参照）。

2. **デモ実行用スクリプト**  
   - **位置**：[demo/demo.php](demo/demo.php)  
   - **概要**：CLI / HTTP 両対応の簡易実行環境 

---

### 実行例

**CLI**：

```bash
# テスト用データの初期化
php demo.php --action=dbInit

# 指定した期間・ステータス条件に該当するユーザーを検索
php demo.php --action=queryUserByDateLimit --status=A --dateStart=2025/09/01 --dateEnd=2025/09/02
```

**http**：
```bash
GET /demo.php?action=queryUserByDateLimit&status=A&dateStart=2025/09/01&dateEnd=2025/09/02
```

---

### フロントエンド

jQuery UI の Datepicker を使用し、  
簡易的な検索 UI を実装しています。

- 開始日・終了日の指定
- 状態の選択
- AJAX による検索結果表示

#### ローカル起動

PHP の組み込みサーバーを起動することで、ローカル環境で動作確認が可能です。

> ※ ローカルでの実行前に、MySQL の接続設定をご確認ください。  
>   設定ファイル： [src/service/VDB.php](src/service/VDB.php)
```
# PHP 組み込みサーバーを起動
php -S 127.0.0.1:8000

# データベース初期化（テストデータ作成）
http://127.0.0.1:8000/demo/demo.php?action=dbInit

# 動作確認用ページを開く
http://127.0.0.1:8000/demo/index.html
```



## 更新履歴

### 1.0
- 要件に必要な検索機能を中心に初期実装
- データモデルおよび SQL 実装

### 1.1
- TrackService の責務整理
- データ書き込み処理の追加（`addUser` / `track` / `updateUserStatus`）
- 将来拡張を考慮した構造整理



## 制約事項および今後の検討

### データモデル観点
- status_track の生成ルールや順序保証については要件未確定
- 分散環境・単一点書き込みにおけるトランザクション設計は今後の検討対象
- データ量増加時の簡易的な最適化方針については別途整理予定

### 工程・統合観点（実装済）
- 本モジュールは独立したサービスとして利用可能
- 実際のプロジェクトへの組み込み例として、LINE Bot プロジェクトに統合

#### 統合プロジェクト概要
- プロジェクト名：lineBotDemo
- 構成：Laravel + LINE Bot
- 統合方式：Git Submodule
- 役割：SaaS 基盤を想定した共通サービス層
- 使用シーン：LINE メッセージのやり取り、コマンド解析


URL：
https://github.com/hellolemon030-wq/lineBotDemo/tree/feather-pega

重点ディレクトリ：
https://github.com/hellolemon030-wq/lineBotDemo/tree/feather-pega/app/Services/pegaservice

業務ロジックから切り離した形でサービス層を構成することで、  
将来的な機能拡張や他プロジェクトへの横断的な利用を見据えた設計としています。