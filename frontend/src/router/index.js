import { createRouter, createWebHistory } from 'vue-router'

function getStoredUser() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')
  } catch {
    return null
  }
}

function resolveDashboardRoute(user) {
  if (user?.role === 'student') {
    return { name: 'student-dashboard' }
  }

  if (user?.role === 'teacher') {
    return { name: 'teacher-dashboard' }
  }

  if (user?.role === 'admin') {
    return { name: 'admin-dashboard' }
  }

  return { name: 'login' }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      redirect: () => resolveDashboardRoute(getStoredUser()),
    },
    {
      path: '/teacher',
      component: () => import('../layouts/TeacherLayout.vue'),
      meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
      children: [
        {
          path: '',
          name: 'teacher-dashboard',
          component: () => import('../views/TeacherDashboard.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'courses',
          name: 'teacher-courses',
          component: () => import('../views/TeacherCoursesView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'courses/:id',
          name: 'teacher-course-detail',
          component: () => import('../views/TeacherCourseDetailView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'courses/:id/students/:userId/history',
          name: 'teacher-student-history',
          component: () => import('../views/TeacherStudentHistoryView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'rewards',
          name: 'teacher-rewards',
          component: () => import('../views/TeacherSectionView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'badges',
          name: 'teacher-badges',
          component: () => import('../views/TeacherSectionView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'redemptions',
          name: 'teacher-redemptions',
          component: () => import('../views/TeacherRedemptionsView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
        {
          path: 'reports',
          name: 'teacher-reports',
          component: () => import('../views/TeacherReportsView.vue'),
          meta: { requiresAuth: true, roles: ['teacher', 'admin'] },
        },
      ],
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: () => import('../views/AdminDashboard.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/student',
      name: 'student-dashboard',
      component: () => import('../views/StudentDashboard.vue'),
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/courses/:courseId/activities',
      name: 'course-activities',
      component: () => import('../views/CourseActivitiesView.vue'),
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/courses/:courseId/leaderboard',
      name: 'course-leaderboard',
      component: () => import('../views/LeaderboardView.vue'),
      meta: { requiresAuth: true, roles: ['student', 'teacher', 'admin'] },
    },
    {
      path: '/courses/:courseId/rewards',
      name: 'course-rewards',
      component: () => import('../views/RewardsStoreView.vue'),
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/student/history',
      name: 'student-history',
      component: () => import('../views/StudentHistoryView.vue'),
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/student/profile',
      name: 'student-profile',
      component: () => import('../views/StudentProfileView.vue'),
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/activities/:id',
      name: 'activity-detail',
      component: () => import('../views/ActivityDetailView.vue'),
      meta: { requiresAuth: true, roles: ['student'] },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem('token')
  const user = getStoredUser()

  if (to.meta.requiresAuth && !token) {
    return { name: 'login' }
  }

  if (to.meta.guestOnly && token) {
    return resolveDashboardRoute(user)
  }

  if (to.meta.roles && user && !to.meta.roles.includes(user.role)) {
    return resolveDashboardRoute(user)
  }

  return true
})

export default router
