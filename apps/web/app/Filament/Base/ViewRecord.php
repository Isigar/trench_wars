<?php

declare(strict_types=1);

namespace App\Filament\Base;

use App\Filament\Concerns\HasResourceSubheading;
use Filament\Resources\Pages\ViewRecord as FilamentViewRecord;

/**
 * Project base for resource View pages. Adds an auto-derived descriptive
 * subheading under the page title (see {@see HasResourceSubheading}).
 */
abstract class ViewRecord extends FilamentViewRecord
{
    use HasResourceSubheading;
}
