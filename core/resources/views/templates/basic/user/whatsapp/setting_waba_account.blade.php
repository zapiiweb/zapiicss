@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="dashboard-container">

        <div class="container-top">
            <div class="container-top__left">
                <h5 class="container-top__title">{{ __(@$pageTitle) }}</h5>
                <p class="container-top__desc">@lang('Configure your WhatsApp Business Account using Meta API or direct connection')
                </p>
            </div>
            <div class="container-top__right">
                <div class="btn--group">
                    <a href="{{ route('user.whatsapp.account.index') }}" class="btn btn--dark"><i class="las la-undo"></i>
                        @lang('Back')</a>
                    <button type="submit" form="whatsapp-meta-form" class="btn btn--base btn-shadow" id="updateTokenBtn">
                        <i class="lab la-telegram"></i>
                        @lang('Update Token')
                    </button>
                </div>
            </div>
        </div>

        <!-- Connection Type Selector -->
        <div class="dashboard-container__body">
            <div class="connection-type-selector">
                <div class="selector-container">
                    <div class="selector-option" data-type="meta">
                        <div class="option-icon">
                            <i class="lab la-facebook"></i>
                        </div>
                        <div class="option-content">
                            <h6 class="option-title">@lang('Meta WhatsApp Business Account')</h6>
                            <p class="option-desc">@lang('Use Meta API with access token')</p>
                        </div>
                    </div>
                    
                    <div class="toggle-switch">
                        <input type="checkbox" id="connectionTypeToggle" class="toggle-input">
                        <label for="connectionTypeToggle" class="toggle-label">
                            <span class="toggle-button"></span>
                        </label>
                    </div>
                    
                    <div class="selector-option" data-type="baileys">
                        <div class="option-icon">
                            <i class="lab la-whatsapp"></i>
                        </div>
                        <div class="option-content">
                            <h6 class="option-title">@lang('WhatsApp Direct Connection')</h6>
                            <p class="option-desc">@lang('Scan QR code to connect directly')</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meta API Configuration Section -->
        <div class="dashboard-container__body meta-config-section">
            <div class="card">
                <div class="card-header bg--light">
                    <h5 class="mb-0">
                        <i class="lab la-facebook"></i> @lang('Meta WhatsApp Business Account')
                    </h5>
                    <p class="mb-0 mt-2 text-muted">
                        @lang('Configure your Meta WhatsApp Business API credentials. Make sure you have taken the access token from your')
                        <a target="_blank" href="https://developers.facebook.com/apps/">
                            <i class="la la-external-link"></i> @lang('Meta Dashboard')
                        </a>
                    </p>
                </div>
                <div class="card-body">
                    <form id="whatsapp-meta-form" method="POST"
                        action="{{ route('user.whatsapp.account.setting.confirm', @$whatsappAccount->id) }}">
                        @csrf
                        <div class="row gy-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="label-two">@lang('Business Name')</label>
                                    <input type="text" class="form--control form-two" name="business_name"
                                        placeholder="@lang('Enter your business name')" value="{{ @$whatsappAccount->business_name }}" readonly
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="label-two">@lang('WhatsApp Number')</label>
                                    <input type="text" class="form--control form-two" name="whatsapp_number"
                                        placeholder="@lang('Enter your WhatsApp number with country code')" value="{{ @$whatsappAccount->phone_number }}" readonly
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="label-two">@lang('WhatsApp Business Account ID')</label>
                                    <input type="text" class="form--control form-two" name="whatsapp_business_account_id"
                                        placeholder="@lang('Enter business account ID')"
                                        value="{{ @$whatsappAccount->whatsapp_business_account_id }}" readonly required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="label-two">@lang('WhatsApp Phone Number ID')</label>
                                    <input type="text" class="form--control form-two" name="phone_number_id"
                                        placeholder="@lang('Enter phone number ID')" value="{{ @$whatsappAccount->phone_number_id }}" readonly
                                        required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="label-two">
                                        @lang('Meta Access Token')
                                    </label>
                                    <i class="fas fa-info-circle text--info ms-1" data-toggle="tooltip" data-placement="top"
                                        title="@lang('If you change the access token, the current token will be expired.')">
                                    </i>
                                    <input type="text" class="form--control form-two" name="meta_access_token"
                                        placeholder="@lang('Enter your access token')" value="{{ @$whatsappAccount->access_token }}" required>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Baileys WhatsApp Connection Section -->
        <div class="dashboard-container__body baileys-config-section" style="display: none;">
            <div class="card">
                <div class="card-header bg--light">
                    <h5 class="mb-0">
                        <i class="lab la-whatsapp"></i> @lang('WhatsApp Direct Connection')
                    </h5>
                    <p class="mb-0 mt-2 text-muted">@lang('Connect your WhatsApp directly by scanning a QR code. No Meta Business Account needed.')</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="baileys-connection-info">
                                @if($whatsappAccount->baileys_connected)
                                    <div class="alert alert-success">
                                        <i class="las la-check-circle"></i> 
                                        @lang('WhatsApp Connected')
                                        @if($whatsappAccount->baileys_phone_number)
                                            <br>
                                            <small>@lang('Phone'): {{ $whatsappAccount->baileys_phone_number }}</small>
                                        @endif
                                        @if($whatsappAccount->baileys_connected_at)
                                            <br>
                                            <small>@lang('Connected at'): {{ $whatsappAccount->baileys_connected_at->format('d/m/Y H:i') }}</small>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn--danger btn-disconnect" data-account-id="{{ $whatsappAccount->id }}">
                                        <i class="las la-unlink"></i> @lang('Disconnect')
                                    </button>
                                @else
                                    <div class="alert alert-info">
                                        <i class="las la-info-circle"></i> 
                                        @lang('Not connected. Click "Generate QR Code" to connect your WhatsApp.')
                                    </div>
                                    <button type="button" class="btn btn--base btn-start-session" data-account-id="{{ $whatsappAccount->id }}">
                                        <i class="las la-qrcode"></i> @lang('Generate QR Code')
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="qr-code-container text-center" style="min-height: 300px; display: flex; align-items: center; justify-content: center; border: 2px dashed #ddd; border-radius: 10px;">
                                <div class="qr-placeholder">
                                    <i class="las la-qrcode" style="font-size: 80px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">@lang('QR code will appear here')</p>
                                </div>
                                <div class="qr-code" style="display: none;">
                                    <canvas id="qrCanvas"></canvas>
                                    <p class="mt-3 text-muted small">@lang('Scan this QR code with your WhatsApp mobile app')</p>
                                </div>
                                <div class="qr-loading" style="display: none;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">@lang('Loading...')</span>
                                    </div>
                                    <p class="mt-2">@lang('Generating QR code...')</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('topbar_tabs')
    @include('Template::partials.profile_tab')
@endpush

@push('style')
<style>
.connection-type-selector {
    background: #fff;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
}

.selector-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.selector-option {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
    cursor: pointer;
    min-width: 250px;
}

.selector-option.active {
    border-color: #{{ gs('base_color') }};
    background: rgba({{ hexdec(substr(gs('base_color'), 0, 2)) }}, {{ hexdec(substr(gs('base_color'), 2, 2)) }}, {{ hexdec(substr(gs('base_color'), 4, 2)) }}, 0.1);
}

.selector-option:hover {
    border-color: #{{ gs('base_color') }};
}

.option-icon {
    font-size: 40px;
    color: #{{ gs('base_color') }};
    min-width: 50px;
}

.option-content {
    flex: 1;
}

.option-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
    color: #1f2937;
}

.option-desc {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}

.toggle-switch {
    display: flex;
    align-items: center;
}

.toggle-input {
    display: none;
}

.toggle-label {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
    background-color: #{{ gs('base_color') }};
    border-radius: 34px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.toggle-button {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 28px;
    height: 28px;
    background-color: white;
    border-radius: 50%;
    transition: transform 0.3s;
}

.toggle-input:checked + .toggle-label {
    background-color: #25d366;
}

.toggle-input:checked + .toggle-label .toggle-button {
    transform: translateX(26px);
}

@media (max-width: 768px) {
    .selector-container {
        flex-direction: column;
    }
    
    .selector-option {
        width: 100%;
    }
    
    .toggle-switch {
        order: -1;
        margin-bottom: 15px;
    }
}
</style>
@endpush

@push('script-lib')
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
@endpush

@push('script')
<script>
(function($) {
    "use strict";

    const accountId = "{{ $whatsappAccount->id }}";
    let statusCheckInterval = null;

    // Toggle between Meta and Baileys configurations
    const toggleSwitch = $('#connectionTypeToggle');
    const metaSection = $('.meta-config-section');
    const baileysSection = $('.baileys-config-section');
    const updateTokenBtn = $('#updateTokenBtn');
    const metaOption = $('.selector-option[data-type="meta"]');
    const baileysOption = $('.selector-option[data-type="baileys"]');

    function switchToMeta() {
        toggleSwitch.prop('checked', false);
        metaSection.slideDown(300);
        baileysSection.slideUp(300);
        updateTokenBtn.show();
        metaOption.addClass('active');
        baileysOption.removeClass('active');
    }

    function switchToBaileys() {
        toggleSwitch.prop('checked', true);
        metaSection.slideUp(300);
        baileysSection.slideDown(300);
        updateTokenBtn.hide();
        metaOption.removeClass('active');
        baileysOption.addClass('active');
    }

    // Initialize based on current connection_type
    const currentConnectionType = {{ $whatsappAccount->connection_type ?? 1 }};
    if (currentConnectionType === 2) {
        switchToBaileys();
    } else {
        switchToMeta();
    }

    // Update connection_type in database
    function updateConnectionType(connectionType) {
        $.ajax({
            url: "{{ route('user.whatsapp.account.update.connection.type', '') }}/" + accountId,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                connection_type: connectionType
            },
            success: function(response) {
                if (response.success) {
                    console.log('Connection type updated successfully');
                }
            },
            error: function(xhr) {
                console.error('Failed to update connection type');
            }
        });
    }

    // Toggle switch handler
    toggleSwitch.on('change', function() {
        if ($(this).is(':checked')) {
            switchToBaileys();
            updateConnectionType(2);
        } else {
            switchToMeta();
            updateConnectionType(1);
        }
    });

    // Click on options to toggle
    metaOption.on('click', function() {
        switchToMeta();
        updateConnectionType(1);
    });

    baileysOption.on('click', function() {
        switchToBaileys();
        updateConnectionType(2);
    });

    // Start session and generate QR code
    $('.btn-start-session').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> @lang("Starting...")');

        $('.qr-placeholder').hide();
        $('.qr-code').hide();
        $('.qr-loading').show();

        $.ajax({
            url: "{{ route('user.whatsapp.account.baileys.start', '') }}/" + accountId,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.remark === 'success') {
                    iziToast.success({
                        message: response.message,
                        position: "topRight"
                    });
                    
                    // Start polling for QR code
                    pollForQRCode();
                } else {
                    iziToast.error({
                        message: response.message || '@lang("Failed to start session")',
                        position: "topRight"
                    });
                    $('.qr-loading').hide();
                    $('.qr-placeholder').show();
                    btn.prop('disabled', false).html('<i class="las la-qrcode"></i> @lang("Generate QR Code")');
                }
            },
            error: function(xhr) {
                iziToast.error({
                    message: '@lang("Error starting session")',
                    position: "topRight"
                });
                $('.qr-loading').hide();
                $('.qr-placeholder').show();
                btn.prop('disabled', false).html('<i class="las la-qrcode"></i> @lang("Generate QR Code")');
            }
        });
    });

    // Poll for QR code
    function pollForQRCode() {
        let attempts = 0;
        const maxAttempts = 30;

        const interval = setInterval(function() {
            attempts++;

            if (attempts > maxAttempts) {
                clearInterval(interval);
                $('.qr-loading').hide();
                $('.qr-placeholder').show();
                $('.btn-start-session').prop('disabled', false).html('<i class="las la-qrcode"></i> @lang("Generate QR Code")');
                iziToast.error({
                    message: '@lang("QR code generation timeout. Please try again.")',
                    position: "topRight"
                });
                return;
            }

            $.ajax({
                url: "{{ route('user.whatsapp.account.baileys.qr', '') }}/" + accountId,
                type: 'GET',
                success: function(response) {
                    if (response.success && response.qr) {
                        clearInterval(interval);
                        displayQRCode(response.qr);
                        // Start checking connection status
                        startStatusCheck();
                    }
                }
            });
        }, 1000);
    }

    // Display QR code
    function displayQRCode(qrData) {
        $('.qr-loading').hide();
        $('.qr-placeholder').hide();
        $('.qr-code').show();

        const canvas = document.getElementById('qrCanvas');
        new QRious({
            element: canvas,
            value: qrData,
            size: 300,
            level: 'H'
        });

        $('.btn-start-session').prop('disabled', false).html('<i class="las la-sync"></i> @lang("Refresh QR Code")');
    }

    // Check connection status
    function startStatusCheck() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }

        statusCheckInterval = setInterval(function() {
            $.ajax({
                url: "{{ route('user.whatsapp.account.baileys.status', '') }}/" + accountId,
                type: 'GET',
                success: function(response) {
                    if (response.success && response.connected) {
                        clearInterval(statusCheckInterval);
                        iziToast.success({
                            message: '@lang("WhatsApp connected successfully!")',
                            position: "topRight"
                        });
                        // Reload page to show connected status
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            });
        }, 3000);
    }

    // Disconnect
    $('.btn-disconnect').on('click', function() {
        if (!confirm('@lang("Are you sure you want to disconnect?")')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> @lang("Disconnecting...")');

        $.ajax({
            url: "{{ route('user.whatsapp.account.baileys.disconnect', '') }}/" + accountId,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.remark === 'success') {
                    iziToast.success({
                        message: response.message,
                        position: "topRight"
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    iziToast.error({
                        message: response.message || '@lang("Failed to disconnect")',
                        position: "topRight"
                    });
                    btn.prop('disabled', false).html('<i class="las la-unlink"></i> @lang("Disconnect")');
                }
            },
            error: function() {
                iziToast.error({
                    message: '@lang("Error disconnecting")',
                    position: "topRight"
                });
                btn.prop('disabled', false).html('<i class="las la-unlink"></i> @lang("Disconnect")');
            }
        });
    });

    // Clean up interval on page unload
    $(window).on('beforeunload', function() {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });

})(jQuery);
</script>
@endpush
