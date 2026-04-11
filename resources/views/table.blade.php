<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>DB Governor — {{ $table }}</title></head>
<body>
<h1>Table: {{ $table }}</h1>
<table>
    <thead>
        <tr>
            @foreach ($columns as $col)
                <th>{{ $col['name'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>

