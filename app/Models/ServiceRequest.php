<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $user_id
 * @property string $title
 * @property string $status
 */
#[Fillable(['title', 'status'])]
class ServiceRequest extends Model {}
