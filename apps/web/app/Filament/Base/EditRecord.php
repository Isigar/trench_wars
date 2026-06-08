<?php

declare(strict_types=1);

namespace App\Filament\Base;

use App\Filament\Concerns\HasResourceSubheading;
use Filament\Resources\Pages\EditRecord as FilamentEditRecord;

/**
 * Project base for resource Edit pages. Adds an auto-derived descriptive
 * subheading under the page title (see {@see HasResourceSubheading}).
 */
abstract class EditRecord extends FilamentEditRecord
{
    use HasResourceSubheading;
}
