<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

/**
 * Generates the BFF contract for the Angular app (spec ARC-11):
 * every laravel-data DTO and backed enum under app/ becomes a TypeScript type.
 *
 * Run `composer types` after changing a DTO or enum. Development-only: registered by
 * AppServiceProvider when the package is installed.
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    public const OUTPUT_DIRECTORY = 'resources/angular/src/app/core/bff/generated';

    public const OUTPUT_FILE = 'bff.ts';

    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path())
            ->outputDirectory(base_path(self::OUTPUT_DIRECTORY))
            ->writer(new FlatModuleWriter(self::OUTPUT_FILE))
            ->withoutManifest();
    }
}
