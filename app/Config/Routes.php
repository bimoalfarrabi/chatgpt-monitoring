<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'WebController::dashboard');

$routes->get('/accounts', 'WebController::accountsIndex');
$routes->post('/accounts/create', 'WebController::createAccount');
$routes->get('/accounts/(:num)', 'WebController::accountDetail/$1');
$routes->post('/accounts/(:num)/delete', 'WebController::deleteAccount/$1');
$routes->post('/subscriptions/(:num)/update', 'WebController::updateSubscription/$1');
$routes->post('/usages/(:num)/update', 'WebController::updateUsage/$1');

$routes->get('/telegram', 'WebController::telegramSettings');
$routes->post('/telegram/settings', 'WebController::saveTelegramSettings');
$routes->post('/telegram/test', 'WebController::telegramTest');

$routes->group('api', static function ($routes) {
    $routes->get('accounts', 'Api\\AccountsController::index');
    $routes->get('accounts/(:num)', 'Api\\AccountsController::show/$1');
    $routes->post('accounts', 'Api\\AccountsController::create');
    $routes->put('accounts/(:num)', 'Api\\AccountsController::update/$1');
    $routes->delete('accounts/(:num)', 'Api\\AccountsController::delete/$1');

    $routes->get('subscriptions', 'Api\\SubscriptionsController::index');
    $routes->get('subscriptions/(:num)', 'Api\\SubscriptionsController::show/$1');
    $routes->post('subscriptions', 'Api\\SubscriptionsController::create');
    $routes->put('subscriptions/(:num)', 'Api\\SubscriptionsController::update/$1');
    $routes->delete('subscriptions/(:num)', 'Api\\SubscriptionsController::delete/$1');

    $routes->post('account-usages/(:num)/update', 'Api\\AccountUsagesController::update/$1');

    $routes->post('telegram/test', 'Api\\TelegramController::test');
    $routes->get('telegram/settings', 'Api\\TelegramController::settings');
    $routes->put('telegram/settings', 'Api\\TelegramController::updateSettings');
});
