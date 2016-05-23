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
