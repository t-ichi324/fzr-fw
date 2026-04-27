<?php

/**
 * Fzr 後方互換エイリアス
 * require 'vendor/fzr/aliases.php'; で既存のグローバルクラス名が使用可能になる
 */

/** エイリアス登録用の安全な関数 */
// Core
if (!class_exists('Engine'))    class_alias(\Fzr\Engine::class, 'Engine');
if (!class_exists('Context'))   class_alias(\Fzr\Context::class, 'Context');
if (!class_exists('Loader'))    class_alias(\Fzr\Loader::class, 'Loader');
if (!class_exists('Config'))    class_alias(\Fzr\Config::class, 'Config');

// HTTP
if (!class_exists('Request'))   class_alias(\Fzr\Request::class, 'Request');
if (!class_exists('Response'))  class_alias(\Fzr\Response::class, 'Response');
if (!class_exists('Route'))     class_alias(\Fzr\Route::class, 'Route');
if (!class_exists('Render'))    class_alias(\Fzr\Render::class, 'Render');
if (!class_exists('Cookie'))    class_alias(\Fzr\Cookie::class, 'Cookie');
if (!class_exists('Session'))   class_alias(\Fzr\Session::class, 'Session');

// Framework
if (!class_exists('Env'))       class_alias(\Fzr\Env::class, 'Env');
if (!class_exists('Logger'))    class_alias(\Fzr\Logger::class, 'Logger');
if (!class_exists('Security'))  class_alias(\Fzr\Security::class, 'Security');
if (!class_exists('Auth'))      class_alias(\Fzr\Auth::class, 'Auth');
if (!class_exists('Path'))      class_alias(\Fzr\Path::class, 'Path');
if (!class_exists('Url'))       class_alias(\Fzr\Url::class, 'Url');
if (!class_exists('Cache'))     class_alias(\Fzr\Cache::class, 'Cache');
if (!class_exists('Message'))   class_alias(\Fzr\Message::class, 'Message');
if (!class_exists('Breadcrumb')) class_alias(\Fzr\Breadcrumb::class, 'Breadcrumb');

// Data
if (!class_exists('Model'))     class_alias(\Fzr\Model::class, 'Model');
if (!class_exists('Bag'))       class_alias(\Fzr\Bag::class, 'Bag');
if (!class_exists('Store'))     class_alias(\Fzr\Store::class, 'Store');
if (!class_exists('BagModel'))  class_alias(\Fzr\Bag::class, 'BagModel');
if (!class_exists('StoreModel')) class_alias(\Fzr\Store::class, 'StoreModel');
if (!class_exists('Collection')) class_alias(\Fzr\Collection::class, 'Collection');
if (!class_exists('Form'))      class_alias(\Fzr\Form::class, 'Form');
if (!class_exists('Storage'))   class_alias(\Fzr\Storage::class, 'Storage');

// Controller
if (!class_exists('Controller')) class_alias(\Fzr\Controller::class, 'Controller');
if (!class_exists('HttpException')) class_alias(\Fzr\HttpException::class, 'HttpException');

// DB
if (!class_exists('Db'))        class_alias(\Fzr\Db\Db::class, 'Db');
if (!class_exists('DbConnection')) class_alias(\Fzr\Db\Connection::class, 'DbConnection');
if (!class_exists('DbQuery'))   class_alias(\Fzr\Db\Query::class, 'DbQuery');
if (!class_exists('Entity'))    class_alias(\Fzr\Db\Entity::class, 'Entity');
if (!class_exists('LiteDb'))    class_alias(\Fzr\Db\LiteDb::class, 'LiteDb');
