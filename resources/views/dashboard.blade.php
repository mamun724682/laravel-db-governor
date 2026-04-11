<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>DB Governor — Dashboard</title></head>
<body>
<h1>Dashboard — {{ $currentConnection }}</h1>
<ul>
    @foreach ($stats as $status => $count)
        <li>{{ $status }}: {{ $count }}</li>
    @endforeach
</ul>
<h2>Tables</h2>
<ul>
    @foreach ($tables as $table)
        <li>
            <a href="{{ route('db-governor.table.show', ['token' => $token, 'connection' => $currentConnection, 'table' => $table]) }}">
                {{ $table }}
            </a>
        </li>
    @endforeach
</ul>
</body>
</html>

