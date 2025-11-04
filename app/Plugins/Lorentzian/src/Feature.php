<?php

namespace App\Plugins\Lorentzian\src;

class Feature
{
    public string $type;
    public int $param1;
    public int $param2;

    public function __construct(string $type, int $param1, int $param2)
    {
        $this->type = $type;
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}


