import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'dashboard.home',
        component: () => import('../views/DashboardView.vue'),
      },
    ],
  },
  {
    path: '/settings/menus',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'settings.menus',
        component: () => import('../views/MenuManagementView.vue'),
      },
    ],
  },
  {
    path: '/sales/orders',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'sales.orders',
        component: () => import('../views/SalesOrderView.vue'),
        meta: { permission: 'sales.view' },
      },
    ],
  },
  {
    path: '/settings/users',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'settings.users',
        component: () => import('../views/UserManagementView.vue'),
        meta: { permission: 'users.view' },
      },
    ],
  },
  {
    path: '/settings/roles',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'settings.roles',
        component: () => import('../views/RoleManagementView.vue'),
        meta: { permission: 'roles.view' },
      },
    ],
  },
  {
    path: '/settings/permissions',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        name: 'settings.permissions',
        component: () => import('../views/PermissionManagementView.vue'),
        meta: { permission: 'permissions.view' },
      },
    ],
  },
  { path: '/', redirect: '/dashboard' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  useUiStore().isRouteLoading = true

  const auth = useAuthStore()

  if (auth.initializing) {
    await auth.tryRestoreSession()
  }

  if (!to.meta.public && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard.home' }
  }

  if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
    return { name: 'dashboard.home' }
  }

  return true
})

router.afterEach(() => {
  useUiStore().isRouteLoading = false
})

router.onError(() => {
  useUiStore().isRouteLoading = false
})

export default router
