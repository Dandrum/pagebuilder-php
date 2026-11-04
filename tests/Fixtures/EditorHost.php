<?php

declare(strict_types=1);

namespace Dandrum\PageBuilder\Tests\Fixtures;

use Dandrum\PageBuilder\Concerns\HasPageBuilder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Image options come from `config('pagebuilder.image')`, which tests set before mounting.
 */
final class EditorHost extends Component
{
    use HasPageBuilder;

    /** @var list<string> */
    public static array $stored = [];

    /** @var array<string, mixed>|null */
    public ?array $initial = null;

    /**
     * @param  array<string, mixed>|null  $initial
     */
    public function mount(?array $initial = null): void
    {
        $this->initial = $initial;
        $this->initializePageBuilder();
    }

    protected function getPageBuilderModel(): Model
    {
        return new Page(['content' => $this->initial]);
    }

    public function storePageBuilderUpload(string $blockId, TemporaryUploadedFile $file, array $data): array
    {
        self::$stored[] = $file->getClientOriginalName();

        return ['media_id' => 2];
    }

    public function validateAll(): void
    {
        $this->validate($this->pageBuilderRules(), $this->pageBuilderMessages());
    }

    public function storePending(): void
    {
        $this->storePendingPageBuilderUploads();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
