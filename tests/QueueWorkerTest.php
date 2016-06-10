<?php

use Mockery as m;

class QueueWorkerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->key = 'AMAZONSQSKEY';
        $this->secret = 'AmAz0n+SqSsEcReT+aLpHaNuM3R1CsTr1nG';
        $this->service = 'sqs';
        $this->region = 'someregion';
        $this->account = '1234567891011';
        $this->queueName = 'emails';
        $this->baseUrl = 'https://sqs.someregion.amazonaws.com';
        $this->releaseDelay = 0;
        // This is how the modified getQueue builds the queueUrl
        $this->queueUrl = $this->baseUrl.'/'.$this->account.'/'.$this->queueName;
        // Get a mock of the SqsClient
        $this->mockedSqsClient = $this->getMockBuilder('Aws\Sqs\SqsClient')
            ->setMethods(['deleteMessage'])
            ->disableOriginalConstructor()
            ->getMock();
        // Use Mockery to mock the IoC Container
        $this->mockedContainer = m::mock('Illuminate\Container\Container');
        $mockHandlerClass = m::mock('StdClass');
        $mockHandlerClass->shouldReceive('customHandleMethod');
        $this->mockedContainer->shouldReceive('make')->andReturn($mockHandlerClass);
        $this->mockedJob = 'foo';
        $this->mockedData = ['data'];
        $this->mockedPayload = json_encode(['TopicArn' => 'mock-arn-id', 'Message' => $this->mockedData, 'attempts' => 1]);
        $this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
        $this->mockedReceiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ1Y+PxCh7R930NRB8ylSacEmoSnW18bgd4nK\/O6ctE+VFVul4eD23mA07vVoSnPI4F\/voI1eNCp6Iax0ktGmhlNVzBwaZHEr91BRtqTRM3QKd2ASF8u+IQaSwyl\/DGK+P1+dqUOodvOVtExJwdyDLy1glZVgm85Yw9Jf5yZEEErqRwzYz\/qSigdvW4sm2l7e4phRol\/+IjMtovOyH\/ukueYdlVbQ4OshQLENhUKe7RNN5i6bE\/e5x9bnPhfj2gbM';
        $this->mockedJobData = ['Body' => $this->mockedPayload,
            'MD5OfBody' => md5($this->mockedPayload),
            'ReceiptHandle' => $this->mockedReceiptHandle,
            'MessageId' => $this->mockedMessageId,
            'Attributes' => ['ApproximateReceiveCount' => 1], ];
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @expectedException        Exception
     * @expectedExceptionMessage QueueWorker: This sqsJob is missing the typical Laravel job parameters. To use non-standard queue payloads you will need to create and configure a custom handler.
     */
    public function testSqsJobWithArnPayloadShouldTakeExceptionIfArnMappingNotDefined()
    {
        $this->mockedSqsClient = $this->getMockBuilder('Aws\Sqs\SqsClient')
            ->setMethods(['changeMessageVisibility'])
            ->disableOriginalConstructor()
            ->getMock();

        $worker = new Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker(m::mock('Illuminate\Queue\QueueManager'));
        $worker->setCustomHandler(false);
        $job = $this->getJob();
        $worker->process('connection', $job, 0, 0);
    }


    public function testSqsJobWithArnPayloadShouldAddJobKeyAndCallHandlerBasedOnMapping()
    {
        $this->mockedSqsClient = $this->getMockBuilder('Aws\Sqs\SqsClient')
            ->setMethods(['changeMessageVisibility'])
            ->disableOriginalConstructor()
            ->getMock();

        $worker = new Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker(m::mock('Illuminate\Queue\QueueManager'));
        $worker->setCustomHandler('MyMock\Handler@customHandleMethod');
        $job = $this->getJob();
        $result =  $worker->process('connection', $job, 0, 0);

        // Assert job key was added with correct class and handler.
        $rawBody = json_decode($result['job']->getRawBody(), true);
        $this->assertEquals('MyMock\Handler@customHandleMethod', $rawBody['job']);
    }

    protected function getJob()
    {
        return new Illuminate\Queue\Jobs\SqsJob(
            $this->mockedContainer,
            $this->mockedSqsClient,
            $this->queueUrl,
            $this->mockedJobData
        );
    }

    public function testJobIsPoppedOffQueueAndProcessed()
    {
        $worker = $this->getMock('Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker', ['process'], [$manager = m::mock('Illuminate\Queue\QueueManager')]);
        $manager->shouldReceive('connection')->once()->with('connection')->andReturn($connection = m::mock('StdClass'));
        $manager->shouldReceive('getName')->andReturn('connection');
        $job = m::mock('Illuminate\Contracts\Queue\Job');
        $connection->shouldReceive('pop')->once()->with('queue')->andReturn($job);
        $worker->expects($this->once())->method('process')->with($this->equalTo('connection'), $this->equalTo($job), $this->equalTo(0), $this->equalTo(0));

        $worker->pop('connection', 'queue');
    }

    public function testJobIsPoppedOffFirstQueueInListAndProcessed()
    {
        $worker = $this->getMock('Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker', ['process'], [$manager = m::mock('Illuminate\Queue\QueueManager')]);
        $manager->shouldReceive('connection')->once()->with('connection')->andReturn($connection = m::mock('StdClass'));
        $manager->shouldReceive('getName')->andReturn('connection');
        $job = m::mock('Illuminate\Contracts\Queue\Job');
        $connection->shouldReceive('pop')->once()->with('queue1')->andReturn(null);
        $connection->shouldReceive('pop')->once()->with('queue2')->andReturn($job);
        $worker->expects($this->once())->method('process')->with($this->equalTo('connection'), $this->equalTo($job), $this->equalTo(0), $this->equalTo(0));

        $worker->pop('connection', 'queue1,queue2');
    }

    public function testWorkerSleepsIfNoJobIsPresentAndSleepIsEnabled()
    {
        $worker = $this->getMock('Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker', ['process', 'sleep'], [$manager = m::mock('Illuminate\Queue\QueueManager')]);
        $manager->shouldReceive('connection')->once()->with('connection')->andReturn($connection = m::mock('StdClass'));
        $connection->shouldReceive('pop')->once()->with('queue')->andReturn(null);
        $worker->expects($this->never())->method('process');
        $worker->expects($this->once())->method('sleep')->with($this->equalTo(3));

        $worker->pop('connection', 'queue', 0, 3);
    }

    public function testWorkerLogsJobToFailedQueueIfMaxTriesHasBeenExceeded()
    {
        $worker = new Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker(m::mock('Illuminate\Queue\QueueManager'), $failer = m::mock('Illuminate\Queue\Failed\FailedJobProviderInterface'));
        $job = m::mock('Illuminate\Contracts\Queue\Job');
        $job->shouldReceive('attempts')->once()->andReturn(10);
        $job->shouldReceive('getQueue')->once()->andReturn('queue');
        $job->shouldReceive('getRawBody')->once()->andReturn('body');
        $job->shouldReceive('delete')->once();
        $job->shouldReceive('failed')->once();
        $failer->shouldReceive('log')->once()->with('connection', 'queue', 'body');

        $worker->process('connection', $job, 3, 0);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testJobIsReleasedWhenExceptionIsThrown()
    {
        $worker = new Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker(m::mock('Illuminate\Queue\QueueManager'));
        $job = m::mock('Illuminate\Contracts\Queue\Job');
        $job->shouldReceive('fire')->once()->andReturnUsing(function () {
            throw new RuntimeException;
        });
        $job->shouldReceive('isDeleted')->once()->andReturn(false);
        $job->shouldReceive('release')->once()->with(5);

        $worker->process('connection', $job, 0, 5);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testJobIsNotReleasedWhenExceptionIsThrownButJobIsDeleted()
    {
        $worker = new Kirschbaum\LaravelSnsSubscriptionQueues\QueueWorker(m::mock('Illuminate\Queue\QueueManager'));
        $job = m::mock('Illuminate\Contracts\Queue\Job');
        $job->shouldReceive('fire')->once()->andReturnUsing(function () {
            throw new RuntimeException;
        });
        $job->shouldReceive('isDeleted')->once()->andReturn(true);
        $job->shouldReceive('release')->never();

        $worker->process('connection', $job, 0, 5);
    }
}