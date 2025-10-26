@extends($activeTemplate . 'layouts.app')
@section('app-content')
    <div class="dashboard position-relative">
        <div class="dashboard__inner flex-wrap">
            @include('Template::partials.sidebar')
            <div class="dashboard__right">
                <div class="container-fluid p-0">
                    @include('Template::partials.auth_header')
                    @stack('topbar_tabs')
                    <div class="dashboard-body">
                        @if (request()->routeIs('user.inbox.list'))
                            <div class="text-end d-flex justify-content-end gap-3 align-items-center mb-2">
                                <span class="filter-icon"> <i class="fas fa-stream"></i> </span>
                                <span class="user-icon"><i class="fa-regular fa-circle-user"></i></span>
                            </div>
                        @endif
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
