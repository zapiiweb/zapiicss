@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="dashboard-container">
        <div class="container-top">
            <div class="container-top__left">
                <h5 class="container-top__title"> {{ __(@$pageTitle) }} </h5>
                <p class="container-top__desc">@lang('Raise a support ticket to get expert help for your queries and concerns.')</p>
            </div>
            <div class="container-top__right">
                <div class="btn--group">
                    <a href="{{ route('ticket.index') }}" class="btn btn--dark btn-shadow">
                        <i class="las la-tags"></i> @lang('Support Tickets List')
                    </a>
                </div>
            </div>
        </div>
        <div class="dashboard-container__body">
            <form action="{{ route('ticket.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="form-group col-md-6">
                        <label class="form-label">@lang('Subject')</label>
                        <input type="text" name="subject" value="{{ old('subject') }}" class="form--control form-two"
                            placeholder="@lang('Enter subject')" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label">@lang('Priority')</label>
                        <select name="priority" class="form-select form--control select2"
                            data-minimum-results-for-search="-1" required>
                            <option value="3">@lang('High')</option>
                            <option value="2">@lang('Medium')</option>
                            <option value="1">@lang('Low')</option>
                        </select>
                    </div>
                    <div class="col-12 form-group">
                        <label class="form-label">@lang('Message')</label>
                        <textarea name="message" id="inputMessage" rows="6" class="form--control form-two"
                            placeholder="@lang('Enter message...')" required>{{ old('message') }}</textarea>
                    </div>
                    <div class="col-md-12">
                        <div class="row fileUploadsContainer">
                        </div>
                        <button type="button" class="btn btn--dark  addAttachment my-2"> <i class="fas fa-plus"></i>
                            @lang('Add Attachment') </button>
                        <button class="btn btn--base ms-2" type="submit"><i class="fa-regular fa-paper-plane"></i>
                            @lang('Submit Query')
                        </button>
                        <p class="mb-2">
                            <span class="text--info">
                                @lang('Max 5 files can be uploaded | Maximum upload size is ' . convertToReadableSize(ini_get('upload_max_filesize')) . ' | Allowed File Extensions: .jpg, .jpeg, .png, .pdf, .doc, .docx')
                            </span>
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .input-group-text:focus {
            box-shadow: none !important;
        }
    </style>
@endpush

@push('style-lib')
    <link href="{{ asset('assets/global/css/select2.min.css') }}" rel="stylesheet">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";
            var fileAdded = 0;
            $('.addAttachment').on('click', function() {
                fileAdded++;
                if (fileAdded == 5) {
                    $(this).attr('disabled', true)
                }
                $(".fileUploadsContainer").append(`
                    <div class="col-lg-4 col-md-12 removeFileInput">
                        <div class="form-group">
                            <div class="input-group">
                                <input type="file" name="attachments[]" class="form--control form-two form-control" accept=".jpeg,.jpg,.png,.pdf,.doc,.docx" required>
                                <button type="button" class="input-group-text removeFile bg--danger border--danger text-white"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    </div>
                `)
            });
            $(document).on('click', '.removeFile', function() {
                $('.addAttachment').removeAttr('disabled', true)
                fileAdded--;
                $(this).closest('.removeFileInput').remove();
            });
        })(jQuery);
    </script>
@endpush

@push('topbar_tabs')
    @include('Template::partials.profile_tab')
@endpush
