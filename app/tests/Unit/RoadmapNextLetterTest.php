<?php

declare(strict_types=1);

use App\Http\Controllers\RoadmapController;

/**
 * Roadmap items are lettered A..Z then AA.. — chr(65+n) silently broke at
 * item 27 ('['). These tests pin the base-26 conversion via reflection.
 */
function nextLetter(int $max): string
{
    $controller = (new ReflectionClass(RoadmapController::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(RoadmapController::class, 'nextLetter');
    $method->setAccessible(true);

    return $method->invoke($controller, $max);
}

it('letters the first twenty six items A through Z', function (): void {
    expect(nextLetter(0))->toBe('A')
        ->and(nextLetter(12))->toBe('M')
        ->and(nextLetter(25))->toBe('Z');
});

it('continues AA onwards past Z instead of emitting punctuation', function (): void {
    expect(nextLetter(26))->toBe('AA')
        ->and(nextLetter(27))->toBe('AB')
        ->and(nextLetter(51))->toBe('AZ')
        ->and(nextLetter(52))->toBe('BA');
});

it('stays within ascii letters for deep indexes', function (): void {
    expect(nextLetter(701))->toBe('ZZ')      // 26*26 - 1
        ->and(nextLetter(702))->toBe('AAA'); // triple letters begin
});
