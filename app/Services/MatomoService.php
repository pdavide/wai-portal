<?php

namespace App\Services;

use App\Contracts\AnalyticsService as AnalyticsServiceContract;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Exceptions\AnalyticsServiceException;
use App\Exceptions\CommandErrorException;
use GuzzleHttp\Client as APIClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;

/**
 * Matomo implementation of Analytics Service.
 */
class MatomoService implements AnalyticsServiceContract
{
    /**
     * Map application website access levels into Matomo Service ones.
     *
     * @var array the mappings
     */
    private const ACCESS_LEVELS_MAPPINGS = [
        WebsiteAccessType::NO_ACCESS => 'noaccess',
        WebsiteAccessType::VIEW => 'view',
        WebsiteAccessType::WRITE => 'write',
        WebsiteAccessType::ADMIN => 'admin',
    ];

    /**
     * Map application website archiving status into Matomo Service ones.
     * Boolean logic is inverted because the mapped value is passed to
     * the `disable` parameter of the `DisableTracking.changeDisableState`
     * API endpoint.
     *
     * @var array the mappings
     */
    private const ARCHIVE_STATUS_MAPPINGS = [
        WebsiteStatus::ACTIVE => 'off',
        WebsiteStatus::ARCHIVED => 'on',
    ];

    /**
     * Local service URL.
     *
     * @var string the local URL
     */
    protected $serviceBaseUri;

    /**
     * Public service URL.
     *
     * @var string the public URL
     */
    protected $servicePublicUrl;

    /**
     * SSL verification flag.
     *
     * @var bool true to check SSL certificates, false to skip
     */
    protected $SSLVerify;

    /**
     * Authentication token for API calls.
     *
     * @var string the authentication toke
     */
    protected $tokenAuth;

    /**
     * Create a new Matomo Service instance.
     */
    public function __construct()
    {
        $this->serviceBaseUri = config('analytics-service.api_base_uri');
        $this->servicePublicUrl = config('analytics-service.public_url');
        $this->SSLVerify = config('analytics-service.ssl_verify');
        $this->tokenAuth = config('analytics-service.admin_token');
    }

    /**
     * Register a new site in the Analytics Service.
     *
     * @param string $siteName the website name
     * @param string $url the website URL
     * @param string $group the website group
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return int the Analytics Service website ID
     */
    public function registerSite(string $siteName, string $url, string $group): int
    {
        $params = [
            'method' => 'SitesManager.addSite',
            'siteName' => $siteName,
            'urls' => $url,
            'group' => $group,
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'token_auth' => $this->tokenAuth,
        ];

        if (app()->environment('production')) {
            $params['excludeUnknownUrls'] = true;
        }

        return $this->apiCall($params)['value'];
    }

    /**
     * Updated an existing site in the Analytics Service.
     *
     * @param string $idSite the Analytics Service website ID
     * @param string $siteName the website name
     * @param string $url the website URL
     * @param string $group the website group
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     */
    public function updateSite(string $idSite, string $siteName, string $url, string $group): void
    {
        $params = [
            'method' => 'SitesManager.updateSite',
            'idSite' => $idSite,
            'siteName' => $siteName,
            'urls' => $url,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Get an array with the ids of all the sites in the Analytics Service.
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     *
     * @return array all the ids of all the sites
     */
    public function getAllSitesId(): array
    {
        $params = [
            'method' => 'SitesManager.getAllSitesId',
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * Get the id of the site with this url in the Analytics Service.
     *
     * @param string $siteUrl the site url of the website
     *
     * @return array all the ids of all the sites
     */
    public function getSitesIdFromUrl(string $siteUrl): array
    {
        $params = [
            'method' => 'SitesManager.getSitesIdFromSiteUrl',
            'token_auth' => $this->tokenAuth,
            'url' => $siteUrl,
        ];

        return $this->apiCall($params);
    }

    /**
     * Get all Urls associated with the Analytics Service website ID.
     *
     * @param string $idSite the Analytics Service website ID
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     *
     * @return array all the urls
     */
    public function getSiteUrlsFromId(string $idSite): array
    {
        $params = [
            'method' => 'SitesManager.getSiteUrlsFromId',
            'idSite' => $idSite,
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * Change archive status in the Analytics Service.
     *
     * @param string $idSites the Analytics Service website ID
     * @param int $status the new status
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     *
     * @see \App\Enums\WebsiteStatus
     */
    public function changeArchiveStatus(string $idSites, int $status): void
    {
        if (WebsiteStatus::ARCHIVED !== $status && WebsiteStatus::ACTIVE !== $status) {
            throw new CommandErrorException('Invalid parameter for archiving: must be ' . WebsiteStatus::ACTIVE . ' or ' . WebsiteStatus::ARCHIVED . '. Received: ' . $status . '.');
        }

        $params = [
            'method' => 'DisableTracking.changeDisableState',
            'idSites' => $idSites,
            'token_auth' => $this->tokenAuth,
            'disable' => self::ARCHIVE_STATUS_MAPPINGS[$status],
        ];

        $this->apiCall($params);
    }

    /**
     * Get Javascript code snippet for a specified site
     * registered in the Analytics Service.
     *
     * @param string $idSite the Analytics Service website ID
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     *
     * @return string the site tracking code
     */
    public function getJavascriptSnippet(string $idSite): string
    {
        $params = [
            'method' => 'SitesManager.getJavascriptTag',
            'idSite' => $idSite,
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params)['value'];
    }

    /**
     * Delete a given site in the Analytics Service.
     *
     * @param string $idSite the Analytics Service website ID
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     */
    public function deleteSite(string $idSite): void
    {
        $params = [
            'method' => 'SitesManager.deleteSite',
            'idSite' => $idSite,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Register a new user in the Analytics Service.
     *
     * @param string $userLogin the Analytics Service user ID
     * @param string $password the Analytics Service user password
     * @param string $email the Analytics Service user email
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     */
    public function registerUser(string $userLogin, string $password, string $email): void
    {
        $params = [
            'method' => 'UsersManager.addUser',
            'userLogin' => $userLogin,
            'password' => $password,
            'email' => $email,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Get a specified user in the Analytics Service.
     *
     * @param string $email the Analytics Service user email
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return array the Analytics Service user
     */
    public function getUserByEmail(string $email): array
    {
        $params = [
            'method' => 'UsersManager.getUserByEmail',
            'userEmail' => $email,
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * @param string $userLogin the Analytics Service user ID
     * @param string $hashedPassword the MD5 hashed Analytics Service user password
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return string the Analytics authentication token
     */
    public function getUserAuthToken(string $userLogin, string $hashedPassword): string
    {
        $params = [
            'method' => 'UsersManager.getTokenAuth',
            'userLogin' => $userLogin,
            'md5Password' => $hashedPassword,
        ];

        return $this->apiCall($params)['value'];
    }

    /**
     * Delete a specified user in the Analytics Service.
     *
     * @param string $userLogin the Analytics Service user ID
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     */
    public function deleteUser(string $userLogin): void
    {
        $params = [
            'method' => 'UsersManager.deleteUser',
            'userLogin' => $userLogin,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Update the email address of a specified user in the Analytics Service.
     *
     * @param string $userLogin the Analytics Service user ID
     * @param string $updatedEmail the updated email address
     * @param string $passwordConfirmation the user password needed to confirm the email change
     * @param string $tokenAuth the Analytics authentication token
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     */
    public function updateUserEmail(string $userLogin, string $updatedEmail, string $passwordConfirmation, string $tokenAuth): void
    {
        $params = [
            'method' => 'UsersManager.updateUser',
            'userLogin' => $userLogin,
            'email' => $updatedEmail,
            'passwordConfirmation' => $passwordConfirmation,
            'token_auth' => $tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Get an array with the login strings of all the users in the Analytics Service.
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     *
     * @return array all the login strings of all the users
     */
    public function getUsersLogin(): array
    {
        $params = [
            'method' => 'UsersManager.getUsersLogin',
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * Login and redirect a specified user in the Analytics Service.
     *
     * @param string $userLogin the Analytics Service user ID
     * @param string $hashedPassword the MD5 hashed Analytics Service user password
     * @param string|null $idSite the Analytics Service website ID to redirect to
     *
     * @return RedirectResponse the Analytics service dashboard
     */
    public function loginAndRedirectUser(string $userLogin, string $hashedPassword, string $idSite = null): RedirectResponse
    {
        return redirect($this->servicePublicUrl . '/index.php?module=Login&action=logme&login=' . $userLogin . '&password=' . $hashedPassword . '&idSite=' . $idSite);
    }

    /**
     * Set permissions for a specified user and specified websites
     * in the Analytics Service.
     *
     * @param string $userLogin the Analytics Service user ID
     * @param int $access the Analytics Service access level
     * @param string $idSites the Analytics Service website ID
     *
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     * @throws CommandErrorException if command is unsuccessful
     *
     * @see \App\Enums\WebsiteAccessType
     */
    public function setWebsiteAccess(string $userLogin, int $access, string $idSites): void
    {
        $params = [
            'method' => 'UsersManager.setUserAccess',
            'userLogin' => $userLogin,
            'access' => self::ACCESS_LEVELS_MAPPINGS[$access],
            'idSites' => $idSites,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * @param string $idSite the Analytics Service website ID
     * @param int $minutes the minutes period
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return int the live number of website visits
     */
    public function getLiveVisits(string $idSite, int $minutes): int
    {
        $params = [
            'method' => 'Live.getCounters',
            'idSite' => $idSite,
            'lastMinutes' => $minutes,
            'token_auth' => $this->tokenAuth,
        ];
        $response = $this->apiCall($params);
        if (!empty($response)) {
            return $response[0]['visits'];
        }

        return 0;
    }

    /**
     * Get total number of visits for a specified site
     * registered in the Analytics Service.
     *
     * @param string $idSite the Analytics Service website ID
     * @param string $from the date range
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return int the total reported website visits
     */
    public function getSiteTotalVisitsFrom(string $idSite, string $from): int
    {
        $params = [
            'method' => 'VisitsSummary.get',
            'idSite' => $idSite,
            'period' => 'range',
            'date' => $from . ',' . now()->format('Y-m-d'),
            'token_auth' => $this->tokenAuth,
        ];
        $response = $this->apiCall($params);

        return $response['nb_visits'] ?? 0;
    }

    /**
     * Get the number of visits for a specified site
     * registered last month in the Analytics Service.
     *
     * @param string $idSite the Analytics Service website ID
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return int the reported website visits from last month
     */
    public function getSiteLastMonthVisits(string $idSite): int
    {
        $params = [
            'method' => 'VisitsSummary.get',
            'idSite' => $idSite,
            'period' => 'month',
            'date' => 'yesterday',
            'token_auth' => $this->tokenAuth,
        ];
        $response = $this->apiCall($params);

        return $response['nb_visits'] ?? 0;
    }

    /**
     * Get the daily number of visits for the last requested days.
     *
     * @param string $idSite the Analytics Service website ID
     * @param int $days the requested number of days
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return array the list of days with the number of visits
     */
    public function getSiteLastDaysVisits(string $idSite, int $days): array
    {
        $params = [
            'method' => 'VisitsSummary.getVisits',
            'idSite' => $idSite,
            'period' => 'day',
            'date' => 'last' . $days,
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * Get the stats from a specific url.
     *
     * @param string $idSite the Analytics Service website ID
     * @param int $days the requested number of days
     * @param string $url the url of the site
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return array the list of days with the number of visits
     */
    public function getStatsPageUrl(string $idSite, int $days, string $url): array
    {
        $params = [
            'method' => 'Actions.getPageUrl',
            'idSite' => $idSite,
            'period' => 'day',
            'date' => 'last' . $days,
            'pageUrl' => $url,
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * Get all defined segments.
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return array the list of days with the number of visits
     */
    public function getAllSegments(): array
    {
        $params = [
            'method' => 'SegmentEditor.getAll',
            'token_auth' => $this->tokenAuth,
            'filter_limit' => -1,
        ];

        return $this->apiCall($params);
    }

    /**
     * Add segment for a specific definition.
     *
     * @param string $idSite the Analytics Service website ID
     * @param string $segment the definition of the segment
     * @param string $name the name of the segment
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     */
    public function addSegment(string $idSite, string $segment, string $name): void
    {
        $params = [
            'method' => 'SegmentEditor.add',
            'idSite' => $idSite,
            'name' => $name,
            'definition' => $segment,
            'enabledAllUsers' => 0,
            'autoArchive' => 1,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Delete a specified segment.
     *
     * @param string $idSegment the segment to be deleted
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     */
    public function deleteSegment(string $idSegment): void
    {
        $params = [
            'method' => 'SegmentEditor.delete',
            'idSegment' => $idSegment,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Get the country report for a specific segment in a specified month.
     *
     * @param string $idSite the Analytics Service website ID
     * @param string $date a date in the month
     * @param string $segment the segment of the report
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return array the report with the countries detected for the specific segment
     */
    public function getCountriesInSegmentInMonth(string $idSite, string $date, string $segment): array
    {
        $params = [
            'method' => 'UserCountry.getCountry',
            'idSite' => $idSite,
            'period' => 'month',
            'date' => $date,
            'segment' => $segment,
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params);
    }

    /**
     * Register a new analytics service report.
     *
     * @param string $name the report name
     * @param array $idSites the Analytics Service websites IDs
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return int the Analytics Service report ID
     */
    public function registerRollUp(string $name, array $idSites): int
    {
        $params = [
            'sourceIdSites' => $idSites,
            'method' => 'RollUpReporting.addRollUp',
            'name' => $name,
            'timezone' => 'Europe/Rome',
            'currency' => 'EUR',
            'token_auth' => $this->tokenAuth,
        ];

        return $this->apiCall($params)['value'];
    }

    /**
     * Get all widgets metadata.
     *
     * @param string $idSite the Analytics Service websites ID
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     *
     * @return array all widgets
     */
    public function getWidgetMetadata($idSite): ?array
    {
        $params = [
            'method' => 'API.getWidgetMetadata',
            'filter_limit' => '-1',
            'deep' => '1',
            'idSite' => $idSite,
        ];

        return $this->apiCall($params);
    }

    /**
     * Update an existing analytics service report.
     * NOTE: the given Analytics Service websites IDs list will replace
     *       the current one.
     *
     * @param string $idRollUp the Analytics Service report ID
     * @param array $idSites the Analytics Service websites IDs
     *
     * @throws CommandErrorException if command is unsuccessful
     * @throws AnalyticsServiceException if unable to connect the Analytics Service
     */
    public function updateRollUp(string $idRollUp, array $idSites): void
    {
        $params = [
            'sourceIdSites' => $idSites,
            'method' => 'RollUpReporting.updateRollUp',
            'idSite' => $idRollUp,
            'token_auth' => $this->tokenAuth,
        ];

        $this->apiCall($params);
    }

    /**
     * Make an API call to Analytics Service.
     *
     * @param array $params the request parameter
     *
     * @throws AnalyticsServiceException if unable to contact the Analytics Service
     * @throws CommandErrorException if command finishes with error status
     *
     * @return array the JSON response
     */
    protected function apiCall(array $params): array
    {
        try {
            $client = new APIClient(['base_uri' => $this->serviceBaseUri]);
            $res = $client->request('GET', 'index.php', [
                'query' => array_merge($params, [
                    'module' => 'API',
                    'format' => 'JSON',
                ]),
                'verify' => $this->SSLVerify,
            ]);
        } catch (GuzzleException $exception) {
            throw new AnalyticsServiceException($exception->getMessage());
        }

        $response = json_decode($res->getBody(), true);

        if (!empty($response['result']) && 'error' === $response['result']) {
            throw new CommandErrorException($response['message']);
        }

        return $response;
    }
}
