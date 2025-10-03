<?php
declare(strict_types=1);

namespace CodeX\Model;

use CodeX\Auth\UserInterface;
use CodeX\Database\Model;

class User extends Model implements UserInterface
{
    public static string $table = 'users';

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRole(): string
    {
        return $this->role ?? 'user';
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        return $this;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }
}