# Laravel SNS Subscription Queues

## Under Active Development

This package is actively being developed and should be considered experimental for the time being.

## Usage

This package extends the default `Illuminate\Queue\QueueServiceProvider` to handle queue payloads from SNS Topic subscriptions. It works by checking for a custom handler configuration in the event the Job doesn't meet the expected Laravel payload structure. If a custom queue handler has been configured this package then adds the necessary structure so that the queue system can process the Job.

## Installation

Add the package to your project:

```
composer require kirschbaum/laravel-sns-subscription-queues
```

Add the following service provider:

```
// config/app.php

'providers' => [
    ...
    Kirschbaum\LaravelSnsSubscriptionQueues\ServiceProvider::class,
    ...
];
```

Publish the config file using the Artisan command:

```
php artisan vendor:publish --provider="Kirschbaum\LaravelSnsSubscriptionQueues\ServiceProvider"
```

The configuration looks like this:

```
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Handlers for SNS Subscription Queues
    |--------------------------------------------------------------------------
    |
    | Here is where we map an ARN Topic to the Laravel Job handler that should
    | process the queue payload.
    |
    */

    // Examples
    'arn:aws:sns:us-east-1:012345667910:my-sns-topic-name' => 'App\\Jobs\\MyCustomHandler@handle',
    'arn:aws:sns:us-east-1:012345667910:my-other-sns-topic-name' => 'App\\Jobs\\AnotherCustomHandler@handle',
    
];
```

Create a corresponding Laravel Job class to handle the payload:

```
<?php namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class MyCustomHandler extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    
    public function handle($sqs_job, $payload)
    {

        $this->setJob($sqs_job);
        
        // Process the job here.
        
        $this->delete();

    }

}
```

## Contributors

[Nathan Kirschbaum](http://www.nathankirschbaum.com/)

[Alfred Nutile](https://alfrednutile.info/)
