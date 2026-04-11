<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>DB Governor — Queries</title></head>
<body>
<h1>Queries — {{ $currentConnection }}</h1>
@foreach ($queries as $query)
    <div>{{ $query->status }} — {{ $query->sql_raw }}</div>
@endforeach
{{ $queries->links() }}
</body>
</html>

