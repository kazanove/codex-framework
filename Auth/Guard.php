<?php
declare(strict_types=1);

namespace CodeX\Auth;

use CodeX\Session\Flash;

class Guard
{
    private ?UserInterface $user = null;
    private UserProvider $provider;

    public function __construct(UserProvider $provider)
    {
        $this->provider = $provider;
        $this->startSession();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->provider->findByEmail($email);
        if ($user && password_verify($password, $user->getPassword())) {
            $this->login($user);
            return true;
        }

        // Задержка при неудачной попытке (защита от брутфорса)
        usleep(100000); // 0.1 секунды
        Flash::put('error', 'Неверные учетные данные.');
        return false;
    }

    public function login(UserInterface $user): void
    {
        $_SESSION['auth_user_id'] = $user->getId();
        $this->user = $user;
    }

    public function logout(): void
    {
        unset($_SESSION['auth_user_id']);
        $this->user = null;
        session_destroy();
    }

    public function user(): ?UserInterface
    {
        $this->check();
        return $this->user;
    }

    public function check(): bool
    {
        if ($this->user) {
            return true;
        }

        if (isset($_SESSION['auth_user_id'])) {
            $this->user = $this->provider->findById($_SESSION['auth_user_id']);
            return $this->user !== null;
        }

        return false;
    }

    public function id(): ?int
    {
        return $this->user?->getId();
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function hasRole(string $role): bool
    {
        return $this->check() && $this->user->getRole() === $role;
    }
}