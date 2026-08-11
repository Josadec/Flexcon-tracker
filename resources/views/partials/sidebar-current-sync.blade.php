<script>
    (() => {
        const trimPath = (path) => {
            const normalized = path.replace(/\/+$/, '');

            return normalized === '' ? '/' : normalized;
        };

        const pathFromHref = (href) => {
            try {
                return trimPath(new URL(href, window.location.origin).pathname);
            } catch {
                return null;
            }
        };

        const exactPaths = new Set([
            '/admin',
            '/admin/production',
            '/admin/production/weighings',
            '/admin/materials',
            '/admin/materials/manage',
            '/admin/packaging',
            '/admin/packaging/manage',
            '/admin/quality',
            '/admin/quality/inspection',
            '/admin/quality/weighings',
            '/admin/sent-lists/display',
            '/admin/sent-lists/tv',
            '/dashboard',
            '/employee/dashboard',
            '/employee/profile',
        ]);

        const prefixedPaths = new Set([
            '/admin/purchase-orders',
            '/admin/work-orders',
            '/admin/shipping-list',
            '/admin/capacity-wizard',
            '/admin/capacity-calculator',
            '/admin/invoices',
            '/admin/parts',
            '/admin/prices',
            '/admin/standards',
            '/admin/kits',
            '/admin/lots',
            '/admin/holidays',
            '/admin/shifts',
            '/admin/break-times',
            '/admin/over-times',
            '/admin/packing-slips',
            '/admin/users',
            '/admin/employees',
            '/admin/departments',
            '/admin/areas',
            '/admin/tables',
            '/admin/semi-automatics',
            '/admin/machines',
            '/admin/roles',
            '/admin/permissions',
            '/admin/reports',
            '/admin/tutorial',
        ]);

        const isPreliminarySentListPath = (currentPath) => {
            return currentPath === '/admin/sent-lists'
                || /^\/admin\/sent-lists\/(?!display(?:\/|$)|tv(?:\/|$))[^/]+(?:\/edit)?$/.test(currentPath);
        };

        const isCurrent = (hrefPath, currentPath) => {
            if (!hrefPath) {
                return false;
            }

            if (hrefPath === '/admin/sent-lists') {
                return isPreliminarySentListPath(currentPath);
            }

            if (exactPaths.has(hrefPath)) {
                return currentPath === hrefPath;
            }

            if (prefixedPaths.has(hrefPath)) {
                return currentPath === hrefPath || currentPath.startsWith(`${hrefPath}/`);
            }

            return currentPath === hrefPath;
        };

        const syncSidebarCurrent = () => {
            const currentPath = trimPath(window.location.pathname);

            document.querySelectorAll('[data-flux-sidebar] [data-flux-navlist-item][href]')
                .forEach((item) => {
                    item.removeAttribute('data-current');

                    if (isCurrent(pathFromHref(item.getAttribute('href')), currentPath)) {
                        item.setAttribute('data-current', '');
                    }
                });
        };

        const scheduleSync = () => window.setTimeout(syncSidebarCurrent, 0);

        document.addEventListener('DOMContentLoaded', scheduleSync);
        document.addEventListener('livewire:navigated', scheduleSync);
        document.addEventListener('alpine:navigated', scheduleSync);
    })();
</script>
