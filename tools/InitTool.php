<?php

namespace Fzr\Tool;

/**
 * php tool init
 *
 * 対話型プロンプトで設定を収集し、Scaffolder でプロジェクトを初期化します。
 * オプション: --force  既存インストールを上書きする
 */
class InitTool extends ToolBase
{
    use Interact;

    public function description(): string
    {
        return 'Initialize a new Fzr project';
    }

    public function execute(): int
    {
        $force = in_array('--force', $this->args, true);

        $this->out('');
        $this->out("\033[1;36m Fzr Setup\033[0m  — Interactive Project Initializer");
        $this->out(str_repeat('─', 50));

        // ── 1. ディレクトリ構成 ─────────────────────────────────────
        $this->out('');
        $this->out("\033[33m[1/5] Directory Structure\033[0m");

        $defaultRoot = dirname(getcwd());
        $pathRoot    = $this->ask('Install root (absolute path)', $defaultRoot);

        // If not absolute, resolve relative to defaultRoot (parent dir)
        if ($pathRoot !== $defaultRoot && !str_starts_with($pathRoot, '/') && !str_starts_with($pathRoot, '\\') && !preg_match('/^[A-Z]:/i', $pathRoot)) {
            $pathRoot = $defaultRoot . DIRECTORY_SEPARATOR . $pathRoot;
        }

        $pathPublic  = $this->ask('Public directory name (. = public root)', 'public');
        $pathApp     = 'app';
        $pathDb      = 'db';
        $pathStorage = 'storage';

        // ── 2. プロジェクト設定 ─────────────────────────────────────
        $this->out('');
        $this->out("\033[33m[2/5] Project Setup\033[0m");

        $appName  = $this->ask('App name', 'My Project');
        $lang = $this->ask('Language', 'ja');
        $timezone = $this->ask('Timezone', 'Asia/Tokyo');
        $debug    = $this->askBool('Enable debug mode', true);
        $includeDoc = $this->askBool('Include Fzr documentation (README/ENV in doc-fzr/)', true);

        // ── 3. フレームワーク統合 ───────────────────────────────────
        $this->out('');
        $this->out("\033[33m[3/5] Framework Integration\033[0m");
        $this->out("  \033[2mcomposer : add fzr/fw to composer.json\033[0m");
        $this->out("  \033[2mrelative : require loader.php via relative path\033[0m");
        $this->out("  \033[2mphar     : copy fzr.phar to install root\033[0m");
        $this->out("  \033[2mbundle   : copy fzr.bundle.php (single file) to install root\033[0m");

        $frameworkMode = $this->askChoice(
            'How to load Fzr core?',
            ['composer', 'relative', 'phar', 'bundle'],
            'composer'
        );

        $currentCore = dirname(dirname(__FILE__));
        $pathCore = $currentCore;

        // ── 4. データベース設定 ────────────────────────────────────
        $this->out('');
        $this->out("\033[33m[4/5] Database\033[0m");

        $dbDriver = $this->askChoice('DB driver', ['sqlite', 'mysql', 'pgsql', 'none'], 'sqlite');
        $dbHost = $dbPort = $dbDatabase = $dbUsername = $dbPassword = '';
        if ($dbDriver !== 'sqlite' && $dbDriver !== 'none') {
            $defaultPort = $dbDriver === 'pgsql' ? '5432' : '3306';
            $dbHost      = $this->ask('DB host', 'localhost');
            $dbPort      = $this->ask('DB port', $defaultPort);
            $dbDatabase  = $this->ask('DB name', 'fzr_app');
            $dbUsername  = $this->ask('DB username', 'root');
            $dbPassword  = $this->askSecret('DB password');
        }

        // ── 5. デプロイメント設定 ─────────────────────────────────
        $this->out('');
        $this->out("\033[33m[5/5] Deployment\033[0m");

        $preset        = 'standard';
        $deployService = $this->slugify($appName);
        $deployRegion  = 'asia-northeast1';
        $gcpProjectId  = '';
        $gcsBucket     = '';
        $gcsKeyPath    = '';

        $deployCloud = $this->askBool('Deploy to a cloud / FaaS platform?', false);

        if ($deployCloud) {
            $this->out('');
            $this->out("  \033[2m[gcp]     Google Cloud Run\033[0m");
            $this->out("  \033[2m[aws]     AWS Lambda  (future support)\033[0m");
            $this->out("  \033[2m[generic] Other stateless FaaS\033[0m");

            $platform = $this->askChoice('Target platform', ['gcp', 'aws', 'generic'], 'gcp');

            if ($platform === 'gcp') {
                $preset = 'gcp';
                $this->out('');
                $this->out("\033[33m  Google Cloud Configuration\033[0m");

                $gcpProjectId  = $this->ask('PROJECT_ID', 'my-gcp-project');
                $deployService = $this->ask('Service name (Cloud Run)', $deployService);

                $regionMap = [
                    '1' => ['asia-northeast1', 'Tokyo'],
                    '2' => ['us-central1',     'Iowa (cheapest)'],
                    '3' => ['us-east1',         'S. Carolina'],
                ];
                $this->out('');
                $this->out("  Choose region:");
                foreach ($regionMap as $k => [$region, $label]) {
                    $this->out("    [{$k}] {$region}  \033[2m({$label})\033[0m");
                }
                $regionIdx    = $this->ask('Region', '2');
                $deployRegion = $regionMap[$regionIdx][0] ?? $regionMap['2'][0];

                $this->out('');
                $gcsBucket  = $this->ask('GCS Bucket name', $deployService . '-storage');
                $gcsKeyPath = $this->ask('Service Account JSON key path (leave blank = ADC)', '');
            } elseif ($platform === 'aws') {
                $this->out("  \033[33m[i]\033[0m AWS Lambda support is not yet available.");
                $this->out("      Generating stateless-aware config without platform specifics.");
            } else {
                $this->out("  \033[2mGenerating stateless-aware config (no platform specifics).\033[0m");
            }
        }

        // ── 6. パラメータまとめ ────────────────────────────────────
        $params = [
            'preset'           => $preset,
            'app_name'         => $appName,
            'lang'             => $lang,
            'timezone'         => $timezone,
            'debug_mode'       => $debug,
            'include_doc'      => $includeDoc,
            'path_root'        => $pathRoot,
            'path_app'         => $pathApp,
            'path_db'          => $pathDb,
            'path_public'      => $pathPublic,
            'path_storage'     => $pathStorage,
            'path_core'        => $pathCore,
            'framework_mode'   => $frameworkMode,
            'db_driver'        => $dbDriver,
            'db_host'          => $dbHost,
            'db_port'          => $dbPort,
            'db_database'      => $dbDatabase,
            'db_username'      => $dbUsername,
            'db_password'      => $dbPassword,
            'deploy_service'   => $deployService,
            'deploy_region'    => $deployRegion,
            'gcp_project_id'   => $gcpProjectId,
            'gcs_bucket'       => $gcsBucket,
            'gcs_key_path'     => $gcsKeyPath,
        ];

        // ── 7. 多重インストール防止チェック ──────────────────────
        if (Scaffolder::isInstalled($pathRoot, $pathApp) && !$force) {
            $this->error('Already installed. Use --force to overwrite.');
            return 1;
        }

        // ── 8. スキャフォルディング実行 ───────────────────────────
        $this->out('');
        $this->out('Generating project files...');
        $result = Scaffolder::scaffold($params);

        if (!$result['ok']) {
            $this->error($result['error'] ?? 'Scaffolding failed.');
            return 1;
        }

        // ── 9. 完了サマリー ───────────────────────────────────────
        $this->out('');
        $this->success('Project initialized successfully!');
        $this->out("  Root    : {$pathRoot}");
        $this->out("  App     : {$pathRoot}/{$pathApp}");
        $this->out("  Public  : {$pathRoot}/{$pathPublic}");
        $this->out("  Storage : {$pathRoot}/{$pathStorage}");
        $this->out('');

        switch ($frameworkMode) {
            case 'composer':
                $this->out("\033[33mNext:\033[0m  cd {$pathRoot} && composer install");
                break;
            case 'phar':
                $this->out("\033[33mNote:\033[0m  fzr.phar has been copied to {$pathRoot}/");
                break;
            case 'bundle':
                $this->out("\033[33mNote:\033[0m  fzr.bundle.php has been copied to {$pathRoot}/");
                break;
            case 'relative':
                $this->out("\033[33mNote:\033[0m  loader.php is loaded via relative path from {$pathCore}");
                break;
        }

        if ($preset === 'gcp') {
            $this->out('');
            $this->out("\033[33mDeploy:\033[0m  cd {$pathRoot} && bash tools/deploy-gcp.sh");
            if ($dbDriver !== 'sqlite' && $dbDriver !== 'none') {
                $this->out("\033[33mNote:\033[0m   Set DB credentials as Cloud Run secrets or --update-secrets");
            }
            $this->out("\033[2mNote:\033[0m   Sessions use encrypted cookies by default (stateless-safe).");
            $this->out("         Add REDIS_HOST/REDIS_PORT env vars to switch to Redis automatically.");
        } else {
            $this->out("\033[33mStart:\033[0m  cd {$pathRoot}/public && php -S localhost:8080");
        }

        $this->out('');
        return 0;
    }

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
        return trim(preg_replace('/-+/', '-', $text), '-');
    }
}
