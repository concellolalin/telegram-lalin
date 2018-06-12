# Telegram Lalín

[Versión en galego](https://github.com/concellolalin/telegram-lalin/blob/master/README.md)

Integración de los canales de telegram con los servicios web del Concello de Lalín. Widget para incorporar los contenidos de un canal de Telegram en una web.

El proyecto emplea como base el framework [slim](https://www.slimframework.com/) y el [API de Telegram para PHP](https://github.com/php-telegram-bot/core).

Contenidos soportados:

* __Texto__
* __Imágenes__, las imágenes son descargadas del servidor de Telegram.
* __Textos con enlaces__: del primer enlace que se encuentre se descarga la información embebida en tags OpenGraph, también la imagen.
* __Enlaces__: se descarga la información embebida en tags OpenGraph junto con la imagen asociada.
* __Audios__ (EXPERIMENTAL), los audios son descargados del servidor de Telegram y añadidos con la tag _audio_.
* __Vídeos__ (EXPERIMENTAL), los vídeos son descargados del servidor de Telegram y añadidos con la tag _video_.


Contenidos PENDIENTES (TO-DO):

* ~~__Audios__~~.
* ~~__Vídeos__~~.
* __Múltiples imágenes agrupadas en un post__.
* __Notas de voz__
* Soporte para ajustar el ancho del widget
* Crear estrutura de templates para que los desarrolladores puedan personalizar la visualización de los post (text, audio, video, link,...).

Si necesitas funcionalidades adicionales reporta una issue en el repositorio del proyecto.

## Instalación

1. Crear una base de datos MariaDB/MySQL y cargar el fichero `schema.sql`
2. Crear un bot en el Telegramm con BotFather y guardar el API-Key
3. Añadir el bot como administrador en el canal del que se desean recibir las actualizaciones
4. Editar el fichero `src/settings.php.dist` y renombrarlo a `settings.php`. Configurar los valores para la conexión a base de datos, API-KEY de Telegram, nombre del bot, nombres de los canales de los que se aceptan actualizaciones, token interno para consultar actualizaciones empleando cron, ...
5. Configurar el cron para que haga `polling` al servidor de Telegram y reciba las actualizaciones
6. Integrar en el sitio con un iframe con la URL siguiendo el patrón: `http://servidor.com/ruta-ao-codigo/messages/nome_canle1`

Ejemplo de configuración del fichero `settings.php`:

```php
return [
    'settings' => [
        'displayErrorDetails' => false,
        'addContentLengthHeader' => false,

        'renderer' => [
            'templates' => __DIR__ . '/../templates/',
            'cache' => __DIR__ . '/../cache/',
        ],

        'db' => [
            'host' => 'localhost',
            'database' => 'nombre_bbdd',
            'dsn' => 'mysql:host=localhost;dbname=nombre_bbdd',
            'user' => 'usuario',
            'password' => 'contraseña',
        ],

        'bot' => [
            'api_key' => 'api_key_de_bot_father',
            'username' => 'NombreDelBotQueCreamosBot',
            'files' => __DIR__ . '/../public/files',
            'channels_allow' => ['nombre_canal1', 'nombre_canal2']
        ],

        'token' => 'TOKEN-ALEATORIO-CONFIGURADO-EN-SETTINGS.PHP',

        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];

```

Detalle de la línea del cron para hacer peticiones a los servidores de Telegram en la busca de actualizaciones. Configurar la periodicidad con la que se quieren realizar las peticiones.

```
*/15 * * * * wget -O /dev/null http://tg.lalin.gal/update/TOKEN-ALEATORIO-CONFIGURADO-EN-SETTINGS.PHP
```

__NOTA__: el API de telegram también puede recibir las actualizaciones directamente desde Telegram configurando webhooks, sin embargo es necesario que nuestro servidor esté configurado para emplear HTTPS.

## Ejemplo de código iframe

```html
<iframe src="http://servidor.com/messages/nombre_canal1"
    width="100%" height="400" frameborder="0"></iframe>
```

Si necesitamos adaptar el tamaño del contenido podemos establecer en la URL el ancho de los contenidos:

```html
<iframe src="http://servidor.com/messages/nombre_canal1/350"
    width="100%" height="400" frameborder="0"></iframe>
```