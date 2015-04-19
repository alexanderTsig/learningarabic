<?php

# This application uses the Slim micro-framework and the Twig template engine.
# Composer was used to construct the initial application skeleton, as
# described here:
#
# http://www.slimframework.com/read/skeleton-application)

require_once '../vendor/autoload.php';
require_once 'class/autoload.php';
require_once 'amember4/library/Am/Lite.php';

# Initialize the Slim framework

$app = new \Slim\Slim([
    'view' => new \Slim\Extras\Views\Twig(),
    'templates.path' => '../templates',
    'log.level' => 4,
    'log.enabled' => true,
    'log.writer' => new \Slim\Extras\Log\DateTimeFileWriter([
        'path' => '../logs',
        'name_format' => 'y-m-d'
            ])
        ]);

# Enforce conditions for route parameters

\Slim\Route::setDefaultConditions([
    'level' => '\d+',
    'lesson' => '\d+',
    'gender' => '(M|F)',
    #'image'  => '.+\.(png|jpg)',
    'width' => '\d+',
    'height' => '\d+'
]);

$user = \PTA\User::getInstance();

# Implement a hook to redirect users who haven't signed in, or those with an expired membership

$app->hook('slim.before', function() use ($app, $user) {
    $request_path = $app->request()->getPathInfo();

    # Do not allow for exam result submissions to be thwarted by an expired aMember session
    if ($app->request()->isPost() && preg_match('/^\/level\/\d+\/\d+\/exam$/', $request_path) != 0)
        return;

    if ($user->isLoggedIn() && !$user->isMembershipValid()) {
        // Redirect the user to the signup page unless dealing with an XHR request for user data
        if (!($app->request()->isGet() && substr($request_path, 0, 9) === '/api/user')) {
            $app->redirect('/amember4/signup');
        }
    } else if (!$user->isLoggedIn() && $request_path !== '/login') {
        $app->redirect('/login');
    }
});

$app->hook('slim.after.router', function() use ($app) {
    # Disable caching for all content types other than images
    $response = $app->response();
    if (substr($response['Content-Type'], 0, 6) === 'image/') {
        $response['Cache-Control'] = 'public, max-age=86400';
        $response['Expires'] = gmdate('D, d M Y H:i:s GMT', strtotime('+1 day'));
    } else {
        $response['Pragma'] = 'nocache';
        $response['Cache-Control'] = 'no-cache, no-store, max-age=0, must-revalidate';
        $response['Expires'] = 'Fri, 01 Jan 1990 00:00:00 GMT';
    }
});

# Set options for the Twig template engine

\Slim\Extras\Views\Twig::$twigOptions = [
    'charset' => 'utf-8',
#   'cache'            => realpath('../templates/cache'),
    'auto_reload' => true,
    'strict_variables' => false, # only enable this for debugging!
    'autoescape' => true
];

$twig = $app->view()->getEnvironment();
$twig->addGlobal('remote_ip', $_SERVER['REMOTE_ADDR']);

# Import routes from separate include files

require 'lib/routes.inc.php';
require 'lib/routes-api.inc.php';

# Execute Slim to handle the request

$app->run();
