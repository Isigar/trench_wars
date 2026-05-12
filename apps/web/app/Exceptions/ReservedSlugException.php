<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
| Source: 02-06-PLAN.md Task 1 + T-02-06-01 (reserved-slug bypass mitigation).
|
| Thrown by ClanSlugGenerator when the derived slug matches a reserved word.
| Callers (FormRequest in plan 02-09, ClanCreateController) should catch this
| and surface it as a validation error to the user.
*/

final class ReservedSlugException extends \RuntimeException {}
