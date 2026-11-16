<?php

namespace Glubschi\Seat\MarketOrders\Jobs;

use Seat\Eveapi\Jobs\AbstractAuthCharacterJob;
use Seat\Eveapi\Models\RefreshToken;

/**
 * Opens the Market Details window of an item in the EVE client of one character.
 *
 * ESI: POST /ui/openwindow/marketdetails/?type_id=…, scope esi-ui.open_window.v1. CCP's ESI documentation
 * ("Opening links in the client") says the character must be logged in. ESI answers 204 without a body.
 *
 * Built as a SeAT authenticated character job so SeAT's ESI client, token refresh and error mapping are used.
 * Unlike SeAT's own jobs it is not queued: the controller calls handle() directly, because a click must
 * open the window at once, and SeAT's queue middleware (WithoutOverlapping per character) would hold the
 * call back while a regular update job of the same character is running. The controller therefore checks
 * the scope itself, which SeAT's CheckTokenScope middleware would do for a queued job.
 */
class OpenMarketDetails extends AbstractAuthCharacterJob
{
    public const SCOPE = 'esi-ui.open_window.v1';

    protected $method = 'post';

    protected $endpoint = '/ui/openwindow/marketdetails/';

    protected string $compatibility_date = '2025-07-20';

    protected $scope = self::SCOPE;

    protected $tags = ['character', 'ui', 'marketorders'];

    public function __construct(RefreshToken $token, protected int $typeId)
    {
        parent::__construct($token);
        $this->query_string = ['type_id' => $typeId];
    }

    /**
     * Sends the request. Does not call parent::handle(): that only logs the queue job ID, which does not exist
     * when the job is run directly.
     *
     * @throws \Throwable  SeAT's ESI exceptions; the controller turns them into a message for the page
     */
    public function handle(): void
    {
        $this->retrieve();
    }

    public function typeId(): int
    {
        return $this->typeId;
    }
}
