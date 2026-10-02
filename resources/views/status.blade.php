<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Status</title>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p>Status: ok</p>
        <p>Date: {{ $date }}</p>
        <nav>
            <a href="/health">Health</a>
            <a href="/up">Up</a>
        </nav>
    </main>
</body>
</html>
