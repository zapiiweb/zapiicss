@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-md-12">
            <x-admin.ui.card>
                <x-admin.ui.card.body>
                    <form method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">@lang('Email Send Method')</label>
                            <select name="email_method" class="select2 form-control" data-minimum-results-for-search="-1">
                                <option value="php" @if (gs('mail_config')->name == 'php') selected @endif>
                                    @lang('PHP Mail')
                                </option>
                                <option value="smtp" @if (gs('mail_config')->name == 'smtp') selected @endif>
                                    @lang('SMTP')
                                </option>
                                <option value="sendgrid" @if (gs('mail_config')->name == 'sendgrid') selected @endif>
                                    @lang('SendGrid API')
                                </option>
                                <option value="mailjet" @if (gs('mail_config')->name == 'mailjet') selected @endif>
                                    @lang('Mailjet API')
                                </option>
                            </select>
                        </div>
                        <div class="row mt-4 d-none configForm" id="smtp">
                            <div class="col-md-12">
                                <h6 class="mb-2">@lang('SMTP Configuration')</h6>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Host') </label>
                                    <input type="text" class="form-control" placeholder="e.g. @lang('smtp.googlemail.com')"
                                        name="host" value="{{ gs('mail_config')->host ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Port') </label>
                                    <input type="text" class="form-control" placeholder="@lang('Available port')"
                                        name="port" value="{{ gs('mail_config')->port ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Encryption')</label>
                                    <select class="form-control select2" data-minimum-results-for-search="-1"
                                        name="enc">
                                        <option value="ssl" @selected(@gs('mail_config')->enc == 'ssl')>@lang('SSL')</option>
                                        <option value="tls" @selected(@gs('mail_config')->enc == 'tls')>@lang('TLS')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Username') </label>
                                    <input type="text" class="form-control" placeholder="@lang('Normally your email') address"
                                        name="username" value="{{ gs('mail_config')->username ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Password') </label>
                                    <input type="text" class="form-control" placeholder="@lang('Normally your email password')"
                                        name="password" value="{{ gs('mail_config')->password ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4 d-none configForm" id="sendgrid">
                            <div class="col-md-12">
                                <h6 class="mb-2">@lang('SendGrid API Configuration')</h6>
                            </div>
                            <div class="form-group col-md-12">
                                <label>@lang('App Key') </label>
                                <input type="text" class="form-control" placeholder="@lang('SendGrid App key')" name="appkey"
                                    value="{{ gs('mail_config')->appkey ?? '' }}">
                            </div>
                        </div>
                        <div class="row mt-4 d-none configForm" id="mailjet">
                            <div class="col-md-12">
                                <h6 class="mb-2">@lang('Mailjet API Configuration')</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Api Public Key') </label>
                                    <input type="text" class="form-control" placeholder="@lang('Mailjet Api Public Key')"
                                        name="public_key" value="{{ gs('mail_config')->public_key ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Api Secret Key') </label>
                                    <input type="text" class="form-control" placeholder="@lang('Mailjet Api Secret Key')"
                                        name="secret_key" value="{{ gs('mail_config')->secret_key ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <x-admin.ui.btn.submit />
                    </form>
                </x-admin.ui.card.body>
            </x-admin.ui.card>
        </div>
    </div>


    <x-admin.ui.modal id="testMailModal">
        <x-admin.ui.modal.header>
            <h1 class="modal-title">@lang('Test Mail Setup')</h1>
            <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close">
                <i class="las la-times"></i>
            </button>
        </x-admin.ui.modal.header>
        <x-admin.ui.modal.body>
            <form action="{{ route('admin.setting.notification.email.test') }}" method="POST">
                @csrf
                <input type="hidden" name="id">
                <div class="form-group">
                    <label class="form-label">@lang('Sent to') </label>
                    <input type="text" name="email" class="form-control" placeholder="@lang('Email Address')" required>
                </div>
                <div class="form-group">
                    <x-admin.ui.btn.modal />
                </div>
            </form>
        </x-admin.ui.modal.body>
    </x-admin.ui.modal>
@endsection
@push('breadcrumb-plugins')
    <button type="button" data-bs-target="#testMailModal" data-bs-toggle="modal"
        class="btn  btn--primary"><i class="fa-regular fa-paper-plane"></i> @lang('Send Test Mail')</button>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            var method = '{{ gs('mail_config')->name }}';
            emailMethod(method);
            $('select[name=email_method]').on('change', function() {
                var method = $(this).val();
                emailMethod(method);
            });

            function emailMethod(method) {
                $('.configForm').addClass('d-none');
                if (method != 'php') {
                    $(`#${method}`).removeClass('d-none');
                }
            }

        })(jQuery);
    </script>
@endpush
