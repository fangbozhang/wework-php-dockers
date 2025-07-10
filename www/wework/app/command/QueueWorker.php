<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;
use think\facade\Queue;

class QueueWorker extends Command
{
    protected function configure()
    {
        $this->setName('queue:work')
            ->addOption('queue', null, Option::VALUE_OPTIONAL, 'The queue to work on', 'WechatMediaDownload')
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3)
            ->addOption('timeout', null, Option::VALUE_OPTIONAL, 'Timeout in seconds for waiting for a job', 5)
            ->addOption('max-jobs', null, Option::VALUE_OPTIONAL, 'Maximum number of jobs to process before restarting', 1000)
            ->setDescription('Start processing jobs on the queue as a daemon');
    }

    protected function execute(Input $input, Output $output)
    {
        $queue = $input->getOption('queue');
        $sleep = $input->getOption('sleep');
        $timeout = $input->getOption('timeout');
        $maxJobs = $input->getOption('max-jobs');
        $jobCount = 0;

        while ($jobCount < $maxJobs) {
            try {
                // 手动处理队列任务，设置超时时间
                $job = Queue::pop($queue, $timeout);

                if ($job) {
                    $job->fire();
                    $jobCount++;
                } else {
                    sleep($sleep);
                }
            } catch (\Exception $e) {
                Log::error("Queue worker error: " . $e->getMessage());
                sleep($sleep);
            }
        }
    }
}