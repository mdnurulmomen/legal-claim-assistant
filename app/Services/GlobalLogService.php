<?php

namespace App\Services;

use App\Models\GlobalLog;

class GlobalLogService
{
    /**
     * Save logs by updating an existing record or creating a new one.
     *
     * @param array $conditionObj
     * @param array $updateObj
     * @return void
     */

    public function saveLogs(array $conditionObj, array $updateObj): void
    {
        GlobalLog::updateOrCreate($conditionObj, $updateObj);
    }
}
