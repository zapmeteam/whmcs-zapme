# ZapMe, Api SDK

**This repository offers the ZapMeApi SDK through composer for developers or interested parties.** We recommend that you read the content below to learn how to use it.

### Requirements
To use the SDK you will need to meet the required requirements:

- PHP 7.3 or above
- PHP extension cURL
- PHP extension jSON

### Installing

``` bash
composer require zapmeteam/api
```

### Using

Once installed composer will make SDK ready for use. All you have to do is instantiate the class, configure it and fire it! See an example below:

```php

require __DIR__ . '/vendor/autoload.php';

use ZapMeTeam\Api\ZapMeApi;

$zapme = (new ZapMeApi)
    ->setApi('API_HERE')
    ->setSecret('SECRET_HERE');

// consuming sendMessage method
$zapme
    ->sendMessage('PHONE_HERE', 'This is a simple test!')
    ->getResult();
```

You can learn about all API methods through the official documentation:
https://docs.zapme.com.br

### Testing
For testing purposes you should clone the repository, **prepare the `.env` file as explained in the `.env.example`** and then run the composer tests:

``` bash
composer test
```

Notes: 

1. **The `.env` is only used for testing purposes** and without it being properly configured the tests will not run successfully.
2. The `.lando.yml` file it's a file for developers using Lando.dev for docker environments. If you want to use it, all you have to do is clone the repository **(preferably with a fork)** and run:
`land start` then you can run the tests like: `lando composer test`

### Contributing

You can contribute to the SDK by submitting a PR with some tweak, improvement, increment or fix. **Is necessary for the contribution to be successful in PHPUnit tests.**