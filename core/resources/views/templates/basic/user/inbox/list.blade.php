@php
    $selectedConversationId = request()->conversation ?? 0;
@endphp
@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="chatbox-area">
        @include('Template::user.inbox.conversation')
        <div class="chatbox-area__body @if (!$selectedConversationId) d-none @endif">
            @include('Template::user.inbox.message_box')
            @include('Template::user.inbox.contact')
        </div>
        <div class="empty-conversation @if ($selectedConversationId) d-none @endif">
            <img class="conversation-empty-image" src="{{ asset($activeTemplateTrue . 'images/conversation_empty.png') }}"
                alt="img">
        </div>
    </div>

    <!-- Modal de Preview de Imagem -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0 text-center position-relative">
                    <button type="button" class="btn position-absolute top-0 end-0" data-bs-dismiss="modal" aria-label="Close" style="z-index: 1050; width: 40px; height: 40px; border: none; background: transparent; color: white; font-size: 32px; line-height: 1; padding: 0; margin: 5px;">
                        <i class="las la-times"></i>
                    </button>
                    <img id="previewImage" src="" alt="Preview" class="img-fluid" style="max-height: 80vh; width: auto;">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-lib')
    <script src="{{ asset($activeTemplateTrue . 'js/emoji-mart.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/pusher.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/broadcasting.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            const $messageBody = $('.msg-body');
            const $messageForm = $('#message-form');
            let isSubmitting = false;

            // Função para obter o separador de data em português
            function getDateSeparator(dateString) {
                const messageDate = new Date(dateString);
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);

                // Comparar apenas as datas, ignorando as horas
                const isToday = messageDate.toDateString() === today.toDateString();
                const isYesterday = messageDate.toDateString() === yesterday.toDateString();

                if (isToday) {
                    return 'Hoje';
                } else if (isYesterday) {
                    return 'Ontem';
                } else {
                    // Verificar se está na semana atual
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay()); // Domingo da semana atual
                    
                    if (messageDate >= weekStart && messageDate < today) {
                        const diasSemana = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];
                        return diasSemana[messageDate.getDay()];
                    } else {
                        // Para datas mais antigas, mostrar data completa
                        const dia = String(messageDate.getDate()).padStart(2, '0');
                        const mes = String(messageDate.getMonth() + 1).padStart(2, '0');
                        const ano = messageDate.getFullYear();
                        return `${dia}/${mes}/${ano}`;
                    }
                }
            }

            // Função para verificar se precisa inserir separador de data
            function checkAndInsertDateSeparator(newMessageHtml) {
                const $tempDiv = $('<div>').html(newMessageHtml);
                const $newMessage = $tempDiv.find('.single-message').first();
                
                if ($newMessage.length === 0) return newMessageHtml;

                // Extrair data da nova mensagem do atributo data-message-id ou da mensagem
                const messageTimeText = $newMessage.find('.message-time').first().text().trim();
                const lastMessage = $messageBody.find('.single-message').last();
                
                if (lastMessage.length === 0) {
                    // Primeira mensagem, não precisa de separador
                    return newMessageHtml;
                }

                // Comparar datas (simplificado - assumindo que a data está disponível)
                // Como não temos a data completa facilmente acessível, vamos deixar o servidor cuidar disso
                return newMessageHtml;
            }


            $messageForm.on('submit', function(e) {
                e.preventDefault();
                if (isSubmitting) return;
                isSubmitting = true;

                const formData = new FormData(this);

                const $submitBtn = $messageForm.find('button[type=submit]');

                formData.append('conversation_id', window.conversation_id);
                formData.append('whatsapp_account_id', window.whatsapp_account_id);

                $.ajax({
                    url: "{{ route('user.inbox.message.send') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $submitBtn.attr('disabled', true).addClass('disabled');
                        $submitBtn.html(
                            `<div class="spinner-border text--base" role="status"></div>`);
                    },
                    success: function(response) {
                        if (response.status == 'success') {
                            $messageForm.trigger('reset');
                            $messageBody.append(response.data.html);
                            if (response.data.conversationId && response.data.lastMessageHtml) {
                                $(`.chat-list__item[data-id="${response.data.conversationId}"]`)
                                    .find('.last-message').html(response.data.lastMessageHtml);
                            }

                            setTimeout(() => {
                                $messageBody.scrollTop($messageBody[0].scrollHeight);
                            }, 50);
                        } else {
                            notify('error', response.message || "@lang('Something went to wrong')");
                        }
                    },
                    complete: function() {
                        isSubmitting = false;
                        $submitBtn.attr('disabled', false).removeClass('disabled');
                        $submitBtn.html(messageSendSvg());
                        $urlInput.val('');
                        $('.message-input').attr('readonly', false);
                        clearImagePreview();
                    }
                });
            });

            $(document).on('submit', '.contactSearch', function(e) {
                e.preventDefault();
                let value = $(this).find('input[name=search]').val();
                window.fetchChatList(value);
            });

            $(document).on('click', '.resender', function() {
                if (isSubmitting) return;

                const $this = $(this);

                const messageId = $this.data('id');
                if (!messageId) return;

                isSubmitting = true;
                $this.addClass('loading');

                $.ajax({
                    url: "{{ route('user.inbox.message.resend') }}",
                    type: "POST",
                    data: {
                        'message_id': messageId,
                        '_token': "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.status == 'success') {
                            $messageBody.find(`[data-message-id="${messageId}"]`).remove();
                            $messageBody.append(response.data.html);
                            $messageBody.scrollTop($messageBody[0].scrollHeight);
                        }
                    },
                    error: function() {
                        notify('error', "@lang('Something went wrong.')");
                    }
                }).always(function() {
                    isSubmitting = false;
                    $this.removeClass('loading');
                });
            });

            const $messageInput = $(".message-input");

            $messageInput.keydown(function(e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    if (e.shiftKey) {
                        $(this).val($(this).val() + "\n");
                    } else {
                        $(this).closest("form").submit();
                    }
                }
            });

            $messageInput.on("focus", function() {
                if (!window.conversation_id) return;
                let route = "{{ route('user.inbox.message.status', ':id') }}";
                $.ajax({
                    url: route.replace(':id', window.conversation_id),
                    type: "GET",
                    success: function(response) {
                        if (response.status == 'success') {
                            if (response.data.unseenMessageCount == 0) {
                                $('.chat-list__item[data-id="' + window.conversation_id + '"]')
                                    .find('.unseen-message').html('');
                                $('.chat-list__item[data-id="' + window.conversation_id + '"]')
                                    .find('.last-message-text').removeClass('text--bold');
                            }
                        }
                    }
                });
            });

            const $emojiIcon = $(".emoji-icon");
            const $emojiContainer = $(".emoji-container");

            const picker = new EmojiMart.Picker({
                onEmojiSelect: (emoji) => {
                    $messageInput.val($messageInput.val() + emoji.native);
                }
            });

            $emojiContainer.append(picker);

            $emojiIcon.on("click", function(e) {
                e.stopPropagation();
                if (isUrlMessage()) return;
                $emojiContainer.toggle();

                if ($emojiContainer.is(":visible")) {
                    $emojiIcon.html('<i class="far fa-times-circle"></i>');
                } else {
                    $emojiIcon.html('<i class="far fa-smile"></i>');
                }
            });


            $(document).on("click", function(e) {
                if (!$(e.target).closest($emojiContainer).length && !$(e.target).closest($emojiIcon).length) {
                    $emojiContainer.hide();
                    $emojiIcon.html('<i class="far fa-smile"></i>');
                }
            });

            const $imageInput = $(".image-input");
            const $documentInput = $(".media-item input[name='document']");
            const $videoInput = $(".media-item input[name='video']");
            const $audioInput = $(".media-item input[name='audio']");
            const $urlInput = $('input[name=cta_url_id]');
            const $previewContainer = $(".image-preview-container");

            // Image Preview
            $imageInput.on("change", function(event) {
                previewFile(event, "image");
            });

            // Document Preview
            $documentInput.on("change", function(event) {
                previewFile(event, "document");
            });

            // Video Preview
            $videoInput.on("change", function(event) {
                previewFile(event, "video");
            });

            // Audio
            $audioInput.on("change", function(event) {
                previewFile(event, "audio");
            });

            // Audio
            $urlInput.on("change", function(event) {
                alert(234324);
                // previewFile(event, "audio");
            });

            $('.select-url').on('click', function(e) {
                let url = $(this).data('id');
                $urlInput.val(url);
                let name = $(this).data('name');
                previewFile(event, "url", name);
            });

            // Block clicks on labels with media_selector if URL exists
            $('.media_selector').on('click', function(e) {
                if (isUrlMessage()) {
                    e.preventDefault();
                    e.stopImmediatePropagation(); // stop the event from reaching input
                    return false;
                }
            });

            $('.media-input').on('click', function(e) {
                if (isUrlMessage()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
            });

            function isUrlMessage() {
                if ($urlInput.val()) {
                    notify('error', 'URL message do not support anything else.');
                    return true;
                }
                return false;
            }

            function previewFile(event, type, name = null) {

                if (type == 'url' && name) {
                    $('.message-input').attr('readonly', true);
                    $('.chat-url__list').removeClass('show');
                    $previewContainer.empty();
                    $previewContainer.append(`
                    <div class="preview-item url-preview text-dark">
                        ${name}
                        <button class="remove-preview">&times;</button>
                    </div>
                    `);

                    return;
                }

                const file = event.target.files[0];
                if (!file && !name) return;

                const reader = new FileReader();

                reader.onload = function(e) {
                    $previewContainer.empty();

                    let previewContent = "";

                    if (type === "image") {
                        previewContent =
                            `<img src="${e.target.result}" alt="Image Preview" class="preview-image preview-item">`;
                    } else if (type === "document") {
                        let parts = file.name.split('.');
                        let name = parts[0];
                        let ext = parts[1];
                        let shortName = name.slice(0, 10);

                        let result = shortName + '.' + ext;
                        previewContent =
                            `<a href="${e.target.result}" target="_blank" class="file-preview">${result}</a>`;
                    } else if (type === "video") {
                        previewContent = `<video controls class="preview-item preview-video">
                        <source src="${e.target.result}" type="${file.type}">
                            Your browser does not support the video tag.
                        </video>`;
                    } else if (type === "audio") {
                        previewContent = `<audio controls class="preview-item preview-audio">
                        <source src="${e.target.result}" type="${file.type}">
                            Your browser does not support the audio tag.
                        </audio>`;
                    }

                    $previewContainer.append(`
                    <div class="preview-item image-preview">
                        ${previewContent}
                        <button class="remove-preview">&times;</button>
                    </div>
                    `);
                };

                reader.readAsDataURL(file);
            }

            $previewContainer.on("click", ".remove-preview", function() {
                $(this).closest(".image-preview").remove();
                clearImagePreview();
                $('.message-input').attr('readonly', false);
                $('.chat-url__list').removeClass('show');
            });

            function clearImagePreview() {
                $previewContainer.empty();
                $imageInput.val("");
                $documentInput.val("");
                $videoInput.val("");
                $audioInput.val("");
                $urlInput.val("");
            }

            const pusherConnection = (eventName, whatsapp) => {
                pusher.connection.bind('connected', () => {
                    const SOCKET_ID = pusher.connection.socket_id;
                    const CHANNEL_NAME = `private-${eventName}-${whatsapp}`;
                    pusher.config.authEndpoint = makeAuthEndPointForPusher(SOCKET_ID, CHANNEL_NAME);
                    let channel = pusher.subscribe(CHANNEL_NAME);
                    channel.bind('pusher:subscription_succeeded', function() {
                        channel.bind(eventName, function(data) {
                            $("body").find('.empty-conversation').remove();
                            $("body").find(".chatbox-area__body").removeClass('d-none');
                            const {
                                messageId
                            } = data.data;

                            if ($messageBody.find(`[data-message-id="${messageId}"]`)
                                .length) {
                                $messageBody.find(
                                        `[data-message-id="${data.data.messageId}"]`)
                                    .find('.message-status').html(data.data.statusHtml);
                            } else {

                                if (parseInt(data.data.conversationId) === parseInt(window.conversation_id)) {
                                    $messageBody.append(data.data.html);
                                    setTimeout(() => {
                                        $messageBody.scrollTop($messageBody[0]
                                            .scrollHeight);
                                    }, 50);
                                }

                                if (data.data.newContact) {
                                    window.conversation_id = data.data.conversationId;
                                    window.fetchChatList("", true);
                                } else {
                                    let targetConversation = $('body').find(
                                        `.chat-list__item[data-id="${data.data.conversationId}"]`
                                    );

                                    if (data.data.lastMessageHtml) {
                                        targetConversation.find('.last-message').html(
                                            data.data.lastMessageHtml);

                                        targetConversation.find('.unseen-message').html(
                                            `<span class="number">${data.data.unseenMessage}</span>`
                                        );
                                        targetConversation.find('.last-message-at').text(
                                            data.data.lastMessageAt);
                                        
                                        targetConversation.prependTo('#chat-list');
                                    }
                                }
                            }

                        })
                    });
                });
            };


            pusherConnection('receive-message', "{{ $whatsappAccount->id }}");

            $('.chat-media__btn, .chat-media__list').on('click', function() {
                $('.chat-media__list').toggleClass('show');
            });

            $('.chat-media__btn').on('click', function() {
                $('.chat-url__list').removeClass('show');
            });

            $('.cta-url-btn').on('click', function(e) {
                $('.chat-url__list').toggleClass('show');
            });

            $("select[name=whatsapp_account_id]").parent().find('.select2.select2-container').addClass('mb-2');

            function messageSendSvg() {
                return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M22 2L11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>`
            }

            $(document).on('click', '.ai-response-button', function(e) {
                e.preventDefault();
                let $message = $(this).data('customer-message');

                if (!$message) return;

                if (isSubmitting) return;
                isSubmitting = true;
                $messageInput.attr('readonly', true).attr('placeholder', '@lang("Generating response from AI...")');

                $.ajax({
                    url: "{{ route('user.inbox.generate.message') }}",
                    type: "POST",
                    data: {
                        message: $message,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.status == 'success') {
                           $messageInput.val(response.data.ai_response);
                        } else {
                            notify('error', response.message || "@lang('Something went to wrong')");
                        }
                    },
                    complete: function() {
                        isSubmitting = false;
                        $messageInput.attr('readonly', false).attr('placeholder', '@lang("Type your message here message...")');
                    }
                });
            });

            // Função para abrir modal de preview de imagem
            $(document).on('click', '.message-image-preview', function(e) {
                e.preventDefault();
                const imageSrc = $(this).attr('src');
                $('#previewImage').attr('src', imageSrc);
                $('#imagePreviewModal').modal('show');
            });

        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .message-input {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .resender {
            cursor: pointer !important;
        }

        .resender.loading {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .media-item {
            position: relative;
        }

        .image-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            top: 12px !important;
            cursor: pointer;
        }

        .media-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer !important;
        }

        .image-upload-btn,
        .image-upload-btn i {
            cursor: pointer;
        }

        .emoji-container {
            position: absolute;
            display: none;
            z-index: 999;
            bottom: 55px;
            left: 13px;
            max-width: 100%;
        }

        .file-preview {
            height: 56px;
            padding-left: 5px;
            font-size: 14px;
        }

        .preview-item,
        .image-preview img {
            max-width: 105px;
            max-height: 55px;
            border-radius: 5px;
            border: 1px solid #ddd;
            object-fit: cover;
        }

        .image-preview-container {
            display: flex;
            align-items: flex-end;

        }

        @media (max-width: 424px) {
            .image-preview-container {
                display: flex;
                align-items: flex-end;
                position: absolute;
                left: 72px;
                top: 50%;
                transform: translateY(-50%);
            }

            .preview-item,
            .image-preview img {
                width: 50px;
                height: 50px;
            }

            .file-preview {
                height: 50px;
                overflow-y: auto;
                background: #fff;
            }
        }

        .image-preview {
            position: relative;
            display: inline-block;
        }

        .url-preview {
            position: relative;
            display: inline-block;
            height: 100%;
            max-width: 200px !important;
            width: 80px !important;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
        }

        .remove-preview {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .chatbody:has(.empty-message) {
            min-height: calc(100% - 180px);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .chatbox-wrapper:has(.empty-message) {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .body-right.contact__details:has(.empty-message) {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .empty-conversation {
            display: flex;
            justify-content: center;
            align-items: center;
            width: calc(100% - 370px) !important;
        }

        @media screen and (max-width: 1399px) {
            .empty-conversation {
                width: calc(100% - 280px) !important;
            }
        }

        @media screen and (max-width: 767px) {
            .empty-conversation {
                width: 100% !important;
            }
        }

        .empty-conversation img {
            max-width: 300px;
        }

        @media screen and (max-width: 575px) {
            .empty-conversation img {
                max-width: 200px;
            }
        }

        /* Date Separator Styles */
        .date-separator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            position: relative;
        }

        .date-separator::before,
        .date-separator::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, transparent, #d1d5db 50%, transparent);
        }

        .date-separator__text {
            padding: 5px 15px;
            background-color: #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 10px;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        @media screen and (max-width: 575px) {
            .date-separator {
                margin: 15px 0;
            }

            .date-separator__text {
                font-size: 11px;
                padding: 4px 12px;
            }
        }
    
    </style>
@endpush
