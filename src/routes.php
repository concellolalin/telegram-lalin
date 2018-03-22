<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/', function (Request $request, Response $response, array $args) {
    // Sample log message
    //$this->logger->info("Slim-Skeleton '/' route");

    $messages = [];

    return $this->view->render($response, 'messages.html', ['messages' => $messages]);
})->setName('home');

// Recuperar as actualizacións
// TODO: non renderizar nada 
$app->get('/update/{token}', function (Request $request, Response $response, array $args) {
    // Sample log message
    //$this->logger->info("Slim-Skeleton '/' route");

    $token = $this->get('settings')['token'];
    if($token !== $args['token']) {
        return $response->withStatus(403);
    }     

    $renderer = new Tg\TelegramRenderer($this);
    $output = $renderer->batch();


    // if ($server_response->isOk()) {
    //     $update_count = count($server_response->getResult());
    //     echo date('Y-m-d H:i:s', time()) . ' - Processed ' . $update_count . ' updates';
    // } else {
    //     echo date('Y-m-d H:i:s', time()) . ' - Failed to fetch updates' . PHP_EOL;
    //     echo $server_response->printError();
    // }

    // return $response->withJson([
    //     'output' => $output
    // ]);

    return $this->view->render($response, 'messages.html', [
        'messages' => $output,
    ]);
})->setName('update');

