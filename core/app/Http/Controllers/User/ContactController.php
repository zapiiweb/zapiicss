<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactTag;
use App\Traits\ContactManager;


class ContactController extends Controller
{
    use ContactManager;

    public function edit($id)
    {
        $user           = getParentUser();
        $contact        = Contact::where('user_id', $user->id)->findOrFail($id);
        $pageTitle      = "Edit Contact - " . $contact->fullName;
        $countries      = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $contactLists   = ContactList::where('user_id', $user->id)->orderBy('name')->get();
        $contactTags    = ContactTag::where('user_id', $user->id)->orderBy('name')->get();
        $existingTagId  = $contact->tags()->pluck('contact_tag_id')->toArray();
        $existingListId = $contact->lists()->pluck('contact_list_id')->toArray();

        return view('Template::user.contact.edit', compact('pageTitle', 'countries', 'contact', 'contactLists', 'contactTags', 'existingTagId', 'existingListId'));
    }
}
