<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Newsfeed extends Model
{
  protected $table = 'newsfeed';
  protected $fillable = [ 'title', 'message', 'from', 'until', 'data' ];
  protected $casts = [
    'from' => 'datetime',
    'until' => 'datetime',
    'data' => 'array',
  ];
  protected $appends = [ 'status' ];
  public $timestamps = false;

  public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
  {
    $at ??= CarbonImmutable::now();

    return $query
      ->where(function (Builder $query) use ($at) {
        $query->whereNull('from')->orWhere('from', '<=', $at);
      })
      ->where(function (Builder $query) use ($at) {
        $query->whereNull('until')->orWhere('until', '>=', $at);
      });
  }

  public function getStatusAttribute(): string
  {
    $now = CarbonImmutable::now();

    if ($this->from?->isAfter($now)) {
      return 'scheduled';
    }

    if ($this->until?->isBefore($now)) {
      return 'expired';
    }

    return 'active';
  }
}
