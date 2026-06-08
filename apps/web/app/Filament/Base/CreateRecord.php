<?php

declare(strict_types=1);

namespace App\Filament\Base;

use App\Filament\Concerns\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord as FilamentCreateRecord;

/**
 * Project base for resource Create pages. Adds an auto-derived descriptive
 * subheading under the page title (see {@see HasResourceSubheading}).
 */
abstract class CreateRecord extends FilamentCreateRecord
{
    use HasResourceSubheading;
}
