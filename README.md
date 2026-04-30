# Fzr (Feather)

軽量・高速・Pure PHP。PHP 8.1+ / Composer / Cloud Native (GCP) 対応。
外部ライブラリへの依存関係ゼロで、小規模・中規模Webアプリ開発に最適なフレームワークです。

---

## 🚀 導入ガイド

Fzrのセットアップ方法（Composer, Phar, Gitクローン）については、**[docs/install.md](./docs/install.md)** を参照してください。

---

### index.php（エントリーポイント）

```php
<?php
require __DIR__ . '/vendor/autoload.php';      // Composer の場合（aliases.php は自動ロード済み）
// require __DIR__ . '/fzr.phar';              // Phar 1つで動かす場合
// require __DIR__ . '/core/loader.php';       // 直接DL/手動ロードの場合


use Fzr\Engine;
use Fzr\Path;

define('ABSPATH', __DIR__);
define('APP_START_TIME', microtime(true));

// 初期化
Engine::init(__DIR__, 'app/app.ini');

// オートローダー登録（コントローラ/モデル等）
Engine::autoload('app/controllers', 'app/models');

// ブートストラップ
Engine::bootstrap(__DIR__ . '/app/bootstrap.php');

// エラーハンドリング
Engine::onError(function(\Throwable $ex) {
    // カスタムエラー処理
});

// ディスパッチ
Engine::dispatch();
```

### bootstrap.php

```php
<?php
use Fzr\Db\Db;
use Fzr\Db\LiteDb;
use Fzr\Env;
use Fzr\Logger;

// Cloud Runの場合はstderrロギング
if (getenv('K_SERVICE')) { // Cloud Run環境判定
    Logger::setOutput('stderr');
}

// DB設定（SQLite）
$db = LiteDb::create('app');
Db::addConnection('default', $db);
Db::migrate();
```

### コントローラ

```php
<?php
use Fzr\Controller;
use Fzr\Response;
use Fzr\Request;
use Fzr\Render;
use Fzr\Attr\Http\Csrf;
use Fzr\Attr\Http\Roles;

class IndexController extends Controller {

    public function index() {
        Render::setTitle('ホーム');
        return Response::view('index');
    }

    #[Csrf]
    public function _post_save() {
        $name = Request::post('name');
        // ...
        return Response::redirect('index');
    }

    #[Roles('admin')]
    public function admin() {
        return Response::view('admin');
    }
}
```

## ルーティング・ディスパッチ規則 (Routing & Dispatch)

Fzrは設定ファイルなしで、URLからコントローラとメソッドを自動的に解決します。

### 基本ルール
`/{controller}/{action}/{params...}` -> `{{Controller}}Controller::{{action}}()`

| URL | コントローラ | メソッド (GET時) | メソッド (POST時) |
|-----|------------|-----------------|------------------|
| `/` | `IndexController` | `index()` | `_post_index()` |
| `/user` | `UserController` | `index()` | `_post_index()` |
| `/user/edit/1` | `UserController` | `edit(1)` | `_post_edit(1)` |
| `/user-profile` | `UserProfileController` | `index()` | `_post_index()` |

### メソッドの優先順位
リクエスト種別（GET/POST/AJAX）に応じて、以下の順序でメソッドが探索されます：

1.  **AJAX**: `_ajax_{method}_{action}`, `_ajax_{action}`
2.  **標準**: `_{method}_{action}`, `{action}`

例：`POST /login` の場合、`_post_login()` があればそれが、なければ `login()` が呼ばれます。
※ `{method}` は `get`, `post`, `put`, `delete` 等。
※ アクション名が省略された場合は `index` とみなされます。

### クラス・メソッド名の変換
URLのパーツ（ケバブケース、スネークケース等）は、以下のルールで変換されます：
- **クラス名**: パスカルケース (`user-profile` -> `UserProfileController`)
- **メソッド名**: キャメルケース (`password-reset` -> `passwordReset()`)

### ビューの配置規則
コントローラのメソッドから `Response::view('path')` を呼ぶ際、ビューファイルは以下のディレクトリに配置するのが一般的です：
- `app/views/{controller}/{action}.php`
  （例：`UserController::index()` -> `app/views/user/index.php`）

### フォールバック
特定のメソッドが見つからない場合、コントローラに `__id($action, ...$params)` が定義されていれば、それが最終的な受け皿として呼び出されます。

## 名前空間

全クラスは `Fzr\` 名前空間に配置:

| クラス | 用途 |
|--------|------|
| `Fzr\Engine` | エンジン/ディスパッチ |
| `Fzr\Context` | 実行コンテキスト（モード・RequestId・デバッグ状態） |
| `Fzr\Request` | リクエスト処理 |
| `Fzr\Response` | レスポンス処理 |
| `Fzr\Render` | テンプレート |
| `Fzr\Controller` | コントローラ基底 |
| `Fzr\Route` | ルーティング |
| `Fzr\Env` | 設定（INI + 環境変数） |
| `Fzr\Logger` | ログ（file / stderr） |
| `Fzr\Auth` | 認証 |
| `Fzr\Security` | セキュリティ（CSRF/IP） |
| `Fzr\Session` | セッション |
| `Fzr\Cookie` | Cookie |
| `Fzr\Cache` | キャッシュ（ドライバ対応） |
| `Fzr\Form` | フォーム処理 |
| `Fzr\Model` | モデル基底 |
| `Fzr\Collection` | コレクション |
| `Fzr\Storage` | ストレージ（ローカル / GCS） |
| `Fzr\Message` | フラッシュメッセージ |
| `Fzr\Breadcrumb` | パンくずリスト |
| `Fzr\Path` | 物理パス |
| `Fzr\Url` | URL生成 |
| `Fzr\Db\Db` | DBファサード |
| `Fzr\Db\Query` | クエリビルダ |
| `Fzr\Db\Entity` | ActiveRecord |
| `Fzr\Db\LiteDb` | SQLiteラッパー |
| `Fzr\Db\Vector` | pgvector ベクトル検索 (RAG) |

## エイリアス有効モード

`aliases.php` を読み込むことで、グローバルクラス名がそのまま使える:

> Composer 方式では `composer.json` の `files` 設定により **自動ロード済み** です。
> Phar / Git Clone 方式では `loader.php` が自動的に読み込みます。

```php
// 手動ロードが必要な場合のみ（通常は不要）
require __DIR__ . '/vendor/fzr/fw/inc/aliases.php';

// \Request でアクセス可能
Request::get('id');
Auth::check();
Db::table('users')->where('active', 1)->all();
```

## Cloud Run 対応

### 環境変数による設定

`app.ini` が不要。環境変数で全ての設定が可能:

| INI キー | 環境変数 |
|----------|----------|
| `db.host` | `DB_HOST` |
| `db.driver` | `DB_DRIVER` |
| `db.database` | `DB_DATABASE` |
| `db.username` | `DB_USERNAME` |
| `db.password` | `DB_PASSWORD` |
| `db.schema` | `DB_SCHEMA` |
| `debug_mode` | `DEBUG_MODE` |
| `app_name` | `APP_NAME` |
| `force_https` | `FORCE_HTTPS` |

キーのドットはアンダースコアに変換され、大文字化して検索される。

### ログ出力

```php
// stderr に構造化JSON出力（Cloud Logging自動取り込み）
Logger::setOutput('stderr');
```

出力例:
```json
{"severity":"INFO","message":"GET 200","time":"2026-04-18 19:00:00","requestId":"a1b2c3d4","userId":"-"}
```

### exit制御

```php
// Cloud Functions / テスト環境で exit を無効化
Response::setExitOnSend(false);
```

### キャッシュドライバ

```php
// Redis等の外部ドライバ設定
Cache::setDriver(new MyRedisCache());
```

ドライバは `get(string $key, int $ttl, callable $closure): mixed` メソッドを実装すればよい。

## Attributes

### HTTP Attributes

```php
use Fzr\Attr\Http\{Csrf, Api, Roles, AllowCors, AllowCache, AllowIframe, IsReadOnly, IpWhitelist};

#[Api]              // APIモード（JSONレスポンス）
#[Csrf]             // CSRF検証必須
#[Roles('admin')]    // ロール制限
#[AllowCors]        // CORS許可（複数ドメイン・プリフライト自動応答）
#[AllowCache(3600)] // キャッシュ許可（Cache-Control）
#[AllowIframe]      // iframe埋め込み許可
#[IsReadOnly]       // POST/PUT/DELETE 禁止
#[IpWhitelist]      // IP制限（Storage/CIDR/物理パス対応）
```

### フィールド Attributes (バリデーション)

モデルのプロパティに付与することで、自動バリデーションやラベル表示に利用できます。

```php
use Fzr\Attr\Field\{Label, Required, Max, Min, MaxValue, MinValue, Email, Numeric, Integer, Url, Regex, In, NotIn, Between, Confirmed, SameAs, Date, Custom};

class User extends \Fzr\Model {
    #[Label('ユーザー名'), Required, Max(50)]
    public string $name;

    #[Label('メールアドレス'), Required, Email]
    public string $email;

    #[Label('パスワード'), Required, Min(8), Confirmed]
    public string $password;
    
    #[Label('年齢'), Integer, Between(18, 100)]
    public int $age;

    #[Label('パスワード確認'), Required, Custom('checkPasswordMatch')]
    public string $password_confirm;

    public function checkPasswordMatch($value): bool|string {
        return $value === $this->password ? true : 'パスワードが一致しません';
    }
}
```

| Attribute | 説明 |
| :--- | :--- |
| `#[Label('名前')]` | フィールドの表示名を設定 |
| `#[Required]` | 必須項目 |
| `#[Email]` | メールアドレス形式 |
| `#[Max(n)]` | 最大文字数 |
| `#[Min(n)]` | 最小文字数 |
| `#[MaxValue(n)]` | 最大値 |
| `#[MinValue(n)]` | 最小値 |
| `#[Numeric]` | 数値形式 |
| `#[Integer]` | 整数形式 |
| `#[Url]` | URL形式 |
| `#[Date]` | 日付形式 |
| `#[Regex('pattern')]` | 正規表現 |
| `#[In('a', 'b')]` | 指定値のいずれか |
| `#[Between(min, max)]` | 数値の範囲 |
| `#[Confirmed]` | `_confirmation` サフィックスのフィールドと一致するか |
| `#[SameAs('field')]` | 指定したフィールド名と一致するか |
| `#[Custom('method')]` | モデル内の指定メソッドでバリデーションを実行 |

## DB操作

対応ドライバ: **SQLite** / **MySQL** / **PostgreSQL**

```php
use Fzr\Db\Db;

// テーブルクエリ
$users = Db::table('users')
    ->where('active', 1)
    ->orderBy('created_at', 'DESC')
    ->all();

// ページネーション
$result = Db::table('posts')
    ->where('published', 1)
    ->page(Request::getInt('page', 1), 20);

echo $result->links();

// トランザクション
Db::transaction(function($pdo) {
    Db::table('accounts')->where('id', 1)->update(['balance' => 500]);
    Db::table('logs')->insert(['action' => 'transfer', 'amount' => 100]);
});

// RAW SQL
$rows = Db::select("SELECT * FROM users WHERE age > :age", ['age' => 18]);
```

## ベクトル検索 / RAG（PostgreSQL pgvector）

PostgreSQL + pgvector を利用して、Embedding ベースの類似検索（RAG）が可能。

### セットアップ

```php
// bootstrap.php
use Fzr\Db\Db;
use Fzr\Db\Connection;
use Fzr\Db\Vector;

// PostgreSQL接続
Db::addConnection('default', Connection::fromEnv());

// pgvector 初期化
$vec = new Vector(Db::connection());
$vec->ensureExtension(); // CREATE EXTENSION IF NOT EXISTS vector
```

### テーブル作成

```php
$vec = new Vector(Db::connection());

// 1536次元 (OpenAI text-embedding-3-small)
$vec->createTable('documents', 1536, [
    'title TEXT NOT NULL',
    'content TEXT NOT NULL',
    'source TEXT',
    'metadata JSONB',
]);
```

### データ挿入

```php
// OpenAI API等でEmbeddingを生成した後
$embedding = [0.012, -0.034, 0.056, ...]; // 1536次元のfloat配列

$id = $vec->insert('documents', $embedding, [
    'title'    => 'ドキュメントタイトル',
    'content'  => 'ドキュメント本文テキスト...',
    'source'   => 'manual_v2.pdf',
    'metadata' => ['page' => 15, 'chapter' => 3],
]);

// バルクインサート
$vec->bulkInsert('documents', [
    ['embedding' => [...], 'title' => 'Doc 1', 'content' => '...'],
    ['embedding' => [...], 'title' => 'Doc 2', 'content' => '...'],
]);
```

### 類似検索

```php
// クエリベクトルで類似検索（コサイン類似度）
$results = $vec->search('documents', $queryEmbedding, limit: 10);

foreach ($results as $row) {
    echo "{$row->title} (distance: {$row->distance})\n";
    echo $row->content . "\n\n";
}

// L2距離での検索
$results = $vec->search('documents', $queryEmbedding, 10, Vector::L2);

// WHERE条件付き検索
$results = $vec->search('documents', $queryEmbedding, 10, Vector::COSINE, [
    'source' => 'manual_v2.pdf'
]);
```

### RAGコンテキスト取得

LLM に渡すコンテキストを一発で取得:

```php
$result = $vec->getContext(
    table: 'documents',
    queryEmbedding: $queryEmbedding,
    contentColumn: 'content',
    limit: 5,
    maxDistance: 0.8
);

// $result['context']  → 関連テキストを結合した文字列
// $result['sources']  → 元の行データ配列
// $result['count']    → ヒット件数

// LLMプロンプト例
$prompt = "以下のコンテキストを参考に質問に回答してください。\n\n"
        . "コンテキスト:\n{$result['context']}\n\n"
        . "質問: {$userQuestion}";
```

### 距離関数

| 定数 | 演算子 | 用途 |
|------|--------|------|
| `Vector::COSINE` | `<=>` | コサイン距離（デフォルト、推奨） |
| `Vector::L2` | `<->` | ユークリッド距離 |
| `Vector::INNER_PRODUCT` | `<#>` | 内積 |

### インデックス管理

```php
// IVFFlat インデックス再構築（データ量増加後）
$vec->reindex('documents', lists: 200);

// HNSW インデックス（高精度、構築は遅い）
$vec->createHnswIndex('documents');
```

## グローバル関数

```php
h($str)           // HTMLエスケープ
e($str)           // HTMLエスケープ（エイリアス）
url('path')       // URL生成
env('key', 'def') // 設定値取得
csrf_field()      // CSRFフィールドHTML
csrf_token()      // CSRFトークン値
collect([...])    // Collection生成
redirect('url')   // リダイレクト応答
view('template')   // ビュー応答
dd($var)          // デバッグダンプ＋終了
```

## エコシステム

Fzr は以下の2つの主要コンポーネントで構成されています。

1.  **Fzr FW** (`fzr/fw`): ルーティング、リクエスト/レスポンス、ミドルウェア、DBコア。
2.  **[Fzr Kit](https://github.com/t-ichi324/fzr-kit)** (`fzr/kit`): CSV、ZIP、和暦/日付、高度な文字列/配列操作ユーティリティ。

## ライセンス

MIT
