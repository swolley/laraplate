<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Types;

use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\AsciiStringType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\DateIntervalType;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\TimeImmutableType;
use Doctrine\DBAL\Types\Type as DoctrineType;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;

enum DoctrineTypeEnum: string
{
    case ASCII_STRING = 'ascii_string';
    case BIGINT = 'bigint';
    case BINARY = 'binary';
    case BLOB = 'blob';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATE_IMMUTABLE = 'date_immutable';
    case DATEINTERVAL = 'dateinterval';
    case DATETIME = 'datetime';
    case DATETIME_IMMUTABLE = 'datetime_immutable';
    case DATETIMETZ = 'datetimetz';
    case DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
    case DECIMAL = 'decimal';
    case FLOAT = 'float';
    case GUID = 'guid';
    case INTEGER = 'integer';
    case JSON = 'json';
    case SIMPLE_ARRAY = 'simple_array';
    case SMALLINT = 'smallint';
    case STRING = 'string';
    case TEXT = 'text';
    case TIME = 'time';
    case TIME_IMMUTABLE = 'time_immutable';
    case UNKNOWN = 'unknown';

    public static function fromDoctrine(DoctrineType|self $type): self
    {
        if ($type instanceof self) {
            return $type;
        }

        return self::fromDoctrineType($type);
    }

    public static function fromString(string $type): self
    {
        return self::tryFrom($type) ?: self::UNKNOWN;
    }

    private static function fromDoctrineType(DoctrineType $type): self
    {
        /**
         * This variable exists only so PHPStan
         * can treat it as a regular string and not a
         * `class-string<Doctrine\DBAL\Types\Type>`.
         *
         * Otherwise PHPStan trips over the types not
         * directy extending `Doctrine\DBAL\Types\Type`.
         */
        $class = $type::class;

        return match ($class) {
            AsciiStringType::class => self::ASCII_STRING,
            BigIntType::class => self::BIGINT,
            BinaryType::class => self::BINARY,
            BlobType::class => self::BLOB,
            BooleanType::class => self::BOOLEAN,
            DateType::class => self::DATE,
            DateImmutableType::class => self::DATE_IMMUTABLE,
            DateIntervalType::class => self::DATEINTERVAL,
            DateTimeType::class => self::DATETIME,
            DateTimeImmutableType::class => self::DATETIME_IMMUTABLE,
            DateTimeTzType::class => self::DATETIMETZ,
            DateTimeTzImmutableType::class => self::DATETIMETZ_IMMUTABLE,
            DecimalType::class => self::DECIMAL,
            FloatType::class => self::FLOAT,
            GuidType::class => self::GUID,
            IntegerType::class => self::INTEGER,
            JsonType::class => self::JSON,
            SimpleArrayType::class => self::SIMPLE_ARRAY,
            SmallIntType::class => self::SMALLINT,
            StringType::class => self::STRING,
            TextType::class => self::TEXT,
            TimeType::class => self::TIME,
            TimeImmutableType::class => self::TIME_IMMUTABLE,
            default => self::UNKNOWN,
        };
    }

    // public function raw(): ?string
    // {
    //     if ($this->value === self::UNKNOWN->value) {
    //         return null;
    //     }

    //     return $this->value;
    // }
}
