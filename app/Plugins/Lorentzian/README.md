## Lorentzian Classification (Laravel PHP)

Port of the Python `advanced_ta` Lorentzian Classification package.

### Install

Add path repo in your Laravel app composer.json and require this package, or place under `packages/` and load via path repository.

### Usage

```php
use AdvancedTa\LorentzianClassification\Facades\LorentzianClassification;

$data = [
    'open' => $openArray,
    'high' => $highArray,
    'low'  => $lowArray,
    'close'=> $closeArray,
];

$clf = LorentzianClassification::make($data);
$result = $clf->data();
```


