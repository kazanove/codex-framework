<?php

declare(strict_types=1);

namespace CodeX\Auth\Controllers;

use CodeX\Auth\Guard;
use CodeX\Auth\UserProvider;
use CodeX\Http\Request;
use CodeX\Http\Response;
use CodeX\Session\Flash;
use CodeX\View;

class RegisterController
{
    public function showRegistrationForm(View $view): string
    {
        return $view->render('auth/register');
    }

    public function register(UserProvider $provider, Request $request, Response $response): Response
    {
        $name = $request['name'] ?? '';
        $email = $request['email'] ?? '';
        $password = $request['password'] ?? '';
        $passwordConfirmation = $request['password_confirmation'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            Flash::put('error', 'Пожалуйста, заполните все поля.');
            return $response->redirect('/register');
        }

        if ($password !== $passwordConfirmation) {
            Flash::put('error', 'Пароли не совпадают.');
            return $response->redirect('/register');
        }

        // Проверяем уникальность email
        if ($provider->findByEmail($email)) {
            Flash::put('error', 'Пользователь с таким email уже существует.');
            return $response->redirect('/register');
        }

        $provider->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        Flash::put('success', 'Регистрация прошла успешно! Теперь вы можете войти.');
        return $response->redirect('/login');
    }
}