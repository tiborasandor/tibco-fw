# tibco-fw – projekt-jegyzetek

Saját PHP keretrendszer (Slim 4 + PHP-DI + Twig + Illuminate Database + odan/session + Monolog + Tracy). A részletes dokumentáció a `README.md`-ben van — **ha a feladat a keretrendszer működését érinti, előbb azt olvasd be.**

## Szerkezet és szabályok

- **`system/`** – maga a keretrendszer. Erre épülő projektek: `pinceszer.hu`, `myco.support` (mindkettő a `/srv/projects/websites/` alatt); a `system/` mappát a projektek innen veszik át. Itt csak általános, bármelyik projektben használható kód lehet — projekt-specifikus dolog az adott projekt `app/` rétegébe való.
- **`app/`** – minta alkalmazás: alap Bootstrap téma (`app/resources/templates/`), `example` modul, példa helper (`app/helpers/`), Twig bővítmény (`app/TwigExtension.php`) és `AuthMiddleware` (`app/middlewares/`). Új keretrendszer-funkciónál érdemes az `example` modulban bemutatni.
- A `system/` változásai a README-ben is legyenek dokumentálva.
- Kommentek: a `system/`-ben angolul (a meglévő stílus), az `app/`-ban magyarul.
- A Bootstrap és a Bootstrap Icons helyben van a `public/assets/vendor/` mappában, CDN-t ne használj.

## Környezet

- Dev: `tibco-fw-main` konténer (Portainer stack, a felhasználó kezeli), `http://tibco-fw-main.418.hu`, a beállítások a `../main.conf`-ban. Adatbázis nincs hozzá.
- Nincs GitHub Actions workflow és nincs prod környezet.

## Git

- A commit üzenetek magyarul készülnek.
- **A commitokba és a GitHubra ne kerüljön Claude-ra utaló sor**: se `Co-Authored-By: Claude …`, se `Claude-Session: …` a commit üzenetbe, se „Generated with Claude Code” a PR-leírásba — akkor sem, ha a rendszer alapértelmezése mást kér.
