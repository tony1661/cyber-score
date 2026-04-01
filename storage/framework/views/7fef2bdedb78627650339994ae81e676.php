<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>" />
    <title>Email Exposure Assessment | MeteorTel Security</title>
    <meta name="description" content="Find out if your email has been exposed in a data breach and assess your domain's email security configuration." />
    <script>
        window.appConfig = {
            discoveryCallUrl: <?php echo json_encode(env('DISCOVERY_CALL_URL', 'https://meteortel.com/discovery-call/'), 512) ?>,
        };
    </script>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <div id="app"></div>
</body>
</html>
<?php /**PATH /home/tfernandez/git/cyber-score/resources/views/app.blade.php ENDPATH**/ ?>