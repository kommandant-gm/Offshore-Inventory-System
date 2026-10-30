<?php

namespace App\Support;

class ScaffoldingOptions
{
    public const DESCRIPTIONS = [
        'SCAFFOLD TUBE' => ['SCAFFOLD TUBE ODD SIZE', 'SCAFFOLD TUBE 6FT', 'SCAFFOLD TUBE 10FT', 'SCAFFOLD TUBE 12FT', 'SCAFFOLD TUBE 15FT', 'SCAFFOLD TUBE 20FT'],
        'SCAFFOLD BOARD' => ['SCAFFOLD BOARD 6FT', 'SCAFFOLD BOARD 12FT'],
        'COUPLERS, BASE PLATE, JOINT PIN' => ['SINGLE COUPLER', 'DOUBLE COUPLER', 'SWIVEL COUPLER', 'SLEEVE COUPLER', 'BEAM CLAMP', 'BASE PLATE', 'JOINT PIN'],
        'LADDERS' => ['WOODEN LADDER 6FT', 'WOODEN LADDER 10FT', 'WOODEN LADDER 12FT', 'WOODEN LADDER 15FT', 'WOODEN LADDER 20FT', 'FIBRE LADDER 12FT', 'FIBRE LADDER 20FT', 'FIBRE LADDER 24FT', 'ALUMINIUM LADDER'],
        'LADDER BEAM' => ['LADDER BEAM 10FT', 'LADDER BEAM 12FT', 'LADDER BEAM 20FT'],
    ];

    public const CONTRACTS = ['SHELL', 'SKA-MCM', 'SKA-HUC', 'SBA', 'EnQuest'];

    public static function defaults(string $kind): array
    {
        return match ($kind) {
            'subcategory' => array_keys(self::DESCRIPTIONS),
            'description' => array_merge(...array_values(self::DESCRIPTIONS)),
            'contract' => self::CONTRACTS,
        };
    }
}
