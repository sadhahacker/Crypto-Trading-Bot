<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'AdminLTE 4'))</title>

    {{--    @viteReactRefresh--}}
    @vite(['resources/css/main.scss', 'resources/js/main.js'])

    @stack('styles')
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
<div class="app-wrapper">
    <!-- Main Header -->
    @include('layouts.navbar')

    <!-- Main Sidebar -->
    @include('layouts.sidebar')

    <!-- Content Wrapper -->
    <div class="app-main">
        <!-- Content Header -->
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0">@yield('page_title')</h3></div>
                    <div class="col-sm-6">
                        <div class="d-flex justify-content-sm-end justify-content-start align-items-center gap-2 flex-wrap">
                            @hasSection('page_actions')
                                @yield('page_actions')
                            @endif
                            <ol class="breadcrumb mb-0">
                                @yield('breadcrumb')
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="app-content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

    <!-- Main Footer -->
    @include('layouts.footer')
</div>

@stack('scripts')
</body>
</html>
