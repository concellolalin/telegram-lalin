<?php

namespace Tg;

require __DIR__ . '/emoji.php';

class TelegramRenderer {

    /**
     * @var \Longman\TelegramBot\Telegram
     */
    private $telegram = null;

    private $container = null;

    public function __construct($container) {
        $this->container = $container;
        // @var \Longman\TelegramBot\Telegram
        $this->telegram = $this->container->get('telegram'); 
    }

    public function batch() {
        $output = [];

        try {
            $server_response = $this->telegram->handleGetUpdates();
            if ($server_response->isOk()) {
                $result = $server_response->getResult();
            } else {
                //echo $server_response->printError();
                // TODO: Log e mensaxe de correo
            }            
    
            foreach($result as $update) {
                if('channel_post' === $update->getUpdateType()) {
                    $message = $update->getUpdateContent();
                    $method = 'get' . str_replace('_', '', ucwords($message->getType(), '_'));

                    if(method_exists($this, $method)) {
                        $output[] = $this->$method($message);
                    } else {
                        die($method);
                    }
                }
                // if('channel_post' === $update->getUpdateType()) {

                //     if(($photos=$update->getUpdateContent()->getPhoto()) !== null) {
                //         $file_id = $photos[2]->file_id;
                //         $response2 = \Longman\TelegramBot\Request::getFile(['file_id' => $file_id]);
                //         if ($response2->isOk()) {
                //             /** @var File $photo_file */
                //             $photo_file = $response2->getResult();
                //             \Longman\TelegramBot\Request::downloadFile($photo_file);
                //         }
                //     }
                // }
            }
        } catch (Longman\TelegramBot\Exception\TelegramException $e) {

        }    
        
        return $output;
    }

    private function getText($message) {
        $text = $message->getText();

        if($this->hasUri($text)) {
            $text = $this->renderUri($text);
        }

        $text = nl2br($text, true);        
        $text = emoji_unified_to_html($text);
        
        // TODO: comprobar se hai URL

        return $text;        
    }

    private function hasUri($text) {
        return preg_match('/http[s]?:\/\/[^ ]+/', strip_tags($text));
    }

    private function renderUri($text) {
        $matches = [];
        preg_match('/(http[s]?:\/\/[^ ]+)/', $text, $matches);

        if(isset($matches[1])) {
            $url = $matches[1];
            // Eliminar entidades HTML
            $url = mb_ereg_replace('(&#[0-9]+;|\s).*?$', '', $url);

            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'allow_redirects' => [
                    'max'             => 3,        // allow at most 10 redirects.
                    'strict'          => true,      // use "strict" RFC compliant redirects.
                    'referer'         => true,      // add a Referer header
                ],
            ]);

            try {
                $response = $client->get($url);
            } catch(\GuzzleHttp\Exception\RequestException $ex) {
                var_dump($url);                
                die();
            }
            /*if($response->getStatusCode() !== 200)  {
                echo $response->getStatusCode() . '<br/>';
                var_dump($url);
                die();
            }*/
            $body = (string)$response->getBody();
            
            $urlRendered = $this->getOpenGraph($body, $url);
            $text = preg_replace('@' . preg_quote($url, '@') . '@', '', $text);

            $text = $this->uri2html($text);
            $text .= $urlRendered;
        }

        return $text;
    }

    private function uri2html($body) {
        $regexp = '/([^"\'])(http[s]?:\/\/[^\s]+)/';
        $body = preg_replace($regexp, '\1<a href="\2">\2</a>', $body);

        return $body;
    }

    private function getOpenGraph($body, $url) {
        $keys = ['og:title', 'og:description', 'og:image'];
        $vals = [];

        $matches = [];
        preg_match_all('/<meta[ ]+property="(og:title|og:description|og:image)"[ ]+content="(.*?)"/i', $body, $matches);

        for($i=0; $i<count($matches[1]); $i++) {
            $vals[$matches[1][$i]] = $matches[2][$i]; 
        }
        
        $html  = '<div class="tg-url" data-uri="' . $url . '">';        
        $html .= '<h1><a href="' . $url . '">' . $vals['og:title'] . '</a></h1>';
        $html .= '<a href="' . $url . '"><img src="' . $vals['og:image'] . '" class="img img-responsive" /></a>';
        $html .= '<p>' . $vals['og:description'] . '</p>';
        $html .= '</div>';        
        
        return $html;
    }

    private function getPhoto($message) {
        if(($photos=$message->getPhoto()) !== null) {
            $file_id = $photos[2]->file_id;
            $response2 = \Longman\TelegramBot\Request::getFile(['file_id' => $file_id]);
            if ($response2->isOk()) {
                /** @var File $photo_file */
                $photo_file = $response2->getResult();
                var_dump($photo_file->getFilePath());die();
                \Longman\TelegramBot\Request::downloadFile($photo_file);
            }
        }
    }

    private function wrap($html) {
        // TODO: empregar 
    }
}