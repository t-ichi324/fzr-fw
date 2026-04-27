# Fzr Configuration Reference (app.ini)

`app.ini` は Fzr フレームワークの動作を制御するための中心的な設定ファイルです。Fzr は「一度書いたらどこでも動く」ことを目指し、環境ごとに柔軟に設定を上書きできる仕組みを備えています。

## 設定の優先順位 (Priority Hierarchy)

Fzr は以下の順序（上が優先）で設定値を決定します。

1.  **Server 環境変数 (OSレベル)**  
    - コンテナの `environment` や Cloud Run の変数等。最も強力な上書き手段です。
2.  **`.env` ファイル**  
    - 主にローカル開発用。OS側に同名の環境変数が存在しない場合のみ適用されます。
3.  **`include_ini` (追加ファイル)**  
    - `app.ini` 内で指定された追加のINIファイル。環境ごとに異なるファイルを配置して切り替える際に便利です。
4.  **`app.ini` (ベース設定)**  
    - プロジェクトのデフォルト値。リポジトリにコミットし、全環境で共有します。

> [!TIP]
> **「共通設定は `app.ini`、秘密情報（パスワード等）はサーバー環境変数や `.env`」** という使い分けが Fzr の推奨スタイルです。

### 内部キーの正規化
Fzr は内部的にすべての設定キーを **`UPPER_SNAKE_CASE`** に正規化します。
- `app.debug` (INI) → `APP_DEBUG` (OS環境変数)
- `db.host` (INI) → `DB_HOST` (OS環境変数)

---

## 🏗️ 環境別ベストプラクティス (Case Studies)

### 1. ローカル開発 (Windows/Mac/WSL)
ローカルでは `.env` ファイルを使って、Gitに含めたくない秘密情報や個人の好みの設定を管理します。

**`.env` (Git管理外)**
```bash
APP_DEBUG=true
DB_PASSWORD=secret_password
```

**`app.ini` (共通設定)**
```ini
[app]
debug = false ; .env があれば上書きされる
```

### 2. Docker / コンテナ環境
`docker-compose.yml` で環境変数を注入し、ログをコンテナ標準の `stderr` (標準エラー出力) に流す設定が一般的です。

**`docker-compose.yml`**
```yaml
services:
  web:
    image: my-fzr-app
    environment:
      - APP_DEBUG=true
      - DB_HOST=db
      - DB_DATABASE=myapp
      - LOG_OUTPUT=stderr # Cloud Logging / CloudWatch 等で収集しやすくなる
```

### 3. GCP Cloud Run (サーバーレス)
マネージドサービスでは環境変数や Secret Manager を使用します。また、一時ファイルは `/tmp` に逃がす必要があります。

**クラウドコンソールの環境変数設定例:**
- `DB_HOST`: `/cloudsql/project:region:instance` (Cloud SQL Proxy利用時)
- `STORAGE_DRIVER`: `gcs`
- `PATH_TEMP`: `/tmp/fzr` (読み書き可能な領域を指定)

**`app.ini` (クラウド最適化の例)**
```ini
[path]
temp = /tmp/fzr-cache

[log]
output = stderr ; Cloud Logging 等に構造化ログとして送る
```

### 4. レンタルサーバー (エックスサーバー、ロリポップ等)
OSレベルの環境変数が設定しづらい環境では、`include_ini` を活用して「公開ディレクトリより上」に置いた秘密ファイルを安全に読み込みます。

**`/home/user/app/app.ini` (リポジトリ管理)**
```ini
; 公開ディレクトリより上の階層にある秘密ファイルを読み込む
include_ini = "../../secrets/db.ini"

[app]
name = "My Production Website"
```

**`/home/user/secrets/db.ini` (サーバーに手動で配置)**
```ini
[db]
host = "mysql123.xserver.jp"
database = "user_dbname"
username = "user_dbuser"
password = "real_password"
```

---

## [app] - 基本設定
| キー | 説明 | デフォルト |
| :--- | :--- | :--- |
| `app.name` | アプリケーションの名称 | `Fzr App` |
| `app.key` | 暗号化キー (32文字) | (自動生成) |
| `app.debug` | デバッグモード (詳細エラー表示) | `false` |
| `app.lang` | 言語設定 (`ja`, `en` 等) | `ja` |
| `app.timezone` | タイムゾーン | `Asia/Tokyo` |
| `app.force_https` | HTTPS への強制リダイレクト | `false` |

## [db] - データベース設定
| キー | 説明 | デフォルト |
| :--- | :--- | :--- |
| `db.driver` | 使用するDB (`sqlite`, `mysql`, `pgsql`) | `mysql` |
| `db.host` | DBホスト | `localhost` |
| `db.database` | データベース名 | - |
| `db.username` | ユーザー名 | - |
| `db.password` | パスワード | - |
| `db.sqlite_path` | SQLite使用時のファイルパス | `storage/db/app.db` |

## [storage] - ストレージ設定
| キー | 説明 | デフォルト |
| :--- | :--- | :--- |
| `storage.driver` | デフォルト保存先 (`local`, `gcs`) | `local` |
| `storage.public_disk` | 公開用ディスクの名称 | `public` |

## [path] - ディレクトリパス設定
書き込み制限がある環境や、ディレクトリ構造が特殊な場合に上書きします。
- `path.storage`: ストレージルート
- `path.db`: DBファイル保存先
- `path.log`: ログ出力先
- `path.temp`: 一時ファイル保存先

## [log] - ロギング
| キー | 説明 | デフォルト |
| :--- | :--- | :--- |
| `log.output` | 出力先 (`file`, `stderr`, `null`) | `file` |
| `log.access` | アクセスログの記録 | `true` |
| `log.debug` | デバッグログの出力 | `app.debug` 連動 |
| `log.db_sel` | SELECTクエリの記録 | `app.debug` 連動 |

---

## [session] / [cookie] - 接続・状態保持
| セクション | キー | 説明 | デフォルト値 |
| :--- | :--- | :--- | :--- |
| `session.name` | セッションクッキー名 | `SID` |
| `session.save_path` | セッションファイルの保存場所 | `storage/temp/sessions` |
| `session.auth_key` | ログイン情報のハッシュ用キー | (app.keyを使用) |
| `cookie.domain` | クッキーの有効ドメイン | - |
| `cookie.secure` | HTTPSのみに制限するか | (環境に応じて自動) |
| `cookie.httponly` | JSからのアクセスを禁止するか | `true` |

## [security] - セキュリティ設定
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `security.csrf_name` | CSRFトークンのキー名 | `csrf_token` |
| `security.allow_external_redirect` | 外部URLへのリダイレクト許可 | `false` |

## [view] - ビュー設定
| キー | 説明 | デフォルト値 |
| :--- | :--- | :--- |
| `view.base_template` | 基本となるベーステンプレート | `@layouts/base.php` |
| `view.error_prefix` | エラービューの接頭辞 | `errors/` |

---

# 高高度な設定例

### 1. Cloud Run / Cloud Logging 対応 (stderr出力)
コンテナ環境など、ログを標準エラー出力に JSON 構造化して出したい場合。

```ini
[log]
output = stderr
access = true
debug = false
```

### 3. 一時ファイルやセッションを /tmp に逃がす場合
書き込み制限のある環境などで、OS標準のテンポラリディレクトリを使用する場合。

```ini
[path]
temp = /tmp/fzr-cache

[session]
save_path = /tmp/fzr-sessions
```