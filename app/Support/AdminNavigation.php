<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Catálogo de pantallas del admin: lo mismo que ofrece el menú lateral, pero
 * en datos, para que el buscador global pueda llevarte a cualquiera de ellas.
 *
 * Los permisos NO se declaran aquí: se leen del middleware `role:` de la propia
 * ruta. Así el catálogo no puede desincronizarse de lo que la ruta permite —
 * declarar los roles a mano era la vía segura a ofrecer un destino que después
 * responde 403.
 */
class AdminNavigation
{
    /**
     * Pantallas navegables. `alias` son las palabras con las que la gente las
     * busca aunque no aparezcan en la etiqueta.
     *
     * @return array<int, array{group: string, label: string, route: string, alias: string}>
     */
    public static function screens(): array
    {
        return [
            ['group' => 'Dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'alias' => 'inicio principal panel home'],

            ['group' => 'Órdenes', 'label' => 'Purchase Orders', 'route' => 'admin.purchase-orders.index', 'alias' => 'po ordenes de compra pedidos'],
            ['group' => 'Órdenes', 'label' => 'Manage PO', 'route' => 'admin.work-orders.index', 'alias' => 'wo work order ordenes de trabajo'],
            // «Estados de WO» ya no es una pantalla propia: se administra desde
            // la ficha de la orden con el modal StatusWOManager, así que no hay
            // ruta que ofrecer en la búsqueda global.
            ['group' => 'Órdenes', 'label' => 'Capacidad', 'route' => 'admin.capacity.wizard', 'alias' => 'planeacion horas asistente'],
            ['group' => 'Órdenes', 'label' => 'Invoices', 'route' => 'admin.invoices.index', 'alias' => 'facturas facturacion'],

            ['group' => 'Catálogos', 'label' => 'Partes', 'route' => 'admin.parts.index', 'alias' => 'numero de parte piezas'],
            ['group' => 'Catálogos', 'label' => 'Precios', 'route' => 'admin.prices.index', 'alias' => 'costos tarifas'],
            ['group' => 'Catálogos', 'label' => 'Estándares', 'route' => 'admin.standards.index', 'alias' => 'standard produccion por hora'],

            ['group' => 'Producción', 'label' => 'Panel de Producción', 'route' => 'admin.production.index', 'alias' => 'dashboard tablero'],
            ['group' => 'Producción', 'label' => 'Pesadas de Producción', 'route' => 'admin.production.weighings', 'alias' => 'pesar bascula piezas'],
            ['group' => 'Producción', 'label' => 'Lotes', 'route' => 'admin.lots.index', 'alias' => 'viajeros'],
            ['group' => 'Producción', 'label' => 'Lista de Envío', 'route' => 'admin.sent-lists.display', 'alias' => 'tablero sent list flujo'],
            ['group' => 'Producción', 'label' => 'Listas Preliminares', 'route' => 'admin.sent-lists.index', 'alias' => 'sent list borrador'],
            ['group' => 'Producción', 'label' => 'Monitor TV', 'route' => 'admin.sent-lists.tv', 'alias' => 'pantalla piso television'],

            ['group' => 'Materiales', 'label' => 'Panel de Materiales', 'route' => 'admin.materials.index', 'alias' => 'dashboard'],
            ['group' => 'Materiales', 'label' => 'Gestión de materiales', 'route' => 'admin.materials.manage', 'alias' => 'liberar material viajeros crimp'],

            ['group' => 'Empaques', 'label' => 'Panel de Empaque', 'route' => 'admin.packaging.index', 'alias' => 'dashboard'],
            ['group' => 'Empaques', 'label' => 'Gestión de empaque', 'route' => 'admin.packaging.manage', 'alias' => 'empacar sobrante registros'],
            ['group' => 'Empaques', 'label' => 'Pesadas de Empaque', 'route' => 'admin.packaging.weighings', 'alias' => 'crimp manguitas'],
            ['group' => 'Empaques', 'label' => 'Shipping List', 'route' => 'admin.shipping-list.index', 'alias' => 'despacho embarque packing slip'],

            ['group' => 'Calidad', 'label' => 'Panel de Calidad', 'route' => 'admin.quality.index', 'alias' => 'dashboard'],
            ['group' => 'Calidad', 'label' => 'Inspección', 'route' => 'admin.quality.inspection', 'alias' => 'aprobar rechazar viajeros'],
            ['group' => 'Calidad', 'label' => 'Pesadas de Calidad', 'route' => 'admin.quality.weighings', 'alias' => 'verificar aprobadas rechazadas'],

            ['group' => 'Administración', 'label' => 'Usuarios', 'route' => 'admin.users.index', 'alias' => 'cuentas acceso'],
            ['group' => 'Administración', 'label' => 'Empleados', 'route' => 'admin.employees.index', 'alias' => 'personal planta'],
            ['group' => 'Administración', 'label' => 'Departamentos', 'route' => 'admin.departments.index', 'alias' => 'areas organigrama'],
            ['group' => 'Administración', 'label' => 'Áreas', 'route' => 'admin.areas.index', 'alias' => 'zonas'],
            ['group' => 'Administración', 'label' => 'Mesas', 'route' => 'admin.tables.index', 'alias' => 'estaciones equipo'],
            ['group' => 'Administración', 'label' => 'Semi-Automáticos', 'route' => 'admin.semi-automatics.index', 'alias' => 'equipos maquinaria'],
            ['group' => 'Administración', 'label' => 'Máquinas', 'route' => 'admin.machines.index', 'alias' => 'equipos'],
            ['group' => 'Administración', 'label' => 'Días Festivos', 'route' => 'admin.holidays.index', 'alias' => 'feriados calendario'],
            ['group' => 'Administración', 'label' => 'Turnos', 'route' => 'admin.shifts.index', 'alias' => 'horarios jornadas'],
            ['group' => 'Administración', 'label' => 'Descansos', 'route' => 'admin.break-times.index', 'alias' => 'break comida'],
            ['group' => 'Administración', 'label' => 'Tiempo Extra', 'route' => 'admin.over-times.index', 'alias' => 'overtime horas extra'],
            ['group' => 'Administración', 'label' => 'Roles', 'route' => 'admin.roles.index', 'alias' => 'permisos accesos'],
            ['group' => 'Administración', 'label' => 'Permisos', 'route' => 'admin.permissions.index', 'alias' => 'roles accesos'],

            ['group' => 'Reportes', 'label' => 'Generar Reportes', 'route' => 'admin.reports.index', 'alias' => 'exportar excel informes'],
            ['group' => 'Reportes', 'label' => 'Historial', 'route' => 'admin.history.index', 'alias' => 'auditoria bitacora rastro quien cambio movimientos iso'],

            ['group' => 'Mi cuenta', 'label' => 'Perfil', 'route' => 'admin.settings.profile', 'alias' => 'ajustes cuenta nombre correo'],
            ['group' => 'Mi cuenta', 'label' => 'Contraseña', 'route' => 'admin.settings.password', 'alias' => 'ajustes password clave'],
            ['group' => 'Mi cuenta', 'label' => 'Apariencia', 'route' => 'admin.settings.appearance', 'alias' => 'ajustes tema oscuro claro'],

            ['group' => 'Ayuda', 'label' => 'Tutorial / Guía', 'route' => 'admin.tutorial', 'alias' => 'ayuda como funciona flujo'],
        ];
    }

    /**
     * Pantallas a las que este usuario sí puede entrar.
     *
     * @return array<int, array{group: string, label: string, route: string, alias: string, url: string}>
     */
    public static function for(?User $user): array
    {
        $visibles = [];

        foreach (self::screens() as $pantalla) {
            if (! self::canAccess($pantalla['route'], $user)) {
                continue;
            }

            $pantalla['url'] = route($pantalla['route']);
            $visibles[] = $pantalla;
        }

        return $visibles;
    }

    /**
     * Pantallas que casan con el texto buscado, sin distinguir acentos ni
     * mayúsculas ("produccion" encuentra "Producción").
     *
     * @return array<int, array{group: string, label: string, route: string, alias: string, url: string}>
     */
    public static function search(?User $user, string $term, int $limit = 8): array
    {
        $term = self::normalize($term);

        if ($term === '') {
            return [];
        }

        $resultados = [];

        foreach (self::for($user) as $pantalla) {
            $heno = self::normalize($pantalla['label'].' '.$pantalla['group'].' '.$pantalla['alias']);

            if (str_contains($heno, $term)) {
                // Lo que empieza igual que lo tecleado sube: "pes" debe traer
                // "Pesadas" antes que una pantalla que sólo la menciona.
                $pantalla['score'] = str_starts_with(self::normalize($pantalla['label']), $term) ? 0 : 1;
                $resultados[] = $pantalla;
            }
        }

        usort($resultados, fn ($a, $b) => [$a['score'], $a['label']] <=> [$b['score'], $b['label']]);

        return array_slice($resultados, 0, $limit);
    }

    /**
     * ¿El usuario puede entrar a esta ruta con nombre?
     *
     * Lee el middleware `role:` real de la ruta, así que responde lo mismo que
     * respondería el navegador.
     */
    public static function canAccess(?string $routeName, ?User $user): bool
    {
        if (! $user || ! $routeName) {
            return false;
        }

        $route = Route::getRoutes()->getByName($routeName);

        if (! $route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                return $user->hasAnyRole(explode('|', substr($middleware, 5)));
            }
        }

        return true; // sólo pide sesión iniciada
    }

    /**
     * Migas de la pantalla actual, deducidas de la ruta.
     *
     * Se derivan del catálogo en vez de declararse pantalla por pantalla: una
     * ruta `admin.users.create` cae en la entrada `admin.users.index` y añade
     * el paso «Nuevo». Así las 89 pantallas tienen migas sin tocar ninguna.
     *
     * @return array<int, array{label: string, url: ?string}>
     */
    public static function breadcrumb(?User $user, ?string $routeName = null): array
    {
        $routeName ??= request()->route()?->getName();

        if (! $routeName || ! str_starts_with($routeName, 'admin.')) {
            return [];
        }

        $migas = [];

        if (self::canAccess('admin.dashboard', $user)) {
            $migas[] = ['label' => 'Inicio', 'url' => route('admin.dashboard')];
        }

        if ($routeName === 'admin.dashboard') {
            return $migas;
        }

        [$pantalla, $accion] = self::locate($routeName);

        if (! $pantalla) {
            return $migas;
        }

        // El grupo del menú, enlazado al panel de su área cuando existe.
        if ($pantalla['group'] !== 'Dashboard') {
            $rutaGrupo = self::eyebrowRoute($pantalla['group']);
            $migas[] = [
                'label' => $pantalla['group'],
                'url' => self::canAccess($rutaGrupo, $user) ? route($rutaGrupo) : null,
            ];
        }

        // La pantalla: enlace sólo si estamos en una subpantalla suya.
        $migas[] = [
            'label' => $pantalla['label'],
            'url' => $accion && self::canAccess($pantalla['route'], $user) ? route($pantalla['route']) : null,
        ];

        if ($accion) {
            $migas[] = ['label' => $accion, 'url' => null];
        }

        // Si el grupo repite lo que dice la pantalla ("Dashboard › Dashboard"),
        // sobra: se queda uno solo.
        return array_values(array_reduce($migas, function ($acc, $miga) {
            if ($acc !== [] && end($acc)['label'] === $miga['label']) {
                return $acc;
            }
            $acc[] = $miga;

            return $acc;
        }, []));
    }

    /**
     * Localiza la pantalla del catálogo a la que pertenece una ruta y, si es
     * una subpantalla, cómo se llama ese paso.
     *
     * @return array{0: ?array{group: string, label: string, route: string, alias: string}, 1: ?string}
     */
    private static function locate(string $routeName): array
    {
        $pantallas = self::screens();

        foreach ($pantallas as $pantalla) {
            if ($pantalla['route'] === $routeName) {
                return [$pantalla, null];
            }
        }

        // `admin.users.create` → familia `admin.users.`
        $familia = Str::beforeLast($routeName, '.').'.';
        $sufijo = Str::afterLast($routeName, '.');

        foreach ($pantallas as $pantalla) {
            if (str_starts_with($pantalla['route'], $familia)) {
                return [$pantalla, self::actionLabel($sufijo)];
            }
        }

        return [null, null];
    }

    private static function actionLabel(string $sufijo): ?string
    {
        return match ($sufijo) {
            'create' => 'Nuevo',
            'edit' => 'Editar',
            'show' => 'Detalle',
            default => null,
        };
    }

    /**
     * A dónde lleva el rótulo de área de una pantalla (el «eyebrow»).
     *
     * Es el primer nivel de navegación que ya existía como texto muerto encima
     * del título; devolviendo una ruta se vuelve el camino de vuelta al área.
     */
    public static function eyebrowRoute(?string $eyebrow): ?string
    {
        if (! $eyebrow) {
            return null;
        }

        // "Área · Calidad" vale lo mismo que "Calidad": el área es la última
        // parte del rótulo. Se parte antes de normalizar porque el separador
        // «·» no sobrevive al paso a ASCII.
        $partes = preg_split('/[·|\/]+/u', $eyebrow) ?: [$eyebrow];
        $clave = self::normalize((string) end($partes));

        return match ($clave) {
            'administracion' => 'admin.dashboard',
            'compras' => 'admin.purchase-orders.index',
            'catalogo', 'catalogos' => 'admin.parts.index',
            'produccion', 'control de produccion' => 'admin.production.index',
            'materiales' => 'admin.materials.index',
            'empaque', 'empaques' => 'admin.packaging.index',
            'calidad' => 'admin.quality.index',
            'reportes' => 'admin.reports.index',
            'mi cuenta' => 'admin.settings.profile',
            'guia' => 'admin.tutorial',
            default => null,
        };
    }

    /** Minúsculas y sin acentos, para comparar como la gente teclea. */
    public static function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->squish()->value();
    }
}
