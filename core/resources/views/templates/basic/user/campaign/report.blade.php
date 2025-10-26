@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="dashboard-container">
        <div class="report-wrapper">
            <div class="d-flex align-items-center justify-content-end">
                <form class="select-group filter-form">
                    <select class="form-select form--control select2" data-minimum-results-for-search="-1" name="export">
                        <option selected value="">@lang('Export Report')</option>
                        <option value="minimal">
                            @lang('Minimal')
                        </option>
                        <option value="maximal">
                            @lang('Maximal')
                        </option>
                    </select>
                </form>
            </div>
            <div class="report-top">
                <div class="row gy-4 justify-content-center">
                    <div class="col-xxl-3 col-sm-6">
                        <div class="report-item">
                            <h5 class="report-item__title">@lang('Total Messages')</h5>
                            <div class="report-item__bottom">
                                <div class="text-wrapper">
                                    <span class="text">{{ $campaign->total_message }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-sm-6">
                        <div class="report-item">
                            <h5 class="report-item__title">@lang('Total Sent Messages')</h5>
                            <div class="report-item__bottom">
                                <div class="text-wrapper">
                                    <span class="text text--base">{{ $campaign->total_send }}</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg--info" role="progressbar" aria-label="Success example"
                                        style="width: {{ getAmount(@$widget['sending_ratio']) }}%;"
                                        aria-valuenow="{{ getAmount(@$widget['sending_ratio']) }}" aria-valuemin="0"
                                        aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-sm-6">
                        <div class="report-item">
                            <h5 class="report-item__title">@lang('Total Success Messages')</h5>
                            <div class="report-item__bottom">
                                <div class="text-wrapper">
                                    <span class="text text--base">{{ @$campaign->total_success }}</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg--info" role="progressbar" aria-label="Success example"
                                        style="width: {{ getAmount(@$widget['success_ratio']) }}%;"
                                        aria-valuenow="{{ getAmount(@$widget['success_ratio']) }}" aria-valuemin="0"
                                        aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-sm-6">
                        <div class="report-item">
                            <h5 class="report-item__title">@lang('Total Failed Messages')</h5>
                            <div class="report-item__bottom">
                                <div class="text-wrapper">
                                    <span class="text text--danger">{{ @$campaign->total_failed }}</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg--info" role="progressbar" aria-label="Success example"
                                        style="width: {{ getAmount(@$widget['fail_ratio']) }}%;"
                                        aria-valuenow="{{ getAmount(@$widget['fail_ratio']) }}" aria-valuemin="0"
                                        aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="campaign-wrapper">
                <div class="performance-history">
                    <h5 class="title">@lang('Campaign History')</h5>
                </div>
                <table class="table table--responsive--xl">
                    <thead>
                        <tr>
                            <th>@lang('Contact')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('Last Modified')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (@$campaignContacts as $campaignContact)
                            <tr>
                                <td>{{ @$campaignContact->contact->mobileNumber }}</td>
                                <td>
                                    @php
                                        echo $campaignContact->statusBadge;
                                    @endphp
                                </td>
                                <td>
                                    {{ showDateTime($campaignContact->updated_at) }}<br>{{ diffForHumans($campaignContact->updated_at) }}
                                </td>
                            </tr>
                        @empty
                            @include('Template::partials.empty_message')
                        @endforelse
                    </tbody>
                </table>
                {{ paginateLinks($campaignContacts) }}
            </div>
        </div>
    </div>
@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
@endpush
@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.filter-form').find('select').on('change', function() {
                $('.filter-form').submit();
            });
        })(jQuery);
    </script>
@endpush
