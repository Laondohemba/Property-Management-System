<?php

namespace Tests\Feature;

use App\Providers\TypeScriptTransformerServiceProvider;
use App\Support\Http\DTOs\ProblemData;
use App\Support\Http\Problem;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The SPA's BFF types are generated from the backend DTOs and committed (spec ARC-11).
 * This fails whenever a DTO or enum changed without regenerating: run `composer types`.
 */
class BffContractTest extends TestCase
{
    public function test_the_committed_typescript_contract_is_up_to_date(): void
    {
        $path = base_path(TypeScriptTransformerServiceProvider::OUTPUT_DIRECTORY.'/'.TypeScriptTransformerServiceProvider::OUTPUT_FILE);
        $this->assertFileExists($path, 'Generate the BFF types with `composer types`.');

        $committed = file_get_contents($path);

        try {
            Artisan::call('typescript:transform');
            $generated = file_get_contents($path);
        } finally {
            file_put_contents($path, $committed);
        }

        $this->assertSame(
            $this->normalize($committed),
            $this->normalize($generated),
            'The generated BFF types differ from the committed copy. Run `composer types` and commit the result.',
        );
    }

    public function test_problem_responses_match_the_problem_contract(): void
    {
        $body = Problem::response(422, 'validation_failed', 'Validation failed', 'Name is required.', [
            'errors' => [['field' => 'name', 'message' => 'Name is required.']],
        ])->getData(true);

        $this->assertSame($body, ProblemData::from($body)->toArray());
        $this->assertSame(['type', 'title', 'status', 'code', 'detail', 'errors'], array_keys($body));
    }

    private function normalize(string $contents): string
    {
        return str_replace("\r\n", "\n", trim($contents));
    }
}
