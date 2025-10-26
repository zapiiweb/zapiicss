<?php

namespace Database\Seeders;

use App\Models\AgentPermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // php artisan db:seed --class=PermissionSeeder
    public function run(): void
    {
        $permissions = [
            "contact" => [
                "view contact",
                "add contact",
                "edit contact",
                "delete contact",
            ],
            "contact list" => [
                "view contact list",
                "add contact list",
                "edit contact list",
                "delete contact list",
                "view list contact",
                "add contact to list",
                "remove contact from list",
            ],
            "contact tag" => [
                "view contact tag",
                "add contact tag",
                "edit contact tag",
                "delete contact tag",
            ],
            "whatsapp" => [
                "view inbox",
                "send message",
                "view contact name",
                "view contact mobile",
                "view contact profile",
            ],
            "customer" => [
                "view customer",
                "add customer",
                "edit customer",
                "delete customer",
            ],
            "template" => [
                "view template",
                "edit template",
                "add template",
                "delete template",
            ],
            "cta url" => [
                "view cta url",
                "add cta url",
                "delete cta url",
            ],
            "ai assistant" => [
                "ai assistant settings",
            ],
            "campaign" => [
                "view campaign",
                "add campaign",
                "edit campaign",
                "delete campaign",
            ],
            "chatbot" => [
                "view chatbot",
                "add chatbot",
                "edit chatbot",
                "delete chatbot"
            ],
            "welcome message" => [
                "view welcome message",
                "add welcome message",
                "edit welcome message",
            ],
            "agent" => [
                "view agent",
                "add agent",
                "edit agent",
                "view permission",
                "assign permission",
                "delete agent",
            ],
            "shortlink" => [
                "view shortlink",
                "add shortlink",
                "edit shortlink",
                "delete shortlink",
            ],
            "floater" => [
                "view floater",
                "add floater",
                "delete floater",
            ],
            "other" => [
                "view dashboard",
                "view wallet",
                "view subscription",
            ]
        ];

        foreach ($permissions as $k => $permission) {
            foreach ($permission as  $item) {
                $exists = AgentPermission::where("name", $item)->where('group_name', $k)->exists();
                if ($exists) continue;
                $permission             = new AgentPermission();
                $permission->name       = $item;
                $permission->group_name = $k;
                $permission->guard_name = "web";
                $permission->save();
            }
        }
    }
}
