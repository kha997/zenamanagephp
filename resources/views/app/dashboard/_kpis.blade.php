{{-- App Dashboard KPIs --}}
<section class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Projects</p>
                        <p class="text-3xl font-bold" x-text="kpis.totalProjects">12</p>
                        <p class="text-blue-100 text-sm">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span x-text="kpis.projectGrowth">+8%</span> from last month
                        </p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-project-diagram text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Active Tasks</p>
                        <p class="text-3xl font-bold" x-text="kpis.activeTasks">45</p>
                        <p class="text-green-100 text-sm">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span x-text="kpis.taskGrowth">+15%</span> from last month
                        </p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-tasks text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Team Members</p>
                        <p class="text-3xl font-bold" x-text="kpis.teamMembers">8</p>
                        <p class="text-purple-100 text-sm">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span x-text="kpis.teamGrowth">+2%</span> from last month
                        </p>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Completion Rate</p>
                        <p class="text-3xl font-bold" x-text="kpis.completionRate">87%</p>
                        <p class="text-orange-100 text-sm">
                            <i class="fas fa-chart-line mr-1"></i>
                            Above target
                        </p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
