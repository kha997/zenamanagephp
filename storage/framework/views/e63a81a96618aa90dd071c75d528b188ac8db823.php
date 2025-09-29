
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    
    
    <div class="border rounded-lg p-6 transition-all duration-200 hover:shadow-md bg-blue-50 text-blue-700 border-blue-100" 
         @click="drillDownMfa()" 
         style="cursor: pointer;"
         :aria-label="`MFA Adoption: ${kpis.mfaAdoption?.value || 0}%, ${kpis.mfaAdoption?.deltaPct > 0 ? 'up' : 'down'} ${Math.abs(kpis.mfaAdoption?.deltaPct || 0)}% in 30 days`">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-600 mr-3">
                        <i class="fas fa-shield-check text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium leading-none mb-1">MFA Adoption</h3>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-bold tabular-nums" aria-live="polite" x-text="loading ? '--' : `${formatPercent(kpis.mfaAdoption?.value || 0)}%`"></span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2" x-show="!loading && kpis.mfaAdoption?.deltaPct !== null">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                          :class="getDeltaClass(kpis.mfaAdoption?.deltaPct)">
                        <i class="fas fa-arrow-up mr-1" x-show="kpis.mfaAdoption?.deltaPct > 0"></i>
                        <i class="fas fa-arrow-down mr-1" x-show="kpis.mfaAdoption?.deltaPct < 0"></i>
                        <i class="fas fa-minus mr-1" x-show="kpis.mfaAdoption?.deltaPct === 0"></i>
                        <span x-text="formatDelta(kpis.mfaAdoption?.deltaPct, '%')"></span>
                    </span>
                </div>
                
                
                <div class="mt-3" x-show="!loading && kpis.mfaAdoption?.series && kpis.mfaAdoption?.series.length > 1">
                    <div class="min-h-[32px] flex items-center">
                        <svg width="120" height="32" class="stroke-current opacity-60" viewBox="0 0 120 32">
                            <polyline :points="generateSparkline(kpis.mfaAdoption?.series)" 
                                      fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 min-h-[32px]" x-show="loading || !kpis.mfaAdoption?.series || kpis.mfaAdoption?.series?.length <= 1"></div>
            </div>
        </div>
    </div>
    
    
    <div class="border rounded-lg p-6 transition-all duration-200 hover:shadow-md bg-red-50 text-red-700 border-red-100" 
         @click="drillDownFailedLogins()" 
         style="cursor: pointer;"
         :aria-label="`Failed Logins: ${formatInt(kpis.failedLogins?.value || 0)}, ${kpis.failedLogins?.deltaAbs > 0 ? 'up' : 'down'} ${Math.abs(kpis.failedLogins?.deltaAbs || 0)} from yesterday`">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 mr-3">
                        <i class="fas fa-exclamation-triangle text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium leading-none mb-1">Failed Logins</h3>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-bold tabular-nums" aria-live="polite" x-text="loading ? '--' : formatInt(kpis.failedLogins?.value || 0)"></span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2" x-show="!loading && kpis.failedLogins?.deltaAbs !== null">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                          :class="getDeltaClass(-(kpis.failedLogins?.deltaAbs || 0), 'worse')">
                        <i class="fas fa-arrow-up mr-1" x-show="kpis.failedLogins?.deltaAbs > 0"></i>
                        <i class="fas fa-arrow-down mr-1" x-show="kpis.failedLogins?.deltaAbs < 0"></i>
                        <i class="fas fa-minus mr-1" x-show="kpis.failedLogins?.deltaAbs === 0"></i>
                        <span x-text="formatDelta(kpis.failedLogins?.deltaAbs, '')"></span>
                    </span>
                </div>
                
                
                <div class="mt-3" x-show="!loading && kpis.failedLogins?.series && kpis.failedLogins?.series.length > 1">
                    <div class="min-h-[32px] flex items-center">
                        <svg width="120" height="32" class="stroke-red-600 opacity-60" viewBox="0 0 120 32">
                            <polyline :points="generateSparkline(kpis.failedLogins?.series)" 
                                      fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 min-h-[32px]" x-show="loading || !kpis.failedLogins?.series || kpis.failedLogins?.series?.length <= 1"></div>
            </div>
        </div>
    </div>
    
    
    <div class="border rounded-lg p-6 transition-all duration-200 hover:shadow-md bg-amber-50 text-amber-700 border-amber-100" 
         @click="drillDownLockedAccounts()" 
         style="cursor: pointer;"
         :aria-label="`Locked Accounts: ${formatInt(kpis.lockedAccounts?.value || 0)}, ${kpis.lockedAccounts?.deltaAbs > 0 ? 'up' : 'down'} ${Math.abs(kpis.lockedAccounts?.deltaAbs || 0)} locked accounts`">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-600 mr-3">
                        <i class="fas fa-lock text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium leading-none mb-1">Locked Accounts</h3>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-bold tabular-nums" aria-live="polite" x-text="loading ? '--' : formatInt(kpis.lockedAccounts?.value || 0)"></span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2" x-show="!loading && kpis.lockedAccounts?.deltaAbs !== null">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                          :class="getDeltaClass(-(kpis.lockedAccounts?.deltaAbs || 0), 'worse')">
                        <i class="fas fa-arrow-up mr-1" x-show="kpis.lockedAccounts?.deltaAbs > 0"></i>
                        <i class="fas fa-arrow-down mr-1" x-show="kpis.lockedAccounts?.deltaAbs < 0"></i>
                        <i class="fas fa-minus mr-1" x-show="kpis.lockedAccounts?.deltaAbs === 0"></i>
                        <span x-text="formatDelta(kpis.lockedAccounts?.deltaAbs, '')"></span>
                    </span>
                </div>
                
                
                <div class="mt-3" x-show="!loading && kpis.lockedAccounts?.series && kpis.lockedAccounts?.series.length > 1">
                    <div class="min-h-[32px] flex items-center">
                        <svg width="120" height="32" class="stroke-amber-600 opacity-60" viewBox="0 0 120 32">
                            <polyline :points="generateSparkline(kpis.lockedAccounts?.series)" 
                                      fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 min-h-[32px]" x-show="loading || !kpis.lockedAccounts?.series || kpis.lockedAccounts?.series?.length <= 1"></div>
            </div>
        </div>
    </div>
    
    
    <div class="border rounded-lg p-6 transition-all duration-200 hover:shadow-md bg-indigo-50 text-indigo-700 border-indigo-100" 
         @click="drillDownActiveSessions()" 
         style="cursor: pointer;"
         :aria-label="`Active Sessions: ${formatInt(kpis.activeSessions?.value || 0)}, ${kpis.activeSessions?.deltaAbs > 0 ? 'up' : 'down'} ${Math.abs(kpis.activeSessions?.deltaAbs || 0)} from yesterday`">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 mr-3">
                        <i class="fas fa-activity text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium leading-none mb-1">Active Sessions</h3>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-bold tabular-nums" aria-live="polite" x-text="loading ? '--' : formatInt(kpis.activeSessions?.value || 0)"></span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2" x-show="!loading && kpis.activeSessions?.deltaAbs !== null">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <i class="fas fa-minus mr-1"></i>
                        <span x-text="formatDelta(kpis.activeSessions?.deltaAbs, '')"></span>
                    </span>
                </div>
                
                
                <div class="mt-3" x-show="!loading && kpis.activeSessions?.series && kpis.activeSessions?.series.length > 1">
                    <div class="min-h-[32px] flex items-center">
                        <svg width="120" height="32" class="stroke-indigo-600 opacity-60" viewBox="0 0 120 32">
                            <polyline :points="generateSparkline(kpis.activeSessions?.series)" 
                                      fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 min-h-[32px]" x-show="loading || !kpis.activeSessions?.series || kpis.activeSessions?.series?.length <= 1"></div>
            </div>
        </div>
    </div>
    
    
    <div class="border rounded-lg p-6 transition-all duration-200 hover:shadow-md bg-orange-50 text-orange-700 border-orange-100" 
         @click="drillDownRiskyKeys()" 
         style="cursor: pointer;"
         :aria-label="`API Keys at Risk: ${formatInt(kpis.riskyKeys?.value || 0)}, ${kpis.riskyKeys?.deltaAbs > 0 ? 'up' : 'down'} ${Math.abs(kpis.riskyKeys?.deltaAbs || 0)} from yesterday`">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-100 text-orange-600 mr-3">
                        <i class="fas fa-key text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium leading-none mb-1">API Keys at Risk</h3>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-2xl font-bold tabular-nums" aria-live="polite" x-text="loading ? '--' : formatInt(kpis.riskyKeys?.value || 0)"></span>
                        </div>
                    </div>
                </div>
                
                <div class="mt-2" x-show="!loading && kpis.riskyKeys?.deltaAbs !== null">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                          :class="getDeltaClass(-(kpis.riskyKeys?.deltaAbs || 0), 'worse')">
                        <i class="fas fa-arrow-up mr-1" x-show="kpis.riskyKeys?.deltaAbs > 0"></i>
                        <i class="fas fa-arrow-down mr-1" x-show="kpis.riskyKeys?.deltaAbs < 0"></i>
                        <i class="fas fa-minus mr-1" x-show="kpis.riskyKeys?.deltaAbs === 0"></i>
                        <span x-text="formatDelta(kpis.riskyKeys?.deltaAbs, '')"></span>
                    </span>
                </div>
                
                
                <div class="mt-3" x-show="!loading && kpis.riskyKeys?.series && kpis.riskyKeys?.series.length > 1">
                    <div class="min-h-[32px] flex items-center">
                        <svg width="120" height="32" class="stroke-orange-600 opacity-60" viewBox="0 0 120 32">
                            <polyline :points="generateSparkline(kpis.riskyKeys?.series)" 
                                      fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3 min-h-[32px]" x-show="loading || !kpis.riskyKeys?.series || kpis.riskyKeys?.series?.length <= 1"></div>
            </div>
        </div>
    </div>
    
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/security/_kpis.blade.php ENDPATH**/ ?>