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
