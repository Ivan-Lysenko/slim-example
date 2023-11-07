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
        $existsUsers[] = $newUser;
        
        $encodedUsers = json_encode($existsUsers);
        $this->get('flash')->addMessage('success', 'Пользователь сохранен');

        return $response->withHeader('Set-Cookie', "users={$encodedUsers}; Path=/")->withRedirect("/users");
    }

    $params = ['user' => $newUser, 'errors' => $errors];

    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
});

$app->get('/users/{id}/edit', function ($request, $response, $args) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = array_filter($users, fn ($user) => $user['id'] === $id);
    $user = array_values($user)[0];

    if (count($user) === 0) {
        return $response->withRedirect('users', 404);
    }

    $params = [
        'user' => $user,
        'errors' => []
    ];

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

$app->get('/login', function ($request, $response) {
    $param = ['errors' => []];
    return $this->get('renderer')->render($response, '/users/login.phtml', $param);
});

$app->post('/login', function ($request, $response) {
    $email = $request->getParsedBodyParam('user')['email'];

    if (!$email) {
        $errors['email'] = 'The field is require';
    }

    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    $user = array_filter($users, fn ($user) => $user['email'] === $email);
    $user = array_values($user)[0];

    if ($user) {
        $this->get('flash')->addMessage('success', 'Successful authorization');
        $_SESSION['user'] = [
            'nickname' => $user['nickname'],
            'email' => $user['email'],
            'authorization' => true
        ];
        return $response->withRedirect("/users");
    }

    $errors['auth'] = 'User don`t exists';
    $param = ['errors' => $errors];
    
    return $this->get('renderer')->render($response, '/users/login.phtml', $param);
});

$app->get('/logout', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    
    return $response->withRedirect('/users');
});

$app->run();