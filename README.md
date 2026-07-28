# tibco-fw

Könnyűsúlyú, modulos felépítésű PHP 8 keretrendszer. Nem tartalmaz beépített admin
felületet vagy frontend témát — csak a backend vázat adja: routing (Slim 4),
dependency injection konténer (PHP-DI), Twig sablonrendszer, Eloquent
(Illuminate/Database) ORM, Monolog naplózás és egy egyszerű modul-betöltő.

## Tartalomjegyzék

- [Követelmények](#követelmények)
- [Telepítés](#telepítés)
- [Könyvtárstruktúra](#könyvtárstruktúra)
- [Hogyan indul el egy kérés](#hogyan-indul-el-egy-kérés)
- [Konfiguráció](#konfiguráció)
- [Modulok](#modulok)
- [Osztályok és a konténer](#osztályok-és-a-konténer)
- [Middleware-ek](#middleware-ek)
- [Nézetek (Twig)](#nézetek-twig)
- [Session és CSRF védelem](#session-és-csrf-védelem)
- [Adatbázis](#adatbázis)
- [Naplózás](#naplózás)
- [Hibakezelés](#hibakezelés)
- [Helperek](#helperek)
- [Validáció](#validáció)
- [Új modul létrehozása – gyors útmutató](#új-modul-létrehozása--gyors-útmutató)
- [Licenc](#licenc)

## Követelmények

- PHP 8.2+
- Composer
- Apache (`mod_rewrite`) vagy más webszerver, ami tudja a `public/.htaccess`-ben
  lévő átírási szabályt (minden kérést az `index.php`-ra irányít)
- MySQL (vagy bármi más, amit az Illuminate/Database driverei támogatnak), ha
  adatbázist is használ a projekt

## Telepítés

```bash
composer install
```

A webszerver dokumentumgyökere a `public/` mappa legyen. A `cache/` és `log/`
mappákat írhatóvá kell tenni a webszerver felhasználója számára (mindkettő
`.gitignore`-olt, futásidőben jönnek létre a tartalmuk).

A keretrendszer nem tölt be `.env` fájlt — a `getenv()`-vel olvasott
környezeti változókat (lásd [Konfiguráció](#konfiguráció)) a szerver szintjén
(Apache `SetEnv`, Docker `environment:`, shell `export`, stb.) kell beállítani.

## Könyvtárstruktúra

```
app/
  settings.php            – teljes alkalmazás-konfiguráció
  middlewares/             – saját (nem modulhoz kötött) middleware-ek
  resources/templates/     – alap layout twig sablonok (app.twig, elements.twig)
  modules/
    <modul_neve>/
      routes.php           – a modul route-jai
      actions/             – HTTP action osztályok (kontrollerek)
      middlewares/         – csak erre a modulra vonatkozó middleware-ek
      repositories/        – adatelérési réteg
      factories/           – egyéb szolgáltatás/gyártó osztályok
      resources/templates/ – a modul saját twig sablonjai
public/
  index.php                – belépési pont
  .htaccess                 – mod_rewrite szabály
system/
  init.php                  – bootstrap: konténer, middleware-ek, modulok betöltése
  Core.php                  – Action/Middleware/Repository/Factory közös őse
  Action.php, Middleware.php, Repository.php, Factory.php
  View.php                  – Twig view kiterjesztés (sablonnév-feloldás)
  TwigHelperFunctions.php    – Twig-be regisztrált helper függvények
  container/
    container_init.php      – a konténer szolgáltatásainak összeszedése
    sets/                    – egyenként: settings, session, router, logger, view, database, helper
  middlewares/               – rendszerszintű middleware-ek (Route, Session, Csrf)
  helpers/                   – ArrayHelper, RequestHelper
  validatorcustomrules/      – saját Respect\Validation szabályok helye
cache/                       – twig cache (gitignore-olt)
log/                          – naplófájlok (gitignore-olt)
```

## Hogyan indul el egy kérés

1. `public/index.php` ellenőrzi a PHP verziót, majd betölti a `system/init.php`-t.
2. `system/init.php`:
   - definiálja az alapkonstansokat (`ROOT_DIR`, `APP_DIR`, `MODULES_DIR`, stb.),
   - betölti a Composer autoloadert,
   - létrehoz egy Slim alkalmazást PHP-DI konténerrel,
   - beregisztrálja a konténer alapszolgáltatásait (`system/container/sets/*`),
   - beállítja az időzónát és a locale-t az `app/settings.php` alapján,
   - class alias-okat hoz létre, hogy a rendszer- és modul-middleware-ek/action-ök
     egyszerű `Request`/`Response`/`RequestHandler` típusneveket használhassanak,
   - beállítja a Respect\Validation saját szabályainak namespace-ét,
   - felépíti a middleware-lánc listáját (rendszer → app → modulok, lásd lentebb),
   - sorra betölti az engedélyezett modulokat: regisztrálja az `actions`,
     `factories`, `repositories`, `middlewares` osztályaikat a konténerben, majd
     betölti a modul `routes.php`-ját, és a route callable rövidítéseket
     (`"PageAction:metodus"`) teljes osztálynévvé alakítja,
   - hozzáadja a middleware-eket az alkalmazáshoz, majd elindítja Slimet
     (`$app->run()`).

## Konfiguráció

Minden beállítás az [`app/settings.php`](app/settings.php) fájlban van:

```php
return [
    'system' => [
        'timezone' => 'Europe/Budapest',
        'locale'   => 'hu_HU.utf8',
        'debug'    => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
        'session'  => [...],
        'logger'   => [...],
        'twig'     => [...],
        'database' => [
            'testdb' => [
                'driver'   => 'mysql',
                'host'     => getenv('DB_HOST') ?: 'hostname',
                'database' => getenv('DB_DATABASE') ?: 'db_name',
                'username' => getenv('DB_USERNAME') ?: 'db_user',
                'password' => getenv('DB_PASSWORD') ?: 'db_pass',
                ...
            ]
        ]
    ],
    'middlewares' => [
        'RequestLogMiddleware' => ['enabled' => true, 'weight' => 0],
    ],
    'modules' => [
        'auth'    => ['enabled' => true, 'weight' => 0],
        'example' => ['enabled' => true, 'weight' => 2],
    ]
];
```

- **`debug`**: csak akkor jelenik meg részletes hibakimenet (stack trace) a
  válaszban, ha az `APP_DEBUG` környezeti változó igazra értékelődik ki.
- **`database`**: kulcsonként egy Illuminate/Database kapcsolat; mindegyik
  elérhető a konténerből `$this->db->kulcsnev` formában (lásd
  [Adatbázis](#adatbázis)).
- **`middlewares`**: az `app/middlewares/` mappában lévő saját (nem
  modul-specifikus) middleware-ek be- és kikapcsolása, valamint futási
  sorrendje (`weight`, kisebb szám = korábban fut).
- **`modules`**: mely modulok legyenek betöltve, és milyen sorrendben
  (`weight`, kisebb szám = korábban töltődik be).

## Modulok

Egy modul (`app/modules/<nev>/`) önálló funkcionális egység, saját route-okkal,
action-ökkel, middleware-ekkel, repository-kkal és sablonokkal. A keretrendszer
két minta-modult tartalmaz:

- **`auth`** – bejelentkezési oldal (`/login`) és egy `AuthMiddleware`, ami
  bejelentkezés-ellenőrzést demonstrál (jelenleg kikommentezett `if (0)` ággal,
  csak vázlatnak).
- **`example`** – egy egyszerű főoldal (`/`), ami egy `Repository`-n keresztül
  kér le adatot, és egy Twig sablont renderel.

### Route regisztráció

A modul `routes.php`-jában a megszokott Slim szintaxis érvényes, a callable
viszont rövidített formában is megadható:

```php
// app/modules/example/routes.php
$app->get('/', 'PageAction:mainPage')->setName('main_page');
```

- `'PageAction:metodus'` – a **saját** modul `actions/` mappájából oldódik fel.
- `'@masikmodul\PageAction:metodus'` – egy **másik**, engedélyezett modul
  action osztályára hivatkozik.

### Osztályok elérhetősége a konténerben

Betöltéskor minden `actions/`, `factories/`, `repositories/` osztály
regisztrálva lesz a konténerben `@modulnev\tipus\OsztalyNev` néven; a
`middlewares/` osztályok pedig bekerülnek a globális middleware-listába.

## Osztályok és a konténer

Az `Action`, `Middleware`, `Repository` és `Factory` osztályok mind a
[`system\Core`](system/Core.php) ősosztályból származnak, ami a konténerhez
kényelmes hozzáférést biztosít:

```php
final class PageAction extends Action {
    public function mainPage(Request $request, Response $response, $args): Response {
        $name = $this->repository('MainRepository')->getName();
        $this->log->info('valami történt');
        return $this->view->render($response, 'main_page.twig', ['name' => $name]);
    }
}
```

- **`__get`**: bármilyen konténerben regisztrált szolgáltatás elérhető
  property-ként (`$this->log`, `$this->db`, `$this->session`, `$this->view`,
  `$this->helper`, `$this->route`, `$this->routeparser`, stb.).
- **`repository('Nev')` / `factory('Nev')`**: a saját modul repository/factory
  osztályát adja vissza; `repository('masikmodul/Nev')` formában másik,
  engedélyezett modulból is elérhető (a `factory()` védett, csak Action/
  Middleware/Repository/Factory osztályból hívható).

### Repository vs. Factory — konvenció

A `Repository` és a `Factory` osztály jelenleg kódszinten teljesen egyforma
(mindkettő üres, a közös `Core`-t örökli) — a kettő közti különbség tisztán
**elnevezési konvenció**, amit érdemes betartani:

- **`Repository`** — csak lekérdezés, mellékhatás nélküli művelet (pl.
  `getName()`, `findById()`). Egy Repository-metódus hívása biztonságosan
  megismételhető, nem módosít adatot.
- **`Factory`** — mutáció: adatbázis `insert`/`update`/`delete`, vagy bármi
  más, ami tényleges állapotváltozással jár.

Ez a **CQS (Command Query Separation)** elve a data layerre alkalmazva: a
metódus típusából (melyik osztályban van) egy pillantásra látszik, hogy
biztonságos-e csak úgy meghívni, vagy módosít valamit. Mivel ezt jelenleg
semmi nem kényszeríti ki kódszinten, ez a fejlesztői fegyelmen (és a helyes
osztályba/metódusba való besoroláson) múlik.

## Middleware-ek

A tényleges futási sorrend (elöl a legkorábban futó):

1. **Rendszer middleware-ek** (mindig aktívak):
   [`RouteMiddleware`](system/middlewares/RouteMiddleware.php) (a `route`
   objektumot teszi elérhetővé), [`SessionMiddleware`](system/middlewares/SessionMiddleware.php)
   (session indítása, CSRF token generálása), [`CsrfMiddleware`](system/middlewares/CsrfMiddleware.php)
   (nem biztonságos HTTP metódusoknál CSRF token ellenőrzése).
2. **Saját, app-szintű middleware-ek** (`app/middlewares/`), az
   `app/settings.php` `middlewares` szekciójában megadott `weight` szerint
   sorrendbe rendezve. Alapból van egy [`RequestLogMiddleware`](app/middlewares/RequestLogMiddleware.php),
   ami minden kérést naplóz (metódus, útvonal, státuszkód, IP, futási idő).
3. **Modulonkénti middleware-ek**, a modulok betöltési sorrendjében (szintén
   `weight` alapján az `app/settings.php`-ben). Egy modulon belül, ha
   több middleware osztály is van a `middlewares/` mappájában, azok
   sorrendje a modul beállításában megadható `middlewares.<Osztály>.weight`
   szerint alakul:
   ```php
   'modules' => [
       'auth' => [
           'enabled' => true,
           'weight' => 0,
           'middlewares' => [
               'AuthMiddleware' => ['weight' => 0],
           ],
       ],
   ],
   ```
   Amelyik middleware osztályhoz nincs `weight` megadva, az a `middlewares/`
   mappában lévő fájlnév szerinti (ábécé-) sorrendjét megtartva, a
   súllyal rendelkezők után kerül a listába.

## Nézetek (Twig)

A Twig loader minden engedélyezett modul `resources/templates/` mappáját
felregisztrálja `@modulnev` névtér alatt, az `app/resources/templates/`
mappa pedig az alap (névtér nélküli) sablonútvonal.

```php
return $this->view->render($response, 'main_page.twig', [...]);
```

A sablonnév-feloldást a [`system\View`](system/View.php) végzi:

- **egy szegmenses név** (pl. `'main_page.twig'`) → a hívó action/middleware
  saját moduljának névterében keresi (`@modulnev/main_page.twig`),
- **`'resources/...'`** → az alap (core) `app/resources/templates/` mappában
  keresi,
- **`'modulnev/sablon.twig'`** → egy másik modul névterében keresi
  (`@modulnev/sablon.twig`).

A `{% extends %}` és `{{ block(...) }}` Twig-oldali hivatkozások (pl.
[`app.twig`](app/resources/templates/app.twig)-ban vagy
[`elements.twig`](app/resources/templates/elements.twig)-ben) a natív Twig
loadert használják, nem ezt a PHP-oldali logikát.

Az [`app.twig`](app/resources/templates/app.twig) és
[`elements.twig`](app/resources/templates/elements.twig) egy minimális, saját
CSS/JS-t nem tartalmazó HTML vázat ad — ide illeszthető be tetszőleges saját
frontend (statikus HTML, egy CSS keretrendszer, vagy egy külön build-elt SPA).

Elérhető Twig helper függvény: `is_active_path(route_vagy_utvonal, class = 'active')`
– akkor adja vissza a megadott class-t, ha az aktuális route neve vagy
útvonala illeszkedik.

## Session és CSRF védelem

A session kezelést az `odan/session` csomag adja
([`system/container/sets/session.php`](system/container/sets/session.php)).
A [`SessionMiddleware`](system/middlewares/SessionMiddleware.php) minden
kérésnél elindítja a sessiont, és ha még nincs, generál egy `csrfToken`-t,
amit a Twig `csrfToken` globális változóként is elér (lásd
[`app.twig`](app/resources/templates/app.twig) `<meta name="csrf-token">`
sorát).

A [`CsrfMiddleware`](system/middlewares/CsrfMiddleware.php) minden nem
biztonságos metódusnál (minden a `GET`/`HEAD`/`OPTIONS` listán kívül)
ellenőrzi a tokent az `X-CSRF-Token` fejlécben vagy a `csrfToken` request
paraméterben; sikertelen ellenőrzésnél `403`-as JSON választ ad, és a
próbálkozást naplózza.

## Adatbázis

Az adatbázis-réteg az `illuminate/database` (Eloquent) csomagra épül, a
[`system/container/sets/database.php`](system/container/sets/database.php)
minden `app/settings.php`-ben felsorolt kapcsolatot regisztrál egy
Capsule Manager-en keresztül:

```php
$this->db->testdb->table('valami')->get();
```

Több adatbázis-kapcsolat is felvehető, egyszerűen újabb kulcsokkal a
`settings.php` `database` szekciójában.

## Naplózás

A naplózást a Monolog adja
([`system/container/sets/logger.php`](system/container/sets/logger.php)).
Minden log szinthez (`debug`, `info`, `warning`, `error`, stb.) külön fájl
készül a `log/` mappában (`debug_log`, `info_log`, ...), emellett egy
összesített `all_log` fájl is tartalmazza az összes bejegyzést.

```php
$this->log->info('üzenet', ['kontextus' => 'adat']);
```

## Hibakezelés

A kezeletlen kivételeket a `system\handlers\TracyErrorHandler`
([`system/handlers/TracyErrorHandler.php`](system/handlers/TracyErrorHandler.php))
kapja el, ami a Slim `ErrorMiddleware` alapértelmezett handlerét váltja le
([`system/init.php`](system/init.php)). A [Tracy](https://tracy.nette.org/)
debuggert az `APP_DEBUG` környezeti változó kapcsolja:

- **`APP_DEBUG=1`**: 500-as (nem várt) hibáknál, ha a kliens HTML-t vár
  (böngészős kérés), a válaszban megjelenik a Tracy teljes, részletes
  "blue screen" oldala (stack trace, változók, kód-kontextus). Emellett
  minden ilyen hibáról egy pillanatkép is mentésre kerül a `log/tracy/`
  mappába (`.html` fájlként) — ez akkor is hasznos, ha épp egy AJAX/API
  hívás hasal el, hiszen oda nem lehet beleírni a teljes HTML oldalt: a
  kliens egy sima JSON hibaüzenetet kap, a részletes blue screen viszont
  elmentve várja a `log/tracy/` mappában.
- **`APP_DEBUG=0`**: a kliens sosem lát részletet — HTML-nél és
  JSON/API válasznál is csak egy generikus hibaüzenetet
  (`"Szerver hiba történt."`), miközben a hiba a naplóba
  (`log/error_log`, `log/all_log`) és — 500-as hiba esetén — a Tracy
  pillanatképek közé is bekerül.
- A várt, kliens felé szánt HTTP kivételek (pl. `Slim\Exception\HttpNotFoundException`,
  404-es útvonal) mindig a saját üzenetükkel térnek vissza, `displayErrorDetails`-től
  függetlenül — ezekhez nem készül Tracy pillanatkép, és nem íródik ki blue screen.

## Helperek

A `$this->helper` objektumon keresztül érhetők el
([`system/container/sets/helper.php`](system/container/sets/helper.php)):

- **`$this->helper->array`** – [`ArrayHelper`](system/helpers/ArrayHelper.php):
  tömbműveletek (pl. `stdToArray`, `firstRowToKeys`, `filterRecursive`,
  `multiSearch`, `diff`, `diffKeys`).
- **`$this->helper->request`** – [`RequestHelper`](system/helpers/RequestHelper.php):
  jelenleg a valódi kliens IP meghatározását tudja (`CF-Connecting-IP` →
  `X-Forwarded-For` → `REMOTE_ADDR` sorrendben).

## Validáció

A `respect/validation` csomag van bekötve, saját validációs szabályok a
[`system/validatorcustomrules/`](system/validatorcustomrules) mappába
kerülhetnek (a namespace már regisztrálva van az `init.php`-ban).

## Új modul létrehozása – gyors útmutató

1. Hozz létre egy mappát: `app/modules/<nev>/`.
2. Regisztráld az `app/settings.php` `modules` szekciójában:
   ```php
   '<nev>' => ['enabled' => true, 'weight' => 1],
   ```
3. Hozd létre a szükséges almappákat igény szerint: `actions/`,
   `repositories/`, `factories/`, `middlewares/`, `resources/templates/`.
4. Írj egy `routes.php`-t a modul gyökerébe, és regisztráld a route-jaidat
   a fentebb bemutatott rövidített callable formátummal.
5. Az action osztályaid `Action`-ből, a middleware-jeid `Middleware`-ből, a
   repository-id `Repository`-ből, a factory-id `Factory`-ból örököljenek
   (ezek a globális `system\` névtér osztályai, a modul-betöltő class
   alias-okon keresztül teszi elérhetővé őket a modul saját névterében).

## Licenc

MIT – lásd [LICENSE](LICENSE).
