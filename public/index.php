<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    return $response->write('Welcome to Slim!');
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $users = json_decode(file_get_contents('../data/users.json'), true);
    $messages = $this->get('flash')->getMessages();
    //var_dump($users);exit;
    $filteredUsers = array_filter($users, fn ($user) => str_contains($user['nickname'], $term));
    $params = ['users' => $filteredUsers, 'flash' => $messages];
    //var_dump($params);exit;
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    return $this->get('renderer')->render($response, 'users/new.phtml');
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode(file_get_contents('../data/users.json'), true);
    $filteredUsers = array_filter($users, fn ($user) => $user['id'] === $id);
    if (count($filteredUsers) === 0) {
        return $response->withRedirect('users', 404);
    }
    $params = ['nickname' => 'user-' . $id];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->post('/users', function ($request, $response) {
    $newUser = $request->getParsedBodyParam('user');
    $newUser['id'] = uniqid();
    if (!file_exists('../data/users.json')) {
        $existsUsers = [];
    } else {
        $existsUsers = json_decode(file_get_contents('../data/users.json'), true);
    }
        $existsUsers[] = $newUser;
    file_put_contents('../data/users.json', json_encode($existsUsers));
    $this->get('flash')->addMessage('success', 'Пользователь сохранен');
    return $response->withRedirect('users');
});

$app->get('/courses/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');


$app->run();