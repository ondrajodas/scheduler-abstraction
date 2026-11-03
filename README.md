# Scheduler

Tenká abstrakce nad plánovači úloh a vlastní distribuovaný mutex.

Aplikace závisí jen na `SchedulerInterface` a `MutexInterface`. Implementaci scheduleru vybírá `SchedulerFactory` podle jména, takže jde vyměnit bez zásahu do aplikace: dnes Laravel Scheduler, později crunz nebo cokoliv jiného. Samotná aplikace na Laravelu neběží. Používá se jen balíček `illuminate/console`, a to uvnitř jednoho adaptéru.

## Rozhraní

```php
interface SchedulerInterface
{
    public function add(ScheduledJob $job): void;   // InvalidArgumentException při nevalidním cronu nebo duplicitním jménu
    public function runDue(): void;                 // volat jednou za minutu ze systémového cronu
}

interface JobInterface
{
    public function run(): void;
}

final readonly class ScheduledJob
{
    public function __construct(
        public string $name,                        // unikátní, [a-z0-9._-], zároveň klíč zámku
        public string $cron,                        // crontab výraz: '*/15 * * * *', '0 3 * * 1-5', '@daily'
        public JobInterface $job,
        public bool $withoutOverlapping = true,
        public int $lockTtlSeconds = 3600,
        public ?DateTimeZone $timezone = null,      // null = výchozí timezone PHP
    ) {}
}

interface MutexInterface
{
    public function acquire(string $key, int $ttlSeconds): ?Lock;   // null = zámek drží někdo jiný
    public function release(Lock $lock): void;                      // uvolní jen vlastní zámek
}
```

## Použití

```php
$factory = new SchedulerFactory(new RedisMutex($redis), $logger, $clock);
$scheduler = $factory->create(SchedulerFactory::DRIVER_LARAVEL);   // jméno driveru z konfigurace

$scheduler->add(new ScheduledJob('billing.sync-invoices', '*/15 * * * *', $syncInvoicesJob, lockTtlSeconds: 900));
$scheduler->add(new ScheduledJob(
    'billing.monthly-report',
    '0 6 1 * *',
    $monthlyReportJob,
    timezone: new DateTimeZone('Europe/Prague'),
));

$scheduler->runDue();
```

Systémový cron spouští vstupní skript každou minutu:

```
* * * * * php /app/bin/scheduler-run
```

[`bin/scheduler-run`](bin/scheduler-run) je ukázkový vstupní skript. V aplikaci se scheduler sestavuje v DI kontejneru a skript zůstává co nejtenčí.

## Jak to funguje

- **`SchedulerFactory`** vytvoří implementaci podle jména driveru a vždy ji obalí do `LockingScheduler`.
- **`LockingScheduler`** obalí každý job s `withoutOverlapping: true` do `LockedJob`. Dokud předchozí běh drží zámek `scheduler:<name>`, job se přeskočí. Zámek se uvolňuje ve `finally`, takže se uvolní i tehdy, když job vyhodí výjimku. Implementace scheduleru samy nic nezamykají.
- **`LaravelScheduler`** spouští joby přes eventy Laravel Scheduleru, ale bez Laravel aplikace. Každý job je `CallbackEvent`. `runDue()` nejdřív vybere eventy na řadě (`isDue()` + `filtersPass()`) a pak je jeden po druhém spustí přes `$event->run()`, stejně jako `artisan schedule:run`. Díky výběru předem nemůže dlouhý job způsobit, že joby po něm přijdou o svou minutu. Selhání jobu se zaloguje a ostatní joby na řadě poběží dál.
  - `LaravelRuntime` zastupuje Laravel aplikaci, kterou eventy očekávají jako `$app`: kontejner pro volání callbacků plus `isDownForMaintenance()` a `environment()`.
  - Příkazové eventy (`->command()`, `->exec()`) a `runInBackground()` se nepoužívají, protože potřebují `base_path()` a `artisan`.
  - Laravel `withoutOverlapping()` ani `onOneServer()` se nikdy nezapínají, takže se Laravel mutex nad cache nikdy nezavolá. Nahrazuje ho `DisabledEventMutex`, který vyhodí výjimku, kdyby se ho něco pokusilo použít. Zamykání zůstává v `LockingScheduler`, a proto se chová stejně pro každou implementaci.
  - Aktuální čas bere Laravel z Carbonu (`Date::now()`). V testech se nastavuje přes `Carbon::setTestNow()`.

## Proč vlastní mutex

Laravel 12 `CacheEventMutex` (`withoutOverlapping()`) u cache úložišť s podporou zámků (Redis, databáze) vytvoří skutečný zámek s náhodným vlastníkem. Jinde použije `Cache::add()`. Problém je v uvolnění: `forget()` si vytvoří nový lock objekt a zavolá `forceRelease()`, takže **vlastníka nikdy nekontroluje**.

| Laravel | Problém | `RedisMutex` |
|---|---|---|
| `forget()` = `forceRelease()`, smaže jakýkoli zámek | job běží déle než TTL → zámek vyprší → převezme ho další běh → první běh doběhne a smaže zámek druhého → třetí běh se překryje s druhým | náhodný token + compare-and-delete v Lua skriptu |
| výchozí TTL 1440 min (24 h) | po `kill -9`, OOM nebo deployi je job až den zablokovaný | TTL pro každý job zvlášť (`lockTtlSeconds`), výchozí 1 h |
| u `runInBackground()` uvolňuje zámek jiný proces (`artisan schedule:finish`) | pokud ten neproběhne, zámek zůstane do vypršení TTL | zámek bere i uvolňuje stejný proces ve `finally` |
| chování závisí na cache driveru (lock vs. `Cache::add()`, DynamoDB vyjmuto) | stejný kód se chová jinak podle konfigurace cache | jedno explicitní úložiště s atomickým `SET NX PX` |

Výpadek Redisu Laravel nezamlčí, výjimka projde dál. `RedisMutex` se chová stejně: vyhodí `MutexException` a job se nespustí (fail closed).

## Přidání dalšího scheduleru (např. crunz)

1. Implementovat `SchedulerInterface` v `src/Scheduler/Crunz/CrunzScheduler.php`. Nepoužívat crunz `preventOverlapping()`, zamykání zajistí `LockingScheduler`.
2. Přidat větev `DRIVER_CRUNZ` do `match` v `SchedulerFactory::create()`. Factory už dostává `ClockInterface` pro implementace, které vyhodnocují cron pro zadaný čas.
3. Přidat `tests/Scheduler/Crunz/CrunzSchedulerTest.php`, který podědí `SchedulerTestCase` a implementuje `createScheduler()` a `setNow()`. Musí pak projít stejnými případy jako Laravel (kdy je job na řadě, timezone, selhávající a dlouhé joby, nevalidní výrazy, duplicitní jména), takže se obě implementace chovají stejně.
4. Přepnout jméno driveru v konfiguraci. Joby, crontab i mutex zůstávají beze změny.

## Rozhodnutí a alternativy

| Rozhodnutí | Alternativa | Proč takhle |
|---|---|---|
| **Job je PHP objekt** (`JobInterface`) | Job jako CLI příkaz | Typová bezpečnost, DI a testovatelnost. Z crunz se proto použije jen vyhodnocení cronu, ne jeho runner |
| **Zamykání je společné** v `LockingScheduler` | Napojit mutex přes každou knihovnu (Laravel `EventMutex`, crunz symfony/lock) | Stejné chování bez ohledu na knihovnu; výměna knihovny nemění zamykání |
| **Laravel adaptér používá Laravel runner** (`CallbackEvent`), ne `artisan schedule:run` | Laravel jen jako parser cronu, nebo minimální Laravel aplikace | Používá Laravel Scheduler, aniž by aplikace běžela na Laravelu |
| **Crontab výraz jako string** | Fluent API (`->dailyAt()`) | Umí ho každá knihovna, rozhraní nezávisí na API konkrétní knihovny |
| **`withoutOverlapping` ve výchozím stavu zapnuté** | Výchozí vypnuté jako v Laravelu | Souběh musí být vědomá volba |
| **Vlastní `MutexInterface` nad Redisem** | `symfony/lock`, databázová tabulka | Zadání chce vlastní mutex; Redis má atomický `SET NX PX` |
| **Factory s `match` podle jména driveru** | Registr driverů, DI tagy | Nejjednodušší řešení; nový driver = jedna větev |

## Vědomá omezení

| Omezení | Dopad | Jak rozšířit | Mění rozhraní? |
|---|---|---|---|
| Joby běží za sebou v jednom procesu | Dlouhý job zdrží další joby v téže minutě | Dlouhou práci posílat do fronty; paralelní běh uvnitř adaptéru | ne |
| Chybí deduplikace napříč instancemi (`onOneServer`) | Na dvou instancích se krátký job v jedné minutě spustí dvakrát | Nepovinný parametr `ScheduledJob` + zámek na klíč `{name}:{YmdHi}` | ne (nový parametr s výchozí hodnotou) |
| Zámek nejde prodloužit | Job delší než `lockTtlSeconds` se překryje s dalším během | `MutexInterface::extend()` volané z jobu | **ano**, nová metoda rozbije existující implementace, rozhodnout dřív, než jich bude víc |
| Zmeškaná minuta se nedohání | Zpožděný start cronu přes hranici minuty = job v té minutě neproběhne | Ukládat čas posledního běhu a dohnat | ne |

## Poznámky k nasazení

- Scheduler spouštět na **jedné instanci**. Mutex brání překryvu běhů napříč servery, ale krátký job by se na dvou instancích scheduleru mohl v jedné minutě spustit dvakrát.
- Joby běží za sebou v jednom procesu, takže dlouhý job zdrží další joby na řadě v téže minutě. Dlouhou práci přesunout do fronty, nebo nastavit `lockTtlSeconds` nad maximální dobu běhu jobu.
- Vyžaduje `ext-redis`. Použít Redis instanci nebo databázi bez eviction politiky `allkeys-lru`, jinak může Redis zámky tiše vyhodit.

## Vývoj

Všechny příkazy běží v Dockeru. Redis pro integrační testy je součástí `docker-compose.yml`.

```bash
docker compose build
docker compose run --rm app composer install
docker compose run --rm app composer tests     # PHPUnit, včetně RedisMutex proti skutečnému Redisu
docker compose run --rm app composer phpcs     # PSR-12
docker compose run --rm app composer phpstan   # úroveň max
docker compose run --rm app composer ci        # všechno výše
```

Společné testovací kontrakty zajišťují, že se nové implementace chovají stejně:

- `SchedulerTestCase` pokrývá každou implementaci `SchedulerInterface` (stačí doplnit `createScheduler()` a `setNow()`).
- `MutexTestCase` pokrývá každou implementaci `MutexInterface` (stačí doplnit `createMutex()` a `expire()`).
