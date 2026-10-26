import http from 'k6/http'
import { check, sleep } from 'k6'

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000'
const STUDENT_TOKEN = __ENV.STUDENT_TOKEN || ''
const TEACHER_TOKEN = __ENV.TEACHER_TOKEN || ''
const COURSE_ID = __ENV.COURSE_ID || '1'

export const options = {
  vus: Number(__ENV.VUS || 20),
  duration: __ENV.DURATION || '60s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: [`p(95)<${__ENV.P95_MS || 500}`],
  },
}

function headers(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
  }
}

export default function () {
  const isTeacher = __VU % 4 === 1 && TEACHER_TOKEN
  const token = isTeacher ? TEACHER_TOKEN : STUDENT_TOKEN

  const courses = http.get(`${BASE}/api/courses`, { headers: headers(token) })
  check(courses, { 'courses 2xx': (r) => r.status >= 200 && r.status < 400 })

  const board = http.get(`${BASE}/api/courses/${COURSE_ID}/leaderboard`, { headers: headers(token) })
  check(board, { 'leaderboard 2xx': (r) => r.status >= 200 && r.status < 400 })

  if (isTeacher) {
    const analytics = http.get(`${BASE}/api/courses/${COURSE_ID}/analytics`, { headers: headers(token) })
    check(analytics, { 'analytics 2xx': (r) => r.status >= 200 && r.status < 400 })
  } else {
    const progress = http.get(`${BASE}/api/me/progress?course_id=${COURSE_ID}`, { headers: headers(token) })
    check(progress, { 'progress 2xx': (r) => r.status >= 200 && r.status < 400 })
  }

  sleep(0.2)
}
