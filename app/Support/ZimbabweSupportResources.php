<?php

namespace App\Support;

class ZimbabweSupportResources
{
    public static function primaryCrisisContact(): string
    {
        $configured = trim(SystemSettings::getString('crisis_hotline', ''));

        return $configured !== '' ? $configured : '112';
    }

    /**
     * @return array<int, array{name: string, contact: string, description: string, action: string, value: string}>
     */
    public static function crisisResources(): array
    {
        $resources = [];
        $configured = trim(SystemSettings::getString('crisis_hotline', ''));

        if ($configured !== '') {
            $resources[] = [
                'name' => 'Campus or configured crisis contact',
                'contact' => $configured,
                'description' => 'Use the crisis contact configured by your institution for urgent support.',
                'action' => 'call',
                'value' => self::dialTarget($configured),
            ];
        }

        $resources[] = [
            'name' => 'Zimbabwe emergency services',
            'contact' => '112',
            'description' => 'Use this immediately on supported mobile networks in Zimbabwe if you are in danger right now.',
            'action' => 'call',
            'value' => '112',
        ];

        $resources[] = [
            'name' => 'General emergency (fixed line)',
            'contact' => '999',
            'description' => 'Use this emergency number if you are calling from a fixed line in Zimbabwe.',
            'action' => 'call',
            'value' => '999',
        ];

        $resources[] = [
            'name' => 'Childline Zimbabwe',
            'contact' => '116',
            'description' => 'Free crisis and protection support for children and young people across Zimbabwe.',
            'action' => 'call',
            'value' => '116',
        ];

        $resources[] = [
            'name' => 'Friendship Bench Zimbabwe',
            'contact' => 'friendshipbenchzimbabwe.org/need-help',
            'description' => 'Zimbabwe-based free talk therapy and peer support, including online and in-person options.',
            'action' => 'link',
            'value' => 'https://www.friendshipbenchzimbabwe.org/need-help',
        ];

        return array_values(array_filter($resources, function (array $resource): bool {
            if ($resource['action'] !== 'call') {
                return true;
            }

            return trim($resource['value']) !== '';
        }));
    }

    public static function crisisPromptContext(): string
    {
        return implode("\n", [
            '- This platform serves students in Zimbabwe, including Africa University.',
            '- When offering urgent support or referrals, prioritize Zimbabwe-specific resources.',
            '- Default to Zimbabwe emergency services on 112, general emergency support on 999 for fixed lines, Childline Zimbabwe on 116, and Friendship Bench Zimbabwe at https://www.friendshipbenchzimbabwe.org/need-help.',
            '- Do not mention hotlines from other countries unless the student explicitly says they are outside Zimbabwe or asks for international options.',
        ]);
    }

    public static function crisisSummaryText(): string
    {
        $primary = self::primaryCrisisContact();

        if ($primary === '112') {
            return 'If you are in Zimbabwe, call 112 now, use 999 from a fixed line, or contact Childline Zimbabwe on 116. Friendship Bench Zimbabwe also offers support at friendshipbenchzimbabwe.org/need-help.';
        }

        return "If you are in Zimbabwe, call {$primary} now. You can also use emergency services on 112, 999 from a fixed line, Childline Zimbabwe on 116, or Friendship Bench Zimbabwe at friendshipbenchzimbabwe.org/need-help.";
    }

    private static function dialTarget(string $contact): string
    {
        return trim((string) preg_replace('/[^\d+]/', '', $contact));
    }
}
