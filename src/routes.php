<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    //$this->logger->info("Slim-Skeleton '/' route");

    return $response->withStatus(403);    
})->setName('home');


$app->get('/messages/{channel}[/{width}]', function (Request $request, Response $response, array $args) {
    $bot = $this->get('settings')['bot'];
    $channel = $args['channel'];
    if(!\in_array($channel, $bot['channels_allow'])) {
        return $response->withStatus(403);
    }     

    $messages = [];

    $renderer = new Tg\TelegramRenderer($this, $request);
    $messages = $renderer->getMessages($channel);
    $title = $renderer->getTitle($channel);    

    $values = [
        'title' => $title,
        'messages' => $messages,        
    ];

    if(isset($args['width']) && is_numeric($args['width']) && ($args['width'] > 100 && $args['width'] < 2048)) {
        $values['width'] = $args['width'];
    }

    return $this->view->render($response, 'messages.html', $values);
})->setName('messages');

// Recuperar as actualizacións
$app->get('/update/{token}', function (Request $request, Response $response, array $args) {
    // Sample log message
    //$this->logger->info("Slim-Skeleton '/' route");

    $token = $this->get('settings')['token'];
    if($token !== $args['token']) {
        return $response->withStatus(403);
    }     

    // TODO: non pasar os parámetros
    $renderer = new Tg\TelegramRenderer($this, $request);
    $output = $renderer->batch();

    return $response->withStatus(200);
})->setName('update');

