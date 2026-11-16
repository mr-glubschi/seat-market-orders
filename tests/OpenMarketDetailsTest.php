<?php

namespace Glubschi\Seat\MarketOrders\Tests;

use Glubschi\Seat\MarketOrders\Jobs\OpenMarketDetails;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Services\Contracts\EsiClient;

/** The job must call ESI's open window route exactly as CCP documents it. */
class OpenMarketDetailsTest extends TestCase
{
    private function job(int $typeId): OpenMarketDetails
    {
        // SeAT's ESI client is bound by SeAT's own providers, which these tests do not boot.
        $this->app->instance(EsiClient::class, \Mockery::mock(EsiClient::class));

        $token = new RefreshToken();
        $token->character_id = 314354929;

        return new OpenMarketDetails($token, $typeId);
    }

    public function test_posts_to_the_market_details_route_with_the_ui_scope(): void
    {
        $job = $this->job(34);

        $this->assertSame('/ui/openwindow/marketdetails/', $job->getEndpoint());
        $this->assertSame('esi-ui.open_window.v1', $job->getScope());
        $this->assertSame(34, $job->typeId());

        $method = (new \ReflectionProperty($job, 'method'))->getValue($job);
        $query = (new \ReflectionProperty($job, 'query_string'))->getValue($job);
        $this->assertSame('post', $method);
        $this->assertSame(['type_id' => 34], $query);
    }
}
