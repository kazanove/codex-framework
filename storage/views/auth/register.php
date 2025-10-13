@extends('layouts/app')

@section('content')
<div class="auth-form">
    <h2>Регистрация</h2>
    <form method="POST" action="{{ url('register') }}">
        @csrf
        <div class="form-group">
            <label for="name">Имя:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="password_confirmation">Подтверждение пароля:</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <button type="submit">Зарегистрироваться</button>
    </form>

    <p><a href="{{ url('login') }}">Уже есть аккаунт? Войдите</a></p>
</div>
@endsection