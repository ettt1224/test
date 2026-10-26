<?php

namespace App\Enums;

/** GHS 危害標示 */
enum HazardLabel: string
{
    case Explosive = 'explosive';
    case Flammable = 'flammable';
    case Oxidizer = 'oxidizer';
    case CompressedGas = 'compressed_gas';
    case Corrosive = 'corrosive';
    case Toxic = 'toxic';
    case Irritant = 'irritant';
    case HealthHazard = 'health_hazard';
    case Environmental = 'environmental';

    public function label(): string
    {
        return match ($this) {
            self::Explosive => '爆炸物',
            self::Flammable => '極燃/易燃',
            self::Oxidizer => '氧化性',
            self::CompressedGas => '高壓氣體',
            self::Corrosive => '腐蝕',
            self::Toxic => '毒性',
            self::Irritant => '刺激/警告',
            self::HealthHazard => '健康危害',
            self::Environmental => '環境危害',
        };
    }

    /** 匯入時可用代碼或中文標籤 */
    public static function fromInput(string $value): ?self
    {
        $value = trim($value);
        foreach (self::cases() as $case) {
            // 「極燃/易燃」可只填「極燃」或「易燃」
            if ($case->value === $value || $case->label() === $value || in_array($value, explode('/', $case->label()), true)) {
                return $case;
            }
        }

        return null;
    }
}
