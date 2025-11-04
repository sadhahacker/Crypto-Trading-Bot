<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DataFrame extends Controller
{
    protected array $data;

    public function __construct(array $candles) {
        $keys = ['timestamp', 'open', 'high', 'low', 'close', 'volume'];

        $this->data = array_map(function($c) use ($keys) {
            if (isset($c['timestamp'])) return $c;

            if (count($c) === count($keys)) {
                return array_combine($keys, $c);
            }

            throw new \InvalidArgumentException("Candle format invalid: " . json_encode($c));
        }, $candles);
    }


    public function getColumn(string $key): array {
        return array_column($this->data, $key);
    }

    public function count(): int {
        return count($this->data);
    }

    public function getLastIndex(int $offset = 1): int {
        return $this->count() - $offset;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getKeysWithData()
    {
        return [
            'timestamp' => $this->getColumn('timestamp'),
            'open' => $this->getColumn('open'),
            'high' => $this->getColumn('high'),
            'low' => $this->getColumn('low'),
            'close' => $this->getColumn('close'),
            'volume' => $this->getColumn('volume'),
        ];
    }
}
