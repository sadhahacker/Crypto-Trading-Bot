<?php

namespace App\Console\Commands;

use App\Http\Controllers\Trading\IndicatorController;
use App\Models\BotConfiguration;
use App\Plugins\LorentzianClassification\ScriptsRunner;
use Illuminate\Console\Command;

class RunLorentzian extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:lorentzian';

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
        $symbol = BotConfiguration::getValue('DEFAULT_SYMBOL');
        $interval = BotConfiguration::getValue('DEFAULT_INTERVAL');
        $limit = (int) BotConfiguration::getValue('DEFAULT_LIMIT');

        (new ScriptsRunner())->run($symbol, $interval, $limit);

        $this->info('Python script finished.');
    }
}
