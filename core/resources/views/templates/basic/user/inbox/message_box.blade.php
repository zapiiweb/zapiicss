<div class="chat-box">
    <div class="chat-box__shape">
        <img src="{{ getImage($activeTemplateTrue . 'images/chat-bg.png') }}" alt="">
    </div>
    <div class="chat-box__header">
        <div class="d-flex align-items-center gap-3">
            <div class="chat-box__item">
                <div class="chat-box__thumb">
                    <img class="avatar contact__profile"
                        src="{{ getImage($activeTemplateTrue . 'images/ch-1.png', isAvatar: true) }}" alt="image">
                </div>
                <div class="chat-box__content">
                    <p class="name contact__name"></p>
                    <p class="text contact__mobile"></p>
                </div>
            </div>
        </div>
    </div>
    <div class="msg-body">

    </div>
    <div class="chat-box__footer">
        <form class="chat-send-area no-submit-loader" id="message-form">
            @csrf
            <div class="btn-group">
                <div class="chat-media">
                    <button class="chat-media__btn" type="button"> <i class="las la-plus"></i> </button>
                    <div class="chat-media__list">
                        <label for="cta_url" class="media-item cta-url-btn">
                            <span class="icon">
                                <i class="fa-solid fa-paperclip"></i>
                            </span>
                            <span class="title">@lang('CTA Url')</span>
                            <input hidden class="media-input" name="cta_url_id" type="number">
                        </label>
                        <label for="audio" class="media-item media_selector"
                            data-media-type="{{ Status::AUDIO_TYPE_MESSAGE }}">
                            <span class="icon">
                                <i class="fas fa-file-audio"></i>
                            </span>
                            <span class="title">@lang('Audio')</span>
                            <input hidden class="media-input" name="audio" type="file" accept="audio/*">
                        </label>
                        <label for="document" class="media-item media_selector"
                            data-media-type="{{ Status::DOCUMENT_TYPE_MESSAGE }}">
                            <span class="icon">
                                <i class="fas fa-file-alt"></i>
                            </span>
                            <span class="title">@lang('Document')</span>
                            <input hidden class="media-input" name="document" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar">
                        </label>
                        <label for="video" class="media-item media_selector"
                            data-media-type="{{ Status::VIDEO_TYPE_MESSAGE }}">
                            <span class="icon">
                                <i class="fas fa-video"></i>
                            </span>
                            <span class="title">@lang('Video')</span>
                            <input class="media-input" name="video" type="file" accept="video/*" hidden>
                        </label>
                    </div>
                    <div class="chat-url__list">
                        @forelse ($ctaUrls as $url)
                            <label class="url-item select-url" data-id="{{ @$url->id }}"
                                data-name="{{ @$url->name }}" data-bs-toggle="tooltip"
                                data-bs-title="{{ @$url->cta_url }}">
                                <span class="icon">
                                    <i class="fa-solid fa-paperclip"></i>
                                </span>
                                <span class="title">{{ @$url->name }}</span>
                            </label>
                        @empty
                            <label class="url-item">
                                <span class="icon">
                                    <i class="fa-solid fa-ban"></i>
                                </span>
                                <span class="title">@lang('No CTA Link')</span>
                            </label>
                        @endforelse
                    </div>
                </div>
                <label for="image" class="btn-item image-upload-btn media_selector"
                    data-media-type="{{ Status::IMAGE_TYPE_MESSAGE }}">
                    <i class="fa-solid fa-image"></i>
                    <input hidden class="image-input" name="image" type="file" accept=".jpg, .jpeg, .png, .webp">
                </label>
            </div>

            <div class="image-preview-container"></div>
            <div class="input-area d-flex align-center gap-2">
                <span class="emoji-icon cursor-pointer">
                    <i class="far fa-smile"></i>
                </span>
                <div class="emoji-container"></div>
                <div class="input-group">
                    <textarea name="message" class="form--control message-input" placeholder="@lang('Type your message here')" autocomplete="off"></textarea>
                </div>
                <button class="chating-btn" type="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none">
                        <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M22 2L11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>
