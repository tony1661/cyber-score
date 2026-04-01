<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Email Exposure Assessment | MeteorTel Security</title>
    <meta name="description" content="Find out if your email has been exposed in a data breach and assess your domain's email security configuration." />
    @if(env('FAVICON_PATH'))
        <link rel="icon" href="{{ env('FAVICON_PATH') }}" type="{{ env('FAVICON_TYPE', 'image/x-icon') }}" />
    @else
        <link rel="icon" href="/favicon.ico" type="image/x-icon" />
    @endif
    <script>
        window.appConfig = {
            discoveryCallUrl: @json(env('DISCOVERY_CALL_URL', 'https://meteortel.com/discovery-call/')),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <div id="app"></div>
</body>
</html>
