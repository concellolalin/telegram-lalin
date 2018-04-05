<?php

namespace Tg;

require __DIR__ . '/emoji.php';

class TelegramRenderer {

    /**
     * @var \Longman\TelegramBot\Telegram
     */
    private $telegram = null;

    private $container = null;
    
    private $request = null;

    public function __construct($container, $request) {
        $this->container = $container;
        // @var \Longman\TelegramBot\Telegram
        $this->telegram = $this->container->get('telegram'); 
        $this->request = $request;
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
                // Sรณ proceso mesaxes enviadas a unha canle
                if('channel_post' === $update->getUpdateType()) {
                    $message = $update->getUpdateContent();

                    // TODO: facer o filtrado dende a consulta a base de datos
                    /*$condition =  \in_array($message->chat['username'], ['ProbasCultura', 'CulturaLalin']);
                    if(!$condition) {
                        continue;
                    }*/

                    $type = $message->getType();
                    $method = 'get' . str_replace('_', '', ucwords($type, '_'));

                    if(method_exists($this, $method)) {
                        $data = $this->$method($message);

                        if($data !== null) {
                            $output[] = [
                                'html' => $this->wrap($data, $message),
                                'message' => $message,
                            ];
                        }
                    } else {
                        die($method);
                    }
                }

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

        if(isset($matches[1]) && count($matches[1]) > 0) {
            for($i=0; $i<count($matches[1]); $i++) {
                $vals[$matches[1][$i]] = $matches[2][$i]; 
            }

            // Converter a codificación a UTF-8
            $title       = $this->toUtf8($vals['og:title']);
            $description = $this->toUtf8($vals['og:description']);
            
            $html  = '<div class="tg-url" data-uri="' . $url . '">';        
            $html .= '<h1><a href="' . $url . '">' . $title . '</a></h1>';

            if(isset($vals['og:image'])) {
                $html .= '<div class="row">';
                    $html .= '<div class="col-md-4">';
                        $html .= '<a href="' . $url . '"><img src="' . $vals['og:image'] . '" class="img img-responsive" /></a>';
                    $html .= '</div>';                  
                    $html .= '<div class="col-md-8">';
                        $html .= '<p>' . $description . '</p>';
                    $html .= '</div>';      
                $html .= '</div>';
            }

            $html .= '<a href="' . $url . '" class="btn btn-default btn-sm btn-block">Ler m&aacute;is</a>';
            $html .= '</div>';
        } else {
            $html = '<a href="' . $url . '">' . $url . '</a>';
        }        
        
        return $html;
    }

    private function toUtf8($str) {
        if(\mb_detect_encoding($str, 'UTF-8', true) === false) {
            $str = \utf8_encode($str);
        }

        return $str;
    }

    private function getPhoto($message) {
        $photo = null;
        if(($photos=$message->getPhoto()) !== null) {
            $file_id = $photos[2]->file_id;
            $response2 = \Longman\TelegramBot\Request::getFile(['file_id' => $file_id]);
            if ($response2->isOk()) {
                /** @var File $photo_file */
                $photo_file = $response2->getResult();                
                \Longman\TelegramBot\Request::downloadFile($photo_file);

                // FIXME: ollo ao path
                $imgSrc = $this->request->getUri()->getBasePath() . '/files/' . $photo_file->getFilePath();
                $photo  = '<a href="' . $imgSrc . '" target="_top">';
                $photo .= '<img src="' . $imgSrc . '" class="img img-responsive" />';
                $photo .= '</a>';
            }
        }
        return $photo;
    }
    
    private function wrap($html, $message) {
        $wrapper = '<div class="tg-wrapper %s"><a href="https://telegram.me/%s" class="channel">%s</a><div>%s</div><div class="date">%s</div></div>';
        
        return sprintf(
            $wrapper, 
            $message->getType(),
            $message->chat['username'],
            $message->chat['title'],
            $html,
            date('H:i', $message->date)
        );
    }

}