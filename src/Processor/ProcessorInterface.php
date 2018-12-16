<?php
namespace Bee\Log\Processor;

interface ProcessorInterface
{
    /**
     * @param array $records
     * @return array The processed records
     */
    public function __invoke(array $records);
}
