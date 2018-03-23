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

    // TODO: non pasar os par?metros
    $renderer = new Tg\TelegramRenderer($this, $request);
    $output = $renderer->batch();

    $dbCfg = $this->get('settings')['db'];

    $db = new PDO($dbCfg['dsn'], $dbCfg['user'], $dbCfg['password']);
    $sql = 'INSERT INTO message_rendered (`message_id`, `chat_username`, `chat_title`, `date`, `html`) VALUES (:id, :username, :title, :date, :html)';
    $statement = $db->prepare($sql);

    foreach($output as $message) {
        $statement->execue([
            ':id' => $message['message']->message_id,
            ':username' => $message['message']->chat['username'],
            ':title' => $message['message']->chat['title'],
            ':date' => $message['message']->date,
            ':html' => $message['html'],
        ]);
    }

    $statement = null;
    $db = null;

    return $this->view->render($response, 'messages.html', [
        'messages' => $output,
    ]);
})->setName('update');

