<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DB Governor — Select Connection</title>
</head>
<body>
    <h1>Select Connection</h1>
    <ul>
        @foreach ($connections as $key => $name)
            <li>
                <a href="{{ route('db-governor.dashboard', ['token' => $token, 'connection' => $key]) }}">
                    {{ $key }}
                </a>
            </li>
        @endforeach
    </ul>
</body>
</html>

