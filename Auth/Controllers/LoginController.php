<?php
declare(strict_types=1);
namespace CodeX\Auth\Controllers;

use CodeX\Auth\Guard;
use CodeX\Http\Request;
use CodeX\Http\Response;
use CodeX\Session\Flash;
use CodeX\View;
use Throwable;

class LoginController
{
    /**
     * @throws Throwable
     */
    public function showLoginForm(View $view): string
    {
        return $view->render('auth/login');
    }

    public function login(Guard $auth, Request $request, Response $response): Response
    {
        $email = $request['email'] ?? '';
        $password = $request['password'] ?? '';

        // Валидация
        if (empty($email) || empty($password)) {
            Flash::put('error', 'Пожалуйста, заполните все поля.');
            return $response->redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::put('error', 'Некорректный email.');
            return $response->redirect('/login');
        }

        if ($auth->attempt($email, $password)) {
            Flash::put('success', 'Вы успешно вошли в систему.');
            return $response->redirect('/dashboard');
        }

        Flash::put('error', 'Неверный email или пароль.');
        return $response->redirect('/login');
    }

    public function logout(Guard $auth, Response $response): Response
    {
        $auth->logout();
        Flash::put('info', 'Вы успешно вышли из системы.');
        return $response->redirect('/'); // ← Используем Response
    }
}