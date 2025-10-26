<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\ApiQuery;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use ApiQuery;

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id')
            ->where('is_agent', Status::YES);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function chatbot()
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function ctaUrl()
    {
        return $this->belongsTo(CtaUrl::class, 'cta_url_id');
    }

    public function statusBadge(): Attribute
    {
        return new Attribute(function () {
            $html = '';

            if ($this->status == Status::SCHEDULED) {
                // Mensagem agendada/pendente de envio (Baileys aguardando webhook)
                $html = '<i class="la la-clock text--warning"></i>';
            } elseif ($this->status == Status::SENT) {
                // Mensagem enviada (um check cinza)
                $html = '<i class="la la-check text--secondary"></i>';
            } elseif ($this->status == Status::DELIVERED) {
                // Mensagem entregue (dois checks cinzas)
                $html = '<i class="la la-check-double text--secondary"></i>';
            } elseif ($this->status == Status::READ) {
                // Mensagem lida (dois checks verdes)
                $html = '<i class="la la-check-double text--success"></i>';
            } elseif ($this->status == Status::FAILED) {
                // Mensagem com erro - permite reenvio
                $html = '<i class="las la-redo-alt text--warning resender" data-id="' . e($this->id) . '"></i>';
            } else {
                // Fallback para qualquer outro status desconhecido
                $html = '<i class="la la-question-circle text--secondary"></i>';
            }

            return $html;
        });
    }
}
