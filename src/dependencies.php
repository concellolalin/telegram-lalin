<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
// $container['renderer'] = function ($c) {
//     $settings = $c->get('settings')['renderer'];
//     return new Slim\Views\PhpRenderer($settings['template_path']);
// };


// Register Twig View helper
$container['view'] = function ($c) {
    $settings = $c->get('settings')['renderer'];

    $view = new \Slim\Views\Twig($settings['templates'], [
        'cache' => $settings['cache'],
    ]);
    
    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new \Slim\Views\TwigExtension($c['router'], $basePath));

    return $view;
};


$container['pdo'] = function ($container) {
    $cfg = $container->get('settings')['db'];
    return new \PDO($cfg['dsn'], $cfg['user'], $cfg['password']);
};

$container['telegram'] = function ($container) {
    $credentials = $container->get('settings')['db'];
    $bot = $container->get('settings')['bot'];

    // Create Telegram API object
    $telegram = new \Longman\TelegramBot\Telegram($bot['api_key'], $bot['username']);

    $telegram->setDownloadPath($bot['files']);
    
    // Enable MySQL
    $telegram->enableMySql($credentials);

    // Establecer límites
    $telegram->enableLimiter();

    return $telegram;
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};
