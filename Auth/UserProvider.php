<?php
declare(strict_types=1);

namespace CodeX\Auth;

use CodeX\Model\User;

class UserProvider
{
    public function findById(int $id): ?UserInterface
    {
        $user = User::find($id);
        return $user ?: null;
    }

    public function findByEmail(string $email): ?UserInterface
    {
        $users = User::where('email', $email)->get();
        return $users[0] ?? null;
    }

    public function create(array $data): UserInterface
    {
        $user = new User();
        $user->setName($data['name'])->setEmail($data['email'])->setPassword($data['password'])->setRole($data['role'] ?? 'user');
        $user->save();
        return $user;
    }
}