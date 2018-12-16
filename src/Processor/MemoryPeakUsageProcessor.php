<?php
namespace Bee\Log\Processor;

class MemoryPeakUsageProcessor extends MemoryProcessor
{
    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $bytes = memory_get_peak_usage($this->realUsage);
        $formatted = $this->formatBytes($bytes);

        $record['extra']['memory_peak_usage'] = $formatted;

        return $record;
    }
}
