<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('login', 'AuthController::loginForm', ['filter' => 'guest']);
$routes->post('login', 'AuthController::login', ['filter' => 'guest']);
$routes->get('register', 'AuthController::registerForm', ['filter' => 'guest']);
$routes->post('register', 'AuthController::register', ['filter' => 'guest']);
$routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

// Endpoint ingest dari script collector lokal (tanpa session auth).
$routes->post('api/router/ingest', 'Api\\RouterIngestController::ingest');

$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'WebController::dashboard');
    $routes->post('dashboard/router/digest', 'WebController::digestRouterDataFromDashboard');

    $routes->get('accounts', 'WebController::accountsIndex');
    $routes->post('accounts/create', 'WebController::createAccount');
    $routes->get('accounts/(:num)', 'WebController::accountDetail/$1');
    $routes->get('accounts/(:num)/history/(:segment)', 'WebController::accountHistory/$1/$2');
    $routes->post('accounts/(:num)/update-name', 'WebController::updateAccountName/$1');
    $routes->post('accounts/(:num)/update-password', 'WebController::updateAccountPassword/$1');
    $routes->post('accounts/(:num)/delete', 'WebController::deleteAccount/$1');
    $routes->post('subscriptions/(:num)/update', 'WebController::updateSubscription/$1');
    $routes->post('subscriptions/(:num)/deactivate', 'WebController::deactivateSubscriptionWorkspace/$1');
    $routes->post('subscriptions/(:num)/renew', 'WebController::renewSubscription/$1');
    $routes->post('subscriptions/(:num)/workspace/create', 'WebController::createWorkspaceFromDeactivated/$1');
    $routes->post('subscriptions/(:num)/plus/update-deactivated', 'WebController::updatePlusAccountFromDeactivated/$1');
    $routes->get('profile', 'WebController::profile');
    $routes->post('profile/update', 'WebController::updateProfile');

    $routes->get('settings', 'WebController::telegramSettings');
    $routes->post('settings/telegram', 'WebController::saveTelegramSettings');
    $routes->post('settings/telegram/test', 'WebController::telegramTest');
    $routes->post('settings/env/save', 'WebController::saveDotEnvFromSettings');
    $routes->post('settings/env/restore-latest', 'WebController::restoreLatestDotEnvBackup');
    $routes->post('settings/hard-reset', 'WebController::hardResetLocalAccountsFromRouter');

    $routes->get('telegram', 'WebController::telegramSettings');
    $routes->post('telegram/settings', 'WebController::saveTelegramSettings');
    $routes->post('telegram/test', 'WebController::telegramTest');
});

$routes->group('api', ['filter' => 'auth'], static function ($routes) {
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

    $routes->post('telegram/test', 'Api\\TelegramController::test');
    $routes->get('telegram/settings', 'Api\\TelegramController::settings');
    $routes->put('telegram/settings', 'Api\\TelegramController::updateSettings');

    $routes->get('router/analytics/summary', 'Api\\RouterAnalyticsController::summary');
    $routes->get('router/analytics/charts', 'Api\\RouterAnalyticsController::charts');
});
