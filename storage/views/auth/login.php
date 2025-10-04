@extends('layouts/app')

@section('content')
<div class="auth-form">
    <h2>Вход</h2>
    <form method="POST" action="{{ url('login') }}">
        @csrf
        <div class="1form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Войти</button>
    </form>
</div>
@endsection