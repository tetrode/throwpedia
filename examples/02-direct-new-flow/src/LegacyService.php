<?php

declare(strict_types=1);

namespace App;

use Exception;
use RuntimeException;
use Tetrode\Throwpedia\Attributes\ExceptionReason;

class LegacyService
{
    #[ExceptionReason(code: 'LEGACY_ERROR', technicalReason: 'Something failed in legacy code', businessReason: 'A system error occurred.')]
    public function doSomething(): void
    {
        if (rand(0, 1)) {
            throw new Exception('Something went wrong');
        }

        throw new RuntimeException('Another error');
    }
}
