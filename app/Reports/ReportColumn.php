<?php

namespace App\Reports;

use Closure;

class ReportColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public Closure $value,
    ) {}

    public static function make(string $key, string $label, Closure $value): self
    {
        return new self($key, $label, $value);
    }

    public function resolve(object $record): mixed
    {
        return ($this->value)($record);
    }
}
