<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senha Reset</title>
</head>
<body>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li style="color: red;">{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form action="{{ url('/password/reset') }}" method="POST">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email:</label>
        <input type="email" name="email" value="{{ old('email', request()->get('email')) }}" required>

        <label for="password">Nova Senha:</label>
        <input type="password" name="password" required>

        <label for="password_confirmation">Confirme sua senha:</label>
        <input type="password" name="password_confirmation" required>

        <button type="submit">Enviar</button>
    </form>
</body>
</html>
