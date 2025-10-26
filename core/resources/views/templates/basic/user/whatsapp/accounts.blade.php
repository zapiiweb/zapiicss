@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="dashboard-container">
        <div class="container-top">
            <div class="container-top__left">
                <h5 class="container-top__title">{{ __(@$pageTitle) }}</h5>
                <p class="container-top__desc">@lang('Connect and configure multiple WhatsApp Business accounts from here')
                </p>
            </div>
            <div class="container-top__right">
                <div class="btn--group">
                    @if (gs('whatsapp_embedded_signup'))
                        <button type="button" class="btn btn--base btn-shadow whatsapp-connect" data-bs-toggle="tooltip"
                            title="@lang('Connect your WhatsApp Business account to our platform with embedded signup')">
                            <i class="lab la-whatsapp"></i>
                            @lang('Connect WhatsApp')
                        </button>
                    @endif
                    <a href="{{ route('user.whatsapp.account.add') }}" class="btn btn--base btn-shadow">
                        <i class="las la-plus"></i>
                        @lang('Add New')
                    </a>
                </div>
            </div>
        </div>
        <div class="dashboard-container__body">
            <div class="dashboard-table">
                <div class="dashboard-table__top">
                    <h5 class="dashboard-table__title mb-0">@lang('All Accounts')</h5>
                </div>
                <table class="table table--responsive--lg">
                    <thead>
                        <tr>
                            <th>@lang('Whatsapp Business Name')</th>
                            <th>@lang('Whatsapp Business Number')</th>
                            <th>@lang('Verification Status')</th>
                            <th>@lang('Is Default Account')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($whatsappAccounts as $whatsappAccount)
                            <tr>
                                <td>{{ __(@$whatsappAccount->business_name) }}</td>
                                <td>{{ @$whatsappAccount->phone_number }}</td>
                                <td>
                                    <div>
                                        @php echo $whatsappAccount->verificationStatusBadge; @endphp
                                        <a title="@lang('Get the current verification status of your whatsapp business account from Meta API')"
                                            href="{{ route('user.whatsapp.account.verification.check', $whatsappAccount->id) }}">
                                            <i class="las la-redo-alt"></i>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    @if ($whatsappAccount->is_default)
                                        <span class="badge badge--success">@lang('Yes')</span>
                                    @else
                                        <span class="badge badge--danger">@lang('No')</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="action-btn">
                                        <button class="action-btn__icon p-1">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="action-dropdown">
                                            @if (!$whatsappAccount->is_default)
                                                <li class="action-dropdown__item">
                                                    <a class="action-dropdown__link"
                                                        href="{{ route('user.whatsapp.account.connect', $whatsappAccount->id) }}">
                                                        <span class="text"><i class="las la-check-circle"></i>
                                                            @lang('Make Default Account')
                                                        </span>
                                                    </a>
                                                </li>
                                            @endif
                                            <li class="action-dropdown__item">
                                                <a class="action-dropdown__link"
                                                    href="{{ route('user.whatsapp.account.setting', $whatsappAccount->id) }}">
                                                    <span class="text">
                                                        <i class="las la-cog"></i>
                                                        @lang('Change Token')
                                                    </span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            @include('Template::partials.empty_message')
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ paginateLinks($whatsappAccounts) }}
        </div>
    </div>

    <div class="modal fade custom--modal whatsapp-connect-modal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Enter your desire pin number')</h5>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('user.whatsapp.account.whatsapp.pin') }}">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="label-two">@lang('Pin')</label>
                            <input type="text" class="form--control form-two" name="pin"
                                placeholder="@lang('Enter your pin')" required>
                        </div>
                        <input type="hidden" class="form--control form-two" name="waba_id">
                        <input type="hidden" class="form--control form-two" name="access_token">
                        <div class="form-group">
                            <button type="submit" class="btn btn--base w-100"><i class="lab la-telegram"></i>
                                @lang('Submit')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('topbar_tabs')
    @include('Template::partials.profile_tab')
@endpush

@push('script-lib')
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            var wabaId = null;
            var accessToken = null;

            window.fbAsyncInit = function() {
                FB.init({
                    appId: "{{ gs('meta_app_id') }}",
                    autoLogAppEvents: true,
                    xfbml: true,
                    version: 'v23.0'
                });
            };

            window.addEventListener('message', (event) => {
                if (!event.origin.endsWith('facebook.com')) return;

                try {
                    const data = JSON.parse(event.data);
                    if (data.type == 'WA_EMBEDDED_SIGNUP' && data.event == 'FINISH') {
                        wabaId = data.data.waba_id;
                        const payload = {
                            waba_id: data.data.waba_id,
                            business_id: data.data.business_id,
                            phone_number_id: data.data.phone_number_id,
                            _token: "{{ csrf_token() }}"
                        };

                        $.ajax({
                            url: "{{ route('user.whatsapp.account.embedded.signup') }}",
                            type: "POST",
                            data: payload,
                            success: function(res) {
                                if (res.data.success) {
                                    notify('success', res.message);
                                } else {
                                    notify('error', res.message);
                                }
                            },
                            error: function(err) {
                                notify("error", "@lang('Failed to connect the business account')");
                            }
                        });
                    }
                } catch (e) {
                    notify("error", "@lang('Failed to connect the business account')");
                }
            });

            const fbLoginCallback = (response) => {
                if (response.authResponse) {
                    const code = response.authResponse.code;
                    if (!code) return;
                    $(".preloader").fadeIn();
                    $.ajax({
                        url: "{{ route('user.whatsapp.account.access.token') }}",
                        type: "POST",
                        data: {
                            code: code,
                            waba_id: wabaId,
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(res) {
                            $(".preloader").fadeOut();
                            if (res.data) {
                                notify('success', res.message);
                                accessToken = res.data.access_token;
                                launchPinModal(wabaId, accessToken);
                            } else {
                                notify('error', res.message);
                            }
                        },
                        error: function(err) {
                            $(".preloader").fadeOut();
                            notify("error", "@lang('Failed to connect the business account')");
                        }
                    });
                } else {
                    notify("error", "@lang('Embedded signup failed')");
                }
            }

            const launchWhatsAppSignup = () => {

                if ('{{ $accountLimit }}' == false) {
                    notify("error", "@lang('You have reached the maximum limit of WhatsApp account. Please upgrade your plan.')");
                    return;
                }

                FB.login(fbLoginCallback, {
                    config_id: "{{ gs('meta_configuration_id') }}",
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        "version": "v23.0",
                        sessionInfoVersion: '3',
                        setup: {},
                    }
                });
            }

            $('.whatsapp-connect').on('click', function(e) {
                e.preventDefault();

                let appId = "{{ gs('meta_app_id') }}";
                let configurationId = "{{ gs('meta_configuration_id') }}";
                let appSecret = "{{ gs('meta_app_secret') }}";

                if (!appId || !configurationId || !appSecret) {
                    notify("error", "@lang('The embedded signup feature is not available at this moment.')");
                    return;
                }

                launchWhatsAppSignup();
            });

            let refreshWarning = function(e) {
                e.preventDefault();
                e.returnValue = '⚠️ Are you sure? Your PIN setup will be lost!';
            };

            function launchPinModal(waba_id, access_token) {
                $('.whatsapp-connect-modal').find('input[name=waba_id]').val(waba_id);
                $('.whatsapp-connect-modal').find('input[name=access_token]').val(access_token);

                $('.whatsapp-connect-modal').modal('show');

                window.addEventListener("beforeunload", refreshWarning);
            }

            $('.whatsapp-connect-modal').on('hidden.bs.modal', function() {
                window.removeEventListener("beforeunload", refreshWarning);
            });

            $('.whatsapp-connect-modal form').on('submit', function() {
                window.removeEventListener("beforeunload", refreshWarning);
            });


        })(jQuery);
    </script>
@endpush
