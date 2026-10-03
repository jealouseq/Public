<?php
declare(strict_types=1);

final class JR_Domain {
    public static function catalog(): array {
        static $data = null;
        if ($data === null) $data = json_decode((string) file_get_contents(dirname(__DIR__) . '/data/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
        return $data;
    }
    public static function tariff(string $console, int $days): array {
        foreach (self::catalog()['tariffs'][$console] ?? [] as $tariff) {
            if ($tariff['days'] === $days) return $tariff;
        }
        throw new InvalidArgumentException('Обери доступний тариф.');
    }
    public static function parse_date(string $date): DateTimeImmutable {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Kyiv'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Вкажи коректну дату.');
        return $parsed;
    }
    public static function return_date(string $date, int $days): string {
        if ($days < 1 || $days > 30) throw new InvalidArgumentException('Некоректний термін.');
        return self::parse_date($date)->modify('+' . $days . ' days')->format('Y-m-d');
    }
    public static function language(array $payload): string {
        $language = $payload['language'] ?? 'uk';
        if (!is_string($language) || !in_array($language, ['uk','ru'], true)) throw new InvalidArgumentException('Обери мову uk або ru.');
        return $language;
    }
    public static function validate(array $payload, ?string $today = null, ?array $inventory = null): array {
        self::language($payload);
        $console = is_string($payload['console'] ?? null) ? $payload['console'] : '';
        $days = filter_var($payload['days'] ?? null, FILTER_VALIDATE_INT);
        if ($days === false || $days === null) throw new InvalidArgumentException('Обери термін оренди.');
        $tariff = self::tariff($console, $days);
        unset($tariff['nameRu'], $tariff['descriptionRu']); // Preserve pre-1.1 canonical request fingerprints.
        $start = is_string($payload['startDate'] ?? null) ? $payload['startDate'] : '';
        self::parse_date($start);
        $today = $today ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
        if ($start < $today) throw new InvalidArgumentException('Дата отримання не може бути в минулому.');
        if ($start > self::parse_date($today)->modify('+1 year')->format('Y-m-d')) throw new InvalidArgumentException('Обери дату протягом найближчого року.');
        $name = trim(is_string($payload['name'] ?? null) ? $payload['name'] : '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) throw new InvalidArgumentException('Вкажи своє ім’я.');
        $phone = preg_replace('/[\s()\-]/', '', is_string($payload['phone'] ?? null) ? $payload['phone'] : '');
        if (!preg_match('/^(?:\+?380\d{9}|0\d{9})$/', $phone)) throw new InvalidArgumentException('Вкажи український номер телефону.');
        if (($payload['consent'] ?? false) !== true) throw new InvalidArgumentException('Потрібна згода на обробку даних.');
        $method = $payload['method'] ?? 'delivery';
        if (!in_array($method, ['delivery','pickup'], true)) throw new InvalidArgumentException('Обери спосіб отримання.');
        $address = trim(is_string($payload['address'] ?? null) ? $payload['address'] : '');
        if ($method === 'delivery' && (mb_strlen($address) < 5 || mb_strlen($address) > 300)) throw new InvalidArgumentException('Вкажи місто та адресу доставки.');
        $controllers = filter_var($payload['controllers'] ?? 1, FILTER_VALIDATE_INT);
        if (!in_array($controllers, [1,2], true)) throw new InvalidArgumentException('Обери один або два геймпади.');
        $game_ids = $payload['gameIds'] ?? [];
        if (!is_array($game_ids) || count($game_ids) > 1000) throw new InvalidArgumentException('Некоректний список ігор.');
        $inventory = $inventory ?? self::catalog()['games'];
        $records = [];
        foreach ($inventory as $record) if (is_string($record['id'] ?? null) && !isset($records[$record['id']])) $records[$record['id']] = $record;
        foreach ($game_ids as $game) {
            if (!is_string($game) || !isset($records[$game])) throw new InvalidArgumentException('Обери гру з каталогу.');
            $record = $records[$game];
            if (!in_array($console,$record['platforms'],true)) throw new InvalidArgumentException('Ця гра недоступна для обраної консолі.');
        }
        $game_ids = array_values(array_unique($game_ids));
        $configured = class_exists('JR_Settings') ? (int) JR_Settings::public()['maxGames'] : 100;
        if (count($game_ids) > min(100, $configured, count($records))) throw new InvalidArgumentException('Перевищено дозволену кількість ігор.');
        return ['console'=>$console,'days'=>$days,'tariff'=>$tariff,'startDate'=>$start,'returnDate'=>self::return_date($start,$days),'name'=>$name,'phone'=>$phone,'method'=>$method,'address'=>$address,'controllers'=>$controllers,'gameIds'=>$game_ids];
    }
}
