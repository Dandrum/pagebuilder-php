<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\View\Components;

use Dandrum\PageBuilder\Support\BlockRegistry;
use Dandrum\PageBuilder\Support\PageBuilderDocument;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Render extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $blocks;

    /**
     * @param  array<string, mixed>|string|null  $blocks  JSON string or already-decoded array,
     *                                                    either the full `{version, blocks}` document
     *                                                    or just a bare `blocks` array.
     */
    public function __construct(array|string|null $blocks = null)
    {
        $document = is_array($blocks)
            ? PageBuilderDocument::fromArray(array_key_exists('blocks', $blocks) ? $blocks : ['blocks' => $blocks])
            : PageBuilderDocument::fromJson($blocks);

        $this->blocks = $document->blocks;
    }

    public function render(): View
    {
        return view('pagebuilder::render', [
            'blocks' => $this->blocks,
            'registry' => app(BlockRegistry::class),
        ]);
    }
}
