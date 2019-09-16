<?php

namespace Firesphere\GoogleAPI\Jobs;

use Firesphere\GoogleAPI\Services\GoogleAnalyticsReportService;
use Firesphere\GoogleAPI\Services\GoogleClientService;
use Firesphere\GoogleAPI\Services\PageUpdateService;
use Google_Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Injector\Injector;
use stdClass;
use SilverStripe\ORM\ValidationException;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

if (!class_exists('Symbiote\\QueuedJobs\\Services\\AbstractQueuedJob')) {
    return;
}

/**
 * Class AnalyticsUpdateJob
 *
 * Job to run in the QueuedJobs
 */
class AnalyticsUpdateJob extends AbstractQueuedJob
{
    /**
     * @var GoogleAnalyticsReportService
     */
    protected $service;

    /**
     * @var PageUpdateService
     */
    protected $updateService;

    /**
     * AnalyticsUpdateJob constructor.
     * @param array $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Update Google Analytics information for the configured pages';
    }

    /**
     * Boot up the process
     * @throws Google_Exception
     * @throws \LogicException
     * @throws ValidationException
     */
    public function process()
    {
        $clientService = new GoogleClientService();

        $this->getReport($clientService);

        $this->isComplete = true;
    }

    /**
     * Execute the whole part
     *
     * @param GoogleClientService $client
     * @throws ValidationException
     */
    protected function getReport($client)
    {
        $this->service = new GoogleAnalyticsReportService($client);

        /** @var array $reports */
        $reports = $this->service->getReport();
        $count = 0;

        $this->updateService = new PageUpdateService();
        foreach ($reports as $report) {
            /** @var array $rows */
            $rows = $report->getData()->getRows();
            $count += $this->updateService->updateVisits($rows);
        }
        $this->addMessage("$count Pages updated with Google Analytics visit count");
    }

    /**
     * If needed, queue itself with
     * @throws NotFoundExceptionInterface
     */
    public function afterComplete()
    {
        if ($this->service->batched && $this->updateService->batched) {
            /** @var AnalyticsUpdateJob $nextJob */
            $nextJob = Injector::inst()->get(self::class);
            $nextJob->setJobData(1, 0, false, new stdClass(), ['Batched data from Google']);
            /** @var QueuedJobService $jobService */
            $jobService = Injector::inst()->get(QueuedJobService::class);
            /* Queue immediately after this job */
            $jobService->queueJob($nextJob, date('Y-m-d H:i:s'));
        }
        parent::afterComplete();
    }
}
