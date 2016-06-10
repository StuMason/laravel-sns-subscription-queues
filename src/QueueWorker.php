<?php 

namespace Kirschbaum\LaravelSnsSubscriptionQueues;

use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Worker;
use Throwable;

class QueueWorker extends Worker
{

    protected $job;
    protected $sqs_job;
    protected $class_name;
    protected $queue_message;
    protected $custom_handler;

    /**
     * Process a given job from the queue.
     *
     * @param  string  $connection
     * @param  \Illuminate\Contracts\Queue\Job  $job
     * @param  int  $maxTries
     * @param  int  $delay
     * @return array|null
     *
     * @throws \Throwable
     */
    public function process($connection, Job $job, $maxTries = 0, $delay = 0)
    {
        // @TODO Tests using File::put('/test', serialize());
        if ($maxTries > 0 && $job->attempts() > $maxTries) {
            return $this->logFailedJob($connection, $job);
        }

        try {
            // First we will fire off the job. Once it is done we will see if it will
            // be auto-deleted after processing and if so we will go ahead and run
            // the delete method on the job. Otherwise we will just keep moving.

            $this->setJob($job);

            if($this->isAnSqsJob())
                $job = $this->processSqsJob($job);
            
            $job->fire();

            $this->raiseAfterJobEvent($connection, $job);

            return ['job' => $job, 'failed' => false];
        } catch (Exception $e) {
            // If we catch an exception, we will attempt to release the job back onto
            // the queue so it is not lost. This will let is be retried at a later
            // time by another listener (or the same one). We will do that here.
            if (! $job->isDeleted()) {
                $job->release($delay);
            }

            throw $e;
        } catch (Throwable $e) {
            if (! $job->isDeleted()) {
                $job->release($delay);
            }

            throw $e;
        }
    }

    private function isAnSqsJob()
    {
        return $this->getClassName() == 'Illuminate\Queue\Jobs\SqsJob';
    }

    private function processSqsJob($job)
    {
        $this->setSqsJob($job);
        $this->setQueueMessage();

        if(!$this->jobPropertyExistInTheQueueMessage())
            $job = $this->createNewJobWithLaravelFormatting($job);

        return $job;
    }

    public function setSqsJob(Job $job)
    {
        $this->sqs_job = $job->getSqsJob();
    }

    public function setQueueMessage()
    {
        $this->queue_message = json_decode($this->sqs_job['Body'], true);
    }

    private function jobPropertyExistInTheQueueMessage()
    {
        return isset($this->queue_message['job']);
    }

    private function createNewJobWithLaravelFormatting($job)
    {
        if(!$this->customHandlerExists())
            throw new Exception("QueueWorker: This sqsJob is missing the typical Laravel job parameters. To use non-standard queue payloads you will need to create and configure a custom handler.");

        $this->addHandlerClassAndSetData();
        $this->setUpdatedSqsJobBodyProperty();

        $class = $this->getClassName();
        $job = new $class(
            $job->getContainer(),
            $job->getSqs(),
            $job->getQueue(),
            $this->sqs_job
        );

        return $job;
    }

    private function addHandlerClassAndSetData()
    {
        $this->queue_message['job'] = $this->getCustomHandler();
        $this->queue_message['data'] = $this->queue_message['Message'];
        unset($this->queue_message['Message']);
    }

    private function setUpdatedSqsJobBodyProperty()
    {
        $this->sqs_job['Body'] = json_encode($this->queue_message);
    }

    private function customHandlerExists()
    {
        return !empty($this->getCustomHandler());
    }

    public function setCustomHandler($handler = null)
    {
        if(null === $handler)
            $handler = config("queue-custom-handlers.{$this->queue_message['TopicArn']}");

        $this->custom_handler =  $handler;
    }

    public function getCustomHandler()
    {
        if(null === $this->custom_handler)
            $this->setCustomHandler();

        return $this->custom_handler;
    }

    public function setJob($job)
    {
        $this->job = $job;
    }

    public function getClassName()
    {
        if(!$this->class_name)
            $this->setClassName();

        return $this->class_name;
    }

    public function setClassName($class_name = null)
    {
        if(null == $class_name)
            $class_name = get_class($this->job);

        $this->class_name = $class_name;
    }

}
