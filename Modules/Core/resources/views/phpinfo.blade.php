<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Info - {{ config('app.name') }}</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <style>
        /* Base styles from welcome.blade.php */
        body {
            margin: 0;
            font-family: Figtree, ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            -webkit-text-size-adjust: 100%;
            -moz-tab-size: 4;
            tab-size: 4;
            color: rgb(255 255 255 / 0.5);
            background-color: rgb(0 0 0);
        }

        /* PHPInfo specific styling */
        #phpinfo {
            padding: 1.5rem;
            position: relative;
        }

        #phpinfo table {
            width: 100%;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            overflow: hidden;
            background-color: rgb(24 24 27);
            box-shadow: 0px 14px 34px 0px rgba(0, 0, 0, 0.08);
            transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
            transition-duration: 300ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            border-collapse: collapse;
            border-spacing: 0;
        }

        #phpinfo table:hover {
            --tw-ring-color: rgb(63 63 70);
            color: rgb(255 255 255 / 0.7);
        }

        #phpinfo td, 
        #phpinfo th {
            padding: 0.75rem 1rem;
            background-color: transparent;
            margin: 0;
        }

        #phpinfo th {
            background-color: rgb(39 39 42);
            color: rgb(255 255 255 / 0.7);
            font-weight: 600;
        }

        a {
            color: inherit;
            text-decoration: none;
            font-weight: inherit;
            font-size: inherit;
        }

        #phpinfo h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0;
            color: rgb(255 255 255 / 0.7);
        }

        #phpinfo h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 1.5rem 0;
            color: rgb(255 255 255 / 0.7);
        }

        #phpinfo hr {
            border: 0;
            border-top: 1px solid rgb(63 63 70);
            margin: 2rem 0;
        }

        #phpinfo .center {
            text-align: center;
        }

        #phpinfo .v {
            color: rgb(255 255 255 / 0.5);
        }

        #phpinfo .e {
            background-color: rgb(39 39 42);
            color: rgb(255 255 255 / 0.7);
            font-weight: 600;
            width: 300px;
        }

        /* Header section styling */
        .header {
            padding: 1.5rem;
            background-color: rgb(24 24 27);
            border-bottom: 1px solid rgb(63 63 70);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header a {
            color: rgb(255 255 255 / 0.7);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: color 0.2s;
        }

        .header a:hover {
            color: rgb(255 255 255);
        }

        /* Container max-width */
        .container {
            max-width: 80rem;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <a href="{{ url('/') }}">← Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div id="phpinfo">
            @php
                ob_start();
                phpinfo();
                $pinfo = ob_get_contents();
                ob_end_clean();
                
                // Estrai solo il contenuto del body
                echo str_replace(
                    "module_Zend Optimizer",
                    "module_Zend_Optimizer",
                    preg_replace(
                        '%^.*<body>(.*)</body>.*$%ms',
                        '$1',
                        $pinfo
                    )
                );
            @endphp
        </div>
    </div>
</body>
</html>