<!-- Onboarding Tour Component -->
<div x-data="onboardingTour()" x-init="init()" class="onboarding-tour">
    <!-- Tour Overlay -->
    <div x-show="isActive" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black bg-opacity-50"
         style="display: none;">
    </div>

    <!-- Tooltip -->
    <div x-show="isActive && currentStep && currentStep.type === 'tooltip'" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed z-50 bg-white rounded-lg shadow-xl max-w-sm p-4"
         :style="tooltipStyle"
         style="display: none;">
        
        <!-- Tooltip Arrow -->
        <div class="absolute w-3 h-3 bg-white transform rotate-45" 
             :class="getTooltipArrowClass()"></div>
        
        <!-- Tooltip Content -->
        <div class="relative">
            <h3 class="text-lg font-semibold text-gray-900 mb-2" x-text="currentStep.title"></h3>
            <p class="text-sm text-gray-600 mb-4" x-text="currentStep.description"></p>
            
            <!-- Actions -->
            <div class="flex justify-between items-center">
                <div class="flex space-x-2">
                    <button @click="skipStep()" 
                            class="text-sm text-gray-500 hover:text-gray-700">
                        Skip
                    </button>
                    <button @click="skipAll()" 
                            class="text-sm text-gray-500 hover:text-gray-700">
                        Skip All
                    </button>
                </div>
                
                <div class="flex space-x-2">
                    <button x-show="currentStep.order > 1" 
                            @click="previousStep()"
                            class="px-3 py-1 text-sm text-gray-600 hover:text-gray-800">
                        Previous
                    </button>
                    <button @click="nextStep()" 
                            class="px-4 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div x-show="isActive && currentStep && currentStep.type === 'modal'" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <!-- Modal Image -->
                    <div x-show="currentStep.content && currentStep.content.image" 
                         class="mx-auto flex items-center justify-center h-32 w-32 rounded-full bg-blue-100 mb-4">
                        <img :src="currentStep.content.image" 
                             :alt="currentStep.title"
                             class="h-20 w-20 object-contain">
                    </div>
                    
                    <!-- Modal Content -->
                    <div class="text-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2" 
                            x-text="currentStep.title"></h3>
                        <p class="text-sm text-gray-500 mb-4" x-text="currentStep.description"></p>
                        
                        <!-- Features List -->
                        <div x-show="currentStep.content && currentStep.content.features" 
                             class="text-left mb-4">
                            <ul class="space-y-2">
                                <template x-for="feature in currentStep.content.features" :key="feature">
                                    <li class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        <span x-text="feature"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        
                        <!-- Next Steps -->
                        <div x-show="currentStep.content && currentStep.content.next_steps" 
                             class="text-left mb-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Next Steps:</h4>
                            <ul class="space-y-1">
                                <template x-for="step in currentStep.content.next_steps" :key="step">
                                    <li class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-arrow-right text-blue-500 mr-2"></i>
                                        <span x-text="step"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Actions -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="nextStep()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <span x-text="currentStep.key === 'onboarding_complete' ? 'Get Started' : 'Next'"></span>
                    </button>
                    
                    <button x-show="currentStep.key !== 'onboarding_complete'" 
                            @click="skipStep()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Skip
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Step Overlay -->
    <div x-show="isActive && currentStep && currentStep.type === 'interactive'" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black bg-opacity-50"
         style="display: none;">
        
        <!-- Interactive Step Content -->
        <div class="fixed z-50 bg-white rounded-lg shadow-xl max-w-md p-6"
             :style="tooltipStyle">
            
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                    <i class="fas fa-mouse-pointer text-blue-600 text-2xl"></i>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-900 mb-2" x-text="currentStep.title"></h3>
                <p class="text-sm text-gray-600 mb-4" x-text="currentStep.description"></p>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Click on the highlighted element to continue
                    </p>
                </div>
                
                <div class="flex justify-center space-x-3">
                    <button @click="skipStep()" 
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                        Skip
                    </button>
                    <button @click="skipAll()" 
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                        Skip All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Indicator -->
    <div x-show="isActive && progress" 
         class="fixed top-4 right-4 z-50 bg-white rounded-lg shadow-lg p-3"
         style="display: none;">
        <div class="flex items-center space-x-3">
            <div class="text-sm text-gray-600">
                <span x-text="progress.current_step || 0"></span> / <span x-text="progress.total_steps || 0"></span>
            </div>
            <div class="w-24 bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                     :style="`width: ${progress.progress_percentage || 0}%`"></div>
            </div>
        </div>
    </div>

    <!-- Help Button -->
    <button @click="startTour()" 
            x-show="!isActive && !isCompleted"
            class="fixed bottom-4 right-4 z-40 bg-blue-600 text-white rounded-full p-3 shadow-lg hover:bg-blue-700 transition-colors duration-200"
            title="Start Tour">
        <i class="fas fa-question-circle text-xl"></i>
    </button>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('onboardingTour', () => ({
        isActive: false,
        isCompleted: false,
        currentStep: null,
        steps: [],
        progress: null,
        tooltipStyle: {},
        targetElement: null,

        async init() {
            await this.checkOnboardingStatus();
            await this.loadSteps();
            
            // Auto-start tour for new users
            if (!this.isCompleted && this.steps.length > 0) {
                this.startTour();
            }
        },

        async checkOnboardingStatus() {
            try {
                const response = await fetch('/api/v1/app/onboarding/is-completed');
                const data = await response.json();
                
                if (data.success) {
                    this.isCompleted = data.data.is_completed;
                }
            } catch (error) {
                console.error('Failed to check onboarding status:', error);
            }
        },

        async loadSteps() {
            try {
                const response = await fetch('/api/v1/app/onboarding/steps');
                const data = await response.json();
                
                if (data.success) {
                    this.steps = data.data;
                }
            } catch (error) {
                console.error('Failed to load onboarding steps:', error);
            }
        },

        async loadProgress() {
            try {
                const response = await fetch('/api/v1/app/onboarding/progress');
                const data = await response.json();
                
                if (data.success) {
                    this.progress = data.data;
                }
            } catch (error) {
                console.error('Failed to load progress:', error);
            }
        },

        async startTour() {
            await this.loadProgress();
            
            if (this.progress && this.progress.next_step) {
                this.currentStep = this.progress.next_step;
                this.isActive = true;
                this.positionTooltip();
            }
        },

        async nextStep() {
            if (!this.currentStep) return;
            
            try {
                await this.completeStep();
                
                // Find next step
                const currentIndex = this.steps.findIndex(step => step.step.id === this.currentStep.id);
                const nextStepData = this.steps[currentIndex + 1];
                
                if (nextStepData) {
                    this.currentStep = nextStepData.step;
                    this.positionTooltip();
                } else {
                    this.finishTour();
                }
            } catch (error) {
                console.error('Failed to complete step:', error);
            }
        },

        async previousStep() {
            if (!this.currentStep) return;
            
            try {
                const currentIndex = this.steps.findIndex(step => step.step.id === this.currentStep.id);
                const previousStepData = this.steps[currentIndex - 1];
                
                if (previousStepData) {
                    this.currentStep = previousStepData.step;
                    this.positionTooltip();
                }
            } catch (error) {
                console.error('Failed to go to previous step:', error);
            }
        },

        async skipStep() {
            if (!this.currentStep) return;
            
            try {
                await this.skipCurrentStep();
                await this.nextStep();
            } catch (error) {
                console.error('Failed to skip step:', error);
            }
        },

        async skipAll() {
            try {
                const response = await fetch('/api/v1/app/onboarding/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (response.ok) {
                    this.finishTour();
                }
            } catch (error) {
                console.error('Failed to skip all steps:', error);
            }
        },

        async completeStep() {
            if (!this.currentStep) return;
            
            try {
                const response = await fetch(`/api/v1/app/onboarding/steps/${this.currentStep.id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        data: {
                            completed_at: new Date().toISOString()
                        }
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to complete step');
                }
            } catch (error) {
                console.error('Failed to complete step:', error);
                throw error;
            }
        },

        async skipCurrentStep() {
            if (!this.currentStep) return;
            
            try {
                const response = await fetch(`/api/v1/app/onboarding/steps/${this.currentStep.id}/skip`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        data: {
                            skipped_at: new Date().toISOString()
                        }
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to skip step');
                }
            } catch (error) {
                console.error('Failed to skip step:', error);
                throw error;
            }
        },

        positionTooltip() {
            if (!this.currentStep || !this.currentStep.target_element) {
                this.tooltipStyle = {
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%)'
                };
                return;
            }

            this.$nextTick(() => {
                const element = document.querySelector(this.currentStep.target_element);
                if (!element) {
                    this.tooltipStyle = {
                        top: '50%',
                        left: '50%',
                        transform: 'translate(-50%, -50%)'
                    };
                    return;
                }

                const rect = element.getBoundingClientRect();
                const position = this.currentStep.position || 'bottom';
                
                let top, left, transform;
                
                switch (position) {
                    case 'top':
                        top = rect.top - 10;
                        left = rect.left + rect.width / 2;
                        transform = 'translate(-50%, -100%)';
                        break;
                    case 'bottom':
                        top = rect.bottom + 10;
                        left = rect.left + rect.width / 2;
                        transform = 'translate(-50%, 0)';
                        break;
                    case 'left':
                        top = rect.top + rect.height / 2;
                        left = rect.left - 10;
                        transform = 'translate(-100%, -50%)';
                        break;
                    case 'right':
                        top = rect.top + rect.height / 2;
                        left = rect.right + 10;
                        transform = 'translate(0, -50%)';
                        break;
                    default:
                        top = rect.bottom + 10;
                        left = rect.left + rect.width / 2;
                        transform = 'translate(-50%, 0)';
                }

                this.tooltipStyle = {
                    top: `${top}px`,
                    left: `${left}px`,
                    transform: transform
                };

                // Highlight target element
                this.highlightElement(element);
            });
        },

        getTooltipArrowClass() {
            if (!this.currentStep) return '';
            
            const position = this.currentStep.position || 'bottom';
            
            switch (position) {
                case 'top':
                    return 'bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2';
                case 'bottom':
                    return 'top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2';
                case 'left':
                    return 'right-0 top-1/2 transform translate-x-1/2 -translate-y-1/2';
                case 'right':
                    return 'left-0 top-1/2 transform -translate-x-1/2 -translate-y-1/2';
                default:
                    return 'top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2';
            }
        },

        highlightElement(element) {
            // Remove previous highlights
            document.querySelectorAll('.onboarding-highlight').forEach(el => {
                el.classList.remove('onboarding-highlight');
            });
            
            // Add highlight to current element
            element.classList.add('onboarding-highlight');
            
            // Scroll element into view
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        },

        finishTour() {
            this.isActive = false;
            this.isCompleted = true;
            this.currentStep = null;
            
            // Remove highlights
            document.querySelectorAll('.onboarding-highlight').forEach(el => {
                el.classList.remove('onboarding-highlight');
            });
            
            // Show completion message
            this.showCompletionMessage();
        },

        showCompletionMessage() {
            // You can implement a toast notification here
            console.log('Onboarding tour completed!');
        }
    }));
});
</script>

<style>
.onboarding-highlight {
    position: relative;
    z-index: 40;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.5);
    border-radius: 8px;
}

.onboarding-tour .fixed {
    pointer-events: none;
}

.onboarding-tour .fixed > * {
    pointer-events: auto;
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/feedback/onboarding-tour.blade.php ENDPATH**/ ?>