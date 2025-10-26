@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="alert alert--info alert-dismissible mb-3 template-requirements" role="alert">
        <div class="alert__content">
            <h4 class="alert__title"><i class="las la-info-circle"></i> @lang('Template Information')</h4>
            <ul class="ms-4">
                <li class="mb-0 text-dark">@lang('Each carousel template is limited to a minimum 2 and maximum of 10 cards.')</li>
                <li class="mb-0 text-dark">@lang('The template body can contain maximum of 1024 characters.')</li>
                <li class="mb-0 text-dark">@lang('You can submit a maximum of 100 templates per hour.')</li>
                <li class="mb-0 text-dark">@lang('The media is not editable, please upload your product image/video for carousel cards.')</li>
            </ul>
        </div>
    </div>
    <div class="dashboard-container">
        <div class="container-top">
            <div class="container-top__left">
                <h5 class="container-top__title">{{ __(@$pageTitle) }}</h5>
                <p class="container-top__desc">@lang('Easily create carousel templates with multiple products cards for better marketing.')</p>
            </div>
            <div class="container-top__right">
                <div class="btn--group">
                    <a href="{{ route('user.template.index') }}" class="btn btn--dark">
                        <i class="las la-undo"></i>
                        @lang('Back')
                    </a>
                    <button class="btn btn--base btn-shadow submitBtn" type="submit" form="template-form">
                        <i class="lab la-telegram"></i> @lang('Save Template')
                    </button>
                </div>
            </div>
        </div>
        <div class="dashboard-container__body">
            <div class="template-info-container">
                <div class="template-info-container__left">
                    <form action="{{ route('user.template.create.carousel.store') }}" method="POST" id="template-form"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="label-two">@lang('Whatsapp Account')</label>
                                    <x-whatsapp_account />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="label-two">@lang('Template Name')</label>
                                    <input type="text" class="form--control form-two" name="name"
                                        placeholder="@lang('Enter a unique name for this template')" value="{{ old('name') }}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form--label label-two">@lang('Language')</label>
                                    <select class="form--control select2" name="language_id" required>
                                        @foreach ($templateLanguages as $templateLanguage)
                                            <option value="{{ @$templateLanguage->id }}" @selected($templateLanguage->code == 'en_US')>
                                                {{ __(@$templateLanguage->country) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="fs-13 mt-1">
                                        <i>@lang('Choose the template language supported by the WhatsApp Business API.')</i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="my-4">
                                <div class="row justify-content-center">
                                    <div class="col-lg-6">
                                        <div class="auth-devider text-center">
                                            <span> @lang('TEMPLATE BODY')</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="label-two">@lang('Body Content')</label>
                                <div class="body-content">
                                    <textarea class="form--control form-two markdown-editor" name="template_body" id="template_body" maxlength="1024"
                                        placeholder="@lang('Write your template body...')" required>{{ old('template_body') }}</textarea>
                                    <button type="button" class="add-variable body-add-variable" data-bs-toggle="tooltip"
                                        title="@lang('Add Variable')">
                                        <i class="las la-plus"></i>
                                    </button>
                                </div>
                                <div class="d-flex justify-content-end fs-12 text-muted">
                                    <span class="character-count">0</span>
                                    <span>/ 1024</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="my-4">
                                <div class="row justify-content-center">
                                    <div class="col-lg-6">
                                        <div class="auth-devider text-center">
                                            <span>@lang('CAROUSEL CARDS')</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn--base add-card-button mb-2">
                                <i class="las la-plus"></i>
                                @lang('Add Carousel')
                            </button>
                        </div>

                        <div id="cards-wrapper">

                            <div class="card-block mb-4 p-3 border rounded carousel--card" data-card-index="0">

                                <div class="d-flex align-items-center justify-content-between">
                                    <h5>Carousel 1</h5>
                                    <button class="btn btn--danger btn--sm remove-card-button">
                                        <i class="las la-times"></i>
                                    </button>
                                </div>

                                <div class="form-group">
                                    <label class="label-two">@lang('Header Format')</label>
                                    <select name="cards[0][header_format]" class="form--control header-format"
                                        data-card-index="0">
                                        <option value="IMAGE" selected>@lang('Image')</option>
                                        <option value="VIDEO">@lang('Video')</option>
                                    </select>
                                </div>

                                <div class="header-field" data-card-index="0"></div>

                                <div class="buttons-wrapper">
                                    <h6>@lang('Buttons')</h6>

                                    <div class="form-group">
                                        <input type="text" name="cards[0][buttons][0][text]" maxlength="25"
                                            class="form--control form-two button-input-element"
                                            placeholder="@lang('Quick Reply Text')" required>
                                        <input type="hidden" name="cards[0][buttons][0][type]" value="QUICK_REPLY">
                                    </div>

                                    <div class="row mt-2">
                                        <div class="col-lg-6">
                                            <input type="text" name="cards[0][buttons][1][text]" maxlength="25"
                                                class="form--control form-two button-input-element"
                                                placeholder="@lang('URL button text')" required>
                                            <input type="hidden" name="cards[0][buttons][1][type]" value="URL">
                                        </div>
                                        <div class="col-lg-6">
                                            <input type="url" name="cards[0][buttons][1][url]" maxlength="2000"
                                                class="form--control form-two visit-website"
                                                placeholder="@lang('Enter valid URL')" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-block mb-4 p-3 border rounded carousel--card" data-card-index="1">

                                <div class="d-flex align-items-center justify-content-between">
                                    <h5>Carousel 2</h5>
                                    <button class="btn btn--danger btn--sm remove-card-button">
                                        <i class="las la-times"></i>
                                    </button>
                                </div>

                                <div class="form-group">
                                    <label class="label-two">@lang('Header Format')</label>
                                    <select name="cards[1][header_format]" class="form--control header-format"
                                        data-card-index="1">
                                        <option value="IMAGE" selected>@lang('Image')</option>
                                        <option value="VIDEO">@lang('Video')</option>
                                    </select>
                                </div>

                                <div class="header-field" data-card-index="1"></div>

                                <div class="buttons-wrapper">
                                    <h6>@lang('Buttons')</h6>

                                    <div class="form-group">
                                        <input type="text" name="cards[1][buttons][0][text]" maxlength="25"
                                            class="form--control form-two button-input-element"
                                            placeholder="@lang('Quick Reply Text')" required>
                                        <input type="hidden" name="cards[1][buttons][0][type]" value="QUICK_REPLY">
                                    </div>

                                    <div class="row mt-2">
                                        <div class="col-lg-6">
                                            <input type="text" name="cards[1][buttons][1][text]" maxlength="25"
                                                class="form--control form-two button-input-element"
                                                placeholder="@lang('URL button text')" required>
                                            <input type="hidden" name="cards[1][buttons][1][type]" value="URL">
                                        </div>
                                        <div class="col-lg-6">
                                            <input type="url" name="cards[1][buttons][1][url]" maxlength="2000"
                                                class="form--control form-two visit-website"
                                                placeholder="@lang('Enter valid URL')" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </form>
                </div>

                <div class="template-info-container__right">
                    <div class="preview-item">
                        <div class="preview-item__header">
                            <h5 class="preview-item__title">@lang('Template Preview')</h5>
                        </div>
                        <div class="preview-item__content">
                            <div class="preview-item__shape">
                                <img src="{{ getImage($activeTemplateTrue . 'images/preview-1.png') }}" alt="image">
                            </div>
                            <div class="preview-item__body">
                                <div class="card-item">
                                    <div class="card-item__content">
                                        <p class="card-item__desc body_text">@lang('Template body')</p>
                                    </div>
                                </div>
                                <div class="carousel-cards overflow-auto mt-1 d-flex gap-2 align-items-center">
                                    <div class="card-item col-12" data-card-index="0">
                                        <div class="card-item__thumb header_media">
                                            <img src="{{ getImage($activeTemplateTrue . 'images/preview-1.png') }}"
                                                alt="image">
                                        </div>
                                        <div class="button-preview mt-2 d-flex gap-2 flex-column">
                                            <button type="button" class="btn btn--template bg-white w-100"
                                                data-type="QUICK_REPLY">
                                                <i class="las la-reply"></i> <span
                                                    class="text">@lang('Send me more')</span>
                                            </button>
                                            <button type="button" class="btn btn--template bg-white w-100"
                                                data-type="URL">
                                                <i class="la la-external-link-alt"></i> <span
                                                    class="text">@lang('Shop')</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-item col-12" data-card-index="1">
                                        <div class="card-item__thumb header_media">
                                            <img src="{{ getImage($activeTemplateTrue . 'images/preview-1.png') }}"
                                                alt="image">
                                        </div>
                                        <div class="button-preview mt-2 d-flex gap-2 flex-column">
                                            <button type="button" class="btn btn--template bg-white w-100"
                                                data-type="QUICK_REPLY">
                                                <i class="las la-reply"></i> <span
                                                    class="text">@lang('Send me more')</span>
                                            </button>
                                            <button type="button" class="btn btn--template bg-white w-100"
                                                data-type="URL">
                                                <i class="la la-external-link-alt"></i> <span
                                                    class="text">@lang('Shop')</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/ovo-markdown.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('#template-form').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                const $submitBtn = $(".submitBtn");
                const oldHtml = $submitBtn.html();
                $.ajax({
                    url: "{{ route('user.template.create.carousel.store') }}",
                    type: "POST",
                    data: formData,
                    contentType: false,
                    processData: false,
                    cache: false,
                    beforeSend: function() {
                        $submitBtn.addClass('disabled').attr("disabled", true).html(`
                            <div class="button-loader d-flex gap-2 flex-wrap align-items-center justify-content-center">
                                <div class="spinner-border"></div><span>Loading...</span>
                            </div>
                        `);
                    },
                    success: function(response) {
                        notify(response.status, response.message);
                        setTimeout(() => {
                            window.location.href = "{{ route('user.template.index') }}";
                        }, 500);
                    },
                    complete: function() {
                        $submitBtn.removeClass('disabled').attr("disabled", false).html(oldHtml);
                    }
                });
            });

            const $buttonPrevContainer = $(".button-preview");
            const $templatePreviewWrapper = $(".preview-item");

            const generateHtml = {
                templateHeaderTypeHtml: function(selectedType, cardIndex) {
                    if (selectedType === 'IMAGE') {
                        return `<div class="form-group">
                            <label class="form--label label-two">@lang('Upload Image')</label>
                            <input type="file" class="form--control form-two card-image-input" data-card-index="${cardIndex}"
                                name="cards[${cardIndex}][header][handle]" accept="image/*">
                        </div>`;
                    } else {
                        return `<div class="form-group">
                            <label class="form--label label-two">@lang('Upload Video')</label>
                            <input type="file" class="form--control form-two card-image-input" data-card-index="${cardIndex}"
                                name="cards[${cardIndex}][header][handle]" accept="video/*">
                        </div>`;
                    }
                }
            }

            $('body').on('input', '.button-input-element', function() {
                let value = $(this).val();
                let cardIndex = $(this).closest('.carousel--card').data('card-index');
                console.log(cardIndex);
                let type = $(this).siblings('input').val();
                let selector = $('.carousel-cards').find(`.card-item[data-card-index="${cardIndex}"]`)
                    .find('.button-preview').find(`button[data-type="${type}"]`).find('.text').text(value);
            });

            $(document).on('click', '.add-card-button', function(e) {
                e.preventDefault();

                let count = $('#cards-wrapper .card-block').length;

                if (count >= 10) {
                    notify('error', "@lang('Maximum 10 carousel cards are allowed')");
                    return;
                }

                let newIndex = count;

                let $clone = $('#cards-wrapper .card-block:first').clone();

                $clone.find('[name]').each(function() {
                    let name = $(this).attr('name');

                    if (name) {
                        let updated = name.replace(/cards\[\d+\]/, 'cards[' + newIndex + ']');
                        $(this).attr('name', updated);
                    }
                });

                $clone.attr('data-card-index', newIndex);
                $clone.find('[data-card-index]').each(function() {
                    $(this).attr('data-card-index', newIndex);
                });

                $clone.find('h5').text('Carousel ' + (newIndex + 1));

                $clone.find('input[type=text], input[type=url], input[type=file]').val('');
                $clone.find('select').prop('selectedIndex', 0);

                $('#cards-wrapper').append($clone);

                $clone.find('.header-format').trigger('change');

                addPreviewCard(newIndex);

            });

            function addPreviewCard(index) {
                const $carousel = $('.carousel-cards');

                let newCard = `
                    <div class="card-item col-12" data-card-index="${index}">
                        <div class="card-item__thumb header_media">
                            <img src="{{ getImage($activeTemplateTrue . 'images/preview-1.png') }}" alt="image">
                        </div>
                        <div class="button-preview mt-2 d-flex gap-2 flex-column">
                            <button type="button" class="btn btn--template bg-white w-100" data-type="QUICK_REPLY">
                                <i class="las la-reply"></i> <span class="text">Send me more</span>
                            </button>
                            <button type="button" class="btn btn--template bg-white w-100" data-type="URL">
                                <i class="la la-external-link-alt"></i> <span class="text">Shop</span>
                            </button>
                        </div>
                    </div>
                `;

                $carousel.append(newCard);
            }


            $(document).on('change', '.header-format', function() {
                var selectedType = $(this).val();
                var cardIndex = $(this).data('card-index');
                var fieldHtml = generateHtml.templateHeaderTypeHtml(selectedType, cardIndex);
                $('.header-field[data-card-index="' + cardIndex + '"]').html(fieldHtml);
            }).change();

            function triggerHeaderFormatChange() {
                $('.header-format').each(function() {
                    $(this).trigger('change');
                });
            }

            triggerHeaderFormatChange();

            $('body').on('input paste', "textarea[name=template_body]", function(e) {
                const text = $(this).val() || "Template Body";
                $templatePreviewWrapper.find('.body_text').html(text);
            }).change();

            var bodyVariableCount = 1;

            $(document).on('click', '.body-add-variable', function() {
                $('textarea[name=template_body]').val($('textarea[name=template_body]').val() +
                    "\{\{" + bodyVariableCount + "\}\}");
                bodyVariableCount++;
                regenerateBodyExampleFields(bodyVariableCount);
                $('textarea[name=template_body]').focus();
            });

            function regenerateBodyExampleFields(count) {
                $('.body-example-field').remove();

                let html = '';
                for (let i = 1; i < count; i++) {
                    html += `
                        <div class="form-group body-example-field">
                            <label class="form--label label-two">Example for \{\{${i}\}\}</label>
                            <input type="text" class="form--control form-two" name="body[example][body_text][]" placeholder="e.g., John" required>
                        </div>`;
                }
                $('textarea[name="template_body"]').closest('.form-group').after(html);
            }

            $(document).on('input', 'input[name="header[example][header_text][]"]', function() {
                let val = $(this).val();
            });

            $(document).on('change', '.card-image-input', function() {
                const fileInput = this;
                const cardIndex = $(this).data('card-index');
                const mediaSelector = $('.carousel-cards').find(`.card-item[data-card-index="${cardIndex}"]`)
                    .find('.header_media');

                if (fileInput.files && fileInput.files[0]) {
                    const file = fileInput.files[0];
                    const fileType = file.type;

                    const reader = new FileReader();

                    reader.onload = function(e) {
                        if (fileType.startsWith('image/')) {
                            mediaSelector.html(`<img src="${e.target.result}" alt="Image">`);
                        } else if (fileType.startsWith('video/')) {
                            mediaSelector.html(
                                `<video controls>
                                    <source src="${e.target.result}" type="${fileType}">
                                    Your browser does not support the video tag.
                                </video>`
                            );
                        } else {
                            notify('error', "@lang('Please select a valid image, video.')");
                            mediaSelector.html('');
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    mediaSelector.html('');
                }
            });

            function updateCharacterCount($input) {
                const count = $input.val().length;
                $input.closest('.form-group').find('.character-count').text(count);
            }

            $(document).on('input paste keyup change', 'textarea[name="template_body"]',
                function() {
                    updateCharacterCount($(this));
                    regenerateHeaderExampleFields();
                });

            $('textarea[name="template_body"]').each(function() {
                updateCharacterCount($(this));
            });

            $(document).on('click', '.remove-card-button', function(e) {
                e.preventDefault();

                let index = $(this).closest('.carousel--card').data('card-index');
                let previewCard = $('.preview-item__body').find(`.card-item[data-card-index="${index}"]`);
                let count = $('body .carousel--card').length;

                if (count == 2) {
                    notify('error', "@lang('Minimum 2 carousel cards are required')");
                    return;
                }
                $(this).closest('.carousel--card').remove();
                previewCard.remove();
            });

        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .form--control[type=file] {
            line-height: 1 !important;
            padding: 8px 2px !important;
            height: 40px;
        }

        .form--control[type=file]::-webkit-file-upload-button {
            padding: unset !important;
        }

        .form--control[type=file]::file-selector-button {
            padding: unset !important;
        }

        .body-content {
            position: relative;
        }

        .add-variable {
            position: absolute;
            top: 5px;
            right: 5px;
            z-index: 1;
            height: 30px;
            width: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 1px solid #e8e8e8;
            border-radius: 50% !important;
        }

        .dropdown-menu {
            background: hsl(var(--section-bg));
            border-radius: 6px;
            border: 0;
            padding: 0 !important;
            overflow: hidden;
        }

        .dropdown-menu li .dropdown-item {
            color: hsl(var(--black)) !important;
            cursor: pointer;
            margin: 0;
            padding: 8px 14px;
            border-bottom: 1px solid hsl(var(--black)/.1);
            transition: .2s linear;
        }

        .dropdown-menu li:last-child .dropdown-item {
            border-bottom: 0;
        }

        .dropdown-menu li .dropdown-item:hover {
            background-color: hsl(var(--base)/.2);
        }

        .custom-attribute-wrapper {
            display: flex;
            width: 100%;
            gap: 10px;
            align-items: flex-end;
        }

        .template-info-container__right .preview-item__content .card-item {
            width: 100%;
            border-radius: 10px;
        }

        .preview-item {
            position: sticky;
            top: 0;
            z-index: 1;
            right: 0;
        }

        .divider-title::after {
            position: absolute;
            content: '';
            top: 14px;
            right: -40px;
            background: #6b6b6b65;
            height: 2px;
            width: 80px;
        }


        .divider-title::before {
            position: absolute;
            content: '';
            top: 14px;
            left: -40px;
            background: #6b6b6b65;
            height: 2px;
            width: 80px;
        }

        .template-info-container__right .preview-item__content .card-item__thumb img {
            max-height: 210px
        }
    </style>
@endpush
