<?php

namespace Glubschi\Seat\MarketOrders\Orders;

use Carbon\Carbon;

/** One open market order of one of the user's characters, as SeAT stored it. */
final class Order
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $characterId,
        public readonly int $typeId,
        public readonly string $typeName,
        public readonly int $locationId,
        public readonly string $locationName,
        public readonly bool $isStructure,
        public readonly int $regionId,
        public readonly bool $isBuy,
        public readonly float $price,
        public readonly int $volumeRemain,
        public readonly int $volumeTotal,
        public readonly Carbon $issued,
        public readonly int $durationDays,
    ) {
    }

    /** ESI: "An order expires at time issued + duration" (duration in days). */
    public function expires(): Carbon
    {
        return $this->issued->copy()->addDays($this->durationDays);
    }

    public function side(): string
    {
        return $this->isBuy ? 'buy' : 'sell';
    }
}
