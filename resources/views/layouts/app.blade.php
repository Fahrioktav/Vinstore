<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>@yield('title')</title>
    @vite('resources/css/app.css')
</head>
<body>
    <main>
        <head>
            <x-navbar/>
        </head>
        @yield('content')
    </main>
    
</body>
</html> 