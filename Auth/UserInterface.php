<?php
declare(strict_types=1);

namespace CodeX\Auth;

interface UserInterface
{
    public function getId(): int;
    public function getEmail(): string;
    public function getPassword(): string;
    public function getRole(): string;
}