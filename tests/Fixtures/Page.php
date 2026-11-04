<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Unsaved, in-memory model: the editor only reads its `content` attribute. */
final class Page extends Model
{
    protected $guarded = [];

    protected $casts = ['content' => 'array'];
}
