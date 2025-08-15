<?php

namespace App\Console\Commands;

use App\Http\Controllers\Trading\IndicatorController;
use App\Plugins\LorentzianClassification\ScriptsRunner;
use Illuminate\Console\Command;

class RunTrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:trade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        (new IndicatorController((new ScriptsRunner())))->tradeStater();
    }
}
