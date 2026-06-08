<?php

declare(strict_types=1);

namespace App\Filament\Base;

use App\Filament\Concerns\HasResourceSubheading;
use Filament\Resources\Pages\ListRecords as FilamentListRecords;

/**
 * Project base for resource List pages. Adds an auto-derived descriptive
 * subheading under the page title (see {@see HasResourceSubheading}). Resource
 * List pages extend this instead of Filament's ListRecords directly.
 */
abstract class ListRecords extends FilamentListRecords
{
    use HasResourceSubheading;
}
