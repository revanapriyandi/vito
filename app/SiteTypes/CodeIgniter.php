<?php

namespace App\SiteTypes;

use App\Models\Site;

class CodeIgniter extends PHPSite
{
    public static function id(): string
    {
        return 'codeigniter';
    }

    public static function make(): self
    {
        return new self(new Site(['type' => self::id()]));
    }

    public function baseCommands(): array
    {
        return array_merge(parent::baseCommands(), [
            [
                'name' => 'spark:migrate',
                'command' => 'php spark migrate --all',
            ],
            [
                'name' => 'spark:optimize',
                'command' => 'php spark optimize',
            ],
        ]);
    }
}
