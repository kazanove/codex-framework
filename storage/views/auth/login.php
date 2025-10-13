@extends('layouts/auth')

@section('title')
Регистрация
@endsection

@section('content')
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="logo">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            <h1>Создайте аккаунт</h1>
            <p>Заполните форму, чтобы зарегистрироваться</p>
        </div>

        @if(flash('error'))
        <div class="alert alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            {{ flash('error') }}
        </div>
        @endif

        @if(flash('success'))
        <div class="alert alert-success" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-5.93c3.5 3.5 6.2 8.5 6.2 14.7z"/>
            </svg>
            {{ flash('success') }}
        </div>
        @endif

        <form method="POST" action="{{ url('register') }}" class="login-form" novalidate>
            @csrf
            <div class="form-group">
                <label for="name" class="form-label">Имя</label>
                <div class="input-wrapper">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-5.356-3.893A2 2 0 0 0 15 13c0-3.085-2.47-5.5-5.5-5.5S4 9.915 4 13a2 2 0 0 0 .894 1.605C5.5 15.1 6.2 16.1 7 17v4"/>
                    </svg>
                    <input
                            type="text"
                            id="name"
                            name="name"
                            required
                            autocomplete="name"
                            class="form-input"
                            placeholder="Введите ваше имя"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <div class="input-wrapper">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            autocomplete="email"
                            class="form-input"
                            placeholder="Введите ваш email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Пароль</label>
                <div class="input-wrapper">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            class="form-input"
                            placeholder="Введите пароль"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Подтверждение пароля</label>
                <div class="input-wrapper">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            class="form-input"
                            placeholder="Подтвердите пароль"
                    >
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span>Зарегистрироваться</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12,5 19,12 12,19"/>
                    </svg>
                </button>
            </div>
        </form>

        <div class="login-footer">
            <p>Уже есть аккаунт? <a href="{{ url('login') }}">Войти</a></p>
        </div>
    </div>
</div>
@endsection