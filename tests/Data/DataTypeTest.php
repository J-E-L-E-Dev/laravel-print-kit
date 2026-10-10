<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Tests\Data;

use Ahinest\LaravelPrintKit\Data\DataType;
use Ahinest\LaravelPrintKit\Data\DataTypeFormatter;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidDataType;
use Ahinest\LaravelPrintKit\Data\Exceptions\InvalidResolvedValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataTypeTest extends TestCase
{
    #[DataProvider('validTypes')]
    public function test_parser(string $code, string $kind, ?int $scale): void
    {
        $type = DataType::parse($code);
        $this->assertSame($kind, $type->kind);
        $this->assertSame($scale, $type->decimalPlaces);
    }

    public static function validTypes(): array
    {
        return [['C', 'C', null], ['N', 'N', null], ['D', 'D', null], ['T', 'T', null], ['D,0', 'D', 0], ['D,2', 'D', 2], ['D,4', 'D', 4], ['D,18', 'D', 18]];
    }

    #[DataProvider('invalidTypes')]
    public function test_invalid_types(mixed $code): void
    {
        $this->expectException(InvalidDataType::class);
        DataType::parse($code);
    }

    public static function invalidTypes(): array
    {
        return array_map(fn ($v) => [$v], ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'currency', 'barcode', '', null, 1, [], 'c', 'D,-1', 'D,19', 'D,01', 'D,2.5', 'D,', 'D, 2', 'D,2,3', ' D', 'N,2']);
    }

    #[DataProvider('formattedValues')]
    public function test_formatting(string $code, mixed $value, string $expected): void
    {
        $this->assertSame($expected, (new DataTypeFormatter)->format($value, DataType::parse($code)));
    }

    public static function formattedValues(): array
    {
        return [['C', null, ''], ['C', 123, '123'], ['C', false, ''], ['C', 'ñ', 'ñ'], ['N', '00125.500', '125.5'],
            ['N', '-0.00', '0'], ['D', '00125.500', '125.500'], ['D,2', '125', '125.00'], ['D,2', '125.1', '125.10'],
            ['D,2', '125.555', '125.56'], ['D,2', '-125.555', '-125.56'], ['D,2', '-125.554', '-125.55'],
            ['D,0', '-0.5', '-1'], ['D,4', 0, '0.0000'], ['D,2', 125.5, '125.50'],
            ['D,2', '999999999999999999999999.995', '1000000000000000000000000.00'],
            ['D,18', '0.0000000000000000005', '0.000000000000000001']];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values(string $code, mixed $value): void
    {
        $this->expectException(InvalidResolvedValue::class);
        (new DataTypeFormatter)->format($value, DataType::parse($code));
    }

    public static function invalidValues(): array
    {
        return [['N', null], ['D', null], ['D,2', null], ['N', true], ['D', '1,25'], ['D', '1 000'], ['D', '1e2'],
            ['D', ''], ['N', INF], ['D', NAN], ['D', []], ['C', []], ['C', INF], ['C', "\xFF"], ['T', 'value'], ['D', str_repeat('1', 1025)]];
    }
}
