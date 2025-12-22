import { rest } from 'msw'
import { API_URL } from './constants'
import { tasks } from '../fixtures/tasks.json'

export const tasksHandlers = [
  rest.get(`${API_URL}/tasks`, (req, res, ctx) => {
    const tenantId = req.url.searchParams.get('tenantId')
    const status = req.url.searchParams.get('status')
    const priority = req.url.searchParams.get('priority')
    const assigneeId = req.url.searchParams.get('assigneeId')
    const projectId = req.url.searchParams.get('projectId')
    const dueFrom = req.url.searchParams.get('dueFrom')
    const dueTo = req.url.searchParams.get('dueTo')
    const q = req.url.searchParams.get('q')

    let filteredTasks = tasks.filter(task => {
      if (tenantId && task.tenantId !== tenantId) return false
      if (status && task.status !== status) return false
      if (priority && task.priority !== priority) return false
      if (assigneeId && !task.assignees.includes(assigneeId)) return false
      if (projectId && task.projectId !== projectId) return false
      // TODO: Add date range filtering
      if (q && !task.title.toLowerCase().includes(q.toLowerCase())) return false
      return true
    })

    const page = Number(req.url.searchParams.get('page')) || 1
    const perPage = Number(req.url.searchParams.get('perPage')) || 10
    const start = (page - 1) * perPage
    const end = start + perPage
    const paginatedTasks = filteredTasks.slice(start, end)

    return res(
      ctx.status(200),
      ctx.json({
        data: paginatedTasks,
        total: filteredTasks.length,
        page,
        perPage
      })
    )
  })
]
