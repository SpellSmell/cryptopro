<?php


// Routes

$app->get('/healthcheck', \App\Controller::class . ':healthcheck');
$app->get('/certificates', \App\Controller::class . ':certificates');
$app->post('/sign', \App\Controller::class . ':sign');
$app->post('/sign2', \App\Controller::class . ':sign2');
$app->post('/cosign', \App\Controller::class . ':cosign');
$app->post('/verify', \App\Controller::class . ':verify');
$app->post('/unsign', \App\Controller::class . ':unsign');
