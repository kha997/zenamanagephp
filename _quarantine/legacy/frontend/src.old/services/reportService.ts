import jsPDF from 'jspdf'
import html2canvas from 'html2canvas'
import { Project, Task, User } from '../types'

export interface ReportData {
  projects: Project[]
  tasks: Task[]
  users: User[]
  dateRange: {
    start: Date
    end: Date
  }
  filters?: {
    status?: string[]
    priority?: string[]
    assignee?: string[]
  }
}

export interface ReportOptions {
  format: 'pdf' | 'excel' | 'csv'
  includeCharts: boolean
  includeDetails: boolean
  includeSummary: boolean
}

class ReportService {
  // Generate PDF Report
  async generatePDFReport(data: ReportData, options: ReportOptions): Promise<Blob> {
    const pdf = new jsPDF('p', 'mm', 'a4')
    
    // Add title
    pdf.setFontSize(20)
    pdf.text('Project Management Report', 20, 20)
    
    // Add date range
    pdf.setFontSize(12)
    pdf.text(
      `Period: ${data.dateRange.start.toLocaleDateString()} - ${data.dateRange.end.toLocaleDateString()}`,
      20,
      30
    )
    
    // Add summary
    if (options.includeSummary) {
      this.addSummarySection(pdf, data)
    }
    
    // Add project details
    if (options.includeDetails) {
      this.addProjectDetails(pdf, data.projects)
    }
    
    // Add task details
    if (options.includeDetails) {
      this.addTaskDetails(pdf, data.tasks)
    }
    
    return pdf.output('blob')
  }

  // Generate Excel Report
  async generateExcelReport(data: ReportData, options: ReportOptions): Promise<Blob> {
    // This would typically use a library like xlsx
    // For now, we'll return a CSV format
    const csvContent = this.generateCSVContent(data)
    return new Blob([csvContent], { type: 'text/csv' })
  }

  // Generate CSV Report
  async generateCSVReport(data: ReportData, options: ReportOptions): Promise<Blob> {
    const csvContent = this.generateCSVContent(data)
    return new Blob([csvContent], { type: 'text/csv' })
  }

  // Generate report from HTML element
  async generateReportFromElement(elementId: string, filename: string): Promise<void> {
    const element = document.getElementById(elementId)
    if (!element) {
      throw new Error('Element not found')
    }

    const canvas = await html2canvas(element, {
      scale: 2,
      useCORS: true,
      allowTaint: true
    })

    const imgData = canvas.toDataURL('image/png')
    const pdf = new jsPDF('p', 'mm', 'a4')
    
    const imgWidth = 210
    const pageHeight = 295
    const imgHeight = (canvas.height * imgWidth) / canvas.width
    let heightLeft = imgHeight

    let position = 0

    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
    heightLeft -= pageHeight

    while (heightLeft >= 0) {
      position = heightLeft - imgHeight
      pdf.addPage()
      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
      heightLeft -= pageHeight
    }

    pdf.save(`${filename}.pdf`)
  }

  // Download report
  downloadReport(blob: Blob, filename: string, mimeType: string): void {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  // Private helper methods
  private addSummarySection(pdf: jsPDF, data: ReportData): void {
    pdf.setFontSize(16)
    pdf.text('Summary', 20, 50)
    
    pdf.setFontSize(12)
    pdf.text(`Total Projects: ${data.projects.length}`, 20, 60)
    pdf.text(`Total Tasks: ${data.tasks.length}`, 20, 70)
    pdf.text(`Total Users: ${data.users.length}`, 20, 80)
    
    const completedProjects = data.projects.filter(p => p.status === 'completed').length
    const completionRate = data.projects.length > 0 ? (completedProjects / data.projects.length) * 100 : 0
    pdf.text(`Completion Rate: ${completionRate.toFixed(1)}%`, 20, 90)
  }

  private addProjectDetails(pdf: jsPDF, projects: Project[]): void {
    pdf.setFontSize(16)
    pdf.text('Project Details', 20, 110)
    
    pdf.setFontSize(10)
    let yPosition = 120
    
    projects.forEach((project, index) => {
      if (yPosition > 280) {
        pdf.addPage()
        yPosition = 20
      }
      
      pdf.text(`${index + 1}. ${project.name}`, 20, yPosition)
      pdf.text(`   Status: ${project.status}`, 25, yPosition + 5)
      pdf.text(`   Progress: ${project.progress}%`, 25, yPosition + 10)
      pdf.text(`   Cost: $${project.actual_cost}`, 25, yPosition + 15)
      
      yPosition += 25
    })
  }

  private addTaskDetails(pdf: jsPDF, tasks: Task[]): void {
    pdf.setFontSize(16)
    pdf.text('Task Details', 20, 200)
    
    pdf.setFontSize(10)
    let yPosition = 210
    
    tasks.forEach((task, index) => {
      if (yPosition > 280) {
        pdf.addPage()
        yPosition = 20
      }
      
      pdf.text(`${index + 1}. ${task.name}`, 20, yPosition)
      pdf.text(`   Status: ${task.status}`, 25, yPosition + 5)
      pdf.text(`   Priority: ${task.priority}`, 25, yPosition + 10)
      pdf.text(`   Assignee: ${task.user?.name || 'Unassigned'}`, 25, yPosition + 15)
      
      yPosition += 25
    })
  }

  private generateCSVContent(data: ReportData): string {
    let csv = 'Project Management Report\n\n'
    csv += `Period,${data.dateRange.start.toLocaleDateString()} - ${data.dateRange.end.toLocaleDateString()}\n`
    csv += `Total Projects,${data.projects.length}\n`
    csv += `Total Tasks,${data.tasks.length}\n`
    csv += `Total Users,${data.users.length}\n\n`
    
    // Projects CSV
    csv += 'Projects\n'
    csv += 'Name,Status,Progress,Cost,Start Date,End Date\n'
    data.projects.forEach(project => {
      csv += `"${project.name}","${project.status}",${project.progress},${project.actual_cost},"${project.start_date}","${project.end_date}"\n`
    })
    
    csv += '\n'
    
    // Tasks CSV
    csv += 'Tasks\n'
    csv += 'Name,Status,Priority,Assignee,Project,Start Date,End Date\n'
    data.tasks.forEach(task => {
      csv += `"${task.name}","${task.status}","${task.priority}","${task.user?.name || 'Unassigned'}","${task.project?.name || 'No Project'}","${task.start_date}","${task.end_date}"\n`
    })
    
    return csv
  }
}

export const reportService = new ReportService()
