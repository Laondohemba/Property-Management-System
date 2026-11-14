<?php

namespace Tests\Architecture;

use App\Http\Controllers\Controller;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;
use Spatie\LaravelData\Data;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Architecture rules (spec ARC-10 and §10.2). These fail the build when code crosses a boundary.
 */
class ArchitectureTest extends TestCase
{
    /**
     * Parts of another module that may be used directly. Everything else (Http, Policies,
     * Listeners, Notifications, Console, Support, ...) is internal to its module.
     */
    private const PUBLIC_MODULE_LAYERS = ['Models', 'DTOs', 'Enums', 'Actions', 'Contracts', 'Events'];

    public function test_modules_reach_other_modules_only_through_their_public_layers(): void
    {
        $violations = [];

        foreach ($this->files('app/Modules') as $file) {
            $module = $this->moduleOf($file->getRelativePathname());

            foreach ($this->moduleReferences($file->getContents()) as [$otherModule, $layer, $class]) {
                if ($otherModule === $module || $layer === null || in_array($layer, self::PUBLIC_MODULE_LAYERS, true)) {
                    continue; // own module, another module's root facade (e.g. Audit\Audit), or a public layer
                }

                $violations[] = "{$module}: {$file->getRelativePathname()} uses {$class}";
            }
        }

        $this->assertSame([], $violations, "Use another module's Actions, DTOs, Models, Enums, Contracts or Events instead:\n".implode("\n", $violations));
    }

    public function test_shared_support_code_does_not_depend_on_module_internals(): void
    {
        $violations = [];

        foreach ($this->files('app/Support') as $file) {
            foreach ($this->moduleReferences($file->getContents()) as [, $layer, $class]) {
                if ($layer !== null && ! in_array($layer, self::PUBLIC_MODULE_LAYERS, true)) {
                    $violations[] = "{$file->getRelativePathname()} uses {$class}";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_models_do_not_depend_on_the_http_layer(): void
    {
        $violations = [];

        foreach ($this->files('app/Modules', '*/Models/*.php') as $file) {
            if (preg_match_all('/^use (Illuminate\\\\Http\\\\[^;]+|App\\\\[^;]*\\\\Http\\\\[^;]+);/m', $file->getContents(), $matches)) {
                foreach ($matches[1] as $class) {
                    $violations[] = "{$file->getRelativePathname()} uses {$class}";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_every_application_file_declares_strict_types(): void
    {
        $missing = [];

        foreach ($this->files('app') as $file) {
            if (! str_contains($file->getContents(), 'declare(strict_types=1);')) {
                $missing[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $missing, 'Add declare(strict_types=1) to: '.implode(', ', $missing));
    }

    public function test_no_debugging_calls_are_left_in_the_application(): void
    {
        $found = [];

        foreach ($this->files('app') as $file) {
            if (preg_match('/(?<![\w>:$])(dd|dump|var_dump|ray|ddd)\s*\(/', $file->getContents(), $match)) {
                $found[] = "{$file->getRelativePathname()} ({$match[1]})";
            }
        }

        $this->assertSame([], $found, implode("\n", $found));
    }

    public function test_controllers_extend_the_base_controller(): void
    {
        foreach ($this->classesIn('app/Modules', '*/Http/Controllers/*.php') as $class) {
            $this->assertTrue(is_subclass_of($class, Controller::class), "{$class} must extend ".Controller::class);
        }
    }

    public function test_dtos_are_laravel_data_objects(): void
    {
        foreach ([...$this->classesIn('app/Modules', '*/DTOs/*.php'), ...$this->classesIn('app/Support', '*/DTOs/*.php')] as $class) {
            $this->assertTrue(is_subclass_of($class, Data::class), "{$class} must extend ".Data::class.' so it is part of the generated BFF contract.');
        }
    }

    public function test_enums_are_backed(): void
    {
        foreach ($this->classesIn('app/Modules', '*/Enums/*.php') as $class) {
            $this->assertTrue((new ReflectionEnum($class))->isBacked(), "{$class} must be a backed enum (spec §10.2).");
        }
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function files(string $directory, string $path = '*.php'): iterable
    {
        $finder = Finder::create()->files()->in(dirname(__DIR__, 2).'/'.$directory)->name('*.php');

        if ($path !== '*.php') {
            $finder->path($this->pathRegex($path));
        }

        return $finder;
    }

    /**
     * @return list<class-string>
     */
    private function classesIn(string $directory, string $path): array
    {
        $classes = [];
        $namespace = 'App\\'.str_replace('/', '\\', substr($directory, strlen('app/')));

        foreach ($this->files($directory, $path) as $file) {
            $class = $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if ((class_exists($class) || enum_exists($class)) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function moduleOf(string $relativePath): string
    {
        return explode('/', str_replace('\\', '/', $relativePath))[0];
    }

    /**
     * Every App\Modules\{Module}\{Layer}\... referenced by a file (imports and inline names).
     *
     * @return list<array{0: string, 1: string|null, 2: string}>
     */
    private function moduleReferences(string $source): array
    {
        preg_match_all('/\\\\?App\\\\Modules\\\\(\w+)\\\\([\w\\\\]+)/', $source, $matches, PREG_SET_ORDER);

        return array_map(function (array $match): array {
            $segments = explode('\\', $match[2]);
            // A single segment is a class at the module root, i.e. the module's public facade.
            $layer = count($segments) > 1 ? $segments[0] : null;

            return [$match[1], $layer, 'App\\Modules\\'.$match[1].'\\'.$match[2]];
        }, $matches);
    }

    /**
     * Glob such as "*\/DTOs/*.php" to a Finder path regex (Finder normalises separators to "/").
     */
    private function pathRegex(string $glob): string
    {
        return '#^'.strtr(preg_quote($glob, '#'), ['\*' => '[^/]+']).'$#';
    }
}
