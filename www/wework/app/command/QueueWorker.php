<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Queue;

class QueueWorker extends Command
{
    protected function configure()
    {
        $this->setName('queue:work')
            ->addOption('queue', null, Option::VALUE_OPTIONAL, 'The queue to work on', 'WechatMediaDownload')
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Number of seconds to sleep when no job is available', 3)
            ->setDescription('Start processing jobs on the queue as a daemon');
    }

    protected function execute(Input $input, Output $output)
    {
        $queue = $input->getOption('queue');
        $sleep = $input->getOption('sleep');

        while (true) {
            // 手动处理队列任务
            $job = Queue::pop($queue);

            if ($job) {
                $job->fire();
            } else {
                sleep($sleep);
            }
        }
    }
}