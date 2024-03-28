# php-array-utils
Mix of illuminate/Support/Arr and Kohana's array helpers including chaining.

## How to use

1) Installation

```sh

$ composer require santaklouse/php-array-utils:dev-main

```

2) Usage

```
use ArrayUtils\Arr;

Arr::chain([['id' => 1], ['id' => 2], ['id' => 3])
  ->except(1)
  ->pluck('id')
  ->map(fn ($n) => pow($n, 2))
  ->value();


// === [1, 9]
```
