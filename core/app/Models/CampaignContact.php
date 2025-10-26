<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CampaignContact extends Model
{
    protected $guard = ['id'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * specified column for export with column manipulation 
     *
     * @var array
     */
    public function exportColumns(): array
    {
        return  [
            'campaign_id' => [
                'name' => "Campaign",
                "callback" => function ($item) {
                    return $item->campaign->title;
                }
            ],
            'contact_id' => [
                'name' => "Contact",
                "callback" => function ($item) {
                    return $item->contact->mobileNumber;
                }
            ],
            'status' => [
                'name' => "Status",
                "callback" => function ($item) {
                    return strip_tags($item->statusBadge);
                }
            ],
            'send_at' => [
                'name'     => "Send At",
                "callback" => function ($item) {
                    return showDateTime($item->send_at, lang: 'en');
                }
            ],
            'created_at' => [
                'name'     => "Created At",
                "callback" => function ($item) {
                    return showDateTime($item->created_at, lang: 'en');
                }
            ],
            'updated_at' => [
                'name'     => "Updated At",
                "callback" => function ($item) {
                    return showDateTime($item->updated_at, lang: 'en');
                }
            ]
        ];
    }

    public function statusBadge(): Attribute
    {
        return new Attribute(function () {
            $html = '';
            if ($this->status == Status::CAMPAIGN_MESSAGE_NOT_SENT) {
                $html = '<span class="custom--badge badge--secondary">Not Sent</span>';
            } elseif ($this->status == Status::CAMPAIGN_MESSAGE_IS_SENT) {
                $html = '<span class="custom--badge badge--primary">Sent</span>';
            } elseif ($this->status == Status::CAMPAIGN_MESSAGE_IS_FAILED) {
                $html = '<span class="custom--badge badge--danger">Failed</span>';
            } else {
                $html = '<span class="custom--badge badge--success">Success</span>';
            }

            return $html;
        });
    }
}
