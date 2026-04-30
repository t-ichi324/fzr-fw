# CHANGELOG

## [Unreleased]

### Added
- **Core: データアクセス層の Collection 統一**:
    - `Db::select()`, `Query::all()`, `Entity::all()` 等の複数行取得メソッドの戻り値を、従来の `array` から `Collection` オブジェクトへ刷新。
    - メソッドチェーンによる `map`, `filter`, `where`, `pluck` などの直感的なデータ操作をサポート。
    - `isEmpty()`, `isNotEmpty()` による型安全な空判定を導入。
- **Core: ページネーションクラスの刷新 (Paginated)**:
    - `Db\Result` を `Db\Paginated` へリネームし、`Collection` を継承する形に整理。
    - ページ情報（`total`, `lastPage`, `currentPage` 等）を保持しつつ、全件取得結果と同様のリスト操作メソッドを利用可能に。
- **Core: Path / Url 機能の強化**:
    - `Path::get()` にディレクトリ結合と正規化を集約し、OSを問わない堅牢なパス解決を実現。
    - `Url::current()` を追加。現在のリクエストパスに基づいた相対/絶対URLの生成を容易に。
    - `Url::api()` を追加。`app.ini` の `api_prefix` 設定と連動した API エンドポイント生成をサポート。
- **Config: 新しい設定キーの追加**:
    - `app.assets_version`, `api_prefix`, および各種 `path.*` (app, ctrl, view, models, public) を `ENV.md` に定義。

### Changed
- **Form: 入力値の自動 Trim 機能を導入**:
    - `Form` へのデータバインド時に文字列の前後スペースを自動で削除。
    - パスワード関連のフィールドもコピペミス防止のため Trim 対象に含めるよう変更。
    - 再帰的な処理により、ネストされた配列データにも対応。
- **バリデーションルールの命名整理**:
    - 文字列長: `MinLength` / `MaxLength` を `Min` / `Max` へ短縮。
    - 数値大小: `minValue` / `maxValue` を新設。
    - `FormValidator`: `min()`, `max()`, `minValue()`, `maxValue()` メソッドを整備。
    - Attributes: `#[Min]`, `#[Max]`, `#[MinValue]`, `#[MaxValue]` を整備。
    - エラーメッセージのプレースホルダーを `:len` から `:min` / `:max` へ変更。
- **Auth: login() シグネチャの修正**: 第2引数を roles 配列から `bool $regenerate` へ修正。ロール情報はユーザーオブジェクトから自動抽出される仕様に準拠。
- **Core: Result クラスの廃止と移行**: 内部的に `Result` と呼称されていたクラスを `Paginated` (ページネーション) および `Collection` (全件取得) へ完全に移行。

### Fixed
- **Db\Query: chunk() メソッドの無限ループを修正**: `Collection` 移行に伴い、`empty()` での判定が常に false になる問題を `isEmpty()` へ修正.
- **Core: 名前空間の解決を安定化**: `Entity` や `Query` の戻り値型宣言を完全修飾名 (`\Fzr\Collection`) に統一し、IDE や実行環境での名前解決エラーを解消.
- **Documentation: AI 指示書 (AI.md) の最新化**: `Auth::login()` の引数型、`Result` クラスのリネーム、および属性の解決順序に関する記述を現状の実装と一致するように修正.
- **Documentation: README.md の整合性向上**: 削除済みクラス `FileInfo`/`DirectoryInfo` の記述を削除.

### Added
- **HttpClient 実装**: cURLベースの軽量・高機能HTTPクライアントを新規実装。マジックメソッド（`__callStatic`, `__call`）の採用により、静的呼び出し（`HttpClient::get()`）とインスタンスチェーン（`->withToken()->post()`）の両方を簡潔に記述可能。
- **AI-Friendly Improvements (Core)**: 全主要クラス（約30ファイル）に詳細な設計意図、依存関係、および `@method` タグを含む PHPDoc を追加。これにより Cursor や Claude 等の AI によるコード理解と補完精度を劇的に向上。
- **Context: クラウド環境のリクエスト追跡を改善**: Google Cloud Run 等の環境において `X-Cloud-Trace-Context` からトレースIDを自動抽出し、外部API呼び出しログ等との関連付けを正確化。
- **Engine: リフレクションキャッシュの実装**: リクエスト毎のアクション解析オーバーヘッドを削減するため、リフレクション結果のメモリ内キャッシュを導入。
- **Form/Validation: ハイブリッド・バリデーションの導入**:
    - `#[Custom('methodName')]` アトリビュートを新設。Model/DTO 自身のメソッドでバリデーションロジックを記述可能に。
    - `Form::validate()` 実行直前に `preSyncModel()` を行い、入力を Model プロパティに反映。これによりカスタムメソッド内で他のフィールドを容易に参照可能。
    - `FormValidator::with()` メソッドを追加。Controller 内で動的なクロージャ・バリデーションを柔軟に追加可能。
    - バリデーション実行順序を「標準ルール -> カスタムルール」に固定し、カスタムロジック実行時のデータ品質を保証。
- **Refinement: 型一貫性と可読性の向上**:
    - `Form::fromModel()` および `FormValidator` のチェーンメソッドの戻り型を `static` に統一。
    - `Auth::userObject()` の不正確な `@template` を削除し、実態に即した DocBlock へ修正。
    - `Engine.php` 内のネスト `if` ブロックを整形し、可読性を向上。
    - `Form::addError()` 内の冗長な `null` チェック（デッドコード）を削除。

### Added
- **Jwt: JWT ユーティリティをコアに移植** (`src/Jwt.php`, `namespace Fzr`):
  - `fzr/kit` の `Fzr\Auth\Jwt` を `fzr/fw` コアへ吸収し、`Fzr\Jwt` として独立ファイル化。
  - 配置理由: (1) CSRF・IP制限を担う `Security.php` との責務分離、(2) セッションベースの `Auth.php` とステートレス JWT の分離、(3) `Fzr\Jwt` という短い名前空間でコアクラスと同列に並ぶ一貫性、(4) 他クラスへの依存ゼロでバンドル環境でも安全。
  - kit 側の `php-libs/src/Auth/Jwt.php` を削除し二重定義を解消。
  - API: `Jwt::encode()` / `Jwt::decode()` / `Jwt::verify()` / `Jwt::fromBearer()` — 変更なし。

### Fixed
- **Db\Query: マルチDB対応の強化**:
  - `quoteIdentifier()` を強化し、`SUM(col)`・`FIELD(...)` 等の関数式および `*` をクォートせずにそのまま返すように修正。ドット分割前に `(` / スペースを検出することで `SUM(t.col)` 等の複合式の誤爆を防止。
  - `buildSelect`, `count`, `page`, `insert`, `insertMany`, `upsert`, `update`, `delete` の全箇所で `{$this->table}` を `quoteIdentifier()` 経由に変更。`order`, `group` 等の SQL 予約語のテーブル名を MySQL / PostgreSQL / SQLite いずれでも安全に扱えるように修正。
  - `rightJoin()` において SQLite ドライバ使用時に `Logger::warning` を発行。SQLite 3.39.0 未満では RIGHT JOIN が非サポートのため早期に把握できるよう改善。
- **Engine: `__finally` 戻り値オーバーライドの修正**: `inv_inner` メソッドの戻り値処理を改善し、`__finally` フックで戻り値を正常に差し替えられるように修正。
- **Cache: ディレクトリ権限の修正**: キャッシュディレクトリ作成時のパーミッションを `0766` から、実行ビットを含む `0775` に変更し、アクセスエラーを防止。
- **Security: CSRF フィールドのエスケープ不足を修正**: `Security::csrfField()` においてトークンとキーが HTML エスケープされていなかった不整合を修正。
- **FormRender: エラーメッセージのエスケープ漏れを修正**: バリデーションエラー表示時の XSS リスクを排除するため、`error()` メソッドの出力を強制的にエスケープ。
- **Result: ページネーションリンクの結合ロジック改善**: 既存のクエリパラメータがある URL でも `&` で正しく結合し、URL が破損しないように修正。

### Added
- **Attributes 解決優先順位の明文化**:
    - `AI.md` を更新し、属性の解決順序が「Dispatchメソッド > Actionメソッド > Class」であることを明記。
    - ロール判定（`#[Roles]`）時にログインチェック（`Auth::check()`）が自動で行われる仕様をドキュメントに追加。
- **Roles 属性の一本化と柔軟な引数対応**:
    - `Role`（単数形）を廃止し、`Roles`（複数形）に一本化。
    - `Roles` のコンストラクタを改良し、`"admin"`（単一文字列）、`"admin", "manager"`（可変引数）、`["admin", "manager"]`（配列）のすべてを許容するように変更。
- **Guest 属性の追加とリダイレクト指定機能**:
    - `Guest` 属性を新設。ログイン済みユーザーのアクセスを制限し、指定のページ（デフォルトは `/`）へリダイレクト可能。
    - `Auth` 属性において、未ログイン時のリダイレクト先を個別に指定できる `$redirect` プロパティを追加。
- **AllowCors 属性の再設計**:
    - `origin`（複数指定可）、`methods`、`headers`、`credentials` を属性で詳細に指定可能に。
    - リクエストの `Origin` ヘッダに基づいた動的な `Access-Control-Allow-Origin` の返却に対応。
    - プリフライト（OPTIONSリクエスト）への自動応答機能を実装。
- **Attribute 検証ロジックの改善**:
    - `Engine.php` の `verifyAccess()` を改良し、ベースアクション（`save`）と実際の実行メソッド（`_post_save` 等）の両方から属性を収集・検証するように変更。
    - これにより、アトリビュートの記述場所による混乱（ハルシネーション）を解消。
- **Form コンポーネントのオンデマンド・ロード化**:
    - `src/Form.php` を `Form`, `FormValidator`, `FormRender` の3ファイルに分割。
    - バリデーションとレンダリングを遅延読み込みにすることで、API等の不要なリクエストでのオーバーヘッドを削減。
- **Form エントリポイントの一本化**:
    - `Form::from()` と `Form::fromModel()` を `Form::from(mixed $source, array $ignore = [])` に統合。
- **Core の純化**:
    - フレームワークの本来の役割に集中するため、`FileInfo.php` および `DirectoryInfo.php` をコアから削除しスリム化。
- **InitTool のパス解決を改善**:
    - `php tool init` でインストール先（Install root）に相対パス（プロジェクト名のみなど）が入力された場合、デフォルトで表示されている親ディレクトリを基点とした絶対パスに自動解決するように修正。
    - これにより、`fzr/` 内で実行した際に `fzr/fzr-test/` のように意図せずフレームワーク内にプロジェクトが作成される問題を防ぐ。

### Changed
- **フレームワーク構造のスリム化と統合**:
    - 関連するクラスをドメインごとに1ファイルへ統合し、ファイル数を大幅に削減。
    - `src/Env.php`: `Env`, `Config`, `Context` を統合。
    - `src/Path.php`: `Path`, `FileInfo`, `DirectoryInfo` を統合。
    - `src/Model.php`: `Model`, `Bag`, `Store`, `ModelHelper` を統合。
    - `src/Engine.php`: `Engine`, `Loader`, `Route` を統合。
    - これにより、AIが一度に読み込めるコンテキストの密度を高め、開発効率を向上。
- **コードの圧縮と安全性の向上**:
    - `Model.php`, `Path.php` において、単純な getter やガード節を1行に圧縮し、ファイル全体の行数を抑制。
    - 1行の `if` 文であっても、将来の拡張性と安全性を考慮し、波括弧 `{ }` を必須とするスタイルに統一。
- **データコンテナ基底クラスの命名を整理**:
    - `BagModel` を **`Bag`** へ、`StoreModel` を **`Store`** へ改名し、よりシンプルで実態に近い命名に変更。
    - `Auth`, `Session`, `Form` などのフレームワーク各コンポーネントが新しい基底クラスを継承するように更新。
    - `inc/aliases.php` に旧名 (`BagModel`, `StoreModel`) のエイリアスを残し、既存コードへの影響を最小限に抑制。
    - `AI.md` を更新し、AIによる開発・修正時に新しい命名規則が適用されるよう改善。


- **プロジェクトスケルトンのスリム化**:
    - `php tool init` で生成される初期プロジェクトから、ログイン関連のコード（`LoginController`, `User` モデル, `login` ビュー）を削除。
    - 初期状態をよりクリーンにし、ユーザーが必要に応じて機能を追加できる構成に変更。
    - `bootstrap.php` や `AI.md` 内のサンプルコードを、ログイン以外の汎用的な例（`/user/save` 等）に差し替え。
    - `IndexController` と `index.php` に、`Render::setData()` を利用したシステム情報表示のデモコードを追加。
    - `IndexController` に、POSTリクエストのハンドリング例（コメントアウト）を追加。
    - `AuthController` を廃止し、認証チェックを `#[Auth]` アトリビュートに統一。
- **BundleTool のバンドル化エラーを修正**:
    - `Engine.php` で `Attr` や `Model` を `require_once` している箇所が、単一ファイルにバンドル化した際にクラス重複定義エラーを引き起こす問題を修正。
    - `FZR_BUNDLED` 定数による条件付き読み込みを導入し、バンドル環境での動作を安定化。
    - `BundleTool` で生成されるファイルにバンドル時刻を保持する `FZR_BUNDLE_TIME` 定数を追加。
    - `PharTool` で生成されるスタブに `FZR_PHAR` 定数を追加。


### Added
- **AI-Friendly Improvements**:
    - AI（Claude/Cursor等）の学習コストを下げるため、`README.md` にルーティング・ディスパッチ規則の早見表を追加。
    - プロジェクト初期化時 (`php tool init`) に、AIがプロジェクト構造を即座に理解するための `CLAUDE.md` を自動生成するように強化。
    - `doc-fzr/` ディレクトリに配備される配布用ドキュメントに `CLAUDE.md` (フレームワーク参照用) を追加。
    - `app/bootstrap.php` にルーティング規則のクイックリファレンスコメントを追加。
    - `Render::getData()`, `Auth::user()`, `Query::where()` 等の主要メソッドに PHPDoc ヒントを追加し、AIによる型推論の精度を向上。
- **クエリビルダーの柔軟な開始**:
    - `Db::query()` メソッドを追加し、テーブル指定なしでクエリビルダーを開始できるよう対応。
    - `Query::table(string $table)` メソッドを追加し、インスタンス生成後にテーブル名を指定できるよう対応。
    - `Query` クラスの `$table` プロパティを `?string` に変更し、遅延初期化をサポート。
- **トレース機能 (Tracer)**:
    - 実行時間、データベースクエリ、ログ、キャッシュ操作を自動収集する `Tracer` クラスを実装。
    - `app.debug = true` の時、Web画面の末尾にモダンなデザインのデバッグインスペクタを自動挿入する機能を実装。
    - DBクエリの実行時間計測、パラメータの自動マスク、キャッシュのヒット/ミス判定を可視化。
- **Logger の安定性向上**: オートロード中に `Logger` が呼び出された際、`Auth` や `Tracer` の未ロードによる無限再帰や Fatal Error が発生する問題を修正。
- **GCPデプロイの改善**: `deploy-gcp.bat` の先頭にプロジェクトルートへの移動コマンド (`cd /d "%~dp0.."`) を追加し、バッチファイルのあるディレクトリから直接実行可能な利便性を向上。
- **ストレージ・ディレクトリ解決の問題を修正**: `app.ini` 等で相対パス（例: `storage/public`）を指定した場合に、Webリクエスト時に `public/` ディレクトリ配下に作成される不具合を修正。常にプロジェクトルート基準でパスが解決されるように改善。
- **トレース機能 (Tracer) の UI 分離**:
    - `src/Tracer.php` 内に埋め込まれていた HTML/CSS を `@layouts/debug.php` ビュー（スタブ）へ分離。
    - `Engine.php` による自動挿入を廃止し、ベースレイアウト `base.php.stub` から明示的にインクルードする構造へ変更。これにより、デバッグパネルのカスタマイズや表示位置の制御が容易に。
- **Cookieセッションドライバーの安定化**: 出力開始後に `setcookie` が呼ばれて `headers already sent` エラーが発生するのを防ぐため、レスポンス送信直前に `session_write_close()` を呼び出し、セッション（Cookie）を確定させるように改善。
- [Optimization] Call session_write_close() in Response::sendHeaders() to prevent "headers already sent" errors in stateless environments.
- [Optimization] Remove Docker BuildKit cache mounts from Dockerfile.stub and Scaffolder for better compatibility with target build environments.
- [Enhancement] Modernize Debug Tracer with new tabs:
    - "System" tab: Real-time visibility of Context (Stateless/Local), Storage/Session drivers, and DB configuration.
    - "Request" tab: Visualization of Method, URL, GET/POST parameters, and Raw JSON Body.
    - "Error" tab: Aggregates HTTP errors and Exceptions with automatic tab switching on error detection.
- [UX] Persist Debug Tracer's open/close state and active tab selection across page reloads using localStorage.
- [UX] Display HTTP status code in Tracer toggle when errors occur (e.g., "ERROR: 404").
- **scaffold 設定の改善**: GCPプリセット時でもデータベースドライバを選択可能にし、設定の柔軟性を向上。
- **tool init の操作性向上**:
    - フレームワーク統合モードのデフォルトを `composer` に変更し、モダンな開発環境に最適化。
    - フレームワークの `composer.json` を修正し、ライブラリ名 (`fzr/fw`) とオートロード設定を正しく定義。
    - `Scaffolder` で生成される `composer.json` に GitHub のリポジトリ参照を追加し、リモートからのインストールをサポート。
    - `Scaffolder` 内のパス解決バグを修正し、`composer install` が正しく動作するように改善。
    - 対話型プロンプト (`askChoice`) において、選択肢の数値インデックス（1, 2...）や頭文字（c, b...）による入力をサポート。
    - `askBool` において、`1/0`, `on/off` 等の多様な肯定・否定入力をサポート。
- **ドキュメントの配布オプション**:
    - `tool init` 時に、フレームワークのドキュメント (`README.md`, `ENV.md`) をプロジェクトの `doc-fzr/` ディレクトリへ配布するかどうかを選択可能に改善。
- **Windows 環境への最適化**:
    - `deploy-gcp.bat` スタブに `chcp 65001` を追加し、Windows のコマンドプロンプトで日本語が文字化けする問題を修正。


### Removed
- **AWSサポートの廃止 (未検証のため)**:
  - `InitTool` / `Scaffolder` から AWS プリセットを削除。
  - `Storage` クラスから `S3StorageAdapter` および `s3` ドライバを削除。
  - 各種ドキュメント（README, roadmap, app_ini）から AWS 関連の記述を削除。

### Added
- **開発ツール (Tools) とフレームワークコア (Core) の完全な分離**:
  - `src/Command/` 配下の開発者用 CLI ツールをルートの `tools/` ディレクトリへ集約。
  - バンドルファイル (`fzr.bundle.php`) および Phar ファイルから、開発ツール（Scaffolder や Build 関連）を完全に除外。配布物の軽量化とセキュリティの向上を実現。
  - CLI 用の名前空間を `Fzr\Command` から `Fzr\Tool` へ移行。
- **テンプレート外部ファイル化 (Stub System)**:
  - `Scaffolder.php` 内にハードコードされていた初期化用テンプレートを `tools/stubs/*.stub` へ抽出。
  - プロジェクトルートの `Dockerfile`, `.gitignore`, `.htaccess` 等もスタブとして管理し、初期化時にこれらを自動配備するフローへ改善。
- **対話型プロンプトの強化**:
  - `Interact` トレイトを導入し、`InitTool` と `GenTool` で対話型ロジックを共通化。
  - `make:model` コマンドにおいて、設定ファイルが見つからない際に対話形式でパスを指定できるフローを実装。
- **デフォルト設定ファイル名の改名 (env.ini -> app.ini)**:
  - `.env` (環境変数) との役割の混同を避け、`app/` ディレクトリ直下の主設定であることを明確にするため `app.ini` へ変更。
  - `Fzr\Config::DEFAULT_INI` の更新と、全ツール類の追従を完了。
- **クラウドデプロイ自動化 (Deployment Automation)**:
  - `gcp` / `aws` プリセット時に、それぞれの環境に最適化されたデプロイスクリプト（`deploy-gcp.sh / .bat` 等）を自動生成するように強化。
  - `init` 時に、サービス名（自動スラッグ化）とリージョンを最小限の対話で設定可能に。
  - 選択したプリセット以外の不要なスクリプトを生成しないフィルタリング機能を実装。
- **環境自動検知によるログ切り替え**:
  - `Context::isStateless()` による環境判定（Cloud Run等の検知）に基づき、ローカルならファイル、サーバーなら標準出力（stderr）へログを自動で振り分ける挙動を安定化。
- **Docker イメージのオンデマンド最適化**:
  - 選択されたデータベースドライバに応じて、必要な PHP 拡張（pdo_mysql / pdo_pgsql）とシステムライブラリのみをインストールするよう Dockerfile を動的化。
- **ディレクトリ構造の最適化 (Storage/DB Separation)**:
  - データベースディレクトリ (`db/`) を `storage/` からルート直下へ移動し独立化。により、`storage/` ディレクトリを丸ごと `ignore` 可能に。
- **Bug Fix**:
  - `Logger` クラスにおいて名前空間内での `STDERR` 参照により Fatal Error が発生していた問題を修正。

### Changed
- **`php tool init` ウィザードとコアローディングの強化**:
  - `Install root (absolute path)` を最初の質問に移動し、すべての設定の起点として明確化。
  - `relative` / `phar` / `bundle` / `composer` の4つの統合方式をサポート。
  - `phar` / `bundle` 使用時、CORE ファイルが欠落していればその場で自動ビルドして配備する機能を実装。
  - セットアップ中に実際の DB 資格情報を入力し、`.env` を直接生成するフローへ改善。
  - `public/index.php` 内での Fzr のロードを `vendor/autoload.php` から完全に切り離し、Composer なしでも Fzr が確実に動作するように修正。
- **名前空間とツールマッピング**:
  - `tool` CLI エントリポイントを更新し、`Fzr\Tool` 名前空間をサポートするカスタムオートローダーを実装。
  - ツール名を `InitCommand` → `InitTool` のように改名し、エンドユーザー用コマンドと明確に区別。
- **エラーハンドリングとデフォルトビューの導入**:
  - モダンでレスポンシブな 404 / Error ページのデフォルトビューを Scaffolder に追加。
  - `Engine.php` に、ビューファイルすら存在しない場合でも洗練された HTML を出力する強力なフォールバック出力を実装。
- **モダンな初期デザイン (Fzr Modern Interface)**:
  - スマホ対応の三本線メニュー付きヘッダー、実行時間・メモリ使用量を統合したデバッグフッターなど、プレミアム感のある初期デザインを標準搭載。
- **バグ修正**:
  - `fzr.bundle.php` 生成時のクラス読み込み順を `Config -> Path -> Env -> Model -> Controller -> Engine` の順に固定し、循環エラー（Class not found）を解消。

---

## [1.0.0] - 2026-04-20

### Added
- **Unified CLI Architecture**: 全機能を統合した `fzr` コマンドを導入（init, make:model, build）。
- **Modern Scaffolding**: `php tool init` による対話型プロジェクト初期化エンジンを実装。
- **Modern Directory Structure**: `src/` (PSR-4 Classes) と `inc/` (Functional Helpers) を分離したクリーンな構成。
- **Cloud Native Ready**: GCP/AWS プリセットの導入、およびコンテナ環境向けの `stderr` 自動ロギング対応。
- **Phar Compiler**: フレームワークを単一の `fzr.phar` ファイルにパッケージ化するビルドコマンドを実装。
- **Single File Bundler**: 全ソースを一ファイルに連結した `fzr.php` を生成する `bundle` コマンドを実装（IDE補完対応）。
- **Storage Subsystem**: Local, S3, GCS を抽象化して扱えるドライバーベースのストレージ機能。
- **Redis Cache Driver**: GCP/AWS等のステートレス環境向けに、高速にキャッシュを共有できる `RedisCacheDriver` を追加。同時に `Cache::delete()` メソッドを新設。
- **Zero-Config Cloud Runtime**: 実行環境の環境変数 (`K_SERVICE`, `GAE_APPLICATION`, `REDISHOST` 等) を自動検知し、GCP/Cloud Run 上にデプロイされた瞬間に `Cache` および `Session` のバックエンドを自動的に Redis に切り替える機能（ゼロ・コンフィギュレーション）を導入。
- **JSON Payload parsing**: `application/json` での POST リクエスト時に `php://input` を自動デコードする `Request::input()` メソッドを追加。また `Security::verifyCsrf()` が JSON ペイロード内のトークン検証にも対応。
- **Attribute-based Validation**: PHP 8 アトリビュートを利用したモデル生成とバリデーション機能。
- **Native .env Support**: 外部ライブラリ不要の軽量なパース機能を `Env::loadEnv()` として実装。Engine 起動時に自動読み込みをサポート。
- **Internal Key Normalization**: `Env.php` に `normalizeKey()` を実装し、内部管理を一元化。環境変数と INI 設定の完全な互換性を確保。
- **Layered Configuration Support**: `app.ini` 内で `include_ini` を活用することで、固定設定を共有しつつローカル環境で安全に上書きできる構成を Scaffolder テンプレートで提案。

### Changed
- **Env Priority**: 環境変数（OSおよび `.env`）が INI ファイルの設定よりも優先されるように一貫性を確保。
- **Engine**: クラウド環境変数（`REDISHOST`等）の自動検知に加え、起動時の `.env` 自動読み込みに対応。
- **Installation Flow**: セキュリティリスク排除のため、従来の Web ベースセットアップを廃止し、CLI 完全移行。
- **Template System**: ベーステンプレートの配置を `@layouts` ディレクトリへ変更し、視認性を向上。
