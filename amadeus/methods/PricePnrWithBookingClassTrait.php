<?php

namespace Amadeus\Methods;

use Amadeus\models\BagAllowance;
use Amadeus\models\OrderFlow;
use Amadeus\models\SegmentDetails;
use common\models\Price;
use SebastianBergmann\Money\Currency;
use SebastianBergmann\Money\Money;

trait PricePnrWithBookingClassTrait
{

    use BasicMethodsTrait;

    /**
     * Show details on price & baggage
     *
     * @param OrderFlow $orderFlow
     * @return OrderFlow
     */
    public function pricePnrWithBookingClass($orderFlow)
    {
        $data = $this->getClient()->farePricePNRWithBookingClass($orderFlow->getSearchRequest()->getCurrency());

        $fareList = [];
        foreach ($this->iterateStd($data->fareList->fareDataInformation->fareDataSupInformation) as $f) {
            if (isset($f->fareAmount))
                $fareList[(string)$f->fareDataQualifier] = Money::fromString(
                    (string)$f->fareAmount,
                    new Currency((string)$f->fareCurrency)
                );
        }

        $taxesList = [];
        foreach ($this->iterateStd($data->fareList->taxInformation) as $t) {
            $taxesList[] = Money::fromString(
                (string)$t->amountDetails->fareDataMainInformation->fareAmount,
                new Currency((string)$t->amountDetails->fareDataMainInformation->fareCurrency)
            );
        }

        $lastTktDate = \DateTime::createFromFormat('d-m-Y',
            join('-', [
                $data->fareList->lastTktDate->dateTime->day,
                $data->fareList->lastTktDate->dateTime->month,
                $data->fareList->lastTktDate->dateTime->year
            ])
        );

        $segments = [];
        foreach ($this->iterateStd($data->fareList->segmentInformation) as $s) {
            $classOfService = $s->segDetails->segmentDetail->classOfService;
            $bagAllowanceInformation = $s->bagAllowanceInformation->bagAllowanceDetails;
            $bagAllowance = new BagAllowance(
                isset($bagAllowanceInformation->baggageWeight) ? (string)$bagAllowanceInformation->baggageWeight : null,
                (string)$bagAllowanceInformation->baggageType,
                isset($bagAllowanceInformation->measureUnit) ? (string)$bagAllowanceInformation->measureUnit : null,
                isset($bagAllowanceInformation->baggageQuantity) ? (string)$bagAllowanceInformation->baggageQuantity : null
            );
            $segments[] = new SegmentDetails($classOfService, $bagAllowance);
        }

        /** @var Money $totalFare */
        $totalFare = $fareList[712];

        /** @var Money $baseFare */
        $baseFare = $fareList['B'];

        /** @var Money $tax */
        $tax = $totalFare->subtract($baseFare);

        $price = new Price($baseFare, $tax);

        //Set commission
        $this->getCommissions($orderFlow->getSegments(), $orderFlow->getValidatingCarrier(), $orderFlow->getSearchRequest(), $price);

        $orderFlow->setPrice($price);

        $orderFlow->setLastTktDate($lastTktDate);

        return $orderFlow;
    }

}