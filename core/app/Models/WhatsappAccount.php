<?php

namespace App\Models;

use App\Traits\ApiQuery;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class WhatsappAccount extends Model
{
    use ApiQuery;

    protected $guarded = ['id'];

    protected $casts = [
        'baileys_connected' => 'boolean',
        'baileys_connected_at' => 'datetime',
        'connection_type' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chatbots()
    {
        return $this->hasMany(Chatbot::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class, 'whatsapp_account_id');
    }

    public function welcomeMessage()
    {
        return $this->hasOne(WelcomeMessage::class);
    }

    public function verificationStatusBadge(): Attribute
    {
        return new Attribute(function () {
            $html = '';
            if ($this->code_verification_status == 'VERIFIED') {
                $html = '<span class="badge custom--badge badge--success">' . trans('Verified') . '</span>';
            }elseif ($this->code_verification_status == 'EXPIRED') {
                $html = '<span class="badge custom--badge badge--warning">' . trans('Expired') . '</span>';   
            }else {
                $html = '<span class="badge custom--badge badge--danger">' . trans('Not Verified') . '</span>';
            }
            return $html;
        });
    }
}

