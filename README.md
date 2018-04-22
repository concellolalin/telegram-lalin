# Telegram Lalín

Integración das canles de telegram cos servizos web do Concello de Lalín. Widget para incorporar os contidos dunha canle de Telegram nunha web. 

O proxecto emprega como base o framework [slim](https://www.slimframework.com/) e o [API de Telegram para PHP](https://github.com/php-telegram-bot/core).

Contidos soportados: 

* __Texto__
* __Imaxes__, ás imaxes son descargadas do servidor de Telegram.
* __Textos con ligazóns__: da primeira ligazón atopada descárgase a información embebida en tags OpenGraph, tamén a imaxe.
* __Ligazóns__: descárgase a información embebida en tags OpenGraph xunto coa imaxe asociada.


Contidos PENDENTES (TO-DO):

* __Audios__.
* __Vídeos__.
* __Múltiples imaxes agrupadas nun post__.

Se precisas das funcionalidades pendentes reporta unha issue no repositorio do proxecto.


## Instalación

1. Crear unha base de datos MariaDB/MySQL e cargar o ficheiro `schema.sql`
2. Crear un bot no Telegramm co BotFather e gardar o API-Key
3. Engadir o bot como administrador da canle da que se desexan recibir as actualizacións
4. Editar o ficheiro `src/settings.php.dist` e renomealo a `settings.php`. Configurar os valores para a conexión a base de datos, API-KEY de Telegram, nome do bot, nomes das canles das que se aceptan actualizacións, token interno para consultar actualizacións empregando cron, ...
5. Configurar o cron para que faga `polling` ao servidor de Telegram e reciba as actualización

Exemplo de configuración do ficheiro `settings.php`:

```php
return [
    'settings' => [
        'displayErrorDetails' => false, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'templates' => __DIR__ . '/../templates/',
            'cache' => __DIR__ . '/../cache/',
        ],

        'db' => [
            'host' => 'localhost',
            'database' => 'nome_basedatos',
            'dsn' => 'mysql:host=localhost;dbname=nome_basedatos',
            'user' => 'usuario',
            'password' => 'contrasinal',
        ],

        'bot' => [
            'api_key' => 'api_key_xerado_por_bot_father',
            'username' => 'NomeDoBotQueCreamosBot',
            'files' => __DIR__ . '/../public/files',
            'channels_allow' => ['nome_canle1', 'nome_canle2']
        ],

        'token' => 'token_aleatorio_de_valores_para_actualizar',

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];

```

Detalle da liña do cron para facer peticións aos servidores de Telegram na procura de actualizacións. Configurar a periodicidade ca que se queren realizar as peticións. 

```
*/15 * * * * wget -O /dev/null http://tg.lalin.gal/update/TOKEN-ALEATORIO-CONFIGURADO-NO-SETTINGS.PHP
```

__NOTA__: o API de telegram tamén pode recibir as actualizacións directamente dende Telegram configurando webhooks, porén é preciso que o noso servidor esté configurado para empregar HTTPS. 