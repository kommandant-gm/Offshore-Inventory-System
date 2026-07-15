<?php

namespace App\Support;

class AssistantPrompts
{
    public static function defaults(): array
    {
        return [
            'Where is CON-Y1-0001?',
            'What is the current stock for CAT-ROPE-001?',
            'Show anomalies',
            'Show critical anomalies',
            'Why is CON-Y1-0001 flagged?',
            'Show anomalies for Labuan Inventory',
            'Show anomalies for Consumables',
            'Show last movement for CAT-HOSE-009.',
            'Tell me about AIR HOSE 1 1/2".',
            'How many items are in Labuan Inventory?',
            'How many items are in Consumables?',
            'Total stock in Labuan Inventory',
        ];
    }

    public static function forBranch(?string $code): array
    {
        if ($code !== 'KL-IT') return self::defaults();
        return ['How many IT assets are available?','Who has DESBKL/LT/2022/001?','Show assets under repair','How many assets run Windows 11?','Show assets older than 5 years','Show assets assigned to Project department'];
    }
}
