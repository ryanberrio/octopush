<?php

$app = new Silex\Application();

if (!defined('APPLICATION_ENV')) {
    define(
        'APPLICATION_ENV', getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'
    );
}

if (APPLICATION_ENV == 'dev') {
    $app['debug'] = true;
}

$configFile = 'config.yml';

$app->register(new Providers\ConfigServiceProvider(__DIR__ . "/config/{$configFile}"));

$app->register(new Silex\Provider\ServiceControllerServiceProvider());

$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/Views',
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => $app['config']['database']
));

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.handler' => new \Monolog\Handler\SyslogHandler('octopush'),
    'monolog.logfile' => $app['config']['log.path'],
    'monolog.level' => $app['config']['log.level'],
    'monolog.name' => 'octopush',
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['helpers.session'] = Helpers\Session::getInstance($app);

$app['models.JobMapper'] = $app->share(
        function ($app) {
    return new Models\JobMapper($app['db']);
}
);

$app['services.jenkins'] = $app->share(
        function ($app) {
    return new Services\Jenkins($app['config'], new \Library\HttpRequest(), $app['monolog']);
}
);

$app['services.GitHub'] = $app->share(
        function ($app) {
    return new Services\GitHub($app['config'], new \Library\HttpRequest(), $app['monolog']);
}
);

$app['services.ThirdParty'] = $app->share(
        function ($app) {
    return new Services\ThirdParty($app['config'], new \Library\HttpRequest(), $app['monolog']);
}
);

$app['jobs.controller'] = $app->share(
        function () use ($app) {
    return new Controllers\JobsController($app, $app['config'], $app['models.JobMapper'], $app['monolog']);
}
);

$app['queue.controller'] = $app->share(
        function () use ($app) {
    return new Controllers\QueueController($app, $app['models.JobMapper'], $app['services.jenkins'], $app['jobs.controller'], $app['monolog']);
}
);

$app->register(new Gigablah\Silex\OAuth\OAuthServiceProvider(), array(
    'oauth.services' => array(
        'GitHub' => array(
            'key' => $app['config']['github_key'],
            'secret' => $app['config']['github_secret'],
            'scope' => array('user'),
            'user_endpoint' => 'https://api.github.com/user'
        )
    )
));

$app->register(new Silex\Provider\FormServiceProvider()); // for CSRF tokens

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern' => '^/',
            'anonymous' => true,
            'oauth' => array(
                'failure_path' => '/login_error',
                'with_csrf' => true,
            ),
            'logout' => array(
                'logout_path' => '/logout',
                'with_csrf' => true
            ),
            'users' => new Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider()
        )
    )
));
