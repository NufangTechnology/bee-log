<?php
namespace Bee\Logger\Processor;

/**
 * An optional interface to allow labelling Monolog processors.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ProcessorInterface
{
    /**
     * @param array $records
     *
     * @return array The processed records
     */
    public function __invoke(array $records);
}
