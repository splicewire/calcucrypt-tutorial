<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A Post's publication state.
 *
 * Inlined from the retired `splicewire/laravel-satellite-publishing` package (see
 * `app/Models/Post.php` for the full account). Only the two members this site ever used are
 * reproduced — `content:sync` sets `Published` or `Draft` and nothing reads any other state — so
 * this is deliberately not a guess at the original enum's full shape.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
