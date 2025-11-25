# Modern KPI Architecture Standards for ZenaManage

## 📊 **KPI Categories theo Kiến Trúc Hiện Đại**

### **1. Flow Metrics (Quan Trọng Nhất cho Agile/Lean)**
- **Throughput**: Số lượng tasks hoàn thành trong khoảng thời gian (tasks/week)
- **Cycle Time**: Thời gian trung bình từ khi task được assign đến khi hoàn thành
- **Lead Time**: Thời gian từ khi task được tạo đến khi hoàn thành
- **Work in Progress (WIP)**: Số lượng tasks đang được xử lý đồng thời

### **2. Predictability Metrics**
- **Velocity**: Tốc độ hoàn thành work (points/week hoặc tasks/week)
- **Forecast Accuracy**: Độ chính xác của dự đoán deadline
- **On-Time Delivery Rate**: Tỷ lệ projects/tasks hoàn thành đúng deadline

### **3. Quality Metrics**
- **Defect Rate**: Tỷ lệ tasks bị reject hoặc cần rework
- **Rework Rate**: Tỷ lệ công việc phải làm lại
- **First-Time Completion Rate**: Tỷ lệ tasks hoàn thành đúng ngay lần đầu

### **4. Business Value Metrics**
- **ROI**: Return on Investment (nếu có budget tracking)
- **Customer Satisfaction**: Điểm đánh giá từ clients
- **Value Delivery**: Tỷ lệ features/tasks tạo giá trị thực sự

### **5. Efficiency Metrics**
- **Resource Utilization**: Tỷ lệ sử dụng team capacity
- **Bottleneck Identification**: Identify where work is stuck
- **Average Response Time**: Thời gian phản hồi trung bình

### **6. Trend & Comparative Metrics**
- **Trend Indicators**: So sánh với period trước (+/- %)
- **Period-over-Period**: So sánh Week-over-Week, Month-over-Month
- **Target Achievement**: % đạt target so với mục tiêu

---

## 🎯 **Recommended KPI Set cho Dashboard**

### **Dashboard Level (4 KPIs chính)**

#### **Option 1: Flow-Focused (Modern Agile)**
1. **Throughput** (Tasks Completed This Week)
   - Value: 24 tasks
   - Trend: +12% vs last week
   - Action: "View Completed Tasks"

2. **Cycle Time** (Average Time to Complete)
   - Value: 3.2 days
   - Trend: -0.5 days vs last week (improving)
   - Action: "View Flow Analysis"

3. **Work in Progress** (Active Tasks)
   - Value: 18 tasks
   - Trend: +3 vs last week
   - Action: "Manage WIP"

4. **On-Time Delivery** (% Completed on Time)
   - Value: 87%
   - Trend: +5% vs last week
   - Action: "View Overdue Tasks"

#### **Option 2: Balanced (Traditional + Modern)**
1. **Active Projects** (Current Workload)
   - Value: 12 projects
   - Sub-metric: 3 overdue
   - Trend: +2 vs last month
   - Action: "View Active Projects"

2. **This Week's Throughput** (Productivity)
   - Value: 45 tasks completed
   - Trend: +8% vs last week
   - Action: "View Completed Tasks"

3. **Average Cycle Time** (Speed)
   - Value: 2.8 days
   - Trend: -0.3 days (faster)
   - Action: "Analyze Flow"

4. **Team Utilization** (Efficiency)
   - Value: 78% capacity
   - Trend: -5% (less overloaded)
   - Action: "View Team Status"

---

## 🔄 **KPI Design Principles**

### **1. Actionable Insights**
Mỗi KPI phải có:
- **Primary Value**: Số liệu chính (large, prominent)
- **Context**: Sub-metric hoặc comparison
- **Trend Indicator**: Arrow up/down với percentage
- **Action Button**: Gọi hành động cụ thể
- **Visual Indicator**: Color coding (green/yellow/red)

### **2. Real-Time Updates**
- Auto-refresh mỗi 60 giây
- Manual refresh button
- Loading states với skeleton
- Error handling với retry

### **3. Comparative Analysis**
- So sánh với period trước (last week, last month)
- Percentage change với color coding
- Sparkline charts cho trend visualization

### **4. Role-Based KPIs**
- **Executive**: Business value, ROI, customer satisfaction
- **Project Manager**: Throughput, on-time delivery, team utilization
- **Team Member**: Personal tasks, focus time, collaboration score
- **Client**: Project progress, milestone status, response time

---

## 📈 **KPI Card Structure (Modern)**

```tsx
interface ModernKpiCard {
  // Primary Metrics
  value: number | string;
  unit?: string; // 'tasks', 'days', '%', '$'
  
  // Context & Comparison
  subValue?: string; // "vs last week"
  trend?: {
    value: number; // percentage change
    direction: 'up' | 'down' | 'neutral';
    period: 'week' | 'month' | 'quarter';
  };
  
  // Visual Indicators
  sparkline?: number[]; // Mini trend chart data
  status?: 'good' | 'warning' | 'critical';
  
  // Actionability
  primaryAction?: {
    label: string;
    href: string;
  };
  
  // Metadata
  lastUpdated: string;
  refreshInterval?: number;
}
```

---

## ✅ **Implementation Checklist**

### **Phase 1: Core Metrics (Current)**
- [x] Total Projects
- [x] Total Tasks
- [x] Pending Tasks
- [x] Team Members

### **Phase 2: Flow Metrics (Next Priority)**
- [ ] Throughput (tasks/week)
- [ ] Cycle Time (average days)
- [ ] Work in Progress (active tasks)
- [ ] Lead Time (end-to-end)

### **Phase 3: Quality & Predictability**
- [ ] On-Time Delivery Rate
- [ ] Defect/Rework Rate
- [ ] Forecast Accuracy
- [ ] Velocity Trend

### **Phase 4: Business Value**
- [ ] ROI (if budget tracking enabled)
- [ ] Customer Satisfaction Score
- [ ] Value Delivery Rate
- [ ] Resource Utilization

---

## 🎨 **Visual Design Standards**

### **KPI Card Layout**
```
┌─────────────────────────────┐
│ [Icon]  Metric Label        │
│                             │
│    Large Value              │
│    Unit (if applicable)     │
│                             │
│ [Trend Arrow] ±X% vs period │
│ [Sparkline Chart]           │
│                             │
│ [Primary Action Button]     │
└─────────────────────────────┘
```

### **Color Coding**
- **Green**: Good performance, improving trend
- **Yellow**: Warning, needs attention
- **Red**: Critical, immediate action required
- **Blue**: Neutral information

### **Trend Indicators**
- **↑ Green**: Positive trend (improving)
- **↓ Red**: Negative trend (declining)
- **→ Gray**: Stable (no significant change)

---

## 📊 **Recommended Response Time**

- **KPI Load Time**: < 300ms (p95)
- **Refresh Interval**: 60 seconds (configurable)
- **Cache Duration**: 30 seconds per tenant
- **Data Freshness**: Real-time với 30s staleness tolerance

---

## 🔍 **Examples của Modern KPIs**

### **Example 1: Throughput Card**
```
┌─────────────────────────────┐
│ 📊 Tasks Completed This Week │
│                             │
│        24 tasks             │
│                             │
│    ↑ +12% vs last week      │
│    [small sparkline]        │
│                             │
│   [View Completed Tasks]    │
│   Last updated: 2 min ago    │
└─────────────────────────────┘
```

### **Example 2: Cycle Time Card**
```
┌─────────────────────────────┐
│ ⏱️  Average Cycle Time       │
│                             │
│       3.2 days              │
│                             │
│    ↓ -0.5 days (faster)     │
│    [trend chart]            │
│                             │
│   [View Flow Analysis]      │
│   Last updated: 2 min ago   │
└─────────────────────────────┘
```

---

## 🚀 **Next Steps**

1. **Update DashboardMetrics Type** để bao gồm flow metrics
2. **Add Trend Calculation** trong backend
3. **Implement Sparkline Charts** cho mini trends
4. **Add Comparative Periods** (last week, last month)
5. **Create Action Buttons** cho mỗi KPI
6. **Add Refresh Indicators** với last updated timestamp

