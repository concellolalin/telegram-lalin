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
                        //die($method);
                        echo($method . '<br/>');
                    }
                }

            }
        } catch (Longman\TelegramBot\Exception\TelegramException $e) {

        }    

        // Gardar na base de datos para amosar despois
        if(count($output) > 0) {
            $this->save2db($output);
        }
        
        return $output;
    }

    public function getTitle($channel) {
        $dbCfg = $this->container->get('settings')['db'];

        $db = new \PDO($dbCfg['dsn'], $dbCfg['user'], $dbCfg['password']);
        $sql = 'SELECT `title` FROM chat WHERE LOWER(`username`) LIKE LOWER(:username) LIMIT 0, 1';

        $statement = $db->prepare($sql);
        $statement->execute([
            ':username' => $channel,
        ]);

        $title = $statement->fetch();

        $statement = null;
        $db = null;

        return utf8_encode($title['title']);
    }

    public function getMessages($channel) {
        $messages = [];

        $dbCfg = $this->container->get('settings')['db'];

        $db = new \PDO($dbCfg['dsn'], $dbCfg['user'], $dbCfg['password']);
        $sql = 'SELECT `message_id`, `chat_username` AS username, `chat_title` AS title, `date`, `html` ' . 
            'FROM message_rendered WHERE LOWER(`chat_username`) LIKE LOWER(:username) ' .
            'ORDER BY `date` DESC, `message_id` DESC ' . 
            'LIMIT 0, 20';

        $statement = $db->prepare($sql);
        $statement->execute([
            ':username' => $channel,
        ]);

        $messages = $statement->fetchAll();     
        $messages = array_reverse($messages);   

        $statement = null;
        $db = null;

        return $messages;
    }

    private function save2db($output) {
        // TODO: mellorar conectividade coa base de datos
        // TODO: meter no código do telegramrenderer
        $dbCfg = $this->container->get('settings')['db'];

        $db = new \PDO($dbCfg['dsn'], $dbCfg['user'], $dbCfg['password']);
        $sql = 'REPLACE INTO message_rendered (`message_id`, `chat_username`, `chat_title`, `date`, `html`) VALUES (:id, :username, :title, :date, :html)';
        $statement = $db->prepare($sql);

        foreach($output as $message) {            
            $statement->execute([
                ':id' => $message['message']->message_id,
                ':username' => \strtolower($message['message']->chat['username']),
                ':title' => $message['message']->chat['title'],
                ':date' => date('Y-m-d H:i:s', $message['message']->date),
                ':html' => $message['html'],
            ]);
        }

        $statement = null;
        $db = null;
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
            $html .= '<h1><a href="' . $url . '" target="_top">' . $title . '</a></h1>';

            if(isset($vals['og:image'])) {
                $html .= '<div class="row">';
                    $html .= '<div class="col-md-4">';
                        $html .= '<a href="' . $url . '" target="_top"><img src="' . $vals['og:image'] . '" class="img img-responsive" /></a>';
                    $html .= '</div>';                  
                    $html .= '<div class="col-md-8">';
                        $html .= '<p>' . $description . '</p>';
                    $html .= '</div>';      
                $html .= '</div>';
            }

            $html .= '<a href="' . $url . '" class="btn btn-default btn-sm btn-block" target="_top">Ler m&aacute;is</a>';
            $html .= '</div>';
        } else {
            $html = '<a href="' . $url . '" target="_top">' . $url . '</a>';
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
        $username = \strtolower($message->chat['username']);
        $bot = $this->container->get('settings')['bot'];

        if(($photos=$message->getPhoto()) !== null) {
            $file_id = $photos[2]->file_id;

            // Cada photo coa súa canle
            $this->telegram->setDownloadPath($bot['files'] . '/' . $username);

            $response2 = \Longman\TelegramBot\Request::getFile(['file_id' => $file_id]);
            if ($response2->isOk()) {
                /** @var File $photo_file */
                $photo_file = $response2->getResult();                
                \Longman\TelegramBot\Request::downloadFile($photo_file);

                // FIXME: ollo ao path
                $imgSrc = $this->request->getUri()->getBasePath() . '/files/' . $username . '/' . $photo_file->getFilePath();
                $photo = '<a href="' . $imgSrc . '" target="_top">';
                $photo .= '<img src="' . $imgSrc . '" class="img img-responsive" /></a>';
            }
        }
        return $photo;
    }

    private function getAudio($message) {
        $html = null;
        $username = \strtolower($message->chat['username']);
        $bot = $this->container->get('settings')['bot'];
        
        if(($audio=$message->getAudio()) !== null) {
            $file_id = $audio->getFileId();

            // Cada audio coa súa canle
            $this->telegram->setDownloadPath($bot['files'] . '/' . $username);

            $response2 = \Longman\TelegramBot\Request::getFile(['file_id' => $file_id]);
            if ($response2->isOk()) {
                /** @var File $audio_file */
                $audio_file = $response2->getResult();                
                \Longman\TelegramBot\Request::downloadFile($audio_file);

                $title = $audio->getTitle();

                // FIXME: ollo ao path
                $imgSrc = $this->request->getUri()->getBasePath() . '/files/' . $username . '/' . $audio_file->getFilePath();
                $html  = '<div class="audio">';
                if( strlen(trim($title)) > 0 ) {
                    $html .= '<h1>' . $title . '</h1>'; 
                }
                $html .= '<audio class="width" src="' . $imgSrc . '" controls="controls">';
                $html .= '</audio>';
                $html .= '<p class="media-info">' . $this->formatTime($audio->getDuration()) . ', ' . $this->formatSizeUnits($audio->getFileSize()) . '</p>';
                $html .= '</div>';
            }
        }
        return $html;
    }

    private function getVideo($message) {
        $html = null;
        $username = \strtolower($message->chat['username']);
        $bot = $this->container->get('settings')['bot'];
        
        if(($video=$message->getVideo()) !== null) {
            $file_id = $video->getFileId();

            // Cada audio coa súa canle
            $this->telegram->setDownloadPath($bot['files'] . '/' . $username);

            $response2 = \Longman\TelegramBot\Request::getFile(['file_id' => $file_id]);
            if ($response2->isOk()) {
                /** @var File $video_file */
                $video_file = $response2->getResult();                
                \Longman\TelegramBot\Request::downloadFile($video_file);

                $title = $video->getTitle();

                // FIXME: ollo ao path
                $imgSrc = $this->request->getUri()->getBasePath() . '/files/' . $username . '/' . $video_file->getFilePath();
                $html  = '<div class="video">';
                if( strlen(trim($title)) > 0 ) {
                    $html .= '<h1>' . $title . '</h1>'; 
                }
                $html .= '<video class="width" src="' . $imgSrc . '" controls="controls">';
                $html .= '</video>';
                $html .= '<p class="media-info">' . $this->formatTime($video->getDuration()) . ', ' . $this->formatSizeUnits($video->getFileSize()) . '</p>';
                $html .= '</div>';
            }
        }
        return $html;
    }

    private function formatTime($seconds) {
        if(floor($seconds / 3600) <= 0) {
            return gmdate("i:s", $seconds % 3600);
        }
        return floor($seconds / 3600) . gmdate(":i:s", $seconds % 3600);
    }

    private function formatSizeUnits($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
    
    private function wrap($html, $message) {
        $wrapper = '<div class="tg-wrapper %s"><a href="https://telegram.me/%s" class="channel" target="_top">%s</a><div>%s</div><div class="date">%s</div></div>';
        
        return sprintf(
            $wrapper, 
            $message->getType(),
            \strtolower($message->chat['username']),
            $message->chat['title'],
            $html,
            date('H:i', $message->date)
        );
    }

}