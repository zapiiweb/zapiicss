<div class="sidebar-menu flex-between">
    <div class="sidebar-menu__inner">
        <span class="sidebar-menu__close d-lg-none d-block">
            <i class="fas fa-times"></i>
        </span>
        <a href="javascript:void(0)" class="sidebar-collapse-btn d-none d-lg-flex">
            <i class="fas fa-chevron-left"></i>
        </a>
        <div class="sidebar-logo">
            <a href="{{ route('home') }}" class="sidebar-logo__link">
                <img src="{{ siteLogo('dark') }}" alt="logo" class="logo-expanded">
                <img src="{{ asset('assets/images/logo_icon/favicon.png') }}" alt="logo" class="logo-collapsed">
            </a>
        </div>
        <ul class="sidebar-menu-list">
            <x-permission_check permission="view dashboard">
                <li class="sidebar-menu-list__item {{ menuActive('user.home') }}">
                    <a href="{{ route('user.home') }}" class="sidebar-menu-list__link">
                        <span class="icon">
                            <i class="fa-solid fa-border-all"></i>
                        </span>
                        <span class="text">@lang('My Dashboard')</span>
                    </a>
                </li>
            </x-permission_check>
            <x-permission_check :permission="[
                'view contact',
                'view contact list',
                'view contact tag',
                'view template',
                'view campaign',
                'view chatbot',
                'view welcome message',
                'view shortlink',
                'view floater',
            ]">
                <li class="sidebar-menu-list__title">
                    <span class="text">@lang('MARKETING TOOLS')</span>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['view contact', 'view contact list', 'view contact tag']">
                <li class="sidebar-menu-list__item has-dropdown">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon">
                            <i class="fa-regular fa-id-card"></i>
                        </span>
                        <span class="text">@lang('Manage Contacts')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <x-permission_check permission="view contact">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.contact.*') }}">
                                    <a href="{{ route('user.contact.list') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Manage Contacts')</span>
                                    </a>
                                </li>
                            </x-permission_check>

                            <x-permission_check permission="view contact tag">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.contacttag.*') }}">
                                    <a href="{{ route('user.contacttag.list') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Manage Contact Tag')</span>
                                    </a>
                                </li>
                            </x-permission_check>

                            <x-permission_check permission="view contact list">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.contactlist.*') }}">
                                    <a href="{{ route('user.contactlist.list') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Manage Contact List')</span>
                                    </a>
                                </li>
                            </x-permission_check>

                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['view template', 'add template', 'delete template']">
                <li class="sidebar-menu-list__item has-dropdown {{ menuActive('user.template.*') }}">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon"><i class="fa-solid fa-envelope-square"></i></span>
                        <span class="text">@lang('Manage Templates')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <li class="sidebar-submenu-list__item {{ menuActive('user.template.create') }}">
                                <a href="{{ route('user.template.create') }}" class="sidebar-submenu-list__link">
                                    <span class="text">@lang('New Template')</span>
                                </a>
                            </li>
                            <li class="sidebar-submenu-list__item {{ menuActive('user.template.create.carousel') }}">
                                <a href="{{ route('user.template.create.carousel') }}"
                                    class="sidebar-submenu-list__link">
                                    <span class="text">@lang('Carousel Template')</span>
                                </a>
                            </li>
                            <li class="sidebar-submenu-list__item {{ menuActive('user.template.index') }}">
                                <a href="{{ route('user.template.index') }}" class="sidebar-submenu-list__link">
                                    <span class="text">@lang('All Template')</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['view campaign', 'add campaign', 'delete campaign']">
                <li class="sidebar-menu-list__item has-dropdown {{ menuActive('user.campaign.*') }}">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon"> <i class="fa-solid fa-volume-high"></i> </span>
                        <span class="text">@lang('Manage Campaigns')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <x-permission_check permission="add campaign">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.campaign.create') }}">
                                    <a href="{{ route('user.campaign.create') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('New Campaign')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                            <x-permission_check permission="view campaign">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.campaign.index') }}">
                                    <a href="{{ route('user.campaign.index') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('All Campaign')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['view chatbot', 'view welcome message']">
                <li class="sidebar-menu-list__item has-dropdown {{ menuActive('user.automation.*') }}">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon"><i class="fa-solid fa-envelope-square"></i></span>
                        <span class="text">@lang('Manage Automation')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <x-permission_check permission="view chatbot">
                                <li
                                    class="sidebar-submenu-list__item {{ menuActive('user.automation.chatbot.index') }}">
                                    <a href="{{ route('user.automation.chatbot.index') }}"
                                        class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Chatbot')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                            <x-permission_check permission="view welcome message">
                                <li
                                    class="sidebar-submenu-list__item {{ menuActive('user.automation.welcome.message') }}">
                                    <a href="{{ route('user.automation.welcome.message') }}"
                                        class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Welcome Message')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                            <li class="sidebar-submenu-list__item {{ menuActive('user.automation.ai.assistant') }}">
                                <a href="{{ route('user.automation.ai.assistant') }}"
                                    class="sidebar-submenu-list__link">
                                    <span class="text">@lang('AI Assistant')</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['add shortlink', 'view shortlink']">
                <li class="sidebar-menu-list__item has-dropdown {{ menuActive('user.shortlink.*') }}">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon"><i class="fa-solid fa-link"></i></span>
                        <span class="text">@lang('Manage ShortLink')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <x-permission_check permission="add shortlink">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.shortlink.create') }}">
                                    <a href="{{ route('user.shortlink.create') }}"
                                        class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Create ShortLink')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                            <x-permission_check permission="view shortlink">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.shortlink.index') }}">
                                    <a href="{{ route('user.shortlink.index') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Manage ShortLink')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['add floater', 'view floater']">
                <li class="sidebar-menu-list__item has-dropdown {{ menuActive('user.floater.*') }}">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon">
                            <i class="fa-brands fa-whatsapp"></i>
                        </span>
                        <span class="text">@lang('Manage Floaters')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <x-permission_check permission="add floater">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.floater.create') }}">
                                    <a href="{{ route('user.floater.create') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Create Floater')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                            <x-permission_check permission="view floater">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.floater.index') }}">
                                    <a href="{{ route('user.floater.index') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Manage Floater')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['add cta url', 'view cta url']">
                <li class="sidebar-menu-list__item has-dropdown {{ menuActive('user.cta-url.*') }}">
                    <a href="#" class="sidebar-menu-list__link">
                        <span class="icon">
                            <i class="fa-solid fa-paperclip"></i>
                        </span>
                        <span class="text">@lang('Manage CTA URL')</span>
                    </a>
                    <div class="sidebar-submenu">
                        <ul class="sidebar-submenu-list">
                            <x-permission_check permission="add cta url">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.cta-url.create') }}">
                                    <a href="{{ route('user.cta-url.create') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('Create URL')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                            <x-permission_check permission="view cta url">
                                <li class="sidebar-submenu-list__item {{ menuActive('user.cta-url.index') }}">
                                    <a href="{{ route('user.cta-url.index') }}" class="sidebar-submenu-list__link">
                                        <span class="text">@lang('CTA URl List')</span>
                                    </a>
                                </li>
                            </x-permission_check>
                        </ul>
                    </div>
                </li>
            </x-permission_check>
            <x-permission_check :permission="['view inbox', 'view customer', 'view agent']">
                <li class="sidebar-menu-list__title">
                    <span class="text">@lang('CRM TOOLS')</span>
                </li>
            </x-permission_check>
            <x-permission_check permission="view inbox">
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.inbox.list') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.inbox.*') }}">
                        <span class="icon"> <i class="fas fa-sms"></i> </span>
                        <span class="text">@lang('Manage Inbox')</span>
                    </a>
                </li>
            </x-permission_check>
            <x-permission_check permission="view customer">
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.customer.list') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.customer.*') }}">
                        <span class="icon"> <i class="fas fa-users"></i> </span>
                        <span class="text">@lang('Manage Customer')</span>
                    </a>
                </li>
            </x-permission_check>
            <x-permission_check permission="view agent">
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.agent.list') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.agent.*') }}">
                        <span class="icon"> <i class="fa-solid fa-users-gear"></i> </span>
                        <span class="text">@lang('Manage Agent')</span>
                    </a>
                </li>
            </x-permission_check>
            <x-permission_check permission="view ticket">
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('ticket.index') }}"
                        class="sidebar-menu-list__link {{ menuActive('ticket.index') }}">
                        <span class="icon"> <i class="fa-solid fa-tags"></i> </span>
                        <span class="text">@lang('Support Ticket')</span>
                    </a>
                </li>
            </x-permission_check>

            @if (isParentUser())
                <li class="sidebar-menu-list__title">
                    <span class="text">@lang('FINANCE')</span>
                </li>
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.deposit.history') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.deposit.*') }}">
                        <span class="icon"> <i class="fa-solid fa-money-bill-transfer"></i> </span>
                        <span class="text">@lang('Manage Deposit')</span>
                    </a>
                </li>
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.withdraw.history') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.withdraw*') }}">
                        <span class="icon"> <i class="fa-solid fa-wallet"></i> </span>
                        <span class="text">@lang('Manage Withdraw')</span>
                    </a>
                </li>
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.transactions') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.transactions') }}">
                        <span class="icon"><i class="fa-solid fa-right-left"></i></span>
                        <span class="text">@lang('Transactions Logs')</span>
                    </a>
                </li>
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.referral.index') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.referral.index') }}">
                        <span class="icon"> <i class="fa-solid fa-share-nodes"></i> </span>
                        <span class="text">@lang('Manage Referrals')</span>
                    </a>
                </li>
            @endif
            <li class="sidebar-menu-list__title">
                <span class="text">@lang('BILLING & PROFILE')</span>
            </li>

            @if (isParentUser())
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.whatsapp.account.index') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.whatsapp.account.*') }}">
                        <span class="icon"> <i class="fa-solid fa-phone"></i> </span>
                        <span class="text">@lang('Whatsapp Accounts')</span>
                    </a>
                </li>
                <li class="sidebar-menu-list__item">
                    <a href="{{ route('user.subscription.index') }}"
                        class="sidebar-menu-list__link {{ menuActive('user.subscription.index') }}">
                        <span class="icon"> <i class="fa-solid fa-dollar-sign"></i> </span>
                        <span class="text">@lang('Subscription Info')</span>
                    </a>
                </li>
            @endif
            <li class="sidebar-menu-list__item">
                <a href="{{ route('user.profile.setting') }}"
                    class="sidebar-menu-list__link {{ menuActive('user.profile.setting') }}">
                    <span class="icon"> <i class="fas fa-user"></i> </span>
                    <span class="text">@lang('Manage Profile')</span>
                </a>
            </li>
        </ul>
    </div>
</div>
