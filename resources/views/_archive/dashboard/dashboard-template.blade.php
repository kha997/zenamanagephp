@extends('layouts.app-layout')

@section('title', 'Dashboard - ZenaManage')

@section('content')
<div x-data="zenaDashboard()" x-init="init()" class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-600 mt-1">Welcome back! Here's what's happening with your projects.</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                        <i class="fas fa-sync-alt mr-2" :class="refreshing ? 'animate-spin' : ''"></i>
                        <span x-show="!refreshing">Refresh</span>
                        <span x-show="refreshing">Refreshing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Active Projects -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Projects</p>
                        <p class="text-2xl font-bold text-gray-900 kpi--projects-active">—</p>
                        <p class="text-xs text-green-600" x-text="kpiTrends.projects_active || '+0%'"></p>
                    </div>
                </div>
            </div>
            
            <!-- Tasks Due Today -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Tasks Due Today</p>
                        <p class="text-2xl font-bold text-gray-900 kpi--tasks-today">—</p>
                        <p class="text-xs text-blue-600" x-text="kpiTrends.tasks_due_today || '+0%'"></p>
                    </div>
                </div>
            </div>
            
            <!-- Overdue Tasks -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Overdue Tasks</p>
                        <p class="text-2xl font-bold text-gray-900 kpi--tasks-overdue">—</p>
                        <p class="text-xs text-red-600" x-text="kpiTrends.tasks_overdue || '+0%'"></p>
                    </div>
                </div>
            </div>
            
            <!-- Focus Minutes -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Focus Minutes Today</p>
                        <p class="text-2xl font-bold text-gray-900 kpi--focus-minutes">—</p>
                        <p class="text-xs text-purple-600" x-text="kpiTrends.focus_minutes_today || '+0%'"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Data Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Revenue Chart -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Goal</h3>
                <div id="revenue-chart" class="h-64"></div>
            </div>
            
            <!-- Meeting Schedules -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Meeting Schedules</h3>
                <ul data-zena-meetings class="space-y-3">
                    <!-- Meeting items will be populated by JavaScript -->
                </ul>
            </div>
            
            <!-- Notifications -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                    <div id="notification-dropdown" class="relative">
                        <button class="relative p-2 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="badge badge-primary absolute -top-1 -right-1 text-xs">0 New</span>
                        </button>
                    </div>
                </div>
                <div id="tabs-basic-1" class="space-y-3">
                    <ul class="space-y-2">
                        <!-- Notification items will be populated by JavaScript -->
                    </ul>
                </div>
            </div>
        </div>

        <!-- Team Table -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Team Members</h3>
                <div class="flex items-center space-x-2">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-user-plus mr-2"></i>Add Member
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table id="users-dashboard" class="datatables-users w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-3 px-4">
                                <input type="checkbox" class="checkbox checkbox-sm" id="select-all-users">
                                <label for="select-all-users" class="sr-only">Select All</label>
                            </th>
                            <th class="text-left py-3 px-4 font-medium text-gray-900">User</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-900">Role</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-900">Plan</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-900">Status</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-900">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Team member rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Smart Search Modal -->
    <div id="search-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-hidden">
                <div class="p-6 border-b">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Smart Search</h3>
                        <button onclick="closeSearchModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div class="mt-4">
                        <input type="text" 
                               id="kbdInput" 
                               placeholder="Search projects, tasks, users..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                <div class="p-6">
                    <div id="search-tabs-1" class="space-y-2">
                        <ul class="space-y-1">
                            <!-- Search results will be populated by JavaScript -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include required JavaScript libraries -->
<script src="./node_modules/jquery/dist/jquery.min.js"></script>
<script src="./node_modules/datatables.net/js/dataTables.min.js"></script>
<script src="./node_modules/apexcharts/dist/apexcharts.min.js"></script>
<script src="./node_modules/lodash/lodash.min.js"></script>
<script src="./node_modules/flyonui/dist/helper-apexcharts.js"></script>

<script>
/**
 * ZenaManage Dashboard Template
 * Scope: Tenant (App). Uses SimpleSessionAuth (cookie). No tokens in JS.
 * Every request sends credentials & tenant header.
 */
const ZENA_API = {
  // KPI & Dashboard
  KPIS:           '/api/v1/app/dashboard/kpis',                 // GET
  MEETINGS:       '/api/v1/app/calendar/events?range=today..+7',// GET
  NOTIFICATIONS:  '/api/v1/app/notifications?unread=true',      // GET
  TEAM:           '/api/v1/app/team/users?limit=100',           // GET (for the table demo)
  SEARCH:         '/api/v1/app/search?q=',                      // GET
  HEALTH:         '/api/v1/public/health',                      // GET (optional heartbeat)
};

const TENANT_HEADER = () => {
  // if you keep tenant in cookie/session you can omit this; otherwise set from a meta tag
  // <meta name="x-tenant-id" content="...">
  const m = document.querySelector('meta[name="x-tenant-id"]');
  return m ? { 'X-Tenant-Id': m.content } : {};
};

async function apiGet(url) {
  const res = await fetch(url, {
    method: 'GET',
    credentials: 'include',
    headers: {
      'Accept': 'application/json',
      ...TENANT_HEADER()
    }
  });
  if (!res.ok) {
    // Standardized error envelope expected: { error: { id, code, message, details } }
    let msg = 'Request failed';
    try {
      const payload = await res.json();
      msg = payload?.error?.message || payload?.message || msg;
    } catch (_) {}
    throw new Error(msg + ` (HTTP ${res.status})`);
  }
  return res.json();
}

// ---------- UI helpers (replace text in your existing KPI areas) ----------
function setText(sel, value) {
  const el = document.querySelector(sel);
  if (el) el.textContent = value;
}

// Example renderers for your existing blocks
function renderKPIs(data) {
  // Expected shape (example):
  // {
  //   projects_active: 12,
  //   tasks_due_today: 7,
  //   tasks_overdue: 3,
  //   focus_minutes_today: 142,
  //   trend: { projects_active: +8, tasks_overdue: -12, ... },
  //   revenue: { discount: 14987, profit: 1735, sale: 11548 }
  // }

  // Map KPIs into your four cards (rename selectors to the exact KPI elements you want)
  setText('.kpi--projects-active', data.projects_active ?? '—');
  setText('.kpi--tasks-today', data.tasks_due_today ?? '—');
  setText('.kpi--tasks-overdue', data.tasks_overdue ?? '—');
  setText('.kpi--focus-minutes', (data.focus_minutes_today ?? 0) + ' min');

  // Chart: reuse Apex donut "Revenue Goal" with real values if you track them
  if (data.revenue) {
    buildChart('#revenue-chart', () => ({
      chart: { height: 182, width: 182, type: 'donut', offsetX: 10, parentHeightOffset: 0 },
      labels: ['Discount', 'Profit', 'Sale'],
      series: [data.revenue.discount || 0, data.revenue.profit || 0, data.revenue.sale || 0],
      colors: ['var(--color-primary)', 'var(--color-success)', 'var(--color-warning)'],
      stroke: { width: 4, colors: ['var(--color-base-200)'] },
      dataLabels: { enabled: false }, legend: { show: false }, grid: { show: false },
      states: { hover: { filter: { type: 'none' } }, active: { filter: { type: 'none' } } },
      plotOptions: {
        pie: {
          expandOnClick: false,
          donut: {
            size: '83%', background: 'transparent',
            labels: {
              show: true,
              value: {
                fontSize: '1.5rem', fontFamily: 'Inter, ui-sans-serif', fontWeight: 700,
                color: 'var(--color-base-content)', offsetY: -17,
                formatter: val => '$' + parseInt(val)
              },
              name: { offsetY: 17, fontFamily: 'Inter, ui-sans-serif' },
              total: {
                show: true, fontSize: '14px', color: 'var(--color-base-content)', fontWeight: 500,
                label: 'Total Profit',
                formatter: () => '$' + (data.revenue.profit || 0)
              }
            }
          }
        }
      }
    }))
  }
}

function renderMeetings(list) {
  // Replace static "Meeting Schedules" list with API data
  // Expected item: { id, title, starts_at, ends_at, label, avatar_url }
  const ul = document.querySelector('[data-zena-meetings]');
  if (!ul) return;
  ul.innerHTML = (list || []).slice(0, 4).map(ev => `
    <li>
      <div class="flex items-center gap-3">
        <div class="avatar">
          <div class="rounded-field size-10">
            <img src="${ev.avatar_url || 'https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png'}" alt="avatar"/>
          </div>
        </div>
        <div class="grow">
          <h6 class="text-base-content mb-px font-medium">${_.escape(ev.title || 'Meeting')}</h6>
          <div class="text-base-content/50 flex items-center gap-1 text-sm">
            <span class="icon-[tabler--calendar] size-4.5"></span>
            <span>${ev.starts_at} - ${ev.ends_at}</span>
          </div>
        </div>
        <span class="badge badge-soft">${_.escape(ev.label || 'Event')}</span>
      </div>
    </li>
  `).join('');
}

function renderNotifications(list) {
  // Put unread count into bell
  const badge = document.querySelector('#notification-dropdown .badge-primary');
  if (badge) badge.textContent = `${(list || []).length} New`;

  // Render first tab list (you can expand by type)
  const inbox = document.querySelector('#tabs-basic-1 ul');
  if (!inbox) return;
  inbox.innerHTML = (list || []).slice(0, 5).map(n => `
    <li>
      <div class="flex w-full items-center gap-3 py-3">
        <div class="avatar"><div class="size-10 rounded-full">
          <img src="${n.actor_avatar || 'https://cdn.flyonui.com/fy-assets/avatar/avatar-2.png'}" alt="avatar" />
        </div></div>
        <div class="flex-1">
          <h6 class="text-base-content mb-0.5 font-medium">${_.escape(n.title || 'Notification')}</h6>
          <div class="flex items-center gap-x-2.5">
            <p class="text-base-content/50 text-sm">${_.escape(n.time_ago || '')}</p>
            <span class="bg-neutral/20 size-1.5 rounded-full"></span>
            <p class="text-base-content/50 text-sm">${_.escape(n.category || '')}</p>
          </div>
        </div>
        <div class="flex flex-col items-center gap-3">
          <button class="btn btn-xs btn-circle btn-text" data-zena-dismiss="${_.escape(n.id)}">
            <span class="icon-[tabler--x] text-base-content/80 size-4"></span>
          </button>
          <div class="bg-primary size-1.5 rounded-full"></div>
        </div>
      </div>
    </li>
    <li><hr class="border-base-content/20 -mx-3 my-1.5" /></li>
  `).join('');
}

function renderTeamTable(list) {
  // Replace demo Payment Status with Team table (keeps DataTable skin)
  // Expected item: { id, name, handle, role, plan, status, avatar_url }
  const tbody = document.querySelector('.datatables-users tbody');
  if (!tbody) return;
  const rows = (list || []).slice(0, 50).map((u, i) => `
    <tr>
      <td class="w-3.5">
        <div class="flex h-5 items-center">
          <input id="table-filter-${i+1}" type="checkbox" class="checkbox checkbox-sm select-single-user" data-datatable-row-selecting-individual=""/>
          <label for="table-filter-${i+1}" class="sr-only">Checkbox</label>
        </div>
      </td>
      <td>
        <div class="flex items-center gap-4">
          <div class="avatar"><div class="size-8.5 rounded-full"><img src="${u.avatar_url || 'https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png'}" alt="avatar"/></div></div>
          <div>
            <div class="text-base-content font-medium">${_.escape(u.name)}</div>
            <div class="text-base-content/80 text-sm">${_.escape(u.handle || u.email || '')}</div>
          </div>
        </div>
      </td>
      <td>
        <div class="flex items-center gap-4">
          <span class="icon-[tabler--user] size-6"></span>
          <p>${_.escape(u.role || 'Member')}</p>
        </div>
      </td>
      <td>${_.escape(u.plan || 'Tenant')}</td>
      <td><span class="badge badge-soft ${u.status === 'active' ? 'badge-success' : (u.status === 'pending' ? 'badge-warning' : 'badge-secondary')}">${_.escape(u.status || '—')}</span></td>
      <td>
        <div class="flex items-center">
          <button class="delete-record btn btn-circle btn-text" aria-label="Action button"><span class="icon-[tabler--trash] size-5"></span></button>
          <a class="btn btn-circle btn-text" aria-label="View" href="/app/team/${encodeURIComponent(u.id)}"><span class="icon-[tabler--eye] size-5"></span></a>
          <div class="dropdown relative inline-flex">
            <button type="button" class="dropdown-toggle btn btn-circle btn-text" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
              <span class="icon-[tabler--dots-vertical] size-5"></span>
            </button>
            <ul class="dropdown-menu dropdown-open:opacity-100 hidden w-auto p-1" role="menu" aria-orientation="vertical">
              <li><a class="dropdown-item cursor-pointer px-2 py-1.5 text-sm" href="/app/team/${encodeURIComponent(u.id)}/edit">Edit</a></li>
            </ul>
          </div>
        </div>
      </td>
    </tr>
  `).join('');
  tbody.innerHTML = rows;
  // Re-init DataTable AFTER injecting rows
  if (window.HSDataTable) new HSDataTable('#users-dashboard');
}

// Smart Search modal
const doSmartSearch = _.debounce(async (q) => {
  const outAll = document.querySelector('#search-tabs-1 .modal-body ul');
  if (!q || !outAll) return;
  try {
    const data = await apiGet(ZENA_API.SEARCH + encodeURIComponent(q));
    // Expected: { pages:[], projects:[], tasks:[], users:[] }
    const items = []
      .concat((data.pages || []).map(p => ({ icon: 'tabler--device-desktop-analytics', label: p.title, href: p.href })))
      .concat((data.projects || []).map(p => ({ icon: 'tabler--briefcase', label: p.name, href: `/app/projects/${p.id}` })))
      .concat((data.tasks || []).map(t => ({ icon: 'tabler--check', label: t.title, href: `/app/tasks/${t.id}` })))
      .concat((data.users || []).map(u => ({ icon: 'tabler--user', label: u.name, href: `/app/team/${u.id}` })));
    outAll.innerHTML = items.slice(0, 8).map(it => `
      <li><a class="hover:bg-base-200 rounded-field flex items-center gap-2 px-1 py-1.5" href="${it.href}">
        <span class="icon-[${it.icon}] size-6 shrink-0"></span>
        <h6 class="font-medium">${_.escape(it.label)}</h6>
      </a></li>
    `).join('');
  } catch (e) {
    outAll.innerHTML = `<li class="px-2 py-2 text-sm text-error">${_.escape(e.message)}</li>`;
  }
}, 250);

// Wire search input
document.addEventListener('input', (e) => {
  if (e.target && e.target.id === 'kbdInput') {
    doSmartSearch(e.target.value.trim());
  }
});

// Modal functions
function openSearchModal() {
  document.getElementById('search-modal').classList.remove('hidden');
  document.getElementById('kbdInput').focus();
}

function closeSearchModal() {
  document.getElementById('search-modal').classList.add('hidden');
}

// Alpine.js component
function zenaDashboard() {
    return {
        refreshing: false,
        kpiTrends: {},
        
        init() {
            console.log('🚀 ZenaManage Dashboard initialized');
            this.loadDashboardData();
        },
        
        async refreshData() {
            this.refreshing = true;
            console.log('🔄 Refreshing dashboard data...');
            await this.loadDashboardData();
            setTimeout(() => {
                this.refreshing = false;
            }, 1000);
        },
        
        async loadDashboardData() {
            try {
                // 1) KPIs
                try {
                    const kpis = await apiGet(ZENA_API.KPIS);
                    renderKPIs(kpis);
                    this.kpiTrends = kpis.trend || {};
                } catch (e) {
                    console.warn('KPI error:', e.message);
                    // Fallback data
                    renderKPIs({
                        projects_active: 12,
                        tasks_due_today: 7,
                        tasks_overdue: 3,
                        focus_minutes_today: 142,
                        revenue: { discount: 14987, profit: 1735, sale: 11548 }
                    });
                }

                // 2) Meetings (Calendar)
                try {
                    const meetings = await apiGet(ZENA_API.MEETINGS);
                    renderMeetings(meetings?.data || meetings || []);
                } catch (e) {
                    console.warn('Meetings error:', e.message);
                    // Fallback data
                    renderMeetings([
                        {
                            id: 1,
                            title: 'Project Review Meeting',
                            starts_at: '10:00 AM',
                            ends_at: '11:00 AM',
                            label: 'Meeting',
                            avatar_url: 'https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png'
                        },
                        {
                            id: 2,
                            title: 'Team Standup',
                            starts_at: '2:00 PM',
                            ends_at: '2:30 PM',
                            label: 'Standup',
                            avatar_url: 'https://cdn.flyonui.com/fy-assets/avatar/avatar-2.png'
                        }
                    ]);
                }

                // 3) Notifications
                try {
                    const noti = await apiGet(ZENA_API.NOTIFICATIONS);
                    renderNotifications(noti?.data || noti || []);
                } catch (e) {
                    console.warn('Notification error:', e.message);
                    // Fallback data
                    renderNotifications([
                        {
                            id: 1,
                            title: 'Task Completed',
                            time_ago: '2 hours ago',
                            category: 'Task',
                            actor_avatar: 'https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png'
                        },
                        {
                            id: 2,
                            title: 'New Comment',
                            time_ago: '4 hours ago',
                            category: 'Comment',
                            actor_avatar: 'https://cdn.flyonui.com/fy-assets/avatar/avatar-2.png'
                        }
                    ]);
                }

                // 4) Team table (demo dataset)
                try {
                    const team = await apiGet(ZENA_API.TEAM);
                    renderTeamTable(team?.data || team || []);
                } catch (e) {
                    console.warn('Team error:', e.message);
                    // Fallback data
                    renderTeamTable([
                        {
                            id: 1,
                            name: 'John Doe',
                            email: 'john@example.com',
                            role: 'Project Manager',
                            plan: 'Pro',
                            status: 'active',
                            avatar_url: 'https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png'
                        },
                        {
                            id: 2,
                            name: 'Jane Smith',
                            email: 'jane@example.com',
                            role: 'Developer',
                            plan: 'Basic',
                            status: 'active',
                            avatar_url: 'https://cdn.flyonui.com/fy-assets/avatar/avatar-2.png'
                        }
                    ]);
                }

                // 5) Delete button behavior (kept from sample—purely client-side)
                const { dataTable } = new HSDataTable('#users-dashboard');
                document.querySelector('.datatables-users tbody')?.addEventListener('click', (event) => {
                    if (event.target.closest('.delete-record')) {
                        const row = event.target.closest('tr');
                        if (row) dataTable.row(row).remove().draw();
                        // If you want server-side delete, call DELETE /api/v1/app/team/users/:id then refresh.
                    }
                });
                
            } catch (error) {
                console.error('❌ Error loading dashboard data:', error);
            }
        }
    };
}

// Global functions for modal
window.openSearchModal = openSearchModal;
window.closeSearchModal = closeSearchModal;

// Initialize on page load
window.addEventListener('load', async () => {
    console.log('📊 Dashboard template loaded successfully');
});
</script>

<style>
/* Custom styles for the dashboard template */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Badge styles */
.badge {
    @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
}

.badge-primary {
    @apply bg-blue-100 text-blue-800;
}

.badge-success {
    @apply bg-green-100 text-green-800;
}

.badge-warning {
    @apply bg-yellow-100 text-yellow-800;
}

.badge-secondary {
    @apply bg-gray-100 text-gray-800;
}

.badge-soft {
    @apply bg-gray-100 text-gray-600;
}

/* Button styles */
.btn {
    @apply inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md transition-colors duration-200;
}

.btn-circle {
    @apply rounded-full p-2;
}

.btn-text {
    @apply text-gray-600 hover:text-gray-900 hover:bg-gray-100;
}

.btn-xs {
    @apply px-2 py-1 text-xs;
}

/* Avatar styles */
.avatar {
    @apply relative;
}

.rounded-field {
    @apply rounded-lg;
}

.size-10 {
    @apply w-10 h-10;
}

.size-8.5 {
    @apply w-8 h-8;
}

.size-6 {
    @apply w-6 h-6;
}

.size-4 {
    @apply w-4 h-4;
}

.size-4.5 {
    @apply w-4 h-4;
}

.size-1.5 {
    @apply w-1.5 h-1.5;
}

/* Icon styles */
.icon-\[tabler--calendar\] {
    @apply w-4 h-4;
}

.icon-\[tabler--user\] {
    @apply w-6 h-6;
}

.icon-\[tabler--x\] {
    @apply w-4 h-4;
}

.icon-\[tabler--trash\] {
    @apply w-5 h-5;
}

.icon-\[tabler--eye\] {
    @apply w-5 h-5;
}

.icon-\[tabler--dots-vertical\] {
    @apply w-5 h-5;
}

.icon-\[tabler--device-desktop-analytics\] {
    @apply w-6 h-6;
}

.icon-\[tabler--briefcase\] {
    @apply w-6 h-6;
}

.icon-\[tabler--check\] {
    @apply w-6 h-6;
}

/* Dropdown styles */
.dropdown {
    @apply relative;
}

.dropdown-menu {
    @apply absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10;
}

.dropdown-item {
    @apply block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100;
}

/* Checkbox styles */
.checkbox {
    @apply rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50;
}

/* Table styles */
.datatables-users {
    @apply w-full;
}

.datatables-users th {
    @apply text-left py-3 px-4 font-medium text-gray-900;
}

.datatables-users td {
    @apply py-3 px-4;
}

/* Responsive design */
@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        @apply grid-cols-1;
    }
    
    .grid-cols-1.lg\\:grid-cols-3 {
        @apply grid-cols-1;
    }
}

/* Loading states */
[x-cloak] { 
    display: none !important; 
}

/* Focus states for accessibility */
button:focus,
input:focus {
    @apply outline-none ring-2 ring-blue-500 ring-opacity-50;
}

/* Hover effects */
.hover\\:shadow-xl:hover {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.hover\\:bg-blue-100:hover {
    @apply bg-blue-100;
}

.hover\\:text-gray-900:hover {
    @apply text-gray-900;
}

.hover\\:text-gray-600:hover {
    @apply text-gray-600;
}

.hover\\:bg-gray-100:hover {
    @apply bg-gray-100;
}

.hover\\:bg-base-200:hover {
    @apply bg-gray-100;
}
</style>
@endsection
