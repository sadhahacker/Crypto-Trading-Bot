<?php

namespace App\Http\Controllers\Strategies;

use Stochastix\Domain\Common\Enum\DirectionEnum;
use Stochastix\Domain\Common\Enum\TALibFunctionEnum;
use Stochastix\Domain\Common\Model\MultiTimeframeOhlcvSeries;
use Stochastix\Domain\Indicator\Model\TALibIndicator;
use Stochastix\Domain\Order\Enum\OrderTypeEnum;
use Stochastix\Domain\Strategy\AbstractStrategy;

class EmaRsiVolumeStrategy extends AbstractStrategy
{
    private int $emaFastPeriod = 9;
    private int $emaSlowPeriod = 21;

    protected function defineIndicators(): void
    {
        $this->addIndicator(
            'ema_fast',
            new TALibIndicator(
                TALibFunctionEnum::Ema,
                ['timePeriod' => $this->emaFastPeriod]
            )
        );

        $this->addIndicator(
            'ema_slow',
            new TALibIndicator(
                TALibFunctionEnum::Ema,
                ['timePeriod' => $this->emaSlowPeriod]
            )
        );
    }

    public function onBar(MultiTimeframeOhlcvSeries $bars): void
    {
        $fastEma = $this->getIndicatorSeries('ema_fast');
        $slowEma = $this->getIndicatorSeries('ema_slow');

        $limitPrice = '100.0';

        // Check for a long entry signal
        if ($fastEma->crossesOver($slowEma)) {
            $this->entry(
                direction: DirectionEnum::Long,
                orderType: OrderTypeEnum::Limit,
                quantity: '0.5',
                price: $limitPrice,
                clientOrderId: 'ema-long-' . time()
            );
        }

        // Check for a short entry signal
        if ($fastEma->crossesUnder($slowEma)) {
            $this->entry(
                direction: DirectionEnum::Short,
                orderType: OrderTypeEnum::Limit,
                quantity: '0.5',
                price: $limitPrice,
                clientOrderId: 'ema-short-' . time()
            );
        }
    }
}
