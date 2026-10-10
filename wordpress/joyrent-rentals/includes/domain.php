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
        if (str_contains($date, "\0")) throw new InvalidArgumentException('Вкажи коректну дату.');
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
    public static function telegram(mixed $value): string {
        $message='Вкажи Telegram у форматі @username або https://t.me/username.';
        if (!is_string($value) || strlen($value)>80 || preg_match('//u',$value)!==1 || preg_match('/[\x00-\x1F\x7F]/',$value)) throw new InvalidArgumentException($message);
        $value=trim($value);
        if ($value==='') return '';
        if (preg_match('~\A@?([A-Za-z0-9_]{5,32})\z~',$value,$match) || preg_match('~\Ahttps://t\.me/([A-Za-z0-9_]{5,32})/?\z~i',$value,$match)) return '@'.strtolower($match[1]);
        throw new InvalidArgumentException($message);
    }
    private static function plain_contact(string $value): bool {
        // Keep plain user text intact; controls/markup can disappear in WooCommerce
        // or impersonate separate fields in owner notifications.
        return preg_match('//u',$value)===1
            && preg_match('/[\x00-\x1F\x7F]/',$value)===0
            && strip_tags($value)===$value;
    }
    // Normalize syntax independently from today's availability, so an accepted request can replay.
    public static function canonical(array $payload): array {
        self::language($payload);
        $console = is_string($payload['console'] ?? null) ? $payload['console'] : '';
        $days = filter_var($payload['days'] ?? null, FILTER_VALIDATE_INT);
        if ($days === false || $days === null) throw new InvalidArgumentException('Обери термін оренди.');
        $tariff = self::tariff($console, $days);
        unset($tariff['nameRu'], $tariff['descriptionRu']); // Preserve pre-1.1 canonical request fingerprints.
        $start = is_string($payload['startDate'] ?? null) ? $payload['startDate'] : '';
        self::parse_date($start);
        $name = trim(is_string($payload['name'] ?? null) ? $payload['name'] : '');
        if (!self::plain_contact($name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) throw new InvalidArgumentException('Вкажи своє ім’я.');
        $phone = preg_replace('/[\s()\-]/', '', is_string($payload['phone'] ?? null) ? $payload['phone'] : '');
        if (!preg_match('/^(?:\+?380\d{9}|0\d{9})$/', $phone)) throw new InvalidArgumentException('Вкажи український номер телефону.');
        if (($payload['consent'] ?? false) !== true) throw new InvalidArgumentException('Потрібна згода на обробку даних.');
        $method = $payload['method'] ?? 'delivery';
        if (!in_array($method, ['delivery','pickup'], true)) throw new InvalidArgumentException('Обери спосіб отримання.');
        $address_value = array_key_exists('address',$payload) ? $payload['address'] : '';
        if (!is_string($address_value)) throw new InvalidArgumentException('Вкажи адресу доставки в Одесі.');
        $address = trim($address_value);
        if (!self::plain_contact($address) || mb_strlen($address) > 300 || ($method === 'delivery' && mb_strlen($address) < 5)) throw new InvalidArgumentException('Вкажи адресу доставки в Одесі.');
        if ($method === 'pickup') $address = '';
        $controllers = filter_var($payload['controllers'] ?? 1, FILTER_VALIDATE_INT);
        if (!in_array($controllers, [1,2], true)) throw new InvalidArgumentException('Обери один або два геймпади.');
        $game_ids = $payload['gameIds'] ?? [];
        if (!is_array($game_ids) || count($game_ids) > 1000) throw new InvalidArgumentException('Некоректний список ігор.');
        foreach ($game_ids as $game) if (!is_string($game)) throw new InvalidArgumentException('Обери гру з каталогу.');
        $game_ids = array_values(array_unique($game_ids));
        if (count($game_ids) > 100) throw new InvalidArgumentException('Перевищено дозволену кількість ігор.');
        $security_mode = array_key_exists('securityMode',$payload) ? $payload['securityMode'] : 'deposit';
        if (!is_string($security_mode) || !in_array($security_mode,['deposit','contract'],true)) throw new InvalidArgumentException('Обери оформлення із заставою або за договором.');
        $requested_game = array_key_exists('requestedGame',$payload) ? $payload['requestedGame'] : '';
        if (!is_string($requested_game) || preg_match('//u',$requested_game)!==1) throw new InvalidArgumentException('Вкажи назву гри звичайним текстом (до 120 символів).');
        $requested_game = trim($requested_game);
        if (mb_strlen($requested_game)>120 || preg_match('/[\x00-\x1F\x7F]/u',$requested_game) || strip_tags($requested_game)!==$requested_game) throw new InvalidArgumentException('Вкажи назву гри звичайним текстом (до 120 символів).');
        $telegram=self::telegram(array_key_exists('telegram',$payload)?$payload['telegram']:'');
        $data = ['console'=>$console,'days'=>$days,'tariff'=>$tariff,'startDate'=>$start,'returnDate'=>self::return_date($start,$days),'name'=>$name,'phone'=>$phone,'method'=>$method,'address'=>$address,'controllers'=>$controllers,'gameIds'=>$game_ids];
        // Explicit defaults keep the same fingerprint as requests made before these options existed.
        if ($security_mode!=='deposit') $data['securityMode']=$security_mode;
        if ($requested_game!=='') $data['requestedGame']=$requested_game;
        if ($telegram!=='') $data['telegram']=$telegram;
        return $data;
    }
    public static function intent_fingerprint(array $data): string {
        $games = array_values(array_unique($data['gameIds'])); sort($games, SORT_STRING);
        $intent = [
            'console'=>$data['console'],'days'=>$data['days'],'startDate'=>$data['startDate'],
            'name'=>trim($data['name']),'phone'=>preg_replace('/[\s()\-]/','',$data['phone']),
            'method'=>$data['method'],'address'=>$data['method']==='pickup'?'':trim($data['address']),
            'controllers'=>$data['controllers'],'gameIds'=>$games,
            'securityMode'=>$data['securityMode']??'deposit','requestedGame'=>trim($data['requestedGame']??''),
        ];
        $telegram=self::telegram($data['telegram']??'');
        if ($telegram!=='') $intent['telegram']=$telegram;
        return hash('sha256', json_encode($intent, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public static function validate(array $payload, ?string $today = null, ?array $inventory = null): array {
        $data = self::canonical($payload);
        $today = $today ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
        if ($data['startDate'] < $today) throw new InvalidArgumentException('Дата отримання не може бути в минулому.');
        if ($data['startDate'] > self::parse_date($today)->modify('+1 year')->format('Y-m-d')) throw new InvalidArgumentException('Обери дату протягом найближчого року.');
        $inventory = $inventory ?? self::catalog()['games'];
        $records = [];
        foreach ($inventory as $record) if (is_string($record['id'] ?? null) && !isset($records[$record['id']])) $records[$record['id']] = $record;
        foreach ($data['gameIds'] as $game) {
            if (!isset($records[$game])) throw new InvalidArgumentException('Обери гру з каталогу.');
            if (!in_array($data['console'],$records[$game]['platforms'],true)) throw new InvalidArgumentException('Ця гра недоступна для обраної консолі.');
        }
        $configured = class_exists('JR_Settings') ? (int) JR_Settings::public()['maxGames'] : 100;
        if (count($data['gameIds']) > min(100, $configured, count($records))) throw new InvalidArgumentException('Перевищено дозволену кількість ігор.');
        return $data;
    }
}
