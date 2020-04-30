<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

/**
 * API version number
 */
$apiVersion = '4';
$apiBase = 'api/v' . $apiVersion;
$router->group([
    'prefix' => $apiBase,
], function () use ($router) {
    require __DIR__.'/v4/surveys.php';
   }
);