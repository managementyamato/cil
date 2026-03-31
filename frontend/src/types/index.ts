export interface User {
  email: string
  name: string
  role: 'admin' | 'product' | 'sales'
}

export interface SessionInfo {
  authenticated: true
  user: User
}

export interface UnauthenticatedSession {
  authenticated: false
}

export type SessionResponse = SessionInfo | UnauthenticatedSession

export interface ApiResponse<T> {
  success: boolean
  data?: T
  error?: string
  message?: string
}

export interface DashboardTroubles {
  total: number
  pending: number
  inProgress: number
  onHold: number
  completed: number
  completionRate: number
  currentMonthCount: number
  monthChange: number
  overdueCount: number
}

export interface DashboardActivity {
  type: string
  action: string
  title: string
  date: string
  user: string
}

export interface DashboardPermissions {
  canViewFinance: boolean
  canViewTroubles: boolean
  canViewMaster: boolean
  canViewPhotoAttendance: boolean
  canViewTroubleForm: boolean
}

export interface DashboardData {
  troubles: DashboardTroubles
  projects: { byStatus: Record<string, number> }
  sales: { currentMonth: number; currentMonthWan: string; change: number } | null
  recentActivities: DashboardActivity[]
  permissions: DashboardPermissions
  calendarConfigured: boolean
  alcoholCheck: { configured: boolean; missingCount: number }
}

export interface CalendarEvent {
  title: string
  start?: string
  end?: string
  isAllDay?: boolean
  calendarColor?: string
}
