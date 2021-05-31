<?php

namespace App\Http\View\Composers;

use App\Support\Markdown;
use App\Traits\GetsLocalizedYamlContent;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\View\View;

class MetadataComposer
{
    use GetsLocalizedYamlContent;
    /**
     * The current session.
     *
     * @var Store
     */
    protected $session;

    /**
     * The current request.
     *
     * @var Request
     */
    protected $request;

    /**
     * @var Markdown
     */
    protected $markdown;

    /**
     * Create a new ModalComposer.
     *
     * @param Store $session
     */
    public function __construct(Request $request, Store $session)
    {
        $this->request = $request;
        $this->session = $session;
        $this->markdown = new Markdown();
    }

    /**
     * Bind modal data to the view.
     *
     * @param View $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $metaStructuredData = [];
        if (Breadcrumbs::exists()) {
            $breadcrumbs = Breadcrumbs::generate();
            $items = [];
            foreach ($breadcrumbs as $key => $breadcrumb) {
                $item = [
                    '@type' => 'ListItem',
                    'position' => $key + 1,
                    'name' => $breadcrumb->title,
                    'item' => $breadcrumb->url,
                ];
                array_push($items, $item);
            }
            $breadcrumbs = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $items,
            ];
            $metaStructuredData['breadcrumbs'] = $breadcrumbs;
        }
        switch (optional($this->request->route())->getName()) {
            case 'faq':
                $faqs = $this->getLocalizedYamlContent('faqs');
                $items = [];
                foreach ($faqs as $faq) {
                    $item = [
                        '@type' => 'Question',
                        'name' => $faq['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $this->markdown->convertToHtml($faq['answer']),
                        ],
                    ];
                    array_push($items, $item);
                }
                $specificStructuredData = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $items,
                ];
                break;
            case 'how-to-join':
                $steps = $this->getLocalizedYamlContent('how-to-join-steps');
                $items = [];
                foreach ($steps as $step) {
                    $item = [
                        '@type' => 'HowToStep',
                        'name' => $step['name'],
                        'text' => $this->markdown->convertToHtml($step['description']),
                        'url' => url(route('how-to-join')),
                        'image' => [
                            '@type' => 'ImageObject',
                            'url' => asset('images/how-to-join-steps/' . $step['image'] . '.svg'),
                            'height' => '406',
                            'width' => '305',
                        ],
                    ];
                    array_push($items, $item);
                }
                $specificStructuredData = [
                    '@context' => 'https://schema.org',
                    '@type' => 'HowTo',
                    'name' => __('Come partecipare'),
                    'step' => $items,
                ];
                break;
        }

        if (isset($specificStructuredData)) {
            $metaStructuredData['specificStructuredData'] = $specificStructuredData;
        }

        $view->with('metaStructuredData', $metaStructuredData);
    }
}
