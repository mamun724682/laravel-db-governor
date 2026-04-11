<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Governor — Login</title>
</head>
<body>
    @if (session('error'))
        <p>{{ session('error') }}</p>
    @endif

    <form method="POST" action="{{ route('db-governor.login.submit') }}">
        @csrf
        <label>Email
            <input type="email" name="email" required>
        </label>
        <button type="submit">Login</button>
    </form>
</body>
</html>

