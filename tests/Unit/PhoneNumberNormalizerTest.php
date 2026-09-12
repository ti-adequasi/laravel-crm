<?php

use Webkul\Pbx\Exceptions\InvalidPhoneNumberException;
use Webkul\Pbx\Support\PhoneNumberNormalizer;

it('normalizes valid Brazilian numbers to national format', function (string $raw, string $expected) {
    expect(PhoneNumberNormalizer::normalize($raw))->toBe($expected);
})->with([
    'mobile, pure digits, no country code' => ['11987654321', '11987654321'],
    'landline, pure digits, no country code' => ['1132654321', '1132654321'],
    'mobile, formatted with parens/dash/space' => ['(11) 98765-4321', '11987654321'],
    'landline, formatted with parens/dash/space' => ['(11) 3265-4321', '1132654321'],
    'mobile, with +55' => ['+5511987654321', '11987654321'],
    'landline, with +55' => ['+551132654321', '1132654321'],
    'mobile, with 55 (no +)' => ['5511987654321', '11987654321'],
    'landline, with 55 (no +)' => ['551132654321', '1132654321'],
    'mobile, with leading trunk 0' => ['011987654321', '11987654321'],
    'mobile, with dots' => ['11.98765.4321', '11987654321'],
    'different valid DDD (Rio, 21)' => ['21987654321', '21987654321'],
]);

it('rejects input that cannot be confidently normalized', function (string $raw) {
    expect(fn () => PhoneNumberNormalizer::normalize($raw))
        ->toThrow(InvalidPhoneNumberException::class);
})->with([
    'empty string' => [''],
    'too short' => ['123456'],
    'way too long, unrecognized shape' => ['1234567890123456'],
    'non-numeric garbage' => ['abc'],
    '11 digits but invalid DDD (00)' => ['00987654321'],
    '11 digits, missing the mandatory mobile 9th-digit marker' => ['11887654321'],
    '12 digits, neither 55 nor 0 prefix' => ['123456789012'],
    '13 digits not starting with 55' => ['1234567890123'],
]);

it('rejects null input', function () {
    expect(fn () => PhoneNumberNormalizer::normalize(null))
        ->toThrow(InvalidPhoneNumberException::class);
});
