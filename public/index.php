<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

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
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    return $response->write('Welcome to Slim!');
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    //var_dump($_COOKIE);exit;
    $messages = $this->get('flash')->getMessages();

    $filteredUsers = array_filter($users, fn ($user) => str_contains($user['nickname'], $term));
    $params = ['users' => $filteredUsers, 'flash' => $messages];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) {
    
    $params = [
        'user' => [],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $filteredUsers = array_filter($users, fn ($user) => $user['id'] === $id);
    if (count($filteredUsers) === 0) {
        return $response->withRedirect('users', 404);
    }
    $params = ['nickname' => 'user-' . $id];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->post('/users', function ($request, $response) {
    $newUser = $request->getParsedBodyParam('user');
    $errors = [];

    if (!$newUser['nickname']) {
        $errors['nickname'] = 'The field is require';
    }

    if (!$newUser['email']) {
        $errors['email'] = 'The field is require';
    }

    if (count($errors) === 0) {
        
        $newUser['id'] = uniqid();
        $existsUsers = json_decode($request->getCookieParam('users', json_encode([])), true);
        //var_dump($newUser);
        
        $existsUsers[] = $newUser;
        
        $encodedUsers = json_encode($existsUsers);
        //var_dump($encodedUsers);exit;
        $this->get('flash')->addMessage('success', 'Пользователь сохранен');

        return $response->withHeader('Set-Cookie', "users={$encodedUsers}; Path=/")->withRedirect("/users");
    }

    $params = ['user' => $newUser, 'errors' => $errors];

    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
});

$app->get('/users/{id}/edit', function ($request, $response, $args) {
    $id = $args['id'];
    // var_dump($id);exit;
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    // var_dump($id);
    // var_dump($users);exit;
    $user = array_filter($users, fn ($user) => $user['id'] === $id);
    $user = array_values($user)[0];
    // var_dump($user);exit;
    if (count($user) === 0) {
        return $response->withRedirect('users', 404);
    }
    //var_dump($user);exit;
    $params = [
        'user' => $user,
        'errors' => []
    ];
    //var_dump($params);exit;
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = array_filter($users, fn ($user) => $user['id'] === $id);
    $user = array_values($user)[0];
    $userData = $request->getParsedBodyParam('user');
    $errors = [];

    if (!$userData['nickname']) {
        $errors['nickname'] = 'The field is require';
    }

    if (!$userData['email']) {
        $errors['email'] = 'The field is require';
    }

    if (count($errors) === 0) {
        $users = array_map(function ($user) use ($id, $userData) {
            if ($user['id'] === $id) {
                $user['nickname'] = $userData['nickname'];
                $user['email'] = $userData['email'];
            }
            return $user;
        }, $users);

        $encodedUsers = json_encode($users);
        $this->get('flash')->addMessage('success', 'User has been updated');

        return $response->withHeader('Set-Cookie', "users={$encodedUsers}; Path=/")->withRedirect("/users");
    }

    $params = [
        'errors' => $errors,
        'user' => $user
    ];
    //var_dump($params);exit;

    return $this->get('renderer')->render($response->withStatus(422), '/users/edit.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $newUsers = array_filter($users, fn ($user) => $user['id'] !== $id);

    $encodedUsers = json_encode($newUsers);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withHeader('Set-Cookie', "users={$encodedUsers}; Path=/")->withRedirect("/users");
});

$app->get('/courses/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');


$app->run();