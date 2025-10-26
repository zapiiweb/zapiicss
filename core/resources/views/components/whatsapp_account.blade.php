@php
    $whatsappAccounts = App\Models\WhatsappAccount::where('user_id', getParentUser()->id)->get();
    $defaultAccount = $whatsappAccounts->where('is_default', Status::YES)->first();
@endphp

@props(['isHide' => false])

@if (!$isHide || $whatsappAccounts->count() > 1)
    <select class="form--control select2 form-two" required name="whatsapp_account_id">
        @foreach ($whatsappAccounts as $whatsappAccount)
            <option value="{{ $whatsappAccount->id }}" @selected($whatsappAccount->id == (request()->whatsapp_account_id ?? old('whatsapp_account_id', $defaultAccount->id ?? null)))>
                {{ __($whatsappAccount->business_name) }} ({{ $whatsappAccount->phone_number }})
            </option>
        @endforeach
    </select>
@endif
