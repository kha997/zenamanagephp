<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ZenaChangeRequest extends ChangeRequest
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ZenaChangeRequestFactory::new();
    }
}
