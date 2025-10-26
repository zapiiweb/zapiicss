@extends('admin.layouts.app')
@section('panel')
    <form action="{{ route('admin.withdraw.method.update', $method->id) }}" method="POST" enctype="multipart/form-data" class="method-form">
        @csrf
        <div class="row gy-4">
            <div class="col-12">
                <x-admin.ui.card>
                    <x-admin.ui.card.body>
                        <div class="row gy-4">
                            <div class="col-xxl-4 col-xl-6">
                                <x-image-uploader image="{{ $method->image }}" class="w-100" type="withdrawMethod"
                                    :required=false />
                            </div>
                            <div class="col-xxl-8 col-xl-6">
                                <div class="form-group">
                                    <label>@lang('Name')</label>
                                    <input type="text" class="form-control" name="name" value="{{ $method->name }}"
                                        required />
                                </div>
                                <div class="form-group">
                                    <label>@lang('Currency')</label>
                                    <div class="input-group input--group">
                                        <input type="text" name="currency" class="form-control border-radius-5"
                                            value="{{ $method->currency }}" required />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>@lang('Rate')</label>
                                    <div class="input-group input--group">
                                        <span class="input-group-text">1 {{ __(gs('cur_text')) }}
                                            =
                                        </span>
                                        <input type="number" step="any" class="form-control rateInput" name="rate"
                                            value="{{ getAmount($method->rate) }}" required />
                                        <span class="currency_symbol input-group-text"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-admin.ui.card.body>
                </x-admin.ui.card>
            </div>
            <div class="col-lg-6">
                <div class="card border border--gray h-100">
                    <h5 class="card-header bg--gray fs-20">@lang('Range')</h5>
                    <div class="card-body">
                        <div class="form-group">
                            <label>@lang('Minimum Amount')</label>
                            <div class="input-group input--group">
                                <input type="text" class="form-control minLimit" name="min_limit"
                                    value="{{ getAmount($method->min_limit) }}" required />
                                <span class="input-group-text"> {{ __(gs('cur_text')) }} </span>
                            </div>
                            <span class="text--danger fs-13 d-none minimum-error">@lang('The minimum amount must be greater than the fixed charge')</span>
                        </div>
                        <div class="form-group">
                            <label>@lang('Maximum Amount')</label>
                            <div class="input-group input--group">
                                <input type="text" class="form-control" name="max_limit"
                                    value="{{ getAmount($method->max_limit) }}" required />
                                <span class="input-group-text"> {{ __(gs('cur_text')) }} </span>
                            </div>
                            <span class="text--danger fs-13 maximum-error d-none">@lang('The maximum amount must be greater than the minimum amount')</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border border--gray h-100">
                    <h5 class="card-header bg--gray fs-20">@lang('Charge')</h5>
                    <div class="card-body">
                        <div class="form-group">
                            <label>@lang('Fixed Charge')</label>
                            <div class="input-group input--group">
                                <input type="text" class="form-control" name="fixed_charge"
                                    value="{{ getAmount($method->fixed_charge) }}" required />
                                <span class="input-group-text"> {{ __(gs('cur_text')) }} </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>@lang('Percent Charge')</label>
                            <div class="input-group input--group">
                                <input type="text" class="form-control" name="percent_charge"
                                    value="{{ getAmount($method->percent_charge) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="card border border--gray h-100">
                    <h5 class="card-header bg--gray fs-20">@lang('Withdraw Instruction') </h5>
                    <div class="card-body">
                        <div class="form-group">
                            <textarea rows="5" class="form-control border-radius-5 editor" name="instruction">{{ $method->description }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="submitRequired form-change-alert d-none mt-3"><i class="fas fa-exclamation-triangle"></i>
                    @lang('You\'ve to click on the submit button to apply the changes')</div>
                <x-generated-form :form=$form generateTitle="Generate User Data Form" formTitle="User Data Form"
                    formSubtitle="Effortlessly gather complete user information with our easy-to-use data form."
                    generateSubTitle="Create user data forms to capture required data from the users on withdrawal via this method." />
            </div>
            <div class="col-12">
                
                <x-admin.ui.btn.submit class="submit-btn" />
            </div>
        </div>
    </form>
@endsection

@push('script-lib')
    <script src="{{ asset('assets/global/js/summernote-lite.min.js') }}"></script>
@endpush
@push('style-lib')
    <link href="{{ asset('assets/global/css/summernote-lite.min.css') }}" rel="stylesheet">
@endpush

@push('breadcrumb-plugins')
    <x-back_btn route="{{ route('admin.withdraw.method.index') }}" />
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('input[name=currency]').on('input', function() {
                $('.currency_symbol').text($(this).val());
            });
            $('.currency_symbol').text($('input[name=currency]').val());

            @if (old('currency'))
                $('input[name=currency]').trigger('input');
            @endif

            $("input[name=min_limit],input[name=max_limit],input[name=fixed_charge]").on('input change',function(){
                validation();
            });

            const validation=()=> {
                const minLimit    = Number($('input[name=min_limit]').val());
                const maxLimit    = Number($('input[name=max_limit]').val());
                const fixedCharge = Number($('input[name=fixed_charge]').val());
                var minAmountValidate,maxAmountValidate=false;
                
                if(minLimit && (minLimit <= fixedCharge)){
                    $(".minimum-error").removeClass('d-none');
                    minAmountValidate=false;
                }else{
                    $(".minimum-error").addClass('d-none');
                    minAmountValidate=true;
                }
                
                if(maxLimit <= minLimit   && (minLimit && maxLimit)){
                    maxAmountValidate=false;
                    $(".maximum-error").removeClass('d-none');
                }else{
                    $(".maximum-error").addClass('d-none');
                    maxAmountValidate=true;
                }
                
                if(minAmountValidate && maxAmountValidate){
                    $(".submit-btn").removeClass('disabled').attr("disabled",false);
                }else{
                    $(".submit-btn").addClass('disabled').attr("disabled",true);
                }
            }
        })(jQuery);
    </script>
@endpush
