<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> {{ gs()->siteName(__($pageTitle)) }}</title>

    <meta name="P-A-ID" content="{{ config('app.PUSHER_APP_KEY') }}">
    <meta name="P-CLUSTER" content="{{ config('app.PUSHER_APP_CLUSTER') }}">
    <meta name="APP-DOMAIN" content="{{ route('home') }}">

    @include('partials.seo')

    <link href="{{ asset('assets/global/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/global/css/all.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">

    @stack('style-lib')
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/custom-animation.css') }}?v=1">

    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/custom.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/main.css') }}">

    @stack('style')
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/color.php') }}?color={{ gs('base_color') }}">
</head>
@php echo loadExtension('google-analytics') @endphp

<body>
    <div class="preloader">
        <span class="loader"></span>
    </div>
    <div class="body-overlay"></div>

    <div class="sidebar-overlay"></div>

    @yield('app-content')

    @include('Template::partials.cookie')

    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/wow.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/main.js') }}"></script>

    @stack('script-lib')

    <script src="{{ asset('assets/global/js/global.js') }}"></script>
    @php echo loadExtension('tawk-chat') @endphp

    @include('partials.notify')

    @if (gs('pn'))
        @include('partials.push_script')
    @endif

    @stack('script')

<script>
    (function($) {
        "use strict";

        $('.policy').on('click', function() {
            $.get('{{ route('cookie.accept') }}', function(response) {
                $('.cookies-card').addClass('d-none');
            });
        });

        // event when change lang
        $(".langSel").on("click", function() {
            let lang = $(this).data('value');
            window.location.href = "{{ route('home') }}/change/" + lang;
        });

        //show cookie card
        setTimeout(function() {
            $('.cookies-card').removeClass('hide');
        }, 2000);
    })(jQuery);
</script>
</body>

</html>
