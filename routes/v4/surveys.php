<?php

// Forms
$router->group([
    'namespace' => 'Surveys',
    'prefix' => 'surveys',
    'middleware' => ['scope:forms', 'expiration']
], function () use ($router) {
    // Public access
    $router->get('/', 'SurveyController@index');
});

// Restricted access
$router->group([
    'namespace' => 'Surveys',
    'prefix' => 'surveys',
    'middleware' => ['auth:api', 'scope:forms']
], function () use ($router) {
    $router->post('/', 'SurveyController@store');
});
