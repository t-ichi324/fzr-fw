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

    public function execute(): int
    {
        $force = in_array('--force', $this->args, true);

        $this->out('');
        $this->out("\033[1;36m Fzr Setup\033[0m  — Interactive Project Initializer");
        $this->out(str_repeat('─', 50));

        // ── 1. ディレクトリ構成 ─────────────────────────────────────
        $this->out('');
        $this->out("\033[33m[1/4] Directory Structure\033[0m");

        $defaultRoot = dirname(getcwd());
        $pathRoot    = $this->ask('Install root (absolute path)', $defaultRoot);

        if ($pathRoot !== $defaultRoot && !str_starts_with($pathRoot, '/') && !str_starts_with($pathRoot, '\\') && !preg_match('/^[A-Z]:/i', $pathRoot)) {
            $pathRoot = $defaultRoot . DIRECTORY_SEPARATOR . $pathRoot;
        }

        $pathPublic  = $this->ask('Public directory name (. = public root)', 'public');
        $pathApp     = 'app';
        $pathDb      = 'db';
        $pathStorage = 'storage';

        // ── 2. プロジェクト設定 ─────────────────────────────────────
        $this->out('');
        $this->out("\033[33m[2/4] Project Setup\033[0m");

        $appName    = $this->ask('App name', 'My Project');
        $lang       = $this->ask('Language', 'ja');
        $timezone   = $this->ask('Timezone', 'Asia/Tokyo');
        $debug      = $this->askBool('Enable debug mode', true);
        $includeDoc = $this->askBool('Include Fzr documentation (README/ENV in project root)', true);

        // ── 3. フレームワーク統合 ───────────────────────────────────
        $this->out('');
        $this->out("\033[33m[3/4] Framework Integration\033[0m");
        $this->out("  \033[2mcomposer : add fzr/fw to composer.json\033[0m");
        $this->out("  \033[2mrelative : require loader.php via relative path\033[0m");
        $this->out("  \033[2mphar     : copy fzr.phar to install root\033[0m");
        $this->out("  \033[2mbundle   : copy fzr.bundle.php (single file) to install root\033[0m");

        $frameworkMode = $this->askChoice(
            'How to load Fzr core?',
            ['composer', 'relative', 'phar', 'bundle'],
            'composer'
        );

        $pathCore = dirname(dirname(__FILE__));

        // ── 4. データベース設定 ────────────────────────────────────
        $this->out('');
        $this->out("\033[33m[4/4] Database\033[0m");

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

        // ── パラメータまとめ ────────────────────────────────────────
        $params = [
            'app_name'       => $appName,
            'lang'           => $lang,
            'timezone'       => $timezone,
            'debug_mode'     => $debug,
            'include_doc'    => $includeDoc,
            'path_root'      => $pathRoot,
            'path_app'       => $pathApp,
            'path_db'        => $pathDb,
            'path_public'    => $pathPublic,
            'path_storage'   => $pathStorage,
            'path_core'      => $pathCore,
            'framework_mode' => $frameworkMode,
            'db_driver'      => $dbDriver,
            'db_host'        => $dbHost,
            'db_port'        => $dbPort,
            'db_database'    => $dbDatabase,
            'db_username'    => $dbUsername,
            'db_password'    => $dbPassword,
        ];

        if (Scaffolder::isInstalled($pathRoot, $pathApp) && !$force) {
            $this->error('Already installed. Use --force to overwrite.');
            return 1;
        }

        $this->out('');
        $this->out('Generating project files...');
        $result = Scaffolder::scaffold($params);

        if (!$result['ok']) {
            $this->error($result['error'] ?? 'Scaffolding failed.');
            return 1;
        }

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

        $this->out("\033[33mStart:\033[0m  cd {$pathRoot}/public && php -S localhost:8080");
        $this->out('');
        return 0;
    }
}
