<?php

namespace App\Transformers;

use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;
use App\Models\Website;
use League\Fractal\TransformerAbstract;

class WebsiteTransformer extends TransformerAbstract
{
    /**
     * @param \App\Models\Website $website
     *
     * @return array
     */
    public function transform(Website $website)
    {
        $data = [
            'url' => '<a href="http://' . $website->url . '">' . $website->url . '</a>',
            'type' => WebsiteType::getDescription($website->type),
            'added_at' => $website->created_at->format('d/m/Y'),
            'last_month_visits' => $website->getLastMonthVisits(),
            'status' => WebsiteStatus::getDescription($website->status),
            'actions' => [
                [
                    'link' => route('website-javascript-snippet', ['website' => $website], false),
                    'label' => __('ui.pages.websites.index.view_javascript_snippet'),
                ],
            ],
            'control' => '',
        ];

        if (WebsiteStatus::PENDING != $website->status) {
            $data['actions'][] = [
                'link' => route('analytics-service-login', [], false),
                'label' => __('ui.pages.websites.index.go_to_analytics_service'),
            ];
        }

        if ((WebsiteStatus::PENDING == $website->status || auth()->user()->can('manage-sites')) && WebsiteType::PRIMARY != $website->type) {
            $data['actions'][] = [
                'link' => route('websites-edit', ['website' => $website], false),
                'label' => __('ui.pages.websites.index.edit_website'),
            ];
        }

        return $data;
    }
}
