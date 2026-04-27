# Fzr Framework — AI Development Guide

> このファイルはAI向けの[Fzrフレームワーク]開発・修正時の指示書です。解析・実装のたびに知見を追記してください。
> 更新日: 2026-04-24

## CLI Commands (Scaffolded Project)

- `php cli help` — List available commands
- `php cli make-model` — Generate Entity classes from DB tables
- `php cli make-model users -f` — Specified tables, force overwrite
- `php cli <name>` — Run `app/commands/<name>.php`

> `cli` はプロジェクトルートに生成されるエントリファイル。`app/commands/` 以下の `.php` がコマンドとして機能する。クラスとして書いて `Command` を継承するか、普通のスクリプトとして書くかは自由。

---

## Routing & Dispatch (Engine.php)

Auto-routing: `/{controller}/{action}/{params}` → `{Controller}Controller::{action}()`

| HTTP | URL | Controller | Method |
|------|-----|------------|--------|
| GET | `/` | `IndexController` | `index()` |
| POST | `/` | `IndexController` | `_post_index()` |
| GET | `/login` | `LoginController` | `index()` |
| POST | `/login` | `LoginController` | `_post_index()` |
| GET | `/user/edit` | `UserController` | `edit()` |
| POST | `/user/edit` | `UserController` | `_post_edit()` |

**Naming rules**
- URL segment (kebab/snake) → PascalCase class, camelCase method
- `/user-profile/password-reset` → `UserProfileController::passwordReset()`

**Method lookup order** (first match wins)
1. AJAX: `_ajax_{method}_{action}` → `_ajax_{action}`
2. Standard: `_{method}_{action}` → `{action}`
3. Fallback: `__id($action, ...$params)` (if defined)

**アクション省略時のデフォルト**: URL にアクションセグメントがない場合、action = `index` として解決される。

```
POST /login      → controller=login, action=index  → LoginController::_post_index()
POST /login/save → controller=login, action=save   → LoginController::_post_save()
POST /           → controller=index, action=index  → IndexController::_post_index()
GET  /login/login→ controller=login, action=login  → LoginController::login()
```

> `LoginController::_post_index()` と `LoginController::_post_login()` はどちらも正当なパターン。前者は `POST /login`、後者は `POST /login/login` に対応する。「どちらが正しいか」ではなく「どの URL に対応するか」の違い。

**View convention**: `app/views/{controller}/{action}.php`

---

## クラス索引（Class Index）

> クラス名からファイルを引くためのインデックス。複数クラスが1ファイルに同居しているケースを網羅。

### 複数クラスが同居しているファイル（注意）

| ファイル | 含むクラス／型 | 備考 |
|---------|-------------|------|
| `src/Attr/Http.php` | `Csrf`, `Auth`, `Api`, `Roles`, `Role`, `AllowCors`, `AllowCache`, `AllowIframe`, `IsReadOnly`, `IpWhitelist` | namespace: `Fzr\Attr\Http` |
| `src/Attr/Field.php` | `Label`, `Required`, `MaxLength`, `MinLength`, `Email`, `Numeric`, `Integer`, `Url`, `Regex`, `In`, `NotIn`, `Between`, `Confirmed`, `SameAs`, `Date`, `Custom` | namespace: `Fzr\Attr\Field` |

> `Attr/Http.php` と `Attr/Field.php` は PHP Attribute の仕様上複数定義が必要なため、オートロード不可。`src/Loader.php` で事前 `require_once` 済み。

### 1クラス1ファイル（src/）

| ファイル | クラス | 役割 |
|---------|-------|------|
| `src/Config.php` | `Config` | システム定数（`VERSION`, `CTRL_EXT` 等） |
| `src/Env.php` | `Env` | INI 読み込み・環境変数フォールバック |
| `src/Context.php` | `Context` | 実行状態（Web/CLI/API/Debug モード） |
| `src/Path.php` | `Path` | 物理パス管理 |
| `src/Form.php` | `Form` | フォームデータ管理 |
| `src/FormValidator.php` | `FormValidator` | バリデーションロジック |
| `src/FormRender.php` | `FormRender` | HTMLタグ生成 |
| `src/Loader.php` | `Loader` | PSR-4 オートローダー＋コア依存の事前 require |
| `src/Engine.php` | `Engine` | ブートストラップ・ディスパッチ |
| `src/Route.php` | `Route` | ルーティング定義 |
| `src/Model.php` | `Model` | プロパティベースモデル基底 |
| `src/Bag.php` | `Bag` | 非構造化モデル基底（配列ベース） |
| `src/Store.php` | `Store` | 静的モデル基底（レジストリベース） |
| `src/Controller.php` | `Controller` | コントローラ基底（`__before`, `__after`, `__finally`） |
| `src/Command.php` | `Command` | CLI コマンド基底（`handle(): int`, 出力・引数ヘルパー） |
| `src/Auth.php` | `Auth` | 認証・認可（`Store` 継承） |
| `src/Session.php` | `Session` | セッション管理（File/Redis/Cookie対応） |
| `src/Cookie.php` | `Cookie` | Cookie管理 |
| `src/Request.php` | `Request` | リクエスト取得 |
| `src/Response.php` | `Response` | レスポンス生成 |
| `src/Render.php` | `Render` | テンプレート描画 |
| `src/Collection.php` | `Collection` | 汎用コレクション（ArrayAccess/IteratorAggregate/Countable） |
| `src/Message.php` | `Message` | フラッシュメッセージ（Session依存） |
| `src/Url.php` | `Url` | URL生成 |
| `src/Breadcrumb.php` | `Breadcrumb` | パンくずリスト（Url依存） |
| `src/HttpException.php` | `HttpException` | HTTP例外（400/401/403/404/500等） |
| `src/Security.php` | `Security` | CSRF検証・IP制限 |
| `src/Storage.php` | `Storage` | ファイルストレージ（Local/GCS対応） |
| `src/Cache.php` | `Cache` | キャッシュ（Redis対応） |
| `src/Logger.php` | `Logger` | ログ出力（ファイル/stderr） |
| `src/Tracer.php` | `Tracer` | 実行トレース・クエリ記録 |

### 1クラス1ファイル（src/Db/）

| ファイル | クラス | 役割 |
|---------|-------|------|
| `src/Db/Db.php` | `Db` | DBスタティックファサード（`tables()`, `schema()`, `generateModels()` 含む） |
| `src/Db/Connection.php` | `Connection` | PDOラッパー（mysql/pgsql/sqlite） |
| `src/Db/Query.php` | `Query` | クエリビルダー |
| `src/Db/Result.php` | `Result` | 検索結果セット（`Collection` 継承） |
| `src/Db/Entity.php` | `Entity` | ActiveRecord基底（`Model` 継承） |
| `src/Db/Migration.php` | `Migration` | マイグレーション実行 |
| `src/Db/LiteDb.php` | `LiteDb` | SQLiteファサード（`Db` への委譲） |
| `src/Db/Vector.php` | `Vector` | pgvector対応（RAG用途） |

---

## Coding Standards & Aliases

Fzr uses **Class Aliases** to reduce context overhead. All core classes are available in the global namespace.
- **Rules**: Always use short names like `Request::get()` instead of `\Fzr\Request::get()`.
- **Reference**: Aliases are defined in `inc/aliases.php` and auto-loaded.

**Type Hinting for AI**:
- Help inference with: `/** @var User $user */ $user = Auth::user();`
- View data: `<?php /** @var array<Item> $items */ $items = Render::getData('items'); ?>`

---

## Core Classes

| Class | Key methods |
|-------|-------------|
| `Controller` | Base. Hooks: `__before`, `__after`, `__finally` |
| `AuthController` | (Removed) Use `#[Auth]` or `#[Roles]` attributes instead. |
| `Auth` | `check()`, `login()`, `logout()`, `user()`, `userObject()`, `getId()`/`id()`, `getEmail()`/`mail()`, `getUsername()`/`username()`, `getRoles()`/`roles()`, `hasRole()`, `is()`, `isAdmin()`, `isGuest()`, `token()` |
| `Message` | `success/error/warning/info($msg)`, `get()` → `['type','message']` |
| `Request` | `get/post/param/input($key, $default)`, `getInt/postInt/paramInt`, `getBool/postBool`, `isPost()`, `isAjax()` |
| `Response` | `view($tpl)`, `redirect($url)`, `json($data)`, `error($code)` |
| `Render` | `setTitle($t)`, `setData($k,$v)`, `getData($k)` |
| `Form` | Form validation + HTML generation — see section below |
| `FormValidator` | `rule()`, `required()`, `email()`, ..., `with(callable, ?string)` |
| `Session` | `get/set/remove/flash/getFlash($key)`, `regenerate()` |
| `Cookie` | `get/set/has/remove($key)` |
| `Db` | `table($t)`, `select($sql,$p)`, `execute($sql,$p)`, `transaction($fn)`, `migrate()`, `tables()`, `schema($t)`, `generateModels($dir, $force, $tables)` |
| `Command` | `handle(): int` (実装必須), `line/info/success/warn/error($msg)`, `arg($n)`, `hasFlag($flag)`, `option($key)` |
| `Entity` | ActiveRecord — see section below. `find($id): ?static`, `first($field, $val): ?static`, `where()->first()`, `all(): Result<static>` |
| `DbQuery` | Chainable query builder |
| `Storage` | `disk($name)->put/get/delete/url($path)`, `public()->...`, `private()->...` |
| `Cache` | `get($key, $ttl, $fn)` |
| `Store` | Base for static data buckets (Auth, Session etc.) — `fill()`, `get()`, `set()`, `all()`. グローバルエイリアスあり |
| `Bag` | 非構造化（配列ベース）モデル基底。`Model`（プロパティベース）と対。`fill()`, `merge()`, `replace()`, `bind()`, `get()`, `set()`, `all()`. グローバルエイリアスあり |
| `Context` | `isDebug()`, `isApi()`, `isStateless()` |

---

## Controller Patterns

```php
// Standard controller
class FooController extends Controller {
    public function index() {
        Render::setTitle('...');
        Render::setData('items', Item::all());
        return Response::view('foo/index');
    }

    // Attributes go on the route-action method (not on _post_* / _ajax_* handlers)
    #[Csrf]
    public function save() { }  // ← attribute lookup uses this method name

    public function _post_save() {
        // ...
        Message::success('Saved.');
        return Response::redirect('foo');
    }

    // Lifecycle hooks (optional overrides)
    public function __before(string $action, string $method) {
        // return a Response to short-circuit
    }
}

// Login required (any role)
#[Auth]
class DashboardController extends Controller { }

// Admin required
#[Roles('admin')]
class AdminController extends Controller { }

// Role restriction on a single method
class UserController extends Controller {
    #[Roles('admin')]
    public function destroy() { }
}
```

---

## Authentication Flow

```php
// Login (in controller _post_index)
$user = User::authenticate($email, $password); // implement this in your User model
if (!$user) {
    Message::error('Invalid credentials.');
    return Response::redirect('login');
}
Auth::login($user, [$user->role]); // second arg = roles array
return Response::redirect('/');

// Logout
Auth::logout();
return Response::redirect('login');

// Remember Me (optional)
Auth::onLogin(function($user, ?string $token) {
    if ($token) { /* store $token in DB */ }
});
Auth::resolveRemember(function(string $token): ?object {
    return User::where('remember_token', $token)->first();
});
```

---

## Form Validation + HTML Generation

`Form` wires together validation rules and HTML rendering. Model field attributes are auto-applied via `Form::from()`.

```php
// ── Controller ──────────────────────────────────────────
// Option A: derive rules from Model attributes automatically
$form = Form::from($user, ignore: ['id']);    // Use 'from' for Model or generic sources
$form = Form::fromRequest();                  // Use 'fromRequest' for automatic HTTP data
$form->fill(Request::post() ?: []);           // populate with POST data
if (!$form->validate()) {
    $form->flashError();                       // エラーをすべて Message::error() に送出
    // $form->flashError('入力内容を確認してください。'); // 概括メッセージ1件にまとめる場合
    return Response::redirect('user/create');
}
// バリデーション通過後、Modelへ書き戻す
$user = new User();
$form->sync($user);          // フォームのデータをモデルのプロパティに反映
$user->setPassword($form->get('password'));  // パスワードは個別処理
$user->save();

// Option B: define rules manually
$form = Form::fromRequest();
$form->rule('name',  '名前')->required()->maxLength(50);
$form->rule('email', 'メール')->required()->email();
$form->rule('age',   '年齢')->integer()->between(0, 150);

// Add dynamic rules via with()
$form->rule('email')->with(fn($v) => !User::exists($v), 'Already exists.');

if (!$form->validate()) { ... }

// Pass form to view
Render::setData('form', $form);

// ── View ────────────────────────────────────────────────
<?php $form = Render::getData('form'); ?>
<form method="post">
    <?= csrf_field() ?>
    <?= $form->tag('name')->label() ?>
    <?= $form->tag('name')->input('text', ['class' => 'form-control']) ?>
    <?= $form->tag('name')->error() ?>          <!-- first error string -->

    <?= $form->tag('role')->select(['user' => '一般', 'admin' => '管理者']) ?>
</form>
```

**Validation Attributes (Model properties) and equivalent chain methods**

| Attribute | Chain method | Rule |
|-----------|-------------|------|
| `#[Required]` | `required()` | Non-empty |
| `#[Email]` | `email()` | Valid email |
| `#[MaxLength(n)]` | `maxLength(n)` | ≤ n chars |
| `#[MinLength(n)]` | `minLength(n)` | ≥ n chars |
| `#[Numeric]` | `numeric()` | Numeric value |
| `#[Integer]` | `integer()` | Integer value |
| `#[Url]` | `url()` | Valid URL |
| `#[Date]` | `date()` | Parseable date |
| `#[Regex('p')]` | `regex('p')` | Matches pattern |
| `#[In('a','b')]` | — | One of listed values |
| `#[NotIn('a','b')]` | — | Not in listed values |
| `#[Between(min,max)]` | `between(min,max)` | Numeric range |
| `#[Confirmed]` | `confirmed()` | Matches `{field}_confirmation` |
| `#[SameAs('other')]` | — | Matches another field |
| `#[Custom('method')]` | `with()` | Calls method on Model/DTO |

**Custom Validation (Model Method Pattern)**
`#[Custom]` を使用すると、Model 自身のメソッドでバリデーションを行えます。
- `Form::validate()` の開始時に `preSyncModel()` が走り、全プロパティが最新の入力値で更新されます。
- そのため、メソッド内では `$this->password` のように他のフィールドを参照できます。
- メソッドは `true` (成功) または `string` (エラーメッセージ。`:field` 置換あり) を返すべきです。

```php
#[Custom('checkUnique')]
public string $email;

public function checkUnique($val) {
    return User::exists($val) ? ':field は既に使用されています' : true;
}
```

Validation error messages can be overridden in `app.ini`:
```ini
[validation]
required  = ":field は必須です。"
maxLength = ":field は :len 文字以内で入力してください。"
```

---

## Database — Entity (ActiveRecord)

```php
// Model definition
class Post extends Entity {
    protected static ?string $table = 'posts'; // optional; default = snake_plural of class name
    public ?int    $id         = null;
    public string  $title      = '';
    public string  $body       = '';
    public int     $published  = 0;
    public ?string $created_at = null;
}

// Retrieval
$post  = Post::find(1);                          // by PK, returns ?Post
$post  = Post::first('slug', $slug);             // 条件1件取得, returns ?Post（型が確定するので推奨）
$posts = Post::all();                            // Result<Post> ← Resultオブジェクト（links()等が使える）
$posts = Post::where('published', 1)
             ->orderBy('created_at', 'DESC')
             ->all();                            // array<Post> ← 生配列（Result ではない）

// Pagination
$result = Post::where('published', 1)->page(Request::getInt('page', 1), 20);
echo $result->links();                           // HTML pagination links

// Create / Update / Delete
$id = Post::create(['title' => 'Hello', 'body' => '...', 'created_at' => date('Y-m-d H:i:s')]);
Post::updateById(1, ['title' => 'Updated']);
Post::deleteById(1);

// Instance save (INSERT if no PK, UPDATE if PK set)
$post = new Post();
$post->title = 'New';
$post->save();

// Query builder operators: =  !=  <>  >  <  >=  <=  LIKE  IN  BETWEEN  IS  IS NOT
Post::where('id', '!=', 5)->where('title', 'LIKE', '%foo%')->all();
Post::where('status', ['draft', 'review'])->all();  // IN via array

// Entity::save() は全プロパティを UPDATE する。特定フィールドのみ更新するなら updateById() を使う
Post::updateById($id, ['title' => 'Updated']);   // フィールド指定更新（推奨）
$post->title = 'Updated'; $post->save();         // 全フィールドUPDATE

// firstOrCreate — 存在すれば取得、なければ作成
$user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $hash]);
```

---

## Database — Raw / Facade

```php
// SELECT → Result (iterable, countable)
$rows = Db::select("SELECT * FROM posts WHERE id > :id", ['id' => 10]);

// INSERT / UPDATE / DELETE → affected rows
Db::execute("DELETE FROM sessions WHERE exp < :now", ['now' => time()]);

// Query builder without Entity
$rows = Db::table('posts')->where('published', 1)->orderBy('id','DESC')->all();

// Transaction
Db::transaction(function($pdo) {
    Db::table('accounts')->where('id', 1)->update(['balance' => 500]);
    Db::table('logs')->insert(['action' => 'transfer']);
});
```

---

## Request — メソッド使い分け

| 取得元 | 文字列 | 数値 | 真偽値 |
|--------|--------|------|--------|
| GET のみ | `Request::get($k)` | `Request::getInt($k)` | `Request::getBool($k)` |
| POST のみ | `Request::post($k)` | `Request::postInt($k)` | `Request::postBool($k)` |
| GET + POST | `Request::param($k)` | `Request::paramInt($k)` | — |
| JSON / GET / POST | `Request::input($k)` | — | — |

- **通常フォーム**: `Request::post()` / `Request::postInt()`
- **API (JSON body)**: `Request::input()` — `Content-Type: application/json` のとき `php://input` から読む
- **検索クエリなど GET パラメータ**: `Request::get()` / `Request::getInt()`

---

## HTTP Attributes

```php
use Fzr\Attr\Http\{Csrf, Api, Roles, Guest, AllowCors, AllowCache, AllowIframe, IsReadOnly, IpWhitelist};

#[Csrf]             // Verify CSRF token on POST/PUT/DELETE
#[Roles('admin')]   // Require role. Supports "admin", ["a","b"]
#[Guest]            // Only for non-logged-in users. Redirects to "/" if logged in.
#[Guest(redirect: "/dashboard")] // Custom redirect for logged-in users
#[Auth(redirect: "/login-info")] // Custom redirect for non-logged-in users
#[IpWhitelist]      // Uses "ip.whitelist" from app.ini/env
#[IpWhitelist('gcs:ips/admin.txt')] // Load list from Storage (GCS)
#[IpWhitelist('/abs/path/ips.txt')]  // Load list from local physical file
#[IpWhitelist('192.168.1.0/24')]     // Literal CIDR range
#[Api]              // JSON response mode
#[AllowCors]        // Standard CORS (Uses app.ini or Defaults)
#[AllowCors('https://example.com')] // Single origin
#[AllowCors(['https://a.com', 'https://b.com'], credentials: false)] // Multiple origins
#[AllowCache(3600)] // Set Cache-Control max-age
#[AllowIframe]      // Allow embedding in iframes
#[IsReadOnly]       // Block POST/PUT/DELETE
```

### 属性の解決優先順位 (Resolution Priority)

`Engine.php` は以下の順序で属性を探索し、**最初に見つかったもの（最も具体的なもの）を適用します。** これにより、クラス全体の設定を特定のメソッドで上書きするといった柔軟な制御が可能です。

| 優先順位 | 対象 | 例 | 備考 |
| :--- | :--- | :--- | :--- |
| **1 (最優先)** | **Dispatch Method** | `_post_save()`, `_ajax_index()` | 実際に呼び出されるハンドラ |
| **2 (中)** | **Action Method** | `save()`, `index()` | ルーティング上のベースメソッド |
| **3 (低)** | **Controller Class** | `class IndexController` | クラス全体のデフォルト設定 |

> [!TIP]
> ロール判定（`#[Roles]`）を行う場合、システムは自動的にログインチェック（`Auth::check()`）も行います。未ログインの場合は 401 Unauthorized になります。

---

## Global Helper Functions

```php
h($str)            // HTML escape (always use in views)
e($str)            // alias for h()
url('path')        // Generate URL respecting base path
env('key', 'def')  // Read config value (INI + env var)
csrf_field()       // <input type="hidden" name="csrf_token" value="...">
csrf_token()       // Raw token string
redirect('url')    // Return redirect Response
view('template')   // Return view Response
collect([...])     // Create Collection (map/filter/pluck etc.)
dd($var)           // Dump and die (debug only)
```

---

## Migrations

Files: `storage/db/migrations/NNN_description.sql` — executed in filename order; each file runs once.

Enable auto-run in `app.ini`:
```ini
[db]
auto_migrate = true
```

SQLite example:
```sql
CREATE TABLE IF NOT EXISTS posts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT    NOT NULL,
    body       TEXT    NOT NULL DEFAULT '',
    published  INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
);
```

---

## Standard Project Layout

```
app/
  app.ini          — Config (DB, storage, session, log, view)
  bootstrap.php    — DB connection + migrate + Cloud Run logger
  controllers/     — *Controller.php files
  commands/        — CLI コマンドファイル（php cli <name> で実行）
  models/          — Entity subclasses
  views/
    @layouts/
      base.php     — Master layout (Render::getContent() injection point)
      debug.php    — Debug toolbar (shown when Context::isDebug())
    {controller}/
      {action}.php

cli                — CLI エントリポイント（php cli <command>）

public/            — Document root
  index.php        — Entry point
  .htaccess        — Rewrite all to index.php
  css/ js/ images/

storage/
  db/migrations/   — *.sql migration files
  log/             — Access and debug logs
  temp/sessions/   — Session files (local mode)
  public/          — Publicly accessible uploaded files
  private/         — Private storage

vendor/fzr/fw/     — Framework core (do not edit)
AI.md              — This AI guide (for the project)
FZR_AI.md          — Fzr framework AI guide (reference)
FZR_README.md      — Fzr framework manual
FZR_ENV.md         — Fzr configuration reference
```

---

## Typical bootstrap.php

```php
<?php
use Fzr\Loader;
use Fzr\Path;
use Fzr\Env;
use Fzr\Db\Db;
use Fzr\Db\Connection;
use Fzr\Logger;

Loader::add(Path::app('models'));

if (getenv('K_SERVICE')) {
    Logger::setOutput('stderr'); // Cloud Run structured logging
}

if (Env::get('db.driver', 'none') !== 'none') {
    Db::addConnection('default', Connection::fromEnv());
    if (Env::getBool('db.auto_migrate', false)) {
        Db::migrate();
    }
}
```

---

## テスト用 DB 差し替え（モック切り替え）

Fzr は DI コンテナを持たないが、`Db::addConnection()` で接続を上書きできるため、テスト時に実 DB を差し替えられる。**追加実装は不要**。

```php
// テスト用に別の SQLite インメモリ DB を差し替える
$testConn = new \Fzr\Db\Connection('default', [
    'driver'     => 'sqlite',
    'sqlitePath' => ':memory:',
]);
\Fzr\Db\Db::addConnection('default', $testConn);

// 以降の Entity / Db 呼び出しはすべてインメモリ DB を使う
Db::migrate();   // マイグレーションをインメモリ DBに適用
$id = Post::create(['title' => 'test']);
assert(Post::find($id) !== null);
```

**仕組み**: `Db::addConnection('default', $conn)` は内部の `$connections['default']` を上書きするだけ。以降 `Entity::query()` が `Db::connection('default')` を取得するため、差し替えが全体に波及する。

**制約**: 静的レジストリなのでテストケース間のリセットが必要。各テストの setUp で再登録するか、tearDown で元の接続を戻す。

---

## Known Pitfalls & Lessons Learned

<!-- AIが実装・解析で発見した注意点をここに追記する -->

- **メソッド名とURLの対応**: `LoginController::_post_index()` は `POST /login`、`LoginController::_post_login()` は `POST /login/login` に対応する。どちらも正当なパターンであり、「正誤」ではなく「どの URL に対応させるか」の設計判断。アクションセグメントを省略した URL では action が `index` になる点に注意。
- **`Render::getData()` の型**: ビュー側で `getData()` を使うと型が `mixed` になる。コントローラで渡した型をコメントで明示するか、`Form::fromModel()` 経由で型付きのまま扱う。
- **`auto_migrate` はデフォルト `false`**: マイグレーションを使う場合は `app.ini` で `auto_migrate = true` に変更すること。
- **`where('id', '!=', $val)` は使用可**: Query ビルダは `!=`, `<>`, `>`, `LIKE` 等の演算子を第2引数で受け取れる。
- **セッション保存パス**: ローカル開発では `storage/temp/sessions/` が存在しないとセッションエラーになる場合がある。ディレクトリを事前に作成すること。
- **Attributeの解決優先順位**: `#[Csrf]`・`#[Auth]` 等の属性は「Dispatchメソッド > Actionメソッド > Class」の順で解決されます。例えば、クラス全体に `#[Auth]` が付いていても、`index()` メソッド自体に属性がなければクラスの設定が適用されますが、もし `_get_index()` に別の属性を付ければそれが優先されます。
- **Store の継承**: 独自のグローバルな状態を管理したい場合は `Store` を継承する。`$_allData` レジストリにより子クラス間でデータは混線しない。
- **`Auth` の拡張性**: `Auth::getId()`, `Auth::getEmail()` は内部的に `Store::get()` を使用しており、`app.ini` の設定（`auth.user_id_name` 等）でキー名を変更可能。
- **`Auth` アトリビュート**: ログイン必須にするには、コントローラまたはメソッドに `#[Auth]` を付ける。

### ログイン機能実装時の注意

- **`logout()` は `Controller` を継承すること**: `AuthController` にすると未ログインユーザーが `/logout` にアクセスした際に 401 → `LOGIN_PAGE` リダイレクトになる。ログアウト処理は認証不要なので通常の `Controller` を使う。
- **`Auth::login()` の第2引数は配列必須**: `Auth::login($user, $user->role)` は型エラー。`Auth::login($user, [$user->role])` のように配列で渡すこと。roles が複数ある場合は `explode(',', $user->roles)` 等で変換する。
- **`LOGIN_PAGE` 定数は Engine が自動定義**: `AuthController` で 401 が発生すると `LOGIN_PAGE` にリダイレクトされる。この定数は Engine 初期化時に `app.ini` の `app.login_page`（デフォルト: `"login"`）から自動定義されるため、開発者が `define()` する必要はない。変更する場合は `app.ini` で設定する。
- **条件付き1件取得は `first()` を使う**: 単純条件なら `User::first('email', $email)` を使うと戻り型が `?static`（= `?User`）として確定し、IDE補完・AI生成どちらも正確に機能する。複雑な条件（複数 where / orderBy 等）では `where()->first()` を使い、`/** @var User|null $user */` を付ける。

---

## Model / Bag / Store — 使い分け

| クラス | ベース | 用途 | 特徴 |
|--------|--------|------|------|
| `Model` | プロパティ定義 | DB行・フォームデータなど構造が決まっているもの | `public ?string $name = null;` のように型・デフォルト値を宣言。`Entity` の基底。 |
| `Bag` | 配列 | キーが実行時に決まる非構造化データ | `$bag->set('key', $val)` で動的に追加可能。`replace()` で全入れ替えできる。 |
| `Store` | 静的レジストリ | リクエスト全体で共有するグローバル状態 | `Auth`, `Session` がこれを継承。インスタンス不要、クラス単位でデータを保持。 |
| `Entity` | `Model` 継承 | DBテーブルへの ActiveRecord マッピング | `save()`, `find()`, `where()` 等が使える。テーブル名は自動推定。 |

**選択フロー**:
1. DBのテーブル行を扱う → `Entity`
2. フォームや API のペイロードで構造が決まっている → `Model`
3. キーが動的・構造不定 → `Bag`
4. リクエスト横断のグローバル状態（認証情報・設定等） → `Store` 継承クラス

---

## データ操作の統一ポリシー (Unified Data Manipulation)

| メソッド | 意味 | `Model` | `Bag` | `Store` |
|---------|------|---------|------------|--------------|
| `merge($data)` / `fill($data)` | **マージ** — 既存を保持しつつ更新 | ✓ | ✓ | ✓ |
| `replace($data)` | **全入れ替え** — 既存を破棄して作り直す | ✗ | ✓ | ✓ |
| `bind($source)` | **スマートマージ** — JSON/Model/Form を問わず抽出して `merge()` | ✓ | ✓ | ✓ |
| `from($source)` | **新規生成**（`Model`/`Bag`） / **全入れ替え**（`Store`） | ✓ | ✓ | ✓ |

- `Model`（プロパティベース）は `replace()` を持たない。プロパティに直接代入するか `Bag` を使う。
- `Store::from()` はインスタンスを返さず `replace()` と同等（静的クラスのため）。

---

## 典型パターン集

### CRUD コントローラ（全体フロー）

```php
class ItemController extends Controller
{
    // 全体を認証必須にする
    public function __before($action, $method) {
        // もしクラス全体に #[Auth] を付けていない場合、ここで Auth::check() してもよいが、
        // 基本はアトリビュート推奨。
    }
    // GET /item — 一覧
    public function index() {
        $items = Item::where('user_id', Auth::getId())->orderBy('id', 'DESC')->all();
        Render::setTitle('一覧');
        Render::setData('items', $items);
        return Response::view('item/index');
    }

    // GET /item/create — 新規フォーム
    public function create() {
        Render::setData('form', Form::from(new Item(), ignore: ['id', 'user_id', 'created_at']));
        return Response::view('item/form');
    }

    // POST /item/create — 新規登録
    #[Csrf]
    public function _post_create() {
        $form = Form::from(new Item(), ignore: ['id', 'user_id', 'created_at']);
        $form->fill(Request::post());
        if (!$form->validate()) {
            $form->flashError();
            return Response::redirect('item/create');
        }
        $item = new Item();
        $form->sync($item);
        $item->user_id = Auth::getId();
        $item->save();
        Message::success('登録しました。');
        return Response::redirect('item');
    }

    // GET /item/edit/{id} — 編集フォーム
    public function edit(int $id) {
        /** @var Item|null $item */
        $item = Item::find($id);
        if (!$item || $item->user_id !== Auth::getId()) return Response::error(404);
        $form = Form::from($item, ignore: ['id', 'user_id', 'created_at']);
        Render::setData('form', $form);
        Render::setData('item', $item);
        return Response::view('item/form');
    }

    // POST /item/edit/{id} — 更新
    #[Csrf]
    public function _post_edit(int $id) {
        /** @var Item|null $item */
        $item = Item::find($id);
        if (!$item || $item->user_id !== Auth::getId()) return Response::error(404);
        $form = Form::from($item, ignore: ['id', 'user_id', 'created_at']);
        $form->fill(Request::post());
        if (!$form->validate()) {
            $form->flashError();
            return Response::redirect("item/edit/{$id}");
        }
        $form->sync($item);
        $item->save();
        Message::success('更新しました。');
        return Response::redirect('item');
    }

    // POST /item/delete/{id} — 削除
    #[Csrf]
    public function _post_delete(int $id) {
        /** @var Item|null $item */
        $item = Item::find($id);
        if (!$item || $item->user_id !== Auth::getId()) return Response::error(404);
        $item->delete();
        Message::success('削除しました。');
        return Response::redirect('item');
    }
}
```

### API エンドポイント（JSON）

```php
// #[Api] でクラス全体を JSON モードに。エラーも自動で JSON になる。
#[Api]
#[Auth]
class ApiItemController extends Controller
{
    // GET /api-item — 一覧
    public function index() {
        $items = Item::where('user_id', Auth::getId())->all();
        return Response::json(['items' => $items]);
    }

    // POST /api-item/store — 登録
    #[Csrf]
    public function store() { }

    public function _post_store() {
        // JSON body は Request::input() で取得
        $name = Request::input('name', '');
        if ($name === '') return Response::json(['error' => 'name is required'], 422);
        $id = Item::create(['name' => $name, 'user_id' => Auth::getId()]);
        return Response::json(['id' => $id], 201);
    }
}
```

### `__before` — 共通前処理（コントローラ内ミドルウェア）

```php
class AdminController extends Controller
{
    private array $stats = [];

    // アクション前に必ず実行される。Response を return すると後続アクションをスキップ。
    public function __before(string $action, string $method): mixed
    {
        // 共通データのロード
        $this->stats = ['users' => User::count()];
        Render::setData('stats', $this->stats);
        return null; // null を返すと通常通り続行
    }
}

---

## Using Fzr/Kit (Utilities)

`fzr/kit` はフレームワークから独立したユーティリティ群です。フォームのクリーニングや日本固有のデータ処理にはこちらを使用します。

### 1. 便利な文字列操作 (Str)
- `Str::cleanString($val)`: 不可視文字（ZWSP等）を自動除去。
- `Str::formatZengin($val)`: 全銀（銀行振込）フォーマットへの変換。
- `Str::sanitizeFile($name)`: ファイル名に使えない記号を安全な全角記号に置換。

### 2. 和暦・日付 (Wareki)
- `Wareki::parse($str)`: 「令和5年」「R5/10/1」などの和暦・略称・漢数字を含む文字列を西暦に復元。

### 3. 配列操作 (Arr)
- `Arr::trimRecursive($array)`: 多次元配列の全要素を全角スペース含めて再帰的にトリム。

これらのクラスは `Fzr\Util` や `Fzr\Date` 名前空間にありますが、`aliases.php` により短いクラス名で利用可能です。
```
